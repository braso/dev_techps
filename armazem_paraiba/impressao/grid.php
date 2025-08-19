<?php

/*
 * MODO DEBUG
 * Em um ambiente de produção, é recomendado controlar isso através de uma variável de ambiente.
 */
/*
ini_set("display_errors", 1);
error_reporting(E_ALL);
//*/
require_once __DIR__ . "./../tcpdf/tcpdf.php";
require_once __DIR__ . "./../funcoes_ponto.php";

$empresa = carregar("empresa", $_POST['IdEmpresa']);

class CustomPDF extends TCPDF {
    public string $tituloPersonalizado;
    protected static $empresaData;

    public static function setEmpresaData($data) {
        self::$empresaData = $data;
    }

    /**
     * Construtor para inicializar o PDF com dados essenciais.
     * @param array $empresaData Dados da empresa (ex: ['empr_tx_logo' => 'path/logo.png'])
     * @param string $titulo Título do documento
     * @param string $orientation Orientação da página (P, L)
     * @param string $unit Unidade de medida (mm, pt, cm, in)
     * @param string $format Formato da página (A4, A5, etc.)
     */
    public function __construct(array $empresaData, string $titulo = 'Relatório Sem Título', string $orientation = 'P', string $unit = 'mm', string $format = 'A4') {
        parent::__construct($orientation, $unit, $format, true, 'UTF-8', false);

        $this->empresaData = $empresaData;
        $this->tituloPersonalizado = $titulo;

        // Configurações padrão do documento
        $this->SetCreator(PDF_CREATOR);
        $this->SetAuthor('Tech PS');
        $this->SetTitle($this->tituloPersonalizado);

        // ======================= A CORREÇÃO ESTÁ AQUI =======================
        // A margem superior (50) agora é maior que a altura do cabeçalho (~45mm).
        $this->SetMargins(15, 35, 15);
        // ====================================================================
        
        $this->SetAutoPageBreak(true, 20); // A margem inferior para a quebra de página
    }

    public function Header() {
        // Logo Cliente
        $this->Image(__DIR__ . "/../imagens/logo_topo_cliente.png", 10, 10, 40, 10);

        // Logo Empresa (alinhado à direita)
        $logoEmpresa = __DIR__ .'/../'.($this->empresaData["empr_tx_logo"] ?? 'default_logo.png');
        if (file_exists($logoEmpresa)) {
            $this->Image($logoEmpresa, $this->GetPageWidth() - 45, 10, 30, 15);
        }


        // Título - Usando SetY para garantir a posição correta
        $this->SetY(15); // Posiociona o cursor abaixo da linha azul
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

$pdf = new CustomPDF($empresa , 'Lista de '.$_POST["paginaTitulo"],"L");
$pdf->setEmpresaData($empresa);
$pdf->AddPage();

// Recebe e trata o HTML
$htmlTabela = $_POST['tabela_html'] ?? '';

// Limpeza adicional do HTML
$htmlTabela = preg_replace('/<i[^>]*>(.*?)<\/i>/', '', $htmlTabela); // Remove ícones
$htmlTabela = str_replace(';""', '', $htmlTabela); // Corrige atributos malformados

// Escreve o HTML no PDF
$pdf->writeHTML($htmlTabela, true, false, true, false, '');

// Gera o PDF
$nomeArquivo = 'Lista de '.$_POST["paginaTitulo"].'.pdf';
$pdf->Output($nomeArquivo, 'I');