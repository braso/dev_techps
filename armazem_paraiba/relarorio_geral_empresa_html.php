<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// var_dump($totaisEmpresas);

$totalJorPrevResut = "00:00";
$totalJorPrev = "00:00";
$totalJorEfe = "00:00";
$totalHE50 = "00:00";
$totalHE100 = "00:00";
$totalAdicNot = "00:00";
$totalEspInd = "00:00";
$totalSaldoPeriodo = "00:00";
$saldoFinal = '00:00';

foreach ($totaisEmpresas as $totalEmpresa) {
	$totalJorPrev      += somarHorarios([$totalJorPrev, $totalEmpresa['jornadaPrevista']]);
//     $totalJorEfe       += somarHorarios([$totalJorEfe, $totalEmpresa['jornadaEfetiva']]);
// 	$totalHE50         += somarHorarios([$totalHE50, $totalEmpresa['he50']]);
//     $totalHE100        += somarHorarios([$totalHE100, $totalEmpresa['he100']]);
//     $totalAdicNot      += somarHorarios([$totalAdicNot, $totalEmpresa['adicionalNoturno']]);
//     $totalEspInd       += somarHorarios([$totalEspInd, $totalEmpresa['esperaIndenizada']]);
//     $saldoAnterior     += somarHorarios([$saldoAnterior, $totalEmpresa['saldoAnterior']]);
// 	$totalSaldoPeriodo += somarHorarios([$totalSaldoPeriodo, $totalEmpresa['saldoPeriodo']]);
//     $saldoFinal        += somarHorarios([$saldoFinal, $totalEmpresa['saldoFinal']]);
// 	$totalMotorista += $totalEmpresa['totalMotorista'];
// 	$totalNaoEndossados += $totalEmpresa['naoEndossados'];
// 	$totalEndossados += $totalEmpresa['endossados'];
// 	$totalEndossoPacial += $totalEmpresa['endossoPacial'];
}
?>

<style>
	#saldo {
		width: 50% !important;
		margin-top: 9px !important;
		text-align: center;
	}
</style>
<div class="col-md-12 col-sm-12">
	<div class="portlet light ">
		<div class="table-responsive">
			<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact" id="saldo">
				<thead>
					<tr>
						<th colspan="1"></th>
						<th colspan="1">QUANT</th>
						<th colspan="1">%</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>NÃO ENDOSSADO</td>
						<td class="textCentralizado"><?= $totalNaoEndossados ?></td>
						<td class="textPocentagemNEndosado"><?= number_format(($totalNaoEndossados / $totalMotorista) * 100, 2) ?></td>
					</tr>
					<tr>
						<td>ENDOSSO PARCIAL</td>
						<td class="textCentralizado"><?= $totalEndossoPacial ?></td>
						<td class="textPocentagemNEndosado"><?= number_format(($totalEndossoPacial / $totalMotorista) * 100, 2) ?></td>
					</tr>
					<tr>
						<td>ENDOSSADO</td>
						<td class="textCentralizado"><?= $totalEndossados ?></td>
						<td class="textPocentagemNEndosado"><?= number_format(($totalEndossados / $totalMotorista) * 100, 2) ?></td>
					</tr>
				</tbody>
			</table>
			<br>
			<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact" id="saldo">
				<thead>
					<tr class="totais">
						<th colspan="1">PERÍODO: <?= $periodoInicio->format('d/m/Y') . ' - ' . $periodoFim->format('d/m/Y') ?></th>
						<th colspan="1"></th>
						<th colspan="1"><?= $totalJorPrev ?></th>
						<th colspan="1"><?= $totalJorEfe ?></th>
						<th colspan="1"><?= $totalHE50  ?></th>
						<th colspan="1"><?= $totalHE100 ?></th>
						<th colspan="1"><?= $totalAdicNot ?></th>
						<th colspan="1"><?= $totalEspInd ?></th>
						<th colspan="1"><?= $saldoAnterior ?></th>
						<th colspan="1"><?= $totalSaldoPeriodo ?></th>
						<th colspan="1"><?= $saldoFinal ?></th>
					</tr>
					<tr class="titulos">
						<th>Unidade - <?= $nomeEmpresa[0]['empr_tx_nome'] ?></th>
						<th>Status Endosso</th>
						<th>Jornada Prevista</th>
						<th>Jornada Efetiva</th>
						<th>HE 50%</th>
						<th>HE 100%</th>
						<th>Adicional Noturno</th>
						<th>ESPERA INDENIZADA</th>
						<th>Saldo Anterior</th>
						<th>Saldo Periodo</th>
						<th>Saldo Final</th>
					</tr>
				</thead>
				<tbody>
					<?
					?>
				</tbody>
			</table>

		</div>
	</div>
</div>