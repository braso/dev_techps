<?php
include_once $_SERVER['DOCUMENT_ROOT']."/contex20/funcoes_vendas.php";
include "../conecta.php";







function imprimir_caixa(){

	if ( intval($_POST[id])==0 ) {
		echo "ERRO: Código em falta!";
		exit;
	}

	
	cabeca_planilha('Fechamento de Caixa');


	$a_caixa = carregar('caixa',$_POST[id]);
	$a_pdv   = carregar('pdv',$a_caixa[caix_nb_pdv]);
	$a_user  = carregar('user',$a_caixa[caix_nb_user]);


		?>
		<header>
			<aside>
				<figure>
					<img src="../imagens/logo_cliente.png" alt="../imagens/logo_cliente.png"/>
				</figure>
				<!-- <p class="left">Mossoró/RN</p> -->
			</aside>
			<center>
				<h1><?=$a_pdv[pdv_tx_nome]?></h1>
				<h4>CÓD: <?=str_pad($_POST[id],4,0,STR_PAD_LEFT)?></h4>
			</center>			
			<aside>
				<p class="right">Emissão: <span><br><?=date("d/m/Y")?></span></p>
			</aside>
		</header>
		<hr>
		<section>
			<p><b>RECEBIMENTOS:</b></p>
			<table id="t01">
				<tr>
					<th>&nbsp;&nbsp;&nbsp;&nbsp;</th>
					<th>FORMA</th>
					<th>VENDA</th>	
					<th>VALOR (R$)</th>			
				</tr>
				<?
				$extra_recebimento = " AND orde_nb_caixa='$a_caixa[caix_nb_id]' AND fobo_tx_status!='inativo' AND bole_tx_status!='inativo' AND orde_tx_status!='inativo' AND orde_tx_situacao='finalizado' ";
				$sql_recebimentos = query(" SELECT * FROM fobo,boleto,movimento,ordem WHERE fobo_nb_boleto=bole_nb_id AND bole_nb_movimento=movi_nb_id AND movi_nb_ordem=orde_nb_id $extra_recebimento GROUP BY fobo_nb_id ");
				while($row = carrega_array($sql_recebimentos)){
					$j++;

					$a_forma = carregar('forma',$row[fobo_nb_forma]);

					?><tr>
						<td class="text-center"><?=$j?></td>
						<td><?=$a_forma['form_tx_nome']?></td>
						<td class="text-center"><?=$row['orde_nb_id']?></td>
						<td class="text-center"><?=valor($row['fobo_tx_valor'],1)?></td>
					</tr><?php

					$total_recebimento += $row['fobo_tx_valor'];
				}

				?><tr>
					<td class="text-center">&nbsp;</td>
					<td><b>TOTAL:<b></td>
					<td class="text-center">&nbsp;</td>
					<td class="text-center"><b><?=valor($total_recebimento,1)?><b></td>
				</tr><?php

				?>
			</table>
		</section>




		<hr>
		<section>
			<p><b>OPERAÇÕES:</b></p>
			<table id="t02">
				<tr>
					<th>&nbsp;&nbsp;&nbsp;&nbsp;</th>
					<th>ID</th>
					<th>TIPO</th>
					<th>OBSERVAÇÃO</th>
					<th>VALOR (R$)</th>
				</tr>
				<?php

				$j = 0;
				$sql_operacao = "SELECT * FROM operacaocaixa WHERE oper_nb_caixa = '$a_caixa[caix_nb_id]' AND oper_tx_status!='inativo' ORDER BY oper_nb_id ASC";

				$result = query($sql_operacao);
				while($row = $result->fetch_assoc()){
					$j++;

					?><tr>
						<td class="text-center"><?=$j?></td>
						<td class="text-center"><?=$row['oper_nb_id']?></td>
						<td><?=$row['oper_tx_tipo']?></td>
						<td class="text-center"><?=$row['oper_tx_obs']?></td>
						<td class="text-center"><?=valor($row['oper_tx_valor'],1)?></td>
					</tr><?php

					if ( strtolower($row['oper_tx_tipo'])=='sangria' )
						$total_operacao -= $row['oper_tx_valor'];
					else
						$total_operacao += $row['oper_tx_valor'];
				}

				?><tr>
					<td class="text-center">&nbsp;</td>
					<td class="text-center">&nbsp;</td>
					<td><b>TOTAL:<b></td>
					<td class="text-center">&nbsp;</td>
					<td class="text-center"><b><?=valor($total_operacao,1)?><b></td>
				</tr><?php
				?>
			</table>
		</section>




		<hr>
		<section>
			<p><b>FECHAMENTO:</b></p>
			<table id="t03">
				<tr>
					<th>&nbsp;&nbsp;&nbsp;&nbsp;</th>
					<th>ID</th>
					<th>FORMA</th>
					<th>INFORMADO (R$)</th>
					<th>SISTEMA (R$)</th>
					<th>DIFERENÇA (R$)</th>
				</tr>
				<?php

				$j = 0;
				$sql_fechamento = "SELECT * FROM fechamento,forma WHERE fech_nb_forma=form_nb_id AND fech_nb_caixa = '$a_caixa[caix_nb_id]' AND fech_tx_status!='inativo' ORDER BY fech_nb_id ASC";

				$result = query($sql_fechamento);
				while($row = $result->fetch_assoc()){
					$j++;

					?><tr>
						<td class="text-center"><?=$j?></td>
						<td class="text-center"><?=$row['fech_nb_id']?></td>
						<td><?=$row['form_tx_nome']?></td>
						<td class="text-center"><?=valor($row['fech_tx_valor'],1)?></td>
						<td class="text-center"><?=valor($row['fech_tx_valorSistema'],1)?></td>
						<td class="text-center"><?=valor($row['fech_tx_diferenca'],1)?></td>
					</tr><?php

					$total_informado += $row['fech_tx_valor'];
					$total_sistema   += $row['fech_tx_valorSistema'];
					$total_diferenca += $row['fech_tx_diferenca'];
				}

				?><tr>
					<td class="text-center">&nbsp;</td>
					<td class="text-center">&nbsp;</td>
					<td><b>TOTAL:<b></td>
					<td class="text-center"><b><?=valor($total_informado,1)?><b></td>
					<td class="text-center"><b><?=valor($total_sistema,1)?><b></td>
					<td class="text-center"><b><?=valor($total_diferenca,1)?><b></td>
				</tr><?php
				?>
			</table>
		</section>
		<!-- <footer>
			<aside>
				<p>Cliente: <?=mb_strtoupper($a_entidade[enti_tx_nome])?></p>
			</aside>
		</footer> -->
	</body>
	</html>
	<?

	exit;	
}





function imprimir_venda(){

	if ( intval($_POST[id])==0 ) {
		echo "ERRO: Código em falta!";
		exit;
	}

	
	cabeca_planilha('Venda: '.$_POST[id]);


	$a_ordem = carregar('ordem',$_POST[id]);
	$a_entidade = carregar('entidade',$a_ordem[orde_nb_entidade]);

		?>
		<header>
			<aside>
				<figure>
					<img src="../imagens/logo_cliente.png" alt="../imagens/logo_cliente.png"/>
				</figure>
				<!-- <p class="left">Mossoró/RN</p> -->
			</aside>
			<center>
				<h1>VENDA: <?=str_pad($_POST[id],4,0,STR_PAD_LEFT)?></h1>
			</center>			
			<aside>
				<p class="right">Emissão: <span><br><?=date("d/m/Y")?></span></p>
			</aside>
		</header>
		<hr>
		<section>
			<p><b>PRODUTOS/SERVIÇOS</b></p>
			<table id="t01">
				<tr>
					<th>&nbsp;&nbsp;&nbsp;&nbsp;</th>
					<th>ID</th>
					<th>PRODUTO</th>
					<th>QTDE</th>	
					<th>UNITÁRIO</th>
					<th>DESCONTO</th>	
					<th>TOTAL</th>				
				</tr>
				<?
				$sql = query("SELECT * FROM orpr,produto WHERE orpr_nb_ordem = '$a_ordem[orde_nb_id]' AND orpr_nb_produto=prod_nb_id ORDER BY orpr_nb_id ASC");
				while($row = carrega_array($sql)){
					$j++;

					if ($row['orpr_tx_descontoReais']>0)
						$string_descontos = valor($row['orpr_tx_descontoReais'],1).'&nbsp;('.valor($row['orpr_tx_descontoPorcentagem']).'%)';
					else
						$string_descontos = '';


					if ( $row['orpr_tx_status']=='inativo' ) {
						$riscar  = '<strike>';
						$riscar2 = '</strike>';
					} else {				
						$riscar  = '';
						$riscar2 = '';
					}

					?><tr>
						<td class="text-center"><?=$riscar.$j.$riscar2?></td>
						<td class="text-center"><?=$riscar.$row['prod_nb_id'].$riscar2?></td>
						<td><?=$riscar.$row['prod_tx_nome'].$riscar2?></td>
						<td class="text-center"><?=$riscar.valor_3_casas($row['orpr_tx_quantidade'],1).$riscar2?></td>
						<td class="text-center"><?=$riscar.valor($row['orpr_tx_valorUnitario'],1).$riscar2?></td>
						<td class="text-center"><?=$riscar.$string_descontos.$riscar2?></td>
						<td class="text-center"><?=$riscar.valor($row['orpr_tx_valor'],1).$riscar2?></td>
					</tr><?php

					if ( $row['orpr_tx_status']!='inativo' ) {
						// SOMA APENAS SE ESTIVER ATIVO
						$qtde_itens++;
						$valor_total_venda += $row['orpr_tx_valor'];
					}				
				}

				?><tr>
					<td class="text-center">&nbsp;</td>
					<td class="text-center">&nbsp;</td>
					<td><b>TOTAL:<b></td>
					<td class="text-center">&nbsp;</td>
					<td class="text-center">&nbsp;</td>
					<td class="text-center">&nbsp;</td>
					<td class="text-center"><b><?=valor($valor_total_venda,1)?><b></td>
				</tr><?php

				?>
			</table>
		</section>




		<hr>
		<section>
			<p><b>PAGAMENTOS</b></p>
			<table id="t02" style="width: 50%">
				<tr>
					<th>ID</th>
					<th>FORMA</th>
					<th>VALOR</th>
				</tr>
				<?php

				$valor_total_pago = 0;
				$sql_pagamento = "SELECT * FROM pagamento,forma WHERE paga_nb_ordem = '$a_ordem[orde_nb_id]' AND paga_nb_forma=form_nb_id ORDER BY paga_nb_id ASC";

				$result = query($sql_pagamento);
				while($row = $result->fetch_assoc()){

					if ( $row['paga_tx_status']!='inativo' ) {
						?><tr>
							<td class="text-center"><?=$row['paga_nb_id']?></td>
							<td><?=$row['form_tx_nome']?></td>
							<td class="text-center"><?=valor($row['paga_tx_valor'],1)?></td>
						</tr><?php
						$valor_total_pago += $row['paga_tx_valor'];

					} else {
						// SE O PAGAMENTO ESTIVER SIDO REMOVIDO
						?><tr>
							<td class="text-center"><strike><?=$row['paga_nb_id']?></strike></td>
							<td><strike><?=$row['form_tx_nome']?></strike></td>
							<td class="text-center"><strike><?=valor($row['paga_tx_valor'],1)?></strike></td>
						</tr><?php
					}
				}

				if ( $valor_total_pago>0 ) {
					?><tr>
						<td class="text-center"></td>
						<td><b>TOTAL RECEBIDO:</b></td>
						<td class="text-center"><b><?=valor($valor_total_pago,1)?></b></td>
					</tr><?php
				}

				$saldo = $valor_total_venda-$valor_total_pago;

				if ( $saldo>0 ) {
					// SE FALTAR RECEBER ALGUM VALOR
					?><tr>
						<td class="text-center"></td>
						<td><b>FALTA RECEBER:</b></td>
						<td class="text-center"><b><?=valor($saldo,1)?></b></td>
					</tr><?php

				} elseif ( $saldo<0 ) {
					// MOSTRA O VALOR DO TROCO
					?><tr>
						<td class="text-center"></td>
						<td><b>TROCO:</b></td>
						<td class="text-center"><b><?=valor($saldo*(-1),1)?></b></td>
					</tr><?php
				}

				?>
			</table>
		</section>
		<footer>
			<aside>
				<p>Cliente: <?=mb_strtoupper($a_entidade[enti_tx_nome])?></p>
			</aside>
		</footer>
	</body>
	</html>
	<?

	exit;	
}





function cabeca_planilha($titulo='Relatório'){
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<title><?=$titulo?></title>	
		<style>
			body{
				font-family: arial;
			}
			header{
				position: relative;
				margin: 0 0 0.3% 0;
				padding: 0;
				width: 100%;
				float: left;
				/*background-color: red;*/
			}
			header aside{
				position: relative;
				margin: 0;
				padding: 0;
				width: 20%;
				float: left;
				/*background-color: yellow;*/
			}
			header aside figure{
				position: relative;
				margin: 0;
				padding: 2%;
				width: 96%;
				float: left;
				/*background-color: lightblue;*/
			}
			header aside figure img{
				position: relative;
				margin: 0;
				padding: 0;
				width: 100%;
				float: left;			
			}
			header aside p.left{
				position: relative;
				margin: 0;
				padding: 1% 0;
				width: 100%;
				text-align: center;
				float: left;
				font-weight: bold;
				/*background-color: brown;*/
			}
			header aside p.right{
				position: relative;
				margin: 0;
				padding: 12.7% 0;
				width: 100%;
				text-align: center;
				float: left;
				font-weight: bold;
				/*background-color: brown;*/
			}
			header center{
				position: relative;
				margin: 0;
				padding: 1.9% 0;
				width: 60%;
				float: left;
				/*background-color: orange;*/
			}
			section{
				position: relative;
				margin: 0;
				padding: 0 1% 0.8% 1%;
				width: 98%;
				float: left;
				/*background-color: blue;*/
			}		
			table {
				position: relative;
				margin: 0;
				padding: 0;
					width:100%;
					float: left;
					/*background-color: purple*/
			}
			table, th, td {
					border: 1px solid black;
					border-collapse: collapse;
			}
			th, td {			
					padding: 0;		    
			}
			table#t01 tr:nth-child(even) {
					background-color: #eee;
			}
			table#t01 tr:nth-child(odd) {
				 background-color: #fff;
			}
			table#t01 th {
					background-color: silver;
					color: #000;
			}					
			table#t01 th.total {
					background-color: gray;
					color: #fff;
			}		
			table#t01 td.dt{
				text-align: center;
				font-weight: bold;
			}


			table#t02 tr:nth-child(even) {
					background-color: #eee;
			}
			table#t02 tr:nth-child(odd) {
				 background-color: #fff;
			}
			table#t02 th {
					background-color: silver;
					color: #000;
			}					
			table#t02 th.total {
					background-color: gray;
					color: #fff;
			}		
			table#t02 td.dt{
				text-align: center;
				font-weight: bold;
			}


			table#t03 tr:nth-child(even) {
					background-color: #eee;
			}
			table#t03 tr:nth-child(odd) {
				 background-color: #fff;
			}
			table#t03 th {
					background-color: silver;
					color: #000;
			}					
			table#t03 th.total {
					background-color: gray;
					color: #fff;
			}		
			table#t03 td.dt{
				text-align: center;
				font-weight: bold;
			}
			footer{
				position: relative;
				margin: 0;
				padding: 0;
				width: 100%;
				float: left;
				/*background-color: green;*/
			}
			footer aside{
				position: relative;
				margin: 0;
				padding: 0;
				width: 50%;
				float: left;
				font-weight: bold;
				font-size: 10pt;
				/*background-color: pink;*/
			}
			footer aside p{
				position: relative;
				margin: 0;
				padding: 0 0 0 2%;
				width: 98%;
				float: left;
				/*background-color: purple;*/
			}
			footer aside.aright{			
				padding: 1.35% 0;
			}
			footer aside.aright p.ass{
				padding: 0.6% 0 0.6% 35%;
				width: 65%;
				font-weight: bold;
				font-size: 8pt;
			}
			@media print{				
				header aside p.left{
					font-size: 8pt;
				}
				header center{
					padding: 1.7% 0;					
				}
				header h1{
					font-size: 12pt;
				}
				header center h3{
					font-size: 10pt;
				}
				header aside p.right{
					padding: 9% 0;
					font-size: 10pt;
				}
				table#t01 th{
					font-size: 12pt;
				}
				table#t02 th{
					font-size: 12pt;
				}
				table#t03 th{
					font-size: 12pt;
				}
				td{
					font-size: 10pt;
				}
				footer aside p{
					font-size: 8pt;
				}
			}
		</style>
	</head>
	<body>
	<?
}

