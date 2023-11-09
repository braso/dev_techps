<?php
	include "conecta.php";


	function cadastra_ajuste() {
		$_POST['hora'] = date('H:i');
		$_POST['data'] = date('Y-m-d');

		$aMotorista = carregar('entidade', $_POST['id']);

		$extra = " AND pont_tx_data LIKE '" . $_POST['data'] . " %' AND pont_tx_matricula = '$aMotorista[enti_tx_matricula]'";
		$ultimoPonto = "SELECT pont_tx_tipo
			FROM ponto
			JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
			JOIN user ON ponto.pont_nb_user = user.user_nb_id
			LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
			WHERE ponto.pont_tx_status != 'inativo' 
			$extra ORDER BY pont_tx_data DESC, pont_nb_id DESC LIMIT 1";

		$sqlUltimoPonto = query($ultimoPonto);
		$qtdeUltimoPonto = num_linhas($sqlUltimoPonto);
		$aUltimoPonto = carrega_array($sqlUltimoPonto);

		$queryMacroPonto = query("SELECT macr_tx_codigoInterno, macr_tx_codigoExterno FROM macroponto WHERE macr_nb_id = '" . $_POST['idMacro'] . "'");
		$aTipo = carrega_array($queryMacroPonto);

		if ($qtdeUltimoPonto > 0 && "$aUltimoPonto[pont_tx_tipo]" == "$aTipo[macr_tx_codigoInterno]") {
			set_status("ERRO: Último ponto é do mesmo tipo!");
			index();
			exit;
		}

		$campos = array('pont_nb_user', 'pont_tx_matricula', 'pont_tx_data', 'pont_tx_tipo', 'pont_tx_tipoOriginal', 'pont_tx_status', 'pont_tx_dataCadastro', 'pont_nb_motivo', 'pont_tx_descricao');
		$valores = array($_SESSION['user_nb_id'], $aMotorista['enti_tx_matricula'], "$_POST[data] $_POST[hora]", $aTipo[0], $aTipo[1], 'ativo', date("Y-m-d H:i:s"), $_POST['motivo'], $_POST['descricao']);
		inserir('ponto', $campos, $valores);


		index();
		exit;
	}



	function index() {

		
		cabecalho('Registrar Ponto');
		
		if (!isset($_SESSION['user_nb_entidade']) || empty($_SESSION['user_nb_entidade'])){
			echo "Motorista não localizado. Tente fazer o login novamente.";
			exit;
		}
		$_POST['id'] = $_SESSION['user_nb_entidade'];
		$_POST['data'] = date('Y-m-d');

		$aMotorista = carregar('entidade', $_POST['id']);

		$sqlCheck = query(
			"SELECT user_tx_login, endo_tx_dataCadastro".
				" FROM endosso, user".
				" WHERE endo_tx_mes = '".substr($_POST['data'], 0, 7)."-01'".
				" AND endo_nb_entidade = '".$aMotorista['enti_nb_id']."'".
				" AND endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."'".
				" AND endo_tx_status = 'ativo'".
				" AND endo_nb_userCadastro = user_nb_id".
				" LIMIT 1"
		);
		$aEndosso = carrega_array($sqlCheck);

		$extra = " AND pont_tx_data LIKE '" . $_POST['data'] . " %' AND pont_tx_matricula = '$aMotorista[enti_tx_matricula]'";


		
		$ponto = "SELECT pont_tx_tipo, pont_nb_id, pont_tx_data
			FROM ponto
			JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
			JOIN user ON ponto.pont_nb_user = user.user_nb_id
			LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
			WHERE ponto.pont_tx_status != 'inativo'";

		$aPrimeiroPonto = carrega_array(query(
			$ponto.
			" AND macr_tx_codigoInterno = '1'".
			$extra." ORDER BY pont_tx_data DESC, pont_nb_id DESC LIMIT 1"
		));
		
		$aUltimoPonto = carrega_array(query(
			$ponto.
			$extra." ORDER BY pont_tx_data DESC, pont_nb_id DESC LIMIT 1"
		));
		$ultimoTipo = $aUltimoPonto['pont_tx_tipo'];
		
		// $inicios = array(1, 3, 5, 7, 9, 11);
		// $fins = array(2, 4, 6, 8, 10, 12);

		$botao1 = "<button type='button'class='btn green margin-bottom-10' onclick='carregar_submit(\"1\",\"Tem certeza que deseja Iniciar Jornada?\");'> <br><i style='font-size: 30px;' class='fa fa-car fa-6'></i><br>Iniciar Jornada<br>&nbsp;</button>";
		$botao2 = "<button type='button'class='btn red margin-bottom-10' onclick='carregar_submit(\"2\",\"Tem certeza que deseja Encerrar Jornada?\");'> <br><i style='font-size: 30px;' class='fa fa-car fa-6'></i><br>Encerrar Jornada<br>&nbsp;</button>";
		$botao3 = "<button type='button'class='btn green margin-bottom-10' onclick='carregar_submit(\"3\",\"Tem certeza que deseja Iniciar Refeição?\");'> <br><i style='font-size: 30px;' class='fa fa-cutlery fa-6'></i><br>Iniciar Refeição<br>&nbsp;</button>";
		$botao4 = "<button type='button'class='btn red margin-bottom-10' onclick='carregar_submit(\"4\",\"Tem certeza que deseja Encerrar Refeição?\");'> <br><i style='font-size: 30px;' class='fa fa-cutlery fa-6'></i><br>Encerrar Refeição<br>&nbsp;</button>";
		$botao5 = "<button type='button'class='btn green margin-bottom-10' onclick='carregar_submit(\"5\",\"Tem certeza que deseja Iniciar Espera?\");'> <br><i style='font-size: 30px;' class='fa fa-clock-o fa-6'></i><br>Iniciar Espera<br>&nbsp;</button>";
		$botao6 = "<button type='button'class='btn red margin-bottom-10' onclick='carregar_submit(\"6\",\"Tem certeza que deseja Encerrar Espera?\");'> <br><i style='font-size: 30px;' class='fa fa-clock-o fa-6'></i><br>Encerrar Espera<br>&nbsp;</button>";
		$botao7 = "<button type='button'class='btn green margin-bottom-10' onclick='carregar_submit(\"7\",\"Tem certeza que deseja Iniciar Descanso?\");'> <br><i style='font-size: 30px;' class='fa fa-hourglass-start fa-6'></i><br>Iniciar Descanso<br>&nbsp;</button>";
		$botao8 = "<button type='button'class='btn red margin-bottom-10' onclick='carregar_submit(\"8\",\"Tem certeza que deseja Encerrar Descanso?\");'> <br><i style='font-size: 30px;' class='fa fa-hourglass-end fa-6'></i><br>Encerrar Descanso<br>&nbsp;</button>";
		$botao9 = "<button type='button'class='btn green margin-bottom-10' onclick='carregar_submit(\"9\",\"Tem certeza que deseja Iniciar Repouso?\");'> <br><i style='font-size: 30px;' class='fa fa-bed fa-6'></i><br>Iniciar Repouso<br>&nbsp;</button>";
		$botao10 = "<button type='button'class='btn red margin-bottom-10' onclick='carregar_submit(\"10\",\"Tem certeza que deseja Encerrar Repouso?\");'> <br><i style='font-size: 30px;' class='fa fa-bed fa-6'></i><br>Encerrar Repouso<br>&nbsp;</button>";
		$botao11 = "<button type='button'class='btn green margin-bottom-10' onclick='carregar_submit(\"11\",\"Tem certeza que deseja Iniciar Repouso Embarcado?\");'> <br><i style='font-size: 30px;' class='fa fa-bed fa-6'></i><br>Iniciar Repouso Embarcado<br>&nbsp;</button>";
		$botao12 = "<button type='button'class='btn red margin-bottom-10' onclick='carregar_submit(\"12\",\"Tem certeza que deseja Encerrar Repouso Embarcado?\");'> <br><i style='font-size: 30px;' class='fa fa-bed fa-6'></i><br>Encerrar Repouso Embarcado<br>&nbsp;</button>";

		if ($ultimoTipo == '' || $ultimoTipo == 2) {
			$botaoTemp = [$botao1];
		} elseif (in_array($ultimoTipo, [1,4,6,8,10,12])){
			$botaoTemp = [$botao2, $botao3, $botao5, $botao7, $botao9, $botao11];
		} elseif ($ultimoTipo == 3) {
			$botaoTemp = [$botao4];
		} elseif ($ultimoTipo == 5) {
			$botaoTemp = [$botao6];
		} elseif ($ultimoTipo == 7) {
			$botaoTemp = [$botao8];
		} elseif ($ultimoTipo == 9) {
			$botaoTemp = [$botao10];
		} elseif ($ultimoTipo == 11) {
			$botaoTemp = [$botao12];
		}

		$c0[] = texto('Hora', '<h1 id="clock">Carregando...</h1>', 2);

		$c[] = texto('Matrícula', $aMotorista['enti_tx_matricula'], 2);
		$c[] = texto('Motorista', $aMotorista['enti_tx_nome'], 5);
		$c[] = texto('CPF', $aMotorista['enti_tx_cpf'], 3);

		$c2[] = campo('Data', 'data', data($_POST['data']), 2, '', 'readonly=readonly');
		// $c2[] = combo_bd('Tipo', 'idMacro', '', 4, 'macroponto', '', "$extraMacro ORDER BY macr_nb_id ASC");
		$c2[] = combo_bd('Motivo:', 'motivo', '', 4, 'motivo', '', ' AND moti_nb_id = "31" AND moti_tx_tipo = "Ajuste"'); //VERIFICAR JS

		// $c3[] = textarea('Justificativa:','descricao','',12);

		if (count($aEndosso) == 0) {
			$botao = $botaoTemp;
		} else {
			$c2[] = texto('Endosso:', "Endossado por " . $aEndosso['user_tx_login'] . " em " . data($aEndosso['endo_tx_dataCadastro'], 1), 6);
		}

		abre_form('Dados do Registro de Ponto');
		linha_form($c0);
		linha_form($c);
		linha_form($c2);
		// linha_form($c3);
		fecha_form($botao);

		$sql = "SELECT *
			FROM ponto
			JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_nb_id
			JOIN user ON ponto.pont_nb_user = user.user_nb_id
			LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
			WHERE ponto.pont_tx_status != 'inativo' 
			$extra";


		$cab = array('CÓD', 'DATA', 'HORA', 'TIPO', 'MOTIVO', 'USUÁRIO', 'DATA CADASTRO');

		$val = array('pont_nb_id', 'data(pont_tx_data)', 'data(pont_tx_data,3)', 'macr_tx_nome', 'moti_tx_nome', 'user_tx_login', 'data(pont_tx_dataCadastro,1)');
		grid($sql, $cab, $val, '', '', 2, 'ASC', -1);

		rodape();

	?>

		<form id="form_submit" name="form_submit" method="post" action="">
			<input type="hidden" name="acao" id="acao" />
			<input type="hidden" name="id" id="id" />
			<input type="hidden" name="data" id="data" />
			<input type="hidden" name="idMacro" id="idMacro" />
			<input type="hidden" name="motivo" id="motivo" />
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


			// function carregar_submit(idMacro, msg) {
			// 	if (['2'].includes(idMacro)) {
			// 		let duracao = (calculateElapsedTime('<?= $aPrimeiroPonto['pont_tx_data'] ?>'))
			// 		msg += "\nTotal da jornada: " + duracao;

			// 	}
			// 	if (['4', '6', '8', '10', '12'].includes(idMacro)) {
			// 		let duracao = (calculateElapsedTime('<?= $aUltimoPonto['pont_tx_data'] ?>'))
			// 		msg += "\nDuração: " + duracao;
			// 	}
			// 	if (confirm(msg), 'CONFIRMAR') {
			// 		document.form_submit.acao.value = 'cadastra_ajuste';
			// 		document.form_submit.id.value = <?= $_POST['id'] ?>;
			// 		document.form_submit.data.value = '<?= $_POST['data'] ?>';
			// 		document.form_submit.idMacro.value = idMacro;
			// 		document.form_submit.motivo.value = 31;
			// 		document.form_submit.submit();
			// 	}
			// }

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
					duracao = calculateElapsedTime('<?= $aPrimeiroPonto['pont_tx_data'] ?>');
					msg += "<br><br>Total da jornada: " + duracao;
				}

				if (['4'].includes(idMacro)) {
					duracao = calculateElapsedTime('<?= $aPrimeiroPonto['pont_tx_data'] ?>');
					msg += "<br><br>Duração Esperada: 01:00";
				}

				if (['4', '6', '8', '10', '12'].includes(idMacro)) {
					duracao = calculateElapsedTime('<?= $aUltimoPonto['pont_tx_data'] ?>');
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
					document.form_submit.acao.value = 'cadastra_ajuste';
					document.form_submit.id.value = <?= $_POST['id'] ?>;
					document.form_submit.data.value = '<?= $_POST['data'] ?>';
					document.form_submit.idMacro.value = idMacro;
					document.form_submit.motivo.value = 31;
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