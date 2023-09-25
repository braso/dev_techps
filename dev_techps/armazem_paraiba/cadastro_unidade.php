<?php
include "conecta.php";

function exclui_unidade(){

	remover('unidade',$_POST[id]);
	index();
	exit;

}
function modifica_unidade(){
	global $a_mod;

	$a_mod=carregar('unidade',$_POST[id]);

	layout_unidade();
	exit;

}

function cadastra_unidade(){
	
	$campos=array(unid_tx_nome,unid_tx_descricao,unid_tx_status);
	$valores=array($_POST[nome],$_POST[descricao],'ativo');

	if($_POST[id]>0)
		atualizar('unidade',$campos,$valores,$_POST[id]);
	else
		inserir('unidade',$campos,$valores);

	index();
	exit;
}


function layout_unidade(){
	global $a_mod;

	cabecalho("Cadastro de Unidade");

	$c[] = campo('Nome','nome',$a_mod[unid_tx_nome],2);
	$c[] = campo('Descrição','descricao',$a_mod[unid_tx_descricao],6);

	$botao[] = botao('Gravar','cadastra_unidade','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados da Unidade');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function index(){

	cabecalho("Cadastro de Unidade");

	$extra = '';

	if($_POST[busca_codigo])
		$extra .= " AND unid_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND unid_tx_nome LIKE '%$_POST[busca_nome]%'";

	if($_POST[busca_descricao])		
		$extra .= " AND unid_tx_descricao LIKE '%$_POST[busca_descricao]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],3);
	$c[] = campo('Descrição','busca_descricao',$_POST[busca_descricao],7);

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_unidade');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM unidade WHERE unid_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','DESCRIÇÃO','','');
	$val = array('unid_nb_id','unid_tx_nome','unid_tx_descricao','icone_modificar(unid_nb_id,modifica_unidade)','icone_excluir(unid_nb_id,exclui_unidade)');

	grid($sql,$cab,$val);

	rodape();

}