<?php
// include 'conecta.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//SMTP needs accurate times, and the PHP time zone MUST be set
//This should be done in your php.ini, but this is how to do it if you don't have access to that
date_default_timezone_set('America/Fortaleza');

require getcwd().'PHPMailer/src/Exception.php';
require getcwd().'PHPMailer/src/PHPMailer.php';
require getcwd().'PHPMailer/src/SMTP.php';

//Create a new PHPMailer instance
$mail = new PHPMailer();
//Tell PHPMailer to use SMTP
$mail->isSMTP();
//Enable SMTP debugging
//SMTP::DEBUG_OFF = off (for production use)
//SMTP::DEBUG_CLIENT = client messages
//SMTP::DEBUG_SERVER = client and server messages
$mail->SMTPDebug = SMTP::DEBUG_SERVER;
//Set the hostname of the mail server
$mail->Host = 'mail.braso.mobi'; 
//Set the SMTP port number - likely to be 25, 465 or 587
$mail->Port = 465;
//Whether to use SMTP authentication
$mail->SMTPAuth = true;
//Username to use for SMTP authentication
$mail->Username = 'suporte_techps@braso.mobi';
//Password to use for SMTP authentication
$mail->Password = 'm&ic=p{tg15#';
//Set who the message is to be sent from
$mail->setFrom('suporte_techps@braso.mobi', 'Teste');
//Set an alternative reply-to address
$mail->addAddress('wallacealanmorais@gmail.com');
//Set who the message is to be sent to
$mail->addReplyTo('suporte_techps@braso.mobi', 'Information');

$mail->isHTML(true);                                  //Set email format to HTML
$mail->Subject = 'Here is the subject';
$mail->Body    = 'This is the HTML message body <b>in bold!</b>';
$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

//send the message, check for errors
if (!$mail->send()) {
    echo 'Mailer Error: ' . $mail->ErrorInfo;
} else {
    echo 'Message sent!';
}