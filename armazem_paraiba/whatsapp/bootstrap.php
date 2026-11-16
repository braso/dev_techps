<?php
/**
 * Bootstrap das telas autenticadas do módulo WhatsApp.
 * Carrega sessão, conexão, permissões e helpers do módulo.
 */

if (PHP_SAPI !== 'cli'
    && basename(strval($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

if (!defined('WA_MODULE_DIR')) {
    define('WA_MODULE_DIR', __DIR__);
}

require_once dirname(__DIR__) . '/load_env.php';
// A sessão é iniciada por conecta.php, depois do endurecimento do cookie.
require_once dirname(__DIR__) . '/conecta.php';
require_once dirname(__DIR__) . '/check_permission.php';

require_once __DIR__ . '/lib/log.php';
require_once __DIR__ . '/lib/seguranca.php';
wa_security_headers();
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, private');
}
require_once __DIR__ . '/lib/telefone.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/data.php';
wa_db_timezone_utc();
require_once __DIR__ . '/migracoes.php';

wa_migrar($conn);

require_once __DIR__ . '/lib/provider/ProviderFactory.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/contatos.php';
require_once __DIR__ . '/lib/fila.php';
require_once __DIR__ . '/lib/midia.php';
require_once __DIR__ . '/lib/mensagens_prontas.php';
require_once __DIR__ . '/lib/mensagens.php';
require_once __DIR__ . '/lib/chamadas.php';
