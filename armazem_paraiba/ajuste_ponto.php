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
		try{
			$matricula = mysqli_fetch_assoc(query(
				"SELECT enti_tx_matricula FROM entidade
					WHERE enti_tx_status = 'ativo'
						AND enti_nb_id = {$_POST["idMotorista"]}
					LIMIT 1;"
			))["enti_tx_matricula"];
			$newPonto = conferirErroPonto($matricula, new DateTime("{$_POST["data"]} {$_POST["hora"]}"), $_POST["idMacro"], $_POST["motivo"], $_POST["justificativa"]);
		}catch(Exception $e){
			set_status($e->getMessage());
			index();
			exit;
		}

		//Conferir se já existe um ponto naquele segundo para adicionar o próximo 1 segundo após{
			$temPonto = mysqli_fetch_assoc(query(
				"SELECT pont_nb_id, pont_tx_data FROM ponto
					WHERE pont_tx_status = 'ativo'
						AND pont_tx_matricula = '{$matricula}'
						AND STR_TO_DATE(pont_tx_data, '%Y-%m-%d %H:%i') = STR_TO_DATE('".($_POST["data"]." ".$_POST["hora"])."', '%Y-%m-%d %H:%i')
					ORDER BY pont_tx_data DESC
					LIMIT 1;"
			));
	
			if(!empty($temPonto["pont_tx_data"])){
				$seg = explode(":", $temPonto["pont_tx_data"])[2];
				$seg = intval($seg)+1;
				$newPonto["pont_tx_data"] = "{$_POST["data"]} {$_POST["hora"]}:".str_pad(strval($seg), 2, "0", STR_PAD_LEFT);
			}
		//}

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
		if(is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
			// dd("teste");
			$_POST["returnValues"] = json_encode([
				"HTTP_REFERER" => $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/index.php"
			]);
			voltar();
		}
		
		global $CONTEX;

		//Conferir se os campos de $_POST estão vazios{
			if(empty($_POST["idMotorista"]) || empty($_POST["data"])){
				echo "<script>alert('ERRO: Deve ser selecionado um funcionário e uma data para ajustar.')</script>";
				
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/espelho_ponto.php";
				if(empty($_POST["returnValues"])){
					$_POST["returnValues"] = json_encode($_POST);
				}
				voltar();
				exit;
			}
	
			if (empty($_POST["status"])) {
				$_POST["status"] = "ativo";
			}
		//}

		cabecalho("Ajuste de Ponto");

		$motorista = mysqli_fetch_assoc(query(
			"SELECT enti_nb_id, enti_tx_matricula, enti_tx_nome, enti_tx_ocupacao, enti_tx_cpf FROM entidade
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
		$botoes[] = criarBotaoVoltar("espelho_ponto.php");
		$botoes[] = $botaoConsLog; //BOTÃO CONSULTAR LOGISTICA
		$botoes[] = status();
		
		echo abre_form("Dados do Ajuste de Ponto");
		echo linha_form($textFields);
		
		echo campo_hidden("idMotorista", $_POST["idMotorista"]);
		//Campos para retornar para a pesquisa do espelho de ponto ou após um registro de ponto{
			echo campo_hidden("busca_empresa", 		empty($_POST["busca_empresa"])? "": $_POST["busca_empresa"]);
			echo campo_hidden("busca_motorista", 	$_POST["idMotorista"]);
			echo campo_hidden("busca_data", 		$_POST["data"]);
			echo campo_hidden("busca_periodo[]",	$_POST["busca_periodo"][0]);
			echo campo_hidden("busca_periodo[]",	$_POST["busca_periodo"][1]);
		//}
		
		echo linha_form($variableFields);
		echo linha_form($campoJust);
		echo fecha_form($botoes);

		$iconeExcluir = criarSQLIconeTabela("pont_nb_id", "excluirPonto", "Excluir", "glyphicon glyphicon-remove", "Deseja inativar o registro?", "excluirPontoJS(',pont_nb_id,')");

		$sql = pegarSqlDia(
			$motorista["enti_tx_matricula"], 
			new DateTime($_POST["data"]." 00:00:00"),
			[
				"pont_tx_data", 
				"endo_tx_status",
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
				"IF(pont_tx_status = 'ativo' AND endo_tx_status IS NULL, {$iconeExcluir}, NULL) as iconeExcluir"
			]
		);


		$gridFields = [
			"CÓD"												=> "pont_nb_id",
			"DATA"												=> "data(pont_tx_data,1)",
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