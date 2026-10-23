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
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS roleta_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    telefone VARCHAR(64) NULL,
    empresa VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
?>