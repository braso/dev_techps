<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
	require_once __DIR__."/funcoes_paineis.php";
	require __DIR__."/../funcoes_ponto.php";

// relatorio_nao_conformidade_juridica();

	// function enviarForm() {
	// 	$_POST["acao"] = $_POST["campoAcao"];
	// 	index();
	// }

	// function carregarJS(array $arquivos) {

	// 	$linha = "linha = '<tr>'";
	// 	if (!empty($_POST["empresa"])) {
	// 		$linha .= "+'<td>'+row.matricula+'</td>'
	// 					+'<td>'+row.nome+'</td>'
	// 					+'<td>'+(row.ocupacao?? '')+'</td>'
	// 					+'<td>'+(row.inicioSemRegistro?? '')+'</td>'
	// 					+'<td>'+(row.inicioRefeicaoSemRegistro	?? '')+'</td>'
	// 					+'<td>'+(row.fimRefeicaoSemRegistro	?? '')+'</td>'
	// 					+'<td>'+(row.fimSemRegistro	?? '')+'</td>'
	// 					+'<td>'+(row.refeicao1h	?? '')+'</td>'
	// 					+'<td>'+(row.refeicao2h	?? '')+'</td>'
	// 					+'<td>'+(row.esperaAberto	?? '')+'</td>'
	// 					+'<td>'+(row.descansoAberto	?? '')+'</td>'
	// 					+'<td>'+(row.repousoAberto	?? '')+'</td>'
	// 					+'<td>'+(row.jornadaAberto	?? '')+'</td>'
	// 					+'<td>'+(row.jornadaExedida	?? '')+'</td>'
	// 					+'<td>'+(row.mdcDescanso	?? '')+'</td>'
	// 					+'<td>'+(row.intersticio	?? '')+'</td>'
	// 				+'</tr>';";
	// 	} else {
	// 		$linha .= "+'<td class=\"nomeEmpresa\" style=\"cursor: pointer;\" onclick=\"setAndSubmit('+row.empr_nb_id+')\">'+row.empr_tx_nome+'</td>'
	// 					+'<td>'+(row.qtdMotoristas?? '')+'</td>'
	// 					+'<td>'+(row.totais.inicioSemRegistro?? '')+'</td>'
	// 					+'<td>'+(row.totais.inicioRefeicaoSemRegistro	?? '')+'</td>'
	// 					+'<td>'+(row.totais.fimRefeicaoSemRegistro	?? '')+'</td>'
	// 					+'<td>'+(row.totais.fimSemRegistro	?? '')+'</td>'
	// 					+'<td>'+(row.totais.refeicao1h	?? '')+'</td>'
	// 					+'<td>'+(row.totais.refeicao2h	?? '')+'</td>'
	// 					+'<td>'+(row.totais.esperaAberto	?? '')+'</td>'
	// 					+'<td>'+(row.totais.descansoAberto	?? '')+'</td>'
	// 					+'<td>'+(row.totais.repousoAberto	?? '')+'</td>'
	// 					+'<td>'+(row.totais.jornadaAberto	?? '')+'</td>'
	// 					+'<td>'+(row.totais.jornadaExedida	?? '')+'</td>'
	// 					+'<td>'+(row.totais.mdcDescanso	?? '')+'</td>'
	// 					+'<td>'+(row.totais.intersticio	?? '')+'</td>'
	// 				+'</tr>';";
	// 	}

	// 	$carregarDados = "";
	// 	foreach ($arquivos as $arquivo) {
	// 		$carregarDados .= "carregarDados('".$arquivo."');";
	// 	}

	// 	echo
	// 		"<form name='myForm' method='post' action='".htmlspecialchars($_SERVER["PHP_SELF"])."'>
	// 			<input type='hidden' name='acao'>
	// 			<input type='hidden' name='campoAcao'>
	// 			<input type='hidden' name='empresa'>
	// 			<input type='hidden' name='busca_dataMes'>
	// 			<input type='hidden' name='busca_dataInicio'>
	// 			<input type='hidden' name='busca_dataFim'>
	// 			<input type='hidden' name='busca_data'>
	// 		</form>
	// 		<script>
	// 			function setAndSubmit(empresa){
	// 				document.myForm.acao.value = 'enviarForm()';
	// 				document.myForm.campoAcao.value = 'buscar';
	// 				document.myForm.empresa.value = empresa;
	// 				document.myForm.busca_dataMes.value = document.getElementById('busca_dataMes').value;
	// 				document.myForm.submit();
	// 			}

	// 			function atualizarPainel(){
	// 				console.info(document.getElementById('busca_data').value);
	// 				document.myForm.empresa.value = document.getElementById('empresa').value;
	// 				document.myForm.busca_data.value = document.getElementById('busca_data').value;
	// 				document.myForm.acao.value = 'atualizar';
	// 				document.myForm.submit();
	// 			}

	// 			function imprimir(){
	// 				window.print();
	// 			}
			
	// 			$(document).ready(function(){
	// 				var tabela = $('#tabela-empresas tbody');

	// 				function carregarDados(urlArquivo){
	// 					$.ajax({
	// 						url: urlArquivo,
	// 						dataType: 'json',
	// 						success: function(data){
	// 							var row = {};
	// 							$.each(data, function(index, item){
	// 								row[index] = item;
	// 							});
	// 							if(row.idMotorista != undefined){
	// 								// Mostrar painel dos motoristas
	// 								delete row.idMotorista;

	// 								if(row.statusEndosso != 'E'){
	// 									row = {
	// 										'matricula': row.matricula,
	// 										'nome': row.nome,
	// 										'ocupacao': row.ocupacao,
	// 										'statusEndosso': row.statusEndosso,
	// 										'saldoAnterior': row.saldoAnterior
	// 									};
	// 								}
	// 							}else{
	// 								// Mostrar painel geral das empresas.
								
	// 								console.log(row['totais']);
	// 								if(row.percEndossado < 1){
	// 									row.totais = {
	// 										'saldoAnterior': row.totais.saldoAnterior
	// 									};
	// 								}
	// 							}
	// 							invalidValues = [undefined, '00:00'];"
	// 							.$linha
	// 							."tabela.append(linha);
	// 						},
	// 						error: function(){
	// 							console.log('Erro ao carregar os dados.');
	// 						}
	// 					});
	// 				}
	// 				// // Função para conversão de Horas para Minutos
	// 				// function horasParaMinutos(horas) {
	// 				//     var partes = horas.split(':');
	// 				//     var horasNumeros = parseInt(partes[0], 10);  // Horas (pode ser positivo ou negativo)
	// 				//     var minutos = parseInt(partes[1], 10);       // Minutos

	// 				//     // Converte as horas para minutos totais
	// 				//     return (horasNumeros*60)+(horasNumeros < 0? -minutos: minutos);
	// 				// }
						
	// 				// // Função para ordenar a tabela
	// 				// function ordenarTabela(coluna, ordem){
	// 				//     var linhas = tabela.find('tr').get();
						
	// 				//     linhas.sort(function(a, b){
	// 				//         var valorA = $(a).children('td').eq(coluna).text();
	// 				//         var valorB = $(b).children('td').eq(coluna).text();

	// 				//         // Verifica se os valores estão no formato HHH:mm (inclui 1, 2 ou 3 dígitos nas horas)
	// 				//         if (valorA.match(/^-?\d{1,3}:\d{2}$/) && valorB.match(/^-?\d{1,3}:\d{2}$/)) {
	// 				//             valorA = horasParaMinutos(valorA);
	// 				//             valorB = horasParaMinutos(valorB);
	// 				//         }

	// 				//         if(valorA < valorB){
	// 				//             return ordem === 'asc'? -1: 1;
	// 				//         }
	// 				//         if(valorA > valorB){
	// 				//             return ordem === 'asc'? 1: -1;
	// 				//         }
	// 				//         return 0;
	// 				//     });

	// 				//     $.each(linhas, function(index, row){
	// 				//         tabela.append(row);
	// 				//     });
	// 				// }

	// 				// // Evento de clique para ordenar a tabela ao clicar no cabeçalho
	// 				// $('#titulos th').click(function(){
	// 				//     var coluna = $(this).index();
	// 				//     var ordem = $(this).data('order');
	// 				//     $('#tabela-empresas th').data('order', 'desc'); // Redefinir ordem de todas as colunas
	// 				//     $(this).data('order', ordem === 'desc'? 'asc': 'desc');
	// 				//     ordenarTabela(coluna, $(this).data('order'));

	// 				//     // Ajustar classes para setas de ordenação
	// 				//     $('#titulos th').removeClass('sort-asc sort-desc');
	// 				//     $(this).addClass($(this).data('order') === 'asc'? 'sort-asc': 'sort-desc');
	// 				// });

	// 				// $('#tabela1 tbody td').click(function(event) {
	// 				//     if ($(this).is(':first-child')) {
	// 				//         var textoPrimeiroTd = $(this).text().trim(); // Pega o texto do primeiro <td>
	// 				//         var status = '';
	// 				//         if(textoPrimeiroTd === 'Não Endossado'){
	// 				//             var status = 'N';
	// 				//         } else if (textoPrimeiroTd === 'Endo. Parcialmente'){
	// 				//             var status = 'EP';
	// 				//         } else{
	// 				//             var status = 'E'
	// 				//         }

	// 				//         $('#tabela-empresas tbody tr').each(function() {
	// 				//             var textoCelula = $(this).find('td').eq(3).text().trim(); // Pegar o texto da primeira célula (coluna 3) de cada linha
	// 				//             // Mostrar ou ocultar a linha com base na comparação
	// 				//             if (textoCelula === status) {
	// 				//                 $(this).show(); // Mostrar linha se o texto da célula corresponder ao valor clicado
	// 				//             } else {
	// 				//                 $(this).hide(); // Ocultar linha se o texto da célula for diferente
	// 				//             }
	// 				//         });

			
	// 				//     } else {
	// 				//         event.stopPropagation(); // Impede que o evento de clique se propague
	// 				//     }
	// 				// });

	// 				// $('#tabela1 thead tr th').click(function(event) {
	// 				//     if ($(this).is(':first-child')) {
	// 				//         var textoPrimeiroTd = $(this).text().trim(); // Pega o texto do primeiro <td>
	// 				//         $('#tabela-empresas tbody tr').each(function() {
	// 				//             $(this).show(); // Mostrar linha se o texto da célula corresponder ao valor clicado
	// 				//         });
	// 				//     } else {
	// 				//         event.stopPropagation(); // Impede que o evento de clique se propague
	// 				//     }
	// 				// });

	// 				// $('#tabela2 tbody td').click(function(event) {
	// 				//     if ($(this).is(':first-child')) {
	// 				//         var textoPrimeiroTd = $(this).text().trim(); // Pega o texto do primeiro <td>

	// 				//         // Definindo a condição de filtro com base no texto do primeiro <td>
	// 				//         var condicao;
	// 				//         if (textoPrimeiroTd === 'Meta') {
	// 				//             condicao = function(textoCelula) {
	// 				//                 return textoCelula === '00:00'; // Exibir se for igual a 00:00
	// 				//             };
	// 				//         } else if (textoPrimeiroTd === 'Positivo') {
	// 				//             condicao = function(textoCelula) {
	// 				//                 return textoCelula > '00:00'; // Exibir se for maior que 00:00
	// 				//             };
	// 				//         } else {
	// 				//             condicao = function(textoCelula) {
	// 				//                 return textoCelula < '00:00'; // Exibir se for menor que 00:00
	// 				//             };
	// 				//         }

	// 				//         // Percorrendo as linhas da tabela #tabela-empresas
	// 				//         $('#tabela-empresas tbody tr').each(function() {
	// 				//             var textoCelula = $(this).find('td').eq(12).text().trim(); // Pegar o texto da coluna 13 de cada linha
	// 				//             // Mostrar ou ocultar a linha com base na condição definida
	// 				//             if (condicao(textoCelula)) {
	// 				//                 $(this).show(); // Mostrar linha se a condição for verdadeira
	// 				//             } else {
	// 				//                 $(this).hide(); // Ocultar linha se a condição for falsa
	// 				//             }
	// 				//         });
	// 				//     } else {
	// 				//         event.stopPropagation(); // Impede que o evento de clique se propague
	// 				//     }
	// 				// });

	// 				// $('#tabela2 thead tr th').click(function(event) {
	// 				//     if ($(this).is(':first-child')) {
	// 				//         var textoPrimeiroTd = $(this).text().trim(); // Pega o texto do primeiro <td>
	// 				//         $('#tabela-empresas tbody tr').each(function() {
	// 				//             $(this).show(); // Mostrar linha se o texto da célula corresponder ao valor clicado
	// 				//         });
	// 				//     } else {
	// 				//         event.stopPropagation(); // Impede que o evento de clique se propague
	// 				//     }
	// 				// });


	// 				".$carregarDados. "
	// 			});
	// 			//Variação dos campos de pesquisa{
    //                 var camposAcao = document.getElementsByName('campoAcao');
    //                 if (camposAcao[0].checked){
    //                     document.getElementById('botaoContexBuscar').innerHTML = 'Buscar';
    //                 }
    //                 if (camposAcao[1].checked){
    //                     document.getElementById('botaoContexBuscar').innerHTML = 'Atualizar';
    //                 }
    //                 camposAcao[0].addEventListener('change', function() {
    //                     if (camposAcao[0].checked){
    //                         document.getElementById('botaoContexBuscar').innerHTML = 'Buscar';
    //                     }
    //                 });
    //                 camposAcao[1].addEventListener('change', function() {
    //                     if (camposAcao[1].checked){
    //                         document.getElementById('botaoContexBuscar').innerHTML = 'Atualizar';
    //                     }
    //                 });
    //             //}
	// 		</script>"
	// 	;
	// }

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
			combo_net("Empresa", "empresa", $_POST["empresa"]?? "", 4, "empresa", ""),
			$campoAcao,
			campo_mes("Mês*", "busca_dataMes", ($_POST["busca_dataMes"] ?? date("Y-m")), 2),
			combo("Endossado",	"busca_endossado", (!empty($_POST["busca_endossado"]) ? $_POST["busca_endossado"] : ""), 2, ["endossado" => "Sim", "naoEndossado" => "Não"])
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


			$path .= "/".$_POST["busca_dataMes"]."/".$empresa["empr_nb_id"];
			if (is_dir($path)) {
				$pastaSaldosEmpresa = dir($path);
				while ($arquivo = $pastaSaldosEmpresa->read()) {
					if (!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
						$arquivos[] = $arquivo;
					}
				}
				$pastaSaldosEmpresa->close();

				$dataEmissao = "Atualizado em: ".date("d/m/Y H:i", filemtime($path."/empresa_".$empresa["empr_nb_id"].".json")); //Utilizado no HTML.
				$periodoRelatorio = json_decode(file_get_contents($path."/empresa_".$empresa["empr_nb_id"].".json"), true);
				$periodoRelatorio = [
					"dataInicio" => $periodoRelatorio["dataInicio"],
					"dataFim" => $periodoRelatorio["dataFim"]
				];


				$motoristas = [];
				foreach ($arquivos as $arquivo) {
					$json = json_decode(file_get_contents($path."/".$arquivo), true);
					$json["dataAtualizacao"] = date("d/m/Y H:i", filemtime($path."/".$arquivo));
					foreach ($totais as $key => $value) {
						$totais[$key] += $json[$key];
					}
					$motoristas[] = $json;
				}

				foreach ($arquivos as &$arquivo) {
					$arquivo = $path."/".$arquivo;
				}
				$totais["empresaNome"] = $empresa["empr_tx_nome"];
			} else {
				$encontrado = false;
			}
		} elseif (!empty($_POST["busca_dataMes"])) {
			//Painel geral das empresas
			$empresas = [];
			$logoEmpresa = mysqli_fetch_assoc(query(
				"SELECT empr_tx_logo FROM empresa"
					." WHERE empr_tx_status = 'ativo'"
					." AND empr_tx_Ehmatriz = 'sim'"
					." LIMIT 1;"
			))["empr_tx_logo"]; //Utilizado no HTML.

			$logoEmpresa = $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/".$logoEmpresa;


			$path .= "/".$_POST["busca_dataMes"];


			if (is_dir($path) && file_exists($path."/empresas.json")) {
				$dataEmissao = "Atualizado em: ".date("d/m/Y H:i", filemtime($path."/empresas.json")); //Utilizado no HTML.
				$arquivoGeral = json_decode(file_get_contents($path."/empresas.json"), true);

				$periodoRelatorio = [
					"dataInicio" => $arquivoGeral["dataInicio"],
					"dataFim" => $arquivoGeral["dataFim"]
				];

				$pastaSaldos = dir($path);
				while ($arquivo = $pastaSaldos->read()) {
					if (!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresas"))) {
						$arquivo = $path."/".$arquivo."/empresa_".$arquivo.".json";
						$arquivos[] = $arquivo;
						$json = json_decode(file_get_contents($arquivo), true);
						foreach ($totais as $key => $value) {
							$totais[$key] +=  $json["totais"][$key];
						}
						$empresas[] = $json;
					}
				}
				$pastaSaldos->close();

			} else {
				$encontrado = false;
			}
		}

		if ($encontrado) {
			$rowTotais = "<tr class='totais'>";
			$rowTitulos = "<tr id='titulos' class='titulos'>";

			if (!empty($_POST["empresa"])) {
				$rowTotais .=
					"<th colspan='2'>".$totais["empresaNome"]."</th>"
					."<th colspan='1'></th>"
					."<th colspan='1'>".$totais["inicioSemRegistro"]."</th>"
					."<th colspan='1'>".$totais["inicioRefeicaoSemRegistro"]."</th>"
					."<th colspan='1'>".$totais["fimRefeicaoSemRegistro"]."</th>"
					."<th colspan='1'>".$totais["fimSemRegistro"]."</th>"
					."<th colspan='1'>".$totais["refeicao1h"]."</th>"
					."<th colspan='1'>".$totais["refeicao2h"]."</th>"
					."<th colspan='1'>".$totais["esperaAberto"]."</th>"
					."<th colspan='1'>".$totais["descansoAberto"]."</th>"
					."<th colspan='1'>".$totais["repousoAberto"]."</th>"
					."<th colspan='1'>".$totais["jornadaAberto"]."</th>"
					."<th colspan='1'>".$totais["jornadaExedida"]."</th>"
					."<th colspan='1'>".$totais["mdcDescanso"]."</th>"
					."<th colspan='1'>".$totais["intersticio"]."</th>";

				$rowTitulos .=
					"<th class='matricula'>Matrícula</th>"
					."<th class='nome'>Nome</th>"
					."<th class='ocupacao'>Ocupação</th>"
					."<th class='status'>Início da Jornada</th>"
					."<th class='jornadaPrevista'>Início da Refeição</th>"
					."<th class='jornadaEfetiva'>Fim da Refeição</th>"
					."<th class='he50APagar'>Fim da Jornada</th>"
					."<th class='he100APagar'>Refeição menor que 01h</th>"
					."<th class='adicionalNoturno'>Refeição menor que 02h</th>"
					."<th class='esperaIndenizada'>Espera em Aberto</th>"
					."<th class='saldoAnterior'>Descanso em Aberto</th>"
					."<th class='saldoPeriodo'>Repouso em Aberto</th>"
					."<th class='saldoFinal'>Jornada em Aberto</th>"
					."<th class='saldoFinal'>Tempo de Jornada Excedido</th>"
					."<th class='saldoFinal'>Descanso de MDC não Respeitado</th>"
					."<th class='saldoFinal'>Interstício</th>";
			} else {
				$rowTotais .=
					"<th colspan='1'></th>"
					."<th colspan='1'></th>"
					."<th colspan='1'>".$totais["inicioSemRegistro"]."</th>"
					."<th colspan='1'>".$totais["inicioRefeicaoSemRegistro"]."</th>"
					."<th colspan='1'>".$totais["fimRefeicaoSemRegistro"]."</th>"
					."<th colspan='1'>".$totais["fimSemRegistro"]."</th>"
					."<th colspan='1'>".$totais["refeicao1h"]."</th>"
					."<th colspan='1'>".$totais["refeicao2h"]."</th>"
					."<th colspan='1'>".$totais["esperaAberto"]."</th>"
					."<th colspan='1'>".$totais["descansoAberto"]."</th>"
					."<th colspan='1'>".$totais["repousoAberto"]."</th>"
					."<th colspan='1'>".$totais["jornadaAberto"]."</th>"
					."<th colspan='1'>".$totais["jornadaExedida"]."</th>"
					."<th colspan='1'>".$totais["mdcDescanso"]."</th>"
					."<th colspan='1'>".$totais["intersticio"]."</th>";
				$rowTitulos .=
					"<th data-column='nome' data-order='asc'>Nome da Empresa/Filial</th>"
					."<th data-column='nome' data-order='asc'>Qtd.Motoristas</th>"
					."<th class='status'>Início da Jornada</th>"
					."<th class='jornadaPrevista'>Início da Refeição</th>"
					."<th class='jornadaEfetiva'>Fim da Refeição</th>"
					."<th class='he50APagar'>Fim da Jornada</th>"
					."<th class='he100APagar'>Refeição menor que 01h</th>"
					."<th class='adicionalNoturno'>Refeição menor que 02h</th>"
					."<th class='esperaIndenizada'>Espera em Aberto</th>"
					."<th class='saldoAnterior'>Descanso em Aberto</th>"
					."<th class='saldoPeriodo'>Repouso em Aberto</th>"
					."<th class='saldoFinal'>Jornada em Aberto</th>"
					."<th class='saldoFinal'>Tempo de Jornada Excedido</th>"
					."<th class='saldoFinal'>Descanso de MDC não Respeitado</th>"
					."<th class='saldoFinal'>Interstício</th>";
			}
			$rowTotais .= "</tr>";
			$rowTitulos .= "</tr>";

			$titulo = "Geral de saldo";
			include_once "painel_html2.php";

			echo "<div class='script'>"
				."<script>"
				.((!empty($_POST["empresa"]))? "document.getElementById('tabela1').style.display = 'table';": "")
				// ."console.log(endossos);"
				."document.getElementsByClassName('porcentagemEndo')[0].getElementsByTagName('td')[1].innerHTML = endossos.totais.E;
						document.getElementsByClassName('porcentagemEndoPc')[0].getElementsByTagName('td')[1].innerHTML = endossos.totais.EP;
						document.getElementsByClassName('porcentagemNaEndo')[0].getElementsByTagName('td')[1].innerHTML = endossos.totais.N;
						document.getElementsByClassName('porcentagemEndo')[0].getElementsByTagName('td')[2].innerHTML = Math.round(endossos.porcentagens.E*10000)/100+'%';
						document.getElementsByClassName('porcentagemEndoPc')[0].getElementsByTagName('td')[2].innerHTML = Math.round(endossos.porcentagens.EP*10000)/100+'%';
						document.getElementsByClassName('porcentagemNaEndo')[0].getElementsByTagName('td')[2].innerHTML = Math.round(endossos.porcentagens.N*10000)/100+'%';
						
						document.getElementsByClassName('porcentagemPosi')[0].getElementsByTagName('td')[1].innerHTML = saldos.totais.positivos;
						document.getElementsByClassName('porcentagemMeta')[0].getElementsByTagName('td')[1].innerHTML = saldos.totais.meta;
						document.getElementsByClassName('porcentagemNega')[0].getElementsByTagName('td')[1].innerHTML = saldos.totais.negativos;
						document.getElementsByClassName('porcentagemPosi')[0].getElementsByTagName('td')[2].innerHTML = Math.round(saldos.porcentagens.positivos*10000)/100+'%';
						document.getElementsByClassName('porcentagemMeta')[0].getElementsByTagName('td')[2].innerHTML = Math.round(saldos.porcentagens.meta*10000)/100+'%';
						document.getElementsByClassName('porcentagemNega')[0].getElementsByTagName('td')[2].innerHTML = Math.round(saldos.porcentagens.negativos*10000)/100+'%';
						document.getElementsByClassName('script')[0].innerHTML = '';
					</script>";
			echo "</div>";
		} else {
			if (!empty($_POST["acao"]) && $_POST["acao"] == "buscar") {
				set_status("Não Possui dados desse mês");
				echo "<script>alert('Não Possui dados desse mês')</script>";
			}
		}

		// carregarJS($arquivos);
		rodape();
	}
