<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	include "funcoes_ponto.php"; // conecta.php importado dentro de funcoes_ponto	

	function cadastrar(){
		$url = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/'));
		header('Location: ' . 'https://braso.mobi' . $url . '/cadastro_endosso');
		exit();
	}

	function index(){
		global $totalResumo, $CONTEX;

		if(!empty($_GET['acao']) && $_GET['acao'] == 'index'){//Se estiver pesquisando
			//Conferir se os campos foram inseridos.
			if(empty($_GET['busca_data']) || empty($_GET['busca_motorista'])){
				echo '<script>alert("Insira data e motorista para pesquisar.");</script>';
			}
		}

		cabecalho('Não Conformidade');

		if ($_SESSION['user_nb_empresa'] > 0 && is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$extraEmpresa = " AND empr_nb_id = '" . $_SESSION['user_nb_empresa'] . "'";
			$extraEmpresaMotorista = " AND enti_nb_empresa = '" . $_SESSION['user_nb_empresa'] . "'";
		}else{
			$extraEmpresa = '';
			$extraEmpresaMotorista = '';
		}

		$extra = '';
		$_GET['busca_situacao'] = 'Não conformidade';
		foreach(['busca_empresa', 'busca_data', 'busca_motorista', 'busca_situacao'] as $campo){
			if(isset($_GET[$campo]) && !empty($_GET[$campo])){
				$_POST[$campo] = $_GET[$campo];
			}
		}

		if(!empty($_POST['busca_motorista'])){
			$extra = " AND enti_nb_id = " . $_POST['busca_motorista'];
		}
		if(!empty($_POST['busca_data']) && !empty($_POST['busca_empresa'])){
			$carregando = "Carregando...";
		}
		if(empty($_POST['busca_data'])){
			$_POST['busca_data'] = date("Y-m");
		}
		if(!empty($_POST['busca_empresa'])){
			$_POST['busca_empresa'] = (int)$_POST['busca_empresa'];
		}else{
			$_POST['busca_empresa'] = $_SESSION['user_nb_empresa'];
		}
		$extraMotorista = " AND enti_nb_empresa = " . $_POST['busca_empresa'];

		//CAMPOS DE CONSULTA{
			$c = [
				campo_mes('* Data:',    'busca_data',      (!empty($_POST['busca_data'])?      $_POST['busca_data']     : ''), 2),
				combo_net('Motorista:', 'busca_motorista', (!empty($_POST['busca_motorista'])? $_POST['busca_motorista']: ''), 3, 'entidade', '', ' AND enti_tx_tipo = "Motorista"' . $extraMotorista . $extraEmpresaMotorista, 'enti_tx_matricula')
			];

			if(is_int(strpos($_SESSION['user_tx_nivel'], 'Super Administrador'))){
				array_unshift($c, combo_net('* Empresa:', 'busca_empresa',   (!empty($_POST['busca_empresa'])?   $_POST['busca_empresa']  : ''), 3, 'empresa', 'onchange=selecionaMotorista(this.value)', $extraEmpresa));
			}
		//}

		//BOTOES{
			$b = [
				botao("Buscar", 'index', '', '', '', 1),
				botao("Cadastrar Abono", 'layout_abono', '', '', '', 1)
			];
		//}

		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);

		$cab = [
			"", "MAT.", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
			"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO DIÁRIO / SEMANAL", "HE 50%", "HE&nbsp;100%",
			"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO(*)"
		];

		//function buscar_endosso(){
			$counts = [
				'total' => 0,								//$countEndosso
				'naoConformidade' => 0,						//$countNaoConformidade
				'verificados' => 0,							//countVerificados
				'endossados' => ['sim' => 0, 'nao' => 0],	//countEndossados e $countNaoEndossados
			];
			if(!empty($_POST['busca_data']) && !empty($_POST['busca_empresa']) && !empty($_POST['busca_motorista'])){

				$date = new DateTime($_POST['busca_data']);

				$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $date->format('m'), $date->format('Y'));
	
				$sqlMotorista = query(
					"SELECT * FROM entidade
						WHERE enti_tx_tipo = 'Motorista'
							AND enti_nb_empresa = ".$_POST['busca_empresa']." ".$extra."
							AND enti_tx_status != 'inativo'
						ORDER BY enti_tx_nome"
				);
				while ($aMotorista = carrega_array($sqlMotorista)) {
					$counts['total']++;
					if(empty($aMotorista['enti_tx_nome']) || empty($aMotorista['enti_tx_matricula'])){
						continue;
					}
	
					//Pegando e formatando registros dos dias{
						for ($i = 1; $i <= $daysInMonth; $i++) {
							$dataVez = $_POST['busca_data']."-".str_pad($i, 2, 0, STR_PAD_LEFT);
							$aDetalhado = diaDetalhePonto($aMotorista['enti_tx_matricula'], $dataVez);

							if(isset($aDetalhado['fimJornada'][0]) && (strpos($aDetalhado['fimJornada'][0], ':00') !== false) && date('Y-m-d', strtotime($aDetalhado['fimJornada'][0])) != $dataVez){
								array_splice($aDetalhado['fimJornada'], 1, 0, 'D+1');
							}
	
							//Converter array em string{
								$legendas = mysqli_fetch_all(query(
									"SELECT UNIQUE moti_tx_legenda FROM motivo 
										WHERE moti_tx_legenda IS NOT NULL;"
									), 
									MYSQLI_ASSOC
								);
								foreach(['inicioJornada', 'fimJornada', 'inicioRefeicao', 'fimRefeicao'] as $tipo){
									if (count($aDetalhado[$tipo]) > 0){
										for($f = 0; $f < count($aDetalhado[$tipo]); $f++){
											//Formatar datas para hora e minutos sem perder o D+1, caso tiver
											if(strpos($aDetalhado[$tipo][$f], ':00', strlen($aDetalhado[$tipo][$f])-3) !== false){
												if(strpos($aDetalhado[$tipo][$f], 'D+1') !== false){
													$aDetalhado[$tipo][$f] = explode(' ', $aDetalhado[$tipo][$f]);
													$aDetalhado[$tipo][$f] = substr($aDetalhado[$tipo][$f][1], 0, strlen($aDetalhado[$tipo][$f][1])-3)+$aDetalhado[$tipo][$f][2];
												}else{
													$aDetalhado[$tipo][$f] = date('H:i', strtotime($aDetalhado[$tipo][$f]));
												}
											}
										}
										$aDetalhado[$tipo] = implode("<br>", $aDetalhado[$tipo]);
										foreach($legendas as $legenda){
											$aDetalhado[$tipo] = str_replace('<br><strong>'.$legenda['moti_tx_legenda'].'</strong>', ' <strong>'.$legenda['moti_tx_legenda'].'</strong>', $aDetalhado[$tipo]);
										}
									}else{
										$aDetalhado[$tipo] = '';
									}
								}
							//}
		
							$row = array_values(array_merge([verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])], [$aMotorista['enti_tx_matricula']], $aDetalhado));
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

					if (count($aDia) > 0) {

						$aEndosso = carrega_array(query(
							"SELECT user_tx_login, endo_tx_dataCadastro, endo_tx_ate 
								FROM endosso JOIN user ON endo_nb_userCadastro = user_nb_id 
								WHERE endo_tx_mes = '" . substr($_POST['busca_data'], 0, 7) . '-01' . "' AND endo_nb_entidade = '" . $aMotorista['enti_nb_id'] . "'
									AND endo_tx_matricula = '" . $aMotorista['enti_tx_matricula'] . "' 
									AND endo_tx_status = 'ativo' 
								LIMIT 1"
						));
						if (is_array($aEndosso) && count($aEndosso) > 0) {
							$counts['endossados']++;
							$infoEndosso = " - Endossado por " . $aEndosso['user_tx_login'] . " em " . data($aEndosso['endo_tx_dataCadastro'], 1);
							$aIdMotoristaEndossado[] = $aMotorista['enti_nb_id'];
							$aMatriculaMotoristaEndossado[] = $aMotorista['enti_tx_matricula'];
						} else {
							$infoEndosso = '';
							$counts['endossados']['nao']++;
						}

						$aIdMotorista[] 		= $aMotorista['enti_nb_id'];
						$aMatriculaMotorista[] 	= $aMotorista['enti_tx_matricula'];						

						$aEmpresa = carregar('empresa', $aMotorista['enti_nb_empresa']);

						if ($aEmpresa['empr_nb_parametro'] > 0) {
							$aParametro = carregar('parametro', $aEmpresa['empr_nb_parametro']);
							$convencaoPadrao = '| Convenção Padrão? Sim';
							foreach(['tx_jornadaSemanal', 'tx_jornadaSabado', 'tx_percentualHE', 'tx_percentualSabadoHE'] as $campo){
								if($aParametro['para_'.$campo] != $aMotorista['enti_'.$campo]){
									$convencaoPadrao = '| Convenção Padrão? Não';
									break;
								}
							}
							if($aParametro['para_nb_id'] != $aMotorista['enti_nb_parametro']){
								$convencaoPadrao = '| Convenção Padrão? Não';
							}
						}
						
						$dadosParametro = carrega_array(query(
							'SELECT para_tx_tolerancia, para_tx_dataCadastro, para_nb_qDias FROM parametro 
								JOIN entidade ON para_nb_id = enti_nb_parametro 
								WHERE enti_nb_parametro = '.$aMotorista['enti_nb_parametro'].' 
								LIMIT 1;'
						));
						$dataCicloProx = strtotime($dadosParametro['para_tx_dataCadastro']);
						if($dataCicloProx !== false){
							while($dataCicloProx < strtotime($aEndosso['endo_tx_ate'])){
								$dataCicloProx += intval($dadosParametro['para_nb_qDias'])*60*60*24;
							}
						}
						
						$saldoAnterior = mysqli_fetch_all(
							query(
								"SELECT endo_tx_saldo FROM `endosso`
									WHERE endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
										AND endo_tx_ate < '".$_POST['busca_data']."-01'
										AND endo_tx_status = 'ativo'
									ORDER BY endo_tx_ate DESC
									LIMIT 1;"
							),
							MYSQLI_ASSOC
						)[0];
						if(isset($saldoAnterior['endo_tx_saldo'])){
							$saldoAnterior = $saldoAnterior['endo_tx_saldo'];
						}else{
							$saldoAnterior = '--:--';
						}

						$saldoFinal = '--:--';
						if($saldoAnterior != '--:--'){
							$saldoFinal = somarHorarios([$saldoAnterior, $totalResumo['diffSaldo']]);
						}
	
						$saldosMotorista = 
							'<div class="table-responsive">
								<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact" id="saldo">
									<thead><tr>
										<th>Saldo Anterior:</th>
										<th>Saldo do Período:</th>
										<th>Saldo Final:</th>
									</thead></tr>
									<tbody>
										<tr>
										<td>'.$saldoAnterior.'</td>
										<td>'.$totalResumo['diffSaldo'].'</td>
										<td>'.$saldoFinal.'</td>
										</tr>
									</tbody>
									</table>
							</div>
							Fim do ciclo: '.date('d/m/Y', $dataCicloProx);
						

						abre_form("[$aMotorista[enti_tx_matricula]] $aMotorista[enti_tx_nome] | $aEmpresa[empr_tx_nome] $infoEndosso $convencaoPadrao $saldosMotorista");

						
						$aDia[] = array_values(array_merge(['', '', '', '', '', '', '', '<b>TOTAL</b>'], $totalResumo));
	
						$tolerancia = intval($dadosParametro['para_tx_tolerancia']);

						$exibir = True;
						$saldoColIndex = 21;
						
						for($f = 0; $f < count($aDia); $f++){
							
							$keys = array_keys($aDia[$f]);
							$hasUnconformities = false;
							foreach($keys as $key){
								if(strpos($aDia[$f][$key], 'fa-warning') !== false){
									$hasUnconformities = true;
									$counts['naoConformidade']++;
									break;
								}
							}
							if($_POST['busca_situacao'] == 'Não conformidade' && !$hasUnconformities){ //Se for pra aparecer apenas inconformidades e a linha não tiver inconformidades
								$exibir = False;
							}else{
								$exibir = True;
							}
							$aDia[$f]['exibir'] = $exibir;

							if(empty($aDia[$f][$saldoColIndex])){
								$aDia[$f][$saldoColIndex] = '00:00';	
							}

							$saldoStr = str_replace('<b>', '', $aDia[$f][$saldoColIndex]);
							$saldoStr = explode(':', $saldoStr);
							$saldo = intval($saldoStr[0])*60;
							$saldo += ($saldoStr[0] == '-'? -1: 1)*intval($saldoStr[1]);

							if($saldo >= -($tolerancia) && $saldo <= $tolerancia){
								$aDia[$f][$saldoColIndex] = '00:00';
							}
						}
						$qtt = count($aDia);
						for($f = 0; $f < $qtt;){
							if(isset($aDia[$f]['exibir']) && !$aDia[$f]['exibir']){
								unset($aDia[$f]);
							}else{
								$f++;
							}
						}
						
						grid2($cab, $aDia);
						fecha_form();
	
						$aSaldo[$aMotorista['enti_tx_matricula']] = $totalResumo['diffSaldo'];
					}
	
					$totalResumo = ['diffRefeicao' => '00:00', 'diffEspera' => '00:00', 'diffDescanso' => '00:00', 'diffRepouso' => '00:00', 'diffJornada' => '00:00', 'jornadaPrevista' => '00:00', 'diffJornadaEfetiva' => '00:00', 'maximoDirecaoContinua' => '', 'intersticio' => '00:00', 'he50' => '00:00', 'he100' => '00:00', 'adicionalNoturno' => '00:00', 'esperaIndenizada' => '00:00', 'diffSaldo' => '00:00'];

					unset($aDia);
				}
			}
		//}
		echo '<div class="printable"></div>';

		rodape();

		$counts['message'] = '<br><br><b>Total: '.$counts['total'].' | Não Conformidades: '.$counts['naoConformidade'].'</b>';
		
		include 'endosso_html.php';
	}
?>