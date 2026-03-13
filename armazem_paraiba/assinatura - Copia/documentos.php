<?php
include_once "../conecta.php";
include_once "componentes/layout_header.php";

$funcionarioId = intval($_GET["funcionario"] ?? 0);

$funcionarios = [];
$resFunc = mysqli_query(
	$conn,
	"SELECT
		e.enti_nb_id,
		e.enti_tx_nome,
		e.enti_tx_email,
		e.enti_tx_cpf,
		e.enti_tx_matricula,
		COUNT(df.docu_nb_id) as total_docs
	FROM documento_funcionario df
	JOIN entidade e ON e.enti_nb_id = df.docu_nb_entidade
	GROUP BY e.enti_nb_id, e.enti_tx_nome, e.enti_tx_email, e.enti_tx_cpf, e.enti_tx_matricula
	ORDER BY e.enti_tx_nome ASC"
);
if($resFunc){
	while($r = mysqli_fetch_assoc($resFunc)){
		$id = intval($r["enti_nb_id"] ?? 0);
		$nome = trim(strval($r["enti_tx_nome"] ?? ""));
		if($id <= 0 || $nome === ""){
			continue;
		}
		$email = trim(strval($r["enti_tx_email"] ?? ""));
		$cpf = trim(strval($r["enti_tx_cpf"] ?? ""));
		$mat = trim(strval($r["enti_tx_matricula"] ?? ""));
		$total = intval($r["total_docs"] ?? 0);
		$label = $nome;
		if($cpf !== ""){ $label .= " | CPF: " . $cpf; }
		if($mat !== ""){ $label .= " | Mat: " . $mat; }
		if($email !== ""){ $label .= " | " . $email; }
		$label .= " (" . $total . ")";
		$funcionarios[] = ["id" => $id, "label" => $label];
	}
}

$docs = [];
$dbPaths = [];
$pastaAbs = "";
$pastaExists = false;
$arquivosSemCadastro = [];

if($funcionarioId > 0){
	$stmt = mysqli_prepare(
		$conn,
		"SELECT
			df.*,
			t.tipo_tx_nome as tipo_nome,
			u.user_tx_login as usuario_login
		FROM documento_funcionario df
		LEFT JOIN tipos_documentos t ON t.tipo_nb_id = df.docu_tx_tipo
		LEFT JOIN user u ON u.user_nb_id = df.docu_tx_usuarioCadastro
		WHERE df.docu_nb_entidade = ?
		ORDER BY df.docu_tx_dataCadastro DESC, df.docu_nb_id DESC"
	);
	if($stmt){
		mysqli_stmt_bind_param($stmt, "i", $funcionarioId);
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		while($row = mysqli_fetch_assoc($res)){
			$docs[] = $row;
			$path = trim(strval($row["docu_tx_caminho"] ?? ""));
			if($path !== ""){
				$dbPaths[$path] = true;
				$dbPaths[ltrim($path, "/\\")] = true;
			}
		}
		mysqli_stmt_close($stmt);
	}

	$root = dirname(__DIR__);
	$pastaAbs = rtrim(str_replace("\\", "/", $root), "/") . "/arquivos/Funcionarios/" . $funcionarioId . "/";
	$pastaExists = is_dir($pastaAbs);
	if($pastaExists){
		$files = @scandir($pastaAbs);
		if(is_array($files)){
			foreach($files as $f){
				if($f === "." || $f === ".."){
					continue;
				}
				$abs = $pastaAbs . $f;
				if(!is_file($abs)){
					continue;
				}
				$rel = "arquivos/Funcionarios/" . $funcionarioId . "/" . $f;
				if(isset($dbPaths[$rel]) || isset($dbPaths[ltrim($rel, "/\\")])){
					continue;
				}
				$arquivosSemCadastro[] = [
					"basename" => $f,
					"rel" => $rel,
					"size" => @filesize($abs) ?: 0,
					"mtime" => @filemtime($abs) ?: 0
				];
			}
		}
		usort($arquivosSemCadastro, function($a, $b){
			return intval($b["mtime"] ?? 0) <=> intval($a["mtime"] ?? 0);
		});
	}
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
	<div class="mb-8 flex flex-col md:flex-row justify-between items-center gap-4">
		<div>
			<h1 class="text-2xl font-bold text-gray-800">Documentos do Funcionário</h1>
			<p class="text-gray-500">Arquivos da pasta do funcionário e registros do cadastro</p>
		</div>
		<div class="flex gap-2">
			<a href="index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">
				<i class="fas fa-arrow-left mr-2"></i>Voltar
			</a>
		</div>
	</div>

	<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
		<form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
			<div class="md:col-span-8">
				<label class="block text-sm font-medium text-gray-700 mb-1">Funcionário</label>
				<select id="filtro_funcionario_documentos" name="funcionario" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
					<option value="">Selecione</option>
					<?php foreach($funcionarios as $f): ?>
						<option value="<?php echo intval($f["id"]); ?>" <?php echo ($funcionarioId === intval($f["id"])) ? "selected" : ""; ?>>
							<?php echo htmlspecialchars($f["label"]); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="md:col-span-4 flex gap-2">
				<button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
					Buscar
				</button>
				<a href="documentos.php" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600" title="Limpar">
					<i class="fas fa-times"></i>
				</a>
			</div>
		</form>
	</div>

	<?php if($funcionarioId <= 0): ?>
		<div class="bg-blue-50 border border-blue-100 text-blue-900 rounded-xl p-6">
			Selecione um funcionário para listar os documentos.
		</div>
	<?php else: ?>
		<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
			<div class="bg-white rounded-xl border border-gray-200 p-4">
				<div class="text-xs text-gray-500">Registros no cadastro</div>
				<div class="text-2xl font-bold text-gray-900"><?php echo count($docs); ?></div>
			</div>
			
			
		</div>

		<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
			<div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
				<div class="font-semibold text-gray-800">Documentos (Cadastro)</div>
				<div class="text-xs text-gray-500"><?php echo count($docs); ?> itens</div>
			</div>
			<div class="overflow-x-auto">
				<table class="w-full text-left border-collapse">
					<thead>
						<tr class="bg-white border-b border-gray-200 text-xs uppercase text-gray-500 font-semibold tracking-wider">
							<th class="px-6 py-4">Documento</th>
							<th class="px-6 py-4">Tipo</th>
							<th class="px-6 py-4">Cadastro</th>
							<th class="px-6 py-4">Status</th>
							<th class="px-6 py-4">Origem</th>
							<th class="px-6 py-4 text-right">Ações</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<?php if(empty($docs)): ?>
							<tr>
								<td colspan="6" class="px-6 py-12 text-center text-gray-500">
									Nenhum documento cadastrado para este funcionário.
								</td>
							</tr>
						<?php else: ?>
							<?php foreach($docs as $row): ?>
								<?php
									$nome = trim(strval($row["docu_tx_nome"] ?? ""));
									$desc = trim(strval($row["docu_tx_descricao"] ?? ""));
									$tipoNome = trim(strval($row["tipo_nome"] ?? ""));
									$dataCadastro = trim(strval($row["docu_tx_dataCadastro"] ?? ""));
									$dataVenc = trim(strval($row["docu_tx_dataVencimento"] ?? ""));
									$assinado = strtolower(trim(strval($row["docu_tx_assinado"] ?? "nao")));
									$visivel = strtolower(trim(strval($row["docu_tx_visivel"] ?? "nao")));
									$caminho = trim(strval($row["docu_tx_caminho"] ?? ""));
									$usuarioId = intval($row["docu_tx_usuarioCadastro"] ?? 0);
									$usuarioLogin = trim(strval($row["usuario_login"] ?? ""));

									$origem = "Manual";
									if($usuarioId <= 0){
										$origem = "Sistema";
										if($assinado === "sim" && $desc !== "" && stripos($desc, "assinado eletronicamente") !== false){
											$origem = "Assinatura";
										}
									}

									$statusTxt = $assinado === "sim" ? "Assinado" : "Pendente";
									$statusClass = $assinado === "sim" ? "bg-green-100 text-green-800 border-green-200" : "bg-yellow-50 text-yellow-800 border-yellow-200";

									$link = "";
									if($caminho !== ""){
										$link = rtrim($baseContex, "/") . "/" . ltrim(str_replace("\\", "/", $caminho), "/");
									}
								?>
								<tr class="hover:bg-gray-50 transition-colors">
									<td class="px-6 py-4">
										<div class="flex items-start gap-3">
											<div class="bg-blue-50 text-blue-600 h-10 w-10 rounded-lg flex items-center justify-center flex-shrink-0">
												<i class="far fa-file"></i>
											</div>
											<div>
												<div class="font-medium text-gray-900"><?php echo htmlspecialchars($nome !== "" ? $nome : "(Sem nome)"); ?></div>
												<?php if($desc !== ""): ?>
													<div class="text-xs text-gray-500 mt-0.5 max-w-md"><?php echo htmlspecialchars($desc); ?></div>
												<?php endif; ?>
												<?php if($dataVenc !== "" && $dataVenc !== "0000-00-00" && $dataVenc !== "0000-00-00 00:00:00"): ?>
													<div class="text-xs text-gray-500 mt-0.5">Venc.: <?php echo htmlspecialchars(date("d/m/Y", strtotime($dataVenc))); ?></div>
												<?php endif; ?>
											</div>
										</div>
									</td>
									<td class="px-6 py-4 text-sm text-gray-700">
										<?php echo htmlspecialchars($tipoNome !== "" ? $tipoNome : "—"); ?>
									</td>
									<td class="px-6 py-4 text-sm text-gray-700">
										<?php echo $dataCadastro !== "" ? htmlspecialchars(date("d/m/Y H:i", strtotime($dataCadastro))) : "—"; ?>
									</td>
									<td class="px-6 py-4">
										<div class="flex flex-col gap-1">
											<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $statusClass; ?>">
												<?php echo htmlspecialchars($statusTxt); ?>
											</span>
											<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border <?php echo $visivel === "sim" ? "bg-gray-50 text-gray-700 border-gray-200" : "bg-red-50 text-red-700 border-red-200"; ?>">
												<?php echo $visivel === "sim" ? "Visível" : "Oculto"; ?>
											</span>
										</div>
									</td>
									<td class="px-6 py-4 text-sm text-gray-700">
										<div class="font-semibold"><?php echo htmlspecialchars($origem); ?></div>
										<?php if($usuarioId > 0): ?>
											<div class="text-xs text-gray-500"><?php echo htmlspecialchars($usuarioLogin !== "" ? $usuarioLogin : ("User #" . $usuarioId)); ?></div>
										<?php endif; ?>
									</td>
									<td class="px-6 py-4 text-right">
										<?php if($link !== ""): ?>
											<?php $downloadName = $caminho !== "" ? basename(str_replace("\\", "/", $caminho)) : "documento"; ?>
											<div class="inline-flex items-center justify-end gap-4">
												<a href="<?php echo htmlspecialchars($link); ?>" target="_blank" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700 font-semibold text-sm">
													<i class="fas fa-eye"></i> Abrir
												</a>
												<a href="<?php echo htmlspecialchars($link); ?>" download="<?php echo htmlspecialchars($downloadName); ?>" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-800 font-semibold text-sm">
													<i class="fas fa-download"></i> Baixar
												</a>
											</div>
										<?php else: ?>
											<span class="text-sm text-gray-400">—</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		
			
	<?php endif; ?>
</div>

<?php
$baseAssets = rtrim(strval($baseContex ?? ""), "/");
if (isset($hasEnvPaths) && $hasEnvPaths) {
	$baseAssets = rtrim(strval($urlBase ?? "") . strval($_ENV["APP_PATH"] ?? ""), "/");
} else {
	$pos = strrpos($baseAssets, "/");
	$baseAssets = $pos !== false ? substr($baseAssets, 0, $pos) : $baseAssets;
}
?>
<link rel="stylesheet" href="<?php echo $baseAssets; ?>/contex20/assets/global/plugins/select2/css/select2.css">
<style>
	.select2-container{width:100%!important}
	.select2-container--default .select2-selection--single{height:42px;border-color:#d1d5db;border-radius:.5rem;background-color:#fff}
	.select2-container--default .select2-selection--single .select2-selection__rendered{line-height:42px;padding-left:.75rem;padding-right:2.5rem;color:#111827}
	.select2-container--default .select2-selection--single .select2-selection__arrow{height:42px;right:.5rem}
</style>
<script src="<?php echo $baseAssets; ?>/contex20/assets/global/plugins/jquery.min.js"></script>
<script src="<?php echo $baseAssets; ?>/contex20/assets/global/plugins/select2/js/select2.min.js"></script>
<script src="<?php echo $baseAssets; ?>/contex20/assets/global/plugins/select2/js/i18n/pt-BR.js"></script>
<script>
	if(window.jQuery && jQuery.fn && typeof jQuery.fn.select2 === "function"){
		const $func = jQuery("#filtro_funcionario_documentos");
		if($func.length){
			$func.select2({
				placeholder: "Selecione",
				allowClear: true,
				width: "100%",
				language: "pt-BR",
				minimumResultsForSearch: 0,
				dropdownParent: jQuery("body")
			});
		}
	}
</script>

<?php include_once "componentes/layout_footer.php"; ?>
