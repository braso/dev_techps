<?php
// gerar_pdf.php
// Versão simplificada: Gera um PDF de "Certificado de Assinatura" que faz referência ao documento original.
// Nota: Sem FPDI instalado via Composer, não conseguimos editar o PDF original para inserir o carimbo nele diretamente de forma robusta.
// Solução: Geramos um PDF de manifesto/comprovante que pode ser anexado ao processo.

require_once('../tcpdf/tcpdf.php');
$interno = true; // Evita bloqueio de sessão
include "../conecta.php"; 

// Verifica se foi passado o ID da assinatura
$id_assinatura = isset($_GET['id_assinatura']) ? intval($_GET['id_assinatura']) : 0;

if ($id_assinatura <= 0) {
    die('ID da assinatura inválido.');
}

// Busca os dados da assinatura no banco
$sql = "SELECT * FROM assinatura_eletronica WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_assinatura);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$dados = mysqli_fetch_assoc($result);

if (!$dados) {
    die('Assinatura não encontrada.');
}

// Dados para o PDF
$nome_funcionario = "Funcionário (CPF: " . $dados['cpf'] . ")"; 
$data_assinatura = date('d/m/Y H:i:s', strtotime($dados['data_assinatura']));
$hash = $dados['hash_assinatura'];
$protocolo = $hash; 
$ip = $dados['ip_address'];
$user_agent = $dados['user_agent'];
$doc_original = $dados['id_documento'];

// Configuração do TCPDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Define informações do documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Tech PS - Tecnologia e Solução');
$pdf->SetTitle('Comprovante de Assinatura Eletrônica');
$pdf->SetSubject('Comprovante de Assinatura');

// Remove cabeçalho e rodapé padrão
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Define margens
$pdf->SetMargins(15, 15, 15);

// Adiciona página
$pdf->AddPage();

// Conteúdo do Documento
$html = <<<EOF
<style>
    h1 { color: #333; font-family: helvetica; font-size: 18pt; text-align: center; border-bottom: 2px solid #ccc; padding-bottom: 10px; }
    .info-box { font-family: helvetica; font-size: 11pt; line-height: 1.4; }
    .label { font-weight: bold; color: #555; }
    .hash-box {
        background-color: #f0f0f0;
        border: 1px dashed #999;
        padding: 10px;
        font-family: courier;
        font-size: 9pt;
        color: #333;
        margin-top: 10px;
    }
    .footer-legal {
        margin-top: 50px;
        font-size: 8pt;
        color: #888;
        text-align: center;
    }
</style>

<h1>COMPROVANTE DE ASSINATURA ELETRÔNICA</h1>
<br><br>

<div class="info-box">
    <p><span class="label">Documento Original:</span> {$doc_original}</p>
    <p><span class="label">Assinado por:</span> {$nome_funcionario}</p>
    <p><span class="label">CPF:</span> {$dados['cpf']}</p>
    <p><span class="label">RG:</span> {$dados['rg']}</p>
    <p><span class="label">Data e Hora:</span> {$data_assinatura}</p>
    <p><span class="label">Endereço IP:</span> {$ip}</p>
    <p><span class="label">Navegador/Dispositivo:</span> {$user_agent}</p>
</div>

<br><br>
<div class="label">Hash de Integridade (Assinatura Digital):</div>
<div class="hash-box">{$hash}</div>

<br><br>
<p>
    O signatário declarou ter lido e concordado com o teor do documento original referenciado acima.
    Esta assinatura foi realizada eletronicamente através do sistema TechPS, com validade jurídica assegurada pela Medida Provisória nº 2.200-2/2001.
</p>

<div class="footer-legal">
    Tech PS - Tecnologia e Solução<br>
    Gerado em: {$data_assinatura}
</div>
EOF;

// Escreve o conteúdo HTML no PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Gera o nome do arquivo
$nome_arquivo = 'comprovante_' . $dados['id_documento'] . '_' . $id_assinatura . '.pdf';
$caminho_completo = __DIR__ . '/docAssinado/' . $nome_arquivo;

// Salva o arquivo no servidor
$pdf->Output($caminho_completo, 'F');

// Atualiza o banco com o caminho do arquivo
$sql_update = "UPDATE assinatura_eletronica SET caminho_arquivo = ? WHERE id = ?";
$stmt_update = mysqli_prepare($conn, $sql_update);
$caminho_relativo = 'docAssinado/' . $nome_arquivo;
mysqli_stmt_bind_param($stmt_update, "si", $caminho_relativo, $id_assinatura);
mysqli_stmt_execute($stmt_update);

// Exibe o PDF no navegador (Força Download)
$pdf->Output($nome_arquivo, 'D');
?>
