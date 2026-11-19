<?php
/**
 * Bootstrap das telas e APIs do Assistente Virtual.
 *
 * Carrega ambiente, conexão, permissões, migrações e helpers do módulo.
 */

if (PHP_SAPI !== 'cli'
    && basename(strval($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

if (!defined('IA_MODULE_DIR')) {
    define('IA_MODULE_DIR', __DIR__);
}

if (PHP_SAPI === 'cli') {
    // conecta.php redireciona para logout quando não há sessão de domínio.
    // Em CLI, assumimos execução interna e evitamos esse desvio.
    $interno = true;
    if (empty($_SERVER['REQUEST_SCHEME'])) { $_SERVER['REQUEST_SCHEME'] = 'http'; }
    if (empty($_SERVER['HTTP_HOST'])) { $_SERVER['HTTP_HOST'] = 'localhost'; }
    if (empty($_SERVER['REQUEST_URI'])) { $_SERVER['REQUEST_URI'] = '/cli'; }
    // No container, o projeto fica em /var/www/html/braso e o APP_PATH é /braso.
    // Em CLI o DOCUMENT_ROOT não é preenchido; sem ele, os includes do contex20 falham.
    if (empty($_SERVER['DOCUMENT_ROOT'])) {
        $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
    }
    $docRootOverride = getenv('IA_DOCUMENT_ROOT');
    if ($docRootOverride) {
        $_SERVER['DOCUMENT_ROOT'] = rtrim($docRootOverride, '/\\');
    }
}

require_once dirname(__DIR__) . '/load_env.php';
require_once dirname(__DIR__) . '/conecta.php';
require_once dirname(__DIR__) . '/check_permission.php';
require_once dirname(__DIR__) . '/menu_estrutura.php';

// O módulo trata erros de SQL como dados (loga e segue), nunca como fatal.
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/migracoes.php';

if (isset($conn) && $conn instanceof mysqli) {
    $schemaOk = (ia_meta_ler($conn, 'schema_version') === '1') && ia_tabela_existe($conn, 'ia_chunk');
    if (!$schemaOk) {
        ia_criar_tabelas($conn);
    }
    if (ia_meta_ler($conn, 'menu_version') !== '3') {
        ia_migrar_menu($conn);
    }
}

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/text.php';
require_once __DIR__ . '/lib/embeddings.php';
require_once __DIR__ . '/lib/ingest.php';
require_once __DIR__ . '/lib/retrieval.php';
require_once __DIR__ . '/lib/provider.php';
require_once __DIR__ . '/lib/audit.php';

// Se a base foi zerada por uma restauração de backup/reset, reconstrói a
// partir das fontes locais antes de atender a requisição. Em CLI a ingestão
// é sempre explícita (ingestao.php), então não roda aqui.
if (PHP_SAPI !== 'cli' && function_exists('ia_garantir_base')) {
    ia_garantir_base();
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, private');
}
