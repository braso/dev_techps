<?php
include "conecta.php";


function exclui_conjunto(){
	remover('conjunto',$_POST[id]);

	index();
	exit;
}

function modifica_conjunto(){
	global $a_mod;

	$a_mod = carregar('conjunto',$_POST[id]);

	layout_conjunto();
	exit;
}


function cadastra_conjunto(){
	global $a_mod;
	
	$campos = array("conj_tx_nome",conj_tx_status);
	$valores = array($_POST["nome"],'ativo');

	if(!$_POST["id"]){
		array_push($campos, 'conj_nb_userCadastro','conj_tx_dataCadastro');
		array_push($valores, $_SESSION["user_nb_id"],date("Y-m-d"));

		$id=inserir('conjunto',$campos,$valores);
	}else{
		atualizar('conjunto',$campos,$valores,$_POST[id]);
		$id=$_POST[id];
	}

	$_POST[id]=$id;
	index();
	exit;

}

function layout_conjunto(){
	global $a_mod;
	cabecalho("Cadastro de Conjunto");

	$c[]=campo('Nome','nome',$a_mod[conj_tx_nome],8);
	
	$b[]=botao('Gravar','cadastra_conjunto','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Conjunto');
	linha_form($c);
		
	fecha_form($b);

	rodape();

}

function index(){
	cabecalho("Cadastro de Conjunto");

	$extra = '';
	if($_POST["busca_codigo"])
		$extra .=" AND conj_nb_id = '$_POST[busca_codigo]'";
	if($_POST["busca_nome"])
		$extra .=" AND conj_tx_nome LIKE '%$_POST[busca_nome]%'";
	
	$c[]=campo('Código','busca_codigo',$_POST["busca_codigo"],1);
	$c[]=campo('Nome','busca_nome',$_POST["busca_nome"],9);
	
	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_conjunto');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM conjunto WHERE conj_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','','');
	$val = array('conj_nb_id','conj_tx_nome','icone_modificar(conj_nb_id,modifica_conjunto)',
		'icone_excluir(conj_nb_id,exclui_conjunto)');

	grid($sql,$cab,$val);

	rodape();

}


?>