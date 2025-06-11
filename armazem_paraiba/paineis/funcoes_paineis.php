<?php

/* Modo debug{
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//}*/
function campo_dataHora($nome, $variavel, $modificador, $tamanho, $extra = '') {
	$classe = "form-control input-sm campo-fit-content";

	if (!empty($_POST["errorFields"]) && in_array($variavel, $_POST["errorFields"])) {
		$classe .= " error-field";
	}

	if (empty($modificador)) {
		$dataHora = DateTime::createFromFormat("Y-m-d H:i", date("Y-m-d H:i"));
	} else {
		// converte de d/m/Y H:i para Y-m-d H:i
		$tempData = DateTime::createFromFormat("d/m/Y H:i", $modificador);
		$dataHora = DateTime::createFromFormat("Y-m-d H:i", $tempData->format("Y-m-d H:i"));
	}
	// $dataHora = DateTime::createFromFormat("Y-m-d H:i", date("Y-m-d H:i"));

	$dataScript =
		"<script type='text/javascript' src='" . $_ENV["APP_PATH"] . $_ENV["CONTEX_PATH"] . "/js/moment.min.js'></script>
		<script type='text/javascript' src='" . $_ENV["APP_PATH"] . $_ENV["CONTEX_PATH"] . "/js/daterangepicker.min.js'></script>
		<link rel='stylesheet' type='text/css' href='" . $_ENV["APP_PATH"] . $_ENV["CONTEX_PATH"] . "/js/daterangepicker.css' />

		<script>
			$(function() {
				$('input[name=\"$variavel\"]').daterangepicker({
					singleDatePicker: true, // Seleciona apenas uma data
					timePicker: true, // Habilita a seleção de hora
					timePicker24Hour: true, // Usa o formato de 24 horas
					//timePickerIncrement: 30, // Incremento de 30 minutos
					opens: 'left',
					startDate: '" . $dataHora->format("d/m/Y H:i") . "',
					locale: {
						format: 'DD/MM/YYYY HH:mm', // Formato de exibição
						separator: ' - ',
						applyLabel: 'Aplicar',
						cancelLabel: 'Cancelar',
						customRangeLabel: 'Custom',
						daysOfWeek: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
						monthNames: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
						firstDay: 1
					},
					minDate: moment().startOf('minute'),
				}, function(start, end, label) {
					// Atualiza o campo oculto com a data e hora selecionadas
					$('input[name=\"" . $variavel . "\"]').val(start.format('DD/MM/YYYY HH:mm'));
				});

				$('input[name=\"$variavel\"]').isOverwriteMode = true;

				// Máscara de entrada para data e hora
				$('input[name=\"$variavel\"]').inputmask({mask: ['99/99/9999 99:99'], placeholder: '01/01/2023 00:00'});
				$('input[name=\"$variavel\"]').css('min-width', 'max-content');
			});
		</script>";

	$campo =
		"<div class='col-sm-" . $tamanho . " margin-bottom-5 campo-fit-content'>
			<label>" . $nome . "</label>
			<input name='" . $variavel . "' id='" . $variavel . "' autocomplete='off' type='text' class='" . $classe . "' " . $extra . ">
			<input name='" . $variavel . "' value='" . $dataHora->format("Y-m-d H:i") . "' autocomplete='off' type='hidden' class='" . $classe . "' " . $extra . ">
		</div>";

	return $campo . $dataScript;
}
//Funções comuns aos paineis{
function calcPercs(array $values): array {
	$total = 0;
	foreach ($values as $value) {
		$total += $value;
	}

	if ($total == 0) {
		return array_pad([], sizeof($values), 0);
	}

	$percentuais = array_pad([], sizeof($values), 0);
	for ($f = 0; $f < sizeof($values); $f++) {
		$percentuais[$f] = $values[$f] / $total;
	}

	return $percentuais;
}

function salvarArquivo(string $path, string $fileName, string $data) {
	if (!is_dir($path)) {
		mkdir($path, 0755, true);
	}
	$data = json_encode($data, JSON_UNESCAPED_UNICODE);
	file_put_contents($path . '/' . $fileName, $data);
}
//}

//Funções de criação de cada painel{
function criar_relatorio_saldo() {

	global $totalResumo;

	//Conferir se os campos POST estão preenchidos{
	$camposObrig = ["busca_dataMes" => "Mês"];
	$errorMsg = conferirCamposObrig($camposObrig, $_POST);
	if (!empty($errorMsg)) {
		set_status("ERRO: " . $errorMsg);
		unset($_POST["acao"]);
		index();
		exit;
	}

	//}

	$dataMes = DateTime::createFromFormat("Y-m-d H:i:s", $_POST["busca_dataMes"] . "-01 00:00:00");
	$dataFim = DateTime::createFromFormat("Y-m-d H:i:s", (date("Y-m-d") < $dataMes->format("Y-m-t") ? date("Y-m-d") : $dataMes->format("Y-m-t")) . " 00:00:00");

	$empresas = mysqli_fetch_all(query(
		"SELECT empr_nb_id, empr_tx_nome FROM empresa"
			. " WHERE empr_tx_status = 'ativo'"
			. (!empty($_POST["empresa"]) ? " AND empr_nb_id = " . $_POST["empresa"] : "")
			. " ORDER BY empr_tx_nome ASC;"
	), MYSQLI_ASSOC);

	$totaisEmpresas = [
		"jornadaPrevista" 	=> "00:00",
		"jornadaEfetiva" 	=> "00:00",
		"HESemanal" 		=> "00:00",
		"HESabado" 			=> "00:00",
		"adicionalNoturno" 	=> "00:00",
		"esperaIndenizada" 	=> "00:00",
		"saldoAnterior" 	=> "00:00",
		"saldoPeriodo" 		=> "00:00",
		"saldoFinal" 		=> "00:00",
		"qtdMotoristas" 	=> 0
	];

	foreach ($empresas as $empresa) {
		$path = "./arquivos/saldos" . "/" . $dataMes->format("Y-m") . "/" . $empresa["empr_nb_id"];
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}

		if (is_dir($path)) {
			$pasta = dir($path);
			while (($arquivo = $pasta->read()) !== false) {
				// Ignora os diretórios especiais '.' e '..'
				if ($arquivo != '.' && $arquivo != '..') {
					$arquivoPath = $path . '/' . $arquivo;  // Caminho completo do 
					unlink($arquivoPath);  // Apaga o arquivo
				}
			}
			$pasta->close();
		}

		if (file_exists($path . "/empresa_" . $empresa["empr_nb_id"] . ".json")) {
			if (date("Y-m-d", filemtime($path . "/empresa_" . $empresa["empr_nb_id"] . ".json")) == date("Y-m-d")) {
				// continue;
			}
		}
		
		$motoristas = mysqli_fetch_all(query(
			"SELECT * FROM entidade
						LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
						LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
						LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
						WHERE enti_tx_status = 'ativo'
							AND DATE_FORMAT(enti_tx_dataCadastro, '%Y-%m') <= '{$dataMes->format("Y-m")}'
							AND enti_nb_empresa = '{$empresa["empr_nb_id"]}'
							" . (!empty($_POST["motorista"]) ? "AND enti_nb_id = '{$_POST["motorista"]}'" : "") . "
						ORDER BY enti_tx_nome ASC;"
		), MYSQLI_ASSOC);

		$rows = [];
		$statusEndossos = [
			"E" 	=> 0,
			"EP" 	=> 0,
			"N" 	=> 0
		];
		foreach ($motoristas as $motorista) {

			if($motorista["enti_tx_admissao"] > $dataMes->format("Y-m-d")){
				continue;
			}
			//Status Endosso{
			$endossos = mysqli_fetch_all(query(
				"SELECT * FROM endosso"
					. " WHERE endo_tx_status = 'ativo'"
					. " AND endo_nb_entidade = '" . $motorista["enti_nb_id"] . "'"
					. " AND ("
					. "   (endo_tx_de  >= '" . $dataMes->format("Y-m-d") . "' AND endo_tx_de  <= '" . $dataFim->format("Y-m-d") . "')"
					. "OR (endo_tx_ate >= '" . $dataMes->format("Y-m-d") . "' AND endo_tx_ate <= '" . $dataFim->format("Y-m-d") . "')"
					. "OR (endo_tx_de  <= '" . $dataMes->format("Y-m-d") . "' AND endo_tx_ate >= '" . $dataFim->format("Y-m-d") . "')"
					. ");"
			), MYSQLI_ASSOC);

			$statusEndosso = "N";
			if (count($endossos) >= 1) {
				$statusEndosso = "E";
				if (strtotime($dataMes->format("Y-m-d")) < strtotime($endossos[0]["endo_tx_de"]) || strtotime($dataFim->format("Y-m-d")) > strtotime($endossos[count($endossos) - 1]["endo_tx_ate"])) {
					$statusEndosso .= "P";
				}
			}
			$statusEndossos[$statusEndosso]++;
			//}

			//saldoAnterior{
			// $endossoCompleto = montarEndossoMes($dataMes, $motorista);

			// $saldoAnterior = $endossoCompleto["totalResumo"]["saldoAnterior"];
			$ultimoEndosso = mysqli_fetch_assoc(query(
				"SELECT endo_tx_filename FROM endosso"
					." WHERE endo_tx_status = 'ativo'"
						." AND endo_tx_matricula = '".$motorista["enti_tx_matricula"]."'"
						." AND endo_tx_ate < '".$dataMes->format("Y-m-d")."'"
					." ORDER BY endo_tx_ate DESC"
					." LIMIT 1;"
			));
			
			$saldoAnterior = "";
			if(!empty($ultimoEndosso) && file_exists($_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/arquivos/endosso/".$ultimoEndosso["endo_tx_filename"].".csv")){
				$ultimoEndosso = lerEndossoCSV($ultimoEndosso["endo_tx_filename"]);
				if(empty($totalResumo)){
					$totalResumo = $ultimoEndosso["totalResumo"];
				}else{
					foreach(["saldoAnterior", "saldoFinal"] as $key){
						$totalResumo[$key] = operarHorarios(
							[
								(!empty($totalResumo[$key])? $totalResumo[$key]: "00:00"),
								(!empty($ultimoEndosso["totalResumo"][$key])? $ultimoEndosso["totalResumo"][$key]: "00:00")
							], 
							"+"
						);
					}
				}
				$saldoAnterior = $ultimoEndosso["totalResumo"]["saldoFinal"];
			}elseif(!empty($motorista["enti_tx_banco"])){
				$saldoAnterior = $motorista["enti_tx_banco"];
				$saldoAnterior = $saldoAnterior[0] == "0" && strlen($saldoAnterior) > 5? substr($saldoAnterior, 1): $saldoAnterior;
			}
			//}

			$totaisMot = [
				"jornadaPrevista" 	=> "00:00",
				"jornadaEfetiva" 	=> "00:00",
				"HESemanal" 		=> "00:00",
				"HESabado" 			=> "00:00",
				"adicionalNoturno" 	=> "00:00",
				"esperaIndenizada" 	=> "00:00",
				"saldoPeriodo" 		=> "00:00",
				"saldoFinal" 		=> "00:00"
			];

			for ($dia = new DateTime($dataMes->format("Y-m-d")); $dia <= $dataFim; $dia->modify("+1 day")) {
				$diaPonto = diaDetalhePonto($motorista, $dia->format("Y-m-d"));
				//Formatando informações{
				foreach (array_keys($diaPonto) as $f) {
					if (in_array($f, ["data", "diaSemana"])) {
						continue;
					}
					if (strlen($diaPonto[$f]) > 5) {
						$diaPonto[$f] = preg_replace("/.*&nbsp;/", "", $diaPonto[$f]);
						if (is_int(preg_match_all("/(-?\d{2,4}:\d{2})/", $diaPonto[$f], $matches))) {
							$diaPonto[$f] = array_pop($matches[1]);
						} else {
							$diaPonto[$f] = "";
						}
					}
				}
				//}


				$diaPonto["he50"]              = !empty($diaPonto["he50"]) ? $diaPonto["he50"] : "00:00";
				$diaPonto["he100"]             = !empty($diaPonto["he100"]) ? $diaPonto["he100"] : "00:00";

				$totaisMot["jornadaPrevista"]  = somarHorarios([$totaisMot["jornadaPrevista"],  $diaPonto["jornadaPrevista"]]);
				$totaisMot["jornadaEfetiva"]   = somarHorarios([$totaisMot["jornadaEfetiva"],   $diaPonto["diffJornadaEfetiva"]]);
				$totaisMot["HESemanal"]        = somarHorarios([$totaisMot["HESemanal"],        $diaPonto["he50"]]);
				$totaisMot["HESabado"]         = somarHorarios([$totaisMot["HESabado"],         $diaPonto["he100"]]);
				$totaisMot["adicionalNoturno"] = somarHorarios([$totaisMot["adicionalNoturno"], $diaPonto["adicionalNoturno"]]);
				$totaisMot["esperaIndenizada"] = somarHorarios([$totaisMot["esperaIndenizada"], $diaPonto["esperaIndenizada"]]);
				$totaisMot["saldoPeriodo"]     = somarHorarios([$totaisMot["saldoPeriodo"],     $diaPonto["diffSaldo"]]);
				$totaisMot["saldoFinal"]       = somarHorarios([$saldoAnterior,                 $totaisMot["saldoPeriodo"]]);
			}

			$row = [
				"idMotorista" 		=> $motorista["enti_nb_id"],
				"matricula" 		=> $motorista["enti_tx_matricula"],
				"ocupacao" 			=> $motorista["enti_tx_ocupacao"],
				"nome" 				=> $motorista["enti_tx_nome"],
				"statusEndosso" 	=> $statusEndosso,
				"jornadaPrevista" 	=> $totaisMot["jornadaPrevista"],
				"jornadaEfetiva" 	=> $totaisMot["jornadaEfetiva"],
				"HESemanal" 		=> $totaisMot["HESemanal"],
				"HESabado" 			=> $totaisMot["HESabado"],
				"adicionalNoturno" 	=> $totaisMot["adicionalNoturno"],
				"esperaIndenizada" 	=> $totaisMot["esperaIndenizada"],

				"saldoAnterior" 	=> $saldoAnterior,
				"saldoPeriodo" 		=> $totaisMot["saldoPeriodo"],
				"saldoFinal" 		=> $totaisMot["saldoFinal"]
			];
			$nomeArquivo = $motorista["enti_tx_matricula"] . ".json";
			file_put_contents($path . "/" . $nomeArquivo, json_encode($row, JSON_UNESCAPED_UNICODE));

			$rows[] = $row;
		}


		$totaisEmpr = [
			"jornadaPrevista" => "00:00",
			"jornadaEfetiva" => "00:00",
			"HESemanal" => "00:00",
			"HESabado" => "00:00",
			"adicionalNoturno" => "00:00",
			"esperaIndenizada" => "00:00",
			"saldoAnterior" => "00:00",
			"saldoPeriodo" => "00:00",
			"saldoFinal" => "00:00"
		];

		foreach ($rows as $row) {
			$totaisEmpr["jornadaPrevista"]  = operarHorarios([$totaisEmpr["jornadaPrevista"], $row["jornadaPrevista"]], "+");
			$totaisEmpr["jornadaEfetiva"]   = operarHorarios([$totaisEmpr["jornadaEfetiva"], $row["jornadaEfetiva"]], "+");
			$totaisEmpr["HESemanal"]        = operarHorarios([$totaisEmpr["HESemanal"], $row["HESemanal"]], "+");
			$totaisEmpr["HESabado"]         = operarHorarios([$totaisEmpr["HESabado"], $row["HESabado"]], "+");
			$totaisEmpr["adicionalNoturno"] = operarHorarios([$totaisEmpr["adicionalNoturno"], $row["adicionalNoturno"]], "+");
			$totaisEmpr["esperaIndenizada"] = operarHorarios([$totaisEmpr["esperaIndenizada"], $row["esperaIndenizada"]], "+");
			$totaisEmpr["saldoAnterior"]    = operarHorarios([$totaisEmpr["saldoAnterior"], $row["saldoAnterior"]], "+");
			$totaisEmpr["saldoPeriodo"]     = operarHorarios([$totaisEmpr["saldoPeriodo"], $row["saldoPeriodo"]], "+");
			$totaisEmpr["saldoFinal"]       = operarHorarios([$totaisEmpr["saldoFinal"], $row["saldoFinal"]], "+");
		}

		//Adicionar valores da empresa à soma total das empresas{
		if (empty($_POST["empresa"])) {
			foreach ($totaisEmpr as $key => $value) {
				$totaisEmpresas[$key] = operarHorarios([$totaisEmpresas[$key], $value], "+");
			}
			$totaisEmpresas["qtdMotoristas"] += count($motoristas);
		}
		//}

		$empresa["totais"] = $totaisEmpr;
		$empresa["qtdMotoristas"] = count($motoristas);
		$empresa["dataInicio"] = $dataMes->format("Y-m-d");
		$empresa["dataFim"] = $dataFim->format("Y-m-d");
		if (array_sum(array_values($statusEndossos)) != 0) {
			$empresa["percEndossado"] = ($statusEndossos["E"]) / array_sum(array_values($statusEndossos));
		} else {
			$empresa["percEndossado"] = 0;
		}

		file_put_contents($path . "/empresa_" . $empresa["empr_nb_id"] . ".json", json_encode($empresa));
	}

	if (empty($_POST["empresa"])) {
		$path = "./arquivos/saldos" . "/" . $dataMes->format("Y-m");
		$totaisEmpresas["dataInicio"] = $dataMes->format("Y-m-d");
		$totaisEmpresas["dataFim"] = $dataFim->format("Y-m-d");
		file_put_contents($path . "/empresas.json", json_encode($totaisEmpresas));
	}
	return;
}

function criar_relatorio_endosso() {
	$mes = new DateTime($_POST["busca_data"] . "-01");
	$fimMes = new DateTime($mes->format("Y-m-t"));

	$empresas = mysqli_fetch_all(query(
		"SELECT empr_nb_id, empr_tx_nome FROM empresa"
			. " WHERE empr_tx_status = 'ativo'"
			. (!empty($_POST["empresa"]) ? " AND empr_nb_id = " . $_POST["empresa"] : "")
			. " ORDER BY empr_tx_nome ASC;"
	), MYSQLI_ASSOC);

	$totaisEmpresas = [
		"jornadaPrevista" => "00:00",
		"jornadaEfetiva" => "00:00",
		"he50APagar" => "00:00",
		"he100APagar" => "00:00",
		"adicionalNoturno" => "00:00",
		"esperaIndenizada" => "00:00",
		"saldoAnterior" => "00:00",
		"saldoPeriodo" => "00:00",
		"saldoFinal" => "00:00",
		"qtdMotoristas" => 0
	];

	foreach ($empresas as $empresa) {
		$path = "./arquivos/endossos" . "/" . $mes->format("Y-m") . "/" . $empresa["empr_nb_id"];
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}

		if (is_dir($path)) {
			$pasta = dir($path);
			while (($arquivo = $pasta->read()) !== false) {
				// Ignora os diretórios especiais '.' e '..'
				if ($arquivo != '.' && $arquivo != '..') {
					$arquivoPath = $path . '/' . $arquivo;  // Caminho completo do 
					unlink($arquivoPath);  // Apaga o arquivo
				}
			}
			$pasta->close();
		}

		$filtroOcupacao = " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')";

		$motoristas = mysqli_fetch_all(query(
			"SELECT entidade.*, parametro.para_tx_pagarHEExComPerNeg, parametro.para_tx_inicioAcordo, parametro.para_nb_qDias, parametro.para_nb_qDias FROM entidade"
				. " LEFT JOIN parametro ON enti_nb_parametro = para_nb_id"
				. " WHERE enti_tx_status = 'ativo'"
				// . " AND DATE_FORMAT(enti_tx_dataCadastro, '%Y-%m') <= '{$mes->format("Y-m")}'"
				. " AND enti_nb_empresa = " . $empresa["empr_nb_id"]
				. " " . $filtroOcupacao
				. " ORDER BY enti_tx_nome ASC;"
		), MYSQLI_ASSOC);

		$rows = [];
		$statusEndossos = [
			"E" => 0,
			"EP" => 0,
			"N" => 0
		];
		foreach ($motoristas as $motorista) {
			if (substr($motorista["enti_tx_admissao"], 0, 7) <= $mes->format("Y-m")) {
				$endossos = mysqli_fetch_all(query(
					"SELECT * FROM endosso"
						." WHERE endo_tx_status = 'ativo'"
						." AND endo_nb_entidade = '{$motorista["enti_nb_id"]}'"
						." AND ("
						."   (endo_tx_de  >= '".$mes->format("Y-m-01")."' AND endo_tx_de  <= '".$mes->format("Y-m-t")."')"
						."OR (endo_tx_ate >= '".$mes->format("Y-m-01")."' AND endo_tx_ate <= '".$mes->format("Y-m-t")."')"
						."OR (endo_tx_de  <= '".$mes->format("Y-m-01")."' AND endo_tx_ate >= '".$mes->format("Y-m-t")."')"
						.")"
						." ORDER BY endo_tx_ate;"
				), MYSQLI_ASSOC);

				$statusEndosso = "N";
				if (count($endossos) >= 1) {
					$statusEndosso = "E";
					if (strtotime($mes->format("Y-m-01")) != strtotime($endossos[0]["endo_tx_de"]) || strtotime($mes->format("Y-m-t")) > strtotime($endossos[count($endossos) - 1]["endo_tx_ate"])) {
						$statusEndosso .= "P";
					}
				}
				$statusEndossos[$statusEndosso]++;
				//}

					//saldoAnterior{
					$endossoCompleto = montarEndossoMes($mes, $motorista);
					$saldoAnterior = $endossoCompleto["totalResumo"]["saldoAnterior"] ?? "00:00";
					//}

					$totaisMot = [
						"jornadaPrevista" => "",
						"jornadaEfetiva" => "",
						"he50APagar" => "",
						"he100APagar" => "",
						"adicionalNoturno" => "",
						"esperaIndenizada" => "",
						"saldoAnterior" => $saldoAnterior,
						"saldoPeriodo" => "",
						"saldoFinal" => ""
					];
					if ($statusEndosso != "N") {

						$totaisMot["jornadaPrevista"]     = $endossoCompleto["totalResumo"]["jornadaPrevista"]     ?? "00:00";
						$totaisMot["jornadaEfetiva"]      = $endossoCompleto["totalResumo"]["diffJornadaEfetiva"]   ?? "00:00";
						$totaisMot["he50APagar"]          = $endossoCompleto["totalResumo"]["he50APagar"]           ?? "00:00";
						$totaisMot["he100APagar"]         = $endossoCompleto["totalResumo"]["he100APagar"]          ?? "00:00";
						$totaisMot["adicionalNoturno"]    = $endossoCompleto["totalResumo"]["adicionalNoturno"]     ?? "00:00";
						$totaisMot["esperaIndenizada"]    = $endossoCompleto["totalResumo"]["esperaIndenizada"]     ?? "00:00";
						$totaisMot["saldoPeriodo"]        = $endossoCompleto["totalResumo"]["diffSaldo"]            ?? "00:00";
						$totaisMot["saldoFinal"]          = $endossoCompleto["totalResumo"]["saldoFinal"]           ?? "00:00";
						if(empty($endossoCompleto["endo_tx_max50APagar"])){
							$endossoCompleto["endo_tx_max50APagar"] = "00:00";
						}

						$aPagar2 = calcularHorasAPagar($endossoCompleto["totalResumo"]["saldoBruto"] ?? "00:00", $endossoCompleto["totalResumo"]["he50"] ?? "00:00", 
						$endossoCompleto["totalResumo"]["he100"]?? "00:00", $endossoCompleto["endo_tx_max50APagar"]?? "00:00", 
						($motorista["para_tx_pagarHEExComPerNeg"]?? "nao"));
						$aPagar2 = operarHorarios($aPagar2, "+");
						$totaisMot["saldoFinal"]        = operarHorarios([$endossoCompleto["totalResumo"]["saldoBruto"], $aPagar2], "-");
					}

				$row = [
					"idMotorista" => $motorista["enti_nb_id"],
					"matricula" => $motorista["enti_tx_matricula"],
					"nome" => $motorista["enti_tx_nome"],
					"ocupacao" => $motorista["enti_tx_ocupacao"],
					"statusEndosso" => $statusEndosso,
					"jornadaPrevista" => $totaisMot["jornadaPrevista"],
					"jornadaEfetiva" => $totaisMot["jornadaEfetiva"],
					"he50APagar" => $totaisMot["he50APagar"],
					"he100APagar" => $totaisMot["he100APagar"],
					"adicionalNoturno" => $totaisMot["adicionalNoturno"],
					"esperaIndenizada" => $totaisMot["esperaIndenizada"],
					"saldoAnterior" => $saldoAnterior,
					"saldoPeriodo" => $totaisMot["saldoPeriodo"],
					"saldoFinal" => $totaisMot["saldoFinal"]
				];

				// dd($row);
				$nomeArquivo = $motorista["enti_tx_matricula"] . ".json";
				file_put_contents($path . "/" . $nomeArquivo, json_encode($row, JSON_UNESCAPED_UNICODE));

				$rows[] = $row;
			}
		}


		$totaisEmpr = [
			"jornadaPrevista" => "00:00",
			"jornadaEfetiva" => "00:00",
			"he50APagar" => "00:00",
			"he100APagar" => "00:00",
			"adicionalNoturno" => "00:00",
			"esperaIndenizada" => "00:00",
			"saldoAnterior" => "00:00",
			"saldoPeriodo" => "00:00",
			"saldoFinal" => "00:00"
		];
		
		foreach ($rows as $row) {
			$totaisEmpr["jornadaPrevista"]  = operarHorarios([$totaisEmpr["jornadaPrevista"], $row["jornadaPrevista"]], "+");
			$totaisEmpr["jornadaEfetiva"]   = operarHorarios([$totaisEmpr["jornadaEfetiva"], $row["jornadaEfetiva"]], "+");
			$totaisEmpr["he50APagar"]       = operarHorarios([$totaisEmpr["he50APagar"], $row["he50APagar"]], "+");
			$totaisEmpr["he100APagar"]      = operarHorarios([$totaisEmpr["he100APagar"], $row["he100APagar"]], "+");
			$totaisEmpr["adicionalNoturno"] = operarHorarios([$totaisEmpr["adicionalNoturno"], $row["adicionalNoturno"]], "+");
			$totaisEmpr["esperaIndenizada"] = operarHorarios([$totaisEmpr["esperaIndenizada"], $row["esperaIndenizada"]], "+");
			$totaisEmpr["saldoAnterior"]    = operarHorarios([$totaisEmpr["saldoAnterior"], $row["saldoAnterior"]], "+");
			$totaisEmpr["saldoPeriodo"]     = operarHorarios([$totaisEmpr["saldoPeriodo"], $row["saldoPeriodo"]], "+");
			$totaisEmpr["saldoFinal"]       = operarHorarios([$totaisEmpr["saldoFinal"], $row["saldoFinal"]], "+");
		}

		//Adicionar valores da empresa à soma total das empresas{
		if (empty($_POST["empresa"])) {
			foreach ($totaisEmpr as $key => $value) {
				$totaisEmpresas[$key] = operarHorarios([$totaisEmpresas[$key], $value], "+");
			}
			$totaisEmpresas["qtdMotoristas"] += count($motoristas);
		}
		//}

			$empresa["totais"] = $totaisEmpr;
			$empresa["qtdMotoristas"] = count($motoristas);
			$empresa["dataInicio"] = $mes->format("01/m/Y");
			$empresa["dataFim"] = $mes->format("t/m/Y");
			$empresa["percEndossado"] = ($statusEndossos["E"]) / array_sum(array_values($statusEndossos));


		file_put_contents($path . "/empresa_" . $empresa["empr_nb_id"] . ".json", json_encode($empresa));
	}

	if (empty($_POST["empresa"])) {
		$path = "./arquivos/endossos" . "/" . $mes->format("Y-m");
		$totaisEmpresas["dataInicio"] = $mes->format("01/m/Y");
		$totaisEmpresas["dataFim"] = $mes->format("t/m/Y");
		file_put_contents($path . "/empresas.json", json_encode($totaisEmpresas));
	}

	return;
}

function criar_relatorio_jornada() {

	$dataAtual = new DateTime();

	$campos = ["fimJornada", "inicioRefeicao", "fimRefeicao"];

	$path = "./arquivos/jornada" . "/" . $_POST["empresa"];
	if (!is_dir($path)) {
		mkdir($path, 0755, true);
	}

	$filtroOcupacao = "";
	if (!empty($_POST["busca_ocupacao"])) {
		$filtroOcupacao = "AND enti_tx_ocupacao IN ('{$_POST["busca_ocupacao"]}')";
	}

	$motoristas = mysqli_fetch_all(query(
		"SELECT * FROM entidade
					WHERE enti_tx_status = 'ativo'
						AND enti_nb_empresa = {$_POST["empresa"]}
						{$filtroOcupacao}
					ORDER BY enti_tx_nome ASC;"
	), MYSQLI_ASSOC);

	$pasta = dir($path);
	while (($arquivo = $pasta->read()) !== false) {
		// Ignora os diretórios especiais '.' e '..'
		if ($arquivo != '.' && $arquivo != '..') {
			$arquivoPath = $path . '/' . $arquivo;  // Caminho completo do arquivo
			unlink($arquivoPath);  // Apaga o arquivo
		}
	}
	$pasta->close();

	foreach ($motoristas as $motorista) {
		$row = [];
		$arrayDias = [];
		$datasPontosAbertos = mysqli_fetch_all(query(
			" SELECT p.pont_tx_data
					FROM ponto p"
				. " WHERE p.pont_tx_matricula = '{$motorista["enti_tx_matricula"]}'"
				. " AND p.pont_tx_status = 'ativo'"
				. " AND p.pont_tx_tipo = 1"
				. " ORDER BY p.pont_tx_data DESC -- Ordena pela data em ordem decrescente
					LIMIT 1;"
		), MYSQLI_ASSOC);

		foreach ($datasPontosAbertos as $datas) {
			$endossos = mysqli_fetch_all(query(
				"SELECT endo_tx_de, endo_tx_ate"
					. " FROM `endosso`"
					. " where endo_tx_matricula = '{$motorista["enti_tx_matricula"]}'"
					. " AND '{$datas["pont_tx_data"]}' BETWEEN endo_tx_de AND endo_tx_ate"
			), MYSQLI_ASSOC);
			if (empty($endossos)) {
				$date = DateTime::createFromFormat('Y-m-d H:i:s', $datas["pont_tx_data"]);
				$arrayDias[] = $date->format('Y-m-d');
			}
		}

		foreach ($arrayDias as $dia) {
			$dia = diaDetalhePonto($motorista, $dia);

			$descanso = "";
			$espera = "";
			$jornada = "";
			$jornadaEfetiva = "";
			$refeicao = "";
			$repouso = "";

			$dataItem = DateTime::createFromFormat('d/m/Y', $dia["data"]);
			$diferenca = $dataAtual->diff($dataItem);
			$diaDiferenca = $diferenca->days;

			if (strpos($dia["fimJornada"], "fa fa-warning") !== false) {
				$fimJornada = true;
			} else {
				$horaInicio = preg_replace('/<strong>.*?<\/strong>/', '', $dia["inicioJornada"]);
				$horaFim = preg_replace('/<strong>.*?<\/strong>/', '', $dia["fimJornada"]);
				$horaRemoverExtraI = preg_replace('/[^0-9:]/', ' ', $horaInicio);
				$horaRemoverExtraF = preg_replace('/\s*D\+\d+/', '', $horaFim);
				$horaRemoverHtmlF = str_replace(['<br>', '<br/>', '<br />'], ' ', $horaRemoverExtraF);
				$inicio = explode(' ', $horaRemoverExtraI);
				$fim = explode(' ', $horaRemoverHtmlF);
				$filtraInicio = array_filter($inicio);
				$filtraFim = array_filter($fim);
				if (sizeof($filtraInicio) == sizeof($filtraFim) || sizeof($filtraInicio) < sizeof($filtraFim)) {
					$fimJornada = false;
				} else {
					$fimJornada = true;
				}
			}

			if (strpos($dia["inicioJornada"], "fa fa-warning") === false && $fimJornada) {
				// Verificação da refeição
				$inicioRefeicaoWarning = is_int(strpos($dia["inicioRefeicao"], "fa-warning"));
				$fimRefeicaoWarning = is_int(strpos($dia["fimRefeicao"], "fa-warning"));
				$diffRefeicaoInfo = is_int(strpos($dia["diffRefeicao"], "fa-info-circle")) && is_int(strpos($dia["diffRefeicao"], "color:red;"));
				$diffRefeicaoWarning = is_int(strpos($dia["diffRefeicao"], "fa-warning"));

				if ($inicioRefeicaoWarning && $fimRefeicaoWarning) {
					$refeicao = "";
				} elseif ($inicioRefeicaoWarning || $fimRefeicaoWarning || (!empty($dia["inicioRefeicao"]) && empty($dia["fimRefeicao"]))) {
					$refeicao = "*";
				} elseif ($diffRefeicaoInfo && !$diffRefeicaoWarning) {
					$refeicao = "*";
				} elseif ($dia["diffRefeicao"] != "00:00") {
					$refeicao = $dia["diffRefeicao"];
				} else {
					$refeicao = "";
				}

				// Verificação do descanso
				$descanso = (trim($dia["diffDescanso"]) == "00:00") ? "" : (
					is_int(strpos($dia["diffDescanso"], "fa-info-circle"))
					&& is_int(strpos($dia["diffDescanso"], "color:red;"))
					&& !is_int(strpos($dia["diffDescanso"], "fa fa-warning")) ? "*" : $dia["diffDescanso"]
				);

				// Verificação da espera
				$espera = ($dia["diffEspera"] == "00:00") ? "" : (
					is_int(strpos($dia["diffEspera"], "fa-info-circle"))
					&& is_int(strpos($dia["diffEspera"], "color:red;"))
					&& !is_int(strpos($dia["diffEspera"], "fa fa-warning")) ? "*" : $dia["diffEspera"]
				);

				// Verificação do repouso
				$repouso = ($dia["diffRepouso"] != "00:00") ? (
					is_int(strpos($dia["diffRepouso"], "fa-info-circle"))
					&& is_int(strpos($dia["diffRepouso"], "color:red;"))
					&& !is_int(strpos($dia["diffRepouso"], "fa fa-warning")) ? "*" : $dia["diffRepouso"]
				) : "";

				// Verificação da jornada
				if ((is_int(strpos($dia["diffJornada"], "fa-info-circle"))
						&& is_int(strpos($dia["diffJornada"], "color:red;")))
					|| $fimJornada
				) {
					if (strlen($dia["inicioJornada"]) == 5) {
						$hora = preg_replace('/<strong>.*?<\/strong>/', '', $dia["inicioJornada"]);
						$hora = preg_replace('/[^0-9:]/', '', $hora);
						$hora = trim($hora);
						$horaEspecifica = new DateTime($hora);
						$horaAtual = new DateTime();
						$jornada = $horaAtual->diff($horaEspecifica)->format('%H:%I');
					} else {
						$horaInicio = preg_replace('/<strong>.*?<\/strong>/', '', $dia["inicioJornada"]);
						$horaRemoverExtraI = preg_replace('/[^0-9:]/', ' ', $horaInicio);
						$inicio = explode(' ', $horaRemoverExtraI);
						$horaInicio = array_filter($inicio);
						$horas = trim(end($horaInicio));
						$horaEspecifica = new DateTime($horas);
						$horaAtual = new DateTime();
						$jornada = $horaAtual->diff($horaEspecifica)->format('%H:%I');
					}
				} else {
					$jornada = $dia["diffJornada"];
				}

				// $jornadaEfetiva = $dia["diffJornadaEfetiva"] == "00:00" ? "----" : $dia["diffJornadaEfetiva"];
			}


			$campos = !empty(array_filter([$jornada, $descanso, $espera, $refeicao, $repouso]));
			if ($campos) {
				$parametro = mysqli_fetch_all(query(
					"SELECT para_tx_jornadaSemanal, para_tx_jornadaSabado, para_tx_maxHESemanalDiario, para_tx_adi5322"
						. " FROM `parametro`"
						. " WHERE para_nb_id = " . $motorista["enti_nb_parametro"]
				), MYSQLI_ASSOC);

				if (date('l', strtotime($dia["data"])) == "Saturday") {
					$jornadaDia = $parametro[0]["para_tx_jornadaSabado"];
				} else {
					$jornadaDia = $parametro[0]["para_tx_jornadaSemanal"];
				}

				$horaLimpa = preg_replace('/<strong>.*?<\/strong>/', '',  $dia["inicioJornada"]);
				$horaLimpa = preg_replace('/[^0-9:]/', ' ', $horaLimpa);
				$horaLimpa = trim($horaLimpa);
				$row[] = [
					"data" => $dia["data"],
					"jornadaDia" => $jornadaDia,
					"limiteExtras" => $parametro[0]["para_tx_maxHESemanalDiario"] == 0 ? '00:00' : $parametro[0]["para_tx_maxHESemanalDiario"],
					"adi5322" => $parametro[0]["para_tx_adi5322"],
					"inicioJornada" => $horaLimpa,
					"diaDiferenca" => $diaDiferenca,
					"matricula" => $motorista["enti_tx_matricula"],
					"nome" => $motorista["enti_tx_nome"],
					"ocupacao" => $motorista["enti_tx_ocupacao"],
					"jornada" => strip_tags($jornada),
					"jornadaEfetiva" => strip_tags($jornadaEfetiva),
					"refeicao" => strip_tags($refeicao),
					"espera" => strip_tags($espera),
					"descanso" => strip_tags($descanso),
					"repouso" => strip_tags($repouso)
				];
			}
		}

		if (!empty($row)) {
			$nomeArquivo = $motorista["enti_tx_matricula"] . ".json";
			$arquivosMantidos[] = $nomeArquivo;
			file_put_contents($path . "/" . $nomeArquivo, json_encode($row, JSON_UNESCAPED_UNICODE));
		}

		$pasta = dir($path);
		if ($arquivosMantidos != null) {
			while ($arquivo = $pasta->read()) {
				if (!in_array($arquivo, $arquivosMantidos)) {
					unlink($arquivo); // Apaga o arquivo
				}
			}
			$pasta->close();
		}
	}
	// sleep(1);
	return;
}

function relatorio_nao_conformidade_juridica() {

	$periodoInicio = new DateTime($_POST["busca_dataMes"] . "-01");
	$periodoInicio2 = new DateTime($_POST["busca_dataMes"] . "-01");
	$hoje = new DateTime();

	if ($periodoInicio->format('Y-m') === $hoje->format('Y-m')) {
		$hoje->modify('-1 day');
		// Se for o mês atual, a data limite é o dia de hoje
		$periodoFim = $hoje;
		$periodoFim2 = $hoje;
	} else {
		$periodoFim = new DateTime($periodoInicio->format("Y-m-t"));
		$periodoFim2 = new DateTime($periodoInicio->format("Y-m-t"));
	}

	$path = "./arquivos/nao_conformidade_juridica" . "/" . $periodoInicio->format("Y-m") . "/" . $_POST["empresa"];
	if (!is_dir($path)) {
		mkdir($path, 0755, true);
	}

	if (!empty($_POST["busca_ocupacao"])) {
		$filtroOcupacao = "AND enti_tx_ocupacao IN ('{$_POST["busca_ocupacao"]}')";
	}

	$motoristas = mysqli_fetch_all(query(
		"SELECT * FROM entidade
				LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
				LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
				LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
				WHERE enti_tx_status = 'ativo'
					-- AND enti_nb_id = 248
					AND enti_nb_empresa = {$_POST["empresa"]}
					AND enti_tx_dataCadastro <= '{$periodoInicio->format("Y-m-t")}'
				ORDER BY enti_tx_nome ASC;"
	), MYSQLI_ASSOC);

	$row = [];

	if ($_POST["busca_endossado"] == "endossado") {
		$dir = '/endossado';
	} else {
		$dir = '/nao_endossado';
	}

	if (is_dir($path . $dir)) {
		$pasta = dir($path . $dir);
		while (($arquivo = $pasta->read()) !== false) {
			// Ignora os diretórios especiais '.' e '..'
			if ($arquivo != '.' && $arquivo != '..') {
				$arquivoPath = $path . '/' . $dir . '/' . $arquivo;  // Caminho completo do 
				unlink($arquivoPath);  // Apaga o arquivo
			}
		}
		$pasta->close();
	}

	foreach ($motoristas as $motorista) {

		$totalMotorista = [
			"matricula" 				=> $motorista["enti_tx_matricula"],
			"nome" 						=> $motorista["enti_tx_nome"],
			"ocupacao" 					=> $motorista["enti_tx_ocupacao"],
			"jornadaPrevista" 			=> 0,
			"jornadaEfetiva" 			=> 0,
			"refeicao" 					=> 0,
			"espera" 					=> 0,
			"descanso" 					=> 0,
			"repouso" 					=> 0,
			"jornada" 					=> 0,
			"mdc"		 				=> 0,
			"intersticioInferior" 		=> 0,
			"intersticioSuperior" 		=> 0,

			"refeicaoSemRegistro" 		=> 0,
			"refeicao1h" 				=> 0,
			"refeicao2h" 				=> 0,
			"jornadaExcedido10h" 		=> 0,
			"jornadaExcedido12h" 		=> 0,
			"mdcDescanso30m" 			=> 0,
			"mdcDescanso15m" 			=> 0,
			"mdcDescanso30m5h" 			=> 0,
			"faltaJustificada"          => 0,
			"falta"                     => 0,
			"diasConformidade"          => 0,

			"dataInicio"				=> $periodoInicio2->format("d/m/Y"),
			"dataFim"					=> $periodoFim2->format("d/m/Y")
		];
		
		if ($_POST["busca_endossado"] == "endossado") {
			$houveInteracao = false;
			$endossoCompleto = montarEndossoMes($periodoInicio, $motorista);
			if(!empty($endossoCompleto["endo_tx_pontos"]))  {
				foreach ($endossoCompleto["endo_tx_pontos"] as $ponto) {
					$inicioJornadaWarning = strpos($ponto["3"], "fa-warning") !== false && strpos($ponto["3"], "color:red;")
						&& strpos($ponto["12"], "fa-info-circle") ===  false && strpos($ponto["12"], "color:green;") ===  false;
					$fimJornadaWarning = strpos($ponto["6"], "fa-warning") !== false  && strpos($ponto["6"], "color:red;")
						&& strpos($ponto["12"], "fa-info-circle") ===  false && strpos($ponto["12"], "color:green;") ===  false;
					$diffJornada = $ponto["11"];
					$diffJornadaEfetiva = $ponto["13"];
	
					// Verificações jornada
					if ($inicioJornadaWarning || $fimJornadaWarning) {
						$totalMotorista["12"] += 1;
						$houveInteracao = true;
					}
	
					if (
						$inicioJornadaWarning && strpos($ponto["12"], "fa-info-circle") !== false &&
						strpos($ponto["12"], "color:green;") !== false
					) {
						$totalMotorista["faltaJustificada"] += 1;
						$houveInteracao = true;
					}
	
					if ($inicioJornadaWarning) {
						$totalMotorista["falta"] += 1;
						$houveInteracao = true;
					}
	
					if (strpos($diffJornada, "fa-info-circle") !== false && strpos($diffJornada, "color:red;") !== false) {
						$totalMotorista["jornada"] += 1;
						$houveInteracao = true;
					}
					if (strpos($diffJornadaEfetiva, "fa-warning") !== false && strpos($diffJornadaEfetiva, "color:orange;") !== false) {
						$totalMotorista["jornadaEfetiva"] += 1;
						$houveInteracao = true;
					}
					if (strpos($diffJornadaEfetiva, "Tempo excedido de 10:00") !== false) {
						$totalMotorista["jornadaExcedido10h"] += 1;
						$houveInteracao = true;
					}
					if (strpos($diffJornadaEfetiva, "Tempo excedido de 12:00") !== false) {
						$totalMotorista["jornadaExcedido12h"] += 1;
						$houveInteracao = true;
					}
	
					// Refeição
					$inicioRefeicao = strpos($ponto["4"], "fa-warning") !== false;
					$fimRefeicao = strpos($ponto["5"], "fa-warning") !== false;
					$diffRefeicao = $ponto["7"];
	
					if ($inicioRefeicao || $fimRefeicao) {
						$totalMotorista["refeicao"]++;
						$houveInteracao = true;
					} else if (strpos($diffRefeicao, "fa-warning") !== false) {
						$totalMotorista["refeicao"]++;
						$houveInteracao = true;
					}
					if (strpos($diffRefeicao, "fa-info-circle") !== false && strpos($diffRefeicao, "color:orange;") !== false) {
						$totalMotorista["refeicao"]++;
						$houveInteracao = true;
					}
					if ($inicioRefeicao || $fimRefeicao) {
						$totalMotorista["refeicaoSemRegistro"] += 1;
						$houveInteracao = true;
					}
					if ($inicioRefeicao == false && $fimRefeicao == false && strpos($diffRefeicao, "01:00h") !== false) {
						$totalMotorista["refeicao1h"] += 1;
						$houveInteracao = true;
					}
					if (strpos($diffRefeicao, "02:00h") !== false) {
						$totalMotorista["refeicao2h"] += 1;
						$houveInteracao = true;
					}
	
					// Máximo Direção Contínua
					$maximoDirecaoContinua = $ponto["14"];
					if (strpos($maximoDirecaoContinua, "fa-warning") !== false && strpos($maximoDirecaoContinua, "color:orange;") !== false) {
						$totalMotorista["mdc"]++;
						$houveInteracao = true;
					}
					if (strpos($maximoDirecaoContinua, "digiridos não respeitado") !== false) {
						$totalMotorista["mdcDescanso30m5h"] += 1;
						$houveInteracao = true;
					}
					if (strpos($maximoDirecaoContinua, "00:15 não respeitado") !== false) {
						$totalMotorista["mdcDescanso15m"] += 1;
						$houveInteracao = true;
					}
					if (strpos($maximoDirecaoContinua, "00:30 não respeitado") !== false) {
						$totalMotorista["mdcDescanso30m"] += 1;
						$houveInteracao = true;
					}
	
					// Outros campos de descanso
					foreach (["8", "9", "10"] as $campo) {
						$diffCampo = $ponto["diff" . $campo];
						if (strpos($diffCampo, "fa-info-circle") !== false && strpos($diffCampo, "color:red;") !== false) {
							$totalMotorista[strtolower($campo)]++;
							$houveInteracao = true;
						}
					}
	
					// Interstício
					if (strpos($ponto["15"], "faltaram") !== false) {
						$totalMotorista["intersticioSuperior"]++;
						$houveInteracao = true;
					}
					if (strpos($ponto["15"], "ininterruptas") !== false) {
						$totalMotorista["intersticioInferior"]++;
						$houveInteracao = true;
					}
	
					if ($houveInteracao) {
						$totalMotorista["diasConformidade"]++;
					}
				}
				$motoristaTotais[] = $totalMotorista;
	
				if (!is_dir($path . "/endossado/")) {
					mkdir($path . "/endossado/", 0755, true);  // Cria o diretório com permissões adequadas
				}
	
				file_put_contents($path . "/endossado/" . $motorista["enti_tx_matricula"] . ".json", json_encode($totalMotorista, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
			}
		} else {
			$diaPonto = [];

			for ($date = $periodoInicio; $date <= $periodoFim; $date->modify('+1 day')) {
				$diaPonto[] = diaDetalhePonto($motorista, $date->format("Y-m-d"));
			}

			$periodoInicio = new DateTime($_POST["busca_dataMes"] . "-01");

			// dd($motorista["enti_tx_nome"], false);
			// dd($diaPonto, false);

			if (!is_dir($path . "/nao_endossado/")) {
				mkdir($path . "/nao_endossado/", 0755, true);  // Cria o diretório com permissões adequadas
			}
			
			foreach ($diaPonto as $dia) {
				$houveInteracao = false;
				// Jornada
				$inicioJornadaWarning = strpos($dia["inicioJornada"], "fa-warning") !== false && strpos($dia["inicioJornada"], "color:red;") !== false
					&& strpos($dia["jornadaPrevista"], "fa-info-circle") ===  false && strpos($dia["jornadaPrevista"], "color:green;") ===  false;

				$fimJornadaWarning = strpos($dia["fimJornada"], "fa-warning") == false  && strpos($dia["fimJornada"], "color:red;") !== false
					&& strpos($dia["jornadaPrevista"], "fa-info-circle") ===  false && strpos($dia["jornadaPrevista"], "color:green;") ===  false;

				$diffJornada = $dia["diffJornada"];
				$diffJornadaEfetiva = $dia["diffJornadaEfetiva"];

				// Verificações jornada
				if ($inicioJornadaWarning || $fimJornadaWarning) {
					$totalMotorista["jornadaPrevista"] += 1;
					$houveInteracao = true;
				}

				// dd(strpos($dia["fimJornada"], "fa-warning") !== false, false);
				// dd(strpos($dia["fimJornada"], "color:red;") !== false, false);

				if (strpos($dia["fimJornada"], "fa-warning") !== false  && strpos($dia["fimJornada"], "color:red;") !== false) {
					$totalMotorista["jornada"] += 1;
					$houveInteracao = true;
				}

				if (
					$inicioJornadaWarning && strpos($dia["jornadaPrevista"], "fa-info-circle") !== false &&
					strpos($dia["jornadaPrevista"], "color:green;") !== false
				) {
					$totalMotorista["faltaJustificada"] += 1;
					$houveInteracao = true;
				}

				if ($inicioJornadaWarning) {
					$totalMotorista["falta"] += 1;
					$houveInteracao = true;
				}

				if (strpos($diffJornada, "fa-info-circle") !== false && strpos($diffJornada, "color:red;") !== false) {
					$totalMotorista["jornada"] += 1;
					$houveInteracao = true;
				}
				if (strpos($diffJornadaEfetiva, "fa-warning") !== false && strpos($diffJornadaEfetiva, "color:orange;") !== false) {
					$totalMotorista["jornadaEfetiva"] += 1;
					$houveInteracao = true;
				}
				if (strpos($diffJornadaEfetiva, "Tempo excedido de 10:00") !== false) {
					$totalMotorista["jornadaExcedido10h"] += 1;
					$houveInteracao = true;
				}
				if (strpos($diffJornadaEfetiva, "Tempo excedido de 12:00") !== false) {
					$totalMotorista["jornadaExcedido12h"] += 1;
					$houveInteracao = true;
				}

				// Refeição
				$inicioRefeicao = strpos($dia["inicioRefeicao"], "fa-warning") !== false;
				$fimRefeicao = strpos($dia["fimRefeicao"], "fa-warning") !== false;
				$diffRefeicao = $dia["diffRefeicao"];

				if ($inicioRefeicao || $fimRefeicao) {
					$totalMotorista["refeicao"]++;
					$houveInteracao = true;
				} else if (strpos($diffRefeicao, "fa-warning") !== false) {
					$totalMotorista["refeicao"]++;
					$houveInteracao = true;
				}
				if (strpos($diffRefeicao, "fa-info-circle") !== false && strpos($diffRefeicao, "color:orange;") !== false) {
					$totalMotorista["refeicao"]++;
					$houveInteracao = true;
				}
				if (strpos($diffRefeicao, "fa-info-circle") !== false && strpos($diffRefeicao, "color:red;") !== false) {
					$totalMotorista["refeicao"]++;
					$houveInteracao = true;
				}
				if ($inicioRefeicao || $fimRefeicao) {
					$totalMotorista["refeicaoSemRegistro"] += 1;
					$houveInteracao = true;
				}
				if ($inicioRefeicao == false && $fimRefeicao == false && strpos($diffRefeicao, "01:00h") !== false) {
					$totalMotorista["refeicao1h"] += 1;
					$houveInteracao = true;
				}
				if (strpos($diffRefeicao, "02:00h") !== false) {
					$totalMotorista["refeicao2h"] += 1;
					$houveInteracao = true;
				}

				// Máximo Direção Contínua
				$maximoDirecaoContinua = $dia["maximoDirecaoContinua"];
				if (strpos($maximoDirecaoContinua, "fa-warning") !== false && strpos($maximoDirecaoContinua, "color:orange;") !== false) {
					$totalMotorista["mdc"]++;
					$houveInteracao = true;
				}
				if (strpos($maximoDirecaoContinua, "digiridos não respeitado") !== false) {
					$totalMotorista["mdcDescanso30m5h"] += 1;
					$houveInteracao = true;
				}
				if (strpos($maximoDirecaoContinua, "00:15 não respeitado") !== false) {
					$totalMotorista["mdcDescanso15m"] += 1;
					$houveInteracao = true;
				}
				if (strpos($maximoDirecaoContinua, "00:30 não respeitado") !== false) {
					$totalMotorista["mdcDescanso30m"] += 1;
					$houveInteracao = true;
				}

				// Outros campos de descanso
				foreach (["Espera", "Descanso", "Repouso"] as $campo) {
					$diffCampo = $dia["diff" . $campo];
					if (strpos($diffCampo, "fa-info-circle") !== false && strpos($diffCampo, "color:red;") !== false) {
						$totalMotorista[strtolower($campo)]++;
						$houveInteracao = true;
					}
				}

				// Interstício
				if (strpos($dia["intersticio"], "faltaram") !== false) {
					$totalMotorista["intersticioSuperior"]++;
					$houveInteracao = true;
				}
				if (strpos($dia["intersticio"], "ininterruptas") !== false) {
					$totalMotorista["intersticioInferior"]++;
					$houveInteracao = true;
				}

				if ($houveInteracao) {
					$totalMotorista["diasConformidade"]++;
				}
			}

			$motoristaTotais[] = $totalMotorista;

			file_put_contents($path . "/nao_endossado/" . $motorista["enti_tx_matricula"] . ".json", json_encode($totalMotorista, JSON_UNESCAPED_UNICODE));
		}
	}

	$totaisEmpr = [
		"falta" 			        => 0,
		"jornadaEfetiva" 			=> 0,
		"refeicao" 					=> 0,
		"espera" 					=> 0,
		"descanso" 					=> 0,
		"repouso" 					=> 0,
		"jornada" 					=> 0,
		"mdc"		 				=> 0,
		"intersticioInferior" 		=> 0,
		"intersticioSuperior" 		=> 0,

		"refeicaoSemRegistro" 		=> 0,
		"refeicao1h" 				=> 0,
		"refeicao2h" 				=> 0,
		"jornadaExcedido10h" 		=> 0,
		"jornadaExcedido12h" 		=> 0,
		"mdcDescanso30m" 			=> 0,
		"mdcDescanso15m" 			=> 0,
		"mdcDescanso30m5h" 			=> 0,
	];

	foreach ($motoristaTotais as $motorista) {
		foreach ($totaisEmpr as $key => $value) {
			if (isset($motorista[$key]) && is_numeric($motorista[$key])) {
				$totaisEmpr[$key] += $motorista[$key];
			}
		}
	}

	if ($_POST["busca_endossado"] == "endossado") {
		file_put_contents($path . "/endossado/empresa_" . $_POST["empresa"] . ".json", json_encode($totaisEmpr, JSON_UNESCAPED_UNICODE));
	} else {
		file_put_contents($path . "/nao_endossado/empresa_" . $_POST["empresa"] . ".json", json_encode($totaisEmpr, JSON_UNESCAPED_UNICODE));
	}

	// sleep(1);
	return;
}

function criar_relatorio_ajustes() {
	$periodoInicio = new DateTime($_POST["busca_periodo"][0]);
	$periodoFim = new DateTime($_POST["busca_periodo"][1]);

	$empresas = mysqli_fetch_all(query(
		"SELECT empr_nb_id, empr_tx_nome FROM empresa"
			. " WHERE empr_tx_status = 'ativo'"
			. (!empty($_POST["empresa"]) ? " AND empr_nb_id = " . $_POST["empresa"] : "")
			. " ORDER BY empr_tx_nome ASC;"
	), MYSQLI_ASSOC);

	$dominiosAutotrac = ["/comav"];
	if (!in_array($_SERVER["REQUEST_URI"], $dominiosAutotrac)) {
		$macros = mysqli_fetch_all(
			query(
				"SELECT macr_tx_nome FROM macroponto WHERE macr_tx_fonte = 'positron'"
			),
			MYSQLI_ASSOC
		);
	} else {
		$macros = mysqli_fetch_all(
			query(
				"SELECT macr_tx_nome FROM macroponto WHERE macr_tx_fonte != 'positron'"
			),
			MYSQLI_ASSOC
		);
	}

	$macros = array_column($macros, 'macr_tx_nome');
	foreach ($empresas as $empresa) {
		$path = "./arquivos/ajustes" . "/" . $periodoInicio->format("Y-m") . "/" . $empresa["empr_nb_id"];
		if (is_dir($path)) {
			$pasta = dir($path);
			while (($arquivo = $pasta->read()) !== false) {
				// Ignora os diretórios especiais '.' e '..'
				if ($arquivo != '.' && $arquivo != '..') {
					$arquivoPath = $path . '/' . $arquivo;  // Caminho completo do 
					unlink($arquivoPath);  // Apaga o arquivo
				}
			}
			$pasta->close();
		}
	}

	foreach ($empresas as $empresa) {
		$totaisEmpr = [];
		$totaisEmpresa = [];
		$rows = [];
		// $totais= [];
		$path = "./arquivos/ajustes" . "/" . $periodoInicio->format("Y-m") . "/" . $empresa["empr_nb_id"];
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}

		$motoristas = mysqli_fetch_all(
			query(
				"SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula, enti_tx_ocupacao FROM entidade
					 WHERE enti_tx_status = 'ativo'
					 AND enti_nb_empresa = {$empresa['empr_nb_id']}
					 AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')
					 ORDER BY enti_tx_nome ASC;"
			),
			MYSQLI_ASSOC
		);

		$pasta = dir($path);
		if (is_dir($path)) {
			while (($arquivo = $pasta->read()) !== false) {
				// Ignora os diretórios especiais '.' e '..'
				if ($arquivo != '.' && $arquivo != '..') {
					$arquivoPath = $path . '/' . $arquivo;  // Caminho completo do arquivo
					unlink($arquivoPath);  // Apaga o arquivo
				}
			}
			$pasta->close();
		}

		foreach ($motoristas as $motorista) {
			$ocorrencias = [];
			$verificaValores = [];

			foreach ($macros as $macro) {
				if (!isset($ocorrencias[$macro])) {
					$ocorrencias[$macro] = [
						'ativo' => 0,
						'inativo' => 0,
					];
				}
			}

			$totalMotorista = [
				"matricula" 				=> $motorista["enti_tx_matricula"],
				"nome" 						=> $motorista["enti_tx_nome"],
				"ocupacao" 					=> $motorista["enti_tx_ocupacao"],


				// "dataInicio"				=> $periodoInicio->format("d/m/Y"),
				// "dataFim"					=> $periodoFim->format("d/m/Y")
			];
			$diaInicio = $periodoInicio->format('Y-m-d');
			$diafim = $periodoFim->format('Y-m-d');

			$userID = mysqli_fetch_all(
				query(
					"SELECT user_nb_id FROM user"
						. " WHERE user_nb_entidade = '{$motorista['enti_nb_id']}'"
				),
				MYSQLI_ASSOC
			);

			$idUser = $userID[0]['user_nb_id'];

			$pontosAtivos = mysqli_fetch_all(
				query(
					"SELECT DISTINCT ponto.pont_tx_data,  ponto.pont_tx_matricula, motivo.moti_tx_nome, macroponto.macr_tx_nome, 
							ponto.pont_tx_status"
						. " FROM ponto"
						. " LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id"
						. " INNER JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno"
						. " AND macroponto.macr_tx_fonte = 'positron'"
						. " LEFT JOIN user ON ponto.pont_nb_userCadastro = user.user_nb_id"
						. " WHERE pont_tx_matricula = '$motorista[enti_tx_matricula]'"
						. " AND pont_nb_userCadastro != '$idUser'"
						. " AND (user.user_tx_matricula <> ponto.pont_tx_matricula OR user.user_tx_matricula IS NULL)"
						. " AND pont_tx_status != 'inativo'"
						. " AND pont_tx_data BETWEEN STR_TO_DATE('$diaInicio 00:00:00', '%Y-%m-%d %H:%i:%s')"
						. " AND STR_TO_DATE('$diafim 23:59:59', '%Y-%m-%d %H:%i:%s')"
						. " ORDER BY ponto.pont_tx_data ASC;"
				),
				MYSQLI_ASSOC
			);

			foreach ($pontosAtivos as $registro2) {
				$macr_tx_nome = $registro2['macr_tx_nome'];
				if (in_array($macr_tx_nome, $macros)) {
					// Inicializa como 0 se não existir
					if (!isset($ocorrencias[$macr_tx_nome]["ativo"])) {
						$ocorrencias[$macr_tx_nome]["ativo"] = 0;
					}

					// Incrementa o contador
					$ocorrencias[$macr_tx_nome]["ativo"]++;
				}
			}

			$pontosInativos = mysqli_fetch_all(
				query(
					"SELECT ponto.pont_tx_data, ponto.pont_tx_matricula, ponto.pont_tx_status, 
						ponto.pont_tx_tipo, macroponto.macr_tx_nome"
						. " FROM ponto"
						. " INNER JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno"
						. " WHERE pont_tx_matricula = '$motorista[enti_tx_matricula]'"
						. " AND pont_tx_status != 'ativo'"
						. " AND pont_tx_data BETWEEN STR_TO_DATE('$diaInicio 00:00:00', '%Y-%m-%d %H:%i:%s')"
						. " AND STR_TO_DATE('$diafim 23:59:59', '%Y-%m-%d %H:%i:%s')"
						. " ORDER BY ponto.pont_tx_data ASC;"
				),
				MYSQLI_ASSOC
			);

			foreach ($pontosInativos as $registro) {
				$macr_tx_nome = $registro['macr_tx_nome'];
				if (in_array($macr_tx_nome, $macros)) {
					// Inicializa como 0 se não existir
					if (!isset($ocorrencias[$macr_tx_nome]["inativo"])) {
						$ocorrencias[$macr_tx_nome]["inativo"] = 0;
					}

					// Incrementa o contador
					$ocorrencias[$macr_tx_nome]["inativo"]++;
				}
			}
			$totalMotorista = array_merge($totalMotorista, $ocorrencias);
			$totalMotorista['pontos'] = array_merge($pontosAtivos, $pontosInativos);
			// Filtrar apenas os campos numéricos que precisam ser verificados
			$verificaValores = array_filter($totalMotorista, function ($key) {
				return !in_array($key, ["matricula", "nome", "ocupacao", "pontos"]);
			}, ARRAY_FILTER_USE_KEY);

			$rows[] = $ocorrencias;
			if (array_sum(array_map(function ($valor) {
				return array_sum($valor); // Soma os valores de 'ativo' e 'inativo' dentro de cada chave
			}, $verificaValores)) > 0) {
				file_put_contents($path . "/" . $motorista["enti_tx_matricula"] . ".json", json_encode($totalMotorista, JSON_UNESCAPED_UNICODE));
			}
		}

		foreach ($rows as $row => $eventos) {
			foreach ($eventos as $evento => $valores) {
				if (!isset($totaisEmpr[$evento])) {
					$totaisEmpr[$evento] = 0; // Inicializa a soma para a chave do evento
				}

				foreach ($valores as $estado => $value) {
					$totaisEmpr[$evento] += $value; // Incrementa o total para a chave do evento
				}

				foreach ($valores as $estado => $value) {
					if ($estado === 'ativo') {
						$totaisEmpr['totais']['ativo'] += $value; // Soma para os ativos
					} elseif ($estado === 'inativo') {
						$totaisEmpr['totais']['inativo'] += $value; // Soma para os inativos
					}
				}
			}
		}

		$totais[] = $totaisEmpr;
		$empresa = array_merge($totaisEmpr, $empresa);

		$empresa["qtdMotoristas"] = count($motoristas);
		$empresa["dataInicio"] = $periodoInicio->format("d/m/Y");
		$empresa["dataFim"] = $periodoFim->format("d/m/Y");

		file_put_contents($path . "/empresa_" . $empresa["empr_nb_id"] . ".json", json_encode($empresa));
	}

	foreach ($totais as $empresaKey => $values) {
		foreach ($values as $categoriaKey => $value) {
			if (!is_numeric($value)) {
				continue; // Ignora valores que não são números
			}

			if (!isset($totaisEmpresa[$empresaKey][$categoriaKey])) {
				$totaisEmpresa[$empresaKey][$categoriaKey] = 0;
			}

			$totaisEmpresa[$empresaKey][$categoriaKey] += $value;
		}
	}

	if (empty($_POST["empresa"])) {
		$path = "./arquivos/ajustes" . "/" . $periodoInicio->format("Y-m");
		$totaisEmpresa["dataInicio"] = $periodoInicio->format("d/m/Y");
		$totaisEmpresa["dataFim"] = $periodoFim->format("d/m/Y");
		file_put_contents($path . "/empresas.json", json_encode($totaisEmpresa));
	}

	return;
}

function logisticas() {
	$path = "./arquivos/nc_logistica" . "/" . $_POST["empresa"];
	$motoristasLivres = [];
	$totalMotoristasLivres = 0;
	if (!is_dir($path)) {
		mkdir($path, 0755, true);
	}

	$periodo = DateTime::createFromFormat('d/m/Y H:i', $_POST["busca_periodo"]);
	$periodoInicio = $periodo->format('Y-m') . "-01";
	$hoje = new DateTime();

	if (empty($_POST["busca_periodo"])) {
		// $hoje->modify('-1 day');
		// Se for o mês atual, a data limite é o dia de hoje
		$periodoFim = $hoje;
	} else {
		$periodoFim = DateTime::createFromFormat('d/m/Y H:i', $_POST["busca_periodo"]);
	}

	if (!empty($_POST["busca_ocupacao"])) {
		$filtroOcupacao = "AND enti_tx_ocupacao IN ('{$_POST["busca_ocupacao"]}')";
	}

	$motoristas = mysqli_fetch_all(query(
		"SELECT * FROM entidade
				LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
				WHERE enti_tx_status = 'ativo'
					AND enti_nb_empresa = {$_POST["empresa"]}
					AND enti_tx_dataCadastro <= '{$periodoInicio}'
					{$filtroOcupacao}
				ORDER BY enti_tx_nome ASC;"
	), MYSQLI_ASSOC);

	foreach ($motoristas as $motorista) {
		$parametro = mysqli_fetch_all(query(
			"SELECT para_tx_jornadaSemanal, para_tx_jornadaSabado, para_tx_maxHESemanalDiario, para_tx_adi5322"
				. " FROM `parametro`"
				. " WHERE para_nb_id = " . $motorista["enti_nb_parametro"]
		), MYSQLI_ASSOC);

		$diaPonto = [];
		$maxTentativas = 30; // Define um limite de 30 dias
		$tentativas = 0;

		for ($date = $periodoFim;; $date->modify('-1 day'), $tentativas++) {
			$diaPonto = diaDetalhePonto($motorista, $date->format('Y-m-d'));

			if (
				!empty($diaPonto["inicioJornada"]) && strpos($diaPonto["inicioJornada"], "fa-warning") === false
			) {
				break;
			}
		}

		if (empty($_POST["busca_periodo"])) {
			$periodoFim = $hoje;
		} else {
			$periodoFim = DateTime::createFromFormat('d/m/Y H:i', $_POST["busca_periodo"]);
		}

		if (!empty($diaPonto["fimJornada"]) && strpos($diaPonto["fimJornada"], "fa-warning") === false) {
			$totalMotoristasLivres += 1;

			if (strpos($diaPonto["fimJornada"], "D+") !==  false) {
				if (preg_match('/D\+(\d+)/', $diaPonto["fimJornada"], $matches) === 1) {
					$dias = $matches[1]; // Captura o número após "D+"
					$dataFim = DateTime::createFromFormat('d/m/Y', $diaPonto['data']);
					$dataFim->modify("+$dias days");
					$dataPonto = $dataFim->format('d/m/Y');
				}
			} else {
				$dataPonto = $diaPonto['data'];
			}
			$horaString = preg_replace('/^(\d{2}:\d{2}).*/', '$1', $diaPonto['fimJornada']);
			$dataString = $dataPonto . ' ' . $horaString;

			$dataFormatada = DateTime::createFromFormat('d/m/Y H:i', $dataString);

			// Calcula datas de referência (8h e 11h após a última jornada)
			$dataMais8Horas = clone $dataFormatada;
			$dataMais8Horas->modify('+8 hours');

			$dataMais11Horas = clone $dataFormatada;
			$dataMais11Horas->modify('+11 hours');

			$dataReferenciaStr = $_POST['busca_periodo'] ?? null;
			$dataReferencia = DateTime::createFromFormat('d/m/Y H:i', $dataReferenciaStr);

			// Calcula diferença em minutos desde a última jornada
			$diferenca = $dataFormatada->diff($dataReferencia);
			$totalMinutos = ($diferenca->days * 24 * 60) + ($diferenca->h * 60) + $diferenca->i;

			// Verifica se a ADI 5322 está ativa
			// Se ativa, significa que o repouso ideal é de 11h (mas o mínimo absoluto para iniciar pode ser considerado 8h para efeito de aviso parcial)
			$considerarADI = isset($parametro[0]['para_tx_adi5322']) && $parametro[0]['para_tx_adi5322'] === 'sim';

			// Para exibição, o campo "ADI_5322" indica qual o repouso mínimo esperado:
			// - Se ADI ativa: "Sim (11h mín.)"
			// - Se não: "Não (8h mín.)"
			$infoADI = $considerarADI ? 'Sim' : 'Não';

			// Define os limites:
			$minimoAbsoluto = 8 * 60;   // 480 minutos
			$minimoCompleto = 11 * 60;   // 660 minutos

			// Calcula o aviso de repouso, mostrando a diferença em relação ao repouso completo (11h)
			$difRepouso = $totalMinutos - $minimoCompleto;
			$horas = floor(abs($difRepouso) / 60);
			$minutos = abs($difRepouso) % 60;
			$sinal = ($difRepouso < 0) ? '-' : '';

			$avisoRepouso = $sinal . str_pad($horas, 2, '0', STR_PAD_LEFT) . ":" .
				str_pad($minutos, 2, '0', STR_PAD_LEFT);
			
			if ($horas >= 24 && $sinal !== '-') {
				$diasExtras = floor($horas / 24);
				$avisoRepouso .= " (D+" . $diasExtras . ")";
			}

			// Para o campo 'Apos8': exibe a data de +8h apenas se a ADI não estiver ativa e se o repouso for inferior a 11h
			$exibirApos8 = (!$considerarADI && $totalMinutos < $minimoCompleto) ? $dataMais8Horas->format('d/m/Y H:i') : '';

			// O campo 'Apos11' exibe sempre a data de +11h
			$exibirApos11 = $dataMais11Horas->format('d/m/Y H:i');

			// Dados do motorista
			$dadosMotorista = [
				'matricula'       => $motorista['enti_tx_matricula'],
				'Nome'            => $motorista['enti_tx_nome'],
				'ocupacao'        => $motorista['enti_tx_ocupacao'],
				'ultimaJornada'   => $dataFormatada->format('d/m/Y H:i'),
				'repouso'         => $avisoRepouso,
				'Apos8'           => $exibirApos8,   // Exibe +8h, conforme condição
				'Apos11'          => $exibirApos11,  // Exibe +11h sempre
				'consulta'        => $dataReferenciaStr,
				'ADI_5322'        => $infoADI
			];

			// Categorização:
			// Para ambos os casos (ADI ativa ou não), usamos os dois limites:
			// - "naoPermitido": repouso < 8h
			// - "parcial": repouso entre 8h e 11h (por exemplo, -03:00 indica repouso parcial se faltar 3h para completar 11h)
			// - "disponivel": repouso >= 11h
			if ($totalMinutos < $minimoAbsoluto) {
				$motoristasLivres['naoPermitido'][] = $dadosMotorista;
			} elseif ($totalMinutos >= $minimoAbsoluto && $totalMinutos < $minimoCompleto) {
				$motoristasLivres['parcial'][] = $dadosMotorista;
			} else {
				$motoristasLivres['disponivel'][] = $dadosMotorista;
			}
		}
	}

	$motoristasLivres['total'] = [
		'totalMotoristasJornada' => count($motoristas) - $totalMotoristasLivres,
		'totalMotoristasLivres' => $totalMotoristasLivres,
		'consulta'        => $dataReferenciaStr,
	];

	file_put_contents($path . "/nc_logistica.json", json_encode($motoristasLivres, JSON_UNESCAPED_UNICODE));
}
