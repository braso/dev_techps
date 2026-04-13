<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
    if(empty(session_id())){
        $lifetime = 30*60;
        ini_set('session.gc_maxlifetime', $lifetime);
    }
    if(empty(session_id())){
        session_start();
    }

	
	include_once __DIR__."/load_env.php";
	
	global $_SESSION, $CONTEX, $conn;
	date_default_timezone_set('America/Fortaleza');
	
	$CONTEX['path'] = $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"];
	
	
	// session_cache_limiter("public, no-store");
	
	$_SESSION['last_activity'] = time();
	if(isset($_SESSION['user_tx_login']) && !isset($_SESSION['domain'])){
		$_SESSION['domain'] = $CONTEX['path'];
	}
	
	if(!isset($interno) && !isset($_POST['interno'])){
		if(
			(empty($_SESSION['last_activity']) || (time()-(int)$_SESSION['last_activity'] > (int)ini_get('session.gc_maxlifetime')))	//Se a sessão expirou
			|| (empty($_SESSION['domain']) || $_SESSION['domain'] != $CONTEX['path'])													//ou se o login é relacionado a outro domínio
		){
			echo 
				"<form action='".$_ENV["URL_BASE"].$CONTEX['path']."/logout.php' name='form_logout' method='post'>
					<input name='sourcePage' type='hidden' value='".$_SERVER["REQUEST_URI"]."'>
				</form>"
			;
			echo "<script>document.form_logout.submit();</script>";
			exit;
		}
	}

	$_SESSION['last_activity'] = time();
	
	//CONEXÃO BASE DE DADOS{
		$conn = mysqli_connect(
			$_ENV["DB_HOST"],
			$_ENV["DB_USER"],
			$_ENV["DB_PASSWORD"],
			$_ENV["DB_NAME"]
		) or die("Connection failed: ".mysqli_connect_error());
		$conn->set_charset("utf8");
	//}
	
    // INICIALIZAÇÃO DE TABELAS (GARANTE A ESTRUTURA PARA CLIENTES NOVOS)

    // 1. Tabela Principal de RFIDs
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rfids (
        rfids_nb_id INT(11) AUTO_INCREMENT PRIMARY KEY,
        rfids_tx_uid VARCHAR(255) NOT NULL UNIQUE,
        rfids_nb_user_id INT(11) DEFAULT NULL,
        rfids_tx_status ENUM('ativo', 'disponivel', 'excluido') DEFAULT 'disponivel',
        rfids_tx_motivo_exclusao VARCHAR(100) DEFAULT NULL,
        rfids_tx_descricao TEXT,
        rfid_dt_created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 2. Tabela de Log de Auditoria RFIDs
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rfids_log (
        rlog_nb_id INT(11) AUTO_INCREMENT PRIMARY KEY,
        rlog_nb_rfid_id INT(11) NOT NULL,
        rlog_tx_acao VARCHAR(50) NOT NULL,
        rlog_tx_status_anterior VARCHAR(20) DEFAULT NULL,
        rlog_tx_status_novo VARCHAR(20) DEFAULT NULL,
        rlog_nb_user_anterior INT(11) DEFAULT NULL,
        rlog_nb_user_novo INT(11) DEFAULT NULL,
        rlog_tx_motivo TEXT DEFAULT NULL,
        rlog_nb_user_atualiza INT(11) NOT NULL,
        rlog_dt_data DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 3. Tabela de Equipamentos (IoT)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS equipamentos (
        equip_nb_id INT(11) AUTO_INCREMENT PRIMARY KEY,
        equip_tx_nome VARCHAR(100) NOT NULL,
        equip_tx_tipo ENUM('embarcado','estacao_fixa') DEFAULT 'embarcado',
        equip_tx_identificador VARCHAR(100) NOT NULL UNIQUE,
        equip_tx_token VARCHAR(64) DEFAULT NULL,
        equip_tx_status ENUM('ativo','inativo','manutencao','excluido') DEFAULT 'ativo',
        equip_dt_created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Tabela de Log de Auditoria dos Equipamentos
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS equipamentos_log (
        elog_nb_id INT(11) AUTO_INCREMENT PRIMARY KEY,
        elog_nb_equip_id INT(11) NOT NULL,
        elog_nb_user_atualiza INT(11) DEFAULT NULL,
        elog_tx_acao VARCHAR(50) NOT NULL,
        elog_tx_status_anterior VARCHAR(50) DEFAULT NULL,
        elog_tx_status_novo VARCHAR(50) DEFAULT NULL,
        elog_tx_motivo VARCHAR(255) DEFAULT NULL,
        elog_dt_data DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_elog_equip (elog_nb_equip_id),
        CONSTRAINT fk_elog_equip FOREIGN KEY (elog_nb_equip_id) REFERENCES equipamentos (equip_nb_id) ON DELETE CASCADE ON UPDATE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 5. Tabela de Digitais
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS digitais (
        digitais_nb_id INT(11) AUTO_INCREMENT PRIMARY KEY,
        digitais_nb_user_id INT(11) NOT NULL,
        digitais_nb_equipamento_id INT(11) NOT NULL,
        digitais_nb_id_sensor INT(11) NOT NULL,
        digitais_tx_dedo ENUM('Polegar Direito','Indicador Direito','Medio Direito','Anelar Direito','Minimo Direito','Polegar Esquerdo','Indicador Esquerdo','Medio Esquerdo','Anelar Esquerdo','Minimo Esquerdo') NOT NULL,
        digitais_bl_template TEXT DEFAULT NULL,
        digitais_tx_status ENUM('ativo','pendente_sync','excluido') DEFAULT 'pendente_sync',
        digitais_tx_motivo_exclusao VARCHAR(100) DEFAULT NULL,
        digitais_dt_ultimo_acesso DATETIME DEFAULT CURRENT_TIMESTAMP,
        digitais_dt_created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        digitais_dt_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_dig_user (digitais_nb_user_id),
        INDEX idx_dig_equip (digitais_nb_equipamento_id),
        INDEX idx_dig_status (digitais_tx_status),
        CONSTRAINT fk_dig_equip FOREIGN KEY (digitais_nb_equipamento_id) REFERENCES equipamentos (equip_nb_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
        CONSTRAINT fk_dig_user FOREIGN KEY (digitais_nb_user_id) REFERENCES user (user_nb_id) ON DELETE RESTRICT ON UPDATE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 6. Tabela de Log de Auditoria das Digitais
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS digitais_log (
        dlog_nb_id INT(11) AUTO_INCREMENT PRIMARY KEY,
        dlog_nb_digital_id INT(11) NOT NULL,
        dlog_tx_acao VARCHAR(50) NOT NULL,
        dlog_tx_status_anterior VARCHAR(50) DEFAULT NULL,
        dlog_tx_status_novo VARCHAR(50) DEFAULT NULL,
        dlog_nb_user_atualiza INT(11) DEFAULT NULL,
        dlog_tx_motivo VARCHAR(255) DEFAULT NULL,
        dlog_dt_data DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_dlog_digital (dlog_nb_digital_id),
        CONSTRAINT fk_dlog_digital FOREIGN KEY (dlog_nb_digital_id) REFERENCES digitais (digitais_nb_id) ON DELETE CASCADE ON UPDATE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

	include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_grid.php";
	include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_form.php";
	include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes.php";
?>