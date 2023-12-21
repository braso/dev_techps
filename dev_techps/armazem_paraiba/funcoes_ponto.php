<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	include "conecta.php";

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

		$aData = explode(" - ", $_POST['daterange']);

		$begin = new DateTime(data($aData[0]));
		$end = new DateTime(data($aData[1]));

		$a=carregar('entidade',$_POST['motorista']);
		
		for ($i = $begin; $i <= $end; $i->modify('+1 day')) {

			$sqlRemover = query("SELECT * FROM abono WHERE abon_tx_data = '" . $i->format("Y-m-d") . "' AND abon_tx_matricula = '$a[enti_tx_matricula]' AND abon_tx_status = 'ativo'");
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

		cabecalho('Espelho de Ponto');

		$c[] = combo_net('Motorista:','motorista',$_POST['busca_motorista'],4,'entidade','',' AND enti_tx_tipo = "Motorista"','enti_tx_matricula');
		$c[] = campo('Data(s):','daterange',$_POST['daterange'],3);
		$c[] = campo_hora('Abono: (hh:mm)','abono','',3);
		$c2[] = combo_bd('Motivo:','motivo',$_POST['motivo'],4,'motivo','',' AND moti_tx_tipo = "Abono"');
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

	function cadastra_ajuste(){

		if($_POST['hora'] == ''){
			set_status("ERRO: Dados insuficientes!");
			layout_ajuste();
			exit;
		}

		$aMotorista = carregar('entidade',$_POST['id']);

		$queryMacroPonto = query("SELECT macr_tx_codigoInterno, macr_tx_codigoExterno FROM macroponto WHERE macr_nb_id = '".$_POST['idMacro']."'");
		$aTipo = carrega_array($queryMacroPonto);

		$campos = ['pont_nb_user', 'pont_tx_matricula', 'pont_tx_data', 'pont_tx_tipo', 'pont_tx_tipoOriginal', 'pont_tx_status', 'pont_tx_dataCadastro', 'pont_nb_motivo', 'pont_tx_descricao'];
		$valores = [$_SESSION['user_nb_id'], $aMotorista['enti_tx_matricula'], "$_POST[data] $_POST[hora]", $aTipo[0], $aTipo[1], 'ativo', date("Y-m-d H:i:s"),$_POST['motivo'],$_POST['descricao']];
		inserir('ponto',$campos,$valores);
			
		
		layout_ajuste();
		exit;
	}

	function excluir_ponto(){
		$a=carregar('ponto', (int)$_POST['id']);
		remover('ponto', (int)$_POST['id']);
		
		$_POST['id'] = $_POST['idEntidade'];
		$_POST['data'] = substr($a['pont_tx_data'],0, -9);
		$_POST['busca_data'] = $a['pont_tx_data'];


		layout_ajuste();
		exit;
	}

	function layout_ajuste(){
		global $a_mod;

		cabecalho('Espelho de Ponto');

		if($_POST['busca_data1'] == '' && $_POST['busca_data'])
			$_POST['busca_data1'] = $_POST['busca_data'];
		if($_POST['busca_data2'] == '' && $_POST['busca_data'])
			$_POST['busca_data2'] = $_POST['busca_data'];
		
		$aMotorista = carregar('entidade',$_POST['id']);

		$sqlCheck = query("SELECT user_tx_login, endo_tx_dataCadastro FROM endosso, user WHERE endo_tx_mes = '".substr($_POST['data'], 0,7).'-01'."' AND endo_nb_entidade = '".$aMotorista['enti_nb_id']."'
				AND endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."' AND endo_tx_status = 'ativo' AND endo_nb_userCadastro = user_nb_id LIMIT 1");
		$aEndosso = carrega_array($sqlCheck);
			
		$extra = " AND pont_tx_data LIKE '".$_POST['data']." %' AND pont_tx_matricula = '$aMotorista[enti_tx_matricula]'";

		$botao_imprimi = 
			'<button  href="#" onclick="imprimir()">Imprimir (Ctrl + P)</button >
				<script>
					function imprimir() {
						// Abrir a caixa de diálogo de impressão
						window.print();
					}
				</script>';

		$c[] = texto('Matrícula',$aMotorista['enti_tx_matricula'],2);
		$c[] = texto('Motorista',$aMotorista['enti_tx_nome'],5);
		$c[] = texto('CPF',$aMotorista['enti_tx_cpf'],3);

		$c2[] = campo('Data','data',data($_POST['data']),2,'','readonly=readonly');
		$c2[] = campo_hora('Hora','hora',$a_mod['macr_tx_codigoExterno'],2);
		$c2[] = combo_bd('Código Macro','idMacro','',4,'macroponto','','ORDER BY macr_nb_id ASC');
		$c2[] = combo_bd('Motivo:','motivo','',4,'motivo','',' AND moti_tx_tipo = "Ajuste"');

		$c3[] = textarea('Justificativa:','descricao','',12);

		if(count($aEndosso) == 0){
			$botao[] = botao('Gravar','cadastra_ajuste','id,busca_motorista,busca_data1,busca_data2,data,busca_data',"$_POST[id],$_POST[id],$_POST[busca_data1],$_POST[busca_data2],$_POST[data],".substr($_POST['data'],0, -3));
			$iconeExcluir = 'icone_excluir(pont_nb_id,excluir_ponto,idEntidade,' . $_POST['id'] . ')';
		}else{
			$c2[] = texto('Endosso:',"Endossado por ".$aEndosso['user_tx_login']." em ".data($aEndosso['endo_tx_dataCadastro'],1),6);
		}
		$botao[] = $botao_imprimi;
		$botao[] = botao('Voltar', 'index', 'busca_data1, busca_data2, id, busca_empresa, busca_motorista, data, busca_data', "$_POST[busca_data1], $_POST[busca_data2], $_POST[id], $aMotorista[enti_nb_empresa], $_POST[id], $_POST[data], ".substr($_POST['data'], 0, -3));
		
		abre_form('Dados do Ajuste de Ponto');
		linha_form($c);
		linha_form($c2);
		linha_form($c3);
		fecha_form($botao);

		$sql = "SELECT * FROM ponto
			JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
			JOIN user ON ponto.pont_nb_user = user.user_nb_id
			LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
			WHERE ponto.pont_tx_status != 'inativo' 
			$extra";

		
		$cab = ['CÓD','DATA','HORA','TIPO','MOTIVO', 'LEGENDA','JUSTIFICATIVA','USUÁRIO','DATA CADASTRO',''];

		// $ver2 = "icone_modificar(arqu_nb_id,layout_confirma)";
		$val = ['pont_nb_id','data(pont_tx_data)','data(pont_tx_data,3)','macr_tx_nome','moti_tx_nome','moti_tx_legenda','pont_tx_descricao','user_tx_login','data(pont_tx_dataCadastro,1)','icone_excluir(pont_nb_id,excluir_ponto,idEntidade,'.$_POST['id'].')'];
		grid($sql,$cab,$val,'','',2,'ASC',-1);

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
			$negative = ($horarios[$f][0] == '-');
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
		$datetime1 = new DateTime('2000-01-01 ' . $tempo1);
		$datetime2 = new DateTime('2000-01-01 ' . '05:30');
		$datetime3 = new DateTime('2000-01-01 ' . '03:00');
		$datetimeDescanso = new DateTime('2000-01-01 ' . $tempoDescanso);
		$datetimeDescanso1 = new DateTime('2000-01-01 ' . '00:30');
		$datetimeDescanso2 = new DateTime('2000-01-01 ' . '00:15');

		$alertaDescanso = '';

		if($datetime1 > $datetime2){
			if($datetimeDescanso < $datetimeDescanso1){
				$alertaDescanso = "<i style='color:orange;' title='Descanso de 00:30 não respeitado' class='fa fa-warning'></i>";
			}
			return "<a style='white-space: nowrap;'>$alertaDescanso<i style='color:orange;' title='Tempo excedido de 05:30' class='fa fa-warning'></i></a>&nbsp;".$tempo1;
			// return "<a style='white-space: nowrap;'>$alertaDescanso</a>&nbsp;".$tempo1;
		}elseif($datetime1 > $datetime3){
			if($datetimeDescanso < $datetimeDescanso2){
				$alertaDescanso = "<i style='color:orange;' title='Descanso de 00:15 não respeitado' class='fa fa-warning'></i>";
			}
			// return "<a style='white-space: nowrap;'>$alertaDescanso<i style='color:orange;' title='Tempo excedido de 03:00' class='fa fa-warning'></i></a>&nbsp;".$tempo1;
			return "<a style='white-space: nowrap;'>$alertaDescanso</a>&nbsp;".$tempo1;
		}else{
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

		if($datetime1 > $datetime2){
			return "<a style='white-space: nowrap;'><i style='color:orange;' title='Tempo excedido de $tempo2' class='fa fa-warning'></i></a>&nbsp;".$tempo1;
		}else{
			return $tempo1;
		}
	}

	function verificaTolerancia($saldoDiario, $data, $idMotorista) {
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
		$saldoEmMinutos = intval($saldoDiario[0])*60+($saldoDiario[0] == '-'? -1: 1)*intval($saldoDiario[1]);

		if($saldoEmMinutos < -($tolerancia)){
			$cor = 'red';
		}elseif($saldoEmMinutos > $tolerancia){
			$cor = 'green';
		}else{
			$cor = 'blue';
		}

		if ($_SESSION['user_tx_nivel'] == 'Motorista') {
			$retorno = '<center><span><i style="color:'.$cor.';" class="fa fa-circle"></i></span></center>';
		} else {
			$retorno = '<center><a title="Ajuste de Ponto" href="#" onclick="ajusta_ponto(\''.$data.'\', \''.$idMotorista.'\')"><i style="color:'.$cor.';" class="fa fa-circle"></i></a></center>';
		}
		return $retorno;
	}

	function ordenar_horarios($inicio, $fim, $ehEspera = false) {
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
		$tooltip ='';
		$horarios_com_origem = [];
		for ($i = 0; $i < count($horarios); $i++) {
			$tooltip .= ucfirst($origem[$i])." ".data($horarios[$i],1)."\n";

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

		foreach ($horarios_com_origem as $item) {
			if ($item["origem"] == "inicio") {
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
					
					$pares[] = ["inicio" => date("H:i", strtotime($inicio_atual)), "fim" => date("H:i", strtotime($item["horario"])), "intervalo" => $interval];

				}

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

		if(count($inicio) == 0 && count($fim) == 0){
			$iconeAlerta = '';
		}elseif(count($inicio) != count($fim) || count($horarios_com_origem)/2 != (count($pares) + count($paresParaRepouso))){ 
			$iconeAlerta = "<a><i style='color:red;' title='$tooltip' class='fa fa-info-circle'></i></a>";
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

	function dateTimeToSecs(DateTime $dateTime): int{
    	$res = date_diff(DateTime::createFromFormat('H:i', '00:00'), $dateTime);
        $res = 
        	($res->invert? 1:-1)*
			(
				$res->d*24*60*60+
				$res->h*60*60+
				$res->i*60+
				$res->s
			);
        return $res;
    }

	function calcJorPre($data, $jornadas, $abono = null){
		if (date('w', strtotime($data)) == '6') { //SABADOS
			$jornadaPrevista = $jornadas['sabado'];
		} elseif (date('w', strtotime($data)) == '0' || isset($jornadas['feriado'])) { //DOMINGOS OU FERIADOS
			$jornadaPrevista = '00:00';
		} else {
			$jornadaPrevista = $jornadas['semanal'];
		}

		$jornadaPrevistaOriginal = $jornadaPrevista;
		if($abono !== null){
			$jornadaPrevista = (new DateTime($data." ".$abono))
				->diff(new DateTime($data." ".$jornadaPrevista));
		}else{
			$jornadaPrevista = (new DateTime($data." ".$jornadaPrevista));
		}
		$jornadaPrevista = $jornadaPrevista->format("H:i");

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

		if ($aMotorista['enti_nb_empresa'] && $aMotorista['empr_nb_cidade'] && $aMotorista['cida_nb_id'] && $aMotorista['cida_tx_uf']) {
			$extraFeriado = " AND ((feri_nb_cidade = '$aMotorista[cida_nb_id]' OR feri_tx_uf = '$aMotorista[cida_tx_uf]') OR ((feri_nb_cidade = '' OR feri_nb_cidade IS NULL) AND (feri_tx_uf = '' OR feri_tx_uf IS NULL)))";
		}

		$aParametro = carregar('parametro', $aMotorista['enti_nb_parametro']);
		$alertaJorEfetiva = (($aParametro['para_tx_acordo'] == 'Sim')? '12:00': '10:00');

		$queryFeriado = query("SELECT feri_tx_nome FROM feriado WHERE feri_tx_data LIKE '____-" . substr($data, 5, 5) . "%' AND feri_tx_status != 'inativo' $extraFeriado");
		$stringFeriado = '';
		while ($row = carrega_array($queryFeriado)) {
			$stringFeriado .= $row[0] . "\n";
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
				
				$sql = query(
					"SELECT macroponto.macr_tx_nome, ponto.* FROM ponto 
					JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
					WHERE pont_tx_status != 'inativo' 
						AND pont_tx_matricula = $matricula 
						AND pont_tx_data LIKE '".$diaSeguinte."%' 
						-- AND pont_nb_id >= (
						-- 	SELECT pont_nb_id FROM ponto 
						-- 		WHERE pont_tx_status != 'inativo' 
						-- 			AND pont_tx_matricula = '".$matricula."' 
						-- 			AND pont_tx_data LIKE '".$diaSeguinte."%'
						-- 			AND pont_tx_tipo = '1'
						-- 		LIMIT 1
						-- )
					ORDER BY pont_tx_data ASC;"
				);
				while($ponto = carrega_array($sql)){
					$ponto['diaSeguinte'] = True;
					$pontosDia[] = $ponto;
					if($ponto['pont_tx_tipo'] == '2'){
						break;
					}
				}
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
			$tiposRegistrados[] = [date("H:i", strtotime($ponto['pont_tx_data'])), $ponto['pont_tx_tipo']]; //$arrayMDC
			if(!isset($registros[$tipos[$ponto['pont_tx_tipo']]])){
				$registros[$tipos[$ponto['pont_tx_tipo']]] = [];
			}
			$registros[$tipos[$ponto['pont_tx_tipo']]][] = $ponto['pont_tx_data'];
		}

		$registros['jornadaCompleto']  = ordenar_horarios($registros['inicioJornada'],  $registros['fimJornada']);		/* $jornadaOrdenado */
		$registros['refeicaoCompleto'] = ordenar_horarios($registros['inicioRefeicao'], $registros['fimRefeicao']);		/* $refeicaoOrdenada */
		$registros['esperaCompleto']   = ordenar_horarios($registros['inicioEspera'],   $registros['fimEspera'], true);	/* $esperaOrdenada */
		$registros['descansoCompleto'] = ordenar_horarios($registros['inicioDescanso'], $registros['fimDescanso']);		/* $descansoOrdenado */
		$registros['repousoCompleto'] = ordenar_horarios($registros['inicioRepouso'], $registros['fimRepouso']);		/* $repousoOrdenado */;

		if (isset($registros['esperaCompleto']['paresParaRepouso']) && !empty($registros['esperaCompleto']['paresParaRepouso'])){
			$paresParaRepouso = $registros['esperaCompleto']['paresParaRepouso'];
			unset($registros['esperaCompleto']['paresParaRepouso']);
			for ($i = 0; $i < count($paresParaRepouso); $i++) {
				$registros['repousoPorEspera']['inicioRepouso'][] 	= $data . ' ' . $paresParaRepouso[$i]['inicio'];	/*$aDataHorainicioRepouso*/
				$registros['repousoPorEspera']['fimRepouso'][] 		= $data . ' ' . $paresParaRepouso[$i]['fim'];		/*$aDataHorafimRepouso*/
			}
			$registros['repousoPorEspera']['repousoCompleto'] = ordenar_horarios($registros['repousoPorEspera']['inicioRepouso'], $registros['repousoPorEspera']['fimRepouso']);
		}else{
			$registros['repousoPorEspera']['repousoCompleto'] = ordenar_horarios([], []);
		}

		$aRetorno['diffRefeicao'] = $registros['refeicaoCompleto']['icone'] . $registros['refeicaoCompleto']['totalIntervalo'];
		$aRetorno['diffEspera']   = $registros['esperaCompleto']['icone'] . $registros['esperaCompleto']['totalIntervalo'];
		$aRetorno['diffDescanso'] = $registros['descansoCompleto']['icone'] . $registros['descansoCompleto']['totalIntervalo'];
		$aRetorno['diffRepouso']  = $registros['repousoCompleto']['icone'] . $registros['repousoCompleto']['totalIntervalo'];

		$contagemEspera += count($registros['esperaCompleto']['pares']);

		$aAbono = carrega_array(query(
			"SELECT * FROM abono, motivo, user 
				WHERE abon_tx_status != 'inativo' 
					AND abon_nb_userCadastro = user_nb_id 
					AND abon_tx_matricula = '$matricula' 
					AND abon_tx_data = '$data' 
					AND abon_nb_motivo = moti_nb_id
				ORDER BY abon_nb_id DESC 
				LIMIT 1"
		));
		
		$aRetorno['diffJornada'] = $registros['jornadaCompleto']['icone'].$registros['jornadaCompleto']['totalIntervalo'];

		//JORNADA PREVISTA{
			$jornadas = [
				'sabado' => $aMotorista['enti_tx_jornadaSabado'],
				'semanal'=> $aMotorista['enti_tx_jornadaSemanal'],
				'feriado'=> ($stringFeriado != ''? True: null)
			];
			[$jornadaPrevistaOriginal, $jornadaPrevista] = calcJorPre($data, $jornadas, $aAbono['abon_tx_abono']);
			$aRetorno['jornadaPrevista'] = $jornadaPrevista;
		//}

		//JORNADA EFETIVA{
			$jornadaIntervalo = new DateTime($registros['jornadaCompleto']['totalIntervalo']);

			//SOMATORIO DE TODAS AS ESPERAS
			$totalNaoJornada = new DateTime(
				somarHorarios([
						$registros['refeicaoCompleto']['totalIntervalo'],
						$registros['esperaCompleto']['totalIntervalo'],
						$registros['descansoCompleto']['totalIntervalo'],
						$registros['repousoCompleto']['totalIntervalo']
				])
			);

			$jornadaEfetiva = $jornadaIntervalo->diff($totalNaoJornada); //$diffJornadaEfetiva
			$jornadaEfetiva = DateTime::createFromFormat('H:i', $jornadaEfetiva->format("%H:%I"));
			$aRetorno['diffJornadaEfetiva'] = verificaTempo($jornadaEfetiva->format('H:i'), $alertaJorEfetiva);
		//}

		// INICIO CALCULO INTERSTICIO{
			if (isset($registros['inicioJornada']) && count($registros['inicioJornada']) > 0){

				$ultimoDiaJornada = carrega_array(query(
					"SELECT pont_tx_data FROM ponto
					WHERE pont_tx_status != 'inativo'
						AND pont_tx_matricula = '$matricula'
						AND pont_tx_data < '$data'
					ORDER BY pont_tx_data DESC
					LIMIT 1"
				))[0];
				$ultimoDiaJornada = new DateTime($ultimoDiaJornada);

				$interJornadas = (new DateTime($registros['inicioJornada'][0]))->diff($ultimoDiaJornada);

				// Obter a diferença total em minutos
				$totalMinJornada = ($interJornadas->days*24*60)+($interJornadas->h*60)+$interJornadas->i;

				// Calcular as horas e minutos

				$intersticio = sprintf("%02d:%02d", floor($totalMinJornada / 60), $totalMinJornada % 60); // Formatar a string no formato HH:MM
				$totalIntersticio = somarHorarios(
					[$intersticio, $totalNaoJornada->format("H:i"), $interJornadas->format('%H:%I')]
				);

				list($hours, $minutes) = explode(':', $totalIntersticio);
				$totalInterSeg = $hours * 3600 + $minutes * 60;

				$totalSegJor = $totalMinJornada * 60;

				$icone = '';
				if ($totalInterSeg < (11*3600)){ // < 11 horas
					$icone .= "<a><i style='color:red;' title='Interstício Total de 11:00 não respeitado' class='fa fa-warning'></i></a>";
				}
				if ($totalSegJor < (8*3600)){ // < 8 horas
					$icone .= "<a><i style='color:red;' title='O mínimo de 08:00h ininterruptas no primeiro período, não respeitado.' class='fa fa-warning'></i></a>";
				}

				$aRetorno['intersticio'] = $icone.$totalIntersticio;
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
				
				$saldoDiario = operarHorarios([$saldoDiario, $transferir], '-');
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

			$adicionalNoturno = gmdate('H:i', (strtotime($data.' '.$registros['jornadaCompleto']['totalIntervaloAdicionalNot']))-(strtotime($data.' '.$intervalosNoturnos)));
			$aRetorno['adicionalNoturno'] = ($adicionalNoturno == '00:00')? '' : $adicionalNoturno;
		//FIM ADICIONAL NOTURNO

		//HORAS EXTRAS{
			if ($stringFeriado != '') {						//Se for feriado
				$iconeFeriado =  "<a><i style='color:orange;' title='$stringFeriado' class='fa fa-info-circle'></i></a>";
				$aRetorno['he100'] = $iconeFeriado.$aRetorno['diffSaldo'];
			}elseif(date('w', strtotime($data)) == '0'){	//Se for domingo
				$aRetorno['he100'] = $aRetorno['diffSaldo'];
			}elseif($aRetorno['diffSaldo'][0] != '-'){		//Se o saldo estiver positivo
				$aRetorno['he50'] = $aRetorno['diffSaldo'];
			}

			if($aRetorno['diffSaldo'] >= $aParametro['para_tx_HorasEXExcedente']){
				$aRetorno['he100'] = operarHorarios([$aRetorno['he100'], $aParametro['para_tx_HorasEXExcedente']], '+');
				$aRetorno['diffSaldo'] = operarHorarios([$aRetorno['diffSaldo'], $aParametro['para_tx_HorasEXExcedente']], '-');
			}
		//}

		//MÁXIMA DIREÇÃO CONTÍNUA
			$aRetorno['maximoDirecaoContinua'] = verificaTempoMdc(
				maxDirecaoContinua($tiposRegistrados),
				$registros['descansoCompleto']['totalIntervalo']
			);
		//FIM MÁXIMA DIREÇÃO CONTÍNUA

		//JORNADA MÍNIMA
			$dtJornada = new DateTime($data.' '.$jornadaEfetiva->format("H:i"));
			$dtJornadaMinima = new DateTime($data.' 06:00');

			$fezJorMinima = (
				($dtJornada > $dtJornadaMinima || $aRetorno['jornadaPrevista'] != '00:00') && 
				$jornadaEfetiva->format("H:i") != '00:00'
			);
		//FIM JORNADA MÍNIMA

		//01:00 DE REFEICAO
			if(count($registros['refeicaoCompleto']['pares']) > 0){
				for ($i = 0; $i < count($registros['refeicaoCompleto']['pares']); $i++) {
					$dtIntervaloRefeicao = new DateTime($data.' '.$registros['refeicaoCompleto']['pares'][$i]['intervalo']);
				}
			}else{
				$dtIntervaloRefeicao = new DateTime($data.' 00:00');
			}
			$avisoRefeicaoMinima = '';
			if($dtIntervaloRefeicao > (new DateTime($data.' 02:00'))){
				$avisoRefeicaoMinima = "<a><i style='color:orange;' title='Refeição com tempo máximo de 02:00h não respeitado.' class='fa fa-info-circle'></i></a>";
			}elseif ($dtJornada > $dtJornadaMinima && ($dtIntervaloRefeicao < (new DateTime($data.' 01:00')) || $aRetorno['diffRefeicao'] == '00:00')) {
				$avisoRefeicaoMinima = "<a><i style='color:red;' title='Refeição com tempo mínimo de 01:00h não respeitado.' class='fa fa-warning'></i></a>";
			}

			$aRetorno['diffRefeicao'] = $avisoRefeicaoMinima.' '.$aRetorno['diffRefeicao'];
		//FIM 01:00 DE REFEICAO

		//ALERTAS
			if($fezJorMinima){
				if(!isset($registros['inicioJornada'][0]) || $registros['inicioJornada'][0] == ''){
					$aRetorno['inicioJornada'][] 	= "<a><i style='color:red;' title='Batida início de jornada não registrada!' class='fa fa-warning'></i></a>";
				}
				if(!isset($registros['fimJornada'][0]) || $registros['fimJornada'][0] == ''){
					$aRetorno['fimJornada'][] 		= "<a><i style='color:red;' title='Batida fim de jornada não registrada!' class='fa fa-warning'></i></a>";
				}
				if(!isset($registros['inicioRefeicao'][0]) || $aRetorno['inicioRefeicao'][0] == ''){
					$aRetorno['inicioRefeicao'][] 	= "<a><i style='color:red;' title='Batida início de refeição não registrada!' class='fa fa-warning'></i></a>".$avisoRefeicaoMinima;
				}
				if(!isset($registros['fimRefeicao'][0]) || $aRetorno['fimRefeicao'][0] == ''){
					$aRetorno['fimRefeicao'][] 		= "<a><i style='color:red;' title='Batida fim de refeição não registrada!' class='fa fa-warning'></i></a>".$avisoRefeicaoMinima;
				}
			}else{
				if(!isset($registros['inicioJornada'][0]) || $registros['inicioJornada'][0] == ''){
					$aRetorno['inicioJornada'][] 	= "<a><i style='color:red;' title='Batida início de jornada não registrada!' class='fa fa-warning'></i></a>";
				}
			}
			if (is_array($aAbono) && count($aAbono) > 0) {
				$warning = 
					"<a><i "
						."style='color:orange;' "
						."title='"
							."Jornada Original: " . str_pad($jornadaPrevistaOriginal, 2, '0', STR_PAD_LEFT) . ":00:00\n"
							."Abono: ".$aAbono['abon_tx_abono']."\n"
							."Motivo: ".$aAbono['moti_tx_nome']."\n"
							."Justificativa: ".$aAbono['abon_tx_descricao']."\n\n"
							."Registro efetuado por ".$aAbono['user_tx_login']." em " . data($aAbono['abon_tx_dataCadastro'], 1)."' "
						."class='fa fa-warning'/>"
					."</a>&nbsp;";
	
				$aRetorno['jornadaPrevista'] = $warning.$aRetorno['jornadaPrevista'];
			}
		//FIM ALERTAS

		foreach(['inicioJornada', 'fimJornada', 'inicioRefeicao', 'fimRefeicao'] as $campo){
			if(count($registros[$campo]) > 0 && !empty($registros[$campo][0])){
				$aRetorno[$campo] = $registros[$campo];
			}
		}

		if (count($registros['inicioEspera']) > 0 && count($registros['fimEspera']) > 0){
			$aRetorno['diffEspera'] = $registros['esperaCompleto']['icone'].$registros['esperaCompleto']['totalIntervalo'];
		}
		if (count($registros['inicioDescanso']) > 0 && count($registros['fimDescanso']) > 0){
			$aRetorno['diffDescanso'] = $registros['descansoCompleto']['icone'].verificaTempo($registros['descansoCompleto']['totalIntervalo'], "05:30");
		}
		if (count($registros['inicioRepouso']) > 0 && count($registros['fimRepouso']) > 0){
			$aRetorno['diffRepouso'] = $registros['repousoCompleto']['icone'].$registros['repousoCompleto']['totalIntervalo'];
		}
		
		$ajuste = mysqli_fetch_all(
			query(
				"SELECT pont_tx_data,macr_tx_nome FROM ponto
					JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
					JOIN user ON ponto.pont_nb_user = user.user_nb_id
					LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
					WHERE ponto.pont_tx_status != 'inativo' 
						AND pont_tx_data LIKE '%$data%' 
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
				$possuiAjustes['jornada']['inicio']  = $possuiAjustes['jornada']['inicio'] 	|| $valor["macr_tx_nome"] == 'Inicio de Jornada';
				$possuiAjustes['jornada']['fim'] 	 = $possuiAjustes['jornada']['fim'] 	|| $valor["macr_tx_nome"] == 'Fim de Jornada';
				$possuiAjustes['refeicao']['inicio'] = $possuiAjustes['refeicao']['inicio'] || $valor["macr_tx_nome"] == 'Inicio de Refeição';
				$possuiAjustes['refeicao']['fim'] 	 = $possuiAjustes['refeicao']['fim']	|| $valor["macr_tx_nome"] == 'Fim de Refeição';
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
		
		$legendas = mysqli_fetch_all(
			query(
				"SELECT pont_tx_data,macr_tx_nome, moti_tx_legenda FROM ponto
				JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
				JOIN user ON ponto.pont_nb_user = user.user_nb_id
				LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
				WHERE ponto.pont_nb_motivo IS NOT NULL 
					AND pont_tx_data LIKE '%$data%' 
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
			if (!empty($legenda) && array_key_exists($legenda, $contagens[$acao])) {
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
		}elseif($saldo > 0){
			$aRetorno['diffSaldo'] = "<b>".$aRetorno['diffSaldo']."</b>";
		}


		// TOTALIZADOR 
		$campos = ['diffRefeicao', 'diffEspera', 'diffDescanso', 'diffRepouso', 'diffJornada', 'jornadaPrevista', 'diffJornadaEfetiva', 'maximoDirecaoContinua', 'intersticio', 'he50', 'he100', 'adicionalNoturno', 'esperaIndenizada', 'diffSaldo'];
		foreach($campos as $campo){
			if(empty($totalResumo[$campo])){
				$totalResumo[$campo] = '00:00';
			}else{
				$totalResumo[$campo] = somarHorarios([$totalResumo[$campo], strip_tags(str_replace("&nbsp;", "", $aRetorno[$campo]))]);
			}
		}
		unset($campos);
		
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

	function diaDetalheEndosso($matricula, $data, $status = ''){
		global $totalResumo, $contagemEspera;
		setlocale(LC_ALL, 'pt_BR.utf8');

		$aRetorno['data'] = data($data);
		$aRetorno['diaSemana'] = substr(pegarDiaDaSemana(strtotime($data)), 0, 3);
		$campos = ['inicioJornada', 'inicioRefeicao', 'fimRefeicao', 'fimJornada', 'diffRefeicao', 'diffEspera', 'diffDescanso', 'diffRepouso', 'diffJornada', 'jornadaPrevista', 'diffJornadaEfetiva', 'maximoDirecaoContinua', 'intersticio', 'he50', 'he100', 'adicionalNoturno', 'esperaIndenizada', 'diffSaldo', 'temPendencias'];
		foreach($campos as $campo){
			$aRetorno[$campo] = '';
		}

		$sqlMotorista = query("SELECT * FROM entidade WHERE enti_tx_status != 'inativo' AND enti_tx_matricula = '$matricula' LIMIT 1");
		$aMotorista = carrega_array($sqlMotorista);

		$aEmpresa = carregar('empresa', $aMotorista['enti_nb_empresa']);
		$aEmpCidade = carregar('cidade', $aEmpresa['empr_nb_cidade']);
		if ($aMotorista['enti_nb_empresa'] && $aEmpresa['empr_nb_cidade'] && $aEmpCidade['cida_nb_id'] && $aEmpCidade['cida_tx_uf']) {
			$extraFeriado = " AND (
				(feri_nb_cidade = '$aEmpCidade[cida_nb_id]' AND feri_tx_uf = '$aEmpCidade[cida_tx_uf]')
				OR (feri_nb_cidade IS NULL AND feri_tx_uf IS NULL)
				OR (feri_nb_cidade = 0 AND feri_tx_uf = ''))";
		}

		$aParametro = carregar('parametro', $aMotorista['enti_nb_parametro']);
		$horaAlertaJornadaEfetiva = ($aParametro['para_tx_acordo'] == 'Sim')? '12:00': '10:00';

		$sqlFeriado = "SELECT feri_tx_nome FROM feriado WHERE feri_tx_data LIKE '____-" . substr($data, 5, 5) . "%' AND feri_tx_status != 'inativo' $extraFeriado";
		$queryFeriado = query($sqlFeriado);
		$stringFeriado = '';
		while($row = carrega_array($queryFeriado)){
			$stringFeriado .= $row[0]."\n";
		}

		if(date('%w',strtotime($data)) == '6'){ //SABADOS
			// $horas_sabado = $aMotorista[enti_tx_jornadaSabado];
			// $cargaHoraria = sprintf("%02d:%02d", floor($horas_sabado), ($horas_sabado - floor($horas_sabado)) * 60);
			$cargaHoraria = $aMotorista['enti_tx_jornadaSabado'];
		}elseif(date('%w',strtotime($data)) == '0'){ //DOMINGOS
			$cargaHoraria = '00:00';
		}else{
			// $horas_diarias = $aMotorista[enti_tx_jornadaSemanal]/5;
			// $cargaHoraria = sprintf("%02d:%02d", floor($horas_diarias), ($horas_diarias - floor($horas_diarias)) * 60);
			$cargaHoraria = $aMotorista['enti_tx_jornadaSemanal'];
		}

		if($stringFeriado != ''){
			$cargaHoraria = '00:00';
		}
		

		// if($cargaHoraria == 0){
		// 	return $aRetorno;
		// 	exit;
		// }

		$sql = query("SELECT * FROM ponto WHERE pont_tx_status != 'inativo' AND pont_tx_matricula = '$matricula'  AND pont_tx_data LIKE '$data%' ORDER BY pont_tx_data ASC");
		while($aDia = carrega_array($sql)){
			
			// $queryMacroPonto = query("SELECT macr_tx_nome,macr_tx_codigoInterno FROM macroponto WHERE macr_tx_codigoExterno = '".$aDia[pont_tx_tipo]."'");
			// $aTipo = carrega_array($queryMacroPonto);

			$arrayMDC[] = [date("H:i",strtotime($aDia['pont_tx_data'])), $aDia['pont_tx_tipo']];

			switch($aDia['pont_tx_tipo']){
				case '1':
					$arrayInicioJornada[] = date("H:i",strtotime($aDia['pont_tx_data']));
					$arrayInicioJornada2[] = $aDia['pont_tx_data'];
					// $aRetorno['inicioJornada'] = date("H:i",strtotime($aDia[pont_tx_data]));
					$inicioDataAdicional = $aDia['pont_tx_data'];
				break;
				case '2':
					$arrayFimJornada[] = date("H:i",strtotime($aDia['pont_tx_data']));
					$arrayFimJornada2[] = $aDia['pont_tx_data'];
					// $aRetorno['fimJornada'] = date("H:i",strtotime($aDia[pont_tx_data]));
					$fimDataAdicional = $aDia['pont_tx_data'];
				break;
				case '3':
					$arrayInicioRefeicaoFormatado[] = date("H:i",strtotime($aDia['pont_tx_data']));
					$arrayInicioRefeicao[] = $aDia['pont_tx_data'];
				break;
				case '4':
					$arrayFimRefeicaoFormatado[] = date("H:i",strtotime($aDia['pont_tx_data']));
					$arrayFimRefeicao[] = $aDia['pont_tx_data'];
				break;
				case '5':
					$aDataHorainicioEspera[] = $aDia['pont_tx_data'];
				break;
				case '6':
					$aDataHorafimEspera[] = $aDia['pont_tx_data'];
				break;
				case '7':
					$aDataHorainicioDescanso[] = $aDia['pont_tx_data'];
				break;
				case '8':
					$aDataHorafimDescanso[] = $aDia['pont_tx_data'];
				break;
				case '9':
					$aDataHorainicioRepouso[] = $aDia['pont_tx_data'];
				break;
				case '10':
					$aDataHorafimRepouso[] = $aDia['pont_tx_data'];
				break;
				case '11':
					$aDataHorainicioRepouso[] = $aDia['pont_tx_data']; // ADICIONADO AO REPOUSO
				break;
				case '12':
					$aDataHorafimRepouso[] = $aDia['pont_tx_data']; // ADICIONADO AO REPOUSO
				break;
			}
		}
		
		$jornadaOrdenado = ordenar_horarios($arrayInicioJornada2, $arrayFimJornada2);
		$refeicaoOrdenada = ordenar_horarios($arrayInicioRefeicao, $arrayFimRefeicao);
		$esperaOrdenada = ordenar_horarios($aDataHorainicioEspera, $aDataHorafimEspera, true);
		$descansoOrdenado = ordenar_horarios($aDataHorainicioDescanso, $aDataHorafimDescanso);
		
		if($esperaOrdenada['paresParaRepouso']){
			for ($i=0; $i < count($esperaOrdenada['paresParaRepouso']); $i++) { 
				$aDataHorainicioRepouso[] = $data.' '.$esperaOrdenada['paresParaRepouso'][$i]['inicio'];
				$aDataHorafimRepouso[] = $data.' '.$esperaOrdenada['paresParaRepouso'][$i]['fim'];
				$aDataHorainicioEsperaParaRepouso[] = $data.' '.$esperaOrdenada['paresParaRepouso'][$i]['inicio'];
				$aDataHorafimEsperaParaRepouso[] = $data.' '.$esperaOrdenada['paresParaRepouso'][$i]['fim'];

			}
			$esperaParaRepousoOrdenado = ordenar_horarios($aDataHorainicioEsperaParaRepouso, $aDataHorafimEsperaParaRepouso);
		}
		$repousoOrdenado = ordenar_horarios($aDataHorainicioRepouso, $aDataHorafimRepouso);

		$aRetorno['diffRefeicao'] = $refeicaoOrdenada['icone'].$refeicaoOrdenada['totalIntervalo'];
		$aRetorno['diffEspera']   = $esperaOrdenada['icone'].$esperaOrdenada['totalIntervalo'];
		$aRetorno['diffDescanso'] = $descansoOrdenado['icone'].$descansoOrdenado['totalIntervalo'];
		$aRetorno['diffRepouso']  = $repousoOrdenado['icone'].$repousoOrdenado['totalIntervalo'];

		$contagemEspera += count($esperaOrdenada['pares']);

		$sqlAbono = query("SELECT * FROM abono, motivo, user 
			WHERE abon_tx_status != 'inativo' AND abon_nb_userCadastro = user_nb_id 
			AND abon_tx_matricula = '$matricula' AND abon_tx_data = '$data' AND abon_nb_motivo = moti_nb_id
			ORDER BY abon_nb_id DESC LIMIT 1");
		
		$aAbono = carrega_array($sqlAbono);
		if($aAbono[0] > 0){
			$tooltip = "Jornada Original: ".str_pad($cargaHoraria, 2, '0', STR_PAD_LEFT).":00:00"."\n".
					"Abono: $aAbono[abon_tx_abono]\n".
					"Motivo: $aAbono[moti_tx_nome]\n".
					"Justificativa: $aAbono[abon_tx_descricao]\n\n".
					"Registro efetuado por $aAbono[user_tx_login] em ".data($aAbono['abon_tx_dataCadastro'],1);

			$iconeAbono =  "<a><i style='color:orange;' title='$tooltip' class='fa fa-warning'></i></a>";
			$aRetorno['temPendencias'] = True;
		}else{
			$tooltip = $iconeAbono = '';
		}

		
		// INICIO CALCULO JORNADA TRABALHO (DESCONSIDEREANDO PAUSAS)
		for ($i=0; $i < count($arrayInicioJornada); $i++) { 
			$dataHoraInicioJornada = new DateTime($data." ".$arrayInicioJornada[$i]);
			$dataHoraFimJornada = new DateTime($data." ".$arrayFimJornada[$i]);

			$diffJornada = $dataHoraInicioJornada->diff($dataHoraFimJornada);
			$arrayDiffJornada[] = $diffJornada->format("%H:%I");
		}

		
		$aRetorno['diffJornada'] = $jornadaOrdenado['icone'].$jornadaOrdenado['totalIntervalo'];
		// FIM CALCULO CALCULO JORNADA TRABALHO
		
		
		// INICIO JORNADA ESPERADA
		$dataJornadaPrevista = DateTime::createFromFormat('Y-m-d H:i', $data." ".$cargaHoraria);
		$dataAbono = new DateTime($data." ".$aAbono['abon_tx_abono']);
		$diffJornadaPrevista = $dataAbono->diff($dataJornadaPrevista);

		$aRetorno['jornadaPrevista'] = $diffJornadaPrevista->format("%H:%I");
		// FIM JORNADA ESPERADA

		// INICIO CALCULO JORNADA EFETIVAMENTE DO DIA
		$horaTotal = new DateTime($jornadaOrdenado['totalIntervalo']);

		//SOMATORIO DE TODAS AS ESPERAS
		$horaTotalIntervalos = new DateTime(somarHorarios([$refeicaoOrdenada['totalIntervalo'], $esperaOrdenada['totalIntervalo'], $descansoOrdenado['totalIntervalo'], $repousoOrdenado['totalIntervalo']]));

		$diffJornadaEfetiva = $horaTotal->diff($horaTotalIntervalos);
		$aRetorno['diffJornadaEfetiva'] = verificaTempo($diffJornadaEfetiva->format("%H:%I"), $horaAlertaJornadaEfetiva);
		// FIM CALCULO JORNADA EFETIVAMENTE DO DIA

		// INICIO CALCULO INTERSTICIO
		if($inicioDataAdicional != ''){

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
			
			$diffIntersticio = somarHorarios([$hInter, $horaTotalIntervalos->format("H:i"), $jornadaOrdenado['interjornada']]);


			list($hours, $minutes) = explode(':', $diffIntersticio);
			$tsInterTotal = $hours*3600 + $minutes*60;

			list($hours, $minutes) = explode(':', $hInter);
			$hInter = $hours*3600 + $minutes*60;

			$iconeInter = '';
			
			if($tsInterTotal < (11*3600)){
				$iconeInter .= "<a><i style='color:red;' title='Interstício Total de 11:00 não respeitado' class='fa fa-warning'></i></a>";
				$aRetorno['temPendencias'] = True;
			}
			if($hInter < (8*3600)){
				$iconeInter .= "<a><i style='color:red;' title='O mínimo de 08:00h ininterruptas no primeiro período, não respeitado.' class='fa fa-warning'></i></a>";
				$aRetorno['temPendencias'] = True;
			}


			$aRetorno['intersticio'] = $iconeInter.$diffIntersticio;
		}

		// FIM CALCULO INTERSTICIO





		//CALCULO ESPERA INDENIZADA
		$horaJornadaEsperada = DateTime::createFromFormat('H:i', $aRetorno['jornadaPrevista']);
		$horario1 = DateTime::createFromFormat('H:i', ($diffJornadaEfetiva->format("%H:%I")));
		$horario2 = DateTime::createFromFormat('H:i', somarHorarios([$esperaOrdenada['totalIntervalo'], $esperaParaRepousoOrdenado['totalIntervalo']]));

		$esperaIndenizada = clone $horario1;
		$esperaIndenizada->add(new DateInterval('PT' . $horario2->format('H') . 'H' . $horario2->format('i') . 'M'));

		// $jornadaEfetiva = $esperaIndenizada->format('H:i');
		$jornadaEfetiva = $diffJornadaEfetiva->format("%H:%I");
		
		$dateCargaHoraria = new DateTime($aRetorno['jornadaPrevista']);
		$dateJornadaEfetiva = new DateTime($jornadaEfetiva);
		$saldoPositivo = $dateCargaHoraria->diff($dateJornadaEfetiva)->format("%r");

		if ($esperaIndenizada > $horaJornadaEsperada && $horario1 < $horaJornadaEsperada ) {

			$esperaIndenizada = $horaJornadaEsperada->diff($esperaIndenizada)->format('%H:%I');
			// SE JORNADA EFETIVA FOR MENOR QUE A JORNADA ESPERADA, NAO GERA SALDO
			
			$jornadaEfetiva = $aRetorno['jornadaPrevista'];
			
		} else {
			$esperaIndenizada = ($saldoPositivo == '-' || $horario2->format('H:i') == '00:00')? '': $horario2->format('H:i');
		}

		$aRetorno['esperaIndenizada'] = $esperaIndenizada;
		
		//FIM CALCULO ESPERA INDENIZADA


		//INICIO ADICIONAL NOTURNO
		$jornadaNoturno = $jornadaOrdenado['totalIntervaloAdicionalNot'];
		
		$refeicaoNoturno = $refeicaoOrdenada['totalIntervaloAdicionalNot'];
		$esperaNoturno   = $esperaOrdenada['totalIntervaloAdicionalNot'];
		$descansoNoturno = $descansoOrdenado['totalIntervaloAdicionalNot'];
		$repousoNoturno  = $repousoOrdenado['totalIntervaloAdicionalNot'];

		$intervalosNoturnos = somarHorarios([$refeicaoNoturno, $esperaNoturno, $descansoNoturno, $repousoNoturno]);

		$adicionalNoturno = gmdate('H:i' , (strtotime($data.' '.$jornadaNoturno)) - (strtotime($data.' '.$intervalosNoturnos)));
		$aRetorno['adicionalNoturno'] = $adicionalNoturno == '00:00' ? '' : $adicionalNoturno;

		//FIM ADICIONAL NOTURNO

		//CALCULO SALDO
		$dateCargaHoraria = new DateTime($aRetorno['jornadaPrevista']);
		$dateJornadaEfetiva = new DateTime($jornadaEfetiva);

		$diffSaldo = $dateCargaHoraria->diff($dateJornadaEfetiva);
		
		$aRetorno['diffSaldo'] = $diffSaldo->format("%r%H:%I");
		
		//FIM CALCULO SALDO



		
		if($stringFeriado != ''){
			$iconeFeriado =  "<a><i style='color:orange;' title='$stringFeriado' class='fa fa-info-circle'></i></a>";
			$aRetorno['he100'] = $iconeFeriado.$aRetorno['diffSaldo'];
		}elseif(date('%w',strtotime($data)) == '0'){
			$aRetorno['he100'] = $aRetorno['diffSaldo'];
		}elseif($aRetorno['diffSaldo'][0] != '-'){
			$aRetorno['he50'] = $aRetorno['diffSaldo'];
		}

		
		$mdc = maxDirecaoContinua($arrayMDC);

		$aRetorno['maximoDirecaoContinua'] = verificaTempoMdc($mdc, $descansoOrdenado['totalIntervalo']);


		// VERIFICA SE A JORNADA É MAIOR QUE 06:00
		$dtJornada = new DateTime($data.' '.$diffJornadaEfetiva->format("%H:%I"));
		$dtJornadaMinima = new DateTime($data.' '.'06:00');
		
		$verificaJornadaMinima = (
			($dtJornada > $dtJornadaMinima) && ($diffJornadaEfetiva->format("%H:%I") != '00:00') || 
			($aRetorno['jornadaPrevista'] != '00:00') && ($diffJornadaEfetiva->format("%H:%I") == '00:00')
		)+0;
		//FIM VERIFICA JORNADA MINIMA

		//VERIFICA SE HOUVE 01:00 DE REFEICAO
		$dtRefeicaoMinima = new DateTime($data.' '.'01:00');
		$menor1h = 0;	
		for ($i=0; $i < count($refeicaoOrdenada['pares']); $i++) { 
			$dtIntervaloRefeicao = new DateTime($data.' '.$refeicaoOrdenada['pares'][$i]['intervalo']);
			if($dtIntervaloRefeicao < $dtRefeicaoMinima){
				$menor1h = 1;
			}
		}

		if($aRetorno['diffRefeicao'] == '00:00') $menor1h = 1;

		if($menor1h && $dtJornada > $dtJornadaMinima){
			$aRetorno['diffRefeicao'] = "<a><i style='color:red;' title='Refeição com tempo ininterrupto mínimo de 01:00h, não respeitado.' class='fa fa-warning'></i></a>" . $aRetorno['diffRefeicao'];
			$aRetorno['temPendencias'] = True;
		}else{
			$iconeRefeicaoMinima = '';
		}

		if($verificaJornadaMinima == 1){
			if($arrayInicioJornada[0] == ''){
				$aRetorno['inicioJornada']  = "<a><i style='color:red;' title='Batida início de jornada não registrada!' class='fa fa-warning'></i></a>";
			}
			if($arrayFimJornada[0] == ''){
				$aRetorno['fimJornada']     = "<a><i style='color:red;' title='Batida fim de jornada não registrada!' class='fa fa-warning'></i></a>";
			}
			if($aRetorno['inicioRefeicao'] == ''){
				$aRetorno['inicioRefeicao'] = "<a><i style='color:red;' title='Batida início de refeição não registrada!' class='fa fa-warning'></i></a>".$iconeRefeicaoMinima;
			}
			if($aRetorno['fimRefeicao'] == ''){
				$aRetorno['fimRefeicao']    = "<a><i style='color:red;' title='Batida fim de refeição não registrada!' class='fa fa-warning'></i></a>".$iconeRefeicaoMinima;
			}

			if($arrayInicioJornada[0] == '' || $arrayFimJornada[0] == '' || $aRetorno['inicioRefeicao'] == '' || $aRetorno['fimRefeicao'] == '') $aRetorno['temPendencias'] = True;
		}
		if($iconeAbono != ''){
			$aRetorno['jornadaPrevista'] = $iconeAbono."&nbsp;".$aRetorno['jornadaPrevista'];
		}

		$arrays = [
			['inicioJornada', 	$arrayInicioJornada],
			['fimJornada', 		$arrayFimJornada],
			['inicioRefeicao', 	$arrayInicioRefeicaoFormatado],
			['fimRefeicao', 	$arrayFimRefeicaoFormatado]
		];
		foreach($arrays as $array){
			if(count($array[1]) > 0){
				$aRetorno[$array[0]] = implode("<br>", $array[1]);
			}
		}

		if(count($aDataHorainicioEspera) > 0 && count($aDataHorafimEspera) > 0){
			$aRetorno['diffEspera'] = $esperaOrdenada['icone'].$esperaOrdenada['totalIntervalo'];
		}
		if (count($aDataHorainicioDescanso) > 0 && count($aDataHorafimDescanso) > 0) {
			$aRetorno['diffDescanso'] =  $descansoOrdenado['icone'] . verificaTempo($descansoOrdenado['totalIntervalo'], "05:30");
		}
		if (count($aDataHorainicioRepouso) > 0 && count($aDataHorafimRepouso) > 0) {
			$aRetorno['diffRepouso'] = $repousoOrdenado['icone'] . $repousoOrdenado['totalIntervalo'];
		}

		//switch case não utilizado pois há um break dentro de uma das condições, que buga quando há um switch case por fora.
		if($status == 'Com alerta(s)'){
			$temPendencias = False;
			foreach($aRetorno as $dado){
				if(is_int(strpos(strval($dado),  'fa-warning')) && is_int(strpos(strval($dado),  'color:red'))){
					$temPendencias = True;
					break;
				}
			}
			if(!$temPendencias){
				return -1;
			}
		}elseif($status == 'Com alerta de refeição'){
			if( is_bool(strpos(strval($aRetorno['inicioRefeicao']), 'fa-warning')) &&
				is_bool(strpos(strval($aRetorno['fimRefeicao']),    'fa-warning'))
			){
				# Não tem alerta de refeição
				return -1;
			}
		}elseif($status == 'Com alerta na jornada efetiva'){
			if(is_bool(strpos(strval($aRetorno['diffJornadaEfetiva']), 'fa-warning'))){
				# Não tem alerta na jornada efetiva
				return -1;
			}
		}elseif($status == 'Sem pendências'){ //Contrário do "Com alerta(s)"
			$temPendencias = False;
			foreach($aRetorno as $dado){
				if(is_int(strpos(strval($dado),  'fa-warning')) && is_int(strpos(strval($dado),  'color:red'))){
					$temPendencias = True;
					break;
				}
			}
			if($temPendencias){
				return -1;
			}
		}elseif($status == 'Com saldo negativo' || $status == 'Com saldo positivo' && ($aRetorno['diffSaldo'][0] != '-' || $aRetorno['diffSaldo'] == '')){
			# Saldo negativo ou positivo
			return -1;
		}elseif($status == 'Com saldo previsto' && is_bool(strpos(strval($aRetorno['jornadaPrevista']),  '00:00'))){
			//Não tem a jornada prevista zerada
			return -1;
		}

		// TOTALIZADOR 
		foreach(['diffRefeicao', 'diffEspera', 'diffDescanso', 'diffRepouso', 'diffJornada', 'jornadaPrevista', 'diffJornadaEfetiva', 'intersticio', 'he50', 'he100', 'adicionalNoturno', 'esperaIndenizada', 'diffSaldo'] as $campo){
			$totalResumo[$campo] = somarHorarios([$totalResumo[$campo], strip_tags($aRetorno[$campo])]);
		}
		$totalResumo['maximoDirecaoContinua'] = '';
		
		if(($aRetorno['inicioJornada'] == '' && date('%w',strtotime($data)) == '%6') || ($aRetorno['inicioJornada'] != '' && date('%w',strtotime($data)) == '%0')){
			$aRetorno['jornadaPrevista'] = '00:00';
		}else{
			$aRetorno['jornadaPrevista'] = operarHorarios([$aRetorno['jornadaPrevista'], $aRetorno['diffJornadaEfetiva']], '-');
		}

		$legendas = [
			null => '',
			'' => '',
			'I' => 'Incluída Manualmente',
			'P' => 'Pré-Assinalada',
			'T' => 'Outras fontes de marcação',
			'DSR' => 'Descanso Semanal Remunerado e Abono'
		];

		$aRetorno['moti_tx_motivo'] = $legendas[$aAbono['moti_tx_legenda']];

		return $aRetorno;
	}

	function diaDetalheEndosso2($aMotorista, $data, $status = ''){
		global $totalResumo, $contagemEspera;
		setlocale(LC_ALL, 'pt_BR.utf8');

		$aRetorno['data'] = data($data);
		// $aRetorno['diaSemana'] = pegarDiaDaSemana(strtotime($data));
		$aRetorno['diaSemana'] = substr(iconv('UTF-8', 'ASCII//TRANSLIT', pegarDiaDaSemana(strtotime($data))), 0, 3);
		$campos = [
			'inicioJornada', 'inicioRefeicao', 'fimRefeicao', 'fimJornada', 
			'diffRefeicao', 'diffEspera', 'diffDescanso', 'diffRepouso', 'diffJornada', 
			'jornadaPrevista', 'diffJornadaEfetiva', 'maximoDirecaoContinua', 'intersticio', 
			'he50', 'he100', 'adicionalNoturno', 'esperaIndenizada', 'diffSaldo', 'temPendencias'
		];
		foreach($campos as $campo){
			$aRetorno[$campo] = '';
		}
		unset($campos);

		// <CONSULTAS NO BD>

		if (isset($aMotorista['enti_nb_empresa']) && isset($aMotorista['empr_nb_cidade']) && isset($aMotorista['cida_nb_id']) && isset($aMotorista['cida_tx_uf'])) {
			$extraFeriado = " AND (
				(feri_nb_cidade = '".$aMotorista["cida_nb_id"]."' AND feri_tx_uf = '".$aMotorista["cida_tx_uf"]."')
				OR (feri_nb_cidade IS NULL AND feri_tx_uf IS NULL)
				OR (feri_nb_cidade = 0 AND feri_tx_uf = ''))";
		}else{
			$extraFeriado = '';
		}

		$sql = query(
			"SELECT feri_tx_nome FROM feriado
				WHERE feri_tx_data LIKE '%-".substr($data, 5, 5)."%'
				AND feri_tx_status != 'inativo' $extraFeriado"
		);
		$stringFeriado = '';
		while($row = carrega_array($sql)){
			$stringFeriado .= $row[0]."\n";
		}
		
		$clock_info = [
			'inicioJornada' => '',		//inicioJornada
			'fimJornada' => '',			//fimJornada
			'inicioRefeicao' => '',		//inicioRefeicao
			'fimRefeicao' => '',		//fimRefeicao
			'inicioEspera' => '',		//inicioEspera
			'fimEspera' => '',			//fimEspera
			'inicioDescanso' => '',		//inicioDescanso
			'fimDescanso' => '',		//fimDescanso
			'inicioRepouso' => '',		//inicioRepouso
			'fimRepouso' => ''			//fimRepouso
		];

		$sql = query("SELECT * FROM ponto WHERE pont_tx_status != 'inativo' AND pont_tx_matricula = '$matricula'  AND pont_tx_data LIKE '$data%' ORDER BY pont_tx_data ASC");
		$pontosTipo = [
			'1' => 'inicioJornada',
			'2' => 'fimJornada',
			'3' => 'inicioRefeicao',
			'4' => 'fimRefeicao',
			'5' => 'inicioEspera',
			'6' => 'fimEspera',
			'7' => 'inicioDescanso',
			'8' => 'fimDescanso',
			'9' => 'inicioRepouso',
			'10' => 'fimRepouso',
			'11' => 'inicioRepouso',
			'12' => 'fimRepouso'
		];
		while($aDia = carrega_array($sql)){
			$arrayMDC[] = [$aDia['pont_tx_data']/*Alterado*/, $aDia['pont_tx_tipo']];
			$clock_info[$pontosTipo[$aDia['pont_tx_tipo']]] = $aDia['pont_tx_data'];
		}
		unset($pontosTipo);

		$aAbono = carrega_array(query(
			"SELECT * FROM abono, motivo, user
				WHERE abon_tx_status != 'inativo' AND abon_nb_userCadastro = user_nb_id
				AND abon_tx_matricula = '$matricula' AND abon_tx_data = '$data' AND abon_nb_motivo = moti_nb_id
				ORDER BY abon_nb_id DESC LIMIT 1"
		));

		$calculate_interstice = !empty($clock_info['inicioJornada']);
		if($calculate_interstice){
			$aDiaAnterior = carrega_array(query(
				"SELECT pont_tx_data FROM ponto 
					WHERE pont_tx_status != 'inativo' AND 
						pont_tx_matricula = '$matricula' AND 
						pont_tx_data < '$data' 
					ORDER BY pont_tx_data DESC 
					LIMIT 1"
			));
		}

		// </CONSULTAS NO BD>
		
		$clock_info['jornada'] 	= ordenar_horarios($clock_info['inicioJornada'], $clock_info['fimJornada']);
		$clock_info['refeicao'] = ordenar_horarios($clock_info['inicioRefeicao'], $clock_info['fimRefeicao']);
		$clock_info['espera'] 	= ordenar_horarios($clock_info['inicioEspera'], $clock_info['fimEspera'], true);
		$clock_info['descanso'] = ordenar_horarios($clock_info['inicioDescanso'], $clock_info['fimDescanso']);
		
		if(isset($esperaOrdenada['paresParaRepouso'])){
			foreach($esperaOrdenada['paresParaRepouso'] as $parRepouso){
				$clock_info['inicioRepouso'][] 		 = $data.' '.$parRepouso['inicio'];
				$clock_info['inicioEsperaRepouso'][] = $data.' '.$parRepouso['inicio'];
				$clock_info['fimRepouso'][] 		 = $data.' '.$parRepouso['fim'];
				$clock_info['fimEsperaRepouso'][] 	 = $data.' '.$parRepouso['fim'];
			}
			$esperaParaRepousoOrdenado = ordenar_horarios($clock_info['inicioEsperaRepouso'], $clock_info['fimEsperaRepouso']);
		}
		$repousoOrdenado = ordenar_horarios($clock_info['inicioRepouso'], $clock_info['fimRepouso']);

		$aRetorno['diffRefeicao'] = $clock_info['refeicao']['icone'] .$clock_info['refeicao']['totalIntervalo'];
		$aRetorno['diffEspera']   = $clock_info['espera']['icone']	 .$clock_info['espera']['totalIntervalo'];
		$aRetorno['diffDescanso'] = $clock_info['descanso']['icone'] .$clock_info['descanso']['totalIntervalo'];
		$aRetorno['diffRepouso']  = $clock_info['repouso']['icone']	 .$clock_info['repouso']['totalIntervalo'];

		$contagemEspera += count($clock_info['espera']['pares']);

		if(date('%w',strtotime($data)) == '6'){ //SABADOS
			$cargaHoraria = $aMotorista['enti_tx_jornadaSabado'];
		}elseif(date('%w',strtotime($data)) == '0' || $stringFeriado != ''){ //DOMINGOS OU FERIADOS
			//Alterar para uma variável do parâmetro caso exista a possibilidade de carga horária no domingo.
			$cargaHoraria = '00:00';
		}else{
			$cargaHoraria = $aMotorista['enti_tx_jornadaSemanal'];
		}

		if($aAbono[0] > 0){
			$tooltip = "Jornada Original: ".str_pad($cargaHoraria, 2, '0', STR_PAD_LEFT).":00:00\n".
					"Abono: ".$aAbono['abon_tx_abono']."\n".
					"Motivo: ".$aAbono['moti_tx_nome']."\n".
					"Justificativa: ".$aAbono['abon_tx_descricao']."\n\n".
					"Registro efetuado por ".$aAbono['user_tx_login']." em ".data($aAbono['abon_tx_dataCadastro'],1);

			$iconeAbono =  "<a><i style='color:orange;' title='$tooltip' class='fa fa-warning'></i></a>";
			$aRetorno['temPendencias'] = True;
		}else{
			$tooltip = $iconeAbono = '';
		}

		// <JORNADA TRABALHO> (DESCONSIDEREANDO PAUSAS)
			for ($i=0; $i < count($clock_info['inicioJornada']); $i++) {
				$dataHoraInicioJornada = new DateTime($data." ".date('H:i', strtotime($clock_info['inicioJornada'][$i])));
				$dataHoraFimJornada = new DateTime($data." ".date('H:i', strtotime($clock_info['fimJornada'][$i])));
				
				$diffJornada = $dataHoraInicioJornada->diff($dataHoraFimJornada);
				$arrayDiffJornada[] = $diffJornada->format("%H:%I");
			}
			
			
			$aRetorno['diffJornada'] = $clock_info['jornada']['icone'].$clock_info['jornada']['totalIntervalo'];
		// </JORNADA TRABALHO> (DESCONSIDEREANDO PAUSAS)
		
		// <JORNADA PREVISTA>
			$dataJornadaPrevista = new DateTime($data." ".$cargaHoraria);
			$dataAbono = new DateTime($data." ".$aAbono['abon_tx_abono']);
			$diffJornadaPrevista = $dataAbono->diff($dataJornadaPrevista);
			
			$aRetorno['jornadaPrevista'] = $diffJornadaPrevista->format("%H:%I");
		// </JORNADA PREVISTA>
		
		// <JORNADA EFETIVA>
			$horaTotal = new DateTime($clock_info['jornada']['totalIntervalo']);
			$horaAlertaJornadaEfetiva = ($aMotorista['para_tx_acordo'] == 'Sim')? '12:00': '10:00';

			//SOMATORIO DE TODAS AS ESPERAS
			$horaTotalIntervalos = new DateTime(somarHorarios([$clock_info['refeicao']['totalIntervalo'], $clock_info['espera']['totalIntervalo'], $clock_info['descanso']['totalIntervalo'], $repousoOrdenado['totalIntervalo']]));

			$diffJornadaEfetiva = $horaTotal->diff($horaTotalIntervalos);
			$aRetorno['diffJornadaEfetiva'] = verificaTempo($diffJornadaEfetiva->format("%H:%I"), $horaAlertaJornadaEfetiva);
		// </JORNADA EFETIVA>

		// <INTERSTICIO>
			if($calculate_interstice){
				$dateInicioJornada = new DateTime($clock_info['inicioJornada']);
				$dateDiaAnterior = new DateTime($aDiaAnterior['pont_tx_data']);
				$diffJornada = $dateInicioJornada->diff($dateDiaAnterior);

				// Obter a diferença total em minutos
				$diffMinutos = ($diffJornada->days*24*60)+($diffJornada->h*60)+$diffJornada->i;

				// Calcular as horas e minutos
				$horas = floor($diffMinutos / 60);
				$minutos = $diffMinutos % 60;

				// Formatar a string no formato HH:MM
				$hInter = sprintf("%02d:%02d", $horas, $minutos);
				
				$diffIntersticio = somarHorarios([$hInter, $horaTotalIntervalos->format("H:i"), $clock_info['jornada']['interjornada']]);


				$tsInterTotal = explode(':', $diffIntersticio);
				$tsInterTotal = $tsInterTotal[0]*3600 + $tsInterTotal[1]*60;

				$hInter = explode(':', $hInter);
				$hInter = $hInter[0]*3600 + $hInter[1]*60;

				$iconeInter = '';
				
				if($tsInterTotal < (11*3600)){
					$iconeInter .= "<a><i style='color:red;' title='Interstício Total de 11:00 não respeitado' class='fa fa-warning'></i></a>";
					$aRetorno['temPendencias'] = True;
				}
				if($hInter < (8*3600)){
					$iconeInter .= "<a><i style='color:red;' title='O mínimo de 08:00h ininterruptas no primeiro período, não respeitado.' class='fa fa-warning'></i></a>";
					$aRetorno['temPendencias'] = True;
				}


				$aRetorno['intersticio'] = $iconeInter.$diffIntersticio;
			}
		// </INTERSTICIO>

		//CALCULO ESPERA INDENIZADA
		$horaJornadaEsperada = DateTime::createFromFormat('H:i', $aRetorno['jornadaPrevista']);
		$horario1 = DateTime::createFromFormat('H:i', ($diffJornadaEfetiva->format("%H:%I")));
		$horario2 = DateTime::createFromFormat('H:i', somarHorarios([$clock_info['espera']['totalIntervalo'], $esperaParaRepousoOrdenado['totalIntervalo']]));

		$esperaIndenizada = $horario1;
		$esperaIndenizada->add(new DateInterval('PT' . $horario2->format('H') . 'H' . $horario2->format('i') . 'M'));

		// $jornadaEfetiva = $esperaIndenizada->format('H:i');
		$jornadaEfetiva = $diffJornadaEfetiva->format("%H:%I");
		
		$dateCargaHoraria = new DateTime($aRetorno['jornadaPrevista']);
		$dateJornadaEfetiva = new DateTime($jornadaEfetiva);
		$saldoPositivo = $dateCargaHoraria->diff($dateJornadaEfetiva)->format("%r");

		if ($esperaIndenizada > $horaJornadaEsperada && $horario1 < $horaJornadaEsperada ) {

			$esperaIndenizada = $horaJornadaEsperada->diff($esperaIndenizada)->format('%H:%I');
			// SE JORNADA EFETIVA FOR MENOR QUE A JORNADA ESPERADA, NAO GERA SALDO
			
			$jornadaEfetiva = $aRetorno['jornadaPrevista'];
			
		} else {
			$esperaIndenizada = ($saldoPositivo == '-' || $horario2->format('H:i') == '00:00')? '': $horario2->format('H:i');
		}

		$aRetorno['esperaIndenizada'] = $esperaIndenizada;
		
		//FIM CALCULO ESPERA INDENIZADA


		//INICIO ADICIONAL NOTURNO
		$jornadaNoturno  = $clock_info['jornada']['totalIntervaloAdicionalNot'];
		$refeicaoNoturno = $clock_info['refeicao']['totalIntervaloAdicionalNot'];
		$esperaNoturno   = $clock_info['espera']['totalIntervaloAdicionalNot'];
		$descansoNoturno = $clock_info['descanso']['totalIntervaloAdicionalNot'];
		$repousoNoturno  = $repousoOrdenado['totalIntervaloAdicionalNot'];

		$intervalosNoturnos = somarHorarios([$refeicaoNoturno, $esperaNoturno, $descansoNoturno, $repousoNoturno]);

		$adicionalNoturno = gmdate('H:i' , (strtotime($data.' '.$jornadaNoturno)) - (strtotime($data.' '.$intervalosNoturnos)));
		$aRetorno['adicionalNoturno'] = $adicionalNoturno == '00:00' ? '' : $adicionalNoturno;

		//FIM ADICIONAL NOTURNO

		//CALCULO SALDO
		$dateCargaHoraria = new DateTime($aRetorno['jornadaPrevista']);
		$dateJornadaEfetiva = new DateTime($jornadaEfetiva);

		$diffSaldo = $dateCargaHoraria->diff($dateJornadaEfetiva);
		
		$aRetorno['diffSaldo'] = $diffSaldo->format("%r%H:%I");
		
		//FIM CALCULO SALDO


		if($stringFeriado != ''){
			$iconeFeriado =  "<a><i style='color:orange;' title='$stringFeriado' class='fa fa-info-circle'></i></a>";
			$aRetorno['he100'] = $iconeFeriado.$aRetorno['diffSaldo'];
		}elseif(date('%w',strtotime($data)) == '0'){
			$aRetorno['he100'] = $aRetorno['diffSaldo'];
		}elseif($aRetorno['diffSaldo'][0] != '-'){
			$aRetorno['he50'] = $aRetorno['diffSaldo'];
		}

		
		$mdc = maxDirecaoContinua($arrayMDC);

		$aRetorno['maximoDirecaoContinua'] = verificaTempoMdc($mdc, $clock_info['descanso']['totalIntervalo']);


		// VERIFICA SE A JORNADA É MAIOR QUE 06:00
		$dtJornada = new DateTime($data.' '.$diffJornadaEfetiva->format("%H:%I"));
		$dtJornadaMinima = new DateTime($data.' '.'06:00');
		
		$jornadaMinimaVerificada = (
			($dtJornada > $dtJornadaMinima)
			&& ($diffJornadaEfetiva->format("%H:%I") != '00:00')
			|| ($aRetorno['jornadaPrevista'] != '00:00')
			&& ($diffJornadaEfetiva->format("%H:%I") == '00:00')
		);
		//FIM VERIFICA JORNADA MINIMA

		//VERIFICA SE HOUVE 01:00 DE REFEICAO
		$dtRefeicaoMinima = new DateTime($data.' '.'01:00');
		$menor1h = 0;	
		for ($i=0; $i < count($clock_info['refeicao']['pares']); $i++) { 
			$dtIntervaloRefeicao = new DateTime($data.' '.$clock_info['refeicao']['pares'][$i]['intervalo']);
			if($dtIntervaloRefeicao < $dtRefeicaoMinima){
				$menor1h = 1;
			}
		}

		if($aRetorno['diffRefeicao'] == '00:00') $menor1h = 1;

		if($menor1h && $dtJornada > $dtJornadaMinima){
			$aRetorno['diffRefeicao'] = "<a><i style='color:red;' title='Refeição com tempo ininterrupto mínimo de 01:00h, não respeitado.' class='fa fa-warning'></i></a>" . $aRetorno['diffRefeicao'];
			$aRetorno['temPendencias'] = True;
		}else{
			$iconeRefeicaoMinima = '';
		}

		if($jornadaMinimaVerificada){
			$warning = ["<a><i style='color:red;' title='Batida", " não registrada!' class='fa fa-warning'></i></a>"];
			
			if($clock_info['inicioJornada'][0] == ''){
				$aRetorno['inicioJornada'] = $warning[0]." início de jornada".$warning[1];
				$aRetorno['temPendencias'] = True;
			}
			if($clock_info['fimJornada'][0] == ''){
				$aRetorno['fimJornada'] = $warning[0]." fim de jornada".$warning[1];
				$aRetorno['temPendencias'] = True;
			}
			if($aRetorno['inicioRefeicao'] == ''){
				$aRetorno['inicioRefeicao'] = $warning[0]." início de refeição".$warning[1].$iconeRefeicaoMinima;
				$aRetorno['temPendencias'] = True;
			}
			if($aRetorno['fimRefeicao'] == ''){
				$aRetorno['fimRefeicao'] = $warning[0]." fim de refeição".$warning[1].$iconeRefeicaoMinima;
				$aRetorno['temPendencias'] = True;
			}

			unset($warning);
		}
		if($iconeAbono != ''){
			$aRetorno['jornadaPrevista'] = $iconeAbono."&nbsp;".$aRetorno['jornadaPrevista'];
		}

		$time_arrays = [
			['inicioJornada', 	$clock_info['inicioJornada']],
			['fimJornada', 		$clock_info['fimJornada']],
			['inicioRefeicao', 	$clock_info['inicioRefeicao']],
			['fimRefeicao', 	$clock_info['fimRefeicao']]
		];
		foreach($time_arrays as $time_array){
			if(count($time_array[1]) > 0){
				$aRetorno[$time_array[0]] = implode("<br>", $time_array[1]);
			}
		}

		if(count($clock_info['inicioEspera']) > 0 && count($clock_info['fimEspera']) > 0){
			$aRetorno['diffEspera'] = $clock_info['espera']['icone'].$clock_info['espera']['totalIntervalo'];
		}
		if (count($clock_info['inicioDescanso']) > 0 && count($clock_info['fimDescanso']) > 0) {
			$aRetorno['diffDescanso'] =  $clock_info['descanso']['icone'] . verificaTempo($clock_info['descanso']['totalIntervalo'], "05:30");
		}
		if (count($clock_info['inicioRepouso']) > 0 && count($clock_info['fimRepouso']) > 0) {
			$aRetorno['diffRepouso'] = $repousoOrdenado['icone'] . $repousoOrdenado['totalIntervalo'];
		}

		//switch case não utilizado pois há um break dentro de uma das condições, que buga quando há um switch case por fora.
		if($status == 'Com alerta(s)'){
			$temPendencias = False;
			foreach($aRetorno as $dado){
				if(is_int(strpos(strval($dado),  'fa-warning')) && is_int(strpos(strval($dado),  'color:red'))){
					$temPendencias = True;
					break;
				}
			}
			if(!$temPendencias){
				return -1;
			}
		}elseif($status == 'Com alerta de refeição'){
			if( is_bool(strpos(strval($aRetorno['inicioRefeicao']), 'fa-warning')) &&
				is_bool(strpos(strval($aRetorno['fimRefeicao']),    'fa-warning'))
			){
				# Não tem alerta de refeição
				return -1;
			}
		}elseif($status == 'Com alerta na jornada efetiva'){
			if(is_bool(strpos(strval($aRetorno['diffJornadaEfetiva']), 'fa-warning'))){
				# Não tem alerta na jornada efetiva
				return -1;
			}
		}elseif($status == 'Sem pendências'){ //Contrário do "Com alerta(s)"
			$temPendencias = False;
			foreach($aRetorno as $dado){
				if(is_int(strpos(strval($dado),  'fa-warning')) && is_int(strpos(strval($dado),  'color:red'))){
					$temPendencias = True;
					break;
				}
			}
			if($temPendencias){
				return -1;
			}
		}elseif($status == 'Com saldo negativo' || $status == 'Com saldo positivo' && ($aRetorno['diffSaldo'][0] != '-' || $aRetorno['diffSaldo'] == '')){
			# Saldo negativo ou positivo
			return -1;
		}elseif($status == 'Com saldo previsto' && is_bool(strpos(strval($aRetorno['jornadaPrevista']),  '00:00'))){
			//Não tem a jornada prevista zerada
			return -1;
		}

		// TOTALIZADOR 
		foreach(['diffRefeicao', 'diffEspera', 'diffDescanso', 'diffRepouso', 'diffJornada', 'jornadaPrevista', 'diffJornadaEfetiva', 'intersticio', 'he50', 'he100', 'adicionalNoturno', 'esperaIndenizada', 'diffSaldo'] as $campo){
			$totalResumo[$campo] = somarHorarios([$totalResumo[$campo], strip_tags($aRetorno[$campo])]);
		}
		$totalResumo['maximoDirecaoContinua'] = '';
		
		if(($aRetorno['inicioJornada'] == '' && date('%w',strtotime($data)) == '%6') || ($aRetorno['inicioJornada'] != '' && date('%w',strtotime($data)) == '%0')){
			$aRetorno['jornadaPrevista'] = '00:00';
		}else{
			$aRetorno['jornadaPrevista'] = operarHorarios([$aRetorno['jornadaPrevista'],  $aRetorno['diffJornadaEfetiva']], '-');
		}

		$legendas = [
			null => '',
			'' => '',
			'I' => 'Incluída Manualmente',
			'P' => 'Pré-Assinalada',
			'T' => 'Outras fontes de marcação',
			'DSR' => 'Descanso Semanal Remunerado e Abono'
		];

		$aRetorno['moti_tx_motivo'] = $legendas[$aAbono['moti_tx_legenda']];
		
		return $aRetorno;
	}	
?>