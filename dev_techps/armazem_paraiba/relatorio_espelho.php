<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Espelho de Ponto</title>
	<link rel="stylesheet" href="css/endosso.css">
	<style>
		.company-info>td{
			text-align: left;
		}
	</style>
</head>

<body>
	<div class="header">
		<img src="<?= $aEmpresa['empr_tx_logo'] ?>" alt="Logo Empresa Esquerda">
		<h1>Espelho de Ponto</h1>
		<div class="right-logo">
			<p></p>
			<img src="<?=$CONTEX['path']?>/imagens/logo_topo_cliente.png" alt="Logo Empresa Direita">
		</div>
	</div>
	<div class="info">
		<table class="table-header">
			<tr class="company-info">
				<td style="text-align: left;"><b>Empresa:</b> <?= $aEmpresa['empr_tx_nome'] ?></td>
				<td style="text-align: left;"><b>CNPJ:</b> <?= $aEmpresa['empr_tx_cnpj'] ?></td>
				<td colspan="2" style="text-align: left;"><b>End.</b> <?= "$enderecoEmpresa, $aEmpresa[cida_tx_nome]/$aEmpresa[cida_tx_uf], $aEmpresa[empr_tx_cep]" ?></td>
				<td style="text-align: left;"><b>Período:</b> <?= sprintf("%02d/%04d", $month, $year) ?></td>
				<td style="text-align: left;"><b>Emissão Doc.:</b> <?= $aEndosso['endo_tx_dataCadastro'] . " (UTC-3)" ?></td>
			</tr>
			
			<tr class="employee-info">
				<td style="text-align: left;"><b>Motorista:</b> <?= $dadosMotorista['enti_tx_nome'] ?></td>
				<td style="text-align: left;"><b>Função:</b> <?= $dadosMotorista['enti_tx_ocupacao'] ?></td>
				<td style="text-align: left;"><b>CPF:</b> <?= $dadosMotorista['enti_tx_cpf'] ?></td>
				<td style="text-align: left;"><b>Turno:</b> D.SEM/H: <?= $dadosMotorista['enti_tx_jornadaSemanal'] ?> FDS/H: <?= $aMotorista['enti_tx_jornadaSabado'] ?> </td>
				<td style="text-align: left;"><b>Matrícula:</b> <?= $dadosMotorista['enti_tx_matricula'] ?></td>
				<td style="text-align: left;"><b>Admissão:</b> <?= data($dadosMotorista['enti_tx_admissao']) ?></td>
			</tr>
		</table>
	</div>
	<table class="table" border="1">
		<thead>
			<tr>
				<th colspan="2">PERÍODO</th>
				<th colspan="4">REGISTROS DE JORNADA</th>
				<th colspan="4">INTERVALOS</th>
				<th colspan="3">JORNADA</th>
				<th colspan="5">APURAÇÃO DO CONTROLE DA JORNADA</th>
				<!-- <th>TRATAMENTO</th> -->
			</tr>
			<tr>
				<th>DATA</th>
				<th>DIA</th>
				<th>INÍCIO</th>
				<th>INÍCIO REF.</th>
				<th>FIM REF.</th>
				<th>FIM</th>
				<th>REFEIÇÃO</th>
				<th>ESPERA</th>
				<th>DESCANSO</th>
				<th>REPOUSO</th>
				<th>PREVISTA</th>
				<th>EFETIVA</th>
				<th>MDC</th>
				<th>INTERSTÍCIO</th>
				<th>HE 50%</th>
				<th>HE&nbsp;100%</th>
				<th>ADICIONAL NOT.</th>
				<th>ESPERA IND.</th>
				<th>MOTIVO</th>
				<th>SALDO</th>
			</tr>
		</thead>
		<tbody>
			<?
				foreach ($aDia as $aDiaVez) {
					echo '<tr>';
					for ($j = 1; $j < 21; $j++){
						if($j > 2 && $j < 7){
							foreach($legendas as $legenda){
								$aDiaVez[$j] = str_replace('<br><strong>'.$legenda['moti_tx_legenda'].'</strong>', ' <strong>'.$legenda['moti_tx_legenda'].'</strong>', $aDiaVez[$j]);
							}
						}
						echo '<td>'.$aDiaVez[$j].'</td>';
					}
					echo '</tr>';
				}
			?>

	</tbody>
</table>

<div><b>TOTAL: <?= $diasEndossados ?> dias</b></div>


<table class="table-bottom">
	<tr>
		<td rowspan="2">
			<table class="table-info">
				<tr>
					<td>Carga Horaria Prevista:</td>
					<td>
						<center><?= $totalResumo['jornadaPrevista'] ?></center>
					</td>
				</tr>
				<tr>
					<td>Carga Horaria Efetiva Realizada:</td>
					<td>
						<center><?= $totalResumo['diffJornadaEfetiva'] ?></center>
					</td>
				</tr>
				<tr>
					<td>Adicional Noturno:</td>
					<td>
						<center><?= $totalResumo['adicionalNoturno'] ?></center>
					</td>
				</tr>
				<tr>
					<td>Espera Indenizada:</td>
					<td>
						<center><?= $totalResumo['esperaIndenizada'] ?></center>
					</td>
				</tr>
			</table>

			<table class="table-info2">
				<tr>
					<td>Horas Extras (50%) - a pagar:</td>
					<td>
						<center><?= $totalResumo['he50'] ?></center>
					</td>
				</tr>
				<tr>
					<td>Horas Extras (100%) - a pagar:</td>
					<td>
						<center><?= $totalResumo['he100'] ?></center>
					</td>
				</tr>
			</table>
		</td>

			<td>
				<table class="table-resumo">
					<tr>
						<td>Saldo Anterior</td>
						<td><?=$saldoAnterior?></td>
						<td class="empty"></td>
						<td>Saldo Período</td>
						<td><?= $totalResumo['diffSaldo'] ?></td>
						<td class="empty"></td>
						<td>Saldo Atual</td>
						<td><?= $saldoAtual ?></td>
					</tr>
				</table>
			</td>
			
		</tr>
		<tr>
			<td>
				<div class="signature-block" style="display: inline-block; width: 45%;">
					<center>
						<p>___________________________________________________________</p>
					</center>
					<center>
						<p>Responsável</p>
					</center>
					<center>
						<p>Cargo</p>
					</center>
				</div>
				<div class="signature-block" style="display: inline-block; width: 45%;">
					<center>
						<p>___________________________________________________________</p>
					</center>
					<center>
						<p><?= $dadosMotorista['enti_tx_nome'] ?></p>
					</center>
					<center>
						<p>Motorista</p>
					</center>
				</div>
			</td>
		</tr>
		<tr>
			<td style="position: absolute; left: 73rem;"><b>Impressão Doc.:</b> <?= date("d/m/Y \T H:i:s") . "(UTC-3)" ?></td>
		</tr>
	</table>
</body>

</html>
<div style="page-break-after: always;"></div>