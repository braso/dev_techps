<?php
// ini_set("display_errors", 1);
// error_reporting(E_ALL);

require_once __DIR__."/conecta.php";

date_default_timezone_set('America/Fortaleza');

/* =========================
   FUNÇÕES
========================= */

class SimpleZip {
    private $files = [];
    
    public function addFile($content, $filename) {
        $this->files[] = ['name' => $filename, 'data' => $content];
    }
    
    public function save($filepath) {
        $fp = fopen($filepath, 'wb');
        if (!$fp) return false;

        $centralDir = '';
        $offset = 0;
        
        foreach ($this->files as $file) {
            $data = $file['data'];
            $name = $file['name'];
            $len = strlen($data);
            $crc = crc32($data);
            $namelen = strlen($name);
            
            // Local File Header
            $header = "\x50\x4b\x03\x04"; // Signature
            $header .= "\x0a\x00";       // Version needed
            $header .= "\x00\x00";       // Flags
            $header .= "\x00\x00";       // Compression (0 = Store)
            $header .= "\x00\x00\x00\x00"; // Time/Date
            $header .= pack('V', $crc);    // CRC32
            $header .= pack('V', $len);    // Compressed size
            $header .= pack('V', $len);    // Uncompressed size
            $header .= pack('v', $namelen); // Filename length
            $header .= "\x00\x00";       // Extra field length
            $header .= $name;
            
            fwrite($fp, $header . $data);
            
            // Central Directory Record
            $cd = "\x50\x4b\x01\x02";   // Signature
            $cd .= "\x00\x00";          // Version made by
            $cd .= "\x0a\x00";          // Version needed
            $cd .= "\x00\x00";          // Flags
            $cd .= "\x00\x00";          // Compression (0 = Store)
            $cd .= "\x00\x00\x00\x00";  // Time/Date
            $cd .= pack('V', $crc);     // CRC32
            $cd .= pack('V', $len);     // Compressed size
            $cd .= pack('V', $len);     // Uncompressed size
            $cd .= pack('v', $namelen); // Filename length
            $cd .= "\x00\x00";          // Extra field length
            $cd .= "\x00\x00";          // Comment length
            $cd .= "\x00\x00";          // Disk number start
            $cd .= "\x00\x00";          // Internal attrs
            $cd .= "\x00\x00\x00\x00";  // External attrs
            $cd .= pack('V', $offset);  // Offset of local header
            $cd .= $name;
            
            $centralDir .= $cd;
            $offset += strlen($header) + $len;
        }
        
        // End of Central Directory Record
        $eocd = "\x50\x4b\x05\x06";     // Signature
        $eocd .= "\x00\x00";            // Disk number
        $eocd .= "\x00\x00";            // Disk number with CD
        $eocd .= pack('v', count($this->files)); // Disk entries
        $eocd .= pack('v', count($this->files)); // Total entries
        $eocd .= pack('V', strlen($centralDir)); // CD size
        $eocd .= pack('V', $offset);    // CD offset
        $eocd .= "\x00\x00";            // Comment length
        
        fwrite($fp, $centralDir . $eocd);
        fclose($fp);
        return true;
    }
}

function pad($valor, $tamanho, $tipo='A'){
    if($tipo === 'N'){
        $valor = preg_replace('/\D/','',$valor ?? '');
        return str_pad(substr($valor, -$tamanho), $tamanho, '0', STR_PAD_LEFT);
    }
    return str_pad(substr($valor ?? '',0,$tamanho), $tamanho, ' ', STR_PAD_RIGHT);
}

function formatDH($datetime){
    $dt = new DateTime($datetime);
    return $dt->format('YmdHis');
}

function formatDate($date){
    $dt = new DateTime($date);
    return $dt->format('dmY');
}

/* =========================
   CRC-16 CCITT (Kermit)
========================= */
function crc16_kermit($data){
    $crc = 0x0000;
    $len = strlen($data);

    for ($i = 0; $i < $len; $i++) {
        $crc ^= ord($data[$i]);
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x0001) {
                $crc = ($crc >> 1) ^ 0x8408;
            } else {
                $crc = $crc >> 1;
            }
        }
    }

    $crc = ~$crc & 0xFFFF;
    return strtoupper(str_pad(dechex($crc),4,'0',STR_PAD_LEFT));
}

function gerarHashTipo7($dados){
    return strtoupper(hash('sha256', $dados));
}

/* =========================
   PARÂMETROS
========================= */

$empresaId = intval($_REQUEST['empresa'] ?? 0);
$dataInicio = $_REQUEST['data_inicio'] ?? date('Y-m-01');
$dataFim = $_REQUEST['data_fim'] ?? date('Y-m-d');

if(!$empresaId){
    exit("Empresa não informada.");
}

/* =========================
   EMPRESA
========================= */

$stmt = $conn->prepare("
    SELECT empr_tx_cnpj, empr_tx_nome, empr_tx_inpi
    FROM empresa
    WHERE empr_nb_id = ?
    LIMIT 1
");
$stmt->bind_param("i",$empresaId);
$stmt->execute();
$stmt->bind_result($emprCnpj, $emprNome, $emprInpi);

if(!$stmt->fetch()){
    exit("Empresa não encontrada.");
}
$stmt->close();

/* =========================
   INICIALIZAÇÃO
========================= */

$linhas = [];
$hashAnterior = '';
$nsr = 0;
$totalTipo5 = 0;
$totalTipo7 = 0;

/* =========================
   HEADER TIPO 1
========================= */

$headerBase =
    pad(0,9,'N').
    '1'.
    '1'.
    pad($emprCnpj,14,'N').
    pad('',14,'N').
    pad($emprNome,150).
    pad($emprInpi,17,'N').
    pad(formatDate($dataInicio),10,'N').
    pad(formatDate($dataFim),10,'N').
    formatDH(date('Y-m-d H:i:s')).
    '003'.
    '1'.
    pad($emprCnpj,14,'N').
    pad('',30);

$linhas[] = $headerBase . crc16_kermit($headerBase);

/* =========================
   REGISTRO TIPO 5
========================= */

$stmt = $conn->prepare("
    SELECT enti_tx_cpf,
           enti_tx_nome,
           enti_tx_dataCadastro,
           enti_tx_dataAtualiza,
           enti_tx_situacao
    FROM entidade
    WHERE enti_nb_empresa = ?
    ORDER BY enti_tx_nome ASC
");

$stmt->bind_param("i",$empresaId);
$stmt->execute();
$stmt->bind_result($cpfEmp,$nomeEmp,$dataCad,$dataAlt,$situacao);

while($stmt->fetch()){

    $nsr++;
    $totalTipo5++;

    if($situacao == 'ativo'){
        $operacao = 'I';
        $dataGravacao = $dataCad;
    } elseif($situacao == 'inativo'){
        $operacao = 'E';
        $dataGravacao = $dataAlt ?: $dataCad;
    } else {
        $operacao = 'A';
        $dataGravacao = $dataAlt ?: $dataCad;
    }

    $baseTipo5 =
        pad($nsr,9,'N').
        '5'.
        formatDate($dataGravacao).
        date('Hi', strtotime($dataGravacao)).
        $operacao.
        pad($cpfEmp,11,'N').
        pad($nomeEmp,52);

    $linhas[] = $baseTipo5 . crc16_kermit($baseTipo5);
}
$stmt->close();

/* =========================
   REGISTRO TIPO 3
========================= */

$dataInicioFull = $dataInicio." 00:00:00";
$dataFimFull = $dataFim." 23:59:59";

$stmt = $conn->prepare("
    SELECT p.pont_tx_data, e.enti_tx_cpf
    FROM ponto p
    INNER JOIN entidade e ON p.pont_tx_matricula = e.enti_tx_matricula
    WHERE e.enti_nb_empresa = ?
    AND p.pont_tx_data BETWEEN ? AND ?
    ORDER BY p.pont_tx_data ASC, p.pont_nb_id ASC
");

$stmt->bind_param("iss",$empresaId,$dataInicioFull,$dataFimFull);
$stmt->execute();
$stmt->bind_result($pontData,$entiCpf);

while($stmt->fetch()){

    $nsr++;
    $totalTipo7++; // Mantendo a contagem para o trailer (embora seja tipo 3 agora, o trailer pode precisar ajustar o nome da variavel ou campo)

    $baseHash =
        pad($nsr,9,'N').
        '3'.
        pad($entiCpf,11,'N').
        formatDH($pontData).
        $hashAnterior;

    $hashAtual = gerarHashTipo7($baseHash); // SHA256

    $linha =
        pad($nsr,9,'N').
        '3'.
        pad($entiCpf,11,'N').
        formatDH($pontData).
        $hashAtual;

    $linhas[] = $linha;
    $hashAnterior = $hashAtual;
}
$stmt->close();

/* =========================
   TRAILER TIPO 9
========================= */

$trailer =
    '999999999'.
    '9'.
    pad('0',9,'N').
    pad($totalTipo7,9,'N').
    pad('0',9,'N').
    pad($totalTipo5,9,'N').
    pad('0',9,'N').
    pad('0',9,'N').
    '9';

$linhas[] = $trailer . crc16_kermit($trailer);

/* =========================
   ASSINATURA P7S
========================= */

$conteudo = implode("\r\n",$linhas)."\r\n";

// Verifica se foi enviado certificado PFX para assinatura
if (isset($_FILES['certificado_pfx']) && $_FILES['certificado_pfx']['error'] == UPLOAD_ERR_OK && !empty($_POST['certificado_senha'])) {
    
    $pfxContent = file_get_contents($_FILES['certificado_pfx']['tmp_name']);
    $pfxPassword = $_POST['certificado_senha'];
    
    $certInfo = [];
    if (openssl_pkcs12_read($pfxContent, $certInfo, $pfxPassword)) {
        $publicKey = $certInfo['cert'];
        $privateKey = $certInfo['pkey'];
        
        // Arquivos temporários
        $tempAfd = tempnam(sys_get_temp_dir(), 'AFD');
        $signedAfd = tempnam(sys_get_temp_dir(), 'AFD_SIGNED');
        
        file_put_contents($tempAfd, $conteudo);
        
        // Assinatura PKCS#7 (Attached)
        if (openssl_pkcs7_sign($tempAfd, $signedAfd, $publicKey, $privateKey, [], PKCS7_BINARY | PKCS7_NOATTR)) {
            $conteudoSigned = file_get_contents($signedAfd);

            // Tenta criar ZIP com ambos os arquivos usando implementação PHP Pura (Fallback Garantido)
            $nomeBase = "AFD{$emprInpi}{$emprCnpj}_REP_P";
            $zipFilename = tempnam(sys_get_temp_dir(), 'AFD_ZIP');
            
            $zipCreated = false;

            // 1. Tenta usar ZipArchive (Extensão PHP)
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                    $zip->addFromString($nomeBase.".txt", $conteudo);
                    $zip->addFromString($nomeBase.".p7s", $conteudoSigned);
                    $zip->close();
                    if(file_exists($zipFilename) && filesize($zipFilename) > 0) {
                        $zipCreated = true;
                    }
                }
            }
            
            // 2. Se falhar, usa implementação SimpleZip (PHP Puro)
            if (!$zipCreated) {
                $simpleZip = new SimpleZip();
                $simpleZip->addFile($conteudo, $nomeBase.".txt");
                $simpleZip->addFile($conteudoSigned, $nomeBase.".p7s");
                if ($simpleZip->save($zipFilename)) {
                    $zipCreated = true;
                }
            }

            if ($zipCreated) {
                // Download ZIP
                header("Content-Type: application/zip");
                header("Content-Disposition: attachment; filename=\"{$nomeBase}.zip\"");
                header("Content-Length: " . filesize($zipFilename));
                readfile($zipFilename);
                unlink($zipFilename);
            } else {
                // Fallback final: Baixa apenas o P7S
                header("Content-Type: application/pkcs7-signature");
                header("Content-Disposition: attachment; filename=\"{$nomeBase}.p7s\"");
                echo $conteudoSigned;
            }
            
            // Limpeza
            if(file_exists($tempAfd)) unlink($tempAfd);
            if(file_exists($signedAfd)) unlink($signedAfd);
            exit;

        } else {
            $conteudo .= "\r\nERRO AO ASSINAR: " . openssl_error_string();
        }
        
        unlink($tempAfd);
        if(file_exists($signedAfd)) unlink($signedAfd);
        
    } else {
        $conteudo .= "\r\nERRO AO LER CERTIFICADO: Senha incorreta ou arquivo inválido.";
    }

} else {
    // Sem assinatura, adiciona marcador conforme exemplo do usuário
    $conteudo .= str_pad("ASSINATURA_DIGITAL_EM_ARQUIVO_P7S", 100, ' ', STR_PAD_RIGHT)."\r\n";
}

/* =========================
   DOWNLOAD
========================= */

header("Content-Type: text/plain; charset=ISO-8859-1");
header("Content-Disposition: attachment; filename=\"AFD{$emprInpi}{$emprCnpj}_REP_P.txt\"");

echo $conteudo;
exit;
