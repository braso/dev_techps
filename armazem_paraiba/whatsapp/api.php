<?php
/**
 * API AJAX autenticada do módulo WhatsApp.
 */

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

    switch ($acao) {
        case 'conversas':
            wa_api_permissao('/whatsapp/index.php');
            $lista = wa_conversa_listar([
                'busca' => strval($_GET['busca'] ?? ''),
                'filtro' => strval($_GET['filtro'] ?? 'todas'),
                'usuario' => wa_api_usuario(),
            ]);
            wa_json(['success' => true, 'conversas' => $lista]);

        case 'mensagens':
            wa_api_permissao('/whatsapp/index.php');
            $conversaId = (int)($_GET['conversa'] ?? 0);
            $aposId = (int)($_GET['apos_id'] ?? 0);
            $conv = wa_conversa_por_id($conversaId);
            if (!$conv) {
                wa_json(['success' => false, 'message' => 'Conversa não encontrada.'], 404);
            }
            $mensagens = wa_mensagens_listar($conversaId, $aposId);
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
            $nome = trim(strval($_POST['nome'] ?? ''));
            $telefone = trim(strval($_POST['telefone'] ?? ''));
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
            $ids = wa_api_ids($_POST['ids'] ?? []);
            $mensagem = trim(strval($_POST['mensagem'] ?? ''));
            $titulo = trim(strval($_POST['titulo'] ?? ''));
            if (empty($ids)) {
                wa_json(['success' => false, 'message' => 'Selecione pelo menos um destinatário.'], 422);
            }
            if ($mensagem === '') {
                wa_json(['success' => false, 'message' => 'Informe a mensagem do lote.'], 422);
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
            $resumo = wa_sincronizar_contatos();
            wa_json(['success' => true, 'resumo' => $resumo, 'message' => 'Contatos sincronizados.']);

        case 'processar_fila':
            wa_api_permissao('/whatsapp/lote.php');
            if (!preg_match('/administrador/i', strval($_SESSION['user_tx_nivel'] ?? ''))) {
                wa_json(['success' => false, 'message' => 'Apenas administradores podem forçar a fila.'], 403);
            }
            @set_time_limit(90);
            $resultado = wa_processar_fila(10);
            wa_json(['success' => true, 'resultado' => $resultado, 'message' => 'Fila processada.']);

        case 'testar_provider':
            wa_api_permissao('/whatsapp/configuracoes.php');
            [$provider] = wa_config_provider();
            $teste = $provider->testConnection();
            wa_json($teste, !empty($teste['success']) ? 200 : 422);

        case 'enviar_teste':
            wa_api_permissao('/whatsapp/configuracoes.php');
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

        case 'salvar_config':
            wa_api_permissao('/whatsapp/configuracoes.php');
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
            ], wa_api_usuario());
            if (!empty($resultado['success'])) {
                wa_log('config_atualizada', 'Configuração do provedor atualizada.', ['provider' => $_POST['provider'] ?? '']);
            }
            wa_json($resultado, !empty($resultado['success']) ? 200 : 422);

        case 'qrcode':
            wa_api_permissao('/whatsapp/configuracoes.php');
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
