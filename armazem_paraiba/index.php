<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/
	include_once 'informativos.php';
	if(empty(session_id())){
		$started = session_start();
	}
    include_once "load_env.php";

	$turnos = ['Noite', 'Manhã', 'Tarde', 'Noite'];
	$turnoAtual = $turnos[intval((intval(date('H'))-3)/6)];

	if(isset($_SESSION['user_nb_id']) && !empty($_SESSION['user_nb_id']) && empty($_POST)){ //Se já há um usuário logado e não está tentando um novo login
		include_once "conecta.php";
		cabecalho("");
		bemVindo($_SESSION['user_tx_nome'],$turnoAtual,$_SESSION['horaEntrada']);
		rodape();
		exit;
	}

	if(empty($_POST['user']) && !empty($_POST['username'])){
		$_POST['user'] = $_POST['username'];
	}

	if(!empty($_POST['user']) && !empty($_POST['password'])){//Tentando logar
	
		if(isset($_SESSION['user_nb_id']) && !empty($_SESSION['user_nb_id'])){ //Se já há um usuário logado
			$_SESSION = [];
			session_destroy();
		}else{
			$_SESSION['user_tx_login'] = $_POST['user'];
		}

		
		$interno = true;
		include_once "conecta.php";
		
		$usuario = mysqli_fetch_assoc(
			query(
				"SELECT * FROM user 
					WHERE user_tx_status = 'ativo' 
						AND user_tx_login = '".$_POST['user']."' 
						AND user_tx_senha = '".$_POST['password']."'"
			)
		);

		if(!empty($usuario)){ //Se encontrou um usuário
			$usuario = $usuario;
			$dataHoje = strtotime(date("Y-m-d")); // Transforma a data de hoje em timestamp
			$dataVerificarObj = strtotime($usuario['user_tx_expiracao']);
			if ($dataVerificarObj >= $dataHoje && !empty($usuario['user_tx_expiracao']) && $usuario['user_tx_expiracao'] == '0000-00-00') {
				echo "<div class='alert alert-danger display-block'>
					<span> Usuário expirado. </span>
				</div>";
			} else {
				$_SESSION['user_nb_id'] 		= $usuario['user_nb_id'];
				$_SESSION['user_tx_nome'] 		= $usuario['user_tx_nome'];
				$_SESSION['user_tx_nivel'] 		= $usuario['user_tx_nivel'];
				$_SESSION['user_tx_login'] 		= $usuario['user_tx_login'];
				$_SESSION['user_nb_entidade'] 	= $usuario['user_nb_entidade'];
				$_SESSION['user_nb_empresa'] 	= $usuario['user_nb_empresa'];
				$_SESSION['user_tx_foto'] 		= !empty($usuario['user_tx_foto'])? $usuario['user_tx_foto']: $CONTEX['path'].'/../contex20/img/user.png';

				if(!isset($_SESSION['horaEntrada'])){
					$_SESSION['horaEntrada'] = date('H:i');
				}

				if(!empty($_POST['getSessionValues'])){
					echo json_encode($_SESSION);
					exit;
				}

				if(in_array($_SESSION['user_tx_nivel'], ['Motorista', 'Ajudante'])){
					echo '<meta http-equiv="refresh" content="0; url=./batida_ponto.php"/>';
				}else{
					cabecalho("");
					bemVindo($usuario['user_tx_nome'],$turnoAtual,$_SESSION['horaEntrada']);
					rodape();
				}
	
			}

		}else{
			echo 
				"<form action='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/index.php?error=notfound' name='form_voltar' method='post'>
					<input type='hidden' name='dominio' value='".($_POST['dominio']?? '')."'>
					<input type='hidden' name='user' value='".($_POST['user']?? '')."'>
					<input type='hidden' name='password' value='".($_POST['password']?? '')."'>
				</form>"
			;
			echo "<script>document.form_voltar.submit();</script>";
		}
	}else{
		echo '<meta http-equiv="refresh" content="0; url=./../index.php?error=emptyfields"/>';
	}
