<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
	
	include "funcoes_ponto.php"; // conecta.php importado dentro de funcoes_ponto	

	function redirParaAbono(){
		global $CONTEX;
		if(empty($_POST['busca_motorista'])){
			header("Location: ".$CONTEX["path"]."/cadastro_abono.php");
			exit;
		}
	
		// Gerar o HTML do formulário e a página de redirecionamento

		echo "<form id='forms_abono' action='".$CONTEX["path"]."/cadastro_abono.php' method='post'>"
				.campo_hidden("acao", "layout_abono")
				.campo_hidden("busca_motorista", htmlspecialchars($_POST['busca_motorista']))
				.campo_hidden("busca_empresa", htmlspecialchars($_POST['busca_empresa']))
				.campo_hidden("busca_data", htmlspecialchars($_POST['busca_data']))
				.campo_hidden("HTTP_REFERER", $CONTEX["path"]."/nao_conformidade.php")
			."</form>";
		
		echo "<script>document.getElementById('forms_abono').submit();</script>";

		exit;
	}

	// function cadastrar(){
	// 	$url = substr($_SERVER["REQUEST_URI"], 0, strrpos($_SERVER["REQUEST_URI"], "/"));
	// 	header("Location: ".$_SERVER["HTTP_ORIGIN"].$url."/cadastro_endosso");
	// 	exit();
	// }

	function buscar(){
		if(!empty($_GET["acao"]) && $_GET["acao"] == "buscar"){//Se estiver pesquisando
			//Conferir se os campos foram inseridos.
			if(empty($_GET["busca_data"])){
				echo "<script>alert('Insira data para pesquisar.');</script>";
			}
		}
		index();
		exit;
	}

	function index(){
		global $totalResumo, $CONTEX;

		cabecalho("Não Conformidade");

		$extraEmpresa = "";
		$extraEmpresaMotorista = "";
		if ($_SESSION["user_nb_empresa"] > 0 && is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
			$extraEmpresa = " AND empr_nb_id = '".$_SESSION["user_nb_empresa"]."'";
			$extraEmpresaMotorista = " AND enti_nb_empresa = '".$_SESSION["user_nb_empresa"]."'";
		}

		$extra = "";
		$_GET["busca_situacao"] = "Não conformidade";
		foreach(["busca_empresa", "busca_data", "busca_motorista", "busca_situacao"] as $campo){
			if(isset($_GET[$campo]) && !empty($_GET[$campo])){
				$_POST[$campo] = $_GET[$campo];
			}
		}

		if(!empty($_POST["busca_motorista"])){
			$extra = " AND enti_nb_id = ".$_POST["busca_motorista"];
		}
		if(!empty($_POST["busca_data"]) && !empty($_POST["busca_empresa"])){
			$carregando = "Carregando...";
		}
		if(empty($_POST["busca_data"])){
			$_POST["busca_data"] = date("Y-m");
		}
		if(!empty($_POST["busca_empresa"])){
			$_POST["busca_empresa"] = (int)$_POST["busca_empresa"];
		}
		$extraMotorista = " AND enti_nb_empresa = ".$_POST["busca_empresa"];

		//CAMPOS DE CONSULTA{
			$c = [
				combo_net("Funcionário:", "busca_motorista", (!empty($_POST["busca_motorista"])? $_POST["busca_motorista"]: ""), 3, "entidade", "", " AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')".$extraMotorista.$extraEmpresaMotorista, "enti_tx_matricula"),
				campo_mes("Data*:",     "busca_data",      (!empty($_POST["busca_data"])?      $_POST["busca_data"]     : ""), 2)
			];

			if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
				array_unshift($c, combo_net("Empresa*:", "busca_empresa",   (!empty($_POST["busca_empresa"])?   $_POST["busca_empresa"]  : ""), 3, "empresa", "onchange=selecionaMotorista(this.value)", $extraEmpresa));
			}
		//}
		$botao_imprimir =
			"<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>
			<script>
    			function imprimir() {
    			// Abrir a caixa de diálogo de impressão
    			window.print();
    			}
			</script>";

		//BOTOES{
			$b = [
				botao("Buscar", "buscar", "", "", "", "","btn btn-success"),
				botao("Cadastrar Abono", "redirParaAbono", "", "", "", 1),
				$botao_imprimir
			];
		//}

		abre_form("Filtro de Busca");
		linha_form($c);
		fecha_form($b, "<span id=dadosResumo><b>".$carregando."</b></span>");
		
		echo 
			"<div id='tituloRelatorio'>
				<h1>Não Conformidade</h1>
				<img id='logo' style='width: 150px' src='".$CONTEX["path"]."/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
			</div>
			<style>
				#tituloRelatorio{
					display: none;
				}
			</style>"
		;

		//function buscar_endosso(){
			$counts = [
				"total" => 0,								//$countEndosso
				"naoConformidade" => 0,						//$countNaoConformidade
				"verificados" => 0,							//countVerificados
				"endossados" => ["sim" => 0, "nao" => 0],	//countEndossados e $countNaoEndossados
			];
			if(!empty($_POST["busca_empresa"])){
				$date = new DateTime($_POST["busca_data"]);

				$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $date->format("m"), $date->format("Y"));

				$sqlMotorista = query(
					"SELECT * FROM entidade"
						." WHERE enti_tx_status = 'ativo'"
							." AND enti_nb_empresa = ".$_POST["busca_empresa"]
							." AND (enti_tx_ocupacao IN ('Motorista', 'Ajudante','Funcionário') AND enti_tx_dataCadastro < '".$date->format("Y-m-t")."')"
							." ".$extra
						." ORDER BY enti_tx_nome;"
				);
				// Caso tenha que voltar o codigo
				// $sqlMotorista = query(
				// 	"SELECT * FROM entidade
				// 		WHERE enti_tx_ocupacao IN ('Motorista', 'Ajudante')
				// 			AND enti_nb_empresa = ".$_POST["busca_empresa"]." ".$extra."
				// 			AND enti_tx_status = 'ativo'
				// 		ORDER BY enti_tx_nome"
				// );
				while ($aMotorista = carrega_array($sqlMotorista)) {
					$counts["total"]++;
					if(empty($aMotorista["enti_tx_nome"]) || empty($aMotorista["enti_tx_matricula"])){
						continue;
					}
	
					//Pegando e formatando registros dos dias{
						for ($i = 1; $i <= $daysInMonth; $i++) {
							$dataVez = $date->format("Y-m")."-".str_pad($i, 2, 0, STR_PAD_LEFT);
							if($dataVez < $aMotorista["enti_tx_dataCadastro"]){
								continue;
							}
							if($dataVez >= date("Y-m-d")){
								break;
							}
							
							$aDetalhado = diaDetalhePonto($aMotorista["enti_tx_matricula"], $dataVez);
							
							$row = array_values(array_merge([verificaTolerancia($aDetalhado["diffSaldo"], $dataVez, $aMotorista["enti_nb_id"])], $aDetalhado));
							for($f = 0; $f < sizeof($row)-1; $f++){
								if($f == 13){//Se for da coluna "Jornada Prevista", não apaga
									continue;
								}
								if($row[$f] == "00:00"){
									$row[$f] = "";
								}
							}
							$aDia[] = $row;
						}
					//}
					criarFuncoesDeAjuste();
					
					if (count($aDia) > 0) {

						$aEndosso = carrega_array(query(
							"SELECT user_tx_login, endo_tx_dataCadastro, endo_tx_ate 
								FROM endosso JOIN user ON endo_nb_userCadastro = user_nb_id 
								WHERE endo_tx_status = 'ativo' 
									AND '".$_POST["busca_data"]."' BETWEEN endo_tx_de AND endo_tx_ate
									AND endo_nb_entidade = '".$aMotorista["enti_nb_id"]."'
									AND endo_tx_matricula = '".$aMotorista["enti_tx_matricula"]."'
								LIMIT 1"
						));
						if (is_array($aEndosso) && count($aEndosso) > 0) {
							$counts["endossados"]++;
							$infoEndosso = " - Endossado por ".$aEndosso["user_tx_login"]." em ".data($aEndosso["endo_tx_dataCadastro"], 1);
							$aIdMotoristaEndossado[] = $aMotorista["enti_nb_id"];
							$aMatriculaMotoristaEndossado[] = $aMotorista["enti_tx_matricula"];
						} else {
							$infoEndosso = "";
							$counts["endossados"]["nao"]++;
						}

						$aEmpresa = carregar("empresa", $aMotorista["enti_nb_empresa"]);

						if ($aEmpresa["empr_nb_parametro"] > 0) {
							$aParametro = carregar("parametro", $aEmpresa["empr_nb_parametro"]);
							$convencaoPadrao = "| Convenção Padrão? Sim";
							foreach(["tx_jornadaSemanal", "tx_jornadaSabado", "tx_percHESemanal", "tx_percHEEx"] as $campo){
								if($aParametro["para_".$campo] != $aMotorista["enti_".$campo]){
									$convencaoPadrao = "| Convenção Padrão? Não";
									break;
								}
							}
							if($aParametro["para_nb_id"] != $aMotorista["enti_nb_parametro"]){
								$convencaoPadrao = "| Convenção Padrão? Não";
							}
						}
						
						$dadosParametro = carrega_array(query(
							"SELECT para_tx_tolerancia, para_tx_dataCadastro, para_nb_qDias FROM parametro 
								JOIN entidade ON para_nb_id = enti_nb_parametro 
								WHERE enti_nb_parametro = ".$aMotorista["enti_nb_parametro"]." 
								LIMIT 1;"
						));
						$dataCicloProx = strtotime($dadosParametro["para_tx_dataCadastro"]);
						if($dataCicloProx !== false){
							while(!empty($aEndosso["endo_tx_ate"]) && $dataCicloProx < strtotime($aEndosso["endo_tx_ate"])){
								$dataCicloProx += intval($dadosParametro["para_nb_qDias"])*60*60*24;
							}
						}
						
						$saldoAnterior = mysqli_fetch_assoc(
							query(
								"SELECT endo_tx_saldo FROM endosso
									WHERE endo_tx_matricula = '".$aMotorista["enti_tx_matricula"]."'
										AND endo_tx_ate < '".$_POST["busca_data"]."'
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
						}

						$cab = [
							"", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
							"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO DIÁRIO / SEMANAL", "H.E. ".$aMotorista["enti_tx_percHESemanal"]."%", "H.E. ".$aMotorista["enti_tx_percHEEx"]."%",
							"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO(**)"
						];
				
	
						$saldosMotorista = 
							"<div class='table-responsive'>
								<table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact' id='saldo'>
									<thead><tr>
										<th>Saldo Anterior:</th>
										<th>Saldo do Período:</th>
										<th>Saldo Final:</th>
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

						abre_form("[".$aMotorista["enti_tx_matricula"]."] ".$aMotorista["enti_tx_nome"]." | ".$aEmpresa["empr_tx_nome"]." ".$infoEndosso." ".$convencaoPadrao." ".$saldosMotorista);
	
						$tolerancia = intval($dadosParametro["para_tx_tolerancia"]);

						$saldoColIndex = 20;
						
						for($f = 0; $f < count($aDia); $f++){
							$keys = array_keys($aDia[$f]);
							$hasUnconformities = false;
							foreach($keys as $key){
								if(strpos($aDia[$f][$key], "fa-warning") !== false){
									$hasUnconformities = true;
									$counts["naoConformidade"] += substr_count($aDia[$f][$key], "fa-warning");
								}
								if(is_int(strpos($aDia[$f][$key], "fa-info-circle"))){
									if(is_int(strpos($aDia[$f][$key], "color:red;")) || is_int(strpos($aDia[$f][$key], "color:orange;"))){
										$hasUnconformities = true;
										$counts["naoConformidade"] += substr_count($aDia[$f][$key], "fa-warning");
									}
								}
							}
							$aDia[$f]["exibir"] = !(($_POST["busca_situacao"] == "Não conformidade" && !$hasUnconformities));

							if(empty($aDia[$f][$saldoColIndex])){
								$aDia[$f][$saldoColIndex] = "00:00";	
							}

							$saldoStr = str_replace("<b>", "", $aDia[$f][$saldoColIndex]);
							$saldoStr = explode(":", $saldoStr);
							$saldo = intval($saldoStr[0])*60;
							$saldo += ($saldoStr[0] == "-"? -1: 1)*intval($saldoStr[1]);

							if($saldo >= -($tolerancia) && $saldo <= $tolerancia){
								$aDia[$f][$saldoColIndex] = "00:00";
							}
						}

						$aDia[] = array_values(array_merge(["", "", "", "", "", "", "<b>TOTAL</b>"], $totalResumo));
						$aDia[count($aDia)-1]["exibir"] = True;

						$qtt = count($aDia);
						for($f=0; $f<$qtt; $f++){
							if(isset($aDia[$f]["exibir"]) && !$aDia[$f]["exibir"]){
								$aDia = array_merge(array_slice($aDia, 0, $f), array_slice($aDia, $f+1));
								$f--;
							}
						}

						for($f = 0; $f < count($aDia); $f++){
							unset($aDia[$f]["exibir"]);
						}

						grid2($cab, $aDia);
						fecha_form();
	
						$aSaldo[$aMotorista["enti_tx_matricula"]] = $totalResumo["diffSaldo"];
					}
	
					$totalResumo = ["diffRefeicao" => "00:00", "diffEspera" => "00:00", "diffDescanso" => "00:00", "diffRepouso" => "00:00", "diffJornada" => "00:00", "jornadaPrevista" => "00:00", "diffJornadaEfetiva" => "00:00", "maximoDirecaoContinua" => "", "intersticio" => "00:00", "he50" => "00:00", "he100" => "00:00", "adicionalNoturno" => "00:00", "esperaIndenizada" => "00:00", "diffSaldo" => "00:00"];
					unset($aDia);
				}
			}
		//}
		echo "<div class='printable'></div><style>";
		include "css/nao_conformidade.css";
		echo "</style>";

		rodape();

		$counts["message"] = "<b>Total: ".$counts["total"]." | Não Conformidades: ".$counts["naoConformidade"]."</b>";

		$select2URL = 
			$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/select2.php"
			."?path=".$CONTEX["path"]
			."&tabela=entidade"
			."&extra_limite=15"
			."&extra_busca=enti_tx_matricula"
		; // Utilizado dentro de endosso_html.php
		
		include "html/endosso_html.php";
		echo 
			"<script>
				window.onload = function() {
					document.getElementById('dadosResumo').innerHTML = '".$counts["message"]."';
				};
			</script>"
		;
	}