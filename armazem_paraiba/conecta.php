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
		$conn->set_charset("utf8mb4");
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

    // Migração da tabela de ajustes: chave de lote para agrupar um unico PDF por envio.
    $checkEnvioDoc = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_ajuste LIKE 'data_envio_documento'");
    if ($checkEnvioDoc && mysqli_num_rows($checkEnvioDoc) == 0) {
        mysqli_query($conn, "ALTER TABLE solicitacoes_ajuste ADD COLUMN data_envio_documento DATETIME NULL AFTER data_visualizacao;");
    };

    // Migração da tabela de instancias de documento: suporte ao vinculo por entidade e data de referencia.
    $checkInstEnt = mysqli_query($conn, "SHOW COLUMNS FROM inst_documento_modulo LIKE 'inst_nb_entidade'");
    if ($checkInstEnt && mysqli_num_rows($checkInstEnt) == 0) {
        mysqli_query($conn, "ALTER TABLE inst_documento_modulo ADD COLUMN inst_nb_entidade INT NULL AFTER inst_nb_user;");
    };

    $checkInstRef = mysqli_query($conn, "SHOW COLUMNS FROM inst_documento_modulo LIKE 'inst_tx_data_referencia'");
    if ($checkInstRef && mysqli_num_rows($checkInstRef) == 0) {
        mysqli_query($conn, "ALTER TABLE inst_documento_modulo ADD COLUMN inst_tx_data_referencia DATE NULL AFTER inst_nb_entidade;");
    };

    // Migração da tabela parametro: coluna para abonar feriados automaticamente na escala
    $checkAbonarFeriado = mysqli_query($conn, "SHOW COLUMNS FROM parametro LIKE 'para_tx_abonarFeriadoEscala'");
    if ($checkAbonarFeriado && mysqli_num_rows($checkAbonarFeriado) == 0) {
        mysqli_query($conn, "ALTER TABLE parametro ADD COLUMN para_tx_abonarFeriadoEscala ENUM('sim','nao') NOT NULL DEFAULT 'nao' COMMENT 'Abonar automaticamente feriados na escala'");
    };

    // Migração da tabela endosso: colunas necessárias para o cadastro atual
    $checkEndossoNome = mysqli_query($conn, "SHOW COLUMNS FROM endosso LIKE 'endo_tx_nome'");
    if ($checkEndossoNome && mysqli_num_rows($checkEndossoNome) == 0) {
        mysqli_query($conn, "ALTER TABLE endosso ADD COLUMN endo_tx_nome VARCHAR(255) NULL AFTER endo_nb_entidade");
    };
    $checkEndossoEmpresa = mysqli_query($conn, "SHOW COLUMNS FROM endosso LIKE 'endo_nb_empresa'");
    if ($checkEndossoEmpresa && mysqli_num_rows($checkEndossoEmpresa) == 0) {
        mysqli_query($conn, "ALTER TABLE endosso ADD COLUMN endo_nb_empresa INT NULL AFTER endo_tx_nome");
    };
    $checkEndossoPontos = mysqli_query($conn, "SHOW COLUMNS FROM endosso LIKE 'endo_tx_pontos'");
    if ($checkEndossoPontos && mysqli_num_rows($checkEndossoPontos) == 0) {
        mysqli_query($conn, "ALTER TABLE endosso ADD COLUMN endo_tx_pontos LONGTEXT NULL AFTER endo_tx_max50APagar");
    };
    $checkEndossoResumo = mysqli_query($conn, "SHOW COLUMNS FROM endosso LIKE 'totalResumo'");
    if ($checkEndossoResumo && mysqli_num_rows($checkEndossoResumo) == 0) {
        mysqli_query($conn, "ALTER TABLE endosso ADD COLUMN totalResumo LONGTEXT NULL AFTER endo_tx_pontos");
    };

    // Migração da tabela entidade: aumentar o tamanho do saldo de horas para varchar(15)
    $checkBancoCol = mysqli_query($conn, "SHOW COLUMNS FROM entidade LIKE 'enti_tx_banco'");
    if ($checkBancoCol && $row = mysqli_fetch_assoc($checkBancoCol)) {
        if ($row['Type'] === 'varchar(8)') {
            mysqli_query($conn, "ALTER TABLE entidade MODIFY COLUMN enti_tx_banco VARCHAR(15) DEFAULT '00:00';");
        }
    }

    // Criação da tabela feriado_funcionario se não existir
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS feriado_funcionario (
        fefi_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        fefi_tx_nome VARCHAR(255) NOT NULL,
        fefi_tx_data DATE NOT NULL,
        fefi_nb_entidade INT NOT NULL,
        fefi_tx_status VARCHAR(10) DEFAULT 'ativo',
        fefi_nb_userCadastro INT DEFAULT NULL,
        fefi_tx_dataCadastro DATETIME DEFAULT NULL,
        UNIQUE KEY uk_feriado_funcionario_data_entidade (fefi_tx_data, fefi_nb_entidade)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;");

    // Criação da tabela feriado_parametro se não existir
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS feriado_parametro (
        feit_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        feit_nb_parametro INT NOT NULL,
        feit_tx_titulo VARCHAR(255) NOT NULL,
        feit_tx_data DATE NOT NULL,
        feit_tx_status VARCHAR(10) DEFAULT 'ativo',
        feit_nb_userCadastro INT DEFAULT NULL,
        feit_tx_dataCadastro DATETIME DEFAULT NULL,
        KEY idx_parametro (feit_nb_parametro),
        KEY idx_data (feit_tx_data)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;");

    // Criação da tabela poi_tipo se não existir
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS poi_tipo (
        poti_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        poti_tx_codigo VARCHAR(50) NOT NULL UNIQUE,
        poti_tx_nome VARCHAR(100) NOT NULL,
        poti_tx_emoji VARCHAR(10) NOT NULL DEFAULT '📌',
        poti_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $seedTipos = [
        ['fa-box',           'Caixa',                        '📦'],
        ['fa-building',      'Prédio',                       '🏢'],
        ['fa-industry',      'Indústria',                    '🏭'],
        ['fa-store',         'Loja',                         '🏪'],
        ['fa-gas-pump',      'Posto',                        '⛽'],
        ['fa-parking',       'Estacionamento',               '🅿️'],
        ['fa-hospital',      'Hospital',                     '🏥'],
        ['fa-university',    'Banco',                        '🏦'],
        ['fa-utensils',      'Restaurante',                  '🍽️'],
        ['fa-hotel',         'Hotel',                        '🏨'],
        ['fa-warehouse',     'Armazém',                      '🏭'],
        ['fa-truck',         'Caminhão',                     '🚚'],
        ['fa-map-pin',       'Alfinete',                     '📍'],
        ['fa-flag-checkered','Ponto de Jornada',             '🏁'],
        ['Balança Rodoviária','Balança Rodoviária',          '⚖️'],
        ['INÍCIO DE ESPERA', 'INÍCIO DE ESPERA',              '⏸️'],
        ['FIM DE ESPERA',    'FIM DE ESPERA',                 '▶️'],
        ['FIM DE DESCANSO',  'FIM DE DESCANSO',              '▶️'],
        ['FIM DE REPOUSO',   'FIM DE REPOUSO',               '▶️'],
        ['Posto de Gasolina','Posto de Gasolina',            '⛽'],
        ['Embarcadouro',     'Embarcadouro',                 '⚓'],
        ['Pesagem',          'Pesagem',                      '⚖️'],
        ['Posto Fiscal',     'Posto Fiscal',                 '🏛️'],
        ['PRF - Polícia Rodoviária Federal','PRF - Polícia Rodoviária Federal','👮'],
        ['PM - Polícia Militar','PM - Polícia Militar',      '👮‍♂️'],
        ['Pedágios',         'Pedágios',                     '🛣️'],
        ['INÍCIO DE JORNADA','INÍCIO DE JORNADA',            '🏁'],
        ['INÍCIO REFEIÇÃO',  'INÍCIO REFEIÇÃO',              '🍽️'],
        ['FIM REFEIÇÃO',     'FIM REFEIÇÃO',                 '🍽️'],
        ['INÍCIO DE DESCANSO','INÍCIO DE DESCANSO',          '💤'],
        ['INÍCIO DE REPOUSO','INÍCIO DE REPOUSO',            '😴'],
        ['INÍCIO DE PERNOITE','INÍCIO DE PERNOITE',          '🌙'],
        ['FIM DE PERNOITE',  'FIM DE PERNOITE',              '🌅'],
        ['FIM DE JORNADA',   'FIM DE JORNADA',               '🔚'],
        ['Oficina',          'Oficina',                      '🔧'],
        ['Garagem',          'Garagem',                      '🅿️'],
        ['Base/Terminal',    'Base/Terminal',                '🏢'],
        ['Cliente',          'Cliente',                      '🤝'],
        ['Fornecedor',       'Fornecedor',                   '📦'],
        ['Pátio',            'Pátio',                        '🏭'],
        ['Porto Seco',       'Porto Seco',                   '🚢'],
        ['Almoxarifado',     'Almoxarifado',                 '📦'],
        ['Centro de Distribuição','Centro de Distribuição',  '🏭'],
        ['Ponto de Apoio',   'Ponto de Apoio',               '🆘'],
        ['Parada Obrigatória','Parada Obrigatória',          '🛑'],
        ['Fronteira',        'Fronteira',                    '🚧'],
        ['Alfândega',        'Alfândega',                    '🛃'],
        ['Garagem Cliente',  'Garagem Cliente',              '🏠'],
    ];
    foreach ($seedTipos as $t) {
        $rsChk = mysqli_query($conn, "SELECT 1 FROM poi_tipo WHERE poti_tx_codigo = '".mysqli_real_escape_string($conn, $t[0])."' LIMIT 1");
        if ($rsChk && !mysqli_fetch_assoc($rsChk)) {
            $c = mysqli_real_escape_string($conn, $t[0]);
            $n = mysqli_real_escape_string($conn, $t[1]);
            $e = mysqli_real_escape_string($conn, $t[2]);
            mysqli_query($conn, "INSERT INTO poi_tipo (poti_tx_codigo, poti_tx_nome, poti_tx_emoji) VALUES ('$c', '$n', '$e')");
        }
    }

	include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_grid.php";
	include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_form.php";
	include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes.php";

