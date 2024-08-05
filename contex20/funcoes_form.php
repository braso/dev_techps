<?php
	include_once "./../version.php";

	global $conn, $version;
	function cabecalho($nome_pagina,$foco=0,$relatorio=0){
		//As variáveis são utilizadas dentro de cabecalho.php
		include "cabecalho.php";
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
		<?php

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
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>

				<link href="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" />
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/js/select2.min.js"></script>
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/js/i18n/pt-BR.js" type="text/javascript"></script>

				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery-inputmask/jquery.inputmask.bundle.min.js" type="text/javascript"></script>
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery-inputmask/maskMoney.js" type="text/javascript"></script>
				<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css" />
				<link href="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
				<link href="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/simple-line-icons/simple-line-icons.min.css" rel="stylesheet" type="text/css" />
				<link href="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
				<link href="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/uniform/css/uniform.default.css" rel="stylesheet" type="text/css" />
				<link href="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css" rel="stylesheet" type="text/css" />
				<!-- FIM GLOBAL MANDATORY STYLES -->

				<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/datatables/datatables.min.js" rel="stylesheet" type="text/css" />
				<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" rel="stylesheet" type="text/css" />
				<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
				<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />

				<link href="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
				<link href="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" type="text/css" />

				<!-- INICIO TEMA GLOBAL STYLES -->
				<link href="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/css/components.min.css" rel="stylesheet" id="style_components" type="text/css" />
				<link href="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/css/plugins.min.css" rel="stylesheet" type="text/css" />
				<!-- FIM TEMA GLOBAL STYLES -->
				<!-- INICIO TEMA LAYOUT STYLES -->
				<link href="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/layout/css/layout.min.css" rel="stylesheet" type="text/css" />
				<link href="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/layout/css/themes/default.min.css" rel="stylesheet" type="text/css" id="style_color" />
				<link href="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/layout/css/custom.min.css" rel="stylesheet" type="text/css" />
				<!-- FIM TEMA LAYOUT STYLES -->
				<link rel="shortcut icon" href="favicon.ico" />

				<script type="text/javascript">
					function contex_foco(elemento){
						var campoFoco=document.forms[0].elements[<?= $foco?>];
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
									<h1><?= $nome_pagina?> </h1>
								</div>
							</div>
						</div>
						
						<div class="page-content">
							<div class="container-fluid">
								<div class="page-content-inner">
									<div class="row ">
										<div class="col-md-12">
		<?php
	}

	function rodape(){
		global $version, $CONTEX;
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
						<?php date("Y")?> &copy; <a href="https://www.techps.com.br" target="_blank" style="margin-right: 30px">TechPS</a> Versão: <?= $version?>
					</div>
				</div>
				<div class="scroll-to-top">
					<i class="icon-arrow-up"></i>
				</div>
				<!-- FIM INNER FOOTER -->
				<!-- FIM FOOTER -->
				<!-- INICIO CORE PLUGINS -->

				<form id="loginTimeoutForm" method="post" target="<?=$_SERVER['HTTP_ORIGIN'].$CONTEX['path']?>/logout.php" action="logout"></form>
				
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js" type="text/javascript"></script>
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/uniform/jquery.uniform.min.js" type="text/javascript"></script>
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-tabdrop/js/bootstrap-tabdrop.js" type="text/javascript"></script>
				<!-- FIM CORE PLUGINS -->
				<!-- INICIO TEMA GLOBAL SCRIPTS -->
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/scripts/app.min.js" type="text/javascript"></script>
				<!-- FIM TEMA GLOBAL SCRIPTS -->
				<!-- INICIO TEMA LAYOUT SCRIPTS -->
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/layout/scripts/layout.min.js" type="text/javascript"></script>
				<script src="<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/layout/scripts/demo.min.js" type="text/javascript"></script>
				<!-- FIM TEMA LAYOUT SCRIPTS -->


				<script>
					var timeoutId;
					function updateTimer(){
						if(timeoutId){
							clearTimeout(timeoutId);
						}
						timeoutId = setTimeout(function(){
							let form = document.getElementById('loginTimeoutForm');
							form.submit();
							window.location.href = '<?= $CONTEX['path']?>/logout.php';
						}, 15*60*1000);
					}
				</script>

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
		<?php
	}

	function abre_form($nome_form='',$col='12',$focus='2'){
		global $idContexForm;


		echo 
			"<div class='col-md-".$col." col-sm-".$col."'>
				<div class='portlet light'>"
		;

		if($nome_form){
			echo 
				"<div class='portlet-title'>
					<div class='caption'>
						<span class='caption-subject font-dark bold'>".$nome_form."</span>
					</div>
				</div>"
			;
		}

		echo 
			"<div class='portlet-body form'>
			<form role='form' name='contex_form".$idContexForm."' method='post' enctype='multipart/form-data'>"
		;
		$idContexForm++;
	}

	function linha_form($fields, $extraClasse = ""){
		$campo = '';
		foreach($fields as $field){
			$campo .= strval($field);
		}

		echo 
			"<div class='row ".$extraClasse."'>
				".$campo."
			</div>"
		;
	}

	function voltar(){

		die(var_dump($_POST));

		if(empty($_POST["HTTP_REFERER"])){
			set_status("Tela de origem indefinida.");
			index();
			exit;
		}
		
		$_POST['acao'] = "index";
		
		echo "<form action='".str_replace($_ENV["URL_BASE"], "", $_POST["HTTP_REFERER"])."' name='form_voltar' method='post'>";
		foreach($_POST as $key => $value){
			echo "<input type='hidden' name='".$key."' value='".$value."'>";
		}
		echo "</form>";
		
		echo "<script>document.form_voltar.submit();</script>";
		exit;
	}

	function fecha_form(array $botao = [], string $extra = ""){
		$botoes = '';
		if($botao !='' || $_POST['msg_status']){
			for($i=0;$i<count($botao);$i++){
				$botoes.="<div class='fecha-form-btn'>".$botao[$i]."</div>";
			}

			echo 
				"<div class='form-actions'>
					".$botoes."
				</div>
				<div class='msg-status-text'>".($_POST["msg_status"]?? "")."</div>"
			;
		}

		echo "</form>".$extra."</div></div></div><!-- FIM FORMULARIO-->";
	}

	function conferirCamposObrig(array $camposObrig, array $camposEnviados): string{
		//Ainda em desenvolvimento.
		$baseErrMsg = "ERRO: Campos obrigatórios não preenchidos:";
		$errorMsg = $baseErrMsg."  ";

		foreach($camposObrig as $key => $value){
			var_dump([empty($_POST[$key]), $_POST[$key] != "0"]); echo "<br>";
			if(empty($_POST[$key]) && $_POST[$key] != "0"){
				$errorMsg .= " ".$camposObrig[$key].", ";
			}
		}
		$errorMsg = substr($errorMsg, 0, strlen($errorMsg)-2);

		if($errorMsg == $baseErrMsg){
			return "";
		}

		return $errorMsg;
	}