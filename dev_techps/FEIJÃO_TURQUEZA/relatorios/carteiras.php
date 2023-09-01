<?php
include "../conecta.php";





function modifica_cliente(){
	global $a_mod;

	$a_mod = carregar('entidade',$_POST[id]);

	layout_carteira();
	exit;
}



function layout_carteira(){
	global $a_mod;
	cabecalho("Relatório de Carteiras");


	$c[]=texto('Código',$a_mod[enti_nb_id],2);
	$c[]=texto('Nome',$a_mod[enti_tx_nome],4);
	$c[]=texto('CPF/CNPJ',$a_mod[enti_tx_cpf],2);
	$c[]=texto('Crédito Usado (Dívida)',valor($a_mod[enti_tx_creditoUsado],1),3);
	
	// $b[]=botao('Gravar','cadastra_cliente','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Cliente');
	linha_form($c);
	
	fecha_form($b);


	// $sql = "SELECT * FROM iteminventario,produto WHERE item_nb_inventario='$_POST[id]' AND prod_tx_status!='inativo' AND item_tx_status!='inativo' AND item_nb_produto=prod_nb_id ";
	// $cab = array('CÓDIGO','PRODUTO','MARCA','CATEGORIA','VALOR','QUANTIDADE');
	// $val = array('prod_nb_id','prod_tx_nome','carrega_marca(prod_nb_marca)','carrega_categoria(prod_nb_categoria)','valor(prod_tx_preco,1)','exibe_campo_quantidade(item_nb_id)');

	// grid($sql,$cab,$val,'','','0','desc','-1');

	?><table class="table compact table-striped table-bordered table-hover dt-responsive dataTable no-footer dtr-inline" role="grid" style="width: 70%;" width="100%">
		<thead>
			<tr role="row">
				<th style="width: 164px;">DATA</th>
				<th style="width: 322px;">DÉBITO</th>
				<th style="width: 179px;">CRÉDITO</th>
				<th style="width: 269px;">SALDO</th>
			</tr>
		</thead>
		<tbody><?php

			$saldo = 0;
				
			$extra_sql .= " AND movi_nb_entidade='$a_mod[enti_nb_id]' ";
			$extra_sql .= " AND (orde_tx_tipo='recebimento carteira' OR form_tx_carteira='sim') ";
			$extra_sql .= " AND bole_tx_status != 'inativo' AND fobo_tx_status != 'inativo' ";

			$sql_extrato = query("SELECT * FROM fobo,forma,boleto,movimento,ordem WHERE fobo_nb_boleto=bole_nb_id AND fobo_nb_forma=form_nb_id AND bole_nb_movimento=movi_nb_id AND movi_nb_ordem=orde_nb_id $extra_sql ORDER BY fobo_tx_data ASC ");
			while ( $row = $sql_extrato->fetch_assoc() ) {
				$j++;

				$valor_credito = '';
				$valor_debito  = '';
				if ( strtolower($row['form_tx_carteira'])=='sim' )
					$valor_debito = $row['fobo_tx_valor'];
				else
					$valor_credito = $row['fobo_tx_valor'];

				$saldo = $saldo+$valor_credito-$valor_debito;


				if ( ($j%2) == 0 )
					$classe = 'odd';
				else
					$classe = 'even';

				?><tr role="row" class="<?=$classe?>">
					<td><?=data($row['fobo_tx_data'],1)?></td>
					<td><?=valor($valor_debito,1)?></td>
					<td><?=valor($valor_credito,1)?></td>
					<td><?=valor($saldo,1)?></td>
				</tr><?php
			}

		?></tbody>
	</table>
	<br><?



	rodape();

}




function index(){
	cabecalho("Relatório de Carteiras");

	if($_POST[busca_codigo])
		$extra .=" AND enti_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND enti_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_cpf])
		$extra .=" AND enti_tx_cpf LIKE '%$_POST[busca_cpf]%'";

	if ( $_POST[busca_exibir]=='Em Aberto' || $_POST[busca_exibir]=='' ) {
		$extra .=" AND enti_tx_creditoUsado != 0 ";
	} elseif ( $_POST[busca_exibir]=='Quitado' ) {
		$extra .=" AND enti_tx_creditoUsado = 0 ";
	}


	
	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],4);
	$c[]=campo('CPF','busca_cpf',$_POST[busca_cpf],2,MASCARA_CPF);
	$c[]=combo('Exibir','busca_exibir',$_POST[busca_exibir],2,array('Em Aberto','Quitado','Todos'));


	$b[]=botao('Buscar','index');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM entidade WHERE enti_tx_status != 'inativo' AND enti_tx_tipo = 'Cliente' $extra";
	$cab = array('CÓDIGO','NOME','CPF','CRÉDITO USADO','');
	$val = array('enti_nb_id','enti_tx_nome','enti_tx_cpf','valor(enti_tx_creditoUsado,1)','icone_modificar(enti_nb_id,modifica_cliente)');

	grid($sql,$cab,$val);

	rodape();

}


?>