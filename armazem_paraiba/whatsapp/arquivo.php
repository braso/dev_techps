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
$caminho = $arquivo !== '' ? __DIR__ . '/arquivos/' . $arquivo : '';
if ($arquivo === '' || !is_file($caminho)) {
    http_response_code(404);
    echo 'Arquivo não encontrado.';
    exit;
}

if (!headers_sent()) {
    $nomeFinal = $nome !== '' ? $nome : $arquivo;
    $inline = !$download && (
        str_starts_with($mime, 'image/') ||
        str_starts_with($mime, 'audio/') ||
        str_starts_with($mime, 'video/')
    );
    header('Content-Type: ' . ($mime !== '' ? $mime : 'application/octet-stream'));
    header('Content-Length: ' . filesize($caminho));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($nomeFinal) . '"');
    header('Cache-Control: private, max-age=0, no-store');
    header('X-Content-Type-Options: nosniff');
}
readfile($caminho);
exit;
