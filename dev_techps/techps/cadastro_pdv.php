<?php
include "conecta.php";

function exclui_pdv(){

	remover('pdv',$_POST[id]);
	index();
	exit;

}
function modifica_pdv(){
	global $a_mod;

	$a_mod=carregar('pdv',$_POST[id]);

	layout_pdv();
	exit;

}

function cadastra_pdv(){
	
	$campos  = array(pdv_tx_nome,pdv_tx_status);
	$valores = array($_POST[nome],'ativo');

	if($_POST[id]>0)
		atualizar('pdv',$campos,$valores,$_POST[id]);
	else
		inserir('pdv',$campos,$valores);

	index();
	exit;
}


function layout_pdv(){
	global $a_mod;

	cabecalho("Cadastro de PDV");

	$c[] = campo('Nome','nome',$a_mod[pdv_tx_nome],6);

	$botao[] = botao('Gravar','cadastra_pdv','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do PDV');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function index(){

	cabecalho("Cadastro de PDV");

	if($_POST[busca_codigo])
		$extra .= " AND pdv_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND pdv_tx_nome LIKE '%$_POST[busca_nome]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],10);

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_pdv');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM pdv WHERE pdv_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','','');
	$val = array('pdv_nb_id','pdv_tx_nome','icone_modificar(pdv_nb_id,modifica_pdv)','icone_excluir(pdv_nb_id,exclui_pdv)');

	grid($sql,$cab,$val);

	rodape();

}