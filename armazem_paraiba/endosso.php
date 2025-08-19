<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
	
	include "funcoes_ponto.php"; // conecta.php importado dentro de funcoes_ponto

	function imprimir_relatorio(){
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
			"SELECT DISTINCT enti_nb_id, entidade.*, parametro.para_tx_pagarHEExComPerNeg FROM entidade
				JOIN endosso ON enti_nb_id = endo_nb_entidade
				LEFT JOIN parametro ON enti_nb_parametro = para_nb_id
				WHERE enti_tx_status = 'ativo' AND endo_tx_status = 'ativo'
					".(!empty($_POST["busca_motorista"])? "AND enti_nb_id = {$_POST["busca_motorista"]}": "")."
					AND enti_nb_empresa = {$_POST["busca_empresa"]}
					AND enti_tx_ocupacao IN ('Motorista', 'Ajudante','Funcionário')
					AND endo_tx_mes = '{$_POST["busca_data"]}-01'
				ORDER BY enti_tx_nome;"
		), MYSQLI_ASSOC);

		$qtdDiasEndossados = 0;

		$date = new DateTime($_POST["busca_data"]);

		for($f = 0; $f < count($motoristas); $f++){
			$rows = [];

			//Pegando e formatando registros dos dias{
				
				$endossoCompleto = montarEndossoMes($date, $motoristas[$f]);
				$totalResumo = $endossoCompleto["totalResumo"];

				if(empty($totalResumo["HESemanalAPagar"])){
					$totalResumo["HESemanalAPagar"] = $totalResumo["he50APagar"];
				}
				if(empty($totalResumo["HEExAPagar"])){
					$totalResumo["HEExAPagar"] = $totalResumo["he100APagar"];
				}

				for ($f2 = 0; $f2 < count($endossoCompleto["endo_tx_pontos"]); $f2++) {
					$qtdDiasEndossados++;
					$aDetalhado = $endossoCompleto["endo_tx_pontos"][$f2];
					array_shift($aDetalhado);
					array_splice($aDetalhado, 10, 1); //Retira a coluna de "Jornada" que está entre "Repouso" e "Jornada Prevista"
					$rows[] = $aDetalhado;
				}
			//}

			//Inserir coluna de motivos{
				$legendas = [
					"" => "",
					"I" => "(I - Incluída Manualmente)",
					"P" => "(P - Pré-Assinalada)",
					"T" => "(T - Outras fontes de marcação)",
					"DSR" => "(DSR - Descanso Semanal Remunerado e Abono)"
				];
				for($f2 = 0; $f2 < count($rows); $f2++){
					$data = implode("-", array_reverse(explode("/", $rows[$f2]["data"])));

					
					$bdMotivos = mysqli_fetch_all(
						query(
							"SELECT moti_tx_legenda, moti_tx_nome FROM ponto
								JOIN entidade ON pont_tx_matricula = enti_tx_matricula
								JOIN motivo ON pont_nb_motivo = moti_nb_id
								WHERE pont_tx_status = 'ativo'
									AND enti_nb_id = '{$endossoCompleto["endo_nb_entidade"]}' 
									AND pont_tx_data LIKE '{$data}%'
									AND pont_tx_tipo IN (1,2,3,4);"
						), 
						MYSQLI_ASSOC
					);

					$bdAbonos = mysqli_fetch_all(query(
						"SELECT motivo.moti_tx_nome FROM abono
							JOIN entidade ON abon_tx_matricula = enti_tx_matricula
							JOIN motivo ON abon_nb_motivo = moti_nb_id
							WHERE abon_tx_status = 'ativo' 
								AND enti_nb_id = '{$endossoCompleto["endo_nb_entidade"]}' 
								AND abon_tx_data LIKE '{$data}%' 
							LIMIT 1;"
						),MYSQLI_ASSOC
					);

					$motivos = "";
					if(!empty($bdAbonos[0]["moti_tx_nome"])){
						$motivos .= $bdAbonos[0]["moti_tx_nome"]."<br>";
					}

					for($f3 = 0; $f3 < count($bdMotivos); $f3++){
						$motivo = isset($legendas[$bdMotivos[$f3]["moti_tx_legenda"]])? $bdMotivos[$f3]["moti_tx_nome"]: "";
						if(!empty($motivo) && is_bool(strpos($motivos, $motivo))){
							$motivos .= $motivo."<br>";
						}
					}

					array_splice($rows[$f2], 18, 0, $motivos); // inserir a coluna de motivo, no momento da implementação, estava na coluna 19
				}
			//}
			
			$botoes = ["<button id='btnCsv' onclick='downloadCSV(\"".$motoristas[$f]["enti_nb_id"]."\", \"".$motoristas[$f]["enti_tx_nome"]."\")'><img width='20' height='20' src='https://img.icons8.com/glyph-neue/64/FFFFFF/csv.png' alt='csv'/> Baixar CSV</button>"];
			if($f == count($motoristas)-1){
				$botoes[] = "<br><br><br><button id='btnImprimir' class='btn default' type='button' onclick='imprimir()'><img width='20' height='20' src='https://img.icons8.com/android/24/FFFFFF/print.png' alt='print'/> Imprimir</button>";
			}

			$motorista = $motoristas[$f];

			$colspanTitulos = [2,4,2,2,4,2]; //Utilizado em relatorio_espelho.php
			$cabecalho = [
				"data" => "DATA",
				"diaSemana" => "DIA",
				"inicioJornada" => "INÍCIO",
				"inicioRefeicao" => "INÍCIO REF.",
				"fimRefeicao" => "FIM REF.",
				"fimJornada" => "FIM",
				"diffRefeicao" => "REFEIÇÃO",
				//"diffEspera" =>  "ESPERA",
				"diffDescanso" => "DESCANSO",
				//"diffRepouso" =>  "REPOUSO",
				"jornadaPrevista" => "PREVISTA",
				"diffJornadaEfetiva" => "EFETIVA",
				//"maximoDirecaoContinua" =>  "MDC",
				"intersticio" => "INTERSTÍCIO",
				"he50" => "HE {$motorista["enti_tx_percHESemanal"]}%",
				"he100" => "HE&nbsp;{$motorista["enti_tx_percHEEx"]}%",
				"adicionalNoturno" => "ADICIONAL NOT.",
				//"esperaIndenizada" =>  "ESPERA IND.",
				"0" => "MOTIVO",
				"diffSaldo" => "SALDO",
			];

			if(in_array($motorista["enti_tx_ocupacao"], ["Ajudante", "Motorista"])){
				$colspanTitulos = [2,4,4,3,5,2];
				$cabecalho = array_merge(
					array_slice($cabecalho, 0, 7),
					["diffEspera" => "ESPERA"],
					array_slice($cabecalho, 7, 1),
					["diffRepouso" => "REPOUSO"],
					array_slice($cabecalho, 8, 2),
					["maximoDirecaoContinua" => "MDC"],
					array_slice($cabecalho, 10, 4),
					["esperaIndenizada" => "ESPERA INDENIZADA"],
					array_slice($cabecalho, 14, count($cabecalho))
				);
			}


			include "./relatorio_espelho.php";
			include "./csv_relatorio_espelho.php";
			if(count($motoristas) > 1){
				echo "<hr>";
			}
		}
		
		exit;
	}

	function buscarEndosso(){
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
					SELECT endo_nb_entidade FROM endosso
						WHERE endo_tx_status = 'ativo'
							AND endo_tx_mes = '{$_POST["busca_data"]}-01'
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

			$rows = [];
			//Pegando e formatando registros dos dias{
				
				$date = new DateTime($_POST["busca_data"]);

				$endossoCompleto = montarEndossoMes($date, $motorista);

				if(count($endossoCompleto) == 0){
					$counts["endossados"]["nao"]++;
					$motNaoEndossados .= "- [".$motorista["enti_tx_matricula"]."] ".$motorista["enti_tx_nome"]."<br>";
					continue;
				}
				
				$counts["naoConformidade"] += substr_count(json_encode($endossoCompleto["endo_tx_pontos"]), "fa-warning");
				$totalResumo = $endossoCompleto["totalResumo"];

				for ($i = 0; $i < count($endossoCompleto["endo_tx_pontos"]); $i++) {
					$aDetalhado = $endossoCompleto["endo_tx_pontos"][$i];
					$rows[] = $aDetalhado;
				}
				$totalResumoGrid = $totalResumo;

				$unsetKeys = [
					"saldoBruto",
					"saldoAnterior",
					"he50APagar",
					"he100APagar",
					"saldoFinal",
					"desconto_manual",
					"desconto_faltas_nao_justificadas"
				];
				foreach($unsetKeys as $unsetKey){
					unset($totalResumoGrid[$unsetKey]);
				}

				if(count($rows) > 0){
					$rows[] = array_values(array_merge(["", "", "", "", "", "", "<b>TOTAL</b>"], $totalResumoGrid));
				}
				unset($totalResumoGrid);
			//}

			if (count($rows) > 0) {
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
				$saldoFinal = (!empty($totalResumo["saldoFinal"])? $totalResumo["saldoFinal"]: operarHorarios([$totalResumo["saldoBruto"], $aPagar], "-"));

				if(!empty($totalResumo["desconto_manual"]) && !empty($totalResumo["desconto_faltas_nao_justificadas"]) && ($totalResumo["desconto_manual"] != "00:00" || $totalResumo["desconto_faltas_nao_justificadas"] != "00:00")){
					$descontado = "{$totalResumo["desconto_manual"]} + {$totalResumo["desconto_faltas_nao_justificadas"]}";
				}else{
					$descontado = "";
				}

				$saldosMotorista = "SALDOS: <br>
					<div class='table-responsive'>
						<table class='table w-auto text-xsmall bold table-bordered table-striped table-condensed flip-content table-hover compact' id='saldo'>
							<thead>
								<tr>
									<th>Anterior</th>
									<th>Período</th>
									<th>Bruto</th>
									<th>Pago</th>"
									.(!empty($descontado)? "<th>Descontado</th>": "")
									."<th>Final</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>{$totalResumo["saldoAnterior"]}</td>
									<td>{$totalResumo["diffSaldo"]}</td>
									<td>{$totalResumo["saldoBruto"]}</td>
									<td>{$aPagar}</td>"
									.(!empty($descontado)? "<td>{$totalResumo["desconto_manual"]} + {$totalResumo["desconto_faltas_nao_justificadas"]}</td>": "")
									."<td>{$saldoFinal}</td>
								</tr>
							</tbody>
						</table>
					</div>"
				;
				
				$aEmpresa = carregar("empresa", $motorista["enti_nb_empresa"]);
				$buttonImprimir = "";
				if (empty($_POST["busca_motorista"])){
					$buttonImprimir = 
						"<button"
							." name='acao'"
							." id='botaoContexCadastrar ImprimirRelatorio_".$motorista["enti_tx_matricula"]."'"
							." value='impressao_relatorio'"
							." type='button'"
							." class='btn btn-default'"
							." style=''"
							// ." disabled"
						.">"
							."Imprimir Relatório"
						."</button>"
					; 
				}

				$cabecalhoForm = 
					"<div style='display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;'>
						<div>
							{$aEmpresa["empr_tx_nome"]}<br>
							[{$motorista["enti_tx_matricula"]}] {$motorista["enti_tx_nome"]}
						</div>
						<div>
							{$buttonImprimir}
						</div>
					</div>
					{$saldosMotorista}"
				;

				$endossoHTML .=  abre_form($cabecalhoForm);
				
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

				$endossoHTML .=  montarTabelaPonto($cabecalho, $rows);
				$endossoHTML .=  fecha_form();

				$endossoHTML .=  
					"<form name='form_imprimir_relatorio_".$motorista["enti_tx_matricula"]."' method='post' target='_blank'>
						<input type='hidden' name='acao' value=''>
						<input type='hidden' name='busca_data' value='{$_POST["busca_data"]}'>
						<input type='hidden' name='busca_empresa' value='{$_POST["busca_empresa"]}'>
						<input type='hidden' name='busca_motorista' value=''>
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
								form.elements['busca_motorista'].value = idMotorista;
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
			}else{
				$counts["endossados"]["nao"]++;
				$motNaoEndossados .= "- [".$motorista["enti_tx_matricula"]."] ".$motorista["enti_tx_nome"]."<br>";
			}
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
				$fields[] = combo_net("Empresa*", "busca_empresa", (!empty($_POST["busca_empresa"])? $_POST["busca_empresa"] : $_SESSION["user_nb_empresa"]), 3, "empresa", "onchange=selecionaMotorista(this.value)", ($extraEmpresa?? ""));
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
