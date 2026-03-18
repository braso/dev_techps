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

    // Tabela Principal de RFIDs
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rfids (
        rfids_nb_id INT(11) AUTO_INCREMENT PRIMARY KEY,
        rfids_tx_uid VARCHAR(255) NOT NULL UNIQUE,
        rfids_nb_user_id INT(11) DEFAULT NULL,
        rfids_tx_status ENUM('ativo', 'disponivel', 'excluido') DEFAULT 'disponivel',
        rfids_tx_motivo_exclusao VARCHAR(100) DEFAULT NULL,
        rfids_tx_descricao TEXT,
        rfid_dt_created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // Tabela de Log de Auditoria (Já nasce com os nomes de colunas novos)
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
    
    // Migração Segura da tabela RFIDs
    $checkMotivo = mysqli_query($conn, "SHOW COLUMNS FROM rfids LIKE 'rfids_tx_motivo_exclusao'");
    if ($checkMotivo && mysqli_num_rows($checkMotivo) == 0) {
        
        // Passo 1: Cria a nova coluna para guardar a justificativa
        mysqli_query($conn, "ALTER TABLE rfids ADD COLUMN rfids_tx_motivo_exclusao VARCHAR(100) DEFAULT NULL AFTER rfids_tx_status;");
        
        // Passo 2: SALVA OS DADOS! Se estava 'perdido', o motivo vira 'perdido' e o status vira 'excluido'.
        mysqli_query($conn, "UPDATE rfids 
                             SET rfids_tx_motivo_exclusao = rfids_tx_status, 
                                 rfids_tx_status = 'excluido' 
                             WHERE rfids_tx_status IN ('bloqueado', 'perdido', 'quebrado');");
        
        // Passo 3: Com os dados a salvo, restringe o ENUM para o novo padrão do sistema
        mysqli_query($conn, "ALTER TABLE rfids MODIFY COLUMN rfids_tx_status ENUM('ativo', 'disponivel', 'excluido') DEFAULT 'disponivel';");
    };

    // 2. Tabela rfids_log: Atualiza entidade_anterior para user_anterior
    $checkCol2 = mysqli_query($conn, "SHOW COLUMNS FROM rfids_log LIKE 'rlog_nb_entidade_anterior'");
    if ($checkCol2 && mysqli_num_rows($checkCol2) > 0) {
        mysqli_query($conn, "ALTER TABLE rfids_log CHANGE rlog_nb_entidade_anterior rlog_nb_user_anterior INT(11) DEFAULT NULL;");
    };

    // 3. Tabela rfids_log: Atualiza entidade_nova para user_novo
    $checkCol3 = mysqli_query($conn, "SHOW COLUMNS FROM rfids_log LIKE 'rlog_nb_entidade_nova'");
    if ($checkCol3 && mysqli_num_rows($checkCol3) > 0) {
        mysqli_query($conn, "ALTER TABLE rfids_log CHANGE rlog_nb_entidade_nova rlog_nb_user_novo INT(11) DEFAULT NULL;");
    };


	include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_grid.php";
	include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_form.php";
	include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes.php";