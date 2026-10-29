<?php
// Conexao MySQL + inicializacao das tabelas da Roleta (independente de sessao)
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    foreach ($env as $attr => $val) {
        $v = is_string($val) ? trim($val) : $val;
        if (is_string($v) && strlen($v) >= 2) {
            $first = $v[0]; $last = $v[strlen($v)-1];
            if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
                $v = substr($v, 1, -1);
            }
        }
        putenv("{$attr}={$v}");
        $_ENV[$attr] = $v;
    }
}

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';
$dbName = getenv('DB_NAME') ?: 'techpsjornada_dev';

$conn = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
if (!$conn) {
    die('Falha na conexao com o banco: ' . mysqli_connect_error());
}
$conn->set_charset('utf8mb4');
mysqli_query($conn, "SET time_zone = '-03:00'");

// Criar tabelas se nao existirem
$queries = [
"CREATE TABLE IF NOT EXISTS roleta_premios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(32) UNIQUE NOT NULL,
    nome VARCHAR(255) NOT NULL,
    foto VARCHAR(255) NULL,
    cor VARCHAR(16) NOT NULL DEFAULT '#30a0b8',
    peso INT NOT NULL DEFAULT 1,
    quantidade INT NOT NULL DEFAULT 0,
    ativo TINYINT NOT NULL DEFAULT 1,
    girar_novamente TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS roleta_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    telefone VARCHAR(64) NULL,
    empresa VARCHAR(255) NULL,
    documento VARCHAR(20) NULL,
    segmento VARCHAR(255) NULL,
    funcao VARCHAR(255) NULL,
    observacoes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lead_documento (documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS roleta_lead_premios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    premio_id INT NOT NULL,
    bonus INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lead_premio (lead_id, premio_id),
    KEY idx_lead_premios_lead (lead_id),
    FOREIGN KEY (lead_id) REFERENCES roleta_leads(id) ON DELETE CASCADE,
    FOREIGN KEY (premio_id) REFERENCES roleta_premios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS roleta_codigos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(32) UNIQUE NOT NULL,
    lead_id INT NULL,
    giros_permitidos INT NOT NULL DEFAULT 1,
    giros_usados INT NOT NULL DEFAULT 0,
    expira_em DATETIME NULL,
    ativo TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES roleta_leads(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS roleta_giros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_liberacao VARCHAR(32) NULL,
    lead_id INT NULL,
    premio_id INT NULL,
    premio_nome VARCHAR(255) NULL,
    premio_foto VARCHAR(255) NULL,
    premio_cor VARCHAR(16) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS roleta_config (
    chave VARCHAR(64) PRIMARY KEY,
    valor TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS roleta_qrcodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    imagem VARCHAR(255) NOT NULL,
    logo VARCHAR(255) NULL,
    ativo TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($queries as $q) { mysqli_query($conn, $q); }

// defaults
mysqli_query($conn, "INSERT IGNORE INTO roleta_config (chave, valor) VALUES ('modo_livre','0')");
mysqli_query($conn, "INSERT IGNORE INTO roleta_config (chave, valor) VALUES ('admin_password_hash','')");

function roleta_cfg($conn, $chave, $default = null) {
    $stmt = mysqli_prepare($conn, "SELECT valor FROM roleta_config WHERE chave=?");
    mysqli_stmt_bind_param($stmt, "s", $chave);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $row ? $row['valor'] : $default;
}
function roleta_setcfg($conn, $chave, $valor) {
    $stmt = mysqli_prepare($conn, "INSERT INTO roleta_config (chave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)");
    mysqli_stmt_bind_param($stmt, "ss", $chave, $valor);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
function roleta_json($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function roleta_gen_codigo($prefix = 'PRM') {
    return $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}
function roleta_gen_liberacao($conn = null) {
    if (!$conn) {
        global $conn;
    }
    if (!$conn) return 'TECHSP01';

    // Incremento atômico do contador no banco (evita códigos repetidos)
    $upd = mysqli_query($conn, "UPDATE roleta_config SET valor = LAST_INSERT_ID(CAST(valor AS UNSIGNED) + 1) WHERE chave='codigo_liberacao_seq'");
    $seq = 0;
    if ($upd && mysqli_affected_rows($conn) > 0) {
        $rs = mysqli_query($conn, "SELECT LAST_INSERT_ID() AS seq");
        if ($rs) {
            $row = mysqli_fetch_assoc($rs);
            $seq = (int)($row['seq'] ?? 0);
        }
    }
    if ($seq <= 0) {
        $rsMax = mysqli_query($conn, "SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 7) AS UNSIGNED)), 0) AS max_seq FROM roleta_codigos WHERE codigo REGEXP '^TECHSP[0-9]+$'");
        $maxSeq = $rsMax ? (int)mysqli_fetch_assoc($rsMax)['max_seq'] : 0;
        $seq = $maxSeq + 1;
        roleta_setcfg($conn, 'codigo_liberacao_seq', (string)$seq);
    }
    return 'TECHSP' . str_pad((string)$seq, 2, '0', STR_PAD_LEFT);
}
function roleta_somente_digitos($valor) {
    return preg_replace('/\D+/', '', (string)$valor);
}
function roleta_valida_cpf($cpf) {
    $cpf = roleta_somente_digitos($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int)$cpf[$i] * (($t + 1) - $i);
        }
        $digito = ($soma * 10) % 11;
        if ($digito === 10) $digito = 0;
        if ((int)$cpf[$t] !== $digito) return false;
    }
    return true;
}
function roleta_valida_cnpj($cnpj) {
    $cnpj = roleta_somente_digitos($cnpj);
    if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) return false;
    $calcular = function ($base, array $pesos) {
        $soma = 0;
        foreach (str_split($base) as $i => $digito) {
            $soma += (int)$digito * $pesos[$i];
        }
        $resto = $soma % 11;
        return $resto < 2 ? 0 : 11 - $resto;
    };
    $d1 = $calcular(substr($cnpj, 0, 12), [5,4,3,2,9,8,7,6,5,4,3,2]);
    if ((int)$cnpj[12] !== $d1) return false;
    $d2 = $calcular(substr($cnpj, 0, 13), [6,5,4,3,2,9,8,7,6,5,4,3,2]);
    return (int)$cnpj[13] === $d2;
}
// Retorna 'cpf', 'cnpj' ou false
function roleta_valida_documento($documento) {
    $doc = roleta_somente_digitos($documento);
    if (strlen($doc) === 11) return roleta_valida_cpf($doc) ? 'cpf' : false;
    if (strlen($doc) === 14) return roleta_valida_cnpj($doc) ? 'cnpj' : false;
    return false;
}
// Recorta as margens brancas do QR e recria uma borda de respiro, deixando o código maior.
function roleta_processar_qr_arquivo($origem, $ext, $destino) {
    if (!extension_loaded('gd') || !is_file($origem)) return false;
    $ext = strtolower($ext);
    $src = false;
    switch ($ext) {
        case 'png': $src = @imagecreatefrompng($origem); break;
        case 'jpg': case 'jpeg': $src = @imagecreatefromjpeg($origem); break;
        case 'gif': $src = @imagecreatefromgif($origem); break;
        case 'webp': $src = @imagecreatefromwebp($origem); break;
    }
    if (!$src) return false;

    $w = imagesx($src); $h = imagesy($src);
    if ($w < 10 || $h < 10) { imagedestroy($src); return false; }

    $flat = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($flat, 255, 255, 255);
    imagefilledrectangle($flat, 0, 0, $w - 1, $h - 1, $white);
    imagealphablending($flat, true);
    imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);
    imagedestroy($src);

    $scanMax = 700;
    $scale = min(1, $scanMax / max($w, $h));
    $sw = max(1, (int)round($w * $scale));
    $sh = max(1, (int)round($h * $scale));
    $scan = imagecreatetruecolor($sw, $sh);
    $whiteScan = imagecolorallocate($scan, 255, 255, 255);
    imagefilledrectangle($scan, 0, 0, $sw - 1, $sh - 1, $whiteScan);
    imagealphablending($scan, true);
    imagecopyresampled($scan, $flat, 0, 0, 0, 0, $sw, $sh, $w, $h);

    // Detecta as faixas verticais com conteúdo e escolhe o maior bloco (o QR,
    // ignorando legendas/textos pequenos abaixo ou acima dele).
    $rowHas = array_fill(0, $sh, false);
    for ($y = 0; $y < $sh; $y++) {
        for ($x = 0; $x < $sw; $x++) {
            $rgb = imagecolorat($scan, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            if ($r < 220 || $g < 220 || $b < 220) {
                $rowHas[$y] = true;
                break;
            }
        }
    }
    $runs = [];
    $runStart = null;
    for ($y = 0; $y < $sh; $y++) {
        if ($rowHas[$y]) {
            if ($runStart === null) $runStart = $y;
        } elseif ($runStart !== null) {
            $runs[] = [$runStart, $y - 1];
            $runStart = null;
        }
    }
    if ($runStart !== null) $runs[] = [$runStart, $sh - 1];
    if (empty($runs)) { imagedestroy($scan); imagedestroy($flat); return false; }

    $mergeGap = max(2, (int)round($sh * 0.005));
    $merged = [];
    foreach ($runs as $run) {
        if (empty($merged)) { $merged[] = $run; continue; }
        $last = count($merged) - 1;
        if ($run[0] - $merged[$last][1] - 1 <= $mergeGap) {
            $merged[$last][1] = $run[1];
        } else {
            $merged[] = $run;
        }
    }
    $bestRun = $merged[0];
    foreach ($merged as $run) {
        if (($run[1] - $run[0]) > ($bestRun[1] - $bestRun[0])) $bestRun = $run;
    }
    $minY = $bestRun[0];
    $maxY = $bestRun[1];
    $minX = $sw;
    $maxX = -1;
    for ($y = $minY; $y <= $maxY; $y++) {
        for ($x = 0; $x < $sw; $x++) {
            $rgb = imagecolorat($scan, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            if ($r < 220 || $g < 220 || $b < 220) {
                if ($x < $minX) $minX = $x;
                if ($x > $maxX) $maxX = $x;
            }
        }
    }
    imagedestroy($scan);
    if ($maxX < $minX || $maxY < $minY) { imagedestroy($flat); return false; }

    if ($scale < 1) {
        $minX = max(0, (int)floor($minX / $scale) - 2);
        $minY = max(0, (int)floor($minY / $scale) - 2);
        $maxX = min($w - 1, (int)ceil(($maxX + 1) / $scale) + 2);
        $maxY = min($h - 1, (int)ceil(($maxY + 1) / $scale) + 2);
    }

    $cw = $maxX - $minX + 1;
    $ch = $maxY - $minY + 1;
    $side = max($cw, $ch);
    $margin = max(12, (int)round($side * 0.07));
    $outSize = $side + 2 * $margin;
    $out = imagecreatetruecolor($outSize, $outSize);
    $whiteOut = imagecolorallocate($out, 255, 255, 255);
    imagefilledrectangle($out, 0, 0, $outSize - 1, $outSize - 1, $whiteOut);
    $dstX = (int)(($outSize - $cw) / 2);
    $dstY = (int)(($outSize - $ch) / 2);
    imagecopy($out, $flat, $dstX, $dstY, $minX, $minY, $cw, $ch);
    imagedestroy($flat);

    $ok = @imagepng($out, $destino, 9);
    imagedestroy($out);
    if (!$ok) { @unlink($destino); return false; }
    return true;
}

// Retorna o caminho salvo, null quando nenhum arquivo foi enviado ou false em caso de erro.
function roleta_salvar_upload_qr($file) {
    if (!isset($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return false;
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['png','jpg','jpeg','gif','webp'], true)) return false;
    $dir = __DIR__ . '/uploads/qrcodes';
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) return false;
    $base = bin2hex(random_bytes(16));

    $destPng = $dir . '/' . $base . '.png';
    if (roleta_processar_qr_arquivo($file['tmp_name'], $ext, $destPng)) {
        return 'uploads/qrcodes/' . $base . '.png';
    }

    $fname = $base . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $fname)) return false;
    return 'uploads/qrcodes/' . $fname;
}

// Retorna o caminho salvo, null quando nenhuma logo foi enviada ou false em caso de erro.
function roleta_salvar_upload_logo($file) {
    if (!isset($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return false;
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['png','jpg','jpeg','gif','webp'], true)) return false;
    $dir = __DIR__ . '/uploads/qrcodes/logos';
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) return false;
    $fname = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $fname)) return false;
    return 'uploads/qrcodes/logos/' . $fname;
}
function roleta_primeiro_nome($nome) {
    if (!$nome) return null;
    $parts = preg_split('/\s+/', trim($nome));
    return $parts[0] ?? null;
}

// Bônus de chance permitidos por brinde/lead (em % sobre o peso original)
function roleta_bonus_validos() {
    return [25, 50, 75];
}

// Normaliza a lista vinda do admin: [{premio_id:1, bonus:50}, ...]
function roleta_parse_bonus_premios($raw) {
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) return [];
    $validos = roleta_bonus_validos();
    $out = [];
    foreach ($raw as $item) {
        if (!is_array($item)) continue;
        $premioId = (int)($item['premio_id'] ?? 0);
        $bonus = (int)($item['bonus'] ?? 0);
        if ($premioId <= 0 || !in_array($bonus, $validos, true)) continue;
        $out[$premioId] = $bonus;
    }
    return $out;
}

// Substitui os bônus de um lead (ignora brindes inexistentes)
function roleta_sync_bonus_lead($conn, $leadId, array $bonusPremios) {
    $leadId = (int)$leadId;
    if ($leadId <= 0) return;
    $del = mysqli_prepare($conn, "DELETE FROM roleta_lead_premios WHERE lead_id=?");
    mysqli_stmt_bind_param($del, 'i', $leadId);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);
    if (empty($bonusPremios)) return;
    $ins = mysqli_prepare($conn, "INSERT INTO roleta_lead_premios (lead_id, premio_id, bonus) SELECT ?, id, ? FROM roleta_premios WHERE id=?");
    foreach ($bonusPremios as $premioId => $bonus) {
        $premioId = (int)$premioId;
        $bonus = (int)$bonus;
        mysqli_stmt_bind_param($ins, 'iii', $leadId, $bonus, $premioId);
        mysqli_stmt_execute($ins);
    }
    mysqli_stmt_close($ins);
}

// Bônus cadastrados de um lead, indexados por premio_id
function roleta_bonus_lead($conn, $leadId) {
    $out = [];
    $leadId = (int)$leadId;
    if ($leadId <= 0) return $out;
    $stmt = mysqli_prepare($conn, "SELECT premio_id, bonus FROM roleta_lead_premios WHERE lead_id=?");
    mysqli_stmt_bind_param($stmt, 'i', $leadId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $out[(int)$row['premio_id']] = (int)$row['bonus'];
    }
    mysqli_stmt_close($stmt);
    return $out;
}

// Migração: novos campos do cadastro de leads (segmento, função e observações)
foreach ([
    'documento' => 'VARCHAR(20) NULL',
    'segmento' => 'VARCHAR(255) NULL',
    'funcao' => 'VARCHAR(255) NULL',
    'observacoes' => 'TEXT NULL',
] as $colunaLead => $definicaoLead) {
    $checkLead = mysqli_query($conn, "SHOW COLUMNS FROM roleta_leads LIKE '$colunaLead'");
    if ($checkLead && mysqli_num_rows($checkLead) === 0) {
        try {
            mysqli_query($conn, "ALTER TABLE roleta_leads ADD COLUMN $colunaLead $definicaoLead");
        } catch (Throwable $e) {}
    }
}
// Garante a unicidade do CPF/CNPJ (NULLs continuam permitidos)
$idxDocumento = mysqli_query($conn, "SHOW INDEX FROM roleta_leads WHERE Key_name='uq_lead_documento'");
if ($idxDocumento && mysqli_num_rows($idxDocumento) === 0) {
    try {
        mysqli_query($conn, "ALTER TABLE roleta_leads ADD UNIQUE KEY uq_lead_documento (documento)");
    } catch (Throwable $e) {}
}

// Migração: coluna do prêmio especial "Gire Novamente" em bancos já existentes
$colCheck = mysqli_query($conn, "SHOW COLUMNS FROM roleta_premios LIKE 'girar_novamente'");
$temColuna = $colCheck && mysqli_num_rows($colCheck) > 0;
if (!$temColuna) {
    try {
        mysqli_query($conn, "ALTER TABLE roleta_premios ADD COLUMN girar_novamente TINYINT NOT NULL DEFAULT 0 AFTER ativo");
    } catch (Throwable $e) {}
    $colCheck = mysqli_query($conn, "SHOW COLUMNS FROM roleta_premios LIKE 'girar_novamente'");
    $temColuna = $colCheck && mysqli_num_rows($colCheck) > 0;
}

// Cria o prêmio padrão "Gire Novamente" uma única vez
if ($temColuna && roleta_cfg($conn, 'girar_novamente_seed', '0') !== '1') {
    $temEspecial = mysqli_query($conn, "SELECT id FROM roleta_premios WHERE girar_novamente=1 LIMIT 1");
    if ($temEspecial && mysqli_num_rows($temEspecial) === 0) {
        $existente = mysqli_query($conn, "SELECT id FROM roleta_premios WHERE codigo='GIRE-NOVAMENTE' OR LOWER(TRIM(nome))='gire novamente' ORDER BY id LIMIT 1");
        $row = $existente ? mysqli_fetch_row($existente) : null;
        if ($row) {
            $id = (int)$row[0];
            mysqli_query($conn, "UPDATE roleta_premios SET girar_novamente=1 WHERE id=$id");
        } else {
            mysqli_query($conn, "INSERT IGNORE INTO roleta_premios (codigo,nome,foto,cor,peso,quantidade,ativo,girar_novamente) VALUES ('GIRE-NOVAMENTE','Gire Novamente',NULL,'#4caf50',1,0,1,1)");
        }
    }
    roleta_setcfg($conn, 'girar_novamente_seed', '1');
}

// Sequência dos códigos de liberação (TECHSP01, TECHSP02, TECHSP03, ...)
$rsSeqMax = mysqli_query($conn, "SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 7) AS UNSIGNED)), 0) AS max_seq FROM roleta_codigos WHERE codigo REGEXP '^TECHSP[0-9]+$'");
$seqMax = $rsSeqMax ? (int)mysqli_fetch_assoc($rsSeqMax)['max_seq'] : 0;
$seqAtual = roleta_cfg($conn, 'codigo_liberacao_seq', null);
if ($seqAtual === null || (int)$seqAtual < $seqMax) {
    roleta_setcfg($conn, 'codigo_liberacao_seq', (string)$seqMax);
}

// Migração: logo opcional nos QR Codes já cadastrados
$colQrLogo = mysqli_query($conn, "SHOW COLUMNS FROM roleta_qrcodes LIKE 'logo'");
if ($colQrLogo && mysqli_num_rows($colQrLogo) === 0) {
    try {
        mysqli_query($conn, "ALTER TABLE roleta_qrcodes ADD COLUMN logo VARCHAR(255) NULL AFTER imagem");
    } catch (Throwable $e) {}
}

// Migração única: recorta as margens dos QR Codes já cadastrados
if (roleta_cfg($conn, 'qrcodes_crop_migracao', '0') !== '1') {
    $rsQr = mysqli_query($conn, "SELECT id, imagem FROM roleta_qrcodes");
    while ($rsQr && ($qr = mysqli_fetch_assoc($rsQr))) {
        $oldPath = __DIR__ . '/' . $qr['imagem'];
        if (!is_file($oldPath)) continue;
        $ext = strtolower(pathinfo($oldPath, PATHINFO_EXTENSION));
        $novoNome = bin2hex(random_bytes(8)) . '.png';
        $novoPath = dirname($oldPath) . '/' . $novoNome;
        if (roleta_processar_qr_arquivo($oldPath, $ext, $novoPath)) {
            $novoRel = str_replace('\\', '/', ltrim(str_replace(__DIR__, '', $novoPath), '/\\'));
            $stmt = mysqli_prepare($conn, "UPDATE roleta_qrcodes SET imagem=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'si', $novoRel, $qr['id']);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            @unlink($oldPath);
        }
    }
    roleta_setcfg($conn, 'qrcodes_crop_migracao', '1');
}
?>