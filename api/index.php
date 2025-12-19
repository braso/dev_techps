<?php

ini_set("display_errors", 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/core/Env.php';
require_once __DIR__ . '/core/Logger.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/DeviceService.php';

Env::load(__DIR__ . '/.env');

$databases = require __DIR__ . '/config/databases.php';

$input = json_decode(file_get_contents('php://input'), true);

// 🔴 Validações obrigatórias
if (empty($input['empresa'])) {
    Logger::error('empresa ausente');
    http_response_code(400);
    echo json_encode(['erro' => 'empresa obrigatória']);
    exit;
}

if (empty($input['device_id'])) {
    Logger::error('device_id ausente');
    http_response_code(400);
    echo json_encode(['erro' => 'device_id obrigatório']);
    exit;
}

// 🔹 Campos opcionais
$marcaModelo   = $input['marca_modelo']   ?? null;
$versaoAndroid = $input['versao_android'] ?? null;
$nomeDispositivo = $input['matricula'] ?? null;

try {
    $service = new DeviceService($databases);

    $response = $service->sync(
        $input['empresa'],
        $input['device_id'],
        $nomeDispositivo,
        $marcaModelo,
        $versaoAndroid
    );

    echo json_encode($response);

} catch (Throwable $e) {
    Logger::error('Erro na requisição', [
        'error' => $e->getMessage()
    ]);

    http_response_code(400);
    echo json_encode([
        'erro' => $e->getMessage()
    ]);
}
