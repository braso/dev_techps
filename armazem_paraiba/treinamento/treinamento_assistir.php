<?php
	include_once __DIR__."/../load_env.php";
	include_once __DIR__."/../conecta.php";

	// =====================================================
	// MÓDULO DE TREINAMENTO - Listagem para Usuário
	// =====================================================

	$usuarioId = $_SESSION["user_nb_id"] ?? 0;
	$nivelUsuario = $_SESSION["user_tx_nivel"] ?? "";
	$isAdmin = (strpos($nivelUsuario, "Administrador") !== false);

	// Buscar treinamentos disponíveis para o usuário
	function buscarTreinamentosDisponiveis($usuarioId, $isAdmin) {
		$treinamentos = [];

		if ($isAdmin) {
			// Admin vê todos os treinamentos ativos
			$rs = query(
				"SELECT t.*,
					(SELECT COUNT(*) FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ?) as usuario_progresso,
					(SELECT tp.trepr_nb_porcentagem_assistida FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ?) as porcentagem,
					(SELECT tp.trepr_nb_concluido FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ?) as concluido,
					(SELECT tp.trepr_nb_avaliacao_aprovada FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ?) as avaliacao_aprovada
				FROM treinamento t
				WHERE t.trei_tx_status = 'ativo'
				AND (t.trei_dt_data_liberacao IS NULL OR t.trei_dt_data_liberacao <= NOW())
				ORDER BY t.trei_nb_id DESC",
				"iiii",
				[$usuarioId, $usuarioId, $usuarioId, $usuarioId]
			);
		} else {
			// Buscar perfil do usuário logado
			$perfilUsuario = 0;
			$rsPerfil = query("SELECT perfil_nb_id FROM usuario_perfil WHERE ativo = 1 AND user_nb_id = ? LIMIT 1", "i", [$usuarioId]);
			if ($rsPerfil && ($rowPerfil = mysqli_fetch_assoc($rsPerfil))) {
				$perfilUsuario = (int)$rowPerfil["perfil_nb_id"];
			}

			// Usuário comum: buscar treinamentos que ele tem acesso
			// Verifica se o perfil do usuário está na lista de perfis permitidos do treinamento
			$rs = query(
				"SELECT t.*,
					(SELECT COUNT(*) FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ?) as usuario_progresso,
					(SELECT tp.trepr_nb_porcentagem_assistida FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ?) as porcentagem,
					(SELECT tp.trepr_nb_concluido FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ?) as concluido,
					(SELECT tp.trepr_nb_avaliacao_aprovada FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ?) as avaliacao_aprovada
				FROM treinamento t
				WHERE t.trei_tx_status = 'ativo'
				AND (t.trei_dt_data_liberacao IS NULL OR t.trei_dt_data_liberacao <= NOW())
				AND NOT EXISTS (
					SELECT 1 FROM treinamento_bloqueio tb
					WHERE tb.trebl_nb_treinamento_id = t.trei_nb_id
					AND tb.trebl_nb_usuario_id = ?
				)
				AND (
					-- Sem perfil definido: todos com acesso
					t.trei_tx_tipo_usuario_permitido IS NULL
					OR t.trei_tx_tipo_usuario_permitido = ''
					-- Perfil do usuário está na lista de perfis permitidos (número ou string)
					OR JSON_CONTAINS(t.trei_tx_tipo_usuario_permitido, ?)
					OR JSON_CONTAINS(t.trei_tx_tipo_usuario_permitido, ?)
					-- Atribuído individualmente ao usuário
					OR EXISTS (
						SELECT 1 FROM treinamento_atribuicao ta
						WHERE ta.treate_nb_treinamento_id = t.trei_nb_id
						AND ta.treate_nb_usuario_id = ?
					)
				)
				ORDER BY t.trei_nb_id DESC",
				"iiiiisii",
				[$usuarioId, $usuarioId, $usuarioId, $usuarioId, $usuarioId, '"' . $perfilUsuario . '"', $perfilUsuario, $usuarioId]
			);
		}

		if ($rs) {
			while ($row = mysqli_fetch_assoc($rs)) {
				$treinamentos[] = $row;
			}
		}

		return $treinamentos;
	}

	// Buscar treinamentos
	$treinamentos = buscarTreinamentosDisponiveis($usuarioId, $isAdmin);

	// Incluir cabecalho
	cabecalho("Meus Treinamentos");

	echo "
	<style>
		.treinamento-card {
			border: 1px solid #ddd;
			border-radius: 8px;
			margin-bottom: 20px;
			background: #fff;
			box-shadow: 0 2px 4px rgba(0,0,0,0.05);
			transition: transform 0.2s, box-shadow 0.2s;
			overflow: hidden;
		}
		.treinamento-card:hover {
			transform: translateY(-2px);
			box-shadow: 0 4px 8px rgba(0,0,0,0.1);
		}
		.treinamento-card .card-header {
			background: #3c8dbc;
			color: #fff;
			padding: 10px 15px;
			font-weight: bold;
		}
		.treinamento-card .card-body {
			padding: 15px;
		}
		.treinamento-card .thumbnail {
			width: 100%;
			height: 180px;
			object-fit: cover;
			border-radius: 4px;
			background: #f0f0f0;
		}
		.treinamento-card .info-item {
			margin-bottom: 8px;
		}
		.treinamento-card .info-item i {
			width: 20px;
			color: #666;
		}
		.treinamento-card .progress {
			margin-top: 10px;
			margin-bottom: 10px;
		}
		.treinamento-card .badge-status {
			font-size: 12px;
			padding: 5px 10px;
		}
		.treinamento-card .btn-assistir {
			width: 100%;
			padding: 10px;
			font-size: 16px;
		}
		.filtros-container {
			background: #f9f9f9;
			padding: 15px;
			border-radius: 8px;
			margin-bottom: 20px;
		}
		.stats-bar {
			background: #fff;
			padding: 15px;
			border-radius: 8px;
			margin-bottom: 20px;
			border: 1px solid #eee;
		}
		.stat-item {
			text-align: center;
			padding: 10px;
		}
		.stat-item .stat-number {
			font-size: 28px;
			font-weight: bold;
			color: #3c8dbc;
		}
		.stat-item .stat-label {
			color: #666;
			font-size: 12px;
		}
	</style>

	<div class='container-fluid'>
		<!-- Estatísticas -->
		<div class='row stats-bar'>
			<div class='col-md-3 col-sm-6'>
				<div class='stat-item'>
					<div class='stat-number'>" . count($treinamentos) . "</div>
					<div class='stat-label'>Treinamentos Disponíveis</div>
				</div>
			</div>
			<div class='col-md-3 col-sm-6'>
				<div class='stat-item'>
					<div class='stat-number'>" . count(array_filter($treinamentos, function($t) { return ($t['usuario_progresso'] ?? 0) > 0 && ($t['concluido'] ?? 0) == 0; })) . "</div>
					<div class='stat-label'>Em Andamento</div>
				</div>
			</div>
			<div class='col-md-3 col-sm-6'>
				<div class='stat-item'>
					<div class='stat-number'>" . count(array_filter($treinamentos, function($t) { return ($t['concluido'] ?? 0) == 1; })) . "</div>
					<div class='stat-label'>Concluídos</div>
				</div>
			</div>
			<div class='col-md-3 col-sm-6'>
				<div class='stat-item'>
					<div class='stat-number'>" . count(array_filter($treinamentos, function($t) { return ($t['usuario_progresso'] ?? 0) == 0; })) . "</div>
					<div class='stat-label'>Não Iniciados</div>
				</div>
			</div>
		</div>

		<!-- Filtros -->
		<div class='filtros-container'>
			<div class='row'>
				<div class='col-md-4'>
					<label>Buscar por título:</label>
					<input type='text' id='buscaTitulo' class='form-control' placeholder='Digite para buscar...' onkeyup='filtrarTreinamentos()'>
				</div>
				<div class='col-md-3'>
					<label>Status:</label>
					<select id='filtroStatus' class='form-control' onchange='filtrarTreinamentos()'>
						<option value=''>Todos</option>
						<option value='nao_iniciado'>Não Iniciado</option>
						<option value='em_andamento'>Em Andamento</option>
						<option value='concluido'>Concluído</option>
					</select>
				</div>
				<div class='col-md-3'>
					<label>Tipo:</label>
					<select id='filtroTipo' class='form-control' onchange='filtrarTreinamentos()'>
						<option value=''>Todos</option>
						<option value='dss'>DSS</option>
						<option value='treinamento'>Treinamento</option>
					</select>
				</div>
				<div class='col-md-2'>
					<label>&nbsp;</label><br>
					<button class='btn btn-default' onclick='limparFiltros()'><i class='fa fa-times'></i> Limpar</button>
				</div>
			</div>
		</div>

		<!-- Lista de Treinamentos -->
		<div class='row' id='listaTreinamentos'>";

	if (empty($treinamentos)) {
		echo "
			<div class='col-md-12'>
				<div class='alert alert-info'>
					<i class='fa fa-info-circle'></i> Nenhum treinamento disponível no momento.
				</div>
			</div>";
	} else {
		foreach ($treinamentos as $t) {
			$treinamentoId = $t["trei_nb_id"];
			$titulo = htmlspecialchars($t["trei_tx_titulo"]);
			$descricao = htmlspecialchars($t["trei_tx_descricao"] ?? "");
			$tipo = $t["trei_tx_tipo"];
			$tipoLabel = ($tipo === "dss") ? "DSS" : "Treinamento";
			$cargaHoraria = $t["trei_nb_carga_horaria"] ?? 0;
			$obrigatorio = ($t["trei_nb_obrigatorio"] ?? 0) == 1;
			$thumbnail = $t["trei_tx_thumbnail"] ?? "";
			$porcentagem = round($t["porcentagem"] ?? 0, 1);
			$concluido = ($t["concluido"] ?? 0) == 1;
			$aprovado = ($t["avaliacao_aprovada"] ?? 0) == 1;
			$progresso = $t["usuario_progresso"] ?? 0;

			// Definir status
			if ($concluido) {
				$statusClass = "badge-success";
				$statusLabel = "Concluído";
				$btnClass = "btn-success";
				$btnLabel = "<i class='fa fa-check'></i> Concluído";
				$btnAction = "";
			} elseif ($progresso > 0) {
				$statusClass = "badge-warning";
				$statusLabel = "Em Andamento";
				$btnClass = "btn-warning";
				$btnLabel = "<i class='fa fa-play'></i> Continuar";
				$btnAction = "treinamento_player.php?id={$treinamentoId}";
			} else {
				$statusClass = "badge-info";
				$statusLabel = "Não Iniciado";
				$btnClass = "btn-primary";
				$btnLabel = "<i class='fa fa-play'></i> Assistir";
				$btnAction = "treinamento_player.php?id={$treinamentoId}";
			}

			// Thumbnail
			$thumbSrc = !empty($thumbnail) ? "treinamento/uploads/{$thumbnail}" : "";
			$thumbHtml = !empty($thumbSrc)
				? "<img src='{$thumbSrc}' class='thumbnail' alt='{$titulo}'>"
				: "<div class='thumbnail' style='display:flex;align-items:center;justify-content:center;background:#3c8dbc;color:#fff;font-size:48px;'><i class='fa fa-graduation-cap'></i></div>";

			// Badge tipo
			$tipoBadgeClass = ($tipo === "dss") ? "badge-primary" : "badge-info";
			$obrigatorioBadge = $obrigatorio ? "<span class='badge badge-danger'>Obrigatório</span> " : "";

			echo "
			<div class='col-md-4 col-sm-6 treinamento-item'
				data-titulo='" . strtolower($t["trei_tx_titulo"]) . "'
				data-status='" . ($concluido ? "concluido" : ($progresso > 0 ? "em_andamento" : "nao_iniciado")) . "'
				data-tipo='{$tipo}'>
				<div class='treinamento-card'>
					<div class='card-header'>
						<span class='badge {$tipoBadgeClass}'>{$tipoLabel}</span>
						<span class='badge {$statusClass}'>{$statusLabel}</span>
						{$obrigatorioBadge}
					</div>
					<div class='card-body'>
						{$thumbHtml}
						<h4 style='margin-top:10px;'>{$titulo}</h4>
						<p class='text-muted' style='font-size:13px;'>" . substr($descricao, 0, 120) . (strlen($descricao) > 120 ? "..." : "") . "</p>

						<div class='info-item'>
							<i class='fa fa-clock'></i> <strong>{$cargaHoraria}</strong> minutos
						</div>";

			if ($progresso > 0) {
				echo "
						<div class='progress progress-mini'>
							<div class='progress-bar progress-bar-success' role='progressbar' style='width:{$porcentagem}%'></div>
						</div>
						<small class='text-muted'>{$porcentagem}% assistido</small>";
			}

			echo "
						<div style='margin-top:15px;'>";

			if (!empty($btnAction)) {
				echo "<a href='{$btnAction}' class='btn {$btnClass} btn-assistir'>{$btnLabel}</a>";
			} else {
				echo "<button class='btn {$btnClass} btn-assistir' disabled>{$btnLabel}</button>";
			}

			echo "
						</div>
					</div>
				</div>
			</div>";
		}
	}

	echo "
		</div>
	</div>

	<script>
		function filtrarTreinamentos() {
			var busca = $('#buscaTitulo').val().toLowerCase();
			var status = $('#filtroStatus').val();
			var tipo = $('#filtroTipo').val();

			$('.treinamento-item').each(function(){
				var el = $(this);
				var titulo = el.data('titulo');
				var statusItem = el.data('status');
				var tipoItem = el.data('tipo');

				var mostraTitulo = !busca || titulo.indexOf(busca) !== -1;
				var mostraStatus = !status || statusItem === status;
				var mostraTipo = !tipo || tipoItem === tipo;

				if(mostraTitulo && mostraStatus && mostraTipo){
					el.show();
				} else {
					el.hide();
				}
			});
		}

		function limparFiltros() {
			$('#buscaTitulo').val('');
			$('#filtroStatus').val('');
			$('#filtroTipo').val('');
			filtrarTreinamentos();
		}
	</script>";

	rodape();
