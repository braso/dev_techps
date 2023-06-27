<?php
include "conecta.php";


function exclui_usuario(){
	remover('user',$_POST[id]);

	index();
	exit;
}

function modifica_usuario(){
	global $a_mod;

	$a_mod = carregar('user',$_POST[id]);

	layout_usuario();
	exit;
}


function cadastra_usuario(){
	
	if($_POST[senha] != $_POST[senha2]){
		set_status("ERRO: Senhas não conferem!");
		modifica_usuario();
		exit;
	}



	$campos = array(user_tx_nome,user_tx_login,user_tx_nivel,user_tx_status);
	$valores = array($_POST[nome],$_POST[login],$_POST[nivel],'ativo');

	if(!$_POST[id]){
		
		$sql = query("SELECT * FROM user WHERE user_tx_login = '$_POST[login]' AND user_tx_nivel = '$_POST[nivel]'");
		if(num_linhas($sql)>0){
			set_status("ERRO: Login já cadastrado!");
			layout_usuario();
			exit;
		}		

		if(!$_POST[senha] || !$_POST[senha2]){
			set_status("ERRO: Preecha o campo senha e confirme-a!");
			layout_usuario();
			exit;
		}


		inserir('user',$campos,$valores);
		$_POST[id]=ultimo_reg('user');
	}else{
		atualizar('user',$campos,$valores,$_POST[id]);
	}

	if($_POST[senha]!='' && $_POST[senha2]!=''){
		atualizar('user',array(user_tx_senha),array(md5($_POST[senha])),$_POST[id]);
	}

	index();
	exit;

}



function layout_usuario(){
	global $a_mod;
	cabecalho("Cadastro de Usuário");

	if($_SESSION[user_tx_nivel] != 'Administrador')
		$extra .= "disabled='disabled'";
	else
		$extra .= "";

	$c[]=campo('Nome','nome',$a_mod[user_tx_nome],4,'',$extra);
	$c[]=combo('Nível','nivel',$a_mod[user_tx_nivel],4,array("Administrador","Motorista"),$extra);
	$c[]=campo('Login','login',$a_mod[user_tx_login],4);
	$c[]=campo_senha('Senha','senha',"",2);
	$c[]=campo_senha('Confirmar Senha','senha2',"",2);
	
	$b[]=botao('Gravar','cadastra_usuario','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Usuário');	
	linha_form($c);
	fecha_form($b);

	rodape();

}


function index(){
	if($_GET[id]){
		if($_GET[id] != $_SESSION[user_nb_id]){
			echo"ERRO: Usuário não autorizado!";
			exit;
		}
		$_POST[id]=$_GET[id];
		modifica_usuario();
		exit;		
	}

	cabecalho("Cadastro de Usuário");

	if($_POST[busca_codigo])
		$extra .=" AND user_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND user_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_login])
		$extra .=" AND user_tx_login LIKE '%$_POST[busca_login]%'";
	if($_POST[busca_nivel])
		$extra .=" AND user_tx_nivel = '$_POST[busca_nivel]'";
	
	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],6);
	$c[]=campo('Login','busca_login',$_POST[busca_login],3);
	$c[]=combo('Nível','busca_nivel',$_POST[busca_nivel],2,array("","Administrador","Funcionário"));

	$b[]=botao('Buscar','index');
	
	if($_SESSION[user_tx_nivel] == 'Administrador');
		$b[]=botao('Inserir','layout_usuario');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM user WHERE user_tx_status != 'inativo' AND user_nb_id > 1 $extra";
	$cab = array('CÓDIGO','NOME','LOGIN','NÍVEL','','');
	$val = array('user_nb_id','user_tx_nome','user_tx_login','user_tx_nivel','icone_modificar(user_nb_id,modifica_usuario)',
			'icone_excluir(user_nb_id,exclui_usuario)');

	

	grid($sql,$cab,$val);

	rodape();

}


?>