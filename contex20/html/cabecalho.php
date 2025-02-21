<!DOCTYPE html>
<!--[if IE 8]> <html lang="pt-br" class="ie8 no-js"> <![endif]-->
<!--[if IE 9]> <html lang="pt-br" class="ie9 no-js"> <![endif]-->
<!--[if !IE]><!-->
<html lang="pt-br">
<!--<![endif]-->
<!-- INICIO HEAD -->

<head>
	<meta charset="UTF-8" />
	<title>TechPS</title>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta content="width=device-width, initial-scale=1" name="viewport" />
	<meta content="" name="description" />
	<meta content="" name="author" />

	<!-- INICIO GLOBAL MANDATORY STYLES -->
	<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>

	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" />
	<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/js/select2.min.js"></script>
	<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/js/i18n/pt-BR.js" type="text/javascript"></script>

	<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery-inputmask/jquery.inputmask.bundle.min.js" type="text/javascript"></script>
	<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery-inputmask/inputmask/jquery.inputmask.js" type="text/javascript"></script>
	<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery-inputmask/maskMoney.js" type="text/javascript"></script>
	<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/uniform/css/uniform.default.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet" type="text/css" />
	<!-- FIM GLOBAL MANDATORY STYLES -->

	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/datatables/datatables.min.js" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />

	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" type="text/css" />

	<!-- INICIO TEMA GLOBAL STYLES -->
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/css/components.min.css" rel="stylesheet" id="style_components" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/css/plugins.min.css" rel="stylesheet" type="text/css" />
	<!-- FIM TEMA GLOBAL STYLES -->
	<!-- INICIO TEMA LAYOUT STYLES -->
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/layout/css/layout.min.css" rel="stylesheet" type="text/css" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/layout/css/themes/default.min.css" rel="stylesheet" type="text/css" id="style_color" />
	<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/layout/css/custom.min.css" rel="stylesheet" type="text/css" />
	<!-- FIM TEMA LAYOUT STYLES -->
	<link rel="apple-touch-icon" sizes="180x180" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/favicon-16x16.png">
	<link rel="shortcut icon" type="image/x-icon" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/favicon-32x32.png?v=2">
	<link rel="manifest" href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/img/favicon/site.webmanifest">

	<style>

		*{
			transition: background-color .3s;
		}

		body {
			--img_path: url("<?=$CONTEX["path"]?>/imagens/logo_topo_cliente.png");
		}

		.logo-default{
			transition: filter .3s;
		}

		.logo-default:hover{
			filter: brightness(1.5);
			transition: filter .5s;
		}

		.dropdown-toggle{
			height: -webkit-fill-available;
		}

		.page-container{
			height: calc(100vh - 140px);
			width: -webkit-fill-available;
		}

		.form-actions {
			display: flex;
			flex-wrap: wrap;
		}

		.imageForm .row {
			display: grid;
			justify-items: center;
		}

		.row {
			margin: 0px 0px 25px 0px;
		}

		@media(max-width:768px) {
			.row div {
				min-width: auto;
			}
		}

		.row div label {
			margin: 0px 10px;
			background-color: white;
			z-index: 1;
			position: relative;
			padding: 0px 5px;
			border-radius: 10px;
			text-wrap: nowrap;
		}

		.row div p {
			padding: 10px;
			text-align: center;
			align-content: center;
			border-top: 1px solid #c2cad8;
			border-bottom: 1px solid #c2cad8;
		}

		.portlet{
			border-radius: 20px;
		}

		.portlet.light {
			box-shadow: 0px 5px 10px rgba(0, 0, 0, 0.2);
		}

		.portlet>.portlet-body p {
			margin-top: -10px;
		}

		.img-section {
			float: left;
			width: 25%;
			margin-bottom: 20px;
		}

		.img-section div {
			width: 100%;
		}

		.img-section .text-left {
			margin-bottom: 10px;
		}

		.img-section .text-left img {
			width: 100%;
		}

		.row div img {
			max-width: 200px;
		}

		.input-sm,
		select.input-sm,
		span.select2-selection.select2-selection--single {
			height: 40px;
			margin-top: -10px;
			align-content: center;
		}

		.campo-fit-content{
			min-width: fit-content;
		}

		.error-field, .select-error-field span.select2-selection.select2-selection--single {
			border-color: red;
		}

		.fecha-form-btn {
			margin: 0.5rem;
			width: fit-content;
			height: fit-content;
			text-align: -webkit-center;
			text-align: -moz-center;
		}

		.portlet-body button .glyphicon{
			top: 0px;
			width: calc(100% - 2px);
		}

		#botaoContexVoltar:focus,
		#botaoContexVoltar:hover {
			background-color: darkgray;
		}

		.msg-status-text {
			width: -webkit-fill-available;
			text-align: center;
			margin: 1rem;
			font-weight: bold;
			animation: grow 0.5s cubic-bezier(0.36, 0.17, 0.17, 1.97);
		}

		@keyframes grow {
			0% {
				font-size: 5px;
			}

			100% {
				font-size: 14px;
			}
		}

		@media(max-width: 992px) {
			body {
				--img_path: url("<?=$CONTEX["path"]?>/imagens/logo_mobile.png");
			}

			.page-container{
				height: calc(100vh - 110px);
			}

			.img-section {
				width: 100%;
			}

			.img-section .text-left img {
				width: 25%;
			}

			.row>div, .row>div>div {
				padding: 0px;
			}
		}

		@media(max-width: 480px){
			.page-container{
				height: 100vh;
			}

			.page-footer{
				display: none;
			}
		}
	</style>

	<script type="text/javascript">
		function validChar(e, pattern = '[a-zA-z0-9]'){
			char = String.fromCharCode(e.keyCode);
			return (char.match(pattern));
		};
		function contex_foco(elemento){
			var campoFoco = document.forms[0].elements[<?=$foco?>];
			if (campoFoco != null){
				campoFoco.focus();
			}
		}
	</script>
</head>
<!-- FIM HEAD -->

<!-- <body style="zoom:100%;" class="page-container-bg-solid page-boxed"> -->

<body onload="contex_foco()" onclick="updateTimer()" style="zoom:100%;" class="page-container-bg-solid page-boxed">
	
	<!-- INICIO HEADER -->
	<div class="page-header">
		<!-- INICIO HEADER TOP -->
		<div class="page-header-top">
			<div class="container-fluid">
				<!-- INICIO LOGO -->
				<div class="page-logo">
					<a href="<?=$CONTEX["path"]?>/index.php">
						<div class="logo-default"></div>
					</a>
				</div>
				<!-- FIM LOGO -->
				<div class="menu-options">
					<!-- INICIO TOP NAVIGATION MENU -->
					<div class="top-menu">
						<ul class="nav navbar-nav pull-right">
							<li class="droddown dropdown-separator">
								<span class="separator"></span>
							</li>
							<!-- INICIO USER LOGIN DROPDOWN -->
							<li class="dropdown dropdown-user dropdown-dark">
								<a href="javascript:;" class="dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
									<img alt="" class="img-circle" src="<?=(!empty($_SESSION["user_tx_foto"])? $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/".$_SESSION["user_tx_foto"]: $_ENV["APP_PATH"]."/contex20/img/user.png")?>">
									<span class="username username-hide-mobile"><?=$_SESSION["user_tx_login"]?></span>
								</a>
								<ul class="dropdown-menu dropdown-menu-default">
									<li>
										<a href="<?=$CONTEX["path"]?>/cadastro_usuario.php?id=<?=$_SESSION["user_nb_id"]?>">
											<i class="icon-user"></i> Perfil </a>
									</li>
									<li class="divider"> </li>
									<li>
										<a href="<?=$CONTEX["path"]?>/logout.php">
											<i class="icon-key"></i> Sair </a>
									</li>
								</ul>
							</li>
							<!-- FIM USER LOGIN DROPDOWN -->
							<!-- INICIO QUICK SIDEBAR TOGGLER -->
							<!-- <li class="dropdown dropdown-extended quick-sidebar-toggler">
												<i class="icon-logout"></i>
											</li> -->
							<!-- FIM QUICK SIDEBAR TOGGLER -->
						</ul>
					</div>
					<!-- INICIO RESPONSIVE MENU TOGGLER -->
					<a href="javascript:;" class="menu-toggler"></a>
					<!-- FIM RESPONSIVE MENU TOGGLER -->
				</div>
				<!-- FIM TOP NAVIGATION MENU -->
			</div>
		</div>
		<!-- FIM HEADER TOP -->
		<?php include($_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/menu.php");?>
	</div>
	<!-- FIM HEADER -->

	<?php
	/*Descomentar quando for utilizar.
		if ($relatorio == "1") {
			echo
				"<style>
					@media print {
						body {
							zoom: 70%;
							margin: 0;
							padding: 0;
						}
						table {
							zoom: 70%;
						}
					}
				</style>"
			;
		}
	*/
	?>

	<!-- INICIO CONTAINER -->
	<div class="page-container">
		<!-- INICIO CONTENT -->
		<div class="page-content-wrapper">
			<!-- INICIO CONTENT BODY -->
			<!-- INICIO PAGE HEAD-->
			<div class="page-head">
				<div class="container-fluid">
					<!-- INICIO PAGE TITLE -->
					<div class="page-title">
						<h1><?=$nome_pagina.(is_int(strpos($_SERVER["REQUEST_URI"], "dev")) ? " (Dev)" : "")?> </h1>
					</div>
					<!-- FIM PAGE TITLE -->
				</div>
			</div>
			<!-- FIM PAGE HEAD-->

			<!-- INICIO PAGE CONTENT BODY -->
			<div class="page-content">
				<div class="container-fluid">
					<!-- INICIO PAGE CONTENT INNER -->
					<div class="page-content-inner">
						<div class="row ">
							<div class="col-md-12">