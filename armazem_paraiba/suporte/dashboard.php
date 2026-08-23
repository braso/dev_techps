<?php
    /* ============================================================
       Suporte — Dashboard (apenas domínio TechPS)
       Indicadores agregados de todos os chamados: por status, tipo,
       empresa, tela/página, setor, tendência e chamados mais antigos
       ainda em aberto. Tudo via API externa (mesmo backend da gestão).
       ============================================================ */
    include __DIR__ . "/../load_env.php";
    include_once __DIR__ . "/../conecta.php";
    include_once __DIR__ . "/../check_permission.php";
    include_once __DIR__ . "/_datas.php";

    $__empresaAtual = trim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");
    // Dashboard central: mesmos domínios liberados para a Gestão de Suporte.
    if (strpos($__empresaAtual, "techps") === false && strpos($__empresaAtual, "demo") === false) {
        echo "<script>alert('Acesso restrito ao domínio TechPS.'); window.location.href='" . ($_ENV["CONTEX_PATH"] ?? "") . "/batida_ponto.php';</script>";
        exit;
    }

    $__apiUrl   = rtrim(strval($_ENV["SUPORTE_API_URL"] ?? ""), "/");
    $__adminKey = strval($_ENV["SUPORTE_ADMIN_KEY"] ?? "");

    if (!function_exists("gestao_requisitar")) {
        function gestao_requisitar(string $metodo, string $rota, array $query = [], array $post = []): array {
            global $__apiUrl, $__adminKey;
            $url = $__apiUrl . $rota;
            if (!empty($query)) {
                $url .= "?" . http_build_query($query);
            }
            $ch = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER     => ["x-api-key: " . $__adminKey],
            ];
            if ($metodo === "POST") {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = http_build_query($post);
            }
            curl_setopt_array($ch, $options);
            $resposta = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $json = json_decode((string) $resposta, true);
            return [
                "ok"    => $httpCode >= 200 && $httpCode < 300 && is_array($json) && !empty($json["ok"]),
                "http"  => $httpCode,
                "dados" => is_array($json) ? $json : [],
            ];
        }
    }

    // ── Empresas e setores para o filtro (via API) ─────────────────────
    $__resEmpresas = gestao_requisitar("GET", "/suporte/empresas");
    $__empresas = $__resEmpresas["ok"] ? ($__resEmpresas["dados"]["empresas"] ?? []) : [];

    $__resSetores = gestao_requisitar("GET", "/suporte/setores");
    $__setoresFiltro = $__resSetores["ok"] ? ($__resSetores["dados"]["setores"] ?? []) : [];

    // ── Filtros ──────────────────────────────────────────────────────
    $__fEmpresa = trim(strval($_GET["empresa"] ?? ""));
    $__fSetorId = (int) ($_GET["setor_id"] ?? 0);
    $__fStatus  = trim(strval($_GET["status"] ?? ""));
    $__fInicio  = trim(strval($_GET["data_inicio"] ?? ""));
    $__fFim     = trim(strval($_GET["data_fim"] ?? ""));

    $__statusListagem = ["aberto", "em_analise", "em_andamento", "aguardando_cliente", "resolvido", "cancelado", "reaberto", "encaminhado_ssi", "teste_interno"];

    $__queryFiltro = [];
    if ($__fEmpresa !== "") $__queryFiltro["empresa"] = $__fEmpresa;
    if ($__fSetorId > 0) $__queryFiltro["setor_id"] = $__fSetorId;
    if (in_array($__fStatus, $__statusListagem, true)) $__queryFiltro["status"] = $__fStatus;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $__fInicio)) $__queryFiltro["data_inicio"] = $__fInicio;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $__fFim)) $__queryFiltro["data_fim"] = $__fFim;

    $__res = gestao_requisitar("GET", "/suporte/dashboard", $__queryFiltro);
    $__d = $__res["ok"] ? $__res["dados"] : [];
    $__resumo = $__d["resumo"] ?? [];
    $__porStatus = $__d["por_status"] ?? [];
    $__porTipo = $__d["por_tipo"] ?? [];
    $__porPrioridade = $__d["por_prioridade"] ?? [];
    $__porEmpresa = $__d["por_empresa"] ?? [];
    $__porPagina = $__d["por_pagina"] ?? [];
    $__porSetor = $__d["por_setor"] ?? [];
    $__tendencia = $__d["tendencia"] ?? [];
    $__maisAntigos = $__d["mais_antigos_abertos"] ?? [];
    $__slaResumo = $__d["sla"] ?? [];
    $__slaDentro = (int) ($__slaResumo["dentro_prazo"] ?? 0);
    $__slaAtrasado = (int) ($__slaResumo["atrasado"] ?? 0);
    $__slaSemConfig = (int) ($__slaResumo["sem_config"] ?? 0);

    $__statusLabel = [
        "aberto"             => "Aberto",
        "em_analise"         => "Em Análise",
        "em_andamento"       => "Em Andamento",
        "aguardando_cliente" => "Aguardando cliente",
        "resolvido"          => "Concluído",
        "cancelado"          => "Cancelado",
        "reaberto"           => "Reaberto",
        "encaminhado_ssi"    => "Encaminhado a SSI",
        "teste_interno"      => "Teste Interno",
    ];
    $__tipoLabel = [
        "duvida"           => "Dúvida operacional",
        "sugestao"         => "Sugestão",
        "bug"              => "Bug de sistema",
        "nao_classificado" => "Não classificado",
    ];
    $__prioridadeLabel = [
        "baixa"   => "Baixa",
        "media"   => "Média",
        "alta"    => "Alta",
        "urgente" => "Urgente",
    ];

    $__totalGeral = (int) ($__resumo["total"] ?? 0);
    $__abertosAgora = (int) ($__resumo["abertos_agora"] ?? 0);
    $__resolvidos = (int) ($__resumo["resolvidos"] ?? 0);
    $__cancelados = (int) ($__resumo["cancelados"] ?? 0);
    $__tempoResolucao = $__resumo["tempo_medio_resolucao_horas"] ?? null;
    $__tempoAceite = $__resumo["tempo_medio_aceite_horas"] ?? null;

    function suporte_fmt_horas($horas): string {
        if ($horas === null) return "—";
        $horas = (float) $horas;
        if ($horas < 48) return number_format($horas, 1, ",", ".") . "h";
        return number_format($horas / 24, 1, ",", ".") . " dias";
    }

    cabecalho("Dashboard de Suporte");
?>

<div class="row">
    <div class="col-md-12">
        <div class="portlet light bordered">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-bar-chart font-blue"></i>
                    <span class="caption-subject bold uppercase">Dashboard de Suporte</span>
                    <span class="caption-helper">Visão geral dos chamados de todas as empresas</span>
                </div>
                <div class="actions">
                    <a href="gestao.php" class="btn btn-default btn-sm"><i class="fa fa-list"></i> Gestão de Suporte</a>
                </div>
            </div>
            <div class="portlet-body">

                <?php if (!$__res["ok"]): ?>
                    <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> Não foi possível consultar a API de suporte.</div>
                <?php endif; ?>

                <form method="get" class="form-inline" style="margin-bottom:18px;">
                    <div class="form-group" style="margin-right:10px;">
                        <label style="margin-right:5px;">Empresa</label>
                        <select name="empresa" class="form-control">
                            <option value="">Todas</option>
                            <?php foreach ($__empresas as $__e): ?>
                                <option value="<?= htmlspecialchars(strval($__e["empresa_key"] ?? "")) ?>" <?= ($__fEmpresa === strval($__e["empresa_key"] ?? "")) ? "selected" : "" ?>>
                                    <?= htmlspecialchars(strval($__e["empresa_nome"] ?? $__e["empresa_key"] ?? "")) ?> (<?= htmlspecialchars(strval($__e["empresa_key"] ?? "")) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-right:10px;">
                        <label style="margin-right:5px;">Setor</label>
                        <select name="setor_id" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($__setoresFiltro as $__s): ?>
                                <option value="<?= (int) ($__s["id"] ?? 0) ?>" <?= ($__fSetorId === (int) ($__s["id"] ?? 0)) ? "selected" : "" ?>>
                                    <?= htmlspecialchars(strval($__s["nome"] ?? "")) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-right:10px;">
                        <label style="margin-right:5px;">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($__statusLabel as $__sk => $__sl): if ($__sk === "nao_classificado") continue; ?>
                                <option value="<?= $__sk ?>" <?= ($__fStatus === $__sk) ? "selected" : "" ?>><?= htmlspecialchars($__sl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-right:10px;">
                        <label style="margin-right:5px;">De</label>
                        <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($__fInicio) ?>" />
                    </div>
                    <div class="form-group" style="margin-right:10px;">
                        <label style="margin-right:5px;">Até</label>
                        <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($__fFim) ?>" />
                    </div>
                    <button type="submit" class="btn blue"><i class="fa fa-search"></i> Filtrar</button>
                    <a href="dashboard.php" class="btn btn-default">Limpar</a>
                </form>

                <!-- KPIs -->
                <div class="row" style="margin-bottom:10px;">
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="sup-kpi">
                            <div class="sup-kpi-label"><i class="fa fa-life-ring"></i> Total de chamados</div>
                            <div class="sup-kpi-value"><?= number_format($__totalGeral, 0, ",", ".") ?></div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="sup-kpi">
                            <div class="sup-kpi-label"><i class="fa fa-clock-o"></i> Em aberto agora</div>
                            <div class="sup-kpi-value" style="color:#e67e22;"><?= number_format($__abertosAgora, 0, ",", ".") ?></div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="sup-kpi">
                            <div class="sup-kpi-label"><i class="fa fa-check-circle"></i> Concluídos</div>
                            <div class="sup-kpi-value" style="color:#27ae60;"><?= number_format($__resolvidos, 0, ",", ".") ?></div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="sup-kpi">
                            <div class="sup-kpi-label"><i class="fa fa-times-circle"></i> Cancelados</div>
                            <div class="sup-kpi-value" style="color:#7f8c8d;"><?= number_format($__cancelados, 0, ",", ".") ?></div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="sup-kpi">
                            <div class="sup-kpi-label"><i class="fa fa-hourglass-half"></i> Tempo médio p/ aceite</div>
                            <div class="sup-kpi-value" style="font-size:20px;"><?= htmlspecialchars(suporte_fmt_horas($__tempoAceite)) ?></div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="sup-kpi">
                            <div class="sup-kpi-label"><i class="fa fa-flag-checkered"></i> Tempo médio de resolução</div>
                            <div class="sup-kpi-value" style="font-size:20px;"><?= htmlspecialchars(suporte_fmt_horas($__tempoResolucao)) ?></div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="sup-kpi">
                            <div class="sup-kpi-label"><i class="fa fa-check"></i> SLA dentro do prazo</div>
                            <div class="sup-kpi-value" style="color:#27ae60;"><?= number_format($__slaDentro, 0, ",", ".") ?></div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="sup-kpi">
                            <div class="sup-kpi-label"><i class="fa fa-exclamation-triangle"></i> SLA atrasado</div>
                            <div class="sup-kpi-value" style="color:#e74c3c;"><?= number_format($__slaAtrasado, 0, ",", ".") ?></div>
                            <?php if ($__slaSemConfig > 0): ?><div style="font-size:11px;color:#999;margin-top:2px;"><?= $__slaSemConfig ?> sem SLA configurado</div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tendência (linha inteira) -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="sup-panel">
                            <h4><i class="fa fa-line-chart"></i> Tendência de chamados abertos<?php if (!empty($__tendencia) && isset($__tendencia[0]["periodo"]) && strlen($__tendencia[0]["periodo"]) === 7): ?> <small class="text-muted">(agrupado por mês — período extenso)</small><?php endif; ?></h4>
                            <div class="sup-chart-wrap" style="height:230px;"><canvas id="chartTendencia"></canvas></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="sup-panel">
                            <h4><i class="fa fa-building"></i> Empresas que mais abriram chamados</h4>
                            <div class="sup-chart-wrap" style="height:<?= max(220, count($__porEmpresa) * 30) ?>px;"><canvas id="chartEmpresas"></canvas></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="sup-panel">
                            <h4><i class="fa fa-desktop"></i> Telas com mais chamados</h4>
                            <div class="sup-chart-wrap" style="height:<?= max(220, count($__porPagina) * 30) ?>px;"><canvas id="chartPaginas"></canvas></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="sup-panel">
                            <h4><i class="fa fa-pie-chart"></i> Chamados por status</h4>
                            <div class="sup-chart-wrap"><canvas id="chartStatus"></canvas></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="sup-panel">
                            <h4><i class="fa fa-tag"></i> Chamados por tipo</h4>
                            <div class="sup-chart-wrap"><canvas id="chartTipo"></canvas></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="sup-panel">
                            <h4><i class="fa fa-flag"></i> Chamados por prioridade</h4>
                            <div class="sup-chart-wrap"><canvas id="chartPrioridade"></canvas></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="sup-panel">
                            <h4><i class="fa fa-sitemap"></i> Chamados por setor</h4>
                            <div class="sup-chart-wrap"><canvas id="chartSetor"></canvas></div>
                        </div>
                    </div>
                </div>

                <!-- Chamados mais antigos ainda em aberto -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="sup-panel">
                            <h4><i class="fa fa-exclamation-triangle"></i> Chamados em aberto há mais tempo</h4>
                            <?php if (empty($__maisAntigos)): ?>
                                <p class="text-muted"><i class="fa fa-check"></i> Nenhum chamado em aberto no recorte selecionado.</p>
                            <?php else: ?>
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="width:70px;">Nº</th>
                                            <th>Empresa</th>
                                            <th style="width:150px;">Status</th>
                                            <th style="width:150px;">Aberto em</th>
                                            <th style="width:110px;">Dias em aberto</th>
                                            <th style="width:90px;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($__maisAntigos as $__a): ?>
                                            <tr>
                                                <td>#<?= (int) ($__a["id"] ?? 0) ?></td>
                                                <td><?= htmlspecialchars(strval($__a["empresa_nome"] ?? $__a["empresa_key"] ?? "")) ?></td>
                                                <td><?= htmlspecialchars($__statusLabel[strval($__a["status"] ?? "")] ?? strval($__a["status"] ?? "")) ?></td>
                                                <td><?= htmlspecialchars(suporte_fmt_data(strval($__a["created_at"] ?? ""))) ?></td>
                                                <td><span class="label <?= ((int) ($__a["dias_aberto"] ?? 0)) >= 7 ? "label-danger" : "label-warning" ?>"><?= (int) ($__a["dias_aberto"] ?? 0) ?> dia(s)</span></td>
                                                <td><a href="gestao.php?id=<?= (int) ($__a["id"] ?? 0) ?>" class="btn btn-xs blue"><i class="fa fa-cog"></i> Gerir</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<style>
    .sup-kpi{ background:#fff; border:1px solid #e5e9ee; border-radius:6px; padding:12px 14px; margin-bottom:15px; min-height:78px; }
    .sup-kpi-label{ font-size:12px; color:#8a94a3; font-weight:600; text-transform:uppercase; letter-spacing:.02em; margin-bottom:6px; }
    .sup-kpi-value{ font-size:26px; font-weight:700; color:#2c3e50; line-height:1; }
    .sup-panel{ background:#fff; border:1px solid #e5e9ee; border-radius:6px; padding:16px 18px; margin-bottom:20px; }
    .sup-panel h4{ margin-top:0; margin-bottom:14px; }
    .sup-chart-wrap{ position:relative; height:260px; width:100%; }
    .sup-chart-wrap canvas{ max-width:100% !important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    var TENDENCIA = <?= json_encode($__tendencia, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var POR_EMPRESA = <?= json_encode($__porEmpresa, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var POR_PAGINA = <?= json_encode($__porPagina, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var POR_STATUS = <?= json_encode($__porStatus, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var POR_TIPO = <?= json_encode($__porTipo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var POR_PRIORIDADE = <?= json_encode($__porPrioridade, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var POR_SETOR = <?= json_encode($__porSetor, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var STATUS_LABEL = <?= json_encode($__statusLabel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var TIPO_LABEL = <?= json_encode($__tipoLabel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var PRIORIDADE_LABEL = <?= json_encode($__prioridadeLabel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    var PALETA = ["#4e73df", "#1cc88a", "#f6c23e", "#e74a3b", "#36b9cc", "#8e44ad", "#e67e22", "#7f8c8d", "#16a085", "#2c3e50"];

    Chart.defaults.font.family = "'Segoe UI', Roboto, Arial, sans-serif";
    Chart.defaults.color = "#5b6672";

    function corPorStatus(status) {
        var mapa = {
            aberto: "#f6c23e", em_analise: "#8e44ad", em_andamento: "#36b9cc",
            aguardando_cliente: "#4e73df", resolvido: "#1cc88a", cancelado: "#7f8c8d",
            reaberto: "#f6c23e", encaminhado_ssi: "#e74a3b", teste_interno: "#16a085"
        };
        return mapa[status] || "#95a5a6";
    }

    // Tendência
    var elTendencia = document.getElementById("chartTendencia");
    if (elTendencia && TENDENCIA.length) {
        new Chart(elTendencia, {
            type: "line",
            data: {
                labels: TENDENCIA.map(function (t) { return t.periodo; }),
                datasets: [{
                    label: "Chamados abertos",
                    data: TENDENCIA.map(function (t) { return t.total; }),
                    borderColor: "#4e73df",
                    backgroundColor: "rgba(78,115,223,0.12)",
                    fill: true,
                    tension: 0.25,
                    pointRadius: 2
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    } else if (elTendencia) {
        elTendencia.parentElement.innerHTML = '<p class="text-muted">Sem dados no recorte selecionado.</p>';
    }

    // Empresas (barra horizontal)
    var elEmpresas = document.getElementById("chartEmpresas");
    if (elEmpresas && POR_EMPRESA.length) {
        new Chart(elEmpresas, {
            type: "bar",
            data: {
                labels: POR_EMPRESA.map(function (e) { return e.empresa_nome || e.empresa_key; }),
                datasets: [{ label: "Chamados", data: POR_EMPRESA.map(function (e) { return e.total; }), backgroundColor: "#4e73df" }]
            },
            options: {
                indexAxis: "y", responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    } else if (elEmpresas) {
        elEmpresas.parentElement.innerHTML = '<p class="text-muted">Sem dados no recorte selecionado.</p>';
    }

    // Telas/páginas (barra horizontal)
    var elPaginas = document.getElementById("chartPaginas");
    if (elPaginas && POR_PAGINA.length) {
        new Chart(elPaginas, {
            type: "bar",
            data: {
                labels: POR_PAGINA.map(function (p) { return p.pagina; }),
                datasets: [{ label: "Chamados", data: POR_PAGINA.map(function (p) { return p.total; }), backgroundColor: "#e67e22" }]
            },
            options: {
                indexAxis: "y", responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    } else if (elPaginas) {
        elPaginas.parentElement.innerHTML = '<p class="text-muted">Sem dados no recorte selecionado.</p>';
    }

    // Status (doughnut)
    var elStatus = document.getElementById("chartStatus");
    if (elStatus && POR_STATUS.length) {
        new Chart(elStatus, {
            type: "doughnut",
            data: {
                labels: POR_STATUS.map(function (s) { return STATUS_LABEL[s.status] || s.status; }),
                datasets: [{ data: POR_STATUS.map(function (s) { return s.total; }), backgroundColor: POR_STATUS.map(function (s) { return corPorStatus(s.status); }) }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom", labels: { boxWidth: 12, font: { size: 11 } } } } }
        });
    } else if (elStatus) {
        elStatus.parentElement.innerHTML = '<p class="text-muted">Sem dados no recorte selecionado.</p>';
    }

    // Tipo (pizza)
    var elTipo = document.getElementById("chartTipo");
    if (elTipo && POR_TIPO.length) {
        new Chart(elTipo, {
            type: "pie",
            data: {
                labels: POR_TIPO.map(function (t) { return TIPO_LABEL[t.tipo] || t.tipo; }),
                datasets: [{ data: POR_TIPO.map(function (t) { return t.total; }), backgroundColor: PALETA }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom", labels: { boxWidth: 12, font: { size: 11 } } } } }
        });
    } else if (elTipo) {
        elTipo.parentElement.innerHTML = '<p class="text-muted">Sem dados no recorte selecionado.</p>';
    }

    // Prioridade (doughnut)
    var elPrioridade = document.getElementById("chartPrioridade");
    if (elPrioridade && POR_PRIORIDADE.length) {
        var corPrioridade = { baixa: "#95a5a6", media: "#36b9cc", alta: "#f6c23e", urgente: "#e74a3b" };
        new Chart(elPrioridade, {
            type: "doughnut",
            data: {
                labels: POR_PRIORIDADE.map(function (p) { return PRIORIDADE_LABEL[p.prioridade] || p.prioridade; }),
                datasets: [{ data: POR_PRIORIDADE.map(function (p) { return p.total; }), backgroundColor: POR_PRIORIDADE.map(function (p) { return corPrioridade[p.prioridade] || "#95a5a6"; }) }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom", labels: { boxWidth: 12, font: { size: 11 } } } } }
        });
    } else if (elPrioridade) {
        elPrioridade.parentElement.innerHTML = '<p class="text-muted">Sem dados no recorte selecionado.</p>';
    }

    // Setor (barra vertical)
    var elSetor = document.getElementById("chartSetor");
    if (elSetor && POR_SETOR.length) {
        new Chart(elSetor, {
            type: "bar",
            data: {
                labels: POR_SETOR.map(function (s) { return s.setor; }),
                datasets: [{ label: "Chamados", data: POR_SETOR.map(function (s) { return s.total; }), backgroundColor: "#1cc88a" }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    } else if (elSetor) {
        elSetor.parentElement.innerHTML = '<p class="text-muted">Sem dados no recorte selecionado.</p>';
    }
})();
</script>

<?php rodape(); ?>
