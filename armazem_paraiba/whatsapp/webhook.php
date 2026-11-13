<?php
/**
 * Webhook público do módulo WhatsApp.
 * GET  = verificação da Cloud API (hub.challenge)
 * POST = mensagens e status (Cloud API / Evolution API)
 */

require_once __DIR__ . '/bootstrap_public.php';

if (trim(strval($_ENV['WHATSAPP_ENABLED'] ?? '')) !== '1') {
    http_response_code(200);
    echo 'disabled';
    exit;
}

$cfg = wa_config(true);
$providerName = strval($cfg['cfg_tx_provider'] ?? 'evolution');

// Verificação do webhook da Meta.
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $mode = strval($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
    $token = strval($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
    $challenge = strval($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');
    $esperado = strval($cfg['cfg_tx_verify_token'] ?? '');

    if ($providerName === 'cloud' && $esperado !== '' && $mode === 'subscribe' && hash_equals($esperado, $token)) {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo $challenge;
        exit;
    }

    if ($providerName === 'evolution') {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ok';
        exit;
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// A partir daqui é POST.
$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    http_response_code(400);
    echo 'Bad Request';
    exit;
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$headers = is_array($headers) ? $headers : [];

[$provider] = wa_config_provider();

if (!$provider->verifyWebhook($headers, $raw)) {
    wa_log('webhook_assinatura_invalida', 'Assinatura/token inválido.', ['provider' => $providerName], 'warning');
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo 'Bad Request';
    exit;
}

$eventId = hash('sha256', $raw);
$stmt = mysqli_prepare($conn, "INSERT IGNORE INTO wa_evento (evt_tx_provider, evt_tx_event_id, evt_tx_tipo, evt_tx_payload, evt_tx_status) VALUES (?, ?, ?, ?, 'recebido')");
if ($stmt) {
    $tipo = strval($payload['event'] ?? $payload['object'] ?? 'evento');
    mysqli_stmt_bind_param($stmt, 'ssss', $providerName, $eventId, $tipo, $raw);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$rsEvento = query("SELECT evt_nb_id, evt_tx_status FROM wa_evento WHERE evt_tx_provider = ? AND evt_tx_event_id = ? LIMIT 1", "ss", [$providerName, $eventId]);
$evento = $rsEvento ? mysqli_fetch_assoc($rsEvento) : null;
if ($evento && strval($evento['evt_tx_status']) === 'processado') {
    http_response_code(200);
    echo 'ok';
    exit;
}
$eventoId = (int)($evento['evt_nb_id'] ?? 0);

try {
    $eventos = $provider->parseWebhook($payload);
    $processados = 0;

    foreach ($eventos as $item) {
        $tipoEvento = strval($item['tipo'] ?? '');
        if ($tipoEvento === 'mensagem') {
            $msgId = wa_registrar_mensagem_webhook($item, $provider);
            if ($msgId) {
                $processados++;
            }
        } elseif ($tipoEvento === 'status') {
            wa_atualizar_status_provider(strval($item['provider_id'] ?? ''), strval($item['status'] ?? ''));
            $processados++;
        }
    }

    if ($eventoId > 0) {
        wa_exec("UPDATE wa_evento SET evt_tx_status = 'processado', evt_tx_erro = NULL WHERE evt_nb_id = ?", "i", [$eventoId]);
    }
    wa_log('webhook_processado', 'Evento processado.', ['provider' => $providerName, 'eventos' => $processados]);
} catch (Throwable $e) {
    if ($eventoId > 0) {
        wa_exec("UPDATE wa_evento SET evt_tx_status = 'erro', evt_tx_erro = ? WHERE evt_nb_id = ?", "si", [substr($e->getMessage(), 0, 250), $eventoId]);
    }
    wa_log('webhook_erro', $e->getMessage(), ['provider' => $providerName], 'error');
}

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'ok';
