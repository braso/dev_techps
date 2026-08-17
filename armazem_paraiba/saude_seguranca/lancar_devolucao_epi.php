<?php
ob_start();
/* Modo debug
    ini_set("display_errors", 1);
    error_reporting(E_ALL);
//*/

include "conecta.php";

function obter_colaboradores_empresa() {
    $empId = $_GET["empresa_id"] ?? "";
    $cond = "";
    if ($empId === "" || $empId === "0" || $empId === "sem_filial") {
        $cond = " AND (e.enti_nb_empresa IS NULL OR e.enti_nb_empresa = 0)";
    } else {
        $cond = " AND e.enti_nb_empresa = " . (int)$empId;
    }
    $sqlColabs = query("SELECT e.enti_nb_id, e.enti_tx_nome, e.enti_tx_matricula 
                        FROM entidade e 
                        LEFT JOIN operacao o ON e.enti_tx_tipoOperacao = o.oper_nb_id
                        WHERE e.enti_tx_status = 'ativo' 
                          AND COALESCE(o.oper_tx_nome, '') <> 'Diretor'
                          {$cond} 
                        ORDER BY e.enti_tx_nome ASC");
    $list = [];
    if ($sqlColabs) {
        while ($row = mysqli_fetch_assoc($sqlColabs)) {
            $list[] = [
                "id" => $row["enti_nb_id"],
                "nome" => $row["enti_tx_nome"] . ($row["enti_tx_matricula"] ? " (Matrícula: " . $row["enti_tx_matricula"] . ")" : "")
            ];
        }
    }
    ob_clean();
    echo json_encode($list);
    exit;
}

function obterUltimaEntregaAjax() {
    $colaborador_id = (int)($_GET["colaborador_id"] ?? 0);
    $epi_id = (int)($_GET["epi_id"] ?? 0);
    $empresa_id = isset($_GET["empresa_id"]) ? (int)$_GET["empresa_id"] : 0;
    ob_clean();
    if ($colaborador_id <= 0 || $epi_id <= 0) {
        echo json_encode(null);
        exit;
    }
    $condEmpresa = "";
    if ($empresa_id > 0) {
        $condEmpresa = " AND (dev.ss_e_nb_empresa_id = {$empresa_id} OR dev.ss_e_nb_empresa_id IS NULL OR dev.ss_e_nb_empresa_id = 0)";
    }
    $sql = query(
        "SELECT dev.ss_e_nb_id, dev.ss_e_nb_quantidade, dev.ss_e_tx_variacao,
                DATE_FORMAT(dev.ss_e_tx_data_entrega, '%d/%m/%Y') AS data_entrega_fmt,
                dev.ss_e_tx_status,
                IFNULL(emp.empr_tx_nome, 'Matriz') AS filial_nome
         FROM ss_epi_entrega dev
         LEFT JOIN empresa emp ON dev.ss_e_nb_empresa_id = emp.empr_nb_id
         WHERE dev.ss_e_nb_colaborador_id = ? AND dev.ss_e_nb_epi_id = ?
           AND dev.ss_e_tx_status NOT IN ('inativo')
           {$condEmpresa}
         ORDER BY dev.ss_e_tx_data_entrega DESC, dev.ss_e_nb_id DESC
         LIMIT 1",
        "ii",
        [$colaborador_id, $epi_id]
    );
    $info = null;
    if ($sql && $row = mysqli_fetch_assoc($sql)) {
        $info = [
            "entrega_id" => (int)$row["ss_e_nb_id"],
            "quantidade" => (int)$row["ss_e_nb_quantidade"],
            "variacao" => $row["ss_e_tx_variacao"] ?? "",
            "data_entrega" => $row["data_entrega_fmt"],
            "status" => $row["ss_e_tx_status"],
            "filial" => $row["filial_nome"]
        ];
    }
    echo json_encode($info);
    exit;
}

function cadastrarDevolucaoLoteAjax() {
    $lotes = json_decode($_POST["lotes"] ?? "[]", true);
    $colaborador_id = (int)($_POST["colaborador_id"] ?? 0);
    $data_devolucao = $_POST["data_devolucao"];

    if (empty($data_devolucao)) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Data da devolução inválida."]);
        exit;
    }

    $sucessos = 0;
    $erros = [];
    $userCadastro = $_SESSION["user_nb_id"] ?? 0;
    $dataCadastro = date("Y-m-d H:i:s");

    foreach ($lotes as $item) {
        // Colaborador do próprio item (multi-colaborador no mesmo lote), com fallback para o POST
        $item_colaborador_id = !empty($item["colaborador_id"]) ? (int)$item["colaborador_id"] : $colaborador_id;
        if ($item_colaborador_id <= 0) {
            $erros[] = "Colaborador inválido para o EPI ID {$epi_id}.";
            continue;
        }
        $epi_id = (int)($item["epi_id"] ?? 0);
        $quantidade = (int)($item["quantidade"] ?? 0);
        $status = $item["status"] ?? "devolvido";
        $variacao = !empty($item["variacao"]) ? trim($item["variacao"]) : null;
        $empresa_id = !empty($item["empresa_id"]) ? (int)$item["empresa_id"] : null;
        if ($empresa_id === 0) {
            $empresa_id = null; // Matriz
        }
        $justificativa = trim($item["justificativa"] ?? "");
        $estornar = !empty($item["estornar"]) ? "sim" : "nao";
        $destino = in_array($item["destino"] ?? "", ["estoque", "inspecao", "nenhum"]) ? $item["destino"] : "nenhum";
        // Perdido não tem destino: o item não volta nem vai para inspeção
        if ($status === "perdido") {
            $destino = "nenhum";
        }

        if (!in_array($status, ["devolvido", "substituido", "perdido"])) {
            $erros[] = "Tipo de evento inválido para o EPI ID {$epi_id}.";
            continue;
        }
        if (empty($justificativa)) {
            $erros[] = "Justificativa obrigatória para o EPI ID {$epi_id}.";
            continue;
        }
        if ($quantidade <= 0) {
            $erros[] = "Quantidade inválida para o EPI ID {$epi_id}.";
            continue;
        }

        $epi = carregar("ss_epi", $epi_id);
        if (empty($epi)) {
            $erros[] = "EPI não encontrado para ID {$epi_id}.";
            continue;
        }
        $temVariacoes = !empty($epi["ss_e_tx_variacoes"]);
        if ($temVariacoes && empty($variacao)) {
            $erros[] = "O EPI '{$epi['ss_e_tx_subgrupo']} / {$epi['ss_e_tx_item']}' possui variações cadastradas. Selecione a variação (numeração/tamanho).";
            continue;
        }

        // Vincula automaticamente a entrega anterior do mesmo EPI para o mesmo colaborador
        $entrega_anterior_id = null;
        $sqlAnt = query(
            "SELECT ss_e_nb_id FROM ss_epi_entrega
             WHERE ss_e_nb_colaborador_id = ? AND ss_e_nb_epi_id = ?
               AND ss_e_tx_status NOT IN ('inativo')
             ORDER BY ss_e_tx_data_entrega DESC, ss_e_nb_id DESC
             LIMIT 1",
            "ii",
            [$item_colaborador_id, $epi_id]
        );
        if ($sqlAnt && $rowAnt = mysqli_fetch_assoc($sqlAnt)) {
            $entrega_anterior_id = (int)$rowAnt["ss_e_nb_id"];
        }

        $devolucao = [
            "ss_e_nb_colaborador_id"      => $item_colaborador_id,
            "ss_e_nb_epi_id"              => $epi_id,
            "ss_e_nb_empresa_id"          => $empresa_id,
            "ss_e_tx_variacao"            => $variacao,
            "ss_e_tx_data_entrega"        => $data_devolucao,
            "ss_e_nb_quantidade"          => $quantidade,
            "ss_e_tx_vencimento"          => null,
            "ss_e_tx_status"              => $status,
            "ss_e_tx_assinatura"          => "",
            "ss_e_tx_foto"                => "",
            "ss_e_tx_justificativa"       => $justificativa,
            "ss_e_tx_estornado"           => ($destino === "estoque") ? "sim" : "nao",
            "ss_e_tx_destino"             => ($destino === "nenhum") ? null : $destino,
            "ss_e_tx_status_inspecao"     => ($destino === "inspecao") ? "pendente" : null,
            "ss_e_tx_data_devolucao"      => $data_devolucao,
            "ss_e_nb_entrega_anterior_id" => $entrega_anterior_id,
            "ss_e_nb_userCadastro"        => $userCadastro,
            "ss_e_tx_dataCadastro"        => $dataCadastro
        ];

        $res = inserir("ss_epi_entrega", array_keys($devolucao), array_values($devolucao));
        if ($res && !is_a($res[0], 'Exception')) {
            // Devolução/perda não geram baixa;
            // destino 'estoque' devolve a quantidade imediatamente; 'inspecao' aguarda aprovação na página de inspeção
            if (in_array($status, ["devolvido", "substituido"]) && $destino === "estoque") {
                $motivoEntrada = ($status === "substituido")
                    ? 'Devolução de EPI substituído (colaborador ID: ' . $item_colaborador_id . ')'
                    : 'Devolução de EPI (colaborador ID: ' . $item_colaborador_id . ')';
                registrarMovimentacaoEstoque($epi_id, $quantidade, 'entrada', $motivoEntrada, null, null, '', null, null, $empresa_id, null, null, $variacao);
            }
            $sucessos++;
        } else {
            $erros[] = "Erro ao registrar devolução do EPI ID {$epi_id} no banco de dados.";
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

function layoutDevolucao() {
    cabecalho("Lançar Devolução de EPI");

    $temFiliais = ss_tem_filiais_cadastradas();

    // EPIs: TODOS os ativos do tipo estoque (independente de saldo)
    $sqlEpis = query("SELECT ss_e_nb_id, ss_e_tx_grupo, ss_e_tx_subgrupo, ss_e_tx_item, ss_e_tx_ca, ss_e_tx_variacoes, ss_e_tx_foto
                      FROM ss_epi
                      WHERE ss_e_tx_status = 'ativo' AND ss_e_tx_cadastro_tipo = 'estoque'
                      ORDER BY ss_e_tx_grupo ASC, ss_e_tx_subgrupo ASC, ss_e_tx_item ASC");
    $allEpis = [];
    $epiOptions = ["" => "Selecione o EPI"];
    if ($sqlEpis) {
        while ($row = mysqli_fetch_assoc($sqlEpis)) {
            $allEpis[] = [
                "id" => (int)$row["ss_e_nb_id"],
                "nome" => ($row["ss_e_tx_subgrupo"] ?? "") . " / " . ($row["ss_e_tx_item"] ?? "") . " / " . $row["ss_e_tx_grupo"] . " (CA: " . ($row["ss_e_tx_ca"] ?? "N/A") . ")",
                "variacoes" => $row["ss_e_tx_variacoes"] ?? ""
            ];
            $epiOptions[$row["ss_e_nb_id"]] = $allEpis[count($allEpis) - 1]["nome"];
        }
    }

    // Saldos por EPI/filial (apenas para informação)
    $sqlBalances = query("SELECT ss_e_nb_epi_id, IFNULL(ss_e_nb_empresa_id, 0) AS empresa_id,
                                 SUM(CASE WHEN ss_e_tx_tipo = 'entrada' THEN ss_e_nb_quantidade ELSE -ss_e_nb_quantidade END) AS saldo
                          FROM ss_epi_estoque
                          GROUP BY ss_e_nb_epi_id, empresa_id");
    $epiBalances = [];
    $user_empresa = !empty($_SESSION["user_nb_empresa"]) ? (int)$_SESSION["user_nb_empresa"] : 0;
    if ($sqlBalances) {
        while ($row = mysqli_fetch_assoc($sqlBalances)) {
            $epiId = (int)$row["ss_e_nb_epi_id"];
            $empId = (int)$row["empresa_id"];
            $saldo = (int)$row["saldo"];
            if ($empId == 0 || $empId == $user_empresa) {
                $empId = $user_empresa;
            }
            if (!isset($epiBalances[$epiId])) {
                $epiBalances[$epiId] = [];
            }
            if (!isset($epiBalances[$epiId][$empId])) {
                $epiBalances[$epiId][$empId] = 0;
            }
            $epiBalances[$epiId][$empId] += $saldo;
        }
    }

    $empresaOptions = ["" => "Todas as Empresas"];
    $sqlEmpresas = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
    $empresasJsArr = [0 => "Matriz"];
    if ($sqlEmpresas) {
        while ($rowEmp = mysqli_fetch_assoc($sqlEmpresas)) {
            $empresaOptions[$rowEmp["empr_nb_id"]] = $rowEmp["empr_tx_nome"];
            $empresasJsArr[(int)$rowEmp["empr_nb_id"]] = $rowEmp["empr_tx_nome"];
        }
    }

    $fields = [];
    // Campo Empresa sempre visível (mesmo sem filiais): por padrão mostra a empresa em que
    // o usuário está logado — e os colaboradores são carregados a partir dela
    $empresaPadrao = !empty($_SESSION["user_nb_empresa"]) ? (int)$_SESSION["user_nb_empresa"] : "";
    if ($empresaPadrao !== "" && !isset($empresaOptions[$empresaPadrao])) {
        $empresaPadrao = "";
    }
    $fields[] = combo("Empresa", "empresa_id", $_POST["empresa_id"] ?? $empresaPadrao, 6, $empresaOptions);
    $fields[] = campo_data("Data da Devolução*", "data_devolucao", date("Y-m-d"), 6);
    $campo_colaborador = "
        <div class='col-sm-12 margin-bottom-5 campo-fit-content' id='container_colaborador_lista' style='position: relative;'>
            <label>Colaborador* (marque um ou mais — busque por nome ou matrícula)</label>
            <input type='text' id='busca_colaborador_lista' class='form-control input-sm' placeholder='Clique aqui e filtre por nome ou matrícula...' autocomplete='off'>
            <div id='lista_colaboradores_checkboxes' style='display: none; position: absolute; z-index: 1050; left: 15px; right: 15px; max-height: 220px; overflow-y: auto; border: 1px solid #ccc; border-radius: 4px; padding: 8px; background: #fff; box-shadow: 0 6px 18px rgba(0,0,0,0.15);'></div>
            <div id='colaboradores_selecionados_tags' style='margin-top: 6px;'></div>
            <span class='help-block' id='contador_colaboradores_selecionados' style='font-size: 11px; margin-bottom: 0;'>Nenhum colaborador selecionado.</span>
        </div>
    ";

    $campo_epi = "
        <div class='col-sm-12 margin-bottom-5 campo-fit-content' id='container_epi_lista' style='position: relative;'>
            <label>EPI* (marque um ou mais itens — busque por nome ou CA)</label>
            <input type='text' id='busca_epi_lista' class='form-control input-sm' placeholder='Clique aqui e filtre por nome, grupo ou CA...' autocomplete='off'>
            <div id='lista_epis_checkboxes' style='display: none; position: absolute; z-index: 1050; left: 15px; right: 15px; max-height: 260px; overflow-y: auto; border: 1px solid #ccc; border-radius: 4px; padding: 8px; background: #fff; box-shadow: 0 6px 18px rgba(0,0,0,0.15);'></div>
            <div id='epis_selecionados_tags' style='margin-top: 6px;'></div>
            <span class='help-block' id='contador_epis_selecionados' style='font-size: 11px; margin-bottom: 0;'>Nenhum EPI selecionado.</span>
        </div>
    ";

    $campo_tipo = combo("Tipo de Evento*", "tipo_evento", "devolvido", 3, [
        "devolvido" => "Devolvido",
        "substituido" => "Substituído",
        "perdido" => "Perdido/Extraviado"
    ]);

    $campo_lista_selecionados = "
        <div class='col-sm-12 margin-bottom-5 campo-fit-content'>
            <label>EPIs Selecionados (ajuste a quantidade de cada um)</label>
            <div class='table-responsive' style='max-height: 260px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;'>
                <table class='table table-striped table-bordered table-hover' style='margin-bottom: 0;'>
                    <thead>
                        <tr style='background-color: #f9f9f9;'>
                            <th>EPI</th>
                            <th style='width: 110px; text-align: center;'>Quantidade</th>
                            <th style='width: 60px; text-align: center;'>Ações</th>
                        </tr>
                    </thead>
                    <tbody id='tbody_epis_selecionados'>
                        <tr><td colspan='3' class='text-center text-muted' style='padding: 15px;'>Nenhum EPI selecionado.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    ";

    // Botão de adicionar alinhado à direita, na mesma linha do tipo de evento
    $campo_botao_adicionar = "
        <div class='col-sm-9 margin-bottom-5 campo-fit-content'>
            <div style='padding-top: 24px; text-align: right;'>
                <button type='button' class='btn btn-primary' id='btn_adicionar_item' onclick='adicionarItemALista()'><i class='fa fa-plus'></i> Adicionar à Lista</button>
            </div>
        </div>
    ";

    $campo_status_hiddens = '
        <input type="hidden" name="status_justificativa" id="status_justificativa" value="">
        <input type="hidden" name="status_estornar" id="status_estornar" value="0">
    ';

    $buttons = [
        '<a href="inspecao_devolucao_epi.php" class="btn btn-warning"><i class="fa fa-search"></i> Inspeção de Devoluções</a>',
        '<a href="devolucao_epi.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Voltar à Gestão de Devoluções</a>'
    ];

    echo abre_form("Dados da Devolução");
    echo linha_form($fields);
    echo linha_form([$campo_colaborador]);

    // Seção interna: Seleção de EPIs
    echo '
    <div class="portlet light bordered" style="margin-top: 5px; margin-bottom: 5px;">
        <div class="portlet-title" style="margin-bottom: 5px;">
            <div class="caption font-blue">
                <i class="fa fa-shield"></i>
                <span class="caption-subject bold uppercase">Seleção de EPIs</span>
            </div>
        </div>
        <div class="portlet-body">';
    echo linha_form([$campo_epi]);
    echo linha_form([$campo_lista_selecionados]);
    echo linha_form([$campo_tipo, $campo_botao_adicionar]);
    echo '
        </div>
    </div>';

    echo $campo_status_hiddens;
    echo fecha_form($buttons);

    echo '
    <div class="row">
        <div class="col-sm-12">
            <div class="portlet light bordered" id="container_acoes_globais" style="display: none; margin-top: 15px;">
                <div class="portlet-title">
                    <div class="caption font-green-haze">
                        <i class="fa fa-list font-green-haze"></i>
                        <span class="caption-subject bold uppercase">Lista de Devoluções</span>
                    </div>
                    <div class="actions">
                        <button type="button" class="btn btn-success" onclick="gravarDevolucoes()"><i class="fa fa-save"></i> Gravar Devoluções</button>
                    </div>
                </div>
                <div class="portlet-body">
                    <div id="container_listas_devolucoes"></div>
                </div>
            </div>
        </div>
    </div>
    ';

    echo "<script>
    var allEpis = " . json_encode($allEpis) . ";
    var epiBalances = " . json_encode($epiBalances) . ";
    var empresasNomes = " . json_encode($empresasJsArr) . ";
    var userEmpresaId = " . (int)$user_empresa . ";
    var itensDevolucaoLote = [];
    ";

    echo "
    // O script é renderizado no final da página (DOM já carregado) — escopo global,
    // permitindo que as funções sejam chamadas pelos botões (onclick) e pelo lote.
    var allColaboradores = [];
    var colaboradoresSelecionadosMap = {};

    function montarListaColaboradores(colabs) {
            allColaboradores = colabs || [];
            var container = $('#lista_colaboradores_checkboxes');
            container.empty();
            if (allColaboradores.length === 0) {
                container.append('<div class=\"text-muted\" style=\"padding: 8px;\">Nenhum colaborador encontrado para a empresa selecionada.</div>');
            }
            allColaboradores.forEach(function(colab) {
                var row = $('<div>', { class: 'colaborador_checkbox_item', 'data-termo': (colab.nome || '').toLowerCase() })
                    .css({ display: 'flex', alignItems: 'center', gap: '8px', padding: '5px 6px', borderBottom: '1px solid #eee', cursor: 'pointer' });
                var chk = $('<input>', { type: 'checkbox', class: 'chk_seleciona_colaborador', value: colab.id })
                    .css({ minWidth: '16px', height: '16px', position: 'relative' });
                if (colaboradoresSelecionadosMap[colab.id]) {
                    chk.prop('checked', true);
                }
                var lbl = $('<label>', { style: 'margin: 0; font-weight: normal; cursor: pointer;' }).text(colab.nome);
                row.append(chk).append(lbl);
                row.on('click', function(e) {
                    if (e.target !== chk[0] && e.target !== lbl[0] && !$(e.target).is('input')) {
                        chk.prop('checked', !chk.prop('checked')).trigger('change');
                    }
                });
                container.append(row);
            });
            atualizarTagsColaboradores();
        }

        function atualizarTagsColaboradores() {
            var container = $('#colaboradores_selecionados_tags');
            container.empty();
            colaboradoresSelecionadosMap = {};
            var total = 0;
            $('#lista_colaboradores_checkboxes .chk_seleciona_colaborador:checked').each(function() {
                var id = $(this).val();
                var colab = allColaboradores.find(function(c) { return c.id == id; });
                if (!colab) return;
                total++;
                colaboradoresSelecionadosMap[id] = colab.nome;
                var tag = $('<span>', {
                    class: 'label label-success tag_colaborador_selecionado',
                    style: 'margin: 2px; font-size: 11px; font-weight: normal; display: inline-flex; align-items: center; gap: 6px; padding: 5px 9px; border-radius: 4px;'
                }).text(colab.nome);
                var btn = $('<span>', { style: 'cursor: pointer; font-weight: bold; font-size: 13px;', title: 'Remover seleção' }).html('&times;');
                btn.on('click', function() {
                    $('#lista_colaboradores_checkboxes .chk_seleciona_colaborador[value=\"' + id + '\"]').prop('checked', false).trigger('change');
                });
                tag.append(btn);
                container.append(tag);
            });
            $('#contador_colaboradores_selecionados').text(total > 0 ? total + ' colaborador(es) selecionado(s).' : 'Nenhum colaborador selecionado.');
        }

        function filtrarListaColaboradores() {
            var termo = $.trim($('#busca_colaborador_lista').val().toLowerCase());
            $('#lista_colaboradores_checkboxes .colaborador_checkbox_item').each(function() {
                var item = $(this);
                var marcado = item.find('.chk_seleciona_colaborador').is(':checked');
                var corresponde = (termo === '') || (item.attr('data-termo').indexOf(termo) !== -1);
                item.toggle(marcado || corresponde);
            });
        }

        function obterColaboradoresSelecionados() {
            var lista = [];
            $('#lista_colaboradores_checkboxes .chk_seleciona_colaborador:checked').each(function() {
                var id = $(this).val();
                var colab = allColaboradores.find(function(c) { return c.id == id; });
                if (colab) {
                    lista.push({ id: colab.id, nome: colab.nome });
                }
            });
            return lista;
        }

        function carregarColaboradoresEmpresa(callback) {
            const empresaId = $('select[name=\"empresa_id\"]').val() || '0';
            $('#lista_colaboradores_checkboxes').html('<div class=\"text-muted\" style=\"padding: 8px;\">Carregando colaboradores...</div>');
            $.getJSON('lancar_devolucao_epi.php?acao=obter_colaboradores_empresa&empresa_id=' + empresaId, function(data) {
                montarListaColaboradores(data);
                if (typeof callback === 'function') callback();
            });
        }

        var quantidadesEpis = {};

        function montarListaEpis() {
            var container = $('#lista_epis_checkboxes');
            container.empty();
            allEpis.forEach(function(epi) {
                const balances = epiBalances[epi.id] || {};
                let saldoTotal = 0;
                Object.keys(balances).forEach(function(eid) { saldoTotal += balances[eid] || 0; });
                var row = $('<div>', { class: 'epi_checkbox_item', 'data-termo': (epi.nome || '').toLowerCase() })
                    .css({ display: 'flex', alignItems: 'center', gap: '8px', padding: '5px 6px', borderBottom: '1px solid #eee', cursor: 'pointer' });
                var chk = $('<input>', { type: 'checkbox', class: 'chk_seleciona_epi', value: epi.id })
                    .css({ minWidth: '16px', height: '16px', position: 'relative' });
                var lbl = $('<label>', { style: 'margin: 0; font-weight: normal; cursor: pointer;' })
                    .text(epi.nome + ' (Saldo total: ' + saldoTotal + ')');
                row.append(chk).append(lbl);
                row.on('click', function(e) {
                    if (e.target !== chk[0] && e.target !== lbl[0] && !$(e.target).is('input')) {
                        chk.prop('checked', !chk.prop('checked')).trigger('change');
                    }
                });
                container.append(row);
            });
            atualizarTabelaSelecionados();
        }

        function atualizarContadorEpis() {
            var total = $('#lista_epis_checkboxes .chk_seleciona_epi:checked').length;
            $('#contador_epis_selecionados').text(total > 0 ? total + ' EPI(s) selecionado(s).' : 'Nenhum EPI selecionado.');
        }

        // Lista (tabela) de EPIs selecionados: quantidade editável e remoção por item
        function atualizarTabelaSelecionados() {
            var tbody = $('#tbody_epis_selecionados');
            tbody.empty();
            var total = 0;
            $('#lista_epis_checkboxes .chk_seleciona_epi:checked').each(function() {
                var id = $(this).val();
                var epi = allEpis.find(function(e) { return e.id == id; });
                if (!epi) return;
                total++;
                var tr = $('<tr>');
                tr.append($('<td style=\"vertical-align: middle;\">').text(epi.nome));
                var tdQtd = $('<td style=\"text-align: center; vertical-align: middle;\">');
                var inputQtd = $('<input>', { type: 'number', class: 'form-control input-sm qtd_epi_selecionado', min: '1', 'data-epi-id': id })
                    .css({ width: '70px', textAlign: 'center', margin: 'auto' })
                    .val(quantidadesEpis[id] || 1);
                tdQtd.append(inputQtd);
                tr.append(tdQtd);
                var tdAcao = $('<td style=\"text-align: center; vertical-align: middle;\">');
                var btnRemover = $('<button>', { type: 'button', class: 'btn btn-danger btn-xs' }).html('<i class=\"fa fa-trash\"></i>');
                btnRemover.on('click', function() {
                    $('#lista_epis_checkboxes .chk_seleciona_epi[value=\"' + id + '\"]').prop('checked', false).trigger('change');
                });
                tdAcao.append(btnRemover);
                tr.append(tdAcao);
                tbody.append(tr);
            });
            if (total === 0) {
                tbody.append('<tr><td colspan=\"3\" class=\"text-center text-muted\" style=\"padding: 15px;\">Nenhum EPI selecionado.</td></tr>');
            }
            atualizarContadorEpis();
        }

        function filtrarListaEpis() {
            var termo = $.trim($('#busca_epi_lista').val().toLowerCase());
            $('#lista_epis_checkboxes .epi_checkbox_item').each(function() {
                var item = $(this);
                var marcado = item.find('.chk_seleciona_epi').is(':checked');
                var corresponde = (termo === '') || (item.attr('data-termo').indexOf(termo) !== -1);
                // Itens marcados permanecem visíveis mesmo com filtro ativo
                item.toggle(marcado || corresponde);
            });
        }

        function limparSelecaoEpis() {
            $('#lista_epis_checkboxes .chk_seleciona_epi').prop('checked', false).trigger('change');
            $('#busca_epi_lista').val('');
            quantidadesEpis = {};
            filtrarListaEpis();
            atualizarTabelaSelecionados();
        }

        function obterEpisSelecionados() {
            var ids = [];
            $('#lista_epis_checkboxes .chk_seleciona_epi:checked').each(function() {
                ids.push($(this).val());
            });
            return ids;
        }

        function exibirInfoUltimaEntrega() {
            const colabs = obterColaboradoresSelecionados();
            const epiIds = obterEpisSelecionados();
            $('#info_ultima_entrega').remove();
            // Mostra a última entrega apenas quando houver um único colaborador e um único EPI selecionados
            if (colabs.length !== 1 || epiIds.length !== 1) return;
            const colabId = colabs[0].id;
            const epiId = epiIds[0];
            $.getJSON('lancar_devolucao_epi.php?acao=obterUltimaEntregaAjax&colaborador_id=' + colabId + '&epi_id=' + epiId, function(info) {
                if (!info) {
                    $('<div id=\"info_ultima_entrega\" class=\"alert alert-warning\" style=\"margin-top: 10px; padding: 8px 12px; font-size: 12px;\"><i class=\"fa fa-info-circle\"></i> Nenhuma entrega anterior encontrada para este colaborador/EPI.</div>').insertAfter($('#lista_epis_checkboxes'));
                    return;
                }
                var statusTxt = {ativo:'Entregue', substituido:'Substituído', devolvido:'Devolvido', perdido:'Perdido'}[info.status] || info.status;
                $('<div id=\"info_ultima_entrega\" class=\"alert alert-info\" style=\"margin-top: 10px; padding: 8px 12px; font-size: 12px;\"><i class=\"fa fa-check-circle\"></i> <strong>Última entrega:</strong> ' + info.data_entrega + ' | Qtd: ' + info.quantidade + ' | Status: ' + statusTxt + ' | Filial: ' + info.filial + (info.variacao ? ' | Var: ' + info.variacao : '') + '</div>').insertAfter($('#lista_epis_checkboxes'));
            });
        }

        function perguntarJustificativaStatus(status, callbackOk, callbackCancel) {
            var titulo = ''; var texto = ''; var mostrarDestino = (status !== 'perdido');
            if (status === 'substituido') { titulo = 'Substituição de EPI'; texto = 'O colaborador devolveu um EPI do mesmo tipo que está sendo substituído? Informe o motivo.'; }
            else if (status === 'devolvido') { titulo = 'Devolução de EPI'; texto = 'Informe o motivo da devolução do EPI.'; }
            else { titulo = 'Perda / Extravio de EPI'; texto = 'Registre a justificativa da perda ou extravio.'; }
            var html = '<div style=\"text-align: left;\">' +
                '<p style=\"margin-bottom: 10px;\">' + texto + '</p>' +
                '<div class=\"form-group\">' +
                    '<label for=\"swal_justificativa_status\">Justificativa*:</label>' +
                    '<textarea id=\"swal_justificativa_status\" class=\"form-control\" rows=\"3\" placeholder=\"Detalhe o motivo...\"></textarea>' +
                '</div>';
            if (mostrarDestino) {
                var labelDestino = (status === 'substituido') ? 'O que fazer com o EPI antigo devolvido?' : 'O que fazer com o EPI devolvido?';
                html += '<div class=\"form-group\" style=\"margin-top: 10px;\">' +
                    '<label style=\"font-weight: bold;\">' + labelDestino + '</label>' +
                    '<select id=\"swal_destino_status\" class=\"form-control\">' +
                        '<option value=\"estoque\">Retornar ao estoque imediatamente</option>' +
                        '<option value=\"inspecao\" selected>Aguardar inspeção (pendência)</option>' +
                        '<option value=\"nenhum\">Descarte de EPI</option>' +
                    '</select>' +
                    '<small class=\"text-muted\">Itens em inspeção são analisados na página de Inspeção de Devoluções (retorno ao estoque ou descarte).</small>' +
                '</div>';
            }
            html += '</div>';
            Swal.fire({
                title: titulo,
                html: html,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Confirmar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const j = document.getElementById('swal_justificativa_status').value;
                    if (!j || j.trim() === '') { Swal.showValidationMessage('A justificativa é obrigatória!'); return false; }
                    var destino = 'nenhum';
                    if (mostrarDestino) destino = document.getElementById('swal_destino_status').value;
                    return { justificativa: j, destino: destino };
                }
            }).then((result) => {
                if (result.isConfirmed) callbackOk(result.value.justificativa, result.value.destino);
                else if (typeof callbackCancel === 'function') callbackCancel();
            });
        }

        function desenharListas() {
            var container = $('#container_listas_devolucoes');
            container.empty();
            if (itensDevolucaoLote.length === 0) { $('#container_acoes_globais').hide(); return; }
            $('#container_acoes_globais').show();

            // Agrupa os itens por colaborador
            var grupos = {};
            itensDevolucaoLote.forEach(function(item) {
                if (!grupos[item.colaborador_id]) {
                    grupos[item.colaborador_id] = {
                        colaborador_id: item.colaborador_id,
                        colaborador_nome: item.colaborador_nome || 'Colaborador ' + item.colaborador_id,
                        itens: []
                    };
                }
                grupos[item.colaborador_id].itens.push(item);
            });

            Object.keys(grupos).forEach(function(colabId) {
                var grupo = grupos[colabId];

                var tableHtml = '<div class=\"portlet box green-haze\" style=\"margin-bottom: 20px;\">' +
                    '<div class=\"portlet-title\">' +
                        '<div class=\"caption\">' +
                            '<i class=\"fa fa-user\"></i> Colaborador: <strong>' + grupo.colaborador_nome + '</strong> (' + grupo.itens.length + ' item(ns))' +
                        '</div>' +
                    '</div>' +
                    '<div class=\"portlet-body\">' +
                    '<div class=\"table-responsive\"><table class=\"table table-striped table-bordered table-hover\">' +
                    '<thead><tr>' +
                        '<th>EPI</th>' +
                        '<th style=\"text-align: center; width: 70px;\">Qtd</th>' +
                        '<th style=\"width: 110px;\">Variação</th>' +
                        '<th style=\"text-align: center; width: 130px;\">Tipo</th>' +
                        '<th style=\"width: 220px;\">Justificativa</th>' +
                        '<th style=\"text-align: center; width: 170px;\">Destino</th>' +
                        '<th style=\"text-align: center; width: 60px;\">Ações</th>' +
                    '</tr></thead><tbody>';

                grupo.itens.forEach(function(item) {
                    var tipoLabel = {devolvido:'<span class=\"label label-info\">Devolvido</span>', substituido:'<span class=\"label label-warning\">Substituído</span>', perdido:'<span class=\"label label-danger\">Perdido</span>'}[item.status] || item.status;
                    var destinoHtml;
                    if (item.status === 'perdido') {
                        destinoHtml = '<span class=\"label label-default\">Sem destino</span>';
                    } else if (item.destino === 'estoque') {
                        destinoHtml = '<span class=\"label label-success\">Retorna ao estoque</span>';
                    } else if (item.destino === 'inspecao') {
                        destinoHtml = '<span class=\"label label-warning\">Pendência de inspeção</span>';
                    } else {
                        destinoHtml = '<span class=\"label label-danger\">Descarte de EPI</span>';
                    }
                    // Variação resolvida na sacola (itens com variações cadastradas)
                    var variacaoCellHtml = (item.variacao || '-');
                    if (item.variacoes) {
                        var listaVar = item.variacoes.split(',').map(function(v) { return v.trim(); }).filter(Boolean);
                        if (listaVar.length > 0) {
                            var opts = '<option value=\"\">Selecione</option>';
                            listaVar.forEach(function(v) {
                                opts += '<option value=\"' + v + '\"' + (item.variacao === v ? ' selected' : '') + '>' + v + '</option>';
                            });
                            variacaoCellHtml = '<select class=\"form-control input-sm sel_var_devolucao\" data-unique=\"' + item.unique_id + '\">' + opts + '</select>';
                        }
                    }
                    tableHtml += '<tr>' +
                        '<td>' + destacarItemEpi(item.epi_nome) + '</td>' +
                        '<td style=\"text-align: center; font-weight: bold;\">' + item.quantidade + '</td>' +
                        '<td style=\"text-align: center;\">' + variacaoCellHtml + '</td>' +
                        '<td style=\"text-align: center;\">' + tipoLabel + '</td>' +
                        '<td style=\"font-size: 12px;\">' + item.justificativa + '</td>' +
                        '<td style=\"text-align: center;\">' + destinoHtml + '</td>' +
                        '<td style=\"text-align: center;\"><button type=\"button\" class=\"btn btn-danger btn-xs\" onclick=\"removerItem(\'' + item.unique_id + '\')\"><i class=\"fa fa-trash\"></i></button></td>' +
                    '</tr>';
                });

                tableHtml += '</tbody></table></div>' +
                    '</div></div>';
                container.append(tableHtml);
            });
        }

        $('select[name=\"empresa_id\"]').on('change', function() {
            // Ao trocar de empresa, limpa a seleção de colaboradores e recarrega a lista
            $('#lista_colaboradores_checkboxes .chk_seleciona_colaborador').prop('checked', false);
            carregarColaboradoresEmpresa();
            exibirInfoUltimaEntrega();
        });

        // Filtro e seleção de colaboradores (abre ao clicar no filtro)
        $('#busca_colaborador_lista').on('focus', function() {
            filtrarListaColaboradores();
            $('#lista_colaboradores_checkboxes').show();
        });
        $('#busca_colaborador_lista').on('input', function() {
            filtrarListaColaboradores();
            $('#lista_colaboradores_checkboxes').show();
        });
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#container_colaborador_lista').length) {
                $('#lista_colaboradores_checkboxes').hide();
            }
        });
        $(document).on('change', '.chk_seleciona_colaborador', function() {
            atualizarTagsColaboradores();
            filtrarListaColaboradores();
            exibirInfoUltimaEntrega();
        });

        // Filtro e seleção dos EPIs na lista com checkboxes (abre ao clicar no filtro)
        $('#busca_epi_lista').on('focus', function() {
            filtrarListaEpis();
            $('#lista_epis_checkboxes').show();
        });
        $('#busca_epi_lista').on('input', function() {
            filtrarListaEpis();
            $('#lista_epis_checkboxes').show();
        });
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#container_epi_lista').length) {
                $('#lista_epis_checkboxes').hide();
            }
        });
        $(document).on('change', '.chk_seleciona_epi', function() {
            atualizarTabelaSelecionados();
            filtrarListaEpis();
            exibirInfoUltimaEntrega();
        });

        // Mantém as quantidades ajustadas na tabela de selecionados
        $(document).on('input change', '.qtd_epi_selecionado', function() {
            var id = $(this).attr('data-epi-id');
            var qtd = parseInt($(this).val(), 10);
            quantidadesEpis[id] = (qtd > 0) ? qtd : 1;
        });

        // Seleção de variação na sacola
        $(document).on('change', '.sel_var_devolucao', function() {
            var uniqueId = $(this).attr('data-unique');
            var item = itensDevolucaoLote.find(function(it) { return it.unique_id === uniqueId; });
            if (item) {
                item.variacao = $(this).val() || '';
            }
        });

        montarListaEpis();
        carregarColaboradoresEmpresa();

    function adicionarItemALista() {
        var colabs = obterColaboradoresSelecionados();
        if (colabs.length === 0) { alert('Marque ao menos um Colaborador na lista.'); return; }
        var empresaId = $('select[name=\"empresa_id\"]').length > 0 ? $('select[name=\"empresa_id\"]').val() : '0';
        if (empresaId === '') empresaId = '0';

        // Coleta os itens marcados com a quantidade definida na tabela de selecionados
        var itensMarcados = [];
        $('#lista_epis_checkboxes .chk_seleciona_epi:checked').each(function() {
            var id = $(this).val();
            var epi = allEpis.find(function(e) { return e.id == id; });
            if (!epi) return;
            var qtd = quantidadesEpis[id] || 1;
            itensMarcados.push({ epi: epi, quantidade: qtd });
        });
        if (itensMarcados.length === 0) { alert('Marque ao menos um EPI na lista.'); return; }
        var tipoEvento = $('select[name=\"tipo_evento\"]').val();

        function adicionarTodos(justificativa, destino) {
            // Cruzamento: cada EPI selecionado é lançado para cada colaborador selecionado
            itensMarcados.forEach(function(m) {
                colabs.forEach(function(colab) {
                    adicionarItem(m.epi.id, m.epi.nome, m.quantidade, tipoEvento, empresaId, '', justificativa, destino, m.epi.variacoes, colab.id, colab.nome);
                });
            });
            limparSelecaoEpis();
            desenharListas();
        }

        // Sempre pergunta a justificativa e o destino (estoque / inspeção / descarte) a cada adição
        perguntarJustificativaStatus(tipoEvento, adicionarTodos, function() {
            alert('Operação cancelada: informe a justificativa para continuar.');
        });
    }

    function destacarItemEpi(nome) {
        // Formato esperado: Item / Subgrupo / Grupo (CA: X)
        var partes = nome.split(' / ');
        if (partes.length > 1) {
            return '<strong>' + partes[0] + '</strong> <small class=\"text-muted\">/ ' + partes.slice(1).join(' / ') + '</small>';
        }
        return '<strong>' + nome + '</strong>';
    }

    function adicionarItem(epiId, epiNome, quantidade, status, empresaId, variacao, justificativa, destino, variacoes, colabId, colabNome) {
        var uniqueId = new Date().getTime() + '_' + Math.random().toString(36).substr(2, 5);
        itensDevolucaoLote.push({
            unique_id: uniqueId,
            colaborador_id: colabId,
            colaborador_nome: colabNome || ('Colaborador ' + colabId),
            epi_id: epiId,
            epi_nome: epiNome,
            quantidade: quantidade,
            status: status,
            empresa_id: empresaId,
            variacao: variacao || '',
            variacoes: variacoes || '',
            justificativa: justificativa,
            destino: destino
        });
        desenharListas();
    }

    function removerItem(uniqueId) {
        itensDevolucaoLote = itensDevolucaoLote.filter(function(item) { return item.unique_id !== uniqueId; });
        desenharListas();
    }

    function gravarDevolucoes() {
        if (itensDevolucaoLote.length === 0) { alert('Adicione ao menos um item à lista.'); return; }
        
        // Todos os itens precisam de colaborador vinculado
        var semColaborador = itensDevolucaoLote.filter(function(item) { return !item.colaborador_id; });
        if (semColaborador.length > 0) {
            alert('Um ou mais itens estão sem colaborador vinculado. Remova e adicione novamente.');
            return;
        }
        
        // Itens com variações cadastradas precisam ter a variação selecionada na lista
        var semVariacao = itensDevolucaoLote.filter(function(item) {
            return item.variacoes && !item.variacao;
        });
        if (semVariacao.length > 0) {
            alert('Um ou mais itens possuem variações (numeração/tamanho) sem seleção. Selecione a variação de cada item na lista antes de gravar.');
            return;
        }
        
        if (!confirm('Deseja gravar ' + itensDevolucaoLote.length + ' devolução(ões)?')) return;
        var dataDevolucao = $('#data_devolucao').val();
        $.ajax({
            url: 'lancar_devolucao_epi.php?acao=cadastrarDevolucaoLoteAjax',
            type: 'POST',
            data: {
                colaborador_id: 0,
                data_devolucao: dataDevolucao,
                lotes: JSON.stringify(itensDevolucaoLote)
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' || response.status === 'partial') {
                    itensDevolucaoLote = [];
                    desenharListas();
                    var msg = response.sucessos > 0 ? 'Devoluções registradas com sucesso!' : 'Nenhuma devolução registrada.';
                    if (response.erros && response.erros.length > 0) msg += '\\n\\nFalhas:\\n' + response.erros.join('\\n');
                    Swal.fire({ title: 'Resultado', text: msg, icon: response.erros.length > 0 ? 'warning' : 'success' }).then(function() {
                        window.location.href = 'devolucao_epi.php';
                    });
                } else {
                    alert('Erro ao registrar devoluções: ' + (response.message || ''));
                }
            },
            error: function() { alert('Erro de comunicação com o servidor.'); }
        });
    }
    </script>
    ";

    rodape();
}

function index() {
    layoutDevolucao();
}
