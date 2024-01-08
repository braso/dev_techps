<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
		if(!isset($_GET['debug'])){
			echo '<div style="text-align:center; vertical-align: center; height: 100%; padding-top: 20%">Esta página está em desenvolvimento.</div>';
			exit;
		}
	//*/

	include "funcoes_ponto.php"; //Conecta incluso dentro de funcoes_ponto

	function index() {
		global $CACTUX_CONF, $totalResumo;
	
		cabecalho('Espelho de Ponto');
		
		$extraBuscaMotorista = '';
		if ($_SESSION['user_tx_nivel'] == 'Motorista') {
			$_POST['busca_motorista'] = $_SESSION['user_nb_entidade'];
			$extraBuscaMotorista = " AND enti_nb_id = '$_SESSION[user_nb_entidade]'";
		}
	
		if (!empty($_POST['busca_motorista'])) {
			$aMotorista = carregar('entidade', $_POST['busca_motorista']);
			$aDadosMotorista = [$aMotorista['enti_tx_matricula']];
			$aEmpresa = carregar('empresa', $aMotorista['enti_nb_empresa']);
		}

		$extraEmpresa = '';
		if ($_SESSION['user_nb_empresa'] > 0 && $_SESSION['user_tx_nivel'] != 'Administrador' && $_SESSION['user_tx_nivel'] != 'Super Administrador') {
			$extraEmpresa = " AND enti_nb_empresa = '$_SESSION[user_nb_empresa]'";
		}
	
		if (!isset($_POST['busca_data1']) || $_POST['busca_data1'] == '') {
			$_POST['busca_data1'] = date("Y-m-01");
		}
	
		if (!isset($_POST['busca_data2']) || $_POST['busca_data2'] == '') {
			$_POST['busca_data2'] = date("Y-m-d");
		}
	
		//CAMPOS DE CONSULTA
		$c = [
			combo_net('Motorista*:', 'busca_motorista', $_POST['busca_motorista']?? '', 4, 'entidade', '', " AND enti_tx_tipo = \"Motorista\" $extraEmpresa $extraBuscaMotorista", 'enti_tx_matricula')
			,campo_data('Data Início:', 'busca_data1', $_POST['busca_data1']?? '', 2)
			,campo_data('Data Fim:', 'busca_data2', $_POST['busca_data2']?? '', 2)
		];
	
		//BOTOES
		$b = [botao("Buscar", 'index')];
		if ($_SESSION['user_tx_nivel'] != 'Motorista') {
			$b[] = botao("Cadastrar Abono", 'layout_abono');
		}
	
		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);
	
		// $cab = array("MATRÍCULA", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA", "REFEIÇÃO", "ESPERA", "ATRASO", "EFETIVA", "PERÍODO TOTAL", "INTERSTÍCIO DIÁRIO", "INT. SEMANAL", "ABONOS", "FALTAS", "FOLGAS", "H.E.", "H.E. 100%", "ADICIONAL NOTURNO", "ESPERA INDENIZADA", "OBSERVAÇÕES");
		$cab = array(
			"", "MAT.", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
			"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO", "HE 50%", "HE&nbsp;100%",
			"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO(**)"
		);
	
		// Converte as datas para objetos DateTime
		$startDate = !empty($_POST['busca_data1'])? new DateTime($_POST['busca_data1']): '';
		$endDate   = !empty($_POST['busca_data2'])? new DateTime($_POST['busca_data2']): '';
	
		// Loop for para percorrer as datas


		if (!empty($_POST['busca_data1']) && !empty($_POST['busca_data2']) && !empty($_POST['busca_motorista'])){
			$aDia = [];
			for ($date = $startDate; $date <= $endDate; $date->modify('+1 day')) {
				$dataVez = $date->format('Y-m-d');
				$aDetalhado = diaDetalhePonto($aMotorista['enti_tx_matricula'], $dataVez);

				if(isset($aDetalhado['fimJornada'][0]) && count($aDetalhado['fimJornada'][0]) > 0 && $aDetalhado['fimJornada'][0] < $aDetalhado['inicioJornada'][0]){
					array_splice($aDetalhado['fimJornada'], 1, 0, 'D+1');
				}
				// if(isset($aDetalhado['fimJornada'][0]) && (strpos($aDetalhado['fimJornada'][0], ':00') !== false) && date('Y-m-d', strtotime($aDetalhado['fimJornada'][0])) != $dataVez){
				// 	array_splice($aDetalhado['fimJornada'], 1, 0, 'D+1');
				// }
								
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
				
				$row = array_values(array_merge([verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])], $aDadosMotorista, $aDetalhado));
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
	
			if ($aEmpresa['empr_nb_parametro'] > 0) {
				$aParametro = carregar('parametro', $aEmpresa['empr_nb_parametro']);
				if (
					$aParametro['para_tx_jornadaSemanal'] != $aMotorista['enti_tx_jornadaSemanal'] ||
					$aParametro['para_tx_jornadaSabado'] != $aMotorista['enti_tx_jornadaSabado'] ||
					$aParametro['para_tx_percentualHE'] != $aMotorista['enti_tx_percentualHE'] ||
					$aParametro['para_tx_percentualSabadoHE'] != $aMotorista['enti_tx_percentualSabadoHE'] ||
					$aParametro['para_nb_id'] != $aMotorista['enti_nb_parametro']
				) {
					$ehPadrao = 'Não';
				} else {
					$ehPadrao = 'Sim';
				}

				$convencaoPadrao = '| Convenção Padrão? ' . $ehPadrao;
			}

			$saldoAnterior = mysqli_fetch_assoc(
				query(
					"SELECT endo_tx_saldo FROM `endosso`
						WHERE endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
							AND endo_tx_ate < '".$_POST['busca_data1']."'
							AND endo_tx_status = 'ativo'
						ORDER BY endo_tx_ate DESC
						LIMIT 1;"
				)
			);
			if(isset($saldoAnterior['endo_tx_saldo'])){
				$saldoAnterior = $saldoAnterior['endo_tx_saldo'];
			}else{
				$saldoAnterior = '--:--';
			}

			$saldoFinal = '--:--';
			if($saldoAnterior != '--:--'){
				$saldoFinal = somarHorarios([$saldoAnterior, $totalResumo['diffSaldo']]);
			}


			$saldosMotorista = ' <div class="table-responsive">
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
				  </div>';
	
			abre_form("[$aMotorista[enti_tx_matricula]] $aMotorista[enti_tx_nome] | $aEmpresa[empr_tx_nome] $convencaoPadrao $saldosMotorista");
	
	?>
	
			<style>
				table thead tr th:nth-child(4),
				table thead tr th:nth-child(8),
				table thead tr th:nth-child(12),
				table td:nth-child(4),
				table td:nth-child(8),
				table td:nth-child(12) {
					border-right: 3px solid #d8e4ef !important;
				}
				.th-align {
				    text-align: center; /* Define o alinhamento horizontal desejado, pode ser center, left ou right */
				    vertical-align: middle !important; /* Define o alinhamento vertical desejado, pode ser top, middle ou bottom */
				    
				}
			</style>
		<?
			$aDia[] = array_values(array_merge(array('', '', '', '', '', '', '', '<b>TOTAL</b>'), $totalResumo));
			
			grid2($cab, $aDia, "Jornada Semanal (Horas): $aMotorista[enti_tx_jornadaSemanal]");
			fecha_form();
		}
	
		rodape();
	
		?>
		<style>

		#saldo {
			width: 50% !important;
			margin-top: 9px !important;
			text-align: center;
		}
		</style>
	
		<form name="form_ajuste_ponto" method="post">
			<input type="hidden" name="acao" value="layout_ajuste">
			<input type="hidden" name="id" value="<?= $aMotorista['enti_nb_id'] ?>">
			<input type="hidden" name="data">
		</form>
	
		<script>
			function ajusta_ponto(data, motorista) {
				document.form_ajuste_ponto.data.value = data;
				document.form_ajuste_ponto.id.value = motorista;
				document.form_ajuste_ponto.submit();
			}
		</script>
	<?
	
	}
?>
