<?php
include_once "../conecta.php";
include_once "componentes/layout_header.php";

$funcionarioId = intval($_GET["funcionario"] ?? 0);
$tab = strtolower(trim(strval($_GET["tab"] ?? "")));
$tab = in_array($tab, ["todos", "assinados", "arquivo"], true) ? $tab : "todos";
$filtro_status_assinado = strtolower(trim(strval($_GET["assinado"] ?? "")));
$filtro_status_assinado = in_array($filtro_status_assinado, ["sim", "nao"], true) ? $filtro_status_assinado : "";
$filtro_data_inicio = trim(strval($_GET["data_inicio"] ?? ""));
$filtro_data_fim = trim(strval($_GET["data_fim"] ?? ""));
$filtro_tipo_documento = intval($_GET["tipo_documento"] ?? 0);
$busca = trim(strval($_GET["busca"] ?? ""));

$effective_status_assinado = $filtro_status_assinado;
if($tab === "assinados"){
	$effective_status_assinado = "sim";
}
if($tab === "arquivo"){
	$effective_status_assinado = "nao";
}

$tiposDocumentos = [];
$resTipos = mysqli_query($conn, "SELECT tipo_nb_id, tipo_tx_nome FROM tipos_documentos WHERE tipo_tx_status = 'ativo' ORDER BY tipo_tx_nome ASC");
if($resTipos){
	while($r = mysqli_fetch_assoc($resTipos)){
		$tid = intval($r["tipo_nb_id"] ?? 0);
		$tn = trim(strval($r["tipo_tx_nome"] ?? ""));
		if($tid > 0 && $tn !== ""){
			$tiposDocumentos[] = ["id" => $tid, "nome" => $tn];
		}
	}
}

$funcionarios = [];
$resFunc = mysqli_query(
	$conn,
	"SELECT DISTINCT
		e.enti_nb_id,
		e.enti_tx_nome,
		e.enti_setor_id,
		g.grup_tx_nome as setor_nome
	FROM entidade e
	LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
	WHERE COALESCE(NULLIF(TRIM(e.enti_tx_nome),''),'') <> ''
	ORDER BY e.enti_tx_nome ASC"
);
if($resFunc){
	while($r = mysqli_fetch_assoc($resFunc)){
		$id = intval($r["enti_nb_id"] ?? 0);
		$nome = trim(strval($r["enti_tx_nome"] ?? ""));
		if($id <= 0 || $nome === ""){
			continue;
		}
		$setor = trim(strval($r["setor_nome"] ?? ""));
		$label = $nome . " | " . ($setor !== "" ? $setor : "—");
		$funcionarios[] = ["id" => $id, "label" => $label];
	}
}

$docs = [];
if($funcionarioId > 0){
	$where = [];
	$types = "";
	$vars = [];

	$where[] = "df.docu_nb_entidade = ?";
	$types .= "i";
	$vars[] = $funcionarioId;

	if($effective_status_assinado !== ""){
		$where[] = "LOWER(COALESCE(df.docu_tx_assinado,'nao')) = ?";
		$types .= "s";
		$vars[] = $effective_status_assinado;
	}
	if($filtro_tipo_documento > 0){
		$where[] = "df.docu_tx_tipo = ?";
		$types .= "i";
		$vars[] = $filtro_tipo_documento;
	}
	if($filtro_data_inicio !== ""){
		$where[] = "DATE(df.docu_tx_dataCadastro) >= ?";
		$types .= "s";
		$vars[] = $filtro_data_inicio;
	}
	if($filtro_data_fim !== ""){
		$where[] = "DATE(df.docu_tx_dataCadastro) <= ?";
		$types .= "s";
		$vars[] = $filtro_data_fim;
	}
	if($busca !== ""){
		$where[] = "(df.docu_tx_nome LIKE ? OR df.docu_tx_descricao LIKE ? OR df.docu_tx_caminho LIKE ?)";
		$types .= "sss";
		$like = "%" . $busca . "%";
		$vars[] = $like;
		$vars[] = $like;
		$vars[] = $like;
	}

	$whereSql = !empty($where) ? ("WHERE " . implode(" AND ", $where)) : "";
	$docs = mysqli_fetch_all(query(
		"SELECT
			df.*,
			t.tipo_tx_nome as tipo_nome,
			u.user_tx_login as usuario_login,
			e.enti_tx_nome as funcionario_nome,
			e.enti_setor_id,
			g.grup_tx_nome as setor_nome
		FROM documento_funcionario df
		JOIN entidade e ON e.enti_nb_id = df.docu_nb_entidade
		LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
		LEFT JOIN tipos_documentos t ON t.tipo_nb_id = df.docu_tx_tipo
		LEFT JOIN user u ON u.user_nb_id = df.docu_tx_usuarioCadastro
		{$whereSql}
		ORDER BY df.docu_tx_dataCadastro DESC, df.docu_nb_id DESC",
		$types,
		$vars
	), MYSQLI_ASSOC) ?: [];
}

$arquivosSemCadastro = [];
if($funcionarioId > 0 && $tab !== "assinados"){
	$dbPaths = [];
	$resPaths = query("SELECT docu_tx_caminho FROM documento_funcionario WHERE docu_nb_entidade = ?", "i", [$funcionarioId]);
	while($resPaths && ($r = mysqli_fetch_assoc($resPaths))){
		$path = trim(strval($r["docu_tx_caminho"] ?? ""));
		if($path !== ""){
			$dbPaths[$path] = true;
			$dbPaths[ltrim($path, "/\\")] = true;
		}
	}

	$root = dirname(__DIR__);
	$pastaAbs = rtrim(str_replace("\\", "/", $root), "/") . "/arquivos/Funcionarios/" . $funcionarioId . "/";
	if(is_dir($pastaAbs)){
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
				$mtime = @filemtime($abs) ?: 0;
				if($filtro_data_inicio !== "" || $filtro_data_fim !== ""){
					$dt = $mtime > 0 ? date("Y-m-d", $mtime) : "";
					if($filtro_data_inicio !== "" && $dt !== "" && $dt < $filtro_data_inicio){
						continue;
					}
					if($filtro_data_fim !== "" && $dt !== "" && $dt > $filtro_data_fim){
						continue;
					}
				}
				$arquivosSemCadastro[] = [
					"basename" => $f,
					"rel" => $rel,
					"size" => @filesize($abs) ?: 0,
					"mtime" => $mtime
				];
			}
		}
		usort($arquivosSemCadastro, function($a, $b){
			return intval($b["mtime"] ?? 0) <=> intval($a["mtime"] ?? 0);
		});
	}
}

$totalDb = count($docs);
$totalPasta = count($arquivosSemCadastro);
$showFuncCol = false;
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
	<div class="mb-8 flex flex-col md:flex-row justify-between items-center gap-4">
		<div>
			<h1 class="text-2xl font-bold text-gray-800">Documentos dos Funcionários</h1>
			<p class="text-gray-500">Lista documentos do cadastro e, ao selecionar um funcionário, também os arquivos em <span class="font-semibold">arquivos/Funcionarios</span></p>
		</div>
		<div class="flex gap-2">
			<a href="index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">
				<i class="fas fa-arrow-left mr-2"></i>Voltar
			</a>
		</div>
	</div>

	<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
		<form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
			<div class="md:col-span-4">
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

			<div class="md:col-span-3">
				<label class="block text-sm font-medium text-gray-700 mb-1">Documentos</label>
				<select name="tab" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
					<option value="todos" <?php echo $tab === "todos" ? "selected" : ""; ?>>Todos os Documentos</option>
					<option value="assinados" <?php echo $tab === "assinados" ? "selected" : ""; ?>>Documentos Assinados</option>
					<option value="arquivo" <?php echo $tab === "arquivo" ? "selected" : ""; ?>>Documentos (Arquivo do Funcionário)</option>
				</select>
			</div>

			<div class="md:col-span-2">
				<label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
				<?php if($tab === "assinados" || $tab === "arquivo"): ?>
					<input type="hidden" name="assinado" value="<?php echo $tab === "assinados" ? "sim" : "nao"; ?>">
				<?php endif; ?>
				<select name="assinado" <?php echo ($tab === "assinados" || $tab === "arquivo") ? "disabled" : ""; ?> class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
					<option value="">Todos</option>
					<option value="sim" <?php echo ($tab === "assinados" || $filtro_status_assinado === "sim") ? "selected" : ""; ?>>Assinado</option>
					<option value="nao" <?php echo ($tab === "arquivo" || ($tab === "todos" && $filtro_status_assinado === "nao")) ? "selected" : ""; ?>>Pendente</option>
				</select>
			</div>

			<div class="md:col-span-3">
				<label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
				<select name="tipo_documento" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
					<option value="">Todos</option>
					<?php foreach($tiposDocumentos as $t): ?>
						<option value="<?php echo intval($t["id"]); ?>" <?php echo ($filtro_tipo_documento === intval($t["id"])) ? "selected" : ""; ?>>
							<?php echo htmlspecialchars($t["nome"]); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="md:col-span-4">
				<label class="block text-sm font-medium text-gray-700 mb-1">Período</label>
				<div class="grid grid-cols-2 gap-2">
					<div>
						<div class="text-[11px] font-semibold text-gray-500 mb-1">Início</div>
						<input type="date" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
					</div>
					<div>
						<div class="text-[11px] font-semibold text-gray-500 mb-1">Fim</div>
						<input type="date" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
					</div>
				</div>
			</div>

			<div class="md:col-span-5">
				<label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
				<input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Nome, descrição ou caminho..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
			</div>

			<div class="md:col-span-3 flex gap-2">
				<button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
					Filtrar
				</button>
				<a href="documentos.php" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600" title="Limpar">
					<i class="fas fa-times"></i>
				</a>
			</div>
		</form>
	</div>

	<?php if($funcionarioId <= 0): ?>
		<div class="bg-blue-50 border border-blue-100 text-blue-900 rounded-xl p-6">
			Selecione um funcionário e clique em <span class="font-semibold">Filtrar</span> para listar todos os documentos dele.
		</div>
	<?php else: ?>
	<div class="grid grid-cols-1 <?php echo $tab === "assinados" ? "md:grid-cols-1" : "md:grid-cols-3"; ?> gap-4 mb-6">
		<div class="bg-white rounded-xl border border-gray-200 p-4">
			<div class="text-xs text-gray-500">Registros no cadastro com assinatura</div>
			<div class="text-2xl font-bold text-gray-900"><?php echo $totalDb; ?></div>
		</div>
		<?php if($tab !== "assinados"): ?>
			<div class="bg-white rounded-xl border border-gray-200 p-4">
				<div class="text-xs text-gray-500">Arquivos (Pasta do Funcionário)</div>
				<div class="text-2xl font-bold text-gray-900"><?php echo $totalPasta; ?></div>
				<div class="text-xs text-gray-500 mt-1">arquivos cadastrados sem assinatura</div>
			</div>
			<div class="bg-white rounded-xl border border-gray-200 p-4">
				<div class="text-xs text-gray-500">Total</div>
				<div class="text-2xl font-bold text-gray-900"><?php echo ($totalDb + $totalPasta); ?></div>
			</div>
		<?php endif; ?>
	</div>

	<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
		<div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
			<div class="font-semibold text-gray-800"><?php echo $tab === "assinados" ? "Documentos Assinados" : ($tab === "arquivo" ? "Documentos (Cadastro - Não Assinados)" : "Documentos (Cadastro)"); ?></div>
			<div class="text-xs text-gray-500"><?php echo $totalDb; ?> itens</div>
		</div>
		<div class="overflow-x-auto">
			<table class="w-full text-left border-collapse">
				<thead>
					<tr class="bg-white border-b border-gray-200 text-xs uppercase text-gray-500 font-semibold tracking-wider">
						<?php if($showFuncCol): ?><th class="px-6 py-4">Funcionário</th><?php endif; ?>
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
							<td colspan="<?php echo $showFuncCol ? 7 : 6; ?>" class="px-6 py-12 text-center text-gray-500">
								Nenhum documento encontrado para os filtros selecionados.
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
								$funcNome = trim(strval($row["funcionario_nome"] ?? ""));
								$setor = trim(strval($row["setor_nome"] ?? ""));

								$origem = $usuarioId > 0 ? "Manual" : "Sistema";
								$origemDetalhe = $usuarioId > 0 ? ($usuarioLogin !== "" ? $usuarioLogin : ("User #" . $usuarioId)) : "";

								$statusTxt = $assinado === "sim" ? "Assinado" : "Pendente";
								$statusClass = $assinado === "sim" ? "bg-green-100 text-green-800 border-green-200" : "bg-yellow-50 text-yellow-800 border-yellow-200";

								$link = "";
								if($caminho !== ""){
									$link = rtrim($baseContex, "/") . "/" . ltrim(str_replace("\\", "/", $caminho), "/");
								}
							?>
							<tr class="hover:bg-gray-50 transition-colors">
								<?php if($showFuncCol): ?>
									<td class="px-6 py-4 text-sm text-gray-700">
										<div class="font-semibold"><?php echo htmlspecialchars($funcNome !== "" ? $funcNome : ("#" . intval($row["docu_nb_entidade"] ?? 0))); ?></div>
										<div class="text-xs text-gray-500"><?php echo htmlspecialchars($setor !== "" ? $setor : "—"); ?></div>
									</td>
								<?php endif; ?>
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
									<?php if($origemDetalhe !== ""): ?>
										<div class="text-xs text-gray-500"><?php echo htmlspecialchars($origemDetalhe); ?></div>
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

	<?php if($funcionarioId > 0 && $tab !== "assinados"): ?>
		<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
			<div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
				<div class="font-semibold text-gray-800">Arquivos (Pasta do Funcionário)</div>
				<div class="text-xs text-gray-500"><?php echo $totalPasta; ?> itens</div>
			</div>
			<div class="overflow-x-auto">
				<table class="w-full text-left border-collapse">
					<thead>
						<tr class="bg-white border-b border-gray-200 text-xs uppercase text-gray-500 font-semibold tracking-wider">
							<th class="px-6 py-4">Arquivo</th>
							<th class="px-6 py-4">Data</th>
							<th class="px-6 py-4">Status</th>
							<th class="px-6 py-4 text-right">Ações</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<?php if(empty($arquivosSemCadastro)): ?>
							<tr>
								<td colspan="4" class="px-6 py-12 text-center text-gray-500">
									Nenhum arquivo encontrado na pasta.
								</td>
							</tr>
						<?php else: ?>
							<?php foreach($arquivosSemCadastro as $a): ?>
								<?php
									$basename = strval($a["basename"] ?? "");
									$rel = strval($a["rel"] ?? "");
									$mtime = intval($a["mtime"] ?? 0);
									$link = $rel !== "" ? (rtrim($baseContex, "/") . "/" . ltrim(str_replace("\\", "/", $rel), "/")) : "";
								?>
								<tr class="hover:bg-gray-50 transition-colors">
									<td class="px-6 py-4">
										<div class="flex items-center gap-3">
											<div class="bg-gray-50 text-gray-600 h-10 w-10 rounded-lg flex items-center justify-center flex-shrink-0">
												<i class="far fa-file"></i>
											</div>
											<div class="font-medium text-gray-900"><?php echo htmlspecialchars($basename !== "" ? $basename : "(Sem nome)"); ?></div>
										</div>
									</td>
									<td class="px-6 py-4 text-sm text-gray-700">
										<?php echo $mtime > 0 ? htmlspecialchars(date("d/m/Y H:i", $mtime)) : "—"; ?>
									</td>
									<td class="px-6 py-4">
										<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gray-50 text-gray-700 border-gray-200">
											Não assinado
										</span>
									</td>
									<td class="px-6 py-4 text-right">
										<?php if($link !== ""): ?>
											<div class="inline-flex items-center justify-end gap-4">
												<a href="<?php echo htmlspecialchars($link); ?>" target="_blank" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700 font-semibold text-sm">
													<i class="fas fa-eye"></i> Abrir
												</a>
												<a href="<?php echo htmlspecialchars($link); ?>" download="<?php echo htmlspecialchars($basename !== "" ? $basename : "arquivo"); ?>" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-800 font-semibold text-sm">
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
	.select2-container{max-width:100%}
	.select2-dropdown{max-width:100%;box-sizing:border-box}
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
				dropdownAutoWidth: false,
				dropdownParent: $func.closest("form")
			});

			$func.on("select2:open", function(){
				const $container = $func.next(".select2");
				const w = $container.length ? $container.outerWidth() : null;
				const $dropdown = jQuery(".select2-container--open .select2-dropdown");
				if(w && $dropdown.length){
					$dropdown.css({ width: w + "px", minWidth: w + "px" });
				}
			});
		}
	}
</script>

<?php include_once "componentes/layout_footer.php"; ?>
