<?php
/**
 * Bootstrap público do módulo WhatsApp (webhook e cron).
 * Não inicia sessão e não redireciona para login.
 */

if (!defined('WA_MODULE_DIR')) {
    define('WA_MODULE_DIR', __DIR__);
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? 'https';

require_once dirname(__DIR__) . '/load_env.php';

date_default_timezone_set('America/Fortaleza');

try {
    $conn = mysqli_connect(
        $_ENV['DB_HOST'] ?? '',
        $_ENV['DB_USER'] ?? '',
        $_ENV['DB_PASSWORD'] ?? '',
        $_ENV['DB_NAME'] ?? ''
    );
} catch (Throwable $e) {
    $conn = false;
    error_log('[whatsapp][public] falha na conexão: ' . $e->getMessage());
}
if (!$conn) {
    error_log('[whatsapp][public] falha na conexão: ' . mysqli_connect_error());
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        echo 'Indisponível.';
    }
    exit(1);
}
$conn->set_charset('utf8mb4');

require_once dirname(dirname(__DIR__)) . '/contex20/funcoes.php';

require_once __DIR__ . '/lib/log.php';
require_once __DIR__ . '/lib/seguranca.php';
require_once __DIR__ . '/lib/telefone.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/migracoes.php';

wa_migrar($conn);

require_once __DIR__ . '/lib/provider/ProviderFactory.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/contatos.php';
require_once __DIR__ . '/lib/fila.php';
require_once __DIR__ . '/lib/midia.php';
require_once __DIR__ . '/lib/mensagens.php';
