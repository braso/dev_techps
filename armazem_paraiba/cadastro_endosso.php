<?php

		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");

	date_default_timezone_set('America/Sao_Paulo');

	include "funcoes_ponto.php"; // conecta.php importado dentro de funcoes_ponto
	include_once "check_permission.php";

	function conferirErros($modo = 0, $idMotorista = null): string{
		//Modo = 0: Conferência geral dos parâmetros do formulário.
		//Modo = 1: Conferência do caso de um motorista específico.
		
		if($modo == 0){
			//Conferir se os campos obrigatórios estão preenchidos corretamente{
				$baseErrMsg = "Há campos obrigatórios incorretos: ";
				$errorMsg = $baseErrMsg;
				if(!isset($_POST["empresa"])){
					if(!isset($_POST["busca_motorista"])){
						if($_SESSION["user_tx_nivel"] == "Super Administrador"){
							$_POST["errorFields"][] = "empresa";
							$errorMsg .= "Empresa, ";
						}else{
							$_POST["empresa"] = $_SESSION["user_nb_empresa"];
						}
					}else{
						$empresa = mysqli_fetch_assoc(query(
							"SELECT empresa.empr_nb_id FROM empresa JOIN entidade ON empr_nb_id = enti_nb_empresa WHERE enti_nb_id = ".$_POST["busca_motorista"]." LIMIT 1;"
						));
						$_POST["empresa"] = $empresa["empr_nb_id"];
					}
				}
				if(empty($_POST["data_de"]) || empty($_POST["data_ate"]) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $_POST["data_de"]) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $_POST["data_ate"])){
					$errorMsg .= "Data, ";
					$_POST["errorFields"][] = "data_de";
					$_POST["errorFields"][] = "data_ate";
				}
				if($errorMsg != $baseErrMsg){
					return "ERRO: ".substr($errorMsg, 0, strlen($errorMsg)-2);
				}
	
				if(!empty($_POST["extraPago"]) && !preg_match("/^\d{2,4}:\d{2}$/", $_POST["extraPago"])){
					$errorMsg = "Máximo de horas preenchido incorretamente. ";
					$_POST["errorFields"][] = "extraPago";
				}
				if(!empty($_POST["horas_a_descontar"]) && !preg_match("/^\d{2,4}:\d{2}$/", $_POST["horas_a_descontar"])){
					$errorMsg = "Horas a descontar preenchido incorretamente. ";
					$_POST["errorFields"][] = "horas_a_descontar";
				}
				if($errorMsg != $baseErrMsg){
					return "ERRO: ".substr($errorMsg, 0, strlen($errorMsg)-2);
				}
			//}
	
			//Conferir se o endosso tem mais de um mês{
				$difference = strtotime($_POST["data_ate"]) - strtotime($_POST["data_de"]);
				$dateDiff = date_diff(DateTime::createFromFormat("Y-m-d", $_POST["data_ate"]), DateTime::createFromFormat("Y-m-d", $_POST["data_de"]));
				if($dateDiff->m > 0){
					$errorMsg = "Não é possível cadastrar um endosso com mais de um mês.";
				}
				unset($difference);
			//}

			//Conferir se o endosso passa da data atual{
				if($_POST["data_de"] >= date("Y-m-d") || $_POST["data_ate"] >= date("Y-m-d")){
					$errorMsg = "Não é possível cadastrar um endosso que inclua a data atual ou datas futuras.";
					$_POST["errorFields"][] = "data_de";
					$_POST["errorFields"][] = "data_ate";
				}
			//}

			if($errorMsg != $baseErrMsg){
				return "ERRO: ".$errorMsg;
			}
		}elseif($modo == 1){
			if(empty($idMotorista)){
				return "ERRO: parâmetros de conferirErros() incorretos.  ";
			}
			
			$motorista = mysqli_fetch_assoc(query(
				"SELECT * FROM entidade "
				." WHERE enti_tx_status = 'ativo'"
					." AND enti_nb_id = ".$idMotorista.";"
			));

			$motErrMsg = "";

			//Conferir se está tentando endossar meses anteriores ao cadastro do motorista{
				$dataCadastro = new DateTime($motorista["enti_tx_admissao"]." 00:00:00");
				if($_POST["data_de"] < $dataCadastro->format("Y-m-01")){
					$motErrMsg = "Não é possível cadastrar um endosso antes do mês de admissão (".$dataCadastro->format("m/Y")."). ";
					$_POST["errorFields"][] = "data_de";
				}
			//}

			if(empty($motErrMsg)){
				//Conferir se está entrelaçado com outro endosso{
					$endossosMotorista = mysqli_fetch_assoc(query(
						"SELECT endo_tx_de, endo_tx_ate FROM endosso"
							." WHERE endo_nb_entidade = ".$motorista["enti_nb_id"].""
								." AND ("
									." (endo_tx_ate >= '".$_POST["data_de"]."')"
									." AND ('".$_POST["data_ate"]."' >= endo_tx_de)"
								." )"
								." AND endo_tx_status = 'ativo'"
							." LIMIT 1;"
					));
					if(!empty($endossosMotorista)){
						$endossosMotorista["endo_tx_de"]  = vsprintf("%02d/%02d/%04d", array_reverse(explode("-", $endossosMotorista["endo_tx_de"])));
						$endossosMotorista["endo_tx_ate"] = vsprintf("%02d/%02d/%04d", array_reverse(explode("-", $endossosMotorista["endo_tx_ate"])));
						$motErrMsg = "Já endossado de ".$endossosMotorista["endo_tx_de"]." até ".$endossosMotorista["endo_tx_ate"].".  ";
						$_POST["errorFields"][] = "data_de";
						$_POST["errorFields"][] = "data_ate";
					}
					unset($endossosMotorista);
				//}

				$possuiEndoPosterior = false;
			
				//Conferir se está tentando endossar antes de outro endosso{
					$possuiEndoPosterior = mysqli_fetch_all(query(
							"SELECT endo_tx_de FROM endosso"
								." WHERE endo_nb_entidade = '".$motorista["enti_nb_id"]."'"
									." AND endo_tx_status = 'ativo'"
								." ORDER BY endo_tx_de DESC"
								." LIMIT 1;"
					),MYSQLI_ASSOC);
					if((count($possuiEndoPosterior) > 0)){
						//Conferir se o endosso que está sendo feito vem antes do primeiro{
							if($_POST["data_ate"] < $possuiEndoPosterior[0]["endo_tx_de"]){
								$motErrMsg = "Já existe um endosso depois de ".vsprintf("%02d/%02d/%04d", array_reverse(explode("-", $_POST["data_ate"]))).".  ";
								$_POST["errorFields"][] = "data_ate";
							}
						//}
					}
					$possuiEndoPosterior = (count($possuiEndoPosterior) == 0);
				//}
			}

			

			if(empty($motErrMsg)){
				//Conferir se tem espaço entre o último endosso e o endosso atual{
					$ultimoEndosso = mysqli_fetch_all(
						query(
							"SELECT * FROM endosso
								WHERE endo_nb_entidade = '".$motorista["enti_nb_id"]."'
									AND endo_tx_ate < '".$_POST["data_de"]."'
									AND endo_tx_status = 'ativo'
								ORDER BY endo_tx_ate DESC
								LIMIT 1;"
						),
						MYSQLI_ASSOC
					);

					if(is_array($ultimoEndosso) && count($ultimoEndosso) > 0 && !$possuiEndoPosterior){ //Se possui um último Endosso
						$ultimoEndosso = $ultimoEndosso[0];
						$ultimoEndosso["endo_tx_ate"] = DateTime::createFromFormat("Y-m-d", $ultimoEndosso["endo_tx_ate"]);
						$dataDe = DateTime::createFromFormat("Y-m-d", $_POST["data_de"]);
						$qtdDias = date_diff($ultimoEndosso["endo_tx_ate"], $dataDe);
						if($qtdDias->days > 1){
							$motErrMsg = "Há um tempo não endossado entre ".$ultimoEndosso["endo_tx_ate"]->format("d/m/Y")." e ".$dataDe->format("d/m/Y").".  ";
							$_POST["errorFields"][] = "data_de";
						}
					}else{ //Se é o primeiro endosso sendo feito para este motorista
						if(isset($motorista["enti_tx_banco"])){
							$ultimoEndosso["endo_tx_saldo"] = $motorista["enti_tx_banco"];
						}else{
							$ultimoEndosso["endo_tx_saldo"] = "00:00";
						}
					}
				//}
			}

			if(!empty($motErrMsg)){
				return $motErrMsg;
			}
		}else{
			return "Modo incorreto em conferirErros.";
		}

		//String vazia significa "Sem Erros";
		return "";
	}

	function pegarSaldoPeriodoNegativo(){
		$err = conferirErros();
		if(!empty($err)){
			set_status($err);
			index();
			exit;	
		}

		if(empty($_POST["busca_motorista"])){
			set_status("ERRO: Insira o motorista para consultar seu saldo.");
			index();
			exit;
		}

		$err = conferirErros(1, $_POST["busca_motorista"]);
		if(!empty($err)){
			set_status("ERRO: ".$err);
			index();
			exit;
		}

		$motorista = mysqli_fetch_assoc(query(
			"SELECT * FROM entidade
			 LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
			 LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
			 LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
			 WHERE enti_tx_status = 'ativo'
				 AND enti_nb_id = '{$_POST["busca_motorista"]}'
			 LIMIT 1;"
		));

		$descFaltasNaoJustificadas = "00:00";
		$saldoRestante = "00:00";
		for($dia = new DateTime($_POST["data_de"]); $dia <= new DateTime($_POST["data_ate"]); $dia->add(DateInterval::createFromDateString("1 day"))){
			$row = diaDetalhePonto($motorista, $dia->format("Y-m-d"));
			if($motorista["para_tx_descFaltas"] == "sim" && strpos($row["inicioJornada"], "Batida início de jornada não registrada!")){
				$descFaltasNaoJustificadas = operarHorarios([$descFaltasNaoJustificadas, $row["jornadaPrevista"]], "+");
			}else{
				$saldoRestante = operarHorarios([$saldoRestante, $row["diffSaldo"]], "+");
			}
		}
		
		if($saldoRestante[0] == "-"){
			$_POST["horas_a_descontar"] = $saldoRestante;
		}else{
			$_POST["horas_a_descontar"] = "00:00";
		}

		if($motorista["para_tx_descFaltas"] == "sim" && $descFaltasNaoJustificadas != "00:00"){
			set_status("Além desse, há um período de {$descFaltasNaoJustificadas} que já será descontado por faltas não justificadas.");
		}

		index();
		exit;
	}

	function pegarSaldoTotal(){

		
		if(empty($_POST["busca_motorista"])){
			set_status("ERRO: Insira o motorista para consultar seu saldo.");
			index();
			exit;
		}

		$err = conferirErros(1, $_POST["busca_motorista"]);

		if(!empty($err)){
			set_status("ERRO: ".$err);
			index();
			exit;
		}

		$_POST["extraPago"] = "00:00";

		if(empty($_POST["busca_motorista"]) || empty($_POST["data_de"]) || empty($_POST["data_ate"])){
			set_status("ERRO: Insira funcionário e datas para inserir o saldo possível.");
			index();
			exit;
		}


		$motorista = mysqli_fetch_assoc(query(
			"SELECT * FROM entidade
			 LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
			 LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
			 LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
			 WHERE enti_tx_status = 'ativo'
				 AND enti_nb_id = '{$_POST["busca_motorista"]}'
			 LIMIT 1;"
		));


		$ultimoEndosso = mysqli_fetch_assoc(query(
			"SELECT enti_tx_matricula, endo_tx_filename FROM endosso "
				." JOIN entidade ON enti_nb_id = endo_nb_entidade"
				." WHERE endo_tx_status = 'ativo'"
					." AND endo_nb_entidade = ".$_POST["busca_motorista"]
				." ORDER BY endo_tx_ate DESC, endo_nb_id DESC"
				." LIMIT 1;"
		));

		
		if(!empty($ultimoEndosso["endo_tx_filename"]) && file_exists($_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/arquivos/endosso/".$ultimoEndosso["endo_tx_filename"].".csv")){
			$ultimoEndosso = lerEndossoCSV($ultimoEndosso["endo_tx_filename"]);
			$saldoAnterior = $ultimoEndosso["totalResumo"]["saldoFinal"];

			$dataDe = new DateTime($ultimoEndosso["endo_tx_ate"]);
			$dataDe->modify("+1 day");
		}else{
			$saldoAnterior = $motorista["enti_tx_banco"];
			$dataDe = new DateTime($_POST["data_de"]);
		}
		$dataAte = new DateTime($_POST["data_ate"]);
		
		$rows = [];


		$descFaltasNaoJustificadas = "00:00";

		for(
			$date = $dataDe;
			date_diff($date, $dataAte)->days >= 0 && !(date_diff($date, $dataAte)->invert);
			$date = date_add($date, DateInterval::createFromDateString("1 day"))
		){
			$row = diaDetalhePonto($motorista, $date->format("Y-m-d"));
			if($motorista["para_tx_descFaltas"] == "sim" && strpos($row["inicioJornada"], "Batida início de jornada não registrada!")){
				$descFaltasNaoJustificadas = operarHorarios([$descFaltasNaoJustificadas, $row["jornadaPrevista"]], "+");
			}

			$rows[] = $row;
		}

		

		$totalResumo = setTotalResumo(array_slice(array_keys($rows[0]), 7));
		somarTotais($totalResumo, $rows);

		$diffSaldo = strip_tags($totalResumo["diffSaldo"]);
		$he50      = strip_tags($totalResumo["he50"]);
		$he100     = strip_tags($totalResumo["he100"]);
        $saldoBrutoParam = operarHorarios([$saldoAnterior, $diffSaldo], "+");

		[$totalResumo["he50APagar"], $totalResumo["he100APagar"]] = calcularHorasAPagar($diffSaldo, $saldoBrutoParam, $he50, $he100, "999:59", ($motorista["para_tx_pagarHEExComPerNeg"]?? "nao"));

        $saldoBruto = $saldoBrutoParam;

		$totalResumo["saldoAnterior"] = $saldoAnterior;
            $totalResumo["saldoBruto"]       = $saldoBruto;
		
		$_POST["extraPago"] = operarHorarios([$totalResumo["saldoBruto"], $descFaltasNaoJustificadas], "-");
		if($_POST["extraPago"][0] == "-"){
			$_POST["extraPago"] = "00:00";
		}
		set_status("O máximo que pode ser pago a este(a) funcionário(a) é: ".$_POST["extraPago"]);

		index();
		exit;
	}

function cadastrar(){

		global $CONTEX;
		
		$err = conferirErros();
		if(!empty($err)){
			set_status($err);
			index();
			exit;
		}


		if(empty($_POST["endosso_confirmado"])){
			//Criar formulário de confirmação{
				$formConfirmacao = 
					"var formConfirmacao = document.createElement('form');"
					."formConfirmacao.name = 'formConfirmacao';"
					."formConfirmacao.method = 'post';"
					."formConfirmacao.style.display = 'none';"
					."formConfirmacao.action = '".$_SERVER["REQUEST_URI"]."';";
				foreach($_POST as $key => $value){
					if($key == 'acao'){
						continue;
					}

					$formConfirmacao .= 
						"input = document.createElement('input');"
						."input.name = '".$key."';"
						."input.setAttribute('value', '".$value."');"
						."formConfirmacao.appendChild(input);"
					;
				}
				echo
    				"<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Document</title></head><body></body></html>"
    				."<script>"
    					."var confirmado = confirm('O endosso, uma vez realizado, é irreversível. Deseja continuar?');"
    					.$formConfirmacao
						."input = document.createElement('input');"
    					."input.name = 'endosso_confirmado';"
    					."input.setAttribute('value', confirmado);"
    					."formConfirmacao.appendChild(input);"
						
						."input = document.createElement('input');"
    					."input.name = 'acao';"
    					."input.setAttribute('value', 'cadastrar()');"
    					."formConfirmacao.appendChild(input);"
    					
						."document.body.appendChild(formConfirmacao);"
						."formConfirmacao.submit();"
    				."</script>"
    			;
    			exit;
			//}
		}elseif($_POST["endosso_confirmado"] == "false"){
		    index();
		    exit;
		}
		unset($_POST["endosso_confirmado"], $_POST["acao"]);

		$motoristas = mysqli_fetch_all(query(
			"SELECT * FROM entidade
				LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
				LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
				LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
				WHERE enti_tx_status = 'ativo'
					AND enti_nb_empresa = {$_POST["empresa"]}
					".((!empty($_POST["busca_motorista"]))? " AND enti_nb_id = {$_POST["busca_motorista"]}": "")."
				ORDER BY enti_tx_nome ASC;"
		), MYSQLI_ASSOC);

		$novosEndossos = [];

		foreach($motoristas as $motorista){
			$motErrMsg = conferirErros(1, $motorista["enti_nb_id"]);
			if(!empty($motErrMsg)){
				$novoEndosso = [
					"endo_nb_entidade" 	=> $motorista["enti_nb_id"],
					"endo_tx_matricula" => $motorista["enti_tx_matricula"],
					"error" 			=> True,
					"errorMsg" 			=> "[".$motorista["enti_tx_matricula"]."] ".$motorista["enti_tx_nome"].": ".substr($motErrMsg, 0, strlen($motErrMsg)-2)
				];
				$novosEndossos[] = $novoEndosso;
				continue;
			}

			$descFaltasNaoJustificadas = "00:00";
			//Pegar dados dos dias{
				$rows = [];
				$dateDiff = date_diff(DateTime::createFromFormat("Y-m-d", $_POST["data_ate"]), DateTime::createFromFormat("Y-m-d", $_POST["data_de"]));
				for ($i = 0; $i <= $dateDiff->d; $i++) {
					$dataVez = strtotime($_POST["data_de"]);
					$dataVez = date("Y-m-d", $dataVez+($i*60*60*24));
					$aDetalhado = diaDetalhePonto($motorista, $dataVez);

					
					$row = array_merge([verificaTolerancia($aDetalhado["diffSaldo"], $dataVez, $motorista["enti_nb_id"])], $aDetalhado);
					if($motorista["para_tx_descFaltas"] == "sim" && strpos($row["inicioJornada"], "Batida início de jornada não registrada!")){
						$descFaltasNaoJustificadas = operarHorarios([$descFaltasNaoJustificadas, $row["jornadaPrevista"]], "+");
					}
					if(is_int(strpos($row[0], "Ajuste de Ponto"))){
						$row[0] = str_replace("Ajuste de Ponto", "Ajuste de Ponto(endossado)", $row[0]);
						$row[0] = str_replace("glyphicon glyphicon-pencil'>", "glyphicon glyphicon-pencil'>(E)", $row[0]);
					}

					foreach($row as $key => &$value){
						if ($value == "00:00" && $key != "diffSaldo"){
							$value = "";
						}
					}
					$rows[] = $row;
				}
				$totalResumo = setTotalResumo(array_slice(array_keys($rows[0]), 7));
				somarTotais($totalResumo, $rows);
			//}
			

			$ultimoEndosso = mysqli_fetch_assoc(query(
				"SELECT * FROM endosso
					WHERE endo_tx_status = 'ativo'
						AND endo_nb_entidade = '{$motorista["enti_nb_id"]}'
						AND endo_tx_ate < '{$_POST["data_de"]}'
					ORDER BY endo_tx_ate DESC
					LIMIT 1;"
			));

			if(empty($ultimoEndosso)){
				if(!empty($motorista["enti_tx_banco"])){
					$ultimoEndosso["endo_tx_saldo"] = $motorista["enti_tx_banco"];
				}else{
					$ultimoEndosso["endo_tx_saldo"] = "00:00";
				}
			}
			$saldoAnterior = $ultimoEndosso["endo_tx_saldo"];
			if(!empty($ultimoEndosso["endo_tx_filename"]) && file_exists($_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/arquivos/endosso/".$ultimoEndosso["endo_tx_filename"].".csv")){
				$ultimoEndossoCSV = lerEndossoCSV($ultimoEndosso["endo_tx_filename"]);
				$saldoAnterior = $ultimoEndossoCSV["totalResumo"]["saldoFinal"];
			}
				
			//Calculando datas de início e fim do ciclo{
				$aEndosso = mysqli_fetch_array(query(
					"SELECT endo_tx_dataCadastro, endo_tx_ate, endo_tx_max50APagar FROM endosso 
						WHERE endo_nb_entidade = '".$motorista["enti_nb_id"]."'"
				), MYSQLI_BOTH);
				
				if(!empty($motorista["para_nb_qDias"]) && !empty($aEndosso)){
					$dataCicloProx = strtotime($motorista["para_tx_inicioAcordo"]);
					while($dataCicloProx < strtotime($aEndosso["endo_tx_ate"])){
						$dataCicloProx += intval($motorista["para_nb_qDias"])*60*60*24;
					}
					$dataCicloAnt = $dataCicloProx - intval($motorista["para_nb_qDias"])*60*60*24;
	
					$dataCicloProx = date("Y-m-d", $dataCicloProx);
					$dataCicloAnt  = date("Y-m-d", $dataCicloAnt);
				}else{
					$dataCicloProx = "--/--/----";
					$dataCicloAnt  = "--/--/----";
				}
			//}

    $diffSaldo = strip_tags($totalResumo["diffSaldo"]);
    $he50      = strip_tags($totalResumo["he50"]);
    $he100     = strip_tags($totalResumo["he100"]);
    $saldoBruto = operarHorarios([$saldoAnterior, $diffSaldo], "+");
    $max50Auto = operarHorarios([$saldoBruto, $descFaltasNaoJustificadas], "-");
    if($max50Auto[0] == "-"){ $max50Auto = "00:00"; }
    $pagarExtras = (!empty($_POST["pagar_horas"]) && $_POST["pagar_horas"] == "sim");
    if(!$pagarExtras){
        $max50Auto = "00:00";
        $_POST["extraPago"] = "00:00";
    }else{
        $_POST["extraPago"] = $max50Auto;
    }

    $saldoPeriodoParaCalculo = $diffSaldo;
    // Ensure diffSaldo is treated as 00:00 if it is equivalent
    $diffSaldoClean = operarHorarios([$diffSaldo], "+");
    if($diffSaldoClean == "00:00" && $saldoBruto[0] != "-"){
        $saldoPeriodoParaCalculo = $saldoBruto;
    }

    if($pagarExtras){
        $aPagar = calcularHorasAPagar($saldoPeriodoParaCalculo, $saldoBruto, $he50, $he100, $max50Auto, ($motorista["para_tx_pagarHEExComPerNeg"]?? "nao"));
    }else{
        $aPagar = ["00:00", "00:00"];
    }
    
    $saldoFinal = operarHorarios([$saldoBruto, "-".$aPagar[0], "-".$aPagar[1]], "+");
			
		if($diffSaldo[0] == "-"){
			$saldoPossivelDescontar = operarHorarios([$diffSaldo, $descFaltasNaoJustificadas], "+");
				$saldoPossivelDescontar = operarHorarios([$saldoPossivelDescontar, "-00:01"], "*");
			}else{
				$saldoPossivelDescontar = "00:00";
			}
			
			if($saldoPossivelDescontar != "00:00" && $_POST["descontar_horas"] == "sim"){
				$totalResumo["desconto_manual"] = (operarHorarios([$_POST["horas_a_descontar"], $saldoPossivelDescontar], "-")[0] == "-")?
					$_POST["horas_a_descontar"]:
					$saldoPossivelDescontar
				;

				$saldoFinal = operarHorarios([$saldoFinal, $aPagar[1]], "+");
			}else{
				$totalResumo["desconto_manual"] = "00:00";
			}

            $saldoFinal = operarHorarios([$saldoFinal, $totalResumo["desconto_manual"]], "+");
            if($_POST["zerarSaldoNegativo"] == "sim" && $saldoFinal[0] == "-"){
                $totalResumo["desconto_manual"] = operarHorarios([$totalResumo["desconto_manual"], $saldoFinal], "+");
                $saldoFinal = "00:00";
            }


			$totalResumo["desconto_faltas_nao_justificadas"] = $descFaltasNaoJustificadas;
			$totalResumo["saldoAnterior"] 	= $saldoAnterior;
			$totalResumo["saldoBruto"] 		= $saldoBruto;
			$totalResumo["saldoFinal"] 		= $saldoFinal;
			$totalResumo["he50APagar"] 		= $aPagar[0];
			$totalResumo["he100APagar"] 	= $aPagar[1];

			$novoEndosso = [
				"endo_nb_entidade" 		  => $motorista["enti_nb_id"],
				"endo_tx_nome" 			  => $motorista["enti_tx_nome"],
				"endo_tx_matricula" 	  => $motorista["enti_tx_matricula"],
				"endo_tx_mes" 			  => substr($_POST["data_de"], 0, 8)."01",
				"endo_tx_de" 			  => $_POST["data_de"],
				"endo_tx_ate" 			  => $_POST["data_ate"],
				"endo_tx_dataCadastro" 	  => date("Y-m-d H:i:s"),
				"endo_nb_userCadastro" 	  => $_SESSION["user_nb_id"],
				"endo_tx_status" 		  => "ativo",
				"endo_tx_max50APagar" 	  => $_POST["extraPago"],
				"endo_tx_pontos"		  => $rows,
				"totalResumo"			  => $totalResumo
			];

			$novoEndosso["endo_tx_pontos"] = json_encode($novoEndosso["endo_tx_pontos"]);
			$novoEndosso["endo_tx_pontos"] = str_replace("<\/", "</", $novoEndosso["endo_tx_pontos"]);

			$novosEndossos[] = $novoEndosso;
		}

		$baseErrMsg = "<br><br>ERRO(S):<br>";
		$errorMsg = $baseErrMsg;

		$baseSucMsg = ["Registros inseridos com sucesso.<br><br><br>
		<div class='table-responsive' style='justify-items: center'>
			<table class='table w-auto text-xsmall bold table-bordered table-striped table-condensed flip-content table-hover compact' style='width: fit-content;'>
				<thead>
					<tr>
						<th>Matrícula</th>
						<th>Nome</th>
						<th>Anterior</th>
						<th>Período</th>
						<th>Bruto</th>
						<th>Pago</th>
						<th>Descontado</th>
						<th>Final</th>
					</tr>
				</thead>
				<tbody>", 
				"", 
				"</tbody></table></div>"
		];
		$successMsg = $baseSucMsg;

		foreach($novosEndossos as $novoEndosso){
			if(isset($novoEndosso["error"])){
				$errorMsg .= $novoEndosso["errorMsg"]."<br>";
				continue;
			}
			$successMsg[1] .= 
				"<tr>
					<td>{$novoEndosso["endo_tx_matricula"]}</td>
					<td>{$novoEndosso["endo_tx_nome"]}</td>
					<td>{$novoEndosso["totalResumo"]["saldoAnterior"]}</td>
					<td>{$novoEndosso["totalResumo"]["diffSaldo"]}</td>
					<td>{$novoEndosso["totalResumo"]["saldoBruto"]}</td>
					<td>".operarHorarios([$novoEndosso["totalResumo"]["he50APagar"], $novoEndosso["totalResumo"]["he100APagar"]], "+")."</td>
					<td>".operarHorarios([$novoEndosso["totalResumo"]["desconto_manual"], $novoEndosso["totalResumo"]["desconto_faltas_nao_justificadas"]], "+")."</td>
					<td>{$novoEndosso["totalResumo"]["saldoFinal"]}</td>
				</tr>"
			;

			//* Salvando arquivo e cadastrando no banco de dados

				$filename = md5($novoEndosso["endo_nb_entidade"].$novoEndosso["endo_tx_mes"]);
				$novoEndosso["totalResumo"] = json_encode($novoEndosso["totalResumo"]);
				$novoEndosso["totalResumo"] = str_replace("<\/", "</", $novoEndosso["totalResumo"]);
				$path = $_SERVER["DOCUMENT_ROOT"].$CONTEX["path"]."/arquivos/endosso";
				if(!is_dir($path)){
					if (!mkdir($path, 0777, true)) {
						$errorMsg .= "Erro ao criar diretório: $path<br>";
						continue; // Pula para o próximo se não conseguir criar a pasta
					}
				}

				if(file_exists($path."/".$filename.".csv")){
					$version = 2;
					while(file_exists($path."/".$filename."_".strval($version).".csv")){
						$version++;
					}
					$filename = $filename."_".strval($version);
				}

				$novoEndosso["endo_tx_filename"] = $filename;
				$file = fopen($path."/".$filename.".csv", "w");

				if($file){
					fputcsv($file, array_keys($novoEndosso));
					fputcsv($file, array_values($novoEndosso));
					fclose($file);

					// Só insere no banco se o arquivo foi criado com sucesso
					unset($novoEndosso["endo_tx_pontos"]);
					unset($novoEndosso["totalResumo"]);
					unset($novoEndosso["endo_tx_nome"]);
					
					inserir("endosso", array_keys($novoEndosso), array_values($novoEndosso));
				}else{
					$errorMsg .= "Erro ao abrir o arquivo para escrita: $filename<br>";
				};
				
		}

		$statusMsg = ($successMsg != $baseSucMsg? implode("", $successMsg): "").($errorMsg != $baseErrMsg? $errorMsg: "");
		set_status($statusMsg);

		index();
		exit;
}

function excluirEndosso(){
    $just = trim($_POST["justificativaExclusao"] ?? "");
    if($just === ""){
        set_status("ERRO: Justificativa de exclusão é obrigatória.");
        index();
        exit;
    }
    $campos = [
        "endo_tx_status",
        "endo_tx_dataExclusao",
        "endo_tx_justificativaExclusao",
        "endo_nb_userExclusao"
    ];
    $valores = [
        "inativo",
        date("Y-m-d H:i:s"),
        $just,
        $_SESSION["user_nb_id"] ?? null
    ];
    atualizar("endosso", $campos, $valores, $_POST["id"]);
    index();
    exit;
}

function modificarEndosso(){
    global $a_mod;
    $a_mod = carregar("endosso", $_POST["id"]);
    visualizarEndosso();
    exit;
}

function visualizarEndosso(){
    global $a_mod;
    cabecalho("Visualizar Endosso");
    $ent = mysqli_fetch_assoc(query(
        "SELECT enti_tx_matricula, enti_tx_nome FROM entidade WHERE enti_nb_id = ".$a_mod["endo_nb_entidade"]." LIMIT 1;"
    ));
    $c = [
        texto("Matrícula", $ent["enti_tx_matricula"]?? "", 2),
        texto("Funcionário", $ent["enti_tx_nome"]?? "", 4),
        texto("Período", vsprintf("%02d/%02d/%04d", array_reverse(explode("-", $a_mod["endo_tx_de"])))." a ".vsprintf("%02d/%02d/%04d", array_reverse(explode("-", $a_mod["endo_tx_ate"]))), 3),
        texto("Data de Endosso", date("d/m/Y H:i", strtotime($a_mod["endo_tx_dataCadastro"])), 3),
        texto("Status", ucfirst($a_mod["endo_tx_status"]), 2)
    ];
    $botao = [
        criarBotaoVoltar("cadastro_endosso.php")
    ];
    echo abre_form("Detalhes do Endosso");
    echo linha_form($c);
    echo fecha_form($botao);
    rodape();
}

function index(){
		
		
		global $CONTEX;

		cabecalho("Cadastro de Endosso");

		$condicoes_motorista = "AND enti_tx_status = 'ativo' AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')";
		if($_SESSION["user_tx_nivel"] != "Super Administrador" && !temPermissaoMenu('/cadastro_empresa.php')){
			$condicoes_motorista .= " AND enti_nb_empresa = ".$_SESSION["user_nb_empresa"];
		}

		$_POST["empresa"] = $_POST["empresa"]?? $_SESSION["user_nb_empresa"];
		if(!empty($_POST["empresa"])){
			$condicoes_motorista .= " AND enti_nb_empresa = ".$_POST["empresa"];
		}

		$condSubSetor = " ORDER BY sbgr_tx_nome ASC";
		if (!empty($_POST["busca_setor"])) {
			$condSubSetor = " AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"])." ORDER BY sbgr_tx_nome ASC";
		}
		$subsetorExtra = empty($_POST["busca_setor"]) ? "disabled" : "";

		$hasSubsetor = 0;
		if (!empty($_POST["busca_setor"])) {
			$row = mysqli_fetch_assoc(query(
				"SELECT COUNT(*) AS c FROM sbgrupos_documentos WHERE sbgr_tx_status = 'ativo' AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"])." LIMIT 1;"
			));
			$hasSubsetor = (int)($row["c"]??0);
		}

		if (!empty($_POST["busca_setor"])) {
			$condicoes_motorista .= " AND enti_setor_id = ".intval($_POST["busca_setor"]);
		}
		if (!empty($_POST["busca_subsetor"])) {
			$condicoes_motorista .= " AND enti_subSetor_id = ".intval($_POST["busca_subsetor"]);
		}
		if (!empty($_POST["busca_operacao"])) {
			$condicoes_motorista .= " AND enti_tx_tipoOperacao = ".intval($_POST["busca_operacao"]);
		}

		$camposHE = 
			"<div class='col-sm-3 margin-bottom-5' style='min-width:200px; width:100%;'>
				<label>"."Pagar Horas Extras"."</label><br>
				<label class='radio-inline'>
					<input type='radio' id='extraSim' name='pagar_horas' value='sim' ".(!empty($_POST["pagar_horas"]) && $_POST["pagar_horas"] == "sim"? "checked": "")."> Sim
				</label>
				<label class='radio-inline'>
          			<input type='radio' id='extraNao' name='pagar_horas' value='nao'".((empty($_POST["pagar_horas"]) || $_POST["pagar_horas"] == "nao")? "checked": "")."> Não
				</label>
			</div>
		
			<div id='max50APagar' class='col-sm-3 margin-bottom-20' style='display: ".(!empty($_POST["pagar_horas"]) && $_POST["pagar_horas"] == "sim"? "block": "none").";'>
				<div>
					<label>Máx. de HE Semanal a pagar</label>
					<input class='form-control input-sm campo-fit-content' name='extraPago' autocomplete='off' value = '".(!empty($_POST["extraPago"])? $_POST["extraPago"]:"")."'>
				</div>
				<div style='margin-left: 5px;width: max-content; font-size: 12px;'><a onclick='pegarSaldoTotal()'>Inserir todo o saldo possível.</a></div>
			</div>"
		;

		$_POST["horas_a_descontar"] = (!empty($_POST["horas_a_descontar"])? ($_POST["horas_a_descontar"][0] == "-"? substr($_POST["horas_a_descontar"], 1): $_POST["horas_a_descontar"]):"");
		$camposDesconto = 
			"<div class='col-sm-3 margin-bottom-5' style='min-width:200px;width:100%;display: flex;'>
				<div>
					<label>"."Descontar atrasos?"."</label><br>
					<label class='radio-inline'>
						<input type='radio' id='descSim' name='descontar_horas' value='sim' ".(!empty($_POST["descontar_horas"]) && $_POST["descontar_horas"] == "sim"? "checked": "")."> Sim
					</label>
					<label class='radio-inline'>
						<input type='radio' id='descNao' name='descontar_horas' value='nao'".((empty($_POST["descontar_horas"]) || $_POST["descontar_horas"] == "nao")? "checked": "")."> Não
					</label>
				</div>
				<div>
					<label>"."Zerar saldos negativos?"."</label><br>
					<label class='radio-inline'>
						<input type='radio' name='zerarSaldoNegativo' value='sim' ".(!empty($_POST["zerarSaldoNegativo"]) && $_POST["zerarSaldoNegativo"] == "sim"? "checked": "")."> Sim
					</label>
					<label class='radio-inline'>
						<input type='radio' name='zerarSaldoNegativo' value='nao'".((empty($_POST["zerarSaldoNegativo"]) || $_POST["zerarSaldoNegativo"] == "nao")? "checked": "")."> Não
					</label>
				</div>
			</div>
			<div id='descEmFolha' class='col-sm-3 margin-bottom-20' style='display: ".(!empty($_POST["descontar_horas"]) && $_POST["descontar_horas"] == "sim"? "block": "none").";'>
				<div>
					<label>Horas a descontar</label>
					<input class='form-control input-sm campo-fit-content' name='horas_a_descontar' autocomplete='off' value = '{$_POST["horas_a_descontar"]}'>
				</div>
				<div style='margin-left: 5px;width: max-content; font-size: 12px;'><a onclick='pegarSaldoPeriodoNegativo()'>Inserir todo o saldo negativo do período.</a></div>
			</div>"
		;

		$fields = [];
		$fields[] = combo_bd("!Setor", "busca_setor", (!empty($_POST["busca_setor"]) ? $_POST["busca_setor"] : ""), 2, "grupos_documentos", "onchange='this.form.submit()'");
		if ($hasSubsetor > 0) {
			$fields[] = combo_bd("!Subsetor", "busca_subsetor", (!empty($_POST["busca_subsetor"]) ? $_POST["busca_subsetor"] : ""), 2, "sbgrupos_documentos", $subsetorExtra." onchange='this.form.submit()'", (!empty($_POST["busca_setor"]) ? " AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"])." ORDER BY sbgr_tx_nome ASC" : " AND 1 = 0 ORDER BY sbgr_tx_nome ASC"));
		}
		$fields[] = combo_bd("!Cargo", "busca_operacao", (!empty($_POST["busca_operacao"]) ? $_POST["busca_operacao"] : ""), 2, "operacao", "onchange='this.form.submit()'");
		$fields[] = combo_net("Funcionário", "busca_motorista", $_POST["busca_motorista"]?? "", 4, "entidade", "", $condicoes_motorista, "enti_tx_matricula");
		$fields[] = campo_data("De*", "data_de", ($_POST["data_de"]?? ""), 2);
		$fields[] = campo_data("Ate*", "data_ate", ($_POST["data_ate"]?? ""), 2);
		 $fields[] = combo("Status", "busca_status", ($_POST["busca_status"] ?? "ativo"), 2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"], "onchange='this.form.submit()'");
        $fields[] = $camposHE;
        $fields[] = $camposDesconto;
       
		if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador")) || temPermissaoMenu('/cadastro_empresa.php')){
			array_unshift($fields, combo_net("Empresa*","empresa", !empty($_POST["empresa"])? $_POST["empresa"]: $_SESSION["user_nb_empresa"],4,"empresa", "onchange='selecionaMotorista(this.value)'"));
		}else{
			array_unshift($fields, campo_hidden("empresa", $_SESSION["user_nb_empresa"]));
		}
		$buttons = [
			botao("Cadastrar Endosso", "cadastrar", "", "", "", "", "btn btn-success")
		];

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons);

        $gridFields = [
            "CÓDIGO" => "endosso.endo_nb_id AS id",
            "MATRÍCULA" => "entidade.enti_tx_matricula AS matricula",
            "NOME" => "entidade.enti_tx_nome AS nome",
            "PERÍODO" => "CONCAT(DATE_FORMAT(endo_tx_de, '%d/%m/%Y'), ' a ', DATE_FORMAT(endo_tx_ate, '%d/%m/%Y')) AS periodo",
            "DATA DE ENDOSSO" => "DATE_FORMAT(endo_tx_dataCadastro, '%d/%m/%Y %H:%i') AS data_endosso",
            "STATUS" => "endo_tx_status AS status"
        ];
        if (!empty($_POST["busca_status"]) && $_POST["busca_status"] === "inativo") {
            $gridFields["JUSTIFICATIVA EXCLUSÃO"] = "endosso.endo_tx_justificativaExclusao AS justificativa_exclusao";
            $gridFields["EXCLUÍDO POR"] = "COALESCE((SELECT user_tx_nome FROM user WHERE user_nb_id = endosso.endo_nb_userExclusao LIMIT 1), '') AS user_exclusao";
            $gridFields["DATA EXCLUSÃO"] = "IF(endo_tx_dataExclusao IS NULL,'', DATE_FORMAT(endo_tx_dataExclusao, '%d/%m/%Y %H:%i')) AS data_exclusao";
        }
		$camposBusca = [
			"empresa" => "entidade.enti_nb_empresa",
			"busca_motorista" => "entidade.enti_nb_id",
			"busca_setor" => "entidade.enti_setor_id",
			"busca_subsetor" => "entidade.enti_subSetor_id",
			"busca_operacao" => "entidade.enti_tx_tipoOperacao",
			"busca_status" => "endosso.endo_tx_status"
		];
        $queryBase = (
            "SELECT ".implode(", ", array_values($gridFields)).
            " FROM endosso JOIN entidade ON endosso.endo_nb_entidade = entidade.enti_nb_id"
        );

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
            ["cadastro_endosso.php", "cadastro_endosso.php"],
            ["modificarEndosso()", "excluirEndosso()"]
        );
        // Substitui o handler de exclusão por SweetAlert com justificativa obrigatória
        $actions["functions"][1] =
            "$(document).off('click', '[class=\"glyphicon glyphicon-remove search-remove\"]');\n"
            . "$('[class=\"glyphicon glyphicon-remove search-remove\"]').on('click', async function(event){\n"
            . "  const id = $(event.target).parent().parent().children()[0].innerHTML;\n"
            . "  const { value: justificativa } = await Swal.fire({\n"
            . "    icon: 'warning',\n"
            . "    title: 'Excluir endosso',\n"
            . "    html: '<p>Ao excluir o endosso, a recuperação do arquivo torna-se irreversível.<br>Será obrigatório realizar um novo cadastro do endosso.</p>' +\n"
            . "          '<label style=\"display:block;text-align:left;margin-top:10px;\">Justificativa</label>' +\n"
            . "          '<textarea id=\"swal-justificativa\" class=\"swal2-textarea\" placeholder=\"Descreva o motivo\"></textarea>',\n"
            . "    showCancelButton: true,\n"
            . "    confirmButtonText: 'Excluir',\n"
            . "    cancelButtonText: 'Cancelar',\n"
            . "    focusConfirm: false,\n"
            . "    preConfirm: () => {\n"
            . "      const v = document.getElementById('swal-justificativa').value.trim();\n"
            . "      if(!v){ Swal.showValidationMessage('Informe a justificativa'); }\n"
            . "      return v;\n"
            . "    }\n"
            . "  });\n"
            . "  if(!justificativa){ return; }\n"
            . "  const form = document.createElement('form');\n"
            . "  form.setAttribute('method', 'post');\n"
            . "  form.setAttribute('action', 'cadastro_endosso.php');\n"
            . "  const idInput = document.createElement('input'); idInput.name='id'; idInput.value=id; form.appendChild(idInput);\n"
            . "  const acaoInput = document.createElement('input'); acaoInput.name='acao'; acaoInput.value='excluirEndosso()'; form.appendChild(acaoInput);\n"
            . "  const justInput = document.createElement('input'); justInput.name='justificativaExclusao'; justInput.value=justificativa; form.appendChild(justInput);\n"
            . "  const inputs = document.contex_form.getElementsByTagName('input');\n"
            . "  const selects = document.contex_form.getElementsByTagName('select');\n"
            . "  if(inputs != undefined){ for(key in inputs){ if(inputs[key].value != undefined && inputs[key].value != ''){ form.appendChild(inputs[key].cloneNode(true)); } } }\n"
            . "  if(selects != undefined){ for(key in selects){ if(selects[key].value != undefined && selects[key].value != ''){ form.appendChild(selects[key].cloneNode(true)); } } }\n"
            . "  document.getElementsByTagName('body')[0].appendChild(form);\n"
            . "  form.submit();\n"
            . "});\n"
            . "esconderInativar('glyphicon glyphicon-remove search-remove', 5);";
        $gridFields["actions"] = $actions["tags"];
        $jsFunctions = "const funcoesInternas = function(){".implode(" ", $actions["functions"])."}";
        echo gridDinamico("tabelaEndossos", $gridFields, $camposBusca, $queryBase, $jsFunctions);

		// Toggle Subsetor: só manipula se o campo existir
		echo "<script>
			$(document).ready(function(){
				function toggleSubsetor(){
					var elSub = $('#busca_subsetor');
					if(elSub.length === 0){ return; }
					var setor = $('select[name=\"busca_setor\"]').val();
					var elWrap = elSub.closest('.campo-fit-content');
					if(setor){
						elWrap.show();
						elSub.prop('disabled', false);
					}else{
						elWrap.hide();
						elSub.prop('disabled', true).val('');
					}
				}
				toggleSubsetor();
				$('select[name=\"busca_setor\"]').on('change', toggleSubsetor);
			});
		</script>";
		
        rodape();

		echo 
			"<script>"
				."appPath = '".($_ENV["APP_PATH"]?? "")."';"
				."contexPath = '".($CONTEX["path"]?? "")."';\n"
		;
		include "js/cadastro_endosso.js";
		echo "</script>";
	}
