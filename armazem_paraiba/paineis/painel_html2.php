<link rel="stylesheet" href="../css/paineis.css">
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
			<div>
				<h1>Relatorio <?= $titulo ?></h1>
			</div>
			<div class='emissao' style="display: block !important;">
				<?= $dataEmissao . "<br>"
					. "<b>Período do relatório:</b> " . $periodoRelatorio["dataInicio"] . " a " . $periodoRelatorio["dataFim"] ?>
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
				<h4>Legendas</h4>
			</div>
			<div class="portlet-body form">
				<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
					<tbody>
						<tr>
							<td class="tituloBaixaGravidade">Jornada Prevista</td>
							<td class="baixaGravidade">"Abono (Folgas, Férias ou outros)."</td>
						</tr>
						<tr>
							<td class="TituloMediaGravidade">Jornada Efetiva</td>
							<td class="mediaGravidade">"Tempo exedido de 10:00h." ou "Tempo exedido de 12:00h."</td>
						</tr>
						<tr>
							<td class="TituloMediaGravidade">MDC - Máximo de Direção Continua</td>
							<td class="mediaGravidade">"Descanso de 00:30 a cada 05:30 dirigidos não respeitado." ou "Descanso de 00:15 não respeitado." ou "Descanso de 00:30 não respeitado."</td>
						</tr>
						<tr>
							<td class="tituloAltaGravidade">Refeição</td>
							<td class="altaGravidade">"Batida início de refeição não registrada!" ou "Refeição Initerrupita maior do que 01:00h não respeitada" ou "Refeição com Tempo máximo de 02:00h não respeitada."</td>
						</tr>
						<tr>
							<td class="tituloAltaGravidade">Interstício Inferior</td>
							<td class="altaGravidade">"O mínimo de 08:00h ininterruptas no primeiro período não respeitado."</td>
						</tr>
						<tr>
							<td class="tituloAltaGravidade">Interstício Superior</td>
							<td class="altaGravidade">"Interstício Total de 11:00 não respeitado, faltaram 00:32."</td>
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