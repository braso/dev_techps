<?php
include "conecta.php";

function exclui_servico(){

	remover('produto',$_POST[id]);
	index();
	exit;

}
function modifica_servico(){
	global $a_mod;

	$a_mod=carregar('produto',$_POST[id]);

	layout_servico();
	exit;

}

function cadastra_servico(){
	
	$campos=array(prod_tx_nome,prod_tx_preco,prod_tx_servico,prod_tx_status);
	$valores=array($_POST[nome],valor($_POST[preco]),'sim','ativo');

	if($_POST[id]>0) {
		atualizar('produto',$campos,$valores,$_POST[id]);
	} else {
		array_push($campos, prod_nb_userCadastro,prod_tx_dataCadastro);
		array_push($valores, $_SESSION[user_nb_id],date("Y-m-d H:i:s"));

		inserir('produto',$campos,$valores);
	}

	index();
	exit;
}


function layout_servico(){
	global $a_mod;

	cabecalho("Cadastro de Serviço");

	$c[] = campo('Nome','nome',$a_mod[prod_tx_nome],6);
	$c[] = campo('Valor','preco',valor($a_mod[prod_tx_preco]),2,'MASCARA_VALOR');

	$botao[] = botao('Gravar','cadastra_servico','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do Serviço');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function index(){

	cabecalho("Cadastro de Serviço");

	if($_POST[busca_codigo])
		$extra .= " AND prod_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND prod_tx_nome LIKE '%$_POST[busca_nome]%'";

	// EXIBE APENAS OS SERVIÇOS
	$extra .= " AND prod_tx_servico = 'sim'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],10);

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_servico');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM produto WHERE prod_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','VALOR','','');
	$val = array('prod_nb_id','prod_tx_nome','valor(prod_tx_preco)','icone_modificar(prod_nb_id,modifica_servico)','icone_excluir(prod_nb_id,exclui_servico)');

	grid($sql,$cab,$val);

	rodape();

}