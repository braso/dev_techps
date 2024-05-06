<?php
	include_once "../load_env.php";
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/	
	
	global $_SESSION, $CONTEX, $conn;
	date_default_timezone_set('America/Fortaleza');

	$CONTEX['path'] = $_ENV["APP_PATH"]; //Alterar de acordo com o domínio em que se encontra

	$lifetime = 30*60;
	ini_set('session.gc_maxlifetime', $lifetime);
	
	// session_cache_limiter("public, no-store");

	if(empty(session_id())){
		session_start();
	}
	$_SESSION['last_activity'] = time();
	if(isset($_SESSION['user_tx_login']) && !isset($_SESSION['domain'])){
		$_SESSION['domain'] = $CONTEX['path'];
	}

	if(!isset($interno)){
		if(
			(empty($_SESSION['last_activity']) || (time()-(int)$_SESSION['last_activity'] > (int)ini_get('session.gc_maxlifetime')))	//Se a sessão expirou
			|| (empty($_SESSION['domain']) || $_SESSION['domain'] != $CONTEX['path'])													//ou se o login é relacionado a outro domínio
		){
			echo 
				"<form action='".$_ENV["URL_BASE"].$CONTEX['path']."/logout.php' name='form_logout' method='post'>
				</form>"
			;
			echo "<script>document.form_logout.submit();</script>";
			exit;
		}
	}

	$_SESSION['last_activity'] = time();
	

	//CONEXÃO BASE DE DADOS{
		$servername = $_ENV["DB_HOST"];
		$username = $_ENV["DB_USER"];
		$password = $_ENV["DB_PASSWORD"];
		$dbname = $_ENV["DB_NAME"];
		$conn = mysqli_connect($servername, $username, $password, $dbname) or die("Connection failed: " . mysqli_connect_error());
		$conn->set_charset("utf8");
	//}

	include_once "../contex20/funcoes_grid.php";
	include_once "../contex20/funcoes_form.php";
	include_once "../contex20/funcoes.php";
?>