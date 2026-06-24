<?php
include_once "../conecta.php";
include_once "componentes/layout_header.php";

// Parâmetros de filtro
$empresaId          = intval($_GET["empresa"] ?? 0);
$funcionarioId      = intval($_GET["funcionario"] ?? 0);
$origemFuncionario  = strtolower(trim(strval($_GET["origem"] ?? "")));
$origemFuncionario  = in_array($origemFuncionario, ["interna", "externa"], true) ? $origemFuncionario : "";
$tab                = strtolower(trim(strval($_GET["tab"] ?? "")));
$tab                = in_array($tab, ["todos", "assinados", "arquivo", "expirados"], true) ? $tab : "todos";
$filtro_status      = strtolower(trim(strval($_GET["assinado"] ?? "")));
$filtro_status      = in_array($filtro_status, ["sim", "nao", "expirado"], true) ? $filtro_status : "";
$filtro_data_inicio = trim(strval($_GET["data_inicio"] ?? ""));
$filtro_data_fim    = trim(strval($_GET["data_fim"] ?? ""));
$filtro_tipo        = intval($_GET["tipo_documento"] ?? 0);
$busca              = trim(strval($_GET["busca"] ?? ""));

// Status efetivo por aba
$effective_status = $filtro_status;
if ($tab === "assinados")  { $effective_status = "sim"; }
if ($tab === "arquivo")    { $effective_status = "nao"; }
if ($tab === "expirados")  { $effective_status = "expirado"; }

// Tipos de documentos
$tiposDocumentos = [];
$resTipos = mysqli_query($conn, "SELECT tipo_nb_id, tipo_tx_nome FROM tipos_documentos WHERE tipo_tx_status = 'ativo' ORDER BY tipo_tx_nome ASC");
if ($resTipos) {
	while ($r = mysqli_fetch_assoc($resTipos)) {
		$tid = intval($r["tipo_nb_id"] ?? 0);
		$tn  = trim(strval($r["tipo_tx_nome"] ?? ""));
		if ($tid > 0 && $tn !== "") { $tiposDocumentos[] = ["id" => $tid, "nome" => $tn]; }
	}
}

// Empresas
$empresas = [];
$resEmp = mysqli_query($conn, "SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
if ($resEmp) {
	while ($r = mysqli_fetch_assoc($resEmp)) {
		$eid  = intval($r["empr_nb_id"] ?? 0);
		$enm  = trim(strval($r["empr_tx_nome"] ?? ""));
		if ($eid > 0 && $enm !== "") { $empresas[] = ["id" => $eid, "nome" => $enm]; }
	}
}

// Origem "externa" usa signatarios_externos; "interna" usa entidade
$tipoPessoa      = strtolower(trim(strval($_GET["tipo_pessoa"] ?? "")));
$tipoPessoa      = in_array($tipoPessoa, ["funcionario", "externo"], true) ? $tipoPessoa : "funcionario";

// Força tipo_pessoa conforme origem
if ($origemFuncionario === "externa") { $tipoPessoa = "externo"; }
if ($origemFuncionario === "interna") { $tipoPessoa = "funcionario"; }

$funcionarios = [];

if ($origemFuncionario === "externa") {
	// ─── Signatários Externos ────────────────────────────────────────
	$whereExt  = "s.sign_tx_status = 'ativo'";
	$typesExt  = "";
	$paramsExt = [];
	if ($empresaId > 0) {
		$whereExt .= " AND s.sign_nb_empresa = ?";
		$typesExt  = "i";
		$paramsExt = [$empresaId];
	}
	$sqlExt = "SELECT s.sign_nb_id, s.sign_tx_nome, s.sign_nb_empresa, e.empr_tx_nome as empresa_nome
			   FROM signatarios_externos s
			   LEFT JOIN empresa e ON e.empr_nb_id = s.sign_nb_empresa
			   WHERE {$whereExt}
			   ORDER BY s.sign_tx_nome ASC";
	$resExt = $typesExt !== ""
		? (function() use ($conn, $sqlExt, $typesExt, $paramsExt) {
			$st = mysqli_prepare($conn, $sqlExt);
			if (!$st) return null;
			mysqli_stmt_bind_param($st, $typesExt, ...$paramsExt);
			mysqli_stmt_execute($st);
			return mysqli_stmt_get_result($st);
		  })()
		: mysqli_query($conn, $sqlExt);
	if ($resExt) {
		while ($r = mysqli_fetch_assoc($resExt)) {
			$id    = intval($r["sign_nb_id"] ?? 0);
			$nome  = trim(strval($r["sign_tx_nome"] ?? ""));
			$empNm = trim(strval($r["empresa_nome"] ?? ""));
			if ($id <= 0 || $nome === "") { continue; }
			$label = $nome;
			if ($empNm !== "") { $label .= " | " . $empNm; }
			$funcionarios[] = ["id" => $id, "label" => $label, "empresa_id" => intval($r["sign_nb_empresa"] ?? 0), "tipo" => "externo"];
		}
	}
} else {
	// ─── Entidade (funcionários internos) ────────────────────────────
	$whereFunc     = "COALESCE(NULLIF(TRIM(e.enti_tx_nome),''),'') <> ''";
	$tiposFuncVars = "";
	$tiposFuncParams = [];

	if ($empresaId > 0) {
		$whereFunc .= " AND e.enti_nb_empresa = ?";
		$tiposFuncVars  .= "i";
		$tiposFuncParams[] = $empresaId;
	} elseif ($origemFuncionario === "interna") {
		$whereFunc .= " AND e.enti_nb_empresa IS NOT NULL AND e.enti_nb_empresa > 0";
	}

	$sqlFunc = "SELECT DISTINCT e.enti_nb_id, e.enti_tx_nome, e.enti_nb_empresa, emp.empr_tx_nome as empresa_nome,
					e.enti_setor_id, g.grup_tx_nome as setor_nome
				FROM entidade e
				LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
				LEFT JOIN empresa emp ON emp.empr_nb_id = e.enti_nb_empresa
				WHERE {$whereFunc}
				ORDER BY e.enti_tx_nome ASC";
	if ($tiposFuncVars !== "") {
		$stmtFunc = mysqli_prepare($conn, $sqlFunc);
		if ($stmtFunc) {
			mysqli_stmt_bind_param($stmtFunc, $tiposFuncVars, ...$tiposFuncParams);
			mysqli_stmt_execute($stmtFunc);
			$resFunc = mysqli_stmt_get_result($stmtFunc);
		}
	} else {
		$resFunc = mysqli_query($conn, $sqlFunc);
	}
	if ($resFunc) {
		while ($r = mysqli_fetch_assoc($resFunc)) {
			$id     = intval($r["enti_nb_id"] ?? 0);
			$nome   = trim(strval($r["enti_tx_nome"] ?? ""));
			$setor  = trim(strval($r["setor_nome"] ?? ""));
			$empNm  = trim(strval($r["empresa_nome"] ?? ""));
			$empId2 = intval($r["enti_nb_empresa"] ?? 0);
			if ($id <= 0 || $nome === "") { continue; }
			$label  = $nome;
			if ($empNm !== "") { $label .= " | " . $empNm; }
			elseif ($setor !== "") { $label .= " | " . $setor; }
			$funcionarios[] = ["id" => $id, "label" => $label, "empresa_id" => $empId2, "tipo" => "funcionario"];
		}
	}
}

// ─── ASSINATURAS DO SIGNATÁRIO EXTERNO ──────────────────────────────────────
$assinaturasExterno = [];
if ($funcionarioId > 0 && $tipoPessoa === "externo") {
	// Busca email/cpf do signatário para fazer o JOIN via assinantes
	$resSignExt = mysqli_query($conn, "SELECT sign_tx_email, sign_tx_cpf FROM signatarios_externos WHERE sign_nb_id = " . $funcionarioId . " LIMIT 1");
	$signExt    = $resSignExt ? mysqli_fetch_assoc($resSignExt) : null;
	if ($signExt) {
		$signEmail = trim(strval($signExt["sign_tx_email"] ?? ""));
		$signCpf   = preg_replace('/\D/', '', strval($signExt["sign_tx_cpf"] ?? ""));

		$condParts = [];
		$typesS    = "";
		$paramsS   = [];
		if ($signEmail !== "") {
			$condParts[] = "LOWER(TRIM(a.email)) = LOWER(?)";
			$typesS  .= "s";
			$paramsS[] = $signEmail;
		}
		if ($signCpf !== "") {
			$condParts[] = "REPLACE(REPLACE(REPLACE(a.cpf,'.',''),'-',''),' ','') = ?";
			$typesS  .= "s";
			$paramsS[] = $signCpf;
		}

		if (!empty($condParts)) {
			$cond    = implode(" OR ", $condParts);
			$resSign = query(
				"SELECT a.id, a.nome, a.email, a.status, a.data_assinatura,
				        sol.id as sol_id, sol.nome as doc_nome, sol.nome_arquivo_original,
				        sol.caminho_arquivo as doc_caminho, sol.data_solicitacao as doc_data,
				        sol.status_final as solic_status
				 FROM assinantes a
				 JOIN solicitacoes_assinatura sol ON sol.id = a.id_solicitacao
				 WHERE ($cond)
				 ORDER BY sol.data_solicitacao DESC",
				$typesS, $paramsS
			);
			if ($resSign) {
				while ($r = mysqli_fetch_assoc($resSign)) { $assinaturasExterno[] = $r; }
			}
		}
	}
}

// ─── DOCUMENTOS DO FUNCIONÁRIO ───────────────────────────────────────────────
$docs = [];
if ($funcionarioId > 0 && $tipoPessoa === "funcionario") {
	$where = [];
	$types = "";
	$vars  = [];

	$where[] = "df.docu_nb_entidade = ?";
	$types   .= "i";
	$vars[]   = $funcionarioId;

	if ($effective_status === "sim") {
		$where[] = "LOWER(COALESCE(df.docu_tx_assinado,'nao')) = 'sim'";
	} elseif ($effective_status === "nao") {
		$where[] = "LOWER(COALESCE(df.docu_tx_assinado,'nao')) = 'nao'";
	} elseif ($effective_status === "expirado") {
		$where[] = "df.docu_tx_dataVencimento IS NOT NULL AND df.docu_tx_dataVencimento <> '0000-00-00' AND df.docu_tx_dataVencimento <> '0000-00-00 00:00:00' AND DATE(df.docu_tx_dataVencimento) < CURDATE()";
	}

	if ($filtro_tipo > 0) {
		$where[] = "df.docu_tx_tipo = ?";
		$types   .= "i";
		$vars[]   = $filtro_tipo;
	}
	if ($filtro_data_inicio !== "") {
		$where[] = "DATE(df.docu_tx_dataCadastro) >= ?";
		$types   .= "s";
		$vars[]   = $filtro_data_inicio;
	}
	if ($filtro_data_fim !== "") {
		$where[] = "DATE(df.docu_tx_dataCadastro) <= ?";
		$types   .= "s";
		$vars[]   = $filtro_data_fim;
	}
	if ($busca !== "") {
		$where[] = "(df.docu_tx_nome LIKE ? OR df.docu_tx_descricao LIKE ? OR df.docu_tx_caminho LIKE ?)";
		$types   .= "sss";
		$like     = "%" . $busca . "%";
		$vars[]   = $like;
		$vars[]   = $like;
		$vars[]   = $like;
	}

	$whereSql = !empty($where) ? ("WHERE " . implode(" AND ", $where)) : "";
	$docs = mysqli_fetch_all(query(
		"SELECT df.*, t.tipo_tx_nome as tipo_nome, u.user_tx_login as usuario_login,
				e.enti_tx_nome as funcionario_nome, e.enti_setor_id, g.grup_tx_nome as setor_nome,
				emp.empr_tx_nome as empresa_nome
		FROM documento_funcionario df
		JOIN entidade e ON e.enti_nb_id = df.docu_nb_entidade
		LEFT JOIN empresa emp ON emp.empr_nb_id = e.enti_nb_empresa
		LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
		LEFT JOIN tipos_documentos t ON t.tipo_nb_id = df.docu_tx_tipo
		LEFT JOIN user u ON u.user_nb_id = df.docu_tx_usuarioCadastro
		{$whereSql}
		ORDER BY df.docu_tx_dataCadastro DESC, df.docu_nb_id DESC",
		$types, $vars
	), MYSQLI_ASSOC) ?: [];
}

// Arquivos físicos sem cadastro (funcionário)
$arquivosSemCadastro = [];
if ($funcionarioId > 0 && $tipoPessoa === "funcionario" && $tab !== "assinados" && $tab !== "expirados") {
	$dbPaths = [];
	$resPaths = query("SELECT docu_tx_caminho FROM documento_funcionario WHERE docu_nb_entidade = ?", "i", [$funcionarioId]);
	while ($resPaths && ($r = mysqli_fetch_assoc($resPaths))) {
		$path = trim(strval($r["docu_tx_caminho"] ?? ""));
		if ($path !== "") {
			$dbPaths[$path] = true;
			$dbPaths[ltrim($path, "/\\")] = true;
		}
	}
	$root     = dirname(__DIR__);
	$pastaAbs = rtrim(str_replace("\\", "/", $root), "/") . "/arquivos/Funcionarios/" . $funcionarioId . "/";
	if (is_dir($pastaAbs)) {
		$files = @scandir($pastaAbs);
		if (is_array($files)) {
			foreach ($files as $f) {
				if ($f === "." || $f === "..") { continue; }
				$abs = $pastaAbs . $f;
				if (!is_file($abs)) { continue; }
				$rel = "arquivos/Funcionarios/" . $funcionarioId . "/" . $f;
				if (isset($dbPaths[$rel]) || isset($dbPaths[ltrim($rel, "/\\")])) { continue; }
				$mtime = @filemtime($abs) ?: 0;
				if ($filtro_data_inicio !== "") {
					$dt = $mtime > 0 ? date("Y-m-d", $mtime) : "";
					if ($dt !== "" && $dt < $filtro_data_inicio) { continue; }
				}
				if ($filtro_data_fim !== "") {
					$dt = $mtime > 0 ? date("Y-m-d", $mtime) : "";
					if ($dt !== "" && $dt > $filtro_data_fim) { continue; }
				}
				$arquivosSemCadastro[] = ["basename" => $f, "rel" => $rel, "size" => @filesize($abs) ?: 0, "mtime" => $mtime];
			}
		}
		usort($arquivosSemCadastro, fn($a, $b) => intval($b["mtime"]) <=> intval($a["mtime"]));
	}
}

// ─── DOCUMENTOS DA EMPRESA ───────────────────────────────────────────────────
$docsEmpresa = [];
$arquivosEmpresaSemCadastro = [];
if ($empresaId > 0) {
	$whereEmp  = [];
	$typesEmp  = "i";
	$varsEmp   = [$empresaId];
	$whereEmp[]= "de.empr_nb_id = ?";

	if ($effective_status === "sim") {
		$whereEmp[] = "LOWER(COALESCE(de.docu_tx_assinado,'nao')) = 'sim'";
	} elseif ($effective_status === "nao") {
		$whereEmp[] = "LOWER(COALESCE(de.docu_tx_assinado,'nao')) = 'nao'";
	} elseif ($effective_status === "expirado") {
		$whereEmp[] = "de.docu_tx_datavencimento IS NOT NULL AND de.docu_tx_datavencimento <> '0000-00-00' AND de.docu_tx_datavencimento <> '0000-00-00 00:00:00' AND DATE(de.docu_tx_datavencimento) < CURDATE()";
	}

	if ($filtro_tipo > 0) { $whereEmp[] = "de.docu_tx_tipo = ?"; $typesEmp .= "i"; $varsEmp[] = $filtro_tipo; }
	if ($filtro_data_inicio !== "") { $whereEmp[] = "DATE(de.docu_tx_dataCadastro) >= ?"; $typesEmp .= "s"; $varsEmp[] = $filtro_data_inicio; }
	if ($filtro_data_fim    !== "") { $whereEmp[] = "DATE(de.docu_tx_dataCadastro) <= ?"; $typesEmp .= "s"; $varsEmp[] = $filtro_data_fim; }
	if ($busca !== "") {
		$whereEmp[] = "(de.docu_tx_nome LIKE ? OR de.docu_tx_descricao LIKE ? OR de.docu_tx_caminho LIKE ?)";
		$typesEmp  .= "sss";
		$like        = "%" . $busca . "%";
		$varsEmp[]   = $like; $varsEmp[] = $like; $varsEmp[] = $like;
	}

	$whereEmpSql = "WHERE " . implode(" AND ", $whereEmp);
	$docsEmpresa = mysqli_fetch_all(query(
		"SELECT de.*, t.tipo_tx_nome as tipo_nome, u.user_tx_login as usuario_login, emp.empr_tx_nome as empresa_nome
		FROM documento_empresa de
		JOIN empresa emp ON emp.empr_nb_id = de.empr_nb_id
		LEFT JOIN tipos_documentos t ON t.tipo_nb_id = de.docu_tx_tipo
		LEFT JOIN user u ON u.user_nb_id = de.docu_tx_usuarioCadastro
		{$whereEmpSql}
		ORDER BY de.docu_tx_dataCadastro DESC, de.docu_nb_id DESC",
		$typesEmp, $varsEmp
	), MYSQLI_ASSOC) ?: [];

	// Arquivos físicos da empresa sem cadastro
	if ($tab !== "assinados" && $tab !== "expirados") {
		$dbPathsEmp = [];
		$resPathsEmp = query("SELECT docu_tx_caminho FROM documento_empresa WHERE empr_nb_id = ?", "i", [$empresaId]);
		while ($resPathsEmp && ($r = mysqli_fetch_assoc($resPathsEmp))) {
			$p = trim(strval($r["docu_tx_caminho"] ?? ""));
			if ($p !== "") { $dbPathsEmp[$p] = true; $dbPathsEmp[ltrim($p, "/\\")] = true; }
		}
		$pastaEmpAbs = rtrim(str_replace("\\", "/", dirname(__DIR__)), "/") . "/arquivos/docu_empresa/" . $empresaId . "/";
		if (is_dir($pastaEmpAbs)) {
			$files = @scandir($pastaEmpAbs);
			if (is_array($files)) {
				foreach ($files as $f) {
					if ($f === "." || $f === "..") { continue; }
					$abs = $pastaEmpAbs . $f;
					if (!is_file($abs)) { continue; }
					$rel = "arquivos/docu_empresa/" . $empresaId . "/" . $f;
					if (isset($dbPathsEmp[$rel]) || isset($dbPathsEmp[ltrim($rel, "/\\")])) { continue; }
					$mtime = @filemtime($abs) ?: 0;
					$arquivosEmpresaSemCadastro[] = ["basename" => $f, "rel" => $rel, "size" => @filesize($abs) ?: 0, "mtime" => $mtime];
				}
			}
			usort($arquivosEmpresaSemCadastro, fn($a, $b) => intval($b["mtime"]) <=> intval($a["mtime"]));
		}
	}
}

$totalDb      = count($docs);
$totalPasta   = count($arquivosSemCadastro);
$totalEmp     = count($docsEmpresa);
$totalEmpPasta= count($arquivosEmpresaSemCadastro);
$temConteudo  = $empresaId > 0 || $funcionarioId > 0;

function docStatusBadge(string $assinado, string $venc): array {
	$hoje = date("Y-m-d");
	$expirado = $venc !== "" && $venc !== "0000-00-00" && $venc !== "0000-00-00 00:00:00" && date("Y-m-d", strtotime($venc)) < $hoje;
	if ($expirado)         { return ["Expirado",  "bg-red-100 text-red-800 border-red-200"]; }
	if ($assinado === "sim") { return ["Assinado",  "bg-green-100 text-green-800 border-green-200"]; }
	return ["Pendente", "bg-yellow-50 text-yellow-800 border-yellow-200"];
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

	<!-- Cabeçalho -->
	<div class="mb-8 flex flex-col md:flex-row justify-between items-center gap-4">
		<div>
			<h1 class="text-2xl font-bold text-gray-800">Documentos</h1>
			<p class="text-gray-500 text-sm">Documentos de funcionários e empresas — assinados, pendentes e expirados.</p>
		</div>
		<a href="index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors text-sm font-medium">
			<i class="fas fa-arrow-left mr-2"></i>Voltar
		</a>
	</div>

	<!-- Filtros -->
	<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
		<form method="GET" id="formFiltros" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">

			<!-- Empresa -->
			<div class="md:col-span-4">
				<label class="block text-sm font-medium text-gray-700 mb-1">Empresa</label>
				<select id="filtro_empresa" name="empresa" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">
					<option value="">Todas as empresas</option>
					<?php foreach ($empresas as $e): ?>
						<option value="<?php echo $e["id"]; ?>" <?php echo ($empresaId === $e["id"]) ? "selected" : ""; ?>>
							<?php echo htmlspecialchars($e["nome"]); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Origem do funcionário -->
			<div class="md:col-span-2">
				<label class="block text-sm font-medium text-gray-700 mb-1">Origem</label>
				<select name="origem" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">
					<option value="">Todos</option>
					<option value="interna" <?php echo $origemFuncionario === "interna" ? "selected" : ""; ?>>Interna</option>
					<option value="externa" <?php echo $origemFuncionario === "externa" ? "selected" : ""; ?>>Externa</option>
				</select>
			</div>

			<!-- Funcionário / Signatário -->
			<div class="md:col-span-6">
				<label class="block text-sm font-medium text-gray-700 mb-1">
					<?php echo $origemFuncionario === "externa" ? "Signatário Externo" : "Funcionário"; ?>
				</label>
				<input type="hidden" name="tipo_pessoa" id="campo_tipo_pessoa" value="<?php echo htmlspecialchars($tipoPessoa); ?>">
				<select id="filtro_funcionario" name="funcionario" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">
					<option value="">Selecione (opcional)</option>
					<?php foreach ($funcionarios as $f): ?>
						<option value="<?php echo $f["id"]; ?>"
							data-empresa="<?php echo $f["empresa_id"]; ?>"
							data-tipo="<?php echo htmlspecialchars($f["tipo"] ?? "funcionario"); ?>"
							<?php echo ($funcionarioId === $f["id"]) ? "selected" : ""; ?>>
							<?php echo htmlspecialchars($f["label"]); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Visão (tab) -->
			<div class="md:col-span-3">
				<label class="block text-sm font-medium text-gray-700 mb-1">Documentos</label>
				<select name="tab" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">
					<option value="todos"     <?php echo $tab === "todos"     ? "selected" : ""; ?>>Todos</option>
					<option value="assinados" <?php echo $tab === "assinados" ? "selected" : ""; ?>>Assinados</option>
					<option value="arquivo"   <?php echo $tab === "arquivo"   ? "selected" : ""; ?>>Não assinados</option>
					<option value="expirados" <?php echo $tab === "expirados" ? "selected" : ""; ?>>Expirados</option>
				</select>
			</div>

			<!-- Status -->
			<div class="md:col-span-3">
				<label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
				<?php $tabFixed = in_array($tab, ["assinados", "arquivo", "expirados"], true); ?>
				<?php if ($tabFixed): ?>
					<input type="hidden" name="assinado" value="<?php echo $tab === "assinados" ? "sim" : ($tab === "arquivo" ? "nao" : "expirado"); ?>">
				<?php endif; ?>
				<select name="assinado" <?php echo $tabFixed ? "disabled" : ""; ?> class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm disabled:bg-gray-100">
					<option value="">Todos</option>
					<option value="sim"      <?php echo ($effective_status === "sim")      ? "selected" : ""; ?>>Assinado</option>
					<option value="nao"      <?php echo ($effective_status === "nao")      ? "selected" : ""; ?>>Pendente</option>
					<option value="expirado" <?php echo ($effective_status === "expirado") ? "selected" : ""; ?>>Expirado</option>
				</select>
			</div>

			<!-- Tipo -->
			<div class="md:col-span-3">
				<label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
				<select name="tipo_documento" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">
					<option value="">Todos</option>
					<?php foreach ($tiposDocumentos as $t): ?>
						<option value="<?php echo $t["id"]; ?>" <?php echo ($filtro_tipo === $t["id"]) ? "selected" : ""; ?>>
							<?php echo htmlspecialchars($t["nome"]); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Período -->
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

			<!-- Busca + botões -->
			<div class="md:col-span-5">
				<label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
				<input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Nome, descrição ou caminho..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
			</div>

			<div class="md:col-span-3 flex gap-2">
				<button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium text-sm">
					<i class="fas fa-search mr-1"></i> Filtrar
				</button>
				<a href="documentos.php" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600" title="Limpar">
					<i class="fas fa-times"></i>
				</a>
			</div>

		</form>
	</div>

	<?php if (!$temConteudo): ?>
		<div class="bg-blue-50 border border-blue-100 text-blue-900 rounded-xl p-6 text-sm">
			Selecione uma <span class="font-semibold">empresa</span> e/ou um <span class="font-semibold">funcionário</span> e clique em <span class="font-semibold">Filtrar</span> para listar os documentos.
		</div>
	<?php else: ?>

		<!-- Contadores -->
		<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
			<div class="bg-white rounded-xl border border-gray-200 p-4">
				<div class="text-xs text-gray-500">Docs. Funcionário (cadastro)</div>
				<div class="text-2xl font-bold text-gray-900"><?php echo $totalDb; ?></div>
			</div>
			<div class="bg-white rounded-xl border border-gray-200 p-4">
				<div class="text-xs text-gray-500">Arquivos sem cadastro (pasta)</div>
				<div class="text-2xl font-bold text-gray-900"><?php echo $totalPasta; ?></div>
			</div>
			<div class="bg-white rounded-xl border border-gray-200 p-4">
				<div class="text-xs text-gray-500">Docs. Empresa (cadastro)</div>
				<div class="text-2xl font-bold text-gray-900"><?php echo $totalEmp; ?></div>
			</div>
			<div class="bg-white rounded-xl border border-gray-200 p-4">
				<div class="text-xs text-gray-500">Arquivos Empresa sem cadastro</div>
				<div class="text-2xl font-bold text-gray-900"><?php echo $totalEmpPasta; ?></div>
			</div>
		</div>

		<?php
		$renderTabela = function(array $rows, bool $isEmpresa = false) use ($baseContex, $tab): void {
			$colSpan = $isEmpresa ? 5 : 6;
			?>
			<div class="overflow-x-auto">
				<table class="w-full text-left border-collapse">
					<thead>
						<tr class="bg-white border-b border-gray-200 text-xs uppercase text-gray-500 font-semibold tracking-wider">
							<th class="px-6 py-4">Documento</th>
							<th class="px-6 py-4">Tipo</th>
							<th class="px-6 py-4">Cadastro</th>
							<th class="px-6 py-4">Status</th>
							<?php if (!$isEmpresa): ?>
								<th class="px-6 py-4">Origem</th>
							<?php endif; ?>
							<th class="px-6 py-4 text-right">Ações</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<?php if (empty($rows)): ?>
							<tr>
								<td colspan="<?php echo $colSpan; ?>" class="px-6 py-12 text-center text-gray-500 text-sm">
									Nenhum documento encontrado para os filtros selecionados.
								</td>
							</tr>
						<?php else: ?>
							<?php foreach ($rows as $row): ?>
								<?php
									$nome        = trim(strval($row["docu_tx_nome"] ?? ""));
									$desc        = trim(strval($row["docu_tx_descricao"] ?? ""));
									$tipoNome    = trim(strval($row["tipo_nome"] ?? ""));
									$dataCadastro= trim(strval($row["docu_tx_dataCadastro"] ?? ""));
									$dataVenc    = trim(strval($row["docu_tx_dataVencimento"] ?? $row["docu_tx_datavencimento"] ?? ""));
									$assinado    = strtolower(trim(strval($row["docu_tx_assinado"] ?? "nao")));
									$visivel     = strtolower(trim(strval($row["docu_tx_visivel"] ?? "nao")));
									$caminho     = trim(strval($row["docu_tx_caminho"] ?? ""));
									$usuarioId   = intval($row["docu_tx_usuarioCadastro"] ?? 0);
									$usuarioLogin= trim(strval($row["usuario_login"] ?? ""));
									$origem      = $usuarioId > 0 ? "Manual" : "Sistema";
									$origemDet   = $usuarioId > 0 ? ($usuarioLogin !== "" ? $usuarioLogin : "User #".$usuarioId) : "";
									[$statusTxt, $statusClass] = docStatusBadge($assinado, $dataVenc);
									$link = $caminho !== "" ? rtrim($baseContex, "/") . "/" . ltrim(str_replace("\\", "/", $caminho), "/") : "";
								?>
								<tr class="hover:bg-gray-50 transition-colors">
									<td class="px-6 py-4">
										<div class="flex items-start gap-3">
											<div class="bg-blue-50 text-blue-600 h-10 w-10 rounded-lg flex items-center justify-center flex-shrink-0">
												<i class="far fa-file-pdf"></i>
											</div>
											<div>
												<div class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($nome !== "" ? $nome : "(Sem nome)"); ?></div>
												<?php if ($desc !== ""): ?>
													<div class="text-xs text-gray-500 mt-0.5 max-w-xs"><?php echo htmlspecialchars($desc); ?></div>
												<?php endif; ?>
												<?php if ($dataVenc !== "" && $dataVenc !== "0000-00-00" && $dataVenc !== "0000-00-00 00:00:00"): ?>
													<div class="text-xs text-gray-400 mt-0.5">Venc.: <?php echo htmlspecialchars(date("d/m/Y", strtotime($dataVenc))); ?></div>
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
												<?php echo $statusTxt; ?>
											</span>
											<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border <?php echo $visivel === "sim" ? "bg-gray-50 text-gray-600 border-gray-200" : "bg-red-50 text-red-700 border-red-200"; ?>">
												<?php echo $visivel === "sim" ? "Visível" : "Oculto"; ?>
											</span>
										</div>
									</td>
									<?php if (!$isEmpresa): ?>
										<td class="px-6 py-4 text-sm text-gray-700">
											<div class="font-semibold"><?php echo htmlspecialchars($origem); ?></div>
											<?php if ($origemDet !== ""): ?>
												<div class="text-xs text-gray-400"><?php echo htmlspecialchars($origemDet); ?></div>
											<?php endif; ?>
										</td>
									<?php endif; ?>
									<td class="px-6 py-4 text-right">
										<?php if ($link !== ""): ?>
											<div class="inline-flex items-center justify-end gap-4">
												<a href="<?php echo htmlspecialchars($link); ?>" target="_blank" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 font-semibold text-sm">
													<i class="fas fa-eye"></i> Abrir
												</a>
												<a href="<?php echo htmlspecialchars($link); ?>" download="<?php echo htmlspecialchars(basename(str_replace("\\", "/", $caminho))); ?>" class="inline-flex items-center gap-1 text-gray-600 hover:text-gray-800 font-semibold text-sm">
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
			<?php
		};

		$renderArquivosSemCadastro = function(array $arquivos) use ($baseContex): void { ?>
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
						<?php if (empty($arquivos)): ?>
							<tr>
								<td colspan="4" class="px-6 py-12 text-center text-gray-500 text-sm">Nenhum arquivo encontrado na pasta.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($arquivos as $a): ?>
								<?php
									$basename = strval($a["basename"] ?? "");
									$rel      = strval($a["rel"] ?? "");
									$mtime    = intval($a["mtime"] ?? 0);
									$link     = $rel !== "" ? rtrim($baseContex, "/") . "/" . ltrim(str_replace("\\", "/", $rel), "/") : "";
								?>
								<tr class="hover:bg-gray-50 transition-colors">
									<td class="px-6 py-4">
										<div class="flex items-center gap-3">
											<div class="bg-gray-50 text-gray-500 h-10 w-10 rounded-lg flex items-center justify-center flex-shrink-0">
												<i class="far fa-file"></i>
											</div>
											<div class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($basename !== "" ? $basename : "(Sem nome)"); ?></div>
										</div>
									</td>
									<td class="px-6 py-4 text-sm text-gray-700">
										<?php echo $mtime > 0 ? htmlspecialchars(date("d/m/Y H:i", $mtime)) : "—"; ?>
									</td>
									<td class="px-6 py-4">
										<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gray-50 text-gray-600 border-gray-200">
											Não cadastrado
										</span>
									</td>
									<td class="px-6 py-4 text-right">
										<?php if ($link !== ""): ?>
											<div class="inline-flex items-center justify-end gap-4">
												<a href="<?php echo htmlspecialchars($link); ?>" target="_blank" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 font-semibold text-sm">
													<i class="fas fa-eye"></i> Abrir
												</a>
												<a href="<?php echo htmlspecialchars($link); ?>" download="<?php echo htmlspecialchars($basename !== "" ? $basename : "arquivo"); ?>" class="inline-flex items-center gap-1 text-gray-600 hover:text-gray-800 font-semibold text-sm">
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
		<?php };
		?>

		<!-- ─── SEÇÃO: SIGNATÁRIO EXTERNO ──────────────────────────── -->
		<?php if ($funcionarioId > 0 && $tipoPessoa === "externo"): ?>
			<?php
			// Encontra nome do signatário selecionado
			$signNome = "";
			foreach ($funcionarios as $f) {
				if ($f["id"] === $funcionarioId) { $signNome = $f["label"]; break; }
			}
			?>
			<div class="mb-8">
				<h2 class="text-lg font-bold text-gray-700 mb-3 flex items-center gap-2">
					<i class="fas fa-user-tie text-purple-500"></i> Signatário Externo
					<span class="text-sm font-normal text-gray-500">— <?php echo htmlspecialchars($signNome); ?></span>
				</h2>

				<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
					<div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
						<div class="font-semibold text-gray-800 text-sm">Documentos assinados</div>
						<span class="text-xs text-gray-500"><?php echo count($assinaturasExterno); ?> itens</span>
					</div>
					<div class="overflow-x-auto">
						<table class="w-full text-left border-collapse">
							<thead>
								<tr class="bg-white border-b border-gray-200 text-xs uppercase text-gray-500 font-semibold tracking-wider">
									<th class="px-6 py-4">Documento</th>
									<th class="px-6 py-4">Data solicitação</th>
									<th class="px-6 py-4">Data assinatura</th>
									<th class="px-6 py-4">Status</th>
									<th class="px-6 py-4 text-right">Ações</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-100">
								<?php if (empty($assinaturasExterno)): ?>
									<tr>
										<td colspan="5" class="px-6 py-12 text-center text-gray-500 text-sm">
											Nenhum documento encontrado para este signatário.
										</td>
									</tr>
								<?php else: ?>
									<?php foreach ($assinaturasExterno as $sa):
										$docNome     = trim(strval($sa["nome_arquivo_original"] ?? $sa["doc_nome"] ?? ""));
										$docCaminho  = trim(strval($sa["doc_caminho"] ?? ""));
										$docData     = trim(strval($sa["doc_data"] ?? ""));
										$dataAssina  = trim(strval($sa["data_assinatura"] ?? ""));
										$solicStatus = strtolower(trim(strval($sa["solic_status"] ?? $sa["status"] ?? "")));
										$link        = $docCaminho !== "" ? rtrim($baseContex, "/") . "/" . ltrim(str_replace("\\", "/", $docCaminho), "/") : "";
										$statusMap   = [
											"concluido" => ["Concluído", "bg-green-100 text-green-800 border-green-200"],
											"assinado"  => ["Assinado",  "bg-green-100 text-green-800 border-green-200"],
											"pendente"  => ["Pendente",  "bg-yellow-50 text-yellow-800 border-yellow-200"],
											"cancelado" => ["Cancelado", "bg-gray-100 text-gray-600 border-gray-200"],
											"expirado"  => ["Expirado",  "bg-red-100 text-red-800 border-red-200"],
										];
										[$stTxt, $stClass] = $statusMap[$solicStatus] ?? [ucfirst($solicStatus ?: "—"), "bg-gray-100 text-gray-600 border-gray-200"];
									?>
									<tr class="hover:bg-gray-50 transition-colors">
										<td class="px-6 py-4">
											<div class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($docNome !== "" ? $docNome : "(Sem nome)"); ?></div>
										</td>
										<td class="px-6 py-4 text-sm text-gray-700">
											<?php echo $docData !== "" ? htmlspecialchars(date("d/m/Y H:i", strtotime($docData))) : "—"; ?>
										</td>
										<td class="px-6 py-4 text-sm text-gray-700">
											<?php echo $dataAssina !== "" && $dataAssina !== "0000-00-00 00:00:00" ? htmlspecialchars(date("d/m/Y H:i", strtotime($dataAssina))) : "—"; ?>
										</td>
										<td class="px-6 py-4">
											<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $stClass; ?>">
												<?php echo $stTxt; ?>
											</span>
										</td>
										<td class="px-6 py-4 text-right">
											<?php if ($link !== ""): ?>
												<div class="inline-flex items-center justify-end gap-4">
													<a href="<?php echo htmlspecialchars($link); ?>" target="_blank" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 font-semibold text-sm">
														<i class="fas fa-eye"></i> Abrir
													</a>
													<a href="<?php echo htmlspecialchars($link); ?>" download class="inline-flex items-center gap-1 text-gray-600 hover:text-gray-800 font-semibold text-sm">
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
			</div>
		<?php endif; ?>

		<!-- ─── SEÇÃO: FUNCIONÁRIO ──────────────────────────────────── -->
		<?php if ($funcionarioId > 0 && $tipoPessoa === "funcionario"): ?>
			<?php
			$funcNome = "";
			foreach ($funcionarios as $f) { if ($f["id"] === $funcionarioId) { $funcNome = $f["label"]; break; } }
			?>
			<div class="mb-8">
				<h2 class="text-lg font-bold text-gray-700 mb-3 flex items-center gap-2">
					<i class="fas fa-user text-blue-500"></i> Documentos do Funcionário
					<?php if ($funcNome !== ""): ?>
						<span class="text-sm font-normal text-gray-500">— <?php echo htmlspecialchars($funcNome); ?></span>
					<?php endif; ?>
				</h2>

				<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-4">
					<div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
						<div class="font-semibold text-gray-800 text-sm">Cadastrados no sistema</div>
						<span class="text-xs text-gray-500"><?php echo $totalDb; ?> itens</span>
					</div>
					<?php $renderTabela($docs, false); ?>
				</div>

				<?php if ($tab !== "assinados" && $tab !== "expirados"): ?>
					<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
						<div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
							<div class="font-semibold text-gray-800 text-sm">Arquivos na pasta sem cadastro</div>
							<span class="text-xs text-gray-500"><?php echo $totalPasta; ?> itens</span>
						</div>
						<?php $renderArquivosSemCadastro($arquivosSemCadastro); ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<!-- ─── SEÇÃO: EMPRESA ──────────────────────────────────────── -->
		<?php if ($empresaId > 0): ?>
			<div class="mb-8">
				<h2 class="text-lg font-bold text-gray-700 mb-3 flex items-center gap-2">
					<i class="fas fa-building text-indigo-500"></i> Documentos da Empresa
					<span class="text-sm font-normal text-gray-500">
						<?php foreach ($empresas as $e) { if ($e["id"] === $empresaId) { echo "— " . htmlspecialchars($e["nome"]); break; } } ?>
					</span>
				</h2>

				<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-4">
					<div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
						<div class="font-semibold text-gray-800 text-sm">Cadastrados no sistema</div>
						<span class="text-xs text-gray-500"><?php echo $totalEmp; ?> itens</span>
					</div>
					<?php $renderTabela($docsEmpresa, true); ?>
				</div>

				<?php if ($tab !== "assinados" && $tab !== "expirados"): ?>
					<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
						<div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
							<div class="font-semibold text-gray-800 text-sm">Arquivos na pasta sem cadastro</div>
							<span class="text-xs text-gray-500"><?php echo $totalEmpPasta; ?> itens</span>
						</div>
						<?php $renderArquivosSemCadastro($arquivosEmpresaSemCadastro); ?>
					</div>
				<?php endif; ?>
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
(function () {
	if (!window.jQuery || !jQuery.fn || typeof jQuery.fn.select2 !== "function") return;

	function initSelect2($el) {
		if (!$el.length) return;
		$el.select2({ placeholder: "Selecione", allowClear: true, width: "100%", language: "pt-BR", minimumResultsForSearch: 0, dropdownAutoWidth: false, dropdownParent: $el.closest("form") });
		$el.on("select2:open", function () {
			var $c = $el.next(".select2"), w = $c.length ? $c.outerWidth() : null;
			var $dd = jQuery(".select2-container--open .select2-dropdown");
			if (w && $dd.length) { $dd.css({ width: w + "px", minWidth: w + "px" }); }
		});
	}

	initSelect2(jQuery("#filtro_empresa"));
	initSelect2(jQuery("#filtro_funcionario"));

	// Atualiza tipo_pessoa conforme funcionário selecionado
	jQuery("#filtro_funcionario").on("change", function () {
		var $opt  = jQuery(this).find("option:selected");
		var tipo  = $opt.data("tipo") || "funcionario";
		jQuery("#campo_tipo_pessoa").val(tipo);
	});

	// Ao mudar empresa: limpa funcionário e submete o formulário
	jQuery("#filtro_empresa").on("change", function () {
		jQuery("#filtro_funcionario").val("").trigger("change");
		jQuery("#campo_tipo_pessoa").val("funcionario");
		jQuery("#formFiltros").submit();
	});

	// Ao mudar origem (só existe quando sem empresa selecionada): submete
	jQuery("select[name='origem']:not(:disabled)").on("change", function () {
		jQuery("#filtro_funcionario").val("").trigger("change");
		var origem = jQuery(this).val();
		jQuery("#campo_tipo_pessoa").val(origem === "externa" ? "externo" : "funcionario");
		jQuery("#formFiltros").submit();
	});
})();
</script>

<?php include_once "componentes/layout_footer.php"; ?>
