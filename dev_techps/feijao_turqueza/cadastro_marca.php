<?php
include "conecta.php";

function exclui_marca(){

	remover('marca',$_POST[id]);
	index();
	exit;

}
function modifica_marca(){
	global $a_mod;

	$a_mod=carregar('marca',$_POST[id]);

	layout_marca();
	exit;

}

function cadastra_marca(){
	
	$campos=array(marc_tx_nome,marc_tx_status);
	$valores=array($_POST[nome],'ativo');

	if($_POST[id]>0)
		atualizar('marca',$campos,$valores,$_POST[id]);
	else
		inserir('marca',$campos,$valores);

	index();
	exit;
}


function layout_marca(){
	global $a_mod;

	cabecalho("Cadastro de Marca");

	$c[] = campo('Nome','nome',$a_mod[marc_tx_nome],6);

	$botao[] = botao('Gravar','cadastra_marca','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados da Marca');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function index(){

	cabecalho("Cadastro de Marca");

	if($_POST[busca_codigo])
		$extra .= " AND marc_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND marc_tx_nome LIKE '%$_POST[busca_nome]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],10);

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_marca');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM marca WHERE marc_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','','');
	$val = array('marc_nb_id','marc_tx_nome','icone_modificar(marc_nb_id,modifica_marca)','icone_excluir(marc_nb_id,exclui_marca)');

	grid($sql,$cab,$val);

	rodape();

}