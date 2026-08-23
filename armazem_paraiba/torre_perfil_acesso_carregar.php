<?php
/* ============================================================
   Torre de Comando — carrega a visibilidade do painel configurada
   para um perfil de acesso (camada de admin, dentro do modal
   "Personalizar painel"). Não confundir com torre_preferencia,
   que é a visualização pessoal de cada usuário.
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

$perfilAcessoId = (int) ($_GET["perfil_acesso_id"] ?? 0);
if ($perfilAcessoId <= 0) {
    http_response_code(400);
    echo json_encode(["ok" => false, "msg" => "Perfil de acesso inválido."]);
    exit;
}

$permitidos = torre_carregar_permitidos_perfil_acesso($perfilAcessoId);
echo json_encode(["ok" => true, "permitidos" => $permitidos]);
