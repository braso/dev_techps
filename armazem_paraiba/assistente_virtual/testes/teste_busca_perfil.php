<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';
$func = ia_db_one(
    "SELECT u.user_nb_id, u.user_tx_login, u.user_tx_nome
       FROM user u
       JOIN usuario_perfil up ON up.user_nb_id=u.user_nb_id AND up.ativo=1
      WHERE u.user_tx_status='ativo' AND u.user_tx_nivel='Funcionário' AND up.perfil_nb_id=2
      ORDER BY u.user_nb_id ASC LIMIT 1"
);
$_SESSION['user_tx_nivel'] = 'Funcionário';
$_SESSION['user_nb_id'] = (int)($func['user_nb_id'] ?? 0);
$_SESSION['user_tx_login'] = strval($func['user_tx_login'] ?? '');
$_SESSION['user_tx_nome'] = strval($func['user_tx_nome'] ?? '');
$_SESSION['domain'] = '/braso/armazem_paraiba';

$pergunta = $argv[1] ?? 'Como cadastrar um funcionário?';
echo "Pergunta: {$pergunta}\n";
echo "Telas: " . implode(', ', ia_telas_permitidas()) . "\n\n";
$chunks = ia_busca_chunks($pergunta, 8);
foreach ($chunks as $i => $c) {
    echo ($i + 1) . ') score=' . round((float)($c['score'] ?? 0), 3)
        . ' | ' . ($c['doc_tx_titulo'] ?? '') . ' — ' . ($c['chk_tx_titulo'] ?? '')
        . ' | path=' . ($c['chk_tx_menu_path'] ?? '') . ' | perfis=' . ($c['chk_tx_perfis'] ?? '') . "\n";
    echo '   ' . mb_substr(trim(str_replace("\n", ' ', $c['chk_tx_conteudo'] ?? '')), 0, 140, 'UTF-8') . "\n";
}
