<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
		
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
        header("Pragma: no-cache"); // HTTP 1.0.
        header("Expires: 0");
	//*/
	include_once __DIR__."/conecta.php";

	function calcularAbono($horario1, $horario2){
		// Converter os horários em minutos
		$horario1 = str_replace("-", "", $horario1);
		$horario2 = str_replace("-", "", $horario2);
		$saldoPositivo = $horario1;

		$minutos1 = intval(substr($horario1, 0, 2)) * 60 + intval(substr($horario1, 3, 2));
		$minutos2 = intval(substr($horario2, 0, 2)) * 60 + intval(substr($horario2, 3, 2));

		$diferencaMinutos = $minutos2 - $minutos1;

		if($diferencaMinutos > 0){
			return $saldoPositivo;
		}
		
		return $horario2;
	}

	function layout_ajuste(){
		global $CONTEX;
		echo "<form action='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/ajuste_ponto.php' name='form_ajuste_ponto' method='post'>";
		unset($_POST["acao"]);
		foreach($_POST as $key => $value){
			echo "<input type='hidden' name='".$key."' value='".$value."'>";
		}
		echo "</form>";
		
		echo "<script>document.form_ajuste_ponto.submit();</script>";
		exit;
	}

	function excluir_ponto(){
		if(empty($_POST["id"]) || empty($_POST["just"]) || empty($_POST["atualiza"])){
			return false;
		}

		$a=carregar("ponto", (int)$_POST["id"]);
		remover_ponto((int)$_POST["id"], $_POST["just"], $_POST["atualiza"]);
		
		$_POST["id"] = $_POST["idEntidade"];
		$_POST["data"] = substr($a["pont_tx_data"],0, -9);
		$_POST["busca_data"] = $a["pont_tx_data"];

		layout_ajuste();
		exit;
	}

	function operarHorarios(array $horarios, string $operacao): string{
		//Horários com formato de rH:i. Ex.: 00:04, 05:13, -01:12.
		//$Operação

		if(count($horarios) == 0 || !in_array($operacao, ["+", "-", "*", "/"])){
			return 0;
		}

		foreach($horarios as $horario){
			if(empty($horario)){
				$horario = "00:00";
			}
			if(!preg_match("/^-?\d{2,10}:\d{2}$/", $horario)){
				echo "<script>console.log('".("Format error: |".strval($horario)."|")."')</script>";
			}
		}

		$negative = ($horarios[0][0] == "-");
		$result = explode(":", $horarios[0]);
		$result = intval($result[0]*60)+($negative?-1:1)*intval($result[1]);

		for($f = 1; $f < count($horarios); $f++){
			if(empty($horarios[$f])){
				continue;
			}
			$negative = ($horarios[$f][0] == "-");
			$horarios[$f] = str_replace(["<b>", "</b>"], ["", ""], $horarios[$f]);
			$horarios[$f] = explode(":", $horarios[$f]);
			$horarios[$f] = intval($horarios[$f][0]*60)+($negative?-1:1)*intval($horarios[$f][1]);
			switch($operacao){
				case "+":
					$result += $horarios[$f];
				break;
				case "-":
					$result -= $horarios[$f];
				break;
				case "*":
					$result *= $horarios[$f];
				break;
				case "/":
					$result /= $horarios[$f];
				break;
			}
		}

		$result = 
			(($result < 0)?"-":"")
			.sprintf("%02d:%02d", abs(intval($result/60)), abs(intval($result%60)));

		return $result;
	}

	function somarHorarios(array $horarios): string{
		return operarHorarios($horarios, "+");
	}

	function verificarAlertaMDC(array $intervalos = []): string{
		$baseErrMsg = "Descanso de 00:30 a cada 05:30 digiridos não respeitado.";
		$mdc = "00:00";
		
		if(empty($intervalos)){
			return $mdc;
		}

		for($f = 0; $f < count($intervalos); $f++){
			$date = date_add(new DateTime("1970-01-01 00:00:00"), $intervalos[$f][1]);
			$intervalos[$f][1] = sprintf("%02d:%02d", abs(intval((dateTimeToSecs($date)/60)/60)), abs(intval((dateTimeToSecs($date)/60)%60)));
			if($intervalos[$f][0] == true && $intervalos[$f][1] > $mdc){
				$mdc = $intervalos[$f][1];
			}
		}

		if($mdc > "05:30"){
			$mdc = "<a style='white-space: nowrap;'>".
						"<i style='color:orange;' title='".$baseErrMsg."' class='fa fa-warning'></i>".
					"</a>".
					"&nbsp;".$mdc
			;
			return $mdc;
		}

		
		for($f = 0; $f < count($intervalos); $f++){
			$considerarPosJornada = true;
			$somaTempoAtivo = "00:00";
			$somaTempoDescanso = "00:00";
			$somaTempoTotal = "00:00";

			$tempoTrabalho = "05:30";
			$tempoDescanso = "00:15";

			if($intervalos[$f][0] == false){
				$intervalos = array_splice($intervalos, $f+1, count($intervalos), []);
				$f = -1;
				continue;
			}
			for($f2 = 0; $f2 < count($intervalos); $f2++){
				if($intervalos[$f2][0]){
					$somaTempoAtivo = operarHorarios([$somaTempoAtivo, $intervalos[$f2][1]], "+");
				}else{
					$somaTempoDescanso = operarHorarios([$somaTempoDescanso, $intervalos[$f2][1]], "+");
				}
				$somaTempoTotal = operarHorarios([$somaTempoAtivo, $somaTempoDescanso], "+");

				if($somaTempoTotal >= operarHorarios([$tempoTrabalho, $tempoDescanso], "+")){
					$considerarPosJornada = false;
					$excedente = operarHorarios([$somaTempoTotal, operarHorarios([$tempoTrabalho, $tempoDescanso], "+")], "-");
					if($intervalos[$f2][0]){
						// $somaTempoAtivo = operarHorarios([$somaTempoAtivo, $excedente], "-");
					}else{
						$somaTempoDescanso = operarHorarios([$somaTempoDescanso, $excedente], "-");
					}
					$f2 = count($intervalos);
				}
			}
			$somaTempoTotal = operarHorarios([$somaTempoAtivo, $somaTempoDescanso], "+");

			if($considerarPosJornada){
				$excedente = operarHorarios([$somaTempoTotal, operarHorarios([$tempoTrabalho, $tempoDescanso], "+")], "-"); //Dará um número negativo
				$somaTempoDescanso = operarHorarios([$somaTempoDescanso, $excedente], "-"); //Será somado, pois o excedente é negativo
			}
	
			if($somaTempoAtivo > $tempoTrabalho && $somaTempoDescanso < $tempoDescanso){
				$mdc = "<a style='white-space: nowrap;'>".
							"<i style='color:orange;' title='".$baseErrMsg."\n\nDirigido: ".$somaTempoAtivo."\nDescansado: ".$somaTempoDescanso."' class='fa fa-warning'></i>".
						"</a>".
						"&nbsp;".$mdc
				;
				$f = count($intervalos);
			}
		}

		/**
		 * Percorrer intervalos e separar em intervalos de 6 em 6 horas
		 * Se houver algum intervalo de 6 horas com menos de 30 minutos de descanso, enviar o alerta.
		 */
		
		return $mdc;
	}

	function verificaLimiteTempo(string $tempoEfetuado, string $limite){
		// Verifica se os parâmetros são strings e possuem o formato correto
		if(!preg_match("/^\d{2}:\d{2}$/", $tempoEfetuado) || !preg_match("/^\d{2}:\d{2}$/", $limite)){
			return "";
		}
		if(intval(explode(":", $tempoEfetuado)[0]) > 23){
			$vals = explode(":", $tempoEfetuado);
			$dateInterval = new DateInterval("P".floor($vals[0]/24)."DT".($vals[0]%24)."H".$vals[1]."M");
			$datetime1 = new DateTime("2000-01-01 00:00");
			$datetime1->add($dateInterval);
		}else{
			$datetime1 = new DateTime("2000-01-01 ".$tempoEfetuado);
		}
		$datetime2 = new DateTime("2000-01-01 ".$limite);

		if($datetime1 > $datetime2){
			return "<a style='white-space: nowrap;'><i style='color:orange;' title='Tempo excedido de ".$limite."' class='fa fa-warning'></i></a>&nbsp;".$tempoEfetuado;
		}else{
			return $tempoEfetuado;
		}
	}

	function verificaTolerancia($saldoDiario, $data, $idMotorista){
		$saldoDiario = str_replace(["<b>", "</b>"], ["", ""], $saldoDiario);
		date_default_timezone_set("America/Recife");
		$sqlTolerancia = query(
			"SELECT en.enti_nb_parametro, par.para_tx_tolerancia
				FROM entidade en
				INNER JOIN parametro par ON en.enti_nb_parametro = par.para_nb_id
				WHERE en.enti_nb_id = '".$idMotorista."'");
		
		$toleranciaArray = carrega_array($sqlTolerancia);

		$tolerancia = (empty($toleranciaArray["para_tx_tolerancia"]))? 0: $toleranciaArray["para_tx_tolerancia"];
		$tolerancia = intval($tolerancia);

		$saldoDiario = explode(":", $saldoDiario);
		$saldoEmMinutos = intval($saldoDiario[0])*60+($saldoDiario[0][0] == "-"? -1: 1)*intval($saldoDiario[1]);

		if($saldoEmMinutos < -($tolerancia)){
			$cor = "red";
		}elseif($saldoEmMinutos > $tolerancia){
			$cor = "green";
		}else{
			$cor = "blue";
		}

		$endossado = mysqli_fetch_all(
			query(
				"SELECT * FROM endosso 
					JOIN entidade ON endo_tx_matricula = enti_tx_matricula
					WHERE '".$data."' BETWEEN endo_tx_de AND endo_tx_ate
						AND enti_nb_id = ".$idMotorista."
						AND endo_tx_status = 'ativo';"
			), 
			MYSQLI_ASSOC
		);

		$title = "Ajuste de Ponto";
		$func = "ajusta_ponto(".$idMotorista.",\"".$data."\"";
		$content = "<i style='color:".$cor.";' class='fa fa-circle'>";
		if(count($endossado) > 0){
			$title .= " (endossado)";
			$func .= ", true";
			$content .= "(E)";
		}
		$func .= ")";
		if($_SESSION["user_tx_nivel"] == "Motorista"){
			$func = "";
		}
		$content .= "</i>";
		
		$retorno = "<a title='".$title."' onclick='".$func."'>".$content."</a>";
		return $retorno;
	}

	function criarFuncoesDeAjuste(){
		echo 
			"<script>
				function ajusta_ponto(motorista, data, endossado = false){
					if(endossado == true){
						alert('Dia já endossado.');
					}
					document.form_ajuste_ponto.id.value = motorista;
					document.form_ajuste_ponto.data.value = data;
					document.form_ajuste_ponto.HTTP_REFERER.value = '".(!empty($_POST["HTTP_REFERER"])? $_POST["HTTP_REFERER"]: $_SERVER["REQUEST_URI"])."';
					document.form_ajuste_ponto.submit();
				}
			</script>"
		;
	}

	function ordenar_horarios($inicio, $fim, $ehEspera = false, $ehEsperaRepouso = false){
		$pares_horarios = [
			"horariosOrdenados" => [],
			"pares" => [],
			"totalIntervalo" => "00:00",
			"icone" => "",
			"paresAdicionalNot" => "00:00",
			"totalIntervaloAdicionalNot" => "00:00"
		];

		if(empty($inicio) || empty($fim)){
			return $pares_horarios;
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
		for ($i = 0; $i < count($horarios); $i++){
			$horarios_com_origem[] = [
				"horario" => $horarios[$i],
				"origem" => $origem[$i]
			];
		}

		$dtInicioAdicionalNot = new DateTime(substr($horarios[0],0,10)." 05:00");
		$dtFimAdicionalNot = new DateTime(substr($horarios[0],0,10)." 22:00");
		$totalIntervalo = new DateTime(substr($horarios[0],0,10)." 00:00");
		$totalIntervaloAdicionalNot = new DateTime(substr($horarios[0],0,10)." 00:00");
		$inicio_atual = null;
		$inicio_anterio = null;
		$pares = [];
		$paresParaRepouso = [];

		$temErroJornada = False;

		$inicio_atual = "";
		foreach ($horarios_com_origem as $item){
			if($item["origem"] == "inicio"){
				if(!empty($inicio_atual)){
					// $pares[] = ["inicio" => date("H:i", strtotime($inicio_atual)), "fim" => ""];
					$pares[] = ["inicio" => $inicio_atual, "fim" => ""];
					$temErroJornada = True;
				}
				$inicio_atual = $item["horario"];
			}elseif($item["origem"] == "fim" && !empty($inicio_atual)){
				$hInicio = new DateTime($inicio_atual);
				$hFim = new DateTime($item["horario"]);
				
				$interval = $hInicio->diff($hFim);

				// se intervalo > 2 horas && ehEspera true
				if($ehEspera && (($interval->h*60) + $interval->i > 120)){
					// $paresParaRepouso[] = ["inicio" => date("H:i", strtotime($inicio_atual)), "fim" => date("H:i", strtotime($item["horario"]))];
					$paresParaRepouso[] = ["inicio" => $inicio_atual, "fim" => $item["horario"]];
				}else{
					$totalIntervalo->add($interval);
					$interval = $interval->format("%H:%I");
				}
				// $pares[] = ["inicio" => date("H:i", strtotime($inicio_atual)), "fim" => date("H:i", strtotime($item["horario"])), "intervalo" => $interval];
				$pares[] = ["inicio" => $inicio_atual, "fim" => $item["horario"], "intervalo" => $interval];

				// VERIFICA HORA SE HÁ HORA EXTRA ACIMA DAS 22:00
				if($hFim > $dtFimAdicionalNot){
					$fimExtra = date("H:i", strtotime($item["horario"]));
					if($hInicio > $dtFimAdicionalNot){
						$hInicioAdicionalNot = $hInicio; //CRIA UMA NOVA VARIAVEL PARA NO CASO DAS 05:00
						$incioExtra = date("H:i", strtotime($inicio_atual));
					}else{
						$hInicioAdicionalNot = new DateTime(substr($horarios[0],0,10)." 22:00");
						$incioExtra = "22:00";
					}

					$intervalAdicionalNot = $hInicioAdicionalNot->diff($hFim);
					$totalIntervaloAdicionalNot->add($intervalAdicionalNot);

					$intervalAdicionalNot = $intervalAdicionalNot->format("%d %H:%I");
					
					
					$paresAdicionalNot[] = ["inicio" => $incioExtra, "fim" => $fimExtra, "intervalo" => $intervalAdicionalNot];
				}

				// VERIFICA HORA SE HÁ HORA EXTRA ABAIXO DAS 05:00
				if($hInicio < $dtInicioAdicionalNot){
					$incioExtra = date("H:i", strtotime($inicio_atual));
					if($hFim > $dtInicioAdicionalNot){
						$hFim = new DateTime(substr($horarios[0],0,10)." 05:00");
						$fimExtra = "05:00";
					}else{
						$fimExtra = date("H:i", strtotime($item["horario"]));
					}

					$intervalAdicionalNot = $hInicio->diff($hFim);
					$totalIntervaloAdicionalNot->add($intervalAdicionalNot);
					
					$intervalAdicionalNot = $intervalAdicionalNot->format("%H:%I");;
					

					$paresAdicionalNot[] = ["inicio" => $incioExtra, "fim" => $fimExtra, "intervalo" => $intervalAdicionalNot];
				}

				$inicio_atual = null;
			}elseif($item["origem"] == "fim" && $inicio_atual == null){
				// Se encontrarmos um fim sem um início correspondente, armazenamos o horário sem par
				$sem_fim[] = $item["horario"];
			}
		}
		if($item["origem"] == "inicio"){
			// $pares[] = ["inicio" => date("H:i", strtotime($item["horario"])), "fim" => ""];
			$pares[] = ["inicio" => $item["horario"], "fim" => ""];
		}

		$tooltip = "";
		for($f = 0; $f < count($pares); $f++){
			$tooltip .= "Início:_".$pares[$f]["inicio"]."\n"
				."Fim:___".$pares[$f]["fim"]."\n\n";
		}
		$iconeAlerta = "";
		if(!((count($inicio)+count($fim) == 0) || empty($tooltip))){
			if(count($inicio) != count($fim) || count($horarios_com_origem)/2 != (count($pares)) || $temErroJornada){ 
				$iconeAlerta = "<a><i style='color:red;' title='".$tooltip."' class='fa fa-info-circle'></i></a>";
			}elseif($ehEsperaRepouso){
				$iconeAlerta = "<a><i style='color:#99ff99;' title='".$tooltip."' class='fa fa-info-circle'></i></a>";
			}else{
				$iconeAlerta = "<a><i style='color:green;' title='".$tooltip."' class='fa fa-info-circle'></i></a>";
			}
		}

		$pares_horarios = [
			"horariosOrdenados" => $horarios_com_origem,
			"pares" => $pares,
			"totalIntervalo" => $totalIntervalo,
			"icone" => $iconeAlerta
		];
		
		if(count($horarios_com_origem) > 2){
			$totalInterjornada = new DateTime(substr($horarios[0],0,10)." 00:00");
			for ($i = 1; $i < count($horarios_com_origem); $i++){ 
				$horarioVez = $horarios_com_origem[$i];
				$horarioAnterior = $horarios_com_origem[($i-1)];
				if($horarioVez["origem"] == "inicio" && $horarioAnterior["origem"] == "fim"){
					$dtInicio = new DateTime($horarioVez["horario"]);
					$dtFim = new DateTime($horarioAnterior["horario"]);
					
					$intervalInterjornada = $dtFim->diff($dtInicio);

					$totalInterjornada->add($intervalInterjornada);
					
				}
			}

			$pares_horarios["interjornada"] = $totalInterjornada->format("H:i");

		}

		if(count($paresParaRepouso) > 0){
			$pares_horarios["paresParaRepouso"] = $paresParaRepouso;
		}
		$pares_horarios["paresAdicionalNot"] = "00:00";
		$pares_horarios["totalIntervaloAdicionalNot"] = "00:00";
		if(isset($paresAdicionalNot) && count($paresAdicionalNot) > 0){
			$pares_horarios["paresAdicionalNot"] = $paresAdicionalNot;
			$pares_horarios["totalIntervaloAdicionalNot"] = $totalIntervaloAdicionalNot->format("H:i");
		}
		// Retorna o array de horários com suas respectivas origens
		return $pares_horarios;
	}

	function dateTimeToSecs(DateTime $dateTime, DateTime $baseDate = null): int{
		if(empty($baseDate)){
			$baseDate = DateTime::createFromFormat("Y-m-d H:i:s", "1970-01-01 00:00:00");
		}
    	$res = date_diff($dateTime, $baseDate);
        $res = 
        	($res->invert? 1:-1)*
			(
				$res->days*24*60*60+
				$res->h*60*60+
				$res->i*60+
				$res->s
			);
        return $res;
    }

	function calcJorPre($data, $jornadas, $abono = null): array{
		//$jornadas = ["sabado" => string, "semanal" => string, "feriado" => bool]

		if(date("w", strtotime($data)) == "0" || $jornadas["feriado"]){ 	//DOMINGOS OU FERIADOS
			$jornadaPrevista = "00:00";
		}elseif(date("w", strtotime($data)) == "6"){ 						//SABADOS
			$jornadaPrevista = $jornadas["sabado"];
		}else{															//DIAS DE SEMANA
			$jornadaPrevista = $jornadas["semanal"];
		}

		$jornadaPrevistaOriginal = $jornadaPrevista;
		$jornadaPrevista = (new DateTime($data." ".$jornadaPrevista))->format("H:i");
		if($abono !== null || $jornadas["feriado"] === null){
			$jornadaPrevista = (new DateTime($data." ".$abono))->diff(new DateTime($data." ".$jornadaPrevista))->format("%H:%I");
		}

		return [$jornadaPrevistaOriginal, $jornadaPrevista];
	}

	function diaDetalhePonto($matricula, $data): array{
		global $totalResumo, $contagemEspera;
		setlocale(LC_ALL, "pt_BR.utf8");

		$aRetorno = [
			"data" => data($data),
			"diaSemana" => strtoupper(substr(pegarDiaDaSemana($data), 0, 3)),
			"inicioJornada" => [],
			"inicioRefeicao" => [],
			"fimRefeicao" => [],
			"fimJornada" => [],
			"diffRefeicao" => "00:00",
			"diffEspera" => "00:00",
			"diffDescanso" => "00:00",
			"diffRepouso" => "00:00",
			"diffJornada" => "00:00",
			"jornadaPrevista" => "00:00",
			"diffJornadaEfetiva" => "00:00",
			"maximoDirecaoContinua" => "00:00",
			"intersticio" => "00:00",
			"he50" => "00:00",
			"he100" => "00:00",
			"adicionalNoturno" => "00:00",
			"esperaIndenizada" => "00:00",
			"diffSaldo" => "00:00"
		];
		$aMotorista = mysqli_fetch_assoc(query(
			"SELECT * FROM entidade"
			." LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id"
			." LEFT JOIN cidade  ON empresa.empr_nb_cidade = cidade.cida_nb_id"
			." WHERE enti_tx_status = 'ativo'"
				." AND enti_tx_matricula = '".$matricula."'"
			." LIMIT 1;"
		));

		if(empty($aMotorista["enti_nb_parametro"])){
			$aMotorista["enti_nb_parametro"] = $aMotorista["empr_nb_parametro"];
		}

		$extraFeriado = "";
		if(!empty($aMotorista["enti_nb_empresa"]) && !empty($aMotorista["empr_nb_cidade"]) && !empty($aMotorista["cida_nb_id"]) && !empty($aMotorista["cida_tx_uf"])){
			$extraFeriado = " AND ((feri_nb_cidade = '".$aMotorista["cida_nb_id"]."' OR feri_tx_uf = '".$aMotorista["cida_tx_uf"]."') OR ((feri_nb_cidade = '' OR feri_nb_cidade IS NULL) AND (feri_tx_uf = '' OR feri_tx_uf IS NULL)))";
		}

		$aParametro = carregar("parametro", $aMotorista["enti_nb_parametro"]);
		$alertaJorEfetiva = ((isset($aParametro["para_tx_acordo"]) && $aParametro["para_tx_acordo"] == "sim")? "12:00": "10:00");

		$queryFeriado = query(
			"SELECT feri_tx_nome FROM feriado 
				WHERE feri_tx_data LIKE '".$data."%' 
					AND feri_tx_status = 'ativo' ".$extraFeriado
		);
		$stringFeriado = "";
		while ($row = carrega_array($queryFeriado)){
			$stringFeriado .= $row[0]."\n";
		}

		$tiposRegistrados = [];
		$tipos = [
			"inicioJornada" => "Inicio de Jornada",
			"fimJornada" => "Fim de Jornada",
			"inicioRefeicao" => "Inicio de Refeição",
			"fimRefeicao" => "Fim de Refeição",
			"inicioEspera" => "Inicio de Espera",
			"fimEspera" => "Fim de Espera",
			"inicioDescanso" => "Inicio de Descanso",
			"fimDescanso" => "Fim de Descanso",
			"inicioRepouso" => "Inicio de Repouso",
			"fimRepouso" => "Fim de Repouso",
			"inicioRepousoEmb" => "Inicio de Repouso Embarcado",
			"fimRepousoEmb" => "Fim de Repouso Embarcado"
		];
		$registros = [];
		foreach(array_keys($tipos) as $tipo){
			$registros[$tipo] = [];
		}

		$tiposBd = mysqli_fetch_all(query(
			"SELECT DISTINCT macr_tx_codigoInterno, macr_tx_nome FROM macroponto"
		), MYSQLI_ASSOC);

		$dictTipos = [];
		for($f = 0; $f < count($tiposBd); $f++){
			if(is_int(array_search($tiposBd[$f]["macr_tx_nome"], array_values($tipos), true))){
				$dictTipos[$tiposBd[$f]["macr_tx_codigoInterno"]] = array_keys($tipos)[array_search($tiposBd[$f]["macr_tx_nome"], array_values($tipos), true)];
			}
		}

		$tipos = $dictTipos;

		$pontosDia = [];
		$sql = query(
			"SELECT * FROM ponto 
				WHERE pont_tx_status = 'ativo' 
					AND pont_tx_matricula = '".$matricula."' 
					AND pont_tx_data LIKE '".$data."%' 
				ORDER BY pont_tx_data ASC"
		);
		while($ponto = carrega_array($sql)){
			$pontosDia[] = $ponto;
		}

		if(count($pontosDia) > 0){
			if($pontosDia[count($pontosDia)-1]["pont_tx_tipo"] != "2"){ //Se o último registro do dia != fim de jornada => há uma jornada aberta que seguiu para os dias seguintes

				$dataProxFim = mysqli_fetch_assoc(query(
					"SELECT pont_tx_data FROM ponto
						JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
						WHERE pont_tx_status = 'ativo'
							AND pont_tx_matricula = '".$matricula."'
							AND pont_tx_data > '".$data." 23:59:59'
							AND pont_tx_tipo = '2'
						ORDER BY pont_tx_data ASC
						LIMIT 1;"
				));

				$diaSeguinte = (new DateTime($data))->add(DateInterval::createFromDateString("1 day"));
				$diaSeguinte = $diaSeguinte->format("Y-m-d");
				
				$pontosDiaSeguinte = [];
				if(!empty($dataProxFim["pont_tx_data"])){
					$pontosDiaSeguinte = mysqli_fetch_all(query(
							"SELECT macroponto.macr_tx_nome, ponto.* FROM ponto 
							JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
							WHERE pont_tx_status = 'ativo' 
								AND pont_tx_matricula = '".$matricula."'
								AND pont_tx_data > '".$data." 23:59:59'
								AND pont_tx_data <= '".$dataProxFim["pont_tx_data"]."'
							ORDER BY pont_tx_data ASC;"
					),MYSQLI_ASSOC);
				}

				for($f = 0; $f < count($pontosDiaSeguinte); $f++){

					//Se encontrar um fim de jornada, ignora os pontos que estiverem depois dele, pois corresponderão a uma próxima jornada.
					if($pontosDiaSeguinte[$f]["pont_tx_tipo"] == "2"){
						$pontosDiaSeguinte = array_slice($pontosDiaSeguinte, 0, $f+1);
						break;
					}

					//Não pega os pontos do dia seguinte caso tenha um início de jornada sem ter fechado o anterior. Isso impede dos mesmos pontos ficarem repetidos em dois dias distintos.
					if($pontosDiaSeguinte[$f]["pont_tx_tipo"] == "1"){
						$pontosDiaSeguinte = [];
						break;
					}
				}
				$pontosDia = array_merge($pontosDia, $pontosDiaSeguinte);
			}
			
			while(count($pontosDia) > 0 && $pontosDia[0]["pont_tx_tipo"] != "1"){ //Se o 1° registro != início de jornada => É uma jornada que veio do dia anterior
				array_shift($pontosDia);
			}
		}

		foreach($pontosDia as $ponto){
			if(!isset($registros[$tipos[$ponto["pont_tx_tipo"]]])){
				$registros[$tipos[$ponto["pont_tx_tipo"]]] = [];
			}
			$registros[$tipos[$ponto["pont_tx_tipo"]]][] = $ponto["pont_tx_data"];
		}


		$registros["jornadaCompleto"]  = ordenar_horarios($registros["inicioJornada"], $registros["fimJornada"]);		/* $jornadaOrdenado */
		$registros["jornadaCompleto"]["totalIntervalo"] = is_string($registros["jornadaCompleto"]["totalIntervalo"])? 
			new DateTime($data." ".$registros["jornadaCompleto"]["totalIntervalo"]): 
			$registros["jornadaCompleto"]["totalIntervalo"]
		;
		if(!empty($registros["inicioJornada"][0])){

			$diffJornada = date_diff(
				new DateTime(substr($registros["inicioJornada"][0], 0, strpos($registros["inicioJornada"][0], " "))." 00:00:00"), 
				$registros["jornadaCompleto"]["totalIntervalo"]
			);
			$diffJornada = formatToTime($diffJornada->days*24+$diffJornada->h, $diffJornada->i);

		}else{
			$diffJornada = "00:00";
		}

		foreach(["refeicao", "espera", "descanso", "repouso"] as $campoIgnorado){
			if(is_bool(strpos($aParametro["para_tx_ignorarCampos"], $campoIgnorado))){
				$registros[$campoIgnorado."Completo"] = ordenar_horarios($registros["inicio".ucfirst($campoIgnorado)], $registros["fim".ucfirst($campoIgnorado)]);		/* $refeicaoOrdenada */
			}else{
				$registros[$campoIgnorado."Completo"] = ordenar_horarios([], []);
			}
		}
		
		//REPOUSO POR ESPERA{
			if(isset($registros["esperaCompleto"]["paresParaRepouso"]) && !empty($registros["esperaCompleto"]["paresParaRepouso"])){
				$paresParaRepouso = $registros["esperaCompleto"]["paresParaRepouso"];
				// unset($registros["esperaCompleto"]["paresParaRepouso"]);
				for ($i = 0; $i < count($paresParaRepouso); $i++){
					$registros["repousoPorEspera"]["inicioRepouso"][] 	= $data." ".$paresParaRepouso[$i]["inicio"].":00";	/*$aDataHorainicioRepouso*/
					$registros["repousoPorEspera"]["fimRepouso"][] 		= $data." ".$paresParaRepouso[$i]["fim"].":00";		/*$aDataHorafimRepouso*/
				}
				$registros["repousoPorEspera"]["repousoCompleto"] = ordenar_horarios($registros["repousoPorEspera"]["inicioRepouso"], $registros["repousoPorEspera"]["fimRepouso"],false,true);
			}else{
				$registros["repousoPorEspera"]["repousoCompleto"] = ordenar_horarios([], []);
			}
		//}

		foreach(["refeicao", "descanso", "repouso", "espera"] as $campo){
			$totalIntervalo = (is_string($registros[$campo."Completo"]["totalIntervalo"]))?
				new DateTime($data." ".$registros[$campo."Completo"]["totalIntervalo"]):
				$registros[$campo."Completo"]["totalIntervalo"]
			;

			$totalIntervalo = date_diff(new DateTime($data." 00:00"), $totalIntervalo);
			if($totalIntervalo->s > 0){
				$totalIntervalo->i++;
				$totalIntervalo->s = 0;
				if($totalIntervalo->i >=60){
					$totalIntervalo->h++;
					$totalIntervalo->i -= 60;
				}
			}
			$totalIntervalo = formatToTime($totalIntervalo->days*24+$totalIntervalo->h, $totalIntervalo->i);
			
			$registros[$campo."Completo"]["totalIntervalo"] = $totalIntervalo;
		}
		$totalIntervalo = (is_string($registros["repousoPorEspera"]["repousoCompleto"]["totalIntervalo"]))?
			new DateTime($data." ".$registros["repousoPorEspera"]["repousoCompleto"]["totalIntervalo"]):
			$registros["repousoPorEspera"]["repousoCompleto"]["totalIntervalo"]
		;
		
		$totalIntervalo = date_diff(new DateTime($data." 00:00"), $totalIntervalo);
		$totalIntervalo = formatToTime($totalIntervalo->days*24+$totalIntervalo->h, $totalIntervalo->i);

		$registros["repousoPorEspera"]["repousoCompleto"]["totalIntervalo"] = $totalIntervalo;


		$registros["repousoCompleto"]["totalIntervalo"] = operarHorarios([$registros["repousoCompleto"]["totalIntervalo"], $registros["repousoPorEspera"]["repousoCompleto"]["totalIntervalo"]], "+");
		$registros["repousoCompleto"]["icone"] .= $registros["repousoPorEspera"]["repousoCompleto"]["icone"];
		
		$aRetorno["diffRefeicao"] = $registros["refeicaoCompleto"]["icone"].$registros["refeicaoCompleto"]["totalIntervalo"];
		$aRetorno["diffEspera"]   = $registros["esperaCompleto"]["icone"].$registros["esperaCompleto"]["totalIntervalo"];
		$aRetorno["diffDescanso"] = $registros["descansoCompleto"]["icone"].$registros["descansoCompleto"]["totalIntervalo"];
		$aRetorno["diffRepouso"]  = $registros["repousoCompleto"]["icone"].$registros["repousoCompleto"]["totalIntervalo"];

		$contagemEspera += count($registros["esperaCompleto"]["pares"]);

		$aAbono = carrega_array(
			query(
				"SELECT * FROM abono, motivo, user 
					WHERE abon_tx_status = 'ativo' 
						AND abon_nb_userCadastro = user_nb_id 
						AND abon_tx_matricula = '".$matricula."' 
						AND abon_tx_data = '".$data."' 
						AND abon_nb_motivo = moti_nb_id
					ORDER BY abon_nb_id DESC 
					LIMIT 1"
			)
		);
		
		$aRetorno["diffJornada"] = $registros["jornadaCompleto"]["icone"].$diffJornada;

		//JORNADA PREVISTA{
			$jornadas = [
				"sabado" => $aMotorista["enti_tx_jornadaSabado"],
				"semanal"=> $aMotorista["enti_tx_jornadaSemanal"],
				"feriado"=> ($stringFeriado != ""? True: null)
			];

			[$jornadaPrevistaOriginal, $jornadaPrevista] = calcJorPre($data, $jornadas, ($aAbono["abon_tx_abono"]?? null));

			$aRetorno["jornadaPrevista"] = $jornadaPrevista;
			if($jornadas["feriado"] == True){
				$iconeFeriado =  " <a><i style='color:green;' title='".$stringFeriado."' class='fa fa-info-circle'></i></a>";
				$aRetorno["diaSemana"] .= $iconeFeriado;
			}
		//}

		//JORNADA EFETIVA{

			if(is_string($registros["jornadaCompleto"]["totalIntervalo"])){
				$jornadaIntervalo = new DateTime($data." 00:00");
			}else{
				$jornadaIntervalo = $registros["jornadaCompleto"]["totalIntervalo"];
			}

			$totalNaoJornada = [
				$registros["refeicaoCompleto"]["totalIntervalo"]
			];

			//Ignorar intervalos que tenham sido marcados para ignorar no parâmetro{
				if(!empty($aMotorista["enti_nb_parametro"]) && !empty($aParametro["para_tx_ignorarCampos"])){
					$campos = ["espera", "descanso", "repouso"/*, "repousoEmbarcado"*/];
					foreach($campos as $campo){
						if(is_bool(strpos($aParametro["para_tx_ignorarCampos"], $campo))){
							$totalNaoJornada[] = $registros[$campo."Completo"]["totalIntervalo"];
						}
					}
				}else{
					$totalNaoJornada = [
						$registros["refeicaoCompleto"]["totalIntervalo"],
						$registros["esperaCompleto"]["totalIntervalo"],
						$registros["descansoCompleto"]["totalIntervalo"],
						$registros["repousoCompleto"]["totalIntervalo"]
					];
				}
			//}
			
			//SOMATORIO DE TODAS AS ESPERAS

			if(!empty($registros["inicioJornada"][0])){
				$value = new DateTime($data." 00:00");
				for($f = 0; $f < count($totalNaoJornada); $f++){
					$times = explode(":", $totalNaoJornada[$f]);
					$totalNaoJornada[$f] = new DateInterval("P".floor($times[0]/24)."DT".($times[0]%24)."H".$times[1]."M");
					$value->add($totalNaoJornada[$f]);
				}
				$totalNaoJornada = $value;
			}else{
				$totalNaoJornada = new DateTime($data." 00:00");
			}

			$jornadaEfetiva = $totalNaoJornada->diff($jornadaIntervalo);
			$diffJornadaEfetiva = formatToTime($jornadaEfetiva->days*24+$jornadaEfetiva->h, $jornadaEfetiva->i);
			if($jornadaEfetiva->days > 0){
				$jornadaEfetiva = (new DateTime($data." 00:00"))->add($jornadaEfetiva);
			}else{
				$jornadaEfetiva = DateTime::createFromFormat("Y-m-d H:i", $data." ".$jornadaEfetiva->format("%H:%I"));
			}

			$aRetorno["diffJornadaEfetiva"] = verificaLimiteTempo($diffJornadaEfetiva, $alertaJorEfetiva);
		//}

		//CÁLCULO DE INSTERTÍCIO{
			if(isset($registros["inicioJornada"]) && count($registros["inicioJornada"]) > 0){

				$ultimoFimJornada = carrega_array(query(
					"SELECT pont_tx_data FROM ponto
						WHERE pont_tx_status = 'ativo'
							AND pont_tx_tipo = 2
							AND pont_tx_matricula = '".$matricula."'
							AND pont_tx_data < '".$registros["inicioJornada"][0]."'
						ORDER BY pont_tx_data DESC
						LIMIT 1"
				));
				if(!empty($ultimoFimJornada)){
					$ultimoFimJornada = DateTime::createFromFormat("Y-m-d H:i:s", $ultimoFimJornada[0]);
					
					$intersticioDiario = (new DateTime($registros["inicioJornada"][0]))->diff($ultimoFimJornada);
					
					// Obter a diferença total em minutos
					$minInterDiario = (
						$intersticioDiario->days*60*24+
						$intersticioDiario->h*60+
						$intersticioDiario->i
					);

					// Calcular as horas e minutos

					$intersticio = sprintf("%02d:%02d", floor($minInterDiario / 60), $minInterDiario % 60); // Formatar a string no formato H:I

					$totalIntersticio = somarHorarios(
						[$intersticio, $totalNaoJornada->format("H:i")]
					);

					$icone = "";
					if($totalIntersticio < sprintf("%0".(strlen($totalIntersticio)-3)."d:%02d", "11","00")){ // < 11 horas
						$restante = operarHorarios([sprintf("%0".(strlen($totalIntersticio)-3)."d:%02d", "11","00"), $totalIntersticio], "-");
						$icone .= "<a><i style='color:red;' title='Interstício Total de 11:00 não respeitado, faltaram ".$restante."' class='fa fa-warning'></i></a>";
					}
					if($minInterDiario < (8*60)){ // < 8 horas
						$icone .= "<a><i style='color:red;' title='O mínimo de 08:00h ininterruptas no primeiro período, não respeitado.' class='fa fa-warning'></i></a>";
					}

					$aRetorno["intersticio"] = $icone.$totalIntersticio;
				}else{
					$aRetorno["intersticio"] = "00:00";
				}
			}
		//}

		//CALCULO SALDO{
			$saldoDiario = date_diff(
				DateTime::createFromFormat("Y-m-d H:i", $data." ".$jornadaPrevista),
				$jornadaEfetiva
			);
			
			$saldoDiario = ($saldoDiario->invert? "-": "").sprintf("%02d:%02d", abs($saldoDiario->days*24+$saldoDiario->h), abs($saldoDiario->i));
			$aRetorno["diffSaldo"] = $saldoDiario;
		//}

		//CALCULO ESPERA INDENIZADA{
			$intervaloEsp = somarHorarios([$registros["esperaCompleto"]["totalIntervalo"], $registros["repousoPorEspera"]["repousoCompleto"]["totalIntervalo"]]);
			$indenizarEspera = ($intervaloEsp >= "02:00");

			if($saldoDiario[0] == "-"){
				if($intervaloEsp > substr($saldoDiario, 1)){
					$transferir = substr($saldoDiario, 1);
				}else{
					$transferir = $intervaloEsp;
				}	
				$saldoDiario = operarHorarios([$saldoDiario, $transferir], "+");
				$aRetorno["diffSaldo"] = $saldoDiario;
				$intervaloEsp = operarHorarios([$intervaloEsp, $transferir], "-");
			}

			if($indenizarEspera){
				$esperaIndenizada = $intervaloEsp;
			}else{
				$esperaIndenizada = "00:00";
			}

			$aRetorno["esperaIndenizada"] = $esperaIndenizada;
		//}

		//INICIO ADICIONAL NOTURNO
			// $jornadaNoturno = $registros["jornadaCompleto"]["totalIntervaloAdicionalNot"];
			// $refeicaoNoturno = $registros["refeicaoCompleto"]["totalIntervaloAdicionalNot"];
			// $esperaNoturno = $registros["esperaCompleto"]["totalIntervaloAdicionalNot"];
			// $descansoNoturno = $registros["descansoCompleto"]["totalIntervaloAdicionalNot"];
			// $repousoNoturno = $registros["repousoCompleto"]["totalIntervaloAdicionalNot"];

			$intervalosNoturnos = somarHorarios([
				$registros["refeicaoCompleto"]["totalIntervaloAdicionalNot"], 
				$registros["esperaCompleto"]["totalIntervaloAdicionalNot"], 
				$registros["descansoCompleto"]["totalIntervaloAdicionalNot"], 
				$registros["repousoCompleto"]["totalIntervaloAdicionalNot"]
			]);

			$aRetorno["adicionalNoturno"] = operarHorarios([$registros["jornadaCompleto"]["totalIntervaloAdicionalNot"], $intervalosNoturnos], "-");
		//FIM ADICIONAL NOTURNO
		
		//TOLERÂNCIA{
			$tolerancia = carrega_array(query(
				"SELECT parametro.para_tx_tolerancia FROM entidade 
					JOIN parametro ON enti_nb_parametro = para_nb_id 
					WHERE enti_nb_parametro = ".$aMotorista["enti_nb_parametro"]."
					LIMIT 1;"
			))[0];
			$tolerancia = intval($tolerancia);
		

			$saldo = explode(":", $aRetorno["diffSaldo"]);
			$saldo = intval($saldo[0])*60 + ($saldo[0][0] == "-"? -1: 1)*intval($saldo[1]);
			
			if($saldo >= -($tolerancia) && $saldo <= $tolerancia){
				$aRetorno["diffSaldo"] = "00:00";
				$saldo = 0;
			}
		//}

		//HORAS EXTRAS{
			if($aRetorno["diffSaldo"][0] != "-"){ 	//Se o saldo for positivo

				if($jornadas["feriado"] == True || (new DateTime($data." 00:00:00"))->format("D") == "Sun"){
					$aRetorno["he100"] = $aRetorno["diffSaldo"];
					$aRetorno["he50"] = "00:00";
				}else{
					if(	(isset($aParametro["para_tx_HorasEXExcedente"]) && !empty($aParametro["para_tx_HorasEXExcedente"])) &&
						$aParametro["para_tx_HorasEXExcedente"] != "00:00" && 
						$aRetorno["diffSaldo"] >= $aParametro["para_tx_HorasEXExcedente"]
					){// saldo diário >= limite de horas extras 100%
						$aRetorno["he100"] = operarHorarios([$aRetorno["diffSaldo"], $aParametro["para_tx_HorasEXExcedente"]], "-");
					}else{
						$aRetorno["he100"] = "00:00";
					}
					$aRetorno["he50"] = operarHorarios([$aRetorno["diffSaldo"], $aRetorno["he100"]], "-");
				}
			}
		//}

		

		//MÁXIMA DIREÇÃO CONTÍNUA{
			// $aRetorno["maximoDirecaoContinua"] = verificarAlertaMDC(
			// 	maxDirecaoContinua($tiposRegistrados),
			// 	$registros["descansoCompleto"]["totalIntervalo"]
			// );

			if(is_bool(strpos($aParametro["para_tx_ignorarCampos"], "mdc"))){
				$intervalos = [];
				$interAtivo = null;
				foreach($pontosDia as $ponto){
					if(empty($interAtivo)){
						$interAtivo = new DateTime($ponto["pont_tx_data"]);
						continue;
					}
					
					$intervalos[] = [
						!($tipos[$ponto["pont_tx_tipo"]] == "inicioJornada" || (is_int(strpos($tipos[$ponto["pont_tx_tipo"]], "fim")) && $tipos[$ponto["pont_tx_tipo"]] != "fimJornada")), 
						date_diff($interAtivo, new DateTime($ponto["pont_tx_data"]))
					];
					$interAtivo = new DateTime($ponto["pont_tx_data"]);
				}
				$aRetorno["maximoDirecaoContinua"] = verificarAlertaMDC($intervalos);
			}
		//}

		//JORNADA MÍNIMA
			$dtJornada = new DateTime($data." ".$jornadaEfetiva->format("H:i"));
			$dtJornadaMinima = new DateTime($data." 06:00");

			$fezJorMinima = ($dtJornada >= $dtJornadaMinima);
		//FIM JORNADA MÍNIMA

		//ALERTAS{
			if((!isset($registros["inicioJornada"][0]) || $registros["inicioJornada"][0] == "") && $aRetorno["jornadaPrevista"] != "00:00"){
				$aRetorno["inicioJornada"][] 	= "<a><i style='color:red;' title='Batida início de jornada não registrada!' class='fa fa-warning'></i></a>";
			}
			if($fezJorMinima || count($registros["inicioJornada"]) > 0){
				if(!isset($registros["fimJornada"][0]) || $registros["fimJornada"][0] == ""){
					$aRetorno["fimJornada"][] 	  = "<a><i style='color:red;' title='Batida fim de jornada não registrada!' class='fa fa-warning'></i></a>";
				}

				//01:00 DE REFEICAO{
					$maiorRefeicao = "00:00";
					if(count($registros["refeicaoCompleto"]["pares"]) > 0){
						for ($i = 0; $i < count($registros["refeicaoCompleto"]["pares"]); $i++){
							if(!empty($registros["refeicaoCompleto"]["pares"][$i]["intervalo"]) && $maiorRefeicao < $registros["refeicaoCompleto"]["pares"][$i]["intervalo"]){
								$maiorRefeicao = $registros["refeicaoCompleto"]["pares"][$i]["intervalo"];
							}
						}
					}

					$avisoRefeicao = "";
					if($maiorRefeicao > "02:00"){
						$avisoRefeicao = "<a><i style='color:orange;' title='Refeição com tempo máximo de 02:00h não respeitado.' class='fa fa-info-circle'></i></a>";
					}elseif($dtJornada > $dtJornadaMinima && $maiorRefeicao < '01:00'){
						$avisoRefeicao = "<a><i style='color:red;' title='Refeição ininterrupta maior do que 01:00h não respeitado.' class='fa fa-warning'></i></a>";
					}
				//}

				if((!isset($registros["inicioRefeicao"][0]) || empty($aRetorno["inicioRefeicao"][0])) && $jornadaEfetiva->format("H:i") > "06:00"){
					$aRetorno["inicioRefeicao"][] = "<a><i style='color:red;' title='Batida início de refeição não registrada!' class='fa fa-warning'></i></a>";
				}else{
					$aRetorno["inicioRefeicao"][] = $avisoRefeicao;
				}

				if((!isset($registros["fimRefeicao"][0]) || empty($aRetorno["fimRefeicao"][0])) && ($jornadaEfetiva->format("H:i") > "06:00")){
					$aRetorno["fimRefeicao"][] 	  = "<a><i style='color:red;' title='Batida fim de refeição não registrada!' class='fa fa-warning'></i></a>";
				}else{
					$aRetorno["fimRefeicao"][] = $avisoRefeicao;
				}
				if(!empty($avisoRefeicao)){
					$aRetorno["diffRefeicao"] = $avisoRefeicao." ".$aRetorno["diffRefeicao"];
				}
			}
			if(is_array($aAbono) && count($aAbono) > 0){
				$warning = 
					"<a><i "
						."style='color:green;' "
						."title='"
							."Jornada Original: ".str_pad($jornadaPrevistaOriginal, 2, "0", STR_PAD_LEFT).":00\n"
							."Abono: ".$aAbono["abon_tx_abono"]."\n"
							."Motivo: ".$aAbono["moti_tx_nome"]."\n"
							."Justificativa: ".$aAbono["abon_tx_descricao"]."\n\n"
							."Registro efetuado por ".$aAbono["user_tx_login"]." em ".data($aAbono["abon_tx_dataCadastro"], 1)."' "
						."class='fa fa-info-circle'></i>"
					."</a>&nbsp;"
				;
				$aRetorno["jornadaPrevista"] = $warning.$aRetorno["jornadaPrevista"];
			}
		//}

		foreach(["inicioJornada", "fimJornada", "inicioRefeicao", "fimRefeicao"] as $campo){
			if(count($registros[$campo]) > 0 && !empty($registros[$campo][0])){
				$aRetorno[$campo] = $registros[$campo];
			}
		}

		if(count($registros["inicioEspera"]) > 0 && count($registros["fimEspera"]) > 0){
			$aRetorno["diffEspera"]   = $registros["esperaCompleto"]["icone"].$registros["esperaCompleto"]["totalIntervalo"];
		}
		if(count($registros["inicioDescanso"]) > 0 && count($registros["fimDescanso"]) > 0){
			$aRetorno["diffDescanso"] = $registros["descansoCompleto"]["icone"].$registros["descansoCompleto"]["totalIntervalo"];
		}
		if(count($registros["inicioRepouso"]) > 0 && count($registros["fimRepouso"]) > 0){
			$aRetorno["diffRepouso"]  = $registros["repousoCompleto"]["icone"].$registros["repousoCompleto"]["totalIntervalo"];
		}
		
		//LEGENDAS{
			if(!empty($registros["inicioJornada"])){
				$datas = 
					"('".implode("', '", $registros["inicioJornada"])."'"
					.(!empty($registros["inicioRefeicao"])? ", '".implode("', '", $registros["inicioRefeicao"])."'": "")
					.(!empty($registros["fimRefeicao"])? ", '".implode("', '", $registros["fimRefeicao"])."'": "")
					.(!empty($registros["fimJornada"])? ", '".implode("', '", $registros["fimJornada"])."')": ")")
				;

				$legendas = mysqli_fetch_all(
					query(
						"SELECT * FROM ponto
							JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
							JOIN user ON ponto.pont_nb_user = user.user_nb_id
							LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
							WHERE ponto.pont_nb_motivo IS NOT NULL 
								AND pont_tx_status = 'ativo'
								AND pont_tx_data IN ".$datas." 
								AND pont_tx_matricula = '".$matricula."'"
					),
					MYSQLI_ASSOC
				);
		
				$tipos = [
					"I" => 0, 
					"P" => 0, 
					"T" => 0, 
					"DSR" => 0
				];
				$contagens = [
					"inicioJornada" => $tipos,
					"fimJornada" => $tipos,
					"inicioRefeicao" => $tipos,
					"fimRefeicao" => $tipos,
				];
				
				foreach ($legendas as $value){
					$legenda = $value["moti_tx_legenda"];
				
					switch ($value["macr_tx_nome"]){
						case "Inicio de Jornada":
							$acao = "inicioJornada";
							break;
						case "Fim de Jornada":
							$acao = "fimJornada";
							break;
						case "Inicio de Refeição":
							$acao = "inicioRefeicao";
							break;
						case "Fim de Refeição":
							$acao = "fimRefeicao";
							break;
						default:
							$acao = "";
					}
					if($acao != "" && !empty($legenda) && array_key_exists($legenda, $contagens[$acao])){
						$contagens[$acao][$legenda]++;
					}
				}
				
				foreach ($contagens as $acao => $tipos){
					foreach ($tipos as $tipo => $quantidade){
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
						WHERE pont_tx_data LIKE '%".$data."%' 
							AND pont_tx_matricula = '".$matricula."'"
				),
				MYSQLI_ASSOC
			);
	
			$possuiAjustes = [
				"jornada"  => ["inicio" => False, "fim" => False], 	//$quantidade_inicioJ e $quantidade_fimJ
				"refeicao" => ["inicio" => False, "fim" => False],	//$quantidade_inicioR e $quantidade_fimR
			];
	
			foreach ($ajuste as $valor){
				if($data == substr($valor["pont_tx_data"], 0, 10)){
					if($valor["pont_tx_status"] == "inativo"){
						$possuiAjustes["jornada"]["inicio"]  = $possuiAjustes["jornada"]["inicio"] 	|| $valor["macr_tx_nome"] == "Inicio de Jornada";
						$possuiAjustes["jornada"]["fim"] 	 = $possuiAjustes["jornada"]["fim"] 	|| $valor["macr_tx_nome"] == "Fim de Jornada";
						$possuiAjustes["refeicao"]["inicio"] = $possuiAjustes["refeicao"]["inicio"] || $valor["macr_tx_nome"] == "Inicio de Refeição";
						$possuiAjustes["refeicao"]["fim"] 	 = $possuiAjustes["refeicao"]["fim"]	|| $valor["macr_tx_nome"] == "Fim de Refeição";
					}
				}
			}
			if($possuiAjustes["jornada"]["inicio"]){
				$aRetorno["inicioJornada"][] = "*";
			}
			if($possuiAjustes["jornada"]["fim"]){
				$aRetorno["fimJornada"][] = "*";
			}
			if($possuiAjustes["refeicao"]["inicio"]){
				$aRetorno["inicioRefeicao"][] = "*";
			}
			if($possuiAjustes["refeicao"]["fim"]){
				$aRetorno["fimRefeicao"][] = "*";
			}
		//}

		//SOMANDO TOTAIS{
			$campos = [
				"diffRefeicao", "diffEspera", "diffDescanso", "diffRepouso", "diffJornada", 
				"jornadaPrevista", "diffJornadaEfetiva", "maximoDirecaoContinua", "intersticio", 
				"he50", "he100", "adicionalNoturno", "esperaIndenizada", "diffSaldo"
			];
			foreach($campos as $campo){
				if(empty($totalResumo[$campo])){
					$totalResumo[$campo] = "00:00";
				}
				$totalResumo[$campo] = operarHorarios(
					[$totalResumo[$campo], strip_tags(str_replace(["&nbsp;", " "], "", $aRetorno[$campo]))], 
					"+"
				);
			}
			unset($campos);
		//}

		if($saldo > 0){
			$aRetorno["diffSaldo"] = "<b>".$aRetorno["diffSaldo"]."</b>";
		}

		foreach(["inicioJornada", "fimJornada", "inicioRefeicao", "fimRefeicao"] as $tipo){
			$pontos = [];
			if(count($aRetorno[$tipo]) > 0){
				foreach($aRetorno[$tipo] as $ponto){
					if(preg_match("/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/", $ponto)){
						$pontos[] = [
							"key" => count($pontos),
							"value" => $ponto
						];
					}
				}
			}

			
			if(!empty($pontos)){
				$dataDia = DateTime::createFromFormat("d/m/Y H:i:s", $aRetorno["data"]." 00:00:00");
				foreach($pontos as $ponto){
					$dataFim = DateTime::createFromFormat("Y-m-d H:i:s", $ponto["value"]);
					$qttDias = date_diff($dataDia, $dataFim);
					if(!is_bool($qttDias)){
						$qttDias = intval($qttDias->format("%d"));
						if($qttDias > 0){
							array_splice($aRetorno[$tipo], $ponto["key"]+1, 0, "D+".$qttDias);
						}
					}
				}
			}
		}


		//Converter array em string{
			$legendas = mysqli_fetch_all(query(
				"SELECT DISTINCT moti_tx_legenda FROM motivo 
					WHERE moti_tx_legenda IS NOT NULL;"
				), 
				MYSQLI_ASSOC
			);

			foreach(["inicioJornada", "fimJornada", "inicioRefeicao", "fimRefeicao"] as $tipo){
				if(count($aRetorno[$tipo]) > 0){
					for($f = 0; $f < count($aRetorno[$tipo]); $f++){
						//Formatar datas para hora e minutos
						if(strlen($aRetorno[$tipo][$f]) > 3 && strpos($aRetorno[$tipo][$f], ":", strlen($aRetorno[$tipo][$f])-3) !== false){
							$aRetorno[$tipo][$f] = date("H:i", strtotime($aRetorno[$tipo][$f]));
						}
					}
					$aRetorno[$tipo] = implode("<br>", $aRetorno[$tipo]);
					foreach($legendas as $legenda){
						$aRetorno[$tipo] = str_replace("<br><strong>".$legenda["moti_tx_legenda"]."</strong>", " <strong>".$legenda["moti_tx_legenda"]."</strong>", $aRetorno[$tipo]);
					}
					$aRetorno[$tipo] = str_replace("<br>D+", " D+", $aRetorno[$tipo]);
					$aRetorno[$tipo] = str_replace("<br>*", " *", $aRetorno[$tipo]);
				}else{
					$aRetorno[$tipo] = "";
				}
			}
		//}
		
		return $aRetorno;
	}

	function pegarDiaDaSemana($date){
		$week = [
			"Sunday" => "Domingo", 
			"Monday" => "Segunda-Feira",
			"Tuesday" => "Terca-Feira",
			"Wednesday" => "Quarta-Feira",
			"Thursday" => "Quinta-Feira",
			"Friday" => "Sexta-Feira",
			"Saturday" => "Sábado"
		];
		$response = iconv("UTF-8", "ASCII//TRANSLIT", $week[date("l", strtotime($date))]);
		return $response;
	}

	//@return [he50, he100]
	function calcularHorasAPagar(string $saldoBruto, string $he50, string $he100, string $max50APagar): array{
		$params = [$saldoBruto, $he50, $he100, $max50APagar];
		foreach($params as $param){
			if(!preg_match("/^-?\d{2,4}:\d{2}$/", $param)){
				throw new Exception("Format error: ".$param);
			}
		}

		if($saldoBruto[0] == "-"){
			return ["00:00", "00:00"];
		}
		if($saldoBruto <= $he100){
			return ["00:00", $saldoBruto];
		}

		$excedente = operarHorarios([$saldoBruto, $he100], "-");
		if($excedente <= $max50APagar){
			return [$excedente, $he100];
		}

		return [$max50APagar, $he100];
	}

	function criar_relatorio($anoMes){

        [$ano, $mes] = explode("-", $anoMes);

        // Cria a data de início do mês especificado
        $periodoInicio = date($anoMes."-01 00:00:00");
        $periodoFim = date("Y-m-t", mktime(0, 0, 0, $mes, 1, $ano));
        $dataInicio = new DateTime($periodoInicio);
        $dataFim = new DateTime($periodoFim);

        $empresas = mysqli_fetch_all(query(
            "SELECT empr_nb_id, empr_tx_nome FROM empresa"
            ." WHERE empr_tx_status = 'ativo'"
            ." ORDER BY empr_tx_nome ASC;"
        ),MYSQLI_ASSOC);
        
        foreach($empresas as $empresa){

            $path = "./arquivos"."/".$empresa["empr_nb_id"]."/".$anoMes;
            if(!file_exists($path."/empresa_".$empresa["empr_nb_id"].".json")){
                if(!is_dir($path)){
                    mkdir($path, 0755, true);
                }
                file_put_contents($path."/empresa_".$empresa["empr_nb_id"].".json", "");
            }

            $motoristas = mysqli_fetch_all(query(
                "SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_banco FROM entidade"
                ." WHERE enti_tx_status = 'ativo'"
                    ." AND enti_nb_empresa = ".$empresa["empr_nb_id"]
                    ." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante')"
                ." ORDER BY enti_tx_nome ASC;"
            ),MYSQLI_ASSOC);
            
            $rows = [];
            $statusEndossos = [
                "E" => 0,
                "EP" => 0,
                "N" => 0
            ];
            foreach($motoristas as $motorista){

                //Status Endosso{
                    $endossos = mysqli_fetch_all(query(
                        "SELECT * FROM endosso"
                        ." WHERE endo_tx_status = 'ativo'"
                            ." AND endo_nb_entidade = '".$motorista["enti_nb_id"]."'"
                            ." AND ("
                                ."(endo_tx_de  >= '".$periodoInicio."' AND endo_tx_de  <= '".$periodoFim."')"
                                ."OR (endo_tx_ate >= '".$periodoInicio."' AND endo_tx_ate <= '".$periodoFim."')"
                                ."OR (endo_tx_de  <= '".$periodoInicio."' AND endo_tx_ate >= '".$periodoFim."')"
                            .");"
                    ), MYSQLI_ASSOC);
                    
                    $statusEndosso = "N";
                    if(count($endossos) >= 1){
                        $statusEndosso = "E";
                        if(strtotime($periodoInicio) != strtotime($endossos[0]["endo_tx_de"]) || strtotime($periodoFim) > strtotime($endossos[count($endossos)-1]["endo_tx_ate"])){
                            $statusEndosso .= "P";
                        }
                    }
                    $statusEndossos[$statusEndosso]++;
                //}
                
                $arquivosEndo = [];
                foreach($endossos as $arquivo){
                    $arquivosEndo [] = lerEndossoCSV($arquivo["endo_tx_filename"]);
                }
                $endossoCompleto = [];

                $row = [
                    "jornadaPrevista" => "00:00",
                    "jornadaEfetiva" => "00:00",
                    "HESemanal" => "00:00",
                    "HESabado" => "00:00",
                    "adicionalNoturno" => "00:00",
                    "esperaIndenizada" => "00:00",
                    "saldoPeriodo" => "00:00",
                    "saldoFinal" => "00:00"
                ];

                if(count($arquivosEndo) > 0){
                    $endossoCompleto = $arquivosEndo[0];
                    $saldoAnterior = $endossoCompleto["totalResumo"]["saldoAnterior"];
                    if(empty($endossoCompleto["endo_tx_max50APagar"]) && !empty($endossoCompleto["endo_tx_horasApagar"])){
                        $endossoCompleto["endo_tx_max50APagar"] = $endossoCompleto["endo_tx_horasApagar"];
                    }

                    for ($f = 1; $f < count($arquivosEndo); $f++){ 
                        if(empty($arquivosEndo[$f]["endo_tx_max50APagar"]) && !empty($arquivosEndo[$f]["endo_tx_horasApagar"])){
                            $arquivosEndo[$f]["endo_tx_max50APagar"] = $arquivosEndo[$f]["endo_tx_horasApagar"];
                        }

                        if($endossoCompleto["endo_tx_max50APagar"] != "00:00"){
                            $endossoCompleto["endo_tx_max50APagar"] = operarHorarios([$endossoCompleto["endo_tx_max50APagar"], $arquivosEndo[$f]["endo_tx_max50APagar"]], "+");	
                            if(is_int(strpos($endossoCompleto["endo_tx_max50APagar"], "-"))){
                                $endossoCompleto["endo_tx_max50APagar"] = "00:00";
                            }
                        }
                        foreach($arquivosEndo[$f]["totalResumo"] as $key => $value){
                            if(in_array($key, ["saldoAnterior"])){
                                continue;
                            }
                            $endossoCompleto["totalResumo"][$key] = operarHorarios([$endossoCompleto["totalResumo"][$key], $value], "+");
                        }    
                    }

                    $row["saldoPeriodo"]                            = $endossoCompleto["totalResumo"]["diffSaldo"];
                    $endossoCompleto["totalResumo"]["saldoBruto"]   = operarHorarios([$saldoAnterior, $row["saldoPeriodo"]], "+");
                    [$row["HESemanal"], $row["HESabado"]]           = calcularHorasAPagar($endossoCompleto["totalResumo"]["saldoBruto"], $endossoCompleto["totalResumo"]["he50"], $endossoCompleto["totalResumo"]["he100"], $endossoCompleto["endo_tx_max50APagar"]);
                    $row["jornadaPrevista"]                         = $endossoCompleto["totalResumo"]["jornadaPrevista"];
                    $row["jornadaEfetiva"]                          = $endossoCompleto["totalResumo"]["diffJornadaEfetiva"];
                    $row["adicionalNoturno"]                        = $endossoCompleto["totalResumo"]["adicionalNoturno"];
                    $row["esperaIndenizada"]                        = $endossoCompleto["totalResumo"]["esperaIndenizada"];
                    $row["saldoFinal"]                              = operarHorarios([$endossoCompleto["totalResumo"]["saldoBruto"], $endossoCompleto["totalResumo"]["he50_aPagar"], $endossoCompleto["totalResumo"]["he100_aPagar"]], "-");
                }

                $rows[] = [ 
                    "idMotorista" => $motorista["enti_nb_id"],
                    "matricula" => $motorista["enti_tx_matricula"],
                    "nome" => $motorista["enti_tx_nome"],
                    "statusEndosso" => $statusEndosso,
                    "jornadaPrevista" => $row["jornadaPrevista"],
                    "jornadaEfetiva" => $row["jornadaEfetiva"],
                    "HESemanal" => $row["HESemanal"],
                    "HESabado" => $row["HESabado"],
                    "adicionalNoturno" => $row["adicionalNoturno"],
                    "esperaIndenizada" => $row["esperaIndenizada"],
                    "saldoAnterior" => $saldoAnterior,
                    "saldoPeriodo" => $row["saldoPeriodo"],
                    "saldoFinal" => $row["saldoFinal"]
                ];
            }

            [
                $totalJorPrevResut,
                $totalJorPrev,
                $totalJorEfe,
                $totalHE50,
                $totalHE100,
                $totalAdicNot,
                $totalEspInd,
                $saldoAnterior,
                $totalSaldoPeriodo,
                $saldoFinal
            ] = array_pad([], 10, "00:00");

            foreach ($rows as $row){
                $totalJorPrev      = somarHorarios([$totalJorPrev, $row["jornadaPrevista"]]);
                $totalJorEfe       = somarHorarios([$totalJorEfe, $row["jornadaEfetiva"]]);
                $totalHE50         = somarHorarios([$totalHE50, $row["HESemanal"]]);
                $totalHE100        = somarHorarios([$totalHE100, $row["HESabado"]]);
                $totalAdicNot      = somarHorarios([$totalAdicNot, $row["adicionalNoturno"]]);
                $totalEspInd       = somarHorarios([$totalEspInd, $row["esperaIndenizada"]]);
                $saldoAnterior     = somarHorarios([$saldoAnterior, $row["saldoAnterior"]]);
                $totalSaldoPeriodo = somarHorarios([$totalSaldoPeriodo, $row["saldoPeriodo"]]);
                $saldoFinal        = somarHorarios([$saldoFinal, $row["saldoFinal"]]);
            }

            $totaisJson = [
                "empresaId"        => $empresa["empr_nb_id"],
                "empresaNome"      => $empresa["empr_tx_nome"],
                "jornadaPrevista"  => $totalJorPrev,
                "JornadaEfetiva"   => $totalJorEfe,
                "he50"             => $totalHE50,
                "he100"            => $totalHE100,
                "adicionalNoturno" => $totalAdicNot,
                "esperaIndenizada" => $totalEspInd,
                "saldoAnterior"    => $saldoAnterior,
                "saldoPeriodo"     => $totalSaldoPeriodo,
                "saldoFinal"       => $saldoFinal,
                "naoEndossados"    => $statusEndossos["N"],
                "endossados"       => $statusEndossos["E"],
                "endossoPacial"    => $statusEndossos["EP"],
                "totalMotorista"   => count($motoristas)
            ];

            $totais[] = $totaisJson;
            
            if(!is_dir($path)){
                mkdir($path,0755,true);
            }
            $fileName = "totalMotoristas.json";
            $jsonArquiTotais = json_encode($totaisJson, JSON_UNESCAPED_UNICODE);
            file_put_contents($path."/".$fileName, $jsonArquiTotais);
                
        }

        if(!is_dir("./arquivos/paineis/empresas/".$anoMes."")){
            mkdir("./arquivos/paineis/empresas/".$anoMes."",0755,true);
        }
        $path = "./arquivos/paineis/empresas/".$anoMes;
        $fileName = "totalEmpresas.json";
        $jsonArquiTotais = json_encode($totais, JSON_UNESCAPED_UNICODE);
        file_put_contents($path."/".$fileName, $jsonArquiTotais);
        
        [
            /*$totalJorPrevResut, */$totalJorPrev, $totalJorEfe, $totalHE50, $totalHE100, $totalAdicNot,
            $totalEspInd, $totalSaldoPeriodo, $toralSaldoAnter, $saldoFinal
        ] = array_pad([], 9, "00:00");

        $totalMotorista 	= 0;
        $totalNaoEndossados = 0;
        $totalEndossados 	= 0;
        $totalEndossoPacial = 0;
        
        foreach ($totais as $totalEmpresa){

            $totalMotorista 	+= $totalEmpresa["totalMotorista"];
            $totalNaoEndossados += $totalEmpresa["naoEndossados"];
            $totalEndossados 	+= $totalEmpresa["endossados"];
            $totalEndossoPacial += $totalEmpresa["endossoPacial"];
            
            $totalJorPrev           = somarHorarios([$totalEmpresa["jornadaPrevista"],$totalJorPrev]);
            $totalJorEfe            = somarHorarios([$totalJorEfe, $totalEmpresa["JornadaEfetiva"]]);
            $totalHE50              = somarHorarios([$totalHE50, $totalEmpresa["he50"]]);
            $totalHE100             = somarHorarios([$totalHE100, $totalEmpresa["he100"]]);
            $totalAdicNot           = somarHorarios([$totalAdicNot, $totalEmpresa["adicionalNoturno"]]);
            $totalEspInd            = somarHorarios([$totalEspInd, $totalEmpresa["esperaIndenizada"]]);
            $toralSaldoAnter        = somarHorarios([$toralSaldoAnter, $totalEmpresa["saldoAnterior"]]);
            $totalSaldoPeriodo      = somarHorarios([$totalSaldoPeriodo, $totalEmpresa["saldoPeriodo"]]);
            $saldoFinal             = somarHorarios([$saldoFinal, $totalEmpresa["saldoFinal"]]);
        }
        
        $jsonTotaisEmpr = [
            "EmprTotalJorPrev"      => $totalJorPrev,
            "EmprTotalJorEfe"       => $totalJorEfe,
            "EmprTotalHE50"         => $totalHE50,
            "EmprTotalHE100"        => $totalHE100,
            "EmprTotalAdicNot"      => $totalAdicNot,
            "EmprTotalEspInd"       => $totalEspInd,
            "EmprTotalSaldoAnter"   => $toralSaldoAnter,
            "EmprTotalSaldoPeriodo" => $totalSaldoPeriodo,
            "EmprTotalSaldoFinal"   => $saldoFinal,
            "EmprTotalMotorista"    => $totalMotorista,
            "EmprTotalNaoEnd"       => $totalNaoEndossados,
            "EmprTotalEnd"          => $totalEndossados,
            "EmprTotalEndPac"       => $totalEndossoPacial,
        ];
        

        $path = "./arquivos/empresas/".$anoMes;
        if(!is_dir($path)){
            mkdir($path,0755,true);
        }
        $fileName = "empresas.json";
        file_put_contents($path."/".$fileName, json_encode($jsonTotaisEmpr));
        return;
    }
