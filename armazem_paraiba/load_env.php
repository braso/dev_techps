<?php
    if (PHP_SAPI !== 'cli'
        && basename(strval($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
        http_response_code(404);
        exit;
    }

    /**
     * Carrega o .env do tenant.
     *
     * Ordem de busca:
     * 1. TECHPS_ENV_FILE (caminho absoluto definido via variável de ambiente do servidor);
     * 2. techps.env no diretório pai do projeto (fora do document root);
     * 3. .env local na pasta do projeto (__DIR__ . '/.env');
     * 4. .env na pasta raiz do repositório (dirname(__DIR__) . '/.env').
     */
    $envCandidatos = [];
    $envExplicito = trim(strval(getenv('TECHPS_ENV_FILE') ?: ''));
    if ($envExplicito !== '') {
        $envCandidatos[] = $envExplicito;
    }

    // Normalmente /var/www/techps.env, /home/usuario/techps.env etc.
    $envCandidatos[] = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'techps.env';
    $envCandidatos[] = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'techps.env';

    // .env local da pasta do tenant (ex: opafrutas/.env, armazem_paraiba/.env)
    $envCandidatos[] = __DIR__ . DIRECTORY_SEPARATOR . '.env';

    // .env na pasta pai/raiz do projeto (fallback)
    $envCandidatos[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

    $envFilePath = '';
    foreach (array_unique($envCandidatos) as $candidato) {
        if (!is_string($candidato) || $candidato === '' || !is_file($candidato) || !is_readable($candidato)) {
            continue;
        }
        $envFilePath = $candidato;
        break;
    }

    if ($envFilePath !== '') {
        $env = parse_ini_file($envFilePath, false, INI_SCANNER_RAW);
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
            if (!empty($_SERVER["HTTP_HOST"])) {
                $scheme = (!empty($_SERVER['HTTPS']) && strtolower(strval($_SERVER['HTTPS'])) !== 'off')
                    || strtolower(strval($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
                    ? 'https'
                    : strval($_SERVER["REQUEST_SCHEME"] ?? "http");
                $_ENV["URL_BASE"] = "{$scheme}://{$_SERVER["HTTP_HOST"]}";
            } elseif (empty($_ENV["URL_BASE"])) {
                $_ENV["URL_BASE"] = "http://localhost";
            }
        }
    }
?>
