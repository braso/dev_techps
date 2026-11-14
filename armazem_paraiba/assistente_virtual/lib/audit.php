<?php
/**
 * Persistência de conversas, mensagens, feedback e auditoria do assistente.
 */

if (!function_exists('ia_titulo_conversa')) {
    function ia_titulo_conversa(string $pergunta): string {
        $t = trim(preg_replace('/\s+/u', ' ', $pergunta));
        return mb_strlen($t, 'UTF-8') > 80 ? mb_substr($t, 0, 77, 'UTF-8') . '...' : $t;
    }
}

if (!function_exists('ia_criar_conversa')) {
    function ia_criar_conversa(string $titulo = ''): int {
        $uid = ia_user_id();
        $stmt = ia_db_query(
            "INSERT INTO ia_conversa (user_nb_id, conv_tx_titulo, conv_tx_tenant, conv_dt_criacao, conv_dt_atualizacao, conv_tx_status)
             VALUES (?, ?, ?, NOW(), NOW(), 'ativa')",
            'iss',
            [$uid, $titulo !== '' ? $titulo : 'Nova conversa', ia_tenant()]
        );
        return $stmt ? ia_db_insert_id($stmt) : 0;
    }
}

if (!function_exists('ia_conversa_do_usuario')) {
    function ia_conversa_do_usuario(int $convId): ?array {
        if ($convId <= 0) { return null; }
        return ia_db_one("SELECT * FROM ia_conversa WHERE conv_nb_id=? AND user_nb_id=? LIMIT 1", 'ii', [$convId, ia_user_id()]);
    }
}

if (!function_exists('ia_atualizar_conversa')) {
    function ia_atualizar_conversa(int $convId, string $titulo = ''): void {
        if ($convId <= 0) { return; }
        if ($titulo !== '') {
            ia_db_query("UPDATE ia_conversa SET conv_dt_atualizacao=NOW(), conv_tx_titulo=? WHERE conv_nb_id=? AND user_nb_id=?", 'sii', [$titulo, $convId, ia_user_id()]);
        } else {
            ia_db_query("UPDATE ia_conversa SET conv_dt_atualizacao=NOW() WHERE conv_nb_id=? AND user_nb_id=?", 'ii', [$convId, ia_user_id()]);
        }
    }
}

if (!function_exists('ia_salvar_mensagem')) {
    function ia_salvar_mensagem(int $convId, string $papel, string $conteudo, array $fontes = [], int $tokensIn = 0, int $tokensOut = 0, int $latencia = 0): int {
        $papel = in_array($papel, ['user', 'assistant', 'system'], true) ? $papel : 'assistant';
        $fontesJson = !empty($fontes) ? json_encode($fontes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $stmt = ia_db_query(
            "INSERT INTO ia_mensagem (conv_nb_id, msg_tx_papel, msg_tx_conteudo, msg_tx_fontes, msg_nb_tokens_entrada, msg_nb_tokens_saida, msg_nb_latencia_ms, msg_dt_criacao)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            'isssiii',
            [$convId, $papel, $conteudo, $fontesJson, $tokensIn, $tokensOut, $latencia]
        );
        return $stmt ? ia_db_insert_id($stmt) : 0;
    }
}

if (!function_exists('ia_ultimas_mensagens')) {
    function ia_ultimas_mensagens(int $convId, int $limit = 10): array {
        if ($convId <= 0) { return []; }
        $limit = max(1, min(30, $limit));
        $rows = ia_db_all("SELECT msg_tx_papel, msg_tx_conteudo, msg_dt_criacao FROM ia_mensagem WHERE conv_nb_id=? ORDER BY msg_nb_id DESC LIMIT $limit", 'i', [$convId]);
        return array_reverse($rows);
    }
}

if (!function_exists('ia_listar_conversas')) {
    function ia_listar_conversas(int $limit = 20): array {
        $limit = max(1, min(50, $limit));
        return ia_db_all(
            "SELECT conv_nb_id, conv_tx_titulo, conv_dt_atualizacao
               FROM ia_conversa
              WHERE user_nb_id=? AND conv_tx_status='ativa'
              ORDER BY conv_dt_atualizacao DESC
              LIMIT $limit",
            'i',
            [ia_user_id()]
        );
    }
}

if (!function_exists('ia_log_consulta')) {
    function ia_log_consulta(string $pergunta, array $chunks, string $modelo, int $tokensIn, int $tokensOut, int $latencia, string $resultado, int $convId = 0, string $tela = ''): void {
        $hash = hash('sha256', ia_normalizar_busca($pergunta));
        $ids = [];
        foreach ($chunks as $c) {
            if (!empty($c['chk_nb_id'])) { $ids[] = (int)$c['chk_nb_id']; }
        }
        ia_db_query(
            "INSERT INTO ia_consulta_log
                (user_nb_id, conv_nb_id, log_tx_tenant, log_tx_tela, log_tx_pergunta_hash, log_tx_pergunta, log_tx_chunks, log_tx_modelo, log_nb_tokens_entrada, log_nb_tokens_saida, log_nb_latencia_ms, log_tx_resultado, log_dt_criacao)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            'iissssssiiis',
            [
                ia_user_id(),
                $convId,
                ia_tenant(),
                mb_substr($tela, 0, 255, 'UTF-8'),
                $hash,
                mb_substr(ia_redigir_texto($pergunta), 0, 2000, 'UTF-8'),
                json_encode($ids),
                mb_substr($modelo, 0, 80, 'UTF-8'),
                $tokensIn,
                $tokensOut,
                $latencia,
                $resultado,
            ]
        );
    }
}

if (!function_exists('ia_salvar_feedback')) {
    function ia_salvar_feedback(int $msgId, int $nota, string $comentario = ''): bool {
        if ($msgId <= 0) { return false; }
        // Garante que a mensagem pertence a uma conversa do usuário.
        $msg = ia_db_one(
            "SELECT m.msg_nb_id FROM ia_mensagem m JOIN ia_conversa c ON c.conv_nb_id=m.conv_nb_id WHERE m.msg_nb_id=? AND c.user_nb_id=? LIMIT 1",
            'ii',
            [$msgId, ia_user_id()]
        );
        if (!$msg) { return false; }
        $nota = $nota > 0 ? 1 : -1;
        $stmt = ia_db_query(
            "INSERT INTO ia_feedback (msg_nb_id, user_nb_id, fb_nb_nota, fb_tx_comentario, fb_dt_criacao)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE fb_nb_nota=VALUES(fb_nb_nota), fb_tx_comentario=VALUES(fb_tx_comentario), fb_dt_criacao=NOW()",
            'iiis',
            [$msgId, ia_user_id(), $nota, mb_substr($comentario, 0, 1000, 'UTF-8')]
        );
        return (bool)$stmt;
    }
}

if (!function_exists('ia_rate_limit_ok')) {
    function ia_rate_limit_ok(): bool {
        $limite = ia_settings()['rate_limit'];
        $agora = time();
        $marcas = $_SESSION['ia_rate'] ?? [];
        if (!is_array($marcas)) { $marcas = []; }
        $marcas = array_values(array_filter($marcas, fn($t) => is_numeric($t) && ($agora - (int)$t) < 60));
        if (count($marcas) >= $limite) {
            $_SESSION['ia_rate'] = $marcas;
            return false;
        }
        $marcas[] = $agora;
        $_SESSION['ia_rate'] = $marcas;
        return true;
    }
}

if (!function_exists('ia_quota_ok')) {
    function ia_quota_ok(): bool {
        $quota = ia_settings()['daily_quota'];
        $usadas = (int)(ia_db_val(
            "SELECT COUNT(*) FROM ia_consulta_log WHERE user_nb_id=? AND DATE(log_dt_criacao)=CURDATE()",
            'i',
            [ia_user_id()]
        ) ?? 0);
        return $usadas < $quota;
    }
}

if (!function_exists('ia_limpar_retencao')) {
    function ia_limpar_retencao(): void {
        $dias = ia_settings()['retention_days'];
        if ($dias <= 0) { return; }
        $antigas = ia_db_all("SELECT conv_nb_id FROM ia_conversa WHERE conv_dt_atualizacao < DATE_SUB(NOW(), INTERVAL ? DAY)", 'i', [$dias]);
        foreach ($antigas as $c) {
            $id = (int)$c['conv_nb_id'];
            ia_db_query("DELETE FROM ia_mensagem WHERE conv_nb_id=?", 'i', [$id]);
            ia_db_query("DELETE FROM ia_conversa WHERE conv_nb_id=?", 'i', [$id]);
        }
        ia_db_query("DELETE FROM ia_consulta_log WHERE log_dt_criacao < DATE_SUB(NOW(), INTERVAL ? DAY)", 'i', [$dias]);
    }
}

if (!function_exists('ia_estatisticas')) {
    function ia_estatisticas(): array {
        return [
            'docs' => (int)(ia_db_val("SELECT COUNT(*) FROM ia_documento WHERE doc_tx_status='ativo'") ?? 0),
            'chunks' => (int)(ia_db_val("SELECT COUNT(*) FROM ia_chunk WHERE chk_bl_ativo=1") ?? 0),
            'faqs' => (int)(ia_db_val("SELECT COUNT(*) FROM ia_faq WHERE faq_tx_status='ativo'") ?? 0),
            'conversas_hoje' => (int)(ia_db_val("SELECT COUNT(DISTINCT conv_nb_id) FROM ia_consulta_log WHERE DATE(log_dt_criacao)=CURDATE()") ?? 0),
            'perguntas_hoje' => (int)(ia_db_val("SELECT COUNT(*) FROM ia_consulta_log WHERE DATE(log_dt_criacao)=CURDATE()") ?? 0),
            'sem_resposta' => (int)(ia_db_val("SELECT COUNT(*) FROM ia_consulta_log WHERE log_tx_resultado='sem_base' AND DATE(log_dt_criacao)=CURDATE()") ?? 0),
            'feedback_positivo' => (int)(ia_db_val("SELECT COUNT(*) FROM ia_feedback WHERE fb_nb_nota>0") ?? 0),
            'feedback_negativo' => (int)(ia_db_val("SELECT COUNT(*) FROM ia_feedback WHERE fb_nb_nota<0") ?? 0),
        ];
    }
}

if (!function_exists('ia_perguntas_sem_resposta')) {
    function ia_perguntas_sem_resposta(int $limit = 20): array {
        $limit = max(1, min(100, $limit));
        return ia_db_all(
            "SELECT log_tx_pergunta_hash, MIN(log_tx_pergunta) AS pergunta, COUNT(*) AS total
               FROM ia_consulta_log
              WHERE log_tx_resultado IN ('sem_base','extrativo')
              GROUP BY log_tx_pergunta_hash
              ORDER BY total DESC
              LIMIT $limit"
        );
    }
}
