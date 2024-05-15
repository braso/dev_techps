<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	include 'painel_empresa_csv.php';

	$MotoristasTotais = [];
	$MotoristaTotais = [];
	if (is_dir("./arquivos/paineis/empresas/$_POST[busca_data]") != false) {
		// Obtém O total dos saldos das empresa
		$file = "./arquivos/paineis/$idEmpresa/$_POST[busca_data]/totalMotoristas.json";

		if (file_exists("./arquivos/paineis/$idEmpresa/$_POST[busca_data]")) {
			$conteudo_json = file_get_contents($file);
			$MotoristasTotais = json_decode($conteudo_json,true);
		}


		// Obtém O total dos saldos de cada Motorista
		$fileEmpresas = "./arquivos/paineis/$idEmpresa/$_POST[busca_data]/motoristas.json";
		if (file_exists("./arquivos/paineis/$idEmpresa/$_POST[busca_data]")) {
			$conteudo_json = file_get_contents($fileEmpresas);
			$MotoristaTotais = json_decode($conteudo_json,true);
		}
	}else   
		echo '<script>alert("Não Possui dados desse més")</script>';


	// Obtém o tempo da última modificação do arquivo
	$timestamp = '';
	$timestamp = filemtime($file);
	$Emissão = date('d/m/Y H:i:s', $timestamp);


	// Calcula a porcentagem
	$porcentagenNaEndo = number_format(0,2);
	$porcentagenEndoPc = number_format(0,2);
	$porcentagenEndo = number_format(0,2);
	if ($MotoristasTotais['naoEndossados'] != 0) {
		$porcentagenNaEndo = number_format(($MotoristasTotais['naoEndossados'] / $MotoristasTotais['totalMotorista']) * 100,2);
	}
	if ($MotoristasTotais['endossoPacial'] != 0) {
		$porcentagenEndoPc = number_format(($MotoristasTotais['endossoPacial']/ $MotoristasTotais['totalMotorista']) * 100, 2) ;
	}
	if ($MotoristasTotais['endossados'] != 0) {
		$porcentagenEndo = number_format(($MotoristasTotais['endossados'] / $MotoristasTotais['totalMotorista']) * 100,2);
	}


	$quantPosi = 0;
	$quantNega = 0;
	$quantMeta = 0;

	foreach ($MotoristaTotais as $MotoristaTotal) {
		$saldoFinal = $MotoristaTotal['saldoFinal'];

		if ($saldoFinal == '00:00') {
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
		$porcentagenMeta  = number_format(($quantMeta / count($MotoristaTotais)) * 100, 2);
	}
	if ($quantNega != 0) {
		$porcentagenNega = number_format(($quantNega / count($MotoristaTotais)) * 100, 2);
	}
	if ($quantPosi != 0) {
		$porcentagenPosi = number_format(($quantPosi / count($MotoristaTotais)) * 100, 2);
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
	tr.totais > th:nth-child(1),
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
<div class="col-md-12 col-sm-12">
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
						<td class="textCentralizado"><?= $MotoristasTotais['naoEndossados'] ?></td>
						<td style="background-color: #ff471a;" class="porcentagenNaEndo"><?= $porcentagenNaEndo ?></td>
					</tr>
					<tr>
						<td>ENDOSSO PARCIAL</td>
						<td class="textCentralizado"><?= $MotoristasTotais['endossoPacial']?></td>
						<td style="background-color: #ffff66;" class="porcentagenEndoPc;"><?= $porcentagenEndoPc ?></td>
					</tr>
					<tr>
						<td>ENDOSSADO</td>
						<td class="textCentralizado"><?= $MotoristasTotais['endossados'] ?></td>
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
						<th colspan="1"></th>
						<?php
								if ($MotoristasTotais != null) {
									echo "<th colspan='1'> $MotoristasTotais[jornadaPrevista]</th>";
									echo "<th colspan='1'> $MotoristasTotais[JornadaEfetiva]</th>";
									echo "<th colspan='1'>".(($MotoristasTotais['he50'] == '00:00') ? '' : $MotoristasTotais['he50'])."</th>";
									echo "<th colspan='1'> $MotoristasTotais[he100]</th>";
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
							echo "<td>" . (($MotoristaTotal['he50'] == '00:00') ? '' : $MotoristaTotal['he50']) . "</td>";
							echo "<td>" . (($MotoristaTotal['he100'] == '00:00') ? '' : $MotoristaTotal['he100']) . "</td>";
							echo "<td>" . (($MotoristaTotal['adicionalNoturno'] == '00:00') ? '' : $MotoristaTotal['adicionalNoturno']) . "</td>";
							echo "<td>" . (($MotoristaTotal['esperaIndenizada'] == '00:00') ? '' : $MotoristaTotal['esperaIndenizada']) . "</td>";
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
<script>
    function downloadCSV() {
        // Caminho do arquivo CSV no servidor
        var filePath = '<?="./arquivos/paineis/Painel_$MotoristasTotais[empresaNome].csv" ?>' // Substitua pelo caminho do seu arquivo

        // Cria um link para download
        var link = document.createElement('a');

        // Configurações do link
        link.setAttribute('href', filePath);
        link.setAttribute('download', '<?= "Painel_$MotoristasTotais[empresaNome].csv"?>');

        // Adiciona o link ao documento
        document.body.appendChild(link);

        // Simula um clique no link para iniciar o download
        link.click();

        // Remove o link
        document.body.removeChild(link);
    }
</script>