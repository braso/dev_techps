<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
		if(!isset($_GET['debug'])){
			echo '<div style="text-align:center; vertical-align: center; height: 100%; padding-top: 20%">Esta página está em desenvolvimento.</div>';
			exit;
		}
	//*/

	include "funcoes_ponto.php"; // conecta.php importado dentro de funcoes_ponto

	function intToTime(int $time): string{
		//Obs.: Variável $time deve estar em minutos.
		$res = str_pad(intval($time/60), 2, 0, STR_PAD_LEFT).':'.str_pad(abs($time%60), 2, 0, STR_PAD_LEFT);

		return $res;
	}

	function cadastrar(){
		$url = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/'));
		// header('Location: ' . 'https://braso.mobi' . $url . '/cadastro_endosso');
		exit();
	}

	function imprimir_relatorio(){
		global $totalResumo, $CONTEX; //Utilizado em relatorio_espelho.php

		if (!$_POST['idMotoristaEndossado']) {
			$motorista = carregar('entidade', $_POST['busca_motorista']);
			$_POST['idMotoristaEndossado'] = $motorista['enti_nb_id'];
		}

		if (empty($_POST['busca_data']) || empty($_POST['busca_empresa']) || empty($_POST['idMotoristaEndossado'])){
			echo '<script>alert("Insira data e motorista para gerar relatório.");</script>';
			index();
			exit;
		}

		$date = new DateTime($_POST['busca_data']);
		$month = intval($date->format('m'));
		$year = intval($date->format('Y'));
		
		$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

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
					AND enti_tx_status != 'inativo'
				ORDER BY enti_tx_nome"
		);
		
		$diasEndossados = 0;

		while ($aMotorista = carrega_array($sqlMotorista)) {
			//Pegando e formatando registros dos dias{
				for ($i = 1; $i <= $daysInMonth; $i++) {
					$dataVez = $_POST['busca_data']."-".str_pad($i, 2, 0, STR_PAD_LEFT);
					$sqlEndosso = mysqli_fetch_all(
						query(
							"SELECT * FROM endosso 
								WHERE endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
									AND ('".$dataVez."' BETWEEN endo_tx_de AND endo_tx_ate)
									AND endo_tx_status != 'inativo'"
						),
						MYSQLI_ASSOC
					);
					
					if(count($sqlEndosso) == 0){ //Se não estiver endossado nesse dia, passa para o próximo.
						continue;
					}

					$diasEndossados++;

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
								$aDetalhado[$tipo] = str_replace('<br>D+1', ' D+1', $aDetalhado[$tipo]);
							}else{
								$aDetalhado[$tipo] = '';
							}
						}
					//}

					$totalResumo['adicionalNoturno'] = operarHorarios([$totalResumo['adicionalNoturno'], $aDetalhado['adicionalNoturno']], '+');

					$row = array_values(array_merge([verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])], [$aMotorista['enti_tx_matricula']], $aDetalhado));
					for($f = 0; $f < sizeof($row)-1; $f++){
						if($f == 13){//Se for da coluna "Jornada Prevista", não apaga
							continue;
						}
						if($row[$f] == "00:00"){
							$row[$f] = "";
						}
					}

					array_shift($row);
					array_splice($row, 11, 1); //Retira a coluna de "Jornada" que está entre "Repouso" e "Jornada Prevista"
					$aDia[] = $row;
				}
			//}

			//Inserir coluna de motivos{
				for($f = 0; $f < count($aDia); $f++){
					$data = explode('/', $aDia[$f][1]);
					$data = $data[2].'-'.$data[1].'-'.$data[0];
					$bdMotivos = mysqli_fetch_all(
						query(
							"SELECT * FROM ponto 
								JOIN motivo ON pont_nb_motivo = moti_nb_id
								WHERE pont_tx_matricula = '".$aDia[$f][0]."' 
									AND pont_tx_data LIKE '".$data."%'
									AND pont_tx_tipo IN (1,2,3,4)
									AND pont_tx_status = 'ativo'"
						), 
						MYSQLI_ASSOC
					);
					$motivos = '';
					for($f2 = 0; $f2 < count($bdMotivos); $f2++){
						$motivos .= $bdMotivos[$f2]['moti_tx_nome'].'<br>';
					}

					array_splice($aDia[$f], 19, 0, $motivos); // 19 pois a coluna de motivo, no momento da implementação, estava na coluna 19
				}
			//}

			//unset($aMotorista);

			$aEndosso = carrega_array(query(
				"SELECT endo_tx_dataCadastro, endo_tx_ate, endo_tx_horasApagar, endo_tx_pagarHoras FROM endosso 
					WHERE endo_tx_matricula = '$aMotorista[enti_tx_matricula]'"
			));
			$saldoAnterior = mysqli_fetch_all(
				query(
					"SELECT endo_tx_filename FROM endosso
						WHERE endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
							AND endo_tx_ate < '".$_POST['busca_data']."-01'
							AND endo_tx_status = 'ativo'
						ORDER BY endo_tx_ate DESC
						LIMIT 1;"
				),
				MYSQLI_ASSOC
			);
			if($saldoAnterior == null || count($saldoAnterior) == 0){
				$saldoAnterior = '00:00';
			}else{
				$saldoAnterior = $saldoAnterior[0];
				//Puxando Saldo do último Endosso{
					$csvFile = fopen('./arquivos/endosso/'.$saldoAnterior['endo_tx_filename'].'.csv', "r");
					$csvKeys = fgetcsv($csvFile);
					$csvValues = fgetcsv($csvFile);
					
					$csvEndosso = $csvValues[array_search('totalResumo', $csvKeys)];
					$saldoAnterior = json_decode($csvEndosso)->saldoAtual;

					unset($csvFile);
					unset($csvKeys);
					unset($csvValues);
				//}
			}
			
			$sqlMotorista = query(
				"SELECT * FROM entidade".
					" LEFT JOIN parametro ON enti_nb_parametro = para_nb_id".
					" WHERE enti_tx_tipo = 'Motorista'".
					" AND enti_nb_id IN (" . $_POST['idMotoristaEndossado'].")".
					" AND enti_nb_empresa = " . $_POST['busca_empresa'] .
					" ORDER BY enti_tx_nome");
			$dadosMotorista = carrega_array($sqlMotorista);

			$dataCiclo = ['de' => strtotime($dadosMotorista['enti_tx_admissao'].' 00:00:00'), 'ate' => strtotime($dadosMotorista['enti_tx_admissao'].' 00:00:00')];
			$endoTimestamp = strtotime($aEndosso['endo_tx_ate'].' 00:00:00');
			while($dataCiclo['ate'] < $endoTimestamp){
				$dataCiclo['ate'] += ($dadosMotorista['para_nb_qDias'])*24*60*60;
			}
			$dataCiclo['de'] = $dataCiclo['ate']-($dadosMotorista['para_nb_qDias'])*24*60*60;
			$dataCiclo['ate'] = $dataCiclo['ate']-1*24*60*60;

			$dataCiclo['de']  = date('Y-m-d', $dataCiclo['de']);
			$dataCiclo['ate'] = date('Y-m-d', $dataCiclo['ate']);


			if($aEndosso['endo_tx_dataCadastro'] > $dataCiclo['ate']){
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


			if (isset($dadosMotorista['para_nb_qDias']) && !empty($dadosMotorista['para_nb_qDias']) && date('Y-m-d') >= $dataCiclo['ate']){ //Deve ser feito somente quando for obrigado a pagar a hora extra?

				//Contexto do HE100
				// $he100 = strtotime($aDetalhado['he100']);
				if(!empty($aDetalhado['he100'])){
					$he100 = explode(':', $aDetalhado['he100']);
					$he100 = intval($he100[0]) * 60 + ($he100[0][0] == '-' ? -1 : 1) * intval($he100[1]);
				}else{
					$he100 = 0;
				}
				
				$he50_pagar = explode(':', $totalResumo['he50']);
				$he50_pagar = intval($he50_pagar[0]) * 60 + ($he50_pagar[0][0] == '-' ? -1 : 1) * intval($he50_pagar[1]);
				
				$he100_pagar = explode(':', $totalResumo['he100']);
				$he100_pagar = intval($he100_pagar[0]) * 60 + ($he100_pagar[0][0] == '-' ? -1 : 1) * intval($he100_pagar[1]);

				$saldoPeriodo = explode(':', $totalResumo['diffSaldo']);
				$saldoPeriodo = intval($saldoPeriodo[0]) * 60 + ($saldoPeriodo[0][0] == '-' ? -1 : 1) * intval($saldoPeriodo[1]);
				
				if ($saldoPeriodo <= 0) {
					if($he50_pagar > 0){
						if($he50_pagar > -($saldoPeriodo)){
							$he50_pagar += $saldoPeriodo;
							$saldoPeriodo = 0;
						}else{
							$saldoPeriodo += $he50_pagar;
							$he50_pagar = 0;
						}
					}
				} else {
					if ($he100_pagar > 0) {
						$transferir = $saldoPeriodo - (($saldoPeriodo > $he100) ? $he100 : 0);

						$saldoPeriodo -= $transferir;
						$he100_pagar += $transferir;
					}else{
						$he100_pagar = 0;
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
				}else{
					$he50_pagar = 0;	
				}
				$totalResumo['he50'] = intToTime($he50_pagar);
				$totalResumo['he100'] = intToTime($he100_pagar);

				$totalResumo['diffSaldo'] = intToTime($saldoPeriodo);
			}else{
				$totalResumo['he50'] = '00:00';
				$totalResumo['he100'] = '00:00';
			}
		}
		$saldoAtual = operarHorarios([$saldoAnterior, $totalResumo['diffSaldo']], '+'); //Utilizado em relatorio_espelho.php

		$legendas = mysqli_fetch_all(query(
			"SELECT UNIQUE moti_tx_legenda FROM motivo 
				WHERE moti_tx_legenda IS NOT NULL;"
			), 
			MYSQLI_ASSOC
		); //Utilizado em relatorio_espelho.php
		
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
		foreach(['busca_empresa', 'busca_data', 'busca_motorista', 'busca_situacao', 'busca_endossado'] as $campo){
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
		if(!empty($_POST['busca_endossado']) && !empty($_POST['busca_empresa'])){
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
				campo_mes('* Data:',    'busca_data',      (!empty($_POST['busca_data'])?      $_POST['busca_data']     : ''), 2),
				combo_net('Motorista:', 'busca_motorista', (!empty($_POST['busca_motorista'])? $_POST['busca_motorista']: ''), 3, 'entidade', '', ' AND enti_tx_tipo = "Motorista"' . $extraMotorista . $extraEmpresaMotorista, 'enti_tx_matricula'),
				combo(    'Situação:',  'busca_situacao',  (!empty($_POST['busca_situacao'])?  $_POST['busca_situacao'] : ''), 2, ['Todos', 'Verificado', 'Não conformidade']),
				combo(    'Endosso:',   'busca_endossado', (!empty($_POST['busca_endossado'])? $_POST['busca_endossado']: ''), 2, ['', 'Endossado', 'Endossado parcialmente', 'Não endossado'])
			];

			if(is_int(strpos($_SESSION['user_tx_nivel'], 'Super Administrador'))){
				array_unshift($c, combo_net('* Empresa:', 'busca_empresa',   (!empty($_POST['busca_empresa'])?   $_POST['busca_empresa']  : ''), 3, 'empresa', 'onchange=selecionaMotorista(this.value)', $extraEmpresa));
			}
		//}

		//BOTOES{
			if ($_POST['busca_situacao'] != 'Verificado') {
				$disabled = 'disabled=disabled title="Filtre apenas por Verificado para efetuar a impressão do endosso."';
			}
			$b = [
				botao("Buscar", 'index', '', '', '', 1),
				botao("Cadastrar Abono", 'layout_abono', '', '', '', 1),
				'<button name="acao" id="botaoContexCadastrar CadastrarEndosso" value="cadastrar_endosso" type="button" class="btn default">Cadastrar Endosso</button>',
				'<button name="acao" id="botaoContexCadastrar ImprimirRelatorio" value="impressao_relatorio" ' . $disabled . ' type="button" class="btn default">Imprimir Relatório</button>',
				'<span id=dadosResumo><b>' . $carregando . '</b></span>'
			];
		//}

		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);

		$cab = [
			"", "MAT.", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
			"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO DIÁRIO / SEMANAL", "HE 50%", "HE&nbsp;100%",
			"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO(**)"
		];

		//function buscar_endosso(){
			$counts = [
				'total' => 0,								//$countEndosso
				'naoConformidade' => 0,						//$countNaoConformidade
				'verificados' => 0,							//countVerificados
				'endossados' => ['sim' => 0, 'nao' => 0],	//countEndossados e $countNaoEndossados
			];
			if(!empty($_POST['busca_data']) && !empty($_POST['busca_empresa']) && !empty($_GET['acao'])){

				$date = new DateTime($_POST['busca_data']);

				$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $date->format('m'), $date->format('Y'));
	
				$sqlMotorista = query(
					"SELECT * FROM entidade
						WHERE enti_tx_tipo = 'Motorista'
							AND enti_nb_empresa = ".$_POST['busca_empresa']." ".$extra."
							AND enti_tx_status != 'inativo'
						ORDER BY enti_tx_nome"
				);
				while ($aMotorista = carrega_array($sqlMotorista)){
					$counts['total']++;
					if(empty($aMotorista['enti_tx_nome']) || empty($aMotorista['enti_tx_matricula'])){
						continue;
					}
	
					//Pegando e formatando registros dos dias{
						for ($i = 1; $i <= $daysInMonth; $i++) {
							$dataVez = $_POST['busca_data']."-".str_pad($i, 2, 0, STR_PAD_LEFT);
							
							$sqlEndosso = mysqli_fetch_all(
								query(
									"SELECT * FROM endosso 
										WHERE endo_tx_matricula = '".$aMotorista['enti_tx_matricula'].
											"' AND '".$dataVez."' BETWEEN endo_tx_de AND endo_tx_ate".
											" AND endo_tx_status != 'inativo'"
								),
								MYSQLI_ASSOC
							);
							if(count($sqlEndosso) == 0){ //Se não estiver endossado nesse dia, passa para o próximo.
								continue;
							}

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
										$aDetalhado[$tipo] = str_replace('<br>D+1', ' D+1', $aDetalhado[$tipo]);
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

					if(count($aDia) == 0){
						echo '<script>alert("Período ainda não endossado.")</script>';
					}

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
							'SELECT para_tx_tolerancia, para_tx_dataCadastro, para_nb_qDias, para_tx_inicioAcordo FROM parametro 
								JOIN entidade ON para_nb_id = enti_nb_parametro 
								WHERE enti_nb_parametro = '.$aMotorista['enti_nb_parametro'].' 
								LIMIT 1;'
						));

						$dataCicloProx = strtotime($dadosParametro['para_tx_inicioAcordo'].' 00:00:00');
						$endoTimestamp = strtotime($aEndosso['endo_tx_ate'].' 00:00:00');
						while($dataCicloProx < $endoTimestamp && !empty($dadosParametro['para_nb_qDias'])){
							$dataCicloProx += $dadosParametro['para_nb_qDias']*24*60*60;
						}
						$dataCicloProx = date('Y-m-d', $dataCicloProx);
						
						$saldoAnterior = mysqli_fetch_all(
							query(
								"SELECT endo_tx_filename FROM endosso
									WHERE endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
										AND endo_tx_ate < '".$_POST['busca_data']."-01'
										AND endo_tx_status = 'ativo'
									ORDER BY endo_tx_ate DESC
									LIMIT 1;"
							),
							MYSQLI_ASSOC
						)[0];

						if(empty($saldoAnterior)){
							$saldoAnterior = '00:00';
						}else{
							//Puxando Saldo do último Endosso{
								$csvFile = fopen('./arquivos/endosso/'.$saldoAnterior['endo_tx_filename'].'.csv', "r");
								$csvKeys = fgetcsv($csvFile);
								$csvValues = fgetcsv($csvFile);
								
								$csvEndosso = $csvValues[array_search('totalResumo', $csvKeys)];
								$saldoAnterior = json_decode($csvEndosso)->saldoAtual;
	
								unset($csvFile);
								unset($csvKeys);
								unset($csvValues);
							//}
						}

						$saldoFinal = '00:00';
						$saldoFinal = somarHorarios([$saldoAnterior, $totalResumo['diffSaldo']]);
						$dataCicloProx = explode('-', $dataCicloProx);
						$dataCicloProx = sprintf('%02d/%02d/%04d', $dataCicloProx[2], $dataCicloProx[1], $dataCicloProx[0]);
	
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
							Fim do ciclo: '.$dataCicloProx;
						

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

		$counts['message'] = '<br><br><b>Total: '.$counts['total'].' | Verificados: '.$counts['verificados'].' | Não Conformidades: '.$counts['naoConformidade'].' | Endossados: '.$counts['endossados']['sim'].' | Não Endossados: '.$counts['endossados']['nao'].'</b>';
		include 'endosso_html.php';
	}
?>