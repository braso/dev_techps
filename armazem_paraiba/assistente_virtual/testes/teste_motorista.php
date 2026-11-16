<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';
$_SESSION['user_nb_id'] = 0;
$_SESSION['user_tx_login'] = 'motorista.teste';
$_SESSION['user_tx_nome'] = 'Motorista Teste';
$_SESSION['user_tx_nivel'] = 'Motorista';
$_SESSION['domain'] = '/braso/armazem_paraiba';

echo 'pode_usar=' . (ia_pode_usar() ? 'sim' : 'nao') . "\n";
echo 'telas=' . implode(', ', ia_telas_permitidas()) . "\n";
$faq = ia_busca_faq('Como consultar o espelho de ponto?', null);
echo 'faq_espelho=' . ($faq ? 'sim' : 'nao') . "\n";
$faqAdmin = ia_busca_faq('Como cadastrar um funcionário?', null);
$telas = ia_telas_permitidas();
echo 'faq_admin_restrita=' . ($faqAdmin && !ia_pode_ver_chunk([
    'chk_tx_menu_path' => $faqAdmin['faq_tx_menu_path'] ?? '',
    'chk_tx_perfis' => $faqAdmin['faq_tx_perfis'] ?? 'todos',
], $telas) ? 'sim' : 'nao') . "\n";
