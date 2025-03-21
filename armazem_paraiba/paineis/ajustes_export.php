<?php
require_once __DIR__ . "/../tcpdf/tcpdf.php"; // Inclui a biblioteca TCPDF

function gerarPDF($data) {
    // Verificar se os dados foram recebidos corretamente
    if (!$data || (!isset($data['motivo']) && !isset($data['motivos']))) {
        return json_encode(['error' => 'Dados inválidos']);
    }

    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Resumo de Ajustes de Ponto', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);

    if (isset($data['motivo'])) {
        $pdf->Ln(5);
        $pdf->Cell(0, 10, 'Motivo: ' . $data['motivo'], 1, 1, 'C', 0);
        $pdf->SetFont('helvetica', '', 12);
        foreach ($data['pontos'] as $nome => $quantidade) {
            $pdf->Ln(5);
            $pdf->Cell(0, 10, $nome . ' - Quantidade Ajustes: ' . $quantidade, 0, 1);
        }
    } elseif (isset($data['motivos'])) {
        foreach ($data['motivos'] as $motivo => $pontos) {
            $pdf->Ln(5);
            $pdf->Cell(0, 10, 'Motivo: ' . $motivo, 1, 1, 'C', 0);
            $pdf->SetFont('helvetica', '', 12);
            foreach ($pontos as $nome => $quantidade) {
                $pdf->Ln(5);
                $pdf->Cell(0, 10, $nome . ' - Quantidade Ajustes: ' . $quantidade, 0, 1);
            }
        }
    }

    $dirPath = __DIR__ . '/arquivos/ajustes/pdf/';
    if (!file_exists($dirPath)) {
        mkdir($dirPath, 0777, true);
    }
    date_default_timezone_set('America/Sao_Paulo');
    $fileName = 'ajustes_' . date('d-m-Y_H-i-s') . '.pdf';
    $filePath = $dirPath . $fileName;
    $pdf->Output($filePath, 'F');

    return json_encode(['file' => './arquivos/ajustes/pdf/' . $fileName]);
}

function gerarCSV($data) {
    if (!$data || (!isset($data['motivo']) && !isset($data['motivos']))) {
        return json_encode(['error' => 'Dados inválidos']);
    }

    $dirPath = __DIR__ . '/arquivos/ajustes/csv/';
    if (!file_exists($dirPath)) {
        mkdir($dirPath, 0777, true);
    }
    date_default_timezone_set('America/Sao_Paulo');
    $fileName = 'ajustes_' . date('d-m-Y_H-i-s') . '.csv';
    $filePath = $dirPath . $fileName;
    
    $file = fopen($filePath, 'w');
    fputcsv($file, ['Motivo', 'Funcionário', 'Quantidade Ajustes']);

    if (isset($data['motivo'])) {
        foreach ($data['pontos'] as $nome => $quantidade) {
            fputcsv($file, [$data['motivo'], $nome, $quantidade]);
        }
    } elseif (isset($data['motivos'])) {
        foreach ($data['motivos'] as $motivo => $pontos) {
            foreach ($pontos as $nome => $quantidade) {
                fputcsv($file, [$motivo, $nome, $quantidade]);
            }
        }
    }
    fclose($file);
    
    return json_encode(['file' => './arquivos/ajustes/csv/' . $fileName]);
}

// Obter os dados JSON do corpo da requisição
$data = json_decode(file_get_contents('php://input'), true);
$tipo = $_GET['tipo'] ?? 'pdf';

echo $tipo === 'csv' ? gerarCSV($data) : gerarPDF($data);
exit;
