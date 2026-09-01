<?php

		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");


	ini_set('session.gc_maxlifetime', 12*60*60); //Mesmo tempo de vida do conecta.php
	$started = session_start();
	
	include_once "load_env.php";

	if(empty($_POST["getSessionValues"])){
		echo "<style>";
		include "css/index.css";
		echo "</style>";
	}

	$turnos = ["Noite", "Manhã", "Tarde", "Noite"];
	$turnoAtual = $turnos[intval((intval(date("H"))-3)/6)];

	function index(){
		global $turnoAtual;

		
		if(array_values(array_intersect(array_keys($_SESSION), ["user_tx_nome", "user_tx_login", "user_tx_nivel", "horaEntrada"])) != ["user_tx_login", "user_tx_nome", "user_tx_nivel", "horaEntrada"]){
			logar();
		}

		include_once "conecta.php";
	include_once "comunicado_popup.php";
	cabecalho("");
		showWelcome($_SESSION["user_tx_nome"],$turnoAtual,$_SESSION["horaEntrada"]);
		mostrarComunicadoPopup();
		rodape();
		exit;
	}

	function showWelcome($usuario, $turnoAtual, $horaEntrada) {
		global $turnoAtual;

		// Só quem é Administrador/Super Administrador, ou tem permissão explícita de
		// telas de gestão (empresa ou funcionário), enxerga a Torre de Comando. Todo o
		// resto (motorista, ajudante, funcionário operacional etc.) cai direto na
		// batida de ponto, que passa a ser a tela inicial dele.
		include_once __DIR__."/check_permission.php";
		$nivel = $_SESSION["user_tx_nivel"] ?? "";
		$isAdmin = (bool) preg_match('/administrador/i', $nivel);
		$temPermissaoGestao = function_exists('temPermissaoMenu')
			&& (temPermissaoMenu('/cadastro_empresa.php') || temPermissaoMenu('/cadastro_funcionario.php'));

		if (!$isAdmin && !$temPermissaoGestao) {
			echo "<meta http-equiv='refresh' content='0; url=./batida_ponto.php'/>";
			exit;
		}

		// Torre de Comando embutida direto na tela de boas-vindas — sem
		// precisar navegar/clicar em nada para ver o dashboard.
		include_once "torre_comando.php";
		renderTorreDeComando();
	}

	if(!empty($_SESSION["user_nb_id"]) && empty($_POST["user"]) && empty($_POST["password"])){ //Se já há um usuário logado e não está tentando um novo login
		$interno = true;
		include_once "conecta.php";
		include_once "comunicado_popup.php";
		cabecalho("");
		showWelcome($_SESSION["user_tx_nome"],$turnoAtual,$_SESSION["horaEntrada"]);
		mostrarComunicadoPopup();
		rodape();
		exit;
	}

	function logar(){
	    
		global $turnoAtual;
		if(empty($_POST["user"]) && !empty($_POST["username"])){
			$_POST["user"] = $_POST["username"];
		}
	
		$error = "emptyfields";
	
		if(!empty($_POST["user"]) && !empty($_POST["password"])){//Tentando logar
			if(!empty($_SESSION["user_tx_login"]) && $_SESSION["user_tx_login"] != $_POST["user"]){ //Se já há um usuário logado
				$_SESSION = [];
				session_destroy();
			}else{
				$_SESSION["user_tx_login"] = $_POST["user"];
			}
	
			
			$interno = true; //Utilizado em conecta.php;
			include_once "conecta.php";
			include_once "comunicado_popup.php";
			include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_form.php";
			
			$resUsuario = query(
				"SELECT * FROM user"
					." WHERE user_tx_status = 'ativo'"
						." AND user_tx_login = ?"
						." AND user_tx_senha = ?;",
				"ss",
				[$_POST["user"], $_POST["password"]]
			);
			$usuario = $resUsuario ? mysqli_fetch_assoc($resUsuario) : null;
			
			if(!empty($usuario)){ //Se encontrou um usuário

				if (!empty($usuario["user_tx_expiracao"]) && strtotime($usuario["user_tx_expiracao"]) < strtotime(date("Y-m-d"))){
					$error = "expireduser";
					$_POST["HTTP_REFERER"] = $_ENV["APP_PATH"]."/index.php?error=".$error;
					$_POST["returnValues"] = json_encode([
						"HTTP_REFERER" => $_POST["HTTP_REFERER"],
						"empresa" => $_POST["empresa"],
						"user" => $_POST["user"],
						"password" => $_POST["password"]
					]);
					voltar();
					exit;
				}
	
			foreach($usuario as $key => $value){
				$_SESSION[$key] = $value;
			}


				if(!isset($_SESSION["horaEntrada"])){
					$_SESSION["horaEntrada"] = date("H:i");
				}
				if(!empty($_POST["getSessionValues"])){
					echo json_encode($_SESSION);
					exit;
				}
				// Perfis operacionais devem cair direto na batida quando tiverem permissão do menu.
				// Admin/Super Admin continuam no fluxo padrão abaixo (showWelcome).
				if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário", "Terceirizado"])){
					include_once __DIR__."/check_permission.php";
					if (function_exists('temPermissaoMenu') && temPermissaoMenu('/batida_ponto.php')){
						echo "<meta http-equiv='refresh' content='0; url=./batida_ponto.php'/>";
						exit;
					}
					cabecalho("");
					showWelcome($usuario["user_tx_nome"], $turnoAtual, $_SESSION["horaEntrada"]);
					mostrarComunicadoPopup();
					rodape();
					exit;
				}
	
				if(!empty($_POST["sourcePage"]) && is_int(strpos($_POST["sourcePage"], $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]))){
					echo 
						"<form name='goToSourceForm' action='".$_POST["sourcePage"]."'></form>"
						."<script>document.goToSourceForm.submit();</script>"
					;
				}
	
			cabecalho("");
			showWelcome($usuario["user_tx_nome"], $turnoAtual, $_SESSION["horaEntrada"]);
			mostrarComunicadoPopup();
			rodape();
			exit;
		}
		}
        
        include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_form.php";
        $error = (empty($_POST["user"]) || empty($_POST["password"])) ? "emptyfields" : "notfound";
        $_POST["HTTP_REFERER"] = $_ENV["APP_PATH"]."/index.php?error=".$error;
        $_POST["returnValues"] = json_encode([
            "HTTP_REFERER" => $_POST["HTTP_REFERER"],
            "empresa" => $_POST["empresa"],
            "user" => $_POST["user"],
            "password" => $_POST["password"]
        ]);

        voltar();
        exit;
    }

	logar();
