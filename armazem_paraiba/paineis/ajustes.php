<?php
/* Modo debug
	ini_set("display_errors", 1);
	error_reporting(E_ALL);
//*/
	header("Expires: 01 Jan 2001 00:00:00 GMT");
    header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
    header('Cache-Control: post-check=0, pre-check=0', FALSE);
    header('Pragma: no-cache');

    require_once __DIR__."/funcoes_paineis.php";
    require __DIR__."/../funcoes_ponto.php";

function enviarForm() {
	$_POST["acao"] = $_POST["campoAcao"];
	index();
}

function carregarJS(array $arquivos) {
	$dominiosAutotrac = ["/comav"];
	$linha = "linha = '<tr>'";
	if (!empty($_POST["empresa"])) {
		$linha .= "+'<td>'+row.matricula+'</td>'
					+'<td>'+row.nome+'</td>'
					+'<td>'+row.ocupacao+'</td>'
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
	}

	$carregarDados = "";
	foreach ($arquivos as $arquivo) {
		$carregarDados .= "carregarDados('" . $arquivo . "');";
	}

	echo
	"<form name='myForm' method='post' action='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "'>
                <input type='hidden' name='acao'>
                <input type='hidden' name='atualizar'>
                <input type='hidden' name='campoAcao'>
                <input type='hidden' name='empresa'>
                <input type='hidden' name='busca_dataInicio'>
                <input type='hidden' name='busca_dataFim'>
                <input type='hidden' name='busca_data'>
            </form>
            <script>
                function setAndSubmit(empresa){
                    document.myForm.acao.value = 'enviarForm()';
                    document.myForm.campoAcao.value = 'buscar';
                    document.myForm.empresa.value = empresa;
                    document.myForm.busca_data.value = document.getElementById('busca_data').value;
                    document.myForm.submit();
                }

                function atualizarPainel(){
                    document.myForm.empresa.value = document.getElementById('empresa').value;
                    document.myForm.busca_data.value = document.getElementById('busca_data').value;
                    document.myForm.atualizar.value = 'atualizar';
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
								console.log(row);
                                "
								. $linha
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
                    //     return (horasNumeros * 60) + (horasNumeros < 0 ? -minutos : minutos);
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
                    //             return ordem === 'asc' ? -1 : 1;
                    //         }
                    //         if(valorA > valorB){
                    //             return ordem === 'asc' ? 1 : -1;
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
                    //     $(this).data('order', ordem === 'desc' ? 'asc' : 'desc');
                    //     ordenarTabela(coluna, $(this).data('order'));

                    //     // Ajustar classes para setas de ordenação
                    //     $('#titulos th').removeClass('sort-asc sort-desc');
                    //     $(this).addClass($(this).data('order') === 'asc' ? 'sort-asc' : 'sort-desc');
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


                    " . $carregarDados . "
                });
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

			//Este comando de cabecalho deve ficar entre o alert() e a chamada de criar_relatorio_saldo() para notificar e aparecer o ícone de carregamento antes de começar o processamento
			cabecalho("Relatório Geral de Saldo");

			criar_relatorio_ajustes();
		} else {
			cabecalho("Relatório Geral de Saldo");
		}
	} else {
		cabecalho("Relatório Geral de Saldo");
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
		combo_net("Empresa", "empresa", $_POST["empresa"] ?? "", 4, "empresa", ""),
		$campoAcao,
		campo( "Período", "busca_periodo", (!empty($_POST["busca_periodo"])? $_POST["busca_periodo"]: [date("Y-m-01"), date("Y-m-d")]),
				2, "MASCARA_PERIODO")
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
	$totais = [];
	$dataEmissao = ""; //Utilizado no HTML
	$encontrado = true;
	$path = "./arquivos/ajustes";
	$periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

	if (!empty($_POST["empresa"]) && !empty($_POST["busca_periodo"])) {
		$periodoInicio = new DateTime($_POST["busca_periodo"][0]);
		$path .= "/" . $periodoInicio->format("Y-m") . "/" . $_POST["empresa"];
		if (is_dir($path) && file_exists($path ."/empresa_" . $_POST["empresa"] . ".json")) {
			$dataEmissao = "Atualizado em: " . date("d/m/Y H:i", filemtime($path . "/empresa_" . $_POST["empresa"] . ".json"));
			$arquivoGeral = json_decode(file_get_contents($path . "/empresa_" . $_POST["empresa"]. ".json"), true);

			$periodoRelatorio = [
				"dataInicio" => $arquivoGeral["dataInicio"],
				"dataFim" => $arquivoGeral["dataFim"]
			];

			$pastaAjuste = dir($path);
			while ($arquivo = $pastaAjuste->read()) {
				if (!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
					$arquivo = $path."/".$arquivo;
					$arquivos[] = $arquivo;
					$json = json_decode(file_get_contents($arquivo), true);
					$empresas[] = $json;
				}
			}
			$pastaAjuste->close();
		}
	} elseif(!empty($_POST["busca_periodo"])){
		$periodoInicio = new DateTime($_POST["busca_periodo"][0]);
		$periodoFim = new DateTime($_POST["busca_periodo"][1]);

		if ($periodoInicio->format("Y-m") === $periodoFim->format("Y-m")) {
			$path .= "/" . $periodoInicio->format("Y-m"). "/" ;
		}

		if(is_dir($path) && file_exists($path . "/empresas.json")){
			$dataEmissao = "Atualizado em: " . date("d/m/Y H:i", filemtime($path . "/empresas.json"));
			$arquivoGeral = json_decode(file_get_contents($path . "/empresas.json"), true);

			$periodoRelatorio = [
				"dataInicio" => $arquivoGeral["dataInicio"],
				"dataFim" => $arquivoGeral["dataFim"]
			];

			$pastaAjuste = dir($path);
			while ($arquivo = $pastaAjuste->read()) {
				if (!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresas"))) {
					$arquivo = $path . $arquivo . "/empresa_" . $arquivo . ".json";
					$arquivos[] = $arquivo;
					$json = json_decode(file_get_contents($arquivo), true);
					$empresas[] = $json;
				}
			}
			$pastaAjuste->close();
		}
	} else {
		$encontrado = false;
	}

	if ($encontrado) {
		$rowTotais = "<tr class='totais'>";
		$rowTitulos = "<tr id='titulos' class='titulos'>";

		if (!empty($_POST["empresa"])) {
			if (!in_array($_SERVER["REQUEST_URI"], $dominiosAutotrac)) {
				$rowTotais .=
					"<th colspan='1'>" . $arquivoGeral["empr_tx_nome"] . "</th>"
					. "<th colspan='1'></th>"
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
					"<th data-column='matricula' data-order='asc'>Matrícula</th>"
					. "<th data-column='nome' data-order='asc'>Nome da Funcionário</th>"
					. "<th data-column='qtdMotoristas' data-order='asc'>Ocupação</th>"
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
		}
		$rowTotais .= "</tr>";
		$rowTitulos .= "</tr>";
	}
	$mostra = false;
	include_once "painel_html2.php";
	carregarJS($arquivos);
	rodape();
}