<?php
/* Modo debug
	ini_set("display_errors", 1);
	error_reporting(E_ALL);
//*/
require "../funcoes_ponto.php";
require_once __DIR__."/funcoes_paineis.php";

// relatorio_nao_conformidade_juridica();

function index() {
	require_once __DIR__ . "/funcoes_paineis.php";


	if (!empty($_POST["atualizar"])) {
		echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
		ob_flush();
		flush();
		cabecalho("Relatório de Não Conformidade Juridica");
		relatorio_nao_conformidade_juridica();
	} else {
		cabecalho("Relatório de Não Conformidade Juridica");
	}

	$extraCampoData = "";
	if (empty($_POST["busca_data"])) {
		$_POST["busca_data"] = date("Y-m");
	}

	// $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
	//position: absolute; top: 101px; left: 420px;
	$fields = [
		combo_net("Empresa:", "empresa", $_POST["empresa"] ?? "", 4, "empresa", ""),
		campo_mes("Data", "busca_data", ($_POST["busca_data"] ?? ""), 2, $extraCampoData)
		// $texto,
	];
	$botao_volta = "";
	if (!empty($_POST["empresa"])) {
		$botao_volta = "<button class='btn default' type='button' onclick='setAndSubmit(\"\")'>Voltar</button>";
	}
	$botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";
	if (!empty($_SESSION["user_tx_nivel"]) && is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
		$botaoAtualizarPainel = "<a class='btn btn-warning' onclick='atualizarPainel()'> Atualizar Painel</a>";
	}
	$buttons = [
		botao("Buscar", "index", "", "", "", "", "btn btn-info"),
		$botao_imprimir,
		$botao_volta,
		$botaoAtualizarPainel
	];


	abre_form();
	linha_form($fields);
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

	if (!empty($_POST["empresa"]) && !empty($_POST["busca_data"])) {
		//Painel dos endossos dos motoristas de uma empresa específica
		$empresa = mysqli_fetch_assoc(query(
			"SELECT * FROM empresa"
				. " WHERE empr_tx_status = 'ativo'"
				. " AND empr_nb_id = " . $_POST["empresa"]
				. " LIMIT 1;"
		));


		$path .= "/" . $_POST["busca_data"] . "/" . $empresa["empr_nb_id"];

		if (is_dir($path)) {
			$pastaSaldosEmpresa = dir($path);
			while ($arquivo = $pastaSaldosEmpresa->read()) {
				if (!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
					$arquivos[] = $arquivo;
				}
			}
			$pastaSaldosEmpresa->close();

			$dataEmissao = "Atualizado em: " . date("d/m/Y H:i", filemtime($path . "/empresa_" . $empresa["empr_nb_id"] . ".json")); //Utilizado no HTML.
			$periodoRelatorio = json_decode(file_get_contents($path . "/empresa_" . $empresa["empr_nb_id"] . ".json"), true);
			$periodoRelatorio = [
				"dataInicio" => $periodoRelatorio["dataInicio"],
				"dataFim" => $periodoRelatorio["dataFim"]
			];

			
			$motoristas = [];
			foreach ($arquivos as $arquivo) {
				$json = json_decode(file_get_contents($path . "/" . $arquivo), true);
				$json["dataAtualizacao"] = date("d/m/Y H:i", filemtime($path . "/" . $arquivo));
				foreach ($totais as $key => $value) {
					$totais[$key] += $json[$key];
				}
				$motoristas[] = $json;
			}
			
			foreach ($arquivos as &$arquivo) {
				$arquivo = $path . "/" . $arquivo;
			}
			$totais["empresaNome"] = $empresa["empr_tx_nome"];

		} else {
			$encontrado = false;
		}
	}  elseif (!empty($_POST["busca_data"])) {
		//Painel geral das empresas
		$empresas = [];
		$logoEmpresa = mysqli_fetch_assoc(query(
			"SELECT empr_tx_logo FROM empresa"
				. " WHERE empr_tx_status = 'ativo'"
				. " AND empr_tx_Ehmatriz = 'sim'"
				. " LIMIT 1;"
		))["empr_tx_logo"]; //Utilizado no HTML.

		$logoEmpresa = $_ENV["APP_PATH"] . $_ENV["CONTEX_PATH"] . "/" . $logoEmpresa;


		$path .= "/" . $_POST["busca_data"];


		if (is_dir($path) && file_exists($path . "/empresas.json")) {
			$dataEmissao = "Atualizado em: " . date("d/m/Y H:i", filemtime($path . "/empresas.json")); //Utilizado no HTML.
			$arquivoGeral = json_decode(file_get_contents($path . "/empresas.json"), true);

			$periodoRelatorio = [
				"dataInicio" => $arquivoGeral["dataInicio"],
				"dataFim" => $arquivoGeral["dataFim"]
			];

			// $pastaEndossos = dir($path);
			// while ($arquivo = $pastaEndossos->read()) {
			// 	if (!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresas"))) {
			// 		$arquivo = $path . "/" . $arquivo . "/empresa_" . $arquivo . ".json";
			// 		$arquivos[] = $arquivo;
			// 		$json = json_decode(file_get_contents($arquivo), true);
			// 		foreach ($totais as $key => $value) {
			// 			$totais[$key] =  $json["totais"][$key];
			// 		}

			// 	}
			// }
			// $pastaEndossos->close();

		} else {
			$encontrado = false;
		}
	}

	if ($encontrado) {
		$rowTotais = "<tr class='totais'>";
		$rowTitulos = "<tr id='titulos' class='titulos'>";

		if (!empty($_POST["empresa"])) {
			$rowTotais .=
				"<th colspan='2'>" . $totais["empresaNome"] . "</th>"
				. "<th colspan='1'></th>"
				. "<th colspan='1'></th>"
				. "<th colspan='1'>" . $totais["inicioSemRegistro"] . "</th>"
				. "<th colspan='1'>" . $totais["inicioRefeicaoSemRegistro"] . "</th>"
				. "<th colspan='1'>" . $totais["fimRefeicaoSemRegistro"] . "</th>"
				. "<th colspan='1'>" . $totais["fimSemRegistro"] . "</th>"
				. "<th colspan='1'>" . $totais["refeicao1h"] . "</th>"
				. "<th colspan='1'>" . $totais["refeicao2h"] . "</th>"
				. "<th colspan='1'>" . $totais["esperaAberto"] . "</th>"
				. "<th colspan='1'>" . $totais["descansoAberto"] . "</th>"
				. "<th colspan='1'>" . $totais["repousoAberto"] . "</th>"
				. "<th colspan='1'>" . $totais["jornadaAberto"] . "</th>"
				. "<th colspan='1'>" . $totais["jornadaExedida"] . "</th>"
				. "<th colspan='1'>" . $totais["mdcDescanso"] . "</th>"
				. "<th colspan='1'>" . $totais["intersticio"] . "</th>";

			$rowTitulos .=
				"<th class='matricula'>Matrícula</th>"
				. "<th class='nome'>Nome</th>"
				. "<th class='ocupacao'>Ocupação</th>"
				. "<th class='status'>Status Endosso</th>"
				. "<th class='jornadaPrevista'>Jornada Prevista</th>"
				. "<th class='jornadaEfetiva'>Jornada Efetiva</th>"
				. "<th class='he50APagar'>H.E. Semanal Pago</th>"
				. "<th class='he100APagar'>H.E. Ex. Pago</th>"
				. "<th class='adicionalNoturno'>Adicional Noturno</th>"
				. "<th class='esperaIndenizada'>Espera Indenizada</th>"
				. "<th class='saldoAnterior'>Saldo Anterior</th>"
				. "<th class='saldoPeriodo'>Saldo Período</th>"
				. "<th class='saldoFinal'>Saldo Final</th>";
		} else {
			$rowTotais .=
				"<th colspan='1'></th>"
				. "<th colspan='1'></th>"
				. "<th colspan='1'></th>"
				. "<th colspan='1'>" . $totais["jornadaPrevista"] . "</th>"
				. "<th colspan='1'>" . $totais["jornadaEfetiva"] . "</th>"
				. "<th colspan='1'> " . (($totais["he50APagar"] == "00:00") ? "" : $totais["he50APagar"]) . "</th>"
				. "<th colspan='1'> " . (($totais["he100APagar"] == "00:00") ? "" : $totais["he100APagar"]) . "</th>"
				. "<th colspan='1'> " . (($totais["adicionalNoturno"] == "00:00") ? "" : $totais["adicionalNoturno"]) . "</th>"
				. "<th colspan='1'> " . (($totais["esperaIndenizada"] == "00:00") ? "" : $totais["esperaIndenizada"]) . "</th>"
				. "<th colspan='1'> " . (($totais["saldoAnterior"] == "00:00") ? "" : $totais["saldoAnterior"]) . "</th>"
				. "<th colspan='1'> " . (($totais["saldoPeriodo"] == "00:00") ? "" : $totais["saldoPeriodo"]) . "</th>"
				. "<th colspan='1'> " . (($totais["saldoFinal"] == "00:00") ? "" : $totais["saldoFinal"]) . "</th>";

			$rowTitulos .=
				"<th data-column='nome' data-order='asc'>Nome da Empresa/Filial</th>"
				. "<th data-column='percEndossados' data-order='asc'>% Endossados</th>"
				. "<th data-column='qtdMotoristas' data-order='asc'>Qtd. Motoristas</th>"
				. "<th data-column='jornadaPrevista' data-order='asc'>Jornada Prevista</th>"
				. "<th data-column='JornadaEfetiva' data-order='asc'>Jornada Efetiva</th>"
				. "<th data-column='he50APagar' data-order='asc'>H.E. Semanal Pago</th>"
				. "<th data-column='he100APagar' data-order='asc'>H.E. Ex. Pago</th>"
				. "<th data-column='adicionalNoturno' data-order='asc'>Adicional Noturno</th>"
				. "<th data-column='esperaIndenizada' data-order='asc'>Espera Indenizada</th>"
				. "<th data-column='saldoAnterior' data-order='asc'>Saldo Anterior</th>"
				. "<th data-column='saldoPeriodo' data-order='asc'>Saldo Periodo</th>"
				. "<th data-column='saldoFinal' data-order='asc'>Saldo Final</th>";
		}
		$rowTotais .= "</tr>";
		$rowTitulos .= "</tr>";

		$titulo = "de Não Conformidade Juridica"; // usado no html
		include_once "painel_html2.php";

		echo "<div class='script'>";
		
	} else {
		if (!empty($_POST["acao"])) {
			echo "<script>alert('Não Possui dados desse mês')</script>";
		}
	}

	echo "</div>";


	// carregarJS($arquivos);
	rodape();
}