<?php
require_once __DIR__ . '/../lib/seguranca.php';

$_ENV['APP_KEY'] = $_ENV['APP_KEY'] ?? 'chave-de-teste-local';

$falhas = 0;
$texto = 'token-secreto-123';
try {
    $cifrado = wa_encrypt($texto);
    $decifrado = wa_decrypt($cifrado);
    $ok = $decifrado === $texto;
    echo ($ok ? 'OK ' : 'FALHA ') . "criptografia/descriptografia\n";
    if (!$ok) {
        $falhas++;
    }
} catch (Throwable $e) {
    $falhas++;
    echo 'FALHA criptografia: ' . $e->getMessage() . "\n";
}

echo "\n" . ($falhas === 0 ? 'Todos os testes passaram.' : "{$falhas} teste(s) falharam.") . "\n";
exit($falhas === 0 ? 0 : 1);
