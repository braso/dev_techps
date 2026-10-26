<?php
/**
 * Valida se os modelos de termo informados pertencem a tipos de documento
 * com Assinatura = Sim, requisito para o modo Assinatura/ICP.
 */
include_once __DIR__ . "/funcoes_termos.php";
include_once dirname(__DIR__, 2) . "/conecta.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Content-Type: application/json; charset=utf-8");

function termos_icp_resposta(int $code, array $payload): void {
	http_response_code($code);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

if($_SERVER["REQUEST_METHOD"] !== "POST"){
	termos_icp_resposta(405, ["ok" => false, "error" => "Método não permitido. Use POST."]);
}

termos_ensure_tables($conn);

if(!termos_pode_acessar("/documentos/termos/gerar_termos.php")){
	termos_icp_resposta(403, ["ok" => false, "error" => "Sem permissão para verificar o tipo de documento."]);
}

$raw = file_get_contents("php://input");
$dados = json_decode($raw, true);
if(!is_array($dados)){
	$dados = $_POST;
}

$modelos = $dados["modelos"] ?? ($dados["modelo_id"] ?? []);
if(!is_array($modelos)){
	$modelos = [$modelos];
}
$modelos = array_values(array_unique(array_filter(array_map("intval", $modelos))));

if(empty($modelos)){
	termos_icp_resposta(400, ["ok" => false, "error" => "Informe ao menos um modelo de termo."]);
}

$tipos = [];
$erros = [];

foreach($modelos as $modeloId){
	$modelo = termos_carregar_modelo($modeloId);
	if(empty($modelo)){
		$erros[] = "Modelo #{$modeloId} não encontrado.";
		continue;
	}

	$tipo = termos_carregar_tipo(intval($modelo["mode_nb_tipo_doc"] ?? 0));
	if(empty($tipo)){
		$erros[] = "Tipo de documento do modelo \"" . termos_h($modelo["mode_tx_nome"] ?? ("#" . $modeloId)) . "\" não encontrado.";
		continue;
	}

	$tipoId = intval($tipo["tipo_nb_id"] ?? 0);
	if($tipoId <= 0){
		$erros[] = "Tipo de documento inválido para o modelo \"" . termos_h($modelo["mode_tx_nome"] ?? ("#" . $modeloId)) . "\".";
		continue;
	}

	if(!isset($tipos[$tipoId])){
		$tipos[$tipoId] = [
			"tipo_id" => $tipoId,
			"tipo_nome" => strval($tipo["tipo_tx_nome"] ?? ""),
			"tipo_assinatura" => termos_tipo_requer_assinatura($tipo) ? "sim" : "nao",
			"modelos" => []
		];
	}

	$tipos[$tipoId]["modelos"][] = [
		"id" => $modeloId,
		"nome" => strval($modelo["mode_tx_nome"] ?? "")
	];
}

$lista = array_values($tipos);
$bloqueado = false;
foreach($lista as $tipoInfo){
	if(strtolower(trim(strval($tipoInfo["tipo_assinatura"] ?? "nao"))) !== "sim"){
		$bloqueado = true;
		break;
	}
}

termos_icp_resposta(200, [
	"ok" => true,
	"bloqueado" => $bloqueado,
	"tipos" => $lista,
	"erros" => $erros
]);
