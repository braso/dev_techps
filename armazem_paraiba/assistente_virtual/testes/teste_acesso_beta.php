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

$admin = ia_db_one(
    "SELECT user_nb_id FROM user
      WHERE user_tx_status='ativo' AND user_tx_nivel LIKE '%Administrador%'
      ORDER BY (user_tx_nivel='Super Administrador') DESC, user_nb_id ASC LIMIT 1"
);
$func = ia_db_one(
    "SELECT u.user_nb_id, up.perfil_nb_id
       FROM user u
       JOIN usuario_perfil up ON up.user_nb_id=u.user_nb_id AND up.ativo=1
      WHERE u.user_tx_status='ativo' AND u.user_tx_nivel='Funcionário'
      ORDER BY u.user_nb_id ASC LIMIT 1"
);
if (!$admin || !$func) {
    echo "FAIL: admin ou funcionário ativo não encontrado.\n";
    exit(1);
}

$adminId = (int)$admin['user_nb_id'];
$funcId = (int)$func['user_nb_id'];
$perfilId = (int)$func['perfil_nb_id'];

ia_teste_sessao('Administrador', $adminId);
echo 'admin=' . (ia_pode_usar() ? 'sim' : 'nao') . "\n";

ia_teste_sessao('Funcionário', $funcId);
echo 'funcionario_sem_permissao=' . (ia_pode_usar() ? 'sim' : 'nao') . "\n";

ia_teste_sessao('Motorista', 0);
echo 'motorista_sem_perfil=' . (ia_pode_usar() ? 'sim' : 'nao') . "\n";

$menuId = (int)(ia_db_val("SELECT menu_nb_id FROM menu_item WHERE menu_tx_path='/assistente_virtual/index.php' LIMIT 1") ?? 0);
if ($menuId > 0 && $perfilId > 0) {
    ia_menu_liberar_perfil($conn, $perfilId, $menuId);
    ia_teste_sessao('Funcionário', $funcId);
    echo 'funcionario_apos_liberar=' . (ia_pode_usar() ? 'sim' : 'nao') . "\n";

    ia_menu_revogar_perfil($conn, $perfilId, $menuId);
    echo 'funcionario_apos_revogar=' . (ia_pode_usar() ? 'sim' : 'nao') . "\n";
}
