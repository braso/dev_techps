<?php
    /* Modo Debug{
        ini_set("display_errors", 1);
        error_reporting(E_ALL);

		header("Expires: 01 Jan 2001 00:00:00 GMT");
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
    //}*/

	
	include_once "funcoes_ponto.php";

	$dominiosAutotrac = ["/comav"];
	if(!in_array($_ENV["CONTEX_PATH"], $dominiosAutotrac) && is_bool(strpos($_SERVER["HTTP_HOST"], "localhost"))){
		echo "Apenas empresas com Autotrac registrados podem utilizar este serviço.<br>";
		exit;
	}

	echo "Em desenvolvimento...<br>";
	// exit;

	//Puxar pontos da API{
		$apiUrl = "https://apimacrocomav.logsyncwebservice.techps.com.br/macros";		//URL da API
		$token = "0cb538f85dbe5cf630d03b4be8a61b77";									//Token de autenticação Bearer
		
		$ch = curl_init();
		// Configurações do cURL
		curl_setopt($ch, CURLOPT_URL, $apiUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Authorization: Bearer {$token}"
		]);
		
		$response = curl_exec($ch);														//Executa a requisição
		
		if (curl_errno($ch)) {															//Verifica se houve erro
			echo "Erro ao consumir a API: ".curl_error($ch);
			exit;
		}
		
		$pontos = json_decode($response, true);											//Decodifica o JSON da resposta
		
		curl_close($ch);																//Fecha a requisição cURL
		
		// Verifica se os dados são válidos
		if(empty($pontos) || !is_array($pontos)){
			echo "Nenhum dado válido retornado da API.";
			exit;
		}
	//}
		
	//Ordenar os pontos{
		$dates = [];
		$matriculas = [];
		foreach($pontos as $ponto){
			$datetime = DateTime::createFromFormat("d/m/Y H:i:s", $ponto["position_time"]);

			if(!empty($datetime)){
				$dates[] = $datetime->format("Y-m-d H:i:s");
			} else {
				$datetime = DateTime::createFromFormat("Y-m-d\TH:i:s.v\Z", $ponto["position_time"]);
				if(!empty($datetime)){
					$dates[] = $datetime->format("Y-m-d H:i:s");
				}else{
					error_log("Erro ao converter data: " . $ponto["position_time"]);
				}
			}
			$matriculas[] = $ponto["driver_cpf"];
		}
		array_multisort($matriculas, SORT_ASC, $dates, SORT_ASC, $pontos);
	//}

	// /*Cadastrar somente do primeiro motorista.{
		// $matriculaInicial = $pontos[0]["driver_cpf"];
	//}*/

	$macros = mysqli_fetch_all(query(
		"SELECT macr_tx_codigoExterno FROM macroponto
			WHERE macr_tx_status = 'ativo'
				AND macr_tx_fonte = 'autotrac'
			ORDER BY macr_tx_codigoExterno;"
	), MYSQLI_ASSOC);
	for($f = 0; $f < count($macros); $f++){
		$macros[$f] = $macros[$f]["macr_tx_codigoExterno"];
	}

	//"" = Ignorar
	//0 = Conferir se há um intervalo aberto antes para fechar.
	//valor > 0: Conferir se há um intervalo aberto antes para fechar + substituir pela macro de abertura com esse codigoInterno
	$relacaoMacros = [
		"50" => "1",	//"INICIO DE JORNADA",
		"51" => "0",	//"INICIO DE VIAGEM",
		"52" => "",		//"PARADA EVENTUAL",
		"53" => "0",	//"REINICIO DE VIAGEM",
		"54" => "0",	//"FIM DE VIAGEM",
		"55" => "",		//"SOL DE DESVIO DE ROTA",
		"56" => "",		//"SOL DESENGATE/BAU",
		"57" => "7",	//"MANUTENCAO",
		"58" => "",		//"ABANDONO DE COMBOIO",
		"59" => "",		//"MACRO MSG LIVRE",
		"60" => "",		//"AG DESCARGA",
		"61" => "",		//"ABASTECIMENTO - ARLA32",
		"62" => "2",	//"PERNOITE - FIM DE JORNADA",
		"63" => "3",	//"REFEICAO",
		"64" => "5",	//"EM ESPERA",
		"65" => "7",	//"DESCANSO",
		"66" => "",		//"TROCA DE VEÍCULO",
		"67" => "0",	//"INICIO DE VIAGEM",
		"68" => "0",	//"REINICIO DE VIAGEM",
		"69" => "",		//???
		"70" => "2"		//"FIM DE JORNADA"
	];

	$f = 0;
	foreach($pontos as $ponto){
		
		$newPonto = [
			"pont_nb_userCadastro"	=> $_SESSION['user_nb_id'],
			"pont_tx_dataCadastro" 	=> date("Y-m-d H:i:s"),
			"pont_tx_matricula" 	=> $ponto["driver_cpf"],	// No caso da Comav, a mátricula do motorista é o CPF.
			"pont_tx_data" 			=> $dates[$f],
			// "pont_tx_tipo" é colocado mais abaixo
			"pont_tx_tipoOriginal" 	=> $ponto["macro_number"],
			"pont_tx_latitude" 		=> $ponto["latitude"],
			"pont_tx_longitude" 	=> $ponto["longitude"],
			"pont_tx_placa" 		=> ($ponto["vehicle_name"] != "desativado")? $ponto["vehicle_name"]: NULL,
			"pont_tx_status"		=> "ativo"
		];

		$f++;
		echo "<br>---------------------------<br>".$f."<br>";
		dd($newPonto["pont_tx_data"], false);
		// if($f > 1000){
		// 	break;
		// }

		//Conferir se tipo de ponto existe{
			if(!in_array($newPonto["pont_tx_tipoOriginal"], $macros)){
				echo "ERRO: Tipo de ponto não encontrado. ({$newPonto["pont_tx_tipoOriginal"]})<br>";
				continue;
			}
		//}

		if(!in_array($newPonto["pont_tx_tipoOriginal"], array_keys($relacaoMacros))){
			echo "Tipo de ponto não encontrado.";
			continue;
		}elseif($relacaoMacros[$newPonto["pont_tx_tipoOriginal"]] == ""){
			echo "Ponto ignorado por macroponto: {$newPonto["pont_tx_tipoOriginal"]}, ".$relacaoMacros[$newPonto["pont_tx_tipoOriginal"]];
			continue;
		}

		$newPonto["pont_tx_tipo"] = $relacaoMacros[$newPonto["pont_tx_tipoOriginal"]];

		$ultimoPonto = mysqli_fetch_assoc(query(
			"SELECT * FROM ponto
				JOIN macroponto ON pont_tx_tipo = macr_tx_codigoInterno
				WHERE pont_tx_status = 'ativo' AND macr_tx_status = 'ativo'
					AND pont_tx_matricula = '{$newPonto["pont_tx_matricula"]}'
					AND pont_tx_data <= '{$newPonto["pont_tx_data"]}'
				ORDER BY pont_tx_data DESC
				LIMIT 1;"
		));

		if(!empty($ultimoPonto)){
			//Criar um ponto de finalização do último intervalo antes de criar um novo intervalo.{
				/*Conferir erros com o cadastro do ponto atual{
					if($newPonto["pont_tx_tipo"] == $ultimoPonto["pont_tx_tipo"]){
						echo "O último ponto é do mesmo tipo. (".$newPonto["pont_tx_tipo"].")";
						continue;
					}
					
					if($newPonto["pont_tx_tipo"] == "0"){
						if(((int)$ultimoPonto["pont_tx_tipo"]) == 1){
							echo "Não tem um intervalo anterior aberto para fechar. (".$ultimoPonto["pont_tx_tipo"].", ".$newPonto["pont_tx_tipo"].")";
							continue;
						}
						$newPonto["pont_tx_tipo"] = strval((int)$ultimoPonto["pont_tx_tipo"]+1);
						if(intval($newPonto["pont_tx_tipo"]) > 8){
							dd(["ALERTA ".__LINE__, $newPonto, $ultimoPonto]);
						}
					}

					if($newPonto["pont_tx_tipo"] == $ultimoPonto["pont_tx_tipo"]){
						echo "O último ponto é do mesmo tipo. (".$newPonto["pont_tx_tipo"].")";
						continue;
					}
				//}*/

				//Conferir se há uma jornada aberta no horário para inserir pontos.
				/* Manter esse código salvo para caso seja necessário fazer essa conferência no futuro.
					if(in_array($newPonto["pont_tx_tipo"], ["1", "2"])){
						$temJornadaAberta = mysqli_fetch_assoc(query(
							"SELECT pont_nb_id, pont_tx_tipo FROM ponto
								WHERE pont_tx_status = 'ativo'
									AND pont_tx_matricula = '".$newPonto["pont_tx_matricula"]."'
									AND pont_tx_data <= '".$newPonto["pont_tx_data"]."'
									AND pont_tx_tipo IN ('1', '2')
								ORDER BY pont_tx_data DESC
								LIMIT 1;"
						));
						if($temJornadaAberta["pont_tx_tipo"] == "1" && $newPonto["pont_tx_tipo"] == "1"){
							echo "Jornada já aberta anteriormente.";
							continue;
						}
						if($temJornadaAberta["pont_tx_tipo"] == "2" && $newPonto["pont_tx_tipo"] == "2"){
							echo "Jornada já fechada neste horário ou após ele.";
							continue;
						}

						$jornadaJaFechada = mysqli_fetch_assoc(query(
							"SELECT pont_nb_id, pont_tx_tipo FROM ponto
								WHERE pont_tx_status = 'ativo'
									AND pont_tx_matricula = '".$newPonto["pont_tx_matricula"]."'
									AND pont_tx_data >= '".$newPonto["pont_tx_data"]."'
									AND pont_tx_tipo IN ('1', '2')
								ORDER BY pont_tx_data DESC
								LIMIT 1;"
						));

						if($jornadaJaFechada["pont_tx_tipo"] == "2"){
							echo "Essa jornada já foi fechada posteriormente.";
							continue;
						}
					}
				//*/

				if([$ultimoPonto["pont_tx_matricula"], $ultimoPonto["pont_tx_data"], $ultimoPonto["pont_tx_tipo"]] == [$newPonto["pont_tx_matricula"], $newPonto["pont_tx_data"], $newPonto["pont_tx_tipo"]]){
					echo "Ponto já cadastrado.";
					continue;
				}

				if(((int)$ultimoPonto["pont_tx_tipo"])%2 == 1 										//O último ponto é uma abertura (tipo = ímpar)
					&& (int)$ultimoPonto["pont_tx_tipo"] != ((int)$newPonto["pont_tx_tipo"])-1 		//E Este ponto não é o fechamento do intervalo anterior
					&& (int)$ultimoPonto["pont_tx_tipo"] != 1										//E O último ponto não é uma abertura de jornada
				){
					//Cadastrar final do último ponto
					$fechAnterior = $newPonto;
					$fechAnterior["pont_tx_dataCadastro"] = date("Y-m-d H:i:s");
					$fechAnterior["pont_tx_data"] = (new DateTime($newPonto["pont_tx_data"]))->modify("-1 second")->format("Y-m-d H:i:s");
					$fechAnterior["pont_tx_tipo"] = strval((int)$ultimoPonto["pont_tx_tipo"]+1);
					if(intval($fechAnterior["pont_tx_tipo"]) > 8){
						dd(["ALERTA ".__LINE__, $newPonto, $ultimoPonto]);
					}
				}
			//}

		}elseif((int)$newPonto["pont_tx_tipo"]%2 == 0){
			echo "Não tem um intervalo anterior aberto para fechar. (".$newPonto["pont_tx_tipo"].")";
			continue;
		}
		
		//*Registrar ponto{
			if(!empty($fechAnterior)){
				$result = inserir("ponto", array_keys($fechAnterior), array_values($fechAnterior));
				if(gettype($result[0]) == "object" && get_class($result[0]) == "Exception"){
					echo "ERRO: Ao inserir ponto ".__LINE__.".<br><br>";
					dd($result);
				}
				dd([$ultimoPonto, $fechAnterior], false);
				echo "Fechamento cadastrado com sucesso.<br>";
				unset($fechAnterior);
			}
			$result = inserir("ponto", array_keys($newPonto), array_values($newPonto));
			if(gettype($result[0]) == "object" && get_class($result[0]) == "Exception"){
				echo "ERRO: Ao inserir ponto ".__LINE__.".<br><br>";
				dd($result);
			}
			dd($newPonto, false);
			echo "Ponto cadastrado com sucesso.<br><br>";
		//}*/
	}
