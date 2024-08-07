<?php
    /* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
    //*/
	
    include_once $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/PHPMailer/src/Exception.php";
    include_once $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/PHPMailer/src/PHPMailer.php";
    include_once $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/PHPMailer/src/SMTP.php";

    use \PHPMailer\PHPMailer\PHPMailer;
    use \PHPMailer\PHPMailer\Exception;

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