<?php
/**
 * Testa o modo sem LLM (ASSISTENTE_PROVIDER=extrativo).
 * Uso: php testes/teste_extrativo.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';

$_ENV['ASSISTENTE_PROVIDER'] = 'extrativo';
$_SESSION['user_tx_nivel'] = 'Super Administrador';
$_SESSION['user_nb_id'] = 1;
$_SESSION['domain'] = '/braso/armazem_paraiba';

$falhas = 0;
function check(string $nome, bool $ok, string $detalhe = ''): void {
    global $falhas;
    if ($ok) {
        echo "PASS: {$nome}\n";
    } else {
        $falhas++;
        echo "FAIL: {$nome}" . ($detalhe !== '' ? " — {$detalhe}" : '') . "\n";
    }
}

check('LLM desligado', !ia_llm_ativo());

$faq = ia_busca_faq('Como usar as diárias?', null);
check('FAQ encontrada no banco', $faq !== null, $faq ? '' : 'nenhuma FAQ');

$chunks = ia_busca_chunks('Como funciona o totem facial?', 5);
check('busca trouxe trechos', !empty($chunks), 'total=' . count($chunks));

$t0 = microtime(true);
$r = ia_gerar_resposta('Como funciona o totem facial?', $chunks, []);
$ms = (int)round((microtime(true) - $t0) * 1000);
check('modo extrativo', ($r['modo'] ?? '') === 'extrativo', 'modo=' . ($r['modo'] ?? ''));
check('resposta não vazia', trim(strval($r['resposta'] ?? '')) !== '');
check('fontes presentes', !empty($r['fontes']));
check('sem chamada ao Ollama (rápido)', $ms < 3000, $ms . 'ms');

$fora = ia_busca_chunks('Qual é a capital da França?', 5);
$rf = ia_gerar_resposta('Qual é a capital da França?', $fora, []);
check('pergunta fora do sistema não inventa', ($rf['modo'] ?? '') === 'sem_base', 'modo=' . ($rf['modo'] ?? ''));

echo "\nResposta extrativa (primeiros 400 chars):\n";
echo mb_substr(strval($r['resposta'] ?? ''), 0, 400, 'UTF-8') . "\n";

echo $falhas === 0 ? "\nTodos os testes passaram.\n" : "\n{$falhas} teste(s) falharam.\n";
exit($falhas === 0 ? 0 : 1);
