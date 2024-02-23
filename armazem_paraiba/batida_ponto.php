<?php
    /*Modo debug{
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//}*/
	include "funcoes_ponto.php";

	function cadastra_ponto() {
		$dataHora = date('Y-m-d')." ".date('H:i');
		$aMotorista = carregar('entidade', $_POST['id']);

		if(empty($_POST['motivo'])){
			$_POST['motivo'] = mysqli_fetch_all(
				query("SELECT moti_nb_id FROM motivo WHERE moti_tx_nome = 'Registro de ponto mobile' LIMIT 1"),
				MYSQLI_ASSOC
			)[0]['moti_nb_id'];
		}
		$_POST['motivo'] = intval($_POST['motivo']);

		$ultimoPonto = mysqli_fetch_all(
			query(
				"SELECT *, (macr_tx_codigoInterno = ".$_POST['idMacro'].") as sameTypeError FROM ponto
					JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
					JOIN user ON ponto.pont_nb_user = user.user_nb_id
					LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
					WHERE ponto.pont_tx_status != 'inativo' 
						AND pont_tx_data <= '".date('Y-m-d')." 23:59'
						AND pont_tx_matricula = '$aMotorista[enti_tx_matricula]'
					ORDER BY 
						pont_tx_data DESC, 
						pont_nb_id DESC 
					LIMIT 1"
			),
			MYSQLI_ASSOC
		)[0];

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
				'pont_tx_dataCadastro' 	=> date("Y-m-d H:i:s"),
				'pont_nb_motivo' 		=> $_POST['motivo'],
			];
			inserir('ponto', array_keys($novoPonto), array_values($novoPonto));
		}

		index();
		exit;
	}

	function criaBotaoRegistro(string $classe, int $tipoRegistro, string $nome, string $iconClass){
		return 
			"<button type='button'class='".$classe."' onclick='carregar_submit(\"".strval($tipoRegistro)."\",\" Tem certeza que deseja ".$nome."?\");'><br>
				<i style='font-size: 30px; min-height: 30px;' class='".$iconClass."'></i><br>
				".$nome."<br>
				&nbsp;
			</button>";
	}

	function index() {
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

		$condicoesPontoBasicas = 
			"ponto.pont_tx_status != 'inativo'
			AND ponto.pont_tx_matricula = '".$aMotorista['enti_tx_matricula']."'"
		;

		$abriuJornadaHoje = mysqli_fetch_assoc(
			query(
				"SELECT * FROM ponto
					WHERE ".$condicoesPontoBasicas."
						AND ponto.pont_tx_tipo = 1
						AND ponto.pont_tx_data LIKE '%".date('Y-m-d')."%'
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
							AND pont_tx_data <= '".date('Y-m-d 00:00:00')."'
						ORDER BY pont_tx_data DESC
						LIMIT 1;"
				)
			);

			if(!empty($temJornadaAberta) && intval($temJornadaAberta['temJornadaAberta'])){
				$jornadaFechadaHoje = mysqli_fetch_assoc(
					query(
						"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 2) as jornadaFechadaHoje FROM ponto
							WHERE ".$condicoesPontoBasicas."
								AND ponto.pont_tx_tipo = 2
								AND pont_tx_data LIKE '%".date('Y-m-d')."%'
							ORDER BY pont_tx_data ASC
							LIMIT 1;"
					)
				);
				if(!empty($jornadaFechadaHoje) && intval($jornadaFechadaHoje['jornadaFechadaHoje'])){
					$sqlDataInicio = date('Y-m-d H:i:s');
				}else{
					$sqlDataInicio = $temJornadaAberta['pont_tx_data'];
				}
			}else{
				$sqlDataInicio = date('Y-m-d H:i:s');
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
			3  => 'inicioRefeição', 
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
					if($value->format('Y-m-d') < date('Y-m-d')){
						$pares['jornada'][] = ['inicio' => $value->format("H:i"), 'diaAnterior' => true];
					}else{
						$pares['jornada'][] = ['inicio' => $value->format("H:i")];
					}
				break;
				case 2:
					$pares['jornada'][count($pares['jornada'])-1]['fim'] = $value->format("H:i");
				break;
				case 3:
					$pares['refeicao'][] = ['inicio' => $value->format("H:i")];
				break;
				case 4:
					$pares['refeicao'][count($pares['refeicao'])-1]['fim'] = $value->format("H:i");
				break;
				case 5:
					$pares['espera'][] = ['inicio' => $value->format("H:i")];
				break;
				case 6:
					$pares['espera'][count($pares['espera'])-1]['fim'] = $value->format("H:i");
				break;
				case 7:
					$pares['descanso'][] = ['inicio' => $value->format("H:i")];
				break;
				case 8:
					$pares['descanso'][count($pares['descanso'])-1]['fim'] = $value->format("H:i");
				break;
				case 9:
					$pares['repouso'][] = ['inicio' => $value->format("H:i")];
				break;
				case 10:
					$pares['repouso'][count($pares['repouso'])-1]['fim'] = $value->format("H:i");
				break;
				case 11:
					$pares['repousoEmbarcado'][] = ['inicio' => $value->format("H:i")];
				break;
				case 12:
					$pares['repousoEmbarcado'][count($pares['repousoEmbarcado'])-1]['fim'] = $value->format("H:i");
				break;
			}
		}

		$ultimoInicioJornada = !empty($pares['jornada'])? $pares['jornada'][count($pares['jornada'])-1]['inicio']: null;

		$jornadaCompleta = '00:00';
		for($f = 0; $f < count($pares['jornada']); $f++){
			if(!empty($pares['jornada'][$f]['fim'])){
				if(!empty($pares['jornada'][$f]['diaAnterior'])){
					$pares['jornada'][$f]['inicio'] = operarHorarios([$pares['jornada'][0]['inicio'], "24:00"], '-');
				}
				$jornada = operarHorarios([$pares['jornada'][$f]['fim'], $pares['jornada'][$f]['inicio']], '-');
				$jornadaCompleta = operarHorarios([$jornadaCompleta, $jornada], '+');
			}
		}
		
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
			'inicioJornada' 			=> criaBotaoRegistro('btn green margin-bottom 10', 1,  'Iniciar Jornada', 'fa fa-car fa-6'),
			'inicioRefeicao' 			=> criaBotaoRegistro('btn green margin-bottom 10', 3,  'Iniciar Refeição', 'fa fa-cutlery fa-6'),
			'inicioEspera' 				=> criaBotaoRegistro('btn green margin-bottom 10', 5,  'Iniciar Espera', 'fa fa-clock-o fa-6'),
			'inicioDescanso' 			=> criaBotaoRegistro('btn green margin-bottom 10', 7,  'Iniciar Descanso', 'fa fa-hourglass-start fa-6'),
			'inicioRepouso' 			=> criaBotaoRegistro('btn green margin-bottom 10', 9,  'Iniciar Repouso', 'fa fa-bed fa-6'),
			'inicioRepousoEmbarcado'	=> criaBotaoRegistro('btn green margin-bottom 10', 11, 'Iniciar Repouso Embarcado', 'fa fa-bed fa-6'),

			'fimJornada' 				=> criaBotaoRegistro('btn red margin-bottom 10', 2,  'Encerrar Jornada', 'fa fa-car fa-6'),
			'fimRefeicao' 				=> criaBotaoRegistro('btn red margin-bottom 10', 4,  'Encerrar Refeição', 'fa fa-cutlery fa-6'),
			'fimEspera' 				=> criaBotaoRegistro('btn red margin-bottom 10', 6,  'Encerrar Espera', 'fa fa-clock-o fa-6'),
			'fimDescanso' 				=> criaBotaoRegistro('btn red margin-bottom 10', 8,  'Encerrar Descanso', 'fa fa-hourglass-end fa-6'),
			'fimRepouso' 				=> criaBotaoRegistro('btn red margin-bottom 10', 10, 'Encerrar Repouso', 'fa fa-bed fa-6'),
			'fimRepousoEmbarcado' 		=> criaBotaoRegistro('btn red margin-bottom 10', 12, 'Encerrar Repouso Embarcado', 'fa fa-bed fa-6'),
		];

		$botoesVisiveis = [];

		if (empty($pontos['ultimo']['pont_tx_tipo']) || intval($pontos['ultimo']['pont_tx_tipo']) == 2) {
			$botoesVisiveis = [$botoes['inicioJornada']];
		} elseif ($pontos['ultimo']['pont_tx_tipo'] == 1 || in_array($pontos['ultimo']['pont_tx_tipo'], array_keys($fins))){
			$botoesVisiveis = [
				$botoes['fimJornada'], 
				$botoes['inicioRefeicao'], 
				$botoes['inicioEspera'], 
				$botoes['inicioDescanso'], 
				$botoes['inicioRepouso'], 
				$botoes['inicioRepousoEmbarcado']
			];
		}elseif(in_array($pontos['ultimo']['pont_tx_tipo'], array_keys($inicios))){
			$botoesVisiveis = [
				$botoes[$fins[$pontos['ultimo']['pont_tx_tipo']+1]]
			];
		}

		$c = [
			[texto('Hora', '<h1 id="clock">Carregando...</h1>', 2)]
		];
		$c[] = [
			texto('Matrícula', $aMotorista['enti_tx_matricula'], 2), 
			texto('Motorista', $aMotorista['enti_tx_nome'], 5),
			texto('CPF', $aMotorista['enti_tx_cpf'], 3)
		];

		$c[] = [
			campo('Data', 'data', data(date('Y-m-d')), 2, '', 'readonly=readonly'),
			texto('Motivo:', 'Registro de ponto mobile', 4),
			campo_hidden('motivo', 'Registro de ponto mobile')
		];

		$aEndosso = carrega_array(
			query(
				"SELECT user_tx_login, endo_tx_dataCadastro
					FROM endosso, user
					WHERE endo_tx_status = 'ativo'
						AND '".date('Y-m-d')."' BETWEEN endo_tx_de AND endo_tx_ate
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
		linha_form($c[0]);
		linha_form($c[1]);
		linha_form($c[2]);
		fecha_form($botoesVisiveis);


		$gridFields = [
			'CÓD'			=> 'pont_nb_id',  
			'DATA'			=> 'data(pont_tx_data)', 
			'HORA'			=> 'data(pont_tx_data,3)',  
			'TIPO'			=> 'macr_tx_nome',  
			'MOTIVO'		=> 'moti_tx_nome',  
			'USUÁRIO'		=> 'user_tx_login',  
			'DATA CADASTRO'	=> 'data(pont_tx_dataCadastro,1)'
		];

		grid($sql, array_keys($gridFields), array_values($gridFields), '', '', 2, 'desc', -1);
		rodape();
	?>

		<form id="form_submit" name="form_submit" method="post" action="">
			<input type="hidden" name="acao" id="acao" />
			<input type="hidden" name="id" id="id" />
			<input type="hidden" name="data" id="data" />
			<input type="hidden" name="idMacro" id="idMacro" />
			<input type="hidden" name="motivo" id="motivo"/>
		</form>

		<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="exampleModalLabel">Registrar Ponto</h4>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body" id="modal-content">
						<!-- O conteúdo da mensagem será inserido aqui -->
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-primary" id="modal-confirm">CONFIRMAR</button>
						<button type="button" class="btn btn-secondary" data-dismiss="modal" id="modal-cancel">Cancelar</button>
					</div>
				</div>
			</div>
		</div>

		<style>
			.modal-dialog {
				transform: translate(0, -50%);
				top: 30%;
				margin: 0 auto;
			}
		</style>
		<script>

		function operarHorarios(horarios = [], operacao){
			if(!Array.isArray(horarios) || horarios.length == 0 || !(['+', '-', '*', '/'].includes(operacao))){
				return "00:00";
			}
			let negative = (horarios[0][0] == '-');
			let result = horarios[0].split(':');
			result = parseInt(result[0]*60)+(negative?-1:1)*parseInt(result[1]);

			for(f = 1; f < horarios.length; f++){
				if(horarios[f]===null || horarios[f]===""){
					continue;
				}
				let negative = (horarios[f][0] == '-');
				horarios[f] = horarios[f].replace(['<b>', '</b>'], ['', '']);
				horarios[f] = horarios[f].split(':');
				horarios[f] = parseInt(horarios[f][0]*60)+(negative?-1:1)*parseInt(horarios[f][1]);
				switch(operacao){
					case '+':
						result += horarios[f];
					break;
					case '-':
						result -= horarios[f];
					break;
					case '*':
						result *= horarios[f];
					break;
					case '/':
						result /= horarios[f];
					break;
				}
			}

			result = [
				((result < 0)?'-':''),
				Math.abs(parseInt(result/60)),
				Math.abs(parseInt(result%60))
			];
			if(result[1] < 10){
				result[1] = '0'+result[1];
			}
			if(result[2] < 10){
				result[2] = '0'+result[2];
			}
			
			result = result[0]+result[1]+":"+result[2];
			
			return result;
		}

			function calculateElapsedTime(startTime) {
				const isoStartTime = startTime.replace(' ', 'T');
				const startDate = new Date(isoStartTime);
				const currentDate = new Date();

				// Configura as datas para o fuso horário UTC
				startDate.setMinutes(startDate.getMinutes() - startDate.getTimezoneOffset());
				currentDate.setMinutes(currentDate.getMinutes() - currentDate.getTimezoneOffset());

				// Calcula a diferença de tempo em minutos
				const timeDifferenceMinutes = Math.floor((currentDate - startDate) / 60000); // 60000 ms em um minuto

				// Extrai horas e minutos
				const hours = Math.floor(timeDifferenceMinutes / 60);
				const minutes = timeDifferenceMinutes % 60;

				// Formata a hora e os minutos como HH:MM
				const formattedHours = hours.toString().padStart(2, '0');
				const formattedMinutes = minutes.toString().padStart(2, '0');
				const formattedTime = formattedHours + ':' + formattedMinutes;

				return formattedTime;
			}

			function openModal(content) {
				const modal = document.getElementById('myModal');
				const modalContent = document.getElementById('modalContent');
				modalContent.innerHTML = content;
				modal.style.display = 'block';
			}

			function closeModal() {
				const modal = document.getElementById('myModal');
				modal.style.display = 'none';
			}

			function carregar_submit(idMacro, msg) {
				let duracao = '';
				let confirmButtonText = '';
				let confirmButtonClass = '';

				if (['2'].includes(idMacro)) {

					let localTimeString = (new Date()).toLocaleTimeString(undefined, {
						hour:   '2-digit',
						minute: '2-digit',
					});
					
					jornadaAtual = operarHorarios([localTimeString, '<?=$ultimoInicioJornada?>'], '-');
					jornadaEfetiva = operarHorarios(['<?=$jornadaEfetiva?>', jornadaAtual], '+');
					
					duracao = calculateElapsedTime('<?= ($pontos['primeiro']['pont_tx_data']?? '') ?>');
					msg += "<br><br>Total da jornada efetiva: " + jornadaEfetiva;
				}

				if (['4'].includes(idMacro)) {
					duracao = calculateElapsedTime('<?= ($pontos['primeiro']['pont_tx_data']?? '') ?>');
					msg += "<br><br>Duração Esperada: 01:00";
				}

				if (['4', '6', '8', '10', '12'].includes(idMacro)) {
					duracao = calculateElapsedTime('<?= ($pontos['ultimo']['pont_tx_data']?? '') ?>');
					msg += "<br><br>Duração: " + duracao;
				}

				if (['2', '4', '6', '8', '10', '12'].includes(idMacro)) {
					confirmButtonText = 'ENCERRAR';
					confirmButtonClass = 'btn-danger';
				} else {
					confirmButtonText = 'INICIAR';
					confirmButtonClass = 'btn-primary';
				}

				const modalContent = document.getElementById('modal-content');
				modalContent.innerHTML = msg;

				$('#myModal').modal('show');

				const confirmButton = document.getElementById('modal-confirm');
				confirmButton.innerHTML = confirmButtonText;
				confirmButton.className = 'btn ' + confirmButtonClass;

				$('#modal-confirm').on('click', function() {
					$('#myModal').modal('hide');
					document.form_submit.acao.value = 'cadastra_ponto';
					document.form_submit.id.value = <?= $_SESSION['user_nb_entidade'] ?>;
					document.form_submit.data.value = '<?= date('Y-m-d') ?>';
					document.form_submit.idMacro.value = idMacro;
					<?= (isset($motivo['moti_nb_id'])? 'document.form_submit.motivo.value = '.$motivo['moti_nb_id'].';': '') ?>;
					document.form_submit.submit();
				});

				$('#modal-cancel').on('click', function() {
					$('#myModal').modal('hide');
				});
			}



			function updateClock() {
				const now = new Date();
				const hours = String(now.getHours()).padStart(2, '0');
				const minutes = String(now.getMinutes()).padStart(2, '0');
				const timeString = hours + ':' + minutes;

				document.getElementById('clock').textContent = timeString;
			}

			updateClock(); // Atualizar imediatamente
			setInterval(updateClock, 1000); // Atualizar a cada segundo
		</script>

	<?
	}
?>