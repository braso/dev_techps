<?php
require_once __DIR__ . '/bootstrap.php';

verificaPermissao('/whatsapp/index.php');

$anexoId = (int)($_GET['anexo'] ?? 0);
$msgId = (int)($_GET['msg'] ?? 0);
$download = !empty($_GET['download']);
$arquivo = '';
$nome = '';
$mime = 'application/octet-stream';

if ($anexoId > 0) {
    $rs = query("SELECT * FROM wa_anexo WHERE anx_nb_id = ? LIMIT 1", "i", [$anexoId]);
    $row = $rs ? mysqli_fetch_assoc($rs) : null;
    if ($row) {
        $arquivo = strval($row['anx_tx_arquivo'] ?? '');
        $nome = strval($row['anx_tx_nome'] ?? basename($arquivo));
        $mime = strval($row['anx_tx_mime'] ?? $mime);
    }
} elseif ($msgId > 0) {
    $rsAnexo = query("SELECT * FROM wa_anexo WHERE anx_nb_msg = ? ORDER BY anx_nb_id DESC LIMIT 1", "i", [$msgId]);
    $anexo = $rsAnexo ? mysqli_fetch_assoc($rsAnexo) : null;
    if ($anexo) {
        $arquivo = strval($anexo['anx_tx_arquivo'] ?? '');
        $nome = strval($anexo['anx_tx_nome'] ?? basename($arquivo));
        $mime = strval($anexo['anx_tx_mime'] ?? $mime);
    } else {
        $rs = query("SELECT * FROM wa_mensagem WHERE msg_nb_id = ? LIMIT 1", "i", [$msgId]);
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        if ($row) {
            $arquivo = strval($row['msg_tx_arquivo'] ?? '');
            $nome = basename($arquivo);
            $mime = strval($row['msg_tx_mime'] ?? $mime);
        }
    }
}

$arquivo = basename($arquivo);
$caminho = $arquivo !== '' ? wa_midia_pasta() . '/' . $arquivo : '';
if ($arquivo === '' || !is_file($caminho)) {
    http_response_code(404);
    echo 'Arquivo não encontrado.';
    exit;
}

if (!headers_sent()) {
    $nomeFinal = $nome !== '' ? $nome : $arquivo;
    $mimeBase = strtolower(trim(explode(';', $mime)[0]));
    $tipoPermitido = function_exists('wa_midia_tipo_por_mime')
        ? wa_midia_tipo_por_mime($mimeBase)
        : null;
    if ($tipoPermitido === null) {
        $mimeBase = 'application/octet-stream';
    }
    $inline = !$download && $tipoPermitido !== null && in_array($tipoPermitido, ['imagem', 'audio', 'video'], true);
    header('Content-Type: ' . $mimeBase);
    header('Content-Length: ' . filesize($caminho));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($nomeFinal) . '"');
    header('Cache-Control: private, max-age=0, no-store');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'");
}
readfile($caminho);
exit;
