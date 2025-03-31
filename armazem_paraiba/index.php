<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/

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
		cabecalho("");
		showWelcome($_SESSION["user_tx_nome"],$turnoAtual,$_SESSION["horaEntrada"]);
		rodape();
		exit;
	}

	function showWelcome($usuario, $turnoAtual, $horaEntrada) {
		global $turnoAtual;

        $contatos = [
            "Telefone" 				=> "<a href='https://api.whatsapp.com/send?phone=5584981578492' target='_blank'>(84) 98157-8492</a>",
            "Treinamento" 			=> "<a href='mailto:treinamento@techps.com.br' target='_blank'>treinamento@techps.com.br</a>",
            "Suporte de Sistemas" 	=> "<a href='mailto:suporte@techps.com.br' target='_blank'>suporte@techps.com.br</a>",
            "Comercial" 			=> "<a href='mailto:comercial@techps.com.br' target='_blank'>comercial@techps.com.br</a>",
            "Financeiro" 			=> "<a href='mailto:financeiro@techps.com.br' target='_blank'>financeiro@techps.com.br</a>",
            "Administrativo" 		=> "<a href='mailto:administrativo@techps.com.br' target='_blank'>administrativo@techps.com.br</a>"
        ];

        $table = "<table class='table w-auto table-condensed flip-content table-hover compact'><tbody>";
        foreach ($contatos as $area => $link) {
            $table .= "<tr><th>".$area.": </th><td>".$link."</td></tr>";
        }
        $table .= "</tbody></table>";
        
        echo 
			"<div id='boas-vindas' class='portlet light'>"
				."<div style='text-align: center; align-content: center; height: 5em;'>"
					."Bem Vindo(a), <b>".$usuario."</b>.<br>"
					."Período da ".$turnoAtual." iniciado às ".$horaEntrada."."
				."</div>"
				."<div class='obs'>"
					."<p>Neste sistema, você encontra informações relacionadas a: "
						."<ul>"
							."<li>Registros;</li>"
							."<li>Apontamentos de espelho de ponto;</li>"
							."<li>Endosso;</li>"
							."<li>Não conformidades;</li>"
							."<li>Acesso aos relatórios dos serviços contratados.</li>"
						."</ul>"
					."</p>"
				."</div>"
				."<p>Em caso de dúvida, respondemos a partir de uma das formas de contato abaixo.</p>"
				."<h4><b>Contatos:</b></h4>"
				."".$table."
			</div>"
		;
    }

	if(!empty($_SESSION["user_nb_id"]) && empty($_POST["user"]) && empty($_POST["password"])){ //Se já há um usuário logado e não está tentando um novo login
		$interno = true;
		include_once "conecta.php";
		cabecalho("");
		showWelcome($_SESSION["user_tx_nome"],$turnoAtual,$_SESSION["horaEntrada"]);
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
			
			$usuario = mysqli_fetch_assoc(query(
				"SELECT * FROM user"
					." WHERE user_tx_status = 'ativo'"
						." AND user_tx_login = '".$_POST["user"]."'"
						." AND user_tx_senha = '".$_POST["password"]."';"
			));
	
			if(!empty($usuario)){ //Se encontrou um usuário

				$dataHoje = strtotime(date("Y-m-d")); // Transforma a data de hoje em timestamp
				$dataVerificarObj = strtotime($usuario["user_tx_expiracao"]);
				if ($dataVerificarObj >= $dataHoje && !empty($usuario["user_tx_expiracao"]) && $usuario["user_tx_expiracao"] != "0000-00-00"){
					echo 
						"<div class='alert alert-danger display-block'>
							<span> Usuário expirado. </span>
						</div>"
					;
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
				if(in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])){
					echo "<meta http-equiv='refresh' content='0; url=./batida_ponto.php'/>";
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
				rodape();
				exit;
			}
		}

		$error = "notfound";
		$_POST["HTTP_REFERER"] = $_ENV["APP_PATH"]."/index.php?error=".$error;
		$_POST["returnValues"] = json_encode([
			"HTTP_REFERER" => $_POST["HTTP_REFERER"],
			"empresa" => $_POST["empresa"],
			"user" => $_POST["user"],
			"password" => $_POST["password"]
		]);

		include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_form.php";
		voltar();
		exit;
	}

	logar();