<?php
include_once "../conecta.php";
include_once "componentes/layout_header.php";

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// Parâmetros de filtro
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_assinatura = isset($_GET['assinatura']) ? $_GET['assinatura'] : '';
$filtro_envio = isset($_GET['envio']) ? strtolower(trim(strval($_GET['envio']))) : '';
$filtro_envio = in_array($filtro_envio, ['avulso', 'governanca'], true) ? $filtro_envio : '';
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$busca = isset($_GET['busca']) ? $_GET['busca'] : '';
$filtro_tipo_documento = intval($_GET['tipo_documento'] ?? 0);
$filtro_funcionario = intval($_GET['funcionario'] ?? 0);
$view = strtolower(trim(strval($_GET["view"] ?? "")));

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

$hasCreatedAt = false;
$hasDataSolicitacao = false;
$chkCreatedAt = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'created_at'");
if ($chkCreatedAt && mysqli_num_rows($chkCreatedAt) > 0) {
    $hasCreatedAt = true;
}
$chkDataSolic = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'data_solicitacao'");
if ($chkDataSolic && mysqli_num_rows($chkDataSolic) > 0) {
    $hasDataSolicitacao = true;
}

$dataEnvioExprParts = [];
if ($hasCreatedAt) {
    $dataEnvioExprParts[] = "NULLIF(s.created_at,'0000-00-00 00:00:00')";
}
if ($hasDataSolicitacao) {
    $dataEnvioExprParts[] = "NULLIF(s.data_solicitacao,'0000-00-00 00:00:00')";
}
$dataEnvioExpr = !empty($dataEnvioExprParts) ? ("COALESCE(" . implode(", ", $dataEnvioExprParts) . ")") : "NULL";

function tpFormatDtSp(?string $raw): string {
    $raw = trim(strval($raw ?? ""));
    if ($raw === "" || $raw === "0000-00-00 00:00:00") {
        return "-";
    }
    try {
        $dt = new DateTimeImmutable($raw, new DateTimeZone("UTC"));
        $dt = $dt->setTimezone(new DateTimeZone("America/Sao_Paulo"));
        return $dt->format("d/m/Y H:i");
    } catch (Throwable $e) {
        $ts = strtotime($raw);
        if (!$ts) {
            return "-";
        }
        return date("d/m/Y H:i", $ts);
    }
}

$checkModo = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'modo_envio'");
if ($checkModo && mysqli_num_rows($checkModo) == 0) {
    @mysqli_query($conn, "ALTER TABLE solicitacoes_assinatura ADD COLUMN modo_envio VARCHAR(30) NULL");
}

$checkGrupo = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'grupo_envio'");
if ($checkGrupo && mysqli_num_rows($checkGrupo) == 0) {
    @mysqli_query($conn, "ALTER TABLE solicitacoes_assinatura ADD COLUMN grupo_envio VARCHAR(64) NULL");
}

$hasExpiresAt = false;
$checkExp = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'expires_at'");
if ($checkExp && mysqli_num_rows($checkExp) > 0) {
    $hasExpiresAt = true;
} elseif ($checkExp && mysqli_num_rows($checkExp) == 0) {
    @mysqli_query($conn, "ALTER TABLE solicitacoes_assinatura ADD COLUMN expires_at DATETIME NULL");
    $hasExpiresAt = true;
}

if ($hasExpiresAt) {
    @mysqli_query($conn, "UPDATE solicitacoes_assinatura s SET s.expires_at = DATE_ADD($dataEnvioExpr, INTERVAL 24 HOUR) WHERE (s.expires_at IS NULL OR s.expires_at = '0000-00-00 00:00:00') AND $dataEnvioExpr IS NOT NULL");
}

if ($filtro_status) {
    if ($filtro_status == 'concluido') {
        $where_clauses[] = "(SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id) = (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado')";
    } elseif ($filtro_status == 'expirado') {
        if ($hasExpiresAt) {
            $where_clauses[] = "(s.expires_at IS NOT NULL AND s.expires_at <> '0000-00-00 00:00:00' AND UTC_TIMESTAMP() > s.expires_at) AND ((SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado') < (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id))";
        } else {
            $where_clauses[] = "0=1";
        }
    } elseif ($filtro_status == 'pendente') {
        $where_clauses[] = "(SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado') < (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id)";
    }
}

if ($filtro_envio) {
    if ($filtro_envio === 'governanca') {
        $where_clauses[] = "(s.modo_envio = 'governanca' OR ((s.modo_envio IS NULL OR s.modo_envio = '') AND EXISTS (SELECT 1 FROM assinantes ax WHERE ax.id_solicitacao = s.id AND LOWER(TRIM(ax.funcao)) LIKE '%setor%')))";
    } elseif ($filtro_envio === 'avulso') {
        $where_clauses[] = "(s.modo_envio IN ('avulso','funcionarios','separar_paginas') OR ((s.modo_envio IS NULL OR s.modo_envio = '') AND NOT EXISTS (SELECT 1 FROM assinantes ax WHERE ax.id_solicitacao = s.id AND LOWER(TRIM(ax.funcao)) LIKE '%setor%')))";
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
    $where_clauses[] = "DATE($dataEnvioExpr) >= '$filtro_data_inicio'";
}

if ($filtro_data_fim) {
    $where_clauses[] = "DATE($dataEnvioExpr) <= '$filtro_data_fim'";
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

@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS setor_responsavel (
    sres_nb_id INT AUTO_INCREMENT PRIMARY KEY,
    sres_nb_setor_id INT NOT NULL,
    sres_nb_entidade_id INT NOT NULL,
    sres_tx_assinar_governanca ENUM('sim','nao') NOT NULL DEFAULT 'nao',
    sres_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    sres_tx_dataCadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_setor_entidade (sres_nb_setor_id, sres_nb_entidade_id),
    INDEX ix_setor (sres_nb_setor_id),
    INDEX ix_entidade (sres_nb_entidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Query principal
$sql = "
    SELECT s.*, 
    $dataEnvioExpr as data_envio,
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

$solicitacoes = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $solicitacoes[] = $row;
    }
}

$assinantesPorSolicitacao = [];
$idsSolicitacoes = array_values(array_filter(array_map(function ($r) {
    return intval($r["id"] ?? 0);
}, $solicitacoes)));

if (!empty($idsSolicitacoes)) {
    $idsSql = implode(",", array_map("intval", $idsSolicitacoes));
    $sqlAss = "
        SELECT
            a.id as assinante_id,
            a.id_solicitacao,
            a.ordem,
            a.nome,
            a.email,
            a.funcao,
            a.status,
            a.data_assinatura,
            a.enti_nb_id,
            e.enti_setor_id,
            g.grup_tx_nome AS setor_nome,
            e.enti_subSetor_id,
            sb.sbgr_tx_nome AS subsetor_nome,
            GROUP_CONCAT(DISTINCT gresp.grup_tx_nome ORDER BY gresp.grup_tx_nome SEPARATOR ', ') AS setores_responsavel
        FROM assinantes a
        LEFT JOIN entidade e
            ON e.enti_nb_id = a.enti_nb_id
        LEFT JOIN grupos_documentos g
            ON g.grup_nb_id = e.enti_setor_id
        LEFT JOIN sbgrupos_documentos sb
            ON sb.sbgr_nb_id = e.enti_subSetor_id
        LEFT JOIN setor_responsavel sr
            ON sr.sres_nb_entidade_id = a.enti_nb_id
            AND sr.sres_tx_status = 'ativo'
        LEFT JOIN grupos_documentos gresp
            ON gresp.grup_nb_id = sr.sres_nb_setor_id
        WHERE a.id_solicitacao IN ($idsSql)
        GROUP BY a.id
        ORDER BY a.id_solicitacao ASC, a.ordem ASC, a.id ASC
    ";
    $resAss = mysqli_query($conn, $sqlAss);
    if ($resAss) {
        while ($a = mysqli_fetch_assoc($resAss)) {
            $sid = intval($a["id_solicitacao"] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            if (!isset($assinantesPorSolicitacao[$sid])) {
                $assinantesPorSolicitacao[$sid] = [];
            }
            $assinantesPorSolicitacao[$sid][] = $a;
        }
    }
}

$assinantesGrupoPorEnvio = [];
if (!empty($solicitacoes)) {
    $grupos = [];
    $getGroupKey = function (array $r) use ($assinantesPorSolicitacao): string {
        $gid = trim(strval($r["grupo_envio"] ?? ""));
        if ($gid !== "") {
            return $gid;
        }
        $modo = strtolower(trim(strval($r["modo_envio"] ?? "")));
        if ($modo !== "avulso") {
            return "";
        }
        if (intval($r["total_assinantes"] ?? 0) !== 1) {
            return "";
        }
        $sid = intval($r["id"] ?? 0);
        if ($sid <= 0 || empty($assinantesPorSolicitacao[$sid]) || !is_array($assinantesPorSolicitacao[$sid]) || count($assinantesPorSolicitacao[$sid]) !== 1) {
            return "";
        }
        $fx = strtolower(trim(strval($assinantesPorSolicitacao[$sid][0]["funcao"] ?? "")));
        if ($fx === "" || strpos($fx, "funcion") === false) {
            return "";
        }
        $raw = trim(strval($r["data_envio"] ?? ($r["created_at"] ?? ($r["data_solicitacao"] ?? ""))));
        $ts = $raw !== "" ? strtotime($raw) : false;
        if (!$ts) {
            return "";
        }
        $file = trim(strval($r["nome_arquivo_original"] ?? ""));
        if ($file === "") {
            return "";
        }
        $tipo = intval($r["tipo_documento_id"] ?? 0);
        $bucket = date("Y-m-d H:i", $ts);
        return "LEG-" . md5($modo . "|" . $file . "|" . $tipo . "|" . $bucket);
    };

    foreach ($solicitacoes as $r) {
        $gid = $getGroupKey($r);
        if ($gid === "") { continue; }
        $id = intval($r["id"] ?? 0);
        if ($id <= 0) {
            continue;
        }
        if (!isset($grupos[$gid])) {
            $grupos[$gid] = [
                "row" => $r,
                "ids" => [],
                "total_assinantes" => 0,
                "total_assinados" => 0,
                "data_envio_ts" => null,
                "data_envio_raw" => "",
                "expires_at_ts" => null,
                "expires_at_raw" => "",
            ];
        }
        $grupos[$gid]["ids"][] = $id;
        $grupos[$gid]["total_assinantes"] += intval($r["total_assinantes"] ?? 0);
        $grupos[$gid]["total_assinados"] += intval($r["total_assinados"] ?? 0);
        $raw = trim(strval($r["data_envio"] ?? ($r["created_at"] ?? ($r["data_solicitacao"] ?? ""))));
        $ts = $raw !== "" ? strtotime($raw) : false;
        if ($ts) {
            $cur = $grupos[$gid]["data_envio_ts"];
            if ($cur === null || $ts < $cur) {
                $grupos[$gid]["data_envio_ts"] = $ts;
                $grupos[$gid]["data_envio_raw"] = $raw;
            }
        }

        $expRaw = trim(strval($r["expires_at"] ?? ""));
        $expTs = $expRaw !== "" ? strtotime($expRaw) : false;
        if ($expTs) {
            $curExp = $grupos[$gid]["expires_at_ts"];
            if ($curExp === null || $expTs > $curExp) {
                $grupos[$gid]["expires_at_ts"] = $expTs;
                $grupos[$gid]["expires_at_raw"] = $expRaw;
            }
        }
    }

    foreach ($grupos as $gid => $info) {
        if (count($info["ids"]) < 2) {
            unset($grupos[$gid]);
        }
    }

    foreach ($grupos as $gid => $info) {
        $assinantesGrupoPorEnvio[$gid] = [];
        foreach ($info["ids"] as $sid) {
            if (!empty($assinantesPorSolicitacao[$sid]) && is_array($assinantesPorSolicitacao[$sid])) {
                foreach ($assinantesPorSolicitacao[$sid] as $a) {
                    $assinantesGrupoPorEnvio[$gid][] = $a;
                }
            }
        }
    }

    $novas = [];
    $seen = [];
    foreach ($solicitacoes as $r) {
        $gid = $getGroupKey($r);
        if ($gid === "" || !isset($grupos[$gid])) {
            $novas[] = $r;
            continue;
        }
        if (isset($seen[$gid])) {
            continue;
        }
        $seen[$gid] = true;
        $base = $grupos[$gid]["row"];
        $base["total_assinantes"] = $grupos[$gid]["total_assinantes"];
        $base["total_assinados"] = $grupos[$gid]["total_assinados"];
        if ($grupos[$gid]["data_envio_raw"] !== "") {
            $base["data_envio"] = $grupos[$gid]["data_envio_raw"];
        }
        if ($grupos[$gid]["expires_at_raw"] !== "") {
            $base["expires_at"] = $grupos[$gid]["expires_at_raw"];
        }
        $base["_grupo_envio"] = $gid;
        $base["_grupo_qtd"] = count($grupos[$gid]["ids"]);
        $novas[] = $base;
    }
    $solicitacoes = $novas;
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Cabeçalho da Página -->
    <div class="mb-8 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <?php if($view === "documentos"): ?>
                <h1 class="text-2xl font-bold text-gray-800">Documentos (Assinaturas)</h1>
                <p class="text-gray-500">Todos os documentos enviados para assinatura (assinados e pendentes)</p>
            <?php else: ?>
                <h1 class="text-2xl font-bold text-gray-800">Consulta de documentos com assinatura obrigatória.</h1>
                <p class="text-gray-500">Histórico e rastreamento de assinaturas</p>
            <?php endif; ?>
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
        <form method="GET" id="formFiltrosConsulta" class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <?php if($view !== ""): ?>
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
            <?php endif; ?>
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
                    <option value="expirado" <?php echo $filtro_status == 'expirado' ? 'selected' : ''; ?>>Expirado</option>
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

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Envio</label>
                <select name="envio" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white">
                    <option value="">Todos</option>
                    <option value="avulso" <?php echo $filtro_envio == 'avulso' ? 'selected' : ''; ?>>Avulso</option>
                    <option value="governanca" <?php echo $filtro_envio == 'governanca' ? 'selected' : ''; ?>>Governança</option>
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
                    <?php if (!empty($solicitacoes)): ?>
                        <?php foreach ($solicitacoes as $row):
                            $completo = ($row['total_assinantes'] > 0 && $row['total_assinantes'] == $row['total_assinados']);
                            $progresso = $row['total_assinantes'] > 0 ? round(($row['total_assinados'] / $row['total_assinantes']) * 100) : 0;
                            $tipoDocNome = trim(strval($row["tipo_documento_nome"] ?? ""));
                            $validarIcp = strtolower(trim(strval($row["validar_icp"] ?? "nao")));
                            $statusFinal = strtolower(trim(strval($row["status_final"] ?? "")));
                            $icpFinalizado = ($statusFinal === "finalizado");
                            $modoEnvio = strtolower(trim(strval($row["modo_envio"] ?? "")));
                            $isGovernanca = ($modoEnvio === "governanca");
                            $expiresRaw = trim(strval($row["expires_at"] ?? ""));
                            $isExpired = false;
                            if (!$completo && $expiresRaw !== "" && $expiresRaw !== "0000-00-00 00:00:00") {
                                try {
                                    $exp = new DateTimeImmutable($expiresRaw, new DateTimeZone("UTC"));
                                    $now = new DateTimeImmutable("now", new DateTimeZone("UTC"));
                                    $isExpired = ($now > $exp);
                                } catch (Throwable $e) {
                                    $expTs = strtotime($expiresRaw . " UTC");
                                    if ($expTs) {
                                        $isExpired = (time() > $expTs);
                                    }
                                }
                            }

                            $solId = intval($row["id"] ?? 0);
                            $isGrupoEnvio = isset($row["_grupo_envio"]) && trim(strval($row["_grupo_envio"] ?? "")) !== "";
                            $grupoEnvioId = $isGrupoEnvio ? trim(strval($row["_grupo_envio"])) : "";
                            $grupoEnvioQtd = $isGrupoEnvio ? intval($row["_grupo_qtd"] ?? 0) : 0;

                            $assinantes = [];
                            if ($isGrupoEnvio) {
                                $assinantes = isset($assinantesGrupoPorEnvio[$grupoEnvioId]) ? $assinantesGrupoPorEnvio[$grupoEnvioId] : [];
                            } else {
                                $assinantes = $solId > 0 && isset($assinantesPorSolicitacao[$solId]) ? $assinantesPorSolicitacao[$solId] : [];
                            }
                            $totalSig = is_array($assinantes) ? count($assinantes) : 0;
                            $modalId = $isGrupoEnvio ? ("modal-signatarios-grupo-" . md5($grupoEnvioId)) : ("modal-signatarios-" . $solId);

                            $hasSetorRole = false;
                            if (!$isGovernanca && $modoEnvio === "" && $totalSig > 0) {
                                foreach ($assinantes as $aTmp) {
                                    $fx = strtolower(trim(strval($aTmp["funcao"] ?? "")));
                                    if ($fx !== "" && strpos($fx, "setor") !== false) {
                                        $hasSetorRole = true;
                                        break;
                                    }
                                    $respSetores = trim(strval($aTmp["setores_responsavel"] ?? ""));
                                    if ($respSetores !== "") {
                                        $hasSetorRole = true;
                                        break;
                                    }
                                }
                                if ($hasSetorRole) {
                                    $isGovernanca = true;
                                }
                            }

                            $envioLabel = "Avulso";
                            $envioBadgeClass = "bg-gray-50 text-gray-700 border border-gray-200";
                            $envioIcon = "fa-paper-plane";
                            if ($isGovernanca) {
                                $envioLabel = "Governança";
                                $envioBadgeClass = "bg-blue-50 text-blue-800 border border-blue-200";
                                $envioIcon = "fa-sitemap";
                            } elseif ($isGrupoEnvio && $modoEnvio === "avulso") {
                                $envioLabel = "Avulso (Todos)";
                            } elseif ($modoEnvio === "funcionarios") {
                                $envioLabel = "Avulso (Funcionários)";
                            } elseif ($modoEnvio === "separar_paginas") {
                                $envioLabel = "Avulso (Separar páginas)";
                            } elseif ($modoEnvio === "" && $hasSetorRole) {
                                $envioLabel = "Governança";
                                $envioBadgeClass = "bg-blue-50 text-blue-800 border border-blue-200";
                                $envioIcon = "fa-sitemap";
                            }
                            $envioTitle = $modoEnvio !== "" ? ("Modo: " . $modoEnvio) : "";
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
                                        <p class="text-xs text-gray-500">
                                            <?php if ($isGrupoEnvio): ?>
                                                <?php
                                                    $short = $grupoEnvioId !== "" ? substr($grupoEnvioId, -8) : "";
                                                    echo "Lote: " . htmlspecialchars($short !== "" ? $short : $grupoEnvioId);
                                                    echo $grupoEnvioQtd > 0 ? " • Enviados: " . intval($grupoEnvioQtd) : "";
                                                ?>
                                            <?php else: ?>
                                                ID: #<?php echo $row['id']; ?>
                                            <?php endif; ?>
                                        </p>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold <?php echo $envioBadgeClass; ?>" <?php echo $envioTitle !== "" ? 'title="' . htmlspecialchars($envioTitle) . '"' : ""; ?>>
                                                <i class="fas <?php echo $envioIcon; ?> mr-1"></i> Envio: <?php echo htmlspecialchars($envioLabel); ?>
                                            </span>
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
                                <?php
                                    $raw = trim(strval($row["data_envio"] ?? ($row["created_at"] ?? ($row["data_solicitacao"] ?? ""))));
                                    echo tpFormatDtSp($raw);
                                ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($isExpired): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <span class="w-2 h-2 mr-1.5 bg-red-500 rounded-full"></span>
                                        Expirado
                                    </span>
                                <?php elseif ($icpFinalizado): ?>
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
                                <div class="max-w-xs">
                                    <?php if ($totalSig <= 0): ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php else:
                                        $first = $assinantes[0];
                                        $st = strtolower(trim(strval($first["status"] ?? "")));
                                        $nomeSig = htmlspecialchars(strval($first["nome"] ?? ""), ENT_QUOTES, "UTF-8");
                                        if ($st === "assinado"): ?>
                                            <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded-full mr-2 mb-1 border border-green-200" title="Assinado">
                                                <i class="fas fa-check mr-1"></i><?php echo $nomeSig; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-block bg-yellow-50 text-yellow-800 text-xs px-2 py-0.5 rounded-full mr-2 mb-1 border border-yellow-200" title="Pendente">
                                                <i class="far fa-clock mr-1"></i><?php echo $nomeSig; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($totalSig > 1): ?>
                                            <button type="button"
                                                class="text-xs text-blue-600 hover:text-blue-800 underline"
                                                onclick="tpOpenModal('<?php echo $modalId; ?>')">
                                                Ver todos (<?php echo $totalSig; ?>)
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <?php if ($totalSig > 1): ?>
                                    <div id="<?php echo $modalId; ?>" class="tp-modal hidden fixed inset-0 z-50">
                                        <div class="absolute inset-0 bg-black/40" onclick="tpCloseModal('<?php echo $modalId; ?>')"></div>
                                        <div class="relative mx-auto my-10 w-[95%] max-w-3xl bg-white rounded-xl shadow-xl border border-gray-200">
                                            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                                                <div>
                                                    <div class="text-sm font-semibold text-gray-900">Signatários</div>
                                                    <div class="text-xs text-gray-500">Documento ID: #<?php echo intval($solId); ?></div>
                                                </div>
                                                <button type="button" class="text-gray-500 hover:text-gray-800" onclick="tpCloseModal('<?php echo $modalId; ?>')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <div class="p-5 max-h-[70vh] overflow-auto">
                                                <div class="space-y-3">
                                                    <?php foreach ($assinantes as $a):
                                                        $nomeA = htmlspecialchars(strval($a["nome"] ?? ""), ENT_QUOTES, "UTF-8");
                                                        $emailA = htmlspecialchars(strval($a["email"] ?? ""), ENT_QUOTES, "UTF-8");
                                                        $funcaoA = htmlspecialchars(strval($a["funcao"] ?? ""), ENT_QUOTES, "UTF-8");
                                                        $setorA = trim(strval($a["setor_nome"] ?? ""));
                                                        $subsetorA = trim(strval($a["subsetor_nome"] ?? ""));
                                                        $setorTxt = $setorA !== "" ? $setorA : "—";
                                                        if ($subsetorA !== "") {
                                                            $setorTxt .= " / " . $subsetorA;
                                                        }
                                                        $setorTxtSafe = htmlspecialchars($setorTxt, ENT_QUOTES, "UTF-8");
                                                        $respSetores = trim(strval($a["setores_responsavel"] ?? ""));
                                                        $respSafe = htmlspecialchars($respSetores, ENT_QUOTES, "UTF-8");
                                                        $stA = strtolower(trim(strval($a["status"] ?? "")));
                                                        $dataA = trim(strval($a["data_assinatura"] ?? ""));
                                                        $dataFmt = $dataA !== "" ? tpFormatDtSp($dataA) : "Pendente";
                                                    ?>
                                                        <div class="border border-gray-200 rounded-lg p-4">
                                                            <div class="flex items-start justify-between gap-3">
                                                                <div class="min-w-0">
                                                                    <div class="text-sm font-semibold text-gray-900 truncate"><?php echo $nomeA; ?></div>
                                                                    <div class="text-xs text-gray-500 truncate"><?php echo $emailA; ?></div>
                                                                    <div class="mt-2 text-xs text-gray-600">
                                                                        <span class="font-semibold text-gray-700">Função:</span> <?php echo $funcaoA !== "" ? $funcaoA : "—"; ?>
                                                                        <span class="mx-2 text-gray-300">|</span>
                                                                        <span class="font-semibold text-gray-700">Setor:</span> <?php echo $setorTxtSafe; ?>
                                                                    </div>
                                                                    <?php if ($respSetores !== ""): ?>
                                                                        <div class="mt-2">
                                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                                                                                <i class="fas fa-user-shield mr-1"></i> Responsável: <?php echo $respSafe; ?>
                                                                            </span>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="text-right flex-shrink-0">
                                                                    <?php if ($stA === "assinado"): ?>
                                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                            <span class="w-2 h-2 mr-1.5 bg-green-500 rounded-full"></span>
                                                                            Assinado
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                            <span class="w-2 h-2 mr-1.5 bg-yellow-500 rounded-full"></span>
                                                                            Pendente
                                                                        </span>
                                                                    <?php endif; ?>
                                                                    <div class="mt-2 text-xs text-gray-500"><?php echo htmlspecialchars($dataFmt, ENT_QUOTES, "UTF-8"); ?></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="px-5 py-4 border-t border-gray-100 flex justify-end">
                                                <button type="button" class="px-4 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50" onclick="tpCloseModal('<?php echo $modalId; ?>')">
                                                    Fechar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
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
                        <?php if ($tipoDocNome !== ""): ?>
                        <tr class="bg-gray-50/60">
                            <td colspan="6" class="px-6 pt-0 pb-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Tipo de Documento</span>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-amber-50 text-amber-900 border border-amber-200 font-semibold text-sm">
                                        <i class="fas fa-tag mr-1 text-amber-700"></i>
                                        <?php echo htmlspecialchars($tipoDocNome, ENT_QUOTES, "UTF-8"); ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
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
                Mostrando <?php echo count($solicitacoes); ?> registros
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
    function tpOpenModal(id){
        const el = document.getElementById(id);
        if(!el) return;
        el.classList.remove("hidden");
        document.body.classList.add("overflow-hidden");
    }
    function tpCloseModal(id){
        const el = document.getElementById(id);
        if(!el) return;
        el.classList.add("hidden");
        const anyOpen = document.querySelector(".tp-modal:not(.hidden)");
        if(!anyOpen){
            document.body.classList.remove("overflow-hidden");
        }
    }
    document.addEventListener("keydown", function(e){
        if(e.key === "Escape"){
            document.querySelectorAll(".tp-modal").forEach(function(m){
                m.classList.add("hidden");
            });
            document.body.classList.remove("overflow-hidden");
        }
    });

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

    (function(){
        const form = document.getElementById("formFiltrosConsulta");
        if(!form) return;

        const autoFields = form.querySelectorAll(
            'select[name="status"],' +
            'select[name="assinatura"],' +
            'select[name="envio"],' +
            'select[name="tipo_documento"],' +
            'select[name="funcionario"],' +
            'input[name="data_inicio"],' +
            'input[name="data_fim"]'
        );

        let submitTimer = 0;
        function scheduleSubmit(){
            if(submitTimer){
                clearTimeout(submitTimer);
            }
            submitTimer = window.setTimeout(function(){
                if(typeof form.requestSubmit === "function"){
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }, 150);
        }

        autoFields.forEach(function(el){
            el.addEventListener("change", scheduleSubmit);
        });

        if(window.jQuery){
            const $tipo = jQuery("#filtro_tipo_documento");
            if($tipo.length){
                $tipo.on("select2:select select2:clear", scheduleSubmit);
            }
            const $func = jQuery("#filtro_funcionario");
            if($func.length){
                $func.on("select2:select select2:clear", scheduleSubmit);
            }
        }
    })();
</script>

<?php include_once "componentes/layout_footer.php"; ?>
