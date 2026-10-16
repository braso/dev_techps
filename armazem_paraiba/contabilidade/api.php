<?php
/**
 * API de Contabilidade — TechPS Jornada
 *
 * Alimenta o app de extrato/cobrança com os números de cada empresa.
 * Só leitura, só GET, e a chave vai no header — nunca na URL, que fica
 * registrada em log de acesso, no histórico do navegador e no Referer.
 *
 * Autenticação: header  X-Api-Key: <chave>   (CONTABILIDADE_API_KEY no .env)
 *
 * Endpoints:
 *   GET ?recurso=status
 *   GET ?recurso=empresas[&mes=YYYY-MM][&status=ativo|inativo|todos][&detalhado=1]
 *       → a listagem completa: cada empresa com funcionários ativos,
 *         placas, assinaturas do mês e quantidade de ocupações.
 *         Com detalhado=1, cada empresa traz também a lista de ocupações
 *         (com quantas pessoas em cada) e as placas — o que permite
 *         precificar o conjunto todo sem uma chamada por empresa
 *   GET ?recurso=contagens&empresa_id=X[&mes=YYYY-MM]
 *       → o detalhe de uma empresa: cada ocupação com quantas pessoas,
 *         cada placa e as assinaturas do mês. É o que a tela de preços usa
 *   GET ?recurso=funcionarios[&empresa_id=X][&status=ativo|inativo|todos][&ocupacao=X]
 *   GET ?recurso=funcionario&id=X
 *   GET ?recurso=placas[&empresa_id=X]
 *   GET ?recurso=overview&empresa_id=X
 *
 * Esta API não devolve salário, RG nem PIS, e o CPF sai mascarado:
 * cobrança se faz com contagem, não com dado pessoal (LGPD, art. 6º, III).
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

/**
 * Erro de banco: a mensagem do MySQL vai para o log do servidor, não para
 * a resposta. Nome de tabela e de coluna são mapa da casa para quem ataca.
 */
function erroSql(mysqli $conn, string $contexto): void {
    error_log('[contabilidade/api] ' . $contexto . ': ' . mysqli_error($conn));
    jsonError(500, 'Erro ao consultar os dados.');
}

/** CPF mascarado: confere a pessoa sem expor o número. */
function cpfMascarado(?string $cpf): string {
    $d = preg_replace('/\D/', '', strval($cpf));
    if (strlen($d) !== 11) return '';
    return '***.***.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}

/**
 * Mês de referência vindo da query, no formato YYYY-MM.
 * Sem parâmetro, ou com lixo, usa o mês corrente: cobrança não pode
 * depender de o chamador acertar o formato.
 */
function mesReferencia(): string {
    $mes = trim(strval($_GET['mes'] ?? ''));
    if (preg_match('/^(\d{4})-(\d{2})$/', $mes, $m)
        && intval($m[2]) >= 1 && intval($m[2]) <= 12) {
        return $mes;
    }
    return date('Y-m');
}

// ── Headers CORS ──────────────────────────────────────────────────────────────
// Quem consome esta API é o backend do app de extrato, servidor a servidor,
// e servidor não usa CORS. Liberar "*" só serviria para um navegador qualquer
// tentar a sorte com a chave. Se algum dia um front precisar chamar direto,
// basta pôr a origem em CONTABILIDADE_CORS_ORIGIN no .env.
$corsOrigem = trim(strval($_ENV['CONTABILIDADE_CORS_ORIGIN'] ?? ''));
if ($corsOrigem !== '') {
    header('Access-Control-Allow-Origin: ' . $corsOrigem);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: X-Api-Key, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError(405, 'Método não permitido. Use GET.');
}

// ── Carrega .env ──────────────────────────────────────────────────────────────
$loadEnvPath = __DIR__ . '/../load_env.php';
if (!file_exists($loadEnvPath)) {
    jsonError(500, 'Configuração do servidor incompleta.');
}
require_once $loadEnvPath;

// ── Autenticação ──────────────────────────────────────────────────────────────
// Autentica ANTES de tocar no banco. Na ordem anterior, uma requisição sem
// chave abria conexão e, se o banco estivesse fora, recebia de volta o
// mysqli_connect_error() — host e usuário do banco entregues a quem não
// provou nada.
$apiKey = trim(strval(
    $_SERVER['HTTP_X_API_KEY']
    ?? $_SERVER['HTTP_X_APIKEY']
    ?? ''
));

$apiKeyEsperada = trim(strval($_ENV['CONTABILIDADE_API_KEY'] ?? ''));

if ($apiKeyEsperada === '') {
    error_log('[contabilidade/api] CONTABILIDADE_API_KEY não definida no .env');
    jsonError(500, 'Configuração do servidor incompleta.');
}

if ($apiKey === '' || !hash_equals($apiKeyEsperada, $apiKey)) {
    jsonError(401, 'Não autorizado. Informe a chave no header X-Api-Key.');
}

// ── Conecta ao banco ──────────────────────────────────────────────────────────
$conn = @mysqli_connect(
    $_ENV['DB_HOST']     ?? '',
    $_ENV['DB_USER']     ?? '',
    $_ENV['DB_PASSWORD'] ?? '',
    $_ENV['DB_NAME']     ?? ''
);
if (!$conn) {
    error_log('[contabilidade/api] falha de conexão: ' . mysqli_connect_error());
    jsonError(503, 'Banco de dados indisponível.');
}
$conn->set_charset('utf8mb4');

// ── Roteamento ────────────────────────────────────────────────────────────────
$recurso = strtolower(trim(strval($_GET['recurso'] ?? '')));

switch ($recurso) {

    case 'status':
        jsonOk(['status' => 'ok', 'timestamp' => date('Y-m-d H:i:s')]);
        break;

    case 'empresas':
        rotaEmpresas($conn);
        break;

    case 'contagens':
        rotaContagens($conn);
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
        jsonError(400, 'Recurso inválido. Opções: status, empresas, contagens, funcionarios, funcionario, placas, overview');
}

// ── Rotas ─────────────────────────────────────────────────────────────────────

function rotaEmpresas($conn): void {
    $status = strtolower(trim(strval($_GET['status'] ?? 'ativo')));
    $mes    = mesReferencia();
    $where  = '';
    if (in_array($status, ['ativo', 'inativo'], true)) {
        $s = mysqli_real_escape_string($conn, $status);
        $where = "WHERE empr_tx_status = '$s'";
    }

    $mesEsc = mysqli_real_escape_string($conn, $mes);

    /* Tudo numa consulta: a tela precisa de todas as empresas com todos os
       números, e uma chamada por empresa viraria dezenas de idas ao banco.

       As assinaturas são atribuídas pelo funcionário que assinou, porque
       solicitacoes_assinatura.empresa_id vem nulo nos registros existentes. */
    $sql = "
        SELECT
            e.empr_nb_id           AS id,
            e.empr_tx_nome         AS nome,
            e.empr_tx_fantasia     AS nome_fantasia,
            e.empr_tx_cnpj         AS cnpj,
            e.empr_tx_Ehmatriz     AS eh_matriz,
            e.empr_tx_status       AS status,
            e.empr_tx_dataCadastro AS data_cadastro,
            (
                SELECT COUNT(*) FROM entidade x
                 WHERE x.enti_nb_empresa = e.empr_nb_id AND x.enti_tx_status = 'ativo'
            ) AS total_funcionarios_ativos,
            (
                SELECT COUNT(*) FROM entidade x
                 WHERE x.enti_nb_empresa = e.empr_nb_id AND x.enti_tx_status = 'inativo'
            ) AS total_funcionarios_inativos,
            (
                SELECT COUNT(DISTINCT x.enti_tx_ocupacao) FROM entidade x
                 WHERE x.enti_nb_empresa = e.empr_nb_id AND x.enti_tx_status = 'ativo'
                   AND x.enti_tx_ocupacao IS NOT NULL AND x.enti_tx_ocupacao <> ''
            ) AS total_ocupacoes,
            (
                SELECT COUNT(*) FROM placa p WHERE p.plac_nb_empresa = e.empr_nb_id
            ) AS total_placas,
            (
                SELECT COUNT(*)
                  FROM assinantes a
                  JOIN solicitacoes_assinatura s ON s.id = a.id_solicitacao
                  LEFT JOIN entidade ea ON ea.enti_nb_id = COALESCE(a.enti_nb_id, s.enti_nb_id)
                 WHERE a.data_assinatura IS NOT NULL
                   AND DATE_FORMAT(a.data_assinatura, '%Y-%m') = '$mesEsc'
                   AND COALESCE(s.empresa_id, ea.enti_nb_empresa) = e.empr_nb_id
            ) AS total_assinaturas_mes,
            (
                SELECT COUNT(DISTINCT a.cpf)
                  FROM assinantes a
                  JOIN solicitacoes_assinatura s ON s.id = a.id_solicitacao
                  LEFT JOIN entidade ea ON ea.enti_nb_id = COALESCE(a.enti_nb_id, s.enti_nb_id)
                 WHERE a.data_assinatura IS NOT NULL
                   AND a.cpf IS NOT NULL AND a.cpf <> ''
                   AND DATE_FORMAT(a.data_assinatura, '%Y-%m') = '$mesEsc'
                   AND COALESCE(s.empresa_id, ea.enti_nb_empresa) = e.empr_nb_id
            ) AS total_assinantes_mes,
            (
                SELECT COUNT(DISTINCT s.id)
                  FROM solicitacoes_assinatura s
                  LEFT JOIN assinantes sa ON sa.id_solicitacao = s.id
                  LEFT JOIN entidade es ON es.enti_nb_id = COALESCE(s.enti_nb_id, sa.enti_nb_id)
                 WHERE DATE_FORMAT(s.data_solicitacao, '%Y-%m') = '$mesEsc'
                   AND COALESCE(s.empresa_id, es.enti_nb_empresa) = e.empr_nb_id
            ) AS total_documentos_mes
        FROM empresa e
        $where
        ORDER BY e.empr_tx_nome ASC
    ";

    $res = mysqli_query($conn, $sql);
    if (!$res) {
        erroSql($conn, 'Erro ao consultar empresas');
    }

    $empresas = [];
    $soma = [
        'funcionarios' => 0, 'placas' => 0, 'assinaturas' => 0, 'documentos' => 0,
    ];

    while ($row = mysqli_fetch_assoc($res)) {
        $row['cnpj'] = formatarCnpj($row['cnpj'] ?? '');
        foreach ([
            'total_funcionarios_ativos', 'total_funcionarios_inativos',
            'total_ocupacoes', 'total_placas', 'total_assinaturas_mes',
            'total_assinantes_mes', 'total_documentos_mes',
        ] as $campo) {
            $row[$campo] = intval($row[$campo]);
        }
        $soma['funcionarios'] += $row['total_funcionarios_ativos'];
        $soma['placas']       += $row['total_placas'];
        $soma['assinaturas']  += $row['total_assinaturas_mes'];
        $soma['documentos']   += $row['total_documentos_mes'];
        $empresas[] = $row;
    }

    /* Detalhe de ocupações e placas, quando pedido com &detalhado=1.
       São duas consultas agrupadas para o conjunto todo — o chamador que
       precisa precificar não deve ter de fazer uma chamada por empresa. */
    if (($_GET['detalhado'] ?? '') === '1' && $empresas !== []) {
        $ids = array_map(static fn($e) => intval($e['id']), $empresas);
        $listaIds = implode(',', $ids);

        $porEmpresaOcup = [];
        $resOc = mysqli_query($conn, "
            SELECT e.enti_nb_empresa AS empresa_id,
                   COALESCE(NULLIF(TRIM(e.enti_tx_ocupacao), ''), '(sem ocupação)') AS ocupacao,
                   COUNT(*) AS quantidade
              FROM entidade e
             WHERE e.enti_nb_empresa IN ($listaIds) AND e.enti_tx_status = 'ativo'
             GROUP BY 1, 2
             ORDER BY 3 DESC, 2 ASC
        ");
        if (!$resOc) {
            erroSql($conn, 'Erro ao consultar ocupações');
        }
        while ($row = mysqli_fetch_assoc($resOc)) {
            $porEmpresaOcup[intval($row['empresa_id'])][] = [
                'ocupacao'   => $row['ocupacao'],
                'quantidade' => intval($row['quantidade']),
            ];
        }

        $porEmpresaPlaca = [];
        $resPl = mysqli_query($conn, "
            SELECT p.plac_nb_empresa AS empresa_id, p.plac_tx_placa AS placa,
                   p.plac_tx_modelo AS veiculo
              FROM placa p
             WHERE p.plac_nb_empresa IN ($listaIds)
             ORDER BY p.plac_tx_placa ASC
        ");
        if (!$resPl) {
            erroSql($conn, 'Erro ao consultar placas');
        }
        while ($row = mysqli_fetch_assoc($resPl)) {
            $porEmpresaPlaca[intval($row['empresa_id'])][] = [
                'placa'   => $row['placa'],
                'veiculo' => $row['veiculo'],
            ];
        }

        foreach ($empresas as &$emp) {
            $id = intval($emp['id']);
            $emp['ocupacoes'] = $porEmpresaOcup[$id] ?? [];
            $emp['placas']    = $porEmpresaPlaca[$id] ?? [];
        }
        unset($emp);
    }

    jsonOk([
        'total'    => count($empresas),
        'mes'      => $mes,
        'somas'    => $soma,
        'empresas' => $empresas,
    ]);
}

/**
 * Contagens de uma empresa, item por item — é o que a tela de preços precisa
 * para perguntar "quanto custa cada ocupação" sem o operador adivinhar
 * quantas pessoas tem em cada uma.
 */
function rotaContagens($conn): void {
    $empresaId = intval($_GET['empresa_id'] ?? 0);
    if ($empresaId <= 0) {
        jsonError(400, 'Informe o parâmetro empresa_id.');
    }

    $mes    = mesReferencia();
    $mesEsc = mysqli_real_escape_string($conn, $mes);

    /* Empresa */
    $resEmp = mysqli_query($conn, "
        SELECT empr_nb_id AS id, empr_tx_nome AS nome, empr_tx_fantasia AS nome_fantasia,
               empr_tx_cnpj AS cnpj, empr_tx_status AS status
          FROM empresa WHERE empr_nb_id = $empresaId LIMIT 1
    ");
    if (!$resEmp) {
        erroSql($conn, 'Erro ao consultar empresa');
    }
    $empresa = mysqli_fetch_assoc($resEmp);
    if (!$empresa) {
        jsonError(404, 'Empresa não encontrada.');
    }
    $empresa['cnpj'] = formatarCnpj($empresa['cnpj'] ?? '');

    /* Ocupações com quantas pessoas em cada */
    $resOcup = mysqli_query($conn, "
        SELECT COALESCE(NULLIF(TRIM(e.enti_tx_ocupacao), ''), '(sem ocupação)') AS ocupacao,
               COUNT(*) AS quantidade
          FROM entidade e
         WHERE e.enti_nb_empresa = $empresaId AND e.enti_tx_status = 'ativo'
         GROUP BY 1 ORDER BY 2 DESC, 1 ASC
    ");
    if (!$resOcup) {
        erroSql($conn, 'Erro ao consultar ocupações');
    }
    $ocupacoes = [];
    while ($row = mysqli_fetch_assoc($resOcup)) {
        $ocupacoes[] = ['ocupacao' => $row['ocupacao'], 'quantidade' => intval($row['quantidade'])];
    }

    /* Placas */
    $resPlaca = mysqli_query($conn, "
        SELECT p.plac_tx_placa AS placa, p.plac_tx_modelo AS veiculo
          FROM placa p WHERE p.plac_nb_empresa = $empresaId
         ORDER BY p.plac_tx_placa ASC
    ");
    if (!$resPlaca) {
        erroSql($conn, 'Erro ao consultar placas');
    }
    $placas = [];
    while ($row = mysqli_fetch_assoc($resPlaca)) {
        $placas[] = ['placa' => $row['placa'], 'veiculo' => $row['veiculo']];
    }

    /* Assinaturas do mês — pela mesma regra da listagem */
    $resAss = mysqli_query($conn, "
        SELECT COUNT(*) AS assinaturas,
               COUNT(DISTINCT NULLIF(a.cpf, '')) AS assinantes,
               COUNT(DISTINCT s.id) AS documentos_assinados
          FROM assinantes a
          JOIN solicitacoes_assinatura s ON s.id = a.id_solicitacao
          LEFT JOIN entidade ea ON ea.enti_nb_id = COALESCE(a.enti_nb_id, s.enti_nb_id)
         WHERE a.data_assinatura IS NOT NULL
           AND DATE_FORMAT(a.data_assinatura, '%Y-%m') = '$mesEsc'
           AND COALESCE(s.empresa_id, ea.enti_nb_empresa) = $empresaId
    ");
    if (!$resAss) {
        erroSql($conn, 'Erro ao consultar assinaturas');
    }
    $ass = mysqli_fetch_assoc($resAss) ?: [];

    /* Documentos criados no mês — mesma atribuição da listagem, pelos
       signatários, porque a solicitação não guarda a empresa. */
    $resDoc = mysqli_query($conn, "
        SELECT COUNT(DISTINCT s.id) AS documentos
          FROM solicitacoes_assinatura s
          LEFT JOIN assinantes sa ON sa.id_solicitacao = s.id
          LEFT JOIN entidade es ON es.enti_nb_id = COALESCE(s.enti_nb_id, sa.enti_nb_id)
         WHERE DATE_FORMAT(s.data_solicitacao, '%Y-%m') = '$mesEsc'
           AND COALESCE(s.empresa_id, es.enti_nb_empresa) = $empresaId
    ");
    if (!$resDoc) {
        erroSql($conn, 'Erro ao consultar documentos');
    }
    $documentosNoMes = intval((mysqli_fetch_assoc($resDoc) ?: ['documentos' => 0])['documentos']);

    jsonOk([
        'mes'     => $mes,
        'empresa' => $empresa,
        'contagens' => [
            'funcionarios_ativos' => array_sum(array_column($ocupacoes, 'quantidade')),
            'placas'              => count($placas),
            'assinaturas'         => intval($ass['assinaturas'] ?? 0),
            'assinantes'          => intval($ass['assinantes'] ?? 0),
            'documentos_assinados' => intval($ass['documentos_assinados'] ?? 0),
            'documentos'          => $documentosNoMes,
        ],
        'ocupacoes' => $ocupacoes,
        'placas'    => $placas,
    ]);
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
            e.enti_tx_ocupacao     AS ocupacao,
            o.oper_tx_nome         AS cargo,
            e.enti_tx_admissao     AS data_admissao,
            e.enti_tx_desligamento AS data_desligamento,
            e.enti_tx_status       AS status,
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
        erroSql($conn, 'Erro ao consultar funcionários');
    }

    $funcionarios = [];
    while ($row = mysqli_fetch_assoc($res)) {
        /* CPF mascarado e nada de salário: a cobrança é por contagem. */
        $row['cpf']          = cpfMascarado($row['cpf'] ?? '');
        $row['empresa_cnpj'] = formatarCnpj($row['empresa_cnpj'] ?? '');
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
        erroSql($conn, 'Erro ao consultar funcionário');
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
        erroSql($conn, 'Erro ao consultar placas');
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
        erroSql($conn, 'Erro ao consultar empresa');
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
        erroSql($conn, 'Erro ao consultar funcionários');
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
        erroSql($conn, 'Erro ao consultar placas');
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
