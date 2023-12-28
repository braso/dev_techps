<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	include "funcoes_ponto.php"; // conecta.php importado dentro de funcoes_ponto	
	// include "funcoes_ponto_oacdc.php"; // conecta.php importado dentro de funcoes_ponto	

	function cadastrar(){
		$url = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/'));
		header('Location: ' . 'https://braso.mobi' . $url . '/cadastro_endosso');
		exit();
	}

	function imprimir_endosso(){
		global $totalResumo;

		if (!$_POST['idMotoristaEndossado']) {
			$motorista = carregar('entidade', $_POST['busca_motorista']);
			$_POST['idMotoristaEndossado'] = $motorista['enti_nb_id'];
		}

		if (empty($_POST['busca_data']) || empty($_POST['busca_empresa']) || empty($_POST['idMotoristaEndossado'])){
			print_r("Há informações faltando.<br>");
			index();
			exit;
		}

		$date = new DateTime($_POST['busca_data']);
		$month = $date->format('m');
		$year = $date->format('Y');
		
		$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

		$primeiroDia = '01/' . $month . '/' . $year;
		$ultimoDia = $daysInMonth . '/' . $month . '/' . $year;

		$aEmpresa = carrega_array(
			query(
				'SELECT empresa.*, cidade.cida_tx_nome, cidade.cida_tx_uf FROM empresa JOIN cidade ON empresa.empr_nb_cidade = cidade.cida_nb_id'.
				' WHERE empr_nb_id = '.$_POST['busca_empresa']
			)
		);

		$enderecoEmpresa = implode(", ",array_filter([
			$aEmpresa['empr_tx_endereco'], 
			$aEmpresa['empr_tx_numero'], 
			$aEmpresa['empr_tx_bairro'], 
			$aEmpresa['empr_tx_complemento'], 
			$aEmpresa['empr_tx_referencia']
		])); //Utilizado em relatorio_espelho.php

		$sqlMotorista = query(
			"SELECT * FROM entidade 
				WHERE enti_tx_tipo = 'Motorista' 
					AND enti_nb_id IN (" . $_POST['idMotoristaEndossado'] . ") 
					AND enti_nb_empresa = " . $_POST['busca_empresa'] . " 
				ORDER BY enti_tx_nome"
		);
		
		while ($aMotorista = carrega_array($sqlMotorista)) {
			for ($i = 1; $i <= $daysInMonth; $i++) {
				$dataVez = $_POST['busca_data'] . "-" . str_pad($i, 2, 0, STR_PAD_LEFT);

				$aDetalhado = diaDetalheEndosso2($aMotorista['enti_tx_matricula'], $dataVez);
				$campos = [
					'data', 'diaSemana', 'inicioJornada', 'inicioRefeicao', 'fimRefeicao', 'fimJornada',
					'diffRefeicao', 'diffEspera', 'diffDescanso', 'diffRepouso', 'diffJornada',
					'jornadaPrevista', 'diffJornadaEfetiva', 'intersticio', 'he50', 'he100', 'adicionalNoturno', 
					'esperaIndenizada', 'moti_tx_motivo'
				];
				$aDetalhadoCampos = [];
				foreach($campos as $campo){
					$aDetalhadoCampos[] = $aDetalhado[$campo];
				}
				unset($campos);

				$row = array_values(array_merge([verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])], $aDetalhadoCampos));
				for ($f = 0; $f < sizeof($row) - 1; $f++) {
					if ($row[$f] == "00:00") {
						$row[$f] = "";
					}
				}
				$aDia[] = $row;
			}

			//unset($aMotorista);

			$aEndosso = carrega_array(query(
				"SELECT endo_tx_dataCadastro, endo_tx_ate, endo_tx_horasApagar, endo_tx_pagarHoras FROM endosso 
					WHERE endo_tx_matricula = '$aMotorista[enti_tx_matricula]'"
			));

			$lastMonthDate = date('Y-m', strtotime('-1 month', strtotime($year.'-'.$month.'-01')));
			$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month-1, $year);
			$saldoAnterior = '00:00';
			
			$lastMonthDay = $lastMonthDate.'-'.$daysInMonth;
			$saldoPassado = diaDetalheEndosso($aMotorista['enti_tx_matricula'], $lastMonthDay)['diffSaldo'];
			$saldoAnterior = operarHorarios([$saldoAnterior, $saldoPassado], '+');
			
			$sqlMotorista = query(
				"SELECT * FROM entidade".
					" LEFT JOIN parametro ON enti_nb_parametro = para_nb_id".
					" WHERE enti_tx_tipo = 'Motorista'".
					" AND enti_nb_id IN (" . $_POST['idMotoristaEndossado'].")".
					" AND enti_nb_empresa = " . $_POST['busca_empresa'] .
					" ORDER BY enti_tx_nome");
			$dadosMotorista = carrega_array($sqlMotorista);

			$dataCicloProx = strtotime($dadosMotorista['para_tx_inicioAcordo']);
			while($dataCicloProx < strtotime($aEndosso['endo_tx_ate'])){
				$dataCicloProx += intval($dadosMotorista['para_nb_qDias'])*60*60*24;
			}
			$dataCicloAnt = $dataCicloProx - intval($dadosMotorista['para_nb_qDias'])*60*60*24;

			$dataCicloProx = date('Y-m-d', $dataCicloProx);
			$dataCicloAnt  = date('Y-m-d', $dataCicloAnt);


			if($aEndosso['endo_tx_dataCadastro'] > $dataCicloProx){
				//Obrigar a pagar horas extras
				
				// $horasObrigatorias = $aEndosso['endo_tx_saldo'] + 
			}
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

			$saldoAtual = operarHorarios([$saldoAnterior, $totalResumo['diffSaldo']], '+'); //Usado dentro de relatorio_espelho.php

			// $totalResumo = ['diffRefeicao' => '00:00','diffEspera' => '00:00','diffDescanso' => '00:00','diffRepouso' => '00:00','diffJornada' => '00:00','jornadaPrevista' => '00:00','diffJornadaEfetiva' => '00:00','maximoDirecaoContinua' => '','intersticio' => '00:00','he50' => '00:00','he100' => '00:00','adicionalNoturno' => '00:00','esperaIndenizada' => '00:00','diffSaldo' => '00:00'];
			// unset($aDia);
		}

		include "./relatorio_espelho.php";
		exit;
	}

	function cadastrar_endosso(){

		$aSaldo = json_decode($_POST['aSaldo'], true);

		$aID = explode(',', $_POST['idMotorista']);
		$aMatricula = explode(',', $_POST['matriculaMotorista']);


		for ($i = 0; $i < count($aID); $i++) {
			$sqlCheck = query(
				"SELECT endo_nb_id FROM endosso 
					WHERE endo_tx_mes = '" . $_POST['busca_data'] . '-01' . "' 
						AND endo_nb_entidade = '" . $aID[$i] . "' 
						AND endo_tx_matricula = '" . $aMatricula[$i] . "' 
						AND endo_tx_status = 'ativo'"
			);
			if (num_linhas($sqlCheck) == 0) {

				$campos = ['endo_nb_entidade', 'endo_tx_matricula', 'endo_tx_mes', 'endo_tx_dataCadastro', 'endo_nb_userCadastro', 'endo_tx_status', 'endo_tx_saldo'];
				$valores = [$aID[$i], $aMatricula[$i], $_POST['busca_data'] . '-01', date("Y-m-d H:i:s"), $_SESSION['user_nb_id'], 'ativo', $aSaldo[$aMatricula[$i]]];

				inserir('endosso', $campos, $valores);
			}
		}

		index();
		exit;
	}

	function index(){
		global $totalResumo, $CONTEX;

		cabecalho('Endosso');

		if ($_SESSION['user_nb_empresa'] > 0 && is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$extraEmpresa = " AND empr_nb_id = '" . $_SESSION['user_nb_empresa'] . "'";
			$extraEmpresaMotorista = " AND enti_nb_empresa = '" . $_SESSION['user_nb_empresa'] . "'";
		}else{
			$extraEmpresa = '';
			$extraEmpresaMotorista = '';
		}

		$extra = '';
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
		if (!empty($_POST['busca_endossado']) && !empty($_POST['busca_empresa'])) {
			if(is_int(strpos($_POST['busca_endossado'], 'Endossado'))){
				$extra .= " AND enti_nb_id";
				if(is_int(strpos($_POST['busca_endossado'], 'Não '))){
					$extra .= " NOT";
				}
				$extra .= " IN (SELECT endo_nb_entidade FROM endosso, entidade WHERE endo_tx_mes = '" . substr($_POST['busca_data'], 0, 7) . '-01' .
					"' AND enti_nb_empresa = '" . $_POST['busca_empresa'] .
					"' AND endo_nb_entidade = enti_nb_id AND endo_tx_status = 'ativo'
				)";
			}
		}

		//CAMPOS DE CONSULTA{
			$c = [
				combo_net('* Empresa:', 'busca_empresa',   (!empty($_POST['busca_empresa'])?   $_POST['busca_empresa']  : ''), 3, 'empresa', 'onchange=selecionaMotorista(this.value)', $extraEmpresa),
				campo_mes('* Data:',    'busca_data',      (!empty($_POST['busca_data'])?      $_POST['busca_data']     : ''), 2),
				combo_net('Motorista:', 'busca_motorista', (!empty($_POST['busca_motorista'])? $_POST['busca_motorista']: ''), 3, 'entidade', '', ' AND enti_tx_tipo = "Motorista"' . $extraMotorista . $extraEmpresaMotorista, 'enti_tx_matricula'),
				combo(    'Situação:',  'busca_situacao',  (!empty($_POST['busca_situacao'])?  $_POST['busca_situacao'] : ''), 2, ['Todos', 'Verificado', 'Não conformidade']),
				combo(    'Endosso:',   'busca_endossado', (!empty($_POST['busca_endossado'])? $_POST['busca_endossado']: ''), 2, ['', 'Endossado', 'Endossado parcialmente', 'Não endossado'])
			];
		//}

		//BOTOES{
			if ($_POST['busca_situacao'] != 'Verificado') {
				$disabled = 'disabled=disabled title="Filtre apenas por Verificado para efetuar a impressão do endosso."';
			}
			$b = [
				botao("Buscar", 'index', '', '', '', 1),
				botao("Cadastrar Abono", 'layout_abono', '', '', '', 1),
				'<button name="acao" id="botaoContexCadastrar CadastrarEndosso" value="cadastrar_endosso" type="button" class="btn default">Cadastrar Endosso</button>',
				'<button name="acao" id="botaoContexCadastrar ImprimirEndosso" value="impressao_endosso" ' . $disabled . ' type="button" class="btn default">Imprimir Endossados</button>',
				'<span id=dadosResumo><b>' . $carregando . '</b></span>'
			];
		//}

		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);

		/*$cab = [
			"MATRÍCULA", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA", 
			"REFEIÇÃO", "ESPERA", "ATRASO", "EFETIVA", "PERÍODO TOTAL", "INTERSTÍCIO DIÁRIO", "INT. SEMANAL", "ABONOS", "FALTAS", "FOLGAS", "H.E.", "H.E. 100%", 
			"ADICIONAL NOTURNO", "ESPERA INDENIZADA", "OBSERVAÇÕES"
		];*/
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
			if(!empty($_POST['busca_data']) && !empty($_POST['busca_empresa'])){
				$counts['total']++;

				$date = new DateTime($_POST['busca_data']);

				$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $date->format('m'), $date->format('Y'));
	
				$sqlMotorista = query(
					"SELECT * FROM entidade
						WHERE enti_tx_tipo = 'Motorista'
							AND enti_nb_empresa = ".$_POST['busca_empresa']." ".$extra."
						ORDER BY enti_tx_nome"
				);
				while ($aMotorista = carrega_array($sqlMotorista)) {
					if(empty($aMotorista['enti_tx_nome']) || empty($aMotorista['enti_tx_matricula'])){
						continue;
					}
	
					//Pegando e formatando registros dos dias{
						for ($i = 1; $i <= $daysInMonth; $i++) {
							$dataVez = $_POST['busca_data']."-".str_pad($i, 2, 0, STR_PAD_LEFT);
							
							$aDetalhado = diaDetalheEndosso($aMotorista['enti_tx_matricula'], $dataVez);

							if(isset($aDetalhado['fimJornada'][0]) && (strpos($aDetalhado['fimJornada'][0], ':00') !== false) && date('Y-m-d', strtotime($aDetalhado['fimJornada'][0])) != $dataVez){
								array_splice($aDetalhado['fimJornada'], 1, 0, 'D+1');
							}
	
							//Converter array em string{
								foreach(['inicioJornada', 'fimJornada', 'inicioRefeicao', 'fimRefeicao'] as $tipo){
									if (is_array($aDetalhado[$tipo]) && count($aDetalhado[$tipo]) > 0){
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
							$aDiaOriginal[] = $aDetalhado;
						}
					//}
	
					$exibir = True;

					$keys = [
						'diffRefeicao','diffEspera','diffDescanso','diffRepouso',
						'diffJornada','jornadaPrevista','diffJornadaEfetiva','maximoDirecaoContinua',
						'intersticio','he50','he100','adicionalNoturno',
						'esperaIndenizada','diffSaldo'
					];
	
					foreach($aDiaOriginal as $diaVez){
						$checkString = '';
						foreach($keys as $key){
							$checkString .= $diaVez[$key];
						}
						if (strpos($checkString, 'color:red;') !== false){// Se houver pelo menos uma inconformidade nos campos listados

							$exibir = in_array($_POST['busca_situacao'], ['Todos', 'Não Conformidade']);
							$counts['naoConformidade']++;
							
						} else {
							$exibir = True;
						}
					}

					if (!$exibir) {
						unset($aDia);
						unset($aDiaOriginal);
					}

					if (count($aDia) > 0) {

						$sqlCheck = query(
							"SELECT user_tx_login, endo_tx_dataCadastro, endo_tx_ate 
								FROM endosso JOIN user ON endo_nb_userCadastro = user_nb_id 
								WHERE endo_tx_mes = '" . substr($_POST['busca_data'], 0, 7) . '-01' . "' AND endo_nb_entidade = '" . $aMotorista['enti_nb_id'] . "'
									AND endo_tx_matricula = '" . $aMotorista['enti_tx_matricula'] . "' 
									AND endo_tx_status = 'ativo' 
								LIMIT 1"
						);
						$aEndosso = carrega_array($sqlCheck);
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

						$saldoAnterior = mysqli_fetch_assoc(
							query(
								"SELECT endo_tx_saldo FROM `endosso`
									WHERE endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
										AND endo_tx_ate < '".$_POST['busca_data']."-01'
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
						
						for($f = 0; $f < count($aDia); $f++){
							if(empty($aDia[$f][count($aDia[$f])-1])){
								$aDia[$f][count($aDia[$f])-1] = '00:00';	
							}

							$saldoStr = str_replace('<b>', '', $aDia[$f][count($aDia[$f])-1]);
							$saldoStr = explode(':', $saldoStr);
							$saldo = intval($saldoStr[0])*60;
							$saldo += ($saldoStr[0] == '-'? -1: 1)*intval($saldoStr[1]);

							if($saldo >= -($tolerancia) && $saldo <= $tolerancia){
								$aDia[$f][count($aDia[$f])-1] = '00:00';
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

			if (in_array($_POST['busca_situacao'], ['Todos', 'Verificado'])){
				// $countVerificados = $countEndosso - $countNaoConformidade; //Utilizado em endosso_html.php
			}
		//}

		echo '<div class="printable"></div>';

		rodape();

		include 'endosso_html.php';
	}
?>