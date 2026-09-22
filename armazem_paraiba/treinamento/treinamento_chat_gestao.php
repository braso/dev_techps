<?php
	include_once __DIR__."/../load_env.php";
	include_once __DIR__."/../conecta.php";
	include_once __DIR__."/../check_permission.php";
	verificaPermissao('/treinamento/cadastro_treinamento.php');

	// =====================================================
	// MÓDULO DE TREINAMENTO - Gestão de Conversas (Chat)
	// O gestor acompanha e responde as dúvidas dos usuários
	// =====================================================

	$usuarioId = $_SESSION["user_nb_id"] ?? 0;
	$nivelUsuario = $_SESSION["user_tx_nivel"] ?? "";
	$isAdmin = (strpos($nivelUsuario, "Administrador") !== false);
	$treinamentoId = (int)($_GET["id"] ?? $_GET["treinamento_id"] ?? $_POST["treinamento_id"] ?? 0);

	// Ao abrir a conversa de um treinamento, marca como visualizada até a última mensagem atual
	if ($treinamentoId > 0 && empty($_GET["acao_gestao"]) && empty($_POST["acao_gestao"])) {
		$ultimaMsg = mysqli_fetch_assoc(query(
			"SELECT MAX(trem_nb_id) AS ultimo FROM treinamento_mensagem WHERE trem_nb_treinamento_id = ?",
			"i",
			[$treinamentoId]
		));
		$ultimoIdLido = (int)($ultimaMsg["ultimo"] ?? 0);
		query(
			"INSERT INTO treinamento_mensagem_leitura (trei_nb_id, user_nb_id, trel_nb_ultimo_id_lido, trel_dt_data_leitura)
			 VALUES (?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE
				trel_nb_ultimo_id_lido = GREATEST(trel_nb_ultimo_id_lido, VALUES(trel_nb_ultimo_id_lido)),
				trel_dt_data_leitura = VALUES(trel_dt_data_leitura)",
			"iiis",
			[$treinamentoId, $usuarioId, $ultimoIdLido, date("Y-m-d H:i:s")]
		);
	}

	// =====================================================
	// AJAX: LISTAR TREINAMENTOS COM MÉTRICAS DE CONVERSA
	// =====================================================

	if (isset($_GET["acao_gestao"]) && $_GET["acao_gestao"] === "listar_treinamentos") {
		header('Content-Type: application/json');
		$treinamentos = [];
		$rs = query(
			"SELECT t.trei_nb_id, t.trei_tx_titulo, t.trei_tx_status,
				(SELECT COUNT(*) FROM treinamento_mensagem m WHERE m.trem_nb_treinamento_id = t.trei_nb_id) AS total_mensagens,
				(SELECT COUNT(*) FROM treinamento_mensagem m
					WHERE m.trem_nb_treinamento_id = t.trei_nb_id
					AND m.trem_tx_usuario_nivel NOT LIKE '%Administrador%'
					AND m.trem_nb_id > COALESCE(GREATEST(
						(SELECT MAX(m2.trem_nb_id) FROM treinamento_mensagem m2
							WHERE m2.trem_nb_treinamento_id = t.trei_nb_id
							AND m2.trem_tx_usuario_nivel LIKE '%Administrador%'),
						(SELECT tl.trel_nb_ultimo_id_lido FROM treinamento_mensagem_leitura tl
							WHERE tl.trei_nb_id = t.trei_nb_id AND tl.user_nb_id = ?)
					), 0)
				) AS pendentes,
				(SELECT m3.trem_tx_usuario_nome FROM treinamento_mensagem m3 WHERE m3.trem_nb_treinamento_id = t.trei_nb_id ORDER BY m3.trem_nb_id DESC LIMIT 1) AS ultimo_autor,
				(SELECT m3.trem_dt_data_cadastro FROM treinamento_mensagem m3 WHERE m3.trem_nb_treinamento_id = t.trei_nb_id ORDER BY m3.trem_nb_id DESC LIMIT 1) AS ultima_data
			FROM treinamento t
			WHERE t.trei_tx_status = 'ativo'
			ORDER BY pendentes DESC, total_mensagens DESC, t.trei_nb_id DESC",
			"i",
			[$usuarioId]
		);
		while ($rs && ($row = mysqli_fetch_assoc($rs))) {
			$treinamentos[] = $row;
		}
		echo json_encode(["success" => true, "treinamentos" => $treinamentos]);
		exit;
	}

	// =====================================================
	// AJAX: LISTAR MENSAGENS DE UM TREINAMENTO
	// =====================================================

	if (isset($_GET["acao_gestao"]) && $_GET["acao_gestao"] === "mensagens") {
		header('Content-Type: application/json');
		if ($treinamentoId <= 0) {
			echo json_encode(["success" => false, "mensagens" => []]);
			exit;
		}
		$aposId = (int)($_GET["apos_id"] ?? 0);
		$mensagens = [];
		$rs = query(
			"SELECT * FROM treinamento_mensagem WHERE trem_nb_treinamento_id = ? AND trem_nb_id > ? ORDER BY trem_nb_id ASC LIMIT 200",
			"ii",
			[$treinamentoId, $aposId]
		);
		while ($rs && ($row = mysqli_fetch_assoc($rs))) {
			$mensagens[] = $row;
		}
		echo json_encode(["success" => true, "mensagens" => $mensagens]);
		exit;
	}

	// =====================================================
	// AJAX: ENVIAR RESPOSTA (texto)
	// =====================================================

	if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao_gestao"] ?? "") === "enviar") {
		header('Content-Type: application/json');
		$treinamentoId = (int)($_POST["treinamento_id"] ?? 0);
		$texto = trim((string)($_POST["texto"] ?? ""));
		if ($treinamentoId <= 0 || $texto === "") {
			echo json_encode(["success" => false, "message" => "Não foi possível enviar a mensagem."]);
			exit;
		}
		query(
			"INSERT INTO treinamento_mensagem (trem_nb_treinamento_id, trem_nb_usuario_id, trem_tx_usuario_nome, trem_tx_usuario_login, trem_tx_usuario_nivel, trem_tx_tipo, trem_tx_mensagem) VALUES (?, ?, ?, ?, ?, 'texto', ?)",
			"iissss",
			[$treinamentoId, $usuarioId, $_SESSION["user_tx_nome"] ?? "", $_SESSION["user_tx_login"] ?? "", $_SESSION["user_tx_nivel"] ?? "", $texto]
		);
		echo json_encode(["success" => true]);
		exit;
	}

	// =====================================================
	// PÁGINA PRINCIPAL
	// =====================================================

	cabecalho("Gestão de Conversas");

	echo "
	<style>
		.gc-container { margin-top: 10px; }
		.gc-card {
			border: 1px solid #ddd;
			border-radius: 8px;
			margin-bottom: 12px;
			background: #fff;
			padding: 12px 15px;
			cursor: pointer;
			transition: box-shadow 0.2s;
		}
		.gc-card:hover { box-shadow: 0 3px 8px rgba(0,0,0,0.12); }
		.gc-card-titulo { font-size: 15px; font-weight: bold; color: #333; overflow-wrap: anywhere; word-break: break-word; }
		.gc-card-badges { margin-top: 4px; }
		.gc-card-meta { font-size: 12px; color: #666; margin-top: 4px; overflow-wrap: anywhere; word-break: break-word; }
		.gc-badge-pendente {
			background: #d9534f;
			color: #fff;
			border-radius: 12px;
			padding: 3px 9px;
			font-size: 12px;
			font-weight: bold;
			margin-left: 8px;
		}
		.gc-badge-zero { background: #5cb85c; }
		.gc-chat-container {
			max-height: 480px;
			overflow-y: auto;
			border: 1px solid #ddd;
			border-radius: 6px;
			padding: 12px;
			background: #fafafa;
			margin-top: 10px;
		}
		.gc-msg { margin-bottom: 12px; max-width: 80%; padding: 8px 12px; border-radius: 8px; }
		.gc-msg-outro { background: #e9f1f8; border: 1px solid #c9dcec; }
		.gc-msg-meu { background: #d4edda; border: 1px solid #b7dcc3; margin-left: auto; }
		.gc-msg-cabecalho { font-size: 12px; margin-bottom: 3px; color: #444; overflow-wrap: anywhere; word-break: break-word; }
		.gc-msg-corpo { font-size: 13px; overflow-wrap: anywhere; word-break: break-word; }
		.gc-imagem { max-width: min(220px, 100%); border-radius: 6px; border: 1px solid #ddd; }
		.gc-chat-container audio { max-width: min(280px, 100%) !important; }
		.gc-badge-nivel {
			font-size: 10px;
			padding: 2px 6px;
			border-radius: 8px;
			vertical-align: middle;
			margin-left: 6px;
		}
		.gc-badge-admin { background: #3c8dbc; color: #fff; }
		.gc-badge-usuario { background: #888; color: #fff; }
		.gc-form { margin-top: 12px; }
		@media (max-width: 767px) {
			.gc-card { padding: 10px 12px; }
			.gc-chat-container { max-height: 60vh; }
			.gc-msg { max-width: 92%; }
			.gc-chat-container audio { max-width: 100% !important; }
		}
	</style>

	<div class='container-fluid'>
		<div class='row'>
			<div class='col-md-12'>
				<div class='info-card'>
					<h4><i class='fa fa-comments'></i> Gestão de Conversas</h4>
					<p class='text-muted'>
						<i class='fa fa-info-circle'></i> Acompanhe as dúvidas dos usuários nos treinamentos e responda diretamente. Mensagens sem resposta do gestor aparecem em <strong>vermelho</strong>.
						<br><small><i class='fa fa-bell'></i> <strong>Dica:</strong> os contadores de <strong>\"sem resposta\"</strong> (e as notificações) saem da tela automaticamente assim que o gestor <strong>responde</strong> a conversa.</small>
					</p>
				</div>
			</div>
		</div>

		<div class='row'>
			<div class='col-md-12' id='gcLista'>";

	if ($treinamentoId > 0) {
		$treinamento = carregar("treinamento", $treinamentoId);
		if (!empty($treinamento)) {
			echo "
				<a href='treinamento_chat_gestao.php' class='btn btn-default btn-sm' style='margin-bottom:10px;'><i class='fa fa-arrow-left'></i> Voltar para a lista</a>
				<div class='info-card'>
					<h4><i class='fa fa-video-camera'></i> " . htmlspecialchars($treinamento["trei_tx_titulo"]) . "</h4>
					<div id='gcChatContainer' class='gc-chat-container'></div>
					<div class='gc-form'>
						<div class='input-group'>
							<input type='text' id='gcTexto' class='form-control' placeholder='Escreva sua resposta...' maxlength='1000'>
							<span class='input-group-btn'>
								<button type='button' class='btn btn-primary' id='gcEnviar'><i class='fa fa-paper-plane'></i> Responder</button>
							</span>
						</div>
					</div>
				</div>";
		}
	} else {
		echo "
			<div class='info-card'>
				<div class='row' id='gcCards'></div>
			</div>";
	}

	echo "
			</div>
		</div>
	</div>

	<script>
		var gcTreinamentoId = {$treinamentoId};
		var gcUltimoId = 0;
		var gcBaseUrl = '{$_ENV["URL_BASE"]}{$CONTEX["path"]}/treinamento/uploads/';
		var gcUsuarioId = {$usuarioId};

		function gcMontarCard(t) {
			var pendentes = parseInt(t.pendentes || 0);
			var badge = pendentes > 0
				? '<span class=\"gc-badge-pendente\">' + pendentes + ' sem resposta</span>'
				: '<span class=\"gc-badge-pendente gc-badge-zero\">Em dia</span>';
			var total = parseInt(t.total_mensagens || 0);
			var meta = 'Sem mensagens ainda';
			if(t.ultimo_autor) {
				meta = 'Última mensagem de <strong>' + $('<span>').text(t.ultimo_autor).html() + '</strong>';
				if(t.ultima_data) {
					var d = new Date(t.ultima_data);
					if(!isNaN(d)) {
						meta += ' em ' + String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear() + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
					}
				}
			}
			meta += ' &middot; ' + total + ' mensagem(ns)';
			return '<div class=\"gc-card\" data-treinamento=\"' + t.trei_nb_id + '\">' +
				'<div class=\"gc-card-titulo\"><i class=\"fa fa-video-camera\"></i> ' + $('<span>').text(t.trei_tx_titulo).html() + '</div>' +
				'<div class=\"gc-card-badges\">' + badge + '</div>' +
				'<div class=\"gc-card-meta\">' + meta + '</div>' +
			'</div>';
		}

		function gcCarregarLista() {
			$.get(window.location.pathname, { acao_gestao: 'listar_treinamentos' }, function(data) {
				if(!data.success) return;
				var container = $('#gcCards');
				if(!container.length) return;
				if(!data.treinamentos || data.treinamentos.length === 0) {
					container.html('<div class=\"col-md-12\"><div class=\"alert alert-info\"><i class=\"fa fa-info-circle\"></i> Nenhum treinamento ativo.</div></div>');
					return;
				}
				var html = '';
				data.treinamentos.forEach(function(t) {
					html += '<div class=\"col-md-6 col-sm-12\">' + gcMontarCard(t) + '</div>';
				});
				container.html(html);
			}, 'json');
		}

		function gcMontarMensagem(m) {
			var tipo = m.trem_tx_tipo || 'texto';
			var corpo = '';
			if(tipo === 'texto') {
				corpo = $('<div>').text(m.trem_tx_mensagem || '').html().replace(/\\n/g, '<br>');
			} else if(tipo === 'imagem') {
				corpo = '<a href=\"' + gcBaseUrl + m.trem_tx_arquivo + '\" target=\"_blank\"><img src=\"' + gcBaseUrl + m.trem_tx_arquivo + '\" class=\"gc-imagem\" alt=\"imagem\"></a>';
			} else if(tipo === 'audio') {
				corpo = '<audio controls preload=\"none\" style=\"max-width:280px;\"><source src=\"' + gcBaseUrl + m.trem_tx_arquivo + '\"></audio>';
			}
			var ehMeu = (parseInt(m.trem_nb_usuario_id) === gcUsuarioId);
			var nome = $('<span>').text(m.trem_tx_usuario_nome || 'Usuário').html();
			var login = $('<span>').text(m.trem_tx_usuario_login || '').html();
			var nivel = m.trem_tx_usuario_nivel || '';
			var ehAdmin = (nivel.indexOf('Administrador') !== -1);
			var badgeNivel = '<span class=\"gc-badge-nivel ' + (ehAdmin ? 'gc-badge-admin' : 'gc-badge-usuario') + '\">' + $('<span>').text(ehAdmin ? 'Gestor' : nivel).html() + '</span>';
			var data = new Date(m.trem_dt_data_cadastro);
			var dataLabel = '';
			if(!isNaN(data)) {
				dataLabel = String(data.getDate()).padStart(2,'0') + '/' + String(data.getMonth()+1).padStart(2,'0') + '/' + data.getFullYear() + ' ' + String(data.getHours()).padStart(2,'0') + ':' + String(data.getMinutes()).padStart(2,'0');
			}
			return '<div class=\"gc-msg ' + (ehMeu ? 'gc-msg-meu' : 'gc-msg-outro') + '\">' +
				'<div class=\"gc-msg-cabecalho\"><i class=\"fa fa-user-circle\"></i> <strong>' + nome + '</strong>' + badgeNivel +
				'<span class=\"text-muted\" style=\"font-size:11px;\"> (' + login + ') - ' + dataLabel + '</span></div>' +
				'<div class=\"gc-msg-corpo\">' + corpo + '</div></div>';
		}

		function gcCarregarMensagens() {
			if(gcTreinamentoId <= 0) return;
			$.get(window.location.pathname, {
				acao_gestao: 'mensagens',
				treinamento_id: gcTreinamentoId,
				apos_id: gcUltimoId
			}, function(data) {
				if(!data.success || !data.mensagens || data.mensagens.length === 0) return;
				var container = $('#gcChatContainer');
				var estavaVazio = container.find('.gc-msg').length === 0;
				data.mensagens.forEach(function(m) {
					container.append(gcMontarMensagem(m));
					gcUltimoId = Math.max(gcUltimoId, parseInt(m.trem_nb_id));
				});
				container.scrollTop(container[0].scrollHeight);
			}, 'json');
		}

		function gcEnviarResposta() {
			var texto = $('#gcTexto').val().trim();
			if(!texto) return;
			$.post(window.location.pathname, {
				acao_gestao: 'enviar',
				treinamento_id: gcTreinamentoId,
				texto: texto
			}, function(data) {
				if(data.success) {
					$('#gcTexto').val('');
					gcCarregarMensagens();
				} else {
					Swal.fire({ icon: 'error', title: 'Erro', text: data.message || 'Não foi possível enviar.' });
				}
			}, 'json');
		}

		$(document).ready(function() {
			$(document).on('click', '.gc-card', function() {
				var id = $(this).attr('data-treinamento');
				if(id) window.location.href = 'treinamento_chat_gestao.php?id=' + id;
			});
			if(gcTreinamentoId > 0) {
				gcCarregarMensagens();
				setInterval(gcCarregarMensagens, 5000);
				$('#gcEnviar').on('click', gcEnviarResposta);
				$('#gcTexto').on('keydown', function(e) { if(e.key === 'Enter') gcEnviarResposta(); });
			} else {
				gcCarregarLista();
				setInterval(gcCarregarLista, 15000);
			}
		});
	</script>";

	rodape();