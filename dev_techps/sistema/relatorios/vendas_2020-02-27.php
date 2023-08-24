<?php
include "../conecta.php";





function icone_imprimir_venda($id){
	$icone = 'glyphicon glyphicon-print';
	
	$icone='class="'.$icone.'"';
	
	return "<center><a title=\"$title\" style='color:gray' onclick='javascript:imprimir_venda(\"$id\");' ><spam $icone></spam></a></center>";
	
}



function index(){

	cabecalho("Relatório de Vendas");

	if($_POST[busca_codigo])
		$extra .= " AND orde_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND enti_tx_nome LIKE '%$_POST[busca_nome]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],10);

	$botao[] = botao('Buscar','index');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM ordem,entidade WHERE orde_tx_status!='inativo' AND orde_tx_situacao='finalizado' AND orde_nb_entidade=enti_nb_id $extra";
	$cab = array('CÓDIGO','NOME','DATA','VALOR BRUTO','DESCONTO R$','VALOR LÍQUIDO','');
	$val = array('orde_nb_id','enti_tx_nome','data(orde_tx_data)','valor(orde_tx_valorBruto)','valor(orde_tx_descontoReais)','valor(orde_tx_valor)','icone_imprimir_venda(orde_nb_id)');

	grid($sql,$cab,$val,'','','0','desc','25');


	?><form action="../pdv/impressao.php" id="form_imprime_venda" name="form_imprime_venda" method="post" target="_blank">
		<input type="hidden" name="acao" value="imprimir_venda">
		<input type="hidden" name="id" value="">
	</form>
	<script type="text/javascript">
		function imprimir_venda(id_venda){
			document.form_imprime_venda.id.value = id_venda;
			document.getElementById("form_imprime_venda").submit();
		}
	</script><?php

	rodape();
}