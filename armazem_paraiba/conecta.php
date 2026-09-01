<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
    if(empty(session_id())){
        //Sessão sem limite prático de inatividade: o logout por inatividade fica só na
        //batida_ponto.php (timer de 15s no próprio HTML da tela).
        $lifetime = 12*60*60;
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
	
	if(isset($_SESSION['user_tx_login']) && !isset($_SESSION['domain'])){
		$_SESSION['domain'] = $CONTEX['path'];
	}

	if(!isset($interno) && !isset($_POST['interno'])){
		if(
			(empty($_SESSION['domain']) || $_SESSION['domain'] != $CONTEX['path'])	//Se não há login ou o login é relacionado a outro domínio
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

    // Migração da tabela entidade: colunas de responsável (usadas por assinatura/governança
    // e pelo e-mail/acompanhamento de chamados de suporte pelo responsável do funcionário).
    foreach ([
        "enti_respSetor_id"      => "INT NULL",
        "enti_respCargo_id"      => "INT NULL",
        "enti_respSetor_ids"     => "TEXT NULL",
        "enti_respCargo_ids"     => "TEXT NULL",
        "enti_respFuncionario_id"  => "INT NULL",
        "enti_respFuncionario_ids" => "TEXT NULL",
    ] as $__respCol => $__respTipo) {
        $__checkRespCol = mysqli_query($conn, "SHOW COLUMNS FROM entidade LIKE '{$__respCol}'");
        if ($__checkRespCol && mysqli_num_rows($__checkRespCol) === 0) {
            mysqli_query($conn, "ALTER TABLE entidade ADD COLUMN {$__respCol} {$__respTipo}");
        }
    }

    // Migração da tabela perfil_acesso: coluna pra esconder o campo de salário do
    // funcionário para quem estiver incluído em perfis marcados com essa opção.
    $checkEsconderSalario = mysqli_query($conn, "SHOW COLUMNS FROM perfil_acesso LIKE 'perfil_tx_esconderSalario'");
    if ($checkEsconderSalario && mysqli_num_rows($checkEsconderSalario) === 0) {
        mysqli_query($conn, "ALTER TABLE perfil_acesso ADD COLUMN perfil_tx_esconderSalario ENUM('sim','nao') NOT NULL DEFAULT 'nao'");
    };

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

    // =====================================================
    // MÓDULO DE TREINAMENTO - Migrações
    // =====================================================

    // Tabela principal de treinamentos
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS treinamento (
        trei_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        trei_tx_titulo VARCHAR(255) NOT NULL,
        trei_tx_descricao TEXT,
        trei_tx_conteudo_programatico TEXT,
        trei_tx_tipo ENUM('dss','treinamento') DEFAULT 'treinamento',
        trei_tx_tipo_treinamento ENUM('inicial','periodico','eventual') DEFAULT 'eventual',
        trei_tx_tipo_usuario_permitido TEXT,
        trei_tx_url_video VARCHAR(500),
        trei_tx_tipo_video ENUM('youtube','vimeo','upload') DEFAULT 'youtube',
        trei_nb_carga_horaria INT DEFAULT 0,
        trei_nb_dias_validade INT DEFAULT 365,
        trei_tx_thumbnail VARCHAR(500),
        trei_dt_data_publicacao DATETIME,
        trei_dt_data_liberacao DATETIME,
        trei_tx_status ENUM('ativo','inativo') DEFAULT 'ativo',
        trei_nb_obrigatorio TINYINT(1) DEFAULT 0,
        trei_tx_avaliacao_pergunta TEXT,
        trei_tx_avaliacao_opcoes TEXT,
        trei_nb_avaliacao_resposta_correta INT,
        trei_nb_quantidade_questoes_prova INT DEFAULT 5,
        trei_nb_nota_minima_aprovacao INT DEFAULT 70,
        trei_dt_data_cadastro DATETIME,
        trei_dt_data_atualiza DATETIME
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Tabela de materiais de apoio
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS treinamento_material (
        tram_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        tram_nb_treinamento_id INT NOT NULL,
        tram_tx_nome VARCHAR(255),
        tram_tx_descricao TEXT,
        tram_tx_arquivo VARCHAR(500),
        tram_tx_tipo_arquivo VARCHAR(50),
        tram_nb_tamanho INT,
        tram_nb_ordem INT DEFAULT 0,
        tram_tx_status ENUM('ativo','inativo') DEFAULT 'ativo',
        tram_dt_data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_treinamento (tram_nb_treinamento_id),
        FOREIGN KEY (tram_nb_treinamento_id) REFERENCES treinamento(trei_nb_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Tabela de questões (banco de provas)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS treinamento_questao (
        treq_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        treq_nb_treinamento_id INT NOT NULL,
        treq_tx_pergunta TEXT NOT NULL,
        treq_tx_opcoes JSON NOT NULL,
        treq_nb_resposta_correta INT NOT NULL,
        treq_nb_ordem INT DEFAULT 0,
        treq_tx_status ENUM('ativo','inativo') DEFAULT 'ativo',
        treq_dt_data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_treinamento (treq_nb_treinamento_id),
        FOREIGN KEY (treq_nb_treinamento_id) REFERENCES treinamento(trei_nb_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Tabela de progresso do usuário
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS treinamento_progresso (
        trepr_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        trepr_nb_usuario_id INT NOT NULL,
        trepr_nb_treinamento_id INT NOT NULL,
        trepr_dt_data_inicio DATETIME,
        trepr_nb_tempo_assistido INT DEFAULT 0,
        trepr_nb_porcentagem_assistida DECIMAL(5,2) DEFAULT 0,
        trepr_nb_avaliacao_aprovada TINYINT(1) DEFAULT 0,
        trepr_nb_avaliacao_tentativas INT DEFAULT 0,
        trepr_tx_avaliacao_respostas_json JSON,
        trepr_nb_avaliacao_nota DECIMAL(5,2),
        trepr_nb_concluido TINYINT(1) DEFAULT 0,
        trepr_dt_data_conclusao DATETIME,
        trepr_dt_data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_usuario_treinamento (trepr_nb_usuario_id, trepr_nb_treinamento_id),
        KEY idx_treinamento (trepr_nb_treinamento_id),
        FOREIGN KEY (trepr_nb_usuario_id) REFERENCES user(user_nb_id) ON DELETE CASCADE,
        FOREIGN KEY (trepr_nb_treinamento_id) REFERENCES treinamento(trei_nb_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Tabela de atribuições (treinamento x usuário)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS treinamento_atribuicao (
        treate_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        treate_nb_treinamento_id INT NOT NULL,
        treate_nb_usuario_id INT NOT NULL,
        treate_dt_data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_treinamento_usuario (treate_nb_treinamento_id, treate_nb_usuario_id),
        KEY idx_usuario (treate_nb_usuario_id),
        FOREIGN KEY (treate_nb_treinamento_id) REFERENCES treinamento(trei_nb_id) ON DELETE CASCADE,
        FOREIGN KEY (treate_nb_usuario_id) REFERENCES user(user_nb_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Tabela de logs de auditoria
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS treinamento_log (
        trelog_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        trelog_nb_treinamento_id INT NOT NULL,
        trelog_nb_usuario_id INT NOT NULL,
        trelog_tx_evento VARCHAR(100),
        trelog_tx_detalhe TEXT,
        trelog_tx_ip VARCHAR(45),
        trelog_tx_user_agent TEXT,
        trelog_dt_data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_treinamento (trelog_nb_treinamento_id),
        KEY idx_usuario (trelog_nb_usuario_id),
        FOREIGN KEY (trelog_nb_treinamento_id) REFERENCES treinamento(trei_nb_id) ON DELETE CASCADE,
        FOREIGN KEY (trelog_nb_usuario_id) REFERENCES user(user_nb_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Tabela de bloqueios individuais (usuário desmarcado não vê o treinamento mesmo com perfil)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS treinamento_bloqueio (
        trebl_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        trebl_nb_treinamento_id INT NOT NULL,
        trebl_nb_usuario_id INT NOT NULL,
        trebl_dt_data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_treinamento_usuario (trebl_nb_treinamento_id, trebl_nb_usuario_id),
        KEY idx_usuario (trebl_nb_usuario_id),
        FOREIGN KEY (trebl_nb_treinamento_id) REFERENCES treinamento(trei_nb_id) ON DELETE CASCADE,
        FOREIGN KEY (trebl_nb_usuario_id) REFERENCES user(user_nb_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // =====================================================

	include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_grid.php";
	include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_form.php";
	include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes.php";

