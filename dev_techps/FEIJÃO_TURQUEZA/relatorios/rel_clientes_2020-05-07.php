<?php
include "../conecta.php";

function cabeca_planilha(){
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<title>Relatório de Pontos</title>	
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

	cabeca_planilha();
	
		?>
		<header>
			<aside>
				<figure>
					<img src="../imagens/logo_cliente.png" alt="imagens/logo_cliente.png"/>
				</figure>
				<p class="left">Mossoró/RN</p>
			</aside>
			<center>
				<h1>RELATÓRIO DE CLIENTES</h1>
			</center>
		</header>
		<hr>
		<section>
			<table id="t01">
				<tr>
					<th>NOME</th>
					<th>TELEFONE</th>					
					<th>E-MAIL</th>					
					<th>CIDADE</th>										
				</tr>
				<?
					$sql = query("SELECT * FROM entidade,cidade WHERE enti_tx_status != 'inativo' AND enti_nb_cidade = cida_nb_id AND enti_tx_tipo = 'Cliente' ORDER BY enti_tx_nome ASC");

					while($a = carrega_array($sql)){
						?>
						<tr>
							<td style="text-align: left;padding-left: 1%;"><?=$a[enti_tx_nome]?></td>
							<td style="text-align: center;"><?=$a[enti_tx_fone1]?></td> 						
							<td style="text-align: center;"><?=$a[enti_tx_email]?></td> 						
							<td style="text-align: center;"><?=$a[cida_tx_nome]?></td> 						
						</tr>
						<?
					}
				?>
			</table>
		</section>
	</body>
	</html>
	<?	

}