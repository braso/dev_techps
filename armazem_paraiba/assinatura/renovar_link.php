<?php
$interno = true;
include __DIR__ . "/../conecta.php";
include __DIR__ . "/email_config.php";
include __DIR__ . "/email_helper.php";

require __DIR__ . '/../../PHPMailer/src/Exception.php';
require __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../../PHPMailer/src/SMTP.php';

header('Content-Type: application/json');

$ids = $_POST['ids'] ?? [];
$extraDias = intval($_POST['extra_dias'] ?? 0);
$renovarToken = $_POST['renovar_token'] ?? '';

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'Nenhum documento selecionado.']);
    exit;
}

$ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'IDs inválidos.']);
    exit;
}

if ($extraDias < 1 || $extraDias > 30) {
    echo json_encode(['success' => false, 'message' => 'Prazo deve ser entre 1 e 30 dias.']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$expectedToken = $_SESSION['renovar_token'] ?? '';
if ($expectedToken === '' || !hash_equals($expectedToken, $renovarToken)) {
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido. Recarregue a página.']);
    exit;
}

$sucesso = 0;
$erros = 0;
$mensagens = [];

foreach ($ids as $idSol) {
    $res = mysqli_query($conn, "SELECT * FROM solicitacoes_assinatura WHERE id = $idSol");
    $sol = mysqli_fetch_assoc($res);
    if (!$sol) {
        $erros++;
        $mensagens[] = "Solicitação #$idSol não encontrada.";
        continue;
    }

    $dataEnvioRaw = trim(strval($sol['created_at'] ?? $sol['data_solicitacao'] ?? ''));
    if ($dataEnvioRaw === '' || $dataEnvioRaw === '0000-00-00 00:00:00') {
        $erros++;
        $mensagens[] = "Solicitação #$idSol sem data de envio.";
        continue;
    }

    $tsEnvio = strtotime($dataEnvioRaw);
    if (!$tsEnvio) {
        $erros++;
        $mensagens[] = "Solicitação #$idSol com data de envio inválida.";
        continue;
    }

    $diasDecorridos = (int)floor((time() - $tsEnvio) / 86400);
    if ($diasDecorridos < 0) $diasDecorridos = 0;

    if ($diasDecorridos >= 30) {
        $maxExtra = 30;
    } else {
        $maxExtra = 30 - $diasDecorridos;
    }

    if ($extraDias > $maxExtra) {
        $erros++;
        $mensagens[] = "Solicitação #$idSol: prazo máximo de extensão é $maxExtra dias (enviada há $diasDecorridos dias).";
        continue;
    }

    $stmtUpd = mysqli_prepare($conn, "UPDATE solicitacoes_assinatura SET expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? DAY), prazo_expiracao_dias = ? WHERE id = ?");
    if (!$stmtUpd) {
        $erros++;
        $mensagens[] = "Solicitação #$idSol: erro de preparação.";
        continue;
    }
    mysqli_stmt_bind_param($stmtUpd, "iii", $extraDias, $extraDias, $idSol);
    if (!mysqli_stmt_execute($stmtUpd)) {
        mysqli_stmt_close($stmtUpd);
        $erros++;
        $mensagens[] = "Solicitação #$idSol: erro ao atualizar.";
        continue;
    }
    mysqli_stmt_close($stmtUpd);

    $resAss = mysqli_query($conn, "SELECT * FROM assinantes WHERE id_solicitacao = $idSol AND LOWER(TRIM(status)) = 'pendente' ORDER BY ordem ASC, id ASC");
    if (!$resAss) {
        $erros++;
        $mensagens[] = "Solicitação #$idSol: erro ao buscar assinantes.";
        continue;
    }

    $temPendentes = false;
    while ($ass = mysqli_fetch_assoc($resAss)) {
        $temPendentes = true;
        $idAss = intval($ass['id']);
        $novoToken = bin2hex(random_bytes(32));

        $stmtTok = mysqli_prepare($conn, "UPDATE assinantes SET token = ? WHERE id = ?");
        if ($stmtTok) {
            mysqli_stmt_bind_param($stmtTok, "si", $novoToken, $idAss);
            mysqli_stmt_execute($stmtTok);
            mysqli_stmt_close($stmtTok);
        }

        $email = trim(strval($ass['email']));
        $nome = trim(strval($ass['nome']));
        $funcao = trim(strval($ass['funcao'] ?? 'Signatário'));
        $nomeArquivo = trim(strval($sol['nome_arquivo_original'] ?? ''));
        $idDoc = trim(strval($sol['id_documento'] ?? ''));
        $entiNbId = intval($ass['enti_nb_id'] ?? 0);

        if ($email !== '') {
            if ($entiNbId > 0) {
                $baseUrl = assinatura_getBaseUrl();
                $baseUrl = rtrim($baseUrl !== "" ? $baseUrl : (defined("BASE_URL_ASSINATURA") ? (string)BASE_URL_ASSINATURA : ""), "/");
                $linkAssinatura = $baseUrl . "/assinar_via_link.php?token=" . urlencode($novoToken);
                $linkPlataforma = $baseUrl . "/pendentes.php";

                $stmtNotif = mysqli_prepare($conn, "INSERT INTO notificacoes (notf_nb_entidade, notf_tx_titulo, notf_tx_mensagem, notf_tx_link, notf_tx_tipo) VALUES (?, ?, ?, ?, 'sucesso')");
                if ($stmtNotif) {
                    $titulo = "Novo prazo para assinatura";
                    $msg = "O prazo para assinar o documento \"$nomeArquivo\" foi renovado por mais $extraDias dias. Acesse seu link de assinatura.";
                    mysqli_stmt_bind_param($stmtNotif, "isss", $entiNbId, $titulo, $msg, $linkAssinatura);
                    mysqli_stmt_execute($stmtNotif);
                    mysqli_stmt_close($stmtNotif);
                }
            }

            enviarEmailAssinatura($email, $nome, $novoToken, $nomeArquivo, $idDoc, $funcao);
        }
    }

    if (!$temPendentes) {
        $mensagens[] = "Solicitação #$idSol: todos os assinantes já assinaram. Prazo renovado, mas sem pendentes.";
    }

    $sucesso++;
}

echo json_encode([
    'success' => $sucesso > 0,
    'message' => "$sucesso documento(s) renovado(s) com sucesso. $erros erro(s).",
    'details' => $mensagens
]);
