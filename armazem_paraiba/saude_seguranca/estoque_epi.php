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
        SELECT epi.ss_e_nb_id, epi.ss_e_tx_foto, epi.ss_e_tx_grupo, epi.ss_e_tx_subgrupo, epi.ss_e_tx_item, epi.ss_e_tx_fabricante, epi.ss_e_tx_modelo, epi.ss_e_tx_ca, epi.ss_e_tx_validade_ca,
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
               IFNULL(est.ss_e_tx_chave_nf, '-') AS chave_nf
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
                            <th style="text-align: center;">CA / Validade</th>
                            <th style="width: 100px; text-align: center;">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>';
                    
    $hasDetails = false;
    if ($sqlDetails) {
        while ($row = mysqli_fetch_assoc($sqlDetails)) {
            $hasDetails = true;
            $fotoHtml = ss_grid_foto_render($row["ss_e_tx_foto"]);
            $val_ca = !empty($row["ss_e_tx_validade_ca"]) ? date("d/m/Y", strtotime($row["ss_e_tx_validade_ca"])) : "-";
            echo '
                        <tr>
                            <td style="text-align: center; vertical-align: middle;">' . $fotoHtml . '</td>
                            <td style="vertical-align: middle;"><strong>' . htmlspecialchars($row["ss_e_tx_grupo"]) . '</strong><br><span class="text-muted">' . htmlspecialchars($row["ss_e_tx_subgrupo"]) . '</span></td>
                            <td style="vertical-align: middle;">' . htmlspecialchars($row["ss_e_tx_item"]) . '</td>
                            <td style="vertical-align: middle;">' . htmlspecialchars($row["ss_e_tx_fabricante"] ?? "-") . '<br><small class="text-muted">' . htmlspecialchars($row["ss_e_tx_modelo"] ?? "-") . '</small></td>
                            <td style="text-align: center; vertical-align: middle;"><strong>' . htmlspecialchars($row["ss_e_tx_ca"] ?? "-") . '</strong><br><small class="text-muted">val: ' . $val_ca . '</small></td>
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
                            <th>NF / Recebimento</th>
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
                            <td style="vertical-align: middle;">NF: ' . htmlspecialchars($rowMov["chave_nf"]) . '<br><small class="text-muted">receb: ' . $rowMov["data_receb_fmt"] . '</small></td>
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

function parseBrValor($val) {
    if (empty($val)) return null;
    $val = str_replace(['R$', ' '], '', $val);
    if (strpos($val, ',') !== false) {
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
    }
    return floatval($val);
}

function lancarEstoque() {
    $camposObrig = [
        "epi_id" => "EPI",
        "quantidade" => "Quantidade",
        "tipo" => "Tipo"
    ];
    
    $errorMsg = conferirCamposObrig($camposObrig, $_POST);
    if (!empty($errorMsg)) {
        set_status("ERRO: {$errorMsg}");
        registrarMovimentacao();
        exit;
    }

    $epi_id     = (int)$_POST["epi_id"];
    $quantidade = (int)$_POST["quantidade"];
    $tipo       = $_POST["tipo"];
    $motivo     = $_POST["motivo"] ?? "";
    $valor_unitario = parseBrValor($_POST["valor_unitario"] ?? "");
    $valor_total = parseBrValor($_POST["valor_total"] ?? "");

    $empresa_id = isset($_POST["empresa_id"]) ? (int)$_POST["empresa_id"] : 0;
    if ($empresa_id === 0) {
        $empresa_id = null; // Matriz
    }

    $data_recebimento = !empty($_POST["data_recebimento"]) ? $_POST["data_recebimento"] : null;
    $chave_nf = !empty($_POST["chave_nf"]) ? $_POST["chave_nf"] : null;
    $fornecedor = !empty($_POST["fornecedor"]) ? $_POST["fornecedor"] : null;

    if ($epi_id <= 0) {
        $_POST["errorFields"][] = "epi_id";
        set_status("ERRO: Selecione um EPI válido.");
        registrarMovimentacao();
        exit;
    }

    if ($quantidade <= 0) {
        $_POST["errorFields"][] = "quantidade";
        set_status("ERRO: A quantidade deve ser maior que zero.");
        registrarMovimentacao();
        exit;
    }

    // Regra de negócio: impede saída maior que o saldo em estoque
    if ($tipo === 'saida') {
        $saldoAtual = obterSaldoEstoque($epi_id, $empresa_id);
        if ($quantidade > $saldoAtual) {
            $_POST["errorFields"][] = "quantidade";
            set_status("ERRO: Estoque insuficiente. Saldo atual: {$saldoAtual}.");
            registrarMovimentacao();
            exit;
        }
    }

    $sucesso = registrarMovimentacaoEstoque($epi_id, $quantidade, $tipo, $motivo, $valor_unitario, $valor_total, "", $data_recebimento, $chave_nf, $empresa_id, $fornecedor);
    if ($sucesso) {
        set_status("Movimentação registrada com sucesso!");
    } else {
        set_status("ERRO ao registrar movimentação.");
    }

    index();
    exit;
}

function lancarEstoqueLoteAjax() {
    $lotes = json_decode($_POST["lotes"] ?? "[]", true);
    $sucessos = 0;
    $erros = [];
    
    foreach ($lotes as $item) {
        $epi_id = (int)$item["epi_id"];
        $quantidade = (int)$item["quantidade"];
        $tipo = $item["tipo"];
        $motivo = $item["motivo"] ?? "";
        $valor_unitario = parseBrValor($item["valor_unitario"] ?? "");
        $valor_total = parseBrValor($item["valor_total"] ?? "");
        $data_recebimento = !empty($item["data_recebimento"]) ? $item["data_recebimento"] : null;
        $chave_nf = !empty($item["chave_nf"]) ? $item["chave_nf"] : null;
        $fornecedor = !empty($item["fornecedor"]) ? $item["fornecedor"] : null;
        
        $empresa_id = !empty($item["empresa_id"]) ? (int)$item["empresa_id"] : null;
        if ($empresa_id === 0) {
            $empresa_id = null; // Matriz
        }
        
        if ($epi_id <= 0 || $quantidade <= 0) {
            $erros[] = "EPI inválido ou quantidade inválida.";
            continue;
        }
        
        if ($tipo === 'saida') {
            $saldoAtual = obterSaldoEstoque($epi_id, $empresa_id);
            if ($quantidade > $saldoAtual) {
                $erros[] = "Estoque insuficiente para saída do EPI ID {$epi_id}. Saldo atual: {$saldoAtual}.";
                continue;
            }
        }
        
        $sucesso = registrarMovimentacaoEstoque($epi_id, $quantidade, $tipo, $motivo, $valor_unitario, $valor_total, "", $data_recebimento, $chave_nf, $empresa_id, $fornecedor);
        if ($sucesso) {
            $sucessos++;
        } else {
            $erros[] = "Erro ao registrar movimentação para o EPI ID {$epi_id}.";
        }
    }
    
    ob_clean();
    echo json_encode([
        "status" => count($erros) === 0 ? "success" : "partial",
        "sucessos" => $sucessos,
        "erros" => $erros
    ]);
    exit;
}

function registrarMovimentacao() {
    cabecalho("Lançar Movimentação de Estoque");

    $sql = query("SELECT ss_e_nb_id, CONCAT(IFNULL(ss_e_tx_subgrupo, ''), ' - ', IFNULL(ss_e_tx_item, ''), ' - ', IFNULL(ss_e_tx_ca, 'N/A')) AS epi_nome 
                  FROM ss_epi 
                  WHERE ss_e_tx_status = 'ativo' AND ss_e_tx_cadastro_tipo = 'estoque'
                  ORDER BY ss_e_tx_subgrupo ASC, ss_e_tx_item ASC");
    $epiOptions = ["" => "Selecione o EPI"];
    if ($sql) {
        while ($row = mysqli_fetch_assoc($sql)) {
            $epiOptions[$row["ss_e_nb_id"]] = $row["epi_nome"];
        }
    }

    // Carregar todas as empresas ativas
    $sqlEmpresas = query("SELECT empr_nb_id, empr_tx_nome, empr_tx_cnpj FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
    $empresaOptions = ["" => "Selecione a Empresa"];
    if ($sqlEmpresas) {
        while ($rowEmp = mysqli_fetch_assoc($sqlEmpresas)) {
            $empresaOptions[$rowEmp["empr_nb_id"]] = $rowEmp["empr_tx_nome"] . " (CNPJ: " . $rowEmp["empr_tx_cnpj"] . ")";
        }
    }

    $empresasJsArr = [];
    foreach ($empresaOptions as $eid => $ename) {
        if (empty($eid)) continue;
        $cleanName = preg_replace('/ \(CNPJ: .+\)$/', '', $ename);
        $empresasJsArr[$eid] = $cleanName;
    }
    $jsEmpresas = json_encode($empresasJsArr);

    $campo_empresa  = combo("Empresa*", "empresa_id", $_POST["empresa_id"] ?? ($_SESSION["user_nb_empresa"] ?? "0"), 3, $empresaOptions, "id='empresa_id'");
    $campo_epi      = combo("EPI*", "epi_id", $_POST["epi_id"] ?? "", 4, $epiOptions);
    $campo_quant    = campo("Quantidade*", "quantidade", $_POST["quantidade"] ?? "", 2, "MASCARA_NUMERO");
    $campo_tipo     = combo("Tipo*", "tipo", $_POST["tipo"] ?? "entrada", 3, ["entrada" => "Entrada (Compra/Ajuste)", "saida" => "Saída (Descarte/Ajuste)"]);

    $campo_motivo   = campo("Motivo/Observação", "motivo", $_POST["motivo"] ?? "", 4, "", "maxlength='255'");
    $campo_valor_unitario = campo("Valor Unitário", "valor_unitario", $_POST["valor_unitario"] ?? "", 4, "MASCARA_VALOR");
    $campo_valor_total    = campo("Valor Total", "valor_total", $_POST["valor_total"] ?? "", 4, "MASCARA_VALOR");

    $campo_data_receb     = campo_data("Data de Recebimento", "data_recebimento", $_POST["data_recebimento"] ?? "", 4);
    $campo_chave_nf       = campo("Chave NF", "chave_nf", $_POST["chave_nf"] ?? "", 4, "", "maxlength='100'");
    $campo_fornecedor     = campo("Fornecedor", "fornecedor", $_POST["fornecedor"] ?? "", 4, "", "maxlength='255'");

    $buttons = [];
    $buttons[] = '<button type="button" class="btn btn-primary" id="btn_adicionar_item" onclick="adicionarItemALista()"><i class="fa fa-plus"></i> Adicionar Item à Lista</button>';
    $buttons[] = '<button type="button" class="btn btn-default" onclick="confirmarVoltar()"><i class="fa fa-arrow-left"></i> Voltar</button>';

    echo abre_form("Dados da Movimentação");
    echo linha_form([$campo_empresa, $campo_epi, $campo_quant, $campo_tipo]);
    echo linha_form([$campo_motivo, $campo_valor_unitario, $campo_valor_total]);
    echo linha_form([$campo_data_receb, $campo_chave_nf, $campo_fornecedor]);
    echo fecha_form($buttons);

    echo '<div id="container_listas_filiais" style="margin-top: 25px;"></div>';
    
    echo '
    <div id="container_acoes_globais" style="margin-top: 20px; display: none;">
        <div class="row">
            <div class="col-md-12 text-right">
                <button type="button" class="btn btn-success btn-lg" onclick="salvarTodasAsListas()"><i class="fa fa-save"></i> Salvar Todos os Lançamentos</button>
            </div>
        </div>
    </div>';

    echo "
    <script>
    var itensLote = {};
    var empresasNomes = " . $jsEmpresas . ";

    $(document).ready(function() {
        if (typeof $.fn.select2 === 'function') {
            $.fn.select2.defaults.set('theme', 'bootstrap');
            $('select[name=\"epi_id\"], select[name=\"empresa_id\"]').select2();
        }

        function calcularTotal() {
            let quant = parseInt($('#quantidade').val(), 10) || 0;
            let unit = parseBrFloat($('#valor_unitario').val());
            if (quant > 0 && unit > 0) {
                let total = quant * unit;
                let totalStr = formatBrFloat(total);
                
                if (typeof $('#valor_total').maskMoney === 'function') {
                    $('#valor_total').maskMoney('mask', total);
                } else {
                    $('#valor_total').val(totalStr);
                }
            }
        }

        function parseBrFloat(str) {
            if (!str) return 0;
            let clean = str.replace(/R\$\s?/, '').replace(/\s/g, '');
            if (clean.indexOf(',') !== -1) {
                clean = clean.replace(/\./g, '').replace(/,/g, '.');
            }
            return parseFloat(clean) || 0;
        }

        function formatBrFloat(num) {
            return num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        $('#quantidade, #valor_unitario').on('keyup change input', calcularTotal);
    });

    function adicionarItemALista() {
        var epiSelect = $('select[name=\"epi_id\"]');
        var epiId = epiSelect.val();
        var epiNome = epiSelect.find('option:selected').text();
        var quantidade = $('#quantidade').val();
        var tipo = $('select[name=\"tipo\"]').val();
        var motivo = $('#motivo').val();
        var valorUnitario = $('#valor_unitario').val();
        var valorTotal = $('#valor_total').val();
        var dataRecebimento = $('#data_recebimento').val();
        var chaveNf = $('#chave_nf').val();
        var fornecedor = $('#fornecedor').val();
        
        var empresaId = $('select[name=\"empresa_id\"]').val() || '0';
        
        if (!epiId) {
            alert('Por favor, selecione um EPI.');
            return;
        }
        if (!quantidade || parseInt(quantidade, 10) <= 0) {
            alert('Por favor, informe uma quantidade maior que zero.');
            return;
        }
        if (!empresaId) {
            alert('Por favor, selecione a Empresa.');
            return;
        }
        
        var item = {
            unique_id: new Date().getTime() + '_' + Math.random().toString(36).substr(2, 5),
            epi_id: epiId,
            epi_nome: epiNome,
            quantidade: parseInt(quantidade, 10),
            tipo: tipo,
            motivo: motivo,
            valor_unitario: valorUnitario,
            valor_total: valorTotal,
            data_recebimento: dataRecebimento,
            chave_nf: chaveNf,
            fornecedor: fornecedor,
            empresa_id: empresaId
        };
        
        if (!itensLote[empresaId]) {
            itensLote[empresaId] = [];
        }
        itensLote[empresaId].push(item);
        
        epiSelect.val('').trigger('change');
        $('#quantidade').val('');
        $('#valor_unitario').val('');
        $('#valor_total').val('');
        $('#motivo').val('');
        
        desenharListas();
    }

    function desenharListas() {
        var container = $('#container_listas_filiais');
        container.empty();
        
        var totalListas = 0;
        
        for (var empresaId in itensLote) {
            var itens = itensLote[empresaId];
            if (itens.length === 0) continue;
            
            totalListas++;
            var empresaNome = empresasNomes[empresaId] || ('Filial ID ' + empresaId);
            
            var panelHtml = '<div class=\"portlet box blue-hoki\" id=\"panel_empresa_' + empresaId + '\" style=\"margin-bottom: 20px;\">' +
                '<div class=\"portlet-title\">' +
                    '<div class=\"caption\">' +
                        '<i class=\"fa fa-shopping-cart\"></i> Lançamentos para: <strong>' + empresaNome + '</strong> (' + itens.length + ' item(ns))' +
                    '</div>' +
                    '<div class=\"actions\" style=\"display: inline-block; float: right; margin-top: 4px;\">' +
                        '<button type=\"button\" class=\"btn btn-default btn-sm\" style=\"background-color: #fff; color: #333;\" onclick=\"salvarListaEmpresa(\'' + empresaId + '\')\"><i class=\"fa fa-save\"></i> Salvar Esta Empresa</button>' +
                    '</div>' +
                '</div>' +
                '<div class=\"portlet-body\">' +
                    '<div class=\"table-responsive\">' +
                        '<table class=\"table table-striped table-bordered table-hover\">' +
                            '<thead>' +
                                '<tr>' +
                                    '<th>EPI</th>' +
                                    '<th style=\"text-align: center;\">Operação</th>' +
                                    '<th style=\"text-align: center; width: 80px;\">Qtd</th>' +
                                    '<th>Fornecedor</th>' +
                                    '<th>NF / Recebimento</th>' +
                                    '<th>Valor (Unit/Total)</th>' +
                                    '<th>Motivo</th>' +
                                    '<th style=\"width: 50px; text-align: center;\">Ações</th>' +
                                '</tr>' +
                            '</thead>' +
                            '<tbody>';
            
            for (var i = 0; i < itens.length; i++) {
                var it = itens[i];
                var badgeTipo = it.tipo === 'entrada'
                    ? '<span class=\"label label-sm label-success\">Entrada</span>'
                    : '<span class=\"label label-sm label-danger\">Saída</span>';
                    
                panelHtml += '<tr id=\"row_item_' + it.unique_id + '\">' +
                                '<td style=\"vertical-align: middle;\">' + it.epi_nome + '</td>' +
                                '<td style=\"text-align: center; vertical-align: middle;\">' + badgeTipo + '</td>' +
                                '<td style=\"text-align: center; font-weight: bold; vertical-align: middle;\">' + it.quantidade + '</td>' +
                                '<td style=\"vertical-align: middle;\">' + (it.fornecedor || '-') + '</td>' +
                                '<td style=\"vertical-align: middle;\">NF: ' + (it.chave_nf || '-') + '<br><small class=\"text-muted\">receb: ' + (it.data_recebimento || '-') + '</small></td>' +
                                '<td style=\"vertical-align: middle;\">U: ' + (it.valor_unitario || '-') + '<br>T: ' + (it.valor_total || '-') + '</td>' +
                                '<td style=\"font-style: italic; vertical-align: middle;\">' + (it.motivo || '-') + '</td>' +
                                '<td style=\"text-align: center; vertical-align: middle;\">' +
                                    '<button type=\"button\" class=\"btn btn-danger btn-xs\" onclick=\"removerItem(\'' + empresaId + '\', \'' + it.unique_id + '\')\"><i class=\"fa fa-trash\"></i></button>' +
                                '</td>' +
                            '</tr>';
            }
            
            panelHtml += '</tbody>' +
                        '</table>' +
                    '</div>' +
                '</div>' +
            '</div>';
            
            container.append(panelHtml);
        }
        
        if (totalListas > 0) {
            $('#container_acoes_globais').show();
        } else {
            $('#container_acoes_globais').hide();
        }
    }

    function removerItem(empresaId, uniqueId) {
        if (!itensLote[empresaId]) return;
        itensLote[empresaId] = itensLote[empresaId].filter(function(item) {
            return item.unique_id !== uniqueId;
        });
        desenharListas();
    }

    function salvarListaEmpresa(empresaId) {
        var itens = itensLote[empresaId];
        if (!itens || itens.length === 0) return;
        
        if (!confirm('Deseja salvar os lançamentos desta empresa/filial?')) return;
        
        enviarLotesAjax(itens, function() {
            delete itensLote[empresaId];
            desenharListas();
            alert('Lançamentos salvos com sucesso!');
        });
    }

    function salvarTodasAsListas() {
        var todosItens = [];
        for (var empresaId in itensLote) {
            todosItens = todosItens.concat(itensLote[empresaId]);
        }
        if (todosItens.length === 0) return;
        
        if (!confirm('Deseja salvar todos os lançamentos de todas as empresas/filiais?')) return;
        
        enviarLotesAjax(todosItens, function() {
            itensLote = {};
            desenharListas();
            alert('Todos os lançamentos salvos com sucesso!');
        });
    }

    function enviarLotesAjax(lotes, callbackSucesso) {
        $.ajax({
            url: 'estoque_epi.php?acao=lancarEstoqueLoteAjax',
            type: 'POST',
            data: { lotes: JSON.stringify(lotes) },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' || response.status === 'partial') {
                    if (response.erros && response.erros.length > 0) {
                        alert('Avisos durante a gravação:\\n\\n' + response.erros.join('\\n'));
                    }
                    callbackSucesso();
                } else {
                    alert('Erro ao salvar lançamentos.');
                }
            },
            error: function() {
                alert('Erro na comunicação com o servidor.');
            }
        });
    }

    function confirmarVoltar() {
        var temItens = false;
        for (var empId in itensLote) {
            if (itensLote[empId].length > 0) {
                temItens = true;
                break;
            }
        }
        
        if (temItens) {
            if (confirm('Você possui itens nas listas que ainda não foram salvos. Deseja realmente sair sem salvar?')) {
                window.location.href = 'estoque_epi.php';
            }
        } else {
            window.location.href = 'estoque_epi.php';
        }
    }
    </script>
    ";

    rodape();
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
        $_POST["busca_visao"] = "mov";
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
    $fields[] = combo("Visualização", "busca_visao", $_POST["busca_visao"] ?? "mov", 2, ["saldo" => "Saldo Atual por EPI", "mov" => "Histórico de Movimentações"]);

    $buttons = [];
    $buttons[] = botao("Buscar", "index");
    $buttons[] = botao("Cadastrar Item no Estoque", "modificarEpiEstoque", "", "", "", "", "btn btn-primary");
    $buttons[] = botao("Gerenciar Kits", "listarKits", "", "", "", "", "btn btn-info");
    $buttons[] = botao("Lançar Movimentação Estoque", "registrarMovimentacao", "", "", "", "", "btn btn-success");

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
            "CÓDIGO"      => "ss_e_nb_id",
            "FOTO"        => "ss_grid_foto_render(ss_e_tx_foto)",
            "GRUPO"       => "ss_e_tx_grupo",
            "EPI"         => "ss_e_tx_subgrupo",
            "DESCRIÇÃO"   => "ss_e_tx_item",
            "FABRICANTE"  => "ss_e_tx_fabricante",
            "MODELO"      => "ss_e_tx_modelo",
            "CA"          => "ss_e_tx_ca",
            "VALIDADE CA" => "ss_e_tx_validade_ca",
            "STATUS"      => "ss_e_tx_status",
            "SALDO ATUAL" => "saldo"
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
                        SELECT epi.ss_e_nb_id, epi.ss_e_tx_foto, epi.ss_e_tx_grupo, CONCAT(IFNULL(epi.ss_e_tx_subgrupo, ''), ' - CA: ', IFNULL(epi.ss_e_tx_ca, 'N/A')) AS ss_e_tx_subgrupo, epi.ss_e_tx_item, epi.ss_e_tx_fabricante, epi.ss_e_tx_modelo, epi.ss_e_tx_ca, epi.ss_e_tx_validade_ca, epi.ss_e_tx_status, epi.ss_e_tx_cadastro_tipo,
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
                    submitPost("", { acao: "modificarEpiEstoque", id: id });
                });

                // For each row, check status and customize the inativar/ativar icon
                $("#result tbody tr").each(function() {
                    var row = $(this);
                    var statusCell = row.find("td").eq(9); // STATUS is column index 9
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
                    var statusCell = row.find("td").eq(9);
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

function cadastrarEpiEstoque() {
    if (!empty($_POST["id"]) && empty($_POST["itens_json"])) {
        // Direct single-item update
        $id = (int)$_POST["id"];
        $validade_ca = !empty($_POST["validade_ca"]) ? $_POST["validade_ca"] : null;
        $vida_util = !empty($_POST["vida_util"]) ? (int)$_POST["vida_util"] : 0;
        
        $epi = [
            "ss_e_tx_grupo"         => $_POST["grupo"],
            "ss_e_tx_subgrupo"      => $_POST["subgrupo"],
            "ss_e_tx_item"          => $_POST["item"],
            "ss_e_tx_descricao"     => $_POST["descricao"],
            "ss_e_tx_fabricante"    => $_POST["fabricante"],
            "ss_e_tx_modelo"        => $_POST["modelo"] ?? "",
            "ss_e_tx_ca"            => $_POST["ca"],
            "ss_e_tx_validade_ca"   => $validade_ca,
            "ss_e_nb_vida_util"     => $vida_util,
            "ss_e_tx_status"        => $_POST["status"] ?? "ativo"
        ];
        
        atualizar("ss_epi", array_keys($epi), array_values($epi), $id);
        
        $fotos_mantidas = !empty($_POST["fotos_mantidas"]) ? $_POST["fotos_mantidas"] : "";
        $new_paths = [];
        if (!empty($_FILES["foto"]["name"][0])) {
            $allowed = ["image/jpeg", "image/png", "image/jpg"];
            $total_files = count($_FILES["foto"]["name"]);
            $dir_foto = "arquivos/epi/{$id}/";
            if (!is_dir($dir_foto)) {
                mkdir($dir_foto, 0777, true);
            }
            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES["foto"]["error"][$i] === UPLOAD_ERR_OK) {
                    $file_type = $_FILES["foto"]["type"][$i];
                    if (in_array($file_type, $allowed)) {
                        $nomeOriginal = basename($_FILES["foto"]["name"][$i]);
                        $ext = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
                        $target_name = "FOTO_{$id}_" . time() . "_" . $i . "." . $ext;
                        $target_path = $dir_foto . $target_name;
                        if (move_uploaded_file($_FILES["foto"]["tmp_name"][$i], $target_path)) {
                            $new_paths[] = $target_path;
                        }
                    }
                }
            }
        }
        $fotos_existentes_array = array_filter(explode(",", $fotos_mantidas));
        $final_paths = array_merge($fotos_existentes_array, $new_paths);
        $final_paths_str = implode(",", $final_paths);
        atualizar("ss_epi", ["ss_e_tx_foto"], [$final_paths_str ? $final_paths_str : null], $id);
        
        set_status("Item atualizado com sucesso no estoque!");
        index();
        exit;
    }

    $itensJson = $_POST["itens_json"] ?? "";
    $itens = json_decode($itensJson, true);
    
    if (empty($itens) || !is_array($itens)) {
        set_status("ERRO: Nenhum item na lista para gravar.");
        modificarEpiEstoque();
        exit;
    }

    $gravados = 0;
    foreach ($itens as $item) {
        $validade_ca = !empty($item["validade_ca"]) ? $item["validade_ca"] : null;
        $vida_util = !empty($item["vida_util"]) ? (int)$item["vida_util"] : 0;
        
        $epi = [
            "ss_e_tx_grupo"         => $item["grupo"],
            "ss_e_tx_subgrupo"      => $item["subgrupo"],
            "ss_e_tx_item"          => $item["item"],
            "ss_e_tx_descricao"     => $item["descricao"],
            "ss_e_tx_fabricante"    => $item["fabricante"],
            "ss_e_tx_modelo"        => $item["modelo"] ?? "",
            "ss_e_tx_ca"            => $item["ca"],
            "ss_e_tx_validade_ca"   => $validade_ca,
            "ss_e_nb_vida_util"     => $vida_util,
            "ss_e_tx_status"        => $item["status"] ?? "ativo",
            "ss_e_tx_cadastro_tipo" => "estoque"
        ];

        if (!empty($item["id"])) {
            $id = (int)$item["id"];
            atualizar("ss_epi", array_keys($epi), array_values($epi), $id);
        } else {
            $res = inserir("ss_epi", array_keys($epi), array_values($epi));
            $id = (int)$res[0];
        }

        $existing_paths = [];
        if (!empty($item["fotos_existentes"])) {
            $existing_paths = array_filter(explode(",", $item["fotos_existentes"]));
        }

        // Processamento de novas fotos em Base64
        $new_paths = [];
        if (!empty($item["fotos"]) && is_array($item["fotos"])) {
            foreach ($item["fotos"] as $fKey => $fObj) {
                if (!empty($fObj["base64"]) && strpos($fObj["base64"], "data:image/") === 0) {
                    $partes = explode(',', $fObj["base64"]);
                    if (count($partes) > 1) {
                        $base64_data = $partes[1];
                        $extensao = "jpg";
                        if (preg_match('/^data:image\/(\w+);base64/', $fObj["base64"], $match)) {
                            $extensao = strtolower($match[1]);
                        }
                        
                        $dir_foto = "arquivos/epi/{$id}/";
                        if (!is_dir($dir_foto)) {
                            mkdir($dir_foto, 0777, true);
                        }
                        
                        $caminho_foto = $dir_foto . "FOTO_{$id}_" . time() . "_" . $fKey . "." . $extensao;
                        $conteudo = base64_decode($base64_data);
                        if (file_put_contents($caminho_foto, $conteudo)) {
                            $new_paths[] = $caminho_foto;
                        }
                    }
                }
            }
        }

        $final_paths = array_merge($existing_paths, $new_paths);
        $final_paths_str = implode(",", $final_paths);
        atualizar("ss_epi", ["ss_e_tx_foto"], [$final_paths_str ? $final_paths_str : null], $id);

        $gravados++;
    }

    set_status("Sucesso: {$gravados} item(ns) gravado(s) no estoque!");
    index();
    exit;
}

function modificarEpiEstoque() {
    $isEdit = false;
    if (!empty($_POST["id"])) {
        if (is_array($_POST["id"])) {
            $_POST["id"] = $_POST["id"][0];
        }
        $isEdit = true;
        $epi = carregar("ss_epi", $_POST["id"]);
        foreach ($epi as $key => $value) {
            $cleanedKey = str_replace("ss_e_tx_", "", $key);
            $cleanedKey = str_replace("ss_e_nb_", "", $cleanedKey);
            if (empty($_POST[$cleanedKey])) {
                $_POST[$cleanedKey] = $value;
            }
        }
    }

    // Carregar EPIs universais para selects encadeados
    $sqlUniversal = query("SELECT DISTINCT ss_e_tx_grupo, ss_e_tx_subgrupo, ss_e_tx_item FROM ss_epi WHERE ss_e_tx_cadastro_tipo = 'universal' AND ss_e_tx_status = 'ativo' ORDER BY ss_e_tx_grupo, ss_e_tx_subgrupo, ss_e_tx_item");
    $universalEpis = [];
    if ($sqlUniversal) {
        while ($row = mysqli_fetch_assoc($sqlUniversal)) {
            $universalEpis[] = [
                "grupo" => $row["ss_e_tx_grupo"],
                "subgrupo" => $row["ss_e_tx_subgrupo"],
                "item" => $row["ss_e_tx_item"]
            ];
        }
    }

    cabecalho($isEdit ? "Editar EPI no Estoque" : "Ficha de EPI no Estoque");
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
    </style>
    <?php

    // Campos do formulário
    $campo_grupo       = combo("Grupo*", "grupo", $_POST["grupo"] ?? "", 4, ["" => "Carregando grupos..."]);
    $campo_subgrupo    = combo("EPI", "subgrupo", $_POST["subgrupo"] ?? "", 4, ["" => "Selecione o Grupo primeiro..."]);
    $campo_item        = combo("Descrição*", "item", $_POST["item"] ?? "", 4, ["" => "Selecione o Subgrupo primeiro..."]);
    
    $campo_fabricante  = campo("Fabricante", "fabricante", $_POST["fabricante"] ?? "", 3, "", "maxlength='100'");
    $campo_modelo      = campo("Modelo", "modelo", $_POST["modelo"] ?? "", 3, "", "maxlength='100'");
    $campo_ca          = campo("MTE Certificado de Aprovacão (CA)", "ca", $_POST["ca"] ?? "", 3, "", "maxlength='50'");
    $campo_validade_ca = campo_data("Validade do CA", "validade_ca", $_POST["validade_ca"] ?? "", 3);
    
    $campo_vida_util   = campo("Vida Útil (dias)", "vida_util", $_POST["vida_util"] ?? "0", 3, "MASCARA_NUMERO");
    $campo_status      = combo("Status", "status", $_POST["status"] ?? "ativo", 3, ["ativo" => "Ativo", "inativo" => "Inativo"]);
    $campo_foto = '
        <div class="col-sm-6 margin-bottom-5 campo-fit-content">
            <label>Imagens (Selecione uma ou mais)</label>
            <input name="foto[]" id="foto_input" autocomplete="off" type="file" class="form-control input-sm campo-fit-content" multiple accept="image/jpeg,image/png,image/jpg">
        </div>';
    
    $fotos = [];
    if (!empty($_POST["foto"])) {
        $fotos = array_filter(explode(",", $_POST["foto"]));
    }
    
    $preview_html = "";
    foreach ($fotos as $idx => $f) {
        $src = $_ENV["APP_PATH"] . '/' . $f;
        $preview_html .= '
            <div class="preview-item" data-path="' . htmlspecialchars($f) . '" style="display: inline-flex; align-items: center; gap: 5px; margin-right: 10px; margin-bottom: 10px; border: 1px solid #ccc; padding: 5px; border-radius: 4px;">
                <img src="' . $src . '" style="max-height: 80px; max-width: 80px; object-fit: cover; cursor: pointer;" onclick="verImagemMaior(\'' . $src . '\')">
                <button type="button" class="btn btn-danger btn-xs btn_remover_foto_existente" data-path="' . htmlspecialchars($f) . '" title="Remover"><i class="fa fa-remove"></i></button>
            </div>';
    }

    $preview_div = '
        <div class="col-sm-12 margin-bottom-5" id="preview_foto_container" style="margin-top: 15px; display: block;">
            <div id="existing_photos_container" style="display: inline-block;">' . $preview_html . '</div>
            <div id="new_photos_container" style="display: inline-block;"></div>
        </div>
    ';
    
    $campo_descricao   = textarea("Observações", "descricao", $_POST["descricao"] ?? "", 12, "style='height: 100px;'");

    // Configuração de botões do formulário principal
    $buttons = [];
    if ($isEdit) {
        $buttons[] = botao("Gravar Alterações", "cadastrarEpiEstoque", "id", $_POST["id"], "", "", "btn btn-success");
        $buttons[] = criarBotaoVoltar();
    } else {
        $buttons[] = '<button type="button" class="btn btn-primary" id="btn_adicionar_lista">Adicionar à Lista</button>';
        $buttons[] = '<button type="button" class="btn btn-default" id="btn_limpar_form">Limpar Campos</button>';
    }

    echo abre_form($isEdit ? "Editar Dados do EPI" : "Dados do EPI no Estoque");
    echo campo_hidden("remover_foto_atual", "0");
    echo campo_hidden("fotos_mantidas", $_POST["foto"] ?? "");
    echo linha_form([$campo_grupo, $campo_subgrupo, $campo_item]);
    echo linha_form([$campo_fabricante, $campo_modelo, $campo_ca, $campo_validade_ca]);
    echo linha_form([$campo_vida_util, $campo_status, $campo_foto]);
    echo linha_form([$preview_div]);
    echo linha_form([$campo_descricao]);
    echo fecha_form($buttons);

    if (!$isEdit) {
        // Tabela de itens adicionados temporariamente (Apenas no cadastro de novo lote)
        echo "
        <div class='portlet light bordered' style='margin-top: 20px;'>
            <div class='portlet-title'>
                <div class='caption font-green-haze'>
                    <i class='fa fa-list font-green-haze'></i>
                    <span class='caption-subject bold uppercase'>Itens a serem Gravados no Estoque</span>
                </div>
            </div>
            <div class='portlet-body'>
                <div class='table-responsive'>
                    <table class='table table-striped table-bordered table-hover' id='tabela_itens_temp'>
                        <thead>
                            <tr>
                                <th>Grupo</th>
                                <th>EPI</th>
                                <th>Descrição</th>
                                <th>Fabricante</th>
                                <th>Modelo</th>
                                <th>CA</th>
                                <th>Validade CA</th>
                                <th>Vida Útil</th>
                                <th>Imagem</th>
                                <th>Status</th>
                                <th style='width: 100px;'>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Itens renderizados por JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        ";

        // Formulário final de gravação (Apenas no cadastro de novo lote)
        echo abre_form("Confirmar Gravação");
        echo '<input type="hidden" name="itens_json" id="itens_json" value="">';
        echo '<input type="hidden" name="id" value="">';
        
        $final_buttons = [];
        $final_buttons[] = botao("Gravar Todos os Itens", "cadastrarEpiEstoque", "", "", "", "", "btn btn-success");
        $final_buttons[] = criarBotaoVoltar();
        echo fecha_form($final_buttons);
    }

    ?>
    <script>
    $(document).ready(function() {
        const isEditMode = <?php echo $isEdit ? 'true' : 'false'; ?>;
        
        // Select2 & cascading dropdown logic
        const data = <?php echo json_encode($universalEpis); ?>;
        const $grupoSelect = $('select[name="grupo"]');
        const $subgrupoSelect = $('select[name="subgrupo"]');
        const $itemSelect = $('select[name="item"]');
        
        const currentGrupo = <?php echo json_encode($_POST['grupo'] ?? ''); ?>;
        const currentSubgrupo = <?php echo json_encode($_POST['subgrupo'] ?? ''); ?>;
        const currentItem = <?php echo json_encode($_POST['item'] ?? ''); ?>;

        function populateGrupos() {
            let grupos = [...new Set(data.map(d => d.grupo))];
            if (currentGrupo && !grupos.includes(currentGrupo)) {
                grupos.push(currentGrupo);
            }
            grupos.sort();
            
            $grupoSelect.html('<option value="">Selecione o Grupo</option>');
            grupos.forEach(g => {
                if (g) {
                    $grupoSelect.append(new Option(g, g, g === currentGrupo, g === currentGrupo));
                }
            });
            if (typeof $.fn.select2 === 'function') {
                $grupoSelect.select2();
            }
            $grupoSelect.trigger('change');
        }

        $grupoSelect.on('change', function() {
            const selectedGrupo = $(this).val();
            $subgrupoSelect.html('<option value="">Selecione o Subgrupo</option>').prop('disabled', !selectedGrupo);
            $itemSelect.html('<option value="">Selecione o Item</option>').prop('disabled', true);
            
            if (selectedGrupo) {
                let subgrupos = [];
                data.filter(d => d.grupo === selectedGrupo).forEach(d => {
                    if (d.subgrupo) {
                        d.subgrupo.split(/[;,]/).forEach(s => {
                            let ts = s.trim();
                            if (ts && !subgrupos.includes(ts)) {
                                subgrupos.push(ts);
                            }
                        });
                    }
                });
                if (selectedGrupo === currentGrupo && currentSubgrupo && !subgrupos.includes(currentSubgrupo)) {
                    subgrupos.push(currentSubgrupo);
                }
                subgrupos.sort();
                
                subgrupos.forEach(s => {
                    if (s) {
                        $subgrupoSelect.append(new Option(s, s, s === currentSubgrupo, s === currentSubgrupo));
                    }
                });
            }
            if (typeof $.fn.select2 === 'function') {
                $subgrupoSelect.select2();
                $itemSelect.select2();
            }
            $subgrupoSelect.trigger('change');
        });

        $subgrupoSelect.on('change', function() {
            const selectedGrupo = $grupoSelect.val();
            const selectedSubgrupo = $(this).val();
            $itemSelect.html('<option value="">Selecione o Item</option>').prop('disabled', !selectedSubgrupo);
            
            if (selectedGrupo && selectedSubgrupo) {
                let items = [];
                data.filter(d => d.grupo === selectedGrupo).forEach(d => {
                    let hasSub = false;
                    if (d.subgrupo) {
                        d.subgrupo.split(/[;,]/).forEach(s => {
                            if (s.trim() === selectedSubgrupo) hasSub = true;
                        });
                    }
                    if (hasSub && d.item) {
                        d.item.split(/[;,]/).forEach(i => {
                            let ti = i.trim();
                            if (ti && !items.includes(ti)) {
                                items.push(ti);
                            }
                        });
                    }
                });
                if (selectedGrupo === currentGrupo && selectedSubgrupo === currentSubgrupo && currentItem && !items.includes(currentItem)) {
                    items.push(currentItem);
                }
                items.sort();
                
                items.forEach(i => {
                    if (i) {
                        $itemSelect.append(new Option(i, i, i === currentItem, i === currentItem));
                    }
                });
            }
            if (typeof $.fn.select2 === 'function') {
                $itemSelect.select2();
            }
        });

        populateGrupos();

        let tempFotosArray = []; // Array de {base64: "...", name: "..."}
        
        // Detectar alteração de novos arquivos
        $('#foto_input').on('change', function(event) {
            $('#new_photos_container').empty();
            tempFotosArray = [];
            
            const files = event.target.files;
            if (files && files.length > 0) {
                const allowedTypes = ["image/jpeg", "image/png", "image/jpg"];
                
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    if (!allowedTypes.includes(file.type)) {
                        Swal.fire('Atenção', 'O arquivo "' + file.name + '" não é uma imagem válida (JPEG, JPG ou PNG).', 'warning');
                        continue;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        tempFotosArray.push({
                            base64: e.target.result,
                            name: file.name
                        });
                        
                        // Mostrar preview da imagem nova
                        const imgHtml = `
                            <div class="preview-item-new" style="display: inline-flex; align-items: center; gap: 5px; margin-right: 10px; margin-bottom: 10px; border: 1px solid #aaa; padding: 5px; border-radius: 4px; background: #f9f9f9;">
                                <img src="${e.target.result}" style="max-height: 80px; max-width: 80px; object-fit: cover; cursor: pointer;" onclick="verImagemMaior('${e.target.result}')" title="Nova Imagem">
                            </div>`;
                        $('#new_photos_container').append(imgHtml);
                    };
                    reader.readAsDataURL(file);
                }
            }
        });

        // Clique para remover foto existente
        $(document).on('click', '.btn_remover_foto_existente', function() {
            const pathToRemove = $(this).attr('data-path');
            $(this).closest('.preview-item').remove();
            
            let mantidas = $('#fotos_mantidas').val().split(',').filter(Boolean);
            mantidas = mantidas.filter(p => p !== pathToRemove);
            $('#fotos_mantidas').val(mantidas.join(','));
            $('#remover_foto_atual').val('1'); // Sinaliza que fotos foram modificadas/removidas
        });

        function removerPreviaFoto() {
            tempFotosArray = [];
            $('#foto_input').val('');
            $('#new_photos_container').empty();
        }

        if (!isEditMode) {
            $('#itens_json').closest('form').attr('name', 'form_gravar_lote');
            
            // Lógica de lista temporária de cadastro em lote
            let itemsList = [];
            let editIndex = null;
            
            function renderTable() {
                const tbody = $('#tabela_itens_temp tbody');
                tbody.empty();
                
                if (itemsList.length === 0) {
                    tbody.append('<tr><td colspan="11" style="text-align: center; color: #999;">Nenhum item adicionado à lista.</td></tr>');
                    $('#itens_json').val('');
                    return;
                }
                
                itemsList.forEach((item, index) => {
                    const row = $('<tr>');
                    row.append($('<td>').text(item.grupo));
                    row.append($('<td>').text(item.subgrupo));
                    row.append($('<td>').text(item.item));
                    row.append($('<td>').text(item.fabricante || '---'));
                    row.append($('<td>').text(item.modelo || '---'));
                    row.append($('<td>').text(item.ca || '---'));
                    row.append($('<td>').text(item.validade_ca || '---'));
                    row.append($('<td>').text(item.vida_util + ' dias'));
                    
                    let fotosHtml = '';
                    let allFotos = [];
                    if (item.fotos_existentes) {
                        allFotos = allFotos.concat(item.fotos_existentes.split(',').filter(Boolean));
                    }
                    if (item.fotos) {
                        item.fotos.forEach(f => {
                            allFotos.push(f.base64 || f);
                        });
                    }
                    allFotos.forEach(fotoPath => {
                        let resolvedSrc = fotoPath;
                        var appPath = <?php echo json_encode($_ENV["APP_PATH"]); ?>;
                        if (fotoPath && fotoPath.indexOf('data:image/') === -1 && fotoPath.indexOf('http') !== 0) {
                            resolvedSrc = appPath + '/' + fotoPath;
                        }
                        fotosHtml += `<a href="${resolvedSrc}" target="_blank" style="margin-right: 5px;"><img src="${resolvedSrc}" style="max-height: 40px; max-width: 40px; border-radius: 4px; object-fit: cover;"></a>`;
                    });
                    if (!fotosHtml) fotosHtml = '<span class="text-muted">-</span>';
                    row.append($('<td>').html(fotosHtml));
                    
                    row.append($('<td>').html('<span class="label label-' + (item.status === 'ativo' ? 'success' : 'danger') + '">' + item.status.toUpperCase() + '</span>'));
                    
                    const actionsTd = $('<td>');
                    const editBtn = $('<button type="button" class="btn btn-xs btn-warning" style="margin-right: 5px;"><i class="fa fa-edit"></i></button>');
                    editBtn.on('click', () => editItem(index));
                    
                    const deleteBtn = $('<button type="button" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>');
                    deleteBtn.on('click', () => removeItem(index));
                    
                    actionsTd.append(editBtn).append(deleteBtn);
                    row.append(actionsTd);
                    tbody.append(row);
                });
                
                $('#itens_json').val(JSON.stringify(itemsList));
            }

            function editItem(index) {
                editIndex = index;
                const item = itemsList[index];
                
                $('select[name="grupo"]').val(item.grupo).trigger('change');
                $('select[name="subgrupo"]').val(item.subgrupo).trigger('change');
                $('select[name="item"]').val(item.item).trigger('change');
                
                $('input[name="fabricante"]').val(item.fabricante);
                $('input[name="modelo"]').val(item.modelo || '');
                $('input[name="ca"]').val(item.ca);
                $('input[name="validade_ca"]').val(item.validade_ca);
                $('input[name="vida_util"]').val(item.vida_util);
                $('select[name="status"]').val(item.status).trigger('change');
                $('textarea[name="descricao"]').val(item.descricao);
                
                $('#existing_photos_container').empty();
                $('#new_photos_container').empty();
                tempFotosArray = [];

                let allExisting = [];
                if (item.fotos_existentes) {
                    allExisting = item.fotos_existentes.split(',').filter(Boolean);
                }
                
                $('#fotos_mantidas').val(item.fotos_existentes || "");
                
                allExisting.forEach(f => {
                    let src = <?php echo json_encode($_ENV["APP_PATH"]); ?> + '/' + f;
                    let pItem = `
                        <div class="preview-item" data-path="${f}" style="display: inline-flex; align-items: center; gap: 5px; margin-right: 10px; margin-bottom: 10px; border: 1px solid #ccc; padding: 5px; border-radius: 4px;">
                            <img src="${src}" style="max-height: 80px; max-width: 80px; object-fit: cover; cursor: pointer;" onclick="verImagemMaior('${src}')">
                            <button type="button" class="btn btn-danger btn-xs btn_remover_foto_existente" data-path="${f}"><i class="fa fa-remove"></i></button>
                        </div>`;
                    $('#existing_photos_container').append(pItem);
                });

                if (item.fotos) {
                    item.fotos.forEach(fObj => {
                        let pathOrBase64 = fObj.base64 || fObj;
                        if (pathOrBase64.indexOf('data:image/') === 0) {
                            tempFotosArray.push(fObj);
                            let imgHtml = `
                                <div class="preview-item-new" style="display: inline-flex; align-items: center; gap: 5px; margin-right: 10px; margin-bottom: 10px; border: 1px solid #aaa; padding: 5px; border-radius: 4px; background: #f9f9f9;">
                                    <img src="${pathOrBase64}" style="max-height: 80px; max-width: 80px; object-fit: cover; cursor: pointer;" onclick="verImagemMaior('${pathOrBase64}')">
                                </div>`;
                            $('#new_photos_container').append(imgHtml);
                        } else {
                            let src = <?php echo json_encode($_ENV["APP_PATH"]); ?> + '/' + pathOrBase64;
                            let pItem = `
                                <div class="preview-item" data-path="${pathOrBase64}" style="display: inline-flex; align-items: center; gap: 5px; margin-right: 10px; margin-bottom: 10px; border: 1px solid #ccc; padding: 5px; border-radius: 4px;">
                                    <img src="${src}" style="max-height: 80px; max-width: 80px; object-fit: cover; cursor: pointer;" onclick="verImagemMaior('${src}')">
                                    <button type="button" class="btn btn-danger btn-xs btn_remover_foto_existente" data-path="${pathOrBase64}"><i class="fa fa-remove"></i></button>
                                </div>`;
                            $('#existing_photos_container').append(pItem);
                        }
                    });
                }
                
                $('#btn_adicionar_lista').text('Atualizar na Lista').removeClass('btn-primary').addClass('btn-info');
            }

            function removeItem(index) {
                Swal.fire({
                    title: 'Remover?',
                    text: 'Deseja remover este item da lista temporária?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sim, remover',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        itemsList.splice(index, 1);
                        renderTable();
                        if (editIndex === index) {
                            limparForm();
                        }
                    }
                });
            }

            function limparForm() {
                editIndex = null;
                $('select[name="grupo"]').val('').trigger('change');
                $('input[name="fabricante"]').val('');
                $('input[name="modelo"]').val('');
                $('input[name="ca"]').val('');
                $('input[name="validade_ca"]').val('');
                $('input[name="vida_util"]').val('0');
                $('select[name="status"]').val('ativo').trigger('change');
                $('textarea[name="descricao"]').val('');
                removerPreviaFoto();
                $('#btn_adicionar_lista').text('Adicionar à Lista').removeClass('btn-info').addClass('btn-primary');
            }

            $('#btn_adicionar_lista').on('click', function() {
                const grupo = $('select[name="grupo"]').val();
                const subgrupo = $('select[name="subgrupo"]').val();
                const itemVal = $('select[name="item"]').val();
                
                if (!grupo || !itemVal) {
                    Swal.fire('Atenção', 'Os campos Grupo e Descrição são obrigatórios.', 'warning');
                    return;
                }
                
                const itemData = {
                    id: editIndex !== null ? itemsList[editIndex].id : '',
                    grupo: grupo,
                    subgrupo: subgrupo,
                    item: itemVal,
                    fabricante: $('input[name="fabricante"]').val(),
                    modelo: $('input[name="modelo"]').val(),
                    ca: $('input[name="ca"]').val(),
                    validade_ca: $('input[name="validade_ca"]').val(),
                    vida_util: parseInt($('input[name="vida_util"]').val(), 10) || 0,
                    status: $('select[name="status"]').val() || 'ativo',
                    descricao: $('textarea[name="descricao"]').val(),
                    fotos: tempFotosArray.length > 0 ? tempFotosArray : (editIndex !== null ? itemsList[editIndex].fotos : []),
                    fotos_existentes: $('#fotos_mantidas').val() || ""
                };
                
                if (editIndex !== null) {
                    itemsList[editIndex] = itemData;
                } else {
                    itemsList.push(itemData);
                }
                
                limparForm();
                renderTable();
            });
            
            $('#btn_limpar_form').on('click', limparForm);

            let bypassValidation = false;
            $('form[name="form_gravar_lote"]').on('submit', function(e) {
                if (bypassValidation) {
                    return;
                }

                const activeBtn = $(document.activeElement);
                if (activeBtn.attr('name') === 'acao' && activeBtn.val() === 'voltar') {
                    if (itemsList.length > 0) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Sair sem salvar?',
                            text: 'Existem itens na lista temporária que ainda não foram gravados. Deseja realmente voltar e perder as alterações?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Sim, sair',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                bypassValidation = true;
                                var form = $('form[name="form_gravar_lote"]');
                                var inputAcao = $('<input type="hidden" name="acao" value="voltar">');
                                form.append(inputAcao);
                                form.submit();
                            }
                        });
                    }
                    return;
                }

                if (itemsList.length === 0) {
                    e.preventDefault();
                    Swal.fire('Atenção', 'Adicione pelo menos um item à lista antes de gravar.', 'warning');
                }
            });

            renderTable();
        }
    });
    </script>
    <?php

    rodape();
}

// --- CRUD DE KITS ---

function listarKits() {
    cabecalho("Gerenciamento de Kits de EPI");
    echo '<style>#btnExportPDF { display: none !important; }</style>';

    $fields = [
        campo("Nome do Kit", "busca_nome", $_POST["busca_nome"] ?? "", 4)
    ];

    $buttons = [];
    $buttons[] = botao("Buscar", "listarKits");
    $buttons[] = botao("Cadastrar Kit", "modificarKit", "", "", "", "", "btn btn-success");
    $buttons[] = botao("Voltar ao Estoque", "index", "", "", "", "", "btn btn-default");

    echo abre_form("Filtros de Pesquisa");
    echo linha_form($fields);
    echo fecha_form($buttons);

    $gridFields = [
        "CÓDIGO" => "ss_k_nb_id",
        "NOME"   => "ss_k_tx_nome",
        "STATUS" => "ss_k_tx_status"
    ];

    $camposBusca = [
        "busca_nome" => "ss_k_tx_nome"
    ];

    $queryBase = "SELECT ss_k_nb_id, ss_k_tx_nome, ss_k_tx_status FROM ss_kit";

    $gridFields["actions"] = [
        '<span class="fa fa-edit acao-editar-kit" title="Alterar" style="color:#337ab7; cursor:pointer; font-size:16px; margin-right:8px;"></span>',
        '<span class="fa fa-ban acao-inativar-kit" title="Inativar/Ativar" style="color:#f0ad4e; cursor:pointer; font-size:16px; margin-right:8px;"></span>',
        '<span class="fa fa-trash acao-excluir-kit" title="Excluir" style="color:#d9534f; cursor:pointer; font-size:16px;"></span>'
    ];

    $jsAcoes = '
        var funcoesInternas = function(){
            // Bind Alterar click
            $(".acao-editar-kit").off("click").on("click", function(event) {
                var id = $(this).closest("tr").attr("data-row-id");
                submitPost("", { acao: "modificarKit", id: id });
            });

            // For each row, check status and customize the inativar/ativar icon
            $("#result tbody tr").each(function() {
                var row = $(this);
                var statusCell = row.find("td").eq(2); // STATUS column is index 2 (CÓDIGO, NOME, STATUS)
                var statusText = statusCell.text().trim().toLowerCase();
                
                var inativarIcon = row.find(".acao-inativar-kit");
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
            $(".acao-inativar-kit").off("click").on("click", function(event) {
                var row = $(this).closest("tr");
                var id = row.attr("data-row-id");
                var kitNome = row.find("td").eq(1).text().trim(); // NOME
                var statusCell = row.find("td").eq(2);
                var statusText = statusCell.text().trim().toLowerCase();
                
                var isCurrentlyInactive = (statusText.indexOf("inativo") >= 0 || statusText === "inativo");
                var acaoLabel = isCurrentlyInactive ? "ativar" : "inativar";
                var acaoPHP = isCurrentlyInactive ? "ativarKit" : "inativarKit";
                var confirmBtnColor = isCurrentlyInactive ? "#5cb85c" : "#f0ad4e";
                
                Swal.fire({
                    title: "Tem certeza?",
                    html: "Deseja " + acaoLabel + " o Kit <b>" + kitNome + "</b>?",
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
            $(".acao-excluir-kit").off("click").on("click", function(event) {
                var row = $(this).closest("tr");
                var id = row.attr("data-row-id");
                var kitNome = row.find("td").eq(1).text().trim();
                
                Swal.fire({
                    title: "Tem certeza?",
                    html: "Deseja excluir permanentemente o Kit <b>" + kitNome + "</b>?<br><br><span style=\'color:#d9534f;\'><b>Atenção:</b> Isso excluirá o kit e todos os seus itens associados!</span>",
                    icon: "error",
                    showCancelButton: true,
                    confirmButtonColor: "#d9534f",
                    cancelButtonColor: "#6c757d",
                    confirmButtonText: "Sim, excluir!",
                    cancelButtonText: "Cancelar"
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitPost("", { acao: "excluirKit", id: id });
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

    echo gridDinamico("tabelaKits", $gridFields, $camposBusca, $queryBase, $jsAcoes);

    rodape();
}

function modificarKit() {
    if (!empty($_POST["id"])) {
        if (is_array($_POST["id"])) {
            $_POST["id"] = $_POST["id"][0];
        }
        $kit = carregar("ss_kit", $_POST["id"]);
        $_POST["nome_kit"] = $kit["ss_k_tx_nome"];
        $_POST["status"] = $kit["ss_k_tx_status"];

        $sqlItens = query("SELECT ss_ki_nb_epi_id, ss_ki_nb_quantidade 
                           FROM ss_kit_item 
                           WHERE ss_ki_nb_kit_id = " . (int)$_POST["id"]);
        $kitItens = [];
        if ($sqlItens) {
            while ($row = mysqli_fetch_assoc($sqlItens)) {
                $epi = carregar("ss_epi", $row["ss_ki_nb_epi_id"]);
                $epiNome = $epi["ss_e_tx_grupo"] . " / " . $epi["ss_e_tx_subgrupo"] . " / " . $epi["ss_e_tx_item"] . " (CA: " . ($epi["ss_e_tx_ca"] ?: 'N/A') . ")";
                $kitItens[] = [
                    "epi_id" => $row["ss_ki_nb_epi_id"],
                    "epi_nome" => $epiNome,
                    "quantidade" => $row["ss_ki_nb_quantidade"],
                    "foto" => $epi["ss_e_tx_foto"]
                ];
            }
        }
    } else {
        $kitItens = [];
    }

    // Carregar EPIs de estoque para o kit
    $sqlEpi = query("SELECT ss_e_nb_id, CONCAT(ss_e_tx_grupo, ' / ', IFNULL(ss_e_tx_subgrupo, ''), ' / ', IFNULL(ss_e_tx_item, ''), ' (CA: ', IFNULL(ss_e_tx_ca, 'N/A'), ')') AS epi_nome 
                     FROM ss_epi 
                     WHERE ss_e_tx_status = 'ativo' AND ss_e_tx_cadastro_tipo = 'estoque'
                     ORDER BY ss_e_tx_grupo ASC");
    $epiOptions = ["" => "Selecione o EPI"];
    if ($sqlEpi) {
        while ($row = mysqli_fetch_assoc($sqlEpi)) {
            $epiOptions[$row["ss_e_nb_id"]] = $row["epi_nome"];
        }
    }

    // Carregar mapa de fotos de EPIs
    $sqlEpisFotos = query("SELECT ss_e_nb_id, ss_e_tx_foto FROM ss_epi WHERE ss_e_tx_status = 'ativo' AND ss_e_tx_cadastro_tipo = 'estoque'");
    $epiFotosMap = [];
    if ($sqlEpisFotos) {
        while ($row = mysqli_fetch_assoc($sqlEpisFotos)) {
            $epiFotosMap[$row["ss_e_nb_id"]] = $row["ss_e_tx_foto"];
        }
    }

    cabecalho("Ficha de Kit de EPI");

    $campo_nome   = campo("Nome do Kit*", "nome_kit", $_POST["nome_kit"] ?? "", 6, "", "maxlength='100'");
    $campo_status = combo("Status", "status", $_POST["status"] ?? "ativo", 3, ["ativo" => "Ativo", "inativo" => "Inativo"]);
    
    $campo_epi = combo("EPI para Adicionar", "kit_epi_id", "", 6, $epiOptions);
    $campo_qtd = campo("Quantidade do Item", "kit_epi_qtd", "1", 2, "MASCARA_NUMERO");
    $btn_add   = '<div class="col-sm-4 margin-bottom-5 campo-fit-content" style="margin-top:23px;"><button type="button" class="btn btn-primary" id="btn_add_epi_kit">Adicionar ao Kit</button></div>';

    echo abre_form("Dados Gerais do Kit");
    echo linha_form([$campo_nome, $campo_status]);
    echo fecha_form([]);

    echo abre_form("Composição do Kit");
    echo linha_form([$campo_epi, $campo_qtd, $btn_add]);
    
    echo "
    <div class='table-responsive' style='margin-top: 15px;'>
        <table class='table table-striped table-bordered table-hover' id='tabela_itens_kit'>
            <thead>
                <tr>
                    <th style='width: 80px;'>Imagem</th>
                    <th>EPI</th>
                    <th style='width: 150px;'>Quantidade</th>
                    <th style='width: 100px;'>Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- Preenchido via JS -->
            </tbody>
        </table>
    </div>
    ";
    echo fecha_form([]);

    // Form final de envio
    echo abre_form("Salvar Kit");
    echo '<input type="hidden" name="kit_itens_json" id="kit_itens_json" value="">';
    echo '<input type="hidden" name="id" value="' . ($_POST["id"] ?? "") . '">';
    echo '<input type="hidden" name="nome_kit" id="final_nome_kit" value="">';
    echo '<input type="hidden" name="status" id="final_status" value="">';

    $final_buttons = [];
    $final_buttons[] = botao("Salvar Kit", "cadastrarKit", "", "", "", "", "btn btn-success");
    $final_buttons[] = botao("Voltar", "listarKits", "", "", "", "", "btn btn-default");
    echo fecha_form($final_buttons);

    echo "
    <script>
    $(document).ready(function() {
        $('#kit_itens_json').closest('form').attr('name', 'form_salvar_kit');
        if (typeof $.fn.select2 === 'function') {
            $.fn.select2.defaults.set('theme', 'bootstrap');
            $('select[name=\"kit_epi_id\"]').select2();
        }

        let kitItens = " . json_encode($kitItens) . ";
        const epiFotosMap = " . json_encode($epiFotosMap) . ";

        if (typeof window.verImagemMaior === 'undefined') {
            window.verImagemMaior = function(src) {
                Swal.fire({
                    imageUrl: src,
                    imageAlt: 'Imagem do EPI',
                    showConfirmButton: false,
                    showCloseButton: true,
                    background: '#fff',
                    backdrop: 'rgba(0,0,0,0.8)'
                });
            };
        }

        function renderKitTable() {
            const tbody = $('#tabela_itens_kit tbody');
            tbody.empty();
            
            if (kitItens.length === 0) {
                tbody.append('<tr><td colspan=\"4\" style=\"text-align: center; color: #999;\">Nenhum item adicionado ao kit.</td></tr>');
                $('#kit_itens_json').val('');
                return;
            }
            
            kitItens.forEach((item, index) => {
                const row = $('<tr>');
                
                let fotoHtml = '<span class=\"text-muted\">-</span>';
                if (item.foto) {
                    let resolvedSrc = item.foto;
                    let appPath = " . json_encode($_ENV["APP_PATH"]) . ";
                    if (item.foto.indexOf('data:image/') === -1 && item.foto.indexOf('http') !== 0) {
                        resolvedSrc = appPath + '/' + item.foto;
                    }
                    fotoHtml = '<img src=\"' + resolvedSrc + '\" class=\"thumbnail-kit-item\" onclick=\"verImagemMaior(\'' + resolvedSrc + '\')\" style=\"max-height: 40px; max-width: 40px; border-radius: 4px; border: 1px solid #ccc; cursor: pointer; object-fit: cover;\" title=\"Clique para ampliar\">';
                }
                row.append($('<td>').html(fotoHtml));
                
                row.append($('<td>').text(item.epi_nome));
                row.append($('<td>').text(item.quantidade));
                
                const actionsTd = $('<td>');
                const deleteBtn = $('<button type=\"button\" class=\"btn btn-xs btn-danger\"><i class=\"fa fa-trash\"></i></button>');
                deleteBtn.on('click', function() {
                    kitItens.splice(index, 1);
                    renderKitTable();
                });
                actionsTd.append(deleteBtn);
                row.append(actionsTd);
                tbody.append(row);
            });
            
            $('#kit_itens_json').val(JSON.stringify(kitItens));
        }

        $('#btn_add_epi_kit').on('click', function() {
            const epiSelect = $('select[name=\"kit_epi_id\"]');
            const epiId = epiSelect.val();
            const epiNome = epiSelect.find('option:selected').text();
            const qtd = parseInt($('#kit_epi_qtd').val(), 10) || 0;
            
            if (!epiId) {
                Swal.fire('Atenção', 'Selecione um EPI para adicionar.', 'warning');
                return;
            }
            if (qtd <= 0) {
                Swal.fire('Atenção', 'A quantidade deve ser maior que zero.', 'warning');
                return;
            }
            
            const exists = kitItens.some(item => item.epi_id == epiId);
            if (exists) {
                Swal.fire('Atenção', 'Este EPI já foi adicionado ao kit.', 'warning');
                return;
            }
            
            const fotoPath = epiFotosMap[epiId] || '';
            kitItens.push({
                epi_id: epiId,
                epi_nome: epiNome,
                quantidade: qtd,
                foto: fotoPath
            });
            
            epiSelect.val('').trigger('change');
            $('#kit_epi_qtd').val('1');
            renderKitTable();
        });

        const initialKitItensStr = JSON.stringify(kitItens);
        const initialNomeKit = $('input[name=\"nome_kit\"]').val() || '';
        const initialStatus = $('select[name=\"status\"]').val() || 'ativo';
        let bypassKitValidation = false;

        $('form[name=\"form_salvar_kit\"]').on('submit', function(e) {
            if (bypassKitValidation) {
                return;
            }

            const activeBtn = $(document.activeElement);
            if (activeBtn.attr('name') === 'acao' && activeBtn.val() === 'listarKits') {
                const currentKitItensStr = JSON.stringify(kitItens);
                const currentNomeKit = $('input[name=\"nome_kit\"]').val() || '';
                const currentStatus = $('select[name=\"status\"]').val() || 'ativo';
                
                const hasChanges = (initialKitItensStr !== currentKitItensStr) || 
                                    (initialNomeKit !== currentNomeKit) || 
                                    (initialStatus !== currentStatus);
                
                if (hasChanges) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Sair sem salvar?',
                        text: 'Existem alterações não salvas no Kit. Deseja realmente voltar?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Sim, sair',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            bypassKitValidation = true;
                            var form = $('form[name=\"form_salvar_kit\"]');
                            var inputAcao = $('<input type=\"hidden\" name=\"acao\" value=\"listarKits\">');
                            form.append(inputAcao);
                            form.submit();
                        }
                    });
                }
                return;
            }

            const nomeKit = $('input[name=\"nome_kit\"]').val();
            if (!nomeKit || nomeKit.trim() === '') {
                e.preventDefault();
                Swal.fire('Atenção', 'O campo Nome do Kit é obrigatório.', 'warning');
                return;
            }
            if (kitItens.length === 0) {
                e.preventDefault();
                Swal.fire('Atenção', 'Adicione pelo menos um item à composição do kit.', 'warning');
                return;
            }
            
            $('#final_nome_kit').val(nomeKit);
            $('#final_status').val($('select[name=\"status\"]').val());
        });

        renderKitTable();
    });
    </script>
    ";

    rodape();
}

function cadastrarKit() {
    $camposObrig = [
        "nome_kit" => "Nome do Kit"
    ];
    $errorMsg = conferirCamposObrig($camposObrig, $_POST);
    if (!empty($errorMsg)) {
        set_status("ERRO: {$errorMsg}");
        modificarKit();
        exit;
    }

    $nome_kit = $_POST["nome_kit"];
    $status = $_POST["status"] ?? "ativo";
    $userCadastro = $_SESSION["user_nb_id"] ?? 0;
    $dataCadastro = date("Y-m-d H:i:s");

    $kit = [
        "ss_k_tx_nome" => $nome_kit,
        "ss_k_tx_status" => $status
    ];

    if (empty($_POST["id"])) {
        $kit["ss_k_nb_userCadastro"] = $userCadastro;
        $kit["ss_k_tx_dataCadastro"] = $dataCadastro;
        $res = inserir("ss_kit", array_keys($kit), array_values($kit));
        $kitId = $res[0];
    } else {
        $kitId = (int)$_POST["id"];
        $kit["ss_k_nb_userAtualiza"] = $userCadastro;
        $kit["ss_k_tx_dataAtualiza"] = $dataCadastro;
        atualizar("ss_kit", array_keys($kit), array_values($kit), $kitId);
    }

    // Atualizar itens
    query("DELETE FROM ss_kit_item WHERE ss_ki_nb_kit_id = {$kitId}");

    $itens = json_decode($_POST["kit_itens_json"] ?? "[]", true);
    if (is_array($itens)) {
        foreach ($itens as $item) {
            $epiId = (int)$item["epi_id"];
            $qtd = (int)$item["quantidade"];
            if ($epiId > 0 && $qtd > 0) {
                query("INSERT INTO ss_kit_item (ss_ki_nb_kit_id, ss_ki_nb_epi_id, ss_ki_nb_quantidade) 
                       VALUES ({$kitId}, {$epiId}, {$qtd})");
            }
        }
    }

    set_status("Kit salvo com sucesso!");
    listarKits();
    exit;
}

function excluirKit() {
    if (!empty($_POST["id"])) {
        $id = (int)$_POST["id"];
        query("DELETE FROM ss_kit WHERE ss_k_nb_id = {$id}");
        query("DELETE FROM ss_kit_item WHERE ss_ki_nb_kit_id = {$id}");
        set_status("Kit excluído permanentemente com sucesso!");
    }
    listarKits();
    exit;
}

function inativarKit() {
    if (!empty($_POST["id"])) {
        $id = (int)$_POST["id"];
        atualizar("ss_kit", ["ss_k_tx_status"], ["inativo"], $id);
        set_status("Kit inativado com sucesso!");
    }
    listarKits();
    exit;
}

function ativarKit() {
    if (!empty($_POST["id"])) {
        $id = (int)$_POST["id"];
        atualizar("ss_kit", ["ss_k_tx_status"], ["ativo"], $id);
        set_status("Kit ativado com sucesso!");
    }
    listarKits();
    exit;
}
?>
