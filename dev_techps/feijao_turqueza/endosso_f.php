<?php
include "funcoes_ponto.php"; // NAO ALTERAR ORDEM

	function cadastrar(){
		$url = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/'));
		header('Location: '.'https://braso.mobi'.$url.'/cadastro_endosso');
		exit();
	}

function imprimir_endosso() {
	global $totalResumo, $contagemEspera;

	if ($_POST[busca_data] && $_POST[busca_empresa]) {

		$date = new DateTime($_POST[busca_data]);
		$month = $date->format('m');
		$year = $date->format('Y');

		$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

		$primeiroDia = '01/' . $month . '/' . $year;
		$ultimoDia = $daysInMonth . '/' . $month . '/' . $year;

		$aEmpresa = carregar('empresa', $_POST[busca_empresa]);
		$aCidadeEmpresa = carregar('cidade', $aEmpresa[empr_nb_cidade]);

		$enderecoEmpresa = implode(
			", ",
			array_filter(array(
				$aEmpresa[empr_tx_endereco],
				$aEmpresa[empr_tx_numero],
				$aEmpresa[empr_tx_bairro],
				$aEmpresa[empr_tx_complemento],
				$aEmpresa[empr_tx_referencia]
			))
		);

		$sqlMotorista = query("SELECT * FROM entidade WHERE enti_tx_tipo = 'Motorista' AND enti_nb_id IN (" . $_POST[idMotoristaEndossado] . ") AND enti_nb_empresa = " . $_POST[busca_empresa] . " ORDER BY enti_tx_nome");
		while ($aMotorista = carrega_array($sqlMotorista)) {

			$sqlAbono = query("SELECT * FROM abono, motivo, user 
				WHERE abon_tx_status != 'inativo' AND abon_nb_userCadastro = user_nb_id 
				AND abon_tx_matricula = '$aMotorista[enti_tx_matricula]' AND abon_tx_data LIKE '$_POST[busca_data]-%' AND abon_nb_motivo = moti_nb_id
				ORDER BY abon_nb_id DESC LIMIT 1");

			$qtdeAbono = num_linhas($sqlAbono);

			$sqlFolga = query("SELECT * FROM abono, motivo, user 
					WHERE abon_tx_status != 'inativo' AND abon_nb_userCadastro = user_nb_id AND moti_nb_id = 29
					AND abon_tx_matricula = '$aMotorista[enti_tx_matricula]' AND abon_tx_data LIKE '$_POST[busca_data]-%' AND abon_nb_motivo = moti_nb_id
					ORDER BY abon_nb_id DESC LIMIT 1");

			$qtdeFolga = num_linhas($sqlFolga);

			$sqlFalta = query("SELECT * FROM abono, motivo, user 
						WHERE abon_tx_status != 'inativo' AND abon_nb_userCadastro = user_nb_id AND moti_nb_id = 32
						AND abon_tx_matricula = '$aMotorista[enti_tx_matricula]' AND abon_tx_data LIKE '$_POST[busca_data]-%' AND abon_nb_motivo = moti_nb_id
						ORDER BY abon_nb_id DESC LIMIT 1");

			$qtdeFalta = num_linhas($sqlFalta);

			for ($i = 1; $i <= $daysInMonth; $i++) {
				$dataVez = $_POST[busca_data] . "-" . str_pad($i, 2, 0, STR_PAD_LEFT);

				$aDetalhado = diaDetalhePonto($aMotorista[enti_tx_matricula], $dataVez);

				$aDia[] = array_values(array_merge(array(verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])), array($aMotorista[enti_tx_matricula]), $aDetalhado));
			}

?>
			<!DOCTYPE html>
			<html lang="en">

			<head>
				<meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<title>Espelho de Ponto</title>
				<link rel="stylesheet" href="css/endosso.css">
			</head>

			<body>
				<div class="header">
					<img src="<?= $aEmpresa[empr_tx_logo] ?>" alt="Logo Empresa Esquerda">
					<h1>Espelho de Ponto</h1>
					<div class="right-logo">
						<p></p>
						<img src="/techps/sistema/imagens/logo_topo_cliente.png" alt="Logo Empresa Direita">
					</div>
				</div>
				<div class="info">
					<table class="table-header">
						<tr class="employee-info">
							<td style="text-align: left;"><b>Motorista:</b> <?= $aMotorista[enti_tx_nome] ?></td>
							<td style="text-align: left;"><b>Função:</b> <?= $aMotorista[enti_tx_ocupacao] ?></td>
							<td style="text-align: left;"><b>CPF:</b> <?= $aMotorista[enti_tx_cpf] ?></td>
							<td style="text-align: left;"><b>Turno:</b> D.SEM/H: <?= $aMotorista[enti_tx_jornadaSemanal] ?> FDS/H: <?= $aMotorista[enti_tx_jornadaSabado] ?> </td>
							<td style="text-align: left;"><b>Matrícula:</b> <?= $aMotorista[enti_tx_matricula] ?></td>
							<td style="text-align: left;"><b>Admissão:</b> <?= data($aMotorista[enti_tx_admissao]) ?></td>
						</tr>

						<tr class="company-info">
							<td style="text-align: left;"><b>Empresa:</b> <?= $aEmpresa[empr_tx_nome] ?></td>
							<td style="text-align: left;"><b>CNPJ:</b> <?= $aEmpresa[empr_tx_cnpj] ?></td>
							<td colspan="2" style="text-align: left;"><b>End.</b> <?= "$enderecoEmpresa, $aCidadeEmpresa[cida_tx_nome]/$aCidadeEmpresa[cida_tx_uf], $aEmpresa[empr_tx_cep]" ?></td>
							<td style="text-align: left;"><b>Período:</b> <?= "$primeiroDia à $ultimoDia" ?></td>
							<td style="text-align: left;"><b>Emissão Doc.:</b> <?= date("d/m/Y \T H:i:s") . "(UTC-3)" ?></td>
						</tr>
					</table>
				</div>
				<table class="table" border="">
					<thead>
						<tr>
							<th>MAT.</th>
							<th>DATA</th>
							<th>DIA</th>
							<th>INÍCIO JORNADA</th>
							<th>INÍCIO REFEIÇÃO</th>
							<th>FIM REFEIÇÃO</th>
							<th>FIM JORNADA</th>
							<th>REFEIÇÃO</th>
							<th>ESPERA</th>
							<th>DESCANSO</th>
							<th>REPOUSO</th>
							<th>JORNADA</th>
							<th>JORNADA PREVISTA</th>
							<th>JORNADA EFETIVA</th>
							<th>MDC</th>
							<th>INTERSTÍCIO</th>
							<th>HE 50%</th>
							<th>HE&nbsp;100%</th>
							<th>ADICIONAL NOT.</th>
							<th>ESPERA INDENIZADA</th>
							<th>SALDO DIÁRIO</th>
						</tr>
					</thead>
					<tbody>
						<?
						// $aDia[] = array_values(array_merge(array('','','','','','','','<b>TOTAL</b>'), $totalResumo));
						for ($i = 0; $i < count($aDia); $i++) {
							$j = 1;
							$aDiaVez = $aDia[$i];
						?>
							<tr>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
								<td><?= $aDiaVez[$j++] ?></td>
							</tr>

						<?
						}
						?>

					</tbody>
				</table>

				<div><b>TOTAL: <?= $daysInMonth ?> dias</b></div>


				<table class="table-bottom">
					<tr>
						<td rowspan="2">
							<table class="table-info">
								<tr>
									<td>Carga horaria Mensal</td>
									<td>
										<center><?= $totalResumo['jornadaPrevista'] ?></center>
									</td>
								</tr>
								<tr>
									<td>Horas Efetivas realizadas:</td>
									<td>
										<center><?= $totalResumo['diffJornadaEfetiva'] ?></center>
									</td>
								</tr>
								<tr>
									<td>Adicional Noturno:</td>
									<td>
										<center><?= $totalResumo['adicionalNoturno'] ?></center>
									</td>
								</tr>
								<tr>
									<td>Horas Extras:</td>
									<td>
										<center><?= $totalResumo['he50'] ?></center>
									</td>
								</tr>
								<tr>
									<td>Horas Extras (100%):</td>
									<td>
										<center><?= $totalResumo['he100'] ?></center>
									</td>
								</tr>
								<tr>
									<td>Espera Indenizada:</td>
									<td>
										<center><?= $totalResumo['esperaIndenizada'] ?></center>
									</td>
								</tr>
								<tr>
									<td>Saldo Período:</td>
									<td>
										<center><?= $totalResumo['diffSaldo'] ?></center>
									</td>
								</tr>
							</table>

							<table class="table-info2">
								<tr>
									<td>Abonos</td>
									<td>
										<center><?= $qtdeAbono ?></center>
									</td>
								</tr>
								<tr>
									<td>Folgas:</td>
									<td>
										<center><?= $qtdeFolga ?></center>
									</td>
								</tr>

								<tr>
									<td>Faltas:</td>
									<td>
										<center><?= $qtdeFalta ?></center>
									</td>
								</tr>
								<tr>
									<td>Esperas:</td>
									<td>
										<center><?= $contagemEspera ?></center>
									</td>
								</tr>

							</table>
						</td>

						<td>
							<table class="table-resumo">
								<tr>
									<td>Saldo Anterior</td>
									<td>11:11</td>
									<td class="empty"></td>
									<td>Saldo Período</td>
									<td>22:22</td>
									<td class="empty"></td>
									<td>Saldo Atual</td>
									<td>33:33</td>
								</tr>
							</table>
						</td>

					</tr>
					<tr>
						<td>
							<div class="signature-block" style="display: inline-block; width: 45%;">
								<center>
									<p>___________________________________________________________</p>
								</center>
								<center>
									<p>Responsável</p>
								</center>
								<center>
									<p>Cargo:</p>
								</center>
							</div>
							<div class="signature-block" style="display: inline-block; width: 45%;">
								<center>
									<p>___________________________________________________________</p>
								</center>
								<center>
									<p><?= $aMotorista[enti_tx_nome] ?></p>
								</center>
								<center>
									<p>Motorista</p>
								</center>
							</div>
						</td>
					</tr>
				</table>
			</body>

			</html>
			<div style="page-break-after: always;"></div>

	<?
			$totalResumo = array('diffRefeicao' => '00:00', 'diffEspera' => '00:00', 'diffDescanso' => '00:00', 'diffRepouso' => '00:00', 'diffJornada' => '00:00', 'jornadaPrevista' => '00:00', 'diffJornadaEfetiva' => '00:00', 'maximoDirecaoContinua' => '', 'intersticio' => '00:00', 'he50' => '00:00', 'he100' => '00:00', 'adicionalNoturno' => '00:00', 'esperaIndenizada' => '00:00', 'diffSaldo' => '00:00');
			unset($aDia);
			unset($contagemEspera);
		}
	}

	exit;
}


function cadastrar_endosso() {
	$aSaldo = json_decode($_POST['aSaldo'], true);

	$aID = explode(',', $_POST['idMotorista']);
	$aMatricula = explode(',', $_POST['matriculaMotorista']);


	for ($i = 0; $i < count($aID); $i++) {
		$sqlCheck = query("SELECT endo_nb_id FROM endosso WHERE endo_tx_mes = '" . $_POST[busca_data] . '-01' . "' 
			AND endo_nb_entidade = '" . $aID[$i] . "' AND endo_tx_matricula = '" . $aMatricula[$i] . "' AND endo_tx_status = 'ativo'");
		if (num_linhas($sqlCheck) == 0) {

			$campos = array('endo_nb_entidade', 'endo_tx_matricula', 'endo_tx_mes', 'endo_tx_dataCadastro', 'endo_nb_userCadastro', 'endo_tx_status', 'endo_tx_saldo');
			$valores = array($aID[$i], $aMatricula[$i], $_POST[busca_data] . '-01', date("Y-m-d H:i:s"), $_SESSION[user_nb_id], 'ativo', $aSaldo[$aMatricula[$i]]);

			inserir('endosso', $campos, $valores);
		}
	}

	index();
	exit;
}


function index() {
	global $totalResumo;

	cabecalho('Endosso');

	if ($_SESSION[user_nb_empresa] > 0 && $_SESSION[user_tx_nivel] != 'Administrador') {
		$extraEmpresa = " AND empr_nb_id = '$_SESSION[user_nb_empresa]'";
		$extraEmpresaMotorista = " AND enti_nb_empresa = '$_SESSION[user_nb_empresa]'";
	}

	if ($_POST['busca_motorista']) {
		$extra = " AND enti_nb_id = " . $_POST['busca_motorista'];
	}

	if ($_POST[busca_data] && $_POST[busca_empresa]) {
		$carregando = "Carregando...";
	}

	if ($_POST[busca_data] == '') {
		$_POST[busca_data] = date("Y-m");
	}

	if ($_POST[busca_empresa]) {
		$_POST[busca_empresa] = (int)$_POST[busca_empresa];
		$extraMotorista = " AND enti_nb_empresa = '$_POST[busca_empresa]'";
	}

	if ($_POST[busca_endossado] && $_POST[busca_empresa]) {
		if ($_POST[busca_endossado] == 'Endossado') {
			$extra .= " AND enti_nb_id IN (
				SELECT endo_nb_entidade FROM endosso, entidade WHERE endo_tx_mes = '" . substr($_POST[busca_data], 0, 7) . '-01' . "' AND enti_nb_empresa = '$_POST[busca_empresa]' 
				AND endo_nb_entidade = enti_nb_id AND endo_tx_status = 'ativo'
				)";
		}

		if ($_POST[busca_endossado] == 'Não endossado') {
			$extra .= " AND enti_nb_id NOT IN (
				SELECT endo_nb_entidade FROM endosso, entidade WHERE endo_tx_mes = '" . substr($_POST[busca_data], 0, 7) . '-01' . "' AND enti_nb_empresa = '$_POST[busca_empresa]' 
				AND endo_nb_entidade = enti_nb_id AND endo_tx_status = 'ativo'
				)";
		}
	}


	$countEndosso = 0;
	$countNaoConformidade = 0;
	$countVerificados = 0;
	$countEndossados = 0;
	$countNaoEndossados = 0;

	//CONSULTA
	$c[] = combo_net('* Empresa:', 'busca_empresa', $_POST[busca_empresa], 3, 'empresa', 'onchange=selecionaMotorista(this.value)', $extraEmpresa);
	$c[] = campo_mes('* Data:', 'busca_data', $_POST[busca_data], 2);
	$c[] = combo_net('Motorista:', 'busca_motorista', $_POST[busca_motorista], 3, 'entidade', '', ' AND enti_tx_tipo = "Motorista"' . $extraMotorista . $extraEmpresaMotorista, 'enti_tx_matricula');
	$c[] = combo('Situação:', 'busca_situacao', $_POST[busca_situacao], 2, array('Todos', 'Verificado', 'Não conformidade'));
	$c[] = combo('Endosso:', 'busca_endossado', $_POST[busca_endossado], 2, array('', 'Endossado', 'Não endossado'));

	//BOTOES
	$b[] = botao("Buscar", 'index', '', '', '', 1);
	$b[] = botao("Cadastrar Abono", 'layout_abono', '', '', '', 1);
	// $b[] = botao("Cadastrar Endosso",'cadastra_endosso');
	if ($_POST[busca_situacao] != 'Verificado') {
		$disabled = 'disabled=disabled title="Filtre apenas por Verificado para efetuar o endosso."';
		$disabled2 = 'disabled=disabled title="Filtre apenas por Verificado para efetuar a impressão endosso."';
	}
	$b[] = '<button name="acao" id="botaoContexCadastrar CadastrarEndosso" value="cadastrar_endosso" ' . $disabled . ' type="button" class="btn default">Cadastrar Endosso</button>';
	$b[] = '<button name="acao" id="botaoContexCadastrar ImprimirEndosso" value="impressao_endosso" ' . $disabled2 . ' type="button" class="btn default">Imprimir Endossados</button>';
	$b[] = '<span id=dadosResumo><b>' . $carregando . '</b></span>';


	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	// $cab = array("MATRÍCULA", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA", "REFEIÇÃO", "ESPERA", "ATRASO", "EFETIVA", "PERÍODO TOTAL", "INTERSTÍCIO DIÁRIO", "INT. SEMANAL", "ABONOS", "FALTAS", "FOLGAS", "H.E.", "H.E. 100%", "ADICIONAL NOTURNO", "ESPERA INDENIZADA", "OBSERVAÇÕES");
	$cab = array(
		"", "MAT.", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
		"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA", "MDC", "INTERSTÍCIO", "HE 50%", "HE&nbsp;100%",
		"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO DIÁRIO"
	);

	if ($_POST[busca_data] && $_POST[busca_empresa]) {

		$date = new DateTime($_POST[busca_data]);
		$month = $date->format('m');
		$year = $date->format('Y');

		$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

		$sqlMotorista = query("SELECT * FROM entidade WHERE enti_tx_tipo = 'Motorista' AND enti_nb_empresa = " . $_POST[busca_empresa] . " $extra ORDER BY enti_tx_nome");
		while ($aMotorista = carrega_array($sqlMotorista)) {
			$aEmpresa = carregar('empresa', $aMotorista['enti_nb_empresa']);

			for ($i = 1; $i <= $daysInMonth; $i++) {
				$dataVez = $_POST[busca_data] . "-" . str_pad($i, 2, 0, STR_PAD_LEFT);

				$aDetalhado = diaDetalhePonto($aMotorista[enti_tx_matricula], $dataVez);

				$aDia[] = array_values(array_merge(array(verificaTolerancia($aDetalhado['diffSaldo'], $dataVez, $aMotorista['enti_nb_id'])), array($aMotorista[enti_tx_matricula]), $aDetalhado));
				$aDiaOriginal[] = $aDetalhado;
			}

			// if($aMotorista[enti_tx_matricula]=='6952'){
			// 	echo "<pre>";
			// 	print_r($aDiaOriginal);
			// 	echo "</pre>";
			// }

			$exibir = 1;

			for ($i = 0; $i < count($aDiaOriginal); $i++) {
				$diaVez = $aDiaOriginal[$i];

				if (
					(strpos($diaVez['diffRefeicao'], 'color:red;') !== false) ||
					(strpos($diaVez['diffEspera'], 'color:red;') !== false) ||
					(strpos($diaVez['diffDescanso'], 'color:red;') !== false) ||
					(strpos($diaVez['diffRepouso'], 'color:red;') !== false) ||
					(strpos($diaVez['diffJornada'], 'color:red;') !== false) ||
					(strpos($diaVez['jornadaPrevista'], 'color:red;') !== false) ||
					(strpos($diaVez['diffJornadaEfetiva'], 'color:red;') !== false) ||
					(strpos($diaVez['maximoDirecaoContinua'], 'color:red;') !== false) ||
					(strpos($diaVez['intersticio'], 'color:red;') !== false) ||
					(strpos($diaVez['he50'], 'color:red;') !== false) ||
					(strpos($diaVez['he100'], 'color:red;') !== false) ||
					(strpos($diaVez['adicionalNoturno'], 'color:red;') !== false) ||
					(strpos($diaVez['esperaIndenizada'], 'color:red;') !== false) ||
					(strpos($diaVez['diffSaldo'], 'color:red;') !== false)

				) {
					//SE HOUVER RED E BUSCA POR NAO CONFORMIDADE EXIBE. LOGICA CONTRARIA CASO VERIFICADOS
					if ($_POST[busca_situacao] == 'Não conformidade' || $_POST[busca_situacao] == 'Todos') {
						$countNaoConformidade++;
						$exibir = 1;
						break;
					} elseif ($_POST[busca_situacao] == 'Verificado') {
						$exibir = 0;
						$totalResumo = array('diffRefeicao' => '00:00', 'diffEspera' => '00:00', 'diffDescanso' => '00:00', 'diffRepouso' => '00:00', 'diffJornada' => '00:00', 'jornadaPrevista' => '00:00', 'diffJornadaEfetiva' => '00:00', 'maximoDirecaoContinua' => '', 'intersticio' => '00:00', 'he50' => '00:00', 'he100' => '00:00', 'adicionalNoturno' => '00:00', 'esperaIndenizada' => '00:00', 'diffSaldo' => '00:00');
						break;
					}
				} else {
					if ($_POST[busca_situacao] == 'Não conformidade') {
						$exibir = 0;
					} elseif ($_POST[busca_situacao] == 'Verificado') {
						$exibir = 1;
					}
				}
			}

			if ($exibir == 0) {
				unset($aDia);
				unset($aDiaOriginal);
			}

			if (count($aDia) > 0) {

				$sqlCheck = query("SELECT user_tx_login, endo_tx_dataCadastro FROM endosso, user WHERE endo_tx_mes = '" . substr($_POST[busca_data], 0, 7) . '-01' . "' AND endo_nb_entidade = '" . $aMotorista['enti_nb_id'] . "'
				AND endo_tx_matricula = '" . $aMotorista['enti_tx_matricula'] . "' AND endo_tx_status = 'ativo' AND endo_nb_userCadastro = user_nb_id LIMIT 1");
				$aEndosso = carrega_array($sqlCheck);
				if (count($aEndosso) > 0) {
					$infoEndosso = " - Endossado por " . $aEndosso['user_tx_login'] . " em " . data($aEndosso['endo_tx_dataCadastro'], 1);
					$countEndossados++;
					$aIdMotoristaEndossado[] = $aMotorista[enti_nb_id];
					$aMatriculaMotoristaEndossado[] = $aMotorista[enti_tx_matricula];
				} else {
					$infoEndosso = '';
					$countNaoEndossados++;
				}

				$aIdMotorista[] = $aMotorista[enti_nb_id];
				$aMatriculaMotorista[] = $aMotorista[enti_tx_matricula];

				$countEndosso++;

				if ($aEmpresa[empr_nb_parametro] > 0) {
					$aParametro = carregar('parametro', $aEmpresa[empr_nb_parametro]);
					if (
						$aParametro[para_tx_jornadaSemanal] != $aMotorista[enti_tx_jornadaSemanal] ||
						$aParametro[para_tx_jornadaSabado] != $aMotorista[enti_tx_jornadaSabado] ||
						$aParametro[para_tx_percentualHE] != $aMotorista[enti_tx_percentualHE] ||
						$aParametro[para_tx_percentualSabadoHE] != $aMotorista[enti_tx_percentualSabadoHE] ||
						$aParametro[para_nb_id] != $aMotorista[enti_nb_parametro]
					) {

						$ehPadrao = 'Não';
					} else {
						$ehPadrao = 'Sim';
					}

					$convencaoPadrao = '| Convenção Padrão? ' . $ehPadrao;
				}

				abre_form("[$aMotorista[enti_tx_matricula]] $aMotorista[enti_tx_nome] | $aEmpresa[empr_tx_nome] $infoEndosso $convencaoPadrao");

				$aDia[] = array_values(array_merge(array('', '', '', '', '', '', '', '<b>TOTAL</b>'), $totalResumo));

				grid2($cab, $aDia, "Jornada Semanal (Horas): $aMotorista[enti_tx_jornadaSemanal]");
				fecha_form();

				$aSaldo[$aMotorista[enti_tx_matricula]] = $totalResumo['diffSaldo'];
			}

			$totalResumo = array('diffRefeicao' => '00:00', 'diffEspera' => '00:00', 'diffDescanso' => '00:00', 'diffRepouso' => '00:00', 'diffJornada' => '00:00', 'jornadaPrevista' => '00:00', 'diffJornadaEfetiva' => '00:00', 'maximoDirecaoContinua' => '', 'intersticio' => '00:00', 'he50' => '00:00', 'he100' => '00:00', 'adicionalNoturno' => '00:00', 'esperaIndenizada' => '00:00', 'diffSaldo' => '00:00');

			unset($aDia);
			unset($aDiaOriginal);
		}
	}

	if ($_POST[busca_situacao] == 'Todos' || $_POST[busca_situacao] == 'Verificado') {
		$countVerificados = $countEndosso - $countNaoConformidade;
	}

	?>
	<div class="printable">

	</div>
	<?

	rodape();

	?>

	<style>

	</style>

	<form name="form_cadastrar_endosso" method="post">
		<input type="hidden" name="acao" value="cadastrar_endosso">
		<input type="hidden" name="idMotorista" value="<?= implode(",", $aIdMotorista) ?>">
		<input type="hidden" name="matriculaMotorista" value="<?= implode(",", $aMatriculaMotorista) ?>">
		<input type="hidden" name="busca_empresa" value="<?= $_POST[busca_empresa] ?>">
		<input type="hidden" name="busca_data" value="<?= $_POST[busca_data] ?>">
		<input type="hidden" name="busca_motorista" value="<?= $_POST[busca_motorista] ?>">
		<input type="hidden" name="busca_situacao" value="<?= $_POST[busca_situacao] ?>">
		<input type="hidden" name="aSaldo" value="<?= htmlspecialchars(json_encode($aSaldo)) ?>">
	</form>

	<form name="form_imprimir_endosso" method="post" target="_blank">
		<input type="hidden" name="acao" value="imprimir_endosso">
		<input type="hidden" name="idMotoristaEndossado" value="<?= implode(",", $aIdMotoristaEndossado) ?>">
		<input type="hidden" name="matriculaMotoristaEndossado" value="<?= implode(",", $aMatriculaMotoristaEndossado) ?>">
		<input type="hidden" name="busca_empresa" value="<?= $_POST[busca_empresa] ?>">
		<input type="hidden" name="busca_data" value="<?= $_POST[busca_data] ?>">
		<input type="hidden" name="busca_motorista" value="<?= $_POST[busca_motorista] ?>">
		<input type="hidden" name="busca_situacao" value="<?= $_POST[busca_situacao] ?>">
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
					url: '/contex20/select2.php?path=/techps/sistema&tabela=entidade&extra_ordem=&extra_limite=15&extra_bd=' + buscaExtra + '&extra_busca=enti_tx_matricula',
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
				if (confirm('Deseja confirma o endosso de <?= $countEndosso ?> motorista(s)?\nVerificados: <?= $countVerificados ?> motorista(s).\nNão Conformidade: <?= $countNaoConformidade ?> motorista(s).')) {
					if (<?= count($aIdMotorista) ?>) {
						document.form_cadastrar_endosso.submit()
					} else {
						alert('Não há motoristas para endossar!')
					}
				}
			}

			document.getElementById('botaoContexCadastrar ImprimirEndosso').onclick = function() {
				document.form_imprimir_endosso.submit()
			}

		};
	</script>
<?

}
