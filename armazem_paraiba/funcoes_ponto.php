<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
		
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
        header("Pragma: no-cache"); // HTTP 1.0.
        header("Expires: 0");
	//*/
	include_once __DIR__."/conecta.php";

	function calcJorPre(string $data, string $jornadaSemanal, string $jornadaSabado, bool $ehFeriado, $abono = null): array{

		if(date("w", strtotime($data)) == "0" || $ehFeriado){ 	//DOMINGOS OU FERIADOS
			$jornadaPrevista = "00:00";
		}elseif(date("w", strtotime($data)) == "6"){ 			//SABADOS
			$jornadaPrevista = $jornadaSabado;
		}else{													//DIAS DE SEMANA
			$jornadaPrevista = $jornadaSemanal;
		}

		$jornadaPrevistaOriginal = $jornadaPrevista;
		$jornadaPrevista = (new DateTime("{$data} {$jornadaPrevista}"))->format("H:i");
		if(!empty($abono)){
			$jornadaPrevista = (new DateTime("{$data} {$abono}"))->diff(new DateTime("{$data} {$jornadaPrevista}"))->format("%H:%I");
		}

		return [$jornadaPrevistaOriginal, $jornadaPrevista];
	}

	function calcularAbono($saldo, $tempoAbono){
		
		if($saldo[0] == "-"){
			return ((substr($saldo, 1) >= $tempoAbono)? $tempoAbono: substr($saldo, 1));
		}else{
			return "00:00";
		}
	}

	function calcularAdicNot(array $registros): string{

		$chavesInvalidas = array_filter(array_keys($registros), function($key){
			return !(preg_match("/^inicio|fim/", $key));
		});
		foreach($chavesInvalidas as $key){
			unset($registros[$key]);
		}
		if(!isset($registros["inicioJornada"]) || empty($registros["inicioJornada"])){
			return "00:00";
		}


		//Ordenando os horários{
			$horarios = [];
			$valsEstaTrabalhando = [];
			foreach($registros as $tipo => $values){
				if($values != [] && $tipo != "jornadaCompleto"){
					$horarios = array_merge($horarios, $values);
					$estaTrabalhando = ($tipo == "inicioJornada" || (is_int(strpos($tipo, "fim")) && $tipo != "fimJornada"));
					$valsEstaTrabalhando = array_pad($valsEstaTrabalhando, count($valsEstaTrabalhando)+count($values), $estaTrabalhando);
				}
			}
			array_multisort($horarios, SORT_ASC, $valsEstaTrabalhando);
		//}
		$adicNot = "00:00";
		$pares_horarios["paresAdicionalNot"] = [];

		$primReg = new DateTime($horarios[0]);

		$periodosAdicNot = [
			"inicios" => [
				new DateTime($primReg->format("Y-m-d 00:00")),
				new DateTime($primReg->format("Y-m-d 22:00")),
			],
			"fins" => [
				new DateTime($primReg->format("Y-m-d 05:00")),
				date_add(
					new DateTime($primReg->format("Y-m-d 05:00")),
					DateInterval::createFromDateString("+1 day"))
				]
		];

		$hInicio = null;
		
		$avancarDias = (function(DateTime &$dataInicio, DateTime $dataPara, array &$periodosAdicNot): int{
			$qtdDias = date_diff($dataInicio, $dataPara)->days;
			foreach([$dataInicio, $periodosAdicNot["inicios"][0], $periodosAdicNot["inicios"][1], $periodosAdicNot["fins"][0], $periodosAdicNot["fins"][1]] as &$data){
				$data->add(DateInterval::createFromDateString(($qtdDias)." days"));
			}

			if($dataInicio->format("Y-m-d H:i") > $dataPara->format("Y-m-d H:i")){
				$dataInicio = $dataInicio->sub(DateInterval::createFromDateString(($qtdDias)." days"));
			}
			return $qtdDias;
		});

		for($f = 0; $f < count($horarios); $f++){
			$horario = new DateTime($horarios[$f]);
			if($valsEstaTrabalhando[$f]){
				$hInicio = $horario;
				if($hInicio->format("Y-m-d") != $periodosAdicNot["inicios"][0]->format("Y-m-d")){
					$avancarDias($hInicio, $periodosAdicNot["inicios"][0], $periodosAdicNot);
				}
			}elseif(!$valsEstaTrabalhando[$f] && !empty($hInicio)){
				$hFim = $horario;
				$valAtual = new DateInterval("P0D");
				
				if($hInicio >= $periodosAdicNot["inicios"][0] && $hInicio < $periodosAdicNot["fins"][0]){
					if($hFim <= $periodosAdicNot["fins"][0]){
						//$hFim - $hInicio
						$valAtual = date_diff($hFim, $hInicio);
					}elseif($hFim < $periodosAdicNot["inicios"][1]){
						//$periodosAdicNot["fins"][0]  - $hInicio
						$valAtual = date_diff($periodosAdicNot["fins"][0], $hInicio);
					}elseif($hFim <= $periodosAdicNot["fins"][1]){
						//($hFim - $periodosAdicNot["inicios"][1]) + ($periodosAdicNot["fins"][0] - $hInicio)
						$a = date_diff($hFim, $periodosAdicNot["inicios"][1]);
						$b = date_diff($periodosAdicNot["fins"][0], $hInicio);
						$valAtual->d = ($a->d)+($b->d);
						$valAtual->h = ($a->h)+($b->h);
						$valAtual->i = ($a->i)+($b->i);
					}else{												//$hFim > $periodosAdicNot["fins"][1]
						//($hFim - $hInicio >= "24:00", logo, considerar a quantidade de períodos noturnos entre início e fim)
						$qtdDias = $avancarDias($hInicio, $hFim, $periodosAdicNot);
						$f--;
						continue;
					}
				}elseif($hInicio >= $periodosAdicNot["fins"][0] && $hInicio < $periodosAdicNot["inicios"][1]){
					if($hFim < $periodosAdicNot["inicios"][1]){
						//"00:00"
						$valAtual = new DateInterval("P0D");
					}elseif($hFim <= $periodosAdicNot["fins"][1]){
						//$hFim - $periodosAdicNot["inicio"][1]
						$valAtual = date_diff($hFim, $periodosAdicNot["inicios"][1]);
					}else{
						//($hFim - $hInicio >= "24:00", logo, considerar a quantidade de períodos noturnos entre início e fim)
						if(date_diff($hInicio, $hFim)->d > 0){
							$qtdDias = $avancarDias($hInicio, $hFim, $periodosAdicNot);
							$f--;
							continue;
						}else{
							$valAtual = new DateInterval("PT7H");
						}
					}
				}elseif($hInicio >= $periodosAdicNot["inicios"][1] && $hInicio < $periodosAdicNot["fins"][1]){
					if($hFim <= $periodosAdicNot["fins"][1]){
						//$hFim - $hInicio
						$valAtual = date_diff($hFim, $hInicio);
					}else{
						if(date_diff($hInicio, $hFim)->d > 0){
							//($hFim - $hInicio >= "24:00", logo, considerar a quantidade de períodos noturnos entre início e fim)
							$qtdDias = $avancarDias($hInicio, $hFim, $periodosAdicNot);
							$f--;
							continue;
						}
					}
				}else{
					if($hInicio->format("Y-m-d") == $periodosAdicNot["inicios"][0]->format("Y-m-d")){
						$hInicio->sub(DateInterval::createFromDateString(((date_diff($periodosAdicNot["inicios"][0], $hInicio))->d-1)." day"));
						$qtdDias = $avancarDias($hInicio, $hFim, $periodosAdicNot);
						$f--;
						continue;
					}
				}

				
				
				$valAtual = formatToTime(($valAtual->d+($qtdDias?? 0))*7+$valAtual->h, $valAtual->i, ceil($valAtual->s/60)*60);
				$adicNot = operarHorarios([$adicNot, $valAtual], "+");
				$paresAdicionalNot[] = ["inicio" => $hInicio, "fim" => $hFim, "intervalo" => $valAtual];
				
				unset($hInicio);
				unset($hFim);
				unset($qtdDias);
			}	
		}


		return $adicNot;
	}

	//@return [he50, he100]
	function calcularHorasAPagar(string $saldoBruto, string $he50, string $he100, string $max50APagar, string $pagarHEExComPerNeg = "nao"): array{
		$params = [$saldoBruto, $he50, $he100, $max50APagar];

		foreach($params as $param){
			if(!preg_match("/^-?\d{2,10}:\d{2}$/", $param)){
				throw new Exception("Format error (calcularHorasAPagar): ".$param);
			}
		}

		if($saldoBruto[0] == "-"){
			if($pagarHEExComPerNeg == "sim"){
				return ["00:00", $he100];
			}

			return ["00:00", "00:00"];
		}
		if(operarHorarios([$he100, $saldoBruto], "-")[0] != "-"){
			return ["00:00", $saldoBruto];
		}
		
		$excedente = operarHorarios([$saldoBruto, $he100], "-");
		if(operarHorarios([$max50APagar, $excedente], "-")[0] != "-"){
			return [$excedente, $he100];
		}

		return [$max50APagar, $he100];
	}

	function criar_relatorio($anoMes){

        [$ano, $mes] = explode("-", $anoMes);

        // Cria a data de início do mês especificado
        $periodoInicio = date($anoMes."-01 00:00:00");
        $periodoFim = date("Y-m-t", mktime(0, 0, 0, $mes, 1, $ano));
        $mes = new DateTime($periodoInicio);
        $fimMes = new DateTime($periodoFim);
		
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

	function conferirErroPonto(string $matricula, DateTime $dataPonto, int $idMacro, int $motivo = null, string $justificativa = null): array{
		//Conferir se tem as informações necessárias{
			if(empty($matricula)){
				$_POST["errorFields"][] = "motorista";
				throw new Exception("Funcionário não encontrado.");
			}

			$macro = mysqli_fetch_assoc(query(
				"SELECT macr_tx_codigoInterno, macr_tx_codigoExterno, macr_tx_nome FROM macroponto
					WHERE macr_tx_status = 'ativo'
						AND macr_nb_id = {$idMacro}
					LIMIT 1;"
			));
			if(empty($macro)){
				$_POST["errorFields"][] = "idMacro";
				throw new Exception("Macro não encontrada.");
			}
		//}

		$newPonto = [
			"pont_nb_motivo" 		=> $motivo,
			"pont_nb_userCadastro"	=> $_SESSION["user_nb_id"],

			"pont_tx_data" 			=> $dataPonto->format("Y-m-d H:i:s"),
			"pont_tx_dataCadastro" 	=> date("Y-m-d H:i:s"),
			"pont_tx_justificativa" => $justificativa,
			"pont_tx_matricula" 	=> $matricula,
			"pont_tx_status" 		=> "ativo",
			"pont_tx_tipo" 			=> $macro["macr_tx_codigoInterno"],
			"pont_tx_tipoOriginal" 	=> $macro["macr_tx_codigoExterno"]
		];

		$codigosJornada = ["inicio" => 1, "fim" => 2];

		$ultimoPonto = mysqli_fetch_assoc(query(
			"SELECT * FROM ponto
				WHERE pont_tx_status = 'ativo'
					AND pont_tx_matricula = '{$matricula}'
					AND pont_tx_data <= STR_TO_DATE('{$dataPonto->format("Y-m-d H:i:59")}', '%Y-%m-%d %H:%i:%s')
				ORDER BY pont_tx_data DESC, pont_nb_id DESC
				LIMIT 1;"
		));

		//Confere se já tem um ponto no mesmo minuto, e adiciona aos segundos como índice de ordenação{
			if(
				!empty($ultimoPonto["pont_tx_data"]) && 
				substr($ultimoPonto["pont_tx_data"], 0, -2) == substr(strval($newPonto["pont_tx_data"]), 0, -2)
			){
				$indiceSeg = intval(substr($ultimoPonto["pont_tx_data"], -2))+1;
				$newPonto["pont_tx_data"] = substr($newPonto["pont_tx_data"], 0, -2).sprintf("%02d", $indiceSeg);
			}
		//}

		$ultPontoJornada = null;
		if(!empty($ultimoPonto["pont_tx_tipo"])){
			if(in_array(intval($ultimoPonto["pont_tx_tipo"]), $codigosJornada)){
				$ultPontoJornada = $ultimoPonto;
			}else{
				$ultPontoJornada = mysqli_fetch_assoc(query(
					"SELECT pont_tx_tipo FROM ponto 
						WHERE pont_tx_tipo IN ('{$codigosJornada["inicio"]}', '{$codigosJornada["fim"]}')
							AND pont_tx_status = 'ativo'
							AND pont_tx_matricula = '{$matricula}'
							AND pont_tx_data <= STR_TO_DATE('{$dataPonto->format("Y-m-d H:i:59")}', '%Y-%m-%d %H:%i:%s')
						ORDER BY pont_tx_data DESC
						LIMIT 1;"
				));
			}
		}


		if(empty($ultPontoJornada) || (!empty($ultimoPonto) && $ultimoPonto["pont_tx_tipo"] == $codigosJornada["fim"])){
			if($newPonto["pont_tx_tipo"] != $codigosJornada["inicio"]){
				throw new Exception("Jornada aberta não encontrada.");
			}
		}else{
			if($ultimoPonto["pont_tx_tipo"] == $codigosJornada["inicio"]){
				if($newPonto["pont_tx_tipo"] == $codigosJornada["inicio"]){
					throw new Exception("Jornada aberta já existente.");
				}
				if($newPonto["pont_tx_tipo"]%2 == 0 && ($newPonto["pont_tx_tipo"] != $codigosJornada["fim"])){
					throw new Exception("Intervalo aberto não encontrado.");
				}
			}elseif($ultimoPonto["pont_tx_tipo"]%2 == 1){
				if($newPonto["pont_tx_tipo"] == $codigosJornada["fim"]){
					throw new Exception("Não é possível fechar com um intervalo aberto.");
				}
				if($newPonto["pont_tx_tipo"]%2 == 1){
					throw new Exception("Não é possível abrir com outro intervalo aberto.");
				}
				if($newPonto["pont_tx_tipo"] != intval($ultimoPonto["pont_tx_tipo"])+1){
					throw new Exception("Não é possível fechar com outro intervalo aberto.");
				}
			}elseif($newPonto["pont_tx_tipo"]%2 == 0 && $newPonto["pont_tx_tipo"] != $codigosJornada["fim"]){
				throw new Exception("Intervalo aberto não encontrado.");
			}
		}

		return $newPonto;
	}

	function dateTimeToSecs(DateTime $dateTime, DateTime $baseDate = null): int{
		if(empty($baseDate)){
			$baseDate = DateTime::createFromFormat("Y-m-d H:i:s", "1970-01-01 00:00:00");
		}
    	$res = date_diff($dateTime, $baseDate);
        $res = 
        	($res->invert? 1:-1)*
			(
				$res->days*24*60*60+
				$res->h*60*60+
				$res->i*60+
				$res->s
			);
        return $res;
    }

	function diaDetalhePonto(array $motorista, string $data): array{
		global $totalResumo;
		setlocale(LC_ALL, "pt_BR.utf8");

		$aRetorno = [
			"data" 					=> data($data),
			"diaSemana" 			=> strtoupper(substr(str_replace("á", "a", pegarDiaSemana($data)), 0, 3)),
			"inicioJornada" 		=> [],
			"inicioRefeicao" 		=> [],
			"fimRefeicao" 			=> [],
			"fimJornada" 			=> [],
			"diffRefeicao" 			=> "00:00",
			"diffEspera" 			=> "00:00",
			"diffDescanso" 			=> "00:00",
			"diffRepouso" 			=> "00:00",
			"diffJornada" 			=> "00:00",
			"jornadaPrevista" 		=> "00:00",
			"diffJornadaEfetiva" 	=> "00:00",
			"maximoDirecaoContinua" => "00:00",
			"intersticio" 			=> "00:00",
			"he50" 					=> "00:00",
			"he100" 				=> "00:00",
			"adicionalNoturno" 		=> "00:00",
			"esperaIndenizada" 		=> "00:00",
			"diffSaldo" 			=> "00:00"
		];

		if(empty($motorista["enti_nb_parametro"])){
			$motorista["enti_nb_parametro"] = $motorista["empr_nb_parametro"];
			$parametroEmpresa = mysqli_fetch_assoc(query(
				"SELECT * FROM parametro
					WHERE para_nb_id = {$motorista["empr_nb_parametro"]}
					LIMIT 1;"
			));
			$motorista = array_merge($motorista, $parametroEmpresa);
			$motorista["enti_tx_jornadaSabado"] = $motorista["para_tx_jornadaSabado"];
			$motorista["enti_tx_jornadaSemanal"] = $motorista["para_tx_jornadaSemanal"];
			$motorista["enti_tx_percHESemanal"] = $motorista["para_tx_percHESemanal"];
			$motorista["enti_tx_percHEEx"] = $motorista["para_tx_percHEEx"];
		}
		
		
		//Organizar array com tipos de ponto{
			$registros = [
				null,
				"inicioJornada" => [],
				"fimJornada" => [],
				"inicioRefeicao" => [],
				"fimRefeicao" => [],
				"inicioEspera" => [],
				"fimEspera" => [],
				"inicioDescanso" => [],
				"fimDescanso" => [],
				"inicioRepouso" => [],
				"fimRepouso" => [],
				"inicioRepousoEmb" => [],
				"fimRepousoEmb" => []
			];
			$tipos = array_keys($registros);
		//}

		query(
			"SET @dataInicioBusca = (
				SELECT pont_tx_data FROM ponto
					WHERE pont_tx_status = 'ativo'
						AND pont_tx_matricula = '{$motorista["enti_tx_matricula"]}'
						AND pont_tx_data BETWEEN '{$data} 00:00:00' AND '{$data} 23:59:59'
						AND pont_tx_tipo = '1'
					ORDER BY pont_tx_data ASC
					LIMIT 1
			);"
		);

		$pontosDia = mysqli_fetch_all(query(
			"SELECT * FROM ponto
				WHERE pont_tx_status = 'ativo'
					AND pont_tx_matricula = '{$motorista["enti_tx_matricula"]}'
					AND pont_tx_data < '{$data} 23:59:59' 
					AND IF(@dataInicioBusca IS NOT NULL, pont_tx_data >= @dataInicioBusca, 0)
				ORDER BY pont_tx_data ASC;"
		), MYSQLI_ASSOC);

		//JORNADA PREVISTA{
			//Consultar feriados do dia{
				$stringFeriado = getFeriados($motorista, $data);
				if(!empty($stringFeriado)){
					$iconeFeriado = " <a><i style='color:green;' title='{$stringFeriado}' class='fa fa-info-circle'></i></a>";
					$aRetorno["diaSemana"] .= $iconeFeriado;
				}
			//}

			$abonos = mysqli_fetch_assoc(query(
				"SELECT * FROM abono, motivo, user
					WHERE abon_tx_status = 'ativo'
						AND abon_nb_userCadastro = user_nb_id
						AND abon_tx_matricula = '{$motorista["enti_tx_matricula"]}'
						AND abon_tx_data = '{$data}'
						AND abon_nb_motivo = moti_nb_id
					ORDER BY abon_nb_id DESC
					LIMIT 1;"
			));

			[$jornadaPrevistaOriginal, $jornadaPrevista] = calcJorPre($data, $motorista["enti_tx_jornadaSemanal"], $motorista["enti_tx_jornadaSabado"], !empty($stringFeriado), ($abonos["abon_tx_abono"]?? null));
			$aRetorno["jornadaPrevista"] = $jornadaPrevista;
			if(!empty($abonos)){
				$warning = 
					"<a><i style='color:green;' title="
							."'Jornada Original: ".$jornadaPrevistaOriginal."\n"
							."Abono: {$abonos["abon_tx_abono"]}\n"
							."Motivo: {$abonos["moti_tx_nome"]}\n"
							."Justificativa: {$abonos["abon_tx_descricao"]}\n\n"
							."Registro efetuado por {$abonos["user_tx_login"]} em ".data($abonos["abon_tx_dataCadastro"], 1)."'"
						." class='fa fa-info-circle'></i>"
					."</a>&nbsp;"
				;
				$aRetorno["jornadaPrevista"] = $warning.$aRetorno["jornadaPrevista"];
			}

		//}

		//CASO NÃO HAJA PONTOS{
			if(count($pontosDia) == 0){
				$aRetorno["diffSaldo"] = getSaldoDiario($jornadaPrevista, "00:00");
				if((preg_replace("/([^\-^0-:])+/", "", strip_tags($aRetorno["jornadaPrevista"]))) != "00:00" && strpos($aRetorno["jornadaPrevista"], "Abono:") == false){
					$aRetorno["inicioJornada"][] = "<a><i style='color:red;' title='Batida início de jornada não registrada!' class='fa fa-warning'></i></a>";
				}

				//Converter array em string{
					$legendas = mysqli_fetch_all(query(
						"SELECT DISTINCT moti_tx_legenda FROM motivo 
							WHERE moti_tx_legenda IS NOT NULL;"
						), 
						MYSQLI_ASSOC
					);
		
					foreach(["inicioJornada", "fimJornada", "inicioRefeicao", "fimRefeicao"] as $tipo){
						$aRetorno[$tipo] = implode("", $aRetorno[$tipo]);
					}
				//}

				//SOMANDO TOTAIS{
					foreach(array_slice(array_keys($aRetorno), 6) as $campo){
						$totalResumo[$campo] = operarHorarios([((empty($totalResumo[$campo]))? "00:00": $totalResumo[$campo]), strip_tags(urldecode($aRetorno[$campo]))], "+");
					}
				//}

				return $aRetorno;
			}
		//}

		if(count($pontosDia) > 0 && $pontosDia[count($pontosDia)-1]["pont_tx_tipo"] != "2"){//Se o último registro do dia != fim de jornada, significa que há uma jornada aberta que seguiu para os dias seguintes
			query(
				"SET @dataProxFim = (
					SELECT pont_tx_data FROM ponto
						JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
						WHERE pont_tx_status = 'ativo'
							AND pont_tx_matricula = '{$motorista["enti_tx_matricula"]}'
							AND pont_tx_data > '{$data} 23:59:59'
							AND pont_tx_tipo IN ('1', '2')
						ORDER BY pont_tx_data ASC
						LIMIT 1
				);"
			);
			$pontosDiaSeguinte = mysqli_fetch_all(query(
				"SELECT macroponto.macr_tx_nome, ponto.* FROM ponto
					JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
					WHERE pont_tx_status = 'ativo'
						AND pont_tx_matricula = '{$motorista["enti_tx_matricula"]}'
						AND pont_tx_data > '{$data} 23:59:59'
						AND IF(@dataProxFim IS NOT NULL, pont_tx_data <= @dataProxFim, 0)
					ORDER BY pont_tx_data ASC;"
			), MYSQLI_ASSOC);
			foreach($pontosDiaSeguinte as $pontoDiaSeguinte){
				//Não pega os pontos do dia seguinte caso tenha um início de jornada sem ter fechado o anterior. Isso impede dos mesmos pontos ficarem repetidos em dois dias distintos.
				if($pontoDiaSeguinte["pont_tx_tipo"] == "1"){
					$pontosDiaSeguinte = [];
					break;
				}
			}
			$pontosDia = array_merge($pontosDia, $pontosDiaSeguinte);
		}
		foreach($pontosDia as $ponto){
			$registros[$tipos[$ponto["pont_tx_tipo"]]][] = $ponto["pont_tx_data"];
		}

		
		$registros["jornadaCompleto"] = organizarIntervalos($data, $registros["inicioJornada"], $registros["fimJornada"]);

		if(!empty($registros["inicioJornada"][0])){
			$diffJornada = date_diff(
				new DateTime(substr($registros["inicioJornada"][0], 0, strpos($registros["inicioJornada"][0], " "))." 00:00:00"), 
				$registros["jornadaCompleto"]["totalIntervalo"]
			);
			$diffJornada = formatToTime($diffJornada->days*24+$diffJornada->h, $diffJornada->i);

		}else{
			$diffJornada = "00:00";
		}
		$aRetorno["diffJornada"] = $registros["jornadaCompleto"]["icone"].$diffJornada;

		//IGNORAR CAMPOS{
			foreach(["refeicao", "espera", "descanso", "repouso"] as $campoIgnorado){
				if(is_bool(strpos($motorista["para_tx_ignorarCampos"], $campoIgnorado))){
					$registros[$campoIgnorado."Completo"] = organizarIntervalos($data, $registros["inicio".ucfirst($campoIgnorado)], $registros["fim".ucfirst($campoIgnorado)]);
				}else{
					$registros[$campoIgnorado."Completo"] = organizarIntervalos($data, [], []);
				}
			}
		//}
		
		//REPOUSO POR ESPERA{
			$repousosPorEspera = [
				"pares" => [],
				"totalIntervalo" => new DateTime("{$data} 00:00:00"),
				"icone" => ""
			];

			//Passar esperas > 02:00 para repouso{
				foreach($registros["esperaCompleto"]["pares"] as $key => $parEspera){
					if(operarHorarios([$parEspera["intervalo"], "02:01"], "-")[0] != "-"){//Se $intervalo - 02:01 der um valor positivo, significa que $intervalo > 02:00
						$repousosPorEspera["pares"][] = $parEspera;
						unset($registros["esperaCompleto"]["pares"][$key]);
						$modifyParam = explode(":", $parEspera["intervalo"]);
						$registros["esperaCompleto"]["totalIntervalo"]->modify("-{$modifyParam[0]} hours -{$modifyParam[1]} minutes");
						$registros["repousoCompleto"]["totalIntervalo"]->modify("+{$modifyParam[0]} hours +{$modifyParam[1]} minutes");
					}
				}
			//}
			
			if(!empty($repousosPorEspera["pares"])){
				$totalIntervalo = "00:00";
				foreach($repousosPorEspera["pares"] as $par){
					$totalIntervalo = operarHorarios([$totalIntervalo, $par["intervalo"]], "+");
				}
				$modifyParam = explode(":", $totalIntervalo);
				$repousosPorEspera["totalIntervalo"] = $repousosPorEspera["totalIntervalo"]->modify("{$modifyParam[0]} hours {$modifyParam[1]} minutes");
				$repousosPorEspera["icone"] = montarIconeIntervalo($repousosPorEspera["pares"], "fa fa-info-circle", "#00ff00");
				$registros["repousoPorEspera"] = $repousosPorEspera;
				
				//Adicionar os pares em $registros["repousoCompleto"]["pares"]
				$registros["repousoCompleto"]["pares"] = array_merge($registros["repousoCompleto"]["pares"], $repousosPorEspera["pares"]);
				
				$inicios = [];
				foreach($registros["repousoCompleto"]["pares"] as $par){
					$inicios[] = $par["inicio"];
				}
				//Ordenar os pares
				array_multisort($inicios, SORT_ASC, $registros["repousoCompleto"]["pares"]);

				//Adicionar ícone de repouso por espera no início de $registros["repousoCompleto"]["icone"]
				$registros["repousoCompleto"]["icone"] = $repousosPorEspera["icone"].$registros["repousoCompleto"]["icone"];
				
			}else{
				$registros["repousoPorEspera"] = organizarIntervalos($data, [], []);
			}
		//}
		
		//Converter os totalIntervalo de DateTime para string{
			foreach(["refeicaoCompleto", "descansoCompleto", "repousoCompleto", "esperaCompleto", "repousoPorEspera"] as $campo){
				$temp = date_diff(new DateTime("{$data} 00:00:00"), $registros[$campo]["totalIntervalo"]);
				$temp = formatToTime($temp->days*24+$temp->h, $temp->i);
				$registros[$campo]["totalIntervalo"] = $temp;
			}
		//}

		$aRetorno["diffRefeicao"] = $registros["refeicaoCompleto"]["icone"].$registros["refeicaoCompleto"]["totalIntervalo"];
		$aRetorno["diffEspera"]   = $registros["esperaCompleto"]["icone"].$registros["esperaCompleto"]["totalIntervalo"];
		$aRetorno["diffDescanso"] = $registros["descansoCompleto"]["icone"].$registros["descansoCompleto"]["totalIntervalo"];
		$aRetorno["diffRepouso"]  = $registros["repousoCompleto"]["icone"].$registros["repousoCompleto"]["totalIntervalo"];

		//JORNADA EFETIVA{
			if(is_string($registros["jornadaCompleto"]["totalIntervalo"])){
				$jornadaIntervalo = new DateTime($data." 00:00");
			}else{
				$jornadaIntervalo = $registros["jornadaCompleto"]["totalIntervalo"];
			}

			$totalNaoJornada = [
				$registros["refeicaoCompleto"]["totalIntervalo"]
			];

			//Ignorar intervalos que tenham sido marcados para ignorar no parâmetro{
				if(!empty($motorista["enti_nb_parametro"]) && !empty($motorista["para_tx_ignorarCampos"])){
					$campos = ["espera", "descanso", "repouso"/*, "repousoEmbarcado"*/];
					foreach($campos as $campo){
						if(is_bool(strpos($motorista["para_tx_ignorarCampos"], $campo))){
							$totalNaoJornada[] = $registros[$campo."Completo"]["totalIntervalo"];
						}
					}
				}else{
					$totalNaoJornada = [
						$registros["refeicaoCompleto"]["totalIntervalo"],
						(($motorista["para_tx_adi5322"] == "nao")? $registros["esperaCompleto"]["totalIntervalo"]: "00:00"),
						$registros["descansoCompleto"]["totalIntervalo"],
						$registros["repousoCompleto"]["totalIntervalo"]
					];
				}
			//}
			
			
			if(!empty($registros["inicioJornada"][0])){
				$value = new DateTime("{$data} 00:00:00");
				for($f = 0; $f < count($totalNaoJornada); $f++){
					$modifyParam = explode(":", $totalNaoJornada[$f]);
					$value = $value->modify("{$modifyParam[0]} hours {$modifyParam[1]} minutes");
				}
				$totalNaoJornada = $value;
			}else{
				$totalNaoJornada = new DateTime("{$data} 00:00:00");
			}

			$jornadaEfetiva = $totalNaoJornada->diff($jornadaIntervalo);
			$jornadaEfetiva = formatToTime($jornadaEfetiva->days*24+$jornadaEfetiva->h, $jornadaEfetiva->i);

			$limiteJorEfetiva = ((isset($motorista["para_tx_acordo"]) && $motorista["para_tx_acordo"] == "sim")? "12:00": "10:00");
			$aRetorno["diffJornadaEfetiva"] = verificaLimiteTempo($jornadaEfetiva, $limiteJorEfetiva);
		//}

		//CÁLCULO DE INTERSTÍCIO{
			if(!empty($registros["inicioJornada"])){

				$ultimoFimJornada = mysqli_fetch_array(query(
					"SELECT pont_tx_data FROM ponto
						WHERE pont_tx_status = 'ativo'
							AND pont_tx_tipo = 2
							AND pont_tx_matricula = '{$motorista["enti_tx_matricula"]}'
							AND pont_tx_data < '{$registros["inicioJornada"][0]}'
						ORDER BY pont_tx_data DESC
						LIMIT 1;"
				), MYSQLI_BOTH);
				if(!empty($ultimoFimJornada)){
					$ultimoFimJornada = DateTime::createFromFormat("Y-m-d H:i:s", $ultimoFimJornada[0]);
					
					$intersticioDiario = (new DateTime($registros["inicioJornada"][0]))->diff($ultimoFimJornada);
					
					// Obter a diferença total em minutos
					$intersticioDiario = (
						$intersticioDiario->days*60*24+
						$intersticioDiario->h*60+
						$intersticioDiario->i
					);

					
					// Calcular as horas e minutos
					
					$intersticioDiario = sprintf("%02d:%02d", floor($intersticioDiario / 60), $intersticioDiario % 60); // Formatar a string no formato H:I
					$totalIntersticio = operarHorarios([$intersticioDiario, $totalNaoJornada->format("H:i")], "+");

					$icone = "";
					$interMinimo = "08:00";
					if($motorista["para_tx_adi5322"] == "sim" && operarHorarios([$intersticioDiario, "11:00"], "-")[0] == "-"){
						$interMinimo = "11:00";
					}

					if(operarHorarios([$intersticioDiario, $interMinimo], "-")[0] == "-"){
						$restante = operarHorarios([$interMinimo, $intersticioDiario], "-");
						$title = "Interstício Mínimo de {$interMinimo} não respeitado, faltaram {$restante}.";
						if(operarHorarios([$totalIntersticio, $interMinimo], "-")[0] != "-"){
							$title .= "\nInterstício remanescente compensado com intervalos do dia (".$totalNaoJornada->format("H:i").").";
						}
						$icone .= "<a><i style='color:red;' title='{$title}' class='fa fa-warning'></i></a>";
					}
					unset($restante);

					$aRetorno["intersticio"] = $icone.$totalIntersticio;
				}else{
					$aRetorno["intersticio"] = "00:00";
				}
			}
		//}

		//CALCULO SALDO DIÁRIO{
			$aRetorno["diffSaldo"] = getSaldoDiario($jornadaPrevista, $jornadaEfetiva);
		//}

		//CALCULO ESPERA INDENIZADA{
			if($motorista["para_tx_adi5322"] == "sim"){
				$aRetorno["esperaIndenizada"] = "00:00";
			}else{
				$intervaloEsp = somarHorarios([$registros["esperaCompleto"]["totalIntervalo"], $registros["repousoPorEspera"]["totalIntervalo"]]);
				$indenizarEspera = operarHorarios([$intervaloEsp, "02:00"], "-")[0] != '-';
				//Compensar com o intervalo de espera caso o saldo diário esteja negativo{
					if($aRetorno["diffSaldo"][0] == "-"){
						if($intervaloEsp > substr($aRetorno["diffSaldo"], 1)){
							$transferir = substr($aRetorno["diffSaldo"], 1);
						}else{
							$transferir = $intervaloEsp;
						}
						$intervaloEsp = operarHorarios([$intervaloEsp, $transferir], "-");
						$aRetorno["diffSaldo"] = operarHorarios([$aRetorno["diffSaldo"], $transferir], "+");
					}
				//}

				if($indenizarEspera){
					$aRetorno["esperaIndenizada"] = $intervaloEsp;
				}
			}
		//}

		//INICIO ADICIONAL NOTURNO{
			$aRetorno["adicionalNoturno"] = calcularAdicNot($registros);
		//}
		
		//TOLERÂNCIA{
			$tolerancia = !empty($motorista["para_tx_tolerancia"])? intval($motorista["para_tx_tolerancia"]): 0;

			$saldo = explode(":", $aRetorno["diffSaldo"]);
			$saldo = intval($saldo[0])*60 + ($saldo[0][0] == "-"? -1: 1)*intval($saldo[1]);
			
			if($saldo >= -($tolerancia) && $saldo <= $tolerancia){
				$aRetorno["diffSaldo"] = "00:00";
				$saldo = 0;
			}
		//}

		//HORAS EXTRAS{
			if($aRetorno["diffSaldo"][0] != "-"){ 	//Se o saldo for positivo

				if(!empty($stringFeriado) || (new DateTime("{$data} 00:00:00"))->format("D") == "Sun"){
					$aRetorno["he100"] = $aRetorno["diffSaldo"];
					$aRetorno["he50"] = "00:00";
				}else{
					if(	(isset($motorista["para_tx_maxHESemanalDiario"]) && !empty($motorista["para_tx_maxHESemanalDiario"])) &&
						$motorista["para_tx_maxHESemanalDiario"] != "00:00" && 
						$aRetorno["diffSaldo"] >= $motorista["para_tx_maxHESemanalDiario"]
					){// saldo diário >= limite de horas extras 100%
						$aRetorno["he100"] = operarHorarios([$aRetorno["diffSaldo"], $motorista["para_tx_maxHESemanalDiario"]], "-");
					}else{
						$aRetorno["he100"] = "00:00";
					}
					$aRetorno["he50"] = operarHorarios([$aRetorno["diffSaldo"], $aRetorno["he100"]], "-");
				}
			}
		//}

		

		//MÁXIMA DIREÇÃO CONTÍNUA{
			if(is_bool(strpos($motorista["para_tx_ignorarCampos"], "mdc"))){
				$intervalos = [];
				$interAtivo = null;
				foreach($pontosDia as $ponto){
					if(empty($interAtivo)){
						$interAtivo = new DateTime($ponto["pont_tx_data"]);
						continue;
					}
					
					$intervalos[] = [
						!($tipos[$ponto["pont_tx_tipo"]] == "inicioJornada" || (is_int(strpos($tipos[$ponto["pont_tx_tipo"]], "fim")) && $tipos[$ponto["pont_tx_tipo"]] != "fimJornada")), 
						date_diff($interAtivo, new DateTime($ponto["pont_tx_data"]))
					];
					$interAtivo = new DateTime($ponto["pont_tx_data"]);
				}
				$aRetorno["maximoDirecaoContinua"] = verificarAlertaMDC($intervalos);
			}
		//}

		//JORNADA MÍNIMA
			$modifyParam = explode(":", $jornadaEfetiva);
			$dtJornada = (new DateTime("{$data} 00:00:00"))->modify("{$modifyParam[0]} hours {$modifyParam[1]} minutes");
			$dtJornadaMinima = new DateTime($data." 06:00");

			$fezJorMinima = ($dtJornada >= $dtJornadaMinima);
		//FIM JORNADA MÍNIMA

		//ALERTAS{
			if((!isset($registros["inicioJornada"][0]) || $registros["inicioJornada"][0] == "") && $aRetorno["jornadaPrevista"] != "00:00"){
				$aRetorno["inicioJornada"][] 	= "<a><i style='color:red;' title='Batida início de jornada não registrada!' class='fa fa-warning'></i></a>";
			}
			if($fezJorMinima || count($registros["inicioJornada"]) > 0){
				if(!isset($registros["fimJornada"][0]) || $registros["fimJornada"][0] == ""){
					$aRetorno["fimJornada"][] 	  = "<a><i style='color:red;' title='Batida fim de jornada não registrada!' class='fa fa-warning'></i></a>";
				}

				//01:00 DE REFEICAO{
					$maiorRefeicao = "00:00";
					if(count($registros["refeicaoCompleto"]["pares"]) > 0){
						for ($i = 0; $i < count($registros["refeicaoCompleto"]["pares"]); $i++){
							if(!empty($registros["refeicaoCompleto"]["pares"][$i]["intervalo"]) && $maiorRefeicao < $registros["refeicaoCompleto"]["pares"][$i]["intervalo"]){
								$maiorRefeicao = $registros["refeicaoCompleto"]["pares"][$i]["intervalo"];
							}
						}
					}

					$avisoRefeicao = "";
					if($maiorRefeicao > "02:00"){
						$avisoRefeicao = "<a><i style='color:orange;' title='Refeição com tempo máximo de 02:00h não respeitado.' class='fa fa-info-circle'></i></a>";
					}elseif($dtJornada > $dtJornadaMinima && $maiorRefeicao < '01:00'){
						$avisoRefeicao = "<a><i style='color:red;' title='Refeição ininterrupta maior do que 01:00h não respeitado.' class='fa fa-warning'></i></a>";
					}
				//}

				// if((!isset($registros["inicioRefeicao"][0]) || empty($aRetorno["inicioRefeicao"][0])) && $jornadaEfetiva > "06:00"){
				// 	$aRetorno["inicioRefeicao"][] = "<a><i style='color:red;' title='Batida início de refeição não registrada!' class='fa fa-warning'></i></a>";
				// }else{
				// 	$aRetorno["inicioRefeicao"][] = $avisoRefeicao;
				// }

				// if((!isset($registros["fimRefeicao"][0]) || empty($aRetorno["fimRefeicao"][0])) && ($jornadaEfetiva > "06:00")){
				// 	$aRetorno["fimRefeicao"][] 	  = "<a><i style='color:red;' title='Batida fim de refeição não registrada!' class='fa fa-warning'></i></a>";
				// }else{
				// 	$aRetorno["fimRefeicao"][] = $avisoRefeicao;
				// }
				if(!empty($avisoRefeicao)){
					$aRetorno["diffRefeicao"] = $avisoRefeicao." ".$aRetorno["diffRefeicao"];
				}
			}
		//}

		foreach(["inicioJornada", "fimJornada", "inicioRefeicao", "fimRefeicao"] as $campo){
			if(count($registros[$campo]) > 0 && !empty($registros[$campo][0])){
				$aRetorno[$campo] = $registros[$campo];
			}
		}

		if(count($registros["inicioEspera"]) > 0 && count($registros["fimEspera"]) > 0){
			$aRetorno["diffEspera"]   = $registros["esperaCompleto"]["icone"].$registros["esperaCompleto"]["totalIntervalo"];
		}
		if(count($registros["inicioDescanso"]) > 0 && count($registros["fimDescanso"]) > 0){
			$aRetorno["diffDescanso"] = $registros["descansoCompleto"]["icone"].$registros["descansoCompleto"]["totalIntervalo"];
		}
		if(count($registros["inicioRepouso"]) > 0 && count($registros["fimRepouso"]) > 0){
			$aRetorno["diffRepouso"]  = $registros["repousoCompleto"]["icone"].$registros["repousoCompleto"]["totalIntervalo"];
		}
		
		//LEGENDAS{
			if(!empty($registros["inicioJornada"])){
				$datas = 
					"('".implode("', '", $registros["inicioJornada"])."'"
					.(!empty($registros["inicioRefeicao"])? ", '".implode("', '", $registros["inicioRefeicao"])."'": "")
					.(!empty($registros["fimRefeicao"])? ", '".implode("', '", $registros["fimRefeicao"])."'": "")
					.(!empty($registros["fimJornada"])? ", '".implode("', '", $registros["fimJornada"])."')": ")")
				;

				$legendas = mysqli_fetch_all(query(
					"SELECT moti_tx_legenda, macr_tx_nome FROM ponto
						JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
						LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
						WHERE ponto.pont_nb_motivo IS NOT NULL 
							AND pont_tx_status = 'ativo'
							AND pont_tx_data IN ".$datas." 
							AND pont_tx_matricula = '{$motorista["enti_tx_matricula"]}'"
				), MYSQLI_ASSOC);
		
				$tipos = [
					"I" 	=> 0, 
					"P" 	=> 0, 
					"T" 	=> 0, 
					"DSR" 	=> 0
				];
				$contagens = [
					"inicioJornada" => $tipos,
					"fimJornada" => $tipos,
					"inicioRefeicao" => $tipos,
					"fimRefeicao" => $tipos,
				];
				
				foreach ($legendas as $value){
					$legenda = $value["moti_tx_legenda"];
				
					switch ($value["macr_tx_nome"]){
						case "Inicio de Jornada":
							$acao = "inicioJornada";
							break;
						case "Fim de Jornada":
							$acao = "fimJornada";
							break;
						case "Inicio de Refeição":
							$acao = "inicioRefeicao";
							break;
						case "Fim de Refeição":
							$acao = "fimRefeicao";
							break;
						default:
							$acao = "";
					}
					if($acao != "" && !empty($legenda) && array_key_exists($legenda, $contagens[$acao])){
						$contagens[$acao][$legenda]++;
					}
				}
				
				foreach ($contagens as $acao => $tipos){
					foreach ($tipos as $tipo => $quantidade){
						if($quantidade > 0){
							$aRetorno[$acao][] = "<strong>{$tipo}</strong>";
						}
					}
				}
			}
		//}

		//Aviso de registro inativado{
			$ajuste = mysqli_fetch_all(query(
				"SELECT DISTINCT macr_tx_nome FROM ponto
					JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
					WHERE pont_tx_status = 'inativo'
						AND pont_tx_data LIKE '%{$data}%' 
						AND pont_tx_matricula = '{$motorista["enti_tx_matricula"]}'"
			), MYSQLI_ASSOC);
	
			//nomeNoBD => nomeNoRetorno
			$macroNomes = [
				"Inicio de Jornada" 	=> "inicioJornada",
				"Fim de Jornada" 		=> "fimJornada",
				"Inicio de Refeição" 	=> "inicioRefeicao",
				"Fim de Refeição" 		=> "fimRefeicao"
			];
			foreach ($ajuste as $macroNome){
				if(!empty($macroNomes[$macroNome["macr_tx_nome"]])){
					$aRetorno[$macroNomes[$macroNome["macr_tx_nome"]]][] = "*";
				}	
			}
		//}

		//SOMANDO TOTAIS{
			foreach(array_slice(array_keys($aRetorno), 6) as $campo){
				$totalResumo[$campo] = operarHorarios([((empty($totalResumo[$campo]))? "00:00": $totalResumo[$campo]), preg_replace("/([^\-^0-:])+/", "", strip_tags($aRetorno[$campo]))], "+");
			}
		//}

		if($saldo > 0){
			$aRetorno["diffSaldo"] = "<b>".$aRetorno["diffSaldo"]."</b>";
		}

		foreach(["inicioJornada", "fimJornada", "inicioRefeicao", "fimRefeicao"] as $tipo){
			$pontos = [];
			if(count($aRetorno[$tipo]) > 0){
				foreach($aRetorno[$tipo] as $ponto){
					if(preg_match("/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/", $ponto)){
						$pontos[] = [
							"key" => count($pontos),
							"value" => $ponto
						];
					}
				}
			}

			if(!empty($pontos)){
				$dataDia = DateTime::createFromFormat("d/m/Y H:i:s", $aRetorno["data"]." 00:00:00");
				foreach($pontos as $ponto){
					$dataFim = DateTime::createFromFormat("Y-m-d H:i:s", $ponto["value"]);
					$qttDias = date_diff($dataDia, $dataFim);
					if(!is_bool($qttDias)){
						$qttDias = intval($qttDias->format("%d"));
						if($qttDias > 0){
							array_splice($aRetorno[$tipo], array_search($ponto["value"], $aRetorno[$tipo])+1, 0, "D+".$qttDias);
						}
					}
				}
			}
		}


		//Converter array em string{
			$legendas = mysqli_fetch_all(query(
				"SELECT DISTINCT moti_tx_legenda FROM motivo 
					WHERE moti_tx_legenda IS NOT NULL;"
				), 
				MYSQLI_ASSOC
			);

			$legendas = array_map(function($legenda){return $legenda["moti_tx_legenda"];}, $legendas);

			
			$legendas = array_merge($legendas, [
				"D+",
				"*",
				"<strong>"
			]);

			$getStringPontosColuna = function (array $pontos, array $legendas){
				if(count($pontos) == 0 || (count($pontos) == 1 && $pontos[0] == "")){
					return "";
				}
				
				foreach($pontos as &$value){
					//Formatar datas para H:i
					if(preg_match("/-?\d{2,10}:\d{2}:\d{2}$/", $value, $matches)){
						$value = substr($matches[0], 0, -3);
					}
				}
				$pontos = implode("<br>", $pontos);

				$search = array_map(function($legenda){return "<br>{$legenda}";}, $legendas);
				$replace = array_map(function($legenda){return " {$legenda}";}, $legendas);
				
				$result = str_replace($search, $replace, $pontos);
				return $result;
			};

			foreach(["inicioJornada", "fimJornada", "inicioRefeicao", "fimRefeicao"] as $tipo){
				$aRetorno[$tipo] = $getStringPontosColuna($aRetorno[$tipo], $legendas);
			}
		//}

		return $aRetorno;
	}

	function getFeriados(array &$motorista, string $data): string{
		$sqlFeriado = 
			"SELECT feri_tx_nome FROM feriado 
				WHERE feri_tx_status = 'ativo'
					AND feri_tx_data LIKE '{$data}%'"
		;
		$extra = "";
		if(!empty($motorista["cida_tx_uf"])){
			$extra = 
				" AND (
					(feri_nb_cidade IS NULL AND feri_tx_uf IS NULL)
					OR (feri_tx_uf = '{$motorista["cida_tx_uf"]}' AND feri_nb_cidade IS NULL)"
					.(!empty($motorista["cida_nb_id"])? " OR feri_nb_cidade = '{$motorista["cida_nb_id"]}'": "")
				.")"
			;
		}
		$feriados = mysqli_fetch_all(query($sqlFeriado.$extra), MYSQLI_ASSOC);

		$result = "";
		if(!empty($feriados)){
			$result = $feriados[0]["feri_tx_nome"];
			for($f = 1; $f < count($feriados); $f++){
				$result .= ", {$feriados[$f]["feri_tx_nome"]}";
			}
		}
		return $result;
	}
	
	function getSaldoDiario(string $jornadaPrevista, string $jornadaEfetiva): string{
		$saldoDiario = operarHorarios(["-".$jornadaPrevista, $jornadaEfetiva], "+");
		return $saldoDiario;
	}

	function montarEndossoMes(DateTime $dateMes, array $aMotorista): array{
		$month = intval($dateMes->format("m"));
		$year = intval($dateMes->format("Y"));
		
		$daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));
		$endossos = mysqli_fetch_all(query(
			"SELECT endo_tx_filename FROM endosso 
				WHERE endo_tx_status = 'ativo'
					AND endo_tx_matricula = '".$aMotorista["enti_tx_matricula"]."'
					AND endo_tx_de >= '".sprintf("%04d-%02d-%02d", $year, $month, "01")."'
					AND endo_tx_ate <= '".sprintf("%04d-%02d-%02d", $year, $month, $daysInMonth)."'
				ORDER BY endo_tx_de ASC"
		),MYSQLI_ASSOC);

		foreach($endossos as &$endosso){
			$endosso = lerEndossoCSV($endosso["endo_tx_filename"]);
		}

		$endossoCompleto = [];

		if(count($endossos) > 0){
			$endossoCompleto = $endossos[0];
			if(empty($endossoCompleto["endo_tx_max50APagar"]) && !empty($endossoCompleto["endo_tx_horasApagar"])){
				$endossoCompleto["endo_tx_max50APagar"] = $endossoCompleto["endo_tx_horasApagar"];
			}
			for($f = 1; $f < count($endossos); $f++){
				if(empty($endossos[$f]["endo_tx_max50APagar"])){
					if(!empty($endossos[$f]["endo_tx_horasApagar"])){
						$endossos[$f]["endo_tx_max50APagar"] = $endossos[$f]["endo_tx_horasApagar"];
					}else{
						$endossoCompleto["endo_tx_max50APagar"] = "00:00";
					}
				}
				$endossoCompleto["endo_tx_ate"] = $endossos[$f]["endo_tx_ate"];
				$endossoCompleto["endo_tx_pontos"] = array_merge($endossoCompleto["endo_tx_pontos"], $endossos[$f]["endo_tx_pontos"]);
				if($endossoCompleto["endo_tx_max50APagar"] != "00:00"){
					$endossoCompleto["endo_tx_max50APagar"] = operarHorarios([$endossoCompleto["endo_tx_max50APagar"], $endossos[$f]["endo_tx_max50APagar"]], "+");	
					if(is_int(strpos($endossoCompleto["endo_tx_max50APagar"], "-"))){
						$endossoCompleto["endo_tx_max50APagar"] = "00:00";
					}
				}
				foreach($endossos[$f]["totalResumo"] as $key => $value){
					if(in_array($key, ["saldoAnterior", "saldoBruto", "saldoFinal"])){
						continue;
					}
					$endossoCompleto["totalResumo"][$key] = operarHorarios([$endossoCompleto["totalResumo"][$key], $value], "+");
				}
			}
			$endossoCompleto["totalResumo"]["saldoBruto"] = operarHorarios([$endossoCompleto["totalResumo"]["saldoAnterior"], $endossoCompleto["totalResumo"]["diffSaldo"]], "+");
			$endossoCompleto["totalResumo"]["saldoFinal"] = $endossos[count($endossos)-1]["totalResumo"]["saldoFinal"];
		}

		return $endossoCompleto;
	}

	function montarIconeIntervalo(array $pares, string $classe = "fa fa-info-circle", string $cor = "green"): string{
		$icone = "";
		$tooltip = "";
		for($f = 0; $f < count($pares); $f++){
			$temp = [
				(!empty($pares[$f]["inicio"])? DateTime::createFromFormat("Y-m-d H:i:s", $pares[$f]["inicio"])->format("d/m H:i"): ""),
				(!empty($pares[$f]["fim"])? DateTime::createFromFormat("Y-m-d H:i:s", $pares[$f]["fim"])->format("d/m H:i"): "")
			];
			$tooltip .= 
				"Início: {$temp[0]}\n"
				."Fim:    {$temp[1]}\n\n";
		}
		unset($temp);
		if(is_int(strpos($tooltip, "Início: \n")) || is_int(strpos($tooltip, "Fim:    \n"))){
			$cor = "red";
		}

		$icone = "<a><i title='{$tooltip}' class='{$classe}' style='color:{$cor};'></i></a>";

		return $icone;
	}

	function operarHorarios(array $horarios, string $operacao): string{
		//Horários com formato de rH:i. Ex.: 00:04, 05:13, -01:12.
		//$operação

		if(empty($horarios) || !in_array($operacao, ["+", "-", "*", "/"])){
			echo "<script>console.log('Operação não encontrada (operarHorarios): |{$operacao}|')</script>";
			return "00:00";
		}
		if(preg_match_all("/-?\d{2,10}:\d{2}(?=:\d{2})?/", implode(" ", $horarios)) != count($horarios)){
			echo "<script>console.log('Format error (operarHorarios): |".implode(", ", $horarios)."|')</script>";
			return "00:00";
		}
		if(count($horarios) == 1){
			return $horarios[0];
		}

		$horarios = preg_replace("/([^\-^0-:])+/", "", $horarios);
		preg_match_all("/-?\d{2,10}:\d{2}(?=:\d{2})?/", implode(",", $horarios), $horarios);
		$horarios = $horarios[0];
		
		$result = array_shift($horarios);
		$result = explode(":", $result);
		$result = intval($result[0]*60)+(($result[0][0] == "-")?-1:1)*intval($result[1]);
		
		foreach($horarios as $horario){
			if(empty($horario)){
				continue;
			}
			$horario = explode(":", $horario);
			$horario = intval($horario[0]*60)+(($horario[0][0] == "-")?-1:1)*intval($horario[1]);

			eval("\$result {$operacao}= {$horario};");
		}

		$result = sprintf("%s%02d:%02d", (($result < 0)?"-":""), abs(intval($result/60)), abs(intval($result%60)));

		return $result;
	}
	
	function ordenarHorariosTipo(array $inicios, array $fins, string $tipo = "", int $order = SORT_ASC): array{
		if(empty($inicios) && empty($fins)){
			return [];
		}
		// Inicializa o array resultante e o array de indicação
		$horarios = [];
		$tipos = [];

		$horarios = array_merge($inicios, $fins);
		$tipos = array_pad([], count($inicios), "inicio".ucfirst($tipo));
		$tipos = array_pad($tipos, count($tipos)+count($fins), "fim".ucfirst($tipo));

		// Ordena o array de horários
		array_multisort($horarios, $order, $tipos);

		for($f = 0; $f < count($horarios); $f++){
			$horarios[$f] = [
				"data" => $horarios[$f], 
				"tipo" => $tipos[$f]
			];
		}

		return $horarios;
	}

	function organizarIntervalos(string $data, array $inicios, array $fins): array{

		$totalIntervalo = new DateTime("{$data} 00:00:00");
		
		//Resposta padrão{
			$paresResult = [
				"pares" => [],
				"totalIntervalo" => $totalIntervalo,
				"icone" => ""
			];

			if(empty($inicios) && empty($fins)){
				return $paresResult;
			}
		//}

		$horariosOrdenados = ordenarHorariosTipo($inicios, $fins);
		unset($inicios);
		unset($fins);

		$pares = [];
		$parAtual = null;

		//Arredonda os minutos, caso haja segundos
		$getInterval = function (string $inicio, string $fim) {
			$interval = (new DateTime($inicio))->diff(new DateTime($fim));
			if($interval->s > 30){
				$interval->s = 0;
				$interval->i++;
			}
			return $interval;
		};


		foreach ($horariosOrdenados as $ponto){
			if($ponto["tipo"] == "inicio"){
				if(!empty($parAtual["inicio"])){
					//Significa que tem dois inícios consecutivos
					$pares[] = $parAtual;
				}
				$parAtual = ["inicio" => $ponto["data"], "fim" => null];
			}elseif($ponto["tipo"] == "fim"){
				if(empty($parAtual["inicio"])){
					//Significa que tem dois fins consecutivos
					$pares[] = $parAtual;
				}else{

					$parAtual["fim"] = $ponto["data"];
					$interval = $getInterval($parAtual["inicio"], $parAtual["fim"]);

					$totalIntervalo->add($interval);
					$parAtual["intervalo"] = formatToTime($interval->h, $interval->i, $interval->s);
					$pares[] = $parAtual;

					$parAtual = null;
				}
			}
		}
		if($ponto["tipo"] == "inicio"){
			$pares[] = ["inicio" => $ponto["data"], "fim" => "", "intervalo" => "00:00"];
		}

		$paresResult = [
			"pares" => $pares,
			"totalIntervalo" => $totalIntervalo
		];

		$paresResult["icone"] = montarIconeIntervalo($pares);
		if(count($horariosOrdenados) > 2){
			$totalInterjornada = new DateTime("{$data} 00:00:00");
			for ($i = 1; $i < count($horariosOrdenados); $i++){
				if($horariosOrdenados[$i]["tipo"] == "inicio" && $horariosOrdenados[$i-1]["tipo"] == "fim"){
					$intervalInterjornada = $getInterval($horariosOrdenados[$i]["data"], $horariosOrdenados[$i-1]["data"]);
					$totalInterjornada->add($intervalInterjornada);
				}
			}
			$paresResult["interjornada"] = formatToTime(intval($totalInterjornada->format("H")), intval($totalInterjornada->format("i")), intval($totalInterjornada->format("s")));
		}

		// Retorna o array de horários com suas respectivas origens
		return $paresResult;
	}

	function pegarDiaSemana($date){
		$week = [
			"Sunday" => "Domingo", 
			"Monday" => "Segunda-Feira",
			"Tuesday" => "Terca-Feira",
			"Wednesday" => "Quarta-Feira",
			"Thursday" => "Quinta-Feira",
			"Friday" => "Sexta-Feira",
			"Saturday" => "Sábado"
		];
		$response = $week[date("l", strtotime($date))];

		return $response;
	}

	function pegarSqlDia(string $matricula, DateTime $data, array $cols): string{

		$condicoesPontoBasicas = "ponto.pont_tx_status = 'ativo' AND ponto.pont_tx_matricula = '{$matricula}'";

		$sqlDataInicio = $data->format("Y-m-d 00:00:00");
		$sqlDataFim = $data->format("Y-m-d 23:59:59");

		$ultJornadaOntem = mysqli_fetch_assoc(query(
			"SELECT pont_tx_data, (pont_tx_tipo = 1) as jornadaAbertaAntes FROM ponto "
				." WHERE {$condicoesPontoBasicas}"
					." AND pont_tx_tipo IN (1,2)"
					." AND pont_tx_data < STR_TO_DATE('{$sqlDataInicio}', '%Y-%m-%d %H:%i:%s')"
				." ORDER BY pont_tx_data DESC"
				." LIMIT 1;"
		));

		$primJornadaAmanha = mysqli_fetch_assoc(query(
			"SELECT pont_tx_data, (pont_tx_tipo = 2) as jornadaFechadaApos FROM ponto "
				." WHERE {$condicoesPontoBasicas}"
					." AND pont_tx_tipo IN (1,2)"
					." AND pont_tx_data > STR_TO_DATE('{$sqlDataFim}', '%Y-%m-%d %H:%i:%s')"
				." ORDER BY pont_tx_data ASC"
				." LIMIT 1;"
		));


		if(!empty($ultJornadaOntem) && intval($ultJornadaOntem["jornadaAbertaAntes"])){
			$sqlDataInicio = $ultJornadaOntem["pont_tx_data"];
		}

		if(!empty($primJornadaAmanha) && intval($primJornadaAmanha["jornadaFechadaApos"])){
			$sqlDataFim = $primJornadaAmanha["pont_tx_data"];
		}

		$condicoesPontoBasicas = 
			"ponto.pont_tx_status = '{$_POST["status"]}'"
			." AND ponto.pont_tx_matricula = '{$matricula}'"
			." AND entidade.enti_tx_status = 'ativo'"
			." AND user.user_tx_status = 'ativo'"
			." AND macroponto.macr_tx_status = 'ativo'"
		;
		$sql = 
			"SELECT DISTINCT pont_nb_id, ".implode(",", $cols)." FROM ponto"
				." JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno"
				." JOIN entidade ON ponto.pont_tx_matricula = entidade.enti_tx_matricula"
				." JOIN user ON entidade.enti_nb_id = user.user_nb_entidade"
				." LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id"
				." LEFT JOIN endosso ON ponto.pont_tx_matricula = endosso.endo_tx_matricula AND endo_tx_status = 'ativo' AND endo_tx_matricula = '{$matricula}' AND '{$data->format("Y-m-d")}' BETWEEN endo_tx_de AND endo_tx_ate"
				." WHERE {$condicoesPontoBasicas}"
					." AND macr_tx_fonte = 'positron'"
					." AND ponto.pont_tx_data >= STR_TO_DATE('{$sqlDataInicio}', '%Y-%m-%d %H:%i:%s')"
					." AND ponto.pont_tx_data <= STR_TO_DATE('{$sqlDataFim}', '%Y-%m-%d %H:%i:%s')"
		;


		return $sql;
	}

	function somarHorarios(array $horarios): string{
		return operarHorarios($horarios, "+");
	}

	function verificaLimiteTempo(string $tempoEfetuado, string $limite){
		// Verifica se os parâmetros são strings e possuem o formato correto
		if(!preg_match("/^\d{2}:\d{2}$/", $tempoEfetuado) || !preg_match("/^\d{2}:\d{2}$/", $limite)){
			return "";
		}
		if(intval(explode(":", $tempoEfetuado)[0]) > 23){
			$vals = explode(":", $tempoEfetuado);
			$dateInterval = new DateInterval("P".floor($vals[0]/24)."DT".($vals[0]%24)."H".$vals[1]."M");
			$datetime1 = new DateTime("2000-01-01 00:00");
			$datetime1->add($dateInterval);
		}else{
			$datetime1 = new DateTime("2000-01-01 ".$tempoEfetuado);
		}
		$datetime2 = new DateTime("2000-01-01 ".$limite);

		if($datetime1 > $datetime2){
			return "<a style='white-space: nowrap;'><i style='color:orange;' title='Tempo excedido de ".$limite."' class='fa fa-warning'></i></a>&nbsp;".$tempoEfetuado;
		}else{
			return $tempoEfetuado;
		}
	}

	function verificarAlertaMDC(array $intervalos = []): string{
		$baseErrMsg = "Descanso de 00:15 a cada 05:30 digiridos não respeitado.";
		$mdc = "00:00";
		
		if(empty($intervalos)){
			return $mdc;
		}

		for($f = 0; $f < count($intervalos); $f++){
			$date = date_add(new DateTime("1970-01-01 00:00:00"), $intervalos[$f][1]);
			$intervalos[$f][1] = sprintf("%02d:%02d", abs(intval((dateTimeToSecs($date)/60)/60)), abs(intval((dateTimeToSecs($date)/60)%60)));
			if($intervalos[$f][0] == true && $intervalos[$f][1] > $mdc){ //Se o intervalo é de um horário ativo de trabalho E for maior que o MDC atual.
				$mdc = $intervalos[$f][1];
			}
		}

		if($mdc > "05:30"){
			$mdc = "<a style='white-space: nowrap;'>".
						"<i style='color:orange;' title='{$baseErrMsg}' class='fa fa-warning'></i>".
					"</a>".
					"&nbsp;".$mdc
			;
			return $mdc;
		}

		
		for($f = 0; $f < count($intervalos); $f++){
			$considerarPosJornada = true;
			$somaTempoAtivo = "00:00";
			$somaTempoDescanso = "00:00";
			$somaTempoTotal = "00:00";

			$tempoTrabalho = "05:30";
			$tempoDescanso = "00:15";

			for($f2 = 0; $f2 < count($intervalos); $f2++){
				if($intervalos[$f2][0]){
					$somaTempoAtivo = operarHorarios([$somaTempoAtivo, $intervalos[$f2][1]], "+");
				}else{
					$somaTempoDescanso = operarHorarios([$somaTempoDescanso, $intervalos[$f2][1]], "+");
				}
				$somaTempoTotal = operarHorarios([$somaTempoAtivo, $somaTempoDescanso], "+");

				if($somaTempoTotal >= operarHorarios([$tempoTrabalho, $tempoDescanso], "+")){
					$considerarPosJornada = false;
					$excedente = operarHorarios([$somaTempoTotal, operarHorarios([$tempoTrabalho, $tempoDescanso], "+")], "-");
					if($intervalos[$f2][0]){
						// $somaTempoAtivo = operarHorarios([$somaTempoAtivo, $excedente], "-");
					}else{
						$somaTempoDescanso = operarHorarios([$somaTempoDescanso, $excedente], "-");
					}
					$f2 = count($intervalos);
				}
			}
			$somaTempoTotal = operarHorarios([$somaTempoAtivo, $somaTempoDescanso], "+");

			if($considerarPosJornada){
				$excedente = operarHorarios([$somaTempoTotal, operarHorarios([$tempoTrabalho, $tempoDescanso], "+")], "-"); //Dará um número negativo
				$somaTempoDescanso = operarHorarios([$somaTempoDescanso, $excedente], "-"); //Será somado, pois o excedente é negativo
			}
	
			if($somaTempoAtivo > $tempoTrabalho && $somaTempoDescanso < $tempoDescanso){
				$mdc = "<a style='white-space: nowrap;'>".
							"<i style='color:orange;' title='{$baseErrMsg}\n\nDirigido: ".$somaTempoAtivo."\nDescansado: ".$somaTempoDescanso."' class='fa fa-warning'></i>".
						"</a>".
						"&nbsp;".$mdc
				;
				$f = count($intervalos);
			}
		}

		/**
		 * Percorrer intervalos e separar em intervalos de 6 em 6 horas
		 * Se houver algum intervalo de 6 horas com menos de 30 minutos de descanso, enviar o alerta.
		 */
		
		return $mdc;
	}

	function verificaTolerancia(string $saldoDiario, string $data, $idMotorista){
		$saldoDiario = str_replace(["<b>", "</b>"], ["", ""], $saldoDiario);
		date_default_timezone_set("America/Recife");
		
		$tolerancia = mysqli_fetch_assoc(query(
			"SELECT par.para_tx_tolerancia
				FROM entidade en
				INNER JOIN parametro par ON en.enti_nb_parametro = par.para_nb_id
				WHERE en.enti_nb_id = '{$idMotorista}'"
		));

		$tolerancia = (empty($tolerancia["para_tx_tolerancia"]))? 0: $tolerancia["para_tx_tolerancia"];
		$tolerancia = intval($tolerancia);

		$saldoDiario = explode(":", $saldoDiario);
		$saldoEmMinutos = intval($saldoDiario[0])*60+($saldoDiario[0][0] == "-"? -1: 1)*intval($saldoDiario[1]);

		if($saldoEmMinutos < -($tolerancia)){
			$cor = "red";
		}elseif($saldoEmMinutos > $tolerancia){
			$cor = "green";
		}else{
			$cor = "blue";
		}

		$endossado = mysqli_fetch_all(
			query(
				"SELECT * FROM endosso 
					JOIN entidade ON endo_tx_matricula = enti_tx_matricula
					WHERE '".$data."' BETWEEN endo_tx_de AND endo_tx_ate
						AND enti_nb_id = {$idMotorista}
						AND endo_tx_status = 'ativo';"
			),
			MYSQLI_ASSOC
		);

		$title = "Ajuste de Ponto";
		$func = "ajustarPonto({$idMotorista},\"{$data}\"";
		$content = "<i style='color:{$cor};' class='fa fa-circle'>";
		if(count($endossado) > 0){
			$title .= " (endossado)";
			$func .= ", true";
			$content .= "(E)";
		}
		$func .= ")";
		if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])){
			$func = "";
		}
		$content .= "</i>";
		
		$retorno = "<a title='".$title."' onclick='{$func}'>{$content}</a>";
		return $retorno;
	}