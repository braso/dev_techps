<?php
// Define interno como true para evitar redirecionamento de login/logout no conecta.php
$interno = true;

// Inclui a conexão com o banco de dados
// Tenta primeiro no diretório atual (caso tenha sido copiado) ou no diretório pai
if (file_exists("conecta.php")) {
    include "conecta.php";
} elseif (file_exists("../conecta.php")) {
    include "../conecta.php";
} else {
    die("Erro: Arquivo conecta.php não encontrado.");
}

// Tenta carregar configuração automática
// Configuração de Email
if (file_exists("email_config.php")) {
    include "email_config.php";
} elseif (file_exists("../email_config.php")) {
    include "../email_config.php";
}

// Carrega bibliotecas do PHPMailer
$phpmailerPath = __DIR__ . '/../../PHPMailer/src/';
if (file_exists($phpmailerPath . 'Exception.php')) {
    require $phpmailerPath . 'Exception.php';
    require $phpmailerPath . 'PHPMailer.php';
    require $phpmailerPath . 'SMTP.php';
}

require_once 'email_helper.php';

$pfxConfig = [];
if (file_exists('config_pfx.php')) {
    $pfxConfig = include('config_pfx.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Método inválido.');
}

$id_documento = isset($_POST['id_documento']) ? intval($_POST['id_documento']) : 0;
$pfx_password = $_POST['pfx_password'] ?? '';
$pfx_file_upload = isset($_FILES['pfx_file']) && $_FILES['pfx_file']['error'] == UPLOAD_ERR_OK;

// Variáveis para o certificado que será usado
$pfxPathToUse = null;
$pfxPasswordToUse = null;
$isTempFile = false;

// 1. Determina qual certificado usar
if ($pfx_file_upload) {
    // Prioridade: Upload do usuário
    $pfxPasswordToUse = $pfx_password;
    if (empty($pfxPasswordToUse)) die('Senha do certificado é obrigatória.');

    $pfxTmpName = $_FILES['pfx_file']['tmp_name'];
    $pfxExt = pathinfo($_FILES['pfx_file']['name'], PATHINFO_EXTENSION);
    $pfxPathToUse = __DIR__ . '/temp_cert_' . uniqid() . '.' . $pfxExt;
    
    if (!move_uploaded_file($pfxTmpName, $pfxPathToUse)) {
        die('Erro ao salvar certificado temporário.');
    }
    $isTempFile = true;

} elseif (!empty($pfxConfig['auto_sign']) && !empty($pfxConfig['pfx_path']) && file_exists($pfxConfig['pfx_path'])) {
    // Uso Automático: Configuração do Servidor
    $pfxPathToUse = $pfxConfig['pfx_path'];
    // Se o usuário mandou senha, usa a dele (caso queira sobrescrever), senão usa a do config
    $pfxPasswordToUse = !empty($pfx_password) ? $pfx_password : ($pfxConfig['pfx_password'] ?? '');
    
    if (empty($pfxPasswordToUse)) {
        die('Senha do certificado não configurada e não fornecida.');
    }
} else {
    die('Nenhum certificado fornecido ou configurado.');
}

// Fix for OpenSSL config on Windows/XAMPP
$opensslConf = 'C:/xampp/apache/conf/openssl.cnf';
if (file_exists($opensslConf)) {
    putenv("OPENSSL_CONF=$opensslConf");
}

if ($id_documento <= 0) {
    die('ID do documento inválido.');
}

// 2. Auto-Healing: Adicionar coluna status_final se não existir
$checkCol = "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'status_final'";
$resCol = mysqli_query($conn, $checkCol);
if (mysqli_num_rows($resCol) == 0) {
    $sqlAlter = "ALTER TABLE solicitacoes_assinatura ADD COLUMN status_final VARCHAR(50) DEFAULT 'pendente'";
    mysqli_query($conn, $sqlAlter);
}

// 3. Busca informações do documento
$sql = "SELECT * FROM solicitacoes_assinatura WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_documento);
mysqli_stmt_execute($stmt);
$doc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$doc) {
    if ($isTempFile && file_exists($pfxPathToUse)) unlink($pfxPathToUse);
    die('Documento não encontrado.');
}

// Caminho do arquivo atual (assinado visualmente)
$inputPdfPath = __DIR__ . '/' . $doc['caminho_arquivo'];

if (!file_exists($inputPdfPath)) {
    if ($isTempFile && file_exists($pfxPathToUse)) unlink($pfxPathToUse);
    die('Arquivo PDF original não encontrado no servidor: ' . $inputPdfPath);
}

// 4. ASSINATURA DIGITAL COM PHP (TCPDF + FPDI)
// ============================================

// Definição dos caminhos das bibliotecas
$tcpdfPath = __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

// Verifica se as bibliotecas existem
if (!file_exists($tcpdfPath)) {
    die("Erro: Biblioteca TCPDF não encontrada. Por favor, execute o script 'setup_libs.php' para instalar as dependências.");
}

// Carrega TCPDF
require_once($tcpdfPath);

// Autoloader para FPDI
spl_autoload_register(function ($class) {
    if (strpos($class, 'setasign\Fpdi\\') === 0) {
        $filename = str_replace('\\', '/', substr($class, 14)) . '.php';
        $fullpath = __DIR__ . '/vendor/setasign/fpdi/src/' . $filename;
        if (file_exists($fullpath)) {
            require_once $fullpath;
        }
    }
});

use setasign\Fpdi\Tcpdf\Fpdi;

// Diretório de saída
$outputDir = __DIR__ . '/docFinalizado/';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$outputFileName = 'final_' . time() . '_' . uniqid() . '.pdf';
$outputPdfPath = $outputDir . $outputFileName;

try {
    // 4.1 Carrega e valida o Certificado PFX
    $certStore = [];
    $pfxContent = file_get_contents($pfxPathToUse);
    
    if (!openssl_pkcs12_read($pfxContent, $certStore, $pfxPasswordToUse)) {
        throw new Exception("Não foi possível ler o certificado PFX. Verifique se a senha está correta.");
    }
    
    $cert = $certStore['cert'];
    $pkey = $certStore['pkey'];
    
    // 4.2 Inicializa o PDF com FPDI
    // Classe Fpdi estende TCPDF
    $pdf = new Fpdi();
    
    // Configurações básicas
    $pdf->SetCreator('TechPS Assinador');
    $pdf->SetAuthor('TechPS');
    $pdf->SetTitle('Documento Assinado Digitalmente');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // 4.3 Importa as páginas do PDF original
    $pageCount = $pdf->setSourceFile($inputPdfPath);
    
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $tplIdx = $pdf->importPage($pageNo);
        $size = $pdf->getTemplateSize($tplIdx);
        
        // Define orientação baseada nas dimensões
        $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
        
        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
        $pdf->useTemplate($tplIdx);
    }
    
    // 4.4 Configura a Assinatura Digital
    $info = array(
        'Name' => 'Assinatura Digital ICP-Brasil',
        'Location' => 'Sistema TechPS',
        'Reason' => 'Garantia de Autenticidade e Integridade',
        'ContactInfo' => 'suporte@techps.com.br',
    );
    
    // Aplica a assinatura (será gravada ao salvar)
    $pdf->setSignature($cert, $pkey, $pfxPasswordToUse, '', 2, $info);
    
    // 4.5 Adiciona o Carimbo Visual (Última Página)
    // Extrai dados do certificado para o carimbo
    $certData = openssl_x509_parse($certStore['cert']);
    $subject = $certData['subject'];
    $issuer = $certData['issuer'];
    
    // Tenta obter o nome mais apropriado (CN ou O)
    $signerName = $subject['CN'] ?? $subject['O'] ?? 'Signatário Desconhecido';
    if (is_array($signerName)) $signerName = implode(', ', $signerName);
    
    $issuerName = $issuer['CN'] ?? $issuer['O'] ?? 'Autoridade Certificadora';
    if (is_array($issuerName)) $issuerName = implode(', ', $issuerName);
    
    $signDate = date('d/m/Y H:i:s');
    
    // Calcula o hash do documento original (SHA-256)
    $docHash = hash_file('sha256', $inputPdfPath);
    $shortHash = substr($docHash, 0, 20) . '...'; // Exibe apenas o início
    
    // Gera um código de verificação simples (simulação)
    $verificationCode = strtoupper(substr(md5($outputFileName . $signDate . $id_documento), 0, 16));

    // Posiciona no canto inferior direito
    $w = 120; // Largura um pouco maior
    $h = 36;  // Altura um pouco maior
    $margin = 10;
    
    // Desativa quebra de página automática para garantir que o carimbo fique na mesma página
    $pdf->SetAutoPageBreak(false);
    
    $x = $pdf->getPageWidth() - $w - $margin;
    $y = $pdf->getPageHeight() - $h - $margin;
    
    // Fundo branco com borda arredondada
    $pdf->SetAlpha(1);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
    $pdf->RoundedRect($x, $y, $w, $h, 2, '1111', 'DF');
    
    // Logo ICP-Brasil
    $logoPath = __DIR__ . '/assets/icp.png';
    if (file_exists($logoPath)) {
        // Ajusta tamanho da logo (reduzido para não sobrepor texto)
        $pdf->Image($logoPath, $x + 2, $y + 4, 18, 0, 'PNG');
    }
    
    // Área de Texto
    $textX = $x + 30; // Margem esquerda do texto
    $currentY = $y + 3;
    
    // Título
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($textX, $currentY);
    $pdf->Cell(0, 0, 'ASSINADO DIGITALMENTE', 0, 1, 'L');
    
    // Nome do Signatário
    $currentY += 4;
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY($textX, $currentY);
    // Limita largura do texto para evitar estouro
    $pdf->Cell($w - 32, 0, substr(strtoupper($signerName), 0, 45), 0, 1, 'L');
    
    // Dados da Assinatura
    $currentY += 4;
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetXY($textX, $currentY);
    $pdf->Cell($w - 32, 0, 'Data: ' . $signDate, 0, 1, 'L');
    
    $currentY += 3;
    $pdf->SetXY($textX, $currentY);
    $pdf->Cell($w - 32, 0, 'Emissor: ' . substr($issuerName, 0, 50), 0, 1, 'L');

    // Hash e Código
    $currentY += 3;
    $pdf->SetXY($textX, $currentY);
    $pdf->Cell($w - 32, 0, 'Hash Doc: ' . $shortHash, 0, 1, 'L');
    
    $currentY += 3;
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->SetXY($textX, $currentY);
    $pdf->Cell($w - 32, 0, 'Cód. Verificação: ' . $verificationCode, 0, 1, 'L');
    
    // Texto Legal (Rodapé do carimbo)
    $pdf->SetFont('helvetica', '', 5);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetXY($x + 2, $y + $h - 7);
    $pdf->MultiCell($w - 4, 8, "Documento assinado digitalmente conforme MP 2.200-2/2001 e Lei 14.063/2020.\nValidade jurídica assegurada.", 0, 'L');

    // 4.6 Salva o arquivo final
    $pdf->Output($outputPdfPath, 'F');
    
    if (!file_exists($outputPdfPath)) {
        throw new Exception("Erro ao salvar o arquivo PDF assinado.");
    }
    
    // Sucesso!

} catch (Exception $e) {
    // Limpa temp file se erro
    if ($isTempFile && file_exists($pfxPathToUse)) {
        unlink($pfxPathToUse);
    }
    die("Erro na assinatura digital: " . $e->getMessage());
}

// Remove certificado temporário IMEDIATAMENTE (apenas se for upload)
if ($isTempFile && file_exists($pfxPathToUse)) {
    unlink($pfxPathToUse);
}

// 5. Atualiza o banco de dados
$novoCaminhoRelativo = 'docFinalizado/' . $outputFileName;
$sqlUpdate = "UPDATE solicitacoes_assinatura SET caminho_arquivo = ?, status_final = 'finalizado' WHERE id = ?";
$stmtUp = mysqli_prepare($conn, $sqlUpdate);
mysqli_stmt_bind_param($stmtUp, "si", $novoCaminhoRelativo, $id_documento);

if (mysqli_stmt_execute($stmtUp)) {
    // Envia e-mail final para TODOS com o documento assinado digitalmente
    try {
        // Garante que o ID string do documento esteja disponível
        $docIdString = isset($doc['id_documento']) ? $doc['id_documento'] : $id_documento;
        enviarEmailFinalizacao($conn, $id_documento, $docIdString, $novoCaminhoRelativo);
    } catch (Exception $e) {
        // Não interrompe o fluxo se falhar o email, mas registra erro se possível
        error_log("Erro ao enviar email final no processar_finalizacao: " . $e->getMessage());
    }

    // Redireciona com sucesso
    header("Location: finalizar.php?status=success&message=Documento assinado digitalmente com sucesso!");
    exit;
} else {
    die('Erro ao atualizar banco de dados: ' . mysqli_error($conn));
}
?>
