<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	include_once "conecta.php";

	function calcularAbono($horario1, $horario2){
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

	function cadastra_abono(){
		// Conferir se os campos obrigatórios estão preenchidos{
			$campos_obrigatorios = ['motorista' => 'Motorista', 'daterange' => 'Data', 'abono' => 'Horas', 'motivo' => 'Motivo'];
			$error = false;
			$errorMsg = '';
			foreach(array_keys($campos_obrigatorios) as $campo){
				if(!isset($_POST[$campo]) || empty($_POST[$campo])){
					$error = true;
					$errorMsg .= $campos_obrigatorios[$campo].', ';
				}
			}

			if($error){
				set_status('ERRO: Campos obrigatórios não preenchidos: '. substr($errorMsg, 0, strlen($errorMsg)-2).'.');
				layout_abono();
				exit;
			}
		// }

		$_POST['busca_motorista'] = $_POST['motorista'];

		
		$aData = explode(" - ", $_POST['daterange']);
		$aData[0] = explode('/', $aData[0]);
		$aData[0] = $aData[0][2].'-'.$aData[0][1].'-'.$aData[0][0];
		$aData[1] = explode('/', $aData[1]);
		$aData[1] = $aData[1][2].'-'.$aData[1][1].'-'.$aData[1][0];
		//Conferir se há um período entrelaçado com essa data{
			$endosso = mysqli_fetch_all(
				query(
					"SELECT * FROM endosso
						WHERE endo_tx_status = 'ativo'
							AND endo_nb_entidade = ".$_POST['motorista']."
							AND (
								'".$aData[0]."' BETWEEN endo_tx_de AND endo_tx_ate
								OR '".$aData[1]."' BETWEEN endo_tx_de AND endo_tx_ate
							)
						LIMIT 1"
				),
				MYSQLI_ASSOC
			);

			if(!empty($endosso)){
				$endosso = $endosso[0];
				$endosso['endo_tx_de'] = explode('-', $endosso['endo_tx_de']);
				$endosso['endo_tx_de'] = $endosso['endo_tx_de'][2].'/'.$endosso['endo_tx_de'][1].'/'.$endosso['endo_tx_de'][0];

				$endosso['endo_tx_ate'] = explode('-', $endosso['endo_tx_ate']);
				$endosso['endo_tx_ate'] = $endosso['endo_tx_ate'][2].'/'.$endosso['endo_tx_ate'][1].'/'.$endosso['endo_tx_ate'][0];

				set_status('ERRO: Possui um endosso de '.$endosso['endo_tx_de'].' até '.$endosso['endo_tx_ate'].'.');
				layout_abono();
				exit;
			}
		//}

		$begin = new DateTime($aData[0]);
		$end = new DateTime($aData[1]);

		$a=carregar('entidade',$_POST['motorista']);
		
		for ($i = $begin; $i <= $end; $i->modify('+1 day')) {

			$sqlRemover = query("SELECT * FROM abono WHERE abon_tx_data = '".$i->format("Y-m-d")."' AND abon_tx_matricula = '$a[enti_tx_matricula]' AND abon_tx_status = 'ativo'");
			while ($aRemover = carrega_array($sqlRemover)) {
				remover('abono', $aRemover['abon_nb_id']);
			}

			$aDetalhado = diaDetalhePonto($a['enti_tx_matricula'], $i->format("Y-m-d"));

			$abono = calcularAbono($aDetalhado['diffSaldo'], $_POST['abono']);


			$campos = ['abon_tx_data', 'abon_tx_matricula', 'abon_tx_abono', 'abon_nb_motivo', 'abon_tx_descricao', 'abon_nb_userCadastro', 'abon_tx_dataCadastro', 'abon_tx_status'];
			$valores = [$i->format("Y-m-d"), $a['enti_tx_matricula'], $abono, $_POST['motivo'], $_POST['descricao'], $_SESSION['user_nb_id'], date("Y-m-d H:i:s"), 'ativo'];

			inserir('abono', $campos, $valores);
		}

		$_POST['busca_motorista'] = $_POST['motorista'];

		index();
		exit;
	}

	function layout_abono(){

		cabecalho('Cadastro Abono');

		$c[] = combo_net('Motorista*:','motorista',$_POST['busca_motorista']?? '',4,'entidade','',' AND enti_tx_tipo = "Motorista"','enti_tx_matricula');
		$c[] = campo('Data(s)*:','daterange', ($_POST['daterange']?? ''),3);
		$c[] = campo_hora('Abono*: (hh:mm)','abono', ($_POST['abono']?? ''),3);
		$c2[] = combo_bd('Motivo*:','motivo', ($_POST['motivo']?? ''),4,'motivo','',' AND moti_tx_tipo = "Abono"');
		$c2[] = textarea('Justificativa:','descricao', ($_POST['descricao']?? ''),12);
		
		//BOTOES
		$b[] = botao("Voltar",'index');
		$b[] = botao("Gravar",'cadastra_abono','','','','','btn btn-success');
		
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
						"daysOfWeek": ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sab"],
						"monthNames": ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"],
						"firstDay": 1
					},
				}, function(start, end, label) {
					// console.log("A new date selection was made: " + start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD'));
				});
			});
		</script>
		<?
	}

	function layout_ajuste(){
		global $CONTEX;
		echo 
			'<form action="https://braso.mobi'.$CONTEX['path'].'/ajuste_ponto" name="form_ajuste_ponto" method="post">
				<input type="hidden" name="id" value="'.$_POST['id'].'">
				<input type="hidden" name="data" value="'.$_POST['data'].'">
				<input type="hidden" name="data_de" value="'.$_POST['data_de'].'">
				<input type="hidden" name="data_ate" value="'.$_POST['data_ate'].'">
			</form>
			<script>
				document.form_ajuste_ponto.submit();
			</script>'
		;
		exit;
	}

	function excluir_ponto(){
		$a=carregar('ponto', (int)$_POST['id']);
		remover_ponto('ponto', (int)$_POST['id'],$_POST['just']);
		
		$_POST['id'] = $_POST['idEntidade'];
		$_POST['data'] = substr($a['pont_tx_data'],0, -9);
		$_POST['busca_data'] = $a['pont_tx_data'];


		layout_ajuste();
		exit;
	}

	function maxDirecaoContinua($dados) {
		$mdc = 0; // duração máxima contínua
		$jornada_inicio = null; // hora do início da jornada
		$ultima_batida = null; // hora da última batida

		for ($i = 0; $i < count($dados) - 1; $i++) {
			$atual = $dados[$i];
			$proximo = $dados[$i + 1];

			/* Ignora os intervalos entre:
				- Início de Refeição(3) e Fim de Refeição(4);
				- Início de Espera(5) e Fim de Espera(6);
				- Início de Descanso(7) e Fim de Descanso(8); 
				- Início de Repouso(9) e Fim de Repouso(10); 
				- Início de Repouso Embarcado(11) e Fim de Repouso Embarcado(12); 
				- Fim de Jornada(2) e Início de Jornada(1)
			*/
			if(in_array([$atual[1], $proximo[1]], [[3,4], [5,6], [7,8], [9,10], [11,12], [2,1]])){
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

	function operarHorarios(array $horarios, string $operacao): string{
		//Horários com formato de rH:i. Ex.: 00:04, 05:13, -01:12.
		//$Operação

		if(count($horarios) == 0 || !in_array($operacao, ['+', '-', '*', '/'])){
			return 0;
		}

		$negative = ($horarios[0][0] == '-');
		$result = explode(':', $horarios[0]);
		$result = intval($result[0]*60)+($negative?-1:1)*intval($result[1]);

		for($f = 1; $f < count($horarios); $f++){
			if(empty($horarios[$f])){
				continue;
			}
			$negative = ($horarios[$f][0] == '-');
			$horarios[$f] = str_replace(['<b>', '</b>'], ['', ''], $horarios[$f]);
			$horarios[$f] = explode(':', $horarios[$f]);
			$horarios[$f] = intval($horarios[$f][0]*60)+($negative?-1:1)*intval($horarios[$f][1]);
			switch($operacao){
				case '+':
					$result += $horarios[$f];
				break;
				case '-':
					$result -= $horarios[$f];
				break;
				case '*':
					$result *= $horarios[$f];
				break;
				case '/':
					$result /= $horarios[$f];
				break;
			}
		}

		$result = 
			(($result < 0)?'-':'').
			sprintf('%02d:%02d', abs(intval($result/60)), abs(intval($result%60)));

		return $result;
	}

	function somarHorarios(array $horarios): string{
		return operarHorarios($horarios, '+');	
	}

	function verificaTempoMdc($tempo1 = '00:00', $tempoDescanso = '00:00') {
		// Verifica se os parâmetros são strings e possuem o formato correto
		if (!is_string($tempo1) || !is_string($tempoDescanso) || !preg_match('/^\d{2}:\d{2}$/', $tempo1) || !preg_match('/^\d{2}:\d{2}$/', $tempoDescanso)) {
			return '';
		}
		// $datetime1 = new DateTime('2000-01-01 '.$tempo1);
		// $datetimeDescanso = new DateTime('2000-01-01 '.$tempoDescanso);
		$datetime2 = '05:30';
		$datetime3 = '03:00';
		$datetimeDescanso1 = '00:30';
		$datetimeDescanso2 = '00:15';

		$alertaDescanso = '';

		if($tempo1 > $datetime2){
			if($tempoDescanso < $datetimeDescanso1){
				$alertaDescanso = "<i style='color:orange;' title='Descanso de 00:30 não respeitado' class='fa fa-warning'></i>";
			}
			return "<a style='white-space: nowrap;'>$alertaDescanso<i style='color:orange;' title='Tempo excedido de 05:30' class='fa fa-warning'></i></a>&nbsp;".$tempo1;
		}elseif($tempo1 > $datetime3){
			if($tempoDescanso < $datetimeDescanso2){
				$alertaDescanso = "<i style='color:orange;' title='Descanso de 00:15 não respeitado' class='fa fa-warning'></i>";
			}
			return "<a style='white-space: nowrap;'>$alertaDescanso</a>&nbsp;".$tempo1;
		}else{
			return $tempo1;
		}
	}

	function verificaLimiteTempo(string $tempoEfetuado, string $limite) {
		// Verifica se os parâmetros são strings e possuem o formato correto
		if (!preg_match('/^\d{2}:\d{2}$/', $tempoEfetuado) || !preg_match('/^\d{2}:\d{2}$/', $limite)) {
			return '';
		}
		$datetime1 = new DateTime('2000-01-01 '.$tempoEfetuado);
		$datetime2 = new DateTime('2000-01-01 '.$limite);

		if($datetime1 > $datetime2){
			return "<a style='white-space: nowrap;'><i style='color:orange;' title='Tempo excedido de $limite' class='fa fa-warning'></i></a>&nbsp;".$tempoEfetuado;
		}else{
			return $tempoEfetuado;
		}
	}

	function verificaTolerancia($saldoDiario, $data, $idMotorista) {
		$saldoDiario = str_replace(['<b>', '</b>'], ['', ''], $saldoDiario);
		date_default_timezone_set('America/Recife');
		$sqlTolerancia = query(
			"SELECT en.enti_nb_parametro, par.para_tx_tolerancia
				FROM `entidade` en
				INNER JOIN parametro par ON en.enti_nb_parametro = par.para_nb_id
				WHERE en.enti_nb_id = '".$idMotorista."'");
		
		$toleranciaArray = carrega_array($sqlTolerancia);

		$tolerancia = (empty($toleranciaArray['para_tx_tolerancia']))? 0: $toleranciaArray['para_tx_tolerancia'];
		$tolerancia = intval($tolerancia);

		$saldoDiario = explode(':', $saldoDiario);
		$saldoEmMinutos = intval($saldoDiario[0])*60+($saldoDiario[0][0] == '-'? -1: 1)*intval($saldoDiario[1]);

		if($saldoEmMinutos < -($tolerancia)){
			$cor = 'red';
		}elseif($saldoEmMinutos > $tolerancia){
			$cor = 'green';
		}else{
			$cor = 'blue';
		}

		if ($_SESSION['user_tx_nivel'] == 'Motorista') {
			$retorno = '<span><i style="color:'.$cor.';" class="fa fa-circle"></i></span>';
		} else {
			$endossado = mysqli_fetch_all(
				query(
					'SELECT * FROM endosso 
						JOIN entidade ON endo_tx_matricula = enti_tx_matricula
						WHERE \''.$data.'\' BETWEEN endo_tx_de AND endo_tx_ate
							AND enti_nb_id = '.$idMotorista.'
							AND endo_tx_status != "inativo";'
				), 
				MYSQLI_ASSOC
			);
			if(count($endossado) > 0){
				$retorno = '<a title="Ajuste de Ponto (endossado)" onclick="avisar_ponto_endossado('.$idMotorista.',\''.$data.'\')"><i style="color:'.$cor.';" class="fa fa-circle">(E)</i></a>';
			}else{
				$retorno = '<a title="Ajuste de Ponto" href="#" onclick="ajusta_ponto('.$idMotorista.',\''.$data.'\')"><i style="color:'.$cor.';" class="fa fa-circle"></i></a>';
			}
		}

		
		echo 
			'<script>
				function ajusta_ponto(motorista, data) {
					document.form_ajuste_ponto.id.value = motorista;
					document.form_ajuste_ponto.data.value = data;
					document.form_ajuste_ponto.submit();
				}
				function avisar_ponto_endossado(motorista, data){
					alert("Dia já endossado.");
					document.form_ajuste_ponto.id.value = motorista;
					document.form_ajuste_ponto.data.value = data;
					document.form_ajuste_ponto.submit();
				}
			</script>'
		;

		return $retorno;
	}

	function ordenar_horarios($inicio, $fim, $ehEspera = false, $ehEsperaRepouso = false) {
		if(empty($inicio) && empty($fim)){
			return [
				"horariosOrdenados" => [],
				"pares" => [],
				"totalIntervalo" => "00:00",
				"icone" => "",
				'paresAdicionalNot' => '00:00',
				'totalIntervaloAdicionalNot' => '00:00'
			];
		}
		// Inicializa o array resultante e o array de indicação
		$horarios = [];
		$origem = [];

		// Adiciona os horários do array de início e marca a origem como "inicio"
		foreach ($inicio as $h){
			$horarios[] = $h;
			$origem[] = "inicio";
		}

		// Adiciona os horários do array de fim e marca a origem como "fim"
		foreach ($fim as $h){
			$horarios[] = $h;
			$origem[] = "fim";
		}

		// Ordena o array de horários
		array_multisort($horarios, SORT_ASC, $origem, SORT_DESC);

		// Cria um array associativo para cada horário com sua origem correspondente
		$horarios_com_origem = [];
		for ($i = 0; $i < count($horarios); $i++) {
			$horarios_com_origem[] = [
				"horario" => $horarios[$i],
				"origem" => $origem[$i]
			];
		}

		$dtInicioAdicionalNot = new DateTime(substr($horarios[0],0,10).' 05:00');
		$dtFimAdicionalNot = new DateTime(substr($horarios[0],0,10).' 22:00');
		$totalIntervalo = new DateTime(substr($horarios[0],0,10).' 00:00');
		$totalIntervaloAdicionalNot = new DateTime(substr($horarios[0],0,10).' 00:00');
		$inicio_atual = null;
		$inicio_anterio = null;
		$pares = [];
		$paresParaRepouso = [];

		$temErroJornada = False;

		foreach ($horarios_com_origem as $item) {
			if ($item["origem"] == "inicio") {
				if($inicio_atual !== null){
					$pares[] = ["inicio" => date("H:i", strtotime($inicio_atual)), "fim" => ''];
					$temErroJornada = True;
				}
				$inicio_atual = $item["horario"];
			} elseif ($item["origem"] == "fim" && $inicio_atual != null) {
				$hInicio = new DateTime($inicio_atual);
				$hFim = new DateTime($item["horario"]);
				
				$interval = $hInicio->diff($hFim);

				// se intervalo > 2 horas && ehEspera true
				if($ehEspera && (($interval->h*60) + $interval->i > 120)){
					$paresParaRepouso[] = ["inicio" => date("H:i", strtotime($inicio_atual)), "fim" => date("H:i", strtotime($item["horario"]))];
				}else{
					$totalIntervalo->add($interval);
					$interval = $interval->format("%H:%I");
				}
				$pares[] = ["inicio" => date("H:i", strtotime($inicio_atual)), "fim" => date("H:i", strtotime($item["horario"])), "intervalo" => $interval];

				// VERIFICA HORA SE HÁ HORA EXTRA ACIMA DAS 22:00
				if($hFim > $dtFimAdicionalNot){
					$fimExtra = date("H:i", strtotime($item["horario"]));
					if($hInicio > $dtFimAdicionalNot){
						$hInicioAdicionalNot = $hInicio; //CRIA UMA NOVA VARIAVEL PARA NO CASO DAS 05:00
						$incioExtra = date("H:i", strtotime($inicio_atual));
					}else{
						$hInicioAdicionalNot = new DateTime(substr($horarios[0],0,10).' 22:00');
						$incioExtra = '22:00';
					}

					$intervalAdicionalNot = $hInicioAdicionalNot->diff($hFim);
					$totalIntervaloAdicionalNot->add($intervalAdicionalNot);
					
					$intervalAdicionalNot = $intervalAdicionalNot->format("%H:%I");
					

					$paresAdicionalNot[] = ["inicio" => $incioExtra, "fim" => $fimExtra, "intervalo" => $intervalAdicionalNot];
				}

				// VERIFICA HORA SE HÁ HORA EXTRA ABAIXO DAS 05:00
				if($hInicio < $dtInicioAdicionalNot){
					$incioExtra = date("H:i", strtotime($inicio_atual));
					if($hFim > $dtInicioAdicionalNot){
						$hFim = new DateTime(substr($horarios[0],0,10).' 05:00');
						$fimExtra = '05:00';
					}else{
						$fimExtra = date("H:i", strtotime($item["horario"]));
					}

					$intervalAdicionalNot = $hInicio->diff($hFim);
					$totalIntervaloAdicionalNot->add($intervalAdicionalNot);
					
					$intervalAdicionalNot = $intervalAdicionalNot->format("%H:%I");;
					

					$paresAdicionalNot[] = ["inicio" => $incioExtra, "fim" => $fimExtra, "intervalo" => $intervalAdicionalNot];
				}

				$inicio_atual = null;
			} elseif ($item["origem"] == "fim" && $inicio_atual == null) {
				// Se encontrarmos um fim sem um início correspondente, armazenamos o horário sem par
				$sem_fim[] = $item["horario"];
			}
		}
		if($item["origem"] == 'inicio'){
			$pares[] = ["inicio" => date("H:i", strtotime($item['horario'])), "fim" => ''];
		}

		$tooltip = '';
		for($f = 0; $f < count($pares); $f++){
			$tooltip .= 'Início: '." ".$pares[$f]['inicio']."\n";
			$tooltip .= 'Fim: '." ".$pares[$f]['fim']."\n\n";
		}
		if((count($inicio) == 0 && count($fim) == 0) || $tooltip == ''){
			$iconeAlerta = '';
		}elseif(count($inicio) != count($fim) || count($horarios_com_origem)/2 != (count($pares)) || $temErroJornada){ 
			$iconeAlerta = "<a><i style='color:red;' title='$tooltip' class='fa fa-info-circle'></i></a>";
		}elseif($ehEsperaRepouso){
			$iconeAlerta = "<a><i style='color:#99ff99;' title='$tooltip' class='fa fa-info-circle'></i></a>";
		}else{
			$iconeAlerta = "<a><i style='color:green;' title='$tooltip' class='fa fa-info-circle'></i></a>";
		}
		

		$pares_horarios = [
			'horariosOrdenados' => $horarios_com_origem,
			'pares' => $pares,
			'totalIntervalo' => $totalIntervalo->format('H:i'),
			'icone' => $iconeAlerta
		];
		
		if(count($horarios_com_origem) > 2){
			$totalInterjornada = new DateTime(substr($horarios[0],0,10).' 00:00');
			for ($i = 1; $i < count($horarios_com_origem); $i++) { 
				$horarioVez = $horarios_com_origem[$i];
				$horarioAnterior = $horarios_com_origem[($i-1)];
				if($horarioVez['origem'] == 'inicio' && $horarioAnterior['origem'] == 'fim'){
					$dtInicio = new DateTime($horarioVez['horario']);
					$dtFim = new DateTime($horarioAnterior['horario']);
					
					$intervalInterjornada = $dtFim->diff($dtInicio);

					$totalInterjornada->add($intervalInterjornada);
					
				}
			}

			$pares_horarios['interjornada'] = $totalInterjornada->format('H:i');

		}

		if(count($paresParaRepouso) > 0){
			$pares_horarios['paresParaRepouso'] = $paresParaRepouso;
		}
		if(isset($paresAdicionalNot) && count($paresAdicionalNot) > 0){
			$pares_horarios['paresAdicionalNot'] = $paresAdicionalNot;
			$pares_horarios['totalIntervaloAdicionalNot'] = $totalIntervaloAdicionalNot->format('H:i');
		}else{
			$pares_horarios['paresAdicionalNot'] = '00:00';
			$pares_horarios['totalIntervaloAdicionalNot'] = '00:00';
		}
		
		// Retorna o array de horários com suas respectivas origens
		return $pares_horarios;
	}

	function dateTimeToSecs(DateTime $dateTime, $baseDate = ''): int{
		if(empty($baseDate)){
			$baseDate = DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 00:00:00');
		}
    	$res = date_diff($baseDate, $dateTime);
		$monthDays = [31, 28+($res->y%4 == 0? 1: 0), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $res = 
        	($res->invert? 1:-1)*
			(
				$res->y*24*60*60*30*365+
				$res->m*24*60*60*$monthDays[$res->m]+
				$res->d*24*60*60+
				$res->h*60*60+
				$res->i*60+
				$res->s
			);
        return $res;
    }

	function calcJorPre($data, $jornadas, $abono = null){
		//$jornadas = ['sabado' => string, 'semanal' => string, 'feriado' => bool]

		if (date('w', strtotime($data)) == '0' || $jornadas['feriado']) { 	//DOMINGOS OU FERIADOS
			$jornadaPrevista = '00:00';
		}elseif (date('w', strtotime($data)) == '6') { 						//SABADOS
			$jornadaPrevista = $jornadas['sabado'];
		} else {															//DIAS DE SEMANA
			$jornadaPrevista = $jornadas['semanal'];
		}

		$jornadaPrevistaOriginal = $jornadaPrevista;
		if($abono !== null){
			$jornadaPrevista = (new DateTime($data." ".$abono))->diff(new DateTime($data." ".$jornadaPrevista));
			$jornadaPrevista = $jornadaPrevista->format("%H:%I");
		}else{
			$jornadaPrevista = (new DateTime($data." ".$jornadaPrevista));
			$jornadaPrevista = $jornadaPrevista->format("H:i");
		}

		return [$jornadaPrevistaOriginal, $jornadaPrevista];
	}

	function diaDetalhePonto($matricula, $data): array{
		global $totalResumo, $contagemEspera;
		setlocale(LC_ALL, 'pt_BR.utf8');

		$aRetorno = [
			'data' => data($data),
			'diaSemana' => strtoupper(substr(pegarDiaDaSemana($data), 0, 3)),
			'inicioJornada' => [],
			'inicioRefeicao' => [],
			'fimRefeicao' => [],
			'fimJornada' => [],
			'diffRefeicao' => '',
			'diffEspera' => '',
			'diffDescanso' => '',
			'diffRepouso' => '',
			'diffJornada' => '',
			'jornadaPrevista' => '',
			'diffJornadaEfetiva' => '',
			'maximoDirecaoContinua' => '',
			'intersticio' => '',
			'he50' => '',
			'he100' => '',
			'adicionalNoturno' => '',
			'esperaIndenizada' => '',
			'diffSaldo' => ''
		];
		$aMotorista = carrega_array(query(
			"SELECT * FROM entidade
				LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
				LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id
				WHERE enti_tx_status != 'inativo' 
					AND enti_tx_matricula = '$matricula' 
				LIMIT 1"
		));

		if(!isset($aMotorista['enti_nb_parametro']) || empty($aMotorista['enti_nb_parametro'])){
			$aMotorista['enti_nb_parametro'] = $aMotorista['empr_nb_parametro'];
		}

		if ($aMotorista['enti_nb_empresa'] && $aMotorista['empr_nb_cidade'] && $aMotorista['cida_nb_id'] && $aMotorista['cida_tx_uf']) {
			$extraFeriado = " AND ((feri_nb_cidade = '$aMotorista[cida_nb_id]' OR feri_tx_uf = '$aMotorista[cida_tx_uf]') OR ((feri_nb_cidade = '' OR feri_nb_cidade IS NULL) AND (feri_tx_uf = '' OR feri_tx_uf IS NULL)))";
		}else{
			$extraFeriado = "";
		}

		$aParametro = carregar('parametro', $aMotorista['enti_nb_parametro']);
		$alertaJorEfetiva = ((isset($aParametro['para_tx_acordo']) && $aParametro['para_tx_acordo'] == 'sim')? '12:00': '10:00');

		$queryFeriado = query(
			"SELECT feri_tx_nome FROM feriado 
				WHERE feri_tx_data LIKE '".$data."%' 
					AND feri_tx_status != 'inativo' ".$extraFeriado
		);
		$stringFeriado = '';
		while ($row = carrega_array($queryFeriado)) {
			$stringFeriado .= $row[0]."\n";
		}

		$tiposRegistrados = [];
		$tipos = [
			'1'  => 'inicioJornada',
			'2'  => 'fimJornada',
			'3'  => 'inicioRefeicao',
			'4'  => 'fimRefeicao',
			'5'  => 'inicioEspera',
			'6'  => 'fimEspera',
			'7'  => 'inicioDescanso',
			'8'  => 'fimDescanso',
			'9'  => 'inicioRepouso',
			'10' => 'fimRepouso',
			'11' => 'inicioRepousoEmb',
			'12' => 'fimRepousoEmb'
		];
		$registros = [];
		foreach(array_values($tipos) as $tipo){
			$registros[$tipo] = [];
		}

		$pontosDia = [];
		$sql = query(
			"SELECT * FROM ponto 
				WHERE pont_tx_status != 'inativo' 
					AND pont_tx_matricula = '$matricula' 
					AND pont_tx_data LIKE '$data%' 
				ORDER BY pont_tx_data ASC"
		);
		while($ponto = carrega_array($sql)){
			$pontosDia[] = $ponto;
		}

		if(count($pontosDia) > 0){
			if($pontosDia[count($pontosDia)-1]['pont_tx_tipo'] != '2'){ //Se o último registro do dia != fim de jornada => há uma jornada aberta que seguiu para o outro dia
				$diaSeguinte = (new DateTime($data))->add(DateInterval::createFromDateString('1 day'));
				$diaSeguinte = $diaSeguinte->format('Y-m-d');
				
				$pontosDiaSeguinte = mysqli_fetch_all(
					query(
						"SELECT macroponto.macr_tx_nome, ponto.*, (1) as diaSeguinte FROM ponto 
						JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
						WHERE pont_tx_status != 'inativo' 
							AND pont_tx_matricula = '$matricula' 
							AND pont_tx_data LIKE '".$diaSeguinte."%'
						ORDER BY pont_tx_data ASC;"
					),
					MYSQLI_ASSOC
				);

				for($f = 0; $f < count($pontosDiaSeguinte); $f++){

					//Se encontrar um fim de jornada, ignora os pontos que estiverem depois dele, pois corresponderão a uma próxima jornada.
					if($pontosDiaSeguinte[$f]['pont_tx_tipo'] == '2'){
						$pontosDiaSeguinte = array_slice($pontosDiaSeguinte, 0, $f+1);
						break;
					}

					//Não pega os pontos do dia seguinte caso tenha um início de jornada sem ter fechado o anterior. Isso impede dos mesmos pontos ficarem repetidos em dois dias distintos.
					if($pontosDiaSeguinte[$f]['pont_tx_tipo'] == '1'){
						$pontosDiaSeguinte = [];
						break;
					}
				}
				$pontosDia = array_merge($pontosDia, $pontosDiaSeguinte);
			}
			
			if($pontosDia[0]['pont_tx_tipo'] != '1'){ //Se o 1° registro != início de jornada => É uma jornada que veio do dia anterior
				while(count($pontosDia) > 0){
					if($pontosDia[0]['pont_tx_tipo'] != '1'){
						array_shift($pontosDia);
					}else{
						break;
					}
				}
			}
		}
		foreach($pontosDia as $ponto){
			$tiposRegistrados[] = [date("H:i", strtotime($ponto['pont_tx_data'])), $ponto['pont_tx_tipo']];
			if(!isset($registros[$tipos[$ponto['pont_tx_tipo']]])){
				$registros[$tipos[$ponto['pont_tx_tipo']]] = [];
			}
			$registros[$tipos[$ponto['pont_tx_tipo']]][] = $ponto['pont_tx_data'];
		}

		$registros['jornadaCompleto']  = ordenar_horarios($registros['inicioJornada'],  $registros['fimJornada']);		/* $jornadaOrdenado */
		$registros['refeicaoCompleto'] = ordenar_horarios($registros['inicioRefeicao'], $registros['fimRefeicao']);		/* $refeicaoOrdenada */
		$registros['esperaCompleto']   = ordenar_horarios($registros['inicioEspera'],   $registros['fimEspera'], True);	/* $esperaOrdenada */
		$registros['descansoCompleto'] = ordenar_horarios($registros['inicioDescanso'], $registros['fimDescanso']);		/* $descansoOrdenado */
		$registros['repousoCompleto']  = ordenar_horarios($registros['inicioRepouso'], $registros['fimRepouso']);		/* $repousoOrdenado */

		
		if (isset($registros['esperaCompleto']['paresParaRepouso']) && !empty($registros['esperaCompleto']['paresParaRepouso'])){
			$paresParaRepouso = $registros['esperaCompleto']['paresParaRepouso'];
			// unset($registros['esperaCompleto']['paresParaRepouso']);
			for ($i = 0; $i < count($paresParaRepouso); $i++) {
				$registros['repousoPorEspera']['inicioRepouso'][] 	= $data.' '.$paresParaRepouso[$i]['inicio'].':00';	/*$aDataHorainicioRepouso*/
				$registros['repousoPorEspera']['fimRepouso'][] 		= $data.' '.$paresParaRepouso[$i]['fim'].':00';		/*$aDataHorafimRepouso*/
			}
			$registros['repousoPorEspera']['repousoCompleto'] = ordenar_horarios($registros['repousoPorEspera']['inicioRepouso'], $registros['repousoPorEspera']['fimRepouso'],false,true);
		}else{
			$registros['repousoPorEspera']['repousoCompleto'] = ordenar_horarios([], []);
		}
		$registros['repousoCompleto']['totalIntervalo'] = operarHorarios([$registros['repousoCompleto']['totalIntervalo'], $registros['repousoPorEspera']['repousoCompleto']['totalIntervalo']], '+');
		$registros['repousoCompleto']['icone'] .= $registros['repousoPorEspera']['repousoCompleto']['icone'];
		
		$aRetorno['diffRefeicao'] = $registros['refeicaoCompleto']['icone'].$registros['refeicaoCompleto']['totalIntervalo'];
		$aRetorno['diffEspera']   = $registros['esperaCompleto']['icone'].$registros['esperaCompleto']['totalIntervalo'];
		$aRetorno['diffDescanso'] = $registros['descansoCompleto']['icone'].$registros['descansoCompleto']['totalIntervalo'];
		$aRetorno['diffRepouso']  = $registros['repousoCompleto']['icone'].$registros['repousoCompleto']['totalIntervalo'];

		$contagemEspera += count($registros['esperaCompleto']['pares']);

		$aAbono = carrega_array(
			query(
				"SELECT * FROM abono, motivo, user 
					WHERE abon_tx_status != 'inativo' 
						AND abon_nb_userCadastro = user_nb_id 
						AND abon_tx_matricula = '$matricula' 
						AND abon_tx_data = '$data' 
						AND abon_nb_motivo = moti_nb_id
					ORDER BY abon_nb_id DESC 
					LIMIT 1"
			)
		);
		
		$aRetorno['diffJornada'] = $registros['jornadaCompleto']['icone'].$registros['jornadaCompleto']['totalIntervalo'];

		//JORNADA PREVISTA{
			$jornadas = [
				'sabado' => $aMotorista['enti_tx_jornadaSabado'],
				'semanal'=> $aMotorista['enti_tx_jornadaSemanal'],
				'feriado'=> ($stringFeriado != ''? True: null)
			];

			[$jornadaPrevistaOriginal, $jornadaPrevista] = calcJorPre($data, $jornadas, $aAbono['abon_tx_abono']);

			$aRetorno['jornadaPrevista'] = $jornadaPrevista;
			if($jornadas['feriado'] == True){
				$iconeFeriado =  " <a><i style='color:orange;' title='$stringFeriado' class='fa fa-info-circle'></i></a>";
				$aRetorno['diaSemana'] .= $iconeFeriado;
			}
		//}

		//JORNADA EFETIVA{
			$jornadaIntervalo = new DateTime($registros['jornadaCompleto']['totalIntervalo']);

			$totalNaoJornada = [
				$registros['refeicaoCompleto']['totalIntervalo']
			];

			//Ignorar intervalos que tenham sido marcados para ignorar no parâmetro{
				if(!empty($aMotorista['enti_nb_parametro']) && !empty($aParametro['para_tx_ignorarCampos'])){
					$campos = ['espera', 'descanso', 'repouso'/*, 'repousoEmbarcado'*/];
					foreach($campos as $campo){
						if(is_bool(strpos($aParametro['para_tx_ignorarCampos'], $campo))){
							$totalNaoJornada[] = $registros[$campo.'Completo']['totalIntervalo'];
						}
					}
				}else{
					$totalNaoJornada = [
						$registros['refeicaoCompleto']['totalIntervalo'],
						$registros['esperaCompleto']['totalIntervalo'],
						$registros['descansoCompleto']['totalIntervalo'],
						$registros['repousoCompleto']['totalIntervalo']
					];
				}
			//}
			
			//SOMATORIO DE TODAS AS ESPERAS

			$totalNaoJornada = new DateTime(somarHorarios($totalNaoJornada));

			$jornadaEfetiva = $jornadaIntervalo->diff($totalNaoJornada); //$diffJornadaEfetiva
			$jornadaEfetiva = DateTime::createFromFormat('H:i', $jornadaEfetiva->format("%H:%I"));
			$aRetorno['diffJornadaEfetiva'] = verificaLimiteTempo($jornadaEfetiva->format('H:i'), $alertaJorEfetiva);
		//}

		//CÁLCULO DE INSTERTÍCIO{
			if (isset($registros['inicioJornada']) && count($registros['inicioJornada']) > 0){

				$ultimoFimJornada = carrega_array(query(
					"SELECT pont_tx_data FROM ponto
						WHERE pont_tx_status != 'inativo'
							AND pont_tx_tipo = 2
							AND pont_tx_matricula = '$matricula'
							AND pont_tx_data < '".$registros['inicioJornada'][0]."'
						ORDER BY pont_tx_data DESC
						LIMIT 1"
				))[0];
				if(!empty($ultimoFimJornada)){
					$ultimoFimJornada = DateTime::createFromFormat('Y-m-d H:i:s', $ultimoFimJornada);
					
					$intersticioDiario = (new DateTime($registros['inicioJornada'][0]))->diff($ultimoFimJornada);
					
					// Obter a diferença total em minutos
					$minInterDiario = (
						$intersticioDiario->y*60*24*30*365+
						$intersticioDiario->m*60*24*30+
						$intersticioDiario->d*60*24+
						$intersticioDiario->h*60+
						$intersticioDiario->i
					);

					// Calcular as horas e minutos

					$intersticio = sprintf("%02d:%02d", floor($minInterDiario / 60), $minInterDiario % 60); // Formatar a string no formato H:I

					$totalIntersticio = somarHorarios(
						[$intersticio, $totalNaoJornada->format("H:i")]
					);

					$icone = '';
					if ($totalIntersticio < sprintf("%0".(strlen($totalIntersticio)-3)."d:%02d", "11","00")){ // < 11 horas
						$restante = operarHorarios([sprintf("%0".(strlen($totalIntersticio)-3)."d:%02d", "11","00"), $totalIntersticio], '-');
						$icone .= "<a><i style='color:red;' title='Interstício Total de 11:00 não respeitado, faltaram ".$restante."' class='fa fa-warning'></i></a>";
					}
					if ($minInterDiario < (8*60)){ // < 8 horas
						$icone .= "<a><i style='color:red;' title='O mínimo de 08:00h ininterruptas no primeiro período, não respeitado.' class='fa fa-warning'></i></a>";
					}

					$aRetorno['intersticio'] = $icone.$totalIntersticio;
				}else{
					$aRetorno['intersticio'] = '00:00';
				}
			}
		//}

		//CALCULO SALDO{
			$saldoDiario = (date_diff(DateTime::createFromFormat('H:i', $jornadaPrevista), $jornadaEfetiva))->format("%r%H:%I");
			$aRetorno['diffSaldo'] = $saldoDiario;
		//}

		//CALCULO ESPERA INDENIZADA{
			$intervaloEsp = somarHorarios([$registros['esperaCompleto']['totalIntervalo'], $registros['repousoPorEspera']['repousoCompleto']['totalIntervalo']]);
			$indenizarEspera = ($intervaloEsp >= '02:00');

			if ($saldoDiario[0] == '-'){
				if($intervaloEsp > substr($saldoDiario, 1)){
					$transferir = substr($saldoDiario, 1);
				}else{
					$transferir = $intervaloEsp;
				}	
				$saldoDiario = operarHorarios([$saldoDiario, $transferir], '+');
				$aRetorno['diffSaldo'] = $saldoDiario;
				$intervaloEsp = operarHorarios([$intervaloEsp, $transferir], '-');
			}

			if($indenizarEspera){
				$esperaIndenizada = $intervaloEsp;
			}else{
				$esperaIndenizada = '00:00';
			}

			$aRetorno['esperaIndenizada'] = $esperaIndenizada;
		//}

		//INICIO ADICIONAL NOTURNO
			// $jornadaNoturno = $registros['jornadaCompleto']['totalIntervaloAdicionalNot'];
			// $refeicaoNoturno = $registros['refeicaoCompleto']['totalIntervaloAdicionalNot'];
			// $esperaNoturno = $registros['esperaCompleto']['totalIntervaloAdicionalNot'];
			// $descansoNoturno = $registros['descansoCompleto']['totalIntervaloAdicionalNot'];
			// $repousoNoturno = $registros['repousoCompleto']['totalIntervaloAdicionalNot'];

			$intervalosNoturnos = somarHorarios([
				$registros['refeicaoCompleto']['totalIntervaloAdicionalNot'], 
				$registros['esperaCompleto']['totalIntervaloAdicionalNot'], 
				$registros['descansoCompleto']['totalIntervaloAdicionalNot'], 
				$registros['repousoCompleto']['totalIntervaloAdicionalNot']
			]);

			$aRetorno['adicionalNoturno'] = operarHorarios([$registros['jornadaCompleto']['totalIntervaloAdicionalNot'], $intervalosNoturnos], '-');
		//FIM ADICIONAL NOTURNO
		
		//TOLERÂNCIA{
			$tolerancia = carrega_array(query(
				"SELECT parametro.para_tx_tolerancia FROM entidade 
					JOIN parametro ON enti_nb_parametro = para_nb_id 
					WHERE enti_nb_parametro = ".$aMotorista['enti_nb_parametro']."
					LIMIT 1;"
			))[0];
			$tolerancia = intval($tolerancia);
			
			$saldo = explode(':', $aRetorno['diffSaldo']);
			$saldo = intval($saldo[0])*60 + ($saldo[0][0] == '-'? -1: 1)*intval($saldo[1]);
			
			if($saldo >= -($tolerancia) && $saldo <= $tolerancia){
				$aRetorno['diffSaldo'] = '00:00';
				$saldo = 0;
			}
		//}

		//HORAS EXTRAS{
			$aRetorno['he100'] = '';
			if($aRetorno['diffSaldo'][0] != '-'){ 	//Se o saldo for positivo

				if($jornadas['feriado'] == True){
					$aRetorno['he100'] = $aRetorno['diffSaldo'];
					$aRetorno['he50'] = '00:00';
				}else{
					if(	(isset($aParametro["para_tx_HorasEXExcedente"]) && !empty($aParametro["para_tx_HorasEXExcedente"])) &&
						$aParametro["para_tx_HorasEXExcedente"] != '00:00' && 
						$aRetorno['diffSaldo'] >= $aParametro["para_tx_HorasEXExcedente"]
					){// saldo diário >= limite de horas extras 100%
						$aRetorno['he100'] = operarHorarios([$aRetorno['diffSaldo'], $aParametro["para_tx_HorasEXExcedente"]], '-');
					}else{
						$aRetorno['he100'] = '00:00';
					}
					$aRetorno['he50'] = operarHorarios([$aRetorno['diffSaldo'], $aRetorno['he100']], '-');
				}
			}
		//}
		

		//MÁXIMA DIREÇÃO CONTÍNUA{
			$aRetorno['maximoDirecaoContinua'] = verificaTempoMdc(
				maxDirecaoContinua($tiposRegistrados),
				$registros['descansoCompleto']['totalIntervalo']
			);
		//}

		//JORNADA MÍNIMA
			$dtJornada = new DateTime($data.' '.$jornadaEfetiva->format("H:i"));
			$dtJornadaMinima = new DateTime($data.' 06:00');

			$fezJorMinima = ($dtJornada >= $dtJornadaMinima);
		//FIM JORNADA MÍNIMA

		//ALERTAS{
			if((!isset($registros['inicioJornada'][0]) || $registros['inicioJornada'][0] == '') && $aRetorno['jornadaPrevista'] != '00:00'){
				$aRetorno['inicioJornada'][] 	= "<a><i style='color:red;' title='Batida início de jornada não registrada!' class='fa fa-warning'></i></a>";
			}
			if($fezJorMinima || count($registros['inicioJornada']) > 0){
				if(!isset($registros['fimJornada'][0]) || $registros['fimJornada'][0] == ''){
					$aRetorno['fimJornada'][] 	  = "<a><i style='color:red;' title='Batida fim de jornada não registrada!' class='fa fa-warning'></i></a>";
				}

				//01:00 DE REFEICAO{
					$maiorRefeicao = '00:00';
					if(count($registros['refeicaoCompleto']['pares']) > 0){
						for ($i = 0; $i < count($registros['refeicaoCompleto']['pares']); $i++) {
							if($maiorRefeicao < $registros['refeicaoCompleto']['pares'][$i]['intervalo']){
								$maiorRefeicao = $registros['refeicaoCompleto']['pares'][$i]['intervalo'];
							}
						}
					}

					$avisoRefeicao = '';
					if($maiorRefeicao > '02:00'){
						$avisoRefeicao = "<a><i style='color:orange;' title='Refeição com tempo máximo de 02:00h não respeitado.' class='fa fa-info-circle'></i></a>";
					}elseif ($dtJornada > $dtJornadaMinima && $maiorRefeicao < '01:00') {
						$avisoRefeicao = "<a><i style='color:red;' title='Refeição ininterrupta maior do que 01:00h não respeitado.' class='fa fa-warning'></i></a>";
					}
				//}

				if((!isset($registros['inicioRefeicao'][0]) || empty($aRetorno['inicioRefeicao'][0])) && $jornadaEfetiva->format("H:i") > "06:00"){
					$aRetorno['inicioRefeicao'][] = "<a><i style='color:red;' title='Batida início de refeição não registrada!' class='fa fa-warning'></i></a>";
				}else{
					$aRetorno['inicioRefeicao'][] = $avisoRefeicao;
				}

				if((!isset($registros['fimRefeicao'][0]) || empty($aRetorno['fimRefeicao'][0])) && ($jornadaEfetiva->format("H:i") > "06:00")){
					$aRetorno['fimRefeicao'][] 	  = "<a><i style='color:red;' title='Batida fim de refeição não registrada!' class='fa fa-warning'></i></a>";
				}else{
					$aRetorno['fimRefeicao'][] = $avisoRefeicao;
				}
				if(!empty($avisoRefeicao)){
					$aRetorno['diffRefeicao'] = $avisoRefeicao.' '.$aRetorno['diffRefeicao'];
				}
			}
			if (is_array($aAbono) && count($aAbono) > 0) {
				$warning = 
					"<a><i "
						."style='color:orange;' "
						."title='"
							."Jornada Original: ".str_pad($jornadaPrevistaOriginal, 2, '0', STR_PAD_LEFT).":00:00\n"
							."Abono: ".$aAbono['abon_tx_abono']."\n"
							."Motivo: ".$aAbono['moti_tx_nome']."\n"
							."Justificativa: ".$aAbono['abon_tx_descricao']."\n\n"
							."Registro efetuado por ".$aAbono['user_tx_login']." em ".data($aAbono['abon_tx_dataCadastro'], 1)."' "
						."class='fa fa-warning'></i>"
					."</a>&nbsp;"
				;
				$aRetorno['jornadaPrevista'] = $warning.$aRetorno['jornadaPrevista'];
			}
		//}

		foreach(['inicioJornada', 'fimJornada', 'inicioRefeicao', 'fimRefeicao'] as $campo){
			if(count($registros[$campo]) > 0 && !empty($registros[$campo][0])){
				$aRetorno[$campo] = $registros[$campo];
			}
		}

		if (count($registros['inicioEspera']) > 0 && count($registros['fimEspera']) > 0){
			$aRetorno['diffEspera']   = $registros['esperaCompleto']['icone'].$registros['esperaCompleto']['totalIntervalo'];
		}
		if (count($registros['inicioDescanso']) > 0 && count($registros['fimDescanso']) > 0){
			$aRetorno['diffDescanso'] = $registros['descansoCompleto']['icone'].$registros['descansoCompleto']['totalIntervalo'];
		}
		if (count($registros['inicioRepouso']) > 0 && count($registros['fimRepouso']) > 0){
			$aRetorno['diffRepouso']  = $registros['repousoCompleto']['icone'].$registros['repousoCompleto']['totalIntervalo'];
		}
		
		//LEGENDAS{
			if(!empty($registros['inicioJornada'])){
				$datas = 
					'("'.implode('", "', $registros['inicioJornada']).'"'
					.(!empty($registros['inicioRefeicao'])? ', "'.implode('", "', $registros['inicioRefeicao']).'"': '')
					.(!empty($registros['fimRefeicao'])? ', "'.implode('", "', $registros['fimRefeicao']).'"': '')
					.(!empty($registros['fimJornada'])? ', "'.implode('", "', $registros['fimJornada']).'")': ')')
				;

				$legendas = mysqli_fetch_all(
					query(
						"SELECT * FROM ponto
							JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
							JOIN user ON ponto.pont_nb_user = user.user_nb_id
							LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
							WHERE ponto.pont_nb_motivo IS NOT NULL 
								AND pont_tx_status != 'inativo'
								AND pont_tx_data IN ".$datas." 
								AND pont_tx_matricula = '$matricula'"
					),
					MYSQLI_ASSOC
				);
		
				$tipos = [
					'I' => 0, 
					'P' => 0, 
					'T' => 0, 
					'DSR' => 0
				];
				$contagens = [
					'inicioJornada' => $tipos,
					'fimJornada' => $tipos,
					'inicioRefeicao' => $tipos,
					'fimRefeicao' => $tipos,
				];
				
				foreach ($legendas as $value) {
					$legenda = $value['moti_tx_legenda'];
				
					switch ($value['macr_tx_nome']) {
						case 'Inicio de Jornada':
							$acao = 'inicioJornada';
							break;
						case 'Fim de Jornada':
							$acao = 'fimJornada';
							break;
						case 'Inicio de Refeição':
							$acao = 'inicioRefeicao';
							break;
						case 'Fim de Refeição':
							$acao = 'fimRefeicao';
							break;
						default:
							$acao = '';
					}
					if ($acao != '' && !empty($legenda) && array_key_exists($legenda, $contagens[$acao])) {
						$contagens[$acao][$legenda]++;
					}
				}
				
				foreach ($contagens as $acao => $tipos) {
					foreach ($tipos as $tipo => $quantidade) {
						if($quantidade > 0){
							$aRetorno[$acao][] = "<strong>$tipo</strong>";
						}
					}
				}
			}
		//}

		//Aviso de registro inativado{
			$ajuste = mysqli_fetch_all(
				query(
					"SELECT pont_tx_data, macr_tx_nome, pont_tx_status FROM ponto
						JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
						JOIN user ON ponto.pont_nb_user = user.user_nb_id
						LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
						WHERE pont_tx_data LIKE '%$data%' 
							AND pont_tx_matricula = '$matricula'"
				),
				MYSQLI_ASSOC
			);
	
			$possuiAjustes = [
				'jornada'  => ['inicio' => False, 'fim' => False], 	//$quantidade_inicioJ e $quantidade_fimJ
				'refeicao' => ['inicio' => False, 'fim' => False],	//$quantidade_inicioR e $quantidade_fimR
			];
	
			foreach ($ajuste as $valor) {
				if($data == substr($valor["pont_tx_data"], 0, 10)){
					if($valor['pont_tx_status'] == 'inativo'){
						$possuiAjustes['jornada']['inicio']  = $possuiAjustes['jornada']['inicio'] 	|| $valor["macr_tx_nome"] == 'Inicio de Jornada';
						$possuiAjustes['jornada']['fim'] 	 = $possuiAjustes['jornada']['fim'] 	|| $valor["macr_tx_nome"] == 'Fim de Jornada';
						$possuiAjustes['refeicao']['inicio'] = $possuiAjustes['refeicao']['inicio'] || $valor["macr_tx_nome"] == 'Inicio de Refeição';
						$possuiAjustes['refeicao']['fim'] 	 = $possuiAjustes['refeicao']['fim']	|| $valor["macr_tx_nome"] == 'Fim de Refeição';
					}
				}
			}
			if($possuiAjustes['jornada']['inicio']){
				$aRetorno['inicioJornada'][] = "*";
			}
			if($possuiAjustes['jornada']['fim']){
				$aRetorno['fimJornada'][] = "*";
			}
			if($possuiAjustes['refeicao']['inicio']){
				$aRetorno['inicioRefeicao'][] = "*";
			}
			if($possuiAjustes['refeicao']['fim']){
				$aRetorno['fimRefeicao'][] = "*";
			}
		//}

		//SOMANDO TOTAIS{
			$campos = [
				'diffRefeicao', 'diffEspera', 'diffDescanso', 'diffRepouso', 'diffJornada', 
				'jornadaPrevista', 'diffJornadaEfetiva', 'maximoDirecaoContinua', 'intersticio', 
				'he50', 'he100', 'adicionalNoturno', 'esperaIndenizada', 'diffSaldo'
			];
			foreach($campos as $campo){
				if(empty($totalResumo[$campo])){
					$totalResumo[$campo] = '00:00';
				}
				$totalResumo[$campo] = operarHorarios(
					[$totalResumo[$campo], strip_tags(str_replace("&nbsp;", "", $aRetorno[$campo]))], 
					'+'
				);
			}
			unset($campos);
		//}

		if($saldo > 0){
			$aRetorno['diffSaldo'] = "<b>".$aRetorno['diffSaldo']."</b>";
		}

		$ultimoFimJornada = [];
		if(count($aRetorno['fimJornada']) > 0){
			for($f = count($aRetorno['fimJornada'])-1; $f >= 0; $f--){
				if(preg_match("/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/", $aRetorno['fimJornada'][$f])){
					$ultimoFimJornada = [
						'key' => $f, 
						'value' => $aRetorno['fimJornada'][$f]
					];
					break;
				}
			}
		}else{
			$ultimoFimJornada = null;
		}

		if(!empty($ultimoFimJornada)){
			$dataDia = DateTime::createFromFormat('d/m/Y H:i:s', $aRetorno['data'].' 00:00:00');
			$dataFim = DateTime::createFromFormat('Y-m-d H:i:s', $ultimoFimJornada['value']);
			$qttDias = date_diff($dataDia, $dataFim);
			if(!is_bool($qttDias)){
				$qttDias = intval($qttDias->format('%d'));
				if($qttDias > 0){
					array_splice($aRetorno['fimJornada'], $ultimoFimJornada['key']+1, 0, 'D+1');
				}
			}
		}

		//Converter array em string{
			$legendas = mysqli_fetch_all(query(
				"SELECT UNIQUE moti_tx_legenda FROM motivo 
					WHERE moti_tx_legenda IS NOT NULL;"
				), 
				MYSQLI_ASSOC
			);

			foreach(['inicioJornada', 'fimJornada', 'inicioRefeicao', 'fimRefeicao'] as $tipo){
				if (count($aRetorno[$tipo]) > 0){
					for($f = 0; $f < count($aRetorno[$tipo]); $f++){
						//Formatar datas para hora e minutos
						if(strlen($aRetorno[$tipo][$f]) > 3 && strpos($aRetorno[$tipo][$f], ':00', strlen($aRetorno[$tipo][$f])-3) !== false){
							$aRetorno[$tipo][$f] = date('H:i', strtotime($aRetorno[$tipo][$f]));
						}
					}
					$aRetorno[$tipo] = implode("<br>", $aRetorno[$tipo]);
					foreach($legendas as $legenda){
						$aRetorno[$tipo] = str_replace('<br><strong>'.$legenda['moti_tx_legenda'].'</strong>', ' <strong>'.$legenda['moti_tx_legenda'].'</strong>', $aRetorno[$tipo]);
					}
					$aRetorno[$tipo] = str_replace('<br>D+1', ' D+1', $aRetorno[$tipo]);
					$aRetorno[$tipo] = str_replace('<br>*', ' *', $aRetorno[$tipo]);
				}else{
					$aRetorno[$tipo] = '';
				}
			}
		//}
		
		return $aRetorno;
	}

	function pegarDiaDaSemana($date){
		$week = [
			'Sunday' => 'Domingo', 
			'Monday' => 'Segunda-Feira',
			'Tuesday' => 'Terca-Feira',
			'Wednesday' => 'Quarta-Feira',
			'Thursday' => 'Quinta-Feira',
			'Friday' => 'Sexta-Feira',
			'Saturday' => 'Sábado'
		];
		$response = iconv('UTF-8', 'ASCII//TRANSLIT', $week[date('l', strtotime($date))]);
		return $response;
	}
?>