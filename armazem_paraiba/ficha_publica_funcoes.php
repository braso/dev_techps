<?php
/*
 * Ficha Funcional Pública — funções compartilhadas.
 *
 * Este arquivo é usado por dois contextos:
 *  - ficha_publica.php (página pública, sem login e sem sessão);
 *  - ficha_qrcode.php (tela interna autenticada, que já inclui conecta.php).
 *
 * Por isso o bootstrap só abre conexão quando ainda não existe uma conexão
 * ativa. Nenhuma função daqui inicia sessão ou exige login.
 */

if (!function_exists('ficha_publica_bootstrap')) {
    /**
     * Garante ambiente, timezone e conexão mysqli para a ficha pública.
     * Quando chamado por uma página que já incluiu conecta.php, reaproveita
     * a conexão global existente.
     */
    function ficha_publica_bootstrap(): void
    {
        global $conn;

        if ($conn instanceof mysqli) {
            return;
        }

        if (!isset($_ENV['DB_HOST'])) {
            require_once __DIR__ . '/load_env.php';
        }

        date_default_timezone_set('America/Fortaleza');
        mysqli_report(MYSQLI_REPORT_OFF);

        $host = strval($_ENV['DB_HOST'] ?? '');
        $user = strval($_ENV['DB_USER'] ?? '');
        $password = strval($_ENV['DB_PASSWORD'] ?? '');
        $database = strval($_ENV['DB_NAME'] ?? '');

        if ($host === '' || $database === '') {
            $conn = null;
            return;
        }

        $conexao = @mysqli_connect($host, $user, $password, $database);
        if ($conexao instanceof mysqli) {
            @$conexao->set_charset('utf8mb4');
            $conn = $conexao;
        } else {
            $conn = null;
        }
    }
}

if (!function_exists('ficha_publica_db')) {
    function ficha_publica_db(): ?mysqli
    {
        global $conn;
        return ($conn instanceof mysqli) ? $conn : null;
    }
}

if (!function_exists('ficha_publica_ensure_table')) {
    /**
     * Cria a tabela de códigos caso o banco ainda não tenha passado pela
     * migração do conecta.php. É idempotente e silenciosa.
     */
    function ficha_publica_ensure_table(mysqli $conn): bool
    {
        try {
            $existe = @mysqli_query($conn, "SELECT 1 FROM funcionario_ficha_publica LIMIT 1");
            if ($existe instanceof mysqli_result) {
                mysqli_free_result($existe);
                return true;
            }
        } catch (Throwable $e) {
            // Tabela ausente: cria abaixo.
        }

        $sql = "CREATE TABLE IF NOT EXISTS funcionario_ficha_publica (
            ffp_nb_id INT AUTO_INCREMENT PRIMARY KEY,
            ffp_nb_entidade INT NOT NULL,
            ffp_tx_codigo CHAR(10) NOT NULL,
            ffp_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ffp_dt_dataCadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ffp_nb_userCadastro INT NULL,
            ffp_dt_dataAtualiza DATETIME NULL,
            ffp_nb_userAtualiza INT NULL,
            UNIQUE KEY uk_codigo (ffp_tx_codigo),
            KEY idx_entidade_status (ffp_nb_entidade, ffp_tx_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            return (bool) @mysqli_query($conn, $sql);
        } catch (Throwable $e) {
            error_log('[ficha_publica] Falha ao garantir tabela: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('ficha_publica_stmt_all')) {
    /**
     * Executa um prepared statement e devolve todas as linhas.
     * Em caso de erro devolve [] e registra no log.
     */
    function ficha_publica_stmt_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
    {
        $stmt = null;
        try {
            $stmt = @mysqli_prepare($conn, $sql);
            if (!$stmt) {
                return [];
            }
            if ($types !== '' && count($params) === strlen($types)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            if (!mysqli_stmt_execute($stmt)) {
                return [];
            }
            $result = mysqli_stmt_get_result($stmt);
            $rows = [];
            if ($result instanceof mysqli_result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $rows[] = $row;
                }
            }
            return $rows;
        } catch (Throwable $e) {
            error_log('[ficha_publica] Erro de consulta: ' . $e->getMessage());
            return [];
        } finally {
            if ($stmt instanceof mysqli_stmt) {
                @mysqli_stmt_close($stmt);
            }
        }
    }
}

if (!function_exists('ficha_publica_stmt_one')) {
    function ficha_publica_stmt_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
    {
        $rows = ficha_publica_stmt_all($conn, $sql, $types, $params);
        return $rows[0] ?? null;
    }
}

if (!function_exists('ficha_publica_exec')) {
    /**
     * Executa INSERT/UPDATE/DELETE com prepared statement.
     */
    function ficha_publica_exec(mysqli $conn, string $sql, string $types = '', array $params = []): bool
    {
        $stmt = null;
        try {
            $stmt = @mysqli_prepare($conn, $sql);
            if (!$stmt) {
                return false;
            }
            if ($types !== '' && count($params) === strlen($types)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            return (bool) mysqli_stmt_execute($stmt);
        } catch (Throwable $e) {
            error_log('[ficha_publica] Erro de gravação: ' . $e->getMessage());
            return false;
        } finally {
            if ($stmt instanceof mysqli_stmt) {
                @mysqli_stmt_close($stmt);
            }
        }
    }
}

if (!function_exists('ficha_publica_alphabet')) {
    function ficha_publica_alphabet(): string
    {
        // Crockford Base32 sem I, L, O e U (evita confusão na digitação).
        return '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    }
}

if (!function_exists('ficha_publica_normalizar_codigo')) {
    function ficha_publica_normalizar_codigo($codigo): string
    {
        $codigo = strtoupper(trim(strval($codigo)));
        $codigo = preg_replace('/[^A-Z0-9]/', '', $codigo);
        $codigo = strtr($codigo, ['O' => '0', 'I' => '1', 'L' => '1']);
        return strval($codigo);
    }
}

if (!function_exists('ficha_publica_formatar_codigo')) {
    function ficha_publica_formatar_codigo($codigo): string
    {
        $codigo = ficha_publica_normalizar_codigo($codigo);
        if (strlen($codigo) === 10) {
            return substr($codigo, 0, 5) . '-' . substr($codigo, 5);
        }
        return $codigo;
    }
}

if (!function_exists('ficha_publica_gerar_codigo')) {
    /**
     * Gera um código aleatório único de 10 caracteres.
     */
    function ficha_publica_gerar_codigo(mysqli $conn): ?string
    {
        $alphabet = ficha_publica_alphabet();
        $max = strlen($alphabet) - 1;

        for ($tentativa = 0; $tentativa < 30; $tentativa++) {
            $codigo = '';
            try {
                for ($i = 0; $i < 10; $i++) {
                    $codigo .= $alphabet[random_int(0, $max)];
                }
            } catch (Throwable $e) {
                error_log('[ficha_publica] Falha ao gerar código aleatório: ' . $e->getMessage());
                return null;
            }

            $existe = ficha_publica_stmt_one(
                $conn,
                "SELECT 1 AS existe FROM funcionario_ficha_publica WHERE ffp_tx_codigo = ? LIMIT 1",
                's',
                [$codigo]
            );

            if (empty($existe)) {
                return $codigo;
            }
        }

        error_log('[ficha_publica] Não foi possível gerar um código único após 30 tentativas.');
        return null;
    }
}

if (!function_exists('ficha_publica_obter_codigo_ativo')) {
    function ficha_publica_obter_codigo_ativo(mysqli $conn, int $entidadeId): ?string
    {
        if ($entidadeId <= 0) {
            return null;
        }

        $row = ficha_publica_stmt_one(
            $conn,
            "SELECT ffp_tx_codigo
             FROM funcionario_ficha_publica
             WHERE ffp_nb_entidade = ? AND ffp_tx_status = 'ativo'
             ORDER BY ffp_nb_id DESC
             LIMIT 1",
            'i',
            [$entidadeId]
        );

        return !empty($row['ffp_tx_codigo']) ? strval($row['ffp_tx_codigo']) : null;
    }
}

if (!function_exists('ficha_publica_obter_ou_criar_codigo')) {
    /**
     * Retorna o código ativo do funcionário. Se não existir, cria um novo.
     */
    function ficha_publica_obter_ou_criar_codigo(mysqli $conn, int $entidadeId, int $userId = 0): ?string
    {
        if ($entidadeId <= 0) {
            return null;
        }

        $existente = ficha_publica_obter_codigo_ativo($conn, $entidadeId);
        if ($existente !== null) {
            return $existente;
        }

        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            $codigo = ficha_publica_gerar_codigo($conn);
            if ($codigo === null) {
                return null;
            }

            $ok = ficha_publica_exec(
                $conn,
                "INSERT INTO funcionario_ficha_publica
                    (ffp_nb_entidade, ffp_tx_codigo, ffp_tx_status, ffp_dt_dataCadastro, ffp_nb_userCadastro)
                 VALUES (?, ?, 'ativo', NOW(), NULLIF(?, 0))",
                'isi',
                [$entidadeId, $codigo, $userId]
            );

            if ($ok) {
                // Garante que só o código recém-criado permaneça ativo para este
                // funcionário, mesmo em caso de requisições concorrentes.
                ficha_publica_exec(
                    $conn,
                    "UPDATE funcionario_ficha_publica
                     SET ffp_tx_status = 'inativo',
                         ffp_dt_dataAtualiza = NOW(),
                         ffp_nb_userAtualiza = NULLIF(?, 0)
                     WHERE ffp_nb_entidade = ?
                       AND ffp_tx_status = 'ativo'
                       AND ffp_tx_codigo <> ?",
                    'iis',
                    [$userId, $entidadeId, $codigo]
                );

                return $codigo;
            }

            // Pode ter havido concorrência: se outro request criou o código, usa o dele.
            $existente = ficha_publica_obter_codigo_ativo($conn, $entidadeId);
            if ($existente !== null) {
                return $existente;
            }
        }

        return null;
    }
}

if (!function_exists('ficha_publica_regenerar_codigo')) {
    /**
     * Inativa o código atual e cria um novo. Usa transação para não deixar o
     * funcionário sem código caso a geração falhe.
     */
    function ficha_publica_regenerar_codigo(mysqli $conn, int $entidadeId, int $userId = 0): ?string
    {
        if ($entidadeId <= 0) {
            return null;
        }

        ficha_publica_ensure_table($conn);

        try {
            @mysqli_begin_transaction($conn);
        } catch (Throwable $e) {
            error_log('[ficha_publica] Falha ao iniciar transação: ' . $e->getMessage());
            return null;
        }

        $ok = ficha_publica_exec(
            $conn,
            "UPDATE funcionario_ficha_publica
             SET ffp_tx_status = 'inativo',
                 ffp_dt_dataAtualiza = NOW(),
                 ffp_nb_userAtualiza = NULLIF(?, 0)
             WHERE ffp_nb_entidade = ? AND ffp_tx_status = 'ativo'",
            'ii',
            [$userId, $entidadeId]
        );

        if (!$ok) {
            try { @mysqli_rollback($conn); } catch (Throwable $e) {}
            return null;
        }

        $novo = ficha_publica_obter_ou_criar_codigo($conn, $entidadeId, $userId);
        if ($novo === null) {
            try { @mysqli_rollback($conn); } catch (Throwable $e) {}
            return null;
        }

        try {
            @mysqli_commit($conn);
        } catch (Throwable $e) {
            error_log('[ficha_publica] Falha ao confirmar transação: ' . $e->getMessage());
            return null;
        }

        return $novo;
    }
}

if (!function_exists('ficha_publica_sql_funcionario')) {
    /**
     * SELECT padrão dos dados do funcionário + empresa/setor/cargo.
     * Não inclui o código; cada consulta adiciona o que precisa.
     */
    function ficha_publica_sql_funcionario(): string
    {
        return "SELECT e.enti_nb_id,
                       e.enti_tx_nome,
                       e.enti_tx_cpf,
                       e.enti_tx_matricula,
                       e.enti_tx_foto,
                       e.enti_tx_ocupacao,
                       e.enti_tx_admissao,
                       e.enti_tx_status,
                       e.enti_nb_empresa,
                       e.enti_setor_id,
                       e.enti_tx_tipoOperacao,
                       emp.empr_tx_nome,
                       emp.empr_tx_logo,
                       g.grup_tx_nome AS setor_nome,
                       o.oper_tx_nome AS cargo_nome
                FROM entidade e
                LEFT JOIN empresa emp ON emp.empr_nb_id = e.enti_nb_empresa
                LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
                LEFT JOIN operacao o ON o.oper_nb_id = e.enti_tx_tipoOperacao";
    }
}

if (!function_exists('ficha_publica_obter_funcionario')) {
    function ficha_publica_obter_funcionario(mysqli $conn, int $entidadeId): ?array
    {
        if ($entidadeId <= 0) {
            return null;
        }

        return ficha_publica_stmt_one(
            $conn,
            ficha_publica_sql_funcionario() . " WHERE e.enti_nb_id = ? LIMIT 1",
            'i',
            [$entidadeId]
        );
    }
}

if (!function_exists('ficha_publica_buscar_por_codigo')) {
    /**
     * Busca a ficha somente por código ativo e funcionário ativo.
     */
    function ficha_publica_buscar_por_codigo(mysqli $conn, string $codigo): ?array
    {
        $codigo = ficha_publica_normalizar_codigo($codigo);
        if (strlen($codigo) !== 10) {
            return null;
        }

        $sql = "SELECT f.ffp_tx_codigo,
                       e.enti_nb_id,
                       e.enti_tx_nome,
                       e.enti_tx_cpf,
                       e.enti_tx_matricula,
                       e.enti_tx_foto,
                       e.enti_tx_ocupacao,
                       e.enti_tx_admissao,
                       e.enti_tx_status,
                       e.enti_nb_empresa,
                       e.enti_setor_id,
                       e.enti_tx_tipoOperacao,
                       emp.empr_tx_nome,
                       emp.empr_tx_logo,
                       g.grup_tx_nome AS setor_nome,
                       o.oper_tx_nome AS cargo_nome
                FROM funcionario_ficha_publica f
                JOIN entidade e ON e.enti_nb_id = f.ffp_nb_entidade
                LEFT JOIN empresa emp ON emp.empr_nb_id = e.enti_nb_empresa
                LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
                LEFT JOIN operacao o ON o.oper_nb_id = e.enti_tx_tipoOperacao
                WHERE f.ffp_tx_codigo = ?
                  AND f.ffp_tx_status = 'ativo'
                  AND e.enti_tx_status = 'ativo'
                ORDER BY f.ffp_nb_id DESC
                LIMIT 1";

        return ficha_publica_stmt_one($conn, $sql, 's', [$codigo]);
    }
}

if (!function_exists('ficha_publica_listar_epis')) {
    /**
     * Lista os EPIs atualmente em posse do funcionário.
     *
     * Regra: considera o registro mais recente de cada combinação
     * funcionário + EPI + variação, ignorando registros excluídos/inativados
     * e registros de não-entrega. Só aparece se esse registro mais recente
     * estiver com status "ativo".
     */
    function ficha_publica_listar_epis(mysqli $conn, int $entidadeId): array
    {
        if ($entidadeId <= 0) {
            return [];
        }

        $sql = "SELECT ent.ss_e_nb_id,
                       ent.ss_e_nb_epi_id,
                       ent.ss_e_tx_data_entrega,
                       ent.ss_e_tx_vencimento,
                       ent.ss_e_tx_variacao,
                       ent.ss_e_nb_quantidade,
                       epi.ss_e_tx_grupo,
                       epi.ss_e_tx_subgrupo,
                       epi.ss_e_tx_item,
                       epi.ss_e_tx_ca,
                       epi.ss_e_nb_vida_util
                FROM ss_epi_entrega ent
                JOIN ss_epi epi ON epi.ss_e_nb_id = ent.ss_e_nb_epi_id
                WHERE ent.ss_e_nb_colaborador_id = ?
                  AND ent.ss_e_tx_status = 'ativo'
                  AND NOT EXISTS (
                      SELECT 1
                      FROM ss_epi_entrega ent2
                      WHERE ent2.ss_e_nb_colaborador_id = ent.ss_e_nb_colaborador_id
                        AND ent2.ss_e_nb_epi_id = ent.ss_e_nb_epi_id
                        AND IFNULL(ent2.ss_e_tx_variacao, '') = IFNULL(ent.ss_e_tx_variacao, '')
                        AND ent2.ss_e_tx_status NOT IN ('inativo', 'nao_entregue')
                        AND (
                            ent2.ss_e_tx_data_entrega > ent.ss_e_tx_data_entrega
                            OR (
                                ent2.ss_e_tx_data_entrega = ent.ss_e_tx_data_entrega
                                AND ent2.ss_e_nb_id > ent.ss_e_nb_id
                            )
                        )
                  )
                ORDER BY epi.ss_e_tx_grupo ASC,
                         epi.ss_e_tx_subgrupo ASC,
                         epi.ss_e_tx_item ASC,
                         ent.ss_e_tx_variacao ASC";

        return ficha_publica_stmt_all($conn, $sql, 'i', [$entidadeId]);
    }
}

if (!function_exists('ficha_publica_listar_treinamentos')) {
    /**
     * Lista os treinamentos concluídos do funcionário.
     * A ligação é feita por user.user_nb_entidade, pois o progresso do
     * treinamento é registrado por usuário.
     */
    function ficha_publica_listar_treinamentos(mysqli $conn, int $entidadeId): array
    {
        if ($entidadeId <= 0) {
            return [];
        }

        $sql = "SELECT t.trei_nb_id,
                       t.trei_tx_titulo,
                       t.trei_tx_tipo,
                       t.trei_tx_tipo_treinamento,
                       t.trei_nb_carga_horaria,
                       t.trei_nb_dias_validade,
                       p.data_conclusao,
                       p.nota
                FROM treinamento t
                JOIN (
                    SELECT p.trepr_nb_treinamento_id,
                           MAX(p.trepr_dt_data_conclusao) AS data_conclusao,
                           MAX(p.trepr_nb_avaliacao_nota) AS nota
                    FROM treinamento_progresso p
                    JOIN user u ON u.user_nb_id = p.trepr_nb_usuario_id
                    WHERE u.user_nb_entidade = ?
                      AND p.trepr_nb_episodio_id IS NULL
                      AND p.trepr_nb_concluido = 1
                    GROUP BY p.trepr_nb_treinamento_id
                ) p ON p.trepr_nb_treinamento_id = t.trei_nb_id
                ORDER BY p.data_conclusao DESC, t.trei_tx_titulo ASC";

        return ficha_publica_stmt_all($conn, $sql, 'i', [$entidadeId]);
    }
}

if (!function_exists('ficha_publica_data')) {
    function ficha_publica_data($data): string
    {
        $data = trim(strval($data));
        if ($data === '' || $data === '0000-00-00' || strpos($data, '0000-00-00') === 0) {
            return '';
        }
        $ts = strtotime($data);
        return $ts ? date('d/m/Y', $ts) : '';
    }
}

if (!function_exists('ficha_publica_data_hora')) {
    function ficha_publica_data_hora($data): string
    {
        $data = trim(strval($data));
        if ($data === '' || strpos($data, '0000-00-00') === 0) {
            return '';
        }
        $ts = strtotime($data);
        return $ts ? date('d/m/Y H:i', $ts) : '';
    }
}

if (!function_exists('ficha_publica_carga_horaria')) {
    function ficha_publica_carga_horaria($segundos): string
    {
        $segundos = (int) $segundos;
        if ($segundos <= 0) {
            return '';
        }

        $horas = intdiv($segundos, 3600);
        $minutos = intdiv($segundos % 3600, 60);

        if ($horas > 0) {
            return $horas . 'h' . ($minutos > 0 ? ' ' . $minutos . 'min' : '');
        }

        return $minutos . 'min';
    }
}

if (!function_exists('ficha_publica_mascarar_cpf')) {
    function ficha_publica_mascarar_cpf($cpf): string
    {
        $digitos = preg_replace('/\D/', '', strval($cpf));
        if (strlen($digitos) !== 11) {
            return '';
        }
        return '***.***.***-' . substr($digitos, -2);
    }
}

if (!function_exists('ficha_publica_iniciais')) {
    function ficha_publica_iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        $partes = array_values(array_filter($partes, function ($p) {
            return trim($p) !== '';
        }));

        if (empty($partes)) {
            return '?';
        }

        $primeiro = $partes[0];
        $ultimo = count($partes) > 1 ? $partes[count($partes) - 1] : '';

        $inicial1 = function_exists('mb_substr') ? mb_substr($primeiro, 0, 1, 'UTF-8') : substr($primeiro, 0, 1);
        $inicial2 = function_exists('mb_substr') ? mb_substr($ultimo, 0, 1, 'UTF-8') : substr($ultimo, 0, 1);

        $iniciais = $inicial1 . $inicial2;
        return function_exists('mb_strtoupper') ? mb_strtoupper($iniciais, 'UTF-8') : strtoupper($iniciais);
    }
}

if (!function_exists('ficha_publica_resolver_arquivo')) {
    /**
     * Resolve caminhos de foto/logo cadastrados no banco.
     * Devolve ['url' => ..., 'abs' => ...] ou null.
     */
    function ficha_publica_resolver_arquivo($caminho): ?array
    {
        $caminho = trim(strval($caminho));
        if ($caminho === '') {
            return null;
        }

        $caminho = str_replace('\\', '/', $caminho);

        if (preg_match('#^(https?:)?//#i', $caminho) || stripos($caminho, 'data:image/') === 0) {
            return ['url' => $caminho, 'abs' => null];
        }

        $caminho = preg_replace('#/+#', '/', $caminho);
        $caminho = ltrim(strval($caminho), '/');

        $appPath = rtrim(strval($_ENV['APP_PATH'] ?? ''), '/');
        $contextPath = rtrim(strval($_ENV['CONTEX_PATH'] ?? ''), '/');
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $caminho)));

        $candidatos = [
            [
                'abs' => __DIR__ . '/' . $caminho,
                'url' => $appPath . $contextPath . '/' . $encodedPath,
            ],
            [
                'abs' => dirname(__DIR__) . '/' . $caminho,
                'url' => $appPath . '/' . $encodedPath,
            ],
        ];

        $raizes = array_values(array_filter([
            realpath(__DIR__),
            realpath(dirname(__DIR__)),
        ]));

        foreach ($candidatos as $candidato) {
            $abs = realpath($candidato['abs']);
            if ($abs === false || !is_file($abs)) {
                continue;
            }

            $absNormalizado = str_replace('\\', '/', $abs);
            foreach ($raizes as $raiz) {
                $raizNormalizada = rtrim(str_replace('\\', '/', $raiz), '/');
                if ($raizNormalizada === '') {
                    continue;
                }
                if ($absNormalizado === $raizNormalizada || strpos($absNormalizado, $raizNormalizada . '/') === 0) {
                    return ['url' => $candidato['url'], 'abs' => $abs];
                }
            }
        }

        return null;
    }
}

if (!function_exists('ficha_publica_base_url')) {
    function ficha_publica_base_url(): string
    {
        $override = trim(strval($_ENV['FICHA_PUBLICA_URL_BASE'] ?? ''));
        if ($override !== '') {
            return rtrim($override, '/');
        }

        $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($_SERVER['REQUEST_SCHEME'] ?? 'http');
        $scheme = strtolower(trim(explode(',', strval($scheme))[0]));
        if (!in_array($scheme, ['http', 'https'], true)) {
            $scheme = 'http';
        }

        $host = trim(strval($_SERVER['HTTP_HOST'] ?? 'localhost'));
        if ($host === '') {
            $host = 'localhost';
        }

        return $scheme . '://' . $host;
    }
}

if (!function_exists('ficha_publica_pagina_url')) {
    function ficha_publica_pagina_url(): string
    {
        $appPath = rtrim(strval($_ENV['APP_PATH'] ?? ''), '/');
        $contextPath = rtrim(strval($_ENV['CONTEX_PATH'] ?? ''), '/');
        return ficha_publica_base_url() . $appPath . $contextPath . '/ficha_publica.php';
    }
}

if (!function_exists('ficha_publica_url')) {
    function ficha_publica_url($codigo): string
    {
        return ficha_publica_pagina_url() . '?c=' . rawurlencode(ficha_publica_formatar_codigo($codigo));
    }
}

if (!function_exists('ficha_publica_somar_dias')) {
    function ficha_publica_somar_dias($data, int $dias): ?string
    {
        $data = trim(strval($data));
        if ($data === '' || strpos($data, '0000-00-00') === 0) {
            return null;
        }

        try {
            $dt = new DateTimeImmutable($data);
        } catch (Throwable $e) {
            return null;
        }

        if ($dias <= 0) {
            return null;
        }

        return $dt->modify('+' . $dias . ' days')->format('Y-m-d');
    }
}

if (!function_exists('ficha_publica_status_validade')) {
    /**
     * Calcula o status de validade de uma data.
     *
     * @param string|null $vencimento Data no formato Y-m-d.
     * @param bool $temControle Quando false, retorna "sem validade".
     * @return array{codigo:string,label:string,dias:?int,data:?string}
     */
    function ficha_publica_status_validade($vencimento, bool $temControle = true): array
    {
        $vencimento = trim(strval($vencimento));

        if (!$temControle || $vencimento === '' || strpos($vencimento, '0000-00-00') === 0) {
            return ['codigo' => 'sem_validade', 'label' => 'Sem validade', 'dias' => null, 'data' => null];
        }

        try {
            $hoje = new DateTimeImmutable('today');
            $data = new DateTimeImmutable($vencimento);
        } catch (Throwable $e) {
            return ['codigo' => 'sem_validade', 'label' => 'Sem validade', 'dias' => null, 'data' => null];
        }

        $dias = (int) $hoje->diff($data)->format('%r%a');

        if ($dias < 0) {
            return ['codigo' => 'vencido', 'label' => 'Vencido', 'dias' => $dias, 'data' => $data->format('Y-m-d')];
        }

        if ($dias === 0) {
            return ['codigo' => 'vence_hoje', 'label' => 'Vence hoje', 'dias' => 0, 'data' => $data->format('Y-m-d')];
        }

        if ($dias <= 30) {
            return ['codigo' => 'a_vencer', 'label' => 'A vencer', 'dias' => $dias, 'data' => $data->format('Y-m-d')];
        }

        return ['codigo' => 'em_dia', 'label' => 'Em dia', 'dias' => $dias, 'data' => $data->format('Y-m-d')];
    }
}

if (!function_exists('ficha_publica_status_detalhe')) {
    function ficha_publica_status_detalhe(array $status): string
    {
        $dias = $status['dias'] ?? null;
        if ($dias === null) {
            return '';
        }

        $codigo = strval($status['codigo'] ?? '');
        if ($codigo === 'a_vencer') {
            return $dias === 1 ? 'em 1 dia' : 'em ' . $dias . ' dias';
        }
        if ($codigo === 'vencido') {
            $abs = abs((int) $dias);
            return 'há ' . $abs . ($abs === 1 ? ' dia' : ' dias');
        }
        if ($codigo === 'vence_hoje') {
            return '';
        }

        return '';
    }
}

if (!function_exists('ficha_publica_qr_svg')) {
    /**
     * Gera o QR Code como SVG inline usando o TCPDF já embarcado.
     */
    function ficha_publica_qr_svg(string $url, int $modulo = 4): string
    {
        $arquivo = __DIR__ . '/tcpdf/tcpdf_barcodes_2d.php';
        if (!is_file($arquivo)) {
            return '';
        }

        if (!class_exists('TCPDF2DBarcode')) {
            require_once $arquivo;
        }

        if (!class_exists('TCPDF2DBarcode')) {
            return '';
        }

        try {
            $barcode = new TCPDF2DBarcode($url, 'QRCODE,M');
            $svg = $barcode->getBarcodeSVGcode($modulo, $modulo, '#111111');
        } catch (Throwable $e) {
            error_log('[ficha_publica] Falha ao gerar QR Code: ' . $e->getMessage());
            return '';
        }

        if (!is_string($svg) || trim($svg) === '') {
            return '';
        }

        $svg = preg_replace('/^\s*<\?xml[^>]*\?>\s*/i', '', $svg);
        $svg = preg_replace('/<!DOCTYPE[^>]*>\s*/i', '', strval($svg));
        $svg = preg_replace_callback(
            '/<svg\s+width="([\d.]+)"\s+height="([\d.]+)"/i',
            function ($m) {
                return '<svg viewBox="0 0 ' . $m[1] . ' ' . $m[2] . '" width="100%" height="100%"';
            },
            strval($svg),
            1
        );

        return strval($svg);
    }
}

if (!function_exists('ficha_publica_h')) {
    function ficha_publica_h($valor): string
    {
        return htmlspecialchars(strval($valor), ENT_QUOTES, 'UTF-8');
    }
}
