<?php
include "conecta.php";

function exclui_tipo(){

	remover('tipoimovel',$_POST[id]);
	index();
	exit;

}
function modifica_tipo(){
	global $a_mod;

	$a_mod=carregar('tipoimovel',$_POST[id]);

	layout_tipo();
	exit;

}

function cadastra_tipo(){
	
	$campos=array(tipo_tx_nome,tipo_tx_status);
	$valores=array($_POST[nome],'ativo');

	if($_POST[id]>0)
		atualizar('tipoimovel',$campos,$valores,$_POST[id]);
	else
		inserir('tipoimovel',$campos,$valores);

	index();
	exit;
}


function layout_tipo(){
	global $a_mod;

	cabecalho("Cadastro Tipo de Imóvel");

	$c[] = campo('Nome','nome',$a_mod[tipo_tx_nome],6);

	$botao[] = botao('Gravar','cadastra_tipo','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do Tipo');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function index(){

	cabecalho("Cadastro Tipo de Imóvel");

	if($_POST[busca_codigo])
		$extra .= " AND tipo_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND tipo_tx_nome LIKE '%$_POST[busca_nome]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],10);

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_tipo');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM tipoimovel WHERE tipo_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','','');
	$val = array('tipo_nb_id','tipo_tx_nome','icone_modificar(tipo_nb_id,modifica_tipo)','icone_excluir(tipo_nb_id,exclui_tipo)');

	grid($sql,$cab,$val);

	rodape();

}