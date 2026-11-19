<?php
/**
 * API do Assistente Virtual.
 *
 * Ações: ask, feedback, historico, mensagens, nova_conversa, sugestoes.
 * A geração é 100% local (Ollama); nenhum conteúdo sai da infraestrutura.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/bootstrap.php';

if (!ia_pode_ver_assistente()) {
    ia_json_out(['ok' => false, 'erro' => 'Sem permissão para usar o assistente.'], 403);
}

if (!function_exists('ia_sse_start')) {
    function ia_sse_start(): void {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        if (!headers_sent()) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache, no-transform');
            header('X-Accel-Buffering: no');
        }
        echo ": conectado\n\n";
        @flush();
    }
}

if (!function_exists('ia_sse_event')) {
    function ia_sse_event(array $dados): void {
        echo 'data: ' . json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        @ob_flush();
        @flush();
    }
}

if (!function_exists('ia_sse_delta')) {
    function ia_sse_delta(string $texto): void {
        if ($texto === '') { return; }
        ia_sse_event(['delta' => $texto]);
    }
}

if (!function_exists('ia_limpar_resposta_direta')) {
    /** Remove fontes inline; a interface já mostra o bloco de fontes separado. */
    function ia_limpar_resposta_direta(string $texto): string {
        $texto = preg_replace('/^\s*\*{0,2}Fonte:?\*{0,2}.*$/mi', '', $texto);
        $texto = preg_replace('/^\s*\*{0,2}Fontes consultadas:?\*{0,2}.*$/mis', '', $texto);
        $texto = preg_replace("/\n{3,}/", "\n\n", $texto);
        return trim($texto);
    }
}

if (!function_exists('ia_api_resposta_direta')) {
    /** Salva, registra e devolve uma resposta que não passou pelo LLM. */
    function ia_api_resposta_direta(int $convId, string $pergunta, string $resposta, array $fontes, string $modo, string $tela, bool $stream): void {
        $resposta = ia_limpar_resposta_direta($resposta);
        $tokens = ia_estimar_tokens($resposta);
        $msgId = ia_salvar_mensagem($convId, 'assistant', $resposta, $fontes, 0, $tokens, 0);
        ia_log_consulta($pergunta, [], $modo, 0, $tokens, 0, $modo, $convId, $tela);
        if ($stream) {
            session_write_close();
            ia_sse_start();
            ia_sse_delta($resposta);
            ia_sse_event([
                'done' => true,
                'conversa_id' => $convId,
                'mensagem_id' => $msgId,
                'fontes' => $fontes,
                'modo' => $modo,
            ]);
            exit;
        }
        ia_json_out([
            'ok' => true,
            'conversa_id' => $convId,
            'mensagem_id' => $msgId,
            'resposta' => $resposta,
            'fontes' => $fontes,
            'modo' => $modo,
        ]);
    }
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}
$acao = trim(strval($input['acao'] ?? ''));
$csrf = strval($input['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

if (!ia_csrf_validar($csrf)) {
    ia_json_out(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página e tente novamente.'], 419);
}

if ($acao === 'sugestoes') {
    $tela = trim(strval($input['tela'] ?? ''));
    ia_json_out(['ok' => true, 'sugestoes' => ia_sugestoes_por_tela($tela)]);
}

if ($acao === 'historico') {
    $conversas = ia_listar_conversas(20);
    foreach ($conversas as &$c) {
        $c['conv_dt_atualizacao_fmt'] = !empty($c['conv_dt_atualizacao'])
            ? date('d/m H:i', strtotime(strval($c['conv_dt_atualizacao'])))
            : '';
    }
    unset($c);
    ia_json_out(['ok' => true, 'conversas' => $conversas]);
}

if ($acao === 'mensagens') {
    $convId = (int)($input['conversa_id'] ?? 0);
    if (!ia_conversa_do_usuario($convId)) {
        ia_json_out(['ok' => false, 'erro' => 'Conversa não encontrada.'], 404);
    }
    $mensagens = ia_db_all(
        "SELECT msg_nb_id, msg_tx_papel, msg_tx_conteudo, msg_tx_fontes, msg_dt_criacao
           FROM ia_mensagem WHERE conv_nb_id=? ORDER BY msg_nb_id ASC",
        'i',
        [$convId]
    );
    foreach ($mensagens as &$m) {
        $m['fontes'] = json_decode(strval($m['msg_tx_fontes'] ?? '[]'), true) ?: [];
        unset($m['msg_tx_fontes']);
    }
    unset($m);
    ia_json_out(['ok' => true, 'mensagens' => $mensagens]);
}

if ($acao === 'nova_conversa') {
    $id = ia_criar_conversa('Nova conversa');
    ia_json_out(['ok' => true, 'conversa_id' => $id]);
}

if ($acao === 'feedback') {
    $msgId = (int)($input['mensagem_id'] ?? 0);
    $nota = (int)($input['nota'] ?? 0);
    $comentario = trim(strval($input['comentario'] ?? ''));
    $ok = ia_salvar_feedback($msgId, $nota, $comentario);
    ia_json_out(['ok' => $ok, 'erro' => $ok ? '' : 'Não foi possível registrar o feedback.'], $ok ? 200 : 400);
}

if ($acao !== 'ask') {
    ia_json_out(['ok' => false, 'erro' => 'Ação inválida.'], 400);
}

$pergunta = trim(strval($input['pergunta'] ?? ''));
if (mb_strlen($pergunta, 'UTF-8') < 2) {
    ia_json_out(['ok' => false, 'erro' => 'Digite uma pergunta.'], 400);
}
if (mb_strlen($pergunta, 'UTF-8') > 2000) {
    ia_json_out(['ok' => false, 'erro' => 'A pergunta é muito longa (máximo 2.000 caracteres).'], 400);
}
if (!ia_rate_limit_ok()) {
    ia_json_out(['ok' => false, 'erro' => 'Muitas perguntas em pouco tempo. Aguarde um minuto e tente novamente.'], 429);
}
if (!ia_quota_ok()) {
    ia_json_out(['ok' => false, 'erro' => 'Limite diário de perguntas atingido. Tente novamente amanhã ou abra um chamado de suporte.'], 429);
}

$stream = !empty($input['stream']);
$tela = trim(strval($input['tela'] ?? ''));
$convId = (int)($input['conversa_id'] ?? 0);
if ($convId > 0 && !ia_conversa_do_usuario($convId)) {
    $convId = 0;
}
$historico = $convId > 0 ? ia_ultimas_mensagens($convId, 6) : [];
if ($convId <= 0) {
    $convId = ia_criar_conversa(ia_titulo_conversa($pergunta));
}

ia_salvar_mensagem($convId, 'user', $pergunta);
ia_atualizar_conversa($convId);

if (ia_detectar_saudacao($pergunta)) {
    $resposta = "Olá! Sou o assistente do sistema. Posso ajudar com dúvidas de acesso, configuração e uso das telas. O que você precisa?";
    ia_api_resposta_direta($convId, $pergunta, $resposta, [], 'saudacao', $tela, $stream);
}

// Atalho determinístico: perguntas já respondidas na FAQ aprovada não
// precisam passar pelo LLM. Resposta instantânea e padronizada.
$telasPermitidas = ia_telas_permitidas();
$faq = ia_busca_faq($pergunta, null);
if ($faq && (float)($faq['_score'] ?? 0) >= 0.72) {
    $faqPermitido = ia_pode_ver_chunk([
        'chk_tx_menu_path' => $faq['faq_tx_menu_path'] ?? '',
        'chk_tx_perfis' => $faq['faq_tx_perfis'] ?? 'todos',
    ], $telasPermitidas);

    if ($faqPermitido) {
        $resposta = trim(strval($faq['faq_tx_resposta'] ?? ''));
        $fontes = [[
            'titulo' => 'Perguntas Frequentes do Sistema',
            'secao' => strval($faq['faq_tx_pergunta'] ?? ''),
            'caminho' => strval($faq['faq_tx_menu_path'] ?? ''),
            'fonte' => 'faq',
            'versao' => '',
        ]];
        $modo = 'faq';
    } else {
        $resposta = "Essa rotina é restrita ao perfil administrativo. Procure o RH ou o suporte para orientação sobre este cadastro.";
        $fontes = [];
        $modo = 'restrito';
    }

    ia_api_resposta_direta($convId, $pergunta, $resposta, $fontes, $modo, $tela, $stream);
}

$chunks = ia_busca_chunks($pergunta, ia_settings()['top_chunks']);

if ($stream) {
    session_write_close();
    ia_sse_start();
    $onDelta = function (string $delta): void {
        ia_sse_delta($delta);
    };
    $r = ia_gerar_resposta($pergunta, $chunks, $historico, $onDelta, true, false);
    if (in_array(($r['modo'] ?? ''), ['sem_base', 'base_vazia', 'extrativo'], true)) {
        ia_sse_delta(strval($r['resposta'] ?? ''));
    }
    $msgId = ia_salvar_mensagem(
        $convId,
        'assistant',
        strval($r['resposta'] ?? ''),
        $r['fontes'] ?? [],
        (int)($r['tokens_entrada'] ?? 0),
        (int)($r['tokens_saida'] ?? 0),
        (int)($r['latencia_ms'] ?? 0)
    );
    ia_log_consulta(
        $pergunta,
        $chunks,
        strval($r['modelo'] ?? ''),
        (int)($r['tokens_entrada'] ?? 0),
        (int)($r['tokens_saida'] ?? 0),
        (int)($r['latencia_ms'] ?? 0),
        strval($r['modo'] ?? 'llm'),
        $convId,
        $tela
    );
    ia_sse_event([
        'done' => true,
        'conversa_id' => $convId,
        'mensagem_id' => $msgId,
        'fontes' => $r['fontes'] ?? [],
        'modo' => $r['modo'] ?? 'llm',
    ]);
    exit;
}

$r = ia_gerar_resposta($pergunta, $chunks, $historico, null, false, false);
$msgId = ia_salvar_mensagem(
    $convId,
    'assistant',
    strval($r['resposta'] ?? ''),
    $r['fontes'] ?? [],
    (int)($r['tokens_entrada'] ?? 0),
    (int)($r['tokens_saida'] ?? 0),
    (int)($r['latencia_ms'] ?? 0)
);
ia_log_consulta(
    $pergunta,
    $chunks,
    strval($r['modelo'] ?? ''),
    (int)($r['tokens_entrada'] ?? 0),
    (int)($r['tokens_saida'] ?? 0),
    (int)($r['latencia_ms'] ?? 0),
    strval($r['modo'] ?? 'llm'),
    $convId,
    $tela
);

ia_json_out([
    'ok' => true,
    'conversa_id' => $convId,
    'mensagem_id' => $msgId,
    'resposta' => strval($r['resposta'] ?? ''),
    'fontes' => $r['fontes'] ?? [],
    'modo' => $r['modo'] ?? 'llm',
    'aviso' => strval($r['aviso'] ?? ''),
]);
