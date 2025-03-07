<?php
	include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/version.php";

	global $conn, $version;

	function cabecalho(string $nome_pagina, int $foco=0, int $relatorio=0): void{
		include $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/loading.html";
		
		global $CONTEX, $_SESSION;
		//As variáveis são utilizadas dentro de cabecalho.php
		include __DIR__."/html/cabecalho.php";
	}

	function rodape(): void{
		global $version, $CONTEX;

		include __DIR__."/html/rodape.php";
	}

	// function cabecaRelatorio($nome_pagina,$foco=0){
	// 	echo "cabecaRelatorio()";
	// 	return;
		
	// 	global $CONTEX;
	// 	echo "<!DOCTYPE html><!--[if IE 8]> <html lang='pt-br' class='ie8 no-js'> <![endif]--><!--[if IE 9]> <html lang='pt-br' class='ie9 no-js'> <![endif]--><!--[if !IE]><!--><html lang='pt-br'><!--<![endif]--><!-- INICIO HEAD --><head><meta charset='utf-8' /><title>CONTAINER Sistemas</title><meta http-equiv='X-UA-Compatible' content='IE=edge'><meta content='width=device-width, initial-scale=1' name='viewport' /><meta content='' name='description' /><meta content='' name='author' /><!-- INICIO GLOBAL MANDATORY STYLES --><script src='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/jquery.min.js' type='text/javascript'></script><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/select2/css/select2.min.css' rel='stylesheet' /><script src='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/select2/js/select2.min.js'></script><script src='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/select2/js/i18n/pt-BR.js' type='text/javascript'></script><script src='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/jquery-inputmask/jquery.inputmask.bundle.min.js' type='text/javascript'></script><script src='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/jquery-inputmask/maskMoney.js' type='text/javascript'></script><link href='https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all' rel='stylesheet' type='text/css' /><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/font-awesome/css/font-awesome.min.css' rel='stylesheet' type='text/css' /><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/simple-line-icons/simple-line-icons.min.css' rel='stylesheet' type='text/css' /><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/bootstrap/css/bootstrap.min.css' rel='stylesheet' type='text/css' /><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/uniform/css/uniform.default.css' rel='stylesheet' type='text/css' /><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/bootstrap-switch/css/bootstrap-switch.min.css' rel='stylesheet' type='text/css' /><!-- FIM GLOBAL MANDATORY STYLES --><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/datatables/datatables.min.js' rel='stylesheet' type='text/css' /><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js' rel='stylesheet' type='text/css' /><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/datatables/datatables.min.css' rel='stylesheet' type='text/css' /><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css' rel='stylesheet' type='text/css' /><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/select2/css/select2.min.css' rel='stylesheet' type='text/css' /><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css' rel='stylesheet' type='text/css' /><!-- INICIO TEMA GLOBAL STYLES --><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/css/components.min.css' rel='stylesheet' id='style_components' type='text/css' /><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/css/plugins.min.css' rel='stylesheet' type='text/css' /><!-- FIM TEMA GLOBAL STYLES --><!-- INICIO TEMA LAYOUT STYLES --><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/layout/css/layout.min.css' rel='stylesheet' type='text/css' /><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/layout/css/themes/default.min.css' rel='stylesheet' type='text/css' id='style_color' /><link href='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/layout/css/custom.min.css' rel='stylesheet' type='text/css' /><!-- FIM TEMA LAYOUT STYLES --><link rel='shortcut icon' href='favicon.ico' /><style>table.table thead tr th{font-size: 10pt;}table.table td{font-size: 8pt;}p.text-left{font-size: 8pt;}label{font-size: 8pt;}@media print{table.table thead tr th{font-size: 8pt;}table.table td{font-size: 6pt;}}.page-header .page-header-menu{background-color: white;}</style><script type='text/javascript'>function contex_foco(elemento){var campoFoco=document.forms[0].elements[".$foco."];if(campoFoco != null)campoFoco.focus();}</script></head><!-- FIM HEAD --><body onload='contex_foco()' class='page-container-bg-solid page-boxed'><div class='page-container'><div class='page-content-wrapper'><div class='page-head'><div class='container-fluid'><div class='page-title'><h1>".$nome_pagina."</h1></div></div></div><div class='page-content'><div class='container-fluid'><div class='page-content-inner'><div class='row '><div class='col-md-12'>";
	// }

	function createForm(string $title="", int $width, string $content, array $buttons, string $extra=""){
		
		if(empty($_POST["msg_status"])){
			$_POST["msg_status"] = "";
		}

		if(!empty($title)){
			$title = "<div class='portlet-title'>
				<div class='caption'>
					<span class='caption-subject font-dark bold'>{$title}</span>
				</div>
			</div>";
		}

		if(!empty($buttons) || !empty($_POST["msg_status"])){
			$buttons = 
				"<div class='form-actions'>
					<div class='fecha-form-btn'>"
						.implode("</div><div class='fecha-form-btn'>", $buttons)
					."</div>
				</div>
				<div class='msg-status-text'>
					{$_POST["msg_status"]}
				</div>"
			;
		}else{
			$buttons = "";
		}
		
		$result = 
			"<div class='col-md-{$width}'>
				<div class='portlet light'>
					{$title}
					<div class='portlet-body form'>
						<form role='form' name='contex_form' method='post' enctype='multipart/form-data'>
							{$content}
							{$buttons}
						</form>
						{$extra}
					</div>
				</div>
			</div>
			<!-- FIM FORMULARIO-->"
		;

		return $result;
	}

	function abre_form(string $nome_form="", int $width=12): string{
		$result = "<div class='col-md-{$width}'><div class='portlet light'>";

		if(!empty($nome_form)){
			$result .= 
				"<div class='portlet-title'>
					<div class='caption'>
						<span class='caption-subject font-dark bold'>{$nome_form}</span>
					</div>
				</div>"
			;
		}

		$result .= 
			"<div class='portlet-body form'>
			<form role='form' name='contex_form' method='post' enctype='multipart/form-data'>"
		;
		
		return $result;
	}

	function linha_form(array $fields): string{
		$campo = "";
		foreach($fields as $field){
			$campo .= strval($field);
		}

		return "<div class='row'>{$campo}</div>";
	}

	//$telaPadrao: A tela para qual será retornada caso o HTTP_REFERER esteja querendo voltar para a mesma tela que já está
	function criarBotaoVoltar($telaPadrao = null, $acaoPadrao = null, $extra = ""): string{
		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], $_SERVER["REQUEST_URI"]))){
				if(empty($telaPadrao)){
					$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_SERVER["REQUEST_URI"];
				}else{
					$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/".$telaPadrao;
				}
			}
		}
		if(empty($_POST["acaoPrevia"])){
			if(empty($acaoPadrao)){
				$_POST["acaoPrevia"] = "index";
			}else{
				$_POST["acaoPrevia"] = $acaoPadrao;
			}
		}

		if(empty($_POST["returnValues"])){
			$_POST["returnValues"] = json_encode($_POST);
		}

		return botao("Voltar", "voltar")."<input type='hidden' name='returnValues' value='{$_POST["returnValues"]}' {$extra}/>";
	}

	function voltar(){
		
		$returnValues = json_decode($_POST["returnValues"]);

		if(empty($returnValues->HTTP_REFERER)){
			set_status("Tela de origem indefinida.");
			index();
			exit;
		}

		if(!empty($returnValues->acaoPrevia)){
			$returnValues->acao = $returnValues->acaoPrevia;
			unset($returnValues->acaoPrevia);
		}
		
		if(empty($returnValues->acao) || $returnValues->acao == "voltar()"){
			$returnValues->acao = "index";
		}
		
		$formVoltar = "<form action='".str_replace($_ENV["URL_BASE"], "", $returnValues->HTTP_REFERER)."' name='form_voltar' method='post'>";
		foreach($returnValues as $key => $value){
			if(is_array($value)){
				foreach($value as $val){
					$formVoltar .= "<input type='hidden' name='".$key."[]' value='".$val."'>";
				}
			}else{
				$formVoltar .= "<input type='hidden' name='".$key."' value='".$value."'>";
			}
		}
		$formVoltar .= "</form>";
		$formVoltar .= "<script>document.form_voltar.submit();</script>";
		
		echo $formVoltar;
		exit;
	}

	function fecha_form(array $botoes = [], string $extra = ""): string{
		$result = "";
		if(empty($_POST["msg_status"])){
			$_POST["msg_status"] = "";
		}
		if(!empty($botoes) || !empty($_POST["msg_status"])){
			$botoes = "<div class='fecha-form-btn'>".implode("</div><div class='fecha-form-btn'>", $botoes)."</div>";
			
			$result = 
				"<div class='form-actions'>{$botoes}</div>
				<div class='msg-status-text'>{$_POST["msg_status"]}</div>"
			;
		}

		$result .= "</form>{$extra}</div></div></div><!-- FIM FORMULARIO-->";

		return $result;
	}

	function conferirCamposObrig(array $camposObrig, array $camposEnviados): string{
		//Ainda em desenvolvimento.
		$baseErrMsg = "Campos obrigatórios não preenchidos:";
		$errorMsg = [];	

		$parteComum = array_intersect_key($camposEnviados, $camposObrig);
		$parteComum = array_filter($parteComum, function($value){
			return !empty($value);
		});
		if($parteComum != $camposObrig){
			foreach(array_diff_key($camposObrig, $parteComum) as $key => $value){
				$_POST["errorFields"][] = $key;
				$errorMsg[] = $value;
			}
		}

		return ((empty($errorMsg))? "": $baseErrMsg." ".implode(", ", $errorMsg).".");
	}

	function criarHiddenForm(string $nome, array $campos, array $valores, string $acao = ""){

		$form = "<form ".(!empty($acao)? "action='{$acao}'": "")." name='{$nome}' method='post'>";
		foreach($campos as $key => $campo){
			if(is_array($valores[$key])){
				foreach($valores[$key] as $val){
					$form .= "<input type='hidden' name='{$campo}[]' ".(!empty($val)? "value='{$val}'": "").">";
				}
			}else{
				$form .= "<input type='hidden' name='{$campo}' ".(!empty($valores[$key])? "value='{$valores[$key]}'": "").">";
			}
		}
		$form .= "</form>";

		return $form;
	}