<?php
/**
 * Contatos do WhatsApp: sincronização com entidade/user, resolução de
 * atalhos e consultas usadas pelo chat e pelo envio em lote.
 */

if (!function_exists('wa_contato_por_id')) {
    function wa_contato_por_id(int $id): ?array {
        if ($id <= 0) {
            return null;
        }
        $rs = query("SELECT * FROM wa_contato WHERE cont_nb_id = ? LIMIT 1", "i", [$id]);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        return $row ?: null;
    }
}

if (!function_exists('wa_contato_upsert')) {
    /**
     * Cria ou atualiza um contato. Preserva opt-in e contatos manuais.
     * $dados: cont_nb_entidade, cont_nb_user, cont_nb_empresa, cont_tx_nome,
     *         cont_tx_telefone, cont_tx_setor, cont_tx_cargo, cont_tx_origem
     */
    function wa_contato_upsert(array $dados): array {
        $entidadeId = (int)($dados['cont_nb_entidade'] ?? 0);
        $userId = (int)($dados['cont_nb_user'] ?? 0);
        $empresaId = (int)($dados['cont_nb_empresa'] ?? 0);
        $nome = trim(strval($dados['cont_tx_nome'] ?? ''));
        $telefone = trim(strval($dados['cont_tx_telefone'] ?? ''));
        $setor = trim(strval($dados['cont_tx_setor'] ?? ''));
        $cargo = trim(strval($dados['cont_tx_cargo'] ?? ''));
        $origem = strval($dados['cont_tx_origem'] ?? 'entidade');
        if (!in_array($origem, ['entidade', 'user', 'manual'], true)) {
            $origem = 'entidade';
        }
        if ($nome === '') {
            $nome = $telefone !== '' ? $telefone : 'Contato sem nome';
        }

        $e164 = wa_normalizar_telefone($telefone);
        $existente = null;
        $porE164 = false;

        if ($e164 !== null) {
            $rs = query("SELECT * FROM wa_contato WHERE cont_tx_e164 = ? LIMIT 1", "s", [$e164]);
            $existente = $rs ? mysqli_fetch_assoc($rs) : null;
            $porE164 = !empty($existente);
        }

        if (!$existente && $entidadeId > 0) {
            $rs = query("SELECT * FROM wa_contato WHERE cont_nb_entidade = ? ORDER BY cont_nb_id ASC LIMIT 1", "i", [$entidadeId]);
            $existente = $rs ? mysqli_fetch_assoc($rs) : null;
        }
        if (!$existente && $userId > 0) {
            $rs = query("SELECT * FROM wa_contato WHERE cont_nb_user = ? ORDER BY cont_nb_id ASC LIMIT 1", "i", [$userId]);
            $existente = $rs ? mysqli_fetch_assoc($rs) : null;
        }

        // Contato manual com o mesmo telefone: não sobrescreve.
        if ($existente && strval($existente['cont_tx_origem'] ?? '') === 'manual' && $origem !== 'manual') {
            return ['id' => (int)$existente['cont_nb_id'], 'acao' => 'ignorado', 'motivo' => 'manual'];
        }

        if ($existente) {
            $id = (int)$existente['cont_nb_id'];
            $sets = [
                'cont_nb_entidade = ?',
                'cont_nb_user = ?',
                'cont_nb_empresa = ?',
                'cont_tx_nome = ?',
                'cont_tx_telefone = ?',
                'cont_tx_e164 = ?',
                'cont_tx_setor = ?',
                'cont_tx_cargo = ?',
                'cont_tx_origem = ?',
                'cont_dt_ultima_sincronizacao = NOW()',
            ];
            $types = 'iiissssss';
            $vals = [
                $entidadeId > 0 ? $entidadeId : null,
                $userId > 0 ? $userId : null,
                $empresaId > 0 ? $empresaId : null,
                $nome,
                $telefone !== '' ? $telefone : null,
                $e164,
                $setor !== '' ? $setor : null,
                $cargo !== '' ? $cargo : null,
                strval($existente['cont_tx_origem'] ?? $origem),
            ];
            $ok = wa_exec("UPDATE wa_contato SET " . implode(', ', $sets) . " WHERE cont_nb_id = ?", $types . 'i', array_merge($vals, [$id]));
            return ['id' => $id, 'acao' => $ok ? 'atualizado' : 'erro', 'motivo' => $porE164 ? 'e164' : ''];
        }

        $ok = wa_exec(
            "INSERT INTO wa_contato
                (cont_nb_entidade, cont_nb_user, cont_nb_empresa, cont_tx_nome, cont_tx_telefone, cont_tx_e164,
                 cont_tx_setor, cont_tx_cargo, cont_tx_origem, cont_tx_status, cont_tx_optin, cont_dt_ultima_sincronizacao)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo', 'nao', NOW())",
            "iiissssss",
            [
                $entidadeId > 0 ? $entidadeId : null,
                $userId > 0 ? $userId : null,
                $empresaId > 0 ? $empresaId : null,
                $nome,
                $telefone !== '' ? $telefone : null,
                $e164,
                $setor !== '' ? $setor : null,
                $cargo !== '' ? $cargo : null,
                $origem,
            ]
        );
        if (!$ok) {
            return ['id' => 0, 'acao' => 'erro', 'motivo' => 'insert'];
        }
        return ['id' => (int)mysqli_insert_id($GLOBALS['conn']), 'acao' => 'criado', 'motivo' => ''];
    }
}

if (!function_exists('wa_sincronizar_contatos')) {
    function wa_sincronizar_contatos(): array {
        global $conn;
        $resumo = ['criados' => 0, 'atualizados' => 0, 'ignorados' => 0, 'invalidos' => 0, 'usuarios' => 0, 'erros' => 0];

        $sql = "SELECT
                    e.enti_nb_id,
                    e.enti_tx_nome,
                    e.enti_tx_fone1,
                    e.enti_tx_fone2,
                    e.enti_tx_status,
                    e.enti_tx_desligamento,
                    e.enti_nb_empresa,
                    empr.empr_tx_nome AS empresa_nome,
                    g.grup_tx_nome AS setor_nome,
                    o.oper_tx_nome AS cargo_nome,
                    u.user_nb_id,
                    u.user_tx_fone,
                    u.user_tx_nome
                FROM entidade e
                LEFT JOIN empresa empr ON empr.empr_nb_id = e.enti_nb_empresa
                LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
                LEFT JOIN operacao o ON o.oper_nb_id = e.enti_tx_tipoOperacao
                LEFT JOIN user u ON u.user_nb_id = (
                    SELECT MIN(u2.user_nb_id) FROM user u2
                    WHERE u2.user_nb_entidade = e.enti_nb_id AND u2.user_tx_status = 'ativo'
                )
                WHERE e.enti_tx_status = 'ativo'
                  AND (e.enti_tx_desligamento IS NULL OR e.enti_tx_desligamento = '' OR e.enti_tx_desligamento > CURDATE())
                ORDER BY e.enti_tx_nome ASC";

        $rs = query($sql);
        if (!$rs) {
            return $resumo + ['erro' => 'Não foi possível consultar os funcionários.'];
        }

        while ($row = mysqli_fetch_assoc($rs)) {
            $telefone = trim(strval($row['enti_tx_fone1'] ?? ''));
            $origem = 'entidade';
            if ($telefone === '') {
                $telefone = trim(strval($row['enti_tx_fone2'] ?? ''));
            }
            if ($telefone === '' && trim(strval($row['user_tx_fone'] ?? '')) !== '') {
                $telefone = trim(strval($row['user_tx_fone'] ?? ''));
                $origem = 'user';
            }
            if (wa_normalizar_telefone($telefone) === null) {
                $resumo['invalidos']++;
            }
            $r = wa_contato_upsert([
                'cont_nb_entidade' => (int)$row['enti_nb_id'],
                'cont_nb_user' => (int)($row['user_nb_id'] ?? 0),
                'cont_nb_empresa' => (int)($row['enti_nb_empresa'] ?? 0),
                'cont_tx_nome' => strval($row['enti_tx_nome'] ?? $row['user_tx_nome'] ?? ''),
                'cont_tx_telefone' => $telefone,
                'cont_tx_setor' => strval($row['setor_nome'] ?? ''),
                'cont_tx_cargo' => strval($row['cargo_nome'] ?? ''),
                'cont_tx_origem' => $origem,
            ]);
            if ($r['acao'] === 'criado') {
                $resumo['criados']++;
            } elseif ($r['acao'] === 'atualizado') {
                $resumo['atualizados']++;
            } elseif ($r['acao'] === 'ignorado') {
                $resumo['ignorados']++;
            } else {
                $resumo['erros']++;
            }
        }

        // Usuários sem entidade vinculada também viram contatos.
        $rsUsers = query(
            "SELECT u.user_nb_id, u.user_tx_nome, u.user_tx_fone, u.user_nb_empresa
             FROM user u
             WHERE u.user_tx_status = 'ativo'
               AND u.user_tx_fone IS NOT NULL AND u.user_tx_fone <> ''
               AND (u.user_nb_entidade IS NULL OR u.user_nb_entidade = 0)
             ORDER BY u.user_tx_nome ASC"
        );
        while ($rsUsers && ($u = mysqli_fetch_assoc($rsUsers))) {
            $resumo['usuarios']++;
            $r = wa_contato_upsert([
                'cont_nb_user' => (int)$u['user_nb_id'],
                'cont_nb_empresa' => (int)($u['user_nb_empresa'] ?? 0),
                'cont_tx_nome' => strval($u['user_tx_nome'] ?? ''),
                'cont_tx_telefone' => strval($u['user_tx_fone'] ?? ''),
                'cont_tx_origem' => 'user',
            ]);
            if ($r['acao'] === 'criado') {
                $resumo['criados']++;
            } elseif ($r['acao'] === 'atualizado') {
                $resumo['atualizados']++;
            } elseif ($r['acao'] === 'ignorado') {
                $resumo['ignorados']++;
            } else {
                $resumo['erros']++;
            }
        }

        wa_meta_gravar($conn, 'ultima_sincronizacao_contatos', date('Y-m-d H:i:s'));
        wa_log('contatos_sincronizados', 'Sincronização concluída.', $resumo);
        return $resumo;
    }
}

if (!function_exists('wa_contato_resolver_por_entidade')) {
    function wa_contato_resolver_por_entidade(int $entidadeId): ?array {
        if ($entidadeId <= 0) {
            return null;
        }
        $rs = query(
            "SELECT e.enti_nb_id, e.enti_tx_nome, e.enti_tx_fone1, e.enti_tx_fone2, e.enti_nb_empresa,
                    empr.empr_tx_nome AS empresa_nome,
                    g.grup_tx_nome AS setor_nome, o.oper_tx_nome AS cargo_nome,
                    u.user_nb_id, u.user_tx_fone
             FROM entidade e
             LEFT JOIN empresa empr ON empr.empr_nb_id = e.enti_nb_empresa
             LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
             LEFT JOIN operacao o ON o.oper_nb_id = e.enti_tx_tipoOperacao
             LEFT JOIN user u ON u.user_nb_id = (
                SELECT MIN(u2.user_nb_id) FROM user u2
                WHERE u2.user_nb_entidade = e.enti_nb_id AND u2.user_tx_status = 'ativo'
             )
             WHERE e.enti_nb_id = ? LIMIT 1",
            "i",
            [$entidadeId]
        );
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        if (!$row) {
            return null;
        }
        $telefone = trim(strval($row['enti_tx_fone1'] ?? ''));
        $origem = 'entidade';
        if ($telefone === '') {
            $telefone = trim(strval($row['enti_tx_fone2'] ?? ''));
        }
        if ($telefone === '') {
            $telefone = trim(strval($row['user_tx_fone'] ?? ''));
            $origem = 'user';
        }
        $r = wa_contato_upsert([
            'cont_nb_entidade' => $entidadeId,
            'cont_nb_user' => (int)($row['user_nb_id'] ?? 0),
            'cont_nb_empresa' => (int)($row['enti_nb_empresa'] ?? 0),
            'cont_tx_nome' => strval($row['enti_tx_nome'] ?? ''),
            'cont_tx_telefone' => $telefone,
            'cont_tx_setor' => strval($row['setor_nome'] ?? ''),
            'cont_tx_cargo' => strval($row['cargo_nome'] ?? ''),
            'cont_tx_origem' => $origem,
        ]);
        return wa_contato_por_id((int)$r['id']);
    }
}

if (!function_exists('wa_contato_resolver_por_user')) {
    function wa_contato_resolver_por_user(int $userId): ?array {
        if ($userId <= 0) {
            return null;
        }
        $rs = query(
            "SELECT u.user_nb_id, u.user_tx_nome, u.user_tx_fone, u.user_nb_entidade, u.user_nb_empresa,
                    e.enti_tx_nome, e.enti_tx_fone1, e.enti_tx_fone2,
                    empr.empr_tx_nome AS empresa_nome,
                    g.grup_tx_nome AS setor_nome, o.oper_tx_nome AS cargo_nome
             FROM user u
             LEFT JOIN entidade e ON e.enti_nb_id = u.user_nb_entidade
             LEFT JOIN empresa empr ON empr.empr_nb_id = u.user_nb_empresa
             LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
             LEFT JOIN operacao o ON o.oper_nb_id = e.enti_tx_tipoOperacao
             WHERE u.user_nb_id = ? LIMIT 1",
            "i",
            [$userId]
        );
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        if (!$row) {
            return null;
        }
        $telefone = trim(strval($row['user_tx_fone'] ?? ''));
        $origem = 'user';
        if ($telefone === '' && trim(strval($row['enti_tx_fone1'] ?? '')) !== '') {
            $telefone = trim(strval($row['enti_tx_fone1'] ?? ''));
            $origem = 'entidade';
        }
        if ($telefone === '') {
            $telefone = trim(strval($row['enti_tx_fone2'] ?? ''));
            $origem = 'entidade';
        }
        $r = wa_contato_upsert([
            'cont_nb_entidade' => (int)($row['user_nb_entidade'] ?? 0),
            'cont_nb_user' => $userId,
            'cont_nb_empresa' => (int)($row['user_nb_empresa'] ?? 0),
            'cont_tx_nome' => strval($row['user_tx_nome'] ?? $row['enti_tx_nome'] ?? ''),
            'cont_tx_telefone' => $telefone,
            'cont_tx_setor' => strval($row['setor_nome'] ?? ''),
            'cont_tx_cargo' => strval($row['cargo_nome'] ?? ''),
            'cont_tx_origem' => $origem,
        ]);
        return wa_contato_por_id((int)$r['id']);
    }
}

if (!function_exists('wa_contato_resolver_por_telefone')) {
    function wa_contato_resolver_por_telefone(string $telefone, string $nome = ''): ?array {
        $e164 = wa_normalizar_telefone($telefone);
        if ($e164 === null) {
            return null;
        }
        $rs = query("SELECT * FROM wa_contato WHERE cont_tx_e164 = ? LIMIT 1", "s", [$e164]);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        if ($row) {
            return $row;
        }
        $r = wa_contato_upsert([
            'cont_tx_nome' => $nome !== '' ? $nome : wa_formatar_telefone($e164),
            'cont_tx_telefone' => $telefone,
            'cont_tx_origem' => 'manual',
        ]);
        return wa_contato_por_id((int)$r['id']);
    }
}

if (!function_exists('wa_contato_buscar')) {
    function wa_contato_buscar(string $termo, int $limite = 30): array {
        $termo = trim($termo);
        $limite = max(1, min(100, $limite));
        $like = '%' . $termo . '%';
        $rs = query(
            "SELECT c.*,
                    (SELECT conv_nb_id FROM wa_conversa cv WHERE cv.conv_nb_contato = c.cont_nb_id LIMIT 1) AS conv_nb_id
             FROM wa_contato c
             WHERE c.cont_tx_status = 'ativo'
               AND (? = '' OR c.cont_tx_nome LIKE ? OR c.cont_tx_telefone LIKE ? OR c.cont_tx_e164 LIKE ?)
             ORDER BY c.cont_tx_nome ASC
             LIMIT ?",
            "ssssi",
            [$termo, $like, $like, $like, $limite]
        );
        $out = [];
        while ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $out[] = $row;
        }
        return $out;
    }
}

if (!function_exists('wa_contatos_filtrados')) {
    function wa_contatos_filtrados(array $filtros = [], int $limite = 500): array {
        $where = ["c.cont_tx_status = 'ativo'"];
        $types = '';
        $vals = [];

        $busca = trim(strval($filtros['busca'] ?? ''));
        if ($busca !== '') {
            $where[] = "(c.cont_tx_nome LIKE ? OR c.cont_tx_telefone LIKE ? OR c.cont_tx_e164 LIKE ?)";
            $like = '%' . $busca . '%';
            $types .= 'sss';
            array_push($vals, $like, $like, $like);
        }
        $empresa = (int)($filtros['empresa'] ?? 0);
        if ($empresa > 0) {
            $where[] = 'c.cont_nb_empresa = ?';
            $types .= 'i';
            $vals[] = $empresa;
        }
        $setor = trim(strval($filtros['setor'] ?? ''));
        if ($setor !== '') {
            $where[] = 'c.cont_tx_setor = ?';
            $types .= 's';
            $vals[] = $setor;
        }
        $cargo = trim(strval($filtros['cargo'] ?? ''));
        if ($cargo !== '') {
            $where[] = 'c.cont_tx_cargo = ?';
            $types .= 's';
            $vals[] = $cargo;
        }
        if (!empty($filtros['somente_optin'])) {
            $where[] = "c.cont_tx_optin = 'sim'";
        }
        if (!empty($filtros['somente_com_whatsapp'])) {
            $where[] = 'c.cont_tx_e164 IS NOT NULL';
        }
        $limite = max(1, min(1000, $limite));
        $sql = "SELECT c.*, empr.empr_tx_nome AS cont_tx_empresa_nome,
                    (SELECT conv_nb_id FROM wa_conversa cv WHERE cv.conv_nb_contato = c.cont_nb_id LIMIT 1) AS conv_nb_id
                FROM wa_contato c
                LEFT JOIN empresa empr ON empr.empr_nb_id = c.cont_nb_empresa
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.cont_tx_nome ASC
                LIMIT ?";
        $types .= 'i';
        $vals[] = $limite;

        $rs = query($sql, $types, $vals);
        $out = [];
        while ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $out[] = $row;
        }
        return $out;
    }
}

if (!function_exists('wa_listar_empresas')) {
    function wa_listar_empresas(): array {
        $rs = query("SELECT empr_nb_id AS id, empr_tx_nome AS nome FROM empresa ORDER BY empr_tx_nome ASC");
        $out = [];
        while ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $out[] = $row;
        }
        return $out;
    }
}

if (!function_exists('wa_listar_setores')) {
    function wa_listar_setores(): array {
        $rs = query("SELECT DISTINCT cont_tx_setor AS nome FROM wa_contato WHERE cont_tx_setor IS NOT NULL AND cont_tx_setor <> '' ORDER BY cont_tx_setor ASC");
        $out = [];
        while ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $out[] = strval($row['nome']);
        }
        return $out;
    }
}

if (!function_exists('wa_listar_cargos')) {
    function wa_listar_cargos(): array {
        $rs = query("SELECT DISTINCT cont_tx_cargo AS nome FROM wa_contato WHERE cont_tx_cargo IS NOT NULL AND cont_tx_cargo <> '' ORDER BY cont_tx_cargo ASC");
        $out = [];
        while ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $out[] = strval($row['nome']);
        }
        return $out;
    }
}
