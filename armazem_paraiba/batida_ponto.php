<?php
    /*Modo debug{
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//}*/
	include "funcoes_ponto.php";

	function cadastra_ponto() {
		$hoje = date('Y-m-d');
		$dataHora = $hoje." ".date('H:i').':00';
		$aMotorista = carregar('entidade', $_POST['id']);

		if(empty($_POST['motivo'])){
			$_POST['motivo'] = mysqli_fetch_all(
				query("SELECT moti_nb_id FROM motivo WHERE moti_tx_nome = 'Registro de ponto mobile' LIMIT 1"),
				MYSQLI_ASSOC
			)[0]['moti_nb_id'];
		}
		$_POST['motivo'] = intval($_POST['motivo']);

		$ultimoPonto = pegarPontosDia($aMotorista['enti_tx_matricula'])[0];
		if(!empty($ultimoPonto)){
			$ultimoPonto = $ultimoPonto[count($ultimoPonto)-1];
			$ultimoPonto['sameTypeError'] = ($ultimoPonto['pont_tx_tipo'] == $_POST['idMacro']);
		}

		$aTipo = mysqli_fetch_all(
			query(
				"SELECT macr_tx_codigoInterno, macr_tx_codigoExterno FROM macroponto 
					WHERE macr_nb_id = '".$_POST['idMacro']."'"
			),
			MYSQLI_ASSOC
		)[0];

		if (!empty($ultimoPonto) && intval($ultimoPonto['sameTypeError'])){
			set_status("ERRO: Último ponto é do mesmo tipo.");
		}else{
			$novoPonto = [
				'pont_nb_user' 			=> $_SESSION['user_nb_id'],
				'pont_tx_matricula' 	=> $aMotorista['enti_tx_matricula'],
				'pont_tx_data' 			=> strval($dataHora),
				'pont_tx_tipo' 			=> $aTipo['macr_tx_codigoInterno'],
				'pont_tx_tipoOriginal' 	=> $aTipo['macr_tx_codigoExterno'],
				'pont_tx_status' 		=> 'ativo',
				'pont_tx_dataCadastro' 	=> $hoje.' '.date("H:i:s"),
				'pont_nb_motivo' 		=> $_POST['motivo'],
			];
			inserir('ponto', array_keys($novoPonto), array_values($novoPonto));
		}

		index();
		exit;
	}

	function pegarPontosDia(string $matricula): array{
		$hoje = date('Y-m-d');
		$condicoesPontoBasicas = 
			"ponto.pont_tx_status != 'inativo'
			AND ponto.pont_tx_matricula = '".$matricula."'
			AND ponto.pont_tx_data <= '".$hoje.' '.date('H:i:s')."'"
		;

		$abriuJornadaHoje = mysqli_fetch_assoc(
			query(
				"SELECT * FROM ponto
					WHERE ".$condicoesPontoBasicas."
						AND ponto.pont_tx_tipo = 1
						AND ponto.pont_tx_data LIKE '%".$hoje."%'
					ORDER BY ponto.pont_tx_data ASC
					LIMIT 1;"
			)
		);

		if(empty($abriuJornadaHoje)){
			//Confere se há uma jornada aberta que veio do dia anterior.
			$temJornadaAberta = mysqli_fetch_assoc(
				query(
					"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 1) as temJornadaAberta FROM ponto
						WHERE ".$condicoesPontoBasicas."
							AND ponto.pont_tx_tipo IN (1,2)
							AND pont_tx_data <= '".$hoje." 00:00:00'
						ORDER BY pont_tx_data DESC
						LIMIT 1;"
				)
			);

			if(!empty($temJornadaAberta) && intval($temJornadaAberta['temJornadaAberta'])){//Se tem uma jornada que veio do dia anterior
				$jornadaFechadaHoje = mysqli_fetch_assoc(
					query(
						"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 2) as jornadaFechadaHoje FROM ponto
							WHERE ".$condicoesPontoBasicas."
								AND ponto.pont_tx_tipo IN (1,2)
								AND pont_tx_data LIKE '%".$hoje."%'
							ORDER BY pont_tx_data ASC
							LIMIT 1;"
					)
				);
				if(!empty($jornadaFechadaHoje) && intval($jornadaFechadaHoje['jornadaFechadaHoje'])){
					$sqlDataInicio = $jornadaFechadaHoje['pont_tx_data'];
				}else{
					$sqlDataInicio = $temJornadaAberta['pont_tx_data'];
				}
			}else{
				$sqlDataInicio = $hoje." ".date('H:i:s');
			}
		}else{
			$sqlDataInicio = $abriuJornadaHoje['pont_tx_data'];
		}
		
		$sql = 
			"SELECT * FROM ponto
				JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno
				WHERE ".$condicoesPontoBasicas."
					AND ponto.pont_tx_data >= '".$sqlDataInicio."'
				ORDER BY pont_tx_data ASC"
		;

		$pontosCompleto = mysqli_fetch_all(query($sql),MYSQLI_ASSOC);

		return [$pontosCompleto, $sql];
	}

	function criaBotaoRegistro(string $classe, int $tipoRegistro, string $nome, string $iconClass){
		return 
			"<button type='button'class='".$classe."' onclick='carregar_submit(\"".strval($tipoRegistro)."\",\" Tem certeza que deseja ".$nome."?\");'>
				<div class='button-icon'>
				    <i style='min-height: 30px;' class='".$iconClass."'></i>
				</div>
				<div class='button-title'>
				    ".$nome."
				</div>
			</button>";
	}

	function index() {

		$hoje = date('Y-m-d');
		cabecalho('Registrar Ponto');
		
		$motivo = mysqli_fetch_all(
			query(
				'SELECT moti_nb_id FROM `motivo` 
					WHERE moti_tx_status != "inativo" 
						AND moti_tx_nome = "Registro de ponto mobile" 
						AND moti_tx_tipo = "Ajuste"'
			), 
			MYSQLI_ASSOC
		);
		
		if(empty($_SESSION['user_nb_entidade'])){
			echo "Motorista não localizado. Tente fazer o login novamente.";
			exit;
		}

		$aMotorista = carregar('entidade', $_SESSION['user_nb_entidade']);

		[$pontosCompleto, $sql] = pegarPontosDia($aMotorista['enti_tx_matricula']);

		if(!empty($pontosCompleto)){
			$pontos = [
				'primeiro' => $pontosCompleto[0],
				'ultimo' => $pontosCompleto[count($pontosCompleto)-1]
			];
		}else{
			$pontos = [
				'primeiro' => null,
				'ultimo' => null
			];
		}

		$inicios = [
			1  => 'inicioJornada', 
			3  => 'inicioRefeicao', 
			5  => 'inicioEspera', 
			7  => 'inicioDescanso', 
			9  => 'inicioRepouso', 
			11 => 'inicioRepousoEmbarcado'
		];
		$fins = [
			2  => 'fimJornada', 
			4  => 'fimRefeicao', 
			6  => 'fimEspera', 
			8  => 'fimDescanso', 
			10 => 'fimRepouso', 
			12 => 'fimRepousoEmbarcado'
		];

		$pares = [
			'jornada' 			=> [],
			'refeicao' 			=> [],
			'espera' 			=> [],
			'descanso'			=> [],
			'repouso'			=> [],
			'repousoEmbarcado' 	=> []
		];

		for($f = 0; $f < count($pontosCompleto); $f++){
			if(empty($pontosCompleto[$f])){
				continue;
			}

			$value = DateTime::createFromFormat('Y-m-d H:i:s', $pontosCompleto[$f]['pont_tx_data']);

			switch(intval($pontosCompleto[$f]['pont_tx_tipo'])){
				case 1:
					$pares['jornada'][] = [
						'inicio' => (($value->format('Y-m-d') < $hoje)? operarHorarios([$value->format("H:i"), "24:00"], '-'): $value->format("H:i"))
					];
				break;
				case 2:
					if(count($pares['jornada']) == 0){
						$pares['jornada'][0] = ['inicio' => null, 'fim' => $value->format("H:i")];
					}else{
						$pares['jornada'][count($pares['jornada'])-1]['fim'] = $value->format("H:i");
					}
				break;
				case 3:
					$pares['refeicao'][] = [
						'inicio' => (($value->format('Y-m-d') < $hoje)? operarHorarios([$value->format("H:i"), "24:00"], '-'): $value->format("H:i"))
					];
				break;
				case 4:
					$pares['refeicao'][count($pares['refeicao'])-1]['fim'] = $value->format("H:i");
				break;
				case 5:
					$pares['espera'][] = [
						'inicio' => (($value->format('Y-m-d') < $hoje)? operarHorarios([$value->format("H:i"), "24:00"], '-'): $value->format("H:i"))
					];
				break;
				case 6:
					$pares['espera'][count($pares['espera'])-1]['fim'] = $value->format("H:i");
				break;
				case 7:
					$pares['descanso'][] = [
						'inicio' => (($value->format('Y-m-d') < $hoje)? operarHorarios([$value->format("H:i"), "24:00"], '-'): $value->format("H:i"))
					];
				break;
				case 8:
					$pares['descanso'][count($pares['descanso'])-1]['fim'] = $value->format("H:i");
				break;
				case 9:
					$pares['repouso'][] = [
						'inicio' => (($value->format('Y-m-d') < $hoje)? operarHorarios([$value->format("H:i"), "24:00"], '-'): $value->format("H:i"))
					];
				break;
				case 10:
					$pares['repouso'][count($pares['repouso'])-1]['fim'] = $value->format("H:i");
				break;
				case 11:
					$pares['repousoEmbarcado'][] = [
						'inicio' => (($value->format('Y-m-d') < $hoje)? operarHorarios([$value->format("H:i"), "24:00"], '-'): $value->format("H:i"))
					];
				break;
				case 12:
					$pares['repousoEmbarcado'][count($pares['repousoEmbarcado'])-1]['fim'] = $value->format("H:i");
				break;
			}
		}

		$jornadaCompleta = '00:00';
		for($f = 0; $f < count($pares['jornada']); $f++){
			if(!empty($pares['jornada'][$f]['fim'])){
				$jornada = operarHorarios([$pares['jornada'][$f]['fim'], $pares['jornada'][$f]['inicio']], '-');
				$jornadaCompleta = operarHorarios([$jornadaCompleta, $jornada], '+');
			}
		}

		$ultimoInicioJornada = !empty($pares['jornada'])? $pares['jornada'][count($pares['jornada'])-1]['inicio']: null;
		
		foreach($pares as $key => $value){
			if(is_array($value) && count($value) > 0){
				for($f = 0; $f < count($value); $f++){
					if(isset($value[$f]['inicio']) && isset($value[$f]['fim'])){
						$value[$f] = operarHorarios([$value[$f]['fim'], $value[$f]['inicio']], '-');
					}else{
						$value[$f] = '00:00';
					}
				}
				$value = operarHorarios($value, '+');
			}else{
				$value = '00:00';
			}
			$pares[$key] = $value;
		}

		
		$jornadaEfetiva = operarHorarios([$pares['refeicao'], $pares['espera'], $pares['descanso'], $pares['repouso'], $pares['repousoEmbarcado']], '+');

		$jornadaEfetiva = operarHorarios([$pares['jornada'], $jornadaEfetiva], '-');


		$botoes = [
			'inicioJornada' 			=> criaBotaoRegistro('btn green', 1,  'Iniciar Jornada', 'fa fa-car fa-6'),
			'inicioRefeicao' 			=> criaBotaoRegistro('btn green', 3,  'Iniciar Refeição', 'fa fa-cutlery fa-6'),
			'inicioEspera' 				=> criaBotaoRegistro('btn green', 5,  'Iniciar Espera', 'fa fa-clock-o fa-6'),
			'inicioDescanso' 			=> criaBotaoRegistro('btn green', 7,  'Iniciar Descanso', 'fa fa-hourglass-start fa-6'),
			'inicioRepouso' 			=> criaBotaoRegistro('btn green', 9,  'Iniciar Repouso', 'fa fa-bed fa-6'),
			// 'inicioRepousoEmbarcado'	=> criaBotaoRegistro('btn green', 11, 'Iniciar Repouso Embarcado', 'fa fa-bed fa-6'),

			'fimJornada' 				=> criaBotaoRegistro('btn red', 2,  'Encerrar Jornada', 'fa fa-car fa-6'),
			'fimRefeicao' 				=> criaBotaoRegistro('btn red', 4,  'Encerrar Refeição', 'fa fa-cutlery fa-6'),
			'fimEspera' 				=> criaBotaoRegistro('btn red', 6,  'Encerrar Espera', 'fa fa-clock-o fa-6'),
			'fimDescanso' 				=> criaBotaoRegistro('btn red', 8,  'Encerrar Descanso', 'fa fa-hourglass-end fa-6'),
			'fimRepouso' 				=> criaBotaoRegistro('btn red', 10, 'Encerrar Repouso', 'fa fa-bed fa-6'),
			// 'fimRepousoEmbarcado' 		=> criaBotaoRegistro('btn red', 12, 'Encerrar Repouso Embarcado', 'fa fa-bed fa-6'),
		];

		$botoesVisiveis = [];

		if (empty($pontos['ultimo']['pont_tx_tipo']) || intval($pontos['ultimo']['pont_tx_tipo']) == 2) {
			$botoesVisiveis = [$botoes['inicioJornada']];
		} elseif ($pontos['ultimo']['pont_tx_tipo'] == 1 || in_array($pontos['ultimo']['pont_tx_tipo'], array_keys($fins))){
			$botoesVisiveis = [
				$botoes['inicioRepouso'],
				$botoes['inicioDescanso'], 
				$botoes['inicioEspera'], 
				$botoes['inicioRefeicao'], 
				$botoes['fimJornada']
				// $botoes['inicioRepousoEmbarcado']
			];
		}elseif(in_array($pontos['ultimo']['pont_tx_tipo'], array_keys($inicios))){
			$botoesVisiveis = [
				$botoes[$fins[$pontos['ultimo']['pont_tx_tipo']+1]]
			];
		}

		$c = [
			"<div id='clockParent' class='col-sm-5 margin-bottom-5' >
			<label>Hora</label><br>
			<p class='text-left' id='clock'>Carregando...</p>
		</div>",
			texto('Matrícula', $aMotorista['enti_tx_matricula'], 2), 
			campo_hidden('CPF', $aMotorista['enti_tx_cpf'], 2),
			campo_hidden('Motorista', $aMotorista['enti_tx_nome'], 2),
			campo('Data', 'data', data($hoje), 2, '', 'readonly=readonly'),
			campo_hidden('motivo', 'Registro de ponto mobile')
		];

		$aEndosso = carrega_array(
			query(
				"SELECT user_tx_login, endo_tx_dataCadastro
					FROM endosso, user
					WHERE endo_tx_status = 'ativo'
						AND '".$hoje."' BETWEEN endo_tx_de AND endo_tx_ate
						AND endo_nb_entidade = '".$aMotorista['enti_nb_id']."'
						AND endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
						AND endo_nb_userCadastro = user_nb_id
					LIMIT 1"
			)
		);
		if (!empty($aEndosso)){
			$c[2] = texto('Endosso:', "Endossado por " . $aEndosso['user_tx_login'] . " em " . data($aEndosso['endo_tx_dataCadastro'], 1), 6);
			$botoesVisiveis = [];
		}

		abre_form('Dados do Registro de Ponto');
		linha_form($c);
		fecha_form($botoesVisiveis);


		$gridFields = [
			'DATA'			=> 'data(pont_tx_data, 1)',
			'TIPO'			=> 'macr_tx_nome',
			'DATA CADASTRO'	=> 'data(pont_tx_dataCadastro,1)'
		];

		grid($sql, array_keys($gridFields), array_values($gridFields), '', '', 0, 'desc', -1);
		rodape();


		include "batida_ponto_html.php";
	}
?>