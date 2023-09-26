<?php
include "conecta.php";


function calcularAbono($horario1, $horario2) {
	// Converter os horários em minutos
	$horario1 = str_replace("-", "", $horario1);
	$horario2 = str_replace("-", "", $horario2);
	$saldoPositivo = $horario1;

	$minutos1 = intval(substr($horario1, 0, 2)) * 60 + intval(substr($horario1, 3, 2));
	$minutos2 = intval(substr($horario2, 0, 2)) * 60 + intval(substr($horario2, 3, 2));

	$diferencaMinutos = $minutos2 - $minutos1;

	if ($diferencaMinutos > 0) {
		return $saldoPositivo;
	} else {
		return $horario2;
	}
}

function cadastra_abono() {

	$aData = explode(" - ", $_POST[daterange]);

	$begin = new DateTime(data($aData[0]));
	$end = new DateTime(data($aData[1]));

	$a = carregar('entidade', $_POST[motorista]);

	for ($i = $begin; $i <= $end; $i->modify('+1 day')) {

		$sqlRemover = query("SELECT * FROM abono WHERE abon_tx_data = '" . $i->format("Y-m-d") . "' AND abon_tx_matricula = '$a[enti_tx_matricula]' AND abon_tx_status = 'ativo'");
		while ($aRemover = carrega_array($sqlRemover)) {
			remover('abono', $aRemover[abon_nb_id]);
		}

		$aDetalhado = diaDetalhePonto($a[enti_tx_matricula], $i->format("Y-m-d"));

		$abono = calcularAbono($aDetalhado['diffSaldo'], $_POST[abono]);


		$campos = array(abon_tx_data, abon_tx_matricula, abon_tx_abono, abon_nb_motivo, abon_tx_descricao, abon_nb_userCadastro, abon_tx_dataCadastro, abon_tx_status);
		$valores = array($i->format("Y-m-d"), $a[enti_tx_matricula], $abono, $_POST[motivo], $_POST[descricao], $_SESSION[user_nb_id], date("Y-m-d H:i:s"), 'ativo');

		inserir('abono', $campos, $valores);
	}

	$_POST[busca_motorista] = $_POST[motorista];

	index();
	exit;
}

function layout_abono() {
	global $CACTUX_CONF;

	cabecalho('Espelho de Ponto');

	$c[] = combo_net('Motorista:', 'motorista', $_POST[busca_motorista], 4, 'entidade', '', ' AND enti_tx_tipo = "Motorista"', 'enti_tx_matricula');
	$c[] = campo('Data(s):', 'daterange', $_POST[daterange], 3);
	$c[] = campo_hora('Abono: (hh:mm)', 'abono', '', 3);
	$c2[] = combo_bd('Motivo:', 'motivo', $_POST[motivo], 4, 'motivo', '', ' AND moti_tx_tipo = "Abono"');
	$c2[] = textarea('Justificativa:', 'descricao', '', 12);

	//BOTOES
	$b[] = botao("Voltar", 'index');
	$b[] = botao("Gravar", 'cadastra_abono');

	abre_form('Filtro de Busca');
	linha_form($c);
	linha_form($c2);
	fecha_form($b);

	rodape();

?>
	<script type="text/javascript" src="js/moment.min.js"></script>
	<script type="text/javascript" src="js/daterangepicker.min.js"></script>
	<link rel="stylesheet" type="text/css" href="js/daterangepicker.css" />

	<script>
		$(function() {
			$('input[name="daterange"]').daterangepicker({
				opens: 'left',
				"locale": {
					"format": "DD/MM/YYYY",
					"separator": " - ",
					"applyLabel": "Aplicar",
					"cancelLabel": "Cancelar",
					"fromLabel": "From",
					"toLabel": "To",
					"customRangeLabel": "Custom",
					"weekLabel": "W",
					"daysOfWeek": [
						"Dom",
						"Seg",
						"Ter",
						"Qua",
						"Qui",
						"Sex",
						"Sab"
					],
					"monthNames": [
						"Janeiro",
						"Fevereiro",
						"Março",
						"Abril",
						"Maio",
						"Junho",
						"Julho",
						"Agosto",
						"Setembro",
						"Outubro",
						"Novembro",
						"Dezembro"
					],
					"firstDay": 1
				},
			}, function(start, end, label) {
				// console.log("A new date selection was made: " + start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD'));
			});
		});
	</script>
<?
}

function cadastra_ajuste() {

	if ($_POST[hora] == '') {
		set_status("ERRO: Dados insuficientes!");
		layout_ajuste();
		exit;
	}

	$aMotorista = carregar('entidade', $_POST[id]);

	$queryMacroPonto = query("SELECT macr_tx_codigoInterno, macr_tx_codigoExterno FROM macroponto WHERE macr_nb_id = '" . $_POST[idMacro] . "'");
	$aTipo = carrega_array($queryMacroPonto);

	$campos = array(pont_nb_user, pont_tx_matricula, pont_tx_data, pont_tx_tipo, pont_tx_tipoOriginal, pont_tx_status, pont_tx_dataCadastro, pont_nb_motivo, pont_tx_descricao);
	$valores = array($_SESSION[user_nb_id], $aMotorista['enti_tx_matricula'], "$_POST[data] $_POST[hora]", $aTipo[0], $aTipo[1], 'ativo', date("Y-m-d H:i:s"), $_POST[motivo], $_POST[descricao]);
	inserir('ponto', $campos, $valores);


	layout_ajuste();
	exit;
}

function excluir_ponto() {
	$a = carregar('ponto', (int)$_POST[id]);
	remover('ponto', (int)$_POST[id]);

	$_POST[id] = $_POST[idEntidade];
	$_POST[data] = substr($a[pont_tx_data], 0, -9);
	$_POST[busca_data] = $a[pont_tx_data];


	layout_ajuste();
	exit;
}

function layout_ajuste() {
	global $a_mod;

	cabecalho('Espelho de Ponto');

	if ($_POST[busca_data1] == '' && $_POST[busca_data])
		$_POST[busca_data1] = $_POST[busca_data];
	if ($_POST[busca_data2] == '' && $_POST[busca_data])
		$_POST[busca_data2] = $_POST[busca_data];

	$aMotorista = carregar('entidade', $_POST[id]);

	$sqlCheck = query("SELECT user_tx_login, endo_tx_dataCadastro FROM endosso, user WHERE endo_tx_mes = '" . substr($_POST[data], 0, 7) . '-01' . "' AND endo_nb_entidade = '" . $aMotorista['enti_nb_id'] . "'
			AND endo_tx_matricula = '" . $aMotorista['enti_tx_matricula'] . "' AND endo_tx_status = 'ativo' AND endo_nb_userCadastro = user_nb_id LIMIT 1");
	$aEndosso = carrega_array($sqlCheck);

	$extra = " AND pont_tx_data LIKE '" . $_POST[data] . " %' AND pont_tx_matricula = '$aMotorista[enti_tx_matricula]'";

	$c[] = texto('Matrícula', $aMotorista[enti_tx_matricula], 2);
	$c[] = texto('Motorista', $aMotorista[enti_tx_nome], 5);
	$c[] = texto('CPF', $aMotorista[enti_tx_cpf], 3);

	$c2[] = campo('Data', 'data', data($_POST[data]), 2, '', 'readonly=readonly');
	$c2[] = campo_hora('Hora', 'hora', $a_mod[macr_tx_codigoExterno], 2);
	$c2[] = combo_bd('Código Macro', 'idMacro', '', 4, 'macroponto', '', 'ORDER BY macr_nb_id ASC');
	$c2[] = combo_bd('Motivo:', 'motivo', '', 4, 'motivo', '', ' AND moti_tx_tipo = "Ajuste"');

	$c3[] = textarea('Justificativa:', 'descricao', '', 12);

	if (count($aEndosso) == 0) {
		$botao[] = botao('Gravar', 'cadastra_ajuste', 'id,busca_motorista,busca_data1,busca_data2,data,busca_data', "$_POST[id],$_POST[id],$_POST[busca_data1],$_POST[busca_data2],$_POST[data]," . substr($_POST[data], 0, -3));
		$iconeExcluir = 'icone_excluir(pont_nb_id,excluir_ponto,idEntidade,' . $_POST[id] . ')';
	} else {
		$c2[] = texto('Endosso:', "Endossado por " . $aEndosso['user_tx_login'] . " em " . data($aEndosso['endo_tx_dataCadastro'], 1), 6);
		$iconeExcluir = '';
	}

	$botao[] = botao('Voltar', 'index', 'busca_data1,busca_data2,id,busca_empresa,busca_motorista,data,busca_data', "$_POST[busca_data1],$_POST[busca_data2],$_POST[id],$aMotorista[enti_nb_empresa],$_POST[id],$_POST[data]," . substr($_POST[data], 0, -3));

	abre_form('Dados do Ajuste de Ponto');
	linha_form($c);
	linha_form($c2);
	linha_form($c3);
	fecha_form($botao);

	$sql = "SELECT *
		FROM ponto
		JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
		JOIN user ON ponto.pont_nb_user = user.user_nb_id
		LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
		WHERE ponto.pont_tx_status != 'inativo' 
		$extra";


	$cab = array('CÓD', 'DATA', 'HORA', 'TIPO', 'MOTIVO', 'JUSTIFICATIVA', 'USUÁRIO', 'DATA CADASTRO', '');

	// $ver2 = "icone_modificar(arqu_nb_id,layout_confirma)";
	$val = array('pont_nb_id', 'data(pont_tx_data)', 'data(pont_tx_data,3)', 'macr_tx_nome', 'moti_tx_nome', 'pont_tx_descricao', 'user_tx_login', 'data(pont_tx_dataCadastro,1)', $iconeExcluir);
	grid($sql, $cab, $val, '', '', 2, 'ASC', -1);

	rodape();
}



function maxDirecaoContinua($dados) {
	$mdc = 0; // duração máxima contínua
	$jornada_inicio = null; // hora do início da jornada
	$ultima_batida = null; // hora da última batida

	for ($i = 0; $i < count($dados) - 1; $i++) {
		$atual = $dados[$i];
		$proximo = $dados[$i + 1];

		// Ignora as subtrações dos tipos 3-4, 5-6, 7-8, 9-10, 11-12 e 2-1
		if (($atual[1] == 3 && $proximo[1] == 4) ||
			($atual[1] == 5 && $proximo[1] == 6) ||
			($atual[1] == 7 && $proximo[1] == 8) ||
			($atual[1] == 9 && $proximo[1] == 10) ||
			($atual[1] == 11 && $proximo[1] == 12) ||
			($atual[1] == 2 && $proximo[1] == 1)
		) {
			continue;
		}

		$horario_atual = strtotime($atual[0]);
		$horario_proximo = strtotime($proximo[0]);
		$duracao = $horario_proximo - $horario_atual;

		if ($jornada_inicio === null) {
			$jornada_inicio = $horario_atual;
			$mdc = $duracao;
		} else {
			if ($duracao > $mdc) {
				$mdc = $duracao;
				$ultima_batida = $proximo[0];
			}
		}
	}

	return $mdc > 0 ? gmdate('H:i', $mdc) : '0';
}



function somarHorarios($horarios): string {
	$totalSegundos = 0;

	foreach ($horarios as $horario) {
		list($horas, $minutos, $segundos) = explode(':', $horario);

		if (substr($horas, 0, 1) == '-') {
			$horas = abs((int) $horas); // Converte as horas para valor absoluto
			$minutos = abs((int) $minutos); // Converte os minutos para valor absoluto
			$segundos = abs((int) $segundos); // Converte os segundos para valor absoluto
			$totalSegundos -= ($horas * 3600 + $minutos * 60 + $segundos);
		} else {
			$totalSegundos += ($horas * 3600 + $minutos * 60 + $segundos);
		}
	}

	$horas = floor(abs($totalSegundos) / 3600);
	$minutos = floor((abs($totalSegundos) % 3600) / 60);
	$segundos = abs($totalSegundos) % 60;

	// Verifica se o resultado é negativo e formata o retorno de acordo
	if ($totalSegundos < 0) {
		return sprintf('-%02d:%02d', $horas, $minutos);
	} else {
		return sprintf('%02d:%02d', $horas, $minutos);
	}
}

function verificaTempoMdc($tempo1 = '00:00', $tempoDescanso = '00:00') {
	// Verifica se os parâmetros são strings e possuem o formato correto
	if (!is_string($tempo1) || !is_string($tempoDescanso) || !preg_match('/^\d{2}:\d{2}$/', $tempo1) || !preg_match('/^\d{2}:\d{2}$/', $tempoDescanso)) {
		return '';
	}
	$datetime1 = new DateTime('2000-01-01 ' . $tempo1);
	$datetime2 = new DateTime('2000-01-01 ' . '05:30');
	$datetime3 = new DateTime('2000-01-01 ' . '03:00');
	$datetimeDescanso = new DateTime('2000-01-01 ' . $tempoDescanso);
	$datetimeDescanso1 = new DateTime('2000-01-01 ' . '00:30');
	$datetimeDescanso2 = new DateTime('2000-01-01 ' . '00:15');

	$alertaDescanso = '';

	if ($datetime1 > $datetime2) {
		if ($datetimeDescanso < $datetimeDescanso1) {
			$alertaDescanso = "<i style='color:orange;' title='Descanso de 00:30 não respeitado' class='fa fa-warning'></i>";
		}
		return "<a style='white-space: nowrap;'>$alertaDescanso<i style='color:orange;' title='Tempo excedido de 05:30' class='fa fa-warning'></i></a>&nbsp;" . $tempo1;
		// return "<a style='white-space: nowrap;'>$alertaDescanso</a>&nbsp;".$tempo1;
	} elseif ($datetime1 > $datetime3) {
		if ($datetimeDescanso < $datetimeDescanso2) {
			$alertaDescanso = "<i style='color:orange;' title='Descanso de 00:15 não respeitado' class='fa fa-warning'></i>";
		}
		// return "<a style='white-space: nowrap;'>$alertaDescanso<i style='color:orange;' title='Tempo excedido de 03:00' class='fa fa-warning'></i></a>&nbsp;".$tempo1;
		return "<a style='white-space: nowrap;'>$alertaDescanso</a>&nbsp;" . $tempo1;
	} else {
		return $tempo1;
	}
}

function verificaTempo($tempo1 = '00:00', $tempo2) {
	// Verifica se os parâmetros são strings e possuem o formato correto
	if (!is_string($tempo1) || !is_string($tempo2) || !preg_match('/^\d{2}:\d{2}$/', $tempo1) || !preg_match('/^\d{2}:\d{2}$/', $tempo2)) {
		return '';
	}
	$datetime1 = new DateTime('2000-01-01 ' . $tempo1);
	$datetime2 = new DateTime('2000-01-01 ' . $tempo2);

	if ($datetime1 > $datetime2) {
		return "<a style='white-space: nowrap;'><i style='color:orange;' title='Tempo excedido de $tempo2' class='fa fa-warning'></i></a>&nbsp;" . $tempo1;
	} else {
		return $tempo1;
	}
}

function verificaTolerancia($saldoDiario, $data, $motorista) {
	date_default_timezone_set('America/Sao_Paulo');
	$tolerancia = '00:10';

	$datetimeSaldo = new DateTime('2000-01-01 ' . $saldoDiario);
	$datetimeTolerancia = new DateTime('2000-01-01 ' . $tolerancia);
	$interval = $datetimeSaldo->diff($datetimeTolerancia);
	$horas = $interval->format('%H');
	$minutos = $interval->format('%I');
	$totalMinutos = $horas * 60 + $minutos;
	$toleranciaMinutos = intval(substr($tolerancia, 0, 2)) * 60 + intval(substr($tolerancia, 3, 2));

	if ($saldoDiario[0] == '-') {
		$saldoEmMinutos = intval(substr($saldoDiario, 1, 2)) * 60 + intval(substr($saldoDiario, 4, 2));
		if ($totalMinutos <= $toleranciaMinutos || abs($saldoEmMinutos) <= $toleranciaMinutos) {
			$cor = 'blue';
		} else {
			$cor = 'red';
		}
	} else {
		if ($saldoDiario == '00:00') {
			$cor = 'blue';
		} else {
			$cor = 'green';
		}
	}

	// echo "$data: $saldoDiario - $tolerancia -> $cor <br>";

	if ($_SESSION[user_tx_nivel] == 'Motorista') {
		$retorno = '<center><span><i style="color:' . $cor . ';" class="fa fa-circle"></i></span></center>';
	} else {
		$retorno = '<center><a title="Ajuste de Ponto" href="#" onclick="ajusta_ponto(\'' . $data . '\', \'' . $motorista . '\')"><i style="color:' . $cor . ';" class="fa fa-circle"></i></a></center>';
	}


	return $retorno;
}






function calcular_maximo_direcao($inicio_jornada, $fim_jornada, $pausas) {
	if ($inicio_jornada == '' && $fim_jornada == '') {
		return '';
	}

	if ($inicio_jornada == '') {
		$inicio_jornada = '00:00';
	}

	if ($fim_jornada == '') {
		$fim_jornada = '00:00';
	}

	$maximo_direcao = 0;
	$ultimo_fim = DateTime::createFromFormat("H:i", $inicio_jornada);

	foreach ($pausas as $pausa) {
		$inicio_pausa = DateTime::createFromFormat("H:i", $pausa["inicio"]);
		$fim_pausa = DateTime::createFromFormat("H:i", $pausa["fim"]);

		$duracao_intervalo = $ultimo_fim->diff($inicio_pausa)->format("%H:%I");
		if ($duracao_intervalo > $maximo_direcao) {
			$maximo_direcao = $duracao_intervalo;
		}

		$ultimo_fim = $fim_pausa;
	}

	$duracao_jornada = $ultimo_fim->diff(DateTime::createFromFormat("H:i", $fim_jornada))->format("%H:%I");
	if ($duracao_jornada > $maximo_direcao) {
		$maximo_direcao = $duracao_jornada;
	}

	return $maximo_direcao;
}



function ordena_horarios($inicio, $fim) {
	// Inicializa o array resultante e o array de indicação
	$horarios = array();
	$origem = array();

	// Adiciona os horários do array de início e marca a origem como "inicio"
	foreach ($inicio as $h) {
		$horarios[] = $h;
		$origem[] = "Inicio:";
	}

	// Adiciona os horários do array de fim e marca a origem como "fim"
	foreach ($fim as $h) {
		$horarios[] = $h;
		$origem[] = "Fim:";
	}

	// Ordena o array de horários
	array_multisort($horarios, SORT_ASC, $origem);

	// Cria um array associativo para cada horário com sua origem correspondente
	$horarios_com_origem = array();
	$alertaTooltip = '';

	for ($i = 0; $i < count($horarios); $i++) {
		// $horarios_com_origem[] = array(
		//     "horario" => $horarios[$i],
		//     "origem" => $origem[$i]
		// );

		$alertaTooltip .= "$origem[$i] " . data($horarios[$i], 1) . "\n";
	}

	if (count($inicio) != count($fim)) {
		$iconeAlerta =  "<a><i style='color:red;' title='$alertaTooltip' class='fa fa-info-circle'></i></a>";
	} else {
		$iconeAlerta =  "<a><i style='color:orange;' title='$alertaTooltip' class='fa fa-info-circle'></i></a>";
	}



	// Retorna o array de horários com suas respectivas origens
	return $iconeAlerta;
}


function ordena_horarios_pares($inicio, $fim, $ehEspera = 0) {
	// Inicializa o array resultante e o array de indicação
	$horarios = array();
	$origem = array();
	$pares_horarios = array();

	// Adiciona os horários do array de início e marca a origem como "inicio"
	foreach ($inicio as $h) {
		$horarios[] = $h;
		$origem[] = "inicio";
	}

	// Adiciona os horários do array de fim e marca a origem como "fim"
	foreach ($fim as $h) {
		$horarios[] = $h;
		$origem[] = "fim";
	}

	// Ordena o array de horários
	array_multisort($horarios, SORT_ASC, $origem, SORT_DESC);

	// Cria um array associativo para cada horário com sua origem correspondente
	$horarios_com_origem = array();
	$alertaTooltip = '';

	for ($i = 0; $i < count($horarios); $i++) {
		$horarios_com_origem[] = array(
			"horario" => $horarios[$i],
			"origem" => $origem[$i]
		);

		$alertaTooltip .= ucfirst($origem[$i]) . " " . data($horarios[$i], 1) . "\n";
	}


	$dtInicioAdicionalNot = new DateTime(substr($horarios[0], 0, 10) . ' 05:00');
	$dtFimAdicionalNot = new DateTime(substr($horarios[0], 0, 10) . ' 22:00');
	$totalIntervalo = new DateTime(substr($horarios[0], 0, 10) . ' 00:00');
	$totalIntervaloAdicionalNot = new DateTime(substr($horarios[0], 0, 10) . ' 00:00');
	$inicio_atual = null;
	$pares = array();
	$paresParaRepouso = array();

	foreach ($horarios_com_origem as $item) {
		if ($item["origem"] == "inicio") {
			$inicio_atual = $item["horario"];
		} elseif ($item["origem"] == "fim" && $inicio_atual != null) {
			$hInicio = new DateTime($inicio_atual);
			$hFim = new DateTime($item["horario"]);

			$interval = $hInicio->diff($hFim);

			// se intervalo > 2 horas && ehEspera true
			if ($ehEspera == 1 && (($interval->h * 60) + $interval->i > 120)) {
				$paresParaRepouso[] = array("inicio" => date("H:i", strtotime($inicio_atual)), "fim" => date("H:i", strtotime($item["horario"])));
			} else {

				$totalIntervalo->add($interval);

				$interval = $interval->format("%H:%I");

				$pares[] = array("inicio" => date("H:i", strtotime($inicio_atual)), "fim" => date("H:i", strtotime($item["horario"])), "intervalo" => $interval);
			}

			// VERIFICA HORA SE HÁ HORA EXTRA ACIMA DAS 22:00
			if ($hFim > $dtFimAdicionalNot) {
				$fimExtra = date("H:i", strtotime($item["horario"]));
				if ($hInicio > $dtFimAdicionalNot) {
					$hInicioAdicionalNot = $hInicio; //CRIA UMA NOVA VARIAVEL PARA NO CASO DAS 05:00
					$incioExtra = date("H:i", strtotime($inicio_atual));
				} else {
					$hInicioAdicionalNot = new DateTime(substr($horarios[0], 0, 10) . ' 22:00');
					$incioExtra = '22:00';
				}

				$intervalAdicionalNot = $hInicioAdicionalNot->diff($hFim);
				$totalIntervaloAdicionalNot->add($intervalAdicionalNot);

				$intervalAdicionalNot = $intervalAdicionalNot->format("%H:%I");


				$paresAdicionalNot[] = array("inicio" => $incioExtra, "fim" => $fimExtra, "intervalo" => $intervalAdicionalNot);
			}

			// VERIFICA HORA SE HÁ HORA EXTRA ABAIXO DAS 05:00
			if ($hInicio < $dtInicioAdicionalNot) {
				$incioExtra = date("H:i", strtotime($inicio_atual));
				if ($hFim > $dtInicioAdicionalNot) {
					$hFim = new DateTime(substr($horarios[0], 0, 10) . ' 05:00');
					$fimExtra = '05:00';
				} else {
					$fimExtra = date("H:i", strtotime($item["horario"]));
				}

				$intervalAdicionalNot = $hInicio->diff($hFim);
				$totalIntervaloAdicionalNot->add($intervalAdicionalNot);

				$intervalAdicionalNot = $intervalAdicionalNot->format("%H:%I");;


				$paresAdicionalNot[] = array("inicio" => $incioExtra, "fim" => $fimExtra, "intervalo" => $intervalAdicionalNot);
			}

			$inicio_atual = null;
		} elseif ($item["origem"] == "fim" && $inicio_atual == null) {
			// Se encontrarmos um fim sem um início correspondente, armazenamos o horário sem par
			$sem_fim[] = $item["horario"];
		}
	}

	if (count($inicio) == 0 && count($fim) == 0) {
		$iconeAlerta = '';
	} elseif (count($inicio) != count($fim) || count($horarios_com_origem) / 2 != (count($pares) + count($paresParaRepouso))) {
		$iconeAlerta = "<a><i style='color:red;' title='$alertaTooltip' class='fa fa-info-circle'></i></a>";
	} else {
		$iconeAlerta = "<a><i style='color:green;' title='$alertaTooltip' class='fa fa-info-circle'></i></a>";
	}




	$pares_horarios['horariosOrdenados'] = $horarios_com_origem;
	$pares_horarios['pares'] = $pares;
	$pares_horarios['totalIntervalo'] = $totalIntervalo->format('H:i');
	$pares_horarios['icone'] = $iconeAlerta;

	if (count($horarios_com_origem) > 2) {
		$totalInterjornada = new DateTime(substr($horarios[0], 0, 10) . ' 00:00');
		for ($i = 0; $i < count($horarios_com_origem); $i++) {
			$horarioVez = $horarios_com_origem[$i];
			$horarioAnterior = $horarios_com_origem[($i - 1)];
			if ($horarioVez['origem'] == 'inicio' && $horarioAnterior['origem'] == 'fim') {
				$dtInicio = new DateTime($horarioVez['horario']);
				$dtFim = new DateTime($horarioAnterior['horario']);

				$intervalInterjornada = $dtFim->diff($dtInicio);

				$totalInterjornada->add($intervalInterjornada);
			}
		}

		$pares_horarios['interjornada'] = $totalInterjornada->format('H:i');
	}

	if (count($paresParaRepouso) > 0) {
		$pares_horarios['paresParaRepouso'] = $paresParaRepouso;
	}
	if (count($paresAdicionalNot) > 0) {
		$pares_horarios['paresAdicionalNot'] = $paresAdicionalNot;
		$pares_horarios['totalIntervaloAdicionalNot'] = $totalIntervaloAdicionalNot->format('H:i');
	}



	// echo "<pre>";
	// print_r($pares_horarios);exit;

	// Retorna o array de horários com suas respectivas origens
	return $pares_horarios;
}

function diaDetalhePonto($matricula, $data) {
	global $totalResumo, $contagemEspera;
	setlocale(LC_ALL, 'pt_BR.utf8');

	$aRetorno['data'] = data($data);
	$aRetorno['diaSemana'] = mb_strtoupper(strftime('%a', strtotime($data)));
	$aRetorno['inicioJornada'] = '';
	$aRetorno['inicioRefeicao'] = '';
	$aRetorno['fimRefeicao'] = '';
	$aRetorno['fimJornada'] = '';
	$aRetorno['diffRefeicao'] = '';
	$aRetorno['diffEspera'] = '';
	$aRetorno['diffDescanso'] = '';
	$aRetorno['diffRepouso'] = '';
	$aRetorno['diffJornada'] = '';
	$aRetorno['jornadaPrevista'] = '';
	$aRetorno['diffJornadaEfetiva'] = '';
	$aRetorno['maximoDirecaoContinua'] = '';
	$aRetorno['intersticio'] = '';
	$aRetorno['he50'] = '';
	$aRetorno['he100'] = '';
	$aRetorno['adicionalNoturno'] = '';
	$aRetorno['esperaIndenizada'] = '';
	$aRetorno['diffSaldo'] = '';

	$sqlMotorista = query("SELECT * FROM entidade WHERE enti_tx_status != 'inativo' AND enti_tx_matricula = '$matricula' LIMIT 1");
	$aMotorista = carrega_array($sqlMotorista);

	$aEmpresa = carregar('empresa', $aMotorista[enti_nb_empresa]);
	$aEmpCidade = carregar('cidade', $aEmpresa[empr_nb_cidade]);
	if ($aMotorista[enti_nb_empresa] && $aEmpresa[empr_nb_cidade] && $aEmpCidade[cida_nb_id] && $aEmpCidade[cida_tx_uf]) {
		$extraFeriado = " AND (feri_nb_cidade = '$aEmpCidade[cida_nb_id]' OR feri_tx_uf = '$aEmpCidade[cida_tx_uf]')";
	}

	$aParametro = carregar('parametro', $aMotorista[enti_nb_parametro]);
	if ($aParametro[para_tx_acordo] == 'Sim') {
		$horaAlertaJornadaEfetiva = '12:00';
	} else {
		$horaAlertaJornadaEfetiva = '10:00';
	}

	$sqlFeriado = "SELECT feri_tx_nome FROM feriado WHERE feri_tx_data LIKE '____-" . substr($data, 5, 5) . "%' AND feri_tx_status != 'inativo' $extraFeriado";
	$queryFeriado = query($sqlFeriado);
	$stringFeriado = '';
	while ($row = carrega_array($queryFeriado)) {
		$stringFeriado .= $row[0] . "\n";
	}

	if (strftime('%w', strtotime($data)) == '6') { //SABADOS
		// $horas_sabado = $aMotorista[enti_tx_jornadaSabado];
		// $cargaHoraria = sprintf("%02d:%02d", floor($horas_sabado), ($horas_sabado - floor($horas_sabado)) * 60);
		$cargaHoraria = $aMotorista[enti_tx_jornadaSabado];
	} elseif (strftime('%w', strtotime($data)) == '0') { //DOMINGOS
		$cargaHoraria = '00:00';
	} else {
		// $horas_diarias = $aMotorista[enti_tx_jornadaSemanal]/5;
		// $cargaHoraria = sprintf("%02d:%02d", floor($horas_diarias), ($horas_diarias - floor($horas_diarias)) * 60);
		$cargaHoraria = $aMotorista[enti_tx_jornadaSemanal];
	}

	if ($stringFeriado != '') {
		$cargaHoraria = '00:00';
	}


	// if($cargaHoraria == 0){
	// 	return $aRetorno;
	// 	exit;
	// }

	$sql = query("SELECT * FROM ponto WHERE pont_tx_status != 'inativo' AND pont_tx_matricula = '$matricula'  AND pont_tx_data LIKE '$data%' ORDER BY pont_tx_data ASC");
	while ($aDia = carrega_array($sql)) {

		// $queryMacroPonto = query("SELECT macr_tx_nome,macr_tx_codigoInterno FROM macroponto WHERE macr_tx_codigoExterno = '".$aDia[pont_tx_tipo]."'");
		// $aTipo = carrega_array($queryMacroPonto);

		$arrayMDC[] = [date("H:i", strtotime($aDia[pont_tx_data])), $aDia[pont_tx_tipo]];

		if ($aDia[pont_tx_tipo] == '1') {
			$arrayInicioJornada[] = date("H:i", strtotime($aDia[pont_tx_data]));
			$arrayInicioJornada2[] = $aDia[pont_tx_data];
			// $aRetorno['inicioJornada'] = date("H:i",strtotime($aDia[pont_tx_data]));
			$inicioDataAdicional = $aDia[pont_tx_data];
		}

		if ($aDia[pont_tx_tipo] == '2') {
			$arrayFimJornada[] = date("H:i", strtotime($aDia[pont_tx_data]));
			$arrayFimJornada2[] = $aDia[pont_tx_data];
			// $aRetorno['fimJornada'] = date("H:i",strtotime($aDia[pont_tx_data]));
			$fimDataAdicional = $aDia[pont_tx_data];
		}

		if ($aDia[pont_tx_tipo] == '3') {
			$arrayInicioRefeicaoFormatado[] = date("H:i", strtotime($aDia[pont_tx_data]));
			$arrayInicioRefeicao[] = $aDia[pont_tx_data];
		}

		if ($aDia[pont_tx_tipo] == '4') {
			$arrayFimRefeicaoFormatado[] = date("H:i", strtotime($aDia[pont_tx_data]));
			$arrayFimRefeicao[] = $aDia[pont_tx_data];
		}

		if ($aDia[pont_tx_tipo] == '5') {
			$aDataHorainicioEspera[] = $aDia[pont_tx_data];
		}

		if ($aDia[pont_tx_tipo] == '6') {
			$aDataHorafimEspera[] = $aDia[pont_tx_data];
		}

		if ($aDia[pont_tx_tipo] == '7') {
			$aDataHorainicioDescanso[] = $aDia[pont_tx_data];
		}

		if ($aDia[pont_tx_tipo] == '8') {
			$aDataHorafimDescanso[] = $aDia[pont_tx_data];
		}

		if ($aDia[pont_tx_tipo] == '9') {
			$aDataHorainicioRepouso[] = $aDia[pont_tx_data];
		}

		if ($aDia[pont_tx_tipo] == '10') {
			$aDataHorafimRepouso[] = $aDia[pont_tx_data];
		}

		if ($aDia[pont_tx_tipo] == '11') {
			// $dataHoraRepousoEmbarcado = date("H:i",strtotime($aDia[pont_tx_data]));
			$aDataHorainicioRepouso[] = $aDia[pont_tx_data]; // ADICIONADO AO REPOUSO
		}

		if ($aDia[pont_tx_tipo] == '12') {
			// $dataHorafimRepousoEmbarcado = date("H:i",strtotime($aDia[pont_tx_data]));
			$aDataHorafimRepouso[] = $aDia[pont_tx_data]; // ADICIONADO AO REPOUSO
		}

		// echo "<hr>";
		// echo "$aTipo[macr_tx_nome] - $aTipo[macr_tx_codigoInterno] -> ";
		// echo date("Y-m-d",strtotime($aDia['pont_tx_data']));
		// echo " - ";
		// // print_r($aTipo);
		// echo "<hr>";


		// if($dataHorainicioDescanso!= '' && $dataHorafimDescanso != ''){
		// 	// echo "<hr>$data - $dataHorainicioDescanso --- $dataHorafimDescanso";
		// 	$dateInicioDescanso = new DateTime($dataHorainicioDescanso);
		// 	$dateFimDescanso = new DateTime($dataHorafimDescanso);
		// 	$diffDescanso = $dateInicioDescanso->diff($dateFimDescanso);
		// 	$aDescanso[] = $diffDescanso->format("%H:%I");

		// 	unset($dataHorainicioDescanso, $dataHorafimDescanso);

		// }

		// if($dataHorainicioEspera!= '' && $dataHorafimEspera != ''){
		// 	// echo "<hr>$data - $dataHorainicioEspera --- $dataHorafimEspera";
		// 	$dateInicioEspera = new DateTime($dataHorainicioEspera);
		// 	$dateFimEspera = new DateTime($dataHorafimEspera);
		// 	$diffEspera = $dateInicioEspera->diff($dateFimEspera);
		// 	$aEspera[] = $diffEspera->format("%H:%I");

		// 	unset($dataHorainicioEspera, $dataHorafimEspera);

		// }

	}

	$jornadaOrdenado = ordena_horarios_pares($arrayInicioJornada2, $arrayFimJornada2);
	$refeicaoOrdenada = ordena_horarios_pares($arrayInicioRefeicao, $arrayFimRefeicao);
	$esperaOrdenada = ordena_horarios_pares($aDataHorainicioEspera, $aDataHorafimEspera, 1);
	$descansoOrdenado = ordena_horarios_pares($aDataHorainicioDescanso, $aDataHorafimDescanso);

	if ($esperaOrdenada['paresParaRepouso']) {
		for ($i = 0; $i < count($esperaOrdenada['paresParaRepouso']); $i++) {
			$aDataHorainicioRepouso[] = $data . ' ' . $esperaOrdenada['paresParaRepouso'][$i]['inicio'];
			$aDataHorafimRepouso[] = $data . ' ' . $esperaOrdenada['paresParaRepouso'][$i]['fim'];
			$aDataHorainicioEsperaParaRepouso[] = $data . ' ' . $esperaOrdenada['paresParaRepouso'][$i]['inicio'];
			$aDataHorafimEsperaParaRepouso[] = $data . ' ' . $esperaOrdenada['paresParaRepouso'][$i]['fim'];
		}
		$esperaParaRepousoOrdenado = ordena_horarios_pares($aDataHorainicioEsperaParaRepouso, $aDataHorafimEsperaParaRepouso);
	}
	$repousoOrdenado = ordena_horarios_pares($aDataHorainicioRepouso, $aDataHorafimRepouso);

	$aRetorno['diffRefeicao'] = $refeicaoOrdenada['icone'] . $refeicaoOrdenada['totalIntervalo'];
	$aRetorno['diffEspera'] = $esperaOrdenada['icone'] . $esperaOrdenada['totalIntervalo'];
	$aRetorno['diffDescanso'] = $descansoOrdenado['icone'] . $descansoOrdenado['totalIntervalo'];
	$aRetorno['diffRepouso'] = $repousoOrdenado['icone'] . $repousoOrdenado['totalIntervalo'];

	// echo "$data <br>";
	$contagemEspera += count($esperaOrdenada['pares']);

	// echo "<pre>";
	// print_r($jornadaOrdenado);
	// echo "<pre>";
	// print_r($descansoOrdenado);
	// echo "</pre>";
	// echo "<hr>";

	$sqlAbono = query("SELECT * FROM abono, motivo, user 
		WHERE abon_tx_status != 'inativo' AND abon_nb_userCadastro = user_nb_id 
		AND abon_tx_matricula = '$matricula' AND abon_tx_data = '$data' AND abon_nb_motivo = moti_nb_id
		ORDER BY abon_nb_id DESC LIMIT 1");
	$aAbono = carrega_array($sqlAbono);
	if ($aAbono[0] > 0) {
		$tooltip = "Jornada Original: " . str_pad($cargaHoraria, 2, '0', STR_PAD_LEFT) . ":00:00" . "\n";
		$tooltip .= "Abono: $aAbono[abon_tx_abono]\n";
		$tooltip .= "Motivo: $aAbono[moti_tx_nome]\n";
		$tooltip .= "Justificativa: $aAbono[abon_tx_descricao]\n\n";
		$tooltip .= "Registro efetuado por $aAbono[user_tx_login] em " . data($aAbono[abon_tx_dataCadastro], 1);

		$iconeAbono =  "<a><i style='color:orange;' title='$tooltip' class='fa fa-warning'></i></a>";
	} else {
		$tooltip = '';
		$iconeAbono = '';
	}


	// INICIO CALCULO JORNADA TRABALHO (DESCONSIDEREANDO PAUSAS)
	for ($i = 0; $i < count($arrayInicioJornada); $i++) {
		$dataHoraInicioJornada = new DateTime($data . " " . $arrayInicioJornada[$i]);
		$dataHoraFimJornada = new DateTime($data . " " . $arrayFimJornada[$i]);


		$diffJornada = $dataHoraInicioJornada->diff($dataHoraFimJornada);
		$arrayDiffJornada[] = $diffJornada->format("%H:%I");
	}


	$aRetorno['diffJornada'] = $jornadaOrdenado['icone'] . $jornadaOrdenado['totalIntervalo'];
	// FIM CALCULO CALCULO JORNADA TRABALHO


	// INICIO JORNADA ESPERADA
	$dataJornadaPrevista = new DateTime($data . " " . $cargaHoraria);
	$dataAbono = new DateTime($data . " " . $aAbono['abon_tx_abono']);
	$diffJornadaPrevista = $dataAbono->diff($dataJornadaPrevista);

	$aRetorno['jornadaPrevista'] = $diffJornadaPrevista->format("%H:%I");
	// $aRetorno['jornadaPrevista'] = '08:00';
	// FIM JORNADA ESPERADA

	// echo "<br>|$data | $aRetorno[inicioJornada] | $aRetorno[fimJornada] | $aRetorno[diffJornada]|$aRetorno[diffRefeicao]";
	// exit;
	// INICIO CALCULO JORNADA EFETIVAMENTE DO DIA
	$horaTotal = new DateTime($jornadaOrdenado['totalIntervalo']);

	//SOMATORIO DE TODAS AS ESPERAS
	$horaTotalIntervalos = new DateTime(somarHorarios(array($refeicaoOrdenada['totalIntervalo'], $esperaOrdenada['totalIntervalo'], $descansoOrdenado['totalIntervalo'], $repousoOrdenado['totalIntervalo'])));

	$diffJornadaEfetiva = $horaTotal->diff($horaTotalIntervalos);
	$aRetorno['diffJornadaEfetiva'] = verificaTempo($diffJornadaEfetiva->format("%H:%I"), $horaAlertaJornadaEfetiva);
	// FIM CALCULO JORNADA EFETIVAMENTE DO DIA

	// INICIO CALCULO INTERSTICIO
	if ($inicioDataAdicional != '') {

		$sqlDiaAnterior = query("SELECT pont_tx_data FROM ponto WHERE pont_tx_status != 'inativo' AND pont_tx_matricula = '$matricula'  AND pont_tx_data < '$data' ORDER BY pont_tx_data DESC LIMIT 1");
		$aDiaAnterior = carrega_array($sqlDiaAnterior);

		$dateInicioJornada = new DateTime($arrayInicioJornada2[0]);
		$dateDiaAnterior = new DateTime($aDiaAnterior[0]);


		$diffJornada = $dateInicioJornada->diff($dateDiaAnterior);
		// $diffIntersticio = $diffJornada->format("%H:%I");

		// Obter a diferença total em minutos
		$diffMinutos = ($diffJornada->days * 24 * 60) + ($diffJornada->h * 60) + $diffJornada->i;

		// Calcular as horas e minutos
		$horas = floor($diffMinutos / 60);
		$minutos = $diffMinutos % 60;

		// Formatar a string no formato HH:MM
		$hInter = sprintf("%02d:%02d", $horas, $minutos);

		$diffIntersticio = somarHorarios(array($hInter, $horaTotalIntervalos->format("H:i"), $jornadaOrdenado['interjornada']));


		list($hours, $minutes) = explode(':', $diffIntersticio);
		$tsInterTotal = $hours * 3600 + $minutes * 60;

		list($hours, $minutes) = explode(':', $hInter);
		$hInter = $hours * 3600 + $minutes * 60;

		$diferenca11h = (11 * 3600);
		$diferenca8h = (8 * 3600);

		$iconeInter = '';

		if ($tsInterTotal < $diferenca11h) {
			$iconeInter .= "<a><i style='color:red;' title='Interstício Total de 11:00 não respeitado' class='fa fa-warning'></i></a>";
		}

		if ($hInter < $diferenca8h) {
			$iconeInter .= "<a><i style='color:red;' title='O mínimo de 08:00h ininterruptas no primeiro período, não respeitado.' class='fa fa-warning'></i></a>";
		}


		$aRetorno['intersticio'] = $iconeInter . $diffIntersticio;
	}

	// FIM CALCULO INTERSTICIO





	//CALCULO ESPERA INDENIZADA
	$horaJornadaEsperada = DateTime::createFromFormat('H:i', $aRetorno['jornadaPrevista']);
	$horario1 = DateTime::createFromFormat('H:i', ($diffJornadaEfetiva->format("%H:%I")));
	$horario2 = DateTime::createFromFormat('H:i', somarHorarios(array($esperaOrdenada['totalIntervalo'], $esperaParaRepousoOrdenado['totalIntervalo'])));

	$esperaIndenizada = clone $horario1;
	$esperaIndenizada->add(new DateInterval('PT' . $horario2->format('H') . 'H' . $horario2->format('i') . 'M'));

	// $jornadaEfetiva = $esperaIndenizada->format('H:i');
	$jornadaEfetiva = $diffJornadaEfetiva->format("%H:%I");

	$dateCargaHoraria = new DateTime($aRetorno['jornadaPrevista']);
	$dateJornadaEfetiva = new DateTime($jornadaEfetiva);
	$saldoPositivo = $dateCargaHoraria->diff($dateJornadaEfetiva)->format("%r");

	if ($esperaIndenizada > $horaJornadaEsperada && $horario1 < $horaJornadaEsperada) {

		$esperaIndenizada = $horaJornadaEsperada->diff($esperaIndenizada)->format('%H:%I');
		// SE JORNADA EFETIVA FOR MENOR QUE A JORNADA ESPERADA, NAO GERA SALDO

		$jornadaEfetiva = $aRetorno['jornadaPrevista'];
	} else {
		if ($saldoPositivo == '-' || $horario2->format('H:i') == '00:00') {
			$esperaIndenizada = '';
		} else {
			$esperaIndenizada = $horario2->format('H:i');
		}
	}

	$aRetorno['esperaIndenizada'] = $esperaIndenizada;

	//FIM CALCULO ESPERA INDENIZADA


	//INICIO ADICIONAL NOTURNO
	$jornadaNoturno = $jornadaOrdenado['totalIntervaloAdicionalNot'];

	$refeicaoNoturno = $refeicaoOrdenada['totalIntervaloAdicionalNot'];
	$esperaNoturno = $esperaOrdenada['totalIntervaloAdicionalNot'];
	$descansoNoturno = $descansoOrdenado['totalIntervaloAdicionalNot'];
	$repousoNoturno = $repousoOrdenado['totalIntervaloAdicionalNot'];

	$intervalosNoturnos = somarHorarios([$refeicaoNoturno, $esperaNoturno, $descansoNoturno, $repousoNoturno]);

	$adicionalNoturno = gmdate('H:i', (strtotime($data . ' ' . $jornadaNoturno)) - (strtotime($data . ' ' . $intervalosNoturnos)));
	$aRetorno['adicionalNoturno'] = $adicionalNoturno == '00:00' ? '' : $adicionalNoturno;

	//FIM ADICIONAL NOTURNO

	//CALCULO SALDO
	$dateCargaHoraria = new DateTime($aRetorno['jornadaPrevista']);
	$dateJornadaEfetiva = new DateTime($jornadaEfetiva);

	// print_r($dateCargaHoraria);
	// echo "<hr>";
	// print_r($dateJornadaEfetiva);
	// echo "<hr>";
	// exit;

	// echo str_pad($cargaHoraria, 2, '0', STR_PAD_LEFT).":00:00<br>";
	$diffSaldo = $dateCargaHoraria->diff($dateJornadaEfetiva);
	// $aRetorno['diffSaldo'] = $hours.":".$minutes.":".$seconds;
	$aRetorno['diffSaldo'] = $diffSaldo->format("%r%H:%I");
	//FIM CALCULO SALDO




	if ($stringFeriado != '') {
		$iconeFeriado =  "<a><i style='color:orange;' title='$stringFeriado' class='fa fa-info-circle'></i></a>";
		$aRetorno['he100'] = $iconeFeriado . $aRetorno['diffSaldo'];
	} elseif (strftime('%w', strtotime($data)) == '0') {
		$aRetorno['he100'] = $aRetorno['diffSaldo'];
	} elseif ($aRetorno['diffSaldo'][0] != '-') {
		$aRetorno['he50'] = $aRetorno['diffSaldo'];
	}


	$mdc = maxDirecaoContinua($arrayMDC);

	$aRetorno['maximoDirecaoContinua'] = verificaTempoMdc($mdc, $descansoOrdenado['totalIntervalo']);


	// VERIFICA SE A JORNADA É MAIOR QUE 06:00
	$dtJornada = new DateTime($data . ' ' . $diffJornadaEfetiva->format("%H:%I"));
	$dtJornadaMinima = new DateTime($data . ' ' . '06:00');

	if (($dtJornada > $dtJornadaMinima && $diffJornadaEfetiva->format("%H:%I") != '00:00' || ($aRetorno['jornadaPrevista'] != '00:00' && $diffJornadaEfetiva->format("%H:%I") == '00:00'))) {
		$verificaJornadaMinima = 1;
	} else {
		$verificaJornadaMinima = 0;
	}
	//FIM VERIFICA JORNADA MINIMA

	//VERIFICA SE HOUVE 01:00 DE REFEICAO
	$dtRefeicaoMinima = new DateTime($data . ' ' . '01:00');
	$menor1h = 0;
	for ($i = 0; $i < count($refeicaoOrdenada['pares']); $i++) {
		$dtIntervaloRefeicao = new DateTime($data . ' ' . $refeicaoOrdenada['pares'][$i]['intervalo']);
		if ($dtIntervaloRefeicao < $dtRefeicaoMinima) {
			$menor1h = 1;
		}
	}

	if ($aRetorno['diffRefeicao'] == '00:00')
		$menor1h = 1;

	if ($menor1h && $dtJornada > $dtJornadaMinima) {
		$aRetorno['diffRefeicao'] = "<a><i style='color:red;' title='Refeição com tempo ininterrupto mínimo de 01:00h, não respeitado.' class='fa fa-warning'></i></a>" . $aRetorno['diffRefeicao'];
	} else {
		$iconeRefeicaoMinima = '';
	}

	// if($data == '2023-07-08'){
	// 	echo '<pre>'.$data.'</pre>';
	// 	echo '<pre>'.($menor1h).'</pre>';
	// 	echo '<pre>'.($dtJornada > $dtJornadaMinima).'</pre>';
	// 	echo '<pre>'.($aRetorno['diffRefeicao']).'</pre>';
	// 	echo '<pre>'.($aRetorno['jornadaPrevista'] != '00:00').'</pre>';
	// }
	if ($arrayInicioJornada[0] == '' && $verificaJornadaMinima == 1) {
		$aRetorno['inicioJornada'] = "<a><i style='color:red;' title='Batida início de jornada não registrada!' class='fa fa-warning'></i></a>";
	}
	// if($aRetorno['fimJornada'] == '' && $cargaHoraria != 0){
	if ($arrayFimJornada[0] == '' && $verificaJornadaMinima == 1) {
		$aRetorno['fimJornada'] = "<a><i style='color:red;' title='Batida fim de jornada não registrada!' class='fa fa-warning'></i></a>";
	}
	// if($aRetorno['inicioRefeicao'] == '' && $cargaHoraria != 0){
	if ($aRetorno['inicioRefeicao'] == '' && $verificaJornadaMinima == 1) {
		$aRetorno['inicioRefeicao'] = "<a><i style='color:red;' title='Batida início de refeição não registrada!' class='fa fa-warning'></i></a>" . $iconeRefeicaoMinima;
	}
	// if($aRetorno['fimRefeicao'] == '' && $cargaHoraria != 0){
	if ($aRetorno['fimRefeicao'] == '' && $verificaJornadaMinima == 1) {
		$aRetorno['fimRefeicao'] = "<a><i style='color:red;' title='Batida fim de refeição não registrada!' class='fa fa-warning'></i></a>" . $iconeRefeicaoMinima;
	}

	if ($iconeAbono != '') {
		$aRetorno['jornadaPrevista'] = $iconeAbono . "&nbsp;" . $aRetorno['jornadaPrevista'];
	}

	if (count($arrayInicioJornada) > 0) {
		$aRetorno['inicioJornada'] = implode("<br>", $arrayInicioJornada);
	}

	if (count($arrayFimJornada) > 0) {
		$aRetorno['fimJornada'] = implode("<br>", $arrayFimJornada);
	}

	if (count($arrayInicioRefeicao) > 0) {
		$aRetorno['inicioRefeicao'] = implode("<br>", $arrayInicioRefeicaoFormatado);
	}

	if (count($arrayFimRefeicao) > 0) {
		$aRetorno['fimRefeicao'] = implode("<br>", $arrayFimRefeicaoFormatado);
	}

	if (count($aDataHorainicioEspera) > 0 && count($aDataHorafimEspera) > 0) {
		$aRetorno['diffEspera'] = $esperaOrdenada['icone'] . $esperaOrdenada['totalIntervalo'];
	}
	if (count($aDataHorainicioDescanso) > 0 && count($aDataHorafimDescanso) > 0) {
		$aRetorno['diffDescanso'] =  $descansoOrdenado['icone'] . verificaTempo($descansoOrdenado['totalIntervalo'], "05:30");
	}
	if (count($aDataHorainicioRepouso) > 0 && count($aDataHorafimRepouso) > 0) {
		$aRetorno['diffRepouso'] = $repousoOrdenado['icone'] . $repousoOrdenado['totalIntervalo'];
	}

	// TOTALIZADOR 
	$totalResumo['diffRefeicao'] = somarHorarios(array($totalResumo['diffRefeicao'], strip_tags(str_replace("&nbsp;", "", $aRetorno['diffRefeicao']))));
	$totalResumo['diffEspera'] = somarHorarios(array($totalResumo['diffEspera'], strip_tags(str_replace("&nbsp;", "", $aRetorno['diffEspera']))));
	$totalResumo['diffDescanso'] = somarHorarios(array($totalResumo['diffDescanso'], strip_tags(str_replace("&nbsp;", "", $aRetorno['diffDescanso']))));
	$totalResumo['diffRepouso'] = somarHorarios(array($totalResumo['diffRepouso'], strip_tags(str_replace("&nbsp;", "", $aRetorno['diffRepouso']))));
	$totalResumo['diffJornada'] = somarHorarios(array($totalResumo['diffJornada'], strip_tags(str_replace("&nbsp;", "", $aRetorno['diffJornada']))));
	$totalResumo['jornadaPrevista'] = somarHorarios(array($totalResumo['jornadaPrevista'], strip_tags(str_replace("&nbsp;", "", $aRetorno['jornadaPrevista']))));
	$totalResumo['diffJornadaEfetiva'] = somarHorarios(array($totalResumo['diffJornadaEfetiva'], strip_tags(str_replace("&nbsp;", "", $aRetorno['diffJornadaEfetiva']))));
	$totalResumo['maximoDirecaoContinua'] = '';
	$totalResumo['intersticio'] = somarHorarios(array($totalResumo['intersticio'], strip_tags(str_replace("&nbsp;", "", $aRetorno['intersticio']))));
	$totalResumo['he50'] = somarHorarios(array($totalResumo['he50'], strip_tags(str_replace("&nbsp;", "", $aRetorno['he50']))));
	$totalResumo['he100'] = somarHorarios(array($totalResumo['he100'], strip_tags(str_replace("&nbsp;", "", $aRetorno['he100']))));
	$totalResumo['adicionalNoturno'] = somarHorarios(array($totalResumo['adicionalNoturno'], strip_tags(str_replace("&nbsp;", "", $aRetorno['adicionalNoturno']))));
	$totalResumo['esperaIndenizada'] = somarHorarios(array($totalResumo['esperaIndenizada'], strip_tags(str_replace("&nbsp;", "", $aRetorno['esperaIndenizada']))));
	$totalResumo['diffSaldo'] = somarHorarios(array($totalResumo['diffSaldo'], strip_tags(str_replace("&nbsp;", "", $aRetorno['diffSaldo']))));

	// echo "<pre>";
	// echo "$aRetorno[diffSaldo] -> $totalResumo[diffSaldo]";
	// echo "</pre>";

	return $aRetorno;
}
