<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	include "funcoes_ponto.php"; //Conecta incluso dentro de funcoes_ponto

	function cadastro_abono(){
		global $CONTEX;

		unset($_POST["acao"]);
		$form = "<form id='cadastrarAbono' action='".$CONTEX["path"]."/cadastro_abono.php' method='post'>";
		foreach($_POST as $key => $value){
			$form .= "<input name='".$key."' value='".$value."'/>";
		}
		$form .= "</form>";

		echo $form.
			"<script>document.getElementById('cadastrarAbono').submit();</script>";
		;
		exit;
	}

	function carregarJS($opt){
		global $CONTEX;
		
		$select2URL = 
			$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/select2.php"
			."?path=".$CONTEX["path"]
			."&tabela=entidade"
			."&extra_limite=15"
			."&extra_busca=enti_tx_matricula"
		;

		echo 
			"<script>

				function selecionaMotorista(idEmpresa) {
					let buscaExtra = '';
					if(idEmpresa > 0){
						buscaExtra = '&extra_bd='+encodeURI('AND enti_tx_ocupacao IN (\"Motorista\", \"Ajudante\") AND enti_nb_empresa = '+idEmpresa+'');
						$('.busca_motorista')[0].innerHTML = null;
					}else{
						buscaExtra = '&extra_bd='+encodeURI('AND enti_tx_ocupacao IN (\"Motorista\", \"Ajudante\")');
					}

					// Verifique se o elemento está usando Select2 antes de destruí-lo
					if ($('.busca_motorista').data('select2')) {
						$('.busca_motorista').select2('destroy');
					}

					$.fn.select2.defaults.set('theme', 'bootstrap');
					$('.busca_motorista').select2({
						language: 'pt-BR',
						placeholder: 'Selecione um item',
						allowClear: true,
						ajax: {
							url: '".$select2URL."'+buscaExtra,
							dataType: 'json',
							delay: 250,
							processResults: function(data) {
								return {
									results: data
								};
							},
							cache: true
						}
					});


				}

				function updateValues(){
					if(".(!empty($_POST["busca_empresa"])? $_POST["busca_empresa"]: 0)." !== 0){
						empresa = document.getElementById('busca_empresa').value;
						selecionaMotorista(empresa);

						if(".(!empty($_POST["busca_motorista"])?1:0)."){
							document.getElementById('busca_motorista').innerHTML = '".$opt."';
							alert(document.getElementById('busca_motorista').value);
						}
					}
				}
			</script>"
		;
	}

	function index() {
		global $CONTEX, $totalResumo, $conn;

		var_dump($_POST); echo "<br><br>";

		cabecalho("Espelho de Ponto");

		$extraBuscaMotorista = "";
		$extraCampoData = "";
		if (in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante"])) {
			$_POST["busca_motorista"] = $_SESSION["user_nb_entidade"];
			$_POST["busca_empresa"] = $_SESSION["user_nb_empresa"];
			$extraBuscaMotorista = " AND enti_nb_id = '".$_SESSION["user_nb_entidade"]."'";
		}

		if (!empty($_POST["busca_motorista"])) {
			$aMotorista = carregar("entidade", $_POST["busca_motorista"]);
			$aEmpresa = carregar("empresa", $aMotorista["enti_nb_empresa"]);
		}

		$extraEmpresa = "";
		if (!empty($_SESSION["user_nb_empresa"]) && $_SESSION["user_tx_nivel"] != "Administrador" && $_SESSION["user_tx_nivel"] != "Super Administrador") {
			$extraEmpresa = " AND enti_nb_empresa = ".$_SESSION["user_nb_empresa"];
		}

		if (empty($_POST["busca_dataInicio"])){
			if(!empty($_POST["data_de"])){
				$_POST["busca_dataInicio"] = $_POST["data_de"];
			}else{
				$_POST["busca_dataInicio"] = date("Y-m-01");
			}
		}
		if (empty($_POST["busca_dataFim"])){
			if(!empty($_POST["data_ate"])){
				$_POST["busca_dataFim"] = $_POST["data_ate"];
			}else{
				$_POST["busca_dataFim"] = date("Y-m-d");
			}
		}

		$searchError = false;
		
		$opt = "";
		
		if(isset($_POST["acao"]) && $_POST["acao"] == "index"){
			//Confere se há algum erro na pesquisa{
				$errorMsg = "Insira os campos para pesquisar: ";
				if(empty($_POST["busca_empresa"])){
					if(empty($_POST["busca_motorista"])){
						$searchError = true;
						$errorMsg .= "Empresa, ";
						$_POST["busca_empresa"] = $_SESSION["user_nb_empresa"];
					}else{
						$idEmpresa = mysqli_fetch_assoc(query(
							"SELECT empr_nb_id FROM entidade 
								JOIN empresa ON enti_nb_empresa = empr_nb_id
								WHERE enti_tx_status = 'ativo'
									AND enti_nb_id = ".$_POST["busca_motorista"].";"
						));
						$_POST["busca_empresa"] = $idEmpresa["empr_nb_id"];
					}
				}
				if(empty($_POST["busca_motorista"])){
					$searchError = true;
					$errorMsg .= "Motorista/Ajudante, ";
				}
				if(empty($_POST["busca_dataInicio"])){
					$searchError = true;
					$errorMsg .= "Data Início, ";
				}
				if(empty($_POST["busca_dataFim"])){
					$searchError = true;
					$errorMsg .= "Data Fim, ";
				}
	
				if(!$searchError && !empty($_POST["busca_empresa"]) && !empty($_POST["busca_motorista"])){
					if($_POST["busca_dataInicio"] > date("Y-m-d") || $_POST["busca_dataFim"] > date("Y-m-d")){
						$searchError = true;
						$errorMsg = "Data de pesquisa não pode ser após hoje (".date("d/m/Y")."). ";
					}

					$motorista = mysqli_fetch_assoc(
						query(
							"SELECT enti_nb_id, enti_tx_nome FROM entidade
								WHERE enti_tx_status = 'ativo'
									AND enti_nb_empresa = ".$_POST["busca_empresa"]."
									AND enti_nb_id = ".$_POST["busca_motorista"]."
								LIMIT 1"
						)
					);
	
					if(empty($motorista)){
						$searchError = true;
						$errorMsg = "Este motorista não pertence a esta empresa. ";
					}
	
					$opt = "<option value=\"".$motorista["enti_nb_id"]."\">[".$motorista["enti_nb_id"]."]".$motorista["enti_tx_nome"]."</option>";
				}
	
				if($searchError){
					$errorMsg = substr($errorMsg, 0, -2).".";
					set_status("ERRO: ".$errorMsg);
				}
			//}
		}else{
			$_POST["busca_empresa"]   = $_POST["busca_empresa"]?? "";
			$_POST["busca_motorista"] = $_POST["busca_motorista"]?? "";
		}

		//CAMPOS DE CONSULTA
		if($_SESSION["user_tx_nivel"] == "Motorista"){
			$nomeEmpresa = mysqli_fetch_assoc(query("SELECT empr_tx_nome FROM empresa WHERE empr_nb_id = ".$_SESSION["user_nb_empresa"]));
			$searchFields = [
				texto("Empresa*", $nomeEmpresa["empr_tx_nome"], 3),
				texto("Motorista/Ajudante*", $_SESSION["user_tx_nome"], 3),
			];
		}else{
			$searchFields = [
				combo_net("Empresa*", "busca_empresa", ($_POST["busca_empresa"]?? ""), 3, "empresa", "onchange=selecionaMotorista(this.value) ", $extraEmpresa),
				combo_net(
					"Motorista/Ajudante*",
					"busca_motorista",
					(!empty($_POST["busca_motorista"])? $_POST["busca_motorista"]: ""),
					4, 
					"entidade", 
					"", 
					(!empty($_POST["busca_empresa"])?" AND enti_nb_empresa = ".$_POST["busca_empresa"]:"")." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante') ".$extraEmpresa." ".$extraBuscaMotorista, 
					"enti_tx_matricula"
				)
			];
		}

		$searchFields = array_merge(
			$searchFields,
			[
				campo_data("Data Início", "busca_dataInicio", ($_POST["busca_dataInicio"]?? ""), 2, $extraCampoData),
				campo_data("Data Fim", "busca_dataFim", ($_POST["busca_dataFim"]?? ""), 2,$extraCampoData)
			]
		);

		$botao_imprimir =
			"<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button >
					<script>
						function imprimir() {
							// Abrir a caixa de diálogo de impressão
							window.print();
						}
					</script>";
		//BOTOES
		$b = [
			botao("Buscar", "index", "", "", "", "", "btn btn-success"),
		];
		if (!in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante"])) {
			$b[] = botao("Cadastrar Abono", "cadastro_abono", "", "", "btn btn-secondary");
		}
		$b[] = $botao_imprimir;
		
		abre_form("Filtro de Busca");
		linha_form($searchFields);
		fecha_form($b);
    
		echo 
			"<div id='tituloRelatorio'>
				<h1>Espelho de Ponto</h1>
				<img id='logo' style='width: 150px' src='".$CONTEX["path"]."/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
			</div>
			<style>
				#tituloRelatorio{
					display: none;
				}"
		;
		include "css/espelho_ponto.css";
		echo "</style>";
		
		$cab = [
			"", "DATA", "<div style='margin:10px'>DIA</div>", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
			"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO", "HE 50%", "HE&nbsp;100%",
			"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO(**)"
		];

		// Converte as datas para objetos DateTime
		$startDate = !empty($_POST["busca_dataInicio"])? new DateTime($_POST["busca_dataInicio"]): "";
		$endDate   = !empty($_POST["busca_dataFim"])? new DateTime($_POST["busca_dataFim"]): "";

		if (!$searchError && !empty($_POST["acao"]) && $_POST["acao"] == "index"){
			$aDia = [];

			// Loop for para percorrer as datas
			for ($date = $startDate; $date <= $endDate; $date->modify("+1 day")) {
				$dataVez = $date->format("Y-m-d");

				$aDetalhado = diaDetalhePonto($aMotorista["enti_tx_matricula"], $dataVez);
				
				$row = array_values(array_merge([verificaTolerancia($aDetalhado["diffSaldo"], $dataVez, $aMotorista["enti_nb_id"])], $aDetalhado));
				for($f = 0; $f < sizeof($row)-1; $f++){
          			if($f == 12){//Se for da coluna "Jornada Prevista", não apaga
						continue;
					}
					if($row[$f] == "00:00"){
						$row[$f] = "";
					}
				}
				$aDia[] = $row;
			}
			criarFuncoesDeAjuste();
	
			if (!empty($aEmpresa["empr_nb_parametro"])) {
				$parametroPadrao = carregar("parametro", $aEmpresa["empr_nb_parametro"]);
				if (
					$parametroPadrao["para_tx_jornadaSemanal"] 		!= $aMotorista["enti_tx_jornadaSemanal"] ||
					$parametroPadrao["para_tx_jornadaSabado"] 		!= $aMotorista["enti_tx_jornadaSabado"] ||
					$parametroPadrao["para_tx_percentualHE"] 		!= $aMotorista["enti_tx_percentualHE"] ||
					$parametroPadrao["para_tx_percentualSabadoHE"] 	!= $aMotorista["enti_tx_percentualSabadoHE"] ||
					$parametroPadrao["para_nb_id"] 					!= $aMotorista["enti_nb_parametro"]
				) {
					$parametroPadrao = "Convenção Não Padronizada, Semanal (".$aMotorista["enti_tx_jornadaSemanal"]."), Sábado (".$aMotorista["enti_tx_jornadaSabado"].")";
				} else {
					$parametroPadrao = "Convenção Padronizada: ".$parametroPadrao["para_tx_nome"].", Semanal (".$parametroPadrao["para_tx_jornadaSemanal"]."), Sábado (".$parametroPadrao["para_tx_jornadaSabado"].")";
				}
			}else{
				$parametroPadrao = "Convenção Não Padronizada, Semanal (".$aMotorista["enti_tx_jornadaSemanal"]."), Sábado (".$aMotorista["enti_tx_jornadaSabado"].")";
			}

			$saldoAnterior = mysqli_fetch_assoc(
				query(
					"SELECT endo_tx_saldo FROM endosso
						WHERE endo_tx_matricula = '".$aMotorista["enti_tx_matricula"]."'
							AND endo_tx_ate < '".$_POST["busca_dataInicio"]."'
							AND endo_tx_status = 'ativo'
						ORDER BY endo_tx_ate DESC
						LIMIT 1;"
				)
			);
			if(isset($saldoAnterior["endo_tx_saldo"])){
				$saldoAnterior = $saldoAnterior["endo_tx_saldo"];
			}elseif(!empty($aMotorista["enti_tx_banco"])){
				$saldoAnterior = $aMotorista["enti_tx_banco"];
				$saldoAnterior = $saldoAnterior[0] == "0" && strlen($saldoAnterior) > 5? substr($saldoAnterior, 1): $saldoAnterior;
			}else{
				$saldoAnterior = "--:--";
			}

			$saldoFinal = "--:--";
			if($saldoAnterior != "--:--"){
				$saldoFinal = somarHorarios([$saldoAnterior, $totalResumo["diffSaldo"]]);
			}else{
				$saldoFinal = $totalResumo["diffSaldo"];
			}
			

			$saldosMotorista = "SALDOS: <br>
				<div class='table-responsive'>
					<table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact' id='saldo'>
						<thead>
							<tr>
								<th>Anterior:</th>
								<th>Período:</th>
								<th>Final:</th>
							</tr>
						</thead>
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
				 
			$periodoPesquisa = "De ".date("d/m/Y", strtotime($_POST["busca_dataInicio"]))." até ".date("d/m/Y", strtotime($_POST["busca_dataFim"]));
      
			abre_form(
				"<div>"
					.$aEmpresa["empr_tx_nome"]."<br>"
					."[".$aMotorista["enti_tx_matricula"]."] ".$aMotorista["enti_tx_nome"]."<br>"
					.$parametroPadrao."<br><br>"
					.$periodoPesquisa."<br>"
				."</div>"
				.$saldosMotorista
			);

			$aDia[] = array_values(array_merge(["", "", "", "", "", "", "<b>TOTAL</b>"], $totalResumo));
			
			grid2($cab, $aDia, "Jornada Semanal (Horas): ".$aMotorista["enti_tx_jornadaSemanal"]);
			fecha_form();
		}
		
		rodape();
		
		echo 
			"<form name='form_ajuste_ponto' method='post'>
				<input type='hidden' name='acao' value='layout_ajuste'>
				<input type='hidden' name='id' value='". $aMotorista["enti_nb_id"] ."'>
				<input type='hidden' name='data'>
				<input type='hidden' name='data_de' value='".((!empty($_POST["busca_dataInicio"])? $_POST["busca_dataInicio"]: date("01/m/Y")))."'>
				<input type='hidden' name='data_ate' value='".$_POST["busca_dataFim"]."'>
			</form>"
		;

		carregarJS($opt);
	}
