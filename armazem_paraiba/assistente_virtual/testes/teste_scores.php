<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';
$_SESSION['user_tx_nivel'] = 'Super Administrador';
$_SESSION['user_nb_id'] = 1;
$_SESSION['domain'] = '/braso/armazem_paraiba';

$perguntas = [
    'Como cadastrar um funcionário?',
    'Como bater o ponto?',
    'Como consultar o espelho de ponto?',
    'Como funciona o banco de horas?',
    'Como cadastrar um feriado?',
    'Como cadastrar férias?',
    'Como importar os pontos?',
    'Como liberar o acesso a uma tela?',
    'Como cadastrar um usuário?',
    'O que é uma não conformidade?',
    'O que é adicional noturno?',
    'Como funciona o totem facial?',
    'Como configurar o intervalo de almoço?',
    'Como emitir o relatório de horas extras?',
    'Como funciona a jornada 12x36?',
    'Como ajustar uma batida de ponto esquecida?',
    'Qual é a capital da França?',
    'Qual a previsão do tempo para amanhã?',
    'Quem ganhou o campeonato brasileiro?',
    'Como fazer um bolo de chocolate?',
];

foreach ($perguntas as $p) {
    $chunks = ia_busca_chunks($p, 5);
    $top = (float)($chunks[0]['score'] ?? 0);
    $titulo = $chunks[0]['doc_tx_titulo'] ?? '';
    $secao = $chunks[0]['chk_tx_titulo'] ?? '';
    printf("%6.2f | %-52s | %s — %s\n", $top, $p, $titulo, $secao);
}
