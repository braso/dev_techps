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

function enviarEmailProximo($email, $nome, $token, $nomeArquivo, $idDoc, $funcao, $caminhoArquivo = null) {
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
        $linkAssinatura = rtrim($base !== "" ? $base : (defined("BASE_URL_ASSINATURA") ? (string)BASE_URL_ASSINATURA : ""), "/") . "/assinar_via_link.php?token=" . $token;
        
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
            <p>Ou acesse: $linkAssinatura</p>
        </div>";
        
        $mail->Body = $corpo;
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
    $tabelaAudit = "<table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
        <tr style='background: #f8f9fa;'>
            <th style='border: 1px solid #ddd; padding: 8px;'>Ordem</th>
            <th style='border: 1px solid #ddd; padding: 8px;'>Nome</th>
            <th style='border: 1px solid #ddd; padding: 8px;'>Função</th>
            <th style='border: 1px solid #ddd; padding: 8px;'>Data Assinatura</th>
        </tr>";
    
    foreach ($listaAssinantes as $a) {
        $dataFmt = $a['data_assinatura'] ? date('d/m/Y H:i', strtotime($a['data_assinatura'])) : 'Pendente';
        $tabelaAudit .= "<tr>
            <td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>{$a['ordem']}</td>
            <td style='border: 1px solid #ddd; padding: 8px;'>{$a['nome']}</td>
            <td style='border: 1px solid #ddd; padding: 8px;'>{$a['funcao']}</td>
            <td style='border: 1px solid #ddd; padding: 8px;'>{$dataFmt}</td>
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
            
            $corpo = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd;'>
                <h2 style='color: green;'>Processo de Assinatura Concluído</h2>
                <p>Olá, <strong>$nome</strong>.</p>
                <p>O documento foi assinado por todas as partes envolvidas.</p>
                <p>Segue abaixo o registro das assinaturas:</p>
                $tabelaAudit
                <br>
                <p>O documento final assinado está em anexo.</p>
                <p style='font-size: 12px; color: #999; margin-top: 20px;'>TechPS Assinaturas Digitais</p>
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
