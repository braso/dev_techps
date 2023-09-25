<?php
include "conecta.php";


function somarHorarios($horarios): string {
    $totalSegundos = 0;

    foreach ($horarios as $horario) {
        list($horas, $minutos, $segundos) = explode(':', $horario);
        $totalSegundos += $horas * 3600 + $minutos * 60 + $segundos;
    }

    $horas = floor($totalSegundos / 3600);
    $minutos = floor(($totalSegundos % 3600) / 60);
    $segundos = $totalSegundos % 60;

    return sprintf('%02d:%02d', $horas, $minutos, $segundos);
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
	$alertaTooltip ='';

    for ($i = 0; $i < count($horarios); $i++) {
        // $horarios_com_origem[] = array(
        //     "horario" => $horarios[$i],
        //     "origem" => $origem[$i]
        // );

		$alertaTooltip .= "$origem[$i] ".data($horarios[$i],1)."\n";

    }

	$iconeAlerta =  "<a><i style='color:orange;' title='$alertaTooltip' class='fa fa-info-circle'></i></a>";

	

    // Retorna o array de horários com suas respectivas origens
    return $iconeAlerta;
}

function diaDetalhePonto($matricula, $data){
	setlocale(LC_ALL, 'pt_BR.utf8');

	$aRetorno['data'] = data($data);
	$aRetorno['diaSemana'] = mb_strtoupper(strftime('%a',strtotime($data)));
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
	$aRetorno['he50'] = '';
	$aRetorno['he100'] = '';
	$aRetorno['adicionalNoturno'] = '';
	$aRetorno['esperaIndenizada'] = '';
	$aRetorno['diffSaldo'] = '';
	
	$sqlMotorista = query("SELECT * FROM entidade WHERE enti_tx_status != 'inativo' AND enti_tx_matricula = '$matricula' LIMIT 1");
	$aMotorista = carrega_array($sqlMotorista);
	if(strftime('%w',strtotime($data)) == '6'){
		$cargaHoraria = $aMotorista[enti_tx_jornadaSabado];
	}elseif(strftime('%w',strtotime($data)) == '0'){
		$cargaHoraria = 0; //DOMINGOS
	}else{
		$cargaHoraria = $aMotorista[enti_tx_jornadaSemanal]/5;
	}

	// if($cargaHoraria == 0){
	// 	return $aRetorno;
	// 	exit;
	// }

	$sql = query("SELECT * FROM ponto WHERE pont_tx_status != 'inativo' AND pont_tx_matricula = '$matricula'  AND pont_tx_data LIKE '$data%' ORDER BY pont_tx_data ASC");
	while($aDia = carrega_array($sql)){
		
		// $queryMacroPonto = query("SELECT macr_tx_nome,macr_tx_codigoInterno FROM macroponto WHERE macr_tx_codigoExterno = '".$aDia[pont_tx_tipo]."'");
		// $aTipo = carrega_array($queryMacroPonto);

		if($aDia[pont_tx_tipo] == '1'){
			$aRetorno['inicioJornada'] = date("H:i",strtotime($aDia[pont_tx_data]));
			$inicioDataAdicional = $aDia[pont_tx_data];
		}

		if($aDia[pont_tx_tipo] == '2'){
			$aRetorno['fimJornada'] = date("H:i",strtotime($aDia[pont_tx_data]));
			$fimDataAdicional = $aDia[pont_tx_data];
		}

		if($aDia[pont_tx_tipo] == '3'){
			$aRetorno['inicioRefeicao'] = date("H:i",strtotime($aDia[pont_tx_data]));
		}

		if($aDia[pont_tx_tipo] == '4'){
			$aRetorno['fimRefeicao'] = date("H:i",strtotime($aDia[pont_tx_data]));
		}

		if($aDia[pont_tx_tipo] == '5'){
			$dataHorainicioEspera = $aDia[pont_tx_data];
			$aDataHorainicioEspera[] = $dataHorainicioEspera;
		}

		if($aDia[pont_tx_tipo] == '6'){
			$dataHorafimEspera = $aDia[pont_tx_data];
			$aDataHorafimEspera[] = $dataHorafimEspera;
		}

		if($aDia[pont_tx_tipo] == '7'){
			$dataHorainicioDescanso = date("H:i",strtotime($aDia[pont_tx_data]));
			$aDataHorainicioDescanso[] = $dataHorainicioDescanso;
		}

		if($aDia[pont_tx_tipo] == '8'){
			$dataHorafimDescanso = date("H:i",strtotime($aDia[pont_tx_data]));
			$aDataHorafimDescanso[] = $dataHorafimDescanso;
		}

		if($aDia[pont_tx_tipo] == '9'){
			$dataHorainicioRepouso = date("H:i",strtotime($aDia[pont_tx_data]));
			$aDataHorainicioRepouso[] = $dataHorainicioRepouso;
		}

		if($aDia[pont_tx_tipo] == '10'){
			$dataHorafimRepouso = date("H:i",strtotime($aDia[pont_tx_data]));
			$aDataHorafimRepouso[] = $dataHorafimRepouso;
		}

		if($aDia[pont_tx_tipo] == '11'){
			$dataHoraRepousoEmbarcado = date("H:i",strtotime($aDia[pont_tx_data]));
		}

		if($aDia[pont_tx_tipo] == '12'){
			$dataHorafimRepousoEmbarcado = date("H:i",strtotime($aDia[pont_tx_data]));
		}
		
		// echo "<hr>";
		// echo "$aTipo[macr_tx_nome] - $aTipo[macr_tx_codigoInterno] -> ";
		// echo date("Y-m-d",strtotime($aDia['pont_tx_data']));
		// echo " - ";
		// // print_r($aTipo);
		// echo "<hr>";
		if($dataHorainicioEspera!= '' && $dataHorafimEspera != ''){
			// echo "<hr>$data - $dataHorainicioEspera --- $dataHorafimEspera";
			$dateInicioEspera = new DateTime($dataHorainicioEspera);
			$dateFimEspera = new DateTime($dataHorafimEspera);
			$diffEspera = $dateInicioEspera->diff($dateFimEspera);
			$aEspera[] = $diffEspera->format("%H:%I");

			unset($dataHorainicioEspera, $dataHorafimEspera);

		}
		
	}

	$somaEspera = somarHorarios($aEspera);

	$sqlAbono = query("SELECT * FROM abono, motivo, user 
		WHERE abon_tx_status != 'inativo' AND abon_nb_userCadastro = user_nb_id 
		AND abon_tx_matricula = '$matricula' AND abon_tx_data = '$data' AND abon_nb_motivo = moti_nb_id
		ORDER BY abon_nb_id DESC LIMIT 1");
	$aAbono = carrega_array($sqlAbono);
	if($aAbono[0] > 0){
		$tooltip = "Jornada Original: ".str_pad($cargaHoraria, 2, '0', STR_PAD_LEFT).":00:00"."\n";
		$tooltip .= "Abono: $aAbono[abon_tx_abono]\n";
		$tooltip .= "Motivo: $aAbono[moti_tx_nome]\n";
		$tooltip .= "Justificativa: $aAbono[abon_tx_descricao]\n\n";
		$tooltip .= "Registro efetuado por $aAbono[user_tx_login] em ".data($aAbono[abon_tx_dataCadastro],1);

		$iconeAbono =  "<a><i style='color:orange;' title='$tooltip' class='fa fa-warning'></i></a>";
	}else{
		$tooltip = '';
		$iconeAbono = '';
	}

	
	// INICIO CALCULO REFEICAO
	$dataHoraInicioRefeicao = new DateTime($data." ".$aRetorno['inicioRefeicao']);
	$dataHoraFimRefeicao = new DateTime($data." ".$aRetorno['fimRefeicao']);

	$diffRefeicao = $dataHoraInicioRefeicao->diff($dataHoraFimRefeicao);
	$aRetorno['diffRefeicao'] = $diffRefeicao->format("%r%H:%I");
	// FIM CALCULO REFEICAO

	// INICIO CALCULO ESPERA
	// $dataHoraInicioEspera = new DateTime($data." ".$dataHorainicioEspera);
	// $dataHoraFimEspera = new DateTime($data." ".$dataHorafimEspera);

	// $diffEspera = $dataHoraInicioEspera->diff($dataHoraFimEspera);
	// $aRetorno['diffEspera'] = $diffEspera->format("%r%H:%I");

	$aRetorno['diffEspera'] = $iconeEspera.somarHorarios($aEspera);
	

	if($aAbono[0] > 0){
		$tooltip = "Jornada Original: ".str_pad($cargaHoraria, 2, '0', STR_PAD_LEFT).":00:00"."\n";
		$tooltip .= "Abono: $aAbono[abon_tx_abono]\n";
		$tooltip .= "Motivo: $aAbono[moti_tx_nome]\n";
		$tooltip .= "Justificativa: $aAbono[abon_tx_descricao]\n\n";
		$tooltip .= "Registro efetuado por $aAbono[user_tx_login] em ".data($aAbono[abon_tx_dataCadastro],1);

		$iconeAbono =  "<a><i style='color:orange;' title='$tooltip' class='fa fa-warning'></i></a>";
	}else{
		$tooltip = '';
		$iconeAbono = '';
	}
	// FIM CALCULO ESPERA

	// INICIO CALCULO DESCANSO
	$dataHoraInicioDescanso = new DateTime($data." ".$dataHorainicioDescanso);
	$dataHoraFimDescanso = new DateTime($data." ".$dataHorafimDescanso);
	

	$diffDescanso = $dataHoraInicioDescanso->diff($dataHoraFimDescanso);

	$aRetorno['diffDescanso'] = $iconeDescanso.$diffDescanso->format("%r%H:%I");
	// FIM CALCULO DESCANSO

	// INICIO CALCULO REPOUSO
	$dataHoraInicioRepouso = new DateTime($data." ".$dataHorainicioRepouso);
	$dataHoraFimRepouso = new DateTime($data." ".$dataHorafimRepouso);

	$diffRepouso = $dataHoraInicioRepouso->diff($dataHoraFimRepouso);

	$aRetorno['diffRepouso'] = $iconeRepouso.$diffRepouso->format("%H:%I");
	// FIM CALCULO REPOUSO
	

	// INICIO CALCULO JORNADA TRABALHO (DESCONSIDEREANDO PAUSAS)
	$dataHoraInicioJornada = new DateTime($data." ".$aRetorno['inicioJornada']);
	$dataHoraFimJornada = new DateTime($data." ".$aRetorno['fimJornada']);
	

	$diffJornada = $dataHoraInicioJornada->diff($dataHoraFimJornada);
	$aRetorno['diffJornada'] = $diffJornada->format("%H:%I");
	// FIM CALCULO CALCULO JORNADA TRABALHO
	
	
	// INICIO JORNADA ESPERADA
	$dataJornadaPrevista = new DateTime($data." ".str_pad($cargaHoraria, 2, '0', STR_PAD_LEFT).":00:00");
	$dataAbono = new DateTime($data." ".$aAbono['abon_tx_abono']);
	$diffJornadaPrevista = $dataAbono->diff($dataJornadaPrevista);
	$aRetorno['jornadaPrevista'] = $diffJornadaPrevista->format("%H:%I");
	// FIM JORNADA ESPERADA

	// echo "<br>|$data | $aRetorno[inicioJornada] | $aRetorno[fimJornada] | $aRetorno[diffJornada]|$aRetorno[diffRefeicao]";
	// exit;
	// INICIO CALCULO JORNADA EFETIVAMENTE DO DIA
	$horaTotal = new DateTime($aRetorno['diffJornada']);

	//SOMATORIO DE TODAS AS ESPERAS
	$horaTotalIntervalos = new DateTime(somarHorarios(array($aRetorno['diffRefeicao'], $aRetorno['diffEspera'], $aRetorno['diffDescanso'],	$aRetorno['diffRepouso'])));

	$diffJornadaEfetiva = $horaTotal->diff($horaTotalIntervalos);
	$aRetorno['diffJornadaEfetiva'] = $diffJornadaEfetiva->format("%H:%I");
	// FIM CALCULO JORNADA EFETIVAMENTE DO DIA

	

	//CALCULO ESPERA INDENIZADA
	$horaJornadaEsperada = DateTime::createFromFormat('H:i', $aRetorno['jornadaPrevista']);
	$horario1 = DateTime::createFromFormat('H:i', $aRetorno['diffJornadaEfetiva']);
	$horario2 = DateTime::createFromFormat('H:i', $somaEspera);

	$esperaIndenizada = clone $horario1;
	$esperaIndenizada->add(new DateInterval('PT' . $horario2->format('H') . 'H' . $horario2->format('i') . 'M'));

	$jornadaEfetiva = $esperaIndenizada->format('H:i');
	
	if ($esperaIndenizada > $horaJornadaEsperada && $horario1 < $horaJornadaEsperada ) {
		$esperaIndenizada = $horaJornadaEsperada->diff($esperaIndenizada)->format('%H:%I');
		// SE JORNADA EFETIVA FOR MENOR QUE A JORNADA ESPERADA, NAO GERA SALDO
		
		$jornadaEfetiva = '08:00';
		
	} else {
		$esperaIndenizada = '';
	}

	// echo "$aRetorno[diffJornadaEfetiva] | $somaEspera | $esperaIndenizada";
	// echo "<br>";

	$aRetorno['esperaIndenizada'] = $esperaIndenizada;
	
	//FIM CALCULO ESPERA INDENIZADA

	//CALCULO SALDO
	$dateCargaHoraria = new DateTime($aRetorno['jornadaPrevista']);
	$dateJornadaEfetiva = new DateTime($jornadaEfetiva);

	// echo str_pad($cargaHoraria, 2, '0', STR_PAD_LEFT).":00:00<br>";
	$diffSaldo = $dateCargaHoraria->diff($dateJornadaEfetiva);
	// $aRetorno['diffSaldo'] = $hours.":".$minutes.":".$seconds;
	$aRetorno['diffSaldo'] = $diffSaldo->format("%r%H:%I");
	//FIM CALCULO SALDO

	if($aRetorno['diffSaldo'][0] != '-'){
		$aRetorno['he50'] = $aRetorno['diffSaldo'];
	}

	if($iconeAbono != ''){
		$aRetorno['jornadaPrevista'] = $iconeAbono."&nbsp;".$aRetorno['jornadaPrevista'];
	}

	// if($aRetorno['inicioJornada'] == '' && $cargaHoraria != 0){
	if($aRetorno['inicioJornada'] == ''){
		$aRetorno['inicioJornada'] = "<a><i style='color:red;' title='Batida início de jornada não registrada!' class='fa fa-warning'></i></a>";
	}
	// if($aRetorno['fimJornada'] == '' && $cargaHoraria != 0){
	if($aRetorno['fimJornada'] == ''){
		$aRetorno['fimJornada'] = "<a><i style='color:red;' title='Batida fim de jornada não registrada!' class='fa fa-warning'></i></a>";
	}
	// if($aRetorno['inicioRefeicao'] == '' && $cargaHoraria != 0){
	if($aRetorno['inicioRefeicao'] == ''){
		$aRetorno['inicioRefeicao'] = "<a><i style='color:red;' title='Batida início de refeição não registrada!' class='fa fa-warning'></i></a>";
	}
	// if($aRetorno['fimRefeicao'] == '' && $cargaHoraria != 0){
	if($aRetorno['fimRefeicao'] == ''){
		$aRetorno['fimRefeicao'] = "<a><i style='color:red;' title='Batida fim de refeição não registrada!' class='fa fa-warning'></i></a>";
	}

	if($inicioDataAdicional!= '' && $fimDataAdicional != ''){
		// // Definindo as datas de início e fim do intervalo
		// $data_inicio = new DateTime($inicioDataAdicional);
		// $data_fim = new DateTime($fimDataAdicional);
		// // $data_inicio = new DateTime('2023-03-15 23:30:00');
		// // $data_fim = new DateTime('2023-03-16 04:45:00');

		// // Definindo as datas de início e fim do intervalo desejado (22h do dia anterior até 5h do dia seguinte)
		// $intervalo_inicio = clone $data_inicio;
		// $intervalo_inicio->setTime(22, 0, 0);
		// if ($intervalo_inicio > $data_inicio) {
		// 	$intervalo_inicio->modify('-1 day');
		// }

		// $intervalo_fim = clone $data_inicio;
		// $intervalo_fim->setTime(5, 0, 0);
		// if ($intervalo_fim < $data_inicio) {
		// 	$intervalo_fim->modify('+1 day');
		// }

		// // Verificando se o intervalo de tempo está dentro do intervalo desejado
		// if ($data_inicio >= $intervalo_inicio && $data_fim <= $intervalo_fim) {
		// 	$duracao_intervalo = $data_fim->diff($data_inicio);
		// 	$duracao_intervalo_formatada = $duracao_intervalo->format('%H:%I:%S');
		// 	$aRetorno['adicionalNoturno'] = $duracao_intervalo_formatada;
		// } else {
		// 	$aRetorno['adicionalNoturno'] = "";
		// }


		// Define as batidas de ponto
		// $hora_inicial = new DateTime('2023-03-16 22:00:00');
		// $hora_final = new DateTime('2023-03-17 05:00:00');
		$hora_inicial = new DateTime($inicioDataAdicional);
		$hora_final = new DateTime($fimDataAdicional);

		// Define a hora em que o adicional noturno começa e termina
		$inicio_adicional = new DateTime($hora_inicial->format('Y-m-d') . ' 22:00:00');
		$fim_adicional = new DateTime($hora_inicial->format('Y-m-d') . ' 05:00:00');
		$fim_adicional->modify('+1 day');


		// Verifica se a batida de ponto inicial ocorreu antes das 22h
		if ($hora_inicial < $inicio_adicional) {
			$hora_inicial = $inicio_adicional;
		}

		// Verifica se a batida de ponto final ocorreu depois das 5h da manhã
		if ($hora_final >= $fim_adicional) {
			$hora_final = $fim_adicional;
		}

		// print_r($hora_inicial);
		// echo "<hr>";
		// print_r($hora_final);
		// echo "<hr>";

		// Calcula a duração do adicional noturno
		$duracao_adicional = $hora_inicial ->diff($hora_final);

		// Formata a duração do adicional noturno em HH:MM
		$duracao_formatada = $duracao_adicional->format('%H:%I');

		// Verifica se houve adicional noturno
		if ($duracao_adicional->invert || $duracao_adicional->format('%H:%I') === '00:00') {
			// echo "Não há adicional noturno a ser calculado";
			$aRetorno['adicionalNoturno'] = "";
		} else {
			// echo "Duração do adicional noturno: " . $duracao_formatada;
			$aRetorno['adicionalNoturno'] = $duracao_formatada;
		}



	}

	if(count($aDataHorainicioEspera)>0 && count($aDataHorafimEspera)>0){
		$iconeEspera = ordena_horarios($aDataHorainicioEspera, $aDataHorafimEspera);
		$aRetorno['diffEspera'] = $iconeEspera.somarHorarios($aEspera);
	}
	if(count($aDataHorainicioDescanso)>0 && count($aDataHorafimDescanso)>0){
		$iconeDescanso = ordena_horarios($aDataHorainicioDescanso, $aDataHorafimDescanso);
		$aRetorno['diffDescanso'] = $iconeDescanso.$diffDescanso->format("%r%H:%I");
	}
	if(count($aDataHorainicioRepouso)>0 && count($aDataHorafimRepouso)>0){
		$iconeRepouso = ordena_horarios($aDataHorainicioRepouso, $aDataHorafimRepouso);
		$aRetorno['diffRepouso'] = $iconeRepouso.$diffRepouso->format("%H:%I");
	}

	
	return $aRetorno;
	
}



function cor($diffSaldo, $data){
	// $a_bole = carregar('boleto',$id_bole);
	
	if($diffSaldo[0] == '-'){
		$retorno = '<center><a title="Ajuste de Ponto" href="#" onclick="ajusta_ponto(\''.$data.'\')"><i style="color:red;" class="fa fa-circle"></i></a></center>';
	}else{
		$retorno = '<center><a title="Ajuste de Ponto" href="#" onclick="ajusta_ponto(\''.$data.'\')"><i style="color:blue;" class="fa fa-circle"></i></a></center>';
		// if($dias < 0){
			// $retorno = '<center><i style="color:red;" class="fa fa-circle"></i></center>';
		// }else{
			// $retorno = '<center><i style="color:green;" class="fa fa-circle"></i></center>';
		// }
	}

	return $retorno;
	
}

function cadastra_abono(){

	$aData = explode(" - ", $_POST[daterange]);

	$begin = new DateTime(data($aData[0]));
	$end = new DateTime(data($aData[1]));

	$a=carregar('entidade',$_POST[motorista]);
	
	for($i = $begin; $i <= $end; $i->modify('+1 day')){
		$campos = array(abon_tx_data, abon_tx_matricula, abon_tx_abono, abon_nb_motivo, abon_tx_descricao, abon_nb_userCadastro, abon_tx_dataCadastro, abon_tx_status);
		$valores = array($i->format("Y-m-d"), $a[enti_tx_matricula], $_POST[abono], $_POST[motivo], $_POST[descricao], $_SESSION[user_nb_id], date("Y-m-d H:i:s"), 'ativo');

		inserir('abono', $campos, $valores);
	}

	$_POST[busca_motorista] = $_POST[motorista];

	index();
	exit;
}

function layout_abono(){
	global $CACTUX_CONF;

	cabecalho('Espelho de Ponto');

	$c[] = combo_net('Motorista:','motorista',$_POST[busca_motorista],4,'entidade','',' AND enti_tx_tipo = "Motorista"','enti_tx_matricula');
	$c[] = campo('Data(s):','daterange',$_POST[daterange],3);
	$c[] = campo_hora('Abono: (hh:mm)','abono','',3);
	$c2[] = combo_bd('Motivo:','motivo',$_POST[motivo],4,'motivo');
	$c2[] = textarea('Justificativa:','descricao','',12);
	
	//BOTOES
	$b[] = botao("Voltar",'index');
	$b[] = botao("Gravar",'cadastra_abono');
	
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

function cadastra_ajuste(){

	$aMotorista = carregar('entidade',$_POST[id]);

	$queryMacroPonto = query("SELECT macr_tx_codigoInterno, macr_tx_codigoExterno FROM macroponto WHERE macr_nb_id = '".$_POST[idMacro]."'");
	$aTipo = carrega_array($queryMacroPonto);


	$extra = " AND pont_tx_data LIKE '$_POST[data] %' AND pont_tx_matricula = '$aMotorista[enti_tx_matricula]' AND pont_tx_tipo = $aTipo[0]";
	$queryCheck = query("SELECT * FROM ponto WHERE pont_tx_status != 'inativo' $extra");
	$aCheck = carrega_array($queryCheck);
	if(num_linhas($queryCheck) > 0){
		remover('ponto',$aCheck[0]);
	}


	$campos = array(pont_nb_user, pont_tx_matricula, pont_tx_data, pont_tx_tipo, pont_tx_tipoOriginal, pont_tx_status, pont_tx_dataCadastro);
	$valores = array($_SESSION[user_nb_id], $aMotorista['enti_tx_matricula'], "$_POST[data] $_POST[hora]", $aTipo[0], $aTipo[1], 'ativo', date("Y-m-d H:i:s"));
	inserir('ponto',$campos,$valores);

	
	layout_ajuste();
	exit;
}

function excluir_ponto(){
	$a=carregar('ponto', (int)$_POST[id]);
	remover('ponto', (int)$_POST[id]);
	
	$_POST[id] = $_POST[idEntidade];
	$_POST[data] = substr($a[pont_tx_data],0, -9);
	$_POST[busca_data] = $a[pont_tx_data];


	layout_ajuste();
	exit;
}

function layout_ajuste(){
	global $a_mod;

	cabecalho('Espelho de Ponto');
	
	$aMotorista = carregar('entidade',$_POST[id]);
	
	$extra = " AND pont_tx_data LIKE '$_POST[data] %' AND pont_tx_matricula = '$aMotorista[enti_tx_matricula]'";

	$c[] = texto('Matrícula',$aMotorista[enti_tx_matricula],2);
	$c[] = texto('Motorista',$aMotorista[enti_tx_nome],5);
	$c[] = texto('CPF',$aMotorista[enti_tx_cpf],3);

	$c2[] = campo('Data','data',data($_POST[data]),3,'','readonly=readonly');
	$c2[] = campo_hora('Hora','hora',$a_mod[macr_tx_codigoExterno],3);
	$c2[] = combo_bd('Código Macro','idMacro','',3,'macroponto','','ORDER BY macr_nb_id ASC');

	$botao[] = botao('Gravar','cadastra_ajuste','id,busca_motorista,data,busca_data',"$_POST[id],$_POST[id],$_POST[data],".substr($_POST[data],0, -3));
	$botao[] = botao('Voltar','index','id,busca_motorista,data,busca_data',"$_POST[id],$_POST[id],$_POST[data],".substr($_POST[data],0, -3));
	
	abre_form('Dados do Ajuste de Ponto');
	linha_form($c);
	linha_form($c2);
	fecha_form($botao);

	$sql=" SELECT *	FROM ponto,macroponto,user WHERE pont_nb_user = user_nb_id AND pont_tx_tipo = macr_nb_id AND pont_tx_status != 'inativo' $extra ";
	$cab = array('CÓD','TIPO','DATA','HORA','USUÁRIO','DATA CADASTRO','');

	// $ver2 = "icone_modificar(arqu_nb_id,layout_confirma)";
	$val = array('pont_nb_id','macr_tx_nome','data(pont_tx_data)','data(pont_tx_data,3)','user_tx_login','data(pont_tx_dataCadastro,1)','icone_excluir(pont_nb_id,excluir_ponto,idEntidade,'.$_POST[id].')');
	grid($sql,$cab,$val,'','',3,'ASC');

	rodape();

}


function index() {
	global $CACTUX_CONF;

	cabecalho('Espelho de Ponto');

	$extra = '';

	if($_POST[busca_motorista]){
		$aMotorista = carregar('entidade',$_POST[busca_motorista]);
		$aDadosMotorista = array($aMotorista[enti_tx_matricula]);
	}

	//CONSULTA
	$c[] = combo_net('Motorista:','busca_motorista',$_POST[busca_motorista],5,'entidade','',' AND enti_tx_tipo = "Motorista"','enti_tx_matricula');
	$c[] = campo_mes('Data:','busca_data',$_POST[busca_data],2);
	
	//BOTOES
	$b[] = botao("Buscar",'index');
	$b[] = botao("Cadastrar Abono",'layout_abono');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);
	
	// $cab = array("MATRÍCULA", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA", "REFEIÇÃO", "ESPERA", "ATRASO", "EFETIVA", "PERÍODO TOTAL", "INTERSTÍCIO DIÁRIO", "INT. SEMANAL", "ABONOS", "FALTAS", "FOLGAS", "H.E.", "H.E. 100%", "ADICIONAL NOTURNO", "ESPERA INDENIZADA", "OBSERVAÇÕES");
	$cab = array("", "MAT.", "DATA", "DIA", "INÍCIO JORNADA", "INÍCIO REFEIÇÃO", "FIM REFEIÇÃO", "FIM JORNADA",
		"REFEIÇÃO", "ESPERA", "DESCANSO", "REPOUSO", "JORNADA", "JORNADA PREVISTA", "JORNADA EFETIVA","HE 50%", "HE 100%",
		"ADICIONAL NOT.", "ESPERA INDENIZADA", "SALDO");

	if($_POST[busca_data] && $_POST[busca_motorista]){
		$date = new DateTime($_POST[busca_data]);
		$month = $date->format('m');
		$year = $date->format('Y');

		$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		
		for ($i = 1; $i <= $daysInMonth; $i++) {
			$dataVez = $_POST[busca_data]."-".str_pad($i,2,0,STR_PAD_LEFT);
			
			$aDetalhado = diaDetalhePonto($aMotorista[enti_tx_matricula], $dataVez);
			
			$aDia[] = array_values(array_merge(array(cor($aDetalhado['diffSaldo'], $dataVez)), $aDadosMotorista, $aDetalhado));
			
		}

		abre_form();

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
		function ajusta_ponto(data){
			document.form_ajuste_ponto.data.value = data;
			document.form_ajuste_ponto.submit();
		}
	</script>
	<?

}
