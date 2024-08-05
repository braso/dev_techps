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
	tr.totais > th:nth-child(n+1):nth-child(-n+12){
		text-align: justify;
	}
	.titulos{
		background-color: #99ccff;
	}
	tr.titulos > th:nth-child(n+1):nth-child(-n+12){
		text-align: justify;
	}
	#tituloRelatorio{
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
</style>

<div id='tituloRelatorio'>
	<img class='left-logo' style='width: 150px' src='' alt='Logo Empresa Esquerda'>
	<h3>Relatorio Geral de Espelho de Ponto</h3>
	<div>
		<img class='right-logo' style='width: 150px' src='' alt='Logo Empresa Direita'>
	</div>
</div>
<div class='col-md-12 col-sm-12'>
	<div class='portlet light '>
		<div class='emissao'></div>
		<div class='table-responsive'>
		<div style='display: flex;'>
			<table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact' id='tabela1'>
				<thead>
					<tr>
						<th colspan='1'></th>
						<th colspan='1'>QUANT</th>
						<th colspan='1'>%</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>NÃO ENDOSSADO</td>
						<td class='porcentagenNaEndoTitle textCentralizado'></td>
						<td style='background-color: #ff471a;' class='porcentagenNaEndo'></td>
					</tr>
					<tr>
						<td>ENDOSSO PARCIAL</td>
						<td class='porcentagenEndoPcTitle textCentralizado'></td>
						<td style='background-color: #ffff66;' class='porcentagenEndoPc'></td>
					</tr>
					<tr>
						<td>ENDOSSADO</td>
						<td class='porcentagenEndoTitle textCentralizado'></td>
						<td style='background-color: #66b3ff;' class='porcentagenEndo'></td>
					</tr>
				</tbody>
			</table>
			<br>
			<table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact' id='tabela2'>
				<thead>
					<tr>
						<th colspan='1'>SALDO FINAL</th>
						<th colspan='1'>QUANT</th>
						<th colspan='1'>%</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td class='porcentagenMeta' style='background-color: #66b3ff;'>META</td>
						<td class='quantMeta textCentralizado'></td>
						<td class='porcentagenMetaValue'></td>
					</tr>
					<tr>
						<td class='porcentagenPosit' style='background-color: #00b33c;'>POSITIVO</td>
						<td class='quantPosi textCentralizado'></td>
						<td class='porcentagenPosiValue'></td>
					</tr>
					<tr>
						<td class='porcentagenNega' style='background-color: #ff471a;'>NEGATIVO</td>
						<td class='quantNega textCentralizado'></td>
						<td class='porcentagenNegaValue'></td>
					</tr>
				</tbody>
			</table>
		</div>
			<br>
			<div class='portlet-body form'>
			<table id='tabela-motorista' class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact'>
				<thead>
					<tr class='totais'>
						<th colspan='1'>Período: De <?=$dataInicioFormatada.' até '.$dataFimFormatada?></th>
						<th colspan='1'></th>
						<?=(!empty($MotoristasTotais)?
								 "<th colspan='1'>".$MotoristasTotais['jornadaPrevista']."</th>"
								."<th colspan='1'>".$MotoristasTotais['JornadaEfetiva']."</th>"
								."<th colspan='1'>".$MotoristasTotais['he50']."</th>"
								."<th colspan='1'>".$MotoristasTotais['he100']."</th>"
								."<th colspan='1'>".$MotoristasTotais['adicionalNoturno']."</th>"
								."<th colspan='1'>".$MotoristasTotais['esperaIndenizada']."</th>"
								."<th colspan='1'>".$MotoristasTotais['saldoAnterior']."</th>"
								."<th colspan='1'>".$MotoristasTotais['saldoPeriodo']."</th>"
								."<th colspan='1'>".$MotoristasTotais['saldoFinal']."</th>"
							:""
						)?>
					</tr>
					<tr id='ti' class='titulos'>
						<th data-column='motorista' data-order='asc'>Unidade - <?=$MotoristasTotais["empresaNome"];?></th>
						<th data-column='statusEndosso' data-order='asc'>Status Endosso</th>
						<th data-column='jornadaPrevista' data-order='asc'>Jornada Prevista</th>
						<th data-column='jornadaEfetiva' data-order='asc'>Jornada Efetiva</th>
						<th data-column='he50' data-order='asc'>HE 50%</th>
						<th data-column='he100' data-order='asc'>HE 100%</th>
						<th data-column='adicionalNoturno' data-order='asc'>Adicional Noturno</th>
						<th data-column='esperaIndenizada' data-order='asc'>ESPERA INDENIZADA</th>
						<th data-column='saldoAnterior' data-order='asc'>Saldo Anterior</th>
						<th data-column='saldoPeriodo' data-order='asc'>Saldo Periodo</th>
						<th data-column='saldoFinal' data-order='asc'>Saldo Final</th>
					</tr>
				</thead>
				<tbody>
						<!-- Conteúdo do json motoristas será inserido aqui -->
				</tbody>
			</table>
			</div>

		</div>
	</div>
</div>
<script>

	<?=
		"document.getElementsByClassName('left-logo')[0].src = '".$aEmpresa[0]["empr_tx_logo"]."';"
		."document.getElementsByClassName('right-logo')[0].src = '".$CONTEX['path']."/imagens/logo_topo_cliente.png"."';"
		."document.getElementsByClassName('emissao')[0].innerHTML = '".("Emissão Doc.: ".$emissão)."';"

		."document.getElementsByClassName('porcentagenEndoTitle')[0].innerHTML = '".$MotoristasTotais["endossados"]."';"
		."document.getElementsByClassName('porcentagenEndoPcTitle')[0].innerHTML = '".$MotoristasTotais["endossoPacial"]."';"
		."document.getElementsByClassName('porcentagenNaEndoTitle')[0].innerHTML = '".$MotoristasTotais["naoEndossados"]."';"

		."document.getElementsByClassName('porcentagenEndo')[0].innerHTML   = '".$percentuaisEndossos["endossados"]."';"
		."document.getElementsByClassName('porcentagenEndoPc')[0].innerHTML = '".$percentuaisEndossos["endossadosParcialmente"]."';"
		."document.getElementsByClassName('porcentagenNaEndo')[0].innerHTML = '".$percentuaisEndossos["naoEndossados"]."';"

		."document.getElementsByClassName('quantPosi')[0].innerHTML = '".$contagemSaldos["positivos"]."';"
		."document.getElementsByClassName('quantMeta')[0].innerHTML = '".$contagemSaldos["zerados"]."';"
		."document.getElementsByClassName('quantNega')[0].innerHTML = '".$contagemSaldos["negativos"]."';"

		."document.getElementsByClassName('porcentagenPosiValue')[0].innerHTML = '".$percentuaisSaldos["positivos"]."';"
		."document.getElementsByClassName('porcentagenMetaValue')[0].innerHTML = '".$percentuaisSaldos["zerados"]."';"
		."document.getElementsByClassName('porcentagenNegaValue')[0].innerHTML = '".$percentuaisSaldos["negativos"]."';"

		// ."document.getElementsByClassName('totais')[0].innerHTML = '".$porcentagenNega."';"
	?>

	$(document).ready(function (){
		var tabela = $('#tabela-motorista tbody');

		function carregarDado() {
			$.ajax({
				url: '<?="arquivos/paineis/".$idEmpresa."/".$_POST['busca_data']."/motoristas.json"?>',
				dataType: 'json',
				success: function(data){
					tabela.empty();
					$.each(data, function(index, item){
						// console.log("Item "+index+":");
						// for (var chave in item) {
						// 	if (item.hasOwnProperty(chave)) {
						// 		console.log(chave+": "+item[chave]);
						// 	}
						// }
						
						var he50 = (item.he50 === null || item.he50 === '00:00')? '' : item.he50;
						var he100 = (item.he100 === null || item.he100 === '00:00')? '' : item.he100;
						var adicionalNoturno = (item.adicionalNoturno === null || item.adicionalNoturno === '00:00')? '' : item.adicionalNoturno;
						var esperaIndenizada = (item.esperaIndenizada === null || item.esperaIndenizada === '00:00')? '' : item.esperaIndenizada;
						var saldoAnterior = item.saldoAnterior;
						var saldoPeriodo = item.saldoPeriodo;
						var saldoFinal = item.saldoFinal;

						var linha = 
							'<tr>'+
								'<td>'+item.motorista+'</td>'+
								'<td>'+item.statusEndosso+'</td>'+
								'<td>'+item.jornadaPrevista+'</td>'+
								'<td>'+item.jornadaEfetiva+'</td>'+
								'<td>'+he50+'</td>'+
								'<td>'+he100+'</td>'+
								'<td>'+adicionalNoturno+'</td>'+
								'<td>'+esperaIndenizada+'</td>'+
								'<td>'+saldoAnterior+'</td>'+
								'<td>'+saldoPeriodo+'</td>'+
								'<td>'+saldoFinal+'</td>'+
							'</tr>'
						;

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
		$('#ti th').click(function(){
			var coluna = $(this).index();
			var ordem = $(this).data('order');
			$('#tabela-motorista th').data('order', 'desc'); // Redefinir ordem de todas as colunas
			$(this).data('order', ordem === 'desc' ? 'asc' : 'desc');
			ordenarTabela(coluna, $(this).data('order'));

			// Ajustar classes para setas de ordenação
			$('#ti th').removeClass('sort-asc sort-desc');
			$(this).addClass($(this).data('order') === 'asc' ? 'sort-asc' : 'sort-desc');
		});

		carregarDado();
	});

	function downloadCSV() {
		// Caminho do arquivo CSV no servidor
		var filePath = '<?="./arquivos/paineis/Painel_".$MotoristasTotais["empresaNome"].".csv"?>' // Substitua pelo caminho do seu arquivo

		// Cria um link para download
		var link = document.createElement('a');

		// Configurações do link
		link.setAttribute('href', filePath);
		link.setAttribute('download', '<?="Painel_".$MotoristasTotais["empresaNome"].".csv"?>');

		// Adiciona o link ao documento
		document.body.appendChild(link);

		// Simula um clique no link para iniciar o download
		link.click();

		// Remove o link
		document.body.removeChild(link);
	}
</script>