<?php
/**
 * Testes de permissão do Assistente Virtual.
 * Uso: php testes/teste_permissoes.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';

$falhas = 0;
function ia_check(string $nome, bool $ok, string $detalhe = ''): void {
    global $falhas;
    if ($ok) {
        echo "PASS: {$nome}\n";
    } else {
        $falhas++;
        echo "FAIL: {$nome}" . ($detalhe !== '' ? " — {$detalhe}" : '') . "\n";
    }
}

function ia_sessao(string $nivel, int $userId): void {
    $_SESSION['user_tx_nivel'] = $nivel;
    $_SESSION['user_nb_id'] = $userId;
    $_SESSION['domain'] = '/braso/armazem_paraiba';
}

// Administrador: vê a FAQ administrativa.
ia_sessao('Super Administrador', 138);
$telasAdmin = ia_telas_permitidas();
$faqAdmin = ia_busca_faq('Como cadastrar um funcionário?', null);
ia_check('admin encontra FAQ de cadastro', $faqAdmin !== null);
if ($faqAdmin) {
    ia_check('admin pode ver o caminho da FAQ', ia_pode_ver_chunk([
        'chk_tx_menu_path' => $faqAdmin['faq_tx_menu_path'] ?? '',
        'chk_tx_perfis' => $faqAdmin['faq_tx_perfis'] ?? 'todos',
    ], $telasAdmin));
}

// Funcionário: não vê a FAQ administrativa.
ia_sessao('Funcionário', 206);
$telasFunc = ia_telas_permitidas();
$faqFunc = ia_busca_faq('Como cadastrar um funcionário?', null);
ia_check('funcionário encontra a pergunta, mas ela é restrita', $faqFunc !== null && !ia_pode_ver_chunk([
    'chk_tx_menu_path' => $faqFunc['faq_tx_menu_path'] ?? '',
    'chk_tx_perfis' => $faqFunc['faq_tx_perfis'] ?? 'todos',
], $telasFunc));

// Funcionário: vê a FAQ de senha (tela pública).
$faqSenha = ia_busca_faq('Esqueci minha senha, como recuperar?', null);
ia_check('funcionário pode ver FAQ de senha', $faqSenha !== null && ia_pode_ver_chunk([
    'chk_tx_menu_path' => $faqSenha['faq_tx_menu_path'] ?? '',
    'chk_tx_perfis' => $faqSenha['faq_tx_perfis'] ?? 'todos',
], $telasFunc));

// Funcionário: a busca não devolve caminho administrativo.
$chunks = ia_busca_chunks('Como cadastrar um funcionário?', 8);
$temAdmin = false;
foreach ($chunks as $c) {
    $path = ia_path_normalizado(strval($c['chk_tx_menu_path'] ?? ''));
    if ($path === '/cadastro_funcionario.php' || $path === '/correcao_funcionario.php') {
        $temAdmin = true;
        break;
    }
}
ia_check('busca do funcionário não traz cadastro de funcionário', !$temAdmin);

// Funcionário: pergunta do próprio ponto traz caminho permitido.
$chunksEspelho = ia_busca_chunks('Como consultar o espelho de ponto?', 8);
$temEspelho = false;
foreach ($chunksEspelho as $c) {
    if (ia_path_normalizado(strval($c['chk_tx_menu_path'] ?? '')) === '/espelho_ponto.php') {
        $temEspelho = true;
        break;
    }
}
ia_check('busca do funcionário traz o espelho de ponto', $temEspelho);

// Pergunta sem base: nenhum chunk relevante.
$chunksSemBase = ia_busca_chunks('Qual é a capital da França?', 8);
$topSemBase = (float)($chunksSemBase[0]['score'] ?? 0);
ia_check('pergunta fora do sistema fica abaixo do limiar', $topSemBase < (float)ia_settings()['min_score'], 'score=' . $topSemBase);

echo $falhas === 0 ? "\nTodos os testes passaram.\n" : "\n{$falhas} teste(s) falharam.\n";
exit($falhas === 0 ? 0 : 1);
