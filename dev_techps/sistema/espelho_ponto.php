<?php
	include "funcoes_ponto.php"; // NAO ALTERAR ORDEM
	include "conecta.php";

	function index(){

		global $totalResumo;

		cabecalho('Espelho de Ponto');

		if($_POST['busca_motorista']){
			$aMotorista = carregar('entidade',$_POST['busca_motorista']);
			$aDadosMotorista = [$aMotorista['enti_tx_matricula']];
		}

		if($_POST['busca_data1'] == '') $_POST['busca_data1'] = date("Y-m-01");
	
		if($_POST['busca_data2'] == '') $_POST['busca_data2'] = date("Y-m-d");

		//CONSULTA
		$c[] = combo_net('Motorista:','busca_motorista',$_POST['busca_motorista'],5,'entidade','',' AND enti_tx_tipo = "Motorista"','enti_tx_matricula');
		// $c[] = campo_mes('Data:','busca_data',$_POST[busca_data],2);
		$c[] = campo_data('Data Início:','busca_data1',$_POST['busca_data1'],2);
		$c[] = campo_data('Data Fim:','busca_data2',$_POST['busca_data2'],2);
		$c[] = combo('Status', 'busca_status', $_POST['busca_status'], 3, ['', 'Com alerta(s)', 'Com alerta de refeição', 'Sem saldo cadastrado', 'Com saldo negativo', 'Com saldo positivo']);
		
		
		//BOTOES
		$b[] = botao("Buscar",'index');
		$b[] = botao("Cadastrar Abono",'layout_abono');
		
		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);
		
		// $cab = ["MATRÍCULA", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA", "REFEIÇÃO", "ESPERA", "ATRASO", "EFETIVA", "PERÍODO TOTAL", "INTERSTÍCIO DIÁRIO", "INT. SEMANAL", "ABONOS", "FALTAS", "FOLGAS", "H.E.", "H.E. 100%", "ADICIONAL NOTURNO", "ESPERA INDENIZADA", "OBSERVAÇÕES"];
		$cab = ["", "MAT.", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA", "REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", 
			"JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA","MDC","INTERSTÍCIO","HE 50%", "HE 100%", "ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO"];

		// Converte as datas para objetos DateTime
		$startDate = new DateTime($_POST['busca_data1']);
		$endDate = new DateTime($_POST['busca_data2']);

		// Loop for para percorrer as datas
		

		if($_POST['busca_data1'] && $_POST['busca_data2'] && ($_POST['busca_motorista'] != '' || $_POST['busca_status'] != '')){
			// $date = new DateTime($_POST[busca_data]);
			// $month = $date->format('m');
			// $year = $date->format('Y');

			// $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
			
			// for ($i = 1; $i <= $daysInMonth; $i++) {
				// 	$dataVez = $_POST[busca_data]."-".str_pad($i,2,0,STR_PAD_LEFT);
				// 	$aDetalhado = diaDetalhePonto($aMotorista[enti_tx_matricula], $dataVez);
				// 	$aDia[] = array_values(array_merge([verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])], $aDadosMotorista, $aDetalhado));
			// }

			$found_register = False;

			for($date = $startDate; $date <= $endDate; $date->modify('+1 day')){
				$dataVez = $date->format('Y-m-d');
				$aDetalhado = diaDetalhePonto($aMotorista['enti_tx_matricula'], $dataVez);
				if($aDetalhado['temPendencias'] == True){
					if($_POST['busca_status'] == 'Com alerta(s)'){
						if(	is_bool(strpos(strval($aDetalhado['inicioJornada']),  'fa fa-warning')) && 
							is_bool(strpos(strval($aDetalhado['inicioRefeicao']), 'fa fa-warning')) &&
							is_bool(strpos(strval($aDetalhado['fimRefeicao']),    'fa fa-warning')) &&
							is_bool(strpos(strval($aDetalhado['fimJornada']),     'fa fa-warning'))
						){
							# Não tem pendências
							continue;
						}
					}elseif($_POST['busca_status'] == 'Com alerta de refeição'){
						if( is_bool(strpos(strval($aDetalhado['inicioRefeicao']), 'fa fa-warning')) &&
							is_bool(strpos(strval($aDetalhado['fimRefeicao']),    'fa fa-warning'))
						){
							# Não tem pendências
							continue;
						}
					}elseif($_POST['busca_status'] == 'Sem saldo cadastrado'){

					}elseif($_POST['busca_status'] == 'Com saldo negativo'){
						if($aDetalhado['diffSaldo'][0] != '-' || $aDetalhado['diffSaldo'] == ''){
							# Saldo positivo
							continue;
						}
					}elseif($_POST['busca_status'] == 'Com saldo positivo'){
						if($aDetalhado['diffSaldo'][0] == '-' || $aDetalhado['diffSaldo'] == ''){
							# Saldo negativo
							continue;
						}
					}
				}

				if(!empty($aMotorista['enti_nb_id'])){
					$found_register = True;
				}

				$aDia[] = array_values(array_merge([verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])], $aDadosMotorista, $aDetalhado));
			}

			if($aMotorista['enti_nb_parametro'] > 0){
				$aParametro = carregar('parametro', $aMotorista['enti_nb_parametro']);
				if(	$aParametro['para_tx_jornadaSemanal']		!= $aMotorista['enti_tx_jornadaSemanal'] ||
					$aParametro['para_tx_jornadaSabado']		!= $aMotorista['enti_tx_jornadaSabado'] ||
					$aParametro['para_tx_percentualHE']			!= $aMotorista['enti_tx_percentualHE'] ||
					$aParametro['para_tx_percentualSabadoHE']	!= $aMotorista['enti_tx_percentualSabadoHE']){
		
					$convencaoPadrao = '| Convenção Padrão? Não';
				}else{
					$convencaoPadrao = '| Convenção Padrão? Sim';
				}
			}

			if($found_register){
				abre_form("[$aMotorista[enti_tx_matricula]] $aMotorista[enti_tx_nome] $convencaoPadrao");
			}

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
<?
	
			print_r("Tudo?");
			$aDia[] = array_values(array_merge(array('','','','','','','','<b>TOTAL</b>'), $totalResumo));

			grid2($cab,$aDia,"Jornada Semanal (Horas): $aMotorista[enti_tx_jornadaSemanal]");
			fecha_form();
		}
		rodape();
?>
	<form name="form_ajuste_ponto" method="post">
		<input type="hidden" name="acao" value="layout_ajuste">
		<input type="hidden" name="id" value="<?=$aMotorista['enti_nb_id']?>">
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
?>