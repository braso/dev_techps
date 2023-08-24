<!DOCTYPE html>
<html lang="pt-br">
<head>
	<title>IMPRESSÃO VISITA</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
	<style type="text/css">
		body .container{
			background-image: url(fundo.png);
			background-repeat: no-repeat;
			background-size: contain;
			background-position: 75% 100%;
			color: #663399;
		}
		header{
			padding: 1% 0;
		}
		.sub_linha{
			border-bottom: 1px solid #663399;
		}
		.linha_centro{
			position:relative;
			margin:0;
			padding: 1% 0;
			float:left;
			text-align: center;
		}
		.op_venda{
			text-align: right;
			font-weight: bold;
		}
		.op_aluguel{
			text-align: left;
			font-weight: bold;	
		}
		footer{
			padding: 5% 0 2% 0;
		}
		.side_footer{
			padding-left: 15%;
		}
		.side_footer .content{
			width:50%;
			border-top: 1px solid #663399;
		}
		.linha_separa_001{
			width: 100%;
			height: 150px;
		}
		.linha_separa_002{
			width: 100%;
			height: 70px;
		}
		.linha_separa_003{
			width: 100%;
			height: 80px;
		}
		@media print{
			header figure{
				width: 40%;
			}
			.ajuste001{
				width: 15%;
				float: left;
			}
			.ajuste002{
				width: 85%;
				float: left;
			}
			.ajuste003{
				width: 10%;
				float: left;
			}
			.ajuste004{
				width: 40%;
				float: left;
			}
			.ajuste005{
				width: 15%;
				float: left;
			}
			.ajuste006{
				width: 35%;
				float: left;
			}
			.ajuste007{
				width: 11%;
				float: left;
			}
			.ajuste008{
				width: 34%;
				float: left;
			}
			.ajuste009{
				width: 9%;
				float: left;
			}
			.ajuste010{
				width: 15%;
				float: left;
			}
			.ajuste011{
				width: 21%;
				float: left;
			}
			.ajuste012{
				width: 10%;
				float: left;
			}
			.ajuste013{
				width: 20%;
				float: left;
				text-align: right;
			}
			.ajuste014{
				width: 80%;
				float: left;
			}
			.ajuste015{
				width: 20%;
				float: left;
				text-align: right;
			}
			.ajuste016{
				width: 80%;
				float: left;
				padding-left: 20%;
			}
			.linha_separa_001{
				height: 20px;
				float: left;
			}
			.op_venda{
				width: 50%;
				float: left;
				text-align: center;
			}
			.op_aluguel{
				width: 50%;
				float: left;
				text-align: center;
			}
			.linha_separa_002{
				height: 20px;
				float: left;
			}
			.linha_separa_003{
				height: 30px;
				float: left;
			}
			.side_footer{
				width: 50%;
				float: left;
				padding-left: 0;
			}
			.side_footer .content{
				width: 100%;
				float: left;
			}
		}
	</style>
</head>
<body>
	<div class="container col-md-12">
		<header class="col-md-12">
			<figure class="col-md-6 col-sm-12">
				<img src="logo.png" alt="logo.png" />
			</figure>
		</header>
		<section class="col-md-12">
			<p class="col-md-12">
				<div class="col-md-1 ajuste001">
					Cliente(s):
				</div>
				<div class="col-md-11 sub_linha ajuste002">
					Teste
				</div>
			</p>
			<p class="col-md-12">
				<div class="col-md-1 ajuste003">
					Tel.:
				</div>
				<div class="col-md-5 sub_linha ajuste004">
					(84)99133-6358
				</div>
				<div class="col-md-1 text-center ajuste005">
					Corretor:
				</div>
				<div class="col-md-5 sub_linha ajuste006">
					Fernando Medeiros
				</div>
			</p>
			<p class="col-md-12">
				<div class="col-md-1 ajuste001">
					Bairro:
				</div>
				<div class="col-md-11 sub_linha ajuste002">
					Alto do Sumaré
				</div>
			</p>
			<p class="col-md-12">
				<div class="col-md-1 ajuste007">
					Cidade:
				</div>
				<div class="col-md-3 sub_linha ajuste008">
					Governador Dix-Sept Rosado
				</div>
				<div class="col-md-1 text-center ajuste009">
					Data:
				</div>
				<div class="col-md-3 sub_linha ajuste010">
					07/05/1990
				</div>
				<div class="col-md-1 text-center ajuste011">
					Hora da Visita:
				</div>
				<div class="col-md-3 sub_linha ajuste012">
					15:00
				</div>
			</p>
			<div class="linha_separa_001"></div>
			<p class="col-md-12 linha_centro">
				<div class="col-md-6 col-sm-6 op_venda">
					<span>Venda (  )</span>
				</div>
				<div class="col-md-6 col-sm-6 op_aluguel">
					<span>Aluguel (  )</span>					
				</div>
			</p>
			<div class="linha_separa_002"></div>
			<p class="col-md-12">
				<div class="col-md-6 col-sm-6 text-left ajuste013">
					<span>Casa (  )</span>
				</div>
				<div class="col-md-6 col-sm-6 text-left ajuste014">
					<span>Prédio ou Casa Comercial (  )</span>					
				</div>
			</p>
			<p>
				<div class="col-md-6 col-sm-6 text-left ajuste015">
					<span>Apartamento (  )</span>
				</div>
				<div class="col-md-6 col-sm-6 text-left ajuste016">
					<span>Terreno (  )</span>					
				</div>
			</p>
			<div class="linha_separa_003"></div>
			<p class="col-md-12">
				<div class="col-md-1">
					Imóvel:
				</div>
				<div class="col-md-11 sub_linha">
					Descrição do Imóvel
				</div>
			</p>
		</section>
		<footer class="col-md-12">
			<div class="col-md-6 col-sm-6 text-center side_footer">
				<div class="col-md-12 text-center content">Ass. do Corretor</div>
			</div>
			<div class="col-md-6 col-sm-6 text-center side_footer">
				<div class="col-md-12 text-center content">Ass. do Cliente</div>
			</div>
		</footer>
	</div>
</body>
</html>