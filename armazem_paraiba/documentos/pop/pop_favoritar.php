<?php
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

include_once __DIR__ . "/funcoes_pop.php";
include_once dirname(__DIR__, 2) . "/conecta.php";

if(!termos_pode_acessar("/documentos/pop/index.php")){
	http_response_code(403);
	echo json_encode(["ok" => false, "msg" => "Acesso negado."], JSON_UNESCAPED_UNICODE);
	exit;
}

pop_ensure_schema();

$userId = intval($_SESSION["user_nb_id"] ?? 0);
$origem = strtolower(trim(strval($_POST["origem"] ?? "")));
$docId = intval($_POST["id"] ?? 0);

if($userId <= 0 || !in_array($origem, ["termo", "upload"], true) || $docId <= 0){
	http_response_code(400);
	echo json_encode(["ok" => false, "msg" => "Dados inválidos."], JSON_UNESCAPED_UNICODE);
	exit;
}

$doc = pop_obter_documento($origem, $docId);
if(empty($doc)){
	http_response_code(404);
	echo json_encode(["ok" => false, "msg" => "POP não encontrado."], JSON_UNESCAPED_UNICODE);
	exit;
}

$res = query(
	"SELECT popf_nb_id FROM pop_favorito
	 WHERE popf_nb_user = ? AND popf_tx_origem = ? AND popf_nb_doc = ?
	 LIMIT 1",
	"isi",
	[$userId, $origem, $docId]
);
$existente = ($res instanceof mysqli_result) ? (mysqli_fetch_assoc($res) ?: []) : [];

if(!empty($existente)){
	query(
		"DELETE FROM pop_favorito WHERE popf_nb_user = ? AND popf_tx_origem = ? AND popf_nb_doc = ?",
		"isi",
		[$userId, $origem, $docId]
	);
	echo json_encode(["ok" => true, "favorito" => false], JSON_UNESCAPED_UNICODE);
	exit;
}

inserir("pop_favorito", [
	"popf_nb_user",
	"popf_tx_origem",
	"popf_nb_doc",
	"popf_tx_dataCadastro"
], [
	$userId,
	$origem,
	$docId,
	date("Y-m-d H:i:s")
]);

echo json_encode(["ok" => true, "favorito" => true], JSON_UNESCAPED_UNICODE);
