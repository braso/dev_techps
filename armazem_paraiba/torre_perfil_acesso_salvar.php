<?php
/* ============================================================
   Torre de Comando — salva (ou remove) a visibilidade do painel
   para um perfil de acesso (camada de admin, dentro do modal
   "Personalizar painel"). Diferente de torre_preferencia_salvar.php:
   isso não é por usuário — é por perfil de acesso (mesmo cadastro de
   cadastro_perfil_acesso.php) e vale pra todo mundo que tiver aquele
   perfil.
   ============================================================ */

include "conecta.php";
include_once "torre_comando.php";

header("Content-Type: application/json; charset=utf-8");

if (empty($_SESSION["user_nb_id"])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "msg" => "Sessão expirada. Recarregue a página e faça login novamente."]);
    exit;
}

if (!torre_pode_gerenciar_perfil_painel()) {
    http_response_code(403);
    echo json_encode(["ok" => false, "msg" => "Você não tem permissão para gerenciar a visibilidade por perfil de acesso."]);
    exit;
}

$corpo = json_decode(file_get_contents("php://input"), true);
$perfilAcessoId = (int) ($corpo["perfil_acesso_id"] ?? 0);
if ($perfilAcessoId <= 0) {
    http_response_code(400);
    echo json_encode(["ok" => false, "msg" => "Perfil de acesso inválido."]);
    exit;
}

// Confere que o perfil de acesso existe de fato (evita gravar referenciando um id qualquer).
$perfilRow = torre_fetch_assoc(query("SELECT perfil_nb_id FROM perfil_acesso WHERE perfil_nb_id = ?", "i", [$perfilAcessoId]));
if (empty($perfilRow)) {
    http_response_code(404);
    echo json_encode(["ok" => false, "msg" => "Perfil de acesso não encontrado."]);
    exit;
}

torre_ensure_perfil_painel_schema();

if (!empty($corpo["remover"])) {
    query("DELETE FROM torre_perfil_painel WHERE tpp_nb_perfilAcesso = ?", "i", [$perfilAcessoId]);
    echo json_encode(["ok" => true]);
    exit;
}

$permitidos = is_array($corpo["permitidos"] ?? null)
    ? array_values(array_unique(array_map("strval", $corpo["permitidos"])))
    : [];

$permitidosJson = json_encode($permitidos, JSON_UNESCAPED_UNICODE);
$agora = date("Y-m-d H:i:s");

query(
    "INSERT INTO torre_perfil_painel (tpp_nb_perfilAcesso, tpp_tx_permitidos, tpp_tx_dataCadastro, tpp_tx_dataAtualiza)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE tpp_tx_permitidos = VALUES(tpp_tx_permitidos), tpp_tx_dataAtualiza = VALUES(tpp_tx_dataAtualiza)",
    "isss",
    [$perfilAcessoId, $permitidosJson, $agora, $agora]
);

echo json_encode(["ok" => true]);
