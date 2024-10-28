<?php
    /* Modo Debug{
        ini_set("display_errors", 1);
        ini_set("display_startup_errors", 1);
        error_reporting(E_ALL);
    //}*/

	
	include_once "funcoes_ponto.php";
	
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
		foreach($pontos as &$ponto){
			$ponto["position_time"] = substr(DateTime::createFromFormat("d/m/Y H:i:s", $ponto["position_time"])->format("Y-m-d H:i:s"), 0, -3).":00";
			$datas[] = $ponto["position_time"];
		}
		array_multisort($datas, SORT_ASC, $pontos, SORT_ASC);
		
		// Verifica se os dados são válidos
		if(empty($pontos) || !is_array($pontos)){
			echo "Nenhum dado válido retornado da API.";
			return;
		}
		$f = 0;
		foreach ($pontos as $ponto) {
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

			$macroInterno = mysqli_fetch_assoc(query(
				"SELECT macr_tx_codigoInterno FROM macroponto"
				." WHERE macr_tx_status = 'ativo'"
					." AND macr_tx_fonte = 'autotrac'"
					." AND macr_tx_codigoExterno = '".$ponto["macro_number"]."'"
				." LIMIT 1;"
			));

			if(empty($macroInterno)){
				echo "ERRO: Tipo de ponto não encontrado. (".$ponto["macro_number"].")<br>";
				continue;
			}
			
			$newPonto["pont_tx_tipo"] = $macroInterno["macr_tx_codigoInterno"];
			// Relacionar com a tabela macroponto
			
			// Consultar o ID do tipo de ponto baseado no código externo
			$idMacro = mysqli_fetch_assoc(query(
				"SELECT macr_nb_id FROM macroponto WHERE macr_tx_codigoExterno = '".$ponto["macro_number"]."'"
			));

			// Se o tipo de ponto for encontrado, inserir os dados
			if(empty($idMacro)){
				echo "ERRO: Código externo não encontrado na tabela macroponto: ".$ponto["macro_number"]."<br>";
				continue;
			}

			// Consultar se já existe um registro com a mesma data e hora (incluindo segundos), CPF e placa
			$count = mysqli_fetch_assoc(query(
				"SELECT COUNT(*) as qtd FROM ponto"
				." WHERE pont_tx_status = 'ativo'"
					." AND pont_tx_data = '".$newPonto["pont_tx_data"]."'"
					." AND pont_tx_matricula = '".$newPonto["pont_tx_matricula"]."'"
				." LIMIT 1;"
			));
			if(!empty($count) && $count["qtd"] != "0"){
				echo "Registro já existente: ".$newPonto["pont_tx_matricula"].", ".$newPonto["pont_tx_data"]."<br>.";
				continue; // Pula para o próximo ponto
			}

			if(++$f > 10){
				break;
			}
			dd($newPonto, false);
			/*Registrar ponto{
				$result = inserir("ponto", array_keys($newPonto), array_values($newPonto));
				if(gettype($result[0]) == "object" && get_class($result[0]) == "Exception"){
					echo "ERRO: Ao inserir ponto.";
				}
			//}*/
		}
	}

	// URL da API
	$apiUrl = "https://apimacrocomav.logsyncwebservice.techps.com.br/macros";
	// Token de autenticação Bearer
	$token = "0cb538f85dbe5cf630d03b4be8a61b77";

	// Chamar a função para buscar e inserir os macros
	fetchAndInsertMacros($apiUrl, $token);