<?php
include_once "../conecta.php";
include_once "componentes/layout_header.php";

// Parâmetros de filtro
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_assinatura = isset($_GET['assinatura']) ? $_GET['assinatura'] : '';
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$busca = isset($_GET['busca']) ? $_GET['busca'] : '';
$filtro_tipo_documento = intval($_GET['tipo_documento'] ?? 0);
$filtro_funcionario = intval($_GET['funcionario'] ?? 0);

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

$funcionariosFiltro = [];
$resFunc = mysqli_query($conn, "
    SELECT DISTINCT e.enti_nb_id, e.enti_tx_nome, e.enti_tx_email, e.enti_tx_cpf, e.enti_tx_matricula
    FROM assinantes a
    JOIN entidade e ON e.enti_nb_id = a.enti_nb_id
    WHERE a.enti_nb_id IS NOT NULL
    ORDER BY e.enti_tx_nome ASC
");
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
        $label = $nome;
        if($cpf !== ""){ $label .= " | CPF: " . $cpf; }
        if($mat !== ""){ $label .= " | Mat: " . $mat; }
        if($email !== ""){ $label .= " | " . $email; }
        $funcionariosFiltro[] = ["id" => $id, "label" => $label];
    }
}

// Construção da query
$where_clauses = ["1=1"];

if ($filtro_status) {
    if ($filtro_status == 'concluido') {
        $where_clauses[] = "(SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id) = (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado')";
    } elseif ($filtro_status == 'pendente') {
        $where_clauses[] = "(SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado') < (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id)";
    }
}

if ($filtro_assinatura) {
    if ($filtro_assinatura === 'assinados') {
        $where_clauses[] = "(SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado') > 0";
    } elseif ($filtro_assinatura === 'nao_assinados') {
        $where_clauses[] = "(SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado') = 0";
    }
}

if ($filtro_data_inicio) {
    $where_clauses[] = "DATE(s.created_at) >= '$filtro_data_inicio'";
}

if ($filtro_data_fim) {
    $where_clauses[] = "DATE(s.created_at) <= '$filtro_data_fim'";
}

if ($busca) {
    $where_clauses[] = "(s.nome_arquivo_original LIKE '%$busca%' OR s.id LIKE '%$busca%')";
}

if ($filtro_tipo_documento > 0) {
    $where_clauses[] = "s.tipo_documento_id = " . intval($filtro_tipo_documento);
}

if ($filtro_funcionario > 0) {
    $where_clauses[] = "EXISTS (SELECT 1 FROM assinantes a WHERE a.id_solicitacao = s.id AND a.enti_nb_id = " . intval($filtro_funcionario) . ")";
}

$where_sql = implode(' AND ', $where_clauses);

// Query principal
$sql = "
    SELECT s.*, 
    t.tipo_tx_nome as tipo_documento_nome,
    (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id) as total_assinantes,
    (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado') as total_assinados,
    (SELECT GROUP_CONCAT(CONCAT(nome, ' (', status, ')') SEPARATOR ', ') FROM assinantes a WHERE a.id_solicitacao = s.id) as assinantes_info
    FROM solicitacoes_assinatura s
    LEFT JOIN tipos_documentos t ON t.tipo_nb_id = s.tipo_documento_id
    WHERE $where_sql
    ORDER BY s.id DESC
";

$result = mysqli_query($conn, $sql);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Cabeçalho da Página -->
    <div class="mb-8 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Consulta de Documentos</h1>
            <p class="text-gray-500">Histórico e rastreamento de assinaturas</p>
        </div>
        <div class="flex gap-2">
            <a href="index.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Voltar
            </a>
            <a href="nova_assinatura.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors shadow-md">
                <i class="fas fa-plus mr-2"></i>Nova Assinatura
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" 
                        class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        placeholder="Nome do arquivo ou ID...">
                </div>
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                    <option value="">Todos</option>
                    <option value="concluido" <?php echo $filtro_status == 'concluido' ? 'selected' : ''; ?>>Concluído</option>
                    <option value="pendente" <?php echo $filtro_status == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Assinatura</label>
                <select name="assinatura" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                    <option value="">Todos</option>
                    <option value="assinados" <?php echo $filtro_assinatura == 'assinados' ? 'selected' : ''; ?>>Assinados</option>
                    <option value="nao_assinados" <?php echo $filtro_assinatura == 'nao_assinados' ? 'selected' : ''; ?>>Não assinados</option>
                </select>
            </div>

            <div class="md:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de documento</label>
                <select id="filtro_tipo_documento" name="tipo_documento" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                    <option value="">Todos</option>
                    <?php foreach ($tiposDocumentos as $t): ?>
                        <option value="<?php echo intval($t["id"]); ?>" <?php echo ($filtro_tipo_documento == intval($t["id"])) ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($t["nome"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="md:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Funcionário</label>
                <select id="filtro_funcionario" name="funcionario" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                    <option value="">Todos</option>
                    <?php foreach ($funcionariosFiltro as $f): ?>
                        <option value="<?php echo intval($f["id"]); ?>" <?php echo ($filtro_funcionario == intval($f["id"])) ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($f["label"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="md:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Período</label>
                <div class="flex gap-2">
                    <input type="date" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                    <input type="date" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                </div>
            </div>

            <div class="md:col-span-4 flex gap-2 items-end">
                <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                    Filtrar
                </button>
                <a href="consultar.php" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600" title="Limpar Filtros">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Resultados -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase text-gray-500 font-semibold tracking-wider">
                        <th class="px-6 py-4">Documento</th>
                        <th class="px-6 py-4">Data Envio</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Progresso</th>
                        <th class="px-6 py-4">Signatários</th>
                        <th class="px-6 py-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): 
                            $completo = ($row['total_assinantes'] > 0 && $row['total_assinantes'] == $row['total_assinados']);
                            $progresso = $row['total_assinantes'] > 0 ? round(($row['total_assinados'] / $row['total_assinantes']) * 100) : 0;
                            $tipoDocNome = trim(strval($row["tipo_documento_nome"] ?? ""));
                            $validarIcp = strtolower(trim(strval($row["validar_icp"] ?? "nao")));
                            $statusFinal = strtolower(trim(strval($row["status_final"] ?? "")));
                            $icpFinalizado = ($statusFinal === "finalizado");
                            
                            // Formatar lista de signatários para exibição
                            $signatarios_raw = explode(', ', $row['assinantes_info']);
                            $signatarios_html = '';
                            $pendentes_count = 0;
                            
                            foreach ($signatarios_raw as $sig) {
                                if (strpos($sig, '(assinado)') !== false) {
                                    $signatarios_html .= '<span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded-full mr-1 mb-1 border border-green-200" title="Assinado"><i class="fas fa-check mr-1"></i>' . str_replace(' (assinado)', '', $sig) . '</span>';
                                } else {
                                    $signatarios_html .= '<span class="inline-block bg-yellow-50 text-yellow-800 text-xs px-2 py-0.5 rounded-full mr-1 mb-1 border border-yellow-200" title="Pendente"><i class="far fa-clock mr-1"></i>' . str_replace(' (pendente)', '', $sig) . '</span>';
                                    $pendentes_count++;
                                }
                            }
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="bg-blue-50 text-blue-600 h-10 w-10 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="far fa-file-pdf text-xl"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 truncate max-w-xs" title="<?php echo htmlspecialchars($row['nome_arquivo_original']); ?>">
                                            <?php echo htmlspecialchars($row['nome_arquivo_original']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">ID: #<?php echo $row['id']; ?><?php echo $tipoDocNome !== "" ? " • Tipo: " . htmlspecialchars($tipoDocNome) : ""; ?></p>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <?php if ($icpFinalizado): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-100 text-indigo-800 border border-indigo-200">
                                                    <i class="fas fa-certificate mr-1"></i> ICP-Brasil
                                                </span>
                                            <?php elseif ($validarIcp === "sim"): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                    <i class="fas fa-shield-alt mr-1"></i> ICP solicitado
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-50 text-gray-600 border border-gray-200">
                                                    <i class="fas fa-file-signature mr-1"></i> Assinatura simples
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($icpFinalizado): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        <span class="w-2 h-2 mr-1.5 bg-indigo-500 rounded-full"></span>
                                        Finalizado ICP
                                    </span>
                                <?php elseif ($completo && $validarIcp === "sim"): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-800">
                                        <span class="w-2 h-2 mr-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                                        Assinado (ICP pendente)
                                    </span>
                                <?php elseif ($completo): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <span class="w-2 h-2 mr-1.5 bg-green-500 rounded-full"></span>
                                        Concluído
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <span class="w-2 h-2 mr-1.5 bg-yellow-500 rounded-full animate-pulse"></span>
                                        Pendente
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="w-full bg-gray-200 rounded-full h-2.5 mb-1">
                                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $progresso; ?>%"></div>
                                </div>
                                <span class="text-xs text-gray-500"><?php echo $row['total_assinados']; ?> de <?php echo $row['total_assinantes']; ?> assinaturas</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap max-w-xs">
                                    <?php echo $signatarios_html; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="<?php echo $row['caminho_arquivo']; ?>" target="_blank" class="text-gray-400 hover:text-blue-600 transition-colors" title="Visualizar Documento">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($completo): ?>
                                        <a href="finalizar.php" class="text-gray-400 hover:text-purple-600 transition-colors" title="Ir para Finalização">
                                            <i class="fas fa-certificate"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="far fa-folder-open text-4xl mb-3 text-gray-300"></i>
                                    <p>Nenhum documento encontrado com os filtros selecionados.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginação (Simplificada para Demo) -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex items-center justify-between">
            <span class="text-sm text-gray-500">
                Mostrando <?php echo mysqli_num_rows($result); ?> registros
            </span>
            <!-- 
            <div class="flex gap-1">
                <button class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-100 text-sm" disabled>Anterior</button>
                <button class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-100 text-sm">Próxima</button>
            </div>
            -->
        </div>
    </div>

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
        const $tipo = jQuery("#filtro_tipo_documento");
        if($tipo.length){
            $tipo.select2({
                placeholder: "Todos",
                allowClear: true,
                width: "100%",
                language: "pt-BR",
                minimumResultsForSearch: 0,
                dropdownParent: jQuery("body")
            });
        }
        const $func = jQuery("#filtro_funcionario");
        if($func.length){
            $func.select2({
                placeholder: "Todos",
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
