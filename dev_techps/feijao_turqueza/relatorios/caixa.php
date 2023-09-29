<?php
include "../conecta.php";






function icone_imprimir_caixa($id){
	$icone = 'glyphicon glyphicon-print';
	
	$icone='class="'.$icone.'"';
	
	return "<center><a title=\"$title\" style='color:gray' onclick='javascript:imprimir_caixa(\"$id\");' ><spam $icone></spam></a></center>";
	
}



function index(){

	cabecalho("Relatório de Caixa");

	if($_POST[busca_codigo])
		$extra .= " AND caix_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND pdv_tx_nome LIKE '%$_POST[busca_nome]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],10);

	$botao[] = botao('Buscar','index');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM caixa,pdv,user WHERE caix_tx_status!='inativo' AND caix_nb_pdv=pdv_nb_id AND caix_nb_user=user_nb_id $extra";
	$cab = array('CÓDIGO','PDV','OPERADOR','DATA ABERTURA','DATA FECHAMENTO','');
	$val = array('caix_nb_id','pdv_tx_nome','user_tx_login','data(caix_tx_data,1)','data(caix_tx_dataFechamento,1)','icone_imprimir_caixa(caix_nb_id)');

	grid($sql,$cab,$val,'','','0','desc','25');


	?><form action="../pdv/impressao.php" id="form_imprime_caixa" name="form_imprime_caixa" method="post" target="_blank">
		<input type="hidden" name="acao" value="imprimir_caixa">
		<input type="hidden" name="id" value="">
	</form>
	<script type="text/javascript">
		function imprimir_caixa(id_caixa){
			document.form_imprime_caixa.id.value = id_caixa;
			document.getElementById("form_imprime_caixa").submit();
		}
	</script><?php

	rodape();
}