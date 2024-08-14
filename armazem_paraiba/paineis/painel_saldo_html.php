<style>
	#tabela1 {
		min-width:30%;
		text-align: center;
	}

	#tabela2 {
		min-width: 30%;
		text-align: center;
	}

	.emissao {
		margin-bottom: 20px;
		width: -webkit-fill-available;
		align-content: center;
		text-align: center;
	}

	.totais {
		background-color: #ffe699;
	}

	.titulos {
		background-color: #99ccff;
	}

	#tituloRelatorio {
		display: none;
	}

	th {
		cursor: pointer;
	}

	th.sort-asc::after {
		content: " \2191";
	}

	th.sort-desc::after {
		content: " \2193";
	}
	#impressao{
        display: none;
    }

	@media print {
		body {
			margin: 1cm;
			margin-right: 0cm;
			/* Ajuste o valor conforme necessário para afastar do lado direito */
			transform: scale(1.0);
			transform-origin: top left;
		}

		@page {
			size: A4 landscape;
			margin: 1cm;
		}

		#tituloRelatorio {
			/*font-size: 2px !important;*/
			/*padding-left: 200px;*/
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: -50px !important;
		}

		div:nth-child(6)>div.portlet.light,
		.scroll-to-top  {
			display: none !important;
		}

		#pdf2htmldiv>div {
			padding: 88px 20px 15px !important;
		}

		/* .portlet.light>.portlet-title {
			border-bottom: none;
			margin-bottom: 0px;
		} */

		.caption {
			padding-top: 0px;
			margin-left: -50px !important;
			padding-bottom: 0px;
		}

		.emissao {
			padding-left: 680px !important;
		}

		.porcentagemEndo {
			box-shadow: 0 0 0 1000px #66b3ff inset !important;
		}

		.porcentagemNaEndo {
			box-shadow: 0 0 0 1000px #ff471a inset !important;
		}

		.porcentagemEndoPc {
			box-shadow: 0 0 0 1000px #ffff66 inset !important;
		}

		thead tr.totais th {
			box-shadow: 0 0 0 1000px #ffe699 inset !important;
			/* Cor para impressão */
		}

		thead tr.titulos th {
			box-shadow: 0 0 0 1000px #99ccff inset !important;
			/* Cor para impressão */
		}

		.porcentagemMeta {
			box-shadow: 0 0 0 1000px #66b3ff inset !important;
		}

		.porcentagemPosit {
			box-shadow: 0 0 0 1000px #00b33c inset !important;
		}

		.porcentagemNegat {
			box-shadow: 0 0 0 1000px #ff471a inset !important;
		}

		.portlet.light {
			padding: 75px 20px 15px !important;
		}

		#impressao {
			display: block !important;
			position: relative;
			padding-left: 630px!important;
		}
	}

	table thead tr th:nth-child(3),
	table thead tr th:nth-child(7),
	table thead tr th:nth-child(11),
	table td:nth-child(3),
	table td:nth-child(7),
	table td:nth-child(11) {
		border-right: 3px solid #d8e4ef !important;
	}

	.th-align {
		text-align: center;
		/* Define o alinhamento horizontal desejado, pode ser center, left ou right */
		vertical-align: middle !important;
		/* Define o alinhamento vertical desejado, pode ser top, middle ou bottom */

	}

	.emissao {

	}
</style>
<div id="tituloRelatorio">
	<img style='width: 150px' src="<?=$aEmpresa[0]["empr_tx_logo"]?>" alt="Logo Empresa Esquerda">
	<h3>Relatorio Geral de saldo</h3>
	<div class="right-logo">
		<img style='width: 150px' src="<?=$CONTEX["path"]?>/imagens/logo_topo_cliente.png" alt="Logo Empresa Direita">
	</div>
</div>
<div class="col-md-12 col-sm-12" id="pdf2htmldiv">
	<div class="portlet light ">
		<div class="table-responsive">
			<div style="display: flex;">
				<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact" id="tabela1">
					<thead>
						<tr>
							<th colspan="1">SALDO FINAL</th>
							<th colspan="1">QUANT</th>
							<th colspan="1">%</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td class="porcentagemMeta" style="background-color: #66b3ff;">META</td>
							<td class="textCentralizado"><?=$quantMeta?></td>
							<td><?=$performance["meta_endo"]?></td>
						</tr>
						<tr>
							<td class='porcentagemPosit' style="background-color: #00b33c;">POSITIVO</td>
							<td class="textCentralizado"><?=$quantPosi?></td>
							<td><?=$performance["posi_endoPc"]?></td>
						</tr>
						<tr>
							<td class='porcentagemNegat' style="background-color: #ff471a;">NEGATIVO</td>
							<td class="textCentralizado"><?=$quantNega?></td>
							<td><?=$performance["nega_naEndo"]?></td>
						</tr>
					</tbody>
				</table>
				<div class="emissao">
					<?=(!empty($emissao)? "<b>Atualizado em:</b> ".$emissao."<br>": "")
					."<b>Período do relatório:</b> ".$dataInicioFormatada." a ".$dataFimFormatada?></div>
			</div>
			<br>
			<div class="portlet-body form">
				<table id="tabela-empresas" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact">
					<thead>
						<tr class="totais">
							<th colspan='2'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
						</tr>
						<tr id='titulos' class="titulos">
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
						</tr>
					</thead>
					<tbody>
						<!-- Conteúdo do json empresas será inserido aqui -->
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
	<div id="impressao">
		<b>Impressão Doc.:</b> <?=date("d/m/Y \T H:i:s")." (UTC-3)"?>
	</div>
	<script>
		$(document).ready(function() {
			var tabela = $('#tabela-empresas tbody');

			<?php
			if (
				isset($_POST['empresa']) && !empty($_POST['empresa']) && isset($_POST['busca_dataInicio']) && !empty($_POST['busca_dataInicio'])
				&& isset($_POST['busca_dataFim']) && !empty($_POST['busca_dataFim'])
			) {
				$url = "./arquivos/paineis/Saldo/$idEmpresa/$mes-$ano/motoristas.json";
			} else {
				$url = "arquivos/paineis/Saldo/empresas/$mes-$ano/totalEmpresas.json";
			}
			?>

			function carregarDados() {
				$.ajax({
					url: '<?=$url?>',
					dataType: 'json',
					success: function(data) {
						tabela.empty();
						$.each(data, function(index, item) {
							//console.log("Item " + index + ":");
							for (var chave in item) {
								if (item.hasOwnProperty(chave)) {
									//console.log(chave + ": " + item[chave]);
								}
							}

							console.log(item);

							<?php
								$linha = "'<tr>'";
								if (!empty($_POST['empresa']) && !empty($_POST['busca_dataInicio']) && !empty($_POST['busca_dataFim'])){
									$linha .= 
										"+'<td>'+item.matricula+'</td>'"
										."+'<td>'+item.motorista+'</td>'";
								} else {
									$linha .= 
										"+'<td style=\"cursor: pointer;\" onclick=setAndSubmit('+item.empresaId+')>'+item.empresaNome+'</td>'"
										."+'<td>'+item.totalMotorista+'</td>'"
									;
								}
								$linha .= 
									"+'<td>'+item.jornadaPrevista+'</td>'"
									."+'<td>'+item.jornadaEfetiva+'</td>'"
									."+'<td>'+((item.he50 === null || item.he50 === '00:00')? '': item.he50)+'</td>'"
									."+'<td>'+((item.he100 === null || item.he100 === '00:00')? '': item.he100)+'</td>'"
									."+'<td>'+((item.adicionalNoturno === null || item.adicionalNoturno === '00:00')? '': item.adicionalNoturno)+'</td>'"
									."+'<td>'+((item.esperaIndenizada === null || item.esperaIndenizada === '00:00')? '': item.esperaIndenizada)+'</td>'"
									."+'<td>'+((item.saldoAnterior === null || item.saldoAnterior === '00:00')? '': item.saldoAnterior)+'</td>'"
									."+'<td>'+((item.saldoPeriodo === null || item.saldoPeriodo === '00:00')? '': item.saldoPeriodo)+'</td>'"
									."+'<td>'+((item.saldoFinal === null || item.saldoFinal === '00:00')? '': item.saldoFinal)+'</td>'";
								
								$linha .= "+'</tr>'";
								echo "var linha = ".$linha.";";
							?>
							tabela.append(linha);
						});
					},
					error: function() {
						console.log('Erro ao carregar os dados.');
					}
				});
			}
			// Função para ordenar a tabela
			function ordenarTabela(coluna, ordem) {
				var linhas = tabela.find('tr').get();
				linhas.sort(function(a, b) {
					var valorA = $(a).children('td').eq(coluna).text().toUpperCase();
					var valorB = $(b).children('td').eq(coluna).text().toUpperCase();

					if (valorA < valorB) {
						return ordem === 'asc'? -1: 1;
					}
					if (valorA > valorB) {
						return ordem === 'asc'? 1: -1;
					}
					return 0;
				});
				$.each(linhas, function(index, row) {
					tabela.append(row);
				});
			}

			// Evento de clique para ordenar a tabela ao clicar no cabeçalho
			$('#titulos th').click(function() {
				var coluna = $(this).index();
				var ordem = $(this).data('order');
				$('#tabela-empresas th').data('order', 'desc'); // Redefinir ordem de todas as colunas
				$(this).data('order', ordem === 'desc'? 'asc': 'desc');
				ordenarTabela(coluna, $(this).data('order'));

				// Ajustar classes para setas de ordenação
				$('#titulos th').removeClass('sort-asc sort-desc');
				$(this).addClass($(this).data('order') === 'asc'? 'sort-asc': 'sort-desc');
			});

			carregarDados();
		});

		// function downloadCSV() {
		//     // Caminho do arquivo CSV no servidor
		//     var filePath = './arquivos/paineis/Painel_Geral.csv' // Substitua pelo caminho do seu arquivo

		//     // Cria um link para download
		//     var link = document.createElement('a');

		//     // Configurações do link
		//     link.setAttribute('href', filePath);
		//     link.setAttribute('download', 'Painel_Geral.csv');

		//     // Adiciona o link ao documento
		//     document.body.appendChild(link);

		//     // Simula um clique no link para iniciar o download
		//     link.click();

		//     // Remove o link
		//     document.body.removeChild(link);
		// }
	</script>