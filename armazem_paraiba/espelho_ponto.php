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
			if(is_array($value)){
				foreach($value as $val){
					$form .= "<input name='".$key."[]' value='".$val."'/>";
				}
			}else{
				$form .= "<input name='".$key."' value='".$value."'/>";
			}
		}
		$form .= "</form>";

		echo $form.
			"<script>document.getElementById('cadastrarAbono').submit();</script>"
		;
		exit;
	}

	function buscarEspelho(){

		if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])){
			[$_POST["busca_motorista"], $_POST["busca_empresa"]] = [$_SESSION["user_nb_entidade"], $_SESSION["user_nb_empresa"]];
		}

		
		//Confere se há algum erro na pesquisa{
			try{
				if(empty($_POST["busca_empresa"])){
					if(empty($_POST["busca_motorista"])){
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

				if(empty($_POST["busca_periodo"]) && !empty($_POST["periodo_abono"])){
					$_POST["busca_periodo"] = $_POST["periodo_abono"];
					unset($_POST["periodo_abono"]);
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
				
				if(!empty($_POST["busca_empresa"]) && !empty($_POST["busca_motorista"])){
					if($_POST["busca_periodo"][0] > date("Y-m-d") || $_POST["busca_periodo"][1] > date("Y-m-d")){
						$_POST["errorFields"][] = "busca_periodo";
						throw new Exception("Data de pesquisa não pode ser após hoje (".date("d/m/Y").").");
					}else{
						$motorista = mysqli_fetch_assoc(query(
							"SELECT enti_nb_id, enti_tx_nome, enti_tx_admissao FROM entidade"
								." WHERE enti_tx_status = 'ativo'"
									." AND enti_nb_empresa = ".$_POST["busca_empresa"]
									." AND enti_nb_id = ".$_POST["busca_motorista"]
								." LIMIT 1;"
						));
		
						if(empty($motorista)){
							$_POST["errorFields"][] = "busca_motorista";
							throw new Exception("Este funcionário não pertence a esta empresa.");
						}
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
		cabecalho("Buscar Espelho de Ponto");

		$condBuscaMotorista = "";
		$condBuscaEmpresa = "";

		if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])){
			$_POST["busca_motorista"] = $_SESSION["user_nb_entidade"];
			$_POST["busca_empresa"] = $_SESSION["user_nb_empresa"];
			$condBuscaMotorista = " AND enti_nb_id = '".$_SESSION["user_nb_entidade"]."'";
		}

		if(!empty($_SESSION["user_nb_empresa"]) && $_SESSION["user_tx_nivel"] != "Administrador" && $_SESSION["user_tx_nivel"] != "Super Administrador"){
			$condBuscaEmpresa .= " AND enti_nb_empresa = ".$_SESSION["user_nb_empresa"];
		}

		//CAMPOS DE CONSULTA{
			if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])){
				$nomeEmpresa = mysqli_fetch_assoc(query("SELECT empr_tx_nome FROM empresa WHERE empr_nb_id = ".$_SESSION["user_nb_empresa"]));
				$searchFields = [
					texto("Empresa*", $nomeEmpresa["empr_tx_nome"], 3),
					texto("Funcionário*", $_SESSION["user_tx_nome"], 3),
				];
			}else{
				$searchFields = [
					combo_net("Empresa*", "busca_empresa", ($_POST["busca_empresa"]?? ""), 3, "empresa", "onchange=selecionaMotorista(this.value) ", $condBuscaEmpresa),
					combo_net(
						"Funcionário*",
						"busca_motorista",
						(!empty($_POST["busca_motorista"])? $_POST["busca_motorista"]: ""),
						4, 
						"entidade", 
						"", 
						(!empty($_POST["busca_empresa"])?" AND enti_nb_empresa = ".$_POST["busca_empresa"]:"")." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário') ".$condBuscaEmpresa." ".$condBuscaMotorista, 
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
				$b[] = botao("Cadastrar Abono", "cadastro_abono", "", "", "btn btn-secondary");
			}

			$botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";
			$b[] = $botao_imprimir;
		//}
		
		echo abre_form();
		echo linha_form($searchFields);
		echo fecha_form($b);

		$opt = "";
		//Buscar Espelho{
			if(!empty($_POST["acao"]) && $_POST["acao"] == "buscarEspelho()"){
				global $totalResumo;

				echo   
					"<div style='display:none' id='tituloRelatorio'>
						<h1>Espelho de Ponto</h1>
						<img id='logo' style='width: 150px' src='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
					</div>"
				;

				// Converte as datas para objetos DateTime
				[$startDate, $endDate] = [new DateTime($_POST["busca_periodo"][0]), new DateTime($_POST["busca_periodo"][1])];

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
				if(!empty($_POST["busca_motorista"])){
					$aEmpresa = carregar("empresa", $motorista["enti_nb_empresa"]);
				}
				// Loop for para percorrer as datas
				for ($date = $startDate; $date <= $endDate; $date->modify("+1 day")){
					$aDetalhado = diaDetalhePonto($motorista, $date->format("Y-m-d"));
					
					$row = array_values(array_merge([verificaTolerancia($aDetalhado["diffSaldo"], $date->format("Y-m-d"), $motorista["enti_nb_id"])], $aDetalhado));
					for($f = 0; $f < sizeof($row)-1; $f++){
						if(in_array($f, [3, 4, 5, 6, 12])){//Se for das colunas de jornada, refeição ou "Jornada Prevista", não apaga
							continue;
						}
						if($row[$f] == "00:00"){
							$row[$f] = "";
						}
					}
					$rows[] = $row;
				}

				$parametroPadrao = "Convenção Não Padronizada, Semanal (".$motorista["enti_tx_jornadaSemanal"]."), Sábado (".$motorista["enti_tx_jornadaSabado"].")";

				if(!empty($aEmpresa["empr_nb_parametro"])){
					$parametroEmpresa = mysqli_fetch_assoc(query(
						"SELECT para_tx_jornadaSemanal, para_tx_jornadaSabado, para_tx_percHESemanal, para_tx_percHEEx, para_nb_id, para_tx_nome, para_tx_jornadaSemanal, para_tx_jornadaSabado FROM parametro"
							." WHERE para_tx_status = 'ativo'"
								." AND para_nb_id = ".$aEmpresa["empr_nb_parametro"]
							." LIMIT 1;"
					));
					if(array_keys(array_intersect($parametroEmpresa, $motorista)) == ["para_tx_jornadaSemanal", "para_tx_jornadaSabado", "para_tx_percHESemanal", "para_tx_percHEEx", "para_nb_id"]){
						$parametroPadrao = "Convenção Padronizada: ".$parametroEmpresa["para_tx_nome"].", Semanal (".$parametroEmpresa["para_tx_jornadaSemanal"]."), Sábado (".$parametroEmpresa["para_tx_jornadaSabado"].")";
					}
				}

				$ultimoEndosso = mysqli_fetch_assoc(query(
						"SELECT endo_tx_filename FROM endosso"
							." WHERE endo_tx_status = 'ativo'"
								." AND endo_tx_matricula = '".$motorista["enti_tx_matricula"]."'"
								." AND endo_tx_ate < '".$_POST["busca_periodo"][0]."'"
							." ORDER BY endo_tx_ate DESC"
							." LIMIT 1;"
				));

				
				
				$saldoAnterior = "";
				if(!empty($ultimoEndosso)){
					$ultimoEndosso = lerEndossoCSV($ultimoEndosso["endo_tx_filename"]);
					$saldoAnterior = $ultimoEndosso["totalResumo"]["saldoFinal"];
				}elseif(!empty($motorista["enti_tx_banco"])){
					$saldoAnterior = $motorista["enti_tx_banco"];
					$saldoAnterior = $saldoAnterior[0] == "0" && strlen($saldoAnterior) > 5? substr($saldoAnterior, 1): $saldoAnterior;
				}

				$saldoFinal = $totalResumo["diffSaldo"];
				if(!empty($saldoAnterior)){
					$saldoFinal = operarHorarios([$saldoAnterior, $totalResumo["diffSaldo"]], "+");
				}
				

				$saldosMotorista = "SALDOS: <br>
					<div class='table-responsive'>
						<table class='table w-auto text-xsmall bold table-bordered table-striped table-condensed flip-content table-hover compact' id='saldo'>
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
						
				$periodoPesquisa = "De ".date("d/m/Y", strtotime($_POST["busca_periodo"][0]))." até ".date("d/m/Y", strtotime($_POST["busca_periodo"][1]));
			
				$cabecalho = [
					"", "DATA", "<div style='margin:10px'>DIA</div>", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
					"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO", "H.E. ".$motorista["enti_tx_percHESemanal"]."%", "H.E. ".$motorista["enti_tx_percHEEx"]."%",
					"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO(**)"
				];
				$rows[] = array_values(array_merge(["", "", "", "", "", "", "<b>TOTAL</b>"], $totalResumo));

				echo abre_form(
					"<div>"
						.$aEmpresa["empr_tx_nome"]."<br>"
						."[".$motorista["enti_tx_matricula"]."] ".$motorista["enti_tx_nome"]."<br>"
						.$parametroPadrao."<br><br>"
						.$periodoPesquisa."<br>"
					."</div>"
					.$saldosMotorista
				);
				echo montarTabelaPonto($cabecalho, $rows, "Jornada Semanal (Horas): {$motorista["enti_tx_jornadaSemanal"]}");
				echo fecha_form();

				unset($_POST["errorFields"]);
				$params = array_merge($_POST, [
					"acao" => "index",
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
		echo "<style>";
		include "css/espelho_ponto.css";
		echo "</style>";
		rodape();
	}

	function carregarJS($opt): string{
		
		$select2URL = 
			"{$_ENV["URL_BASE"]}{$_ENV["APP_PATH"]}/contex20/select2.php"
			."?path={$_ENV["APP_PATH"]}{$_ENV["CONTEX_PATH"]}"
			."&tabela=entidade"
			."&extra_limite=15"
			."&extra_busca=enti_tx_matricula";
		;

		return 
			"<script>
					function ajustarPonto(idMotorista, data){
						document.form_ajuste_ponto.idMotorista.value = idMotorista;
						document.form_ajuste_ponto.data.value = data;
						document.form_ajuste_ponto.submit();
					}

				function selecionaMotorista(idEmpresa){
					let buscaExtra = '&extra_bd='+encodeURI('AND enti_tx_ocupacao IN (\"Motorista\", \"Ajudante\", \"Funcionário\")');
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
							url: '{$select2URL}'+buscaExtra,
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

				function imprimir(){
					window.print();
				}
			</script>"
		;
	}
