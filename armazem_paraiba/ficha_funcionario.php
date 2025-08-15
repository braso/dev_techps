<?php

/*
 * MODO DEBUG
 * Em um ambiente de produção, é recomendado controlar isso através de uma variável de ambiente.
 */
/*
ini_set("display_errors", 1);
error_reporting(E_ALL);
//*/

require_once __DIR__ . "/tcpdf/tcpdf.php";
require_once __DIR__ . "/funcoes_ponto.php"; // Supondo que as funções carregar() e query() estão aqui.

// =============================================================================
// FUNÇÕES AUXILIARES (mantidas do código original)
// =============================================================================
function formatarData(?string $dataString): string {
    if (empty($dataString) || $dataString === '0000-00-00') {
        return '';
    }
    $timestamp = strtotime($dataString);
    return $timestamp ? date('d/m/Y', $timestamp) : '';
}

function obterDado(array $array, string $chave, $padrao = '') {
    return $array[$chave] ?? $padrao;
}

function formatarMoeda($valor): string {
    $valorNumerico = floatval($valor);
    return 'R$ ' . number_format($valorNumerico, 2, ',', '.');
}

function formatarCPF(?string $cpf): string {
    $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf ?? '');
    if (strlen($cpfLimpo) != 11) {
        return $cpf ?? ''; // Retorna o original se não for um CPF válido
    }
    return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "$1.$2.$3-$4", $cpfLimpo);
}

// 1. Obter ID do funcionário de forma segura

$funcionarioId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$funcionarioId) {
    die("ID de funcionário inválido ou não fornecido.");
}

// 2. Carregar dados principais do funcionário

$motorista = carregar("entidade", $funcionarioId);

if (!$motorista) {

    die("Funcionário com o ID {$funcionarioId} não encontrado.");
}

// 3. Carregar dados relacionados (Empresa, Parâmetro, Cidade da CNH)

// ATENÇÃO: A função `query()` como usada no código original é vulnerável a SQL Injection.

// O ideal é usar Prepared Statements (com MySQLi ou PDO). Ex: $stmt->execute([$id]);

$empresa = [];

if ($idEmpresa = obterDado($motorista, 'enti_nb_empresa')) {
    $empresa = carregar("empresa", $idEmpresa);
}

$parametroJornada = [];

if ($idParametro = obterDado($motorista, 'enti_nb_parametro')) {

    $result = query("SELECT * FROM parametro WHERE para_nb_id = " . intval($idParametro));

    $parametroJornada = mysqli_fetch_assoc($result);
}

$cidadeCNH = [];

if ($idCidade = obterDado($motorista, 'enti_nb_cnhCidade')) {

    // Usando query diretamente como no exemplo original. CUIDADO COM SQL INJECTION.

    $result = query("SELECT cida_tx_nome FROM cidade WHERE cida_nb_id = " . intval($idCidade));

    $cidadeCNH = mysqli_fetch_assoc($result);
}

$cidade = [];

if ($idCidade = obterDado($motorista, 'enti_nb_cidade')) {

    // Usando query diretamente como no exemplo original. CUIDADO COM SQL INJECTION.

    $result = query("SELECT cida_tx_nome, cida_tx_uf FROM cidade WHERE cida_nb_id = " . intval($idCidade));

    $cidade = mysqli_fetch_assoc($result);
}

if ($idCidade = obterDado($motorista, 'enti_nb_cnhCidade')) {

    // Usando query diretamente como no exemplo original. CUIDADO COM SQL INJECTION.

    $result = query("SELECT cida_tx_nome, cida_tx_uf FROM cidade WHERE cida_nb_id = " . intval($idCidade));

    $cidadeCNH = mysqli_fetch_assoc($result);
}

// 4. Lógica de verificação da jornada padrão

$padronizado = "Não";

if (!empty($empresa)) {

    $parametroEmpresa = carregar("parametro", obterDado($empresa, 'empr_nb_parametro'));

    if ($parametroEmpresa) {

        $isPadronizado = (

            obterDado($motorista, "enti_tx_jornadaSemanal") == obterDado($parametroEmpresa, "para_tx_jornadaSemanal") &&

            obterDado($motorista, "enti_tx_jornadaSabado") == obterDado($parametroEmpresa, "para_tx_jornadaSabado") &&

            obterDado($motorista, "enti_tx_percHESemanal") == obterDado($parametroEmpresa, "para_tx_percHESemanal") &&

            obterDado($motorista, "enti_tx_percHEEx") == obterDado($parametroEmpresa, "para_tx_percHEEx")

        );

        $padronizado = $isPadronizado ? "Sim" : "Não";
    }
}


// =============================================================================
// CLASSE PDF PERSONALIZADA (AJUSTADA PARA O NOVO LAYOUT)
// =============================================================================

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
        $this->Image(__DIR__ . "/imagens/logo_topo_cliente.png", 10, 10, 40, 10);

        // Logo Empresa (alinhado à direita)
        $logoEmpresa = __DIR__ . "/" . ($this->empresaData["empr_tx_logo"] ?? 'default_logo.png');
        if (file_exists($logoEmpresa)) {
            $this->Image($logoEmpresa, $this->GetPageWidth() - 45, 10, 30, 15);
        }


        // Título - Usando SetY para garantir a posição correta
        $this->SetY(25); // Posiociona o cursor abaixo da linha azul
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, strtoupper($this->tituloPersonalizado), 0, 1, 'C', false, '', 0, false, 'T', 'M');
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

CustomPDF::setEmpresaData($empresa['empr_tx_logo']); // Para compatibilidade com o código original

// =============================================================================
// FUNÇÕES DE DESENHO PARA O FORMULÁRIO (REFINADAS)
// =============================================================================

/**
 * Desenha uma seção de título com fundo cinza.
 * @param TCPDF $pdf Instância do PDF
 * @param string $titulo O texto do título
 */
function desenharTituloSecao(TCPDF $pdf, string $titulo) {
    // Reduz o espaçamento superior para um visual mais compacto.
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(230, 230, 230);
    // Adiciona um espaçamento interno para afastar o texto da borda da célula.
    // Usamos mb_strtoupper para garantir que caracteres acentuados sejam convertidos corretamente.
    $pdf->Cell(0, 8, ' ' . mb_strtoupper($titulo, 'UTF-8'), 0, 1, 'L', true);
    // Reduz o espaçamento inferior para diminuir a distância entre o título e a primeira linha de campos.
    $pdf->Ln(1);
}

/**
 * Desenha uma linha com múltiplos campos, usando larguras fixas.
 * @param TCPDF $pdf Instância do PDF
 * @param array $campos Array de campos. Cada campo é um array com ['label', 'value', 'largura', 'labelRatio'].
 * @param float $espacoEntreCampos Espaço em mm entre os campos (padrão 2mm).
 */
function desenharLinhaDeCamposFlex(TCPDF $pdf, array $campos, float $espacoEntreCampos = 2) {
    $larguraPagina = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
    $xInicial = $pdf->GetX();

    foreach ($campos as $index => $campo) {
        // Define as larguras do rótulo e do valor
        $larguraLabel = $campo['larguraLabel'] ?? 0;
        $larguraValor = $campo['larguraValor'] ?? 0;
        
        // A largura total do campo é a soma das larguras de label e valor
        $larguraTotal = $larguraLabel + $larguraValor;
        if ($larguraTotal === 0) {
            // Se as larguras não forem definidas, usa a largura total do campo
            $larguraTotal = $campo['largura'];
            $larguraLabel = $larguraTotal * 0.35; // Padrão
            $larguraValor = $larguraTotal * 0.65; // Padrão
        }

        // Largura total do campo (label + valor) + espaçamento
        $larguraCampoTotal = $larguraTotal;
        if ($index < count($campos) - 1) {
            $larguraCampoTotal += $espacoEntreCampos;
        }

        // Verifica se o campo cabe na linha atual
        if ($pdf->GetX() + $larguraCampoTotal > $larguraPagina + $pdf->getMargins()['left']) {
            $pdf->Ln(10);
            $pdf->SetX($xInicial);
        }

        // Prepara o valor para a célula, truncando-o se for muito longo
        $valor = $campo['value'] ?? '';
        
        // Label
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($larguraLabel, 7, ' ' . $campo['label'], 0, 0, 'L', true);

        // Valor
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($larguraValor, 7, ' ' . $valor, 'B', 0, 'L');
        
        // Espaço entre os campos (exceto para o último)
        if ($index < count($campos) - 1) {
            $pdf->Cell($espacoEntreCampos, 7, '');
        }
    }
    
    $pdf->Ln();
    $pdf->Ln(1.5);
}

// =============================================================================
// INICIALIZAÇÃO E GERAÇÃO DO PDF
// =============================================================================

// --- INICIALIZAÇÃO DO DOCUMENTO ---
$pdf = new CustomPDF($empresa, 'Ficha de Cadastro do Colaborador '.$motorista['enti_tx_nome']);
$pdf->AddPage();

// --- CAMPO PARA FOTO 3X4 ---
$fotoPath    = $motorista["enti_tx_foto"] ?? null;
$larguraFoto = 30;
$alturaFoto  = 40;
// Posição alinhada à margem direita
$posXFoto    = $pdf->GetPageWidth() - $pdf->getMargins()['right'] - $larguraFoto;
$posYFoto    = $pdf->getMargins()['top'];

if ($fotoPath && file_exists($fotoPath)) {
    $pdf->Image($fotoPath, $posXFoto, $posYFoto, $larguraFoto, $alturaFoto);
} else {
    $pdf->SetLineStyle(['width' => 0.2, 'dash' => 2]);
    $pdf->Rect($posXFoto, $posYFoto, $larguraFoto, $alturaFoto, 'D');
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetXY($posXFoto, $posYFoto + ($alturaFoto / 2) - 3);
    $pdf->Cell($larguraFoto, 6, 'FOTO 3x4', 0, 0, 'C');
}

// Reposiciona o cursor abaixo da header, considerando o espaço para a foto
$pdf->SetY($posYFoto + $alturaFoto );
$pdf->SetLineStyle(['width' => 0.2, 'dash' => 0]);

// --- SEÇÃO: DADOS PESSOAIS ---
desenharTituloSecao($pdf, 'Dados Pessoais');
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'Matrícula:', 'value' => $motorista["enti_tx_matricula"] ?? '', 'larguraLabel' => 18, 'larguraValor' => 25],
    ['label' => 'Nome completo:', 'value' => $motorista['enti_tx_nome'] ?? '', 'larguraLabel' => 28, 'larguraValor' => 106]
]);
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'Nascimento:', 'value' => formatarData($motorista["enti_tx_nascimento"] ?? ''), 'larguraLabel' => 23, 'larguraValor' => 25],
    ['label' => 'CPF:', 'value' => formatarCPF($motorista["enti_tx_cpf"] ?? ''), 'larguraLabel' => 11, 'larguraValor' => 30],
    ['label' => 'RG:', 'value' => $motorista["enti_tx_rg"] ?? '', 'larguraLabel' => 10, 'larguraValor' => 20],
    ['label' => 'Estado Civil:', 'value' => $motorista["enti_tx_civil"] ?? '', 'larguraLabel' => 23, 'larguraValor' => 32],
]);
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'Sexo:', 'value' => $motorista["enti_tx_sexo"] ?? '', 'larguraLabel' => 13, 'larguraValor' => 42],
    ['label' => 'Emissão RG:', 'value' => formatarData($motorista["enti_tx_rgDataEmissao"] ?? ''),'larguraLabel' => 24, 'larguraValor' => 32],
    ['label' => 'UF RG:', 'value' => $motorista["enti_tx_rgUf"] ?? '', 'larguraLabel' => 15, 'larguraValor' => 12],
    ['label' => 'CEP:', 'value' => $motorista["enti_tx_cep"] ?? '', 'larguraLabel' => 11, 'larguraValor' => 25],
]);
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'Endereço:', 'value' => $motorista["enti_tx_endereco"] ?? '', 'larguraLabel' => 20, 'larguraValor' => 65],
    ['label' => 'Nº:', 'value' => $motorista["enti_tx_numero"] ?? '', 'larguraLabel' => 9, 'larguraValor' => 13],
    ['label' => 'Bairro:', 'value' => $motorista["enti_tx_bairro"] ?? '', 'larguraLabel' => 14, 'larguraValor' => 55],
]);
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'Município:', 'value' => $cidade["cida_tx_nome"] ?? '', 'larguraLabel' => 20, 'larguraValor' => 65],
    ['label' => 'UF:', 'value' => $cidade["cida_tx_uf"] ?? '', 'larguraLabel' => 9, 'larguraValor' => 13],
    ['label' => 'Complemento:', 'value' => $motorista["enti_tx_complemento"] ?? '', 'larguraLabel' => 27, 'larguraValor' => 42],
]);
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'Ponto de Referência:', 'value' => $motorista["enti_tx_referencia"] ?? '', 'larguraLabel' => 35, 'larguraValor' => 145],
]);
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'Telefone 1:', 'value' => $motorista['enti_tx_fone1'] ?? '', 'larguraLabel' => 20, 'larguraValor' => 28],
    ['label' => 'Telefone 2:', 'value' => $motorista['enti_tx_fone2'] ?? '', 'larguraLabel' => 20, 'larguraValor' => 28],
    ['label' => 'Tipo de Operação:', 'value' => $motorista["enti_tx_tipoOperacao"] ?? '', 'larguraLabel' => 34, 'larguraValor' => 46]
]);
desenharLinhaDeCamposFlex($pdf, [['label' => 'Nome do pai:', 'value' => $motorista["enti_tx_pai"] ?? '', 'larguraLabel' => 23, 'larguraValor' => 157]]);
desenharLinhaDeCamposFlex($pdf, [['label' => 'Nome da mãe:', 'value' => $motorista["enti_tx_mae"] ?? '', 'larguraLabel' => 25, 'larguraValor' => 155]]);
desenharLinhaDeCamposFlex($pdf, [['label' => 'Nome do Cônjuge:', 'value' => $motorista["enti_tx_conjugue"] ?? '', 'larguraLabel' => 32, 'larguraValor' => 148]]);

// // --- SEÇÃO: DADOS CONTRATUAIS---
desenharTituloSecao($pdf, 'Dados Contratuais');
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'Empresa:', 'value' => obterDado($empresa, "empr_tx_nome"), 'larguraLabel' => 18, 'larguraValor' => 117],
    ['label' => 'Salário:', 'value' => formatarMoeda($motorista["enti_nb_salario"] ?? ''), 'larguraLabel' => 15, 'larguraValor' => 28],
]);
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'Ocupação:', 'value' => $motorista["enti_tx_ocupacao"] ?? '', 'larguraLabel' => 20, 'larguraValor' => 33],
    ['label' => 'Dt. Admissão:', 'value' => formatarData($motorista["enti_tx_admissao"] ?? ''), 'larguraLabel' => 25, 'larguraValor' => 33],
    ['label' => 'Dt. Desligamento:', 'value' => formatarData($motorista["enti_tx_desligamento"] ?? ''), 'larguraLabel' => 32, 'larguraValor' => 33],
]);

// --- SEÇÃO: CONVENÇÃO SINDICAL - JORNADA PADRÃO DO FUNCIONÁRIO ---
desenharTituloSecao($pdf, 'Jornada Padrão do Funcionário');
desenharLinhaDeCamposFlex($pdf, [['label' => 'Parâmetros da Jornada:', 'value' => $parametroJornada["para_tx_nome"], 'larguraLabel' => 40, 'larguraValor' => 140]]);
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'Jornada Dias Úteis (Hr/dia):', 'value' => $parametroJornada["para_tx_jornadaSemanal"], 'larguraLabel' => 45, 'larguraValor' => 13],
    ['label' => 'Jornada Sábado:', 'value' => $parametroJornada["para_tx_jornadaSabado"] ?? '', 'larguraLabel' => 30, 'larguraValor' => 13],
    ['label' => 'Convenção Padrão:', 'value' => $padronizado, 'larguraLabel' => 35, 'larguraValor' => 40],
]);
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'H.E. Semanal (%):', 'value' => $parametroJornada["para_tx_percHESemanal"] ?? '', 'larguraLabel' => 30, 'larguraValor' => 54],
    ['label' => 'H.E. Extraordinária (%):', 'value' => $parametroJornada["para_tx_percHEEx"] ?? '', 'larguraLabel' => 40, 'larguraValor' => 54],
]);

$pdf->AddPage();
// --- SEÇÃO: CARTEIRA NACIONAL DE HABILITAÇÃO ---
desenharTituloSecao($pdf, 'Carteira Nacional de Habilitação');
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'N° Registro:', 'value' => $motorista["enti_tx_cnhRegistro"] ?? '', 'larguraLabel' => 23, 'larguraValor' => 22],
    ['label' => 'Categoria:', 'value' => $motorista["enti_tx_cnhCategoria"] ?? '', 'larguraLabel' => 20, 'larguraValor' => 13],
    ['label' => 'Validade:', 'value' => formatarData($motorista["enti_tx_cnhValidade"] ?? ''), 'larguraLabel' => 17, 'larguraValor' => 22],
    ['label' => '1º Habilitação:', 'value' => formatarData($motorista["enti_tx_cnhPrimeiraHabilitacao"] ?? ''), 'larguraLabel' => 25, 'larguraValor' => 32],
]);
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'Permissão:', 'value' => $motorista["enti_tx_cnhPermissao"] ?? '', 'larguraLabel' => 21, 'larguraValor' => 31],
    ['label' => 'Pontuação:', 'value' => $motorista["enti_tx_cnhPontuacao"] ?? '', 'larguraLabel' => 21, 'larguraValor' => 31],
    ['label' => 'Atividade Remunerada:', 'value' => $motorista["enti_tx_cnhAtividadeRemunerada"] ?? '', 'larguraLabel' => 40, 'larguraValor' => 32],
]);
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'Cidade/UF Emissão:', 'value' => "[".$cidadeCNH ["cida_tx_uf"] ."] ".$cidadeCNH ["cida_tx_nome"] ?? '', 'larguraLabel' => 35, 'larguraValor' => 145],
]);

// --- SEÇÃO: DADOS DO SISTEMA ---
desenharTituloSecao($pdf, 'Dados do Sistema');
desenharLinhaDeCamposFlex($pdf, [
    ['label' => 'Data de Cadastro:', 'value' => formatarData($motorista["enti_tx_dataCadastro"] ?? ''), 'larguraLabel' => 32, 'larguraValor' => 53],
    ['label' => 'Última Atualização por:', 'value' => formatarData($motorista["enti_nb_userAtualiza"] ?? ''), 'larguraLabel' => 40, 'larguraValor' => 53],
]);

// --- SAÍDA DO ARQUIVO ---
$pdf->Output('ficha_cadastro_colaborador'.$motorista['enti_tx_nome'].'.pdf', 'I');