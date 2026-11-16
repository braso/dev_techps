<?php

/**
 * Mensagens pré-prontas do CRM - TECHPS.
 *
 * O título funciona como agrupador ("quando usar") e o subtítulo como
 * variação selecionável. O corpo aceita as mesmas variáveis do envio em lote.
 */

if (!function_exists('wa_mensagens_prontas_listar')) {
    function wa_mensagens_prontas_listar(bool $somenteAtivas = true): array {
        $where = $somenteAtivas ? "WHERE mpr_tx_status = 'ativo'" : '';
        $rs = query(
            "SELECT mpr_nb_id, mpr_tx_titulo, mpr_tx_subtitulo, mpr_tx_mensagem,
                    mpr_tx_status, mpr_nb_userCadastro,
                    mpr_dt_criacao, mpr_dt_atualizacao
             FROM wa_mensagem_pronta
             {$where}
             ORDER BY mpr_tx_titulo ASC, mpr_tx_subtitulo ASC, mpr_nb_id ASC"
        );
        $out = [];
        while ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $out[] = $row;
        }
        return $out;
    }
}

if (!function_exists('wa_mensagem_pronta_por_id')) {
    function wa_mensagem_pronta_por_id(int $id): ?array {
        if ($id <= 0) {
            return null;
        }
        $rs = query("SELECT * FROM wa_mensagem_pronta WHERE mpr_nb_id = ? LIMIT 1", "i", [$id]);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        return $row ?: null;
    }
}

if (!function_exists('wa_mensagem_pronta_salvar')) {
    function wa_mensagem_pronta_salvar(array $dados, int $usuarioId = 0): array {
        $id = (int)($dados['id'] ?? 0);
        $titulo = trim(strval($dados['titulo'] ?? ''));
        $subtitulo = trim(strval($dados['subtitulo'] ?? ''));
        $mensagem = trim(strval($dados['mensagem'] ?? ''));
        $status = strval($dados['status'] ?? 'ativo') === 'inativo' ? 'inativo' : 'ativo';

        if ($titulo === '') {
            return ['success' => false, 'message' => 'Informe o título (quando usar).'];
        }
        if ($subtitulo === '') {
            return ['success' => false, 'message' => 'Informe o subtítulo.'];
        }
        if ($mensagem === '') {
            return ['success' => false, 'message' => 'Informe a mensagem.'];
        }
        if (mb_strlen($titulo) > 180 || mb_strlen($subtitulo) > 180) {
            return ['success' => false, 'message' => 'Título e subtítulo devem ter até 180 caracteres.'];
        }
        if (mb_strlen($mensagem) > 4096) {
            return ['success' => false, 'message' => 'A mensagem deve ter até 4096 caracteres.'];
        }

        $rsDup = query(
            "SELECT mpr_nb_id FROM wa_mensagem_pronta
             WHERE mpr_tx_titulo = ? AND mpr_tx_subtitulo = ? AND mpr_nb_id <> ? LIMIT 1",
            "ssi",
            [$titulo, $subtitulo, $id]
        );
        if ($rsDup && mysqli_fetch_assoc($rsDup)) {
            return ['success' => false, 'message' => 'Já existe uma mensagem com esse título e subtítulo.'];
        }

        if ($id > 0) {
            $ok = wa_exec(
                "UPDATE wa_mensagem_pronta
                 SET mpr_tx_titulo = ?, mpr_tx_subtitulo = ?, mpr_tx_mensagem = ?,
                     mpr_tx_status = ?, mpr_dt_atualizacao = NOW()
                 WHERE mpr_nb_id = ?",
                "ssssi",
                [$titulo, $subtitulo, $mensagem, $status, $id]
            );
            if (!$ok) {
                return ['success' => false, 'message' => 'Não foi possível atualizar a mensagem.'];
            }
            return ['success' => true, 'message' => 'Mensagem atualizada.', 'id' => $id];
        }

        $ok = wa_exec(
            "INSERT INTO wa_mensagem_pronta
                (mpr_tx_titulo, mpr_tx_subtitulo, mpr_tx_mensagem, mpr_tx_status, mpr_nb_userCadastro)
             VALUES (?, ?, ?, ?, ?)",
            "ssssi",
            [$titulo, $subtitulo, $mensagem, $status, $usuarioId > 0 ? $usuarioId : null]
        );
        if (!$ok) {
            return ['success' => false, 'message' => 'Não foi possível cadastrar a mensagem.'];
        }
        return ['success' => true, 'message' => 'Mensagem cadastrada.', 'id' => (int)mysqli_insert_id($GLOBALS['conn'])];
    }
}

if (!function_exists('wa_mensagem_pronta_excluir')) {
    function wa_mensagem_pronta_excluir(int $id): bool {
        if ($id <= 0) {
            return false;
        }
        return wa_exec("DELETE FROM wa_mensagem_pronta WHERE mpr_nb_id = ?", "i", [$id]);
    }
}
