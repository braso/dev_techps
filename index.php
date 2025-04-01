<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	include_once("version.php");
	include_once("dominios.php");
	$msg = "";

	include_once "load_env.php";

	$error = false;
	if(!empty($_GET["error"])){
		$errorMsgs = [
			"notfound" => "Login ou senha incorretos.",
			"emptyfields" => "Preencha as informações para entrar.",
			"notfounddomain" => "Domínio não encontrado."
		];
		$msg = 
			"<div class='alert alert-danger display-block'>
				<span>".$errorMsgs[$_GET["error"]]."</span>
			</div>"
		;
	}

	if (!empty($_POST["botao"]) && $_POST["botao"] == "Entrar" && !$error){
		if(!empty($_POST["password"])){
			$_POST["password"] = md5($_POST["password"]);
		}
		
		$file = $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"].$_POST["dominio"];

		if(is_int(strpos($dominiosInput, $_POST["dominio"])) && file_exists($file)){
			$formAction = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_POST["dominio"];
			$formName = "formTelaPrincipal";
		}else{
			$formAction = "index.php?error=notfounddomain";
			$formName = "formLogin";
		}

		echo 
			"<form action='".$formAction."' name='".$formName."' method='post'>"
				."<input type='hidden' name='dominio' value='".($_POST["dominio"]?? '')."'>"
				."<input type='hidden' name='user' value='".($_POST["user"]?? '')."'>"
				."<input type='hidden' name='password' value='".($_POST["password"]?? '')."'>"
				.(!empty($_POST["sourcePage"])? "<input type='hidden' name='sourcePage' value='".($_POST["sourcePage"]?? '')."'>": "")
			."</form>"
		;
		echo "<script>document.".$formName.".submit();</script>";
		
		exit;
	}

	include "login.php";