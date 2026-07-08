<?php
$arquivo = __DIR__ . '/arquivos/instrucoesPoi/modelo_importacao_poi.csv';
if (!file_exists($arquivo)) {
    http_response_code(404);
    echo "Arquivo não encontrado.";
    exit;
}
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="modelo_importacao_poi.csv"');
header('Content-Length: ' . filesize($arquivo));
readfile($arquivo);
exit;
