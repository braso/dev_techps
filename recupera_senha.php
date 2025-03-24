<?php

    /* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/
    $interno = true;
    global $CONTEX;

    include_once "./PHPMailer/src/Exception.php";
    include_once "./PHPMailer/src/PHPMailer.php";
    include_once "./PHPMailer/src/SMTP.php";
    include_once "load_env.php";
    include_once 'empresas.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

// $_POST['botao'] = '';
// $msg = '';

    function extrairDominio($url, $empresa_array) {
        $parsed_url = parse_url($url);
        $path_segments = explode('/', $parsed_url['path']);

        $dominio = $path_segments[1] ?? '';

        return in_array($dominio, $empresa_array) ? $dominio : null;
    }

    if ($_POST['botao'] == 'ENVIAR') {
        $dominio_url = $_POST['dominio'];
        $dominio = extrairDominio($dominio_url, $empresa_array);
        $login = $_POST['login'];
        
        if(!empty($dominio)){
            include __DIR__.'/'.$dominio."/conecta.php";
            
            global $CONTEX;
            if (!empty($login)){
                $msg = tokenGenerate($login, $dominio);
            }else{ 
                $msg = 
                    "<div class='alert alert-danger display-block'>
                        <span> Campo E-mail ou Login não foi preenchido </span>
                    </div>"
                ;
            }
        }else{
            $msg = 
                "<div class='alert alert-danger display-block'>
                    <span> Informe a sua empresa </span>
                </div>"
            ;
        }

    }

    if ($_POST['botao'] == 'Redefinir senha') {
        $dominio = $_GET['dominio'];
        include $dominio."/conecta.php";
        
        $token = $_GET['token'];
        $checkTokenSql = query("SELECT user_nb_id FROM user WHERE user_tx_token = '$_GET[token]'");
        $checkToken = mysqli_fetch_assoc($checkTokenSql);

        if (!isset($checkToken) && empty($checkToken)) {
            echo '<script>alert("Link já utilizado ou invalido, por favor solicita novamente a  redefinição de senha.  ")</script>';
            echo "<meta http-equiv='refresh' content='0; url=".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/index.php' />";
            exit;
        }
        
        if (!empty($_POST['senha']) && !empty($_POST['senha2']) && $_POST['senha'] == $_POST['senha2']) {
                $userSql = query("SELECT user_nb_id FROM user WHERE user_tx_token = '$_GET[token]'");
                $userId = mysqli_fetch_assoc($userSql);
                atualizar('user', ['user_tx_senha', 'user_tx_token'], [md5($_POST['senha']), '-'], $userId['user_nb_id']);
                $msg = "
                <div id='redefinido' style='background-color: #0af731; padding: 1px; text-align: center;'>
                    <h4 style = 'color: #fff !important;'>Senha Redefinida.</h4>
                </div>";
        } else {
            $msg = '
            <div id="erro" style="background-color: red; padding: 1px; text-align: center;">
                <h4>Confirmação de senha incorreta</h4>
            </div>';
        }
    }


    function tokenGenerate($login, $domain) {
        $token = bin2hex(random_bytes(16));

        $userSql = query("SELECT user_nb_id, user_tx_nome, user_tx_email FROM user WHERE user_tx_login = '$login' AND user_tx_status = 'ativo'");
        $userId = mysqli_fetch_assoc($userSql);
        if(!empty($userId)){
            atualizar('user', ['user_tx_token'], [$token], $userId['user_nb_id']);
            return sendEmail($userId['user_tx_email'], $token, $userId['user_tx_nome'], $domain);
        } else
            return '
            <div id="erro" style="background-color: red; padding: 1px; text-align: center;">
                <h4 style="color: white;"><strong> Informações não estão corretas </strong></h4>
            </div>';
    }

    function obscureEmail($email) {
        list($user, $domain) = explode('@', $email);
        $userLength = strlen($user);
        $obscureLength = ceil($userLength * 0.8); // Calcula 80% do comprimento do usuário
        $visibleLength = $userLength - $obscureLength;
    
        // Cria a parte obscurecida do usuário
        $obscuredUser = substr($user, 0, $visibleLength).str_repeat('*', $obscureLength);
    
        return $obscuredUser.'@'.$domain;
    }


    function sendEmail($destinatario, $token, $nomeDestinatario, $domain) {
        global $CONTEX;
        
        $caminho = getcwd();

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();

            // Configurações do servidor
            $mail->CharSet = 'UTF-8';
            $mail->Host = 'smtp.titan.email';
            $mail->SMTPAuth = true;
            $mail->Username = 'suporte@techps.com.br';
            $mail->Password = 'gX%]b6qNe=Tg]56';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            // Remetente e Destinatários
            $mail->setFrom('suporte@techps.com.br', 'Tech PS');
            $mail->addAddress($destinatario, $nomeDestinatario);
            $mail->addReplyTo('suporte@techps.com.br', 'Tech PS Suporte');
            // $mail->addCC('wallacealanmorais@gmail.com');
            // $mail->addBCC('wallacealanmorais@gmail.com');

            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = 'Redefinição de Senha';
            $mail->Body = '<b>Redefinição de Senha</b><br>
            Por favor, <a href="'.$_ENV["URL_BASE"].'/'.basename($caminho).'/recupera_senha.php?dominio='.$domain.'&token='.$token.'">clique aqui</a> para resetar sua senha.<br>
            Caso você não tenha solicitado este e-mail de redefinição de senha, por favor, <a href="mailto:suporte@techps.com.br ">entre em contato</a> para que possamos resolver o problema.';
            $mail->Encoding = 'base64';
            $mail->AltBody = "Link para recupera senha: ".$_ENV["URL_BASE"].'/'.basename($caminho)."/recupera_senha.php?token=".$token;

            $obscuredEmail = obscureEmail($destinatario);
            if ($mail->send()) {
                return "
                <div id='enviado' style='text-align: center;'>
                    <h4>E-mail enviado para $obscuredEmail</h4>
                </div>";
            }
        } catch (Exception $exception) {
            return "
            <div id='erro' style='text-align: center;'>
                <h4>Erro ao enviar e-mail: {$mail->ErrorInfo}</h4>
            </div>";
        }
    }

    if(!empty($_POST["appRequest"])){
        echo $msg;
        exit;
    }

    include "recupera_senha_html.php";

    echo 
        "<script>
            document.getElementById('logo').src = '".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/img/logo.png';
            if(".(empty($_GET['token'])? "1": "0")."){
                document.getElementById('no-domain-selected').hidden = false;
                document.getElementsByClassName('form-title font-green')[0].innerHTML = 'Redefinir Senha';
            }else{
                document.getElementById('domain-selected').hidden = false;
                document.getElementsByClassName('form-title font-green')[1].innerHTML = 'Redefinição de Senha - {$_GET["dominio"]}';
            }
        </script>"
    ;