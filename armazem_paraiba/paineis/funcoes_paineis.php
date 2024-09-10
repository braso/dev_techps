<?php

	//Funções comuns aos paineis{
		function calcPercs(array $values): array{
			$total = 0;
			foreach($values as $value){
				$total += $value;
			}
			
			if($total == 0){
				return array_pad([], sizeof($values), 0);
			}
			
			$percentuais = array_pad([], sizeof($values), 0);
			for($f = 0; $f < sizeof($values); $f++){
				$percentuais[$f] = $values[$f]/$total;
			}
			
			return $percentuais;
		}
	//}

	//Funções de criação de cada painel{
		function criar_relatorio_saldo(){
			global $totalResumo;
			$dataInicio = new DateTime($_POST["busca_dataInicio"]);
			$dataFim = new DateTime($_POST["busca_dataFim"]);
	
			$empresas = mysqli_fetch_all(query(
				"SELECT empr_nb_id, empr_tx_nome FROM empresa"
				." WHERE empr_tx_status = 'ativo'"
					.(!empty($_POST["empresa"])? " AND empr_nb_id = ".$_POST["empresa"]: "")
				." ORDER BY empr_tx_nome ASC;"
			),MYSQLI_ASSOC);
	
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

			foreach($empresas as $empresa){
				$path = "./arquivos/saldos"."/".$empresa["empr_nb_id"]."/".$dataInicio->format("Y-m");
				if(!file_exists($path."/empresa_".$empresa["empr_nb_id"].".json")){
					if(!is_dir($path)){
						mkdir($path, 0755, true);
					}
					file_put_contents($path."/empresa_".$empresa["empr_nb_id"].".json", "");
				}
	
				$motoristas = mysqli_fetch_all(query(
					"SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_banco FROM entidade"
					." WHERE enti_tx_status = 'ativo'"
						." AND enti_nb_empresa = ".$empresa["empr_nb_id"]
						." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante')"
					." ORDER BY enti_tx_nome ASC;"
				),MYSQLI_ASSOC);
	
				$rows = [];
				$statusEndossos = [
					"E" => 0,
					"EP" => 0,
					"N" => 0
				];
				foreach($motoristas as $motorista){
					//Status Endosso{
						$endossos = mysqli_fetch_all(query(
							"SELECT * FROM endosso"
							." WHERE endo_tx_status = 'ativo'"
								." AND endo_nb_entidade = '".$motorista["enti_nb_id"]."'"
								." AND ("
									."   (endo_tx_de  >= '".$dataInicio->format("Y-m-d")."' AND endo_tx_de  <= '".$dataFim->format("Y-m-d")."')"
									."OR (endo_tx_ate >= '".$dataInicio->format("Y-m-d")."' AND endo_tx_ate <= '".$dataFim->format("Y-m-d")."')"
									."OR (endo_tx_de  <= '".$dataInicio->format("Y-m-d")."' AND endo_tx_ate >= '".$dataFim->format("Y-m-d")."')"
								.");"
						), MYSQLI_ASSOC);
						
						$statusEndosso = "N";
						if(count($endossos) >= 1){
							$statusEndosso = "E";
							if(strtotime($dataInicio->format("Y-m-d")) < strtotime($endossos[0]["endo_tx_de"]) || strtotime($dataFim->format("Y-m-d")) > strtotime($endossos[count($endossos)-1]["endo_tx_ate"])){
								$statusEndosso .= "P";
							}
						}
						$statusEndossos[$statusEndosso]++;
					//}
	
					//saldoAnterior{
						$saldoAnterior = mysqli_fetch_assoc(query(
							"SELECT endo_tx_saldo FROM endosso"
							." WHERE endo_tx_status = 'ativo'"
								." AND endo_tx_ate < '".$dataInicio->format("Y-m-d")."'"
								." AND endo_tx_matricula = '".$motorista["enti_tx_matricula"]."'"
							." ORDER BY endo_tx_ate DESC"
							." LIMIT 1;"
						));
						
						if(!empty($saldoAnterior)){
							if(!empty($saldoAnterior["endo_tx_saldo"])){
								$saldoAnterior = $saldoAnterior["endo_tx_saldo"];
							}elseif(!empty($motorista["enti_tx_banco"])){
								$saldoAnterior = $motorista["enti_tx_banco"];
							}
							if(strlen($motorista["enti_tx_banco"]) > 5 && $motorista["enti_tx_banco"][0] == "0"){
								$saldoAnterior = substr($saldoAnterior, 1);
							}
						}else{
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
	
					for($dia = new DateTime($dataInicio->format("Y-m-d")); $dia <= $dataFim; $dia->modify("+1 day")){
						$diaPonto = diaDetalhePonto($motorista["enti_tx_matricula"], $dia->format("Y-m-d"));
						//Formatando informações{
							foreach(array_keys($diaPonto) as $f){
								if(in_array($f, ["data", "diaSemana"])){
									continue;
								}
								if(strlen($diaPonto[$f]) > 5){
									$diaPonto[$f] = preg_replace("/.*&nbsp;/", "", $diaPonto[$f]);
									if(preg_match_all("/(-?\d{2,4}:\d{2})/", $diaPonto[$f], $matches)){
										$diaPonto[$f] = array_pop($matches[1]);
									}else{
										$diaPonto[$f] = "";
									}
								}
							}
						//}
						
						
						$diaPonto["he50"]              = !empty($diaPonto["he50"])? $diaPonto["he50"]: "00:00";
						$diaPonto["he100"]             = !empty($diaPonto["he100"])? $diaPonto["he100"]: "00:00";

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
	
				foreach($rows as $row){
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
					if(empty($_POST["empresa"])){
						foreach($totaisEmpr as $key => $value){
							$totaisEmpresas[$key] = operarHorarios([$totaisEmpresas[$key], $value], "+");
						}
						$totaisEmpresas["qtdMotoristas"] += count($motoristas);
					}
				//}
	
				$empresa["totais"] = $totaisEmpr;
				$empresa["qtdMotoristas"] = count($motoristas);
				$empresa["dataInicio"] = $dataInicio->format("Y-m-d");
				$empresa["dataFim"] = $dataFim->format("Y-m-d");
				$empresa["percEndossado"] = ($statusEndossos["E"])/array_sum(array_values($statusEndossos));
	
				file_put_contents($path."/empresa_".$empresa["empr_nb_id"].".json", json_encode($empresa));
			}
	
			if(empty($_POST["empresa"])){
				$path = "./arquivos/saldos";
				if(!is_dir($path)){
					mkdir($path,0755,true);
				}
				$totaisEmpresas["dataInicio"] = $dataInicio->format("Y-m-d");
				$totaisEmpresas["dataFim"] = $dataFim->format("Y-m-d");
				file_put_contents($path."/empresas.json", json_encode($totaisEmpresas));
			}
			return;
		}

		function criar_relatorio_endosso(){
			$mes = new DateTime($_POST["busca_data"]."-01");
			$fimMes = new DateTime($mes->format("Y-m-t"));
	
			$empresas = mysqli_fetch_all(query(
				"SELECT empr_nb_id, empr_tx_nome FROM empresa"
				." WHERE empr_tx_status = 'ativo'"
					.(!empty($_POST["empresa"])? " AND empr_nb_id = ".$_POST["empresa"]: "")
				." ORDER BY empr_tx_nome ASC;"
			),MYSQLI_ASSOC);
	
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
	
			foreach($empresas as $empresa){
				$path = "./arquivos/endossos"."/".$empresa["empr_nb_id"]."/".$mes->format("Y-m");
				if(!file_exists($path."/empresa_".$empresa["empr_nb_id"].".json")){
					if(!is_dir($path)){
						mkdir($path, 0755, true);
					}
					file_put_contents($path."/empresa_".$empresa["empr_nb_id"].".json", "");
				}
	
				$motoristas = mysqli_fetch_all(query(
					"SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_banco FROM entidade"
					." WHERE enti_tx_status = 'ativo'"
						." AND enti_nb_empresa = ".$empresa["empr_nb_id"]
						." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante')"
					." ORDER BY enti_tx_nome ASC;"
				),MYSQLI_ASSOC);
	
				$rows = [];
				$statusEndossos = [
					"E" => 0,
					"EP" => 0,
					"N" => 0
				];
				foreach($motoristas as $motorista){
					//Status Endosso{
						$endossos = mysqli_fetch_all(query(
							"SELECT * FROM endosso"
							." WHERE endo_tx_status = 'ativo'"
								." AND endo_nb_entidade = '".$motorista["enti_nb_id"]."'"
								." AND ("
									."   (endo_tx_de  >= '".$mes->format("Y-m-01")."' AND endo_tx_de  <= '".$mes->format("Y-m-t")."')"
									."OR (endo_tx_ate >= '".$mes->format("Y-m-01")."' AND endo_tx_ate <= '".$mes->format("Y-m-t")."')"
									."OR (endo_tx_de  <= '".$mes->format("Y-m-01")."' AND endo_tx_ate >= '".$mes->format("Y-m-t")."')"
								.")"
							." ORDER BY endo_tx_ate;"
						), MYSQLI_ASSOC);
						
						$statusEndosso = "N";
						if(count($endossos) >= 1){
							$statusEndosso = "E";
							if(strtotime($mes->format("Y-m-01")) != strtotime($endossos[0]["endo_tx_de"]) || strtotime($mes->format("Y-m-t")) > strtotime($endossos[count($endossos)-1]["endo_tx_ate"])){
								$statusEndosso .= "P";
							}
						}
						$statusEndossos[$statusEndosso]++;
					//}
	
					//saldoAnterior{
						$saldoAnterior = mysqli_fetch_assoc(query(
							"SELECT endo_tx_saldo FROM endosso"
							." WHERE endo_tx_status = 'ativo'"
								." AND endo_tx_ate < '".$mes->format("Y-m-01")."'"
								." AND endo_tx_matricula = '".$motorista["enti_tx_matricula"]."'"
							." ORDER BY endo_tx_ate DESC"
							." LIMIT 1;"
						));
						
						if(!empty($saldoAnterior)){
							if(!empty($saldoAnterior["endo_tx_saldo"])){
								$saldoAnterior = $saldoAnterior["endo_tx_saldo"];
							}elseif(!empty($motorista["enti_tx_banco"])){
								$saldoAnterior = $motorista["enti_tx_banco"];
							}
							if(strlen($motorista["enti_tx_banco"]) > 5 && $motorista["enti_tx_banco"][0] == "0"){
								$saldoAnterior = substr($saldoAnterior, 1);
							}
						}else{
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
					if($statusEndosso != "N"){
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
	
						foreach($endossos as $endosso){
							$endosso = lerEndossoCSV($endosso["endo_tx_filename"]);
							if(empty($endosso["totalResumo"]["he50APagar"])){
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
							if(empty($totaisMot["saldoAnterior"])){
								$totaisMot["saldoAnterior"] = $endosso["totalResumo"]["saldoAnterior"];
							}
							$totaisMot["saldoPeriodo"] 		= operarHorarios([$totaisMot["saldoPeriodo"], $endosso["totalResumo"]["diffSaldo"]], "+");
							$totaisMot["saldoPeriodo"] 		= operarHorarios([$totaisMot["saldoPeriodo"], $endosso["totalResumo"]["diffSaldo"]], "+");
	
							if(empty($endosso["totalResumo"]["saldoBruto"]) && !empty($endosso["totalResumo"]["saldoAtual"])){
								$totaisMot["saldoFinal"] = operarHorarios([$endosso["totalResumo"]["saldoAtual"], $endosso["totalResumo"]["he100"]], "+");
							}else{
								$totaisMot["saldoFinal"] = operarHorarios([$endosso["totalResumo"]["saldoAnterior"], $endosso["totalResumo"]["saldoBruto"]], "+");
								$totaisMot["saldoFinal"] = operarHorarios([$totaisMot["saldoFinal"], $endosso["totalResumo"]["he50APagar"], $endosso["totalResumo"]["he100APagar"]], "-");
							}
						}
					}
	
					$row = [
						"idMotorista" => $motorista["enti_nb_id"],
						"matricula" => $motorista["enti_tx_matricula"],
						"nome" => $motorista["enti_tx_nome"],
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
	
				foreach($rows as $row){
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
					if(empty($_POST["empresa"])){
						foreach($totaisEmpr as $key => $value){
							$totaisEmpresas[$key] = operarHorarios([$totaisEmpresas[$key], $value], "+");
						}
						$totaisEmpresas["qtdMotoristas"] += count($motoristas);
					}
				//}
	
				$empresa["totais"] = $totaisEmpr;
				$empresa["qtdMotoristas"] = count($motoristas);
				$empresa["dataInicio"] = $mes->format("Y-m-01");
				$empresa["dataFim"] = $mes->format("Y-m-t");
				$empresa["percEndossado"] = ($statusEndossos["E"])/array_sum(array_values($statusEndossos));
	
				file_put_contents($path."/empresa_".$empresa["empr_nb_id"].".json", json_encode($empresa));
			}
	
			if(empty($_POST["empresa"])){
				$path = "./arquivos/endossos"."/".$dataInicio->format("Y-m");
				if(!is_dir($path)){
					mkdir($path,0755,true);
				}
				$totaisEmpresas["dataInicio"] = $mes->format("Y-m-01");
				$totaisEmpresas["dataFim"] = $mes->format("Y-m-t");
				file_put_contents($path."/empresas.json", json_encode($totaisEmpresas));
			}
			return;
		}
	//}
		
