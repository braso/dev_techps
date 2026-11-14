<?php
/**
 * Embeddings locais via Ollama. Opcional: a busca lexical funciona sem eles.
 */

if (!function_exists('ia_ollama_base')) {
    function ia_ollama_base(): string {
        $s = ia_settings();
        return rtrim($s['ollama_url'], '/');
    }
}

if (!function_exists('ia_http_json')) {
    function ia_http_json(string $url, array $payload, int $timeout = 30): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            return ['ok' => false, 'erro' => $err ?: 'Falha de conexão', 'http' => 0];
        }
        $json = json_decode($resp, true);
        if (!is_array($json)) {
            return ['ok' => false, 'erro' => 'Resposta inválida do Ollama', 'http' => $http];
        }
        return ['ok' => $http >= 200 && $http < 300, 'http' => $http, 'dados' => $json];
    }
}

if (!function_exists('ia_ollama_embed')) {
    /** Gera o embedding de um texto usando o modelo local. */
    function ia_ollama_embed(string $texto): ?array {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }
        $s = ia_settings();
        $base = ia_ollama_base();
        $modelo = $s['embed_model'];

        // API nova (/api/embed) com fallback para a legada (/api/embeddings).
        $r = ia_http_json($base . '/api/embed', [
            'model' => $modelo,
            'input' => $texto,
        ], 60);
        if ($r['ok']) {
            $vec = $r['dados']['embeddings'][0] ?? null;
            if (is_array($vec) && !empty($vec)) {
                return array_map('floatval', $vec);
            }
        }

        $r = ia_http_json($base . '/api/embeddings', [
            'model' => $modelo,
            'prompt' => $texto,
        ], 60);
        if ($r['ok']) {
            $vec = $r['dados']['embedding'] ?? null;
            if (is_array($vec) && !empty($vec)) {
                return array_map('floatval', $vec);
            }
        }
        return null;
    }
}

if (!function_exists('ia_embedding_json')) {
    function ia_embedding_json(?array $vec): ?string {
        if (!is_array($vec) || empty($vec)) {
            return null;
        }
        return json_encode(array_map(fn($v) => round((float)$v, 6), $vec));
    }
}

if (!function_exists('ia_embedding_from_json')) {
    function ia_embedding_from_json(?string $json): ?array {
        if ($json === null || trim($json) === '') {
            return null;
        }
        $vec = json_decode($json, true);
        return is_array($vec) && !empty($vec) ? $vec : null;
    }
}

if (!function_exists('ia_cosine')) {
    function ia_cosine(array $a, array $b): float {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = 0.0; $na = 0.0; $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $x = (float)$a[$i];
            $y = (float)$b[$i];
            $dot += $x * $y;
            $na += $x * $x;
            $nb += $y * $y;
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }
}

if (!function_exists('ia_embeddings_ativos')) {
    function ia_embeddings_ativos(): bool {
        return ia_env('ASSISTENTE_EMBEDDINGS', '0') === '1';
    }
}
