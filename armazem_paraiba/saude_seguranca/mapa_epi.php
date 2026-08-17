<?php
ob_start();
/* Modo debug
    ini_set("display_errors", 1);
    error_reporting(E_ALL);
//*/

include "conecta.php";

function index() {
    cabecalho("Mapa de EPIs da Empresa");

    $temFiliais = ss_tem_filiais_cadastradas();
    $user_empresa = !empty($_SESSION["user_nb_empresa"]) ? (int)$_SESSION["user_nb_empresa"] : 0;

    $empresaOptions = ["" => "Todas as Empresas"];
    $sqlEmpresas = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
    if ($sqlEmpresas) {
        while ($rowEmp = mysqli_fetch_assoc($sqlEmpresas)) {
            $empresaOptions[$rowEmp["empr_nb_id"]] = $rowEmp["empr_tx_nome"];
        }
    }

    $empresaFiltro = "";
    if ($temFiliais && !empty($_POST["busca_filial"])) {
        $empresaFiltro = " AND (ss_e_nb_empresa_id = " . (int)$_POST["busca_filial"] . " OR ss_e_nb_empresa_id IS NULL OR ss_e_nb_empresa_id = 0)";
    } elseif (!$temFiliais && $user_empresa > 0) {
        $empresaFiltro = " AND (ss_e_nb_empresa_id = {$user_empresa} OR ss_e_nb_empresa_id IS NULL OR ss_e_nb_empresa_id = 0)";
    }

    $fields = [];
    if ($temFiliais) {
        $fields[] = combo("Empresa", "busca_filial", $_POST["busca_filial"] ?? "", 3, $empresaOptions);
    }
    $fields[] = campo("Buscar EPI (nome ou CA)", "busca_epi_like", $_POST["busca_epi_like"] ?? "", 4);
    $fields[] = botao("Filtrar", "index");

    echo abre_form("Filtros do Mapa de EPIs");
    echo linha_form($fields);
    echo fecha_form([]);

    // ==================== CONSULTAS ====================

    // 1) Resumo por item de EPI (estoque, em uso, inspeção, descarte, perdido)
    $sqlMapa = query("
        SELECT epi.ss_e_nb_id, epi.ss_e_tx_grupo, epi.ss_e_tx_subgrupo, epi.ss_e_tx_item, epi.ss_e_tx_ca, epi.ss_e_tx_variacoes,
               IFNULL(est.saldo, 0) AS saldo_estoque,
               IFNULL(uso.qtd_uso, 0) AS qtd_uso,
               IFNULL(uso.colabs_uso, 0) AS colabs_uso,
               IFNULL(insc.qtd_inspecao, 0) AS qtd_inspecao,
               IFNULL(desc.qtd_descarte, 0) AS qtd_descarte,
               IFNULL(perd.qtd_perdido, 0) AS qtd_perdido
        FROM ss_epi epi
        LEFT JOIN (
            SELECT ss_e_nb_epi_id, SUM(CASE WHEN ss_e_tx_tipo = 'entrada' THEN ss_e_nb_quantidade ELSE -ss_e_nb_quantidade END) AS saldo
            FROM ss_epi_estoque
            WHERE 1 {$empresaFiltro}
            GROUP BY ss_e_nb_epi_id
        ) est ON est.ss_e_nb_epi_id = epi.ss_e_nb_id
        LEFT JOIN (
            SELECT ent.ss_e_nb_epi_id, SUM(ent.ss_e_nb_quantidade) AS qtd_uso, COUNT(DISTINCT ent.ss_e_nb_colaborador_id) AS colabs_uso
            FROM ss_epi_entrega ent
            WHERE ent.ss_e_tx_status = 'ativo' {$empresaFiltro}
            GROUP BY ent.ss_e_nb_epi_id
        ) uso ON uso.ss_e_nb_epi_id = epi.ss_e_nb_id
        LEFT JOIN (
            SELECT ent.ss_e_nb_epi_id, SUM(ent.ss_e_nb_quantidade) AS qtd_inspecao
            FROM ss_epi_entrega ent
            WHERE ent.ss_e_tx_destino = 'inspecao' AND ent.ss_e_tx_status_inspecao = 'pendente' {$empresaFiltro}
            GROUP BY ent.ss_e_nb_epi_id
        ) insc ON insc.ss_e_nb_epi_id = epi.ss_e_nb_id
        LEFT JOIN (
            SELECT ent.ss_e_nb_epi_id, SUM(ent.ss_e_nb_quantidade) AS qtd_descarte
            FROM ss_epi_entrega ent
            WHERE ((ent.ss_e_tx_destino = 'inspecao' AND ent.ss_e_tx_status_inspecao = 'descartado')
                OR (ent.ss_e_tx_destino IS NULL AND ent.ss_e_tx_status IN ('devolvido', 'substituido'))) {$empresaFiltro}
            GROUP BY ent.ss_e_nb_epi_id
        ) `desc` ON `desc`.ss_e_nb_epi_id = epi.ss_e_nb_id
        LEFT JOIN (
            SELECT ent.ss_e_nb_epi_id, SUM(ent.ss_e_nb_quantidade) AS qtd_perdido
            FROM ss_epi_entrega ent
            WHERE ent.ss_e_tx_status = 'perdido' {$empresaFiltro}
            GROUP BY ent.ss_e_nb_epi_id
        ) perd ON perd.ss_e_nb_epi_id = epi.ss_e_nb_id
        WHERE epi.ss_e_tx_status = 'ativo' AND epi.ss_e_tx_cadastro_tipo = 'estoque'
        ORDER BY epi.ss_e_tx_grupo ASC, epi.ss_e_tx_subgrupo ASC, epi.ss_e_tx_item ASC
    ");

    $mapa = [];
    $totais = [
        "total_tipos" => 0,
        "total_estoque" => 0,
        "total_uso" => 0,
        "total_colabs_uso" => 0,
        "total_inspecao" => 0,
        "total_descarte" => 0,
        "total_perdido" => 0
    ];
    if ($sqlMapa) {
        while ($row = mysqli_fetch_assoc($sqlMapa)) {
            $mapa[] = $row;
            $totais["total_tipos"]++;
            $totais["total_estoque"] += (int)$row["saldo_estoque"];
            $totais["total_uso"] += (int)$row["qtd_uso"];
            $totais["total_colabs_uso"] += (int)$row["colabs_uso"];
            $totais["total_inspecao"] += (int)$row["qtd_inspecao"];
            $totais["total_descarte"] += (int)$row["qtd_descarte"];
            $totais["total_perdido"] += (int)$row["qtd_perdido"];
        }
    }

    // Filtro de busca por nome/CA aplicado em memória
    $termoBusca = trim($_POST["busca_epi_like"] ?? "");
    if ($termoBusca !== "") {
        $termoLower = mb_strtolower($termoBusca);
        $mapa = array_values(array_filter($mapa, function ($r) use ($termoLower) {
            $texto = mb_strtolower(($r["ss_e_tx_grupo"] ?? "") . " " . ($r["ss_e_tx_subgrupo"] ?? "") . " " . ($r["ss_e_tx_item"] ?? "") . " " . ($r["ss_e_tx_ca"] ?? ""));
            return mb_strpos($texto, $termoLower) !== false;
        }));
    }

    // 2) EPIs em uso por cargo/setor (árvore: Cargo -> Colaborador -> EPIs)
    $sqlCargos = query("
        SELECT o.oper_tx_nome AS cargo, col.enti_nb_id, col.enti_tx_nome AS colaborador, col.enti_tx_matricula,
               COUNT(DISTINCT ent.ss_e_nb_epi_id) AS qtd_tipos,
               SUM(ent.ss_e_nb_quantidade) AS qtd_total
        FROM ss_epi_entrega ent
        JOIN entidade col ON ent.ss_e_nb_colaborador_id = col.enti_nb_id
        LEFT JOIN operacao o ON col.enti_tx_tipoOperacao = o.oper_nb_id
        WHERE ent.ss_e_tx_status = 'ativo' {$empresaFiltro}
        GROUP BY cargo, col.enti_nb_id, col.enti_tx_nome, col.enti_tx_matricula
        ORDER BY cargo ASC, col.enti_tx_nome ASC
    ");

    $itensPorColaborador = [];
    $cargosRows = [];
    if ($sqlCargos && mysqli_num_rows($sqlCargos) > 0) {
        $ids = [];
        while ($rowCargo = mysqli_fetch_assoc($sqlCargos)) {
            $cargosRows[] = $rowCargo;
            $ids[] = (int)$rowCargo["enti_nb_id"];
        }
        $idsStr = implode(",", array_unique($ids));
        $sqlItensColab = query("
            SELECT ent.ss_e_nb_colaborador_id, epi.ss_e_tx_grupo, epi.ss_e_tx_subgrupo, epi.ss_e_tx_item,
                   ent.ss_e_nb_quantidade, ent.ss_e_tx_variacao, ent.ss_e_tx_data_entrega
            FROM ss_epi_entrega ent
            JOIN ss_epi epi ON ent.ss_e_nb_epi_id = epi.ss_e_nb_id
            WHERE ent.ss_e_tx_status = 'ativo' AND ent.ss_e_nb_colaborador_id IN ({$idsStr}) {$empresaFiltro}
            ORDER BY epi.ss_e_tx_grupo ASC, epi.ss_e_tx_subgrupo ASC, epi.ss_e_tx_item ASC
        ");
        if ($sqlItensColab) {
            while ($rowItem = mysqli_fetch_assoc($sqlItensColab)) {
                $itensPorColaborador[(int)$rowItem["ss_e_nb_colaborador_id"]][] = $rowItem;
            }
        }
    }

    // 3) Destaques: EPIs com estoque zerado mas em uso (alerta de reposição)
    $repor = [];
    foreach ($mapa as $m) {
        if ((int)$m["saldo_estoque"] <= 0 && (int)$m["qtd_uso"] > 0) {
            $repor[] = $m;
        }
    }

    // ==================== LAYOUT ====================

    echo '
    <style>
        .mapa-card {
            border-radius: 10px;
            padding: 16px;
            color: #fff;
            box-shadow: 0 4px 14px rgba(0,0,0,0.14);
            height: 96px;
        }
        .mapa-card .valor {
            font-size: 26px;
            font-weight: 800;
            line-height: 1.1;
        }
        .mapa-card .rotulo {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.95;
        }
        .arvore-epi {
            margin-bottom: 4px;
        }
        .arvore-epi summary {
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            background: #f5f7fa;
            border: 1px solid #e4e7eb;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            list-style: none;
        }
        .arvore-epi summary::-webkit-details-marker { display: none; }
        .arvore-epi summary:hover { background: #eef1f5; }
        .arvore-epi[open] summary { border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
        .arvore-epi .nivel-item {
            padding: 6px 12px 6px 26px;
            border-left: 1px solid #e4e7eb;
            margin-left: 14px;
        }
        .arvore-epi .nivel-sub {
            padding: 6px 12px 6px 14px;
            border-left: 1px solid #e4e7eb;
            margin-left: 14px;
            color: #555;
        }
        .badge-mapa { font-size: 11px; font-weight: bold; margin-right: 4px; }
    </style>
    ';

    // Cards de totais
    $cardDefs = [
        ["Tipos de EPI", $totais["total_tipos"], "fa-shield", "#337ab7"],
        ["Em Estoque (unids)", $totais["total_estoque"], "fa-cubes", "#5cb85c"],
        ["Em Uso (unids)", $totais["total_uso"], "fa-user", "#5bc0de"],
        ["Colaboradores c/ EPI", $totais["total_colabs_uso"], "fa-users", "#8775a7"],
        ["Em Inspeção", $totais["total_inspecao"], "fa-search", "#f0ad4e"],
        ["Descartados", $totais["total_descarte"], "fa-trash", "#d9534f"],
        ["Perdidos", $totais["total_perdido"], "fa-times-circle", "#8b3d8f"]
    ];
    echo '<div class="row" style="margin-top: 15px; margin-bottom: 15px;">';
    foreach ($cardDefs as $cd) {
        echo '
        <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom: 10px;">
            <div class="mapa-card" style="background: linear-gradient(135deg, ' . $cd[3] . ', ' . $cd[3] . 'cc);">
                <div class="valor">' . $cd[1] . '</div>
                <div class="rotulo"><i class="fa ' . $cd[2] . '"></i> ' . $cd[0] . '</div>
            </div>
        </div>';
    }
    echo '</div>';

    // Alerta de reposição
    if (!empty($repor)) {
        echo '
        <div class="alert alert-danger" style="border-radius: 8px;">
            <i class="fa fa-exclamation-triangle"></i> <strong>Alerta de reposição:</strong> ' . count($repor) . ' EPI(s) estão <strong>sem estoque</strong> mas continuam em uso pelos colaboradores:
            <ul style="margin: 6px 0 0 18px;">';
        foreach (array_slice($repor, 0, 8) as $r) {
            echo '<li>' . htmlspecialchars($r["ss_e_tx_grupo"] . " / " . ($r["ss_e_tx_item"] ?? "")) . ' — em uso: ' . (int)$r["qtd_uso"] . ' unids</li>';
        }
        if (count($repor) > 8) {
            echo '<li>... e mais ' . (count($repor) - 8) . ' item(ns)</li>';
        }
        echo '</ul></div>';
    }

    // ===== ÁRVORE 1: Por Grupo de EPI =====
    echo '
    <div class="portlet light bordered" style="margin-bottom: 20px;">
        <div class="portlet-title">
            <div class="caption font-blue">
                <i class="fa fa-sitemap"></i>
                <span class="caption-subject bold uppercase">Árvore de EPIs por Grupo</span>
            </div>
        </div>
        <div class="portlet-body">';

    if (empty($mapa)) {
        echo '<div class="alert alert-warning">Nenhum EPI encontrado com os filtros aplicados.</div>';
    } else {
        $grupos = [];
        foreach ($mapa as $m) {
            $grupos[$m["ss_e_tx_grupo"]][$m["ss_e_tx_subgrupo"] ?? ""][] = $m;
        }

        foreach ($grupos as $grupo => $subgrupos) {
            $qtdTiposGrupo = 0;
            $saldoGrupo = 0;
            $usoGrupo = 0;
            foreach ($subgrupos as $itens) {
                foreach ($itens as $it) {
                    $qtdTiposGrupo++;
                    $saldoGrupo += (int)$it["saldo_estoque"];
                    $usoGrupo += (int)$it["qtd_uso"];
                }
            }
            echo '
            <details class="arvore-epi">
                <summary>
                    <i class="fa fa-folder-open-o" style="color:#337ab7;"></i> ' . htmlspecialchars($grupo) . '
                    <span class="label label-primary badge-mapa">' . $qtdTiposGrupo . ' tipo(s)</span>
                    <span class="label label-success badge-mapa">Estoque: ' . $saldoGrupo . '</span>
                    <span class="label label-info badge-mapa">Em uso: ' . $usoGrupo . '</span>
                </summary>
                <div style="padding: 4px 0;">';
            foreach ($subgrupos as $subgrupo => $itens) {
                $saldoSub = 0;
                $usoSub = 0;
                foreach ($itens as $it) {
                    $saldoSub += (int)$it["saldo_estoque"];
                    $usoSub += (int)$it["qtd_uso"];
                }
                $labelSub = ($subgrupo !== "") ? $subgrupo : "Sem subgrupo";
                echo '
                <details class="arvore-epi" style="margin-left: 12px;">
                    <summary>
                        <i class="fa fa-folder-o" style="color:#5bc0de;"></i> ' . htmlspecialchars($labelSub) . '
                        <span class="label label-success badge-mapa">Estoque: ' . $saldoSub . '</span>
                        <span class="label label-info badge-mapa">Em uso: ' . $usoSub . '</span>
                    </summary>
                    <div style="padding: 2px 0;">';
                foreach ($itens as $it) {
                    $badges = '';
                    $badges .= '<span class="label label-success badge-mapa">Estoque: ' . (int)$it["saldo_estoque"] . '</span>';
                    $badges .= '<span class="label label-info badge-mapa">Uso: ' . (int)$it["qtd_uso"] . ' (' . (int)$it["colabs_uso"] . ' colab.)</span>';
                    if ((int)$it["qtd_inspecao"] > 0) $badges .= '<span class="label label-warning badge-mapa">Inspeção: ' . (int)$it["qtd_inspecao"] . '</span>';
                    if ((int)$it["qtd_descarte"] > 0) $badges .= '<span class="label label-danger badge-mapa">Descarte: ' . (int)$it["qtd_descarte"] . '</span>';
                    if ((int)$it["qtd_perdido"] > 0) $badges .= '<span class="label label-purple badge-mapa" style="background:#8b3d8f;">Perdido: ' . (int)$it["qtd_perdido"] . '</span>';
                    echo '
                    <div class="nivel-item">
                        <i class="fa fa-check-circle-o" style="color:#5cb85c;"></i> ' . htmlspecialchars($it["ss_e_tx_item"] ?? "") . '
                        ' . (!empty($it["ss_e_tx_ca"]) ? '<small class="text-muted">(CA: ' . htmlspecialchars($it["ss_e_tx_ca"]) . ')</small>' : '') . '
                        <br><span style="margin-left: 20px;">' . $badges . '</span>
                    </div>';
                }
                echo '
                    </div>
                </details>';
            }
            echo '
                </div>
            </details>';
        }
    }
    echo '
        </div>
    </div>';

    // ===== ÁRVORE 2: Por Cargo (setor) =====
    echo '
    <div class="portlet light bordered" style="margin-bottom: 20px;">
        <div class="portlet-title">
            <div class="caption font-green-haze">
                <i class="fa fa-users"></i>
                <span class="caption-subject bold uppercase">EPIs em Uso por Cargo</span>
            </div>
        </div>
        <div class="portlet-body">';

    $cargos = [];
    if (!empty($cargosRows)) {
        foreach ($cargosRows as $rowCargo) {
            $cargoNome = !empty($rowCargo["cargo"]) ? $rowCargo["cargo"] : "Sem cargo definido";
            $cargos[$cargoNome][] = $rowCargo;
        }
    }

    if (empty($cargos)) {
        echo '<div class="alert alert-warning">Nenhum EPI em uso por colaboradores no momento.</div>';
    } else {
        ksort($cargos);
        foreach ($cargos as $cargoNome => $colabs) {
            $qtdColabs = count($colabs);
            $qtdTotalUso = 0;
            foreach ($colabs as $c) {
                $qtdTotalUso += (int)$c["qtd_total"];
            }
            echo '
            <details class="arvore-epi">
                <summary>
                    <i class="fa fa-briefcase" style="color:#44b6ae;"></i> ' . htmlspecialchars($cargoNome) . '
                    <span class="label label-primary badge-mapa">' . $qtdColabs . ' colaborador(es)</span>
                    <span class="label label-info badge-mapa">' . $qtdTotalUso . ' unids em uso</span>
                </summary>
                <div style="padding: 4px 0;">';
            foreach ($colabs as $c) {
                $itensColab = $itensPorColaborador[(int)$c["enti_nb_id"]] ?? [];
                echo '
                <details class="arvore-epi" style="margin-left: 12px;">
                    <summary>
                        <i class="fa fa-user" style="color:#5bc0de;"></i> ' . htmlspecialchars($c["colaborador"]) . '
                        ' . (!empty($c["enti_tx_matricula"]) ? '<small class="text-muted">(Matr. ' . htmlspecialchars($c["enti_tx_matricula"]) . ')</small>' : '') . '
                        <span class="label label-info badge-mapa">' . (int)$c["qtd_tipos"] . ' tipo(s) · ' . (int)$c["qtd_total"] . ' unids</span>
                    </summary>
                    <div style="padding: 2px 0;">';
                foreach ($itensColab as $ic) {
                    echo '
                    <div class="nivel-item">
                        <i class="fa fa-check-circle-o" style="color:#5cb85c;"></i> ' . htmlspecialchars($ic["ss_e_tx_grupo"] . " / " . ($ic["ss_e_tx_item"] ?? "")) . '
                        <span class="label label-default badge-mapa">' . (int)$ic["ss_e_nb_quantidade"] . ' unid(s)</span>
                        ' . (!empty($ic["ss_e_tx_variacao"]) ? '<span class="label label-warning badge-mapa">Var: ' . htmlspecialchars($ic["ss_e_tx_variacao"]) . '</span>' : '') . '
                        <small class="text-muted">desde ' . date("d/m/Y", strtotime($ic["ss_e_tx_data_entrega"])) . '</small>
                    </div>';
                }
                echo '
                    </div>
                </details>';
            }
            echo '
                </div>
            </details>';
        }
    }
    echo '
        </div>
    </div>';

    // ===== GRID DETALHADO =====
    $gridFields = [
        "CÓDIGO"      => "ss_e_nb_id",
        "GRUPO"       => "ss_e_tx_grupo",
        "SUBGRUPO"    => "ss_e_tx_subgrupo",
        "ITEM"        => "ss_e_tx_item",
        "CA"          => "ss_e_tx_ca",
        "ESTOQUE"     => "saldo_estoque",
        "EM USO"      => "qtd_uso",
        "COLAB. C/ EPI" => "colabs_uso",
        "INSPEÇÃO"    => "qtd_inspecao",
        "DESCARTE"    => "qtd_descarte",
        "PERDIDO"     => "qtd_perdido"
    ];

    $camposBusca = [];
    $queryBase = "SELECT * FROM (
                    SELECT epi.ss_e_nb_id, epi.ss_e_tx_grupo, epi.ss_e_tx_subgrupo, epi.ss_e_tx_item, epi.ss_e_tx_ca, epi.ss_e_tx_variacoes,
                           IFNULL(est.saldo, 0) AS saldo_estoque,
                           IFNULL(uso.qtd_uso, 0) AS qtd_uso,
                           IFNULL(uso.colabs_uso, 0) AS colabs_uso,
                           IFNULL(insc.qtd_inspecao, 0) AS qtd_inspecao,
                           IFNULL(dd.qtd_descarte, 0) AS qtd_descarte,
                           IFNULL(perd.qtd_perdido, 0) AS qtd_perdido
                    FROM ss_epi epi
                    LEFT JOIN (
                        SELECT ss_e_nb_epi_id, SUM(CASE WHEN ss_e_tx_tipo = 'entrada' THEN ss_e_nb_quantidade ELSE -ss_e_nb_quantidade END) AS saldo
                        FROM ss_epi_estoque WHERE 1 {$empresaFiltro} GROUP BY ss_e_nb_epi_id
                    ) est ON est.ss_e_nb_epi_id = epi.ss_e_nb_id
                    LEFT JOIN (
                        SELECT ent.ss_e_nb_epi_id, SUM(ent.ss_e_nb_quantidade) AS qtd_uso, COUNT(DISTINCT ent.ss_e_nb_colaborador_id) AS colabs_uso
                        FROM ss_epi_entrega ent WHERE ent.ss_e_tx_status = 'ativo' {$empresaFiltro} GROUP BY ent.ss_e_nb_epi_id
                    ) uso ON uso.ss_e_nb_epi_id = epi.ss_e_nb_id
                    LEFT JOIN (
                        SELECT ent.ss_e_nb_epi_id, SUM(ent.ss_e_nb_quantidade) AS qtd_inspecao
                        FROM ss_epi_entrega ent WHERE ent.ss_e_tx_destino = 'inspecao' AND ent.ss_e_tx_status_inspecao = 'pendente' {$empresaFiltro}
                        GROUP BY ent.ss_e_nb_epi_id
                    ) insc ON insc.ss_e_nb_epi_id = epi.ss_e_nb_id
                    LEFT JOIN (
                        SELECT ent.ss_e_nb_epi_id, SUM(ent.ss_e_nb_quantidade) AS qtd_descarte
                        FROM ss_epi_entrega ent
                        WHERE ((ent.ss_e_tx_destino = 'inspecao' AND ent.ss_e_tx_status_inspecao = 'descartado')
                            OR (ent.ss_e_tx_destino IS NULL AND ent.ss_e_tx_status IN ('devolvido', 'substituido'))) {$empresaFiltro}
                        GROUP BY ent.ss_e_nb_epi_id
                    ) dd ON dd.ss_e_nb_epi_id = epi.ss_e_nb_id
                    LEFT JOIN (
                        SELECT ent.ss_e_nb_epi_id, SUM(ent.ss_e_nb_quantidade) AS qtd_perdido
                        FROM ss_epi_entrega ent WHERE ent.ss_e_tx_status = 'perdido' {$empresaFiltro}
                        GROUP BY ent.ss_e_nb_epi_id
                    ) perd ON perd.ss_e_nb_epi_id = epi.ss_e_nb_id
                    WHERE epi.ss_e_tx_status = 'ativo' AND epi.ss_e_tx_cadastro_tipo = 'estoque'
                  ) AS mapa";

    $jsAcoes = '
        var funcoesInternas = function(){
            // Destaque do EPI (SUBGRUPO, índice 2 — Código, Grupo, Subgrupo, Item...)
            $("#result tbody tr").each(function() {
                $(this).find("td").eq(2).css({ "font-weight": "bold", "color": "#337ab7" });
            });

            // Colora os valores de estoque
            $("#result tbody tr").each(function() {
                var row = $(this);
                var tdEstoque = row.find("td").eq(5);
                var estoque = parseInt(tdEstoque.text()) || 0;
                tdEstoque.html(estoque > 0
                    ? "<span class=\"label label-success\">" + estoque + "</span>"
                    : "<span class=\"label label-danger\">" + estoque + "</span>");
                var tdUso = row.find("td").eq(6);
                var uso = parseInt(tdUso.text()) || 0;
                tdUso.html(uso > 0
                    ? "<span class=\"label label-info\">" + uso + "</span>"
                    : "<span class=\"text-muted\">0</span>");
            });
        };
    ';

    echo '
    <div class="portlet light bordered">
        <div class="portlet-title">
            <div class="caption font-dark">
                <i class="fa fa-table"></i>
                <span class="caption-subject bold uppercase">Detalhamento por EPI</span>
            </div>
        </div>
        <div class="portlet-body">';
    echo gridDinamico("tabelaMapaEpis", $gridFields, $camposBusca, $queryBase, $jsAcoes);
    echo '
        </div>
    </div>';

    rodape();
}
