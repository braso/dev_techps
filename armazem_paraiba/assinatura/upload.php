<?php
header('Content-Type: application/json');

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
    $file = $_FILES['arquivo'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Erro no upload do arquivo.']);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        echo json_encode(['status' => 'error', 'message' => 'Apenas arquivos PDF são permitidos.']);
        exit;
    }

    // Gera nome único
    $filename = uniqid('doc_') . '.pdf';
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode([
            'status' => 'success',
            'url' => 'uploads/' . $filename,
            'path' => $filename, // Caminho relativo dentro de uploads/
            'id_temp' => 'UPLOAD-' . uniqid()
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Falha ao mover arquivo para pasta de destino.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Nenhum arquivo enviado.']);
}
?>
