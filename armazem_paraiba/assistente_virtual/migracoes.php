<?php
/**
 * Migrações do Assistente Virtual.
 *
 * Cria as tabelas ia_* e registra os itens de menu. Segue o padrão tolerante
 * do módulo WhatsApp: falhas de estrutura vão para o log e não derrubam o
 * restante do sistema.
 */

if (!defined('IA_MODULE_DIR')) {
    define('IA_MODULE_DIR', __DIR__);
}

if (!function_exists('ia_tabela_existe')) {
    function ia_tabela_existe($conn, string $tabela): bool {
        $tabela = mysqli_real_escape_string($conn, $tabela);
        $rs = @mysqli_query($conn, "SHOW TABLES LIKE '{$tabela}'");
        return ($rs instanceof mysqli_result) && mysqli_num_rows($rs) > 0;
    }
}

if (!function_exists('ia_executar_sql')) {
    function ia_executar_sql($conn, string $sql): bool {
        if (@mysqli_query($conn, $sql)) {
            return true;
        }
        error_log('[assistente][migracao] ' . mysqli_error($conn) . ' SQL: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 220));
        return false;
    }
}

if (!function_exists('ia_meta_gravar')) {
    function ia_meta_gravar($conn, string $chave, string $valor): void {
        if (!ia_tabela_existe($conn, 'ia_meta')) {
            return;
        }
        $stmt = @mysqli_prepare($conn, "INSERT INTO ia_meta (meta_tx_chave, meta_tx_valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE meta_tx_valor = VALUES(meta_tx_valor)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $chave, $valor);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

if (!function_exists('ia_meta_ler')) {
    function ia_meta_ler($conn, string $chave): ?string {
        if (!ia_tabela_existe($conn, 'ia_meta')) {
            return null;
        }
        $stmt = @mysqli_prepare($conn, "SELECT meta_tx_valor FROM ia_meta WHERE meta_tx_chave = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 's', $chave);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ? strval($row['meta_tx_valor']) : null;
    }
}

if (!function_exists('ia_criar_tabelas')) {
    function ia_criar_tabelas($conn): void {
        ia_executar_sql($conn, "CREATE TABLE IF NOT EXISTS ia_meta (
            meta_tx_chave VARCHAR(80) NOT NULL PRIMARY KEY,
            meta_tx_valor TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        ia_executar_sql($conn, "CREATE TABLE IF NOT EXISTS ia_documento (
            doc_nb_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            doc_tx_titulo VARCHAR(200) NOT NULL,
            doc_tx_fonte VARCHAR(40) NOT NULL DEFAULT 'manual',
            doc_tx_arquivo VARCHAR(500) NULL,
            doc_tx_hash CHAR(64) NULL,
            doc_tx_modulo VARCHAR(60) NULL,
            doc_tx_versao VARCHAR(40) NULL,
            doc_tx_owner VARCHAR(120) NULL,
            doc_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            doc_dt_revisao DATETIME NULL,
            doc_dt_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            doc_dt_atualizacao DATETIME NULL,
            KEY idx_ia_doc_status (doc_tx_status),
            KEY idx_ia_doc_modulo (doc_tx_modulo),
            KEY idx_ia_doc_hash (doc_tx_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $sqlChunk = "CREATE TABLE IF NOT EXISTS ia_chunk (
            chk_nb_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            doc_nb_id INT NOT NULL,
            chk_nb_ordem INT NOT NULL DEFAULT 0,
            chk_tx_titulo VARCHAR(200) NULL,
            chk_tx_conteudo MEDIUMTEXT NOT NULL,
            chk_tx_busca MEDIUMTEXT NULL,
            chk_tx_perfis VARCHAR(255) NULL,
            chk_tx_menu_path VARCHAR(255) NULL,
            chk_tx_embedding MEDIUMTEXT NULL,
            chk_bl_ativo TINYINT(1) NOT NULL DEFAULT 1,
            chk_dt_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ia_chk_doc (doc_nb_id),
            KEY idx_ia_chk_ativo (chk_bl_ativo),
            KEY idx_ia_chk_path (chk_tx_menu_path),
            FULLTEXT KEY ft_ia_chk (chk_tx_titulo, chk_tx_conteudo),
            FULLTEXT KEY ft_ia_chk_titulo (chk_tx_titulo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!ia_executar_sql($conn, $sqlChunk)) {
            // Alguns MySQL/MariaDB antigos recusam FULLTEXT em InnoDB. A busca
            // lexical cai para LIKE; o assistente continua funcionando.
            $semFulltext = preg_replace('/,\s*FULLTEXT KEY\s+\w+\s*\([^)]*\)/i', '', $sqlChunk);
            ia_executar_sql($conn, $semFulltext);
        }
        // Instalações antigas podem ter a tabela sem o índice de título.
        $temFtTitulo = @mysqli_query($conn, "SHOW INDEX FROM ia_chunk WHERE Key_name='ft_ia_chk_titulo'");
        if (!($temFtTitulo instanceof mysqli_result) || mysqli_num_rows($temFtTitulo) === 0) {
            @mysqli_query($conn, "ALTER TABLE ia_chunk ADD FULLTEXT KEY ft_ia_chk_titulo (chk_tx_titulo)");
        }

        $sqlFaq = "CREATE TABLE IF NOT EXISTS ia_faq (
            faq_nb_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            faq_tx_pergunta VARCHAR(500) NOT NULL,
            faq_tx_resposta MEDIUMTEXT NOT NULL,
            faq_tx_modulo VARCHAR(60) NULL,
            faq_tx_menu_path VARCHAR(255) NULL,
            faq_tx_perfis VARCHAR(255) NULL,
            faq_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            faq_dt_atualizacao DATETIME NULL,
            FULLTEXT KEY ft_ia_faq (faq_tx_pergunta, faq_tx_resposta)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!ia_executar_sql($conn, $sqlFaq)) {
            ia_executar_sql($conn, preg_replace('/,\s*FULLTEXT KEY\s+\w+\s*\([^)]*\)/i', '', $sqlFaq));
        }

        ia_executar_sql($conn, "CREATE TABLE IF NOT EXISTS ia_conversa (
            conv_nb_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_nb_id INT NOT NULL DEFAULT 0,
            conv_tx_titulo VARCHAR(160) NULL,
            conv_tx_tenant VARCHAR(80) NULL,
            conv_dt_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            conv_dt_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            conv_tx_status ENUM('ativa','arquivada') NOT NULL DEFAULT 'ativa',
            KEY idx_ia_conv_user (user_nb_id),
            KEY idx_ia_conv_dt (conv_dt_atualizacao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        ia_executar_sql($conn, "CREATE TABLE IF NOT EXISTS ia_mensagem (
            msg_nb_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            conv_nb_id INT NOT NULL,
            msg_tx_papel ENUM('user','assistant','system') NOT NULL,
            msg_tx_conteudo MEDIUMTEXT NOT NULL,
            msg_tx_fontes MEDIUMTEXT NULL,
            msg_nb_tokens_entrada INT NOT NULL DEFAULT 0,
            msg_nb_tokens_saida INT NOT NULL DEFAULT 0,
            msg_nb_latencia_ms INT NOT NULL DEFAULT 0,
            msg_dt_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ia_msg_conv (conv_nb_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        ia_executar_sql($conn, "CREATE TABLE IF NOT EXISTS ia_feedback (
            fb_nb_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            msg_nb_id INT NOT NULL,
            user_nb_id INT NOT NULL DEFAULT 0,
            fb_nb_nota TINYINT NOT NULL DEFAULT 0,
            fb_tx_comentario VARCHAR(1000) NULL,
            fb_dt_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_ia_fb_msg_user (msg_nb_id, user_nb_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        ia_executar_sql($conn, "CREATE TABLE IF NOT EXISTS ia_consulta_log (
            log_nb_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_nb_id INT NOT NULL DEFAULT 0,
            conv_nb_id INT NOT NULL DEFAULT 0,
            log_tx_tenant VARCHAR(80) NULL,
            log_tx_tela VARCHAR(255) NULL,
            log_tx_pergunta_hash CHAR(64) NULL,
            log_tx_pergunta TEXT NULL,
            log_tx_chunks TEXT NULL,
            log_tx_modelo VARCHAR(80) NULL,
            log_nb_tokens_entrada INT NOT NULL DEFAULT 0,
            log_nb_tokens_saida INT NOT NULL DEFAULT 0,
            log_nb_latencia_ms INT NOT NULL DEFAULT 0,
            log_tx_resultado VARCHAR(30) NOT NULL DEFAULT 'ok',
            log_dt_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ia_log_user (user_nb_id),
            KEY idx_ia_log_dt (log_dt_criacao),
            KEY idx_ia_log_resultado (log_tx_resultado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        ia_meta_gravar($conn, 'schema_version', '1');
    }
}

if (!function_exists('ia_menu_garantir_item')) {
    function ia_menu_garantir_item($conn, string $secao, string $label, string $path, string $chave, int $ordem): int {
        if (!ia_tabela_existe($conn, 'menu_item')) {
            return 0;
        }
        $menuId = 0;
        $stmt = @mysqli_prepare($conn, "SELECT menu_nb_id FROM menu_item WHERE menu_tx_path = ? ORDER BY menu_nb_id ASC LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $path);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if ($row) {
                $menuId = (int)$row['menu_nb_id'];
                $up = @mysqli_prepare($conn, "UPDATE menu_item SET menu_tx_secao = ?, menu_tx_label = ?, menu_tx_chave = ?, menu_tx_ordem = ?, menu_tx_ativo = 1 WHERE menu_nb_id = ?");
                if ($up) {
                    mysqli_stmt_bind_param($up, 'sssii', $secao, $label, $chave, $ordem, $menuId);
                    mysqli_stmt_execute($up);
                    mysqli_stmt_close($up);
                }
            }
        }
        if ($menuId <= 0) {
            $ins = @mysqli_prepare($conn, "INSERT INTO menu_item (menu_tx_secao, menu_tx_label, menu_tx_path, menu_tx_chave, menu_tx_ativo, menu_tx_ordem) VALUES (?, ?, ?, ?, 1, ?)");
            if ($ins) {
                mysqli_stmt_bind_param($ins, 'ssssi', $secao, $label, $path, $chave, $ordem);
                mysqli_stmt_execute($ins);
                $menuId = (int)mysqli_insert_id($conn);
                mysqli_stmt_close($ins);
            }
        }
        return $menuId;
    }
}

if (!function_exists('ia_menu_liberar_perfil')) {
    function ia_menu_liberar_perfil($conn, int $perfilId, int $menuId): void {
        if ($perfilId <= 0 || $menuId <= 0) {
            return;
        }
        $check = @mysqli_prepare($conn, "SELECT 1 FROM perfil_menu_item WHERE perfil_nb_id = ? AND menu_nb_id = ? LIMIT 1");
        if (!$check) {
            return;
        }
        mysqli_stmt_bind_param($check, 'ii', $perfilId, $menuId);
        mysqli_stmt_execute($check);
        $res = mysqli_stmt_get_result($check);
        $existe = $res && mysqli_num_rows($res) > 0;
        mysqli_stmt_close($check);
        if ($existe) {
            return;
        }
        $ins = @mysqli_prepare($conn, "INSERT INTO perfil_menu_item (perfil_nb_id, menu_nb_id, perm_ver, perm_inserir, perm_editar, perm_excluir) VALUES (?, ?, 1, 1, 1, 1)");
        if ($ins) {
            mysqli_stmt_bind_param($ins, 'ii', $perfilId, $menuId);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
        }
    }
}

if (!function_exists('ia_menu_revogar_perfil')) {
    function ia_menu_revogar_perfil($conn, int $perfilId, int $menuId): void {
        if ($perfilId <= 0 || $menuId <= 0) {
            return;
        }
        $stmt = @mysqli_prepare($conn, "DELETE FROM perfil_menu_item WHERE perfil_nb_id = ? AND menu_nb_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $perfilId, $menuId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

if (!function_exists('ia_migrar_menu')) {
    function ia_migrar_menu($conn): void {
        $menuIndex = ia_menu_garantir_item($conn, 'assistente', 'Assistente Virtual', '/assistente_virtual/index.php', 'assistente-index', 1);
        $menuAdmin = ia_menu_garantir_item($conn, 'assistente', 'Base de Conhecimento (IA)', '/assistente_virtual/admin.php', 'assistente-admin', 2);

        // Fase beta: acesso explícito por perfil. Administradores recebem os
        // dois itens; os demais perfis ficam sem acesso até o administrador
        // marcar "Assistente Virtual" em Cadastro de Perfil de Acesso.
        $rsPerfis = @mysqli_query($conn, "SELECT perfil_nb_id, perfil_tx_nome FROM perfil_acesso");
        if ($rsPerfis instanceof mysqli_result) {
            while ($p = mysqli_fetch_assoc($rsPerfis)) {
                $perfilId = (int)($p['perfil_nb_id'] ?? 0);
                $nome = strval($p['perfil_tx_nome'] ?? '');
                $isAdmin = stripos($nome, 'Administrador') !== false;
                if ($isAdmin) {
                    ia_menu_liberar_perfil($conn, $perfilId, $menuIndex);
                    ia_menu_liberar_perfil($conn, $perfilId, $menuAdmin);
                } else {
                    ia_menu_revogar_perfil($conn, $perfilId, $menuIndex);
                    ia_menu_revogar_perfil($conn, $perfilId, $menuAdmin);
                }
            }
        }
        ia_meta_gravar($conn, 'menu_version', '2');
    }
}

if (!function_exists('ia_migrar')) {
    function ia_migrar($conn): void {
        if (!($conn instanceof mysqli)) {
            return;
        }
        ia_criar_tabelas($conn);
        ia_migrar_menu($conn);
    }
}
