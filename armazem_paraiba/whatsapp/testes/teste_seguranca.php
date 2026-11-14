<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
require_once __DIR__ . '/../lib/seguranca.php';
require_once __DIR__ . '/../lib/provider/HttpClient.php';

$_ENV['APP_KEY'] = $_ENV['APP_KEY'] ?? 'chave-de-teste-local';

$falhas = 0;
$texto = 'token-secreto-123';
try {
    $cifrado = wa_encrypt($texto);
    $decifrado = wa_decrypt($cifrado);
    $ok = $decifrado === $texto;
    echo ($ok ? 'OK ' : 'FALHA ') . "criptografia/descriptografia\n";
    if (!$ok) {
        $falhas++;
    }
} catch (Throwable $e) {
    $falhas++;
    echo 'FALHA criptografia: ' . $e->getMessage() . "\n";
}

$json = wa_json_script('</script><script>alert(1)</script>');
$okJson = str_contains($json, '\u003C') && !str_contains($json, '</script>');
echo ($okJson ? 'OK ' : 'FALHA ') . "JSON seguro para <script>\n";
if (!$okJson) {
    $falhas++;
}

$okHttps = wa_url_http_valida('https://graph.facebook.com/v21.0', true);
$rejeitaHttpCloud = !wa_url_http_valida('http://graph.facebook.com/v21.0', true);
$rejeitaFile = !wa_url_http_valida('file:///etc/passwd', false);
echo ($okHttps && $rejeitaHttpCloud && $rejeitaFile ? 'OK ' : 'FALHA ') . "validação de URL (HTTPS/protocolo)\n";
if (!$okHttps || !$rejeitaHttpCloud || !$rejeitaFile) {
    $falhas++;
}

$rawLoopback = WaHttpClient::raw('http://127.0.0.1:9/', [], 1);
$okSsrf = empty($rawLoopback['ok']);
echo ($okSsrf ? 'OK ' : 'FALHA ') . "bloqueio de SSRF em mídia privada\n";
if (!$okSsrf) {
    $falhas++;
}

echo "\n" . ($falhas === 0 ? 'Todos os testes passaram.' : "{$falhas} teste(s) falharam.") . "\n";
exit($falhas === 0 ? 0 : 1);
