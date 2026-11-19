<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';
var_dump($_ENV['ASSISTENTE_PROVIDER'] ?? null, ia_settings()['provider'], ia_llm_ativo());
