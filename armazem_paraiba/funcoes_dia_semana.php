<?php
/**
 * Descanso semanal e percentual de hora extra do dia.
 *
 * ── O problema que isto resolve
 *
 * A decisão entre a faixa comum (he50) e a extraordinária (he100) saía de uma
 * regra única, em diaDetalhePonto(): dia sem jornada prevista pagava tudo na
 * extraordinária. Por isso um sábado de folga trabalhado pagava 100%, sem ter
 * como dizer que naquele parâmetro ele vale 50% — o que várias convenções
 * coletivas exigem.
 *
 * ── Onde a marca de DSR mora
 *
 * Na própria linha de escala_dia, junto do início, do fim e do intervalo
 * daquele dia do ciclo. É o quadrado "Dia N" que a pessoa já enxerga ao montar
 * a escala, e por ser o dia do ciclo funciona com periodicidade 5 ou 12, onde
 * dia do ciclo não tem relação nenhuma com dia da semana — com periodicidade
 * diferente de 7 a tela nem mostra o "(Seg)".
 *
 * ── Só no regime de escala
 *
 * O regime de horas por dia não tem DSR: o cadastro dele é "Dias Úteis" e
 * "Sábado", e não há quadrado de dia onde marcar. Houve uma versão com uma
 * tabela parametro_dia_semana para atender esse regime; ela saiu por não ser
 * necessária. A tabela pode ter sido criada em alguma base — ninguém a lê mais.
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

if (!function_exists("diaSemanaDiaDoCiclo")) {
	/**
	 * Em que dia do ciclo da escala a data cai, de 1 até a periodicidade.
	 *
	 * A fórmula estava escrita duas vezes dentro do funcoes_ponto.php, nos dois
	 * lugares que resolvem o dia da escala. Veio para cá porque o DSR também
	 * precisa dela, e três cópias de uma conta de calendário é pedir para elas
	 * divergirem.
	 *
	 * @return int|null null quando o parâmetro não tem escala carregada
	 */
	function diaSemanaDiaDoCiclo(array $motorista, string $data): ?int {
		$periodicidade = intval($motorista["esca_nb_periodicidade"] ?? 0);
		$dataInicio = trim(strval($motorista["esca_tx_dataInicio"] ?? ""));

		if ($periodicidade <= 0 || $dataInicio === "") {
			return null;
		}

		$diferenca = (new DateTime($dataInicio))->diff(new DateTime($data));
		$diferenca = $diferenca->days * ($diferenca->invert ? -1 : 1);

		return intval(round(
			$periodicidade * (($diferenca) / ($periodicidade) - floor($diferenca / $periodicidade)) + 1
		));
	}
}

if (!function_exists("diaSemanaColunasEscalaExistem")) {
	/**
	 * Se as colunas de DSR já existem em escala_dia.
	 *
	 * Enquanto não existirem vale a regra antiga, em vez de erro: a base de um
	 * cliente só ganha as colunas na primeira abertura do cadastro de
	 * parâmetro, e o cálculo pode rodar antes disso.
	 */
	function diaSemanaColunasEscalaExistem(bool $recarregar = false): bool {
		static $existe = null;
		if ($recarregar) {
			$existe = null;
		}
		if ($existe === null) {
			$r = query("SHOW COLUMNS FROM escala_dia LIKE 'esca_tx_ehDsr'");
			$existe = ($r && mysqli_num_rows($r) > 0);
		}
		return $existe;
	}
}

if (!function_exists("diaSemanaGarantirColunasEscala")) {
	/**
	 * Acrescenta as duas colunas de DSR em escala_dia.
	 *
	 * Confere coluna por coluna com SHOW COLUMNS em vez de usar
	 * "ADD COLUMN IF NOT EXISTS": esse IF NOT EXISTS é do MariaDB, e os
	 * servidores de homologação e de produção são MySQL, onde ele é erro de
	 * sintaxe — a página inteira voltaria 500.
	 */
	function diaSemanaGarantirColunasEscala(): void {
		$colunas = [
			"esca_tx_ehDsr"          => "ENUM('sim','nao') NOT NULL DEFAULT 'nao'",
			"esca_nb_percTrabalhado" => "TINYINT UNSIGNED NULL DEFAULT NULL",
		];

		foreach ($colunas as $coluna => $tipo) {
			/* Nome e tipo são literais daqui, não vem nada da tela. */
			$r = query("SHOW COLUMNS FROM escala_dia LIKE '{$coluna}'");
			if ($r && mysqli_num_rows($r) > 0) {
				continue;
			}
			query("ALTER TABLE escala_dia ADD COLUMN {$coluna} {$tipo}");
		}

		diaSemanaColunasEscalaExistem(true);
	}
}

if (!function_exists("carregarEscalaParametro")) {
	/**
	 * A escala de um parâmetro: data de início, periodicidade e os dias.
	 *
	 * Existe porque só o funcoes_ponto.php carrega a escala junto do
	 * funcionário. O espelho monta o $motorista com um SELECT sem join na
	 * escala, e o endosso, o cadastro_endosso, a não conformidade e os painéis
	 * fazem igual — sem isto o DSR da escala não apareceria em nenhuma dessas
	 * telas, só no cálculo.
	 *
	 * Fica em cache por parâmetro: o espelho pergunta uma vez por dia do mês.
	 */
	function carregarEscalaParametro($idParametro, bool $recarregar = false): array {
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

		$escala = mysqli_fetch_assoc(query(
			"SELECT esca_nb_id, esca_tx_dataInicio, esca_nb_periodicidade
				FROM escala WHERE esca_nb_parametro = {$idParametro} LIMIT 1;"
		));
		if (empty($escala)) {
			return $cache[$idParametro];
		}

		$dias = mysqli_fetch_all(query(
			"SELECT * FROM escala_dia
				WHERE esca_nb_escala = " . intval($escala["esca_nb_id"]) . "
				ORDER BY esca_nb_numeroDia ASC;"
		), MYSQLI_ASSOC);

		$cache[$idParametro] = [
			"esca_tx_dataInicio"    => $escala["esca_tx_dataInicio"],
			"esca_nb_periodicidade" => $escala["esca_nb_periodicidade"],
			"diasEscala"            => $dias ?: [],
		];

		return $cache[$idParametro];
	}
}

if (!function_exists("diaSemanaEscalaDoDia")) {
	/**
	 * DSR e percentual da linha de escala_dia em que a data cai.
	 *
	 * @return array{ehDsr: bool, perc: int|null}|null null quando não há escala
	 *         carregada, a data não resolve num dia do ciclo, ou as colunas
	 *         ainda não existem naquela base
	 */
	function diaSemanaEscalaDoDia(array $motorista, string $data): ?array {
		/* Quem já tem a escala em mãos usa a dele, sem consulta: é o caso do
		   cálculo, que a carrega junto do funcionário. Quem não tem — o
		   espelho e as outras telas — cai no carregarEscalaParametro(), que
		   tem cache. */
		$temEscala = (!empty($motorista["diasEscala"]) && is_array($motorista["diasEscala"]))
			&& trim(strval($motorista["esca_tx_dataInicio"] ?? "")) !== "";

		if (!$temEscala) {
			$daBase = carregarEscalaParametro($motorista["enti_nb_parametro"] ?? 0);
			if (empty($daBase["diasEscala"])) {
				return null;
			}
			$motorista = array_merge($motorista, $daBase);
		}

		$diaDoCiclo = diaSemanaDiaDoCiclo($motorista, $data);
		if ($diaDoCiclo === null) {
			return null;
		}

		foreach ($motorista["diasEscala"] as $d) {
			if (!is_array($d) || intval($d["esca_nb_numeroDia"] ?? 0) !== $diaDoCiclo) {
				continue;
			}

			/* Chave ausente é base sem a migração: cai no padrão de antes. */
			$perc = $d["esca_nb_percTrabalhado"] ?? null;

			return [
				"ehDsr" => (strval($d["esca_tx_ehDsr"] ?? "nao") === "sim"),
				"perc"  => (trim(strval($perc)) !== "") ? intval($perc) : null,
			];
		}

		return null;
	}
}

if (!function_exists("parametroDiaSemana")) {
	/**
	 * DSR e percentual do dia, para o funcionário.
	 *
	 * Só o regime de escala tem DSR; qualquer outro devolve vazio, que é a
	 * regra de antes desta funcionalidade. O chamador não precisa saber de
	 * regime: por isso a decisão acontece aqui, e não no cálculo nem no espelho.
	 *
	 * @return array{diaSemana: int, ehDsr: bool, perc: int|null}
	 */
	function parametroDiaSemana(array $motorista, string $data): array {
		$diaSemana = intval(date("w", strtotime($data)));
		$vazio = ["diaSemana" => $diaSemana, "ehDsr" => false, "perc" => null];

		if (strval($motorista["para_tx_tipo"] ?? "") !== "escala") {
			return $vazio;
		}

		$cfgEscala = diaSemanaEscalaDoDia($motorista, $data);
		if ($cfgEscala === null) {
			return $vazio;
		}

		return [
			"diaSemana" => $diaSemana,
			"ehDsr"     => $cfgEscala["ehDsr"],
			"perc"      => $cfgEscala["perc"],
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

if (!function_exists("diaSemanaOpcoesPercentual")) {
	/**
	 * As opções do seletor de percentual.
	 *
	 * São as duas faixas que o sistema realmente paga, lidas do próprio
	 * parâmetro: o espelho, o endosso e as exportações têm he50 e he100 e nada
	 * mais, e para_tx_percHESemanal / para_tx_percHEEx são os rótulos delas. Um
	 * campo livre aceitaria 70% e a folha não teria onde pagar.
	 */
	function diaSemanaOpcoesPercentual(?array $a_mod, ?int $percAtual): string {
		$a_mod = is_array($a_mod) ? $a_mod : [];

		$percSemanal = trim(strval($a_mod["para_tx_percHESemanal"] ?? "")) !== "" ? intval($a_mod["para_tx_percHESemanal"]) : 50;
		$percExtra   = trim(strval($a_mod["para_tx_percHEEx"] ?? "")) !== "" ? intval($a_mod["para_tx_percHEEx"]) : 100;

		/* Rótulo curto: os quadrados ficam lado a lado num flex que quebra
		   linha, e "100% — H.E. extraordinária" esticaria cada quadrado a ponto
		   de caber bem menos dias por linha. O que cada faixa significa está
		   nos campos de H.E. do próprio parâmetro, na mesma tela. */
		$opcoes = "<option value=''>padrão</option>";

		foreach ([$percSemanal, $percExtra] as $valor) {
			$opcoes .= "<option value='" . intval($valor) . "'"
				. ($percAtual !== null && $percAtual === intval($valor) ? " selected" : "")
				. ">" . intval($valor) . "%</option>";
		}

		return $opcoes;
	}
}

if (!function_exists("camposDsrEscalaDia")) {
	/**
	 * Os dois controles de DSR que entram em cada quadrado de dia da escala.
	 *
	 * Sai com __N__ no lugar do número do dia: os quadrados são montados em
	 * JavaScript, porque a quantidade deles muda quando a periodicidade muda, e
	 * o JS troca o __N__ pelo número da linha. A marcação do dia fica aqui, e
	 * não numa tabela à parte, porque o quadrado é onde a escala é montada.
	 *
	 * O percentual acompanha o checkbox porque sozinha a marca não decide nada:
	 * é o percentual que escolhe entre a faixa comum e a extraordinária.
	 */
	function camposDsrEscalaDia(?array $a_mod = null): string {
		$opcoes = diaSemanaOpcoesPercentual($a_mod, null);

		/* As duas colunas são col-sm-1, como as de hora: o cadastro tem a regra
		   ".linhaDiaEscala > .col-sm-1 { padding: 0 }", e um col-sm-2 ficaria
		   fora dela, com os 15px do Bootstrap de cada lado — era isso que
		   deixava o quadrado desalinhado dos campos ao lado. O padding-left
		   inline vence a regra e dá o respiro entre as colunas.
		   A caixa mora numa linha de 30px, a altura do .input-sm dos campos de
		   hora, e é o que a põe na mesma pauta deles em vez de subir. O label
		   inteiro é o alvo de clique, não os 17px do quadradinho. */
		return "<div class='col-sm-1 margin-bottom-5 campo-fit-content' style='padding-left:10px; text-align:center;'>"
				. "<label style='display:block; margin-bottom:5px;'>DSR</label>"
				. "<label for='dsrDia___N__' style='display:block; height:30px; line-height:30px; margin:0; cursor:pointer; font-weight:normal;'>"
					. "<input type='checkbox' id='dsrDia___N__' name='dsrDia___N__' value='1'"
					. " style='width:17px; height:17px; margin:0; vertical-align:middle; cursor:pointer;'>"
				. "</label>"
			. "</div>"
			. "<div class='col-sm-1 margin-bottom-5 campo-fit-content' style='padding-left:10px;'>"
				. "<label style='display:block; margin-bottom:5px; white-space:nowrap;'>% se trab.</label>"
				. "<select name='percDia___N__' id='percDia___N__' class='form-control input-sm'"
				. " title='Percentual pago se a pessoa trabalhar neste dia'"
				. " style='min-width:76px; text-align:center;'>"
				. $opcoes
				. "</select>"
			. "</div>";
	}
}
