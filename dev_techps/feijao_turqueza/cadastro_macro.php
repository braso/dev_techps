<?php
include "conecta.php";

function exclui_macro(){

	remover('macroponto',$_POST[id]);
	index();
	exit;

}
function modifica_macro(){
	global $a_mod;

	$a_mod=carregar('macroponto',$_POST[id]);

	layout_macro();
	exit;

}

function cadastra_macro(){
	
	// $campos=array(macr_tx_nome,macr_tx_codigoInterno,macr_tx_codigoExterno,macr_nb_user,macr_tx_data,macr_tx_status);
	// $valores=array($_POST[nome],$_POST[codigoInterno],$_POST[codigoExterno],$_SESSION[user_nb_id],date("Y-m-d"),'ativo');
	$campos=array(macr_tx_codigoExterno,macr_nb_user,macr_tx_data,macr_tx_status);
	$valores=array($_POST[codigoExterno],$_SESSION[user_nb_id],date("Y-m-d"),'ativo');

	if($_POST[id]>0){
		atualizar('macroponto',$campos,$valores,$_POST[id]);
	}
	// else
	// 	inserir('macroponto',$campos,$valores);

	index();
	exit;
}


function layout_macro(){
	global $a_mod;

	cabecalho("Cadastro Macro");

	$c[] = campo('Nome','nome',$a_mod[macr_tx_nome],6,'','readonly=readonly');
	$c[] = campo('Código Interno','codigoInterno',$a_mod[macr_tx_codigoInterno],3,'','readonly=readonly');
	$c[] = campo('Código Externo','codigoExterno',$a_mod[macr_tx_codigoExterno],3);

	$botao[] = botao('Gravar','cadastra_macro','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do Macro');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function index(){

	cabecalho("Cadastro Macro");

	if($_POST[busca_codigo])
		$extra .= " AND macr_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND macr_tx_nome LIKE '%$_POST[busca_nome]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],10);

	$botao[] = botao('Buscar','index');
	// $botao[] = botao('Inserir','layout_macro');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM macroponto WHERE macr_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','CÓD. INTERNO','CÓD. EXTERNO','');
	$val = array('macr_nb_id','macr_tx_nome','macr_tx_codigoInterno','macr_tx_codigoExterno','icone_modificar(macr_nb_id,modifica_macro)');

	grid($sql,$cab,$val,'','',0,'asc',-1);

	rodape();

}