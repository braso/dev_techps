<?php
/**
 * Placa mais provável de uma batida que veio com coordenada e sem placa.
 *
 * ── O problema
 *
 * O app registra latitude e longitude a cada batida, mas a placa é digitada
 * pelo motorista e vem em branco com frequência. Sem placa não há como ligar a
 * jornada ao veículo no espelho, no endosso nem na logística.
 *
 * ── Como a placa é adivinhada
 *
 * A coordenada da batida é comparada com a posição real dos veículos naquele
 * instante. As posições vêm da API de logística (a mesma do logistica.js e do
 * módulo de diárias): POST /data1 com placa e intervalo do dia devolve a
 * trilha do veículo, cada ponto com moduleTime, latitude e longitude.
 *
 * Para cada placa da empresa pega-se a posição de moduleTime mais perto do
 * horário da batida e mede-se a distância até a batida. O resultado é um
 * ranking por distância — a tela mostra a primeira e deixa escolher outra.
 *
 * ── O que isto NÃO é
 *
 * Não é certeza. Com o veículo parado a API manda um ponto a cada ~30 minutos,
 * então a posição comparada quase nunca é do mesmo minuto da batida: por isso
 * o ranking carrega a defasagem de tempo de cada placa, e a tela a mostra.
 * Dois veículos no mesmo pátio ficam a metros um do outro e a ordem entre eles
 * não quer dizer nada — a escolha final é de quem está conferindo.
 *
 * ── Por que há um cache
 *
 * Uma consulta pede a trilha de TODAS as placas da empresa: na RODOMILAN são
 * 29 chamadas HTTP por clique. Elas vão em paralelo (curl_multi) e o resultado
 * de dia fechado fica guardado, porque trilha de dia passado não muda e o
 * gestor costuma conferir várias batidas do mesmo dia.
 */

if (!function_exists("placaProximaUrlApi")) {
	/**
	 * URL base da API de logística.
	 *
	 * Lê o mesmo parâmetro que o módulo de diárias usa, para não existir uma
	 * segunda configuração que precise ser mudada junto. Se a tabela de
	 * parâmetros não existir naquela base, vale o padrão documentado lá.
	 */
	function placaProximaUrlApi(): string {
		static $url = null;
		if ($url !== null) {
			return $url;
		}

		$url = "https://logistica.integracao.techpsgj.com.br";

		$r = query("SHOW TABLES LIKE 'diaria_parametro'");
		if ($r && mysqli_num_rows($r) > 0) {
			$linha = mysqli_fetch_assoc(query(
				"SELECT diar_pa_tx_valor FROM diaria_parametro
					WHERE diar_pa_tx_chave = 'url_api_logistica' LIMIT 1;"
			));
			$valor = trim(strval($linha["diar_pa_tx_valor"] ?? ""));
			if ($valor !== "") {
				$url = $valor;
			}
		}

		return $url = rtrim($url, "/");
	}
}

if (!function_exists("placaProximaGarantirCache")) {
	/**
	 * Tabela de cache das trilhas já baixadas.
	 *
	 * Criada na primeira busca, no padrão de migração automática do sistema.
	 */
	function placaProximaGarantirCache(): void {
		static $feito = false;
		if ($feito) {
			return;
		}
		$feito = true;

		query("CREATE TABLE IF NOT EXISTS placa_posicao_cache (
			plpc_nb_id INT AUTO_INCREMENT PRIMARY KEY,
			plpc_tx_placa VARCHAR(10) NOT NULL,
			plpc_tx_data DATE NOT NULL,
			plpc_tx_json LONGTEXT NULL,
			plpc_nb_posicoes INT NOT NULL DEFAULT 0,
			plpc_tx_dataCadastro DATETIME NULL,
			UNIQUE KEY uq_placa_data (plpc_tx_placa, plpc_tx_data),
			KEY idx_data (plpc_tx_data)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	}
}

if (!function_exists("placaProximaDistanciaMetros")) {
	/**
	 * Distância em metros entre duas coordenadas (haversine).
	 *
	 * Há uma cópia disto no helpers_diarias.php. Ficou em dois lugares de
	 * propósito: é identidade matemática fechada, que não tem como divergir
	 * com o tempo, e incluir o módulo de diárias inteiro — mais de mil linhas
	 * e quarenta funções diar_* — dentro das telas de ponto por causa de oito
	 * linhas de conta sairia mais caro do que a repetição.
	 */
	function placaProximaDistanciaMetros($lat1, $lon1, $lat2, $lon2): float {
		$lat1 = floatval($lat1); $lon1 = floatval($lon1);
		$lat2 = floatval($lat2); $lon2 = floatval($lon2);

		/* Coordenada zerada é ausência de coordenada, não a ilha a 0,0 no
		   Atlântico: tratar como válida colocaria a placa a 5.000 km em vez de
		   fora da lista. */
		if ($lat1 == 0 && $lon1 == 0) { return -1.0; }
		if ($lat2 == 0 && $lon2 == 0) { return -1.0; }

		$raio = 6371000.0;
		$dLat = deg2rad($lat2 - $lat1);
		$dLon = deg2rad($lon2 - $lon1);
		$a = sin($dLat / 2) * sin($dLat / 2)
			+ cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);

		return $raio * (2 * atan2(sqrt($a), sqrt(1 - $a)));
	}
}

if (!function_exists("placaProximaFormatarDistancia")) {
	/** "370 m" até 1 km; "12,4 km" acima. */
	function placaProximaFormatarDistancia(float $metros): string {
		if ($metros < 0) {
			return "-";
		}
		if ($metros < 1000) {
			return number_format($metros, 0, ",", ".") . " m";
		}
		return number_format($metros / 1000, $metros < 10000 ? 2 : 1, ",", ".") . " km";
	}
}

if (!function_exists("placaProximaFormatarDefasagem")) {
	/** Defasagem de tempo entre a batida e a posição, em texto curto. */
	function placaProximaFormatarDefasagem(int $segundos): string {
		$segundos = abs($segundos);
		if ($segundos < 60) {
			return $segundos . "s";
		}
		$min = intdiv($segundos, 60);
		if ($min < 60) {
			return $min . " min";
		}
		return intdiv($min, 60) . "h " . str_pad(strval($min % 60), 2, "0", STR_PAD_LEFT) . "min";
	}
}

if (!function_exists("placaProximaPlacasDaEmpresa")) {
	/**
	 * Placas cadastradas na empresa do funcionário.
	 *
	 * @return array<string> placas em maiúsculas, sem repetição
	 */
	function placaProximaPlacasDaEmpresa($idEmpresa): array {
		$idEmpresa = intval($idEmpresa);
		if ($idEmpresa <= 0) {
			return [];
		}

		$res = query(
			"SELECT DISTINCT plac_tx_placa FROM placa
				WHERE plac_nb_empresa = {$idEmpresa}
				  AND plac_tx_placa IS NOT NULL AND plac_tx_placa <> ''
				ORDER BY plac_tx_placa ASC;"
		);

		$placas = [];
		while ($res && ($linha = mysqli_fetch_assoc($res))) {
			$placa = strtoupper(trim(strval($linha["plac_tx_placa"])));
			if ($placa !== "" && !in_array($placa, $placas, true)) {
				$placas[] = $placa;
			}
		}

		return $placas;
	}
}

if (!function_exists("placaProximaCorpoRequisicao")) {
	/** O corpo que a API /data1 espera, igual ao que o logistica.js manda. */
	function placaProximaCorpoRequisicao(string $placa, string $data): string {
		return json_encode([
			"plate"      => $placa,
			"date_start" => $data . " 00:00:00",
			"date_end"   => $data . " 23:59:59",
			"speed"      => 99,
		]);
	}
}

if (!function_exists("placaProximaNormalizarTrilha")) {
	/**
	 * Deixa da resposta da API só o que a comparação usa.
	 *
	 * O cliente do módulo de diárias joga o moduleTime fora, e é justamente
	 * dele que esta busca depende: sem o horário da posição não há como dizer
	 * onde o veículo estava na hora da batida.
	 *
	 * @return array<int, array{lat: float, lon: float, ts: int, hora: string, endereco: string, velocidade: int}>
	 */
	function placaProximaNormalizarTrilha($resposta): array {
		$dados = is_array($resposta) ? $resposta : json_decode(strval($resposta), true);
		if (!is_array($dados)) {
			return [];
		}
		/* A API responde {ok:false} quando nao tem dado para a placa. */
		if (isset($dados["ok"]) && $dados["ok"] === false) {
			return [];
		}

		$trilha = [];
		foreach ($dados as $linha) {
			if (!is_array($linha)) {
				continue;
			}

			$lat = floatval($linha["latitude"] ?? 0);
			$lon = floatval($linha["longitude"] ?? 0);
			if ($lat == 0 && $lon == 0) {
				continue;
			}

			/* moduleTime vem como 2026-10-08T08:48:35.0 */
			$bruto = trim(strval($linha["moduleTime"] ?? ""));
			$ts = $bruto !== "" ? strtotime(str_replace("T", " ", $bruto)) : false;
			if ($ts === false) {
				continue;
			}

			$trilha[] = [
				"lat"        => $lat,
				"lon"        => $lon,
				"ts"         => $ts,
				"hora"       => date("H:i:s", $ts),
				"endereco"   => trim(strval($linha["endereco"] ?? "")),
				"velocidade" => intval($linha["speed"] ?? 0),
			];
		}

		return $trilha;
	}
}

if (!function_exists("placaProximaTrilhas")) {
	/**
	 * Trilha de cada placa no dia.
	 *
	 * As que já estão no cache saem dele; as que faltam vão todas de uma vez,
	 * em paralelo. Serial seriam 29 requisições enfileiradas e a tela estouraria
	 * o tempo antes de responder.
	 *
	 * O dia de hoje nunca é guardado: a trilha dele ainda está crescendo.
	 *
	 * @return array<string, array> placa => trilha normalizada
	 */
	function placaProximaTrilhas(array $placas, string $data): array {
		placaProximaGarantirCache();

		$data = date("Y-m-d", strtotime($data));
		$ehHoje = ($data === date("Y-m-d"));
		$trilhas = [];
		$faltando = [];

		foreach ($placas as $placa) {
			$placa = strtoupper(trim(strval($placa)));
			if ($placa === "") {
				continue;
			}
			$trilhas[$placa] = [];
			$faltando[$placa] = true;
		}

		if (empty($trilhas)) {
			return [];
		}

		/* ── Cache ─────────────────────────────────────────────────────── */
		if (!$ehHoje) {
			$lista = "'" . implode("','", array_map(function ($p) {
				return str_replace("'", "", $p);
			}, array_keys($trilhas))) . "'";

			$res = query(
				"SELECT plpc_tx_placa, plpc_tx_json FROM placa_posicao_cache
					WHERE plpc_tx_data = '{$data}' AND plpc_tx_placa IN ({$lista});"
			);
			while ($res && ($linha = mysqli_fetch_assoc($res))) {
				$placa = strtoupper(trim(strval($linha["plpc_tx_placa"])));
				if (!isset($trilhas[$placa])) {
					continue;
				}
				$trilhas[$placa] = placaProximaNormalizarTrilha($linha["plpc_tx_json"]);
				unset($faltando[$placa]);
			}
		}

		if (empty($faltando)) {
			return $trilhas;
		}

		/* ── O que falta, em paralelo ──────────────────────────────────── */
		$url = placaProximaUrlApi() . "/data1";
		if (!function_exists("curl_multi_init")) {
			/* Sem curl_multi o jeito e serial; so vale para poucas placas. */
			foreach (array_keys($faltando) as $placa) {
				$ctx = stream_context_create(["http" => [
					"method"  => "POST",
					"header"  => "Content-Type: application/json\r\n",
					"content" => placaProximaCorpoRequisicao($placa, $data),
					"timeout" => 15,
				]]);
				$resposta = @file_get_contents($url, false, $ctx);
				$trilhas[$placa] = placaProximaNormalizarTrilha($resposta);
				placaProximaGuardarCache($placa, $data, $resposta, count($trilhas[$placa]), $ehHoje);
			}
			return $trilhas;
		}

		$multi = curl_multi_init();
		$handles = [];

		foreach (array_keys($faltando) as $placa) {
			$ch = curl_init($url);
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => placaProximaCorpoRequisicao($placa, $data),
				CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
				CURLOPT_TIMEOUT        => 20,
				CURLOPT_CONNECTTIMEOUT => 8,
			]);
			curl_multi_add_handle($multi, $ch);
			$handles[$placa] = $ch;
		}

		do {
			$status = curl_multi_exec($multi, $ativos);
			if ($ativos) {
				curl_multi_select($multi, 1.0);
			}
		} while ($ativos && $status == CURLM_OK);

		foreach ($handles as $placa => $ch) {
			$resposta = curl_multi_getcontent($ch);
			$trilhas[$placa] = placaProximaNormalizarTrilha($resposta);
			placaProximaGuardarCache($placa, $data, $resposta, count($trilhas[$placa]), $ehHoje);

			curl_multi_remove_handle($multi, $ch);
			curl_close($ch);
		}
		curl_multi_close($multi);

		return $trilhas;
	}
}

if (!function_exists("placaProximaGuardarCache")) {
	/** Guarda a resposta crua, só para dia já fechado. */
	function placaProximaGuardarCache(string $placa, string $data, $resposta, int $quantas, bool $ehHoje): void {
		if ($ehHoje || $resposta === false || $resposta === null || trim(strval($resposta)) === "") {
			return;
		}

		$placa = mysqli_real_escape_string($GLOBALS["conn"], strtoupper(trim($placa)));
		$data = date("Y-m-d", strtotime($data));
		$json = mysqli_real_escape_string($GLOBALS["conn"], strval($resposta));

		query(
			"INSERT INTO placa_posicao_cache (plpc_tx_placa, plpc_tx_data, plpc_tx_json, plpc_nb_posicoes, plpc_tx_dataCadastro)
				VALUES ('{$placa}', '{$data}', '{$json}', " . intval($quantas) . ", NOW())
				ON DUPLICATE KEY UPDATE
					plpc_tx_json = VALUES(plpc_tx_json),
					plpc_nb_posicoes = VALUES(plpc_nb_posicoes),
					plpc_tx_dataCadastro = VALUES(plpc_tx_dataCadastro);"
		);
	}
}

if (!function_exists("placaProximaRanking")) {
	/**
	 * Ranking das placas por distância até a batida.
	 *
	 * De cada trilha sai a posição de horário mais perto da batida — e só ela
	 * entra na conta. Comparar com a trilha inteira acharia o veículo "perto"
	 * por ter passado ali seis horas depois, o que não diz nada.
	 *
	 * @param int $janelaMinutos posição mais distante no tempo que ainda conta
	 * @return array<int, array> ordenado, mais perto primeiro
	 */
	function placaProximaRanking(array $trilhas, float $lat, float $lon, int $tsBatida, int $janelaMinutos = 60): array {
		$janela = max(1, $janelaMinutos) * 60;
		$ranking = [];

		foreach ($trilhas as $placa => $trilha) {
			if (empty($trilha)) {
				$ranking[] = [
					"placa"      => $placa,
					"situacao"   => "sem_trilha",
					"motivo"     => "A API não devolveu posição desta placa no dia.",
					"metros"     => -1.0,
					"defasagem"  => null,
				];
				continue;
			}

			$melhor = null;
			foreach ($trilha as $pos) {
				$dt = abs($pos["ts"] - $tsBatida);
				if ($melhor === null || $dt < $melhor["dt"]) {
					$melhor = ["pos" => $pos, "dt" => $dt];
				}
			}

			if ($melhor === null || $melhor["dt"] > $janela) {
				$ranking[] = [
					"placa"     => $placa,
					"situacao"  => "fora_da_janela",
					"motivo"    => "A posição mais perto no tempo está a "
						. ($melhor === null ? "-" : placaProximaFormatarDefasagem($melhor["dt"]))
						. " da batida, acima da janela de {$janelaMinutos} min.",
					"metros"    => -1.0,
					"defasagem" => $melhor === null ? null : intval($melhor["dt"]),
				];
				continue;
			}

			$metros = placaProximaDistanciaMetros($lat, $lon, $melhor["pos"]["lat"], $melhor["pos"]["lon"]);
			if ($metros < 0) {
				$ranking[] = [
					"placa"     => $placa,
					"situacao"  => "sem_coordenada",
					"motivo"    => "A posição mais perto no tempo veio sem coordenada.",
					"metros"    => -1.0,
					"defasagem" => intval($melhor["dt"]),
				];
				continue;
			}

			$ranking[] = [
				"placa"       => $placa,
				"situacao"    => "ok",
				"metros"      => $metros,
				"distancia"   => placaProximaFormatarDistancia($metros),
				"km"          => round($metros / 1000, 3),
				"defasagem"   => intval($melhor["dt"]),
				"defasagemTx" => placaProximaFormatarDefasagem($melhor["dt"]),
				"horaPosicao" => $melhor["pos"]["hora"],
				"lat"         => $melhor["pos"]["lat"],
				"lon"         => $melhor["pos"]["lon"],
				"endereco"    => $melhor["pos"]["endereco"],
				"velocidade"  => $melhor["pos"]["velocidade"],
				"pontos"      => count($trilha),
			];
		}

		/* Com distância primeiro, as sem medida no fim. Empate de distância
		   desempata pela menor defasagem de tempo: dois veículos no mesmo
		   pátio ficam a metros um do outro. */
		usort($ranking, function ($a, $b) {
			$aOk = ($a["situacao"] === "ok");
			$bOk = ($b["situacao"] === "ok");
			if ($aOk !== $bOk) {
				return $aOk ? -1 : 1;
			}
			if (!$aOk) {
				return strcmp($a["placa"], $b["placa"]);
			}
			if (abs($a["metros"] - $b["metros"]) > 0.5) {
				return $a["metros"] < $b["metros"] ? -1 : 1;
			}
			return intval($a["defasagem"]) <=> intval($b["defasagem"]);
		});

		$posicao = 0;
		foreach ($ranking as $i => $item) {
			$ranking[$i]["posicao"] = ($item["situacao"] === "ok") ? ++$posicao : null;
		}

		return $ranking;
	}
}

if (!function_exists("placaProximaDoPonto")) {
	/**
	 * A busca inteira, a partir do id da batida.
	 *
	 * Nada vem da tela além do id: coordenada, horário e empresa saem do banco,
	 * para a requisição não poder pedir a trilha de outra empresa.
	 *
	 * @return array{ok: bool, erro?: string, ...}
	 */
	function placaProximaDoPonto($idPonto, int $janelaMinutos = 60): array {
		$idPonto = intval($idPonto);
		if ($idPonto <= 0) {
			return ["ok" => false, "erro" => "Batida não informada."];
		}

		$ponto = mysqli_fetch_assoc(query(
			"SELECT p.pont_nb_id, p.pont_tx_data, p.pont_tx_latitude, p.pont_tx_longitude,
					p.pont_tx_placa, p.pont_tx_matricula,
					e.enti_nb_id, e.enti_tx_nome, e.enti_nb_empresa
				FROM ponto p
				LEFT JOIN entidade e ON e.enti_tx_matricula = p.pont_tx_matricula
				WHERE p.pont_nb_id = {$idPonto} LIMIT 1;"
		));

		if (empty($ponto)) {
			return ["ok" => false, "erro" => "Batida {$idPonto} não encontrada."];
		}

		$lat = floatval($ponto["pont_tx_latitude"]);
		$lon = floatval($ponto["pont_tx_longitude"]);
		if ($lat == 0 && $lon == 0) {
			return ["ok" => false, "erro" => "Esta batida não tem latitude e longitude — não há de onde medir."];
		}

		$placas = placaProximaPlacasDaEmpresa($ponto["enti_nb_empresa"]);
		if (empty($placas)) {
			return ["ok" => false, "erro" => "A empresa deste funcionário não tem placa cadastrada."];
		}

		$tsBatida = strtotime($ponto["pont_tx_data"]);
		$dia = date("Y-m-d", $tsBatida);

		$inicio = microtime(true);
		$trilhas = placaProximaTrilhas($placas, $dia);
		$ranking = placaProximaRanking($trilhas, $lat, $lon, $tsBatida, $janelaMinutos);

		$comMedida = array_values(array_filter($ranking, function ($r) {
			return $r["situacao"] === "ok";
		}));

		return [
			"ok"             => true,
			"idPonto"        => $idPonto,
			"funcionario"    => strval($ponto["enti_tx_nome"] ?? ""),
			"matricula"      => strval($ponto["pont_tx_matricula"]),
			"dataBatida"     => $ponto["pont_tx_data"],
			"horaBatida"     => date("H:i:s", $tsBatida),
			"lat"            => $lat,
			"lon"            => $lon,
			"placaAtual"     => strval($ponto["pont_tx_placa"] ?? ""),
			"janelaMinutos"  => $janelaMinutos,
			"placasNaEmpresa" => count($placas),
			"comMedida"      => count($comMedida),
			"segundos"       => round(microtime(true) - $inicio, 2),
			"ranking"        => $ranking,
		];
	}
}

if (!function_exists("placaProximaGravar")) {
	/**
	 * Grava a placa escolhida na batida.
	 *
	 * A placa precisa ser uma das cadastradas na empresa daquele funcionário:
	 * a tela manda um texto, e sem essa conferência daria para gravar qualquer
	 * coisa no campo.
	 */
	function placaProximaGravar($idPonto, string $placa, $idUsuario = null): array {
		$idPonto = intval($idPonto);
		$placa = strtoupper(trim($placa));

		if ($idPonto <= 0 || $placa === "") {
			return ["ok" => false, "erro" => "Batida ou placa não informada."];
		}

		$ponto = mysqli_fetch_assoc(query(
			"SELECT p.pont_nb_id, p.pont_tx_placa, e.enti_nb_empresa
				FROM ponto p
				LEFT JOIN entidade e ON e.enti_tx_matricula = p.pont_tx_matricula
				WHERE p.pont_nb_id = {$idPonto} LIMIT 1;"
		));
		if (empty($ponto)) {
			return ["ok" => false, "erro" => "Batida {$idPonto} não encontrada."];
		}

		$placas = placaProximaPlacasDaEmpresa($ponto["enti_nb_empresa"]);
		if (!in_array($placa, $placas, true)) {
			return ["ok" => false, "erro" => "A placa {$placa} não está cadastrada nesta empresa."];
		}

		$placaSql = mysqli_real_escape_string($GLOBALS["conn"], $placa);
		$usuario = intval($idUsuario);

		query(
			"UPDATE ponto SET pont_tx_placa = '{$placaSql}',
					pont_tx_dataAtualiza = NOW()"
				. ($usuario > 0 ? ", pont_nb_userAtualiza = {$usuario}" : "")
				. " WHERE pont_nb_id = {$idPonto};"
		);

		return [
			"ok"       => true,
			"idPonto"  => $idPonto,
			"placa"    => $placa,
			"anterior" => strval($ponto["pont_tx_placa"] ?? ""),
		];
	}
}

if (!function_exists("placaProximaBlocoModal")) {
	/**
	 * O popup do ranking e o JavaScript que fala com o endpoint.
	 *
	 * Fica aqui, e não solto no ajuste de ponto, porque o espelho e a não
	 * conformidade mostram as mesmas batidas e tendem a querer o mesmo botão.
	 *
	 * O ranking é uma sugestão: a tela mostra distância e defasagem de tempo
	 * lado a lado de propósito, porque a placa mais perto no espaço não é
	 * sempre a mais perto no horário, e quem confere precisa ver as duas.
	 */
	function placaProximaBlocoModal(): string {
		return <<<'HTML'
<div id="placaProximaModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:10050; align-items:flex-start; justify-content:center; overflow:auto; padding:24px 12px;">
	<div style="background:#fff; border-radius:6px; width:100%; max-width:980px; box-shadow:0 10px 40px rgba(0,0,0,.35);">
		<div style="padding:12px 16px; border-bottom:1px solid #e5e5e5; display:flex; align-items:center; gap:10px;">
			<span class="glyphicon glyphicon-road" style="color:#2563eb;"></span>
			<b style="flex:1;">Placa mais próxima da batida</b>
			<a href="#" onclick="placaProximaFechar(); return false;" title="Fechar" style="color:#888; font-size:18px; text-decoration:none;">&times;</a>
		</div>

		<div id="placaProximaCorpo" style="padding:14px 16px; max-height:70vh; overflow:auto;">
			<div style="color:#666;">Carregando...</div>
		</div>

		<div style="padding:10px 16px; border-top:1px solid #e5e5e5; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
			<label style="margin:0; font-weight:normal;">Janela de tempo:</label>
			<select id="placaProximaJanela" class="form-control input-sm" style="width:auto;" onchange="placaProximaRecarregar()">
				<option value="15">15 min</option>
				<option value="30">30 min</option>
				<option value="60" selected>60 min</option>
				<option value="120">2 horas</option>
				<option value="240">4 horas</option>
			</select>
			<small style="color:#777; flex:1; min-width:220px;">
				Com o veículo parado a API manda uma posição a cada ~30 min. Aumente a janela se a placa certa não aparecer.
			</small>
			<button type="button" class="btn btn-default btn-sm" onclick="placaProximaFechar()">Fechar</button>
		</div>
	</div>
</div>

<script>
var placaProximaPontoAtual = 0;

function placaProximaFechar(){
	document.getElementById('placaProximaModal').style.display = 'none';
}

function placaProximaEscapar(txt){
	return String(txt == null ? '' : txt)
		.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function buscarPlacaProximaJS(idPonto){
	placaProximaPontoAtual = idPonto;
	document.getElementById('placaProximaModal').style.display = 'flex';
	placaProximaRecarregar();
}

function placaProximaRecarregar(){
	var corpo = document.getElementById('placaProximaCorpo');
	var janela = document.getElementById('placaProximaJanela').value;

	corpo.innerHTML = '<div style="color:#666; padding:20px 0; text-align:center;">'
		+ '<span class="glyphicon glyphicon-refresh"></span> Consultando a posição de cada placa da empresa...'
		+ '<br><small>São várias consultas à API de logística; o primeiro dia leva alguns segundos.</small></div>';

	var dados = new URLSearchParams();
	dados.append('placaAcao', 'buscar');
	dados.append('idPonto', placaProximaPontoAtual);
	dados.append('janelaMinutos', janela);

	fetch('ajax_placa_proxima.php', {
		method: 'POST',
		headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
		body: dados.toString(),
		credentials: 'same-origin'
	})
	.then(function(r){ return r.text(); })
	.then(function(texto){
		var r;
		try { r = JSON.parse(texto); }
		catch(e){
			/* Sessao expirada devolve o HTML de logout do conecta.php. */
			corpo.innerHTML = '<div class="alert alert-warning" style="margin:0;">'
				+ 'A resposta não veio em JSON. Se a sessão expirou, entre no sistema de novo e tente outra vez.</div>';
			return;
		}
		if (!r.ok) {
			corpo.innerHTML = '<div class="alert alert-warning" style="margin:0;">'
				+ placaProximaEscapar(r.erro || 'Não foi possível buscar.') + '</div>';
			return;
		}
		corpo.innerHTML = placaProximaMontarCorpo(r);
	})
	.catch(function(e){
		corpo.innerHTML = '<div class="alert alert-danger" style="margin:0;">Falha na consulta: '
			+ placaProximaEscapar(e.message) + '</div>';
	});
}

function placaProximaMontarCorpo(r){
	var html = '<div style="background:#f7f9fc; border:1px solid #e5e9f0; border-radius:4px; padding:10px 12px; margin-bottom:12px;">'
		+ '<b>' + placaProximaEscapar(r.funcionario) + '</b> <span style="color:#777;">(matrícula ' + placaProximaEscapar(r.matricula) + ')</span>'
		+ '<br>Batida de <b>' + placaProximaEscapar(r.dataBatida) + '</b>'
		+ ' &nbsp;·&nbsp; coordenada <code>' + Number(r.lat).toFixed(6) + ', ' + Number(r.lon).toFixed(6) + '</code>'
		+ (r.placaAtual ? ' &nbsp;·&nbsp; placa atual <b>' + placaProximaEscapar(r.placaAtual) + '</b>' : ' &nbsp;·&nbsp; <span style="color:#b45309;">sem placa</span>')
		+ '<br><small style="color:#777;">' + r.placasNaEmpresa + ' placas na empresa · '
		+ r.comMedida + ' com posição na janela de ' + r.janelaMinutos + ' min · consulta em ' + r.segundos + 's</small>'
		+ '</div>';

	var comMedida = r.ranking.filter(function(i){ return i.situacao === 'ok'; });
	var semMedida = r.ranking.filter(function(i){ return i.situacao !== 'ok'; });

	if (comMedida.length === 0) {
		html += '<div class="alert alert-warning">Nenhuma placa da empresa tem posição dentro da janela de '
			+ r.janelaMinutos + ' min. Aumente a janela abaixo.</div>';
	} else {
		html += '<table class="table table-condensed table-hover" style="margin-bottom:6px;">'
			+ '<thead><tr style="background:#fafafa;">'
			+ '<th style="width:40px;">#</th><th>Placa</th><th>Distância</th>'
			+ '<th>Defasagem</th><th>Hora da posição</th><th>km/h</th><th>Endereço</th><th style="width:110px;"></th>'
			+ '</tr></thead><tbody>';

		comMedida.forEach(function(i, idx){
			var destaque = (idx === 0) ? ' style="background:#eff6ff;"' : '';
			html += '<tr' + destaque + '>'
				+ '<td><b>' + i.posicao + 'º</b></td>'
				+ '<td><b>' + placaProximaEscapar(i.placa) + '</b></td>'
				+ '<td><b>' + placaProximaEscapar(i.distancia) + '</b>'
				+ '<br><small style="color:#777;">' + Number(i.metros).toFixed(0) + ' m · ' + Number(i.km).toFixed(3) + ' km</small></td>'
				+ '<td>' + placaProximaEscapar(i.defasagemTx) + '</td>'
				+ '<td>' + placaProximaEscapar(i.horaPosicao) + '</td>'
				+ '<td>' + i.velocidade + '</td>'
				+ '<td><small>' + placaProximaEscapar(i.endereco) + '</small></td>'
				+ '<td><button type="button" class="btn btn-xs ' + (idx === 0 ? 'btn-primary' : 'btn-default') + '"'
				+ ' onclick="placaProximaInserir(\'' + placaProximaEscapar(i.placa) + '\')">Inserir</button></td>'
				+ '</tr>';
		});

		html += '</tbody></table>'
			+ '<small style="color:#777; display:block; margin-bottom:10px;">'
			+ 'A ordem é por distância. Repare na defasagem: a placa mais perto no espaço pode ter sido medida '
			+ 'num horário mais longe da batida — e dois veículos no mesmo pátio ficam a metros um do outro, '
			+ 'onde a ordem não decide nada. A escolha é sua.'
			+ '</small>';
	}

	if (semMedida.length > 0) {
		html += '<details style="margin-top:6px;"><summary style="cursor:pointer; color:#555;">'
			+ semMedida.length + ' placa(s) sem posição utilizável</summary>'
			+ '<ul style="margin:8px 0 0 18px; color:#777;">';
		semMedida.forEach(function(i){
			html += '<li><b>' + placaProximaEscapar(i.placa) + '</b> — ' + placaProximaEscapar(i.motivo) + '</li>';
		});
		html += '</ul></details>';
	}

	return html;
}

function placaProximaInserir(placa){
	if (!confirm('Gravar a placa ' + placa + ' nesta batida?')) { return; }

	var dados = new URLSearchParams();
	dados.append('placaAcao', 'inserir');
	dados.append('idPonto', placaProximaPontoAtual);
	dados.append('placa', placa);

	fetch('ajax_placa_proxima.php', {
		method: 'POST',
		headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
		body: dados.toString(),
		credentials: 'same-origin'
	})
	.then(function(r){ return r.json(); })
	.then(function(r){
		if (!r.ok) {
			alert(r.erro || 'Não foi possível gravar a placa.');
			return;
		}
		/* Recarrega para a linha do grid mostrar a placa e o botao sair. */
		location.reload();
	})
	.catch(function(e){ alert('Falha ao gravar: ' + e.message); });
}
</script>
HTML;
	}
}
