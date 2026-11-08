<?php
/**
 * Descanso semanal e percentual de hora extra por dia da semana.
 *
 * ── O problema que isto resolve
 *
 * A decisão entre a faixa comum (he50) e a extraordinária (he100) saía de uma
 * regra única, em diaDetalhePonto(): dia sem jornada prevista pagava tudo na
 * extraordinária. Por isso um sábado de folga trabalhado pagava 100%, sem ter
 * como dizer que naquele parâmetro ele vale 50% — o que várias convenções
 * coletivas exigem.
 *
 * ── Por que por dia da semana, e não por dia do ciclo da escala
 *
 * "Sábado" só coincidiria com um dia do ciclo se a periodicidade fosse 7 e a
 * data de início caísse na segunda. Com periodicidade 5 ou 12, dia do ciclo não
 * tem relação com dia da semana. Por dia da semana a regra também passa a valer
 * para o regime de horas por dia, que tem o mesmo problema: sábado com jornada
 * 00:00 paga 100%.
 *
 * ── Por que só dois percentuais
 *
 * O sistema tem exatamente duas faixas de hora extra no espelho, no endosso e
 * nas exportações, e os campos para_tx_percHESemanal e para_tx_percHEEx são os
 * rótulos delas. Um terceiro percentual não teria onde ser pago, então a tela
 * oferece as duas que existem em vez de um campo livre que a folha não entrega.
 *
 * Compartilhado entre o cadastro de parâmetro (que grava e desenha) e o
 * funcoes_ponto.php (que calcula), para a regra não existir em duas versões.
 */

if (!function_exists("diasDaSemanaParametro")) {
	/** Nomes dos dias, na ordem que o date("w") do PHP usa. */
	function diasDaSemanaParametro(): array {
		return [
			0 => "Domingo",
			1 => "Segunda-feira",
			2 => "Terça-feira",
			3 => "Quarta-feira",
			4 => "Quinta-feira",
			5 => "Sexta-feira",
			6 => "Sábado",
		];
	}
}

if (!function_exists("diaSemanaTabelaExiste")) {
	/**
	 * A tabela é criada na primeira abertura do cadastro de parâmetro. Até lá,
	 * e em qualquer base onde a migração não tenha rodado, vale a regra antiga
	 * em vez de erro.
	 */
	function diaSemanaTabelaExiste(): bool {
		static $existe = null;
		if ($existe === null) {
			$r = query("SHOW TABLES LIKE 'parametro_dia_semana'");
			$existe = ($r && mysqli_num_rows($r) > 0);
		}
		return $existe;
	}
}

if (!function_exists("diaSemanaGarantirTabela")) {
	/** Cria a tabela, no padrão de migração automática do sistema. */
	function diaSemanaGarantirTabela(): void {
		query("CREATE TABLE IF NOT EXISTS parametro_dia_semana (
			pdsm_nb_id INT AUTO_INCREMENT PRIMARY KEY,
			pdsm_nb_parametro INT NOT NULL,
			pdsm_nb_diaSemana TINYINT NOT NULL COMMENT '0=domingo ... 6=sabado',
			pdsm_tx_ehDsr ENUM('sim','nao') NOT NULL DEFAULT 'nao',
			pdsm_nb_percTrabalhado TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Percentual quando o dia e trabalhado. NULL = regra padrao',
			UNIQUE KEY uq_parametro_dia (pdsm_nb_parametro, pdsm_nb_diaSemana),
			KEY idx_parametro (pdsm_nb_parametro)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	}
}

if (!function_exists("carregarDiasSemanaParametro")) {
	/**
	 * Configuração gravada dos sete dias de um parâmetro.
	 *
	 * Fica em cache por parâmetro: o espelho pergunta uma vez por dia do mês, e
	 * sem o cache seriam mais de trinta consultas iguais por funcionário.
	 *
	 * @return array<int, array{ehDsr: string, perc: int|null}>
	 */
	function carregarDiasSemanaParametro($idParametro, bool $recarregar = false): array {
		static $cache = [];

		$idParametro = intval($idParametro);
		if ($idParametro <= 0) {
			return [];
		}
		if ($recarregar) {
			unset($cache[$idParametro]);
		}
		if (array_key_exists($idParametro, $cache)) {
			return $cache[$idParametro];
		}

		$cache[$idParametro] = [];
		if (!diaSemanaTabelaExiste()) {
			return $cache[$idParametro];
		}

		$linhas = mysqli_fetch_all(query(
			"SELECT pdsm_nb_diaSemana, pdsm_tx_ehDsr, pdsm_nb_percTrabalhado
				FROM parametro_dia_semana
				WHERE pdsm_nb_parametro = {$idParametro}
				ORDER BY pdsm_nb_diaSemana ASC;"
		), MYSQLI_ASSOC);

		foreach (($linhas ?: []) as $linha) {
			$cache[$idParametro][intval($linha["pdsm_nb_diaSemana"])] = [
				"ehDsr" => strval($linha["pdsm_tx_ehDsr"]),
				"perc"  => $linha["pdsm_nb_percTrabalhado"],
			];
		}

		return $cache[$idParametro];
	}
}

if (!function_exists("salvarDiasSemanaParametro")) {
	/**
	 * Grava a configuração dos sete dias.
	 *
	 * @param array $dados normalmente o $_POST: diaDsr (0..6 ou vazio) e
	 *                     percDiaSemana[0..6]
	 * @return int quantos dias foram gravados
	 */
	function salvarDiasSemanaParametro($idParametro, array $dados): int {
		$idParametro = intval($idParametro);
		if ($idParametro <= 0) {
			return 0;
		}
		diaSemanaGarantirTabela();

		/* Lista de dias, não um só: escala 5x2 tem dois dias de descanso, e há
		   convenção com DSR em dias diferentes por semana. Aceita também o
		   formato antigo, de valor único, para não perder o que já foi gravado
		   se algum fluxo ainda mandar assim. */
		$diasDsr = $dados["diaDsr"] ?? [];
		if (!is_array($diasDsr)) {
			$diasDsr = trim(strval($diasDsr)) !== "" ? [$diasDsr] : [];
		}
		$diasDsr = array_map("intval", array_filter($diasDsr, function ($v) {
			return trim(strval($v)) !== "";
		}));

		$percPorDia = is_array($dados["percDiaSemana"] ?? null) ? $dados["percDiaSemana"] : [];

		$gravados = 0;
		foreach (array_keys(diasDaSemanaParametro()) as $num) {
			$ehDsr = in_array($num, $diasDsr, true) ? "sim" : "nao";

			/* Percentual em branco fica NULL de propósito: NULL quer dizer
			   "mantém a regra padrão", enquanto 0 seria um percentual. */
			$percBruto = trim(strval($percPorDia[$num] ?? ""));
			$perc = $percBruto !== "" ? intval($percBruto) : null;

			/* Todos os valores saem de intval() ou de literal fixo: não há
			   string da tela indo para o SQL. */
			query(
				"INSERT INTO parametro_dia_semana
						(pdsm_nb_parametro, pdsm_nb_diaSemana, pdsm_tx_ehDsr, pdsm_nb_percTrabalhado)
					VALUES ({$idParametro}, {$num}, '{$ehDsr}', " . ($perc === null ? "NULL" : intval($perc)) . ")
					ON DUPLICATE KEY UPDATE
						pdsm_tx_ehDsr = VALUES(pdsm_tx_ehDsr),
						pdsm_nb_percTrabalhado = VALUES(pdsm_nb_percTrabalhado);"
			);
			$gravados++;
		}

		/* O cache precisa cair, senão a mesma requisição que gravou continuaria
		   lendo a configuração antiga. */
		carregarDiasSemanaParametro($idParametro, true);

		return $gravados;
	}
}

if (!function_exists("parametroDiaSemana")) {
	/**
	 * Configuração do dia da semana da data, para o funcionário.
	 *
	 * @return array{diaSemana: int, ehDsr: bool, perc: int|null}
	 */
	function parametroDiaSemana(array $motorista, string $data): array {
		$diaSemana = intval(date("w", strtotime($data)));
		$vazio = ["diaSemana" => $diaSemana, "ehDsr" => false, "perc" => null];

		/* Quem já carregou a configuração junto do parâmetro usa a dele; quem
		   não carregou cai no carregarDiasSemanaParametro(), que tem cache. */
		$config = (!empty($motorista["diasSemana"]) && is_array($motorista["diasSemana"]))
			? $motorista["diasSemana"]
			: carregarDiasSemanaParametro($motorista["enti_nb_parametro"] ?? 0);

		if (empty($config[$diaSemana])) {
			return $vazio;
		}

		$cfg = $config[$diaSemana];
		return [
			"diaSemana" => $diaSemana,
			"ehDsr"     => (($cfg["ehDsr"] ?? "nao") === "sim"),
			"perc"      => (isset($cfg["perc"]) && trim(strval($cfg["perc"])) !== "")
				? intval($cfg["perc"])
				: null,
		];
	}
}

if (!function_exists("diaSemanaFaixaComum")) {
	/**
	 * Diz se o percentual configurado para o dia é a faixa comum (he50) em vez
	 * da extraordinária (he100).
	 *
	 * A comparação é com o percentual do próprio parâmetro, não com o número
	 * 100: há empresa com extraordinária em 110%.
	 */
	function diaSemanaFaixaComum(array $motorista, ?int $perc): bool {
		if ($perc === null) {
			return false;
		}
		$percExtraordinaria = trim(strval($motorista["para_tx_percHEEx"] ?? "")) !== ""
			? intval($motorista["para_tx_percHEEx"])
			: 100;

		return $perc < $percExtraordinaria;
	}
}

if (!function_exists("blocoDiasSemanaParametro")) {
	/**
	 * Bloco "Descanso semanal e percentual por dia da semana" do formulário.
	 *
	 * Um radio para dizer qual dia é o DSR (um só por parâmetro) e, em cada
	 * linha, o percentual pago se a pessoa trabalhar naquele dia.
	 */
	function blocoDiasSemanaParametro(?array $a_mod = null): string {
		/* Aceita null de propósito: no cadastro novo, e num GET sem POST, o
		   $a_mod da tela ainda não existe. Com o parâmetro tipado só como
		   "array" isso virava TypeError e a página inteira ficava em branco
		   depois do título. */
		$a_mod = is_array($a_mod) ? $a_mod : [];

		$dias = diasDaSemanaParametro();

		$percSemanal = trim(strval($a_mod["para_tx_percHESemanal"] ?? "")) !== "" ? intval($a_mod["para_tx_percHESemanal"]) : 50;
		$percExtra   = trim(strval($a_mod["para_tx_percHEEx"] ?? "")) !== "" ? intval($a_mod["para_tx_percHEEx"]) : 100;

		$config = $a_mod["diasSemana"] ?? [];

		$linhas = "";
		foreach ($dias as $num => $nome) {
			$cfg = $config[$num] ?? [];
			$ehDsr = (($cfg["ehDsr"] ?? "nao") === "sim");
			$percAtual = isset($cfg["perc"]) && trim(strval($cfg["perc"])) !== "" ? intval($cfg["perc"]) : null;

			$opcoes = "<option value=''>padrão (" . $percExtra . "%)</option>";
			foreach ([$percSemanal => "H.E. semanal", $percExtra => "H.E. extraordinária"] as $valor => $rotulo) {
				$opcoes .= "<option value='" . intval($valor) . "'"
					. ($percAtual !== null && $percAtual === intval($valor) ? " selected" : "")
					. ">" . intval($valor) . "% — " . $rotulo . "</option>";
			}

			/* O nome do dia é um <label for> da caixa: clicar no nome marca o
			   dia. O radio de antes era um alvo de 13px e só ele respondia ao
			   clique — era difícil acertar. */
			$linhas .= "<tr" . ($ehDsr ? " style='background:#eff6ff;'" : "") . ">"
				. "<td style='padding:0; border-bottom:1px solid #f1f1f1;'>"
					. "<label for='dsr_" . $num . "' style='display:block; margin:0; padding:9px 16px 9px 4px; font-weight:" . ($ehDsr ? "600" : "normal") . "; cursor:pointer; white-space:nowrap;'>"
						. $nome
					. "</label>"
				. "</td>"
				. "<td style='padding:0; text-align:center; border-bottom:1px solid #f1f1f1;'>"
					. "<label for='dsr_" . $num . "' style='display:block; margin:0; padding:9px 18px; cursor:pointer;'>"
						. "<input type='checkbox' id='dsr_" . $num . "' name='diaDsr[]' value='" . $num . "'"
						. ($ehDsr ? " checked" : "")
						. " style='width:18px; height:18px; vertical-align:middle; cursor:pointer;'>"
					. "</label>"
				. "</td>"
				. "<td style='padding:5px 0 5px 6px; border-bottom:1px solid #f1f1f1;'>"
					. "<select name='percDiaSemana[" . $num . "]' class='form-control input-sm' style='min-width:215px;'>"
					. $opcoes
					. "</select>"
				. "</td>"
			. "</tr>";
		}

		return "<div style='clear:both; width:100%; padding:10px 15px 5px 15px;'>"
			. "<div style='font-weight:bold;'>Descanso semanal e percentual por dia da semana</div>"
			. "<small class='text-muted' style='display:block; margin-bottom:8px; max-width:720px;'>"
				. "Marque quantos dias de descanso o regime tiver — numa 5x2 são dois. "
				. "O percentual vale quando a pessoa trabalha num dia <strong>sem jornada prevista</strong> "
				. "— folga, DSR, sábado não escalado. Hoje esses casos pagam sempre a faixa extraordinária. "
				. "Dia com jornada prevista continua como está: o excesso na faixa semanal, e a "
				. "extraordinária só acima do limite diário."
			. "</small>"
			. "<table style='border-collapse:collapse; border:1px solid #e5e5e5;'>"
				. "<tr style='background:#fafafa;'>"
					. "<th style='padding:6px 16px 6px 4px; text-align:left; font-weight:600;'>Dia</th>"
					. "<th style='padding:6px 18px; font-weight:600;'>DSR</th>"
					. "<th style='padding:6px 0 6px 6px; text-align:left; font-weight:600;'>% se trabalhado</th>"
				. "</tr>"
				. $linhas
			. "</table>"
		. "</div>";
	}
}
