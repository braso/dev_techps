<link rel="stylesheet" href="../css/paineis.css">
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/solid-gauge.js"></script>
<div id="printTitulo">
	<img style="width: 150px" src="<?= $logoEmpresa ?>" alt="Logo Empresa Esquerda">
	<h3><?= $titulo ?></h3>
	<div class="right-logo">
		<img style="width: 150px" src="<?= $_ENV["APP_PATH"] . $_ENV["CONTEX_PATH"] ?>/imagens/logo_topo_cliente.png" alt="Logo Empresa Direita">
	</div>
</div>
<div class="col-md-12 col-sm-12" id="pdf2htmldiv">
	<div class="portlet light">
		<div class="table-responsive">
			<div class='emissao' style="display: block !important;">
				<h2 class="titulo2"><?= $titulo ?></h2>
				<span></span>
				<?= $dataEmissao ?>
				<br>
				<?php
				if (!empty($periodoRelatorio["dataInicio"])) {
					echo "<br> <b>Período do relatório:</b> " . $periodoRelatorio["dataInicio"] . " a " . $periodoRelatorio["dataFim"];
				}
				?>
				<br>
				<?php if (!empty($empresa["empr_tx_nome"])) { ?>
					<span><b>Empresa:</b> <?= $empresa["empr_tx_nome"] ?></span>
					<br>
					<br>
				<?php } ?>
				<span class="total-sem-jornada"></span>
				<span class="total-jornada"> </span>
				<?= $quantFun ?>
				<?= $tabelaMotivo ?>

			</div>
			<?php if ($quantFun) { ?>
				<span style="font-size: 8px;">Marcações com <b>(*)</b> indicam intervalos em aberto</span>
				<span style="margin-left: 19px; font-size: 8px;"><i id="iconLegenda" class="fa fa-circle" aria-hidden="true" style="line-height: 7px !important; color: yellow; border: 1px solid black; border-radius: 50%;"></i> A cor Indica que o tempo total de jornada excedeu o previsto.</span>
				<br>
				<span style="font-size: 8px;">Marcações com <b>(----)</b> indicam que não possui intervalos </span>
				<span style="margin-left: 10px; font-size: 8px;"><i id="iconLegenda1" class="fa fa-circle" aria-hidden="true" style="line-height: 7px !important; color: red;"></i> A cor Indica que o limite máximo de horas extras permitido foi ultrapassado.  </span>
			<?php } ?>
		</div>
		<div class="portlet-body form" style="display: flex; flex-direction: column;">
			<?= $rowGravidade ?>
			<?php if ($mostra === true) { ?>
				<div class="panel-group group1" id="accordion">
					<!-- Accordion Item -->
					<div class="panel panel-default">
						<div class="panel-heading">
							<h3 class="panel-title">
								<a data-toggle="collapse" href="#collapse2" aria-expanded="false" aria-controls="collapse2" class="collapsed">
									<b>
										Legendas
									</b>
								</a>
							</h3>
						</div>
						<div id="collapse2" class="panel-collapse collapse">
							<div class="panel-body">

								<div class="portlet-body form">
									<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
										<thead>
											<tr>
												<td></td>
												<td style="text-align: center;">Descrição</td>
												<td>Total</td>
												<td>%</td>
											</tr>
										</thead>
										<tbody>
											<?php if ($_POST["busca_endossado"] == "naoEndossado") { ?>
												<tr>
													<td class="tituloBaixaGravidade2">Espera</td>
													<td class="baixaGravidade">"Inicio ou Fim de espera sem registro"</td>
													<td class="total"><?= $totalizadores["espera"] ?></td>
													<td class="total"><?= $percentuais["Geral_espera"] ?>%</td>
												</tr>
												<tr>
													<td class="tituloBaixaGravidade2">Descanso</td>
													<td class="baixaGravidade">"Inicio ou Fim de descanso sem registro"</td>
													<td class="total"><?= $totalizadores["descanso"] ?></td>
													<td class="total"><?= $percentuais["Geral_descanso"] ?>%</td>
												</tr>
												<tr>
													<td class="tituloBaixaGravidade2">Repouso</td>
													<td class="baixaGravidade">"Inicio ou Fim de repouso sem registro"</td>
													<td class="total"><?= $totalizadores["repouso"] ?></td>
													<td class="total"><?= $percentuais["Geral_repouso"] ?>%</td>
												</tr>
												<tr>
													<td class="tituloBaixaGravidade2">Jornada</td>
													<td class="baixaGravidade">"Inicio ou Fim de Jornada sem registro"</td>
													<td class="total"><?= $totalizadores["jornada"] ?></td>
													<td class="total"><?= $percentuais["Geral_jornada"] ?>%</td>
												</tr>
											<?php } ?>
											<tr>
												<td class="tituloBaixaGravidade2">Jornada Prevista</td>
												<td class="baixaGravidade">"Faltas não justificadas"</td>
												<td class="total"><?= $totalizadores["falta"] ?></td>
												<td class="total"><?= $percentuais["Geral_falta"] ?>%</td>
											</tr>
											<tr>
												<td class="tituloMediaGravidade2">Jornada Efetiva</td>
												<td class="mediaGravidade">"Tempo excedido de 12:00h de jornada efetiva"</td>
												<td class="total"><?= $totalizadores["jornadaEfetiva"] ?></td>
												<td class="total"><?= $percentuais["Geral_jornadaEfetiva"] ?>%</td>
											</tr>
											<tr>
												<td class="tituloMediaGravidade2">MDC - Máximo de Direção Continua</td>
												<td class="mediaGravidade">"Descanso de 30 minutos a cada 05:30 de direção não respeitado."</td>
												<td class="total"><?= $totalizadores["mdc"] ?></td>
												<td class="total"><?= $percentuais["Geral_mdc"] ?>%</td>
											</tr>
											<tr>
												<td class="tituloAltaGravidade2">Refeição</td>
												<td class="altaGravidade">"Batida de início ou fim de refeição não registrada" ou "Refeição ininterrupta maior que 1 hora não respeitada" ou "Tempo máximo de 2 horas para a refeição não respeitado"</td>
												<td class="total"><?= $totalizadores["refeicao"] ?></td>
												<td class="total"><?= $percentuais["Geral_refeicao"] ?>%</td>
											</tr>
											<tr>
												<td class="tituloAltaGravidade2">Interstício Inferior</td>
												<td class="altaGravidade">"O mínimo de 11 horas de interstício não foi respeitado"</td>
												<td class="total"><?= $totalizadores["intersticioInferior"] ?></td>
												<td class="total"><?= $percentuais["Geral_intersticioInferior"] ?>%</td>
											</tr>
											<tr>
												<td class="tituloAltaGravidade2">Interstício Superior</td>
												<td class="altaGravidade">"Interstício total de 11 horas não respeitado"</td>
												<td class="total"><?= $totalizadores["intersticioSuperior"] ?></td>
												<td class="total"><?= $percentuais["Geral_intersticioSuperior"] ?>%</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="panel-group group2" id="accordion2">
					<!-- Accordion Item -->
					<div class="panel panel-default">
						<div class="panel-heading">
							<h3 class="panel-title">
								<a
									data-toggle="collapse"
									href="#collapse1"
									aria-expanded="false"
									aria-controls="collapse1"
									class="collapsed">
									<b>
										Tabela detalhada de não conformidade
									</b>
								</a>
							</h3>
						</div>
						<div id="collapse1" class="panel-collapse collapse">
							<div class="panel-body">
								<table id="tabela-empresas" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
									<thead>
										<?= $rowTotais ?>
										<?= $rowTitulos ?>
									</thead>
									<tbody>
										<!-- Conteúdo do json empresas será inserido aqui -->
									</tbody>
									<thead>
										<?= $rowTotal ?>
									</thead>
								</table>
							</div>
						</div>
					</div>
				</div>
			<?php } ?>
			<?php if ($mostra === false || empty($mostra)) { ?>
				<div class="table-responsive">
					<table id="tabela-empresas" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
						<thead>
							<?= $rowTotais ?>
							<?= $rowTitulos ?>
							<?= $rowTitulos2 ?>
						</thead>
						<tbody>
							<!-- Conteúdo do json empresas será inserido aqui -->
						</tbody>
						<thead>
							<?= $rowTotal ?>
						</thead>
					</table>
				</div>
			<?php } ?>
			<?php if ($painelDisp === true) {?>
				<div>
				<h4><b>Em jornada</b></h4>
				<div class="table-responsive">
					<table id="tabela-emJornada" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
						<thead>
							<?= $rowTitulos3 ?>
						</thead>
						<tbody>
							<!-- Conteúdo do json empresas será inserido aqui -->
						</tbody>
						<thead>
							<?= $rowTotal ?>
						</thead>
					</table>
				</div>
				</div>
			<?php } ?>

		<?php if ($mostra === true) { ?>
				<div class="panel-group group3" id="accordion3">
					<!-- Accordion Item -->
					<div class="panel panel-default">
						<div class="panel-heading">
							<h3 class="panel-title">
								<a
									data-toggle="collapse"
									href="#collapse3"
									aria-expanded="false"
									aria-controls="collapse3"
									class="collapsed">
									<b>
										Gráfico Detalhado de Não Conformidades
									</b>
								</a>
							</h3>
						</div>
						<div id="collapse3" class="panel-collapse collapse">
							<div class="panel-body">
								<div id='graficoDetalhado' style='width:100%; height:850px; background-color: lightblue;'>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
				<?php } ?>

	<div id="impressao">
		<b>Impressão Doc.:</b> <?= date("d/m/Y \T H:i:s") . " (UTC-3)" ?>
	</div>
</div>

<script>
	function sanitizeJson(jsonString) {
		// Verifica se o JSON é uma string, se não for, converte para string
		if (typeof jsonString !== 'string') {
			jsonString = JSON.stringify(jsonString);
		}

		// Escapa as aspas simples (caso haja)
		jsonString = jsonString.replace(/'/g, '\\"');

		return jsonString;
	}
</script>
<?php if ($mostra === true) { ?>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Gráfico sintético
			const categorias = ['Alta', 'Media', 'Baixa'];
			const valores = <?= json_encode($graficoSintetico) ?>;
			const cores = ['#a30000', '#FF8B00', '#FFE800'];

			const dataFormatada = categorias.map((categoria, index) => ({
				name: categoria,
				y: valores[index],
				color: cores[index]
			}));

			Highcharts.chart('graficoSintetico', {
				chart: {
					type: 'pie',
					height: '80%'  
				},
				title: {
					text: 'Gráfico Sintético de Não Conformidades'
				},
				tooltip: {
					pointFormat: '<b>{point.name}</b>: {point.y} ({point.percentage:.2f}%)',
					style: {
						fontSize: '16px'
					}
				},
				plotOptions: {
					pie: {
						dataLabels: {
							enabled: true,
							style: {
								fontSize: '16px'
							},
							distance: 65
						},
						showInLegend: false,
						minSize: 5
					}
				},
				series: [{
					name: 'Valores',
					data: dataFormatada
				}]
			});

			// Gráfico analítico
			const categoriasAnalitico = <?= json_encode($arrayTitulos) ?>;
			const valoresAnalitico = <?= json_encode($graficoAnalitico) ?>;
			const coresAnalitico = <?= json_encode($coresGrafico) ?>;

			const dataFormatadaAnalitico = categoriasAnalitico.map((categoria2, index) => ({
				name: categoria2,
				y: valoresAnalitico[index],
				color: coresAnalitico[index]
			}));

			Highcharts.chart('graficoAnalitico', {
				chart: {
					type: 'pie',
					height: '65%' 
				},
				title: {
					text: 'Gráfico Analítico de Não Conformidades'
				},
				tooltip: {
					pointFormat: '<b>{point.name}</b>: {point.y} ({point.percentage:.2f}%)',
					style: {
						fontSize: '16px'
					}
				},
				plotOptions: {
					pie: {
						dataLabels: {
							enabled: true,
							style: {
								fontSize: '13px'
							},
							distance: 65
						},
						showInLegend: false,
						minSize: 5
					}
				},
				series: [{
					name: 'Valores',
					data: dataFormatadaAnalitico.filter(function(point) {
						return point.y > 0; // Filtra valores onde 'y' é maior que zero
					})
				}]
			});

			const categoriasDetalhado = <?= json_encode($arrayTitulos2) ?>;
			const valoresDetalhado = <?= json_encode($graficoDetalhado) ?>;
			const coresDetalhado = <?= json_encode($coresGrafico2) ?>;

			// Calcula o total para obter as porcentagens
			// const totalDetalhado = valoresDetalhado.reduce((acc, val) => acc + val, 0);
			const totalDetalhado = <?= $totalGeral + $totalizadores["faltaJustificada"] ?>;

			// Formata os dados para o gráfico de barras em porcentagem
			const dataFormatadaDetalhado = categoriasDetalhado.map((categoria3, index) => ({
				name: categoria3,
				y: totalDetalhado > 0 ? (valoresDetalhado[index] / totalDetalhado) * 100 : 0, // Valor em porcentagem
				valor: valoresDetalhado[index], // Valor absoluto
				color: coresDetalhado[index]
			}));

			Highcharts.chart('graficoDetalhado', {
				chart: {
					type: 'bar', // Altere o tipo do gráfico para 'bar'
					backgroundColor: '#f9f9f9'

				},
				title: {
					text: 'Gráfico Detalhado de Não Conformidades',
					style: {
						fontSize: '20px', // Aumenta o tamanho do título
						// color: '#ffffff'
					}
				},
				xAxis: {
					categories: categoriasDetalhado,
					title: {
						text: 'Não Conformidades Jurídicas',
						style: {
							fontSize: '16px', // Aumenta o tamanho da fonte do título do eixo X
							// color: '#ffffff'
						}
					},
					labels: {
						style: {
							fontSize: '14px' // Aumenta o tamanho da fonte dos rótulos do eixo X
						},
						formatter: function() {
							var color = coresDetalhado[this.pos] || '#000';
							var coresComBordaBranca = ['#ff0000', '#00ff00'];

							var textShadow = ""; // `text-shadow:
							// -1px -1px 1px rgba(0, 0, 0, 0.5), 
							// 1px -1px 1px rgba(0, 0, 0, 0.5), 
							// -1px 1px 1px rgba(0, 0, 0, 0.5),  
							// 1px 1px 1px rgba(0, 0, 0, 0.5);`;
							236, 65, 65

							return `<span style="border-bottom: 1px solid ${color}; ${textShadow}"><b>${this.value}</b></span>`;
						}
					}
				},
				yAxis: {
					min: 0,
					max: 100, // Limita o eixo Y a 100%
					title: {
						text: 'Porcentagem',
						style: {
							fontSize: '16px',
							// color: '#ffffff'
						}
					},
					labels: {
						format: '{value}%', // Exibe as labels do eixo Y como porcentagem
						style: {
							fontSize: '11px', // Aumenta o tamanho da fonte dos rótulos do eixo Y
							// color: '#ffffff'
						}
					},
					tickInterval: 2, // Ajusta o intervalo entre os ticks (linhas de grid)
					gridLineWidth: 0 // Reduz a largura das linhas de grid para torná-las mais finas

				},
				tooltip: {
					// Exibe a quantidade e a porcentagem no tooltip
					pointFormatter: function() {
						return `<b>${this.y.toFixed(2)}%</b> (${this.valor} Não Conformidades)`;
					},
					style: {
						fontSize: '16px' // Aumenta o tamanho da fonte do tooltip
					}
				},
				plotOptions: {
					bar: { // Altere 'column' para 'bar' aqui também
						dataLabels: {
							enabled: true,
							format: '{point.y:.2f}%', // Exibe o valor em porcentagem com duas casas decimais
							style: {
								fontSize: '14px' // Aumenta o tamanho da fonte das labels de dados
							}
						}
					}
				},
				series: [{
					name: 'Valores',
					data: dataFormatadaDetalhado
				}]

			});

			var tabelaMotorista = $('#tabela-motorista tbody');
			var tabelaMotoristaTotal = $('#tabela-motorista thead tr');
			let motoristas = <?= $motoristas ?? 0 ?>;
			let ajudante = <?= $ajudante ?? 0 ?>;
			let funcionario = <?= $funcionario ?? 0 ?>;

			const totalMotorista = motoristas + ajudante + funcionario;

			let linhaMotorista = '';
			let linhaMotorista2 = '';
			let totalPorcentagem = 0; // Para somar as porcentagens

			if (motoristas && motoristas > 0) {
				const percMotoristas = ((motoristas / totalMotorista) * 100).toFixed(2);
				totalPorcentagem += parseFloat(percMotoristas);
				linhaMotorista += '<tr><td>Motorista</td><td>' + motoristas + '</td>';
				linhaMotorista += '<td>' + percMotoristas + '%</td></tr>';
			}
			if (ajudante && ajudante > 0) {
				const percAjudante = ((ajudante / totalMotorista) * 100).toFixed(2);
				totalPorcentagem += parseFloat(percAjudante);
				linhaMotorista += '<tr><td>Ajudante</td><td>' + ajudante + '</td>';
				linhaMotorista += '<td>' + percAjudante + '%</td></tr>';
			}
			if (funcionario && funcionario > 0) {
				const percFuncionario = ((funcionario / totalMotorista) * 100).toFixed(2);
				totalPorcentagem += parseFloat(percFuncionario);
				linhaMotorista += '<tr><td>Funcionário</td><td>' + funcionario + '</td>';
				linhaMotorista += '<td>' + percFuncionario + '%</td></tr>';
			}
			linhaMotorista2 += '<td>' + totalMotorista + '</td>';
			linhaMotorista2 += '<td>' + totalPorcentagem.toFixed(2) + '%</td>';
			tabelaMotorista.append(linhaMotorista);
			tabelaMotoristaTotal.append(linhaMotorista2);
		});


		Highcharts.chart('graficoPerformance', {
			chart: {
				type: 'gauge',
				plotBackgroundColor: null,
				plotBackgroundImage: null,
				plotBorderWidth: 0,
				plotShadow: false,
				height: '80%'
			},
			title: {
				useHTML: true, // Permite adicionar HTML ao título
				text: `
					<div style="display: inline-block;">
						Performance Alta 
						<span class="popup-title-icon" id="popup-icon">&#9432;</span>
					</div>
				`
			},
			tooltip: {
				// Customizando o conteúdo do tooltip
				formatter: function() {
					var quantidadeDeItens = <?= $totalJsonComTudoZero ?>; // Substitua com o valor real ou uma variável
					// Exibe o valor e a quantidade de itens
					return this.series.name + ': ' + this.y + '%<br>Quantidade de funcionários sem não conformidade: ' + quantidadeDeItens;
				},
				style: {
					fontSize: '14px', // Aumenta o tamanho da fonte para 18px
					fontWeight: 'bold', // Deixa a fonte em negrito
					color: '#333333' // Cor do texto do tooltip
				}
			},
			pane: {
				startAngle: -90,
				endAngle: 90,
				background: null,
				center: ['50%', '75%'],
				size: '130%'
			},
			yAxis: {
				min: 0,
				max: 100,
				tickPixelInterval: 60,
				tickPosition: 'inside',
				tickColor: '#000000',
				// tickColor: Highcharts.defaultOptions.chart.backgroundColor || '#FFFFFF',
				tickLength: 15,
				tickWidth: 1,
				minorTickInterval: 5, // Adiciona ticks menores a cada 5 unidades
				minorTickColor: '#555555', // Cor dos ticks menores
				minorTickLength: 10,
				minorTickWidth: 1,
				labels: {
					distance: 20,
					style: {
						fontSize: '14px'
					}
				},
				lineWidth: 0,
				plotBands: [{
						from: 75,
						to: 100,
						color: '#55BF3B',
						thickness: 20
					}, // Verde 
					{
						from: 50,
						to: 75,
						color: '#FFE800',
						thickness: 20
					}, // Amarelo 
					{
						from: 25,
						to: 50,
						color: '#FF8B00',
						thickness: 20
					},
					{
						from: 0,
						to: 25,
						color: '#DF5353',
						thickness: 20
					} // Vermelho
				]
			},
			series: [{
				name: 'Performance',
				data: [<?= round($porcentagemFun, 2) ?>], // Agora o valor está dentro do intervalo de 0 a 100
				dataLabels: {
					format: '{y} %',
					borderWidth: 0,
					color: '#333333',
					style: {
						fontSize: '16px'
					}
				},
				dial: {
					radius: '80%',
					backgroundColor: 'gray',
					baseWidth: 12,
					baseLength: '0%',
					rearLength: '0%'
				},
				pivot: {
					backgroundColor: 'gray',
					radius: 6
				}
			}],
			events: {
				load: function () {
					// Registra evento de clique no ícone do popup
					$(document).on('click', '#popup-icon', function () {
						const popup = $('#popup-baixa');
						popup.toggle(); // Abre ou fecha o popup
					});

					// Evento para fechar o popup
					$(document).on('click', '.popup-close', function () {
						$('#popup-baixa').hide();
					});
				}
			}
		});

		Highcharts.chart('graficoPerformanceMedia', {
			chart: {
				type: 'gauge',
				plotBackgroundColor: null,
				plotBackgroundImage: null,
				plotBorderWidth: 0,
				plotShadow: false,
				height: '80%'
			},
			title: {
				useHTML: true, // Permite adicionar HTML ao título
				text: `
					<div style="display: inline-block;">
						Performance Média 
						<span class="popup-title-icon" id="popup-icon3">&#9432;</span>
					</div>
				`
			},
			tooltip: {
				// Customizando o conteúdo do tooltip
				formatter: function() {
					var quantidadeDeItens = <?= $totalFun  ?>; // Substitua com o valor real ou uma variável
					var perfomaceTotal= <?= number_format($mediaPerfTotal, 2, '.', '') ?>; 
					// Exibe o valor e a quantidade de itens
					return this.series.name + ': ' + this.point.y + '%<br>Quantidade de funcionários com não conformidade: ' + quantidadeDeItens;
				},
				style: {
					fontSize: '14px', // Aumenta o tamanho da fonte para 18px
					fontWeight: 'bold', // Deixa a fonte em negrito
					color: '#333333' // Cor do texto do tooltip
				}
			},
			pane: {
				startAngle: -90,
				endAngle: 90,
				background: null,
				center: ['50%', '75%'],
				size: '130%'
			},
			yAxis: {
				min: 0,
				max: 100,
				tickPixelInterval: 60,
				tickPosition: 'inside',
				tickColor: '#000000',
				// tickColor: Highcharts.defaultOptions.chart.backgroundColor || '#FFFFFF',
				tickLength: 15,
				tickWidth: 1,
				minorTickInterval: 5, // Adiciona ticks menores a cada 5 unidades
				minorTickColor: '#555555', // Cor dos ticks menores
				minorTickLength: 10,
				minorTickWidth: 1,
				labels: {
					distance: 20,
					style: {
						fontSize: '14px'
					}
				},
				lineWidth: 0,
				plotBands: [{
						from: 75,
						to: 100,
						color: '#55BF3B',
						thickness: 20
					}, // Verde 
					{
						from: 50,
						to: 75,
						color: '#FFE800',
						thickness: 20
					}, // Amarelo 
					{
						from: 25,
						to: 50,
						color: '#FF8B00',
						thickness: 20
					},
					{
						from: 0,
						to: 25,
						color: '#DF5353',
						thickness: 20
					} // Vermelho
				]
			},
			series: [{
				name: 'Performance',
				data: [<?= number_format($porcentagemTotalMedia, 2, '.', '') ?>], // Agora o valor está dentro do intervalo de 0 a 100
				dataLabels: {
					format: '{y} %',
					borderWidth: 0,
					color: '#333333',
					style: {
						fontSize: '16px'
					}
				},
				dial: {
					radius: '80%',
					backgroundColor: 'gray',
					baseWidth: 12,
					baseLength: '0%',
					rearLength: '0%'
				},
				pivot: {
					backgroundColor: 'gray',
					radius: 6
				}
			}]
		});

		Highcharts.chart('graficoPerformanceBaixa', {
			chart: {
				type: 'gauge',
				plotBackgroundColor: null,
				plotBackgroundImage: null,
				plotBorderWidth: 0,
				plotShadow: false,
				height: '80%'
			},
			title: {
				useHTML: true, // Permite adicionar HTML ao título
				text: `
					<div style="display: inline-block;">
						Performance Baixa 
						<span class="popup-title-icon" id="popup-icon2">&#9432;</span>
					</div>
				`
			},
			tooltip: {
				// Customizando o conteúdo do tooltip
				formatter: function() {
					var quantidadeDeItens = <?= $totalFun  ?>; // Substitua com o valor real ou uma variável
					var perfomaceTotal= <?= number_format($porcentagemTotalBaixa, 2, '.', '') ?>; 
					// Exibe o valor e a quantidade de itens
					return this.series.name + ': ' + this.point.y + '%<br>Quantidade de funcionários com não conformidade: ' + quantidadeDeItens;
				},
				style: {
					fontSize: '14px', // Aumenta o tamanho da fonte para 18px
					fontWeight: 'bold', // Deixa a fonte em negrito
					color: '#333333' // Cor do texto do tooltip
				}
			},
			pane: {
				startAngle: -90,
				endAngle: 90,
				background: null,
				center: ['50%', '75%'],
				size: '130%'
			},
			yAxis: {
				min: 0,
				max: 100,
				tickPixelInterval: 60,
				tickPosition: 'inside',
				tickColor: '#000000',
				// tickColor: Highcharts.defaultOptions.chart.backgroundColor || '#FFFFFF',
				tickLength: 15,
				tickWidth: 1,
				minorTickInterval: 5, // Adiciona ticks menores a cada 5 unidades
				minorTickColor: '#555555', // Cor dos ticks menores
				minorTickLength: 10,
				minorTickWidth: 1,
				labels: {
					distance: 20,
					style: {
						fontSize: '14px'
					}
				},
				lineWidth: 0,
				plotBands: [{
						from: 75,
						to: 100,
						color: '#55BF3B',
						thickness: 20
					}, // Verde 
					{
						from: 50,
						to: 75,
						color: '#FFE800',
						thickness: 20
					}, // Amarelo 
					{
						from: 25,
						to: 50,
						color: '#FF8B00',
						thickness: 20
					},
					{
						from: 0,
						to: 25,
						color: '#DF5353',
						thickness: 20
					} // Vermelho
				]
			},
			series: [{
				name: 'Performance',
				data: [<?= number_format($porcentagemTotalBaixaG, 2, '.', '') ?>], // Agora o valor está dentro do intervalo de 0 a 100
				dataLabels: {
					format: '{y} %',
					borderWidth: 0,
					color: '#333333',
					style: {
						fontSize: '16px'
					}
				},
				dial: {
					radius: '80%',
					backgroundColor: 'gray',
					baseWidth: 12,
					baseLength: '0%',
					rearLength: '0%'
				},
				pivot: {
					backgroundColor: 'gray',
					radius: 6
				}
			}]
		});

		// Registra evento de clique no ícone do popup após o gráfico ser renderizado
		$(document).on('click', '#popup-icon3', function () {
			const popup = $('#popup-media');
			popup.toggle(); // Alterna entre abrir e fechar o popup
		});

		// Evento para fechar o popup
		$(document).on('click', '.popup-close', function () {
			$('#popup-media').hide(); // Fecha o popup
		});

		// Registra evento de clique no ícone do popup após o gráfico ser renderizado
		$(document).on('click', '#popup-icon2', function () {
			const popup = $('#popup-baixa');
			popup.toggle(); // Alterna entre abrir e fechar o popup
		});

		// Evento para fechar o popup
		$(document).on('click', '.popup-close', function () {
			$('#popup-baixa').hide(); // Fecha o popup
		});

		// Registra evento de clique no ícone do popup após o gráfico ser renderizado
		$(document).on('click', '#popup-icon', function () {
			const popup = $('#popup-alta');
			popup.toggle(); // Alterna entre abrir e fechar o popup
		});

		// Evento para fechar o popup
		$(document).on('click', '.popup-close', function () {
			$('#popup-alta').hide(); // Fecha o popup
		});

	</script>
<?php } ?>