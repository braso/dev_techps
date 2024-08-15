<link rel='stylesheet' href='<?=$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/css/paineis.css"?>'>
<div id='tituloRelatorio'>
	<img style='width: 150px' src='<?=$aEmpresa[0]["empr_tx_logo"]?>' alt='Logo Empresa Esquerda'>
	<h3>Relatorio Geral de saldo</h3>
	<div class='right-logo'>
		<img style='width: 150px' src='<?=$CONTEX["path"]?>/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
	</div>
</div>
<div class='col-md-12 col-sm-12' id='pdf2htmldiv'>
	<div class='portlet light '>
		<div class='table-responsive'>
			<div style='display: flex;'>
				<table style="display: none;" class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact' id='tabela1'>
                    <thead>
                        <tr>
                            <th colspan='1'></th>
                            <th colspan='1'>TOTAL</th>
                            <th colspan='1'>%</th>
                        </tr>
                    </thead>
                    <tbody>
						<tr>
							<td>NÃO ENDOSSADO</td>
							<td class='porcentagemNaEndo'></td>
							<td class="porcentagemNaEndo"></td>
						</tr>
						<tr>
							<td>ENDOSSO PARCIAL</td>
							<td class='porcentagemEndoPc'></td>
							<td class="porcentagemEndoPc"></td>
						</tr>
						<tr>
							<td>ENDOSSADO</td>
							<td class='porcentagemEndo'></td>
							<td class="porcentagemEndo"></td>
						</tr>
                    </tbody>
                </table>
				
				<table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact' id='tabela2'>
					<thead>
						<tr>
							<th colspan='1'>SALDO FINAL</th>
							<th colspan='1'>TOTAL</th>
							<th colspan='1'>%</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td class='porcentagemMeta'>META</td>
							<td class='textCentralizado'><?=$quantMeta?></td>
							<td><?=$performance["meta_endo"]?></td>
						</tr>
						<tr>
							<td class='porcentagemPosit'>POSITIVO</td>
							<td class='textCentralizado'><?=$quantPosi?></td>
							<td><?=$performance["posi_endoPc"]?></td>
						</tr>
						<tr>
							<td class='porcentagemNegat'>NEGATIVO</td>
							<td class='textCentralizado'><?=$quantNega?></td>
							<td><?=$performance["nega_naEndo"]?></td>
						</tr>
					</tbody>
				</table>
				<div class='emissao'>
					<?=(!empty($emissao)? "<b>Atualizado em:</b> ".$emissao."<br>": "")
					."<b>Período do relatório:</b> ".$dataInicio->format("d/m")." a ".$dataFim->format("d/m")?></div>
			</div>
			<br>
			<div class='portlet-body form'>
				<table id='tabela-conteudo' class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact'>
					<thead>
						<tr class='totais'>
							<th colspan='2'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
							<th colspan='1'></th>
						</tr>
						<tr id='titulos' class='titulos'>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
							<th data-order='asc'></th>
						</tr>
					</thead>
					<tbody>
						<!-- Conteúdo do json -->
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
<div id='impressao'>
	<b>Impressão Doc.:</b> <?=date("d/m/Y \T H:i:s")." (UTC-3)"?>
</div>
<script>
	$(document).ready(function() {
		var tabela = $('#tabela-conteudo tbody');
		function carregarDados() {
			$.ajax({
				url: '<?=$url?>',
				dataType: 'json',
				success: function(data) {
					tabela.empty();
					$.each(data, function(index, item) {
						//console.log("Item "+index+":");
						// for (var chave in item) {
						// 	if (item.hasOwnProperty(chave)) {
								//console.log(chave+": "+item[chave]);
						// 	}
						// }
						// console.log(item);

						<?php
							$linha = "'<tr>'";
							if (!empty($_POST['empresa']) && !empty($_POST['busca_data'])){
                                $linha .=
                                     "+'<td>'+item.matricula+'</td>'"
                                    ."+'<td>'+item.motorista+'</td>'"
                                    ."+'<td>'+item.statusEndosso+'</td>'";
							} else {
                                echo "var porcentagem = ((item.endossados/item.totalMotorista)*100)*(!isNaN(item.endossados) && !isNaN(item.totalMotorista) && item.totalMotorista !== 0);";
                                $linha .= 
                                     "+'<td style=\"cursor: pointer;\" onclick=setAndSubmit('+item.empresaId+')>'+item.empresaNome+'</td>'"
                                    ."+'<td>'+porcentagem.toFixed(2)+'</td>'"
                                    ."+'<td>'+item.totalMotorista+'</td>'";
                            }
							$linha .= 
								 "+'<td>'+item.jornadaPrevista+'</td>'"
								."+'<td>'+item.jornadaEfetiva+'</td>'"
								."+'<td>'+((item.he50 === null || item.he50 === '00:00')? '': item.he50)+'</td>'"
								."+'<td>'+((item.he100 === null || item.he100 === '00:00')? '': item.he100)+'</td>'"
								."+'<td>'+((item.adicionalNoturno === null || item.adicionalNoturno === '00:00')? '': item.adicionalNoturno)+'</td>'"
								."+'<td>'+((item.esperaIndenizada === null || item.esperaIndenizada === '00:00')? '': item.esperaIndenizada)+'</td>'"
								."+'<td>'+((item.saldoAnterior === null || item.saldoAnterior === '00:00')? '': item.saldoAnterior)+'</td>'"
								."+'<td>'+((item.saldoPeriodo === null || item.saldoPeriodo === '00:00')? '': item.saldoPeriodo)+'</td>'"
								."+'<td>'+((item.saldoFinal === null || item.saldoFinal === '00:00')? '': item.saldoFinal)+'</td>'";
							
							$linha .= "+'</tr>'";
							echo "var linha = ".$linha.";";
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

				if(valorA != valorB){
					return ((ordem === 'asc'? -1: 1)*((valorA < valorB)? 1: -1));
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
			$('#tabela-conteudo th').data('order', 'desc'); // Redefinir ordem de todas as colunas
			$(this).data('order', (ordem === 'desc'? 'asc': 'desc'));
			ordenarTabela(coluna, $(this).data('order'));

			// Ajustar classes para setas de ordenação
			$('#titulos th').removeClass('sort-asc sort-desc');
			$(this).addClass('sort-'+($(this).data('order') === 'asc'? 'asc': 'desc'));
		});

		carregarDados();
	});

	// function downloadCSV(nomeCsv) {
	//     // Caminho do arquivo CSV no servidor
	//     var filePath = './arquivos/paineis/Painel_'+nomeCsv+'.csv' // Substitua pelo caminho do seu arquivo

	//     // Cria um link para download
	//     var link = document.createElement('a');

	//     // Configurações do link
	//     link.setAttribute('href', filePath);
	//     link.setAttribute('download', 'Painel_'+nomeCsv+'.csv');

	//     // Adiciona o link ao documento
	//     document.body.appendChild(link);

	//     // Simula um clique no link para iniciar o download
	//     link.click();

	//     // Remove o link
	//     document.body.removeChild(link);
	// }
</script>