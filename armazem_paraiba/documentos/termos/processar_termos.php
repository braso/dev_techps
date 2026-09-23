<?php
include_once __DIR__ . "/funcoes_termos.php";
include_once dirname(__DIR__, 2) . "/conecta.php";

function termos_json_resposta(int $code, array $payload): void {
	http_response_code($code);
	header("Content-Type: application/json; charset=utf-8");
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

@set_time_limit(120);

if($_SERVER["REQUEST_METHOD"] !== "POST"){
	termos_json_resposta(405, ["ok" => false, "error" => "Método não permitido. Use POST."]);
}

termos_ensure_tables($conn);

if(!termos_pode_acessar("/documentos/termos/gerar_termos.php")){
	termos_log("acesso_negado", "Tentativa de processamento sem permissão");
	termos_json_resposta(403, ["ok" => false, "error" => "Sem permissão para gerar termos."]);
}

$raw = file_get_contents("php://input");
$dados = json_decode($raw, true);
if(!is_array($dados)){
	$dados = $_POST;
}

$modeloId = intval($dados["modelo_id"] ?? 0);
$entidades = $dados["entidades"] ?? [];
if(!is_array($entidades)){
	$entidades = [];
}
$entidades = array_values(array_filter(array_map("intval", $entidades)));

if($modeloId <= 0 || empty($entidades)){
	termos_json_resposta(400, ["ok" => false, "error" => "Modelo ou funcionários ausentes."]);
}

$params = [
	"modelo_id" => $modeloId,
	"enviar_assinatura" => strval($dados["enviar_assinatura"] ?? "sim"),
	"validar_icp" => strval($dados["validar_icp"] ?? "nao"),
	"enviar_email" => strval($dados["enviar_email"] ?? "sim"),
	"forcar" => strval($dados["forcar"] ?? "nao")
];

$resultados = [];
foreach($entidades as $entiId){
	$resultados[] = termos_processar_um($entiId, $params);
}

termos_json_resposta(200, [
	"ok" => true,
	"processados" => count($resultados),
	"resultados" => $resultados
]);