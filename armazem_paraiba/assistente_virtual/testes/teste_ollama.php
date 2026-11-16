<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';
$modelo = $argv[1] ?? null;
$r = ia_ollama_chat([
    ['role' => 'system', 'content' => 'Responda em português.'],
    ['role' => 'user', 'content' => 'Responda apenas: ok'],
], false, null, $modelo);
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
