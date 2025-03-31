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

		<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css" />

		<link href="./contex20/assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />

		<link href="./contex20/assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css" />

		<link href="./contex20/assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />

		<link href="./contex20/assets/global/plugins/uniform/css/uniform.default.css" rel="stylesheet" type="text/css" />

		<link href="./contex20/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet" type="text/css" />

		<!-- FIM GLOBAL MANDATORY STYLES -->

		<!-- COMECO PLUGINS DE PAGINA -->

		<link href="./contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />

		<link href="./contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" type="text/css" />

		<!-- FIM PLUGINS DE PAGINA -->

		<!-- COMECO THEME GLOBAL STYLES -->

		<link href="./contex20/assets/global/css/components.min.css" rel="stylesheet" id="style_components" type="text/css" />

		<link href="./contex20/assets/global/css/plugins.min.css" rel="stylesheet" type="text/css" />

		<!-- FIM THEME GLOBAL STYLES -->

		<!-- COMECO PAGE LEVEL STYLES -->

		<link href="./contex20/assets/pages/css/login.min.css" rel="stylesheet" type="text/css" />

		<!-- FIM PAGE LEVEL STYLES -->

		<!-- COMECO THEME LAYOUT STYLES -->

		<!-- FIM THEME LAYOUT STYLES -->

		<link rel='apple-touch-icon' sizes='180x180' href='./contex20/img/favicon/apple-touch-icon.png'>
		<link rel='icon' type='image/png' sizes='32x32' href='./contex20/img/favicon/favicon-32x32.png'>
		<link rel='icon' type='image/png' sizes='16x16' href='./contex20/img/favicon/favicon-16x16.png'>
		<link rel='shortcut icon' type='image/x-icon' href='./contex20/img/favicon/favicon-32x32.png?v=2'>
		<link rel='manifest' href='./contex20/img/favicon/site.webmanifest'>
	</head>
	<!-- FIM HEAD -->
	<body class=" login">

		<!-- COMECO LOGO -->

		<div class="logo">
			<a href="index.php">
			<img id="logo" src="" alt="" /> </a>
		</div>

		<!-- FIM LOGO -->

		<!-- COMECO LOGIN -->

		<div class="content">

			<!-- COMECO LOGIN FORM -->

			<form class="login-form" method="post">
				<div id="no-domain-selected" hidden>
					<h3 class="form-title font-green"></h3>
						<p style="text-align:justify">Um link de redefinição de senha será enviado para o seu endereço de e-mail.</p>
						<?=$empresasInput?>
						<div class="form-group">
							<label class="control-label visible-ie8 visible-ie9">Login</label>
							<input focus autofocus class="form-control form-control-solid placeholder-no-fix" type="text" autocomplete="off" placeholder="Login" name="login" />
						</div>
						<?= $msg ?>
						<?php 
							if(!empty($msg)){
								echo 
									'<style>
										#enviar{
											display:none;
										}
									</style>'
								;
							}
						?>
						<div class="form-actions">
							<a href="index.php" style="align-content: center; padding-right: 20px;">Voltar</a>
							<input type="submit" class="btn green uppercase" name="botao" value="ENVIAR">
						</div>
				</div>
				<div id="domain-selected" hidden>
					<h3 class="form-title font-green"></h3>
					<div class="form-group">
						<label class="control-label visible-ie8 visible-ie9">Senha</label>
						<input focus autofocus class="form-control form-control-solid placeholder-no-fix" type="password" autocomplete="off" placeholder="Senha" name="senha" />
					</div>

					<div class="form-group">
						<label class="control-label visible-ie8 visible-ie9">Confirmar Senha</label>
						<input focus autofocus class="form-control form-control-solid placeholder-no-fix" type="password" autocomplete="off" placeholder="Confirmar Senha" name="senha2" />
					</div>
					<?= $msg ?>
					<div class="form-actions">
						<input type="submit" class="btn green uppercase" name="botao" value="Redefinir senha"></input>
					</div>
				</div>
			</form>

			<!-- FIM LOGIN FORM -->

		</div>

		<div class="copyright"> <?= date("Y") ?> © TechPS.</div>

		<!-- COMECO PLUGINS PRINCIPAL -->

		<script src="./contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>

		<script src="./contex20/assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>

		<script src="./contex20/assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>

		<script src="./contex20/assets/global/plugins/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js" type="text/javascript"></script>

		<script src="./contex20/assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>

		<script src="./contex20/assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>

		<script src="./contex20/assets/global/plugins/uniform/jquery.uniform.min.js" type="text/javascript"></script>
		
		<script>
			function redirectIndex() {
				<?php
			$dominio = $_GET['dominio'];
			include $dominio."/conecta.php";
			global $CONTEX;?>
				window.location.href = "<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"] ?>/index.php";
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
