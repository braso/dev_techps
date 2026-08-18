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
        $condVar = " AND (ss_e_nb_empresa_id IS NULL OR ss_e_nb_empresa_id = 0 OR ss_e_nb_empresa_id = {$user_empresa})";
        $condSaldo = " AND (est2.ss_e_nb_empresa_id IS NULL OR est2.ss_e_nb_empresa_id = 0 OR est2.ss_e_nb_empresa_id = {$user_empresa})";
    } else {
        $cond = " AND est.ss_e_nb_empresa_id = {$filial_id}";
        $condVar = " AND ss_e_nb_empresa_id = {$filial_id}";
        $condSaldo = " AND est2.ss_e_nb_empresa_id = {$filial_id}";
    }
    
    // 1. Consulta Saldo Atual
    $sqlDetails = query("
        SELECT epi.ss_e_nb_id, epi.ss_e_tx_foto, epi.ss_e_tx_grupo, epi.ss_e_tx_subgrupo, epi.ss_e_tx_item, epi.ss_e_tx_fabricante, epi.ss_e_tx_modelo, epi.ss_e_tx_ca, epi.ss_e_tx_variacoes,
               IFNULL((SELECT SUM(CASE WHEN est2.ss_e_tx_tipo = 'entrada' THEN est2.ss_e_nb_quantidade ELSE -est2.ss_e_nb_quantidade END)
                       FROM ss_epi_estoque est2
                       WHERE est2.ss_e_nb_epi_id = epi.ss_e_nb_id {$condSaldo}), 0) AS saldo,
               IFNULL(GROUP_CONCAT(DISTINCT CONCAT(IFNULL(v.variacao, ''), ': ', v.saldo_var) SEPARATOR ' | '), '') AS variacoes_detalhe
        FROM ss_epi epi
        JOIN ss_epi_estoque est ON est.ss_e_nb_epi_id = epi.ss_e_nb_id {$cond}
        LEFT JOIN (SELECT ss_e_nb_epi_id, ss_e_tx_variacao AS variacao,
                          SUM(CASE WHEN ss_e_tx_tipo = 'entrada' THEN ss_e_nb_quantidade ELSE -ss_e_nb_quantidade END) AS saldo_var
                   FROM ss_epi_estoque
                   WHERE IFNULL(ss_e_tx_variacao, '') <> '' {$condVar}
                   GROUP BY ss_e_nb_epi_id, ss_e_tx_variacao) v ON v.ss_e_nb_epi_id = epi.ss_e_nb_id
        WHERE epi.ss_e_tx_cadastro_tipo = 'estoque' AND epi.ss_e_tx_status = 'ativo'
        GROUP BY epi.ss_e_nb_id
        HAVING saldo > 0
        ORDER BY epi.ss_e_tx_grupo ASC, epi.ss_e_tx_subgrupo ASC, epi.ss_e_tx_item ASC
    ");
    
    // 2. Consulta Histórico de Movimentações
    $sqlMovs = query("
        SELECT est.ss_e_nb_id, epi.ss_e_tx_grupo, epi.ss_e_tx_subgrupo, epi.ss_e_tx_item, est.ss_e_tx_variacao, est.ss_e_tx_tipo, est.ss_e_nb_quantidade, est.ss_e_db_valor_unitario, est.ss_e_db_valor_total, est.ss_e_tx_motivo, est.ss_e_tx_data, est.ss_e_tx_fornecedor,
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
            $variacoesHtml = "";
            if (!empty($row["variacoes_detalhe"])) {
                $variacoesHtml = '<br><small class="text-muted">Var.: ' . htmlspecialchars($row["variacoes_detalhe"]) . '</small>';
            }
            echo '
                        <tr>
                            <td style="text-align: center; vertical-align: middle;">' . $fotoHtml . '</td>
                            <td style="vertical-align: middle;"><strong>' . htmlspecialchars($row["ss_e_tx_subgrupo"]) . '</strong><br><span class="text-muted">' . htmlspecialchars($row["ss_e_tx_item"] . ' / ' . $row["ss_e_tx_grupo"]) . '</span></td>
                            <td style="vertical-align: middle;">' . htmlspecialchars($row["ss_e_tx_item"]) . '</td>
                            <td style="vertical-align: middle;">' . htmlspecialchars($row["ss_e_tx_fabricante"] ?? "-") . '<br><small class="text-muted">' . htmlspecialchars($row["ss_e_tx_modelo"] ?? "-") . '</small></td>
                            <td style="text-align: center; vertical-align: middle;"><strong>' . htmlspecialchars($row["ss_e_tx_ca"] ?? "-") . '</strong></td>
                            <td style="text-align: center; vertical-align: middle;"><span class="badge badge-success" style="font-size: 14px; padding: 6px 10px; font-weight: bold;">' . $row["saldo"] . '</span>' . $variacoesHtml . '</td>
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
                            <td style="vertical-align: middle;"><strong>' . htmlspecialchars($rowMov["ss_e_tx_subgrupo"]) . '</strong><br><span class="text-muted">' . htmlspecialchars($rowMov["ss_e_tx_item"] . ' / ' . $rowMov["ss_e_tx_grupo"]) . '</span>' . (!empty($rowMov["ss_e_tx_variacao"]) ? '<br><span class="label label-info">Var: ' . htmlspecialchars($rowMov["ss_e_tx_variacao"]) . '</span>' : '') . '</td>
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

function detalhesEpiAjax() {
    ob_clean();
    $epi_id = isset($_GET["epi_id"]) ? (int)$_GET["epi_id"] : 0;
    if ($epi_id === 0) {
        echo '<div class="alert alert-danger">EPI não identificado.</div>';
        exit;
    }

    // 1. Dados cadastrais do EPI
    $sqlEpi = query("SELECT * FROM ss_epi WHERE ss_e_nb_id = {$epi_id} LIMIT 1");
    $epi = $sqlEpi ? mysqli_fetch_assoc($sqlEpi) : null;
    if (empty($epi)) {
        echo '<div class="alert alert-danger">EPI não encontrado.</div>';
        exit;
    }

    // 2. Saldo consolidado por empresa (CNPJ), com saldo separado por variação
    $sqlPorEmpresa = query("
        SELECT IFNULL(e.empr_nb_id, 0) AS empresa_id,
               IFNULL(e.empr_tx_nome, 'Matriz') AS empresa_nome,
               IFNULL(e.empr_tx_cnpj, '-') AS empresa_cnpj,
               agg.total_entradas,
               agg.total_saidas,
               agg.saldo,
               agg.valor_total,
               v.variacoes_saldo
        FROM (
            SELECT IFNULL(ss_e_nb_empresa_id, 0) AS empresa_id,
                   SUM(CASE WHEN ss_e_tx_tipo = 'entrada' THEN ss_e_nb_quantidade ELSE 0 END) AS total_entradas,
                   SUM(CASE WHEN ss_e_tx_tipo <> 'entrada' THEN ss_e_nb_quantidade ELSE 0 END) AS total_saidas,
                   SUM(CASE WHEN ss_e_tx_tipo = 'entrada' THEN ss_e_nb_quantidade ELSE -ss_e_nb_quantidade END) AS saldo,
                   SUM(CASE WHEN ss_e_tx_tipo = 'entrada' THEN IFNULL(ss_e_db_valor_total, 0) ELSE -IFNULL(ss_e_db_valor_total, 0) END) AS valor_total
            FROM ss_epi_estoque
            WHERE ss_e_nb_epi_id = {$epi_id}
            GROUP BY empresa_id
        ) agg
        LEFT JOIN (
            SELECT empresa_id,
                   GROUP_CONCAT(CONCAT(IFNULL(variacao, ''), ':', saldo_var) SEPARATOR '|') AS variacoes_saldo
            FROM (
                SELECT IFNULL(ss_e_nb_empresa_id, 0) AS empresa_id,
                       ss_e_tx_variacao AS variacao,
                       SUM(CASE WHEN ss_e_tx_tipo = 'entrada' THEN ss_e_nb_quantidade ELSE -ss_e_nb_quantidade END) AS saldo_var
                FROM ss_epi_estoque
                WHERE ss_e_nb_epi_id = {$epi_id} AND IFNULL(ss_e_tx_variacao, '') <> ''
                GROUP BY ss_e_nb_epi_id, empresa_id, variacao
            ) sub
            GROUP BY empresa_id
        ) v ON v.empresa_id = agg.empresa_id
        LEFT JOIN empresa e ON agg.empresa_id = e.empr_nb_id
        ORDER BY agg.saldo DESC, empresa_nome ASC
    ");

    // 3. Variações com saldo
    $sqlVariacoes = query("
        SELECT ss_e_tx_variacao,
               SUM(CASE WHEN ss_e_tx_tipo = 'entrada' THEN ss_e_nb_quantidade ELSE -ss_e_nb_quantidade END) AS saldo_var
        FROM ss_epi_estoque
        WHERE ss_e_nb_epi_id = {$epi_id} AND IFNULL(ss_e_tx_variacao, '') <> ''
        GROUP BY ss_e_tx_variacao
        ORDER BY ss_e_tx_variacao ASC
    ");

    // 4. Acumula totais
    $totalSaldo = 0;
    $totalValor = 0;
    $totalEntradas = 0;
    $totalSaidas = 0;
    $empresasHtml = "";
    if ($sqlPorEmpresa) {
        while ($rowEmp = mysqli_fetch_assoc($sqlPorEmpresa)) {
            $totalSaldo += (int)$rowEmp["saldo"];
            $totalValor += (float)$rowEmp["valor_total"];
            $totalEntradas += (int)$rowEmp["total_entradas"];
            $totalSaidas += (int)$rowEmp["total_saidas"];

            $cnpj = trim($rowEmp["empresa_cnpj"] ?? "-");
            if (preg_match('/^\d{14}$/', $cnpj)) {
                $cnpj = substr($cnpj, 0, 2) . "." . substr($cnpj, 2, 3) . "." . substr($cnpj, 5, 3) . "/" . substr($cnpj, 8, 4) . "-" . substr($cnpj, 12, 2);
            }

            $badgeSaldo = 'badge-warning';
            if ((int)$rowEmp["saldo"] <= 0) {
                $badgeSaldo = 'badge-danger';
            } elseif ((int)$rowEmp["saldo"] <= 5) {
                $badgeSaldo = 'badge-warning';
            } else {
                $badgeSaldo = 'badge-success';
            }

            // Badges do saldo por variação desta empresa
            $variacaoBadges = "";
            if (!empty($rowEmp["variacoes_saldo"])) {
                foreach (explode("|", $rowEmp["variacoes_saldo"]) as $varItem) {
                    $partes = explode(":", $varItem, 2);
                    $varNome = $partes[0] ?? "";
                    $varSaldo = (int)($partes[1] ?? 0);
                    $varBadge = 'label-info';
                    if ($varSaldo <= 0) {
                        $varBadge = 'label-danger';
                    } elseif ($varSaldo <= 5) {
                        $varBadge = 'label-warning';
                    }
                    $variacaoBadges .= '<span class="label ' . $varBadge . '" style="font-size: 11px; margin: 2px; display: inline-block;">' . htmlspecialchars($varNome) . ': <strong>' . $varSaldo . '</strong></span>';
                }
            }
            if (empty($variacaoBadges)) {
                $variacaoBadges = '<span class="text-muted">-</span>';
            }

            $empresasHtml .= '
            <tr>
                <td style="vertical-align: middle;"><strong>' . htmlspecialchars($rowEmp["empresa_nome"]) . '</strong><br><small class="text-muted">CNPJ: ' . htmlspecialchars($cnpj) . '</small></td>
                <td style="vertical-align: middle; white-space: nowrap;">' . $variacaoBadges . '</td>
                <td style="text-align: center; vertical-align: middle;"><span class="label label-sm label-success" style="font-weight: bold;">+' . (int)$rowEmp["total_entradas"] . '</span></td>
                <td style="text-align: center; vertical-align: middle;"><span class="label label-sm label-danger" style="font-weight: bold;">-' . (int)$rowEmp["total_saidas"] . '</span></td>
                <td style="text-align: center; vertical-align: middle;"><span class="badge ' . $badgeSaldo . '" style="font-size: 13px; padding: 5px 9px; font-weight: bold;">' . (int)$rowEmp["saldo"] . '</span></td>
                <td style="text-align: right; vertical-align: middle; white-space: nowrap;">R$ ' . number_format((float)$rowEmp["valor_total"], 2, ",", ".") . '</td>
            </tr>';
        }
    }
    if (empty($empresasHtml)) {
        $empresasHtml = '<tr><td colspan="6" class="text-center" style="padding: 20px; font-style: italic; color: #777;">Nenhuma movimentação registrada para este EPI.</td></tr>';
    }

    // Variações
    $variacoesHtml = "";
    if ($sqlVariacoes) {
        while ($rowVar = mysqli_fetch_assoc($sqlVariacoes)) {
            $variacoesHtml .= '<span class="label label-info" style="font-size: 11px; margin: 2px; display: inline-block;">' . htmlspecialchars($rowVar["ss_e_tx_variacao"]) . ': <strong>' . (int)$rowVar["saldo_var"] . '</strong></span>';
        }
    }
    if (empty($variacoesHtml)) {
        $variacoesHtml = '<span class="text-muted">Sem variações cadastradas para este item.</span>';
    }

    // Alertas (criatividade: CA vencido, validade do EPI e estoque baixo)
    $alertsHtml = "";
    $hoje = date("Y-m-d");
    if (!empty($epi["ss_e_tx_validade_ca"]) && $epi["ss_e_tx_validade_ca"] != "0000-00-00" && $epi["ss_e_tx_validade_ca"] < $hoje) {
        $alertsHtml .= '<div class="alert alert-danger" style="margin-bottom: 8px; padding: 8px 12px; font-size: 12px;"><i class="fa fa-exclamation-triangle"></i> <strong>CA vencido em ' . date("d/m/Y", strtotime($epi["ss_e_tx_validade_ca"])) . '!</strong> Este EPI não pode ser utilizado legalmente até a renovação do Certificado de Aprovação.</div>';
    }
    if (!empty($epi["ss_e_tx_validade_epi"]) && $epi["ss_e_tx_validade_epi"] != "0000-00-00" && $epi["ss_e_tx_validade_epi"] < $hoje) {
        $alertsHtml .= '<div class="alert alert-warning" style="margin-bottom: 8px; padding: 8px 12px; font-size: 12px;"><i class="fa fa-clock-o"></i> <strong>Validade do EPI expirada em ' . date("d/m/Y", strtotime($epi["ss_e_tx_validade_epi"])) . '.</strong> Verifique a necessidade de descarte/renovação do lote.</div>';
    }
    if ($totalSaldo <= 5) {
        $alertsHtml .= '<div class="alert alert-warning" style="margin-bottom: 8px; padding: 8px 12px; font-size: 12px;"><i class="fa fa-exclamation-circle"></i> <strong>Estoque baixo:</strong> restam apenas ' . $totalSaldo . ' unidade(s). Considere realizar uma nova entrada.</div>';
    }

    // Foto
    $fotoHtml = "";
    if (!empty($epi["ss_e_tx_foto"])) {
        $paths = array_filter(explode(",", $epi["ss_e_tx_foto"]));
        $primeira = reset($paths);
        if (!empty($primeira)) {
            $src = ss_resolve_foto_url($primeira);
            if (!empty($src)) {
                $fotoHtml = '<img src="' . $src . '" style="max-height: 110px; max-width: 110px; border-radius: 8px; border: 1px solid #ddd; object-fit: cover; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">';
            }
        }
    }

    echo '
    <div class="row" style="margin-bottom: 15px;">
        <div class="col-sm-2 text-center">' . ($fotoHtml ?: '<div style="width: 110px; height: 110px; border-radius: 8px; border: 1px dashed #ccc; display: inline-flex; align-items: center; justify-content: center; color: #aaa;"><i class="fa fa-hard-hat fa-3x"></i></div>') . '</div>
        <div class="col-sm-10">
            <h4 style="margin: 0 0 4px 0; font-weight: bold;">' . htmlspecialchars($epi["ss_e_tx_subgrupo"] ?: "EPI") . '</h4>
            <span class="text-muted">' . htmlspecialchars($epi["ss_e_tx_item"] ?? "") . (!empty($epi["ss_e_tx_grupo"]) ? ' > ' . htmlspecialchars($epi["ss_e_tx_grupo"]) : '') . '</span>
            <br><br>
            <table class="table table-condensed" style="margin-bottom: 0;">
                <tr>
                    <td style="border: none; padding: 2px 8px 2px 0;"><strong>Fabricante:</strong></td><td style="border: none; padding: 2px 8px;">' . htmlspecialchars($epi["ss_e_tx_fabricante"] ?? "-") . '</td>
                    <td style="border: none; padding: 2px 8px 2px 20px;"><strong>Modelo:</strong></td><td style="border: none; padding: 2px 8px;">' . htmlspecialchars($epi["ss_e_tx_modelo"] ?? "-") . '</td>
                </tr>
                <tr>
                    <td style="border: none; padding: 2px 8px 2px 0;"><strong>CA:</strong></td><td style="border: none; padding: 2px 8px;">' . htmlspecialchars($epi["ss_e_tx_ca"] ?? "-") . '</td>
                    <td style="border: none; padding: 2px 8px 2px 20px;"><strong>Validade do CA:</strong></td><td style="border: none; padding: 2px 8px;">' . (!empty($epi["ss_e_tx_validade_ca"]) && $epi["ss_e_tx_validade_ca"] != "0000-00-00" ? date("d/m/Y", strtotime($epi["ss_e_tx_validade_ca"])) : "-") . '</td>
                </tr>
                <tr>
                    <td style="border: none; padding: 2px 8px 2px 0;"><strong>Validade EPI:</strong></td><td style="border: none; padding: 2px 8px;">' . (!empty($epi["ss_e_tx_validade_epi"]) && $epi["ss_e_tx_validade_epi"] != "0000-00-00" ? date("d/m/Y", strtotime($epi["ss_e_tx_validade_epi"])) : "-") . '</td>
                    <td style="border: none; padding: 2px 8px 2px 20px;"><strong>Vida útil:</strong></td><td style="border: none; padding: 2px 8px;">' . (int)($epi["ss_e_nb_vida_util"] ?? 0) . ' dias</td>
                </tr>
                ' . (!empty($epi["ss_e_tx_descricao"]) ? '<tr><td colspan="4" style="border: none; padding: 4px 8px 2px 0;"><em>' . htmlspecialchars($epi["ss_e_tx_descricao"]) . '</em></td></tr>' : '') . '
            </table>
        </div>
    </div>

    ' . $alertsHtml . '

    <div class="row" style="margin-bottom: 15px;">
        <div class="col-sm-3">
            <div style="background: #f8f9fa; border-radius: 6px; padding: 10px; text-align: center; border: 1px solid #e4e7eb;">
                <div style="font-size: 20px; font-weight: 800; color: ' . ($totalSaldo <= 5 ? "#e35b5a" : "#32c5d2") . ';">' . $totalSaldo . '</div>
                <div style="font-size: 11px; color: #777; text-transform: uppercase; font-weight: bold;">Saldo total</div>
            </div>
        </div>
        <div class="col-sm-3">
            <div style="background: #f8f9fa; border-radius: 6px; padding: 10px; text-align: center; border: 1px solid #e4e7eb;">
                <div style="font-size: 20px; font-weight: 800; color: #5cb85c;">+' . $totalEntradas . '</div>
                <div style="font-size: 11px; color: #777; text-transform: uppercase; font-weight: bold;">Entradas</div>
            </div>
        </div>
        <div class="col-sm-3">
            <div style="background: #f8f9fa; border-radius: 6px; padding: 10px; text-align: center; border: 1px solid #e4e7eb;">
                <div style="font-size: 20px; font-weight: 800; color: #d9534f;">-' . $totalSaidas . '</div>
                <div style="font-size: 11px; color: #777; text-transform: uppercase; font-weight: bold;">Saídas</div>
            </div>
        </div>
        <div class="col-sm-3">
            <div style="background: #f8f9fa; border-radius: 6px; padding: 10px; text-align: center; border: 1px solid #e4e7eb;">
                <div style="font-size: 20px; font-weight: 800; color: #337ab7;">R$ ' . number_format($totalValor, 2, ",", ".") . '</div>
                <div style="font-size: 11px; color: #777; text-transform: uppercase; font-weight: bold;">Valor em estoque</div>
            </div>
        </div>
    </div>

    <h5 style="font-weight: bold; margin-top: 0;"><i class="fa fa-building"></i> Saldo por Empresa (CNPJ)</h5>
    <div class="table-responsive" style="margin-bottom: 20px;">
        <table class="table table-striped table-bordered table-hover">
            <thead>
                <tr style="background-color: #f9f9f9;">
                    <th>Empresa</th>
                    <th>Saldo por Variação</th>
                    <th style="text-align: center;">Entradas</th>
                    <th style="text-align: center;">Saídas</th>
                    <th style="text-align: center;">Saldo</th>
                    <th style="text-align: right;">Valor total</th>
                </tr>
            </thead>
            <tbody>' . $empresasHtml . '</tbody>
        </table>
    </div>

    <h5 style="font-weight: bold;"><i class="fa fa-tags"></i> Variações</h5>
    <div style="margin-bottom: 20px;">' . $variacoesHtml . '</div>
    ';
    exit;
}

function index() {
    cabecalho("Controle de Estoque de EPI");
    
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
        #result tbody tr {
            cursor: pointer;
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
    // Filtro de Empresa sempre visível (mesmo sem filiais)
    $fields[] = combo("Empresa", "busca_filial", $_POST["busca_filial"] ?? "", 2, $empresaOptions);
    $fields[] = combo("Status", "busca_status", $_POST["busca_status"] ?? "ativo", 1, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]);
    $fields[] = combo("Visualização", "busca_visao", $_POST["busca_visao"] ?? "saldo", 2, ["saldo" => "Saldo Atual por EPI", "mov" => "Histórico de Movimentações"]);

    $buttons = [];
    $buttons[] = botao("Buscar", "index");
    $buttons[] = '<a href="cadastrar_epi_estoque.php" class="btn btn-primary"><i class="fa fa-plus"></i> Cadastrar Item no Estoque</a>';
    $buttons[] = '<a href="movimentacao_estoque.php" class="btn btn-success"><i class="fa fa-exchange"></i> Lançar Movimentação Estoque</a>';
    $buttons[] = '<a href="devolucao_epi.php" class="btn btn-warning"><i class="fa fa-arrow-circle-left"></i> Gestão de Devoluções</a>';
    $buttons[] = '<a href="mapa_epi.php" class="btn btn-info"><i class="fa fa-sitemap"></i> Mapa de EPIs</a>';

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
            "VARIAÇÃO"          => "ss_e_tx_variacao",
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
                        SELECT est.ss_e_nb_id, est.ss_e_nb_epi_id, epi.ss_e_tx_grupo, CONCAT(IFNULL(epi.ss_e_tx_subgrupo, ''), ' - CA: ', IFNULL(epi.ss_e_tx_ca, 'N/A')) AS ss_e_tx_subgrupo, epi.ss_e_tx_item, est.ss_e_tx_variacao, est.ss_e_tx_tipo, est.ss_e_nb_quantidade, est.ss_e_db_valor_unitario, est.ss_e_db_valor_total, est.ss_e_tx_motivo, est.ss_e_tx_data, est.ss_e_tx_fornecedor,
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
                      
        echo gridDinamico("tabelaHistoricoMov", $gridFields, $camposBusca, $queryBase, "
            var funcoesInternas = function(){
                // Destaque do EPI (coluna EPI, índice 5 — ID, Código, Empresa, Usuário, Grupo, EPI, Descrição...)
                $('#result tbody tr').each(function() {
                    $(this).find('td').eq(5).css({ 'font-weight': 'bold', 'color': '#337ab7' });
                });
            };
        ");
    } else {
        // Exibição do saldo consolidado (Agrupado por EPI usando derived table/subquery para evitar erros de GROUP BY no gridDinamico)
        $gridFields = [
            "CÓDIGO"       => "ss_e_nb_id",
            "FOTO"         => "ss_e_tx_foto",
            "GRUPO"        => "ss_e_tx_grupo",
            "EPI"          => "ss_e_tx_subgrupo",
            "DESCRIÇÃO"    => "ss_e_tx_item",
            "FABRICANTE"   => "ss_e_tx_fabricante",
            "MODELO"       => "ss_e_tx_modelo",
            "CA"           => "ss_e_tx_ca",
            "STATUS"       => "ss_e_tx_status",
            "SALDO ATUAL"  => "saldo",
            "VARIAÇÕES"    => "variacoes_detalhe"
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
        $condVar = "";
        $joinCondSaldo = "";
        if (!empty($busca_filial)) {
            $joinCond = " AND est.ss_e_nb_empresa_id = " . (int)$busca_filial;
            $condVar = " AND ss_e_nb_empresa_id = " . (int)$busca_filial;
            $joinCondSaldo = " AND est2.ss_e_nb_empresa_id = " . (int)$busca_filial;
        }

        $queryBase = "SELECT * FROM (
                        SELECT epi.ss_e_nb_id, epi.ss_e_tx_foto, epi.ss_e_tx_grupo, CONCAT(IFNULL(epi.ss_e_tx_subgrupo, ''), ' - CA: ', IFNULL(epi.ss_e_tx_ca, 'N/A')) AS ss_e_tx_subgrupo, epi.ss_e_tx_item, epi.ss_e_tx_fabricante, epi.ss_e_tx_modelo, epi.ss_e_tx_ca, epi.ss_e_tx_status, epi.ss_e_tx_cadastro_tipo,
                               IFNULL((SELECT SUM(CASE WHEN est2.ss_e_tx_tipo = 'entrada' THEN est2.ss_e_nb_quantidade ELSE -est2.ss_e_nb_quantidade END)
                                       FROM ss_epi_estoque est2
                                       WHERE est2.ss_e_nb_epi_id = epi.ss_e_nb_id {$joinCondSaldo}), 0) AS saldo,
                               IFNULL(GROUP_CONCAT(DISTINCT CONCAT(IFNULL(v.variacao, ''), ': ', v.saldo_var) SEPARATOR ' | '), '') AS variacoes_detalhe
                        FROM ss_epi epi 
                        LEFT JOIN (SELECT ss_e_nb_epi_id, ss_e_tx_variacao AS variacao,
                                          SUM(CASE WHEN ss_e_tx_tipo = 'entrada' THEN ss_e_nb_quantidade ELSE -ss_e_nb_quantidade END) AS saldo_var
                                   FROM ss_epi_estoque
                                   WHERE IFNULL(ss_e_tx_variacao, '') <> '' {$condVar}
                                   GROUP BY ss_e_nb_epi_id, ss_e_tx_variacao) v ON v.ss_e_nb_epi_id = epi.ss_e_nb_id
                        WHERE epi.ss_e_tx_cadastro_tipo = 'estoque'
                        GROUP BY epi.ss_e_nb_id
                      ) AS epi";

        $gridFields["actions"] = [
            '<span class="fa fa-eye acao-detalhes-epi-est" title="Ver detalhes / saldo por CNPJ" style="color:#5bc0de; cursor:pointer; font-size:16px; margin-right:8px;"></span>',
            '<span class="fa fa-edit acao-editar-epi-est" title="Alterar" style="color:#337ab7; cursor:pointer; font-size:16px; margin-right:8px;"></span>',
            '<span class="fa fa-ban acao-inativar-epi-est" title="Inativar/Ativar" style="color:#f0ad4e; cursor:pointer; font-size:16px; margin-right:8px;"></span>',
            '<span class="fa fa-trash acao-excluir-epi-est" title="Excluir" style="color:#d9534f; cursor:pointer; font-size:16px;"></span>'
        ];

        $jsAcoes = '
            var funcoesInternas = function(){
                // Destaque do EPI (coluna EPI, índice 3 — Código, Foto, Grupo, EPI, Descrição...)
                $("#result tbody tr").each(function() {
                    $(this).find("td").eq(3).css({ "font-weight": "bold", "color": "#337ab7" });
                });

                // O clique na linha apenas destaca (destaque global do grid);
                // o modal de detalhes abre somente pelo ícone de olho abaixo.

                // Bind detalhes click (ícone de olho)
                $(".acao-detalhes-epi-est").off("click").on("click", function(event) {
                    event.stopPropagation();
                    var id = $(this).closest("tr").attr("data-row-id");
                    abrirDetalhesEpi(id);
                });

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

    // Modal de detalhes do item de EPI (disponível sempre, com ou sem filiais)
    echo '
    <div class="modal fade" id="modalDetalhesEpi" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 6px;">
                <div class="modal-header" style="background-color: #f5f5f5; border-bottom: 1px solid #ddd; border-top-left-radius: 6px; border-top-right-radius: 6px;">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                    <h4 class="modal-title" style="font-weight: bold; color: #333;"><i class="fa fa-hard-hat"></i> Detalhes do Item de EPI</h4>
                </div>
                <div class="modal-body" id="modalDetalhesEpiBody" style="max-height: 600px; overflow-y: auto; padding: 20px;">
                    <div class="text-center" style="padding: 30px;"><i class="fa fa-spinner fa-spin fa-2x"></i> Carregando detalhes do item...</div>
                </div>
                <div class="modal-footer" style="background-color: #f5f5f5; border-top: 1px solid #ddd; border-bottom-left-radius: 6px; border-bottom-right-radius: 6px;">
                    <button type="button" class="btn btn-success btn-sm" onclick="baixarCsvDetalhesEpi()" title="Baixar os dados dos detalhes em CSV"><i class="glyphicon glyphicon-download-alt"></i> Baixar CSV</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="baixarPdfDetalhesEpi()" title="Baixar os dados dos detalhes em PDF"><i class="glyphicon glyphicon-file"></i> Baixar PDF</button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function abrirDetalhesEpi(epiId) {
        if (!epiId) return;
        $("#modalDetalhesEpiBody").html("<div class=\'text-center\' style=\'padding: 30px;\'><i class=\'fa fa-spinner fa-spin fa-2x\'></i> Carregando detalhes do item...</div>");
        $("#modalDetalhesEpi").modal("show");
        $.ajax({
            url: "estoque_epi.php?acao=detalhesEpiAjax",
            type: "GET",
            data: { epi_id: epiId },
            success: function(response) {
                $("#modalDetalhesEpiBody").html(response);
            },
            error: function() {
                $("#modalDetalhesEpiBody").html("<div class=\'alert alert-danger\'>Ocorreu um erro ao carregar os detalhes do item. Tente novamente.</div>");
            }
        });
    }

    function _ss_limparTextoExport(html) {
        var div = document.createElement("div");
        div.innerHTML = html || "";
        return (div.textContent || div.innerText || "").trim();
    }

    function _ss_sanitizarNomeArquivo(nome) {
        return String(nome || "epi").replace(/[^a-zA-Z0-9-_]+/g, "_").substring(0, 80);
    }

    function _ss_tabelaParaLinhas(tabela) {
        var linhas = [];
        var trs = tabela.querySelectorAll("tr");
        for (var i = 0; i < trs.length; i++) {
            var linha = [];
            var celulas = trs[i].querySelectorAll("th, td");
            for (var j = 0; j < celulas.length; j++) {
                var clone = celulas[j].cloneNode(true);
                var removiveis = clone.querySelectorAll("img, i, .glyphicon, .fa, button, a");
                for (var k = 0; k < removiveis.length; k++) removiveis[k].remove();
                linha.push(_ss_limparTextoExport(clone.innerHTML));
            }
            linhas.push(linha);
        }
        return linhas;
    }

    function baixarCsvDetalhesEpi() {
        var body = document.getElementById("modalDetalhesEpiBody");
        if (!body) return;

        var h4 = body.querySelector("h4");
        var tituloEpi = h4 ? _ss_limparTextoExport(h4.textContent) : "EPI";

        var csv = "\uFEFFsep=;\r\n";
        csv += "Detalhes do EPI;\"" + tituloEpi.replace(/"/g, "\"\"") + "\"\r\n\r\n";

        var cards = body.querySelectorAll(".col-sm-3");
        var resumo = [];
        for (var i = 0; i < cards.length; i++) {
            var divs = cards[i].querySelectorAll("div");
            if (divs.length >= 2) {
                resumo.push([
                    _ss_limparTextoExport(divs[1].textContent),
                    _ss_limparTextoExport(divs[0].textContent)
                ]);
            }
        }
        if (resumo.length > 0) {
            csv += "Resumo\r\n";
            for (var r = 0; r < resumo.length; r++) {
                csv += "\"" + resumo[r][0].replace(/"/g, "\"\"") + "\";\"" + resumo[r][1].replace(/"/g, "\"\"") + "\"\r\n";
            }
            csv += "\r\n";
        }

        var tabela = body.querySelector("table.table-striped");
        if (tabela) {
            var linhas = _ss_tabelaParaLinhas(tabela);
            if (linhas.length > 0) {
                csv += "Saldo por Empresa (CNPJ)\r\n";
                for (var l = 0; l < linhas.length; l++) {
                    var cel = [];
                    for (var c = 0; c < linhas[l].length; c++) {
                        cel.push("\"" + linhas[l][c].replace(/"/g, "\"\"") + "\"");
                    }
                    csv += cel.join(";") + "\r\n";
                }
                csv += "\r\n";
            }
        }

        var h5s = body.querySelectorAll("h5");
        for (var h = 0; h < h5s.length; h++) {
            if (h5s[h].textContent.indexOf("Varia") >= 0) {
                var proximo = h5s[h].nextElementSibling;
                if (proximo) {
                    csv += "Variações\r\n";
                    var spans = proximo.querySelectorAll(".label");
                    for (var s = 0; s < spans.length; s++) {
                        csv += "\"" + _ss_limparTextoExport(spans[s].textContent).replace(/"/g, "\"\"") + "\"\r\n";
                    }
                }
            }
        }

        var blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        var link = document.createElement("a");
        var url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", "detalhes_epi_" + _ss_sanitizarNomeArquivo(tituloEpi) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function baixarPdfDetalhesEpi() {
        var body = document.getElementById("modalDetalhesEpiBody");
        if (!body) return;

        var h4 = body.querySelector("h4");
        var tituloEpi = h4 ? _ss_limparTextoExport(h4.textContent) : "EPI";

        var html = "<style>"
            + "body { font-family: Arial, sans-serif; font-size: 8pt; }"
            + "table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }"
            + "th, td { border: 1px solid #000; padding: 3px; text-align: left; }"
            + "th { font-weight: bold; background-color: #f2f2f2; font-size: 8pt; white-space: nowrap; }"
            + "td { font-size: 7pt; }"
            + "</style>";

        var cards = body.querySelectorAll(".col-sm-3");
        if (cards.length > 0) {
            html += "<table><thead><tr>";
            for (var i = 0; i < cards.length; i++) {
                var divs = cards[i].querySelectorAll("div");
                var rotulo = divs.length >= 2 ? _ss_limparTextoExport(divs[1].textContent) : "";
                html += "<th>" + rotulo + "</th>";
            }
            html += "</tr></thead><tbody><tr>";
            for (var j = 0; j < cards.length; j++) {
                var dv = cards[j].querySelectorAll("div");
                var valor = dv.length >= 2 ? _ss_limparTextoExport(dv[0].textContent) : "";
                html += "<td>" + valor + "</td>";
            }
            html += "</tr></tbody></table><br>";
        }

        var tabela = body.querySelector("table.table-striped");
        if (tabela) {
            var clone = tabela.cloneNode(true);
            var removiveis = clone.querySelectorAll("img, i, .glyphicon, .fa, button, a");
            for (var m = 0; m < removiveis.length; m++) removiveis[m].remove();
            html += clone.outerHTML + "<br>";
        }

        var h5s = body.querySelectorAll("h5");
        for (var h = 0; h < h5s.length; h++) {
            if (h5s[h].textContent.indexOf("Varia") >= 0) {
                var proximo = h5s[h].nextElementSibling;
                if (proximo) html += "<br><b>Variações</b><br>" + proximo.outerHTML;
            }
        }

        var form = document.createElement("form");
        form.method = "POST";
        form.action = (typeof urlGridPdf !== "undefined" && urlGridPdf) ? urlGridPdf : "./impressao/grid.php";
        form.target = "_blank";

        var inputTabela = document.createElement("input");
        inputTabela.type = "hidden";
        inputTabela.name = "tabela_html";
        inputTabela.value = html;
        form.appendChild(inputTabela);

        var selectIdEmpresa = document.getElementById("busca_filial")
            || document.querySelector("select[name=\"busca_filial\"]")
            || document.getElementById("empresa_id")
            || document.querySelector("select[name=\"empresa_id\"]");
        var id = selectIdEmpresa ? selectIdEmpresa.value : (typeof userEmpresaId !== "undefined" ? userEmpresaId : "");

        var IdEmpresa = document.createElement("input");
        IdEmpresa.type = "hidden";
        IdEmpresa.name = "IdEmpresa";
        IdEmpresa.value = id;
        form.appendChild(IdEmpresa);

        var paginaTitulo = document.createElement("input");
        paginaTitulo.type = "hidden";
        paginaTitulo.name = "paginaTitulo";
        paginaTitulo.value = tituloEpi;
        form.appendChild(paginaTitulo);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    </script>
    ';

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
