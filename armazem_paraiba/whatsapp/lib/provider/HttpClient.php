<?php

/**
 * Cliente HTTP simples para os provedores (cURL).
 */
class WaHttpClient {
    private static function protocolosSeguros(): array {
        return [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ];
    }

    private static function validarUrlMidia(string $url, array $allowedHosts = []): ?string {
        if (strlen($url) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return 'URL de mídia inválida.';
        }
        $partes = parse_url($url);
        if (!is_array($partes) || empty($partes['scheme']) || empty($partes['host'])) {
            return 'URL de mídia inválida.';
        }
        $scheme = strtolower(strval($partes['scheme']));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return 'Protocolo de mídia não permitido.';
        }
        if (isset($partes['user']) || isset($partes['pass'])) {
            return 'URL de mídia com credenciais não permitida.';
        }

        $host = strtolower(trim(strval($partes['host']), '[]'));
        $allowed = array_map(static fn($h) => strtolower(trim(strval($h), '[]')), $allowedHosts);
        $hostPermitido = in_array($host, $allowed, true);
        $porta = (int)($partes['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!$hostPermitido && !in_array($porta, [80, 443], true)) {
            return 'Porta de mídia não permitida.';
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolvidos = gethostbynamel($host);
            if (is_array($resolvidos)) {
                $ips = $resolvidos;
            }
        }
        if (empty($ips)) {
            return $hostPermitido ? null : 'Não foi possível validar o host da mídia.';
        }
        if (!$hostPermitido) {
            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return 'Destino de mídia privado ou reservado não permitido.';
                }
            }
        }
        return null;
    }

    public static function json(string $method, string $url, array $headers = [], ?array $payload = null, int $timeout = 25): array {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'raw' => '', 'error' => 'Não foi possível iniciar a requisição.'];
        }

        $headers[] = 'Accept: application/json';
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ] + self::protocolosSeguros());
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'status' => $status, 'data' => null, 'raw' => '', 'error' => $error !== '' ? $error : 'Falha de comunicação.'];
        }

        $data = json_decode($raw, true);
        $ok = $status >= 200 && $status < 300;
        if (!$ok && is_array($data)) {
            $msg = $data['error']['message'] ?? $data['response']['message'] ?? $data['message'] ?? $data['msg'] ?? ('HTTP ' . $status);
            if (is_array($msg)) {
                $msg = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            return ['ok' => false, 'status' => $status, 'data' => $data, 'raw' => $raw, 'error' => strval($msg)];
        }

        return [
            'ok' => $ok,
            'status' => $status,
            'data' => is_array($data) ? $data : null,
            'raw' => $raw,
            'error' => $ok ? '' : ('HTTP ' . $status),
        ];
    }

    /**
     * Baixa conteúdo binário (mídia) e devolve o corpo + content-type.
     */
    public static function raw(string $url, array $headers = [], int $timeout = 45, int $maxBytes = 0, array $allowedHosts = []): array {
        $erroUrl = self::validarUrlMidia($url, $allowedHosts);
        if ($erroUrl !== null) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'mime' => '', 'error' => $erroUrl];
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'mime' => '', 'error' => 'Não foi possível iniciar a requisição.'];
        }
        $opcoes = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ] + self::protocolosSeguros();
        if ($maxBytes > 0) {
            $opcoes[CURLOPT_MAXFILESIZE] = $maxBytes;
        }
        curl_setopt_array($ch, $opcoes);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $mime = strval(curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'mime' => $mime, 'error' => $error !== '' ? $error : 'Falha de comunicação.'];
        }
        if ($maxBytes > 0 && strlen($body) > $maxBytes) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'mime' => $mime, 'error' => 'Mídia acima do limite permitido.'];
        }
        $ok = $status >= 200 && $status < 300;
        return [
            'ok' => $ok,
            'status' => $status,
            'body' => $body,
            'mime' => $mime,
            'error' => $ok ? '' : ('HTTP ' . $status),
        ];
    }

    public static function multipart(string $url, array $headers, array $fields, int $timeout = 40): array {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'raw' => '', 'error' => 'Não foi possível iniciar a requisição.'];
        }

        $headers[] = 'Accept: application/json';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ] + self::protocolosSeguros());

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'status' => $status, 'data' => null, 'raw' => '', 'error' => $error !== '' ? $error : 'Falha de comunicação.'];
        }
        $data = json_decode($raw, true);
        $ok = $status >= 200 && $status < 300;
        $msg = $data['error']['message'] ?? $data['response']['message'] ?? $data['message'] ?? ('HTTP ' . $status);
        if (is_array($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return [
            'ok' => $ok,
            'status' => $status,
            'data' => is_array($data) ? $data : null,
            'raw' => $raw,
            'error' => $ok ? '' : strval($msg),
        ];
    }
}
