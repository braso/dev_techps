<?php
ob_start();
/* Modo debug
    ini_set("display_errors", 1);
    error_reporting(E_ALL);
//*/

include "conecta.php";

function index() {
    cabecalho("Gestão de Devoluções de EPI");

    // ---- Filtros ----
    $empresaOptions = ["" => "Todas"];
    $sqlEmpresasFiltro = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
    if ($sqlEmpresasFiltro) {
        while ($rowEmpFiltro = mysqli_fetch_assoc($sqlEmpresasFiltro)) {
            $empresaOptions[$rowEmpFiltro["empr_nb_id"]] = $rowEmpFiltro["empr_tx_nome"];
        }
    }

    $fields = [
        campo_data("Data Início", "busca_data_inicio", $_POST["busca_data_inicio"] ?? "", 2),
        campo_data("Data Fim", "busca_data_fim", $_POST["busca_data_fim"] ?? "", 2),
        combo("Tipo de Evento", "busca_status", $_POST["busca_status"] ?? "", 2, ["" => "Todos", "devolvido" => "Devolvido", "substituido" => "Substituído", "perdido" => "Perdido/Extraviado"]),
        combo("Retornou ao Estoque?", "busca_estornado", $_POST["busca_estornado"] ?? "", 2, ["" => "Todos", "sim" => "Sim", "nao" => "Não"]),
        combo("Filial", "busca_filial", $_POST["busca_filial"] ?? "", 2, $empresaOptions),
        campo("Colaborador", "busca_nome_like", $_POST["busca_nome_like"] ?? "", 2),
        campo("EPI", "busca_epi_like", $_POST["busca_epi_like"] ?? "", 2)
    ];

    $buttons = [];
    $buttons[] = botao("Buscar", "index");
    $buttons[] = '<a href="lancar_devolucao_epi.php" class="btn btn-success"><i class="fa fa-arrow-circle-left"></i> Lançar Devolução</a>';
    $buttons[] = '<a href="inspecao_devolucao_epi.php" class="btn btn-warning"><i class="fa fa-search"></i> Inspeção de Devoluções</a>';
    $buttons[] = '<a href="mapa_epi.php" class="btn btn-info"><i class="fa fa-sitemap"></i> Mapa de EPIs</a>';
    $buttons[] = '<a href="entrega_epi.php" class="btn btn-default"><i class="fa fa-exchange"></i> Lançar Entrega</a>';
    $buttons[] = '<a href="estoque_epi.php" class="btn btn-default"><i class="fa fa-cubes"></i> Estoque de EPI</a>';

    echo abre_form("Filtros da Gestão de Devoluções");
    echo linha_form($fields);
    echo fecha_form($buttons);

    // ---- Condições manuais (período) ----
    $condPeriodo = "";
    if (!empty($_POST["busca_data_inicio"])) {
        $condPeriodo .= " AND dev.ss_e_tx_data_devolucao >= '" . addslashes($_POST["busca_data_inicio"]) . "'";
    }
    if (!empty($_POST["busca_data_fim"])) {
        $condPeriodo .= " AND dev.ss_e_tx_data_devolucao <= '" . addslashes($_POST["busca_data_fim"]) . "'";
    }

    // ---- Cards com indicadores ----
    $sqlCards = query(
        "SELECT dev.ss_e_tx_status, dev.ss_e_tx_estornado,
                MAX(dev.ss_e_tx_destino) AS ss_e_tx_destino,
                MAX(dev.ss_e_tx_status_inspecao) AS ss_e_tx_status_inspecao,
                SUM(dev.ss_e_nb_quantidade) AS total_unids,
                COUNT(DISTINCT dev.ss_e_nb_colaborador_id) AS total_colaboradores,
                COUNT(*) AS total_registros
         FROM ss_epi_entrega dev
         WHERE dev.ss_e_tx_status IN ('devolvido', 'substituido', 'perdido') {$condPeriodo}
         GROUP BY dev.ss_e_tx_status, dev.ss_e_tx_estornado"
    );

    $cards = [
        "total_eventos" => 0,
        "total_unids" => 0,
        "unids_devolvidas" => 0,
        "unids_substituidas" => 0,
        "unids_perdidas" => 0,
        "unids_retornadas" => 0,
        "unids_inspecao" => 0,
        "total_colaboradores" => 0
    ];
    if ($sqlCards) {
        while ($rowCard = mysqli_fetch_assoc($sqlCards)) {
            $cards["total_eventos"] += (int)$rowCard["total_registros"];
            $cards["total_unids"] += (int)$rowCard["total_unids"];
            $cards["total_colaboradores"] += (int)$rowCard["total_colaboradores"];
            if ($rowCard["ss_e_tx_estornado"] === "sim") {
                $cards["unids_retornadas"] += (int)$rowCard["total_unids"];
            }
            if ($rowCard["ss_e_tx_status"] === "devolvido") $cards["unids_devolvidas"] += (int)$rowCard["total_unids"];
            elseif ($rowCard["ss_e_tx_status"] === "substituido") $cards["unids_substituidas"] += (int)$rowCard["total_unids"];
            elseif ($rowCard["ss_e_tx_status"] === "perdido") $cards["unids_perdidas"] += (int)$rowCard["total_unids"];
            if (($rowCard["ss_e_tx_destino"] ?? "") === "inspecao" && ($rowCard["ss_e_tx_status_inspecao"] ?? "") === "pendente") {
                $cards["unids_inspecao"] += (int)$rowCard["total_unids"];
            }
        }
    }

    echo '
    <div class="row" style="margin-top: 15px; margin-bottom: 20px;">';
    $cardDefs = [
        ["Total de Eventos", $cards["total_eventos"], "fa-clipboard-list", "#337ab7"],
        ["Unidades Devolvidas", $cards["unids_devolvidas"], "fa-arrow-circle-left", "#5bc0de"],
        ["Unidades Substituídas", $cards["unids_substituidas"], "fa-refresh", "#f0ad4e"],
        ["Unidades Perdidas", $cards["unids_perdidas"], "fa-times-circle", "#d9534f"],
        ["Retornadas ao Estoque", $cards["unids_retornadas"], "fa-recycle", "#5cb85c"],
        ["Pendentes de Inspeção", $cards["unids_inspecao"], "fa-search", "#f39c12"]
    ];
    foreach ($cardDefs as $cd) {
        echo '
        <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom: 10px;">
            <div style="background: linear-gradient(135deg, ' . $cd[3] . ', ' . $cd[3] . 'cc); border-radius: 8px; padding: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.12); color: #fff; height: 92px;">
                <div style="font-size: 24px; font-weight: 800; line-height: 1.1;">' . $cd[1] . '</div>
                <div style="font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.95;"><i class="fa ' . $cd[2] . '"></i> ' . $cd[0] . '</div>
            </div>
        </div>';
    }
    echo '
    </div>';

    // ---- Grid ----
    $gridFields = [
        "CÓDIGO"            => "ss_e_nb_id",
        "COLABORADOR"       => "colaborador_nome",
        "EPI"               => "epi_nome",
        "VARIAÇÃO"          => "ss_e_tx_variacao",
        "QUANTIDADE"        => "ss_e_nb_quantidade",
        "DATA DEVOLUÇÃO"    => "data_devolucao_fmt",
        "TIPO"              => "status_label",
        "FILIAL"            => "filial_nome",
        "JUSTIFICATIVA"     => "ss_e_tx_justificativa",
        "RETORNOU AO ESTOQUE" => "estornado_label",
        "INSPEÇÃO"          => "inspecao_label",
        "ENTREGA ANTERIOR"  => "entrega_anterior_label"
    ];

    $camposBusca = [
        "busca_status"     => "dev.ss_e_tx_status",
        "busca_estornado"  => "dev.ss_e_tx_estornado",
        "busca_filial"     => "dev.ss_e_nb_empresa_id",
        "busca_nome_like"  => "dev.colaborador_nome",
        "busca_epi_like"   => "dev.epi_nome"
    ];

    $queryBase = "SELECT * FROM (
                    SELECT dev.ss_e_nb_id, col.enti_tx_nome AS colaborador_nome,
                           IFNULL(emp.empr_tx_nome, 'Matriz') AS filial_nome,
                           dev.ss_e_nb_empresa_id,
                           CONCAT(epi.ss_e_tx_grupo, ' / ', IFNULL(epi.ss_e_tx_subgrupo, ''), ' / ', IFNULL(epi.ss_e_tx_item, '')) AS epi_nome,
                           dev.ss_e_nb_quantidade,
                           IFNULL(dev.ss_e_tx_variacao, '-') AS ss_e_tx_variacao,
                           IFNULL(DATE_FORMAT(dev.ss_e_tx_data_devolucao, '%d/%m/%Y'), '-') AS data_devolucao_fmt,
                           dev.ss_e_tx_status,
                           CASE dev.ss_e_tx_status
                               WHEN 'devolvido' THEN 'Devolvido'
                               WHEN 'substituido' THEN 'Substituído'
                               WHEN 'perdido' THEN 'Perdido/Extraviado'
                               ELSE dev.ss_e_tx_status
                           END AS status_label,
                           dev.ss_e_tx_justificativa,
                           dev.ss_e_tx_estornado,
                           CASE WHEN dev.ss_e_tx_estornado = 'sim' THEN 'Sim' ELSE 'Não' END AS estornado_label,
                           dev.ss_e_tx_destino,
                           dev.ss_e_tx_status_inspecao,
                           CASE
                               WHEN dev.ss_e_tx_destino = 'inspecao' AND dev.ss_e_tx_status_inspecao = 'pendente' THEN 'Pendente'
                               WHEN dev.ss_e_tx_destino = 'inspecao' AND dev.ss_e_tx_status_inspecao = 'aprovado' THEN 'Aprovado'
                               WHEN dev.ss_e_tx_destino = 'inspecao' AND dev.ss_e_tx_status_inspecao = 'descartado' THEN 'Descartado'
                               ELSE '-'
                           END AS inspecao_label,
                           dev.ss_e_nb_entrega_anterior_id,
                           CASE WHEN dev.ss_e_nb_entrega_anterior_id IS NOT NULL THEN CONCAT('#', dev.ss_e_nb_entrega_anterior_id) ELSE '-' END AS entrega_anterior_label,
                           dev.ss_e_nb_colaborador_id, dev.ss_e_nb_epi_id
                    FROM ss_epi_entrega dev
                    JOIN entidade col ON dev.ss_e_nb_colaborador_id = col.enti_nb_id
                    JOIN ss_epi epi ON dev.ss_e_nb_epi_id = epi.ss_e_nb_id
                    LEFT JOIN empresa emp ON dev.ss_e_nb_empresa_id = emp.empr_nb_id
                    WHERE dev.ss_e_tx_status IN ('devolvido', 'substituido', 'perdido')
                  ) AS dev {$condPeriodo}";

    $gridFields["actions"] = [
        '<span class="fa fa-edit acao-editar-devolucao" title="Alterar registro" style="color:#337ab7; cursor:pointer; font-size:16px; margin-right:8px;"></span>',
        '<span class="fa fa-history acao-entrega-anterior" title="Ver entrega anterior vinculada" style="color:#5bc0de; cursor:pointer; font-size:16px;"></span>'
    ];

    $jsAcoes = '
        var funcoesInternas = function(){
            // Alterar registro (abre o form de entrega em modo edição)
            $(".acao-editar-devolucao").off("click").on("click", function(event) {
                var id = $(this).closest("tr").attr("data-row-id");
                submitPost("entrega_epi.php", { acao: "modificarEntrega", id: id });
            });

            // Ver entrega anterior vinculada (se houver)
            $(".acao-entrega-anterior").off("click").on("click", function(event) {
                var row = $(this).closest("tr");
                var id = row.attr("data-row-id");
                var entregaAnterior = row.find("td").eq(10).text().trim().replace("#", "");
                if (!entregaAnterior || entregaAnterior === "-") {
                    alert("Este registro não possui entrega anterior vinculada.");
                    return;
                }
                submitPost("entrega_epi.php", { acao: "modificarEntrega", id: entregaAnterior });
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

    echo gridDinamico("tabelaDevolucoes", $gridFields, $camposBusca, $queryBase, $jsAcoes);
    rodape();
}
