<?php
include_once("version.php");
include $_SERVER['DOCUMENT_ROOT'] . "/sistema/conecta.php";

// $sql = query('SELECT * FROM domain');
// $result = mysqli_fetch_all($sql, MYSQLI_ASSOC);

if ($_GET['erro'] != '') {
	$msg = "<div class='alert alert-danger display-block'>

					<span> Login e/ou senha incorreto. </span>

				</div>";
}

if ($_POST['botao'] == 'Entrar') {
	if ($_POST['domain'] != '' && $_POST['login'] != '' && $_POST['senha'] != '') {
		$domain = $_POST['domain'];
		header("Location: " . $domain . '?user=' . $_POST['login'] . '&password=' . md5($_POST['senha']));
		exit;
	} else if($_POST['domain'] == '' && $_POST['login'] != '' && $_POST['senha'] != ''){
		$msg = "<div class='alert alert-danger display-block'>

					<span> Nenhum domínio selecionado.  </span>

				</div>";
	}
} else if ($_GET['erro'] != '') {
	$msg = "<div class='alert alert-danger display-block'>

					<span> Login e/ou senha incorreto. </span>

				</div>";
}
// else if ($_POST['botao'] == 'Entrar' && $_POST['login'] === '' && $_POST['senha'] === '' ) {

// 	$msg = "<div class='alert alert-danger display-block'>

// 					<span> Preencha o login e senha. </span>

// 				</div>";


// }
?>
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

<!--[if !IE]><!-->







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

	<link href="http://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet"
		type="text/css" />

	<link href="/contex20/assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet"
		type="text/css" />

	<link href="/contex20/assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet"
		type="text/css" />

	<link href="/contex20/assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />

	<link href="/contex20/assets/global/plugins/uniform/css/uniform.default.css" rel="stylesheet" type="text/css" />

	<link href="/contex20/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet"
		type="text/css" />

	<!-- FIM GLOBAL MANDATORY STYLES -->

	<!-- COMECO PLUGINS DE PAGINA -->

	<link href="/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />

	<link href="/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet"
		type="text/css" />

	<!-- FIM PLUGINS DE PAGINA -->

	<!-- COMECO THEME GLOBAL STYLES -->

	<link href="/contex20/assets/global/css/components.min.css" rel="stylesheet" id="style_components"
		type="text/css" />

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

			<h3 class="form-title font-green">Login</h3>

			<div class="form-group">
				<select class="form-control" name="domain">
					<option value="" selected>Domínio</option>
					<!--<option value="https://braso.mobi/techps/techps/index.php">techps</option>-->
					<option value="https://braso.mobi/techps/sistema/index.php">Armazem Paraiba</option>
					<option value="">Leroy Merlin</option>
				</select>
			</div>

			<div class="form-group">

				<!--ie8, ie9 does not support html5 placeholder, so we just show field title for that-->

				<label class="control-label visible-ie8 visible-ie9">Usuário</label>

				<input focus autofocus class="form-control form-control-solid placeholder-no-fix" type="text"
					autocomplete="off" placeholder="Usuário" name="login" />
			</div>

			<div class="form-group">

				<label class="control-label visible-ie8 visible-ie9">Senha</label>

				<input class="form-control form-control-solid placeholder-no-fix" type="password" autocomplete="off"
					placeholder="Senha" name="senha" />
			</div>

			<?= $msg ?>

			<div class="form-actions">

				<input type="submit" class="btn green uppercase" name="botao" value="Entrar"></input>

				<a href="javascript:;" id="forget-password" class="forget-password">Esqueceu sua senha?</a>

			</div>

			<p>Versão:
				<?php echo $version; ?>
			</p>
			<p>Data de lançamento:
				<?php echo $release_date; ?>
			</p>


		</form>

		<!-- FIM LOGIN FORM -->



	</div>

	<div class="copyright">
		<?= date("Y") ?> © TechPS.
	</div>

	<!--[if lt IE 9]>

<script src="/contex20/assets/global/plugins/respond.min.js"></script>

<script src="/contex20/assets/global/plugins/excanvas.min.js"></script> 

<![endif]-->

	<!-- COMECO PLUGINS PRINCIPAL -->

	<script src="/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>

	<script src="/contex20/assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>

	<script src="/contex20/assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>

	<script src="/contex20/assets/global/plugins/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js"
		type="text/javascript"></script>

	<script src="/contex20/assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js"
		type="text/javascript"></script>

	<script src="/contex20/assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script>

	<script src="/contex20/assets/global/plugins/uniform/jquery.uniform.min.js" type="text/javascript"></script>

	<!-- <script src="/contex20/assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script> -->

	<!-- FIM PLUGINS PRINCIPAL -->

	<!-- COMECO PLUGINS DE PAGINA -->

	<!-- <script src="/contex20/assets/global/plugins/jquery-validation/js/jquery.validate.min.js" type="text/javascript"></script> -->

	<!-- <script src="/contex20/assets/global/plugins/jquery-validation/js/additional-methods.min.js" type="text/javascript"></script> -->

	<!-- <script src="/contex20/assets/global/plugins/select2/js/select2.full.min.js" type="text/javascript"></script> -->

	<!-- FIM PLUGINS DE PAGINA -->

	<!-- COMECO SCRIPTS GLOBAL -->

	<!-- <script src="/contex20/assets/global/scripts/app.min.js" type="text/javascript"></script> -->

	<!-- FIM SCRIPTS GLOBAL -->

	<!-- COMECO PAGE LEVEL SCRIPTS -->

	<!-- <script src="/contex20/assets/pages/scripts/login.min.js" type="text/javascript"></script> -->

	<!-- FIM PAGE LEVEL SCRIPTS -->

	<!-- COMECO THEME LAYOUT SCRIPTS -->

	<!-- FIM THEME LAYOUT SCRIPTS -->

</body>



</html>