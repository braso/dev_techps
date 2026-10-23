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

/* Token do formulário de renovação. O renovar_link.php o exige e
   recusa a requisição sem ele; é o que impede um site de fora de
   renovar prazos em nome de quem está logado aqui. */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION["renovar_token"])) {
    $_SESSION["renovar_token"] = bin2hex(random_bytes(32));
}
$renovarToken = $_SESSION["renovar_token"];

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

$checkPrazo = mysqli_query($conn, "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'prazo_expiracao_dias'");
if ($checkPrazo && mysqli_num_rows($checkPrazo) == 0) {
    @mysqli_query($conn, "ALTER TABLE solicitacoes_assinatura ADD COLUMN prazo_expiracao_dias INT NOT NULL DEFAULT 1");
}

if ($hasExpiresAt) {
    @mysqli_query($conn, "UPDATE solicitacoes_assinatura s SET s.expires_at = DATE_ADD($dataEnvioExpr, INTERVAL 24 HOUR) WHERE (s.expires_at IS NULL OR s.expires_at = '0000-00-00 00:00:00') AND $dataEnvioExpr IS NOT NULL AND (s.prazo_expiracao_dias IS NULL OR s.prazo_expiracao_dias <> 0)");
}

if ($filtro_status) {
    if ($filtro_status == 'concluido') {
        $where_clauses[] = "(SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id) = (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado')";
    } elseif ($filtro_status == 'expirado') {
        if ($hasExpiresAt) {
            $where_clauses[] = "(LOWER(TRIM(s.status)) = 'expirado' OR ((s.expires_at IS NOT NULL AND s.expires_at <> '0000-00-00 00:00:00' AND UTC_TIMESTAMP() > s.expires_at) AND ((SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado') < (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id))))";
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
        /* Os ids de todas as solicitações do grupo: renovar o grupo
           precisa renovar o lote inteiro, não só a linha que o representa. */
        $base["_grupo_ids"] = $grupos[$gid]["ids"];
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
                        <th class="pl-6 pr-2 py-4 w-10">
                            <input type="checkbox" id="rnvTodos"
                                   class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                   title="Selecionar todos que podem receber mais prazo">
                        </th>
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
                            /* Pode receber mais prazo quem ainda não fechou: expirado ou
                               pendente. Concluído não tem o que renovar. */
                            $podeRenovar = !$completo;
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

                            /* Quais solicitações esta linha renova. Linha de grupo de envio
                               representa várias: renovar só a que aparece deixaria o resto
                               do lote expirado, e o operador não teria como saber. */
                            $idsRenovaveis = [];
                            if ($isGrupoEnvio && !empty($row["_grupo_ids"]) && is_array($row["_grupo_ids"])) {
                                foreach ($row["_grupo_ids"] as $gidItem) {
                                    $gidInt = intval($gidItem);
                                    if ($gidInt > 0) { $idsRenovaveis[] = $gidInt; }
                                }
                            } elseif ($solId > 0) {
                                $idsRenovaveis[] = $solId;
                            }

                            $rotuloRenovar = trim(strval($row["nome_arquivo_original"] ?? ($row["nome"] ?? "Documento")));
                            if ($isGrupoEnvio) {
                                $rotuloRenovar .= " (" . count($idsRenovaveis) . " envios)";
                            }

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
                            <td class="pl-6 pr-2 py-4 align-top">
                                <?php if ($podeRenovar): ?>
                                    <input type="checkbox"
                                           class="rnv-item w-4 h-4 mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                           value="<?php echo htmlspecialchars(implode(",", $idsRenovaveis), ENT_QUOTES, "UTF-8"); ?>"
                                           data-rotulo="<?php echo htmlspecialchars($rotuloRenovar, ENT_QUOTES, "UTF-8"); ?>">
                                <?php endif; ?>
                            </td>
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
                                <?php if ($isExpired): ?>
                                    <div class="text-[11px] text-red-600 font-medium mt-1">
                                        <i class="far fa-clock"></i> prazo vencido
                                    </div>
                                <?php endif; ?>
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
                                    <?php if ($podeRenovar): ?>
                                        <button type="button"
                                                class="rnv-um text-gray-400 hover:text-amber-600 transition-colors"
                                                data-ids="<?php echo htmlspecialchars(implode(",", $idsRenovaveis), ENT_QUOTES, "UTF-8"); ?>"
                                                data-rotulo="<?php echo htmlspecialchars($rotuloRenovar, ENT_QUOTES, "UTF-8"); ?>"
                                                title="<?php echo $isExpired ? "Dar mais prazo e reabrir para assinatura" : "Estender o prazo deste envio"; ?>">
                                            <i class="fas fa-clock-rotate-left"></i>
                                        </button>
                                    <?php endif; ?>
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
                            <td colspan="7" class="px-6 pt-0 pb-4">
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
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
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
<!-- ─── Dar mais prazo ──────────────────────────────────────────────
     Aparece ao marcar pelo menos uma linha. Fica fixa no rodapé para o
     operador não ter de rolar de volta ao topo depois de escolher numa
     lista longa. -->
<div id="rnvBarra"
     class="hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-200 shadow-[0_-4px_20px_rgba(0,0,0,.08)]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2 text-sm text-gray-700">
            <i class="fas fa-clock-rotate-left text-amber-500"></i>
            <strong id="rnvConta">0</strong> selecionado(s)
        </div>
        <button type="button" id="rnvLimpar" class="text-xs text-gray-500 hover:text-gray-700 underline">limpar</button>
        <div class="ml-auto">
            <button type="button" id="rnvEnviar"
                    class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition-colors">
                <i class="fas fa-clock-rotate-left mr-1"></i> Dar mais prazo
            </button>
        </div>
    </div>
</div>

<!-- ─── Escolher o novo prazo ───────────────────────────────────────
     A escolha e a confirmação no mesmo lugar, com a data de vencimento
     calculada à medida que o operador mexe. É a informação que ele quer
     ver antes de decidir — "mais 7 dias" não diz nada; "vence em 30/10"
     diz. -->
<div id="rnvEscolha" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" onclick="rnvFecharEscolha()"></div>

    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-clock-rotate-left text-amber-500"></i> Dar mais prazo
            </h3>
            <button type="button" onclick="rnvFecharEscolha()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="px-6 py-5 overflow-y-auto">
            <p class="text-sm text-gray-600 mb-4">
                <strong id="rnvEscolhaQtd" class="text-gray-900">0</strong> solicitação(ões) vão receber um novo
                prazo, contado a partir de hoje.
            </p>

            <div id="rnvEscolhaLista" class="hidden mb-4 max-h-28 overflow-y-auto rounded-lg bg-gray-50 border border-gray-200 p-3">
                <ul id="rnvEscolhaItens" class="text-xs text-gray-600 space-y-1"></ul>
            </div>

            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">
                Quanto tempo a mais
            </label>

            <!-- Atalhos para os prazos que se usa quase sempre -->
            <div class="flex flex-wrap gap-2 mb-3" id="rnvAtalhos">
                <button type="button" data-dias="3"  class="rnv-atalho px-3 py-1.5 text-sm rounded-lg border border-gray-300 hover:border-amber-400 hover:bg-amber-50 transition-colors">3 dias</button>
                <button type="button" data-dias="7"  class="rnv-atalho px-3 py-1.5 text-sm rounded-lg border border-gray-300 hover:border-amber-400 hover:bg-amber-50 transition-colors">7 dias</button>
                <button type="button" data-dias="15" class="rnv-atalho px-3 py-1.5 text-sm rounded-lg border border-gray-300 hover:border-amber-400 hover:bg-amber-50 transition-colors">15 dias</button>
                <button type="button" data-dias="30" class="rnv-atalho px-3 py-1.5 text-sm rounded-lg border border-gray-300 hover:border-amber-400 hover:bg-amber-50 transition-colors">30 dias</button>
            </div>

            <div class="flex items-center gap-2 mb-1">
                <input type="number" id="rnvDias" value="7" min="1" max="30"
                       class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none">
                <span class="text-sm text-gray-600">dias (de 1 a 30)</span>
            </div>
            <p id="rnvErroDias" class="hidden text-xs text-red-600 mb-2"></p>

            <p class="text-sm text-gray-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 mt-3">
                <i class="far fa-calendar-check text-blue-500 mr-1"></i>
                Novo vencimento: <strong id="rnvVencimento">—</strong>
            </p>

            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mt-5 mb-2">
                O link de assinatura
            </label>

            <div class="space-y-2">
                <label class="rnv-opcao flex items-start gap-3 p-3 rounded-lg border border-amber-400 bg-amber-50 cursor-pointer">
                    <input type="radio" name="rnvLink" value="manter" checked class="mt-1 text-amber-600 focus:ring-amber-500">
                    <span>
                        <span class="block text-sm font-semibold text-gray-800">Manter o mesmo link</span>
                        <span class="block text-xs text-gray-500 mt-0.5">
                            O link que o signatário já recebeu volta a funcionar. Quem guardou
                            a mensagem antiga consegue assinar sem precisar de e-mail novo.
                        </span>
                    </span>
                </label>

                <label class="rnv-opcao flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-amber-300 cursor-pointer">
                    <input type="radio" name="rnvLink" value="novo" class="mt-1 text-amber-600 focus:ring-amber-500">
                    <span>
                        <span class="block text-sm font-semibold text-gray-800">Gerar link novo</span>
                        <span class="block text-xs text-gray-500 mt-0.5">
                            Invalida o link anterior. Use quando o link pode ter sido
                            repassado para outra pessoa.
                        </span>
                    </span>
                </label>
            </div>

            <label class="flex items-center gap-2 mt-3 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" id="rnvReenviar" checked
                       class="w-4 h-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                Avisar os signatários por e-mail
            </label>

            <p id="rnvAvisoLink" class="hidden text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-3">
                <i class="fas fa-triangle-exclamation mr-1"></i>
                O link enviado antes <strong>deixa de funcionar</strong>. Sem o aviso por
                e-mail, o signatário fica sem como assinar.
            </p>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-2">
            <button type="button" onclick="rnvFecharEscolha()"
                    class="px-4 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50">
                Cancelar
            </button>
            <button type="button" id="rnvConfirmar"
                    class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-paper-plane mr-1"></i> Confirmar e reenviar
            </button>
        </div>
    </div>
</div>

<!-- Resultado da renovação -->
<div id="rnvModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" onclick="rnvFecharModal()"></div>
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 id="rnvModalTitulo" class="font-bold text-gray-800">Renovação</h3>
            <button type="button" onclick="rnvFecharModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="rnvModalCorpo" class="px-6 py-5 overflow-y-auto text-sm text-gray-700"></div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
            <button type="button" onclick="rnvFecharModal()" class="px-4 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50">
                Fechar
            </button>
        </div>
    </div>
</div>

<script src="<?php echo $baseAssets; ?>/contex20/assets/global/plugins/jquery.min.js"></script>
<script src="<?php echo $baseAssets; ?>/contex20/assets/global/plugins/select2/js/select2.min.js"></script>
<script src="<?php echo $baseAssets; ?>/contex20/assets/global/plugins/select2/js/i18n/pt-BR.js"></script>
<script>
/* ─── Dar mais prazo ────────────────────────────────────────────────────
   Conversa com o renovar_link.php, que já existia pronto: ele renova o
   expires_at, reabre a solicitação expirada como 'pendente' (ou
   'em_progresso' se alguém já assinou), gera um token NOVO para cada
   signatário pendente — o link antigo morre, e é assim que tem de ser —
   e reenvia o e-mail.

   O token de sessão é obrigatório do outro lado: sem ele a renovação é
   recusada, e é o que impede uma página de fora de renovar prazos em
   nome de quem está logado aqui. */
(function () {
    "use strict";

    var TOKEN = <?php echo json_encode($renovarToken); ?>;
    var ENDPOINT = "renovar_link.php";

    var barra     = document.getElementById("rnvBarra");
    var escolha   = document.getElementById("rnvEscolha");
    var escQtd    = document.getElementById("rnvEscolhaQtd");
    var escLista  = document.getElementById("rnvEscolhaLista");
    var escItens  = document.getElementById("rnvEscolhaItens");
    var confirmar = document.getElementById("rnvConfirmar");
    var vencEl    = document.getElementById("rnvVencimento");
    var erroDias  = document.getElementById("rnvErroDias");

    /* O que o modal vai renovar quando o operador confirmar. */
    var pendente = { ids: [], rotulos: [] };
    var conta   = document.getElementById("rnvConta");
    var dias    = document.getElementById("rnvDias");
    var enviar  = document.getElementById("rnvEnviar");
    var limpar  = document.getElementById("rnvLimpar");
    var todos   = document.getElementById("rnvTodos");
    var modal   = document.getElementById("rnvModal");
    var titulo  = document.getElementById("rnvModalTitulo");
    var corpo   = document.getElementById("rnvModalCorpo");

    /* A tela existe sem a barra quando não há nada renovável na página. */
    if (!barra || !enviar) { return; }

    function marcados() {
        return Array.prototype.slice.call(document.querySelectorAll(".rnv-item:checked"));
    }

    function atualizarBarra() {
        var n = marcados().length;
        conta.textContent = n;
        barra.classList.toggle("hidden", n === 0);
        /* Espaço no fim da página para a barra fixa não cobrir a última
           linha da tabela. */
        document.body.style.paddingBottom = n > 0 ? "80px" : "";

        if (todos) {
            var itens = document.querySelectorAll(".rnv-item");
            todos.checked = itens.length > 0 && n === itens.length;
            todos.indeterminate = n > 0 && n < itens.length;
        }
    }

    document.addEventListener("change", function (e) {
        if (e.target.classList && e.target.classList.contains("rnv-item")) {
            atualizarBarra();
        }
    });

    if (todos) {
        todos.addEventListener("change", function () {
            document.querySelectorAll(".rnv-item").forEach(function (c) {
                c.checked = todos.checked;
            });
            atualizarBarra();
        });
    }

    if (limpar) {
        limpar.addEventListener("click", function () {
            document.querySelectorAll(".rnv-item").forEach(function (c) { c.checked = false; });
            atualizarBarra();
        });
    }

    function abrirModal(tit, html) {
        titulo.textContent = tit;
        corpo.innerHTML = html;
        modal.classList.remove("hidden");
    }

    window.rnvFecharModal = function () {
        modal.classList.add("hidden");
        /* Depois de renovar, a lista na tela está velha: os status e as
           datas mudaram no banco. Recarrega para o operador não agir sobre
           informação vencida. */
        if (modal.dataset.recarregar === "sim") { window.location.reload(); }
    };

    function escapar(texto) {
        var d = document.createElement("div");
        d.textContent = String(texto == null ? "" : texto);
        return d.innerHTML;
    }

    /* ── O modal de escolha ──────────────────────────────────────────
       `ids` é a lista achatada de todas as solicitações escolhidas: cada
       caixa pode carregar mais de um id, porque uma linha de grupo de
       envio representa o lote inteiro. */
    function abrirEscolha(ids, rotulos) {
        if (!ids.length) { return; }

        pendente.ids = ids;
        pendente.rotulos = rotulos || [];

        escQtd.textContent = ids.length;

        escItens.innerHTML = "";
        var nomes = pendente.rotulos.filter(Boolean);
        if (nomes.length) {
            nomes.slice(0, 12).forEach(function (r) {
                var li = document.createElement("li");
                li.textContent = "• " + r;
                escItens.appendChild(li);
            });
            if (nomes.length > 12) {
                var resto = document.createElement("li");
                resto.className = "text-gray-400";
                resto.textContent = "e mais " + (nomes.length - 12) + "…";
                escItens.appendChild(resto);
            }
            escLista.classList.remove("hidden");
        } else {
            escLista.classList.add("hidden");
        }

        erroDias.classList.add("hidden");
        /* Sempre reabre no padrão — manter o link e avisar. Guardar a
           escolha anterior faria o operador renovar o lote seguinte com
           uma opção que ele não conferiu. */
        var padrao = document.querySelector('input[name="rnvLink"][value="manter"]');
        if (padrao) { padrao.checked = true; }
        if (campoReenviar) { campoReenviar.checked = true; }
        revisarAviso();
        atualizarVencimento();
        escolha.classList.remove("hidden");
        dias.focus();
        dias.select();
    }

    window.rnvFecharEscolha = function () {
        escolha.classList.add("hidden");
        pendente = { ids: [], rotulos: [] };
    };

    /* Mostra a data em que o novo prazo vence. "Mais 7 dias" não diz
       nada para quem precisa avisar o signatário; "vence em 30/10" diz. */
    function atualizarVencimento() {
        var n = parseInt(dias.value, 10);
        if (!n || n < 1 || n > 30) {
            vencEl.textContent = "—";
            return false;
        }
        var d = new Date();
        d.setDate(d.getDate() + n);
        vencEl.textContent = d.toLocaleDateString("pt-BR", {
            day: "2-digit", month: "2-digit", year: "numeric"
        });
        return true;
    }

    dias.addEventListener("input", function () {
        erroDias.classList.add("hidden");
        atualizarVencimento();
    });

    document.querySelectorAll(".rnv-atalho").forEach(function (b) {
        b.addEventListener("click", function () {
            dias.value = b.dataset.dias;
            erroDias.classList.add("hidden");
            atualizarVencimento();
            /* Marca visualmente qual atalho está valendo */
            document.querySelectorAll(".rnv-atalho").forEach(function (o) {
                o.classList.remove("border-amber-500", "bg-amber-50", "text-amber-700", "font-semibold");
            });
            b.classList.add("border-amber-500", "bg-amber-50", "text-amber-700", "font-semibold");
        });
    });

    /* Enter no campo confirma; Esc fecha. */
    dias.addEventListener("keydown", function (e) {
        if (e.key === "Enter") { e.preventDefault(); confirmar.click(); }
    });
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && !escolha.classList.contains("hidden")) { window.rnvFecharEscolha(); }
    });

    confirmar.addEventListener("click", function () {
        var qtdDias = parseInt(dias.value, 10);

        if (!qtdDias || qtdDias < 1 || qtdDias > 30) {
            erroDias.textContent = "Informe um número de 1 a 30.";
            erroDias.classList.remove("hidden");
            dias.focus();
            return;
        }

        executar(pendente.ids, qtdDias);
    });

    var campoReenviar = document.getElementById("rnvReenviar");

    function linkEscolhido() {
        var m = document.querySelector('input[name="rnvLink"]:checked');
        return m ? m.value : "manter";
    }

    /* Gerar link novo SEM avisar ninguém deixa o signatário sem saída: o
       link que ele tem morreu e o novo não chegou. O botão fica vermelho
       só nessa combinação — é o único jeito de a ação dar errado em
       silêncio. */
    function revisarAviso() {
        var novo = linkEscolhido() === "novo";
        var semAviso = campoReenviar && !campoReenviar.checked;
        var caixa = document.getElementById("rnvAvisoLink");

        if (caixa) { caixa.classList.toggle("hidden", !novo); }

        document.querySelectorAll(".rnv-opcao").forEach(function (op) {
            var marcado = op.querySelector("input").checked;
            op.classList.toggle("border-amber-400", marcado);
            op.classList.toggle("bg-amber-50", marcado);
            op.classList.toggle("border-gray-200", !marcado);
        });

        if (confirmar) {
            var perigo = novo && semAviso;
            confirmar.classList.toggle("bg-red-600", perigo);
            confirmar.classList.toggle("hover:bg-red-700", perigo);
            confirmar.classList.toggle("bg-amber-500", !perigo);
            confirmar.classList.toggle("hover:bg-amber-600", !perigo);
        }
    }

    document.querySelectorAll('input[name="rnvLink"]').forEach(function (r) {
        r.addEventListener("change", revisarAviso);
    });
    if (campoReenviar) { campoReenviar.addEventListener("change", revisarAviso); }

    function executar(ids, qtdDias) {
        var dadosForm = new FormData();
        ids.forEach(function (id) { dadosForm.append("ids[]", id); });
        dadosForm.append("extra_dias", qtdDias);
        dadosForm.append("renovar_token", TOKEN);
        dadosForm.append("manter_link", linkEscolhido() === "manter" ? "sim" : "nao");
        dadosForm.append("reenviar_email", campoReenviar && campoReenviar.checked ? "sim" : "nao");

        confirmar.disabled = true;
        var textoOriginal = confirmar.innerHTML;
        confirmar.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Renovando…';

        fetch(ENDPOINT, { method: "POST", body: dadosForm, credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                var detalhes = "";
                if (dados.details && dados.details.length) {
                    detalhes = "<ul class=\"mt-3 text-xs text-gray-600 list-disc pl-5 space-y-1\">"
                        + dados.details.map(function (d) { return "<li>" + escapar(d) + "</li>"; }).join("")
                        + "</ul>";
                }
                var resumo = "";
                if (dados.success) {
                    resumo = '<p class="mt-2 text-xs text-gray-600">'
                        + (dados.link === "mantido"
                            ? "O link que os signatários já tinham voltou a funcionar."
                            : "Foi gerado um link novo; o anterior não vale mais.")
                        + " "
                        + (dados.email === "reenviado"
                            ? "O aviso por e-mail foi disparado."
                            : "Nenhum e-mail foi enviado.")
                        + "</p>";
                }
                window.rnvFecharEscolha();
                modal.dataset.recarregar = dados.success ? "sim" : "nao";
                abrirModal(
                    dados.success ? "Prazo renovado" : "Não foi possível renovar",
                    "<p>" + escapar(dados.message || "") + "</p>" + resumo + detalhes
                );
            })
            .catch(function (erro) {
                /* Falha de rede não é falha de regra: o prazo pode até ter
                   sido gravado. Por isso a mensagem manda conferir em vez
                   de afirmar que não funcionou. */
                window.rnvFecharEscolha();
                modal.dataset.recarregar = "sim";
                abrirModal(
                    "Erro de comunicação",
                    "<p>Não deu para confirmar o resultado: <strong>" + escapar(erro.message) + "</strong></p>"
                    + "<p class=\"mt-2 text-gray-600\">Recarregue a lista e confira se o prazo foi renovado antes de tentar de novo.</p>"
                );
            })
            .finally(function () {
                confirmar.disabled = false;
                confirmar.innerHTML = textoOriginal;
            });
    }

    enviar.addEventListener("click", function () {
        var ids = [];
        var rotulos = [];
        marcados().forEach(function (c) {
            String(c.value).split(",").forEach(function (id) {
                id = id.trim();
                if (id) { ids.push(id); }
            });
            rotulos.push(c.dataset.rotulo || "");
        });
        abrirEscolha(ids, rotulos);
    });

    /* O botão do relógio na linha renova só aquele envio, sem marcar nada. */
    document.addEventListener("click", function (e) {
        var botao = e.target.closest ? e.target.closest(".rnv-um") : null;
        if (!botao) { return; }
        var ids = String(botao.dataset.ids || "").split(",").filter(Boolean);
        abrirEscolha(ids, [botao.dataset.rotulo || ""]);
    });

    atualizarBarra();
})();

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
