<?php
    /* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
    //*/
	
include_once (getcwd()."/../")."PHPMailer/src/Exception.php";
include_once (getcwd()."/../")."PHPMailer/src/PHPMailer.php";
include_once (getcwd()."/../")."PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmailAlerta($destinatario, $nomeDestinatario, $msg) {
    global $CONTEX;
    
    $caminho = getcwd();

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();

        // Configurações do servidor
        $mail->CharSet = 'UTF-8';
        $mail->Host = 'mail.braso.mobi';
        $mail->SMTPAuth = true;
        $mail->Username = 'suporte_techps@braso.mobi';
        $mail->Password = 'm&ic=p{tg15#';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Remetente e Destinatários
        $mail->setFrom('suporte_techps@braso.mobi', 'Tech PS');
        $mail->addAddress($destinatario, $nomeDestinatario);
        $mail->addReplyTo('suporte_techps@braso.mobi', 'Tech PS Suporte');
        // $mail->addCC('wallacealanmorais@gmail.com');
        // $mail->addBCC('wallacealanmorais@gmail.com');

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = 'Alerta De Atualização Dos Pontos';
        $mail->Body = "<p>$msg</p>";
        $mail->Encoding = 'base64';

        if ($mail->send()) {
            return ;
        }

    } catch (Exception $exception) {
        return $mail->ErrorInfo;
    }
}

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
    $msg = '';

    $sqlCheck = query("SELECT * FROM configuracao_alerta LIMIT 1");
	$emails = mysqli_fetch_assoc($sqlCheck);
    // Verifica ser possui diferença de 2 dias
    if ((int)$diferenca == 2 || $diferenca <= 6){
        $msg = "Faz $diferenca dias que o arquivo de ponto no dominio $dominio não é atualizado.";
        sendEmailAlerta($emails['conf_tx_emailFun'],$emails['conf_tx_emailFun'],$msg);
    } elseif ((int)$diferenca  >= 7) {
        $msg = "Faz $diferenca dias que o arquivo de ponto no dominio $dominio não é atualizado.";
        sendEmailAlerta($emails['conf_tx_emailAdm'],$emails['conf_tx_emailAdm'],$msg);
    }
}