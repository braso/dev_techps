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

function redirectTo(string $redirectTo, string $status, ?string $message = null): void {
    $sep = (strpos($redirectTo, "?") === false) ? "?" : "&";
    $url = $redirectTo . $sep . "status=" . urlencode($status);
    if($message !== null && $message !== ""){
        $url .= "&message=" . urlencode($message);
    }
    header("Location: {$url}");
    exit;
}

function ensureAssinaturaTables($conn): void {
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
            tipo_documento_id INT NULL,
            validar_icp ENUM('sim','nao') NOT NULL DEFAULT 'nao',
            data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            status ENUM('pendente', 'em_progresso', 'concluido', 'assinado') DEFAULT 'pendente',
            id_documento VARCHAR(100),
            data_assinatura DATETIME NULL
        )";
        mysqli_query($conn, $sqlCreateTable);
    } else {
        $cols = ['nome', 'nome_arquivo_original'];
        foreach ($cols as $col) {
            $check = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE '$col'");
            if (mysqli_num_rows($check) == 0) {
                mysqli_query($conn, "ALTER TABLE solicitacoes_assinatura ADD COLUMN $col VARCHAR(255)");
            }
        }
        $checkTipo = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'tipo_documento_id'");
        if(mysqli_num_rows($checkTipo) == 0){
            mysqli_query($conn, "ALTER TABLE solicitacoes_assinatura ADD COLUMN tipo_documento_id INT NULL");
        }

        $checkIcp = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'validar_icp'");
        if(mysqli_num_rows($checkIcp) == 0){
            mysqli_query($conn, "ALTER TABLE solicitacoes_assinatura ADD COLUMN validar_icp ENUM('sim','nao') NOT NULL DEFAULT 'nao'");
        }
    }

    $checkTableAssinantes = "SHOW TABLES LIKE 'assinantes'";
    $resultTableAssinantes = mysqli_query($conn, $checkTableAssinantes);
    if (mysqli_num_rows($resultTableAssinantes) == 0) {
        $sqlCreateTableAssinantes = "CREATE TABLE IF NOT EXISTS assinantes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_solicitacao INT NOT NULL,
            enti_nb_id INT NULL,
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
    } else {
        $checkEnti = mysqli_query($conn, "SHOW COLUMNS FROM assinantes LIKE 'enti_nb_id'");
        if ($checkEnti && mysqli_num_rows($checkEnti) == 0) {
            mysqli_query($conn, "ALTER TABLE assinantes ADD COLUMN enti_nb_id INT NULL AFTER id_solicitacao");
        }
    }
}

function contarPaginasPdf(string $path): int {
    if(extension_loaded("imagick")){
        try {
            $im = new Imagick();
            if(method_exists($im, "pingImage")){
                $im->pingImage($path);
            } else {
                $im->readImage($path);
            }
            $n = intval($im->getNumberImages());
            $im->clear();
            $im->destroy();
            return $n > 0 ? $n : 0;
        } catch (Throwable $e) {
        }
    }

    $content = @file_get_contents($path);
    if($content === false){
        return 0;
    }
    if(preg_match_all("/\\/Type\\s*\\/Page(?!s)/", $content, $m) === false){
        return 0;
    }
    return max(0, count($m[0] ?? []));
}

function isFunctionDisabled(string $fn): bool {
    $disabled = ini_get("disable_functions");
    if(!$disabled){
        return false;
    }
    $list = array_filter(array_map("trim", explode(",", $disabled)));
    return in_array($fn, $list, true);
}

function canExec(): bool {
    return function_exists("exec") && !isFunctionDisabled("exec");
}

function findCommand(array $candidates): ?string {
    if(!canExec()){
        return null;
    }

    $isWindows = (PHP_OS_FAMILY ?? "") === "Windows" || DIRECTORY_SEPARATOR === "\\";
    foreach($candidates as $cmd){
        $out = [];
        $code = 1;
        if($isWindows){
            @exec("where " . escapeshellarg($cmd), $out, $code);
        } else {
            @exec("command -v " . escapeshellarg($cmd) . " 2>/dev/null", $out, $code);
            if($code !== 0 || empty($out)){
                $out = [];
                $code = 1;
                @exec("which " . escapeshellarg($cmd) . " 2>/dev/null", $out, $code);
            }
        }
        if($code === 0 && !empty($out)){
            $path = trim(strval($out[0]));
            if($path !== ""){
                return $path;
            }
        }
    }
    return null;
}

function separarPaginaPdf(string $input, int $page, string $output): bool {
    if($page <= 0){
        return false;
    }

    if(extension_loaded("imagick")){
        try {
            $im = new Imagick();
            $im->setResolution(150, 150);
            $im->readImage($input . "[" . ($page - 1) . "]");
            $im->setImageFormat("pdf");
            $im->writeImage($output);
            $im->clear();
            $im->destroy();
            return file_exists($output);
        } catch (Throwable $e) {
        }
    }

    $qpdf = findCommand(["qpdf"]);
    if($qpdf){
        $cmd = escapeshellarg($qpdf) . " --empty --pages " . escapeshellarg($input) . " " . intval($page) . " -- " . escapeshellarg($output);
        $out = [];
        $code = 1;
        @exec($cmd, $out, $code);
        return $code === 0 && file_exists($output);
    }

    $gs = findCommand(["gswin64c", "gswin32c", "gs"]);
    if($gs){
        $cmd =
            escapeshellarg($gs)
            . " -sDEVICE=pdfwrite -dNOPAUSE -dBATCH"
            . " -dFirstPage=" . intval($page)
            . " -dLastPage=" . intval($page)
            . " -sOutputFile=" . escapeshellarg($output)
            . " " . escapeshellarg($input);
        $out = [];
        $code = 1;
        @exec($cmd, $out, $code);
        return $code === 0 && file_exists($output);
    }

    return false;
}

// Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirect = 'enviar_documento.php';
    redirectTo($redirect, "error", "Método inválido");
}

$redirect_to = $_POST['redirect_to'] ?? 'enviar_documento.php';
$modo_envio = $_POST["modo_envio"] ?? "governanca";

// Captura signatários do formulário (Array)
$signatarios = $_POST['signatarios'] ?? [];

// Validação básica
if (empty($signatarios) || !is_array($signatarios)) {
    // Tenta pegar do modo antigo (single signatário) para compatibilidade
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    
    if ($email && $nome) {
        $entiNbId = intval($_POST["enti_nb_id"] ?? 0);
        $signatarios = [
            [
                'nome' => $nome,
                'email' => $email,
                'funcao' => 'Signatário',
                'ordem' => 1,
                'enti_nb_id' => $entiNbId > 0 ? $entiNbId : null
            ]
        ];
    } else {
        $signatarios = [];
    }
}

if(!in_array($modo_envio, ["funcionarios", "separar_paginas"], true) && empty($signatarios)){
    redirectTo($redirect_to, "error", "Nenhum signatário informado");
}

if($modo_envio === "funcionarios"){
    ensureAssinaturaTables($conn);

    $arquivos = $_FILES["arquivos"] ?? null;
    if(!$arquivos || !isset($arquivos["name"]) || !is_array($arquivos["name"])){
        redirectTo($redirect_to, "error", "Nenhum arquivo enviado.");
    }

    $ids = $_POST["funcionarios"] ?? [];
    $ids = is_array($ids) ? $ids : [];
    $ids = array_values(array_unique(array_filter(array_map("intval", $ids), fn($v) => $v > 0)));
    if(empty($ids)){
        $keys = array_keys($arquivos["name"] ?? []);
        $ids = array_values(array_unique(array_filter(array_map("intval", $keys), fn($v) => $v > 0)));
    }
    if(empty($ids)){
        redirectTo($redirect_to, "error", "Nenhum funcionário selecionado.");
    }

    $uploadFileDir = './uploads/';
    if (!is_dir($uploadFileDir)) {
        mkdir($uploadFileDir, 0777, true);
    }

    $enviados = 0;
    $erros = 0;
    $ignorados = 0;

    foreach($ids as $idEntidade){
        $err = $arquivos["error"][$idEntidade] ?? UPLOAD_ERR_NO_FILE;
        if($err !== UPLOAD_ERR_OK){
            continue;
        }

        $tmp = $arquivos["tmp_name"][$idEntidade] ?? "";
        $original = $arquivos["name"][$idEntidade] ?? "";
        if($tmp === "" || $original === ""){
            $erros++;
            continue;
        }

        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if($ext !== "pdf"){
            $erros++;
            continue;
        }

        $funcionario = mysqli_fetch_assoc(query(
            "SELECT
                enti_nb_id,
                enti_tx_nome,
                enti_tx_email
            FROM entidade
            WHERE enti_nb_id = ?
            LIMIT 1",
            "i",
            [$idEntidade]
        ));

        if(empty($funcionario)){
            $erros++;
            continue;
        }

        $email = filter_var(trim(strval($funcionario["enti_tx_email"] ?? "")), FILTER_VALIDATE_EMAIL);
        $nome = trim(strval($funcionario["enti_tx_nome"] ?? ""));
        if(!$email || $nome === ""){
            $ignorados++;
            continue;
        }

        $newFileName = md5($idEntidade . "-" . microtime(true) . "-" . $original . "-" . bin2hex(random_bytes(8))) . ".pdf";
        $dest_path = $uploadFileDir . $newFileName;

        if(!move_uploaded_file($tmp, $dest_path)){
            $erros++;
            continue;
        }

        $tokenMestre = bin2hex(random_bytes(32));
        $id_documento = 'DOC-' . date('YmdHis') . '-' . uniqid();

        $sql = "INSERT INTO solicitacoes_assinatura (token, email, nome, caminho_arquivo, nome_arquivo_original, id_documento, status) VALUES (?, ?, ?, ?, ?, ?, 'pendente')";
        $stmt = mysqli_prepare($conn, $sql);
        if(!$stmt){
            @unlink($dest_path);
            $erros++;
            continue;
        }
        mysqli_stmt_bind_param($stmt, "ssssss", $tokenMestre, $email, $nome, $dest_path, $original, $id_documento);
        if(!mysqli_stmt_execute($stmt)){
            @unlink($dest_path);
            $erros++;
            continue;
        }

        $id_solicitacao = mysqli_insert_id($conn);
        $tokenAssinante = bin2hex(random_bytes(32));
        $funcao = "Funcionário";

        $sqlAssinante = "INSERT INTO assinantes (id_solicitacao, enti_nb_id, nome, email, funcao, ordem, token, status) VALUES (?, NULLIF(?,0), ?, ?, ?, 1, ?, 'pendente')";
        $stmtAssinante = mysqli_prepare($conn, $sqlAssinante);
        if(!$stmtAssinante){
            $erros++;
            continue;
        }
        mysqli_stmt_bind_param($stmtAssinante, "iissss", $id_solicitacao, $idEntidade, $nome, $email, $funcao, $tokenAssinante);
        if(!mysqli_stmt_execute($stmtAssinante)){
            $erros++;
            continue;
        }

        enviarEmailAssinatura($email, $nome, $tokenAssinante, $original, $id_documento, $funcao);
        $enviados++;
    }

    if($enviados <= 0){
        redirectTo($redirect_to, "error", "Nenhum documento foi enviado. Verifique os PDFs anexados e os e-mails dos funcionários.");
    }

    $msg = "Enviados: {$enviados}. Erros: {$erros}. Ignorados (sem e-mail): {$ignorados}.";
    redirectTo($redirect_to, "success", $msg);
}

if($modo_envio === "separar_paginas"){
    if(session_status() === PHP_SESSION_NONE){
        session_start();
    }
    $usuarioCadastro = intval($_SESSION["user_nb_id"] ?? 0);

    $token = trim(strval($_POST["pdf_token"] ?? ""));
    if($token === ""){
        redirectTo($redirect_to, "error", "PDF não encontrado.");
    }
    $tokens = $_SESSION["pdf_split_tokens"] ?? [];
    if(!is_array($tokens) || empty($tokens[$token]) || !is_array($tokens[$token])){
        redirectTo($redirect_to, "error", "Sessão do PDF expirou. Refaça o upload.");
    }

    $path = strval($tokens[$token]["path"] ?? "");
    $original = strval($tokens[$token]["name"] ?? "documento.pdf");
    $pagesStored = intval($tokens[$token]["pages"] ?? 0);

    $dirTmp = realpath(__DIR__ . "/uploads/tmp/");
    $real = $path !== "" ? realpath($path) : false;
    if(!$dirTmp || !$real || strpos($real, $dirTmp) !== 0 || !file_exists($real)){
        unset($_SESSION["pdf_split_tokens"][$token]);
        redirectTo($redirect_to, "error", "Arquivo temporário inválido.");
    }

    $pages = $pagesStored > 0 ? $pagesStored : contarPaginasPdf($real);
    if($pages <= 0){
        unset($_SESSION["pdf_split_tokens"][$token]);
        @unlink($real);
        redirectTo($redirect_to, "error", "Não foi possível identificar as páginas do PDF.");
    }

    $map = $_POST["page_funcionario"] ?? [];
    $map = is_array($map) ? $map : [];

    $documentoAssinar = strtolower(trim(strval($_POST["documento_assinar"] ?? "sim")));
    $documentoAssinar = in_array($documentoAssinar, ["sim", "nao"], true) ? $documentoAssinar : "sim";

    $validarIcp = strtolower(trim(strval($_POST["validar_icp"] ?? "nao")));
    $validarIcp = $validarIcp === "sim" ? "sim" : "nao";
    if($documentoAssinar !== "sim"){
        $validarIcp = "nao";
    }

    $tipoDocumentoId = intval($_POST["tipo_documento"] ?? 0);
    if($tipoDocumentoId <= 0){
        redirectTo($redirect_to, "error", "Selecione o tipo de documento.");
    }

    $tipoDocumento = mysqli_fetch_assoc(query(
        "SELECT tipo_nb_id, tipo_tx_nome, tipo_tx_status FROM tipos_documentos WHERE tipo_nb_id = ? LIMIT 1",
        "i",
        [$tipoDocumentoId]
    ));
    if(empty($tipoDocumento)){
        redirectTo($redirect_to, "error", "Tipo de documento não encontrado.");
    }
    $tipoStatus = strtolower(trim(strval($tipoDocumento["tipo_tx_status"] ?? "")));
    if($tipoStatus !== "ativo"){
        redirectTo($redirect_to, "error", "Tipo de documento inativo.");
    }
    $tipoNome = trim(strval($tipoDocumento["tipo_tx_nome"] ?? ""));

    $uploadFileDir = './uploads/';
    if($documentoAssinar === "sim"){
        ensureAssinaturaTables($conn);
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0777, true);
        }
    }

    $enviados = 0;
    $erros = 0;
    $ignorados = 0;
    $agora = date("Y-m-d H:i:s");

    for($p = 1; $p <= $pages; $p++){
        $idEntidade = intval($map[$p] ?? 0);
        if($idEntidade <= 0){
            continue;
        }

        $funcionario = mysqli_fetch_assoc(query(
            "SELECT enti_nb_id, enti_tx_nome, enti_tx_email FROM entidade WHERE enti_nb_id = ? LIMIT 1",
            "i",
            [$idEntidade]
        ));
        if(empty($funcionario)){
            $erros++;
            continue;
        }

        $email = filter_var(trim(strval($funcionario["enti_tx_email"] ?? "")), FILTER_VALIDATE_EMAIL);
        $nome = trim(strval($funcionario["enti_tx_nome"] ?? ""));
        if($nome === ""){
            $erros++;
            continue;
        }
        if($documentoAssinar === "sim" && !$email){
            $ignorados++;
            continue;
        }

        $base = pathinfo($original, PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '_', strval($base));
        $nomeArquivo = ($safeBase !== "" ? $safeBase : "documento") . "_p" . str_pad((string)$p, 3, "0", STR_PAD_LEFT) . ".pdf";

        if($documentoAssinar === "sim"){
            $newFileName = md5($token . "-" . $idEntidade . "-" . $p . "-" . microtime(true) . "-" . bin2hex(random_bytes(8))) . ".pdf";
            $dest_path = $uploadFileDir . $newFileName;

            if(!separarPaginaPdf($real, $p, $dest_path)){
                $erros++;
                continue;
            }

            $tokenMestre = bin2hex(random_bytes(32));
            $id_documento = 'DOC-' . date('YmdHis') . '-' . uniqid();

            $sql = "INSERT INTO solicitacoes_assinatura (token, email, nome, caminho_arquivo, nome_arquivo_original, id_documento, tipo_documento_id, validar_icp, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente')";
            $stmt = mysqli_prepare($conn, $sql);
            if(!$stmt){
                @unlink($dest_path);
                $erros++;
                continue;
            }
            mysqli_stmt_bind_param($stmt, "ssssssis", $tokenMestre, $email, $nome, $dest_path, $nomeArquivo, $id_documento, $tipoDocumentoId, $validarIcp);
            if(!mysqli_stmt_execute($stmt)){
                @unlink($dest_path);
                $erros++;
                continue;
            }

            $id_solicitacao = mysqli_insert_id($conn);
            $tokenAssinante = bin2hex(random_bytes(32));
            $funcao = "Funcionário";

            $sqlAssinante = "INSERT INTO assinantes (id_solicitacao, nome, email, funcao, ordem, token, status)
                VALUES (?, ?, ?, ?, 1, ?, 'pendente')";
            $stmtAssinante = mysqli_prepare($conn, $sqlAssinante);
            if(!$stmtAssinante){
                $erros++;
                continue;
            }
            mysqli_stmt_bind_param($stmtAssinante, "issss", $id_solicitacao, $nome, $email, $funcao, $tokenAssinante);
            if(!mysqli_stmt_execute($stmtAssinante)){
                $erros++;
                continue;
            }

            enviarEmailAssinatura($email, $nome, $tokenAssinante, $nomeArquivo, $id_documento, $funcao);
            $enviados++;
            continue;
        }

        $root = realpath(__DIR__ . "/..");
        if(!$root){
            $root = dirname(__DIR__);
        }

        $nomeSeguro = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '_', $nomeArquivo);
        $pastaRel = "arquivos/Funcionarios/" . $idEntidade . "/";
        $pastaAbs = rtrim($root, "/\\") . DIRECTORY_SEPARATOR . "arquivos" . DIRECTORY_SEPARATOR . "Funcionarios" . DIRECTORY_SEPARATOR . $idEntidade . DIRECTORY_SEPARATOR;
        if(!is_dir($pastaAbs)){
            mkdir($pastaAbs, 0777, true);
        }
        $destRel = $pastaRel . $nomeSeguro;
        $destAbs = $pastaAbs . $nomeSeguro;
        if(file_exists($destAbs)){
            $info = pathinfo($nomeSeguro);
            $baseName = $info["filename"] ?? "documento";
            $ext = isset($info["extension"]) ? "." . $info["extension"] : "";
            $nomeSeguro = $baseName . "_" . time() . $ext;
            $destRel = $pastaRel . $nomeSeguro;
            $destAbs = $pastaAbs . $nomeSeguro;
        }

        if(!separarPaginaPdf($real, $p, $destAbs)){
            $erros++;
            continue;
        }

        $docNome = $tipoNome !== "" ? ($tipoNome . " - " . ($safeBase !== "" ? $safeBase : "documento") . " p" . str_pad((string)$p, 3, "0", STR_PAD_LEFT)) : pathinfo($nomeSeguro, PATHINFO_FILENAME);

        $okInsert = query(
            "INSERT INTO documento_funcionario
                (docu_nb_entidade, docu_tx_nome, docu_tx_descricao, docu_tx_dataCadastro, docu_tx_dataVencimento, docu_tx_tipo, docu_nb_sbgrupo, docu_tx_usuarioCadastro, docu_tx_assinado, docu_tx_visivel, docu_tx_caminho)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 'nao', ?, ?)",
            "issssiiiss",
            [
                $idEntidade,
                $docNome,
                "",
                $agora,
                null,
                $tipoDocumentoId,
                0,
                $usuarioCadastro,
                "sim",
                $destRel
            ]
        );
        if(!$okInsert){
            @unlink($destAbs);
            $erros++;
            continue;
        }
        $enviados++;
    }

    unset($_SESSION["pdf_split_tokens"][$token]);
    @unlink($real);
    $dirTmpClean = realpath(__DIR__ . "/uploads/tmp/");
    if($dirTmpClean){
        for($p = 1; $p <= $pages; $p++){
            $thumb = rtrim($dirTmpClean, "/\\") . DIRECTORY_SEPARATOR . $token . "_p" . $p . ".jpg";
            if(file_exists($thumb)){
                @unlink($thumb);
            }
        }
    }

    if($enviados <= 0){
        $msg =
            $documentoAssinar === "nao"
                ? "Nenhuma página foi enviada. Verifique se você selecionou os funcionários e se o servidor tem suporte para separar PDF (Imagick, qpdf ou Ghostscript)."
                : "Nenhuma página foi enviada. Verifique se você selecionou os funcionários, se eles têm e-mail e se o servidor tem suporte para separar PDF (Imagick, qpdf ou Ghostscript).";
        redirectTo($redirect_to, "error", $msg);
    }

    $msg =
        $documentoAssinar === "nao"
            ? "Enviados: {$enviados}. Erros: {$erros}."
            : "Enviados: {$enviados}. Erros: {$erros}. Ignorados (sem e-mail): {$ignorados}.";
    redirectTo($redirect_to, "success", $msg);
}

// Verifica upload do arquivo
if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    redirectTo($redirect_to, "error", "Erro no upload do arquivo");
}

$fileTmpPath = $_FILES['arquivo']['tmp_name'];
$fileName = $_FILES['arquivo']['name'];
$fileSize = $_FILES['arquivo']['size'];
$fileType = $_FILES['arquivo']['type'];
$fileNameCmps = explode(".", $fileName);
$fileExtension = strtolower(end($fileNameCmps));

// Apenas PDF
if ($fileExtension !== 'pdf') {
    redirectTo($redirect_to, "error", "Apenas arquivos PDF são permitidos");
}

// Cria diretório de uploads se não existir
$uploadFileDir = './uploads/';
if (!is_dir($uploadFileDir)) {
    mkdir($uploadFileDir, 0777, true);
}

ensureAssinaturaTables($conn);

$tipoDocumentoId = intval($_POST["tipo_documento"] ?? 0);
$validarIcp = strtolower(trim(strval($_POST["validar_icp"] ?? "nao")));
$validarIcp = $validarIcp === "sim" ? "sim" : "nao";

if($modo_envio === "avulso" && $tipoDocumentoId <= 0){
    redirectTo($redirect_to, "error", "Selecione o tipo de documento.");
}
if($tipoDocumentoId > 0){
    $tipoDocumento = mysqli_fetch_assoc(query(
        "SELECT tipo_nb_id, tipo_tx_status FROM tipos_documentos WHERE tipo_nb_id = ? LIMIT 1",
        "i",
        [$tipoDocumentoId]
    ));
    if(empty($tipoDocumento)){
        redirectTo($redirect_to, "error", "Tipo de documento não encontrado.");
    }
    if(strtolower(trim(strval($tipoDocumento["tipo_tx_status"] ?? ""))) !== "ativo"){
        redirectTo($redirect_to, "error", "Tipo de documento inativo.");
    }
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
    
    $sql = "INSERT INTO solicitacoes_assinatura (token, email, nome, caminho_arquivo, nome_arquivo_original, id_documento, tipo_documento_id, validar_icp, status)
        VALUES (?, ?, ?, ?, ?, ?, NULLIF(?,0), ?, 'pendente')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssssis", $tokenMestre, $primeiroSignatario['email'], $primeiroSignatario['nome'], $dest_path, $fileName, $id_documento, $tipoDocumentoId, $validarIcp);
    
    if (mysqli_stmt_execute($stmt)) {
        $id_solicitacao = mysqli_insert_id($conn);
        
        // 2. Insere os Assinantes
        foreach ($signatarios as $index => $sig) {
            $tokenAssinante = bin2hex(random_bytes(32));
            $ordem = $sig['ordem'] ?? ($index + 1);
            $funcao = $sig['funcao'] ?? 'Signatário';
            $entiNbId = intval($sig["enti_nb_id"] ?? 0);
            
            $sqlAssinante = "INSERT INTO assinantes (id_solicitacao, enti_nb_id, nome, email, funcao, ordem, token, status) VALUES (?, NULLIF(?,0), ?, ?, ?, ?, ?, 'pendente')";
            $stmtAssinante = mysqli_prepare($conn, $sqlAssinante);
            mysqli_stmt_bind_param($stmtAssinante, "iisssis", $id_solicitacao, $entiNbId, $sig['nome'], $sig['email'], $funcao, $ordem, $tokenAssinante);
            mysqli_stmt_execute($stmtAssinante);
            
            // Se for o primeiro (ordem 1), enviamos o e-mail agora
            if ($ordem == 1) {
                enviarEmailAssinatura($sig['email'], $sig['nome'], $tokenAssinante, $fileName, $id_documento, $funcao);
            }
        }
        
        redirectTo($redirect_to, "success");
        
    } else {
        redirectTo($redirect_to, "error", "Erro ao criar solicitação: " . mysqli_error($conn));
    }

} else {
    redirectTo($redirect_to, "error", "Erro ao salvar arquivo");
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
