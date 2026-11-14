<?php

/**
 * Provedor Evolution API.
 * Os endpoints variam por versão; manter a versão usada documentada no README.
 */
class EvolutionProvider implements ProviderInterface {
    private string $baseUrl;
    private string $instance;
    private string $token;

    public function __construct(array $config) {
        $this->baseUrl = rtrim(strval($config['cfg_tx_base_url'] ?? ''), '/');
        $this->instance = strval($config['cfg_tx_instance'] ?? '');
        $this->token = strval($config['cfg_tx_token'] ?? '');
    }

    private function headers(): array {
        return ['apikey: ' . $this->token];
    }

    private function request(string $method, string $path, ?array $payload = null): array {
        if ($this->baseUrl === '' || $this->instance === '' || $this->token === '') {
            return ['success' => false, 'error' => 'Evolution API sem base_url, instance ou token configurado.'];
        }
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $res = WaHttpClient::json($method, $url, $this->headers(), $payload);
        if (!$res['ok']) {
            return ['success' => false, 'error' => $res['error'], 'data' => $res['data'] ?? null, 'status' => $res['status']];
        }
        return ['success' => true, 'data' => $res['data'] ?? []];
    }

    public function sendText(string $to, string $text): array {
        $res = $this->request('POST', 'message/sendText/' . rawurlencode($this->instance), [
            'number' => preg_replace('/\D+/', '', $to),
            'text' => $text,
        ]);
        if (!$res['success']) {
            return $res;
        }
        $id = $res['data']['key']['id'] ?? $res['data']['id'] ?? '';
        return ['success' => true, 'provider_id' => strval($id), 'data' => $res['data']];
    }

    public function sendMedia(string $to, string $filePath, string $mime, string $caption = '', string $fileName = ''): array {
        if (!is_file($filePath)) {
            return ['success' => false, 'error' => 'Arquivo de mídia não encontrado.'];
        }
        $conteudo = file_get_contents($filePath);
        if ($conteudo === false) {
            return ['success' => false, 'error' => 'Não foi possível ler o arquivo.'];
        }
        $tipo = 'document';
        if (str_starts_with($mime, 'image/')) {
            $tipo = 'image';
        } elseif (str_starts_with($mime, 'audio/')) {
            $tipo = 'audio';
        } elseif (str_starts_with($mime, 'video/')) {
            $tipo = 'video';
        }

        $res = $this->request('POST', 'message/sendMedia/' . rawurlencode($this->instance), [
            'number' => preg_replace('/\D+/', '', $to),
            'mediatype' => $tipo,
            'mimetype' => $mime,
            'caption' => $caption,
            'media' => base64_encode($conteudo),
            'fileName' => $fileName !== '' ? basename($fileName) : basename($filePath),
        ]);
        if (!$res['success']) {
            return $res;
        }
        $id = $res['data']['key']['id'] ?? $res['data']['id'] ?? '';
        return ['success' => true, 'provider_id' => strval($id), 'data' => $res['data']];
    }

    public function downloadMedia(array $media): array {
        // 1) O webhook pode já trazer o conteúdo em base64 (webhook_base64).
        $base64 = trim(strval($media['media_base64'] ?? ''));
        if ($base64 !== '') {
            return $this->decodificarBase64($base64, strval($media['mime'] ?? ''));
        }

        // 2) Tenta o endpoint da Evolution, que devolve o base64 da mensagem.
        $key = $media['media_key'] ?? [];
        if (is_array($key) && !empty($key['id'])) {
            $res = $this->request('POST', 'chat/getBase64FromMediaMessage/' . rawurlencode($this->instance), [
                'message' => ['key' => $key],
                'convertToMp4' => false,
            ]);
            if (!empty($res['success'])) {
                $retorno = $res['data']['base64'] ?? $res['data']['data']['base64'] ?? '';
                $mime = strval($res['data']['mimetype'] ?? $res['data']['mimeType'] ?? $media['mime'] ?? '');
                if (is_string($retorno) && $retorno !== '') {
                    return $this->decodificarBase64($retorno, $mime);
                }
            }
        }

        // 3) Fallback: URL de mídia devolvida pelo webhook. Só aceita
        // HTTP/HTTPS, bloqueia destinos privados/reservados (SSRF) e limita
        // o tamanho do download.
        $url = trim(strval($media['media_url'] ?? ''));
        if ($url !== '' && preg_match('#^https?://#i', $url)) {
            $hostBase = strval(parse_url($this->baseUrl, PHP_URL_HOST) ?? '');
            $limite = function_exists('wa_midia_limite_bytes') ? wa_midia_limite_bytes() : (25 * 1024 * 1024);
            $res = WaHttpClient::raw($url, $this->headers(), 45, $limite, [$hostBase]);
            if (!empty($res['ok']) && $res['body'] !== '') {
                return [
                    'success' => true,
                    'conteudo' => $res['body'],
                    'mime' => strval($media['mime'] ?? $res['mime'] ?? 'application/octet-stream'),
                    'nome' => strval($media['nome'] ?? ''),
                ];
            }
        }

        return ['success' => false, 'error' => 'Não foi possível obter a mídia da Evolution.'];
    }

    private function decodificarBase64(string $base64, string $mime = ''): array {
        $mimeDetectado = $mime;
        if (preg_match('#^data:([^;]+);base64,(.*)$#s', $base64, $m)) {
            $mimeDetectado = $mimeDetectado !== '' ? $mimeDetectado : $m[1];
            $base64 = $m[2];
        }
        $conteudo = base64_decode(preg_replace('/\s+/', '', $base64) ?? '', true);
        if ($conteudo === false || $conteudo === '') {
            return ['success' => false, 'error' => 'Base64 de mídia inválido.'];
        }
        $limite = function_exists('wa_midia_limite_bytes') ? wa_midia_limite_bytes() : (25 * 1024 * 1024);
        if (strlen($conteudo) > $limite) {
            return ['success' => false, 'error' => 'Mídia acima do limite permitido.'];
        }
        return [
            'success' => true,
            'conteudo' => $conteudo,
            'mime' => $mimeDetectado !== '' ? $mimeDetectado : 'application/octet-stream',
            'nome' => '',
        ];
    }

    public function sendTemplate(string $to, string $template, string $language, array $params = []): array {
        return ['success' => false, 'error' => 'Templates são suportados apenas pela Cloud API.'];
    }

    public function getCallPermission(string $to): array {
        return ['success' => false, 'error' => 'Chamadas oficiais exigem Cloud API.'];
    }

    public function sendCallPermissionRequest(string $to, string $text = ''): array {
        return ['success' => false, 'error' => 'Chamadas oficiais exigem Cloud API.'];
    }

    public function initiateCall(string $to, string $sdpOffer, string $opaque = ''): array {
        return ['success' => false, 'error' => 'A Evolution API não oferece chamadas oficiais pela Cloud API.'];
    }

    public function preAcceptCall(string $callId, string $sdpAnswer): array {
        return ['success' => false, 'error' => 'Chamadas oficiais exigem Cloud API.'];
    }

    public function acceptCall(string $callId, string $sdpAnswer): array {
        return ['success' => false, 'error' => 'Chamadas oficiais exigem Cloud API.'];
    }

    public function rejectCall(string $callId): array {
        return ['success' => false, 'error' => 'Chamadas oficiais exigem Cloud API.'];
    }

    public function terminateCall(string $callId): array {
        return ['success' => false, 'error' => 'Chamadas oficiais exigem Cloud API.'];
    }

    public function setCallingStatus(bool $enabled): array {
        return ['success' => false, 'error' => 'Chamadas oficiais exigem Cloud API.'];
    }

    public function markRead(string $providerMessageId): void {
        // A Evolution aceita marcar como lido por remoteJid + id. Sem o remoteJid
        // completo, não é possível garantir o formato; a leitura local já é registrada.
        if ($providerMessageId === '') {
            return;
        }
        $this->request('POST', 'chat/markMessageAsRead/' . rawurlencode($this->instance), [
            'readMessages' => [['id' => $providerMessageId]],
        ]);
    }

    public function verifyWebhook(array $headers, string $rawBody): bool {
        if ($this->token === '') {
            return false;
        }
        $apiKeyHeader = '';
        foreach ($headers as $chave => $valor) {
            if (strtolower(str_replace('_', '-', $chave)) === 'apikey') {
                $apiKeyHeader = strval($valor);
                break;
            }
        }
        $tokenQuery = isset($_GET['token']) ? strval($_GET['token']) : '';

        // A Evolution pode enviar o apikey da instância no header e o token
        // global na URL. Aceita qualquer um dos dois quando coincidir.
        $headerOk = $apiKeyHeader !== '' && hash_equals($this->token, $apiKeyHeader);
        $queryOk = $tokenQuery !== '' && hash_equals($this->token, $tokenQuery);
        return $headerOk || $queryOk;
    }

    public function parseWebhook(array $payload): array {
        $eventos = [];
        $evento = strtolower(strval($payload['event'] ?? ''));
        $data = $payload['data'] ?? [];

        if (str_contains($evento, 'messages.upsert')) {
            $lista = isset($data[0]) && is_array($data[0]) ? $data : [$data];
            foreach ($lista as $msg) {
                if (!is_array($msg)) {
                    continue;
                }
                $key = $msg['key'] ?? [];
                $remoteJid = strval($key['remoteJid'] ?? '');
                $fromMe = !empty($key['fromMe']);
                $telefone = explode('@', $remoteJid)[0] ?? '';
                $texto = '';
                $tipo = 'texto';
                $mediaId = '';
                $mime = '';
                $mediaUrl = '';
                $mediaBase64 = '';
                $fileName = '';
                $message = $msg['message'] ?? [];

                // Grupos e listas de transmissão ficam fora do inbox individual.
                if ($remoteJid === '' || str_contains($remoteJid, '@g.us') || str_contains($remoteJid, 'broadcast')) {
                    continue;
                }

                if (isset($message['conversation'])) {
                    $texto = strval($message['conversation']);
                } elseif (isset($message['extendedTextMessage']['text'])) {
                    $texto = strval($message['extendedTextMessage']['text']);
                } elseif (isset($message['imageMessage'])) {
                    $tipo = 'imagem';
                    $texto = strval($message['imageMessage']['caption'] ?? '');
                    $mediaId = strval($message['imageMessage']['mediaKey'] ?? '');
                    $mime = strval($message['imageMessage']['mimetype'] ?? '');
                    $mediaUrl = strval($message['imageMessage']['url'] ?? '');
                } elseif (isset($message['audioMessage'])) {
                    $tipo = 'audio';
                    $mediaId = strval($message['audioMessage']['mediaKey'] ?? '');
                    $mime = strval($message['audioMessage']['mimetype'] ?? '');
                    $mediaUrl = strval($message['audioMessage']['url'] ?? '');
                } elseif (isset($message['videoMessage'])) {
                    $tipo = 'video';
                    $texto = strval($message['videoMessage']['caption'] ?? '');
                    $mediaId = strval($message['videoMessage']['mediaKey'] ?? '');
                    $mime = strval($message['videoMessage']['mimetype'] ?? '');
                    $mediaUrl = strval($message['videoMessage']['url'] ?? '');
                } elseif (isset($message['documentMessage']) || isset($message['documentWithCaptionMessage']['message']['documentMessage'])) {
                    $tipo = 'documento';
                    $blocoDocumento = $message['documentMessage'] ?? ($message['documentWithCaptionMessage']['message']['documentMessage'] ?? []);
                    $texto = strval($blocoDocumento['caption'] ?? '');
                    $fileName = strval($blocoDocumento['fileName'] ?? '');
                    if ($texto === '') {
                        $texto = $fileName;
                    }
                    $mediaId = strval($blocoDocumento['mediaKey'] ?? '');
                    $mime = strval($blocoDocumento['mimetype'] ?? '');
                    $mediaUrl = strval($blocoDocumento['url'] ?? '');
                } elseif (isset($message['buttonsResponseMessage']['selectedDisplayText'])) {
                    $texto = strval($message['buttonsResponseMessage']['selectedDisplayText']);
                } elseif (isset($message['listResponseMessage']['title'])) {
                    $texto = strval($message['listResponseMessage']['title']);
                }

                // Algumas versões devolvem a URL fora do bloco da mensagem.
                $mediaUrl = $mediaUrl !== '' ? $mediaUrl : strval($msg['mediaUrl'] ?? '');

                // Algumas versões da Evolution entregam a mídia em base64 no
                // próprio webhook; outras exigem buscar pelo key da mensagem.
                $blocoMidia = match ($tipo) {
                    'imagem' => $message['imageMessage'] ?? [],
                    'audio' => $message['audioMessage'] ?? [],
                    'video' => $message['videoMessage'] ?? [],
                    'documento' => $message['documentMessage'] ?? ($message['documentWithCaptionMessage']['message']['documentMessage'] ?? []),
                    default => [],
                };
                $mediaBase64 = strval($msg['base64'] ?? ($message['base64'] ?? ($blocoMidia['base64'] ?? '')));

                $eventos[] = [
                    'tipo' => 'mensagem',
                    'direcao' => $fromMe ? 'out' : 'in',
                    'provider_id' => strval($key['id'] ?? ''),
                    'from' => $telefone,
                    'nome' => strval($msg['pushName'] ?? ''),
                    'tipo_msg' => $tipo,
                    'texto' => $texto,
                    'media_id' => $mediaId,
                    'media_url' => $mediaUrl,
                    'media_base64' => $mediaBase64,
                    'media_key' => $key,
                    'mime' => $mime,
                    'filename' => $fileName,
                    'timestamp' => intval($msg['messageTimestamp'] ?? time()),
                ];
            }
        } elseif (str_contains($evento, 'messages.update')) {
            $lista = isset($data[0]) && is_array($data[0]) ? $data : [$data];
            foreach ($lista as $upd) {
                $keyId = strval($upd['keyId'] ?? $upd['key']['id'] ?? '');
                $status = strval($upd['status'] ?? $upd['update']['status'] ?? '');
                if ($keyId === '' || $status === '') {
                    continue;
                }
                $eventos[] = [
                    'tipo' => 'status',
                    'provider_id' => $keyId,
                    'status' => wa_status_provider($status),
                    'timestamp' => time(),
                ];
            }
        }

        return $eventos;
    }

    public function testConnection(): array {
        if ($this->baseUrl === '' || $this->instance === '' || $this->token === '') {
            return ['success' => false, 'message' => 'Informe base_url, instance e api_key.'];
        }
        $res = WaHttpClient::json('GET', $this->baseUrl . '/instance/connectionState/' . rawurlencode($this->instance), $this->headers());
        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Falha na conexão: ' . $res['error']];
        }
        $estado = strval($res['data']['instance']['state'] ?? $res['data']['state'] ?? '');
        $aberto = in_array(strtolower($estado), ['open', 'connected', 'conectado'], true);
        return [
            'success' => true,
            'message' => $aberto ? 'Instância conectada.' : ('Instância encontrada, estado: ' . ($estado !== '' ? $estado : 'desconhecido') . '.'),
            'state' => $estado,
        ];
    }

    public function getQrCode(): array {
        if ($this->baseUrl === '' || $this->instance === '' || $this->token === '') {
            return ['success' => false, 'message' => 'Informe base_url, instance e api_key.'];
        }
        $res = WaHttpClient::json('GET', $this->baseUrl . '/instance/connect/' . rawurlencode($this->instance), $this->headers());
        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Não foi possível obter o QR Code: ' . $res['error']];
        }
        $base64 = strval($res['data']['base64'] ?? '');
        $code = strval($res['data']['code'] ?? '');
        return ['success' => true, 'base64' => $base64, 'code' => $code, 'message' => 'QR Code gerado.'];
    }

    public function getConnectionState(): array {
        $teste = $this->testConnection();
        return ['success' => $teste['success'], 'state' => $teste['state'] ?? '', 'message' => $teste['message']];
    }

    public function getTemplates(): array {
        return ['success' => false, 'message' => 'Templates são suportados apenas pela Cloud API.'];
    }
}
