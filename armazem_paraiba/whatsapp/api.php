<?php
/**
 * API AJAX autenticada do módulo WhatsApp.
 */

define('WA_API_REQUEST', true);
require_once __DIR__ . '/bootstrap.php';

if (!function_exists('wa_api_permissao')) {
    function wa_api_permissao(string $path): void {
        if (!function_exists('usuarioPodeAcessar') || !usuarioPodeAcessar($path)) {
            wa_json(['success' => false, 'message' => 'Você não tem permissão para esta ação.'], 403);
        }
    }
}

if (!function_exists('wa_api_csrf')) {
    function wa_api_csrf(): void {
        $metodo = strtoupper(strval($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($metodo !== 'POST') {
            return;
        }
        $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!wa_csrf_validar(is_string($token) ? $token : '')) {
            wa_json(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 419);
        }
    }
}

if (!function_exists('wa_api_usuario')) {
    function wa_api_usuario(): int {
        return (int)($_SESSION['user_nb_id'] ?? 0);
    }
}

if (!function_exists('wa_api_exigir_post')) {
    function wa_api_exigir_post(string $acao): void {
        $metodo = strtoupper(strval($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $acoesPost = [
            'enviar', 'enviar_midia', 'marcar_lida', 'novo_contato',
            'criar_lote', 'sincronizar_contatos', 'processar_fila',
            'enviar_teste', 'testar_provider', 'solicitar_permissao_chamada', 'iniciar_chamada',
            'pre_aceitar_chamada', 'confirmar_chamada', 'rejeitar_chamada',
            'encerrar_chamada', 'habilitar_calling', 'salvar_config',
            'sincronizar_templates', 'qrcode',
        ];
        if (in_array($acao, $acoesPost, true) && $metodo !== 'POST') {
            wa_json(['success' => false, 'message' => 'Método não permitido para esta ação.'], 405);
        }
    }
}

if (!function_exists('wa_api_ids')) {
    function wa_api_ids($valor): array {
        if (is_array($valor)) {
            $ids = $valor;
        } else {
            $ids = explode(',', strval($valor));
        }
        $out = [];
        foreach ($ids as $id) {
            $n = (int)$id;
            if ($n > 0) {
                $out[$n] = $n;
            }
        }
        return array_values($out);
    }
}

try {
    $acao = strval($_GET['wa_acao'] ?? $_POST['wa_acao'] ?? '');
    wa_api_csrf();
    wa_api_exigir_post($acao);

    switch ($acao) {
        case 'conversas':
            wa_api_permissao('/whatsapp/index.php');
            $lista = wa_conversa_listar([
                'busca' => strval($_GET['busca'] ?? ''),
                'filtro' => strval($_GET['filtro'] ?? 'todas'),
                'origem' => strval($_GET['origem'] ?? 'todas'),
                'usuario' => wa_api_usuario(),
            ]);
            foreach ($lista as &$conversa) {
                $conversa['ultima_data_fmt'] = wa_data_utc_para_local(
                    $conversa['ultima_data'] ?? $conversa['conv_dt_ultima_atividade'] ?? '',
                    'd/m'
                );
            }
            unset($conversa);
            wa_json([
                'success' => true,
                'conversas' => $lista,
                'contadores' => [
                    'todas' => wa_conversa_contar('todas', true),
                    'empresa' => wa_conversa_contar('empresa', true),
                ],
            ]);

        case 'mensagens':
            wa_api_permissao('/whatsapp/index.php');
            $conversaId = (int)($_GET['conversa'] ?? 0);
            $aposId = (int)($_GET['apos_id'] ?? 0);
            $conv = wa_conversa_por_id($conversaId);
            if (!$conv) {
                wa_json(['success' => false, 'message' => 'Conversa não encontrada.'], 404);
            }
            $mensagens = wa_mensagens_listar($conversaId, $aposId, 200);
            foreach ($mensagens as &$mensagem) {
                $mensagem['msg_dt_criacao_fmt'] = wa_data_utc_para_local($mensagem['msg_dt_criacao'] ?? '');
            }
            unset($mensagem);
            if ($aposId === 0) {
                wa_marcar_conversa_lida($conversaId);
            }
            wa_json([
                'success' => true,
                'mensagens' => $mensagens,
                'contato' => [
                    'cont_nb_id' => (int)$conv['cont_nb_id'],
                    'cont_tx_nome' => $conv['cont_tx_nome'],
                    'cont_tx_e164' => $conv['cont_tx_e164'],
                    'cont_tx_telefone' => $conv['cont_tx_telefone'],
                    'cont_tx_setor' => $conv['cont_tx_setor'],
                    'cont_tx_cargo' => $conv['cont_tx_cargo'],
                ],
            ]);

        case 'mensagens_prontas':
            if (!function_exists('usuarioPodeAcessar')
                || (!usuarioPodeAcessar('/whatsapp/index.php') && !usuarioPodeAcessar('/whatsapp/lote.php'))) {
                wa_json(['success' => false, 'message' => 'Você não tem permissão para esta ação.'], 403);
            }
            $prontas = wa_mensagens_prontas_listar(true);
            wa_json(['success' => true, 'mensagens' => $prontas]);

        case 'enviar':
            wa_api_permissao('/whatsapp/index.php');
            if (!wa_rate_limit('enviar', 30, 60)) {
                wa_json(['success' => false, 'message' => 'Limite de 30 mensagens por minuto atingido.'], 429);
            }
            $conversaId = (int)($_POST['conversa'] ?? 0);
            $texto = strval($_POST['texto'] ?? '');
            $resultado = wa_enviar_texto($conversaId, $texto, wa_api_usuario(), true);
            wa_json($resultado, !empty($resultado['success']) ? 200 : 422);

        case 'enviar_midia':
            wa_api_permissao('/whatsapp/index.php');
            if (!wa_rate_limit('enviar_midia', 20, 60)) {
                wa_json(['success' => false, 'message' => 'Limite de 20 mídias por minuto atingido.'], 429);
            }
            $conversaId = (int)($_POST['conversa'] ?? 0);
            $legenda = strval($_POST['legenda'] ?? ($_POST['texto'] ?? ''));
            if (empty($_FILES['arquivo']) || !is_array($_FILES['arquivo'])) {
                wa_json(['success' => false, 'message' => 'Selecione um arquivo para enviar.'], 422);
            }
            $resultado = wa_enviar_midia($conversaId, $_FILES['arquivo'], $legenda, wa_api_usuario(), true);
            wa_json($resultado, !empty($resultado['success']) ? 200 : 422);

        case 'marcar_lida':
            wa_api_permissao('/whatsapp/index.php');
            wa_marcar_conversa_lida((int)($_POST['conversa'] ?? 0));
            wa_json(['success' => true]);

        case 'status_mensagens':
            wa_api_permissao('/whatsapp/index.php');
            $conversaId = (int)($_GET['conversa'] ?? 0);
            $rs = query(
                "SELECT msg_nb_id, msg_tx_status FROM wa_mensagem
                 WHERE msg_nb_conversa = ? ORDER BY msg_nb_id DESC LIMIT 100",
                "i",
                [$conversaId]
            );
            $status = [];
            while ($rs && ($row = mysqli_fetch_assoc($rs))) {
                $status[] = ['id' => (int)$row['msg_nb_id'], 'status' => $row['msg_tx_status']];
            }
            wa_json(['success' => true, 'mensagens' => $status]);

        case 'buscar_contatos':
            wa_api_permissao('/whatsapp/index.php');
            $contatos = wa_contato_buscar(strval($_GET['termo'] ?? ''), 30);
            $out = [];
            foreach ($contatos as $c) {
                $out[] = [
                    'cont_nb_id' => (int)$c['cont_nb_id'],
                    'cont_tx_nome' => $c['cont_tx_nome'],
                    'cont_tx_e164' => $c['cont_tx_e164'],
                    'cont_tx_telefone' => $c['cont_tx_telefone'],
                    'cont_tx_setor' => $c['cont_tx_setor'],
                    'cont_tx_cargo' => $c['cont_tx_cargo'],
                    'conv_nb_id' => (int)($c['conv_nb_id'] ?? 0),
                ];
            }
            wa_json(['success' => true, 'contatos' => $out]);

        case 'novo_contato':
            wa_api_permissao('/whatsapp/index.php');
            if (!wa_rate_limit('novo_contato', 10, 60)) {
                wa_json(['success' => false, 'message' => 'Muitas tentativas. Aguarde um minuto.'], 429);
            }
            $nome = trim(strval($_POST['nome'] ?? ''));
            $telefone = trim(strval($_POST['telefone'] ?? ''));
            if (mb_strlen($nome) > 180 || mb_strlen($telefone) > 30) {
                wa_json(['success' => false, 'message' => 'Nome ou telefone muito longo.'], 422);
            }
            if (wa_normalizar_telefone($telefone) === null) {
                wa_json(['success' => false, 'message' => 'Telefone inválido. Informe DDD + número.'], 422);
            }
            $contato = wa_contato_resolver_por_telefone($telefone, $nome);
            if (!$contato) {
                wa_json(['success' => false, 'message' => 'Não foi possível criar o contato.'], 500);
            }
            $conversaId = wa_conversa_garantir((int)$contato['cont_nb_id']);
            wa_json([
                'success' => true,
                'contato' => $contato,
                'conversa_id' => $conversaId,
            ]);

        case 'contatos_lote':
            wa_api_permissao('/whatsapp/lote.php');
            $contatos = wa_contatos_filtrados([
                'busca' => strval($_GET['busca'] ?? ''),
                'empresa' => (int)($_GET['empresa'] ?? 0),
                'setor' => strval($_GET['setor'] ?? ''),
                'cargo' => strval($_GET['cargo'] ?? ''),
                'somente_optin' => !empty($_GET['somente_optin']),
                'somente_com_whatsapp' => true,
            ], 500);
            $out = [];
            foreach ($contatos as $c) {
                $out[] = [
                    'cont_nb_id' => (int)$c['cont_nb_id'],
                    'cont_tx_nome' => $c['cont_tx_nome'],
                    'cont_tx_e164' => $c['cont_tx_e164'],
                    'cont_tx_telefone' => $c['cont_tx_telefone'],
                    'cont_tx_setor' => $c['cont_tx_setor'],
                    'cont_tx_cargo' => $c['cont_tx_cargo'],
                    'cont_tx_empresa_nome' => $c['cont_tx_empresa_nome'] ?? '',
                    'cont_tx_optin' => $c['cont_tx_optin'],
                ];
            }
            wa_json(['success' => true, 'contatos' => $out]);

        case 'criar_lote':
            wa_api_permissao('/whatsapp/lote.php');
            if (!wa_rate_limit('criar_lote', 10, 60)) {
                wa_json(['success' => false, 'message' => 'Muitos lotes em sequência. Aguarde um minuto.'], 429);
            }
            $ids = wa_api_ids($_POST['ids'] ?? []);
            $mensagem = trim(strval($_POST['mensagem'] ?? ''));
            $titulo = trim(strval($_POST['titulo'] ?? ''));
            if (empty($ids)) {
                wa_json(['success' => false, 'message' => 'Selecione pelo menos um destinatário.'], 422);
            }
            if ($mensagem === '') {
                wa_json(['success' => false, 'message' => 'Informe a mensagem do lote.'], 422);
            }
            if (mb_strlen($mensagem) > 4096) {
                wa_json(['success' => false, 'message' => 'A mensagem do lote excede 4096 caracteres.'], 422);
            }
            if (mb_strlen($titulo) > 150) {
                wa_json(['success' => false, 'message' => 'O título do lote excede 150 caracteres.'], 422);
            }
            if (count($ids) > 200) {
                wa_json(['success' => false, 'message' => 'O limite por lote é de 200 destinatários.'], 422);
            }

            global $conn;
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $rs = query(
                "SELECT c.*, empr.empr_tx_nome AS cont_tx_empresa_nome
                 FROM wa_contato c
                 LEFT JOIN empresa empr ON empr.empr_nb_id = c.cont_nb_empresa
                 WHERE c.cont_nb_id IN ({$placeholders}) AND c.cont_tx_status = 'ativo'",
                $types,
                $ids
            );
            $contatos = [];
            while ($rs && ($row = mysqli_fetch_assoc($rs))) {
                $contatos[(int)$row['cont_nb_id']] = $row;
            }
            if (empty($contatos)) {
                wa_json(['success' => false, 'message' => 'Nenhum contato válido encontrado.'], 422);
            }

            $permitirOptout = !empty($_POST['permitir_optout']) && preg_match('/administrador/i', strval($_SESSION['user_tx_nivel'] ?? ''));
            $justificativaOptout = trim(strval($_POST['justificativa'] ?? ''));
            if ($permitirOptout && mb_strlen($justificativaOptout) < 10) {
                wa_json(['success' => false, 'message' => 'Informe uma justificativa com pelo menos 10 caracteres para enviar sem opt-in.'], 422);
            }
            $selecionados = [];
            $semTelefone = 0;
            $semOptin = 0;
            foreach ($ids as $id) {
                $c = $contatos[$id] ?? null;
                if (!$c) {
                    continue;
                }
                if (trim(strval($c['cont_tx_e164'] ?? '')) === '') {
                    $semTelefone++;
                    continue;
                }
                if (strval($c['cont_tx_optin'] ?? 'nao') !== 'sim' && !$permitirOptout) {
                    $semOptin++;
                    continue;
                }
                $selecionados[] = $c;
            }
            if (empty($selecionados)) {
                wa_json([
                    'success' => false,
                    'message' => 'Nenhum destinatário elegível. Sem telefone: ' . $semTelefone . '; sem opt-in: ' . $semOptin . '.',
                ], 422);
            }

            $ok = wa_exec(
                "INSERT INTO wa_lote (lote_tx_titulo, lote_tx_mensagem, lote_tx_status, lote_nb_total, lote_nb_userCadastro, lote_dt_criacao, lote_dt_inicio)
                 VALUES (?, ?, 'enviando', ?, ?, NOW(), NOW())",
                "ssii",
                [$titulo !== '' ? $titulo : ('Lote ' . date('d/m/Y H:i')), $mensagem, count($selecionados), wa_api_usuario()]
            );
            if (!$ok) {
                wa_json(['success' => false, 'message' => 'Não foi possível criar o lote.'], 500);
            }
            $loteId = (int)mysqli_insert_id($conn);

            $criados = 0;
            foreach ($selecionados as $c) {
                $conversaId = wa_conversa_garantir((int)$c['cont_nb_id']);
                if ($conversaId <= 0) {
                    continue;
                }
                $texto = wa_personalizar_mensagem($mensagem, $c);
                $msgId = wa_mensagem_registrar([
                    'msg_nb_conversa' => $conversaId,
                    'msg_tx_direcao' => 'out',
                    'msg_tx_tipo' => 'texto',
                    'msg_tx_conteudo' => $texto,
                    'msg_tx_status' => 'pendente',
                    'msg_nb_user' => wa_api_usuario(),
                ]);
                if ($msgId <= 0) {
                    continue;
                }
                wa_exec(
                    "INSERT INTO wa_lote_destinatario (ld_nb_lote, ld_nb_contato, ld_nb_mensagem, ld_tx_telefone, ld_tx_status)
                     VALUES (?, ?, ?, ?, 'pendente')",
                    "iiis",
                    [$loteId, (int)$c['cont_nb_id'], $msgId, strval($c['cont_tx_e164'])]
                );
                wa_enfileirar_mensagem($msgId);
                $criados++;
            }

            wa_exec("UPDATE wa_lote SET lote_nb_total = ? WHERE lote_nb_id = ?", "ii", [$criados, $loteId]);
            wa_lote_recalcular($loteId);
            wa_log('lote_criado', 'Lote criado.', ['lote' => $loteId, 'destinatarios' => $criados]);
            if ($permitirOptout) {
                wa_log('lote_optout_liberado', 'Envio em lote liberado sem opt-in.', [
                    'lote' => $loteId,
                    'sem_optin' => $semOptin,
                    'justificativa' => $justificativaOptout,
                    'usuario' => wa_api_usuario(),
                ], 'warning');
            }
            wa_json([
                'success' => true,
                'lote_id' => $loteId,
                'total' => $criados,
                'sem_telefone' => $semTelefone,
                'sem_optin' => $semOptin,
                'message' => 'Lote criado e enfileirado.',
            ]);

        case 'status_lote':
            wa_api_permissao('/whatsapp/lote.php');
            $loteId = (int)($_GET['lote'] ?? 0);
            $rs = query("SELECT * FROM wa_lote WHERE lote_nb_id = ? LIMIT 1", "i", [$loteId]);
            $lote = $rs ? mysqli_fetch_assoc($rs) : null;
            if (!$lote) {
                wa_json(['success' => false, 'message' => 'Lote não encontrado.'], 404);
            }
            $rsDest = query(
                "SELECT ld.*, c.cont_tx_nome
                 FROM wa_lote_destinatario ld
                 JOIN wa_contato c ON c.cont_nb_id = ld.ld_nb_contato
                 WHERE ld.ld_nb_lote = ? ORDER BY ld.ld_nb_id ASC",
                "i",
                [$loteId]
            );
            $destinatarios = [];
            while ($rsDest && ($d = mysqli_fetch_assoc($rsDest))) {
                $destinatarios[] = $d;
            }
            wa_json(['success' => true, 'lote' => $lote, 'destinatarios' => $destinatarios]);

        case 'sincronizar_contatos':
            wa_api_permissao('/whatsapp/contatos.php');
            if (!wa_rate_limit('sincronizar_contatos', 2, 300)) {
                wa_json(['success' => false, 'message' => 'Sincronização já executada recentemente. Aguarde alguns minutos.'], 429);
            }
            $resumo = wa_sincronizar_contatos();
            wa_json(['success' => true, 'resumo' => $resumo, 'message' => 'Contatos sincronizados.']);

        case 'processar_fila':
            wa_api_permissao('/whatsapp/lote.php');
            if (!preg_match('/administrador/i', strval($_SESSION['user_tx_nivel'] ?? ''))) {
                wa_json(['success' => false, 'message' => 'Apenas administradores podem forçar a fila.'], 403);
            }
            if (!wa_rate_limit('processar_fila', 5, 60)) {
                wa_json(['success' => false, 'message' => 'Muitas execuções da fila. Aguarde um minuto.'], 429);
            }
            @set_time_limit(90);
            $resultado = wa_processar_fila(10);
            wa_json(['success' => true, 'resultado' => $resultado, 'message' => 'Fila processada.']);

        case 'testar_provider':
            wa_api_permissao('/whatsapp/configuracoes.php');
            if (!wa_rate_limit('testar_provider', 10, 60)) {
                wa_json(['success' => false, 'message' => 'Muitos testes de conexão. Aguarde um minuto.'], 429);
            }
            [$provider] = wa_config_provider();
            $teste = $provider->testConnection();
            wa_json($teste, !empty($teste['success']) ? 200 : 422);

        case 'enviar_teste':
            wa_api_permissao('/whatsapp/configuracoes.php');
            if (!wa_rate_limit('enviar_teste', 3, 60)) {
                wa_json(['success' => false, 'message' => 'Muitos envios de teste. Aguarde um minuto.'], 429);
            }
            $cfgTeste = wa_config();
            $numero = trim(strval($_POST['numero'] ?? ($cfgTeste['cfg_tx_test_number'] ?? '')));
            if ($numero === '') {
                $numero = trim(strval($_ENV['WHATSAPP_TEST_NUMBER'] ?? ''));
            }
            $e164 = wa_normalizar_telefone($numero);
            if ($e164 === null) {
                wa_json(['success' => false, 'message' => 'Informe um número de teste válido no campo “Número para teste” ou em WHATSAPP_TEST_NUMBER.'], 422);
            }
            [$provider] = wa_config_provider();
            $envio = $provider->sendText($e164, 'Teste de conexão do CRM - TECHPS.');
            wa_json([
                'success' => !empty($envio['success']),
                'message' => !empty($envio['success']) ? 'Mensagem de teste enviada.' : strval($envio['error'] ?? 'Falha no envio.'),
                'provider_id' => $envio['provider_id'] ?? '',
            ], !empty($envio['success']) ? 200 : 422);

        case 'chamada_permissao':
            wa_api_permissao('/whatsapp/index.php');
            if (!wa_calling_ativa()) {
                wa_json(['success' => false, 'message' => 'Chamadas não estão ativas. Configure a Cloud API e habilite Calling.'], 422);
            }
            $conv = wa_conversa_por_id((int)($_GET['conversa'] ?? 0));
            if (!$conv || trim(strval($conv['cont_tx_e164'] ?? '')) === '') {
                wa_json(['success' => false, 'message' => 'Conversa ou telefone inválido.'], 422);
            }
            [$provider] = wa_config_provider();
            $permissao = $provider->getCallPermission(strval($conv['cont_tx_e164']));
            wa_json($permissao, !empty($permissao['success']) ? 200 : 422);

        case 'solicitar_permissao_chamada':
            wa_api_permissao('/whatsapp/index.php');
            if (!wa_calling_ativa()) {
                wa_json(['success' => false, 'message' => 'Chamadas não estão ativas.'], 422);
            }
            $conv = wa_conversa_por_id((int)($_POST['conversa'] ?? 0));
            if (!$conv || trim(strval($conv['cont_tx_e164'] ?? '')) === '') {
                wa_json(['success' => false, 'message' => 'Conversa ou telefone inválido.'], 422);
            }
            [$provider] = wa_config_provider();
            $texto = trim(strval($_POST['texto'] ?? ''));
            if ($texto === '') {
                $texto = 'Podemos ligar para você pelo WhatsApp para ajudar no atendimento?';
            }
            if (mb_strlen($texto) > 1024) {
                wa_json(['success' => false, 'message' => 'Texto de permissão acima do limite.'], 422);
            }
            $envio = $provider->sendCallPermissionRequest(strval($conv['cont_tx_e164']), $texto);
            wa_json([
                'success' => !empty($envio['success']),
                'message' => !empty($envio['success']) ? 'Solicitação de permissão enviada. Aguarde a aprovação no WhatsApp.' : strval($envio['error'] ?? 'Falha ao solicitar permissão.'),
                'provider_id' => $envio['provider_id'] ?? '',
            ], !empty($envio['success']) ? 200 : 422);

        case 'iniciar_chamada':
            wa_api_permissao('/whatsapp/index.php');
            if (!wa_calling_ativa()) {
                wa_json(['success' => false, 'message' => 'Chamadas não estão ativas.'], 422);
            }
            $conv = wa_conversa_por_id((int)($_POST['conversa'] ?? 0));
            $sdp = strval($_POST['sdp'] ?? '');
            if (!$conv || trim(strval($conv['cont_tx_e164'] ?? '')) === '') {
                wa_json(['success' => false, 'message' => 'Conversa ou telefone inválido.'], 422);
            }
            if (trim($sdp) === '') {
                wa_json(['success' => false, 'message' => 'Oferta SDP não informada.'], 422);
            }
            if (strlen($sdp) > 200000) {
                wa_json(['success' => false, 'message' => 'Oferta SDP acima do limite permitido.'], 422);
            }
            [$provider] = wa_config_provider();
            $opaque = 'crm-' . (int)$conv['conv_nb_id'] . '-' . time();
            $chamada = $provider->initiateCall(strval($conv['cont_tx_e164']), $sdp, $opaque);
            if (empty($chamada['success'])) {
                wa_json(['success' => false, 'message' => strval($chamada['error'] ?? 'Falha ao iniciar chamada.')], 422);
            }
            $callId = strval($chamada['call_id'] ?? '');
            $localId = wa_chamada_criar_saida((int)$conv['conv_nb_id'], $callId, $sdp, wa_api_usuario(), $opaque);
            wa_json([
                'success' => true,
                'call_id' => $callId,
                'chamada_id' => $localId,
                'message' => 'Chamada iniciada. Aguardando conexão.',
            ]);

        case 'chamada_poll':
            wa_api_permissao('/whatsapp/index.php');
            $callId = trim(strval($_GET['call_id'] ?? ''));
            $chamada = wa_chamada_por_call_id($callId);
            if (!$chamada) {
                wa_json(['success' => false, 'message' => 'Chamada não encontrada.'], 404);
            }
            wa_json(['success' => true, 'chamada' => [
                'id' => (int)$chamada['ch_nb_id'],
                'call_id' => $chamada['ch_tx_call_id'],
                'status' => $chamada['ch_tx_status'],
                'direcao' => $chamada['ch_tx_direcao'],
                'sdp_offer' => $chamada['ch_tx_sdp_offer'],
                'sdp_answer' => $chamada['ch_tx_sdp_answer'],
                'erro' => $chamada['ch_tx_erro'],
                'duracao' => (int)($chamada['ch_nb_duracao'] ?? 0),
            ]]);

        case 'chamadas_pendentes':
            wa_api_permissao('/whatsapp/index.php');
            $pendentes = wa_chamadas_pendentes(5);
            $out = [];
            foreach ($pendentes as $ch) {
                $out[] = [
                    'id' => (int)$ch['ch_nb_id'],
                    'call_id' => $ch['ch_tx_call_id'],
                    'contato' => $ch['cont_tx_nome'] ?? '',
                    'telefone' => $ch['cont_tx_e164'] ?? '',
                    'sdp_offer' => $ch['ch_tx_sdp_offer'],
                    'criada_em' => $ch['ch_dt_criacao'],
                ];
            }
            wa_json(['success' => true, 'chamadas' => $out]);

        case 'pre_aceitar_chamada':
            wa_api_permissao('/whatsapp/index.php');
            $callId = trim(strval($_POST['call_id'] ?? ''));
            $sdp = strval($_POST['sdp'] ?? '');
            if ($callId === '' || trim($sdp) === '') {
                wa_json(['success' => false, 'message' => 'Chamada ou resposta SDP inválida.'], 422);
            }
            if (strlen($sdp) > 200000) {
                wa_json(['success' => false, 'message' => 'Resposta SDP acima do limite permitido.'], 422);
            }
            [$provider] = wa_config_provider();
            $res = $provider->preAcceptCall($callId, $sdp);
            if (empty($res['success'])) {
                wa_json(['success' => false, 'message' => strval($res['error'] ?? 'Falha ao pré-aceitar chamada.')], 422);
            }
            wa_chamada_iniciar_aceite($callId, $sdp, wa_api_usuario());
            wa_json(['success' => true, 'message' => 'Chamada pré-aceita.']);

        case 'confirmar_chamada':
            wa_api_permissao('/whatsapp/index.php');
            $callId = trim(strval($_POST['call_id'] ?? ''));
            $chamada = wa_chamada_por_call_id($callId);
            if (!$chamada) {
                wa_json(['success' => false, 'message' => 'Chamada não encontrada.'], 422);
            }
            if (trim(strval($chamada['ch_tx_sdp_answer'] ?? '')) === '') {
                wa_json(['success' => false, 'message' => 'Resposta SDP da chamada não encontrada.'], 422);
            }
            if (strval($chamada['ch_tx_status'] ?? '') === 'conectada') {
                wa_json(['success' => true, 'message' => 'Chamada já conectada.']);
            }
            [$provider] = wa_config_provider();
            $res = $provider->acceptCall($callId, strval($chamada['ch_tx_sdp_answer']));
            if (empty($res['success'])) {
                wa_json(['success' => false, 'message' => strval($res['error'] ?? 'Falha ao aceitar chamada.')], 422);
            }
            wa_exec("UPDATE wa_chamada SET ch_tx_status = 'conectada', ch_dt_inicio = COALESCE(ch_dt_inicio, NOW()), ch_dt_atualizacao = NOW() WHERE ch_nb_id = ?", "i", [(int)$chamada['ch_nb_id']]);
            wa_json(['success' => true, 'message' => 'Chamada aceita.']);

        case 'rejeitar_chamada':
            wa_api_permissao('/whatsapp/index.php');
            $callId = trim(strval($_POST['call_id'] ?? ''));
            $chamada = wa_chamada_por_call_id($callId);
            [$provider] = wa_config_provider();
            $res = $provider->rejectCall($callId);
            if ($chamada) {
                wa_exec("UPDATE wa_chamada SET ch_tx_status = 'rejeitada', ch_dt_fim = COALESCE(ch_dt_fim, NOW()), ch_dt_atualizacao = NOW() WHERE ch_nb_id = ?", "i", [(int)$chamada['ch_nb_id']]);
            }
            wa_json(['success' => !empty($res['success']), 'message' => !empty($res['success']) ? 'Chamada rejeitada.' : strval($res['error'] ?? 'Falha ao rejeitar.')], !empty($res['success']) ? 200 : 422);

        case 'encerrar_chamada':
            wa_api_permissao('/whatsapp/index.php');
            $callId = trim(strval($_POST['call_id'] ?? ''));
            $chamada = wa_chamada_por_call_id($callId);
            [$provider] = wa_config_provider();
            $res = $provider->terminateCall($callId);
            if ($chamada) {
                wa_exec("UPDATE wa_chamada SET ch_tx_status = 'encerrada', ch_dt_fim = COALESCE(ch_dt_fim, NOW()), ch_dt_atualizacao = NOW() WHERE ch_nb_id = ?", "i", [(int)$chamada['ch_nb_id']]);
            }
            wa_json(['success' => !empty($res['success']), 'message' => !empty($res['success']) ? 'Chamada encerrada.' : strval($res['error'] ?? 'Falha ao encerrar.')], !empty($res['success']) ? 200 : 422);

        case 'habilitar_calling':
            wa_api_permissao('/whatsapp/configuracoes.php');
            if (!wa_rate_limit('habilitar_calling', 5, 60)) {
                wa_json(['success' => false, 'message' => 'Muitas alterações de Calling. Aguarde um minuto.'], 429);
            }
            if (strval(wa_config()['cfg_tx_provider'] ?? '') !== 'cloud') {
                wa_json(['success' => false, 'message' => 'Chamadas oficiais exigem o provedor Cloud API.'], 422);
            }
            [$provider] = wa_config_provider();
            $habilitar = !empty($_POST['enabled']);
            $res = $provider->setCallingStatus($habilitar);
            if (!empty($res['success'])) {
                wa_exec("UPDATE wa_config SET cfg_tx_call_status = ?, cfg_tx_dataAtualiza = NOW() WHERE cfg_nb_id = 1", "s", [$habilitar ? 'ativo' : 'inativo']);
                wa_config(true);
            }
            wa_json([
                'success' => !empty($res['success']),
                'message' => !empty($res['success']) ? ($habilitar ? 'Calling habilitado no número.' : 'Calling desabilitado no número.') : strval($res['error'] ?? 'Falha ao configurar Calling.'),
            ], !empty($res['success']) ? 200 : 422);

        case 'salvar_config':
            wa_api_permissao('/whatsapp/configuracoes.php');
            if (!wa_rate_limit('salvar_config', 10, 60)) {
                wa_json(['success' => false, 'message' => 'Muitas alterações de configuração. Aguarde um minuto.'], 429);
            }
            $resultado = wa_config_salvar([
                'provider' => $_POST['provider'] ?? 'evolution',
                'status' => $_POST['status'] ?? 'inativo',
                'base_url' => $_POST['base_url'] ?? '',
                'instance' => $_POST['instance'] ?? '',
                'token' => $_POST['token'] ?? '',
                'phone_number_id' => $_POST['phone_number_id'] ?? '',
                'waba_id' => $_POST['waba_id'] ?? '',
                'verify_token' => $_POST['verify_token'] ?? '',
                'app_secret' => $_POST['app_secret'] ?? '',
                'retencao_dias' => $_POST['retencao_dias'] ?? 0,
                'call_status' => $_POST['call_status'] ?? 'inativo',
            ], wa_api_usuario());
            if (!empty($resultado['success'])) {
                wa_log('config_atualizada', 'Configuração do provedor atualizada.', ['provider' => $_POST['provider'] ?? '']);
            }
            wa_json($resultado, !empty($resultado['success']) ? 200 : 422);

        case 'qrcode':
            wa_api_permissao('/whatsapp/configuracoes.php');
            if (!wa_rate_limit('qrcode', 6, 60)) {
                wa_json(['success' => false, 'message' => 'Muitas solicitações de QR Code. Aguarde um minuto.'], 429);
            }
            [$provider] = wa_config_provider();
            $qr = $provider->getQrCode();
            wa_json($qr, !empty($qr['success']) ? 200 : 422);

        case 'conexao':
            wa_api_permissao('/whatsapp/configuracoes.php');
            [$provider] = wa_config_provider();
            $estado = $provider->getConnectionState();
            wa_json(['success' => !empty($estado['success']), 'state' => $estado['state'] ?? '', 'message' => $estado['message'] ?? '']);

        case 'sincronizar_templates':
            wa_api_permissao('/whatsapp/configuracoes.php');
            if (!wa_rate_limit('sincronizar_templates', 5, 60)) {
                wa_json(['success' => false, 'message' => 'Muitas sincronizações de templates. Aguarde um minuto.'], 429);
            }
            [$provider] = wa_config_provider();
            $tpl = $provider->getTemplates();
            wa_json($tpl, !empty($tpl['success']) ? 200 : 422);

        default:
            wa_json(['success' => false, 'message' => 'Ação não reconhecida.'], 404);
    }
} catch (Throwable $e) {
    wa_log('api_erro', $e->getMessage(), ['acao' => $acao ?? ''], 'error');
    wa_json(['success' => false, 'message' => 'Erro interno ao processar a solicitação.'], 500);
}
