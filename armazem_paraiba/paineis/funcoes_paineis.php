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
				"SELECT * FROM entidade
					LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
					LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
					LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
					WHERE enti_tx_status = 'ativo'
						AND enti_nb_empresa = '{$empresa["empr_nb_id"]}'
						".(!empty($_POST["motorista"])? "AND enti_nb_id = '{$_POST["motorista"]}'": "")."
					ORDER BY enti_tx_nome ASC;"
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
					$diaPonto = diaDetalhePonto($motorista, $dia->format("Y-m-d"));
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

		$campos = ["fimJornada", "inicioRefeicao", "fimRefeicao"];

		$totaisEmpresas = [
			"fimJornada" => 0,
			"inicioRefeicao" => 0,
			"fimRefeicao" => 0,
			"qtdMotoristas" => 0
		];

		$path = "./arquivos/jornada" . "/" . $_POST["empresa"];
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}

		$filtroOcupacao = "";
		if(!empty($_POST["busca_ocupacao"])){
			$filtroOcupacao = "AND enti_tx_ocupacao IN ('{$_POST["busca_ocupacao"]}')";
		}

		$motoristas = mysqli_fetch_all(query(
			"SELECT * FROM entidade
				LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
				LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
				LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
				WHERE enti_tx_status = 'ativo'
					AND enti_nb_empresa = {$_POST["empresa"]}
					{$filtroOcupacao}
				ORDER BY enti_tx_nome ASC;"
		), MYSQLI_ASSOC);

		$pasta = dir($path);
		while (($arquivo = $pasta->read()) !== false) {
			// Ignora os diretórios especiais '.' e '..'
			if ($arquivo != '.' && $arquivo != '..') {
				$arquivoPath = $path .'/'. $arquivo;  // Caminho completo do arquivo
				unlink($arquivoPath);  // Apaga o arquivo
			}
		}
		$pasta->close();

		foreach ($motoristas as $motorista) {
			$row = [];
			$arrayDias = [];
			$datasPontosAbertos = mysqli_fetch_all(query(
				"SELECT p.pont_tx_data FROM ponto p "
				. "JOIN (
						SELECT DATE(pont_tx_data) AS data_simples
						FROM ponto
						WHERE pont_tx_matricula = '{$motorista["enti_tx_matricula"]}'
						GROUP BY DATE(pont_tx_data)
						HAVING 
							SUM(CASE WHEN pont_tx_tipo = 1 THEN 1 ELSE 0 END) > 0
							AND SUM(CASE WHEN pont_tx_tipo = 2 THEN 1 ELSE 0 END) = 0
					) AS sub ON DATE(p.pont_tx_data) = sub.data_simples"
				. " WHERE p.pont_tx_matricula = '{$motorista["enti_tx_matricula"]}'"
					. " AND p.pont_tx_tipo = 1"
					. " AND p.pont_tx_status = 'ativo';"
			), MYSQLI_ASSOC);

			foreach ($datasPontosAbertos as $datas) {
				$endossos = mysqli_fetch_all(query(
					"SELECT endo_tx_de, endo_tx_ate"
					. " FROM `endosso`"
					. " where endo_tx_matricula = '{$motorista["enti_tx_matricula"]}'"
					. " AND '{$datas["pont_tx_data"]}' BETWEEN endo_tx_de AND endo_tx_ate"
				), MYSQLI_ASSOC);
				if (empty($endossos)) {
					$date = DateTime::createFromFormat('Y-m-d H:i:s', $datas["pont_tx_data"]);
					$arrayDias[] = $date->format('Y-m-d');
				}
			}

			foreach ($arrayDias as $dia) {

				$dia = diaDetalhePonto($motorista, $dia);

				$data = $date->format('Y-m-d');
				$descanso = "";
				$espera = "";
				$jornada = "";
				$jornadaEfetiva = "";
				$refeicao = "";
				$repouso = "";

				if (!is_int(strpos($dia["inicioJornada"], "fa fa-warning")) && is_int(strpos($dia["fimJornada"], "fa fa-warning"))) {
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

					// $jornadaEfetiva = $dia["diffJornadaEfetiva"] == "00:00" ? "----" : $dia["diffJornadaEfetiva"];
				}

				$dataItem = DateTime::createFromFormat('d/m/Y', $dia["data"]);
				$dataAtual = new DateTime();
				$diferenca = $dataAtual->diff($dataItem);
				$diaDiferenca = $diferenca->days;
				$campos = !empty(array_filter([$jornada, $descanso, $espera, $refeicao, $repouso]));
				if ($campos) {
					$parametro = mysqli_fetch_all(query(
					"SELECT para_tx_jornadaSemanal, para_tx_jornadaSabado, para_tx_maxHESemanalDiario"
						. " FROM `parametro`"
						. " WHERE para_nb_id = " . $motorista["enti_nb_parametro"]
					), MYSQLI_ASSOC);

					if(date('l', $dia["data"]) == "Saturday"){
						$jornadaDia = $parametro[0]["para_tx_jornadaSabado"];
					} else {
						$jornadaDia = $parametro[0]["para_tx_jornadaSemanal"];
					}

					$horaLimpa = preg_replace('/<strong>.*?<\/strong>/', '',  $dia["inicioJornada"]);
					$horaLimpa = preg_replace('/[^0-9:]/', '', $horaLimpa);
					$horaLimpa = trim($horaLimpa);
					$row[] = [
						"data" => $dia["data"],
						"jornadaDia" => $jornadaDia,
						"limiteExtras" => $parametro[0]["para_tx_maxHESemanalDiario"] == 0 ? '00:00' : $parametro[0]["para_tx_maxHESemanalDiario"],
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
						"repouso" => strip_tags($repouso)
					];
				}
			}

			if (!empty($row)) {
				$nomeArquivo = $motorista["enti_tx_matricula"] . ".json";
				$arquivosMantidos[] = $nomeArquivo;
				file_put_contents($path . "/" . $nomeArquivo, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
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

		$periodoInicio = new DateTime($_POST["busca_dataMes"]."-01");
		$hoje = new DateTime();

		if ($periodoInicio->format('Y-m') === $hoje->format('Y-m')) {
			$hoje->modify('-1 day');
			// Se for o mês atual, a data limite é o dia de hoje
			$periodoFim = $hoje;
		} else {
			$periodoFim = new DateTime($periodoInicio->format("Y-m-t"));
		}

		if ($_POST["busca_endossado"] == "endossado") {
			$mes = new DateTime($_POST["busca_dataMes"]."-01");
			$endossos = mysqli_fetch_all(query(
				"SELECT * FROM endosso"
					. " WHERE endo_tx_status = 'ativo'"
					. " AND ("
					. "   (endo_tx_de  >= '{$mes->format("Y-m-01")}' AND endo_tx_de  <= '{$mes->format("Y-m-t")}')"
					. "OR (endo_tx_ate >= '{$mes->format("Y-m-01")}' AND endo_tx_ate <= '{$mes->format("Y-m-t")}')"
					. "OR (endo_tx_de  <= '{$mes->format("Y-m-01")}' AND endo_tx_ate >= '{$mes->format("Y-m-t")}')"
					. ")"
					. " ORDER BY endo_tx_ate;"
			), MYSQLI_ASSOC);
		}

		$path = "./arquivos/nao_conformidade_juridica"."/".$periodoInicio->format("Y-m")."/".$_POST["empresa"];
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}

		$motoristas = mysqli_fetch_all(query(
			"SELECT * FROM entidade
				LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
				LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
				LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
				WHERE enti_tx_status = 'ativo'
					AND enti_nb_empresa = {$_POST["empresa"]}
					AND enti_tx_dataCadastro <= '{$periodoInicio->format("Y-m-t")}'
				ORDER BY enti_tx_nome ASC;"
		), MYSQLI_ASSOC);

		$row = [];

		if($_POST["busca_endossado"] == "endossado"){
			$dir = '/endossado';
		} else {
			$dir = '/nao_endossado';
		}

		if (is_dir($path.$dir)){
			$pasta = dir($path.$dir);
			while (($arquivo = $pasta->read()) !== false) {
				// Ignora os diretórios especiais '.' e '..'
				if ($arquivo != '.' && $arquivo != '..') {
					$arquivoPath = $path.'/'.$dir.'/'.$arquivo;  // Caminho completo do 
					unlink($arquivoPath);  // Apaga o arquivo
				}
			}
			$pasta->close();
		}

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

				"refeicaoSemRegistro" 		=> 0,
				"refeicao1h" 				=> 0,
				"refeicao2h" 				=> 0,
				"jornadaExcedido10h" 		=> 0,
				"jornadaExcedido12h" 		=> 0,
				"mdcDescanso30m" 			=> 0,
				"mdcDescanso15m" 			=> 0,
				"mdcDescanso30m5h" 			=> 0,
				"faltaJustificada"          => 0,
				"falta"                     => 0,
				"diasConformidade"          => 0,

				"dataInicio"				=> $periodoInicio->format("d/m/Y"),
				"dataFim"					=> $periodoFim->format("d/m/Y")
			];

			if ($_POST["busca_endossado"] == "endossado") {
				foreach ($endossos as $endosso) {
					$houveInteracao = false;
					if ($motorista["enti_nb_id"] === $endosso["endo_nb_entidade"]) {
						$endosso = lerEndossoCSV($endosso["endo_tx_filename"]);

						foreach ($endosso["endo_tx_pontos"] as $ponto) {
							$inicioJornadaWarning = strpos($ponto["3"], "fa-warning") !== false && strpos($ponto["3"], "color:red;")
							&& strpos($ponto["3"], "fa-info-circle") ===  false && strpos($ponto["3"], "color:green;") ===  false;
							$fimJornadaWarning = strpos($ponto["6"], "fa-warning") !== false  && strpos($ponto["6"], "color:red;")
							&& strpos($ponto["6"], "fa-info-circle") ===  false && strpos($ponto["6"], "color:green;") ===  false;
							$diffJornada = $ponto["11"];
							$diffJornadaEfetiva = $ponto["13"];

							// Verificações jornada
							if ($inicioJornadaWarning || $fimJornadaWarning) {
								$totalMotorista["12"] += 1;
								$houveInteracao = true;
							}

							if ($inicioJornadaWarning && strpos($ponto["12"], "fa-info-circle") !== false && 
								strpos($ponto["12"], "color:green;") !== false) {
								$totalMotorista["faltaJustificada"] += 1;
								$houveInteracao = true;
							}

							if($inicioJornadaWarning){
								$totalMotorista["falta"] += 1;
								$houveInteracao = true;
							}

							if (strpos($diffJornada, "fa-info-circle") !== false && strpos($diffJornada, "color:red;") !== false) {
								$totalMotorista["jornada"] += 1;
								$houveInteracao = true;
							}
							if (strpos($diffJornadaEfetiva, "fa-warning") !== false && strpos($diffJornadaEfetiva, "color:orange;") !== false) {
								$totalMotorista["jornadaEfetiva"] += 1;
								$houveInteracao = true;
							}
							if (strpos($diffJornadaEfetiva, "Tempo excedido de 10:00") !== false) {
								$totalMotorista["jornadaExcedido10h"] += 1;
								$houveInteracao = true;
							}
							if (strpos($diffJornadaEfetiva, "Tempo excedido de 12:00") !== false) {
								$totalMotorista["jornadaExcedido12h"] += 1;
								$houveInteracao = true;
							}

							// Refeição
							$inicioRefeicao = strpos($ponto["4"], "fa-warning") !== false;
							$fimRefeicao = strpos($ponto["5"], "fa-warning") !== false;
							$diffRefeicao = $ponto["7"];

							if ($inicioRefeicao || $fimRefeicao) {
								$totalMotorista["refeicao"]++;
								$houveInteracao = true;
							} 
							else if (strpos($diffRefeicao, "fa-warning") !== false) {
								$totalMotorista["refeicao"]++;
								$houveInteracao = true;
							}
							if (strpos($diffRefeicao, "fa-info-circle") !== false && strpos($diffRefeicao, "color:orange;") !== false) {
								$totalMotorista["refeicao"]++;
								$houveInteracao = true;
							}
							if ($inicioRefeicao || $fimRefeicao) {
								$totalMotorista["refeicaoSemRegistro"] += 1;
								$houveInteracao = true;
							}
							if ($inicioRefeicao == false && $fimRefeicao == false && strpos($diffRefeicao, "01:00h") !== false) {
								$totalMotorista["refeicao1h"] += 1;
								$houveInteracao = true;
							}
							if (strpos($diffRefeicao, "02:00h") !== false) {
								$totalMotorista["refeicao2h"] += 1;
								$houveInteracao = true;
							}

							// Máximo Direção Contínua
							$maximoDirecaoContinua = $ponto["14"];
							if (strpos($maximoDirecaoContinua, "fa-warning") !== false && strpos($maximoDirecaoContinua, "color:orange;") !== false) {
								$totalMotorista["mdc"]++;
								$houveInteracao = true;
							}
							if (strpos($maximoDirecaoContinua, "digiridos não respeitado") !== false) {
								$totalMotorista["mdcDescanso30m5h"] += 1;
								$houveInteracao = true;
							}
							if (strpos($maximoDirecaoContinua, "00:15 não respeitado") !== false) {
								$totalMotorista["mdcDescanso15m"] += 1;
								$houveInteracao = true;
							}
							if (strpos($maximoDirecaoContinua, "00:30 não respeitado") !== false) {
								$totalMotorista["mdcDescanso30m"] += 1;
								$houveInteracao = true;
							}

							// Outros campos de descanso
							foreach (["8", "9", "10"] as $campo) {
								$diffCampo = $ponto["diff".$campo];
								if (strpos($diffCampo, "fa-info-circle") !== false && strpos($diffCampo, "color:red;") !== false) {
									$totalMotorista[strtolower($campo)]++;
									$houveInteracao = true;
								}
							}

							// Interstício
							if (strpos($ponto["15"], "faltaram") !== false) {
								$totalMotorista["intersticioSuperior"]++;
								$houveInteracao = true;
							}
							if (strpos($ponto["15"], "ininterruptas") !== false) {
								$totalMotorista["intersticioInferior"]++;
								$houveInteracao = true;
							}

							if ($houveInteracao) {
								$totalMotorista["diasConformidade"]++;
							}
						}
						$motoristaTotais[] = $totalMotorista;

						if (!is_dir($path."/endossado/")) {
							mkdir($path."/endossado/", 0755, true);  // Cria o diretório com permissões adequadas
						}

						file_put_contents($path."/endossado/".$motorista["enti_tx_matricula"].".json", json_encode($totalMotorista, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
					}
					
				}
			} else {

				$diaPonto = [];
				for ($date = clone $periodoInicio; $date <= $periodoFim; $date->modify('+1 day')) {
					$diaPonto[] = diaDetalhePonto($motorista, $date->format('Y-m-d'));
				}

				if (!is_dir($path."/nao_endossado/")) {
					mkdir($path."/nao_endossado/", 0755, true);  // Cria o diretório com permissões adequadas
				}
				
				foreach ($diaPonto as $dia) {
					$houveInteracao = false;
					// Jornada
					$inicioJornadaWarning = strpos($dia["inicioJornada"], "fa-warning") !== false && strpos($dia["inicioJornada"], "color:red;") !== false
					&& strpos($dia["inicioJornada"], "fa-info-circle") ===  false && strpos($dia["inicioJornada"], "color:green;") ===  false;

					$fimJornadaWarning = strpos($dia["fimJornada"], "fa-warning") == false  && strpos($dia["fimJornada"], "color:red;") !== false
					&& strpos($dia["fimJornada"], "fa-info-circle") ===  false && strpos($dia["fimJornada"], "color:green;") ===  false;

					$diffJornada = $dia["diffJornada"];
					$diffJornadaEfetiva = $dia["diffJornadaEfetiva"];

					// Verificações jornada
					if ($inicioJornadaWarning || $fimJornadaWarning) {
						$totalMotorista["jornadaPrevista"] += 1;
						$houveInteracao = true;
					}

					if ($inicioJornadaWarning && strpos($dia["jornadaPrevista"], "fa-info-circle") !== false && 
						strpos($dia["jornadaPrevista"], "color:green;") !== false) {
						$totalMotorista["faltaJustificada"] += 1;
						$houveInteracao = true;
					}

					if($inicioJornadaWarning){
						$totalMotorista["falta"] += 1;
						$houveInteracao = true;
					}

					if (strpos($diffJornada, "fa-info-circle") !== false && strpos($diffJornada, "color:red;") !== false) {
						$totalMotorista["jornada"] += 1;
						$houveInteracao = true;
					}
					if (strpos($diffJornadaEfetiva, "fa-warning") !== false && strpos($diffJornadaEfetiva, "color:orange;") !== false) {
						$totalMotorista["jornadaEfetiva"] += 1;
						$houveInteracao = true;
					}
					if (strpos($diffJornadaEfetiva, "Tempo excedido de 10:00") !== false) {
						$totalMotorista["jornadaExcedido10h"] += 1;
						$houveInteracao = true;
					}
					if (strpos($diffJornadaEfetiva, "Tempo excedido de 12:00") !== false) {
						$totalMotorista["jornadaExcedido12h"] += 1;
						$houveInteracao = true;
					}

					// Refeição
					$inicioRefeicao = strpos($dia["inicioRefeicao"], "fa-warning") !== false;
					$fimRefeicao = strpos($dia["fimRefeicao"], "fa-warning") !== false;
					$diffRefeicao = $dia["diffRefeicao"];

					if ($inicioRefeicao || $fimRefeicao) {
						$totalMotorista["refeicao"]++;
						$houveInteracao = true;
					} 
					else if (strpos($diffRefeicao, "fa-warning") !== false) {
						$totalMotorista["refeicao"]++;
						$houveInteracao = true;
					}
					if (strpos($diffRefeicao, "fa-info-circle") !== false && strpos($diffRefeicao, "color:orange;") !== false) {
						$totalMotorista["refeicao"]++;
						$houveInteracao = true;
					}
					if ($inicioRefeicao || $fimRefeicao) {
						$totalMotorista["refeicaoSemRegistro"] += 1;
						$houveInteracao = true;
					}
					if ($inicioRefeicao == false && $fimRefeicao == false && strpos($diffRefeicao, "01:00h") !== false) {
						$totalMotorista["refeicao1h"] += 1;
						$houveInteracao = true;
					}
					if (strpos($diffRefeicao, "02:00h") !== false) {
						$totalMotorista["refeicao2h"] += 1;
						$houveInteracao = true;
					}

					// Máximo Direção Contínua
					$maximoDirecaoContinua = $dia["maximoDirecaoContinua"];
					if (strpos($maximoDirecaoContinua, "fa-warning") !== false && strpos($maximoDirecaoContinua, "color:orange;") !== false) {
						$totalMotorista["mdc"]++;
						$houveInteracao = true;
					}
					if (strpos($maximoDirecaoContinua, "digiridos não respeitado") !== false) {
						$totalMotorista["mdcDescanso30m5h"] += 1;
						$houveInteracao = true;
					}
					if (strpos($maximoDirecaoContinua, "00:15 não respeitado") !== false) {
						$totalMotorista["mdcDescanso15m"] += 1;
						$houveInteracao = true;
					}
					if (strpos($maximoDirecaoContinua, "00:30 não respeitado") !== false) {
						$totalMotorista["mdcDescanso30m"] += 1;
						$houveInteracao = true;
					}

					// Outros campos de descanso
					foreach (["Espera", "Descanso", "Repouso"] as $campo) {
						$diffCampo = $dia["diff".$campo];
						if (strpos($diffCampo, "fa-info-circle") !== false && strpos($diffCampo, "color:red;") !== false) {
							$totalMotorista[strtolower($campo)]++;
							$houveInteracao = true;
						}
					}

					// Interstício
					if (strpos($dia["intersticio"], "faltaram") !== false) {
						$totalMotorista["intersticioSuperior"]++;
						$houveInteracao = true;
					}
					if (strpos($dia["intersticio"], "ininterruptas") !== false) {
						$totalMotorista["intersticioInferior"]++;
						$houveInteracao = true;
					}

					if ($houveInteracao) {
						$totalMotorista["diasConformidade"]++;
					}
				}

				$motoristaTotais[] = $totalMotorista;
	
				file_put_contents($path."/nao_endossado/".$motorista["enti_tx_matricula"].".json", json_encode($totalMotorista, JSON_UNESCAPED_UNICODE));
			}

		}
		
		$totaisEmpr = [
			"falta" 			        => 0,
			"jornadaEfetiva" 			=> 0,
			"refeicao" 					=> 0,
			"espera" 					=> 0,
			"descanso" 					=> 0,
			"repouso" 					=> 0,
			"jornada" 					=> 0,
			"mdc"		 				=> 0,
			"intersticioInferior" 		=> 0,
			"intersticioSuperior" 		=> 0,

			"refeicaoSemRegistro" 		=> 0,
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

		if ($_POST["busca_endossado"] == "endossado") {
			file_put_contents($path."/endossado/empresa_".$_POST["empresa"].".json", json_encode($totaisEmpr, JSON_UNESCAPED_UNICODE));
		} else {
			file_put_contents($path."/nao_endossado/empresa_".$_POST["empresa"].".json", json_encode($totaisEmpr, JSON_UNESCAPED_UNICODE));
		}

		// sleep(1);
		return;
	}

	function criar_relatorio_ajustes() {
		$periodoInicio = new DateTime($_POST["busca_periodo"][0]);
		$periodoFim = new DateTime($_POST["busca_periodo"][1]);

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
			$path = "./arquivos/ajustes"."/". $periodoInicio->format("Y-m")."/".$empresa["empr_nb_id"];
			if (is_dir($path)){
				$pasta = dir($path);
				while (($arquivo = $pasta->read()) !== false) {
					// Ignora os diretórios especiais '.' e '..'
					if ($arquivo != '.' && $arquivo != '..') {
						$arquivoPath = $path.'/'.$arquivo;  // Caminho completo do 
						unlink($arquivoPath);  // Apaga o arquivo
					}
				}
				$pasta->close();
			}
		}
			
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
						. " AND enti_nb_empresa = ".$empresa['empr_nb_id']
						. " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')"
						. " ORDER BY enti_tx_nome ASC;"
				),
				MYSQLI_ASSOC
			);

			$pasta = dir($path);
			if (is_dir($pasta)) {
				while (($arquivo = $pasta->read()) !== false) {
					// Ignora os diretórios especiais '.' e '..'
					if ($arquivo != '.' && $arquivo != '..') {
						$arquivoPath = $path .'/'. $arquivo;  // Caminho completo do arquivo
						unlink($arquivoPath);  // Apaga o arquivo
					}
				}
				$pasta->close();
			}

			foreach ($motoristas as $motorista) {
				$ocorrencias = [];
				$verificaValores = []; 

				foreach($macros as $macro){
					if (!isset($ocorrencias[$macro])) {
						$ocorrencias[$macro] = [
							'ativo' => 0,
							'inativo' => 0,
						];
					}
				}
				$totalMotorista = [
					"matricula" 				=> $motorista["enti_tx_matricula"],
					"nome" 						=> $motorista["enti_tx_nome"],
					"ocupacao" 					=> $motorista["enti_tx_ocupacao"],


					// "dataInicio"				=> $periodoInicio->format("d/m/Y"),
					// "dataFim"					=> $periodoFim->format("d/m/Y")
				];
				$diaInicio = $periodoInicio->format('Y-m-d');
				$diafim = $periodoFim->format('Y-m-d');

				$pontosAtivos = mysqli_fetch_all(
					query(
					"SELECT DISTINCT ponto.pont_tx_data,  ponto.pont_tx_matricula, motivo.moti_tx_nome, macroponto.macr_tx_nome, 
						ponto.pont_tx_status"
						." FROM ponto"
						." LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id"
						." INNER JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno"
						." AND macroponto.macr_tx_fonte = 'positron'"
						." LEFT JOIN user ON ponto.pont_nb_userCadastro = user.user_nb_id"
						." WHERE pont_tx_matricula = '$motorista[enti_tx_matricula]'"
						." AND (user.user_tx_matricula <> ponto.pont_tx_matricula OR user.user_tx_matricula IS NULL)"
						." AND pont_tx_data BETWEEN STR_TO_DATE('$diaInicio 00:00:00', '%Y-%m-%d %H:%i:%s')"
						." AND STR_TO_DATE('$diafim 23:59:59', '%Y-%m-%d %H:%i:%s')"
						." ORDER BY ponto.pont_tx_data ASC;"
					),
					MYSQLI_ASSOC
				);

				foreach ($pontosAtivos as $registro2) {
					$macr_tx_nome = $registro2['macr_tx_nome'];
					if (in_array($macr_tx_nome, $macros)) {
						// Inicializa como 0 se não existir
						if (!isset($ocorrencias[$macr_tx_nome]["ativo"])) {
							$ocorrencias[$macr_tx_nome]["ativo"] = 0;
						}
				
						// Incrementa o contador
						$ocorrencias[$macr_tx_nome]["ativo"]++;
					}
				}

				$pontosInativos = mysqli_fetch_all(
					query(
					"SELECT ponto.pont_tx_data, ponto.pont_tx_matricula, ponto.pont_tx_status, 
					ponto.pont_tx_tipo, macroponto.macr_tx_nome"
					." FROM ponto"
					." INNER JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno"
					." WHERE pont_tx_matricula = '$motorista[enti_tx_matricula]'"
					." AND pont_tx_status != 'ativo'"
					." AND pont_tx_data BETWEEN STR_TO_DATE('$diaInicio 00:00:00', '%Y-%m-%d %H:%i:%s')"
					." AND STR_TO_DATE('$diafim 23:59:59', '%Y-%m-%d %H:%i:%s')"
					." ORDER BY ponto.pont_tx_data ASC;"
					),
					MYSQLI_ASSOC
				);

				foreach ($pontosInativos as $registro) {
					$macr_tx_nome = $registro['macr_tx_nome'];
					if (in_array($macr_tx_nome, $macros)) {
						// Inicializa como 0 se não existir
						if (!isset($ocorrencias[$macr_tx_nome]["inativo"])) {
							$ocorrencias[$macr_tx_nome]["inativo"] = 0;
						}
				
						// Incrementa o contador
						$ocorrencias[$macr_tx_nome]["inativo"]++;
					}
				}

				$totalMotorista = array_merge($totalMotorista, $ocorrencias);
				$totalMotorista['pontos'] = array_merge($pontosAtivos, $pontosInativos);
				// Filtrar apenas os campos numéricos que precisam ser verificados
				$verificaValores = array_filter($totalMotorista, function ($key) {
					return !in_array($key, ["matricula", "nome", "ocupacao", "pontos"]);
				}, ARRAY_FILTER_USE_KEY);
				
				$rows[] = $ocorrencias;
			    if (array_sum(array_map(function($valor) {
					return array_sum($valor); // Soma os valores de 'ativo' e 'inativo' dentro de cada chave
				}, $verificaValores)) > 0){
					file_put_contents($path."/". $motorista["enti_tx_matricula"]. ".json", json_encode($totalMotorista, JSON_UNESCAPED_UNICODE));
				}	
			}

			foreach ($rows as $row => $eventos) {
				foreach ($eventos as $evento => $valores) {
					if (!isset($totaisEmpr[$evento])) {
						$totaisEmpr[$evento] = 0; // Inicializa a soma para a chave do evento
					}
			
					foreach ($valores as $estado => $value) {
						$totaisEmpr[$evento] += $value; // Incrementa o total para a chave do evento
					}
				}
			}

			$totais [] = $totaisEmpr;
			$empresa = array_merge($totaisEmpr, $empresa);
			
			$empresa["qtdMotoristas"] = count($motoristas);
			$empresa["dataInicio"] = $periodoInicio->format("d/m/Y");
			$empresa["dataFim"] = $periodoFim->format("d/m/Y");
			
			file_put_contents($path."/empresa_".$empresa["empr_nb_id"].".json", json_encode($empresa));
		}

		foreach ($totais as $key => $values) {
			foreach ($values as $key => $value) {
				if (!isset($totaisEmpresa[$key][$value])) {
					$totaisEmpresa[$key][$value] = 0; // Inicializa a chave com 0, se ainda não existir
				}
				$totaisEmpresa[$key][$value] += $value;
			}
		}
		

		if (empty($_POST["empresa"])) {
			$path = "./arquivos/ajustes"."/". $periodoInicio->format("Y-m");
			$totaisEmpresa["dataInicio"] = $periodoInicio->format("d/m/Y");
			$totaisEmpresa["dataFim"] = $periodoFim->format("d/m/Y");
			file_put_contents($path."/empresas.json", json_encode($totaisEmpresa));
		}

		return;
	}