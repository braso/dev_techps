<?php
$interno = true;

header("Content-Type: application/json; charset=utf-8");
ob_start();

$GLOBALS["__assinatura_json_sent"] = false;

register_shutdown_function(function (): void {
    $err = error_get_last();
    if (!$err) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array(intval($err["type"] ?? 0), $fatalTypes, true)) {
        return;
    }
    if (!empty($GLOBALS["__assinatura_json_sent"])) {
        return;
    }

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    http_response_code(500);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(
        [
            "status" => "error",
            "message" => "Erro interno ao processar a assinatura.",
            "detail" => ($err["message"] ?? "") . " em " . ($err["file"] ?? "") . ":" . ($err["line"] ?? "")
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
});

include __DIR__ . "/../conecta.php";
include __DIR__ . "/email_config.php";

$phpmailerPath = __DIR__ . "/../../PHPMailer/src/";
$phpmailerCandidates = [
    $phpmailerPath,
    __DIR__ . "/../../../PHPMailer/src/",
    dirname(__DIR__, 2) . "/PHPMailer/src/"
];
foreach ($phpmailerCandidates as $base) {
    $base = rtrim(str_replace("\\", "/", strval($base)), "/") . "/";
    if (file_exists($base . "Exception.php") && file_exists($base . "PHPMailer.php") && file_exists($base . "SMTP.php")) {
        require_once $base . "Exception.php";
        require_once $base . "PHPMailer.php";
        require_once $base . "SMTP.php";
        break;
    }
}

$helperPath = __DIR__ . "/email_helper.php";
if (file_exists($helperPath)) {
    require_once $helperPath;
}

$finalizacaoPath = __DIR__ . "/processar_finalizacao.php";
if (file_exists($finalizacaoPath)) {
    require_once $finalizacaoPath;
}

function logDebug(string $message): void {
    $path = __DIR__ . "/log_assinar.txt";
    $ts = date("Y-m-d H:i:s");
    @file_put_contents($path, "[{$ts}] {$message}\n", FILE_APPEND);
}

function jsonError(string $message, int $code = 400): void {
    $GLOBALS["__assinatura_json_sent"] = true;
    http_response_code($code);
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    echo json_encode(["status" => "error", "message" => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ensureAssinaturaTables(mysqli $conn): void {
    $res = mysqli_query($conn, "SHOW TABLES LIKE 'solicitacoes_assinatura'");
    if ($res && mysqli_num_rows($res) == 0) {
        $sql = "CREATE TABLE IF NOT EXISTS solicitacoes_assinatura (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(64) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL,
            nome VARCHAR(255),
            caminho_arquivo VARCHAR(255) NOT NULL,
            nome_arquivo_original VARCHAR(255),
            tipo_documento_id INT NULL,
            validar_icp ENUM('sim','nao') NOT NULL DEFAULT 'nao',
            data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(50) DEFAULT 'pendente',
            id_documento VARCHAR(100),
            data_assinatura DATETIME NULL,
            status_final VARCHAR(50) DEFAULT 'pendente'
        )";
        mysqli_query($conn, $sql);
    } else {
        $cols = [
            "nome" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN nome VARCHAR(255)",
            "nome_arquivo_original" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN nome_arquivo_original VARCHAR(255)",
            "tipo_documento_id" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN tipo_documento_id INT NULL",
            "validar_icp" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN validar_icp ENUM('sim','nao') NOT NULL DEFAULT 'nao'",
            "data_assinatura" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN data_assinatura DATETIME NULL",
            "status_final" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN status_final VARCHAR(50) DEFAULT 'pendente'"
        ];
        foreach ($cols as $col => $ddl) {
            $check = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE '{$col}'");
            if ($check && mysqli_num_rows($check) == 0) {
                mysqli_query($conn, $ddl);
            }
        }
    }

    $resA = mysqli_query($conn, "SHOW TABLES LIKE 'assinantes'");
    if ($resA && mysqli_num_rows($resA) == 0) {
        $sql = "CREATE TABLE IF NOT EXISTS assinantes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_solicitacao INT NOT NULL,
            enti_nb_id INT NULL,
            nome VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            cpf VARCHAR(20),
            funcao VARCHAR(100),
            ordem INT NOT NULL DEFAULT 1,
            token VARCHAR(64) NOT NULL UNIQUE,
            status VARCHAR(50) DEFAULT 'pendente',
            data_assinatura DATETIME NULL,
            ip VARCHAR(45),
            metadados TEXT,
            INDEX (id_solicitacao),
            INDEX (token)
        )";
        mysqli_query($conn, $sql);
    } else {
        $cols = [
            "enti_nb_id" => "ALTER TABLE assinantes ADD COLUMN enti_nb_id INT NULL AFTER id_solicitacao",
            "cpf" => "ALTER TABLE assinantes ADD COLUMN cpf VARCHAR(20)",
            "funcao" => "ALTER TABLE assinantes ADD COLUMN funcao VARCHAR(100)",
            "ordem" => "ALTER TABLE assinantes ADD COLUMN ordem INT NOT NULL DEFAULT 1",
            "data_assinatura" => "ALTER TABLE assinantes ADD COLUMN data_assinatura DATETIME NULL",
            "ip" => "ALTER TABLE assinantes ADD COLUMN ip VARCHAR(45)",
            "metadados" => "ALTER TABLE assinantes ADD COLUMN metadados TEXT"
        ];
        foreach ($cols as $col => $ddl) {
            $check = mysqli_query($conn, "SHOW COLUMNS FROM assinantes LIKE '{$col}'");
            if ($check && mysqli_num_rows($check) == 0) {
                mysqli_query($conn, $ddl);
            }
        }
    }

    $resS = mysqli_query($conn, "SHOW TABLES LIKE 'assinatura_eletronica'");
    if ($resS && mysqli_num_rows($resS) == 0) {
        $sql = "CREATE TABLE IF NOT EXISTS assinatura_eletronica (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255),
            email VARCHAR(255),
            cpf VARCHAR(20),
            rg VARCHAR(50),
            data_assinatura DATETIME NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            latitude VARCHAR(50),
            longitude VARCHAR(50),
            hash_assinatura VARCHAR(64),
            id_documento VARCHAR(100),
            caminho_arquivo VARCHAR(255)
        )";
        mysqli_query($conn, $sql);
    } else {
        $cols = [
            "nome" => "ALTER TABLE assinatura_eletronica ADD COLUMN nome VARCHAR(255) NULL",
            "email" => "ALTER TABLE assinatura_eletronica ADD COLUMN email VARCHAR(255) NULL",
            "cpf" => "ALTER TABLE assinatura_eletronica ADD COLUMN cpf VARCHAR(20) NULL",
            "rg" => "ALTER TABLE assinatura_eletronica ADD COLUMN rg VARCHAR(50) NULL",
            "data_assinatura" => "ALTER TABLE assinatura_eletronica ADD COLUMN data_assinatura DATETIME NULL",
            "ip_address" => "ALTER TABLE assinatura_eletronica ADD COLUMN ip_address VARCHAR(45) NULL",
            "user_agent" => "ALTER TABLE assinatura_eletronica ADD COLUMN user_agent TEXT NULL",
            "latitude" => "ALTER TABLE assinatura_eletronica ADD COLUMN latitude VARCHAR(50) NULL",
            "longitude" => "ALTER TABLE assinatura_eletronica ADD COLUMN longitude VARCHAR(50) NULL",
            "hash_assinatura" => "ALTER TABLE assinatura_eletronica ADD COLUMN hash_assinatura VARCHAR(64) NULL",
            "id_documento" => "ALTER TABLE assinatura_eletronica ADD COLUMN id_documento VARCHAR(100) NULL",
            "caminho_arquivo" => "ALTER TABLE assinatura_eletronica ADD COLUMN caminho_arquivo VARCHAR(255) NULL"
        ];
        foreach ($cols as $col => $ddl) {
            $check = mysqli_query($conn, "SHOW COLUMNS FROM assinatura_eletronica LIKE '{$col}'");
            if ($check && mysqli_num_rows($check) == 0) {
                try {
                    mysqli_query($conn, $ddl);
                } catch (Throwable $e) {
                }
            }
        }
    }
}

function normalizarCpf(string $cpf): string {
    return preg_replace('/\D+/', '', $cpf) ?? "";
}

function parseDataHora(string $value): string {
    $v = trim($value);
    if ($v === "") {
        return date("Y-m-d H:i:s");
    }
    $ts = strtotime($v);
    if ($ts === false) {
        return date("Y-m-d H:i:s");
    }
    return date("Y-m-d H:i:s", $ts);
}

function assinatura_entregarParaFuncionarioEFinalizarNotificacao(
    mysqli $conn,
    int $idSolicitacao,
    string $caminhoFinal,
    string $emailSolicitacao,
    string $nomeArquivoOriginal,
    int $tipoDocumentoId,
    string $docIdGlobal
): void {
    if (function_exists("assinatura_obterEntidadeIdDaSolicitacao") && function_exists("assinatura_inserirDocumentoFuncionario") && function_exists("assinatura_sanitizarNomeArquivo")) {
        $docRow = [
            "email" => $emailSolicitacao,
            "nome_arquivo_original" => $nomeArquivoOriginal,
            "tipo_documento_id" => $tipoDocumentoId
        ];
        $entidadeId = assinatura_obterEntidadeIdDaSolicitacao($conn, $idSolicitacao, $docRow);
        if ($entidadeId > 0) {
            $srcAbs = __DIR__ . "/" . ltrim($caminhoFinal, "/\\");
            if (file_exists($srcAbs)) {
                $root = dirname(__DIR__) . "/arquivos/Funcionarios/" . $entidadeId . "/";
                if (!is_dir($root)) {
                    @mkdir($root, 0777, true);
                }

                $nomeSafe = assinatura_sanitizarNomeArquivo($nomeArquivoOriginal !== "" ? $nomeArquivoOriginal : basename($caminhoFinal));
                if (strtolower(pathinfo($nomeSafe, PATHINFO_EXTENSION)) !== "pdf") {
                    $nomeSafe .= ".pdf";
                }

                $dest = rtrim(str_replace("\\", "/", $root), "/") . "/" . $nomeSafe;
                if (file_exists($dest)) {
                    $info = pathinfo($nomeSafe);
                    $base = $info["filename"] ?? "documento";
                    $ext2 = isset($info["extension"]) ? "." . $info["extension"] : ".pdf";
                    $dest = rtrim(str_replace("\\", "/", $root), "/") . "/" . $base . "_" . time() . $ext2;
                    $nomeSafe = basename($dest);
                }

                if (@copy($srcAbs, $dest)) {
                    $rel = "arquivos/Funcionarios/" . $entidadeId . "/" . $nomeSafe;
                    assinatura_inserirDocumentoFuncionario($conn, $entidadeId, $tipoDocumentoId, $nomeSafe, $rel);
                }
            }
        }
    }

    if (class_exists("PHPMailer\\PHPMailer\\PHPMailer") && function_exists("enviarEmailFinalizacao")) {
        enviarEmailFinalizacao($conn, $idSolicitacao, $docIdGlobal !== "" ? $docIdGlobal : strval($idSolicitacao), $caminhoFinal);
    }
}

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    jsonError("Método inválido.", 405);
}

ensureAssinaturaTables($conn);

$token = trim(strval($_POST["token_solicitacao"] ?? ""));
if ($token === "") {
    jsonError("Token inválido.");
}

$cpf = trim(strval($_POST["cpf"] ?? ""));
$rg = trim(strval($_POST["rg"] ?? ""));
$nome = trim(strval($_POST["nome"] ?? ""));
$latitude = trim(strval($_POST["latitude"] ?? ""));
$longitude = trim(strval($_POST["longitude"] ?? ""));
$deviceInfo = trim(strval($_POST["device_info"] ?? ""));
$hash = trim(strval($_POST["hash_assinatura"] ?? ""));
$dataHora = parseDataHora(strval($_POST["data_hora"] ?? ""));

$arquivo = $_FILES["arquivo_assinado"] ?? null;

$sql = "SELECT a.*, s.id as solicitacao_id, s.email as email_solicitacao, s.nome_arquivo_original, s.tipo_documento_id, s.validar_icp, s.caminho_arquivo, s.id_documento as doc_id_global
        FROM assinantes a
        JOIN solicitacoes_assinatura s ON a.id_solicitacao = s.id
        WHERE a.token = ?
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    jsonError("Falha ao preparar consulta do token.");
}
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) {
    jsonError("Solicitação não encontrada.", 404);
}

$idSolicitacao = intval($row["solicitacao_id"] ?? 0);
if ($idSolicitacao <= 0) {
    jsonError("Solicitação inválida.");
}

$docIdGlobal = trim(strval($row["doc_id_global"] ?? ""));
$emailSolicitacao = trim(strval($row["email_solicitacao"] ?? ""));
$nomeArquivoOriginal = trim(strval($row["nome_arquivo_original"] ?? ""));
$tipoDocumentoId = intval($row["tipo_documento_id"] ?? 0);
$validarIcp = strtolower(trim(strval($row["validar_icp"] ?? "nao"))) === "sim" ? "sim" : "nao";

$statusAss = strtolower(trim(strval($row["status"] ?? "")));
$jaAssinado = ($statusAss === "assinado");
$warning = $jaAssinado ? "Este link já foi utilizado para assinatura." : "";
$protocolo = "";
$caminhoRel = "";
$idAssinatura = 0;

if ($jaAssinado) {
    $metadados = [];
    $metadadosRaw = strval($row["metadados"] ?? "");
    if ($metadadosRaw !== "") {
        $tmp = json_decode($metadadosRaw, true);
        if (is_array($tmp)) {
            $metadados = $tmp;
        }
    }
    $protocolo = trim(strval($metadados["hash"] ?? ""));
    if ($protocolo === "") {
        $protocolo = $hash !== "" ? $hash : bin2hex(random_bytes(16));
    }

    $dataHoraDb = trim(strval($row["data_assinatura"] ?? ""));
    if ($dataHoraDb !== "") {
        $dataHora = $dataHoraDb;
    }

    $caminhoRel = trim(strval($row["caminho_arquivo"] ?? ""));
}

$ordem = intval($row["ordem"] ?? 1);
if ($ordem > 1) {
    $ordemAnterior = $ordem - 1;
    $stmtPrev = mysqli_prepare($conn, "SELECT status FROM assinantes WHERE id_solicitacao = ? AND ordem = ? LIMIT 1");
    if ($stmtPrev) {
        mysqli_stmt_bind_param($stmtPrev, "ii", $idSolicitacao, $ordemAnterior);
        mysqli_stmt_execute($stmtPrev);
        $prev = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPrev));
        mysqli_stmt_close($stmtPrev);
        $prevStatus = strtolower(trim(strval($prev["status"] ?? "")));
        if ($prevStatus !== "assinado") {
            jsonError("Aguardando assinatura anterior.", 409);
        }
    }
}

if (!$jaAssinado) {
    if (!$arquivo || !is_array($arquivo) || ($arquivo["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        jsonError("Arquivo assinado não enviado.");
    }

    $ext = strtolower(pathinfo(strval($arquivo["name"] ?? ""), PATHINFO_EXTENSION));
    if ($ext !== "pdf") {
        jsonError("Apenas PDF é permitido.");
    }

    $protocolo = $hash !== "" ? $hash : bin2hex(random_bytes(16));
    $dirAss = __DIR__ . "/docAssinado/";
    if (!is_dir($dirAss)) {
        @mkdir($dirAss, 0777, true);
    }

    $fileName = "assinado_" . time() . "_" . $protocolo . ".pdf";
    $destAbs = $dirAss . $fileName;
    if (!move_uploaded_file(strval($arquivo["tmp_name"] ?? ""), $destAbs)) {
        jsonError("Falha ao salvar o arquivo no servidor.");
    }

    $caminhoRel = "docAssinado/" . $fileName;

    $ip = trim(strval($_SERVER["REMOTE_ADDR"] ?? ""));
    $ua = trim(strval($_SERVER["HTTP_USER_AGENT"] ?? ""));

    $metadados = [
        "hash" => $protocolo,
        "rg" => $rg,
        "latitude" => $latitude,
        "longitude" => $longitude,
        "device_info" => $deviceInfo
    ];
    $metadadosJson = json_encode($metadados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $cpfDigits = normalizarCpf($cpf);
    $stmtUpd = mysqli_prepare($conn, "UPDATE assinantes SET status = 'assinado', data_assinatura = ?, ip = ?, metadados = ?, cpf = ? WHERE token = ? LIMIT 1");
    if ($stmtUpd) {
        mysqli_stmt_bind_param($stmtUpd, "sssss", $dataHora, $ip, $metadadosJson, $cpfDigits, $token);
        mysqli_stmt_execute($stmtUpd);
        mysqli_stmt_close($stmtUpd);
    }

    $stmtUpSol = mysqli_prepare($conn, "UPDATE solicitacoes_assinatura SET caminho_arquivo = ?, status = 'em_progresso' WHERE id = ? LIMIT 1");
    if ($stmtUpSol) {
        mysqli_stmt_bind_param($stmtUpSol, "si", $caminhoRel, $idSolicitacao);
        mysqli_stmt_execute($stmtUpSol);
        mysqli_stmt_close($stmtUpSol);
    }

    try {
        $stmtIns = mysqli_prepare($conn, "INSERT INTO assinatura_eletronica (nome, email, cpf, rg, data_assinatura, ip_address, user_agent, latitude, longitude, hash_assinatura, id_documento, caminho_arquivo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmtIns) {
            mysqli_stmt_bind_param(
                $stmtIns,
                "ssssssssssss",
                $nome,
                $emailSolicitacao,
                $cpfDigits,
                $rg,
                $dataHora,
                $ip,
                $ua,
                $latitude,
                $longitude,
                $protocolo,
                $docIdGlobal,
                $caminhoRel
            );
            mysqli_stmt_execute($stmtIns);
            $idAssinatura = intval(mysqli_insert_id($conn));
            mysqli_stmt_close($stmtIns);
        }
    } catch (Throwable $e) {
    }
} else {
    if ($protocolo === "") {
        $protocolo = $hash !== "" ? $hash : bin2hex(random_bytes(16));
    }
    if ($caminhoRel === "") {
        $caminhoRel = trim(strval($row["caminho_arquivo"] ?? ""));
    }
}
$total = 0;
$assinados = 0;
$stmtCount = mysqli_prepare($conn, "SELECT COUNT(*) as total, SUM(CASE WHEN LOWER(status) = 'assinado' THEN 1 ELSE 0 END) as assinados FROM assinantes WHERE id_solicitacao = ?");
if ($stmtCount) {
    mysqli_stmt_bind_param($stmtCount, "i", $idSolicitacao);
    mysqli_stmt_execute($stmtCount);
    $cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCount));
    mysqli_stmt_close($stmtCount);
    $total = intval($cnt["total"] ?? 0);
    $assinados = intval($cnt["assinados"] ?? 0);
}

$ultimo = ($total > 0 && $assinados >= $total);

if (!$ultimo) {
    $stmtNext = mysqli_prepare($conn, "SELECT nome, email, token, funcao FROM assinantes WHERE id_solicitacao = ? AND LOWER(status) <> 'assinado' ORDER BY ordem ASC, id ASC LIMIT 1");
    if ($stmtNext) {
        mysqli_stmt_bind_param($stmtNext, "i", $idSolicitacao);
        mysqli_stmt_execute($stmtNext);
        $next = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtNext));
        mysqli_stmt_close($stmtNext);

        if ($next && class_exists("PHPMailer\\PHPMailer\\PHPMailer") && function_exists("enviarEmailProximo")) {
            enviarEmailProximo(
                trim(strval($next["email"] ?? "")),
                trim(strval($next["nome"] ?? "")),
                trim(strval($next["token"] ?? "")),
                $nomeArquivoOriginal !== "" ? $nomeArquivoOriginal : basename($caminhoRel),
                $docIdGlobal !== "" ? $docIdGlobal : strval($idSolicitacao),
                trim(strval($next["funcao"] ?? "Signatário")),
                $caminhoRel
            );
        }
    }
} else {
    $stmtDone = mysqli_prepare($conn, "UPDATE solicitacoes_assinatura SET status = 'assinado', data_assinatura = ? WHERE id = ? LIMIT 1");
    if ($stmtDone) {
        mysqli_stmt_bind_param($stmtDone, "si", $dataHora, $idSolicitacao);
        mysqli_stmt_execute($stmtDone);
        mysqli_stmt_close($stmtDone);
    }

    $caminhoFinal = $caminhoRel;
    if ($validarIcp === "sim" && function_exists("assinatura_finalizar_documento_icp")) {
        $res = assinatura_finalizar_documento_icp($conn, $idSolicitacao, ["send_email" => true]);
        if (!empty($res["ok"]) && !empty($res["caminho_arquivo"])) {
            $caminhoFinal = strval($res["caminho_arquivo"]);
        } elseif (!empty($res["error"])) {
            if ($warning === "") {
                $warning = strval($res["error"]);
            }
            assinatura_entregarParaFuncionarioEFinalizarNotificacao($conn, $idSolicitacao, $caminhoFinal, $emailSolicitacao, $nomeArquivoOriginal, $tipoDocumentoId, $docIdGlobal);
        }
    } else {
        assinatura_entregarParaFuncionarioEFinalizarNotificacao($conn, $idSolicitacao, $caminhoFinal, $emailSolicitacao, $nomeArquivoOriginal, $tipoDocumentoId, $docIdGlobal);
    }

    $caminhoRel = $caminhoFinal;
}

$GLOBALS["__assinatura_json_sent"] = true;
while (ob_get_level() > 0) {
    @ob_end_clean();
}
echo json_encode(
    [
        "status" => "success",
        "protocolo" => $protocolo,
        "data_hora" => $dataHora,
        "id_assinatura" => $idAssinatura,
        "caminho_arquivo" => $caminhoRel,
        "warning" => $warning !== "" ? $warning : null
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
