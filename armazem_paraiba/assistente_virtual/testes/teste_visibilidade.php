<?php
/**
 * Teste unitário da visibilidade do assistente.
 * Não precisa de banco: valida apenas a regra do Super Admin x flag x perfil.
 * Uso: php testes/teste_visibilidade.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$GLOBALS['ia_teste_super'] = false;
function ehSuperAdmin(): bool {
    return (bool)$GLOBALS['ia_teste_super'];
}
function usuarioPodeAcessar($path): bool {
    return (bool)($GLOBALS['ia_teste_perfil'] ?? false);
}

require_once dirname(__DIR__) . '/lib/config.php';

$falhas = 0;
function check(string $nome, bool $ok): void {
    global $falhas;
    if ($ok) {
        echo "PASS: {$nome}\n";
    } else {
        $falhas++;
        echo "FAIL: {$nome}\n";
    }
}

$_SESSION = ['user_tx_nivel' => 'Super Administrador'];
$GLOBALS['ia_teste_super'] = true;
$_ENV['ASSISTENTE_ENABLED'] = '0';
check('Super Admin vê com flag 0', ia_pode_ver_assistente());

$GLOBALS['ia_teste_super'] = false;
$_SESSION = ['user_tx_nivel' => 'Administrador'];
$_ENV['ASSISTENTE_ENABLED'] = '0';
check('Administrador não vê com flag 0', !ia_pode_ver_assistente());
$_ENV['ASSISTENTE_ENABLED'] = '1';
check('Administrador vê com flag 1', ia_pode_ver_assistente());

$_SESSION = ['user_tx_nivel' => 'Funcionário'];
$GLOBALS['ia_teste_perfil'] = false;
$_ENV['ASSISTENTE_ENABLED'] = '1';
check('Funcionário sem perfil não vê', !ia_pode_ver_assistente());
$GLOBALS['ia_teste_perfil'] = true;
check('Funcionário com perfil vê', ia_pode_ver_assistente());
$_ENV['ASSISTENTE_ENABLED'] = '0';
check('Funcionário com perfil não vê com flag 0', !ia_pode_ver_assistente());

echo $falhas === 0 ? "\nTodos os testes passaram.\n" : "\n{$falhas} teste(s) falharam.\n";
exit($falhas === 0 ? 0 : 1);
