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

		<?php if($endossado === true) {?>
		<div class="portlet-body form">
			<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
				<tbody>
					<tr>
						<td>Jornada Prevista</td>
						<td>"Abono (Folgas, Férias ou outros)."</td>
					</tr>
					<tr>
						<td>Jornada Efetiva</td>
						<td>"Tempo exedido de 10:00h." ou "Tempo exedido de 12:00h."</td>
					</tr>
					<tr>
						<td>MDC - Máximo de Direção Continua</td>
						<td>"Descanso de 00:30 a cada 05:30 dirigidos não respeitado." ou "Descanso de 00:15 não respeitado." ou "Descanso de 00:30 não respeitado."</td>
					</tr>
					<tr>
						<td>Refeição</td>
						<td>"Batida início de refeição não registrada!" ou "Refeição Initerrupita maior do que 01:00h não respeitada" ou "Refeição com Tempo máximo de 02:00h não respeitada."</td>
					</tr>
					<tr>
						<td>Interstício Inferior</td>
						<td>"O mínimo de 08:00h ininterruptas no primeiro período não respeitado."</td>
					</tr>
					<tr>
						<td>Interstício Superior</td>
						<td>"Interstício Total de 11:00 não respeitado, faltaram 00:32."</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- <div class="portlet-body form">
			<table id="" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
				<thead>
					<tr>
						<td>Refeição</td>
						<td>Quant.</td>
						<td>%</td>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Batida início de refeição não registrada!</td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Batida fim de refeição não registrada!</td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Refeição Initerrupita maior do que 01:00h não respeitada.</td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Refeição com Tempo máximo de 02:00h não respeitada.</td>
						<td></td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="portlet-body form">
			<table id="" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
				<thead>
					<tr>
						<td>Interstícios</td>
						<td>Quant.</td>
						<td>%</td>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>INFERIOR: O mínimo de 08:00h ininterruptas no primeiro período não respeitado.</td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>SUPERIOR: Interstício Total de 11:00 não respeitado, faltaram 00:32.</td>
						<td></td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="portlet-body form">
			<table id="" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
				<thead>
					<tr>
						<td>Jornada Efetiva</td>
						<td>Quant.</td>
						<td>%</td>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Tempo exedido de 10:00h.</td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Tempo exedido de 12:00h.</td>
						<td></td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="portlet-body form">
			<table id="" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
				<thead>
					<tr>
						<td>MDC</td>
						<td>Quant.</td>
						<td>%</td>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Descanso de 00:30 a cada 05:30 dirigidos não respeitado.</td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Descanso de 00:15 não respeitado.</td>
						<td></td>
						<td></td>
					</tr>
					<tr>
						<td>Descanso de 00:30 não respeitado.</td>
						<td></td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="portlet-body form">
			<table id="" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact">
				<thead>
					<tr>
						<td>Jornada Prevista</td>
						<td>Quant.</td>
						<td>%</td>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Abono (Folgas, Férias ou outros)</td>
						<td></td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div> -->
		<?php }?>

	</div>
</div>
</div>
<div id="impressao">
	<b>Impressão Doc.:</b> <?= date("d/m/Y \T H:i:s") . " (UTC-3)" ?>
</div>