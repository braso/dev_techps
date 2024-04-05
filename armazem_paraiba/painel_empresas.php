<?php
// ini_set('display_errors', 1);
// error_reporting(E_ALL);
include 'painel_empresas_csv.php';

$mesAtual = date("n");
$anoAtual = date("Y");
// Obtém a data de início do mês atual
$dataTimeInicio = new DateTime('first day of this month');
$dataInicio= $dataTimeInicio->format('d/m/Y');

// Obtém a data de fim do mês atual
$dataTimeFim = new DateTime('last day of this month');
$dataFim = $dataTimeFim->format('d/m/Y');


// Obtém O total dos saldos das empresas
$file = "./arquivos/paineis/empresas/$anoAtual-$mesAtual/empresas.json";

if (file_exists("./arquivos/paineis/empresas/$anoAtual-$mesAtual")) {
	$conteudo_json = file_get_contents($file);
	$empresasTotais = json_decode($conteudo_json,true);
}

// Obtém O total dos saldos de cada empresa
$fileEmpresas = "./arquivos/paineis/empresas/$anoAtual-$mesAtual/totalEmpresas.json";
if (file_exists("./arquivos/paineis/empresas/$anoAtual-$mesAtual")) {
	$conteudo_json = file_get_contents($fileEmpresas);
	$empresaTotais = json_decode($conteudo_json,true);
}

// Obtém o tempo da última modificação do arquivo
$timestamp = filemtime($file);
$Emissão = date('d/m/Y H:i:s', $timestamp);

// Calcula a porcentagem
$porcentagenNaEndo = number_format(($empresasTotais['EmprTotalNaoEnd'] / $empresasTotais['EmprTotalMotorista']) * 100, 2);
$porcentagenEndoPc = number_format(($empresasTotais['EmprTotalEndPac']/ $empresasTotais['EmprTotalMotorista']) * 100, 2);
$porcentagenEndo = number_format(($empresasTotais['EmprTotalEnd'] / $empresasTotais['EmprTotalMotorista']) * 100, 2);

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
						<td class="textCentralizado"><?= $empresasTotais['EmprTotalNaoEnd'] ?></td>
						<td class="textPocentagemNEndosado"><?= $porcentagenNaEndo ?></td>
					</tr>
					<tr>
						<td>ENDOSSO PARCIAL</td>
						<td class="textCentralizado"><?= $empresasTotais['EmprTotalEndPac']?></td>
						<td class="textPocentagemEndosadoPc"><?= $porcentagenEndoPc ?></td>
					</tr>
					<tr>
						<td>ENDOSSADO</td>
						<td class="textCentralizado"><?= $empresasTotais['EmprTotalEnd'] ?></td>
						<td class="textPocentagemEndosado"><?= $porcentagenEndo  ?></td>
					</tr>
				</tbody>
			</table>
			<br>
			<div class="portlet-body form">
			<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact" id="tabela2">
				<thead>
					<tr class="totais">
					<th colspan="1">PERÍODO: De <?= $dataInicio . ' até ' . $dataFim ?></th>
						<th> </th>
						<?php
								if ($empresasTotais != null) {
									echo "<th colspan='1'> $empresasTotais[EmprTotalJorPrev]</th>";
									echo "<th colspan='1'> $empresasTotais[EmprTotalJorEfe]</th>";
									echo "<th colspan='1'> $empresasTotais[EmprTotalHE50]</th>";
									echo "<th colspan='1'> $empresasTotais[EmprTotalHE100]</th>";
									echo "<th colspan='1'> $empresasTotais[EmprTotalAdicNot]</th>";
									echo "<th colspan='1'> $empresasTotais[EmprTotalEspInd]</th>";
									echo "<th colspan='1'> $empresasTotais[EmprTotalSaldoAnter]</th>";
									echo "<th colspan='1'> $empresasTotais[EmprTotalSaldoPeriodo]</th>";
									echo "<th colspan='1'> $empresasTotais[EmprTotalSaldoFinal]</th>";
								}
						?>
					</tr>
					<tr class="titulos">
						<th>Todos os CNPJ</th>
						<th>Quantidade de Motoristas</th>
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
					if ($empresaTotais != null) {
						foreach ($empresaTotais as $empresaTotal) {
							echo '<tr class="conteudo">';
							echo "<td onclick=\"setAndSubmit('".$empresaTotal['empresaId']."')\">".$empresaTotal['empresaNome']."</td>";
							echo "<td> $empresaTotal[totalMotorista]</td>";
							echo "<td> $empresaTotal[jornadaPrevista]</td>";
							echo "<td> $empresaTotal[JornadaEfetiva]</td>";
							echo "<td> $empresaTotal[he50]</td>";
							echo "<td> $empresaTotal[he100]</td>";
							echo "<td> $empresaTotal[adicionalNoturno]</td>";
							echo "<td> $empresaTotal[esperaIndenizada]</td>";
							echo "<td> $empresaTotal[saldoAnterior]</td>";
							echo "<td> $empresaTotal[saldoPeriodo]</td>";
							echo "<td> $empresaTotal[saldoFinal]</td>";
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

<script>
    function downloadCSV() {
        // Caminho do arquivo CSV no servidor
        var filePath = './arquivos/paineis/Painel_Geral.csv' // Substitua pelo caminho do seu arquivo

        // Cria um link para download
        var link = document.createElement('a');

        // Configurações do link
        link.setAttribute('href', filePath);
        link.setAttribute('download', 'Painel_Geral.csv');

        // Adiciona o link ao documento
        document.body.appendChild(link);

        // Simula um clique no link para iniciar o download
        link.click();

        // Remove o link
        document.body.removeChild(link);
    }
</script>