<?php
/**
 * Ingestão da base de conhecimento do Assistente Virtual via CLI.
 *
 * Uso:
 *   php ingestao.php --all
 *   php ingestao.php --manual
 *   php ingestao.php --docs
 *   php ingestao.php --menu
 *   php ingestao.php --faq
 *   php ingestao.php --reset --all
 *   php ingestao.php --all --embeddings
 *   php ingestao.php --status
 *   php ingestao.php --limpar-retencao
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bootstrap.php';

$opcoes = $argv ?? [];
$fazerManual = in_array('--manual', $opcoes, true);
$fazerDocs = in_array('--docs', $opcoes, true);
$fazerMenu = in_array('--menu', $opcoes, true);
$fazerFaq = in_array('--faq', $opcoes, true);
$fazerTudo = in_array('--all', $opcoes, true) || (!$fazerManual && !$fazerDocs && !$fazerMenu && !$fazerFaq);
$resetar = in_array('--reset', $opcoes, true);
$comEmbeddings = in_array('--embeddings', $opcoes, true);

if (in_array('--status', $opcoes, true)) {
    echo json_encode(ia_relatorio_cobertura(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit;
}

if (in_array('--limpar-retencao', $opcoes, true)) {
    ia_limpar_retencao();
    echo "Retenção aplicada.\n";
    exit;
}

if ($comEmbeddings) {
    $_ENV['ASSISTENTE_EMBEDDINGS'] = '1';
    putenv('ASSISTENTE_EMBEDDINGS=1');
    echo "Embeddings habilitados para esta execução.\n";
}

if ($resetar) {
    ia_resetar_base();
    echo "Base de conhecimento limpa.\n";
}

$resultado = [];

if ($fazerTudo || $fazerManual) {
    $root = dirname(__DIR__, 2);
    $docx = $root . '/docs/Detalhamento_Manual_Espelho de Ponto - V08_30_03_2026.docx';
    $md = __DIR__ . '/knowledge/manual_espelho_ponto.md';
    if (is_file($docx) && ia_docx_disponivel()) {
        $resultado['manual'] = ia_ingestar_manual_docx($docx, $comEmbeddings);
    } elseif (is_file($md)) {
        $resultado['manual'] = ia_ingestar_manual_md($md, $comEmbeddings);
    } else {
        $resultado['manual'] = ['ok' => false, 'erro' => 'Manual não encontrado.'];
    }
}

if ($fazerTudo || $fazerDocs) {
    $resultado['docs'] = ia_ingestar_docs_existentes($comEmbeddings);
}

if ($fazerTudo || $fazerMenu) {
    $resultado['menu'] = ia_ingestar_mapa_telas($comEmbeddings);
}

if ($fazerTudo || $fazerFaq) {
    $resultado['faq'] = ia_ingestar_faq_seed($comEmbeddings);
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
