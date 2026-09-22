<?php
	include_once __DIR__."/../load_env.php";
	include_once __DIR__."/../conecta.php";
	include_once __DIR__."/../check_permission.php";
	include_once __DIR__."/certificado.php";
	verificaPermissao('/treinamento/cadastro_treinamento.php');

	// =====================================================
	// MÓDULO DE TREINAMENTO - Acompanhamento e Auditoria
	// KPIs, filtros avançados e detalhamento por usuário
	// =====================================================

	// Lista de treinamentos (para o seletor)
	$todosTreinamentos = [];
	$rsTreinos = query("SELECT trei_nb_id, trei_tx_titulo, trei_tx_status FROM treinamento ORDER BY trei_tx_status = 'ativo' DESC, trei_nb_id DESC");
	while ($rsTreinos && ($rT = mysqli_fetch_assoc($rsTreinos))) {
		$todosTreinamentos[] = $rT;
	}
	if (empty($todosTreinamentos)) {
		header("Location: cadastro_treinamento.php");
		exit;
	}

	// Treinamento selecionado (via GET id ou o primeiro ativo)
	$treinamentoId = (int)($_GET["id"] ?? 0);
	if ($treinamentoId <= 0) {
		foreach ($todosTreinamentos as $tSel) {
			if ($tSel["trei_tx_status"] === "ativo") { $treinamentoId = (int)$tSel["trei_nb_id"]; break; }
		}
		if ($treinamentoId <= 0) $treinamentoId = (int)$todosTreinamentos[0]["trei_nb_id"];
	}
	$treinamento = carregar("treinamento", $treinamentoId);
	if (empty($treinamento)) {
		header("Location: cadastro_treinamento.php");
		exit;
	}

	$titulo = htmlspecialchars($treinamento["trei_tx_titulo"]);
	$ehSerie = ($treinamento["trei_tx_serie"] ?? "nao") === "sim";
	$perfisPermitidos = !empty($treinamento["trei_tx_tipo_usuario_permitido"])
		? json_decode($treinamento["trei_tx_tipo_usuario_permitido"], true)
		: [];
	$perfisPermitidos = array_map('intval', (array)$perfisPermitidos);

	// Empresas habilitadas do treinamento
	$empresasHabAcomp = [];
	if (!empty($treinamento["trei_tx_empresas_habilitadas"])) {
		$empresasHabAcomp = json_decode($treinamento["trei_tx_empresas_habilitadas"], true);
		if (!is_array($empresasHabAcomp)) $empresasHabAcomp = [];
		$empresasHabAcomp = array_map('intval', $empresasHabAcomp);
	}

	// =====================================================
	// AJAX: GERAR CERTIFICADOS DOS USUÁRIOS CONCLUÍDOS
	// =====================================================
	if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao_certificados"] ?? "") === "gerar") {
		header('Content-Type: application/json');
		@set_time_limit(300);
		$tId = (int)($_POST["treinamento_id"] ?? 0);
		if ($tId <= 0) {
			echo json_encode(["success" => false, "message" => "Treinamento inválido."]);
			exit;
		}
		$tipoCertificado = treinamento_certificado_buscarTipo();
		if (empty($tipoCertificado)) {
			echo json_encode(["success" => false, "message" => "Tipo de documento 'Certificados' não encontrado ou inativo. Cadastre-o em Tipos de Documentos."]);
			exit;
		}

		$gerados = 0;
		$existentes = 0;
		$erros = [];
		$rsUsuarios = query(
			"SELECT DISTINCT trepr_nb_usuario_id FROM treinamento_progresso WHERE trepr_nb_treinamento_id = ?",
			"i",
			[$tId]
		);
		while ($rsUsuarios && ($rU = mysqli_fetch_assoc($rsUsuarios))) {
			$uId = (int)$rU["trepr_nb_usuario_id"];
			if ($uId <= 0 || !treinamento_certificado_estaConcluido($tId, $uId)) {
				continue;
			}
			$resultado = treinamento_certificado_gerar($tId, $uId);
			if (!empty($resultado["ok"])) {
				if (!empty($resultado["existente"])) {
					$existentes++;
				} else {
					$gerados++;
				}
			} else {
				$erros[] = "Usuário #{$uId}: " . strval($resultado["message"] ?? "erro");
			}
		}

		echo json_encode([
			"success" => true,
			"gerados" => $gerados,
			"existentes" => $existentes,
			"erros" => $erros
		]);
		exit;
	}

	// =====================================================
	// AJAX: LOG DE AUDITORIA DE UM USUÁRIO NO TREINAMENTO
	// =====================================================
	if (isset($_GET["acao"]) && $_GET["acao"] === "log_usuario") {
		header('Content-Type: application/json');
		$uId = (int)($_GET["usuario_id"] ?? 0);
		$tId = (int)($_GET["treinamento_id"] ?? 0);
		if ($uId <= 0 || $tId <= 0) {
			echo json_encode(["success" => false, "itens" => []]);
			exit;
		}
		$itens = [];
		$rsLog = query(
			"SELECT trelog_tx_evento, trelog_tx_detalhe, trelog_tx_ip, trelog_dt_data_cadastro
			 FROM treinamento_log
			 WHERE trelog_nb_usuario_id = ? AND trelog_nb_treinamento_id = ?
			 ORDER BY trelog_nb_id DESC LIMIT 200",
			"ii", [$uId, $tId]
		);
		while ($rsLog && ($rLog = mysqli_fetch_assoc($rsLog))) {
			$itens[] = [
				"evento" => strval($rLog["trelog_tx_evento"] ?? ""),
				"detalhe" => strval($rLog["trelog_tx_detalhe"] ?? ""),
				"ip" => strval($rLog["trelog_tx_ip"] ?? ""),
				"data" => strval($rLog["trelog_dt_data_cadastro"] ?? ""),
			];
		}
		echo json_encode(["success" => true, "itens" => $itens]);
		exit;
	}

	// =====================================================
	// FILTROS
	// =====================================================
	$filtroStatus = in_array($_GET["filtro_status"] ?? "", ["nao_iniciado", "em_andamento", "concluido", "bloqueado"], true) ? $_GET["filtro_status"] : "";
	$filtroEmpresa = (int)($_GET["filtro_empresa"] ?? 0);
	$filtroDataInicio = $_GET["filtro_data_inicio"] ?? "";
	$filtroDataFim = $_GET["filtro_data_fim"] ?? "";

	// Condições SQL
	$condEmpresa = "";
	$tiposCond = "";
	$valsCond = [];

	if (!empty($empresasHabAcomp)) {
		$condEmpresa = " AND u.user_nb_empresa IN (" . implode(",", $empresasHabAcomp) . ")";
	} elseif ($filtroEmpresa > 0) {
		$condEmpresa = " AND u.user_nb_empresa = {$filtroEmpresa}";
	}
	if ($filtroEmpresa > 0 && empty($empresasHabAcomp)) {
		$condEmpresa = " AND u.user_nb_empresa = {$filtroEmpresa}";
	}
	if ($filtroDataInicio !== "") {
		$condEmpresa .= " AND tp.trepr_dt_data_inicio >= ?";
		$tiposCond .= "s";
		$valsCond[] = $filtroDataInicio . " 00:00:00";
	}
	if ($filtroDataFim !== "") {
		$condEmpresa .= " AND tp.trepr_dt_data_inicio <= ?";
		$tiposCond .= "s";
		$valsCond[] = $filtroDataFim . " 23:59:59";
	}

	// =====================================================
	// CONSULTA: USUÁRIOS COM ACESSO + PROGRESSO GERAL
	// =====================================================
	$usuarios = [];
	if (!empty($perfisPermitidos)) {
		$placeholders = implode(",", array_fill(0, count($perfisPermitidos), "?"));
		$rs = query(
			"SELECT u.user_nb_id, u.user_tx_nome, u.user_tx_login, u.user_nb_empresa, e.empr_tx_nome,
				p.perfil_tx_nome,
				tp.trepr_nb_tempo_assistido, tp.trepr_nb_porcentagem_assistida,
				tp.trepr_nb_concluido, tp.trepr_nb_avaliacao_nota, tp.trepr_nb_avaliacao_tentativas,
				tp.trepr_nb_avaliacao_aprovada, tp.trepr_dt_data_inicio, tp.trepr_dt_data_conclusao,
				tb.trebl_nb_id as bloqueado
			 FROM user u
			 JOIN usuario_perfil up ON up.user_nb_id = u.user_nb_id
			 JOIN perfil_acesso p ON p.perfil_nb_id = up.perfil_nb_id
			 LEFT JOIN empresa e ON e.empr_nb_id = u.user_nb_empresa
			 LEFT JOIN treinamento_progresso tp
				ON tp.trepr_nb_usuario_id = u.user_nb_id
				AND tp.trepr_nb_treinamento_id = ?
				AND tp.trepr_nb_episodio_id IS NULL
			 LEFT JOIN treinamento_bloqueio tb
				ON tb.trebl_nb_usuario_id = u.user_nb_id
				AND tb.trebl_nb_treinamento_id = ?
			 WHERE up.ativo = 1 AND u.user_tx_status = 'ativo'
			 AND up.perfil_nb_id IN ({$placeholders})
			 {$condEmpresa}
			 ORDER BY p.perfil_tx_nome, u.user_tx_nome",
			"ii" . str_repeat("i", count($perfisPermitidos)) . $tiposCond,
			array_merge([$treinamentoId, $treinamentoId], $perfisPermitidos, $valsCond)
		);
		// Mapa de tempo/percentual por usuário (somando todos os registros, incluindo episódios)
		$mapaTempo = [];
		$rsTemp = query(
			"SELECT trepr_nb_usuario_id, SUM(trepr_nb_tempo_assistido) AS tempo_total, MAX(trepr_nb_porcentagem_assistida) AS max_percent
			 FROM treinamento_progresso
			 WHERE trepr_nb_treinamento_id = ?
			 GROUP BY trepr_nb_usuario_id",
			"i", [$treinamentoId]
		);
		while ($rsTemp && ($rTemp = mysqli_fetch_assoc($rsTemp))) {
			$mapaTempo[(int)$rTemp["trepr_nb_usuario_id"]] = [
				"tempo" => (int)($rTemp["tempo_total"] ?? 0),
				"percent" => (float)($rTemp["max_percent"] ?? 0)
			];
		}
		// Para séries: episódios aprovados e iniciados por usuário
		$mapaEpiAprov = [];
		$mapaEpiInic = [];
		if ($ehSerie) {
			$rsAprov = query(
				"SELECT trepr_nb_usuario_id, COUNT(*) AS c FROM treinamento_progresso
				 WHERE trepr_nb_treinamento_id = ? AND trepr_nb_episodio_id IS NOT NULL AND trepr_nb_avaliacao_aprovada = 1
				 GROUP BY trepr_nb_usuario_id",
				"i", [$treinamentoId]
			);
			while ($rsAprov && ($rA = mysqli_fetch_assoc($rsAprov))) {
				$mapaEpiAprov[(int)$rA["trepr_nb_usuario_id"]] = (int)($rA["c"] ?? 0);
			}
			$rsInic = query(
				"SELECT trepr_nb_usuario_id, COUNT(*) AS c FROM treinamento_progresso
				 WHERE trepr_nb_treinamento_id = ? AND trepr_nb_episodio_id IS NOT NULL AND trepr_dt_data_inicio IS NOT NULL
				 GROUP BY trepr_nb_usuario_id",
				"i", [$treinamentoId]
			);
			while ($rsInic && ($rI = mysqli_fetch_assoc($rsInic))) {
				$mapaEpiInic[(int)$rI["trepr_nb_usuario_id"]] = (int)($rI["c"] ?? 0);
			}
		}
		$totalEpiSerie = 0;
		if ($ehSerie) {
			$rsEpiCnt = query("SELECT COUNT(*) AS c FROM treinamento_episodio WHERE trepi_nb_treinamento_id = ? AND trepi_tx_status = 'ativo'", "i", [$treinamentoId]);
			$totalEpiSerie = (int)(($rsEpiCnt && ($rEpiCnt = mysqli_fetch_assoc($rsEpiCnt))) ? $rEpiCnt["c"] : 0);
		}

		while ($rs && ($row = mysqli_fetch_assoc($rs))) {
			$uid = (int)$row["user_nb_id"];
			$bloqueado = !empty($row["bloqueado"]);
			$tempoTotal = $mapaTempo[$uid]["tempo"] ?? (int)($row["trepr_nb_tempo_assistido"] ?? 0);
			$percentMax = $mapaTempo[$uid]["percent"] ?? (float)($row["trepr_nb_porcentagem_assistida"] ?? 0);

			// Determinar status
			if ($bloqueado) {
				$status = "bloqueado";
			} elseif ($ehSerie) {
				$aprovEpi = $mapaEpiAprov[$uid] ?? 0;
				$inicEpi = $mapaEpiInic[$uid] ?? 0;
				if ($totalEpiSerie > 0 && $aprovEpi >= $totalEpiSerie) {
					$status = "concluido";
				} elseif ($inicEpi > 0 || !empty($row["trepr_dt_data_inicio"])) {
					$status = "em_andamento";
				} else {
					$status = "nao_iniciado";
				}
			} else {
				$concluidoGeral = (int)($row["trepr_nb_concluido"] ?? 0) == 1;
				$iniciou = !empty($row["trepr_dt_data_inicio"]);
				if ($concluidoGeral) {
					$status = "concluido";
				} elseif ($iniciou) {
					$status = "em_andamento";
				} else {
					$status = "nao_iniciado";
				}
			}

			$row["status_acompanhamento"] = $status;
			$row["tempo_total"] = $tempoTotal;
			$row["percent_max"] = $percentMax;
			$row["epi_aprovados"] = $mapaEpiAprov[$uid] ?? 0;
			$row["epi_iniciados"] = $mapaEpiInic[$uid] ?? 0;

			if (empty($filtroStatus) || $status === $filtroStatus) {
				$usuarios[] = $row;
			}
		}
	}

	// =====================================================
	// KPIs (sobre o resultado filtrado)
	// =====================================================
	$kpi = [
		"total" => 0, "nao_iniciado" => 0, "em_andamento" => 0, "concluido" => 0, "bloqueado" => 0,
		"tempo_total" => 0, "soma_notas" => 0, "notas_count" => 0, "tentativas" => 0, "reprovacoes" => 0,
		"avaliacoes_feitas" => 0
	];
	foreach ($usuarios as $u) {
		$kpi["total"]++;
		$kpi[$u["status_acompanhamento"]]++;
		$kpi["tempo_total"] += (int)$u["tempo_total"];
		$nota = (float)($u["trepr_nb_avaliacao_nota"] ?? 0);
		if ($nota > 0) { $kpi["soma_notas"] += $nota; $kpi["notas_count"]++; }
		$kpi["tentativas"] += (int)($u["trepr_nb_avaliacao_tentativas"] ?? 0);
		if ((int)($u["trepr_nb_avaliacao_tentativas"] ?? 0) > 0) $kpi["avaliacoes_feitas"]++;
		if ((int)($u["trepr_nb_avaliacao_aprovada"] ?? 0) === 0 && (int)($u["trepr_nb_avaliacao_tentativas"] ?? 0) > 0) {
			$kpi["reprovacoes"]++;
		}
	}
	$kpi["taxa_conclusao"] = $kpi["total"] > 0 ? round(($kpi["concluido"] / $kpi["total"]) * 100) : 0;
	$kpi["media_nota"] = $kpi["notas_count"] > 0 ? round($kpi["soma_notas"] / $kpi["notas_count"], 1) : 0;
	$kpi["tempo_medio"] = $kpi["total"] > 0 ? round($kpi["tempo_total"] / $kpi["total"]) : 0;

	function fmtHMS($seg) {
		$seg = max(0, (int)$seg);
		return sprintf("%02d:%02d:%02d", floor($seg / 3600), floor(($seg % 3600) / 60), $seg % 60);
	}

	$statusInfo = [
		"nao_iniciado" => ["label" => "Não Iniciado", "bg" => "#95a5a6", "icon" => "fa-clock-o"],
		"em_andamento" => ["label" => "Em Andamento", "bg" => "#f39c12", "icon" => "fa-play-circle"],
		"concluido" => ["label" => "Concluído", "bg" => "#27ae60", "icon" => "fa-check-circle"],
		"bloqueado" => ["label" => "Bloqueado", "bg" => "#d9534f", "icon" => "fa-lock"],
	];

	cabecalho("Acompanhamento e Auditoria: " . $titulo);
	echo "
	<style>
		.acomp-header { background: linear-gradient(135deg, #1e3a5f, #3c8dbc); color:#fff; border-radius:14px; padding:22px 24px; margin-bottom:20px; box-shadow:0 6px 18px rgba(30,58,95,0.25); }
		.acomp-header h3 { margin:0 0 6px; font-weight:700; overflow-wrap:anywhere; word-break:break-word; }
		.acomp-header .acomp-sub { opacity:0.85; font-size:13px; overflow-wrap:anywhere; word-break:break-word; }
		.kpi-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:20px; }
		.kpi-card { border-radius:12px; padding:16px; color:#fff; box-shadow:0 4px 12px rgba(0,0,0,0.08); position:relative; overflow:hidden; }
		.kpi-card .kpi-icon { font-size:22px; opacity:0.5; position:absolute; right:12px; top:12px; }
		.kpi-card .kpi-num { font-size:26px; font-weight:700; line-height:1.1; }
		.kpi-card .kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9; margin-top:2px; }
		.kpi-total { background:linear-gradient(135deg,#2c3e50,#3c8dbc); }
		.kpi-pendente { background:linear-gradient(135deg,#7f8c8d,#95a5a6); }
		.kpi-andamento { background:linear-gradient(135deg,#e67e22,#f39c12); }
		.kpi-concluido { background:linear-gradient(135deg,#1e8449,#27ae60); }
		.kpi-bloqueado { background:linear-gradient(135deg,#c0392b,#e74c3c); }
		.kpi-taxa { background:linear-gradient(135deg,#2980b9,#3498db); }
		.kpi-tempo { background:linear-gradient(135deg,#34495e,#5d6d7e); }
		.kpi-nota { background:linear-gradient(135deg,#8e44ad,#9b59b6); }
		.kpi-tent { background:linear-gradient(135deg,#d35400,#e67e22); }
		.acomp-filtros { background:#fff; border-radius:12px; padding:16px; margin-bottom:18px; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
		.acomp-filtros label { font-size:11px; text-transform:uppercase; font-weight:600; color:#555; }
		.acomp-table { width:100%; background:#fff; border-collapse:separate; border-spacing:0; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.06); }
		.acomp-table thead th { background:#f4f6f9; color:#2c3e50; font-size:12px; text-transform:uppercase; letter-spacing:0.4px; padding:12px 14px; border-bottom:2px solid #e4e9f0; white-space:nowrap; }
		.acomp-table tbody td { padding:12px 14px; border-bottom:1px solid #f0f2f5; vertical-align:middle; font-size:13px; overflow-wrap:anywhere; word-break:break-word; }
		.acomp-table tbody tr:hover { background:#f7fafc; }
		.acomp-table tbody tr:last-child td { border-bottom:none; }
		.badge-status { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; color:#fff; }
		.avatar-ini { width:34px; height:34px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; color:#fff; font-size:14px; background:#3c8dbc; }
		.acomp-empty { text-align:center; padding:40px; color:#888; }
		.btn-auditar { color:#3c8dbc; cursor:pointer; }
		.btn-auditar:hover { text-decoration:underline; }
		.progress { height:8px; margin:0; }
		.log-item { border-left:3px solid #3c8dbc; padding:8px 12px; margin-bottom:8px; background:#f8fafc; border-radius:0 6px 6px 0; overflow-wrap:anywhere; word-break:break-word; }
		.log-item .log-data { font-size:11px; color:#888; }
		.log-item .log-evento { font-weight:600; font-size:12px; text-transform:capitalize; color:#2c3e50; }
		.log-item .log-ip { font-size:11px; color:#aaa; }
		.select-treino { max-width:400px; }
		@media (max-width: 767px) {
			.acomp-header { padding:16px; }
			.acomp-header .text-right { text-align:left; margin-top:10px; }
			.acomp-header .text-right .btn { margin-bottom:6px; }
			.acomp-filtros { padding:12px; }
			.acomp-filtros .text-right { text-align:left !important; padding-top:0 !important; }
			.acomp-filtros .btn { margin-bottom:6px; }
			.select-treino { max-width:100%; }
			.kpi-grid { grid-template-columns:repeat(auto-fit, minmax(120px, 1fr)); }
			.kpi-card .kpi-num { font-size:22px; }
			.acomp-table thead th, .acomp-table tbody td { padding:10px; }
			#tabelaAcomp { min-width:820px; }
			.modal-dialog { margin:10px; }
		}
	</style>

	<div class='container-fluid'>

		<!-- HEADER -->
		<div class='acomp-header'>
			<div class='row'>
				<div class='col-md-8'>
					<h3><i class='fa fa-clipboard-list'></i> Acompanhamento e Auditoria</h3>
					<div class='acomp-sub'><i class='fa fa-video-camera'></i> <strong>{$titulo}</strong> &middot; " . ($ehSerie ? "Série de vídeos" : "Treinamento único") . " &middot; Carga: <strong>" . fmtHMS((int)$treinamento["trei_nb_carga_horaria"]) . "</strong></div>
				</div>
				<div class='col-md-4 text-right'>
					<a href='cadastro_treinamento.php' class='btn btn-default btn-sm' style='background:rgba(255,255,255,0.15);border:none;color:#fff;'><i class='fa fa-arrow-left'></i> Voltar</a>
					<button class='btn btn-sm' style='background:rgba(255,255,255,0.15);border:none;color:#fff;' onclick='gerarCertificadosAcomp()'><i class='fa fa-certificate'></i> Gerar Certificados</button>
					<button class='btn btn-sm' style='background:rgba(255,255,255,0.15);border:none;color:#fff;' onclick='exportarCSVAcomp()'><i class='fa fa-download'></i> Exportar CSV</button>
				</div>
			</div>
		</div>

		<!-- SELEÇÃO DE TREINAMENTO -->
		<div class='acomp-filtros'>
			<form method='get' action='treinamento_acompanhamento.php' class='row'>
				<div class='col-md-3'>
					<label>Treinamento</label>
					<select name='id' class='form-control input-sm select-treino' onchange='this.form.submit()'>
						";
						foreach ($todosTreinamentos as $tOpt) {
							$selOpt = ((int)$tOpt["trei_nb_id"] === $treinamentoId) ? " selected" : "";
							$statusOpt = ($tOpt["trei_tx_status"] === "ativo") ? "" : " (inativo)";
							echo "<option value='{$tOpt["trei_nb_id"]}'{$selOpt}>" . htmlspecialchars($tOpt["trei_tx_titulo"]) . $statusOpt . "</option>";
						}
						echo "
					</select>
				</div>
				<div class='col-md-2'>
					<label>Status</label>
					<select name='filtro_status' class='form-control input-sm' onchange='this.form.submit()'>
						<option value=''>Todos</option>
						<option value='nao_iniciado' " . ($filtroStatus === "nao_iniciado" ? "selected" : "") . ">Não Iniciado</option>
						<option value='em_andamento' " . ($filtroStatus === "em_andamento" ? "selected" : "") . ">Em Andamento</option>
						<option value='concluido' " . ($filtroStatus === "concluido" ? "selected" : "") . ">Concluído</option>
						<option value='bloqueado' " . ($filtroStatus === "bloqueado" ? "selected" : "") . ">Bloqueado</option>
					</select>
				</div>
				<div class='col-md-2'>
					<label>Data início (de)</label>
					<input type='date' name='filtro_data_inicio' class='form-control input-sm' value='{$filtroDataInicio}'>
				</div>
				<div class='col-md-2'>
					<label>Data início (até)</label>
					<input type='date' name='filtro_data_fim' class='form-control input-sm' value='{$filtroDataFim}'>
				</div>
				<div class='col-md-3'>
					<label>&nbsp;</label><br>
					<button type='submit' class='btn btn-sm btn-primary'><i class='fa fa-search'></i> Aplicar filtros</button>
					<a href='treinamento_acompanhamento.php?id={$treinamentoId}' class='btn btn-sm btn-default'><i class='fa fa-times'></i> Limpar</a>
				</div>
			</form>
		</div>

		<!-- KPIs -->
		<div class='kpi-grid'>
			<div class='kpi-card kpi-total'><i class='fa fa-users kpi-icon'></i><div class='kpi-num'>{$kpi["total"]}</div><div class='kpi-label'>Usuários com acesso</div></div>
			<div class='kpi-card kpi-pendente'><i class='fa fa-clock-o kpi-icon'></i><div class='kpi-num'>{$kpi["nao_iniciado"]}</div><div class='kpi-label'>Não iniciados</div></div>
			<div class='kpi-card kpi-andamento'><i class='fa fa-play-circle kpi-icon'></i><div class='kpi-num'>{$kpi["em_andamento"]}</div><div class='kpi-label'>Em andamento</div></div>
			<div class='kpi-card kpi-concluido'><i class='fa fa-check-circle kpi-icon'></i><div class='kpi-num'>{$kpi["concluido"]}</div><div class='kpi-label'>Concluídos</div></div>
			<div class='kpi-card kpi-bloqueado'><i class='fa fa-lock kpi-icon'></i><div class='kpi-num'>{$kpi["bloqueado"]}</div><div class='kpi-label'>Bloqueados</div></div>
			<div class='kpi-card kpi-taxa'><i class='fa fa-percent kpi-icon'></i><div class='kpi-num'>{$kpi["taxa_conclusao"]}%</div><div class='kpi-label'>Taxa de conclusão</div></div>
			<div class='kpi-card kpi-tempo'><i class='fa fa-hourglass-half kpi-icon'></i><div class='kpi-num' style='font-size:20px;'>" . fmtHMS($kpi["tempo_total"]) . "</div><div class='kpi-label'>Tempo total assistido</div></div>
			<div class='kpi-card kpi-tempo'><i class='fa fa-clock-o kpi-icon'></i><div class='kpi-num' style='font-size:20px;'>" . fmtHMS($kpi["tempo_medio"]) . "</div><div class='kpi-label'>Tempo médio / usuário</div></div>
			<div class='kpi-card kpi-nota'><i class='fa fa-graduation-cap kpi-icon'></i><div class='kpi-num'>{$kpi["media_nota"]}%</div><div class='kpi-label'>Média de notas</div></div>
			<div class='kpi-card kpi-tent'><i class='fa fa-repeat kpi-icon'></i><div class='kpi-num'>{$kpi["tentativas"]}</div><div class='kpi-label'>Tentativas de avaliação</div></div>
		</div>

		<!-- TABELA -->
		<div class='acomp-filtros'>
			<div class='row'>
				<div class='col-md-6'>
					<label>Buscar por nome ou login</label>
					<input type='text' id='buscaUsuario' class='form-control input-sm' placeholder='Digite para filtrar a tabela...' onkeyup='filtrarTabelaAcomp()'>
				</div>
				<div class='col-md-6 text-right' style='padding-top:24px;'>
					<span class='text-muted'><i class='fa fa-info-circle'></i> Clique em <strong>Auditar</strong> para ver o histórico completo do usuário (IP, eventos, datas).</span>
				</div>
			</div>
		</div>

		<div class='table-responsive' style='border-radius:12px;'>
			<table class='acomp-table' id='tabelaAcomp'>
				<thead>
					<tr>
						<th>Usuário</th>
						<th>Empresa</th>
						<th>Perfil</th>
						<th>Status</th>
						<th>Progresso</th>
						<th>Tempo assistido</th>
						<th>Nota / Aprovação</th>
						<th>Tentativas</th>
						<th>Início</th>
						<th>Conclusão</th>
						<th>Auditoria</th>
					</tr>
				</thead>
				<tbody>";

	if (empty($usuarios)) {
		echo "<tr><td colspan='11' class='acomp-empty'>Nenhum usuário encontrado para os filtros aplicados.</td></tr>";
	} else {
		foreach ($usuarios as $u) {
			$st = $u["status_acompanhamento"];
			$info = $statusInfo[$st];
			$nome = htmlspecialchars($u["user_tx_nome"]);
			$inicial = strtoupper(mb_substr($u["user_tx_nome"] ?? "?", 0, 1));
			$porcentagem = round((float)($u["percent_max"] ?? 0), 1);
			$tempo = (int)$u["tempo_total"];
			$nota = (float)($u["trepr_nb_avaliacao_nota"] ?? 0);
			$aprovado = (int)($u["trepr_nb_avaliacao_aprovada"] ?? 0) === 1;
			$tentativas = (int)($u["trepr_nb_avaliacao_tentativas"] ?? 0);
			$dataInicio = !empty($u["trepr_dt_data_inicio"]) ? date("d/m/Y H:i", strtotime($u["trepr_dt_data_inicio"])) : "-";
			$dataConclusao = !empty($u["trepr_dt_data_conclusao"]) ? date("d/m/Y H:i", strtotime($u["trepr_dt_data_conclusao"])) : "-";
			$extraSerie = ($ehSerie && $st !== "nao_iniciado") ? " <small class='text-muted'>(ep. " . $u["epi_aprovados"] . "/" . ($totalEpiSerie ?: 0) . " aprovados)</small>" : "";
			$corBarra = $st === "concluido" ? "#27ae60" : ($st === "em_andamento" ? "#f39c12" : "#bdc3c7");
			$notaLabel = $nota > 0
				? "<span style='color:" . ($aprovado ? "#27ae60" : "#d9534f") . ";font-weight:700;'>" . $nota . "%</span> " . ($aprovado ? "<i class='fa fa-check-circle text-success'></i>" : "<i class='fa fa-times-circle' style='color:#d9534f;'></i>")
				: "-";

			echo "
				<tr data-nome=\"" . strtolower($u["user_tx_nome"]) . "\" data-login=\"" . strtolower($u["user_tx_login"]) . "\">
					<td>
						<div style='display:flex;align-items:center;gap:10px;'>
							<span class='avatar-ini'>{$inicial}</span>
							<div>
								<strong>{$nome}</strong><br>
								<small class='text-muted'><i class='fa fa-user-o'></i> " . htmlspecialchars($u["user_tx_login"]) . "</small>
							</div>
						</div>
					</td>
					<td>" . htmlspecialchars($u["empr_tx_nome"] ?? "-") . "</td>
					<td>" . htmlspecialchars($u["perfil_tx_nome"] ?? "-") . "</td>
					<td><span class='badge-status' style='background:{$info["bg"]};'><i class='fa {$info["icon"]}'></i> {$info["label"]}</span>{$extraSerie}</td>
					<td style='min-width:150px;'>
						<div class='progress'>
							<div class='progress-bar' role='progressbar' style='width:{$porcentagem}%;background:{$corBarra};'></div>
						</div>
						<small class='text-muted'>{$porcentagem}%</small>
					</td>
					<td style='white-space:nowrap;'><i class='fa fa-hourglass-half text-muted'></i> " . fmtHMS($tempo) . "</td>
					<td>{$notaLabel}</td>
					<td>" . ($tentativas > 0 ? $tentativas : "-") . "</td>
					<td style='white-space:nowrap;'>{$dataInicio}</td>
					<td style='white-space:nowrap;'>{$dataConclusao}</td>
					<td>
						<a href='javascript:void(0);' class='btn-auditar' onclick=\"abrirAuditoria({$u["user_nb_id"]}, '" . htmlspecialchars(addslashes($u["user_tx_nome"])) . "')\"><i class='fa fa-search'></i> Auditar</a>
					</td>
				</tr>";
		}
	}

	echo "
				</tbody>
			</table>
		</div>
	</div>

	<!-- MODAL DE AUDITORIA -->
	<div class='modal fade' id='modalAuditoria' tabindex='-1' role='dialog'>
		<div class='modal-dialog modal-lg' role='document'>
			<div class='modal-content'>
				<div class='modal-header'>
					<button type='button' class='close' data-dismiss='modal'>&times;</button>
					<h4 class='modal-title'><i class='fa fa-search'></i> Auditoria do usuário — <span id='audNome'>...</span></h4>
				</div>
				<div class='modal-body' id='audCorpo'>
					<p class='text-muted'><i class='fa fa-spinner fa-spin'></i> Carregando histórico...</p>
				</div>
				<div class='modal-footer'>
					<button type='button' class='btn btn-default' data-dismiss='modal'>Fechar</button>
				</div>
			</div>
		</div>
	</div>

	<script>
		var acompTreinamentoId = {$treinamentoId};
		var acompUsuarioIdAtual = 0;

		function filtrarTabelaAcomp() {
			var busca = $('#buscaUsuario').val().toLowerCase();
			$('#tabelaAcomp tbody tr').each(function() {
				var el = $(this);
				var nome = el.data('nome') || '';
				var login = el.data('login') || '';
				var mostra = !busca || nome.indexOf(busca) !== -1 || login.indexOf(busca) !== -1;
				el.toggle(mostra);
			});
		}

		function abrirAuditoria(usuarioId, nomeUsuario) {
			acompUsuarioIdAtual = usuarioId;
			$('#audNome').text(nomeUsuario || ('Usuário #' + usuarioId));
			$('#audCorpo').html('<p class=\"text-muted\"><i class=\"fa fa-spinner fa-spin\"></i> Carregando histórico...</p>');
			$('#modalAuditoria').modal('show');
			$.get(window.location.pathname, {
				acao: 'log_usuario',
				usuario_id: usuarioId,
				treinamento_id: acompTreinamentoId
			}, function(data) {
				if(!data.success) { $('#audCorpo').html('<p class=\"text-muted\">Sem dados.</p>'); return; }
				if(!data.itens || data.itens.length === 0) {
					$('#audCorpo').html('<p class=\"text-muted\"><i class=\"fa fa-info-circle\"></i> Nenhum evento registrado para este usuário neste treinamento.</p>');
					return;
				}
				var html = '';
				data.itens.forEach(function(it) {
					var d = '';
					if(it.data) {
						var dt = new Date(it.data.replace(' ', 'T'));
						if(!isNaN(dt)) {
							d = String(dt.getDate()).padStart(2,'0') + '/' + String(dt.getMonth()+1).padStart(2,'0') + '/' + dt.getFullYear() + ' ' + String(dt.getHours()).padStart(2,'0') + ':' + String(dt.getMinutes()).padStart(2,'0');
						}
					}
					html += '<div class=\"log-item\">' +
						'<div class=\"log-evento\"><i class=\"fa fa-circle-o\"></i> ' + (it.evento || '') + '</div>' +
						'<div>' + $('<span>').text(it.detalhe || '').html() + '</div>' +
						'<div class=\"log-data\">' + d + ' &middot; IP: <span class=\"log-ip\">' + (it.ip || '-') + '</span></div>' +
					'</div>';
				});
				$('#audCorpo').html(html);
			}, 'json').fail(function() {
				$('#audCorpo').html('<p class=\"text-danger\">Erro ao carregar o histórico.</p>');
			});
		}

		function gerarCertificadosAcomp() {
			Swal.fire({
				title: 'Gerar certificados?',
				html: 'Serão gerados os certificados de todos os usuários que concluíram este treinamento.<br><br><small>Quem já possui certificado não será afetado.</small>',
				icon: 'question',
				showCancelButton: true,
				confirmButtonColor: '#3085d6',
				cancelButtonColor: '#d33',
				confirmButtonText: 'Sim, gerar!',
				cancelButtonText: 'Cancelar'
			}).then((result) => {
				if (!result.isConfirmed) return;
				Swal.fire({ title: 'Gerando certificados...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
				$.post(window.location.pathname, { acao_certificados: 'gerar', treinamento_id: acompTreinamentoId }, function(data) {
					if (!data.success) {
						Swal.fire({ icon: 'error', title: 'Erro', text: data.message || 'Não foi possível gerar os certificados.' });
						return;
					}
					var texto = data.gerados + ' certificado(s) gerado(s). ' + data.existentes + ' já existente(s).';
					if (data.erros && data.erros.length > 0) {
						texto += '<br><br><small>' + data.erros.slice(0, 5).join('<br>') + (data.erros.length > 5 ? '<br>...' : '') + '</small>';
					}
					Swal.fire({ icon: 'success', title: 'Concluído', html: texto }).then(() => window.location.reload());
				}, 'json').fail(function() {
					Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao conectar com o servidor.' });
				});
			});
		}

		function exportarCSVAcomp() {			var linhas = [];
			$('#tabelaAcomp thead th').each(function(i, th) { linhas.push($(th).text().trim()); });
			var csv = '\\uFEFFsep=;\\r\\n' + linhas.join(';') + '\\r\\n';
			$('#tabelaAcomp tbody tr:visible').each(function() {
				var cols = [];
				$(this).find('td').each(function() {
					var txt = $(this).text().trim().replace(/;+/g, ',');
					cols.push('\"' + txt.replace(/\"/g, '\"\"') + '\"');
				});
				csv += cols.join(';') + '\\r\\n';
			});
			var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
			var a = document.createElement('a');
			var data = new Date().toLocaleDateString('pt-BR').replace(/\\//g, '-');
			a.href = URL.createObjectURL(blob);
			a.download = 'acompanhamento_treinamento_{$treinamentoId}_{' + data + '}.csv';
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
		}
	</script>";

	rodape();