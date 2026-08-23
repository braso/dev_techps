<?php
/* ============================================================
   Salva a preferência de notificações do sino (categorias
   habilitadas + envio por e-mail) do usuário logado.
   Tabela notificacao_preferencia criada automaticamente.
   ============================================================ */

include "conecta.php";
include_once "notificacoes.php";

header("Content-Type: application/json; charset=utf-8");

if (empty($_SESSION["user_nb_id"])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "msg" => "Sessão expirada. Recarregue a página e faça login novamente."]);
    exit;
}

$usuarioId = intval($_SESSION["user_nb_id"]);
$corpo = json_decode(file_get_contents("php://input"), true);

$disponiveis = array_keys(notificacao_categorias_disponiveis());
$categorias = is_array($corpo["categorias"] ?? null)
    ? array_values(array_intersect(array_unique(array_map("strval", $corpo["categorias"])), $disponiveis))
    : [];

$emailAtivo = !empty($corpo["email_ativo"]) ? "sim" : "nao";
$email = trim(strval($corpo["email"] ?? ""));
if ($emailAtivo === "sim" && (!filter_var($email, FILTER_VALIDATE_EMAIL))) {
    http_response_code(400);
    echo json_encode(["ok" => false, "msg" => "Informe um e-mail válido para ativar o envio por e-mail."]);
    exit;
}
if ($emailAtivo === "nao") {
    $email = trim(strval($corpo["email"] ?? "")); // mantém o e-mail digitado mesmo se o envio estiver desligado
}

notificacao_ensure_schema();

$categoriasJson = json_encode($categorias, JSON_UNESCAPED_UNICODE);
$agora = date("Y-m-d H:i:s");

query(
    "INSERT INTO notificacao_preferencia (noti_nb_usuario, noti_tx_categorias, noti_tx_email_ativo, noti_tx_email, noti_tx_dataAtualiza)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE noti_tx_categorias = VALUES(noti_tx_categorias), noti_tx_email_ativo = VALUES(noti_tx_email_ativo),
        noti_tx_email = VALUES(noti_tx_email), noti_tx_dataAtualiza = VALUES(noti_tx_dataAtualiza)",
    "issss",
    [$usuarioId, $categoriasJson, $emailAtivo, $email, $agora]
);

echo json_encode(["ok" => true]);
