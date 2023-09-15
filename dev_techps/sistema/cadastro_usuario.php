<?php

include "conecta.php";

function combo_empresa($nome,$variavel,$modificador,$tamanho,$opcao, $opcao2,$extra=''){
		$t_opcao=count($opcao);
		for($i=0;$i<$t_opcao;$i++){
			$selected = ($opcao[$i] == $modificador)? 'selected': '';
			$c_opcao .= '<option value="'.$opcao[$i].'" '.$selected.'>'.$opcao2[$i].'</option>';
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

	$a_mod = carregar('user', $_POST[id]);

	layout_usuario();
	exit;
}


function cadastra_usuario() {

	if ($_POST[senha] != $_POST[senha2]) {
		set_status("ERRO: Senhas não conferem!");
		modifica_usuario();
		exit;
	}

	$campos = array(user_tx_nome, user_tx_login, user_tx_nivel, user_tx_status, user_tx_nascimento, user_tx_cpf, user_tx_rg, user_nb_cidade, user_tx_email, user_nb_empresa, user_tx_expiracao);
	$valores = array($_POST[nome], $_POST[login], $_POST[nivel], 'ativo', $_POST[nascimento], $_POST[cpf], $_POST[rg], $_POST[cidade], $_POST[email], $_POST[empresa], $_POST[expiracao]);

	if (!$_POST[id]) {

		$sql = query("SELECT * FROM user WHERE user_tx_login = '$_POST[login]' AND user_tx_nivel = '$_POST[nivel]'");
		if (num_linhas($sql) > 0) {
			set_status("ERRO: Login já cadastrado!");
			layout_usuario();
			exit;
		}

		if (!$_POST[senha] || !$_POST[senha2]) {
			set_status("ERRO: Preecha o campo senha e confirme-a!");
			layout_usuario();
			exit;
		}

		$campos = array_merge($campos, array(user_nb_userCadastro, user_tx_dataCadastro));
		$valores = array_merge($valores, array($_SESSION[user_nb_id], date("Y-m-d H:i:s")));

		inserir('user', $campos, $valores);
		$_POST[id] = ultimo_reg('user');
	} else {

		$campos = array_merge($campos, array(user_nb_userAtualiza, user_tx_dataAtualiza));
		$valores = array_merge($valores, array($_SESSION[user_nb_id], date("Y-m-d H:i:s")));

		atualizar('user', $campos, $valores, $_POST[id]);
	}

	if ($_POST[senha] != '' && $_POST[senha2] != '') {
		atualizar('user', array(user_tx_senha), array(md5($_POST[senha])), $_POST[id]);
	}

	index();
	exit;
}



function layout_usuario() {
	global $a_mod;
	cabecalho("Cadastro de Usuário");

	$extra = '';

	if ($_SESSION[user_tx_nivel] != 'Administrador') {
		$extra .= "readonly";
		$arrayNivel = array($_SESSION[user_tx_nivel]);
		$extraEmpresa = " AND empr_nb_id = '$_SESSION[user_nb_empresa]'";
	} else {
		$extra .= "";
		$arrayNivel = array("Administrador", "Funcionário", "Motorista");
	}

	$c[] = campo('Nome', 'nome', $a_mod[user_tx_nome], 4, '');
	$c[] = combo('Nível', 'nivel', $a_mod[user_tx_nivel], 2, $arrayNivel, $extra);
	$c[] = campo('Login', 'login', $a_mod[user_tx_login], 2);
	$c[] = campo_senha('Senha', 'senha', "", 2);
	$c[] = campo_senha('Confirmar Senha', 'senha2', "", 2);

	$c[] = campo_data('Dt. Nascimento', 'nascimento', $a_mod[user_tx_nascimento], 2);
	$c[] = campo('CPF', 'cpf', $a_mod[user_tx_cpf], 2, 'MASCARA_CPF');
	$c[] = campo('RG', 'rg', $a_mod[user_tx_rg], 2);
	$c[] = combo_net('Cidade/UF', 'cidade', $a_mod[user_nb_cidade], 3, 'cidade', '', '', 'cida_tx_uf');
	$c[] = campo('E-mail', 'email', $a_mod[user_tx_email], 3);
	$c[] = combo_bd('!Empresa', 'empresa', $a_mod[user_nb_empresa], 3, 'empresa', 'onchange="carrega_empresa(this.value)"', $extraEmpresa);
	$c[] = campo_data('Dt. Expiracao', 'expiracao', $a_mod[user_tx_expiracao], 2);

	$b[] = botao('Gravar', 'cadastra_usuario', 'id', $_POST[id]);
	$b[] = botao('Voltar', 'index');

	abre_form('Dados do Usuário');
	linha_form($c);

	if ($a_mod[user_nb_userCadastro] > 0) {
		$a_userCadastro = carregar('user', $a_mod[user_nb_userCadastro]);
		$txtCadastro = "Registro inserido por $a_userCadastro[user_tx_login] às " . data($a_mod[user_tx_dataCadastro], 1) . ".";
		$cAtualiza[] = texto("Data de Cadastro", "$txtCadastro", 5);
		if ($a_mod[user_nb_userAtualiza] > 0) {
			$a_userAtualiza = carregar('user', $a_mod[user_nb_userAtualiza]);
			$txtAtualiza = "Registro atualizado por $a_userAtualiza[user_tx_login] às " . data($a_mod[user_tx_dataAtualiza], 1) . ".";
			$cAtualiza[] = texto("Última Atualização", "$txtAtualiza", 5);
		}
		echo "<br>";
		linha_form($cAtualiza);
	}

	fecha_form($b);

	rodape();
}


function index() {
	if ($_GET[id]) {
		if ($_GET[id] != $_SESSION[user_nb_id]) {
			echo "ERRO: Usuário não autorizado!";
			exit;
		}
		$_POST[id] = $_GET[id];
		modifica_usuario();
		exit;
	}

	if ($_SESSION[user_tx_nivel] == 'Motorista') {
		$_POST[id] = $_SESSION[user_nb_id];
		modifica_usuario();
	}

	if ($_SESSION[user_nb_empresa] > 0 && $_SESSION[user_tx_nivel] != 'Administrador') {
		$extraEmpresa = " AND empr_nb_id = '$_SESSION[user_nb_empresa]'";
	}

	cabecalho("Cadastro de Usuário");

	$extra = '';

	if ($_POST[busca_codigo])
		$extra .= " AND user_nb_id = '$_POST[busca_codigo]'";
	if ($_POST[busca_nome])
		$extra .= " AND user_tx_nome LIKE '%$_POST[busca_nome]%'";
	if ($_POST[busca_login])
		$extra .= " AND user_tx_login LIKE '%$_POST[busca_login]%'";
	if ($_POST[busca_nivel])
		$extra .= " AND user_tx_nivel = '$_POST[busca_nivel]'";
	if ($_POST[busca_cpf])
		$extra .= " AND user_tx_cpf = '$_POST[busca_cpf]'";
	if ($_POST[busca_empresa])
		$extra .= " AND user_nb_empresa = '$_POST[busca_empresa]'";



	if ($_POST[busca_situacao] == '')
		$_POST[busca_situacao] = 'Ativo';
	if ($_POST[busca_situacao] && $_POST[busca_situacao] != 'Todos')
		$extra .= " AND user_tx_status = '$_POST[busca_situacao]'";

	$c[] = campo('Código', 'busca_codigo', $_POST[busca_codigo], 1);
	$c[] = campo('Nome', 'busca_nome', $_POST[busca_nome], 3);
	$c[] = campo('CPF', 'busca_cpf', $_POST[busca_cpf], 2, 'MASCARA_CPF');
	$c[] = campo('Login', 'busca_login', $_POST[busca_login], 3);
	$c[] = combo('Nível', 'busca_nivel', $_POST[busca_nivel], 2, array("", "Administrador", "Funcionário"));
	$c[] = combo('Situação', 'busca_situacao', $_POST[busca_situacao], 2, array('Todos', 'Ativo', 'Inativo'));
	$c[] = combo_bd('!Empresa', 'busca_empresa', $_POST[busca_empresa], 3, 'empresa', 'onchange="carrega_empresa(this.value)"', $extraEmpresa);

	$b[] = botao('Buscar', 'index');

	if ($_SESSION[user_tx_nivel] == 'Administrador');
	$b[] = botao('Inserir', 'layout_usuario');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM user LEFT JOIN empresa ON empresa.empr_nb_id = user.user_nb_empresa WHERE user_nb_id > 1 $extra $extraEmpresa";
	$cab = array('CÓDIGO', 'NOME', 'CPF', 'LOGIN', 'NÍVEL', 'EMPRESA', '', '');
	$val = array(
		'user_nb_id', 'user_tx_nome', 'user_tx_cpf', 'user_tx_login', 'user_tx_nivel', 'empr_tx_nome', 'icone_modificar(user_nb_id,modifica_usuario)',
		'icone_excluir(user_nb_id,exclui_usuario)'
	);



	grid($sql, $cab, $val);

	rodape();
}