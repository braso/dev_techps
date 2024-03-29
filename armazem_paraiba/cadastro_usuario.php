<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/
	
	include "conecta.php";

	function combo_empresa($nome,$variavel,$modificador,$tamanho,$opcao, $opcao2,$extra=''){
		$t_opcao=count($opcao);
		for($i=0;$i<$t_opcao;$i++){
			$selected = ($opcao[$i] == $modificador)? 'selected': '';
			$c_opcao = '<option value="'.$opcao[$i].'" '.$selected.'>'.$opcao2[$i].'</option>';
		}

		$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
					<label><b>'.$nome.'</b></label>
					<select name="'.$variavel.'" class="form-control input-sm" '.$extra.'>
						'.$c_opcao.'
					</select>
				</div>';

		return $campo;
	}

	function exclui_usuario(){
		remover('user',$_POST['id']);
		index();
		exit;
	}

	function modifica_usuario() {
		global $a_mod;

		$a_mod = carregar('user', $_POST['id']);

		layout_usuario();
		exit;
	}

	function cadastra_usuario() {
		global $a_mod;

		if(isset($_POST['editPermission'])){
			$_POST['editPermission'] = (intval($_POST['editPermission']) == 1);
		}else{
			$_POST['editPermission'] = false;
		}

		$error_msg = "ERRO: Insira os campos ";
		$hasError = false;
    	if ($_POST['editPermission'] == true) {
			$check_fields = [
				//['nome', 'msg_erro']
				['nome', 'Nome, '],
				['login', 'Login, '],
				['senha', 'Senha, '],
				['nascimento', 'Data de nascimento, '],
				['email', 'Email, '],
				['empresa', 'Empresa, ']
			];
			foreach ($check_fields as $field) {
				if (!isset($_POST[$field[0]]) || empty($_POST[$field[0]])) {
					$error_msg .= $field[1];
					$hasError = true;
				}
			}
		}else{
			if(empty($_POST['senha']) || empty($_POST['senha2'])){
				$error_msg .= 'Senha e Confirmação, ';
			}
		}

		if(is_int(strpos($_SESSION['user_tx_nivel'], "Administrador")) && isset($_POST['nivel']) && empty($_POST['nivel'])){	//Se usuário = Administrador && nivelUsuario indefinido
			$error_msg .= 'Nível, ';
		}
		if($_POST['senha'] != $_POST['senha2']){
			$error_msg .= "Confirmação de senha correta, ";
		}
		if($hasError){
			set_status(substr($error_msg, 0, strlen($error_msg)-2).".");
			modifica_usuario();
			exit;
		}
		$usuario = ['user_tx_senha' => md5($_POST['senha'])];

		$campos_variaveis = [
			['user_tx_nome','nome'],
			//Nível está mais abaixo
			//Status está mais abaixo
			['user_tx_login','login'],
			['user_tx_nascimento','nascimento'],
			['user_tx_cpf', 'cpf'],
			['user_tx_rg', 'rg'],
			['user_nb_cidade', 'cidade'],
			['user_tx_email','email'],
			['user_tx_fone','telefone'],
			['user_nb_empresa','empresa'],
			['user_tx_expiracao', 'expiracao']
		];
		foreach($campos_variaveis as $campo){
			if(isset($_POST[$campo[1]]) && !empty($_POST[$campo[1]])){
				$usuario[$campo[0]] = $_POST[$campo[1]];
			}
		}

		$usuario['user_tx_status'] = !empty($_POST['status'])? $_POST['status']: 'ativo';

		if(is_int(strpos($_SESSION['user_tx_nivel'], "Administrador")) && !empty($_POST['nivel'])){
			$usuario['user_tx_nivel'] =  $_POST['nivel'];
		}

		if (!empty($_POST['nivel']) && in_array($_POST['nivel'], ['Motorista', 'Ajudante']) && (!isset($_POST['cpf']) || empty($_POST['cpf']))) {
			set_status("ERRO: CPF obrigatório para motorista/ajudante.");
			modifica_usuario();
			exit;
		}

		if((empty($_POST['senha']) || empty($_POST['senha2'])) && is_bool(strpos($_SESSION['user_tx_nivel'], "Administrador"))){
			set_status("ERRO: Preencha o campo senha e confirme-a.");
			modifica_usuario();
			exit;
		}

		$usuarioCadastrado = mysqli_fetch_all(
			query(
				"SELECT * FROM user
					WHERE user_tx_status = 'ativo'
						AND user_tx_login = '".$_POST['login']."'
					LIMIT 1;"
			),
			MYSQLI_ASSOC
		);


		if(	count($usuarioCadastrado) > 0 											//Se encontrou um usuário com o mesmo login
			&& $usuarioCadastrado[0]['user_nb_id'] != $_POST['id'] 					//E não é o mesmo usuário que está sendo editado
			&& isset($_POST['login'])												//E o login foi enviado para atualização
		){
			set_status("ERRO: Login já cadastrado.");
			modifica_usuario();
			exit;
		}
		
		if(!$_POST['id']){//Criando novo usuário
			$usuario['user_nb_userCadastro'] = $_SESSION['user_nb_id'];
			$usuario['user_tx_dataCadastro'] = date("Y-m-d H:i:s");

			inserir('user', array_keys($usuario), array_values($usuario));
			$_POST['id'] = ultimo_reg('user');
			layout_usuario();
			exit;
			
		}else{//Atualizando usuário existente
			if (is_bool(strpos($_SESSION['user_tx_nivel'], "Administrador"))){
				if (!empty($_POST['senha']) && !empty($_POST['senha2'])) {
					atualizar('user', ['user_tx_senha'], [md5($_POST['senha'])], $_POST['id']);
				}
			}else{
				if(!empty($_POST['id'])){
					$sqlCheckNivel = mysqli_fetch_assoc(query("SELECT user_tx_nivel FROM user WHERE user_nb_id = '".$_POST['id']."' LIMIT 1;"));
				}else{
					$sqlCheckNivel = null;
				}

				if (isset($sqlCheckNivel['user_tx_nivel']) && in_array($sqlCheckNivel['user_tx_nivel'], ['Motorista', 'Ajudante'])) {
					if (!empty($_POST['senha']) && !empty($_POST['senha2'])) {
						$novaSenha = ['user_tx_senha' => md5($_POST['senha'])];
					}
					atualizar('user', array_keys($novaSenha), array_values($novaSenha), $_POST['id']);
					index();
					exit;
				}
				$usuario['user_nb_userAtualiza'] = $_SESSION['user_nb_id'];
				$usuario['user_tx_dataAtualiza'] = date("Y-m-d H:i:s");

				if (!empty($_POST['senha']) && !empty($_POST['senha2'])) {
					$usuario['user_tx_senha'] = md5($_POST['senha']);
				}

				atualizar('user', array_keys($usuario), array_values($usuario), $_POST['id']);
			}
		}

		index();
		exit;
	}

	function layout_usuario() {
		global $a_mod;
	
		if(!empty($_POST['id']) &&												//Se está editando um usuário existente e
			!in_array($a_mod['user_tx_nivel'], ['Motorista', 'Ajudante']) && 	//Esse usuário não é motorista e
			(
				is_int(strpos($_SESSION['user_tx_nivel'], "Administrador")) ||	//O usuário logado é administrador ou
				$_SESSION['user_nb_id'] == $_POST['id']							//Editando o próprio usuário
			)
		){
			$editPermission = true;

			$campo_nome = campo('Nome*', 'nome', ($a_mod['user_tx_nome']?? ''), 4, '','maxlength="65"');
			
			$niveis = [''];
			switch($_SESSION['user_tx_nivel']){
				case "Super Administrador":
					$niveis[] = "Super Administrador";
				case "Administrador":
					$niveis[] = "Administrador";
				case "Funcionário":
					$niveis[] = "Funcionário";
			}
			$campo_nivel = combo('Nível*', 'nivel', $a_mod['user_tx_nivel'], 2, $niveis, "style='margin-bottom:-10px;'");
			$campo_status = combo('Status', 'status', $a_mod['user_tx_status'], 2, ['ativo' => 'Ativo', 'inativo' => 'Inativo'], 'tabindex=04');

			$campo_login = campo('Login*', 'login', $a_mod['user_tx_login'], 2,'','maxlength="30"');
			$campo_nascimento = campo_data('Dt. Nascimento*', 'nascimento', ($a_mod['user_tx_nascimento']?? ($_POST['nascimento']?? '')), 2);
			$campo_cpf = campo('CPF', 'cpf', $a_mod['user_tx_cpf'], 2, 'MASCARA_CPF');
			$campo_rg = campo('RG', 'rg', $a_mod['user_tx_rg'], 2, 'MASCARA_RG', 'maxlength="15"');
			$campo_cidade = combo_net('Cidade/UF', 'cidade', $a_mod['user_nb_cidade'], 3, 'cidade', '', '', 'cida_tx_uf');
			$campo_email = campo('E-mail*', 'email', $a_mod['user_tx_email'], 3);
			$campo_telefone = campo('Telefone', 'telefone', $a_mod['user_tx_fone'], 3,'MASCARA_FONE');
			$campo_empresa = combo_bd('!Empresa*', 'empresa', $a_mod['user_nb_empresa'], 3, 'empresa', 'onchange="carrega_empresa(this.value)"');
			$campo_expiracao = campo_data('Dt. Expiraçao', 'expiracao', $a_mod['user_tx_expiracao'], 2);
			$campo_senha = campo_senha('Senha*', 'senha', "", 2,'maxlength="50"');
			$campo_confirma = campo_senha('Confirmar Senha*', 'senha2', "", 2,'maxlength="12"');
			$campo_matricula = '';

		}elseif(empty($_POST['id'])){//Se está criando um usuário novo.

			$editPermission = true;

			$campo_nome = campo('Nome*', 'nome', ($_POST['nome']?? ''), 4, '','maxlength="65"');
			
			$niveis = [''];
			switch($_SESSION['user_tx_nivel']){
				case "Super Administrador":
					$niveis[] = "Super Administrador";
				case "Administrador":
					$niveis[] = "Administrador";
				case "Funcionário":
					$niveis[] = "Funcionário";
			}
			$campo_nivel = combo('Nível*', 'nivel', ($_POST['nivel']?? ''), 2, $niveis, "style='margin-bottom:-10px;'");
			$campo_status = combo('status', 'status', $a_mod['enti_tx_status'], 2, ['ativo' => 'Ativo', 'inativo' => 'Inativo'], 'tabindex=04');

			$campo_login = campo('Login*', 'login', ($_POST['login']?? ''), 2,'','maxlength="30"');
			$campo_nascimento = campo_data('Dt. Nascimento*', 'nascimento', ($_POST['nascimento']?? ''), 2);
			$campo_cpf = campo('CPF', 'cpf', ($_POST['cpf']?? ''), 2, 'MASCARA_CPF');
			$campo_rg = campo('RG', 'rg', ($_POST['rg']?? ''), 2, 'MASCARA_RG', 'maxlength="15"');
			$campo_cidade = combo_net('Cidade/UF', 'cidade', ($_POST['cidade']?? ''), 3, 'cidade', '', '', 'cida_tx_uf');
			$campo_email = campo('E-mail*', 'email', ($_POST['email']?? ''), 3);
			$campo_telefone = campo('Telefone', 'telefone', ($_POST['telefone']?? ''), 3,'MASCARA_FONE');
			$campo_empresa = combo_bd('!Empresa*', 'empresa', ($_POST['empresa']?? ''), 3, 'empresa', 'onchange="carrega_empresa(this.value)"');
			$campo_expiracao = campo_data('Dt. Expiraçao', 'expiracao', ($_POST['expiracao']?? ''), 2);
			$campo_senha = campo_senha('Senha*', 'senha', "", 2,'maxlength="12"');
			$campo_confirma = campo_senha('Confirmar Senha*', 'senha2', "", 2,'maxlength="12"');
			$campo_matricula = '';

		}else{
			//Entrará aqui caso (editando e o user_nivel != motorista ou ajudante) ou (session_nivel != administrador e não editando próprio usuário)

			$editPermission = false;
			$campo_nome = texto('Nome*', ($a_mod['user_tx_nome']?? ''), 3, "style='margin-bottom:-10px'; for='nome'");
			$campo_login = texto('Login*', ($a_mod['user_tx_login']?? ''), 3, "style='margin-bottom:-10px;'");
			$campo_nivel = texto('Nível*', ($a_mod['user_tx_nivel']?? ''), 2, "style='margin-bottom:-10px;'");
			$data_nascimento = ($a_mod['user_tx_nascimento'] != '0000-00-00') ? date("d/m/Y", strtotime($a_mod['user_tx_nascimento'])) : '00/00/0000' ;
			$campo_nascimento = texto('Dt. Nascimento*', $data_nascimento, 2, "style='margin-bottom:-10px;'");
			$campo_cpf = texto('CPF', ($a_mod['user_tx_cpf']?? ''), 2, "style='margin-bottom:-10px; margin-top: 10px;'");
			$campo_rg = texto('RG', ($a_mod['user_tx_rg']?? ''), 2, "style='margin-bottom:-10px; margin-top: 10px;'");
			
			if(!empty($a_mod['user_nb_cidade'])){
				$cidade_query = query("SELECT * FROM `cidade` WHERE cida_tx_status = 'ativo' AND cida_nb_id = ".$a_mod['user_nb_cidade']."");
				$cidade = mysqli_fetch_array($cidade_query);
			}else{
				$cidade = ['cida_tx_nome' => ''];
			}
			$campo_cidade = texto('Cidade/UF', ($cidade['cida_tx_nome']?? ''), 2, "style='margin-bottom:-10px; margin-top: 10px;'");
			
			$campo_email = texto('E-mail*', ($a_mod['user_tx_email']?? ''), 2, "style='margin-bottom:-10px; margin-top: 10px;'");
			$campo_telefone = texto('Telefone', ($a_mod['user_tx_fone']?? ''), 2, "style='margin-bottom:-10px; margin-top: 10px;'");
			
			if(!empty($a_mod['user_nb_empresa'])){
				$empresa_query = query("SELECT * FROM `empresa` WHERE empr_tx_status = 'ativo' AND empr_nb_id = ".$a_mod['user_nb_empresa']."");
				$empresa = mysqli_fetch_array($empresa_query);	
			}

			$campo_empresa = texto('Empresa*', (!empty($empresa['empr_tx_nome'])? $empresa['empr_tx_nome']: ''), 3, "style='margin-bottom:-10px; margin-top: 10px;'");
			$data_expiracao  = ($a_mod['user_tx_expiracao'] != '0000-00-00') ? date("d/m/Y", strtotime($a_mod['user_tx_expiracao'])) : '00/00/0000' ;
			$campo_expiracao = texto('Dt. Expiraçao', $data_expiracao, 2, "style='margin-bottom:-10px; margin-top: 10px;'");
			if (is_int(strpos($_SESSION['user_tx_nivel'], "Administrador"))){
				$campo_senha = campo_senha('Senha*', 'senha', "", 2);
				$campo_confirma = campo_senha('Confirmar Senha*', 'senha2', "", 2);	
			}else{
				$campo_senha = '';
				$campo_confirma = '';
			}
			$campo_matricula = texto('Matricula', ($a_mod['user_tx_matricula']?? ''), 2, "style='margin-bottom:-10px;'");
		}

		if($editPermission){
			cabecalho("Cadastro de Usuário");
		}else{
			cabecalho("Detalhes do Usuário");
		}

		$c = [
			$campo_nome,
			$campo_nivel,
			$campo_status,
			$campo_login,
			$campo_senha,
			$campo_confirma,
			$campo_matricula,
			$campo_nascimento,

			$campo_cpf,
			$campo_rg,
			$campo_cidade,
			$campo_email,
			$campo_telefone,
			$campo_empresa,
			$campo_expiracao
		];

		$b = [];
		if($editPermission || is_int(strpos($_SESSION['user_tx_nivel'], "Administrador"))){
			$b[] = botao('Gravar', 'cadastra_usuario', 'id,editPermission', ($_POST['id']?? '').','.strval($editPermission),'','','btn btn-success');
		}
		$b[] = botao('Voltar', 'index');

		abre_form('Dados do Usuário');
		linha_form($c);
		

		if ($a_mod['user_nb_userCadastro'] > 0 || $a_mod['user_nb_userAtualiza'] > 0) {
			$a_userCadastro = carregar('user', $a_mod['user_nb_userCadastro']);
			$txtCadastro = "Registro inserido por ".($a_userCadastro['user_tx_login']?? 'admin').(!empty($a_mod['user_tx_dataCadastro'])?" às ".data($a_mod['user_tx_dataCadastro'], 1): '').".";
			$cAtualiza[] = texto("Data de Cadastro", "$txtCadastro", 5);
			if ($a_mod['user_nb_userAtualiza'] > 0) {
				$a_userAtualiza = carregar('user', $a_mod['user_nb_userAtualiza']);
				$txtAtualiza = "Registro atualizado por $a_userAtualiza[user_tx_login] às " . data($a_mod['user_tx_dataAtualiza'], 1) . ".";
				$cAtualiza[] = texto("Última Atualização", "$txtAtualiza", 5);
			}
			echo "<br>";
			linha_form($cAtualiza);
		}
		
		fecha_form($b);
		rodape();
	}


	function index() {
		global $CONTEX;
		
		if (!empty($_GET['id'])){
			if ($_GET['id'] != $_SESSION['user_nb_id']) {
				echo "ERRO: Usuário não autorizado!";
				echo "<script>window.location.replace('".$CONTEX['path']."/index.php');</script>";
				exit;
			}
			$_POST['id'] = $_GET['id'];
			modifica_usuario();
			exit;
		}

		if (in_array($_SESSION['user_tx_nivel'], ['Motorista', 'Ajudante'])) {
			$_POST['id'] = $_SESSION['user_nb_id'];
			modifica_usuario();
		}
		$extraEmpresa = " AND empr_tx_situacao != 'inativo' ORDER BY empr_tx_nome";

		if ($_SESSION['user_nb_empresa'] > 0 && is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$extraEmpresa .= " AND empr_nb_id = '$_SESSION[user_nb_empresa]'";
		}

		cabecalho("Cadastro de Usuário");

		if(empty($_POST['busca_status'])){
			$_POST['busca_status'] = 'ativo';
		}

		$extra = 
			(!empty($_POST['busca_codigo'])? 								" AND user_nb_id = '".$_POST['busca_codigo']."'": "").
			(!empty($_POST['busca_nome'])? 									" AND user_tx_nome LIKE '%".$_POST['busca_nome']."%'": "").
			(!empty($_POST['busca_login'])? 								" AND user_tx_login LIKE '%".$_POST['busca_login']."%'": "").
			((isset($_POST['busca_nivel']) && strtolower($_POST['busca_nivel']) != "todos")? 	" AND user_tx_nivel = '".$_POST['busca_nivel']."'": "").
			(!empty($_POST['busca_cpf'])? 									" AND user_tx_cpf = '".$_POST['busca_cpf']."'": "").
			(!empty($_POST['busca_empresa'])? 								" AND user_nb_empresa = '".$_POST['busca_empresa']."'": "").
			((strtolower($_POST['busca_status']) != 'todos')? 				" AND user_tx_status = '".strtolower($_POST['busca_status'])."'": "").
			(is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))? 	" AND user_tx_nivel != 'Administrador'": '')
		;
		if($_SESSION['user_tx_nivel'] != 'Super Administrador'){
			$extra .= " AND user_tx_nivel != 'Super Administrador'";
		}


		$niveis = ["Todos"];
		switch($_SESSION['user_tx_nivel']){
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

		$c = [
			campo('Código', 		'busca_codigo', 	($_POST['busca_codigo']?? ''), 	1, '', 'maxlength="6"'),
			campo('Nome', 			'busca_nome', 		($_POST['busca_nome']?? ''), 	3, '', 'maxlength="65"'),
			campo('CPF', 			'busca_cpf', 		($_POST['busca_cpf']?? ''), 	2, 'MASCARA_CPF'),
			campo('Login', 			'busca_login', 		($_POST['busca_login']?? ''), 	3, '', 'maxlength="30"'),
			combo('Nível', 			'busca_nivel', 		($_POST['busca_nivel']?? ''), 	2, $niveis),
			combo('Status', 		'busca_status', 	($_POST['busca_status']?? ''), 	2, ['todos' => 'Todos', 'ativo' => 'Ativo', 'inativo' => 'Inativo']),
			combo_bd('!Empresa', 	'busca_empresa', 	($_POST['busca_empresa']?? ''), 3, 'empresa', 'onchange="carrega_empresa(this.value)"', $extraEmpresa)
		];

		$b[] = botao('Buscar', 'index');

		if(is_int(strpos($_SESSION['user_tx_nivel'], 'Administrador'))){
			$b[] = botao('Inserir', 'layout_usuario','','','','','btn btn-success');
		}

		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);

		$sql = 
			"SELECT *, empresa.empr_tx_nome, entidade.enti_tx_matricula FROM user 
				LEFT JOIN empresa ON empresa.empr_nb_id = user.user_nb_empresa 
				LEFT JOIN entidade ON user_nb_entidade = enti_nb_id
				WHERE 1
					$extra"
		;

		$valores = mysqli_fetch_all(
			query($sql),
			MYSQLI_ASSOC
		);

		for($f = 0; $f < count($valores); $f++){
			$valores[$f] = [
				'user_nb_id' => $valores[$f]['user_nb_id'],
				'user_tx_nome' => $valores[$f]['user_tx_nome'],
				'user_tx_matricula' => $valores[$f]['user_tx_matricula'],
				'user_tx_cpf' => $valores[$f]['user_tx_cpf'],
				'user_tx_login' => $valores[$f]['user_tx_login'],
				'user_tx_nivel' => $valores[$f]['user_tx_nivel'],
				'user_tx_email' => $valores[$f]['user_tx_email'],
				'user_tx_fone' => $valores[$f]['user_tx_fone'],
				'empr_tx_nome' => $valores[$f]['empr_tx_nome'],
				'user_tx_status' => $valores[$f]['user_tx_status'],
				'modificar_usuario' => icone_modificar($valores[$f]['user_nb_id'], 'modifica_usuario'),
				'excluir_usuario' => icone_excluir($valores[$f]['user_nb_id'], 'exclui_usuario')
			];
		}


		$cab = ['CÓDIGO', 'NOME','MATRICULA', 'CPF', 'LOGIN', 'NÍVEL', 'E-MAIL', 'TELEFONE', 'EMPRESA', 'STATUS', '', ''];
		$val = [
			'user_nb_id',
			'user_tx_nome',
			'user_tx_matricula',
			'user_tx_cpf',
			'user_tx_login',
			'user_tx_nivel',
			'user_tx_email',
			'user_tx_fone',
			'empr_tx_nome',
			'user_tx_status',
			'icone_modificar(user_nb_id,modifica_usuario)',
			'icone_excluir(user_nb_id,exclui_usuario)'
		];

		// noSQLGrid($cab, $valores);
		grid($sql, $cab, $val);
		rodape();
	}
?>
