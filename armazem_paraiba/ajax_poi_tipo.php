<?php
ob_start(); // Captura qualquer output indesejado (warnings, BOM, whitespace, etc.)

// Garante JSON mesmo em erro fatal
register_shutdown_function(function(){
    $err = error_get_last();
    if($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])){
        ob_end_clean();
        if(!headers_sent()){ header('Content-Type: application/json; charset=utf-8'); }
        echo json_encode(["sucesso" => false, "erro" => "Erro PHP: " . $err['message']], JSON_UNESCAPED_UNICODE);
    }
});

header("Content-Type: application/json; charset=utf-8");
error_reporting(0);

include_once "load_env.php";

// Função para encerrar limpando o buffer e enviando JSON
function jsonSair(array $data): void {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST["ajax_action"] ?? "";

if ($action !== "criar_tipo_poi" && $action !== "listar_tipos_poi") {
    jsonSair(["sucesso" => false, "erro" => "Ação inválida."]);
}

// Conexão direta com o banco
$conn = mysqli_connect(
    $_ENV["DB_HOST"] ?? "localhost",
    $_ENV["DB_USER"] ?? "root",
    $_ENV["DB_PASSWORD"] ?? "",
    $_ENV["DB_NAME"] ?? ""
);
if (!$conn) {
    jsonSair(["sucesso" => false, "erro" => "Erro de conexão com o banco: " . mysqli_connect_error()]);
}
$conn->set_charset("utf8mb4");

// Query helper local
function _q($sql, $types = "", $params = []){
    global $conn;
    if(empty($params)){
        return mysqli_query($conn, $sql);
    }
    $parts = explode('?', $sql);
    $final = $parts[0];
    foreach($parts as $i => $part){
        if($i === 0) continue;
        $val = $params[$i - 1] ?? "";
        $final .= "'" . mysqli_real_escape_string($conn, strval($val)) . "'" . $part;
    }
    return mysqli_query($conn, $final);
}

// Garante a tabela poi_tipo
$__rsDb   = _q("SELECT DATABASE() AS db");
$__dbName = $__rsDb ? strval(mysqli_fetch_assoc($__rsDb)["db"] ?? "") : "";
if($__dbName !== ""){
    $__rsExists = _q("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'poi_tipo' LIMIT 1", "s", [$__dbName]);
    $__exists   = $__rsExists ? mysqli_fetch_assoc($__rsExists) : null;
    if(empty($__exists)){
        _q("CREATE TABLE IF NOT EXISTS poi_tipo (
            poti_nb_id   INT AUTO_INCREMENT PRIMARY KEY,
            poti_tx_codigo VARCHAR(50) NOT NULL UNIQUE,
            poti_tx_nome   VARCHAR(100) NOT NULL,
            poti_tx_emoji  VARCHAR(10) NOT NULL DEFAULT '📌',
            poti_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

// ---- CRIAR NOVO TIPO ----
if ($action === "criar_tipo_poi") {
    $codigo = trim($_POST["codigo"] ?? "");
    $nome   = trim($_POST["nome"]   ?? "");
    $emoji  = trim($_POST["emoji"]  ?? "");

    if (empty($codigo) || empty($nome) || empty($emoji)) {
        jsonSair(["sucesso" => false, "erro" => "Todos os campos são obrigatórios."]);
    }

    $chkRs = _q("SELECT 1 FROM poi_tipo WHERE poti_tx_codigo = ? LIMIT 1", "s", [$codigo]);
    if ($chkRs && mysqli_fetch_assoc($chkRs)) {
        jsonSair(["sucesso" => false, "erro" => "Já existe um tipo com este código."]);
    }

    $res = _q("INSERT INTO poi_tipo (poti_tx_codigo, poti_tx_nome, poti_tx_emoji) VALUES (?, ?, ?)", "sss", [$codigo, $nome, $emoji]);
    if ($res) {
        jsonSair(["sucesso" => true, "tipo" => ["poti_tx_codigo" => $codigo, "poti_tx_nome" => $nome, "poti_tx_emoji" => $emoji]]);
    } else {
        jsonSair(["sucesso" => false, "erro" => "Erro ao salvar no banco: " . mysqli_error($conn)]);
    }
}

// ---- LISTAR TIPOS ----
if ($action === "listar_tipos_poi") {
    $rs     = _q("SELECT poti_tx_codigo, poti_tx_nome, poti_tx_emoji FROM poi_tipo WHERE poti_tx_status = 'ativo' ORDER BY poti_tx_nome ASC");
    $tipos  = [];
    if($rs){ while($r = mysqli_fetch_assoc($rs)){ $tipos[] = $r; } }
    jsonSair($tipos);
}
