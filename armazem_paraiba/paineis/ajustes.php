<?php
/* Modo debug
	ini_set("display_errors", 1);
	error_reporting(E_ALL);
//*/
header("Expires: 01 Jan 2001 00:00:00 GMT");
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header('Cache-Control: post-check=0, pre-check=0', FALSE);
header('Pragma: no-cache');

require_once __DIR__ . "/funcoes_paineis.php";
require __DIR__ . "/../funcoes_ponto.php";

function enviarForm() {
	$_POST["acao"] = $_POST["campoAcao"];
	index();
}

function carregarGraficos($periodoInicio) {
	$path = "./arquivos/ajustes";
	$mesAnterior = clone $periodoInicio; // Clona o objeto original
	$mesAnterior->modify("-1 month");    // Modifica apenas a cópia

	$mesAnteanterior = clone $periodoInicio;
	$mesAnteanterior->modify("-2 months");

	$pathMesAtual = $path ."/" . $periodoInicio->format("Y-m") . "/" . $_POST["empresa"] . "/empresa_" . $_POST["empresa"] . ".json"; // mês atual
	$pathMesAnterior = $path ."/" . $mesAnterior->format("Y-m") . "/" . $_POST["empresa"] . "/empresa_" . $_POST["empresa"] . ".json"; // mês anterior
	$pathMesAnteanterior = $path ."/" . $mesAnteanterior->format("Y-m") . "/" . $_POST["empresa"] . "/empresa_" . $_POST["empresa"] . ".json"; // mês anterior do anterior

	if (file_exists($pathMesAtual)) {
		$jsonMesAtual = json_decode(file_get_contents($pathMesAtual), true);
	}

	if (file_exists($pathMesAnterior)) {
		$jsonMesAnterior = json_decode(file_get_contents($pathMesAnterior), true);
	} else {
		$jsonMesAnterior = [
			"totais" => [
				"ativo" => 0,
				"inativo" => 0
			]
		];
		// $jsonMesAnteanterior = json_encode($jsonMesAnterior);
	};

	if (file_exists($pathMesAnteanterior)) {
		$jsonMesAnteanterior = json_decode(file_get_contents($pathMesAnteanterior), true);
	} else {
		$jsonMesAnteanterior = [
			"totais" => [
				"ativo" => 0,
				"inativo" => 0
			]
		];
		// $jsonMesAnteanterior = json_encode($jsonMesAnteanterior);
	}

	echo "
	<script>
		const mesesPTBR = {
            '01': 'Jan', '02': 'Fev', '03': 'Mar', '04': 'Abr', 
            '05': 'Mai', '06': 'Jun', '07': 'Jul', '08': 'Ago', 
            '09': 'Set', '10': 'Out', '11': 'Nov', '12': 'Dez'
        };

		const meses = [
            '".$mesAnteanterior->format("Y-m")."',
			'".$mesAnterior->format("Y-m")."', 
            '".$periodoInicio->format("Y-m")."', 
        ];

		const totaisPorMes = {
			'".$mesAnteanterior->format("Y-m")."':{
				ativo:".$jsonMesAnteanterior["totais"]["ativo"].",
				inativo:".$jsonMesAnteanterior["totais"]["inativo"]."
			},
			'".$mesAnterior->format("Y-m")."':{
				ativo:".$jsonMesAnterior["totais"]["ativo"].",
				inativo:".$jsonMesAnterior["totais"]["inativo"]."
			},
			'".$periodoInicio->format("Y-m")."':{
				ativo:".$jsonMesAtual["totais"]["ativo"].",
				inativo:".$jsonMesAtual["totais"]["inativo"]."
			}
		};

		const mesesFormatados = meses.map(mes => {
            const [ano, mesNum] = mes.split('-');
            return `\${mesesPTBR[mesNum]}/\${ano}`;
        });

		const commonOptions = {
            chart: { type: 'line',
			style: {
                    fontFamily: 'Arial',
                    fontSize: '12px'
                }
			},
            xAxis: {
                categories: mesesFormatados, // Usa os meses formatados
                title: { text: 'Mês' }
            },
            yAxis: { 
                title: { text: 'Quantidade' },
                min: 0
            },
            legend: { enabled: false },
            tooltip: {
                formatter: function() {
                    const mesOriginal = meses[this.point.index];
                    const [ano, mesNum] = mesOriginal.split('-');
                    const mesExtenso = [
                        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
                    ][parseInt(mesNum)-1];
                    return `<b>\${mesExtenso} \${ano}</b><br/>\${this.series.name}: <b>\${this.y}</b> ajustes`;
                }
            },
            plotOptions: {
                line: {
                    dataLabels: { 
                        enabled: true,
                        formatter: function() {
                            return this.y; // Mostra apenas o número
                        }
                    },
                    marker: { enabled: true }
                }
            }
        };

		Highcharts.chart('chart-ativos', {
            ...commonOptions,
            title: { text: 'Ajustes Ativos - Últimos 3 Meses' },
            series: [{
                name: 'Ativos',
                data: meses.map(mes => totaisPorMes[mes]?.ativo || 0),
                color: '#4CAF50',
                marker: { symbol: 'circle' }
            }]
        });

		Highcharts.chart('chart-inativos', {
            ...commonOptions,
            title: { text: 'Ajustes Inativos - Últimos 3 Meses' },
            series: [{
                name: 'Inativos',
                data: meses.map(mes => totaisPorMes[mes]?.inativo || 0),
                color: '#FF4D4D',
                marker: { symbol: 'circle' }
            }]
        });

	</script>
	"
	;
}

function carregarJS(array $arquivos) {
	// $periodoInicio = new DateTime($_POST["busca_periodo"][0]);
	$dominiosAutotrac = ["/comav"];
	$linha = "linha = '<tr>'";
	if (!empty($_POST["empresa"])) {
		if (!in_array($_SERVER["REQUEST_URI"], $dominiosAutotrac)) {
			$linha .= "+'<td>'+row.matricula+'</td>'
						+'<td>'+row.nome+'</td>'
						+'<td>'+row.ocupacao+'</td>'
						+'<td>' +(row['Inicio de Jornada']?.['ativo'] === 0 ? '' : row['Inicio de Jornada']?.['ativo']) +'</td>'
						+'<td>'+(row['Inicio de Jornada']?.['inativo'] === 0 ? '' : row['Inicio de Jornada']?.['inativo'])+'</td>'
						+'<td>'+(row['Fim de Jornada']?.['ativo'] === 0 ? '' : row['Fim de Jornada']?.['ativo'])+'</td>'
						+'<td>'+(row['Fim de Jornada']?.['inativo'] === 0 ? '' : row['Fim de Jornada']?.['inativo'])+'</td>'
						+'<td>'+(row['Inicio de Refeição']?.['ativo'] === 0 ? '' : row['Inicio de Refeição']?.['ativo'])+'</td>'
						+'<td>'+(row['Inicio de Refeição']?.['inativo'] === 0 ? '' : row['Inicio de Refeição']?.['inativo'])+'</td>'
						+'<td>'+(row['Fim de Refeição']?.['ativo'] === 0 ? '' : row['Fim de Refeição']?.['ativo'])+'</td>'
						+'<td>'+(row['Fim de Refeição']?.['inativo'] === 0 ? '' : row['Fim de Refeição']?.['inativo'])+'</td>'
						+'<td>'+(row['Inicio de Espera']?.['ativo'] === 0 ? '' : row['Inicio de Espera']?.['ativo'])+'</td>'
						+'<td>'+(row['Inicio de Espera']?.['inativo'] === 0 ? '' : row['Inicio de Espera']?.['inativo'])+'</td>'
						+'<td>'+(row['Fim de Espera']?.['ativo'] === 0 ? '' : row['Fim de Espera']?.['ativo'])+'</td>'
						+'<td>'+(row['Fim de Espera']?.['inativo'] === 0 ? '' : row['Fim de Espera']?.['inativo'])+'</td>'
						+'<td>'+(row['Inicio de Descanso']?.['ativo'] === 0 ? '' : row['Inicio de Descanso']?.['ativo'])+'</td>'
						+'<td>'+(row['Inicio de Descanso']?.['inativo'] === 0 ? '' : row['Inicio de Descanso']?.['inativo'])+'</td>'
						+'<td>'+(row['Fim de Descanso']?.['ativo'] === 0 ? '' : row['Fim de Descanso']?.['ativo'])+'</td>'
						+'<td>'+(row['Fim de Descanso']?.['inativo'] === 0 ? '' : row['Fim de Descanso']?.['inativo'])+'</td>'
						+'<td>'+(row['Inicio de Repouso']?.['ativo'] === 0 ? '' : row['Inicio de Repouso']?.['ativo'])+'</td>'
						+'<td>'+(row['Inicio de Repouso']?.['inativo'] === 0 ? '' : row['Inicio de Repouso']?.['inativo'])+'</td>'
						+'<td>'+(row['Fim de Repouso']?.['ativo'] === 0 ? '' : row['Fim de Repouso']?.['ativo'])+'</td>'
						+'<td>'+(row['Fim de Repouso']?.['inativo'] === 0 ? '' : row['Fim de Repouso']?.['inativo'])+'</td>'
						+'<td>'+(row['Inicio de Repouso Embarcado']?.['ativo'] === 0 ? '' : row['Inicio de Repouso Embarcado']?.['ativo'])+'</td>'
						+'<td>'+(row['Inicio de Repouso Embarcado']?.['inativo'] === 0 ? '' : row['Inicio de Repouso Embarcado']?.['inativo'])+'</td>'
						+'<td>'+(row['Fim de Repouso Embarcado']?.['ativo'] === 0 ? '' : row['Fim de Repouso Embarcado']?.['ativo'])+'</td>'
						+'<td>'+(row['Fim de Repouso Embarcado']?.['inativo'] === 0 ? '' : row['Fim de Repouso Embarcado']?.['inativo'])+'</td>'
						+'<td>'+totalAtivo+'</td>'
						+'<td>'+totalInativo+'</td>'
						+'<td>'+(totalInativo+totalAtivo)+'</td>'
						+'</tr>';";
		} else {
			$linha .= "+'<td>'+row['Inicio de Jornada']+'</td>'
				+'<td>'+row['Fim de Jornada']+'</td>'
				+'<td>'+row['Inicio de Refeição']+'</td>'
				+'<td>'+row['Fim de Refeição']+'</td>'
				+'<td>'+row['Inicio de Espera']+'</td>'
				+'<td>'+row['Fim de Espera']+'</td>'
				+'<td>'+row['Inicio de Descanso']+'</td>'
				+'<td>'+row['Fim de Descanso']+'</td>'
				+'<td>'+row['Inicio de Repouso']+'</td>'
				+'<td>'+row['Fim de Repouso']+'</td>'
				+'<td>'+row['Inicio de Repouso Embarcado']+'</td>'
				+'<td>'+row['Fim de Repouso Embarcado']+'</td>'
				+'<td>'+row['Pernoite - Fim De Jornada']+'</td>'
				+'<td>'+row['Refeicao']+'</td>'
				+'<td>'+row['Em Espera']+'</td>'
				+'<td>'+row['Descanso']+'</td>'
				+'<td>'+row['Reinicio De Viagem']+'</td>'
				+'<td>'+row['Inicio De Viagem']+'</td>'
				+'<td>'+row['Fim De Viagem']+'</td>'
				+'<td>'+row['Parada Eventual']+'</td>'
				+'<td>'+row['Sol De Desvio De Rota']+'</td>'
				+'<td>'+row['Sol Desengate/Bau']+'</td>'
				+'<td>'+row['Manutencao']+'</td>'
				+'<td>'+row['Macro Msg Livre']+'</td>'
				+'<td>'+row['Ag Descarga']+'</td>'
				+'<td>'+row['Abastecimento - Arla32']+'</td>'
				+'<td>'+row['Troca De Veículo']+'</td>';";
		}
	} else {
		if (!in_array($_SERVER["REQUEST_URI"], $dominiosAutotrac)) {
			$linha .= "+'<td class=\"nomeEmpresa\" style=\"cursor: pointer;\" onclick=\"setAndSubmit(' + row.empr_nb_id + ')\">'+row.empr_tx_nome+'</td>'
						+'<td>'+row.qtdMotoristas+'</td>'
						+'<td>'+row['Inicio de Jornada']+'</td>'
						+'<td>'+row['Fim de Jornada']+'</td>'
						+'<td>'+row['Inicio de Refeição']+'</td>'
						+'<td>'+row['Fim de Refeição']+'</td>'
						+'<td>'+row['Inicio de Espera']+'</td>'
						+'<td>'+row['Fim de Espera']+'</td>'
						+'<td>'+row['Inicio de Descanso']+'</td>'
						+'<td>'+row['Fim de Descanso']+'</td>'
						+'<td>'+row['Inicio de Repouso']+'</td>'
						+'<td>'+row['Fim de Repouso']+'</td>'
						+'<td>'+row['Inicio de Repouso Embarcado']+'</td>'
						+'<td>'+row['Fim de Repouso Embarcado']+'</td>'
					+'</tr>';";
		} else {
			$linha .= "+'<td class=\"nomeEmpresa\" style=\"cursor: pointer;\" onclick=\"setAndSubmit(' + row.empr_nb_id + ')\">'+row.empr_tx_nome+'</td>'
				+'<td>'+row.qtdMotoristas+'</td>'
				+'<td>'+row['Inicio de Jornada']+'</td>'
				+'<td>'+row['Fim de Jornada']+'</td>'
				+'<td>'+row['Inicio de Refeição']+'</td>'
				+'<td>'+row['Fim de Refeição']+'</td>'
				+'<td>'+row['Inicio de Espera']+'</td>'
				+'<td>'+row['Fim de Espera']+'</td>'
				+'<td>'+row['Inicio de Descanso']+'</td>'
				+'<td>'+row['Fim de Descanso']+'</td>'
				+'<td>'+row['Inicio de Repouso']+'</td>'
				+'<td>'+row['Fim de Repouso']+'</td>'
				+'<td>'+row['Inicio de Repouso Embarcado']+'</td>'
				+'<td>'+row['Fim de Repouso Embarcado']+'</td>'
				+'<td>'+row['Pernoite - Fim De Jornada']+'</td>'
				+'<td>'+row['Refeicao']+'</td>'
				+'<td>'+row['Em Espera']+'</td>'
				+'<td>'+row['Descanso']+'</td>'
				+'<td>'+row['Reinicio De Viagem']+'</td>'
				+'<td>'+row['Inicio De Viagem']+'</td>'
				+'<td>'+row['Fim De Viagem']+'</td>'
				+'<td>'+row['Parada Eventual']+'</td>'
				+'<td>'+row['Sol De Desvio De Rota']+'</td>'
				+'<td>'+row['Sol Desengate/Bau']+'</td>'
				+'<td>'+row['Manutencao']+'</td>'
				+'<td>'+row['Macro Msg Livre']+'</td>'
				+'<td>'+row['Ag Descarga']+'</td>'
				+'<td>'+row['Abastecimento - Arla32']+'</td>'
				+'<td>'+row['Troca De Veículo']+'</td>';";
		}
	}

	$carregarDados = "";
	foreach ($arquivos as $arquivo) {
		$carregarDados .= "carregarDados('{$arquivo}');";
	}

	echo
	"<form name='myForm' method='post' action='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "'>
                <input type='hidden' name='acao'>
                <input type='hidden' name='atualizar'>
                <input type='hidden' name='campoAcao'>
                <input type='hidden' name='empresa'>
                <input type='hidden' name='busca_periodo[]' id='busca_inicio'>
				<input type='hidden' name='busca_periodo[]' id='busca_fim'>
            </form>
            <script>
                function setAndSubmit(empresa){
                    document.myForm.acao.value = 'enviarForm()';
                    document.myForm.campoAcao.value = 'buscar';
                    document.myForm.empresa.value = empresa;
                    const buscaPeriodoInput = document.getElementById('busca_periodo');
					const [inicio, fim] = buscaPeriodoInput.value.split(' - '); 
					 function formatDate(date) {
						const [day, month, year] = date.split('/');
						return year + '-' + month + '-' + day;
					}
					document.getElementById('busca_inicio').value = formatDate(inicio);
					document.getElementById('busca_fim').value = formatDate(fim);
                    document.myForm.submit();
                }

			function imprimir(){
				window.print();
			}
		
			$(document).ready(function(){
				var tabela = $('#tabela-empresas tbody');

				function carregarDados(urlArquivo){
					$.ajax({
						url: urlArquivo + '?v=' + new Date().getTime(),
						dataType: 'json',
						success: function(data){
							var row = {};
							$.each(data, function(index, item){
								row[index] = item;
							});

						let totalAtivo = row['Inicio de Jornada']['ativo'] + row['Fim de Jornada']['ativo'] + row['Inicio de Refeição']['ativo']
						+ row['Fim de Refeição']['ativo'] + row['Inicio de Espera']['ativo'] + row['Fim de Espera']['ativo'] + row['Inicio de Descanso']['ativo']
						+ row['Fim de Descanso']['ativo'] + row['Inicio de Repouso']['ativo'] + row['Fim de Repouso']['ativo']
						+ row['Inicio de Repouso Embarcado']['ativo'] + row['Fim de Repouso Embarcado']['ativo'];

						let totalInativo = row['Inicio de Jornada']['inativo'] + row['Fim de Jornada']['inativo'] + row['Inicio de Refeição']['inativo']
						+ row['Fim de Refeição']['inativo'] + row['Inicio de Espera']['inativo'] + row['Fim de Espera']['inativo']
						+ row['Inicio de Descanso']['inativo'] + row['Fim de Descanso']['inativo'] + row['Inicio de Repouso']['inativo']
						+ row['Fim de Repouso']['inativo'] + row['Inicio de Repouso Embarcado']['inativo'] + row['Fim de Repouso Embarcado']['inativo'];
							// console.log(totalInativo);
							{$linha}
							tabela.append(linha);
						},
						error: function(){
							console.log('Erro ao carregar os dados.');
						}
					});
				}
				// Função para ordenar a tabela
				function ordenarTabela(coluna, ordem) {
					var linhas = tabela.find('tr:not(.titulos, .titulos2)').get(); // Ignorar cabeçalhos na ordenação

					linhas.sort(function (a, b) {
						var valorA = $(a).children('td').eq(coluna).text().trim() || '0'; // Substituir vazio por '0'
						var valorB = $(b).children('td').eq(coluna).text().trim() || '0'; // Substituir vazio por '0'

						// Tentar converter para número
						var numA = parseFloat(valorA);
						var numB = parseFloat(valorB);

						// Comparação numérica
						if (!isNaN(numA) && !isNaN(numB)) {
							return ordem === 'asc' ? numA - numB : numB - numA;
						}

						// Comparação alfabética como fallback
						if (valorA < valorB) {
							return ordem === 'asc' ? -1 : 1;
						}
						if (valorA > valorB) {
							return ordem === 'asc' ? 1 : -1;
						}
						return 0;
					});

					$.each(linhas, function (index, row) {
						tabela.append(row);
					});
				}

				// Evento de clique para ordenar ao clicar nos cabeçalhos do #titulos
				$('#titulos th').click(function () {
					var colunaIndex = $(this).index(); // Índice visual da coluna
					var colspan = parseInt($(this).attr('colspan')) || 1; // Colspan, padrão é 1
					var coluna = 0;

					// Calcular o índice da coluna correspondente
					$('#titulos th').each(function (index) {
						if (index < colunaIndex) {
							coluna += parseInt($(this).attr('colspan')) || 1;
						}
					});

					// Atualizar a ordem de todas as colunas
					var ordem = $(this).data('order') || 'asc';
					$(this).data('order', ordem === 'desc' ? 'asc' : 'desc');

					ordenarTabela(coluna, $(this).data('order'));

					// Atualizar classes de setas para ordenação visual
					$('#titulos th').removeClass('sort-asc sort-desc');
					$(this).addClass($(this).data('order') === 'asc' ? 'sort-asc' : 'sort-desc');
				});

				{$carregarDados}
			});

			//Variação dos campos de pesquisa{
				var camposAcao = document.getElementsByName('campoAcao');
				if (camposAcao[0].checked){
					document.getElementById('botaoContexBuscar').innerHTML = 'Buscar';
				}
				if (camposAcao[1].checked){
					document.getElementById('botaoContexBuscar').innerHTML = 'Atualizar';
				}
				camposAcao[0].addEventListener('change', function() {
					if (camposAcao[0].checked){
						document.getElementById('botaoContexBuscar').innerHTML = 'Buscar';
					}
				});
				camposAcao[1].addEventListener('change', function() {
					if (camposAcao[1].checked){
						document.getElementById('botaoContexBuscar').innerHTML = 'Atualizar';
					}
				});
			//}

			function createModal(motivo,pontos) {

				// Calcular totais
				const totalFuncionarios = Object.keys(pontos).length;
				const totalOcorrencias = Object.values(pontos).reduce((sum, ponto) => sum + ponto.quantidade, 0);

				let modalContent = `
				<div class=\"table-responsive\">
					<table class=\"table table-bordered table-hover\">
						<thead>
						<tr>
							<td><strong>Total Funcionários:</strong></td>
							<td>\${totalFuncionarios}</td>
							<td><strong>Total Ocorrências:</strong></td>
							<td>\${totalOcorrencias}</td>
						</tr>
							<tr>
								<th>matrícula</th>
								<th>Ocupação</th>
								<th>Funcionário</th>
								<th>Quantidade</th>
							</tr>
						</thead>
						<tbody>`;
				   Object.keys(pontos).sort((a, b) => {
						const nomeA = pontos[a].funcionario.nome || '';
						const nomeB = pontos[b].funcionario.nome || '';
						return nomeA.localeCompare(nomeB);
					}).forEach(id => {
						const ponto = pontos[id];
						modalContent += `
							<tr>
								<td>\${ponto.funcionario.matricula}</td>
								<td>\${ponto.funcionario.ocupacao}</td>
								<td>\${ponto.funcionario.nome || 'N/A'}</td>
								<td>\${ponto.quantidade}</td>
							</tr>`;
					});

				modalContent += `
									</tbody>
								</table>
							</div>`;

				const modalHtml = 
					'<div class=\"modal fade\" id=\"dynamicModal\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"dynamicModalLabel\">' +
					'<div class=\"modal-dialog\" role=\"document\">' +
						'<div class=\"modal-content\">' +
						'<div class=\"modal-header\">' +
							'<button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"Close\">' +
							'<span aria-hidden=\"true\">&times;</span>' +
							'</button>' +
							'<h3 class=\"modal-title\" id=\"dynamicModalLabel\">' +
							'Funcionários com o motivo de ajustes ativos: '+motivo+ 
							'</h3>' +
						'</div>' +
						'<div class=\"modal-body\">' +
							modalContent +
						'</div>' +
						'<div class=\"modal-footer\">' +
							'<button type=\"button\" class=\"btn btn-primary\" onclick=\"enviarDados(\''+motivo+'\')\">Imprimir PDF</button>' +
                        	'<button type=\"button\" class=\"btn btn-success\" onclick=\"enviarDados(\''+motivo+'\', \'csv\')\">Exportar CSV</button>' +
							'<button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">Fechar</button>' +
						'</div>' +
						'</div>' +
					'</div>' +
					'</div>';
				
				// Adicionar o modal ao body
				$('body').append(modalHtml);

				// Exibir o modal
				$('#dynamicModal').modal('show');

				// Remover o modal do DOM ao fechar
				$('#dynamicModal').on('hidden.bs.modal', function () {
					$(this).remove();
				});
			}

			function createModal2(motivos) {
				// Cria o conteúdo principal do modal
				let modalContent = '';
				
				// Para cada motivo no objeto
				for (let motivo in motivos) {
					const pontos = motivos[motivo];
					
					// Calcular totais para este motivo
					const totalFuncionarios = Object.keys(pontos).length;
					const totalOcorrencias = Object.values(pontos).reduce((sum, ponto) => sum + ponto.quantidade, 0);

					// Adiciona título do motivo
					modalContent += `
					<div class=\"table-responsive\">
						<table class=\"table table-bordered table-hover\">
							<thead>
							<tr>
								<th colspan=\"4\" style=\"font-size: 1.1em; padding: 10px;\">
									Motivo: \${motivo}
								</th>
							</tr>
							<tr>
								<td><strong>Total Funcionários:</strong></td>
								<td>\${totalFuncionarios}</td>
								<td><strong>Total Ocorrências:</strong></td>
								<td>\${totalOcorrencias}</td>
							</tr>
								<tr>
									<th>Matrícula</th>
									<th>Ocupação</th>
									<th>Funcionário</th>
									<th>Quantidade</th>
								</tr>
							</thead>
							<tbody>`;
					
					// Ordena por nome do funcionário e adiciona linhas
					Object.keys(pontos).sort((a, b) => {
						const nomeA = pontos[a].funcionario.nome || '';
						const nomeB = pontos[b].funcionario.nome || '';
						return nomeA.localeCompare(nomeB);
					}).forEach(id => {
						const ponto = pontos[id];
						modalContent += `
							<tr>
								<td>\${ponto.funcionario.matricula}</td>
								<td>\${ponto.funcionario.ocupacao}</td>
								<td>\${ponto.funcionario.nome || 'N/A'}</td>
								<td>\${ponto.quantidade}</td>
							</tr>`;
					});

					modalContent += `
									</tbody>
								</table>
							</div>`;
				}

				// Estrutura do modal
				const modalHtml = 
					'<div class=\"modal fade\" id=\"dynamicModal\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"dynamicModalLabel\">' +
					'<div class=\"modal-dialog modal-lg\" role=\"document\">' +
						'<div class=\"modal-content\">' +
						'<div class=\"modal-header\">' +
							'<button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"Close\">' +
							'<span aria-hidden=\"true\">&times;</span>' +
							'</button>' +
							'<h3 class=\"modal-title\" id=\"dynamicModalLabel\">' +
							'Relatório Completo de Ajustes de Ponto Ativos' + 
							'</h3>' +
						'</div>' +
						'<div class=\"modal-body\" style=\"max-height: 70vh; overflow-y: auto;\">' +
							modalContent +
						'</div>' +
						'<div class=\"modal-footer\">' +
							'<button type=\"button\" class=\"btn btn-primary\" onclick=\"enviarDados(null)\">Imprimir PDF</button>' +
							'<button type=\"button\" class=\"btn btn-success\" onclick=\"enviarDados(null, \'csv\')\">Exportar CSV</button>' +
							'<button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">Fechar</button>' +
						'</div>' +
						'</div>' +
					'</div>' +
					'</div>';
				
				// Adicionar o modal ao body
				$('body').append(modalHtml);

				// Exibir o modal
				$('#dynamicModal').modal('show');

				// Remover o modal do DOM ao fechar
				$('#dynamicModal').on('hidden.bs.modal', function () {
					$(this).remove();
				});
			}

			function enviarDados(motivo,tipo) {
				var data = '" . json_encode($_POST["busca_periodo"]) . "'
				var form = document.createElement('form');
				form.method = 'POST';
				form.action = 'ajustes_export.php'; // Página que receberá os dados
				form.target = '_blank'; // Abre em nova aba

				// Criando campo 1
				var input1 = document.createElement('input');
				input1.type = 'hidden';
				input1.name = 'empresa';
				input1.value = $_POST[empresa]; // Valor do primeiro campo
				form.appendChild(input1);

				// Criando campo 2
				var input2 = document.createElement('input');
				input2.type = 'hidden';
				input2.name = 'busca_periodo';
				input2.value = data; // Valor do segundo campo
				form.appendChild(input2);

				// Criando campo 3
				var input2 = document.createElement('input');
				input2.type = 'hidden';
				input2.name = 'motivo';
				input2.value = motivo; // Valor do segundo campo
				form.appendChild(input2);

				// Criando campo 3
				var input2 = document.createElement('input');
				input2.type = 'hidden';
				input2.name = 'export';
				input2.value = tipo; // Valor do segundo campo
				form.appendChild(input2);

				document.body.appendChild(form);
				form.submit();
				document.body.removeChild(form);
			}

		</script>";
}

function index() {
	$dominiosAutotrac = ["/comav"];
	if (!empty($_POST["acao"])) {
		if ($_POST["busca_dataMes"] > date("Y-m")) {
			unset($_POST["acao"]);
			$_POST["errorFields"][] = "busca_dataMes";
			set_status("ERRO: Insira um mês menor ou igual ao atual. (" . date("m/Y") . ")");
			cabecalho("Relatório Geral de Saldo");
		} elseif ($_POST["acao"] == "atualizarPainel") {
			echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
			ob_flush();
			flush();

			criar_relatorio_ajustes();
			//Este comando de cabecalho deve ficar entre o alert() e a chamada de criar_relatorio_saldo() para notificar e aparecer o ícone de carregamento antes de começar o processamento
			cabecalho("Relatório Geral de Ajustes");
		} else {
			cabecalho("Relatório Geral de Ajustes");
		}
	} else {
		cabecalho("Relatório Geral de Ajustes");
	}

	// $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
	//position: absolute; top: 101px; left: 420px;

	$campoAcao =
		"<div class='col-sm-2 margin-bottom-5' style='min-width: fit-content;'>
				<label>Ação</label><br>
				<label class='radio-inline'>
					<input type='radio' name='campoAcao' value='buscar' " . ((empty($_POST["campoAcao"]) || $_POST["campoAcao"] == "buscar") ? "checked" : "") . "> Buscar
				</label>
				<label class='radio-inline'>
          			<input type='radio' name='campoAcao' value='atualizarPainel'" . (!empty($_POST["campoAcao"]) && $_POST["campoAcao"] == "atualizarPainel" ? "checked" : "") . "> Atualizar
				</label>
			</div>";

	$campos = [
		combo_net("Empresa", "empresa", $_POST["empresa"] ?? "", 4, "empresa", ""),
		$campoAcao,
		campo("Período", "busca_periodo",
			(!empty($_POST["busca_periodo"]) ? $_POST["busca_periodo"] : [date("Y-m-01"), date("Y-m-d")]),
			2, "MASCARA_PERIODO"),
		combo("Ocupação", "busca_ocupacao", ($_POST["busca_ocupacao"] ?? ""), 2,
		["" => "Todos", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário"]),
	];

	$botao_volta = "";
	if (!empty($_POST["empresa"])) {
		$botao_volta = "<button class='btn default' type='button' onclick='setAndSubmit(\"\")'>Voltar</button>";
	}
	$botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";

	$buttons = [
		botao("Buscar", "enviarForm()", "", "", "", "", "btn btn-info"),
		$botao_imprimir,
		$botao_volta
	];

	echo abre_form();
	echo linha_form($campos);
	echo fecha_form($buttons);

	$arquivos = [];
	$resultado = [];
	$resultado2 = [];
	$totais = [];
	$dataEmissao = ""; //Utilizado no HTML
	$encontrado = false;
	$path = "./arquivos/ajustes";
	$periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

	if (!empty($_POST["empresa"]) && !empty($_POST["busca_periodo"])) {
		$periodoInicio = new DateTime($_POST["busca_periodo"][0]);
		$path .= "/" . $periodoInicio->format("Y-m") . "/" . $_POST["empresa"];
		if (is_dir($path) && file_exists($path . "/empresa_" . $_POST["empresa"] . ".json")) {
			$encontrado = true;
			$dataArquivo = date("d/m/Y", filemtime($path . "/empresa_" . $_POST["empresa"] . ".json"));
			$horaArquivo = date("H:i", filemtime($path . "/empresa_" . $_POST["empresa"] . ".json"));

			$dataAtual = date("d/m/Y");
			$horaAtual = date("H:i");
			if ($dataArquivo != $dataAtual) {
				$alertaEmissao = "<span style='color: red; border: 2px solid; padding: 2px; border-radius: 4px;'>
                        <i style='color:red; margin-right: 5px;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
			} else {
				// Datas iguais: compara as horas
				// if ($horaArquivo < $horaAtual) {
				//     $alertaEmissao = "<i style='color:red;' title='As informações do painel podem estar desatualizadas.' class='fa fa-warning'></i>";
				// } else {
				$alertaEmissao = "<span>";
				// }
			}
			$dataEmissao = $alertaEmissao . " Atualizado em: " . date("d/m/Y H:i", filemtime($path . "/empresa_" . $_POST["empresa"] . ".json")) . "</span>";
			$arquivoGeral = json_decode(file_get_contents($path . "/empresa_" . $_POST["empresa"] . ".json"), true);

			$periodoRelatorio = [
				"dataInicio" => $arquivoGeral["dataInicio"],
				"dataFim" => $arquivoGeral["dataFim"]
			];

			$pastaAjuste = dir($path);
			$totais = []; // Inicializa vazio, será preenchido dinamicamente

			// Chaves que devem ser ignoradas
			$chavesIgnorar = ["matricula", "nome", "ocupacao", "pontos"];
			while ($arquivo = $pastaAjuste->read()) {
				if (!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
					$arquivo = $path . "/" . $arquivo;
					$arquivos[] = $arquivo;
					$json = json_decode(file_get_contents($arquivo), true);

					// Processa cada chave do JSON
					foreach ($json as $chave => $valor) {
						// Ignora as chaves especificadas
						if (in_array($chave, $chavesIgnorar)) {
							continue;
						}

						// Verifica se é um tipo de ponto válido
						if (is_array($valor) && isset($valor['ativo']) && isset($valor['inativo'])) {
							// Inicializa a chave no array de totais se não existir
							if (!isset($totais[$chave])) {
								$totais[$chave] = ['ativo' => 0, 'inativo' => 0];
							}

							// Soma os valores (com conversão para inteiro para segurança)
							$totais[$chave]['ativo'] += (int)$valor['ativo'];
							$totais[$chave]['inativo'] += (int)$valor['inativo'];
						}
					}
					foreach ($json['pontos'] as $key) {
						// Filtra apenas pontos com status "ativo" (case-insensitive)
						if (strtolower($key['pont_tx_status'] ?? '') !== 'ativo') {
							continue; // Pula se não for "ativo"
						}

						// Define o motivo (considera null como válido)
						$motivo = $key['moti_tx_nome'] ?? 'MOTIVO_NAO_INFORMADO'; // Opção 1: Substitui null
						// $motivo = $key['moti_tx_nome']; // Opção 2: Mantém null (se preferir)

						// Contagem geral por motivo
						if (!isset($resultado[$motivo])) {
							$resultado[$motivo] = 0;
						}
						$resultado[$motivo]++;

						// Agrupamento por motivo e funcionário
						if (!isset($resultado2[$motivo])) {
							$resultado2[$motivo] = [];
						}

						$dadosFunc = [
							"matricula" => $json["matricula"] ?? 'SEM_MATRICULA',
							"nome" => $json["nome"] ?? 'NOME_NAO_INFORMADO',
							"ocupacao" => $json["ocupacao"] ?? 'OCUPACAO_NAO_INFORMADA'
						];

						$funcionarioKey = $dadosFunc['matricula'] ?? md5($dadosFunc['nome']);

						if (!isset($resultado2[$motivo][$funcionarioKey])) {
							$resultado2[$motivo][$funcionarioKey] = [
								'funcionario' => $dadosFunc,
								'quantidade' => 0
							];
						}
						$resultado2[$motivo][$funcionarioKey]['quantidade']++;
					}

					$empresas[] = $json;
				}
			}
			$pastaAjuste->close();

			if (!empty($arquivos)) {
				$encontrado = true;
			}

			$tabelaMotivo = "
			<div style='display: flex; flex-direction: column;'>
				<div class='row' id='resumo'>
					<div class='col-md-4.5'>
						<table id='tabela-motivo'
							class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact'
							style='width: 500px !important;'>
							<thead>
								<tr>
									<th colspan='4' style='text-align: center;'><b>Ajustes Ativos</b></th>
							</thead>
							<thead>
								<tr>
									<th>Motivo</th>
									<th>Quantidade</th>
									<th>Funcionários</th>
									<th style='text-align: center;'><button class='btn btn-default btn-sm' onclick=\"createModal2("
											. htmlspecialchars(json_encode($resultado2), ENT_QUOTES, 'UTF-8' ) . ");\" )'>Visualizar
											Todos</button></th>
								</tr>
							</thead>
							<tbody>";
			arsort($resultado);
			foreach (array_keys($resultado) as $motivo) {
				$resultado2Json = json_encode($resultado2[$motivo]);
				$tabelaMotivo .= "<tr>";
				$tabelaMotivo .= "<td>" . $motivo . "</td>";
				$tabelaMotivo .= "<td>" . $resultado[$motivo] . "</td>";
				$tabelaMotivo .= "<td>" . sizeof($resultado2[$motivo]) . "</td>";
				$tabelaMotivo .= "<td style='text-align: center;'><button style='height: 22px; padding: 0px 10px;' class='btn btn-default btn-sm' onclick=\"createModal('$motivo'," . htmlspecialchars(json_encode($resultado2[$motivo]), ENT_QUOTES, 'UTF-8') . ");\">Visualizar</button></td>";
				$tabelaMotivo .= "</tr>";
			}
			$tabelaMotivo .= "</tbody>
			</table>
			</div>
				<div class='col-md-3.5' style='padding-left: 3px !important; width: 315px;'>
					<!-- <div class='container' style='display:flex'> -->
						<div id='chart-ativos' style='width:100%; background: green; height: 232px;'>
							<!-- Conteúdo do gráfico Sintético -->
						<!-- </div> -->
					</div>
				</div>
				<div class='col-md-3.5' style='width: 315px;'>
					<!-- <div class='container' style='display:flex'> -->
						<div id='chart-inativos' style='width:100%; background: green; height: 232px;'>
							<!-- Conteúdo do gráfico Sintético -->
						</div>
					<!-- </div>-->
				</div>
			</div>
			";
		}
	} else {
		$encontrado = false;
	}

	$totalEmpresa = $arquivoGeral['totais']['ativo'] + $arquivoGeral['totais']['inativo'];

	if ($encontrado) {
		$rowTotais = "<tr class='totais'>";
		$rowTitulos = "<tr id='titulos' class='titulos'>";
		$rowTitulos2 = "<tr id='titulos2' class='titulos2'>";

		if (!empty($_POST["empresa"])) {
			if (!in_array($_SERVER["REQUEST_URI"], $dominiosAutotrac)) {

				$rowTotal = "<td></td>
					<td></td>
					<td><b>Total</b></td>
					<td class='total'><b>" . $totais["Inicio de Jornada"]["ativo"] . "</b></td>
					<td class='total'><b>" . $totais["Inicio de Jornada"]["inativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Fim de Jornada"]["ativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Fim de Jornada"]["inativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Inicio de Refeição"]["ativo"] . "</td>
					<td class='total'><b>" . $totais["Inicio de Refeição"]["inativo"] . "</td>
					<td class='total'><b>" . $totais["Fim de Refeição"]["ativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Fim de Refeição"]["inativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Inicio de Espera"]["ativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Inicio de Espera"]["inativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Fim de Espera"]["ativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Fim de Espera"]["inativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Inicio de Descanso"]["ativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Inicio de Descanso"]["inativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Fim de Descanso"]["ativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Fim de Descanso"]["inativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Inicio de Repouso"]["ativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Inicio de Repouso"]["inativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Fim de Repouso"]["ativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Fim de Repouso"]["inativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Inicio de Repouso Embarcado"]["ativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Inicio de Repouso Embarcado"]["inativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Fim de Repouso Embarcado"]["ativo"] . "</b></td> 
					<td class='total'><b>" . $totais["Fim de Repouso Embarcado"]["inativo"] . "</b></td> 
				";

				$rowTotais .=
					"<th colspan='3'>" . $arquivoGeral["empr_tx_nome"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Inicio de Jornada"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Fim de Jornada"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Inicio de Refeição"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Fim de Refeição"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Inicio de Espera"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Fim de Espera"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Inicio de Descanso"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Fim de Descanso"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Inicio de Repouso"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Fim de Repouso"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Inicio de Repouso Embarcado"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Fim de Repouso Embarcado"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral['totais']['ativo'] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral['totais']['inativo'] . "</th>"
					. "<th colspan='2'>" . $totalEmpresa . "</th>";

				$rowTitulos .=
					"<th data-column='matricula' data-order='asc'>Matrícula</th>"
					. "<th data-column='nome' data-order='asc'>Nome do Funcionário</th>"
					. "<th data-column='qtdMotoristas' data-order='asc'>Ocupação</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Inicio de Jornada</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Fim de Jornada</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Inicio de Refeição</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Fim de Refeição</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Inicio de Espera</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Fim de Espera</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Inicio de Descanso</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Fim de Descanso</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Inicio de Repouso</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Fim de Repouso</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Inicio de Repouso Embarcad</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Fim de Repouso Embarcado</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Total</th>"
					. "<th data-column='' data-order='asc' colspan='2'>Total Geral</th>";

				$rowTitulos2 .=
					"<th></th>"
					. "<th></th>"
					. "<th></th>"
					. "<th>Ativo</th>"
					. "<th>Inativo</th>"
					. "<th>Ativo</th>"
					. "<th>Inativo</th>"
					. "<th>Ativo</th>"
					. "<th>Inativo</th>"
					. "<th>Ativo</th>"
					. "<th>Inativo</th>"
					. "<th>Ativo</th>"
					. "<th>Inativo</th>"
					. "<th>Ativo</th>"
					. "<th>Inativo</th>"
					. "<th>Ativo</th>"
					. "<th>Inativo</th>"
					. "<th>Ativo</th>"
					. "<th>Inativo</th>"
					. "<th>Ativo</th>"
					. "<th>Inativo</th>"
					. "<th>Ativo</th>"
					. "<th>Inativo</th>"
					. "<th>Ativo</th>"
					. "<th>Inativo</th>"
					. "<th>Ativo</th>"
					. "<th>Inativo</th>"
					. "<th>Ativo</th>"
					. "<th>Inativo</th>"
					. "<th></th>";
			} else {
				$rowTotais .=
					"<th colspan='1'></th>"
					. "<th colspan='2'></th>"
					. "<th colspan='2'>" . $arquivoGeral["Inicio de Jornada"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Fim de Jornada"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Inicio de Refeição"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Fim de Refeição"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Inicio de Espera"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Fim de Espera"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Inicio de Descanso"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Fim de Descanso"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Inicio de Repouso"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Fim de Repouso"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Inicio de Repouso Embarcado"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Fim de Repouso Embarcado"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Pernoite - Fim De Jornada"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Refeicao"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Em Espera"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Descanso"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Reinicio De Viagem"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Inicio De Viagem"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Fim De Viagem"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Parada Eventual"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Sol De Desvio De Rota"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Sol Desengate/Bau"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Manutencao"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Macro Msg Livre"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Ag Descarga"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Abastecimento - Arla32"] . "</th>"
					. "<th colspan='2'>" . $arquivoGeral["Troca De Veículo"] . "</th>";

				$rowTitulos .=
					"<th data-column='matricula' data-order='asc'>Matrícula</th>"
					. "<th data-column='nome' data-order='asc'>Nome do Funcionário</th>"
					. "<th data-column='qtdMotoristas' data-order='asc'>Ocupação</th>"
					. "<th data-column='percEndossados' data-order='asc'>Inicio de Jornada</th>"
					. "<th data-column='jornadaPrevista' data-order='asc'>Fim de Jornada</th>"
					. "<th data-column='JornadaEfetiva' data-order='asc'>Inicio de Refeição</th>"
					. "<th data-column='he50APagar' data-order='asc'>Fim de Refeição</th>"
					. "<th data-column='he100APagar' data-order='asc'>Inicio de Espera</th>"
					. "<th data-column='adicionalNoturno' data-order='asc'>Fim de Espera</th>"
					. "<th data-column='esperaIndenizada' data-order='asc'>Inicio de Descanso</th>"
					. "<th data-column='saldoAnterior' data-order='asc'>Fim de Descanso</th>"
					. "<th data-column='' data-order='asc'>Inicio de Repouso</th>"
					. "<th data-column='' data-order='asc'>Fim de Repouso</th>"
					. "<th data-column='' data-order='asc'>Inicio de Repouso Embarcado</th>"
					. "<th data-column='' data-order='asc'>Fim de Repouso Embarcado</th>"
					. "<th data-column='' data-order='asc'>Pernoite - Fim De Jornad</th>"
					. "<th data-column='' data-order='asc'>Refeicao</th>"
					. "<th data-column='' data-order='asc'>Em Espera</th>"
					. "<th data-column='' data-order='asc'>Descanso</th>"
					. "<th data-column='' data-order='asc'>Reinicio De Viagem</th>"
					. "<th data-column='' data-order='asc'>Parada Eventual/th>"
					. "<th data-column='' data-order='asc'>Sol De Desvio De Rota</th>"
					. "<th data-column='' data-order='asc'>Sol Desengate/Bau</th>"
					. "<th data-column='' data-order='asc'>Manutencao</th>"
					. "<th data-column='' data-order='asc'>Macro Msg Livre</th>"
					. "<th data-column='' data-order='asc'>Ag Descarga</th>"
					. "<th data-column='' data-order='asc'>Abastecimento - Arla32</th>"
					. "<th data-column='saldoFinal' data-order='asc'>Troca De Veículo</th>";
			}
			$mostra = false;
			$titulo = "Geral de Ajustes";

			$empresa = mysqli_fetch_assoc(query(
				"SELECT * FROM empresa
                WHERE empr_tx_status = 'ativo'
                    AND empr_nb_id = {$_POST["empresa"]}
                LIMIT 1;"
			));
			include_once "painel_html2.php";
		} else {
			if (!in_array($_SERVER["REQUEST_URI"], $dominiosAutotrac)) {
				$rowTotais .=
					"<th colspan='1'></th>"
					. "<th colspan='1'></th>"
					. "<th colspan='1'>" . $arquivoGeral["Inicio de Jornada"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Fim de Jornada"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Inicio de Refeição"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Fim de Refeição"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Inicio de Espera"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Fim de Espera"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Inicio de Descanso"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Fim de Descanso"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Inicio de Repouso"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Fim de Repouso"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Inicio de Repouso Embarcado"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Fim de Repouso Embarcado"] . "</th>";

				$rowTitulos .=
					"<th data-column='nome' data-order='asc'>Nome da Empresa/Filial</th>"
					. "<th data-column='qtdMotoristas' data-order='asc'>Qtd. Motoristas</th>"
					. "<th data-column='percEndossados' data-order='asc'>Inicio de Jornada</th>"
					. "<th data-column='jornadaPrevista' data-order='asc'>Fim de Jornada</th>"
					. "<th data-column='JornadaEfetiva' data-order='asc'>Inicio de Refeição</th>"
					. "<th data-column='he50APagar' data-order='asc'>Fim de Refeição</th>"
					. "<th data-column='he100APagar' data-order='asc'>Inicio de Espera</th>"
					. "<th data-column='adicionalNoturno' data-order='asc'>Fim de Espera</th>"
					. "<th data-column='esperaIndenizada' data-order='asc'>Inicio de Descanso</th>"
					. "<th data-column='saldoAnterior' data-order='asc'>Fim de Descanso</th>"
					. "<th data-column='saldoPeriodo' data-order='asc'>Inicio de Repouso</th>"
					. "<th data-column='saldoPeriodo' data-order='asc'>Fim de Repouso</th>"
					. "<th data-column='saldoPeriodo' data-order='asc'>Inicio de Repouso Embarcad</th>"
					. "<th data-column='saldoPeriodo' data-order='asc'>Fim de Repouso Embarcado</th>";
			} else {
				$rowTotais .=
					"<th colspan='1'></th>"
					. "<th colspan='1'></th>"
					. "<th colspan='1'>" . $arquivoGeral["Inicio de Jornada"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Fim de Jornada"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Inicio de Refeição"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Fim de Refeição"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Inicio de Espera"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Fim de Espera"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Inicio de Descanso"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Fim de Descanso"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Inicio de Repouso"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Fim de Repouso"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Inicio de Repouso Embarcado"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Fim de Repouso Embarcado"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Pernoite - Fim De Jornada"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Refeicao"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Em Espera"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Descanso"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Reinicio De Viagem"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Inicio De Viagem"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Fim De Viagem"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Parada Eventual"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Sol De Desvio De Rota"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Sol Desengate/Bau"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Manutencao"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Macro Msg Livre"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Ag Descarga"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Abastecimento - Arla32"] . "</th>"
					. "<th colspan='1'>" . $arquivoGeral["Troca De Veículo"] . "</th>";

				$rowTitulos .=
					"<th data-column='nome' data-order='asc'>Nome da Empresa/Filial</th>"
					. "<th data-column='qtdMotoristas' data-order='asc'>Qtd. Motoristas</th>"
					. "<th data-column='percEndossados' data-order='asc'>Inicio de Jornada</th>"
					. "<th data-column='jornadaPrevista' data-order='asc'>Fim de Jornada</th>"
					. "<th data-column='JornadaEfetiva' data-order='asc'>Inicio de Refeição</th>"
					. "<th data-column='he50APagar' data-order='asc'>Fim de Refeição</th>"
					. "<th data-column='he100APagar' data-order='asc'>Inicio de Espera</th>"
					. "<th data-column='adicionalNoturno' data-order='asc'>Fim de Espera</th>"
					. "<th data-column='esperaIndenizada' data-order='asc'>Inicio de Descanso</th>"
					. "<th data-column='saldoAnterior' data-order='asc'>Fim de Descanso</th>"
					. "<th data-column='' data-order='asc'>Inicio de Repouso</th>"
					. "<th data-column='' data-order='asc'>Fim de Repouso</th>"
					. "<th data-column='' data-order='asc'>Inicio de Repouso Embarcad</th>"
					. "<th data-column='' data-order='asc'>Fim de Repouso Embarcado</th>"
					. "<th data-column='' data-order='asc'>Pernoite - Fim De Jornad</th>"
					. "<th data-column='' data-order='asc'>Refeicao</th>"
					. "<th data-column='' data-order='asc'>Em Espera</th>"
					. "<th data-column='' data-order='asc'>Descanso</th>"
					. "<th data-column='' data-order='asc'>Reinicio De Viagem</th>"
					. "<th data-column='' data-order='asc'>Parada Eventual/th>"
					. "<th data-column='' data-order='asc'>Sol De Desvio De Rota</th>"
					. "<th data-column='' data-order='asc'>Sol Desengate/Bau</th>"
					. "<th data-column='' data-order='asc'>Manutencao</th>"
					. "<th data-column='' data-order='asc'>Macro Msg Livre</th>"
					. "<th data-column='' data-order='asc'>Ag Descarga</th>"
					. "<th data-column='' data-order='asc'>Abastecimento - Arla32</th>"
					. "<th data-column='saldoFinal' data-order='asc'>Troca De Veículo</th>";
			}
			$mostra = false;
			$titulo = "Geral de Ajustes";
			include_once "painel_html2.php";
		}
		$rowTotais .= "</tr>";
		$rowTitulos .= "</tr>";
	} else {
		if (!empty($_POST["acao"]) && $_POST["acao"] == "buscar") {
			set_status("Não Possui dados desse mês");
			echo "<script>alert('Não Possui dados desse mês')</script>";
		}
	}
	carregarJS($arquivos);
	carregarGraficos($periodoInicio);
	rodape();
}
