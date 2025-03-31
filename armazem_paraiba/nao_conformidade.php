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
				}
			//}
		}

		$_POST["counts"] = [
			"total" => 0,								//$countEndosso
			"naoConformidade" => 0,						//$countNaoConformidade
			"verificados" => 0,							//countVerificados
			"endossados" => ["sim" => 0, "nao" => 0],	//countEndossados e $countNaoEndossados
		];

		if(empty($_POST["busca_empresa"])){
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
						if($prevEndossoMes != $endossoMes){
							if(!empty($endossoMes)){
								$diasEndossados = 0;
								foreach($endossoMes["endo_tx_pontos"] as $row){
									$day = DateTime::createFromFormat("d/m/Y", $row[1]);
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
						}
					//}
					
					$aDetalhado = diaDetalhePonto($motorista, $date->format("Y-m-d"));
					if(!empty($endossoMes) && $prevEndossoMes != $endossoMes){
						foreach($totalResumo as $key => $value){
							$totalResumo[$key] = operarHorarios([$value, $endossoMes["totalResumo"][$key]], "+");
						}
					}
					$prevEndossoMes = $endossoMes;

					$row = array_values(array_merge([verificaTolerancia($aDetalhado["diffSaldo"], $date->format("Y-m-d"), $motorista["enti_nb_id"])], $aDetalhado));
					for($f = 0; $f < sizeof($row)-1; $f++){
						if($f == 13){//Se for da coluna "Jornada Prevista", não apaga
							continue;
						}
						if($row[$f] == "00:00"){
							$row[$f] = "";
						}
					}
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
							AND endo_tx_matricula = '{$motorista["enti_tx_matricula"]}'
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
				$dataCicloProx = strtotime($motorista["para_tx_dataCadastro"]);
				if($dataCicloProx !== false){
					while(!empty($aEndosso["endo_tx_ate"]) && $dataCicloProx < strtotime($aEndosso["endo_tx_ate"])){
						$dataCicloProx += intval($motorista["para_nb_qDias"])*60*60*24;
					}
				}
				$saldoAnterior = mysqli_fetch_assoc(query(
					"SELECT endo_tx_saldo FROM endosso
						WHERE endo_tx_matricula = '{$motorista["enti_tx_matricula"]}'
							AND endo_tx_ate < '{$_POST["busca_data"]}'
							AND endo_tx_status = 'ativo'
						ORDER BY endo_tx_ate DESC
						LIMIT 1;"
				));
				
				if(isset($saldoAnterior["endo_tx_saldo"])){
					$saldoAnterior = $saldoAnterior["endo_tx_saldo"];
				}elseif(!empty($motorista["enti_tx_banco"])){
					$saldoAnterior = $motorista["enti_tx_banco"];
					$saldoAnterior = $saldoAnterior[0] == "0" && strlen($saldoAnterior) > 5? substr($saldoAnterior, 1): $saldoAnterior;
				}else{
					$saldoAnterior = "--:--";
				}
				$saldoFinal = "--:--";
				if($saldoAnterior != "--:--"){
					$saldoFinal = somarHorarios([$saldoAnterior, $totalResumo["diffSaldo"]]);
				}

				$cab = [
					"", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
					"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO DIÁRIO / SEMANAL", "H.E. ".$motorista["enti_tx_percHESemanal"]."%", "H.E. ".$motorista["enti_tx_percHEEx"]."%",
					"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO(**)"
				];
		

				$saldosMotorista = 
					"Saldos:
					<div class='table-responsive'>
						<table class='table w-auto text-xsmall bold table-bordered table-striped table-condensed flip-content table-hover compact' id='saldo'>
							<thead><tr>
								<th>Anterior:</th>
								<th>Período:</th>
								<th>Final:</th>
							</thead></tr>
							<tbody>
								<tr>
								<td>".$saldoAnterior."</td>
								<td>".$totalResumo["diffSaldo"]."</td>
								<td>".$saldoFinal."</td>
								</tr>
							</tbody>
							</table>
					</div>"
				;

				$tolerancia = intval($motorista["para_tx_tolerancia"]);
				$saldoColIndex = 20;
				
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
					if(is_int(strpos($rows[$f][3], "Batida início de jornada não registrada!")) && is_int(strpos($rows[$f][12], "Abono: "))){ //Se tiver um erro de início de jornada E tiver algum abono
						$qtdErros = 0;
					}
					if($qtdErros == 0){
						$f2 = 7;
						foreach($totalResumo as &$total){
							$total = operarHorarios([$total, strip_tags($rows[$f][$f2])], "-");
							$f2++;
						}
						$rows = remFromArray($rows, $f);
						if(empty($rows)){
							break;
						}
						$f--;
						continue;
					}
					$_POST["counts"]["naoConformidade"] += $qtdErros;

					if(empty($rows[$f][$saldoColIndex])){
						$rows[$f][$saldoColIndex] = "00:00";	
					}

					$saldoStr = str_replace("<b>", "", $rows[$f][$saldoColIndex]);
					$saldoStr = explode(":", $saldoStr);
					$saldo = intval($saldoStr[0])*60 + ($saldoStr[0][0] == "-"? -1: 1)*intval($saldoStr[1]);

					if($saldo >= -($tolerancia) && $saldo <= $tolerancia){
						$rows[$f][$saldoColIndex] = "00:00";
					}
				}

				if(empty($rows)){
					continue;
				}

				$_POST["counts"]["total"]++;

				
				$rows[] = array_values(array_merge(["", "", "", "", "", "", "<b>TOTAL</b>"], $totalResumo));
				$tabelasPonto[] = createForm(
					"[".$motorista["enti_tx_matricula"]."] {$motorista["enti_tx_nome"]} | {$motorista["empr_tx_nome"]} {$infoEndosso} {$convencaoPadrao} <br><br>{$saldosMotorista}",
					12, 
					montarTabelaPonto($cab, $rows), 
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
				array_unshift($c, combo_net("Empresa:", "busca_empresa",   (!empty($_POST["busca_empresa"])?   $_POST["busca_empresa"]  : ""), 3, "empresa", "onchange=selecionaMotorista(this.value)", $extraEmpresa));
			}
		//}
		$botao_imprimir =
			"<button name='imprimir' class='btn default' type='button' onclick='imprimir()'>Imprimir</button>
			<script>
    			function imprimir() {
    			// Abrir a caixa de diálogo de impressãof
    			window.print();
    			}
			</script>";

		//BOTOES{
			$b = [
				botao("Buscar", "buscarEspelho", "", "", "", "","btn btn-success"),
				botao("Cadastrar Abono", "redirParaAbono", "acaoPrevia", $_POST["acao"], "", 1),
				$botao_imprimir
			];
		//}

		echo abre_form();
		echo linha_form($c);
		echo fecha_form($b, "<span id=dadosResumo><b>{$carregando}</b></span>");
		
		echo 
			"<div id='tituloRelatorio'>
				<h1>Não Conformidade</h1>
				<img id='logo' style='width: 150px' src='{$CONTEX["path"]}/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
			</div>
			<style>
				#tituloRelatorio{
					display: none;
				}
			</style>"
		;

		if(!empty($_POST["acao"]) && $_POST["acao"] == "buscarEspelho()"){
			echo implode("", $tabelasPonto);
			echo "<script>window.onload = function() {document.getElementById('dadosResumo').innerHTML = '<b>Funcionários: {$_POST["counts"]["total"]} | Não Conformidades: {$_POST["counts"]["naoConformidade"]}</b>';};</script>";
		}
		echo "<div class='printable'></div><style>";
		include "css/nao_conformidade.css";
		echo "</style>";

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