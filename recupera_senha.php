<?php
    //* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

    include_once "./PHPMailer/src/Exception.php";
    include_once "./PHPMailer/src/PHPMailer.php";
    include_once "./PHPMailer/src/SMTP.php";
    include_once "load_env.php";

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    function extrairEmpresa($key, $empresas) {
        $key = strtoupper($key);
        return !empty($empresas[$key])? $empresas[$key]: null;
    }

    function tokenGenerate($login, $domain) {
        $token = bin2hex(random_bytes(16));

        $user = mysqli_fetch_assoc(query(
            "SELECT user_nb_id, user_tx_nome, user_tx_email FROM user
                WHERE user_tx_status = 'ativo'
                    AND user_tx_login = '{$login}';"
        ));
        if(!empty($user)){
            atualizar("user", ["user_tx_token"], [$token], $user["user_nb_id"]);
            return sendEmail($user["user_tx_email"], $token, $user["user_tx_nome"], $domain);
        }else{
            return 
                "<div id='erro' style='background-color: red; padding: 1px; text-align: center;'>
                    <h4 style='color: white;'><strong> Usuário não encontrado </strong></h4>
                </div>";
        }
    }

    function obscureEmail(string $email): string{
        [$user, $domain] = explode("@", $email);
        $userLength = strlen($user);
        $obscureLength = ceil($userLength*0.8); // Calcula 80% do comprimento do usuário
        $visibleLength = $userLength-$obscureLength;
    
        // Cria a parte obscurecida do usuário
        $obscuredUser = substr($user, 0, $visibleLength).str_repeat("*", $obscureLength);
    
        return "{$obscuredUser}@{$domain}";
    }


    function sendEmail($destinatario, $token, $nomeDestinatario, $domain) {
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
            $mail->Body = 
                "<b>Redefinição de Senha</b>
                <br>
                Clique <a href='{$_ENV["URL_BASE"]}/".basename($caminho)."/recupera_senha.php?empresa={$domain}&token={$token}'>aqui</a> para resetar sua senha.
                <br>
                Caso você não tenha solicitado este e-mail de redefinição de senha, por favor, <a href='mailto:suporte@techps.com.br'>entre em contato</a> para que possamos resolver o problema."
            ;
            $mail->Encoding = "base64";
            $mail->AltBody = "Link para recuperar senha: {$_ENV["URL_BASE"]}/".basename($caminho)."/recupera_senha.php?token={$token}";

            $obscuredEmail = obscureEmail($destinatario);
            if($mail->send()){
                return 
                    "<div id='enviado' style='text-align: center;'>
                        <h4>E-mail enviado para {$obscuredEmail}</h4>
                    </div>";
            }
        }catch(Exception $e){
            return 
                "<div id='erro' style='text-align: center;'>
                    <h4>Erro ao enviar e-mail: {$mail->ErrorInfo}</h4>
                </div>
                <script>console.log('{$e->getMessage()}')</script>";
        }
    }

    function index(string $msg = ""){

        include "empresas.php";

        if(!empty($_GET["empresa"])){
            $interno = true; //Utilizado em conecta.php;
            include __DIR__."/{$_GET["empresa"]}/conecta.php";
        }

        if (!empty($_POST["botao"])){
            $empresa = extrairEmpresa($_POST["empresa"], $empresas);
            
            
            if($_POST["botao"] == "ENVIAR"){
                if(!empty($empresa)){
                    if (!empty($_POST["login"])){
                        $token = bin2hex(random_bytes(16));
    
                        $user = mysqli_fetch_assoc(query(
                            "SELECT user_nb_id, user_tx_nome, user_tx_email FROM user
                                WHERE user_tx_status = 'ativo'
                                    AND user_tx_login = '{$_POST["login"]}';"
                        ));
    
                        if(!empty($user)){
                            atualizar("user", ["user_tx_token"], [$token], $user["user_nb_id"]);
                            $msg = sendEmail($user["user_tx_email"], $token, $user["user_tx_nome"], $empresa);
                        }else{
                            $msg = 
                                "<div id='erro' style='background-color: red; padding: 1px; text-align: center;'>
                                    <h4 style='color: white;'><strong> Usuário não encontrado </strong></h4>
                                </div>";
                        }
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
            }elseif($_POST["botao"] == "Redefinir senha"){
                $user = mysqli_fetch_assoc(query(
                    "SELECT user_nb_id FROM user 
                        WHERE user_tx_status = 'ativo' 
                            AND user_tx_token = '{$_GET["token"]}';"
                ));
                
                if(!empty($_POST["senha"]) && !empty($_POST["senha2"]) && $_POST["senha"] == $_POST["senha2"]){
                        atualizar("user", ["user_tx_senha", "user_tx_token"], [md5($_POST["senha"]), "-"], $user["user_nb_id"]);
                        $msg = 
                            "<div id='redefinido' style='background-color:rgb(0, 125, 21); padding: 1px; text-align: center;'>
                                <h4 style = 'color: #fff !important;'>Senha Redefinida. Voltando para o login...</h4>
                            </div>"
                        ;
                }else{
                    $msg = 
                        "<div id='erro' style='background-color: red; padding: 1px; text-align: center;'>
                            <h4>Confirmação de senha incorreta</h4>
                        </div>"
                    ;
                }
            }
        }
        

        if(!empty($_POST["appRequest"])){
            echo $msg;
            exit;
        }

        if(empty($_GET["token"])){

            //Utilizado em recupera_senha_html.php
            $domainDiv = 
                "<div id='no-domain-selected'>
                        <h3 class='form-title font-green'></h3>
                            <p style='text-align:justify'>Um link de redefinição de senha será enviado para o seu endereço de e-mail.</p>
                            {$empresasInput}
                            <div class='form-group'>
                                <label class='control-label visible-ie8 visible-ie9'>Login</label>
                                <input focus autofocus class='form-control form-control-solid placeholder-no-fix' type='text' autocomplete='off' placeholder='Login' name='login' />
                            </div>
                            ".(!empty($msg)?
                                "{$msg}
                                <style>
                                    #enviar{
                                        display:none;
                                    }
                                </style>":
                                "")
                            ."<div class='form-actions'>
                                <a href='index.php' style='align-content: center; padding-right: 20px;'>Voltar</a>
                                <input type='submit' class='btn green uppercase' name='botao' value='ENVIAR'>
                            </div>
                    </div>"
            ;
        }else{

            $user = mysqli_fetch_assoc(query(
                "SELECT user_nb_id FROM user 
                    WHERE user_tx_status = 'ativo' 
                        AND user_tx_token = '{$_GET["token"]}';"
            ));
            if(empty($user)){
                echo 
                    "<script>alert('Link já utilizado ou inválido, por favor solicite novamente a redefinição de senha.')</script>
                    <meta http-equiv='refresh' content='0; url={$_ENV["URL_BASE"]}{$_ENV["APP_PATH"]}/index.php' />"
                ;
                exit;
            }
            unset($user);
            
            
            //Utilizado em recupera_senha_html.php
            $domainDiv = 
                "<div id='domain-selected'>
					<h3 class='form-title font-green'></h3>
					<div class='form-group'>
						<label class='control-label visible-ie8 visible-ie9'>Senha</label>
						<input focus autofocus class='form-control form-control-solid placeholder-no-fix' type='password' autocomplete='off' placeholder='Senha' name='senha' />
					</div>

					<div class='form-group'>
						<label class='control-label visible-ie8 visible-ie9'>Confirmar Senha</label>
						<input focus autofocus class='form-control form-control-solid placeholder-no-fix' type='password' autocomplete='off' placeholder='Confirmar Senha' name='senha2' />
					</div>
					".($msg?? "")."
					<div class='form-actions'>
						<input type='submit' class='btn green uppercase' name='botao' value='Redefinir senha'></input>
					</div>
				</div>"
            ;
        }

        include "recupera_senha_html.php";
        echo 
            "<script>
                document.getElementById('logo').src = '{$_ENV["URL_BASE"]}{$_ENV["APP_PATH"]}/contex20/img/logo.png';
                document.getElementsByClassName('form-title font-green')[0].innerHTML = '".(empty($_GET["token"])?
                    "Redefinir Senha":
                    "Redefinição de Senha<br>{$empresasNomes[$_GET["empresa"]]}")."';
            </script>"
        ;
    }

if(empty($_POST) || (!empty($_POST["acao"]) && $_POST["acao"] == "index")){
    index();
}