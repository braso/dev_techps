<?php
// DEBUG: Ativar exibição de erros temporariamente
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$interno = true; // Evita redirecionamento de sessão no conecta.php

// Configurações e Conexão
include __DIR__ . "/../conecta.php";
include __DIR__ . "/email_config.php";

// Carrega bibliotecas do PHPMailer manualmente (sem Composer)
require __DIR__ . '/../../PHPMailer/src/Exception.php';
require __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirect = $_POST['redirect_to'] ?? 'enviar_documento.php';
    header("Location: $redirect?status=error&message=Método inválido");
    exit;
}

$redirect_to = $_POST['redirect_to'] ?? 'enviar_documento.php';

// Captura signatários do formulário (Array)
$signatarios = $_POST['signatarios'] ?? [];

// Validação básica
if (empty($signatarios) || !is_array($signatarios)) {
    // Tenta pegar do modo antigo (single signatário) para compatibilidade
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    
    if ($email && $nome) {
        $signatarios = [
            [
                'nome' => $nome,
                'email' => $email,
                'funcao' => 'Signatário',
                'ordem' => 1
            ]
        ];
    } else {
        header("Location: $redirect_to?status=error&message=Nenhum signatário informado");
        exit;
    }
}

// Verifica upload do arquivo
if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    header("Location: $redirect_to?status=error&message=Erro no upload do arquivo");
    exit;
}

$fileTmpPath = $_FILES['arquivo']['tmp_name'];
$fileName = $_FILES['arquivo']['name'];
$fileSize = $_FILES['arquivo']['size'];
$fileType = $_FILES['arquivo']['type'];
$fileNameCmps = explode(".", $fileName);
$fileExtension = strtolower(end($fileNameCmps));

// Apenas PDF
if ($fileExtension !== 'pdf') {
    header("Location: $redirect_to?status=error&message=Apenas arquivos PDF são permitidos");
    exit;
}

// Cria diretório de uploads se não existir
$uploadFileDir = './uploads/';
if (!is_dir($uploadFileDir)) {
    mkdir($uploadFileDir, 0777, true);
}

// Verifica tabelas (Self-Healing)
// 1. Tabela Principal (Processo)
$checkTable = "SHOW TABLES LIKE 'solicitacoes_assinatura'";
$resultTable = mysqli_query($conn, $checkTable);
if (mysqli_num_rows($resultTable) == 0) {
    $sqlCreateTable = "CREATE TABLE IF NOT EXISTS solicitacoes_assinatura (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token VARCHAR(64) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL,
        nome VARCHAR(255),
        caminho_arquivo VARCHAR(255) NOT NULL,
        nome_arquivo_original VARCHAR(255),
        data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pendente', 'em_progresso', 'concluido', 'assinado') DEFAULT 'pendente',
        id_documento VARCHAR(100),
        data_assinatura DATETIME NULL
    )";
    mysqli_query($conn, $sqlCreateTable);
} else {
    // Garante colunas novas
    $cols = ['nome', 'nome_arquivo_original'];
    foreach ($cols as $col) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE '$col'");
        if (mysqli_num_rows($check) == 0) {
            mysqli_query($conn, "ALTER TABLE solicitacoes_assinatura ADD COLUMN $col VARCHAR(255)");
        }
    }
    // Atualiza ENUM de status se necessário (difícil via SQL puro sem procedure, vamos assumir compatibilidade)
}

// 2. Tabela de Assinantes (Múltiplos)
$checkTableAssinantes = "SHOW TABLES LIKE 'assinantes'";
$resultTableAssinantes = mysqli_query($conn, $checkTableAssinantes);
if (mysqli_num_rows($resultTableAssinantes) == 0) {
    $sqlCreateTableAssinantes = "CREATE TABLE IF NOT EXISTS assinantes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_solicitacao INT NOT NULL,
        nome VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        cpf VARCHAR(20),
        funcao VARCHAR(100),
        ordem INT NOT NULL DEFAULT 1,
        token VARCHAR(64) NOT NULL UNIQUE,
        status ENUM('pendente', 'assinado') DEFAULT 'pendente',
        data_assinatura DATETIME NULL,
        ip VARCHAR(45),
        metadados TEXT,
        INDEX (id_solicitacao),
        INDEX (token)
    )";
    mysqli_query($conn, $sqlCreateTableAssinantes);
}

// Gera nome único para o arquivo físico
$newFileName = md5(time() . $fileName) . '.' . $fileExtension;
$dest_path = $uploadFileDir . $newFileName;

if(move_uploaded_file($fileTmpPath, $dest_path)) {
    
    // Inicia Transação (Idealmente)
    
    // 1. Cria a Solicitação (Processo Pai)
    // Usamos os dados do primeiro signatário como referência principal
    $primeiroSignatario = $signatarios[0];
    $tokenMestre = bin2hex(random_bytes(32)); 
    $id_documento = 'DOC-' . date('YmdHis') . '-' . uniqid();
    
    $sql = "INSERT INTO solicitacoes_assinatura (token, email, nome, caminho_arquivo, nome_arquivo_original, id_documento, status) VALUES (?, ?, ?, ?, ?, ?, 'pendente')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssss", $tokenMestre, $primeiroSignatario['email'], $primeiroSignatario['nome'], $dest_path, $fileName, $id_documento);
    
    if (mysqli_stmt_execute($stmt)) {
        $id_solicitacao = mysqli_insert_id($conn);
        
        // 2. Insere os Assinantes
        foreach ($signatarios as $index => $sig) {
            $tokenAssinante = bin2hex(random_bytes(32));
            $ordem = $sig['ordem'] ?? ($index + 1);
            $funcao = $sig['funcao'] ?? 'Signatário';
            
            $sqlAssinante = "INSERT INTO assinantes (id_solicitacao, nome, email, funcao, ordem, token, status) VALUES (?, ?, ?, ?, ?, ?, 'pendente')";
            $stmtAssinante = mysqli_prepare($conn, $sqlAssinante);
            mysqli_stmt_bind_param($stmtAssinante, "isssis", $id_solicitacao, $sig['nome'], $sig['email'], $funcao, $ordem, $tokenAssinante);
            mysqli_stmt_execute($stmtAssinante);
            
            // Se for o primeiro (ordem 1), enviamos o e-mail agora
            if ($ordem == 1) {
                enviarEmailAssinatura($sig['email'], $sig['nome'], $tokenAssinante, $fileName, $id_documento, $funcao);
            }
        }
        
        header("Location: $redirect_to?status=success");
        exit;
        
    } else {
        header("Location: $redirect_to?status=error&message=Erro ao criar solicitação: " . mysqli_error($conn));
        exit;
    }

} else {
    header("Location: $redirect_to?status=error&message=Erro ao salvar arquivo");
    exit;
}

// Função Auxiliar de Envio de E-mail
function enviarEmailAssinatura($email, $nome, $token, $nomeArquivo, $idDoc, $funcao) {
    global $mail; // Usa a instância global ou cria nova se necessário (melhor criar nova)
    
    $linkAssinatura = BASE_URL_ASSINATURA . "/assinar_via_link.php?token=" . $token;
    
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
                
                <div style='border-top: 1px solid #eee; margin-top: 30px; padding-top: 20px;'>
                    <p style='font-size: 13px; color: #777; margin-bottom: 10px;'>
                        Se o botão não funcionar, copie e cole o link abaixo no seu navegador:
                    </p>
                    <p style='font-size: 12px; color: #555; background: #f8f9fa; padding: 10px; border-radius: 4px; word-break: break-all; font-family: monospace; border: 1px solid #eee;'>
                        $linkAssinatura
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
            </div>
            
            <div style='background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; border-radius: 0 0 8px 8px;'>
                <p style='margin: 0;'>Mensagem automática enviada pelo sistema de Assinatura Digital TechPS.</p>
                <p style='margin: 5px 0 0 0;'>&copy; " . date('Y') . " Armazém Paraíba - Todos os direitos reservados.</p>
            </div>
        </div>";

        $mail->Body = $corpo;
        $mail->AltBody = "Acesse $linkAssinatura para assinar o documento #$idDoc como $funcao.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erro ao enviar email para $email: " . $mail->ErrorInfo);
        return false;
    }
}
?>