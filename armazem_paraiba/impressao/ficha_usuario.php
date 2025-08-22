<?php

// Requerimentos essenciais
require_once __DIR__ . "./../tcpdf/tcpdf.php";
require_once __DIR__ . "./../funcoes_ponto.php";

// Carrega os dados da empresa e do usuário
$usuario = carregar("user", $_POST['id_usuario']);
$empresa = carregar("empresa", $usuario['user_nb_empresa']);
$cidade = carregar("cidade", $usuario['user_nb_cidade']);

// Checa se os dados foram encontrados
if (!$empresa || !$usuario) {
    die("Dados de empresa ou usuário não encontrados.");
}

function formatarData(?string $dataString): string {
    if (empty($dataString) || $dataString === '0000-00-00') {
        return '';
    }
    $timestamp = strtotime($dataString);
    return $timestamp ? date('d/m/Y', $timestamp) : '';
}

function formatarCPF(?string $cpf): string {
    $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf ?? '');
    if (strlen($cpfLimpo) != 11) {
        return $cpf ?? ''; // Retorna o original se não for um CPF válido
    }
    return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "$1.$2.$3-$4", $cpfLimpo);
}
/**
 * Classe personalizada para o PDF.
 * Mantém o cabeçalho e rodapé personalizados.
 */
class CustomPDF extends TCPDF {
    public string $tituloPersonalizado;
    protected $empresaData;

    public function __construct(array $empresaData, string $titulo = 'Relatório Sem Título', string $orientation = 'L', string $unit = 'mm', string $format = 'A4') {
        parent::__construct($orientation, $unit, $format, true, 'UTF-8', false);

        $this->empresaData = $empresaData;
        $this->tituloPersonalizado = $titulo;

        $this->SetCreator(PDF_CREATOR);
        $this->SetAuthor('Tech PS');
        $this->SetTitle($this->tituloPersonalizado);
        
        $this->SetMargins(15, 35, 15);
        $this->SetAutoPageBreak(true, 20);
    }

    public function Header() {
        $this->Image(__DIR__ . "/../imagens/logo_topo_cliente.png", 10, 10, 40, 10);
        $logoEmpresa = __DIR__ .'/../'.($this->empresaData["empr_tx_logo"] ?? 'default_logo.png');
        if (file_exists($logoEmpresa)) {
            $this->Image($logoEmpresa, $this->GetPageWidth() - 45, 10, 30, 15);
        }
        $this->SetY(15);
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, mb_strtoupper($this->tituloPersonalizado), 0, 1, 'C', false, '', 0, false, 'T', 'M');
    }

    public function Footer() {
        $this->SetY(-15);
        $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(90, 0, 'TECHPS®', 0, 0, 'L');
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(1, 0, 'Gerado em: ' . date('d/m/Y H:i'), 0, 0, 'C');
        parent::Footer();
    }
}

// Cria a instância do PDF
$pdf = new CustomPDF($empresa, 'Ficha do Usuário', "L", "mm", "A4");
$pdf->AddPage();

// --- DEFINIÇÕES DE LAYOUT E VARIÁVEIS ---
$margin_left = 15;
$margin_right = 15;
$page_width = $pdf->GetPageWidth();
$content_width = $page_width - $margin_left - $margin_right;
$line_height = 8;
$small_space = 2;

// Largura da coluna esquerda (container da foto)
$col_left_width = $content_width * 0.25;

// Largura da área de dados à direita
$data_area_width = $content_width - $col_left_width - 5;

// Larguras das sub-colunas de dados (divididas em 2)
$col_data_width = $data_area_width / 2;
$data_label_width = $col_data_width * 0.40;
$data_value_width = $col_data_width * 0.60;
$field_space = 5;

// Posição Y inicial para o conteúdo
$y_start_content = $pdf->GetY();
$pdf->SetY($y_start_content);

// --- SEÇÃO DA FOTO ---
$img_container_height = 90;
$pdf->SetX($margin_left);
$fotoPath = $usuario['user_tx_foto'] ?? null;
$larguraFoto = $col_left_width;
$alturaFoto = $img_container_height;
$posXFoto = $margin_left;
$posYFoto = $y_start_content;

// Desenha o container da foto
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect($posXFoto, $posYFoto, $larguraFoto, $alturaFoto, 'D');

// Renderiza a imagem do usuário ou o placeholder
if ($fotoPath && file_exists($fotoPath)) {
    // A função Image() ajusta a altura automaticamente se a largura for 0.
    $pdf->Image($fotoPath, $posXFoto + 2, $posYFoto + 2, $larguraFoto - 4, 0, '', '', '', false, 300, '', false, false, 0, true);
} else {
    $pdf->SetLineStyle(['width' => 0.2, 'dash' => '2,2']);
    $pdf->Rect($posXFoto, $posYFoto, $larguraFoto, $alturaFoto, 'D');
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetXY($posXFoto, $posYFoto + ($alturaFoto / 2) - 3);
    $pdf->Cell($larguraFoto, 6, 'FOTO', 0, 0, 'C');
    $pdf->SetFont('dejavusans', '', 9);
}

// --- SEÇÃO DOS DADOS ---
$pdf->SetY($y_start_content);

// Função auxiliar para criar campos de duas colunas com espaçamento ajustado
function createField($pdf, $label, $value, $column_width) {
    global $line_height;
    $label_text = $label.':';
    $pdf->SetFont('dejavusans', 'B', 9);
    $label_width = $pdf->GetStringWidth($label_text) + 2; // 2mm de padding
    
    $pdf->Cell($label_width, $line_height, $label_text, 0, 0, 'L');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell($column_width - $label_width, $line_height, $value, 'B', 0, 'L');
}

// Função para criar campos em linhas inteiras com espaçamento ajustado
function createFullRowField($pdf, $label, $value, $row_width) {
    global $line_height, $small_space;
    $label_text = $label.':';
    $pdf->SetFont('dejavusans', 'B', 9);
    $label_width = $pdf->GetStringWidth($label_text) + 2;
    
    $pdf->Cell($label_width, $line_height, $label_text, 0, 0, 'L');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->MultiCell($row_width - $label_width, $line_height, $value, 'B', 'L', 0, 1, '', '', true, 0, false, true, $line_height, 'T');
    $pdf->Ln($small_space);
}

// Posição de início da coluna de dados
$x_data_start = $margin_left + $col_left_width + $field_space;
$pdf->SetX($x_data_start);

// Linha 1: Nome (em uma linha dedicada)
createFullRowField($pdf, 'Nome*', $usuario['user_tx_nome'], $data_area_width);

// Definindo os pontos X para as duas colunas de dados
$x_col1_start = $x_data_start;
$x_col2_start = $x_col1_start + $col_data_width;

// Linha 2: Login e Nível
$pdf->SetX($x_col1_start);
createField($pdf, 'Login', $usuario['user_tx_login'], $col_data_width);
$pdf->SetX($x_col2_start);
createField($pdf, 'Nível*', $usuario['user_tx_nivel'], $col_data_width);
$pdf->Ln($line_height + $small_space);

// Linha 3: Matrícula e Nascido em
$pdf->SetX($x_col1_start);
createField($pdf, 'Matricula', $usuario['user_tx_matricula'], $col_data_width);
$pdf->SetX($x_col2_start);
createField($pdf, 'Nascido em*', formatarData($usuario['user_tx_nascimento']), $col_data_width);
$pdf->Ln($line_height + $small_space);

// Linha 4: CPF e RG
$pdf->SetX($x_col1_start);
createField($pdf, 'CPF', formatarCPF($usuario['user_tx_cpf']), $col_data_width);
$pdf->SetX($x_col2_start);
createField($pdf, 'RG', $usuario['user_tx_rg'], $col_data_width);
$pdf->Ln($line_height + $small_space);

// Linha 5: Cidade/UF e E-mail
$pdf->SetX($x_col1_start);
createField($pdf, 'Cidade/UF', $cidade["cida_tx_nome"]." [".$cidade["cida_tx_uf"]."]", $col_data_width);
$pdf->SetX($x_col2_start);
createField($pdf, 'E-mail*', $usuario['user_tx_email'], $col_data_width);
$pdf->Ln($line_height + $small_space);

// Linha 6: Telefone e Expira em
$pdf->SetX($x_col1_start);
createField($pdf, 'Telefone', $usuario['user_tx_fone'], $col_data_width);
$pdf->SetX($x_col2_start);
createField($pdf, 'Expira em', formatarData($usuario['user_tx_expiracao']), $col_data_width);
$pdf->Ln($line_height + $small_space);

// Linha 7: Empresa (em uma linha dedicada)
$pdf->SetX($x_data_start);
createFullRowField($pdf, 'Empresa*', $empresa['empr_tx_nome'], $data_area_width);

// Saída do PDF
$pdf->Output('ficha_usuario_' . $usuario['id'] . '.pdf', 'I');
exit;