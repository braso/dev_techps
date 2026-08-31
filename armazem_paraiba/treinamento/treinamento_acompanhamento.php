<?php
	include_once __DIR__."/../load_env.php";
	include_once __DIR__."/../conecta.php";
	include_once __DIR__."/../check_permission.php";
	verificaPermissao('/treinamento/cadastro_treinamento.php');

	// =====================================================
	// MÓDULO DE TREINAMENTO - Acompanhamento por Usuário
	// =====================================================

	$treinamentoId = (int)($_GET["id"] ?? $_POST["id"] ?? 0);
	if (!$treinamentoId) {
		header("Location: cadastro_treinamento.php");
		exit;
	}

	$treinamento = carregar("treinamento", $treinamentoId);
	if (empty($treinamento)) {
		header("Location: cadastro_treinamento.php");
		exit;
	}

	$titulo = htmlspecialchars($treinamento["trei_tx_titulo"]);
	$perfisPermitidos = !empty($treinamento["trei_tx_tipo_usuario_permitido"])
		? json_decode($treinamento["trei_tx_tipo_usuario_permitido"], true)
		: [];

	// Filtrar por status
	$filtroStatus = $_GET["filtro_status"] ?? "";
	$filtroStatus = in_array($filtroStatus, ["nao_iniciado", "em_andamento", "concluido", "bloqueado"]) ? $filtroStatus : "";

	// Buscar usuários com acesso: pertencem aos perfis permitidos e não estão bloqueados
	$usuarios = [];
	if (!empty($perfisPermitidos)) {
		$placeholders = implode(",", array_fill(0, count($perfisPermitidos), "?"));
		$rs = query(
			"SELECT DISTINCT u.user_nb_id, u.user_tx_nome, u.user_tx_login,
				p.perfil_nb_id, p.perfil_tx_nome,
				tp.trepr_nb_tempo_assistido,
				tp.trepr_nb_porcentagem_assistida,
				tp.trepr_nb_concluido,
				tp.trepr_dt_data_conclusao,
				tp.trepr_dt_data_inicio,
				tb.trebl_nb_id as bloqueado
			 FROM user u
			 JOIN usuario_perfil up ON up.user_nb_id = u.user_nb_id
			 JOIN perfil_acesso p ON p.perfil_nb_id = up.perfil_nb_id
			 LEFT JOIN treinamento_progresso tp
				ON tp.trepr_nb_usuario_id = u.user_nb_id
				AND tp.trepr_nb_treinamento_id = ?
			 LEFT JOIN treinamento_bloqueio tb
				ON tb.trebl_nb_usuario_id = u.user_nb_id
				AND tb.trebl_nb_treinamento_id = ?
			 WHERE up.ativo = 1 AND u.user_tx_status = 'ativo'
			 AND up.perfil_nb_id IN ({$placeholders})
			 ORDER BY p.perfil_tx_nome, u.user_tx_nome",
			"ii" . str_repeat("i", count($perfisPermitidos)),
			array_merge([$treinamentoId, $treinamentoId], $perfisPermitidos)
		);
		while ($rs && ($row = mysqli_fetch_assoc($rs))) {
			// Determinar status
			$concluido = (int)($row["trepr_nb_concluido"] ?? 0) == 1;
			$iniciou = !empty($row["trepr_dt_data_inicio"]);
			$bloqueado = !empty($row["bloqueado"]);

			if ($bloqueado) {
				$row["status_acompanhamento"] = "bloqueado";
			} elseif ($concluido) {
				$row["status_acompanhamento"] = "concluido";
			} elseif ($iniciou) {
				$row["status_acompanhamento"] = "em_andamento";
			} else {
				$row["status_acompanhamento"] = "nao_iniciado";
			}

			if (empty($filtroStatus) || $row["status_acompanhamento"] === $filtroStatus) {
				$usuarios[] = $row;
			}
		}
	}

	// Estatísticas gerais (sem filtro)
	$stats = ["nao_iniciado" => 0, "em_andamento" => 0, "concluido" => 0, "bloqueado" => 0, "total" => 0];
	if (!empty($perfisPermitidos)) {
		$placeholders = implode(",", array_fill(0, count($perfisPermitidos), "?"));
		$rsStats = query(
			"SELECT
				COUNT(DISTINCT u.user_nb_id) as total,
				SUM(CASE WHEN tb.trebl_nb_id IS NOT NULL THEN 1 ELSE 0 END) as bloqueados,
				SUM(CASE WHEN tb.trebl_nb_id IS NULL AND tp.trepr_nb_concluido = 1 THEN 1 ELSE 0 END) as concluidos,
				SUM(CASE WHEN tb.trebl_nb_id IS NULL AND tp.trepr_dt_data_inicio IS NOT NULL AND (tp.trepr_nb_concluido = 0 OR tp.trepr_nb_concluido IS NULL) THEN 1 ELSE 0 END) as em_andamento
			 FROM user u
			 JOIN usuario_perfil up ON up.user_nb_id = u.user_nb_id
			 LEFT JOIN treinamento_progresso tp
				ON tp.trepr_nb_usuario_id = u.user_nb_id
				AND tp.trepr_nb_treinamento_id = ?
			 LEFT JOIN treinamento_bloqueio tb
				ON tb.trebl_nb_usuario_id = u.user_nb_id
				AND tb.trebl_nb_treinamento_id = ?
			 WHERE up.ativo = 1 AND u.user_tx_status = 'ativo'
			 AND up.perfil_nb_id IN ({$placeholders})",
			"ii" . str_repeat("i", count($perfisPermitidos)),
			array_merge([$treinamentoId, $treinamentoId], $perfisPermitidos)
		);
		if ($rsStats && ($s = mysqli_fetch_assoc($rsStats))) {
			$stats["total"] = (int)($s["total"] ?? 0);
			$stats["bloqueado"] = (int)($s["bloqueados"] ?? 0);
			$stats["concluido"] = (int)($s["concluidos"] ?? 0);
			$stats["em_andamento"] = (int)($s["em_andamento"] ?? 0);
			$stats["nao_iniciado"] = max(0, $stats["total"] - $stats["bloqueado"] - $stats["concluido"] - $stats["em_andamento"]);
		}
	}

	cabecalho("Acompanhamento: " . $titulo);

	echo "
	<style>
		.info-card { background:#fff; border:1px solid #ddd; border-radius:8px; padding:15px; margin-bottom:15px; }
		.stat-card { text-align:center; padding:15px; border-radius:8px; color:#fff; margin-bottom:10px; }
		.stat-card .stat-numero { font-size:28px; font-weight:bold; }
		.stat-card .stat-label { font-size:12px; text-transform:uppercase; }
		.stat-total { background:#3c8dbc; }
		.stat-nao-iniciado { background:#95a5a6; }
		.stat-andamento { background:#f39c12; }
		.stat-concluido { background:#27ae60; }
		.stat-bloqueado { background:#d9534f; }
		.table-acomp { width:100%; background:#fff; }
		.table-acomp th { background:#3c8dbc; color:#fff; }
		.badge-status { padding:4px 10px; border-radius:10px; font-size:12px; color:#fff; }
		.filtro-btn { margin-right:5px; }
	</style>

	<div class='container-fluid'>
		<div class='row'>
			<div class='col-md-12'>
				<div class='info-card'>
					<h4 style='margin-top:0;'>
						<i class='fa fa-users'></i> Acompanhamento - {$titulo}
						<a href='cadastro_treinamento.php' class='btn btn-default btn-sm pull-right'><i class='fa fa-arrow-left'></i> Voltar</a>
					</h4>
					<p class='text-muted'>Visão geral dos usuários com acesso a este treinamento.</p>
				</div>
			</div>
		</div>

		<div class='row'>
			<div class='col-md-2 col-xs-6'><div class='stat-card stat-total'><div class='stat-numero'>{$stats["total"]}</div><div class='stat-label'>Total com Acesso</div></div></div>
			<div class='col-md-2 col-xs-6'><div class='stat-card stat-nao-iniciado'><div class='stat-numero'>{$stats["nao_iniciado"]}</div><div class='stat-label'>Não Iniciaram</div></div></div>
			<div class='col-md-2 col-xs-6'><div class='stat-card stat-andamento'><div class='stat-numero'>{$stats["em_andamento"]}</div><div class='stat-label'>Em Andamento</div></div></div>
			<div class='col-md-2 col-xs-6'><div class='stat-card stat-concluido'><div class='stat-numero'>{$stats["concluido"]}</div><div class='stat-label'>Concluídos</div></div></div>
			<div class='col-md-2 col-xs-6'><div class='stat-card stat-bloqueado'><div class='stat-numero'>{$stats["bloqueado"]}</div><div class='stat-label'>Bloqueados</div></div></div>
			<div class='col-md-2 col-xs-6'>
				<div class='stat-card stat-total'>
					<div class='stat-numero'>" . ($stats["total"] > 0 ? round(($stats["concluido"] / $stats["total"]) * 100) : 0) . "%</div>
					<div class='stat-label'>Conclusão Geral</div>
				</div>
			</div>
		</div>

		<div class='row'>
			<div class='col-md-12'>
				<div class='info-card'>
					<div class='row'>
						<div class='col-md-8'>
							<a href='treinamento_acompanhamento.php?id={$treinamentoId}' class='btn btn-sm " . (empty($filtroStatus) ? "btn-primary" : "btn-default") . " filtro-btn'>Todos</a>
							<a href='treinamento_acompanhamento.php?id={$treinamentoId}&filtro_status=nao_iniciado' class='btn btn-sm " . ($filtroStatus === "nao_iniciado" ? "btn-primary" : "btn-default") . " filtro-btn'>Não Iniciados</a>
							<a href='treinamento_acompanhamento.php?id={$treinamentoId}&filtro_status=em_andamento' class='btn btn-sm " . ($filtroStatus === "em_andamento" ? "btn-primary" : "btn-default") . " filtro-btn'>Em Andamento</a>
							<a href='treinamento_acompanhamento.php?id={$treinamentoId}&filtro_status=concluido' class='btn btn-sm " . ($filtroStatus === "concluido" ? "btn-primary" : "btn-default") . " filtro-btn'>Concluídos</a>
							<a href='treinamento_acompanhamento.php?id={$treinamentoId}&filtro_status=bloqueado' class='btn btn-sm " . ($filtroStatus === "bloqueado" ? "btn-primary" : "btn-default") . " filtro-btn'>Bloqueados</a>
						</div>
						<div class='col-md-4 text-right'>
							<strong>Mostrando:</strong> " . count($usuarios) . " usuário(s)
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class='row'>
			<div class='col-md-12'>
				<div class='info-card'>
					<table class='table table-striped table-bordered table-acomp'>
						<thead>
							<tr>
								<th>#</th>
								<th>Usuário</th>
								<th>Perfil</th>
								<th>Status</th>
								<th>Progresso</th>
								<th>Tempo Assistido</th>
								<th>Início</th>
								<th>Conclusão</th>
							</tr>
						</thead>
						<tbody>";

	if (empty($usuarios)) {
		echo "<tr><td colspan='8' class='text-center text-muted'>Nenhum usuário encontrado para este filtro.</td></tr>";
	} else {
		$idx = 1;
		foreach ($usuarios as $u) {
			$status = $u["status_acompanhamento"];
			$statusInfo = [
				"nao_iniciado" => ["label" => "Não Iniciado", "class" => "badge-status", "bg" => "#95a5a6"],
				"em_andamento" => ["label" => "Em Andamento", "class" => "badge-status", "bg" => "#f39c12"],
				"concluido" => ["label" => "Concluído", "class" => "badge-status", "bg" => "#27ae60"],
				"bloqueado" => ["label" => "Bloqueado", "class" => "badge-status", "bg" => "#d9534f"],
			];
			$info = $statusInfo[$status];
			$porcentagem = round((float)($u["trepr_nb_porcentagem_assistida"] ?? 0), 1);
			$tempo = (int)($u["trepr_nb_tempo_assistido"] ?? 0);
			$tempoLabel = sprintf("%02d:%02d", floor($tempo / 60), $tempo % 60);
			$dataInicio = !empty($u["trepr_dt_data_inicio"]) ? date("d/m/Y H:i", strtotime($u["trepr_dt_data_inicio"])) : "-";
			$dataConclusao = !empty($u["trepr_dt_data_conclusao"]) ? date("d/m/Y H:i", strtotime($u["trepr_dt_data_conclusao"])) : "-";

			echo "
							<tr>
								<td>{$idx}</td>
								<td><strong>" . htmlspecialchars($u["user_tx_nome"]) . "</strong></td>
								<td>" . htmlspecialchars($u["perfil_tx_nome"]) . "</td>
								<td><span class='badge-status' style='background:{$info["bg"]};'>{$info["label"]}</span></td>
								<td style='min-width:150px;'>
									<div class='progress progress-mini' style='margin-bottom:3px;'>
										<div class='progress-bar " . ($status === "concluido" ? "progress-bar-success" : ($status === "em_andamento" ? "progress-bar-warning" : "progress-bar-default")) . "' role='progressbar' style='width:{$porcentagem}%'></div>
									</div>
									<small class='text-muted'>{$porcentagem}%</small>
								</td>
								<td>{$tempoLabel}</td>
								<td>{$dataInicio}</td>
								<td>{$dataConclusao}</td>
							</tr>";
			$idx++;
		}
	}

	echo "
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>";

	rodape();