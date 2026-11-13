<?php
require_once __DIR__ . '/../lib/telefone.php';

$casos = [
    ['11987654321', '5511987654321'],
    ['(11) 98765-4321', '5511987654321'],
    ['011987654321', '5511987654321'],
    ['5511987654321', '5511987654321'],
    ['+55 (11) 98765-4321', '5511987654321'],
    ['1133334444', '551133334444'],
    ['551133334444', '551133334444'],
    ['123', null],
    ['', null],
    ['00987654321', null],
];

$falhas = 0;
foreach ($casos as [$entrada, $esperado]) {
    $obtido = wa_normalizar_telefone($entrada);
    $ok = $obtido === $esperado;
    if (!$ok) {
        $falhas++;
    }
    echo ($ok ? 'OK ' : 'FALHA ') . var_export($entrada, true) . ' => ' . var_export($obtido, true) . ' (esperado ' . var_export($esperado, true) . ")\n";
}

echo "\n" . ($falhas === 0 ? 'Todos os testes passaram.' : "{$falhas} teste(s) falharam.") . "\n";
exit($falhas === 0 ? 0 : 1);
