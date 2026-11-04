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
if(strval($doc["status"] ?? "") !== "ativo"){
	echo json_encode(["ok" => false, "msg" => "Reative o POP antes de compartilhar."], JSON_UNESCAPED_UNICODE);
	exit;
}

$token = pop_criar_envio($origem, $docId, 0, "", "", "", $userId);
if($token === ""){
	http_response_code(500);
	echo json_encode(["ok" => false, "msg" => "Não foi possível gerar o link."], JSON_UNESCAPED_UNICODE);
	exit;
}

pop_log("link_gerado", "Link público gerado para o POP", ["origem" => $origem, "doc" => $docId]);

echo json_encode([
	"ok" => true,
	"url" => pop_link_publico($token),
	"expira" => date("d/m/Y H:i", strtotime("+30 days"))
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
