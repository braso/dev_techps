<?php

include_once (getcwd()."/../")."/PHPMailer/src/Exception.php";
include_once (getcwd()."/../")."/PHPMailer/src/PHPMailer.php";
include_once (getcwd()."/../")."/PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmail($destinatario) {
    
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();

        // Configurações do servidor
        $mail->Host = 'mail.braso.mobi';
        $mail->SMTPAuth = true;
        $mail->Username = 'suporte_techps@braso.mobi';
        $mail->Password = 'm&ic=p{tg15#';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Remetente e Destinatários
        $mail->setFrom('suporte_techps@braso.mobi', 'Nome do Remetente');
        $mail->addAddress($destinatario, 'Primeiro Destinatário');
        $mail->addReplyTo('suporte_techps@braso.mobi', 'Nome de para quem responder');
        // $mail->addCC('wallacealanmorais@gmail.com');
        // $mail->addBCC('wallacealanmorais@gmail.com');

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = 'Assunto aqui';
        $mail->Body = 'Esse é o corpo da mensagem em HTML <b>em negrito!</b>';
        $mail->AltBody = 'Esse é o corpo da mensagem em "texto puro" para clientes que não suportam HTML';

        if($mail->send()){
            echo "E-mail enviado";
        }
    } catch (Exception $exception) {
        echo "Erro ao enviar e-mail: {$mail->ErrorInfo}";
    }
}

sendEmail('wallacealanmorais@gmail.com');
