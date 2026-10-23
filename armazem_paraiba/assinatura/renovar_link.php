<?php
$interno = true;
include __DIR__ . "/../conecta.php";
include __DIR__ . "/email_config.php";
include __DIR__ . "/email_helper.php";
/* A função de envio mora aqui desde que foi tirada do processar_envio.php
   (incluir aquele arquivo dispararia um envio de verdade). Sem este
   require, a chamada abaixo abortava com erro fatal DEPOIS de o prazo já
   ter sido gravado: o prazo valia, o e-mail não saía e a tela não recebia
   resposta nenhuma. */
require_once __DIR__ . "/email_assinatura.php";

require_once __DIR__ . '/../../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';

header('Content-Type: application/json');

$ids = $_POST['ids'] ?? [];
$extraDias = intval($_POST['extra_dias'] ?? 0);
$renovarToken = $_POST['renovar_token'] ?? '';

/* O prazo mora na solicitação (expires_at), não no token: o
   assinar_via_link.php acha o signatário pelo token e só então consulta o
   prazo. Estender o prazo, por si só, já faz o link que a pessoa recebeu
   voltar a funcionar.

   Trocar o token é uma decisão de segurança, não uma necessidade técnica:
   serve para o caso de o link ter vazado enquanto estava expirado. Por
   isso virou escolha de quem renova, com o padrão em MANTER — é o que
   menos atrapalha quem já tem o link no e-mail ou no WhatsApp. */
$manterLink = (($_POST['manter_link'] ?? 'sim') !== 'nao');
$reenviarEmail = (($_POST['reenviar_email'] ?? 'sim') !== 'nao');

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

    /* O novo prazo conta a partir de HOJE — é o que o UPDATE abaixo faz,
       com DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? DAY).

       Havia aqui um teto de "30 menos os dias desde o envio" que
       contradizia isso e invertia a intenção: um documento enviado há 27
       dias só podia receber 3 dias de prazo, enquanto um de 31 dias podia
       receber 30 inteiros. Na prática travava justamente o caso de uso —
       dar prazo novo a um documento que já expirou faz tempo.

       A validação que vale é a de cima: entre 1 e 30 dias, contados de
       agora. Se um dia for preciso um teto de vida útil total da
       solicitação, ele tem de ser explícito e dito ao operador, não
       derivado da data de envio. */

    // Se estava expirada, reabre: 'em_progresso' se alguém já assinou, senão 'pendente'
    @mysqli_query($conn, "UPDATE solicitacoes_assinatura s
        SET s.status = IF((SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND LOWER(TRIM(a.status)) = 'assinado') > 0, 'em_progresso', 'pendente')
        WHERE s.id = " . intval($idSol) . " AND LOWER(TRIM(s.status)) = 'expirado'");
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
        $tokenAtual = trim(strval($ass['token'] ?? ''));

        if ($manterLink && $tokenAtual !== '') {
            /* Mesmo link de antes. Nada a gravar: o prazo novo já foi
               aplicado na solicitação, e é ele que libera o acesso. */
            $novoToken = $tokenAtual;
        } else {
            $novoToken = bin2hex(random_bytes(32));

            $stmtTok = mysqli_prepare($conn, "UPDATE assinantes SET token = ? WHERE id = ?");
            if ($stmtTok) {
                mysqli_stmt_bind_param($stmtTok, "si", $novoToken, $idAss);
                mysqli_stmt_execute($stmtTok);
                mysqli_stmt_close($stmtTok);
            }
        }

        $email = trim(strval($ass['email']));
        $nome = trim(strval($ass['nome']));
        $funcao = trim(strval($ass['funcao'] ?? 'Signatário'));
        $nomeArquivo = trim(strval($sol['nome_arquivo_original'] ?? ''));
        $idDoc = trim(strval($sol['id_documento'] ?? ''));
        $entiNbId = intval($ass['enti_nb_id'] ?? 0);

        if ($email !== '' && $reenviarEmail) {
            if ($entiNbId > 0) {
                $baseUrl = assinatura_getBaseUrl();
                $baseUrl = rtrim($baseUrl !== "" ? $baseUrl : (defined("BASE_URL_ASSINATURA") ? (string)BASE_URL_ASSINATURA : ""), "/");
                $linkAssinatura = $baseUrl . "/assinar_via_link.php?token=" . urlencode($novoToken);
                $linkPlataforma = $baseUrl . "/pendentes.php";

                $stmtNotif = mysqli_prepare($conn, "INSERT INTO notificacoes (notf_nb_entidade, notf_tx_titulo, notf_tx_mensagem, notf_tx_link, notf_tx_tipo) VALUES (?, ?, ?, ?, 'sucesso')");
                if ($stmtNotif) {
                    $titulo = "Novo prazo para assinatura";
                    $msg = "O prazo para assinar o documento \"$nomeArquivo\" foi renovado por mais $extraDias dias. "
                        . ($manterLink
                            ? "O link que você já recebeu voltou a funcionar."
                            : "Use o link novo desta mensagem: o anterior não vale mais.");
                    mysqli_stmt_bind_param($stmtNotif, "isss", $entiNbId, $titulo, $msg, $linkAssinatura);
                    mysqli_stmt_execute($stmtNotif);
                    mysqli_stmt_close($stmtNotif);
                }
            }

            /* O prazo já está gravado neste ponto. Se o e-mail falhar — SMTP
               fora do ar, endereço inválido — isso é um aviso, não um motivo
               para derrubar a requisição e deixar o operador sem resposta
               achando que nada foi feito. */
            try {
                enviarEmailAssinatura($email, $nome, $novoToken, $nomeArquivo, $idDoc, $funcao);
            } catch (\Throwable $erroEmail) {
                $mensagens[] = "Solicitação #$idSol: prazo renovado, mas o e-mail para $email não saiu ("
                    . $erroEmail->getMessage() . "). O link novo está válido; reenvie por outro meio se precisar.";
                error_log("[renovar_link] falha ao enviar e-mail para $email: " . $erroEmail->getMessage());
            }
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
    'link'    => $manterLink ? 'mantido' : 'novo',
    'email'   => $reenviarEmail ? 'reenviado' : 'nao_enviado',
    'details' => $mensagens
]);
