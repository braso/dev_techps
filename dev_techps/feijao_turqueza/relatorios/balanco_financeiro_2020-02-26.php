<?php
include_once $_SERVER['DOCUMENT_ROOT']."/contex20/funcoes_vendas.php";
include "../conecta.php";






function layout_relatorio(){
	// print_r($_POST);

	if($_POST[busca_data1]!='')
		$extra .= " AND bole_tx_data >= '$_POST[busca_data1]' ";

	if($_POST[busca_data2]!='')
		$extra .= " AND bole_tx_data <= '$_POST[busca_data2]' ";

	$extra .= " AND bole_tx_status = 'Encerrado'";

	$extra_receita = " AND movi_tx_tipo='Receita' ";
	$extra_despesa = " AND movi_tx_tipo='Despesa' ";


	cabeca_planilha('Balanço Financeiro');

	?><header>
		<aside>
			<figure>
				<img src="../imagens/logo_cliente.png" alt="../imagens/logo_cliente.png"/>
			</figure>
			<!-- <p class="left">Mossoró/RN</p> -->
		</aside>
		<center>
			<h1>BALANÇO FINANCEIRO</h1>
		</center>			
		<aside>
			<p class="right">Emissão: <span><br><?=date("d/m/Y")?></span></p>
		</aside>
	</header><?php


	$cabecalho = array('CÓD.','ENTIDADE','DESCRIÇÃO','PLANO CONTA','VENCIMENTO','VALOR','DATA PG.','VALOR PG');


	$sql_receita = query("SELECT * FROM boleto,entidade,movimento,planoconta WHERE movi_nb_planoconta = plan_nb_id AND bole_nb_movimento = movi_nb_id AND movi_nb_entidade = enti_nb_id AND bole_tx_status != 'inativo' $extra_receita $extra ORDER BY bole_tx_data ASC");
	while($a = carrega_array($sql_receita)){
		
		$valores[] = array($a[bole_nb_id],$a[enti_tx_nome],$a[bole_tx_obs],$a[plan_tx_nome],data($a[bole_tx_vencimento]),valor($a[bole_tx_valordoc]),data($a[bole_tx_data]),valor($a[bole_tx_valor]));
		
		$total_receita_doc += $a[bole_tx_valordoc];
		$total_receita_pg  += $a[bole_tx_valor];
	}
	$valores[] = array('','','','','<b>TOTAL:</b>','<b>'.valor($total_receita_doc,1),'','<b>'.valor($total_receita_pg,1));

	grid_rel($cabecalho,$valores,'RECEITAS');
	unset($valores);




	$sql_despesas = query("SELECT * FROM boleto,entidade,movimento,planoconta WHERE movi_nb_planoconta = plan_nb_id AND bole_nb_movimento = movi_nb_id AND movi_nb_entidade = enti_nb_id AND bole_tx_status != 'inativo' $extra_despesa $extra ORDER BY bole_tx_data ASC");
	while($a = carrega_array($sql_despesas)){
		
		$valores[] = array($a[bole_nb_id],$a[enti_tx_nome],$a[bole_tx_obs],$a[plan_tx_nome],data($a[bole_tx_vencimento]),valor($a[bole_tx_valordoc]),data($a[bole_tx_data]),valor($a[bole_tx_valor]));
		
		$total_despesa_doc += $a[bole_tx_valordoc];
		$total_despesa_pg  += $a[bole_tx_valor];
	}
	$valores[] = array('','','','','<b>TOTAL:</b>','<b>'.valor($total_despesa_doc,1),'','<b>'.valor($total_despesa_pg,1));

	grid_rel($cabecalho,$valores,'DESPESAS');



	echo "<p style='text-align: left'><b>SALDO PREVISTO: ".valor($total_receita_doc-$total_despesa_doc,1)."</b></p>";
	echo "<p style='text-align: left'><b>SALDO EFETIVO: ".valor($total_receita_pg-$total_despesa_pg,1)."</b></p>";

}





function grid_rel($cabecalho,$valores,$titulo=''){
    

	echo "<table class='tabela_rel'>";
	
	if(count($cabecalho)>0){

		echo "<thead>";
			if ( $titulo!='' ) {// SE PRECISAR EXIBIR UM TÍTULO NA TABELA
				echo "<tr><th colspan=".count($cabecalho).">$titulo</th></tr>";
			}
			echo "<tr>";
			for($i=0;$i<count($cabecalho);$i++){
				if ( is_array($cabecalho[$i]) ) {
					echo "<th width='".$cabecalho[$i][key($cabecalho[$i])]."' >".key($cabecalho[$i])."</th>";
				} else {
					echo "<th>$cabecalho[$i]</th>";
				}
			}
			echo "</tr>";
		echo "</thead>";
	}

	if(count($valores)>0){
		echo "<tbody>";
		
		for($i=0;$i<count($valores);$i++){
			echo "<tr>";
			for($j=0;$j<count($valores[$i]);$j++){
				echo "<td>".$valores[$i][$j]."</td>";
			}
			echo "</tr>";
		}

		echo "</tbody>";
	}

	echo "</table>";

	

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
			table.tabela_rel tr:nth-child(even) {
					background-color: #eee;
			}
			table.tabela_rel tr:nth-child(odd) {
				 background-color: #fff;
			}
			table.tabela_rel th {
					background-color: silver;
					color: #000;
			}					
			table.tabela_rel th.total {
					background-color: gray;
					color: #fff;
			}		
			table.tabela_rel td.dt{
				text-align: center;
				font-weight: bold;
			}
			table.tabela_rel {
				margin-bottom: 25px;
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
				table.tabela_rel th{
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




function index(){

	cabecalho("Balanço Financeiro");


	$c[] = campo_data('Data Inicial','busca_data1','',2);
	$c[] = campo_data('Data Final','busca_data2','',2);

	$botao[] = '<button onclick="imprime_relatorio();" name="acao" id="bt_acao" type="button" class="btn default">Imprimir</button>';
	
	abre_form('Filtro de Busca');
	linha_form($c);
	?><input type="hidden" name="acao" value="layout_relatorio"><?php
	fecha_form($botao);


	?><script type="text/javascript">
		function imprime_relatorio(){
			document.contex_form.target='_blank';
			document.contex_form.submit();
		}
	</script><?php

	rodape();
}