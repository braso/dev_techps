<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$base = 'http://127.0.0.1/braso/armazem_paraiba/assistente_virtual';
$cookie = 'PHPSESSID=iatest';
$tmp = '/tmp/ia_teste_upload.md';
file_put_contents($tmp, "# Teste de Upload\n\n## Como testar o upload?\n\nEste é um documento de teste do assistente.\n\nFonte: teste automatizado.\n");

function ia_get(string $url, string $cookie): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIE => $cookie, CURLOPT_TIMEOUT => 30]);
    $r = curl_exec($ch);
    curl_close($ch);
    return strval($r);
}

$html = ia_get($base . '/admin.php', $cookie);
if (!preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m)) {
    echo "Sem token.\n";
    exit(1);
}
$token = $m[1];
$ch = curl_init($base . '/admin.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIE => $cookie,
    CURLOPT_POSTFIELDS => [
        'csrf' => $token,
        'ia_acao' => 'upload',
        'titulo' => 'Teste de Upload',
        'modulo' => 'teste',
        'menu_path' => '/index.php',
        'perfis' => 'todos',
        'owner' => 'Teste',
        'versao' => '1.0',
        'arquivo' => new CURLFile($tmp, 'text/markdown', 'teste_upload.md'),
    ],
    CURLOPT_TIMEOUT => 60,
]);
$resp = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP {$http}\n";
if (preg_match('/<div class="ia-aviso[^"]*">(.*?)<\/div>/s', strval($resp), $mm)) {
    echo trim(strip_tags($mm[1])) . "\n";
} else {
    echo substr(strip_tags(strval($resp)), 0, 500) . "\n";
}
