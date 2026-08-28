<?php
/*
 * Processa os consumos automaticos de diarias (motor de regras).
 * Uso:
 *   CLI:  php processar_diarias.php [empresa_id] [data_fim=YYYY-MM-DD]
 *   WEB:  ?empresa=X&data_fim=YYYY-MM-DD (requer login)
 * Exemplos de agendamento (Task Scheduler/cron):
 *   php /caminho/processar_diarias.php          -> todas as empresas ate hoje
 *   php /caminho/processar_diarias.php 1        -> apenas empresa 1
 */

$ehCli = (PHP_SAPI === 'cli');

if ($ehCli) {
    include_once __DIR__."/../load_env.php";
    $conn = @mysqli_connect($_ENV["DB_HOST"], $_ENV["DB_USER"], $_ENV["DB_PASSWORD"], $_ENV["DB_NAME"]);
    if (!$conn) {
        fwrite(STDERR, "Falha de conexao: ".mysqli_connect_error().PHP_EOL);
        exit(1);
    }
    $conn->set_charset("utf8mb4");
    if (!function_exists('query')) {
        function query($sql, $types = '', array $vars = array()) {
            global $conn;
            if ($types !== '') {
                $stmt = mysqli_prepare($conn, $sql);
                if (!$stmt) { return false; }
                mysqli_stmt_bind_param($stmt, $types, ...$vars);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                mysqli_stmt_close($stmt);
                return $res;
            }
            return mysqli_query($conn, $sql);
        }
    }
    include_once __DIR__."/helpers_diarias.php";
    $empresaId = isset($argv[1]) ? intval($argv[1]) : 0;
    $dataFim = isset($argv[2]) ? $argv[2] : date('Y-m-d');
} else {
    include_once "../conecta.php";
    include_once "../check_permission.php";
    include_once __DIR__."/helpers_diarias.php";
    diar_ensureSchema();
    if (intval(diar_sessao('user_nb_id', 0)) <= 0) {
        header("Location: ../index.php");
        exit;
    }
    $empresaId = intval($_GET['empresa'] ?? 0);
    $dataFim = preg_replace('/[^0-9\-]/', '', strval($_GET['data_fim'] ?? date('Y-m-d')));
}

diar_ensureSchema();

$dataFim = preg_replace('/[^0-9\-]/', '', strval($dataFim));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
    $dataFim = date('Y-m-d');
}

$sql = "SELECT enti_nb_id FROM entidade
        WHERE enti_tx_status = 'ativo'
          AND enti_tx_ocupacao IN ('Motorista','Ajudante','Motorista Terceirizado','Terceirizado','Tercerizado')"
      . ($empresaId > 0 ? " AND enti_nb_empresa = ".intval($empresaId) : "");

$res = query($sql);
$processados = 0;
$gerados = 0;
if ($res instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($res)) {
        $processados++;
        $gerados += diar_gerarConsumosPendentes($row['enti_nb_id'], $dataFim);
    }
}

$msg = "Processamento de diarias: {$processados} funcionario(s) verificados, {$gerados} consumo(s) gerados automaticamente (ate {$dataFim}).";
diar_log_runtime($msg);

if ($ehCli) {
    echo $msg.PHP_EOL;
} else {
    header("Content-Type: text/plain; charset=utf-8");
    echo $msg.PHP_EOL;
}
