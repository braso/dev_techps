<?php
/*
 * Ficha Funcional — geração e impressão de QR Codes.
 *
 * Tela interna autenticada. Permite:
 *  - abrir o QR individual de um funcionário;
 *  - listar/filtrar funcionários e gerar cartões em lote;
 *  - copiar link/código;
 *  - regenerar o código (invalida o QR anterior).
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

include "conecta.php";
include_once "check_permission.php";
require_once __DIR__ . '/ficha_publica_funcoes.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

$conn = ficha_publica_db();
if (!($conn instanceof mysqli)) {
    http_response_code(500);
    echo '<h1>Não foi possível conectar ao banco de dados.</h1>';
    exit;
}

if (!ficha_publica_ensure_table($conn)) {
    http_response_code(500);
    echo '<h1>Não foi possível preparar a estrutura da ficha pública.</h1>';
    exit;
}

$userId = (int) ($_SESSION['user_nb_id'] ?? 0);
$nivel = strval($_SESSION['user_tx_nivel'] ?? '');
$isAdmin = (bool) preg_match('/administrador|super\s*admin|adminsitrador/i', $nivel);
$empresaSessao = (int) ($_SESSION['user_nb_empresa'] ?? 0);

// A tela pode ser liberada pela permissão de Funcionário (acesso pelo cadastro)
// ou pelo próprio item de menu "Ficha Funcional (QR)".
if (!$isAdmin && function_exists('temPermissaoMenu')) {
    $temAcessoFuncionario = temPermissaoMenu('/cadastro_funcionario.php');
    $temAcessoQr = temPermissaoMenu('/ficha_qrcode.php');

    if (!$temAcessoFuncionario && !$temAcessoQr && function_exists('verificaPermissao')) {
        verificaPermissao('/cadastro_funcionario.php');
    }
}

if (!function_exists('ficha_qrcode_h')) {
    function ficha_qrcode_h($valor): string
    {
        return ficha_publica_h($valor);
    }
}

if (!function_exists('ficha_qrcode_funcionario_ativo')) {
    function ficha_qrcode_funcionario_ativo(array $funcionario): bool
    {
        return strtolower(trim(strval($funcionario['enti_tx_status'] ?? ''))) === 'ativo';
    }
}

if (!function_exists('ficha_qrcode_funcionario_permitido')) {
    function ficha_qrcode_funcionario_permitido(array $funcionario, bool $isAdmin, int $empresaSessao): bool
    {
        if ($isAdmin) {
            return true;
        }
        return ((int) ($funcionario['enti_nb_empresa'] ?? 0)) === $empresaSessao;
    }
}

if (!function_exists('ficha_qrcode_obter_ou_criar')) {
    function ficha_qrcode_obter_ou_criar(mysqli $conn, array $funcionario, int $userId): ?string
    {
        $entidadeId = (int) ($funcionario['enti_nb_id'] ?? 0);
        if ($entidadeId <= 0) {
            return null;
        }

        $antes = ficha_publica_obter_codigo_ativo($conn, $entidadeId);
        $codigo = ficha_publica_obter_ou_criar_codigo($conn, $entidadeId, $userId);

        if ($codigo !== null && $antes === null && function_exists('registrar_log')) {
            registrar_log(
                'ficha_publica',
                'codigo_gerado',
                'Código da ficha funcional gerado para o funcionário #' . $entidadeId . '.',
                [],
                'info',
                [
                    'recurso_tipo' => 'entidade',
                    'recurso_id' => $entidadeId,
                ]
            );
        }

        return $codigo;
    }
}

if (!function_exists('ficha_qrcode_carregar_individual')) {
    /**
     * Valida o funcionário e devolve os dados + código ativo (criando se necessário).
     *
     * @return array{funcionario?:array,codigo?:string,novo?:bool,erro?:string}
     */
    function ficha_qrcode_carregar_individual(
        mysqli $conn,
        int $entidadeId,
        bool $isAdmin,
        int $empresaSessao,
        int $userId
    ): array {
        if ($entidadeId <= 0) {
            return ['erro' => 'Funcionário inválido.'];
        }

        $funcionario = ficha_publica_obter_funcionario($conn, $entidadeId);
        if (empty($funcionario)) {
            return ['erro' => 'Funcionário não encontrado.'];
        }

        if (!ficha_qrcode_funcionario_permitido($funcionario, $isAdmin, $empresaSessao)) {
            return ['erro' => 'Você não tem permissão para acessar este funcionário.'];
        }

        if (!ficha_qrcode_funcionario_ativo($funcionario)) {
            return ['erro' => 'Este funcionário está inativo. A ficha pública só é exibida para funcionários ativos.'];
        }

        $antes = ficha_publica_obter_codigo_ativo($conn, $entidadeId);
        $codigo = ficha_qrcode_obter_ou_criar($conn, $funcionario, $userId);
        if ($codigo === null) {
            return ['erro' => 'Não foi possível gerar o código da ficha. Tente novamente.'];
        }

        return [
            'funcionario' => $funcionario,
            'codigo' => $codigo,
            'novo' => ($antes === null),
        ];
    }
}

if (!function_exists('ficha_qrcode_status_funcionario')) {
    function ficha_qrcode_status_funcionario(array $funcionario): string
    {
        $ativo = ficha_qrcode_funcionario_ativo($funcionario);
        return $ativo
            ? '<span class="status status-ativo">Ativo</span>'
            : '<span class="status status-inativo">Inativo</span>';
    }
}

if (!function_exists('ficha_qrcode_card_html')) {
    /**
     * Renderiza o cartão imprimível com QR Code.
     */
    function ficha_qrcode_card_html(array $funcionario, string $codigo): string
    {
        $nome = trim(strval($funcionario['enti_tx_nome'] ?? ''));
        $matricula = trim(strval($funcionario['enti_tx_matricula'] ?? ''));
        $empresa = trim(strval($funcionario['empr_tx_nome'] ?? ''));
        $codigoFormatado = ficha_publica_formatar_codigo($codigo);
        $url = ficha_publica_url($codigo);
        $svg = ficha_publica_qr_svg($url, 4);

        $logo = ficha_publica_resolver_arquivo($funcionario['empr_tx_logo'] ?? '');
        if ($logo === null) {
            $logo = ficha_publica_resolver_arquivo('imagens/logo_topo_cliente.png');
        }

        $paginaUrl = preg_replace('#^https?://#i', '', ficha_publica_pagina_url());
        $svgHtml = $svg !== ''
            ? $svg
            : '<div class="qr-empty">QR indisponível</div>';

        $html = '<article class="qr-card">';
        $html .= '<div class="qr-card-head">';
        if (!empty($logo)) {
            $html .= '<img class="qr-card-logo" src="' . ficha_qrcode_h($logo['url']) . '" alt="">';
        }
        $html .= '<div class="qr-card-company">' . ficha_qrcode_h($empresa !== '' ? $empresa : 'Ficha Funcional') . '</div>';
        $html .= '</div>';

        $html .= '<div class="qr-card-body">';
        $html .= '<div class="qr-card-qr">' . $svgHtml . '</div>';
        $html .= '<div class="qr-card-info">';
        $html .= '<div class="qr-card-kicker">Ficha Funcional</div>';
        $html .= '<div class="qr-card-name">' . ficha_qrcode_h($nome !== '' ? $nome : 'Funcionário') . '</div>';
        $html .= '<div class="qr-card-meta">Matrícula: <strong>' . ficha_qrcode_h($matricula !== '' ? $matricula : '—') . '</strong></div>';
        $html .= '<div class="qr-card-code-label">Código de acesso</div>';
        $html .= '<div class="qr-card-code">' . ficha_qrcode_h($codigoFormatado) . '</div>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="qr-card-foot">Acesse <strong>' . ficha_qrcode_h($paginaUrl) . '</strong> e informe o código acima ou escaneie o QR Code.</div>';
        $html .= '</article>';

        return $html;
    }
}

$modo = 'lista';
$erroPagina = '';
$avisos = [];
$funcionario = null;
$codigo = null;
$codigoNovo = false;
$cards = [];

$acao = strval($_POST['ffp_action'] ?? '');
$idPost = (int) ($_POST['ffp_id'] ?? 0);
$idGet = (int) ($_GET['ffp_id'] ?? 0);
$msg = strval($_GET['msg'] ?? '');

/*
 * Ações POST.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'regenerar') {
    $carregar = ficha_qrcode_carregar_individual($conn, $idPost, $isAdmin, $empresaSessao, $userId);

    if (!empty($carregar['erro'])) {
        $erroPagina = strval($carregar['erro']);
        $modo = 'lista';
    } else {
        $novo = ficha_publica_regenerar_codigo($conn, $idPost, $userId);
        if ($novo !== null) {
            if (function_exists('registrar_log')) {
                registrar_log(
                    'ficha_publica',
                    'codigo_regenerado',
                    'Código da ficha funcional regenerado para o funcionário #' . $idPost . '.',
                    [],
                    'info',
                    [
                        'recurso_tipo' => 'entidade',
                        'recurso_id' => $idPost,
                    ]
                );
            }

            header('Location: ficha_qrcode.php?ffp_id=' . $idPost . '&msg=regenerado');
            exit;
        }

        $erroPagina = 'Não foi possível gerar um novo código. Tente novamente.';
        $funcionario = $carregar['funcionario'];
        $codigo = $carregar['codigo'];
        $modo = 'individual';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'gerar_lote') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
        return $id > 0;
    })));

    if (empty($ids)) {
        $erroPagina = 'Selecione pelo menos um funcionário para gerar os cartões.';
        $modo = 'lista';
    } else {
        foreach ($ids as $id) {
            $funcionarioLote = ficha_publica_obter_funcionario($conn, $id);

            if (empty($funcionarioLote)) {
                $avisos[] = 'Funcionário #' . $id . ' não encontrado.';
                continue;
            }

            if (!ficha_qrcode_funcionario_permitido($funcionarioLote, $isAdmin, $empresaSessao)) {
                $avisos[] = 'Sem permissão para o funcionário #' . $id . '.';
                continue;
            }

            if (!ficha_qrcode_funcionario_ativo($funcionarioLote)) {
                $avisos[] = 'O funcionário ' . strval($funcionarioLote['enti_tx_nome'] ?? ('#' . $id)) . ' está inativo e foi ignorado.';
                continue;
            }

            $codigoLote = ficha_qrcode_obter_ou_criar($conn, $funcionarioLote, $userId);
            if ($codigoLote === null) {
                $avisos[] = 'Não foi possível gerar o código de ' . strval($funcionarioLote['enti_tx_nome'] ?? ('#' . $id)) . '.';
                continue;
            }

            $cards[] = [
                'funcionario' => $funcionarioLote,
                'codigo' => $codigoLote,
            ];
        }

        $modo = 'lote';
    }
}

/*
 * Visualização individual via GET.
 */
if ($modo !== 'lote' && $idGet > 0) {
    $carregar = ficha_qrcode_carregar_individual($conn, $idGet, $isAdmin, $empresaSessao, $userId);

    if (!empty($carregar['erro'])) {
        $erroPagina = strval($carregar['erro']);
        $modo = 'lista';
    } else {
        $funcionario = $carregar['funcionario'];
        $codigo = $carregar['codigo'];
        $codigoNovo = (bool) ($carregar['novo'] ?? false);
        $modo = 'individual';
    }
}

/*
 * Listagem.
 */
$f_nome = trim(strval($_GET['f_nome'] ?? ''));
$f_matricula = trim(strval($_GET['f_matricula'] ?? ''));
$f_empresa_raw = array_key_exists('f_empresa', $_GET) ? trim(strval($_GET['f_empresa'])) : '';
$f_empresa = ($f_empresa_raw !== '' && ctype_digit($f_empresa_raw)) ? (int) $f_empresa_raw : -1;
$f_setor = (int) ($_GET['f_setor'] ?? 0);
$f_status = array_key_exists('f_status', $_GET) ? trim(strval($_GET['f_status'])) : 'ativo';
if (!in_array($f_status, ['', 'ativo', 'inativo'], true)) {
    $f_status = 'ativo';
}

$funcionarios = [];
$empresas = [];
$setores = [];

if ($modo === 'lista') {
    $empresas = ficha_publica_stmt_all(
        $conn,
        "SELECT empr_nb_id, empr_tx_nome
         FROM empresa
         WHERE empr_tx_status = 'ativo'
         ORDER BY empr_tx_nome ASC"
    );

    $setores = ficha_publica_stmt_all(
        $conn,
        "SELECT grup_nb_id, grup_tx_nome
         FROM grupos_documentos
         ORDER BY grup_tx_nome ASC"
    );

    $where = [];
    $types = '';
    $params = [];

    if (!$isAdmin) {
        $where[] = "COALESCE(e.enti_nb_empresa, 0) = ?";
        $types .= 'i';
        $params[] = $empresaSessao;
    } elseif ($f_empresa >= 0) {
        $where[] = "COALESCE(e.enti_nb_empresa, 0) = ?";
        $types .= 'i';
        $params[] = $f_empresa;
    }

    if ($f_setor > 0) {
        $where[] = "e.enti_setor_id = ?";
        $types .= 'i';
        $params[] = $f_setor;
    }

    if ($f_status !== '') {
        $where[] = "e.enti_tx_status = ?";
        $types .= 's';
        $params[] = $f_status;
    }

    if ($f_nome !== '') {
        $where[] = "e.enti_tx_nome LIKE ?";
        $types .= 's';
        $params[] = '%' . $f_nome . '%';
    }

    if ($f_matricula !== '') {
        $where[] = "e.enti_tx_matricula LIKE ?";
        $types .= 's';
        $params[] = '%' . $f_matricula . '%';
    }

    $whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT e.enti_nb_id,
                   e.enti_tx_nome,
                   e.enti_tx_matricula,
                   e.enti_tx_status,
                   COALESCE(emp.empr_tx_nome, '—') AS empresa_nome,
                   COALESCE(g.grup_tx_nome, '—') AS setor_nome,
                   f.ffp_tx_codigo
            FROM entidade e
            LEFT JOIN empresa emp ON emp.empr_nb_id = e.enti_nb_empresa
            LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
            LEFT JOIN (
                SELECT ffp_nb_entidade, MAX(ffp_nb_id) AS ffp_nb_id
                FROM funcionario_ficha_publica
                WHERE ffp_tx_status = 'ativo'
                GROUP BY ffp_nb_entidade
            ) fx ON fx.ffp_nb_entidade = e.enti_nb_id
            LEFT JOIN funcionario_ficha_publica f ON f.ffp_nb_id = fx.ffp_nb_id
            {$whereSql}
            ORDER BY e.enti_tx_nome ASC
            LIMIT 500";

    $funcionarios = ficha_publica_stmt_all($conn, $sql, $types, $params);
}

$statusOpcoes = [
    '' => 'Todos',
    'ativo' => 'Ativo',
    'inativo' => 'Inativo',
];

$paginaUrl = ficha_publica_pagina_url();
$urlCompleta = $codigo !== null ? ficha_publica_url($codigo) : '';
$codigoFormatado = $codigo !== null ? ficha_publica_formatar_codigo($codigo) : '';
$logoCliente = ficha_publica_resolver_arquivo('imagens/logo_topo_cliente.png');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Ficha Funcional — QR Codes</title>
    <style>
        :root {
            --brand: #1f4e79;
            --brand-dark: #153a5c;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --soft: #f8fafc;
            --green: #166534;
            --green-bg: #dcfce7;
            --red: #991b1b;
            --red-bg: #fee2e2;
            --yellow: #92400e;
            --yellow-bg: #fef3c7;
        }

        * { box-sizing: border-box; }

        html, body { margin: 0; padding: 0; }

        body {
            background: #eef2f7;
            color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            line-height: 1.45;
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }

        .admin-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--brand);
            text-decoration: none;
            font-weight: 800;
        }

        .brand img {
            max-height: 34px;
            max-width: 140px;
            object-fit: contain;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            border: 1px solid transparent;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s ease, border-color .15s ease;
        }

        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-light { background: #fff; color: var(--brand); border-color: #cbd5e1; }
        .btn-light:hover { border-color: var(--brand); }
        .btn-danger { background: #b91c1c; color: #fff; }
        .btn-danger:hover { background: #991b1b; }
        .btn-small { padding: 6px 10px; font-size: 12px; border-radius: 8px; }

        .view-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }

        .view-head h1 {
            margin: 0;
            font-size: 24px;
            letter-spacing: -.02em;
        }

        .view-head p {
            margin: 4px 0 0;
            font-size: 13px;
            color: var(--muted);
        }

        .view-head .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .alert {
            border-radius: 11px;
            padding: 12px 14px;
            font-size: 13px;
            margin: 0 0 16px;
            border: 1px solid transparent;
        }

        .alert-error { background: #fef2f2; border-color: #fecaca; color: var(--red); }
        .alert-warning { background: #fffbeb; border-color: #fde68a; color: var(--yellow); }
        .alert-success { background: #f0fdf4; border-color: #bbf7d0; color: var(--green); }
        .alert ul { margin: 6px 0 0 18px; padding: 0; }

        .filters {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 16px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .field label {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .field input,
        .field select {
            width: 100%;
            padding: 9px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: var(--ink);
            font-family: inherit;
            font-size: 13px;
        }

        .filters .actions {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            grid-column: span 2;
        }

        .table-wrap {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: auto;
        }

        table {
            width: 100%;
            min-width: 860px;
            border-collapse: collapse;
        }

        th, td {
            padding: 11px 12px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            font-size: 13px;
            vertical-align: middle;
        }

        th {
            background: var(--soft);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--muted);
            white-space: nowrap;
        }

        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: #fbfdff; }

        .cell-main { font-weight: 700; }
        .cell-sub { margin-top: 2px; font-size: 11px; color: var(--muted); }
        .muted { color: var(--muted); }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            white-space: nowrap;
        }

        .status-ativo { background: var(--green-bg); color: var(--green); }
        .status-inativo { background: var(--red-bg); color: var(--red); }

        code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            letter-spacing: .06em;
        }

        .table-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 14px;
            background: #fff;
            border: 1px solid var(--line);
            border-top: 0;
            border-radius: 0 0 14px 14px;
            margin-top: -1px;
        }

        .table-note {
            font-size: 12px;
            color: var(--muted);
        }

        .empty-row {
            text-align: center;
            padding: 28px 12px;
            color: var(--muted);
        }

        .single-card-wrap {
            display: flex;
            justify-content: center;
            padding: 8px 0 4px;
        }

        .single-card-wrap .qr-card {
            width: 430px;
            max-width: 100%;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .qr-card {
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
            min-height: 232px;
            overflow: hidden;
        }

        .qr-card-head {
            display: flex;
            align-items: center;
            gap: 9px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 8px;
            min-height: 30px;
        }

        .qr-card-logo {
            max-height: 26px;
            max-width: 92px;
            object-fit: contain;
        }

        .qr-card-company {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .qr-card-body {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }

        .qr-card-qr {
            flex: 0 0 124px;
            width: 124px;
            height: 124px;
            padding: 6px;
            border: 0;
            border-radius: 8px;
            background: #fff;
        }

        .qr-card-qr svg { width: 100%; height: 100%; display: block; }

        .qr-empty {
            width: 100%;
            height: 100%;
            display: grid;
            place-items: center;
            font-size: 10px;
            color: var(--muted);
            text-align: center;
        }

        .qr-card-info { min-width: 0; }

        .qr-card-kicker {
            font-size: 9px;
            font-weight: 900;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--brand);
        }

        .qr-card-name {
            margin-top: 2px;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .qr-card-meta {
            margin-top: 2px;
            font-size: 10px;
            color: var(--muted);
        }

        .qr-card-code-label {
            margin-top: 9px;
            font-size: 8px;
            font-weight: 900;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .qr-card-code {
            margin-top: 1px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 16px;
            font-weight: 900;
            letter-spacing: .08em;
            white-space: nowrap;
        }

        .qr-card-foot {
            border-top: 1px solid var(--line);
            padding-top: 6px;
            font-size: 8px;
            line-height: 1.35;
            color: var(--muted);
            overflow-wrap: anywhere;
        }

        .info-panel {
            margin-top: 18px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 16px;
        }

        .link-box label {
            display: block;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .link-box input {
            width: 100%;
            padding: 10px 11px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            color: var(--ink);
            background: var(--soft);
        }

        .info-panel .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .info-panel .danger-zone {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid var(--line);
        }

        .hint {
            margin: 10px 0 0;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.4;
        }

        @media (max-width: 980px) {
            .filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .filters .actions { grid-column: span 3; }
        }

        @media (max-width: 760px) {
            .page { padding: 16px 12px 36px; }
            .admin-head { align-items: flex-start; }
            .view-head { align-items: flex-start; flex-direction: column; }
            .filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filters .actions { grid-column: span 2; }
            .cards-grid { grid-template-columns: 1fr; }
            .qr-card-body { gap: 10px; }
            .qr-card-qr { flex-basis: 110px; width: 110px; height: 110px; }
        }

        @media (max-width: 520px) {
            .filters { grid-template-columns: 1fr; }
            .filters .actions { grid-column: span 1; }
            .qr-card-body { flex-direction: column; align-items: flex-start; }
            .qr-card-qr { flex-basis: 120px; width: 120px; height: 120px; }
        }

        @media print {
            @page { size: A4; margin: 8mm; }
            body { background: #fff; }
            .page { max-width: none; padding: 0; margin: 0; }
            .no-print { display: none !important; }
            .cards-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 5mm;
            }
            .cards-grid.single { grid-template-columns: 1fr; }
            .cards-grid.single .qr-card {
                width: 92mm;
                margin: 12mm auto;
            }
            .single-card-wrap .qr-card {
                width: 92mm;
                margin: 12mm auto;
            }
            .single-card-wrap { padding: 0; }
            .qr-card {
                box-shadow: none;
                border: 1px solid #6b7280;
                border-radius: 0;
                break-inside: avoid;
                page-break-inside: avoid;
                min-height: 58mm;
                padding: 4mm;
                gap: 2.5mm;
            }
            .qr-card-head { padding-bottom: 2mm; min-height: 8mm; }
            .qr-card-logo { max-height: 8mm; max-width: 26mm; }
            .qr-card-company { font-size: 7pt; }
            .qr-card-body { gap: 3mm; }
            .qr-card-qr {
                flex: 0 0 32mm;
                width: 32mm;
                height: 32mm;
                padding: 1.5mm;
            }
            .qr-card-kicker { font-size: 6pt; }
            .qr-card-name { font-size: 9.5pt; }
            .qr-card-meta { font-size: 6.5pt; }
            .qr-card-code-label { font-size: 5.5pt; margin-top: 2mm; }
            .qr-card-code { font-size: 12pt; letter-spacing: .06em; }
            .qr-card-foot {
                font-size: 5.8pt;
                padding-top: 1.5mm;
                line-height: 1.3;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <header class="admin-head no-print">
        <a class="brand" href="ficha_qrcode.php">
            <?php if (!empty($logoCliente)): ?>
                <img src="<?= ficha_qrcode_h($logoCliente['url']) ?>" alt="Logo">
            <?php endif; ?>
            <span>Ficha Funcional</span>
        </a>
        <a class="btn btn-light" href="cadastro_funcionario.php">Voltar ao cadastro</a>
    </header>

    <?php if ($modo === 'individual' && !empty($funcionario)): ?>
        <div class="view-head no-print">
            <div>
                <h1>QR Code da Ficha Funcional</h1>
                <p>
                    <?= ficha_qrcode_h($funcionario['enti_tx_nome'] ?? '') ?>
                    · Matrícula: <?= ficha_qrcode_h($funcionario['enti_tx_matricula'] ?: '—') ?>
                    <?php if (!empty($funcionario['empr_tx_nome'])): ?>
                        · <?= ficha_qrcode_h($funcionario['empr_tx_nome']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="actions">
                <a class="btn btn-light" href="ficha_qrcode.php">Voltar para a lista</a>
                <button class="btn btn-primary" type="button" onclick="window.print()">Imprimir cartão</button>
            </div>
        </div>

        <?php if ($erroPagina !== ''): ?>
            <div class="alert alert-error no-print"><?= ficha_qrcode_h($erroPagina) ?></div>
        <?php endif; ?>

        <?php if ($msg === 'regenerado'): ?>
            <div class="alert alert-success no-print">Novo código gerado. O código anterior foi invalidado.</div>
        <?php elseif ($codigoNovo): ?>
            <div class="alert alert-success no-print">Código gerado com sucesso. Imprima o cartão abaixo e entregue ao funcionário.</div>
        <?php endif; ?>

        <div class="single-card-wrap">
            <?= ficha_qrcode_card_html($funcionario, strval($codigo)) ?>
        </div>

        <div class="info-panel no-print">
            <div class="link-box">
                <label for="link-ficha">Link de acesso</label>
                <input id="link-ficha" type="text" readonly value="<?= ficha_qrcode_h($urlCompleta) ?>">
            </div>

            <div class="actions">
                <button class="btn btn-light" type="button" data-copy="<?= ficha_qrcode_h($urlCompleta) ?>">Copiar link</button>
                <button class="btn btn-light" type="button" data-copy="<?= ficha_qrcode_h($codigoFormatado) ?>">Copiar código</button>
                <a class="btn btn-light" href="<?= ficha_qrcode_h($urlCompleta) ?>" target="_blank" rel="noopener">Abrir ficha pública</a>
            </div>

            <div class="danger-zone">
                <form method="post" action="ficha_qrcode.php" onsubmit="return confirm('Gerar um novo código? O cartão/QR anterior deixará de funcionar.');">
                    <input type="hidden" name="ffp_action" value="regenerar">
                    <input type="hidden" name="ffp_id" value="<?= (int) ($funcionario['enti_nb_id'] ?? 0) ?>">
                    <button class="btn btn-danger" type="submit">Gerar novo código</button>
                </form>
                <p class="hint">
                    Qualquer pessoa que tiver o código ou o QR Code consegue abrir a ficha pública.
                    Entregue o cartão apenas ao funcionário e gere um novo código se ele for perdido ou compartilhado indevidamente.
                </p>
            </div>
        </div>

    <?php elseif ($modo === 'lote'): ?>
        <div class="view-head no-print">
            <div>
                <h1>Cartões de QR Code</h1>
                <p><?= count($cards) ?> cartão<?= count($cards) === 1 ? '' : 'ões' ?> pronto<?= count($cards) === 1 ? '' : 's' ?> para impressão.</p>
            </div>
            <div class="actions">
                <a class="btn btn-light" href="ficha_qrcode.php">Voltar para a lista</a>
                <?php if (!empty($cards)): ?>
                    <button class="btn btn-primary" type="button" onclick="window.print()">Imprimir cartões</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($erroPagina !== ''): ?>
            <div class="alert alert-error no-print"><?= ficha_qrcode_h($erroPagina) ?></div>
        <?php endif; ?>

        <?php if (!empty($avisos)): ?>
            <div class="alert alert-warning no-print">
                Alguns funcionários não puderam ser incluídos:
                <ul>
                    <?php foreach ($avisos as $aviso): ?>
                        <li><?= ficha_qrcode_h($aviso) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($cards)): ?>
            <div class="table-wrap">
                <div class="empty-row">Nenhum cartão foi gerado. Volte para a lista e selecione funcionários ativos.</div>
            </div>
        <?php else: ?>
            <div class="cards-grid">
                <?php foreach ($cards as $card): ?>
                    <?= ficha_qrcode_card_html($card['funcionario'], strval($card['codigo'])) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="view-head">
            <div>
                <h1>QR Codes da Ficha Funcional</h1>
                <p>Gere o código único e imprima o cartão que será entregue ao funcionário.</p>
            </div>
        </div>

        <?php if ($erroPagina !== ''): ?>
            <div class="alert alert-error"><?= ficha_qrcode_h($erroPagina) ?></div>
        <?php endif; ?>

        <?php if (!empty($avisos)): ?>
            <div class="alert alert-warning">
                <ul>
                    <?php foreach ($avisos as $aviso): ?>
                        <li><?= ficha_qrcode_h($aviso) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="filters" method="get" action="ficha_qrcode.php">
            <div class="field">
                <label for="f_nome">Nome</label>
                <input id="f_nome" type="text" name="f_nome" value="<?= ficha_qrcode_h($f_nome) ?>" placeholder="Nome do funcionário">
            </div>
            <div class="field">
                <label for="f_matricula">Matrícula</label>
                <input id="f_matricula" type="text" name="f_matricula" value="<?= ficha_qrcode_h($f_matricula) ?>" placeholder="Matrícula">
            </div>
            <?php if ($isAdmin): ?>
                <div class="field">
                    <label for="f_empresa">Empresa</label>
                    <select id="f_empresa" name="f_empresa">
                        <option value="" <?= $f_empresa_raw === '' ? 'selected' : '' ?>>Todas</option>
                        <option value="0" <?= $f_empresa_raw === '0' ? 'selected' : '' ?>>Matriz</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?= (int) $empresa['empr_nb_id'] ?>" <?= $f_empresa_raw === (string) $empresa['empr_nb_id'] ? 'selected' : '' ?>>
                                <?= ficha_qrcode_h($empresa['empr_tx_nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="field">
                <label for="f_setor">Setor</label>
                <select id="f_setor" name="f_setor">
                    <option value="0">Todos</option>
                    <?php foreach ($setores as $setor): ?>
                        <option value="<?= (int) $setor['grup_nb_id'] ?>" <?= $f_setor === (int) $setor['grup_nb_id'] ? 'selected' : '' ?>>
                            <?= ficha_qrcode_h($setor['grup_tx_nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f_status">Status</label>
                <select id="f_status" name="f_status">
                    <?php foreach ($statusOpcoes as $valor => $rotulo): ?>
                        <option value="<?= ficha_qrcode_h($valor) ?>" <?= $f_status === $valor ? 'selected' : '' ?>>
                            <?= ficha_qrcode_h($rotulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Filtrar</button>
                <a class="btn btn-light" href="ficha_qrcode.php">Limpar</a>
            </div>
        </form>

        <form method="post" action="ficha_qrcode.php">
            <input type="hidden" name="ffp_action" value="gerar_lote">

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 38px;">
                                <input type="checkbox" id="check-all" title="Selecionar todos">
                            </th>
                            <th>Funcionário</th>
                            <th>Empresa</th>
                            <th>Setor</th>
                            <th>Status</th>
                            <th>Código</th>
                            <th style="width: 150px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($funcionarios)): ?>
                        <tr>
                            <td colspan="7" class="empty-row">Nenhum funcionário encontrado com os filtros informados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($funcionarios as $linha): ?>
                            <?php
                            $linhaAtiva = ficha_qrcode_funcionario_ativo($linha);
                            $linhaId = (int) ($linha['enti_nb_id'] ?? 0);
                            ?>
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        name="ids[]"
                                        value="<?= $linhaId ?>"
                                        <?= $linhaAtiva ? '' : 'disabled title="Funcionário inativo"' ?>
                                    >
                                </td>
                                <td>
                                    <div class="cell-main"><?= ficha_qrcode_h($linha['enti_tx_nome'] ?? '') ?></div>
                                    <div class="cell-sub">Matrícula: <?= ficha_qrcode_h($linha['enti_tx_matricula'] ?: '—') ?></div>
                                </td>
                                <td><?= ficha_qrcode_h($linha['empresa_nome'] ?? '—') ?></td>
                                <td><?= ficha_qrcode_h($linha['setor_nome'] ?? '—') ?></td>
                                <td><?= ficha_qrcode_status_funcionario($linha) ?></td>
                                <td>
                                    <?php if (!empty($linha['ffp_tx_codigo'])): ?>
                                        <code><?= ficha_qrcode_h(ficha_publica_formatar_codigo($linha['ffp_tx_codigo'])) ?></code>
                                    <?php else: ?>
                                        <span class="muted">Sem código</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($linhaAtiva): ?>
                                        <a class="btn btn-light btn-small" href="ficha_qrcode.php?ffp_id=<?= $linhaId ?>" target="_blank" rel="noopener">Ver / Imprimir</a>
                                    <?php else: ?>
                                        <span class="muted">Inativo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($funcionarios)): ?>
                <div class="table-actions">
                    <span class="table-note">
                        <?= count($funcionarios) ?> registro<?= count($funcionarios) === 1 ? '' : 's' ?> exibido<?= count($funcionarios) === 1 ? '' : 's' ?>.
                        <?php if (count($funcionarios) >= 500): ?>
                            Refine os filtros para ver os demais.
                        <?php endif; ?>
                    </span>
                    <button class="btn btn-primary" type="submit">Gerar / Imprimir QR dos selecionados</button>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<script>
(function () {
    const checkAll = document.getElementById('check-all');
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            document.querySelectorAll('input[name="ids[]"]:not(:disabled)').forEach(function (checkbox) {
                checkbox.checked = checkAll.checked;
            });
        });
    }

    function copiarFallback(texto, onDone) {
        const area = document.createElement('textarea');
        area.value = texto;
        area.setAttribute('readonly', 'readonly');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.focus();
        area.select();

        try {
            document.execCommand('copy');
            onDone();
        } catch (e) {
            // Sem suporte à cópia automática: o usuário ainda pode selecionar o link manualmente.
        }

        document.body.removeChild(area);
    }

    function copiarTexto(texto, botao) {
        const original = botao.textContent;
        const concluir = function () {
            botao.textContent = 'Copiado!';
            setTimeout(function () {
                botao.textContent = original;
            }, 1600);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(texto).then(concluir).catch(function () {
                copiarFallback(texto, concluir);
            });
        } else {
            copiarFallback(texto, concluir);
        }
    }

    document.querySelectorAll('[data-copy]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            copiarTexto(botao.getAttribute('data-copy') || '', botao);
        });
    });
})();
</script>
</body>
</html>
