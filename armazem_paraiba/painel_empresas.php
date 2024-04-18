<?php
// ini_set('display_errors', 1);
// error_reporting(E_ALL);
include 'painel_empresas_csv.php';

$mesAtual = date("n");
$anoAtual = date("Y");
$motoristas = mysqli_fetch_all(
	query("SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula  FROM entidade WHERE enti_tx_status != 'inativo' AND enti_tx_ocupacao IN ('Motorista', 'Ajudante');"),
	MYSQLI_ASSOC
);
$totalMotorEmpr = count($motoristas);

$empresasTotais = [];
$empresaTotais = [];
$Emissão = '';
if (is_dir("./arquivos/paineis/empresas/$_POST[busca_data]") != false) {
	$file = "./arquivos/paineis/empresas/$_POST[busca_data]/empresas.json";
	if (file_exists("./arquivos/paineis/empresas/$_POST[busca_data]")) {
		$conteudo_json = file_get_contents($file);
		$empresasTotais = json_decode($conteudo_json,true);
	}

	// Obtém O total dos saldos de cada empresa
	$fileEmpresas = "./arquivos/paineis/empresas/$_POST[busca_data]/totalEmpresas.json";

	if (file_exists("./arquivos/paineis/empresas/$_POST[busca_data]")) {
		$conteudo_json = file_get_contents($fileEmpresas);
		$empresaTotais = json_decode($conteudo_json,true);
	}
	
	// Obtém o tempo da última modificação do arquivo
    $timestamp = filemtime($file);
    $Emissão = date('d/m/Y H:i:s', $timestamp);
}else   
    echo '<script>alert("Não Possui dados desse més")</script>';



// Calcula a porcentagem
$porcentagenNaEndo = number_format(0,2);
$porcentagenEndoPc = number_format(0,2);
$porcentagenEndo = number_format(0,2);
if ($empresasTotais['EmprTotalNaoEnd'] != 0) {
	$porcentagenNaEndo = number_format(($empresasTotais['EmprTotalNaoEnd'] / $empresasTotais['EmprTotalMotorista']) * 100, 2);
}
if ($empresasTotais['EmprTotalEndPac'] != 0) {
	$porcentagenEndoPc = number_format(($empresasTotais['EmprTotalEndPac'] / $empresasTotais['EmprTotalMotorista']) * 100, 2);
}
if ($empresasTotais['EmprTotalEnd'] != 0) {
	$porcentagenEndo = number_format(($empresasTotais['EmprTotalEnd'] / $empresasTotais['EmprTotalMotorista']) * 100, 2);
}

$quantPosi = 0;
$quantNega = 0;
$quantMeta = 0;

foreach ($empresaTotais as $empresaTotal) {
    $saldoFinal = $empresaTotal['saldoFinal'];

    if ($saldoFinal === '00:00') {
        $quantMeta++;
    } elseif ($saldoFinal > '00:00') {
        $quantPosi++;
    } elseif ($saldoFinal < '00:00') {
        $quantNega++;
    }
}

$porcentagenMeta = number_format(0,2);
$porcentagenNega = number_format(0,2);
$porcentagenPosi = number_format(0,2);

if ($quantMeta != 0) {
	$porcentagenMeta  = number_format(($quantMeta / count($empresaTotais)) * 100, 2);
}
if ($quantNega != 0) {
	$porcentagenNega = number_format(($quantNega / count($empresaTotais)) * 100, 2);
}
if ($quantPosi != 0) {
	$porcentagenPosi = number_format(($quantPosi / count($empresaTotais)) * 100, 2);
}

?>

<style>
	#tabela1 {
		width: 30% !important;
		/*margin-top: 9px !important;*/
		text-align: center;
		margin-bottom: -10px !important;
	}
	#tabela2 {
		width: 30% !important;
		/*margin-top: 9px !important;*/
		text-align: center;
		margin-bottom: -10px !important;
		margin-left: 10px;
	}
	.totais{
	    background-color: #ffe699;
	}
	tr.totais > th:nth-child(2),
	tr.totais > th:nth-child(3),
	tr.totais > th:nth-child(4),
	tr.totais > th:nth-child(5),
	tr.totais > th:nth-child(6),
	tr.totais > th:nth-child(7),
	tr.totais > th:nth-child(8),
	tr.totais > th:nth-child(9),
	tr.totais > th:nth-child(10),
	tr.totais > th:nth-child(11),
	tr.totais > th:nth-child(12){
	    text-align: justify;
	}
	.titulos{
		background-color: #99ccff;
	}
	tr.titulos > th:nth-child(1),
	tr.titulos > th:nth-child(2),
	tr.titulos > th:nth-child(3),
	tr.titulos > th:nth-child(4),
	tr.titulos > th:nth-child(5),
	tr.titulos > th:nth-child(6),
	tr.titulos > th:nth-child(7),
	tr.titulos > th:nth-child(8),
	tr.titulos > th:nth-child(9),
	tr.titulos > th:nth-child(10),
	tr.titulos > th:nth-child(11),
	tr.titulos > th:nth-child(12){
	    text-align: justify;
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
<div class="col-md-12 col-sm-12" id="pdf2htmldiv">
	<div class="portlet light ">
	<div class="emissao">Emissão Doc.: <?= $Emissão?></div>
		<div class="table-responsive">
		    <div style="display: flex;">
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
						<td style="background-color: #ff471a;" class="porcentagenNaEndo"><?= $porcentagenNaEndo ?></td>
					</tr>
					<tr>
						<td>ENDOSSO PARCIAL</td>
						<td class="textCentralizado"><?= $empresasTotais['EmprTotalEndPac']?></td>
						<td style="background-color: #ffff66;" class="porcentagenEndoPc"><?= $porcentagenEndoPc ?></td>
					</tr>
					<tr>
						<td>ENDOSSADO</td>
						<td class="textCentralizado"><?= $empresasTotais['EmprTotalEnd'] ?></td>
						<td style="background-color: #66b3ff;" class='porcentagenEndo'><?= $porcentagenEndo  ?></td>
					</tr>
				</tbody>
			</table>
			<br>
			<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact" id="tabela2">
				<thead>
					<tr>
						<th colspan="1">SALDO FINAL</th>
						<th colspan="1">QUANT</th>
						<th colspan="1">%</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td  class="porcentagenMeta" style="background-color: #66b3ff;">META</td>
						<td class="textCentralizado"><?= $quantMeta ?></td>
						<td><?= $porcentagenMeta ?></td>
					</tr>
					<tr>
						<td class='porcentagenPosit' style="background-color: #00b33c;">POSITIVO</td>
						<td class="textCentralizado"><?= $quantPosi?></td>
						<td><?= $porcentagenPosi ?></td>
					</tr>
					<tr>
						<td class='porcentagenNegat' style="background-color: #ff471a;">NEGATIVO</td>
						<td class="textCentralizado"><?= $quantNega ?></td>
						<td><?= $porcentagenNega  ?></td>
					</tr>
				</tbody>
			</table>
			</div>
			<br>
			<div class="portlet-body form">
			<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact">
				<thead>
					<tr class="totais">
					<th colspan="1">Período: De <?= $dataInicio . ' até ' . $dataFim ?></th>
						<th>Status</th>
						<th></th>
						<?php
								if ($empresasTotais != null) {
									echo "<th colspan='1'> $empresasTotais[EmprTotalJorPrev]</th>";
									echo "<th colspan='1'> $empresasTotais[EmprTotalJorEfe]</th>";
									echo "<th colspan='1'> ".(($empresasTotais['EmprTotalHE50'] == '00:00') ? '' : $empresasTotais['EmprTotalHE50'])."</th>";
									echo "<th colspan='1'> ".(($empresasTotais['EmprTotalHE100'] == '00:00') ? '' : $empresasTotais['EmprTotalHE100'])."</th>";
									echo "<th colspan='1'> ".(($empresasTotais['EmprTotalAdicNot'] == '00:00') ? '' : $empresasTotais['EmprTotalAdicNot'])."</th>";
									echo "<th colspan='1'> ".(($empresasTotais['EmprTotalEspInd'] == '00:00') ? '' : $empresasTotais['EmprTotalEspInd'])."</th>";
									echo "<th colspan='1'> $empresasTotais[EmprTotalSaldoAnter]</th>";
									echo "<th colspan='1'> $empresasTotais[EmprTotalSaldoPeriodo]</th>";
									echo "<th colspan='1'> $empresasTotais[EmprTotalSaldoFinal]</th>";
								}
						?>
					</tr>
					<tr class="titulos">
						<th>Todos os CNPJ</th>
						<th>End %</th>
						<th>Quant. Motoristas</th>
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
						    $porcentagenEndoEmpresa = number_format(0,2);
							if ($empresaTotal['endossados'] != 0) {
							    $porcentagenEndoEmpresa = number_format(($empresaTotal['endossados'] / $empresaTotal['totalMotorista']) * 100,2);
                            }
							echo '<tr class="conteudo">';
							echo "<td onclick=\"setAndSubmit('".$empresaTotal['empresaId']."')\">".$empresaTotal['empresaNome']."</td>";
							echo "<td>$porcentagenEndoEmpresa</td>";
							echo "<td> $empresaTotal[totalMotorista]</td>";
							echo "<td> $empresaTotal[jornadaPrevista]</td>";
							echo "<td> $empresaTotal[JornadaEfetiva]</td>";
							echo "<td>" . (($empresaTotal['he50'] == '00:00') ? '' : $empresaTotal['he50']) . "</td>";
							echo "<td>" . (($empresaTotal['he100'] == '00:00') ? '' : $empresaTotal['he100']) . "</td>";
							echo "<td>" . (($empresaTotal['adicionalNoturno'] == '00:00') ? '' : $empresaTotal['adicionalNoturno']) . "</td>";
							echo "<td>" . (($empresaTotal['esperaIndenizada'] == '00:00') ? '' : $empresaTotal['esperaIndenizada']) . "</td>";
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