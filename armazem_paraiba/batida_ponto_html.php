<link rel="stylesheet" href="css/batida_ponto.css">

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
			<?php
				if(!empty($ultimoInicioJornada)){
					echo "jornadaAtual = operarHorarios([localTimeString, '".$ultimoInicioJornada."'], '-');";
				}else{
					echo "jornadaAtual = '00:00';";
				}
			?>
			jornadaEfetiva = operarHorarios(['<?php echo$jornadaEfetiva?>', jornadaAtual], '+');
			
			duracao = calculateElapsedTime('<?php echo ($pontos['primeiro']['pont_tx_data']?? '') ?>');
			msg += "<br><br>Total da jornada efetiva: " + jornadaEfetiva;
		}

		if (['4'].includes(idMacro)) {
			duracao = calculateElapsedTime('<?php echo ($pontos['primeiro']['pont_tx_data']?? '') ?>');
			msg += "<br><br>Duração Esperada: 01:00";
		}

		if (['4', '6', '8', '10', '12'].includes(idMacro)) {
			duracao = calculateElapsedTime('<?php echo ($pontos['ultimo']['pont_tx_data']?? '') ?>');
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
			document.form_submit.id.value = <?php echo $_SESSION['user_nb_entidade'] ?>;
			document.form_submit.data.value = '<?php echo $hoje ?>';
			document.form_submit.idMacro.value = idMacro;
			<?php echo (isset($motivo['moti_nb_id'])? 'document.form_submit.motivo.value = '.$motivo['moti_nb_id'].';': '') ?>;
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
