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

	function cadastra_usuario() {
		global $a_mod;

		$_POST["editPermission"] = (isset($_POST["editPermission"]))? (intval($_POST["editPermission"]) == 1): false;

		//Conferir casos de erro e montar mensagem de erro{
			//Campos obrigatórios não preenchidos{
				$baseErrMsg = "ERRO: Campos obrigatórios não preenchidos: ";
				$errorMsg = $baseErrMsg;
				if ($_POST["editPermission"]) {
					$camposObrig = [
						"nome" => "Nome",
						"login" => "Login",
						// "senha" => "Senha",
						"nascimento" => "Data de Nascimento",
						"email" => "Email",
						"empresa" => "Empresa"
					];
					foreach ($camposObrig as $key => $value) {
						if (!isset($_POST[$key]) || empty($_POST[$key])) {
							$_POST["errorFields"][] = $key;
							$errorMsg .= $value.", ";
						}
					}

					if(empty($_POST["id"]) && (empty($_POST["senha"]) || empty($_POST["senha2"]))){
						$_POST["errorFields"][] = "senha";
						$_POST["errorFields"][] = "senha2";
						$errorMsg .= "Senha e Confirmação".", ";
					}

				}else{
					if(empty($_POST["senha"]) || empty($_POST["senha2"])){
						$_POST["errorFields"][] = "senha";
						$_POST["errorFields"][] = "senha2";
						$errorMsg .= "Senha e Confirmação".", ";
					}
				}
	
				if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador")) && isset($_POST["nivel"]) && empty($_POST["nivel"])){	//Se usuárioLogado = Administrador && nivelUsuario indefinido
					$_POST["errorFields"][] = "nivel";
					$errorMsg .= "Nível".", ";
				}
				if(($_POST["senha"] != $_POST["senha2"])){
					$_POST["errorFields"][] = "senha2";
					$errorMsg .= "Confirmação de senha correta".", ";
				}

				if($errorMsg != $baseErrMsg){
					set_status(substr($errorMsg, 0, strlen($errorMsg)-2).".");
					mostrarFormCadastro();
					exit;
				}
			//}

			$baseErrMsg = "ERRO: ";
			$errorMsg = $baseErrMsg;
			if(!empty($_POST["cpf"])){
				$_POST["cpf"] = preg_replace( "/[^0-9]/is", "", $_POST["cpf"]);
				if(strlen($_POST["cpf"]) != 11){
					$_POST["errorFields"][] = "cpf";
					$errorMsg .= "CPF parcial, ";
				}elseif(!validarCPF($_POST["cpf"])){
					$_POST["errorFields"][] = "cpf";
					$errorMsg .= "CPF inválido, ";
				}
			}
			if(!empty($_POST["rg"])){
        		$_POST["rg"] = preg_replace( "/[^0-9]/is", "", $_POST["rg"]);
				if(strlen($_POST["rg"]) != 9){
					$_POST["errorFields"][] = "rg";
					$errorMsg .= "RG parcial, ";
				}
			}

			if($errorMsg != $baseErrMsg){
				set_status(substr($errorMsg, 0, strlen($errorMsg)-2).".");
				mostrarFormCadastro();
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

		if (!empty($_POST["nivel"]) && in_array($_POST["nivel"], ["Motorista", "Ajudante","Funcionário"]) && (!isset($_POST["cpf"]) || empty($_POST["cpf"]))) {
			set_status("ERRO: CPF obrigatório para motorista/ajudante/funcionário.");
			mostrarFormCadastro();
			exit;
		}

		$canUpdateWithoutPassword = (is_int(strpos($_SESSION["user_tx_nivel"], "Administrador")) || $_POST["id"] == $_SESSION["user_nb_id"]);

		if(
			(empty($_POST["senha"]) || empty($_POST["senha2"]))
			&& !$canUpdateWithoutPassword
		){
			set_status("ERRO: Preencha o campo senha e confirme-a.");
			mostrarFormCadastro();
			exit;
		}

		$usuarioCadastrado = mysqli_fetch_all(
			query(
				"SELECT * FROM user"
					." WHERE user_tx_status = 'ativo'"
						." AND user_tx_login = '".($_POST["login"]?? "")."'"
					." LIMIT 1;"
			),
			MYSQLI_ASSOC
		);



		if(	   count($usuarioCadastrado) > 0 										//Se encontrou um usuário com o mesmo login
			&& isset($_POST["login"])												//E o login foi enviado para atualização
			&& $usuarioCadastrado[0]["user_nb_id"] != $_POST["id"] 					//E não é o mesmo usuário que está sendo editado
		){
			set_status("ERRO: Login já cadastrado.");
			mostrarFormCadastro();
			exit;
		}
		
		if(empty($_POST["id"])){//Criando novo usuário
			$usuario["user_nb_userCadastro"] = $_SESSION["user_nb_id"];
			$usuario["user_tx_dataCadastro"] = date("Y-m-d H:i:s");

			$id = inserir("user", array_keys($usuario), array_values($usuario));
			$_POST["id"] = ultimo_reg("user");
			mostrarFormCadastro();
			exit;
			
		}

		//Atualizando usuário existente

		atualiza_usuario($usuario);
		$id = $_POST["id"];
		
		$idUserFoto = mysqli_fetch_assoc(query("SELECT user_nb_id FROM user WHERE user_nb_id = '".$id."' LIMIT 1;"));
		$file_type = $_FILES["foto"]["type"]; //returns the mimetype

		$allowed = array("image/jpeg", "image/gif", "image/png");
		if (in_array($file_type, $allowed) && $_FILES["foto"]["name"] != "" && !empty($_POST["id"])) {

			if (!is_dir("arquivos/user/".$_POST["id"]."/")) {
				mkdir("arquivos/user/".$_POST["id"]."/", 0777, true);
			}

			$arq = enviar("foto", "arquivos/user/".$_POST["id"]."/", "FOTO_".$id);
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

		mostrarFormCadastro();
		exit;
	}

	function deleteUser(){
		remover("user",$_POST["id"]);
		index();
		exit;
	}

	// function modifica_usuario() {
	// 	global $a_mod;
	// 	$a_mod = carregar("user", $_POST["id"]);
	// 	mostrarFormCadastro();
	// 	exit;
	// }

	function atualiza_usuario(array $usuario){
		
		
		//Atualizando a senha caso seja administrador{
		//}

		if (is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
			if (!empty($_POST["senha"]) && !empty($_POST["senha2"])) {
				$novaSenha = ["user_tx_senha" => md5($_POST["senha"])];
				atualizar("user", array_keys($novaSenha), array_values($novaSenha), $_POST["id"]);
			}
		}

		$sqlCheckNivel = null;
		if(!empty($_POST["id"])){
			$sqlCheckNivel = mysqli_fetch_assoc(query("SELECT user_tx_nivel FROM user WHERE user_nb_id = '".$_POST["id"]."' LIMIT 1;"));
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
		mostrarFormCadastro();
		exit;
	}

	function mostrarFormCadastro() {

		global $a_mod;

		if(!empty($_POST["id"])){
			$a_mod = carregar("user", $_POST["id"]);
		}

		$editingDriver = in_array(($a_mod["user_tx_nivel"]?? ""), ["Motorista", "Ajudante", "Funcionário"]);
		$loggedUserIsAdmin = is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"));


		if(!empty($a_mod["user_tx_foto"])){
			$img = texto(
				"<a style='color:gray' onclick='javascript:remover_foto(\"".($a_mod["user_nb_id"]?? "")."\",\"excluirFoto\",\"\",\"\",\"\",\"\");' >
					<spam class='glyphicon glyphicon-remove'></spam>
					Excluir
				</a>", 
				"<img src='".($a_mod["user_tx_foto"]?? "")."' />", 
				2
			);
		}else{
			$img = texto( 
				"Imagem",
				"<img src='../contex20/img/user.png' />", 
				2
			);
		}

		$campo_foto  = arquivo("Foto (.png, .jpg)", "foto", ($a_mod["enti_tx_foto"]?? ""), 4);
		$campo_login = campo("Login*", "login", ($_POST["login"]?? ($a_mod["user_tx_login"]?? "")), 2,"","maxlength='30'");

		if(!empty($_POST["id"]) &&							//Se está editando um usuário existente e
			!$editingDriver &&								//Este usuário não é motorista e
			(
				$loggedUserIsAdmin ||						//O usuário logado é administrador ou
				$_SESSION["user_nb_id"] == $_POST["id"]		//Editando o próprio usuário
			)
		){
			$editPermission = true;

			$campo_nome = campo("Nome*", "nome", ($a_mod["user_tx_nome"]?? ""), 4, "","maxlength='65'");
			
			$niveis = [""];
			switch($_SESSION["user_tx_nivel"]){
				case "Super Administrador":
					$niveis[] = "Super Administrador";
				case "Administrador":
					$niveis[] = "Administrador";
				case "Embarcador":
					$niveis[] = "Embarcador";
			}
			$campo_nivel = combo("Nível*", "nivel", $a_mod["user_tx_nivel"], 2, $niveis, "");
			$campo_status = combo("Status", "status", $a_mod["user_tx_status"], 2, ["ativo" => "Ativo", "inativo" => "Inativo"], "tabindex=04");

			$campo_nascimento = campo_data("Dt. Nascimento*", "nascimento", ($a_mod["user_tx_nascimento"]?? ($_POST["nascimento"]?? "")), 2);
			$campo_cpf = campo("CPF", "cpf", $a_mod["user_tx_cpf"], 2, "MASCARA_CPF");
			$campo_rg = campo("RG", "rg", $a_mod["user_tx_rg"], 2, "MASCARA_RG", "maxlength='15'");
			$campo_cidade = combo_net("Cidade/UF", "cidade", $a_mod["user_nb_cidade"], 2, "cidade", "", "", "cida_tx_uf");
			$campo_email = campo("E-mail*", "email", $a_mod["user_tx_email"], 2);
			$campo_telefone = campo("Telefone", "telefone", $a_mod["user_tx_fone"], 2,"MASCARA_FONE");
			$campo_empresa = combo_bd("!Empresa*", "empresa", $a_mod["user_nb_empresa"], 2, "empresa", "onchange='carrega_empresa(this.value)'");
			$campo_expiracao = campo_data("Dt. Expiração", "expiracao", $a_mod["user_tx_expiracao"], 2);
			$campo_senha = campo_senha("Senha*", "senha", "", 2, "maxlength='50'");
			$campo_confirma = campo_senha("Confirmar Senha*", "senha2", "", 2,"maxlength='12'");
			$campo_matricula = "";

		}elseif(empty($_POST["id"])){//Se está criando um usuário novo.

			$editPermission = true;

			$campo_nome = campo("Nome*", "nome", ($_POST["nome"]?? ""), 2, "","maxlength='65'");
			
			$niveis = [""];
			switch($_SESSION["user_tx_nivel"]){
				case "Super Administrador":
					$niveis[] = "Super Administrador";
				case "Administrador":
					$niveis[] = "Administrador";
				case "mbarcador":
					$niveis[] = "Embarcador";
			}
			$campo_nivel = combo("Nível*", "nivel", ($_POST["nivel"]?? ""), 2, $niveis, "");
			$campo_status = combo("Status", "status", ($a_mod["enti_tx_status"]?? ""), 2, ["ativo" => "Ativo", "inativo" => "Inativo"], "tabindex=04");

			$campo_nascimento = campo_data("Dt. Nascimento*", "nascimento", ($_POST["nascimento"]?? ""), 2);
			$campo_cpf = campo("CPF", "cpf", ($_POST["cpf"]?? ""), 2, "MASCARA_CPF");
			$campo_rg = campo("RG", "rg", ($_POST["rg"]?? ""), 2, "MASCARA_RG", "maxlength='15'");
			$campo_cidade = combo_net("Cidade/UF", "cidade", ($_POST["cidade"]?? ""), 2, "cidade", "", "", "cida_tx_uf");
			$campo_email = campo("E-mail*", "email", ($_POST["email"]?? ""), 2);
			$campo_telefone = campo("Telefone", "telefone", ($_POST["telefone"]?? ""), 2,"MASCARA_FONE");
			$campo_empresa = combo_bd("!Empresa*", "empresa", ($_POST["empresa"]?? ""), 2, "empresa", "onchange='carrega_empresa(this.value)'");
			$campo_expiracao = campo_data("Dt. Expiração", "expiracao", ($_POST["expiracao"]?? ""), 2);
			$campo_senha = campo_senha("Senha*", "senha", "", 2,"maxlength='12'");
			$campo_confirma = campo_senha("Confirmar Senha*", "senha2", "", 2,"maxlength='12'");
			$campo_matricula = "";

		}else{
			//Entrará aqui caso (editando e o user_nivel != motorista, ajudante ou funcionário) ou (session_nivel != administrador e não editando próprio usuário)
			$editPermission = false;
			$campo_nome = texto("Nome*", ($a_mod["user_tx_nome"]?? ""), 2, "for='nome'");
			$campo_nivel = texto("Nível*", ($a_mod["user_tx_nivel"]?? ""), 2);
			$campo_status = "";
			$data_nascimento = ($a_mod["user_tx_nascimento"] != "0000-00-00") ? date("d/m/Y", strtotime($a_mod["user_tx_nascimento"])) : "00/00/0000";
			$campo_nascimento = texto("Dt. Nascimento*", $data_nascimento, 2, "");
			$campo_cpf = texto("CPF", ($a_mod["user_tx_cpf"]?? ""), 2, "style=''");
			$campo_rg = texto("RG", ($a_mod["user_tx_rg"]?? ""), 2, "style=''");
			
			if(!empty($a_mod["user_nb_cidade"])){
				$cidade_query = query("SELECT * FROM cidade WHERE cida_tx_status = 'ativo' AND cida_nb_id = ".$a_mod["user_nb_cidade"]."");
				$cidade = mysqli_fetch_array($cidade_query);
			}else{
				$cidade = ["cida_tx_nome" => ""];
			}
			$campo_cidade = texto("Cidade/UF", (!empty($cidade["cida_tx_nome"])? "[".$cidade["cida_tx_uf"]."] ".$cidade["cida_tx_nome"]: ""), 2, "style=''");
			
			$campo_email = texto("E-mail*", ($a_mod["user_tx_email"]?? ""), 2, "style=''");
			$campo_telefone = texto("Telefone", ($a_mod["user_tx_fone"]?? ""), 2, "style=''");
			
			if(!empty($a_mod["user_nb_empresa"])){
				$empresa_query = query("SELECT * FROM empresa WHERE empr_tx_status = 'ativo' AND empr_nb_id = ".$a_mod["user_nb_empresa"]."");
				$empresa = mysqli_fetch_array($empresa_query);
			}

			$campo_empresa = texto("Empresa*", (!empty($empresa["empr_tx_nome"])? $empresa["empr_tx_nome"]: ""), 3, "style=''");
			$data_expiracao  = ($a_mod["user_tx_expiracao"] != "0000-00-00") ? date("d/m/Y", strtotime($a_mod["user_tx_expiracao"])) : "00/00/0000";
			$campo_expiracao = texto("Dt. Expiração", $data_expiracao, 2, "style=''");
			$campo_senha = campo_senha("Senha*", "senha", "", 2);
			$campo_confirma = campo_senha("Confirmar Senha*", "senha2", "", 2);
			$campo_matricula = texto("Matricula", ($a_mod["user_tx_matricula"]?? ""), 2, "");
		}

		if($editPermission){
			cabecalho("Cadastro de Usuário");
		}else{
			cabecalho("Detalhes do Usuário");
		}

		$fields = [
			"<div class='img-section'>"
			.$img.
			$campo_foto
			."</div>",
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
		$buttons[] = botao((!empty($_POST["id"])? "Atualizar": "Gravar"), "cadastra_usuario", "id,editPermission", ($_POST["id"]?? "").",".strval($editPermission),"","","btn btn-success");
		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], "cadastro_usuario.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_usuario.php";
			}
		}
		if(!empty($_POST["HTTP_REFERER"])){
			$buttons[] = botao("Voltar", "voltar");
		}

		abre_form("Dados do Usuário");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		linha_form($fields);
		

		if (!empty($a_mod["user_nb_userCadastro"]) && !empty($a_mod["user_nb_userAtualiza"]) && ($a_mod["user_nb_userCadastro"] > 0 || $a_mod["user_nb_userAtualiza"] > 0)) {
			$a_userCadastro = carregar("user", $a_mod["user_nb_userCadastro"]);
			$txtCadastro = "Registro inserido por ".($a_userCadastro["user_tx_login"]?? "admin").(!empty($a_mod["user_tx_dataCadastro"])?" às ".data($a_mod["user_tx_dataCadastro"], 1): "").".";
			$cAtualiza[] = 
					"<div class='col-sm-4 margin-bottom-5'>
						<label>Última Atualização:</label>
						<p class='text-left' style=''>".$txtCadastro."</p>
					</div>"
				;
			if ($a_mod["user_nb_userAtualiza"] > 0) {
				$a_userAtualiza = carregar("user", $a_mod["user_nb_userAtualiza"]);
				$txtAtualiza = "Registro atualizado por $a_userAtualiza[user_tx_login] às " . data($a_mod["user_tx_dataAtualiza"], 1) . ".";
				$cAtualiza[] = 
					"<div class='col-sm-4 margin-bottom-5'>
						<label>Última Atualização:</label>
						<p class='text-left' style=''>".$txtAtualiza."</p>
					</div>"
				;
			}
			echo "<br>";
			linha_form($cAtualiza);
		}

		fecha_form($buttons);
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
			mostrarFormCadastro();
			exit;
		}

		if (in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])) {
			$_POST["id"] = $_SESSION["user_nb_id"];
			mostrarFormCadastro();
			exit;
		}
		$extraEmpresa = " AND empr_tx_situacao = 'ativo' ORDER BY empr_tx_nome";

		if ($_SESSION["user_nb_empresa"] > 0 && is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
			$extraEmpresa .= " AND empr_nb_id = '".$_SESSION["user_nb_empresa"]."'";
		}

		cabecalho("Cadastro de Usuário");

		if(empty($_POST["busca_status"])){
			$_POST["busca_status"] = "ativo";
		}

		$extra = 
			(!empty($_POST["busca_codigo"])? 								" AND user_nb_id = ".$_POST['busca_codigo']: "").
			(!empty($_POST["busca_nome"])? 									" AND user_tx_nome LIKE '%".$_POST["busca_nome"]."%'": "").
			(!empty($_POST["busca_login"])? 								" AND user_tx_login LIKE '%".$_POST["busca_login"]."%'": "").
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
			campo("Nome", 			"busca_nome", 		($_POST["busca_nome"]?? ""), 	3, "", "maxlength='65'"),
			campo("CPF", 			"busca_cpf", 		($_POST["busca_cpf"]?? ""), 	2, "MASCARA_CPF"),
			campo("Login", 			"busca_login", 		($_POST["busca_login"]?? ""), 	3, "", "maxlength='30'"),
			combo("Nível", 			"busca_nivel", 		($_POST["busca_nivel"]?? ""), 	2, $niveis),
			combo("Status", 		"busca_status", 	($_POST["busca_status"]?? ""), 	2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]),
			combo_bd("!Empresa", 	"busca_empresa", 	($_POST["busca_empresa"]?? ""), 3, "empresa", "onchange='carrega_empresa(this.value)'", $extraEmpresa)
		];

		$buttons[] = botao("Buscar", "index");

		if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
			$buttons[] = botao("Inserir", "mostrarFormCadastro","","","","","btn btn-success");
		}

		abre_form();
		linha_form($fields);
		fecha_form($buttons);

		$sql = 
			"SELECT user_nb_id, user_tx_nome, user_tx_matricula, user_tx_cpf, user_tx_login, user_tx_nivel, user_tx_email, user_tx_fone, user_tx_status, empresa.empr_tx_nome, entidade.enti_tx_matricula FROM user"
			." LEFT JOIN empresa ON empresa.empr_nb_id = user.user_nb_empresa"
			." LEFT JOIN entidade ON user_nb_entidade = enti_nb_id"
			." WHERE 1"
				." ".$extra.";"
		;

		$valores = mysqli_fetch_all(
			query($sql),
			MYSQLI_ASSOC
		);

		for($f = 0; $f < count($valores); $f++){
			$valores[$f] = [
				"user_nb_id" => $valores[$f]["user_nb_id"],
				"user_tx_nome" => $valores[$f]["user_tx_nome"],
				"user_tx_matricula" => $valores[$f]["user_tx_matricula"],
				"user_tx_cpf" => $valores[$f]["user_tx_cpf"],
				"user_tx_login" => $valores[$f]["user_tx_login"],
				"user_tx_nivel" => $valores[$f]["user_tx_nivel"],
				"user_tx_email" => $valores[$f]["user_tx_email"],
				"user_tx_fone" => $valores[$f]["user_tx_fone"],
				"empr_tx_nome" => $valores[$f]["empr_tx_nome"],
				"user_tx_status" => $valores[$f]["user_tx_status"],
				"modificar_usuario" => icone_modificar($valores[$f]["user_nb_id"], "mostrarFormCadastro"),
				"excluir_usuario" => icone_excluir($valores[$f]["user_nb_id"], "deleteUser")
			];
		}


		$cab = ["CÓDIGO", "NOME","MATRICULA", "CPF", "LOGIN", "NÍVEL", "E-MAIL", "TELEFONE", "EMPRESA", "STATUS", "", ""];
		$val = [
			"user_nb_id",
			"user_tx_nome",
			"user_tx_matricula",
			"user_tx_cpf",
			"user_tx_login",
			"user_tx_nivel",
			"user_tx_email",
			"user_tx_fone",
			"empr_tx_nome",
			"user_tx_status",
			"icone_modificar(user_nb_id,mostrarFormCadastro)",
			"icone_excluir(user_nb_id,deleteUser)"
		];
		
		grid($sql, $cab, $val);
		rodape();
	}