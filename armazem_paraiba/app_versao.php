<?php
	/* ============================================================
	   Versões do App (Android) — publicação de APK para atualização
	   automática dentro do aplicativo.
	   O app consulta GET /ws/app/version e, se houver versionCode maior
	   que o instalado, baixa o APK em /ws/app/<arquivo> e instala.
	   ============================================================ */
	include_once "check_permission.php";
	include_once "load_env.php";
	include_once "utils/utils.php";

	define("APP_VERSAO_DIR", __DIR__ . "/ws/app/");

	function app_versao_garantirTabela(){
		global $conn;
		$r = @mysqli_query($conn, "SHOW TABLES LIKE 'app_versao'");
		if($r && mysqli_num_rows($r) === 0){
			@mysqli_query($conn, "CREATE TABLE app_versao (
				apve_nb_id INT(11) NOT NULL AUTO_INCREMENT,
				apve_nb_versionCode INT(11) NOT NULL,
				apve_tx_versionName VARCHAR(30) NOT NULL,
				apve_tx_arquivo VARCHAR(255) NOT NULL,
				apve_nb_tamanho BIGINT NULL,
				apve_tx_notas TEXT NULL,
				apve_tx_obrigatoria ENUM('sim','nao') NOT NULL DEFAULT 'nao',
				apve_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
				apve_nb_userCadastro INT(11) NULL,
				apve_tx_dataCadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (apve_nb_id),
				KEY idx_apve_status (apve_tx_status, apve_nb_versionCode)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		}
		if(!is_dir(APP_VERSAO_DIR)) @mkdir(APP_VERSAO_DIR, 0775, true);
	}

	function app_versao_ehAdmin(): bool{
		$nivel = strval($_SESSION["user_tx_nivel"] ?? "");
		return (bool)preg_match('/administrador|super\s*admin/i', $nivel);
	}

	function app_versao_url_base(): string{
		$proto = $_SERVER["HTTP_X_FORWARDED_PROTO"] ?? ($_SERVER["REQUEST_SCHEME"] ?? "http");
		$host  = $_SERVER["HTTP_X_FORWARDED_HOST"] ?? ($_SERVER["HTTP_HOST"] ?? "localhost");
		return "{$proto}://{$host}" . rtrim(strval($_ENV["APP_PATH"] ?? ""), "/") . rtrim(strval($_ENV["CONTEX_PATH"] ?? ""), "/") . "/ws/app/";
	}

	// ---------------------------------------------------------------- ações (acao=...)
	function publicarVersao(){
		global $conn;
		app_versao_garantirTabela();
		if(!app_versao_ehAdmin()){ set_status("ERRO: apenas administradores podem publicar versões."); index(); exit; }

		$versionCode = intval($_POST["versionCode"] ?? 0);
		$versionName = trim(strval($_POST["versionName"] ?? ""));
		$notas       = trim(strval($_POST["notas"] ?? ""));
		$obrig       = (($_POST["obrigatoria"] ?? "nao") === "sim") ? "sim" : "nao";

		if($versionCode <= 0 || $versionName === ""){ set_status("ERRO: informe o código e o nome da versão."); index(); exit; }
		$r = query("SELECT apve_nb_versionCode FROM app_versao WHERE apve_nb_versionCode >= ? LIMIT 1", "i", [$versionCode]);
		if($r && mysqli_num_rows($r) > 0){ set_status("ERRO: já existe uma versão com código igual ou maior ({$versionCode}). Use um código maior."); index(); exit; }

		$f = $_FILES["apk"] ?? null;
		if(!$f || ($f["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK){
			$msg = "ERRO: envie o arquivo APK.";
			if(!empty($f["error"]) && in_array($f["error"], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) $msg .= " O arquivo excede o limite de upload do servidor (upload_max_filesize/post_max_size).";
			set_status($msg); index(); exit;
		}
		$ext = strtolower(pathinfo(strval($f["name"]), PATHINFO_EXTENSION));
		if($ext !== "apk"){ set_status("ERRO: o arquivo deve ter extensão .apk."); index(); exit; }
		// Assinatura do ZIP (APK é um ZIP)
		$fh = fopen($f["tmp_name"], "rb"); $magic = $fh ? fread($fh, 2) : ""; if($fh) fclose($fh);
		if($magic !== "PK"){ set_status("ERRO: o arquivo não parece ser um APK válido."); index(); exit; }

		$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', $versionName);
		$arquivo  = "techps_" . $safeName . "_" . $versionCode . ".apk";
		$dest     = APP_VERSAO_DIR . $arquivo;
		if(!move_uploaded_file($f["tmp_name"], $dest)){ set_status("ERRO: falha ao salvar o APK no servidor (permissão da pasta ws/app)."); index(); exit; }
		$tamanho = filesize($dest);

		// Só a versão mais nova fica ativa
		query("UPDATE app_versao SET apve_tx_status = 'inativo'");
		query(
			"INSERT INTO app_versao (apve_nb_versionCode, apve_tx_versionName, apve_tx_arquivo, apve_nb_tamanho, apve_tx_notas, apve_tx_obrigatoria, apve_tx_status, apve_nb_userCadastro)
			 VALUES (?, ?, ?, ?, ?, ?, 'ativo', ?)",
			"ississi",
			[$versionCode, $versionName, $arquivo, $tamanho, $notas, $obrig, intval($_SESSION["user_nb_id"] ?? 0)]
		);
		set_status("Versão {$versionName} (código {$versionCode}) publicada. Os aparelhos receberão o aviso de atualização.");
		index(); exit;
	}

	function alternarVersao(){
		app_versao_garantirTabela();
		if(!app_versao_ehAdmin()){ set_status("ERRO: sem permissão."); index(); exit; }
		$id = intval($_POST["id"] ?? 0);
		$row = $id > 0 ? mysqli_fetch_assoc(query("SELECT * FROM app_versao WHERE apve_nb_id = ?", "i", [$id])) : null;
		if(!$row){ set_status("ERRO: versão não encontrada."); index(); exit; }
		if($row["apve_tx_status"] === "ativo"){
			query("UPDATE app_versao SET apve_tx_status = 'inativo' WHERE apve_nb_id = ?", "i", [$id]);
			set_status("Versão {$row["apve_tx_versionName"]} desativada. O app deixa de oferecer essa atualização.");
		}else{
			query("UPDATE app_versao SET apve_tx_status = 'inativo'");
			query("UPDATE app_versao SET apve_tx_status = 'ativo' WHERE apve_nb_id = ?", "i", [$id]);
			set_status("Versão {$row["apve_tx_versionName"]} ativada.");
		}
		index(); exit;
	}

	function excluirVersao(){
		app_versao_garantirTabela();
		if(!app_versao_ehAdmin()){ set_status("ERRO: sem permissão."); index(); exit; }
		$id = intval($_POST["id"] ?? 0);
		$row = $id > 0 ? mysqli_fetch_assoc(query("SELECT * FROM app_versao WHERE apve_nb_id = ?", "i", [$id])) : null;
		if($row){
			$path = APP_VERSAO_DIR . basename(strval($row["apve_tx_arquivo"]));
			if(is_file($path)) @unlink($path);
			query("DELETE FROM app_versao WHERE apve_nb_id = ?", "i", [$id]);
			set_status("Versão {$row["apve_tx_versionName"]} excluída.");
		}
		index(); exit;
	}

	// ---------------------------------------------------------------- tela
	function index(){
		global $conn;
		app_versao_garantirTabela();
		$ehAdmin = app_versao_ehAdmin();
		$urlBase = app_versao_url_base();
		$ativa = mysqli_fetch_assoc(query("SELECT * FROM app_versao WHERE apve_tx_status = 'ativo' ORDER BY apve_nb_versionCode DESC LIMIT 1")) ?: null;
		$rs = query("SELECT v.*, u.user_tx_login FROM app_versao v LEFT JOIN user u ON u.user_nb_id = v.apve_nb_userCadastro ORDER BY v.apve_nb_versionCode DESC, v.apve_nb_id DESC");
		$proximoCode = $ativa ? intval($ativa["apve_nb_versionCode"]) + 1 : 15;
		$maxUpload = ini_get("upload_max_filesize") . " / " . ini_get("post_max_size");
		$endpoint = rtrim(dirname($urlBase), "/") . "/version";

		cabecalho("Versões do App (Android)");
?>
<style>
.apv-wrap{display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap}
.apv-card{background:#fff;border:1px solid #e2e2e2;border-radius:8px;padding:18px;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:14px}
.apv-form{flex:0 0 380px;min-width:300px}
.apv-list{flex:1;min-width:320px}
.apv-title{font-size:12px;text-transform:uppercase;letter-spacing:.6px;color:#999;margin-bottom:12px}
.apv-card label{font-size:12px;font-weight:600;color:#555;display:block;margin:10px 0 4px}
.apv-card input[type=text],.apv-card input[type=number],.apv-card textarea{width:100%;border:1px solid #ddd;border-radius:4px;padding:7px 10px;font-size:13px}
.apv-card textarea{min-height:90px;resize:vertical}
.apv-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.apv-badge.ativo{background:#e6f7ec;color:#1e7e34;border:1px solid #b7e4c7}
.apv-badge.inativo{background:#f3f3f3;color:#777;border:1px solid #ddd}
.apv-badge.obrig{background:#fdecea;color:#b71c1c;border:1px solid #f5c6cb}
.apv-table{width:100%;border-collapse:collapse;font-size:12.5px}
.apv-table th{background:#f7f7f7;text-align:left;padding:8px;border-bottom:1px solid #e2e2e2;font-size:11px;text-transform:uppercase;color:#777}
.apv-table td{padding:8px;border-bottom:1px solid #f0f0f0;vertical-align:top}
.apv-atual{background:#eef6fb;border:1px solid #cfe4f3;border-radius:6px;padding:12px 14px;margin-bottom:14px;font-size:13px}
.apv-atual b{font-size:15px}
.apv-help{font-size:11.5px;color:#888;margin-top:6px;line-height:1.4}
.apv-btn{border:0;border-radius:4px;padding:8px 14px;font-size:13px;font-weight:600;color:#fff;background:#3c8dbc;cursor:pointer}
.apv-btn.sec{background:#888;padding:4px 9px;font-size:11px}
.apv-btn.danger{background:#c0392b;padding:4px 9px;font-size:11px}
code.apv-code{background:#f4f4f4;border:1px solid #e5e5e5;border-radius:3px;padding:1px 5px;font-size:11.5px;word-break:break-all}
</style>

<div class="apv-wrap">
	<div class="apv-form">
		<div class="apv-card">
			<div class="apv-title"><i class="fa fa-cloud-upload"></i> Publicar nova versão</div>
			<?php if(!$ehAdmin): ?>
				<div class="apv-help">Apenas administradores podem publicar versões.</div>
			<?php else: ?>
			<form method="post" enctype="multipart/form-data" onsubmit="return apvValidar(this);">
				<input type="hidden" name="acao" value="publicarVersao">
				<label>Código da versão (versionCode)*</label>
				<input type="number" name="versionCode" min="1" value="<?php echo $proximoCode; ?>" required>
				<div class="apv-help">Número inteiro, sempre maior que o da versão anterior. É por ele que o Android decide instalar a atualização.</div>
				<label>Nome da versão*</label>
				<input type="text" name="versionName" placeholder="ex.: 1.1.015" value="" required>
				<label>Arquivo APK*</label>
				<input type="file" name="apk" accept=".apk,application/vnd.android.package-archive" required>
				<div class="apv-help">Limite de upload do servidor: <?php echo htmlspecialchars($maxUpload); ?>. O APK deve ser assinado com a mesma chave das versões anteriores.</div>
				<label>Notas da atualização</label>
				<textarea name="notas" placeholder="O que mudou nesta versão (aparece no aviso dentro do app)"></textarea>
				<label style="display:flex;align-items:center;gap:8px;font-weight:normal">
					<input type="checkbox" name="obrigatoria" value="sim"> Atualização obrigatória (o app não deixa continuar sem atualizar)
				</label>
				<div style="margin-top:14px"><button type="submit" class="apv-btn"><i class="fa fa-upload"></i> Publicar versão</button></div>
			</form>
			<?php endif; ?>
		</div>
		<div class="apv-card">
			<div class="apv-title"><i class="fa fa-info-circle"></i> Como funciona</div>
			<div class="apv-help" style="font-size:12px;color:#666">
				1. O app consulta <code class="apv-code"><?php echo htmlspecialchars($endpoint); ?></code> ao abrir e ao entrar.<br>
				2. Se o código publicado for maior que o instalado, mostra o aviso com as notas.<br>
				3. O funcionário toca em Atualizar: o APK é baixado e o instalador do Android abre.<br>
				4. Na primeira vez o Android pede permissão para "instalar apps desconhecidos" para o TechPS.
			</div>
		</div>
	</div>

	<div class="apv-list">
		<div class="apv-card">
			<div class="apv-title"><i class="fa fa-mobile"></i> Versão que o app está recebendo</div>
			<?php if($ativa): ?>
				<div class="apv-atual">
					<b>v<?php echo htmlspecialchars($ativa["apve_tx_versionName"]); ?></b> &nbsp;(código <?php echo intval($ativa["apve_nb_versionCode"]); ?>)
					<?php if($ativa["apve_tx_obrigatoria"] === "sim"): ?><span class="apv-badge obrig">obrigatória</span><?php endif; ?><br>
					<span style="color:#666">Publicada em <?php echo date("d/m/Y H:i", strtotime($ativa["apve_tx_dataCadastro"])); ?> &middot; <?php echo number_format(intval($ativa["apve_nb_tamanho"]) / 1048576, 1, ",", "."); ?> MB</span><br>
					<a href="<?php echo htmlspecialchars($urlBase . $ativa["apve_tx_arquivo"]); ?>" target="_blank" style="font-size:12px"><i class="fa fa-download"></i> Baixar APK</a>
				</div>
			<?php else: ?>
				<div class="apv-help">Nenhuma versão ativa. Publique a primeira versão ao lado.</div>
			<?php endif; ?>

			<div class="apv-title" style="margin-top:8px">Histórico</div>
			<table class="apv-table">
				<thead><tr><th>Versão</th><th>Código</th><th>Publicação</th><th>Tamanho</th><th>Status</th><th>Notas</th><th></th></tr></thead>
				<tbody>
				<?php $tem = false; while($rs && ($v = mysqli_fetch_assoc($rs))): $tem = true; ?>
					<tr>
						<td><b>v<?php echo htmlspecialchars($v["apve_tx_versionName"]); ?></b><?php if($v["apve_tx_obrigatoria"] === "sim"): ?> <span class="apv-badge obrig">obrig.</span><?php endif; ?></td>
						<td><?php echo intval($v["apve_nb_versionCode"]); ?></td>
						<td><?php echo date("d/m/Y H:i", strtotime($v["apve_tx_dataCadastro"])); ?><br><small style="color:#999"><?php echo htmlspecialchars($v["user_tx_login"] ?? ""); ?></small></td>
						<td><?php echo number_format(intval($v["apve_nb_tamanho"]) / 1048576, 1, ",", "."); ?> MB</td>
						<td><span class="apv-badge <?php echo $v["apve_tx_status"]; ?>"><?php echo $v["apve_tx_status"]; ?></span></td>
						<td style="max-width:260px;white-space:pre-wrap"><?php echo nl2br(htmlspecialchars($v["apve_tx_notas"] ?? "")); ?></td>
						<td style="white-space:nowrap">
							<a class="apv-btn sec" style="text-decoration:none" href="<?php echo htmlspecialchars($urlBase . $v["apve_tx_arquivo"]); ?>" target="_blank"><i class="fa fa-download"></i></a>
							<?php if($ehAdmin): ?>
							<form method="post" style="display:inline" onsubmit="return confirm('<?php echo $v["apve_tx_status"] === "ativo" ? "Desativar" : "Ativar"; ?> a versão v<?php echo htmlspecialchars($v["apve_tx_versionName"]); ?>?');">
								<input type="hidden" name="acao" value="alternarVersao"><input type="hidden" name="id" value="<?php echo intval($v["apve_nb_id"]); ?>">
								<button type="submit" class="apv-btn sec"><?php echo $v["apve_tx_status"] === "ativo" ? "Desativar" : "Ativar"; ?></button>
							</form>
							<form method="post" style="display:inline" onsubmit="return confirm('Excluir a versão v<?php echo htmlspecialchars($v["apve_tx_versionName"]); ?> e o arquivo APK?');">
								<input type="hidden" name="acao" value="excluirVersao"><input type="hidden" name="id" value="<?php echo intval($v["apve_nb_id"]); ?>">
								<button type="submit" class="apv-btn danger"><i class="fa fa-trash"></i></button>
							</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endwhile; if(!$tem): ?>
					<tr><td colspan="7" style="color:#999;text-align:center;padding:18px">Nenhuma versão publicada.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<script>
function apvValidar(f){
	var code = parseInt(f.versionCode.value || '0', 10);
	if(!(code > 0)){ alert('Informe o código da versão.'); return false; }
	if(!f.versionName.value.trim()){ alert('Informe o nome da versão.'); return false; }
	if(!f.apk.files || !f.apk.files.length){ alert('Selecione o arquivo APK.'); return false; }
	var b = f.querySelector('button[type=submit]'); if(b){ b.disabled = true; b.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Enviando...'; }
	return true;
}
</script>
<?php
		rodape();
	}

	include_once "conecta.php";
