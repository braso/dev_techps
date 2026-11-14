<?php
/**
 * Testa a restrição beta do assistente:
 * - admin tem acesso;
 * - funcionário sem o item no perfil não tem;
 * - motorista sem perfil não tem;
 * - ao liberar o item no perfil, o funcionário passa a ter;
 * - ao revogar, volta a não ter.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';

function ia_teste_sessao(string $nivel, int $userId): void {
    $_SESSION['user_tx_nivel'] = $nivel;
    $_SESSION['user_nb_id'] = $userId;
    $_SESSION['domain'] = '/braso/armazem_paraiba';
}

ia_teste_sessao('Administrador', 138);
echo 'admin=' . (ia_pode_usar() ? 'sim' : 'nao') . "\n";

ia_teste_sessao('Funcionário', 206);
echo 'funcionario_sem_permissao=' . (ia_pode_usar() ? 'sim' : 'nao') . "\n";

ia_teste_sessao('Motorista', 0);
echo 'motorista_sem_perfil=' . (ia_pode_usar() ? 'sim' : 'nao') . "\n";

$menuId = (int)(ia_db_val("SELECT menu_nb_id FROM menu_item WHERE menu_tx_path='/assistente_virtual/index.php' LIMIT 1") ?? 0);
if ($menuId > 0) {
    ia_menu_liberar_perfil($conn, 1, $menuId);
    ia_teste_sessao('Funcionário', 206);
    echo 'funcionario_apos_liberar=' . (ia_pode_usar() ? 'sim' : 'nao') . "\n";

    ia_menu_revogar_perfil($conn, 1, $menuId);
    echo 'funcionario_apos_revogar=' . (ia_pode_usar() ? 'sim' : 'nao') . "\n";
}
