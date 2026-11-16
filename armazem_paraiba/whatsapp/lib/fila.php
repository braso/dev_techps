<?php
/**
 * Fila de envio e atualização de status do WhatsApp.
 */

if (!function_exists('wa_fila_rank_status')) {
    function wa_fila_rank_status(string $status): int {
        return match ($status) {
            'pendente', 'recebido' => 0,
            'enviado' => 1,
            'entregue' => 2,
            'lido' => 3,
            'falhou' => 9,
            default => 0,
        };
    }
}

if (!function_exists('wa_enfileirar_mensagem')) {
    function wa_enfileirar_mensagem(int $mensagemId, ?string $agendada = null): int {
        if ($mensagemId <= 0) {
            return 0;
        }
        $agendada = $agendada ?: date('Y-m-d H:i:s');
        $ok = wa_exec(
            "INSERT INTO wa_fila (fil_tx_tipo, fil_nb_referencia, fil_tx_status, fil_dt_agendada)
             VALUES ('mensagem', ?, 'pendente', ?)",
            "is",
            [$mensagemId, $agendada]
        );
        return $ok ? (int)mysqli_insert_id($GLOBALS['conn']) : 0;
    }
}

if (!function_exists('wa_lote_recalcular')) {
    function wa_lote_recalcular(int $loteId): void {
        if ($loteId <= 0) {
            return;
        }
        $rs = query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN ld_tx_status IN ('enviado','entregue','lido') THEN 1 ELSE 0 END) AS enviados,
                SUM(CASE WHEN ld_tx_status = 'falhou' THEN 1 ELSE 0 END) AS falhas,
                SUM(CASE WHEN ld_tx_status = 'pendente' THEN 1 ELSE 0 END) AS pendentes
             FROM wa_lote_destinatario WHERE ld_nb_lote = ?",
            "i",
            [$loteId]
        );
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        if (!$row) {
            return;
        }
        $total = (int)$row['total'];
        $enviados = (int)$row['enviados'];
        $falhas = (int)$row['falhas'];
        $pendentes = (int)$row['pendentes'];
        $status = $pendentes > 0 ? 'enviando' : ($total > 0 ? 'concluido' : 'rascunho');
        $fim = $pendentes > 0 ? null : date('Y-m-d H:i:s');
        wa_exec(
            "UPDATE wa_lote SET lote_nb_total = ?, lote_nb_enviados = ?, lote_nb_falhas = ?, lote_tx_status = ?, lote_dt_fim = ? WHERE lote_nb_id = ?",
            "iiissi",
            [$total, $enviados, $falhas, $status, $fim, $loteId]
        );
    }
}

if (!function_exists('wa_atualizar_lote_por_mensagem')) {
    function wa_atualizar_lote_por_mensagem(int $mensagemId, string $status, string $erro = ''): void {
        if ($mensagemId <= 0) {
            return;
        }
        $rs = query("SELECT ld_nb_id, ld_nb_lote FROM wa_lote_destinatario WHERE ld_nb_mensagem = ? LIMIT 1", "i", [$mensagemId]);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        if (!$row) {
            return;
        }
        $statusLote = in_array($status, ['enviado', 'entregue', 'lido', 'falhou'], true) ? $status : 'pendente';
        $providerId = '';
        $rsMsg = query("SELECT msg_tx_provider_id FROM wa_mensagem WHERE msg_nb_id = ? LIMIT 1", "i", [$mensagemId]);
        $msg = $rsMsg ? mysqli_fetch_assoc($rsMsg) : null;
        if ($msg) {
            $providerId = strval($msg['msg_tx_provider_id'] ?? '');
        }
        wa_exec(
            "UPDATE wa_lote_destinatario SET ld_tx_status = ?, ld_tx_erro = ?, ld_tx_provider_id = ?, ld_dt_atualizacao = NOW() WHERE ld_nb_id = ?",
            "sssi",
            [$statusLote, $erro !== '' ? $erro : null, $providerId !== '' ? $providerId : null, (int)$row['ld_nb_id']]
        );
        wa_lote_recalcular((int)$row['ld_nb_lote']);
    }
}

if (!function_exists('wa_atualizar_status_provider')) {
    function wa_atualizar_status_provider(string $providerId, string $status): void {
        $providerId = trim($providerId);
        if ($providerId === '') {
            return;
        }
        $novo = wa_status_provider($status);
        $rs = query("SELECT msg_nb_id, msg_tx_status FROM wa_mensagem WHERE msg_tx_provider_id = ? ORDER BY msg_nb_id DESC LIMIT 1", "s", [$providerId]);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        if (!$row) {
            return;
        }
        $atual = strval($row['msg_tx_status'] ?? 'pendente');
        if ($novo !== 'falhou' && wa_fila_rank_status($novo) < wa_fila_rank_status($atual)) {
            return;
        }
        wa_exec(
            "UPDATE wa_mensagem SET msg_tx_status = ?, msg_dt_atualizacao = NOW() WHERE msg_nb_id = ?",
            "si",
            [$novo, (int)$row['msg_nb_id']]
        );
        wa_atualizar_lote_por_mensagem((int)$row['msg_nb_id'], $novo);
    }
}

if (!function_exists('wa_processar_item_fila')) {
    function wa_processar_item_fila(array $fila): array {
        global $conn;

        $mensagemId = (int)($fila['fil_nb_referencia'] ?? 0);
        $filaId = (int)($fila['fil_nb_id'] ?? 0);
        $tentativas = (int)($fila['fil_nb_tentativas'] ?? 0);

        $rs = query("SELECT * FROM wa_mensagem WHERE msg_nb_id = ? LIMIT 1", "i", [$mensagemId]);
        $msg = $rs ? mysqli_fetch_assoc($rs) : null;
        if (!$msg) {
            wa_exec("UPDATE wa_fila SET fil_tx_status = 'falhou', fil_tx_erro = 'Mensagem não encontrada.', fil_dt_processada = NOW() WHERE fil_nb_id = ?", "i", [$filaId]);
            return ['success' => false, 'message' => 'Mensagem não encontrada.'];
        }
        if (strval($msg['msg_tx_direcao'] ?? '') !== 'out') {
            wa_exec("UPDATE wa_fila SET fil_tx_status = 'falhou', fil_tx_erro = 'Mensagem de entrada não deve ser enviada.', fil_dt_processada = NOW() WHERE fil_nb_id = ?", "i", [$filaId]);
            return ['success' => false, 'message' => 'Mensagem de entrada.'];
        }

        [$provider, $cfg] = wa_config_provider();
        if (strval($cfg['cfg_tx_status'] ?? '') !== 'ativo') {
            // Não conta tentativa: apenas aguarda a configuração ser ativada.
            wa_exec("UPDATE wa_fila SET fil_tx_status = 'pendente', fil_dt_agendada = DATE_ADD(NOW(), INTERVAL 10 MINUTE), fil_tx_erro = 'Provedor inativo.' WHERE fil_nb_id = ?", "i", [$filaId]);
            return ['success' => false, 'message' => 'Provedor inativo.', 'retry' => true];
        }

        $rsConv = query(
            "SELECT cv.conv_nb_id, c.*
             FROM wa_conversa cv JOIN wa_contato c ON c.cont_nb_id = cv.conv_nb_contato
             WHERE cv.conv_nb_id = ? LIMIT 1",
            "i",
            [(int)$msg['msg_nb_conversa']]
        );
        $contato = $rsConv ? mysqli_fetch_assoc($rsConv) : null;
        if (!$contato || trim(strval($contato['cont_tx_e164'] ?? '')) === '') {
            $erro = 'Contato sem número válido.';
            wa_exec("UPDATE wa_mensagem SET msg_tx_status = 'falhou', msg_tx_erro = ?, msg_dt_atualizacao = NOW() WHERE msg_nb_id = ?", "si", [$erro, $mensagemId]);
            wa_exec("UPDATE wa_fila SET fil_tx_status = 'falhou', fil_tx_erro = ?, fil_dt_processada = NOW() WHERE fil_nb_id = ?", "si", [$erro, $filaId]);
            wa_atualizar_lote_por_mensagem($mensagemId, 'falhou', $erro);
            return ['success' => false, 'message' => $erro];
        }

        $telefone = strval($contato['cont_tx_e164']);
        $tipo = strval($msg['msg_tx_tipo'] ?? 'texto');
        $conteudo = strval($msg['msg_tx_conteudo'] ?? '');

        if ($tipo === 'texto') {
            $envio = $provider->sendText($telefone, $conteudo);
        } elseif ($tipo === 'template') {
            $partes = explode('|', $conteudo);
            $envio = $provider->sendTemplate($telefone, $partes[0] ?? '', $partes[1] ?? 'pt_BR', array_slice($partes, 2));
        } else {
            $arquivo = strval($msg['msg_tx_arquivo'] ?? '');
            $caminho = $arquivo !== '' ? realpath(wa_midia_pasta() . '/' . basename($arquivo)) : false;
            if (!$caminho || !is_file($caminho)) {
                $envio = ['success' => false, 'error' => 'Arquivo de mídia não encontrado.'];
            } else {
                $mime = strval($msg['msg_tx_mime'] ?? 'application/octet-stream');
                $nomeOriginal = '';
                $rsAnexo = query("SELECT anx_tx_nome FROM wa_anexo WHERE anx_nb_msg = ? ORDER BY anx_nb_id DESC LIMIT 1", "i", [$mensagemId]);
                $anexo = $rsAnexo ? mysqli_fetch_assoc($rsAnexo) : null;
                if ($anexo) {
                    $nomeOriginal = strval($anexo['anx_tx_nome'] ?? '');
                }
                $envio = $provider->sendMedia($telefone, $caminho, $mime, $conteudo, $nomeOriginal);
            }
        }

        if (!empty($envio['success'])) {
            $providerId = strval($envio['provider_id'] ?? '');
            wa_exec(
                "UPDATE wa_mensagem SET msg_tx_status = 'enviado', msg_tx_provider_id = ?, msg_tx_erro = NULL, msg_dt_atualizacao = NOW() WHERE msg_nb_id = ?",
                "si",
                [$providerId !== '' ? $providerId : null, $mensagemId]
            );
            wa_exec("UPDATE wa_fila SET fil_tx_status = 'enviado', fil_tx_erro = NULL, fil_dt_processada = NOW() WHERE fil_nb_id = ?", "i", [$filaId]);
            wa_atualizar_lote_por_mensagem($mensagemId, 'enviado');
            return ['success' => true, 'message' => 'Mensagem enviada.', 'provider_id' => $providerId];
        }

        $erro = substr(strval($envio['error'] ?? 'Falha no envio.'), 0, 250);
        $tentativas++;
        if ($tentativas >= 4) {
            wa_exec("UPDATE wa_mensagem SET msg_tx_status = 'falhou', msg_tx_erro = ?, msg_dt_atualizacao = NOW() WHERE msg_nb_id = ?", "si", [$erro, $mensagemId]);
            wa_exec("UPDATE wa_fila SET fil_tx_status = 'falhou', fil_nb_tentativas = ?, fil_tx_erro = ?, fil_dt_processada = NOW() WHERE fil_nb_id = ?", "isi", [$tentativas, $erro, $filaId]);
            wa_atualizar_lote_por_mensagem($mensagemId, 'falhou', $erro);
            wa_log('envio_falhou', $erro, ['mensagem' => $mensagemId, 'tentativas' => $tentativas], 'error');
            return ['success' => false, 'message' => $erro];
        }

        $minutos = $tentativas === 1 ? 1 : ($tentativas === 2 ? 5 : 15);
        wa_exec(
            "UPDATE wa_fila SET fil_tx_status = 'pendente', fil_nb_tentativas = ?, fil_tx_erro = ?, fil_dt_agendada = DATE_ADD(NOW(), INTERVAL {$minutos} MINUTE) WHERE fil_nb_id = ?",
            "isi",
            [$tentativas, $erro, $filaId]
        );
        wa_exec("UPDATE wa_mensagem SET msg_tx_status = 'pendente', msg_tx_erro = ?, msg_dt_atualizacao = NOW() WHERE msg_nb_id = ?", "si", [$erro, $mensagemId]);
        wa_log('envio_reagendado', $erro, ['mensagem' => $mensagemId, 'tentativa' => $tentativas], 'warning');
        return ['success' => false, 'message' => $erro, 'retry' => true];
    }
}

if (!function_exists('wa_processar_fila_por_id')) {
    function wa_processar_fila_por_id(int $filaId): array {
        if ($filaId <= 0) {
            return ['success' => false, 'message' => 'Fila inválida.'];
        }
        $rs = query("SELECT * FROM wa_fila WHERE fil_nb_id = ? LIMIT 1", "i", [$filaId]);
        $fila = $rs ? mysqli_fetch_assoc($rs) : null;
        if (!$fila) {
            return ['success' => false, 'message' => 'Item de fila não encontrado.'];
        }
        wa_exec("UPDATE wa_fila SET fil_tx_status = 'processando' WHERE fil_nb_id = ?", "i", [$filaId]);
        $fila['fil_tx_status'] = 'processando';
        return wa_processar_item_fila($fila);
    }
}

if (!function_exists('wa_processar_fila')) {
    function wa_processar_fila(int $limite = 20): array {
        global $conn;
        $limite = max(1, min(100, $limite));
        $resultado = ['processados' => 0, 'enviados' => 0, 'falhas' => 0, 'reagendados' => 0];

        $rs = query(
            "SELECT * FROM wa_fila
             WHERE fil_tx_status = 'pendente' AND fil_dt_agendada <= NOW()
             ORDER BY fil_nb_id ASC LIMIT ?",
            "i",
            [$limite]
        );
        $itens = [];
        while ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $itens[] = $row;
        }

        foreach ($itens as $fila) {
            $filaId = (int)$fila['fil_nb_id'];
            $ok = wa_exec("UPDATE wa_fila SET fil_tx_status = 'processando' WHERE fil_nb_id = ? AND fil_tx_status = 'pendente'", "i", [$filaId]);
            if (!$ok || wa_affected_rows() === 0) {
                continue;
            }
            $resultado['processados']++;
            $retorno = wa_processar_item_fila($fila);
            if (!empty($retorno['success'])) {
                $resultado['enviados']++;
            } elseif (!empty($retorno['retry'])) {
                $resultado['reagendados']++;
            } else {
                $resultado['falhas']++;
            }
            usleep(random_int(1000000, 3000000));
        }

        return $resultado;
    }
}

if (!function_exists('wa_limpar_retencao')) {
    function wa_limpar_retencao(?int $dias = null): int {
        $cfg = wa_config();
        $dias = $dias ?? (int)($cfg['cfg_nb_retencao_dias'] ?? 0);
        if ($dias <= 0) {
            wa_log('retencao_desativada', 'Retenção desativada: 0 dias (nunca apagar).', [], 'info');
            return 0;
        }
        $corte = (new DateTimeImmutable('-' . $dias . ' days'))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $rs = query("SELECT msg_nb_id FROM wa_mensagem WHERE msg_dt_criacao < ? ORDER BY msg_nb_id ASC LIMIT 500", "s", [$corte]);
        $ids = [];
        while ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $ids[] = (int)$row['msg_nb_id'];
        }
        wa_exec("DELETE FROM wa_evento WHERE evt_dt_criacao < ?", "s", [$corte]);
        if (empty($ids)) {
            return 0;
        }

        $in = implode(',', array_map('intval', $ids));
        $rsAnexos = query("SELECT anx_tx_arquivo FROM wa_anexo WHERE anx_nb_msg IN ({$in})");
        while ($rsAnexos && ($a = mysqli_fetch_assoc($rsAnexos))) {
            $arquivo = basename(strval($a['anx_tx_arquivo'] ?? ''));
            if ($arquivo !== '') {
                $caminho = wa_midia_pasta() . '/' . $arquivo;
                if (is_file($caminho)) {
                    @unlink($caminho);
                }
            }
        }

        wa_exec("DELETE FROM wa_anexo WHERE anx_nb_msg IN ({$in})");
        wa_exec("DELETE FROM wa_mensagem WHERE msg_nb_id IN ({$in})");
        wa_log('retencao', 'Mensagens antigas removidas.', ['quantidade' => count($ids), 'dias' => $dias]);
        return count($ids);
    }
}
