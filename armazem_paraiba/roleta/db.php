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
    segmento VARCHAR(255) NULL,
    funcao VARCHAR(255) NULL,
    observacoes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
function roleta_gen_liberacao() {
    return strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
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
?>