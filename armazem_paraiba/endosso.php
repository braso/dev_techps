<?php
	include "funcoes_ponto.php"; // conecta.php importado dentro de funcoes_ponto
	
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	function intToTime(int $time): string{
		//Obs.: Variável $time deve estar em minutos.
		$res = str_pad(intval($time/60), 2, 0, STR_PAD_LEFT).':'.str_pad(abs($time%60), 2, 0, STR_PAD_LEFT);

		return $res;
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
				WHERE enti_tx_ocupacao IN ('Motorista', 'Ajudante') 
					AND enti_nb_id IN (".$_POST['idMotoristaEndossado'].") 
					AND enti_nb_empresa = ".$_POST['busca_empresa']." 
					AND enti_tx_status != 'inativo'
				ORDER BY enti_tx_nome"
		);
		
		$diasEndossados = 0;

		while ($aMotorista = carrega_array($sqlMotorista)) {
			//Pegando e formatando registros dos dias{
				
				//Montar endosso completo{
					$sqlEndossos = mysqli_fetch_all(
						query(
							'SELECT * FROM endosso 
								WHERE endo_tx_matricula = \''.$aMotorista['enti_tx_matricula'].'\'
									AND endo_tx_status != \'inativo\'
									AND endo_tx_de >= \''.sprintf('%04d-%02d-%02d', $year, $month, '01').'\'
									AND endo_tx_ate <= \''.sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth).'\'
								ORDER BY endo_tx_de ASC'
						),
						MYSQLI_ASSOC
					);
				
					$endossos = [];
					foreach($sqlEndossos as $endosso){
						$aDetalhado = fopen($_SERVER['DOCUMENT_ROOT'].$CONTEX['path'].'/arquivos/endosso/'.$endosso['endo_tx_filename'].'.csv', 'r');
						$keys = fgetcsv($aDetalhado);
						$values = fgetcsv($aDetalhado);
						$aDetalhado = [];
						for($j = 0; $j < count($keys); $j++){
							$aDetalhado[$keys[$j]] = $values[$j];
						}
						$aDetalhado['endo_tx_pontos'] 	= (array)json_decode($aDetalhado['endo_tx_pontos']);
						$aDetalhado['totalResumo'] 		= (array)json_decode($aDetalhado['totalResumo']);
						$endossos[] = $aDetalhado;
					}

					if(count($endossos) > 0){
						$endossoCompleto = $endossos[0];
						for($f = 1; $f < count($endossos); $f++){
							$endossoCompleto['endo_tx_ate'] = $endossos[$f]['endo_tx_ate'];
							$endossoCompleto['endo_tx_pontos'] = array_merge($endossoCompleto['endo_tx_pontos'], $endossos[$f]['endo_tx_pontos']);
							$endossoCompleto['endo_tx_pagarHoras'] = $endossos[$f]['endo_tx_pagarHoras'] == 'sim'? 'sim': $endossoCompleto['endo_tx_pagarHoras'];
							if($endossoCompleto['endo_tx_pagarHoras'] == 'sim'){
								$endossoCompleto['endo_tx_horasApagar'] = operarHorarios([$endossoCompleto['endo_tx_horasApagar'], $endossos[$f]['endo_tx_horasApagar']], '+');	
								if(is_int(strpos($endossoCompleto['endo_tx_horasApagar'], '-'))){
									$endossoCompleto['endo_tx_horasApagar'] = '00:00';
								}
							}
							foreach($endossos[$f]['totalResumo'] as $key => $value){
								$endossoCompleto['totalResumo'][$key] = operarHorarios([$endossoCompleto['totalResumo'][$key], $value], '+');
							}
						}
					}else{
						$endossoCompleto = [];
					}

					//Pesquisar o saldo de um endosso antes do mês pesquisado{
						$saldoAnterior = mysqli_fetch_all(
							query(
								"SELECT endo_tx_saldo FROM endosso 
									WHERE endo_tx_status = 'ativo'
										AND endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
										AND endo_tx_ate < '".$_POST['busca_data']."-01 00:00:00'
									ORDER BY endo_tx_ate DESC
									LIMIT 1"
							),
							MYSQLI_ASSOC
						);
						if(!empty($saldoAnterior)){
							$saldoAnterior = $saldoAnterior[0]['endo_tx_saldo'];
						}elseif(!empty($aMotorista['enti_tx_banco'])){
							$saldoAnterior = $aMotorista['enti_tx_banco'];
						}else{
							$saldoAnterior = '00:00';
						}
						$endossoCompleto['totalResumo']['saldoAnterior'] = $saldoAnterior;
					//}

					$totalResumo = $endossoCompleto['totalResumo'];
				//}

				$totalResumo['saldoAtual'] = operarHorarios([$totalResumo['saldoAnterior'], $totalResumo['diffSaldo']], '+');

				if($totalResumo['diffSaldo'] > "00:00"){
					//Tirar a parte do saldoPeriodo que corresponde ao HE100
				   if($totalResumo['diffSaldo'] > $totalResumo['he100']){
					   $transferir = $totalResumo['he100'];
				   }else{
					   $transferir = $totalResumo['diffSaldo'];
				   }

				   $totalResumo['diffSaldo'] = operarHorarios([$totalResumo['diffSaldo'], $transferir], '-');
				   $totalResumo['saldoAtual'] = operarHorarios([$totalResumo['saldoAtual'], $transferir], '-');
				   $totalResumo['he100'] = $transferir;
				}else{
					$totalResumo['he100'] = '00:00';
				}

				//Limitar a quantidade de HE50 à quantidade informada em endo_tx_horasAPagar{
					if(
						$totalResumo['diffSaldo'] > "00:00"
						&& !empty($endossoCompleto['endo_tx_pagarHoras']) && $endossoCompleto['endo_tx_pagarHoras'] == 'sim'
						&& !empty($endossoCompleto['endo_tx_horasApagar'])
					){
						if($totalResumo['diffSaldo'] > $endossoCompleto['endo_tx_horasApagar']){
							$transferir = $endossoCompleto['endo_tx_horasApagar'];
						}else{
							$transferir = $totalResumo['diffSaldo'];
						}
						$totalResumo['diffSaldo'] = operarHorarios([$totalResumo['diffSaldo'], $transferir], '-');
						$totalResumo['saldoAtual'] = operarHorarios([$totalResumo['saldoAtual'], $transferir], '-');
						$totalResumo['he50'] = $transferir;
					}else{
						$totalResumo['he50'] = '00:00';
					}
				//}

				for ($i = 0; $i < count($endossoCompleto['endo_tx_pontos']); $i++) {
					$diasEndossados++;
					$aDetalhado = $endossoCompleto['endo_tx_pontos'][$i];
					array_shift($aDetalhado);
					array_splice($aDetalhado, 10, 1); //Retira a coluna de "Jornada" que está entre "Repouso" e "Jornada Prevista"
					$aDia[] = $aDetalhado;
				}
			//}

			//Inserir coluna de motivos{
				for($f = 0; $f < count($aDia); $f++){
					$data = explode('/', $aDia[$f][0]);
					$data = $data[2].'-'.$data[1].'-'.$data[0];
					
					$bdMotivos = mysqli_fetch_all(
						query(
							"SELECT * FROM ponto 
								JOIN motivo ON pont_nb_motivo = moti_nb_id
								WHERE pont_tx_matricula = '".$endossoCompleto['endo_tx_matricula']."' 
									AND pont_tx_data LIKE '".$data."%'
									AND pont_tx_tipo IN (1,2,3,4)
									AND pont_tx_status = 'ativo'"
						), 
						MYSQLI_ASSOC
					);

					$bdAbonos = mysqli_fetch_all(
						query("SELECT motivo.moti_tx_nome FROM  abono
								JOIN motivo ON abon_nb_motivo = moti_nb_id
								WHERE abon_tx_matricula = '".$endossoCompleto['endo_tx_matricula']."' 
								AND abon_tx_data LIKE '".$data."%' Limit 1"
							), 
						MYSQLI_ASSOC
					);

					$motivos = '';
					if(!empty($bdAbonos[0]['moti_tx_nome'])){
						$motivos .= 'Abono: '.$bdAbonos[0]['moti_tx_nome'].'<br>';
					}

					for($f2 = 0; $f2 < count($bdMotivos); $f2++){
						$legendas = [
							'' => '',
							'I' => '(I - Incluída Manualmente)',
							'P' => '(P - Pré-Assinalada)',
							'T' => '(T - Outras fontes de marcação)',
							'DSR' => '(DSR - Descanso Semanal Remunerado e Abono)'
						];
						$motivo = isset($legendas[$bdMotivos[$f2]['moti_tx_legenda']])? $bdMotivos[$f2]['moti_tx_nome']: '';
						if(!empty($motivo) && is_bool(strpos($motivos, $motivo))){
							$motivos .= $motivo.'<br>';
						} 
					}
					
					array_splice($aDia[$f], 18, 0, $motivos); // inserir a coluna de motivo, no momento da implementação, estava na coluna 19
				}
			//}
			break; //Adaptar posteriormente para conseguir imprimir mais de um motorista??
		}
		
		include "./relatorio_espelho.php";
		include "./csv_relatorio_espelho.php";
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
		foreach(['busca_empresa', 'busca_data', 'busca_motorista', 'busca_endossado'] as $campo){
			if(isset($_GET[$campo]) && !empty($_GET[$campo])){
				$_POST[$campo] = $_GET[$campo];
			}
		}

		//Conferir se os campos do $_POST estão preenchidos{
			if(!empty($_POST['busca_motorista'])){
				$extra = " AND enti_nb_id = " . $_POST['busca_motorista'];
			}
			if(!empty($_POST['busca_data']) && !empty($_POST['busca_empresa'])){
				$carregando = "Carregando...";
			}else{
				$carregando = '';
			}
			if(empty($_POST['busca_data'])){
				$_POST['busca_data'] = date("Y-m");
			}
			if(!empty($_POST['busca_empresa'])){
				$_POST['busca_empresa'] = (int)$_POST['busca_empresa'];
			}else{
				$_POST['busca_empresa'] = '';
			}
			$extraMotorista = " AND enti_nb_empresa = " . $_POST['busca_empresa'];
			if(!empty($_POST['busca_endossado']) && !empty($_POST['busca_empresa'])){
				if($_POST['busca_endossado'] == 'endossado'){
					$extra .= " AND enti_nb_id IN (SELECT endo_nb_entidade FROM endosso, entidade 
						WHERE '".$_POST['busca_data']."-01' BETWEEN endo_tx_de AND endo_tx_ate".
							" AND enti_nb_empresa = '" . $_POST['busca_empresa'] .
							"' AND endo_nb_entidade = enti_nb_id AND endo_tx_status = 'ativo'
					)";
				}elseif($_POST['busca_endossado'] == 'naoEndossado'){
					$extra .= " AND enti_nb_id NOT IN (SELECT endo_nb_entidade FROM endosso, entidade 
						WHERE '".$_POST['busca_data']."-01' BETWEEN endo_tx_de AND endo_tx_ate".
							" AND enti_nb_empresa = '" . $_POST['busca_empresa'] .
							"' AND endo_nb_entidade = enti_nb_id AND endo_tx_status = 'ativo'
					)";
				}
			}
		//}

		//CAMPOS DE CONSULTA{
			$c = [
				combo_net('Motorista:', 'busca_motorista', (!empty($_POST['busca_motorista'])? $_POST['busca_motorista']: ''), 3, 'entidade', '', ' AND enti_tx_ocupacao IN ("Motorista", "Ajudante")' . $extraMotorista . $extraEmpresaMotorista, 'enti_tx_matricula'),
				campo_mes('Data:',     'busca_data',      (!empty($_POST['busca_data'])?      $_POST['busca_data']     : ''), 2),
				combo(	  'Endossado:',	'busca_endossado', (!empty($_POST['busca_endossado'])? $_POST['busca_endossado']: ''), 2, ['' => '', 'endossado' => 'Sim', 'naoEndossado' => 'Não'])
			];

			if(is_int(strpos($_SESSION['user_tx_nivel'], 'Administrador'))){
				array_unshift($c, combo_net('Empresa*:', 'busca_empresa',   (!empty($_POST['busca_empresa'])?   $_POST['busca_empresa']  : ''), 3, 'empresa', 'onchange=selecionaMotorista(this.value)', $extraEmpresa));
			}
		//}

		//BOTOES{
			$b = [
				botao("Buscar", 'index', '', '', '', 1,'btn btn-info'),
				botao("Cadastrar Abono", 'layout_abono', '', '', '', 1),
				'<button name="acao" id="botaoContexCadastrar CadastrarEndosso" value="cadastrar_endosso" type="button" class="btn btn-success">Cadastrar Endosso</button>',
				'<button name="acao" id="botaoContexCadastrar ImprimirRelatorio" value="impressao_relatorio" type="button" onload="disablePrintButton()" class="btn btn-default">Imprimir Relatório</button>',
			];
		//}

		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b, '<span id="dadosResumo" style="height:"><b>'.$carregando.'</b></span>');

		$cab = [
			"", "DATA", "<div style='margin:10px'>DIA</div>", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
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
						WHERE enti_tx_ocupacao IN ('Motorista', 'Ajudante')
							AND enti_nb_empresa = ".$_POST['busca_empresa']." ".$extra."
							AND enti_tx_status = 'ativo'
						ORDER BY enti_tx_nome"
				);

				$motNaoEndossados = 'MOTORISTA(S) NÃO ENDOSSADO(S): <br><br>';

				while ($aMotorista = carrega_array($sqlMotorista, MYSQLI_ASSOC)){
					$counts['total']++;
					if(empty($aMotorista['enti_tx_nome']) || empty($aMotorista['enti_tx_matricula'])){
						continue;
					}

					//Pegando e formatando registros dos dias{
						$date = new DateTime($_POST['busca_data']);
						$month = intval($date->format('m'));
						$year = intval($date->format('Y'));
						$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

						//Montar endosso completo{
							$sqlEndossos = mysqli_fetch_all(
								query(
									'SELECT * FROM endosso 
										WHERE endo_tx_matricula = \''.$aMotorista['enti_tx_matricula'].'\'
											AND endo_tx_status != \'inativo\'
											AND endo_tx_de >= \''.sprintf('%04d-%02d-%02d', $year, $month, '01').'\'
											AND endo_tx_ate <= \''.sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth).'\'
										ORDER BY endo_tx_de ASC'
								),
								MYSQLI_ASSOC
							);

							if(count($sqlEndossos) == 0){
								$counts['endossados']['nao']++;
								$motNaoEndossados .= "- [".$aMotorista['enti_tx_matricula']."] ".$aMotorista['enti_tx_nome'].'<br>';
								continue;
							}
						
							$endossos = [];
							foreach($sqlEndossos as $endosso){
								$aDetalhado = fopen($_SERVER['DOCUMENT_ROOT'].$CONTEX['path'].'/arquivos/endosso/'.$endosso['endo_tx_filename'].'.csv', 'r');
								$keys = fgetcsv($aDetalhado);
								$values = fgetcsv($aDetalhado);
								$aDetalhado = [];
								for($j = 0; $j < count($keys); $j++){
									if(substr_count($values[$j], 'fa-warning')>0){
										$counts['naoConformidade'] += substr_count($values[$j], 'fa-warning');
									}
									$aDetalhado[$keys[$j]] = $values[$j];
								}
								$aDetalhado['endo_tx_pontos'] 	= (array)json_decode($aDetalhado['endo_tx_pontos']);
								$aDetalhado['totalResumo'] 		= (array)json_decode($aDetalhado['totalResumo']);
								$endossos[] = $aDetalhado;
							}

							if(count($endossos) > 0){
								$endossoCompleto = $endossos[0];
								for($f = 1; $f < count($endossos); $f++){
									$endossoCompleto['endo_tx_ate'] = $endossos[$f]['endo_tx_ate'];
									$endossoCompleto['endo_tx_pontos'] = array_merge($endossoCompleto['endo_tx_pontos'], $endossos[$f]['endo_tx_pontos']);
									$endossoCompleto['endo_tx_pagarHoras'] = $endossos[$f]['endo_tx_pagarHoras'] == 'sim'? 'sim': $endossoCompleto['endo_tx_pagarHoras'];
									if($endossoCompleto['endo_tx_pagarHoras'] == 'sim'){
										$endossoCompleto['endo_tx_horasApagar'] = operarHorarios([$endossoCompleto['endo_tx_horasApagar'], $endossos[$f]['endo_tx_horasApagar']], '+');	
										if(is_int(strpos($endossoCompleto['endo_tx_horasApagar'], '-'))){
											$endossoCompleto['endo_tx_horasApagar'] = '00:00';
										}
									}
									foreach($endossos[$f]['totalResumo'] as $key => $value){
										$endossoCompleto['totalResumo'][$key] = operarHorarios([$endossoCompleto['totalResumo'][$key], $endossos[$f]['totalResumo'][$key]], '+');
									}
								}
							}else{
								$endossoCompleto = [];
							}

							//Pesquisar o saldo de um endosso antes do mês pesquisado{
								$saldoAnterior = mysqli_fetch_all(
									query(
										"SELECT endo_tx_saldo FROM endosso 
											WHERE endo_tx_status = 'ativo'
												AND endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
												AND endo_tx_ate < '".$_POST['busca_data']."-01 00:00:00'
											ORDER BY endo_tx_ate DESC
											LIMIT 1"
									),
									MYSQLI_ASSOC
								);

								if(!empty($saldoAnterior)){
									$saldoAnterior = $saldoAnterior[0]['endo_tx_saldo'];
								}elseif(!empty($aMotorista['enti_tx_banco'])){
									$saldoAnterior = $aMotorista['enti_tx_banco'];
								}else{
									$saldoAnterior = '00:00';
								}
								$endossoCompleto['totalResumo']['saldoAnterior'] = $saldoAnterior;
							//}

							$totalResumo = $endossoCompleto['totalResumo'];
						//}

						$totalResumo['saldoAtual'] = operarHorarios([$totalResumo['saldoAnterior'], $totalResumo['diffSaldo']], '+');

						for ($i = 0; $i < count($endossoCompleto['endo_tx_pontos']); $i++) {
							$aDetalhado = $endossoCompleto['endo_tx_pontos'][$i];
							$aDia[] = $aDetalhado;
						}
						$totalResumoGrid = $totalResumo;
						unset($totalResumoGrid['saldoAtual']);
						unset($totalResumoGrid['saldoAnterior']);
						if(count($aDia) > 0){
							$aDia[] = array_values(array_merge(['', '', '', '', '', '', '<b>TOTAL</b>'], $totalResumoGrid));
						}
						unset($totalResumoGrid);
					//}

					if (count($aDia) > 0) {
						$counts['endossados']['sim']++;
						
						$dadosParametro = carrega_array(query(
							'SELECT para_tx_tolerancia, para_tx_dataCadastro, para_nb_qDias, para_tx_inicioAcordo FROM parametro 
								JOIN entidade ON para_nb_id = enti_nb_parametro 
								WHERE enti_nb_parametro = '.$aMotorista['enti_nb_parametro'].' 
								LIMIT 1;'
						));

						$dataCicloProx = strtotime($dadosParametro['para_tx_inicioAcordo'].' 00:00:00');
						$endoTimestamp = strtotime($endossoCompleto['endo_tx_ate'].' 00:00:00');
						while($dataCicloProx < $endoTimestamp && !empty($dadosParametro['para_nb_qDias'])){
							$dataCicloProx += $dadosParametro['para_nb_qDias']*24*60*60;
						}
						$dataCicloProx = date('Y-m-d', $dataCicloProx);
						$dataCicloProx = explode('-', $dataCicloProx);
						$dataCicloProx = sprintf('%02d/%02d/%04d', $dataCicloProx[2], $dataCicloProx[1], $dataCicloProx[0]);

						$userCadastro = carregar('user', $endossoCompleto['endo_nb_userCadastro']);
						$infoEndosso = " - Endossado por " . $userCadastro['user_tx_login'] . " em " . data($endossoCompleto['endo_tx_dataCadastro'], 1);

						$aEmpresa = carregar('empresa', $aMotorista['enti_nb_empresa']);

						// if (!empty($aEmpresa['empr_nb_parametro'])) {
						// 	$aParametro = carregar('parametro', $aEmpresa['empr_nb_parametro']);
						// 	if (
						// 		$aParametro['para_tx_jornadaSemanal'] != $aMotorista['enti_tx_jornadaSemanal'] ||
						// 		$aParametro['para_tx_jornadaSabado'] != $aMotorista['enti_tx_jornadaSabado'] ||
						// 		$aParametro['para_tx_percentualHE'] != $aMotorista['enti_tx_percentualHE'] ||
						// 		$aParametro['para_tx_percentualSabadoHE'] != $aMotorista['enti_tx_percentualSabadoHE'] ||
						// 		$aParametro['para_nb_id'] != $aMotorista['enti_nb_parametro']
						// 	) {
						// 		$parametroPadrao = 'Convenção Não Padronizada, Semanal ('.$aMotorista['enti_tx_jornadaSemanal'].'), Sábado ('.$aMotorista['enti_tx_jornadaSabado'].')';
						// 	} else {
						// 		$parametroPadrao = 'Convenção Padronizada: '.$aParametro['para_tx_nome'].', Semanal ('.$aParametro['para_tx_jornadaSemanal'].'), Sábado ('.$aParametro['para_tx_jornadaSabado'].')';
						// 	}
						// }else{
							
						// }

						$saldosMotorista = 'SALDOS: <br>
							<div class="table-responsive">
								<table class="table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact" id="saldo">
									<thead>
										<tr>
											<th>Anterior:</th>
											<th>Período:</th>
											<th>Final:</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>'.$totalResumo['saldoAnterior'].'</td>
											<td>'.$totalResumo['diffSaldo'].'</td>
											<td>'.$totalResumo['saldoAtual'].'</td>
										</tr>
									</tbody>
								</table>
							</div>'
						;
						
						$aEmpresa = carregar('empresa', $aMotorista['enti_nb_empresa']);

						abre_form(
							"$aEmpresa[empr_tx_nome]<br>"
							."[$aMotorista[enti_tx_matricula]] $aMotorista[enti_tx_nome]<br>"
							."<br>"/*."$parametroPadrao<br><br>"*/
							.$saldosMotorista
						);
						
						grid2($cab, $aDia);
						fecha_form();

						$aSaldo[$aMotorista['enti_tx_matricula']] = $totalResumo['diffSaldo'];
					}else{
						$counts['endossados']['nao']++;
						$motNaoEndossados .= "- [".$aMotorista['enti_tx_matricula']."] ".$aMotorista['enti_tx_nome'].'<br>';
					}
	
					$totalResumo = ['diffRefeicao' => '00:00', 'diffEspera' => '00:00', 'diffDescanso' => '00:00', 'diffRepouso' => '00:00', 'diffJornada' => '00:00', 'jornadaPrevista' => '00:00', 'diffJornadaEfetiva' => '00:00', 'maximoDirecaoContinua' => '', 'intersticio' => '00:00', 'he50' => '00:00', 'he100' => '00:00', 'adicionalNoturno' => '00:00', 'esperaIndenizada' => '00:00', 'diffSaldo' => '00:00'];

					unset($aDia);
				}
			}

			if($counts['endossados']['nao'] > 0){
				abre_form($motNaoEndossados);
				fecha_form();
			}
			if(!isset($_POST['busca_motorista']) || empty($_POST['busca_motorista']) || (!empty($_POST['busca_motorista']) && $counts['endossados']['sim'] == 0)){
				echo 
					'<script>
						(function(){
							button = document.getElementById("botaoContexCadastrar ImprimirRelatorio");
							button.setAttribute("disabled", true);
							button.setAttribute("title", "Pesquise um motorista endossado para efetuar a impressão do endosso.");
							return;
						})();
					</script>'
				;
			}
		//}
		echo '<div class="printable"></div>';

		rodape();

		$counts['message'] = '<b>Motoristas: '.$counts['total'].' | Verificados: '.$counts['verificados'].' | Não Conformidades: '.$counts['naoConformidade'].' | Endossados: '.$counts['endossados']['sim'].' | Não Endossados: '.$counts['endossados']['nao'].'</b>';

		$select2URL = 
			$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/select2.php"
			."?path=".$CONTEX['path']
			."&tabela=entidade"
			."&extra_limite=15"
			."&extra_busca=enti_tx_matricula"
		; // Utilizado dentro de endosso_html.php

		include_once 'endosso_html.php';
		echo 
			"<script>
				window.onload = function() {
					document.getElementById('dadosResumo').innerHTML = '".$counts['message']."';
			
					document.getElementById('botaoContexCadastrar CadastrarEndosso').onclick = function() {
						window.location.href = '".$_SERVER['HTTP_ORIGIN'].$CONTEX['path']."/cadastro_endosso.php';
					}
			
					document.getElementById('botaoContexCadastrar ImprimirRelatorio').onclick = function() {
						document.form_imprimir_relatorio.submit();
					}
				};
			</script>"
		;
	}
?>