<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Expires: 01 Jan 2001 00:00:00 GMT");
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Cache-Control: post-check=0, pre-check=0", FALSE);
	//*/

	include_once "funcoes_ponto.php";

	function cadastrarAjuste(){
		//Tratamento de erros{
			try{
				//Conferir se tem as informações necessárias{
					$camposObrig = [
						"idMotorista" => "Motorista",
						"hora" => "Hora",
						"idMacro" => "Tipo de Registro",
						"motivo" => "Motivo",
					];
					$errorMsg = conferirCamposObrig($camposObrig, $_POST);
					if(!empty($errorMsg)){
						throw new Exception($errorMsg);
					}

					$aMotorista = carregar("entidade", $_POST["idMotorista"]);
					if(empty($aMotorista)){
						$_POST["errorFields"][] = "idMotorista";
						throw new Exception("Funcionário não encontrado.");
					}

					$aTipo = carregar("macroponto", $_POST["idMacro"]);
					if(empty($aTipo)){
						$_POST["errorFields"][] = "idMacro";
						throw new Exception("Macro não encontrado.");
					}
				//}

				$codigosJornada = ["inicio" => 1, "fim" => 2];
				//Conferir se há uma jornada aberta{
					$temJornadaAberta = mysqli_fetch_all(
						query(
							"SELECT * FROM ponto 
								WHERE pont_tx_tipo IN ('".$codigosJornada["inicio"]."', '".$codigosJornada["fim"]."')
									AND pont_tx_status = 'ativo'
									AND pont_tx_matricula = '".$aMotorista["enti_tx_matricula"]."'
									AND pont_tx_data <= STR_TO_DATE('".$_POST["data"].' '.$_POST["hora"].":59', '%Y-%m-%d %H:%i:%s')
								ORDER BY pont_tx_data DESC
								LIMIT 1"
						),
						MYSQLI_ASSOC
					)[0];
					$temJornadaAberta = (!empty($temJornadaAberta) && intval($temJornadaAberta["pont_tx_tipo"]) == $codigosJornada["inicio"]);

					if($temJornadaAberta){
						if(intval($aTipo["macr_tx_codigoInterno"]) == $codigosJornada["inicio"]){ //Se tem jornada aberta e está tentando cadastrar uma abertura de jornada
							throw new Exception("Não é possível registrar um ".strtolower($aTipo["macr_tx_nome"])." sem fechar o anterior.");
						}elseif(intval($aTipo["macr_tx_codigoInterno"]) == $codigosJornada["fim"]){
							$jornadaFechada = mysqli_fetch_assoc(
								query(
									"SELECT pont_tx_tipo FROM ponto 
										WHERE pont_tx_tipo IN ('".$codigosJornada["inicio"]."', '".$codigosJornada["fim"]."')
											AND pont_tx_status = 'ativo'
											AND pont_tx_matricula = '".$aMotorista["enti_tx_matricula"]."'
											AND pont_tx_data >= STR_TO_DATE('".$_POST["data"].' '.$_POST["hora"].":00', '%Y-%m-%d %H:%i:%s')
										ORDER BY pont_tx_data ASC
										LIMIT 1;"
								)
							);
							$jornadaFechada = (!empty($jornadaFechada) && $jornadaFechada["pont_tx_tipo"] == $codigosJornada["fim"]);
							if(!empty($jornadaFechada)){
								throw new Exception("Esta jornada já foi fechada neste horário ou após ele.");
							}
						}else{
							$matchedTypes = [
								"inicios" => mysqli_fetch_all(query(
									"SELECT macr_tx_codigoInterno FROM macroponto WHERE macr_tx_nome LIKE 'Inicio%' AND macr_tx_nome NOT LIKE '%de Jornada';"
								), MYSQLI_NUM),
								"fins" => mysqli_fetch_all(query(
									"SELECT macr_tx_codigoInterno FROM macroponto WHERE macr_tx_nome LIKE 'Fim%'    AND macr_tx_nome NOT LIKE '%de Jornada'"
								), MYSQLI_NUM),
							];

							$matchedTypes["inicios"] 	= array_map(function($value){return intval($value[0]);}, $matchedTypes["inicios"]);
							$matchedTypes["fins"] 		= array_map(function($value){return intval($value[0]);}, $matchedTypes["fins"]);

							$temPeriodoAberto = mysqli_fetch_assoc(query(
								"SELECT * FROM ponto"
									." WHERE pont_tx_status = 'ativo'"
										." AND pont_tx_matricula = '".$aMotorista["enti_tx_matricula"]."'"
										." AND pont_tx_data <= STR_TO_DATE('".$_POST["data"]." ".$_POST["hora"].":59', '%Y-%m-%d %H:%i:%s')"
									." ORDER BY pont_tx_data DESC, pont_nb_id DESC"
									." LIMIT 1;"
							));
							
							$temPeriodoAberto["pont_tx_tipo"] = !empty($temPeriodoAberto["pont_tx_tipo"])? intval($temPeriodoAberto["pont_tx_tipo"]): 0;
							$temPeriodoAberto["macr_tx_codigoInterno"] = !empty($temPeriodoAberto["macr_tx_codigoInterno"])? intval($temPeriodoAberto["macr_tx_codigoInterno"]): 0;
							
							$tipoMacro = intval($aTipo["macr_tx_codigoInterno"])-(intval($aTipo["macr_tx_codigoInterno"])%2 == 0? 1: 0); //O código de abertura do tipo que está sendo registrado. Se par, é uma abertura, senão, um fechamento.
							$mesmoTipo = ($temPeriodoAberto["pont_tx_tipo"] == $tipoMacro || $temPeriodoAberto["pont_tx_tipo"] == $tipoMacro+1); //Se esse período encontrado é do mesmo tipo que está tentando ser cadastrado
							$temPeriodoAberto = in_array($temPeriodoAberto["pont_tx_tipo"], $matchedTypes["inicios"]); //Se encontrou um período aberto
							
							if(in_array(intval($aTipo["macr_tx_codigoInterno"]), $matchedTypes["inicios"])){ //Se está registrando uma abertura de período
								if($temPeriodoAberto){
									throw new Exception("Não é possível registrar um ".strtolower($aTipo["macr_tx_nome"])." sem fechar o anterior.");
								}
							}elseif(in_array(intval($aTipo["macr_tx_codigoInterno"]), $matchedTypes["fins"])){ //Se está registrando um fechamento de período
								if(!$temPeriodoAberto || !$mesmoTipo){ //Se não tem um período aberto ou se o período aberto é de tipo diferente do que está sendo fechado 
									throw new Exception("Não é possível registrar um ".strtolower($aTipo["macr_tx_nome"])." sem abrir um período antes.");
								}
							}
						}
					}elseif($aTipo["macr_tx_codigoInterno"] != "1"){
						throw new Exception("Não é possível realizar ajustes sem uma jornada aberta.");
					}
				//}
			}catch(Exception $error){
				set_status("ERRO: ".$error->getMessage());
				index();
				exit;
			}
		//}

		$temPonto = mysqli_fetch_assoc(query(
			"SELECT pont_nb_id, pont_tx_data FROM ponto
				WHERE pont_tx_status = 'ativo'
					AND pont_tx_matricula = '".$aMotorista["enti_tx_matricula"]."'
					AND STR_TO_DATE(pont_tx_data, '%Y-%m-%d %H:%i') = STR_TO_DATE('".($_POST["data"]." ".$_POST["hora"])."', '%Y-%m-%d %H:%i')
				ORDER BY pont_tx_data DESC
				LIMIT 1"
		));
		
		$data = $_POST["data"]." ".$_POST["hora"];

		if(!empty($temPonto["pont_tx_data"])){
			$seg = explode(":", $temPonto["pont_tx_data"])[2];
			$seg = intval($seg)+1;
			$data = $data.":".str_pad(strval($seg), 2, "0", STR_PAD_LEFT);
		}else{
			$data = $data.":00";
		}


		$newPonto = [
			"pont_nb_userCadastro"	=> $_SESSION["user_nb_id"],
			"pont_tx_matricula" 	=> $aMotorista["enti_tx_matricula"],
			"pont_tx_data" 			=> $data,
			"pont_tx_tipo" 			=> $aTipo["macr_tx_codigoInterno"],
			"pont_tx_status" 		=> "ativo",
			"pont_tx_dataCadastro" 	=> date("Y-m-d H:i:s"),
			"pont_nb_motivo" 		=> $_POST["motivo"],
			"pont_tx_justificativa" => $_POST["justificativa"]
		];
		
		inserir("ponto", array_keys($newPonto), array_values($newPonto));
		index();
		exit;
	}

	function excluirPonto(){

		if(empty($_POST["idPonto"]) || empty($_POST["justificativa"])){
			set_status("ERRO: Não foi possível inativar o ponto.");
			index();
			exit;
		}

		if(empty($_POST["dataAtualiza"])){
			$_POST["dataAtualiza"] = date("Y-m-d H:i:s");
		}
		
		$ponto = mysqli_fetch_assoc(query(
			"SELECT * FROM ponto 
				WHERE pont_nb_id = {$_POST["idPonto"]} 
				LIMIT 1;"
		));
		if($ponto["pont_tx_status"] == "inativo"){
			set_status("ERRO: Ponto já inativado.");
			index();
			exit;
		}
		$ponto["pont_tx_status"] = "inativo";
		$ponto["pont_tx_justificativa"] = $_POST["justificativa"];
		$ponto["pont_tx_dataAtualiza"] = $_POST["dataAtualiza"];
		
		atualizar("ponto", array_keys($ponto), array_values($ponto), $ponto["pont_nb_id"]);
		
		$_POST["data"] = explode(" ", $ponto["pont_tx_data"])[0];

		index();
		exit;
	}

	function status() {
		return  
			"<style>
				#statusDiv{
					display: inline-flex;
				}
				#status-label{
				margin-right: 10px; 
				
				}
				#status {
					margin-top: -5px;
					width: 93px;
				}
				</style>
				<div id='statusDiv'>
					<label id='status-label'>Status:</label>
					<select name='status' id='status' class='form-control input-sm campo-fit-content' onchange='atualizar_form({$_POST["idMotorista"]}, \"{$_POST["data"]}\", this.value)'>
						<option value='ativo'>Ativos</option>
						<option value='inativo' ".((!empty($_POST["status"]) && $_POST["status"] == "inativo")? "selected": "").">Inativos</option>
					</select>
				</div>"
		;
	}

	function pegarSqlDia(string $matricula, array $cols): string{
		$condicoesPontoBasicas = "ponto.pont_tx_status = 'ativo' AND ponto.pont_tx_matricula = '{$matricula}'";

		$abriuJornadaHoje = mysqli_fetch_assoc(query(
			"SELECT pont_tx_data FROM ponto
				WHERE {$condicoesPontoBasicas}
					AND ponto.pont_tx_tipo = 1
					AND ponto.pont_tx_data LIKE '%".$_POST["data"]."%'
				ORDER BY ponto.pont_tx_data ASC
				LIMIT 1;"
		));

		//Definir data de início da query{
			//Se abriu jornada hoje, considera a partir da data de abertura da jornada.
			if(empty($abriuJornadaHoje)){
				//Se não tem uma jornada que veio de antes, considera a partir de meia-noite de hoje.
				$sqlDataInicio = "{$_POST["data"]} 00:00:00";

				//Se não abriu uma jornada hoje, confere se há uma jornada aberta que veio de antes do dia.
				$temJornadaAberta = mysqli_fetch_assoc(query(
					"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 1) as temJornadaAberta FROM ponto
						WHERE {$condicoesPontoBasicas}
							AND ponto.pont_tx_tipo IN (1,2)
							AND pont_tx_data <= STR_TO_DATE('{$sqlDataInicio}', '%Y-%m-%d %H:%i:%s')
						ORDER BY pont_tx_data DESC
						LIMIT 1;"
				));

				if(!empty($temJornadaAberta) && intval($temJornadaAberta["temJornadaAberta"])){
					//Se a jornada aberta antes do dia não foi fechada, pega desde o momento em que a jornada foi aberta.
					$sqlDataInicio = $temJornadaAberta["pont_tx_data"];
					//Se tem uma jornada que veio de antes do dia, confere se esta foi fechada hoje.
					$jornadaFechadaHoje = mysqli_fetch_assoc(query(
						"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 2) as jornadaFechadaHoje FROM ponto
							WHERE {$condicoesPontoBasicas}
								AND ponto.pont_tx_tipo IN (1,2)
								AND pont_tx_data LIKE '%{$_POST["data"]}%'
							ORDER BY pont_tx_data ASC
							LIMIT 1;"
					));
					if(!empty($jornadaFechadaHoje) && intval($jornadaFechadaHoje["jornadaFechadaHoje"])){
						//Se a jornada aberta antes do dia foi fechada, deve considerar apenas após esse fechamento.
						$sqlDataInicio = $jornadaFechadaHoje["pont_tx_data"];
					}
				}
			}
		//}

		//Definir data de fim da query{
			$sqlDataFim = $_POST["data"]." 23:59:59";
			if(!empty($abriuJornadaHoje)){
				$sqlDataInicio = $abriuJornadaHoje["pont_tx_data"];
				//Se abriu jornada hoje, confere se teve uma jornada aberta que seguiu pros dias seguintes
				$deixouJornadaAberta = mysqli_fetch_assoc(
					query(
						"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 1) as deixouJornadaAberta FROM ponto
							WHERE {$condicoesPontoBasicas}
								AND ponto.pont_tx_tipo IN (1,2)
								AND pont_tx_data <= STR_TO_DATE('".$_POST["data"]." 23:59:59', '%Y-%m-%d %H:%i:%s')
							ORDER BY pont_tx_data DESC
							LIMIT 1;"
					)
				);
				if(!empty($deixouJornadaAberta) && intval($deixouJornadaAberta["deixouJornadaAberta"])){
					//Se deixou uma jornada aberta pros dias seguintes, confere se ela terminou.
					$fimJornada = mysqli_fetch_assoc(
						query(
							"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 2) as fimJornada FROM ponto
								WHERE {$condicoesPontoBasicas}
									AND ponto.pont_tx_tipo IN (1,2)
									AND pont_tx_data >= STR_TO_DATE('".$_POST["data"]." 23:59:59', '%Y-%m-%d %H:%i:%s')
								ORDER BY pont_tx_data ASC
								LIMIT 1;"
						)
					);
					if(!empty($fimJornada) && intval($fimJornada["fimJornada"])){
						//Se a jornada deixada aberta já foi finalizada, pega até o fechamento dessa jornada deixada.
						$sqlDataFim = $fimJornada["pont_tx_data"];
					}
				}
			}
		//}

		$condicoesPontoBasicas = 
			"ponto.pont_tx_status = '{$_POST["status"]}' 
			AND ponto.pont_tx_matricula = '{$matricula}' 
			AND entidade.enti_tx_status = 'ativo' 
			AND user.user_tx_status = 'ativo' 
			AND macroponto.macr_tx_status = 'ativo'"
		;

		
		$sql = 
			"SELECT DISTINCT pont_nb_id, ".implode(",", $cols)." FROM ponto
				JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno
				JOIN entidade ON ponto.pont_tx_matricula = entidade.enti_tx_matricula
				JOIN user ON entidade.enti_nb_id = user.user_nb_entidade
				LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
				WHERE {$condicoesPontoBasicas}
					AND macr_tx_fonte = 'positron'
					AND ponto.pont_tx_data >= STR_TO_DATE('{$sqlDataInicio}', '%Y-%m-%d %H:%i:%s')
					AND ponto.pont_tx_data <= STR_TO_DATE('{$sqlDataFim}', '%Y-%m-%d %H:%i:%s')
				ORDER BY pont_tx_data ASC"
		;


		return $sql;
	}

	// Função para carregar os CNPJs formatados da tabela "empresa"
	// function carregarCNPJsFormatados() {
	// 	global $conn;
	// 	// Consulta SQL para buscar os CNPJs
	// 	$sql = "SELECT empr_tx_cnpj FROM empresa";

	// 	$result = mysqli_query($conn, $sql);

	// 	if (!$result) {
	// 		die("Erro ao consultar CNPJs: ".mysqli_error($conn));
	// 	}

	// 	$cnpjs_formatados = [];
	// 	while ($row = mysqli_fetch_assoc($result)) {
	// 		// Remove pontos, traços e barras do CNPJ
	// 		$cnpj_formatado = preg_replace("/[^0-9]/", "", $row["empr_tx_cnpj"]);
	// 		$cnpjs_formatados[] = $cnpj_formatado;
	// 	}
	// 	return $cnpjs_formatados;
	// }

	function voltarParaEspelho(){
		foreach(["hora", "idMacro", "motivo", "justificativa", "status"] as $campo){
			unset($_POST[$campo]);
		}
		$_POST["acao"] = "buscarEspelho()";
		voltar();
	}

	function carregarJS(){

		$postValues = $_POST;
		$postValues["acao"] = '';
		$postValues["idPonto"] = '';
		unset($postValues["id"]);
		unset($postValues["errorFields"]);
		unset($postValues["msg_status"]);
		$postValues = json_encode($postValues);
		echo 
			"<script>
				function imprimir() {
					// Abrir a caixa de diálogo de impressão
					window.print();
				}

				function addPostValuesToForm(form, postValues){
					input = '';
					for(key in postValues){
						input = document.createElement('input');
						input.type = 'hidden';
						input.value = postValues[key];
						input.name = key;
						if(Array.isArray(postValues[key])){
							input.name += '[]';
							for(f2 in postValues[key]){
								newInput = document.createElement('input');
								newInput.type = input.type;
								newInput.name = input.name;
								newInput.value = postValues[key][f2];
								form.append(newInput);
							}
						}else{
							form.append(input);
						}
					}
				}

				valorDataInicial = document.getElementById('data').value;
				valorStatusInicial = document.getElementById('status').value;

				function atualizar_form(motorista, data, status){
					if(data == null){
						data = document.getElementById('data').value;
					}
					if(status == null){
						status = document.getElementById('status').value;
					}

					if(valorDataInicial != data || valorStatusInicial != status){
						var form = document.form_ajuste_status;
						addPostValuesToForm(form, {$postValues});
						form.acao.value = 'index';
						form.data.value = data;
						form.status.value = status;
						form.submit();
					}
				}

				function excluirPontoJS(idPonto){
					var form = document.form_ajuste_status;

					addPostValuesToForm(form, {$postValues});

					form.idPonto.value = idPonto;
					form.acao.value = 'excluirPonto';
					var justificativa = document.createElement('input');
					justificativa.type = 'hidden';
					justificativa.name = 'justificativa';
					justificativa.value = prompt('Qual a justificativa da exclusão do ponto?');
					form.append(justificativa);
					
					console.log(form);
					form.submit();
				}
			</script>"
		;
	}


	function index(){
		global $CONTEX;

		//Conferir se os campos de $_POST estão vazios{
			if(empty($_POST["idMotorista"]) || empty($_POST["data"])){
				echo "<script>alert('ERRO: Deve ser selecionado um funcionário e uma data para ajustar.')</script>";
				
				$_POST["acao"] = "buscarEspelho()";
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/espelho_ponto.php";
	
				voltar();
				exit;
			}
	
			if(empty($_POST["HTTP_REFERER"]) || is_int(strpos($_POST["HTTP_REFERER"], "ajuste_ponto.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/espelho_ponto.php";
			}

			if (empty($_POST["status"])) {
				$_POST["status"] = "ativo";
			}
		//}

		cabecalho("Ajuste de Ponto");

		$motorista = mysqli_fetch_assoc(query(
			"SELECT * FROM entidade
				WHERE enti_tx_status = 'ativo'
					AND enti_nb_id = {$_POST["idMotorista"]}
				LIMIT 1;"
		));

		$endosso = mysqli_fetch_array(query(
			"SELECT user_tx_login as endo_nb_userCadastro, endo_tx_dataCadastro FROM endosso
				JOIN user ON endo_nb_userCadastro = user_nb_id
				WHERE endo_tx_status = 'ativo'
					AND '{$_POST["data"]}' BETWEEN endo_tx_de AND endo_tx_ate
					AND endo_nb_entidade = '{$motorista["enti_nb_id"]}'
				LIMIT 1;"
		), MYSQLI_BOTH);

		$botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";

		$cnpjs = mysqli_fetch_all(query("SELECT empr_tx_cnpj FROM empresa"), MYSQLI_ASSOC);

		// Assumindo que $motorista já tenha os valores definidos
		// Construir o botão com o código JavaScript embutido
		$botaoConsLog = 
			"<button class='btn default' type='button' onclick='consultarLogistica()'>Consultar Logística</button>
			<script>
			function consultarLogistica() {
				// Obter valores do PHP e HTML
				var matricula = '{$motorista["enti_tx_matricula"]}';
				var motorista = '{$motorista["enti_tx_nome"]}';
				var data = document.getElementById('data').value;

				// Obter todos os CNPJs da variável PHP
				var cnpjs = ".json_encode($cnpjs).";

				// Verificar o conteúdo de cnpjs no console
				// console.log('CNPJs:', cnpjs);

				if (!Array.isArray(cnpjs)) {
					console.error('CNPJs não é um array:', cnpjs);
					return;
				}

				if (cnpjs.length === 0) {
					console.error('A lista de CNPJs está vazia.');
					return;
				}

				// Converte a lista de CNPJs para uma string separada por vírgulas
				var cnpjString = cnpjs.map(String).join(',');

				// Construir a URL com os parâmetros dinâmicos
				var url = '".$_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/logistica.php';
				url += '?motorista='+encodeURIComponent(motorista)+
					'&matricula='+encodeURIComponent(matricula)+
					'&data='+encodeURIComponent(data) +
					'&cnpj='+encodeURIComponent(cnpjString);  // Adicionando todos os CNPJs

				// Abrir a nova página em uma nova aba
				window.open(url, '_blank');
			}
			</script>"
		;

		$textFields[] = texto("Matrícula", $motorista["enti_tx_matricula"], 2);
		$textFields[] = texto($motorista["enti_tx_ocupacao"], $motorista["enti_tx_nome"], 5);
		$textFields[] = texto("CPF", $motorista["enti_tx_cpf"], 3);

		$_POST["status"] = (!empty($_POST["status"]) && $_POST["status"] != "undefined"? $_POST["status"]: "ativo");

		$variableFields = [];
		$campoJust = [];

		$iconeExcluir = "";
		if(!empty($endosso)){
			$variableFields = [
				texto("Endosso", "Endossado por ".$endosso["endo_nb_userCadastro"]." em ".data($endosso["endo_tx_dataCadastro"], 1), 6)
			];
		}else{
			$botoes[] = botao("Gravar", "cadastrarAjuste");

			//Precisa ser uma função para que o server-side chame e substitua os nomes dos campos pelos valores
			$iconeExcluir = "pont_nb_id";
			
			
			$variableFields = [
				campo_data("Data", "data", ($_POST["data"]?? ""), 2, "onfocusout='atualizar_form({$_POST["idMotorista"]}, this.value, \"{$_POST["status"]}\")'"),
				campo_hora("Hora", "hora", ($_POST["hora"]?? ""), 2),
				combo_bd("Tipo de Registro", "idMacro", ($_POST["idMacro"]?? ""), 4, "macroponto", "", "ORDER BY macr_nb_id"),
				combo_bd("Motivo", "motivo", ($_POST["motivo"]?? ""), 4, "motivo", "", " AND moti_tx_tipo = 'Ajuste' ORDER BY moti_tx_nome")
			];
			$campoJust[] = textarea("Justificativa", "justificativa", ($_POST["justificativa"]?? ""), 12, 'maxlength=680');
		}

		$botoes[] = $botao_imprimir;
		$botoes[] = botao("Voltar", "voltar", "acao", "voltarParaEspelho()");
		$botoes[] = $botaoConsLog; //BOTÃO CONSULTAR LOGISTICA
		$botoes[] = status();


		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], "ajuste_ponto.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/espelho_ponto.php";
			}
		}
		
		echo abre_form("Dados do Ajuste de Ponto");
		echo linha_form($textFields);
		
		echo campo_hidden("idMotorista", $_POST["idMotorista"]);
		//Campos para retornar para a pesquisa do espelho de ponto ou após um registro de ponto{
			echo campo_hidden("busca_empresa", 		empty($_POST["busca_empresa"])? "": $_POST["busca_empresa"]);
			echo campo_hidden("busca_motorista", 	$_POST["idMotorista"]);
			echo campo_hidden("busca_data", 		$_POST["data"]);
			echo campo_hidden("busca_periodo[]",	$_POST["busca_periodo"][0]);
			echo campo_hidden("busca_periodo[]",	$_POST["busca_periodo"][1]);
			echo campo_hidden("HTTP_REFERER", 		$_POST["HTTP_REFERER"]);
		//}
		
		echo linha_form($variableFields);
		echo linha_form($campoJust);
		echo fecha_form($botoes);

		$iconeExcluir = criarSQLIconeTabela("pont_nb_id", "excluirPonto", "Excluir", "glyphicon glyphicon-remove", "Deseja inativar o registro?", "excluirPontoJS(',pont_nb_id,')");

		$sql = pegarSqlDia(
			$motorista["enti_tx_matricula"], 
			[
				"pont_nb_id", 
				"DATE_FORMAT(pont_tx_data, '%d/%m/%Y (%H:%i:%s)') AS pont_tx_data", 
				"macr_tx_nome", 
				"moti_tx_nome", 
				"moti_tx_legenda", 
				"pont_tx_justificativa", 
				"(SELECT user_tx_nome FROM user WHERE user.user_nb_id = pont_nb_userCadastro LIMIT 1) as userCadastro", 
				"pont_nb_userCadastro",
				"pont_tx_dataCadastro", 
				"pont_tx_placa", 
				"pont_tx_latitude", 
				"pont_tx_longitude",
				"pont_tx_dataAtualiza",
				"IF(pont_tx_status = 'ativo', {$iconeExcluir}, NULL) as iconeExcluir"
			]
		);


		$gridFields = [
			"CÓD"												=> "pont_nb_id",
			"DATA"												=> "pont_tx_data",
			"PLACA"                                             => "pont_tx_placa",
			"TIPO"												=> "destacarJornadas(macr_tx_nome)",
			"MOTIVO"											=> "moti_tx_nome",
			"LEGENDA"											=> "moti_tx_legenda",
			"JUSTIFICATIVA"										=> "pont_tx_justificativa",
			"USUÁRIO CADASTRO"									=> "userCadastro",
			"DATA CADASTRO"										=> "data(pont_tx_dataCadastro,1)",
			"DATA EXCLUSÃO"                                     => "data(pont_tx_dataAtualiza,1)",
			"LOCALIZAÇÃO"                                       => "map(pont_nb_id)",
			"<spam class='glyphicon glyphicon-remove'></spam>"	=> "iconeExcluir"
		];
		grid($sql, array_keys($gridFields), array_values($gridFields), "", "12", 1, "desc", -1);

		echo
			"<div id='tituloRelatorio'>
				<img id='logo' style='width: 150px' src='{$CONTEX["path"]}/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
			</div>
			<form name='form_ajuste_status' action='".$_SERVER["HTTP_ORIGIN"].$CONTEX["path"]."/ajuste_ponto.php' method='post'>
			</form>
			<style>
				@media print {
					@page {
						size: A4 landscape;
						margin: 1cm;
					}
					body {
						margin: 1cm;
						margin-right: 0cm; /* Ajuste o valor conforme necessário para afastar do lado direito */
						transform: scale(1.0);
						transform-origin: top left;
					}
					#tituloRelatorio{
						display: flex !important;
						position: absolute;
						top: 5px;
						right: 20px;
					}
						
					form > .row
					{
						display: none;
					}

					.portlet.light {
						padding: 0px 10px !important; /* Reduzindo o padding */
						font-size: 10px !important; /* Reduzindo o tamanho da fonte */
						margin-bottom: 0px !important;
					}

					.row div {
						min-width: min-content !important;
					}

					form > div:nth-child(1){
						display: flex;
						flex-wrap: wrap;
					}
					.col-sm-2,
					.col-sm-5,
					.col-sm-3 {
						width: 40% !important;
						padding-left: 0px;
					}
				}
				#tituloRelatorio{
					display: none;
				}
			</style>"
		;

		carregarJS();

		rodape();
	}