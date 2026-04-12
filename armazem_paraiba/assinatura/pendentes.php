<?php
include_once "../conecta.php";

$entiSess = intval($_SESSION["user_nb_entidade"] ?? 0);

if($entiSess <= 0){
	cabecalho("Assinaturas Pendentes");
	echo "<div class='col-md-12'><div class='portlet light'><div class='portlet-body'>";
	echo "<div class='alert alert-warning'>Usuário sem vínculo de funcionário para assinatura.</div>";
	echo "</div></div></div>";
	rodape();
	exit;
}

$tab = strtolower(trim(strval($_GET["tab"] ?? "pendentes")));
$tab = in_array($tab, ["pendentes", "comprovantes"], true) ? $tab : "pendentes";

$compSeenTs = intval($_COOKIE["assinatura_comp_seen_ts"] ?? 0);

$rowsPendentes = [];
$rowsComprovantes = [];
$hasAss = mysqli_query($conn, "SHOW TABLES LIKE 'assinantes'");
$hasSol = mysqli_query($conn, "SHOW TABLES LIKE 'solicitacoes_assinatura'");
if($hasAss && mysqli_num_rows($hasAss) > 0 && $hasSol && mysqli_num_rows($hasSol) > 0){
	$sqlPend = "
		SELECT
			s.id AS solicitacao_id,
			s.nome_arquivo_original,
			s.tipo_documento_id,
			t.tipo_tx_nome AS tipo_documento_nome,
			s.modo_envio,
			s.validar_icp,
			s.data_solicitacao,
			s.expires_at,
			s.status AS status_solicitacao,
			a.id AS assinante_id,
			a.token,
			a.ordem,
			a.funcao,
			a.status AS status_assinante
		FROM assinantes a
		JOIN solicitacoes_assinatura s ON s.id = a.id_solicitacao
		LEFT JOIN tipos_documentos t ON t.tipo_nb_id = s.tipo_documento_id
		WHERE a.enti_nb_id = ?
			AND LOWER(TRIM(a.status)) <> 'assinado'
			AND a.ordem = (
				SELECT MIN(a2.ordem)
				FROM assinantes a2
				WHERE a2.id_solicitacao = a.id_solicitacao
					AND LOWER(TRIM(a2.status)) <> 'assinado'
			)
			AND (s.status = 'pendente' OR s.status = 'em_progresso')
		ORDER BY s.data_solicitacao DESC, s.id DESC
	";
	$stmt = mysqli_prepare($conn, $sqlPend);
	if($stmt){
		mysqli_stmt_bind_param($stmt, "i", $entiSess);
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		if($res){
			$rowsPendentes = mysqli_fetch_all($res, MYSQLI_ASSOC) ?: [];
		}
		mysqli_stmt_close($stmt);
	}

	$sqlComp = "
		SELECT
			s.id AS solicitacao_id,
			s.nome_arquivo_original,
			s.tipo_documento_id,
			t.tipo_tx_nome AS tipo_documento_nome,
			s.modo_envio,
			s.validar_icp,
			s.data_assinatura AS data_conclusao,
			s.status_final,
			s.id_documento AS doc_id_global,
			a.token,
			a.ordem,
			a.funcao,
			a.data_assinatura AS data_assinante
		FROM assinantes a
		JOIN solicitacoes_assinatura s ON s.id = a.id_solicitacao
		LEFT JOIN tipos_documentos t ON t.tipo_nb_id = s.tipo_documento_id
		WHERE a.enti_nb_id = ?
			AND LOWER(TRIM(a.status)) = 'assinado'
			AND LOWER(TRIM(s.status)) IN ('assinado','concluido')
		ORDER BY COALESCE(NULLIF(s.data_assinatura,'0000-00-00 00:00:00'), NULLIF(s.data_solicitacao,'0000-00-00 00:00:00')) DESC, s.id DESC
	";
	$stmt2 = mysqli_prepare($conn, $sqlComp);
	if($stmt2){
		mysqli_stmt_bind_param($stmt2, "i", $entiSess);
		mysqli_stmt_execute($stmt2);
		$res2 = mysqli_stmt_get_result($stmt2);
		if($res2){
			$rowsComprovantes = mysqli_fetch_all($res2, MYSQLI_ASSOC) ?: [];
		}
		mysqli_stmt_close($stmt2);
	}
}

$countPend = intval(count($rowsPendentes));
$countComp = intval(count($rowsComprovantes));
$maxCompTs = 0;
foreach($rowsComprovantes as $r){
	$dt = trim(strval($r["data_conclusao"] ?? ""));
	if($dt === "" || $dt === "0000-00-00 00:00:00"){
		continue;
	}
	$ts = strtotime($dt);
	if($ts && $ts > $maxCompTs){
		$maxCompTs = $ts;
	}
}

$newComp = 0;
if($countComp > 0){
	if($compSeenTs <= 0){
		$newComp = $countComp;
	} else {
		foreach($rowsComprovantes as $r){
			$dt = trim(strval($r["data_conclusao"] ?? ""));
			if($dt === "" || $dt === "0000-00-00 00:00:00"){
				continue;
			}
			$ts = strtotime($dt);
			if($ts && $ts > $compSeenTs){
				$newComp++;
			}
		}
	}
}

if($tab === "comprovantes"){
	$seen = $maxCompTs > 0 ? $maxCompTs : time();
	setcookie("assinatura_comp_seen_ts", strval($seen), time() + 31536000, "/");
	$_COOKIE["assinatura_comp_seen_ts"] = strval($seen);
	$newComp = 0;
}

cabecalho("Assinaturas Pendentes");

echo "<div class='col-md-12'><div class='portlet light'><div class='portlet-body'>";

$tabPendActive = $tab === "pendentes" ? "active" : "";
$tabCompActive = $tab === "comprovantes" ? "active" : "";
$badgeComp = $newComp > 0 ? "<span class='badge badge-success'>{$newComp}</span>" : "";
echo "<ul class='nav nav-tabs' style='margin-bottom: 15px;'>";
echo "  <li class='{$tabPendActive}'><a href='?tab=pendentes'><i class='fa fa-pencil'></i> Pendentes <span class='badge badge-primary'>{$countPend}</span></a></li>";
echo "  <li class='{$tabCompActive}'><a href='?tab=comprovantes'><i class='fa fa-check-circle'></i> Comprovantes {$badgeComp}</a></li>";
echo "</ul>";

if($tab === "pendentes"){
	echo "<div class='row' style='margin-bottom: 10px;'>";
	echo "	<div class='col-md-12'>";
	if($countPend > 0){
		echo "		<div class='note note-info' style='margin-bottom: 10px;'>";
		echo "			<strong><i class='fa fa-bell'></i> Pendências:</strong> {$countPend} documento(s) aguardando sua assinatura.";
		echo "		</div>";
	} else {
		echo "<div class='alert alert-success'><i class='fa fa-check-circle'></i> Nenhum documento pendente de assinatura.</div>";
	}
	if($newComp > 0){
		$txtComp = $newComp === 1 ? "1 comprovante novo" : ($newComp . " comprovantes novos");
		echo "<div class='note note-success' style='margin-bottom: 10px;'>";
		echo "	<strong><i class='fa fa-check-circle'></i> Novidade:</strong> {$txtComp}. ";
		echo "	<a href='?tab=comprovantes' class='btn btn-xs btn-success' style='margin-left:8px;'><i class='fa fa-folder-open'></i> Ver comprovantes</a>";
		echo "</div>";
	}
	echo "	</div>";
	echo "</div>";

	if($countPend > 0){
		echo "<div class='table-responsive'><table class='table table-striped table-bordered table-hover'>";
		echo "<thead><tr>";
		echo "<th><i class='fa fa-file-pdf-o'></i> Documento</th>";
		echo "<th><i class='fa fa-tag'></i> Tipo</th>";
		echo "<th><i class='fa fa-id-badge'></i> Função</th>";
		echo "<th><i class='fa fa-list-ol'></i> Etapa</th>";
		echo "<th><i class='fa fa-calendar'></i> Solicitado em</th>";
		echo "<th><i class='fa fa-clock-o'></i> Prazo</th>";
		echo "<th style='width: 120px;'><i class='fa fa-play-circle'></i> Ação</th>";
		echo "</tr></thead><tbody>";

		$now = time();
		foreach($rowsPendentes as $r){
			$doc = htmlspecialchars(strval($r["nome_arquivo_original"] ?? ""), ENT_QUOTES, "UTF-8");
			$funcao = htmlspecialchars(strval($r["funcao"] ?? "Signatário"), ENT_QUOTES, "UTF-8");
			$ordem = intval($r["ordem"] ?? 0);
			$dataSol = strval($r["data_solicitacao"] ?? "");
			$dataSolFmt = $dataSol !== "" ? date("d/m/Y H:i", strtotime($dataSol)) : "—";
			$tipoDocNome = htmlspecialchars(trim(strval($r["tipo_documento_nome"] ?? "")), ENT_QUOTES, "UTF-8");
			$tipoDocCell = $tipoDocNome !== "" ? ("<span class='label label-info'><i class='fa fa-tag'></i> {$tipoDocNome}</span>") : "<span class='text-muted'>—</span>";

			$modoEnvio = strtolower(trim(strval($r["modo_envio"] ?? "")));
			$envioLabel = $modoEnvio === "governanca" ? "Governança" : ($modoEnvio !== "" ? ucfirst($modoEnvio) : "Avulso");
			$envioBadge = $modoEnvio === "governanca"
				? "<span class='label label-primary' style='margin-left:6px;'><i class='fa fa-sitemap'></i> {$envioLabel}</span>"
				: "<span class='label label-default' style='margin-left:6px;'><i class='fa fa-paper-plane'></i> {$envioLabel}</span>";

			$icp = strtolower(trim(strval($r["validar_icp"] ?? "nao"))) === "sim";
			$icpBadge = $icp ? "<span class='label label-success' style='margin-left:6px;'><i class='fa fa-shield'></i> ICP</span>" : "";

			$expiresRaw = trim(strval($r["expires_at"] ?? ""));
			$expired = false;
			$expiresFmt = "—";
			$prazoExtra = "";
			if($expiresRaw !== "" && $expiresRaw !== "0000-00-00 00:00:00"){
				$ts = strtotime($expiresRaw . " UTC");
				if($ts){
					$expiresFmt = date("d/m/Y H:i", $ts);
					if($now > $ts){
						$expired = true;
						$prazoExtra = " <span class='label label-danger'><i class='fa fa-exclamation-triangle'></i> Expirado</span>";
					} else {
						$diff = $ts - $now;
						$horas = (int)floor($diff / 3600);
						$dias = (int)floor($horas / 24);
						if($dias > 0){
							$prazoExtra = " <span class='label label-warning'><i class='fa fa-hourglass-half'></i> {$dias}d</span>";
						} elseif($horas > 0){
							$prazoExtra = " <span class='label label-warning'><i class='fa fa-hourglass-half'></i> {$horas}h</span>";
						} else {
							$prazoExtra = " <span class='label label-warning'><i class='fa fa-hourglass-half'></i> menos de 1h</span>";
						}
					}
				}
			}

			$token = strval($r["token"] ?? "");
			$link = "assinar_via_link.php?token=" . urlencode($token);
			$btn = $expired
				? "<span class='label label-default'><i class='fa fa-lock'></i> Expirado</span>"
				: "<a class='btn btn-primary btn-sm' href='{$link}'><i class='fa fa-pencil'></i> Assinar</a>";
			$solId = intval($r["solicitacao_id"] ?? 0);
			$docMeta = "";
			if($solId > 0){
				$docMeta = "<div class='text-muted' style='font-size: 12px; margin-top: 4px;'><i class='fa fa-hashtag'></i> Solicitação {$solId}{$envioBadge}{$icpBadge}</div>";
			} else {
				$docMeta = "<div class='text-muted' style='font-size: 12px; margin-top: 4px;'>{$envioBadge}{$icpBadge}</div>";
			}

			echo "<tr>";
			echo "<td><strong><i class='fa fa-file-pdf-o text-danger'></i> {$doc}</strong>{$docMeta}</td>";
			echo "<td>{$tipoDocCell}</td>";
			echo "<td><span class='label label-default'><i class='fa fa-user'></i> {$funcao}</span></td>";
			echo "<td><span class='label label-info'><i class='fa fa-step-forward'></i> ".($ordem > 0 ? $ordem : "—")."</span></td>";
			echo "<td>{$dataSolFmt}</td>";
			echo "<td>{$expiresFmt}{$prazoExtra}</td>";
			echo "<td>{$btn}</td>";
			echo "</tr>";
		}

		echo "</tbody></table></div>";
	}
} else {
	echo "<div class='row' style='margin-bottom: 10px;'>";
	echo "	<div class='col-md-12'>";
	if($countComp > 0){
		echo "		<div class='note note-success' style='margin-bottom: 10px;'>";
		echo "			<strong><i class='fa fa-check-circle'></i> Comprovantes:</strong> {$countComp} documento(s) finalizado(s).";
		echo "		</div>";
	} else {
		echo "<div class='alert alert-info'><i class='fa fa-info-circle'></i> Nenhum comprovante disponível.</div>";
	}
	if($countPend > 0){
		$txtPend = $countPend === 1 ? "1 pendência aguardando sua assinatura" : ($countPend . " pendências aguardando sua assinatura");
		echo "<div class='note note-info' style='margin-bottom: 10px;'>";
		echo "	<strong><i class='fa fa-bell'></i> Atenção:</strong> {$txtPend}. ";
		echo "	<a href='?tab=pendentes' class='btn btn-xs btn-primary' style='margin-left:8px;'><i class='fa fa-pencil'></i> Ir para pendências</a>";
		echo "</div>";
	}
	echo "	</div>";
	echo "</div>";

	if($countComp > 0){
		echo "<div class='table-responsive'><table class='table table-striped table-bordered table-hover'>";
		echo "<thead><tr>";
		echo "<th><i class='fa fa-file-pdf-o'></i> Documento</th>";
		echo "<th><i class='fa fa-tag'></i> Tipo</th>";
		echo "<th><i class='fa fa-sitemap'></i> Modo</th>";
		echo "<th><i class='fa fa-calendar-check-o'></i> Concluído em</th>";
		echo "<th style='width: 160px;'><i class='fa fa-download'></i> Ação</th>";
		echo "</tr></thead><tbody>";

		foreach($rowsComprovantes as $r){
			$doc = htmlspecialchars(strval($r["nome_arquivo_original"] ?? ""), ENT_QUOTES, "UTF-8");
			$tipoDocNome = htmlspecialchars(trim(strval($r["tipo_documento_nome"] ?? "")), ENT_QUOTES, "UTF-8");
			$tipoDocCell = $tipoDocNome !== "" ? ("<span class='label label-info'><i class='fa fa-tag'></i> {$tipoDocNome}</span>") : "<span class='text-muted'>—</span>";

			$modoEnvio = strtolower(trim(strval($r["modo_envio"] ?? "")));
			$envioLabel = $modoEnvio === "governanca" ? "Governança" : ($modoEnvio !== "" ? ucfirst($modoEnvio) : "Avulso");
			$envioBadge = $modoEnvio === "governanca"
				? "<span class='label label-primary'><i class='fa fa-sitemap'></i> {$envioLabel}</span>"
				: "<span class='label label-default'><i class='fa fa-paper-plane'></i> {$envioLabel}</span>";

			$icp = strtolower(trim(strval($r["validar_icp"] ?? "nao"))) === "sim";
			$statusFinal = strtolower(trim(strval($r["status_final"] ?? "")));
			$icpBadge = $icp
				? ($statusFinal === "finalizado"
					? "<span class='label label-success' style='margin-left:6px;'><i class='fa fa-shield'></i> ICP Finalizado</span>"
					: "<span class='label label-warning' style='margin-left:6px;'><i class='fa fa-shield'></i> ICP</span>")
				: "";

			$dataConc = trim(strval($r["data_conclusao"] ?? ""));
			$dataConcFmt = $dataConc !== "" ? date("d/m/Y H:i", strtotime($dataConc)) : "—";

			$solId = intval($r["solicitacao_id"] ?? 0);
			$docMeta = $solId > 0 ? "<div class='text-muted' style='font-size: 12px; margin-top: 4px;'><i class='fa fa-hashtag'></i> Solicitação {$solId}</div>" : "";

			$token = strval($r["token"] ?? "");
			$linkView = "assinar_via_link.php?token=" . urlencode($token) . "&arquivo=1";
			$linkDown = $linkView . "&download=1";
			$btns = "<a class='btn btn-success btn-sm' href='{$linkDown}' target='_blank'><i class='fa fa-download'></i> Baixar</a> <a class='btn btn-default btn-sm' href='{$linkView}' target='_blank'><i class='fa fa-eye'></i></a>";

			echo "<tr>";
			echo "<td><strong><i class='fa fa-file-pdf-o text-danger'></i> {$doc}</strong>{$docMeta}</td>";
			echo "<td>{$tipoDocCell}</td>";
			echo "<td>{$envioBadge}{$icpBadge}</td>";
			echo "<td>{$dataConcFmt}</td>";
			echo "<td>{$btns}</td>";
			echo "</tr>";
		}

		echo "</tbody></table></div>";
	}
}

echo "</div></div></div>";

rodape();
