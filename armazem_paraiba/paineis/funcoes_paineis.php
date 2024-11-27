<?php

	/* Modo debug{
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//}*/

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
			$path = "./arquivos/saldos"."/".$dataMes->format("Y-m")."/".$_POST["empresa"];
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
							." AND endo_nb_entidade = '".$motorista["enti_nb_id"]."'"
							." AND ("
							."   (endo_tx_de  >= '".$dataMes->format("Y-m-d")."' AND endo_tx_de  <= '".$dataFim->format("Y-m-d")."')"
							."OR (endo_tx_ate >= '".$dataMes->format("Y-m-d")."' AND endo_tx_ate <= '".$dataFim->format("Y-m-d")."')"
							."OR (endo_tx_de  <= '".$dataMes->format("Y-m-d")."' AND endo_tx_ate >= '".$dataFim->format("Y-m-d")."')"
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
							. " AND endo_tx_ate < '".$dataMes->format("Y-m-d")."'"
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
			$path = "./arquivos/saldos"."/".$dataMes->format("Y-m");
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
			// 		echo 
			// 			"<script>"
			// 			."confirm('O relatório de ".$empresa["empr_tx_nome"]." já foi gerado hoje, deseja gerar novamente?');"
			// 			."</script>"
			// 		;
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
		$periodoInicio = new DateTime($_POST["busca_dataMes"]."-01");
		$hoje = new DateTime();

		if ($periodoInicio->format('Y-m') === $hoje->format('Y-m')) {
			// Se for o mês atual, a data limite é o dia de hoje
			$periodoFim = $hoje;
		} else {
			$periodoFim = new DateTime($periodoInicio->format("Y-m-t"));
		}

		$campos = ["fimJornada", "inicioRefeicao", "fimRefeicao"];

		$totaisEmpresas = [
			"fimJornada" => 0,
			"inicioRefeicao" => 0,
			"fimRefeicao" => 0,
			"qtdMotoristas" => 0
		];

			$path = "./arquivos/jornada"."/".$periodoInicio->format("Y-m")."/".$_POST["empresa"];
			if (!is_dir($path)) {
				mkdir($path, 0755, true);
			}

			$motoristas = mysqli_fetch_all(query(
				"SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_ocupacao FROM entidade"
				. " WHERE enti_tx_status = 'ativo'"
				. " AND enti_nb_empresa = ". $_POST["empresa"]
				. " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')"
				. " ORDER BY enti_tx_nome ASC;"
			), MYSQLI_ASSOC);

			foreach ($motoristas as $motorista) {
				$row = [];
				$diaPonto = [];
				for ($date = clone $periodoInicio; $date <= $periodoFim; $date->modify('+1 day')) {
					$diaPonto[] = diaDetalhePonto($motorista["enti_tx_matricula"], $date->format('Y-m-d'));
				}

				foreach($diaPonto as $dia){

					$data = $date->format('Y-m-d');
					$descanso = "";
					$espera = "";
					$jornada = "";
					$jornadaEfetiva = "";
					$refeicao = "";
					$repouso = "";

					if (
						!is_int(strpos($dia["inicioJornada"], "fa fa-warning")) && is_int(strpos($dia["fimJornada"], "fa fa-warning"))
					) {
						// Verificação da refeição
						$inicioRefeicaoWarning = is_int(strpos($dia["inicioRefeicao"], "fa-warning"));
						$fimRefeicaoWarning = is_int(strpos($dia["fimRefeicao"], "fa-warning"));
						$diffRefeicaoInfo = is_int(strpos($dia["diffRefeicao"], "fa-info-circle")) && is_int(strpos($dia["diffRefeicao"], "color:red;"));
						$diffRefeicaoWarning = is_int(strpos($dia["diffRefeicao"], "fa-warning"));

						if ($inicioRefeicaoWarning && $fimRefeicaoWarning) {
							$refeicao = "";
						} elseif ($inicioRefeicaoWarning || $fimRefeicaoWarning || (!empty($dia["inicioRefeicao"]) && empty($dia["fimRefeicao"]))) {
							$refeicao = "*";
						} elseif ($diffRefeicaoInfo && !$diffRefeicaoWarning) {
							$refeicao = "*";
						} elseif ($dia["diffRefeicao"] != "00:00") {
							$refeicao = $dia["diffRefeicao"];
						} else {
							$refeicao = "";
						}

						// Verificação do descanso
						$descanso = (trim($dia["diffDescanso"]) == "00:00") ? "" : (
							is_int(strpos($dia["diffDescanso"], "fa-info-circle"))
							&& is_int(strpos($dia["diffDescanso"], "color:red;"))
							&& !is_int(strpos($dia["diffDescanso"], "fa fa-warning")) ? "*" : $dia["diffDescanso"]
						);

						// Verificação da espera
						$espera = ($dia["diffEspera"] == "00:00") ? "" : (
							is_int(strpos($dia["diffEspera"], "fa-info-circle"))
							&& is_int(strpos($dia["diffEspera"], "color:red;"))
							&& !is_int(strpos($dia["diffEspera"], "fa fa-warning")) ? "*" : $dia["diffEspera"]
						);

						// Verificação do repouso
						$repouso = ($dia["diffRepouso"] != "00:00") ? (
							is_int(strpos($dia["diffRepouso"], "fa-info-circle"))
							&& is_int(strpos($dia["diffRepouso"], "color:red;"))
							&& !is_int(strpos($dia["diffRepouso"], "fa fa-warning")) ? "*" : $dia["diffRepouso"]
						) : "";

						// Verificação da jornada
						if ((is_int(strpos($dia["diffJornada"], "fa-info-circle"))
								&& is_int(strpos($dia["diffJornada"], "color:red;")))
							|| is_int(strpos($dia["fimJornada"], "fa fa-warning"))
						) {

							$hora = preg_replace('/<strong>.*?<\/strong>/', '', $dia["inicioJornada"]);
							$hora = preg_replace('/[^0-9:]/', '', $hora);
							$hora = trim($hora);
							$horaEspecifica = new DateTime($hora);
							$horaAtual = new DateTime();
							$jornada = $horaAtual->diff($horaEspecifica)->format('%H:%I');
						} else {
							$jornada = $dia["diffJornada"];
						}

						$jornadaEfetiva = $dia["diffJornadaEfetiva"] == "00:00" ? "----" : $dia["diffJornadaEfetiva"];
					}
					
					$endossado = mysqli_fetch_all(
						query(
							"SELECT * FROM endosso 
							JOIN entidade ON endo_tx_matricula = enti_tx_matricula
							WHERE '".$data."' BETWEEN endo_tx_de AND endo_tx_ate
							AND enti_nb_id = ".$motorista["enti_nb_id"]."
							AND endo_tx_status = 'ativo';"
						),
						MYSQLI_ASSOC
					);
					
					$dataItem = DateTime::createFromFormat('d/m/Y', $dia["data"]);
					$dataAtual = new DateTime();
					$diferenca = $dataAtual->diff($dataItem);
					$diaDiferenca = $diferenca->days;
					$campos = !empty(array_filter([$jornada, $descanso, $espera, $refeicao, $repouso]));
					if ($campos) {
						$horaLimpa = preg_replace('/<strong>.*?<\/strong>/', '',  $dia["inicioJornada"]);
						$horaLimpa = preg_replace('/[^0-9:]/', '', $horaLimpa);
						$horaLimpa = trim($horaLimpa);
						if (count($endossado) < 1) {
							$row[] = [
								"data" => $dia["data"],
								"inicioJornada" => $horaLimpa,
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
					}
				}

				if (!empty($row)) {
					$nomeArquivo = $motorista["enti_tx_matricula"].".json";
					$arquivosMantidos[] = $nomeArquivo;
					file_put_contents($path."/".$nomeArquivo, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
				}

				$pasta = dir($path);
				while ($arquivo = $pasta->read()) {
					if (!in_array($arquivo, $arquivosMantidos)) {
						unlink($arquivo); // Apaga o arquivo
					}
				}
				$pasta->close();
			}
		// sleep(1);
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

		if ($_POST["busca_endossado"] == "endossado") {
			$mes = new DateTime($_POST["busca_dataMes"] . "-01");
			$endossos = mysqli_fetch_all(query(
				"SELECT * FROM endosso"
					. " WHERE endo_tx_status = 'ativo'"
					. " AND ("
					. "   (endo_tx_de  >= '" . $mes->format("Y-m-01") . "' AND endo_tx_de  <= '" . $mes->format("Y-m-t") . "')"
					. "OR (endo_tx_ate >= '" . $mes->format("Y-m-01") . "' AND endo_tx_ate <= '" . $mes->format("Y-m-t") . "')"
					. "OR (endo_tx_de  <= '" . $mes->format("Y-m-01") . "' AND endo_tx_ate >= '" . $mes->format("Y-m-t") . "')"
					. ")"
					. " ORDER BY endo_tx_ate;"
			), MYSQLI_ASSOC);
		}

		$path = "./arquivos/nao_conformidade_juridica" . "/" . $periodoInicio->format("Y-m") . "/" . $_POST["empresa"];
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}

		$motoristas = mysqli_fetch_all(
			query(
				"SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula, enti_tx_ocupacao FROM entidade"
					. " WHERE enti_tx_status = 'ativo'"
					. " AND enti_nb_empresa = " . $_POST["empresa"]
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
				"jornadaExcedido10h" 		=> 0,
				"jornadaExcedido12h" 		=> 0,
				"mdcDescanso30m" 			=> 0,
				"mdcDescanso15m" 			=> 0,
				"mdcDescanso30m5h" 			=> 0,
				"faltaJustificada"          => 0,
				"falta"                     => 0,

				"dataInicio"				=> $periodoInicio->format("d/m/Y"),
				"dataFim"					=> $periodoFim->format("d/m/Y")
			];

			if ($_POST["busca_endossado"] == "endossado") {
				foreach ($endossos as $endosso) {
					if ($motorista["enti_nb_id"] === $endosso["endo_nb_entidade"]) {
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
								$inicioJornadaWarning && strpos($ponto[12], "fa-info-circle") !== false &&
								strpos($ponto[12], "color:green;") !== false ||
								$inicioJornadaWarning && strpos($ponto[12], "fa fa-warning") !== false &&
								strpos($ponto[12], "color:orange;") !== false
							) {
								$totalMotorista["faltaJustificada"] += 1;
							}

							if ($inicioJornadaWarning && strpos($ponto[12], "fa-info-circle") == false && strpos($ponto[12], "color:green;" == false)
							|| $inicioJornadaWarning && strpos($ponto[12], "fa fa-warning") == false && strpos($ponto[12], "color:orange;" == false)) {
								$totalMotorista["falta"] += 1;
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
								$totalMotorista["fimRefeicaoSemRegistro"] += 1;
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
						$motoristaTotais[] = $totalMotorista;

						if (!is_dir($path . "/endossado/")) {
							mkdir($path . "/endossado/", 0755, true);  // Cria o diretório com permissões adequadas
						}

						file_put_contents($path . "/endossado/" . $motorista["enti_tx_matricula"] . ".json", json_encode($totalMotorista, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
					}
					
				}
			} else {

				$diaPonto = [];
				for ($date = clone $periodoInicio; $date <= $periodoFim; $date->modify('+1 day')) {
					$diaPonto[] = diaDetalhePonto($motorista['enti_tx_matricula'], $date->format('Y-m-d'));
				}

				foreach ($diaPonto as $dia) {
					// Jornada
					$inicioJornadaWarning = strpos($dia["inicioJornada"], "fa-warning") !== false;
					$fimJornadaWarning = strpos($dia["fimJornada"], "fa-warning") !== false;
					$diffJornada = $dia["diffJornada"];
					$diffJornadaEfetiva = $dia["diffJornadaEfetiva"];

					// Verificações jornada
					if ($inicioJornadaWarning || $fimJornadaWarning) {
						$totalMotorista["jornadaPrevista"] += 1;
					}

					if ($inicioJornadaWarning && strpos($dia["jornadaPrevista"], "fa-info-circle") !== false && 
						strpos($dia["jornadaPrevista"], "color:green;") !== false) {
						$totalMotorista["faltaJustificada"] += 1;
					}

					if($inicioJornadaWarning && strpos($dia["jornadaPrevista"], "fa-info-circle") === false && strpos($dia["jornadaPrevista"], "color:green;") === false){
						$totalMotorista["falta"] += 1;
					}

					if (strpos($diffJornada, "fa-info-circle") !== false && strpos($diffJornada, "color:red;") !== false) {
						$totalMotorista["jornada"] += 1;
					}
					if (strpos($diffJornadaEfetiva, "fa-warning") !== false && strpos($diffJornadaEfetiva, "color:orange;") !== false) {
						$totalMotorista["jornadaEfetiva"] += 1;
					}
					if (strpos($diffJornadaEfetiva, "Tempo excedido de 10:00") !== false) {
						$totalMotorista["jornadaExcedido10h"] += 1;
					}
					if (strpos($diffJornadaEfetiva, "Tempo excedido de 12:00") !== false) {
						$totalMotorista["jornadaExcedido12h"] += 1;
					}

					// Refeição
					$inicioRefeicao = strpos($dia["inicioRefeicao"], "fa-warning") !== false;
					$fimRefeicao = strpos($dia["fimRefeicao"], "fa-warning") !== false;
					$diffRefeicao = $dia["diffRefeicao"];

					if ($inicioRefeicao || $fimRefeicao) {
						$totalMotorista["refeicao"]++;
					} elseif (strpos($diffRefeicao, "fa-warning") !== false) {
						$totalMotorista["refeicao"]++;
					}
					if (strpos($diffRefeicao, "fa-info-circle") !== false && strpos($diffRefeicao, "color:orange;") !== false) {
						$totalMotorista["refeicao"]++;
					}
					if ($inicioRefeicao) {
						$totalMotorista["inicioRefeicaoSemRegistro"] += 1;
					}
					if ($fimRefeicao) {
						$totalMotorista["fimRefeicaoSemRegistro"] += 1;
					}
					if (strpos($diffRefeicao, "01:00h") !== false) {
						$totalMotorista["refeicao1h"] += 1;
					}
					if (strpos($diffRefeicao, "02:00h") !== false) {
						$totalMotorista["refeicao2h"] += 1;
					}

					// Máximo Direção Contínua
					$maximoDirecaoContinua = $dia["maximoDirecaoContinua"];
					if (strpos($maximoDirecaoContinua, "fa-warning") !== false && strpos($maximoDirecaoContinua, "color:orange;") !== false) {
						$totalMotorista["mdc"]++;
					}
					if (strpos($maximoDirecaoContinua, "digiridos não respeitado") !== false) {
						$totalMotorista["mdcDescanso30m5h"] += 1;
					}
					if (strpos($maximoDirecaoContinua, "00:15 não respeitado") !== false) {
						$totalMotorista["mdcDescanso15m"] += 1;
					}
					if (strpos($maximoDirecaoContinua, "00:30 não respeitado") !== false) {
						$totalMotorista["mdcDescanso30m"] += 1;
					}

					// Outros campos de descanso
					foreach (["Refeicao", "Espera", "Descanso", "Repouso"] as $campo) {
						$diffCampo = $dia["diff" . $campo];
						if (strpos($diffCampo, "fa-info-circle") !== false && strpos($diffCampo, "color:red;") !== false) {
							$totalMotorista[strtolower($campo)]++;
						}
					}

					// Interstício
					if (strpos($dia["intersticio"], "faltaram") !== false) {
						$totalMotorista["intersticioSuperior"]++;
					}
					if (strpos($dia["intersticio"], "ininterruptas") !== false) {
						$totalMotorista["intersticioInferior"]++;
					}
				}

				$motoristaTotais[] = $totalMotorista;
			}

			if (!is_dir($path . "/nao_endossado/")) {
				mkdir($path . "/nao_endossado/", 0755, true);  // Cria o diretório com permissões adequadas
			}

			file_put_contents($path . "/nao_endossado/" . $motorista["enti_tx_matricula"] . ".json", json_encode($totalMotorista, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		}
		$totaisEmpr = [
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
			"jornadaExcedido10h" 		=> 0,
			"jornadaExcedido12h" 		=> 0,
			"mdcDescanso30m" 			=> 0,
			"mdcDescanso15m" 			=> 0,
			"mdcDescanso30m5h" 			=> 0,
		];

		foreach ($motoristaTotais as $motorista) {
			foreach ($totaisEmpr as $key => $value) {
				if (isset($motorista[$key]) && is_numeric($motorista[$key])) {
					$totaisEmpr[$key] += $motorista[$key];
				}
			}
		}

		// var_dump($totaisEmpr);

		if ($_POST["busca_endossado"] == "endossado") {
			file_put_contents($path . "/endossado/empresa_" . $_POST["empresa"] . ".json", json_encode($totaisEmpr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		} else {
			file_put_contents($path . "/nao_endossado/empresa_" . $_POST["empresa"] . ".json", json_encode($totaisEmpr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		}

		// sleep(1);
		return;
	}

	function criar_relatorio_ajustes() {
		$periodoInicio = new DateTime("2024-10-01");
		$hoje = new DateTime("2024-10-10");
		// $hoje = new DateTime();

		// if ($periodoInicio->format('Y-m') === $hoje->format('Y-m')) {
		// 	// Se for o mês atual, a data limite é o dia de hoje
		// 	$periodoFim = $hoje;
		// } else {
		// 	$periodoFim = new DateTime($periodoInicio->format("Y-m-t"));
		// }

		$empresas = mysqli_fetch_all(query(
			"SELECT empr_nb_id, empr_tx_nome FROM empresa"
				. " WHERE empr_tx_status = 'ativo'"
				. (!empty($_POST["empresa"]) ? " AND empr_nb_id = ".$_POST["empresa"] : "")
				. " ORDER BY empr_tx_nome ASC;"
		), MYSQLI_ASSOC);

		$dominiosAutotrac = ["/comav"];
		if(!in_array($_SERVER["REQUEST_URI"], $dominiosAutotrac)){
			$macros = mysqli_fetch_all(
				query(
				"SELECT macr_tx_nome FROM macroponto WHERE macr_tx_fonte = 'positron'"
				),
				MYSQLI_ASSOC
			);
		} else {
			$macros = mysqli_fetch_all(
				query(
				"SELECT macr_tx_nome FROM macroponto WHERE macr_tx_fonte != 'positron'"
				),
				MYSQLI_ASSOC
			);	
		}

		$macros = array_column($macros, 'macr_tx_nome');
			
		foreach ($empresas as $empresa) {
			$totaisEmpr = []; 
			$totaisEmpresa = []; 
			$rows = [];
			// $totais= [];
			$path = "./arquivos/ajustes"."/". $periodoInicio->format("Y-m")."/".$empresa["empr_nb_id"];
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

			foreach ($motoristas as $motorista) {
				$ocorrencias = [];

				foreach($macros as $macro){
					if (!isset($ocorrencias[$macro])) {
						$ocorrencias[$macro] = 0;
					}
				}
				$totalMotorista = [
					"matricula" 				=> $motorista["enti_tx_matricula"],
					"nome" 						=> $motorista["enti_tx_nome"],
					"ocupacao" 					=> $motorista["enti_tx_ocupacao"],


					// "dataInicio"				=> $periodoInicio->format("d/m/Y"),
					// "dataFim"					=> $periodoFim->format("d/m/Y")
				];

				$pontos = mysqli_fetch_all(
					query(
					"SELECT ponto.pont_tx_data, ponto.pont_tx_matricula, motivo.moti_tx_nome, macroponto.macr_tx_nome"
						. " FROM ponto"
						. " INNER JOIN motivo motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id"
						. " INNER JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno"
						. " WHERE pont_tx_status = 'ativo'"
						. " AND pont_tx_matricula = '".$motorista["enti_tx_matricula"] ."'"
						. " AND pont_nb_arquivoponto IS NULL"
						. " AND pont_tx_data BETWEEN STR_TO_DATE( '". $periodoInicio->format("Y-m-d") ." 00:00:00', '%Y-%m-%d %H:%i:%s')"
						. " AND STR_TO_DATE( '". $hoje->format("Y-m-d") ." 23:59:59', '%Y-%m-%d %H:%i:%s');"
					),
					MYSQLI_ASSOC
				);

				foreach ($pontos as $registro) {

					$macr_tx_nome = $registro['macr_tx_nome'];

					if (in_array($macr_tx_nome, $macros)) {
						$ocorrencias[$macr_tx_nome]++;
					}
				}

				$totalMotorista = array_merge($totalMotorista, $ocorrencias);
				// Filtrar apenas os campos numéricos que precisam ser verificados
				$verificaValores = array_filter($totalMotorista, function ($key) {
						return !in_array($key, ["matricula", "nome", "ocupacao"]);
					}, ARRAY_FILTER_USE_KEY);
				
				$rows[] = $ocorrencias;
			    if(array_sum($verificaValores) > 0){
					file_put_contents($path."/". $motorista["enti_tx_matricula"]. ".json", json_encode($totalMotorista, JSON_UNESCAPED_UNICODE));
				}	
			}

			foreach ($rows as $key => $values) {
				foreach ($values as $key => $value) {
					if (!isset($totaisEmpr[$key])) {
						$totaisEmpr[$key] = 0; // Inicializa a chave com 0, se ainda não existir
					}
					$totaisEmpr[$key] += $value;
				}
			}

			$totais [] = $totaisEmpr;
			$empresa = array_merge($totaisEmpr, $empresa);

			
			$empresa["qtdMotoristas"] = count($motoristas);
			$empresa["dataInicio"] = $periodoInicio->format("d/m/Y");
			$empresa["dataFim"] = $hoje->format("d/m/Y");
			
			file_put_contents($path."/empresa_".$empresa["empr_nb_id"].".json", json_encode($empresa));
		}

		foreach ($totais as $key => $values) {
			foreach ($values as $key => $value) {
				if (!isset($totaisEmpresa[$key])) {
					$totaisEmpresa[$key] = 0; // Inicializa a chave com 0, se ainda não existir
				}
				$totaisEmpresa[$key] += $value;
			}
		}
		

		if (empty($_POST["empresa"])) {
			$path = "./arquivos/ajustes"."/". $periodoInicio->format("Y-m");
			$totaisEmpresa["dataInicio"] = $periodoInicio->format("d/m/Y");
			$totaisEmpresa["dataFim"] = $hoje->format("d/m/Y");
			file_put_contents($path."/empresas.json", json_encode($totaisEmpresa));
		}

		return;
	}