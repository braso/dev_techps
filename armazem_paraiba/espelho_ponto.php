<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/

	include "funcoes_ponto.php"; //Conecta incluso dentro de funcoes_ponto
	
	function cadastro_abono(){
		unset($_POST["acao"]);
		$form = "<form id='cadastrarAbono' action='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_abono.php' method='post'>";
		foreach($_POST as $key => $value){
			$form .= "<input name='".$key."' value='".$value."'/>";
		}
		$form .= "</form>";

		echo $form.
			"<script>document.getElementById('cadastrarAbono').submit();</script>"
		;
		exit;
	}

	function buscarEspelho(){
		$data_inicio_obj = new DateTime($_POST["busca_dataInicio"]);
		$data_fim_obj = new DateTime($_POST["busca_dataFim"]);

		if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante"])){
			$_POST["busca_motorista"] = $_SESSION["user_nb_entidade"];
			$_POST["busca_empresa"] = $_SESSION["user_nb_empresa"];
		}
		
		//Confere se há algum erro na pesquisa{
			$baseErrMsg = ["Insira os campos para pesquisar: ", "", ""];
			$errorMsg = $baseErrMsg;
			if(empty($_POST["busca_empresa"])){
				if(empty($_POST["busca_motorista"])){
					$errorMsg[0] .= "Empresa, ";
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
				$errorMsg[0] .= "Motorista/Ajudante, ";
			}
			if(empty($_POST["busca_dataInicio"])){
				$errorMsg[0] .= "Data Início, ";
			}
			if(empty($_POST["busca_dataFim"])){
				$errorMsg[0] .= "Data Fim, ";
			}
			
			if ($data_fim_obj < $data_inicio_obj) {
				$errorMsg[2] .= "A data final não pode ser anterior à data inicial.";
			}
			
			if(!empty($_POST["busca_empresa"]) && !empty($_POST["busca_motorista"])){
				if($_POST["busca_dataInicio"] > date("Y-m-d") || $_POST["busca_dataFim"] > date("Y-m-d")){
					$errorMsg[1] = "Data de pesquisa não pode ser após hoje (".date("d/m/Y")."). ";
				}

				$motorista = mysqli_fetch_assoc(query(
					"SELECT enti_nb_id, enti_tx_nome, enti_tx_dataCadastro FROM entidade
						WHERE enti_tx_status = 'ativo'
							AND enti_nb_empresa = ".$_POST["busca_empresa"]."
							AND enti_nb_id = ".$_POST["busca_motorista"]."
						LIMIT 1;"
				));

				if(empty($motorista)){
					$errorMsg[2] = "Este motorista não pertence a esta empresa. ";
				}else{
					$opt = "<option value=\"".$motorista["enti_nb_id"]."\">[".$motorista["enti_nb_id"]."]".$motorista["enti_tx_nome"]."</option>";
				}
			}

			if($errorMsg != $baseErrMsg){
				foreach($errorMsg as &$msg){
					if(!empty($msg)){
						$msg = substr($msg, 0, -2).".";
					}
				}
				$errorMsg = implode("<br>", $errorMsg);
				set_status("ERRO: ".$errorMsg);
				$_POST["acao"] = "";
			}

			//Conferir se a data de início da pesquisa está antes do cadastro do motorista{
				if(!empty($motorista)){
					$baseErrMsg = [];
					$errorMsg = $baseErrMsg; 
					$data_cadastro = new DateTime($motorista["enti_tx_dataCadastro"]);

					if(date_diff($data_cadastro, $data_inicio_obj)->invert){
						$errorMsg = ["A data inicial deve ser anterior ao cadastro do motorista (".$data_cadastro->format("d/m/Y")."). "];
					}
				}

				if($errorMsg != $baseErrMsg){
					foreach($errorMsg as &$msg){
						if(!empty($msg)){
							$msg = substr($msg, 0, -2).".";
						}
					}
					$errorMsg = implode("<br>", $errorMsg);
					set_status("ERRO: ".$errorMsg);
					$_POST["acao"] = "";
				}
			//}
		//}
		index();
	}

	function index(){
		cabecalho("Espelho de Ponto");

		$condBuscaMotorista = "";
		$condBuscaEmpresa = "";


		if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante"])){
			$_POST["busca_motorista"] = $_SESSION["user_nb_entidade"];
			$_POST["busca_empresa"] = $_SESSION["user_nb_empresa"];
			$condBuscaMotorista = " AND enti_nb_id = '".$_SESSION["user_nb_entidade"]."'";
		}

		if(!empty($_SESSION["user_nb_empresa"]) && $_SESSION["user_tx_nivel"] != "Administrador" && $_SESSION["user_tx_nivel"] != "Super Administrador"){
			$condBuscaEmpresa = " AND enti_nb_empresa = ".$_SESSION["user_nb_empresa"];
		}

		if(empty($_POST["busca_dataInicio"])){
			if(!empty($_POST["data_de"])){
				$_POST["busca_dataInicio"] = $_POST["data_de"];
			}else{
				$_POST["busca_dataInicio"] = date("Y-m-01");
			}
		}
		if(empty($_POST["busca_dataFim"])){
			if(!empty($_POST["data_ate"])){
				$_POST["busca_dataFim"] = $_POST["data_ate"];
			}else{
				$_POST["busca_dataFim"] = date("Y-m-d");
			}
		}

		//CAMPOS DE CONSULTA{
			if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante"])){
				$nomeEmpresa = mysqli_fetch_assoc(query("SELECT empr_tx_nome FROM empresa WHERE empr_nb_id = ".$_SESSION["user_nb_empresa"]));
				$searchFields = [
					texto("Empresa*", $nomeEmpresa["empr_tx_nome"], 3),
					texto("Motorista/Ajudante*", $_SESSION["user_tx_nome"], 3),
				];
			}else{
				$searchFields = [
					combo_net("Empresa*", "busca_empresa", ($_POST["busca_empresa"]?? ""), 3, "empresa", "onchange=selecionaMotorista(this.value) ", $condBuscaEmpresa),
					combo_net(
						"Motorista/Ajudante*",
						"busca_motorista",
						(!empty($_POST["busca_motorista"])? $_POST["busca_motorista"]: ""),
						4, 
						"entidade", 
						"", 
						(!empty($_POST["busca_empresa"])?" AND enti_nb_empresa = ".$_POST["busca_empresa"]:"")." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante') ".$condBuscaEmpresa." ".$condBuscaMotorista, 
						"enti_tx_matricula"
					)
				];
			}

			$searchFields = array_merge(
				$searchFields,
				[
					campo_data("Data Início", "busca_dataInicio", ($_POST["busca_dataInicio"]?? ""), 2),
					campo_data("Data Fim", "busca_dataFim", ($_POST["busca_dataFim"]?? ""), 2)
				]
			);
		//}

		//BOTOES{
			$b = [
				botao("Buscar", "buscarEspelho()", "", "", "", "", "btn btn-success"),
			];
			if(!in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante"])){
				$b[] = botao("Cadastrar Abono", "cadastro_abono", "", "", "btn btn-secondary");
			}

			$botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";
			$b[] = $botao_imprimir;
		//}
		
		abre_form("Filtro de Busca");
		linha_form($searchFields);
		fecha_form($b);

		$opt = "";
		//Buscar Espelho{
		if(!empty($_POST["acao"]) && $_POST["acao"] == "buscarEspelho()"){
			global $totalResumo;

			if(!empty($_POST["busca_motorista"])){
				$aMotorista = carregar("entidade", $_POST["busca_motorista"]);
				$aEmpresa = carregar("empresa", $aMotorista["enti_nb_empresa"]);
			}
			if(empty($_POST["busca_dataInicio"])){
				$_POST["busca_dataInicio"] = (!empty($_POST["data_de"]))? $_POST["data_de"]: date("Y-m-01");
			}
			if(empty($_POST["busca_dataFim"])){
				$_POST["busca_dataFim"] = (!empty($_POST["data_ate"]))? $_POST["data_ate"]: date("Y-m-d");
			}

			echo   
				"<div style='display:none' id='tituloRelatorio'>
					<h1>Espelho de Ponto</h1>
					<img id='logo' style='width: 150px' src='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
				</div>"
			;
			
			$cab = [
				"", "DATA", "<div style='margin:10px'>DIA</div>", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
				"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO", "H.E. ".$aMotorista["enti_tx_percentualHE"]."%", "H.E. ".$aMotorista["enti_tx_percentualSabadoHE"]."%",
				"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO(**)"
			];

			// Converte as datas para objetos DateTime
			$startDate = !empty($_POST["busca_dataInicio"])? new DateTime($_POST["busca_dataInicio"]): "";
			$endDate   = !empty($_POST["busca_dataFim"])? new DateTime($_POST["busca_dataFim"]): "";

			$aDia = [];
			// Loop for para percorrer as datas
			for ($date = $startDate; $date <= $endDate; $date->modify("+1 day")){
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


			$parametroPadrao = "Convenção Não Padronizada, Semanal (".$aMotorista["enti_tx_jornadaSemanal"]."), Sábado (".$aMotorista["enti_tx_jornadaSabado"].")";

			if(!empty($aEmpresa["empr_nb_parametro"])){
				$parametroEmpresa = mysqli_fetch_assoc(query(
					"SELECT para_tx_jornadaSemanal, para_tx_jornadaSabado, para_tx_percentualHE, para_tx_percentualSabadoHE, para_nb_id, para_tx_nome, para_tx_jornadaSemanal, para_tx_jornadaSabado FROM parametro"
						." WHERE para_tx_status = 'ativo'"
							." AND para_nb_id = ".$aEmpresa["empr_nb_parametro"]
						." LIMIT 1;"
				));
				if(array_keys(array_intersect($parametroEmpresa, $aMotorista)) == ["para_tx_jornadaSemanal", "para_tx_jornadaSabado", "para_tx_percentualHE", "para_tx_percentualSabadoHE", "para_nb_id"]){
					$parametroPadrao = "Convenção Padronizada: ".$parametroEmpresa["para_tx_nome"].", Semanal (".$parametroEmpresa["para_tx_jornadaSemanal"]."), Sábado (".$parametroEmpresa["para_tx_jornadaSabado"].")";
				}
			}

			$ultimoEndosso = mysqli_fetch_assoc(query(
					"SELECT endo_tx_filename FROM endosso"
						." WHERE endo_tx_status = 'ativo'"
							." AND endo_tx_matricula = '".$aMotorista["enti_tx_matricula"]."'"
							." AND endo_tx_ate < '".$_POST["busca_dataInicio"]."'"
						." ORDER BY endo_tx_ate DESC"
						." LIMIT 1;"
			));

			
			
			$saldoAnterior = "";
			if(!empty($ultimoEndosso)){
				$ultimoEndosso = lerEndossoCSV($ultimoEndosso["endo_tx_filename"]);
				$saldoAnterior = $ultimoEndosso["totalResumo"]["saldoFinal"];
			}elseif(!empty($aMotorista["enti_tx_banco"])){
				$saldoAnterior = $aMotorista["enti_tx_banco"];
				$saldoAnterior = $saldoAnterior[0] == "0" && strlen($saldoAnterior) > 5? substr($saldoAnterior, 1): $saldoAnterior;
			}


			$saldoFinal = $totalResumo["diffSaldo"];
			if(!empty($saldoAnterior)){
				$saldoFinal = operarHorarios([$saldoAnterior, $totalResumo["diffSaldo"]], "+");
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
								<td>".($saldoAnterior?? "--:--")."</td>
								<td>".($totalResumo["diffSaldo"]?? "--:--")."</td>
								<td>".($saldoFinal?? "--:--")."</td>
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

			echo   
				"<form name='form_ajuste_ponto' method='post'>
					<input type='hidden' name='acao' value='layout_ajuste'>
					<input type='hidden' name='busca_empresa' value='".$_POST["busca_empresa"]."'>
					<input type='hidden' name='id' value='".$aMotorista["enti_nb_id"]."'>
					<input type='hidden' name='HTTP_REFERER' value=''>
					<input type='hidden' name='data'>
					<input type='hidden' name='busca_empresa' value='".$aMotorista['enti_nb_empresa']."'>
					<input type='hidden' name='data_de' value='".((!empty($_POST["busca_dataInicio"])? $_POST["busca_dataInicio"]: date("01/m/Y")))."'>
					<input type='hidden' name='data_ate' value='".$_POST["busca_dataFim"]."'>
				</form>"
			;

		}
		//}
		
		echo carregarJS($opt);
		echo "<style>";
		include "css/espelho_ponto.css";
		echo "</style>";
		rodape();
	}

	function carregarJS($opt): string{
		
		$select2URL = 
			$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/select2.php"
			."?path=".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]
			."&tabela=entidade"
			."&extra_limite=15"
			."&extra_busca=enti_tx_matricula"
		;

		return 
			"<script>

				function selecionaMotorista(idEmpresa){
					let buscaExtra = '&extra_bd='+encodeURI('AND enti_tx_ocupacao IN (\"Motorista\", \"Ajudante\")');
					if(idEmpresa > 0){
						buscaExtra += encodeURI(' AND enti_nb_empresa = '+idEmpresa);
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
							url: '".$select2URL."'+buscaExtra,
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

				function imprimir(){
					window.print();
				}
			</script>"
		;
	}
