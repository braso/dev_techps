<?php
include "conecta.php";

function parseBrValor($val) {
    if (empty($val)) return null;
    $val = str_replace(['R$', ' '], '', $val);
    if (strpos($val, ',') !== false) {
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
    }
    return floatval($val);
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
        $validade_epi = !empty($item["validade_epi"]) ? $item["validade_epi"] : null;
        
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
        
        $sucesso = registrarMovimentacaoEstoque($epi_id, $quantidade, $tipo, $motivo, $valor_unitario, $valor_total, "", $data_recebimento, $chave_nf, $empresa_id, $fornecedor, $validade_epi);
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

function lancarEstoque() {
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
    $validade_epi = !empty($_POST["validade_epi"]) ? $_POST["validade_epi"] : null;

    if ($epi_id <= 0) {
        $_POST["errorFields"][] = "epi_id";
        set_status("ERRO: Selecione um EPI válido.");
        redireciona("movimentacao_estoque.php");
        exit;
    }

    if ($quantidade <= 0) {
        $_POST["errorFields"][] = "quantidade";
        set_status("ERRO: A quantidade deve ser maior que zero.");
        redireciona("movimentacao_estoque.php");
        exit;
    }

    if ($tipo === 'saida') {
        $saldoAtual = obterSaldoEstoque($epi_id, $empresa_id);
        if ($quantidade > $saldoAtual) {
            $_POST["errorFields"][] = "quantidade";
            set_status("ERRO: Estoque insuficiente. Saldo atual: {$saldoAtual}.");
            redireciona("movimentacao_estoque.php");
            exit;
        }
    }

    $sucesso = registrarMovimentacaoEstoque($epi_id, $quantidade, $tipo, $motivo, $valor_unitario, $valor_total, "", $data_recebimento, $chave_nf, $empresa_id, $fornecedor, $validade_epi);
    if ($sucesso) {
        set_status("Movimentação registrada com sucesso!");
    } else {
        set_status("ERRO ao registrar movimentação.");
    }

    redireciona("estoque_epi.php");
    exit;
}

function index() {
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

    $campo_data_receb     = campo_data("Data de Recebimento", "data_recebimento", $_POST["data_recebimento"] ?? "", 3);
    $campo_chave_nf       = campo("Chave NF", "chave_nf", $_POST["chave_nf"] ?? "", 3, "", "maxlength='100'");
    $campo_fornecedor     = campo("Fornecedor", "fornecedor", $_POST["fornecedor"] ?? "", 3, "", "maxlength='255'");
    $campo_validade_epi   = campo_data("Validade do EPI", "validade_epi", $_POST["validade_epi"] ?? "", 3);

    $buttons = [];
    $buttons[] = '<button type="button" class="btn btn-primary" id="btn_adicionar_item" onclick="adicionarItemALista()"><i class="fa fa-plus"></i> Adicionar Item à Lista</button>';
    $buttons[] = '<button type="button" class="btn btn-default" onclick="confirmarVoltar()"><i class="fa fa-arrow-left"></i> Voltar</button>';

    echo abre_form("Dados da Movimentação");
    echo linha_form([$campo_empresa, $campo_epi, $campo_quant, $campo_tipo]);
    echo linha_form([$campo_motivo, $campo_valor_unitario, $campo_valor_total]);
    echo linha_form([$campo_data_receb, $campo_chave_nf, $campo_fornecedor, $campo_validade_epi]);
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

    ?>
    <script>
    var itensLote = {};
    var empresasNomes = <?php echo $jsEmpresas; ?>;

    $(document).ready(function() {
        if (typeof $.fn.select2 === 'function') {
            $.fn.select2.defaults.set('theme', 'bootstrap');
            $('select[name="epi_id"], select[name="empresa_id"]').select2();
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
        var epiSelect = $('select[name="epi_id"]');
        var epiId = epiSelect.val();
        var epiNome = epiSelect.find('option:selected').text();
        var quantidade = $('#quantidade').val();
        var tipo = $('select[name="tipo"]').val();
        var motivo = $('#motivo').val();
        var valorUnitario = $('#valor_unitario').val();
        var valorTotal = $('#valor_total').val();
        var dataRecebimento = $('#data_recebimento').val();
        var chaveNf = $('#chave_nf').val();
        var fornecedor = $('#fornecedor').val();
        var validadeEpi = $('#validade_epi').val();
        
        var empresaId = $('select[name="empresa_id"]').val() || '0';
        
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
            validade_epi: validadeEpi,
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
        $('#validade_epi').val('');
        
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
            
            var panelHtml = '<div class="portlet box blue-hoki" id="panel_empresa_' + empresaId + '" style="margin-bottom: 20px;">' +
                '<div class="portlet-title">' +
                    '<div class="caption">' +
                        '<i class="fa fa-shopping-cart"></i> Lançamentos para: <strong>' + empresaNome + '</strong> (' + itens.length + ' item(ns))' +
                    '</div>' +
                    '<div class="actions" style="display: inline-block; float: right; margin-top: 4px;">' +
                        '<button type="button" class="btn btn-default btn-sm" style="background-color: #fff; color: #333;" onclick="salvarListaEmpresa(\'' + empresaId + '\')"><i class="fa fa-save"></i> Salvar Esta Empresa</button>' +
                    '</div>' +
                '</div>' +
                '<div class="portlet-body">' +
                    '<div class="table-responsive">' +
                        '<table class="table table-striped table-bordered table-hover">' +
                            '<thead>' +
                                '<tr>' +
                                    '<th>EPI</th>' +
                                    '<th style="text-align: center;">Operação</th>' +
                                    '<th style="text-align: center; width: 80px;">Qtd</th>' +
                                    '<th>Fornecedor</th>' +
                                    '<th>NF / Recebimento / Validade</th>' +
                                    '<th>Valor (Unit/Total)</th>' +
                                    '<th>Motivo</th>' +
                                    '<th style="width: 50px; text-align: center;">Ações</th>' +
                                '</tr>' +
                            '</thead>' +
                            '<tbody>';
            
            for (var i = 0; i < itens.length; i++) {
                var it = itens[i];
                var badgeTipo = it.tipo === 'entrada'
                    ? '<span class="label label-sm label-success">Entrada</span>'
                    : '<span class="label label-sm label-danger">Saída</span>';
                    
                panelHtml += '<tr id="row_item_' + it.unique_id + '">' +
                                '<td style="vertical-align: middle;">' + it.epi_nome + '</td>' +
                                '<td style="text-align: center; vertical-align: middle;">' + badgeTipo + '</td>' +
                                '<td style="text-align: center; font-weight: bold; vertical-align: middle;">' + it.quantidade + '</td>' +
                                '<td style="vertical-align: middle;">' + (it.fornecedor || '-') + '</td>' +
                                '<td style="vertical-align: middle;">NF: ' + (it.chave_nf || '-') + '<br><small class="text-muted">receb: ' + (it.data_recebimento || '-') + '</small><br><small class="text-muted">validade: ' + (it.validade_epi || '-') + '</small></td>' +
                                '<td style="vertical-align: middle;">U: ' + (it.valor_unitario || '-') + '<br>T: ' + (it.valor_total || '-') + '</td>' +
                                '<td style="font-style: italic; vertical-align: middle;">' + (it.motivo || '-') + '</td>' +
                                '<td style="text-align: center; vertical-align: middle;">' +
                                    '<button type="button" class="btn btn-danger btn-xs" onclick="removerItem(\'' + empresaId + '\', \'' + it.unique_id + '\')"><i class="fa fa-trash"></i></button>' +
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
            url: 'movimentacao_estoque.php?acao=lancarEstoqueLoteAjax',
            type: 'POST',
            data: { lotes: JSON.stringify(lotes) },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' || response.status === 'partial') {
                    if (response.erros && response.erros.length > 0) {
                        alert('Avisos durante a gravação:\n\n' + response.erros.join('\n'));
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
    <?php
    rodape();
}
