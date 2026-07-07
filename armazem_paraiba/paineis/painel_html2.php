<link rel="stylesheet" href="../css/paineis.css">
<style>
	/* Força fundo branco e texto preto nos gráficos Highcharts (evita modo escuro/herança de variáveis CSS) */
	.highcharts-background {
		fill: #ffffff !important;
	}
	.highcharts-plot-background {
		fill: #ffffff !important;
	}
	.highcharts-container {
		--highcharts-background-color: #ffffff !important;
	}
	.highcharts-title,
	.highcharts-subtitle,
	.highcharts-axis-labels,
	.highcharts-xaxis-labels,
	.highcharts-yaxis-labels,
	.highcharts-axis-labels text,
	.highcharts-xaxis-labels text,
	.highcharts-yaxis-labels text,
	.highcharts-legend-item text,
	.highcharts-data-label,
	.highcharts-data-label text,
	.highcharts-caption,
	.highcharts-label text,
	.highcharts-tooltip text {
		fill: #000000 !important;
		color: #000000 !important;
	}
</style>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/solid-gauge.js"></script>
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
<div id="printTitulo">
	<img style='width: 190px; height: 40px;' src='../imagens/logo_topo_cliente.png' alt='Logo Empresa Esquerda'>
	<h3><?= $titulo ?></h3>
	<img style='width: 180px; height: 80px;' src='../<?= $logoEmpresa ?>' alt='Logo Empresa Direita'>
</div>
<div class="col-md-12 col-sm-12" id="pdf2htmldiv">
	<div class="portlet light">
		<div class="table-responsive">
			<div class='emissao' style="display: block !important;">
				<h2 class="titulo2"><?= $titulo ?></h2>
				<span></span>
				<?= $dataEmissao ?>
				<br>
                <div id="consulta" style=" display: flex; align-items: center; justify-content: center; width: 100%; padding: 5px; margin-top: 10px;">
                    <?= isset($filtrosConsultaHtml) ? $filtrosConsultaHtml : "" ?>
                </div>
				<br>
				<?php if(!empty($periodoRelatorio["dataInicio"])) { ?>
				<?="<br> <b>Período do relatório:</b> ".(!empty($periodoRelatorio["dataInicio"])? "{$periodoRelatorio["dataInicio"]} a {$periodoRelatorio["dataFim"]}": "--/--/----")?>
				<?php } ?>
				<br>
				<?php if (!empty($empresa["empr_tx_nome"])) { ?>
					<span><b>Empresa:</b> <?= $empresa["empr_tx_nome"] ?></span>
					<br>
					<br>
				<?php } ?>
				<?= $quantFun ?>
				<?= $tabelaMotivo ?>

			</div>
			<?php if ($quantFun) { ?>
				<span style="font-size: 8px;">Marcações com <b>(*)</b> indicam intervalos em aberto</span><br>
				<span style=" font-size: 8px;"><i id="iconLegenda" class="glyphicon glyphicon-pencil" aria-hidden="true" style="line-height: 7px !important; color: yellow; border: 1px solid black; border-radius: 50%;"></i> A cor indica que o tempo total de jornada excedeu o previsto.</span>
				<br>
				<span style="font-size: 8px;"> 🔴 Marcações com bolinha vermelha e <b>(----)</b> na coluna Inicio /Fim de Escala  indicam jornada aberta sem escala prevista (batida em dia sem jornada ou  parâmetros para o dia).</span><br>
				<span style=" font-size: 8px;"><i id="iconLegenda1" class="glyphicon glyphicon-pencil" aria-hidden="true" style="line-height: 7px !important; color: red;"></i> A cor indica que o limite máximo de horas extras permitido foi ultrapassado.  </span>
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
				<div class="panel-group group1" id="accordionRanking">
					<div class="panel panel-default">
						<div class="panel-heading">
							<h3 class="panel-title">
								<a
									data-toggle="collapse"
									href="#collapseRanking"
									aria-expanded="false"
									aria-controls="collapseRanking"
									class="collapsed">
									<b>
										Rankeamento
									</b>
								</a>
							</h3>
						</div>
						<div id="collapseRanking" class="panel-collapse collapse">
							<div class="panel-body">
								<?php if (!empty($rankingCategorias) && array_sum($rankingValores) > 0) { ?>
								<div class="row">
									<div class="col-md-8">
										<div id="graficoRanking" style="width:100%; height:400px;"></div>
									</div>
									<?php if (($_POST["ranking_type"] ?? "nao") === "funcionario") { ?>
									<div class="col-md-4">
										<div id="top3-funcionarios" style="width:100%; height:auto; display:flex; flex-direction:column; align-items:center; justify-content:center; margin-top:40px;">
											<?php
												$maxTop = min(3, count($rankingCategorias));
												$medals = ["🏆", "🥈", "🥉"];
												for ($i = 0; $i < $maxTop; $i++) {
													$nome = htmlspecialchars((string)($rankingCategorias[$i] ?? ""), ENT_QUOTES, 'UTF-8');
													$valor = number_format((float)($rankingValores[$i] ?? 0), 2, '.', '');
													$foto = (!empty($rankingFotos[$i]) ? $rankingFotos[$i] : "../../contex20/img/user.png");
													$medal = $medals[$i] ?? "";
													echo "
														<div class='top3-item' style='display:flex; align-items:center; gap:12px; background:#f9f9f9; border-radius:8px; padding:8px; margin-bottom:10px; width:100%; max-width:380px;'>
															<div class='photo-wrap' style='position:relative; width:64px; height:64px;'>
																<img src='{$foto}' alt='Foto de {$nome}' style='width:100%; height:100%; object-fit:cover; border-radius:50%; border:2px solid #ddd;' />
																<div class='medal' style='position:absolute; right:-6px; bottom:-6px; font-size:24px;'>{$medal}</div>
															</div>
															<div class='info' style='flex:1;'>
																<div style='font-weight:600;'>".($i+1)."º — {$nome}</div>
																<div style='font-size:12px; color:#555;'>Performance {$valor}%</div>
															</div>
														</div>
													";
												}
											?>
										</div>
									</div>
									<?php } ?>
									<?php if (!empty($donutSubsetorLabels) && array_sum($donutSubsetorValues) > 0) { ?>
									<div class="col-md-4">
										<div id="graficoDonutSubsetor" style="width:100%; height:400px;"></div>
									</div>
									<?php } ?>
								</div>
								<div id="popup-ranking-info" class="popup" style="width:auto; max-width:min(90vw, 1100px); max-height:80vh; overflow:auto; padding:16px;">
									<button class="popup-close">Fechar</button>
									<h3>Como funciona o Rankeamento</h3>
									<span>
										O rankeamento vem das não conformidades, ordenado das menores para as maiores. O 1º lugar tem a melhor performance; os seguintes têm performances menores.
									</span>
									<br>
									<h4>Base de cálculo</h4>
									<span>
										<?php
											$tipoRank = ($_POST["ranking_type"] ?? "nao");
											if ($tipoRank === "setor") {
												echo "Performance por setor = 100 - ((Não conformidades do setor / Dias computados do setor) × 100).";
											} elseif ($tipoRank === "funcionario") {
												echo "Performance por funcionário = 100 - (média de percentuais de não conformidade do funcionário).";
											} else {
												echo "Selecione um tipo de rankeamento para ver o cálculo.";
											}
										?>
									</span>
									<br>
									<h4>Top atual</h4>
									<ol style="padding-left:16px; display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:10px; align-items:start; word-wrap:break-word;">
										<?php
											if (!empty($rankingCategorias) && !empty($rankingValores)) {
												for($i=0; $i<count($rankingCategorias); $i++){
													$pos = "º lugar";
													$cat = htmlspecialchars((string)$rankingCategorias[$i], ENT_QUOTES, 'UTF-8');
													$val = number_format((float)$rankingValores[$i], 2, '.', '');
													echo "<li><b>{$pos}</b>: {$cat} — {$val}%</li>";
												}
											}
										?>
									</ol>
								</div>
								<?php if (!empty($donutSubsetorLabels) && array_sum($donutSubsetorValues) > 0) { ?>
								<div id="popup-donut-info" class="popup" style="width:auto; max-width:min(90vw, 1100px); max-height:80vh; overflow:auto; padding:16px;">
									<button class="popup-close">Fechar</button>
									<h3>Subsetores</h3>
									<span>Distribuição por subsetor considera total de não conformidades por subsetor.</span>
									<br>
									<span>Ordenado do menor para o maior (menor NC é melhor desempenho).</span>
									<ol style="padding-left:16px; display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:10px; align-items:start; word-wrap:break-word;">
										<?php
											$subsetorPairs = [];
											for($i=0; $i<count($donutSubsetorLabels); $i++){
												$subsetorPairs[] = [
													"label" => (string)$donutSubsetorLabels[$i],
													"value" => (float)$donutSubsetorValues[$i]
												];
											}
											usort($subsetorPairs, function($a, $b){ return $a["value"] <=> $b["value"]; });
											for($i=0; $i<count($subsetorPairs); $i++){
												$pos = ($i+1)."º lugar";
												$cat = htmlspecialchars($subsetorPairs[$i]["label"], ENT_QUOTES, 'UTF-8');
												$val = number_format((float)$subsetorPairs[$i]["value"], 0, '.', '');
												echo "<li><b>{$pos}</b>: {$cat} — {$val} NC</li>";
											}
										?>
									</ol>
								</div>
								<?php } ?>
								<?php } else { ?>
									<div id="rankingSemDados" class="text-muted" style="padding:8px;">Sem dados filtrados</div>
								<?php } ?>
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
									<div class="table-responsive">
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
                                <script>
                                (function(){
                                    var table = document.querySelector('#tabela-empresas');
                                    if (!table) return;
                                    table.querySelectorAll('select, [name="empresa"], #empresa').forEach(function(s){
                                        var next = s.nextElementSibling;
                                        if (next && (next.classList.contains('select2') || next.classList.contains('select2-container'))) {
                                            next.remove();
                                        }
                                        s.remove();
                                    });
                                    table.querySelectorAll('.select2, .select2-container').forEach(function(el){
                                        el.remove();
                                    });
                                    table.querySelectorAll('.select2-hidden-accessible').forEach(function(el){
                                        el.classList.remove('select2-hidden-accessible');
                                        el.removeAttribute('tabindex');
                                        el.removeAttribute('aria-hidden');
                                    });
                                })();
                                </script>
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
						<tfoot>
							<?= $rowTotal ?>
						</tfoot>
					</table>
				</div>
			<?php } ?>
            <script>
            (function(){
                var table = document.querySelector('#tabela-empresas');
                if (!table) return;
                var rect = table.getBoundingClientRect();
                table.querySelectorAll('select').forEach(function(s){
                    var next = s.nextElementSibling;
                    if (next && (next.classList.contains('select2') || next.classList.contains('select2-container'))) {
                        next.remove();
                    }
                    s.remove();
                });
                table.querySelectorAll('.select2, .select2-container').forEach(function(el){
                    el.remove();
                });
                document.querySelectorAll('.select2-container').forEach(function(el){
                    var r = el.getBoundingClientRect();
                    if (r.right >= rect.left && r.left <= rect.right && r.bottom >= rect.top && r.top <= rect.bottom) {
                        el.remove();
                    }
                });
                table.querySelectorAll('.select2-hidden-accessible').forEach(function(el){
                    el.classList.remove('select2-hidden-accessible');
                    el.removeAttribute('tabindex');
                    el.removeAttribute('aria-hidden');
                });
            })();
            </script>

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
								<div id='graficoDetalhado' style='width:100%; height:850px;'>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
				<?php } ?>
		

	<!-- <div id="impressao">
		<b>Impressão Doc.:</b> <?= date("d/m/Y \T H:i:s")." (UTC-3)" ?>
	</div> -->
</div>
</div>
</div>

<div id="loader-overlay" style="
		display: none;
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: rgba(0, 0, 0, 0.7);
		z-index: 9999;
		justify-content: center;
		align-items: center;
		color: white;
		font-family: Arial, sans-serif;
	">
	<div style="text-align: center;">
		<div class="printer-loader" style="
			width: 80px;
			height: 80px;
			background: #fff;
			border-radius: 5px;
			position: relative;
			margin: 0 auto 15px;
			box-shadow: 0 0 10px rgba(0,0,0,0.5);
		">
			<div class="paper" style="
				width: 60px;
				height: 80px;
				background: #f1f1f1;
				position: absolute;
				top: -80px;
				left: 10px;
				animation: print 2s infinite;
				border-radius: 2px;
			"></div>
		</div>
		<p><strong>Aguarde:</strong> gerando o documento...</p>
	</div>
</div>

<style>
@keyframes print {
	0% { top: -80px; opacity: 0; }
	30% { opacity: 1; }
	100% { top: 0; opacity: 1; }
}
</style>

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

		// Função modificada para enviar o gráfico incluindo o ID
		

		// document.addEventListener('DOMContentLoaded', function() {
			// Gráfico sintético
			const categorias = ['Alta', 'Media', 'Baixa'];
			const valores = <?= json_encode($graficoSintetico) ?>;
			const cores = ['#a30000', '#FF8B00', '#FFE800'];

			const dataFormatada = categorias.map((categoria, index) => ({
				name: categoria,
				y: valores[index],
				color: cores[index]
			}));

			const graficoSintetico = Highcharts.chart('graficoSintetico', {
				chart: {
					type: 'pie',
					height: '80%',
					// events: {
					// 	load: function () {
					// 		const chart = this; // <-- o gráfico instanciado
					// 		setTimeout(() => enviarGraficoServidor(chart), 3000); // <-- passa o gráfico como parâmetro
					// 	}
					// }
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

			const graficoAnalitico = Highcharts.chart('graficoAnalitico', {
				chart: {
					type: 'pie',
					height: '65%',
					// events: {
					// 	load: function () {
					// 		const chart = this; // <-- o gráfico instanciado
					// 		setTimeout(() => enviarGraficoServidor(chart), 3000); // <-- passa o gráfico como parâmetro
					// 	}
					// }
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

			const graficoDetalhado = Highcharts.chart('graficoDetalhado', {
				chart: {
					type: 'bar',
					backgroundColor: '#f9f9f9',
					// events: {
					// 	load: function () {
					// 		const chart = this;

					// 		const panel = $('#collapse3');
					// 		if (!panel.hasClass('in')) {
					// 			console.log('🔓 Abrindo accordion...');
					// 			panel.collapse('show');
					// 		}

					// 		setTimeout(() => {
					// 			chart.reflow();

					// 			const elementId = chart.renderTo.id;
					// 			const el = document.getElementById(elementId);

					// 			if (el && el.offsetWidth > 0 && el.offsetHeight > 0) {
					// 				console.log('🎯 Elemento visível, enviando...');

					// 				enviarGraficoServidor(chart).then(() => {
					// 					console.log('✅ Imagem capturada. Fechando accordion...');
					// 					panel.collapse('hide');
					// 				}).catch((error) => {
					// 					console.error('❌ Erro ao capturar imagem:', error);
					// 				});
					// 			} else {
					// 				console.warn('⛔ Elemento ainda invisível, tente aumentar o delay');
					// 			}
					// 		}, 3000);
					// 	}
					// }
				},
				title: {
					text: 'Gráfico Detalhado de Não Conformidades',
					style: {
						fontSize: '20px'
					}
				},
				xAxis: {
					categories: categoriasDetalhado,
					title: {
						text: 'Não Conformidades Jurídicas',
						style: {
							fontSize: '16px'
						}
					},
					labels: {
						style: {
							fontSize: '14px'
						},
						formatter: function () {
							var color = coresDetalhado[this.pos] || '#000';
							var textShadow = ''; // Placeholder para sombra se quiser aplicar depois

							return `<span style="border-bottom: 1px solid ${color}; ${textShadow}"><b>${this.value}</b></span>`;
						}
					}
				},
				yAxis: {
					min: 0,
					max: 100,
					title: {
						text: 'Porcentagem',
						style: {
							fontSize: '16px'
						}
					},
					labels: {
						format: '{value}%',
						style: {
							fontSize: '11px'
						}
					},
					tickInterval: 2,
					gridLineWidth: 0
				},
				tooltip: {
					pointFormatter: function () {
						return `<b>${this.y.toFixed(2)}%</b> (${this.valor} Não Conformidades)`;
					},
					style: {
						fontSize: '16px'
					}
				},
				plotOptions: {
					bar: {
						dataLabels: {
							enabled: true,
							format: '{point.y:.2f}%',
							style: {
								fontSize: '14px'
							}
						}
					}
				},
				series: [{
					name: 'Valores',
					data: dataFormatadaDetalhado
				}]
			});

			const rankingCategorias = <?= json_encode($rankingCategorias) ?>;
			const rankingValores = <?= json_encode($rankingValores) ?>;
			function renderRanking() {
				if (!(Array.isArray(rankingCategorias) && rankingCategorias.length > 0 && document.getElementById('graficoRanking'))) return;
				Highcharts.chart('graficoRanking', {
					chart: { type: 'bar', backgroundColor: '#f9f9f9' },
					title: {
						useHTML: true,
						text: '<?= $rankingTitulo ?> <span class="popup-title-icon" id="popup-icon-ranking">&#9432;</span>',
						style: { fontSize: '20px' }
					},
					xAxis: {
						categories: rankingCategorias,
						title: { text: null },
						labels: { style: { fontSize: '12px' } }
					},
					yAxis: {
						min: 0,
						max: 100,
						title: { text: 'Porcentagem', style: { fontSize: '16px' } },
						labels: { format: '{value}%', style: { fontSize: '11px' } },
						tickInterval: 5,
						gridLineWidth: 0
					},
					tooltip: {
						pointFormatter: function () { return `<b>${this.y.toFixed(2)}%</b>`; },
						style: { fontSize: '16px' }
					},
					plotOptions: {
						bar: { dataLabels: { enabled: true, format: '{point.y:.2f}%', style: { fontSize: '14px' } } }
					},
					series: [{ name: 'Performance', data: rankingValores }]
				});
			}
			const donutLabels = <?= json_encode($donutSubsetorLabels) ?>;
			const donutValues = <?= json_encode($donutSubsetorValues) ?>;
			function renderDonut() {
				if (!(Array.isArray(donutLabels) && donutLabels.length > 0 && document.getElementById('graficoDonutSubsetor'))) return;
				const donutData = donutLabels.map(function(name, idx){
					return { name: name, y: Number(donutValues[idx] || 0) };
				});
				Highcharts.chart('graficoDonutSubsetor', {
					chart: { type: 'pie', backgroundColor: '#f9f9f9' },
					title: {
						useHTML: true,
						text: 'Distribuição por Subsetor <span class="popup-title-icon" id="popup-icon-donut">&#9432;</span>',
						style: { fontSize: '20px' }
					},
					tooltip: { pointFormat: '<b>{point.name}</b>: {point.percentage:.1f}%' },
					plotOptions: {
						pie: {
							innerSize: '55%',
							allowPointSelect: true,
							dataLabels: {
								enabled: true,
								format: '{point.name}: {point.percentage:.1f}%',
								style: { fontSize: '12px' }
							}
						}
					},
					series: [{ name: 'Não Conformidades', data: donutData }]
				});
			}
			$(function(){
				var cr = $('#collapseRanking');
				if (cr.length) {
					cr.on('shown.bs.collapse', function(){
						renderRanking();
						renderDonut();
					});
				}
				$(document).on('click', '#popup-icon-ranking', function(){
					var $pop = $('#popup-ranking-info');
					$pop.css({
						position: 'fixed',
						left: '50%',
						top: '50%',
						transform: 'translate(-50%, -50%)',
						zIndex: 2000
					}).fadeToggle(150);
				});
				$(document).on('click', '#popup-icon-donut', function(){
					var $pop = $('#popup-donut-info');
					$pop.css({
						position: 'fixed',
						left: '50%',
						top: '50%',
						transform: 'translate(-50%, -50%)',
						zIndex: 2000
					}).fadeToggle(150);
				});
				$(document).on('click', '.popup-close', function(){
					$(this).closest('.popup').fadeOut(150);
				});
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
		// });

		var contadorLoad = 0;
		const chartPerformance = Highcharts.chart('graficoPerformance', {
			chart: {
				type: 'gauge',
				plotBackgroundColor: null,
				plotBackgroundImage: null,
				plotBorderWidth: 0,
				plotShadow: false,
				height: '80%',
				// events: {
				// 		load: function () {
				// 			const chart = this; // <-- o gráfico instanciado
				// 			setTimeout(() => enviarGraficoServidor(chart), 3000); // <-- passa o gráfico como parâmetro
				// 		}
				// 	}
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

		const graficoPerformanceMedia = Highcharts.chart('graficoPerformanceMedia', {
			chart: {
				type: 'gauge',
				plotBackgroundColor: null,
				plotBackgroundImage: null,
				plotBorderWidth: 0,
				plotShadow: false,
				height: '80%',
				// events: {
				// 		load: function () {
				// 			const chart = this; // <-- o gráfico instanciado
				// 			setTimeout(() => enviarGraficoServidor(chart), 3000); // <-- passa o gráfico como parâmetro
				// 		}
				// 	}
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

		const graficoPerformanceBaixa = Highcharts.chart('graficoPerformanceBaixa', {
			chart: {
				type: 'gauge',
				plotBackgroundColor: null,
				plotBackgroundImage: null,
				plotBorderWidth: 0,
				plotShadow: false,
				height: '80%',
				// events: {
				// 		load: function () {
				// 			const chart = this; // <-- o gráfico instanciado
				// 			setTimeout(() => enviarGraficoServidor(chart), 3000); // <-- passa o gráfico como parâmetro
				// 		}
				// 	}
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

		async function enviarGraficoServidor(chart) {
			const elementId = chart?.renderTo?.id;
			if (!elementId) {
				console.error('❌ ID do elemento do gráfico não encontrado.');
				return;
			}

			const userEntrada = '<?= $_SESSION['horaEntrada'] ?? '0' ?>';
			const dataGrafc = '<?= 
    !empty($_POST["busca_dataMes"]) ? $_POST["busca_dataMes"] : 
    (!empty($_POST["busca_periodo"][0]) ? date("Y-m", strtotime($_POST["busca_periodo"][0])) : '') 
?>';

			const el = document.getElementById(elementId);
			if (!el) {
				console.error('❌ Elemento não encontrado para o ID:', elementId);
				return;
			}

			const width = el.offsetWidth;
			const height = el.offsetHeight;

			if (width === 0 || height === 0) {
				console.error("❌ Elemento não tem tamanho válido para captura");
				return;
			}

			try {
				console.log('⌛ Aguardando navegador estar ocioso...');
				await new Promise(resolve => requestIdleCallback(resolve));

				console.log('📸 Iniciando captura com html2canvas...');

				if (typeof html2canvas !== 'function') {
					throw new Error("❌ html2canvas não está carregado.");
				}

				const canvas = await html2canvas(el, {
					scale: 2,
					useCORS: true,
					allowTaint: false,
					backgroundColor: '#ffffff'
				});

				const imageData = canvas.toDataURL('image/png');

				if (!imageData.startsWith('data:image/png;base64,')) {
					throw new Error('❌ Imagem gerada inválida');
				}

				const response = await fetch('salvar_grafico_painel.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'Accept': 'application/json',
        				'X-Requested-With': 'XMLHttpRequest',
					},
					body: new URLSearchParams({
						grafico: imageData,
						elementId,
						userEntrada,
						dataGrafc
					}).toString()
				});

				const data = await response.json();

				if (data.status === 'success') {
					console.log('✅ Gráfico salvo com sucesso:', data.fileName);
				} else {
					throw new Error(data.message || 'Erro desconhecido ao salvar gráfico');
				}

			} catch (error) {
				console.error('❌ Erro ao processar o gráfico:', error);
			}
		}

		async function enviarDados() {
			const loader = document.getElementById('loader-overlay');
			let loaderFechado = false;
			let processoCompleto = false;
			let novaJanela = null;

			const fecharLoaderSeguro = () => {
				if (loaderFechado || !loader) return;
				try {
					loader.style.display = 'none';
					loaderFechado = true;
				} catch (e) {
					console.error("Erro ao fechar loader:", e);
					setTimeout(fecharLoaderSeguro, 500);
				}
			};

			const gerenciarJanela = async () => {
				const MAX_TENTATIVAS = 2;
				let tentativas = 0;

				// Função auxiliar para detectar navegador
				const getBrowserInfo = () => {
					const ua = navigator.userAgent;
					return {
						isChrome: /Chrome|CriOS/.test(ua),
						isFirefox: /Firefox|FxiOS/.test(ua),
						isSafari: /Safari/.test(ua) && !/Chrome/.test(ua),
						isEdge: /Edg/.test(ua)
					};
				};

				const getBrowserInstructions = () => {
					const { isChrome, isFirefox } = getBrowserInfo();
					
					if (isChrome) {
						return [
							"1. Clique no ícone de cadeado (🔒) à esquerda da URL",
							"2. Selecione 'Configurações do site'",
							"3. Encontre 'Pop-ups e redirecionamentos'",
							"4. Mude para 'Permitir'"
						].join('\n');
					} else if (isFirefox) {
						return [
							"1. Clique no ícone de escudo (🛡️) na barra de endereço",
							"2. Selecione 'Gerenciar Permissões'",
							"3. Ative 'Pop-ups'"
						].join('\n');
					} else {
						return [
							"1. Acesse as configurações de privacidade do navegador",
							"2. Encontre 'Configurações de conteúdo'",
							"3. Permita pop-ups para este site"
						].join('\n');
					}
				};

				const mostrarSolucaoCompleta = () => {
					const browserMsg = getBrowserInstructions();
					const mensagem = [
						"Primeiro tente:",
						"1. Recarregar (F5)",
						"2. Iniciar novamente",
						"",
						"Se continuar bloqueado:",
						browserMsg,
						"",
						"Após configurar, atualize a página"
					].join('\n');

					alert(mensagem);
					throw new Error('Pop-ups bloqueados - Siga as instruções exibidas');
				};

				while (tentativas < MAX_TENTATIVAS) {
					try {
						if (!novaJanela || novaJanela.closed) {
							if (tentativas > 0) {
								mostrarSolucaoCompleta();
							}

							novaJanela = window.open('', 'janelaExportacao', 'width=1000,height=600');
							
							if (!novaJanela) {
								tentativas++;
								continue;
							}
						}

						// Verificação de segurança
						try {
							if (novaJanela.document) return novaJanela;
						} catch (e) {
							return novaJanela; // Permite cross-origin
						}

					} catch (error) {
						tentativas++;
						if (tentativas >= MAX_TENTATIVAS) {
							throw error;
						}
					}
				}
			};

			try {
				loader.style.display = 'flex';
				
				// 1. Abrir janela
				novaJanela = window.open('', 'janelaExportacao');
				gerenciarJanela();

				// 2. Processar gráficos
				await processarGraficos();
				gerenciarJanela();

				// 3. Enviar formulário com timeout de segurança
				await Promise.race([
					enviarFormulario(novaJanela),
					new Promise((_, reject) => setTimeout(() => reject(new Error('Tempo excedido no envio do formulário')), 500))
				]);

				// 4. Verificação final
				if (novaJanela.closed) {
					throw new Error('Janela fechada durante o processo');
				}

				// 5. Tempo mínimo de exibição do loader
				await new Promise(resolve => setTimeout(resolve, 2000));
				processoCompleto = true;

			} catch (error) {
				console.error("Erro na exportação:", error);
				if (loader && !loaderFechado) {
					loader.innerHTML = `
						<div style="text-align: center; color: #ff6b6b;">
							<p>❌ ${error.message}</p>
							<button onclick="location.reload()" 
									style="margin-top: 10px; padding: 5px 10px;">
								Tentar novamente
							</button>
						</div>
					`;
				}
				throw error; // Re-lança o erro para quem chamou a função
			} finally {
				// Garantia de fechamento do loader
				fecharLoaderSeguro();
				setTimeout(fecharLoaderSeguro, 1000);
			}
		}
	
		async function processarGraficos() {
			try {
				// Processa gráficos normais em paralelo
				await Promise.all([
					enviarGraficoServidor(chartPerformance),
					enviarGraficoServidor(graficoPerformanceMedia),
					enviarGraficoServidor(graficoPerformanceBaixa),
					enviarGraficoServidor(graficoSintetico),
					enviarGraficoServidor(graficoAnalitico)
				]);

				// Processa gráfico detalhado com tratamento especial
				await processarGraficoDetalhado();
				
			} catch (error) {
				console.error("Erro ao processar gráficos:", error);
				throw error; // Re-lança o erro para ser capturado no escopo superior
			}
		}

		async function processarGraficoDetalhado() {
			const panel = $('#collapse3');
			const wasClosed = !panel.hasClass('show');
			
			try {
				if (wasClosed) {
					console.log('🔓 Abrindo accordion para gráfico detalhado...');
					panel.collapse('show');
					await new Promise(resolve => setTimeout(resolve, 800)); // Tempo maior para garantir abertura
				}

				graficoDetalhado.reflow();
				await new Promise(resolve => setTimeout(resolve, 500)); // Tempo para renderização

				console.log('📸 Capturando gráfico detalhado...');
				await enviarGraficoServidor(graficoDetalhado);
				
			} finally {
				if (wasClosed) {
					panel.collapse('hide');
				}
			}
		}

		async function enviarFormulario(novaJanela) {
			return new Promise((resolve) => {
				// Criação do formulário
				var data = "<?= $_POST['busca_dataMes'] ?>";
				var form = document.createElement('form');
				form.method = 'POST';
				form.action = 'export_paineis.php';
				form.target = 'janelaExportacao';

				// Adiciona campos básicos
				var campos = [
					{name: 'empresa', value: "<?= !empty($_POST['empresa']) ? $_POST['empresa'] : 'null' ?>"},
					{name: 'busca_data', value: data},
					{name: 'relatorio', value: 'nc_juridica'},
					{name: 'busca_endossado', value: "<?= $_POST['busca_endossado'] ?? '' ?>"},
					{name: 'operacao', value: "<?= $_POST['operacao'] ?? '' ?>"},
					{name: 'busca_setor', value: "<?= $_POST['busca_setor'] ?? '' ?>"},
					{name: 'busca_subsetor', value: "<?= $_POST['busca_subsetor'] ?? '' ?>"},
					{name: 'ranking_type', value: "<?= $_POST['ranking_type'] ?? '' ?>"},
					{name: 'ranking_limit', value: "<?= $_POST['ranking_limit'] ?? '' ?>"},
					{name: 'busca_ocupacao', value: "<?= !empty($_POST['busca_ocupacao']) && !is_array($_POST['busca_ocupacao']) ? $_POST['busca_ocupacao'] : '' ?>"}
				];

				<?php if(!empty($_POST['busca_ocupacao']) && is_array($_POST['busca_ocupacao'])): ?>
					<?php foreach($_POST['busca_ocupacao'] as $ocup): ?>
						campos.push({name: 'busca_ocupacao[]', value: "<?= $ocup ?>"});
					<?php endforeach; ?>
				<?php endif; ?>

				campos.forEach(function(campo) {
					var input = document.createElement('input');
					input.type = 'hidden';
					input.name = campo.name;
					input.value = campo.value;
					form.appendChild(input);
				});

				// Processamento da tabela (se necessário)
				var tabelaOriginal = document.querySelector('#tabela-empresas');
				if (tabelaOriginal) {
					var tabelaClone = tabelaOriginal.cloneNode(true);
					tabelaClone.querySelectorAll('i.fa, script, style, link').forEach(el => el.remove());

					var coresStatus = {
						'endo': '#4ea9ff',
						'endo-parc': '#ffe80063',
						'nao-endo': '#ec4141'
					};

					var htmlSimplificado = '<table style="width:100%;border-collapse:collapse;font-family:helvetica;font-size:7pt">';
					// Processa o thead principal (primeiro thead)
					var mainThead = tabelaClone.querySelector('thead:first-of-type');
					if (mainThead) {
						htmlSimplificado += '<thead>';
						mainThead.querySelectorAll('tr').forEach(tr => {
							htmlSimplificado += '<tr>';
							tr.querySelectorAll('th').forEach((th, colIndex) => {
								<?php if($_POST['busca_endossado'] !== "endossado") { ?>
									// Cores fixas por coluna (mesmas do corpo da tabela)
									var coresColunas = {
										3: '#ffe800',  
										4: '#ffe800', 
										5: '#ffe800',  
										6: '#ffe800',  
										7: '#ff8b00', 
										8: '#ff8b00', 
										9: '#ff8b00', 
										10: '#a30000', 
										11: '#a30000', 
										12: '#a30000'  
									};
								<?php } else { ?>
									// Cores fixas por coluna (mesmas do corpo da tabela)
									var coresColunas = {
										3: '#ffe800',  
										4: '#ff8b00',  
										5: '#ff8b00',   
										6: '#ff8b00',  
										7: '#a30000', 
										8: '#a30000',   
									};
								<?php } ?>

								var estiloTh = 'border:0.5px solid #000;padding:2px;text-align:center;font-weight:bold;';
								
								// Aplica a cor se existir para esta coluna
								if (coresColunas[colIndex]) {
									estiloTh += 'background-color:' + coresColunas[colIndex] + ';';
								}

								<?php if($_POST['busca_endossado'] !== "endossado") { ?>
									if (colIndex === 10 || colIndex === 11 || colIndex === 12) {
										estiloTh += 'color:white;';
									} 
								<?php } else { ?>
									if (colIndex === 7 || colIndex === 8) {
										estiloTh += 'color:white;';
									}
								<?php } ?>


								htmlSimplificado += '<th style="' + estiloTh + '">';
								htmlSimplificado += th.innerHTML;
								htmlSimplificado += '</th>';
							});
							htmlSimplificado += '</tr>';
						});
						htmlSimplificado += '</thead>';
					}

					var tbody = tabelaClone.querySelector('tbody');
					if (tbody) {
						htmlSimplificado += '<tbody>';
						tbody.querySelectorAll('tr').forEach(tr => {
							// Verifica se a linha está totalmente zerada/vazia
							var linhaZerada = true;
							var celulas = tr.querySelectorAll('td');
							
							// Verifica cada célula (exceto as colunas 0, 1 e 2 - Matrícula, Funcionário, Ocupação)
							for (var i = 3; i < celulas.length - 3; i++) {
								var valor = celulas[i].textContent.trim();
								if (valor !== "" && valor !== "0") {
									linhaZerada = false;
									break;
								}
							}
							htmlSimplificado += '<tr>';
							tr.querySelectorAll('td').forEach((td, colIndex) => {
								var estiloBase = 'border:0.5px solid #000; font-size:6.8pt;';
								
								// Alinhamento do texto
								if (colIndex === 1) { // Coluna "Funcionário"
									estiloBase += 'text-align:left;white-space:nowrap;overflow:hidden;max-width:90px;';
								} else {
									estiloBase += 'text-align:center;';
								}

								<?php if($_POST['busca_endossado'] !== "endossado") { ?>
									// Cores fixas por coluna
									var coresColunas = {
										3: '#FFFACD',  
										4: '#FFFACD',  
										5: '#FFFACD',  
										6: '#FFFACD',  
										7: '#FFDAB9',  
										8: '#FFDAB9',   
										9: '#FFDAB9',  
										10: '#FFCCCB', 
										11: '#FFCCCB',  
										12: '#FFCCCB'   
									};
								<?php } else { ?>
									// Cores fixas por coluna (mesmas do corpo da tabela)
									var coresColunas = {
										3: '#FFFACD',  
										4: '#FFDAB9',  
										5: '#FFDAB9',   
										6: '#FFDAB9',  
										7: '#FFCCCB', 
										8: '#FFCCCB',  
									};
								<?php } ?>

								// Lógica para as 2 últimas colunas
								if (colIndex >= celulas.length - 2) { // Penúltima e última coluna
									var valor = td.textContent.trim();
									var porcentagem = parseFloat(valor) || 0;
									
									if (colIndex === celulas.length - 2) { // Penúltima coluna
										if (porcentagem >= 75) {
											estiloBase += 'background-color:lightgreen;';
										} else if (porcentagem >= 50) {
											estiloBase += 'background-color:#FFFACD;'; // Amarelo
										} else if (porcentagem >= 25) {
											estiloBase += 'background-color:#FFDAB9;'; // Laranja
										} else {
											estiloBase += 'background-color:#FFCCCB;'; // Vermelho
										}
									} 
									else { // Última coluna
										if (porcentagem >= 75) {
											estiloBase += 'background-color:lightgreen;';
										} else if (porcentagem >= 50) {
											estiloBase += 'background-color:#FFFACD;'; // Amarelo
										} else if (porcentagem >= 25) {
											estiloBase += 'background-color:#FFDAB9;'; // Laranja
										} else {
											estiloBase += 'background-color:#FFCCCB;'; // Vermelho
										}
									}
								}

								 // Se a linha estiver zerada, aplica cor verde
								 if (linhaZerada && colIndex > 2) { // Não aplica nas colunas 0, 1 e 2
									estiloBase += 'background-color:#90EE90;'; // Verde claro
								 } else if (coresColunas[colIndex]) {
									estiloBase += 'background-color:' + coresColunas[colIndex] + ';';
								 }

								htmlSimplificado += '<td style="' + estiloBase + '">';
								htmlSimplificado +=  td.innerHTML.trim();
								htmlSimplificado += '</td>';
							});
							htmlSimplificado += '</tr>';
						});
						htmlSimplificado += '</tbody>';
					}

					var totalThead = tabelaClone.querySelector('tbody + thead');
					if (totalThead) {
						// Remove qualquer outro thead que não seja o de totais
						tabelaClone.querySelectorAll('thead').forEach(thead => {
							if (thead !== mainThead && thead !== totalThead) {
								thead.remove();
							}
						});

						htmlSimplificado += '<tfoot>';  // ⭐ Usando tfoot para os totais (mais semântico)
						totalThead.querySelectorAll('tr').forEach(tr => {
							htmlSimplificado += '<tr>';
							tr.querySelectorAll('td').forEach(td => {
								var estilo = 'border:0.5px solid #000;padding:2px;font-size:7pt;text-align:center;';
								if (td.classList.contains('total')) {
									estilo += 'font-weight:bold;';
								}
								htmlSimplificado += '<td style="' + estilo + '">' + td.innerHTML + '</td>';
							});
							htmlSimplificado += '</tr>';
						});
						htmlSimplificado += '</tfoot>';
					}

					htmlSimplificado += '</table>';

					var inputTabela = document.createElement('input');
					inputTabela.type = 'hidden';
					inputTabela.name = 'htmlTabela';
					inputTabela.value = htmlSimplificado;
					form.appendChild(inputTabela);

					// Criando campo 3
					var input2 = document.createElement('input');
					input2.type = 'hidden';
					input2.name = 'busca_endossado';
					input2.value = '<?= $_POST["busca_endossado"]?>' ; // Valor do segundo campo
					form.appendChild(input2);
				}

				const checkComplete = () => {
					// Verifica periodicamente se a janela terminou de carregar
					if (novaJanela.document.readyState === 'complete') {
						resolve();
					} else {
						setTimeout(checkComplete, 100);
					}
				};

				// Adiciona o formulário ao DOM
				document.body.appendChild(form);

				// Envia o formulário
				form.submit();
				form.remove();

				setTimeout(checkComplete, 500);
			});
		}


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
