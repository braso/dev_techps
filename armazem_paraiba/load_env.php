<?php
    if (PHP_SAPI !== 'cli'
        && basename(strval($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
        http_response_code(404);
        exit;
    }

    /**
     * Carrega o .env do tenant.
     *
     * Preferência:
     * 1. TECHPS_ENV_FILE (caminho absoluto definido no servidor);
     * 2. arquivo fora do document root, no diretório pai do projeto;
     * 3. .env legado na pasta do projeto (apenas se explicitamente permitido).
     */
    $envCandidatos = [];
    $envExplicito = trim(strval(getenv('TECHPS_ENV_FILE') ?: ''));
    if ($envExplicito !== '') {
        $envCandidatos[] = $envExplicito;
    }

    // Normalmente /var/www/techps.env, /home/usuario/techps.env etc.
    $envCandidatos[] = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'techps.env';
    $envCandidatos[] = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'techps.env';

    // Compatibilidade temporária: só carrega o .env público se a instalação
    // ainda não tiver sido migrada e a variável abaixo estiver habilitada.
    if (trim(strval(getenv('TECHPS_ALLOW_PUBLIC_ENV') ?: '')) === '1') {
        $envCandidatos[] = __DIR__ . DIRECTORY_SEPARATOR . '.env';
    }

    $envFilePath = '';
    $docroot = realpath(strval($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $permitirPublico = trim(strval(getenv('TECHPS_ALLOW_PUBLIC_ENV') ?: '')) === '1';
    foreach (array_unique($envCandidatos) as $candidato) {
        if (!is_string($candidato) || $candidato === '' || !is_file($candidato) || !is_readable($candidato)) {
            continue;
        }
        $candidatoReal = realpath($candidato);
        if (!$permitirPublico && $docroot !== false && $docroot !== '' && $candidatoReal !== false
            && str_starts_with($candidatoReal, rtrim($docroot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            error_log('[env] ignorando arquivo de ambiente dentro do document root: ' . $candidato);
            continue;
        }
        $envFilePath = $candidato;
        break;
    }

    if ($envFilePath !== '') {
        $env = parse_ini_file($envFilePath);
        if (is_array($env)) {
            foreach ($env as $attr => $val) {
                $v = is_string($val) ? trim($val) : $val;
                if (is_string($v) && strlen($v) >= 2) {
                    $first = $v[0];
                    $last = $v[strlen($v) - 1];
                    if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
                        $v = substr($v, 1, -1);
                    }
                }
                putenv("{$attr}={$v}");
                $_ENV[$attr] = $v;
            }
            $_ENV["URL_BASE"] = ($_SERVER["REQUEST_SCHEME"] ?? "http") . "://{$_SERVER["HTTP_HOST"]}";
        }
    }
?>
