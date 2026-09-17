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
					(SELECT tp.trepr_nb_porcentagem_assistida FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ? ORDER BY tp.trepr_nb_id DESC LIMIT 1) as porcentagem,
					(SELECT tp.trepr_nb_concluido FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ? ORDER BY tp.trepr_nb_id DESC LIMIT 1) as concluido,
					(SELECT tp.trepr_nb_avaliacao_aprovada FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ? ORDER BY tp.trepr_nb_id DESC LIMIT 1) as avaliacao_aprovada
				FROM treinamento t
				WHERE t.trei_tx_status = 'ativo'
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

			// Empresa do usuário logado
			$empresaUsuario = (int)($_SESSION["user_nb_empresa"] ?? 0);

			// Usuário comum: buscar treinamentos que ele tem acesso
			// Verifica se o perfil do usuário está na lista de perfis permitidos do treinamento
			$rs = query(
				"SELECT t.*,
					(SELECT COUNT(*) FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ?) as usuario_progresso,
					(SELECT tp.trepr_nb_porcentagem_assistida FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ? ORDER BY tp.trepr_nb_id DESC LIMIT 1) as porcentagem,
					(SELECT tp.trepr_nb_concluido FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ? ORDER BY tp.trepr_nb_id DESC LIMIT 1) as concluido,
					(SELECT tp.trepr_nb_avaliacao_aprovada FROM treinamento_progresso tp WHERE tp.trepr_nb_treinamento_id = t.trei_nb_id AND tp.trepr_nb_usuario_id = ? ORDER BY tp.trepr_nb_id DESC LIMIT 1) as avaliacao_aprovada
				FROM treinamento t
				WHERE t.trei_tx_status = 'ativo'
				AND NOT EXISTS (
					SELECT 1 FROM treinamento_bloqueio tb
					WHERE tb.trebl_nb_treinamento_id = t.trei_nb_id
					AND tb.trebl_nb_usuario_id = ?
				)
				AND (
					-- Empresa do usuário habilitada (vazio = todas)
					t.trei_tx_empresas_habilitadas IS NULL
					OR t.trei_tx_empresas_habilitadas = ''
					OR JSON_CONTAINS(t.trei_tx_empresas_habilitadas, ?)
					OR JSON_CONTAINS(t.trei_tx_empresas_habilitadas, ?)
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
				"iiiiisiiii",
				[$usuarioId, $usuarioId, $usuarioId, $usuarioId, $usuarioId, '"' . $empresaUsuario . '"', $empresaUsuario, '"' . $perfilUsuario . '"', $perfilUsuario, $usuarioId]
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
		.treinamento-aba { border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.06); margin-bottom: 15px; }
		.treinamento-aba > .panel-heading { border-radius: 0; padding: 12px 16px; }
		.treinamento-aba .panel-title { font-size: 15px; font-weight: 600; }
		.treinamento-aba .panel-title .badge { font-size: 12px; }
		.treinamento-aba .panel-title small { font-size: 12px; font-weight: normal; }
		.treinamento-aba .panel-title .aba-seta { transition: transform 0.2s; }
		.treinamento-aba .panel-collapse { transition: none; }
		.treinamento-aba .panel-body { padding: 15px; }
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
				<div class='col-md-5'>
					<label>Buscar por título:</label>
					<input type='text' id='buscaTitulo' class='form-control' placeholder='Digite para buscar...' onkeyup='filtrarTreinamentos()'>
				</div>
				<div class='col-md-4'>
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

		<!-- Lista de Treinamentos por situação -->
		<div class='treinamento-abas'>";

	if (empty($treinamentos)) {
		echo "
			<div class='col-md-12'>
				<div class='alert alert-info'>
					<i class='fa fa-info-circle'></i> Nenhum treinamento disponível no momento.
				</div>
			</div>";
	} else {
		$buckets = ["pendentes" => "", "andamento" => "", "concluidos" => "", "proximos" => ""];
		$bucketsCount = ["pendentes" => 0, "andamento" => 0, "concluidos" => 0, "proximos" => 0];
		foreach ($treinamentos as $t) {
			$treinamentoId = $t["trei_nb_id"];
			$titulo = htmlspecialchars($t["trei_tx_titulo"]);
			$descricao = htmlspecialchars($t["trei_tx_descricao"] ?? "");
			$tipo = $t["trei_tx_tipo"];
			$tipoLabel = ($tipo === "dss") ? "DSS" : "Treinamento";
			$cargaHoraria = $t["trei_nb_carga_horaria"] ?? 0;
			$cargaHorariaLabel = sprintf("%02d:%02d", floor($cargaHoraria / 60), $cargaHoraria % 60);
			$obrigatorio = ($t["trei_nb_obrigatorio"] ?? 0) == 1;
			$thumbnail = $t["trei_tx_thumbnail"] ?? "";
			$porcentagem = round($t["porcentagem"] ?? 0, 1);
			$concluido = ($t["concluido"] ?? 0) == 1;
			$aprovado = ($t["avaliacao_aprovada"] ?? 0) == 1;
			$progresso = $t["usuario_progresso"] ?? 0;
			$ehSerieCard = ($t["trei_tx_serie"] ?? "nao") === "sim";

			// Bloqueio por data de liberação futura (card visível, mas sem acesso)
			$dataLiberacao = $t["trei_dt_data_liberacao"] ?? null;
			$bloqueadoLiberacao = !empty($dataLiberacao) && strtotime($dataLiberacao) > time();
			$dataLiberacaoLabel = !empty($dataLiberacao) ? date("d/m/Y", strtotime($dataLiberacao)) : "";

			// Série: calcular progresso pelos episódios (episódio atual / total)
			$episodioAtualCard = 0;
			$totalEpisodiosCard = 0;
			$serieIniciada = false;
			$serieConcluida = false;
			$episodiosCard = [];
			if ($ehSerieCard) {
				$rsEpiCard = query(
					"SELECT trepi_nb_id, trepi_tx_titulo, trepi_nb_ordem FROM treinamento_episodio WHERE trepi_nb_treinamento_id = ? AND trepi_tx_status = 'ativo' ORDER BY trepi_nb_ordem, trepi_nb_id",
					"i", [$treinamentoId]
				);
				$todosEpi = [];
				while ($rsEpiCard && ($rEpiCard = mysqli_fetch_assoc($rsEpiCard))) {
					$todosEpi[] = (int)$rEpiCard["trepi_nb_id"];
					$episodiosCard[] = [
						"id" => (int)$rEpiCard["trepi_nb_id"],
						"titulo" => strval($rEpiCard["trepi_tx_titulo"] ?? ""),
						"ordem" => (int)($rEpiCard["trepi_nb_ordem"] ?? 0)
					];
				}
				$totalEpisodiosCard = count($todosEpi);
				foreach ($todosEpi as $idxEpi => $idEpi) {
					$progEpiCard = mysqli_fetch_assoc(query(
						"SELECT trepr_nb_avaliacao_aprovada, trepr_nb_porcentagem_assistida, trepr_dt_data_inicio FROM treinamento_progresso WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ? AND trepr_nb_episodio_id = ?",
						"iii", [$treinamentoId, $usuarioId, $idEpi]
					));
					if (!empty($progEpiCard) && !empty($progEpiCard["trepr_dt_data_inicio"])) {
						$serieIniciada = true;
					}
					if (((int)($progEpiCard["trepr_nb_avaliacao_aprovada"] ?? 0)) === 1) {
						$episodioAtualCard = $idxEpi + 1;
					} else {
						break;
					}
				}
				$serieConcluida = ($episodioAtualCard >= $totalEpisodiosCard && $totalEpisodiosCard > 0);
			}

			// Definir status
			if ($ehSerieCard) {
				if ($serieConcluida) {
					$statusClass = "badge-success";
					$statusLabel = "Concluído";
					$btnClass = "btn-default";
					$btnLabel = "<i class='fa fa-refresh'></i> Rever";
					$btnAction = "treinamento_player.php?id={$treinamentoId}";
				} elseif ($serieIniciada) {
					$statusClass = "badge-warning";
					$statusLabel = "Em Andamento";
					$btnClass = "btn-warning";
					$btnLabel = "<i class='fa fa-play'></i> Continuar (Ep. " . ($episodioAtualCard + 1) . ")";
					$btnAction = "treinamento_player.php?id={$treinamentoId}";
				} else {
					$statusClass = "badge-info";
					$statusLabel = "Não Iniciado";
					$btnClass = "btn-primary";
					$btnLabel = "<i class='fa fa-play'></i> Assistir";
					$btnAction = "treinamento_player.php?id={$treinamentoId}";
				}
} else {
			if ($concluido) {
				$statusClass = "badge-success";
				$statusLabel = "Concluído";
				$btnClass = "btn-default";
				$btnLabel = "<i class='fa fa-refresh'></i> Assistir novamente";
				$btnAction = "treinamento_player.php?id={$treinamentoId}";
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
			}

			// Treinamento com data de liberação futura: card bloqueado
			if ($bloqueadoLiberacao) {
				$statusClass = "badge-default";
				$statusLabel = "Bloqueado";
				$btnClass = "btn-default";
				$btnLabel = "<i class='fa fa-lock'></i> Bloqueado";
				$btnAction = "";
			}

			// Situação do card para as abas
			if ($bloqueadoLiberacao) {
				$situacaoCard = "proximos";
			} elseif ($ehSerieCard) {
				$situacaoCard = $serieConcluida ? "concluidos" : ($serieIniciada ? "andamento" : "pendentes");
			} else {
				$situacaoCard = $concluido ? "concluidos" : ($progresso > 0 ? "andamento" : "pendentes");
			}

			ob_start();

			// Thumbnail
			$thumbSrc = !empty($thumbnail) ? ($_ENV["URL_BASE"] ?? "") . ($CONTEX["path"] ?? "") . "/treinamento/uploads/{$thumbnail}" : "";
			$thumbHtml = !empty($thumbSrc)
				? "<img src='{$thumbSrc}' class='thumbnail' alt='{$titulo}'>"
				: "<div class='thumbnail' style='display:flex;align-items:center;justify-content:center;background:#3c8dbc;color:#fff;font-size:48px;'><i class='fa fa-graduation-cap'></i></div>";

			// Badge tipo
			$tipoBadgeClass = ($tipo === "dss") ? "badge-primary" : "badge-info";
			$obrigatorioBadge = $obrigatorio ? "<span class='badge badge-danger'>Obrigatório</span> " : "";

			echo "
			<div class='col-md-4 col-sm-6 treinamento-item'
				data-titulo='" . strtolower($t["trei_tx_titulo"]) . "'
				data-status='" . ($bloqueadoLiberacao ? "bloqueado" : ($concluido ? "concluido" : ($progresso > 0 ? "em_andamento" : "nao_iniciado"))) . "'
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
						<p class='text-muted' style='font-size:13px;'>" . substr($descricao, 0, 120) . (strlen($descricao) > 120 ? "..." : "") . "</p>";

			if ($bloqueadoLiberacao) {
				echo "
						<div class='alert alert-warning' style='padding:8px 12px; font-size:12px; margin-bottom:10px;'>
							<i class='fa fa-lock'></i> <strong>Em breve!</strong> Este treinamento será liberado em
							<strong>{$dataLiberacaoLabel}</strong>. Você poderá assistir a partir dessa data.
						</div>";
			} else {
				echo "
						<div class='info-item'>
							<i class='fa fa-clock'></i> <strong>{$cargaHorariaLabel}</strong> min
						</div>
						" . ($ehSerieCard ? "<div class='info-item'><i class='fa fa-video-camera'></i> <strong>Série:</strong> " . ($serieConcluida ? "Concluída" : "Episódio " . ($episodioAtualCard + 1) . " de {$totalEpisodiosCard}") . "</div>" : "") . "";
			}

			if ($ehSerieCard && $serieConcluida && !empty($episodiosCard)) {
				echo "
						<div class='episodios-revisao'>
							<small class='text-muted'><i class='fa fa-film'></i> Rever episódios:</small>
							<div style='display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;'>";
							foreach ($episodiosCard as $idxEp => $epCard) {
								$numEp = $idxEp + 1;
								$rotuloEp = "#{$numEp} EP " . str_pad((string)($epCard["ordem"] ?: $numEp), 2, "0", STR_PAD_LEFT);
								echo "<a href='treinamento_player.php?id={$treinamentoId}&episodio={$epCard["id"]}' class='btn btn-xs btn-default' title='" . htmlspecialchars($epCard["titulo"]) . "'>{$rotuloEp}</a>";
							}
				echo "
							</div>
						</div>";
			}

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

			$cardHtml = ob_get_clean();
			$buckets[$situacaoCard] .= $cardHtml;
			$bucketsCount[$situacaoCard]++;
		}
	}

	// =====================================================
	// ABAS POR SITUAÇÃO (painéis colapsáveis com exibir/ocultar)
	// =====================================================
	$abasDef = [
		"pendentes" => ["Pendentes", "fa-clock-o", "panel-info", "Ainda não iniciados"],
		"andamento" => ["Em Andamento", "fa-play-circle-o", "panel-warning", "Iniciados, falta concluir"],
		"concluidos" => ["Concluídos", "fa-check-circle-o", "panel-success", "Vídeos e avaliações finalizados"],
		"proximos" => ["Próximos", "fa-calendar-o", "panel-default", "Serão liberados em breve"],
	];

	$primeiraAbaAberta = true;
	foreach ($abasDef as $chaveAba => $abaInfo) {
		if (($bucketsCount[$chaveAba] ?? 0) === 0) continue;
		$aberta = $primeiraAbaAberta ? " in" : "";
		$primeiraAbaAberta = false;
		echo "
		<div class='panel {$abaInfo[2]} treinamento-aba'>
			<div class='panel-heading' role='button' data-toggle='collapse' data-target='#abacollapse-{$chaveAba}' aria-expanded='" . ($aberta === " in" ? "true" : "false") . "' style='cursor:pointer;'>
				<h4 class='panel-title' style='display:flex; align-items:center; gap:10px;'>
					<i class='fa {$abaInfo[1]}'></i> {$abaInfo[0]}
					<span class='badge'>{$bucketsCount[$chaveAba]}</span>
					<small class='text-muted' style='margin-left:auto;'>{$abaInfo[3]}</small>
					<i class='fa fa-chevron-down aba-seta' style='font-size:12px;'></i>
				</h4>
			</div>
			<div id='abacollapse-{$chaveAba}' class='panel-collapse collapse{$aberta}'>
				<div class='panel-body'>
					<div class='row'>
						{$buckets[$chaveAba]}
					</div>
				</div>
			</div>
		</div>";
	}

	if (($bucketsCount["pendentes"] ?? 0) === 0 && ($bucketsCount["andamento"] ?? 0) === 0 && ($bucketsCount["concluidos"] ?? 0) === 0 && ($bucketsCount["proximos"] ?? 0) === 0) {
		echo "
			<div class='alert alert-info'>
				<i class='fa fa-info-circle'></i> Nenhum treinamento disponível no momento.
			</div>";
	}

	echo "
	</div>

	<script>
		// Exibir/ocultar: a seta acompanha o estado do painel
		$(document).on('hidden.bs.collapse', '.treinamento-aba .panel-collapse', function(){
			$(this).parent().find('.aba-seta').removeClass('fa-chevron-up').addClass('fa-chevron-down');
		});
		$(document).on('shown.bs.collapse', '.treinamento-aba .panel-collapse', function(){
			$(this).parent().find('.aba-seta').removeClass('fa-chevron-down').addClass('fa-chevron-up');
		});
		$('.treinamento-aba .panel-collapse.in').each(function(){
			$(this).parent().find('.aba-seta').removeClass('fa-chevron-down').addClass('fa-chevron-up');
		});

		function filtrarTreinamentos() {
			var busca = $('#buscaTitulo').val().toLowerCase();
			var tipo = $('#filtroTipo').val();

			$('.treinamento-item').each(function(){
				var el = $(this);
				var titulo = el.data('titulo');
				var tipoItem = el.data('tipo');

				var mostraTitulo = !busca || titulo.indexOf(busca) !== -1;
				var mostraTipo = !tipo || tipoItem === tipo;

				if(mostraTitulo && mostraTipo){
					el.show();
				} else {
					el.hide();
				}
			});

			// Esconde o painel quando não sobra nenhum card visível
			$('.treinamento-aba').each(function(){
				var visiveis = $(this).find('.treinamento-item:visible').length;
				$(this).toggle(visiveis > 0);
			});
		}

		function limparFiltros() {
			$('#buscaTitulo').val('');
			$('#filtroTipo').val('');
			filtrarTreinamentos();
		}
	</script>";

	rodape();
