<?php
/*
 * Ficha Funcional Pública
 *
 * Página sem login. O acesso é feito pelo código único do funcionário,
 * presente no cartão/QR Code entregue pela empresa.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/ficha_publica_funcoes.php';

ficha_publica_bootstrap();

$conn = ficha_publica_db();
$codigoDigitado = strval($_GET['c'] ?? '');
$codigo = ficha_publica_normalizar_codigo($codigoDigitado);
$ficha = null;
$erro = '';
$erroServico = false;
$statusHttp = 200;

if ($conn === null) {
    $erroServico = true;
    $statusHttp = 503;
} elseif (!ficha_publica_ensure_table($conn)) {
    $erroServico = true;
    $statusHttp = 503;
} elseif ($codigo !== '') {
    if (strlen($codigo) !== 10) {
        $erro = 'Código inválido. Confira o código informado e tente novamente.';
        $statusHttp = 404;
    } else {
        $ficha = ficha_publica_buscar_por_codigo($conn, $codigo);
        if (empty($ficha)) {
            $erro = 'Código não encontrado. Confira o código informado e tente novamente.';
            $statusHttp = 404;
        }
    }
}

if ($statusHttp !== 200) {
    http_response_code($statusHttp);
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$epis = [];
$treinamentos = [];
$foto = null;
$logo = null;

$logoCliente = ficha_publica_resolver_arquivo('imagens/logo_topo_cliente.png');

if (!empty($ficha)) {
    $entidadeId = (int) $ficha['enti_nb_id'];

    $foto = ficha_publica_resolver_arquivo($ficha['enti_tx_foto'] ?? '');
    $logo = ficha_publica_resolver_arquivo($ficha['empr_tx_logo'] ?? '');

    $epis = ficha_publica_listar_epis($conn, $entidadeId);
    foreach ($epis as $indice => $epi) {
        $vidaUtil = (int) ($epi['ss_e_nb_vida_util'] ?? 0);
        $dataEntrega = strval($epi['ss_e_tx_data_entrega'] ?? '');
        $vencimento = strval($epi['ss_e_tx_vencimento'] ?? '');

        // Sem vida útil e sem validade de lote, o vencimento gravado é a própria
        // data de entrega; nesse caso não há controle de troca por tempo.
        $temControle = ($vidaUtil > 0) || ($vencimento !== '' && $vencimento > $dataEntrega);

        $status = ficha_publica_status_validade($vencimento, $temControle);
        $epis[$indice]['_status'] = $status;
        $epis[$indice]['_tem_controle'] = $temControle;
    }

    $treinamentos = ficha_publica_listar_treinamentos($conn, $entidadeId);
    foreach ($treinamentos as $indice => $treinamento) {
        $diasValidade = (int) ($treinamento['trei_nb_dias_validade'] ?? 0);
        $vencimento = ficha_publica_somar_dias($treinamento['data_conclusao'] ?? '', $diasValidade);
        $status = ficha_publica_status_validade($vencimento, $diasValidade > 0);

        $treinamentos[$indice]['_vencimento'] = $vencimento;
        $treinamentos[$indice]['_status'] = $status;
    }
}

function ficha_publica_badge(array $status): string
{
    $codigo = preg_replace('/[^a-z_]/', '', strval($status['codigo'] ?? 'sem_validade'));
    $label = strval($status['label'] ?? 'Sem validade');
    $detalhe = ficha_publica_status_detalhe($status);

    $texto = $label;
    if ($detalhe !== '') {
        $texto .= ' · ' . $detalhe;
    }

    return '<span class="badge badge-' . ficha_publica_h($codigo) . '">' . ficha_publica_h($texto) . '</span>';
}

function ficha_publica_tipo_treinamento(array $treinamento): string
{
    $tipo = strtolower(trim(strval($treinamento['trei_tx_tipo'] ?? '')));
    $periodicidade = strtolower(trim(strval($treinamento['trei_tx_tipo_treinamento'] ?? '')));

    $tipoLabel = $tipo === 'dss' ? 'DSS' : 'Treinamento';
    $periodicidadeLabel = [
        'inicial' => 'Inicial',
        'periodico' => 'Periódico',
        'eventual' => 'Eventual',
    ][$periodicidade] ?? '';

    return $periodicidadeLabel !== '' ? $tipoLabel . ' · ' . $periodicidadeLabel : $tipoLabel;
}

$codigoExibicao = $codigo !== '' ? ficha_publica_formatar_codigo($codigo) : '';
$codigoVerificacao = '';
if ($codigoExibicao !== '') {
    $partesCodigo = explode('-', $codigoExibicao);
    $codigoVerificacao = '•••••-' . ($partesCodigo[1] ?? '');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#1f4e79">
    <title>Ficha Funcional</title>
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
            --yellow: #92400e;
            --yellow-bg: #fef3c7;
            --orange: #9a3412;
            --orange-bg: #ffedd5;
            --red: #991b1b;
            --red-bg: #fee2e2;
            --gray-bg: #e2e8f0;
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
            max-width: 980px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }

        .topbar {
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
            font-weight: 700;
            letter-spacing: .01em;
        }

        .brand img {
            max-height: 36px;
            max-width: 150px;
            object-fit: contain;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
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
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s ease, border-color .15s ease;
        }

        .btn-primary {
            background: var(--brand);
            color: #fff;
        }

        .btn-primary:hover { background: var(--brand-dark); }

        .btn-light {
            background: #fff;
            color: var(--brand);
            border-color: #cbd5e1;
        }

        .btn-light:hover { border-color: var(--brand); }

        .btn-block {
            width: 100%;
            margin-top: 14px;
            padding: 13px 16px;
            font-size: 15px;
        }

        .document {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 12px 34px rgba(15, 23, 42, .07);
        }

        .doc-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 22px;
            border-bottom: 2px solid var(--line);
        }

        .company {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .company-logo {
            max-width: 150px;
            max-height: 52px;
            object-fit: contain;
        }

        .company-name {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            max-width: 420px;
        }

        .doc-title {
            margin: 4px 0 0;
            font-size: 26px;
            line-height: 1.15;
            color: var(--brand);
            letter-spacing: -.02em;
        }

        .employee-head {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .avatar {
            width: 76px;
            height: 76px;
            flex: 0 0 76px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--gray-bg);
            color: #475569;
            display: grid;
            place-items: center;
            font-size: 25px;
            font-weight: 800;
            border: 3px solid #fff;
            box-shadow: 0 0 0 1px var(--line);
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .employee-name {
            margin: 0;
            font-size: 20px;
            line-height: 1.2;
        }

        .employee-meta {
            margin-top: 4px;
            font-size: 13px;
            color: var(--muted);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin: 20px 0 0;
        }

        .info-item {
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 11px;
            padding: 10px 12px;
            min-width: 0;
        }

        .info-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--muted);
        }

        .info-value {
            margin-top: 2px;
            font-size: 14px;
            font-weight: 700;
            color: var(--ink);
            overflow-wrap: anywhere;
        }

        .section {
            margin-top: 28px;
        }

        .section-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .section-head h2 {
            margin: 0;
            font-size: 18px;
        }

        .section-head span {
            font-size: 12px;
            color: var(--muted);
        }

        .item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            border: 1px solid var(--line);
            border-radius: 13px;
            padding: 14px 16px;
            margin-bottom: 10px;
            background: #fff;
        }

        .item-main { min-width: 0; }

        .item-title {
            font-size: 14px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .item-sub {
            margin-top: 3px;
            font-size: 12px;
            color: var(--muted);
            overflow-wrap: anywhere;
        }

        .item-meta {
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            font-size: 12px;
            color: #475569;
            text-align: right;
        }

        .item-meta strong {
            color: var(--ink);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .badge-em_dia { background: var(--green-bg); color: var(--green); }
        .badge-a_vencer { background: var(--yellow-bg); color: var(--yellow); }
        .badge-vence_hoje { background: var(--orange-bg); color: var(--orange); }
        .badge-vencido { background: var(--red-bg); color: var(--red); }
        .badge-sem_validade { background: var(--gray-bg); color: #475569; }

        .empty {
            border: 1px dashed #cbd5e1;
            border-radius: 13px;
            padding: 22px 16px;
            text-align: center;
            font-size: 13px;
            color: var(--muted);
            background: var(--soft);
        }

        .doc-foot {
            margin-top: 30px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            font-size: 11px;
            color: var(--muted);
        }

        .doc-foot strong { color: #475569; }

        .code-chip {
            display: inline-block;
            margin-left: 4px;
            padding: 2px 7px;
            border-radius: 6px;
            background: var(--soft);
            border: 1px solid var(--line);
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 11px;
            letter-spacing: .06em;
            color: var(--ink);
        }

        .lookup-wrap {
            min-height: 62vh;
            display: grid;
            place-items: center;
        }

        .lookup-card {
            width: 100%;
            max-width: 450px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 34px 30px 28px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, .09);
            text-align: center;
        }

        .lookup-icon {
            width: 62px;
            height: 62px;
            margin: 0 auto;
            border-radius: 18px;
            background: #eaf2f9;
            color: var(--brand);
            display: grid;
            place-items: center;
        }

        .lookup-card h1 {
            margin: 18px 0 6px;
            font-size: 23px;
            letter-spacing: -.02em;
        }

        .lookup-card p {
            margin: 0;
            font-size: 14px;
            color: var(--muted);
        }

        .lookup-form {
            margin-top: 22px;
            text-align: left;
        }

        .lookup-form label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }

        .lookup-form input {
            width: 100%;
            padding: 13px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 11px;
            background: #fff;
            color: var(--ink);
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 19px;
            letter-spacing: .12em;
            text-align: center;
            text-transform: uppercase;
        }

        .lookup-form input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(31, 78, 121, .13);
        }

        .alert {
            margin: 16px 0 0;
            padding: 11px 13px;
            border-radius: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--red);
            font-size: 13px;
            text-align: left;
        }

        .hint {
            margin-top: 16px;
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.4;
        }

        .site-foot {
            margin-top: 24px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }

        @media (max-width: 720px) {
            .page { padding: 16px 12px 36px; }
            .document { padding: 20px 16px; border-radius: 14px; }
            .doc-head { flex-direction: column; gap: 18px; }
            .doc-title { font-size: 22px; }
            .employee-head { width: 100%; }
            .topbar { align-items: flex-start; }
            .topbar-actions { justify-content: flex-end; }
            .item { flex-direction: column; gap: 10px; }
            .item-meta { align-items: flex-start; text-align: left; }
            .doc-foot { flex-direction: column; }
        }

        @media print {
            @page { size: A4; margin: 12mm; }
            body { background: #fff; }
            .page { max-width: none; padding: 0; }
            .no-print { display: none !important; }
            .document {
                border: 0;
                box-shadow: none;
                border-radius: 0;
                padding: 0;
            }
            .doc-head { border-bottom: 1px solid #94a3b8; }
            .info-item, .item { break-inside: avoid; }
            .section { break-inside: avoid; }
            .badge {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .doc-foot {
                position: static;
                margin-top: 18px;
                font-size: 9px;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <header class="topbar no-print">
        <a class="brand" href="ficha_publica.php">
            <?php if (!empty($logoCliente)): ?>
                <img src="<?= ficha_publica_h($logoCliente['url']) ?>" alt="Logo">
            <?php endif; ?>
            <span>Ficha Funcional</span>
        </a>

        <?php if (!empty($ficha)): ?>
            <div class="topbar-actions">
                <a class="btn btn-light" href="ficha_publica.php">Nova consulta</a>
                <button class="btn btn-primary" type="button" onclick="window.print()">Imprimir</button>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!empty($ficha)): ?>
        <main class="document">
            <section class="doc-head">
                <div class="company">
                    <?php if (!empty($logo)): ?>
                        <img class="company-logo" src="<?= ficha_publica_h($logo['url']) ?>" alt="Logo da empresa">
                    <?php elseif (!empty($logoCliente)): ?>
                        <img class="company-logo" src="<?= ficha_publica_h($logoCliente['url']) ?>" alt="Logo">
                    <?php endif; ?>
                    <div>
                        <div class="company-name"><?= ficha_publica_h($ficha['empr_tx_nome'] ?: 'Empresa') ?></div>
                        <h1 class="doc-title">Ficha Funcional</h1>
                    </div>
                </div>

                <div class="employee-head">
                    <div class="avatar">
                        <?php if (!empty($foto)): ?>
                            <img src="<?= ficha_publica_h($foto['url']) ?>" alt="Foto do funcionário">
                        <?php else: ?>
                            <?= ficha_publica_h(ficha_publica_iniciais(strval($ficha['enti_tx_nome'] ?? ''))) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 class="employee-name"><?= ficha_publica_h($ficha['enti_tx_nome'] ?? '') ?></h2>
                        <div class="employee-meta">
                            Matrícula: <strong><?= ficha_publica_h($ficha['enti_tx_matricula'] ?: '—') ?></strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="info-grid">
                <?php
                $cpfMascarado = ficha_publica_mascarar_cpf($ficha['enti_tx_cpf'] ?? '');
                $cargo = trim(strval($ficha['cargo_nome'] ?? ''));
                if ($cargo === '') {
                    $cargo = trim(strval($ficha['enti_tx_ocupacao'] ?? ''));
                }
                $setor = trim(strval($ficha['setor_nome'] ?? ''));
                $situacao = strval($ficha['enti_tx_status'] ?? '');
                ?>
                <?php if ($cpfMascarado !== ''): ?>
                    <div class="info-item">
                        <div class="info-label">CPF</div>
                        <div class="info-value"><?= ficha_publica_h($cpfMascarado) ?></div>
                    </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="info-label">Cargo / Função</div>
                    <div class="info-value"><?= ficha_publica_h($cargo !== '' ? $cargo : '—') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Setor</div>
                    <div class="info-value"><?= ficha_publica_h($setor !== '' ? $setor : '—') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Admissão</div>
                    <div class="info-value"><?= ficha_publica_h(ficha_publica_data($ficha['enti_tx_admissao'] ?? '') ?: '—') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Situação</div>
                    <div class="info-value"><?= ficha_publica_h($situacao !== '' ? ucfirst($situacao) : '—') ?></div>
                </div>
            </section>

            <section class="section">
                <div class="section-head">
                    <h2>EPIs entregues</h2>
                    <span><?= count($epis) ?> registro<?= count($epis) === 1 ? '' : 's' ?></span>
                </div>

                <?php if (empty($epis)): ?>
                    <div class="empty">Nenhum EPI registrado para este funcionário.</div>
                <?php else: ?>
                    <?php foreach ($epis as $epi): ?>
                        <?php
                        $grupo = trim(strval($epi['ss_e_tx_grupo'] ?? ''));
                        $subgrupo = trim(strval($epi['ss_e_tx_subgrupo'] ?? ''));
                        $item = trim(strval($epi['ss_e_tx_item'] ?? ''));
                        $variacao = trim(strval($epi['ss_e_tx_variacao'] ?? ''));

                        $nomeEpi = $subgrupo !== '' ? $subgrupo : ($item !== '' ? $item : $grupo);
                        if ($variacao !== '') {
                            $nomeEpi .= ' — ' . $variacao;
                        }

                        $detalhes = [];
                        if ($grupo !== '' && $grupo !== $nomeEpi) {
                            $detalhes[] = $grupo;
                        }
                        if ($item !== '' && $item !== $nomeEpi && $subgrupo !== '') {
                            $detalhes[] = $item;
                        }
                        if (!empty($epi['ss_e_tx_ca'])) {
                            $detalhes[] = 'CA: ' . $epi['ss_e_tx_ca'];
                        }
                        $detalhes[] = 'Qtd: ' . (int) ($epi['ss_e_nb_quantidade'] ?? 0);

                        $entrega = ficha_publica_data($epi['ss_e_tx_data_entrega'] ?? '');
                        $vencimento = ficha_publica_data($epi['_status']['data'] ?? '');
                        ?>
                        <article class="item">
                            <div class="item-main">
                                <div class="item-title"><?= ficha_publica_h($nomeEpi !== '' ? $nomeEpi : 'EPI') ?></div>
                                <div class="item-sub"><?= ficha_publica_h(implode(' · ', array_filter($detalhes))) ?></div>
                            </div>
                            <div class="item-meta">
                                <span>Entrega: <strong><?= ficha_publica_h($entrega !== '' ? $entrega : '—') ?></strong></span>
                                <span>Vencimento: <strong><?= ficha_publica_h($vencimento !== '' ? $vencimento : '—') ?></strong></span>
                                <?= ficha_publica_badge($epi['_status']) ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="section">
                <div class="section-head">
                    <h2>Treinamentos realizados</h2>
                    <span><?= count($treinamentos) ?> registro<?= count($treinamentos) === 1 ? '' : 's' ?></span>
                </div>

                <?php if (empty($treinamentos)): ?>
                    <div class="empty">Nenhum treinamento concluído registrado para este funcionário.</div>
                <?php else: ?>
                    <?php foreach ($treinamentos as $treinamento): ?>
                        <?php
                        $titulo = trim(strval($treinamento['trei_tx_titulo'] ?? ''));
                        $tipoLabel = ficha_publica_tipo_treinamento($treinamento);
                        $carga = ficha_publica_carga_horaria($treinamento['trei_nb_carga_horaria'] ?? 0);
                        $conclusao = ficha_publica_data($treinamento['data_conclusao'] ?? '');
                        $vencimento = ficha_publica_data($treinamento['_vencimento'] ?? '');

                        $detalhes = [$tipoLabel];
                        if ($carga !== '') {
                            $detalhes[] = 'Carga horária: ' . $carga;
                        }
                        ?>
                        <article class="item">
                            <div class="item-main">
                                <div class="item-title"><?= ficha_publica_h($titulo !== '' ? $titulo : 'Treinamento') ?></div>
                                <div class="item-sub"><?= ficha_publica_h(implode(' · ', array_filter($detalhes))) ?></div>
                            </div>
                            <div class="item-meta">
                                <span>Conclusão: <strong><?= ficha_publica_h($conclusao !== '' ? $conclusao : '—') ?></strong></span>
                                <span>Validade: <strong><?= ficha_publica_h($vencimento !== '' ? $vencimento : '—') ?></strong></span>
                                <?= ficha_publica_badge($treinamento['_status']) ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <footer class="doc-foot">
                <div>
                    <div>Emitido em <strong><?= ficha_publica_h(date('d/m/Y H:i')) ?></strong></div>
                    <?php if ($codigoVerificacao !== ''): ?>
                        <div>Código de verificação: <span class="code-chip"><?= ficha_publica_h($codigoVerificacao) ?></span></div>
                    <?php endif; ?>
                </div>
                <div style="max-width: 480px;">
                    Documento de uso pessoal. Contém dados pessoais e deve ser utilizado apenas para a finalidade solicitada.
                    O status é calculado com base nas datas de entrega/validade e conclusão/validade cadastradas no sistema.
                </div>
            </footer>
        </main>
    <?php else: ?>
        <main class="lookup-wrap">
            <div class="lookup-card">
                <div class="lookup-icon">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="8" y1="13" x2="16" y2="13"></line>
                        <line x1="8" y1="17" x2="13" y2="17"></line>
                    </svg>
                </div>

                <h1>Consultar Ficha Funcional</h1>
                <p>Digite o código informado no cartão com QR Code do funcionário.</p>

                <?php if ($erroServico): ?>
                    <div class="alert">Serviço temporariamente indisponível. Tente novamente em alguns instantes.</div>
                <?php else: ?>
                    <?php if ($erro !== ''): ?>
                        <div class="alert"><?= ficha_publica_h($erro) ?></div>
                    <?php endif; ?>

                    <form class="lookup-form" method="get" action="ficha_publica.php" autocomplete="off">
                        <label for="codigo">Código de acesso</label>
                        <input
                            type="text"
                            id="codigo"
                            name="c"
                            value="<?= ficha_publica_h($codigoExibicao) ?>"
                            placeholder="XXXXX-XXXXX"
                            maxlength="11"
                            inputmode="text"
                            spellcheck="false"
                            autofocus
                        >
                        <button class="btn btn-primary btn-block" type="submit">Consultar ficha</button>
                    </form>

                    <p class="hint">
                        O código é único e pessoal. Não compartilhe com terceiros.<br>
                        Se você é o funcionário e não recebeu seu código, procure o RH.
                    </p>
                <?php endif; ?>
            </div>
        </main>
    <?php endif; ?>

    <footer class="site-foot no-print">
        Ficha Funcional · Consulta pública protegida por código individual.
    </footer>
</div>
</body>
</html>
