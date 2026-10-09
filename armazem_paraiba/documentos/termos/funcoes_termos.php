<?php

if(!defined("TERMOS_DIR")){
	define("TERMOS_DIR", __DIR__);
}
if(!defined("TERMOS_MODULO_DIR")){
	define("TERMOS_MODULO_DIR", dirname(__DIR__, 2));
}

include_once TERMOS_MODULO_DIR . "/utils/celular_opcoes.php";

function termos_ensure_tables($conn = null): void {
	global $conn;
	if(!($conn instanceof mysqli)){
		return;
	}

	mysqli_query($conn, "CREATE TABLE IF NOT EXISTS modelo_termo (
		mode_nb_id INT AUTO_INCREMENT PRIMARY KEY,
		mode_tx_nome VARCHAR(255) NOT NULL,
		mode_nb_tipo_doc INT NOT NULL,
		mode_tx_conteudo LONGTEXT NULL,
		mode_tx_denominacao VARCHAR(120) NULL,
		mode_tx_cidade_assinatura VARCHAR(150) NULL,
		mode_tx_status ENUM('ativo','inativo') DEFAULT 'ativo',
		mode_tx_notificacao ENUM('sim','nao') NOT NULL DEFAULT 'sim',
		mode_nb_ativo INT NULL,
		mode_nb_userCadastro INT NULL,
		mode_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
		mode_nb_userAtualiza INT NULL,
		mode_tx_dataAtualiza DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		KEY idx_tipo (mode_nb_tipo_doc)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	$colsModelo = [
		"mode_tx_notificacao" => "ALTER TABLE modelo_termo ADD COLUMN mode_tx_notificacao ENUM('sim','nao') NOT NULL DEFAULT 'sim' AFTER mode_tx_status",
		"mode_nb_ativo" => "ALTER TABLE modelo_termo ADD COLUMN mode_nb_ativo INT NULL AFTER mode_tx_notificacao"
	];
	foreach($colsModelo as $colModelo => $ddlModelo){
		$checkModelo = mysqli_query($conn, "SHOW COLUMNS FROM modelo_termo LIKE '{$colModelo}'");
		if($checkModelo && mysqli_num_rows($checkModelo) === 0){
			@mysqli_query($conn, $ddlModelo);
		}
	}

	mysqli_query($conn, "CREATE TABLE IF NOT EXISTS modelo_termo_assinante (
		moas_nb_id INT AUTO_INCREMENT PRIMARY KEY,
		moas_nb_modelo INT NOT NULL,
		moas_tx_tipo ENUM('funcionario','empresa','funcionario_especifico','email_fixo') NOT NULL DEFAULT 'funcionario',
		moas_tx_funcao VARCHAR(120) NOT NULL DEFAULT 'Signatário',
		moas_nb_ordem INT NOT NULL DEFAULT 1,
		moas_nb_entidade INT NULL,
		moas_tx_nome VARCHAR(255) NULL,
		moas_tx_email VARCHAR(255) NULL,
		KEY idx_modelo (moas_nb_modelo)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	mysqli_query($conn, "CREATE TABLE IF NOT EXISTS termo_gerado (
		terg_nb_id INT AUTO_INCREMENT PRIMARY KEY,
		terg_nb_modelo INT NOT NULL,
		terg_nb_entidade INT NOT NULL,
		terg_nb_ativo INT NULL,
		terg_nb_tipo_doc INT NULL,
		terg_tx_status ENUM('gerado','notificado','visualizado','aguardando_assinatura','assinado','erro','cancelado') DEFAULT 'gerado',
		terg_tx_caminho VARCHAR(500) NULL,
		terg_nb_solicitacao_assinatura INT NULL,
		terg_nb_assinante INT NULL,
		terg_tx_id_documento VARCHAR(100) NULL,
		terg_nb_documento_funcionario INT NULL,
		terg_tx_detalhe TEXT NULL,
		terg_nb_user_geracao INT NULL,
		terg_dt_geracao DATETIME DEFAULT CURRENT_TIMESTAMP,
		terg_dt_data_notificacao DATETIME NULL,
		terg_dt_data_visualizacao DATETIME NULL,
		terg_tx_ip_visualizacao VARCHAR(45) NULL,
		terg_tx_user_agent_visualizacao TEXT NULL,
		terg_tx_hash_visualizacao VARCHAR(64) NULL,
		terg_dt_data_assinatura DATETIME NULL,
		KEY idx_modelo (terg_nb_modelo),
		KEY idx_entidade (terg_nb_entidade),
		KEY idx_solicitacao (terg_nb_solicitacao_assinatura),
		KEY idx_assinante (terg_nb_assinante)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	$colStatusAtual = mysqli_query($conn, "SHOW COLUMNS FROM termo_gerado LIKE 'terg_tx_status'");
	if($colStatusAtual && ($rowStatus = mysqli_fetch_assoc($colStatusAtual))){
		$tipoStatus = strtolower(strval($rowStatus["Type"] ?? ""));
		if(strpos($tipoStatus, "enum") === 0 && (strpos($tipoStatus, "'notificado'") === false || strpos($tipoStatus, "'visualizado'") === false)){
			$novoEnum = "ENUM('gerado','notificado','visualizado','aguardando_assinatura','assinado','erro','cancelado')";
			$nullStatus = (strtoupper(strval($rowStatus["Null"] ?? "")) === "YES") ? "NULL" : "NOT NULL";
			$defStatus = isset($rowStatus["Default"]) && $rowStatus["Default"] !== null ? " DEFAULT '" . addslashes(strval($rowStatus["Default"])) . "'" : "";
			@mysqli_query($conn, "ALTER TABLE termo_gerado MODIFY COLUMN terg_tx_status {$novoEnum} {$nullStatus}{$defStatus}");
		}
	}

	$colsGerado = [
		"terg_nb_assinante" => "ALTER TABLE termo_gerado ADD COLUMN terg_nb_assinante INT NULL AFTER terg_nb_solicitacao_assinatura",
		"terg_nb_ativo" => "ALTER TABLE termo_gerado ADD COLUMN terg_nb_ativo INT NULL AFTER terg_nb_entidade",
		"terg_dt_data_notificacao" => "ALTER TABLE termo_gerado ADD COLUMN terg_dt_data_notificacao DATETIME NULL AFTER terg_dt_geracao",
		"terg_dt_data_visualizacao" => "ALTER TABLE termo_gerado ADD COLUMN terg_dt_data_visualizacao DATETIME NULL AFTER terg_dt_data_notificacao",
		"terg_tx_ip_visualizacao" => "ALTER TABLE termo_gerado ADD COLUMN terg_tx_ip_visualizacao VARCHAR(45) NULL AFTER terg_dt_data_visualizacao",
		"terg_tx_user_agent_visualizacao" => "ALTER TABLE termo_gerado ADD COLUMN terg_tx_user_agent_visualizacao TEXT NULL AFTER terg_tx_ip_visualizacao",
		"terg_tx_hash_visualizacao" => "ALTER TABLE termo_gerado ADD COLUMN terg_tx_hash_visualizacao VARCHAR(64) NULL AFTER terg_tx_user_agent_visualizacao"
	];
	foreach($colsGerado as $colGerado => $ddlGerado){
		$checkGerado = mysqli_query($conn, "SHOW COLUMNS FROM termo_gerado LIKE '{$colGerado}'");
		if($checkGerado && mysqli_num_rows($checkGerado) === 0){
			@mysqli_query($conn, $ddlGerado);
		}
	}

	mysqli_query($conn, "CREATE TABLE IF NOT EXISTS termo_visualizacao (
		tevi_nb_id INT AUTO_INCREMENT PRIMARY KEY,
		tevi_nb_termo INT NOT NULL,
		tevi_nb_modelo INT NULL,
		tevi_nb_entidade INT NULL,
		tevi_nb_solicitacao INT NULL,
		tevi_nb_assinante INT NULL,
		tevi_tx_evento ENUM('abertura','confirmacao') NOT NULL DEFAULT 'abertura',
		tevi_tx_ip VARCHAR(45) NULL,
		tevi_tx_ip_forwarded VARCHAR(255) NULL,
		tevi_tx_user_agent TEXT NULL,
		tevi_tx_accept_language VARCHAR(120) NULL,
		tevi_tx_host VARCHAR(255) NULL,
		tevi_tx_referer VARCHAR(500) NULL,
		tevi_tx_plataforma VARCHAR(40) NULL,
		tevi_nb_latitude VARCHAR(50) NULL,
		tevi_nb_longitude VARCHAR(50) NULL,
		tevi_nb_geo_precisao VARCHAR(50) NULL,
		tevi_tx_hash VARCHAR(64) NULL,
		tevi_nb_user INT NULL,
		tevi_tx_login VARCHAR(255) NULL,
		tevi_dt_data DATETIME DEFAULT CURRENT_TIMESTAMP,
		KEY idx_termo (tevi_nb_termo),
		KEY idx_entidade (tevi_nb_entidade),
		KEY idx_solicitacao (tevi_nb_solicitacao)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	termos_garantir_tabela_notificacoes($conn);
}

function termos_garantir_tabela_notificacoes($conn = null): void {
	global $conn;
	if(!($conn instanceof mysqli)){
		return;
	}
	mysqli_query($conn, "CREATE TABLE IF NOT EXISTS notificacoes (
		notf_nb_id INT AUTO_INCREMENT PRIMARY KEY,
		notf_nb_entidade INT NOT NULL,
		notf_tx_titulo VARCHAR(255) NOT NULL,
		notf_tx_mensagem TEXT NULL,
		notf_tx_link VARCHAR(500) NULL,
		notf_tx_tipo VARCHAR(30) NOT NULL DEFAULT 'info',
		notf_tx_status VARCHAR(20) NOT NULL DEFAULT 'nao_lida',
		notf_tx_dataCadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
		notf_tx_dataLeitura DATETIME NULL,
		KEY idx_notf_entidade (notf_nb_entidade, notf_tx_status)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function termos_log_dir(): string {
	return rtrim(str_replace("\\", "/", TERMOS_DIR), "/") . "/logs";
}

function termos_log_file_atual(): string {
	return termos_log_dir() . "/termos_" . date("Y-m-d") . ".txt";
}

function termos_log(string $evento, string $detalhe = "", array $extra = []): void {
	$dir = termos_log_dir();
	if(!is_dir($dir)){
		@mkdir($dir, 0777, true);
	}
	if(!is_dir($dir)){
		return;
	}
	$usuario = intval($_SESSION["user_nb_id"] ?? 0);
	$login = trim(strval($_SESSION["user_tx_login"] ?? ""));
	$ip = trim(strval($_SERVER["REMOTE_ADDR"] ?? ""));
	$detalhe = preg_replace('/[\r\n]+/', " ", trim(strval($detalhe)));
	$extraStr = "";
	foreach($extra as $k => $v){
		$extraStr .= " " . $k . "=" . preg_replace('/[\r\n\s]+/', "_", strval($v));
	}
	$linha = sprintf(
		"[%s] user=%s(%d) ip=%s evento=%s detalhe=%s%s\n",
		date("Y-m-d H:i:s"),
		$login,
		$usuario,
		$ip,
		$evento,
		$detalhe,
		$extraStr
	);
	@file_put_contents(termos_log_file_atual(), $linha, FILE_APPEND | LOCK_EX);
	termos_log_limpar(30);
}

function termos_log_limpar(int $dias = 30): void {
	if($dias <= 0){
		return;
	}
	$dir = termos_log_dir();
	if(!is_dir($dir)){
		return;
	}
	$corte = strtotime("-" . intval($dias) . " days");
	$arquivos = glob($dir . "/termos_*.txt");
	if(!is_array($arquivos)){
		return;
	}
	foreach($arquivos as $arquivo){
		if(!preg_match('/termos_(\d{4}-\d{2}-\d{2})\.txt$/', basename($arquivo), $m)){
			continue;
		}
		$ts = strtotime($m[1]);
		if($ts !== false && $ts < $corte){
			@unlink($arquivo);
		}
	}
}

function termos_h(string $v): string {
	return htmlspecialchars(strval($v), ENT_QUOTES, "UTF-8");
}

function termos_sanitizar_html(string $html): string {
	$perm = "<p><br><b><strong><i><em><u><s><strike><sub><sup><ul><ol><li><table><thead><tbody><tr><td><th><h1><h2><h3><h4><h5><h6><div><span><blockquote><pre><a><hr><img>";
	$html = strip_tags($html, $perm);
	$html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
	$html = preg_replace('#<(script|style|iframe|object|embed|link|meta|form|input|button|select|textarea)[^>]*>.*?</\1>#is', '', $html);
	$html = preg_replace('#</?(script|style|iframe|object|embed|link|meta|form|input|button|select|textarea)[^>]*>#i', '', $html);
	$html = preg_replace('/javascript\s*:/i', '', $html);
	$html = preg_replace('/\son[a-z]+\s*=/i', '', $html);
	return strval($html);
}

function termos_formatar_cpf($v): string {
	$v = preg_replace('/\D+/', '', strval($v));
	if(strlen($v) !== 11){
		return strval($v);
	}
	return substr($v, 0, 3) . "." . substr($v, 3, 3) . "." . substr($v, 6, 3) . "-" . substr($v, 9, 2);
}

function termos_formatar_cnpj($v): string {
	$v = preg_replace('/\D+/', '', strval($v));
	if(strlen($v) !== 14){
		return strval($v);
	}
	return substr($v, 0, 2) . "." . substr($v, 2, 3) . "." . substr($v, 5, 3) . "/" . substr($v, 8, 4) . "-" . substr($v, 12, 2);
}

function termos_formatar_data($v): string {
	$v = trim(strval($v));
	if($v === "" || $v === "0000-00-00" || $v === "0000-00-00 00:00:00"){
		return "";
	}
	$ts = strtotime($v);
	if(!$ts){
		return $v;
	}
	return date("d/m/Y", $ts);
}

function termos_data_extenso($v): string {
	$v = trim(strval($v));
	if($v === "" || $v === "0000-00-00"){
		return "";
	}
	$ts = strtotime($v);
	if(!$ts){
		return "";
	}
	$meses = [1 => "janeiro", 2 => "fevereiro", 3 => "março", 4 => "abril", 5 => "maio", 6 => "junho", 7 => "julho", 8 => "agosto", 9 => "setembro", 10 => "outubro", 11 => "novembro", 12 => "dezembro"];
	$d = getdate($ts);
	return $d["mday"] . " de " . ($meses[$d["mon"]] ?? $d["mon"]) . " de " . $d["year"];
}

function termos_carregar_modelo(int $id): array {
	if($id <= 0){
		return [];
	}
	$res = query("SELECT * FROM modelo_termo WHERE mode_nb_id = ? LIMIT 1", "i", [$id]);
	return ($res instanceof mysqli_result) ? (mysqli_fetch_assoc($res) ?: []) : [];
}

function termos_carregar_tipo(int $id): array {
	if($id <= 0){
		return [];
	}
	$res = query("SELECT * FROM tipos_documentos WHERE tipo_nb_id = ? LIMIT 1", "i", [$id]);
	return ($res instanceof mysqli_result) ? (mysqli_fetch_assoc($res) ?: []) : [];
}

function termos_tipo_requer_assinatura(array $tipo): bool {
	return strtolower(trim(strval($tipo["tipo_tx_assinatura"] ?? "nao"))) === "sim";
}

function termos_tipo_requer_ativo(array $tipo): bool {
	return strtolower(trim(strval($tipo["tipo_tx_ativo"] ?? "nao"))) === "sim";
}

function termos_tabela_ativo_existe(): bool {
	global $conn;
	if($conn instanceof mysqli && function_exists("bancoTabelaExiste")){
		return bancoTabelaExiste($conn, "celular");
	}
	return false;
}

function termos_rotulo_ativo(array $a): string {
	$partes = [];
	$nome = trim(strval($a["celu_tx_nome"] ?? ""));
	if($nome !== ""){
		$partes[] = $nome;
	}
	$marca = trim(strval($a["celu_tx_marcaModelo"] ?? ""));
	if($marca !== ""){
		$partes[] = $marca;
	}
	$imei = trim(strval($a["celu_tx_imei"] ?? ""));
	if($imei !== ""){
		$partes[] = "IMEI: " . $imei;
	}
	$txt = implode(" — ", $partes);
	if($txt === ""){
		$txt = "Equipamento #" . intval($a["celu_nb_id"] ?? 0);
	}
	$resp = trim(strval($a["responsavel"] ?? ""));
	if($resp !== ""){
		$txt .= " (" . $resp . ")";
	}
	return $txt;
}

function termos_dados_ativo(int $id): array {
	if($id <= 0 || !termos_tabela_ativo_existe()){
		return [];
	}
	$res = query(
		"SELECT c.*, e.enti_tx_nome AS responsavel
		 FROM celular c
		 LEFT JOIN entidade e ON e.enti_nb_id = c.celu_nb_entidade
		 WHERE c.celu_nb_id = ? LIMIT 1",
		"i",
		[$id]
	);
	return ($res instanceof mysqli_result) ? (mysqli_fetch_assoc($res) ?: []) : [];
}

function termos_listar_ativos(): array {
	if(!termos_tabela_ativo_existe()){
		return [];
	}
	$out = [];
	$res = query(
		"SELECT c.celu_nb_id, c.celu_tx_nome, c.celu_tx_imei, c.celu_tx_numero, c.celu_tx_operadora,
		        c.celu_tx_cimie, c.celu_tx_sistemaOperacional, c.celu_tx_marcaModelo,
		        c.celu_nb_entidade, e.enti_tx_nome AS responsavel
		 FROM celular c
		 LEFT JOIN entidade e ON e.enti_nb_id = c.celu_nb_entidade
		 ORDER BY c.celu_tx_nome ASC"
	);
	while($res && ($r = mysqli_fetch_assoc($res))){
		$r["rotulo"] = termos_rotulo_ativo($r);
		$out[] = $r;
	}
	return $out;
}

function termos_ativo_do_funcionario(int $entiId): int {
	if($entiId <= 0 || !termos_tabela_ativo_existe()){
		return 0;
	}
	$res = query(
		"SELECT celu_nb_id FROM celular WHERE celu_nb_entidade = ? ORDER BY celu_nb_id ASC LIMIT 1",
		"i",
		[$entiId]
	);
	if($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))){
		return intval($row["celu_nb_id"]);
	}
	return 0;
}

function termos_carregar_assinantes(int $modeloId): array {
	$out = [];
	if($modeloId <= 0){
		return $out;
	}
	$res = query(
		"SELECT * FROM modelo_termo_assinante WHERE moas_nb_modelo = ? ORDER BY moas_nb_ordem ASC, moas_nb_id ASC",
		"i",
		[$modeloId]
	);
	while($res && ($r = mysqli_fetch_assoc($res))){
		$out[] = $r;
	}
	return $out;
}

function termos_carregar_registro(int $id): array {
	if($id <= 0){
		return [];
	}
	$res = query("SELECT * FROM termo_gerado WHERE terg_nb_id = ? LIMIT 1", "i", [$id]);
	return ($res instanceof mysqli_result) ? (mysqli_fetch_assoc($res) ?: []) : [];
}

function termos_dados_funcionario(int $entiId): array {
	if($entiId <= 0){
		return [];
	}
	$res = query(
		"SELECT
			e.enti_nb_id, e.enti_tx_nome, e.enti_tx_cpf, e.enti_tx_rg, e.enti_tx_pis,
			e.enti_tx_ctpsNumero, e.enti_tx_ctpsSerie, e.enti_tx_ctpsUf,
			e.enti_tx_admissao, e.enti_tx_matricula, e.enti_tx_ocupacao, e.enti_tx_email,
			e.enti_tx_cnhRegistro, e.enti_tx_cnhCategoria, e.enti_tx_cnhValidade,
			e.enti_tx_status,
			em.empr_nb_id, em.empr_tx_nome, em.empr_tx_cnpj, em.empr_tx_contato, em.empr_tx_email AS empr_email, em.empr_tx_logo,
			cid.cida_tx_nome, cid.cida_tx_uf,
			op.oper_tx_nome AS cargo_nome
		FROM entidade e
		LEFT JOIN empresa em ON em.empr_nb_id = e.enti_nb_empresa
		LEFT JOIN cidade cid ON cid.cida_nb_id = em.empr_nb_cidade
		LEFT JOIN operacao op ON op.oper_nb_id = e.enti_tx_tipoOperacao
		WHERE e.enti_nb_id = ? LIMIT 1",
		"i",
		[$entiId]
	);
	return ($res instanceof mysqli_result) ? (mysqli_fetch_assoc($res) ?: []) : [];
}

function termos_nome_arquivo(array $modelo, array $dados): string {
	$nome = trim(strval($modelo["mode_tx_nome"] ?? "Termo")) . " - " . trim(strval($dados["enti_tx_nome"] ?? ""));
	$nome = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', "_", $nome);
	$nome = preg_replace('/\s+/', " ", $nome);
	$nome = trim($nome);
	if($nome === ""){
		$nome = "termo_" . time();
	}
	return $nome . ".pdf";
}

function termos_cidade_assinatura(array $dados, array $modelo): string {
	$cidade = trim(strval($modelo["mode_tx_cidade_assinatura"] ?? ""));
	if($cidade !== ""){
		return $cidade;
	}
	$nome = trim(strval($dados["cida_tx_nome"] ?? ""));
	$uf = trim(strval($dados["cida_tx_uf"] ?? ""));
	$out = $nome;
	if($out !== "" && $uf !== ""){
		$out .= "/" . $uf;
	}elseif($uf !== ""){
		$out = $uf;
	}
	return $out;
}

function termos_bloco_assinaturas(array $dados, array $assinantes): string {
	if(empty($assinantes)){
		return "";
	}
	usort($assinantes, function($a, $b){
		return intval($a["moas_nb_ordem"] ?? 1) <=> intval($b["moas_nb_ordem"] ?? 1);
	});
	$html = "<br><br>";
	foreach($assinantes as $as){
		$tipo = strval($as["moas_tx_tipo"] ?? "funcionario");
		$funcao = trim(strval($as["moas_tx_funcao"] ?? ""));
		$nome = "";
		$doc = "";
		if($tipo === "funcionario"){
			$nome = trim(strval($dados["enti_tx_nome"] ?? ""));
			$doc = termos_formatar_cpf($dados["enti_tx_cpf"] ?? "");
		}elseif($tipo === "empresa"){
			$nome = trim(strval($dados["empr_tx_nome"] ?? ""));
			$doc = termos_formatar_cnpj($dados["empr_tx_cnpj"] ?? "");
		}elseif($tipo === "funcionario_especifico"){
			$id = intval($as["moas_nb_entidade"] ?? 0);
			$d = $id > 0 ? termos_dados_funcionario($id) : [];
			$nome = trim(strval($d["enti_tx_nome"] ?? ""));
			$doc = termos_formatar_cpf($d["enti_tx_cpf"] ?? "");
		}else{
			$nome = trim(strval($as["moas_tx_nome"] ?? ""));
		}
		if($nome === "" && $doc === ""){
			continue;
		}
		$html .= "<br><br>_____________________________________<br>";
		$html .= "<b>" . termos_h($nome) . "</b>";
		if($doc !== ""){
			$html .= "<br>" . termos_h($doc);
		}
		if($funcao !== ""){
			$html .= "<br><i>" . termos_h($funcao) . "</i>";
		}
	}
	return $html;
}

function termos_montar_signatarios(array $dados, array $assinantes): array {
	$out = [];
	foreach($assinantes as $as){
		$tipo = strval($as["moas_tx_tipo"] ?? "funcionario");
		$funcao = trim(strval($as["moas_tx_funcao"] ?? ""));
		if($funcao === ""){
			$funcao = "Signatário";
		}
		$ordem = max(1, intval($as["moas_nb_ordem"] ?? 1));
		if($tipo === "funcionario"){
			$out[] = [
				"enti_nb_id" => intval($dados["enti_nb_id"] ?? 0),
				"nome" => strval($dados["enti_tx_nome"] ?? ""),
				"email" => strval($dados["enti_tx_email"] ?? ""),
				"funcao" => $funcao,
				"ordem" => $ordem
			];
		}elseif($tipo === "empresa"){
			$out[] = [
				"enti_nb_id" => 0,
				"nome" => strval($dados["empr_tx_nome"] ?? ""),
				"email" => strval($dados["empr_email"] ?? ""),
				"funcao" => $funcao,
				"ordem" => $ordem
			];
		}elseif($tipo === "funcionario_especifico"){
			$id = intval($as["moas_nb_entidade"] ?? 0);
			$d = $id > 0 ? termos_dados_funcionario($id) : [];
			$out[] = [
				"enti_nb_id" => $id,
				"nome" => strval($d["enti_tx_nome"] ?? ""),
				"email" => strval($d["enti_tx_email"] ?? ""),
				"funcao" => $funcao,
				"ordem" => $ordem
			];
		}else{
			$out[] = [
				"enti_nb_id" => 0,
				"nome" => strval($as["moas_tx_nome"] ?? ""),
				"email" => strval($as["moas_tx_email"] ?? ""),
				"funcao" => $funcao,
				"ordem" => $ordem
			];
		}
	}
	return $out;
}

function termos_resolver_placeholders(string $conteudo, array $dados, array $modelo = [], array $assinantes = [], array $ativo = []): array {
	$cidadeAss = termos_cidade_assinatura($dados, $modelo);
	$admissao = trim(strval($dados["enti_tx_admissao"] ?? ""));
	$cargo = trim(strval($dados["cargo_nome"] ?? ""));
	if($cargo === ""){
		$cargo = trim(strval($dados["enti_tx_ocupacao"] ?? ""));
	}

	$mapa = [
		"empresa" => termos_h($dados["empr_tx_nome"] ?? ""),
		"empresa_razao" => termos_h($dados["empr_tx_nome"] ?? ""),
		"empresa_cnpj" => termos_formatar_cnpj($dados["empr_tx_cnpj"] ?? ""),
		"cnpj" => termos_formatar_cnpj($dados["empr_tx_cnpj"] ?? ""),
		"empresa_contato" => termos_h($dados["empr_tx_contato"] ?? ""),
		"empresa_email" => termos_h($dados["empr_email"] ?? ""),
		"funcionario_nome" => termos_h($dados["enti_tx_nome"] ?? ""),
		"nome" => termos_h($dados["enti_tx_nome"] ?? ""),
		"funcionario_cpf" => termos_formatar_cpf($dados["enti_tx_cpf"] ?? ""),
		"cpf" => termos_formatar_cpf($dados["enti_tx_cpf"] ?? ""),
		"funcionario_rg" => termos_h($dados["enti_tx_rg"] ?? ""),
		"rg" => termos_h($dados["enti_tx_rg"] ?? ""),
		"funcionario_pis" => termos_h($dados["enti_tx_pis"] ?? ""),
		"pis" => termos_h($dados["enti_tx_pis"] ?? ""),
		"ctps_numero" => termos_h($dados["enti_tx_ctpsNumero"] ?? ""),
		"carteira_trabalho" => termos_h($dados["enti_tx_ctpsNumero"] ?? ""),
		"ctps_serie" => termos_h($dados["enti_tx_ctpsSerie"] ?? ""),
		"ctps_uf" => termos_h($dados["enti_tx_ctpsUf"] ?? ""),
		"admissao" => termos_formatar_data($admissao),
		"admissao_extenso" => termos_data_extenso($admissao),
		"cargo" => termos_h($cargo),
		"matricula" => termos_h($dados["enti_tx_matricula"] ?? ""),
		"cnh_numero" => termos_h($dados["enti_tx_cnhRegistro"] ?? ""),
		"cnh_categoria" => termos_h($dados["enti_tx_cnhCategoria"] ?? ""),
		"cnh_validade" => termos_formatar_data($dados["enti_tx_cnhValidade"] ?? ""),
		"email_funcionario" => termos_h($dados["enti_tx_email"] ?? ""),
		"denominacao" => termos_h($modelo["mode_tx_denominacao"] ?? ""),
		"cidade_assinatura" => termos_h($cidadeAss),
		"cidade" => termos_h($cidadeAss),
		"data_atual" => date("d/m/Y"),
		"data_atual_extenso" => termos_data_extenso(date("Y-m-d")),
		"bloco_assinaturas" => termos_bloco_assinaturas($dados, $assinantes)
	];

	// Campos do equipamento: gerados dinamicamente a partir das colunas da tabela celular.
	foreach(termos_colunas_ativo() as $token => $info){
		$coluna = strval($info["coluna"] ?? "");
		if($coluna === ""){
			$mapa[$token] = termos_h($ativo["responsavel"] ?? "");
			continue;
		}
		$mapa[$token] = termos_valor_placeholder_ativo($coluna, $ativo);
	}

	// Compatibilidade com modelos antigos que usavam os tokens {{ativo_*}}.
	foreach($mapa as $token => $valor){
		if(strpos($token, "equipamento_") === 0){
			$mapa["ativo_" . substr($token, strlen("equipamento_"))] = $valor;
		}
	}
	if(isset($mapa["equipamento_iccid"])){
		$mapa["ativo_cimie"] = $mapa["equipamento_iccid"];
	}
	$marcaModelo = trim(strval($ativo["celu_tx_marcaModelo"] ?? ""));
	if($marcaModelo === ""){
		$marcaModelo = trim(strval($ativo["celu_tx_marca"] ?? "") . " " . strval($ativo["celu_tx_modelo"] ?? ""));
	}
	$mapa["ativo_marca_modelo"] = termos_h($marcaModelo);

	$usados = [];
	$faltando = [];
	$conteudo = preg_replace_callback('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', function($m) use ($mapa, &$usados, &$faltando){
		$chave = strtolower($m[1]);
		if(array_key_exists($chave, $mapa)){
			$usados[$chave] = true;
			return strval($mapa[$chave]);
		}
		$faltando[$m[1]] = true;
		return $m[0];
	}, $conteudo);

	return [$conteudo, $usados, $faltando];
}

function termos_resolver_caminho_arquivo(string $caminho): string {
	$caminho = trim($caminho);
	if($caminho === ""){
		return "";
	}
	if(preg_match('#^(https?:)?//#i', $caminho)){
		return $caminho;
	}
	$docDir = dirname(__DIR__);
	$modDir = dirname(__DIR__, 2);
	$cands = [];
	if($caminho[0] === "/" || preg_match('#^[A-Za-z]:[\\\\/]#', $caminho)){
		$cands[] = $caminho;
	}else{
		$cands[] = $docDir . "/" . ltrim($caminho, "/\\");
		$cands[] = $modDir . "/" . ltrim($caminho, "/\\");
		$cands[] = $caminho;
	}
	foreach($cands as $cand){
		if(file_exists($cand) && is_file($cand)){
			$rp = realpath($cand);
			return $rp ? str_replace("\\", "/", $rp) : $cand;
		}
	}
	return "";
}

function termos_desenhar_imagem($pdf, string $caminho, float $x, float $y, float $larguraMax, float $alturaMax, bool $alinharDireita = false): void {
	$arquivo = termos_resolver_caminho_arquivo($caminho);
	if($arquivo === ""){
		return;
	}
	$info = @getimagesize($arquivo);
	if(!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)){
		return;
	}
	$largura = $larguraMax;
	$altura = $alturaMax;
	if($info[0] > 0 && $info[1] > 0){
		$proporcao = $info[0] / $info[1];
		$altura = $larguraMax / $proporcao;
		if($altura > $alturaMax){
			$altura = $alturaMax;
			$largura = $alturaMax * $proporcao;
		}
	}
	if($alinharDireita){
		$x = $x - $largura;
	}
	$pdf->Image($arquivo, $x, $y, $largura, $altura, "", "", "", true, 300, "", false, false, 0, false, false, false);
}

function termos_renderizar_pdf(array $dados, array $modelo, array $tipo, string $destino, bool $preview = false, array $ativo = []): bool {
	require_once dirname(__DIR__, 2) . "/tcpdf/tcpdf.php";

	$assinantes = termos_carregar_assinantes(intval($modelo["mode_nb_id"] ?? 0));
	$conteudo = termos_resolver_placeholders(strval($modelo["mode_tx_conteudo"] ?? ""), $dados, $modelo, $assinantes, $ativo)[0];

	if(!class_exists("MYPDF_Termos", false)){
		class MYPDF_Termos extends TCPDF {
			public $termos_header = "";
			public $termos_footer = "";
			public $termos_logo = "";
			public $termos_empresa_logo = "";
			public $termos_preview = false;

			public function Header() {
				if(!empty($this->termos_preview)){
					$this->setAlpha(0.14);
					$this->StartTransform();
					$this->Rotate(45, 105, 148);
					$this->SetFont("helvetica", "B", 42);
					$this->SetTextColor(200, 50, 50);
					$this->Text(30, 148, "PRÉ-VISUALIZAÇÃO", false, false, true, 0, 0, "C");
					$this->StopTransform();
					$this->setAlpha(1);
					$this->SetTextColor(0, 0, 0);
				}

				termos_desenhar_imagem($this, strval($this->termos_logo), 14, 10, 24, 12, false);
				termos_desenhar_imagem($this, strval($this->termos_empresa_logo), $this->getPageWidth() - 14, 10, 24, 12, true);
				$this->SetY(24);
				$this->SetFont("helvetica", "B", 14);
				$titulo = mb_strtoupper(trim(strip_tags(strval($this->termos_header))), "UTF-8");
				if($titulo === ""){
					$titulo = "Documento";
				}
				$this->Cell(0, 6, $titulo, 0, false, "C", 0, "", 0, false, "M", "M");
				$this->Line(15, 31, 195, 31);
			}

			public function Footer() {
				$this->SetY(-15);
				$this->SetFont("helvetica", "I", 8);
				$txt = trim(strip_tags(strval($this->termos_footer)));
				if($txt !== ""){
					$txt .= " | ";
				}
				$this->Cell(0, 10, $txt . "Gerado em " . date("d/m/Y H:i") . " | Página " . $this->getAliasNumPage() . "/" . $this->getAliasNbPages(), 0, false, "C", 0, "", 0, false, "T", "M");
			}
		}
	}

	try{
		$pdf = new MYPDF_Termos(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, "UTF-8", false);
		$pdf->termos_header = trim(strip_tags(strval($tipo["tipo_tx_cabecalho"] ?? "")));
		if($pdf->termos_header === ""){
			$pdf->termos_header = strval($modelo["mode_tx_nome"] ?? "Documento");
		}
		$pdf->termos_footer = trim(strip_tags(strval($tipo["tipo_tx_rodape"] ?? "")));
		$pdf->termos_logo = trim(strval($tipo["tipo_tx_logo"] ?? ""));
		if($pdf->termos_logo === ""){
			$pdf->termos_logo = dirname(__DIR__, 2) . "/imagens/logo_topo_cliente.png";
		}
		$pdf->termos_empresa_logo = trim(strval($dados["empr_tx_logo"] ?? ""));
		$pdf->termos_preview = $preview;

		$pdf->SetCreator("TechPS");
		$pdf->SetAuthor("TechPS");
		$pdf->SetTitle(trim(strval($modelo["mode_tx_nome"] ?? "Documento")));
		$pdf->SetMargins(15, 35, 15);
		$pdf->SetAutoPageBreak(true, 15);

		$pdf->AddPage();
		$pdf->SetFont("helvetica", "", 11);
		$pdf->writeHTML($conteudo, true, false, true, false, "");

		if($preview){
			$pdf->Output(basename($destino), "I");
		}else{
			$pdf->Output($destino, "F");
		}
		return true;
	}catch(Throwable $e){
		termos_log("pdf_erro", $e->getMessage(), [
			"modelo" => intval($modelo["mode_nb_id"] ?? 0),
			"entidade" => intval($dados["enti_nb_id"] ?? 0)
		]);
		return false;
	}
}

function termos_registrar_documento_funcionario(int $entidadeId, int $tipoId, int $sbgrupoId, string $nomeDoc, string $descricao, string $caminhoRel, string $assinado = "nao"): int {
	global $conn;
	$entidadeId = intval($entidadeId);
	if($entidadeId <= 0 || trim($caminhoRel) === ""){
		return 0;
	}

	$existente = query(
		"SELECT docu_nb_id FROM documento_funcionario WHERE docu_nb_entidade = ? AND docu_tx_caminho = ? LIMIT 1",
		"is",
		[$entidadeId, $caminhoRel]
	);
	if($existente instanceof mysqli_result && ($row = mysqli_fetch_assoc($existente))){
		return intval($row["docu_nb_id"]);
	}

	$dados = [
		"docu_nb_entidade" => (string)$entidadeId,
		"docu_tx_nome" => mb_substr(trim(strval($nomeDoc)), 0, 250, "UTF-8"),
		"docu_tx_descricao" => mb_substr(trim(strval($descricao)), 0, 250, "UTF-8"),
		"docu_tx_dataCadastro" => date("Y-m-d H:i:s"),
		"docu_tx_dataVencimento" => null,
		"docu_tx_tipo" => (string)(int)$tipoId,
		"docu_nb_sbgrupo" => (int)$sbgrupoId,
		"docu_tx_usuarioCadastro" => intval($_SESSION["user_nb_id"] ?? 0),
		"docu_tx_assinado" => $assinado === "sim" ? "sim" : "nao",
		"docu_tx_visivel" => "sim",
		"docu_tx_caminho" => $caminhoRel
	];
	$retorno = inserir("documento_funcionario", array_keys($dados), array_values($dados));
	if(is_array($retorno)){
		foreach($retorno as $r){
			if(is_int($r) && $r > 0){
				return $r;
			}
		}
	}
	if(is_numeric($retorno)){
		return (int)$retorno;
	}
	return 0;
}

function termos_executar(string $sql, string $types = "", array $vars = []): bool {
	global $conn;
	if($types === "" || empty($vars)){
		return (bool)mysqli_query($conn, $sql);
	}
	$stmt = mysqli_prepare($conn, $sql);
	if(!$stmt){
		return false;
	}
	mysqli_stmt_bind_param($stmt, $types, ...$vars);
	$ok = mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	return $ok;
}

function termos_inserir_id(string $sql, string $types, array $vars): int {
	global $conn;
	$stmt = mysqli_prepare($conn, $sql);
	if(!$stmt){
		return 0;
	}
	mysqli_stmt_bind_param($stmt, $types, ...$vars);
	if(!mysqli_stmt_execute($stmt)){
		mysqli_stmt_close($stmt);
		return 0;
	}
	$id = (int)mysqli_stmt_insert_id($stmt);
	mysqli_stmt_close($stmt);
	return $id;
}

function termos_inserir_gerado(int $modeloId, int $entiId, int $tipoId, string $status, $caminho, $sol, $idDoc, $docu, $detalhe, int $user, int $ativoId = 0): int {
	return termos_inserir_id(
		"INSERT INTO termo_gerado
			(terg_nb_modelo, terg_nb_entidade, terg_nb_tipo_doc, terg_tx_status, terg_tx_caminho,
			 terg_nb_solicitacao_assinatura, terg_tx_id_documento, terg_nb_documento_funcionario,
			 terg_tx_detalhe, terg_nb_user_geracao, terg_nb_ativo)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
		"iiissiisiii",
		[
			$modeloId,
			$entiId,
			$tipoId,
			$status,
			($caminho !== "" && $caminho !== null) ? strval($caminho) : null,
			$sol > 0 ? $sol : null,
			($idDoc !== "" && $idDoc !== null) ? strval($idDoc) : null,
			$docu > 0 ? $docu : null,
			($detalhe !== "" && $detalhe !== null) ? strval($detalhe) : null,
			$user,
			$ativoId > 0 ? $ativoId : null
		]
	);
}

function termos_atualizar_gerado(int $id, array $campos): bool {
	if($id <= 0 || empty($campos)){
		return false;
	}
	$sets = [];
	$types = "";
	$vars = [];
	foreach($campos as $campo => $valor){
		if(!preg_match('/^[a-zA-Z0-9_]+$/', $campo)){
			continue;
		}
		if($valor === null){
			$sets[] = "{$campo} = NULL";
			continue;
		}
		$sets[] = "{$campo} = ?";
		$types .= "s";
		$vars[] = strval($valor);
	}
	if(empty($sets)){
		return false;
	}
	$types .= "i";
	$vars[] = $id;
	return termos_executar("UPDATE termo_gerado SET " . implode(", ", $sets) . " WHERE terg_nb_id = ?", $types, $vars);
}

function termos_caminho_final(int $entiId, array $modelo, array $dados): array {
	$dir = dirname(__DIR__, 2) . "/arquivos/Funcionarios/" . $entiId . "/";
	if(!is_dir($dir)){
		@mkdir($dir, 0777, true);
	}
	$nome = termos_nome_arquivo($modelo, $dados);
	$dest = rtrim(str_replace("\\", "/", $dir), "/") . "/" . $nome;
	if(file_exists($dest)){
		$info = pathinfo($nome);
		$nome = $info["filename"] . "_" . date("YmdHis") . "." . ($info["extension"] ?? "pdf");
		$dest = rtrim(str_replace("\\", "/", $dir), "/") . "/" . $nome;
	}
	$rel = "arquivos/Funcionarios/" . $entiId . "/" . $nome;
	return [$dest, $rel];
}

function termos_email_fallback(array $dados, array $signatario = []): string {
	$id = intval($signatario["enti_nb_id"] ?? 0);
	if($id > 0){
		return "sememail." . $id . "@techps.com.br";
	}
	$emprId = intval($dados["empr_nb_id"] ?? 0);
	if($emprId > 0){
		return "sememail.empresa." . $emprId . "@techps.com.br";
	}
	return "sememail@techps.com.br";
}

function termos_enviar_assinatura(int $entiId, array $dados, array $modelo, array $tipo, string $pdfTmp, array $opts): array {
	require_once dirname(__DIR__) . "/../assinatura/integracao/assinatura_integracao.php";

	$nomeArquivo = trim(strval($opts["nome_arquivo"] ?? ""));
	if($nomeArquivo === ""){
		$nomeArquivo = termos_nome_arquivo($modelo, $dados);
	}

	$enviarEmail = strtolower(trim(strval($opts["enviar_email"] ?? "sim"))) !== "nao";

	$base = [
		"tipo_documento_id" => intval($tipo["tipo_nb_id"] ?? 0),
		"validar_icp" => strtolower(trim(strval($opts["validar_icp"] ?? "nao"))) === "sim" ? "sim" : "nao",
		"modo_envio" => "avulso",
		"grupo_envio" => "termo_" . intval($modelo["mode_nb_id"] ?? 0),
		"nome_arquivo_original" => $nomeArquivo,
		"enviar_email" => $enviarEmail ? "sim" : "nao",
		"prazo_expiracao_dias" => intval($opts["prazo_expiracao_dias"] ?? 1),
		"apagar_origem" => true
	];

	$assinantes = termos_carregar_assinantes(intval($modelo["mode_nb_id"] ?? 0));
	if(empty($assinantes)){
		$base["funcao"] = "Funcionário";
		$base["salvar_documento_funcionario"] = "sim";
		if(!$enviarEmail){
			$base["email_fallback"] = termos_email_fallback($dados, ["enti_nb_id" => $entiId]);
		}
		return assinatura_integracao_enviarDocumentoParaAssinatura($GLOBALS["conn"], $entiId, $pdfTmp, $base);
	}

	$signatarios = termos_montar_signatarios($dados, $assinantes);
	if(!$enviarEmail){
		foreach($signatarios as &$s){
			$email = trim(strval($s["email"] ?? ""));
			if($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)){
				$s["email"] = termos_email_fallback($dados, $s);
			}
		}
		unset($s);
	}else{
		$validos = 0;
		foreach($signatarios as $s){
			$email = trim(strval($s["email"] ?? ""));
			if($email !== "" && filter_var($email, FILTER_VALIDATE_EMAIL)){
				$validos++;
			}
		}
		if($validos < 1){
			return ["ok" => false, "error" => "Nenhum signatário configurado possui e-mail válido."];
		}
	}

	return assinatura_integracao_enviarDocumentoParaMultiplosAssinantes($GLOBALS["conn"], $pdfTmp, $signatarios, $base);
}

function termos_url_visualizacao(string $token): string {
	$token = trim($token);
	if($token === ""){
		return "";
	}
	$sufixo = "/documentos/termos/visualizar_termo.php?token=" . urlencode($token);
	$raiz = rtrim(strval($_ENV["URL_BASE"] ?? ""), "/");
	$ctx = rtrim(strval($GLOBALS["CONTEX"]["path"] ?? ""), "/");
	if($ctx === ""){
		$ctx = rtrim(strval($_ENV["APP_PATH"] ?? ""), "/") . rtrim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");
	}
	if($raiz !== ""){
		return $raiz . rtrim($ctx, "/") . $sufixo;
	}
	$https = strtolower(trim(strval($_SERVER["HTTPS"] ?? "")));
	$proto = trim(strval($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? ""));
	if($proto === ""){
		$proto = ($https !== "" && $https !== "off") ? "https" : "http";
	}
	$host = trim(strval($_SERVER["HTTP_X_FORWARDED_HOST"] ?? ($_SERVER["HTTP_HOST"] ?? "")));
	if($ctx === ""){
		$dir = rtrim(str_replace("\\", "/", dirname(strval($_SERVER["SCRIPT_NAME"] ?? ""))), "/");
		return $proto . "://" . $host . $dir . "/visualizar_termo.php?token=" . urlencode($token);
	}
	return $proto . "://" . $host . rtrim($ctx, "/") . $sufixo;
}

function termos_enviar_email_visualizacao(string $email, string $nome, string $nomeArquivo, string $link, int $prazoDias = 0): bool {
	$email = trim($email);
	if($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)){
		return false;
	}
	require_once dirname(__DIR__) . "/../assinatura/integracao/assinatura_integracao.php";
	assinatura_integracao_carregarEmail();
	if(!function_exists("configurarSMTP") || !class_exists("PHPMailer\\PHPMailer\\PHPMailer")){
		return false;
	}

	try{
		$mail = new PHPMailer\PHPMailer\PHPMailer(true);
		configurarSMTP($mail);
		$mail->addAddress($email, $nome);

		$cidLogo = function_exists("assinatura_email_embedLogo") ? assinatura_email_embedLogo($mail, "logo_techps_visualizacao") : "";
		$mail->isHTML(true);
		$mail->Subject = "Documento disponível para visualização: " . $nomeArquivo;

		$headerHtml = $cidLogo !== ""
			? "<img src='{$cidLogo}' alt='TechPS' style='max-width: 150px;'>"
			: "<h2 style='color: #333; margin: 0;'>TechPS</h2>";

		$nomeSafe = function_exists("assinatura_h") ? assinatura_h(strtoupper($nome)) : htmlspecialchars(strtoupper($nome), ENT_QUOTES, "UTF-8");
		$arquivoSafe = function_exists("assinatura_h") ? assinatura_h($nomeArquivo) : htmlspecialchars($nomeArquivo, ENT_QUOTES, "UTF-8");
		$linkSafe = function_exists("assinatura_h") ? assinatura_h($link) : htmlspecialchars($link, ENT_QUOTES, "UTF-8");
		$prazoTxt = $prazoDias > 0
			? "<p style='color:#b45309; font-size: 14px; margin: 0 0 8px 0;'>Este link fica disponível por " . intval($prazoDias) . " dia(s).</p>"
			: "";

		$contentHtml = "
			<h2 style='color: #333; font-size: 24px; margin-top: 0;'>Olá, {$nomeSafe}.</h2>

			<p style='color: #555; font-size: 16px; line-height: 1.5;'>
				Existe um documento disponível para a sua <strong style='color: #0056b3;'>visualização</strong>:
			</p>

			<div style='background-color: #f8f9fa; border-left: 4px solid #0056b3; padding: 20px; margin: 25px 0; border-radius: 4px;'>
				<p style='margin: 0; color: #555;'>
					<strong style='color: #333;'>Documento:</strong> <br>
					<span style='font-size: 18px; color: #0056b3;'>{$arquivoSafe}</span>
				</p>
			</div>

			<div style='text-align: center; margin: 35px 0;'>
				<a href='{$linkSafe}' style='background-color: #0056b3; color: white; padding: 16px 32px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
					Visualizar Documento
				</a>
			</div>

			{$prazoTxt}

			<div style='border-top: 1px solid #eee; margin-top: 30px; padding-top: 20px;'>
				<p style='font-size: 13px; color: #777; margin-bottom: 10px;'>
					Se o botão não funcionar, copie e cole o link abaixo no seu navegador:
				</p>
				<p style='font-size: 12px; color: #555; background: #f8f9fa; padding: 10px; border-radius: 4px; word-break: break-all; font-family: monospace; border: 1px solid #eee;'>
					{$linkSafe}
				</p>
			</div>

			<div style='margin-top: 30px; font-size: 12px; color: #777; text-align: justify; line-height: 1.5; border-top: 1px solid #eee; padding-top: 20px;'>
				<p style='margin-bottom: 10px;'>
					Ao abrir o documento, o sistema registra automaticamente data, hora, endereço IP e demais dados do acesso para fins de auditoria, comprovando a sua ciência sobre o conteúdo.
				</p>
				<p style='margin-top: 10px;'>
					<strong>Data de Envio:</strong> " . date("d/m/Y H:i:s") . "
				</p>
			</div>
		";

		$footerHtml = "
			<p style='margin: 0;'>Mensagem automática enviada pelo sistema de Documentos TechPS.</p>
			<p style='margin: 5px 0 0 0;'>&copy; " . date("Y") . " Armazém Paraíba - Todos os direitos reservados.</p>
		";

		if(function_exists("assinatura_email_wrap")){
			$mail->Body = assinatura_email_wrap($headerHtml, $contentHtml, $footerHtml);
		}else{
			$mail->Body = $headerHtml . $contentHtml . $footerHtml;
		}
		$mail->AltBody = "Documento disponível para visualização: {$nomeArquivo}\n\nAcesse: {$link}\n";
		$mail->send();
		return true;
	}catch(Throwable $e){
		termos_log("email_visualizacao_erro", $e->getMessage(), ["email" => $email, "arquivo" => $nomeArquivo]);
		return false;
	}
}

function termos_criar_notificacao_visualizacao(int $entiId, array $dados, array $modelo, array $tipo, string $rel, array $params): array {
	require_once dirname(__DIR__) . "/../assinatura/integracao/assinatura_integracao.php";

	$modeloId = intval($modelo["mode_nb_id"] ?? 0);
	$tipoId = intval($tipo["tipo_nb_id"] ?? 0);
	$pdfAbs = termos_resolver_caminho_arquivo($rel);
	if($pdfAbs === ""){
		return ["ok" => false, "error" => "PDF do documento não encontrado para gerar a notificação."];
	}

	$nomeArquivo = basename(str_replace("\\", "/", $rel));
	$prazo = intval($params["prazo_expiracao_dias"] ?? 1);
	if($prazo < 0){
		$prazo = 0;
	}

	$opts = [
		"tipo_documento_id" => $tipoId,
		"validar_icp" => "nao",
		"modo_envio" => "termo_notificacao",
		"grupo_envio" => "termo_" . $modeloId,
		"nome_arquivo_original" => $nomeArquivo,
		"enviar_email" => "nao",
		"prazo_expiracao_dias" => $prazo,
		"apagar_origem" => false,
		"salvar_documento_funcionario" => "nao",
		"funcao" => "Visualização",
		"email_fallback" => termos_email_fallback($dados, ["enti_nb_id" => $entiId])
	];

	$criada = assinatura_integracao_enviarDocumentoParaAssinatura($GLOBALS["conn"], $entiId, $pdfAbs, $opts);
	if(empty($criada["ok"])){
		return ["ok" => false, "error" => strval($criada["error"] ?? "Falha ao criar a notificação no módulo de assinatura.")];
	}

	$token = strval($criada["token_assinante"] ?? "");
	$solId = intval($criada["id_solicitacao"] ?? 0);
	$idDoc = strval($criada["id_documento"] ?? "");

	$assinanteId = 0;
	if($token !== ""){
		$resAss = query("SELECT id FROM assinantes WHERE token = ? LIMIT 1", "s", [$token]);
		if($resAss instanceof mysqli_result && ($rowAss = mysqli_fetch_assoc($resAss))){
			$assinanteId = intval($rowAss["id"]);
		}
	}
	// A notificação de visualização não é uma pendência de assinatura: sai da lista
	// de "Assinaturas Pendentes" web (que usa status 'pendente'), mas continua sendo
	// devolvida para o app como notificação (que ignora apenas 'dispensado'/'assinado').
	if($assinanteId > 0){
		termos_executar("UPDATE assinantes SET status = 'visualizado' WHERE id = ? AND LOWER(TRIM(status)) = 'pendente'", "i", [$assinanteId]);
	}

	$link = termos_url_visualizacao($token);
	$nomeFunc = trim(strval($dados["enti_tx_nome"] ?? ""));
	termos_executar(
		"INSERT INTO notificacoes (notf_nb_entidade, notf_tx_titulo, notf_tx_mensagem, notf_tx_link, notf_tx_tipo, notf_tx_status) VALUES (?, ?, ?, ?, 'info', 'nao_lida')",
		"isss",
		[
			$entiId,
			"Documento disponível para visualização",
			"O documento \"" . $nomeArquivo . "\" está disponível para leitura." . ($nomeFunc !== "" ? " Confira e registre a ciência." : ""),
			$link
		]
	);

	$emailEnviado = false;
	$enviarEmail = strtolower(trim(strval($params["enviar_email"] ?? "sim"))) !== "nao";
	if($enviarEmail){
		$emailDest = trim(strval($criada["email"] ?? ""));
		$nomeDest = trim(strval($criada["nome"] ?? $nomeFunc));
		if($emailDest !== "" && filter_var($emailDest, FILTER_VALIDATE_EMAIL) && !preg_match('/^sememail\./i', $emailDest)){
			$emailEnviado = termos_enviar_email_visualizacao($emailDest, $nomeDest, $nomeArquivo, $link, $prazo);
		}
	}

	return [
		"ok" => true,
		"id_solicitacao" => $solId,
		"id_documento" => $idDoc,
		"id_assinante" => $assinanteId,
		"token" => $token,
		"link" => $link,
		"email_enviado" => $emailEnviado
	];
}

function termos_ip_cliente(): string {
	$candidatos = [
		$_SERVER["HTTP_CF_CONNECTING_IP"] ?? "",
		$_SERVER["HTTP_X_REAL_IP"] ?? "",
		$_SERVER["HTTP_X_FORWARDED_FOR"] ?? "",
		$_SERVER["REMOTE_ADDR"] ?? ""
	];
	foreach($candidatos as $c){
		$c = trim(explode(",", strval($c))[0]);
		if($c !== "" && filter_var($c, FILTER_VALIDATE_IP)){
			return $c;
		}
	}
	return trim(strval($_SERVER["REMOTE_ADDR"] ?? ""));
}

function termos_processar_um(int $entiId, array $params): array {
	global $conn;
	$modeloId = intval($params["modelo_id"] ?? 0);
	$user = intval($_SESSION["user_nb_id"] ?? 0);

	$modelo = termos_carregar_modelo($modeloId);
	$nome = trim(strval($modelo["mode_tx_nome"] ?? ""));
	if(empty($modelo) || strtolower(trim(strval($modelo["mode_tx_status"] ?? "inativo"))) !== "ativo"){
		return ["ok" => false, "entidade" => $entiId, "msg" => "Modelo de termo não encontrado ou inativo."];
	}

	$tipo = termos_carregar_tipo(intval($modelo["mode_nb_tipo_doc"] ?? 0));
	if(empty($tipo) || strtolower(trim(strval($tipo["tipo_tx_status"] ?? "inativo"))) !== "ativo"){
		return ["ok" => false, "entidade" => $entiId, "msg" => "Tipo de documento ('" . $nome . "') não encontrado ou inativo."];
	}

	$dados = termos_dados_funcionario($entiId);
	if(empty($dados)){
		return ["ok" => false, "entidade" => $entiId, "msg" => "Funcionário não encontrado."];
	}
	$nomeFunc = trim(strval($dados["enti_tx_nome"] ?? ""));

	$exigeAtivo = termos_tipo_requer_ativo($tipo);
	$ativoId = intval($params["ativo_id"] ?? 0);
	if($ativoId <= 0){
		$ativoId = intval($modelo["mode_nb_ativo"] ?? 0);
	}
	if($ativoId <= 0){
		$ativoId = termos_ativo_do_funcionario($entiId);
	}
	$ativo = [];
	if($exigeAtivo){
		if($ativoId > 0){
			$ativo = termos_dados_ativo($ativoId);
		}
		if(empty($ativo)){
			return ["ok" => false, "entidade" => $entiId, "nome" => $nomeFunc, "msg" => $nomeFunc . " — selecione o equipamento (celular) que será vinculado a este termo."];
		}
		$ativoId = intval($ativo["celu_nb_id"]);
	}else{
		$ativoId = 0;
	}

	$forcar = strtolower(trim(strval($params["forcar"] ?? "nao"))) === "sim";
	if(!$forcar){
		$sqlDup = "SELECT terg_nb_id FROM termo_gerado WHERE terg_nb_modelo = ? AND terg_nb_entidade = ? AND terg_tx_status IN ('gerado','notificado','visualizado','aguardando_assinatura','assinado')";
		$typesDup = "ii";
		$varsDup = [$modeloId, $entiId];
		if($exigeAtivo && $ativoId > 0){
			$sqlDup .= " AND terg_nb_ativo = ?";
			$typesDup .= "i";
			$varsDup[] = $ativoId;
		}
		$sqlDup .= " LIMIT 1";
		$dup = query($sqlDup, $typesDup, $varsDup);
		if($dup instanceof mysqli_result && mysqli_num_rows($dup) > 0){
			return ["ok" => false, "entidade" => $entiId, "nome" => $nomeFunc, "msg" => $nomeFunc . " — já possui termo gerado/assinado (use 'Forçar' para regerar)."];
		}
	}

	$tipoId = intval($tipo["tipo_nb_id"] ?? 0);
	$sbgrupo = intval($tipo["tipo_nb_sbgrupo"] ?? 0);
	$requerAssinatura = termos_tipo_requer_assinatura($tipo);
	$descricao = "Termo: " . $nome;

	if($requerAssinatura){
		$tmpDir = dirname(__DIR__) . "/../assinatura/uploads/tmp/";
		if(!is_dir($tmpDir)){
			@mkdir($tmpDir, 0777, true);
		}
		$tmpPdf = rtrim(str_replace("\\", "/", $tmpDir), "/") . "/termo_{$modeloId}_{$entiId}_" . date("YmdHis") . "_" . bin2hex(random_bytes(3)) . ".pdf";

		if(!termos_renderizar_pdf($dados, $modelo, $tipo, $tmpPdf, false, $ativo)){
			return ["ok" => false, "entidade" => $entiId, "nome" => $nomeFunc, "msg" => $nomeFunc . " — falha ao gerar o PDF do termo."];
		}

		$envio = termos_enviar_assinatura($entiId, $dados, $modelo, $tipo, $tmpPdf, $params);
		if(empty($envio["ok"])){
			@unlink($tmpPdf);
			$msgErro = strval($envio["error"] ?? "Falha ao enviar para assinatura.");
			termos_inserir_gerado($modeloId, $entiId, $tipoId, "erro", null, 0, "", 0, "Assinatura: " . $msgErro, $user, $ativoId);
			termos_log("assinatura_erro", $msgErro, ["modelo" => $modeloId, "entidade" => $entiId]);
			return ["ok" => false, "entidade" => $entiId, "nome" => $nomeFunc, "msg" => $nomeFunc . " — erro ao enviar assinatura: " . $msgErro];
		}

		$regId = termos_inserir_gerado(
			$modeloId,
			$entiId,
			$tipoId,
			"aguardando_assinatura",
			null,
			intval($envio["id_solicitacao"] ?? 0),
			strval($envio["id_documento"] ?? ""),
			0,
			"Enviado para assinatura eletrônica.",
			$user,
			$ativoId
		);
		termos_log("gerado", "Termo gerado e enviado para assinatura", [
			"modelo" => $modeloId,
			"entidade" => $entiId,
			"termo" => $regId,
			"solicitacao" => intval($envio["id_solicitacao"] ?? 0)
		]);
		return ["ok" => true, "entidade" => $entiId, "nome" => $nomeFunc, "msg" => $nomeFunc . " — enviado para assinatura eletrônica."];
	}

	[$dest, $rel] = termos_caminho_final($entiId, $modelo, $dados);
	if(!termos_renderizar_pdf($dados, $modelo, $tipo, $dest, false, $ativo)){
		return ["ok" => false, "entidade" => $entiId, "nome" => $nomeFunc, "msg" => $nomeFunc . " — falha ao gerar o PDF do termo."];
	}

	$docuId = termos_registrar_documento_funcionario($entiId, $tipoId, $sbgrupo, $nome, $descricao, $rel, "nao");

	$notificar = strtolower(trim(strval($modelo["mode_tx_notificacao"] ?? "sim"))) === "sim";
	if($notificar){
		$notif = termos_criar_notificacao_visualizacao($entiId, $dados, $modelo, $tipo, $rel, $params);
		if(empty($notif["ok"])){
			$msgErroNotif = strval($notif["error"] ?? "Falha ao notificar o funcionário.");
			$regId = termos_inserir_gerado($modeloId, $entiId, $tipoId, "gerado", $rel, 0, "", $docuId, "Documento gerado sem assinatura. Notificação não enviada: " . $msgErroNotif, $user, $ativoId);
			termos_log("notificacao_erro", $msgErroNotif, ["modelo" => $modeloId, "entidade" => $entiId, "termo" => $regId]);
			return ["ok" => true, "entidade" => $entiId, "nome" => $nomeFunc, "msg" => $nomeFunc . " — documento gerado e salvo no funcionário, mas houve falha na notificação: " . $msgErroNotif];
		}

		$regId = termos_inserir_gerado(
			$modeloId,
			$entiId,
			$tipoId,
			"notificado",
			$rel,
			intval($notif["id_solicitacao"] ?? 0),
			strval($notif["id_documento"] ?? ""),
			$docuId,
			"Documento salvo no funcionário e notificação de visualização enviada.",
			$user,
			$ativoId
		);
		termos_atualizar_gerado($regId, [
			"terg_nb_assinante" => intval($notif["id_assinante"] ?? 0),
			"terg_dt_data_notificacao" => date("Y-m-d H:i:s")
		]);
		termos_log("notificado", "Documento gerado e notificação de visualização enviada", [
			"modelo" => $modeloId,
			"entidade" => $entiId,
			"termo" => $regId,
			"caminho" => $rel,
			"solicitacao" => intval($notif["id_solicitacao"] ?? 0),
			"email" => !empty($notif["email_enviado"]) ? "enviado" : "nao_enviado"
		]);
		$msgEmail = !empty($notif["email_enviado"]) ? " e-mail de notificação enviado" : " notificação registrada (e-mail não pôde ser enviado — confira o cadastro/SMTP)";
		return ["ok" => true, "entidade" => $entiId, "nome" => $nomeFunc, "msg" => $nomeFunc . " — documento salvo no funcionário," . $msgEmail . "."];
	}

	$regId = termos_inserir_gerado($modeloId, $entiId, $tipoId, "gerado", $rel, 0, "", $docuId, "Documento gerado sem assinatura.", $user, $ativoId);
	termos_log("gerado", "Termo gerado (PDF local)", [
		"modelo" => $modeloId,
		"entidade" => $entiId,
		"termo" => $regId,
		"caminho" => $rel
	]);
	return ["ok" => true, "entidade" => $entiId, "nome" => $nomeFunc, "msg" => $nomeFunc . " — documento gerado e salvo no funcionário."];
}

function termos_sincronizar_registro(array $registro): array {
	static $expiradasVerificadas = false;
	if(!$expiradasVerificadas){
		$expiradasVerificadas = true;
		require_once dirname(__DIR__) . "/../assinatura/tipo_assinatura_helper.php";
		if(function_exists("assinatura_expirarPendentes") && isset($GLOBALS["conn"])){
			assinatura_expirarPendentes($GLOBALS["conn"]);
		}
	}

	$id = intval($registro["terg_nb_id"] ?? 0);
	$statusAtual = strtolower(trim(strval($registro["terg_tx_status"] ?? "")));
	if($id <= 0 || in_array($statusAtual, ["assinado", "cancelado"], true)){
		return $registro;
	}

	$solId = intval($registro["terg_nb_solicitacao_assinatura"] ?? 0);
	if($solId <= 0){
		return $registro;
	}

	$resModo = query("SELECT modo_envio FROM solicitacoes_assinatura WHERE id = ? LIMIT 1", "i", [$solId]);
	if($resModo instanceof mysqli_result && ($rowModo = mysqli_fetch_assoc($resModo))){
		if(strtolower(trim(strval($rowModo["modo_envio"] ?? ""))) === "termo_notificacao"){
			return $registro;
		}
	}

	$res = query(
		"SELECT status, caminho_arquivo, data_assinatura FROM solicitacoes_assinatura WHERE id = ? LIMIT 1",
		"i",
		[$solId]
	);
	$sol = ($res instanceof mysqli_result) ? mysqli_fetch_assoc($res) : null;
	if(empty($sol)){
		return $registro;
	}

	$st = strtolower(trim(strval($sol["status"] ?? "")));
	if(in_array($st, ["concluido", "assinado", "finalizado"], true)){
		$caminho = trim(strval($sol["caminho_arquivo"] ?? ""));
		$dataAss = trim(strval($sol["data_assinatura"] ?? ""));
		termos_atualizar_gerado($id, [
			"terg_tx_status" => "assinado",
			"terg_tx_caminho" => $caminho !== "" ? $caminho : null,
			"terg_tx_detalhe" => "Documento assinado eletronicamente.",
			"terg_dt_data_assinatura" => $dataAss !== "" && $dataAss !== "0000-00-00 00:00:00" ? $dataAss : null
		]);
		termos_log("sincronizar", "Termo #{$id} marcado como assinado", ["solicitacao" => $solId]);
		$registro["terg_tx_status"] = "assinado";
		$registro["terg_tx_caminho"] = $caminho;
	}elseif(in_array($st, ["cancelada", "cancelado", "expirada", "expirado", "expired"], true)){
		$detalheStatus = stripos($st, "expir") === 0 ? "Solicitação de assinatura expirada (prazo excedido)." : "Solicitação de assinatura " . $st . ".";
		termos_atualizar_gerado($id, [
			"terg_tx_status" => "erro",
			"terg_tx_detalhe" => $detalheStatus
		]);
		termos_log("sincronizar", "Termo #{$id} — solicitação " . $st, ["solicitacao" => $solId]);
		$registro["terg_tx_status"] = "erro";
	}

	return $registro;
}

function termos_usuario_admin(): bool {
	$nivel = trim(strval($_SESSION["user_tx_nivel"] ?? ""));
	return (bool)preg_match('/(administrador|super\s+admin|adminsitrador)/i', $nivel);
}

function termos_pode_acessar(string $path): bool {
	include_once dirname(__DIR__, 2) . "/check_permission.php";
	if(termos_usuario_admin()){
		return true;
	}
	if(function_exists("temPermissaoMenu")){
		return (bool)temPermissaoMenu($path);
	}
	return false;
}

function termos_verificar_permissao(string $path): void {
	include_once dirname(__DIR__, 2) . "/check_permission.php";
	if(function_exists("verificaPermissao")){
		verificaPermissao($path);
	}
}

function termos_url_relativa(string $caminho): string {
	$partes = explode("/", str_replace("\\", "/", ltrim($caminho, "/\\")));
	$enc = [];
	foreach($partes as $p){
		if($p === ""){
			continue;
		}
		$enc[] = rawurlencode($p);
	}
	return implode("/", $enc);
}

function termos_link_pdf(array $r): string {
	$status = strtolower(trim(strval($r["terg_tx_status"] ?? "")));
	$caminho = trim(strval($r["terg_tx_caminho"] ?? ""));
	if($caminho === ""){
		return "";
	}
	if($status === "assinado"){
		return "../../assinatura/" . termos_url_relativa($caminho);
	}
	if(in_array($status, ["gerado", "notificado", "visualizado"], true)){
		return "../../" . termos_url_relativa($caminho);
	}
	return "";
}

function termos_opcoes_empresas(): array {
	$out = ["" => "Todas"];
	$res = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
	while($res && ($r = mysqli_fetch_assoc($res))){
		$out[intval($r["empr_nb_id"])] = $r["empr_tx_nome"];
	}
	return $out;
}

function termos_opcoes_cargos(): array {
	$out = ["" => "Todos os cargos"];
	$res = query("SELECT oper_nb_id, oper_tx_nome FROM operacao ORDER BY oper_tx_nome ASC");
	while($res && ($r = mysqli_fetch_assoc($res))){
		$out[intval($r["oper_nb_id"])] = $r["oper_tx_nome"];
	}
	return $out;
}

function termos_opcoes_setores(): array {
	$out = ["" => "Todos os setores"];
	$res = query("SELECT grup_nb_id, grup_tx_nome FROM grupos_documentos WHERE grup_tx_status = 'ativo' ORDER BY grup_tx_nome ASC");
	while($res && ($r = mysqli_fetch_assoc($res))){
		$out[intval($r["grup_nb_id"])] = $r["grup_tx_nome"];
	}
	return $out;
}

// Colunas da tabela celular expostas como campos do equipamento nos termos.
// A lista é montada a partir do schema: colunas novas viram opções automaticamente
// (com rótulo amigável para as conhecidas) e colunas removidas deixam de aparecer.
function termos_colunas_ativo(): array {
	global $conn;
	static $cache = null;
	if($cache !== null){
		return $cache;
	}
	$cache = [];

	$rotulos = [
		"celu_tx_nome"               => ["equipamento_nome", "Nome/identificação do equipamento (celular)"],
		"celu_tx_tipo"               => ["equipamento_tipo", "Tipo do equipamento"],
		"celu_tx_marca"              => ["equipamento_marca", "Marca do equipamento"],
		"celu_tx_modelo"             => ["equipamento_modelo", "Modelo do equipamento"],
		"celu_tx_imei"               => ["equipamento_imei", "IMEI 1 do equipamento"],
		"celu_tx_imei2"              => ["equipamento_imei2", "IMEI 2 do equipamento"],
		"celu_tx_numeroSerie"        => ["equipamento_numero_serie", "Número de série do equipamento"],
		"celu_tx_numero"             => ["equipamento_numero", "Número da linha (chip) do equipamento"],
		"celu_tx_operadora"          => ["equipamento_operadora", "Operadora do equipamento"],
		"celu_tx_cimie"              => ["equipamento_iccid", "ICCID do chip do equipamento"],
		"celu_tx_sistemaOperacional" => ["equipamento_sistema_operacional", "Sistema operacional do equipamento"],
		"celu_tx_acessorios"         => ["equipamento_acessorios", "Acessórios entregues do equipamento"],
		"celu_tx_acessoriosOutros"   => ["equipamento_acessorios_outros", "Outros acessórios entregues"],
		"celu_tx_aplicativos"        => ["equipamento_aplicativos", "Aplicativos instalados no equipamento"],
		"celu_tx_aplicativosOutros"  => ["equipamento_aplicativos_outros", "Outros aplicativos instalados"],
		"celu_tx_estadoConservacao"  => ["equipamento_estado_conservacao", "Estado de conservação na entrega"],
		"celu_tx_observacoes"        => ["equipamento_observacoes", "Observações do equipamento"],
		"celu_tx_valorEstimado"      => ["equipamento_valor_estimado", "Valor estimado do bem"],
		"celu_dt_dataEntrega"        => ["equipamento_data_entrega", "Data da entrega do equipamento"],
	];
	$ignorar = ["celu_nb_id", "celu_nb_entidade", "celu_tx_dataCadastro", "celu_tx_dataAtualiza", "celu_tx_marcaModelo"];

	if(termos_tabela_ativo_existe()){
		$res = @mysqli_query($conn, "SHOW COLUMNS FROM celular");
		while($res instanceof mysqli_result && ($c = mysqli_fetch_assoc($res))){
			$coluna = strval($c["Field"] ?? "");
			if($coluna === "" || in_array($coluna, $ignorar, true)){
				continue;
			}
			if(isset($rotulos[$coluna])){
				$token = $rotulos[$coluna][0];
				$rotulo = $rotulos[$coluna][1];
			}else{
				$token = "equipamento_" . termos_slug_coluna($coluna);
				$rotulo = "Equipamento: " . termos_rotulo_coluna($coluna);
			}
			$cache[$token] = ["coluna" => $coluna, "rotulo" => $rotulo];
		}
	}

	$cache["equipamento_responsavel"] = ["coluna" => "", "rotulo" => "Nome do responsável atual pelo equipamento"];
	return $cache;
}

function termos_slug_coluna(string $coluna): string {
	$nome = preg_replace('/^celu_(tx|dt|nb)_/', '', $coluna);
	$nome = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', strval($nome));
	return strtolower(strval($nome));
}

function termos_rotulo_coluna(string $coluna): string {
	$nome = preg_replace('/^celu_(tx|dt|nb)_/', '', $coluna);
	$nome = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', strval($nome));
	return ucfirst(strval($nome));
}

function termos_valor_placeholder_ativo(string $coluna, array $ativo): string {
	$valor = $ativo[$coluna] ?? "";
	if($coluna === "celu_dt_dataEntrega"){
		return termos_h(termos_formatar_data($valor));
	}
	if($coluna === "celu_tx_acessorios"){
		return termos_h(termos_rotular_checklist($valor, opcoesAcessorios()));
	}
	if($coluna === "celu_tx_aplicativos"){
		return termos_h(termos_rotular_checklist($valor, opcoesAplicativos()));
	}
	return termos_h(strval($valor));
}

function termos_rotular_checklist($valor, array $opcoes): string {
	$itens = [];
	foreach(explode(",", strval($valor)) as $chave){
		$chave = trim($chave);
		if($chave === ""){
			continue;
		}
		$itens[] = $opcoes[$chave] ?? $chave;
	}
	return implode(", ", $itens);
}

function termos_lista_placeholders_ativo(): array {
	$lista = [];
	foreach(termos_colunas_ativo() as $token => $info){
		$lista["{{" . $token . "}}"] = strval($info["rotulo"] ?? "");
	}
	return $lista;
}

function termos_lista_placeholders_gerais(): array {
	return [
		"{{empresa_razao}}" => "Razão social da empresa do funcionário",
		"{{empresa_cnpj}}" => "CNPJ da empresa (formatado)",
		"{{empresa_contato}}" => "Contato cadastrado na empresa",
		"{{funcionario_nome}}" => "Nome completo do funcionário",
		"{{funcionario_cpf}}" => "CPF do funcionário (formatado)",
		"{{funcionario_rg}}" => "RG do funcionário",
		"{{funcionario_pis}}" => "PIS do funcionário",
		"{{ctps_numero}}" => "Nº da Carteira de Trabalho",
		"{{ctps_serie}}" => "Série da Carteira de Trabalho",
		"{{ctps_uf}}" => "UF da Carteira de Trabalho",
		"{{admissao}}" => "Data de admissão (dd/mm/aaaa)",
		"{{admissao_extenso}}" => "Data de admissão por extenso",
		"{{cargo}}" => "Cargo do funcionário",
		"{{matricula}}" => "Matrícula",
		"{{cnh_numero}}" => "Nº do registro da CNH",
		"{{cnh_categoria}}" => "Categoria da CNH",
		"{{cnh_validade}}" => "Validade da CNH",
		"{{cidade_assinatura}}" => "Cidade/UF da empresa do funcionário",
		"{{data_atual}}" => "Data atual (dd/mm/aaaa)",
		"{{data_atual_extenso}}" => "Data atual por extenso",
		"{{bloco_assinaturas}}" => "Linhas de assinatura dos signatários configurados",
		"{{email_funcionario}}" => "E-mail do funcionário"
	];
}

function termos_lista_placeholders(): array {
	$lista = array_merge(termos_lista_placeholders_gerais(), termos_lista_placeholders_ativo());
	// Tokens antigos ({{ativo_*}}) continuam reconhecidos em modelos já gravados, mas não aparecem mais no menu.
	foreach(termos_lista_placeholders_ativo() as $token => $desc){
		if(strpos($token, "{{equipamento_") === 0){
			$antigo = "{{ativo_" . substr($token, strlen("{{equipamento_"));
			$lista[$antigo] = $desc . " (campo antigo)";
		}
	}
	$lista["{{ativo_cimie}}"] = "ICCID do chip do equipamento (campo antigo)";
	$lista["{{ativo_marca_modelo}}"] = "Marca e modelo do equipamento (campo antigo)";
	return $lista;
}