<?php
include "funcoes_ponto.php"; // NAO ALTERAR ORDEM
include "conecta.php";


function index() {
	global $totalResumo;

	cabecalho('Não Conformidade');

	if($_POST['busca_motorista']){
		$extra = " AND enti_nb_id = ".$_POST['busca_motorista'];
	}

	if($_POST[busca_data1] == ''){
		$_POST[busca_data1] = date("Y-m-01");
	}

	if($_POST[busca_data2] == ''){
		$_POST[busca_data2] = date("Y-m-d");
	}

	//CONSULTA
	$c[] = combo_net('Empresa:','busca_empresa',$_POST[busca_empresa],4,'empresa');
	// $c[] = campo_mes('Data:','busca_data',$_POST[busca_data],2);
	$c[] = campo_data('Data Início:','busca_data1',$_POST[busca_data1],2);
	$c[] = campo_data('Data Fim:','busca_data2',$_POST[busca_data2],2);
	$c[] = combo_net('Motorista:','busca_motorista',$_POST[busca_motorista],4,'entidade','',' AND enti_tx_tipo = "Motorista"','enti_tx_matricula');
	
	//BOTOES
	$b[] = botao("Buscar",'index');
	$b[] = botao("Cadastrar Abono",'layout_abono');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);
	
	// $cab = array("MATRÍCULA", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA", "REFEIÇÃO", "ESPERA", "ATRASO", "EFETIVA", "PERÍODO TOTAL", "INTERSTÍCIO DIÁRIO", "INT. SEMANAL", "ABONOS", "FALTAS", "FOLGAS", "H.E.", "H.E. 100%", "ADICIONAL NOTURNO", "ESPERA INDENIZADA", "OBSERVAÇÕES");
	$cab = array("", "MAT.", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
		"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA","MDC","INTERSTÍCIO","HE 50%", "HE&nbsp;100%",
		"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO");

	if($_POST[busca_data1] && $_POST[busca_data2] && $_POST[busca_empresa]){
		
		// $date = new DateTime($_POST[busca_data]);
		// $month = $date->format('m');
		// $year = $date->format('Y');
		
		// $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		
		$sqlMotorista = query("SELECT * FROM entidade WHERE enti_tx_tipo = 'Motorista' AND enti_tx_empresaCnpj = ".$_POST[busca_empresa]." $extra ORDER BY enti_tx_nome");
		while($aMotorista = carrega_array($sqlMotorista)){
			
			$startDate = new DateTime($_POST[busca_data1]);
			$endDate = new DateTime($_POST[busca_data2]);
			// for ($i = 1; $i <= $daysInMonth; $i++) {
			for ($date = $startDate; $date <= $endDate; $date->modify('+1 day')) {
				// $dataVez = $_POST[busca_data]."-".str_pad($i,2,0,STR_PAD_LEFT);
				$dataVez = $date->format('Y-m-d');
				
				$aDetalhado = diaDetalhePonto($aMotorista[enti_tx_matricula], $dataVez);

				if (
					(strpos($aDetalhado['diffRefeicao'], 'color:red;') !== false) ||
					(strpos($aDetalhado['diffEspera'], 'color:red;') !== false) ||
					(strpos($aDetalhado['diffDescanso'], 'color:red;') !== false) ||
					(strpos($aDetalhado['diffRepouso'], 'color:red;') !== false) ||
					(strpos($aDetalhado['diffJornada'], 'color:red;') !== false) ||
					(strpos($aDetalhado['jornadaPrevista'], 'color:red;') !== false) ||
					(strpos($aDetalhado['diffJornadaEfetiva'], 'color:red;') !== false) ||
					(strpos($aDetalhado['maximoDirecaoContinua'], 'color:red;') !== false) ||
					(strpos($aDetalhado['intersticio'], 'color:red;') !== false) ||
					(strpos($aDetalhado['he50'], 'color:red;') !== false) ||
					(strpos($aDetalhado['he100'], 'color:red;') !== false) ||
					(strpos($aDetalhado['adicionalNoturno'], 'color:red;') !== false) ||
					(strpos($aDetalhado['esperaIndenizada'], 'color:red;') !== false) ||
					(strpos($aDetalhado['diffSaldo'], 'color:red;') !== false)
					
				) {
					// print_r($aDetalhado);exit;
					$totalResumo = array(
						'diffRefeicao' => '00:00',
						'diffEspera' => '00:00',
						'diffDescanso' => '00:00',
						'diffRepouso' => '00:00',
						'diffJornada' => '00:00',
						'jornadaPrevista' => '00:00',
						'diffJornadaEfetiva' => '00:00',
						'maximoDirecaoContinua' => '',
						'intersticio' => '00:00',
						'he50' => '00:00',
						'he100' => '00:00',
						'adicionalNoturno' => '00:00',
						'esperaIndenizada' => '00:00',
						'diffSaldo' => '00:00'
					);

					// continue;
				}else{
					continue;
				}
				
				$aDia[] = array_values(array_merge(array(verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])), array($aMotorista[enti_tx_matricula]), $aDetalhado));
				
			}
			
			if(count($aDia) > 0){

				if($aMotorista[enti_nb_parametro] > 0 ){
					$aParametro = carregar('parametro', $aMotorista[enti_nb_parametro]);
					if( $aParametro[para_tx_jornadaSemanal] != $aMotorista[enti_tx_jornadaSemanal] ||
						$aParametro[para_tx_jornadaSabado] != $aMotorista[enti_tx_jornadaSabado] ||
						$aParametro[para_tx_percentualHE] != $aMotorista[enti_tx_percentualHE] ||
						$aParametro[para_tx_percentualSabadoHE] != $aMotorista[enti_tx_percentualSabadoHE]){
			
						$ehPadrão = 'Não';
					}else{
						$ehPadrão = 'Sim';
					}
					
					$convencaoPadrao = '| Convenção Padrão? ' . $ehPadrão;
					
				}

				abre_form("[$aMotorista[enti_tx_matricula]] $aMotorista[enti_tx_nome] $convencaoPadrao");
			
				$aDia[] = array_values(array_merge(array('','','','','','','','<b>TOTAL</b>'), $totalResumo));
				
				grid2($cab,$aDia,"Jornada Semanal (Horas): $aMotorista[enti_tx_jornadaSemanal]");
				fecha_form();

			}
			
			$totalResumo = array(
				'diffRefeicao' => '00:00',
				'diffEspera' => '00:00',
				'diffDescanso' => '00:00',
				'diffRepouso' => '00:00',
				'diffJornada' => '00:00',
				'jornadaPrevista' => '00:00',
				'diffJornadaEfetiva' => '00:00',
				'maximoDirecaoContinua' => '',
				'intersticio' => '00:00',
				'he50' => '00:00',
				'he100' => '00:00',
				'adicionalNoturno' => '00:00',
				'esperaIndenizada' => '00:00',
				'diffSaldo' => '00:00'
			);

			unset($aDia);

		}

	}

	rodape();

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
	</style>

	<form name="form_ajuste_ponto" method="post" target="_blank">
		<input type="hidden" name="acao" value="layout_ajuste">
		<input type="hidden" name="id" value="">
		<input type="hidden" name="data">
	</form>

	<script>
		function ajusta_ponto(data, motorista){
			document.form_ajuste_ponto.data.value = data;
			document.form_ajuste_ponto.id.value = motorista;
			document.form_ajuste_ponto.submit();
		}
	</script>
	<?

}
