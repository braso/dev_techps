<?php
include_once "../../conecta.php";
include_once "../componentes/layout_header.php";
$result = query(
    "SELECT enti_nb_id, enti_tx_nome, enti_tx_cpf, enti_tx_email, enti_tx_rg
        FROM entidade
        ORDER BY enti_tx_nome ASC"
);
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Funcionários</h1>
    <p class="text-gray-600 mt-1">Listagem de colaboradores cadastrados no sistema.</p>
</div>

<?php if(mysqli_num_rows($result) > 0): ?>
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
    <?php while($row = mysqli_fetch_assoc($result)): ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 font-bold border border-gray-200">
                    <?php echo strtoupper(substr(($row['enti_tx_nome'] ?? ''), 0, 1)); ?>
                </div>
                <div class="min-w-0">
                    <div class="font-semibold text-gray-900 text-sm truncate" title="<?php echo $row['enti_tx_nome'] ?? ''; ?>">
                        <?php echo $row['enti_tx_nome'] ?? ''; ?>
                    </div>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center gap-2 text-gray-700">
                            <div class="w-5 text-gray-400 text-center"><i class="fas fa-id-card"></i></div>
                            <div class="font-mono truncate"><?php echo $row['enti_tx_cpf'] ?? ''; ?></div>
                        </div>
                        <div class="flex items-center gap-2 text-gray-700">
                            <div class="w-5 text-gray-400 text-center"><i class="fas fa-envelope"></i></div>
                            <div class="truncate" title="<?php echo $row['enti_tx_email'] ?? ''; ?>">
                                <?php echo !empty($row['enti_tx_email']) ? $row['enti_tx_email'] : "—"; ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-gray-700">
                            <div class="w-5 text-gray-400 text-center"><i class="fas fa-address-card"></i></div>
                            <div class="font-mono truncate"><?php echo $row['enti_tx_rg'] ?? "—"; ?></div>
                        </div>
                    </div>
                </div>
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
        <p class="text-gray-500 mt-2">Não há registros de funcionários no momento.</p>
    </div>
<?php endif; ?>

<?php include_once "../componentes/layout_footer.php"; ?>
