<?php
include "conecta.php";

function exclui_motivo(){

	remover('motivo',$_POST[id]);
	index();
	exit;

}
function modifica_motivo(){
	global $a_mod;

	$a_mod=carregar('motivo',$_POST[id]);

	layout_motivo();
	exit;

}

function cadastra_motivo(){
	
	$campos=array(moti_tx_nome, moti_tx_tipo, moti_tx_status);
	$valores=array($_POST[nome], $_POST[tipo], 'ativo');

	if($_POST[id]>0) {
		atualizar('motivo',$campos,$valores,$_POST[id]);
	} else {
		array_push($campos, moti_nb_userCadastro,moti_tx_dataCadastro);
		array_push($valores, $_SESSION[user_nb_id],date("Y-m-d H:i:s"));

		inserir('motivo',$campos,$valores);
	}

	index();
	exit;
}


function layout_motivo(){
	global $a_mod;

	cabecalho("Cadastro de Motivo");

	$c[] = campo('Nome','nome',$a_mod[moti_tx_nome],6);
	$c[] = combo('Tipo','tipo',$a_mod[moti_tx_tipo],4,array('Ajuste','Abono'));
	
	$botao[] = botao('Gravar','cadastra_motivo','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do Motivo');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function index(){

	cabecalho("Cadastro de Motivo");

	if($_POST[busca_codigo])
		$extra .= " AND moti_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND moti_tx_nome LIKE '%$_POST[busca_nome]%'";
	
	if($_POST[busca_tipo])
		$extra .= " AND moti_tx_tipo LIKE '%$_POST[busca_tipo]%'";

	
	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],6);
	$c[] = combo('Tipo','busca_tipo',$_POST[busca_tipo],4,array('','Ajuste','Abono'));

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_motivo');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM motivo WHERE moti_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','TIPO','','');
	$val = array('moti_nb_id','moti_tx_nome','moti_tx_tipo','icone_modificar(moti_nb_id,modifica_motivo)','icone_excluir(moti_nb_id,exclui_motivo)');

	grid($sql,$cab,$val);

	rodape();

}