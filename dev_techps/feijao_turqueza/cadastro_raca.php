<?php
include "conecta.php";

function exclui_raca(){

	remover('raca',$_POST[id]);
	index();
	exit;

}
function modifica_raca(){
	global $a_mod;

	$a_mod=carregar('raca',$_POST[id]);

	layout_raca();
	exit;

}

function cadastra_raca(){
	
	$campos=array(raca_tx_nome,raca_tx_status);
	$valores=array($_POST[nome],'ativo');

	if($_POST[id]>0)
		atualizar('raca',$campos,$valores,$_POST[id]);
	else
		inserir('raca',$campos,$valores);

	index();
	exit;
}


function layout_raca(){
	global $a_mod;

	cabecalho("Cadastro de Raça");

	$c[] = campo('Nome','nome',$a_mod[raca_tx_nome],6);

	$botao[] = botao('Gravar','cadastra_raca','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados da Raça');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function index(){

	cabecalho("Cadastro de Raça");

	if($_POST[busca_codigo])
		$extra .= " AND raca_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND raca_tx_nome LIKE '%$_POST[busca_nome]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],10);

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_raca');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM raca WHERE raca_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','','');
	$val = array('raca_nb_id','raca_tx_nome','icone_modificar(raca_nb_id,modifica_raca)','icone_excluir(raca_nb_id,exclui_raca)');

	grid($sql,$cab,$val);

	rodape();

}