<?php
/**
 * API de Contabilidade — TechPS Jornada
 *
 * Autenticação: header  X-Api-Key: <chave>
 *               ou query ?api_key=<chave>
 *
 * Endpoints:
 *   GET ?recurso=status
 *   GET ?recurso=empresas
 *   GET ?recurso=funcionarios[&empresa_id=X][&status=ativo|inativo|todos][&ocupacao=X]
 *   GET ?recurso=funcionario&id=X
 *   GET ?recurso=placas[&empresa_id=X]
 *   GET ?recurso=overview&empresa_id=X
 */

// ── Helpers definidos primeiro ────────────────────────────────────────────────
function jsonOk(array $data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        array_merge(['ok' => true], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );
    exit;
}

function jsonError(int $httpCode, string $mensagem): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($httpCode);
    echo json_encode(
        ['ok' => false, 'erro' => $mensagem],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function formatarCpf(string $cpf): string {
    $d = preg_replace('/\D/', '', $cpf);
    if (strlen($d) !== 11) return $cpf;
    return substr($d,0,3).'.'.substr($d,3,3).'.'.substr($d,6,3).'-'.substr($d,9,2);
}

function formatarCnpj(string $cnpj): string {
    $d = preg_replace('/\D/', '', $cnpj);
    if (strlen($d) !== 14) return $cnpj;
    return substr($d,0,2).'.'.substr($d,2,3).'.'.substr($d,5,3).'/'.substr($d,8,4).'-'.substr($d,12,2);
}

// ── Headers CORS ──────────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: X-Api-Key, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError(405, 'Método não permitido. Use GET.');
}

// ── Carrega .env e conecta ao banco ───────────────────────────────────────────
$loadEnvPath = __DIR__ . '/../load_env.php';
if (!file_exists($loadEnvPath)) {
    jsonError(500, 'Arquivo load_env.php não encontrado em: ' . $loadEnvPath);
}
require_once $loadEnvPath;

$conn = @mysqli_connect(
    $_ENV['DB_HOST']     ?? '',
    $_ENV['DB_USER']     ?? '',
    $_ENV['DB_PASSWORD'] ?? '',
    $_ENV['DB_NAME']     ?? ''
);
if (!$conn) {
    jsonError(503, 'Falha na conexão com o banco: ' . mysqli_connect_error());
}
$conn->set_charset('utf8');

// ── Autenticação ──────────────────────────────────────────────────────────────
$apiKey = trim(strval(
    $_SERVER['HTTP_X_API_KEY']
    ?? $_SERVER['HTTP_X_APIKEY']
    ?? $_GET['api_key']
    ?? ''
));

$apiKeyEsperada = trim(strval($_ENV['CONTABILIDADE_API_KEY'] ?? ''));

if ($apiKeyEsperada === '') {
    jsonError(500, 'CONTABILIDADE_API_KEY não definida no .env do servidor.');
}

if ($apiKey === '' || !hash_equals($apiKeyEsperada, $apiKey)) {
    jsonError(401, 'Não autorizado. Informe a chave no header X-Api-Key ou no parâmetro ?api_key=');
}

// ── Roteamento ────────────────────────────────────────────────────────────────
$recurso = strtolower(trim(strval($_GET['recurso'] ?? '')));

switch ($recurso) {

    case 'status':
        jsonOk(['status' => 'ok', 'timestamp' => date('Y-m-d H:i:s')]);
        break;

    case 'empresas':
        rotaEmpresas($conn);
        break;

    case 'funcionarios':
        rotaFuncionarios($conn);
        break;

    case 'funcionario':
        rotaFuncionario($conn);
        break;

    case 'placas':
        rotaPlacas($conn);
        break;

    case 'overview':
        rotaOverview($conn);
        break;

    default:
        jsonError(400, 'Recurso inválido. Opções: status, empresas, funcionarios, funcionario, placas, overview');
}

// ── Rotas ─────────────────────────────────────────────────────────────────────

function rotaEmpresas($conn): void {
    $status = strtolower(trim(strval($_GET['status'] ?? 'ativo')));
    $where  = '';
    if (in_array($status, ['ativo', 'inativo'], true)) {
        $s = mysqli_real_escape_string($conn, $status);
        $where = "WHERE empr_tx_status = '$s'";
    }

    $sql = "
        SELECT
            empr_nb_id           AS id,
            empr_tx_nome         AS nome,
            empr_tx_fantasia     AS nome_fantasia,
            empr_tx_cnpj         AS cnpj,
            empr_tx_Ehmatriz     AS eh_matriz,
            empr_tx_status       AS status,
            empr_tx_dataCadastro AS data_cadastro,
            (
                SELECT COUNT(*)
                FROM entidade
                WHERE enti_nb_empresa = empr_nb_id
                  AND enti_tx_status = 'ativo'
            ) AS total_funcionarios_ativos
        FROM empresa
        $where
        ORDER BY empr_tx_nome ASC
    ";

    $res = mysqli_query($conn, $sql);
    if (!$res) {
        jsonError(500, 'Erro ao consultar empresas: ' . mysqli_error($conn));
    }

    $empresas = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['cnpj'] = formatarCnpj($row['cnpj'] ?? '');
        $row['total_funcionarios_ativos'] = intval($row['total_funcionarios_ativos']);
        $empresas[] = $row;
    }

    jsonOk(['total' => count($empresas), 'empresas' => $empresas]);
}

function rotaFuncionarios($conn): void {
    $empresaId = intval($_GET['empresa_id'] ?? 0);
    $status    = strtolower(trim(strval($_GET['status'] ?? 'ativo')));
    $ocupacao  = trim(strval($_GET['ocupacao'] ?? ''));

    $where = ['1=1'];
    if ($empresaId > 0) {
        $where[] = "e.enti_nb_empresa = $empresaId";
    }
    if (in_array($status, ['ativo', 'inativo'], true)) {
        $s = mysqli_real_escape_string($conn, $status);
        $where[] = "e.enti_tx_status = '$s'";
    }
    if ($ocupacao !== '') {
        $o = mysqli_real_escape_string($conn, $ocupacao);
        $where[] = "e.enti_tx_ocupacao = '$o'";
    }

    $sql = "
        SELECT
            e.enti_nb_id           AS id,
            e.enti_tx_nome         AS nome,
            e.enti_tx_matricula    AS matricula,
            e.enti_tx_cpf          AS cpf,
            e.enti_tx_rg           AS rg,
            e.enti_tx_pis          AS pis,
            e.enti_tx_ocupacao     AS ocupacao,
            o.oper_tx_nome         AS cargo,
            e.enti_tx_admissao     AS data_admissao,
            e.enti_tx_desligamento AS data_desligamento,
            e.enti_tx_status       AS status,
            e.enti_nb_salario      AS salario,
            emp.empr_nb_id         AS empresa_id,
            emp.empr_tx_nome       AS empresa_nome,
            emp.empr_tx_cnpj       AS empresa_cnpj,
            g.grup_tx_nome         AS setor,
            p.para_tx_nome         AS parametro_jornada,
            p.para_tx_tipo         AS tipo_jornada,
            e.enti_tx_jornadaSemanal AS jornada_semanal,
            e.enti_tx_jornadaSabado  AS jornada_sabado
        FROM entidade e
        LEFT JOIN empresa emp          ON emp.empr_nb_id  = e.enti_nb_empresa
        LEFT JOIN operacao o           ON o.oper_nb_id    = e.enti_tx_tipoOperacao
        LEFT JOIN grupos_documentos g  ON g.grup_nb_id    = e.enti_setor_id
        LEFT JOIN parametro p          ON p.para_nb_id    = e.enti_nb_parametro
        WHERE " . implode(' AND ', $where) . "
        ORDER BY emp.empr_tx_nome ASC, e.enti_tx_nome ASC
    ";

    $res = mysqli_query($conn, $sql);
    if (!$res) {
        jsonError(500, 'Erro ao consultar funcionários: ' . mysqli_error($conn));
    }

    $funcionarios = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['cpf']          = formatarCpf($row['cpf'] ?? '');
        $row['empresa_cnpj'] = formatarCnpj($row['empresa_cnpj'] ?? '');
        $row['salario']      = $row['salario'] !== null ? floatval($row['salario']) : null;
        $funcionarios[] = $row;
    }

    jsonOk(['total' => count($funcionarios), 'funcionarios' => $funcionarios]);
}

function rotaFuncionario($conn): void {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonError(400, 'Informe o parâmetro id do funcionário.');
    }

    $sql = "
        SELECT
            e.enti_nb_id                AS id,
            e.enti_tx_nome              AS nome,
            e.enti_tx_matricula         AS matricula,
            e.enti_tx_cpf               AS cpf,
            e.enti_tx_rg                AS rg,
            e.enti_tx_rgOrgao           AS rg_orgao,
            e.enti_tx_rgDataEmissao     AS rg_data_emissao,
            e.enti_tx_rgUf              AS rg_uf,
            e.enti_tx_pis               AS pis,
            e.enti_tx_ctpsNumero        AS ctps_numero,
            e.enti_tx_ctpsSerie         AS ctps_serie,
            e.enti_tx_ctpsUf            AS ctps_uf,
            e.enti_tx_email             AS email,
            e.enti_tx_fone1             AS telefone1,
            e.enti_tx_fone2             AS telefone2,
            e.enti_tx_nascimento        AS data_nascimento,
            e.enti_tx_sexo              AS sexo,
            e.enti_tx_civil             AS estado_civil,
            e.enti_tx_racaCor           AS raca_cor,
            e.enti_tx_tipoSanguineo     AS tipo_sanguineo,
            e.enti_tx_ocupacao          AS ocupacao,
            o.oper_tx_nome              AS cargo,
            e.enti_tx_admissao          AS data_admissao,
            e.enti_tx_desligamento      AS data_desligamento,
            e.enti_tx_status            AS status,
            e.enti_nb_salario           AS salario,
            e.enti_tx_banco             AS banco_horas,
            e.enti_tx_cep               AS cep,
            e.enti_tx_endereco          AS endereco,
            e.enti_tx_numero            AS numero,
            e.enti_tx_complemento       AS complemento,
            e.enti_tx_bairro            AS bairro,
            cid.cida_tx_nome            AS cidade,
            cid.cida_tx_uf              AS uf,
            e.enti_tx_pai               AS nome_pai,
            e.enti_tx_mae               AS nome_mae,
            e.enti_tx_jornadaSemanal    AS jornada_semanal,
            e.enti_tx_jornadaSabado     AS jornada_sabado,
            e.enti_tx_percHESemanal     AS perc_he_semanal,
            e.enti_tx_percHEEx          AS perc_he_extra,
            emp.empr_nb_id              AS empresa_id,
            emp.empr_tx_nome            AS empresa_nome,
            emp.empr_tx_fantasia        AS empresa_fantasia,
            emp.empr_tx_cnpj            AS empresa_cnpj,
            g.grup_tx_nome              AS setor,
            sb.sbgr_tx_nome             AS subsetor,
            p.para_nb_id                AS parametro_id,
            p.para_tx_nome              AS parametro_nome,
            p.para_tx_tipo              AS parametro_tipo,
            p.para_tx_abonarFeriadoEscala AS parametro_abonar_feriado_escala
        FROM entidade e
        LEFT JOIN empresa emp          ON emp.empr_nb_id   = e.enti_nb_empresa
        LEFT JOIN operacao o           ON o.oper_nb_id     = e.enti_tx_tipoOperacao
        LEFT JOIN grupos_documentos g  ON g.grup_nb_id     = e.enti_setor_id
        LEFT JOIN sbgrupos_documentos sb ON sb.sbgr_nb_id  = e.enti_subSetor_id
        LEFT JOIN parametro p          ON p.para_nb_id     = e.enti_nb_parametro
        LEFT JOIN cidade cid           ON cid.cida_nb_id   = e.enti_nb_cidade
        WHERE e.enti_nb_id = $id
        LIMIT 1
    ";

    $res = mysqli_query($conn, $sql);
    if (!$res) {
        jsonError(500, 'Erro ao consultar funcionário: ' . mysqli_error($conn));
    }

    $row = mysqli_fetch_assoc($res);
    if (!$row) {
        jsonError(404, 'Funcionário não encontrado.');
    }

    $row['cpf']          = formatarCpf($row['cpf'] ?? '');
    $row['empresa_cnpj'] = formatarCnpj($row['empresa_cnpj'] ?? '');
    $row['salario']      = $row['salario'] !== null ? floatval($row['salario']) : null;

    jsonOk(['funcionario' => $row]);
}

// ── Rota: Placas ──────────────────────────────────────────────────────────────
function rotaPlacas($conn): void {
    $empresaId = intval($_GET['empresa_id'] ?? 0);

    $where = '1=1';
    if ($empresaId > 0) {
        $where .= " AND p.plac_nb_empresa = $empresaId";
    }

    $sql = "
        SELECT
            p.plac_nb_id            AS id,
            p.plac_tx_placa         AS placa,
            p.plac_tx_modelo        AS veiculo,
            emp.empr_nb_id          AS empresa_id,
            emp.empr_tx_nome        AS empresa_nome,
            emp.empr_tx_cnpj        AS empresa_cnpj,
            e.enti_nb_id            AS motorista_id,
            e.enti_tx_nome          AS motorista_nome,
            e.enti_tx_matricula     AS motorista_matricula,
            p.plac_tx_dataCadastro  AS data_cadastro,
            p.plac_tx_dataAtualiza  AS data_atualizacao
        FROM placa p
        JOIN empresa emp    ON emp.empr_nb_id  = p.plac_nb_empresa
        LEFT JOIN entidade e ON e.enti_nb_id   = p.plac_nb_entidade
        WHERE $where
        ORDER BY emp.empr_tx_nome ASC, p.plac_tx_placa ASC
    ";

    $res = mysqli_query($conn, $sql);
    if (!$res) {
        jsonError(500, 'Erro ao consultar placas: ' . mysqli_error($conn));
    }

    $placas = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['empresa_cnpj'] = formatarCnpj($row['empresa_cnpj'] ?? '');
        $placas[] = $row;
    }

    jsonOk(['total' => count($placas), 'placas' => $placas]);
}

// ── Rota: Overview (empresa + funcionários + placas) ─────────────────────────
function rotaOverview($conn): void {
    $empresaId = intval($_GET['empresa_id'] ?? 0);
    if ($empresaId <= 0) {
        jsonError(400, 'Informe o parâmetro empresa_id.');
    }

    // Empresa
    $sqlEmp = "
        SELECT
            empr_nb_id          AS id,
            empr_tx_nome        AS nome,
            empr_tx_fantasia    AS nome_fantasia,
            empr_tx_cnpj        AS cnpj,
            empr_tx_Ehmatriz    AS eh_matriz,
            empr_tx_status      AS status,
            empr_tx_dataCadastro AS data_cadastro
        FROM empresa
        WHERE empr_nb_id = $empresaId
        LIMIT 1
    ";
    $resEmp = mysqli_query($conn, $sqlEmp);
    if (!$resEmp) {
        jsonError(500, 'Erro ao consultar empresa: ' . mysqli_error($conn));
    }
    $empresa = mysqli_fetch_assoc($resEmp);
    if (!$empresa) {
        jsonError(404, 'Empresa não encontrada.');
    }
    $empresa['cnpj'] = formatarCnpj($empresa['cnpj'] ?? '');

    // Funcionários
    $sqlFunc = "
        SELECT
            e.enti_nb_id            AS id,
            e.enti_tx_nome          AS nome,
            e.enti_tx_matricula     AS matricula,
            e.enti_tx_cpf           AS cpf,
            e.enti_tx_pis           AS pis,
            e.enti_tx_ocupacao      AS ocupacao,
            o.oper_tx_nome          AS cargo,
            e.enti_tx_admissao      AS data_admissao,
            e.enti_tx_desligamento  AS data_desligamento,
            e.enti_tx_status        AS status,
            e.enti_nb_salario       AS salario,
            g.grup_tx_nome          AS setor,
            p.para_tx_nome          AS parametro_jornada,
            p.para_tx_tipo          AS tipo_jornada
        FROM entidade e
        LEFT JOIN operacao o          ON o.oper_nb_id   = e.enti_tx_tipoOperacao
        LEFT JOIN grupos_documentos g ON g.grup_nb_id   = e.enti_setor_id
        LEFT JOIN parametro p         ON p.para_nb_id   = e.enti_nb_parametro
        WHERE e.enti_nb_empresa = $empresaId
          AND e.enti_tx_status = 'ativo'
        ORDER BY e.enti_tx_nome ASC
    ";
    $resFunc = mysqli_query($conn, $sqlFunc);
    if (!$resFunc) {
        jsonError(500, 'Erro ao consultar funcionários: ' . mysqli_error($conn));
    }
    $funcionarios = [];
    while ($row = mysqli_fetch_assoc($resFunc)) {
        $row['cpf']    = formatarCpf($row['cpf'] ?? '');
        $row['salario'] = $row['salario'] !== null ? floatval($row['salario']) : null;
        $funcionarios[] = $row;
    }

    // Placas
    $sqlPlacas = "
        SELECT
            p.plac_nb_id            AS id,
            p.plac_tx_placa         AS placa,
            p.plac_tx_modelo        AS veiculo,
            e.enti_nb_id            AS motorista_id,
            e.enti_tx_nome          AS motorista_nome,
            e.enti_tx_matricula     AS motorista_matricula,
            p.plac_tx_dataCadastro  AS data_cadastro
        FROM placa p
        LEFT JOIN entidade e ON e.enti_nb_id = p.plac_nb_entidade
        WHERE p.plac_nb_empresa = $empresaId
        ORDER BY p.plac_tx_placa ASC
    ";
    $resPlacas = mysqli_query($conn, $sqlPlacas);
    if (!$resPlacas) {
        jsonError(500, 'Erro ao consultar placas: ' . mysqli_error($conn));
    }
    $placas = [];
    while ($row = mysqli_fetch_assoc($resPlacas)) {
        $placas[] = $row;
    }

    jsonOk([
        'empresa'     => $empresa,
        'resumo'      => [
            'total_funcionarios_ativos' => count($funcionarios),
            'total_placas'              => count($placas),
        ],
        'funcionarios' => $funcionarios,
        'placas'       => $placas,
    ]);
}
