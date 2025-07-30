<?php
/* Modo debug{
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//}*/

require_once __DIR__ . "/tcpdf/tcpdf.php";
require __DIR__."/funcoes_ponto.php";

$motorista = carregar("entidade", $_POST["id"]);
dd($motorista);

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema');
$pdf->SetTitle('Ficha de Cadastro de Funcionário');

// Margens (esquerda, topo, direita)
$pdf->SetMargins(15, 20, 15);
$pdf->AddPage();

// --- FOTO ---
$fotoPath = $motorista["enti_tx_foto"]?? ""; // Caminho da imagem da foto
$larguraFoto = 30;
$alturaFoto = 40;
$posX = 165;  // mais à direita para evitar sobreposição
$posY = 12;

if (file_exists($fotoPath)) {
    $pdf->Image($fotoPath, $posX, $posY, $larguraFoto, $alturaFoto, '', '', '', true);
} else {
    $pdf->Rect($posX, $posY, $larguraFoto, $alturaFoto);
    $pdf->SetXY($posX, $posY + ($alturaFoto / 2) - 2.5);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell($larguraFoto, 5, 'FOTO', 0, 0, 'C');
}

// --- TÍTULO CENTRAL ---
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetXY(15, 25); // posição no canto superior esquerdo
$pdf->Cell(140, 10, 'FICHA DE CADASTRO DE FUNCIONÁRIO', 0, 1, 'L');
$pdf->Ln(10);

// Função: título de seção
function tituloSecaoFicha($pdf, $texto) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 7, $texto, 'B', 1, 'L');
    $pdf->Ln(1);
    $pdf->SetFont('helvetica', '', 11);
}

// Função: campo
function campoFicha($pdf, $label, $valor, $labelWidth = 45) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell($labelWidth, 6, $label . ':', 0, 0, 'L');
    $pdf->Ln(4);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, $valor, 0, 1, 'L');
}

// --- DADOS PESSOAIS ---
tituloSecaoFicha($pdf, 'DADOS PESSOAIS');
campoFicha($pdf, 'Nome', $motorista['enti_tx_nome']);
campoFicha($pdf, 'E-mail', $motorista['enti_tx_email']);
campoFicha($pdf, 'Telefone', $motorista['enti_tx_fone1']);
campoFicha($pdf, 'Matrícula', $motorista);
campoFicha($pdf, 'Nascimento', $motorista);
campoFicha($pdf, 'CPF', $motorista);
campoFicha($pdf, 'RG', $motorista);
campoFicha($pdf, 'Estado Civil', $motorista);
campoFicha($pdf, 'Sexo', $motorista);
campoFicha($pdf, 'Endereço', $motorista);
campoFicha($pdf, 'CEP', $motorista);

$pdf->Ln(3);

// --- DADOS CONTRATUAIS ---
tituloSecaoFicha($pdf, 'DADOS CONTRATUAIS');
campoFicha($pdf, 'Empresa', 'FRUTICANA PRODUCAO, COMERCIO, IMPORTACAO E EXPORTACAO LTDA');
campoFicha($pdf, 'Ocupação', 'Motorista');
campoFicha($pdf, 'Data de Admissão', '02/02/2021');
campoFicha($pdf, 'Subcontratado', 'Não');

$pdf->Ln(3);

// --- CNH ---
tituloSecaoFicha($pdf, 'CARTEIRA NACIONAL DE HABILITAÇÃO');
campoFicha($pdf, 'Nº Registro', '5793020765');
campoFicha($pdf, 'Categoria', 'AD');
campoFicha($pdf, 'Emissão', '26/07/2023');
campoFicha($pdf, 'Validade', '18/07/2033');
campoFicha($pdf, '1ª Habilitação', '04/06/2013');
campoFicha($pdf, 'Atividade Remunerada (EAR)', 'Sim');
campoFicha($pdf, 'Cidade/UF', 'Recife/PE');

$pdf->Ln(3);

// --- JORNADA E SINDICATO ---
tituloSecaoFicha($pdf, 'JORNADA E SINDICATO');
campoFicha($pdf, 'Sindicato', 'SINDICATO DOS CONDUTORES EM TRANSPORTES RODOVIARIOS DE CARGAS PROPRIAS DO ESTADO DA PARAIBA');
campoFicha($pdf, 'Jornada Semanal', '07:20 horas/dia');
campoFicha($pdf, 'Jornada Sábado', '07:20 horas/dia');
campoFicha($pdf, 'Hora Extra Semanal (%)', '50%');
campoFicha($pdf, 'Hora Extra Extraordinária (%)', '100%');
campoFicha($pdf, 'Convenção Padrão', 'Sim');

$pdf->Ln(3);

// --- HISTÓRICO ---
tituloSecaoFicha($pdf, 'HISTÓRICO');
campoFicha($pdf, 'Data de Cadastro', '11/12/2023');
campoFicha($pdf, 'Última Atualização', '19/02/2024 às 16:50:49');

// Saída
$pdf->Output('ficha_cadastro.pdf', 'I');