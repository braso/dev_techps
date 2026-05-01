<?php
// Processa o POST antes de qualquer include para evitar o dispatcher do contex
$_msg     = "";
$_msgType = "success";

// Flag para saber se precisa redirecionar após salvar
$_redirect = false;

// Inclui só a conexão direta, sem o dispatcher
define('NO_DISPATCHER', true);

include_once "../conecta.php";

// Garante que a tabela existe
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS signatarios_externos (
        sign_nb_id        INT AUTO_INCREMENT PRIMARY KEY,
        sign_tx_nome      VARCHAR(255)  NOT NULL,
        sign_tx_rg        VARCHAR(30)   NULL,
        sign_tx_cpf       VARCHAR(20)   NULL,
        sign_tx_email     VARCHAR(255)  NULL,
        sign_tx_telefone  VARCHAR(100)  NULL,
        sign_tx_status    ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
        sign_tx_dataCadastro DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_cpf (sign_tx_cpf)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ── AÇÕES POST ────────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = trim($_POST["sign_acao"] ?? "");

    // Salvar (inserir ou editar)
    if ($acao === "salvar") {
        $id       = intval($_POST["sign_nb_id"] ?? 0);
        $nome     = trim($_POST["sign_tx_nome"]     ?? "");
        $rg       = trim($_POST["sign_tx_rg"]       ?? "");
        $cpf      = preg_replace('/\D/', '', trim($_POST["sign_tx_cpf"] ?? ""));
        $email    = trim($_POST["sign_tx_email"]    ?? "");
        $telefone = trim($_POST["sign_tx_telefone"] ?? "");
        $status   = in_array($_POST["sign_tx_status"] ?? "", ["ativo","inativo"]) ? $_POST["sign_tx_status"] : "ativo";

        if ($nome === "") {
            $_msg     = "O campo Nome Completo é obrigatório.";
            $_msgType = "error";
        } else {
            $cpfEsc      = mysqli_real_escape_string($conn, $cpf);
            $nomeEsc     = mysqli_real_escape_string($conn, $nome);
            $rgEsc       = mysqli_real_escape_string($conn, $rg);
            $emailEsc    = mysqli_real_escape_string($conn, $email);
            $telefoneEsc = mysqli_real_escape_string($conn, $telefone);

            if ($id > 0) {
                $sql = "UPDATE signatarios_externos SET
                            sign_tx_nome     = '$nomeEsc',
                            sign_tx_rg       = '$rgEsc',
                            sign_tx_cpf      = " . ($cpfEsc !== "" ? "'$cpfEsc'" : "NULL") . ",
                            sign_tx_email    = '$emailEsc',
                            sign_tx_telefone = '$telefoneEsc',
                            sign_tx_status   = '$status'
                        WHERE sign_nb_id = $id";
                if (mysqli_query($conn, $sql)) {
                    $_msg = "Signatário atualizado com sucesso.";
                } else {
                    $_msg     = "Erro ao atualizar: " . mysqli_error($conn);
                    $_msgType = "error";
                }
            } else {
                $sql = "INSERT INTO signatarios_externos
                            (sign_tx_nome, sign_tx_rg, sign_tx_cpf, sign_tx_email, sign_tx_telefone, sign_tx_status)
                        VALUES
                            ('$nomeEsc', '$rgEsc', " . ($cpfEsc !== "" ? "'$cpfEsc'" : "NULL") . ", '$emailEsc', '$telefoneEsc', '$status')";
                if (mysqli_query($conn, $sql)) {
                    $_msg = "Signatário cadastrado com sucesso.";
                } else {
                    $_msg     = "Erro ao cadastrar: " . mysqli_error($conn);
                    $_msgType = "error";
                }
            }
        }
    }

    // Inativar / Reativar
    if ($acao === "inativar" || $acao === "reativar") {
        $id         = intval($_POST["sign_nb_id"] ?? 0);
        $novoStatus = $acao === "inativar" ? "inativo" : "ativo";
        if ($id > 0) {
            mysqli_query($conn, "UPDATE signatarios_externos SET sign_tx_status = '$novoStatus' WHERE sign_nb_id = $id");
            $_msg = $acao === "inativar" ? "Signatário inativado." : "Signatário reativado.";
        }
    }
}

// ── BUSCA / PAGINAÇÃO ─────────────────────────────────────────────────────────
$busca        = trim($_GET["busca"] ?? "");
$pagina       = max(1, intval($_GET["pagina"] ?? 1));
$porPagina    = 15;
$offset       = ($pagina - 1) * $porPagina;
$filtroStatus = trim($_GET["filtro_status"] ?? "ativo");

$where = "1=1";
if ($busca !== "") {
    $bEsc  = mysqli_real_escape_string($conn, $busca);
    $where .= " AND (sign_tx_nome LIKE '%$bEsc%' OR sign_tx_cpf LIKE '%$bEsc%' OR sign_tx_email LIKE '%$bEsc%')";
}
if (in_array($filtroStatus, ["ativo","inativo"])) {
    $where .= " AND sign_tx_status = '$filtroStatus'";
}

$totalRes = mysqli_query($conn, "SELECT COUNT(*) as total FROM signatarios_externos WHERE $where");
$total    = intval(mysqli_fetch_assoc($totalRes)["total"] ?? 0);
$totalPag = max(1, ceil($total / $porPagina));

$lista = [];
$res   = mysqli_query($conn, "SELECT * FROM signatarios_externos WHERE $where ORDER BY sign_tx_nome ASC LIMIT $porPagina OFFSET $offset");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $lista[] = $r;
    }
}

// Edição: carrega registro
$editando = null;
if (isset($_GET["editar"])) {
    $idEdit  = intval($_GET["editar"]);
    $resEdit = mysqli_query($conn, "SELECT * FROM signatarios_externos WHERE sign_nb_id = $idEdit LIMIT 1");
    if ($resEdit) {
        $editando = mysqli_fetch_assoc($resEdit);
    }
}

// Passa mensagem para a view
$msg     = $_msg;
$msgType = $_msgType;

include_once "componentes/layout_header.php";
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Cabeçalho -->
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Signatários Externos</h1>
            <p class="text-gray-500 text-sm mt-1">Cadastro de pessoas externas que podem assinar documentos.</p>
        </div>
        <a href="index.php" class="inline-flex items-center gap-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>

    <?php if ($msg !== ""): ?>
    <div class="mb-6 flex items-center gap-3 px-4 py-3 rounded-lg border <?php echo $msgType === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700'; ?>">
        <i class="fas <?php echo $msgType === 'error' ? 'fa-circle-xmark' : 'fa-circle-check'; ?>"></i>
        <span><?php echo htmlspecialchars($msg); ?></span>
    </div>
    <?php endif; ?>

    <!-- Formulário -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
        <h2 class="text-base font-semibold text-gray-700 mb-5 flex items-center gap-2">
            <i class="fas <?php echo $editando ? 'fa-pen-to-square' : 'fa-user-plus'; ?> text-blue-500"></i>
            <?php echo $editando ? "Editar Signatário" : "Novo Signatário"; ?>
        </h2>

        <form method="POST" action="cadastro_signatario.php<?php echo $busca !== "" ? '?busca='.urlencode($busca) : ''; ?>">
            <input type="hidden" name="sign_acao" value="salvar">
            <?php if ($editando): ?>
                <input type="hidden" name="sign_nb_id" value="<?php echo intval($editando['sign_nb_id']); ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

                <!-- Nome -->
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nome Completo <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="sign_tx_nome" required maxlength="255"
                        value="<?php echo htmlspecialchars($editando['sign_tx_nome'] ?? '', ENT_QUOTES); ?>"
                        placeholder="Nome completo do signatário"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="sign_tx_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white text-sm">
                        <option value="ativo"   <?php echo ($editando['sign_tx_status'] ?? 'ativo') === 'ativo'   ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inativo" <?php echo ($editando['sign_tx_status'] ?? '')      === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </div>

                <!-- RG -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">RG</label>
                    <input type="text" name="sign_tx_rg" maxlength="30" id="campo_rg"
                        value="<?php echo htmlspecialchars($editando['sign_tx_rg'] ?? '', ENT_QUOTES); ?>"
                        placeholder="00.000.000-0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                </div>

                <!-- CPF -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                    <input type="text" name="sign_tx_cpf" maxlength="14" id="campo_cpf"
                        value="<?php
                            $cpfVal = $editando['sign_tx_cpf'] ?? '';
                            if (strlen($cpfVal) === 11) {
                                $cpfVal = substr($cpfVal,0,3).'.'.substr($cpfVal,3,3).'.'.substr($cpfVal,6,3).'-'.substr($cpfVal,9,2);
                            }
                            echo htmlspecialchars($cpfVal, ENT_QUOTES);
                        ?>"
                        placeholder="000.000.000-00"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                </div>

                <!-- E-mail -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                    <input type="email" name="sign_tx_email" maxlength="255"
                        value="<?php echo htmlspecialchars($editando['sign_tx_email'] ?? '', ENT_QUOTES); ?>"
                        placeholder="email@exemplo.com"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                </div>

                <!-- Telefone -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefone(s)</label>
                    <input type="text" name="sign_tx_telefone" maxlength="100" id="campo_telefone"
                        value="<?php echo htmlspecialchars($editando['sign_tx_telefone'] ?? '', ENT_QUOTES); ?>"
                        placeholder="(00) 00000-0000"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                    <p class="text-xs text-gray-400 mt-1">Separe múltiplos números com vírgula.</p>
                </div>

            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit"
                    class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium shadow-sm">
                    <i class="fas fa-floppy-disk"></i>
                    <?php echo $editando ? "Salvar Alterações" : "Cadastrar"; ?>
                </button>
                <?php if ($editando): ?>
                <a href="cadastro_signatario.php"
                    class="inline-flex items-center gap-2 bg-gray-100 text-gray-700 px-5 py-2 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">
                    <i class="fas fa-xmark"></i> Cancelar
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Filtros / Busca -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-4">
        <form method="GET" action="cadastro_signatario.php" class="flex flex-col sm:flex-row gap-3 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-search text-sm"></i>
                    </span>
                    <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>"
                        placeholder="Nome, CPF ou e-mail..."
                        class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="filtro_status" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white text-sm">
                    <option value="ativo"   <?php echo $filtroStatus === 'ativo'   ? 'selected' : ''; ?>>Ativos</option>
                    <option value="inativo" <?php echo $filtroStatus === 'inativo' ? 'selected' : ''; ?>>Inativos</option>
                    <option value=""        <?php echo $filtroStatus === ''        ? 'selected' : ''; ?>>Todos</option>
                </select>
            </div>
            <button type="submit"
                class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <?php if ($busca !== "" || $filtroStatus !== "ativo"): ?>
            <a href="cadastro_signatario.php"
                class="inline-flex items-center gap-2 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors text-sm">
                <i class="fas fa-xmark"></i> Limpar
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabela -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <span class="text-sm text-gray-500">
                <?php echo $total; ?> signatário<?php echo $total !== 1 ? 's' : ''; ?> encontrado<?php echo $total !== 1 ? 's' : ''; ?>
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase text-gray-500 font-semibold tracking-wider">
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Nome Completo</th>
                        <th class="px-4 py-3">RG</th>
                        <th class="px-4 py-3">CPF</th>
                        <th class="px-4 py-3">E-mail</th>
                        <th class="px-4 py-3">Telefone</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($lista)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400">
                            <i class="fas fa-users-slash text-3xl mb-2 block"></i>
                            Nenhum signatário encontrado.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($lista as $s):
                        $cpfFmt = $s['sign_tx_cpf'] ?? '';
                        if (strlen($cpfFmt) === 11) {
                            $cpfFmt = substr($cpfFmt,0,3).'.'.substr($cpfFmt,3,3).'.'.substr($cpfFmt,6,3).'-'.substr($cpfFmt,9,2);
                        }
                        $ativo = $s['sign_tx_status'] === 'ativo';
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors <?php echo !$ativo ? 'opacity-60' : ''; ?>">
                        <td class="px-4 py-3 text-gray-400 font-mono text-xs"><?php echo intval($s['sign_nb_id']); ?></td>
                        <td class="px-4 py-3 font-medium text-gray-800"><?php echo htmlspecialchars($s['sign_tx_nome']); ?></td>
                        <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($s['sign_tx_rg'] ?: '—'); ?></td>
                        <td class="px-4 py-3 text-gray-600 font-mono"><?php echo htmlspecialchars($cpfFmt ?: '—'); ?></td>
                        <td class="px-4 py-3 text-gray-600">
                            <?php if ($s['sign_tx_email']): ?>
                                <a href="mailto:<?php echo htmlspecialchars($s['sign_tx_email']); ?>" class="text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($s['sign_tx_email']); ?>
                                </a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($s['sign_tx_telefone'] ?: '—'); ?></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                                <?php echo $ativo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?php echo $ativo ? 'bg-green-500' : 'bg-gray-400'; ?>"></span>
                                <?php echo $ativo ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <!-- Editar -->
                                <a href="cadastro_signatario.php?editar=<?php echo intval($s['sign_nb_id']); ?><?php echo $busca !== '' ? '&busca='.urlencode($busca) : ''; ?>&filtro_status=<?php echo urlencode($filtroStatus); ?>"
                                    class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg transition-colors" title="Editar">
                                    <i class="fas fa-pen-to-square"></i>
                                </a>
                                <!-- Inativar / Reativar -->
                                <form method="POST" action="cadastro_signatario.php?busca=<?php echo urlencode($busca); ?>&filtro_status=<?php echo urlencode($filtroStatus); ?>&pagina=<?php echo $pagina; ?>"
                                    onsubmit="return confirm('<?php echo $ativo ? 'Inativar este signatário?' : 'Reativar este signatário?'; ?>')">
                                    <input type="hidden" name="sign_acao" value="<?php echo $ativo ? 'inativar' : 'reativar'; ?>">
                                    <input type="hidden" name="sign_nb_id" value="<?php echo intval($s['sign_nb_id']); ?>">
                                    <button type="submit"
                                        class="p-1.5 <?php echo $ativo ? 'text-red-400 hover:bg-red-50' : 'text-green-500 hover:bg-green-50'; ?> rounded-lg transition-colors"
                                        title="<?php echo $ativo ? 'Inativar' : 'Reativar'; ?>">
                                        <i class="fas <?php echo $ativo ? 'fa-ban' : 'fa-rotate-left'; ?>"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($totalPag > 1): ?>
        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
            <span>Página <?php echo $pagina; ?> de <?php echo $totalPag; ?></span>
            <div class="flex gap-1">
                <?php
                $queryBase = http_build_query(array_filter([
                    'busca'         => $busca,
                    'filtro_status' => $filtroStatus,
                ]));
                for ($p = 1; $p <= $totalPag; $p++):
                    $active = $p === $pagina;
                ?>
                <a href="cadastro_signatario.php?<?php echo $queryBase; ?>&pagina=<?php echo $p; ?>"
                    class="px-3 py-1 rounded-lg <?php echo $active ? 'bg-blue-600 text-white font-semibold' : 'hover:bg-gray-100 text-gray-600'; ?>">
                    <?php echo $p; ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
// Máscara CPF
document.getElementById('campo_cpf')?.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').substring(0, 11);
    if (v.length > 9)      v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/^(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
    else if (v.length > 3) v = v.replace(/^(\d{3})(\d{0,3})/, '$1.$2');
    this.value = v;
});

// Máscara Telefone
document.getElementById('campo_telefone')?.addEventListener('input', function () {
    // Permite múltiplos números separados por vírgula — aplica máscara só no último segmento
    let parts = this.value.split(',');
    let last  = parts[parts.length - 1].replace(/\D/g, '').substring(0, 11);
    if (last.length > 10)     last = last.replace(/^(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    else if (last.length > 6) last = last.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    else if (last.length > 2) last = last.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
    parts[parts.length - 1] = last;
    this.value = parts.join(', ');
});
</script>

<?php include_once "componentes/layout_footer.php"; ?>
