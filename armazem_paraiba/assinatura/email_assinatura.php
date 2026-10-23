<?php
/* ============================================================
   Envio do e-mail de assinatura — definição compartilhada.

   Esta função morava dentro do processar_envio.php, que é um SCRIPT:
   incluí-lo dispara um envio de verdade, porque ele processa o $_POST ao
   ser carregado. Quem precisasse só da função — como o renovar_link.php,
   que reenvia o link ao dar mais prazo — não tinha como obtê-la.

   O resultado era um erro em produção que ninguém via: o renovar_link.php
   chamava enviarEmailAssinatura(), a função não existia ali, e o PHP
   abortava com 500 DEPOIS de já ter gravado o novo prazo no banco. O
   prazo era renovado, o e-mail nunca saía e a tela não recebia resposta.

   Agora a definição mora aqui e os dois lados incluem este arquivo.
   ============================================================ */

require_once __DIR__ . "/email_helper.php";

require_once __DIR__ . "/../../PHPMailer/src/Exception.php";
require_once __DIR__ . "/../../PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/../../PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* O guarda evita o erro fatal de redeclaração caso algum fluxo antigo
   ainda carregue as duas definições na mesma requisição. */
if (!function_exists("enviarEmailAssinatura")) {

function enviarEmailAssinatura($email, $nome, $token, $nomeArquivo, $idDoc, $funcao, $externo = false) {
    global $mail; // Usa a instância global ou cria nova se necessário (melhor criar nova)
    
    // Detectar automaticamente se é signatário externo pela função
    if (!$externo && stripos($funcao, 'externo') !== false) {
        $externo = true;
    }
    
    $base = assinatura_getBaseUrl();
    $baseUrl = rtrim($base !== "" ? $base : (defined("BASE_URL_ASSINATURA") ? (string)BASE_URL_ASSINATURA : ""), "/");
    $linkAssinatura = $baseUrl . "/assinar_via_link.php?token=" . urlencode((string)$token);
    $linkPlataforma = $baseUrl . "/pendentes.php";
    
    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $nome);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME . ' Suporte');

        // Logo
        $logoPath = __DIR__ . '/assets/logo.png';
        $cidLogo = '';
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'logo_techps');
            $cidLogo = 'cid:logo_techps';
        }

        $mail->isHTML(true);
        $mail->Subject = "Assinatura Pendente ($funcao): Documento #$idDoc";
        
        $corpo = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; background-color: #ffffff;'>
            <div style='text-align: center; padding: 30px; background-color: #f8f9fa; border-bottom: 1px solid #e0e0e0; border-radius: 8px 8px 0 0;'>
                " . ($cidLogo ? "<img src='$cidLogo' alt='TechPS' style='max-width: 150px;'>" : "<h2 style='color: #333; margin: 0;'>TechPS</h2>") . "
            </div>
            
            <div style='padding: 40px 30px;'>
                <h2 style='color: #333; font-size: 24px; margin-top: 0;'>Olá, " . strtoupper($nome) . ".</h2>
                
                <p style='color: #555; font-size: 16px; line-height: 1.5;'>
                    Você foi indicado como <strong style='color: #0056b3;'>$funcao</strong> para assinar o documento abaixo:
                </p>
                
                <div style='background-color: #f8f9fa; border-left: 4px solid #0056b3; padding: 20px; margin: 25px 0; border-radius: 4px;'>
                    <p style='margin: 0 0 10px 0; color: #555;'>
                        <strong style='color: #333;'>Documento:</strong> <br>
                        <span style='font-size: 18px; color: #0056b3;'>$nomeArquivo</span>
                    </p>
                    <p style='margin: 0; color: #555;'>
                        <strong style='color: #333;'>ID:</strong> <br>
                        <span style='font-family: monospace; font-size: 14px; background: #e9ecef; padding: 2px 6px; rounded: 3px;'>$idDoc</span>
                    </p>
                </div>
                
                <div style='text-align: center; margin: 35px 0;'>
                    <a href='$linkAssinatura' style='background-color: #0056b3; color: white; padding: 16px 32px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
                        Revisar e Assinar Agora
                    </a>
                </div>

                " . (!$externo ? "<div style='text-align: center; margin: 0 0 35px 0;'>
                    <a href='$linkPlataforma' style='background-color: #16a34a; color: white; padding: 14px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; display: inline-block;'>
                        Assinar pela Plataforma
                    </a>
                </div>" : "") . "
                
                <div style='border-top: 1px solid #eee; margin-top: 30px; padding-top: 20px;'>
                    <p style='font-size: 13px; color: #777; margin-bottom: 10px;'>
                        Se o botão não funcionar, copie e cole o link abaixo no seu navegador:
                    </p>
                    <p style='font-size: 12px; color: #555; background: #f8f9fa; padding: 10px; border-radius: 4px; word-break: break-all; font-family: monospace; border: 1px solid #eee;'>
                        $linkAssinatura
                    </p>
                    " . (!$externo ? "<p style='font-size: 12px; color: #777; margin: 10px 0 0 0;'>
                        Alternativa: acesse a plataforma e assine em &quot;Assinaturas Pendentes&quot;: <a href='$linkPlataforma' style='color: #0056b3;'>$linkPlataforma</a>
                    </p>" : "") . "
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
            </div>
            
            <div style='background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; border-radius: 0 0 8px 8px;'>
                <p style='margin: 0;'>Mensagem automática enviada pelo sistema de Assinatura Digital TechPS.</p>
                <p style='margin: 5px 0 0 0;'>&copy; " . date('Y') . " Armazém Paraíba - Todos os direitos reservados.</p>
            </div>
        </div>";

        $mail->Body = $corpo;
        $mail->AltBody = "Assine pelo link: $linkAssinatura\n" . (!$externo ? "Ou acesse a plataforma (Assinaturas Pendentes): $linkPlataforma\n" : "") . "Documento #$idDoc ($funcao).";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erro ao enviar email para $email: " . $mail->ErrorInfo);
        return false;
    }
}

}
