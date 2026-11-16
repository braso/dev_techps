<?php
/**
 * Configuração e helpers do Assistente Virtual.
 */

if (!defined('IA_MODULE_DIR')) {
    define('IA_MODULE_DIR', dirname(__DIR__));
}

if (!function_exists('ia_env')) {
    function ia_env(string $chave, string $padrao = ''): string {
        $valor = $_ENV[$chave] ?? getenv($chave);
        if ($valor === false || $valor === null) {
            return $padrao;
        }
        return trim(strval($valor));
    }
}

if (!function_exists('ia_module_url')) {
    function ia_module_url(string $caminho = ''): string {
        global $CONTEX;
        $base = rtrim(strval($CONTEX['path'] ?? ''), '/');
        if ($base === '') {
            $base = rtrim(ia_env('APP_PATH', '') . ia_env('CONTEX_PATH', ''), '/');
        }
        return $base . '/assistente_virtual' . ($caminho !== '' ? '/' . ltrim($caminho, '/') : '');
    }
}

if (!function_exists('ia_e')) {
    function ia_e($valor): string {
        return htmlspecialchars(strval($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('ia_is_admin')) {
    function ia_is_admin(): bool {
        if (function_exists('ehSuperAdmin') && ehSuperAdmin()) {
            return true;
        }
        if (intval($_SESSION['user_nb_superadmin'] ?? 0) === 1) {
            return true;
        }
        $nivel = trim(strval($_SESSION['user_tx_nivel'] ?? ''));
        return (bool)preg_match('/administrador|super\s+admin|adminsitrador/i', $nivel);
    }
}

if (!function_exists('ia_user_id')) {
    function ia_user_id(): int {
        return (int)($_SESSION['user_nb_id'] ?? 0);
    }
}

if (!function_exists('ia_tenant')) {
    function ia_tenant(): string {
        return trim(strval($_ENV['CONTEX_PATH'] ?? 'armazem_paraiba'), '/');
    }
}

if (!function_exists('ia_csrf_token')) {
    function ia_csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['ia_csrf_token']) || !is_string($_SESSION['ia_csrf_token'])) {
            $_SESSION['ia_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['ia_csrf_token'];
    }
}

if (!function_exists('ia_csrf_validar')) {
    function ia_csrf_validar(?string $token): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $sessao = $_SESSION['ia_csrf_token'] ?? '';
        return is_string($sessao) && $sessao !== '' && is_string($token) && hash_equals($sessao, $token);
    }
}

if (!function_exists('ia_json_out')) {
    function ia_json_out(array $dados, int $status = 200): void {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, private');
        }
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('ia_pode_usar')) {
    /**
     * Acesso ao assistente controlado pelo perfil de acesso.
     * Em beta, não há liberação automática por nível operacional: o perfil
     * precisa ter o item "Assistente Virtual" marcado em Cadastro de Perfil
     * de Acesso. Administradores e Super Administradores têm acesso direto.
     */
    function ia_pode_usar(): bool {
        if (ia_is_admin()) {
            return true;
        }
        return function_exists('usuarioPodeAcessar')
            && usuarioPodeAcessar('/assistente_virtual/index.php');
    }
}

if (!function_exists('ia_path_normalizado')) {
    function ia_path_normalizado(string $path): string {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        $path = preg_replace('/[?#].*$/', '', $path);
        if (strpos($path, '/assistente_virtual/') !== false) {
            return '';
        }
        return $path;
    }
}

if (!function_exists('ia_telas_permitidas')) {
    /**
     * Caminhos de tela que o usuário logado pode acessar. Para administradores,
     * devolve ['*'] (todas as telas). Para os demais, monta a partir do menu.
     */
    function ia_telas_permitidas(): array {
        if (ia_is_admin()) {
            return ['*'];
        }
        $paths = ['/index.php', '/bem_vindo.php', '/logout.php', '/recupera_senha.php', '/login.php', '/login_facial.php'];
        if (!function_exists('menu_estrutura_do_nivel')) {
            return $paths;
        }
        $nivel = strval($_SESSION['user_tx_nivel'] ?? '');
        foreach (menu_estrutura_do_nivel($nivel) as $no) {
            if (($no['tipo'] ?? '') === 'link') {
                $p = ia_path_normalizado(strval($no['path'] ?? ''));
                if ($p !== '') { $paths[] = $p; }
                continue;
            }
            foreach (($no['itens'] ?? []) as $item) {
                $p = ia_path_normalizado(strval($item['path'] ?? ''));
                if ($p !== '') { $paths[] = $p; }
            }
        }
        return array_values(array_unique($paths));
    }
}

if (!function_exists('ia_perfil_autorizado')) {
    function ia_perfil_autorizado(string $perfis, string $nivel): bool {
        $perfis = strtolower(trim($perfis));
        if ($perfis === '' || $perfis === 'todos') {
            return true;
        }
        $nivel = strtolower(trim($nivel));
        $mapa = [
            'admin' => ['admin', 'administrador', 'adm'],
            'rh' => ['rh', 'recursos humanos', 'gestor', 'gestão', 'gestao'],
            'gestor' => ['gestor', 'gestão', 'gestao', 'supervis', 'adm'],
            'supervisao' => ['supervis', 'gestor'],
            'funcionario' => ['funcionário', 'funcionario', 'motorista', 'ajudante', 'terceirizado'],
        ];
        foreach (explode(',', $perfis) as $perfil) {
            $perfil = trim($perfil);
            if ($perfil === '') { continue; }
            foreach (($mapa[$perfil] ?? [$perfil]) as $needle) {
                if ($needle !== '' && strpos($nivel, $needle) !== false) {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('ia_pode_ver_chunk')) {
    function ia_pode_ver_chunk(array $chunk, array $telasPermitidas): bool {
        if (in_array('*', $telasPermitidas, true)) {
            return true;
        }
        $path = ia_path_normalizado(strval($chunk['chk_tx_menu_path'] ?? ''));
        if ($path !== '' && !in_array($path, $telasPermitidas, true)) {
            return false;
        }
        $perfis = strval($chunk['chk_tx_perfis'] ?? 'todos');
        if (!ia_perfil_autorizado($perfis, strval($_SESSION['user_tx_nivel'] ?? ''))) {
            return false;
        }
        return true;
    }
}

if (!function_exists('ia_sugestoes_por_tela')) {
    function ia_sugestoes_por_tela(string $tela = ''): array {
        $tela = strtolower($tela);
        $mapa = [
            'batida_ponto' => [
                'Como registrar o ponto?',
                'Esqueci de bater o ponto, e agora?',
                'Por que minha batida não aparece?',
            ],
            'espelho_ponto' => [
                'Como ler o espelho de ponto?',
                'O que significa adicional noturno?',
                'Como corrigir um ponto no espelho?',
            ],
            'ajuste' => [
                'Como solicitar um ajuste de ponto?',
                'Quem aprova o ajuste?',
                'Quanto tempo leva para o ajuste ser aprovado?',
            ],
            'cadastro_funcionario' => [
                'Como cadastrar um funcionário?',
                'O que é entidade no cadastro?',
                'Como vincular um perfil de acesso?',
            ],
            'cadastro_empresa' => [
                'Como cadastrar uma empresa ou filial?',
                'O que é matriz e filial?',
                'Como anexar documentos da empresa?',
            ],
            'cadastro_feriado' => [
                'Como cadastrar um feriado?',
                'O feriado vale para quais funcionários?',
            ],
            'cadastro_ferias' => [
                'Como cadastrar férias?',
                'Como consultar o período de férias?',
            ],
            'endosso' => [
                'O que é endosso?',
                'Como fechar o espelho de ponto?',
                'O endosso pode ser parcial?',
            ],
            'nao_conformidade' => [
                'O que é uma não conformidade?',
                'Como tratar uma não conformidade?',
            ],
            'carregar_ponto' => [
                'Como importar os pontos?',
                'Quais integrações de ponto existem?',
            ],
            'assinatura' => [
                'Como assinar um documento?',
                'O que é assinatura com governança?',
                'Como validar uma assinatura no ITI?',
            ],
            'suporte' => [
                'Como abrir um chamado de suporte?',
                'Como acompanhar meus chamados?',
            ],
        ];
        foreach ($mapa as $chave => $sugestoes) {
            if ($tela !== '' && strpos($tela, $chave) !== false) {
                return $sugestoes;
            }
        }
        return [
            'Como acessar o sistema?',
            'Onde cadastro um funcionário?',
            'Como consultar o espelho de ponto?',
            'O que é endosso?',
        ];
    }
}

if (!function_exists('ia_settings')) {
    function ia_settings(): array {
        $isSuper = (function_exists('ehSuperAdmin') && ehSuperAdmin()) || ia_is_admin();
        return [
            'enabled' => $isSuper || ia_env('ASSISTENTE_ENABLED', '0') === '1',
            'provider' => ia_env('ASSISTENTE_PROVIDER', 'ollama'),
            'model' => ia_env('ASSISTENTE_MODEL', 'qwen2.5:1.5b'),
            'model_complex' => ia_env('ASSISTENTE_MODEL_COMPLEX', 'qwen2.5:3b'),
            'router' => ia_env('ASSISTENTE_ROUTER', '1') === '1',
            'embed_model' => ia_env('ASSISTENTE_EMBED_MODEL', 'nomic-embed-text'),
            'ollama_url' => rtrim(ia_env('ASSISTENTE_OLLAMA_URL', 'http://127.0.0.1:11434'), '/'),
            'max_tokens' => max(100, (int)ia_env('ASSISTENTE_MAX_TOKENS', '350')),
            'temperature' => (float)ia_env('ASSISTENTE_TEMPERATURE', '0.1'),
            'daily_quota' => max(1, (int)ia_env('ASSISTENTE_DAILY_QUOTA', '300')),
            'rate_limit' => max(1, (int)ia_env('ASSISTENTE_RATE_LIMIT', '20')),
            'timeout' => max(10, (int)ia_env('ASSISTENTE_TIMEOUT', '180')),
            'retention_days' => max(0, (int)ia_env('ASSISTENTE_RETENTION_DAYS', '90')),
            'top_chunks' => max(3, min(10, (int)ia_env('ASSISTENTE_TOP_CHUNKS', '5'))),
            'min_score' => max(0.0, (float)ia_env('ASSISTENTE_MIN_SCORE', '0.9')),
            'num_ctx' => max(2048, min(16384, (int)ia_env('ASSISTENTE_NUM_CTX', '6144'))),
            'num_thread' => max(0, (int)ia_env('ASSISTENTE_NUM_THREAD', '0')),
            'keep_alive' => ia_env('ASSISTENTE_KEEP_ALIVE', '30m'),
        ];
    }
}
