<?php

/**
 * Cliente HTTP simples para os provedores (cURL).
 */
class WaHttpClient {
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
        ]);
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
    public static function raw(string $url, array $headers = [], int $timeout = 45): array {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'mime' => '', 'error' => 'Não foi possível iniciar a requisição.'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $mime = strval(curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'mime' => $mime, 'error' => $error !== '' ? $error : 'Falha de comunicação.'];
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
        ]);

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
