<?php
/*
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
*/

	require_once __DIR__."/funcoes_paineis.php";
	require __DIR__."/../funcoes_ponto.php";

	header("Expires: 01 Jan 2001 00:00:00 GMT");
	header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
	header('Cache-Control: post-check=0, pre-check=0', FALSE);
	header('Pragma: no-cache');

	function enviarForm() {
		$_POST["acao"] = $_POST["campoAcao"];
		index();
	}

	function carregarJS(array $arquivos, array $perfomanceMedia, array $perfomanceBaixa) {
		$jsonPerfomanceMedia = json_encode($perfomanceMedia);
		$jsonPerfomanceBaixa = json_encode($perfomanceBaixa);
		if (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "naoEndossado") {
			$linha = " 
					const arrayPerformanceBaixa = $jsonPerfomanceBaixa;
					const arrayPerformanceMedia = $jsonPerfomanceMedia;
					console.log(100 - arrayPerformanceMedia[row.matricula]);
					porcentagemBaixa = 100 - arrayPerformanceBaixa[row.matricula];
					porcentagemMedia = 100 - arrayPerformanceMedia[row.matricula];

					let corDeFundo = '';
					let classeImpressao = '';
					if(porcentagemBaixa >= 75 && porcentagemBaixa <= 100){
						corDeFundo = 'background-color: lightgreen;';
						classeImpressao = 'impressao-verde';
					} else if(porcentagemBaixa <= 75 && porcentagemBaixa >= 50){
						corDeFundo = 'background-color: var(--var-yellow2);';
						classeImpressao = 'impressao-amarelo';
					} else if(porcentagemBaixa <= 50 && porcentagemBaixa >= 25){
						corDeFundo = 'background-color: var(--var-lightorange);';
						classeImpressao = 'impressao-laranja';
					}else{
						corDeFundo = 'background-color: var(--var-lightred);';
						classeImpressao = 'impressao-vermelho';
					}

					let corDeFundo2 = '';
					let classeImpressao2 = '';

					if (porcentagemMedia >= 75 && porcentagemMedia <= 100) {
						corDeFundo2 = 'background-color: lightgreen;';
						classeImpressao2 = 'impressao-verde';
					} else if (porcentagemMedia < 75 && porcentagemMedia >= 50) {
						corDeFundo2 = 'background-color: var(--var-yellow2);';
						classeImpressao2 = 'impressao-amarelo';
					} else if (porcentagemMedia < 50 && porcentagemMedia >= 25) {
						corDeFundo2 = 'background-color: var(--var-lightorange);';
						classeImpressao2 = 'impressao-laranja';
					} else {
						corDeFundo2 = 'background-color: var(--var-lightred);';
						classeImpressao2 = 'impressao-vermelho';
					}

								
			";

			$linha .= " linha = '<tr style=\"'+(totalNaEndossado === 0 ? 'background-color: lightgreen;' : '')+'\">'";
			$linha .= "+'<td>'+row.matricula+'</td>'
						+'<td>'+row.nome+'</td>'
						+'<td>'+row.ocupacao+'</td>'
						+'<td>'+row.tipoOperacaoNome+'</td>'
						+'<td>'+(row.setorNome?? '')+'</td>'
                   		+'<td>'+(row.subsetorNome?? '')+'</td>'
						+'<td style=\"vertical-align: middle;\" class='+class1+'>'+(row.espera === 0 ? '' : row.espera )+'</td>'
						+'<td style=\"vertical-align: middle;\" class='+class1+'>'+(row.descanso === 0 ? '' : row.descanso )+'</td>'
						+'<td style=\"vertical-align: middle;\" class='+class1+'>'+(row.repouso === 0 ? '' : row.repouso )+'</td>'
						+'<td style=\"vertical-align: middle;\" class='+class1+'>'+(row.jornada === 0 ? '' : row.jornada )+'</td>'
						+'<td style=\"vertical-align: middle;\" class='+class1+'>'+(row.jornadaPrevista === 0 ? '' : row.jornadaPrevista )+'</td>'
						+'<td style=\"vertical-align: middle;\" class='+class2+'>'+(row.jornadaEfetiva	=== 0 ? '' : row.jornadaEfetiva )+'</td>'
						+'<td style=\"vertical-align: middle;\" class='+class2+'>'+(row.mdc === 0 ? '' : row.mdc )+'</td>'
						+'<td style=\"vertical-align: middle;\" class='+class3+'>'+ (row.refeicao === 0 ? '' : row.refeicao) +'</td>'
						+'<td style=\"vertical-align: middle;\" class='+class3+'>'+(row.intersticioInferior === 0 ? '' : row.intersticioInferior )+'</td>'
						+'<td style=\"vertical-align: middle;\" class='+class3+'>'+(row.intersticioSuperior === 0 ? '' : row.intersticioSuperior )+'</td>'
						+'<td style=\"vertical-align: middle;\" class='+class4+'>'+(totalNaEndossado)+'</td>'
						+'<td style=\"vertical-align: middle;\" class='+classeImpressao2+' style=\"'+corDeFundo2+'\">'+(100 - arrayPerformanceMedia[row.matricula]).toFixed(2)+' %</td>'
						+'<td style=\"vertical-align: middle;\" class='+classeImpressao+' style=\"'+corDeFundo+'\">'+(100 - arrayPerformanceBaixa[row.matricula]).toFixed(2)+' %</td>'
					+'</tr>';";
		} elseif (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "endossado") {
			$linha = " 
					const arrayPerformanceBaixa = $jsonPerfomanceBaixa;
					const arrayPerformanceMedia = $jsonPerfomanceMedia;
					console.log(100 - arrayPerformanceMedia[row.matricula]);
					porcentagemBaixa = 100 - arrayPerformanceBaixa[row.matricula];
					porcentagemMedia = 100 - arrayPerformanceMedia[row.matricula];

					let corDeFundo = '';
					let classeImpressao = '';
					if(porcentagemBaixa >= 75 && porcentagemBaixa <= 100){
						corDeFundo = 'background-color: lightgreen;';
						classeImpressao = 'impressao-verde';
					} else if(porcentagemBaixa <= 75 && porcentagemBaixa >= 50){
						corDeFundo = 'background-color: var(--var-yellow2);';
						classeImpressao = 'impressao-amarelo';
					} else if(porcentagemBaixa <= 50 && porcentagemBaixa >= 25){
						corDeFundo = 'background-color: var(--var-lightorange);';
						classeImpressao = 'impressao-laranja';
					}else{
						corDeFundo = 'background-color: var(--var-lightred);';
						classeImpressao = 'impressao-vermelho';
					}

					let corDeFundo2 = '';
					let classeImpressao2 = '';

					if (porcentagemMedia >= 75 && porcentagemMedia <= 100) {
						corDeFundo2 = 'background-color: lightgreen;';
						classeImpressao2 = 'impressao-verde';
					} else if (porcentagemMedia < 75 && porcentagemMedia >= 50) {
						corDeFundo2 = 'background-color: var(--var-yellow2);';
						classeImpressao2 = 'impressao-amarelo';
					} else if (porcentagemMedia < 50 && porcentagemMedia >= 25) {
						corDeFundo2 = 'background-color: var(--var-lightorange);';
						classeImpressao2 = 'impressao-laranja';
					} else {
						corDeFundo2 = 'background-color: var(--var-lightred);';
						classeImpressao2 = 'impressao-vermelho';
					}

								
			";
			
			$linha .= "linha = '<tr style=\"'+(totalNaEndossado === 0 ? 'background-color: lightgreen;' : '')+'\">'";
			$linha .= "+'<td>'+row.matricula+'</td>'
						+'<td>'+row.nome+'</td>'
						+'<td>'+row.ocupacao+'</td>'
						+'<td>'+row.tipoOperacaoNome+'</td>'
						+'<td>'+(row.setorNome?? '')+'</td>'
                    	+'<td>'+(row.subsetorNome?? '')+'</td>'
						+'<td class='+class1+'>'+(row.jornadaPrevista === 0 ? '' : row.jornadaPrevista )+'</td>'
						+'<td class='+class2+'>'+(row.jornadaEfetiva	=== 0 ? '' : row.jornadaEfetiva )+'</td>'
						+'<td class='+class2+'>'+(row.mdc === 0 ? '' : row.mdc )+'</td>'
						+'<td class='+class3+'>'+ (row.refeicao === 0 ? '' : row.refeicao) +'</td>'
						+'<td class='+class3+'>'+(row.intersticioInferior === 0 ? '' : row.intersticioInferior )+'</td>'
						+'<td class='+class3+'>'+(row.intersticioSuperior === 0 ? '' : row.intersticioSuperior )+'</td>'
						+'<td class='+class4+'>'+(totalNaEndossado)+'</td>'
						+'<td class='+classeImpressao2+' style=\"'+corDeFundo2+'\">'+(100 - arrayPerformanceMedia[row.matricula]).toFixed(2)+' %</td>'
						+'<td class='+classeImpressao+' style=\"'+corDeFundo+'\">'+(100 - arrayPerformanceBaixa[row.matricula]).toFixed(2)+' %</td>'
					+'</tr>';";
		} else {
			
			$linha = "
				var empresaId = (row.urlArquivo.match(/\/(\d+)\//) || [null,null])[1];
				var nomeEmpresa = (window.EMPRESA_NOMES && empresaId) ? window.EMPRESA_NOMES[empresaId] : (empresaId || 'Empresa');
				var rowStyle = (totalNaEndossado === 0 ? 'background-color: lightgreen;' : '');
				linha = '<tr style=\"'+rowStyle+'\">'
					+ '<td><a href=\"#\" style=\"text-decoration: none; color: black;\" onclick=\"setAndSubmit(' + empresaId + '); return false;\">' + nomeEmpresa + '</a></td>'
					+ '<td class='+class1+'>' + (row.espera === 0 ? '' : row.espera) + '</td>'
					+ '<td class='+class1+'>' + (row.descanso === 0 ? '' : row.descanso) + '</td>'
					+ '<td class='+class1+'>' + (row.repouso === 0 ? '' : row.repouso) + '</td>'
					+ '<td class='+class1+'>' + (row.jornada === 0 ? '' : row.jornada) + '</td>'
					+ '<td class='+class1+'>' + (row.jornadaPrevista === 0 ? '' : (row.jornadaPrevista || 0)) + '</td>'
					+ '<td class='+class2+'>' + (row.jornadaEfetiva === 0 ? '' : row.jornadaEfetiva) + '</td>'
					+ '<td class='+class2+'>' + (row.mdc === 0 ? '' : row.mdc) + '</td>'
					+ '<td class='+class3+'>' + (row.refeicao === 0 ? '' : row.refeicao) + '</td>'
					+ '<td class='+class3+'>' + (row.intersticioInferior === 0 ? '' : row.intersticioInferior) + '</td>'
					+ '<td class='+class3+'>' + (row.intersticioSuperior === 0 ? '' : row.intersticioSuperior) + '</td>'
					+ '<td class='+class4+'>' + (totalNaEndossado) + '</td>'
				+ '</tr>';
			";
		}

		$carregarDados = "";
		foreach ($arquivos as $arquivo) {
			$carregarDados .= "carregarDados('".$arquivo. "');";
			}

			echo
				"<form name='myForm' method='post' action='".htmlspecialchars($_SERVER["PHP_SELF"]). "'>
					<input type='hidden' name='acao'>
					<input type='hidden' name='campoAcao'>
					<input type='hidden' name='empresa'>
					<input type='hidden' name='busca_dataMes'>
					<input type='hidden' name='busca_dataInicio'>
					<input type='hidden' name='busca_dataFim'>
					<input type='hidden' name='busca_ocupacao'>
					<input type='hidden' name='operacao'>
					<input type='hidden' name='busca_setor'>
					<input type='hidden' name='busca_subsetor'>
					<input type='hidden' name='busca_endossado'>
					<input type='hidden' name='reloadOnly'>
					<input type='hidden' name='busca_data'>
				</form>
				<script>
					function setAndSubmit(empresa){
						document.myForm.acao.value = 'enviarForm';
						document.myForm.campoAcao.value = 'buscar';
						document.myForm.empresa.value = empresa;
						document.myForm.busca_dataMes.value = document.getElementById('busca_dataMes').value;
						document.myForm.busca_ocupacao.value = document.querySelector('[name=\"busca_ocupacao\"]').value;
						var opEl = document.querySelector('[name=\"operacao\"]');
						var setEl = document.querySelector('[name=\"busca_setor\"]');
						var subEl = document.querySelector('[name=\"busca_subsetor\"]');
						var endEl = document.querySelector('[name=\"busca_endossado\"]');
						document.myForm.operacao.value = opEl ? opEl.value : '';
						document.myForm.busca_setor.value = setEl ? setEl.value : '';
						document.myForm.busca_subsetor.value = subEl ? subEl.value : '';
						document.myForm.busca_endossado.value = endEl ? endEl.value : '';
						document.myForm.reloadOnly.value = '';
						document.myForm.submit();
					}

				function atualizarPainel(){
					console.info(document.getElementById('busca_data').value);
					document.myForm.empresa.value = document.getElementById('empresa').value;
					document.myForm.busca_data.value = document.getElementById('busca_data').value;
					document.myForm.busca_ocupacao.value = document.querySelector('[name=\"busca_ocupacao\"]').value;
					document.myForm.acao.value = 'atualizar';
					document.myForm.submit();
				}

				function imprimir(){
					window.print();
				}
							
				$(document).ready(function(){
					var tabela = $('#tabela-empresas tbody');
                    var arrayPerformanceMedia = $jsonPerfomanceMedia;
                    var arrayPerformanceBaixa = $jsonPerfomanceBaixa;
                    var ocupacoesPermitidas = '".$_POST["busca_ocupacao"]."';
                    var operacaoPermitidas = '".$_POST["operacao"]."';
                    var setorPermitidas = '".$_POST["busca_setor"]."';
                    var subSetorPermitidas = '".$_POST["busca_subsetor"]."';
                    var arquivos = ".json_encode($arquivos).";
                    var todosDados = [];
                    var arquivosCarregados = 0;

                    function finalizarProcessamento() {
                        // Ordenação personalizada: Perf Média (Desc) -> Perf Baixa (Desc) -> Gravidade Baixa (Asc) -> Gravidade Média (Asc) -> Gravidade Alta (Asc)
                        todosDados.sort(function(a, b) {
                            // 1. Performance Média (Desc) - Maior é melhor
                            var pmA = 100 - (arrayPerformanceMedia[a.matricula] || 0);
                            var pmB = 100 - (arrayPerformanceMedia[b.matricula] || 0);
                            if (Math.abs(pmA - pmB) > 0.001) return pmB - pmA;

                            // 2. Performance Baixa (Desc) - Maior é melhor (menos NCs no total)
                            var pbA = 100 - (arrayPerformanceBaixa[a.matricula] || 0);
                            var pbB = 100 - (arrayPerformanceBaixa[b.matricula] || 0);
                            if (Math.abs(pbA - pbB) > 0.001) return pbB - pbA;

                            // 3. Gravidade Alta (Asc) - Menos é melhor (Critério principal de gravidade)
                            var altaA = Number(a.refeicao || 0) + Number(a.intersticioInferior || 0) + Number(a.intersticioSuperior || 0);
                            var altaB = Number(b.refeicao || 0) + Number(b.intersticioInferior || 0) + Number(b.intersticioSuperior || 0);
                            if (altaA !== altaB) return altaA - altaB;

                            // 4. Gravidade Média (Asc) - Menos é melhor
                            var mediaA = Number(a.jornadaEfetiva || 0) + Number(a.mdc || 0);
                            var mediaB = Number(b.jornadaEfetiva || 0) + Number(b.mdc || 0);
                            if (mediaA !== mediaB) return mediaA - mediaB;

                            // 5. Gravidade Baixa (Asc) - Menos é melhor
                            var baixaA = Number(a.falta || 0) + Number(a.espera || 0) + Number(a.descanso || 0) + Number(a.repouso || 0) + Number(a.jornada || 0) + Number(a.jornadaPrevista || 0);
                            var baixaB = Number(b.falta || 0) + Number(b.espera || 0) + Number(b.descanso || 0) + Number(b.repouso || 0) + Number(b.jornada || 0) + Number(b.jornadaPrevista || 0);
                            return baixaA - baixaB;
                        });

                        // Renderização
                        $.each(todosDados, function(index, row) {
                            var totalNaEndossado = Number(row.falta || 0) + Number(row.jornadaEfetiva || 0) + Number(row.refeicao || 0) 
                            + Number(row.espera || 0) + Number(row.descanso || 0) + Number(row.repouso || 0) + Number(row.jornada || 0) 
                            + Number(row.mdc || 0) + Number(row.intersticioInferior || 0) + Number(row.intersticioSuperior || 0);
                            var totalEndossado = Number(row.refeicao || 0) + Number(row.falta || 0) + Number(row.jornadaEfetiva || 0) 
                            + Number(row.mdc || 0) + Number(row.intersticioInferior || 0) + Number(row.intersticioSuperior || 0);

                            let class1 = '';
                            let class2 = '';
                            let class3 = '';
                            let class4 = '';

                            if (totalNaEndossado === 0) {
                                class1 = 'highlighted';
                                class2 = 'highlighted';
                                class3 = 'highlighted';
                                class4 = 'highlighted';
                            } else {
                                class1 = 'baixaGravidade';
                                class2 = 'mediaGravidade';
                                class3 = 'altaGravidade';
                                class4 = 'total';
                            }
                            
                            let linha = '';
                            " . $linha . "

                            tabela.append(linha);
                        });
                    }

                    if (arquivos.length > 0) {
                        $.each(arquivos, function(i, url) {
                            $.ajax({
                                url: url + '?v=' + new Date().getTime(),
                                dataType: 'json',
                                success: function(data) {
                                    var row = data;
									row.urlArquivo = url;
                                    // Filtros
                                    var ocSel = ocupacoesPermitidas;
                                    var opSel = operacaoPermitidas;
                                    var setSel = setorPermitidas;
                                    var subSel = subSetorPermitidas;
                                    if (
                                        (ocSel.length > 0 && String(row.ocupacao) !== String(ocSel)) ||
                                        (opSel.length > 0 && String(row.tipoOperacao) !== String(opSel)) ||
                                        (setSel.length > 0 && String(row.setor) !== String(setSel)) ||
                                        (subSel.length > 0 && String(row.subsetor) !== String(subSel))
                                    ) {
                                        return;
                                    }
                                    todosDados.push(row);
                                },
                                error: function() {
                                    console.log('Erro ao carregar ' + url);
                                },
                                complete: function() {
                                    arquivosCarregados++;
                                    if (arquivosCarregados === arquivos.length) {
                                        finalizarProcessamento();
                                    }
                                }
                            });
                        });
                    } else {
                        // Nada a carregar
                    }

					function ordenarTabela(coluna, ordem) {
					var linhas = tabela.find('tr').get();

					linhas.sort(function (a, b) {
						// Extrai os valores da coluna
						var valorA = $(a).children('td').eq(coluna).text().trim();
						var valorB = $(b).children('td').eq(coluna).text().trim();

						// Tenta converter os valores em números
						var numA = parseFloat(valorA.replace(',', '.'));
						var numB = parseFloat(valorB.replace(',', '.'));

						if (!isNaN(numA) && !isNaN(numB)) {
							// Comparação numérica
							return ordem === 'asc' ? numA - numB : numB - numA;
						} else {
							// Caso os valores não sejam números, trata como texto
							valorA = valorA.toUpperCase();
							valorB = valorB.toUpperCase();

							if (valorA < valorB) {
								return ordem === 'asc' ? -1 : 1;
							}
							if (valorA > valorB) {
								return ordem === 'asc' ? 1 : -1;
							}
							return 0;
						}
					});

					// Reinsere as linhas ordenadas na tabela
					$.each(linhas, function (index, row) {
						tabela.append(row);
					});
				}

				$('#titulos th').click(function () {
					var coluna = $(this).index(); // Obtém o índice da coluna clicada
					var ordem = $(this).data('order'); // Obtém a ordem atual (asc/desc)

					// Redefinir ordem de todas as colunas
					$('#tabela-empresas th').data('order', 'desc');
					$(this).data('order', ordem === 'desc' ? 'asc' : 'desc');

					// Chama a função de ordenação
					ordenarTabela(coluna, $(this).data('order'));

					// Ajustar classes para setas de ordenação
					$('#titulos th').removeClass('sort-asc sort-desc');
					$(this).addClass($(this).data('order') === 'asc' ? 'sort-asc' : 'sort-desc');
				});


					
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

                $(document).ready(function() {
                    // Obtém o botão
                    const button = document.getElementById('botaoContexBuscar');

                    // Inicializa o select2 no campo 'empresa'
                    $('#empresa').select2();

                    function isAtualizar() {
                        var campos = document.getElementsByName('campoAcao');
                        return campos.length > 1 && campos[1].checked;
                    }

                    function toggleButtonState() {
                        button.removeAttribute('disabled');
                    }

                    toggleButtonState();

                    // Escuta o evento 'select2:select' para capturar quando uma nova opção é selecionada
                    $('#empresa').on('select2:select', function(e) {
                        toggleButtonState();
                    });

                    // Escuta o evento 'select2:unselect' para capturar quando uma opção é desmarcada (se múltiplo)
                    $('#empresa').on('select2:unselect', function(e) {
                        toggleButtonState();
                    });

                    var camposAcao = document.getElementsByName('campoAcao');
                    if (camposAcao && camposAcao.length > 1) {
                        camposAcao[0].addEventListener('change', toggleButtonState);
                        camposAcao[1].addEventListener('change', toggleButtonState);
                    }
                });
            </script>"
        ;
    }

    function index() {
        include __DIR__.'/../check_permission.php';
        verificaPermissao('/paineis/nc_juridica.php');
		$encontrado = '';
		$totaisFuncionario= [];
		$totaisFuncionario2 = [];
		$totaisMediaFuncionario = [];

		if (empty($_POST["busca_dataMes"])) {
			$_POST["busca_dataMes"] = date("Y-m");
		}

		if (!empty($_POST["acao"]) && $_POST["acao"] == "buscar") {

			if ($_POST["busca_dataMes"] > date("Y-m")) {
				unset($_POST["acao"]);
				$_POST["errorFields"][] = "busca_dataMes";
				set_status("ERRO: Não é possível pesquisar após a data atual.");
			}
			cabecalho("Relatório de Não Conformidade Jurídica Atualizado");
		} elseif (!empty($_POST["acao"]) && $_POST["acao"] == "atualizarPainel") {
			echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
			if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_flush(); }
			flush();

			cabecalho("Relatório de Não Conformidade Jurídica Atualizado");

			$err = (!empty($_POST["busca_dataInicio"]) && $_POST["busca_dataInicio"] > date("Y-m-d"))*1+(!empty($_POST["busca_dataFim"]) && $_POST["busca_dataFim"] > date("Y-m-d"))*2;
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
                if (empty($_POST["empresa"])) {
                    relatorio_nao_conformidade_juridica_todas();
                } else {
                    relatorio_nao_conformidade_juridica(intval($_POST["empresa"]));
                }
            }
        } else {
            cabecalho("Relatório de Não Conformidade Jurídica Atualizado");
        }

	// $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
	//position: absolute; top: 101px; left: 420px;

	$campoAcao =
	"<div class='col-sm-2 margin-bottom-5' style='min-width: fit-content;'>
				<label>"."Ação"."</label><br>
				<label class='radio-inline'>
					<input type='radio' name='campoAcao' value='buscar' ".((empty($_POST["campoAcao"]) || $_POST["campoAcao"] == "buscar") ? "checked" : "")."> Buscar
				</label>
				<label class='radio-inline'>
          			<input type='radio' name='campoAcao' value='atualizarPainel'".(!empty($_POST["campoAcao"]) && $_POST["campoAcao"] == "atualizarPainel" ? "checked" : "")."> Atualizar
				</label>
			</div>";

		$temSubsetorVinculado = false;
		if (!empty($_POST["busca_setor"])) {
			$rowCount = mysqli_fetch_array(query("SELECT COUNT(*) FROM sbgrupos_documentos WHERE sbgr_tx_status = 'ativo' AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"]).";"));
			$temSubsetorVinculado = ($rowCount[0] > 0);
		}

        $ocupacoesOptions = ["" => "Todos"];
        $resOcup = query("SELECT DISTINCT enti_tx_ocupacao FROM entidade WHERE enti_tx_ocupacao IS NOT NULL AND enti_tx_ocupacao <> '' ORDER BY enti_tx_ocupacao ASC");
        while($rowO = mysqli_fetch_assoc($resOcup)){
            $v = $rowO["enti_tx_ocupacao"];
            $ocupacoesOptions[$v] = $v;
        }

        $cargoOptions = ["" => "Todos"];
        $extraEmp = (!empty($_POST["empresa"]) ? " AND e.enti_nb_empresa = ".intval($_POST["empresa"]) : "");
        $resCargo = query(
            "SELECT DISTINCT oper.oper_nb_id AS id, oper.oper_tx_nome AS nome
             FROM operacao oper
             JOIN entidade e ON e.enti_tx_tipoOperacao = oper.oper_nb_id
             WHERE oper.oper_tx_status = 'ativo' AND e.enti_tx_status = 'ativo'".$extraEmp.
             " ORDER BY oper.oper_tx_nome ASC"
        );
        while($rowC = mysqli_fetch_assoc($resCargo)){
            $cargoOptions[$rowC["id"]] = $rowC["nome"];
        }

        $campos = [
            combo_net("Empresa", "empresa", $_POST["empresa"]?? $_SESSION["user_nb_empresa"], 4, "empresa", ""),
            $campoAcao,
            campo_mes("Mês*", "busca_dataMes", ($_POST["busca_dataMes"] ?? date("Y-m")), 2),
            combo("Ocupação", "busca_ocupacao", ($_POST["busca_ocupacao"] ?? ""), 2, $ocupacoesOptions),
            combo("Cargo", "operacao", ($_POST["operacao"]?? ""), 2, $cargoOptions),
            combo_bd("!Setor", 		"busca_setor", 	($_POST["busca_setor"]?? ""), 	2, "grupos_documentos", "onchange=\"(function(f){ if(f.busca_subsetor){ f.busca_subsetor.value=''; } f.reloadOnly.value='1'; f.submit(); })(document.contex_form);\"")
        ];
		if ($temSubsetorVinculado) {
			$campos[] = combo_bd("!Subsetor", 	"busca_subsetor", 	($_POST["busca_subsetor"]?? ""), 	2, "sbgrupos_documentos", "", " AND sbgr_nb_idgrup = ".intval($_POST["busca_setor"])." ORDER BY sbgr_tx_nome ASC");
		}
		$campos[] = combo("Tipo",	"busca_endossado", (!empty($_POST["busca_endossado"]) ? $_POST["busca_endossado"] : ""), 2, ["naoEndossado" => "Atualizado","endossado" => "Pós-fechamento", "semAjustes"=>"Sem ajuste"]);
		$campos[] = combo("Rankeamento", "ranking_type", ($_POST["ranking_type"] ?? "nao"), 2, ["nao" => "Não", "setor" => "Por Setor", "funcionario" => "Por Funcionário"]);
		$campos[] = combo("Limite Rank TOP", "ranking_limit", (string)($_POST["ranking_limit"] ?? "20"), 2, ["10" => "10", "20" => "20", "50" => "50", "100" => "100", "todos" => "Todos"]);

		$botao_volta = "";
		if (!empty($_POST["empresa"])) {
			$botao_volta = "<button class='btn default' type='button' onclick='setAndSubmit(\"\")'>Voltar</button>";
		}
		// $botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";
		$botao_imprimir = "<button class='btn default' type='button' onclick='enviarDados()'>Imprimir</button>";

		$buttons = [
			botao("Buscar", "enviarForm", "", "", "", "", "btn btn-info"),
			$botao_imprimir,
			$botao_volta
		];


		echo abre_form();
		echo campo_hidden("reloadOnly", "");
		echo linha_form($campos);
		echo fecha_form($buttons);


		$arquivos = [];
		$dataEmissao = ""; //Utilizado no HTML
		$path = "./arquivos/nao_conformidade_juridica";
		$periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];
		$rankingCategorias = [];
		$rankingValores = [];
		$rankingTitulo = "";
		$donutSubsetorLabels = [];
		$donutSubsetorValues = [];

		$totais = [
			"jornadaSemRegistro" => 0,
			"refeicaoSemRegistro" => 0,
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

        $periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];
        if (!empty($_POST["busca_dataMes"])) {
            try {
                $periodoInicio = new DateTime($_POST["busca_dataMes"]."-01");
                $hoje = new DateTime();
                if ($periodoInicio->format("Y-m") === $hoje->format("Y-m")) {
                    $hoje->modify("-1 day");
                    $periodoFim = $hoje;
                } else {
                    $periodoFim = new DateTime($periodoInicio->format("Y-m-t"));
                }
                $periodoRelatorio = [
                    "dataInicio" => $periodoInicio->format("d/m/Y"),
                    "dataFim" => $periodoFim->format("d/m/Y")
                ];
            } catch (Exception $e) {
                $periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];
            }
        }

		if (!empty($_POST["empresa"]) && !empty($_POST["busca_dataMes"]) && empty($_POST["reloadOnly"])) {
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

				$totalizadores = [
					"refeicao" => 0,
					"jornadaEfetiva" => 0,
					"espera" => 0,
					"descanso" => 0,
					"repouso" => 0,
					"jornada" => 0,
					"mdc" => 0,
					"intersticioInferior" => 0,
					"intersticioSuperior" => 0,
					"jornadaExcedido10h" => 0,
					"jornadaExcedido12h" => 0,
					"mdcDescanso30m5h" => 0,
					"refeicaoSemRegistro" => 0,
					"refeicao1h" => 0,
					"refeicao2h" => 0,
					"faltaJustificada" => 0,
					"falta" => 0
				];

				$motoristas = 0;
				$ajudante = 0;
				$funcionario = 0;
				$totalJsonComTudoZero = 0;
				$totalDiasNaoCFuncionario = 0;
				$totaisFuncionario = [];
				$totaisFuncionario2 = [];
				$sumTotalMotorista = 0;
				$sumTotalNConformMax = 0;
                $totaisMediaFuncionario = [];
                $totaisGravidadeBaixa = [];
                $totaisGravidadeMedia = [];
                $totaisGravidadeAlta = [];
				$totalizadores["jornadaPrevista"] = 0;
				$ocupacoesPermitidas = $_POST['busca_ocupacao'];
				$operacaoSel = (string)($_POST['operacao'] ?? '');
				$setorSel = (string)($_POST['busca_setor'] ?? '');
				$subsetorSel = (string)($_POST['busca_subsetor'] ?? '');
				$arquivosConsiderados = 0;
				$mediaPerfTotal = 0;
				$funcionarioNomes = [];
				$rankingSetorDias = [];
				$rankingSetorNC = [];
				$rankingSetorNome = [];
				$donutSubsetorAgg = [];
				$donutSubsetorNome = [];
				foreach ($arquivos as &$arquivo) {
					$todosZeros = true;
					$arquivo = $path."/".$arquivo;
					$json = json_decode(file_get_contents($arquivo), true);

                    $ocupacaoJson = $json['ocupacao'] ?? '';
                    if (!empty($ocupacoesPermitidas) && $ocupacaoJson !== $ocupacoesPermitidas) {
                        continue;
                    }
                    if ($operacaoSel !== '' && (string)($json['tipoOperacao'] ?? '') !== $operacaoSel) {
                        continue;
                    }
                    if ($setorSel !== '' && (string)($json['setor'] ?? '') !== $setorSel) {
                        continue;
                    }
                    if ($subsetorSel !== '' && (string)($json['subsetor'] ?? '') !== $subsetorSel) {
                        continue;
                    }

                    $arquivosConsiderados++;

					$totalMotorista = $json["espera"]+$json["descanso"]+$json["repouso"]+$json["jornada"]+$json["falta"]+$json["jornadaEfetiva"]+$json["mdc"]
					+$json["refeicao"]+$json["intersticioInferior"]+$json["intersticioSuperior"];
					
					$totalDiasNaoCFuncionario += $json["diasConformidade"];

					$data = DateTime::createFromFormat('d/m/Y', $json["dataInicio"]);
					$dias = $data->format('t');
					
                    // Calcular totais de gravidade para o funcionário atual
                    $gBaixa = (int)($json["falta"] ?? 0) + (int)($json["espera"] ?? 0) + (int)($json["descanso"] ?? 0) + (int)($json["repouso"] ?? 0) + (int)($json["jornada"] ?? 0) + (int)($json["jornadaPrevista"] ?? 0);
                    $gMedia = (int)($json["jornadaEfetiva"] ?? 0) + (int)($json["mdc"] ?? 0);
                    $gAlta = (int)($json["refeicao"] ?? 0) + (int)($json["intersticioInferior"] ?? 0) + (int)($json["intersticioSuperior"] ?? 0);

                    // Penalidades para ajuste fino da % (High=0.1, Med=0.05, Low=0.01)
                    $penalty = ($gAlta * 0.1) + ($gMedia * 0.05) + ($gBaixa * 0.01);

					$mediaPerfFuncionario = round((($json["diasConformidade"]/ $dias) * 100) + $penalty, 2);
					
                    $denDias = ($dias * max($arquivosConsiderados, 1));
                    $mediaPerfTotal = round(($totalDiasNaoCFuncionario/ $denDias * 100), 2);

					$mediaPerfTotal= 100 - $mediaPerfTotal;

					$totaisMediaFuncionario[$json["matricula"]] = $mediaPerfFuncionario;
                    $totaisGravidadeBaixa[$json["matricula"]] = $gBaixa;
                    $totaisGravidadeMedia[$json["matricula"]] = $gMedia;
                    $totaisGravidadeAlta[$json["matricula"]] = $gAlta;
					$funcionarioNomes[$json["matricula"]] = $json["nome"] ?? (string)$json["matricula"];
					$sid = (string)($json["setor"] ?? "");
					$rankingSetorDias[$sid] = ($rankingSetorDias[$sid] ?? 0) + (int)$dias;
					$rankingSetorNC[$sid] = ($rankingSetorNC[$sid] ?? 0) + (int)($json["diasConformidade"] ?? 0);
					$rankingSetorNome[$sid] = (!empty($json["setorNome"]) ? $json["setorNome"] : ($sid !== "" ? $sid : "Sem Setor"));
					$suid = (string)($json["subsetor"] ?? "");
					$donutSubsetorAgg[$suid] = ($donutSubsetorAgg[$suid] ?? 0) + (int)$totalMotorista;
					$donutSubsetorNome[$suid] = (!empty($json["subsetorNome"]) ? $json["subsetorNome"] : ($suid !== "" ? $suid : "Sem Subsetor"));

					$totalNConformMax = 4 * $dias;

					$sumTotalMotorista += $totalMotorista;
					$sumTotalNConformMax += $totalNConformMax;

					// Baixar performance funcionario
					$porcentagemFunNCon2 = min(100, round(($totalMotorista *100) / $totalNConformMax, 2));

					$totaisFuncionario2[$json["matricula"]] = $porcentagemFunNCon2;

					foreach ($totalizadores as $key => &$total) {
						if(!in_array($key, ['faltaJustificada', 'jornadaPrevista']) && (!isset($json[$key]) || $json[$key] != 0)) {
							$todosZeros = false; // Algum campo não está zerado
							// break;
						}
						$total += $json[$key] ?? 0; // incrementa apenas se o índice existir no JSON
					}

					if ($todosZeros) {
						$totalJsonComTudoZero++; // Incrementa o contador
					}

					if ($json["ocupacao"] === "Motorista") {
						$motoristas++;
					}

					if ($json["ocupacao"] === "Ajudante") {
						$ajudante++;
					}

					if ($json["ocupacao"] === "Funcionário") {
						$funcionario++;
					}

				unset($total);
			}

			if ($sumTotalNConformMax > 0) {
				$porcentagemTotalBaixa = min(100, round(($sumTotalMotorista * 100) / $sumTotalNConformMax, 2));
			} else {
				$porcentagemTotalBaixa = 0;
			}
			if ($arquivosConsiderados === 0) { $mediaPerfTotal = 0; }
            $totalFun = max($arquivosConsiderados - $totalJsonComTudoZero, 0);
			$porcentagemTotalBaixaG = 100 - $porcentagemTotalBaixa;
            if ($arquivosConsiderados > 0 && count($totaisMediaFuncionario) > 0) {
                $invertidos = array_map(function($v){ return max(0, min(100, 100 - (float)$v)); }, $totaisMediaFuncionario);
                $porcentagemTotalMedia = round(array_sum($invertidos) / max(count($invertidos), 1), 2);
                $mediaPerfTotal = $porcentagemTotalMedia;
            }
				$porcentagemTotalBaixa2= (array) $totaisFuncionario2;
				if (!empty($_POST["ranking_type"]) && $_POST["ranking_type"] !== "nao") {
					if ($_POST["ranking_type"] === "funcionario") {
						$pairs = [];
						$rankingMatriculas = [];
						$rankingFotos = [];
						foreach ($totaisMediaFuncionario as $mat => $val) {
							$perf = max(0, min(100, 100 - (float)$val));
							$label = $funcionarioNomes[$mat] ?? (string)$mat;
							$pairs[] = ["label" => $label, "value" => round($perf, 2), "matricula" => (string)$mat];
						}
						usort($pairs, function($a, $b) use ($totaisFuncionario2, $totaisGravidadeBaixa, $totaisGravidadeMedia, $totaisGravidadeAlta) {
                            // 1. Performance Média (Desc)
                            if (abs($b["value"] - $a["value"]) > 0.001) {
                                return $b["value"] <=> $a["value"];
                            }
                            
                            // 2. Performance Baixa (Desc)
                            $perfBaixaA = $totaisFuncionario2[$a["matricula"]] ?? 0;
                            $perfBaixaB = $totaisFuncionario2[$b["matricula"]] ?? 0;
                            if (abs($perfBaixaB - $perfBaixaA) > 0.001) {
                                return $perfBaixaB <=> $perfBaixaA;
                            }

                            // 3. Gravidade Alta (Asc) - Menos é melhor
                            $gAltaA = $totaisGravidadeAlta[$a["matricula"]] ?? 0;
                            $gAltaB = $totaisGravidadeAlta[$b["matricula"]] ?? 0;
                            if ($gAltaA != $gAltaB) {
                                return $gAltaA <=> $gAltaB;
                            }

                            // 4. Gravidade Media (Asc)
                            $gMediaA = $totaisGravidadeMedia[$a["matricula"]] ?? 0;
                            $gMediaB = $totaisGravidadeMedia[$b["matricula"]] ?? 0;
                            if ($gMediaA != $gMediaB) {
                                return $gMediaA <=> $gMediaB;
                            }

                            // 5. Gravidade Baixa (Asc)
                            $gBaixaA = $totaisGravidadeBaixa[$a["matricula"]] ?? 0;
                            $gBaixaB = $totaisGravidadeBaixa[$b["matricula"]] ?? 0;
                            return $gBaixaA <=> $gBaixaB;
                        });
						$rankingLimit = (string)($_POST["ranking_limit"] ?? "20");
						if ($rankingLimit !== "todos") {
							$pairs = array_slice($pairs, 0, max(intval($rankingLimit), 1));
						}
						foreach ($pairs as $p) {
							$rankingCategorias[] = $p["label"];
							$rankingValores[] = $p["value"];
							$rankingMatriculas[] = $p["matricula"];
							$fotoUrl = "";
							$rowEnt = mysqli_fetch_assoc(query(
								"SELECT enti_tx_foto FROM entidade WHERE enti_tx_matricula = ? LIMIT 1",
								"s",
								[$p["matricula"]]
							));
							if (!empty($rowEnt["enti_tx_foto"])) {
								$fotoUrl = $rowEnt["enti_tx_foto"];
							} else {
								$rowUser = mysqli_fetch_assoc(query(
									"SELECT u.user_tx_foto FROM user u INNER JOIN entidade e ON u.user_nb_entidade = e.enti_nb_id WHERE e.enti_tx_matricula = ? LIMIT 1",
									"s",
									[$p["matricula"]]
								));
								if (!empty($rowUser["user_tx_foto"])) {
									$fotoUrl = $rowUser["user_tx_foto"];
								}
							}
							if ($fotoUrl === "") {
 								$fotoUrl = "../../contex20/img/user.png";
							} else {
								if (strpos($fotoUrl, 'arquivos/') === 0 && strpos($fotoUrl, '../') !== 0) {
									$fotoUrl = "../".$fotoUrl;
								}
								if (strpos($fotoUrl, '../arquivos/') === 0) {
									$pathCheck = dirname(__DIR__) . '/' . substr($fotoUrl, 3);
									if (!file_exists($pathCheck)) {
										$fotoUrl = "../../contex20/img/user.png";
									}
								}
							}
							$rankingFotos[] = $fotoUrl;
						}
						$rankingTitulo = "Ranking de Performance por Funcionário";
					} elseif ($_POST["ranking_type"] === "setor") {
						$pairs = [];
						foreach ($rankingSetorDias as $sid => $diasTot) {
							$ncTot = $rankingSetorNC[$sid] ?? 0;
							$perf = ($diasTot > 0) ? (100 - (($ncTot / $diasTot) * 100)) : 0;
							$perf = max(0, min(100, round($perf, 2)));
							$label = $rankingSetorNome[$sid] ?? ($sid !== "" ? $sid : "Sem Setor");
							$pairs[] = ["label" => $label, "value" => $perf];
						}
						usort($pairs, function($a, $b){ return $b["value"] <=> $a["value"]; });
						$rankingLimit = (string)($_POST["ranking_limit"] ?? "20");
						if ($rankingLimit !== "todos") {
							$pairs = array_slice($pairs, 0, max(intval($rankingLimit), 1));
						}
						foreach ($pairs as $p) {
							$rankingCategorias[] = $p["label"];
							$rankingValores[] = $p["value"];
						}
						$rankingTitulo = "Ranking de Performance por Setor";
						foreach ($donutSubsetorAgg as $suid => $val) {
							$donutSubsetorLabels[] = $donutSubsetorNome[$suid] ?? ($suid !== "" ? $suid : "Sem Subsetor");
							$donutSubsetorValues[] = (int)$val;
						}
					}
				}

				if (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "endossado"){
					$totalNaoconformidade = array_sum([
						$totalizadores["mdcDescanso30m5h"],
						$totalizadores["inicioRefeicaoSemRegistro"],
						$totalizadores["refeicaoSemRegistro"],
						$totalizadores["refeicao1h"],
						$totalizadores["refeicao2h"],
						$totalizadores["intersticioInferior"],
						$totalizadores["intersticioSuperior"],
						$totalizadores["jornadaPrevista"]
					]);

					$gravidadeAlta = $totalizadores["refeicao"] + $totalizadores["intersticioInferior"] + $totalizadores["intersticioSuperior"];
					$gravidadeMedia = $totalizadores["jornadaEfetiva"] + $totalizadores["mdc"];
					$gravidadeBaixa = $totalizadores["falta"];

				} else{
					$totalNaoconformidade = array_sum([
						$totalizadores["espera"],
						$totalizadores["descanso"],
						$totalizadores["repouso"],
						$totalizadores["jornada"],
						$totalizadores["faltaJustificada"],
						$totalizadores["falta"],
						$totalizadores["jornadaExcedido10h"],
						$totalizadores["jornadaExcedido12h"],
						$totalizadores["mdcDescanso30m5h"],
						$totalizadores["refeicaoSemRegistro"],
						$totalizadores["refeicao1h"],
						$totalizadores["refeicao2h"],
						$totalizadores["intersticioInferior"],
						$totalizadores["intersticioSuperior"]
					]);
					
					$gravidadeAlta = $totalizadores["refeicao"] + $totalizadores["intersticioInferior"] + $totalizadores["intersticioSuperior"];
					$gravidadeMedia = $totalizadores["jornadaEfetiva"] + $totalizadores["mdc"];
					$gravidadeBaixa = $totalizadores["falta"] + $totalizadores["espera"] + $totalizadores["descanso"] +
					$totalizadores["repouso"] + $totalizadores["jornada"];
				}

				$denAtivos = ($motoristas + $ajudante + $funcionario);
				$porcentagemFun = ($denAtivos > 0) ? (($totalJsonComTudoZero / $denAtivos) * 100) : 0;

				$totalGeral = $gravidadeAlta + $gravidadeMedia + $gravidadeBaixa;
				$graficoSintetico = [$gravidadeAlta, $gravidadeMedia, $gravidadeBaixa];

				$percentuais = [
					"performance" => $totalGeral > 0 ? round($totalJsonComTudoZero / $totalGeral) : 0,
					"alta" => $totalGeral > 0 ? round(($gravidadeAlta / $totalGeral) * 100, 2) : 0,
					"media" => $totalGeral > 0 ? round(($gravidadeMedia / $totalGeral) * 100, 2) : 0,
					"baixa" => $totalGeral > 0 ? round(($gravidadeBaixa / $totalGeral) * 100, 2) : 0
				];

				if ($_POST["busca_endossado"] !== "endossado") {
					// Campos dos graficos {
					$arrayTitulos = ['Espera', 'Descanso', 'Repouso', 'Jornada', 'Jornada Prevista', 'Jornada Efetiva', 'MDC',
					'Refeição', 'Interstício Inferior', 'Interstício Superior'];

					$arrayTitulos2 = [
					'Início ou Fim de espera sem registro',
					'Início ou Fim de repouso sem registro',
					'Inicio ou Fim de repouso sem registro',
					'Início ou fim de jornada sem registro',
					'Faltas justificadas',
					'Faltas não justificadas',
					'Tempo excedido de 10:00h de jornada efetiva',
					'Tempo excedido de 12:00h de jornada efetiva',
					'Descanso de 30 minutos a cada 05:30 de direção não respeitado.',
					'Batida de início ou fim de refeição não registrada',
					'Refeição ininterrupta maior que 1 hora não respeitada',
					'Tempo máximo de 2 horas para a refeição não respeitado',
					'O mínimo de 11 horas de interstício não foi respeitado',
					'Interstício total de 11 horas não respeitado'
					];

					$coresGrafico = ['#FFE800' ,'#FFE800' ,'#FFE800','#FFE800','#FFE800', '#FF8B00', '#FF8B00', '#a30000', '#a30000', '#a30000'];
					$coresGrafico2 = [
					'#FFE000', '#FFE800', '#FFE800', '#FFE800', '#FFE800', '#FFE800', '#FF8B00', '#FF8B00',
					'#FF8B00', '#ff0404', '#ff0404', '#ff0404', '#ff0404', '#ff0404', '#ff0404'];
					//}
					
					$keys = ["espera", "descanso", "repouso", "jornada", "falta", "jornadaEfetiva", "mdc", "refeicao",
					"intersticioInferior", "intersticioSuperior"];

					$keys2 = ["espera", "descanso", "repouso", "jornada", "faltaJustificada", "falta","jornadaExcedido10h", "jornadaExcedido12h",
					"mdcDescanso30m5h", "refeicaoSemRegistro", "refeicao1h",
					"refeicao2h", "intersticioInferior", "intersticioSuperior"];
				} else{
					// Campos dos graficos {
					$arrayTitulos = ['Jornada Prevista', 'Jornada Efetiva', 'MDC',
					'Refeição', 'Interstício Inferior', 'Interstício Superior'];

					$arrayTitulos2 = [
					'Faltas justificadas',
					'Faltas não justificadas',
					'Tempo excedido de 10:00h de jornada efetiva',
					'Tempo excedido de 12:00h de jornada efetiva',
					'Descanso de 30 minutos a cada 05:30 de direção não respeitado.',
					'Descanso de 30 minutos não respeitado',
					'Descanso de 15 minutos não respeitado',
					'Batida de início ou fim de refeição não registrada',
					'Refeição ininterrupta maior que 1 hora não respeitada',
					'Tempo máximo de 2 horas para a refeição não respeitado',
					'O mínimo de 11 horas de interstício não foi respeitado',
					'Interstício total de 11 horas não respeitado'
					];

					$coresGrafico = ['#FFE800', '#FF8B00', '#FF8B00', '#a30000', '#a30000', '#a30000'];
					$coresGrafico2 = ['#FFE800', '#FFE800', '#FF8B00', '#FF8B00', '#FF8B00', '#FF8B00',
					'#FF8B00', '#ec4141', '#ec4141', '#ec4141', '#ec4141', '#ec4141', '#ec4141'];
					//}

					$keys = ["jornadaPrevista", "falta", "mdc", "refeicao","intersticioInferior", "intersticioSuperior"];

					$keys2 = ["faltaJustificada", "falta", "jornadaExcedido10h", "jornadaExcedido12h", "mdcDescanso30m5h", "refeicaoSemRegistro", "refeicao1h", "refeicao2h", "intersticioInferior",
					"intersticioSuperior"];
				}

				// Percentuais gerais de Não Conformidade (baseado no total geral)
				foreach ($keys as $key) {
					$percentuais["Geral_".$key] = $totalGeral > 0 
						? number_format(round(($totalizadores[$key] / $totalGeral) * 100, 2), 2) 
						: "0.00";
					
					$graficoAnalitico[] = $totalizadores[$key];
				}
	
				// Percentuais específicos de Não Conformidade (baseado no total de não conformidade)
				foreach ($keys2 as $key)  {
					if ($totalNaoconformidade > 0 && isset($totalizadores[$key])) {
						$percentuais["Especifico_".$key] =  number_format(round(($totalizadores[$key] / $totalNaoconformidade) * 100, 2),2);
					} else {
						$percentuais["Especifico_".$key] = 0;
					}
					$graficoDetalhado[] = $totalizadores[$key];
				}

				if (!empty($arquivo)) {
					$dataArquivo = date("d/m/Y", filemtime($arquivo));
                    $horaArquivo = date("H:i", filemtime($arquivo));

                    $dataAtual = date("d/m/Y");
                    $horaAtual = date("H:i");
                    if($dataArquivo != $dataAtual){
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
					$dataEmissao = $alertaEmissao ." Atualizado em: ".date("d/m/Y H:i", filemtime($arquivo))."</span>"; //Utilizado no HTML.
					$arquivoGeral = json_decode(file_get_contents($arquivo), true);

					$periodoRelatorio = [
						"dataInicio" => $arquivoGeral["dataInicio"],
						"dataFim" => $arquivoGeral["dataFim"]
					];


					$encontrado = true;
				} 
				// else {
				// 	echo "<script>alert('Não tem não conformidades.')</script>";
				// }

				$pasta = dir($path);
                while($arquivoEmpresa = $pasta->read()){
                    if(!empty($arquivoEmpresa) && !in_array($arquivoEmpresa, [".", ".."]) && !is_bool(strpos($arquivoEmpresa, "empresa_"))){
                        $arquivoEmpresa = $path."/".$arquivoEmpresa;
						$totalempre = json_decode(file_get_contents($arquivoEmpresa), true);
                    }
                }
                $pasta->close();

				$diasMesRef = (int)date('t', strtotime((!empty($_POST['busca_dataMes']) ? $_POST['busca_dataMes'] : date('Y-m')).'-01'));
				$denEmpresa = ($diasMesRef * max(sizeof($arquivos), 1));
				$baseDiasConf = (float)($totalempre["diasConformidade"] ?? 0);
				$percentual = ($denEmpresa > 0) ? (($baseDiasConf / $denEmpresa) * 100) : 0;
				$percentualConformidade = 100 - $percentual;
				if (empty($_POST["busca_ocupacao"]) && empty($_POST["operacao"]) && empty($_POST["busca_setor"]) && empty($_POST["busca_subsetor"])) {
					$porcentagemTotalMedia = round($percentualConformidade, 2);
				}

				// $data = DateTime::createFromFormat('d/m/Y', $json["dataInicio"]);
				// $dias = $data->format('t');
				
			} else {
				$encontrado = false;
			}
		} 
		elseif (empty($_POST["empresa"]) && !empty($_POST["busca_dataMes"]) && empty($_POST["reloadOnly"])) {
			$dirTipo = ($_POST["busca_endossado"] === "naoEndossado") ? "nao_endossado" : "endossado";
			$baseMes = $path."/".$_POST["busca_dataMes"];
			$aggFile = $baseMes."/".$dirTipo."/empresas.json";
			$empresaNomes = [];
			if (is_dir($baseMes)) {
				$dh = dir($baseMes);
				while (($ent = $dh->read()) !== false) {
					if ($ent === "." || $ent === "..") { continue; }
					if (!ctype_digit($ent)) { continue; }
					$empresaId = intval($ent);
					$empFile = $baseMes."/".$empresaId."/".$dirTipo."/empresa_".$empresaId.".json";
					if (file_exists($empFile)) {
						$arquivos[] = $empFile;
						$re = mysqli_fetch_assoc(query("SELECT empr_tx_nome FROM empresa WHERE empr_nb_id = ".$empresaId." LIMIT 1;"));
						$empresaNomes[$empresaId] = (!empty($re["empr_tx_nome"]) ? $re["empr_tx_nome"] : ("Empresa ".$empresaId));
					}
				}
				$dh->close();
			}
			if (file_exists($aggFile)) {
				$arquivoGeral = json_decode(file_get_contents($aggFile), true);
				$dataEmissao = " Atualizado em: ".date("d/m/Y H:i", filemtime($aggFile));
				$periodoRelatorio = [
					"dataInicio" => $arquivoGeral["dataInicio"] ?? "",
					"dataFim" => $arquivoGeral["dataFim"] ?? ""
				];
				$totalizadores = [
					"espera" => (int)($arquivoGeral["espera"] ?? 0),
					"descanso" => (int)($arquivoGeral["descanso"] ?? 0),
					"repouso" => (int)($arquivoGeral["repouso"] ?? 0),
					"jornada" => (int)($arquivoGeral["jornada"] ?? 0),
					"falta" => (int)($arquivoGeral["falta"] ?? 0),
					"jornadaEfetiva" => (int)($arquivoGeral["jornadaEfetiva"] ?? 0),
					"mdc" => (int)($arquivoGeral["mdc"] ?? 0),
					"refeicao" => (int)($arquivoGeral["refeicao"] ?? 0),
					"intersticioInferior" => (int)($arquivoGeral["intersticioInferior"] ?? 0),
					"intersticioSuperior" => (int)($arquivoGeral["intersticioSuperior"] ?? 0),
				];
				$totalGeral = array_sum($totalizadores);
                $gravidadeAlta = $totalizadores["refeicao"] + $totalizadores["intersticioInferior"] + $totalizadores["intersticioSuperior"];
                $gravidadeMedia = $totalizadores["jornadaEfetiva"] + $totalizadores["mdc"];
                $gravidadeBaixa = $totalizadores["falta"] + $totalizadores["espera"] + $totalizadores["descanso"] + $totalizadores["repouso"] + $totalizadores["jornada"];
                $percentuais = [
                    "alta" => $totalGeral > 0 ? round(($gravidadeAlta / $totalGeral) * 100, 2) : 0,
                    "media" => $totalGeral > 0 ? round(($gravidadeMedia / $totalGeral) * 100, 2) : 0,
                    "baixa" => $totalGeral > 0 ? round(($gravidadeBaixa / $totalGeral) * 100, 2) : 0,
                ];
                $totalizadores["faltaJustificada"] = (int)($totalizadores["faltaJustificada"] ?? 0);
                $totalizadores["jornadaExcedido10h"] = (int)($totalizadores["jornadaExcedido10h"] ?? 0);
                $totalizadores["jornadaExcedido12h"] = (int)($totalizadores["jornadaExcedido12h"] ?? 0);
                $totalizadores["mdcDescanso30m5h"] = (int)($totalizadores["mdcDescanso30m5h"] ?? 0);
                $totalizadores["refeicaoSemRegistro"] = (int)($totalizadores["refeicaoSemRegistro"] ?? 0);
                $totalizadores["refeicao1h"] = (int)($totalizadores["refeicao1h"] ?? 0);
                $totalizadores["refeicao2h"] = (int)($totalizadores["refeicao2h"] ?? 0);
                $arrayTitulos = ['Espera', 'Descanso', 'Repouso', 'Jornada', 'Jornada Prevista', 'Jornada Efetiva', 'MDC', 'Refeição', 'Interstício Inferior', 'Interstício Superior'];
                $arrayTitulos2 = [
                    'Início ou Fim de espera sem registro',
                    'Início ou Fim de repouso sem registro',
                    'Inicio ou Fim de repouso sem registro',
                    'Início ou fim de jornada sem registro',
                    'Faltas justificadas',
                    'Faltas não justificadas',
                    'Tempo excedido de 10:00h de jornada efetiva',
                    'Tempo excedido de 12:00h de jornada efetiva',
                    'Descanso de 30 minutos a cada 05:30 de direção não respeitado.',
                    'Batida de início ou fim de refeição não registrada',
                    'Refeição ininterrupta maior que 1 hora não respeitada',
                    'Tempo máximo de 2 horas para a refeição não respeitado',
                    'O mínimo de 11 horas de interstício não foi respeitado',
                    'Interstício total de 11 horas não respeitado'
                ];
                $coresGrafico = ['#FFE800' ,'#FFE800' ,'#FFE800','#FFE800','#FFE800', '#FF8B00', '#FF8B00', '#a30000', '#a30000', '#a30000'];
                $coresGrafico2 = [
                    '#FFE000', '#FFE800', '#FFE800', '#FFE800', '#FFE800', '#FFE800', '#FF8B00', '#FF8B00',
                    '#FF8B00', '#ff0404', '#ff0404', '#ff0404', '#ff0404', '#ff0404', '#ff0404'
                ];
                $keys = ["espera", "descanso", "repouso", "jornada", "falta", "jornadaEfetiva", "mdc", "refeicao", "intersticioInferior", "intersticioSuperior"];
                $keys2 = ["espera", "descanso", "repouso", "jornada", "faltaJustificada", "falta","jornadaExcedido10h", "jornadaExcedido12h",
                    "mdcDescanso30m5h", "refeicaoSemRegistro", "refeicao1h", "refeicao2h", "intersticioInferior", "intersticioSuperior"];
                $graficoAnalitico = array_map(function($k) use ($totalizadores){ return (int)($totalizadores[$k] ?? 0); }, $keys);
                $graficoDetalhado = array_map(function($k) use ($totalizadores){ return (int)($totalizadores[$k] ?? 0); }, $keys2);
                $graficoSintetico = [$gravidadeAlta, $gravidadeMedia, $gravidadeBaixa];
                if (!isset($motoristas)) { $motoristas = 0; }
                if (!isset($ajudante)) { $ajudante = 0; }
                if (!isset($funcionario)) { $funcionario = 0; }
                if (!isset($totalJsonComTudoZero)) { $totalJsonComTudoZero = 0; }
                if (!isset($porcentagemFun)) { $porcentagemFun = 0; }
                if (!isset($mediaPerfTotal)) { $mediaPerfTotal = 0; }
                if (!isset($porcentagemTotalMedia)) { $porcentagemTotalMedia = 0; }
                $totalFun = 0;
				$rowTotais = "<tr class='totais'>"
					. "<th colspan='1'></th>"
					. "<th colspan='1'>".$totalizadores["espera"]."</th>"
					. "<th colspan='1'>".$totalizadores["descanso"]."</th>"
					. "<th colspan='1'>".$totalizadores["repouso"]."</th>"
					. "<th colspan='1'>".$totalizadores["jornada"]."</th>"
					. "<th colspan='1'>".$totalizadores["falta"]."</th>"
					. "<th colspan='1'>".$totalizadores["jornadaEfetiva"]."</th>"
					. "<th colspan='1'>".$totalizadores["mdc"]."</th>"
					. "<th colspan='1'>".$totalizadores["refeicao"]."</th>"
					. "<th colspan='1'>".$totalizadores["intersticioInferior"]."</th>"
					. "<th colspan='1'>".$totalizadores["intersticioSuperior"]."</th>"
					. "<th colspan='1'>".$totalGeral."</th>";
				$encontrado = true;
			} else {
				$totalizadores = [
					"espera" => 0,
					"descanso" => 0,
					"repouso" => 0,
					"jornada" => 0,
					"falta" => 0,
					"jornadaEfetiva" => 0,
					"mdc" => 0,
					"refeicao" => 0,
					"intersticioInferior" => 0,
					"intersticioSuperior" => 0,
				];
				$diasConformidadeTotal = 0;
				$totalJsonComTudoZero = 0;
				$arquivosConsiderados = 0;
				$ocupacoesPermitidas = $_POST['busca_ocupacao'];
				$operacaoSel = (string)($_POST['operacao'] ?? '');
				$setorSel = (string)($_POST['busca_setor'] ?? '');
				$subsetorSel = (string)($_POST['busca_subsetor'] ?? '');
				foreach ($arquivos as $file) {
					$j = json_decode(@file_get_contents($file), true);
					if (!is_array($j)) { continue; }
					$ocupacaoJson = $j['ocupacao'] ?? '';
					if (!empty($ocupacoesPermitidas) && $ocupacaoJson !== $ocupacoesPermitidas) { continue; }
					if ($operacaoSel !== '' && (string)($j['tipoOperacao'] ?? '') !== $operacaoSel) { continue; }
					if ($setorSel !== '' && (string)($j['setor'] ?? '') !== $setorSel) { continue; }
					if ($subsetorSel !== '' && (string)($j['subsetor'] ?? '') !== $subsetorSel) { continue; }
					$arquivosConsiderados++;
					$totalizadores["espera"] += (int)($j["espera"] ?? 0);
					$totalizadores["descanso"] += (int)($j["descanso"] ?? 0);
					$totalizadores["repouso"] += (int)($j["repouso"] ?? 0);
					$totalizadores["jornada"] += (int)($j["jornada"] ?? 0);
					$totalizadores["falta"] += (int)($j["falta"] ?? 0);
					$totalizadores["jornadaEfetiva"] += (int)($j["jornadaEfetiva"] ?? 0);
					$totalizadores["mdc"] += (int)($j["mdc"] ?? 0);
					$totalizadores["refeicao"] += (int)($j["refeicao"] ?? 0);
					$totalizadores["intersticioInferior"] += (int)($j["intersticioInferior"] ?? 0);
					$totalizadores["intersticioSuperior"] += (int)($j["intersticioSuperior"] ?? 0);
					$diasConformidadeTotal += (int)($j["diasConformidade"] ?? 0);
					$sumNC = 
						(int)($j["espera"] ?? 0) + (int)($j["descanso"] ?? 0) + (int)($j["repouso"] ?? 0) + (int)($j["jornada"] ?? 0)
						+ (int)($j["falta"] ?? 0) + (int)($j["jornadaEfetiva"] ?? 0) + (int)($j["mdc"] ?? 0) + (int)($j["refeicao"] ?? 0)
						+ (int)($j["intersticioInferior"] ?? 0) + (int)($j["intersticioSuperior"] ?? 0);
					if ($sumNC === 0) { $totalJsonComTudoZero++; }
				}
				$totalizadores["faltaJustificada"] = (int)($totalizadores["faltaJustificada"] ?? 0);
				$totalizadores["jornadaExcedido10h"] = (int)($totalizadores["jornadaExcedido10h"] ?? 0);
				$totalizadores["jornadaExcedido12h"] = (int)($totalizadores["jornadaExcedido12h"] ?? 0);
				$totalizadores["mdcDescanso30m5h"] = (int)($totalizadores["mdcDescanso30m5h"] ?? 0);
				$totalizadores["refeicaoSemRegistro"] = (int)($totalizadores["refeicaoSemRegistro"] ?? 0);
				$totalizadores["refeicao1h"] = (int)($totalizadores["refeicao1h"] ?? 0);
				$totalizadores["refeicao2h"] = (int)($totalizadores["refeicao2h"] ?? 0);
				$arrayTitulos = ['Espera', 'Descanso', 'Repouso', 'Jornada', 'Jornada Prevista', 'Jornada Efetiva', 'MDC', 'Refeição', 'Interstício Inferior', 'Interstício Superior'];
				$arrayTitulos2 = [
					'Início ou Fim de espera sem registro',
					'Início ou Fim de repouso sem registro',
					'Inicio ou Fim de repouso sem registro',
					'Início ou fim de jornada sem registro',
					'Faltas justificadas',
					'Faltas não justificadas',
					'Tempo excedido de 10:00h de jornada efetiva',
					'Tempo excedido de 12:00h de jornada efetiva',
					'Descanso de 30 minutos a cada 05:30 de direção não respeitado.',
					'Batida de início ou fim de refeição não registrada',
					'Refeição ininterrupta maior que 1 hora não respeitada',
					'Tempo máximo de 2 horas para a refeição não respeitado',
					'O mínimo de 11 horas de interstício não foi respeitado',
					'Interstício total de 11 horas não respeitado'
				];
				$coresGrafico = ['#FFE800' ,'#FFE800' ,'#FFE800','#FFE800','#FFE800', '#FF8B00', '#FF8B00', '#a30000', '#a30000', '#a30000'];
				$coresGrafico2 = [
					'#FFE000', '#FFE800', '#FFE800', '#FFE800', '#FFE800', '#FFE800', '#FF8B00', '#FF8B00',
					'#FF8B00', '#ff0404', '#ff0404', '#ff0404', '#ff0404', '#ff0404', '#ff0404'
				];
				$keys = ["espera", "descanso", "repouso", "jornada", "falta", "jornadaEfetiva", "mdc", "refeicao", "intersticioInferior", "intersticioSuperior"];
				$keys2 = ["espera", "descanso", "repouso", "jornada", "faltaJustificada", "falta","jornadaExcedido10h", "jornadaExcedido12h",
					"mdcDescanso30m5h", "refeicaoSemRegistro", "refeicao1h", "refeicao2h", "intersticioInferior", "intersticioSuperior"];
				$totalGeral = array_sum($totalizadores);
				$gravidadeAlta = $totalizadores["refeicao"] + $totalizadores["intersticioInferior"] + $totalizadores["intersticioSuperior"];
				$gravidadeMedia = $totalizadores["jornadaEfetiva"] + $totalizadores["mdc"];
				$gravidadeBaixa = $totalizadores["falta"] + $totalizadores["espera"] + $totalizadores["descanso"] + $totalizadores["repouso"] + $totalizadores["jornada"];
				$percentuais = [
					"alta" => $totalGeral > 0 ? round(($gravidadeAlta / $totalGeral) * 100, 2) : 0,
					"media" => $totalGeral > 0 ? round(($gravidadeMedia / $totalGeral) * 100, 2) : 0,
					"baixa" => $totalGeral > 0 ? round(($gravidadeBaixa / $totalGeral) * 100, 2) : 0,
				];
				$graficoAnalitico = array_map(function($k) use ($totalizadores){ return (int)($totalizadores[$k] ?? 0); }, $keys);
				$graficoDetalhado = array_map(function($k) use ($totalizadores){ return (int)($totalizadores[$k] ?? 0); }, $keys2);
				$graficoSintetico = [$gravidadeAlta, $gravidadeMedia, $gravidadeBaixa];
				if (!isset($motoristas)) { $motoristas = 0; }
				if (!isset($ajudante)) { $ajudante = 0; }
				if (!isset($funcionario)) { $funcionario = 0; }
				$diasMesRef = (int)date('t', strtotime((!empty($_POST['busca_dataMes']) ? $_POST['busca_dataMes'] : date('Y-m')).'-01'));
				$denEmpresas = max(count($arquivos), 1);
				$denAtivos = $denEmpresas;
				$porcentagemFun = ($denAtivos > 0) ? (($totalJsonComTudoZero / $denAtivos) * 100) : 0;
				$porcentagemTotalBaixa = $percentuais["baixa"];
				$porcentagemTotalBaixaG = 100 - $porcentagemTotalBaixa;
				$totalFun = 0;
				if ($arquivosConsiderados > 0) {
					$totalFun = max($arquivosConsiderados - $totalJsonComTudoZero, 0);
				} else {
					$where = "enti_tx_status = 'ativo'";
					if (!empty($_POST["empresa"])) { $where .= " AND enti_nb_empresa = ".intval($_POST["empresa"]); }
					if (!empty($_POST["busca_ocupacao"])) { $where .= " AND enti_tx_ocupacao = '".mysqli_real_escape_string($GLOBALS["con"], $_POST["busca_ocupacao"])."'"; }
					if (!empty($_POST["operacao"])) { $where .= " AND enti_tx_tipoOperacao = ".intval($_POST["operacao"]); }
					if (!empty($_POST["busca_setor"])) { $where .= " AND enti_setor_id = ".intval($_POST["busca_setor"]); }
					if (!empty($_POST["busca_subsetor"])) { $where .= " AND enti_subSetor_id = ".intval($_POST["busca_subsetor"]); }
					$o = mysqli_fetch_array(query("SELECT COUNT(*) FROM entidade WHERE ".$where." ;"));
					$totalFun = (int)$o[0];
				}
				$denEmpresa = ($diasMesRef * max($totalFun, 1));
				$percentual = ($denEmpresa > 0) ? (($diasConformidadeTotal / $denEmpresa) * 100) : 0;
				$percentualConformidade = 100 - $percentual;
				$porcentagemTotalMedia = round($percentualConformidade, 2);
				if ($porcentagemTotalMedia < 0) { $porcentagemTotalMedia = 0; }
				if ($porcentagemTotalMedia > 100) { $porcentagemTotalMedia = 100; }
				$mediaPerfTotal = $porcentagemTotalMedia;
				$rowTotais = "<tr class='totais'>"
					. "<th colspan='1'></th>"
					. "<th colspan='1'>".$totalizadores["espera"]."</th>"
					. "<th colspan='1'>".$totalizadores["descanso"]."</th>"
					. "<th colspan='1'>".$totalizadores["repouso"]."</th>"
					. "<th colspan='1'>".$totalizadores["jornada"]."</th>"
					. "<th colspan='1'>".$totalizadores["falta"]."</th>"
					. "<th colspan='1'>".$totalizadores["jornadaEfetiva"]."</th>"
					. "<th colspan='1'>".$totalizadores["mdc"]."</th>"
					. "<th colspan='1'>".$totalizadores["refeicao"]."</th>"
					. "<th colspan='1'>".$totalizadores["intersticioInferior"]."</th>"
					. "<th colspan='1'>".$totalizadores["intersticioSuperior"]."</th>"
					. "<th colspan='1'>".$totalGeral."</th>";
				$encontrado = (count($arquivos) > 0);
			}
			echo "<script>window.EMPRESA_NOMES = ".json_encode($empresaNomes, JSON_UNESCAPED_UNICODE).";</script>";
           
		}

		if ($encontrado) {
			$hasDetailFilter = (!empty($_POST["busca_ocupacao"]) || !empty($_POST["operacao"]) || !empty($_POST["busca_setor"]) || !empty($_POST["busca_subsetor"]));
			if (!empty($_POST["empresa"])) {
				if ( $_POST["busca_endossado"] !== "endossado") {
					$totalRow = "<td>".($hasDetailFilter ? $totalizadores["espera"] : $totalempre["espera"])."</td>
						<td class='total'>".($hasDetailFilter ? $totalizadores["descanso"] : $totalempre["descanso"])."</td>
						<td class='total'>".($hasDetailFilter ? $totalizadores["repouso"] : $totalempre["repouso"])."</td>
						<td class='total'>".($hasDetailFilter ? $totalizadores["jornada"] : $totalempre["jornada"])."</td>
						<td class='total'>".($hasDetailFilter ? ($totalizadores["jornadaPrevista"] ?? 0) : ($totalempre["jornadaPrevista"] ?? 0))."</td>";
				}
				
				$totalBaixaPerformance = 100 - $porcentagemTotalBaixa;
				$totalMediaPerformance = $mediaPerfTotal;
				$rowTotal = "<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td>Total</td>
						$totalRow 
						<td class='total'>".($hasDetailFilter ? $totalizadores["jornadaEfetiva"] : $totalempre["jornadaEfetiva"])."</td>
						<td class='total'>".($hasDetailFilter ? $totalizadores["mdc"] : $totalempre["mdc"])."</td>
						<td class='total'>".($hasDetailFilter ? $totalizadores["refeicao"] : $totalempre["refeicao"])."</td>
						<td class='total'>".($hasDetailFilter ? $totalizadores["intersticioInferior"] : $totalempre["intersticioInferior"])."</td>
						<td class='total'>".($hasDetailFilter ? $totalizadores["intersticioSuperior"] : $totalempre["intersticioSuperior"])."</td>
						<td class='total'>$totalGeral</td>
						<td class='total'>$totalMediaPerformance%</td>
						<td class='total'>$totalBaixaPerformance%</td>
				";
			} else {
				$rowTotal = "<td></td>
					<td class='total'>".$totalizadores["espera"]."</td>
					<td class='total'>".$totalizadores["descanso"]."</td>
					<td class='total'>".$totalizadores["repouso"]."</td>
					<td class='total'>".$totalizadores["jornada"]."</td>
					<td class='total'>".$totalizadores["falta"]."</td>
					<td class='total'>".$totalizadores["jornadaEfetiva"]."</td>
					<td class='total'>".$totalizadores["mdc"]."</td>
					<td class='total'>".$totalizadores["refeicao"]."</td>
					<td class='total'>".$totalizadores["intersticioInferior"]."</td>
					<td class='total'>".$totalizadores["intersticioSuperior"]."</td>
					<td class='total'>".$totalGeral."</td>";
			}

			$rowGravidade = "
			<div class='row' id='resumo'>
				<div class='col-md-4'>
					<div id='graficoPerformance' style='width: 250px; height: 195px; margin: 0 auto;'></div>
					<div id='popup-alta' class='popup'>
						<button class='popup-close'>Fechar</button>
						<h3>Sobre o Gráfico:</h3>
						<span>
							Este gráfico apresenta a porcentagem de funcionários com nenhuma não conformidade. 
							Quanto maior o valor, melhor a performance. Mostra o total da empresa ao filtrar por ocupação
						</span>
					</div>
				</div>

				<div class='col-md-3'>
					<div id='graficoPerformanceMedia' style='width: 250px; height: 195px; margin: 0 auto;'></div>
					<div id='popup-media' class='popup'>
						<button class='popup-close'>Fechar</button>
						<h3>Sobre o Gráfico:</h3>
						<span>
							Este gráfico apresenta a porcentagem de não conformidade dos funcionários em relação 
							à quantidade de dias do mês. Quanto maior o valor, melhor a performance. Mostra o total da empresa ao filtrar por ocupação
						</span>
					</div>
				</div>

				<div class='col-md-4'>
					<div id='graficoPerformanceBaixa' style='width: 250px; height: 195px; margin: 0 auto;'></div>
					<div id='popup-baixa' class='popup'>
						<button class='popup-close'>Fechar</button>
						<h3>Sobre o Gráfico:</h3>
						<span>
							Este gráfico apresenta a porcentagem dos funcionários em relação à quantidade de não 
							conformidades no mês. Quanto menor a quantidade, melhor a performance. Mostra o total da empresa ao filtrar por ocupação
						</span>
					</div>
				</div>
			</div>
			

			<div class='row' id='resumo2'>
				<div class='col-md-3'>
					<table id='tabela-motorista' style='width: 275px;' class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact'>"
						. "<thead>"
							. "<tr>"
								. "<td> Quantidade por ocupação </td>"
							. "</th>"
						. "</thead>"
							. "<tbody>"
							. "</tbody>"
						. "</table>
						<table style='width: 275px;' class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact'>"
						. "<thead>"
							. "<tr>"
								. "<td> Nivel de Gravidade</td>"
								. "<td>TOTAL</td>"
								. "<td>%</td>"
							. "</th>"
						. "</thead>"
						. "<tbody>"
							. "<tr>"
								. "<td class='tituloBaixaGravidade2'>Baixa</td>"
								. "<td class='total'>$gravidadeBaixa</td>"
								. "<td class='total'>".$percentuais["baixa"]."%</td>"
							. "</tr>"
							. "<tr>"
								. "<td class='tituloMediaGravidade2'>Média</td>"
								. "<td class='total'>$gravidadeMedia</td>"
								. "<td class='total'>".$percentuais["media"]."%</td>"
							. "</tr>"
							. "<tr>"
								. "<td class='tituloAltaGravidade2'>Alta</td>"
								. "<td class='total'>$gravidadeAlta</td>"
								. "<td class='total'>".$percentuais["alta"]."%</td>"
							. "</tr>"
						. "</tbody>"
					. "</table>			
					</div>
					<div class='col-md-3'>
					<div class='container' style='display:flex'>
						<!-- <div class='col-sm-4'>-->
							<div id='graficoSintetico' style='width:64%;'>
								<!-- Conteúdo do gráfico Sintético -->
							</div>
						<!-- </div>	-->			
						<!-- <div class='col-md-4'>-->
							<div id='graficoAnalitico' style='width:85%;'>
							<!-- Conteúdo do gráfico Analítico -->
							</div>
						<!-- </div>	-->			
					</div>	
				</div>
				</div>
				"
			."</div>";
			
			$rowTitulos = "<tr id='titulos'>";

			if (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "naoEndossado") {
				$titulo = "Performance e Não Conformidade";
				$rowTitulos .=
					"<th class='matricula'>Matricula</th>"
					."<th class='funcionario'>Funcionário</th>"
					."<th class='ocupacao'>Ocupação</th>"
					."<th class='operacao'>Cargo</th>"
					."<th class='setor'>Setor</th>"
					."<th class='subSetor'>SubSetor</th>"
					."<th class='tituloBaixaGravidade'>Espera</th>"
					."<th class='tituloBaixaGravidade'>Descanso</th>"
					."<th class='tituloBaixaGravidade'>Repouso</th>"
					."<th class='tituloBaixaGravidade'>Jornada</th>"
					."<th class='tituloBaixaGravidade'>Jornada Prevista</th>"
					."<th class='tituloMediaGravidade'>Jornada Efetiva</th>"
					."<th class='tituloMediaGravidade'>MDC</th>"
					."<th class='tituloAltaGravidade'>Refeição</th>"
					."<th class='tituloAltaGravidade'>Interstício Inferior</th>"
					."<th class='tituloAltaGravidade'>Interstício Superior</th>"
					. "<th class='tituloTotal'>TOTAL</th>"
					. "<th class='tituloTotal'>Performance Média</th>"
					. "<th class='tituloTotal'>Performance Baixa</th>";

					$endossado = true;

					
			}  elseif (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "endossado") {
				$titulo = "Performance e Não Conformidade Pós-Fechamento";
				$rowTitulos .=
					"<th class='matricula'>Matricula</th>"
					."<th class='funcionario'>Funcionário</th>"
					."<th class='ocupacao'>Ocupação</th>"
					."<th class='operacao'>Operação</th>"
					."<th class='tituloBaixaGravidade'>Jornada Prevista</th>"
					."<th class='tituloMediaGravidade'>Jornada Efetiva</th>"
					."<th class='tituloMediaGravidade'>MDC</th>"
					."<th class='tituloAltaGravidade'>Refeição</th>"
					."<th class='tituloAltaGravidade'>Interstício Inferior</th>"
					."<th class='tituloAltaGravidade'>Interstício Superior</th>"
					."<th class='tituloTotal'>TOTAL</th>"
					."<th class='tituloTotal'>Performance Média</th>"
					."<th class='tituloTotal'>Performance Baixa</th>";


					$endossado = true;
			} else {
				$titulo = ($_POST["busca_endossado"] === "naoEndossado") ? "Não Conformidade por Empresa" : "Pós-fechamento por Empresa";
                $rowTitulos .=
                    "<th class='Titulo'>Nomes Empresas</th>"
                    ."<th class='tituloBaixaGravidade'>Espera</th>"
                    ."<th class='tituloBaixaGravidade'>Descanso</th>"
                    ."<th class='tituloBaixaGravidade'>Repouso</th>"
                    ."<th class='tituloBaixaGravidade'>Jornada</th>"
                    ."<th class='tituloBaixaGravidade'>Jornada Prevista</th>"
					."<th class='tituloMediaGravidade'>Jornada Efetiva</th>"
					."<th class='tituloMediaGravidade'>MDC</th>"
					."<th class='tituloAltaGravidade'>Refeição</th>"
					."<th class='tituloAltaGravidade'>Interstício Inferior</th>"
					."<th class='tituloAltaGravidade'>Interstício Superior</th>"
					."<th class='tituloTotal'>TOTAL</th>";
			}
            $rowTitulos .= "</tr>";
            $filtros = [];
            if(!empty($_POST["operacao"])){
                $r = mysqli_fetch_assoc(query("SELECT oper_tx_nome FROM operacao WHERE oper_nb_id = ".intval($_POST["operacao"])." LIMIT 1;"));
                $filtros[] = "<b>Cargo:</b> ".(!empty($r["oper_tx_nome"]) ? htmlspecialchars($r["oper_tx_nome"], ENT_QUOTES, 'UTF-8') : "Todos");
            } else {
                $filtros[] = "<b>Cargo:</b> Todos";
            }
            if(!empty($_POST["busca_setor"])){
                $r = mysqli_fetch_assoc(query("SELECT grup_tx_nome FROM grupos_documentos WHERE grup_nb_id = ".intval($_POST["busca_setor"])." LIMIT 1;"));
                $filtros[] = "<b>Setor:</b> ".(!empty($r["grup_tx_nome"]) ? htmlspecialchars($r["grup_tx_nome"], ENT_QUOTES, 'UTF-8') : "Todos");
            } else {
                $filtros[] = "<b>Setor:</b> Todos";
            }
            if(!empty($_POST["busca_subsetor"])){
                $r = mysqli_fetch_assoc(query("SELECT sbgr_tx_nome FROM sbgrupos_documentos WHERE sbgr_nb_id = ".intval($_POST["busca_subsetor"])." LIMIT 1;"));
                $filtros[] = "<b>Subsetor:</b> ".(!empty($r["sbgr_tx_nome"]) ? htmlspecialchars($r["sbgr_tx_nome"], ENT_QUOTES, 'UTF-8') : "Todos");
            } else {
                $filtros[] = "<b>Subsetor:</b> Todos";
            }
            $filtrosConsultaHtml = "<div><b>Filtros da consulta:</b> ".(!empty($filtros) ? implode(" | ", $filtros) : "Todos")."</div>";
            $mostra = true;
            include_once "painel_html2.php";

			// if (!empty($_POST["acao"]) && $_POST["acao"] == "buscar") {
			// 	set_status("Não Possui dados desse mês");
			// 	echo "<script>alert('Não Possui dados desse mês')</script>";
			// }
		}

	carregarJS($arquivos, $totaisMediaFuncionario,$totaisFuncionario2);
	rodape();
}
