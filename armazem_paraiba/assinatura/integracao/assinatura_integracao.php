<?php

function assinatura_integracao_baseRoot(): string {
	$root = dirname(__DIR__, 2);
	$rp = realpath($root);
	return $rp ? rtrim(str_replace("\\", "/", $rp), "/") : rtrim(str_replace("\\", "/", $root), "/");
}

function assinatura_integracao_baseAssinatura(): string {
	$base = dirname(__DIR__);
	$rp = realpath($base);
	return $rp ? rtrim(str_replace("\\", "/", $rp), "/") : rtrim(str_replace("\\", "/", $base), "/");
}

function assinatura_integracao_sanitizarNomeArquivo(string $nome): string {
	$nome = trim($nome);
	if($nome === ""){
		$nome = "documento.pdf";
	}
	$nome = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '_', $nome);
	$nome = trim(strval($nome));
	if($nome === ""){
		$nome = "documento.pdf";
	}
	if(strtolower(pathinfo($nome, PATHINFO_EXTENSION)) !== "pdf"){
		$nome .= ".pdf";
	}
	return $nome;
}

function assinatura_integracao_resolverArquivoAbs(string $path): string {
	$p = trim(strval($path));
	if($p === ""){
		return "";
	}

	$p = str_replace("\\", "/", $p);
	$cands = [];

	if(preg_match('#^[a-zA-Z]:/#', $p) || strpos($p, "/") === 0){
		$cands[] = $p;
	}

	$root = assinatura_integracao_baseRoot();
	$assinatura = assinatura_integracao_baseAssinatura();

	$cands[] = $root . "/" . ltrim($p, "/");
	$cands[] = $assinatura . "/" . ltrim($p, "/");

	$docRoot = str_replace("\\", "/", rtrim(strval($_SERVER["DOCUMENT_ROOT"] ?? ""), "/"));
	if($docRoot !== ""){
		$cands[] = $docRoot . "/" . ltrim($p, "/");
	}

	foreach($cands as $cand){
		$cand = strval($cand);
		if($cand === ""){
			continue;
		}
		if(file_exists($cand) && is_file($cand)){
			$rp = realpath($cand);
			if($rp){
				return str_replace("\\", "/", $rp);
			}
			return str_replace("\\", "/", $cand);
		}
	}

	return "";
}

function assinatura_integracao_validarArquivoPdfAbs(string $abs): bool {
	if($abs === "" || !file_exists($abs) || !is_file($abs)){
		return false;
	}
	$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
	if($ext !== "pdf"){
		return false;
	}
	return true;
}

function assinatura_integracao_ensureTables(mysqli $conn): void {
	$res = mysqli_query($conn, "SHOW TABLES LIKE 'solicitacoes_assinatura'");
	if($res && mysqli_num_rows($res) == 0){
		mysqli_query(
			$conn,
			"CREATE TABLE IF NOT EXISTS solicitacoes_assinatura (
				id INT AUTO_INCREMENT PRIMARY KEY,
				token VARCHAR(64) NOT NULL UNIQUE,
				email VARCHAR(255) NOT NULL,
				nome VARCHAR(255),
				caminho_arquivo VARCHAR(255) NOT NULL,
				nome_arquivo_original VARCHAR(255),
				tipo_documento_id INT NULL,
				validar_icp ENUM('sim','nao') NOT NULL DEFAULT 'nao',
				modo_envio VARCHAR(50) DEFAULT 'avulso',
				grupo_envio VARCHAR(100) DEFAULT '',
				data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
				expires_at DATETIME NULL,
				status VARCHAR(50) DEFAULT 'pendente',
				id_documento VARCHAR(100),
				data_assinatura DATETIME NULL,
				status_final VARCHAR(50) DEFAULT 'pendente'
			)"
		);
	} else {
		$cols = [
			"nome" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN nome VARCHAR(255)",
			"nome_arquivo_original" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN nome_arquivo_original VARCHAR(255)",
			"tipo_documento_id" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN tipo_documento_id INT NULL",
			"validar_icp" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN validar_icp ENUM('sim','nao') NOT NULL DEFAULT 'nao'",
			"modo_envio" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN modo_envio VARCHAR(50) DEFAULT 'avulso'",
			"grupo_envio" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN grupo_envio VARCHAR(100) DEFAULT ''",
			"expires_at" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN expires_at DATETIME NULL",
			"data_assinatura" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN data_assinatura DATETIME NULL",
			"status_final" => "ALTER TABLE solicitacoes_assinatura ADD COLUMN status_final VARCHAR(50) DEFAULT 'pendente'"
		];
		foreach($cols as $col => $ddl){
			$check = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE '{$col}'");
			if($check && mysqli_num_rows($check) == 0){
				@mysqli_query($conn, $ddl);
			}
		}
	}

	$resA = mysqli_query($conn, "SHOW TABLES LIKE 'assinantes'");
	if($resA && mysqli_num_rows($resA) == 0){
		mysqli_query(
			$conn,
			"CREATE TABLE IF NOT EXISTS assinantes (
				id INT AUTO_INCREMENT PRIMARY KEY,
				id_solicitacao INT NOT NULL,
				enti_nb_id INT NULL,
				nome VARCHAR(255) NOT NULL,
				email VARCHAR(255) NOT NULL,
				cpf VARCHAR(20),
				funcao VARCHAR(100),
				ordem INT NOT NULL DEFAULT 1,
				token VARCHAR(64) NOT NULL UNIQUE,
				status VARCHAR(50) DEFAULT 'pendente',
				data_assinatura DATETIME NULL,
				ip VARCHAR(45),
				metadados TEXT,
				salvar_documento_funcionario ENUM('sim','nao') NOT NULL DEFAULT 'nao',
				INDEX (id_solicitacao),
				INDEX (token)
			)"
		);
	} else {
		$cols = [
			"enti_nb_id" => "ALTER TABLE assinantes ADD COLUMN enti_nb_id INT NULL AFTER id_solicitacao",
			"cpf" => "ALTER TABLE assinantes ADD COLUMN cpf VARCHAR(20)",
			"funcao" => "ALTER TABLE assinantes ADD COLUMN funcao VARCHAR(100)",
			"ordem" => "ALTER TABLE assinantes ADD COLUMN ordem INT NOT NULL DEFAULT 1",
			"data_assinatura" => "ALTER TABLE assinantes ADD COLUMN data_assinatura DATETIME NULL",
			"ip" => "ALTER TABLE assinantes ADD COLUMN ip VARCHAR(45)",
			"metadados" => "ALTER TABLE assinantes ADD COLUMN metadados TEXT",
			"salvar_documento_funcionario" => "ALTER TABLE assinantes ADD COLUMN salvar_documento_funcionario ENUM('sim','nao') NOT NULL DEFAULT 'nao'"
		];
		foreach($cols as $col => $ddl){
			$check = mysqli_query($conn, "SHOW COLUMNS FROM assinantes LIKE '{$col}'");
			if($check && mysqli_num_rows($check) == 0){
				@mysqli_query($conn, $ddl);
			}
		}
	}

	$resS = mysqli_query($conn, "SHOW TABLES LIKE 'assinatura_eletronica'");
	if($resS && mysqli_num_rows($resS) == 0){
		mysqli_query(
			$conn,
			"CREATE TABLE IF NOT EXISTS assinatura_eletronica (
				id INT AUTO_INCREMENT PRIMARY KEY,
				nome VARCHAR(255),
				email VARCHAR(255),
				cpf VARCHAR(20),
				rg VARCHAR(50),
				data_assinatura DATETIME NULL,
				ip_address VARCHAR(45),
				user_agent TEXT,
				latitude VARCHAR(50),
				longitude VARCHAR(50),
				hash_assinatura VARCHAR(64),
				id_documento VARCHAR(100),
				caminho_arquivo VARCHAR(255)
			)"
		);
	}
}

function assinatura_integracao_carregarEmail(): void {
	$assinaturaDir = assinatura_integracao_baseAssinatura();

	$config = $assinaturaDir . "/email_config.php";
	if(file_exists($config)){
		require_once $config;
	}

	$candidates = [
		$assinaturaDir . "/../../PHPMailer/src/",
		$assinaturaDir . "/../../../PHPMailer/src/",
		dirname($assinaturaDir, 2) . "/PHPMailer/src/"
	];
	foreach($candidates as $base){
		$base = rtrim(str_replace("\\", "/", strval($base)), "/") . "/";
		if(file_exists($base . "Exception.php") && file_exists($base . "PHPMailer.php") && file_exists($base . "SMTP.php")){
			require_once $base . "Exception.php";
			require_once $base . "PHPMailer.php";
			require_once $base . "SMTP.php";
			break;
		}
	}

	$helper = $assinaturaDir . "/email_helper.php";
	if(file_exists($helper)){
		require_once $helper;
	}
}

function assinatura_integracao_criarSolicitacaoUnicoAssinante(
	mysqli $conn,
	array $entidade,
	string $caminhoRel,
	string $nomeArquivoOriginal,
	array $opts
): array {
	$email = trim(strval($entidade["enti_tx_email"] ?? ""));
	$nome = trim(strval($entidade["enti_tx_nome"] ?? ""));
	$entiNbId = intval($entidade["enti_nb_id"] ?? 0);
	if($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)){
		return ["ok" => false, "error" => "Funcionário sem e-mail válido."];
	}

	$tipoDocumentoId = intval($opts["tipo_documento_id"] ?? 0);
	$validarIcp = strtolower(trim(strval($opts["validar_icp"] ?? "nao"))) === "sim" ? "sim" : "nao";
	$modoEnvio = strtolower(trim(strval($opts["modo_envio"] ?? "avulso")));
	$grupoEnvio = trim(strval($opts["grupo_envio"] ?? ""));
	$funcao = trim(strval($opts["funcao"] ?? "Funcionário"));
	$salvarDoc = strtolower(trim(strval($opts["salvar_documento_funcionario"] ?? "sim"))) === "sim" ? "sim" : "nao";

	$tokenMestre = bin2hex(random_bytes(32));
	$tokenAssinante = bin2hex(random_bytes(32));
	$idDocumento = "DOC-" . date("YmdHis") . "-" . uniqid();

	$stmt = mysqli_prepare(
		$conn,
		"INSERT INTO solicitacoes_assinatura
			(token, email, nome, caminho_arquivo, nome_arquivo_original, id_documento, tipo_documento_id, validar_icp, modo_envio, grupo_envio, expires_at, status)
		VALUES
			(?, ?, ?, ?, ?, ?, NULLIF(?,0), ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 24 HOUR), 'pendente')"
	);
	if(!$stmt){
		return ["ok" => false, "error" => "Falha ao preparar solicitação."];
	}
	mysqli_stmt_bind_param($stmt, "ssssssisss", $tokenMestre, $email, $nome, $caminhoRel, $nomeArquivoOriginal, $idDocumento, $tipoDocumentoId, $validarIcp, $modoEnvio, $grupoEnvio);
	if(!mysqli_stmt_execute($stmt)){
		mysqli_stmt_close($stmt);
		return ["ok" => false, "error" => "Falha ao criar solicitação."];
	}
	mysqli_stmt_close($stmt);
	$idSolicitacao = intval(mysqli_insert_id($conn));

	$stmtA = mysqli_prepare(
		$conn,
		"INSERT INTO assinantes
			(id_solicitacao, enti_nb_id, nome, email, funcao, ordem, salvar_documento_funcionario, token, status)
		VALUES
			(?, NULLIF(?,0), ?, ?, ?, 1, ?, ?, 'pendente')"
	);
	if(!$stmtA){
		return ["ok" => false, "error" => "Falha ao preparar assinante."];
	}
	mysqli_stmt_bind_param($stmtA, "iisssss", $idSolicitacao, $entiNbId, $nome, $email, $funcao, $salvarDoc, $tokenAssinante);
	if(!mysqli_stmt_execute($stmtA)){
		mysqli_stmt_close($stmtA);
		return ["ok" => false, "error" => "Falha ao criar assinante."];
	}
	mysqli_stmt_close($stmtA);

	return [
		"ok" => true,
		"id_solicitacao" => $idSolicitacao,
		"id_documento" => $idDocumento,
		"token_assinante" => $tokenAssinante,
		"email" => $email,
		"nome" => $nome,
		"enti_nb_id" => $entiNbId
	];
}

function assinatura_integracao_enviarDocumentoParaAssinatura(
	mysqli $conn,
	int $entiNbId,
	string $arquivoOrigem,
	array $opts = []
): array {
	$entiNbId = intval($entiNbId);
	if($entiNbId <= 0){
		return ["ok" => false, "error" => "Funcionário inválido."];
	}

	$absOrigem = assinatura_integracao_resolverArquivoAbs($arquivoOrigem);
	if($absOrigem === "" || !assinatura_integracao_validarArquivoPdfAbs($absOrigem)){
		return ["ok" => false, "error" => "Arquivo PDF não encontrado."];
	}

	$baseRoot = assinatura_integracao_baseRoot();
	$rpOrig = realpath($absOrigem);
	if($rpOrig){
		$rpOrig = str_replace("\\", "/", $rpOrig);
		if(strpos($rpOrig, $baseRoot . "/") !== 0){
			return ["ok" => false, "error" => "Arquivo fora do diretório permitido."];
		}
	}

	$entidade = null;
	$stmtE = mysqli_prepare($conn, "SELECT enti_nb_id, enti_tx_nome, enti_tx_email FROM entidade WHERE enti_nb_id = ? LIMIT 1");
	if($stmtE){
		mysqli_stmt_bind_param($stmtE, "i", $entiNbId);
		mysqli_stmt_execute($stmtE);
		$entidade = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtE));
		mysqli_stmt_close($stmtE);
	}
	if(!is_array($entidade)){
		return ["ok" => false, "error" => "Funcionário não encontrado."];
	}

	$nomeOriginal = trim(strval($opts["nome_arquivo_original"] ?? ""));
	if($nomeOriginal === ""){
		$nomeOriginal = basename($absOrigem);
	}
	$nomeOriginal = assinatura_integracao_sanitizarNomeArquivo($nomeOriginal);

	$assinaturaDir = assinatura_integracao_baseAssinatura();
	$destDir = $assinaturaDir . "/uploads/integracao/";
	if(!is_dir($destDir)){
		@mkdir($destDir, 0777, true);
	}

	$prefix = date("YmdHis") . "_" . bin2hex(random_bytes(4)) . "_";
	$destFileName = $prefix . $nomeOriginal;
	$destAbs = str_replace("\\", "/", rtrim($destDir, "/")) . "/" . $destFileName;
	if(!@copy($absOrigem, $destAbs)){
		return ["ok" => false, "error" => "Falha ao copiar arquivo para assinatura."];
	}
	$destRel = "uploads/integracao/" . $destFileName;

	if(!empty($opts["apagar_origem"])){
		@unlink($absOrigem);
	}

	assinatura_integracao_ensureTables($conn);
	assinatura_integracao_carregarEmail();

	$criada = assinatura_integracao_criarSolicitacaoUnicoAssinante($conn, $entidade, $destRel, $nomeOriginal, $opts);
	if(empty($criada["ok"])){
		@unlink($destAbs);
		return $criada;
	}

	if(function_exists("enviarEmailProximo")){
		enviarEmailProximo(
			strval($criada["email"] ?? ""),
			strval($criada["nome"] ?? ""),
			strval($criada["token_assinante"] ?? ""),
			$nomeOriginal,
			strval($criada["id_documento"] ?? ""),
			strval($opts["funcao"] ?? "Funcionário"),
			$destAbs,
			intval($criada["enti_nb_id"] ?? 0)
		);
	}

	$criada["caminho_arquivo"] = $destRel;
	$criada["caminho_arquivo_abs"] = $destAbs;
	return $criada;
}

