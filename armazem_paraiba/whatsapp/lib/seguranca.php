<?php
/**
 * Segurança do módulo WhatsApp: CSRF, criptografia de credenciais,
 * escape de saída, JSON e rate limit simples por sessão.
 */

if (!function_exists('wa_e')) {
    function wa_e($valor): string {
        return htmlspecialchars(strval($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wa_json')) {
    function wa_json(array $dados, int $status = 200): void {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('wa_csrf_token')) {
    function wa_csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['wa_csrf_token']) || !is_string($_SESSION['wa_csrf_token'])) {
            $_SESSION['wa_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['wa_csrf_token'];
    }
}

if (!function_exists('wa_csrf_validar')) {
    function wa_csrf_validar(?string $token): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $sessao = $_SESSION['wa_csrf_token'] ?? '';
        return is_string($sessao) && $sessao !== '' && is_string($token) && hash_equals($sessao, $token);
    }
}

if (!function_exists('wa_crypto_key')) {
    function wa_crypto_key(): string {
        $appKey = trim(strval($_ENV['APP_KEY'] ?? ''));
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY não configurada no .env do tenant.');
        }
        return hash('sha256', $appKey, true);
    }
}

if (!function_exists('wa_encrypt')) {
    function wa_encrypt(string $plain): string {
        $key = wa_crypto_key();

        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plain, $nonce, $key);
            return 'sodium:' . base64_encode($nonce . $cipher);
        }

        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($cipher === false) {
                throw new RuntimeException('Falha ao criptografar credencial.');
            }
            return 'gcm:' . base64_encode($iv . $tag . $cipher);
        }

        throw new RuntimeException('Nem sodium nem openssl estão disponíveis para criptografar credenciais.');
    }
}

if (!function_exists('wa_decrypt')) {
    function wa_decrypt(string $encoded): string {
        if ($encoded === '') {
            return '';
        }
        $key = wa_crypto_key();

        try {
            if (str_starts_with($encoded, 'gcm:')) {
                $raw = base64_decode(substr($encoded, 4), true);
                if ($raw === false || strlen($raw) < 28) {
                    return '';
                }
                $iv = substr($raw, 0, 12);
                $tag = substr($raw, 12, 16);
                $cipher = substr($raw, 28);
                $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
                return $plain === false ? '' : $plain;
            }

            if (str_starts_with($encoded, 'sodium:')) {
                if (!function_exists('sodium_crypto_secretbox_open')) {
                    return '';
                }
                $raw = base64_decode(substr($encoded, 7), true);
                if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                    return '';
                }
                $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
                return $plain === false ? '' : $plain;
            }

            // Formato legado do plano (nonce + cipher, sem prefixo).
            if (function_exists('sodium_crypto_secretbox_open')) {
                $raw = base64_decode($encoded, true);
                if ($raw !== false && strlen($raw) > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                    $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                    $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
                    return $plain === false ? '' : $plain;
                }
            }
        } catch (Throwable $e) {
            error_log('[whatsapp][crypto] ' . $e->getMessage());
        }

        return '';
    }
}

if (!function_exists('wa_rate_limit')) {
    /**
     * Rate limit em memória de sessão. Retorna true quando a ação é permitida.
     */
    function wa_rate_limit(string $chave, int $limite, int $janelaSegundos = 60): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true;
        }
        $agora = time();
        $bucket = $_SESSION['wa_rate_limit'][$chave] ?? [];
        if (!is_array($bucket)) {
            $bucket = [];
        }
        $bucket = array_values(array_filter($bucket, function ($ts) use ($agora, $janelaSegundos) {
            return is_int($ts) && ($agora - $ts) < $janelaSegundos;
        }));
        if (count($bucket) >= $limite) {
            $_SESSION['wa_rate_limit'][$chave] = $bucket;
            return false;
        }
        $bucket[] = $agora;
        $_SESSION['wa_rate_limit'][$chave] = $bucket;
        return true;
    }
}

if (!function_exists('wa_base_url')) {
    function wa_base_url(): string {
        return rtrim(strval($_ENV['URL_BASE'] ?? ''), '/')
            . '/' . trim(strval($_ENV['APP_PATH'] ?? ''), '/')
            . '/' . trim(strval($_ENV['CONTEX_PATH'] ?? ''), '/');
    }
}

if (!function_exists('wa_module_url')) {
    function wa_module_url(string $path = ''): string {
        return wa_base_url() . '/whatsapp' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('wa_webhook_url')) {
    function wa_webhook_url(): string {
        return wa_module_url('webhook.php');
    }
}
