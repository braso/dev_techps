<?
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	include_once "conecta.php";	
	
	if(date('H')>=6 && date("H")<=12){
		$turno='Manhã';
	}elseif(date('H')>=13 && date("H")<=18){
		$turno='Tarde';
	}else{
		$turno='Noite';
	}

	if(!empty($_GET['user']) && !empty($_GET['password'])){

		$sql = query("SELECT * FROM user WHERE user_tx_status != 'inativo' AND user_tx_login = '$_GET[user]' AND user_tx_senha = '$_GET[password]'");
		
		if(mysqli_num_rows($sql)>0){
			
			$a = mysqli_fetch_array($sql);
			$dataHoje = strtotime(date("Y-m-d")); // Transforma a data de hoje em timestamp
			$dataVerificarObj = strtotime($a['user_tx_expiracao']);
			if ($dataVerificarObj >= $dataHoje && !empty($a['user_tx_expiracao']) && $a['user_tx_expiracao'] == '0000-00-00') {
				$msg = "<div class='alert alert-danger display-block'>
					<span> Usuário expirado. </span>
				</div>";
			} else {
				$_SESSION['user_nb_id'] 		= $a['user_nb_id'];
				$_SESSION['user_tx_nivel'] 		= $a['user_tx_nivel'];
				$_SESSION['user_tx_login'] 		= $a['user_tx_login'];
				$_SESSION['user_nb_entidade'] 	= $a['user_nb_entidade'];
				$_SESSION['user_nb_empresa'] 	= $a['user_nb_empresa'];
				$_SESSION['user_tx_foto'] 		= !empty($a['user_tx_foto'])? $a['user_tx_foto']: '/contex20/img/user.png';
			}

			if(!isset($_SESSION['horaEntrada'])){
				$_SESSION['horaEntrada'] = date('H:i');
				$_SESSION['user_tx_nome'] = $a['user_tx_nome'];
			}

			cabecalho("Bem-Vindo ao sistema TechPS, $a[user_tx_nome]. Período da $turno iniciado às ".$_SESSION['horaEntrada']);

			rodape();
		}else{
		    $dev = (is_int(strpos($_SERVER["REQUEST_URI"], 'dev_')) || is_int(strpos(($_POST["path"]?? ''), 'dev_'))? "dev_techps": "techps");
			header("Location: https://braso.mobi/".$dev."/index2.php?erro=1");
			exit;
		}
	}elseif(isset($_SESSION['user_nb_id']) && $_SESSION['user_nb_id']>0){
		cabecalho("Bem-Vindo ao sistema TechPS, ".$_SESSION['user_tx_nome'].". Período da $turno iniciado às ".$_SESSION['horaEntrada']);

		rodape();
	} else {
		echo '<meta http-equiv="refresh" content="0; url=./../index2.php" />';
		exit;
	}
?>