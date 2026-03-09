<?php
	include_once "funcoes_ponto.php";

	function gerarTabelaNaoConformidade($motorista, $dataMes) {

		$monthDate = new DateTime($dataMes . "-01");
		$rows = [];

		$dataAdmissao = new DateTime($motorista["enti_tx_admissao"]);

		for ($date = new DateTime($monthDate->format("Y-m-1")); $date->format("Y-m-d") <= $monthDate->format("Y-m-t"); $date->modify("+1 day")) {

			if ($monthDate->format("Y-m") < $dataAdmissao->format("Y-m")) {
				continue;
			}

			if ($date->format("Y-m-d") > date("Y-m-d")) {
				break;
			}

			$aDetalhado = diaDetalhePonto($motorista, $date->format("Y-m-d"));

			$colunasAManterZeros = ["inicioJornada","inicioRefeicao","fimRefeicao","fimJornada","jornadaPrevista","diffSaldo"];

			unset($aDetalhado['inicioEscala'],$aDetalhado['fimEscala']);

			foreach ($aDetalhado as $key => $value) {

				if (in_array($key,$colunasAManterZeros)) {
					continue;
				}

				if ($aDetalhado[$key] == "00:00") {
					$aDetalhado[$key] = "";
				}
			}

			$row = array_merge(
				[verificaTolerancia($aDetalhado["diffSaldo"],$date->format("Y-m-d"),$motorista["enti_nb_id"])],
				$aDetalhado
			);

			$qtdErros = 0;

			foreach ($row as $value) {

				preg_match_all("/(?<=<)([^<|>])+(?=>)/",$value,$tags);

				if (!empty($tags[0])) {

					foreach ($tags[0] as $tag) {

						$qtdErros += substr_count($tag,"fa-warning") * (substr_count($tag,"color:red;") || substr_count($tag,"color:orange;"))
						+ ((is_int(strpos($tag,"fa-info-circle"))) * (substr_count($tag,"color:red;") || substr_count($tag,"color:orange;")));

					}

				}

			}

			if (is_int(strpos($row["inicioJornada"] ?? "","Batida início de jornada não registrada!")) 
			&& is_int(strpos($row["jornadaPrevista"] ?? "","Abono: "))) {

				$qtdErros = 0;

			}

			if ($qtdErros > 0) {

				$rows[] = $row;

			}

		}

		if (empty($rows)) {

			return "<p style='color:green;'>✓ Nenhuma não conformidade encontrada para este mês.</p>";

		}

		$totalResumo = setTotalResumo(array_slice(array_keys($rows[0]),7));

		somarTotais($totalResumo,$rows);

		$cabecalho = [
			"","DATA","<div style='margin:11px'>DIA</div>","INÍCIO JORNADA","INÍCIO REFEIÇÃO","FIM REFEIÇÃO","FIM JORNADA",
			"REFEIÇÃO","DESCANSO","JORNADA",
			"JORNADA PREVISTA","JORNADA EFETIVA","INTERSTÍCIO",
			"H.E. {$motorista["enti_tx_percHESemanal"]}%","H.E. {$motorista["enti_tx_percHEEx"]}%",
			"ADICIONAL NOT.","SALDO DIÁRIO(**)"
		];

		if (in_array($motorista["enti_tx_ocupacao"],["Ajudante","Motorista"])) {

			$cabecalho = array_merge(
				array_slice($cabecalho,0,8),
				["ESPERA"],
				array_slice($cabecalho,8,1),
				["REPOUSO"],
				array_slice($cabecalho,9,3),
				["MDC"],
				array_slice($cabecalho,12,4),
				["ESPERA INDENIZADA"],
				array_slice($cabecalho,16,count($cabecalho))
			);

		}

		$rows[] = array_values(array_merge(["","","","","","","<b>TOTAL</b>"],$totalResumo));

		return montarTabelaPonto($cabecalho,$rows);

	}


	function index() {

		cabecalho("Ajuste de Ponto");

		$idMotorista = $_POST["idMotorista"] ?? 0;

		$motorista = mysqli_fetch_assoc(query("
			SELECT enti_nb_id, enti_tx_matricula, enti_tx_nome, enti_tx_ocupacao,
			enti_tx_cpf, enti_tx_admissao, enti_tx_jornadaSemanal,
			enti_tx_jornadaSabado, enti_tx_percHESemanal,
			enti_tx_percHEEx, enti_nb_parametro
			FROM entidade
			WHERE enti_tx_status = 'ativo'
			AND enti_nb_id = {$idMotorista}
			LIMIT 1
		"));

		$textFields = [];
		$campoJust = [];
		$botoes = [];

		$textFields[] = texto("Matrícula",$motorista["enti_tx_matricula"] ?? "",2);
		$textFields[] = texto($motorista["enti_tx_ocupacao"] ?? "Motorista",$motorista["enti_tx_nome"] ?? "",5);
		$textFields[] = texto("CPF",$motorista["enti_tx_cpf"] ?? "",3);

		$variableFields = [

			campo_data("Data*","data",($_POST["data"] ?? ""),2,"id='dataFiltro'"),
			campo_hora("Hora*","hora",($_POST["hora"] ?? ""),2),

			combo_bd("Tipo de Registro*","idMacro",($_POST["idMacro"] ?? ""),4,"macroponto","","ORDER BY macr_nb_id"),

			combo_bd("Motivo*","motivo",($_POST["motivo"] ?? ""),4,"motivo",""," AND moti_tx_tipo = 'Ajuste' ORDER BY moti_tx_nome")

		];

		$campoJust[] = textarea("Justificativa","justificativa",($_POST["justificativa"] ?? ""),12,'maxlength=680');

		$botoes[] = "<button class='btn default' type='button' onclick='window.print()'>Imprimir</button>";
		$botoes[] = criarBotaoVoltar("espelho_ponto.php");

		echo abre_form("Dados do Ajuste de Ponto");
		echo linha_form($textFields);
		echo linha_form($variableFields);
		echo linha_form($campoJust);
		echo fecha_form($botoes);

		$dataMes = date("Y-m");

		echo "<h3>Não Conformidades </h3>";//.date("m/Y",strtotime($dataMes."-01"))."</h3>";

		echo "<div id='tabelaNaoConformidadeContainer'>";
		echo gerarTabelaNaoConformidade($motorista,$dataMes);
		echo "</div>";

		echo "<div id='mensagemSemDados' style='display:none'>
		<p style='color:green;'>✓ Nenhuma não conformidade encontrada para o dia selecionado.</p>
		</div>";

		rodape();

	}

	index();
?>

<script>

document.addEventListener("DOMContentLoaded",function(){

	const campoData = document.getElementById("dataFiltro");

	if(!campoData) return;

	campoData.addEventListener("change",filtrar);
	campoData.addEventListener("input",filtrar);

	function filtrar(){

		const dataSelecionada = campoData.value;

		const tabela = document.querySelector("#tabelaNaoConformidadeContainer table");

		if(!tabela) return;

		const linhas = tabela.querySelectorAll("tbody tr");

		if(!dataSelecionada){

			linhas.forEach(l=>l.style.display="");

			document.getElementById("mensagemSemDados").style.display="none";

			return;

		}

		const dataFormatada = new Date(dataSelecionada).toLocaleDateString("pt-BR");

		let encontrou=false;

		linhas.forEach(linha=>{

			const celulaData = linha.querySelector("td:nth-child(2)");

			if(!celulaData) return;

			if(linha.innerText.includes("TOTAL")){
				linha.style.display="none";
				return;
			}

			if(celulaData.textContent.trim() === dataFormatada){

				linha.style.display="";
				encontrou=true;

			}else{

				linha.style.display="none";

			}

		});

		document.getElementById("mensagemSemDados").style.display = encontrou ? "none" : "block";

	}

});

</script>