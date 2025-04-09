<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	include "conecta.php";

	// function combo_empresa($nome,$variavel,$modificador,$tamanho,$opcao, $opcao2,$extra=""){
	// 	$t_opcao=count($opcao);
	// 	for($i=0;$i<$t_opcao;$i++){
	// 		$selected = ($opcao[$i] == $modificador)? "selected": "";
	// 		$c_opcao = "<option value='".$opcao[$i]."' ".$selected.">".$opcao2[$i]."</option>";
	// 	}

	// 	$campo="<div class='col-sm-".$tamanho." margin-bottom-5'>
	// 				<label><b>".$nome."</b></label>
	// 				<select name='".$variavel."' class='form-control input-sm campo-fit-content' ".$extra.">
	// 					".$c_opcao."
	// 				</select>
	// 			</div>";

	// 	return $campo;
	// }

	function cadastrarUsuario() {

		$_POST["editPermission"] = (isset($_POST["editPermission"]))? (intval($_POST["editPermission"]) == 1): false;

		//Conferir casos de erro e montar mensagem de erro{
			//Campos obrigatórios não preenchidos{
				if ($_POST["editPermission"]) {
					$camposObrig = [
						"nome" => "Nome",
						"login" => "Login",
						// "senha" => "Senha",
						"nivel" => "Nível",
						"nascimento" => "Data de Nascimento",
						"email" => "Email",
						"empresa" => "Empresa",
					];
					if(empty($_POST["id"])){
						$camposObrig["senha"] = "Senha";
						$camposObrig["senha2"] = "Confirmação de senha";
					}
				}else{
					$camposObrig = [
						"senha" => "Senha",
						"senha2" => "Confirmação de senha",
					];
				}
	
				$errorMsg = conferirCamposObrig($camposObrig, $_POST);

				if(!empty($errorMsg)){
					set_status("ERRO: {$errorMsg}");
					modificarUsuario();
					exit;
				}
			//}

			$errorMsg = "";

			if(!empty($_POST["senha"]) && ($_POST["senha"] != $_POST["senha2"])){
				$_POST["errorFields"][] = "senha2";
				$errorMsg .= "Confirmação de senha correta, ";
			}

			if(!empty($_POST["cpf"])){
				$_POST["cpf"] = preg_replace( "/[^0-9]/is", "", $_POST["cpf"]);
				if(!validarCPF($_POST["cpf"])){
					$_POST["errorFields"][] = "cpf";
					$errorMsg .= "CPF inválido, ";
				}
			}
			if(!empty($_POST["rg"])){
				$_POST["rg"] = preg_replace( "/[^0-9]/is", "", $_POST["rg"]);
				if(strlen($_POST["rg"]) < 3){
					$_POST["errorFields"][] = "rg";
					$errorMsg .= "RG parcial, ";
				}
			}

			if(!empty($errorMsg)){
				set_status("ERRO: ".substr($errorMsg, 0, strlen($errorMsg)-2).".");
				modificarUsuario();
				exit;
			}
		//}

		$usuario = [];
		$campos_variaveis = [
			["user_tx_nome","nome"],
			//Nível está mais abaixo
			//Status está mais abaixo
			["user_tx_login","login"],
			["user_tx_nascimento","nascimento"],
			["user_tx_cpf", "cpf"],
			["user_tx_rg", "rg"],
			["user_nb_cidade", "cidade"],
			["user_tx_email","email"],
			["user_tx_fone","telefone"],
			["user_nb_empresa","empresa"],
			["user_tx_expiracao", "expiracao"]
		];
		foreach($campos_variaveis as $campo){
			if(isset($_POST[$campo[1]]) && !empty($_POST[$campo[1]])){
				$usuario[$campo[0]] = $_POST[$campo[1]];
			}
		}
		if(!empty($_POST["senha"])){
			$usuario["user_tx_senha"] = md5($_POST["senha"]);
		}

		$usuario["user_tx_status"] = !empty($_POST["status"])? $_POST["status"]: "ativo";

		if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador")) && !empty($_POST["nivel"])){
			$usuario["user_tx_nivel"] = $_POST["nivel"];
		}
		
		if(!empty($_POST["cpf"]) && !validarCPF($_POST["cpf"])){
			$_POST["errorFields"][] = "cpf";
			set_status("CPF inválido.");
			modificarUsuario();
			exit;
		}

		$canUpdateWithoutPassword = (is_int(strpos($_SESSION["user_tx_nivel"], "Administrador")) || $_POST["id"] == $_SESSION["user_nb_id"]);

		if((empty($_POST["senha"]) || empty($_POST["senha2"])) && !$canUpdateWithoutPassword){
			set_status("ERRO: Preencha o campo senha e confirme-a.");
			modificarUsuario();
			exit;
		}

		$usuarioCadastrado = mysqli_fetch_all(
			query(
				"SELECT * FROM user
					WHERE user_tx_status = 'ativo'
						AND user_tx_login = '{$_POST["login"]}'
					LIMIT 1;"
			),
			MYSQLI_ASSOC
		);



		if(	   count($usuarioCadastrado) > 0 										//Se encontrou um usuário com o mesmo login
			&& isset($_POST["login"])												//E o login foi enviado para atualização
			&& $usuarioCadastrado[0]["user_nb_id"] != $_POST["id"] 					//E não é o mesmo usuário que está sendo editado
		){
			$_POST["errorFields"][] = "login";
			set_status("ERRO: Login já cadastrado.");
			modificarUsuario();
			exit;
		}
		
		if(empty($_POST["id"])){//Criando novo usuário
			$usuario["user_nb_userCadastro"] = $_SESSION["user_nb_id"];
			$usuario["user_tx_dataCadastro"] = date("Y-m-d H:i:s");

			$id = inserir("user", array_keys($usuario), array_values($usuario));
			$_POST["id"] = $id;
			modificarUsuario();
			exit;
		}

		//Atualizando usuário existente

		atualizarUsuario($usuario);
		$id = $_POST["id"];
		
		$idUserFoto = mysqli_fetch_assoc(query(
			"SELECT user_nb_id FROM user WHERE user_nb_id = {$id} LIMIT 1;"
		));
		$file_type = $_FILES["foto"]["type"]; //returns the mimetype

		$allowed = array("image/jpeg", "image/png", "image/jpg");
		if (in_array($file_type, $allowed) && $_FILES["foto"]["name"] != "" && !empty($_POST["id"])) {

			if (!is_dir("arquivos/user/{$_POST["id"]}/")) {
				mkdir("arquivos/user/{$_POST["id"]}/", 0777, true);
			}

			$arq = enviar("foto", "arquivos/user/{$_POST["id"]}/", "FOTO_{$id}");
			if($arq){
				//Atualizando foto
				atualizar("user", array("user_tx_foto"), array($arq), $idUserFoto["user_nb_id"]);
			}

			$usuario["user_tx_foto"] = $arq;
		}

		if($_POST["id"] == $_SESSION["user_nb_id"]){
			foreach($usuario as $key => $value){
				$_SESSION[$key] = $usuario[$key];
			}
		}

		modificarUsuario();
		exit;
	}

	function excluirUsuario(){
		$usuario = mysqli_fetch_assoc(query(
			"SELECT * FROM user
				LEFT JOIN entidade ON user_nb_entidade = enti_nb_id
				WHERE user_tx_status = 'ativo'
					AND user_nb_id = {$_POST["id"]}
				LIMIT 1;"
		));
		remover("user",$_POST["id"]);
		if(!empty($usuario["enti_nb_id"])){
			remover("entidade", $usuario["enti_nb_id"]);
		}
		index();
		exit;
	}

	function atualizarUsuario(array $usuario){
		if (is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
			if (!empty($_POST["senha"]) && !empty($_POST["senha2"])) {
				$novaSenha = ["user_tx_senha" => md5($_POST["senha"])];
				atualizar("user", array_keys($novaSenha), array_values($novaSenha), $_POST["id"]);
			}
		}

		$sqlCheckNivel = null;
		if(!empty($_POST["id"])){
			$sqlCheckNivel = mysqli_fetch_assoc(query("SELECT user_tx_nivel FROM user WHERE user_nb_id = '{$_POST["id"]}' LIMIT 1;"));
		}
		
		if (isset($sqlCheckNivel["user_tx_nivel"]) && in_array($sqlCheckNivel["user_tx_nivel"], ["Motorista", "Ajudante","Funcionário"])) {
			if (!empty($_POST["senha"]) && !empty($_POST["senha2"])) {
				$novaSenha = ["user_tx_senha" => md5($_POST["senha"])];
				atualizar("user", array_keys($novaSenha), array_values($novaSenha), $_POST["id"]);
			}
		}
		
		$usuario["user_nb_userAtualiza"] = $_SESSION["user_nb_id"];
		$usuario["user_tx_dataAtualiza"] = date("Y-m-d H:i:s");
		
		
		if(!empty($_POST["senha"]) && !empty($_POST["senha2"])){
			$usuario["user_tx_senha"] = md5($_POST["senha"]);
		}
		
		atualizar("user", array_keys($usuario), array_values($usuario), $_POST["id"]);
	}

	function excluirFoto(){
		atualizar("user", ["user_tx_foto"], [""], $_POST["id"]);
		$_SESSION["user_tx_foto"] = "";
		modificarUsuario();
		exit;
	}

	function modificarUsuario(){

		if(!empty($_POST["id"])){
      		if(is_array($_POST["id"])){
				$_POST["id"] = $_POST["id"][0];
			}
			$usuario = mysqli_fetch_assoc(query("SELECT * FROM user WHERE user_nb_id = {$_POST["id"]};"));
			foreach($usuario as $key => $value){
				$key = str_replace(["user_tx_", "user_nb_"], ["", ""], $key);
				if(empty($_POST[$key])){
					$_POST[$key] = $value;
				}
			}
		}

		$editingDriver = in_array(($_POST["nivel"]?? ""), ["Motorista", "Ajudante", "Funcionário"]);
		$loggedUserIsAdmin = is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"));


		if(!empty($_POST["foto"])){
			$img = texto(
				"<a style='color:gray' onclick='javascript:remover_foto(\"{$_POST["id"]}\",\"excluirFoto\",\"\",\"\",\"\",\"\");' >
					<spam class='glyphicon glyphicon-remove'></spam>
					Excluir
				</a>", 
				"<img src='".($_POST["foto"]?? "")."' />", 
				2
			);
		}else{
			$img = texto( 
				"Imagem",
				"<img src='../contex20/img/user.png' />", 
				2
			);
		}

		$campo_foto  = arquivo("Foto (.png, .jpg)", "foto", ($_POST["foto"]?? ""), 4);
		$campo_login = campo("Login*", "login", ($_POST["login"]?? ($_POST["login"]?? "")), 2,"","maxlength='30'");

		$editPermission = (!empty($_POST["id"]) &&			//Se está editando um usuário existente e
			!$editingDriver &&								//O usuário não é motorista e
			(
				$loggedUserIsAdmin ||						//O usuário logado é administrador ou
				$_SESSION["user_nb_id"] == $_POST["id"]		//Editando o próprio usuário
			)
			)
		;

		if($editPermission){

			$campo_nome = campo("Nome*", "nome", ($_POST["nome"]?? ""), 4, "","maxlength='65'");
			
			$niveis = [""];
			switch($_SESSION["user_tx_nivel"]){
				case "Super Administrador":
					$niveis[] = "Super Administrador";
				case "Administrador":
					$niveis[] = "Administrador";
				case "Embarcador":
					$niveis[] = "Embarcador";
			}
			$campo_nivel = combo("Nível*", "nivel", $_POST["nivel"], 2, $niveis, "");
			$campo_status = combo("Status", "status", $_POST["status"], 2, ["ativo" => "Ativo", "inativo" => "Inativo"], "tabindex=04");

			$campo_nascimento = campo_data("Nascido em*", "nascimento", ($_POST["nascimento"]?? ($_POST["nascimento"]?? "")), 2);
			$campo_cpf = campo("CPF", "cpf", $_POST["cpf"], 2, "MASCARA_CPF");
			$campo_rg = campo("RG", "rg", $_POST["rg"], 2, "MASCARA_RG", "maxlength='15'");
			$campo_cidade = combo_net("Cidade/UF", "cidade", $_POST["cidade"], 2, "cidade", "", "", "cida_tx_uf");
			$campo_email = campo("E-mail*", "email", $_POST["email"], 2);
			$campo_telefone = campo("Telefone", "telefone", $_POST["fone"], 2,"MASCARA_FONE");
			$campo_empresa = combo_bd("!Empresa*", "empresa", $_POST["empresa"], 2, "empresa", "onchange='carrega_empresa(this.value)'");
			$campo_expiracao = campo_data("Expira em", "expiracao", $_POST["expiracao"], 2);
			$campo_senha = campo_senha("Senha*", "senha", "", 2, "maxlength='50'");
			$campo_confirma = campo_senha("Confirmar Senha*", "senha2", "", 2,"maxlength='12'");
			$campo_matricula = "";

		}elseif(empty($_POST["id"])){//Se está criando um usuário novo.

			$editPermission = true;

			$campo_nome = campo("Nome*", "nome", ($_POST["nome"]?? ""), 4, "","maxlength='65'");
			
			$niveis = [""];
			switch($_SESSION["user_tx_nivel"]){
				case "Super Administrador":
					$niveis[] = "Super Administrador";
				case "Administrador":
					$niveis[] = "Administrador";
				case "Embarcador":
					$niveis[] = "Embarcador";
			}
			$campo_nivel = combo("Nível*", "nivel", ($_POST["nivel"]?? $niveis), 2, $niveis, "");
			$campo_status = combo("Status", "status", ($_POST["status"]?? "ativo"), 2, ["ativo" => "Ativo", "inativo" => "Inativo"], "tabindex=04");

			$campo_nascimento = campo_data("Nascido em*", "nascimento", ($_POST["nascimento"]?? ""), 2);
			$campo_cpf = campo("CPF", "cpf", ($_POST["cpf"]?? ""), 2, "MASCARA_CPF");
			$campo_rg = campo("RG", "rg", ($_POST["rg"]?? ""), 2, "MASCARA_RG", "maxlength='15'");
			$campo_cidade = combo_net("Cidade/UF", "cidade", ($_POST["cidade"]?? ""), 2, "cidade", "", "", "cida_tx_uf");
			$campo_email = campo("E-mail*", "email", ($_POST["email"]?? ""), 2);
			$campo_telefone = campo("Telefone", "telefone", ($_POST["telefone"]?? ""), 2,"MASCARA_FONE");
			$campo_empresa = combo_bd("!Empresa*", "empresa", ($_POST["empresa"]?? ""), 2, "empresa", "onchange='carrega_empresa(this.value)'");
			$campo_expiracao = campo_data("Expira em", "expiracao", ($_POST["expiracao"]?? ""), 2);
			$campo_senha = campo_senha("Senha*", "senha", "", 2,"maxlength='12'");
			$campo_confirma = campo_senha("Confirmar Senha*", "senha2", "", 2,"maxlength='12'");
			$campo_matricula = "";

		}else{

			$campo_nome = texto("Nome*", ($_POST["nome"]?? ""), 4, "for='nome'");
			$campo_nivel = texto("Nível*", ($_POST["nivel"]?? ""), 2);
			$campo_status = "";
			$data_nascimento = ($_POST["nascimento"] != "0000-00-00") ? date("d/m/Y", strtotime($_POST["nascimento"])) : "00/00/0000";
			$campo_nascimento = texto("Nascido em*", $data_nascimento, 2, "");
			$campo_cpf = texto("CPF", ($_POST["cpf"]?? ""), 2, "style=''");
			$campo_rg = texto("RG", ($_POST["rg"]?? ""), 2, "style=''");
			
			if(!empty($_POST["cidade"])){
				$cidade_query = query("SELECT * FROM cidade WHERE cida_tx_status = 'ativo' AND cida_nb_id = ".$_POST["cidade"]."");
				$cidade = mysqli_fetch_array($cidade_query);
			}else{
				$cidade = ["cida_tx_nome" => ""];
			}
			$campo_cidade = texto("Cidade/UF", (!empty($cidade["cida_tx_nome"])? "[".$cidade["cida_tx_uf"]."] ".$cidade["cida_tx_nome"]: ""), 2, "style=''");
			
			$campo_email = texto("E-mail*", ($_POST["email"]?? ""), 2, "style=''");
			$campo_telefone = texto("Telefone", ($_POST["fone"]?? ""), 2, "style=''");
			
			if(!empty($_POST["empresa"])){
				$empresa_query = query("SELECT * FROM empresa WHERE empr_tx_status = 'ativo' AND empr_nb_id = ".$_POST["empresa"]."");
				$empresa = mysqli_fetch_array($empresa_query);
			}

			$campo_empresa = texto("Empresa*", (!empty($empresa["empr_tx_nome"])? $empresa["empr_tx_nome"]: ""), 3, "style=''");
			$data_expiracao  = ($_POST["expiracao"] != "0000-00-00") ? date("d/m/Y", strtotime($_POST["expiracao"])) : "00/00/0000";
			$campo_expiracao = texto("Expira em", $data_expiracao, 2, "style=''");
			$campo_login = texto("Login", ($_POST["login"]?? ($_POST["login"]?? "")), 2);
			$campo_senha = "";
			$campo_confirma = "";
			if($editingDriver){
				$entidade = carregar("entidade", $_POST["entidade"]);
				$campo_matricula = texto("Matricula", ($entidade["enti_tx_matricula"]?? ""), 2, "");
			}else{
				$campo_matricula = "";
			}
		}

		if($editPermission){
			cabecalho("Cadastro de Usuário");
		}else{
			cabecalho("Detalhes do Usuário");
		}

		$fields = [
			"<div class='img-section'>
				{$img}
				{$campo_foto}
			 	</div>",
			$campo_nome,
			$campo_status,
			$campo_login,
			$campo_senha,
			$campo_confirma,
			$campo_nivel,
			$campo_matricula,
			$campo_nascimento,

			$campo_cpf,
			$campo_rg,
			$campo_cidade,
			$campo_email,
			$campo_telefone,
			$campo_empresa,
			$campo_expiracao,
		];

		$buttons = [];
		$buttons[] = botao((!empty($_POST["id"])? "Atualizar": "Gravar"), "cadastrarUsuario", "id,editPermission", ($_POST["id"]?? "").",".strval($editPermission),"","","btn btn-success");
		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], "cadastro_usuario.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_usuario.php";
			}
		}
		if(!empty($_POST["HTTP_REFERER"])){
			$buttons[] = criarBotaoVoltar();
		}

		echo abre_form("Dados do Usuário");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		echo linha_form($fields);
		

		if (!empty($_POST["userCadastro"]) && !empty($_POST["userAtualiza"]) && ($_POST["userCadastro"] > 0 || $_POST["userAtualiza"] > 0)) {
			$a_userCadastro = carregar("user", $_POST["userCadastro"]);
			$txtCadastro = "Registro inserido por ".($a_userCadastro["user_tx_login"]?? "admin").(!empty($_POST["dataCadastro"])?" às ".data($_POST["dataCadastro"], 1): "").".";
			$cAtualiza[] = 
					"<div class='col-sm-4 margin-bottom-5'>
						<label>Última Atualização:</label>
						<p class='text-left' style=''>".$txtCadastro."</p>
					</div>"
				;
			if ($_POST["userAtualiza"] > 0) {
				$a_userAtualiza = carregar("user", $_POST["userAtualiza"]);
				$txtAtualiza = "Registro atualizado por $a_userAtualiza[user_tx_login] às ".data($_POST["dataAtualiza"], 1).".";
				$cAtualiza[] = 
					"<div class='col-sm-4 margin-bottom-5'>
						<label>Última Atualização:</label>
						<p class='text-left' style=''>".$txtAtualiza."</p>
					</div>"
				;
			}
			echo "<br>";
			echo linha_form($cAtualiza);
		}

		echo fecha_form($buttons);
		echo "<form name='form_excluir_arquivo' method='post' action='cadastro_usuario.php'>
				<input type='hidden' name='id' value=''>
				<input type='hidden' name='nome_arquivo' value=''>
				<input type='hidden' name='acao' value=''>
			</form>
			<script>
			function remover_foto(id, acao, arquivo) {
						if (confirm('Deseja realmente excluir o arquivo ' + arquivo + '?')) {
							document.form_excluir_arquivo.id.value = id;
							document.form_excluir_arquivo.nome_arquivo.value = arquivo;
							document.form_excluir_arquivo.acao.value = acao;
							document.form_excluir_arquivo.submit();
						}
			}
			</script>";

		rodape();
	}


	function index() {
		global $CONTEX;
		
		if (!empty($_GET["id"])){
			if ($_GET["id"] != $_SESSION["user_nb_id"]) {
				echo "ERRO: Usuário não autorizado!";
				echo "<script>window.location.replace('".$CONTEX["path"]."/index.php');</script>";
				exit;
			}
			$_POST["id"] = $_GET["id"];
			modificarUsuario();
			exit;
		}

		if (in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])) {
			$_POST["id"] = $_SESSION["user_nb_id"];
			modificarUsuario();
			exit;
		}
		$extraEmpresa = " AND empr_tx_situacao = 'ativo' ORDER BY empr_tx_nome";

		if ($_SESSION["user_nb_empresa"] > 0 && is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
			$extraEmpresa .= " AND empr_nb_id = '".$_SESSION["user_nb_empresa"]."'";
		}

		cabecalho("Cadastro de Usuário");

		if(!isset($_POST["busca_status"])){
			$_POST["busca_status"] = "ativo";
		}

		$extra = 
			(!empty($_POST["busca_codigo"])? 								" AND user_nb_id = ".$_POST['busca_codigo']: "").
			(!empty($_POST["busca_nome_like"])? 									" AND user_tx_nome LIKE '%".$_POST["busca_nome_like"]."%'": "").
			(!empty($_POST["busca_login_like"])? 								" AND user_tx_login LIKE '%".$_POST["busca_login_like"]."%'": "").
			(!empty($_POST["busca_nivel"])? 								" AND user_tx_nivel = '".$_POST["busca_nivel"]."'": "").
			(!empty($_POST["busca_cpf"])? 									" AND user_tx_cpf = '".$_POST["busca_cpf"]."'": "").
			(!empty($_POST["busca_empresa"])? 								" AND user_nb_empresa = ".$_POST["busca_empresa"]: "").
			(!empty($_POST["busca_status"])? 								" AND user_tx_status = '".strtolower($_POST["busca_status"])."'": "").
			(is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))? 	" AND user_tx_nivel NOT LIKE '%Administrador%'": "")
		;


		$niveis = [""];
		switch($_SESSION["user_tx_nivel"]){
			case "Super Administrador":
				$niveis[] = "Super Administrador";
			case "Administrador":
				$niveis[] = "Administrador";
			case "Funcionário":
				$niveis[] = "Funcionário";
			default;
				$niveis[] = "Motorista";
				$niveis[] = "Ajudante";
			break;
		}

		$fields = [
			campo("Código", 		"busca_codigo", 	($_POST["busca_codigo"]?? ""), 	1, "", "maxlength='6'"),
			campo("Nome", 			"busca_nome_like", 		($_POST["busca_nome_like"]?? ""), 	3, "", "maxlength='65'"),
			campo("CPF", 			"busca_cpf", 		($_POST["busca_cpf"]?? ""), 	2, "MASCARA_CPF"),
			campo("Login", 			"busca_login_like", 		($_POST["busca_login_like"]?? ""), 	3, "", "maxlength='30'"),
			combo("Nível", 			"busca_nivel", 		($_POST["busca_nivel"]?? ""), 	2, $niveis),
			combo("Status", 		"busca_status", 	($_POST["busca_status"]?? ""), 	2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]),
			combo_bd("!Empresa", 	"busca_empresa", 	($_POST["busca_empresa"]?? ""), 3, "empresa", "onchange='carrega_empresa(this.value)'", $extraEmpresa)
		];

		$buttons[] = botao("Buscar", "index");

		if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
			$buttons[] = botao("Inserir", "modificarUsuario","","","","","btn btn-success");
		}

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons);

		/*/Grid{
			$iconeModificar = 	criarSQLIconeTabela("user_nb_id","modificarUsuario","Modificar","glyphicon glyphicon-search");
			$iconeExcluir = 	criarSQLIconeTabela("user_nb_id","excluirUsuario","Excluir","glyphicon glyphicon-remove","Deseja inativar o registro?");

			$sqlFields = [
				"user_nb_id",
				"user_tx_nome",
				"enti_tx_matricula",
				"user_tx_cpf",
				"user_tx_login",
				"user_tx_nivel",
				"user_tx_email",
				"user_tx_fone",
				"empr_tx_nome",
				"user_tx_status"
			];

			$sql = 
				"SELECT ".implode(", ", $sqlFields).",
					{$iconeModificar} as iconeModificar,
					IF(user_tx_status = 'ativo', {$iconeExcluir}, NULL) as iconeExcluir
				FROM user
					LEFT JOIN empresa ON empresa.empr_nb_id = user.user_nb_empresa
					LEFT JOIN entidade ON user_nb_entidade = enti_nb_id
					WHERE 1 {$extra};"
			;

			$gridFields = [
				"CÓDIGO" => "user_nb_id",
				"NOME" => "user_tx_nome",
				"MATRICULA" => "enti_tx_matricula",
				"CPF" => "user_tx_cpf",
				"LOGIN" => "user_tx_login",
				"NÍVEL" => "user_tx_nivel",
				"E-MAIL" => "user_tx_email",
				"TELEFONE" => "user_tx_fone",
				"EMPRESA" => "empr_tx_nome",
				"STATUS" => "user_tx_status",
				"<spam class='glyphicon glyphicon-search'></spam>" => "iconeModificar",
				"<spam class='glyphicon glyphicon-remove'></spam>" => "iconeExcluir"
			];
			
			grid($sql, array_keys($gridFields), array_values($gridFields));
		//}*/

		//Grid dinâmico{
			$gridFields = [
				"CÓDIGO" 		=> "user_nb_id",
				"NOME" 			=> "user_tx_nome",
				"MATRICULA" 	=> "enti_tx_matricula",
				"CPF" 			=> "user_tx_cpf",
				"LOGIN" 		=> "user_tx_login",
				"NÍVEL" 		=> "user_tx_nivel",
				"E-MAIL" 		=> "user_tx_email",
				"TELEFONE" 		=> "user_tx_fone",
				"EMPRESA" 		=> "empr_tx_nome",
				"STATUS" 		=> "user_tx_status"
			];

			$camposBusca = [
				"busca_codigo" 		=> "user_nb_id",
				"busca_nome_like" 	=> "user_tx_nome",
				"busca_cpf" 		=> "user_tx_cpf",
				"busca_login_like" 	=> "user_tx_login",
				"busca_nivel" 		=> "user_tx_nivel",
				"busca_status" 		=> "user_tx_status",
				"busca_empresa" 	=> "empr_tx_nome"
			];

			$queryBase = 
				"SELECT ".implode(", ", array_values($gridFields))." FROM user"
				." LEFT JOIN empresa ON empresa.empr_nb_id = user.user_nb_empresa"
				." LEFT JOIN entidade ON user_nb_entidade = enti_nb_id"
			;

			$actions = criarIconesGrid(
				["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
				["cadastro_usuario.php", "cadastro_usuario.php"],
				["modificarUsuario()", "excluirUsuario()"]
			);
	
			$actions["functions"][1] .= 
				"esconderInativar('glyphicon glyphicon-remove search-remove', 9);"
			;
	
			$gridFields["actions"] = $actions["tags"];
	
			$jsFunctions =
				"const funcoesInternas = function(){
					".implode(" ", $actions["functions"])."
				}"
			;

			echo gridDinamico("tabelaMotoristas", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		//}


		rodape();
	}