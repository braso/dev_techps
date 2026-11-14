<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';
$perguntas = [
    'Como consultar o espelho de ponto?',
    'O que significa a cor vermelha no espelho?',
    'Quais os efeitos do endosso parcial no banco de horas?',
    'O que acontece se o CNPJ da empresa for desativado?',
    'Como cadastrar um funcionário?',
    'O que é adicional noturno?',
];
foreach ($perguntas as $p) {
    echo $p . ' => ' . ia_escolher_modelo($p) . "\n";
}
