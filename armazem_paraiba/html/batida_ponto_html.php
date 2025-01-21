<link rel="stylesheet" href="css/batida_ponto.css">

<form id="form_submit" name="form_submit" method="post" action="">
	<input type="hidden" name="acao" id="acao" />
	<input type="hidden" name="id" id="id" />
	<input type="hidden" name="data" id="data" />
	<input type="hidden" name="idMacro" id="idMacro" />
	<input type="hidden" name="motivo" id="motivo"/>
	<input type="hidden" name="justificativa" id="justificativa"/>
	<input type="hidden" name="latitude" id="latitude">
	<input type="hidden" name="longitude" id="longitude">
	<input type="hidden" name="placa" id="placa" />
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
		const formattedTime = formattedHours+':'+formattedMinutes;

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
		<?=
			"let ultimoInicioJornada 	= '".($ultimoInicioJornada?? "")."';
			let jornadaEfetiva 			= '".($jornadaEfetiva?? "")."';
			let primeiroPonto 			= '".($pontos["primeiro"]["pont_tx_data"]?? "")."';
			let ultimoPonto 			= '".($pontos["ultimo"]["pont_tx_data"]?? "")."';
			let hoje 					= '".$hoje."';
			let idEntidade 				= '".$_SESSION["user_nb_entidade"]."';
			let idMotivo 				= '".($motivo["moti_nb_id"]?? "")."';"
		?>

		if (['2'].includes(idMacro)) {
			let localTimeString = (new Date()).toLocaleTimeString(undefined, {
				hour:   '2-digit',
				minute: '2-digit',
			});
			jornadaAtual = (ultimoInicioJornada != '')? operarHorarios([localTimeString, ultimoInicioJornada], '-'): '00:00';
			if(primeiroPonto.substring(0, 10) < hoje){
				primPontoTime = new Date(primeiroPonto);
				hojeTime = new Date();
				timestampValue = hojeTime.getTime()-primPontoTime.getTime();
				horas = Math.floor(timestampValue/1000/60/60);
				jornadaAtual = horas+":"+String(Math.floor(((timestampValue/1000/60/60)-horas)*60)).padStart(2, '0');
			}
			jornadaEfetiva = operarHorarios([jornadaEfetiva, jornadaAtual], '+');
			duracao = calculateElapsedTime(primeiroPonto);
			msg += "<br><br>Total da jornada efetiva: "+jornadaEfetiva;
		}

		if (['4'].includes(idMacro)) { //Se está encerrando uma refeição
			duracao = calculateElapsedTime(primeiroPonto);
			msg += "<br><br>Duração Esperada: 01:00";
		}

		if (['4', '6', '8', '10', '12'].includes(idMacro)) { //Se está encerrando algum intervalo
			duracao = calculateElapsedTime(ultimoPonto);
			msg += "<br><br>Duração: "+duracao;
		}

		var placa = document.getElementById('placa').value;

		if (['2', '4', '6', '8', '10', '12'].includes(idMacro)) { 	//Se está encerrando algum intervalo
			confirmButtonText = 'ENCERRAR';
			confirmButtonClass = 'btn-danger';
		} else {													//Se está iniciando algum intervalo
			confirmButtonText = 'INICIAR';
			confirmButtonClass = 'btn-primary';
			if (placa === "") {
				msg += "<br><br><span style='color: red;' class='fa fa-warning'></span>Placa do veículo vazia";
			}
		}

		const modalContent = document.getElementById('modal-content');
		modalContent.innerHTML = msg;

		$('#myModal').modal('show');

		const confirmButton = document.getElementById('modal-confirm');
		confirmButton.innerHTML = confirmButtonText;
		confirmButton.className = 'btn '+confirmButtonClass;

		$('#modal-confirm').on('click', function() {
			$('#myModal').modal('hide');

			document.form_submit.acao.value = 'cadastraPonto';
			document.form_submit.id.value = idEntidade;
			document.form_submit.data.value = hoje;
			document.form_submit.placa.value = placa;
			document.form_submit.idMacro.value = idMacro;
			document.form_submit.justificativa.value = document.getElementById("justificativa").value;
			if(idMotivo != ""){
				document.form_submit.motivo.value = idMotivo;
			}
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
		const timeString = hours+':'+minutes;

		document.getElementById('clock').textContent = timeString;
	}

	updateClock(); // Atualizar imediatamente
	setInterval(updateClock, 1000); // Atualizar a cada segundo
</script>