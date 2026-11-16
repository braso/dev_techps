<?php
/**
 * Teste de recuperação e geração local.
 * Uso: php testes/teste_busca.php ["pergunta"] [--llm]
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';

$_SESSION['user_tx_nivel'] = 'Super Administrador';
$_SESSION['user_nb_id'] = 1;
$_SESSION['user_tx_nome'] = 'Teste';

$pergunta = $argv[1] ?? 'Como cadastrar um funcionário?';
$comLlm = in_array('--llm', $argv, true);

echo "Pergunta: {$pergunta}\n\n";

$faq = ia_busca_faq($pergunta);
if ($faq) {
    echo "FAQ exata (score " . round((float)$faq['_score'], 3) . "):\n";
    echo $faq['faq_tx_resposta'] . "\n\n";
} else {
    echo "Nenhuma FAQ exata.\n\n";
}

$chunks = ia_busca_chunks($pergunta, 8);
echo "Chunks recuperados: " . count($chunks) . "\n";
foreach ($chunks as $i => $c) {
    echo ($i + 1) . ') score=' . round((float)($c['score'] ?? 0), 4)
        . ' | ' . ($c['doc_tx_titulo'] ?? '') . ' — ' . ($c['chk_tx_titulo'] ?? '')
        . ' | path=' . ($c['chk_tx_menu_path'] ?? '') . "\n";
    echo '   ' . mb_substr(trim(str_replace("\n", ' ', $c['chk_tx_conteudo'] ?? '')), 0, 160, 'UTF-8') . "\n";
}
echo "\n";

if ($comLlm) {
    $t0 = microtime(true);
    $r = ia_gerar_resposta($pergunta, $chunks, []);
    $dt = round(microtime(true) - $t0, 2);
    echo "Modo: {$r['modo']} | modelo: {$r['modelo']} | {$dt}s\n";
    echo "Resposta:\n{$r['resposta']}\n\n";
    echo "Fontes:\n" . json_encode($r['fontes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
