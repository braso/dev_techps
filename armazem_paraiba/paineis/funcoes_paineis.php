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
		$dataHora = DateTime::createFromFormat("Y-m-d H:i", date("Y-m-d H:i"));

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

			if (!empty($_POST["busca_ocupacao"])) {
				$filtroOcupacao = "AND enti_tx_ocupacao IN ('{$_POST["busca_ocupacao"]}')";
			}

			$motoristas = mysqli_fetch_all(query(
				"SELECT * FROM entidade
						LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
						LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
						LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
						WHERE enti_tx_status = 'ativo'
							AND enti_nb_empresa = '{$empresa["empr_nb_id"]}'
							" . (!empty($_POST["motorista"]) ? "AND enti_nb_id = '{$_POST["motorista"]}'" : "") . "
							{$filtroOcupacao}
						ORDER BY enti_tx_nome ASC;"
			), MYSQLI_ASSOC);

			$rows = [];
			$statusEndossos = [
				"E" 	=> 0,
				"EP" 	=> 0,
				"N" 	=> 0
			];
			foreach ($motoristas as $motorista) {
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
				$saldoAnterior = mysqli_fetch_assoc(query(
					"SELECT endo_tx_saldo FROM endosso"
						. " WHERE endo_tx_status = 'ativo'"
						. " AND endo_tx_ate < '" . $dataMes->format("Y-m-d") . "'"
						. " AND endo_tx_matricula = '" . $motorista["enti_tx_matricula"] . "'"
						. " ORDER BY endo_tx_ate DESC"
						. " LIMIT 1;"
				));

				if (!empty($saldoAnterior)) {
					if (!empty($saldoAnterior["endo_tx_saldo"])) {
						$saldoAnterior = $saldoAnterior["endo_tx_saldo"];
					} elseif (!empty($motorista["enti_tx_banco"])) {
						$saldoAnterior = $motorista["enti_tx_banco"];
					}
					if (strlen($motorista["enti_tx_banco"]) > 5 && $motorista["enti_tx_banco"][0] == "0") {
						$saldoAnterior = substr($saldoAnterior, 1);
					}
				} else {
					$saldoAnterior = "00:00";
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
			// if(file_exists($path."/empresa_".$empresa["empr_nb_id"].".json")){
			// 	if(date("Y-m-d", filemtime($path."/empresa_".$empresa["empr_nb_id"].".json")) == date("Y-m-d")){
			// 		echo 
			// 			"<script>"
			// 			."confirm('O relatório de ".$empresa["empr_tx_nome"]." já foi gerado hoje, deseja gerar novamente?');"
			// 			."</script>"
			// 		;
			// 		continue;
			// 	}
			// }
			if (!empty($_POST["busca_ocupacao"])) {
				$filtroOcupacao = "AND enti_tx_ocupacao IN ('{$_POST["busca_ocupacao"]}')";
			} else {
				$filtroOcupacao = " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')";
			}

			$motoristas = mysqli_fetch_all(query(
				"SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_banco, enti_tx_ocupacao, enti_tx_admissao FROM entidade"
					. " WHERE enti_tx_status = 'ativo'"
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
							. " WHERE endo_tx_status = 'ativo'"
							. " AND endo_nb_entidade = '" . $motorista["enti_nb_id"] . "'"
							. " AND ("
							. "   (endo_tx_de  >= '" . $mes->format("Y-m-01") . "' AND endo_tx_de  <= '" . $mes->format("Y-m-t") . "')"
							. "OR (endo_tx_ate >= '" . $mes->format("Y-m-01") . "' AND endo_tx_ate <= '" . $mes->format("Y-m-t") . "')"
							. "OR (endo_tx_de  <= '" . $mes->format("Y-m-01") . "' AND endo_tx_ate >= '" . $mes->format("Y-m-t") . "')"
							. ")"
							. " ORDER BY endo_tx_ate;"
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
					$saldoAnterior = mysqli_fetch_assoc(query(
						"SELECT endo_tx_saldo FROM endosso"
							. " WHERE endo_tx_status = 'ativo'"
							. " AND endo_tx_ate < '" . $mes->format("Y-m-01") . "'"
							. " AND endo_tx_matricula = '" . $motorista["enti_tx_matricula"] . "'"
							. " ORDER BY endo_tx_ate DESC"
							. " LIMIT 1;"
					));

					if (!empty($saldoAnterior)) {
						if (!empty($saldoAnterior["endo_tx_saldo"])) {
							$saldoAnterior = $saldoAnterior["endo_tx_saldo"];
						} elseif (!empty($motorista["enti_tx_banco"])) {
							$saldoAnterior = $motorista["enti_tx_banco"];
						}
						if (strlen($motorista["enti_tx_banco"]) > 5 && $motorista["enti_tx_banco"][0] == "0") {
							$saldoAnterior = substr($saldoAnterior, 1);
						}
					} else {
						$saldoAnterior = "00:00";
					}
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
						$totaisMot = [
							"jornadaPrevista" => "00:00",
							"jornadaEfetiva" => "00:00",
							"he50APagar" => "00:00",
							"he100APagar" => "00:00",
							"adicionalNoturno" => "00:00",
							"esperaIndenizada" => "00:00",
							"saldoPeriodo" => "00:00",
							"saldoFinal" => "00:00"
						];

						foreach ($endossos as $endosso) {
							$endosso = lerEndossoCSV($endosso["endo_tx_filename"]);
							if (empty($endosso["totalResumo"]["he50APagar"])) {
								$pago = calcularHorasAPagar(
									operarHorarios([$endosso["totalResumo"]["saldoAnterior"], $endosso["totalResumo"]["diffSaldo"]], "+"),
									$endosso["totalResumo"]["he50"],
									$endosso["totalResumo"]["he100"],
									$endosso["endo_tx_horasApagar"]
								);
								[$endosso["totalResumo"]["he50APagar"], $endosso["totalResumo"]["he100APagar"]] = $pago;
							}
							$totaisMot["jornadaPrevista"] 	= operarHorarios([$totaisMot["jornadaPrevista"], $endosso["totalResumo"]["jornadaPrevista"]], "+");
							$totaisMot["jornadaEfetiva"] 	= operarHorarios([$totaisMot["jornadaEfetiva"], $endosso["totalResumo"]["diffJornadaEfetiva"]], "+");
							$totaisMot["he50APagar"] 		= operarHorarios([$totaisMot["he50APagar"], $endosso["totalResumo"]["he50APagar"]], "+");
							$totaisMot["he100APagar"] 		= operarHorarios([$totaisMot["he100APagar"], $endosso["totalResumo"]["he100APagar"]], "+");
							$totaisMot["adicionalNoturno"] 	= operarHorarios([$totaisMot["adicionalNoturno"], $endosso["totalResumo"]["adicionalNoturno"]], "+");
							$totaisMot["esperaIndenizada"] 	= operarHorarios([$totaisMot["esperaIndenizada"], $endosso["totalResumo"]["esperaIndenizada"]], "+");
							if (empty($totaisMot["saldoAnterior"])) {
								$totaisMot["saldoAnterior"] = $endosso["totalResumo"]["saldoAnterior"];
							}
							$totaisMot["saldoPeriodo"] 		= operarHorarios([$totaisMot["saldoPeriodo"], $endosso["totalResumo"]["diffSaldo"]], "+");
							if (empty($endosso["totalResumo"]["saldoBruto"]) && !empty($endosso["totalResumo"]["saldoAtual"])) {
								$totaisMot["saldoFinal"] = operarHorarios([$endosso["totalResumo"]["saldoAtual"], $endosso["totalResumo"]["he100"]], "+");
							} else {
								$totaisMot["saldoFinal"] = operarHorarios([$endosso["totalResumo"]["saldoAnterior"], $totaisMot["saldoPeriodo"]], "+");
								$totaisMot["saldoFinal"] = operarHorarios([$totaisMot["saldoFinal"], $endosso["totalResumo"]["he50APagar"], $endosso["totalResumo"]["he100APagar"]], "-");
							}
						}
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
			$empresa["dataInicio"] = $mes->format("Y-m-01");
			$empresa["dataFim"] = $mes->format("Y-m-t");
			$empresa["percEndossado"] = ($statusEndossos["E"]) / array_sum(array_values($statusEndossos));


			file_put_contents($path . "/empresa_" . $empresa["empr_nb_id"] . ".json", json_encode($empresa));
		}

		if (empty($_POST["empresa"])) {
			$path = "./arquivos/endossos" . "/" . $mes->format("Y-m");
			$totaisEmpresas["dataInicio"] = $mes->format("Y-m-01");
			$totaisEmpresas["dataFim"] = $mes->format("Y-m-t");
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
					." WHERE p.pont_tx_matricula = '{$motorista["enti_tx_matricula"]}'"
					." AND p.pont_tx_status = 'ativo'"
					." AND p.pont_tx_tipo = 1"
					." ORDER BY p.pont_tx_data DESC -- Ordena pela data em ordem decrescente
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

				if(strpos($dia["fimJornada"], "fa fa-warning") !== false){
					$fimJornada = true;
				} else {
					$horaInicio = preg_replace('/<strong>.*?<\/strong>/', '', $dia["inicioJornada"]);
					$horaFim = preg_replace('/<strong>.*?<\/strong>/', '', $dia["fimJornada"]);
					$horaRemoverExtraI = preg_replace('/[^0-9:]/', ' ', $horaInicio );
					$horaRemoverExtraF = preg_replace('/[^0-9:]/', ' ', $horaFim);
					$inicio = explode(' ', $horaRemoverExtraI);
					$fim = explode(' ' , $horaRemoverExtraF);
					$filtraInicio = array_filter($inicio);
					$filtraFim = array_filter($fim);
					if(sizeof($filtraInicio) == sizeof($filtraFim)){
						$fimJornada = false;
					}
					else{
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
						if(strlen($dia["inicioJornada"]) == 5){
							$hora = preg_replace('/<strong>.*?<\/strong>/', '', $dia["inicioJornada"]);
							$hora = preg_replace('/[^0-9:]/', '', $hora);
							$hora = trim($hora);
							$horaEspecifica = new DateTime($hora);
							$horaAtual = new DateTime();
							$jornada = $horaAtual->diff($horaEspecifica)->format('%H:%I');
						} else {
							$horaInicio = preg_replace('/<strong>.*?<\/strong>/', '', $dia["inicioJornada"]);
							$horaRemoverExtraI = preg_replace('/[^0-9:]/', ' ', $horaInicio );
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
			if($arquivosMantidos != null){
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
		$hoje = new DateTime();

		if ($periodoInicio->format('Y-m') === $hoje->format('Y-m')) {
			$hoje->modify('-1 day');
			// Se for o mês atual, a data limite é o dia de hoje
			$periodoFim = $hoje;
		} else {
			$periodoFim = new DateTime($periodoInicio->format("Y-m-t"));
		}

		if ($_POST["busca_endossado"] == "endossado") {
			$mes = new DateTime($_POST["busca_dataMes"] . "-01");
			$endossos = mysqli_fetch_all(query(
				"SELECT * FROM endosso"
					. " WHERE endo_tx_status = 'ativo'"
					. " AND ("
					. "   (endo_tx_de  >= '{$mes->format("Y-m-01")}' AND endo_tx_de  <= '{$mes->format("Y-m-t")}')"
					. "OR (endo_tx_ate >= '{$mes->format("Y-m-01")}' AND endo_tx_ate <= '{$mes->format("Y-m-t")}')"
					. "OR (endo_tx_de  <= '{$mes->format("Y-m-01")}' AND endo_tx_ate >= '{$mes->format("Y-m-t")}')"
					. ")"
					. " ORDER BY endo_tx_ate;"
			), MYSQLI_ASSOC);
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
				LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
				WHERE enti_tx_status = 'ativo'
					AND enti_nb_empresa = {$_POST["empresa"]}
					AND enti_tx_dataCadastro <= '{$periodoInicio->format("Y-m-t")}'
					{$filtroOcupacao}
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

				"dataInicio"				=> $periodoInicio->format("d/m/Y"),
				"dataFim"					=> $periodoFim->format("d/m/Y")
			];

			if ($_POST["busca_endossado"] == "endossado") {
				foreach ($endossos as $endosso) {
					$houveInteracao = false;
					if ($motorista["enti_nb_id"] === $endosso["endo_nb_entidade"]) {
						$endosso = lerEndossoCSV($endosso["endo_tx_filename"]);

						foreach ($endosso["endo_tx_pontos"] as $ponto) {
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
				}
			} else {

				$diaPonto = [];
				for ($date = clone $periodoInicio; $date <= $periodoFim; $date->modify('+1 day')) {
					$diaPonto[] = diaDetalhePonto($motorista, $date->format('Y-m-d'));
				}

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

			if (!empty($_POST["busca_ocupacao"])) {
				$filtroOcupacao = "AND enti_tx_ocupacao IN ('{$_POST["busca_ocupacao"]}')";
			}

			$motoristas = mysqli_fetch_all(
				query(
					"SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula, enti_tx_ocupacao FROM entidade
					 WHERE enti_tx_status = 'ativo'
					 AND enti_nb_empresa = {$empresa['empr_nb_id']}
					 AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')
					 {$filtroOcupacao}
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
			$periodoFim = DateTime::createFromFormat('d/m/Y H:i',$_POST["busca_periodo"]);
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

		// var_dump($periodoFim);

		foreach ($motoristas as $motorista) {
			$diaPonto = [];
			$maxTentativas = 30; // Define um limite de 30 dias
			$tentativas = 0;
			
			for ($date = clone $periodoFim; ; $date->modify('-1 day'), $tentativas++) {
				$diaPonto = diaDetalhePonto($motorista, $date->format('Y-m-d'));
			
				if (strpos($diaPonto["inicioJornada"], "fa-warning") === false && !empty($diaPonto["inicioJornada"]) 
				|| strpos($diaPonto["jornadaPrevista"], "fa-info-circle") !==  false 
				&& strpos($diaPonto["jornadaPrevista"], "Abono: 00:00:00") === false 
				|| strpos($diaPonto["jornadaPrevista"], "fa-info-circle") !==  false 
				&& strpos($diaPonto["jornadaPrevista"], "color:red;") === false) {
					break;
				}
			}

			if (strpos($diaPonto["fimJornada"], "fa fa-warning") === false && !empty($diaPonto["fimJornada"]) 
			|| strpos($diaPonto["jornadaPrevista"], "fa-info-circle") !==  false 
			&& strpos($diaPonto["jornadaPrevista"], "Abono: 00:00:00") === false
			|| strpos($diaPonto["jornadaPrevista"], "fa-info-circle") !==  false 
				&& strpos($diaPonto["jornadaPrevista"], "color:red;") === false) {
				$totalMotoristasLivres += 1;
				if(strpos($diaPonto["jornadaPrevista"], "fa-info-circle") !==  false 
				&& strpos($diaPonto["jornadaPrevista"], "Abono: 00:00:00") === false){
					$dataString = $diaPonto['data'] . ' 00:00';
				} else {
					if(strpos($diaPonto["fimJornada"], "D+") !==  false){
						if (preg_match('/D\+(\d+)/', $diaPonto["fimJornada"], $matches) === 1) {
							$dias = $matches[1]; // Captura o número após "D+"
							$dataFim = DateTime::createFromFormat('d/m/Y', $diaPonto['data']);
							$dataFim->modify("+$dias days");
							$dataPonto = $dataFim->format('d/m/Y');
						} 
					} else{
						$dataPonto = $diaPonto['data'];
					}
					$horaString = preg_replace('/^(\d{2}:\d{2}).*/', '$1', $diaPonto['fimJornada']);
					$dataString = $dataPonto . ' ' . $horaString;
				}

				$dataFormatada = DateTime::createFromFormat('d/m/Y H:i', $dataString);

				$dataMais11Horas = clone $dataFormatada;
				$dataMais11Horas->modify('+11 hours'); // Adiciona as 11 horas

				$dataMais8Horas = clone $dataFormatada;
				$dataMais8Horas->modify('+8 hours');

				$dataReferenciaStr = $_POST['busca_periodo'] ?? null;
				$dataReferencia = DateTime::createFromFormat('d/m/Y H:i', $dataReferenciaStr);

				// Calcula a diferença entre as datas
				$diferenca = $dataFormatada->diff($dataReferencia);

				// Total de horas e minutos considerando o valor absoluto da diferença
				$totalHoras = ($diferenca->days * 24) + $diferenca->h;  // Total de horas
				$totalMinutos = ($totalHoras * 60) + $diferenca->i; // Total em minutos

				// Ajusta o total de minutos para considerar as 11 horas de subtração
				$minutosTotais = $totalMinutos - (11 * 60);

				// Se a diferença for negativa, isso significa que a dataFormatada é posterior à dataReferencia
				if ($minutosTotais < 0) {
					// Ajusta a lógica para garantir que a diferença seja negativa apenas se necessário
					$horasTotais = floor($minutosTotais / 60);  // Horas totais
					$minutosRestantes = abs($minutosTotais % 60); // Minutos restantes
				} else {
					// Caso contrário, faz o cálculo para a diferença positiva
					$horasTotais = floor($minutosTotais / 60);
					$minutosRestantes = $minutosTotais % 60;
				}

				$aviso = ($totalMinutos >= (11 * 60)) ? '11:00 + ' : '';

				$dadosMotorista = [
					'matricula' => $motorista['enti_tx_matricula'],
					'Nome' => $motorista['enti_tx_nome'],
					'ocupacao' => $motorista['enti_tx_ocupacao'],
					'ultimaJornada' => $dataFormatada->format('d/m/Y H:i'),
					'repouso' => $aviso."". str_pad($horasTotais, 2, '0', STR_PAD_LEFT) . ":". str_pad($minutosRestantes, 2, '0', STR_PAD_LEFT),
					'Apos11' => $dataMais11Horas->format('d/m/Y H:i'),
					'Apos8' => $dataMais8Horas->format('d/m/Y H:i'),
					'consulta' => $dataReferenciaStr
				];

				if ($minutosTotais < (-8 * 60)) {
					// Caso o motorista ainda precise de mais de 8 horas (falta mais que 8h)
					$motoristasLivres['naoPermitido'][] = $dadosMotorista;
				} elseif ($minutosTotais >= (-8 * 60) && $minutosTotais < (0)) {
					// Caso o motorista tenha completado mais de 8 horas, mas ainda falta para completar 11 horas
					$motoristasLivres['parcial'][] = $dadosMotorista;
				} else {
					// Caso o motorista tenha completado as 11 horas
					$motoristasLivres['disponivel'][] = $dadosMotorista;
				}

			} else{
				// Data base
				$dataJornada = $diaPonto['data'].' '.$diaPonto['inicioJornada'];
				$dataBase = DateTime::createFromFormat('d/m/Y H:i',$dataJornada);
				$intersticio = preg_replace('/<a.*?>.*?<\/a>/s', '', $diaPonto['intersticio']);
				$intersticio = preg_replace('/<i.*?>.*?<\/i>/s', '', $intersticio);

				$refeicao = preg_replace('/<a.*?>.*?<\/a>/s', '', $diaPonto['diffRefeicao']);
				$refeicao = preg_replace('/<i.*?>.*?<\/i>/s', '', $refeicao);

				$espera = preg_replace('/<a.*?>.*?<\/a>/s', '', $diaPonto['diffEspera']);
				$espera = preg_replace('/<i.*?>.*?<\/i>/s', '', $espera);

				$descanso = preg_replace('/<a.*?>.*?<\/a>/s', '', $diaPonto['diffDescanso']);
				$descanso = preg_replace('/<i.*?>.*?<\/i>/s', '', $descanso);

				$repouso = preg_replace('/<a.*?>.*?<\/a>/s', '', $diaPonto['diffRepouso']);
				$repouso = preg_replace('/<i.*?>.*?<\/i>/s', '', $repouso);
				
				// Separa as duas strings em horas e minutos
				list($h1, $m1) = explode(':', $intersticio); // INTERSTÍCIO
				list($h2, $m2) = explode(':', $refeicao); // REFEIÇÃO
				list($h3, $m3) = explode(':', $espera); // ESPERA
				list($h4, $m4) = explode(':', $descanso); // DESCANSO
				list($h5, $m5) = explode(':', $repouso); // REPOUSO

				// Converte ambos para minutos
				$totalMinutos1 = ($h1 * 60) + $m1;
				$totalMinutos2 = ($h2 * 60) + $m2;
				$totalMinutos3 = ($h3 * 60) + $m3;
				$totalMinutos4 = ($h4 * 60) + $m4;
				$totalMinutos5 = ($h5* 60) + $m5;

				// Calcula a diferença
				$diferencaMinutos = $totalMinutos1 - $totalMinutos2 - $totalMinutos3 - $totalMinutos4 - $totalMinutos5;
				// Converte a diferença de volta para horas e minutos
				$horas = floor($diferencaMinutos / 60);  // 988 / 60 = 16
				$minutos = $diferencaMinutos % 60;         // 988 % 60 = 28
				
				// Cria o intervalo com o resultado (16:28)
				$intervalo = new DateInterval("PT{$horas}H{$minutos}M");

				// Subtrai o intervalo calculado da data base
				$dataBase->sub($intervalo);
				
				// Exibe o resultado final
				$dataBase->format('d/m/Y H:i'); // Resultado: 19/02/2025 13:40

				$dadosMotorista = [
					'matricula' => $motorista['enti_tx_matricula'],
					'Nome' => $motorista['enti_tx_nome'],
					'ocupacao' => $motorista['enti_tx_ocupacao'],
					'ultimaJornada' => $dataBase->format('d/m/Y H:i'),
					'jornadaAtual' => $dataJornada,
					'repouso' => $intersticio,
					'Apos11' => '00/00/00 00:00',
					'Apos8' => '00/00/00 00:00',
					'consulta' => $_POST['busca_periodo'] ?? null
				];

				$motoristasLivres['EmJornada'][] = $dadosMotorista;
			}
		}

		$motoristasLivres['total'] = [
			'totalMotoristasJornada' => count($motoristas) - $totalMotoristasLivres,
			'totalMotoristasLivres' => $totalMotoristasLivres
		];

		file_put_contents($path . "/nc_logistica.json", json_encode($motoristasLivres, JSON_UNESCAPED_UNICODE));
	}
