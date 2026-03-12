<?php
$interno = true;

if(!isset($_SESSION)) {
	session_start();
}

if(isset($_GET["debug"]) && $_GET["debug"] === "1"){
	ini_set("display_errors", 1);
	ini_set("display_startup_errors", 1);
	error_reporting(E_ALL);
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

function getTmpDir(): string {
	$dir = __DIR__ . "/uploads/tmp/";
	if(!is_dir($dir)){
		mkdir($dir, 0777, true);
	}
	return $dir;
}

function gerarToken(): string {
	return bin2hex(random_bytes(16));
}

function extrairTextoPdfPagina(string $pdfPath, int $page): array {
	if($page <= 0){
		return ["ok" => false, "engine" => null, "text" => "", "error" => "Página inválida"];
	}

	$pdftotext = findCommand(["pdftotext"]);
	if(!$pdftotext){
		return ["ok" => false, "engine" => null, "text" => "", "error" => "pdftotext não encontrado no servidor."];
	}

	$cmd =
		escapeshellarg($pdftotext)
		. " -f " . intval($page)
		. " -l " . intval($page)
		. " -layout -enc UTF-8 "
		. escapeshellarg($pdfPath)
		. " - 2>/dev/null";

	$out = [];
	$code = 1;
	@exec($cmd, $out, $code);
	$text = implode("\n", $out);
	if($code !== 0){
		return ["ok" => false, "engine" => "pdftotext", "text" => "", "error" => "Falha ao extrair texto (exit {$code})."];
	}
	return ["ok" => true, "engine" => "pdftotext", "text" => $text, "error" => null];
}

function apenasDigitos(string $s): string {
	return preg_replace('/\D+/', '', $s) ?? "";
}

function validarCpf(string $cpfDigits): bool {
	$cpf = apenasDigitos($cpfDigits);
	if(strlen($cpf) !== 11){
		return false;
	}
	if(preg_match('/^(\d)\1{10}$/', $cpf)){
		return false;
	}
	$sum = 0;
	for($i = 0, $w = 10; $i < 9; $i++, $w--){
		$sum += intval($cpf[$i]) * $w;
	}
	$mod = $sum % 11;
	$dv1 = ($mod < 2) ? 0 : (11 - $mod);
	if(intval($cpf[9]) !== $dv1){
		return false;
	}
	$sum = 0;
	for($i = 0, $w = 11; $i < 10; $i++, $w--){
		$sum += intval($cpf[$i]) * $w;
	}
	$mod = $sum % 11;
	$dv2 = ($mod < 2) ? 0 : (11 - $mod);
	return intval($cpf[10]) === $dv2;
}

function formatarCpf(string $cpfDigits): string {
	$cpf = apenasDigitos($cpfDigits);
	if(strlen($cpf) !== 11){
		return $cpfDigits;
	}
	return substr($cpf, 0, 3) . "." . substr($cpf, 3, 3) . "." . substr($cpf, 6, 3) . "-" . substr($cpf, 9, 2);
}

function extrairCpfsDoTexto(string $text): array {
	$found = [];

	if(preg_match_all('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', $text, $m)){
		foreach($m[0] as $raw){
			$digits = apenasDigitos($raw);
			$found[$digits] = $raw;
		}
	}

	if(preg_match_all('/\b\d{11}\b/', $text, $m2)){
		foreach($m2[0] as $raw){
			$digits = apenasDigitos($raw);
			$found[$digits] = $raw;
		}
	}

	$out = [];
	foreach($found as $digits => $raw){
		$out[] = [
			"digits" => $digits,
			"formatted" => formatarCpf($digits),
			"valid" => validarCpf($digits),
			"raw" => $raw
		];
	}

	usort($out, function($a, $b){
		if(($a["valid"] ?? false) === ($b["valid"] ?? false)){
			return strcmp(strval($a["digits"] ?? ""), strval($b["digits"] ?? ""));
		}
		return ($a["valid"] ?? false) ? -1 : 1;
	});

	return $out;
}

$status = null;
$message = "";
$resultado = null;
$installHint = "";

if(($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && ($_POST["acao_teste"] ?? "") === "extrair_cpf"){
	$arquivo = $_FILES["pdf"] ?? null;
	if(!$arquivo || ($arquivo["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK){
		$status = "error";
		$message = "Selecione um PDF válido.";
	} else if(!canExec()){
		$status = "error";
		$message = "Este servidor não permite exec(), então não dá para usar pdftotext.";
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
				$pages = contarPaginasPdf($dest);
				$pdftotext = findCommand(["pdftotext"]);
				if(!$pdftotext){
					$status = "error";
					$message = "pdftotext não encontrado no servidor. Sem isso não dá para ler o texto do PDF.";
					$isWindows = (PHP_OS_FAMILY ?? "") === "Windows" || DIRECTORY_SEPARATOR === "\\";
					if($isWindows){
						$installHint = "Windows: instale o Poppler e garanta que o executável 'pdftotext' esteja no PATH do servidor/PHP.";
					} else {
						$installHint =
							"Debian/Ubuntu: sudo apt-get update && sudo apt-get install -y poppler-utils\n"
							. "RHEL/CentOS: sudo yum install -y poppler-utils (ou dnf)\n"
							. "Alpine: sudo apk add poppler-utils";
					}
				} else if($pages <= 0){
					$status = "error";
					$message = "Não foi possível identificar as páginas do PDF.";
				} else {
					$items = [];
					for($p = 1; $p <= $pages; $p++){
						$resText = extrairTextoPdfPagina($dest, $p);
						$text = strval($resText["text"] ?? "");
						$cpfs = extrairCpfsDoTexto($text);
						$best = null;
						foreach($cpfs as $c){
							if(!empty($c["valid"])){
								$best = $c;
								break;
							}
						}
						$items[] = [
							"page" => $p,
							"engine" => $resText["engine"] ?? null,
							"ok" => (bool)($resText["ok"] ?? false),
							"error" => $resText["error"] ?? null,
							"text_len" => strlen($text),
							"cpfs" => $cpfs,
							"best" => $best
						];
					}
					$validCount = 0;
					foreach($items as $it){
						if(is_array($it["best"] ?? null)){
							$validCount++;
						}
					}
					$status = "success";
					$message = "Páginas: {$pages}. Páginas com CPF válido: {$validCount}.";
					$resultado = [
						"name" => $original,
						"pages" => $pages,
						"items" => $items
					];
				}
			}
		}
	}
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Teste - Extrair CPF por página (PDF)</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<div class="max-w-6xl mx-auto px-4 py-8">
	<div class="flex items-center justify-between mb-6">
		<div>
			<h2 class="text-2xl font-bold text-gray-800">Teste - Extrair CPF por página (PDF)</h2>
			<p class="text-sm text-gray-500">Extrai texto de cada página e tenta identificar CPFs válidos.</p>
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
					<div class="font-semibold text-gray-700">exec</div>
					<div class="text-gray-600"><?php echo canExec() ? "Disponível" : "Indisponível (desabilitado)"; ?></div>
				</div>
				<div class="bg-gray-50 border border-gray-100 rounded-lg p-3">
					<div class="font-semibold text-gray-700">pdftotext</div>
					<div class="text-gray-600"><?php echo htmlspecialchars(findCommand(["pdftotext"]) ?? "-"); ?></div>
				</div>
			</div>
		</div>

		<div class="p-6">
			<form method="POST" enctype="multipart/form-data" class="space-y-4">
				<input type="hidden" name="acao_teste" value="extrair_cpf">
				<div>
					<label class="block text-sm font-semibold text-gray-700 mb-2">PDF</label>
					<input type="file" name="pdf" accept="application/pdf" required class="block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
					<p class="text-xs text-gray-500 mt-2">Se o PDF for imagem (scan), pode vir sem texto. Aí só com OCR.</p>
				</div>
				<button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold">
					Testar extração
				</button>
			</form>
		</div>
	</div>

	<?php if($status !== null): ?>
		<div class="<?php echo $status === "success" ? "bg-green-50 border-green-100 text-green-800" : "bg-red-50 border-red-100 text-red-800"; ?> border rounded-lg p-4 mb-6 text-sm">
			<div class="font-bold"><?php echo $status === "success" ? "Sucesso" : "Erro"; ?></div>
			<div class="mt-1"><?php echo htmlspecialchars($message); ?></div>
			<?php if($installHint !== ""): ?>
				<pre class="mt-3 whitespace-pre-wrap text-xs bg-white/70 border border-red-100 rounded p-3 text-red-900"><?php echo htmlspecialchars($installHint); ?></pre>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if(is_array($resultado)): ?>
		<div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
			<div class="p-6 border-b border-gray-100">
				<h3 class="text-lg font-bold text-gray-800">Resultado</h3>
				<p class="text-sm text-gray-500 mt-1">
					Arquivo: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($resultado["name"]); ?></span>
					<span class="mx-2">|</span>
					Páginas: <span class="font-semibold text-gray-700"><?php echo intval($resultado["pages"]); ?></span>
				</p>
			</div>
			<div class="p-6 overflow-x-auto">
				<table class="min-w-full text-sm">
					<thead>
						<tr class="text-left text-gray-500">
							<th class="py-2 pr-4">Página</th>
							<th class="py-2 pr-4">Texto</th>
							<th class="py-2 pr-4">CPF válido</th>
							<th class="py-2 pr-4">CPFs encontrados</th>
							<th class="py-2 pr-4">Erro</th>
						</tr>
					</thead>
					<tbody class="text-gray-700">
						<?php foreach($resultado["items"] as $item): ?>
							<?php
								$page = intval($item["page"]);
								$textLen = intval($item["text_len"] ?? 0);
								$best = $item["best"] ?? null;
								$cpfs = $item["cpfs"] ?? [];
								$err = strval($item["error"] ?? "");
							?>
							<tr class="border-t border-gray-100 align-top">
								<td class="py-2 pr-4 font-semibold"><?php echo $page; ?></td>
								<td class="py-2 pr-4"><?php echo $textLen > 0 ? (number_format($textLen, 0, ",", ".") . " chars") : "sem texto"; ?></td>
								<td class="py-2 pr-4">
									<?php if(is_array($best)): ?>
										<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"><?php echo htmlspecialchars(strval($best["formatted"] ?? "")); ?></span>
									<?php else: ?>
										<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">-</span>
									<?php endif; ?>
								</td>
								<td class="py-2 pr-4">
									<?php if(is_array($cpfs) && count($cpfs) > 0): ?>
										<div class="space-y-1">
											<?php foreach($cpfs as $c): ?>
												<?php
													$badgeCls = !empty($c["valid"]) ? "bg-green-50 text-green-800 border-green-100" : "bg-yellow-50 text-yellow-800 border-yellow-100";
												?>
												<div class="inline-flex items-center gap-2 px-2 py-0.5 rounded border text-xs <?php echo $badgeCls; ?>">
													<span class="font-mono"><?php echo htmlspecialchars(strval($c["formatted"] ?? "")); ?></span>
													<span><?php echo !empty($c["valid"]) ? "válido" : "inválido"; ?></span>
												</div>
											<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="text-xs text-gray-500">nenhum</span>
									<?php endif; ?>
								</td>
								<td class="py-2 pr-4">
									<?php if($err !== ""): ?>
										<span class="text-xs text-red-700"><?php echo htmlspecialchars($err); ?></span>
									<?php else: ?>
										<span class="text-xs text-gray-400">-</span>
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

</body>
</html>
