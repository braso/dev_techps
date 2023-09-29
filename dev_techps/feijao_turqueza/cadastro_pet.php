<?php
include "conecta.php";

function exclui_pet(){

	remover('pet',$_POST[id]);
	index();
	exit;

}
function modifica_pet(){
	global $a_mod;

	$a_mod=carregar('pet',$_POST[id]);

	layout_pet();
	exit;

}

function cadastra_pet(){
	global $a_mod;
	
	$campos=array(pet_tx_nome,pet_nb_entidade,pet_tx_tipo,pet_nb_raca,pet_tx_status);
	$valores=array($_POST[nome],$_POST[entidade],$_POST[tipo],$_POST[raca],'ativo');

	$a_mod = array_combine($campos,$valores);

	if (trim($_POST[nome])=='') {
		set_status('ATENÇÃO: Informe o nome do PET!');
		layout_pet();
		exit;
	}
	if (trim($_POST[tipo])=='') {
		set_status('ATENÇÃO: Selecione o tipo do PET!');
		layout_pet();
		exit;
	}
	if (intval($_POST[entidade])==0) {
		set_status('ATENÇÃO: Selecione um cliente!');
		layout_pet();
		exit;
	}


	if($_POST[id]>0)
		atualizar('pet',$campos,$valores,$_POST[id]);
	else
		inserir('pet',$campos,$valores);

	index();
	exit;
}


function layout_pet(){
	global $a_mod;

	cabecalho("Cadastro de PET");

	$c[] = campo('* Nome','nome',$a_mod[pet_tx_nome],8);
	$c[] = combo_net('* Cliente','entidade',$a_mod[pet_nb_entidade],4,'entidade');

	$c[] = combo('* Tipo','tipo',$a_mod[pet_tx_tipo],2,array('','Cão','Gato'));
	$c[] = combo_bd('!Raça','raca',$a_mod[pet_tx_raca],2,'raca');

	$botao[] = botao('Gravar','cadastra_pet','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do PET');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function index(){

	cabecalho("Cadastro de PET");

	if($_POST[busca_codigo])
		$extra .= " AND pet_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .= " AND pet_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_cliente])
		$extra .= " AND pet_nb_entidade='$_POST[busca_cliente]' ";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],6);
	$c[] = combo_net('Cliente','busca_cliente',$_POST[busca_cliente],4,'entidade');

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_pet');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM pet,entidade WHERE pet_tx_status != 'inativo' AND pet_nb_entidade=enti_nb_id $extra";
	$cab = array('CÓDIGO','NOME','CLIENTE','TIPO','','');
	$val = array('pet_nb_id','pet_tx_nome','enti_tx_nome','pet_tx_tipo','icone_modificar(pet_nb_id,modifica_pet)','icone_excluir(pet_nb_id,exclui_pet)');

	grid($sql,$cab,$val);

	rodape();

}