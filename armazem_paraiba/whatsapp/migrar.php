<?php
/**
 * Migração manual do módulo WhatsApp (CLI).
 * Uso: php whatsapp/migrar.php [--forcar]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Este arquivo só pode ser executado via CLI.';
    exit(1);
}

require_once __DIR__ . '/bootstrap_public.php';

$forcar = in_array('--forcar', $argv ?? [], true);

try {
    wa_migrar($conn, true);
    $versao = wa_meta_ler($conn, 'schema_version');
    echo "Migração do CRM - TECHPS concluída. Versão do schema: " . ($versao ?? '?') . "\n";
    echo "Tabelas wa_* e itens de menu verificados.\n";
    if ($forcar) {
        echo "Execução forçada concluída.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Erro na migração: " . $e->getMessage() . "\n");
    exit(1);
}
