<?php
include_once __DIR__ . "/funcoes_termos.php";
include_once dirname(__DIR__, 2) . "/conecta.php";

function termos_json_resposta2(int $code, array $payload): void {
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

@set_time_limit(120);

if($_SERVER["REQUEST_METHOD"] !== "POST"){
    termos_json_resposta2(405, ["ok" => false, "error" => "Método não permitido. Use POST."]);
}

termos_ensure_tables($conn);

if(!termos_pode_acessar("/documentos/termos/gerar_termos.php")){
    termos_log("acesso_negado", "Tentativa de processamento de aparelhos sem permissão");
    termos_json_resposta2(403, ["ok" => false, "error" => "Sem permissão para gerar termos."]);
}

$raw = file_get_contents("php://input");
$dados = json_decode($raw, true);
if(!is_array($dados)){ $dados = $_POST; }

$modeloIdParam = intval($dados["modelo_id"] ?? 0);
$aparelhos = $dados["aparelhos"] ?? [];
if(!is_array($aparelhos)){ $aparelhos = []; }
$aparelhos = array_values(array_unique(array_filter(array_map("intval", $aparelhos))));

if(empty($aparelhos)){
    termos_json_resposta2(400, ["ok" => false, "error" => "Nenhum aparelho selecionado."]);
}

$params = [
    "modelo_id" => $modeloIdParam,
    "enviar_assinatura" => strval($dados["enviar_assinatura"] ?? "sim"),
    "modo_envio" => strval($dados["modo_envio"] ?? ""),
    "validar_icp" => strval($dados["validar_icp"] ?? "nao"),
    "enviar_email" => strval($dados["enviar_email"] ?? "sim"),
    "forcar" => strval($dados["forcar"] ?? "nao"),
    "prazo_expiracao_dias" => intval($dados["prazo_expiracao_dias"] ?? 1),
    "hierarquia" => strval($dados["hierarquia"] ?? "nao")
];

$resultados = [];
foreach($aparelhos as $celuId){
    // Carrega aparelho + responsável.
    $res = query(
        "SELECT c.celu_nb_id, c.celu_nb_entidade, c.celu_nb_modelo_termo, c.celu_tx_nome, c.celu_tx_imei, c.celu_tx_status
         FROM celular c WHERE c.celu_nb_id = ? LIMIT 1",
        "i", [$celuId]
    );
    $cel = ($res instanceof mysqli_result) ? mysqli_fetch_assoc($res) : null;
    if(empty($cel) || intval($cel["celu_nb_entidade"] ?? 0) <= 0){
        $resultados[] = ["ok" => false, "aparelho" => $celuId, "msg" => "Aparelho #{$celuId} — sem responsável vinculado."];
        continue;
    }
    $entiId = intval($cel["celu_nb_entidade"]);
    $statusAparelho = strval($cel["celu_tx_status"] ?? "");
    if($statusAparelho !== "em_uso"){
        $resultados[] = ["ok" => false, "aparelho" => $celuId, "msg" => "Aparelho #{$celuId} — status deve ser 'Em uso' para enviar termo (status atual: " . $statusAparelho . ")."];
        continue;
    }
    // Modelos vinculados a este aparelho.
    $idsVinc = [];
    $principal = intval($cel["celu_nb_modelo_termo"] ?? 0);
    if($principal > 0){ $idsVinc[] = $principal; }
    $resV = query("SELECT cetm_nb_modelo FROM celular_termo WHERE cetm_nb_celular = ?", "i", [$celuId]);
    while($resV && ($rv = mysqli_fetch_assoc($resV))){
        $mid = intval($rv["cetm_nb_modelo"] ?? 0);
        if($mid > 0 && !in_array($mid, $idsVinc, true)){ $idsVinc[] = $mid; }
    }
    if($modeloIdParam > 0){
        if(!in_array($modeloIdParam, $idsVinc, true)){
            $resultados[] = ["ok" => false, "aparelho" => $celuId, "msg" => "Aparelho #{$celuId} — não possui o termo selecionado vinculado."];
            continue;
        }
        $modelosParaEnviar = [$modeloIdParam];
    } else {
        $modelosParaEnviar = $idsVinc;
    }
    if(empty($modelosParaEnviar)){
        $resultados[] = ["ok" => false, "aparelho" => $celuId, "msg" => "Aparelho #{$celuId} — sem termo vinculado."];
        continue;
    }

    foreach($modelosParaEnviar as $modeloId){
        if($modeloId <= 0){ continue; }
        $modelo = termos_carregar_modelo($modeloId);
        if(empty($modelo) || strtolower(trim(strval($modelo["mode_tx_status"] ?? "inativo"))) !== "ativo"){
            $resultados[] = ["ok" => false, "aparelho" => $celuId, "msg" => "Aparelho #{$celuId} — modelo de termo inativo ou não encontrado."];
            continue;
        }
        $tipo = termos_carregar_tipo(intval($modelo["mode_nb_tipo_doc"] ?? 0));
        if(empty($tipo) || strtolower(trim(strval($tipo["tipo_tx_status"] ?? "inativo"))) !== "ativo" || !termos_tipo_requer_ativo($tipo)){
            $resultados[] = ["ok" => false, "aparelho" => $celuId, "msg" => "Aparelho #{$celuId} — o tipo de documento do modelo não é 'com equipamento'."];
            continue;
        }

        $p = $params;
        $p["modelo_id"] = $modeloId;
        $p["ativo_id"] = $celuId;
        $r = termos_processar_um($entiId, $p);
        $r["aparelho"] = $celuId;
        $r["aparelho_nome"] = strval($cel["celu_tx_nome"] ?? "");
        $resultados[] = $r;
    }
}

termos_json_resposta2(200, [
    "ok" => true,
    "processados" => count($resultados),
    "resultados" => $resultados
]);
