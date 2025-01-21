<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
	
	include "funcoes_ponto.php"; // conecta.php importado dentro de funcoes_ponto

	function imprimir_relatorio(){
		global $totalResumo;

		if (empty($_POST["busca_data"]) || empty($_POST["busca_empresa"])){
			$_POST["errorFields"][] = "busca_data";
			$_POST["errorFields"][] = "busca_empresa";
			set_status("ERRO: Insira data e empresa para gerar relatório.");
			index();
			exit;
		}

		//Montando variáveis que serão utilizadas em relatorio_espelho.php{
			global $CONTEX;

			$aEmpresa = mysqli_fetch_array(query(
				"SELECT empresa.*, cidade.cida_tx_nome, cidade.cida_tx_uf FROM empresa JOIN cidade ON empresa.empr_nb_cidade = cidade.cida_nb_id
					WHERE empr_nb_id = {$_POST["busca_empresa"]};"
			), MYSQLI_BOTH);
			$enderecoEmpresa = implode(", ", array_filter([
				$aEmpresa["empr_tx_endereco"], 
				$aEmpresa["empr_tx_numero"], 
				$aEmpresa["empr_tx_bairro"], 
				$aEmpresa["empr_tx_complemento"], 
				$aEmpresa["empr_tx_referencia"]
			]));
		//}

		$motoristas = mysqli_fetch_all(query(
			"SELECT entidade.*, endosso.*, parametro.para_tx_pagarHEExComPerNeg FROM entidade
				JOIN endosso ON enti_tx_matricula = endo_tx_matricula
				LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
				WHERE enti_tx_status = 'ativo' AND endo_tx_status = 'ativo'
					".(!empty($_POST["idMotoristaEndossado"])? "AND enti_nb_id = {$_POST["idMotoristaEndossado"]}": "")."
					AND enti_nb_empresa = {$_POST["busca_empresa"]}
					AND enti_tx_ocupacao IN ('Motorista', 'Ajudante','Funcionário')
					AND endo_tx_mes = '{$_POST["busca_data"]}-01'
				ORDER BY enti_tx_nome;"
		), MYSQLI_ASSOC);

		$qtdDiasEndossados = 0;

		$date = new DateTime($_POST["busca_data"]);

		foreach($motoristas as $motorista) {
			$aDia = [];
			$totalResumo = [];

			//Pegando e formatando registros dos dias{
				
				$endossoCompleto = montarEndossoMes($date, $motorista);

				$totalResumo = $endossoCompleto["totalResumo"];
				$max50APagar = $endossoCompleto["endo_tx_max50APagar"];
				if(empty($max50APagar)){
				    $max50APagar = "00:00";
				}

				$aPagar = calcularHorasAPagar($totalResumo["saldoBruto"], $totalResumo["he50"], $totalResumo["he100"], $max50APagar, ($motorista["para_tx_pagarHEExComPerNeg"]?? "nao"));

				$totalResumo["HESemanalAPagar"] = $aPagar[0];
				$totalResumo["HEExAPagar"] = $aPagar[1];
				$totalResumo["saldoFinal"] = operarHorarios([$totalResumo["saldoBruto"], $totalResumo["HESemanalAPagar"], $totalResumo["HEExAPagar"]], "-");

				for ($i = 0; $i < count($endossoCompleto["endo_tx_pontos"]); $i++) {
					$qtdDiasEndossados++;
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
			
			$botoes = ["<button id='btnCsv' onclick='downloadCSV(\"".$motorista["enti_nb_id"]."\", \"".$motorista["enti_tx_nome"]."\")'><img width='20' height='20' src='https://img.icons8.com/glyph-neue/64/FFFFFF/csv.png' alt='csv'/> Baixar CSV</button>"];
			if($motorista == $motoristas[count($motoristas)-1]){
				$botoes[] = "<br><br><br><button id='btnImprimir' class='btn default' type='button' onclick='imprimir()'><img width='20' height='20' src='https://img.icons8.com/android/24/FFFFFF/print.png' alt='print'/> Imprimir</button>";
			}
			include "./relatorio_espelho.php";
			include "./csv_relatorio_espelho.php";
			echo "<br><br><br><hr>";
		}
		
		exit;
	}

	function buscarEndosso(){
		global $totalResumo;

		$counts = [
			"total" => 0,								//$countEndosso
			"naoConformidade" => 0,						//$countNaoConformidade
			"verificados" => 0,							//countVerificados
			"endossados" => ["sim" => 0, "nao" => 0],	//countEndossados e $countNaoEndossados
		];
	
		$endossoHTML = "";
		$extra = "";

		foreach(["busca_empresa", "busca_data", "busca_motorista", "busca_endossado"] as $campo){
			if(!empty($_GET[$campo])){
				$_POST[$campo] = $_GET[$campo];
			}
		}

		//Conferir se os campos do $_POST estão preenchidos{
			if(empty($_POST["busca_data"])){
				$_POST["busca_data"] = date("Y-m");
			}
			$camposObrig = [
				"busca_empresa" => "Empresa",
				// "busca_motorista" => "Funcionário",
				"busca_data" => "Data"
			];
			if(empty($_POST["busca_empresa"]) && !empty($_POST["busca_motorista"])){
				$_POST["busca_empresa"] = mysqli_fetch_assoc(query("SELECT enti_nb_empresa FROM entidade WHERE enti_nb_id = ".$_POST["busca_motorista"].";"));
				$_POST["busca_empresa"] = (int)$_POST["busca_empresa"]["enti_nb_empresa"];
			}

			if(!empty($_POST["busca_motorista"])){
				$extra = " AND enti_nb_id = ".$_POST["busca_motorista"];
			}
			
			$errorMsg = conferirCamposObrig($camposObrig, $_POST);
			if(!empty($errorMsg)){
				set_status("ERRO: ".$errorMsg);
				unset($_POST["acao"]);
				index();
				exit;
			}
		//}

		$date = DateTime::createFromFormat("Y-m-d H:i:s", $_POST["busca_data"]."-01 00:00:00");
		if($date > new DateTime()){
			$_POST["errorFields"][] = "busca_data";
			set_status("ERRO: Não é possível pesquisar uma data futura.");
			unset($_POST["acao"]);
			index();
			exit;
		}
		
		$_POST["extraMotorista"] = " AND enti_nb_empresa = {$_POST["busca_empresa"]}";
		if(!empty($_POST["busca_endossado"]) && !empty($_POST["busca_empresa"])){
			$extra .= " AND enti_nb_id".(($_POST["busca_endossado"] == "naoEndossado")? " NOT": "")." IN (
					SELECT endo_nb_entidade FROM endosso, entidade
						WHERE endo_tx_status = 'ativo'
							AND endo_tx_mes = '{$_POST["busca_data"]}-01'
							AND enti_nb_empresa = '{$_POST["busca_empresa"]}'
							AND endo_nb_entidade = enti_nb_id
				)"
			;
		}

		$motNaoEndossados = "FUNCIONÁRIO(S) NÃO ENDOSSADO(S): <br><br>";
		
		$sqlMotorista = "SELECT entidade.*, parametro.para_tx_pagarHEExComPerNeg, parametro.para_tx_inicioAcordo, parametro.para_nb_qDias, parametro.para_nb_qDias FROM entidade"
				." LEFT JOIN parametro ON enti_nb_parametro = para_nb_id"
				." WHERE enti_tx_status = 'ativo'"
					." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')"
					." AND enti_nb_empresa = ".$_POST["busca_empresa"]." ".$extra
				." ORDER BY enti_tx_nome;";

		$motoristas = mysqli_fetch_all(query($sqlMotorista), MYSQLI_ASSOC);
		foreach($motoristas as $motorista){
			$counts["total"]++;
			if(empty($motorista["enti_tx_nome"]) || empty($motorista["enti_tx_matricula"])){
				continue;
			}

			//Pegando e formatando registros dos dias{
				$date = new DateTime($_POST["busca_data"]);

				$endossoCompleto = montarEndossoMes($date, $motorista);

				if(count($endossoCompleto) == 0){
					$counts["endossados"]["nao"]++;
					$motNaoEndossados .= "- [".$motorista["enti_tx_matricula"]."] ".$motorista["enti_tx_nome"]."<br>";
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
				unset($totalResumoGrid["saldoFinal"]);


				if(count($aDia) > 0){
					$aDia[] = array_values(array_merge(["", "", "", "", "", "", "<b>TOTAL</b>"], $totalResumoGrid));
				}
				unset($totalResumoGrid);
			//}

			if (count($aDia) > 0) {
				$counts["endossados"]["sim"]++;

				$dataCicloProx = strtotime($motorista["para_tx_inicioAcordo"]." 00:00:00");
				$endoTimestamp = strtotime($endossoCompleto["endo_tx_ate"]." 00:00:00");
				while($dataCicloProx < $endoTimestamp && !empty($motorista["para_nb_qDias"])){
					$dataCicloProx += $motorista["para_nb_qDias"]*24*60*60;
				}
				$dataCicloProx = date("Y-m-d", $dataCicloProx);
				$dataCicloProx = explode("-", $dataCicloProx);
				$dataCicloProx = sprintf("%02d/%02d/%04d", $dataCicloProx[2], $dataCicloProx[1], $dataCicloProx[0]);

				$userCadastro = carregar("user", $endossoCompleto["endo_nb_userCadastro"]);
				$infoEndosso = " - Endossado por ".$userCadastro["user_tx_login"]." em ".data($endossoCompleto["endo_tx_dataCadastro"], 1);

				$aEmpresa = carregar("empresa", $motorista["enti_nb_empresa"]);

				/*
				if (!empty($aEmpresa["empr_nb_parametro"])) {
					$aParametro = carregar("parametro", $aEmpresa["empr_nb_parametro"]);
					if (
						$aParametro["para_tx_jornadaSemanal"] != $motorista["enti_tx_jornadaSemanal"] ||
						$aParametro["para_tx_jornadaSabado"] != $motorista["enti_tx_jornadaSabado"] ||
						$aParametro["para_tx_percHESemanal"] != $motorista["enti_tx_percHESemanal"] ||
						$aParametro["para_tx_percHEEx"] != $motorista["enti_tx_percHEEx"] ||
						$aParametro["para_nb_id"] != $motorista["enti_nb_parametro"]
					) {
						$parametroPadrao = "Convenção Não Padronizada, Semanal (".$motorista["enti_tx_jornadaSemanal"]."), Sábado (".$motorista["enti_tx_jornadaSabado"].")";
					} else {
						$parametroPadrao = "Convenção Padronizada: ".$aParametro["para_tx_nome"].", Semanal (".$aParametro["para_tx_jornadaSemanal"]."), Sábado (".$aParametro["para_tx_jornadaSabado"].")";
					}
				}
				*/

				$aPagar = "--:--";

				if(empty($endossoCompleto["endo_tx_max50APagar"])){
					$endossoCompleto["endo_tx_max50APagar"] = "00:00";
				}

				$aPagar = calcularHorasAPagar($totalResumo["saldoBruto"], $totalResumo["he50"], $totalResumo["he100"], $endossoCompleto["endo_tx_max50APagar"], ($motorista["para_tx_pagarHEExComPerNeg"]?? "nao"));
				$aPagar = operarHorarios($aPagar, "+");
				$saldoFinal = operarHorarios([$totalResumo["saldoBruto"], $aPagar], "-");

				$saldosMotorista = "SALDOS: <br>
					<div class='table-responsive'>
						<table class='table w-auto text-xsmall bold table-bordered table-striped table-condensed flip-content table-hover compact' id='saldo'>
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
				
				$aEmpresa = carregar("empresa", $motorista["enti_nb_empresa"]);
				$buttonInprimir = "";
				if (empty($_POST["busca_motorista"])){
					$buttonInprimir = 
						"<button"
							." name='acao'"
							." id='botaoContexCadastrar ImprimirRelatorio_".$motorista["enti_tx_matricula"]."'"
							." value='impressao_relatorio'"
							." type='button'"
							." class='btn btn-default'"
							." style='position: absolute; top: 20px; left: 420px;'"
							// ." disabled"
						.">"
							."Imprimir Relatório"
						."</button>"
					; 
				}

				$endossoHTML .=  abre_form(
					$aEmpresa["empr_tx_nome"]."<br>"
					."[".$motorista["enti_tx_matricula"]."] ".$motorista["enti_tx_nome"]."<br>"
					."<br>"/*."$parametroPadrao<br><br>"*/
					.$saldosMotorista.
					$buttonInprimir
				);
				
				$cab = [
					"", "DATA", "<div style='margin:11px'>DIA</div>", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
					"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO DIÁRIO / SEMANAL", "H.E. ".$motorista["enti_tx_percHESemanal"]."%", "H.E. ".$motorista["enti_tx_percHEEx"]."%",
					"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO(**)"
				];

				$endossoHTML .=  montarTabelaPonto($cab, $aDia);
				$endossoHTML .=  fecha_form();

				$endossoHTML .=  
					"<form name='form_imprimir_relatorio_".$motorista["enti_tx_matricula"]."' method='post' target='_blank'>
						<input type='hidden' name='acao' value=''>
						<input type='hidden' name='busca_data' value='{$_POST["busca_data"]}'>
						<input type='hidden' name='busca_empresa' value='{$_POST["busca_empresa"]}'>
						<input type='hidden' name='idMotoristaEndossado' value=''>
						<input type='hidden' name='matriculaMotoristaEndossado' value=''>
					</form>
					<script>
						document.addEventListener('DOMContentLoaded', function() {
							const acao = 'imprimir_relatorio';
							const idMotorista = '".$motorista['enti_nb_id']."';
							const matricula = '".$motorista["enti_tx_matricula"]."';

							const form = document.forms['form_imprimir_relatorio_".$motorista["enti_tx_matricula"]."'];

							if (form) {
								// Atualiza os valores dos campos ocultos
								form.elements['acao'].value = acao;
								form.elements['idMotoristaEndossado'].value = idMotorista;
								form.elements['matriculaMotoristaEndossado'].value = matricula;

								// Adiciona o evento de clique ao botão para submeter o formulário
								document.getElementById('botaoContexCadastrar ImprimirRelatorio_".$motorista["enti_tx_matricula"]."').onclick = function() {
									form.submit();
								}
							} else {
								console.error('Formulário não encontrado.');
							}
						});
					</script>"
				;

				$aSaldo[$motorista["enti_tx_matricula"]] = $totalResumo["diffSaldo"];
			}else{
				$counts["endossados"]["nao"]++;
				$motNaoEndossados .= "- [".$motorista["enti_tx_matricula"]."] ".$motorista["enti_tx_nome"]."<br>";
			}

			$totalResumo = ["diffRefeicao" => "00:00", "diffEspera" => "00:00", "diffDescanso" => "00:00", "diffRepouso" => "00:00", "diffJornada" => "00:00", "jornadaPrevista" => "00:00", "diffJornadaEfetiva" => "00:00", "maximoDirecaoContinua" => "", "intersticio" => "00:00", "he50" => "00:00", "he100" => "00:00", "adicionalNoturno" => "00:00", "esperaIndenizada" => "00:00", "diffSaldo" => "00:00"];

			unset($aDia);
		}

		//Função legado, deve ser mantida para conseguir mostrar a mensagem de "dia endossado" em endossos anteriores.
		$endossoHTML .=  "<script>function ajusta_ponto(idMotorista, data, endossado = false){alert('Dia já endossado.');}</script>";

		if($counts["endossados"]["nao"] > 0){
			$endossoHTML .=  abre_form($motNaoEndossados);
			$endossoHTML .=  fecha_form();
		}
		if(!empty($_POST["busca_motorista"]) && $counts["endossados"]["sim"] == 0){
			$endossoHTML .=  
				"<script>
					button = document.getElementById('botaoContexCadastrar ImprimirRelatorio');
					button.setAttribute('disabled', true);
					button.setAttribute('title', 'Pesquise um funcionário endossado para efetuar a impressão do endosso.');

					btnsImprimir = document.getElementsByName('');
				</script>"
			;
		}

		index($counts, $endossoHTML);
		exit;
	}

	function index($counts = null, $endossoHTML = ""){

		cabecalho("Buscar Endosso");

		if(empty($_POST["busca_data"])){
			$_POST["busca_data"] = date("Y-m");
		}

		//CAMPOS DE CONSULTA{
			$extraEmpresa = "";
			$extraEmpresaMotorista = "";
			if(!empty($_SESSION["user_nb_empresa"]) && is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
				$extraEmpresa = " AND empr_nb_id = '{$_SESSION["user_nb_empresa"]}'";
				$extraEmpresaMotorista = " AND enti_nb_empresa = '{$_SESSION["user_nb_empresa"]}'";
			}

			$fields = [];
			if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
				$fields[] = combo_net("Empresa*", "busca_empresa", (!empty($_POST["busca_empresa"])? $_POST["busca_empresa"] : ""), 3, "empresa", "onchange=selecionaMotorista(this.value)", ($extraEmpresa?? ""));
			}
			$fields = array_merge($fields, [
				combo_net("Funcionário", "busca_motorista", (!empty($_POST["busca_motorista"])? $_POST["busca_motorista"]: ""), 3, "entidade", "", " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')".($_POST["extraMotorista"]?? "").($extraEmpresaMotorista?? ""), "enti_tx_matricula"),
				campo_mes("Data*",      "busca_data",      	(!empty($_POST["busca_data"])?      $_POST["busca_data"]     : ""), 2),
				combo(	  "Endossado",	"busca_endossado", 	(!empty($_POST["busca_endossado"])? $_POST["busca_endossado"]: ""), 2, ["endossado" => "Sim", "naoEndossado" => "Não"])
			]);
		//}

		//BOTOES{
			$buttons = [
				botao("Buscar", "buscarEndosso", "", "", "", 1,"btn btn-info"),
				"<button name='acao' id='botaoContexCadastrar ImprimirRelatorio' value='impressao_relatorio' type='button' onload='disablePrintButton()' class='btn btn-default'>Imprimir Relatório</button>",
			];
		//}

		echo "<style>.row div {min-width: auto;}</style>";

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons, "<span id='dadosResumo' style='height:'><b>".((!empty($_POST["busca_data"]) && !empty($_POST["busca_empresa"]))? "Carregando...": "")."</b></span>");

		//function buscar_endosso(){
			if(!empty($_POST["acao"]) && $_POST["acao"] == "buscarEndosso()"){
				echo $endossoHTML;
			}
		//}*/
		echo "<div class='printable'></div>";

		rodape();

		if(!empty($counts)){
			$counts["message"] = "<b>Funcionários: {$counts["total"]} | Verificados: {$counts["verificados"]} | Não Conformidades: {$counts["naoConformidade"]} | Endossados: {$counts["endossados"]["sim"]} | Não Endossados: {$counts["endossados"]["nao"]}</b>";
		}else{
			$counts["message"] = "";
		}

		$select2URL = 
			$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/select2.php"
			."?path=".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]
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
