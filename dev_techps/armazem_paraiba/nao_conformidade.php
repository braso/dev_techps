<?php
include "funcoes_ponto.php"; // NAO ALTERAR ORDEM
// include "conecta.php";


function index() {
	global $CONTEX, $totalResumo;

	cabecalho('Não Conformidade');

	if ($_SESSION['user_nb_empresa'] > 0 && is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
		$extraEmpresa = " AND empr_nb_id = '$_SESSION[user_nb_empresa]'";
		$extraEmpresaMotorista = " AND enti_nb_empresa = '$_SESSION[user_nb_empresa]'";
	}

	if ($_POST['busca_motorista']) {
		$extra = " AND enti_nb_id = " . $_POST['busca_motorista'];
	}

	if ($_POST['busca_data1'] == '') {
		$_POST['busca_data1'] = date("Y-m-01");
	}

	if ($_POST['busca_data2'] == '') {
		$_POST['busca_data2'] = date("Y-m-d");
	}

	if ($_POST['busca_data1'] && $_POST['busca_data2'] && $_POST['busca_empresa']) {
		$carregando = "Carregando...";
	}

	if ($_POST['busca_empresa']) {
		$extraMotorista = " AND enti_nb_empresa = '$_POST[busca_empresa]'";
	}

	//CONSULTA
	$c[] = combo_net('* Empresa:', 'busca_empresa', $_POST['busca_empresa'], 4, 'empresa', 'onchange=selecionaMotorista(this.value)', $extraEmpresa);
	// $c[] = campo_mes('Data:','busca_data',$_POST[busca_data],2);
	$c[] = campo_data('* Data Início:', 'busca_data1', $_POST['busca_data1'], 2);
	$c[] = campo_data('* Data Fim:', 'busca_data2', $_POST['busca_data2'], 2);
	$c[] = combo_net('Motorista:', 'busca_motorista', $_POST['busca_motorista'], 4, 'entidade', '', ' AND enti_tx_tipo = "Motorista"' . $extraMotorista . $extraEmpresaMotorista, 'enti_tx_matricula');

	//BOTOES
	$b[] = botao("Buscar", 'index', '', '', '', 1);
	$b[] = botao("Cadastrar Abono", 'layout_abono', '', '', '', 1);
	$b[] = '<span id=dadosResumo><b>' . $carregando . '</b></span>';

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	// $cab = array("MATRÍCULA", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA", "REFEIÇÃO", "ESPERA", "ATRASO", "EFETIVA", "PERÍODO TOTAL", "INTERSTÍCIO DIÁRIO", "INT. SEMANAL", "ABONOS", "FALTAS", "FOLGAS", "H.E.", "H.E. 100%", "ADICIONAL NOTURNO", "ESPERA INDENIZADA", "OBSERVAÇÕES");
	$cab = array(
		"", "MAT.", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
		"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO DIÁRIO / SEMANAL", "HE 50%", "HE&nbsp;100%",
		"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO(*)"
	);

	if ($_POST['busca_data1'] && $_POST['busca_data2'] && $_POST['busca_empresa']) {

		// $date = new DateTime($_POST[busca_data]);
		// $month = $date->format('m');
		// $year = $date->format('Y');

		// $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

		$countTotalEmpresa = 0;
		$countNaoConformidade = 0;

		$sqlMotorista = query("SELECT * FROM entidade WHERE enti_tx_tipo = 'Motorista' AND enti_nb_empresa = " . $_POST['busca_empresa'] . " $extra ORDER BY enti_tx_nome");
		while ($aMotorista = carrega_array($sqlMotorista)) {
			$aEmpresa = carregar('empresa', $aMotorista['enti_nb_empresa']);
			$countTotalEmpresa++;

			$startDate = new DateTime($_POST['busca_data1']);
			$endDate = new DateTime($_POST['busca_data2']);
			// for ($i = 1; $i <= $daysInMonth; $i++) {
			for ($date = $startDate; $date <= $endDate; $date->modify('+1 day')) {
				// $dataVez = $_POST[busca_data]."-".str_pad($i,2,0,STR_PAD_LEFT);
				$dataVez = $date->format('Y-m-d');

				$aDetalhado = diaDetalhePonto($aMotorista['enti_tx_matricula'], $dataVez);

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
				} else {
					continue;
				}

				$row = array_values(array_merge(array(verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])), array($aMotorista['enti_tx_matricula']), $aDetalhado));
				for($f = 0; $f < sizeof($row)-1; $f++){
					if($row[$f] == "00:00"){
						$row[$f] = "";
					}
				}
				$aDia[] = $row;
			}

			if (count($aDia) > 0) {

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

				$countNaoConformidade++;

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

				$aDia[] = array_values(array_merge(array('', '', '', '', '', '', '', '<b>TOTAL</b>'), $totalResumo));

				// $toleranciaStr = carrega_array(query('SELECT parametro.para_tx_tolerancia FROM entidade JOIN parametro ON enti_nb_parametro = para_nb_id WHERE enti_nb_parametro ='.$aMotorista['enti_nb_parametro'].';'))[0];
				// $toleranciaStr = explode(':', $toleranciaStr);

				// $tolerancia = intval($toleranciaStr[0])*60;

				// if($toleranciaStr[0] == '-'){
				// 	$tolerancia -= intval($toleranciaStr[1]);
				// }else{
				// 	$tolerancia += intval($toleranciaStr[1]);
				// }

				// $saldoFiltrado = '00:00';
				
				// for($f = 0; $f < count($aDia); $f++){
				// 	$saldoStr = explode(':', $aDia[$f][count($aDia[$f])-1]);
				// 	$saldo = intval($saldoStr[0])*60;
				// 	if($saldoStr[0] == '-'){
				// 		$saldo -= intval($saldoStr[1]);
				// 	}else{
				// 		$saldo += intval($saldoStr[1]);
				// 	}
				// 	if($saldo >= -($tolerancia) && $saldo <= $tolerancia){
				// 		$aDia[$f][count($aDia[$f])-1] = '00:00';
				// 	}
				// }

				grid2($cab, $aDia, "Jornada Semanal (Horas): $aMotorista[enti_tx_jornadaSemanal]");
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
		.th-align {
		    text-align: center; /* Define o alinhamento horizontal desejado, pode ser center, left ou right */
		    vertical-align: middle !important; /* Define o alinhamento vertical desejado, pode ser top, middle ou bottom */
		    
		}
		#saldo {
			width: 50% !important;
			margin-top: 9px !important;
			text-align: center;
		}
	</style>

	<form name="form_ajuste_ponto" method="post" target="_blank">
		<input type="hidden" name="acao" value="layout_ajuste">
		<input type="hidden" name="id" value="">
		<input type="hidden" name="data">
	</form>

	<script>
		function ajusta_ponto(data, motorista) {
			document.form_ajuste_ponto.data.value = data;
			document.form_ajuste_ponto.id.value = motorista;
			document.form_ajuste_ponto.submit();
		}

		function selecionaMotorista(idEmpresa) {
			let buscaExtra = '';
			if (idEmpresa > 0)
				buscaExtra = encodeURI('AND enti_tx_tipo = "Motorista" AND enti_nb_empresa = "' + idEmpresa + '"');
			else
				buscaExtra = encodeURI('AND enti_tx_tipo = "Motorista"');

			// Verifique se o elemento está usando Select2 antes de destruí-lo
			if ($('.busca_motorista').data('select2')) {
				$('.busca_motorista').select2('destroy');
			}

			$.fn.select2.defaults.set("theme", "bootstrap");
			$('.busca_motorista').select2({
				language: 'pt-BR',
				placeholder: 'Selecione um item',
				allowClear: true,
				ajax: {
					url: '/contex20/select2.php?path=<?=$CONTEX['path']?>&tabela=entidade&extra_ordem=&extra_limite=15&extra_bd='+buscaExtra+'&extra_busca=enti_tx_matricula',
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

		window.onload = function() {

			document.getElementById('dadosResumo').innerHTML = '<b>Total de Motorista: <?= $countTotalEmpresa ?> | Não Conformidade: <?= $countNaoConformidade ?></b>';

		};
	</script>
<?

}
