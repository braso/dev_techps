<?php
$interno = true;
include_once "../conecta.php";

if(!isset($_SESSION)) {
	session_start();
}

function isFunctionDisabled(string $fn): bool {
	$disabled = ini_get("disable_functions");
	if(!$disabled){
		return false;
	}
	$list = array_filter(array_map("trim", explode(",", $disabled)));
	return in_array($fn, $list, true);
}

function canExec(): bool {
	return function_exists("exec") && !isFunctionDisabled("exec");
}

function findCommand(array $candidates): ?string {
	if(!canExec()){
		return null;
	}

	$isWindows = (PHP_OS_FAMILY ?? "") === "Windows" || DIRECTORY_SEPARATOR === "\\";
	foreach($candidates as $cmd){
		$out = [];
		$code = 1;
		if($isWindows){
			@exec("where " . escapeshellarg($cmd), $out, $code);
		} else {
			@exec("command -v " . escapeshellarg($cmd) . " 2>/dev/null", $out, $code);
			if($code !== 0 || empty($out)){
				$out = [];
				$code = 1;
				@exec("which " . escapeshellarg($cmd) . " 2>/dev/null", $out, $code);
			}
		}
		if($code === 0 && !empty($out)){
			$path = trim(strval($out[0]));
			if($path !== ""){
				return $path;
			}
		}
	}
	return null;
}

function contarPaginasPdf(string $path): int {
	if(extension_loaded("imagick")){
		try {
			$im = new Imagick();
			if(method_exists($im, "pingImage")){
				$im->pingImage($path);
			} else {
				$im->readImage($path);
			}
			$n = intval($im->getNumberImages());
			$im->clear();
			$im->destroy();
			return $n > 0 ? $n : 0;
		} catch (Throwable $e) {
		}
	}

	$content = @file_get_contents($path);
	if($content === false){
		return 0;
	}
	if(preg_match_all("/\\/Type\\s*\\/Page(?!s)/", $content, $m) === false){
		return 0;
	}
	return max(0, count($m[0] ?? []));
}

function separarPaginaPdf(string $input, int $page, string $output): array {
	if($page <= 0){
		return ["ok" => false, "engine" => null, "error" => "Página inválida"];
	}

	$imagickError = null;
	if(extension_loaded("imagick")){
		try {
			$im = new Imagick();
			$im->setResolution(150, 150);
			$im->readImage($input . "[" . ($page - 1) . "]");
			$im->setImageFormat("pdf");
			$im->writeImage($output);
			$im->clear();
			$im->destroy();
			if(file_exists($output)){
				return ["ok" => true, "engine" => "imagick", "error" => null];
			}
		} catch (Throwable $e) {
			$imagickError = $e->getMessage();
		}
	}

	$qpdf = findCommand(["qpdf"]);
	if($qpdf){
		$cmd = escapeshellarg($qpdf) . " --empty --pages " . escapeshellarg($input) . " " . intval($page) . " -- " . escapeshellarg($output) . " 2>&1";
		$out = [];
		$code = 1;
		@exec($cmd, $out, $code);
		if($code === 0 && file_exists($output)){
			return ["ok" => true, "engine" => "qpdf", "error" => null];
		}
		return ["ok" => false, "engine" => "qpdf", "error" => implode("\n", $out)];
	}

	$gs = findCommand(["gswin64c", "gswin32c", "gs"]);
	if($gs){
		$cmd =
			escapeshellarg($gs)
			. " -sDEVICE=pdfwrite -dNOPAUSE -dBATCH"
			. " -dFirstPage=" . intval($page)
			. " -dLastPage=" . intval($page)
			. " -sOutputFile=" . escapeshellarg($output)
			. " " . escapeshellarg($input) . " 2>&1";
		$out = [];
		$code = 1;
		@exec($cmd, $out, $code);
		if($code === 0 && file_exists($output)){
			return ["ok" => true, "engine" => "ghostscript", "error" => null];
		}
		return ["ok" => false, "engine" => "ghostscript", "error" => implode("\n", $out)];
	}

	if($imagickError){
		return ["ok" => false, "engine" => "imagick", "error" => $imagickError];
	}
	return ["ok" => false, "engine" => null, "error" => "Sem engine disponível (Imagick, qpdf ou Ghostscript)."];
}

function gerarToken(): string {
	return bin2hex(random_bytes(16));
}

function getTmpDir(): string {
	$dir = __DIR__ . "/uploads/tmp/";
	if(!is_dir($dir)){
		mkdir($dir, 0777, true);
	}
	return $dir;
}

function cleanupOld(array &$sessionTokens): void {
	$now = time();
	foreach($sessionTokens as $t => $info){
		$created = intval($info["created"] ?? 0);
		if($created > 0 && ($now - $created) <= 3600){
			continue;
		}

		$path = strval($info["path"] ?? "");
		if($path !== "" && file_exists($path)){
			@unlink($path);
		}
		$pages = intval($info["pages"] ?? 0);
		$dir = getTmpDir();
		for($p = 1; $p <= $pages; $p++){
			$pagePath = $dir . $t . "_p" . $p . ".pdf";
			if(file_exists($pagePath)){
				@unlink($pagePath);
			}
		}
		unset($sessionTokens[$t]);
	}
}

$_SESSION["split_test_tokens"] = $_SESSION["split_test_tokens"] ?? [];
if(!is_array($_SESSION["split_test_tokens"])){
	$_SESSION["split_test_tokens"] = [];
}
cleanupOld($_SESSION["split_test_tokens"]);

if(isset($_GET["view"]) && isset($_GET["token"]) && isset($_GET["page"])){
	$token = strval($_GET["token"]);
	$page = intval($_GET["page"]);
	if(!preg_match('/^[a-f0-9]{32}$/', $token) || $page <= 0){
		http_response_code(404);
		exit;
	}
	$info = $_SESSION["split_test_tokens"][$token] ?? null;
	if(!is_array($info)){
		http_response_code(404);
		exit;
	}
	$pages = intval($info["pages"] ?? 0);
	if($pages > 0 && $page > $pages){
		http_response_code(404);
		exit;
	}
	$dir = getTmpDir();
	$pagePath = $dir . $token . "_p" . $page . ".pdf";
	if(!file_exists($pagePath)){
		http_response_code(404);
		exit;
	}
	header("Content-Type: application/pdf");
	header("Content-Disposition: inline; filename=\"pagina_" . $page . ".pdf\"");
	header("Cache-Control: no-cache, no-store, must-revalidate");
	header("Pragma: no-cache");
	header("Expires: 0");
	readfile($pagePath);
	exit;
}

$status = null;
$message = "";
$resultado = null;

if(($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && ($_POST["acao_teste"] ?? "") === "separar"){
	$arquivo = $_FILES["pdf"] ?? null;
	if(!$arquivo || ($arquivo["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK){
		$status = "error";
		$message = "Selecione um PDF válido.";
	} else {
		$original = strval($arquivo["name"] ?? "");
		$ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
		if($ext !== "pdf"){
			$status = "error";
			$message = "Apenas PDF é permitido.";
		} else {
			$token = gerarToken();
			$tmp = strval($arquivo["tmp_name"] ?? "");
			$dir = getTmpDir();
			$dest = $dir . $token . ".pdf";
			if(!move_uploaded_file($tmp, $dest)){
				$status = "error";
				$message = "Falha ao salvar o PDF.";
			} else {
				$paginas = contarPaginasPdf($dest);
				if($paginas <= 0){
					@unlink($dest);
					$status = "error";
					$message = "Não foi possível identificar as páginas do PDF.";
				} else {
					$_SESSION["split_test_tokens"][$token] = [
						"path" => $dest,
						"name" => $original,
						"created" => time(),
						"pages" => $paginas
					];

					$pagesResult = [];
					for($p = 1; $p <= $paginas; $p++){
						$out = $dir . $token . "_p" . $p . ".pdf";
						$res = separarPaginaPdf($dest, $p, $out);
						$pagesResult[] = [
							"page" => $p,
							"ok" => (bool)($res["ok"] ?? false),
							"engine" => $res["engine"] ?? null,
							"error" => $res["error"] ?? null,
							"path" => $out
						];
					}

					$okCount = count(array_filter($pagesResult, fn($r) => !empty($r["ok"])));
					$status = $okCount > 0 ? "success" : "error";
					$message = "Páginas detectadas: {$paginas}. Páginas geradas: {$okCount}.";
					$resultado = [
						"token" => $token,
						"name" => $original,
						"pages" => $paginas,
						"items" => $pagesResult
					];
				}
			}
		}
	}
}

include_once "componentes/layout_header.php";
?>

<div class="max-w-5xl mx-auto px-4 py-8">
	<div class="flex items-center justify-between mb-6">
		<div>
			<h2 class="text-2xl font-bold text-gray-800">Teste - Separação de PDF por página</h2>
			<p class="text-sm text-gray-500">Esta página apenas testa a separação do PDF e permite visualizar cada página gerada.</p>
		</div>
		<a href="nova_assinatura.php?modo=separar_paginas" class="text-blue-600 hover:text-blue-800 font-medium flex items-center gap-2">
			<i class="fas fa-arrow-left"></i> Voltar
		</a>
	</div>

	<div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden mb-6">
		<div class="p-6 border-b border-gray-100">
			<h3 class="text-lg font-bold text-gray-800">Ambiente</h3>
			<div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3 text-sm">
				<div class="bg-gray-50 border border-gray-100 rounded-lg p-3">
					<div class="font-semibold text-gray-700">Imagick</div>
					<div class="text-gray-600"><?php echo extension_loaded("imagick") ? "Habilitado" : "Não habilitado"; ?></div>
				</div>
				<div class="bg-gray-50 border border-gray-100 rounded-lg p-3">
					<div class="font-semibold text-gray-700">exec</div>
					<div class="text-gray-600"><?php echo canExec() ? "Disponível" : "Indisponível (desabilitado)"; ?></div>
				</div>
				<div class="bg-gray-50 border border-gray-100 rounded-lg p-3">
					<div class="font-semibold text-gray-700">qpdf</div>
					<div class="text-gray-600"><?php echo htmlspecialchars(findCommand(["qpdf"]) ?? "-"); ?></div>
				</div>
				<div class="bg-gray-50 border border-gray-100 rounded-lg p-3">
					<div class="font-semibold text-gray-700">Ghostscript</div>
					<div class="text-gray-600"><?php echo htmlspecialchars(findCommand(["gswin64c", "gswin32c", "gs"]) ?? "-"); ?></div>
				</div>
			</div>
		</div>

		<div class="p-6">
			<form method="POST" enctype="multipart/form-data" class="space-y-4">
				<input type="hidden" name="acao_teste" value="separar">
				<div>
					<label class="block text-sm font-semibold text-gray-700 mb-2">PDF (múltiplas páginas)</label>
					<input type="file" name="pdf" accept="application/pdf" required class="block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
				</div>
				<button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold">
					Testar separação
				</button>
			</form>
		</div>
	</div>

	<?php if($status !== null): ?>
		<div class="<?php echo $status === "success" ? "bg-green-50 border-green-100 text-green-800" : "bg-red-50 border-red-100 text-red-800"; ?> border rounded-lg p-4 mb-6 text-sm">
			<div class="font-bold"><?php echo $status === "success" ? "Sucesso" : "Erro"; ?></div>
			<div class="mt-1"><?php echo htmlspecialchars($message); ?></div>
		</div>
	<?php endif; ?>

	<?php if(is_array($resultado)): ?>
		<div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
			<div class="p-6 border-b border-gray-100">
				<h3 class="text-lg font-bold text-gray-800">Resultado</h3>
				<p class="text-sm text-gray-500 mt-1">
					Arquivo: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($resultado["name"]); ?></span>
					<span class="mx-2">|</span>
					Token: <span class="font-mono text-xs text-gray-600"><?php echo htmlspecialchars($resultado["token"]); ?></span>
				</p>
			</div>
			<div class="p-6 overflow-x-auto">
				<table class="min-w-full text-sm">
					<thead>
						<tr class="text-left text-gray-500">
							<th class="py-2 pr-4">Página</th>
							<th class="py-2 pr-4">Status</th>
							<th class="py-2 pr-4">Engine</th>
							<th class="py-2 pr-4">Arquivo</th>
							<th class="py-2 pr-4">Ações</th>
						</tr>
					</thead>
					<tbody class="text-gray-700">
						<?php foreach($resultado["items"] as $item): ?>
							<?php
								$page = intval($item["page"]);
								$ok = !empty($item["ok"]);
								$engine = strval($item["engine"] ?? "");
								$path = strval($item["path"] ?? "");
								$size = file_exists($path) ? filesize($path) : 0;
							?>
							<tr class="border-t border-gray-100">
								<td class="py-2 pr-4 font-semibold"><?php echo $page; ?></td>
								<td class="py-2 pr-4">
									<?php if($ok): ?>
										<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">OK</span>
									<?php else: ?>
										<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">ERRO</span>
									<?php endif; ?>
								</td>
								<td class="py-2 pr-4"><?php echo htmlspecialchars($engine !== "" ? $engine : "-"); ?></td>
								<td class="py-2 pr-4"><?php echo $ok ? (number_format($size / 1024, 1, ",", ".") . " KB") : "-"; ?></td>
								<td class="py-2 pr-4">
									<?php if($ok): ?>
										<a class="text-blue-600 hover:text-blue-800 font-semibold" target="_blank" href="teste_separacao_paginas.php?view=1&token=<?php echo htmlspecialchars($resultado["token"]); ?>&page=<?php echo $page; ?>">
											Abrir
										</a>
									<?php else: ?>
										<span class="text-xs text-gray-500"><?php echo htmlspecialchars(strval($item["error"] ?? "")); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>
</div>

<?php
include_once "componentes/layout_footer.php";
?>
