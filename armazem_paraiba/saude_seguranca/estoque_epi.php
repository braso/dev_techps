<?php
ob_start();
/* Modo debug
    ini_set("display_errors", 1);
    error_reporting(E_ALL);
//*/

include "conecta.php";

function detalhesFilialAjax() {
    ob_clean();
    $filial_id = isset($_GET["filial_id"]) ? (int)$_GET["filial_id"] : 0;
    $user_empresa = !empty($_SESSION["user_nb_empresa"]) ? (int)$_SESSION["user_nb_empresa"] : 0;
    
    if ($filial_id === 0) {
        $cond = " AND (est.ss_e_nb_empresa_id IS NULL OR est.ss_e_nb_empresa_id = 0 OR est.ss_e_nb_empresa_id = {$user_empresa})";
    } else {
        $cond = " AND est.ss_e_nb_empresa_id = {$filial_id}";
    }
    
    // 1. Consulta Saldo Atual
    $sqlDetails = query("
        SELECT epi.ss_e_nb_id, epi.ss_e_tx_foto, epi.ss_e_tx_grupo, epi.ss_e_tx_subgrupo, epi.ss_e_tx_item, epi.ss_e_tx_fabricante, epi.ss_e_tx_modelo, epi.ss_e_tx_ca,
               IFNULL(SUM(CASE WHEN est.ss_e_tx_tipo = 'entrada' THEN est.ss_e_nb_quantidade ELSE -est.ss_e_nb_quantidade END), 0) AS saldo
        FROM ss_epi epi
        JOIN ss_epi_estoque est ON est.ss_e_nb_epi_id = epi.ss_e_nb_id {$cond}
        WHERE epi.ss_e_tx_cadastro_tipo = 'estoque' AND epi.ss_e_tx_status = 'ativo'
        GROUP BY epi.ss_e_nb_id
        HAVING saldo > 0
        ORDER BY epi.ss_e_tx_grupo ASC, epi.ss_e_tx_subgrupo ASC, epi.ss_e_tx_item ASC
    ");
    
    // 2. Consulta Histórico de Movimentações
    $sqlMovs = query("
        SELECT est.ss_e_nb_id, epi.ss_e_tx_grupo, epi.ss_e_tx_subgrupo, epi.ss_e_tx_item, est.ss_e_tx_tipo, est.ss_e_nb_quantidade, est.ss_e_db_valor_unitario, est.ss_e_db_valor_total, est.ss_e_tx_motivo, est.ss_e_tx_data, est.ss_e_tx_fornecedor,
               IFNULL(DATE_FORMAT(est.ss_e_tx_data_recebimento, '%d/%m/%Y'), '-') AS data_receb_fmt,
               IFNULL(est.ss_e_tx_chave_nf, '-') AS chave_nf,
               IFNULL(DATE_FORMAT(est.ss_e_tx_validade, '%d/%m/%Y'), '-') AS validade_fmt
        FROM ss_epi_estoque est
        JOIN ss_epi epi ON est.ss_e_nb_epi_id = epi.ss_e_nb_id
        WHERE epi.ss_e_tx_cadastro_tipo = 'estoque' {$cond}
        ORDER BY est.ss_e_tx_data DESC, est.ss_e_nb_id DESC
        LIMIT 50
    ");

    echo '
    <ul class="nav nav-tabs" role="tablist" style="margin-bottom: 15px;">
        <li role="presentation" class="active">
            <a href="#tab_saldo" aria-controls="tab_saldo" role="tab" data-toggle="tab" style="font-weight: bold;"><i class="fa fa-cubes"></i> Saldo Atual em Estoque</a>
        </li>
        <li role="presentation">
            <a href="#tab_movimentos" aria-controls="tab_movimentos" role="tab" data-toggle="tab" style="font-weight: bold;"><i class="fa fa-history"></i> Últimas 50 Movimentações</a>
        </li>
    </ul>
    
    <div class="tab-content">
        <!-- Aba Saldo Atual -->
        <div role="tabpanel" class="tab-pane fade in active" id="tab_saldo">
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr style="background-color: #f9f9f9;">
                            <th style="width: 80px; text-align: center;">Foto</th>
                            <th>Grupo / Subgrupo</th>
                            <th>Descrição</th>
                            <th>Fabricante / Modelo</th>
                            <th style="text-align: center;">CA</th>
                            <th style="width: 100px; text-align: center;">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>';
                    
    $hasDetails = false;
    if ($sqlDetails) {
        while ($row = mysqli_fetch_assoc($sqlDetails)) {
            $hasDetails = true;
            $fotoHtml = ss_grid_foto_render($row["ss_e_tx_foto"]);
            echo '
                        <tr>
                            <td style="text-align: center; vertical-align: middle;">' . $fotoHtml . '</td>
                            <td style="vertical-align: middle;"><strong>' . htmlspecialchars($row["ss_e_tx_grupo"]) . '</strong><br><span class="text-muted">' . htmlspecialchars($row["ss_e_tx_subgrupo"]) . '</span></td>
                            <td style="vertical-align: middle;">' . htmlspecialchars($row["ss_e_tx_item"]) . '</td>
                            <td style="vertical-align: middle;">' . htmlspecialchars($row["ss_e_tx_fabricante"] ?? "-") . '<br><small class="text-muted">' . htmlspecialchars($row["ss_e_tx_modelo"] ?? "-") . '</small></td>
                            <td style="text-align: center; vertical-align: middle;"><strong>' . htmlspecialchars($row["ss_e_tx_ca"] ?? "-") . '</strong></td>
                            <td style="text-align: center; vertical-align: middle;"><span class="badge badge-success" style="font-size: 14px; padding: 6px 10px; font-weight: bold;">' . $row["saldo"] . '</span></td>
                        </tr>';
        }
    }
    if (!$hasDetails) {
        echo '<tr><td colspan="6" class="text-center" style="padding: 25px; font-style: italic; color: #777;">Não há itens com saldo positivo nesta filial.</td></tr>';
    }
    
    echo '
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Aba Histórico de Movimentações -->
        <div role="tabpanel" class="tab-pane fade" id="tab_movimentos">
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr style="background-color: #f9f9f9;">
                            <th>Data/Hora</th>
                            <th style="text-align: center;">Operação</th>
                            <th>EPI</th>
                            <th style="text-align: center;">Qtd</th>
                            <th>Fornecedor</th>
                            <th>NF / Recebimento / Validade</th>
                            <th>Motivo / Observação</th>
                        </tr>
                    </thead>
                    <tbody>';
                    
    $hasMovs = false;
    if ($sqlMovs) {
        while ($rowMov = mysqli_fetch_assoc($sqlMovs)) {
            $hasMovs = true;
            $dataFmt = date("d/m/Y H:i", strtotime($rowMov["ss_e_tx_data"]));
            $badgeOperacao = ($rowMov["ss_e_tx_tipo"] === "entrada") 
                ? '<span class="label label-sm label-success" style="font-weight: bold;">Entrada</span>'
                : '<span class="label label-sm label-danger" style="font-weight: bold;">Saída</span>';
                
            echo '
                        <tr>
                            <td style="vertical-align: middle;">' . $dataFmt . '</td>
                            <td style="text-align: center; vertical-align: middle;">' . $badgeOperacao . '</td>
                            <td style="vertical-align: middle;"><strong>' . htmlspecialchars($rowMov["ss_e_tx_grupo"]) . '</strong><br><span class="text-muted">' . htmlspecialchars($rowMov["ss_e_tx_subgrupo"] . ' / ' . $rowMov["ss_e_tx_item"]) . '</span></td>
                            <td style="text-align: center; vertical-align: middle; font-weight: bold;">' . $rowMov["ss_e_nb_quantidade"] . '</td>
                            <td style="vertical-align: middle;">' . htmlspecialchars($rowMov["ss_e_tx_fornecedor"] ?? "-") . '</td>
                            <td style="vertical-align: middle;">NF: ' . htmlspecialchars($rowMov["chave_nf"]) . '<br><small class="text-muted">receb: ' . $rowMov["data_receb_fmt"] . '</small><br><small class="text-muted">validade: ' . $rowMov["validade_fmt"] . '</small></td>
                            <td style="vertical-align: middle; font-style: italic;">' . htmlspecialchars($rowMov["ss_e_tx_motivo"] ?? "-") . '</td>
                        </tr>';
        }
    }
    if (!$hasMovs) {
        echo '<tr><td colspan="7" class="text-center" style="padding: 25px; font-style: italic; color: #777;">Não foram encontradas movimentações de estoque para esta filial.</td></tr>';
    }
    
    echo '
                    </tbody>
                </table>
            </div>
        </div>
    </div>';
    exit;
}

function index() {
    cabecalho("Controle de Estoque de EPI");
    echo '<style>#btnExportPDF { display: none !important; }</style>';
    
    $temFiliais = ss_tem_filiais_cadastradas();
    if ($temFiliais) {
        $user_empresa = !empty($_SESSION["user_nb_empresa"]) ? (int)$_SESSION["user_nb_empresa"] : 0;
        
        $sqlStats = query("
            SELECT 
                CASE WHEN est.ss_e_nb_empresa_id = {$user_empresa} OR est.ss_e_nb_empresa_id IS NULL OR est.ss_e_nb_empresa_id = 0 THEN 0 ELSE est.ss_e_nb_empresa_id END AS filial_id,
                COUNT(DISTINCT est.ss_e_nb_epi_id) AS total_tipos,
                SUM(CASE WHEN est.ss_e_tx_tipo = 'entrada' THEN est.ss_e_nb_quantidade ELSE -est.ss_e_nb_quantidade END) AS total_saldo
            FROM ss_epi_estoque est
            JOIN ss_epi epi ON est.ss_e_nb_epi_id = epi.ss_e_nb_id
            WHERE epi.ss_e_tx_cadastro_tipo = 'estoque'
            GROUP BY filial_id
        ");
        
        $statsByFilial = [];
        if ($sqlStats) {
            while ($rowStat = mysqli_fetch_assoc($sqlStats)) {
                $statsByFilial[(int)$rowStat["filial_id"]] = [
                    "total_tipos" => (int)$rowStat["total_tipos"],
                    "total_saldo" => (int)$rowStat["total_saldo"]
                ];
            }
        }
        
        $matrizStats = $statsByFilial[0] ?? ["total_tipos" => 0, "total_saldo" => 0];
        
        $sqlNomes = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' AND empr_nb_id != {$user_empresa} ORDER BY empr_tx_nome ASC");
        $filiaisNomes = [0 => "Matriz"];
        if ($sqlNomes) {
            while ($rowNome = mysqli_fetch_assoc($sqlNomes)) {
                $filiaisNomes[(int)$rowNome["empr_nb_id"]] = $rowNome["empr_tx_nome"];
            }
        }
        
        $cores = ["blue-madison", "red-intense", "green-haze", "purple-plum", "yellow-crust", "blue-hoki", "grey-cascade"];
        $corIdx = 0;
        
        echo '
        <div class="portlet light bordered" style="margin-top: 15px; margin-bottom: 20px;">
            <div class="portlet-title" style="margin-bottom: 10px; border-bottom: 1px solid #eee;">
                <div class="caption font-dark">
                    <i class="icon-share font-dark"></i>
                    <span class="caption-subject bold uppercase">Estoque Consolidado por Empresas</span>
                </div>
                <div class="actions" style="display: inline-block; float: right; margin-top: -4px;">
                    <button type="button" class="btn btn-default btn-xs" id="btn_toggle_filiais" onclick="toggleMostrarTodasFiliais()"><i class="fa fa-eye"></i> Mostrar Todas</button>
                    <button type="button" class="btn btn-default btn-xs" id="btn_toggle_visibilidade" style="margin-left: 5px;" onclick="toggleVisibilidadeCards()"><i class="fa fa-chevron-up"></i> Ocultar Cards</button>
                </div>
                <div class="tools" style="float: right; margin-left: 10px; margin-top: 2px;">
                    <a href="javascript:;" class="collapse" title="Recolher/Expandir"></a>
                </div>
            </div>
            <div class="portlet-body" id="container_cards_filiais">
                <div class="row">';
        
        $matrizSaldo = (int)$matrizStats["total_saldo"];
        $classMatriz = ($matrizSaldo > 0) ? 'card-com-saldo' : 'card-sem-saldo';
        $styleMatriz = ($matrizSaldo > 0) ? 'display: block; margin-bottom: 15px;' : 'display: none; margin-bottom: 15px;';
        
        $corMatriz = $cores[$corIdx++ % count($cores)];
        echo '
                <div class="col-md-2 col-sm-4 col-xs-6 ' . $classMatriz . '" style="' . $styleMatriz . '">
                    <div class="custom-dashboard-card ' . $corMatriz . '" onclick="abrirDetalhesFilial(0, \'Matriz\')">
                        <div class="card-icon-badge">
                            <i class="fa fa-building-o"></i>
                        </div>
                        <div class="card-details">
                            <div class="card-value">' . $matrizSaldo . ' <small>unids</small></div>
                            <div class="card-title">Matriz</div>
                            <div class="card-subtitle">' . $matrizStats["total_tipos"] . ' tipos de EPI</div>
                        </div>
                        <a class="card-footer-action" href="javascript:;" onclick="event.stopPropagation(); abrirDetalhesFilial(0, \'Matriz\')"> 
                            <span>Ver Detalhes</span> <i class="fa fa-arrow-right"></i>
                        </a>
                    </div>
                </div>';
                
        foreach ($filiaisNomes as $fid => $fnome) {
            if ($fid === 0) continue;
            $fStats = $statsByFilial[$fid] ?? ["total_tipos" => 0, "total_saldo" => 0];
            $fSaldo = (int)$fStats["total_saldo"];
            $classFilial = ($fSaldo > 0) ? 'card-com-saldo' : 'card-sem-saldo';
            $styleFilial = ($fSaldo > 0) ? 'display: block; margin-bottom: 15px;' : 'display: none; margin-bottom: 15px;';
            
            $cor = $cores[$corIdx++ % count($cores)];
            echo '
                <div class="col-md-2 col-sm-4 col-xs-6 ' . $classFilial . '" style="' . $styleFilial . '">
                    <div class="custom-dashboard-card ' . $cor . '" onclick="abrirDetalhesFilial(' . $fid . ', \'' . addslashes($fnome) . '\')">
                        <div class="card-icon-badge">
                            <i class="fa fa-map-marker"></i>
                        </div>
                        <div class="card-details">
                            <div class="card-value">' . $fSaldo . ' <small>unids</small></div>
                            <div class="card-title">' . htmlspecialchars($fnome) . '</div>
                            <div class="card-subtitle">' . $fStats["total_tipos"] . ' tipos de EPI</div>
                        </div>
                        <a class="card-footer-action" href="javascript:;" onclick="event.stopPropagation(); abrirDetalhesFilial(' . $fid . ', \'' . addslashes($fnome) . '\')"> 
                            <span>Ver Detalhes</span> <i class="fa fa-arrow-right"></i>
                        </a>
                    </div>
                </div>';
        }
        
        echo '
                </div>
            </div>
        </div>';
    }
    ?>
    <style>
        .campo-fit-content {
            min-width: 0 !important;
        }
        .campo-fit-content label {
            display: block !important;
            margin-bottom: 5px !important;
            font-weight: bold;
        }
        .select2-container--bootstrap, 
        .select2-container,
        span.select2-container {
            display: block !important;
            width: 100% !important;
        }
        span.select2-selection.select2-selection--single {
            width: 100% !important;
        }
        
        /* Estilos customizados e modernos para os cards */
        .custom-dashboard-card {
            position: relative;
            display: block;
            border-radius: 8px !important;
            padding: 15px 15px 10px 15px !important;
            margin-bottom: 0px !important;
            min-height: 100px !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
            cursor: pointer;
            overflow: hidden;
        }
        .custom-dashboard-card:hover {
            transform: translateY(-4px) !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18) !important;
        }
        
        /* Gradients mapping to Metronic classes */
        .custom-dashboard-card.blue-madison { background: linear-gradient(135deg, #3598dc, #2270b2) !important; }
        .custom-dashboard-card.red-intense { background: linear-gradient(135deg, #e35b5a, #be2626) !important; }
        .custom-dashboard-card.green-haze { background: linear-gradient(135deg, #44b6ae, #1f8c85) !important; }
        .custom-dashboard-card.purple-plum { background: linear-gradient(135deg, #8775a7, #594576) !important; }
        .custom-dashboard-card.blue-hoki { background: linear-gradient(135deg, #67809f, #3b5066) !important; }
        .custom-dashboard-card.yellow-crusta { background: linear-gradient(135deg, #f2784b, #c04e22) !important; }
        
        .custom-dashboard-card .card-icon-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease;
        }
        .custom-dashboard-card:hover .card-icon-badge {
            background: rgba(255, 255, 255, 0.28);
        }
        .custom-dashboard-card .card-icon-badge i {
            font-size: 16px;
            color: #fff;
        }
        .custom-dashboard-card .card-details {
            text-align: right;
            padding-bottom: 35px;
            padding-right: 2px;
        }
        .custom-dashboard-card .card-value {
            font-size: 22px !important;
            font-weight: 800 !important;
            color: #fff !important;
            margin-bottom: 2px !important;
            line-height: 1.1 !important;
        }
        .custom-dashboard-card .card-value small {
            font-size: 11px !important;
            font-weight: 400 !important;
            color: rgba(255, 255, 255, 0.9) !important;
        }
        .custom-dashboard-card .card-title {
            font-size: 11px !important;
            font-weight: 700 !important;
            color: #fff !important;
            opacity: 0.95 !important;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 2px !important;
        }
        .custom-dashboard-card .card-subtitle {
            font-size: 10px !important;
            color: rgba(255, 255, 255, 0.85) !important;
            margin-top: 1px !important;
        }
        .custom-dashboard-card .card-footer-action {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 6px 15px;
            background: rgba(0, 0, 0, 0.12);
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-decoration: none !important;
            transition: background 0.2s ease;
        }
        .custom-dashboard-card:hover .card-footer-action {
            background: rgba(0, 0, 0, 0.22);
        }
        .custom-dashboard-card .card-footer-action span {
            font-size: 9px !important;
            font-weight: bold !important;
            color: #fff !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .custom-dashboard-card .card-footer-action i {
            font-size: 9px !important;
            color: #fff !important;
            transition: transform 0.2s ease;
        }
        .custom-dashboard-card:hover .card-footer-action i {
            transform: translateX(4px);
        }
        #btnExportPDF {
            display: none !important;
        }
    </style>
    <?php

    if (!isset($_POST["busca_visao"])) {
        $_POST["busca_visao"] = "saldo";
    }
    if (!isset($_POST["busca_status"])) {
        $_POST["busca_status"] = "ativo";
    }

    // Carregar todas as empresas ativas para filtro de busca
    $empresaOptions = ["" => "Todas as Empresas"];
    $sqlEmpresas = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
    if ($sqlEmpresas) {
        while ($rowEmp = mysqli_fetch_assoc($sqlEmpresas)) {
            $empresaOptions[$rowEmp["empr_nb_id"]] = $rowEmp["empr_tx_nome"];
        }
    }

    $fields = [
        campo("Código", "busca_codigo", $_POST["busca_codigo"] ?? "", 1, "MASCARA_NUMERO"),
        campo("Grupo", "busca_grupo", $_POST["busca_grupo"] ?? "", 2),
        campo("EPI", "busca_subgrupo", $_POST["busca_subgrupo"] ?? "", 2),
        campo("Descrição", "busca_item", $_POST["busca_item"] ?? "", 2),
        campo("Modelo", "busca_modelo", $_POST["busca_modelo"] ?? "", 1),
        campo("CA", "busca_ca", $_POST["busca_ca"] ?? "", 1)
    ];
    if ($temFiliais) {
        $fields[] = combo("Empresa", "busca_filial", $_POST["busca_filial"] ?? "", 2, $empresaOptions);
    }
    $fields[] = combo("Status", "busca_status", $_POST["busca_status"] ?? "ativo", 1, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]);
    $fields[] = combo("Visualização", "busca_visao", $_POST["busca_visao"] ?? "saldo", 2, ["saldo" => "Saldo Atual por EPI", "mov" => "Histórico de Movimentações"]);

    $buttons = [];
    $buttons[] = botao("Buscar", "index");
    $buttons[] = '<a href="cadastrar_epi_estoque.php" class="btn btn-primary"><i class="fa fa-plus"></i> Cadastrar Item no Estoque</a>';
    $buttons[] = '<a href="movimentacao_estoque.php" class="btn btn-success"><i class="fa fa-exchange"></i> Lançar Movimentação Estoque</a>';

    echo abre_form("Filtros de Pesquisa");
    echo linha_form($fields);
    echo fecha_form($buttons);

    $busca_filial = $_POST["busca_filial"] ?? "";

    if ($_POST["busca_visao"] === "mov") {
        // Exibição do histórico de movimentações
        $gridFields = [
            "ID"                => "ss_e_nb_id",
            "CÓDIGO"            => "ss_e_nb_epi_id",
            "EMPRESA"           => "filial_nome",
            "USUÁRIO"           => "colaborador_nome",
            "GRUPO"             => "ss_e_tx_grupo",
            "EPI"               => "ss_e_tx_subgrupo",
            "DESCRIÇÃO"         => "ss_e_tx_item",
            "TIPO"              => "ss_e_tx_tipo",
            "QUANTIDADE"        => "ss_e_nb_quantidade",
            "VLR. UNITÁRIO"     => "ss_e_db_valor_unitario",
            "VLR. TOTAL"        => "ss_e_db_valor_total",
            "DATA RECEB."       => "data_receb_fmt",
            "VALIDADE EPI"      => "validade_epi_fmt",
            "CHAVE NF"          => "chave_nf",
            "FORNECEDOR"        => "ss_e_tx_fornecedor",
            "MOTIVO/OBSERVAÇÃO" => "ss_e_tx_motivo",
            "DATA/HORA"         => "ss_e_tx_data"
        ];

        $camposBusca = [
            "busca_codigo"   => "epi.ss_e_nb_epi_id",
            "busca_grupo"    => "epi.ss_e_tx_grupo",
            "busca_subgrupo" => "epi.ss_e_tx_subgrupo",
            "busca_item"     => "epi.ss_e_tx_item",
            "busca_ca"       => "epi.ss_e_tx_ca",
            "busca_modelo"   => "epi.ss_e_tx_modelo",
            "busca_status"   => "epi.ss_e_tx_status"
        ];

        $condFilial = "";
        if (!empty($busca_filial)) {
            $condFilial = " AND est.ss_e_nb_empresa_id = " . (int)$busca_filial;
        }

        $queryBase = "SELECT * FROM (
                        SELECT est.ss_e_nb_id, est.ss_e_nb_epi_id, epi.ss_e_tx_grupo, CONCAT(IFNULL(epi.ss_e_tx_subgrupo, ''), ' - CA: ', IFNULL(epi.ss_e_tx_ca, 'N/A')) AS ss_e_tx_subgrupo, epi.ss_e_tx_item, est.ss_e_tx_tipo, est.ss_e_nb_quantidade, est.ss_e_db_valor_unitario, est.ss_e_db_valor_total, est.ss_e_tx_motivo, est.ss_e_tx_data, est.ss_e_tx_fornecedor,
                             epi.ss_e_tx_ca, epi.ss_e_tx_modelo, epi.ss_e_tx_status,
                             IFNULL(emp.empr_tx_nome, 'Matriz') AS filial_nome,
                             IFNULL(DATE_FORMAT(est.ss_e_tx_data_recebimento, '%d/%m/%Y'), '-') AS data_receb_fmt,
                             IFNULL(DATE_FORMAT(est.ss_e_tx_validade, '%d/%m/%Y'), '-') AS validade_epi_fmt,
                             IFNULL(est.ss_e_tx_chave_nf, '-') AS chave_nf,
                             IFNULL(usr.user_tx_nome, '-') AS colaborador_nome
                      FROM ss_epi_estoque est 
                      JOIN ss_epi epi ON est.ss_e_nb_epi_id = epi.ss_e_nb_id
                      LEFT JOIN empresa emp ON est.ss_e_nb_empresa_id = emp.empr_nb_id
                      LEFT JOIN user usr ON est.ss_e_nb_userCadastro = usr.user_nb_id
                      WHERE epi.ss_e_tx_cadastro_tipo = 'estoque' {$condFilial}
                        AND (est.ss_e_tx_motivo IS NULL OR LOWER(est.ss_e_tx_motivo) NOT LIKE '%colaborador id:%')
                      ) AS epi";
                      
        echo gridDinamico("tabelaHistoricoMov", $gridFields, $camposBusca, $queryBase, "");
    } else {
        // Exibição do saldo consolidado (Agrupado por EPI usando derived table/subquery para evitar erros de GROUP BY no gridDinamico)
        $gridFields = [
            "CÓDIGO"       => "ss_e_nb_id",
            "FOTO"         => "ss_grid_foto_render(ss_e_tx_foto)",
            "GRUPO"        => "ss_e_tx_grupo",
            "EPI"          => "ss_e_tx_subgrupo",
            "DESCRIÇÃO"    => "ss_e_tx_item",
            "FABRICANTE"   => "ss_e_tx_fabricante",
            "MODELO"       => "ss_e_tx_modelo",
            "CA"           => "ss_e_tx_ca",
            "STATUS"       => "ss_e_tx_status",
            "SALDO ATUAL"  => "saldo"
        ];

        $camposBusca = [
            "busca_codigo"   => "epi.ss_e_nb_id",
            "busca_grupo"    => "epi.ss_e_tx_grupo",
            "busca_subgrupo" => "epi.ss_e_tx_subgrupo",
            "busca_item"     => "epi.ss_e_tx_item",
            "busca_ca"       => "epi.ss_e_tx_ca",
            "busca_modelo"   => "epi.ss_e_tx_modelo",
            "busca_status"   => "epi.ss_e_tx_status"
        ];

        $joinCond = "";
        if (!empty($busca_filial)) {
            $joinCond = " AND est.ss_e_nb_empresa_id = " . (int)$busca_filial;
        }

        $queryBase = "SELECT * FROM (
                        SELECT epi.ss_e_nb_id, epi.ss_e_tx_foto, epi.ss_e_tx_grupo, CONCAT(IFNULL(epi.ss_e_tx_subgrupo, ''), ' - CA: ', IFNULL(epi.ss_e_tx_ca, 'N/A')) AS ss_e_tx_subgrupo, epi.ss_e_tx_item, epi.ss_e_tx_fabricante, epi.ss_e_tx_modelo, epi.ss_e_tx_ca, epi.ss_e_tx_status, epi.ss_e_tx_cadastro_tipo,
                               IFNULL(SUM(CASE WHEN est.ss_e_tx_tipo = 'entrada' THEN est.ss_e_nb_quantidade ELSE -est.ss_e_nb_quantidade END), 0) AS saldo 
                        FROM ss_epi epi 
                        LEFT JOIN ss_epi_estoque est ON est.ss_e_nb_epi_id = epi.ss_e_nb_id {$joinCond}
                        WHERE epi.ss_e_tx_cadastro_tipo = 'estoque'
                        GROUP BY epi.ss_e_nb_id
                      ) AS epi";

        $gridFields["actions"] = [
            '<span class="fa fa-edit acao-editar-epi-est" title="Alterar" style="color:#337ab7; cursor:pointer; font-size:16px; margin-right:8px;"></span>',
            '<span class="fa fa-ban acao-inativar-epi-est" title="Inativar/Ativar" style="color:#f0ad4e; cursor:pointer; font-size:16px; margin-right:8px;"></span>',
            '<span class="fa fa-trash acao-excluir-epi-est" title="Excluir" style="color:#d9534f; cursor:pointer; font-size:16px;"></span>'
        ];

        $jsAcoes = '
            var funcoesInternas = function(){
                // Bind Alterar click
                $(".acao-editar-epi-est").off("click").on("click", function(event) {
                    var id = $(this).closest("tr").attr("data-row-id");
                    submitPost("cadastrar_epi_estoque.php", { id: id });
                });

                // For each row, check status and customize the inativar/ativar icon
                $("#result tbody tr").each(function() {
                    var row = $(this);
                    var statusCell = row.find("td").eq(8); // STATUS is column index 8 (CÓDIGO, FOTO, GRUPO, EPI, DESCRIÇÃO, FABRICANTE, MODELO, CA, STATUS)
                    var statusText = statusCell.text().trim().toLowerCase();
                    
                    var inativarIcon = row.find(".acao-inativar-epi-est");
                    if (statusText.indexOf("inativo") >= 0 || statusText === "inativo") {
                        inativarIcon.removeClass("fa-ban").addClass("fa-check-circle");
                        inativarIcon.attr("title", "Ativar");
                        inativarIcon.css("color", "#5cb85c"); // green
                    } else {
                        inativarIcon.removeClass("fa-check-circle").addClass("fa-ban");
                        inativarIcon.attr("title", "Inativar");
                        inativarIcon.css("color", "#f0ad4e"); // orange
                    }
                });

                // Bind Inativar/Ativar click
                $(".acao-inativar-epi-est").off("click").on("click", function(event) {
                    var row = $(this).closest("tr");
                    var id = row.attr("data-row-id");
                    var epiNome = row.find("td").eq(3).text().trim(); // EPI (subgrupo)
                    var statusCell = row.find("td").eq(10);
                    var statusText = statusCell.text().trim().toLowerCase();
                    
                    var isCurrentlyInactive = (statusText.indexOf("inativo") >= 0 || statusText === "inativo");
                    var acaoLabel = isCurrentlyInactive ? "ativar" : "inativar";
                    var acaoPHP = isCurrentlyInactive ? "ativarEpiEstoque" : "inativarEpiEstoque";
                    var confirmBtnColor = isCurrentlyInactive ? "#5cb85c" : "#f0ad4e";
                    
                    Swal.fire({
                        title: "Tem certeza?",
                        html: "Deseja " + acaoLabel + " o EPI <b>" + epiNome + "</b> no estoque?",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: confirmBtnColor,
                        cancelButtonColor: "#6c757d",
                        confirmButtonText: "Sim, " + acaoLabel + "!",
                        cancelButtonText: "Cancelar"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            submitPost("", { acao: acaoPHP, id: id });
                        }
                    });
                });

                // Bind Excluir click
                $(".acao-excluir-epi-est").off("click").on("click", function(event) {
                    var row = $(this).closest("tr");
                    var id = row.attr("data-row-id");
                    var epiNome = row.find("td").eq(3).text().trim();
                    
                    Swal.fire({
                        title: "Tem certeza?",
                        html: "Deseja excluir permanentemente o EPI <b>" + epiNome + "</b> do estoque?<br><br><span style=\'color:#d9534f;\'><b>Atenção:</b> Isso excluirá também todo o histórico de movimentações e entregas associados a este item!</span>",
                        icon: "error",
                        showCancelButton: true,
                        confirmButtonColor: "#d9534f",
                        cancelButtonColor: "#6c757d",
                        confirmButtonText: "Sim, excluir!",
                        cancelButtonText: "Cancelar"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            submitPost("", { acao: "excluirEpiEstoque", id: id });
                        }
                    });
                });
            };

            if (typeof window.submitPost === "undefined") {
                window.submitPost = function(action, params) {
                    var form = document.createElement("form");
                    form.setAttribute("method", "post");
                    form.setAttribute("action", action);
                    for (var key in params) {
                        var input = document.createElement("input");
                        input.setAttribute("type", "hidden");
                        input.setAttribute("name", key);
                        input.setAttribute("value", params[key]);
                        form.appendChild(input);
                    }
                    $("form[name=\"contex_form\"] :input").each(function() {
                        if (this.name && this.value !== "" && this.name !== "acao" && params[this.name] === undefined) {
                            var input = document.createElement("input");
                            input.setAttribute("type", "hidden");
                            input.setAttribute("name", this.name);
                            input.setAttribute("value", this.value);
                            form.appendChild(input);
                        }
                    });
                    document.body.appendChild(form);
                    form.submit();
                };
            }
        ';

        echo gridDinamico("tabelaSaldoEpis", $gridFields, $camposBusca, $queryBase, $jsAcoes);
    }

    if ($temFiliais) {
        echo '
        <div class="modal fade" id="modalDetalhesFilial" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="border-radius: 6px;">
                    <div class="modal-header" style="background-color: #f5f5f5; border-bottom: 1px solid #ddd; border-top-left-radius: 6px; border-top-right-radius: 6px;">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                        <h4 class="modal-title" id="modalDetalhesFilialTitle" style="font-weight: bold; color: #333;">Detalhes do Estoque - Filial</h4>
                    </div>
                    <div class="modal-body" id="modalDetalhesFilialBody" style="max-height: 600px; overflow-y: auto; padding: 20px;">
                        <div class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i> Carregando estoque...</div>
                    </div>
                    <div class="modal-footer" style="background-color: #f5f5f5; border-top: 1px solid #ddd; border-bottom-left-radius: 6px; border-bottom-right-radius: 6px;">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        var cardsVisiveis = true;
        function toggleVisibilidadeCards() {
            cardsVisiveis = !cardsVisiveis;
            if (cardsVisiveis) {
                $("#container_cards_filiais").slideDown(250);
                $("#btn_toggle_visibilidade").html("<i class=\'fa fa-chevron-up\'></i> Ocultar Cards");
            } else {
                $("#container_cards_filiais").slideUp(250);
                $("#btn_toggle_visibilidade").html("<i class=\'fa fa-chevron-down\'></i> Mostrar Cards");
            }
        }

        var mostrandoTodasFiliais = false;
        function toggleMostrarTodasFiliais() {
            mostrandoTodasFiliais = !mostrandoTodasFiliais;
            if (mostrandoTodasFiliais) {
                $(".card-sem-saldo").slideDown(200);
                $("#btn_toggle_filiais").html("<i class=\'fa fa-eye-slash\'></i> Mostrar Apenas com Saldo");
            } else {
                $(".card-sem-saldo").slideUp(200);
                $("#btn_toggle_filiais").html("<i class=\'fa fa-eye\'></i> Mostrar Todas");
            }
        }

        function selecionarFilialDashboard(filialVal, filialNome) {
            var filialSelect = $("select[name=\'busca_filial\']");
            if (filialSelect.length > 0) {
                filialSelect.val(filialVal).trigger("change");
                
                var targetTable = $("#tabelaSaldoEpis, #tabelaHistoricoMov");
                if (targetTable.length > 0) {
                    $("html, body").animate({
                        scrollTop: targetTable.offset().top - 100
                    }, 500);
                }
            }
        }
        
        function abrirDetalhesFilial(filialId, filialNome) {
            $("#modalDetalhesFilialTitle").text("EPIs em Estoque - " + filialNome);
            $("#modalDetalhesFilialBody").html("<div class=\'text-center\' style=\'padding: 30px;\'><i class=\'fa fa-spinner fa-spin fa-2x\'></i> Carregando dados da filial...</div>");
            $("#modalDetalhesFilial").modal("show");
            
            $.ajax({
                url: "estoque_epi.php?acao=detalhesFilialAjax",
                type: "GET",
                data: { filial_id: filialId },
                success: function(response) {
                    $("#modalDetalhesFilialBody").html(response);
                },
                error: function() {
                    $("#modalDetalhesFilialBody").html("<div class=\'alert alert-danger\'>Ocorreu um erro ao carregar os dados de estoque da filial.</div>");
                }
            });
        }
        </script>
        ';
    }

    rodape();
}

function excluirEpiEstoque() {
    if (!empty($_POST["id"])) {
        $id = (int)$_POST["id"];
        query("DELETE FROM ss_epi WHERE ss_e_nb_id = {$id}");
        set_status("EPI excluído permanentemente com sucesso!");
    }
    index();
    exit;
}

function inativarEpiEstoque() {
    if (!empty($_POST["id"])) {
        $id = (int)$_POST["id"];
        atualizar("ss_epi", ["ss_e_tx_status"], ["inativo"], $id);
        set_status("EPI inativado com sucesso do estoque!");
    }
    index();
    exit;
}

function ativarEpiEstoque() {
    if (!empty($_POST["id"])) {
        $id = (int)$_POST["id"];
        atualizar("ss_epi", ["ss_e_tx_status"], ["ativo"], $id);
        set_status("EPI ativado com sucesso no estoque!");
    }
    index();
    exit;
}



?>
