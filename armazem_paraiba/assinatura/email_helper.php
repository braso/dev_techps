<?php
// email_helper.php

// Dependências do PHPMailer devem ser carregadas antes deste arquivo ou aqui
// Certifique-se de que email_config.php foi incluído

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function configurarSMTP($mail) {
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
}

function assinatura_email_embedLogo($mail, string $cid = "logo_techps"): string {
    $logoPath = __DIR__ . "/assets/logo.png";
    if (file_exists($logoPath)) {
        try {
            $mail->addEmbeddedImage($logoPath, $cid);
            return "cid:" . $cid;
        } catch (Throwable $e) {
            return "";
        }
    }
    return "";
}

function assinatura_email_wrap(string $headerHtml, string $contentHtml, string $footerHtml): string {
    return "
        <div style='font-family: Arial, sans-serif; width:100%; max-width: 960px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; background-color: #ffffff;'>
            <div style='text-align: center; padding: 30px; background-color: #f8f9fa; border-bottom: 1px solid #e0e0e0; border-radius: 8px 8px 0 0;'>
                {$headerHtml}
            </div>
            <div style='padding: 40px 30px;'>
                {$contentHtml}
            </div>
            <div style='background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; border-radius: 0 0 8px 8px;'>
                {$footerHtml}
            </div>
        </div>
    ";
}

function assinatura_getBaseUrl(): string {
    $fallback = defined("BASE_URL_ASSINATURA") ? rtrim((string)BASE_URL_ASSINATURA, "/") : "";

    $proto = trim(strval($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? ""));
    if($proto === ""){
        $https = strtolower(trim(strval($_SERVER["HTTPS"] ?? "")));
        $proto = ($https !== "" && $https !== "off") ? "https" : "http";
    }

    $host = trim(strval($_SERVER["HTTP_X_FORWARDED_HOST"] ?? ""));
    if($host === ""){
        $host = trim(strval($_SERVER["HTTP_HOST"] ?? ""));
    }
    if($host === ""){
        return $fallback;
    }

    $scriptName = str_replace("\\", "/", strval($_SERVER["SCRIPT_NAME"] ?? ""));
    $dir = rtrim(str_replace("\\", "/", dirname($scriptName)), "/");
    if($dir === "."){
        $dir = "";
    }

    return rtrim($proto . "://" . $host . $dir, "/");
}

function assinatura_normalizarCpfDigits(string $cpf): string {
    return preg_replace('/\D+/', '', $cpf) ?? '';
}

function assinatura_formatarCpf(string $cpf): string {
    $d = assinatura_normalizarCpfDigits($cpf);
    if (strlen($d) !== 11) {
        return trim($cpf);
    }
    return substr($d, 0, 3) . "." . substr($d, 3, 3) . "." . substr($d, 6, 3) . "-" . substr($d, 9, 2);
}

function assinatura_normalizarRg(string $rg): string {
    $rg = strtoupper(trim($rg));
    return preg_replace('/[^0-9A-Z]+/', '', $rg) ?? '';
}

function assinatura_h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
function assinatura_maskEmail(string $email): string {
    $email = strtolower(trim($email));
    if ($email === "" || strpos($email, "@") === false) {
        return "—";
    }
    [$local, $domain] = explode("@", $email, 2);
    $l = strlen($local);
    if ($l <= 1) {
        $maskedLocal = "*";
    } else {
        $maskedLocal = substr($local, 0, 1) . str_repeat("*", max(1, $l - 2)) . substr($local, -1);
    }
    return assinatura_h($maskedLocal . "@" . $domain);
}
function assinatura_maskCpf(string $cpf): string {
    $d = assinatura_normalizarCpfDigits($cpf);
    if (strlen($d) !== 11) {
        return "—";
    }
    $last2 = substr($d, -2);
    return "***.***.***-" . $last2;
}
function assinatura_maskRg(string $rg): string {
    $n = assinatura_normalizarRg($rg);
    if ($n === "") {
        return "—";
    }
    $tail = substr($n, -3);
    $maskLen = max(0, strlen($n) - strlen($tail));
    return str_repeat("*", $maskLen) . $tail;
}
function assinatura_getNotificacoesCounts(int $entiId): array {
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return ["pendentes" => 0, "comprovantes" => 0];
    }
    $entiId = intval($entiId);
    if ($entiId <= 0) {
        return ["pendentes" => 0, "comprovantes" => 0];
    }

    $pendentes = 0;
    $stmtP = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM assinantes a
         JOIN solicitacoes_assinatura s ON s.id = a.id_solicitacao
         WHERE a.enti_nb_id = ?
           AND LOWER(TRIM(a.status)) <> 'assinado'
           AND a.ordem = (
                SELECT MIN(a2.ordem)
                FROM assinantes a2
                WHERE a2.id_solicitacao = a.id_solicitacao
                  AND LOWER(TRIM(a2.status)) <> 'assinado'
           )
           AND (s.status = 'pendente' OR s.status = 'em_progresso')"
    );
    if ($stmtP) {
        mysqli_stmt_bind_param($stmtP, "i", $entiId);
        mysqli_stmt_execute($stmtP);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtP));
        mysqli_stmt_close($stmtP);
        $pendentes = intval($row["total"] ?? 0);
    }

    $comprovantes = 0;
    $stmtC = mysqli_prepare(
        $conn,
        "SELECT COUNT(DISTINCT s.id) AS total
         FROM assinantes a
         JOIN solicitacoes_assinatura s ON s.id = a.id_solicitacao
         WHERE a.enti_nb_id = ?
           AND LOWER(TRIM(a.status)) = 'assinado'
           AND LOWER(TRIM(s.status)) IN ('assinado','concluido')"
    );
    if ($stmtC) {
        mysqli_stmt_bind_param($stmtC, "i", $entiId);
        mysqli_stmt_execute($stmtC);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtC));
        mysqli_stmt_close($stmtC);
        $comprovantes = intval($row["total"] ?? 0);
    }

    return ["pendentes" => $pendentes, "comprovantes" => $comprovantes];
}

function assinatura_obterEntiIdPorEmail(string $email): int {
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return 0;
    }
    $email = strtolower(trim($email));
    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 0;
    }
    $stmt = mysqli_prepare($conn, "SELECT enti_nb_id FROM entidade WHERE LOWER(TRIM(enti_tx_email)) = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return intval($row["enti_nb_id"] ?? 0);
    }
    return 0;
}

function enviarEmailProximo($email, $nome, $token, $nomeArquivo, $idDoc, $funcao, $caminhoArquivo = null, $entiNbId = 0) {
    global $conn;
    // Função de log deve estar disponível ou removemos o logDebug
    if (function_exists('logDebug')) {
        logDebug("Enviando email para próximo: $email");
    }
    
    $mail = new PHPMailer(true);
    try {
        configurarSMTP($mail);
        $mail->addAddress($email, $nome);

        $cidLogo = assinatura_email_embedLogo($mail, "logo_techps_proximo");
        
        // Anexa o arquivo parcialmente assinado, se disponível
        if ($caminhoArquivo && file_exists($caminhoArquivo)) {
            // Verifica se é caminho absoluto ou relativo
            $path = $caminhoArquivo;
            if (!file_exists($path) && file_exists(__DIR__ . '/' . $caminhoArquivo)) {
                 $path = __DIR__ . '/' . $caminhoArquivo;
            }
            $mail->addAttachment($path, 'Documento_Assinado_Parcialmente.pdf');
        } elseif ($caminhoArquivo && file_exists(__DIR__ . '/' . $caminhoArquivo)) {
             $mail->addAttachment(__DIR__ . '/' . $caminhoArquivo, 'Documento_Assinado_Parcialmente.pdf');
        }
        
        $base = assinatura_getBaseUrl();
        $baseUrl = rtrim($base !== "" ? $base : (defined("BASE_URL_ASSINATURA") ? (string)BASE_URL_ASSINATURA : ""), "/");
        $linkAssinatura = $baseUrl . "/assinar_via_link.php?token=" . urlencode((string)$token);
        $linkPlataforma = $baseUrl . "/pendentes.php";
        
        $mail->isHTML(true);
        $mail->Subject = "Assinatura Pendente ($funcao): Documento #$idDoc";

        $headerHtml = $cidLogo !== ""
            ? "<img src='{$cidLogo}' alt='TechPS' style='max-width: 150px;'>"
            : "<h2 style='color: #333; margin: 0;'>TechPS</h2>";

        $nomeUp = assinatura_h(strtoupper(strval($nome)));
        $nomeArquivoSafe = assinatura_h(strval($nomeArquivo));
        $funcaoSafe = assinatura_h(strval($funcao));
        $idDocSafe = assinatura_h(strval($idDoc));

        $contentHtml = "
            <h2 style='color: #333; font-size: 24px; margin-top: 0;'>Olá, {$nomeUp}.</h2>

            <p style='color: #555; font-size: 16px; line-height: 1.5;'>
                Você foi indicado como <strong style='color: #0056b3;'>{$funcaoSafe}</strong> para assinar o documento abaixo:
            </p>

            <p style='color:#555; font-size: 14px; line-height:1.5; margin: 10px 0 0 0;'>
                O signatário anterior já concluiu a etapa dele.
            </p>

            <div style='background-color: #f8f9fa; border-left: 4px solid #0056b3; padding: 20px; margin: 25px 0; border-radius: 4px;'>
                <p style='margin: 0 0 10px 0; color: #555;'>
                    <strong style='color: #333;'>Documento:</strong> <br>
                    <span style='font-size: 18px; color: #0056b3;'>{$nomeArquivoSafe}</span>
                </p>
                <p style='margin: 0; color: #555;'>
                    <strong style='color: #333;'>ID:</strong> <br>
                    <span style='font-family: monospace; font-size: 14px; background: #e9ecef; padding: 2px 6px; rounded: 3px;'>{$idDocSafe}</span>
                </p>
            </div>

            <div style='text-align: center; margin: 35px 0;'>
                <a href='{$linkAssinatura}' style='background-color: #0056b3; color: white; padding: 16px 32px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
                    Revisar e Assinar Agora
                </a>
            </div>

            <div style='text-align: center; margin: 0 0 35px 0;'>
                <a href='{$linkPlataforma}' style='background-color: #16a34a; color: white; padding: 14px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; display: inline-block;'>
                    Assinar pela Plataforma
                </a>
            </div>

            <div style='border-top: 1px solid #eee; margin-top: 30px; padding-top: 20px;'>
                <p style='font-size: 13px; color: #777; margin-bottom: 10px;'>
                    Se o botão não funcionar, copie e cole o link abaixo no seu navegador:
                </p>
                <p style='font-size: 12px; color: #555; background: #f8f9fa; padding: 10px; border-radius: 4px; word-break: break-all; font-family: monospace; border: 1px solid #eee;'>
                    {$linkAssinatura}
                </p>
            </div>

            <div style='margin-top: 30px; font-size: 12px; color: #777; text-align: justify; line-height: 1.5; border-top: 1px solid #eee; padding-top: 20px;'>
                <p style='margin-bottom: 10px;'>
                    <strong>Informações Legais:</strong>
                </p>
                <p>
                    Este procedimento de assinatura eletrônica é realizado em conformidade com a <strong>Medida Provisória nº 2.200-2/2001</strong>, que institui a Infraestrutura de Chaves Públicas Brasileira (ICP-Brasil) e garante a autenticidade, a integridade e a validade jurídica de documentos em forma eletrônica, bem como das aplicações que utilizem certificados digitais, e dá outras providências. A validade jurídica desta assinatura é assegurada pela concordância expressa das partes envolvidas.
                </p>
                <p style='margin-top: 10px;'>
                    <strong>Data de Envio:</strong> " . date('d/m/Y H:i:s') . "
                </p>
            </div>
        ";

        $footerHtml = "
            <p style='margin: 0;'>Mensagem automática enviada pelo sistema de Assinatura Digital TechPS.</p>
            <p style='margin: 5px 0 0 0;'>&copy; " . date('Y') . " Armazém Paraíba - Todos os direitos reservados.</p>
        ";

        $mail->Body = assinatura_email_wrap($headerHtml, $contentHtml, $footerHtml);
        $mail->AltBody = "Assinatura pendente: Documento #{$idDoc} ({$funcao}).\n\nAssine pelo link: {$linkAssinatura}\n";
        $mail->send();
        
        if (function_exists('logDebug')) {
            logDebug("Email enviado com sucesso para $email");
        }
    } catch (Exception $e) {
        if (function_exists('logDebug')) {
            logDebug("Erro envio email proximo: " . $mail->ErrorInfo);
        }
        error_log("Erro envio email proximo: " . $mail->ErrorInfo);
    }
}

function enviarEmailFinalizacao($conn, $idSolicitacao, $idDoc, $caminhoArquivo) {
    if (function_exists('logDebug')) {
        logDebug("Preparando email de finalização para solicitacao $idSolicitacao");
    }
    
    // 1. Busca dados da solicitação (dono)
    $sqlSol = "SELECT * FROM solicitacoes_assinatura WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sqlSol);
    mysqli_stmt_bind_param($stmt, "i", $idSolicitacao);
    mysqli_stmt_execute($stmt);
    $solicitacao = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $tipoDocNome = "";
    if ($solicitacao) {
        $tipoId = intval($solicitacao["tipo_documento_id"] ?? 0);
        if ($tipoId > 0) {
            $stmtT = mysqli_prepare($conn, "SELECT tipo_tx_nome FROM tipos_documentos WHERE tipo_nb_id = ? LIMIT 1");
            if ($stmtT) {
                mysqli_stmt_bind_param($stmtT, "i", $tipoId);
                mysqli_stmt_execute($stmtT);
                $rowT = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT));
                mysqli_stmt_close($stmtT);
                $tipoDocNome = assinatura_h(trim(strval($rowT["tipo_tx_nome"] ?? "")));
            }
        }
    }
    
    // 2. Busca todos os assinantes para o relatório
    $sqlAssinantes = "SELECT * FROM assinantes WHERE id_solicitacao = ? ORDER BY ordem ASC";
    $stmtA = mysqli_prepare($conn, $sqlAssinantes);
    mysqli_stmt_bind_param($stmtA, "i", $idSolicitacao);
    mysqli_stmt_execute($stmtA);
    $resA = mysqli_stmt_get_result($stmtA);
    
    $listaAssinantes = [];
    while ($row = mysqli_fetch_assoc($resA)) {
        $listaAssinantes[] = $row;
    }
    
    // Monta Tabela de Auditoria HTML
    $cellCommon = "border:1px solid #e5e7eb;padding:8px;background:#ffffff;vertical-align:top;white-space:nowrap;line-height:1.25;";
    $thCommon = "border:1px solid #e5e7eb;padding:8px;text-align:left;vertical-align:top;white-space:nowrap;line-height:1.25;";
    $tabelaAudit = "<div style='width:100%;overflow-x:auto;'>
        <table style='width:100%; min-width: 980px; border-collapse:collapse; margin-top:16px; font-size:12px; table-layout:auto;'>
            <tr style='background:#f3f4f6;color:#111827;'>
                <th style='{$thCommon}text-align:center;'>Ordem</th>
                <th style='{$thCommon}'>Nome</th>
                <th style='{$thCommon}'>E-mail</th>
                <th style='{$thCommon}'>CPF</th>
                <th style='{$thCommon}'>RG</th>
                <th style='{$thCommon}'>Função</th>
                <th style='{$thCommon}'>Data Assinatura</th>
            </tr>";
    
    foreach ($listaAssinantes as $a) {
        $dataFmt = $a['data_assinatura'] ? date('d/m/Y H:i', strtotime($a['data_assinatura'])) : 'Pendente';
        $nomeA = assinatura_h(strval($a['nome'] ?? ''));
        $emailA = assinatura_maskEmail(strval($a['email'] ?? ''));
        $cpfA = trim(strval($a['cpf'] ?? ''));
        $cpfFmt = $cpfA !== '' ? assinatura_maskCpf($cpfA) : '—';
        $rgA = '—';
        $metaRaw = strval($a['metadados'] ?? '');
        if ($metaRaw !== '') {
            $meta = json_decode($metaRaw, true);
            if (is_array($meta)) {
                $rgTmp = trim(strval($meta['rg'] ?? ''));
                if ($rgTmp !== '') {
                    $rgA = assinatura_h(assinatura_maskRg($rgTmp));
                }
            }
        }
        $funcaoA = assinatura_h(strval($a['funcao'] ?? ''));
        $dataA = assinatura_h($dataFmt);
        $tabelaAudit .= "<tr>
            <td style='{$cellCommon}text-align:center;'>" . intval($a['ordem'] ?? 0) . "</td>
            <td style='{$cellCommon}'>{$nomeA}</td>
            <td style='{$cellCommon}'>{$emailA}</td>
            <td style='{$cellCommon}'>{$cpfFmt}</td>
            <td style='{$cellCommon}'>{$rgA}</td>
            <td style='{$cellCommon}'><span style='display:inline-block; max-width:100%; padding:4px 8px; border-radius:9999px; background:#eef2ff; color:#1f2937; font-weight:600; font-size:12px; white-space:nowrap; line-height:1.2;'>{$funcaoA}</span></td>
            <td style='{$cellCommon}'>{$dataA}</td>
        </tr>";
    }
    $tabelaAudit .= "</table></div>";
    
    // Lista de Destinatários (Todos os assinantes + Dono da solicitação)
    $destinatarios = [];
    if ($solicitacao && isset($solicitacao['email'])) {
        $destinatarios[$solicitacao['email']] = $solicitacao['nome']; // Dono
    }
    foreach ($listaAssinantes as $a) {
        if (!empty($a['email'])) {
            $destinatarios[$a['email']] = $a['nome'];
        }
    }
    
    // Envia para cada um
    foreach ($destinatarios as $email => $nome) {
        if (function_exists('logDebug')) {
            logDebug("Enviando email final para: $email");
        }
        $mail = new PHPMailer(true);
        try {
            configurarSMTP($mail);
            $mail->addAddress($email, $nome);

            $cidLogo = assinatura_email_embedLogo($mail, "logo_techps_final_" . bin2hex(random_bytes(4)));
            
            // Anexa o arquivo final
            // Verifica caminho absoluto ou relativo
            $path = $caminhoArquivo;
            if ($caminhoArquivo && !file_exists($path) && file_exists(__DIR__ . '/' . $caminhoArquivo)) {
                 $path = __DIR__ . '/' . $caminhoArquivo;
            }
            
            if ($path && file_exists($path)) {
                $mail->addAttachment($path, 'Documento_Finalizado.pdf');
            }
            
            $mail->isHTML(true);
            $mail->Subject = "Concluído: Documento #$idDoc Assinado por Todos";
            
            $nomeArquivoOriginal = assinatura_h(strval($solicitacao["nome_arquivo_original"] ?? "Documento"));
            $modoEnvio = strtolower(trim(strval($solicitacao["modo_envio"] ?? "")));
            $badgeModo = $modoEnvio === "governanca"
                ? "<span style='display:inline-block;padding:6px 10px;border-radius:9999px;background:#e0f2fe;color:#0369a1;font-weight:700;font-size:12px;'>Governança</span>"
                : "<span style='display:inline-block;padding:6px 10px;border-radius:9999px;background:#f3f4f6;color:#374151;font-weight:700;font-size:12px;'>Avulso</span>";
            $badgeTipo = $tipoDocNome !== ""
                ? "<span style='display:inline-block;padding:6px 10px;border-radius:9999px;background:#ede9fe;color:#4c1d95;font-weight:700;font-size:12px;'><span style=\"margin-right:6px;\">Tipo</span> $tipoDocNome</span>"
                : "";
            $headerHtml = $cidLogo !== ""
                ? "<img src='{$cidLogo}' alt='TechPS' style='max-width: 150px;'>"
                : "<h2 style='color: #333; margin: 0;'>TechPS</h2>";

            $nomeSafe = assinatura_h(strtoupper(strval($nome)));
            $contentHtml = "
                <table role='presentation' cellpadding='0' cellspacing='0' border='0' style='width:100%;margin-bottom:16px;'>
                    <tr>
                        <td style='width:42px;vertical-align:middle;'>
                            <div style='width:42px;height:42px;border-radius:9999px;background:#dcfce7;text-align:center;line-height:42px;'>
                                <span style='font-size:20px;color:#15803d;line-height:42px;display:inline-block;'>✓</span>
                            </div>
                        </td>
                        <td style='padding-left:12px;vertical-align:middle;'>
                            <h2 style='margin:0;color:#111827;font-size:20px;line-height:1.3;'>Processo de Assinatura Concluído</h2>
                        </td>
                    </tr>
                </table>

                <p style='color:#374151;margin:0 0 10px 0;'>Olá, <strong>{$nomeSafe}</strong>.</p>
                <p style='color:#374151;margin:0 0 2px 0;'>O documento foi assinado por todas as partes envolvidas.</p>

                <div style='margin:12px 0;padding:12px;border:1px dashed #e5e7eb;border-radius:10px;background:#fafafa;'>
                    <div style='color:#111827;font-weight:600;margin-bottom:6px;'>$nomeArquivoOriginal</div>
                    <div style='display:flex;gap:8px;flex-wrap:wrap;'>$badgeModo $badgeTipo</div>
                </div>

                <div style='margin-top:16px;margin-bottom:8px;color:#111827;font-weight:700;'>Registro das assinaturas</div>
                $tabelaAudit

                <div style='margin-top:16px;padding-top:12px;border-top:1px solid #e5e7eb;color:#374151;'>
                    O documento final assinado está em anexo.
                </div>
            ";

            $footerHtml = "
                <p style='margin: 0;'>Mensagem automática enviada pelo sistema de Assinatura Digital TechPS.</p>
                <p style='margin: 5px 0 0 0;'>&copy; " . date('Y') . " Armazém Paraíba - Todos os direitos reservados.</p>
            ";

            $mail->Body = assinatura_email_wrap($headerHtml, $contentHtml, $footerHtml);
            $mail->AltBody = "Processo de Assinatura Concluído.\nDocumento #{$idDoc} foi assinado por todas as partes.\nO documento final assinado está em anexo.";
            $mail->send();
            
            if (function_exists('logDebug')) {
                logDebug("Email final enviado para $email");
            }
        } catch (Exception $e) {
            if (function_exists('logDebug')) {
                logDebug("Erro ao enviar email final para $email: " . $mail->ErrorInfo);
            }
            error_log("Erro ao enviar email final para $email: " . $mail->ErrorInfo);
        }
    }
}
?>
