<?php
include_once $_SERVER['DOCUMENT_ROOT']."/contex20/funcoes_vendas.php";
include "../conecta.php";






function layout_relatorio(){
	// print_r($_POST);

	if($_POST[busca_data1]!='')
		$extra .= " AND orde_tx_dataCadastro >= '$_POST[busca_data1] 00:00:00' ";

	if($_POST[busca_data2]!='')
		$extra .= " AND orde_tx_dataCadastro <= '$_POST[busca_data2] 23:59:59' ";

	if($_POST[busca_cliente]!='')
		$extra .= " AND enti_nb_id = '$_POST[busca_cliente]' ";


	cabeca_planilha('Produtos por Clientes');

	?><header>
		<aside>
			<figure>
				<img src="../imagens/logo_cliente.png" alt="../imagens/logo_cliente.png"/>
			</figure>
			<!-- <p class="left">Mossoró/RN</p> -->
		</aside>
		<center>
			<h1>PRODUTOS POR CLIENTES</h1>
		</center>			
		<aside>
			<p class="right">Emissão: <span><br><?=date("d/m/Y")?></span></p>
		</aside>
	</header><?php


	$sql_clientes = query("SELECT * FROM ordem,entidade WHERE orde_nb_entidade=enti_nb_id AND orde_tx_status!='inativo' AND orde_tx_situacao='finalizado' $extra GROUP BY enti_nb_id ORDER BY enti_tx_nome ASC ");
	while($a_cliente = carrega_array($sql_clientes)){
		echo "<section>
			<br><p><b>$a_cliente[enti_tx_nome]:</b></p>
		</section>";

		$valores    = array();
		$total_qtde = 0;
		$sql_produtos = query("SELECT prod_nb_id, prod_tx_nome, SUM(orpr_tx_quantidade) AS qtde FROM orpr,produto,ordem WHERE orpr_nb_produto=prod_nb_id AND orpr_nb_ordem=orde_nb_id AND orpr_tx_status!='inativo' AND orde_tx_status!='inativo' AND orde_tx_situacao='finalizado' AND orde_nb_entidade = '$a_cliente[enti_nb_id]' GROUP BY prod_nb_id ORDER BY prod_tx_nome ASC ");
		while($a_produto = carrega_array($sql_produtos)){
			$valores[] = array($a_produto[prod_nb_id],$a_produto[prod_tx_nome],valor_3_casas($a_produto[qtde],1));
			$total_qtde += $a_produto[qtde];
		}
		$valores[] = array('','<b>TOTAL:</b>','<b>'.valor_3_casas($total_qtde,1).'</b>');
		$cabecalho = array('CÓD','NOME','QTDE');
		grid_rel($cabecalho,$valores);
	}
}





function grid_rel($cabecalho,$valores){
    

	echo "<table class='tabela_rel'>";
	
	if(count($cabecalho)>0){

		echo "<thead><tr>";
			for($i=0;$i<count($cabecalho);$i++){
				echo "<th>$cabecalho[$i]</th>";
			}
		echo "</thead></tr>";
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

	cabecalho("Produtos por Cliente");


	$c[] = campo_data('Data Inicial','busca_data1','',2);
	$c[] = campo_data('Data Final','busca_data2','',2);
	$c[] = combo_net('Cliente','busca_cliente','',3,'entidade');
	// $c[] = combo_net('Produto/Serviço','busca_produto','',3,'produto');

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