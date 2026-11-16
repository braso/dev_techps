<?php
/**
 * Conversas e mensagens do WhatsApp.
 */

if (!function_exists('wa_conversa_garantir')) {
    function wa_conversa_garantir(int $contatoId): int {
        if ($contatoId <= 0) {
            return 0;
        }
        $rs = query("SELECT conv_nb_id FROM wa_conversa WHERE conv_nb_contato = ? LIMIT 1", "i", [$contatoId]);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        if ($row) {
            return (int)$row['conv_nb_id'];
        }
        $ok = wa_exec(
            "INSERT INTO wa_conversa (conv_nb_contato, conv_tx_status, conv_dt_ultima_atividade) VALUES (?, 'aberta', NOW())",
            "i",
            [$contatoId]
        );
        if (!$ok) {
            // Pode ter sido criada por outra requisição concorrente.
            $rs = query("SELECT conv_nb_id FROM wa_conversa WHERE conv_nb_contato = ? LIMIT 1", "i", [$contatoId]);
            $row = $rs ? mysqli_fetch_assoc($rs) : null;
            return $row ? (int)$row['conv_nb_id'] : 0;
        }
        return (int)mysqli_insert_id($GLOBALS['conn']);
    }
}

if (!function_exists('wa_conversa_por_id')) {
    function wa_conversa_por_id(int $id): ?array {
        if ($id <= 0) {
            return null;
        }
        $rs = query(
            "SELECT cv.*, c.*, empr.empr_tx_nome AS cont_tx_empresa_nome
             FROM wa_conversa cv
             JOIN wa_contato c ON c.cont_nb_id = cv.conv_nb_contato
             LEFT JOIN empresa empr ON empr.empr_nb_id = c.cont_nb_empresa
             WHERE cv.conv_nb_id = ? LIMIT 1",
            "i",
            [$id]
        );
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        return $row ?: null;
    }
}

if (!function_exists('wa_conversa_por_contato')) {
    function wa_conversa_por_contato(int $contatoId): ?array {
        if ($contatoId <= 0) {
            return null;
        }
        $rs = query(
            "SELECT cv.*, c.*, empr.empr_tx_nome AS cont_tx_empresa_nome
             FROM wa_conversa cv
             JOIN wa_contato c ON c.cont_nb_id = cv.conv_nb_contato
             LEFT JOIN empresa empr ON empr.empr_nb_id = c.cont_nb_empresa
             WHERE cv.conv_nb_contato = ? LIMIT 1",
            "i",
            [$contatoId]
        );
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        return $row ?: null;
    }
}

if (!function_exists('wa_conversa_listar')) {
    function wa_conversa_listar(array $filtros = []): array {
        $where = ["1=1"];
        $types = '';
        $vals = [];

        $busca = trim(strval($filtros['busca'] ?? ''));
        if ($busca !== '') {
            $like = '%' . $busca . '%';
            $where[] = "(c.cont_tx_nome LIKE ? OR c.cont_tx_telefone LIKE ? OR c.cont_tx_e164 LIKE ?)";
            $types .= 'sss';
            array_push($vals, $like, $like, $like);
        }
        $filtro = strval($filtros['filtro'] ?? 'todas');
        if ($filtro === 'nao_lidas') {
            $where[] = 'cv.conv_nb_nao_lidas > 0';
        } elseif ($filtro === 'minhas') {
            $where[] = 'cv.conv_nb_responsavel = ?';
            $types .= 'i';
            $vals[] = (int)($filtros['usuario'] ?? 0);
        }
        $origem = strval($filtros['origem'] ?? 'todas');
        if ($origem === 'empresa') {
            $where[] = "(c.cont_nb_entidade IS NOT NULL OR c.cont_nb_user IS NOT NULL OR c.cont_tx_origem IN ('entidade','user'))";
        }

        $sql = "SELECT cv.conv_nb_id, cv.conv_nb_contato, cv.conv_nb_responsavel, cv.conv_tx_status,
                       cv.conv_tx_ultima_mensagem, cv.conv_dt_ultima_atividade, cv.conv_nb_nao_lidas,
                       c.cont_tx_nome, c.cont_tx_telefone, c.cont_tx_e164, c.cont_tx_setor, c.cont_tx_cargo,
                       (SELECT m.msg_tx_conteudo FROM wa_mensagem m WHERE m.msg_nb_conversa = cv.conv_nb_id ORDER BY m.msg_nb_id DESC LIMIT 1) AS ultima_msg,
                       (SELECT m.msg_tx_direcao FROM wa_mensagem m WHERE m.msg_nb_conversa = cv.conv_nb_id ORDER BY m.msg_nb_id DESC LIMIT 1) AS ultima_direcao,
                       (SELECT m.msg_dt_criacao FROM wa_mensagem m WHERE m.msg_nb_conversa = cv.conv_nb_id ORDER BY m.msg_nb_id DESC LIMIT 1) AS ultima_data
                FROM wa_conversa cv
                JOIN wa_contato c ON c.cont_nb_id = cv.conv_nb_contato
                WHERE " . implode(' AND ', $where) . "
                ORDER BY cv.conv_dt_ultima_atividade DESC, cv.conv_nb_id DESC
                LIMIT 200";
        $rs = query($sql, $types, $vals);
        $out = [];
        while ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $out[] = $row;
        }
        return $out;
    }
}

if (!function_exists('wa_mensagem_registrar')) {
    function wa_mensagem_registrar(array $dados): int {
        $conversaId = (int)($dados['msg_nb_conversa'] ?? 0);
        if ($conversaId <= 0) {
            return 0;
        }
        $direcao = ($dados['msg_tx_direcao'] ?? 'out') === 'in' ? 'in' : 'out';
        $tipo = strval($dados['msg_tx_tipo'] ?? 'texto');
        if (!in_array($tipo, ['texto', 'imagem', 'audio', 'documento', 'video', 'template', 'sistema'], true)) {
            $tipo = 'texto';
        }
        $conteudo = strval($dados['msg_tx_conteudo'] ?? '');
        $status = strval($dados['msg_tx_status'] ?? ($direcao === 'in' ? 'recebido' : 'pendente'));
        $providerId = trim(strval($dados['msg_tx_provider_id'] ?? ''));
        $mediaId = trim(strval($dados['msg_tx_media_id'] ?? ''));
        $mime = trim(strval($dados['msg_tx_mime'] ?? ''));
        $arquivo = trim(strval($dados['msg_tx_arquivo'] ?? ''));
        $erro = trim(strval($dados['msg_tx_erro'] ?? ''));
        $userId = (int)($dados['msg_nb_user'] ?? 0);

        $ok = wa_exec(
            "INSERT INTO wa_mensagem
                (msg_nb_conversa, msg_tx_direcao, msg_tx_tipo, msg_tx_conteudo, msg_tx_status,
                 msg_tx_provider_id, msg_tx_media_id, msg_tx_mime, msg_tx_arquivo, msg_tx_erro, msg_nb_user, msg_dt_criacao)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            "isssssssssi",
            [
                $conversaId,
                $direcao,
                $tipo,
                $conteudo,
                $status,
                $providerId !== '' ? $providerId : null,
                $mediaId !== '' ? $mediaId : null,
                $mime !== '' ? $mime : null,
                $arquivo !== '' ? $arquivo : null,
                $erro !== '' ? $erro : null,
                $userId > 0 ? $userId : null,
            ]
        );
        if (!$ok) {
            return 0;
        }
        $msgId = (int)mysqli_insert_id($GLOBALS['conn']);

        $preview = trim(preg_replace('/\s+/', ' ', $conteudo));
        if ($preview === '') {
            $preview = match ($tipo) {
                'imagem' => '[imagem]',
                'audio' => '[áudio]',
                'video' => '[vídeo]',
                'documento' => '[documento]',
                default => '[mensagem]',
            };
        }
        $preview = mb_substr($preview, 0, 250);

        if ($direcao === 'in') {
            wa_exec(
                "UPDATE wa_conversa SET conv_tx_ultima_mensagem = ?, conv_dt_ultima_atividade = NOW(), conv_nb_nao_lidas = conv_nb_nao_lidas + 1 WHERE conv_nb_id = ?",
                "si",
                [$preview, $conversaId]
            );
        } else {
            if ($userId > 0) {
                wa_exec(
                    "UPDATE wa_conversa SET conv_tx_ultima_mensagem = ?, conv_dt_ultima_atividade = NOW(), conv_nb_responsavel = ? WHERE conv_nb_id = ?",
                    "sii",
                    [$preview, $userId, $conversaId]
                );
            } else {
                // Mensagem enviada pelo celular: atualiza a conversa sem
                // apagar o responsável que já estava atribuído no sistema.
                wa_exec(
                    "UPDATE wa_conversa SET conv_tx_ultima_mensagem = ?, conv_dt_ultima_atividade = NOW() WHERE conv_nb_id = ?",
                    "si",
                    [$preview, $conversaId]
                );
            }
        }

        return $msgId;
    }
}

if (!function_exists('wa_conversa_contar')) {
    /**
     * Conta conversas. Com $apenasNaoLidas = true, conta somente as que têm
     * mensagens não lidas (usado nos badges de "Todas" e "Da empresa").
     */
    function wa_conversa_contar(string $origem = 'todas', bool $apenasNaoLidas = false): int {
        $filtros = [];
        if ($origem === 'empresa') {
            $filtros[] = "(c.cont_nb_entidade IS NOT NULL OR c.cont_nb_user IS NOT NULL OR c.cont_tx_origem IN ('entidade','user'))";
        }
        if ($apenasNaoLidas) {
            $filtros[] = 'cv.conv_nb_nao_lidas > 0';
        }
        $where = $filtros ? ' WHERE ' . implode(' AND ', $filtros) : '';
        $rs = query(
            "SELECT COUNT(*) AS total FROM wa_conversa cv JOIN wa_contato c ON c.cont_nb_id = cv.conv_nb_contato{$where}"
        );
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        return $row ? (int)$row['total'] : 0;
    }
}

if (!function_exists('wa_mensagens_listar')) {
    function wa_mensagens_listar(int $conversaId, int $aposId = 0, int $limite = 200): array {
        if ($conversaId <= 0) {
            return [];
        }
        $limite = max(1, min(500, $limite));
        if ($aposId > 0) {
            $rs = query(
                "SELECT m.*, u.user_tx_nome AS usuario_nome,
                        (SELECT a.anx_nb_id FROM wa_anexo a WHERE a.anx_nb_msg = m.msg_nb_id ORDER BY a.anx_nb_id DESC LIMIT 1) AS anexo_id,
                        (SELECT a.anx_tx_nome FROM wa_anexo a WHERE a.anx_nb_msg = m.msg_nb_id ORDER BY a.anx_nb_id DESC LIMIT 1) AS anexo_nome
                 FROM wa_mensagem m
                 LEFT JOIN user u ON u.user_nb_id = m.msg_nb_user
                 WHERE m.msg_nb_conversa = ? AND m.msg_nb_id > ?
                 ORDER BY m.msg_nb_id ASC LIMIT ?",
                "iii",
                [$conversaId, $aposId, $limite]
            );
        } else {
            $rs = query(
                "SELECT * FROM (
                    SELECT m.*, u.user_tx_nome AS usuario_nome,
                           (SELECT a.anx_nb_id FROM wa_anexo a WHERE a.anx_nb_msg = m.msg_nb_id ORDER BY a.anx_nb_id DESC LIMIT 1) AS anexo_id,
                           (SELECT a.anx_tx_nome FROM wa_anexo a WHERE a.anx_nb_msg = m.msg_nb_id ORDER BY a.anx_nb_id DESC LIMIT 1) AS anexo_nome
                    FROM wa_mensagem m
                    LEFT JOIN user u ON u.user_nb_id = m.msg_nb_user
                    WHERE m.msg_nb_conversa = ?
                    ORDER BY m.msg_nb_id DESC LIMIT ?
                 ) ultimas ORDER BY msg_nb_id ASC",
                "ii",
                [$conversaId, $limite]
            );
        }
        $out = [];
        while ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $out[] = $row;
        }
        return $out;
    }
}

if (!function_exists('wa_personalizar_mensagem')) {
    function wa_personalizar_mensagem(string $texto, array $contato): string {
        $nome = trim(strval($contato['cont_tx_nome'] ?? ''));
        $partes = preg_split('/\s+/', $nome);
        $primeiro = $partes && isset($partes[0]) ? $partes[0] : $nome;
        $valores = [
            $nome,
            $primeiro,
            strval($contato['cont_tx_setor'] ?? ''),
            strval($contato['cont_tx_cargo'] ?? ''),
            strval($contato['cont_tx_empresa_nome'] ?? ''),
            wa_formatar_telefone($contato['cont_tx_e164'] ?? $contato['cont_tx_telefone'] ?? ''),
        ];
        // Aceita tanto {nome} quanto {{nome}}.
        $texto = str_replace(
            ['{{nome}}', '{{primeiro_nome}}', '{{setor}}', '{{cargo}}', '{{empresa}}', '{{telefone}}'],
            $valores,
            $texto
        );
        return str_replace(
            ['{nome}', '{primeiro_nome}', '{setor}', '{cargo}', '{empresa}', '{telefone}'],
            $valores,
            $texto
        );
    }
}

if (!function_exists('wa_enviar_texto')) {
    function wa_enviar_texto(int $conversaId, string $texto, int $usuarioId, bool $enviarAgora = true): array {
        $texto = trim($texto);
        if ($conversaId <= 0 || $texto === '') {
            return ['success' => false, 'message' => 'Conversa ou mensagem inválida.'];
        }
        if (mb_strlen($texto) > 4096) {
            return ['success' => false, 'message' => 'A mensagem excede 4096 caracteres.'];
        }
        if (!wa_config_ativa()) {
            return ['success' => false, 'message' => 'Configure e ative o provedor antes de enviar mensagens.'];
        }

        $conv = wa_conversa_por_id($conversaId);
        if (!$conv) {
            return ['success' => false, 'message' => 'Conversa não encontrada.'];
        }
        if (trim(strval($conv['cont_tx_e164'] ?? '')) === '') {
            return ['success' => false, 'message' => 'O contato não possui um número de WhatsApp válido.'];
        }
        // Mensagens prontas podem conter as mesmas variáveis do lote.
        $texto = wa_personalizar_mensagem($texto, $conv);
        if (mb_strlen($texto) > 4096) {
            return ['success' => false, 'message' => 'A mensagem personalizada excede 4096 caracteres.'];
        }

        $msgId = wa_mensagem_registrar([
            'msg_nb_conversa' => $conversaId,
            'msg_tx_direcao' => 'out',
            'msg_tx_tipo' => 'texto',
            'msg_tx_conteudo' => $texto,
            'msg_tx_status' => 'pendente',
            'msg_nb_user' => $usuarioId,
        ]);
        if ($msgId <= 0) {
            return ['success' => false, 'message' => 'Não foi possível registrar a mensagem.'];
        }

        $filaId = wa_enfileirar_mensagem($msgId);
        $resultado = ['success' => true, 'message_id' => $msgId, 'status' => 'pendente', 'message' => 'Mensagem registrada.'];
        if ($enviarAgora && $filaId > 0) {
            $envio = wa_processar_fila_por_id($filaId);
            $rs = query("SELECT msg_tx_status, msg_tx_erro, msg_tx_provider_id FROM wa_mensagem WHERE msg_nb_id = ? LIMIT 1", "i", [$msgId]);
            $msg = $rs ? mysqli_fetch_assoc($rs) : null;
            if ($msg) {
                $resultado['status'] = strval($msg['msg_tx_status'] ?? 'pendente');
                $resultado['provider_id'] = strval($msg['msg_tx_provider_id'] ?? '');
                if (!empty($envio['message'])) {
                    $resultado['message'] = strval($envio['message']);
                }
                if ($resultado['status'] === 'falhou') {
                    $resultado['success'] = false;
                    $resultado['message'] = strval($msg['msg_tx_erro'] ?? 'Falha no envio.');
                }
            }
        }
        return $resultado;
    }
}

if (!function_exists('wa_enviar_midia')) {
    function wa_enviar_midia(int $conversaId, array $arquivo, string $legenda, int $usuarioId, bool $enviarAgora = true): array {
        if ($conversaId <= 0) {
            return ['success' => false, 'message' => 'Conversa inválida.'];
        }
        $legenda = trim($legenda);
        if (mb_strlen($legenda) > 1024) {
            return ['success' => false, 'message' => 'A legenda excede 1024 caracteres.'];
        }
        if (!wa_config_ativa()) {
            return ['success' => false, 'message' => 'Configure e ative o provedor antes de enviar mídias.'];
        }

        $conv = wa_conversa_por_id($conversaId);
        if (!$conv) {
            return ['success' => false, 'message' => 'Conversa não encontrada.'];
        }
        if (trim(strval($conv['cont_tx_e164'] ?? '')) === '') {
            return ['success' => false, 'message' => 'O contato não possui um número de WhatsApp válido.'];
        }

        $erro = '';
        $midia = wa_midia_salvar_upload($arquivo, $erro);
        if (!$midia) {
            return ['success' => false, 'message' => $erro !== '' ? $erro : 'Arquivo inválido.'];
        }
        if ($midia['tipo'] === 'audio') {
            // O WhatsApp não aceita legenda em áudio; evita mostrar um texto
            // que não será enviado ao destinatário.
            $legenda = '';
        }

        $msgId = wa_mensagem_registrar([
            'msg_nb_conversa' => $conversaId,
            'msg_tx_direcao' => 'out',
            'msg_tx_tipo' => strval($midia['tipo']),
            'msg_tx_conteudo' => $legenda,
            'msg_tx_status' => 'pendente',
            'msg_tx_mime' => strval($midia['mime']),
            'msg_tx_arquivo' => strval($midia['arquivo']),
            'msg_nb_user' => $usuarioId,
        ]);
        if ($msgId <= 0) {
            @unlink(wa_midia_pasta() . '/' . strval($midia['arquivo']));
            return ['success' => false, 'message' => 'Não foi possível registrar a mídia.'];
        }
        if (!wa_midia_anexar_mensagem($msgId, $midia, 'out')) {
            wa_log('midia_anexo_falhou', 'Não foi possível registrar o anexo da mensagem.', ['mensagem' => $msgId], 'warning');
        }

        $filaId = wa_enfileirar_mensagem($msgId);
        $resultado = [
            'success' => true,
            'message_id' => $msgId,
            'status' => 'pendente',
            'tipo' => strval($midia['tipo']),
            'message' => 'Mídia registrada.',
        ];
        if ($enviarAgora && $filaId > 0) {
            $envio = wa_processar_fila_por_id($filaId);
            $rs = query("SELECT msg_tx_status, msg_tx_erro, msg_tx_provider_id FROM wa_mensagem WHERE msg_nb_id = ? LIMIT 1", "i", [$msgId]);
            $msg = $rs ? mysqli_fetch_assoc($rs) : null;
            if ($msg) {
                $resultado['status'] = strval($msg['msg_tx_status'] ?? 'pendente');
                $resultado['provider_id'] = strval($msg['msg_tx_provider_id'] ?? '');
                if (!empty($envio['message'])) {
                    $resultado['message'] = strval($envio['message']);
                }
                if ($resultado['status'] === 'falhou') {
                    $resultado['success'] = false;
                    $resultado['message'] = strval($msg['msg_tx_erro'] ?? 'Falha no envio da mídia.');
                }
            }
        }
        return $resultado;
    }
}

if (!function_exists('wa_registrar_mensagem_webhook')) {
    function wa_registrar_mensagem_webhook(array $evento, ?ProviderInterface $provider = null): ?int {
        $direcao = strval($evento['direcao'] ?? 'in') === 'out' ? 'out' : 'in';
        $from = strval($evento['from'] ?? '');
        $e164 = wa_normalizar_telefone($from);
        if ($e164 === null) {
            wa_log('webhook_numero_invalido', 'Número recebido inválido.', ['from' => $from], 'warning');
            return null;
        }
        // Em mensagens enviadas pelo celular, o pushName costuma ser o nome
        // do próprio dono da conta; não usar isso como nome do contato.
        $nomeContato = $direcao === 'out' ? '' : strval($evento['nome'] ?? '');
        $contato = wa_contato_resolver_por_telefone($e164, $nomeContato);
        if (!$contato) {
            return null;
        }
        $conversaId = wa_conversa_garantir((int)$contato['cont_nb_id']);
        if ($conversaId <= 0) {
            return null;
        }

        $providerId = trim(strval($evento['provider_id'] ?? ''));
        if ($providerId !== '') {
            $rsExistente = query(
                "SELECT msg_nb_id FROM wa_mensagem WHERE msg_tx_provider_id = ? ORDER BY msg_nb_id DESC LIMIT 1",
                "s",
                [$providerId]
            );
            $existente = $rsExistente ? mysqli_fetch_assoc($rsExistente) : null;
            if ($existente) {
                // Eco de uma mensagem que o próprio sistema já registrou.
                return (int)$existente['msg_nb_id'];
            }
        }

        $tipo = strval($evento['tipo_msg'] ?? 'texto');
        $texto = strval($evento['texto'] ?? '');
        if ($texto === '') {
            $texto = match ($tipo) {
                'imagem' => $direcao === 'out' ? '[imagem enviada]' : '[imagem recebida]',
                'audio' => $direcao === 'out' ? '[áudio enviado]' : '[áudio recebido]',
                'video' => $direcao === 'out' ? '[vídeo enviado]' : '[vídeo recebido]',
                'documento' => $direcao === 'out' ? '[documento enviado]' : '[documento recebido]',
                default => $direcao === 'out' ? '[mensagem enviada]' : '[mensagem recebida]',
            };
        }

        // Quando a Evolution não devolve um ID, evita duplicar o eco de uma
        // mensagem de saída registrada há poucos minutos.
        if ($direcao === 'out' && $providerId === '') {
            $rsRecente = query(
                "SELECT msg_nb_id FROM wa_mensagem
                 WHERE msg_nb_conversa = ? AND msg_tx_direcao = 'out' AND msg_tx_conteudo = ?
                   AND msg_dt_criacao >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                 ORDER BY msg_nb_id DESC LIMIT 1",
                "is",
                [$conversaId, $texto]
            );
            $recente = $rsRecente ? mysqli_fetch_assoc($rsRecente) : null;
            if ($recente) {
                return (int)$recente['msg_nb_id'];
            }
        }

        $msgId = wa_mensagem_registrar([
            'msg_nb_conversa' => $conversaId,
            'msg_tx_direcao' => $direcao,
            'msg_tx_tipo' => $tipo,
            'msg_tx_conteudo' => $texto,
            'msg_tx_status' => $direcao === 'out' ? 'enviado' : 'recebido',
            'msg_tx_provider_id' => $providerId,
            'msg_tx_media_id' => strval($evento['media_id'] ?? ''),
            'msg_tx_mime' => strval($evento['mime'] ?? ''),
        ]);
        if ($msgId <= 0) {
            return null;
        }

        // Mídias recebidas (ou enviadas pelo celular) são baixadas e salvas
        // imediatamente, antes que a URL temporária do provedor expire.
        if ($provider !== null && in_array($tipo, ['imagem', 'audio', 'video', 'documento'], true)) {
            try {
                wa_midia_baixar_evento($evento, $provider, $msgId, $direcao);
            } catch (Throwable $e) {
                wa_log('midia_webhook_erro', $e->getMessage(), ['mensagem' => $msgId], 'warning');
            }
        }
        return $msgId;
    }
}

if (!function_exists('wa_marcar_conversa_lida')) {
    function wa_marcar_conversa_lida(int $conversaId): void {
        if ($conversaId <= 0) {
            return;
        }
        wa_exec("UPDATE wa_conversa SET conv_nb_nao_lidas = 0 WHERE conv_nb_id = ?", "i", [$conversaId]);

        // Marca no provedor a última mensagem recebida, quando possível.
        try {
            $rs = query(
                "SELECT msg_tx_provider_id FROM wa_mensagem
                 WHERE msg_nb_conversa = ? AND msg_tx_direcao = 'in' AND msg_tx_provider_id IS NOT NULL AND msg_tx_provider_id <> ''
                 ORDER BY msg_nb_id DESC LIMIT 1",
                "i",
                [$conversaId]
            );
            $row = $rs ? mysqli_fetch_assoc($rs) : null;
            if ($row && wa_config_ativa()) {
                [$provider] = wa_config_provider();
                $provider->markRead(strval($row['msg_tx_provider_id']));
            }
        } catch (Throwable $e) {
            wa_log('marcar_lida_provider', $e->getMessage(), [], 'warning');
        }
    }
}
