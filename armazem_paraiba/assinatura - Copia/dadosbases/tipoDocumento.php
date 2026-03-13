<?php
$interno = true;
include_once "../../conecta.php";

header('Content-Type: application/json; charset=utf-8');

$incluirInativos = ($_GET["incluirInativos"] ?? $_POST["incluirInativos"] ?? "nao") === "sim";

$where = $incluirInativos ? "1" : "t.tipo_tx_status = 'ativo'";

$sql =
	"SELECT
		t.tipo_nb_id,
		t.tipo_tx_nome,
		t.tipo_tx_vencimento,
		t.tipo_tx_assinatura,
		t.tipo_tx_status,
		t.tipo_nb_grupo,
		gd.grup_tx_nome,
		t.tipo_nb_sbgrupo,
		sb.sbgr_tx_nome
	FROM tipos_documentos t
	LEFT JOIN grupos_documentos gd ON gd.grup_nb_id = t.tipo_nb_grupo
	LEFT JOIN sbgrupos_documentos sb ON sb.sbgr_nb_id = t.tipo_nb_sbgrupo
	WHERE {$where}
	ORDER BY t.tipo_tx_nome ASC";

$rows = mysqli_fetch_all(query($sql), MYSQLI_ASSOC);

echo json_encode([
	"ok" => true,
	"rows" => $rows
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

