<?php
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

		$error_msg_base = "ERRO: Insira os campos ";
		$error_msg = $error_msg_base;
		if (!$_POST['id']) {
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
				}
			}
		}

		//Se o usuário é um administrador e não definiu o nível do usuário a ser cadastrado

		if(is_int(strpos($_SESSION['user_tx_nivel'], "Administrador")) && isset($_POST['nivel']) && empty($_POST['nivel'])){
			$error_msg .= 'Nível, ';
		}
		if($_POST['senha'] != $_POST['senha2']){
			$error_msg .= "Confirmação de senha correta, ";
		}
		if($error_msg != $error_msg_base){
			set_status(substr($error_msg, 0, strlen($error_msg)-2).".");
			modifica_usuario();
			exit;
		}

		$bd_campos = ['user_tx_nome', 'user_tx_login', 'user_tx_senha', 'user_tx_nascimento', 'user_tx_email', 'user_tx_fone', 'user_nb_empresa'];
		$valores = [$_POST['nome'], $_POST['login'], $_POST['senha'], $_POST['nascimento'], $_POST['email'], $_POST['telefone'],$_POST['empresa']];

		$campos_variaveis = [
			['user_tx_cpf', 'cpf'],
			['user_tx_rg', 'rg'],
			['user_nb_cidade', 'cidade'],
			['user_tx_expiracao', 'expiracao']
		];
		foreach($campos_variaveis as $campo){
			if(isset($_POST[$campo[1]]) && !empty($_POST[$campo[1]])){
				$bd_campos[] = $campo[0];
				$valores[] = $_POST[$campo[1]];
			}
		}

		if(is_int(strpos($_SESSION['user_tx_nivel'], "Administrador")) && !empty($_POST['nivel'])){
			$bd_campos[] = 'user_tx_nivel';
			$valores[] = $_POST['nivel'];
		}

		if (!empty($_POST['nivel']) && $_POST['nivel'] == 'Motorista' && (!isset($_POST['cpf']) || empty($_POST['cpf']))) {
			set_status("ERRO: CPF obrigatório para motorista.");
			modifica_usuario();
			exit;
		}

		if (empty($_POST['senha']) || empty($_POST['senha2'])) {
			set_status("ERRO: Preencha o campo senha e confirme-a.");
			modifica_usuario();
			exit;
		}

		$usuario = carregar('user', '', 'user_nb_id', $_POST['id']);


		if(!$_POST['id']){//Criando novo usuário
			$sql = query("SELECT * FROM user WHERE user_tx_login = '".$_POST['login']."' LIMIT 1");
			if (num_linhas($sql) > 0){
				set_status("ERRO: Login já cadastrado.");
				$a_mod = $_POST;
				modifica_usuario();
				exit;
			}	

			$bd_campos[] = 'user_tx_status';
			$valores[] = 'ativo';

			$bd_campos = array_merge($bd_campos, ['user_nb_userCadastro', 'user_tx_dataCadastro']);
			$valores = array_merge($valores, [$_SESSION['user_nb_id'], date("Y-m-d H:i:s")]);
			inserir('user', $bd_campos, $valores);
			$_POST['id'] = ultimo_reg('user');

		}else{//Atualizando usuário existente
			if (is_bool(strpos($_SESSION['user_tx_nivel'], "Administrador"))){
				if (!empty($_POST['senha']) && !empty($_POST['senha2'])) {
					atualizar('user', ['user_tx_senha'], [md5($_POST['senha'])], $_POST['id']);
				}
				
			}else{
				$bd_campos = array_merge($bd_campos, ['user_nb_userAtualiza', 'user_tx_dataAtualiza']);
				$valores = array_merge($valores, [$_SESSION['user_nb_id'], date("Y-m-d H:i:s")]);

				if (!empty($_POST['senha']) && !empty($_POST['senha2'])) {
					$bd_campos[] = 'user_tx_senha';
					$valores[] = md5($_POST['senha']);
				}
				atualizar('user', $bd_campos, $valores, $_POST['id']);
			}
		}

		index();
		exit;
	}



	function layout_usuario() {
		global $a_mod;
		cabecalho("Cadastro de Usuário");

		$campo_nivel = texto('Nível*', $a_mod['user_tx_nivel'], 2, "style='margin-bottom:-10px;'");
		if($_GET['id'] || (is_int(strpos($_SESSION['user_tx_nivel'], "Administrador")) && $a_mod['user_tx_nivel'] != 'Motorista')){

			$campo_nome = campo('Nome*', 'nome', $a_mod['user_tx_nome'], 4, '');
			$campo_login = campo('Login*', 'login', $a_mod['user_tx_login'], 2);
			$campo_nascimento = campo_data('Dt. Nascimento*', 'nascimento', $a_mod['user_tx_nascimento'], 2);
			$campo_cpf = campo('CPF', 'cpf', $a_mod['user_tx_cpf'], 2, 'MASCARA_CPF');
			$campo_rg = campo('RG', 'rg', $a_mod['user_tx_rg'], 2);
			$campo_cidade = combo_net('Cidade/UF', 'cidade', $a_mod['user_nb_cidade'], 3, 'cidade', '', '', 'cida_tx_uf');
			$campo_email = campo('E-mail*', 'email', $a_mod['user_tx_email'], 3);
			$campo_telefone = campo('Telefone', 'telefone', $a_mod['user_tx_fone'], 3,'MASCARA_FONE');
			$campo_empresa = combo_bd('!Empresa*', 'empresa', $a_mod['user_nb_empresa'], 3, 'empresa', 'onchange="carrega_empresa(this.value)"');
			$campo_expiracao = campo_data('Dt. Expiraçao', 'expiracao', $a_mod['user_tx_expiracao'], 2);
			$campo_senha = campo_senha('Senha*', 'senha', "", 2);
			$campo_confirma = campo_senha('Confirmar Senha*', 'senha2', "", 2);

		}else{

			$campo_nome = texto('Nome*', $a_mod['user_tx_nome'], 3, "style='margin-bottom:-10px'; for='nome'");
			$campo_login = texto('Login*', $a_mod['user_tx_login'], 3, "style='margin-bottom:-10px;'");
			$data_nascimento = ($a_mod['user_tx_nascimento'] != '0000-00-00') ? date("d/m/Y", strtotime($a_mod['user_tx_nascimento'])) : '00/00/0000' ;
			$campo_nascimento = texto('Dt. Nascimento*', $data_nascimento, 2, "style='margin-bottom:-10px;'");
			$campo_cpf = texto('CPF', $a_mod['user_tx_cpf'], 2, "style='margin-bottom:-10px; margin-top: 10px;'");
			$campo_rg = texto('RG', $a_mod['user_tx_rg'], 2, "style='margin-bottom:-10px; margin-top: 10px;'");
			
			if(isset($a_mod['user_nb_cidade'])){
				$cidade_query = query("SELECT * FROM `cidade` WHERE cida_tx_status = 'ativo' AND cida_nb_id = $a_mod[user_nb_cidade]");
				$cidade = mysqli_fetch_array($cidade_query);
			}else{
				$cidade = ['cida_tx_nome' => ''];
			}
			$campo_cidade = texto('Cidade/UF', $cidade['cida_tx_nome'], 2, "style='margin-bottom:-10px; margin-top: 10px;'");
			
			$campo_email = texto('E-mail*', $a_mod['user_tx_email'], 2, "style='margin-bottom:-10px; margin-top: 10px;'");
			$campo_telefone = texto('Telefone', $a_mod['user_tx_fone'], 2, "style='margin-bottom:-10px; margin-top: 10px;'");
			
			$empresa_query = query("SELECT * FROM `empresa` WHERE empr_tx_status = 'ativo' AND empr_nb_id = $a_mod[user_nb_empresa]");
			$empresa = mysqli_fetch_array($empresa_query);
			
			$campo_empresa = texto('Empresa*', $empresa['empr_tx_nome'], 3, "style='margin-bottom:-10px; margin-top: 10px;'");
			$data_expiracao  = ($a_mod['user_tx_expiracao'] != '0000-00-00') ? date("d/m/Y", strtotime($a_mod['user_tx_expiracao'])) : '00/00/0000' ;
			$campo_expiracao = texto('Dt. Expiraçao', $data_expiracao, 2, "style='margin-bottom:-10px; margin-top: 10px;'");
			if (is_int(strpos($_SESSION['user_tx_nivel'], "Administrador"))){
				$campo_senha = campo_senha('Senha*', 'senha', "", 2);
				$campo_confirma = campo_senha('Confirmar Senha*', 'senha2', "", 2);	
			}else{
				$campo_senha = '';
				$campo_confirma = '';
			}
			$campo_matricula = texto('Matricula', $a_mod['user_tx_matricula'], 2, "style='margin-bottom:-10px;'");

		}

		$c[] = $campo_nome;
		$c[] = $campo_nivel;
		$c[] = $campo_login;
		$c[] = $campo_senha;
		$c[] = $campo_confirma;
		$c[] = $campo_matricula;
		$c[] = $campo_nascimento;

		$c[] = $campo_cpf;
		$c[] = $campo_rg;
		$c[] = $campo_cidade;
		$c[] = $campo_email;
		$c[] = $campo_telefone;
		$c[] = $campo_empresa;
		$c[] = $campo_expiracao;

		$b[] = botao('Gravar', 'cadastra_usuario', 'id', $_POST['id']);
		$b[] = botao('Voltar', 'index');

		abre_form('Dados do Usuário');
		linha_form($c);
		

		if ($a_mod['user_nb_userCadastro'] > 0) {
			$a_userCadastro = carregar('user', $a_mod['user_nb_userCadastro']);
			$txtCadastro = "Registro inserido por $a_userCadastro[user_tx_login] às " . data($a_mod['user_tx_dataCadastro'], 1) . ".";
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
		if ($_GET['id']) {
			if ($_GET['id'] != $_SESSION['user_nb_id']) {
				echo "ERRO: Usuário não autorizado!";
				exit;
			}
			$_POST['id'] = $_GET['id'];
			modifica_usuario();
			exit;
		}

		if ($_SESSION['user_tx_nivel'] == 'Motorista') {
			$_POST['id'] = $_SESSION['user_nb_id'];
			modifica_usuario();
		}
		$extraEmpresa = " AND empr_tx_situacao != 'inativo' ORDER BY empr_tx_nome";

		if ($_SESSION['user_nb_empresa'] > 0 && is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$extraEmpresa .= " AND empr_nb_id = '$_SESSION[user_nb_empresa]'";
		}

		cabecalho("Cadastro de Usuário");

		if(!isset($_POST['busca_status'])){
			$_POST['busca_status'] = 'ativo';
		}elseif(strtolower($_POST['busca_status']) != 'todos'){

		}

		$extra = 
			(($_POST['busca_codigo'])? " AND user_nb_id = '".$_POST['busca_codigo']."'": "").
			(($_POST['busca_nome'])? " AND user_tx_nome LIKE '%".$_POST['busca_nome']."%'": "").
			(($_POST['busca_login'])? " AND user_tx_login LIKE '%".$_POST['busca_login']."%'": "").
			((isset($_POST['busca_nivel']) && strtolower($_POST['busca_nivel']) != "todos")? " AND user_tx_nivel = '".$_POST['busca_nivel']."'": "").
			(($_POST['busca_cpf'])? " AND user_tx_cpf = '".$_POST['busca_cpf']."'": "").
			(($_POST['busca_empresa'])? " AND user_nb_empresa = '".$_POST['busca_empresa']."'": "").
			((strtolower($_POST['busca_status']) != 'todos')? " AND user_tx_status = '".strtolower($_POST['busca_status'])."'": "").
			(is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))? " AND user_tx_nivel NOT LIKE '%Administrador%'": '')
		;


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
			break;
		}

		$c = [
			campo('Código', 'busca_codigo', $_POST['busca_codigo'], 1),
			campo('Nome', 'busca_nome', $_POST['busca_nome'], 3),
			campo('CPF', 'busca_cpf', $_POST['busca_cpf'], 2, 'MASCARA_CPF'),
			campo('Login', 'busca_login', $_POST['busca_login'], 3),
			combo('Nível', 'busca_nivel', $_POST['busca_nivel'], 2, $niveis),
			combo('Status', 'busca_status', $_POST['busca_status'], 2, ['Todos', 'Ativo', 'Inativo']),
			combo_bd('!Empresa', 'busca_empresa', $_POST['busca_empresa'], 3, 'empresa', 'onchange="carrega_empresa(this.value)"', $extraEmpresa)
		];

		$b[] = botao('Buscar', 'index');

		if(is_int(strpos($_SESSION['user_tx_nivel'], 'Administrador'))){
			$b[] = botao('Inserir', 'layout_usuario');
		}

		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);

		$sql = "SELECT * FROM user LEFT JOIN empresa ON empresa.empr_nb_id = user.user_nb_empresa WHERE user_nb_id > 1 $extra";
		
		$cab = ['CÓDIGO', 'NOME', 'CPF', 'LOGIN', 'NÍVEL', 'E-MAIL', 'TELEFONE', 'EMPRESA', 'STATUS', '', ''];
		$val = ['user_nb_id', 'user_tx_nome', 'user_tx_cpf', 'user_tx_login', 'user_tx_nivel', 'user_tx_email', 'user_tx_fone', 'empr_tx_nome', 'user_tx_status', 
			'icone_modificar(user_nb_id,modifica_usuario)', 'icone_excluir(user_nb_id,exclui_usuario)'
		];

		grid($sql, $cab, $val);
		rodape();
	}
?>