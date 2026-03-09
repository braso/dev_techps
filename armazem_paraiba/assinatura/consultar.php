<?php
include_once "../conecta.php";
include_once "componentes/layout_header.php";

// Parâmetros de filtro
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$busca = isset($_GET['busca']) ? $_GET['busca'] : '';

// Construção da query
$where_clauses = ["1=1"];

if ($filtro_status) {
    if ($filtro_status == 'concluido') {
        $where_clauses[] = "(SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id) = (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado')";
    } elseif ($filtro_status == 'pendente') {
        $where_clauses[] = "(SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado') < (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id)";
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

$where_sql = implode(' AND ', $where_clauses);

// Query principal
$sql = "
    SELECT s.*, 
    (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id) as total_assinantes,
    (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado') as total_assinados,
    (SELECT GROUP_CONCAT(CONCAT(nome, ' (', status, ')') SEPARATOR ', ') FROM assinantes a WHERE a.id_solicitacao = s.id) as assinantes_info
    FROM solicitacoes_assinatura s
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
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
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
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                    <option value="">Todos</option>
                    <option value="concluido" <?php echo $filtro_status == 'concluido' ? 'selected' : ''; ?>>Concluído</option>
                    <option value="pendente" <?php echo $filtro_status == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Período</label>
                <div class="flex gap-2">
                    <input type="date" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                    <input type="date" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                </div>
            </div>

            <div class="flex gap-2">
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
                                        <p class="text-xs text-gray-500">ID: #<?php echo $row['id']; ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($completo): ?>
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

<?php include_once "componentes/layout_footer.php"; ?>