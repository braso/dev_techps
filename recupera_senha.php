<?php
// include "conecta.php";
global $CONTEX;

include_once "./PHPMailer/src/Exception.php";
include_once "./PHPMailer/src/PHPMailer.php";
include_once "./PHPMailer/src/SMTP.php";
include_once 'dominios.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


function extrairDominio($url, $dominio_array) {
    $parsed_url = parse_url($url);
    $path_segments = explode('/', $parsed_url['path']);
    $dominio = $path_segments[2] ?? '';

    return in_array($dominio, $dominio_array) ? $dominio : null;
}

if ($_POST['botao'] == 'ENVIAR') {
    $dominio_url = $_POST['dominio'];

    $dominio_array = [
        "techps",
        "braso",
        "armazem_paraiba",
        "opafrutas",
        "qualy_transportes",
        "feijao_turqueza"
    ];

    $dominio = extrairDominio($dominio_url, $dominio_array);

    $login = $_POST['login'];
    // $email = $_POST['email'];
    if(!empty($dominio)){
        include $dominio."/conecta.php";
        global $CONTEX;
        if (!empty($login))
            $msg = tokenGenerate($login, $dominio);
        else 
            $msg = '
            <div id="erro" style="background-color: red; padding: 1px; text-align: center;">
                <h4 style = "color: #fff !important;">Campo E-mail ou Login não foi preenchido </h4>
            </div>';
    } else
        $msg = '
        <div id="erro" style="background-color: red; padding: 1px; text-align: center;">
            <h4 style = "color: #fff !important;">Selecione um dominio</h4>
        </div>';

}

if ($_POST['botao'] == 'Redefinir senha') {
    $dominio = $_GET['dominio'];
    include $dominio."/conecta.php";
    
    if (!empty($_POST['senha']) && !empty($_POST['senha2']) && $_POST['senha'] == $_POST['senha2']) {
            $userSql = query("SELECT user_nb_id FROM `user` WHERE user_tx_token = '$_GET[token]'");
            $userId = mysqli_fetch_assoc($userSql);
            atualizar('user', ['user_tx_senha', 'user_tx_token'], [md5($_POST['senha']), '-'], $userId['user_nb_id']);
            $msg = "
            <div id='redefinido' style='background-color: #0af731; padding: 1px; text-align: center;'>
                <h4 style = 'color: #fff !important;'>Senha Redefinida.</h4>
            </div>";
    } else {
        $msg = '
        <div id="erro" style="background-color: red; padding: 1px; text-align: center;">
            <h4 style = "color: #fff !important;">Confirmação de senha incorreta</h4>
        </div>';
    }
}


function tokenGenerate($login, $domain) {
    $token = bin2hex(random_bytes(16));
    $userSql = query("SELECT user_nb_id, user_tx_nome, user_tx_email FROM `user` WHERE user_tx_login = '$login' AND user_tx_status != 'inativo'");
    $userId = mysqli_fetch_assoc($userSql);
    if(!empty($userId)){
        atualizar('user', ['user_tx_token'], [$token], $userId['user_nb_id']);
        return sendEmail($userId['user_tx_email'], $token, $userId['user_tx_nome'], $domain);
    } else
        return '
        <div id="erro" style="background-color: red; padding: 1px; text-align: center;">
            <h4 style = "color: #fff !important;"> As informações não estão corretas  </h4>
        </div>';
}


function sendEmail($destinatario, $token, $nomeDestinatario, $domain) {
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
        $mail->Subject = 'Redefinição de Senha';
        $mail->Body = '<b>Redefinição de Senha</b><br>
        Por favor, <a href="https://braso.mobi/' . basename($caminho)  . '/recupera_senha.php?dominio='.$domain.'&token=' . $token .'">clique aqui</a> para resetar sua senha.<br>
        Caso você não tenha solicitado este e-mail de redefinição de senha, por favor, <a href="mailto:suporte_techps@braso.mobi">entre em contato</a> para que possamos resolver o problema.';
        $mail->Encoding = 'base64';
        $mail->AltBody = "Link para recupera senha: braso.mobi" . basename($caminho)  . "/recupera_senha.php?token=" . $token;

        if ($mail->send()) {
            return "
            <div id='enviado' style='background-color: #0af731; padding: 1px; text-align: center;'>
                <h4 style = 'color: #fff !important;'>E-mail enviado para $destinatario</h4>
            </div>";
        }
    } catch (Exception $exception) {
        return '
        <div id="erro" style="background-color: red; padding: 1px; text-align: center;">
            <h4 style = "color: #fff !important;">Erro ao enviar e-mail: {$mail->ErrorInfo}</h4>
        </div>';
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

        <a href="index2.php">

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
                    <?php
                    echo $dominios;
                    ?>
                    <div class="form-group">
                        <label class="control-label visible-ie8 visible-ie9">Login</label>
                        <input focus autofocus class="form-control form-control-solid placeholder-no-fix" type="text" autocomplete="off" placeholder="Login" name="login" />
                    </div>
                    <?= $msg ?>
                    <div class="form-actions" style="padding: 26px 140px !important">
                        <input type="submit" class="btn green uppercase" name="botao" value="ENVIAR"></input>
                    </div>
                    <?
                } else {
                ?>
                <h3 class="form-title font-green">Redifinição de Senha - <?= $arrayDominio[$_GET['dominio']]; ?></h3>
                    <div class="form-group">
                        <label class="control-label visible-ie8 visible-ie9">Senha</label>
                        <input focus autofocus class="form-control form-control-solid placeholder-no-fix" type="password" autocomplete="off" placeholder="Senha" name="senha" />
                    </div>

                    <div class="form-group">
                        <label class="control-label visible-ie8 visible-ie9">Confirmar Senha</label>
                        <input focus autofocus class="form-control form-control-solid placeholder-no-fix" type="password" autocomplete="off" placeholder="Confirmar Senha" name="senha2" />
                    </div>
                    <?= $msg ?>
                    <div class="form-actions" style="padding: 26px 110px !important">
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
    
    <script>
        function redirectIndex() {
            <?php
    $dominio = $_GET['dominio'];
    include $dominio."/conecta.php";
    global $CONTEX;?>
            window.location.href = "https://braso.mobi<?=$CONTEX['path']?>/index.php";
        }
            
        function esconderErro() {
            var erroDiv = document.getElementById("erro");
            erroDiv.style.display = "none";
        }

        // Chama a função esconderErro após 10 segundos (10000 milissegundos)
        setTimeout(esconderErro, 10000);
        if(document.getElementById("redefinido")){
            setTimeout(redirectIndex, 5000);
        }
    </script>
</body>



</html>