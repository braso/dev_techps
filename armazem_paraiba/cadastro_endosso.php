	<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/
	date_default_timezone_set('America/Sao_Paulo');

	include "funcoes_ponto.php"; // conecta.php importado dentro de funcoes_ponto

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
						if($qtdDias->d > 1){
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
		global $totalResumo;

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
		global $totalResumo;

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
		
		for(
			$date = $dataDe;
			date_diff($date, $dataAte)->days >= 0 && !(date_diff($date, $dataAte)->invert);
			$date = date_add($date, DateInterval::createFromDateString("1 day"))
		){
			diaDetalhePonto($motorista, $date->format("Y-m-d"));
		}

		
		$saldoBruto = operarHorarios([$saldoAnterior, $totalResumo["diffSaldo"]], "+");
		$aPagar = calcularHorasAPagar($saldoBruto, $totalResumo["he50"], $totalResumo["he100"], "999:59", ($motorista["para_tx_pagarHEExComPerNeg"]?? "nao"));
		[$totalResumo["he50APagar"], $totalResumo["he100APagar"]] = $aPagar;

		$totalResumo["saldoAnterior"] = $saldoAnterior;
		$totalResumo["saldoBruto"] = $saldoBruto;
		
		$_POST["extraPago"] = operarHorarios([$totalResumo["saldoBruto"], $totalResumo["he100APagar"]], "-");
		if($_POST["extraPago"][0] == "-"){
			$_POST["extraPago"] = "00:00";
		}
		set_status("O máximo que pode ser pago a este(a) funcionário(a) é: ".$_POST["extraPago"]);

		index();
		exit;
	}

	function cadastrar(){

		global $totalResumo, $CONTEX;
		
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

			if($motorista["para_tx_descFaltas"] == "sim"){
				$descFaltasNaoJustificadas = "00:00";
			}
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
						$row[0] = str_replace("class='fa fa-circle'>", "class='fa fa-circle'>(E)", $row[0]);
					}
					foreach($row as $key => &$value){
						if($key == "diffSaldo"){
							continue;
						}
						if ($value == "00:00") {
							$value = "";
						}
					}
					$rows[] = $row;
				}
			
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
				if(isset($motorista["enti_tx_banco"])){
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

			$saldoBruto = operarHorarios([$saldoAnterior, $totalResumo["diffSaldo"]], "+");
			$aPagar = calcularHorasAPagar($saldoBruto, $totalResumo["he50"], $totalResumo["he100"], (!empty($_POST["extraPago"])? $_POST["extraPago"]: "00:00"), ($motorista["para_tx_pagarHEExComPerNeg"]?? "nao"));
			$totalResumo["desconto_manual"] = ($_POST["descontar_horas"] == "sim")? $_POST["horas_a_descontar"]: "00:00";
			$totalResumo["desconto_faltas_nao_justificadas"] = $descFaltasNaoJustificadas;
			$saldoFinal = operarHorarios([$saldoBruto, $aPagar[0], $aPagar[1], "-".$totalResumo["desconto_manual"], "-".$totalResumo["desconto_faltas_nao_justificadas"]], "+");

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
			foreach($totalResumo as $key => $value){
				$totalResumo[$key] = "00:00";
			}
		}

		$baseErrMsg = "<br><br>ERRO(S):<br>";
		$errorMsg = $baseErrMsg;

		$baseSucMsg = "<br><br>Registro(s) inserido(s) com sucesso!<br><br>H.E. Semanal a pagar:<br>";
		$successMsg = $baseSucMsg;

		foreach($novosEndossos as $novoEndosso){
			if(isset($novoEndosso["error"])){
				$errorMsg .= $novoEndosso["errorMsg"]."<br>";
				continue;
			}
			
			$successMsg .= "- [".$novoEndosso["endo_tx_matricula"]."] ".$novoEndosso["endo_tx_nome"].": ".$novoEndosso["totalResumo"]["he50APagar"]."<br>";
			//* Salvando arquivo e cadastrando no banco de dados

				$filename = md5($novoEndosso["endo_nb_entidade"].$novoEndosso["endo_tx_mes"]);
				$novoEndosso["totalResumo"] = json_encode($novoEndosso["totalResumo"]);
				$novoEndosso["totalResumo"] = str_replace("<\/", "</", $novoEndosso["totalResumo"]);
				$path = $_SERVER["DOCUMENT_ROOT"].$CONTEX["path"]."/arquivos/endosso";
				if(!is_dir($path)){
					mkdir($path);
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
				fputcsv($file, array_keys($novoEndosso));
				fputcsv($file, array_values($novoEndosso));
				fclose($file);
				
				unset($novoEndosso["endo_tx_pontos"]);
				unset($novoEndosso["totalResumo"]);
				unset($novoEndosso["endo_tx_nome"]);
				
				inserir("endosso", array_keys($novoEndosso), array_values($novoEndosso));
			//*/
		}

		$statusMsg = ($successMsg != $baseSucMsg? $successMsg: "").($errorMsg != $baseErrMsg? $errorMsg: "");
		set_status($statusMsg);

		index();
		exit;
	}

	function index(){
		if(is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
			$_POST["returnValues"] = json_encode([
				"HTTP_REFERER" => $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/index.php"
			]);
			voltar();
		}
		
		global $CONTEX;

		cabecalho("Cadastro de Endosso");

		$extra_bd_motorista = " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')";
		if($_SESSION["user_tx_nivel"] != "Super Administrador"){
			$extra_bd_motorista .= " AND enti_nb_empresa = ".$_SESSION["user_tx_emprCnpj"];
		}
		if(!empty($_POST["empresa"])){
			$extra_bd_motorista .= " AND enti_nb_empresa = ".$_POST["empresa"];
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
			"<div class='col-sm-3 margin-bottom-5' style='min-width:200px; width:100%'>
				<label>"."Descontar atrasos?"."</label><br>
				<label class='radio-inline'>
					<input type='radio' id='descSim' name='descontar_horas' value='sim' ".(!empty($_POST["descontar_horas"]) && $_POST["descontar_horas"] == "sim"? "checked": "")."> Sim
				</label>
				<label class='radio-inline'>
          			<input type='radio' id='descNao' name='descontar_horas' value='nao'".((empty($_POST["descontar_horas"]) || $_POST["descontar_horas"] == "nao")? "checked": "")."> Não
				</label>
			</div>
			<div id='descEmFolha' class='col-sm-3 margin-bottom-20' style='display: ".(!empty($_POST["descontar_horas"]) && $_POST["descontar_horas"] == "sim"? "block": "none").";'>
				<div>
					<label>Horas a descontar</label>
					<input class='form-control input-sm campo-fit-content' name='horas_a_descontar' autocomplete='off' value = '{$_POST["horas_a_descontar"]}'>
				</div>
				<div style='margin-left: 5px;width: max-content; font-size: 12px;'><a onclick='pegarSaldoPeriodoNegativo()'>Inserir todo o saldo negativo do período.</a></div>
			</div>"
		;

		$fields = [
			combo_net("Funcionário", "busca_motorista", $_POST["busca_motorista"]?? "", 4, "entidade", "", $extra_bd_motorista, "enti_tx_matricula"),
			campo_data("De*", "data_de", ($_POST["data_de"]?? ""), 2),
			campo_data("Ate*", "data_ate", ($_POST["data_ate"]?? ""), 2),
			$camposHE,
			$camposDesconto

		];
		if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
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
		
		rodape();

		echo 
			"<script>"
				."appPath = '".($_ENV["APP_PATH"]?? "")."';"
				."contexPath = '".($CONTEX["path"]?? "")."';\n"
		;
		include "js/cadastro_endosso.js";
		echo "</script>";
	}
