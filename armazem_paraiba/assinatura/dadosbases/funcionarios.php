<?php
include_once "../../conecta.php";
include_once "../layout_header.php";

// Função para listar funcionários ativos
function listarFuncionarios($conn) {
    $sql = "SELECT enti_nb_id, enti_tx_nome, enti_tx_cpf, enti_tx_email, enti_tx_status, enti_tx_ocupacao, enti_tx_matricula 
            FROM entidade 
            WHERE enti_tx_status != 'inativo' 
            ORDER BY enti_tx_nome ASC";
    return mysqli_query($conn, $sql);
}

// Função para buscar funcionário por ID
function getFuncionarioById($conn, $id) {
    $id = (int)$id;
    $sql = "SELECT enti_nb_id, enti_tx_nome, enti_tx_cpf, enti_tx_email, enti_tx_status, enti_tx_ocupacao, enti_tx_matricula 
            FROM entidade 
            WHERE enti_nb_id = $id";
    return mysqli_fetch_assoc(mysqli_query($conn, $sql));
}

// Busca funcionários ativos
$result = listarFuncionarios($conn);
?>

<div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Funcionários</h1>
        <p class="text-gray-600 mt-1">Listagem de colaboradores cadastrados no sistema.</p>
    </div>
    
    <!-- Barra de Pesquisa (Visual por enquanto) -->
    <div class="relative w-full md:w-64">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
            <i class="fas fa-search"></i>
        </span>
        <input type="text" placeholder="Buscar funcionário..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm bg-white shadow-sm">
    </div>
</div>

<?php if(mysqli_num_rows($result) > 0): ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php while($row = mysqli_fetch_assoc($result)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-all duration-200 relative overflow-hidden group">
            <!-- Top Border Accent -->
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity"></div>
            
            <div class="flex items-start justify-between mb-5">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-bold text-xl border border-gray-200 shadow-inner">
                        <?php echo strtoupper(substr($row['enti_tx_nome'], 0, 1)); ?>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 leading-tight text-lg line-clamp-1" title="<?php echo $row['enti_tx_nome']; ?>">
                            <?php echo $row['enti_tx_nome']; ?>
                        </h3>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mt-1">
                            <?php echo $row['enti_tx_ocupacao'] ?: 'COLABORADOR'; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="space-y-3">
                <div class="flex items-center text-gray-600 text-sm bg-gray-50 p-2 rounded-lg border border-gray-100">
                    <div class="w-8 flex justify-center text-gray-400"><i class="fas fa-id-card"></i></div>
                    <span class="font-mono text-gray-700 font-medium"><?php echo $row['enti_tx_cpf']; ?></span>
                </div>
                
                <div class="flex items-center text-gray-600 text-sm bg-gray-50 p-2 rounded-lg border border-gray-100 group/email">
                    <div class="w-8 flex justify-center text-gray-400 group-hover/email:text-blue-500 transition-colors"><i class="fas fa-envelope"></i></div>
                    <span class="truncate w-full" title="<?php echo $row['enti_tx_email']; ?>">
                        <?php echo $row['enti_tx_email'] ?: '<span class="text-gray-400 italic">E-mail não cadastrado</span>'; ?>
                    </span>
                </div>
            </div>

            <div class="mt-5 pt-4 border-t border-gray-100 flex justify-between items-center">
                <div class="flex flex-col gap-1">
                    <span class="w-fit px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $row['enti_tx_status'] == 'ativo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'; ?>">
                        <i class="fas fa-circle text-[8px] mr-1"></i>
                        <?php echo ucfirst($row['enti_tx_status']); ?>
                    </span>
                    <?php if(!empty($row['enti_tx_matricula'])): ?>
                    <span class="text-xs text-gray-500 font-mono ml-1">
                        MAT: <?php echo $row['enti_tx_matricula']; ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <a href="../../gerar_espelho_assinatura.php?id_motorista=<?php echo $row['enti_nb_id']; ?>" target="_blank" class="bg-blue-50 hover:bg-blue-100 text-blue-600 hover:text-blue-800 text-sm font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-colors border border-blue-200">
                    <i class="fas fa-print"></i>
                    <span>Espelho</span>
                </a>
            </div>
        </div>
    <?php endwhile; ?>
</div>
<?php else: ?>
    <div class="bg-white rounded-lg shadow-sm p-12 text-center border border-gray-200">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400">
            <i class="fas fa-users text-2xl"></i>
        </div>
        <h3 class="text-lg font-medium text-gray-900">Nenhum funcionário encontrado</h3>
        <p class="text-gray-500 mt-2">Não há registros de funcionários ativos no momento.</p>
    </div>
<?php endif; ?>

<?php include_once "../layout_footer.php"; ?>