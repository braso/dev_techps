<?php
include "../conecta.php";


function exclui_forma(){
	remover('forma',$_POST[id]);

	index();
	exit;
}

function modifica_forma(){
	global $a_mod;

	$a_mod = carregar('forma',$_POST[id]);

	layout_forma();
	exit;
}


function cadastra_forma(){

	$campos = array(form_tx_nome,form_tx_identificacao,form_tx_cnpj,form_tx_conta,form_tx_contadv,form_tx_agencia,
		form_tx_agenciadv,form_tx_carteira,form_tx_juros,form_tx_multa,form_tx_status,form_tx_cedente,form_tx_codCliente,form_tx_endereco);
	$valores = array($_POST[nome],$_POST[identificacao],$_POST[cnpj],$_POST[conta],$_POST[contadv],$_POST[agencia],
		$_POST[agenciadv],$_POST[carteira],valor($_POST[juros]),valor($_POST[multa]),'ativo',$_POST[cedente],$_POST[codCliente],$_POST[endereco]);

	if(!$_POST[id]){
		inserir('forma',$campos,$valores);
	}else{
		atualizar('forma',$campos,$valores,$_POST[id]);
	}
	
	index();
	exit;

}



function layout_forma(){
	global $a_mod;
	cabecalho("Cadastro de Forma de Pagamento");

	$c[]=campo('Nome','nome',$a_mod[form_tx_nome],4);
	
	$b[]=botao('Gravar','cadastra_forma','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados da Forma de Pagamento');
	linha_form($c);	
	fecha_form($b);

	rodape();

}


function index(){
	cabecalho("Cadastro de Forma de Pagamento");

	if($_POST[busca_codigo])
		$extra .=" AND form_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND form_tx_nome LIKE '%$_POST[busca_nome]%'";
	

	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],4);

	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_forma');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM forma WHERE form_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','','');
	$val = array('form_nb_id','form_tx_nome','icone_modificar(form_nb_id,modifica_forma)',
		'icone_excluir(form_nb_id,exclui_forma)');

	grid($sql,$cab,$val);

	rodape();

}


?>

