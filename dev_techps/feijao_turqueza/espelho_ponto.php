<?php
	include "funcoes_ponto.php"; //Conecta incluso dentro de funcoes_ponto

	function index() {
		global $CACTUX_CONF, $totalResumo;
	
		cabecalho('Espelho de Ponto');
	
		if ($_SESSION['user_tx_nivel'] == 'Motorista') {
			$_POST['busca_motorista'] = $_SESSION['user_nb_entidade'];
			$extraBuscaMotorista = " AND enti_nb_id = '$_SESSION[user_nb_entidade]'";
		}
	
		if ($_POST['busca_motorista']) {
			$aMotorista = carregar('entidade', $_POST['busca_motorista']);
			$aDadosMotorista = [$aMotorista['enti_tx_matricula']];
			$aEmpresa = carregar('empresa', $aMotorista['enti_nb_empresa']);
		}

		$extraEmpresa = '';
		if ($_SESSION['user_nb_empresa'] > 0 && $_SESSION['user_tx_nivel'] != 'Administrador' && $_SESSION['user_tx_nivel'] != 'Super Administrador') {
			$extraEmpresa = " AND enti_nb_empresa = '$_SESSION[user_nb_empresa]'";
		}
	
		if ($_POST['busca_data1'] == '') {
			$_POST['busca_data1'] = date("Y-m-01");
		}
	
		if ($_POST['busca_data2'] == '') {
			$_POST['busca_data2'] = date("Y-m-d");
		}
	
		//CAMPOS DE CONSULTA
		$c = [
			combo_net('Motorista*:', 'busca_motorista', $_POST['busca_motorista'], 4, 'entidade', '', " AND enti_tx_tipo = \"Motorista\" $extraEmpresa $extraBuscaMotorista", 'enti_tx_matricula')
			//, campo_mes('Data:','busca_data',$_POST[busca_data],2)
			,campo_data('Data Início:', 'busca_data1', $_POST['busca_data1'], 2)
			,campo_data('Data Fim:', 'busca_data2', $_POST['busca_data2'], 2)
			// ,combo('Sem Inconsistência: ', 'busca_inconsistencia', $_POST['busca_inconsistencia'], 2, ['Não', 'Sim']),
			// combo('Com saldo previsto: ', 'busca_saldo_previsto', $_POST['busca_saldo_previsto'], 2, ['Sim', 'Não'])
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
		$startDate = new DateTime($_POST['busca_data1']);
		$endDate = new DateTime($_POST['busca_data2']);
	
		// Loop for para percorrer as datas


		if ($_POST['busca_data1'] && $_POST['busca_data2'] && $_POST['busca_motorista']) {
	
			for ($date = $startDate; $date <= $endDate; $date->modify('+1 day')) {
				$dataVez = $date->format('Y-m-d');
				$aDetalhado = diaDetalhePonto($aMotorista['enti_tx_matricula'], $dataVez);
				
				$row = array_values(array_merge(array(verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])), $aDadosMotorista, $aDetalhado));
				$exibir = True;
				for($f = 0; $f < sizeof($row); $f++){
					if(strpos($row[$f], 'fa-warning') !== false && $_POST['busca_inconsistencia'] == 'Sim'){
						$exibir = False;
						// $f2 = 8;
						// foreach($totalResumo as $key => $value){

						// 	print_r($totalResumo[$key]);
						// 	$totalResumo[$key] = explode(':', $totalResumo[$key]);
						// 	print_r(' => ');
						// 	print_r($totalResumo[$key]);
						// 	$totalResumo[$key] = intval($totalResumo[$key][0])*60+($totalResumo[$key][0][0] == '-'? -1:1)*intval($totalResumo[$key][1]);
						// 	print_r(' => ');
						// 	print_r($totalResumo[$key]);
						// 	print_r('<br>');

						// 	print_r($row[$f2]);
						// 	$row[$f2] = explode(':', substr($row[$f2], -5));
						// 	print_r(' => ');
						// 	print_r($row[$f2]);
						// 	$row[$f2] = intval($row[$f2][0])*60 + ($row[$f2][0][0] == '-'? -1:1)*intval($row[$f2][1]);
						// 	print_r(' => ');
						// 	print_r($row[$f2]);
						// 	print_r('<br>');
							
						// 	print_r($totalResumo[$key]);
						// 	$totalResumo[$key] = $totalResumo[$key] + (($totalResumo[$key]<0?1:-1)*$row[$f2]);
						// 	print_r(' => ');
						// 	print_r($totalResumo[$key]);
						// 	$totalResumo[$key] = sprintf('00',(intval($totalResumo[$key]/60))).":".sprintf('00', ($totalResumo[$key]-intval($totalResumo[$key]/60)*60));
						// 	print_r(' => ');
						// 	print_r($totalResumo[$key]);
						// 	print_r('<br><br>');
						// 	$f2++;
						// }
						break;
					}
					if($row[$f] == "00:00"){
						$row[$f] = "";
					}
				}
				if($exibir){
					$aDia[] = $row;
				}
			}

			var_dump($totalResumo);
			print('<br>');
	
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

			$saldosMotorista = ' <div class="table-responsive">
					<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact" id="saldo">
					  <thead><tr>
						<th>Saldo Anterior:</th>
						<th>Saldo do Período:</th>
						<th>Saldo Final:</th>
					  </thead></tr>
					  <tbody>
						<tr>
						  <td>--:--</td>
						  <td>--:--</td>
						  <td>--:--</td>
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
			
			$toleranciaStr = carrega_array(query('SELECT parametro.para_tx_tolerancia FROM entidade JOIN parametro ON enti_nb_parametro = para_nb_id WHERE enti_nb_parametro ='.$aMotorista['enti_nb_parametro'].';'))[0];
			$toleranciaStr = explode(':', $toleranciaStr);

			$tolerancia = intval($toleranciaStr[0])*60;

			if($toleranciaStr[0] == '-'){
				$tolerancia -= intval($toleranciaStr[1]);
			}else{
				$tolerancia += intval($toleranciaStr[1]);
			}
			
			for($f = 0; $f < count($aDia); $f++){
				$saldoStr = explode(':', $aDia[$f][count($aDia[$f])-1]);
				$saldo = intval($saldoStr[0])*60;
				if($saldoStr[0] == '-'){
					$saldo -= intval($saldoStr[1]);
				}else{
					$saldo += intval($saldoStr[1]);
				}
				if($saldo >= -($tolerancia) && $saldo <= $tolerancia){
					$aDia[$f][count($aDia[$f])-1] = '00:00';
				}
			}
			
	
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