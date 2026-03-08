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
    header("Location: enviar_documento.php?status=error&message=Método inválido");
    exit;
}

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
        header("Location: enviar_documento.php?status=error&message=Nenhum signatário informado");
        exit;
    }
}

// Verifica upload do arquivo
if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    header("Location: enviar_documento.php?status=error&message=Erro no upload do arquivo");
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
    header("Location: enviar_documento.php?status=error&message=Apenas arquivos PDF são permitidos");
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
        
        header("Location: enviar_documento.php?status=success");
        exit;
        
    } else {
        header("Location: enviar_documento.php?status=error&message=Erro ao criar solicitação: " . mysqli_error($conn));
        exit;
    }

} else {
    header("Location: enviar_documento.php?status=error&message=Erro ao salvar arquivo");
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
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #eee; padding: 20px;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                " . ($cidLogo ? "<img src='$cidLogo' alt='TechPS' style='max-width: 150px;'>" : "<h2>TechPS</h2>") . "
            </div>
            <h3>Olá, $nome.</h3>
            <p>Você foi indicado como <strong>$funcao</strong> para assinar o documento abaixo:</p>
            <div style='background: #f8f9fa; padding: 15px; margin: 15px 0;'>
                <strong>Documento:</strong> $nomeArquivo<br>
                <strong>ID:</strong> $idDoc
            </div>
            <p style='text-align: center;'>
                <a href='$linkAssinatura' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>Revisar e Assinar Agora</a>
            </p>
            <p style='font-size: 12px; color: #999; margin-top: 30px;'>
                Se o botão não funcionar, copie e cole: $linkAssinatura
            </p>
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