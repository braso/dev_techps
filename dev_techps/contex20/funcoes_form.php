<?php
	include "./../version.php";

	global $CONTEX, $conn, $version;
	function cabecalho($nome_pagina,$foco=0,$relatorio=0){
	global $CONTEX,$conn;

	if(!$_SESSION['user_nb_id']){

		echo "<meta http-equiv='refresh' content='0; url=".$CONTEX['path']."/index.php' />";
		exit;
	}

	global $CONTEX;
	?>
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
				<script src="/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>

				<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
				<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
				<script src="/contex20/assets/global/plugins/select2/js/i18n/pt-BR.js" type="text/javascript"></script>

				<script src="/contex20/assets/global/plugins/jquery-inputmask/jquery.inputmask.bundle.min.js" type="text/javascript"></script>
				<script src="/contex20/assets/global/plugins/jquery-inputmask/maskMoney.js" type="text/javascript"></script>
				<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/uniform/css/uniform.default.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet" type="text/css" />
				<!-- FIM GLOBAL MANDATORY STYLES -->

				<link href="/contex20/assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />

				<link href="/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" type="text/css" />

				<!-- INICIO TEMA GLOBAL STYLES -->
				<link href="/contex20/assets/global/css/components.min.css" rel="stylesheet" id="style_components" type="text/css" />
				<link href="/contex20/assets/global/css/plugins.min.css" rel="stylesheet" type="text/css" />
				<!-- FIM TEMA GLOBAL STYLES -->
				<!-- INICIO TEMA LAYOUT STYLES -->
				<link href="/contex20/assets/layout/css/layout.min.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/layout/css/themes/default.min.css" rel="stylesheet" type="text/css" id="style_color" />
				<link href="/contex20/assets/layout/css/custom.min.css" rel="stylesheet" type="text/css" />
				<!-- FIM TEMA LAYOUT STYLES -->
				<link rel="apple-touch-icon" sizes="180x180" href="/contex20/img/favicon/apple-touch-icon.png">
				<link rel="icon" type="image/png" sizes="32x32" href="/contex20/img/favicon/favicon-32x32.png">
				<link rel="icon" type="image/png" sizes="16x16" href="/contex20/img/favicon/favicon-16x16.png">
				<link rel="shortcut icon" type="image/x-icon" href="/contex20/img/favicon/favicon-32x32.png?v=2">
				<link rel="manifest" href="/contex20/img/favicon/site.webmanifest">
				<script type="text/javascript">

					function contex_foco(elemento){
						var campoFoco=document.forms[0].elements[<?=$foco?>];
						if(campoFoco != null)
							campoFoco.focus();

					}

				</script>
			</head>
			<!-- FIM HEAD -->

			<!-- <body style="zoom:100%;" class="page-container-bg-solid page-boxed"> -->
			<body onload="contex_foco()" style="zoom:100%;" class="page-container-bg-solid page-boxed">
		<?
		if($relatorio==0){
			?>
					<!-- INICIO HEADER -->
					<div class="page-header">
						<!-- INICIO HEADER TOP -->
						<div class="page-header-top">
							<div class="container-fluid">
								<!-- INICIO LOGO -->
								<div class="page-logo">
									<a href="<?=$CONTEX['path']?>/index.php">
										<!-- <img src="/contex20/img/logo.png" alt="logo" class="logo-default"> -->
										<img src="<?=$CONTEX['path']?>/imagens/logo_topo_cliente.png" alt="logo" class="logo-default">
									</a>
								</div>
								<!-- FIM LOGO -->
								<!-- INICIO RESPONSIVE MENU TOGGLER -->
								<a href="javascript:;" class="menu-toggler"></a>
								<!-- FIM RESPONSIVE MENU TOGGLER -->
								<!-- INICIO TOP NAVIGATION MENU -->
								<div class="top-menu">
									<ul class="nav navbar-nav pull-right">
										<li class="droddown dropdown-separator">
											<span class="separator"></span>
										</li>
										<!-- INICIO USER LOGIN DROPDOWN -->
										<li class="dropdown dropdown-user dropdown-dark">
											<a href="javascript:;" class="dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
												<img alt="" class="img-circle" src="<?=$_SESSION['user_tx_foto'] ? $_SESSION['user_tx_foto'] : '/contex20/img/user.png'?>">
												<span class="username username-hide-mobile"><?=$_SESSION['user_tx_login']?></span>
											</a>
											<ul class="dropdown-menu dropdown-menu-default">
												<li>
													<a href="<?=$CONTEX['path']?>/cadastro_usuario.php?id=<?=$_SESSION['user_nb_id']?>">
														<i class="icon-user"></i> Perfil </a>
												</li>
												<li class="divider"> </li>
												<li>
													<a href="<?=$CONTEX['path']?>/logout.php">
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
								<!-- FIM TOP NAVIGATION MENU -->
							</div>
						</div>
						<!-- FIM HEADER TOP -->
			<?
			include($_SERVER['DOCUMENT_ROOT'].$CONTEX['path'].'/menu.php');
			?>	
					</div>
					<!-- FIM HEADER -->
			<?
		}

		if($relatorio=='1'){
			echo 
				'<style>
					@media print {
					body {zoom:70%;margin: 0;padding:0;}
					table {zoom: 70%;}
				}
				</style>';
		}
		?>

					<!-- INICIO CONTAINER -->
					<div class="page-container">
						<!-- INICIO CONTENT -->
						<div class="page-content-wrapper">
							<!-- INICIO CONTENT BODY -->
							<!-- INICIO PAGE HEAD-->
							<?php if(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))) { 
								?>
							<div class="page-head">
								<div class="container-fluid">
									<!-- INICIO PAGE TITLE -->
									<div class="page-title">
										<h1><?=$nome_pagina.(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))? ' (Dev)': '')?> </h1>
									</div>
									<!-- FIM PAGE TITLE -->
								</div>
							</div>
							<?php 
							}
							?>
							<!-- FIM PAGE HEAD-->

							<!-- INICIO PAGE CONTENT BODY -->
							<div class="page-content">
								<div class="container-fluid">
									<!-- INICIO PAGE CONTENT INNER -->
									<div class="page-content-inner">
										<div class="row ">
											<div class="col-md-12">
		<?
	}

	function cabecaRelatorio($nome_pagina,$foco=0){
		?>
		<style>
			table.table thead tr th{
				font-size: 10pt;	    	
			}
			table.table td{
				font-size: 8pt;	    	
			}
			p.text-left{
				font-size: 8pt;
			}
			label{
				font-size: 8pt;
			}
			@media print{
				table.table thead tr th{
					font-size: 8pt;	    	
				}
				table.table td{
					font-size: 6pt; 	
				}
			}
		</style>
		<?

		if(!$_SESSION['user_nb_id']){
			?>
				<meta http-equiv="refresh" content="0; url=index.php" />
			<?
			exit;
		}

	global $CONTEX;
		?>
			<!DOCTYPE html>
		<!--[if IE 8]> <html lang="pt-br" class="ie8 no-js"> <![endif]-->
		<!--[if IE 9]> <html lang="pt-br" class="ie9 no-js"> <![endif]-->
		<!--[if !IE]><!-->
		<html lang="pt-br">
			<!--<![endif]-->
			<!-- INICIO HEAD -->

			<head>
				<meta charset="utf-8" />
				<title>CONTAINER Sistemas</title>
				<meta http-equiv="X-UA-Compatible" content="IE=edge">
				<meta content="width=device-width, initial-scale=1" name="viewport" />
				<meta content="" name="description" />
				<meta content="" name="author" />
				<!-- INICIO GLOBAL MANDATORY STYLES -->
				<script src="/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>

				<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
				<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
				<script src="/contex20/assets/global/plugins/select2/js/i18n/pt-BR.js" type="text/javascript"></script>

				<script src="/contex20/assets/global/plugins/jquery-inputmask/jquery.inputmask.bundle.min.js" type="text/javascript"></script>
				<script src="/contex20/assets/global/plugins/jquery-inputmask/maskMoney.js" type="text/javascript"></script>
				<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/uniform/css/uniform.default.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet" type="text/css" />
				<!-- FIM GLOBAL MANDATORY STYLES -->

				<link href="/contex20/assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />

				<link href="/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" type="text/css" />

				<!-- INICIO TEMA GLOBAL STYLES -->
				<link href="/contex20/assets/global/css/components.min.css" rel="stylesheet" id="style_components" type="text/css" />
				<link href="/contex20/assets/global/css/plugins.min.css" rel="stylesheet" type="text/css" />
				<!-- FIM TEMA GLOBAL STYLES -->
				<!-- INICIO TEMA LAYOUT STYLES -->
				<link href="/contex20/assets/layout/css/layout.min.css" rel="stylesheet" type="text/css" />
				<link href="/contex20/assets/layout/css/themes/default.min.css" rel="stylesheet" type="text/css" id="style_color" />
				<link href="/contex20/assets/layout/css/custom.min.css" rel="stylesheet" type="text/css" />
				<!-- FIM TEMA LAYOUT STYLES -->
				<link rel="shortcut icon" href="favicon.ico" />
				<script type="text/javascript">
					function contex_foco(elemento){
						var campoFoco=document.forms[0].elements[<?=$foco?>];
						if(campoFoco != null)
							campoFoco.focus();

					}

				</script>
			</head>
			<!-- FIM HEAD -->


			<body onload="contex_foco()" class="page-container-bg-solid page-boxed">
				
				<div class="page-container">
					<div class="page-content-wrapper">
						<div class="page-head">
							<div class="container-fluid">
								<div class="page-title">
									<h1><?=$nome_pagina?> </h1>
								</div>
							</div>
						</div>
						
						<div class="page-content">
							<div class="container-fluid">
								<div class="page-content-inner">
									<div class="row ">
										<div class="col-md-12">
		<?
	}

	function rodape(){
		global $version;
		?>

										</div>
									</div>
								</div>
								<!-- FIM PAGE CONTENT INNER -->
							</div>
						</div>
						<!-- FIM PAGE CONTENT BODY -->


						<!-- FIM CONTENT BODY -->
						</div>
						<!-- FIM CONTENT -->
						
					</div>
					<!-- FIM CONTAINER -->

				<!-- INICIO FOOTER -->
				<!-- INICIO INNER FOOTER -->
				<div class="page-footer">
					<div class="container-fluid"> 
						<?=date("Y")?> &copy; <a href="https://www.techps.com.br" target="_blank" style="margin-right: 30px">TechPS</a> Versão: <?=$version?>
					</div>
				</div>
				<div class="scroll-to-top">
					<i class="icon-arrow-up"></i>
				</div>
				<!-- FIM INNER FOOTER -->
				<!-- FIM FOOTER -->
				<!--[if lt IE 9]>
				<script src="..//contex20/assets/global/plugins/respond.min.js"></script>
				<script src="..//contex20/assets/global/plugins/excanvas.min.js"></script> 
				<![endif]-->
				<!-- INICIO CORE PLUGINS -->
				
				<script src="/contex20/assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
				<script src="/contex20/assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
				<script src="/contex20/assets/global/plugins/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js" type="text/javascript"></script>
				<script src="/contex20/assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
				<!-- <script src="/contex20/assets/global/plugins/jquery.blockui.min.js" type="text/javascript"></script> -->
				<script src="/contex20/assets/global/plugins/uniform/jquery.uniform.min.js" type="text/javascript"></script>
				<script src="/contex20/assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
				<script src="/contex20/assets/global/plugins/bootstrap-tabdrop/js/bootstrap-tabdrop.js" type="text/javascript"></script>
				<!-- FIM CORE PLUGINS -->
				<!-- INICIO TEMA GLOBAL SCRIPTS -->
				<script src="/contex20/assets/global/scripts/app.min.js" type="text/javascript"></script>
				<!-- FIM TEMA GLOBAL SCRIPTS -->
				<!-- INICIO TEMA LAYOUT SCRIPTS -->
				<script src="/contex20/assets/layout/scripts/layout.min.js" type="text/javascript"></script>
				<script src="/contex20/assets/layout/scripts/demo.min.js" type="text/javascript"></script>
				<!-- FIM TEMA LAYOUT SCRIPTS -->

				<!-- BEGIN PAGE LEVEL PLUGINS -->
				<!-- <script src="/contex20/assets/global/scripts/datatable.js" type="text/javascript"></script>
				<script src="/contex20/assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
				<script src="/contex20/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script> -->


				<!-- <script src="/contex20/assets/global/plugins/select2/js/select2.full.min.js" type="text/javascript"></script> -->
				<!-- <script src="/contex20/assets/global/plugins/select2/js/i18n/pt-BR.js" type="text/javascript"></script> -->
				<!-- <script src="/contex20/assets/global/scripts/components-select2.js" type="text/javascript"></script> -->

				
				<!-- <script src="/contex20/assets/global/plugins/jquery-inputmask/form-input-mask.js" type="text/javascript"></script> -->
				<!-- END PAGE LEVEL PLUGINS -->
				

				<!-- BEGIN PAGE LEVEL SCRIPTS -->
				<!-- <script src="/contex20/assets/scripts/table-datatables-responsive.min.js" type="text/javascript"></script> -->
				<!-- END PAGE LEVEL SCRIPTS -->

				<?
				// include 'funcoes_grid.php';
				?>

			</body>

		</html>	
		<!-- 
		<script type="text/javascript">
				// $(document).ajaxStart($.blockUI({});).ajaxStop($.unblockUI);


				$(document).ajaxStart(function() {
					$.blockUI({ 
						message: '<h1><img src="busy.gif" /> Carregando...</h1>' 
					});
				});
				$(document).ajaxStop(function() {
					$.unblockUI();
				});
		</script>
		-->
		<?

	}



	function abre_form($nome_form='',$col='12',$focus='2'){
		global $idContexForm;
		?>

									<!-- INICIO FORMULARIO-->
									<div class="col-md-<?=$col?> col-sm-<?=$col?>">
										<div class="portlet light ">
											<?
											if($nome_form){
											?>
											<div class="portlet-title">
												<div class="caption">
													<span class="caption-subject font-dark bold uppercase"><?=$nome_form?></span>
												</div>
											</div>
											<?
											}
											?>

											<div class="portlet-body form">
												<form role="form" name='contex_form<?=$idContexForm?>' method="post" enctype="multipart/form-data">

		<?
		$idContexForm++;
	}

	function linha_form($c){
		$campo = '';
		for($i=0;$i<count($c);$i++){
			$campo.="$c[$i]";
		}

	?>
		<div class="row">
			<?=$campo?>
		</div>

	<?
	}


	function fecha_form(array $botao = []){
		$botoes = '';
		if($botao !='' || $_POST['msg_status']){
			for($i=0;$i<count($botao);$i++){
				$botoes.=$botao[$i]."&nbsp;&nbsp;";
			}

			$botoes .= "&nbsp;&nbsp;<b>".($_POST['msg_status']?? '')."</b>";

	?>
														<div class="form-actions">
															<?=$botoes?>
														</div>
	<?
		}
	?>
													</form>
													
												</div>
											</div>
										</div>
										<!-- FIM FORMULARIO-->

	<?
	}
?>