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
		file_put_contents($path.'/'.$fileName, $data);
	}
	//}

	//Funções de criação de cada painel{
	function criar_relatorio_saldo() {

		global $totalResumo;

		//Conferir se os campos POST estão preenchidos{
			$camposObrig = ["busca_dataMes" => "Mês"];
			$errorMsg = conferirCamposObrig($camposObrig, $_POST);
			if(!empty($errorMsg)){
				set_status("ERRO: ".$errorMsg);
				unset($_POST["acao"]);
				index();
				exit;
			}

		//}
			
		$dataMes = DateTime::createFromFormat("Y-m-d H:i:s", $_POST["busca_dataMes"]."-01 00:00:00");
		$dataFim = DateTime::createFromFormat("Y-m-d H:i:s", (date("Y-m-d") < $dataMes->format("Y-m-t")? date("Y-m-d"): $dataMes->format("Y-m-t"))." 00:00:00");

		$empresas = mysqli_fetch_all(query(
			"SELECT empr_nb_id, empr_tx_nome FROM empresa"
				. " WHERE empr_tx_status = 'ativo'"
				. (!empty($_POST["empresa"]) ? " AND empr_nb_id = ".$_POST["empresa"] : "")
				. " ORDER BY empr_tx_nome ASC;"
		), MYSQLI_ASSOC);

		$totaisEmpresas = [
			"jornadaPrevista" 	=> "00:00",
			"jornadaEfetiva" 	=> "00:00",
			"HESemanal" 		=> "00:00",
			"HESabado" 			=> "00:00",
			"adicionalNoturno" 	=> "00:00",
			"esperaIndenizada" 	=> "00:00",
			"saldoAnterior" 	=> "00:00",
			"saldoPeriodo" 		=> "00:00",
			"saldoFinal" 		=> "00:00",
			"qtdMotoristas" 	=> 0
		];

		foreach ($empresas as $empresa) {
			$path = "./arquivos/saldos"."/".$dataMes->format("Y-m")."/".$empresa["empr_nb_id"];
			if (!is_dir($path)) {
				mkdir($path, 0755, true);
			}
			if(file_exists($path."/empresa_".$empresa["empr_nb_id"].".json")){
				if(date("Y-m-d", filemtime($path."/empresa_".$empresa["empr_nb_id"].".json")) == date("Y-m-d")){
					// continue;
				}
			}

			$motoristas = mysqli_fetch_all(query(
				"SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_banco, enti_tx_ocupacao FROM entidade"
					. " WHERE enti_tx_status = 'ativo'"
					. " AND enti_nb_empresa = ".$empresa["empr_nb_id"]
					. " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')"
					. " ORDER BY enti_tx_nome ASC;"
			), MYSQLI_ASSOC);

			$rows = [];
			$statusEndossos = [
				"E" 	=> 0,
				"EP" 	=> 0,
				"N" 	=> 0
			];
			foreach ($motoristas as $motorista) {
				//Status Endosso{
					$endossos = mysqli_fetch_all(query(
						"SELECT * FROM endosso"
							." WHERE endo_tx_status = 'ativo'"
							." AND endo_nb_entidade = '" . $motorista["enti_nb_id"] . "'"
							." AND ("
							."   (endo_tx_de  >= '" . $dataMes->format("Y-m-d") . "' AND endo_tx_de  <= '" . $dataFim->format("Y-m-d") . "')"
							."OR (endo_tx_ate >= '" . $dataMes->format("Y-m-d") . "' AND endo_tx_ate <= '" . $dataFim->format("Y-m-d") . "')"
							."OR (endo_tx_de  <= '" . $dataMes->format("Y-m-d") . "' AND endo_tx_ate >= '" . $dataFim->format("Y-m-d") . "')"
							.");"
					), MYSQLI_ASSOC);

					$statusEndosso = "N";
					if (count($endossos) >= 1) {
						$statusEndosso = "E";
						if (strtotime($dataMes->format("Y-m-d")) < strtotime($endossos[0]["endo_tx_de"]) || strtotime($dataFim->format("Y-m-d")) > strtotime($endossos[count($endossos) - 1]["endo_tx_ate"])) {
							$statusEndosso .= "P";
						}
					}
					$statusEndossos[$statusEndosso]++;
				//}

				//saldoAnterior{
					$saldoAnterior = mysqli_fetch_assoc(query(
						"SELECT endo_tx_saldo FROM endosso"
							. " WHERE endo_tx_status = 'ativo'"
							. " AND endo_tx_ate < '" . $dataMes->format("Y-m-d") . "'"
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
					"jornadaPrevista" 	=> "00:00",
					"jornadaEfetiva" 	=> "00:00",
					"HESemanal" 		=> "00:00",
					"HESabado" 			=> "00:00",
					"adicionalNoturno" 	=> "00:00",
					"esperaIndenizada" 	=> "00:00",
					"saldoPeriodo" 		=> "00:00",
					"saldoFinal" 		=> "00:00"
				];

				for ($dia = new DateTime($dataMes->format("Y-m-d")); $dia <= $dataFim; $dia->modify("+1 day")) {
					$diaPonto = diaDetalhePonto($motorista["enti_tx_matricula"], $dia->format("Y-m-d"));
					//Formatando informações{
						foreach (array_keys($diaPonto) as $f) {
							if (in_array($f, ["data", "diaSemana"])) {
								continue;
							}
							if (strlen($diaPonto[$f]) > 5) {
								$diaPonto[$f] = preg_replace("/.*&nbsp;/", "", $diaPonto[$f]);
								if(is_int(preg_match_all("/(-?\d{2,4}:\d{2})/", $diaPonto[$f], $matches))){
									$diaPonto[$f] = array_pop($matches[1]);
								}else{
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
					"idMotorista" 		=> $motorista["enti_nb_id"],
					"matricula" 		=> $motorista["enti_tx_matricula"],
					"ocupacao" 			=> $motorista["enti_tx_ocupacao"],
					"nome" 				=> $motorista["enti_tx_nome"],
					"statusEndosso" 	=> $statusEndosso,
					"jornadaPrevista" 	=> $totaisMot["jornadaPrevista"],
					"jornadaEfetiva" 	=> $totaisMot["jornadaEfetiva"],
					"HESemanal" 		=> $totaisMot["HESemanal"],
					"HESabado" 			=> $totaisMot["HESabado"],
					"adicionalNoturno" 	=> $totaisMot["adicionalNoturno"],
					"esperaIndenizada" 	=> $totaisMot["esperaIndenizada"],

					"saldoAnterior" 	=> $saldoAnterior,
					"saldoPeriodo" 		=> $totaisMot["saldoPeriodo"],
					"saldoFinal" 		=> $totaisMot["saldoFinal"]
				];
				$nomeArquivo = $motorista["enti_tx_matricula"].".json";
				file_put_contents($path."/".$nomeArquivo, json_encode($row, JSON_UNESCAPED_UNICODE));

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
			$empresa["dataInicio"] = $dataMes->format("Y-m-d");
			$empresa["dataFim"] = $dataFim->format("Y-m-d");
			$empresa["percEndossado"] = ($statusEndossos["E"]) / array_sum(array_values($statusEndossos));

			file_put_contents($path."/empresa_".$empresa["empr_nb_id"].".json", json_encode($empresa));
		}

		if (empty($_POST["empresa"])) {
			$path = "./arquivos/saldos" . "/" . $dataMes->format("Y-m");
			$totaisEmpresas["dataInicio"] = $dataMes->format("Y-m-d");
			$totaisEmpresas["dataFim"] = $dataFim->format("Y-m-d");
			file_put_contents($path."/empresas.json", json_encode($totaisEmpresas));
		}
		return;
	}

	function criar_relatorio_endosso() {
		$mes = new DateTime($_POST["busca_data"]."-01");
		$fimMes = new DateTime($mes->format("Y-m-t"));

		$empresas = mysqli_fetch_all(query(
			"SELECT empr_nb_id, empr_tx_nome FROM empresa"
				. " WHERE empr_tx_status = 'ativo'"
				. (!empty($_POST["empresa"]) ? " AND empr_nb_id = ".$_POST["empresa"] : "")
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
			$path = "./arquivos/endossos"."/".$mes->format("Y-m")."/".$empresa["empr_nb_id"];
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
			"SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_banco, enti_tx_ocupacao, enti_tx_admissao FROM entidade"
					. " WHERE enti_tx_status = 'ativo'"
					. " AND enti_nb_empresa = ".$empresa["empr_nb_id"]
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
				if (substr($motorista["enti_tx_admissao"],0,7) <= $mes->format("Y-m")) {
					$endossos = mysqli_fetch_all(query(
						"SELECT * FROM endosso"
							. " WHERE endo_tx_status = 'ativo'"
							. " AND endo_nb_entidade = '".$motorista["enti_nb_id"]."'"
							. " AND ("
							. "   (endo_tx_de  >= '".$mes->format("Y-m-01")."' AND endo_tx_de  <= '".$mes->format("Y-m-t")."')"
							. "OR (endo_tx_ate >= '".$mes->format("Y-m-01")."' AND endo_tx_ate <= '".$mes->format("Y-m-t")."')"
							. "OR (endo_tx_de  <= '".$mes->format("Y-m-01")."' AND endo_tx_ate >= '".$mes->format("Y-m-t")."')"
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
							. " AND endo_tx_ate < '".$mes->format("Y-m-01")."'"
							. " AND endo_tx_matricula = '".$motorista["enti_tx_matricula"]."'"
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
					$nomeArquivo = $motorista["enti_tx_matricula"].".json";
					file_put_contents($path."/".$nomeArquivo, json_encode($row, JSON_UNESCAPED_UNICODE));
	
					$rows[] = $row;
				}
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

			file_put_contents($path."/empresa_".$empresa["empr_nb_id"].".json", json_encode($empresa));
		}

		if (empty($_POST["empresa"])) {
			$path = "./arquivos/endossos"."/".$mes->format("Y-m");
			$totaisEmpresas["dataInicio"] = $mes->format("Y-m-01");
			$totaisEmpresas["dataFim"] = $mes->format("Y-m-t");
			file_put_contents($path."/empresas.json", json_encode($totaisEmpresas));
		}
		return;
	}

	function criar_relatorio_jornada() {
		global $totalResumo;

		$periodoInicio = new DateTime($_POST["busca_dataInicio"]);
		$periodoFim = new DateTime($_POST["busca_dataFim"]);

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
					$data = $date->format('Y-m-d');
					$descanso = "";
					$espera = "";
					$jornada = "";
					$jornadaEfetiva = "";
					$refeicao = "";
					$repouso = "";

					if (
						!is_int(strpos($diaPonto["inicioJornada"], "fa fa-warning")) && is_int(strpos($diaPonto["fimJornada"], "fa fa-warning"))
					) {
						$inicioRefeicaoWarning = is_int(strpos($diaPonto["inicioRefeicao"], "fa-warning"));
						$fimRefeicaoWarning = is_int(strpos($diaPonto["fimRefeicao"], "fa-warning"));
						$diffRefeicaoInfo = is_int(strpos($diaPonto["diffRefeicao"], "fa-info-circle"));
						$diffRefeicaoRed = is_int(strpos($diaPonto["diffRefeicao"], "color:red;"));
						$diffRefeicaoWarning = is_int(strpos($diaPonto["diffRefeicao"], "fa-warning"));

						if ($inicioRefeicaoWarning && $fimRefeicaoWarning) {
							$refeicao = "";
						} elseif ($inicioRefeicaoWarning || $fimRefeicaoWarning || (!empty($diaPonto["inicioRefeicao"]) && empty($diaPonto["fimRefeicao"]))) {
							$refeicao = "*";
						} elseif ($diffRefeicaoInfo && $diffRefeicaoRed && !$diffRefeicaoWarning) {
							$refeicao = "*";
						} else {
							$refeicao = ""; // Se não atender a nenhuma das condições, manter o valor vazio
						}


						if (trim($diaPonto["diffDescanso"]) == "00:00") {
							$descanso = "";
						} else {
							if (
								is_int(strpos($diaPonto["diffDescanso"], "fa-info-circle"))
								&& is_int(strpos($diaPonto["diffDescanso"], "color:red;"))
								&& !is_int(strpos($diaPonto["diffDescanso"], "fa fa-warning"))
							) {
								$descanso  = "*";
							}
						}

						if ($diaPonto["diffEspera"] == "00:00") {
							$espera = "";
						} else {
							if (
								is_int(strpos($diaPonto["diffEspera"], "fa-info-circle"))
								&& is_int(strpos($diaPonto["diffEspera"], "color:red;"))
								&& !is_int(strpos($diaPonto["diffEspera"], "fa fa-warning"))
							) {
								$espera = "*";
							}
						}

						if ($diaPonto["diffRepouso"] == "00:00") {
							$repouso = "";
						} else {
							if (
								is_int(strpos($diaPonto["diffRepouso"], "fa-info-circle"))
								&& is_int(strpos($diaPonto["diffRepouso"], "color:red;"))
								&& !is_int(strpos($diaPonto["diffRepouso"], "fa fa-warning"))
							) {
								$repouso = "*";
							}
						}

						if (
							is_int(strpos($diaPonto["diffJornada"], "fa-info-circle"))
							&& is_int(strpos($diaPonto["diffJornada"], "color:red;"))
							|| is_int(
								strpos($diaPonto["fimJornada"], "fa fa-warning")
							)
						) {
							$jornada = "*";
						} else {
							$jornada = $diaPonto["diffJornada"];
						}

						if ($jornada != "00:00") {
							$jornadaEfetiva = $diaPonto["diffJornadaEfetiva"] == "00:00" ? "*" : $diaPonto["diffJornadaEfetiva"];
						}
					}

					if ($jornada == '*') {
						$campos = !empty(array_filter([$jornada, $descanso, $espera, $refeicao, $repouso]));
					} else {
						$campos = !empty(array_filter([$descanso, $espera, $refeicao, $repouso]));
					}

					$endossado = mysqli_fetch_all(
						query(
							"SELECT * FROM endosso 
							JOIN entidade ON endo_tx_matricula = enti_tx_matricula
							WHERE '" . $data . "' BETWEEN endo_tx_de AND endo_tx_ate
							AND enti_nb_id = " . $motorista["enti_nb_id"] . "
							AND endo_tx_status = 'ativo';"
						),
						MYSQLI_ASSOC
					);

					if (count($endossado) > 0) {
						$dia = $diaPonto["data"] . ' (E)';
					} else {
						$dia = $diaPonto["data"];
						$dataItem = DateTime::createFromFormat('d/m/Y', $diaPonto["data"]);
						$dataAtual = new DateTime();
						$diferenca = $dataAtual->diff($dataItem);
						$diaDiferenca = $diferenca->days;
					}

					if ($campos) {
						$row[] = [
							"data" => $dia,
							"diaDiferenca" => $diaDiferenca,
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
					}

					if (!empty($row)) {
						$nomeArquivo = $motorista["enti_tx_matricula"] . ".json";
						file_put_contents($path . "/" . $nomeArquivo, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
					}
				}
			}
		}
		return;
	}

	function relatorio_nao_conformidade_juridica() {

		$periodoInicio = new DateTime($_POST["busca_dataMes"] . "-01");
		$hoje = new DateTime();

		if ($periodoInicio->format('Y-m') === $hoje->format('Y-m')) {
			// Se for o mês atual, a data limite é o dia de hoje
			$periodoFim = $hoje;
		} else {
			$periodoFim = new DateTime($periodoInicio->format("Y-m-t"));
		}

		$empresas = mysqli_fetch_all(
			query(
				"SELECT empr_nb_id, empr_tx_nome"
				. " FROM `empresa` WHERE empr_tx_status = 'ativo'"
				. " ORDER BY empr_tx_nome ASC;"
			),
			MYSQLI_ASSOC
		);

		foreach ($empresas as $empresa) {
			$path = "./arquivos/nao_conformidade_juridica" . "/" . $periodoInicio->format("Y-m") . "/" . $empresa["empr_nb_id"];
			if (!is_dir($path)) {
				mkdir($path, 0755, true);
			}

			$motoristas = mysqli_fetch_all(
				query(
					"SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula, enti_tx_ocupacao FROM entidade"
					. " WHERE enti_tx_status = 'ativo'"
					. " AND enti_nb_empresa = " . $empresa['empr_nb_id']
					. " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')"
					. " ORDER BY enti_tx_nome ASC;"
				),
				MYSQLI_ASSOC
			);

			$row = [];

			foreach ($motoristas as $motorista) {

				$totalMotorista = [
					"matricula" 				=> $motorista["enti_tx_matricula"],
					"nome" 						=> $motorista["enti_tx_nome"],
					"ocupacao" 					=> $motorista["enti_tx_ocupacao"],
					"jornadaPrevista" 			=> 0,
					"jornadaEfetiva" 			=> 0,
					"refeicao" 					=> 0,
					"espera" 					=> 0,
					"descanso" 					=> 0,
					"repouso" 					=> 0,
					"jornada" 					=> 0,
					"mdc"		 				=> 0,
					"intersticioInferior" 		=> 0,
					"intersticioSuperior" 		=> 0,

					"inicioRefeicaoSemRegistro" => 0,
					"fimRefeicaoSemRegistro" 	=> 0,
					"refeicao1h" 				=> 0,
					"refeicao2h" 				=> 0,
					"jornadaExedida10h" 		=> 0,
					"jornadaExedida12h" 		=> 0,
					"mdcDescanso30m" 			=> 0,
					"mdcDescanso15m" 			=> 0,
					"mdcDescanso30m5h" 			=> 0,

					"dataInicio"				=> $periodoInicio->format("d/m/Y"),
					"dataFim"					=> $periodoFim->format("d/m/Y")
				];

				if ($_POST["busca_endossado"] == "endossado") {
					$mes = new DateTime($_POST["busca_dataMes"] . "-01");
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

					foreach ($endossos as $endosso) {
						$endosso = lerEndossoCSV($endosso["endo_tx_filename"]);

						foreach ($endosso["endo_tx_pontos"] as $ponto) {
							$inicioJornadaWarning = is_int(strpos($ponto["3"], "fa-warning"));
							$fimJornadaWarning = is_int(strpos($ponto["6"], "fa-warning"));

							// Verificar se o inicio ou fim têm "fa-warning" e incrementar apenas uma vez
							if ($inicioJornadaWarning || $fimJornadaWarning) {
								$totalMotorista["jornadaPrevista"] += 1;
							}

							if (is_int(strpos($ponto[11], "fa-info-circle")) && is_int(strpos($ponto[11], "color:red;"))) {
								$totalMotorista["jornada"] += 1;
							}

							if (is_int(strpos($ponto[13], "fa-warning")) && is_int(strpos($ponto[13], "color:orange;"))) {
								$totalMotorista["jornadaEfetiva"] += 1;
							}

							if (
								is_int(strpos($ponto[13], "fa-warning")) && is_int(strpos($ponto[13], "color:orange;"))
								&& is_int(strpos($ponto[13], "Tempo excedido de 10:00"))
							) {
								$totalMotorista["jornadaExedida10h"] += 1;
							}

							if (
								is_int(strpos($ponto[13], "fa-warning")) && is_int(strpos($ponto[13], "color:orange;"))
								&& is_int(strpos($ponto[13], "Tempo excedido de 12:00"))
							) {
								$totalMotorista["jornadaExedida12h"] += 1;
							}

							$inicioRefeicao = is_int(strpos($ponto[4], "fa-warning"));
							$fimRefeicao = is_int(strpos($ponto[5], "fa-warning"));

							if ($inicioRefeicao || $fimRefeicao) {
								$totalMotorista["refeicao"] += 1;
							}

							if (!$inicioRefeicao && !$fimRefeicao && is_int(strpos($ponto[7], "fa-warning"))) {
								$totalMotorista["refeicao"] += 1;
							}

							if (is_int(strpos($ponto[7], "fa-info-circle")) && is_int(strpos($ponto[7], "color:orange;"))) {
								$totalMotorista["refeicao"] += 1;
							}

							if ($inicioRefeicao) {
								$totalMotorista["inicioRefeicaoSemRegistro"] += 1;
							}

							if ($fimRefeicao) {
								$totalMotorista["inicioRefeicaoSemRegistro"] += 1;
							}

							if (is_int(strpos($ponto[7], "fa-warning")) && is_int(strpos($ponto[7], "01:00h"))) {
								$totalMotorista["refeicao1h"] += 1;
							}

							if (
								is_int(strpos($ponto[7], "fa-info-circle")) && is_int(strpos($ponto[7], "color:orange;"))
								&& is_int(strpos($ponto[7], "02:00h"))
							) {
								$totalMotorista["refeicao2h"] += 1;
							}

							if (is_int(strpos($ponto[8], "fa-info-circle")) && is_int(strpos($ponto[8], "color:red;"))) {
								$totalMotorista["espera"] += 1;
							}

							if (is_int(strpos($ponto[9], "fa-info-circle")) && is_int(strpos($ponto[9], "color:red;"))) {
								$totalMotorista["descanso"] += 1;
							}

							if (is_int(strpos($ponto[10], "fa-info-circle")) && is_int(strpos($ponto[10], "color:red;"))) {
								$totalMotorista["repouso"] += 1;
							}

							if (is_int(strpos($ponto[14], "fa-warning")) && is_int(strpos($ponto[14], "color:orange;"))) {
								$totalMotorista["mdc"] += 1;
							}

							if (
								is_int(strpos($ponto[14], "fa-warning")) && is_int(strpos($ponto[14], "color:orange;"))
								&& is_int(strpos($ponto[14], "digiridos não respeitado"))
							) {
								$totalMotorista["mdcDescanso30m5h"] += 1;
							}

							if (
								is_int(strpos($ponto[14], "fa-warning")) && is_int(strpos($ponto[14], "color:orange;"))
								&& is_int(strpos($ponto[14], "00:15 não respeitado"))
							) {
								$totalMotorista["mdcDescanso15m"] += 1;
							}

							if (
								is_int(strpos($ponto[14], "fa-warning")) && is_int(strpos($ponto[14], "color:orange;"))
								&& is_int(strpos($ponto[14], "00:30 não respeitado"))
							) {
								$totalMotorista["mdcDescanso30m"] += 1;
							}

							if (is_int(strpos($ponto[15], "faltaram"))) {
								$totalMotorista["intersticioSuperior"] += 1;
							}

							if (is_int(strpos($ponto[15], "ininterruptas"))) {
								$totalMotorista["intersticioInferior"] += 1;
							}
						}

						if (!is_dir($path . "/endossado/")) {
							mkdir($path . "/endossado/", 0777, true);  // Cria o diretório com permissões adequadas
						}

						file_put_contents($path . "/endossado/" . $motorista["enti_tx_matricula"] . ".json", json_encode($totalMotorista, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
					}
				} else {

					for ($date = clone $periodoInicio; $date <= $periodoFim; $date->modify('+1 day')) {
						$diaPonto = diaDetalhePonto($motorista['enti_tx_matricula'], $date->format('Y-m-d'));

						$inicioJornadaWarning = is_int(strpos($diaPonto["inicioJornada"], "fa-warning"));
						$fimJornadaWarning = is_int(strpos($diaPonto["fimJornada"], "fa-warning"));

						// Verificar se o inicio ou fim têm "fa-warning" e incrementar apenas uma vez
						if ($inicioJornadaWarning || $fimJornadaWarning) {
							$totalMotorista["jornadaPrevista"] += 1;
						}

						if (is_int(strpos($diaPonto["diffJornada"], "fa-info-circle")) && is_int(strpos($diaPonto["diffJornada"], "color:red;"))) {
							$totalMotorista["jornada"] += 1;
						}

						if (is_int(strpos($diaPonto["diffJornadaEfetiva"], "fa-warning")) && is_int(strpos($diaPonto["diffJornadaEfetiva"], "color:orange;"))) {
							$totalMotorista["jornadaEfetiva"] += 1;
						}

						$inicioRefeicao = is_int(strpos($diaPonto["inicioRefeicao"], "fa-warning"));
						$fimRefeicao = is_int(strpos($diaPonto["fimRefeicao"], "fa-warning"));

						if ($inicioRefeicao || $fimRefeicao) {
							$totalMotorista["refeicao"] += 1;
						}

						if (!$inicioRefeicao && !$fimRefeicao && is_int(strpos($diaPonto["diffRefeicao"], "fa-warning"))) {
							$totalMotorista["refeicao"] += 1;
						}

						if (is_int(strpos($diaPonto["diffRefeicao"], "fa-info-circle")) && is_int(strpos($diaPonto["diffRefeicao"], "color:orange;"))) {
							$totalMotorista["refeicao"] += 1;
						}

						if (is_int(strpos($diaPonto["diffEspera"], "fa-info-circle")) && is_int(strpos($diaPonto["diffEspera"], "color:red;"))) {
							$totalMotorista["espera"] += 1;
						}

						if (is_int(strpos($diaPonto["diffDescanso"], "fa-info-circle")) && is_int(strpos($diaPonto["diffDescanso"], "color:red;"))) {
							$totalMotorista["descanso"] += 1;
						}
						if (is_int(strpos($diaPonto["diffRepouso"], "fa-info-circle")) && is_int(strpos($diaPonto["diffRepouso"], "color:red;"))) {
							$totalMotorista["repouso"] += 1;
						}

						if (is_int(strpos($diaPonto["maximoDirecaoContinua"], "fa-warning")) && is_int(strpos($diaPonto["maximoDirecaoContinua"], "color:orange;"))) {
							$totalMotorista["mdc"] += 1;
						}

						if (is_int(strpos($diaPonto["intersticio"], "faltaram"))) {
							$totalMotorista["intersticioSuperior"] += 1;
						}

						if (is_int(strpos($diaPonto["intersticio"], "ininterruptas"))) {
							$totalMotorista["intersticioInferior"] += 1;
						}
					}

					if (!is_dir($path . "/nao_endossado/")) {
						mkdir($path . "/nao_endossado/", 0777, true);  // Cria o diretório com permissões adequadas
					}

					file_put_contents($path . "/nao_endossado/" . $motorista["enti_tx_matricula"] . ".json", json_encode($totalMotorista, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
				}
			}
		}

		return;
	}

	function criar_relatorio_ajustes() {
		global $totalResumo;
		// $periodoInicio = $_POST["busca_dataInicio"];
		// $periodoFim = $_POST["busca_dataFim"];
		$periodoInicio = "2024-10-01";
		$periodoFim = "2024-10-11";

		$empresas = mysqli_fetch_all(
			query(
				"SELECT empr_nb_id, empr_tx_nome"
				. " FROM `empresa` WHERE empr_tx_status = 'ativo'"
				. " ORDER BY empr_tx_nome ASC;"
			),
			MYSQLI_ASSOC
		);

		$pontosTipos = [];

		foreach ($empresas as $empresa) {
			$motoristas = mysqli_fetch_all(
				query(
					"SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula, enti_tx_ocupacao FROM entidade"
					. " WHERE enti_tx_status = 'ativo'"
					. " AND enti_nb_empresa = ".$empresa['empr_nb_id']
					. " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')"
					. " ORDER BY enti_tx_nome ASC;"
				),
				MYSQLI_ASSOC
			);
			foreach($motoristas as $motorista){
				$pontos =mysqli_fetch_all(
					query(
					"SELECT ponto.pont_tx_data, ponto.pont_tx_matricula, motivo.moti_tx_nome, pont_tx_tipo"
						. " FROM ponto"
						. " INNER JOIN motivo motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id"
						. " WHERE pont_tx_status = 'ativo'"
						. " AND pont_tx_matricula = '".$motorista["enti_tx_matricula"] ."'"
						. " AND pont_nb_arquivoponto IS NULL"
						. " AND pont_tx_data BETWEEN STR_TO_DATE('2024-10-01 00:00:00', '%Y-%m-%d %H:%i:%s')"
						. " AND STR_TO_DATE('2024-10-11 23:59:59', '%Y-%m-%d %H:%i:%s');"
					),
					MYSQLI_ASSOC
				);
			}

			// echo '<pre>';
			// echo json_encode($pontosTipos, JSON_PRETTY_PRINT);
			// echo '</pre>';
			die();
		}


		// $totalMotorista = count($ajustes);
		// foreach ($ajustes as $value) {
		// 	$totalInicioJorn += count($value["tipos"]["Inicio de Jornada"]);
		// 	$totalFimJorn  += count($value["tipos"]["Fim de Jornada"]);
		// 	$totalInicioReif += count($value["tipos"]["Inicio de Refeição"]);
		// 	$totalFimReif += count($value["tipos"]["Fim de Refeição"]);
		// }

	}