<?php
/* ============================================================
   Envio de e-mail com o resumo de notificações do sino.

   ESTE ARQUIVO NÃO RODA SOZINHO — precisa ser chamado
   periodicamente por uma tarefa agendada (cron/Agendador de
   Tarefas do Windows) no servidor, ex.: uma vez por dia:

       php /caminho/completo/armazem_paraiba/notificacao_email_enviar.php

   Não existe nenhum cron configurado neste projeto hoje; quem
   tiver acesso ao servidor precisa agendar essa chamada. Sem
   isso, a preferência de e-mail fica salva mas nenhum e-mail
   é disparado automaticamente.
   ============================================================ */

include __DIR__ . "/load_env.php";
include_once __DIR__ . "/conecta.php";
include_once __DIR__ . "/notificacoes.php";

include_once __DIR__ . "/../PHPMailer/src/Exception.php";
include_once __DIR__ . "/../PHPMailer/src/PHPMailer.php";
include_once __DIR__ . "/../PHPMailer/src/SMTP.php";
include_once __DIR__ . "/assinatura/email_config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function notificacao_email_montar_html(array $itens, string $nomeUsuario): string {
    $linhas = "";
    foreach ($itens as $item) {
        $linhas .= "<tr>"
            . "<td style='padding:10px 12px;border-bottom:1px solid #eee;font-weight:600;color:#333;'>" . htmlspecialchars($item["titulo"]) . "</td>"
            . "<td style='padding:10px 12px;border-bottom:1px solid #eee;color:#777;'>" . htmlspecialchars($item["texto"]) . "</td>"
            . "</tr>";
    }
    return "<div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;'>"
        . "<h3 style='color:#2f6fa3;'>Resumo de notificações — Torre de Comando</h3>"
        . "<p style='color:#555;'>Olá, " . htmlspecialchars($nomeUsuario) . ". Segue o que está pendente de atenção hoje:</p>"
        . "<table style='width:100%;border-collapse:collapse;'>{$linhas}</table>"
        . "<p style='color:#aaa;font-size:12px;margin-top:16px;'>Você recebeu este e-mail porque ativou notificações por e-mail no sino do sistema. Para ajustar, acesse o sistema e clique em \"Configurar notificações\".</p>"
        . "</div>";
}

function notificacao_email_enviar_um(string $destino, string $nomeUsuario, array $itens): bool {
    try {
        $mail = new PHPMailer(true);
        $mail->CharSet = "UTF-8";
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($destino);
        $mail->isHTML(true);
        $mail->Subject = "Resumo de notificações — " . count($itens) . " pendência(s)";
        $mail->Body = notificacao_email_montar_html($itens, $nomeUsuario);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("[notificacao_email_enviar] Falha ao enviar para {$destino}: " . ($mail->ErrorInfo ?? $e->getMessage()));
        return false;
    }
}

// ── Rotina principal ──────────────────────────────────────────────────

notificacao_ensure_schema();

$usuarios = torre_fetch_all(query(
    "SELECT np.noti_nb_usuario AS usuario_id, np.noti_tx_categorias AS categorias, np.noti_tx_email AS email, u.user_tx_nome AS nome
     FROM notificacao_preferencia np
     JOIN user u ON u.user_nb_id = np.noti_nb_usuario
     WHERE np.noti_tx_email_ativo = 'sim' AND np.noti_tx_email <> '' AND u.user_tx_status = 'ativo'"
), MYSQLI_ASSOC) ?: [];

$enviados = 0;
$semPendencia = 0;
$falhas = 0;

foreach ($usuarios as $u) {
    $categorias = json_decode(strval($u["categorias"] ?? ""), true);
    $categorias = is_array($categorias) ? array_map("strval", $categorias) : [];
    $itens = notificacao_calcular($categorias);
    if (empty($itens)) { $semPendencia++; continue; }

    $ok = notificacao_email_enviar_um(strval($u["email"]), strval($u["nome"] ?? "usuário"), $itens);
    if ($ok) { $enviados++; } else { $falhas++; }
}

$resumo = "notificacao_email_enviar: {$enviados} enviado(s), {$semPendencia} sem pendência, {$falhas} falha(s).";
error_log($resumo);
if (php_sapi_name() === "cli") {
    echo $resumo . PHP_EOL;
}
