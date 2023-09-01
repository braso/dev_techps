<?php
include "conecta.php";


function exclui_finalidade(){
	remover('finalidade',$_POST[id]);

	index();
	exit;
}

function modifica_finalidade(){
	global $a_mod;

	$a_mod = carregar('finalidade',$_POST[id]);

	layout_finalidade();
	exit;
}


function cadastra_finalidade(){
	global $a_mod;
	
	$campos = array("fina_tx_nome",fina_tx_status);
	$valores = array($_POST["nome"],'ativo');

	if(!$_POST["id"]){
		array_push($campos, 'fina_nb_userCadastro','fina_tx_dataCadastro');
		array_push($valores, $_SESSION["user_nb_id"],date("Y-m-d"));

		$id=inserir('finalidade',$campos,$valores);
	}else{
		atualizar('finalidade',$campos,$valores,$_POST['id']);
		$id=$_POST['id'];
	}

	$_POST['id']=$id;
	index();
	exit;

}

function layout_finalidade(){
	global $a_mod;
	cabecalho("Cadastro de Finalidade");

	$c[]=campo('Descrição','nome',$a_mod['fina_tx_nome'],8);
	
	$b[]=botao('Gravar','cadastra_finalidade','id',$_POST['id']);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Finalidade');
	linha_form($c);
		
	fecha_form($b);

	rodape();

}

function index(){
	cabecalho("Cadastro de Finalidade");

	$extra = '';
	if($_POST["busca_codigo"])
		$extra .=" AND fina_nb_id = '$_POST[busca_codigo]'";
	if($_POST["busca_nome"])
		$extra .=" AND fina_tx_nome LIKE '%$_POST[busca_nome]%'";
	
	$c[]=campo('Código','busca_codigo',$_POST["busca_codigo"],1);
	$c[]=campo('Descrição','busca_nome',$_POST["busca_nome"],9);
	
	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_finalidade');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM finalidade WHERE fina_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','','');
	$val = array('fina_nb_id','fina_tx_nome','icone_modificar(fina_nb_id,modifica_finalidade)',
		'icone_excluir(fina_nb_id,exclui_finalidade)');

	grid($sql,$cab,$val);

	rodape();

}


?>