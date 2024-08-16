<?php
/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0");

$dataTimeInicio = new DateTime($_POST['busca_dataInicio']);
$dataTimeFim = new DateTime($_POST['busca_dataFim']);
$dataInicioFormatada = $dataTimeInicio->format('d/m/Y');
$dataFimFormatada = $dataTimeFim->format('d/m/Y');
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

	.totais {
		background-color: #ffe699;
	}

	tr.totais {
		text-align: justify;
	}

	.titulos {
		background-color: #99ccff;
	}

	#tituloRelatorio {
		display: none;
	}

	th {
		cursor: pointer;
	}

	th.sort-asc::after {
		content: " \2191";
	}

	th.sort-desc::after {
		content: " \2193";
	}
	#impressao{
        display: none;
    }
</style>
<div id="tituloRelatorio">
	<img style='width: 150px' src="<?= $aEmpresa[0]['empr_tx_logo'] ?>" alt="Logo Empresa Esquerda">
	<h3>Relatorio Geral de saldo</h3>
	<div class="right-logo">
		<img style='width: 150px' src="<?= $CONTEX['path'] ?>/imagens/logo_topo_cliente.png" alt="Logo Empresa Direita">
	</div>
</div>
<div class="col-md-12 col-sm-12" id="pdf2htmldiv">
	<div class="portlet light ">
		<div class="emissao">Emissão Doc.: <?= $Emissão ?></div>
		<div class="table-responsive">
			<div style="display: flex;">
				<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact" id="tabela1">
					<thead>
						<tr>
							<th colspan="1">SALDO FINAL</th>
							<th colspan="1">QUANT</th>
							<th colspan="1">%</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td class="porcentagenMeta" style="background-color: #66b3ff;">META</td>
							<td class="textCentralizado"><?= $quantMeta ?></td>
							<td><?= $perfomace['meta_endo'] ?></td>
						</tr>
						<tr>
							<td class='porcentagenPosit' style="background-color: #00b33c;">POSITIVO</td>
							<td class="textCentralizado"><?= $quantPosi ?></td>
							<td><?= $perfomace['posi_endoPc'] ?></td>
						</tr>
						<tr>
							<td class='porcentagenNegat' style="background-color: #ff471a;">NEGATIVO</td>
							<td class="textCentralizado"><?= $quantNega ?></td>
							<td><?= $perfomace['nega_naEndo']  ?></td>
						</tr>
					</tbody>
				</table>
			</div>
			<br>
			<div class="portlet-body form">
				<table id="tabela-empresas" class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact">
					<thead>
						<tr class="totais">
							<th colspan="1">Período: De <?= $dataInicioFormatada . ' até ' . $dataFimFormatada ?></th>
							<th></th>
							<?php
							if (
								isset($_POST['empresa']) && !empty($_POST['empresa']) && isset($_POST['busca_dataInicio']) && !empty($_POST['busca_dataInicio'])
								&& isset($_POST['busca_dataFim']) && !empty($_POST['busca_dataFim'])
							) {
								echo "<th colspan='1'>" . $motoristasTotais['jornadaPrevista'] . "</th>"
									. "<th colspan='1'>" . $motoristasTotais['JornadaEfetiva'] . "</th>"
									. "<th colspan='1'>" . $motoristasTotais['he50'] . "</th>"
									. "<th colspan='1'>" . $motoristasTotais['he100'] . "</th>"
									. "<th colspan='1'>" . $motoristasTotais['adicionalNoturno'] . "</th>"
									. "<th colspan='1'>" . $motoristasTotais['esperaIndenizada'] . "</th>"
									. "<th colspan='1'>" . $motoristasTotais['saldoAnterior'] . "</th>"
									. "<th colspan='1'>" . $motoristasTotais['saldoPeriodo'] . "</th>"
									. "<th colspan='1'>" . $motoristasTotais['saldoFinal'] . "</th>";
							} else {
								echo "<th colspan='1'> $empresas[EmprTotalJorPrev]</th>"
									. "<th colspan='1'> $empresas[EmprTotalJorEfe]</th>"
									. "<th colspan='1'> " . (($empresas['EmprTotalHE50'] == '00:00') ? '' : $empresas['EmprTotalHE50']) . "</th>"
									. "<th colspan='1'> " . (($empresas['EmprTotalHE100'] == '00:00') ? '' : $empresas['EmprTotalHE100']) . "</th>"
									. "<th colspan='1'> " . (($empresas['EmprTotalAdicNot'] == '00:00') ? '' : $empresas['EmprTotalAdicNot']) . "</th>"
									. "<th colspan='1'> " . (($empresas['EmprTotalEspInd'] == '00:00') ? '' : $empresas['EmprTotalEspInd']) . "</th>"
									. "<th colspan='1'> " . (($empresas['EmprTotalSaldoAnter'] == '00:00') ? '' : $empresas['EmprTotalSaldoAnter']) . "</th>"
									. "<th colspan='1'> " . (($empresas['EmprTotalSaldoPeriodo'] == '00:00') ? '' : $empresas['EmprTotalSaldoPeriodo']) . "</th>"
									. "<th colspan='1'> " . (($empresas['EmprTotalSaldoFinal'] == '00:00') ? '' : $empresas['EmprTotalSaldoFinal']) . "</th>";
							}
							?>
						</tr>
						<tr id='titulos' class="titulos">
							<?php

							if (
								isset($_POST['empresa']) && !empty($_POST['empresa']) && isset($_POST['busca_dataInicio']) && !empty($_POST['busca_dataInicio'])
								&& isset($_POST['busca_dataFim']) && !empty($_POST['busca_dataFim'])
							) {
								echo "<th data-column='motorista' data-order='asc'>Matricula</th>"
									. "<th data-column='motorista' data-order='asc'>Unidade -  $motoristasTotais[empresaNome]</th>"
									. "<th data-column='jornadaPrevista' data-order='asc'>Jornada Prevista</th>"
									. "<th data-column='jornadaEfetiva' data-order='asc'>Jornada Efetiva</th>"
									. "<th data-column='he50' data-order='asc'>HE 50%</th>"
									. "<th data-column='he100' data-order='asc'>HE 100%</th>"
									. "<th data-column='adicionalNoturno' data-order='asc'>Adicional Noturno</th>"
									. "<th data-column='esperaIndenizada' data-order='asc'>ESPERA INDENIZADA</th>"
									. "<th data-column='saldoAnterior' data-order='asc'>Saldo Anterior</th>"
									. "<th data-column='saldoPeriodo' data-order='asc'>Saldo Periodo</th>"
									. "<th data-column='saldoFinal' data-order='asc'>Saldo Final</th>";
							} else {
								echo "<th data-column='empresaNome' data-order='asc'>Todos os CNPJ</th>"
									. "<th data-column='totalMotorista' data-order='asc'>Quant. Motoristas</th>"
									. "<th data-column='jornadaPrevista' data-order='asc'>Jornada Prevista</th>"
									. "<th data-column='JornadaEfetiva	' data-order='asc'>Jornada Efetiva</th>"
									. "<th data-column='he50' data-order='asc'>HE 50%</th>"
									. "<th data-column='he100' data-order='asc'>HE 100%</th>"
									. "<th data-column='adicionalNoturno' data-order='asc'>Adicional Noturno</th>"
									. "<th data-column='esperaIndenizada' data-order='asc'>ESPERA INDENIZADA</th>"
									. "<th data-column='saldoAnterior' data-order='asc'>Saldo Anterior</th>"
									. "<th data-column='saldoPeriodo' data-order='asc'>Saldo Periodo</th>"
									. "<th data-column='saldoFinal' data-order='asc'>Saldo Final</th>";
							}

							?>
						</tr>
					</thead>
					<tbody>
						<!-- Conteúdo do json empresas será inserido aqui -->
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
	<div id="impressao">
		<b>Impressão Doc.:</b> <?= date("d/m/Y \T H:i:s") . " (UTC-3)" ?>
	</div>
	<script>
		$(document).ready(function() {
			var tabela = $('#tabela-empresas tbody');

			<?php
			if (
				isset($_POST['empresa']) && !empty($_POST['empresa']) && isset($_POST['busca_dataInicio']) && !empty($_POST['busca_dataInicio'])
				&& isset($_POST['busca_dataFim']) && !empty($_POST['busca_dataFim'])
			) {
				$url = "./arquivos/paineis/saldos/$idEmpresa/$mes-$ano/motoristas.json";
			} else {
				$url = "arquivos/paineis/saldos/empresas/$mes-$ano/totalEmpresas.json";
			}
			?>

			function carregarDados() {
				$.ajax({
					url: '<?= $url ?>',
					dataType: 'json',
					success: function(data) {
						tabela.empty();
						$.each(data, function(index, item) {
							console.log("Item " + index + ":");
							for (var chave in item) {
								if (item.hasOwnProperty(chave)) {
									console.log(chave + ": " + item[chave]);
								}
							}

							<?php
							if (
								isset($_POST['empresa']) && !empty($_POST['empresa']) && isset($_POST['busca_dataInicio']) && !empty($_POST['busca_dataInicio'])
								&& isset($_POST['busca_dataFim']) && !empty($_POST['busca_dataFim'])
							) {
								echo "var linha = '<tr>' +
									'<td>' + item.matricula + '</td>' +
									'<td>' + item.motorista + '</td>' +
									'<td>' + item.jornadaPrevista + '</td>' +
									'<td>' + item.jornadaEfetiva + '</td>' +
									'<td>' + item.he50 + '</td>' +
									'<td>' + item.he100 + '</td>' +
									'<td>' + item.adicionalNoturno + '</td>' +
									'<td>' + item.esperaIndenizada + '</td>' +
									'<td>' + item.saldoAnterior + '</td>' +
									'<td>' + item.saldoPeriodo + '</td>' +
									'<td>' + item.saldoFinal + '</td>' +
									'</tr>';";
							} else {
								echo "var he50 = (item.he50 === null || item.he50 === '00:00') ? '' : item.he50;
								var he100 = (item.he100 === null || item.he100 === '00:00') ? '' : item.he100;
								var adicionalNoturno = (item.adicionalNoturno === null || item.adicionalNoturno === '00:00') ? '' : item.adicionalNoturno;
								var esperaIndenizada = (item.esperaIndenizada === null || item.esperaIndenizada === '00:00') ? '' : item.esperaIndenizada;
								var saldoAnterior = (item.saldoAnterior === null || item.saldoAnterior === '00:00') ? '' : item.saldoAnterior;
								var saldoPeriodo = (item.saldoPeriodo === null || item.saldoPeriodo === '00:00') ? '' : item.saldoPeriodo;
								var saldoFinal = (item.saldoFinal === null || item.saldoFinal === '00:00') ? '' : item.saldoFinal;

								var linha = '<tr>' +
									'<td style=\'cursor: pointer;\' onclick=setAndSubmit(' + item.empresaId + ')>' +
									item.empresaNome + '</td>' +
									'<td>' + item.totalMotorista + '</td>' +
									'<td>' + item.jornadaPrevista + '</td>' +
									'<td>' + item.JornadaEfetiva + '</td>' +
									'<td>' + he50 + '</td>' +
									'<td>' + he100 + '</td>' +
									'<td>' + adicionalNoturno + '</td>' +
									'<td>' + esperaIndenizada + '</td>' +
									'<td>' + saldoAnterior + '</td>' +
									'<td>' + saldoPeriodo + '</td>' +
									'<td>' + saldoFinal + '</td>' +
									'</tr>';";
							}

							?>
							tabela.append(linha);
						});
					},
					error: function() {
						console.log('Erro ao carregar os dados.');
					}
				});
			}
			// Função para ordenar a tabela
			function ordenarTabela(coluna, ordem) {
				var linhas = tabela.find('tr').get();
				linhas.sort(function(a, b) {
					var valorA = $(a).children('td').eq(coluna).text().toUpperCase();
					var valorB = $(b).children('td').eq(coluna).text().toUpperCase();

					if (valorA < valorB) {
						return ordem === 'asc' ? -1 : 1;
					}
					if (valorA > valorB) {
						return ordem === 'asc' ? 1 : -1;
					}
					return 0;
				});
				$.each(linhas, function(index, row) {
					tabela.append(row);
				});
			}

			// Evento de clique para ordenar a tabela ao clicar no cabeçalho
			$('#titulos th').click(function() {
				var coluna = $(this).index();
				var ordem = $(this).data('order');
				$('#tabela-empresas th').data('order', 'desc'); // Redefinir ordem de todas as colunas
				$(this).data('order', ordem === 'desc' ? 'asc' : 'desc');
				ordenarTabela(coluna, $(this).data('order'));

				// Ajustar classes para setas de ordenação
				$('#titulos th').removeClass('sort-asc sort-desc');
				$(this).addClass($(this).data('order') === 'asc' ? 'sort-asc' : 'sort-desc');
			});

			carregarDados();
		});

		// function downloadCSV() {
		//     // Caminho do arquivo CSV no servidor
		//     var filePath = './arquivos/paineis/Painel_Geral.csv' // Substitua pelo caminho do seu arquivo

		//     // Cria um link para download
		//     var link = document.createElement('a');

		//     // Configurações do link
		//     link.setAttribute('href', filePath);
		//     link.setAttribute('download', 'Painel_Geral.csv');

		//     // Adiciona o link ao documento
		//     document.body.appendChild(link);

		//     // Simula um clique no link para iniciar o download
		//     link.click();

		//     // Remove o link
		//     document.body.removeChild(link);
		// }
	</script>