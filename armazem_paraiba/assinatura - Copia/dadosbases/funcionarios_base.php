<?php
$interno = true;
include_once "../../conecta.php";

header('Content-Type: application/json; charset=utf-8');

$status = $_GET["status"] ?? $_POST["status"] ?? "ativo";
$somenteComEmail = ($_GET["somenteComEmail"] ?? $_POST["somenteComEmail"] ?? "nao") === "sim";

$where = [];
$types = "";
$vars = [];

if(in_array($status, ["ativo", "inativo"], true)){
	$where[] = "enti_tx_status = ?";
	$types .= "s";
	$vars[] = $status;
}

if($somenteComEmail){
	$where[] = "enti_tx_email IS NOT NULL AND enti_tx_email <> ''";
}

$whereSql = empty($where) ? "1" : implode(" AND ", $where);

$sql =
	"SELECT
		enti_nb_id,
		enti_tx_nome,
		enti_tx_cpf,
		enti_tx_email,
		enti_tx_rg
	FROM entidade
	WHERE {$whereSql}
	ORDER BY enti_tx_nome ASC";

$rows = mysqli_fetch_all(query($sql, $types, $vars), MYSQLI_ASSOC);

echo json_encode([
	"ok" => true,
	"rows" => $rows
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

