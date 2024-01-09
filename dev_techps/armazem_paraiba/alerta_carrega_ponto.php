<?php
//  Modo debug
		// ini_set('display_errors', 1);
		// error_reporting(E_ALL);
	

include "conecta.php";
include_once (getcwd()."/../")."PHPMailer/src/Exception.php";
include_once (getcwd()."/../")."PHPMailer/src/PHPMailer.php";
include_once (getcwd()."/../")."PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function diffData($dataAtual){
    global $CONTEX;
	$sqlCheck = query("SELECT arqu_tx_data FROM arquivoponto WHERE arqu_tx_status = 'ativo' ORDER BY arqu_tx_data DESC LIMIT 1;");
    $dataUltimoArquivo = mysqli_fetch_assoc($sqlCheck);

    // Obter a data atual no formato 'dmY'
    $dataAtualString = $dataAtual;

    // Converter a string 'dmY' em um objeto DateTime
    $dataAtual = DateTime::createFromFormat('dmY', $dataAtualString);

    // Converter a string 'Y-m-d H:i:s' em um objeto DateTime
    $dataEspecifica = DateTime::createFromFormat('Y-m-d H:i:s', $dataUltimoArquivo["arqu_tx_data"]);

    // Calcular a diferença
    $diferenca = $dataAtual->diff($dataEspecifica)->format('%a');

    $dominio = substr($CONTEX['path'],12);
    // Verifica ser possui diferença de 2 dias
    if ($diferenca == "2" && $diferenca < "6"){
        $msg = "Faz $diferenca dias que o arquivo de ponto no dominio $dominio não é atualizado.";
    } elseif ($diferenca  >= "7") {
        $msg = "Faz $diferenca dias que o arquivo de ponto no dominio $dominio não é atualizado.";
    }
}

diffData(date('dmY'));