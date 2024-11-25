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

function index() {
	var_dump($_POST);
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

			// criar_relatorio_ajustes();
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
		$path .= "/" . $_POST["busca_data"] . "/" . $_POST["empresa"];
		// if (is_dir($path)) {
		// }
	} elseif(!empty($_POST["busca_periodo"])){
		$periodoInicio = new DateTime($_POST["busca_periodo"][0]);
		$periodoFim = new DateTime($_POST["busca_periodo"][1]);

		if ($periodoInicio->format("Y-m") === $periodoFim->format("Y-m")) {
			$path .= "/" . $periodoInicio->format("Y-m"). "/" ;
		}

		if(is_dir($path) && file_exists($path . "/empresas.json")){
			$arquivoGeral = json_decode(file_get_contents($path . "/empresas.json"), true);
			$pastaAjuste = dir($path);
			while ($arquivo = $pastaAjuste->read()) {
				if (!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresas"))) {
					$arquivo = $path . $arquivo . "/empresa_" . $arquivo . ".json";
					// $arquivos[] = $arquivo;
					$json = json_decode(file_get_contents($arquivo), true);
					$empresas[] = $json;
				}
			}
			$pastaAjuste->close();
		}
		echo '<br>';
		var_dump($empresas);
		echo '<br>';
	}
	// carregarJS($arquivos);
	rodape();
}