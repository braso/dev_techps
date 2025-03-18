<?php
require_once __DIR__ . "/../tcpdf/tcpdf.php"; // Inclui a biblioteca TCPDF

// Obter os dados JSON do corpo da requisição
$data = json_decode(file_get_contents('php://input'), true);

// Verificar se os dados foram recebidos corretamente
if (!$data || (!isset($data['motivo']) && !isset($data['motivos']))) {
    // Caso os dados não sejam válidos, retorna um erro
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

// Criar o PDF com a biblioteca TCPDF
$pdf = new TCPDF();
$pdf->AddPage();

// Definir o título
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Resumo de Ajustes de Ponto', 0, 1, 'C');

// Definir a fonte para o conteúdo
$pdf->SetFont('helvetica', '', 12);

// Se for um único motivo
if (isset($data['motivo'])) {
    $motivo = $data['motivo'];
    $pontos = $data['pontos'];
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'Motivo: ' . $motivo, 1, 1, 'C', 0);
    $pdf->SetFont('helvetica', '', 12);
    foreach ($pontos as $nome => $quantidade) {
        $pdf->Ln(5);
        $pdf->Cell(0, 10, $nome . ' - Quantidade Ajustes: ' . $quantidade, 0, 1);
    }
}
// Se for múltiplos motivos
elseif (isset($data['motivos'])) {
    foreach ($data['motivos'] as $motivo => $pontos) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Ln(5);
        $pdf->Cell(0, 10, 'Motivo: ' . $motivo, 1, 1, 'C', 0);
        $pdf->SetFont('helvetica', '', 12);
        foreach ($pontos as $nome => $quantidade) {
            $pdf->Ln(5);
            $pdf->Cell(0, 10, $nome . ' - Quantidade Ajustes: ' . $quantidade, 0, 1);
        }
    }
}

// Salvar o PDF
$dirPath = __DIR__ . '/arquivos/ajustes/pdf/';
if (!file_exists($dirPath)) {
    mkdir($dirPath, 0777, true);
}
$fileName = 'ajustes_' . time() . '.pdf';
$filePath = $dirPath . $fileName;
$pdf->Output($filePath, 'F');

// Retornar o caminho do PDF para o AJAX
echo json_encode(['file' => './arquivos/ajustes/pdf/' . $fileName]);
exit;
?>
