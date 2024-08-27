<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
	
	include "funcoes_ponto.php"; // conecta.php importado dentro de funcoes_ponto

	function montarEndossoMes(DateTime $dateMes, array $aMotorista): array{
		global $CONTEX;

		$month = intval($dateMes->format("m"));
		$year = intval($dateMes->format("Y"));
		
		$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$sqlEndossos = mysqli_fetch_all(
			query(
				"SELECT * FROM endosso 
					WHERE endo_tx_matricula = '".$aMotorista["enti_tx_matricula"]."'
						AND endo_tx_status = 'ativo'
						AND endo_tx_de >= '".sprintf("%04d-%02d-%02d", $year, $month, "01")."'
						AND endo_tx_ate <= '".sprintf("%04d-%02d-%02d", $year, $month, $daysInMonth)."'
					ORDER BY endo_tx_de ASC"
			),
			MYSQLI_ASSOC
		);

		$endossos = [];
		foreach($sqlEndossos as $endosso){
			$endossos[] = lerEndossoCSV($endosso["endo_tx_filename"]);
		}


		$endossoCompleto = [];

		if(count($endossos) > 0){
			$endossoCompleto = $endossos[0];
			if(empty($endossoCompleto["endo_tx_max50APagar"]) && !empty($endossoCompleto["endo_tx_horasApagar"])){
				$endossoCompleto["endo_tx_max50APagar"] = $endossoCompleto["endo_tx_horasApagar"];
			}
			for($f = 1; $f < count($endossos); $f++){
				if(empty($endossos[$f]["endo_tx_max50APagar"]) && !empty($endossos[$f]["endo_tx_horasApagar"])){
					$endossos[$f]["endo_tx_max50APagar"] = $endossos[$f]["endo_tx_horasApagar"];
				}
				if(empty($endossoCompleto["endo_tx_max50APagar"])){
					$endossoCompleto["endo_tx_max50APagar"] = "00:00";
				}
				$endossoCompleto["endo_tx_ate"] = $endossos[$f]["endo_tx_ate"];
				$endossoCompleto["endo_tx_pontos"] = array_merge($endossoCompleto["endo_tx_pontos"], $endossos[$f]["endo_tx_pontos"]);
				if($endossoCompleto["endo_tx_max50APagar"] != "00:00"){
					$endossoCompleto["endo_tx_max50APagar"] = operarHorarios([$endossoCompleto["endo_tx_max50APagar"], $endossos[$f]["endo_tx_max50APagar"]], "+");	
					if(is_int(strpos($endossoCompleto["endo_tx_max50APagar"], "-"))){
						$endossoCompleto["endo_tx_max50APagar"] = "00:00";
					}
				}
				foreach($endossos[$f]["totalResumo"] as $key => $value){
					if(in_array($key, ["saldoAnterior"])){
						continue;
					}
					$endossoCompleto["totalResumo"][$key] = operarHorarios([$endossoCompleto["totalResumo"][$key], $value], "+");
				}

				
				// $endossoCompleto["totalResumo"]["diffSaldo"] = $endossos[$f]["totalResumo"]["diffSaldo"];
				// $endossoCompleto["totalResumo"]["saldoBruto"] = $endossos[$f]["totalResumo"]["saldoBruto"];
			}
		}
		if(!empty($endossoCompleto)){
			$endossoCompleto["totalResumo"]["saldoBruto"] = operarHorarios([$endossoCompleto["totalResumo"]["saldoAnterior"], $endossoCompleto["totalResumo"]["diffSaldo"]], "+");
		}


		return $endossoCompleto;
	}

	function imprimir_relatorio(){
		global $totalResumo;

		if (!$_POST["idMotoristaEndossado"]) {
			$motorista = carregar("entidade", $_POST["busca_motorista"]);
			$_POST["idMotoristaEndossado"] = $motorista["enti_nb_id"];
		}

		if (empty($_POST["busca_data"]) || empty($_POST["busca_empresa"]) || empty($_POST["idMotoristaEndossado"])){
			set_status("ERRO: Insira data e motorista para gerar relatório.");
			index();
			exit;
		}

		$sqlMotorista = query(
			"SELECT * FROM entidade 
				WHERE enti_tx_ocupacao IN ('Motorista', 'Ajudante') 
					AND enti_nb_id IN (".$_POST["idMotoristaEndossado"].") 
					AND enti_nb_empresa = ".$_POST["busca_empresa"]." 
					AND enti_tx_status = 'ativo'
				ORDER BY enti_tx_nome"
		);
		
		$diasEndossados = 0;

		$date = new DateTime($_POST["busca_data"]);

		while ($aMotorista = carrega_array($sqlMotorista)) {
			//Pegando e formatando registros dos dias{
				
				$endossoCompleto = montarEndossoMes($date, $aMotorista);

				$totalResumo = $endossoCompleto["totalResumo"];

				$aPagar = calcularHorasAPagar($totalResumo["saldoBruto"], $totalResumo["he50"], $totalResumo["he100"], $endossoCompleto["endo_tx_max50APagar"]);

				$totalResumo["he50_aPagar"] = $aPagar[0];
				$totalResumo["he100_aPagar"] = $aPagar[1];
				$totalResumo["saldoFinal"] = operarHorarios([$totalResumo["saldoBruto"], $totalResumo["he50_aPagar"], $totalResumo["he100_aPagar"]], "-");

				for ($i = 0; $i < count($endossoCompleto["endo_tx_pontos"]); $i++) {
					$diasEndossados++;
					$aDetalhado = $endossoCompleto["endo_tx_pontos"][$i];
					array_shift($aDetalhado);
					array_splice($aDetalhado, 10, 1); //Retira a coluna de "Jornada" que está entre "Repouso" e "Jornada Prevista"
					$aDia[] = $aDetalhado;
				}
			//}

			//Inserir coluna de motivos{
				for($f = 0; $f < count($aDia); $f++){
					$data = explode("/", $aDia[$f][0]);
					$data = $data[2]."-".$data[1]."-".$data[0];
					
					$bdMotivos = mysqli_fetch_all(
						query(
							"SELECT * FROM ponto 
								JOIN motivo ON pont_nb_motivo = moti_nb_id
								WHERE pont_tx_matricula = '".$endossoCompleto["endo_tx_matricula"]."' 
									AND pont_tx_data LIKE '".$data."%'
									AND pont_tx_tipo IN (1,2,3,4)
									AND pont_tx_status = 'ativo'"
						), 
						MYSQLI_ASSOC
					);

					$bdAbonos = mysqli_fetch_all(
						query("SELECT motivo.moti_tx_nome FROM  abono
								JOIN motivo ON abon_nb_motivo = moti_nb_id
								WHERE abon_tx_matricula = '".$endossoCompleto["endo_tx_matricula"]."' 
								AND abon_tx_data LIKE '".$data."%' Limit 1"
							), 
						MYSQLI_ASSOC
					);

					$motivos = "";
					if(!empty($bdAbonos[0]["moti_tx_nome"])){
						$motivos .= $bdAbonos[0]["moti_tx_nome"]."<br>";
					}

					for($f2 = 0; $f2 < count($bdMotivos); $f2++){
						$legendas = [
							"" => "",
							"I" => "(I - Incluída Manualmente)",
							"P" => "(P - Pré-Assinalada)",
							"T" => "(T - Outras fontes de marcação)",
							"DSR" => "(DSR - Descanso Semanal Remunerado e Abono)"
						];
						$motivo = isset($legendas[$bdMotivos[$f2]["moti_tx_legenda"]])? $bdMotivos[$f2]["moti_tx_nome"]: "";
						if(!empty($motivo) && is_bool(strpos($motivos, $motivo))){
							$motivos .= $motivo."<br>";
						} 
					}
					
					array_splice($aDia[$f], 18, 0, $motivos); // inserir a coluna de motivo, no momento da implementação, estava na coluna 19
				}
			//}
			break; //Adaptar posteriormente para conseguir imprimir mais de um motorista??
		}


		//Montando variáveis que serão utilizadas em relatorio_espelho.php{
			global $CONTEX;

			$aEmpresa = carrega_array(
				query(
					"SELECT empresa.*, cidade.cida_tx_nome, cidade.cida_tx_uf FROM empresa JOIN cidade ON empresa.empr_nb_cidade = cidade.cida_nb_id".
					" WHERE empr_nb_id = ".$_POST["busca_empresa"]
				)
			);
			$enderecoEmpresa = implode(", ", array_filter([
				$aEmpresa["empr_tx_endereco"], 
				$aEmpresa["empr_tx_numero"], 
				$aEmpresa["empr_tx_bairro"], 
				$aEmpresa["empr_tx_complemento"], 
				$aEmpresa["empr_tx_referencia"]
			]));
		//}
		
		include "./relatorio_espelho.php";
		include "./csv_relatorio_espelho.php";
		exit;
	}

	function index(){
		global $totalResumo, $CONTEX;
		cabecalho("Buscar Endosso");

		$extra = "";
		$extraEmpresa = "";
		$extraEmpresaMotorista = "";
		if ($_SESSION["user_nb_empresa"] > 0 && is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
			$extraEmpresa = " AND empr_nb_id = '".$_SESSION["user_nb_empresa"]."'";
			$extraEmpresaMotorista = " AND enti_nb_empresa = '".$_SESSION["user_nb_empresa"]."'";
		}

		foreach(["busca_empresa", "busca_data", "busca_motorista", "busca_endossado"] as $campo){
			if(!empty($_GET[$campo])){
				$_POST[$campo] = $_GET[$campo];
			}
		}
		//Conferir se os campos do $_POST estão preenchidos{
			$error = false;
			$errorMsg = [];
			if(!empty($_POST["busca_empresa"])){
				$_POST["busca_empresa"] = (int)$_POST["busca_empresa"];
			}else{
				$_POST["busca_empresa"] = "";
				$error = true;
				$errorMsg[] = "Empresa";
			}

			if(!empty($_POST["busca_motorista"])){
				$extra = " AND enti_nb_id = ".$_POST["busca_motorista"];
			}
			
			$carregando = "";
			if(!empty($_POST["busca_data"]) && !empty($_POST["busca_empresa"])){
				$carregando = "Carregando...";
			}

			if(empty($_POST["busca_data"])){
				$_POST["busca_data"] = date("Y-m");
			}else{
				$date = DateTime::createFromFormat("Y-m-d H:i:s", $_POST["busca_data"]."-01 00:00:00");
				if($date > new DateTime()){
					$error = true;
					$errorMsg[] = "Data antes da atualidade";
				}
			}

			$extraMotorista = "";
			if(!empty($_POST["acao"])){
				if($error){
					set_status("ERRO: Insira os campos para pesquisar: ".implode(", ", $errorMsg).".");
					unset($_GET["acao"]);
				}

				$extraMotorista = " AND enti_nb_empresa = ".$_POST["busca_empresa"];
				if(!empty($_POST["busca_endossado"]) && !empty($_POST["busca_empresa"])){
					$extra .= " AND enti_nb_id";
					if($_POST["busca_endossado"] == "naoEndossado"){
						$extra .= " NOT";
					}
					$extra .= " IN (
							SELECT endo_nb_entidade FROM endosso, entidade" 
								." WHERE '".$_POST["busca_data"]."-01' BETWEEN endo_tx_de AND endo_tx_ate"
								." AND enti_nb_empresa = '".$_POST["busca_empresa"]."'"
								." AND endo_nb_entidade = enti_nb_id AND endo_tx_status = 'ativo'
						)"
					;
				}
			}
		//}

		//CAMPOS DE CONSULTA{
			$fields = [
				combo_net("Motorista", "busca_motorista", (!empty($_POST["busca_motorista"])? $_POST["busca_motorista"]: ""), 3, "entidade", "", " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante')".$extraMotorista.$extraEmpresaMotorista, "enti_tx_matricula"),
				campo_mes("Data",      "busca_data",      (!empty($_POST["busca_data"])?      $_POST["busca_data"]     : ""), 2),
				combo(	  "Endossado",	"busca_endossado", (!empty($_POST["busca_endossado"])? $_POST["busca_endossado"]: ""), 2, ["" => "", "endossado" => "Sim", "naoEndossado" => "Não"])
			];

			if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
				array_unshift($fields, combo_net("Empresa*", "busca_empresa", (!empty($_POST["busca_empresa"])? $_POST["busca_empresa"] : ""), 3, "empresa", "onchange=selecionaMotorista(this.value)", $extraEmpresa));
			}
		//}

		//BOTOES{
			$buttons = [
				botao("Buscar", "index", "", "", "", 1,"btn btn-info"),
				"<button name='acao' id='botaoContexCadastrar ImprimirRelatorio' value='impressao_relatorio' type='button' onload='disablePrintButton()' class='btn btn-default'>Imprimir Relatório</button>",
			];
		//}

		echo "<style>.row div {min-width: auto;}</style>";

		abre_form("Filtro de Busca");
		linha_form($fields);
		fecha_form($buttons, "<span id='dadosResumo' style='height:'><b>".$carregando."</b></span>");

		$cab = [
			"", "DATA", "<div style='margin:10px'>DIA</div>", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
			"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO DIÁRIO / SEMANAL", "HE 50%", "HE&nbsp;100%",
			"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO(**)"
		];

		$counts = [
			"total" => 0,								//$countEndosso
			"naoConformidade" => 0,						//$countNaoConformidade
			"verificados" => 0,							//countVerificados
			"endossados" => ["sim" => 0, "nao" => 0],	//countEndossados e $countNaoEndossados
		];
		//function buscar_endosso(){
			$motNaoEndossados = "MOTORISTA(S) NÃO ENDOSSADO(S): <br><br>";
			if(!empty($_POST["busca_data"]) && !empty($_POST["busca_empresa"]) && !empty($_GET["acao"])){
				$sqlMotorista = query(
					"SELECT * FROM entidade"
					." WHERE enti_tx_status = 'ativo'"
						." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante')"
						." AND enti_nb_empresa = ".$_POST["busca_empresa"]." ".$extra
					." ORDER BY enti_tx_nome;"
				);

				while($aMotorista = carrega_array($sqlMotorista, MYSQLI_ASSOC)){
					$counts["total"]++;
					if(empty($aMotorista["enti_tx_nome"]) || empty($aMotorista["enti_tx_matricula"])){
						continue;
					}

					//Pegando e formatando registros dos dias{
						$date = new DateTime($_POST["busca_data"]);

						$endossoCompleto = montarEndossoMes($date, $aMotorista);

						if(count($endossoCompleto) == 0){
							$counts["endossados"]["nao"]++;
							$motNaoEndossados .= "- [".$aMotorista["enti_tx_matricula"]."] ".$aMotorista["enti_tx_nome"]."<br>";
							continue;
						}else{
							$counts["naoConformidade"] += substr_count(json_encode($endossoCompleto["endo_tx_pontos"]), "fa-warning");
						}

						$totalResumo = $endossoCompleto["totalResumo"];

						for ($i = 0; $i < count($endossoCompleto["endo_tx_pontos"]); $i++) {
							$aDetalhado = $endossoCompleto["endo_tx_pontos"][$i];
							$aDia[] = $aDetalhado;
						}
						$totalResumoGrid = $totalResumo;
						unset($totalResumoGrid["saldoBruto"]);
						unset($totalResumoGrid["saldoAnterior"]);
						unset($totalResumoGrid["he50APagar"]);
						unset($totalResumoGrid["he100APagar"]);

						if(count($aDia) > 0){
							$aDia[] = array_values(array_merge(["", "", "", "", "", "", "<b>TOTAL</b>"], $totalResumoGrid));
						}
						unset($totalResumoGrid);
					//}

					if (count($aDia) > 0) {
						$counts["endossados"]["sim"]++;
						
						$dadosParametro = carrega_array(query(
							"SELECT para_tx_tolerancia, para_tx_dataCadastro, para_nb_qDias, para_tx_inicioAcordo FROM parametro 
								JOIN entidade ON para_nb_id = enti_nb_parametro 
								WHERE enti_nb_parametro = ".$aMotorista["enti_nb_parametro"]." 
								LIMIT 1;"
						));

						$dataCicloProx = strtotime($dadosParametro["para_tx_inicioAcordo"]." 00:00:00");
						$endoTimestamp = strtotime($endossoCompleto["endo_tx_ate"]." 00:00:00");
						while($dataCicloProx < $endoTimestamp && !empty($dadosParametro["para_nb_qDias"])){
							$dataCicloProx += $dadosParametro["para_nb_qDias"]*24*60*60;
						}
						$dataCicloProx = date("Y-m-d", $dataCicloProx);
						$dataCicloProx = explode("-", $dataCicloProx);
						$dataCicloProx = sprintf("%02d/%02d/%04d", $dataCicloProx[2], $dataCicloProx[1], $dataCicloProx[0]);

						$userCadastro = carregar("user", $endossoCompleto["endo_nb_userCadastro"]);
						$infoEndosso = " - Endossado por ".$userCadastro["user_tx_login"]." em ".data($endossoCompleto["endo_tx_dataCadastro"], 1);

						$aEmpresa = carregar("empresa", $aMotorista["enti_nb_empresa"]);

						/*
						if (!empty($aEmpresa["empr_nb_parametro"])) {
							$aParametro = carregar("parametro", $aEmpresa["empr_nb_parametro"]);
							if (
								$aParametro["para_tx_jornadaSemanal"] != $aMotorista["enti_tx_jornadaSemanal"] ||
								$aParametro["para_tx_jornadaSabado"] != $aMotorista["enti_tx_jornadaSabado"] ||
								$aParametro["para_tx_percentualHE"] != $aMotorista["enti_tx_percentualHE"] ||
								$aParametro["para_tx_percentualSabadoHE"] != $aMotorista["enti_tx_percentualSabadoHE"] ||
								$aParametro["para_nb_id"] != $aMotorista["enti_nb_parametro"]
							) {
								$parametroPadrao = "Convenção Não Padronizada, Semanal (".$aMotorista["enti_tx_jornadaSemanal"]."), Sábado (".$aMotorista["enti_tx_jornadaSabado"].")";
							} else {
								$parametroPadrao = "Convenção Padronizada: ".$aParametro["para_tx_nome"].", Semanal (".$aParametro["para_tx_jornadaSemanal"]."), Sábado (".$aParametro["para_tx_jornadaSabado"].")";
							}
						}
						*/

						$aPagar = "--:--";

						if(empty($endossoCompleto["endo_tx_max50APagar"])){
							$endossoCompleto["endo_tx_max50APagar"] = "00:00";
						}

						$aPagar = calcularHorasAPagar($totalResumo["saldoBruto"], $totalResumo["he50"], $totalResumo["he100"], $endossoCompleto["endo_tx_max50APagar"]);
						$aPagar = operarHorarios($aPagar, "+");
						$saldoFinal = operarHorarios([$totalResumo["saldoBruto"], $aPagar], "-");

						$saldosMotorista = "SALDOS: <br>
							<div class='table-responsive'>
								<table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact' id='saldo'>
									<thead>
										<tr>
											<th>Anterior:</th>
											<th>Período:</th>
											<th>Bruto:</th>
											<th>Pago:</th>
											<th>Final:</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>".$totalResumo["saldoAnterior"]."</td>
											<td>".$totalResumo["diffSaldo"]."</td>
											<td>".$totalResumo["saldoBruto"]."</td>
											<td>".$aPagar."</td>
											<td>".$saldoFinal."</td>
										</tr>
									</tbody>
								</table>
							</div>"
						;
						
						$aEmpresa = carregar("empresa", $aMotorista["enti_nb_empresa"]);
						$buttonInprimir = "";
						if (empty($_POST["busca_motorista"])){
							$buttonInprimir = "<button name='acao' id='botaoContexCadastrar ImprimirRelatorio_".$aMotorista["enti_tx_matricula"]."' value='impressao_relatorio' type='button' class='btn btn-default' 
							style='position: absolute; top: 20px; left: 420px;'>Imprimir Relatório</button>";
						}

						abre_form(
							$aEmpresa["empr_tx_nome"]."<br>"
							."[".$aMotorista["enti_tx_matricula"]."] ".$aMotorista["enti_tx_nome"]."<br>"
							."<br>"/*."$parametroPadrao<br><br>"*/
							.$saldosMotorista.
							$buttonInprimir
						);
						
						grid2($cab, $aDia);
						fecha_form();
						// ".$aMotorista['enti_nb_id']."
						// ".$aMotorista["enti_tx_matricula"]."
						echo "
						<form name='form_imprimir_relatorio_".$aMotorista["enti_tx_matricula"]."' method='post' target='_blank'>
							<input type='hidden' name='acao' value=''>
							<input type='hidden' name='idMotoristaEndossado' value=''>
							<input type='hidden' name='matriculaMotoristaEndossado' value=''>
						</form>
							<script>
								document.addEventListener('DOMContentLoaded', function() {
									const acao = 'imprimir_relatorio';
									const idMotorista = '".$aMotorista['enti_nb_id']."';
									const matricula = '".$aMotorista["enti_tx_matricula"]."';

									const form = document.forms['form_imprimir_relatorio_".$aMotorista["enti_tx_matricula"]."'];

									if (form) {
										// Atualiza os valores dos campos ocultos
										form.elements['acao'].value = acao;
										form.elements['idMotoristaEndossado'].value = idMotorista;
										form.elements['matriculaMotoristaEndossado'].value = matricula;

										// Adiciona o evento de clique ao botão para submeter o formulário
										document.getElementById('botaoContexCadastrar ImprimirRelatorio_".$aMotorista["enti_tx_matricula"]."').onclick = function() {
											form.submit();
										}
									} else {
										console.error('Formulário não encontrado.');
									}
								});
							</script>
							";

						$aSaldo[$aMotorista["enti_tx_matricula"]] = $totalResumo["diffSaldo"];
					}else{
						$counts["endossados"]["nao"]++;
						$motNaoEndossados .= "- [".$aMotorista["enti_tx_matricula"]."] ".$aMotorista["enti_tx_nome"]."<br>";
					}
	
					$totalResumo = ["diffRefeicao" => "00:00", "diffEspera" => "00:00", "diffDescanso" => "00:00", "diffRepouso" => "00:00", "diffJornada" => "00:00", "jornadaPrevista" => "00:00", "diffJornadaEfetiva" => "00:00", "maximoDirecaoContinua" => "", "intersticio" => "00:00", "he50" => "00:00", "he100" => "00:00", "adicionalNoturno" => "00:00", "esperaIndenizada" => "00:00", "diffSaldo" => "00:00"];

					unset($aDia);
				}
			}

			if($counts["endossados"]["nao"] > 0){
				abre_form($motNaoEndossados);
				fecha_form();
			}
			if(empty($_POST["busca_motorista"]) || (!empty($_POST["busca_motorista"]) && $counts["endossados"]["sim"] == 0)){
				echo 
					"<script>
						(function(){
							button = document.getElementById('botaoContexCadastrar ImprimirRelatorio');
							button.setAttribute('disabled', true);
							button.setAttribute('title', 'Pesquise um motorista endossado para efetuar a impressão do endosso.');
							return;
						})();
					</script>"
				;
			}
		//}
		echo "<div class='printable'></div>";

		rodape();

		$counts["message"] = "<b>Motoristas: ".$counts["total"]." | Verificados: ".$counts["verificados"]." | Não Conformidades: ".$counts["naoConformidade"]." | Endossados: ".$counts["endossados"]["sim"]." | Não Endossados: ".$counts["endossados"]["nao"]."</b>";

		$select2URL = 
			$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/select2.php"
			."?path=".$CONTEX["path"]
			."&tabela=entidade"
			."&extra_limite=15"
			."&extra_busca=enti_tx_matricula"
		; // Utilizado dentro de endosso_html.php

		include_once "html/endosso_html.php";
		echo 
			"<script>
				window.onload = function() {
					document.getElementById('dadosResumo').innerHTML = '".$counts["message"]."';
			
					document.getElementById('botaoContexCadastrar ImprimirRelatorio').onclick = function() {
						document.form_imprimir_relatorio.submit();
					}
				};
			</script>"
		;
	}
