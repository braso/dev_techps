<?php

/**
 * Testes básicos de validação e armazenamento de mídia.
 * Uso: php whatsapp/testes/teste_midia.php
 */

define('WA_MODULE_DIR', dirname(__DIR__));
require_once __DIR__ . '/../lib/midia.php';

$falhas = 0;
$ok = 0;

function teste(string $nome, bool $condicao): void {
    global $falhas, $ok;
    if ($condicao) {
        $ok++;
        echo "[OK] {$nome}\n";
        return;
    }
    $falhas++;
    echo "[FALHA] {$nome}\n";
}

teste('detecta imagem PNG', wa_midia_tipo_por_mime('image/png') === 'imagem');
teste('detecta áudio OGG', wa_midia_tipo_por_mime('audio/ogg') === 'audio');
teste('detecta PDF como documento', wa_midia_tipo_por_mime('application/pdf') === 'documento');
teste('rejeita SVG', wa_midia_tipo_por_mime('image/svg+xml') === null);
teste('limite de imagem é 5 MB', wa_midia_limite_bytes('imagem') === 5 * 1024 * 1024);
teste('limite de documento usa o global', wa_midia_limite_bytes('documento') === 16 * 1024 * 1024);

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
$erro = null;
$midia = wa_midia_gravar_arquivo($png, 'image/png', 'foto teste.png', $erro);
teste('grava imagem', is_array($midia) && $erro === null);
teste('mantém tipo imagem', is_array($midia) && $midia['tipo'] === 'imagem');
teste('gera extensão png', is_array($midia) && str_ends_with($midia['arquivo'], '.png'));
teste('sanitiza nome original', is_array($midia) && $midia['nome'] === 'foto teste.png');
if (is_array($midia)) {
    $caminho = wa_midia_pasta() . '/' . $midia['arquivo'];
    teste('arquivo existe no disco', is_file($caminho));
    @unlink($caminho);
}

$tmp = tempnam(sys_get_temp_dir(), 'wa_midia_');
file_put_contents($tmp, $png);
$erro = null;
$upload = wa_midia_salvar_upload([
    'error' => UPLOAD_ERR_OK,
    'tmp_name' => $tmp,
    'name' => 'foto upload.png',
    'size' => strlen($png),
], $erro);
teste('valida e grava upload', is_array($upload) && $erro === null);
if (is_array($upload)) {
    @unlink(wa_midia_pasta() . '/' . $upload['arquivo']);
}
@unlink($tmp);

$tmpSvg = tempnam(sys_get_temp_dir(), 'wa_svg_');
file_put_contents($tmpSvg, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
teste('não disfarça SVG com extensão PNG', wa_midia_detectar_mime($tmpSvg, 'foto.png') === 'image/svg+xml');
@unlink($tmpSvg);

$tmpM4a = tempnam(sys_get_temp_dir(), 'wa_m4a_');
file_put_contents($tmpM4a, "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom");
teste('reconhece M4A como áudio', wa_midia_detectar_mime($tmpM4a, 'audio-gravado.m4a') === 'audio/mp4');
@unlink($tmpM4a);

$erro = null;
$invalido = wa_midia_gravar_arquivo('<svg></svg>', 'image/svg+xml', 'x.svg', $erro);
teste('rejeita SVG no armazenamento', $invalido === null && $erro !== null);

$erro = null;
$texto = wa_midia_gravar_arquivo("linha 1\nlinha 2", 'text/plain', 'relatorio.txt', $erro);
teste('grava documento de texto', is_array($texto) && $texto['tipo'] === 'documento');
if (is_array($texto)) {
    @unlink(wa_midia_pasta() . '/' . $texto['arquivo']);
}

$nome = wa_midia_nome_seguro('../../etc/passwd', 'application/pdf');
teste('remove caminho e completa extensão', $nome === 'passwd.pdf');

echo "\n{$ok} teste(s) OK, {$falhas} falha(s).\n";
exit($falhas > 0 ? 1 : 0);
