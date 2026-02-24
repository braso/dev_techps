<?php
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once __DIR__."/conecta.php";

date_default_timezone_set('America/Fortaleza');

/* =========================
   FUNÇÕES
========================= */

function pad($valor, $tamanho, $tipo='A'){
    if($tipo === 'N'){
        $valor = preg_replace('/\D/','',$valor);
        return str_pad(substr($valor, -$tamanho), $tamanho, '0', STR_PAD_LEFT);
    }
    return str_pad(substr($valor,0,$tamanho), $tamanho, ' ', STR_PAD_RIGHT);
}

function formatDH($datetime){
    $dt = new DateTime($datetime);
    return $dt->format('Y-m-d\TH:i:00O');
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

$empresaId = intval($_GET['empresa'] ?? 0);
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');

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
    $dataInicio.
    $dataFim.
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
        formatDH($dataGravacao).
        $operacao.
        pad($cpfEmp,12,'N').
        pad($nomeEmp,52).
        pad('',4).
        pad($cpfEmp,11,'N');

    $linhas[] = $baseTipo5 . crc16_kermit($baseTipo5);
}
$stmt->close();

/* =========================
   REGISTRO TIPO 7
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
    $totalTipo7++;

    $baseHash =
        pad($nsr,9,'N').
        '7'.
        formatDH($pontData).
        pad($entiCpf,12,'N').
        formatDH($pontData).
        '02'.
        '0'.
        $hashAnterior;

    $hashAtual = gerarHashTipo7($baseHash);

    $linha =
        pad($nsr,9,'N').
        '7'.
        formatDH($pontData).
        pad($entiCpf,12,'N').
        formatDH($pontData).
        '02'.
        '0'.
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
    pad('0',9,'N').
    pad('0',9,'N').
    pad('0',9,'N').
    pad($totalTipo5,9,'N').
    pad('0',9,'N').
    pad($totalTipo7,9,'N').
    '9';

$linhas[] = $trailer;

/* =========================
   ASSINATURA P7S (MARCADOR)
========================= */

$linhas[] = str_pad("ASSINATURA_DIGITAL_EM_ARQUIVO_P7S", 100, ' ', STR_PAD_RIGHT);

/* =========================
   DOWNLOAD
========================= */

$conteudo = implode("\r\n",$linhas)."\r\n";

header("Content-Type: text/plain; charset=ISO-8859-1");
header("Content-Disposition: attachment; filename=\"AFD{$emprInpi}{$emprCnpj}_REP_P.txt\"");

echo $conteudo;
exit;
