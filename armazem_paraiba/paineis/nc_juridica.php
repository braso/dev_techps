<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
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

			$linha .= " linha = '<tr>'";
			$linha .= "+'<td>'+row.matricula+'</td>'
						+'<td>'+row.nome+'</td>'
						+'<td>'+row.ocupacao+'</td>'
						+'<td class='+class1+'>'+(row.espera === 0 ? '' : row.espera )+'</td>'
						+'<td class='+class1+'>'+(row.descanso === 0 ? '' : row.descanso )+'</td>'
						+'<td class='+class1+'>'+(row.repouso === 0 ? '' : row.repouso )+'</td>'
						+'<td class='+class1+'>'+(row.jornada === 0 ? '' : row.jornada )+'</td>'
						+'<td class='+class1+'>'+(row.falta === 0 ? '' : row.falta )+'</td>'
						+'<td class='+class2+'>'+(row.jornadaEfetiva	=== 0 ? '' : row.jornadaEfetiva )+'</td>'
						+'<td class='+class2+'>'+(row.mdc === 0 ? '' : row.mdc )+'</td>'
						+'<td class='+class3+'>'+ (row.refeicao === 0 ? '' : row.refeicao) +'</td>'
						+'<td class='+class3+'>'+(row.intersticioInferior === 0 ? '' : row.intersticioInferior )+'</td>'
						+'<td class='+class3+'>'+(row.intersticioSuperior === 0 ? '' : row.intersticioSuperior )+'</td>'
						+'<td class='+class4+'>'+(totalNaEndossado)+'</td>'
						+'<td class='+classeImpressao2+' style=\"'+corDeFundo2+'\">'+(100 - arrayPerformanceMedia[row.matricula]).toFixed(2)+' %</td>'
						+'<td class='+classeImpressao+' style=\"'+corDeFundo+'\">'+(100 - arrayPerformanceBaixa[row.matricula]).toFixed(2)+' %</td>'
					+'</tr>';";
		} elseif (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "endossado") {
			$linha = "linha = '<tr>'";
			$linha .= "+'<td>'+row.matricula+'</td>'
						+'<td>'+row.nome+'</td>'
						+'<td>'+row.ocupacao+'</td>'
						+'<td class='+class1+'>'+(row.falta === 0 ? '' : row.falta )+'</td>'
						+'<td class='+class2+'>'+(row.jornadaEfetiva	=== 0 ? '' : row.jornadaEfetiva )+'</td>'
						+'<td class='+class2+'>'+(row.mdc === 0 ? '' : row.mdc )+'</td>'
						+'<td class='+class3+'>'+ (row.refeicao === 0 ? '' : row.refeicao) +'</td>'
						+'<td class='+class3+'>'+(row.intersticioInferior === 0 ? '' : row.intersticioInferior )+'</td>'
						+'<td class='+class3+'>'+(row.intersticioSuperior === 0 ? '' : row.intersticioSuperior )+'</td>'
						+'<td class='+class4+'>'+(totalNaEndossado)+'</td>'
					+'</tr>';";
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
				<input type='hidden' name='busca_data'>
			</form>
			<script>
				function setAndSubmit(empresa){
					document.myForm.acao.value = 'enviarForm()';
					document.myForm.campoAcao.value = 'buscar';
					document.myForm.empresa.value = empresa;
					document.myForm.busca_dataMes.value = document.getElementById('busca_dataMes').value;
					document.myForm.busca_ocupacao.value = document.querySelector('[name=\"busca_ocupacao\"]').value;
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
					function carregarDados(urlArquivo){
						$.ajax({
							url: urlArquivo + '?v=' + new Date().getTime(),
							dataType: 'json',
							success: function(data){
								var row = {};
								var motoristas = 0;
								var ajudante = 0;
								var funcionario = 0;

								let class1 = '';
								let class2 = '';
								let class3 = '';
								let class4 = '';
								$.each(data, function(index, item){
									row[index] = item;
								});

								var totalNaEndossado = (row.falta || 0) + (row.jornadaEfetiva || 0) + (row.refeicao || 0) 
								+ (row.espera || 0) + (row.descanso || 0) + (row.repouso || 0) + (row.jornada || 0) 
								+ (row.mdc || 0) + (row.intersticioInferior || 0) + (row.intersticioSuperior || 0);
								var totalEndossado = (row.refeicao || 0) + (row.falta || 0) + (row.jornadaEfetiva || 0) 
								+ (row.mdc || 0) + (row.intersticioInferior || 0) + (row.intersticioSuperior || 0);

								// console.log(totalEndossado);
								if (totalNaEndossado === 0) {
									class1 = 'highlighted';
									class2 = 'highlighted';
									class3 = 'highlighted';
									class4 = 'highlighted';
								} else{
									class1 = 'baixaGravidade';
									class2 = 'mediaGravidade';
									class3 = 'altaGravidade';
									class4 = 'total';
								}
								
								console.log(row);"
								.$linha
								. "
								var novaLinha = $(linha);
								tabela.append(linha);
							},
							error: function(){
								console.log('Erro ao carregar os dados.');
							}
						});
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

				$(document).ready(function() {
					// Obtém o botão
					const button = document.getElementById('botaoContexBuscar');

					// Inicializa o select2 no campo 'empresa'
					$('#empresa').select2();

					// Verifica se já há uma opção selecionada ao carregar a página
					if ($('#empresa').val()) {
						button.removeAttribute('disabled'); // Habilita o botão se houver um valor selecionado
					} else {
						button.setAttribute('disabled', true); // Desabilita se não houver
					}

					// Escuta o evento 'select2:select' para capturar quando uma nova opção é selecionada
					$('#empresa').on('select2:select', function(e) {
						button.removeAttribute('disabled'); // Habilita o botão ao selecionar
					});

					// Escuta o evento 'select2:unselect' para capturar quando uma opção é desmarcada (se múltiplo)
					$('#empresa').on('select2:unselect', function(e) {
						button.setAttribute('disabled', true); // Desabilita o botão ao desmarcar
					});
				});
			</script>"
		;
	}

	function index() {
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
			ob_flush();
			flush();

			cabecalho("Relatório de Não Conformidade Jurídica Atualizado");

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
				// $tempoInicio = microtime(true);
				relatorio_nao_conformidade_juridica();
				// $tempoFim = microtime(true);
				// $tempoExecucao = $tempoFim - $tempoInicio;
				// $tempoExecucaoMinutos = $tempoExecucao / 60;
				// echo "Tempo de execução: " . number_format($tempoExecucaoMinutos, 4) . " minutos";
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

		$campos = [
			combo_net("Empresa", "empresa", $_POST["empresa"]?? "", 4, "empresa", ""),
			$campoAcao,
			campo_mes("Mês*", "busca_dataMes", ($_POST["busca_dataMes"] ?? date("Y-m")), 2),
			combo("Ocupação", "busca_ocupacao", ($_POST["busca_ocupacao"] ?? ""), 2, 
            ["" => "Todos", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário"]),
			combo("Tipo",	"busca_endossado", (!empty($_POST["busca_endossado"]) ? $_POST["busca_endossado"] : ""), 2, ["naoEndossado" => "Atualizado","endossado" => "Pós-fechamento", "semAjustes"=>"Sem ajuste"])
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
		$dataEmissao = ""; //Utilizado no HTML
		$path = "./arquivos/nao_conformidade_juridica";
		$periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

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
				$totalJsonComTudoZero = 0;
				$totalDiasNaoCFuncionario = 0;
				$totaisFuncionario = [];
				$totaisFuncionario2 = [];
				$totaisMediaFuncionario = [];
				foreach ($arquivos as &$arquivo) {
					$todosZeros = true;
					$arquivo = $path."/".$arquivo;
					$json = json_decode(file_get_contents($arquivo), true);

					$totalMotorista = $json["espera"]+$json["descanso"]+$json["repouso"]+$json["jornada"]+$json["falta"]+$json["jornadaEfetiva"]+$json["mdc"]
					+$json["refeicao"]+$json["intersticioInferior"]+$json["intersticioSuperior"];
					
					$totalDiasNaoCFuncionario += $json["diasConformidade"];

					$data = new DateTime($json["dataInicio"]);
					$dias = $data->format('t');
					
					$mediaPerfTotal = round(($totalDiasNaoCFuncionario/ ($dias * sizeof($arquivos)) * 100), 2);

					$mediaPerfFuncionario = round(($json["diasConformidade"]/ $dias) * 100, 2);

					$totaisMediaFuncionario[$json["matricula"]] = $mediaPerfFuncionario;

					$totalNConformMax = 4 * $dias;
					// Baixar performance total
					$porcentagemFunNCon = round(($totalMotorista *100) / ($totalNConformMax * sizeof($arquivos)), 2);
					// Baixar performance funcionario
					$porcentagemFunNCon2 = round(($totalMotorista *100) / $totalNConformMax, 2);

					$totaisFuncionario[$json["matricula"]] = $porcentagemFunNCon;
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

				$porcentagemTotalBaixa= array_sum((array) $totaisFuncionario);
				$totalFun = sizeof($arquivos) - $totalJsonComTudoZero;
				$porcentagemTotalBaixaG = 100 - $porcentagemTotalBaixa;
				$porcentagemTotalMedia = 100 - $mediaPerfTotal;
				$porcentagemTotalBaixa2= (array) $totaisFuncionario2;

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
					$gravidadeBaixa = $totalizadores["jornadaPrevista"];

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

				$porcentagemFun = ($totalJsonComTudoZero / ($motoristas + $ajudante + $funcionario)) * 100;

				$totalGeral = $gravidadeAlta + $gravidadeMedia + $gravidadeBaixa;
				$graficoSintetico = [$gravidadeAlta, $gravidadeMedia, $gravidadeBaixa];

				$percentuais = [
					"performance" => round($totalMotoristasComConformidadesZeradas / $totalGeral),
					"alta" => round(($gravidadeAlta / $totalGeral) * 100, 2),
					"media" => round(($gravidadeMedia / $totalGeral) * 100, 2),
					"baixa" => round(($gravidadeBaixa / $totalGeral) * 100, 2)
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
					$percentuais["Geral_".$key] = number_format(round(($totalizadores[$key] / $totalGeral) * 100, 2),2);
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
				
			} else {
				$encontrado = false;
			}
		} 

		if ($encontrado) {
			if ( $_POST["busca_endossado"] !== "endossado") {
				$totalRow = "<td>".$totalempre["espera"]."</td>
					<td class='total'>".$totalempre["descanso"]."</td>
					<td class='total'>".$totalempre["repouso"]."</td>
					<td class='total'>".$totalempre["jornada"]."</td>";
			}
			
			$totalBaixaPerformance = 100 - array_sum($totaisFuncionario);
			$totalMediaPerformance = 100 - $mediaPerfTotal;
			$rowTotal = "<td></td>
					<td></td>
					<td>Total</td>
					$totalRow 
					<td class='total'>".$totalempre["falta"]."</td>
					<td class='total'>".$totalempre["jornadaEfetiva"]."</td>
					<td class='total'>".$totalempre["mdc"]."</td>
					<td class='total'>".$totalempre["refeicao"]."</td>
					<td class='total'>".$totalempre["intersticioInferior"]."</td>
					<td class='total'>".$totalempre["intersticioSuperior"]."</td>
					<td class='total'>$totalGeral</td>
					<td class='total'>$totalMediaPerformance%</td>
					<td class='total'>$totalBaixaPerformance%</td>
			";

			$rowGravidade = "
			<div class='row' id='resumo'>
				<div class='col-md-4'>
					<div id='graficoPerformance' style='width: 250px; height: 195px; margin: 0 auto;'></div>
					<div id='popup-alta' class='popup'>
						<button class='popup-close'>Fechar</button>
						<h3>Sobre o Gráfico:</h3>
						<span>
							Este gráfico apresenta a porcentagem de funcionários com nenhuma não conformidade. 
							Quanto maior o valor, melhor a performance.
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
							à quantidade de dias do mês. Quanto maior o valor, melhor a performance.
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
							conformidades no mês. Quanto menor a quantidade, melhor a performance.
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
			</div>";
			
			$rowTitulos = "<tr id='titulos'>";

			if (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "naoEndossado") {
				$titulo = "Performance e Não Conformidade";
				$rowTitulos .=
					"<th class='matricula'>Matricula</th>"
					."<th class='funcionario'>Funcionário</th>"
					."<th class='ocupacao'>Ocupação</th>"
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
					."<th class='tituloBaixaGravidade'>Jornada Prevista</th>"
					."<th class='tituloMediaGravidade'>Jornada Efetiva</th>"
					."<th class='tituloMediaGravidade'>MDC</th>"
					."<th class='tituloAltaGravidade'>Refeição</th>"
					."<th class='tituloAltaGravidade'>Interstício Inferior</th>"
					."<th class='tituloAltaGravidade'>Interstício Superior</th>"
					."<th class='tituloTotal'>TOTAL</th>";


					$endossado = true;
			}
			$rowTitulos .= "</tr>";
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
