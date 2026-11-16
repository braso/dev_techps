<?php

/**
 * Chamadas de voz da Cloud API.
 *
 * O navegador cuida do WebRTC/SDP; aqui ficam o registro, o vínculo com a
 * conversa, o estado da chamada e o processamento dos webhooks.
 */

if (!function_exists('wa_chamada_por_call_id')) {
    function wa_chamada_por_call_id(string $callId): ?array {
        $callId = trim($callId);
        if ($callId === '') {
            return null;
        }
        $rs = query("SELECT * FROM wa_chamada WHERE ch_tx_call_id = ? LIMIT 1", "s", [$callId]);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        return $row ?: null;
    }
}

if (!function_exists('wa_chamada_por_id')) {
    function wa_chamada_por_id(int $id): ?array {
        if ($id <= 0) {
            return null;
        }
        $rs = query("SELECT * FROM wa_chamada WHERE ch_nb_id = ? LIMIT 1", "i", [$id]);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        return $row ?: null;
    }
}

if (!function_exists('wa_chamada_contexto')) {
    function wa_chamada_contexto(string $telefone, string $nome = ''): ?array {
        $e164 = wa_normalizar_telefone($telefone);
        if ($e164 === null) {
            return null;
        }
        $contato = wa_contato_resolver_por_telefone($e164, $nome);
        if (!$contato) {
            return null;
        }
        $conversaId = wa_conversa_garantir((int)$contato['cont_nb_id']);
        if ($conversaId <= 0) {
            return null;
        }
        return ['contato' => $contato, 'conversa_id' => $conversaId];
    }
}

if (!function_exists('wa_chamada_criar_saida')) {
    function wa_chamada_criar_saida(int $conversaId, string $callId, string $sdpOffer, int $usuarioId, string $opaque = ''): int {
        $conv = wa_conversa_por_id($conversaId);
        if (!$conv || trim($callId) === '') {
            return 0;
        }
        $existente = wa_chamada_por_call_id($callId);
        if ($existente) {
            wa_exec(
                "UPDATE wa_chamada SET ch_nb_conversa = ?, ch_nb_contato = ?, ch_tx_sdp_offer = COALESCE(NULLIF(ch_tx_sdp_offer, ''), ?), ch_nb_user = COALESCE(?, ch_nb_user), ch_dt_atualizacao = NOW() WHERE ch_nb_id = ?",
                "iisii",
                [$conversaId, (int)$conv['cont_nb_id'], $sdpOffer, $usuarioId > 0 ? $usuarioId : null, (int)$existente['ch_nb_id']]
            );
            return (int)$existente['ch_nb_id'];
        }
        $ok = wa_exec(
            "INSERT INTO wa_chamada
                (ch_tx_call_id, ch_nb_conversa, ch_nb_contato, ch_tx_direcao, ch_tx_status,
                 ch_tx_sdp_offer, ch_nb_user, ch_tx_opaque, ch_dt_criacao)
             VALUES (?, ?, ?, 'out', 'solicitando', ?, ?, ?, NOW())",
            "siisis",
            [
                trim($callId),
                $conversaId,
                (int)$conv['cont_nb_id'],
                $sdpOffer,
                $usuarioId > 0 ? $usuarioId : null,
                $opaque !== '' ? substr($opaque, 0, 512) : null,
            ]
        );
        return $ok ? (int)mysqli_insert_id($GLOBALS['conn']) : 0;
    }
}

if (!function_exists('wa_chamada_processar_evento')) {
    function wa_chamada_processar_evento(array $evento): ?int {
        $callId = trim(strval($evento['call_id'] ?? ''));
        if ($callId === '') {
            return null;
        }
        $tipoEvento = strval($evento['tipo'] ?? '');
        $row = wa_chamada_por_call_id($callId);

        if ($tipoEvento === 'chamada_status') {
            if (!$row) {
                return null;
            }
            $status = strtolower(strval($evento['status'] ?? ''));
            $mapa = [
                'ringing' => 'tocando',
                'accepted' => 'conectada',
                'rejected' => 'rejeitada',
            ];
            if (isset($mapa[$status])) {
                $novo = $mapa[$status];
                $atualStatus = strval($row['ch_tx_status'] ?? '');
                $statusFinais = ['conectada', 'encerrada', 'rejeitada', 'falhou'];
                if (in_array($atualStatus, $statusFinais, true)) {
                    // Webhooks de chamada não têm ordem garantida; não reabre
                    // uma chamada que já foi conectada ou finalizada.
                    wa_chamada_registrar_evento((int)$row['ch_nb_id'], $tipoEvento, $evento);
                    return (int)$row['ch_nb_id'];
                }
                $campos = "ch_tx_status = ?, ch_dt_atualizacao = NOW()";
                $params = [$novo];
                $types = "s";
                if ($novo === 'conectada') {
                    $campos .= ", ch_dt_inicio = COALESCE(ch_dt_inicio, NOW())";
                }
                if ($novo === 'rejeitada') {
                    $campos .= ", ch_dt_fim = COALESCE(ch_dt_fim, NOW())";
                }
                $params[] = (int)$row['ch_nb_id'];
                $types .= "i";
                wa_exec("UPDATE wa_chamada SET {$campos} WHERE ch_nb_id = ?", $types, $params);
            }
            wa_chamada_registrar_evento((int)$row['ch_nb_id'], $tipoEvento, $evento);
            return (int)$row['ch_nb_id'];
        }

        if ($tipoEvento !== 'chamada_evento') {
            return null;
        }

        $direcao = strval($evento['direcao'] ?? '') === 'in' ? 'in' : 'out';
        $telefone = $direcao === 'in'
            ? strval($evento['from'] ?? '')
            : strval($evento['to'] ?? '');
        $nome = $direcao === 'in' ? strval($evento['nome'] ?? '') : '';
        if ($row) {
            $conversaId = (int)$row['ch_nb_conversa'];
            $contatoId = (int)$row['ch_nb_contato'];
        } else {
            $contexto = wa_chamada_contexto($telefone, $nome);
            if (!$contexto) {
                wa_log('chamada_sem_contato', 'Chamada sem contato resolvido.', ['call_id' => $callId, 'telefone' => $telefone], 'warning');
                return null;
            }
            $conversaId = (int)$contexto['conversa_id'];
            $contatoId = (int)$contexto['contato']['cont_nb_id'];
        }
        $eventoNome = strtolower(strval($evento['evento'] ?? ''));
        $sdp = strval($evento['sdp'] ?? '');
        $sdpTipo = strval($evento['sdp_tipo'] ?? '');
        $status = strtolower(strval($evento['status'] ?? ''));
        $duracao = max(0, (int)($evento['duracao'] ?? 0));
        $inicio = (int)($evento['inicio'] ?? 0);
        $fim = (int)($evento['fim'] ?? 0);
        $erro = substr(strval($evento['erro'] ?? ''), 0, 250);

        if (!$row) {
            $statusInicial = $eventoNome === 'connect' ? 'tocando' : 'solicitando';
            $offer = $direcao === 'in' ? $sdp : '';
            $answer = $direcao === 'out' && $sdpTipo === 'answer' ? $sdp : '';
            $ok = wa_exec(
                "INSERT INTO wa_chamada
                    (ch_tx_call_id, ch_nb_conversa, ch_nb_contato, ch_tx_direcao, ch_tx_status,
                     ch_tx_sdp_offer, ch_tx_sdp_answer, ch_tx_erro, ch_tx_opaque, ch_dt_criacao)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                "siissssss",
                [
                    $callId,
                    $conversaId,
                    $contatoId,
                    $direcao,
                    $statusInicial,
                    $offer !== '' ? $offer : null,
                    $answer !== '' ? $answer : null,
                    $erro !== '' ? $erro : null,
                    strval($evento['opaque'] ?? '') !== '' ? substr(strval($evento['opaque']), 0, 512) : null,
                ]
            );
            if (!$ok) {
                return null;
            }
            $rowId = (int)mysqli_insert_id($GLOBALS['conn']);
        } else {
            $rowId = (int)$row['ch_nb_id'];
            $sets = ["ch_dt_atualizacao = NOW()"];
            $types = "";
            $params = [];
            if ($eventoNome === 'connect') {
                $sets[] = "ch_tx_status = CASE WHEN ch_tx_status IN ('conectada','encerrada','rejeitada','falhou') THEN ch_tx_status ELSE 'tocando' END";
                if ($direcao === 'in' && $sdp !== '') {
                    $sets[] = "ch_tx_sdp_offer = ?";
                    $types .= "s";
                    $params[] = $sdp;
                }
                if ($direcao === 'out' && $sdpTipo === 'answer' && $sdp !== '') {
                    $sets[] = "ch_tx_sdp_answer = ?";
                    $types .= "s";
                    $params[] = $sdp;
                }
            } elseif ($eventoNome === 'terminate') {
                $final = $status === 'failed' ? 'falhou' : 'encerrada';
                $sets[] = "ch_tx_status = ?";
                $types .= "s";
                $params[] = $final;
                $sets[] = "ch_dt_fim = COALESCE(ch_dt_fim, NOW())";
                if ($duracao > 0) {
                    $sets[] = "ch_nb_duracao = ?";
                    $types .= "i";
                    $params[] = $duracao;
                }
                if ($inicio > 0) {
                    $sets[] = "ch_dt_inicio = FROM_UNIXTIME(?)";
                    $types .= "i";
                    $params[] = $inicio;
                }
                if ($fim > 0) {
                    $sets[] = "ch_dt_fim = FROM_UNIXTIME(?)";
                    $types .= "i";
                    $params[] = $fim;
                }
                if ($erro !== '') {
                    $sets[] = "ch_tx_erro = ?";
                    $types .= "s";
                    $params[] = $erro;
                }
            }
            if ($types !== '') {
                $params[] = $rowId;
                $types .= "i";
                wa_exec("UPDATE wa_chamada SET " . implode(', ', $sets) . " WHERE ch_nb_id = ?", $types, $params);
            } else {
                wa_exec("UPDATE wa_chamada SET ch_dt_atualizacao = NOW() WHERE ch_nb_id = ?", "i", [$rowId]);
            }
        }

        wa_chamada_registrar_evento($rowId, $eventoNome !== '' ? $eventoNome : $tipoEvento, $evento);
        return $rowId;
    }
}

if (!function_exists('wa_chamada_registrar_evento')) {
    function wa_chamada_registrar_evento(int $chamadaId, string $evento, array $payload): void {
        if ($chamadaId <= 0) {
            return;
        }
        wa_exec(
            "INSERT INTO wa_chamada_evento (che_nb_chamada, che_tx_evento, che_tx_payload, che_dt_criacao) VALUES (?, ?, ?, NOW())",
            "iss",
            [$chamadaId, substr($evento, 0, 40), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );
    }
}

if (!function_exists('wa_chamadas_pendentes')) {
    function wa_chamadas_pendentes(int $limite = 5): array {
        $limite = max(1, min(20, $limite));
        $rs = query(
            "SELECT ch.*, c.cont_tx_nome, c.cont_tx_e164
             FROM wa_chamada ch
             JOIN wa_contato c ON c.cont_nb_id = ch.ch_nb_contato
             WHERE ch.ch_tx_direcao = 'in' AND ch.ch_tx_status = 'tocando'
               AND ch.ch_tx_sdp_offer IS NOT NULL AND ch.ch_tx_sdp_offer <> ''
             ORDER BY ch.ch_nb_id DESC LIMIT ?",
            "i",
            [$limite]
        );
        $out = [];
        while ($rs && ($row = mysqli_fetch_assoc($rs))) {
            $out[] = $row;
        }
        return $out;
    }
}

if (!function_exists('wa_chamada_iniciar_aceite')) {
    function wa_chamada_iniciar_aceite(string $callId, string $sdpAnswer, int $usuarioId = 0): bool {
        $row = wa_chamada_por_call_id($callId);
        if (!$row) {
            return false;
        }
        $ok = wa_exec(
            "UPDATE wa_chamada SET ch_tx_sdp_answer = ?, ch_tx_status = 'solicitando', ch_nb_user = COALESCE(?, ch_nb_user), ch_dt_atualizacao = NOW() WHERE ch_nb_id = ?",
            "sii",
            [$sdpAnswer, $usuarioId > 0 ? $usuarioId : null, (int)$row['ch_nb_id']]
        );
        return $ok;
    }
}

if (!function_exists('wa_chamada_status_label')) {
    function wa_chamada_status_label(string $status): string {
        return match (strtolower($status)) {
            'solicitando' => 'Solicitando',
            'tocando' => 'Chamando',
            'conectada' => 'Conectada',
            'encerrada' => 'Encerrada',
            'rejeitada' => 'Rejeitada',
            'falhou' => 'Falhou',
            default => ucfirst($status),
        };
    }
}
