<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/

	include "funcoes_ponto.php"; //Conecta incluso dentro de funcoes_ponto


	function montarMensagemParametro(array &$motorista): string{
		$mensagemParametro = $motorista["para_tx_nome"];
		if($motorista["para_tx_tipo"] == "horas_por_dia"){
			$mensagemParametro .= " Semanal (".$motorista["enti_tx_jornadaSemanal"]."), Sábado (".$motorista["enti_tx_jornadaSabado"].")";
		}elseif(($motorista["para_tx_tipo"] == "escala")){
			$escala = mysqli_fetch_assoc(query("SELECT * FROM escala WHERE esca_nb_parametro = {$motorista["enti_nb_parametro"]}"));
			$mensagemParametro .= "<br>Dia 1: ".(new DateTime($escala["esca_tx_dataInicio"]))->format("d/m/Y");
		}

		if(!empty($motorista["empr_nb_parametro"])){
			$parametroEmpresa = mysqli_fetch_assoc(query(
				"SELECT para_tx_jornadaSemanal, para_tx_jornadaSabado, para_tx_percHESemanal, para_tx_percHEEx, para_nb_id, para_tx_nome, para_tx_tipo FROM parametro"
					." WHERE para_tx_status = 'ativo'"
						." AND para_nb_id = ".$motorista["empr_nb_parametro"]
					." LIMIT 1;"
			));
			
			if(!empty($parametroEmpresa)){
				if($parametroEmpresa["para_tx_tipo"] == "horas_por_dia"){
					$padronizado = (
						[$motorista["para_nb_id"], $motorista["para_tx_jornadaSemanal"], $motorista["para_tx_jornadaSabado"], $motorista["para_tx_percHESemanal"], $motorista["para_tx_percHEEx"]]
						==
						[$parametroEmpresa["para_nb_id"], $parametroEmpresa["para_tx_jornadaSemanal"], $parametroEmpresa["para_tx_jornadaSabado"], $parametroEmpresa["para_tx_percHESemanal"], $parametroEmpresa["para_tx_percHEEx"]]
					);
				}else{
					$padronizado = (
						[$motorista["para_nb_id"], $motorista["para_tx_percHESemanal"], $motorista["para_tx_percHEEx"]]
						==
						[$parametroEmpresa["para_nb_id"], $parametroEmpresa["para_tx_percHESemanal"], $parametroEmpresa["para_tx_percHEEx"]]
					);
				}

				$mensagemParametro = (!$padronizado? "Não ": "")."Padronizado.<br>";
				$mensagemParametro .= "{$motorista["para_tx_nome"]}";
				if(!empty($motorista["para_tx_jornadaSemanal"]) && !empty($motorista["para_tx_jornadaSabado"])){
					$mensagemParametro .= "<br>Semanal ({$motorista["para_tx_jornadaSemanal"]}), Sábado ({$motorista["para_tx_jornadaSabado"]})";
				}
			}
		}
		return $mensagemParametro;
	}


	function redirParaAbono(){
		unset($_POST["acao"]);
		if(empty($_POST['busca_motorista'])){
			header("Location: {$_ENV["APP_PATH"]}{$_ENV["CONTEX_PATH"]}/cadastro_abono.php");
			exit;
		}

		$_POST["acao"] = "index";
		echo criarHiddenForm(
			"form_abono",
			array_keys($_POST),
			array_values($_POST),
			"cadastro_abono.php"
		);
		echo "<script>document.form_abono.submit();</script>";
		exit;
	}

	function buscarEspelho(){
		if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])){
			[$_POST["busca_motorista"], $_POST["busca_empresa"]] = [$_SESSION["user_nb_entidade"], $_SESSION["user_nb_empresa"]];
		}
		
		//Confere se há algum erro na pesquisa{
			try{

				if(empty($_POST["busca_periodo"]) && !empty($_POST["periodo_abono"])){
					$_POST["busca_periodo"] = $_POST["periodo_abono"];
					unset($_POST["periodo_abono"]);
				}
				if(!empty($_POST["busca_motorista"]) && empty($_POST["busca_empresa"])){
					$_POST["busca_empresa"] = mysqli_fetch_assoc(query(
						"SELECT enti_nb_empresa FROM entidade WHERE enti_nb_id = {$_POST["busca_motorista"]} LIMIT 1;"
					))["enti_nb_empresa"];
				}
	
				//Conferir campos obrigatórios{
					$camposObrig = [
						"busca_empresa" => "Empresa",
						"busca_motorista" => "Funcionário",
						"busca_periodo" => "Período"
					];
					$errorMsg = conferirCamposObrig($camposObrig, $_POST);
					
					if(!empty($errorMsg)){
						throw new Exception($errorMsg);
					}
				//}

				if(is_string($_POST["busca_periodo"])){
					$_POST["busca_periodo"] = explode(" - ", $_POST["busca_periodo"]);
				}

				if($_POST["busca_periodo"][0] > date("Y-m-d") || $_POST["busca_periodo"][1] > date("Y-m-d")){
					$_POST["errorFields"][] = "busca_periodo";
					throw new Exception("Data de pesquisa não pode ser após hoje (".date("d/m/Y").").");
				}else{
					$motorista = mysqli_fetch_assoc(query(
						"SELECT enti_tx_admissao FROM entidade"
							." WHERE enti_tx_status = 'ativo'"
								." AND enti_nb_empresa = {$_POST["busca_empresa"]}"
								." AND enti_nb_id = {$_POST["busca_motorista"]}"
							." LIMIT 1;"
					));
	
					if(empty($motorista)){
						$_POST["errorFields"][] = "busca_motorista";
						throw new Exception("Este funcionário não pertence a esta empresa.");
					}
				}

				//Conferir se a data de início da pesquisa está antes do cadastro do motorista{
					if(!empty($motorista)){
						$dataInicio = new DateTime($_POST["busca_periodo"][0]);
						$data_cadastro = new DateTime($motorista["enti_tx_admissao"]);
						if($dataInicio->format("Y-m") < $data_cadastro->format("Y-m")){
							$_POST["errorFields"][] = "busca_periodo";
							throw new Exception("O mês inicial deve ser posterior ou igual ao mês de admissão do funcionário (".$data_cadastro->format("m/Y").").");
						}
					}
				//}
			}catch(Exception $error){
				set_status("ERRO: ".$error->getMessage());
				unset($_POST["acao"]);
			}
		//}

		index();
		exit;
	}

	function index(){
		cabecalho(empty($_POST["title"])? "Buscar Espelho de Ponto": $_POST["title"]);

		echo "<style>";
		include "css/espelho_ponto.css";
		echo "</style>";

		//CAMPOS DE CONSULTA{
			$condBuscaMotorista = "AND enti_tx_status = 'ativo'";
			$condBuscaEmpresa = "AND empr_tx_status = 'ativo'";

			if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])){
				[$_POST["busca_motorista"], $_POST["busca_empresa"]] = [$_SESSION["user_nb_entidade"], $_SESSION["user_nb_empresa"]];
				$condBuscaMotorista .= " AND enti_nb_id = '".$_SESSION["user_nb_entidade"]."'";
			}

			if(!empty($_SESSION["user_nb_empresa"]) && $_SESSION["user_tx_nivel"] != "Administrador" && $_SESSION["user_tx_nivel"] != "Super Administrador"){
				$condBuscaEmpresa .= " AND enti_nb_empresa = ".$_SESSION["user_nb_empresa"];
			}

			if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])){
				$nomeEmpresa = mysqli_fetch_assoc(query("SELECT empr_tx_nome FROM empresa WHERE empr_nb_id = ".$_SESSION["user_nb_empresa"]));
				$searchFields = [
					texto("Empresa*", $nomeEmpresa["empr_tx_nome"], 3),
					texto("Funcionário*", $_SESSION["user_tx_nome"], 3),
				];
			}else{
				$_POST["busca_empresa"] = $_POST["busca_empresa"]?? $_SESSION["user_nb_empresa"];
				$searchFields = [
					combo_net("Empresa*", "busca_empresa", $_POST["busca_empresa"], 3, "empresa", "onchange=selecionaMotorista(this.value) ", $condBuscaEmpresa),
					combo_net(
						"Funcionário*",
						"busca_motorista",
						(!empty($_POST["busca_motorista"])? $_POST["busca_motorista"]: ""),
						4, 
						"entidade JOIN empresa ON enti_nb_empresa = empr_nb_id", 
						"", 
						(!empty($_POST["busca_empresa"])?" AND enti_nb_empresa = {$_POST["busca_empresa"]}":"")." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário') {$condBuscaEmpresa} {$condBuscaMotorista}",
						"enti_tx_matricula"
					)
				];
			}

			$searchFields[] = campo(
				"Período", "busca_periodo",
				(!empty($_POST["busca_periodo"])? $_POST["busca_periodo"]: [date("Y-m-01"), date("Y-m-d")]),
				2,
				"MASCARA_PERIODO"
			);
		//}

		//BOTOES{
			$b = [
				botao("Buscar", "buscarEspelho()", "", "", "", "", "btn btn-success"),
			];
			if(!in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])){
				$b[] = botao("Cadastrar Abono", "redirParaAbono", "acaoPrevia", $_POST["acao"]??"", "btn btn-secondary");
			}
			if(!empty($_POST["acao"]) && $_POST["acao"] == "buscarEspelho()"){
				$b[] = "<button class='btn default' type='button' onclick='imprimir(this)'>Imprimir</button>";
			}
		//}
		
		echo abre_form();
		echo linha_form($searchFields);
		echo fecha_form($b);
		// if(!in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])){
		// 	echo botao("Cadastrar Abono", "redirParaAbono", "", "", "btn btn-secondary");
		// }
		// if(!empty($_POST["acao"]) && $_POST["acao"] == "buscarEspelho()"){
		// 	echo "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";
		// }


		$opt = "";
		//Buscar Espelho{
			if(!empty($_POST["acao"]) && $_POST["acao"] == "buscarEspelho()"){
				echo   
					"<div style='display:none' id='tituloRelatorio'>
						<h1>Espelho de Ponto</h1>
						<img id='logo' style='width: 150px' src='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
					</div>"
				;

				// Converte as datas para objetos DateTime
				[$startDate, $endDate] = [new DateTime($_POST["busca_periodo"][0]), new DateTime($_POST["busca_periodo"][1]." 23:59:59")];
				$rows = [];
				
				$motorista = mysqli_fetch_assoc(query(
					"SELECT * FROM entidade
					 LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
					 LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
					 LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
					 WHERE enti_tx_status = 'ativo'
						 AND enti_nb_id = '{$_POST["busca_motorista"]}'
					 LIMIT 1;"
				));
				
				//Conferir se há dias do mês já endossados{
					$endossoMes = montarEndossoMes($startDate, $motorista);
					
					if(!empty($endossoMes)){
						$diasEndossados = 0;
						foreach($endossoMes["endo_tx_pontos"] as $row){
							$day = DateTime::createFromFormat("d/m/Y", $row["data"]);
							if($day >= $startDate && $day <= $endDate){
								$diasEndossados++;
								$rows[] = $row;
							}
						}
						if($diasEndossados > 0){
							$startDate->modify("+{$diasEndossados} day");
						}
					}
				//}

				
				// Loop for para percorrer as datas
				$descFaltasNaoJustificadas = "00:00";
				$qtdDiasNaoJustificados = 0;
				for ($date = $startDate; $date <= $endDate; $date->modify("+1 day")){
					$aDetalhado = diaDetalhePonto($motorista, $date->format("Y-m-d"));
					/* Descomentar ao conseguir adaptar a lógica da página de nao_conformidade para espelho_ponto
						if(!empty($_POST["naoConformidade"])){
							$rowString = implode(", ", array_values($aDetalhado));
							$qtdErros = (
									substr_count($rowString, "fa-warning") 																				//Conta todos os triângulos, pois todos os triângulos são alertas de não conformidade.
									+((is_int(strpos($rowString, "fa-info-circle")))*(substr_count($rowString, "color:red;") + substr_count($rowString, "color:orange;")))	//Conta os círculos que sejam vermelhos ou laranjas.
								)
								*!(is_int(strpos($rowString, "Batida início de jornada não registrada!")) && is_int(strpos($rowString, "Abono: ")))
							;
						
							if($qtdErros == 0){
								$keyPrimColunaTotal = array_search("diffRefeicao", array_keys($aDetalhado));
								for($f2 = $keyPrimColunaTotal; $f2 < count($aDetalhado); $f2++){
									$totalResumo[$f2-$keyPrimColunaTotal] = operarHorarios([$totalResumo[$f2-$keyPrimColunaTotal], strip_tags(array_values($aDetalhado)[$f2])], "-");
								}
								continue;
							}
						}


						$row = array_values(array_merge([verificaTolerancia($aDetalhado["diffSaldo"], $date->format("Y-m-d"), $motorista["enti_nb_id"])], $aDetalhado));
						for($f = 0; $f < sizeof($row)-1; $f++){
							if(in_array($f, [3, 4, 5, 6, 12])){//Se for das colunas de início de jornada, refeição ou "Jornada Prevista", não apaga
								continue;
							}
							if($row[$f] == "00:00"){
								$row[$f] = "";
							}
						}
						$rows[] = $row;
					//*/

					$colunasAManterZeros = ["inicioJornada", "inicioRefeicao", "fimRefeicao", "fimJornada", "jornadaPrevista", "diffSaldo"];
					foreach($aDetalhado as $key => &$value){
						if(in_array($key, $colunasAManterZeros)){//Se for das colunas de início de jornada, refeição ou "Jornada Prevista", mantém os valores zerados.
							continue;
						}
						if($value == "00:00"){
							$value = "";
						}
					}
					
					$row = array_merge([verificaTolerancia($aDetalhado["diffSaldo"], $date->format("Y-m-d"), $motorista["enti_nb_id"])], $aDetalhado);
					if(strpos($row["inicioJornada"], "Batida início de jornada não registrada!") !== false){
						$descFaltasNaoJustificadas = operarHorarios([$descFaltasNaoJustificadas, $row["jornadaPrevista"]], "+");
						$qtdDiasNaoJustificados++;
					}

					$rows[] = $row;
				}

				$totalResumo = setTotalResumo(array_slice(array_keys($rows[0]), 7));
				
				somarTotais($totalResumo, $rows);


				$mensagemParametro = montarMensagemParametro($motorista);


				$ultimoEndosso = mysqli_fetch_assoc(query(
					"SELECT endo_tx_filename FROM endosso"
						." WHERE endo_tx_status = 'ativo'"
							." AND endo_nb_entidade = '".$motorista["enti_nb_id"]."'"
							." AND endo_tx_ate < '".$_POST["busca_periodo"][0]."'"
						." ORDER BY endo_tx_ate DESC"
						." LIMIT 1;"
				));
				
				
				$saldoAnterior = "00:00";
				if(!empty($ultimoEndosso) && file_exists("{$_SERVER["DOCUMENT_ROOT"]}{$_ENV["APP_PATH"]}{$_ENV["CONTEX_PATH"]}/arquivos/endosso/{$ultimoEndosso["endo_tx_filename"]}.csv")){
					$ultimoEndosso = lerEndossoCSV($ultimoEndosso["endo_tx_filename"]);
					$saldoAnterior = $ultimoEndosso["totalResumo"]["saldoFinal"]?? "--:--";
				}elseif(!empty($motorista["enti_tx_banco"])){
					$saldoAnterior = $motorista["enti_tx_banco"];
					$saldoAnterior = ($saldoAnterior == "00:00" && strlen($saldoAnterior) > 5)? substr($saldoAnterior, 1): $saldoAnterior;
				}

				$saldoBruto = $totalResumo["diffSaldo"];
				$saldoBruto = operarHorarios([$saldoAnterior, $totalResumo["diffSaldo"]], "+");

				$saldosMotorista = "SALDOS: <br>
					<div class='table-responsive' style='display: flex; justify-content: space-between; align-items: center;'>
						<table class='table w-auto text-xsmall bold table-bordered table-striped table-condensed flip-content table-hover compact' id='saldo'>
							<thead>
								<tr>
									<th>Anterior:</th>
									<th>Período:</th>
									<th>Bruto:</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>".($saldoAnterior?? "--:--")."</td>
									<td>".($totalResumo["diffSaldo"]?? "--:--")."</td>
									<td>".($saldoBruto?? "--:--")."</td>
								</tr>
							</tbody>
						</table>
						<div style='font-weight: 600;'>
							".(($motorista["para_tx_descFaltas"] == "sim" && $descFaltasNaoJustificadas != "00:00")? "Serão descontadas {$descFaltasNaoJustificadas} horas por {$qtdDiasNaoJustificados} faltas não justificadas.":"")."
						</div>
					</div>"
				;
						
				$periodoPesquisa = "De ".date("d/m/Y", strtotime($_POST["busca_periodo"][0]))." até ".date("d/m/Y", strtotime($_POST["busca_periodo"][1]));
			
				$cabecalho = [
					"", "DATA", "<div style='margin:11px'>DIA</div>", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
					"REFEIÇÃO"/*, ESPERA*/, "DESCANSO"/*, "REPOUSO"*/, "JORNADA", 
					"JORNADA PREVISTA", "JORNADA EFETIVA"/*, "MDC"*/, "INTERSTÍCIO", "H.E. {$motorista["enti_tx_percHESemanal"]}%", "H.E. {$motorista["enti_tx_percHEEx"]}%",
					"ADICIONAL NOT."/*, "ESPERA INDENIZADA"*/, "SALDO DIÁRIO(**)"
				];

				if(in_array($motorista["enti_tx_ocupacao"], ["Ajudante", "Motorista"])){
					$cabecalho = array_merge(
						array_slice($cabecalho, 0, 8), 
						["ESPERA"], 
						array_slice($cabecalho, 8, 1), 
						["REPOUSO"], 
						array_slice($cabecalho, 9, 3), 
						["MDC"], 
						array_slice($cabecalho, 12, 4), 
						["ESPERA INDENIZADA"], 
						array_slice($cabecalho, 16, count($cabecalho))
					);
				}

				unset(
					$totalResumo["saldoAnterior"],
					$totalResumo["saldoBruto"],
					$totalResumo["he50APagar"],
					$totalResumo["he100APagar"],
					$totalResumo["saldoFinal"],
					$totalResumo["horas_descontadas"],
					$totalResumo["desconto_manual"],
					$totalResumo["desconto_faltas_nao_justificadas"]
				);
				$rows[] = array_values(array_merge(["", "", "", "", "", "", "<b>TOTAL</b>"], $totalResumo));

				echo abre_form(
					"<table class='table w-auto text-xsmall bold table-bordered table-striped table-condensed flip-content table-hover compact espelho-cabecalho-info'>
						<thead>
							<tr>
								<th>Empresa</th>
								<th>Funcionário</th>
								<th>Parâmetro</th>
							</tr>
						<tbody>
							<tr>
								<td>{$motorista["empr_tx_nome"]}</td>
								<td>[{$motorista["enti_tx_matricula"]}] {$motorista["enti_tx_nome"]}</td>
								<td>{$mensagemParametro}</td>
						</tbody>
					</table>
					{$periodoPesquisa}<br>
					{$saldosMotorista}"
				);
				echo montarTabelaPonto($cabecalho, $rows);
				echo fecha_form();

				unset($_POST["errorFields"]);

				
				$params = array_merge($_POST, [
					"acao" => "index",
					"acaoPrevia" => $_POST["acao"],
					"idMotorista" => null,
					"data" => null,
					"HTTP_REFERER" => (!empty($_POST["HTTP_REFERER"])? $_POST["HTTP_REFERER"]: $_SERVER["REQUEST_URI"])
				]);

				echo criarHiddenForm(
					"form_ajuste_ponto",
					array_keys($params),
					array_values($params),
					"ajuste_ponto.php"
				);
				unset($params);
			}
		//}
		
		echo carregarJS($opt);
		rodape();
	}

	function carregarJS($opt): string{

		
		$select2URL = 
			"{$_ENV["URL_BASE"]}{$_ENV["APP_PATH"]}/contex20/select2.php"
			."?path={$_ENV["APP_PATH"]}{$_ENV["CONTEX_PATH"]}"
			."&tabela=entidade"
			."&colunas=enti_tx_matricula"
			."&limite=15"
		;

		$logoEmpresa = mysqli_fetch_assoc(query(
            "SELECT empr_tx_logo FROM empresa
                WHERE empr_tx_status = 'ativo'
                    AND empr_nb_id = '{$_POST["busca_empresa"]}'
				LIMIT 1;"
			))["empr_tx_logo"];

		return 
			"<script>
				function ajustarPonto(idMotorista, data){
					document.form_ajuste_ponto.idMotorista.value = idMotorista;
					document.form_ajuste_ponto.data.value = data;
					document.form_ajuste_ponto.submit();
				}

				
				function selecionaMotorista(idEmpresa){
					let condicoes = '&condicoes='+encodeURI('enti_tx_ocupacao IN (\"Motorista\", \"Ajudante\", \"Funcionário\")');
					if(idEmpresa > 0){
						condicoes += encodeURI(' AND enti_nb_empresa = '+idEmpresa);
						$('.busca_motorista')[0].innerHTML = null;
					}

					// Verifique se o elemento está usando Select2 antes de destruí-lo
					if($('.busca_motorista').data('select2')){
						$('.busca_motorista').select2('destroy');
					}
					$.fn.select2.defaults.set('theme', 'bootstrap');
					$('.busca_motorista').select2({
						language: 'pt-BR',
						placeholder: 'Selecione um item',
						allowClear: true,
						ajax: {
							url: '{$select2URL}'+condicoes,
							dataType: 'json',
							delay: 250,
							processResults: function(data){
								return {
									results: data
								};
							},
							cache: true
						}
					});
				}

				function imprimir() {
					const alvo = document.querySelector('div > div.portlet-title');
					if (!alvo) {
						alert('Conteúdo para impressão não encontrado.');
						return;
					}

					const conteudo = alvo.closest('.portlet') || alvo.parentElement;
					const cloneConteudo = conteudo.cloneNode(true);

					// Guarda a data/hora atual
					const dataAtual = new Date().toLocaleString();

					// Cabeçalho para a impressão
					const cabecalhoHTML = `
						<header id='print-header'>
							<img src='./imagens/logo_topo_cliente.png' alt='Logo Esquerda'>
							<h1>Espelho de Ponto</h1>
							<img src='./$logoEmpresa' alt='Logo Direita'>
						</header>`;

					// Rodapé para a impressão
					const rodapeHTML = `
						<footer id='print-footer'>
							<div><strong>TECHPS®</strong></div>
							<div><em>Gerado em: \${dataAtual}</em></div>
						</footer>`;

					// Abre janela de impressão
					const janela = window.open('', '_blank');
					janela.document.write(`
						<html>
						<head>
							<title>Impressão - Espelho de Ponto</title>
							<meta charset='utf-8'>
							<link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css'>
							<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>
							<link rel='stylesheet' href='./css/impressao_espelho.css'>
						</head>
						<body>
							\${cabecalhoHTML}
							
							<main class='conteudo-impressao'>
								\${cloneConteudo.outerHTML}
							</main>

							\${rodapeHTML}
							
							<script>
								// Executa após o conteúdo ser carregado na nova janela
								window.onload = function() {
									window.print();
								};

								// Fecha a aba quando o evento 'afterprint' é disparado
								window.addEventListener('afterprint', () => {
									window.close();
								});
							<\\/script>
						</body>
						</html>
					`);
					janela.document.close();
				}
			</script>"
		;
	}
