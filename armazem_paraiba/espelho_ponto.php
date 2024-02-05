<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	include_once "funcoes_ponto.php"; //Conecta incluso dentro de funcoes_ponto

	function index() {
		global $CONTEX, $totalResumo;
	
		cabecalho('Espelho de Ponto');
		
		$extraBuscaMotorista = '';
		if ($_SESSION['user_tx_nivel'] == 'Motorista') {
			$_POST['busca_motorista'] = $_SESSION['user_nb_entidade'];
			$extraBuscaMotorista = " AND enti_nb_id = '$_SESSION[user_nb_entidade]'";
		}
	
		if (!empty($_POST['busca_motorista'])) {
			$aMotorista = carregar('entidade', $_POST['busca_motorista']);
			$aEmpresa = carregar('empresa', $aMotorista['enti_nb_empresa']);
		}

		$extraEmpresa = '';
		if ($_SESSION['user_nb_empresa'] > 0 && $_SESSION['user_tx_nivel'] != 'Administrador' && $_SESSION['user_tx_nivel'] != 'Super Administrador') {
			$extraEmpresa = " AND enti_nb_empresa = '$_SESSION[user_nb_empresa]'";
		}
	
		if (!isset($_POST['busca_data1']) || empty($_POST['busca_data1'])) {
			$_POST['busca_data1'] = date("Y-m-01");
		}
	
		if (!isset($_POST['busca_data2']) || empty($_POST['busca_data2'])) {
			$_POST['busca_data2'] = date("Y-m-d");
		}
	
		//CAMPOS DE CONSULTA
		$c = [
			combo_net('Motorista*:', 'busca_motorista', $_POST['busca_motorista']?? '', 4, 'entidade', '', " AND enti_tx_tipo = \"Motorista\" $extraEmpresa $extraBuscaMotorista", 'enti_tx_matricula')
			,campo_data('Data Início:', 'busca_data1', $_POST['busca_data1']?? '', 2)
			,campo_data('Data Fim:', 'busca_data2', $_POST['busca_data2']?? '', 2)
		];
	
		//BOTOES
		$b = [botao("Buscar", 'index','','','','','btn btn-success')];
		if ($_SESSION['user_tx_nivel'] != 'Motorista') {
			$b[] = botao("Cadastrar Abono", 'layout_abono');
		}
	
		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);
	
		// $cab = array("MATRÍCULA", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA", "REFEIÇÃO", "ESPERA", "ATRASO", "EFETIVA", "PERÍODO TOTAL", "INTERSTÍCIO DIÁRIO", "INT. SEMANAL", "ABONOS", "FALTAS", "FOLGAS", "H.E.", "H.E. 100%", "ADICIONAL NOTURNO", "ESPERA INDENIZADA", "OBSERVAÇÕES");
		$cab = array(
			"", "DATA", "<div style='margin:10px'>DIA</div>", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
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
				
				$row = array_values(array_merge([verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])], $aDetalhado));
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
			}elseif(!empty($aMotorista['enti_tx_banco'])){
				$saldoAnterior = $aMotorista['enti_tx_banco'];
				$saldoAnterior = $saldoAnterior[0] == '0' && strlen($saldoAnterior) > 5? substr($saldoAnterior, 1): $saldoAnterior;
			}else{
				$saldoAnterior = '--:--';
			}

			$saldoFinal = '--:--';
			if($saldoAnterior != '--:--'){
				$saldoFinal = somarHorarios([$saldoAnterior, $totalResumo['diffSaldo']]);
			}else{
				$saldoFinal = somarHorarios(['00:00', $totalResumo['diffSaldo']]);
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
				 
      $periodoPesquisa = 'PERÍODO DA BUSCA : '.date("d/m/Y", strtotime($_POST['busca_data1'])).' ATÉ '.date("d/m/Y", strtotime($_POST['busca_data2']));
      
			abre_form("[$aMotorista[enti_tx_matricula]] $aMotorista[enti_tx_nome] | $aEmpresa[empr_tx_nome] $convencaoPadrao | $periodoPesquisa $saldosMotorista");
	
	?>
	
			<style>
				table thead tr th:nth-child(3),
				table thead tr th:nth-child(7),
				table thead tr th:nth-child(11),
				table td:nth-child(3),
				table td:nth-child(7),
				table td:nth-child(11) {
					border-right: 3px solid #d8e4ef !important;
				}
				.th-align {
				    text-align: center; /* Define o alinhamento horizontal desejado, pode ser center, left ou right */
				    vertical-align: middle !important; /* Define o alinhamento vertical desejado, pode ser top, middle ou bottom */
				    
				}
			</style>
		<?
			$aDia[] = array_values(array_merge(array('', '', '', '', '', '', '<b>TOTAL</b>'), $totalResumo));
			
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
			<input type="hidden" name="data_de" value="<?=$_POST['busca_data1']?>">
			<input type="hidden" name="data_ate" value="<?=$_POST['busca_data2']?>">
		</form>
	<?
	}
?>