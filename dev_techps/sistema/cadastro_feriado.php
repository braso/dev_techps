<?php
include "conecta.php";

function exclui_feriado(){

	remover('feriado',$_POST[id]);
	index();
	exit;

}
function modifica_feriado(){
	global $a_mod;

	$a_mod=carregar('feriado',$_POST[id]);

	layout_feriado();
	exit;

}

function cadastra_feriado(){
	
	$campos=array(feri_tx_nome,feri_tx_data,feri_tx_status);
	$valores=array($_POST[nome],$_POST[data],'ativo');

	if($_POST[id]>0) {
		atualizar('feriado',$campos,$valores,$_POST[id]);
	} else {
		array_push($campos, feri_nb_userCadastro,feri_tx_dataCadastro);
		array_push($valores, $_SESSION[user_nb_id],date("Y-m-d H:i:s"));

		inserir('feriado',$campos,$valores);
	}

	index();
	exit;
}


function layout_feriado(){
	global $a_mod;

	cabecalho("Cadastro de Feriado");

	$c[] = campo('Nome','nome',$a_mod[feri_tx_nome],6);
	$c[] = campo_data('Data','data',$a_mod[feri_tx_data],2);

	$botao[] = botao('Gravar','cadastra_feriado','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do Feriado');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function index(){

	cabecalho("Cadastro de Feriado");

	if($_POST[busca_codigo])
		$extra .= " AND feri_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND feri_tx_nome LIKE '%$_POST[busca_nome]%'";

	// EXIBE APENAS OS FeriadoS
	// $extra .= " AND feri_tx_feriado = 'sim'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],10);

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_feriado');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM feriado WHERE feri_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','DATA','','');
	$val = array('feri_nb_id','feri_tx_nome','data(feri_tx_data)','icone_modificar(feri_nb_id,modifica_feriado)','icone_excluir(feri_nb_id,exclui_feriado)');

	grid($sql,$cab,$val);

	rodape();

}