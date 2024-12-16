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
							console.log(row);
							{$linha}
							tabela.append(linha);
						},
						error: function(){
							console.log('Erro ao carregar os dados.');
						}
					});
				}
					
				// Função para ordenar a tabela
				function ordenarTabela(coluna, ordem){
					var linhas = tabela.find('tr').get();
					
					linhas.sort(function(a, b){
						var valorA = $(a).children('td').eq(coluna).text();
						var valorB = $(b).children('td').eq(coluna).text();

						if(valorA < valorB){
							return ordem === 'asc' ? -1 : 1;
						}
						if(valorA > valorB){
							return ordem === 'asc' ? 1 : -1;
						}
						return 0;
					});

					$.each(linhas, function(index, row){
						tabela.append(row);
					});
				}

				// Evento de clique para ordenar a tabela ao clicar no cabeçalho
				$('#titulos th').click(function(){
					var coluna = $(this).index();
					var ordem = $(this).data('order');
					$('#tabela-empresas th').data('order', 'desc'); // Redefinir ordem de todas as colunas
					$(this).data('order', ordem === 'desc' ? 'asc' : 'desc');
					ordenarTabela(coluna, $(this).data('order'));

					// Ajustar classes para setas de ordenação
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
		</script>"
	;
}

function index() {
	$dominiosAutotrac = ["/comav"];
	if (!empty($_POST["acao"])) {
		if ($_POST["busca_dataMes"] > date("Y-m")) {
			unset($_POST["acao"]);
			$_POST["errorFields"][] = "busca_dataMes";
			set_status("ERRO: Insira um mês menor ou igual ao atual. (".date("m/Y").")");
			cabecalho("Relatório Geral de Saldo");
		} elseif ($_POST["acao"] == "atualizarPainel") {
			echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
			ob_flush();
			flush();

			//Este comando de cabecalho deve ficar entre o alert() e a chamada de criar_relatorio_saldo() para notificar e aparecer o ícone de carregamento antes de começar o processamento
			cabecalho("Relatório Geral de Ajustes");

			criar_relatorio_ajustes();
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
					<input type='radio' name='campoAcao' value='buscar' ".((empty($_POST["campoAcao"]) || $_POST["campoAcao"] == "buscar") ? "checked" : "")."> Buscar
				</label>
				<label class='radio-inline'>
          			<input type='radio' name='campoAcao' value='atualizarPainel'".(!empty($_POST["campoAcao"]) && $_POST["campoAcao"] == "atualizarPainel" ? "checked" : "")."> Atualizar
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
	$encontrado = false;
	$path = "./arquivos/ajustes";
	$periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

	if (!empty($_POST["empresa"]) && !empty($_POST["busca_periodo"])) {
		$periodoInicio = new DateTime($_POST["busca_periodo"][0]);
		$path .= "/".$periodoInicio->format("Y-m")."/".$_POST["empresa"];
		if (is_dir($path) && file_exists($path ."/empresa_".$_POST["empresa"].".json")) {
			$encontrado = true;
			$dataArquivo = date("d/m/Y", filemtime($path."/empresa_".$_POST["empresa"].".json"));
			$horaArquivo = date("H:i", filemtime($path."/empresa_".$_POST["empresa"].".json"));

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
			$dataEmissao = $alertaEmissao." Atualizado em: ".date("d/m/Y H:i", filemtime($path."/empresa_".$_POST["empresa"].".json"))."</span>";
			$arquivoGeral = json_decode(file_get_contents($path."/empresa_".$_POST["empresa"]. ".json"), true);

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
			if(!empty($arquivos)){
				$encontrado = true;
			}
		}
	} 
	// elseif(!empty($_POST["busca_periodo"])){
	// 	$periodoInicio = new DateTime($_POST["busca_periodo"][0]);
	// 	$periodoFim = new DateTime($_POST["busca_periodo"][1]);

	// 	if ($periodoInicio->format("Y-m") === $periodoFim->format("Y-m")) {
	// 		$path .= "/" . $periodoInicio->format("Y-m"). "/" ;
	// 	}

	// 	if(is_dir($path) && file_exists($path . "/empresas.json")){
	// 		$encontrado = true;
	// 		$dataArquivo = date("d/m/Y", filemtime($path . "/empresas.json"));
	// 		$horaArquivo = date("H:i", filemtime($path . "/empresas.json"));

	// 		$dataAtual = date("d/m/Y");
	// 		$horaAtual = date("H:i");
	// 		if ($dataArquivo != $dataAtual) {
	// 			$alertaEmissao = "<span style='color: red; border: 2px solid; padding: 2px; border-radius: 4px;'>
    //                     <i style='color:red;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
	// 		} else {
	// 			// Datas iguais: compara as horas
	// 			// if ($horaArquivo < $horaAtual) {
	// 			//     $alertaEmissao = "<i style='color:red;' title='As informações do painel podem estar desatualizadas.' class='fa fa-warning'></i>";
	// 			// } else {
	// 			$alertaEmissao = "<span>";
	// 			// }
	// 		}
	// 		$dataEmissao = $alertaEmissao." Atualizado em: " . date("d/m/Y H:i", filemtime($path . "/empresas.json")). "</span>";
	// 		$arquivoGeral = json_decode(file_get_contents($path . "/empresas.json"), true);

	// 		$periodoRelatorio = [
	// 			"dataInicio" => $arquivoGeral["dataInicio"],
	// 			"dataFim" => $arquivoGeral["dataFim"]
	// 		];
	// 		$pastaAjuste = dir($path);
	// 		while ($arquivo = $pastaAjuste->read()) {
	// 			if (!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresas"))) {
	// 				$arquivo = $path . $arquivo . "/empresa_" . $arquivo . ".json";
	// 				$arquivos[] = $arquivo;
	// 				$json = json_decode(file_get_contents($arquivo), true);
	// 				$empresas[] = $json;
	// 			}
	// 		}
	// 		$pastaAjuste->close();
	// 	}
	// } 
	else {
		$encontrado = false;
	}

	if ($encontrado) {
		$rowTotais = "<tr class='totais'>";
		$rowTitulos = "<tr id='titulos' class='titulos'>";

		if (!empty($_POST["empresa"])) {
			if (!in_array($_SERVER["REQUEST_URI"], $dominiosAutotrac)) {
				$rowTotais .=
					"<th colspan='3'>".$arquivoGeral["empr_tx_nome"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Inicio de Jornada"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Fim de Jornada"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Inicio de Refeição"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Fim de Refeição"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Inicio de Espera"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Fim de Espera"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Inicio de Descanso"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Fim de Descanso"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Inicio de Repouso"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Fim de Repouso"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Inicio de Repouso Embarcado"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Fim de Repouso Embarcado"]."</th>";

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
					. "<th colspan='1'>".$arquivoGeral["Inicio de Jornada"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Fim de Jornada"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Inicio de Refeição"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Fim de Refeição"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Inicio de Espera"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Fim de Espera"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Inicio de Descanso"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Fim de Descanso"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Inicio de Repouso"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Fim de Repouso"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Inicio de Repouso Embarcado"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Fim de Repouso Embarcado"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Pernoite - Fim De Jornada"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Refeicao"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Em Espera"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Descanso"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Reinicio De Viagem"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Inicio De Viagem"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Fim De Viagem"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Parada Eventual"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Sol De Desvio De Rota"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Sol Desengate/Bau"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Manutencao"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Macro Msg Livre"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Ag Descarga"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Abastecimento - Arla32"]."</th>"
					. "<th colspan='1'>".$arquivoGeral["Troca De Veículo"]."</th>";

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
			$mostra = false;
			$titulo = "Geral de Ajustes";
			include_once "painel_html2.php";
		} else {
			if (!in_array($_SERVER["REQUEST_URI"], $dominiosAutotrac)) {
				$rowTotais .=
				"<th colspan='1'></th>"
				. "<th colspan='1'></th>"
				. "<th colspan='1'>".$arquivoGeral["Inicio de Jornada"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Fim de Jornada"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Inicio de Refeição"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Fim de Refeição"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Inicio de Espera"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Fim de Espera"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Inicio de Descanso"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Fim de Descanso"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Inicio de Repouso"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Fim de Repouso"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Inicio de Repouso Embarcado"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Fim de Repouso Embarcado"]."</th>";

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
				. "<th colspan='1'>".$arquivoGeral["Inicio de Jornada"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Fim de Jornada"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Inicio de Refeição"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Fim de Refeição"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Inicio de Espera"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Fim de Espera"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Inicio de Descanso"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Fim de Descanso"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Inicio de Repouso"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Fim de Repouso"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Inicio de Repouso Embarcado"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Fim de Repouso Embarcado"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Pernoite - Fim De Jornada"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Refeicao"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Em Espera"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Descanso"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Reinicio De Viagem"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Inicio De Viagem"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Fim De Viagem"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Parada Eventual"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Sol De Desvio De Rota"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Sol Desengate/Bau"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Manutencao"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Macro Msg Livre"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Ag Descarga"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Abastecimento - Arla32"]."</th>"
				. "<th colspan='1'>".$arquivoGeral["Troca De Veículo"]."</th>";

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
	rodape();
}