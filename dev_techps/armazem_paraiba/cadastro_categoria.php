<?php
include "conecta.php";

function exclui_categoria(){

	remover('categoria',$_POST[id]);
	index();
	exit;

}
function modifica_categoria(){
	global $a_mod;

	$a_mod=carregar('categoria',$_POST[id]);

	layout_categoria();
	exit;

}

function cadastra_categoria(){
	
	$campos=array(cate_tx_nome,cate_tx_status);
	$valores=array($_POST[nome],'ativo');

	if($_POST[id]>0)
		atualizar('categoria',$campos,$valores,$_POST[id]);
	else
		inserir('categoria',$campos,$valores);

	index();
	exit;
}


function layout_categoria(){
	global $a_mod;

	cabecalho("Cadastro de Categoria");

	$c[] = campo('Nome','nome',$a_mod[cate_tx_nome],6);

	$botao[] = botao('Gravar','cadastra_categoria','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados da Categoria');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function index(){

	cabecalho("Cadastro de Categoria");

	if($_POST[busca_codigo])
		$extra .= " AND cate_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND cate_tx_nome LIKE '%$_POST[busca_nome]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],10);

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_categoria');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM categoria WHERE cate_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','','');
	$val = array('cate_nb_id','cate_tx_nome','icone_modificar(cate_nb_id,modifica_categoria)','icone_excluir(cate_nb_id,exclui_categoria)');

	grid($sql,$cab,$val);

	rodape();

}