<?php
include "../conecta.php";




function exclui_venda(){

	$id_venda = intval($_POST[id]);

	$a_ordem = carregar('ordem',$id_venda);
	$a_cliente = carregar('entidade',$a_ordem[orde_nb_entidade]);

	// if ( strtolower($a_ordem[orde_tx_tipo])=='recebimento carteira' ) {
	// 	set_status('ATENÇÃO: Não é possível excluir recebimentos de carteiras!');
	// 	index();
	// 	exit;
	// }



	// REMOVE OS ORPR
	$sql_orpr = query(" SELECT * FROM orpr WHERE orpr_nb_ordem = '$id_venda' AND orpr_tx_status!='inativo' ");
	while ( $a_orpr = carrega_array($sql_orpr) ) {
		remover('orpr',$a_orpr[orpr_nb_id]);
	}

	// REMOVE OS PAGAMENTOS
	$valor_carteira = 0;
	$sql_pagamento = query(" SELECT * FROM pagamento,forma WHERE paga_nb_ordem = '$id_venda' AND paga_tx_status!='inativo' AND paga_nb_forma=form_nb_id ");
	while ( $a_paga = carrega_array($sql_pagamento) ) {
		remover('pagamento',$a_paga[paga_nb_id]);

		if (strtolower($a_paga[form_tx_carteira])=='sim') {// CONTABILIZA OS RECEBIMENTOS EM CARTEIRA
			$valor_carteira += $a_paga[paga_tx_valor];
		}
	}


	// NESSA PARTE É FEITO O AJUSTE DO SALDO DO CLIENTE

	if ( $valor_carteira>0 ) {// SE TIVER EXCLUIDO ALGUM PAGAMENTO EM CARTEIRA, REMOVE DO SALDO USADO
		atualizar('entidade',array(enti_tx_creditoUsado),array($a_cliente[enti_tx_creditoUsado]-$valor_carteira),$a_cliente[enti_nb_id]);
	}

	if( strtolower($a_ordem[orde_tx_tipo])=='recebimento carteira' ) {// SE EXCLUIR UM PAGAMENTO DE CARTEIRA, RETORNA O SALDO PARA O CLIENTE
		atualizar('entidade',array(enti_tx_creditoUsado),array($a_cliente[enti_tx_creditoUsado]+$a_ordem[orde_tx_valor]),$a_cliente[enti_nb_id]);
	}


	// REMOVE TODA A PARTE FINANCEIRA, CASO TENHA SIDO GERADA
	$sql_movimento = query("SELECT * FROM movimento WHERE movi_nb_ordem='$id_venda' AND movi_tx_status != 'inativo'");
	while ( $a_movi = carrega_array($sql_movimento) ) {
		remover('movimento',$a_movi[movi_nb_id]);
		
		// REMOVE OS BOLETOS
		$sql_boleto = query("SELECT * FROM boleto WHERE bole_nb_movimento='$a_movi[movi_nb_id]' AND bole_tx_status != 'inativo'");
		while ( $a_bole = carrega_array($sql_boleto) ) {
			remover('boleto',$a_bole[bole_nb_id]);
			
			// REMOVE OS FOBOS
			$sql_fobo = query("SELECT * FROM fobo WHERE fobo_nb_boleto='$a_bole[bole_nb_id]' AND fobo_tx_status != 'inativo'");
			while ( $a_fobo = carrega_array($sql_fobo) ) {
				remover('fobo',$a_fobo[fobo_nb_id]);
			}
		}
	}


	// SE A ORDEM POSSUIR UM ATENDIMENTO ASSOCIADO, TAMBÉM O REMOVE
	if ( $a_ordem[orde_nb_atendimento]>0 ) {
		$sql_atendimento = query("SELECT * FROM atendimento WHERE aten_nb_id='$a_ordem[orde_nb_atendimento]' AND aten_tx_status='ativo' AND aten_tx_situacao != 'Atendido'");
		while ( $a_aten = carrega_array($sql_atendimento) ) {
			remover('atendimento',$a_aten[aten_nb_id]);
		}
	}



	// POR FIM, REMOVE OS REGISTROS DA TABELA ORDEM
	remover('ordem',$id_venda);


	index();
	exit;
}





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
	if($_POST[busca_data1]!='')
		$extra .= " AND orde_tx_data >= '$_POST[busca_data1]' ";
	if($_POST[busca_data2]!='')
		$extra .= " AND orde_tx_data <= '$_POST[busca_data2]' ";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],6);
	$c[] = campo_data('Data Inicial','busca_data1',$_POST[busca_data1],2);
	$c[] = campo_data('Data Final','busca_data2',$_POST[busca_data2],2);

	$botao[] = botao('Buscar','index');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM ordem,entidade WHERE orde_tx_status!='inativo' AND orde_tx_situacao='finalizado' AND orde_nb_entidade=enti_nb_id $extra";
	$cab = array('CÓDIGO','NOME','DATA','VALOR BRUTO','DESCONTO R$','VALOR LÍQUIDO','TIPO','','');
	$val = array('orde_nb_id','enti_tx_nome','data(orde_tx_data)','valor(orde_tx_valorBruto)','valor(orde_tx_descontoReais)','valor(orde_tx_valor)','orde_tx_tipo','icone_imprimir_venda(orde_nb_id)','icone_excluir(orde_nb_id,exclui_venda)');

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