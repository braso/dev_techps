<?php
include "../conecta.php";




function modifica_ordem(){
	global $a_mod;

	$a_mod=carregar('ordem',$_POST[id]);

	layout_ordem();
	exit;
}




function layout_ordem(){
	global $a_mod;

	cabecalho("Relatório de Vendas");

	$a_enti = carregar('entidade',$a_mod[orde_nb_entidade]);

	$c[] = texto('Nome',$a_enti[enti_tx_nome],4);
	$c[] = texto('Data',data($a_mod[orde_tx_data]),2);
	$c[] = texto('Valor Bruto',valor($a_mod[orde_tx_valorBruto],1),2);
	$c[] = texto('Desconto R$',valor($a_mod[orde_tx_descontoReais]),2);
	$c[] = texto('Valor Líquido',valor($a_mod[orde_tx_valor],1),2);

	$botao[] = botao('Voltar','index');
	
	abre_form('Dados da Venda');
	linha_form($c);
	fecha_form($botao);


	
	$sql = "SELECT * FROM orpr,produto WHERE orpr_nb_ordem='$a_mod[orde_nb_id]' AND orpr_tx_status!='inativo' AND orpr_nb_produto=prod_nb_id ";
	$cab = array('ID','PRODUTO','QTDE','UNITÁRIO','DESCONTO R$','TOTAL');
	$val = array('prod_nb_id','prod_tx_nome','orpr_tx_quantidade','valor(orpr_tx_valorUnitario,1)','valor(orpr_tx_descontoReais)','valor(orpr_tx_valor,1)');
	

	abre_form('Produtos Vendidos');
	grid($sql,$cab,$val,'','','0','desc','-1');
	fecha_form();

	rodape();
}





function index(){

	cabecalho("Relatório de Vendas");

	if($_POST[busca_codigo])
		$extra .= " AND cate_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND cate_tx_nome LIKE '%$_POST[busca_nome]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],10);

	$botao[] = botao('Buscar','index');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM ordem,entidade WHERE orde_tx_status!='inativo' AND orde_tx_situacao='finalizado' AND orde_nb_entidade=enti_nb_id $extra";
	$cab = array('CÓDIGO','NOME','DATA','VALOR BRUTO','DESCONTO R$','VALOR LÍQUIDO','');
	$val = array('orde_nb_id','enti_tx_nome','data(orde_tx_data)','valor(orde_tx_valorBruto)','valor(orde_tx_descontoReais)','valor(orde_tx_valor)','icone_modificar(orde_nb_id,modifica_ordem)');

	grid($sql,$cab,$val,'','','0','desc','50');

	rodape();
}