<?php
require_once __DIR__ . '/../lib/provider/ProviderFactory.php';

$falhas = 0;
function verificar($condicao, string $descricao): void {
    global $falhas;
    if ($condicao) {
        echo "OK  {$descricao}\n";
    } else {
        $falhas++;
        echo "FALHA {$descricao}\n";
    }
}

$cloudCfg = [
    'cfg_tx_provider' => 'cloud',
    'cfg_tx_token' => 'token',
    'cfg_tx_phone_number_id' => '123',
    'cfg_tx_waba_id' => '456',
    'cfg_tx_app_secret' => 'segredo',
];
$cloud = new CloudApiProvider($cloudCfg);

$payloadCloud = [
    'object' => 'whatsapp_business_account',
    'entry' => [[
        'id' => '123',
        'changes' => [[
            'value' => [
                'contacts' => [['profile' => ['name' => 'Maria Teste']]],
                'messages' => [[
                    'id' => 'wamid.1',
                    'from' => '5511999999999',
                    'type' => 'text',
                    'text' => ['body' => 'Olá'],
                    'timestamp' => '1700000000',
                ]],
                'statuses' => [[
                    'id' => 'wamid.2',
                    'status' => 'delivered',
                    'recipient_id' => '5511999999999',
                    'timestamp' => '1700000001',
                ]],
            ],
        ]],
    ]],
];

$eventos = $cloud->parseWebhook($payloadCloud);
verificar(count($eventos) === 2, 'Cloud parseWebhook retorna mensagem e status');
verificar(($eventos[0]['tipo'] ?? '') === 'mensagem' && ($eventos[0]['from'] ?? '') === '5511999999999', 'Cloud identifica mensagem recebida');
verificar(($eventos[1]['status'] ?? '') === 'entregue', 'Cloud mapeia status delivered para entregue');

$payloadCloudImagem = [
    'entry' => [[
        'changes' => [[
            'value' => [
                'contacts' => [['profile' => ['name' => 'Maria Teste']]],
                'messages' => [[
                    'id' => 'wamid.3',
                    'from' => '5511999999999',
                    'type' => 'image',
                    'image' => ['id' => 'media-123', 'mime_type' => 'image/jpeg', 'caption' => 'Foto'],
                    'timestamp' => '1700000002',
                ]],
            ],
        ]],
    ]],
];
$eventosImg = $cloud->parseWebhook($payloadCloudImagem);
verificar(($eventosImg[0]['tipo_msg'] ?? '') === 'imagem', 'Cloud identifica imagem');
verificar(($eventosImg[0]['media_id'] ?? '') === 'media-123', 'Cloud extrai media_id da imagem');
verificar(($eventosImg[0]['mime'] ?? '') === 'image/jpeg', 'Cloud extrai MIME da imagem');
verificar(($eventosImg[0]['texto'] ?? '') === 'Foto', 'Cloud extrai legenda da imagem');

$raw = json_encode($payloadCloud);
$assinatura = 'sha256=' . hash_hmac('sha256', $raw, 'segredo');
verificar($cloud->verifyWebhook(['X-Hub-Signature-256' => $assinatura], $raw), 'Cloud valida assinatura correta');
verificar(!$cloud->verifyWebhook(['X-Hub-Signature-256' => 'sha256=errado'], $raw), 'Cloud rejeita assinatura inválida');

$evolutionCfg = [
    'cfg_tx_provider' => 'evolution',
    'cfg_tx_base_url' => 'http://localhost',
    'cfg_tx_instance' => 'teste',
    'cfg_tx_token' => 'apikey-teste',
];
$evolution = new EvolutionProvider($evolutionCfg);
$payloadEvolution = [
    'event' => 'messages.upsert',
    'instance' => 'teste',
    'data' => [
        'key' => ['remoteJid' => '5511888888888@s.whatsapp.net', 'fromMe' => false, 'id' => 'EVO1'],
        'pushName' => 'João',
        'message' => ['conversation' => 'Bom dia'],
        'messageTimestamp' => 1700000000,
    ],
];
$eventosEvo = $evolution->parseWebhook($payloadEvolution);
verificar(count($eventosEvo) === 1 && ($eventosEvo[0]['from'] ?? '') === '5511888888888', 'Evolution identifica mensagem recebida');
verificar($evolution->verifyWebhook(['apikey' => 'apikey-teste'], '{}'), 'Evolution valida apikey');
verificar(!$evolution->verifyWebhook(['apikey' => 'outra'], '{}'), 'Evolution rejeita apikey inválida');

$payloadEvolutionImagem = [
    'event' => 'messages.upsert',
    'instance' => 'teste',
    'data' => [
        'key' => ['remoteJid' => '5511888888888@s.whatsapp.net', 'fromMe' => false, 'id' => 'EVO2'],
        'pushName' => 'João',
        'message' => [
            'imageMessage' => [
                'caption' => 'Comprovante',
                'mimetype' => 'image/jpeg',
                'url' => 'https://exemplo.test/midia.jpg',
                'mediaKey' => 'chave',
                'base64' => 'data:image/jpeg;base64,AAAA',
            ],
        ],
        'messageTimestamp' => 1700000001,
    ],
];
$eventosEvoImg = $evolution->parseWebhook($payloadEvolutionImagem);
verificar(($eventosEvoImg[0]['tipo_msg'] ?? '') === 'imagem', 'Evolution identifica imagem');
verificar(($eventosEvoImg[0]['media_url'] ?? '') === 'https://exemplo.test/midia.jpg', 'Evolution extrai URL da imagem');
verificar(($eventosEvoImg[0]['media_base64'] ?? '') !== '', 'Evolution extrai base64 da imagem');
verificar(($eventosEvoImg[0]['media_key']['id'] ?? '') === 'EVO2', 'Evolution preserva key da mensagem para download');

echo "\n" . ($falhas === 0 ? 'Todos os testes passaram.' : "{$falhas} teste(s) falharam.") . "\n";
exit($falhas === 0 ? 0 : 1);
