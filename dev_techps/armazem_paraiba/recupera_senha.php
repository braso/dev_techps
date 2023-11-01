<?php
include "conecta.php";
global $CONTEX;

include_once (getcwd() . "/../") . "/PHPMailer/src/Exception.php";
include_once (getcwd() . "/../") . "/PHPMailer/src/PHPMailer.php";
include_once (getcwd() . "/../") . "/PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


if (!empty($_POST['senha']) && !empty($_POST['senha'])) {
    if ($_POST['senha'] === $_POST['senha2']) {
        $userSql = query("SELECT user_nb_id FROM `user` WHERE user_tx_token = '$_GET[token]'");
        $userId = mysqli_fetch_assoc($userSql);
        var_dump(md5($_POST['senha']));
        atualizar('user', ['user_tx_senha', 'user_tx_token'], [md5($_POST['senha']), '-'], $userId['user_nb_id']);
    }
}

if ($_POST['botao'] == 'ENVIAR') {
    $email = $_POST['email'];

    if (!empty($email))
        tokenGenerate($email);
}

function tokenGenerate($email) {
    $token = bin2hex(random_bytes(16));
    $userSql = query("SELECT user_nb_id FROM `user` WHERE user_tx_email = '$email'");
    $userId = mysqli_fetch_assoc($userSql);
    atualizar('user', ['user_tx_token'], [$token], $userId['user_nb_id']);
    sendEmail($email, $token);
}


function sendEmail($destinatario, $token) {
    global $CONTEX;

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
        $mail->Subject = 'Redefinição de Senha';
        $mail->Body = '<b>Redefinição de Senha</b><br>
        Por favor,<a href="https://braso.mobi' . $CONTEX['path'] . '/recupera_senha.php?token=' . $token . '">clique aqui</a> para resetar sua senha.<br>
        Caso você não tenha solicitado este e-mail de redefinição de senha, por favor, <a href="mailto:suporte_techps@braso.mobi">entre em contato</a para que possamos resolver o problema.';
        $mail->AltBody = "Link para recupera senha: braso.mobi" . $CONTEX['path'] . "/recupera_senha.php?token=" . $token;

        if ($mail->send()) {
            echo "E-mail enviado";
        }
    } catch (Exception $exception) {
        echo "Erro ao enviar e-mail: {$mail->ErrorInfo}";
    }
}

?>
<!DOCTYPE html>

<html lang="pt-BR">

<!--<![endif]-->

<!-- COMECO HEAD -->



<head>

    <meta charset="utf-8" />

    <title>TechPS</title>

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta content="width=device-width, initial-scale=1" name="viewport" />

    <meta content="" name="description" />

    <meta content="" name="author" />

    <!-- COMECO GLOBAL MANDATORY STYLES -->

    <link href="http://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css" />

    <link href="/contex20/assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />

    <link href="/contex20/assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css" />

    <link href="/contex20/assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />

    <link href="/contex20/assets/global/plugins/uniform/css/uniform.default.css" rel="stylesheet" type="text/css" />

    <link href="/contex20/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet" type="text/css" />

    <!-- FIM GLOBAL MANDATORY STYLES -->

    <!-- COMECO PLUGINS DE PAGINA -->

    <link href="/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />

    <link href="/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" type="text/css" />

    <!-- FIM PLUGINS DE PAGINA -->

    <!-- COMECO THEME GLOBAL STYLES -->

    <link href="/contex20/assets/global/css/components.min.css" rel="stylesheet" id="style_components" type="text/css" />

    <link href="/contex20/assets/global/css/plugins.min.css" rel="stylesheet" type="text/css" />

    <!-- FIM THEME GLOBAL STYLES -->

    <!-- COMECO PAGE LEVEL STYLES -->

    <link href="/contex20/assets/pages/css/login.min.css" rel="stylesheet" type="text/css" />

    <!-- FIM PAGE LEVEL STYLES -->

    <!-- COMECO THEME LAYOUT STYLES -->

    <!-- FIM THEME LAYOUT STYLES -->

    <link rel="shortcut icon" href="favicon.ico" />
</head>

<!-- FIM HEAD -->



<body class=" login">

    <!-- COMECO LOGO -->

    <div class="logo">

        <a href="index.php">

            <img src="../contex20/img/logo.png" alt="" /> </a>

    </div>

    <!-- FIM LOGO -->

    <!-- COMECO LOGIN -->

    <div class="content">

        <!-- COMECO LOGIN FORM -->

        <form class="login-form" method="post">
                <?
                if (empty($_GET['token'])) {
                    ?>
                    <h3 class="form-title font-green">Redefinir Senha</h3>
                    <p style="text-align:justify">Um link de redefinição de senha será enviado para o seu endereço de e-mail.</p>
                    <div class="form-group">
                        <label class="control-label visible-ie8 visible-ie9">E-mail</label>
                        <input focus autofocus class="form-control form-control-solid placeholder-no-fix" type="email" autocomplete="off" placeholder="E-mail" name="email" />
                    </div>
                    <?= $msg ?>
                    <div class="form-actions" style="padding: 26px 128px !important">
                        <input type="submit" class="btn green uppercase" name="botao" value="ENVIAR"></input>
                    </div>
                    <?
                } else {
                ?>
                <h3 class="form-title font-green">Redifinição de Senha</h3>
                    <div class="form-group">
                        <label class="control-label visible-ie8 visible-ie9">Senha</label>
                        <input focus autofocus class="form-control form-control-solid placeholder-no-fix" type="password" autocomplete="off" placeholder="Senha" name="senha" />
                    </div>

                    <div class="form-group">
                        <label class="control-label visible-ie8 visible-ie9">Confirmar Senha</label>
                        <input focus autofocus class="form-control form-control-solid placeholder-no-fix" type="password" autocomplete="off" placeholder="Confirmar Senha" name="senha2" />
                    </div>

                    <?= $msg ?>
                    <div class="form-actions" style="padding: 26px 128px !important">
                        <input type="submit" class="btn green uppercase" name="botao" value="Redefinir senha"></input>
                    </div>
                <?
                }
                ?>
        </form>

        <!-- FIM LOGIN FORM -->

    </div>

    <div class="copyright"> <?= date("Y") ?> © TechPS. </div>

    <!-- COMECO PLUGINS PRINCIPAL -->

    <script src="/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>

    <script src="/contex20/assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>

    <script src="/contex20/assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>

    <script src="/contex20/assets/global/plugins/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js" type="text/javascript"></script>

    <script src="/contex20/assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>

    <script src="/contex20/assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>

    <script src="/contex20/assets/global/plugins/uniform/jquery.uniform.min.js" type="text/javascript"></script>


</body>



</html>