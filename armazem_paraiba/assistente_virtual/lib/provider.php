<?php
/**
 * Provedor local de geração de respostas (Ollama).
 * Nenhuma chamada sai da infraestrutura do cliente.
 */

if (!function_exists('ia_estimar_tokens')) {
    function ia_estimar_tokens(string $texto): int {
        return (int)ceil(mb_strlen($texto, 'UTF-8') / 4);
    }
}

if (!function_exists('ia_escolher_modelo')) {
    /** Roteia perguntas simples para o modelo rápido e complexas para o maior. */
    function ia_escolher_modelo(string $pergunta): string {
        $s = ia_settings();
        if (empty($s['router'])) {
            return $s['model'];
        }
        $n = ia_normalizar_busca($pergunta);
        $complexos = [
            'cnpj', 'desativ', 'endosso', 'banco de horas',
            'rescis', 'salario', 'folha', 'juridic', 'legal',
            'calculo', 'parametro', 'convencao', 'acordo', 'sindicato',
        ];
        foreach ($complexos as $termo) {
            if ($termo !== '' && strpos($n, $termo) !== false) {
                return $s['model_complex'];
            }
        }
        return $s['model'];
    }
}

if (!function_exists('ia_ollama_chat')) {
    /**
     * Chama /api/chat do Ollama.
     *
     * @param callable|null $onDelta Recebe cada pedaço de texto quando $stream=true.
     */
    function ia_ollama_chat(array $mensagens, bool $stream = false, ?callable $onDelta = null, ?string $modelo = null): array {
        $s = ia_settings();
        if (($s['provider'] ?? 'ollama') !== 'ollama') {
            return ['ok' => false, 'erro' => 'Provedor não suportado nesta versão: ' . $s['provider'], 'http' => 0];
        }
        $modelo = ($modelo !== null && trim($modelo) !== '') ? trim($modelo) : $s['model'];
        $url = ia_ollama_base() . '/api/chat';
        $opcoes = [
            'temperature' => $s['temperature'],
            'num_predict' => $s['max_tokens'],
            'num_ctx' => $s['num_ctx'],
            'top_p' => 0.8,
        ];
        if (!empty($s['num_thread'])) {
            $opcoes['num_thread'] = $s['num_thread'];
        }
        $payload = [
            'model' => $modelo,
            'messages' => $mensagens,
            'stream' => $stream,
            'keep_alive' => $s['keep_alive'],
            'options' => $opcoes,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => $s['timeout'],
        ]);

        if ($stream) {
            $buffer = '';
            $completo = '';
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$buffer, &$completo, $onDelta) {
                $buffer .= $data;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $linha = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);
                    if ($linha === '') { continue; }
                    $json = json_decode($linha, true);
                    if (!is_array($json)) { continue; }
                    $delta = strval($json['message']['content'] ?? '');
                    if ($delta !== '') {
                        $completo .= $delta;
                        if (is_callable($onDelta)) {
                            $onDelta($delta);
                        }
                    }
                }
                return strlen($data);
            });
            $ok = curl_exec($ch);
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $erro = curl_error($ch);
            curl_close($ch);
            if ($ok === false) {
                return ['ok' => false, 'erro' => $erro ?: 'Falha ao chamar o Ollama', 'http' => $http, 'texto' => $completo];
            }
            return ['ok' => true, 'texto' => $completo, 'http' => $http];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            return ['ok' => false, 'erro' => $erro ?: 'Falha ao chamar o Ollama', 'http' => $http, 'texto' => ''];
        }
        $json = json_decode($resp, true);
        if (!is_array($json)) {
            return ['ok' => false, 'erro' => 'Resposta inválida do Ollama', 'http' => $http, 'texto' => ''];
        }
        $texto = strval($json['message']['content'] ?? ($json['response'] ?? ''));
        if ($http < 200 || $http >= 300) {
            return ['ok' => false, 'erro' => strval($json['error'] ?? 'Erro HTTP ' . $http), 'http' => $http, 'texto' => $texto];
        }
        return ['ok' => true, 'texto' => $texto, 'http' => $http];
    }
}

if (!function_exists('ia_ollama_modelos')) {
    function ia_ollama_modelos(): array {
        $url = ia_ollama_base() . '/api/tags';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 4,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $http < 200 || $http >= 300) {
            return [];
        }
        $json = json_decode($resp, true);
        $nomes = [];
        foreach (($json['models'] ?? []) as $m) {
            if (!empty($m['name'])) { $nomes[] = strval($m['name']); }
        }
        return $nomes;
    }
}

if (!function_exists('ia_gerar_resposta')) {
    /**
     * Gera a resposta final. Se o LLM local falhar, devolve resposta extrativa
     * da própria base — o assistente continua útil em modo degradado.
     */
    function ia_gerar_resposta(string $pergunta, array $chunks, array $historico = [], ?callable $onDelta = null, bool $stream = false, bool $anexarFontes = true): array {
        $inicio = microtime(true);
        $modelo = ia_escolher_modelo($pergunta);
        $fontes = ia_fontes($chunks);
        if (empty($chunks)) {
            return [
                'ok' => true,
                'resposta' => "Não encontrei essa informação no manual do sistema. Posso abrir um chamado de suporte para você?",
                'modo' => 'sem_base',
                'fontes' => [],
                'tokens_entrada' => 0,
                'tokens_saida' => 0,
                'latencia_ms' => (int)round((microtime(true) - $inicio) * 1000),
                'modelo' => $modelo,
            ];
        }

        // Evita gerar resposta quando a recuperação só trouxe conteúdo fraco.
        // Sem essa barreira, o modelo tende a "completar" com passos inventados.
        $topScore = (float)($chunks[0]['score'] ?? 0);
        if ($topScore < (float)ia_settings()['min_score']) {
            return [
                'ok' => true,
                'resposta' => "Não encontrei essa informação no manual do sistema. Posso abrir um chamado de suporte para você?",
                'modo' => 'sem_base',
                'fontes' => [],
                'tokens_entrada' => 0,
                'tokens_saida' => 0,
                'latencia_ms' => (int)round((microtime(true) - $inicio) * 1000),
                'modelo' => $modelo,
            ];
        }

        $mensagens = ia_montar_mensagens($pergunta, $chunks, $historico);
        $r = ia_ollama_chat($mensagens, $stream, $onDelta, $modelo);
        $texto = ia_limpar_resposta_llm(strval($r['texto'] ?? ''));

        if (empty($r['ok']) || $texto === '') {
            $fallback = ia_resposta_extrativa($chunks, $pergunta);
            if ($stream && is_callable($onDelta)) {
                $onDelta($fallback);
            }
            return [
                'ok' => true,
                'resposta' => $fallback,
                'modo' => 'extrativo',
                'fontes' => $fontes,
                'tokens_entrada' => 0,
                'tokens_saida' => 0,
                'latencia_ms' => (int)round((microtime(true) - $inicio) * 1000),
                'modelo' => 'extrativo',
                'aviso' => $r['erro'] ?? '',
            ];
        }

        // Reforça a citação com as fontes reais recuperadas. Isso evita que o
        // modelo invente um caminho de tela ou esqueça a origem.
        if (!empty($fontes)) {
            $linhas = [];
            foreach ($fontes as $f) {
                $linha = '- ' . $f['titulo'];
                if (!empty($f['secao'])) { $linha .= ' — ' . $f['secao']; }
                if (!empty($f['caminho'])) { $linha .= ' (`' . $f['caminho'] . '`)'; }
                $linhas[] = $linha;
            }
            $bloco = "**Fontes consultadas:**\n" . implode("\n", $linhas);
            if ($anexarFontes && stripos($texto, 'fontes consultadas') === false && stripos($texto, 'fonte:') === false) {
                $texto .= "\n\n" . $bloco;
            }
        }

        return [
            'ok' => true,
            'resposta' => $texto,
            'modo' => 'llm',
            'fontes' => $fontes,
            'tokens_entrada' => ia_estimar_tokens(ia_contexto($chunks) . $pergunta),
            'tokens_saida' => ia_estimar_tokens($texto),
            'latencia_ms' => (int)round((microtime(true) - $inicio) * 1000),
            'modelo' => $modelo,
        ];
    }
}
