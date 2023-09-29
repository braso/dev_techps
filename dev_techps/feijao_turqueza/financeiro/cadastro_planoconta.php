<?php
include "../conecta.php";


function exclui_planoconta(){
	remover('planoconta',$_POST[id]);

	index();
	exit;
}

function modifica_planoconta(){
	global $a_mod;

	$a_mod = carregar('planoconta',$_POST[id]);

	layout_planoconta();
	exit;
}


function cadastra_planoconta(){

	$campos = array(plan_tx_nome,plan_tx_tipo,plan_tx_status);
	$valores = array($_POST[nome],$_POST[tipo],'ativo');

	if(!$_POST[id]){
		inserir('planoconta',$campos,$valores);
	}else{
		atualizar('planoconta',$campos,$valores,$_POST[id]);
	}
	index();
	exit;

}



function layout_planoconta(){
	global $a_mod;
	cabecalho("Cadastro de Plano de Conta");

	$c[]=campo('Nome','nome',$a_mod[plan_tx_nome],4);
	$c[]=combo('Tipo','tipo',$a_mod[plan_tx_tipo],3,array("Receita","Despesa"));
	
	
	$b[]=botao('Gravar','cadastra_planoconta','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Plano de Conta');
	linha_form($c);
	fecha_form($b);

	rodape();

}


function index(){
	cabecalho("Cadastro de Plano de Conta");

	if($_POST[busca_codigo])
		$extra .=" AND plan_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND plan_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_tipo])
		$extra .=" AND plan_tx_tipo = '$_POST[busca_tipo]'";

	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],4);
	$c[]=combo('Tipo','busca_tipo',$_POST[busca_tipo],3,array("","Receita","Despesa"));

	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_planoconta');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM planoconta WHERE plan_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','TIPO','','');
	$val = array('plan_nb_id','plan_tx_nome','plan_tx_tipo','icone_modificar(plan_nb_id,modifica_planoconta)',
		'icone_excluir(plan_nb_id,exclui_planoconta)');

	grid($sql,$cab,$val);

	rodape();

}


?>

