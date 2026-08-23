<?php
/* ============================================================
   Torre de Comando — salva uma visualização nomeada (perfil) de
   visibilidade de cards/painéis do usuário logado.
   Tabela torre_preferencia criada/migrada automaticamente.
   ============================================================ */

include "conecta.php";
include_once "torre_comando.php";

header("Content-Type: application/json; charset=utf-8");

if (empty($_SESSION["user_nb_id"])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "msg" => "Sessão expirada. Recarregue a página e faça login novamente."]);
    exit;
}

$usuarioId = intval($_SESSION["user_nb_id"]);

$corpo = json_decode(file_get_contents("php://input"), true);

$nome = trim(strval($corpo["nome"] ?? ""));
if ($nome === "") {
    http_response_code(400);
    echo json_encode(["ok" => false, "msg" => "Informe um nome para essa visualização."]);
    exit;
}
if (mb_strlen($nome) > 100) {
    $nome = mb_substr($nome, 0, 100);
}

$ocultos = is_array($corpo["ocultos"] ?? null)
    ? array_values(array_unique(array_map("strval", $corpo["ocultos"])))
    : [];

$ordemSecoes = is_array($corpo["ordemSecoes"] ?? null)
    ? array_values(array_unique(array_map("strval", $corpo["ordemSecoes"])))
    : [];

$ordemItens = [];
if (is_array($corpo["ordemItens"] ?? null)) {
    foreach ($corpo["ordemItens"] as $secaoChave => $lista) {
        if (!is_array($lista)) continue;
        $ordemItens[strval($secaoChave)] = array_values(array_map("strval", $lista));
    }
}

torre_ensure_preferencia_schema();

$config = ["ocultos" => $ocultos, "ordemSecoes" => $ordemSecoes, "ordemItens" => $ordemItens];
$ocultosJson = json_encode($config, JSON_UNESCAPED_UNICODE);
$agora = date("Y-m-d H:i:s");

query(
    "INSERT INTO torre_preferencia (torr_nb_usuario, torr_tx_nome, torr_tx_ocultos, torr_tx_dataCadastro, torr_tx_dataAtualiza)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE torr_tx_ocultos = VALUES(torr_tx_ocultos), torr_tx_dataAtualiza = VALUES(torr_tx_dataAtualiza)",
    "issss",
    [$usuarioId, $nome, $ocultosJson, $agora, $agora]
);

echo json_encode(["ok" => true, "nome" => $nome]);
