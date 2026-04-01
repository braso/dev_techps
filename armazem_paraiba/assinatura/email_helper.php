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
<<<<<<< HEAD
function enviarEmailProximo($email, $nome, $token, $nomeArquivo, $idDoc, $funcao, $caminhoArquivo = null) {
=======
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
>>>>>>> a6192cd1684b6740d21dfbd919cc78a416304661
    // Função de log deve estar disponível ou removemos o logDebug
    if (function_exists('logDebug')) {
        logDebug("Enviando email para próximo: $email");
    }
    
    $mail = new PHPMailer(true);
    try {
        configurarSMTP($mail);
        $mail->addAddress($email, $nome);
        
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

        $entiId = intval($entiNbId);
        if ($entiId <= 0) {
            $entiId = assinatura_obterEntiIdPorEmail(strval($email));
        }
        $counts = assinatura_getNotificacoesCounts($entiId);
        $qPend = intval($counts["pendentes"] ?? 0);
        $qComp = intval($counts["comprovantes"] ?? 0);
        $sPend = $qPend === 1 ? "1 pendência" : ($qPend . " pendências");
        $sComp = $qComp === 1 ? "1 comprovante" : ($qComp . " comprovantes");
        $badgeLine = "<span style='display:inline-block;padding:6px 10px;border-radius:9999px;background:#eff6ff;color:#1d4ed8;font-weight:700;font-size:12px;margin-right:8px;'>Pendentes: {$qPend}</span>"
            . "<span style='display:inline-block;padding:6px 10px;border-radius:9999px;background:#ecfdf5;color:#065f46;font-weight:700;font-size:12px;'>Comprovantes: {$qComp}</span>";
        $linkPlataformaComp = $linkPlataforma . "?tab=comprovantes";
        
        $mail->isHTML(true);
        $mail->Subject = "Sua vez de assinar ($funcao): Documento #$idDoc";
        
        $corpo = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd;'>
            <h2>Ação Necessária: Assinatura de Documento</h2>
            <p>Olá, <strong>$nome</strong>.</p>
            <p>O documento <strong>$nomeArquivo</strong> requer sua assinatura como <strong>$funcao</strong>.</p>
            <p>O signatário anterior já concluiu a etapa dele.</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='$linkAssinatura' style='background-color: #007bff; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Revisar e Assinar</a>
            </p>
            <p style='text-align: center; margin: 0 0 30px 0;'>
                <a href='$linkPlataforma' style='background-color: #16a34a; color: white; padding: 12px 18px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Assinar pela Plataforma</a>
            </p>
            <p style='text-align:center; margin: 0 0 10px 0;'>$badgeLine</p>
            <p style='text-align:center; margin: 0 0 18px 0; color:#374151; font-size: 12px;'>Você tem $sPend e $sComp disponíveis na plataforma.</p>
            <p>Link direto: $linkAssinatura</p>
            <p>Plataforma (Assinaturas Pendentes): <a href='$linkPlataforma'>$linkPlataforma</a></p>
            <p>Comprovantes (Documentos Finalizados): <a href='$linkPlataformaComp'>$linkPlataformaComp</a></p>
        </div>";
        
        $mail->Body = $corpo;
        $mail->AltBody = "Assine pelo link: $linkAssinatura\nPlataforma (Assinaturas Pendentes): $linkPlataforma\nComprovantes (Documentos Finalizados): $linkPlataformaComp\nNotificações: Pendentes {$qPend} | Comprovantes {$qComp}\nDocumento #$idDoc ($funcao).";
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
    $tabelaAudit = "<table style='width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 13px;'>
        <tr style='background: #f3f4f6; color: #111827;'>
            <th style='border: 1px solid #e5e7eb; padding: 10px; text-align: left;'>Ordem</th>
            <th style='border: 1px solid #e5e7eb; padding: 10px; text-align: left;'>Nome</th>
            <th style='border: 1px solid #e5e7eb; padding: 10px; text-align: left;'>E-mail</th>
            <th style='border: 1px solid #e5e7eb; padding: 10px; text-align: left;'>CPF</th>
            <th style='border: 1px solid #e5e7eb; padding: 10px; text-align: left;'>RG</th>
            <th style='border: 1px solid #e5e7eb; padding: 10px; text-align: left;'>Função</th>
            <th style='border: 1px solid #e5e7eb; padding: 10px; text-align: left;'>Data Assinatura</th>
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
            <td style='border: 1px solid #e5e7eb; padding: 10px; text-align: center; background: #ffffff;'>" . intval($a['ordem'] ?? 0) . "</td>
            <td style='border: 1px solid #e5e7eb; padding: 10px; background: #ffffff;'>{$nomeA}</td>
            <td style='border: 1px solid #e5e7eb; padding: 10px; background: #ffffff;'>{$emailA}</td>
            <td style='border: 1px solid #e5e7eb; padding: 10px; background: #ffffff;'>{$cpfFmt}</td>
            <td style='border: 1px solid #e5e7eb; padding: 10px; background: #ffffff;'>{$rgA}</td>
            <td style='border: 1px solid #e5e7eb; padding: 10px; background: #ffffff;'><span style='display:inline-block; padding:4px 8px; border-radius:9999px; background:#eef2ff; color:#1f2937; font-weight:600;'>{$funcaoA}</span></td>
            <td style='border: 1px solid #e5e7eb; padding: 10px; background: #ffffff;'>{$dataA}</td>
        </tr>";
    }
    $tabelaAudit .= "</table>";
    
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
            $corpo = "
            <div style='font-family: Arial, sans-serif; padding: 24px; border: 1px solid #e5e7eb; border-radius: 14px; background:#ffffff;'>
                <table role='presentation' cellpadding='0' cellspacing='0' border='0' style='width:100%;margin-bottom:12px;'>
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
                <p style='color:#374151;margin:0 0 8px 0;'>Olá, <strong>$nome</strong>.</p>
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
                <div style='margin-top:16px;color:#9ca3af;font-size:12px;'>TechPS Assinaturas Digitais</div>
            </div>";
            
            $mail->Body = $corpo;
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
