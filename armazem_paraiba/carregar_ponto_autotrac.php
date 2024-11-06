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
	if(!in_array($_ENV["CONTEX_PATH"], $dominiosAutotrac)){
		echo "Apenas empresas com Autotrac registrados podem utilizar este serviço.<br>";
		exit;
	}

	echo "Em desenvolvimento...<br>";
	exit;
	
	// Função para consumir a API e inserir os dados na tabela
	function fetchAndInsertMacros($apiUrl, $token) {
		$ch = curl_init();
		// Configurações do cURL
		curl_setopt($ch, CURLOPT_URL, $apiUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Authorization: Bearer $token"
		]);
		
		// Executa a requisição
		$response = curl_exec($ch);
		
		// Verifica se houve erro
		if (curl_errno($ch)) {
			echo "Erro ao consumir a API: ".curl_error($ch);
			return;
		}
		
		// Decodifica o JSON da resposta
		$pontos = json_decode($response, true);
		
		// Fecha a requisição cURL
		curl_close($ch);

		$datas = [];
		$nomes = [];
		foreach($pontos as &$ponto){
			$ponto["position_time"] = DateTime::createFromFormat("d/m/Y H:i:s", $ponto["position_time"])->format("Y-m-d H:i:s");
			$datas[] = $ponto["position_time"];
			$nomes[] = $ponto["driver_name"];
		}
		array_multisort($nomes, SORT_ASC, $datas, SORT_ASC, $pontos, SORT_ASC);
		
		// Verifica se os dados são válidos
		if(empty($pontos) || !is_array($pontos)){
			echo "Nenhum dado válido retornado da API.";
			return;
		}

		/*Cadastrar somente do primeiro motorista.{
			$matriculaInicial = $pontos[0]["driver_cpf"];
		//}*/

		foreach ($pontos as $ponto) {
			echo "<br>---------------------------<br>";
			// Usar a data diretamente da API
			$newPonto = [
				"pont_nb_userCadastro"	=> $_SESSION['user_nb_id'],
				"pont_tx_dataCadastro" 	=> date("Y-m-d H:i:s"),
				"pont_tx_matricula" 	=> $ponto["driver_cpf"], // CPF do motorista (matrícula)
				"pont_tx_data" 			=> $ponto["position_time"],
				// "pont_tx_tipo" é colocado mais abaixo
				"pont_tx_latitude" 		=> $ponto["latitude"],    // Latitude
				"pont_tx_longitude" 	=> $ponto["longitude"],  // Longitude
				"pont_tx_placa" 		=> $ponto["vehicle_name"],   // Placa do veículo
				"pont_tx_status"		=> "ativo"
			];

			/*Cadastrar somente do primeiro motorista.{
				if($newPonto["pont_tx_matricula"] != $matriculaInicial){
					echo "break";
					return;
				}
			// }*/

			//Conferir se tipo de ponto existe{
				$macroInterno = mysqli_fetch_assoc(query(
					"SELECT macr_tx_codigoInterno FROM macroponto"
					." WHERE macr_tx_status = 'ativo' AND macr_tx_fonte = 'autotrac'"
						." AND macr_tx_codigoExterno = '".$ponto["macro_number"]."'"
					." LIMIT 1;"
				));
	
				if(empty($macroInterno)){
					echo "ERRO: Tipo de ponto não encontrado. (".$ponto["macro_number"].")<br>";
					continue;
				}
			//}
			//Conferir se o macroponto existe{
				$idMacro = mysqli_fetch_assoc(query(
					"SELECT macr_nb_id, macr_tx_nome FROM macroponto"
					." WHERE macr_tx_status = 'ativo' AND macr_tx_fonte = 'autotrac'"
						." AND macr_tx_codigoExterno = '".$ponto["macro_number"]."';"
				));

				// Se o tipo de ponto for encontrado, inserir os dados
				if(empty($idMacro)){
					echo "ERRO: Código externo não encontrado na tabela macroponto: ".$ponto["macro_number"]."<br>";
					continue;
				}
			//}

			//0 = Conferir se há um intervalo aberto antes para fechar.
			//"" = Ignorar
			//inteiro > 0: Conferir se há um intervalo aberto antes para fechar + substituir pela macro de abertura com esse codigoInterno

			$relacaoMacros = [
				"50" => "1",	//"INICIO DE JORNADA",
				"51" => "0",	//"INICIO DE VIAGEM",
				"52" => "",		//"PARADA EVENTUAL",
				"53" => "0",	//"REINICIO DE VIAGEM",
				"54" => "",		//"FIM DE VIAGEM",
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

			if(!in_array($ponto["macro_number"], array_keys($relacaoMacros))){
				echo "Tipo de ponto não encontrado.";
				continue;
			}
			if($relacaoMacros[$ponto["macro_number"]] == ""){
				echo "Ponto ignorado por macroponto: ".$ponto["macro_number"].", ".$relacaoMacros[$ponto["macro_number"]];
				continue;
			}

			$newPonto["pont_tx_tipo"] = $relacaoMacros[$ponto["macro_number"]];

			$ultimoPonto = mysqli_fetch_assoc(query(
				"SELECT * FROM ponto"
				." JOIN macroponto ON pont_tx_tipo = macr_tx_codigoInterno"
				." WHERE pont_tx_status = 'ativo' AND macr_tx_status = 'ativo'"
				." AND pont_tx_matricula = '".$newPonto["pont_tx_matricula"]."'"
				." AND pont_tx_data < '".$newPonto["pont_tx_data"]."'"
				."ORDER BY pont_tx_data DESC"
				." LIMIT 1;"
			));


			if(!empty($ultimoPonto)){
				//Criar um ponto de finalização do último intervalo antes de criar para um novo.{
					if($newPonto["pont_tx_tipo"] == "0"){
						if(((int)$ultimoPonto["pont_tx_tipo"]) == 1){
							echo "Não tem um intervalo anterior aberto para fechar. (".$newPonto["pont_tx_tipo"].")";
							continue;
						}

						$newPonto["pont_tx_tipo"] = strval((int)$ultimoPonto["pont_tx_tipo"]+1);
					}

					if($newPonto["pont_tx_tipo"] == $ultimoPonto["pont_tx_tipo"]){
						echo "O último ponto é do mesmo tipo. (".$newPonto["pont_tx_tipo"].")";
						continue;
					}

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
							remover_ponto($temJornadaAberta["pont_nb_id"], "Há um fechamento de jornada posterior.");
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
					*/

					if(((int)$ultimoPonto["pont_tx_tipo"])%2 == 1 										//o último ponto é uma abertura (tipo = ímpar)
						&& (int)$ultimoPonto["pont_tx_tipo"] != ((int)$newPonto["pont_tx_tipo"])-1 		//E o último ponto não for a abertura deste
						&& !in_array("1", [$ultimoPonto["pont_tx_tipo"]])								//E nem o último ponto, nem o ponto atual são aberturas de jornada
					){
						//Cadastrar final do último ponto
						$fechAnterior = $newPonto;
						$fechAnterior["pont_tx_dataCadastro"] = date("Y-m-d H:i:s");
						$fechAnterior["pont_tx_data"] = (new DateTime($newPonto["pont_tx_data"]))->modify("-1 second")->format("Y-m-d H:i:s");
						$fechAnterior["pont_tx_tipo"] = strval((int)$ultimoPonto["pont_tx_tipo"]+1);
					}
				//}
			}elseif((int)$newPonto["pont_tx_tipo"]%2 == 0){
				//Não tem um intervalo anterior aberto para fechar.
				echo "Não tem um intervalo anterior aberto para fechar. (".$newPonto["pont_tx_tipo"].")";
				continue;
			}

			// Consultar se já existe um registro com o mesmo horário e matrícula{
				$jaCadastrado = !empty(mysqli_fetch_assoc(query(
					"SELECT pont_nb_id FROM ponto"
					." WHERE pont_tx_status = 'ativo'"
					." AND pont_tx_matricula = '".$newPonto["pont_tx_matricula"]."'"
					." AND pont_tx_data = '".$newPonto["pont_tx_data"]."'"
					." AND pont_tx_tipo = '".$newPonto["pont_tx_tipo"]."';"
				)));
				if($jaCadastrado){
					echo "Ponto já cadastrado.";
					continue;
				}
			// }
			
			//*Registrar ponto{
				if(!empty($fechAnterior)){
					$result = inserir("ponto", array_keys($fechAnterior), array_values($fechAnterior));
					if(gettype($result[0]) == "object" && get_class($result[0]) == "Exception"){
						echo "ERRO: Ao inserir ponto.<br><br>";
						exit;
					}
					dd([$ultimoPonto, $fechAnterior], false);
					echo "Fechamento cadastrado com sucesso.<br>";
					unset($fechAnterior);
				}
				$result = inserir("ponto", array_keys($newPonto), array_values($newPonto));
				if(gettype($result[0]) == "object" && get_class($result[0]) == "Exception"){
					echo "ERRO: Ao inserir ponto.<br><br>";
					exit;
				}
				dd($newPonto, false);
				echo "Ponto cadastrado com sucesso.<br><br>";
			//}*/
		}
	}

	// URL da API
	$apiUrl = "https://apimacrocomav.logsyncwebservice.techps.com.br/macros";
	// Token de autenticação Bearer
	$token = "0cb538f85dbe5cf630d03b4be8a61b77";

	// Chamar a função para buscar e inserir os macros
	fetchAndInsertMacros($apiUrl, $token);