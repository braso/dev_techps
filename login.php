<!DOCTYPE html>
<!-- 
	Template Name: Metronic - Responsive Admin Dashboard Template build with Twitter Bootstrap 3.3.6
	Version: 4.5.4
	Author: KeenThemes
	Website: http://www.keenthemes.com/
	Contact: support@keenthemes.com
	Follow: www.twitter.com/keenthemes
	Like: www.facebook.com/keenthemes
	Purchase: http://themeforest.net/item/metronic-responsive-admin-dashboard-template/4021469?ref=keenthemes
	License: You must have a valid license purchased only from themeforest(the above link) in order to legally use the theme for your project.
-->
<!--[if IE 8]> <html lang="en" class="ie8 no-js"> <![endif]-->
<!--[if IE 9]> <html lang="en" class="ie9 no-js"> <![endif]-->
<html lang="pt-BR">
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
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css" />
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/uniform/css/uniform.default.css" rel="stylesheet" type="text/css" />
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet" type="text/css" />
		<!-- FIM GLOBAL MANDATORY STYLES -->
		<!-- COMECO PLUGINS DE PAGINA -->
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" type="text/css" />
		<!-- FIM PLUGINS DE PAGINA -->
		<!-- COMECO THEME GLOBAL STYLES -->
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/css/components.min.css" rel="stylesheet" id="style_components" type="text/css" />
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/css/plugins.min.css" rel="stylesheet" type="text/css" />
		<!-- FIM THEME GLOBAL STYLES -->
		<!-- COMECO PAGE LEVEL STYLES -->
		<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/pages/css/login.min.css" rel="stylesheet" type="text/css" />
		<!-- FIM PAGE LEVEL STYLES -->
		<!-- COMECO THEME LAYOUT STYLES -->
		<!-- FIM THEME LAYOUT STYLES -->
		<link rel="apple-touch-icon" sizes="180x180" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/favicon-16x16.png">
		<link rel="shortcut icon" type="image/x-icon" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/favicon-32x32.png?v=2">
		<link rel="manifest" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/site.webmanifest">
	</head>
	<!-- FIM HEAD -->
	<body class="login">
		<!-- COMECO LOGO -->
		<div class="logo">
			<a href="https://techps.com.br/">
				<img src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/logo.png" alt="" /> </a>
		</div>
		<!-- FIM LOGO -->
		<!-- COMECO LOGIN -->
		<div class="content">
			<!-- COMECO LOGIN FORM -->
			<form class="login-form" method="post">
				<h3 class="form-title font-green">Login <?=(is_int(strpos($_SERVER["REQUEST_URI"], "dev"))? "(Dev)": "")?></h3>
				<!--Vem do arquivo empresas.php -->
				<?=$empresasInput?>

				<div class="form-group">
					<!--ie8, ie9 does not support html5 placeholder, so we just show field title for that-->
					<input 
						class="form-control form-control-solid placeholder-no-fix" 
						type="text"
						autocomplete="off" 
						placeholder="Usuário" 
						name="user"
						<?=(!empty($_POST["user"])? "value=".$_POST["user"]: "")?>
					/>
				</div>
				<div class="form-group">
					<input 
						class="form-control form-control-solid placeholder-no-fix" 
						type="password" 
						autocomplete="off"
						placeholder="Senha" 
						name="password"
					/>
				</div>
				<a href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/recupera_senha.php"?>" id="forget-password" class="forget-password">Esqueceu sua senha?</a>
				<?=(!empty($_POST["sourcePage"])? "<input type='hidden' name='sourcePage' value= '".$_POST["sourcePage"]."'/>": "")?>
				<?= $msg ?>
				<div class="form-actions">
					<input type="submit" class="btn green uppercase" name="botao" value="Entrar"></input>
				</div>
				<p style="font-size: small; margin: 10px 0px">Versão:
					<?= $version; ?><br>
					Data de lançamento:
					<?= $release_date; ?>
				</p>
			</form>
			<!-- FIM LOGIN FORM -->
		</div>
		<div class="copyright">
			<?= date("Y") ?> © TechPS.
		</div>
		<!--[if lt IE 9]>
		<![endif]-->
		<!-- COMECO PLUGINS PRINCIPAL -->
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js" type="text/javascript"></script>
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>
		<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/uniform/jquery.uniform.min.js" type="text/javascript"></script>

		<?=$dataScript?>
		<!-- FIM PLUGINS PRINCIPAL -->
		<!-- COMECO PLUGINS DE PAGINA -->
		<!-- FIM PLUGINS DE PAGINA -->
		<!-- COMECO SCRIPTS GLOBAL -->
		<!-- FIM SCRIPTS GLOBAL -->
		<!-- COMECO PAGE LEVEL SCRIPTS -->
		<!-- FIM PAGE LEVEL SCRIPTS -->
		<!-- COMECO THEME LAYOUT SCRIPTS -->
		<!-- FIM THEME LAYOUT SCRIPTS -->
	</body>