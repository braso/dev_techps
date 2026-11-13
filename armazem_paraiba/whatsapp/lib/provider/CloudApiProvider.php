<?php

/**
 * Provedor Cloud API oficial da Meta (WhatsApp Business Platform).
 * Documentação: https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class CloudApiProvider implements ProviderInterface {
    private string $baseUrl;
    private string $token;
    private string $phoneNumberId;
    private string $wabaId;
    private string $appSecret;

    public function __construct(array $config) {
        $this->baseUrl = rtrim(strval($config['cfg_tx_base_url'] ?? 'https://graph.facebook.com/v21.0'), '/');
        if ($this->baseUrl === '') {
            $this->baseUrl = 'https://graph.facebook.com/v21.0';
        }
        $this->token = strval($config['cfg_tx_token'] ?? '');
        $this->phoneNumberId = strval($config['cfg_tx_phone_number_id'] ?? '');
        $this->wabaId = strval($config['cfg_tx_waba_id'] ?? '');
        $this->appSecret = strval($config['cfg_tx_app_secret'] ?? '');
    }

    private function headers(): array {
        return ['Authorization: Bearer ' . $this->token];
    }

    private function request(string $method, string $path, ?array $payload = null): array {
        if ($this->token === '' || $this->phoneNumberId === '') {
            return ['success' => false, 'error' => 'Cloud API sem token ou phone_number_id configurado.'];
        }
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $res = WaHttpClient::json($method, $url, $this->headers(), $payload);
        if (!$res['ok']) {
            return ['success' => false, 'error' => $res['error'], 'status' => $res['status']];
        }
        return ['success' => true, 'data' => $res['data'] ?? []];
    }

    public function sendText(string $to, string $text): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => preg_replace('/\D+/', '', $to),
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $text],
        ];
        $res = $this->request('POST', $this->phoneNumberId . '/messages', $payload);
        if (!$res['success']) {
            return $res;
        }
        $id = $res['data']['messages'][0]['id'] ?? '';
        return ['success' => true, 'provider_id' => strval($id), 'data' => $res['data']];
    }

    public function sendMedia(string $to, string $filePath, string $mime, string $caption = '', string $fileName = ''): array {
        if (!is_file($filePath)) {
            return ['success' => false, 'error' => 'Arquivo de mídia não encontrado.'];
        }
        $nomeArquivo = $fileName !== '' ? basename($fileName) : basename($filePath);
        $upload = WaHttpClient::multipart(
            $this->baseUrl . '/' . $this->phoneNumberId . '/media',
            $this->headers(),
            [
                'messaging_product' => 'whatsapp',
                'file' => new CURLFile($filePath, $mime, $nomeArquivo),
            ]
        );
        if (!$upload['ok']) {
            return ['success' => false, 'error' => $upload['error']];
        }
        $mediaId = strval($upload['data']['id'] ?? '');
        if ($mediaId === '') {
            return ['success' => false, 'error' => 'A Meta não retornou o id da mídia.'];
        }

        $tipo = 'document';
        if (str_starts_with($mime, 'image/')) {
            $tipo = 'image';
        } elseif (str_starts_with($mime, 'audio/')) {
            $tipo = 'audio';
        } elseif (str_starts_with($mime, 'video/')) {
            $tipo = 'video';
        }

        $objeto = ['id' => $mediaId];
        if ($caption !== '' && in_array($tipo, ['image', 'video', 'document'], true)) {
            $objeto['caption'] = $caption;
        }
        if ($tipo === 'document') {
            $objeto['filename'] = $nomeArquivo;
        }

        $res = $this->request('POST', $this->phoneNumberId . '/messages', [
            'messaging_product' => 'whatsapp',
            'to' => preg_replace('/\D+/', '', $to),
            'type' => $tipo,
            $tipo => $objeto,
        ]);
        if (!$res['success']) {
            return $res;
        }
        return [
            'success' => true,
            'provider_id' => strval($res['data']['messages'][0]['id'] ?? ''),
            'data' => $res['data'],
        ];
    }

    public function downloadMedia(array $media): array {
        $mediaId = trim(strval($media['media_id'] ?? ''));
        if ($mediaId === '') {
            return ['success' => false, 'error' => 'Cloud API sem media_id para baixar.'];
        }
        // 1) Consulta os metadados para obter a URL temporária.
        $meta = $this->request('GET', rawurlencode($mediaId));
        if (empty($meta['success'])) {
            return ['success' => false, 'error' => strval($meta['error'] ?? 'Falha ao consultar a mídia.')];
        }
        $url = strval($meta['data']['url'] ?? '');
        if ($url === '') {
            return ['success' => false, 'error' => 'A Meta não retornou a URL da mídia.'];
        }
        // 2) Baixa o binário autenticado.
        $res = WaHttpClient::raw($url, $this->headers());
        if (empty($res['ok']) || $res['body'] === '') {
            return ['success' => false, 'error' => strval($res['error'] ?? 'Falha ao baixar a mídia.')];
        }
        $mime = strval($meta['data']['mime_type'] ?? $media['mime'] ?? $res['mime'] ?? 'application/octet-stream');
        $nome = strval($meta['data']['filename'] ?? $media['nome'] ?? '');
        return [
            'success' => true,
            'conteudo' => $res['body'],
            'mime' => $mime,
            'nome' => $nome,
        ];
    }

    public function sendTemplate(string $to, string $template, string $language, array $params = []): array {
        $componentes = [];
        if (!empty($params)) {
            $componentes[] = [
                'type' => 'body',
                'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => strval($p)], array_values($params)),
            ];
        }
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => preg_replace('/\D+/', '', $to),
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language !== '' ? $language : 'pt_BR'],
            ],
        ];
        if (!empty($componentes)) {
            $payload['template']['components'] = $componentes;
        }
        $res = $this->request('POST', $this->phoneNumberId . '/messages', $payload);
        if (!$res['success']) {
            return $res;
        }
        return ['success' => true, 'provider_id' => strval($res['data']['messages'][0]['id'] ?? ''), 'data' => $res['data']];
    }

    public function markRead(string $providerMessageId): void {
        if ($providerMessageId === '') {
            return;
        }
        $this->request('POST', $this->phoneNumberId . '/messages', [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $providerMessageId,
        ]);
    }

    public function verifyWebhook(array $headers, string $rawBody): bool {
        if ($this->appSecret === '') {
            return false;
        }
        $assinatura = '';
        foreach ($headers as $chave => $valor) {
            if (strtolower(str_replace('_', '-', $chave)) === 'x-hub-signature-256') {
                $assinatura = strval($valor);
                break;
            }
        }
        if (!str_starts_with($assinatura, 'sha256=')) {
            return false;
        }
        $esperada = 'sha256=' . hash_hmac('sha256', $rawBody, $this->appSecret);
        return hash_equals($esperada, $assinatura);
    }

    public function parseWebhook(array $payload): array {
        $eventos = [];
        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                $contatos = $value['contacts'] ?? [];
                $nomeContato = strval($contatos[0]['profile']['name'] ?? '');

                foreach (($value['messages'] ?? []) as $msg) {
                    $tipo = strval($msg['type'] ?? 'texto');
                    $texto = '';
                    $mediaId = '';
                    $mime = '';
                    $fileName = '';
                    if ($tipo === 'text') {
                        $texto = strval($msg['text']['body'] ?? '');
                    } elseif (in_array($tipo, ['image', 'audio', 'video', 'document', 'sticker'], true)) {
                        $bloco = $msg[$tipo] ?? [];
                        $texto = strval($bloco['caption'] ?? '');
                        $mediaId = strval($bloco['id'] ?? '');
                        $mime = strval($bloco['mime_type'] ?? '');
                        if ($tipo === 'document') {
                            $fileName = strval($bloco['filename'] ?? '');
                            $texto = strval($bloco['caption'] ?? $fileName);
                        }
                    } elseif ($tipo === 'button') {
                        $texto = strval($msg['button']['text'] ?? '');
                    } elseif ($tipo === 'interactive') {
                        $texto = strval($msg['interactive']['button_reply']['title'] ?? $msg['interactive']['list_reply']['title'] ?? '');
                    }

                    $eventos[] = [
                        'tipo' => 'mensagem',
                        'provider_id' => strval($msg['id'] ?? ''),
                        'from' => strval($msg['from'] ?? ''),
                        'nome' => $nomeContato,
                        'tipo_msg' => wa_tipo_mensagem($tipo),
                        'texto' => $texto,
                        'media_id' => $mediaId,
                        'media_url' => '',
                        'media_base64' => '',
                        'media_key' => [],
                        'mime' => $mime,
                        'filename' => $fileName,
                        'timestamp' => intval($msg['timestamp'] ?? time()),
                    ];
                }

                foreach (($value['statuses'] ?? []) as $status) {
                    $eventos[] = [
                        'tipo' => 'status',
                        'provider_id' => strval($status['id'] ?? ''),
                        'status' => wa_status_provider(strval($status['status'] ?? '')),
                        'recipient' => strval($status['recipient_id'] ?? ''),
                        'timestamp' => intval($status['timestamp'] ?? time()),
                    ];
                }
            }
        }
        return $eventos;
    }

    public function testConnection(): array {
        if ($this->token === '' || $this->phoneNumberId === '') {
            return ['success' => false, 'message' => 'Informe o token e o phone_number_id.'];
        }
        $res = WaHttpClient::json(
            'GET',
            $this->baseUrl . '/' . $this->phoneNumberId . '?fields=display_phone_number,verified_name',
            $this->headers()
        );
        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Falha na conexão: ' . $res['error']];
        }
        $nome = strval($res['data']['verified_name'] ?? '');
        $numero = strval($res['data']['display_phone_number'] ?? '');
        return ['success' => true, 'message' => 'Conectado' . ($nome !== '' ? " a {$nome}" : '') . ($numero !== '' ? " ({$numero})" : '') . '.'];
    }

    public function getQrCode(): array {
        return ['success' => false, 'message' => 'QR Code é exclusivo da Evolution API.'];
    }

    public function getConnectionState(): array {
        $teste = $this->testConnection();
        return ['success' => $teste['success'], 'state' => $teste['success'] ? 'open' : 'closed', 'message' => $teste['message']];
    }

    public function getTemplates(): array {
        if ($this->token === '' || $this->wabaId === '') {
            return ['success' => false, 'message' => 'Informe o token e o waba_id.'];
        }
        $res = WaHttpClient::json(
            'GET',
            $this->baseUrl . '/' . $this->wabaId . '/message_templates?fields=name,status,category,language,components&limit=200',
            $this->headers()
        );
        if (!$res['ok']) {
            return ['success' => false, 'message' => $res['error']];
        }
        $templates = $res['data']['data'] ?? [];
        global $conn;
        foreach ($templates as $tpl) {
            $nome = strval($tpl['name'] ?? '');
            $idioma = strval($tpl['language'] ?? 'pt_BR');
            $categoria = strtoupper(strval($tpl['category'] ?? 'UTILITY'));
            if (!in_array($categoria, ['MARKETING', 'UTILITY', 'AUTHENTICATION'], true)) {
                $categoria = 'UTILITY';
            }
            $status = strval($tpl['status'] ?? '');
            $componentes = json_encode($tpl['components'] ?? [], JSON_UNESCAPED_UNICODE);
            $existe = query("SELECT tpl_nb_id FROM wa_template WHERE tpl_tx_nome = ? AND tpl_tx_idioma = ? LIMIT 1", "ss", [$nome, $idioma]);
            $row = $existe ? mysqli_fetch_assoc($existe) : null;
            if ($row) {
                wa_exec("UPDATE wa_template SET tpl_tx_categoria = ?, tpl_tx_status = ?, tpl_tx_componentes = ?, tpl_dt_atualizacao = NOW() WHERE tpl_nb_id = ?", "sssi", [$categoria, $status, $componentes, (int)$row['tpl_nb_id']]);
            } else {
                wa_exec("INSERT INTO wa_template (tpl_tx_nome, tpl_tx_idioma, tpl_tx_categoria, tpl_tx_status, tpl_tx_componentes, tpl_dt_atualizacao) VALUES (?, ?, ?, ?, ?, NOW())", "sssss", [$nome, $idioma, $categoria, $status, $componentes]);
            }
        }
        return ['success' => true, 'total' => count($templates), 'message' => count($templates) . ' template(s) sincronizado(s).'];
    }
}

if (!function_exists('wa_tipo_mensagem')) {
    function wa_tipo_mensagem(string $tipo): string {
        return match (strtolower($tipo)) {
            'text', 'texto', 'conversation', 'extendedtextmessage' => 'texto',
            'image', 'imagem', 'imagemessage' => 'imagem',
            'audio', 'audiomessage' => 'audio',
            'video', 'videomessage' => 'video',
            'document', 'documento', 'documentmessage' => 'documento',
            'template' => 'template',
            'sticker' => 'imagem',
            default => 'texto',
        };
    }
}

if (!function_exists('wa_status_provider')) {
    function wa_status_provider(string $status): string {
        $s = strtolower(trim($status));
        if (in_array($s, ['sent', 'enviado'], true)) {
            return 'enviado';
        }
        if (in_array($s, ['delivered', 'delivery_ack', 'entregue'], true)) {
            return 'entregue';
        }
        if (in_array($s, ['read', 'played', 'lido'], true)) {
            return 'lido';
        }
        if (in_array($s, ['failed', 'error', 'falhou'], true)) {
            return 'falhou';
        }
        return $s !== '' ? $s : 'enviado';
    }
}
