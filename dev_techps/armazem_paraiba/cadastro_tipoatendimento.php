<?php
include "conecta.php";

function exclui_tipoatendimento(){

	remover('tipoatendimento',$_POST[id]);
	index();
	exit;

}
function modifica_tipoatendimento(){
	global $a_mod;

	$a_mod=carregar('tipoatendimento',$_POST[id]);

	layout_tipoatendimento();
	exit;

}

function cadastra_tipoatendimento(){
	
	$campos=array(tipo_tx_nome,tipo_tx_status);
	$valores=array($_POST[nome],'ativo');

	if($_POST[id]>0)
		atualizar('tipoatendimento',$campos,$valores,$_POST[id]);
	else
		inserir('tipoatendimento',$campos,$valores);

	index();
	exit;
}


function layout_tipoatendimento(){
	global $a_mod;

	cabecalho("Cadastro de Tipo de Atendimento");

	$c[] = campo('Nome','nome',$a_mod[tipo_tx_nome],6);

	$botao[] = botao('Gravar','cadastra_tipoatendimento','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do Tipo de Atendimento');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function index(){

	cabecalho("Cadastro de Tipo de Atendimento");

	if($_POST[busca_codigo])
		$extra .= " AND cate_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND tipo_tx_nome LIKE '%$_POST[busca_nome]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],10);

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_tipoatendimento');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM tipoatendimento WHERE tipo_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','','');
	$val = array('cate_nb_id','tipo_tx_nome','icone_modificar(cate_nb_id,modifica_tipoatendimento)','icone_excluir(cate_nb_id,exclui_tipoatendimento)');

	grid($sql,$cab,$val);

	rodape();

}