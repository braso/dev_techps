<?php
/**
 * Cron do módulo WhatsApp.
 * Executar somente via CLI:
 *   php /caminho/do/tenant/whatsapp/cron.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Este arquivo só pode ser executado via CLI.';
    exit(1);
}

require_once __DIR__ . '/bootstrap_public.php';
if (trim(strval($_ENV['WHATSAPP_ENABLED'] ?? '')) !== '1') {
    echo "Módulo CRM - TECHPS desativado para este tenant.\n";
    exit(0);
}
set_time_limit(0);
@ini_set('memory_limit', '256M');

$lockName = 'whatsapp_' . substr(hash('sha256', strval($_ENV['DB_NAME'] ?? 'default')), 0, 12) . '.lock';
$lockFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $lockName;
$lock = fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Outra execução do cron CRM - TECHPS já está em andamento.\n");
    exit(0);
}

$codigoSaida = 0;
try {
    $saida = [
        'inicio' => date('Y-m-d H:i:s'),
        'fila' => wa_processar_fila(20),
    ];

    $ultimaSync = wa_meta_ler($conn, 'ultima_sincronizacao_contatos');
    if ($ultimaSync === null || substr($ultimaSync, 0, 10) !== date('Y-m-d')) {
        $saida['contatos'] = wa_sincronizar_contatos();
    }

    $ultimaLimpeza = wa_meta_ler($conn, 'ultima_limpeza_retencao');
    if ($ultimaLimpeza === null || substr($ultimaLimpeza, 0, 10) !== date('Y-m-d')) {
        $saida['retencao'] = ['removidas' => wa_limpar_retencao()];
        wa_meta_gravar($conn, 'ultima_limpeza_retencao', date('Y-m-d H:i:s'));
    }

    $saida['fim'] = date('Y-m-d H:i:s');
    echo json_encode($saida, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    wa_log('cron_erro', $e->getMessage(), [], 'error');
    fwrite(STDERR, "Erro no cron CRM - TECHPS: " . $e->getMessage() . "\n");
    $codigoSaida = 1;
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

exit($codigoSaida);
