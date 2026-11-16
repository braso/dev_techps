<?php
/**
 * Executa as migrações do Assistente Virtual via CLI.
 *
 * Uso: php armazem_paraiba/assistente_virtual/migrar.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bootstrap.php';
ia_migrar($conn);
echo "Migração do Assistente Virtual concluída.\n";
