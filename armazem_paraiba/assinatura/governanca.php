<?php
include_once "../conecta.php";

function assinatura_ensureSetorResponsavelSchema(): void{
    $sql = "CREATE TABLE IF NOT EXISTS setor_responsavel (
        sres_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        sres_nb_setor_id INT NOT NULL,
        sres_nb_entidade_id INT NOT NULL,
        sres_tx_assinar_governanca ENUM('sim','nao') NOT NULL DEFAULT 'nao',
        sres_nb_ordem INT NOT NULL DEFAULT 0,
        sres_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
        sres_tx_dataCadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_setor_entidade (sres_nb_setor_id, sres_nb_entidade_id),
        INDEX ix_setor (sres_nb_setor_id),
        INDEX ix_entidade (sres_nb_entidade_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    query($sql);

    $dbRow = mysqli_fetch_assoc(query("SELECT DATABASE() AS db"));
    $db = strval($dbRow["db"] ?? "");
    if($db === ""){
        return;
    }

    $exists = mysqli_fetch_assoc(query(
        "SELECT 1 AS ok
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = 'setor_responsavel'
            AND COLUMN_NAME = 'sres_nb_ordem'
        LIMIT 1",
        "s",
        [$db]
    ));
    if(empty($exists)){
        query("ALTER TABLE setor_responsavel ADD COLUMN sres_nb_ordem INT NOT NULL DEFAULT 0");
    }
}

function assinatura_ensureOperacaoResponsavelSchema(): void{
    $dbRow = mysqli_fetch_assoc(query("SELECT DATABASE() AS db"));
    $db = strval($dbRow["db"] ?? "");
    if($db === ""){
        return;
    }

    $exists = mysqli_fetch_assoc(query(
        "SELECT 1 AS ok
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = 'operacao_responsavel'
        LIMIT 1",
        "s",
        [$db]
    ));

    if(empty($exists)){
        query(
            "CREATE TABLE IF NOT EXISTS operacao_responsavel (
                opre_nb_id INT AUTO_INCREMENT PRIMARY KEY,
                opre_nb_operacao_id INT NOT NULL,
                opre_nb_entidade_id INT NOT NULL,
                opre_tx_assinar_governanca ENUM('sim','nao') NOT NULL DEFAULT 'nao',
                opre_nb_ordem INT NOT NULL DEFAULT 0,
                opre_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
                opre_tx_dataCadastro DATETIME NOT NULL,
                UNIQUE KEY uniq_operacao_entidade (opre_nb_operacao_id, opre_nb_entidade_id),
                KEY idx_operacao (opre_nb_operacao_id),
                KEY idx_entidade (opre_nb_entidade_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

function assinatura_ensureEntidadeResponsavelCols(): void{
    $dbRow = mysqli_fetch_assoc(query("SELECT DATABASE() AS db"));
    $db = strval($dbRow["db"] ?? "");
    if($db === ""){
        return;
    }

    $cols = mysqli_fetch_all(query(
        "SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = 'entidade'",
        "s",
        [$db]
    ), MYSQLI_ASSOC);
    $colNames = array_map(fn($r) => strval($r["COLUMN_NAME"] ?? ""), $cols ?: []);
    $has = array_flip($colNames);

    if(!isset($has["enti_respFuncionario_id"])){
        query("ALTER TABLE entidade ADD COLUMN enti_respFuncionario_id INT NULL");
    }
    if(!isset($has["enti_respFuncionario_ids"])){
        query("ALTER TABLE entidade ADD COLUMN enti_respFuncionario_ids TEXT NULL");
    }
    if(!isset($has["enti_respSetor_ids"])){
        query("ALTER TABLE entidade ADD COLUMN enti_respSetor_ids TEXT NULL");
    }
    if(!isset($has["enti_respCargo_ids"])){
        query("ALTER TABLE entidade ADD COLUMN enti_respCargo_ids TEXT NULL");
    }
}

if(($_GET["ajax"] ?? "") === "funcionario_info"){
    header("Content-Type: application/json; charset=utf-8");
    assinatura_ensureSetorResponsavelSchema();
    assinatura_ensureOperacaoResponsavelSchema();
    assinatura_ensureEntidadeResponsavelCols();

    $id = intval($_GET["id"] ?? 0);
    if($id <= 0){
        echo json_encode(["ok" => false]);
        exit;
    }

    $row = mysqli_fetch_assoc(query(
        "SELECT
            e.enti_nb_id,
            e.enti_tx_nome,
            e.enti_tx_email,
            e.enti_setor_id,
            g.grup_tx_nome AS setor_nome,
            e.enti_tx_tipoOperacao,
            o.oper_tx_nome AS cargo_nome,
            e.enti_respFuncionario_id,
            e.enti_respFuncionario_ids,
            e.enti_respSetor_ids,
            e.enti_respCargo_ids
        FROM entidade e
        LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
        LEFT JOIN operacao o ON o.oper_nb_id = e.enti_tx_tipoOperacao
        WHERE e.enti_nb_id = ?
        LIMIT 1",
        "i",
        [$id]
    ));

    if(empty($row)){
        echo json_encode(["ok" => false]);
        exit;
    }

    $setorId = intval($row["enti_setor_id"] ?? 0);
    $cargoId = intval($row["enti_tx_tipoOperacao"] ?? 0);
    $responsaveis = [];
    $respIds = [];
    $csvA = trim(strval($row["enti_respSetor_ids"] ?? ""));
    $csvB = trim(strval($row["enti_respCargo_ids"] ?? ""));
    $respSetorIds = [];
    $respCargoIds = [];
    foreach([$csvA, $csvB] as $csvX){
        if($csvX === ""){ continue; }
        foreach(explode(",", $csvX) as $p){
            $v = intval(trim($p));
            if($v > 0){ $respIds[] = $v; }
        }
    }
    if($csvA !== ""){
        foreach(explode(",", $csvA) as $p){
            $v = intval(trim($p));
            if($v > 0){ $respSetorIds[$v] = true; }
        }
    }
    if($csvB !== ""){
        foreach(explode(",", $csvB) as $p){
            $v = intval(trim($p));
            if($v > 0){ $respCargoIds[$v] = true; }
        }
    }
    $respIds = array_values(array_unique(array_filter(array_map("intval", $respIds), fn($v) => $v > 0)));

    if(!empty($respIds)){
        $idsSql = implode(",", $respIds);
        $responsaveis = mysqli_fetch_all(query(
            "SELECT
                e.enti_nb_id AS id,
                e.enti_tx_nome AS nome,
                e.enti_tx_email AS email,
                g.grup_tx_nome AS setor_nome,
                o.oper_tx_nome AS cargo_nome
            FROM entidade e
            LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
            LEFT JOIN operacao o ON o.oper_nb_id = e.enti_tx_tipoOperacao
            WHERE e.enti_nb_id IN ($idsSql)
            AND e.enti_tx_status = 'ativo'
            ORDER BY e.enti_tx_nome ASC"
        ), MYSQLI_ASSOC);
        $responsaveis = array_map(function($r) use ($respSetorIds, $respCargoIds){
            $id = intval($r["id"] ?? 0);
            $origens = [];
            if($id > 0 && isset($respSetorIds[$id])){ $origens[] = "setor"; }
            if($id > 0 && isset($respCargoIds[$id])){ $origens[] = "cargo"; }
            $r["origem"] = implode(",", array_values(array_unique($origens)));
            return $r;
        }, $responsaveis ?: []);
    } else {
        $map = [];
        if($setorId > 0){
            $rowsSet = mysqli_fetch_all(query(
                "SELECT
                    e.enti_nb_id AS id,
                    e.enti_tx_nome AS nome,
                    e.enti_tx_email AS email,
                    g.grup_tx_nome AS setor_nome,
                    o.oper_tx_nome AS cargo_nome,
                    (CASE WHEN sr.sres_nb_ordem <= 0 THEN 999999 ELSE sr.sres_nb_ordem END) AS ord
                FROM setor_responsavel sr
                INNER JOIN entidade e ON e.enti_nb_id = sr.sres_nb_entidade_id
                LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
                LEFT JOIN operacao o ON o.oper_nb_id = e.enti_tx_tipoOperacao
                WHERE sr.sres_nb_setor_id = ?
                AND sr.sres_tx_status = 'ativo'
                AND sr.sres_tx_assinar_governanca = 'sim'
                AND e.enti_tx_status = 'ativo'
                ORDER BY ord ASC, e.enti_tx_nome ASC",
                "i",
                [$setorId]
            ), MYSQLI_ASSOC);
            foreach(($rowsSet ?: []) as $r){
                $rid = intval($r["id"] ?? 0);
                if($rid <= 0){ continue; }
                $map[$rid] = [
                    "id" => $rid,
                    "nome" => $r["nome"] ?? "",
                    "email" => $r["email"] ?? "",
                    "setor_nome" => $r["setor_nome"] ?? "",
                    "cargo_nome" => $r["cargo_nome"] ?? "",
                    "ord" => intval($r["ord"] ?? 999999),
                    "origens" => ["setor"]
                ];
            }
        }
        if($cargoId > 0){
            $rowsCar = mysqli_fetch_all(query(
                "SELECT
                    e.enti_nb_id AS id,
                    e.enti_tx_nome AS nome,
                    e.enti_tx_email AS email,
                    g.grup_tx_nome AS setor_nome,
                    o.oper_tx_nome AS cargo_nome,
                    (CASE WHEN orv.opre_nb_ordem <= 0 THEN 999999 ELSE orv.opre_nb_ordem END) AS ord
                FROM operacao_responsavel orv
                INNER JOIN entidade e ON e.enti_nb_id = orv.opre_nb_entidade_id
                LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
                LEFT JOIN operacao o ON o.oper_nb_id = e.enti_tx_tipoOperacao
                WHERE orv.opre_nb_operacao_id = ?
                AND orv.opre_tx_status = 'ativo'
                AND orv.opre_tx_assinar_governanca = 'sim'
                AND e.enti_tx_status = 'ativo'
                ORDER BY ord ASC, e.enti_tx_nome ASC",
                "i",
                [$cargoId]
            ), MYSQLI_ASSOC);
            foreach(($rowsCar ?: []) as $r){
                $rid = intval($r["id"] ?? 0);
                if($rid <= 0){ continue; }
                if(!isset($map[$rid])){
                    $map[$rid] = [
                        "id" => $rid,
                        "nome" => $r["nome"] ?? "",
                        "email" => $r["email"] ?? "",
                        "setor_nome" => $r["setor_nome"] ?? "",
                        "cargo_nome" => $r["cargo_nome"] ?? "",
                        "ord" => intval($r["ord"] ?? 999999),
                        "origens" => ["cargo"]
                    ];
                } else {
                    $map[$rid]["origens"] = array_values(array_unique(array_merge($map[$rid]["origens"] ?? [], ["cargo"])));
                }
            }
        }
        $responsaveis = array_values($map);
        usort($responsaveis, function($a, $b){
            $ao = intval($a["ord"] ?? 999999);
            $bo = intval($b["ord"] ?? 999999);
            if($ao === $bo){
                return strcasecmp(strval($a["nome"] ?? ""), strval($b["nome"] ?? ""));
            }
            return $ao <=> $bo;
        });
        $responsaveis = array_map(function($r){
            return [
                "id" => intval($r["id"] ?? 0),
                "nome" => strval($r["nome"] ?? ""),
                "email" => strval($r["email"] ?? ""),
                "setor_nome" => strval($r["setor_nome"] ?? ""),
                "cargo_nome" => strval($r["cargo_nome"] ?? ""),
                "origem" => implode(",", array_values(array_unique(array_map("strval", $r["origens"] ?? []))))
            ];
        }, $responsaveis);
    }

    echo json_encode([
        "ok" => true,
        "funcionario" => [
            "id" => intval($row["enti_nb_id"] ?? 0),
            "nome" => strval($row["enti_tx_nome"] ?? ""),
            "email" => strval($row["enti_tx_email"] ?? "")
        ],
        "setor" => [
            "id" => $setorId,
            "nome" => strval($row["setor_nome"] ?? "")
        ],
        "cargo" => [
            "id" => $cargoId,
            "nome" => strval($row["cargo_nome"] ?? "")
        ],
        "responsaveis" => $responsaveis
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$tiposDocumentos = [];
$resTipos = query("SELECT tipo_nb_id, tipo_tx_nome FROM tipos_documentos WHERE tipo_tx_status = 'ativo' ORDER BY tipo_tx_nome ASC");
if($resTipos){
    while($r = mysqli_fetch_assoc($resTipos)){
        $id = intval($r["tipo_nb_id"] ?? 0);
        $nome = trim(strval($r["tipo_tx_nome"] ?? ""));
        if($id > 0 && $nome !== ""){
            $tiposDocumentos[] = ["id" => $id, "nome" => $nome];
        }
    }
}

include_once "componentes/layout_header.php";
?>
<!-- Tailwind CSS (Included in header) -->
<!-- FontAwesome (Included in header) -->
<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" />
<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" />
<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>
<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/js/select2.min.js" type="text/javascript"></script>
<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/js/i18n/pt-BR.js" type="text/javascript"></script>
<style>
    .drag-active {
        border-color: #3b82f6;
        background-color: #eff6ff;
    }

    .select2-container--default .select2-selection--single {
        height: 42px;
        border-radius: 0.5rem;
        border-color: #e5e7eb;
        background-color: #f9fafb;
        padding-top: 6px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        padding-left: 36px;
        color: #111827;
        line-height: 28px;
        font-size: 0.875rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
        right: 10px;
    }
</style>

<div class="bg-gray-50 py-10 px-4 font-sans">

    <div class="max-w-4xl w-full mx-auto bg-white shadow-xl rounded-2xl overflow-hidden">
        
        <!-- Header simplified -->
        <div class="bg-white px-8 py-6 border-b border-gray-100 flex justify-between items-center text-left">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Assinatura com Governança</h2>
                <p class="text-gray-500 text-sm">Envie um documento com mais de 1 signatário para validar e acompanhar o processo de assinatura (etapas, ordem e auditoria).</p>
            </div>
            <a href="index.php" class="text-gray-500 hover:text-blue-600 text-sm font-medium transition-colors flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-50">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <div class="p-8">
            
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] === 'success'): ?>
                    <div class="mb-8 p-4 rounded-xl bg-green-50 text-green-800 border border-green-200 flex items-center gap-3 shadow-sm">
                        <i class="fas fa-check-circle text-xl"></i>
                        <div>
                            <p class="font-bold">Sucesso!</p>
                            <p class="text-sm">Processo iniciado. O primeiro signatário foi notificado via e-mail.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mb-8 p-4 rounded-xl bg-red-50 text-red-800 border border-red-200 flex items-center gap-3 shadow-sm">
                        <i class="fas fa-exclamation-circle text-xl"></i>
                        <div>
                            <p class="font-bold">Erro ao enviar</p>
                            <p class="text-sm"><?php echo htmlspecialchars($_GET['message'] ?? 'Ocorreu um erro desconhecido.'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form action="processar_envio.php" method="POST" enctype="multipart/form-data" id="formEnvio">
                <input type="hidden" name="redirect_to" value="governanca.php">
                <input type="hidden" name="modo_envio" value="governanca">
                
                <!-- Upload Section -->
                <div class="mb-10">
                    <label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">
                        1. Documento Original (PDF)
                    </label>
                    
                    <div id="drop-zone" class="relative border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-blue-400 hover:bg-blue-50 transition-all cursor-pointer group">
                        <input type="file" id="arquivo" name="arquivo" accept="application/pdf" required
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        
                        <div class="space-y-3 pointer-events-none">
                            <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center mx-auto group-hover:bg-white group-hover:scale-110 transition-transform">
                                <i class="fas fa-cloud-upload-alt text-3xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-700 font-medium group-hover:text-blue-600 transition-colors">Clique ou arraste o arquivo PDF aqui</p>
                                <p class="text-gray-400 text-xs mt-1">Tamanho máximo: 10MB</p>
                            </div>
                            <p id="file-name" class="text-sm font-semibold text-blue-600 hidden mt-2 py-1 px-3 bg-blue-100 rounded-full inline-block"></p>
                        </div>
                    </div>
                </div>

                <hr class="border-gray-100 mb-10">

                <div class="mb-10">
                    <label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">
                        2. Tipo de Documento
                    </label>

                    <div class="grid md:grid-cols-2 gap-5">
                        <div>
                            <label for="tipo_documento" class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">Selecione</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-tag text-gray-400 text-xs"></i>
                                </div>
                                <select id="tipo_documento" name="tipo_documento" required
                                    class="w-full pl-9 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all appearance-none">
                                    <option value="">Selecione</option>
                                    <?php foreach($tiposDocumentos as $t): ?>
                                        <option value="<?php echo intval($t["id"]); ?>"><?php echo htmlspecialchars($t["nome"], ENT_QUOTES, "UTF-8"); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase" for="validar_icp_governanca">Validade ICP-Brasil (opcional)</label>
                            <label class="flex items-start gap-3 px-4 py-3 rounded-xl border border-gray-200 bg-white cursor-pointer">
                                <input type="checkbox" id="validar_icp_governanca" name="validar_icp" value="sim" class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <div>
                                    <div class="text-sm font-semibold text-gray-800">Validar com ICP-Brasil</div>
                                    <div class="text-xs text-gray-500">Ative para aplicar validação/carimbo do tempo ICP-Brasil, elevando o nível de confiança jurídica do documento final.</div>
                                </div>
                            </label>
                            <div id="icp_info_governanca" class="hidden mt-3 bg-emerald-50 border border-emerald-100 text-emerald-900 rounded-lg p-4">
                                <div class="text-sm font-semibold flex items-center gap-2">
                                    <i class="fas fa-shield-alt"></i>
                                    Validação com ICP-Brasil
                                </div>
                                <div class="text-xs mt-1 leading-relaxed">
                                    Seu documento será assinado com ICP-Brasil após a assinatura do último signatário.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-gray-100 mb-10">

                <!-- Signatários Section -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide">
                            3. Signatários (Ordem Sequencial)
                        </label>
                        <button type="button" id="btnAddSignatario" 
                            class="text-sm bg-blue-50 text-blue-600 hover:bg-blue-100 px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2 border border-blue-200">
                            <i class="fas fa-plus"></i> Adicionar
                        </button>
                    </div>
                    
                    <p class="text-sm text-gray-500 mb-6 bg-yellow-50 p-3 rounded-lg border border-yellow-100 flex items-start gap-2">
                        <i class="fas fa-info-circle text-yellow-500 mt-0.5"></i>
                        O documento será enviado para o primeiro da lista. Assim que assinar, o próximo será notificado automaticamente.
                    </p>

                    <div id="signatarios-container" class="space-y-4">
                        <!-- Cards inseridos via JS -->
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" 
                    class="w-full bg-gradient-to-r from-green-600 to-green-500 hover:from-green-700 hover:to-green-600 text-white font-bold py-4 px-6 rounded-xl shadow-lg shadow-green-200 transform hover:-translate-y-0.5 transition-all flex items-center justify-center gap-3 text-lg">
                    <span>Iniciar Processo de Assinatura</span>
                    <i class="fas fa-paper-plane"></i>
                </button>

            </form>
        </div>
    </div>

    <!-- Template do Card de Signatário (Oculto) -->
    <template id="signatario-template">
        <div class="signatario-card bg-white border border-gray-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow relative group">
            <div class="absolute -left-3 top-5 bg-gray-800 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm shadow-md z-10 index-badge">
                1
            </div>
            
            <div class="flex justify-between items-start mb-4 ml-4">
                <h4 class="font-semibold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-user-pen text-gray-400"></i> Dados do Signatário
                </h4>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn-move-up text-gray-400 hover:text-gray-700 transition-colors p-1" title="Mover para cima">
                        <i class="fas fa-arrow-up"></i>
                    </button>
                    <button type="button" class="btn-move-down text-gray-400 hover:text-gray-700 transition-colors p-1" title="Mover para baixo">
                        <i class="fas fa-arrow-down"></i>
                    </button>
                    <button type="button" class="btn-remove text-gray-400 hover:text-red-500 transition-colors p-1" title="Remover">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-5 ml-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">Funcionário</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400 text-xs"></i>
                        </div>
                        <select class="select-funcionario w-full pl-9 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all"></select>
                        <input type="hidden" class="input-entidade" value="">
                        <input type="hidden" class="input-setor-id" value="">
                        <input type="hidden" class="input-nome" value="">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">E-mail Corporativo</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400 text-xs"></i>
                        </div>
                        <input type="email" name="email" required placeholder="joao@empresa.com"
                            class="input-email w-full pl-9 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all" readonly>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">Setor</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-sitemap text-gray-400 text-xs"></i>
                        </div>
                        <input type="text" placeholder="—" class="input-setor w-full pl-9 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all" readonly>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">Responsáveis (Assina Governança)</label>
                    <div class="box-responsaveis min-h-[42px] w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700 flex flex-wrap gap-2 items-center"></div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">Função / Papel</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-briefcase text-gray-400 text-xs"></i>
                        </div>
                        <select name="funcao" required
                            class="select-funcao w-full pl-9 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all appearance-none">
                            <option value="Funcionário">Funcionário</option>
                            <option value="Responsável do Setor">Responsável do Setor</option>
                            <option value="Gerente">Gerente</option>
                            <option value="Diretor">Diretor</option>
                            <option value="Testemunha">Testemunha</option>
                            <option value="Representante Legal">Representante Legal</option>
                            <option value="Contratante">Contratante</option>
                            <option value="Outro">Outro</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">Cadastrar no cadastro do funcionário</label>
                    <label class="flex items-start gap-3 px-4 py-3 rounded-xl border border-gray-200 bg-white cursor-pointer">
                        <input type="checkbox" class="input-salvar-doc mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" value="sim">
                        <div>
                            <div class="text-sm font-semibold text-gray-800">Salvar e cadastrar</div>
                            <div class="text-xs text-gray-500">Após assinar, salva em <span class="font-semibold">arquivos/Funcionarios</span> e registra em <span class="font-semibold">Documentos</span> do funcionário.</div>
                        </div>
                    </label>
                </div>
            </div>
            
            <input type="hidden" name="ordem" class="input-ordem">
        </div>
    </template>

    <script>
        const container = document.getElementById('signatarios-container');
        const btnAdd = document.getElementById('btnAddSignatario');
        const template = document.getElementById('signatario-template');
        const fileInput = document.getElementById('arquivo');
        const dropZone = document.getElementById('drop-zone');
        const fileNameDisplay = document.getElementById('file-name');
        const formEnvio = document.getElementById('formEnvio');
        const icpInput = document.getElementById('validar_icp_governanca');
        const icpInfo = document.getElementById('icp_info_governanca');

        const baseUrl = <?=json_encode($_ENV["URL_BASE"].$_ENV["APP_PATH"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
        const contexPath = <?=json_encode($_ENV["APP_PATH"].$_ENV["CONTEX_PATH"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
        const condAtivo = encodeURIComponent("AND enti_tx_status = 'ativo'");
        let cardUidSeq = 0;

        function syncIcpInfo(){
            if(!icpInput || !icpInfo) return;
            icpInfo.classList.toggle('hidden', !icpInput.checked);
        }
        if(icpInput){
            icpInput.addEventListener('change', syncIcpInfo);
            syncIcpInfo();
        }

        // Drag and Drop Effects
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('drag-active');
        }

        function unhighlight(e) {
            dropZone.classList.remove('drag-active');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            updateFileName();
        }

        fileInput.addEventListener('change', updateFileName);

        function updateFileName() {
            if (fileInput.files.length > 0) {
                const name = fileInput.files[0].name;
                fileNameDisplay.textContent = name;
                fileNameDisplay.classList.remove('hidden');
            }
        }

        // Logic for Signatories
        function addSignatario(nome = '', email = '', funcao = 'Funcionário') {
            const clone = template.content.cloneNode(true);
            const card = clone.querySelector('.signatario-card');
            card.dataset.uid = String(++cardUidSeq);
            
            // Set values if provided (for edit/preload)
            if(nome) card.querySelector('.input-nome').value = nome;
            if(email) card.querySelector('.input-email').value = email;
            if(funcao) card.querySelector('.select-funcao').value = funcao;

            // Remove button logic
            const btnRemove = card.querySelector('.btn-remove');
            btnRemove.addEventListener('click', () => {
                if (container.children.length > 1) {
                    card.remove();
                    reordenar();
                } else {
                    alert('É necessário pelo menos um signatário.');
                }
            });

            container.appendChild(card);
            initCard(card);
            reordenar();
        }

        function escapeHtml(str){
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderResponsaveis(card, responsaveis) {
            const box = card.querySelector('.box-responsaveis');
            if (!box) return;
            if (!responsaveis || !Array.isArray(responsaveis) || responsaveis.length === 0) {
                box.innerHTML = '<span class="text-gray-400 text-xs">Nenhum responsável configurado para assinar governança.</span>';
                return;
            }
            box.innerHTML = responsaveis.map(r => {
                const nome = (r && r.nome) ? String(r.nome) : '';
                const email = (r && r.email) ? String(r.email) : '';
                const origem = (r && r.origem) ? String(r.origem) : '';
                const hasSetor = origem.toLowerCase().indexOf('setor') !== -1;
                const hasCargo = origem.toLowerCase().indexOf('cargo') !== -1;
                const tipo = (hasSetor && hasCargo) ? 'SETOR/CARGO' : (hasSetor ? 'SETOR' : (hasCargo ? 'CARGO' : ''));
                let label = email ? (nome + ' | ' + email) : nome;
                if(tipo){ label += ' | ' + tipo; }
                const id = (r && r.id != null) ? String(r.id) : '';
                return '<span class="inline-flex items-center px-2 py-1 rounded-md bg-blue-50 text-blue-700 text-xs border border-blue-100" data-id="'+id+'"><span>'+escapeHtml(label)+'</span><button type="button" class="resp-remove ml-2 text-red-600 hover:text-red-700 focus:outline-none" title="Remover">×</button></span>';
            }).join('');
        }

        function parseSources(str){
            const raw = String(str || '').split(',').map(s => s.trim()).filter(Boolean);
            return Array.from(new Set(raw));
        }

        function setSources(card, sources){
            card.dataset.autoSources = Array.from(new Set((sources || []).map(s => String(s)))).join(',');
        }

        function removeSourceFromAutoCards(sourceUid){
            const uid = String(sourceUid || '');
            if(uid === '') return;
            const autos = container.querySelectorAll('.signatario-card[data-auto-responsavel="1"]');
            autos.forEach(c => {
                const sources = parseSources(c.dataset.autoSources || '');
                const next = sources.filter(s => s !== uid);
                if(next.length === 0){
                    c.remove();
                } else {
                    setSources(c, next);
                }
            });
        }

        function findCardByEntidadeId(entidadeId){
            const id = String(entidadeId || '');
            if(id === '') return null;
            const cards = container.querySelectorAll('.signatario-card');
            for(const c of cards){
                const inp = c.querySelector('.input-entidade');
                if(inp && String(inp.value || '') === id){
                    return c;
                }
            }
            return null;
        }

        function preselectFuncionarioNoCard(card, entidadeId, label){
            const sel = card.querySelector('.select-funcionario');
            if(!sel) return;
            const id = String(entidadeId || '');
            if(id === '') return;
            const text = String(label || ('ID ' + id));

            const opt = new Option(text, id, true, true);
            sel.appendChild(opt);

            if(window.jQuery && jQuery.fn && typeof jQuery.fn.select2 === 'function'){
                const $sel = jQuery(sel);
                $sel.val(id).trigger('change.select2');
            } else {
                sel.value = id;
            }
        }

        function syncAutoResponsaveis(card, responsaveis, setorId, setorNome){
            const sourceUid = String(card.dataset.uid || '');
            if(sourceUid === '') return;

            removeSourceFromAutoCards(sourceUid);

            const selectedEnti = (card.querySelector('.input-entidade')?.value || '').toString();
            const list = Array.isArray(responsaveis) ? responsaveis : [];
            const excluded = String(card.dataset.excludedIds || '').split(',').map(s => s.trim()).filter(Boolean);
            let insertAfter = card;

            for(const r of list){
                const respId = r && r.id != null ? String(r.id) : '';
                if(respId === '' || respId === selectedEnti) continue;
                if(excluded.includes(respId)) continue;

                const existing = findCardByEntidadeId(respId);
                if(existing){
                    if(existing.dataset.autoResponsavel === '1'){
                        const sources = parseSources(existing.dataset.autoSources || '');
                        if(!sources.includes(sourceUid)){
                            sources.push(sourceUid);
                            setSources(existing, sources);
                        }
                    }
                    continue;
                }

                const clone = template.content.cloneNode(true);
                const respCard = clone.querySelector('.signatario-card');
                respCard.dataset.uid = String(++cardUidSeq);
                respCard.dataset.autoResponsavel = '1';
                respCard.dataset.autoSources = sourceUid;

                const btnRemove = respCard.querySelector('.btn-remove');
                if(btnRemove) btnRemove.style.display = 'none';

                const nome = (r && r.nome) ? String(r.nome) : '';
                const email = (r && r.email) ? String(r.email) : '';
                respCard.querySelector('.input-nome').value = nome;
                respCard.querySelector('.input-email').value = email;
                respCard.querySelector('.input-email').readOnly = true;
                respCard.querySelector('.input-entidade').value = respId;
                respCard.querySelector('.input-setor-id').value = setorId ? String(setorId) : '';
                const inpSetor = respCard.querySelector('.input-setor');
                if(inpSetor) inpSetor.value = setorNome ? String(setorNome) : '';
                const funcSel = respCard.querySelector('.select-funcao');
                if(funcSel) funcSel.value = 'Responsável do Setor';

                const label = email ? (nome + ' | ' + email) : nome;
                preselectFuncionarioNoCard(respCard, respId, label);

                insertAfter.insertAdjacentElement('afterend', respCard);
                insertAfter = respCard;
                initCard(respCard, { disableSelect: true, skipFetch: true });
            }
        }
        function getExcluded(card){
            return String(card.dataset.excludedIds || '').split(',').map(s => s.trim()).filter(Boolean);
        }
        function setExcluded(card, ids){
            const u = Array.from(new Set((ids || []).map(s => String(s).trim()).filter(Boolean)));
            card.dataset.excludedIds = u.join(',');
        }
        function removeAutoCardByEntidade(respId){
            const c = findCardByEntidadeId(respId);
            if(c && c.dataset.autoResponsavel === '1'){ c.remove(); reordenar(); }
        }
        function excludeResponsavel(card, respId){
            const ids = getExcluded(card);
            if(!ids.includes(respId)){ ids.push(respId); }
            setExcluded(card, ids);
            removeAutoCardByEntidade(respId);
        }

        async function carregarInfoFuncionario(card, entidadeId) {
            const inputNome = card.querySelector('.input-nome');
            const inputEmail = card.querySelector('.input-email');
            const inputSetor = card.querySelector('.input-setor');
            const inputEnti = card.querySelector('.input-entidade');
            const inputSetorId = card.querySelector('.input-setor-id');
            const sourceUid = String(card.dataset.uid || '');

            if (inputEnti) inputEnti.value = entidadeId ? String(entidadeId) : '';
            if (!entidadeId) {
                if (inputNome) inputNome.value = '';
                if (inputEmail) inputEmail.value = '';
                if (inputSetor) inputSetor.value = '';
                if (inputSetorId) inputSetorId.value = '';
                renderResponsaveis(card, []);
                removeSourceFromAutoCards(sourceUid);
                reordenar();
                return;
            }

            try {
                const res = await fetch('governanca.php?ajax=funcionario_info&id=' + encodeURIComponent(String(entidadeId)), {
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (!data || !data.ok) {
                    renderResponsaveis(card, []);
                    removeSourceFromAutoCards(sourceUid);
                    reordenar();
                    return;
                }
                if (inputNome) inputNome.value = (data.funcionario && data.funcionario.nome) ? String(data.funcionario.nome) : '';
                if (inputEmail) inputEmail.value = (data.funcionario && data.funcionario.email) ? String(data.funcionario.email) : '';
                if (inputSetor) inputSetor.value = (data.setor && data.setor.nome) ? String(data.setor.nome) : '';
                if (inputSetorId) inputSetorId.value = (data.setor && data.setor.id) ? String(data.setor.id) : '';
                renderResponsaveis(card, data.responsaveis || []);
                syncAutoResponsaveis(card, data.responsaveis || [], (data.setor && data.setor.id) ? data.setor.id : '', (data.setor && data.setor.nome) ? data.setor.nome : '');
                reordenar();
            } catch (e) {
                renderResponsaveis(card, []);
                removeSourceFromAutoCards(sourceUid);
                reordenar();
            }
        }

        function initCard(card, opts){
            const options = opts || {};
            const box = card.querySelector('.box-responsaveis');
            if (box && box.innerHTML.trim() === '') {
                renderResponsaveis(card, []);
            }
            if(box){
                box.addEventListener('click', function(e){
                    const btn = e.target.closest('.resp-remove');
                    if(!btn) return;
                    const item = btn.parentElement;
                    const rid = item.getAttribute('data-id') || '';
                    if(rid){ excludeResponsavel(card, rid); item.remove(); }
                });
            }

            const sel = card.querySelector('.select-funcionario');
            if(!sel) return;

            const btnUp = card.querySelector('.btn-move-up');
            if(btnUp){
                btnUp.addEventListener('click', function(){
                    const prev = card.previousElementSibling;
                    if(prev){
                        container.insertBefore(card, prev);
                        reordenar();
                    }
                });
            }
            const btnDown = card.querySelector('.btn-move-down');
            if(btnDown){
                btnDown.addEventListener('click', function(){
                    const next = card.nextElementSibling;
                    if(next){
                        container.insertBefore(next, card);
                        reordenar();
                    }
                });
            }

            if(window.jQuery && jQuery.fn && typeof jQuery.fn.select2 === 'function'){
                const $sel = jQuery(sel);
                $sel.select2({
                    language: 'pt-BR',
                    placeholder: 'Selecione',
                    allowClear: true,
                    width: '100%',
                    ajax: {
                        url: baseUrl + '/contex20/select2.php?path=' + encodeURIComponent(contexPath) + '&tabela=entidade&ordem=&limite=15&condicoes=' + condAtivo,
                        dataType: 'json',
                        delay: 250,
                        processResults: function (data) { return { results: data }; },
                        cache: true
                    }
                });

                if(!options.skipFetch){
                    $sel.on('select2:select', function(e){
                        const d = e && e.params ? e.params.data : null;
                        const id = d && d.id ? d.id : '';
                        carregarInfoFuncionario(card, id);
                    });

                    $sel.on('select2:clear', function(){
                        carregarInfoFuncionario(card, '');
                    });

                    $sel.on('change', function(){
                        const id = $sel.val();
                        if(!id){
                            carregarInfoFuncionario(card, '');
                        }
                    });
                }

                if(options.disableSelect){
                    $sel.prop('disabled', true);
                }
            }
        }

        function reordenar() {
            const cards = container.querySelectorAll('.signatario-card');
            
            cards.forEach((card, index) => {
                const realIndex = index + 1;
                
                // Update badge
                card.querySelector('.index-badge').textContent = realIndex;

                const btnUp = card.querySelector('.btn-move-up');
                if(btnUp){
                    btnUp.disabled = index === 0;
                    btnUp.classList.toggle('opacity-30', index === 0);
                    btnUp.classList.toggle('cursor-not-allowed', index === 0);
                }
                const btnDown = card.querySelector('.btn-move-down');
                if(btnDown){
                    btnDown.disabled = index === cards.length - 1;
                    btnDown.classList.toggle('opacity-30', index === cards.length - 1);
                    btnDown.classList.toggle('cursor-not-allowed', index === cards.length - 1);
                }
                
                // Update Remove Button Visibility (hide for first if only one)
                const btnRemove = card.querySelector('.btn-remove');
                if(btnRemove){
                    if(card.dataset.autoResponsavel === '1'){
                        btnRemove.style.display = 'none';
                    } else {
                        btnRemove.style.display = cards.length === 1 ? 'none' : 'block';
                    }
                }

                // Update input names for PHP array
                card.querySelector('.input-nome').name = `signatarios[${index}][nome]`;
                card.querySelector('.input-email').name = `signatarios[${index}][email]`;
                card.querySelector('.select-funcao').name = `signatarios[${index}][funcao]`;
                const enti = card.querySelector('.input-entidade');
                if(enti) enti.name = `signatarios[${index}][enti_nb_id]`;
                const setorId = card.querySelector('.input-setor-id');
                if(setorId) setorId.name = `signatarios[${index}][setor_id]`;

                const salvar = card.querySelector('.input-salvar-doc');
                if(salvar){
                    salvar.name = `signatarios[${index}][salvar_documento_funcionario]`;
                    if(index === 0){
                        salvar.checked = true;
                        salvar.disabled = true;
                    } else {
                        salvar.disabled = false;
                    }
                }
                
                // Update hidden order input
                const ordemInput = card.querySelector('.input-ordem');
                ordemInput.name = `signatarios[${index}][ordem]`;
                ordemInput.value = realIndex;
            });
        }

        btnAdd.addEventListener('click', () => addSignatario());

        // Initialize with 2 empty slots or default
        addSignatario('', '', 'Funcionário');
        addSignatario('', '', 'Gerente');

        if(formEnvio){
            formEnvio.addEventListener('submit', function(e){
                const cards = container.querySelectorAll('.signatario-card');
                for(const card of cards){
                    const enti = card.querySelector('.input-entidade');
                    const nome = card.querySelector('.input-nome');
                    const email = card.querySelector('.input-email');
                    if(!enti || String(enti.value || '').trim() === ''){
                        e.preventDefault();
                        alert('Selecione um funcionário em todos os signatários.');
                        return;
                    }
                    if(!nome || String(nome.value || '').trim() === '' || !email || String(email.value || '').trim() === ''){
                        e.preventDefault();
                        alert('Os dados do funcionário (nome/e-mail) precisam estar preenchidos em todos os signatários.');
                        return;
                    }
                }
            });
        }

    </script>
</div>
<?php
include_once "componentes/layout_footer.php";
?>
