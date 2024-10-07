<?php

// ini_set("display_errors", 1);
//         error_reporting(E_ALL);

//Funções comuns aos paineis{
function calcPercs(array $values): array {
	$total = 0;
	foreach ($values as $value) {
		$total += $value;
	}

	if ($total == 0) {
		return array_pad([], sizeof($values), 0);
	}

	$percentuais = array_pad([], sizeof($values), 0);
	for ($f = 0; $f < sizeof($values); $f++) {
		$percentuais[$f] = $values[$f] / $total;
	}

	return $percentuais;
}

function salvarArquivo(string $path, string $fileName, string $data) {
	if (!is_dir($path)) {
		mkdir($path, 0755, true);
	}
	$data = json_encode($data, JSON_UNESCAPED_UNICODE);
	file_put_contents($path . '/' . $fileName, $data);
}
//}

//Funções de criação de cada painel{
function criar_relatorio_saldo() {

	global $totalResumo;
	$dataInicio = new DateTime($_POST["busca_dataInicio"]);
	$dataFim = new DateTime($_POST["busca_dataFim"]);

	$empresas = mysqli_fetch_all(query(
		"SELECT empr_nb_id, empr_tx_nome FROM empresa"
			. " WHERE empr_tx_status = 'ativo'"
			. (!empty($_POST["empresa"]) ? " AND empr_nb_id = " . $_POST["empresa"] : "")
			. " ORDER BY empr_tx_nome ASC;"
	), MYSQLI_ASSOC);

	$totaisEmpresas = [
		"jornadaPrevista" => "00:00",
		"jornadaEfetiva" => "00:00",
		"HESemanal" => "00:00",
		"HESabado" => "00:00",
		"adicionalNoturno" => "00:00",
		"esperaIndenizada" => "00:00",
		"saldoAnterior" => "00:00",
		"saldoPeriodo" => "00:00",
		"saldoFinal" => "00:00",
		"qtdMotoristas" => 0
	];

	foreach ($empresas as $empresa) {
		$path = "./arquivos/saldos" . "/" . $dataInicio->format("Y-m") . "/" . $empresa["empr_nb_id"];
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}
		// if(file_exists($path."/empresa_".$empresa["empr_nb_id"].".json")){
		// 	if(date("Y-m-d", filemtime($path."/empresa_".$empresa["empr_nb_id"].".json")) == date("Y-m-d")){
		// 		// echo 
		// 		// 	"<script>"
		// 		// 	."confirm('O relatório de ".$empresa["empr_tx_nome"]." já foi gerado hoje, deseja gerar novamente?');"
		// 		// 	."</script>"
		// 		// ;
		// 		continue;
		// 	}
		// }

		$motoristas = mysqli_fetch_all(query(
			"SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_banco, enti_tx_ocupacao FROM entidade"
				. " WHERE enti_tx_status = 'ativo'"
				. " AND enti_nb_empresa = " . $empresa["empr_nb_id"]
				. " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')"
				. " ORDER BY enti_tx_nome ASC;"
		), MYSQLI_ASSOC);

		$rows = [];
		$statusEndossos = [
			"E" => 0,
			"EP" => 0,
			"N" => 0
		];
		foreach ($motoristas as $motorista) {
			//Status Endosso{
			$endossos = mysqli_fetch_all(query(
				"SELECT * FROM endosso"
					. " WHERE endo_tx_status = 'ativo'"
					. " AND endo_nb_entidade = '" . $motorista["enti_nb_id"] . "'"
					. " AND ("
					. "   (endo_tx_de  >= '" . $dataInicio->format("Y-m-d") . "' AND endo_tx_de  <= '" . $dataFim->format("Y-m-d") . "')"
					. "OR (endo_tx_ate >= '" . $dataInicio->format("Y-m-d") . "' AND endo_tx_ate <= '" . $dataFim->format("Y-m-d") . "')"
					. "OR (endo_tx_de  <= '" . $dataInicio->format("Y-m-d") . "' AND endo_tx_ate >= '" . $dataFim->format("Y-m-d") . "')"
					. ");"
			), MYSQLI_ASSOC);

			$statusEndosso = "N";
			if (count($endossos) >= 1) {
				$statusEndosso = "E";
				if (strtotime($dataInicio->format("Y-m-d")) < strtotime($endossos[0]["endo_tx_de"]) || strtotime($dataFim->format("Y-m-d")) > strtotime($endossos[count($endossos) - 1]["endo_tx_ate"])) {
					$statusEndosso .= "P";
				}
			}
			$statusEndossos[$statusEndosso]++;
			//}

			//saldoAnterior{
			$saldoAnterior = mysqli_fetch_assoc(query(
				"SELECT endo_tx_saldo FROM endosso"
					. " WHERE endo_tx_status = 'ativo'"
					. " AND endo_tx_ate < '" . $dataInicio->format("Y-m-d") . "'"
					. " AND endo_tx_matricula = '" . $motorista["enti_tx_matricula"] . "'"
					. " ORDER BY endo_tx_ate DESC"
					. " LIMIT 1;"
			));

			if (!empty($saldoAnterior)) {
				if (!empty($saldoAnterior["endo_tx_saldo"])) {
					$saldoAnterior = $saldoAnterior["endo_tx_saldo"];
				} elseif (!empty($motorista["enti_tx_banco"])) {
					$saldoAnterior = $motorista["enti_tx_banco"];
				}
				if (strlen($motorista["enti_tx_banco"]) > 5 && $motorista["enti_tx_banco"][0] == "0") {
					$saldoAnterior = substr($saldoAnterior, 1);
				}
			} else {
				$saldoAnterior = "00:00";
			}
			//}

			$totaisMot = [
				"jornadaPrevista" => "00:00",
				"jornadaEfetiva" => "00:00",
				"HESemanal" => "00:00",
				"HESabado" => "00:00",
				"adicionalNoturno" => "00:00",
				"esperaIndenizada" => "00:00",
				"saldoPeriodo" => "00:00",
				"saldoFinal" => "00:00"
			];

			for ($dia = new DateTime($dataInicio->format("Y-m-d")); $dia <= $dataFim; $dia->modify("+1 day")) {
				$diaPonto = diaDetalhePonto($motorista["enti_tx_matricula"], $dia->format("Y-m-d"));
				//Formatando informações{
				foreach (array_keys($diaPonto) as $f) {
					if (in_array($f, ["data", "diaSemana"])) {
						continue;
					}
					if (strlen($diaPonto[$f]) > 5) {
						$diaPonto[$f] = preg_replace("/.*&nbsp;/", "", $diaPonto[$f]);
						if (preg_match_all("/(-?\d{2,4}:\d{2})/", $diaPonto[$f], $matches)) {
							$diaPonto[$f] = array_pop($matches[1]);
						} else {
							$diaPonto[$f] = "";
						}
					}
				}
				//}


				$diaPonto["he50"]              = !empty($diaPonto["he50"]) ? $diaPonto["he50"] : "00:00";
				$diaPonto["he100"]             = !empty($diaPonto["he100"]) ? $diaPonto["he100"] : "00:00";

				$totaisMot["jornadaPrevista"]  = somarHorarios([$totaisMot["jornadaPrevista"],  $diaPonto["jornadaPrevista"]]);
				$totaisMot["jornadaEfetiva"]   = somarHorarios([$totaisMot["jornadaEfetiva"],   $diaPonto["diffJornadaEfetiva"]]);
				$totaisMot["HESemanal"]        = somarHorarios([$totaisMot["HESemanal"],        $diaPonto["he50"]]);
				$totaisMot["HESabado"]         = somarHorarios([$totaisMot["HESabado"],         $diaPonto["he100"]]);
				$totaisMot["adicionalNoturno"] = somarHorarios([$totaisMot["adicionalNoturno"], $diaPonto["adicionalNoturno"]]);
				$totaisMot["esperaIndenizada"] = somarHorarios([$totaisMot["esperaIndenizada"], $diaPonto["esperaIndenizada"]]);
				$totaisMot["saldoPeriodo"]     = somarHorarios([$totaisMot["saldoPeriodo"],     $diaPonto["diffSaldo"]]);
				$totaisMot["saldoFinal"]       = somarHorarios([$saldoAnterior,                 $totaisMot["saldoPeriodo"]]);
			}

			$row = [
				"idMotorista" => $motorista["enti_nb_id"],
				"matricula" => $motorista["enti_tx_matricula"],
				"ocupacao" => $motorista["enti_tx_ocupacao"],
				"nome" => $motorista["enti_tx_nome"],
				"statusEndosso" => $statusEndosso,
				"jornadaPrevista" => $totaisMot["jornadaPrevista"],
				"jornadaEfetiva" => $totaisMot["jornadaEfetiva"],
				"HESemanal" => $totaisMot["HESemanal"],
				"HESabado" => $totaisMot["HESabado"],
				"adicionalNoturno" => $totaisMot["adicionalNoturno"],
				"esperaIndenizada" => $totaisMot["esperaIndenizada"],

				"saldoAnterior" => $saldoAnterior,
				"saldoPeriodo" => $totaisMot["saldoPeriodo"],
				"saldoFinal" => $totaisMot["saldoFinal"]
			];
			$nomeArquivo = $motorista["enti_tx_matricula"] . ".json";
			file_put_contents($path . "/" . $nomeArquivo, json_encode($row, JSON_UNESCAPED_UNICODE));

			$rows[] = $row;
		}


		$totaisEmpr = [
			"jornadaPrevista" => "00:00",
			"jornadaEfetiva" => "00:00",
			"HESemanal" => "00:00",
			"HESabado" => "00:00",
			"adicionalNoturno" => "00:00",
			"esperaIndenizada" => "00:00",
			"saldoAnterior" => "00:00",
			"saldoPeriodo" => "00:00",
			"saldoFinal" => "00:00"
		];

		foreach ($rows as $row) {
			$totaisEmpr["jornadaPrevista"]  = operarHorarios([$totaisEmpr["jornadaPrevista"], $row["jornadaPrevista"]], "+");
			$totaisEmpr["jornadaEfetiva"]   = operarHorarios([$totaisEmpr["jornadaEfetiva"], $row["jornadaEfetiva"]], "+");
			$totaisEmpr["HESemanal"]        = operarHorarios([$totaisEmpr["HESemanal"], $row["HESemanal"]], "+");
			$totaisEmpr["HESabado"]         = operarHorarios([$totaisEmpr["HESabado"], $row["HESabado"]], "+");
			$totaisEmpr["adicionalNoturno"] = operarHorarios([$totaisEmpr["adicionalNoturno"], $row["adicionalNoturno"]], "+");
			$totaisEmpr["esperaIndenizada"] = operarHorarios([$totaisEmpr["esperaIndenizada"], $row["esperaIndenizada"]], "+");
			$totaisEmpr["saldoAnterior"]    = operarHorarios([$totaisEmpr["saldoAnterior"], $row["saldoAnterior"]], "+");
			$totaisEmpr["saldoPeriodo"]     = operarHorarios([$totaisEmpr["saldoPeriodo"], $row["saldoPeriodo"]], "+");
			$totaisEmpr["saldoFinal"]       = operarHorarios([$totaisEmpr["saldoFinal"], $row["saldoFinal"]], "+");
		}

		//Adicionar valores da empresa à soma total das empresas{
		if (empty($_POST["empresa"])) {
			foreach ($totaisEmpr as $key => $value) {
				$totaisEmpresas[$key] = operarHorarios([$totaisEmpresas[$key], $value], "+");
			}
			$totaisEmpresas["qtdMotoristas"] += count($motoristas);
		}
		//}

		$empresa["totais"] = $totaisEmpr;
		$empresa["qtdMotoristas"] = count($motoristas);
		$empresa["dataInicio"] = $dataInicio->format("Y-m-d");
		$empresa["dataFim"] = $dataFim->format("Y-m-d");
		$empresa["percEndossado"] = ($statusEndossos["E"]) / array_sum(array_values($statusEndossos));

		file_put_contents($path . "/empresa_" . $empresa["empr_nb_id"] . ".json", json_encode($empresa));
	}

	if (empty($_POST["empresa"])) {
		$path = "./arquivos/saldos" . "/" . $dataInicio->format("Y-m");
		$totaisEmpresas["dataInicio"] = $dataInicio->format("Y-m-d");
		$totaisEmpresas["dataFim"] = $dataFim->format("Y-m-d");
		file_put_contents($path . "/empresas.json", json_encode($totaisEmpresas));
	}
	return;
}

function criar_relatorio_endosso() {
	$mes = new DateTime($_POST["busca_data"] . "-01");
	$fimMes = new DateTime($mes->format("Y-m-t"));

	$empresas = mysqli_fetch_all(query(
		"SELECT empr_nb_id, empr_tx_nome FROM empresa"
			. " WHERE empr_tx_status = 'ativo'"
			. (!empty($_POST["empresa"]) ? " AND empr_nb_id = " . $_POST["empresa"] : "")
			. " ORDER BY empr_tx_nome ASC;"
	), MYSQLI_ASSOC);

	$totaisEmpresas = [
		"jornadaPrevista" => "00:00",
		"jornadaEfetiva" => "00:00",
		"he50APagar" => "00:00",
		"he100APagar" => "00:00",
		"adicionalNoturno" => "00:00",
		"esperaIndenizada" => "00:00",
		"saldoAnterior" => "00:00",
		"saldoPeriodo" => "00:00",
		"saldoFinal" => "00:00",
		"qtdMotoristas" => 0
	];

	foreach ($empresas as $empresa) {
		$path = "./arquivos/endossos" . "/" . $mes->format("Y-m") . "/" . $empresa["empr_nb_id"];
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}
		// if(file_exists($path."/empresa_".$empresa["empr_nb_id"].".json")){
		// 	if(date("Y-m-d", filemtime($path."/empresa_".$empresa["empr_nb_id"].".json")) == date("Y-m-d")){
		// 		// echo 
		// 		// 	"<script>"
		// 		// 	."confirm('O relatório de ".$empresa["empr_tx_nome"]." já foi gerado hoje, deseja gerar novamente?');"
		// 		// 	."</script>"
		// 		// ;
		// 		continue;
		// 	}
		// }

		$motoristas = mysqli_fetch_all(query(
			"SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_banco, enti_tx_ocupacao FROM entidade"
				. " WHERE enti_tx_status = 'ativo'"
				. " AND enti_nb_empresa = " . $empresa["empr_nb_id"]
				. " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')"
				. " ORDER BY enti_tx_nome ASC;"
		), MYSQLI_ASSOC);

		$rows = [];
		$statusEndossos = [
			"E" => 0,
			"EP" => 0,
			"N" => 0
		];
		foreach ($motoristas as $motorista) {
			//Status Endosso{
			$endossos = mysqli_fetch_all(query(
				"SELECT * FROM endosso"
					. " WHERE endo_tx_status = 'ativo'"
					. " AND endo_nb_entidade = '" . $motorista["enti_nb_id"] . "'"
					. " AND ("
					. "   (endo_tx_de  >= '" . $mes->format("Y-m-01") . "' AND endo_tx_de  <= '" . $mes->format("Y-m-t") . "')"
					. "OR (endo_tx_ate >= '" . $mes->format("Y-m-01") . "' AND endo_tx_ate <= '" . $mes->format("Y-m-t") . "')"
					. "OR (endo_tx_de  <= '" . $mes->format("Y-m-01") . "' AND endo_tx_ate >= '" . $mes->format("Y-m-t") . "')"
					. ")"
					. " ORDER BY endo_tx_ate;"
			), MYSQLI_ASSOC);

			$statusEndosso = "N";
			if (count($endossos) >= 1) {
				$statusEndosso = "E";
				if (strtotime($mes->format("Y-m-01")) != strtotime($endossos[0]["endo_tx_de"]) || strtotime($mes->format("Y-m-t")) > strtotime($endossos[count($endossos) - 1]["endo_tx_ate"])) {
					$statusEndosso .= "P";
				}
			}
			$statusEndossos[$statusEndosso]++;
			//}

			//saldoAnterior{
			$saldoAnterior = mysqli_fetch_assoc(query(
				"SELECT endo_tx_saldo FROM endosso"
					. " WHERE endo_tx_status = 'ativo'"
					. " AND endo_tx_ate < '" . $mes->format("Y-m-01") . "'"
					. " AND endo_tx_matricula = '" . $motorista["enti_tx_matricula"] . "'"
					. " ORDER BY endo_tx_ate DESC"
					. " LIMIT 1;"
			));

			if (!empty($saldoAnterior)) {
				if (!empty($saldoAnterior["endo_tx_saldo"])) {
					$saldoAnterior = $saldoAnterior["endo_tx_saldo"];
				} elseif (!empty($motorista["enti_tx_banco"])) {
					$saldoAnterior = $motorista["enti_tx_banco"];
				}
				if (strlen($motorista["enti_tx_banco"]) > 5 && $motorista["enti_tx_banco"][0] == "0") {
					$saldoAnterior = substr($saldoAnterior, 1);
				}
			} else {
				$saldoAnterior = "00:00";
			}
			//}

			$totaisMot = [
				"jornadaPrevista" => "",
				"jornadaEfetiva" => "",
				"he50APagar" => "",
				"he100APagar" => "",
				"adicionalNoturno" => "",
				"esperaIndenizada" => "",
				"saldoAnterior" => $saldoAnterior,
				"saldoPeriodo" => "",
				"saldoFinal" => ""
			];
			if ($statusEndosso != "N") {
				$totaisMot = [
					"jornadaPrevista" => "00:00",
					"jornadaEfetiva" => "00:00",
					"he50APagar" => "00:00",
					"he100APagar" => "00:00",
					"adicionalNoturno" => "00:00",
					"esperaIndenizada" => "00:00",
					"saldoPeriodo" => "00:00",
					"saldoFinal" => "00:00"
				];

				foreach ($endossos as $endosso) {
					$endosso = lerEndossoCSV($endosso["endo_tx_filename"]);
					if (empty($endosso["totalResumo"]["he50APagar"])) {
						$pago = calcularHorasAPagar(
							operarHorarios([$endosso["totalResumo"]["saldoAnterior"], $endosso["totalResumo"]["diffSaldo"]], "+"),
							$endosso["totalResumo"]["he50"],
							$endosso["totalResumo"]["he100"],
							$endosso["endo_tx_horasApagar"]
						);
						[$endosso["totalResumo"]["he50APagar"], $endosso["totalResumo"]["he100APagar"]] = $pago;
					}
					$totaisMot["jornadaPrevista"] 	= operarHorarios([$totaisMot["jornadaPrevista"], $endosso["totalResumo"]["jornadaPrevista"]], "+");
					$totaisMot["jornadaEfetiva"] 	= operarHorarios([$totaisMot["jornadaEfetiva"], $endosso["totalResumo"]["diffJornadaEfetiva"]], "+");
					$totaisMot["he50APagar"] 		= operarHorarios([$totaisMot["he50APagar"], $endosso["totalResumo"]["he50APagar"]], "+");
					$totaisMot["he100APagar"] 		= operarHorarios([$totaisMot["he100APagar"], $endosso["totalResumo"]["he100APagar"]], "+");
					$totaisMot["adicionalNoturno"] 	= operarHorarios([$totaisMot["adicionalNoturno"], $endosso["totalResumo"]["adicionalNoturno"]], "+");
					$totaisMot["esperaIndenizada"] 	= operarHorarios([$totaisMot["esperaIndenizada"], $endosso["totalResumo"]["esperaIndenizada"]], "+");
					if (empty($totaisMot["saldoAnterior"])) {
						$totaisMot["saldoAnterior"] = $endosso["totalResumo"]["saldoAnterior"];
					}
					$totaisMot["saldoPeriodo"] 		= operarHorarios([$totaisMot["saldoPeriodo"], $endosso["totalResumo"]["diffSaldo"]], "+");
					if (empty($endosso["totalResumo"]["saldoBruto"]) && !empty($endosso["totalResumo"]["saldoAtual"])) {
						$totaisMot["saldoFinal"] = operarHorarios([$endosso["totalResumo"]["saldoAtual"], $endosso["totalResumo"]["he100"]], "+");
					} else {
						$totaisMot["saldoFinal"] = operarHorarios([$endosso["totalResumo"]["saldoAnterior"], $totaisMot["saldoPeriodo"]], "+");
						$totaisMot["saldoFinal"] = operarHorarios([$totaisMot["saldoFinal"], $endosso["totalResumo"]["he50APagar"], $endosso["totalResumo"]["he100APagar"]], "-");
					}
				}
			}

			$row = [
				"idMotorista" => $motorista["enti_nb_id"],
				"matricula" => $motorista["enti_tx_matricula"],
				"nome" => $motorista["enti_tx_nome"],
				"ocupacao" => $motorista["enti_tx_ocupacao"],
				"statusEndosso" => $statusEndosso,
				"jornadaPrevista" => $totaisMot["jornadaPrevista"],
				"jornadaEfetiva" => $totaisMot["jornadaEfetiva"],
				"he50APagar" => $totaisMot["he50APagar"],
				"he100APagar" => $totaisMot["he100APagar"],
				"adicionalNoturno" => $totaisMot["adicionalNoturno"],
				"esperaIndenizada" => $totaisMot["esperaIndenizada"],
				"saldoAnterior" => $saldoAnterior,
				"saldoPeriodo" => $totaisMot["saldoPeriodo"],
				"saldoFinal" => $totaisMot["saldoFinal"]
			];
			$nomeArquivo = $motorista["enti_tx_matricula"] . ".json";
			file_put_contents($path . "/" . $nomeArquivo, json_encode($row, JSON_UNESCAPED_UNICODE));

			$rows[] = $row;
		}


		$totaisEmpr = [
			"jornadaPrevista" => "00:00",
			"jornadaEfetiva" => "00:00",
			"he50APagar" => "00:00",
			"he100APagar" => "00:00",
			"adicionalNoturno" => "00:00",
			"esperaIndenizada" => "00:00",
			"saldoAnterior" => "00:00",
			"saldoPeriodo" => "00:00",
			"saldoFinal" => "00:00"
		];

		foreach ($rows as $row) {
			$totaisEmpr["jornadaPrevista"]  = operarHorarios([$totaisEmpr["jornadaPrevista"], $row["jornadaPrevista"]], "+");
			$totaisEmpr["jornadaEfetiva"]   = operarHorarios([$totaisEmpr["jornadaEfetiva"], $row["jornadaEfetiva"]], "+");
			$totaisEmpr["he50APagar"]       = operarHorarios([$totaisEmpr["he50APagar"], $row["he50APagar"]], "+");
			$totaisEmpr["he100APagar"]      = operarHorarios([$totaisEmpr["he100APagar"], $row["he100APagar"]], "+");
			$totaisEmpr["adicionalNoturno"] = operarHorarios([$totaisEmpr["adicionalNoturno"], $row["adicionalNoturno"]], "+");
			$totaisEmpr["esperaIndenizada"] = operarHorarios([$totaisEmpr["esperaIndenizada"], $row["esperaIndenizada"]], "+");
			$totaisEmpr["saldoAnterior"]    = operarHorarios([$totaisEmpr["saldoAnterior"], $row["saldoAnterior"]], "+");
			$totaisEmpr["saldoPeriodo"]     = operarHorarios([$totaisEmpr["saldoPeriodo"], $row["saldoPeriodo"]], "+");
			$totaisEmpr["saldoFinal"]       = operarHorarios([$totaisEmpr["saldoFinal"], $row["saldoFinal"]], "+");
		}

		//Adicionar valores da empresa à soma total das empresas{
		if (empty($_POST["empresa"])) {
			foreach ($totaisEmpr as $key => $value) {
				$totaisEmpresas[$key] = operarHorarios([$totaisEmpresas[$key], $value], "+");
			}
			$totaisEmpresas["qtdMotoristas"] += count($motoristas);
		}
		//}

		$empresa["totais"] = $totaisEmpr;
		$empresa["qtdMotoristas"] = count($motoristas);
		$empresa["dataInicio"] = $mes->format("Y-m-01");
		$empresa["dataFim"] = $mes->format("Y-m-t");
		$empresa["percEndossado"] = ($statusEndossos["E"]) / array_sum(array_values($statusEndossos));

		file_put_contents($path . "/empresa_" . $empresa["empr_nb_id"] . ".json", json_encode($empresa));
	}

	if (empty($_POST["empresa"])) {
		$path = "./arquivos/endossos" . "/" . $mes->format("Y-m");
		$totaisEmpresas["dataInicio"] = $mes->format("Y-m-01");
		$totaisEmpresas["dataFim"] = $mes->format("Y-m-t");
		file_put_contents($path . "/empresas.json", json_encode($totaisEmpresas));
	}
	return;
}

function criar_relatorio_jornada() {
	global $totalResumo;

	$periodoInicio = new DateTime($_POST["busca_data"] . "-01");
	$periodoFim = new DateTime($periodoInicio->format("Y-m-t"));

	$campos = ["fimJornada", "inicioRefeicao", "fimRefeicao"];

	$empresas = mysqli_fetch_all(query(
		"SELECT empr_nb_id, empr_tx_nome FROM empresa"
			. " WHERE empr_tx_status = 'ativo'"
			. " ORDER BY empr_tx_nome ASC;"
	), MYSQLI_ASSOC);

	$totaisEmpresas = [
		"fimJornada" => 0,
		"inicioRefeicao" => 0,
		"fimRefeicao" => 0,
		"qtdMotoristas" => 0
	];

	foreach ($empresas as $empresa) {
		$path = "./arquivos/jornada" . "/" . $periodoInicio->format("Y-m") . "/" . $empresa["empr_nb_id"];
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}

		$motoristas = mysqli_fetch_all(query(
			"SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_ocupacao FROM entidade"
				. " WHERE enti_tx_status = 'ativo'"
				. " AND enti_nb_empresa = " . $empresa["empr_nb_id"]
				. " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')"
				. " ORDER BY enti_tx_nome ASC;"
		), MYSQLI_ASSOC);

		foreach ($motoristas as $motorista) {
			$row = [];
			for ($date = clone $periodoInicio; $date <= $periodoFim; $date->modify('+1 day')) {
				$diaPonto = diaDetalhePonto($motorista["enti_tx_matricula"], $date->format('Y-m-d'));
				$descanso = "";
				$espera = "";
				$jornada = "";
				$jornadaEfetiva = ""; 
				$refeicao = "";
				$repouso = "";

				if (!is_int(strpos($diaPonto["inicioJornada"], "fa fa-warning")) && is_int(strpos($diaPonto["fimJornada"], "fa fa-warning")) 
				|| !is_int(strpos($diaPonto["inicioJornada"], "fa fa-warning")) && ! is_int(strpos($diaPonto["fimJornada"], "fa fa-warning"))) {
					$inicioRefeicaoWarning = is_int(strpos($diaPonto["inicioRefeicao"], "fa-warning"));
					$fimRefeicaoWarning = is_int(strpos($diaPonto["fimRefeicao"], "fa-warning"));

					if ($inicioRefeicaoWarning && $fimRefeicaoWarning) {
						$refeicao = "";
					} elseif ($inicioRefeicaoWarning || $fimRefeicaoWarning) {
						$refeicao = "*";
					} else {
						if (is_int(strpos($diaPonto["diffRefeicao"], "fa-info-circle")) 
						&& is_int(strpos($diaPonto["diffRefeicao"], "color:red;"))
						&& !is_int(strpos($diaPonto["diffRefeicao"], "fa fa-warning"))) {
							$refeicao = "*";
						}
					}

					if (trim($diaPonto["diffDescanso"]) == "00:00") {
						$descanso = "";
					} else {
						if (is_int(strpos($diaPonto["diffDescanso"], "fa-info-circle")) 
						&& is_int(strpos($diaPonto["diffDescanso"], "color:red;"))
						&& !is_int(strpos($diaPonto["diffDescanso"], "fa fa-warning"))) {
							$descanso  = "*";
						}
					}

					if ($diaPonto["diffEspera"] == "00:00") {
						$espera = "";
					} else {
						if (is_int(strpos($diaPonto["diffEspera"], "fa-info-circle")) 
						&& is_int(strpos($diaPonto["diffEspera"], "color:red;"))
						&& !is_int(strpos($diaPonto["diffEspera"], "fa fa-warning"))) {
							$espera = "*";
						}
					}

					if ($diaPonto["diffRepouso"] == "00:00") {
						$repouso = "";
					} else {
						if (is_int(strpos($diaPonto["diffRepouso"], "fa-info-circle")) 
						&& is_int(strpos($diaPonto["diffRepouso"], "color:red;"))
						&& !is_int(strpos($diaPonto["diffRepouso"], "fa fa-warning"))) {
							$repouso = "*";
						}
					}

					if (is_int(strpos($diaPonto["diffJornada"], "fa-info-circle")) 
					&& is_int(strpos($diaPonto["diffJornada"], "color:red;")))  {
						$jornada = "*";
					} else{
						$jornada = $diaPonto["diffJornada"];
					}

					$jornadaEfetiva = $diaPonto["diffJornadaEfetiva"] == "00:00" ? "-" : $diaPonto["diffJornadaEfetiva"];

				}

				if ($jornada == '*') {
					$campos = !empty(array_filter([$jornada,$descanso, $espera, $refeicao, $repouso]));
				} else{
					$campos = !empty(array_filter([$descanso, $espera, $refeicao, $repouso]));
				}

				if ($campos) {
					$row [] = [
						"data" => $date->format('d/m/Y'),
						"matricula" => $motorista["enti_tx_matricula"],
						"nome" => $motorista["enti_tx_nome"],
						"ocupacao" => $motorista["enti_tx_ocupacao"],
						"jornada" => strip_tags($jornada),
						"jornadaEfetiva" => strip_tags($jornadaEfetiva),
						"refeicao" => strip_tags($refeicao),
						"espera" => strip_tags($espera),
						"descanso" => strip_tags($descanso),
						"repouso" => strip_tags($repouso),
						"dataInicio" => $periodoInicio->format('d/m/Y'),
						"dataFim" => $periodoFim->format('d/m/Y')
					];

					if (!empty($row)) {
						$nomeArquivo = $motorista["enti_tx_matricula"] . ".json";
						file_put_contents($path . "/" . $nomeArquivo, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
					}
	
				}
				
			}
		}
	}
	return;
}

function relatorio_nao_conformidade_juridica() {
	// $periodoInicio = $_POST["busca_dataInicio"];
	// $periodoFim = $_POST["busca_dataFim"];

	$periodoInicio = "2024-10-01";
	$periodoFim = "2024-10-07";

	$empresas = mysqli_fetch_all(
		query(
			"SELECT empr_nb_id, empr_tx_nome"
			. " FROM `empresa` WHERE empr_tx_status = 'ativo'"
			. " ORDER BY empr_tx_nome ASC;"
		),
		MYSQLI_ASSOC
	);

	foreach ($empresas as $empresa) {
		// $path = "./arquivos/nao_conformidade_juridica" . "/" . $periodoInicio->format("Y-m") . "/" . $empresa["empr_nb_id"];
		// if (!is_dir($path)) {
		// 	mkdir($path, 0755, true);
		// }

		$motoristas = mysqli_fetch_all(
			query(
				"SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula FROM entidade"
				. " WHERE enti_tx_status = 'ativo'"
				. " AND enti_nb_empresa = " . $empresa['empr_nb_id']
				. " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')"
				. " ORDER BY enti_tx_nome ASC;"
			),
			MYSQLI_ASSOC
		);

		foreach ($motoristas as $motorista) {
			$row = [];

			$diasPonto = [];
			$dataTimeInicio = new DateTime($periodoInicio);
			$dataTimeFim = new DateTime($periodoFim);

			$mes = $dataTimeInicio->format('m');
			$ano = $dataTimeInicio->format('Y');

			for ($date = $dataTimeInicio; $date <= $dataTimeFim; $date->modify('+1 day')) {
				$dataVez = $date->format('Y-m-d');

				$diasPonto[] = diaDetalhePonto($motorista['enti_tx_matricula'], $dataVez);
			}

			$contadores = [
				"inicioSemRegistro" => 0,
				"inicioRefeicaoSemRegistro" => 0,
				"fimRefeicaoSemRegistro" => 0,
				"fimSemRegistro" => 0,
				"refeicao1h" => 0,
				"refeicao2h" => 0,
				"esperaAberto" => 0,
				"descansoAberto" => 0,
				"repousoAberto" => 0,
				"jornadaAberto" => 0,
				"jornadaExedida" => 0,
				"mdcDescanso" => 0,
				"intersticio" => 0,
			];

			foreach ($diasPonto as $diaPonto) {
				if (strpos($diaPonto["inicioJornada"], "fa-warning") !== false) {
					$contadores["inicioSemRegistro"] += 1;
				}
				if (strpos($diaPonto["inicioRefeicao"], "fa-warning") !== false) {
					$contadores["inicioRefeicaoSemRegistro"] += 1;
				}
				if (strpos($diaPonto["fimRefeicao"], "fa-warning") !== false) {
					$contadores["fimRefeicaoSemRegistro"] += 1;
				}
				if (strpos($diaPonto["fimJornada"], "fa-warning") !== false) {
					$contadores["fimSemRegistro"] += 1;
				}

				if (strpos($diaPonto["diffRefeicao"], "fa-warning") !== false) {
					$contadores["refeicao1h"] += 1;
				}
				
				if (strpos($diaPonto["diffRefeicao"], "fa-info-circle") !== false &&
				strpos($diaPonto["diffRefeicao"], "color:orange;") !== false) {
					$contadores["refeicao2h"] += 1;
				}

				if (strpos($diaPonto["diffEspera"], "fa-info-circle") !== false &&
				strpos($diaPonto["diffEspera"], "color:red;") !== false) {
					$contadores["esperaAberto"] += 1;
				}

				if (strpos($diaPonto["diffDescanso"], "fa-info-circle") !== false &&
				strpos($diaPonto["diffDescanso"], "color:red;") !== false) {
					$contadores["descansoAberto"] += 1;
				}

				if (strpos($diaPonto["diffRepouso"], "fa-info-circle") !== false &&
				strpos($diaPonto["diffRepouso"], "color:red;") !== false) {
					$contadores["repousoAberto"] += 1;
				}

				if (strpos($diaPonto["diffJornada"], "fa-info-circle") !== false &&
				strpos($diaPonto["diffJornada"], "color:red;") !== false) {
					$contadores["jornadaAberto"] += 1;
				}

				if (strpos($diaPonto["diffJornadaEfetiva"], "fa-warning") !== false &&
				strpos($diaPonto["diffJornadaEfetiva"], "color:orange;") !== false) {
					$contadores["jornadaExedida"] += 1;
				}

				if (strpos($diaPonto["maximoDirecaoContinua"], "fa-warning") !== false &&
				strpos($diaPonto["maximoDirecaoContinua"], "color:orange;") !== false) {
					$contadores["mdcDescanso"] += 1;
				}

				if (strpos($diaPonto["intersticio"], "fa-warning") !== false &&
				strpos($diaPonto["intersticio"], "color:red;") !== false) {
					$contadores["intersticio"] += 1;
				}
				
				$row = $contadores;
			};
 
			echo '<pre>';
			var_dump($motorista["enti_tx_nome"]);
			echo json_encode($row, JSON_PRETTY_PRINT);
			echo '</pre>';
		}

		die();
	}
}