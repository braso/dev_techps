<?php
use setasign\Fpdi\Tcpdf\Fpdi;

function assinatura_normalizarCpf(string $cpf): string {
    return preg_replace('/\D+/', '', $cpf);
}

function assinatura_sanitizarNomeArquivo(string $nome): string {
    $base = basename($nome);
    $safe = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '_', strval($base));
    $safe = trim(strval($safe));
    return $safe !== "" ? $safe : "documento.pdf";
}

function assinatura_obterEntidadeIdPorCpfOuEmail(mysqli $conn, string $cpf, string $emailAssinante, string $emailSolicitacao): int {
    $cpfDigits = assinatura_normalizarCpf($cpf);
    if ($cpfDigits !== "") {
        $sqlCpf = "SELECT enti_nb_id FROM entidade WHERE REPLACE(REPLACE(REPLACE(enti_tx_cpf, '.', ''), '-', ''), ' ', '') = ? LIMIT 1";
        $stmtCpf = mysqli_prepare($conn, $sqlCpf);
        if ($stmtCpf) {
            mysqli_stmt_bind_param($stmtCpf, "s", $cpfDigits);
            mysqli_stmt_execute($stmtCpf);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCpf));
            mysqli_stmt_close($stmtCpf);
            $id = intval($row["enti_nb_id"] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        $sqlCpfNum = "SELECT enti_nb_id FROM entidade WHERE CAST(REPLACE(REPLACE(REPLACE(enti_tx_cpf, '.', ''), '-', ''), ' ', '') AS UNSIGNED) = CAST(? AS UNSIGNED) LIMIT 1";
        $stmtCpfNum = mysqli_prepare($conn, $sqlCpfNum);
        if ($stmtCpfNum) {
            mysqli_stmt_bind_param($stmtCpfNum, "s", $cpfDigits);
            mysqli_stmt_execute($stmtCpfNum);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCpfNum));
            mysqli_stmt_close($stmtCpfNum);
            $id = intval($row["enti_nb_id"] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }
    }

    $emails = [];
    foreach ([$emailAssinante, $emailSolicitacao] as $em) {
        $em = strtolower(trim(strval($em)));
        if ($em !== "" && filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $em;
        }
    }
    $emails = array_values(array_unique($emails));
    foreach ($emails as $em) {
        $stmt = mysqli_prepare($conn, "SELECT enti_nb_id FROM entidade WHERE LOWER(TRIM(enti_tx_email)) = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $em);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            $id = intval($row["enti_nb_id"] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }
    }

    return 0;
}

function assinatura_obterEntidadeIdDaSolicitacao(mysqli $conn, int $idSolicitacao, array $docRow): int {
    $stmt = mysqli_prepare($conn, "SELECT enti_nb_id, cpf, email FROM assinantes WHERE id_solicitacao = ? ORDER BY ordem DESC, id DESC LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $idSolicitacao);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $enti = intval($row["enti_nb_id"] ?? 0);
        if ($enti > 0) {
            return $enti;
        }
        $cpf = trim(strval($row["cpf"] ?? ""));
        $emailAss = trim(strval($row["email"] ?? ""));
        $emailSol = trim(strval($docRow["email"] ?? ""));
        $id = assinatura_obterEntidadeIdPorCpfOuEmail($conn, $cpf, $emailAss, $emailSol);
        if ($id > 0) {
            return $id;
        }
    }

    $cpf = trim(strval($docRow["cpf"] ?? ""));
    $emailSol = trim(strval($docRow["email"] ?? ""));
    return assinatura_obterEntidadeIdPorCpfOuEmail($conn, $cpf, "", $emailSol);
}

function assinatura_inserirDocumentoFuncionario(mysqli $conn, int $entidadeId, int $tipoDocumentoId, string $nomeArquivo, string $caminhoRelativo): void {
    if ($entidadeId <= 0 || $caminhoRelativo === "") {
        return;
    }

    $nome = trim(strval(pathinfo($nomeArquivo, PATHINFO_FILENAME)));
    if ($nome === "") {
        $nome = "Documento assinado";
    }

    $descricao = "Documento assinado eletronicamente.";
    $dataCadastro = date("Y-m-d H:i:s");
    $usuarioCadastro = 0;

    $sql =
        "INSERT INTO documento_funcionario
            (docu_nb_entidade, docu_tx_nome, docu_tx_descricao, docu_tx_dataCadastro, docu_tx_dataVencimento, docu_tx_tipo, docu_nb_sbgrupo, docu_tx_usuarioCadastro, docu_tx_assinado, docu_tx_visivel, docu_tx_caminho)
        VALUES
            (?, ?, ?, ?, NULL, NULLIF(?,0), NULL, ?, 'sim', 'sim', ?)";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return;
    }

    mysqli_stmt_bind_param($stmt, "isssiis", $entidadeId, $nome, $descricao, $dataCadastro, $tipoDocumentoId, $usuarioCadastro, $caminhoRelativo);
    @mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function assinatura_finalizar_documento_icp($conn, int $idDocumento, array $opts = []): array {
    $baseDir = __DIR__;
    $sendEmail = array_key_exists("send_email", $opts) ? (bool)$opts["send_email"] : true;
    $pfxPasswordInput = strval($opts["pfx_password"] ?? "");
    $pfxFile = $opts["pfx_file"] ?? null;

    if ($idDocumento <= 0) {
        return ["ok" => false, "error" => "ID do documento inválido."];
    }

    $pfxConfig = [];
    if (file_exists($baseDir . "/config_pfx.php")) {
        $tmp = include($baseDir . "/config_pfx.php");
        if (is_array($tmp)) {
            $pfxConfig = $tmp;
        }
    }

    $checkCol = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'status_final'");
    if ($checkCol && mysqli_num_rows($checkCol) == 0) {
        mysqli_query($conn, "ALTER TABLE solicitacoes_assinatura ADD COLUMN status_final VARCHAR(50) DEFAULT 'pendente'");
    }

    $stmt = mysqli_prepare($conn, "SELECT * FROM solicitacoes_assinatura WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return ["ok" => false, "error" => "Falha ao preparar consulta do documento."];
    }
    mysqli_stmt_bind_param($stmt, "i", $idDocumento);
    mysqli_stmt_execute($stmt);
    $doc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$doc) {
        return ["ok" => false, "error" => "Documento não encontrado."];
    }

    $statusFinal = strtolower(trim(strval($doc["status_final"] ?? "")));
    $caminhoAtual = strval($doc["caminho_arquivo"] ?? "");
    $alreadyFinalized = false;
    $existingAbs = "";
    if ($statusFinal === "finalizado" && $caminhoAtual !== "") {
        $abs = $baseDir . "/" . ltrim($caminhoAtual, "/\\");
        if (file_exists($abs)) {
            $alreadyFinalized = true;
            $existingAbs = $abs;
        }
    }

    $pfxPathToUse = null;
    $pfxPasswordToUse = null;
    $isTempFile = false;

    if (!$alreadyFinalized && is_array($pfxFile) && (($pfxFile["error"] ?? null) === UPLOAD_ERR_OK)) {
        $pfxPasswordToUse = $pfxPasswordInput;
        if ($pfxPasswordToUse === "") {
            return ["ok" => false, "error" => "Senha do certificado é obrigatória."];
        }

        $pfxTmpName = strval($pfxFile["tmp_name"] ?? "");
        $pfxExt = pathinfo(strval($pfxFile["name"] ?? ""), PATHINFO_EXTENSION);
        $pfxPathToUse = $baseDir . "/temp_cert_" . uniqid() . ($pfxExt !== "" ? "." . $pfxExt : "");

        if ($pfxTmpName === "" || !is_uploaded_file($pfxTmpName) || !move_uploaded_file($pfxTmpName, $pfxPathToUse)) {
            return ["ok" => false, "error" => "Erro ao salvar certificado temporário."];
        }
        $isTempFile = true;
    } elseif (!$alreadyFinalized && !empty($pfxConfig["auto_sign"]) && !empty($pfxConfig["pfx_path"]) && file_exists(strval($pfxConfig["pfx_path"]))) {
        $pfxPathToUse = strval($pfxConfig["pfx_path"]);
        $pfxPasswordToUse = $pfxPasswordInput !== "" ? $pfxPasswordInput : strval($pfxConfig["pfx_password"] ?? "");
        if ($pfxPasswordToUse === "") {
            return ["ok" => false, "error" => "Senha do certificado não configurada e não fornecida."];
        }
    } elseif (!$alreadyFinalized) {
        return ["ok" => false, "error" => "Nenhum certificado fornecido ou configurado."];
    }

    $opensslConf = "C:/xampp/apache/conf/openssl.cnf";
    if (file_exists($opensslConf)) {
        putenv("OPENSSL_CONF=$opensslConf");
    }

    $inputPdfPath = $baseDir . "/" . ltrim($caminhoAtual, "/\\");
    if (!$alreadyFinalized && !file_exists($inputPdfPath)) {
        if ($isTempFile && $pfxPathToUse && file_exists($pfxPathToUse)) {
            @unlink($pfxPathToUse);
        }
        return ["ok" => false, "error" => "Arquivo PDF original não encontrado no servidor."];
    }

    $tcpdfCandidates = [
        $baseDir . "/vendor/tecnickcom/tcpdf/tcpdf.php",
        $baseDir . "/../tcpdf/tcpdf.php"
    ];
    $tcpdfPath = "";
    foreach ($tcpdfCandidates as $cand) {
        if (file_exists($cand)) {
            $tcpdfPath = $cand;
            break;
        }
    }
    if ($tcpdfPath === "") {
        if ($isTempFile && $pfxPathToUse && file_exists($pfxPathToUse)) {
            @unlink($pfxPathToUse);
        }
        return ["ok" => false, "error" => "Biblioteca TCPDF não encontrada no servidor."];
    }
    require_once $tcpdfPath;

    spl_autoload_register(function ($class) use ($baseDir) {
        if (strpos($class, "setasign\\Fpdi\\") === 0) {
            $filename = str_replace("\\", "/", substr($class, 14)) . ".php";
            $fullpath = $baseDir . "/vendor/setasign/fpdi/src/" . $filename;
            if (file_exists($fullpath)) {
                require_once $fullpath;
            }
        }
    });

    if (!$alreadyFinalized && !class_exists("setasign\\Fpdi\\Tcpdf\\Fpdi")) {
        if ($isTempFile && $pfxPathToUse && file_exists($pfxPathToUse)) {
            @unlink($pfxPathToUse);
        }
        return ["ok" => false, "error" => "Biblioteca FPDI não encontrada no servidor."];
    }

    $outputDir = $baseDir . "/docFinalizado/";
    if (!is_dir($outputDir)) {
        @mkdir($outputDir, 0777, true);
    }

    $outputFileName = "final_" . time() . "_" . uniqid() . ".pdf";
    $outputPdfPath = $outputDir . $outputFileName;

    if (!$alreadyFinalized) {
        try {
        $certStore = [];
        $pfxContent = @file_get_contents($pfxPathToUse);
        if ($pfxContent === false || !openssl_pkcs12_read($pfxContent, $certStore, $pfxPasswordToUse)) {
            throw new Exception("Não foi possível ler o certificado PFX. Verifique se a senha está correta.");
        }

        $cert = $certStore["cert"] ?? null;
        $pkey = $certStore["pkey"] ?? null;
        if (!$cert || !$pkey) {
            throw new Exception("Certificado inválido.");
        }

        $pdf = new Fpdi();
        $pdf->SetCreator("TechPS Assinador");
        $pdf->SetAuthor("TechPS");
        $pdf->SetTitle("Documento Assinado Digitalmente");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pageCount = $pdf->setSourceFile($inputPdfPath);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplIdx = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplIdx);
            $orientation = ($size["width"] > $size["height"]) ? "L" : "P";
            $pdf->AddPage($orientation, [$size["width"], $size["height"]]);
            $pdf->useTemplate($tplIdx);
        }

        $info = [
            "Name" => "Assinatura Digital ICP-Brasil",
            "Location" => "Sistema TechPS",
            "Reason" => "Garantia de Autenticidade e Integridade",
            "ContactInfo" => "suporte@techps.com.br",
        ];
        $pdf->setSignature($cert, $pkey, $pfxPasswordToUse, "", 2, $info);

        $certData = openssl_x509_parse($certStore["cert"]);
        $subject = $certData["subject"] ?? [];
        $issuer = $certData["issuer"] ?? [];

        $signerName = $subject["CN"] ?? ($subject["O"] ?? "Signatário Desconhecido");
        if (is_array($signerName)) {
            $signerName = implode(", ", $signerName);
        }
        $issuerName = $issuer["CN"] ?? ($issuer["O"] ?? "Autoridade Certificadora");
        if (is_array($issuerName)) {
            $issuerName = implode(", ", $issuerName);
        }

        $signDate = date("d/m/Y H:i:s");
        $docHash = hash_file("sha256", $inputPdfPath);
        $shortHash = substr($docHash, 0, 20) . "...";
        $verificationCode = strtoupper(substr(md5($outputFileName . $signDate . $idDocumento), 0, 16));

        $w = 120;
        $h = 36;
        $margin = 10;

        $pdf->SetAutoPageBreak(false);

        $x = $pdf->getPageWidth() - $w - $margin;
        $y = $pdf->getPageHeight() - $h - $margin;

        $pdf->SetAlpha(1);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetLineStyle(["width" => 0.2, "cap" => "butt", "join" => "miter", "dash" => 0, "color" => [0, 0, 0]]);
        $pdf->RoundedRect($x, $y, $w, $h, 2, "1111", "DF");

        $logoPath = $baseDir . "/assets/icp.png";
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, $x + 2, $y + 4, 18, 0, "PNG");
        }

        $textX = $x + 30;
        $currentY = $y + 3;

        $pdf->SetFont("helvetica", "B", 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($textX, $currentY);
        $pdf->Cell(0, 0, "ASSINADO DIGITALMENTE", 0, 1, "L");

        $currentY += 4;
        $pdf->SetFont("helvetica", "B", 8);
        $pdf->SetXY($textX, $currentY);
        $pdf->Cell($w - 32, 0, substr(strtoupper(strval($signerName)), 0, 45), 0, 1, "L");

        $currentY += 4;
        $pdf->SetFont("helvetica", "", 6);
        $pdf->SetXY($textX, $currentY);
        $pdf->Cell($w - 32, 0, "Data: " . $signDate, 0, 1, "L");

        $currentY += 3;
        $pdf->SetXY($textX, $currentY);
        $pdf->Cell($w - 32, 0, "Emissor: " . substr(strval($issuerName), 0, 50), 0, 1, "L");

        $currentY += 3;
        $pdf->SetXY($textX, $currentY);
        $pdf->Cell($w - 32, 0, "Hash Doc: " . $shortHash, 0, 1, "L");

        $currentY += 3;
        $pdf->SetFont("helvetica", "B", 6);
        $pdf->SetXY($textX, $currentY);
        $pdf->Cell($w - 32, 0, "Cód. Verificação: " . $verificationCode, 0, 1, "L");

        $pdf->SetFont("helvetica", "", 5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY($x + 2, $y + $h - 7);
        $pdf->MultiCell($w - 4, 8, "Documento assinado digitalmente conforme MP 2.200-2/2001 e Lei 14.063/2020.\nValidade jurídica assegurada.", 0, "L");

        $pdf->Output($outputPdfPath, "F");
        if (!file_exists($outputPdfPath)) {
            throw new Exception("Erro ao salvar o arquivo PDF assinado.");
        }
        } catch (Throwable $e) {
        if ($isTempFile && $pfxPathToUse && file_exists($pfxPathToUse)) {
            @unlink($pfxPathToUse);
        }
        if (file_exists($outputPdfPath)) {
            @unlink($outputPdfPath);
        }
        return ["ok" => false, "error" => "Erro na assinatura digital: " . $e->getMessage()];
        }
    } else {
        $outputPdfPath = $existingAbs;
    }

    if ($isTempFile && $pfxPathToUse && file_exists($pfxPathToUse)) {
        @unlink($pfxPathToUse);
    }

    if (!$alreadyFinalized) {
        $novoCaminhoRelativo = "docFinalizado/" . $outputFileName;
        $stmtUp = mysqli_prepare($conn, "UPDATE solicitacoes_assinatura SET caminho_arquivo = ?, status_final = 'finalizado' WHERE id = ?");
        if (!$stmtUp) {
            if (file_exists($outputPdfPath)) {
                @unlink($outputPdfPath);
            }
            return ["ok" => false, "error" => "Erro ao preparar atualização do banco."];
        }
        mysqli_stmt_bind_param($stmtUp, "si", $novoCaminhoRelativo, $idDocumento);
        if (!mysqli_stmt_execute($stmtUp)) {
            if (file_exists($outputPdfPath)) {
                @unlink($outputPdfPath);
            }
            return ["ok" => false, "error" => "Erro ao atualizar banco de dados: " . mysqli_error($conn)];
        }
    } else {
        $novoCaminhoRelativo = $caminhoAtual;
    }

    $entidadeId = assinatura_obterEntidadeIdDaSolicitacao($conn, $idDocumento, $doc);
    if ($entidadeId > 0 && file_exists($outputPdfPath)) {
        $root = dirname($baseDir) . "/arquivos/Funcionarios/" . $entidadeId . "/";
        if (!is_dir($root)) {
            @mkdir($root, 0777, true);
        }

        $nomeArquivoOriginal = trim(strval($doc["nome_arquivo_original"] ?? ""));
        $nomeArquivoSafe = assinatura_sanitizarNomeArquivo($nomeArquivoOriginal !== "" ? $nomeArquivoOriginal : basename($novoCaminhoRelativo));
        if (strtolower(pathinfo($nomeArquivoSafe, PATHINFO_EXTENSION)) !== "pdf") {
            $nomeArquivoSafe .= ".pdf";
        }

        $destAbs = rtrim(str_replace("\\", "/", $root), "/") . "/" . $nomeArquivoSafe;
        if (file_exists($destAbs)) {
            $info = pathinfo($nomeArquivoSafe);
            $base = $info["filename"] ?? "documento";
            $ext = isset($info["extension"]) ? "." . $info["extension"] : ".pdf";
            $destAbs = rtrim(str_replace("\\", "/", $root), "/") . "/" . $base . "_" . time() . $ext;
            $nomeArquivoSafe = basename($destAbs);
        }

        if (@copy($outputPdfPath, $destAbs)) {
            $rel = "arquivos/Funcionarios/" . $entidadeId . "/" . $nomeArquivoSafe;
            $tipoDocumentoId = intval($doc["tipo_documento_id"] ?? 0);
            assinatura_inserirDocumentoFuncionario($conn, $entidadeId, $tipoDocumentoId, $nomeArquivoSafe, $rel);
        }
    }

    if ($sendEmail && !$alreadyFinalized) {
        $helper = $baseDir . "/email_helper.php";
        if (file_exists($helper)) {
            require_once $helper;
        }
        try {
            $docIdString = strval($doc["id_documento"] ?? $idDocumento);
            if (function_exists("enviarEmailFinalizacao")) {
                enviarEmailFinalizacao($conn, $idDocumento, $docIdString, $novoCaminhoRelativo);
            }
        } catch (Throwable $e) {
            error_log("Erro ao enviar email final no processar_finalizacao: " . $e->getMessage());
        }
    }

    return ["ok" => true, "caminho_arquivo" => $novoCaminhoRelativo, "already_finalized" => $alreadyFinalized];
}

if (basename(__FILE__) === basename($_SERVER["SCRIPT_FILENAME"] ?? "")) {
    $interno = true;

    if (file_exists("conecta.php")) {
        include "conecta.php";
    } elseif (file_exists("../conecta.php")) {
        include "../conecta.php";
    } else {
        die("Erro: Arquivo conecta.php não encontrado.");
    }

    if (file_exists("email_config.php")) {
        include "email_config.php";
    } elseif (file_exists("../email_config.php")) {
        include "../email_config.php";
    }

    $phpmailerPath = __DIR__ . "/../../PHPMailer/src/";
    if (file_exists($phpmailerPath . "Exception.php")) {
        require $phpmailerPath . "Exception.php";
        require $phpmailerPath . "PHPMailer.php";
        require $phpmailerPath . "SMTP.php";
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        die("Método inválido.");
    }

    $id_documento = intval($_POST["id_documento"] ?? 0);
    $pfx_password = strval($_POST["pfx_password"] ?? "");
    $pfx_file = (isset($_FILES["pfx_file"]) && is_array($_FILES["pfx_file"])) ? $_FILES["pfx_file"] : null;

    $res = assinatura_finalizar_documento_icp($conn, $id_documento, [
        "pfx_password" => $pfx_password,
        "pfx_file" => $pfx_file,
        "send_email" => true,
    ]);

    if (!empty($res["ok"])) {
        header("Location: finalizar.php?status=success&message=Documento assinado digitalmente com sucesso!");
        exit;
    }

    die(strval($res["error"] ?? "Erro na assinatura digital."));
}
