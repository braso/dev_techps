<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	include_once "version.php";
	include_once "empresas.php";
	$msg = "";

	include_once "load_env.php";

	$error = false;
	if(!empty($_GET["error"])){
		$errorMsgs = [
			"notfound" => "Login ou senha incorretos.",
			"emptyfields" => "Preencha as informações para entrar.",
			"nullcompany" => "Empresa não encontrada."
		];
		$msg = 
			"<div class='alert alert-danger display-block'>
				<span>{$errorMsgs[$_GET["error"]]}</span>
			</div>"
		;
	}

	if (!empty($_POST["botao"]) && $_POST["botao"] == "Entrar" && !$error){
		if(!empty($_POST["empresa"])){
			$_POST["empresa"] = strtoupper($_POST["empresa"]);
		}
		if(!empty($_POST["password"])){
			$_POST["password"] = md5($_POST["password"]);
		}

		$file = $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/".$empresas[$_POST["empresa"]];

		if(!empty($empresas[$_POST["empresa"]]) && file_exists($file)){
			$formAction = $_ENV["URL_BASE"].$_ENV["APP_PATH"]."/".$empresas[$_POST["empresa"]];
			$formName = "formTelaPrincipal";
		}else{
			$formAction = "index.php?error=nullcompany";
			$formName = "formLogin";
		}

		echo 
			"<form action='{$formAction}' name='{$formName}' method='post'>"
				."<input type='hidden' name='empresa' value='".($_POST["empresa"]?? "")."'>"
				."<input type='hidden' name='user' value='".($_POST["user"]?? "")."'>"
				."<input type='hidden' name='password' value='".($_POST["password"]?? "")."'>"
				.(!empty($_POST["sourcePage"])? "<input type='hidden' name='sourcePage' value='".($_POST["sourcePage"]?? "")."'>": "")
			."</form>"
		;
		echo "<script>document.{$formName}.submit();</script>";
		exit;
	}

	$regexValidChar = "\"[A-Z]|[a-z]\"";
	$dataScript = 
		"<script>
			function validChar(e, pattern = {$regexValidChar}){
				char = String.fromCharCode(e.keyCode);
				return (char.match(pattern));
			};
			field = document.getElementsByName('empresa')[0];
			if(typeof field.addEventListener !== 'undefined'){
				field.addEventListener('keypress', function(e){
					if(!validChar(e, {$regexValidChar})){
						e.preventDefault();
					}
				});
				field.addEventListener('paste', function(e){
					e.srcElement.value = e.clipboardData.getData('Text').replaceAll(/[!-\']/g, '');
					e.preventDefault();
				});
			}
		</script>"
	;

	include "login.php";