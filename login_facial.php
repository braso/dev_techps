<?php
/*
    Endpoint de autenticação por biometria facial.
    Recebe: empresa_key (string), descritor (JSON float array)
    Retorna: JSON { ok, user, password, msg }
    
    Fluxo:
    1. Carrega todos os usuários ativos da empresa que têm face_descriptor
    2. Compara o descritor recebido com cada um (distância euclidiana)
    3. Se distância < threshold → autenticado → retorna login e senha (md5 já pronto)
*/

header("Content-Type: application/json");
header("Cache-Control: no-store");

// Sem sessão necessária aqui — é um endpoint público de autenticação
include_once "load_env.php";

// Captura APP_PATH e URL_BASE do .env raiz ANTES de sobrescrever com o .env da empresa
$rootAppPath = $_ENV["APP_PATH"] ?? "";
$urlBase     = ($_SERVER["HTTPS"] ?? "") === "on"
    ? "https://" . $_SERVER["HTTP_HOST"]
    : (strpos($_SERVER["HTTP_HOST"], "localhost") !== false ? "http://" : "https://") . $_SERVER["HTTP_HOST"];

$empresaKey  = trim($_POST["empresa_key"] ?? "");
$descritorRaw = trim($_POST["descritor"] ?? "");

if (empty($empresaKey) || empty($descritorRaw)) {
    echo json_encode(["ok" => false, "msg" => "Dados insuficientes."]);
    exit;
}

// Mapeia empresa_key → path (carrega do empresas.php para não duplicar)
include_once __DIR__ . "/empresas.php";
// $empresas já está disponível via empresas.php

$empresaKey = strtoupper($empresaKey);
if (!array_key_exists($empresaKey, $empresas)) {
    echo json_encode(["ok" => false, "msg" => "Empresa não encontrada."]);
    exit;
}

// Conecta ao banco da empresa correta
$empresaDir = $empresas[$empresaKey];
$envFile = $_SERVER["DOCUMENT_ROOT"] . $rootAppPath . "/" . $empresaDir . "/.env";

if (!file_exists($envFile)) {
    echo json_encode(["ok" => false, "msg" => "Configuração da empresa não encontrada."]);
    exit;
}

// Carrega .env da empresa
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (strpos($line, '=') === false) continue;
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}

$conn = @mysqli_connect(
    $_ENV["DB_HOST"],
    $_ENV["DB_USER"],
    $_ENV["DB_PASSWORD"],
    $_ENV["DB_NAME"]
);
if (!$conn) {
    echo json_encode(["ok" => false, "msg" => "Erro de conexão com o banco."]);
    exit;
}
$conn->set_charset("utf8");

// Valida descritor recebido
$descritorRecebido = json_decode($descritorRaw, true);
if (!is_array($descritorRecebido) || count($descritorRecebido) < 10) {
    echo json_encode(["ok" => false, "msg" => "Descritor facial inválido."]);
    exit;
}

// Busca todos os usuários com biometria cadastrada
$rs = mysqli_query($conn,
    "SELECT user_nb_id, user_tx_nome, user_tx_login, user_tx_senha, user_nb_empresa,
            user_tx_face_descriptor
     FROM user
     WHERE user_tx_status = 'ativo'
       AND user_tx_face_descriptor IS NOT NULL
       AND user_tx_face_descriptor != ''
     LIMIT 2000"
);

if (!$rs) {
    echo json_encode(["ok" => false, "msg" => "Erro ao consultar banco."]);
    exit;
}

// Threshold mais rígido — distância máxima aceita
// Menor = mais exigente. 0.35 é bem restrito, 0.42 é moderado.
$THRESHOLD = 0.38;

$melhorDistancia = PHP_FLOAT_MAX;
$melhorUsuario   = null;

while ($row = mysqli_fetch_assoc($rs)) {
    $descBanco = json_decode($row["user_tx_face_descriptor"], true);
    if (!is_array($descBanco) || count($descBanco) !== count($descritorRecebido)) continue;

    // Distância euclidiana
    $soma = 0.0;
    foreach ($descBanco as $i => $v) {
        $diff = $v - ($descritorRecebido[$i] ?? 0);
        $soma += $diff * $diff;
    }
    $dist = sqrt($soma);

    if ($dist < $melhorDistancia) {
        $melhorDistancia = $dist;
        $melhorUsuario   = $row;
    }
}

if ($melhorUsuario && $melhorDistancia <= $THRESHOLD) {
    // Monta URL usando host real da requisição + APP_PATH do .env raiz
    $loginUrl = $urlBase . $rootAppPath . "/" . $empresaDir . "/index.php";

    echo json_encode([
        "ok"        => true,
        "user"      => $melhorUsuario["user_tx_login"],
        "password"  => $melhorUsuario["user_tx_senha"], // já é MD5
        "nome"      => $melhorUsuario["user_tx_nome"],
        "login_url" => $loginUrl,
        "distancia" => round($melhorDistancia, 4),
        "msg"       => "Usuário reconhecido: " . $melhorUsuario["user_tx_nome"]
    ]);
} else {
    echo json_encode([
        "ok"  => false,
        "msg" => "Rosto não reconhecido. Tente novamente ou use login/senha.",
        "distancia" => round($melhorDistancia, 4)
    ]);
}
