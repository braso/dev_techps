<?php

use function PHPSTORM_META\type;

	include "funcoes_ponto.php"; // Conecta importado dentro de funcoes_ponto
	include "funcoes_grid_oacdc.php";

	function cadastrar(){
		$url = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/'));
		header('Location: ' . 'https://braso.mobi' . $url . '/cadastro_endosso');
		exit();
	}

	function imprimir_endosso(){
		global $totalResumo, $contagemEspera;

		if (!$_POST['idMotoristaEndossado']) {
			$motorista = carregar('entidade', $_POST['busca_motorista']);
			$_POST['idMotoristaEndossado'] = $motorista['enti_nb_id'];
		}

		if ($_POST['busca_data'] && $_POST['busca_empresa'] && $_POST['idMotoristaEndossado']) {

			$date = new DateTime($_POST['busca_data']);
			$month = $date->format('m');
			$year = $date->format('Y');

			$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

			$primeiroDia = '01/' . $month . '/' . $year;
			$ultimoDia = $daysInMonth . '/' . $month . '/' . $year;

			$aEmpresa = carregar('empresa', $_POST['busca_empresa']);
			$aCidadeEmpresa = carregar('cidade', $aEmpresa['empr_nb_cidade']);

			$enderecoEmpresa = implode(
				", ",
				array_filter([
					$aEmpresa['empr_tx_endereco'],
					$aEmpresa['empr_tx_numero'],
					$aEmpresa['empr_tx_bairro'],
					$aEmpresa['empr_tx_complemento'],
					$aEmpresa['empr_tx_referencia']
				])
			);

			$sqlMotorista = query("SELECT * FROM entidade WHERE enti_tx_tipo = 'Motorista' AND enti_nb_id IN (" . $_POST['idMotoristaEndossado'] . ") AND enti_nb_empresa = " . $_POST['busca_empresa'] . " ORDER BY enti_tx_nome");
			
			while ($aMotorista = carrega_array($sqlMotorista)) {
				for ($i = 1; $i <= $daysInMonth; $i++) {
					$dataVez = $_POST['busca_data'] . "-" . str_pad($i, 2, 0, STR_PAD_LEFT);

					$aDetalhado = diaDetalheEndosso($aMotorista['enti_tx_matricula'], $dataVez);
					$aDetalhadoCampos = [
						$aDetalhado['data'], $aDetalhado['diaSemana'], $aDetalhado['inicioJornada'], $aDetalhado['inicioRefeicao'],
						$aDetalhado['fimRefeicao'], $aDetalhado['fimJornada'], $aDetalhado['diffRefeicao'], $aDetalhado['diffEspera'], $aDetalhado['diffDescanso'], $aDetalhado['diffRepouso'],
						$aDetalhado['diffJornada'], $aDetalhado['diffJornadaEfetiva'], $aDetalhado['jornadaPrevista'], $aDetalhado['intersticio'], $aDetalhado['he50'], $aDetalhado['he100'],
						$aDetalhado['adicionalNoturno'], $aDetalhado['esperaIndenizada'], $aDetalhado['moti_tx_motivo']
					];

					$row = array_values(array_merge(array(verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])), $aDetalhadoCampos));
					for ($f = 0; $f < sizeof($row) - 1; $f++) {
						if ($row[$f] == "00:00") {
							$row[$f] = "";
						}
					}
					$aDia[] = $row;
				}

				// 			unset($aMotorista);

				$sqlEndosso = query("SELECT endo_tx_dataCadastro, endo_tx_ate, endo_tx_horasApagar, endo_tx_pagarHoras FROM endosso WHERE endo_tx_matricula = '$aMotorista[enti_tx_matricula]'");
				$aEndosso = carrega_array($sqlEndosso);

				$lastMonthDate = date('Y-m', strtotime('-1 month', strtotime($year . '-' . $month . '-01')));
				$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month - 1, $year);
				$saldoAnterior = '00:00';
				
				while ($aMotorista = carrega_array($sqlMotorista)) {
					for ($i = 1; $i <= $daysInMonth; $i++) {
						$lastMonthDay = $lastMonthDate . '-' . str_pad($i, 2, 0, STR_PAD_LEFT);
						$aDetalhado = diaDetalheEndosso($aMotorista['enti_tx_matricula'], $lastMonthDay);
						$saldoAnterior = somarHorarios([$saldoAnterior, $aDetalhado['diffSaldo']]);
					}
				}
				
				$sqlMotorista = query(
					"SELECT * FROM entidade".
						" LEFT JOIN parametro ON enti_nb_parametro = para_nb_id".
						" WHERE enti_tx_tipo = 'Motorista'".
						" AND enti_nb_id IN (" . $_POST['idMotoristaEndossado'].")".
						" AND enti_nb_empresa = " . $_POST['busca_empresa'] .
						" ORDER BY enti_tx_nome");
				$dadosMotorista = carrega_array($sqlMotorista);

				/*
				$teste = strtotime("2023-10-28 11:54:34");
				$teste = date('Y-m-d h:i:s', $teste+10); //Soma 10 segundos
				echo $teste;
				*/

				$dataCicloProx = strtotime($dadosMotorista['para_tx_dataCadastro']);
				while($dataCicloProx < strtotime($aEndosso['endo_tx_ate'])){
					$dataCicloProx += intval($dadosMotorista['para_nb_qDias'])*60*60*24;
				}
				$dataCicloAnt = $dataCicloProx - intval($dadosMotorista['para_nb_qDias'])*60*60*24;;

				$dataCicloProx = date('Y-m-d', $dataCicloProx);
				$dataCicloAnt = date('Y-m-d', $dataCicloAnt);

				/*
					Fazer as contas pra relacionar o dia do cadastro do endosso com o dia do cadastro do parâmetro e conferir
				se o endosso está dentro da faixa de tempo em que deve ser obrigado pagar hora extra ou não
				Se(endossoCadastroData > ultimoCicloData){
					Obrigar pagar horas extras entre $dataCicloAnt e $dataCicloProx
					(horas que devem ser obrigadas a pagar: endo_tx_saldo + somaSaldos(endo_tx_ate, $dataCicloProx))
					$horasObrigatorias = endo_tx_saldo + somaSaldos(endo_tx_ate, $dataCicloProx)
				}
				*/


				if (isset($dadosMotorista['para_nb_qDias']) && $dadosMotorista['para_nb_qDias'] != null) { //Deve ser feito somente quando for obrigado a pagar a hora extra?

					//Contexto do HE100
					// $he100 = strtotime($aDetalhado['he100']);
					$he100 = explode(':', $aDetalhado['he100']);
					$he100 = intval($he100[0]) * 60 + ($he100[0][0] == '-' ? -1 : 1) * intval($he100[1]);
					
					$he50_pagar = explode(':', $totalResumo['he50']);
					$he50_pagar = intval($he50_pagar[0]) * 60 + ($he50_pagar[0][0] == '-' ? -1 : 1) * intval($he50_pagar[1]);
					
					$he100_pagar = explode(':', $totalResumo['he100']);
					$he100_pagar = intval($he100_pagar[0]) * 60 + ($he100_pagar[0][0] == '-' ? -1 : 1) * intval($he100_pagar[1]);

					$saldoPeriodo = explode(':', $totalResumo['diffSaldo']);
					$saldoPeriodo = intval($saldoPeriodo[0]) * 60 + ($saldoPeriodo[0][0] == '-' ? -1 : 1) * intval($saldoPeriodo[1]);
					
					if ($saldoPeriodo <= 0) {
						# Não faz nada
					} else {
						if ($he100_pagar > 0) {
							$transferir = $saldoPeriodo - (($saldoPeriodo > $he100) ? $he100 : 0);

							$saldoPeriodo -= $transferir;
							$he100_pagar += $transferir;
							$totalResumo['he100'] = intval($he100_pagar / 60) . ':' . ($he100_pagar - intval($he100_pagar / 60) * 60);
						}
					}

					//Contexto do HE50
					if($aEndosso['endo_tx_pagarHoras'] == 'sim'){
						if($saldoPeriodo > $aEndosso['endo_tx_horasApagar']){
							$transferir = $aEndosso['endo_tx_horasApagar'];
						}else{
							$transferir = $saldoPeriodo;
						}
						$saldoPeriodo -= $transferir;

						$he50_pagar += $transferir;
						$totalResumo['he50'] = intval($he50_pagar / 60) . ':' . ($he50_pagar - intval($he50_pagar / 60) * 60);
					}

					$totalResumo['diffSaldo'] = intval($saldoPeriodo / 60) . ':' . ($saldoPeriodo - intval($saldoPeriodo / 60) * 60);
				}

				$saldoAtual = somarHorarios([$saldoAnterior, $totalResumo['diffSaldo']]); //Usado dentro de relatorio_espelho.php

				include "./relatorio_espelho.php";

				$totalResumo = ['diffRefeicao' => '00:00','diffEspera' => '00:00','diffDescanso' => '00:00','diffRepouso' => '00:00','diffJornada' => '00:00','jornadaPrevista' => '00:00','diffJornadaEfetiva' => '00:00','maximoDirecaoContinua' => '','intersticio' => '00:00','he50' => '00:00','he100' => '00:00','adicionalNoturno' => '00:00','esperaIndenizada' => '00:00','diffSaldo' => '00:00'];
				unset($aDia);
			}
		}else{
			print_r("Há informações faltando.<br>");
		}
		exit;
	}


	function cadastrar_endosso(){

		$aSaldo = json_decode($_POST['aSaldo'], true);

		$aID = explode(',', $_POST['idMotorista']);
		$aMatricula = explode(',', $_POST['matriculaMotorista']);


		for ($i = 0; $i < count($aID); $i++) {
			$sqlCheck = query("SELECT endo_nb_id FROM endosso WHERE endo_tx_mes = '" . $_POST['busca_data'] . '-01' . "' 
					AND endo_nb_entidade = '" . $aID[$i] . "' AND endo_tx_matricula = '" . $aMatricula[$i] . "' AND endo_tx_status = 'ativo'");
			if (num_linhas($sqlCheck) == 0) {

				$campos = ['endo_nb_entidade', 'endo_tx_matricula', 'endo_tx_mes', 'endo_tx_dataCadastro', 'endo_nb_userCadastro', 'endo_tx_status', 'endo_tx_saldo'];
				$valores = [$aID[$i], $aMatricula[$i], $_POST['busca_data'] . '-01', date("Y-m-d H:i:s"), $_SESSION['user_nb_id'], 'ativo', $aSaldo[$aMatricula[$i]]];

				inserir('endosso', $campos, $valores);
			}
		}

		index();
		exit;
	}


	function index()
	{
		global $totalResumo, $CONTEX;

		cabecalho('Endosso');

		if ($_SESSION['user_nb_empresa'] > 0 && is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$extraEmpresa = " AND empr_nb_id = '" . $_SESSION['user_nb_empresa'] . "'";
			$extraEmpresaMotorista = " AND enti_nb_empresa = '" . $_SESSION['user_nb_empresa'] . "'";
		}

		if ($_POST['busca_motorista']) {
			$extra = " AND enti_nb_id = " . $_POST['busca_motorista'];
		}

		if ($_POST['busca_data'] && $_POST['busca_empresa']) {
			$carregando = "Carregando...";
		}

		if ($_POST['busca_data'] == '') {
			$_POST['busca_data'] = date("Y-m");
		}

		if ($_POST['busca_empresa']) {
			$_POST['busca_empresa'] = (int)$_POST['busca_empresa'];
			$extraMotorista = " AND enti_nb_empresa = '" . $_POST['busca_empresa'] . "'";
		}

		if ($_POST['busca_endossado'] && $_POST['busca_empresa']) {
			if ($_POST['busca_endossado'] == 'Endossado') {
				$extra .= " AND enti_nb_id IN (
						SELECT endo_nb_entidade FROM endosso, entidade WHERE endo_tx_mes = '" . substr($_POST['busca_data'], 0, 7) . '-01' .
					"' AND enti_nb_empresa = '" . $_POST['busca_empresa'] .
					"' AND endo_nb_entidade = enti_nb_id AND endo_tx_status = 'ativo'
					)";
			}

			if ($_POST['busca_endossado'] == 'Não endossado') {
				$extra .= " AND enti_nb_id NOT IN (
						SELECT endo_nb_entidade FROM endosso, entidade WHERE endo_tx_mes = '" . substr($_POST['busca_data'], 0, 7) . '-01' . "' AND enti_nb_empresa = '" . $_POST['busca_empresa'] . "' 
						AND endo_nb_entidade = enti_nb_id AND endo_tx_status = 'ativo'
						)";
			}
		}


		$countEndosso = $countNaoConformidade = $countVerificados = $countEndossados = $countNaoEndossados = 0;

		//CONSULTA
		$c[] = combo_net('* Empresa:', 'busca_empresa', $_POST['busca_empresa'], 3, 'empresa', 'onchange=selecionaMotorista(this.value)', $extraEmpresa);
		$c[] = campo_mes('* Data:', 'busca_data', $_POST['busca_data'], 2);
		$c[] = combo_net('Motorista:', 'busca_motorista', $_POST['busca_motorista'], 3, 'entidade', '', ' AND enti_tx_tipo = "Motorista"' . $extraMotorista . $extraEmpresaMotorista, 'enti_tx_matricula');
		$c[] = combo('Situação:', 'busca_situacao', $_POST['busca_situacao'], 2, ['Todos', 'Verificado', 'Não conformidade']);
		$c[] = combo('Endosso:', 'busca_endossado', $_POST['busca_endossado'], 2, ['', 'Endossado', 'Endossado parcialmente', 'Não endossado']);

		//BOTOES
		$b[] = botao("Buscar", 'index', '', '', '', 1);
		$b[] = botao("Cadastrar Abono", 'layout_abono', '', '', '', 1);
		if ($_POST['busca_situacao'] != 'Verificado') {
			$disabled = 'disabled=disabled title="Filtre apenas por Verificado para efetuar o endosso."';
			$disabled2 = 'disabled=disabled title="Filtre apenas por Verificado para efetuar a impressão endosso."';
		}
		$b[] = '<button name="acao" id="botaoContexCadastrar CadastrarEndosso" value="cadastrar_endosso" type="button" class="btn default">Cadastrar Endosso</button>';
		$b[] = '<button name="acao" id="botaoContexCadastrar ImprimirEndosso" value="impressao_endosso" ' . $disabled2 . ' type="button" class="btn default">Imprimir Endossados</button>';
		$b[] = '<span id=dadosResumo><b>' . $carregando . '</b></span>';


		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);

			// $cab = ["MATRÍCULA", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA", "REFEIÇÃO", "ESPERA", "ATRASO", "EFETIVA", "PERÍODO TOTAL", "INTERSTÍCIO DIÁRIO", "INT. SEMANAL", "ABONOS", "FALTAS", "FOLGAS", "H.E.", "H.E. 100%", "ADICIONAL NOTURNO", "ESPERA INDENIZADA", "OBSERVAÇÕES"];
			$cab = [
				"", "MAT.", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
				"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO DIÁRIO / SEMANAL", "HE 50%", "HE&nbsp;100%",
				"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO(**)"
			];

			//function buscar_endosso(){
				if ($_POST['busca_data'] && $_POST['busca_empresa']) {

					$date = new DateTime($_POST['busca_data']);
					$month = $date->format('m');
					$year = $date->format('Y');
		
					$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		
					$sqlMotorista = query("SELECT * FROM entidade WHERE enti_tx_tipo = 'Motorista' AND enti_nb_empresa = " . $_POST['busca_empresa'] . " $extra ORDER BY enti_tx_nome");
					while ($aMotorista = carrega_array($sqlMotorista)) {
						if($aMotorista['enti_tx_nome'] == '' || $aMotorista['enti_tx_matricula'] == ''){
							continue;
						}
						$aEmpresa = carregar('empresa', $aMotorista['enti_nb_empresa']);
		
						for ($i = 1; $i <= $daysInMonth; $i++) {
							$dataVez = $_POST['busca_data'] . "-" . str_pad($i, 2, 0, STR_PAD_LEFT);
		
							$aDetalhado = diaDetalhePonto($aMotorista['enti_tx_matricula'], $dataVez);
		
							$row = array_values(array_merge([verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])], [$aMotorista['enti_tx_matricula']], $aDetalhado));;
							for($f = 0; $f < sizeof($row)-1; $f++){
								if($row[$f] == "00:00"){
									$row[$f] = "";
								}
							}
							$aDia[] = $row;
							$aDiaOriginal[] = $aDetalhado;
						}
		
						$exibir = True;

						$keys = [
							'diffRefeicao','diffEspera','diffDescanso','diffRepouso',
							'diffJornada','jornadaPrevista','diffJornadaEfetiva','maximoDirecaoContinua',
							'intersticio','he50','he100','adicionalNoturno',
							'esperaIndenizada','diffSaldo'
						];
		
						for ($i = 0; $i < count($aDiaOriginal); $i++) {
							$diaVez = $aDiaOriginal[$i];
							if (strpos($diaVez['diffRefeicao'].$diaVez['diffEspera'].$diaVez['diffDescanso'].$diaVez['diffRepouso'].$diaVez['diffJornada'].$diaVez['jornadaPrevista'].$diaVez['diffJornadaEfetiva'].$diaVez['maximoDirecaoContinua'].$diaVez['intersticio'].$diaVez['he50'].$diaVez['he100'].$diaVez['adicionalNoturno'].$diaVez['esperaIndenizada'].$diaVez['diffSaldo'], 'color:red;') !== false){
								//SE HOUVER RED E BUSCA POR NAO CONFORMIDADE, EXIBE. SE NÃO E VERIFICAO, NÃO EXIBE.
								$exibir = ($_POST['busca_situacao'] != 'Verificado');
								
								if ($_POST['busca_situacao'] == 'Não conformidade' || $_POST['busca_situacao'] == 'Todos') {
									$countNaoConformidade++;
									break;
								} elseif ($_POST['busca_situacao'] == 'Verificado') {
									$totalResumo = [];
									foreach($keys as $key){
										$totalResumo[$key] = '00:00';
									}
									break;
								}
							} else {
								$exibir = ($_POST['busca_situacao'] == 'Verificado');
							}
						}

						if (!$exibir) {
							unset($aDia);
							unset($aDiaOriginal);
						}
		
						if (count($aDia) > 0) {
		
							$sqlCheck = query("SELECT user_tx_login, endo_tx_dataCadastro, endo_tx_ate FROM endosso JOIN user ON endo_nb_userCadastro = user_nb_id WHERE endo_tx_mes = '" . substr($_POST['busca_data'], 0, 7) . '-01' . "' AND endo_nb_entidade = '" . $aMotorista['enti_nb_id'] . "'
							AND endo_tx_matricula = '" . $aMotorista['enti_tx_matricula'] . "' AND endo_tx_status = 'ativo' LIMIT 1");
							$aEndosso = carrega_array($sqlCheck);
							if (count($aEndosso) > 0) {
								$infoEndosso = " - Endossado por " . $aEndosso['user_tx_login'] . " em " . data($aEndosso['endo_tx_dataCadastro'], 1);
								$countEndossados++;
								$aIdMotoristaEndossado[] = $aMotorista['enti_nb_id'];
								$aMatriculaMotoristaEndossado[] = $aMotorista['enti_tx_matricula'];
							} else {
								$infoEndosso = '';
								$countNaoEndossados++;
							}
		
							$aIdMotorista[] = $aMotorista['enti_nb_id'];
							$aMatriculaMotorista[] = $aMotorista['enti_tx_matricula'];
		
							$countEndosso++;
		
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
											<td>--:--</td>
											<td>'.$totalResumo['diffSaldo'].'</td>
											<td>--:--</td>
											</tr>
										</tbody>
										</table>
								</div>';
		
							abre_form("[$aMotorista[enti_tx_matricula]] $aMotorista[enti_tx_nome] | $aEmpresa[empr_tx_nome] $infoEndosso $convencaoPadrao $saldosMotorista");
		
							$aDia[] = array_values(array_merge(['', '', '', '', '', '', '', '<b>TOTAL</b>'], $totalResumo));
		
							
							$dadosParametro = carrega_array(query('SELECT para_tx_tolerancia, para_tx_dataCadastro, para_nb_qDias FROM parametro JOIN entidade ON para_nb_id = enti_nb_parametro WHERE enti_nb_parametro = '.$aMotorista['enti_nb_parametro'].' LIMIT 1;'));
							$toleranciaStr = explode(':', $dadosParametro['para_tx_tolerancia']);
		
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

							$dataCicloProx = strtotime($dadosParametro['para_tx_dataCadastro']);
							if($dataCicloProx !== false){
								while($dataCicloProx < strtotime($aEndosso['endo_tx_ate'])){
									$dataCicloProx += intval($dadosParametro['para_nb_qDias'])*60*60*24;
								}
							}

		
							grid2($cab, $aDia);
							fecha_form();
		
							$aSaldo[$aMotorista['enti_tx_matricula']] = $totalResumo['diffSaldo'];
						}
		
						$totalResumo = ['diffRefeicao' => '00:00', 'diffEspera' => '00:00', 'diffDescanso' => '00:00', 'diffRepouso' => '00:00', 'diffJornada' => '00:00', 'jornadaPrevista' => '00:00', 'diffJornadaEfetiva' => '00:00', 'maximoDirecaoContinua' => '', 'intersticio' => '00:00', 'he50' => '00:00', 'he100' => '00:00', 'adicionalNoturno' => '00:00', 'esperaIndenizada' => '00:00', 'diffSaldo' => '00:00'];
		
						unset($aDia);
						unset($aDiaOriginal);
					}
				}
				
				if ($_POST['busca_situacao'] == 'Todos' || $_POST['busca_situacao'] == 'Verificado') {
					$countVerificados = $countEndosso - $countNaoConformidade;
				}
			//}

		echo '<div class="printable"></div>';

		rodape();

		?>

			<style>
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

		<form name="form_cadastrar_endosso" method="post">
			<input type="hidden" name="acao" value="cadastrar_endosso">
			<input type="hidden" name="idMotorista" value="<?= implode(",", $aIdMotorista) ?>">
			<input type="hidden" name="matriculaMotorista" value="<?= implode(",", $aMatriculaMotorista) ?>">
			<input type="hidden" name="busca_empresa" value="<?= $_POST['busca_empresa'] ?>">
			<input type="hidden" name="busca_data" value="<?= $_POST['busca_data'] ?>">
			<input type="hidden" name="busca_motorista" value="<?= $_POST['busca_motorista'] ?>">
			<input type="hidden" name="busca_situacao" value="<?= $_POST['busca_situacao'] ?>">
			<input type="hidden" name="aSaldo" value="<?= htmlspecialchars(json_encode($aSaldo)) ?>">
		</form>

		<form name="form_imprimir_endosso" method="post" target="_blank">
			<input type="hidden" name="acao" value="imprimir_endosso">
			<input type="hidden" name="idMotoristaEndossado" value="<?= implode(",", $aIdMotoristaEndossado) ?>">
			<input type="hidden" name="matriculaMotoristaEndossado" value="<?= (implode(",", $aMatriculaMotoristaEndossado)) ?>">
			<input type="hidden" name="busca_empresa" value="<?= $_POST['busca_empresa'] ?>">
			<input type="hidden" name="busca_data" value="<?= $_POST['busca_data'] ?>">
			<input type="hidden" name="busca_motorista" value="<?= $_POST['busca_motorista'] ?>">
			<input type="hidden" name="busca_situacao" value="<?= $_POST['busca_situacao'] ?>">
		</form>

		<form name="form_ajuste_ponto" method="post" target="_blank">
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

			function selecionaMotorista(idEmpresa) {
				let buscaExtra = '';
				if (idEmpresa > 0) {
					buscaExtra = encodeURI('AND enti_tx_tipo = "Motorista" AND enti_nb_empresa = "' + idEmpresa + '"');
				} else {
					buscaExtra = encodeURI('AND enti_tx_tipo = "Motorista"');
				}
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
						url: "/contex20/select2.php?path=" + "<?= $CONTEX['path'] ?>" + "&tabela=entidade&extra_ordem=&extra_limite=15&extra_bd=" + buscaExtra + "&extra_busca=enti_tx_matricula",
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
				document.getElementById('dadosResumo').innerHTML = '<b>Total: <?= $countEndosso ?> | Verificados: <?= $countVerificados ?> | Não Conformidade: <?= $countNaoConformidade ?> | Endossados: <?= $countEndossados ?> | Não Endossados: <?= $countNaoEndossados ?></b>';

				document.getElementById('botaoContexCadastrar CadastrarEndosso').onclick = function() {
					window.location.href = '<?= "https://braso.mobi" . $CONTEX['path'] . "/cadastro_endosso" ?>';
				}

				document.getElementById('botaoContexCadastrar ImprimirEndosso').onclick = function() {
					document.form_imprimir_endosso.submit();
				}

			};
		</script>
	<?

	}
?>