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

		if (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "naoEndossado") {
			$linha = "linha = '<tr>'";
			$linha .= "+'<td>'+row.matricula+'</td>'
						+'<td>'+row.nome+'</td>'
						+'<td>'+row.ocupacao+'</td>'
						+'<td class=\'baixaGravidade\'>'+(row.espera === 0 ? '' : row.espera )+'</td>'
						+'<td class=\'baixaGravidade\'>'+(row.descanso === 0 ? '' : row.descanso )+'</td>'
						+'<td class=\'baixaGravidade\'>'+(row.repouso === 0 ? '' : row.repouso )+'</td>'
						+'<td class=\'baixaGravidade\'>'+(row.jornada === 0 ? '' : row.jornada )+'</td>'
						+'<td class=\'baixaGravidade\'>'+(row.jornadaPrevista === 0 ? '' : row.jornadaPrevista )+'</td>'
						+'<td class=\'mediaGravidade\'>'+(row.jornadaEfetiva	=== 0 ? '' : row.jornadaEfetiva )+'</td>'
						+'<td class=\'mediaGravidade\'>'+(row.mdc === 0 ? '' : row.mdc )+'</td>'
						+'<td class=\'altaGravidade\'>'+ (row.refeicao === 0 ? '' : row.refeicao) +'</td>'
						+'<td class=\'altaGravidade\'>'+(row.intersticioInferior === 0 ? '' : row.intersticioInferior )+'</td>'
						+'<td class=\'altaGravidade\'>'+(row.intersticioSuperior === 0 ? '' : row.intersticioSuperior )+'</td>'
						+'<td class=\'total\'>'+(totalNaEndossado)+'</td>'
					+'</tr>';";
		} elseif (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "endossado") {
			$linha = "linha = '<tr>'";
			$linha .= "+'<td>'+row.matricula+'</td>'
						+'<td>'+row.nome+'</td>'
						+'<td>'+row.ocupacao+'</td>'
						+'<td class=\'baixaGravidade\'>'+(row.jornadaPrevista === 0 ? '' : row.jornadaPrevista )+'</td>'
						+'<td class=\'mediaGravidade\'>'+(row.jornadaEfetiva	=== 0 ? '' : row.jornadaEfetiva )+'</td>'
						+'<td class=\'mediaGravidade\'>'+(row.mdc === 0 ? '' : row.mdc )+'</td>'
						+'<td class=\'altaGravidade\'>'+ (row.refeicao === 0 ? '' : row.refeicao) +'</td>'
						+'<td class=\'altaGravidade\'>'+(row.intersticioInferior === 0 ? '' : row.intersticioInferior )+'</td>'
						+'<td class=\'altaGravidade\'>'+(row.intersticioSuperior === 0 ? '' : row.intersticioSuperior )+'</td>'
						+'<td class=\'total\'>'+(totalEndossado)+'</td>'
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
							url: urlArquivo + '?v=' + new Date().getTime(),
							dataType: 'json',
							success: function(data){
								var row = {};
								var motoristas = 0;
								var ajudante = 0;
								var funcionario = 0;
								$.each(data, function(index, item){
									row[index] = item;
								});

								var totalNaEndossado = (row.jornadaPrevista || 0) + (row.jornadaEfetiva || 0) + (row.refeicao || 0) 
								+ (row.espera || 0) + (row.descanso || 0) + (row.repouso || 0) + (row.jornada || 0) 
								+ (row.mdc || 0) + (row.intersticioInferior || 0) + (row.intersticioSuperior || 0);
									
								var totalEndossado = (row.refeicao || 0) + (row.jornadaPrevista || 0) + (row.jornadaEfetiva || 0) 
								+ (row.mdc || 0) + (row.intersticioInferior || 0) + (row.intersticioSuperior || 0);
								console.log(row);"
								.$linha
								. "tabela.append(linha);
							},
							error: function(){
								console.log('Erro ao carregar os dados.');
							}
						});
					}


					function ordenarTabela(coluna, ordem){
						var linhas = tabela.find('tr').get();
						linhas.sort(function(a, b){
							// Extrai os valores da coluna como números
							var valorA = parseFloat($(a).children('td').eq(coluna).text());
							var valorB = parseFloat($(b).children('td').eq(coluna).text());

							// Verifica se os valores são números
							if (!isNaN(valorA) && !isNaN(valorB)) {
								// Comparação numérica
								return ordem === 'asc' ? valorA - valorB : valorB - valorA;
							} else {
								// Caso os valores não sejam números, trata como texto
								valorA = $(a).children('td').eq(coluna).text().toUpperCase();
								valorB = $(b).children('td').eq(coluna).text().toUpperCase();

								if (valorA < valorB) {
									return ordem === 'asc' ? -1 : 1;
								}
								if (valorA > valorB) {
									return ordem === 'asc' ? 1 : -1;
								}
								return 0;
							}
						});
						
						$.each(linhas, function(index, row){
							tabela.append(row);
						});
					}

					$('#titulos th').click(function(){
						var colunaClicada = $(this).attr('class');
						// console.log(colunaClicada)
	
						var coluna = $(this).index();
						var ordem = $(this).data('order');

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

		if (empty($_POST["busca_dataMes"])) {
			$_POST["busca_dataMes"] = date("Y-m");
		}

		if (!empty($_POST["acao"]) && $_POST["acao"] == "buscar") {

			if ($_POST["busca_dataMes"] > date("Y-m")) {
				unset($_POST["acao"]);
				$_POST["errorFields"][] = "busca_dataMes";
				set_status("ERRO: Não é possível pesquisar após a data atual.");
			}
			cabecalho("Relatório de Não Conformidade Juridica Atualizado");
		} elseif (!empty($_POST["acao"]) && $_POST["acao"] == "atualizarPainel") {
			echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
			ob_flush();
			flush();

			cabecalho("Relatório de Não Conformidade Juridica Atualizado");

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
				// echo "Tempo de execução: ".number_format($tempoExecucao, 4)." segundos";
			}
		} else {
			cabecalho("Relatório de Não Conformidade Juridica Atualizado");
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

				$totalizadores = [
					"refeicao" => 0,
					"jornadaPrevista" => 0,
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
					"mdcDescanso30m" => 0,
					"mdcDescanso15m" => 0,
					"inicioRefeicaoSemRegistro" => 0,
					"fimRefeicaoSemRegistro" => 0,
					"refeicao1h" => 0,
					"refeicao2h" => 0,
					"faltaJustificada" => 0,
					"falta" => 0
				];

				$todosZerados = true;
				$totalMotoristasComConformidadesZeradas = 0;

				$motoristas = 0;
				foreach ($arquivos as &$arquivo) {
					$arquivo = $path."/".$arquivo;
					$json = json_decode(file_get_contents($arquivo), true);
					foreach ($totalizadores as $key => &$total) {
						if (!isset($json[$chave]) || $json[$chave] !== 0) {
							$todosZerados = false;
						}
						$total += $json[$key] ?? 0; // incrementa apenas se o índice existir no JSON
					}
					if ($todosZerados) {
						$totalMotoristasComConformidadesZeradas++;
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

				if (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "endossado"){
					$totalNaoconformidade = array_sum([
						$totalizadores["mdcDescanso30m5h"],
						$totalizadores["mdcDescanso30m"],
						$totalizadores["mdcDescanso15m"],
						$totalizadores["inicioRefeicaoSemRegistro"],
						$totalizadores["fimRefeicaoSemRegistro"],
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
						$totalizadores["mdcDescanso30m"],
						$totalizadores["mdcDescanso15m"],
						$totalizadores["inicioRefeicaoSemRegistro"],
						$totalizadores["fimRefeicaoSemRegistro"],
						$totalizadores["refeicao1h"],
						$totalizadores["refeicao2h"],
						$totalizadores["intersticioInferior"],
						$totalizadores["intersticioSuperior"]
					]);
					
					$gravidadeAlta = $totalizadores["refeicao"] + $totalizadores["intersticioInferior"] + $totalizadores["intersticioSuperior"];
					$gravidadeMedia = $totalizadores["jornadaEfetiva"] + $totalizadores["mdc"];
					$gravidadeBaixa = $totalizadores["jornadaPrevista"] + $totalizadores["espera"] + $totalizadores["descanso"] +
					$totalizadores["repouso"] + $totalizadores["jornada"];
				}

				$totalGeral = $gravidadeAlta + $gravidadeMedia + $gravidadeBaixa;
				$graficoSintetico = [$totalMotoristasComConformidadesZeradas ,$gravidadeAlta, 
				$gravidadeMedia, $gravidadeBaixa];

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

					$arrayTitulos2 = ['Inicio ou Fim de espera sem registro', 'Inicio ou Fim de descanso sem registro',
					'Inicio ou Fim de repouso sem registro', 'Inicio ou Fim de jornada sem registro', 'Faltas justificadas', 'Faltas Não justificadas',
					'Tempo excedida de 10:00h', 'Tempo excedida de 12:00h', 'Descanso de 00:30 a cada 05:30 dirigidos não respeitado',
					'Descanso de 00:30 não respeitado', 'Descanso de 00:15 não respeitado', 'Batida início de refeição não registrado',
					'Batida fim de refeição não registrado', 'Refeição Initerrupita maior do que 01:00h não respeitada',
					'Refeição com Tempo máximo de 02:00h não respeitada', 'O mínimo de 08:00h ininterruptas no primeiro período não respeitado',
					'Interstício Total de 11:00 não respeitado, faltaram 00:32'
					];

					$coresGrafico = ['#f1c61f' ,'#f1c61f' ,'#f1c61f','#f1c61f','#f1c61f', '#FFB520', '#FFB520', '#a30000', '#a30000', '#a30000'];
					$coresGrafico2 = ['#f1c61f', '#f1c61f', '#f1c61f', '#f1c61f', '#f1c61f', '#f1c61f', '#FFB520', '#FFB520', '#FFB520', '#FFB520',
					'#FFB520', '#a30000', '#a30000', '#a30000', '#a30000', '#a30000', '#a30000'];
					//}
					
					$keys = ["espera", "descanso", "repouso", "jornada", "jornadaPrevista", "jornadaEfetiva", "mdc", "refeicao",
					"intersticioInferior", "intersticioSuperior"];

					$keys2 = ["espera", "descanso", "repouso", "jornada", "faltaJustificada", "falta","jornadaExcedido10h", "jornadaExcedido12h",
					"mdcDescanso30m5h", "mdcDescanso30m","mdcDescanso15m", "inicioRefeicaoSemRegistro", "fimRefeicaoSemRegistro" , "refeicao1h",
					"refeicao2h", "intersticioInferior", "intersticioSuperior"];
				} else{
					// Campos dos graficos {
					$arrayTitulos = ['Jornada Prevista', 'Jornada Efetiva', 'MDC',
					'Refeição', 'Interstício Inferior', 'Interstício Superior'];

					$arrayTitulos2 = ['Faltas justificadas', 'Faltas Não justificadas',
					'Tempo excedida de 10:00h', 'Tempo excedida de 12:00h', 'Descanso de 00:30 a cada 05:30 dirigidos não respeitado',
					'Descanso de 00:30 não respeitado', 'Descanso de 00:15 não respeitado', 'Batida início de refeição não registrado',
					'Batida fim de refeição não registrado', 'Refeição Initerrupita maior do que 01:00h não respeitada',
					'Refeição com Tempo máximo de 02:00h não respeitada', 'O mínimo de 08:00h ininterruptas no primeiro período não respeitado',
					'Interstício Total de 11:00 não respeitado, faltaram 00:32'
					];

					$coresGrafico = ['#f1c61f', '#FFB520', '#FFB520', '#a30000', '#a30000', '#a30000'];
					$coresGrafico2 = ['#f1c61f', '#f1c61f', '#FFB520', '#FFB520', '#FFB520', '#FFB520',
					'#FFB520', '#a30000', '#a30000', '#a30000', '#a30000', '#a30000', '#a30000'];
					//}

					$keys = ["jornadaPrevista", "jornadaEfetiva", "mdc", "refeicao","intersticioInferior", "intersticioSuperior"];

					$keys2 = ["faltaJustificada", "falta", "jornadaExcedido10h", "jornadaExcedido12h", "mdcDescanso30m5h", "mdcDescanso30m",
					"mdcDescanso15m", "inicioRefeicaoSemRegistro", "fimRefeicaoSemRegistro" , "refeicao1h", "refeicao2h", "intersticioInferior",
					"intersticioSuperior"];
				}

				// Percentuais gerais de Não Conformidade (baseado no total geral)
				foreach ($keys as $key) {
					$percentuais["Geral_".$key] = round(($totalizadores[$key] / $totalGeral) * 100, 2);
					$graficoAnalitico[] = $totalizadores[$key];
				}
	
				// Percentuais específicos de Não Conformidade (baseado no total de não conformidade)
				foreach ($keys2 as $key)  {
					if ($totalNaoconformidade > 0 && isset($totalizadores[$key])) {
						$percentuais["Especifico_".$key] = round(($totalizadores[$key] / $totalNaoconformidade) * 100, 2);
					} else {
						$percentuais["Especifico_".$key] = 0;
					}
					$graficoDetalhado[] = $totalizadores[$key];
				}

				if (!empty($arquivo)) {
					$dataEmissao = "Atualizado em: ".date("d/m/Y H:i", filemtime($arquivo)); //Utilizado no HTML.
					$arquivoGeral = json_decode(file_get_contents($arquivo), true);

					$periodoRelatorio = [
						"dataInicio" => $arquivoGeral["dataInicio"],
						"dataFim" => $arquivoGeral["dataFim"]
					];

					$encontrado = true;
				} else {
					echo "<script>alert('Não tem jornadas abertas.')</script>";
				}

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
					<td>".$totalempre["descanso"]."</td>
					<td>".$totalempre["repouso"]."</td>
					<td>".$totalempre["jornada"]."</td>";
			}
			
			$rowTotal = "<td></td>
					<td></td>
					<td>Total</td>
					$totalRow 
					<td>".$totalempre["jornadaPrevista"]."</td>
					<td>".$totalempre["jornadaEfetiva"]."</td>
					<td>".$totalempre["mdc"]."</td>
					<td>".$totalempre["refeicao"]."</td>
					<td>".$totalempre["intersticioInferior"]."</td>
					<td>".$totalempre["intersticioSuperior"]."</td>
					<td>$totalGeral</td>
			";

			$rowGravidade = "
			<div class='row'>
				<div class='container' style='display:flex'>
					<div class='col-md-5'>
						<div id='graficoSintetico' style='width:100%; height:390px; background-color: lightgray;'>
							<!-- Conteúdo do gráfico Sintético -->
						</div>
					</div>				
					<div class='col-md-6'>
						<div id='graficoAnalitico' style='width:130%; height:390px; background-color: lightblue;'>
						<!-- Conteúdo do gráfico Analítico -->
						</div>
					</div>				
				</div>
				<div class='col-md-3'>
					<table id='tabela-motorista' style='width: 350px;' class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact'>"
						. "<thead>"
							. "<tr>"
								. "<td> Quandidade de motoristas por ocupação </td>"
								. "<td>TOTAL</td>"
								. "<td>%</td>"
							. "</th>"
						. "</thead>"
							. "<tbody>"
							. "</tbody>"
						. "</table>
				</div>
				<div class='col-md-3'> 
					<table style='width: 350px;' class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content compact'>"
						. "<thead>"
							. "<tr>"
								. "<td> Nivel de Gravidade</td>"
								. "<td>TOTAL</td>"
								. "<td>%</td>"
							. "</th>"
						. "</thead>"
						. "<tbody>"
							. "<tr>"
							. "<td class='tituloPerformance'>Performance</td>"
							. "<td>$totalMotoristasComConformidadesZeradas</td>"
							. "<td>".$percentuais["performance"]."%</td>"
							. "</tr>"
							. "<tr>"
								. "<td class='tituloBaixaGravidade'>Baixa</td>"
								. "<td class='total'>$gravidadeBaixa</td>"
								. "<td class='total'>".$percentuais["baixa"]."%</td>"
							. "</tr>"
							. "<tr>"
								. "<td class='tituloMediaGravidade'>Média</td>"
								. "<td class='total'>$gravidadeMedia</td>"
								. "<td class='total'>".$percentuais["media"]."%</td>"
							. "</tr>"
							. "<tr>"
								. "<td class='tituloAltaGravidade'>Alta</td>"
								. "<td class='total'>$gravidadeAlta</td>"
								. "<td class='total'>".$percentuais["alta"]."%</td>"
							. "</tr>"
						. "</tbody>"
					. "</table>
				</div>
			</div>";
			
			$rowTitulos = "<tr id='titulos'>";

			if (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "naoEndossado") {
				$titulo = "Antes do Fechamento";
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
					. "<th class='tituloTotal'>TOTAL</th>";

					$endossado = true;

					
			}  elseif (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "endossado") {
				$titulo = "Pós-Fechamento";
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

			include_once "painel_html2.php";

			// if (!empty($_POST["acao"]) && $_POST["acao"] == "buscar") {
			// 	set_status("Não Possui dados desse mês");
			// 	echo "<script>alert('Não Possui dados desse mês')</script>";
			// }
		}

	carregarJS($arquivos);
	rodape();
}
