<?php
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

$mesAtual = date("n");
$anoAtual = date("Y");
// Obtém a data de início do mês atual
$dataTimeInicio = new DateTime('first day of this month');
$dataInicio= $dataTimeInicio->format('d/m/Y');

// Obtém a data de fim do mês atual
$dataTimeFim = new DateTime('last day of this month');
$dataFim = $dataTimeFim->format('d/m/Y');

// Obtém O total dos saldos das empresa
$file = "./arquivos/paineis/$idEmpresa/$anoAtual-$mesAtual/totalMotoristas.json";

if (file_exists("./arquivos/paineis/$idEmpresa/$anoAtual-$mesAtual")) {
	$conteudo_json = file_get_contents($file);
	$MotoristasTotais = json_decode($conteudo_json,true);
}


// Obtém O total dos saldos de cada Motorista
$fileEmpresas = "./arquivos/paineis/$idEmpresa/$anoAtual-$mesAtual/motoristas.json";
if (file_exists("./arquivos/paineis/$idEmpresa/$anoAtual-$mesAtual")) {
	$conteudo_json = file_get_contents($fileEmpresas);
	$MotoristaTotais = json_decode($conteudo_json,true);
}

// Obtém o tempo da última modificação do arquivo
$timestamp = filemtime($file);
$Emissão = date('d/m/Y H:i:s', $timestamp);


// Calcula a porcentagem
$porcentagenNaEndo = number_format(($MotoristasTotais['naoEndossados'] / $MotoristasTotais['totalMotorista']) * 100,2);
$porcentagenEndoPc = number_format(($MotoristasTotais['endossoPacial']/ $MotoristasTotais['totalMotorista']) * 100, 2);
$porcentagenEndo = number_format(($MotoristasTotais['endossados'] / $MotoristasTotais['totalMotorista']) * 100, 2);

// Define a cor com base na porcentagem
if ($porcentagenNaEndo == 0.00) {
    $cssBgNaEndo = 'background-color: #85e085';
} elseif ($porcentagenNaEndo <= 50.00) {
    $cssBgNaEndo = 'background-color: #ffff4d';
} else {
    $cssBgNaEndo = 'background-color: #ff4d4d';
}

if ($porcentagenEndoPc == 0.00) {
    $cssBgEndopc = 'background-color: #85e085';
} elseif ($porcentagenEndoPc <= 50.00) {
    $cssBgEndopc = 'background-color: #ffff4d';
} else {
    $cssBgEndopc = 'background-color: #ff4d4d';
}

if ($porcentagenEndo == 0.00) {
    $cssBgEndo = 'background-color: #ff4d4d';
} elseif ($porcentagenEndo <= 50.00) {
    $cssBgEndo = 'background-color: #ffff4d';
} else {
    $cssBgEndo = 'background-color: #85e085';
}

?>

<style>
	#tabela1 {
		width: 30% !important;
		/*margin-top: 9px !important;*/
		text-align: center;
		margin-bottom: -10px !important;
	}
	.textPocentagemNEndosado {
            <? echo $cssBgNaEndo ?>
    }
    .textPocentagemEndosadoPc {
            <? echo $cssBgEndopc ?>
    }
    .textPocentagemEndosado {
            <? echo $cssBgEndo ?>
    }
</style>

<div id="tituloRelatorio">
    <img style='width: 150px' src="<?=  $aEmpresa[0]['empr_tx_logo'] ?>" alt="Logo Empresa Esquerda">
	<h3>Relatorio Geral de Espelho de Ponto</h3>
	 <div class="right-logo">
        <p></p>
        <img style='width: 150px' src="<?=$CONTEX['path']?>/imagens/logo_topo_cliente.png" alt="Logo Empresa Direita">
    </div>
</div>
<style>
	#tituloRelatorio{
		display: none;
    }
</style>
<div class="col-md-12 col-sm-12">
	<div class="portlet light ">
		<div class="emissao">Emissão Doc.: <?= $Emissão?></div>
		<div class="table-responsive">
			<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact" id="tabela1">
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
						<td class="textCentralizado"><?= $MotoristasTotais['naoEndossados'] ?></td>
						<td class="textPocentagemNEndosado"><?= $porcentagenNaEndo ?></td>
					</tr>
					<tr>
						<td>ENDOSSO PARCIAL</td>
						<td class="textCentralizado"><?= $MotoristasTotais['endossoPacial']?></td>
						<td class="textPocentagemEndosadoPc"><?= number_format(($MotoristasTotais['endossoPacial']/ $MotoristasTotais['totalMotorista']) * 100, 2) ?></td>
					</tr>
					<tr>
						<td>ENDOSSADO</td>
						<td class="textCentralizado"><?= $MotoristasTotais['endossados'] ?></td>
						<td class="textPocentagemEndosado"><?= number_format(($MotoristasTotais['endossados'] / $MotoristasTotais['totalMotorista']) * 100, 2) ?></td>
					</tr>
				</tbody>
			</table>
			<br>
			<div class="portlet-body form">
			<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact" id="tabela2">
				<thead>
					<tr class="totais">
						<th colspan="1">PERÍODO: De <?= $dataInicio . ' até ' . $dataFim ?></th>
						<th colspan="1"></th>
						<?php
								if ($MotoristasTotais != null) {
									echo "<th colspan='1'> $MotoristasTotais[jornadaPrevista]</th>";
									echo "<th colspan='1'> $MotoristasTotais[JornadaEfetiva]</th>";
									echo "<th colspan='1'> $MotoristasTotais[he50]</th>";
									echo "<th colspan='1'> $MotoristasTotais[HE100]</th>";
									echo "<th colspan='1'> $MotoristasTotais[adicionalNoturno]</th>";
									echo "<th colspan='1'> $MotoristasTotais[esperaIndenizada]</th>";
									echo "<th colspan='1'> $MotoristasTotais[saldoAnterior]</th>";
									echo "<th colspan='1'> $MotoristasTotais[saldoPeriodo]</th>";
									echo "<th colspan='1'> $MotoristasTotais[saldoFinal]</th>";
								}
						?>
					</tr>
					<tr class="titulos">
						<th>Unidade - <?= $MotoristasTotais['empresaNome']; ?></th>
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
					if ($MotoristaTotais != null) {
						foreach ($MotoristaTotais as $MotoristaTotal) {
							echo '<tr class="conteudo">';
							echo "<td> $MotoristaTotal[motorista]</td>";
							echo "<td> $MotoristaTotal[statusEndosso]</td>";
							echo "<td> $MotoristaTotal[jornadaPrevista]</td>";
							echo "<td> $MotoristaTotal[jornadaEfetiva]</td>";
							echo "<td> $MotoristaTotal[he50]</td>";
							echo "<td> $MotoristaTotal[he100]</td>";
							echo "<td> $MotoristaTotal[adicionalNoturno]</td>";
							echo "<td> $MotoristaTotal[esperaIndenizada]</td>";
							echo "<td> $MotoristaTotal[saldoAnterior]</td>";
							echo "<td> $MotoristaTotal[saldoPeriodo]</td>";
							echo "<td> $MotoristaTotal[saldoFinal]</td>";
							echo '</tr>';
						}
					}
				?>
				</tbody>
			</table>
			</div>

		</div>
	</div>
</div>