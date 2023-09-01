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


function modifica_usuario(){
	global $a_mod;
	$a_mod = carregar('user',$_POST['id']);
	layout_usuario();
	exit;
}


function cadastra_usuario(){
	if($_POST['senha'] != $_POST['senha2']){
		set_status("ERRO: Senhas não conferem.");
		modifica_usuario();
		exit;
	}

	$campos = ['user_tx_nome','user_tx_login','user_tx_nivel','user_tx_status','user_tx_emprCnpj'];
	if($_SESSION['user_tx_nivel'] === 'Super Administrador'){
		if($_POST['empresa']){
			$empresa = query('SELECT empr_tx_cnpj FROM empresa where empr_tx_cnpj ='. $_POST['empresa']);
			if(empty($empresa)){
				set_status("ERRO: CNPJ não cadastrado.");
				exit;
			}
		}else{
			set_status("ERRO: CNPJ não informado.");
			exit;
		}
	    $valores = [$_POST['nome'],$_POST['login'],$_POST['nivel'],'ativo', 	$_POST['empresa']];
	}else{
	    $valores = [$_POST['nome'],$_POST['login'],$_POST['nivel'],'ativo', 	$_SESSION['user_tx_emprCnpj']];
	}

	if(!$_POST['id']){
		$sql = query("SELECT * FROM user WHERE user_tx_login = '".$_POST['login']."' AND user_tx_nivel = '".$_POST['nivel']."'");
		if(num_linhas($sql)>0){
			set_status("ERRO: Login já cadastrado.");
			layout_usuario();
			exit;
		}
		if(!($_POST['senha'] && $_POST['senha2'])){
			set_status("ERRO: Preecha o campo senha e confirme-a.");
			layout_usuario();
			exit;
		}

		inserir('user',$campos,$valores);
		$_POST['id']=ultimo_reg('user');
	}else{
		atualizar('user',$campos,$valores,$_POST['id']);
	}
	if($_POST['senha']!='' && $_POST['senha2']!=''){
		atualizar('user',['user_tx_senha'],[md5($_POST['senha'])],$_POST['id']);
	}
	index();
	exit;
}


function layout_usuario(){
	global $a_mod;
	cabecalho("Cadastro de Usuário");

	$extra .= ($_SESSION['user_tx_nivel'] != 'Administrador' && $_SESSION['user_tx_nivel'] != 'Super Administrador') ? 'disabled' : '';
	
	if ($_SESSION['user_tx_nivel'] === 'Super Administrador') {
		$combo = ['Administrador','Motorista', 'Funcionário','Super Administrador'];
		$sql = query("SELECT empr_tx_nome, empr_tx_cnpj  FROM empresa");
		$result = mysqli_fetch_all($sql, MYSQLI_ASSOC);
		foreach ($result as $value){
		    $empresas_name[] = $value['empr_tx_nome'];
		    $empresas_cnpj[] = $value['empr_tx_cnpj'];
		}
	}else{
		$combo = ['Administrador','Motorista', 'Funcionário'];
	}
	$c[]=campo('Nome','nome',$a_mod['user_tx_nome'],4,'',$extra);
	$c[]=combo('Nível','nivel',$a_mod['user_tx_nivel'],4,$combo,$extra);
	$c[]=campo('Login','login',$a_mod['user_tx_login'],4);
	$c[]=campo_senha('Senha','senha',"",2);
	$c[]=campo_senha('Confirmar Senha','senha2',"",2);
	if ($_SESSION['user_tx_nivel'] === 'Super Administrador'){
	    $c[]=combo_empresa('Empresa','empresa',$a_mod['user_tx_emprCnpj'],4,$empresas_cnpj,$empresas_name,$extra);
	}
	$b[]=botao('Gravar','cadastra_usuario','id',$_POST['id']);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Usuário');	
	linha_form($c);
	fecha_form($b);
	rodape();
}


function index(){
	if($_GET['id']){
		if($_GET['id'] != $_SESSION['user_nb_id']){
			echo"ERRO: Usuário não autorizado.";
			exit;
		}
		$_POST['id']=$_GET['id'];
		modifica_usuario();
		exit;		
	}

	cabecalho("Cadastro de Usuário");

	$extra .= ($_POST['busca_codigo'])? " AND user_nb_id = '$_POST[busca_codigo]'"		 : '';
	$extra .= ($_POST['busca_nome'])  ? " AND user_tx_nome LIKE '%$_POST[busca_nome]%'"	 : '';
	$extra .= ($_POST['busca_login']) ? " AND user_tx_login LIKE '%$_POST[busca_login]%'": '';
	$extra .= ($_POST['busca_nivel']) ? " AND user_tx_nivel = '$_POST[busca_nivel]'"	 : '';
	

	$c[]=campo('Código','busca_codigo',$_POST['busca_codigo'],1);
	$c[]=campo('Nome','busca_nome',$_POST['busca_nome'],6);
	$c[]=campo('Login','busca_login',$_POST['busca_login'],3);
	$c[]=combo('Nível','busca_nivel',$_POST['busca_nivel'],2,["","Administrador","Funcionário", "Motorista"]);

	$b[]=botao('Buscar','index');
	
	if($_SESSION['user_tx_nivel'] == 'Administrador' || $_SESSION['user_tx_nivel'] == 'Super Administrador'){
		$b[]=botao('Inserir','layout_usuario');
	}

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM user WHERE user_tx_status != 'inativo' AND user_nb_id > 1 $extra";
	$cab = ['CÓDIGO','NOME','LOGIN','NÍVEL','',''];
	$val = ['user_nb_id','user_tx_nome','user_tx_login','user_tx_nivel','icone_modificar(user_nb_id,modifica_usuario)', 'icone_excluir(user_nb_id,exclui_usuario)'];
	
	grid($sql,$cab,$val);
	rodape();
}
?>