<?php
header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'message' => 'Servidor respondendo',
    'timestamp' => date('Y-m-d H:i:s')
]);
