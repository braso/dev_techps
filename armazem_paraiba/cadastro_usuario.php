<?php
/*
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	*/
	include_once "utils/utils.php";
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
			["user_nb_empresa","empresa"]
		];
		foreach($campos_variaveis as $campo){
			if(isset($_POST[$campo[1]]) && !empty($_POST[$campo[1]])){
				$usuario[$campo[0]] = $_POST[$campo[1]];
			}
		}
		$usuario["user_tx_expiracao"] = !empty($_POST["expiracao"])? $_POST["expiracao"]: null;

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

			// =========================================================================
			// ATUALIZAÇÃO DO CICLO DE VIDA DO RFID (Gestão de Ativos)
			// =========================================================================
			$rfid_selecionado = !empty($_POST["rfid_id"]) ? trim($_POST["rfid_id"]) : "";
			$id_usuario_rfid = (int)$_POST["id"]; // Pega o ID que acabou de ser gerado ou atualizado
			
			// 1. DEVOLVER PARA A GAVETA: Limpa cartões ativos do usuário
			$sql_limpeza = "UPDATE rfids SET rfids_tx_status = 'disponivel', rfids_nb_entidade_id = NULL WHERE rfids_nb_entidade_id = {$id_usuario_rfid} AND rfids_tx_status = 'ativo'";
			if ($rfid_selecionado != "") {
				$sql_limpeza .= " AND rfids_nb_id != " . (int)$rfid_selecionado;
			}
			query($sql_limpeza);

			// 2. VINCULAR NOVO CARTÃO: Ativa o selecionado
			if ($rfid_selecionado != "") {
				query("UPDATE rfids SET rfids_tx_status = 'ativo', rfids_nb_entidade_id = {$id_usuario_rfid} WHERE rfids_nb_id = " . (int)$rfid_selecionado);
			}
			// =========================================================================

			set_status("Cadastro inserido com sucesso!");
			modificarUsuario();
			exit;
		}

		//Atualizando usuário existente
		atualizarUsuario($usuario);
		$id = $_POST["id"];
		
		if(empty($_POST["id"])){//Criando novo usuário
            $usuario["user_nb_userCadastro"] = $_SESSION["user_nb_id"];
            $usuario["user_tx_dataCadastro"] = date("Y-m-d H:i:s");

            $id = inserir("user", array_keys($usuario), array_values($usuario));
            $_POST["id"] = $id;
            
            // ---> COLE O BLOCO DO RFID AQUI <---

            set_status("Cadastro inserido com sucesso!");
            modificarUsuario();
            exit;
        }

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
				set_status("Cadastro atualizado com sucesso!");
			}

			$usuario["user_tx_foto"] = $arq;
		}

		if($_POST["id"] == $_SESSION["user_nb_id"]){
			foreach($usuario as $key => $value){
				$_SESSION[$key] = $usuario[$key];
			}
		}

		// =========================================================================
        // ATUALIZAÇÃO DO CRÁCHA (RFID) NO BANCO DE DADOS
        // =========================================================================
        $rfid_selecionado = !empty($_POST["rfid_id"]) ? trim($_POST["rfid_id"]) : "";
        $id_do_usuario = (int)$_POST["id"]; // O ID do usuário que acabou de ser salvo/atualizado

        // 1. DEVOLVER PARA A GAVETA: Tira da mão deste usuário qualquer cartão ativo que ele tinha
        // (Isso garante que se ele trocar de crachá, o antigo volta a ficar disponível)
        query("UPDATE rfids SET rfids_tx_status = 'disponivel', rfids_nb_entidade_id = NULL WHERE rfids_nb_entidade_id = {$id_do_usuario} AND rfids_tx_status = 'ativo'");

        // 2. VINCULAR O NOVO: Se o chefe selecionou um cartão na tela, ativa ele para este usuário
        if ($rfid_selecionado != "") {
            query("UPDATE rfids SET rfids_tx_status = 'ativo', rfids_nb_entidade_id = {$id_do_usuario} WHERE rfids_nb_id = " . (int)$rfid_selecionado);
        }
        // =========================================================================

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
		set_status("Cadastro atualizado com sucesso!");
	}

	function excluirFoto(){
		atualizar("user", ["user_tx_foto"], [""], $_POST["id"]);
		$_SESSION["user_tx_foto"] = "";
		modificarUsuario();
		exit;
	}

	function modificarUsuario(){
		echo '<style>
		@media print{
			div.col-sm-4.margin-bottom-5.campo-fit-content > input,
			div.col-sm-4.margin-bottom-5.campo-fit-content > label,
			form > div:nth-child(3) > div:nth-child(6),
			form > div:nth-child(3) > div:nth-child(4),
			form > div.form-actions
			{
				display: none;
			}
			@page{
				size: landscape;
			}
		}
		</style>';
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

		$hasMenuPermission = false;
		$perfilId = 0;
		if(!empty($_SESSION["user_nb_id"])){
			$rowPerfil = mysqli_fetch_assoc(query("SELECT perfil_nb_id FROM usuario_perfil WHERE ativo = 1 AND user_nb_id = ? LIMIT 1", "i", [$_SESSION["user_nb_id"]]));
			if(!empty($rowPerfil["perfil_nb_id"])) $perfilId = (int)$rowPerfil["perfil_nb_id"];
		}
		if($perfilId > 0){
			$rowPerm = mysqli_fetch_assoc(query(
				"SELECT 1 FROM perfil_menu_item p JOIN menu_item m ON m.menu_nb_id = p.menu_nb_id WHERE p.perfil_nb_id = ? AND p.perm_ver = 1 AND m.menu_tx_ativo = 1 AND m.menu_tx_path = '/cadastro_usuario.php' LIMIT 1",
				"i",
				[$perfilId]
			));
			$hasMenuPermission = !empty($rowPerm);
		}
		$loggedUserIsAdmin = ($loggedUserIsAdmin || $hasMenuPermission);


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
		$campo_expiracao = campo_data("Expira em", "expiracao", ($_POST["expiracao"]?? ""), 2);

        // BUSCA DE CARTÕES RFID (Gestão de Ativos)
        $rfidOptions = [" " => "Sem RFID"];
        $userIdForRfid = !empty($_POST["id"]) ? (int)$_POST["id"] : 0;
        
        if ($userIdForRfid > 0) {
            $condRfid = "WHERE (rfids_tx_status = 'disponivel' AND rfids_nb_entidade_id IS NULL) OR (rfids_nb_entidade_id = {$userIdForRfid})";
        } else {
            $condRfid = "WHERE rfids_tx_status = 'disponivel' AND rfids_nb_entidade_id IS NULL";
        }

        $sqlBuscaRfids = "SELECT rfids_nb_id, rfids_tx_uid, rfids_tx_descricao, rfids_tx_status FROM rfids {$condRfid} ORDER BY rfids_tx_uid ASC";
        
        // 1. BLINDAGEM: Executa a query
        $rsRfids = query($sqlBuscaRfids);
        
        // 2. Só tenta ler se a query NÃO deu erro (se não for false)
        if ($rsRfids) {
            while($r = mysqli_fetch_assoc($rsRfids)){
                $label = $r["rfids_tx_uid"];
                if(!empty($r["rfids_tx_descricao"])) {
                    $label .= " - " . $r["rfids_tx_descricao"];
                }
                
                if($r["rfids_tx_status"] != 'disponivel' && $r["rfids_tx_status"] != 'ativo') {
                    $label .= " (STATUS: " . strtoupper($r["rfids_tx_status"]) . ")";
                }
                $rfidOptions[$r["rfids_nb_id"]] = $label;
            }
        } else {
            // Se falhar, avisa na tela em vez de derrubar o sistema inteiro
            global $conn; // Puxa a conexão caso ela esteja no escopo global
            echo "<div style='background:#ffcccc; color:red; padding:10px; margin:10px 0; border:1px solid red;'>
                    <b>Erro no SQL do RFID:</b> " . mysqli_error($conn) . "<br>
                    <b>Query:</b> " . $sqlBuscaRfids . "
                  </div>";
        }

        $selectedRfid = "";
        if($userIdForRfid > 0){
            $sqlAssigned = "SELECT rfids_nb_id FROM rfids WHERE rfids_nb_entidade_id = {$userIdForRfid} LIMIT 1";
            $rsAssigned = query($sqlAssigned);
            
            // BLINDAGEM: Verifica também a segunda query
            if ($rsAssigned) {
                $rowAssigned = mysqli_fetch_assoc($rsAssigned);
                if(!empty($rowAssigned)) {
                    $selectedRfid = $rowAssigned["rfids_nb_id"];
                }
            }
        }
        // =========================================================================
		$editPermission = (!empty($_POST["id"]) && (
			$loggedUserIsAdmin ||
			(($_SESSION["user_nb_id"] ?? null) == ($_POST["id"] ?? null))
		));

		$niveis = ["" => ""];
		switch($_SESSION["user_tx_nivel"]){
			case "Super Administrador":
				$niveis["Super Administrador"] = "Super Administrador";
			case "Administrador":
				$niveis["Administrador"] = "Administrador";
			case "Embarcador":
				$niveis["Embarcador"] = "Embarcador";
			case "Funcionário":
				$niveis["Funcionário"] = "Funcionário";
		
		}

		if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Funcionário"])){
			$niveis = [$_SESSION["user_tx_nivel"] => $_SESSION["user_tx_nivel"]];
		}

		if($editPermission){

			$campo_nome = campo("Nome*", "nome", ($_POST["nome"]?? ""), 4, "","maxlength='65'");

			$campo_nivel = combo("Nível*", "nivel", $_POST["nivel"], 2, $niveis, "");
			$campo_status = combo("Status", "status", $_POST["status"], 2, ["ativo" => "Ativo", "inativo" => "Inativo"], "tabindex=04");
            //ícone com a cor laranja (warning) típica de edição
            $icone_editar_rfid = "&nbsp;&nbsp;<a href='javascript:void(0);' onclick='editarRfidNaTela()' title='Editar status deste crachá' style='color: #f0ad4e;'><span class='glyphicon glyphicon-pencil'></span></a>";
            $campo_rfid = combo("Crachá (RFID)" . $icone_editar_rfid, "rfid_id", $selectedRfid, 2, $rfidOptions, "id='select_rfid_id'");
			$campo_nascimento = campo_data("Nascido em*", "nascimento", ($_POST["nascimento"]?? ($_POST["nascimento"]?? "")), 2);
			$campo_cpf = campo("CPF", "cpf", $_POST["cpf"], 2, "MASCARA_CPF");
			$campo_rg = campo("RG", "rg", $_POST["rg"], 2, "MASCARA_RG", "maxlength='15'");
			$campo_cidade = combo_net("Cidade/UF", "cidade", $_POST["cidade"], 2, "cidade", "", "", "cida_tx_uf");
			$campo_email = campo("E-mail*", "email", $_POST["email"], 2);
			$campo_telefone = campo("Telefone", "telefone", $_POST["fone"], 2,"MASCARA_FONE");
			$campo_empresa = combo_bd("!Empresa*", "empresa", $_POST["empresa"]?? $_SESSION["user_nb_empresa"], 2, "empresa", "onchange='carrega_empresa(this.value)'");
			$campo_senha = campo_senha("Senha*", "senha", "", 2, "maxlength='50'");
			$campo_confirma = campo_senha("Confirmar Senha*", "senha2", "", 2,"maxlength='50'");
			$campo_matricula = "";

		}elseif(empty($_POST["id"])){//Se está criando um usuário novo.

			$editPermission = true;

			$campo_nome = campo("Nome*", "nome", ($_POST["nome"]?? ""), 4, "","maxlength='65'");
			
			$campo_nivel = combo("Nível*", "nivel", ($_POST["nivel"]?? $niveis), 2, $niveis, "");
			$campo_status = combo("Status", "status", ($_POST["status"]?? "ativo"), 2, ["ativo" => "Ativo", "inativo" => "Inativo"], "tabindex=04");
			//ícone com a cor laranja (warning) típica de edição
            $icone_editar_rfid = "&nbsp;&nbsp;<a href='javascript:void(0);' onclick='editarRfidNaTela()' title='Editar status deste crachá' style='color: #f0ad4e;'><span class='glyphicon glyphicon-pencil'></span></a>";
            $campo_rfid = combo("Crachá (RFID)" . $icone_editar_rfid, "rfid_id", $selectedRfid, 2, $rfidOptions, "id='select_rfid_id'");
			$campo_nascimento = campo_data("Nascido em*", "nascimento", ($_POST["nascimento"]?? ""), 2);
			$campo_cpf = campo("CPF", "cpf", ($_POST["cpf"]?? ""), 2, "MASCARA_CPF");
			$campo_rg = campo("RG", "rg", ($_POST["rg"]?? ""), 2, "MASCARA_RG", "maxlength='15'");
			$campo_cidade = combo_net("Cidade/UF", "cidade", ($_POST["cidade"]?? ""), 2, "cidade", "", "", "cida_tx_uf");
			$campo_email = campo("E-mail*", "email", ($_POST["email"]?? ""), 2);
			$campo_telefone = campo("Telefone", "telefone", ($_POST["telefone"]?? ""), 2,"MASCARA_FONE");
			$campo_empresa = combo_bd("!Empresa*", "empresa", ($_POST["empresa"]?? $_SESSION["user_nb_empresa"]), 2, "empresa", "onchange='carrega_empresa(this.value)'");
			$campo_senha = campo_senha("Senha*", "senha", "", 2,"maxlength='50'");
			$campo_confirma = campo_senha("Confirmar Senha*", "senha2", "", 2,"maxlength='50'");
			$campo_matricula = "";

		}else{

			$campo_nome = texto("Nome*", ($_POST["nome"]?? "-"), 4, "for='nome'");
			$campo_nivel = texto("Nível*", ($_POST["nivel"]?? "-"), 2);
			$campo_status = "";
			$campo_nascimento = texto("Nascido em*", !empty($_POST["nascimento"])? date("d/m/Y", strtotime($_POST["nascimento"])): "", 2, "");
			$campo_cpf = texto("CPF", ($_POST["cpf"]?? "-"), 2, "style=''");
			$campo_rg = texto("RG", ($_POST["rg"]?? "-"), 2, "style=''");
			
			if(!empty($_POST["cidade"])){
				$cidade_query = query("SELECT * FROM cidade WHERE cida_tx_status = 'ativo' AND cida_nb_id = ".$_POST["cidade"]."");
				$cidade = mysqli_fetch_array($cidade_query);
			}else{
				$cidade = ["cida_tx_nome" => ""];
			}
			$campo_cidade = texto("Cidade/UF", (!empty($cidade["cida_tx_nome"])? "[".$cidade["cida_tx_uf"]."] ".$cidade["cida_tx_nome"]: "-"), 2, "style=''");
			
			$campo_email = texto("E-mail*", ($_POST["email"]?? "-"), 2, "style=''");
			$campo_telefone = texto("Telefone", ($_POST["fone"]?? "-"), 2, "style=''");
			
			if(!empty($_POST["empresa"])){
				$empresa_query = query("SELECT * FROM empresa WHERE empr_tx_status = 'ativo' AND empr_nb_id = ".$_POST["empresa"]."");
				$empresa = mysqli_fetch_array($empresa_query);
			}

			$campo_empresa = texto("Empresa*", (!empty($empresa["empr_tx_nome"])? $empresa["empr_tx_nome"]: "-"), 3, "style=''");
			$campo_login = texto("Login", ($_POST["login"]?? ($_POST["login"]?? "-")), 2);
			$campo_senha = "";
			$campo_confirma = "";
			if($loggedUserIsAdmin){
				$campo_senha = campo_senha("Senha*", "senha", "", 2,"maxlength='50'");
				$campo_confirma = campo_senha("Confirmar Senha*", "senha2", "", 2,"maxlength='50'");
			}
			if($editingDriver){
				$entidade = carregar("entidade", (string)($_POST["entidade"] ?? ""));
				$campo_matricula = texto("Matricula", ($entidade["enti_tx_matricula"]?? "-"), 2, "");
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
			$campo_rfid,
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
		// Capturamos o referer atual se ele ainda não existir no POST
		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
		};

		// Se o usuário veio da tela de RFID, o "Voltar" dele deve ser a lista de usuários, 
		if(is_int(strpos($_POST["HTTP_REFERER"], "cadastro_rfid.php"))){
			$_POST["HTTP_REFERER"] = "cadastro_usuario.php"; 
		};

		if(!empty($_POST["HTTP_REFERER"])){
			$buttons[] = criarBotaoVoltar();
		};

		if (!empty($_POST["id"])) {
			$buttons[] = '<button class="btn default" type="button" onclick="imprimir()">Imprimir</button>';
		};

		echo abre_form("Dados do Usuário");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		echo linha_form($fields);
		

		$cAtualiza = [];

        if (!empty($_POST["userCadastro"]) && $_POST["userCadastro"] > 0) {
            $a_userCadastro = carregar("user", $_POST["userCadastro"]);
            $txtCadastro = "Registro inserido por ".($a_userCadastro["user_tx_login"]?? "admin").(!empty($_POST["dataCadastro"])?" às ".data($_POST["dataCadastro"], 1): "").".";
            $cAtualiza[] = 
                "<div class='col-sm-4 margin-bottom-5'>
                    <label>Dados de Criação:</label>
                    <p class='text-left' style=''>".$txtCadastro."</p>
                </div>";
        }

        if (!empty($_POST["userAtualiza"]) && $_POST["userAtualiza"] > 0) {
            $a_userAtualiza = carregar("user", $_POST["userAtualiza"]);
            $txtAtualiza = "Registro atualizado por ".($a_userAtualiza["user_tx_login"]?? "admin").(!empty($_POST["dataAtualiza"])?" às ".data($_POST["dataAtualiza"], 1): "").".";
            $cAtualiza[] = 
                "<div class='col-sm-4 margin-bottom-5'>
                    <label>Última Atualização:</label>
                    <p class='text-left' style=''>".$txtAtualiza."</p>
                </div>";
        }

        // 3. Só imprime a linha se houver alguma informação para mostrar
        if (!empty($cAtualiza)) {
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

            function imprimir() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = './impressao/ficha_usuario.php';
                form.target = '_blank';

                const inputID = document.createElement('input');
                inputID.type = 'hidden';
                inputID.name = 'id_usuario';
                inputID.value = ".$_POST["id"].";
                form.appendChild(inputID);
                
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }

            function editarRfidNaTela() {
                // Pega o valor que está selecionado na caixinha agora
                var selectRfid = document.getElementById('select_rfid_id') || document.getElementsByName('rfid_id')[0];
                var idRfidSelecionado = selectRfid.value;

                // Validação: Vê se tem algo selecionado
                if (!idRfidSelecionado || idRfidSelecionado.trim() === '') {
                    Swal.fire('Atenção', 'Selecione um crachá válido na lista antes de clicar em editar.', 'warning');
                    return;
                }

                // Cria um formulário dinâmico e envia via POST para a tela de RFID
                var formRfid = document.createElement('form');
                formRfid.method = 'POST';
                formRfid.action = 'cadastro_rfid.php'; 

                var fieldId = document.createElement('input');
                fieldId.type = 'hidden';
                fieldId.name = 'id';
                fieldId.value = idRfidSelecionado;
                formRfid.appendChild(fieldId);

                var fieldAcao = document.createElement('input');
                fieldAcao.type = 'hidden';
                fieldAcao.name = 'acao';
                fieldAcao.value = 'editarRfid'; // Dispara o form de edição do seu framework
                formRfid.appendChild(fieldAcao);

                // ==========================================================
                // O PULO DO GATO: O PHP imprime o ID diretamente aqui dentro!
                // ==========================================================
                var idUsuarioAtual = '" . (!empty($_POST["id"]) ? $_POST["id"] : "") . "';
                
                if (idUsuarioAtual !== '') {
                    var fieldRetorno = document.createElement('input');
                    fieldRetorno.type = 'hidden';
                    fieldRetorno.name = 'id_usuario_retorno';
                    fieldRetorno.value = idUsuarioAtual;
                    formRfid.appendChild(fieldRetorno);
                }

                document.body.appendChild(formRfid);
                formRfid.submit();
            }

            </script>";

			if (!empty($_POST["entidade"])) {
				$arquivos = mysqli_fetch_all(query(
					"SELECT * FROM documento_funcionario"
						." WHERE docu_nb_entidade = ".$_POST["entidade"]
				),MYSQLI_ASSOC);
				echo "</div><div class='col-md-12'><div class='col-md-12 col-sm-12'>".arquivosFuncionario("Documentos", $_POST["entidade"], $arquivos);
			}

		rodape();
	}


function index() {
        global $CONTEX;
        $permitido = false;
        $hasMenuPermission = false;
        if(in_array($_SESSION["user_tx_nivel"], ["Motorista","Ajudante","Funcionário"])){
            $permitido = true;
        }else{
            $perfilId = 0;
            if(!empty($_SESSION["user_nb_id"])){
                $rowPerfil = mysqli_fetch_assoc(query("SELECT perfil_nb_id FROM usuario_perfil WHERE ativo = 1 AND user_nb_id = ? LIMIT 1", "i", [$_SESSION["user_nb_id"]]));
                if(!empty($rowPerfil["perfil_nb_id"])) $perfilId = (int)$rowPerfil["perfil_nb_id"];
            }
            if($perfilId > 0){
                $rowPerm = mysqli_fetch_assoc(query(
                    "SELECT 1 FROM perfil_menu_item p JOIN menu_item m ON m.menu_nb_id = p.menu_nb_id WHERE p.perfil_nb_id = ? AND p.perm_ver = 1 AND m.menu_tx_ativo = 1 AND m.menu_tx_path = '/cadastro_usuario.php' LIMIT 1",
                    "i",
                    [$perfilId]
                ));
                $permitido = !empty($rowPerm);
                $hasMenuPermission = !empty($rowPerm);
            }
        }
        if(is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador")) && !$permitido){
            $_POST["returnValues"] = json_encode([
                "HTTP_REFERER" => $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/batida_ponto.php"
            ]);
            voltar();
        }
		
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

        if (in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"]) && !$permitido) {
            $_POST["id"] = $_SESSION["user_nb_id"];
            modificarUsuario();
            exit;
        }
		$extraEmpresa = " AND empr_tx_situacao = 'ativo' ORDER BY empr_tx_nome";

        if ($_SESSION["user_nb_empresa"] > 0 && is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador")) && !$permitido) {
            $extraEmpresa .= " AND empr_nb_id = '".$_SESSION["user_nb_empresa"]."'";
        }

		echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>";

		cabecalho("Cadastro de Usuário");

		if(!isset($_POST["busca_status"])){
			$_POST["busca_status"] = "ativo";
		}

		// $extra = 
		// 	(!empty($_POST["busca_codigo"])? 								" AND user_nb_id = ".$_POST['busca_codigo']: "").
		// 	(!empty($_POST["busca_nome_like"])? 							" AND user_tx_nome LIKE '%".$_POST["busca_nome_like"]."%'": "").
		// 	(!empty($_POST["busca_login_like"])? 							" AND user_tx_login LIKE '%".$_POST["busca_login_like"]."%'": "").
		// 	(!empty($_POST["busca_nivel"])? 								" AND user_tx_nivel = '".$_POST["busca_nivel"]."'": "").
		// 	(!empty($_POST["busca_cpf"])? 									" AND user_tx_cpf = '".$_POST["busca_cpf"]."'": "").
		// 	(!empty($_POST["busca_empresa"])? 								" AND user_nb_empresa = ".$_POST["busca_empresa"]: "").
		// 	(!empty($_POST["busca_status"])? 								" AND user_tx_status = '".strtolower($_POST["busca_status"])."'": "").
		// 	(is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))? 	" AND user_tx_nivel NOT LIKE '%Administrador%'": "")
		// ;


		$niveis = ["" => ""];
		switch($_SESSION["user_tx_nivel"]){
			case "Super Administrador":
				$niveis["Super Administrador"] = "Super Administrador";
			case "Administrador":
				$niveis["Administrador"] = "Administrador";
			case "Funcionário":
				$niveis["Funcionário"] = "Funcionário";
			default;
				$niveis["Motorista"] = "Motorista";
				$niveis["Ajudante"] = "Ajudante";
			break;
		}

        $fields = [
            campo("Código", 		"busca_codigo", 		($_POST["busca_codigo"]?? ""), 	1, "", "maxlength='6'"),
            campo("Nome", 			"busca_nome_like", 		($_POST["busca_nome_like"]?? ""), 	3, "", "maxlength='65'"),
            campo("CPF", 			"busca_cpf", 		($_POST["busca_cpf"]?? ""), 	2, "MASCARA_CPF"),
            campo("Login", 			"busca_login_like", 		($_POST["busca_login_like"]?? ""), 	3, "", "maxlength='30'"),
            combo("Nível", 			"busca_nivel", 		($_POST["busca_nivel"]?? ""), 	2, $niveis),
            combo("Status", 		"busca_status", 	($_POST["busca_status"]?? ""), 	2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]),
            combo_bd("!Empresa", 	"busca_empresa", 	($_POST["busca_empresa"]?? $_SESSION["user_nb_empresa"]), 	3, "empresa", "onchange='carrega_empresa(this.value)'", $extraEmpresa),
            combo_bd("!Cargo", 		"busca_operacao", 	($_POST["busca_operacao"]?? ""), 	2, "operacao"),
            combo_bd("!Setor", 		"busca_setor", 	($_POST["busca_setor"]?? ""), 	2, "grupos_documentos"),
            combo_bd("!Subsetor", 	"busca_subsetor", 	($_POST["busca_subsetor"]?? ""), 	2, "sbgrupos_documentos", "", (!empty($_POST["busca_setor"]) ? " AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"])." ORDER BY sbgr_tx_nome ASC" : " ORDER BY sbgr_tx_nome ASC"))
        ];

        $buttons[] = botao("Buscar", "index");

        $canInsert = is_int(strpos($_SESSION["user_tx_nivel"], "Administrador")) || $hasMenuPermission;
        if($canInsert){
            $buttons[] = botao("Inserir", "modificarUsuario","","","","","btn btn-success");
        }

		$buttons[] = '<button class="btn default" type="button" onclick="imprimirTabelaCompleta()">Imprimir</button>';

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons);

		$logoEmpresa = mysqli_fetch_assoc(query(
            "SELECT empr_tx_logo FROM empresa
                    WHERE empr_tx_status = 'ativo'
                        AND empr_tx_Ehmatriz = 'sim'
                    LIMIT 1;"
        ))["empr_tx_logo"];

		echo "<div id='tituloRelatorio' style='display: none;'>
                    <img style='width: 190px; height: 40px;' src='./imagens/logo_topo_cliente.png' alt='Logo Empresa Esquerda'>
					<h1>Cadastro de Usuário</h1>
                    <img style='width: 180px; height: 80px;' src='./$logoEmpresa' alt='Logo Empresa Direita'>
            </div>";

		//Grid dinâmico{
            $gridFields = [
                "CÓDIGO"        => "user_nb_id",
                "NOME"          => "user_tx_nome",
                "MATRICULA"     => "enti_tx_matricula",
                "CPF"           => "user_tx_cpf",
                "LOGIN"         => "user_tx_login",
                "NÍVEL"         => "user_tx_nivel",
                "CARGO"         => "oper_tx_nome",
                "SETOR"         => "grup_tx_nome",
                "SUBSETOR"      => "sbgr_tx_nome",
                "E-MAIL"        => "user_tx_email",
                "TELEFONE"      => "user_tx_fone",
                "EMPRESA"       => "empr_tx_nome",
                "STATUS"        => "user_tx_status",
                "AUTENTICAÇÃO"  => "rfids_nb_id" 
            ];

            $camposBusca = [
                "busca_codigo"      => "user_nb_id",
                "busca_nome_like"   => "user_tx_nome",
                "busca_cpf"         => "user_tx_cpf",
                "busca_login_like"  => "user_tx_login",
                "busca_nivel"       => "user_tx_nivel",
                "busca_status"      => "user_tx_status",
                "busca_empresa"     => "empr_nb_id",
                "busca_operacao"    => "enti_tx_tipoOperacao",
                "busca_setor"       => "enti_setor_id",
                "busca_subsetor"    => "enti_subSetor_id"
            ];

            $queryBase = 
                "SELECT ".implode(", ", array_values($gridFields))." FROM user"
                ." LEFT JOIN empresa ON empresa.empr_nb_id = user.user_nb_empresa"
                ." LEFT JOIN entidade ON user_nb_entidade = enti_nb_id"
                ." LEFT JOIN operacao ON enti_tx_tipoOperacao = oper_nb_id"
                ." LEFT JOIN grupos_documentos ON enti_setor_id = grup_nb_id"
                ." LEFT JOIN sbgrupos_documentos subg ON enti_subSetor_id = subg.sbgr_nb_id"
                ." LEFT JOIN rfids ON rfids.rfids_nb_entidade_id = user.user_nb_id AND rfids.rfids_tx_status = 'ativo'"
            ;

            // 1. Chamamos a utilitária para gerar os botões padrão
            $acoesGrid = gerarAcoesComConfirmacao(
                "cadastro_usuario.php", 
                "modificarUsuario", 
                "excluirUsuario", 
                "Deseja excluir o usuário código: ", 
                "CÓDIGO"
            );

            $gridFields["actions"] = $acoesGrid["tags"];

            // 2. Mesclamos o JS da utilitária com as regras dinâmicas da tela de Usuários
            $jsFunctions = $acoesGrid["js"] . "
                
                // FUNÇÃO RADAR: Descobre em qual índice numérico uma coluna está baseada no nome
                const pegarIndiceColuna = function(nomeColuna) {
                    var index = -1;
                    $('table thead th').each(function(i) {
                        if ($(this).text().trim().toUpperCase() === nomeColuna.toUpperCase()) {
                            index = i;
                            return false; // Interrompe o loop ao encontrar
                        }
                    });
                    return index;
                };

                // FUNÇÃO: Varre a tabela e desenha os ícones HTML de biometria/crachá
                const formatarBiometria = function() {
                    // Descobre onde as colunas estão agora
                    var idxCodigo = pegarIndiceColuna('CÓDIGO');
                    var idxAutenticacao = pegarIndiceColuna('AUTENTICAÇÃO');

                    // Se não achar as colunas, aborta para não quebrar a tela
                    if (idxCodigo === -1 || idxAutenticacao === -1) return;

                    $('table tbody tr').each(function() {
                        var colIdUser = $(this).find('td').eq(idxCodigo).text().trim();
                        var tdAutenticacao = $(this).find('td').eq(idxAutenticacao); 
                        var idRfid = tdAutenticacao.text().trim(); 
                        
                        if (!colIdUser) return;
                        
                        var htmlIcones = '';
                        
                        if (idRfid !== '') {
                            htmlIcones += '<span onclick=\"abrirRfidDireto(' + idRfid + ', ' + colIdUser + ')\" class=\"glyphicon glyphicon-credit-card\" style=\"color: #28a745; font-size: 14px; margin-right: 12px; cursor: pointer;\" title=\"Editar Crachá Ativo\"></span>';
                        } else {
                            htmlIcones += '<span class=\"glyphicon glyphicon-credit-card\" style=\"color: #d6d6d6; font-size: 14px; margin-right: 12px;\" title=\"Sem Crachá Ativo\"></span>';
                        }
                        
                        htmlIcones += '<span class=\"glyphicon glyphicon-hand-up\" style=\"color: #d6d6d6; font-size: 14px; margin-right: 12px;\" title=\"Sem Digital\"></span>';
                        htmlIcones += '<span class=\"glyphicon glyphicon-user\" style=\"color: #d6d6d6; font-size: 14px;\" title=\"Sem Facial\"></span>';
                        
                        tdAutenticacao.html(htmlIcones);
                    });
                };

                window.abrirRfidDireto = function(idRfid, idUsuario) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'cadastro_rfid.php';
                    
                    var inputId = document.createElement('input');
                    inputId.type = 'hidden';
                    inputId.name = 'id';
                    inputId.value = idRfid;
                    form.appendChild(inputId);
                    
                    var inputAcao = document.createElement('input');
                    inputAcao.type = 'hidden';
                    inputAcao.name = 'acao';
                    inputAcao.value = 'editarRfid';
                    form.appendChild(inputAcao);

                    if (idUsuario) {
                        var fieldRetorno = document.createElement('input');
                        fieldRetorno.type = 'hidden';
                        fieldRetorno.name = 'id_usuario_retorno';
                        fieldRetorno.value = idUsuario;
                        form.appendChild(fieldRetorno);
                    }
                    
                    document.body.appendChild(form);
                    form.submit();
                };

                // Executa as funções no ciclo de vida do grid
                var funcoesInternasAntiga = funcoesInternas; 
                funcoesInternas = function(){
                    // Roda o JS da lupa e do SweetAlert
                    if(typeof funcoesInternasAntiga === 'function') funcoesInternasAntiga(); 
                    
                    // Roda a sua formatação de crachás
                    formatarBiometria(); 
                    
                    // Esconde a lixeira baseando-se na posição atualizada da coluna STATUS
                    var idxStatus = pegarIndiceColuna('STATUS');
                    if (idxStatus !== -1) {
                        esconderInativar('glyphicon glyphicon-remove search-button', idxStatus);
                    }
                };
            ";

            echo gridDinamico("tabelaMotoristas", $gridFields, $camposBusca, $queryBase, $jsFunctions);
        //}

		rodape();
	}
