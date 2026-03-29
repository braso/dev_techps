<?php
$interno = true;
include __DIR__ . "/../conecta.php";

function redirectErro(string $msg) {
	header("Location: documentos_em_lote.php?status=error&message=" . urlencode($msg));
	exit;
}

function redirectOk(string $msg) {
	header("Location: documentos_em_lote.php?status=success&message=" . urlencode($msg));
	exit;
}

if(($_SERVER["REQUEST_METHOD"] ?? "") !== "POST"){
	redirectErro("Método inválido.");
}

$tipoId = intval($_POST["tipo_documento"] ?? 0);
$visivel = $_POST["visivel"] ?? "nao";
$visivel = in_array($visivel, ["sim","nao"], true) ? $visivel : "nao";
$nome = trim(strval($_POST["nome"] ?? ""));
$descricao = trim(strval($_POST["descricao"] ?? ""));
$dataVencimento = $_POST["data_vencimento"] ?? null;
$modo = $_POST["modo"] ?? "unico";
$statusFuncionario = $_POST["status_funcionario"] ?? "ativo";
$enviarAssinatura = ($_POST["enviar_assinatura"] ?? "nao") === "sim";
$funcaoAssinatura = trim(strval($_POST["funcao_assinatura"] ?? "Funcionário"));
if($funcaoAssinatura === ""){
	$funcaoAssinatura = "Funcionário";
}

if($tipoId <= 0 || $nome === ""){
	redirectErro("Campos obrigatórios não preenchidos.");
}

$usuarioCadastro = intval($_SESSION["user_nb_id"] ?? 0);
if($usuarioCadastro <= 0){
	redirectErro("Sessão inválida.");
}

$tipo = mysqli_fetch_assoc(query(
	"SELECT tipo_nb_id FROM tipos_documentos WHERE tipo_nb_id = ? AND tipo_tx_status = 'ativo' LIMIT 1",
	"i",
	[$tipoId]
));
if(empty($tipo)){
	redirectErro("Tipo de documento não encontrado.");
}

$whereEntidade = "enti_nb_id > 0";
$types = "";
$vars = [];
if($statusFuncionario === "ativo" || $statusFuncionario === "inativo"){
	$whereEntidade .= " AND enti_tx_status = ?";
	$types .= "s";
	$vars[] = $statusFuncionario;
}

$entidades = mysqli_fetch_all(query(
	"SELECT
		enti_nb_id,
		enti_tx_nome,
		enti_tx_email,
		REPLACE(REPLACE(REPLACE(enti_tx_cpf, '.', ''), '-', ''), ' ', '') AS cpf
	FROM entidade
	WHERE {$whereEntidade}
	ORDER BY enti_nb_id ASC",
	$types,
	$vars
), MYSQLI_ASSOC);

if(empty($entidades)){
	redirectErro("Nenhum funcionário encontrado para o filtro selecionado.");
}

$idToEntidade = [];
$cpfToId = [];
foreach($entidades as $e){
	$id = intval($e["enti_nb_id"] ?? 0);
	if($id > 0){
		$idToEntidade[$id] = $e;
	}
	$cpf = preg_replace('/\D+/', '', strval($e["cpf"] ?? ""));
	if($cpf !== ""){
		$cpfToId[$cpf] = intval($e["enti_nb_id"]);
	}
}

$formatos = [
	"application/pdf" => "pdf"
];

function sanitizeFileName(string $name): string {
	$name = basename($name);
	$name = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '_', $name);
	return $name === "" ? ("arquivo_" . time() . ".pdf") : $name;
}

function ensureUniquePath(string $path): string {
	if(!file_exists($path)){
		return $path;
	}
	$info = pathinfo($path);
	$dir = $info["dirname"] ?? ".";
	$base = $info["filename"] ?? "arquivo";
	$ext = isset($info["extension"]) ? ".".$info["extension"] : "";
	return rtrim($dir, "/\\") . "/" . $base . "_" . time() . $ext;
}

function inserirDocumentoFuncionario(array $data): bool {
	$sql =
		"INSERT INTO documento_funcionario
			(docu_nb_entidade, docu_tx_nome, docu_tx_descricao, docu_tx_dataCadastro, docu_tx_dataVencimento, docu_tx_tipo, docu_nb_sbgrupo, docu_tx_usuarioCadastro, docu_tx_assinado, docu_tx_visivel, docu_tx_caminho)
		VALUES
			(?, ?, ?, ?, ?, ?, ?, ?, 'nao', ?, ?)";

	$sbgrupo = intval($data["docu_nb_sbgrupo"] ?? 0);

	$res = query(
		$sql,
		"issssiiiss",
		[
			intval($data["docu_nb_entidade"]),
			strval($data["docu_tx_nome"]),
			strval($data["docu_tx_descricao"]),
			strval($data["docu_tx_dataCadastro"]),
			$data["docu_tx_dataVencimento"],
			intval($data["docu_tx_tipo"]),
			$sbgrupo,
			intval($data["docu_tx_usuarioCadastro"]),
			strval($data["docu_tx_visivel"]),
			strval($data["docu_tx_caminho"])
		]
	);

	return (bool) $res;
}

function ensureAssinaturaTables($conn): void {
	$checkTable = "SHOW TABLES LIKE 'solicitacoes_assinatura'";
	$resultTable = mysqli_query($conn, $checkTable);
	if(mysqli_num_rows($resultTable) == 0) {
		$sqlCreateTable = "CREATE TABLE IF NOT EXISTS solicitacoes_assinatura (
			id INT AUTO_INCREMENT PRIMARY KEY,
			token VARCHAR(64) NOT NULL UNIQUE,
			email VARCHAR(255) NOT NULL,
			nome VARCHAR(255),
			caminho_arquivo VARCHAR(255) NOT NULL,
			nome_arquivo_original VARCHAR(255),
			data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
			expires_at DATETIME NULL,
			status ENUM('pendente', 'em_progresso', 'concluido', 'assinado') DEFAULT 'pendente',
			id_documento VARCHAR(100),
			data_assinatura DATETIME NULL
		)";
		mysqli_query($conn, $sqlCreateTable);
	} else {
		$cols = ['nome', 'nome_arquivo_original'];
		foreach ($cols as $col) {
			$check = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE '$col'");
			if(mysqli_num_rows($check) == 0) {
				mysqli_query($conn, "ALTER TABLE solicitacoes_assinatura ADD COLUMN $col VARCHAR(255)");
			}
		}

		$checkExp = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'expires_at'");
		if($checkExp && mysqli_num_rows($checkExp) == 0){
			mysqli_query($conn, "ALTER TABLE solicitacoes_assinatura ADD COLUMN expires_at DATETIME NULL");
		}

		$hasCreatedAt = false;
		$chkCreatedAt = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'created_at'");
		if($chkCreatedAt && mysqli_num_rows($chkCreatedAt) > 0){
			$hasCreatedAt = true;
		}
		$baseExpr = $hasCreatedAt
			? "COALESCE(NULLIF(created_at,'0000-00-00 00:00:00'), NULLIF(data_solicitacao,'0000-00-00 00:00:00'))"
			: "NULLIF(data_solicitacao,'0000-00-00 00:00:00')";
		@mysqli_query($conn, "UPDATE solicitacoes_assinatura SET expires_at = DATE_ADD({$baseExpr}, INTERVAL 24 HOUR) WHERE (expires_at IS NULL OR expires_at = '0000-00-00 00:00:00') AND {$baseExpr} IS NOT NULL");
	}

	$checkTableAssinantes = "SHOW TABLES LIKE 'assinantes'";
	$resultTableAssinantes = mysqli_query($conn, $checkTableAssinantes);
	if(mysqli_num_rows($resultTableAssinantes) == 0) {
		$sqlCreateTableAssinantes = "CREATE TABLE IF NOT EXISTS assinantes (
			id INT AUTO_INCREMENT PRIMARY KEY,
			id_solicitacao INT NOT NULL,
			nome VARCHAR(255) NOT NULL,
			email VARCHAR(255) NOT NULL,
			cpf VARCHAR(20),
			funcao VARCHAR(100),
			ordem INT NOT NULL DEFAULT 1,
			token VARCHAR(64) NOT NULL UNIQUE,
			status ENUM('pendente', 'assinado') DEFAULT 'pendente',
			data_assinatura DATETIME NULL,
			ip VARCHAR(45),
			metadados TEXT,
			INDEX (id_solicitacao),
			INDEX (token)
		)";
		mysqli_query($conn, $sqlCreateTableAssinantes);
	}
}

function criarSolicitacaoAssinatura($conn, array $entidade, string $caminhoArquivo, string $nomeArquivoOriginal, string $funcao): array {
	$email = trim(strval($entidade["enti_tx_email"] ?? ""));
	$nome = trim(strval($entidade["enti_tx_nome"] ?? ""));
	if($email === ""){
		return ["ok" => false, "error" => "Funcionário sem e-mail."];
	}

	$tokenMestre = bin2hex(random_bytes(32));
	$tokenAssinante = bin2hex(random_bytes(32));
	$idDocumento = "DOC-" . date("YmdHis") . "-" . uniqid();

	$sql = "INSERT INTO solicitacoes_assinatura (token, email, nome, caminho_arquivo, nome_arquivo_original, id_documento, expires_at, status)
		VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 24 HOUR), 'pendente')";
	$stmt = mysqli_prepare($conn, $sql);
	if(!$stmt){
		return ["ok" => false, "error" => "Falha ao preparar solicitação."];
	}
	mysqli_stmt_bind_param($stmt, "ssssss", $tokenMestre, $email, $nome, $caminhoArquivo, $nomeArquivoOriginal, $idDocumento);
	if(!mysqli_stmt_execute($stmt)){
		return ["ok" => false, "error" => "Falha ao criar solicitação."];
	}
	$idSolicitacao = mysqli_insert_id($conn);

	$sqlAssinante = "INSERT INTO assinantes (id_solicitacao, nome, email, funcao, ordem, token, status)
		VALUES (?, ?, ?, ?, 1, ?, 'pendente')";
	$stmtA = mysqli_prepare($conn, $sqlAssinante);
	if(!$stmtA){
		return ["ok" => false, "error" => "Falha ao preparar assinante."];
	}
	mysqli_stmt_bind_param($stmtA, "issss", $idSolicitacao, $nome, $email, $funcao, $tokenAssinante);
	if(!mysqli_stmt_execute($stmtA)){
		return ["ok" => false, "error" => "Falha ao criar assinante."];
	}

	return [
		"ok" => true,
		"id_solicitacao" => $idSolicitacao,
		"id_documento" => $idDocumento,
		"token_assinante" => $tokenAssinante,
		"email" => $email,
		"nome" => $nome
	];
}

if($enviarAssinatura){
	include __DIR__ . "/email_config.php";
	require __DIR__ . "/../../PHPMailer/src/Exception.php";
	require __DIR__ . "/../../PHPMailer/src/PHPMailer.php";
	require __DIR__ . "/../../PHPMailer/src/SMTP.php";
	include __DIR__ . "/email_helper.php";
	ensureAssinaturaTables($conn);
}

query("START TRANSACTION;");
$inseridos = 0;
$erros = 0;
$assinaturasCriadas = 0;
$assinaturasErros = 0;

if($modo === "por_funcionario"){
	$arquivos = $_FILES["arquivos"] ?? null;
	if(!$arquivos || !isset($arquivos["name"]) || !is_array($arquivos["name"]) || count($arquivos["name"]) === 0){
		query("ROLLBACK;");
		redirectErro("Selecione os PDFs.");
	}

	for($i = 0; $i < count($arquivos["name"]); $i++){
		if(($arquivos["error"][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK){
			$erros++;
			continue;
		}

		$tmp = $arquivos["tmp_name"][$i] ?? "";
		$original = strval($arquivos["name"][$i] ?? "");
		if($tmp === "" || $original === ""){
			$erros++;
			continue;
		}

		$tipoMime = mime_content_type($tmp);
		if(!array_key_exists($tipoMime, $formatos)){
			$erros++;
			continue;
		}

		$digits = preg_replace('/\D+/', '', pathinfo($original, PATHINFO_FILENAME));
		$entidadeId = 0;
		if(strlen($digits) === 11 && isset($cpfToId[$digits])){
			$entidadeId = $cpfToId[$digits];
		} elseif($digits !== "") {
			$entidadeId = intval($digits);
		}

		if($entidadeId <= 0){
			$erros++;
			continue;
		}
		if(!isset($idToEntidade[$entidadeId])){
			$erros++;
			continue;
		}

		$pasta = "arquivos/Funcionarios/" . $entidadeId . "/";
		if(!is_dir($pasta)){
			mkdir($pasta, 0777, true);
		}

		$nomeSeguro = sanitizeFileName($original);
		$dest = ensureUniquePath($pasta . $nomeSeguro);

		if(!move_uploaded_file($tmp, $dest)){
			$erros++;
			continue;
		}

		$ok = inserirDocumentoFuncionario([
			"docu_nb_entidade" => $entidadeId,
			"docu_tx_nome" => $nome,
			"docu_tx_descricao" => $descricao,
			"docu_tx_dataCadastro" => date("Y-m-d H:i:s"),
			"docu_tx_dataVencimento" => ($dataVencimento !== "" ? $dataVencimento : null),
			"docu_tx_tipo" => $tipoId,
			"docu_nb_sbgrupo" => 0,
			"docu_tx_usuarioCadastro" => $usuarioCadastro,
			"docu_tx_visivel" => $visivel,
			"docu_tx_caminho" => $dest
		]);

		if(!$ok){
			@unlink($dest);
			$erros++;
			continue;
		}

		$inseridos++;

		if($enviarAssinatura){
			$assinatura = criarSolicitacaoAssinatura($conn, $idToEntidade[$entidadeId], $dest, $original, $funcaoAssinatura);
			if(!($assinatura["ok"] ?? false)){
				$assinaturasErros++;
			} else {
				$assinaturasCriadas++;
				if(function_exists("enviarEmailProximo")){
					enviarEmailProximo(
						$assinatura["email"],
						$assinatura["nome"],
						$assinatura["token_assinante"],
						$original,
						$assinatura["id_documento"],
						$funcaoAssinatura,
						$dest
					);
				}
			}
		}
	}

	if($inseridos <= 0){
		query("ROLLBACK;");
		redirectErro("Nenhum documento foi inserido. Verifique os nomes dos arquivos (ID/CPF no início).");
	}

	query("COMMIT;");
	$msg = "Inseridos: {$inseridos}. Erros: {$erros}.";
	if($enviarAssinatura){
		$msg .= " Assinaturas criadas: {$assinaturasCriadas}. Erros assinatura: {$assinaturasErros}.";
	}
	redirectOk($msg);
}

$arquivoUnico = $_FILES["arquivo_unico"] ?? null;
if(!$arquivoUnico || ($arquivoUnico["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK){
	query("ROLLBACK;");
	redirectErro("Selecione o PDF único.");
}

$tipoMime = mime_content_type($arquivoUnico["tmp_name"]);
if(!array_key_exists($tipoMime, $formatos)){
	query("ROLLBACK;");
	redirectErro("Apenas PDF é permitido.");
}

$conteudo = file_get_contents($arquivoUnico["tmp_name"]);
if($conteudo === false){
	query("ROLLBACK;");
	redirectErro("Falha ao ler o arquivo enviado.");
}

$nomeSeguroBase = sanitizeFileName($arquivoUnico["name"] ?? "documento.pdf");
$agora = date("Y-m-d H:i:s");
$dataV = ($dataVencimento !== "" ? $dataVencimento : null);

foreach($entidades as $e){
	$entidadeId = intval($e["enti_nb_id"]);
	if($entidadeId <= 0){
		$erros++;
		continue;
	}

	$pasta = "arquivos/Funcionarios/" . $entidadeId . "/";
	if(!is_dir($pasta)){
		mkdir($pasta, 0777, true);
	}

	$dest = ensureUniquePath($pasta . $nomeSeguroBase);
	$okWrite = file_put_contents($dest, $conteudo);
	if($okWrite === false){
		$erros++;
		continue;
	}

	$ok = inserirDocumentoFuncionario([
		"docu_nb_entidade" => $entidadeId,
		"docu_tx_nome" => $nome,
		"docu_tx_descricao" => $descricao,
		"docu_tx_dataCadastro" => $agora,
		"docu_tx_dataVencimento" => $dataV,
		"docu_tx_tipo" => $tipoId,
		"docu_nb_sbgrupo" => 0,
		"docu_tx_usuarioCadastro" => $usuarioCadastro,
		"docu_tx_visivel" => $visivel,
		"docu_tx_caminho" => $dest
	]);

	if(!$ok){
		@unlink($dest);
		$erros++;
		continue;
	}

	$inseridos++;

	if($enviarAssinatura){
		$nomeArquivoOriginal = basename($dest);
		$assinatura = criarSolicitacaoAssinatura($conn, $e, $dest, $nomeArquivoOriginal, $funcaoAssinatura);
		if(!($assinatura["ok"] ?? false)){
			$assinaturasErros++;
		} else {
			$assinaturasCriadas++;
			if(function_exists("enviarEmailProximo")){
				enviarEmailProximo(
					$assinatura["email"],
					$assinatura["nome"],
					$assinatura["token_assinante"],
					$nomeArquivoOriginal,
					$assinatura["id_documento"],
					$funcaoAssinatura,
					$dest
				);
			}
		}
	}
}

if($inseridos <= 0){
	query("ROLLBACK;");
	redirectErro("Nenhum documento foi inserido.");
}

query("COMMIT;");
$msg = "Inseridos: {$inseridos}. Erros: {$erros}.";
if($enviarAssinatura){
	$msg .= " Assinaturas criadas: {$assinaturasCriadas}. Erros assinatura: {$assinaturasErros}.";
}
redirectOk($msg);

