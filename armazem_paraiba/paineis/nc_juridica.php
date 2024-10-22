<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
	require_once __DIR__."/funcoes_paineis.php";
	require __DIR__."/../funcoes_ponto.php";

// $_POST["busca_endossado"] = "naoEndossado";
// $_POST["busca_dataMes"] = "2024-05";
// relatorio_nao_conformidade_juridica();

	header("Expires: 01 Jan 2001 00:00:00 GMT");
	header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
	header('Cache-Control: post-check=0, pre-check=0', FALSE);
	header('Pragma: no-cache');

	function enviarForm() {
		$_POST["acao"] = $_POST["campoAcao"];
		index();
	}

	function carregarJS(array $arquivos) {

		$linha = "linha = '<tr>'";
		if (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "naoEndossado") {
			$linha .= "+'<td>'+row.nome+'</td>'
						+'<td class=\'refeicao\'>'+ (row.refeicao === 0 ? '' : row.refeicao) +'</td>'
						+'<td>'+(row.espera === 0 ? '' : row.espera )+'</td>'
						+'<td>'+(row.descanso === 0 ? '' : row.descanso )+'</td>'
						+'<td>'+(row.repouso === 0 ? '' : row.repouso )+'</td>'
						+'<td>'+(row.jornada === 0 ? '' : row.jornada )+'</td>'
						+'<td>'+(row.jornadaPrevista === 0 ? '' : row.jornadaPrevista )+'</td>'
						+'<td>'+(row.jornadaEfetiva	=== 0 ? '' : row.jornadaEfetiva )+'</td>'
						+'<td>'+(row.mdc === 0 ? '' : row.mdc )+'</td>'
						+'<td>'+(row.intersticioInferior === 0 ? '' : row.intersticioInferior )+'</td>'
						+'<td>'+(row.intersticioSuperior === 0 ? '' : row.intersticioSuperior )+'</td>'
						+'<td>'+(totalNaEndossado)+'</td>'
					+'</tr>';";
		} elseif (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "endossado") {
			$linha .= "+'<td>'+row.nome+'</td>'
							+'<td class=\'refeicao\'>'+ (row.refeicao === 0 ? '' : row.refeicao) +'</td>'
							+'<td>'+(row.jornadaPrevista === 0 ? '' : row.jornadaPrevista )+'</td>'
							+'<td>'+(row.jornadaEfetiva	=== 0 ? '' : row.jornadaEfetiva )+'</td>'
							+'<td>'+(row.mdc === 0 ? '' : row.mdc )+'</td>'
							+'<td>'+(row.intersticioInferior === 0 ? '' : row.intersticioInferior )+'</td>'
							+'<td>'+(row.intersticioSuperior === 0 ? '' : row.intersticioSuperior )+'</td>'
							+'<td>'+(totalEndossado)+'</td>'
						+'</tr>';";
		}

		$carregarDados = "";
		foreach ($arquivos as $arquivo) {
			$carregarDados .= "carregarDados('".$arquivo."');";
		}

		echo
			"<form name='myForm' method='post' action='".htmlspecialchars($_SERVER["PHP_SELF"]). "'>
				<input type='hidden' name='acao'>
				<input type='hidden' name='campoAcao'>
				<input type='hidden' name='empresa'>
				<input type='hidden' name='busca_dataMes'>
				<input type='hidden' name='busca_dataInicio'>
				<input type='hidden' name='busca_dataFim'>
				<input type='hidden' name='busca_data'>
			</form>
			<script>
				function setAndSubmit(empresa){
					document.myForm.acao.value = 'enviarForm()';
					document.myForm.campoAcao.value = 'buscar';
					document.myForm.empresa.value = empresa;
					document.myForm.busca_dataMes.value = document.getElementById('busca_dataMes').value;
					document.myForm.submit();
				}

				function atualizarPainel(){
					console.info(document.getElementById('busca_data').value);
					document.myForm.empresa.value = document.getElementById('empresa').value;
					document.myForm.busca_data.value = document.getElementById('busca_data').value;
					document.myForm.acao.value = 'atualizar';
					document.myForm.submit();
				}

				function imprimir(){
					window.print();
				}

				function calcularTotalColuna(tabelaId, colunaClasse, resultadoId) {
					var total = 0;  // Inicializa a variável para acumular o total

					// Itera por todas as células da coluna especificada (células com a classe fornecida)
					$(tabelaId + ' tbody tr').each(function() {
						var valor = parseFloat($(this).find(colunaClasse).text());  // Pega o valor da célula

						// Verifica se o valor é numérico antes de somar
						if (!isNaN(valor)) {
							total += valor;
						}
					});

					console.log(total);

					// Exibe o resultado na tela
					// $(resultadoId).text('Total: ' + total);
				}

			
				$(document).ready(function(){
					var tabela = $('#tabela-empresas tbody');

					function carregarDados(urlArquivo){
						$.ajax({
							url: urlArquivo+ '?v=' + new Date().getTime(),
							dataType: 'json',
							success: function(data){
								var row = {};
								$.each(data, function(index, item){
									row[index] = item;
									});
									var totalNaEndossado = (row.jornadaPrevista || 0) + (row.jornadaEfetiva || 0) + (row.refeicao || 0) 
									+ (row.espera || 0) + (row.descanso || 0) + (row.repouso || 0) + (row.jornada || 0) 
									+ (row.mdc || 0) + (row.intersticioInferior || 0) + (row.intersticioSuperior || 0);

									var totalEndossado = (row.refeicao || 0) + (row.jornadaPrevista || 0) + (row.jornadaEfetiva || 0) 
										+ (row.mdc || 0) + (row.intersticioInferior || 0) + (row.intersticioSuperior || 0);
									console.log(row);
									"
								.$linha
								. "tabela.append(linha);
							},
							error: function(){
								console.log('Erro ao carregar os dados.');
							}
						});
					}

					// // Função para conversão de Horas para Minutos
					// function horasParaMinutos(horas) {
					//     var partes = horas.split(':');
					//     var horasNumeros = parseInt(partes[0], 10);  // Horas (pode ser positivo ou negativo)
					//     var minutos = parseInt(partes[1], 10);       // Minutos

					//     // Converte as horas para minutos totais
					//     return (horasNumeros*60)+(horasNumeros < 0? -minutos: minutos);
					// }
						
					// // Função para ordenar a tabela
					// function ordenarTabela(coluna, ordem){
					//     var linhas = tabela.find('tr').get();
						
					//     linhas.sort(function(a, b){
					//         var valorA = $(a).children('td').eq(coluna).text();
					//         var valorB = $(b).children('td').eq(coluna).text();

					//         // Verifica se os valores estão no formato HHH:mm (inclui 1, 2 ou 3 dígitos nas horas)
					//         if (valorA.match(/^-?\d{1,3}:\d{2}$/) && valorB.match(/^-?\d{1,3}:\d{2}$/)) {
					//             valorA = horasParaMinutos(valorA);
					//             valorB = horasParaMinutos(valorB);
					//         }

					//         if(valorA < valorB){
					//             return ordem === 'asc'? -1: 1;
					//         }
					//         if(valorA > valorB){
					//             return ordem === 'asc'? 1: -1;
					//         }
					//         return 0;
					//     });

					//     $.each(linhas, function(index, row){
					//         tabela.append(row);
					//     });
					// }

					// // Evento de clique para ordenar a tabela ao clicar no cabeçalho
					// $('#titulos th').click(function(){
					//     var coluna = $(this).index();
					//     var ordem = $(this).data('order');
					//     $('#tabela-empresas th').data('order', 'desc'); // Redefinir ordem de todas as colunas
					//     $(this).data('order', ordem === 'desc'? 'asc': 'desc');
					//     ordenarTabela(coluna, $(this).data('order'));

					//     // Ajustar classes para setas de ordenação
					//     $('#titulos th').removeClass('sort-asc sort-desc');
					//     $(this).addClass($(this).data('order') === 'asc'? 'sort-asc': 'sort-desc');
					// });

					// $('#tabela1 tbody td').click(function(event) {
					//     if ($(this).is(':first-child')) {
					//         var textoPrimeiroTd = $(this).text().trim(); // Pega o texto do primeiro <td>
					//         var status = '';
					//         if(textoPrimeiroTd === 'Não Endossado'){
					//             var status = 'N';
					//         } else if (textoPrimeiroTd === 'Endo. Parcialmente'){
					//             var status = 'EP';
					//         } else{
					//             var status = 'E'
					//         }

					//         $('#tabela-empresas tbody tr').each(function() {
					//             var textoCelula = $(this).find('td').eq(3).text().trim(); // Pegar o texto da primeira célula (coluna 3) de cada linha
					//             // Mostrar ou ocultar a linha com base na comparação
					//             if (textoCelula === status) {
					//                 $(this).show(); // Mostrar linha se o texto da célula corresponder ao valor clicado
					//             } else {
					//                 $(this).hide(); // Ocultar linha se o texto da célula for diferente
					//             }
					//         });

			
					//     } else {
					//         event.stopPropagation(); // Impede que o evento de clique se propague
					//     }
					// });

					// $('#tabela1 thead tr th').click(function(event) {
					//     if ($(this).is(':first-child')) {
					//         var textoPrimeiroTd = $(this).text().trim(); // Pega o texto do primeiro <td>
					//         $('#tabela-empresas tbody tr').each(function() {
					//             $(this).show(); // Mostrar linha se o texto da célula corresponder ao valor clicado
					//         });
					//     } else {
					//         event.stopPropagation(); // Impede que o evento de clique se propague
					//     }
					// });

					// $('#tabela2 tbody td').click(function(event) {
					//     if ($(this).is(':first-child')) {
					//         var textoPrimeiroTd = $(this).text().trim(); // Pega o texto do primeiro <td>

					//         // Definindo a condição de filtro com base no texto do primeiro <td>
					//         var condicao;
					//         if (textoPrimeiroTd === 'Meta') {
					//             condicao = function(textoCelula) {
					//                 return textoCelula === '00:00'; // Exibir se for igual a 00:00
					//             };
					//         } else if (textoPrimeiroTd === 'Positivo') {
					//             condicao = function(textoCelula) {
					//                 return textoCelula > '00:00'; // Exibir se for maior que 00:00
					//             };
					//         } else {
					//             condicao = function(textoCelula) {
					//                 return textoCelula < '00:00'; // Exibir se for menor que 00:00
					//             };
					//         }

					//         // Percorrendo as linhas da tabela #tabela-empresas
					//         $('#tabela-empresas tbody tr').each(function() {
					//             var textoCelula = $(this).find('td').eq(12).text().trim(); // Pegar o texto da coluna 13 de cada linha
					//             // Mostrar ou ocultar a linha com base na condição definida
					//             if (condicao(textoCelula)) {
					//                 $(this).show(); // Mostrar linha se a condição for verdadeira
					//             } else {
					//                 $(this).hide(); // Ocultar linha se a condição for falsa
					//             }
					//         });
					//     } else {
					//         event.stopPropagation(); // Impede que o evento de clique se propague
					//     }
					// });

					// $('#tabela2 thead tr th').click(function(event) {
					//     if ($(this).is(':first-child')) {
					//         var textoPrimeiroTd = $(this).text().trim(); // Pega o texto do primeiro <td>
					//         $('#tabela-empresas tbody tr').each(function() {
					//             $(this).show(); // Mostrar linha se o texto da célula corresponder ao valor clicado
					//         });
					//     } else {
					//         event.stopPropagation(); // Impede que o evento de clique se propague
					//     }
					// });


					".$carregarDados. "
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
			</script>"
		;
	}

	function index() {

		if (empty($_POST["busca_dataMes"])) {
			$_POST["busca_dataMes"] = date("Y-m");
		}

		if (!empty($_POST["acao"]) && $_POST["acao"] == "buscar") {

			if ($_POST["busca_dataMes"] > date("Y-m")) {
				unset($_POST["acao"]);
				$_POST["errorFields"][] = "busca_dataMes";
				set_status("ERRO: Não é possível pesquisar após a data atual.");
			}
			cabecalho("Relatório de Não Conformidade Juridica");
		} elseif (!empty($_POST["acao"]) && $_POST["acao"] == "atualizarPainel") {
			echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
			ob_flush();
			flush();

			cabecalho("Relatório de Não Conformidade Juridica");

			$err = ($_POST["busca_dataInicio"] > date("Y-m-d"))*1+($_POST["busca_dataFim"] > date("Y-m-d"))*2;
			if ($err > 0) {
				switch ($err) {
					case 1:
						$_POST["errorFields"][] = "busca_dataInicio";
						break;
					case 2:
						$_POST["errorFields"][] = "busca_dataFim";
						break;
					case 3:
						$_POST["errorFields"][] = "busca_dataInicio";
						$_POST["errorFields"][] = "busca_dataFim";
						break;
				}
				unset($_POST["acao"]);
				set_status("ERRO: Não é possível atualizar após a data atual.");
			} else {
				require_once "funcoes_paineis.php";
				relatorio_nao_conformidade_juridica();
			}
		} else {
			cabecalho("Relatório de Não Conformidade Juridica");
		}

	// $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
	//position: absolute; top: 101px; left: 420px;

	$campoAcao =
	"<div class='col-sm-2 margin-bottom-5' style='min-width: fit-content;'>
				<label>" . "Ação" . "</label><br>
				<label class='radio-inline'>
					<input type='radio' name='campoAcao' value='buscar' " . ((empty($_POST["campoAcao"]) || $_POST["campoAcao"] == "buscar") ? "checked" : "") . "> Buscar
				</label>
				<label class='radio-inline'>
          			<input type='radio' name='campoAcao' value='atualizarPainel'" . (!empty($_POST["campoAcao"]) && $_POST["campoAcao"] == "atualizarPainel" ? "checked" : "") . "> Atualizar
				</label>
			</div>";

		$campos = [
			combo_net("Empresa", "empresa", $_POST["empresa"]?? "", 4, "empresa", ""),
			$campoAcao,
			campo_mes("Mês*", "busca_dataMes", ($_POST["busca_dataMes"] ?? date("Y-m")), 2),
			combo("Endossado",	"busca_endossado", (!empty($_POST["busca_endossado"]) ? $_POST["busca_endossado"] : ""), 2, ["naoEndossado" => "Não","endossado" => "Sim"])
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


		abre_form();
		linha_form($campos);
		fecha_form($buttons);


		$arquivos = [];
		$dataEmissao = ""; //Utilizado no HTML
		$encontrado = true;
		$path = "./arquivos/nao_conformidade_juridica";
		$periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

		$totais = [
			"inicioSemRegistro" => 0,
			"inicioRefeicaoSemRegistro" => 0,
			"fimRefeicaoSemRegistro" => 0,
			"fimSemRegistro" => 0,
			"refeicao1h" => 0,
			"refeicao2h" => 0,
			"esperaAberto" => 0,
			"descansoAberto" => 0,
			"repousoAberto" => 0,
			"jornadaAberto" => 0,
			"jornadaExedida" => 0,
			"mdcDescanso" => 0,
			"intersticio" => 0
		];

		$periodoRelatorio = [
			"dataInicio" => "1900-01-01",
			"dataFim" => "1900-01-01"
		];

		if (!empty($_POST["empresa"]) && !empty($_POST["busca_dataMes"])) {
			//Painel dos endossos dos motoristas de uma empresa específica
			$empresa = mysqli_fetch_assoc(query(
				"SELECT * FROM empresa"
					." WHERE empr_tx_status = 'ativo'"
					." AND empr_nb_id = ".$_POST["empresa"]
					." LIMIT 1;"
			));

			if ($_POST["busca_endossado"] === "naoEndossado") {
				$pastaArquivo = "nao_endossado";
			}
			else{
				$pastaArquivo = "endossado";
			}

			$path .= "/".$_POST["busca_dataMes"]."/".$empresa["empr_nb_id"]."/".$pastaArquivo;

			if (is_dir($path)) {
				$pasta = dir($path);
				while ($arquivo = $pasta->read()) {
					if (!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
						$arquivos[] = $arquivo;
					}

				}
				$pasta->close();

				$totalRefeição = 0;
				$totalJornadaPrevista = 0;
				$totalJornadaEfetiva = 0; 
				$totalEspera = 0;
				$totalDescanso = 0;
				$totalRepouso = 0; 
				$totalJornada = 0;
				$totalMdc = 0; 
				$totalIntersticioInferior = 0;
				$totalIntersticioSuperior = 0;
				foreach ($arquivos as &$arquivo) {
					$arquivo = $path . "/" . $arquivo;
					if (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "endossado"){
						$json = @json_decode(file_get_contents($arquivo), true);
						$totalRefeição += $json["refeicao"];
						$totalJornadaPrevista += $json["jornadaPrevista"];
						$totalJornadaEfetiva += $json["jornadaEfetiva"];
						$totalEspera += $json["espera"];
						$totalDescanso += $json["descanso"];
						$totalRepouso += $json["repouso"];
						$totalJornada += $json["jornada"];
						$totalMdc += $json["mdc"];
						$totalIntersticioInferior += $json["intersticioInferior"];
						$totalIntersticioSuperior += $json["intersticioSuperior"];
					}
				}

				if (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "endossado"){
					$gravidadeAlta = $totalRefeição + $totalIntersticioInferior + $totalIntersticioSuperior;
					$gravidadeMedia = $totalJornadaEfetiva + $totalMdc;
					$gravidadeBaixa = $totalJornadaPrevista;	
				}


				if (!empty($arquivo)) {
					$dataEmissao = "Atualizado em: " . date("d/m/Y H:i", filemtime($arquivo)); //Utilizado no HTML.
					$arquivoGeral = json_decode(file_get_contents($arquivo), true);

					$periodoRelatorio = [
						"dataInicio" => $arquivoGeral["dataInicio"],
						"dataFim" => $arquivoGeral["dataFim"]
					];

					$encontrado = true;
				} else {
					echo "<script>alert('Não tem jornadas abertas.')</script>";
				}
				
			} else {
				$encontrado = false;
			}
		} 

		if ($encontrado) {
			
			$rowTitulos = "<tr id='titulos' >";

			if (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "naoEndossado") {
				
				$rowTitulos .=
					"<th class=''>Motoristas</th>"
					."<th class=''>Refeição</th>"
					."<th class=''>Espera</th>"
					."<th class=''>Descanso</th>"
					."<th class=''>Repouso</th>"
					."<th class=''>Jornada</th>"
					."<th class=''>Jornada Prevista</th>"
					."<th class=''>Jornada Efetiva</th>"
					."<th class=''>MDC</th>"
					."<th class=''>Interstício Inferior</th>"
					."<th class=''>Interstício Superior</th>"
					."<th class=''>TOTAL</th>";

					$endossado = false;

					
			}  elseif (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "endossado") {
				$rowTitulos .=
					"<th class=''>Motoristas</th>"
					. "<th class=''>Refeição</th>"
					. "<th class=''>Jornada Prevista</th>"
					. "<th class=''>Jornada Efetiva</th>"
					. "<th class=''>MDC</th>"
					. "<th class=''>Interstício Inferior</th>"
					. "<th class=''>Interstício Superior</th>"
					. "<th class=''>TOTAL</th>";

				$rowGravidade = "<table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact'>"
					. "<thead>"
					. "<tr>"
					. "<td> Nivel de Gravidade</td>"
					. "<td>%</td>"
					. "</th>"
					. "</thead>"
					. "<tbody>"
					. "<tr>"
					. "<td>Baixa</td>"
					. "<td>$gravidadeBaixa</td>"
					. "<td></td>"
					. "</tr>"
					. "<tr>"
					. "<td>Média</td>"
					. "<td>$gravidadeMedia</td>"
					. "<td>%</td>"
					. "</tr>"
					. "<tr>"
					. "<td>Alta</td>"
					. "<td>$gravidadeAlta</td>"
					. "<td>%</td>"
					. "</tr>"
					. "</tbody>"
					. "</table>";

					$endossado = true;
			}
			$rowTitulos .= "</tr>";

			include_once "painel_html2.php";

			// if (!empty($_POST["acao"]) && $_POST["acao"] == "buscar") {
			// 	set_status("Não Possui dados desse mês");
			// 	echo "<script>alert('Não Possui dados desse mês')</script>";
			// }
		}

	carregarJS($arquivos);
	rodape();
}
