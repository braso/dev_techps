<?php
/**
 * Módulo WhatsApp — migrações e integração com o menu.
 *
 * Executado pelo bootstrap web (uma vez por versão) e pelo migrar.php (CLI).
 * Usa apenas mysqli direto para poder rodar também no bootstrap público
 * (webhook/cron), onde o conecta.php não é carregado.
 */

if (!function_exists('wa_tabela_existe')) {
    function wa_tabela_existe($conn, string $tabela): bool {
        $tabela = mysqli_real_escape_string($conn, $tabela);
        $rs = @mysqli_query($conn, "SHOW TABLES LIKE '{$tabela}'");
        return ($rs instanceof mysqli_result) && mysqli_num_rows($rs) > 0;
    }
}

if (!function_exists('wa_coluna_existe')) {
    function wa_coluna_existe($conn, string $tabela, string $coluna): bool {
        if (!wa_tabela_existe($conn, $tabela)) {
            return false;
        }
        $coluna = mysqli_real_escape_string($conn, $coluna);
        $rs = @mysqli_query($conn, "SHOW COLUMNS FROM `{$tabela}` LIKE '{$coluna}'");
        return ($rs instanceof mysqli_result) && mysqli_num_rows($rs) > 0;
    }
}

if (!function_exists('wa_garantir_coluna')) {
    function wa_garantir_coluna($conn, string $tabela, string $coluna, string $definicao): void {
        if (!wa_tabela_existe($conn, $tabela) || wa_coluna_existe($conn, $tabela, $coluna)) {
            return;
        }
        @mysqli_query($conn, "ALTER TABLE `{$tabela}` ADD COLUMN {$coluna} {$definicao}");
    }
}

if (!function_exists('wa_executar_sql')) {
    function wa_executar_sql($conn, string $sql): bool {
        if (@mysqli_query($conn, $sql)) {
            return true;
        }
        error_log('[whatsapp][migracao] ' . mysqli_error($conn) . ' SQL: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 180));
        return false;
    }
}

if (!function_exists('wa_meta_ler')) {
    function wa_meta_ler($conn, string $chave): ?string {
        if (!wa_tabela_existe($conn, 'wa_meta')) {
            return null;
        }
        $chaveEsc = mysqli_real_escape_string($conn, $chave);
        $rs = @mysqli_query($conn, "SELECT meta_tx_valor FROM wa_meta WHERE meta_tx_chave = '{$chaveEsc}' LIMIT 1");
        $row = ($rs instanceof mysqli_result) ? mysqli_fetch_assoc($rs) : null;
        return $row ? strval($row['meta_tx_valor']) : null;
    }
}

if (!function_exists('wa_meta_gravar')) {
    function wa_meta_gravar($conn, string $chave, string $valor): void {
        $stmt = @mysqli_prepare($conn, "INSERT INTO wa_meta (meta_tx_chave, meta_tx_valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE meta_tx_valor = VALUES(meta_tx_valor)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $chave, $valor);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

if (!function_exists('wa_menu_garantir_item')) {
    function wa_menu_garantir_item($conn, string $label, string $path, string $chave, int $ordem): void {
        if (!wa_tabela_existe($conn, 'menu_item')) {
            return;
        }
        $secao = 'whatsapp';
        $rs = @mysqli_prepare($conn, "SELECT menu_nb_id FROM menu_item WHERE menu_tx_path = ? ORDER BY menu_nb_id ASC LIMIT 1");
        if (!$rs) {
            return;
        }
        mysqli_stmt_bind_param($rs, 's', $path);
        mysqli_stmt_execute($rs);
        $res = mysqli_stmt_get_result($rs);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($rs);

        if ($row) {
            $menuId = (int)$row['menu_nb_id'];
            $up = @mysqli_prepare($conn, "UPDATE menu_item SET menu_tx_secao = ?, menu_tx_label = ?, menu_tx_chave = ?, menu_tx_ordem = ?, menu_tx_ativo = 1 WHERE menu_nb_id = ?");
            if ($up) {
                mysqli_stmt_bind_param($up, 'sssii', $secao, $label, $chave, $ordem, $menuId);
                mysqli_stmt_execute($up);
                mysqli_stmt_close($up);
            }
        } else {
            $ins = @mysqli_prepare($conn, "INSERT INTO menu_item (menu_tx_secao, menu_tx_label, menu_tx_path, menu_tx_chave, menu_tx_ativo, menu_tx_ordem) VALUES (?, ?, ?, ?, 1, ?)");
            if ($ins) {
                mysqli_stmt_bind_param($ins, 'ssssi', $secao, $label, $path, $chave, $ordem);
                mysqli_stmt_execute($ins);
                $menuId = (int)mysqli_insert_id($conn);
                mysqli_stmt_close($ins);
            }
        }

        if (empty($menuId)) {
            return;
        }

        // Libera para os perfis administrativos. Os demais perfis são liberados
        // manualmente em Cadastro de Perfil de Acesso.
        $rsPerfis = @mysqli_query($conn, "SELECT perfil_nb_id FROM perfil_acesso WHERE perfil_tx_nome LIKE '%Administrador%'");
        if (!($rsPerfis instanceof mysqli_result)) {
            return;
        }
        while ($perfil = mysqli_fetch_assoc($rsPerfis)) {
            $perfilId = (int)($perfil['perfil_nb_id'] ?? 0);
            if ($perfilId <= 0) {
                continue;
            }
            $check = @mysqli_prepare($conn, "SELECT 1 FROM perfil_menu_item WHERE perfil_nb_id = ? AND menu_nb_id = ? LIMIT 1");
            if (!$check) {
                continue;
            }
            mysqli_stmt_bind_param($check, 'ii', $perfilId, $menuId);
            mysqli_stmt_execute($check);
            $resCheck = mysqli_stmt_get_result($check);
            $existe = $resCheck && mysqli_num_rows($resCheck) > 0;
            mysqli_stmt_close($check);
            if ($existe) {
                continue;
            }
            $insPerm = @mysqli_prepare($conn, "INSERT INTO perfil_menu_item (perfil_nb_id, menu_nb_id, perm_ver, perm_inserir, perm_editar, perm_excluir) VALUES (?, ?, 1, 1, 1, 1)");
            if ($insPerm) {
                mysqli_stmt_bind_param($insPerm, 'ii', $perfilId, $menuId);
                mysqli_stmt_execute($insPerm);
                mysqli_stmt_close($insPerm);
            }
        }
    }
}

if (!function_exists('wa_migrar_menu')) {
    function wa_migrar_menu($conn): void {
        $itens = [
            ['Chat', '/whatsapp/index.php', 'whatsapp-chat', 1],
            ['Contatos', '/whatsapp/contatos.php', 'whatsapp-contatos', 2],
            ['Envio em Lote', '/whatsapp/lote.php', 'whatsapp-lote', 3],
            ['Configurações', '/whatsapp/configuracoes.php', 'whatsapp-config', 4],
            ['Mensagens Prontas', '/whatsapp/mensagens_prontas.php', 'whatsapp-mensagens-prontas', 5],
        ];
        foreach ($itens as $item) {
            wa_menu_garantir_item($conn, $item[0], $item[1], $item[2], $item[3]);
        }
        wa_meta_gravar($conn, 'menu_version', '2');
    }
}

if (!function_exists('wa_migrar')) {
    function wa_migrar($conn, bool $forcar = false): void {
        if (!($conn instanceof mysqli)) {
            return;
        }

        $versaoAtual = 6;
        $versao = wa_meta_ler($conn, 'schema_version');
        $menuVersao = wa_meta_ler($conn, 'menu_version');

        if (!$forcar && $versao !== null && (int)$versao >= $versaoAtual && (int)$menuVersao >= 2) {
            return;
        }

        $reportAnterior = mysqli_report(MYSQLI_REPORT_OFF);
        try {
            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_meta (
                meta_tx_chave VARCHAR(80) PRIMARY KEY,
                meta_tx_valor VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_config (
                cfg_nb_id INT AUTO_INCREMENT PRIMARY KEY,
                cfg_tx_provider ENUM('cloud','evolution') NOT NULL DEFAULT 'evolution',
                cfg_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'inativo',
                cfg_tx_base_url VARCHAR(255) NULL,
                cfg_tx_instance VARCHAR(120) NULL,
                cfg_tx_token TEXT NULL,
                cfg_tx_phone_number_id VARCHAR(80) NULL,
                cfg_tx_waba_id VARCHAR(80) NULL,
                cfg_tx_verify_token VARCHAR(120) NULL,
                cfg_tx_app_secret TEXT NULL,
                cfg_tx_webhook_url VARCHAR(255) NULL,
                cfg_tx_test_number VARCHAR(30) NULL,
                cfg_nb_retencao_dias INT NOT NULL DEFAULT 0,
                cfg_tx_call_status ENUM('ativo','inativo') NOT NULL DEFAULT 'inativo',
                cfg_nb_userAtualiza INT NULL,
                cfg_tx_dataAtualiza DATETIME NULL,
                cfg_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_contato (
                cont_nb_id INT AUTO_INCREMENT PRIMARY KEY,
                cont_nb_entidade INT NULL,
                cont_nb_user INT NULL,
                cont_nb_empresa INT NULL,
                cont_tx_nome VARCHAR(200) NOT NULL,
                cont_tx_telefone VARCHAR(30) NULL,
                cont_tx_e164 VARCHAR(20) NULL,
                cont_tx_setor VARCHAR(150) NULL,
                cont_tx_cargo VARCHAR(150) NULL,
                cont_tx_origem ENUM('entidade','user','manual') DEFAULT 'entidade',
                cont_tx_status ENUM('ativo','inativo') DEFAULT 'ativo',
                cont_tx_optin ENUM('sim','nao') DEFAULT 'nao',
                cont_dt_optin DATETIME NULL,
                cont_dt_ultima_sincronizacao DATETIME NULL,
                cont_dt_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_e164 (cont_tx_e164),
                KEY idx_entidade (cont_nb_entidade),
                KEY idx_user (cont_nb_user),
                KEY idx_nome (cont_tx_nome)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_conversa (
                conv_nb_id INT AUTO_INCREMENT PRIMARY KEY,
                conv_nb_contato INT NOT NULL,
                conv_nb_responsavel INT NULL,
                conv_tx_status ENUM('aberta','pendente','resolvida') DEFAULT 'aberta',
                conv_tx_ultima_mensagem VARCHAR(255) NULL,
                conv_dt_ultima_atividade DATETIME NULL,
                conv_nb_nao_lidas INT DEFAULT 0,
                UNIQUE KEY uk_contato (conv_nb_contato),
                KEY idx_ultima (conv_dt_ultima_atividade)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_mensagem (
                msg_nb_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                msg_nb_conversa INT NOT NULL,
                msg_tx_direcao ENUM('in','out') NOT NULL,
                msg_tx_tipo ENUM('texto','imagem','audio','documento','video','template','sistema') DEFAULT 'texto',
                msg_tx_conteudo TEXT NULL,
                msg_tx_status ENUM('pendente','enviado','entregue','lido','falhou','recebido') DEFAULT 'pendente',
                msg_tx_provider_id VARCHAR(120) NULL,
                msg_tx_media_id VARCHAR(120) NULL,
                msg_tx_mime VARCHAR(100) NULL,
                msg_tx_arquivo VARCHAR(300) NULL,
                msg_tx_erro VARCHAR(255) NULL,
                msg_nb_user INT NULL,
                msg_dt_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
                msg_dt_atualizacao DATETIME NULL,
                KEY idx_conversa (msg_nb_conversa, msg_nb_id),
                KEY idx_provider (msg_tx_provider_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_anexo (
                anx_nb_id INT AUTO_INCREMENT PRIMARY KEY,
                anx_nb_msg BIGINT NOT NULL,
                anx_tx_arquivo VARCHAR(300) NOT NULL,
                anx_tx_nome VARCHAR(200) NULL,
                anx_tx_mime VARCHAR(100) NULL,
                anx_nb_tamanho INT NULL,
                anx_tx_direcao ENUM('in','out') DEFAULT 'out',
                anx_dt_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_msg (anx_nb_msg)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_mensagem_pronta (
                mpr_nb_id INT AUTO_INCREMENT PRIMARY KEY,
                mpr_tx_titulo VARCHAR(180) NOT NULL,
                mpr_tx_subtitulo VARCHAR(180) NOT NULL,
                mpr_tx_mensagem TEXT NOT NULL,
                mpr_tx_status ENUM('ativo','inativo') DEFAULT 'ativo',
                mpr_nb_ordem INT DEFAULT 0,
                mpr_nb_userCadastro INT NULL,
                mpr_dt_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
                mpr_dt_atualizacao DATETIME NULL,
                UNIQUE KEY uk_titulo_subtitulo (mpr_tx_titulo, mpr_tx_subtitulo),
                KEY idx_status_ordem (mpr_tx_status, mpr_nb_ordem)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_chamada (
                ch_nb_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                ch_tx_call_id VARCHAR(120) NULL,
                ch_nb_conversa INT NOT NULL,
                ch_nb_contato INT NOT NULL,
                ch_tx_direcao ENUM('in','out') NOT NULL,
                ch_tx_status ENUM('solicitando','tocando','conectada','encerrada','rejeitada','falhou') NOT NULL DEFAULT 'solicitando',
                ch_tx_sdp_offer LONGTEXT NULL,
                ch_tx_sdp_answer LONGTEXT NULL,
                ch_tx_erro VARCHAR(255) NULL,
                ch_nb_user INT NULL,
                ch_tx_opaque VARCHAR(512) NULL,
                ch_dt_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
                ch_dt_atualizacao DATETIME NULL,
                ch_dt_inicio DATETIME NULL,
                ch_dt_fim DATETIME NULL,
                ch_nb_duracao INT NULL,
                UNIQUE KEY uk_call_id (ch_tx_call_id),
                KEY idx_status (ch_tx_status, ch_tx_direcao),
                KEY idx_conversa (ch_nb_conversa)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_chamada_evento (
                che_nb_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                che_nb_chamada BIGINT NULL,
                che_tx_evento VARCHAR(40) NULL,
                che_tx_payload LONGTEXT NULL,
                che_dt_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_chamada (che_nb_chamada)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_lote (
                lote_nb_id INT AUTO_INCREMENT PRIMARY KEY,
                lote_tx_titulo VARCHAR(150) NULL,
                lote_tx_mensagem TEXT NOT NULL,
                lote_tx_status ENUM('rascunho','enviando','concluido','cancelado') DEFAULT 'rascunho',
                lote_nb_total INT DEFAULT 0,
                lote_nb_enviados INT DEFAULT 0,
                lote_nb_falhas INT DEFAULT 0,
                lote_nb_userCadastro INT NULL,
                lote_dt_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
                lote_dt_inicio DATETIME NULL,
                lote_dt_fim DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_lote_destinatario (
                ld_nb_id INT AUTO_INCREMENT PRIMARY KEY,
                ld_nb_lote INT NOT NULL,
                ld_nb_contato INT NOT NULL,
                ld_nb_mensagem BIGINT NULL,
                ld_tx_telefone VARCHAR(30) NULL,
                ld_tx_status ENUM('pendente','enviado','entregue','lido','falhou') DEFAULT 'pendente',
                ld_tx_erro VARCHAR(255) NULL,
                ld_tx_provider_id VARCHAR(120) NULL,
                ld_dt_atualizacao DATETIME NULL,
                UNIQUE KEY uk_lote_contato (ld_nb_lote, ld_nb_contato),
                KEY idx_lote (ld_nb_lote)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_fila (
                fil_nb_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                fil_tx_tipo ENUM('mensagem','lote') DEFAULT 'mensagem',
                fil_nb_referencia BIGINT NOT NULL,
                fil_tx_status ENUM('pendente','processando','enviado','falhou') DEFAULT 'pendente',
                fil_nb_tentativas INT DEFAULT 0,
                fil_dt_agendada DATETIME DEFAULT CURRENT_TIMESTAMP,
                fil_dt_processada DATETIME NULL,
                fil_tx_erro VARCHAR(255) NULL,
                KEY idx_status (fil_tx_status, fil_dt_agendada)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_template (
                tpl_nb_id INT AUTO_INCREMENT PRIMARY KEY,
                tpl_tx_nome VARCHAR(120) NOT NULL,
                tpl_tx_idioma VARCHAR(10) DEFAULT 'pt_BR',
                tpl_tx_categoria ENUM('MARKETING','UTILITY','AUTHENTICATION') DEFAULT 'UTILITY',
                tpl_tx_status VARCHAR(30) NULL,
                tpl_tx_componentes LONGTEXT NULL,
                tpl_dt_atualizacao DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            wa_executar_sql($conn, "CREATE TABLE IF NOT EXISTS wa_evento (
                evt_nb_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                evt_tx_provider VARCHAR(30) NOT NULL,
                evt_tx_event_id VARCHAR(160) NULL,
                evt_tx_tipo VARCHAR(40) NULL,
                evt_tx_payload LONGTEXT NULL,
                evt_tx_status ENUM('recebido','processado','erro') DEFAULT 'recebido',
                evt_tx_erro VARCHAR(255) NULL,
                evt_dt_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_event (evt_tx_provider, evt_tx_event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Colunas adicionadas em versões posteriores (idempotente).
            wa_garantir_coluna($conn, 'wa_config', 'cfg_tx_test_number', "VARCHAR(30) NULL");
            wa_garantir_coluna($conn, 'wa_config', 'cfg_nb_retencao_dias', "INT NOT NULL DEFAULT 0");
            wa_garantir_coluna($conn, 'wa_config', 'cfg_tx_call_status', "ENUM('ativo','inativo') NOT NULL DEFAULT 'inativo'");
            // Mantém o default do banco alinhado ao "0 = nunca apagar".
            @mysqli_query($conn, "ALTER TABLE wa_config ALTER COLUMN cfg_nb_retencao_dias SET DEFAULT 0");
            wa_garantir_coluna($conn, 'wa_contato', 'cont_nb_empresa', "INT NULL");
            wa_garantir_coluna($conn, 'wa_contato', 'cont_tx_setor', "VARCHAR(150) NULL");
            wa_garantir_coluna($conn, 'wa_contato', 'cont_tx_cargo', "VARCHAR(150) NULL");
            wa_garantir_coluna($conn, 'wa_contato', 'cont_dt_dataCadastro', "DATETIME DEFAULT CURRENT_TIMESTAMP");
            wa_garantir_coluna($conn, 'wa_mensagem', 'msg_tx_media_id', "VARCHAR(120) NULL");
            wa_garantir_coluna($conn, 'wa_mensagem', 'msg_tx_mime', "VARCHAR(100) NULL");
            wa_garantir_coluna($conn, 'wa_mensagem', 'msg_tx_arquivo', "VARCHAR(300) NULL");
            wa_garantir_coluna($conn, 'wa_mensagem', 'msg_dt_atualizacao', "DATETIME NULL");
            wa_garantir_coluna($conn, 'wa_lote_destinatario', 'ld_tx_telefone', "VARCHAR(30) NULL");
            wa_garantir_coluna($conn, 'wa_evento', 'evt_tx_erro', "VARCHAR(255) NULL");

            // Configuração inicial (uma linha).
            @mysqli_query($conn, "INSERT IGNORE INTO wa_config (cfg_nb_id, cfg_tx_provider, cfg_tx_status) VALUES (1, 'evolution', 'inativo')");

            wa_meta_gravar($conn, 'schema_version', (string)$versaoAtual);
            wa_migrar_menu($conn);
        } finally {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        }
    }
}
