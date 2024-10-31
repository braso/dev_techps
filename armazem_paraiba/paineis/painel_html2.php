<link rel="stylesheet" href="../css/paineis.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div id="printTitulo">
	<img style="width: 150px" src="<?= $logoEmpresa ?>" alt="Logo Empresa Esquerda">
	<h3>Relatorio <?= $titulo ?></h3>
	<div class="right-logo">
		<img style="width: 150px" src="<?= $_ENV["APP_PATH"] . $_ENV["CONTEX_PATH"] ?>/imagens/logo_topo_cliente.png" alt="Logo Empresa Direita">
	</div>
</div>
<div class="col-md-12 col-sm-12" id="pdf2htmldiv">
	<div class="portlet light ">
		<div class="table-responsive">
			<div class='emissao' style="display: block !important;">
				<?php if ($endossado === true) { ?>
					<h1 class="titulo2">Relatorio <?= $titulo ?></h1>
				<?php } ?>
				<span></span>
				<?= $dataEmissao . "<br>"
					. "<b>Período do relatório:</b> " . $periodoRelatorio["dataInicio"] . " a " . $periodoRelatorio["dataFim"] ?>
				<br>
				<span><b>Empresa:</b> <?= $empresa["empr_tx_nome"] ?></span>
			</div>
		</div>
		<div class="portlet-body form">
			<?= $rowGravidade ?>
			<table id="tabela-empresas" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
				<thead>
					<?= $rowTitulos ?>
				</thead>
				<tbody>
					<!-- Conteúdo do json empresas será inserido aqui -->
				</tbody>
			</table>
		</div>

		<?php if ($endossado === true) { ?>
			<div>
				<h4><b>Legendas</b></h4>
			</div>
			<div class="portlet-body form">
				<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
					<thead>
						<tr>
							<td></td>
							<td></td>
							<td>Total</td>
							<td>%</td>
						</tr>
					</thead>
					<tbody>
						<?php if ($_POST["busca_endossado"] == "naoEndossado") { ?>
							<tr>
								<td class="tituloBaixaGravidade">Espera</td>
								<td class="baixaGravidade">"Inicio ou Fim de espera sem registro"</td>
								<td class="total"><?= $totalEspera ?></td>
								<td class="total"><?= $percentualEspera ?>%</td>
							</tr>
							<tr>
								<td class="tituloBaixaGravidade">Descanso</td>
								<td class="baixaGravidade">"Inicio ou Fim de descanso sem registro"</td>
								<td class="total"><?= $totalDescanso ?></td>
								<td class="total"><?= $percentualDescanso ?>%</td>
							</tr>
							<tr>
								<td class="tituloBaixaGravidade">Repouso</td>
								<td class="baixaGravidade">"Inicio ou Fim de repouso sem registro"</td>
								<td class="total"><?= $totalRepouso ?></td>
								<td class="total"><?= $percentualRepouso ?>%</td>
							</tr>
							<tr>
								<td class="tituloBaixaGravidade">Jornada</td>
								<td class="baixaGravidade">"Inicio ou Fim de Jornada sem registro"</td>
								<td class="total"><?= $totalJornada ?></td>
								<td class="total"><?= $percentualJornada ?>%</td>
							</tr>
						<?php } ?>
						<tr>
							<td class="tituloBaixaGravidade">Jornada Prevista</td>
							<td class="baixaGravidade">"Abono (Folgas, Férias ou outros)."</td>
							<td class="total"><?= $totalJornadaPrevista ?></td>
							<td class="total"><?= $percentualJornadaPrevista ?>%</td>
						</tr>
						<tr>
							<td class="tituloMediaGravidade">Jornada Efetiva</td>
							<td class="mediaGravidade">"Tempo excedido de 10:00h." ou "Tempo excedido de 12:00h."</td>
							<td class="total"><?= $totalJornadaEfetiva ?></td>
							<td class="total"><?= $percentualJornadaEfetiva ?>%</td>
						</tr>
						<tr>
							<td class="tituloMediaGravidade">MDC - Máximo de Direção Continua</td>
							<td class="mediaGravidade">"Descanso de 00:30 a cada 05:30 dirigidos não respeitado." ou "Descanso de 00:15 não respeitado." ou "Descanso de 00:30 não respeitado."</td>
							<td class="total"><?= $totalMdc ?></td>
							<td class="total"><?= $percentualMDC ?>%</td>
						</tr>
						<tr>
							<td class="tituloAltaGravidade">Refeição</td>
							<td class="altaGravidade">"Batida início de refeição não registrada!" ou "Refeição Initerrupita maior do que 01:00h não respeitada" ou "Refeição com Tempo máximo de 02:00h não respeitada."</td>
							<td class="total"><?= $totalRefeicao ?></td>
							<td class="total"><?= $percentualRefeicao ?>%</td>
						</tr>
						<tr>
							<td class="tituloAltaGravidade">Interstício Inferior</td>
							<td class="altaGravidade">"O mínimo de 08:00h ininterruptas no primeiro período não respeitado."</td>
							<td class="total"><?= $totalIntersticioInferior ?></td>
							<td class="total"><?= $percentualIntersticioInferior ?>%</td>
						</tr>
						<tr>
							<td class="tituloAltaGravidade">Interstício Superior</td>
							<td class="altaGravidade">"Interstício Total de 11:00 não respeitado, faltaram 00:32."</td>
							<td class="total"><?= $totalIntersticioSuperior ?></td>
							<td class="total"><?= $percentualIntersticioSuperior ?>%</td>
						</tr>
					</tbody>
				</table>
			</div>
			<div>
				<h4><b>Total de Não conformidade de todos Funcionário</b></h4>
			</div>
			<div class="portlet-body form">
				<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact" style="width: 400px;">
					<thead>
						<tr>
							<td></td>
							<td>Total</td>
							<td>%</td>
						</tr>
					</thead>
					<tbody>
						<?php if ($_POST["busca_endossado"] == "naoEndossado") { ?>
						<tr>
							<td class="tituloBaixaGravidade">Inicio ou Fim de espera sem registro</td>
							<td><?= $totalEspera ?></td>
						</tr>
						<tr>
							<td class="tituloBaixaGravidade">Inicio ou Fim de descanso sem registro</td>
							<td><?= $totalDescanso ?></td>
						</tr>
						<tr>
							<td class="tituloBaixaGravidade">Inicio ou Fim de repouso sem registro</td>
							<td><?= $totalRepouso ?></td>
						</tr>
						<tr>
							<td class="tituloBaixaGravidade">Inicio ou Fim de jornada sem registro</td>
							<td><?= $totalJornada ?></td>
						</tr>
						<?php } ?>
						<tr>
							<td class="tituloBaixaGravidade">Abono (Folgas, Férias ou outros)</td>
							<td><?= $totalJornadaPrevista ?></td>
						</tr>
						<tr>
							<td class="tituloMediaGravidade">Tempo excedida de 10:00h</td>
							<td><?= $totalJornadaExcedido10h ?></td>
						</tr>
						<tr>
							<td class="tituloMediaGravidade">Tempo excedida de 12:00h</td>
							<td><?= $totalJornadaExcedido12h ?></td>
						</tr>
						<tr>
							<td class="tituloMediaGravidade">Descanso de 00:30 a cada 05:30 dirigidos não respeitado</td>
							<td><?= $totalMdcDescanso30m5h ?></td>
						</tr>
						<tr>
							<td class="tituloMediaGravidade">Descanso de 00:30 não respeitado</td>
							<td><?= $totalMdcDescanso30m ?></td>
						</tr>
						<tr>
							<td class="tituloMediaGravidade">Descanso de 00:15 não respeitado</td>
							<td><?= $totalMdcDescanso15m ?></td>
						</tr>
						<tr>
							<td class="tituloAltaGravidade">Batida início de refeição não registrado</td>
							<td><?= $totalInicioRefeicaoSemRegistro ?></td>
						</tr>
						<tr>
							<td class="tituloAltaGravidade">Batida fim de refeição não registrado</td>
							<td><?= $totalFimRefeicaoSemRegistro ?></td>
						</tr>
						<tr>
							<td class="tituloAltaGravidade">Refeição Initerrupita maior do que 01:00h não respeitada</td>
							<td><?= $totalRefeicao1h ?></td>
						</tr>
						<tr>
							<td class="tituloAltaGravidade">Refeição com Tempo máximo de 02:00h não respeitada</td>
							<td><?= $totalRefeicao2h ?></td>
						</tr>
						<tr>
							<td class="tituloAltaGravidade">O mínimo de 08:00h ininterruptas no primeiro período não respeitado</td>
							<td><?= $totalIntersticioInferior ?></td>
						</tr>
						<tr>
							<td class="tituloAltaGravidade">Interstício Total de 11:00 não respeitado, faltaram 00:32</td>
							<td><?= $totalIntersticioSuperior ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		<?php } ?>

	</div>
</div>
</div>
<div id="impressao">
	<b>Impressão Doc.:</b> <?= date("d/m/Y \T H:i:s") . " (UTC-3)" ?>
</div>
<script>
	const data = {
		labels: ['Espera', 'Descanso', 'Repouso', 'Jornada', 'Jornada Prevista', 'Jornada Efetiva', 'MDC', 'Refeição', 'Interstício Inferior', 'Interstício Superior'],
		datasets: [{
			data: [<?= $percentualEspera ?>, <?= $percentualDescanso ?>, <?= $percentualRepouso ?>, <?= $percentualJornada ?>, <?= $percentualJornadaPrevista ?>, <?= $percentualJornadaEfetiva ?>,
				<?= $percentualMDC ?>, <?= $percentualRefeicao ?>, <?= $percentualIntersticioInferior ?>, <?= $percentualIntersticioSuperior ?>
			], // Dados das fatias
			backgroundColor: ['#53d02a', '#53d02a', '#53d02a', '#53d02a', '#53d02a', '#f1c61f', '#f1c61f', '#ec4141', '#ec4141', '#ec4141'], // Cores das fatias
			hoverOffset: 4
		}]
	};

	const config = {
		type: 'pie',
		data: data,
		options: {
			responsive: false,
			plugins: {
				legend: {
					display: false,
					position: 'top',
				},
				title: {
					display: true,
					text: 'Gráfico de Não Conformidades'
				},
				tooltip: {
					callbacks: {
						label: function(tooltipItem) {
							return tooltipItem.raw + '%'; // Formata o tooltip para mostrar o %
						}
					}
				}
			}
		},
	};

	// Criar e renderizar o gráfico
	const myPieChart = new Chart(
		document.getElementById('myPieChart'),
		config
	);
</script>