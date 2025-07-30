<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
		
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/

	// $_POST["title"] = "Não Conformidades";
	// $_POST["naoConformidade"] = true;
	// include "espelho_ponto.php";
	// index();
	// exit;


	include "funcoes_ponto.php"; // conecta.php importado dentro de funcoes_ponto	

	function redirParaAbono(){
		global $CONTEX;

		if(empty($_POST['busca_motorista'])){
			header("Location: {$CONTEX["path"]}/cadastro_abono.php");
			exit;
		}
	
		if(!empty($_POST["busca_data"])){
			$_POST["busca_periodo"] = [
				$_POST["busca_data"]."-01",
				((new DateTime())->format("Y-m") == $_POST["busca_data"])? (new DateTime())->format("Y-m-d"): (DateTime::createFromFormat("Y-m", $_POST["busca_data"]))->format("Y-m-t")
			];
		}
		// Gerar o HTML do formulário e a página de redirecionamento
		$_POST["acao"] = "layout_abono";
		$_POST["HTTP_REFERER"] = "{$CONTEX["path"]}/nao_conformidade.php";

		echo criarHiddenForm(
			"forms_abono",
			array_keys($_POST),
			array_values($_POST),
			"{$CONTEX["path"]}/cadastro_abono.php"
		);
		echo "<script>document.forms_abono.submit();</script>";
		exit;
	}

	function buscarEspelho(){
		global $totalResumo, $tabelasPonto;

		if(!empty($_POST["acao"]) && $_POST["acao"] == "buscarEspelho()"){//Se estiver pesquisando
			//Conferir campos obrigatórios{
				$baseErrMsg = "ERRO: Campos obrigatórios não preenchidos: ";
				$errorMsg = $baseErrMsg;
				$camposObrig = [
					"busca_data" => "Data"
				];
				if(empty($_POST["busca_motorista"])){
					$camposObrig["busca_empresa"] = "Empresa";
				}
				foreach($camposObrig as $key => $value){
					if(empty($_POST[$key])){
						$_POST["errorFields"][] = $key;
						$errorMsg .= $value.", ";
					}
				}

				$_POST["busca_data"] = date("Y-m", strtotime($_POST["busca_data"]));

				if($errorMsg != $baseErrMsg){
					set_status(substr($errorMsg, 0, -2).".");
					unset($_POST["acao"]);
					index();
					exit;
				}
			//}
		}

		$_POST["counts"] = [
			"total" => 0,								//$countEndosso
			"naoConformidade" => 0,						//$countNaoConformidade
			"verificados" => 0,							//countVerificados
			"endossados" => ["sim" => 0, "nao" => 0],	//countEndossados e $countNaoEndossados
		];

		if(empty($_POST["busca_empresa"]) && !empty($_POST["busca_motorista"])){
			$_POST["busca_empresa"] = mysqli_fetch_assoc(query(
				"SELECT empr_nb_id FROM empresa 
					JOIN entidade ON empr_nb_id = enti_nb_empresa
					WHERE empr_tx_status = 'ativo'
						AND enti_nb_id = {$_POST["busca_motorista"]}
					LIMIT 1;"
			));
			if(empty($_POST["busca_empresa"])){
				set_status("ERRO: Empresa ativa não encontrada");
				index();
				exit;
			}else{
				$_POST["busca_empresa"] = $_POST["busca_empresa"]["empr_nb_id"];
			}
		}
		$monthDate = new DateTime($_POST["busca_data"]);

		$motoristas = mysqli_fetch_all(query(
			"SELECT * FROM entidade
				LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
				LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
				LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
				WHERE enti_tx_status = 'ativo'
					".((!empty($_POST["busca_motorista"]))? " AND enti_nb_id = {$_POST["busca_motorista"]}": "")."
					AND enti_nb_empresa = {$_POST["busca_empresa"]}
					AND (enti_tx_admissao < '".$monthDate->format("Y-m-t")."' OR enti_tx_admissao IS NULL)
				ORDER BY enti_tx_nome ASC;"
		), MYSQLI_ASSOC);


		foreach($motoristas as $motorista){
			// if(empty($motorista["enti_tx_nome"]) || empty($motorista["enti_tx_matricula"])){
			// 	continue;
			// }

			//Pegando e formatando registros dos dias{
				$rows = [];

				$dataAdmissao = new DateTime($motorista["enti_tx_admissao"]);

				if(empty($motorista["enti_nb_parametro"])){
					$motorista["enti_nb_parametro"] = $motorista["empr_nb_parametro"];
					$parametroEmpresa = mysqli_fetch_assoc(query(
						"SELECT * FROM parametro 
							WHERE para_nb_id = {$motorista["empr_nb_parametro"]} 
							LIMIT 1;"
					));
					$motorista = array_merge($motorista, $parametroEmpresa);
					$motorista["enti_tx_jornadaSabado"] = $motorista["para_tx_jornadaSabado"];
					$motorista["enti_tx_jornadaSemanal"] = $motorista["para_tx_jornadaSemanal"];
					$motorista["enti_tx_percHESemanal"] = $motorista["para_tx_percHESemanal"];
					$motorista["enti_tx_percHEEx"] = $motorista["para_tx_percHEEx"];
				}

				$prevEndossoMes = "";
				// for($date = new DateTime($monthDate->format("Y-m-1")); $date->format("Y-m-d") <= $monthDate->format("Y-m-t"); $date->modify("+1 day")){
				for($date = new DateTime($monthDate->format("Y-m-1")); $date->format("Y-m-d") <= $monthDate->format("Y-m-t"); $date->modify("+1 day")){
					if($monthDate->format("Y-m") < $dataAdmissao->format("Y-m")){
						continue;
					}
					if($date->format("Y-m-d") > date("Y-m-d")){
						break;
					}

					
					//Conferir se o dia já está endossado{
						$endossoMes = montarEndossoMes($date, $motorista);
						if($prevEndossoMes != $endossoMes && !empty($endossoMes)){

							$diasEndossados = 0;
							foreach($endossoMes["endo_tx_pontos"] as $row){
								$day = DateTime::createFromFormat("d/m/Y", $row["data"]);
								if($day > $date){
									$diasEndossados++;
									$rows[] = $row;
								}
							}
							if($diasEndossados > 0){
								$date->modify("+".($diasEndossados-1)." day");
								continue;
							}
						}
					//}
					
					$aDetalhado = diaDetalhePonto($motorista, $date->format("Y-m-d"));
					$prevEndossoMes = $endossoMes;
				
					$colunasAManterZeros = ["inicioJornada", "inicioRefeicao", "fimRefeicao", "fimJornada", "jornadaPrevista", "diffSaldo"];
					foreach($aDetalhado as $key => $value){
						if(in_array($key, $colunasAManterZeros)){//Se for das colunas de início de jornada, refeição ou "Jornada Prevista", mantém os valores zerados.
							continue;
						}
						if($aDetalhado[$key] == "00:00"){
							$aDetalhado[$key] = "";
						}
					}
					$row = array_merge([verificaTolerancia($aDetalhado["diffSaldo"], $date->format("Y-m-d"), $motorista["enti_nb_id"])], $aDetalhado);
					$rows[] = $row;
				}
			//}
			if (count($rows) > 0) {
				$aEndosso = mysqli_fetch_array(query(
					"SELECT user_tx_login, endo_tx_dataCadastro, endo_tx_ate 
						FROM endosso JOIN user ON endo_nb_userCadastro = user_nb_id 
						WHERE endo_tx_status = 'ativo' 
							AND '{$_POST["busca_data"]}' BETWEEN endo_tx_de AND endo_tx_ate
							AND endo_nb_entidade = '{$motorista["enti_nb_id"]}'
						LIMIT 1;"
				), MYSQLI_BOTH);
				if (is_array($aEndosso) && count($aEndosso) > 0) {
					$_POST["counts"]["endossados"]++;
					$infoEndosso = " - Endossado por ".$aEndosso["user_tx_login"]." em ".data($aEndosso["endo_tx_dataCadastro"], 1);
					$aIdMotoristaEndossado[] = $motorista["enti_nb_id"];
					$aMatriculaMotoristaEndossado[] = $motorista["enti_tx_matricula"];
				} else {
					$infoEndosso = "";
					$_POST["counts"]["endossados"]["nao"]++;
				}

				if ($motorista["empr_nb_parametro"] > 0) {
					if(
						[$motorista["enti_tx_jornadaSemanal"], $motorista["enti_tx_jornadaSabado"], $motorista["enti_tx_percHESemanal"], $motorista["enti_tx_percHEEx"]]
						== 
						[$motorista["para_tx_jornadaSemanal"], $motorista["para_tx_jornadaSabado"], $motorista["para_tx_percHESemanal"], $motorista["para_tx_percHEEx"]])
					{
						$convencaoPadrao = "| Convenção Padrão? Sim";
					}else{
						$convencaoPadrao = "| Convenção Padrão? Não";
					}
				}
				// $dataCicloProx = strtotime($motorista["para_tx_dataCadastro"]);
				// if($dataCicloProx !== false){
				// 	while(!empty($aEndosso["endo_tx_ate"]) && $dataCicloProx < strtotime($aEndosso["endo_tx_ate"])){
				// 		$dataCicloProx += intval($motorista["para_nb_qDias"])*60*60*24;
				// 	}
				// }

				$tolerancia = intval($motorista["para_tx_tolerancia"]);

				$totalResumo = [
					"diffRefeicao" => "00:00",
					"diffDescanso" => "00:00",
					"diffJornada" => "00:00",
					"jornadaPrevista" => "00:00",
					"diffJornadaEfetiva" => "00:00",
					"intersticio" => "00:00",
					"he50" => "00:00",
					"he100" => "00:00",
					"adicionalNoturno" => "00:00",
					"diffSaldo" => "00:00"
				];

				if(in_array($motorista["enti_tx_ocupacao"], ["Ajudante", "Motorista"])){
					$totalResumo = array_merge($totalResumo, [
						"diffEspera" => "00:00",
						"diffRepouso" => "00:00",
						"maximoDirecaoContinua" => "",
						"esperaIndenizada" => "00:00"
					]);
				}

				for($f = 0; $f < count($rows); $f++){
					$qtdErros = 0;
					foreach($rows[$f] as $key => $value){
						preg_match_all("/(?<=<)([^<|>])+(?=>)/", $value, $tags);
						array_walk_recursive($tags[0], function($tag, $key) use (&$qtdErros){
							$qtdErros += substr_count($tag, "fa-warning")*(substr_count($tag, "color:red;") || substr_count($tag, "color:orange;"))		//Conta todos os triângulos, pois todos os triângulos são alertas de não conformidade.
								+((is_int(strpos($tag, "fa-info-circle")))*(substr_count($tag, "color:red;") || substr_count($tag, "color:orange;")))	//Conta os círculos que sejam vermelhos ou laranjas.
							;
						}, $qtdErros);
					}
					if(is_int(strpos($rows[$f]["inicioJornada"], "Batida início de jornada não registrada!")) && is_int(strpos($rows[$f]["jornadaPrevista"], "Abono: "))){ //Se tiver um erro de início de jornada E tiver algum abono
						$qtdErros = 0;
					}
					if($qtdErros == 0){
						// $f2 = 7;
						// foreach($totalResumo as &$total){
						// 	if(empty($rows[$f][$f2])){
						// 		break;
						// 	}
						// 	$total = operarHorarios([$total, strip_tags($rows[$f][$f2])], "-");
						// 	$f2++;
						// }
						$rows = remFromArray($rows, $f);
						if(empty($rows)){
							break;
						}
						$f--;
						continue;
					}
					$_POST["counts"]["naoConformidade"] += $qtdErros;

					if(empty($rows[$f]["diffSaldo"])){
						$rows[$f]["diffSaldo"] = "00:00";
					}

					$saldoStr = str_replace("<b>", "", $rows[$f]["diffSaldo"]);
					$saldoStr = explode(":", $saldoStr);
					$saldo = intval($saldoStr[0])*60 + ($saldoStr[0][0] == "-"? -1: 1)*intval($saldoStr[1]);

					if($saldo >= -($tolerancia) && $saldo <= $tolerancia){
						$rows[$f]["diffSaldo"] = "00:00";
					}

					foreach($totalResumo as $key => $value){
						$totalResumo[$key] = operarHorarios([$totalResumo[$key], (!empty($row[$key])? $row[$key]: "00:00")], "+");
					}
				}

				if(empty($rows)){
					continue;
				}

				$_POST["counts"]["total"]++;

				//------------------------------------------------------------------------------------------------
				$ultimoEndosso = mysqli_fetch_assoc(query(
					"SELECT endo_tx_filename FROM endosso
						WHERE endo_nb_entidade = '{$motorista["enti_nb_id"]}'
							AND endo_tx_ate < '{$_POST["busca_data"]}'
							AND endo_tx_status = 'ativo'
						ORDER BY endo_tx_ate DESC
						LIMIT 1;"
				));

				if(!empty($ultimoEndosso)){
					$ultimoEndosso = lerEndossoCSV($ultimoEndosso["endo_tx_filename"]);
					$saldoAnterior = $ultimoEndosso["totalResumo"]["saldoAnterior"];
				}elseif(!empty($motorista["enti_tx_banco"])){
					$saldoAnterior = $motorista["enti_tx_banco"];
					$saldoAnterior = $saldoAnterior[0] == "0" && strlen($saldoAnterior) > 5? substr($saldoAnterior, 1): $saldoAnterior;
				}else{
					$saldoAnterior = "--:--";
				}
				$saldoFinal = "--:--";

				if($saldoAnterior != "--:--"){
					$saldoFinal = somarHorarios([$saldoAnterior, ($totalResumo["diffSaldo"]?? "00:00")]);
				}

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

				// $saldosMotorista = 
				// 	"Saldos:
				// 	<div class='table-responsive'>
				// 		<table class='table w-auto text-xsmall bold table-bordered table-striped table-condensed flip-content table-hover compact' id='saldo'>
				// 			<thead><tr>
				// 				<th>Anterior:</th>
				// 				<th>Período:</th>
				// 				<th>Final:</th>
				// 			</thead></tr>
				// 			<tbody>
				// 				<tr>
				// 				<td>".$saldoAnterior."</td>
				// 				<td>".$totalResumo["diffSaldo"]."</td>
				// 				<td>".$saldoFinal."</td>
				// 				</tr>
				// 			</tbody>
				// 			</table>
				// 	</div>"
				// ;
				$saldosMotorista = "";
				//------------------------------------------------------------------------------------------------

				if (empty($_POST["busca_motorista"])){
					$buttonImprimir = 
						"<button type='button' class='btn btn-default' onclick='imprimirIndividual(this)'>Imprimir Relatório</button>"
					; 
				}

				$cabecalhoForm = 
					"<div style='display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;'>
						<div>
							[{$motorista["enti_tx_matricula"]}] {$motorista["enti_tx_nome"]} | {$motorista["empr_tx_nome"]} {$infoEndosso} {$convencaoPadrao}
						</div>
						<div>
							 $buttonImprimir
						</div>
					</div>"
				;

				$rows[] = array_values(array_merge(["", "", "", "", "", "", "<b>TOTAL</b>"], $totalResumo));
				$tabelasPonto[] = createForm(
					"{$cabecalhoForm} <br><br>{$saldosMotorista}",
					12, 
					montarTabelaPonto($cabecalho, $rows), 
					[]
				);
				$aSaldo[$motorista["enti_tx_matricula"]] = $totalResumo["diffSaldo"];
			}

			$totalResumo = [
				"diffRefeicao" => "00:00",
				"diffEspera" => "00:00",
				"diffDescanso" => "00:00",
				"diffRepouso" => "00:00",
				"diffJornada" => "00:00",
				"jornadaPrevista" => "00:00",
				"diffJornadaEfetiva" => "00:00",
				"maximoDirecaoContinua" => "",
				"intersticio" => "00:00",
				"he50" => "00:00",
				"he100" => "00:00",
				"adicionalNoturno" => "00:00",
				"esperaIndenizada" => "00:00",
				"diffSaldo" => "00:00"
			];
			unset($rows);
		}

		index();
		exit;
	}

	function index(){
		global $CONTEX, $tabelasPonto;

		cabecalho("Não Conformidade");

		$extraEmpresa = "";
		$extraEmpresaMotorista = "";
		if ($_SESSION["user_nb_empresa"] > 0 && is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
			$extraEmpresa = " AND empr_nb_id = '".$_SESSION["user_nb_empresa"]."'";
			$extraEmpresaMotorista = " AND enti_nb_empresa = '".$_SESSION["user_nb_empresa"]."'";
		}

		$carregando = (!empty($_POST["acao"]) && $_POST["acao"] == "buscarEspelho()")? "Carregando...": "";
		$extraMotorista = "";
		
		if(empty($_POST["acao"]) && empty($_POST["busca_data"])){
			$_POST["busca_data"] = date("Y-m");
		}
		if(!empty($_POST["busca_empresa"])){
			$_POST["busca_empresa"] = (int)$_POST["busca_empresa"];
			$extraMotorista = " AND enti_nb_empresa = {$_POST["busca_empresa"]}";
		}else{
			$_POST["busca_empresa"] = $_SESSION["user_nb_empresa"];
		}

		if(empty($_POST["busca_motorista"])){
			$_POST["busca_motorista"] = "";
		}
		if(empty($_POST["busca_data"])){
			$_POST["busca_data"] = "";
		}

		//CAMPOS DE CONSULTA{
			$c = [
				combo_net(
					"Funcionário:", "busca_motorista", $_POST["busca_motorista"], 3, 
					"entidade", "", " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário') {$extraMotorista}{$extraEmpresaMotorista}", "enti_tx_matricula"
				),
				campo_mes("Data*:", "busca_data", $_POST["busca_data"], 2)
			];

			if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
				array_unshift($c, combo_net("Empresa*:", "busca_empresa",   (!empty($_POST["busca_empresa"])?   $_POST["busca_empresa"]  : ""), 3, "empresa", "onchange=selecionaMotorista(this.value)", $extraEmpresa));
			}
		//}
		$botao_imprimir =
			"<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>
			<script>
    			function imprimir() {
					// Abrir a caixa de diálogo de impressãof
					window.print();
    			}
			</script>";

		//BOTOES{
			$b = [
				botao("Buscar", "buscarEspelho", "", "", "", "","btn btn-success"),
				botao("Cadastrar Abono", "redirParaAbono", "acaoPrevia", $_POST["acao"]?? "", "", 1),
				$botao_imprimir
			];
		//}

		echo abre_form();
		echo linha_form($c);
		echo fecha_form($b, "<span id=dadosResumo><b>{$carregando}</b></span>");
		
		$logoEmpresa = mysqli_fetch_assoc(query(
            "SELECT empr_tx_logo FROM empresa
                    WHERE empr_tx_status = 'ativo'
                        AND empr_tx_Ehmatriz = 'sim'
                    LIMIT 1;"
        ))["empr_tx_logo"];
		
		echo 
			"<div id='tituloRelatorio'>
                    <img style='width: 190px; height: 40px;' src='./imagens/logo_topo_cliente.png' alt='Logo Empresa Esquerda'>
					<h1>Não Conformidades</h1>
                    <img style='width: 180px; height: 80px;' src='./$logoEmpresa' alt='Logo Empresa Direita'>
            </div>
			<style>
				#tituloRelatorio{
					display: none;
				}
			</style>"
		;

		if(!empty($_POST["acao"]) && $_POST["acao"] == "buscarEspelho()"){
			echo (!empty($tabelasPonto)? implode("", $tabelasPonto): "");
			echo "<script>window.onload = function() {document.getElementById('dadosResumo').innerHTML = '<b>Funcionários: {$_POST["counts"]["total"]} | Não Conformidades: {$_POST["counts"]["naoConformidade"]}</b>';};</script>";
		}
		echo "<div class='printable'></div><style>";
		include "css/nao_conformidade.css";
		echo "</style>";

		echo "
		<script>
			function imprimirIndividual(botao) {
				const bloco = botao.closest('.col-md-12');
				if (!bloco) {
					alert('Bloco não encontrado.');
					return;
				}

				const conteudo = bloco.cloneNode(true);

				// Remove botões de impressão internos
				conteudo.querySelectorAll('button[onclick*=\"imprimirIndividual\"]').forEach(btn => btn.remove());

				const cssImpressao = `
					@media print {
						body {
							margin: 1cm;
							transform: scale(1.0);
							transform-origin: top left;
						}

						@page {
							size: A4 landscape;
							margin: 1cm;
						}

						div.portlet-title > div > span > div.table-responsive {
							max-width: 50% !important;
						}

						#saldo {
							margin-top: 9px !important;
							text-align: center;
							width: 50% !important;
						}

						.portlet.light {
							padding: 12px 20px 15px !important;
						}

						.portlet {
							border-radius: 20px !important;
							margin-top: 0 !important;
							margin-bottom: 25px !important;
						}

						#tituloRelatorio {
							display: flex;
							align-items: center;
							justify-content: space-between;
							gap: 1em;
						}

						#tituloRelatorio h1 {
							margin: 0;
							font-size: 1.5em;
							flex-grow: 1;
							text-align: center;
						}

						#tituloRelatorio img {
							display: block;
						}

						body > div.scroll-to-top {
							display: none !important;
						}

						div:nth-child(12) > .portlet.light {
							display: none !important;
						}

						#logo {
							display: flex;
							position: absolute;
							top: 5px;
							right: 50px;
						}

						.portlet-body.form .table-responsive,
						.table-responsive {
							overflow: visible !important;
							max-width: none !important;
							max-height: none !important;
							width: auto !important;
						}

						table {
							width: 100% !important;
							table-layout: auto !important;
						}

						.portlet.light > .portlet-title {
							border-bottom: none;
							margin-bottom: 0px;
						}

						.caption {
							padding-top: 0px;
							margin-left: -50px !important;
							padding-bottom: 0px;
						}

						.portlet-body form{
							margin-left: -50px !important;
						}
						
						.color_red:before {
							color: red !important;
						}
						.color_green:before {
							color: green !important;
						}
						.color_blue:before {
							color: blue !important;
						}
						.color_greenLight:before {
							color: #00ff00 !important;
						}
						.color_orange:before {
							color: orange !important;
						}
					}
				`;

				const tituloRelatorio = `
					<div id='tituloRelatorio'>				
						<h1>Não Conformidades</h1>
            		</div>`;

				const janela = window.open('', '_blank');

				janela.document.write(`
					<html>
					<head>
						<title>Impressão</title>
						<meta charset='utf-8'>
						<link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css'>
						<style>
							body { font-family: Arial, sans-serif; margin: 20px; }
							table { width: 100%; border-collapse: collapse; }
							th, td { border: 1px solid #ccc; padding: 5px; font-size: 12px; }
							\${cssImpressao}
						</style>
					</head>
					<body>
						\${tituloRelatorio}
						\${conteudo.outerHTML}
					</body>
					</html>
				`);

				janela.document.close();

				janela.onload = function () {
					const imgs = janela.document.images;
					if (imgs.length === 0) {
						janela.print();
						janela.close();
						return;
					}

					let carregadas = 0;
					for (let img of imgs) {
						if (img.complete) {
							carregadas++;
						} else {
							img.onload = img.onerror = () => {
								carregadas++;
								if (carregadas === imgs.length) {
									janela.print();
									janela.close();
								}
							};
						}
					}

					// Caso todas já estejam carregadas
					if (carregadas === imgs.length) {
						janela.print();
						janela.close();
					}
				};
			}
		</script>

		";


		rodape();

		$select2URL = 
			$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/select2.php"
			."?path=".$CONTEX["path"]
			."&tabela=entidade"
			."&extra_limite=15"
			."&extra_busca=enti_tx_matricula"
		; // Utilizado dentro de endosso_html.php
		
		include "html/endosso_html.php";

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
	}
//*/