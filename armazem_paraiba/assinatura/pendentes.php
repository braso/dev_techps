<?php
include_once "../conecta.php";

$entiSess = intval($_SESSION["user_nb_entidade"] ?? 0);

cabecalho("Assinaturas Pendentes");

echo "<div class='col-md-12'><div class='portlet light'><div class='portlet-body'>";

if($entiSess <= 0){
	echo "<div class='alert alert-warning'>Usuário sem vínculo de funcionário para assinatura.</div>";
	echo "</div></div></div>";
	rodape();
	exit;
}

$rows = [];
$hasAss = mysqli_query($conn, "SHOW TABLES LIKE 'assinantes'");
$hasSol = mysqli_query($conn, "SHOW TABLES LIKE 'solicitacoes_assinatura'");
if($hasAss && mysqli_num_rows($hasAss) > 0 && $hasSol && mysqli_num_rows($hasSol) > 0){
	$sql = "
		SELECT
			s.id AS solicitacao_id,
			s.nome_arquivo_original,
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
	$stmt = mysqli_prepare($conn, $sql);
	if($stmt){
		mysqli_stmt_bind_param($stmt, "i", $entiSess);
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		if($res){
			$rows = mysqli_fetch_all($res, MYSQLI_ASSOC) ?: [];
		}
	}
}

if(empty($rows)){
	echo "<div class='alert alert-success'>Nenhum documento pendente de assinatura.</div>";
	echo "</div></div></div>";
	rodape();
	exit;
}

echo "<div class='table-responsive'><table class='table table-striped table-bordered table-hover'>";
echo "<thead><tr>";
echo "<th>Documento</th>";
echo "<th>Função</th>";
echo "<th>Etapa</th>";
echo "<th>Solicitado em</th>";
echo "<th>Prazo</th>";
echo "<th>Ação</th>";
echo "</tr></thead><tbody>";

$now = time();
foreach($rows as $r){
	$doc = htmlspecialchars(strval($r["nome_arquivo_original"] ?? ""), ENT_QUOTES, "UTF-8");
	$funcao = htmlspecialchars(strval($r["funcao"] ?? "Signatário"), ENT_QUOTES, "UTF-8");
	$ordem = intval($r["ordem"] ?? 0);
	$dataSol = strval($r["data_solicitacao"] ?? "");
	$dataSolFmt = $dataSol !== "" ? date("d/m/Y H:i", strtotime($dataSol)) : "—";

	$expiresRaw = trim(strval($r["expires_at"] ?? ""));
	$expired = false;
	$expiresFmt = "—";
	if($expiresRaw !== "" && $expiresRaw !== "0000-00-00 00:00:00"){
		$ts = strtotime($expiresRaw . " UTC");
		if($ts){
			$expiresFmt = date("d/m/Y H:i", $ts);
			if($now > $ts){
				$expired = true;
			}
		}
	}

	$token = strval($r["token"] ?? "");
	$link = "assinar_via_link.php?token=" . urlencode($token);
	$btn = $expired ? "<span class='label label-default'>Expirado</span>" : "<a class='btn btn-primary btn-sm' href='{$link}'>Assinar</a>";

	echo "<tr>";
	echo "<td>{$doc}</td>";
	echo "<td>{$funcao}</td>";
	echo "<td>".($ordem > 0 ? $ordem : "—")."</td>";
	echo "<td>{$dataSolFmt}</td>";
	echo "<td>{$expiresFmt}</td>";
	echo "<td>{$btn}</td>";
	echo "</tr>";
}

echo "</tbody></table></div>";
echo "</div></div></div>";

rodape();
