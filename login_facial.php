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

$empresaKey  = trim($_POST["empresa_key"] ?? "");
$descritorRaw = trim($_POST["descritor"] ?? "");

if (empty($empresaKey) || empty($descritorRaw)) {
    echo json_encode(["ok" => false, "msg" => "Dados insuficientes."]);
    exit;
}

// Mapeia empresa_key → path (mesmo mapa do empresas.php)
$empresas = [
    "ARMAZEMPARAIBA"  => "armazem_paraiba",
    "BLUEROAD"        => "blueroad",
    "BRASO"           => "braso",
    "CARAU"           => "carau_transporte",
    "COMAV"           => "comav",
    "FEIJAOTURQUEZA"  => "feijao_turqueza",
    "FSLOG"           => "fs_log_transportes",
    "HN"              => "hn_transportes",
    "IFRN"            => "ifrn",
    "JRJ"             => "jrj_organizacao",
    "LOGSYNC"         => "logsync_techps",
    "LEMON"           => "lemon",
    "NH"              => "nh_transportes",
    "OPAFRUTAS"       => "opafrutas",
    "PKFMEDEIROS"     => "pkf_medeiros",
    "QUALY"           => "qualy_transportes",
    "SÃO LUCAS"       => "sao_lucas",
    "TECHPS"          => "techps",
    "DEMO"            => "techps_demo",
    "TRAMPOLIMGAS"    => "trampolim_gas",
    "TRANSCOPEL"      => "transcopel",
    "PB TRANSPORTES"  => "pb_transportes",
    "ODONTO TANGARA"  => "odontotangara",
    "CLINICA GERLANE" => "clinica_gerlane",
    "IRANEIDE OLIVEIRA"=> "iraneide_oliveira",
    "MIDIA DIGITAL"   => "midia_digital",
    "ENOVE"           => "enove",
    "TMILITAO"        => "t_militao",
];

$empresaKey = strtoupper($empresaKey);
if (!array_key_exists($empresaKey, $empresas)) {
    echo json_encode(["ok" => false, "msg" => "Empresa não encontrada."]);
    exit;
}

// Conecta ao banco da empresa correta
$appPath = $_ENV["APP_PATH"] ?? "";
$empresaDir = $empresas[$empresaKey];
$envFile = $_SERVER["DOCUMENT_ROOT"] . $appPath . "/" . $empresaDir . "/.env";

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
    // URL_BASE dinâmica baseada no host real da requisição (ignora o .env)
    $urlBase  = ($_SERVER["REQUEST_SCHEME"] ?? "https") . "://" . $_SERVER["HTTP_HOST"];
    $loginUrl = $urlBase . ($_ENV["APP_PATH"] ?? "") . "/" . $empresaDir . "/index.php";

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
