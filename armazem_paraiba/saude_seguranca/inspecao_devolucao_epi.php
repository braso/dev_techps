<?php
ob_start();
/* Modo debug
    ini_set("display_errors", 1);
    error_reporting(E_ALL);
//*/

include "conecta.php";

function registrarInspecao() {
    $id = (int)($_POST["id"] ?? 0);
    $decisao = $_POST["decisao"] ?? "";
    $responsavel = trim($_POST["responsavel"] ?? "");
    $obs = trim($_POST["obs_inspecao"] ?? "");

    if ($id <= 0 || !in_array($decisao, ["aprovado", "descartado"])) {
        set_status("ERRO: Dados inválidos para a inspeção.");
        index();
        exit;
    }
    if (empty($responsavel)) {
        set_status("ERRO: Informe o responsável pela inspeção.");
        index();
        exit;
    }

    $reg = carregar("ss_epi_entrega", $id);
    if (empty($reg) || ($reg["ss_e_tx_destino"] ?? "") !== "inspecao" || ($reg["ss_e_tx_status_inspecao"] ?? "") !== "pendente") {
        set_status("ERRO: Este registro não está em pendência de inspeção.");
        index();
        exit;
    }

    atualizar(
        "ss_epi_entrega",
        ["ss_e_tx_status_inspecao", "ss_e_tx_responsavel_inspecao", "ss_e_tx_data_inspecao", "ss_e_tx_obs_inspecao", "ss_e_tx_estornado", "ss_e_nb_userAtualiza", "ss_e_tx_dataAtualiza"],
        [$decisao, $responsavel, date("Y-m-d"), $obs, $decisao === "aprovado" ? "sim" : "nao", $_SESSION["user_nb_id"] ?? 0, date("Y-m-d H:i:s")],
        $id
    );

    if ($decisao === "aprovado") {
        $empresa_id = !empty($reg["ss_e_nb_empresa_id"]) ? (int)$reg["ss_e_nb_empresa_id"] : null;
        registrarMovimentacaoEstoque(
            (int)$reg["ss_e_nb_epi_id"],
            (int)$reg["ss_e_nb_quantidade"],
            'entrada',
            'Devolução aprovada em inspeção (colaborador ID: ' . (int)$reg["ss_e_nb_colaborador_id"] . ')',
            null, null, '', null, null,
            $empresa_id, null, null,
            !empty($reg["ss_e_tx_variacao"]) ? $reg["ss_e_tx_variacao"] : null
        );
    }

    set_status($decisao === "aprovado" ? "Inspeção aprovada — item retornou ao estoque!" : "Descarte registrado com sucesso!");
    index();
    exit;
}

function index() {
    cabecalho("Inspeção de Devoluções de EPI");

    // ---- Filtros ----
    $empresaOptions = ["" => "Todas"];
    $sqlEmpresasFiltro = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
    if ($sqlEmpresasFiltro) {
        while ($rowEmpFiltro = mysqli_fetch_assoc($sqlEmpresasFiltro)) {
            $empresaOptions[$rowEmpFiltro["empr_nb_id"]] = $rowEmpFiltro["empr_tx_nome"];
        }
    }

    $fields = [
        combo("Situação da Inspeção", "busca_status", $_POST["busca_status"] ?? "pendente", 2, ["" => "Todas", "pendente" => "Pendente", "aprovado" => "Aprovado (estoque)", "descartado" => "Descartado"]),
        campo_data("Data Início", "busca_data_inicio", $_POST["busca_data_inicio"] ?? "", 2),
        campo_data("Data Fim", "busca_data_fim", $_POST["busca_data_fim"] ?? "", 2),
        combo("Filial", "busca_filial", $_POST["busca_filial"] ?? "", 2, $empresaOptions),
        campo("Colaborador", "busca_nome_like", $_POST["busca_nome_like"] ?? "", 2),
        campo("EPI", "busca_epi_like", $_POST["busca_epi_like"] ?? "", 2)
    ];

    $buttons = [];
    $buttons[] = botao("Buscar", "index");
    $buttons[] = '<a href="lancar_devolucao_epi.php" class="btn btn-success"><i class="fa fa-arrow-circle-left"></i> Lançar Devolução</a>';
    $buttons[] = '<a href="devolucao_epi.php" class="btn btn-default"><i class="fa fa-list"></i> Gestão de Devoluções</a>';

    echo abre_form("Filtros da Inspeção de Devoluções");
    echo linha_form($fields);
    echo fecha_form($buttons);

    $condPeriodo = "";
    if (!empty($_POST["busca_data_inicio"])) {
        $condPeriodo .= " AND dev.ss_e_tx_data_devolucao >= '" . addslashes($_POST["busca_data_inicio"]) . "'";
    }
    if (!empty($_POST["busca_data_fim"])) {
        $condPeriodo .= " AND dev.ss_e_tx_data_devolucao <= '" . addslashes($_POST["busca_data_fim"]) . "'";
    }

    // ---- Cards de situação ----
    $sqlCards = query(
        "SELECT dev.ss_e_tx_status_inspecao, COUNT(*) AS total, SUM(dev.ss_e_nb_quantidade) AS total_unids
         FROM ss_epi_entrega dev
         WHERE dev.ss_e_tx_destino = 'inspecao' {$condPeriodo}
         GROUP BY dev.ss_e_tx_status_inspecao"
    );
    $cards = ["pendente" => ["total" => 0, "unids" => 0], "aprovado" => ["total" => 0, "unids" => 0], "descartado" => ["total" => 0, "unids" => 0]];
    if ($sqlCards) {
        while ($rowCard = mysqli_fetch_assoc($sqlCards)) {
            $st = $rowCard["ss_e_tx_status_inspecao"] ?? "pendente";
            if (isset($cards[$st])) {
                $cards[$st]["total"] = (int)$rowCard["total"];
                $cards[$st]["unids"] = (int)$rowCard["total_unids"];
            }
        }
    }

    $cardDefs = [
        ["Pendentes de Inspeção", $cards["pendente"]["total"], $cards["pendente"]["unids"], "fa-clock-o", "#f0ad4e"],
        ["Aprovados (retornaram ao estoque)", $cards["aprovado"]["total"], $cards["aprovado"]["unids"], "fa-check-circle", "#5cb85c"],
        ["Descartados", $cards["descartado"]["total"], $cards["descartado"]["unids"], "fa-trash", "#d9534f"]
    ];
    echo '
    <div class="row" style="margin-top: 15px; margin-bottom: 20px;">';
    foreach ($cardDefs as $cd) {
        echo '
        <div class="col-md-4 col-sm-6" style="margin-bottom: 10px;">
            <div style="background: linear-gradient(135deg, ' . $cd[4] . ', ' . $cd[4] . 'cc); border-radius: 8px; padding: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.12); color: #fff;">
                <div style="font-size: 22px; font-weight: 800; line-height: 1.1;">' . $cd[1] . ' registros · ' . $cd[2] . ' unids</div>
                <div style="font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.95;"><i class="fa ' . $cd[3] . '"></i> ' . $cd[0] . '</div>
            </div>
        </div>';
    }
    echo '
    </div>';

    // ---- Usuários (responsáveis pela inspeção) ----
    // Por padrão vem o usuário logado, podendo indicar outra pessoa
    $usuarioLogado = trim($_SESSION["user_tx_nome"] ?? "");
    $sqlUsers = query("SELECT user_nb_id, user_tx_nome FROM user WHERE user_tx_status = 'ativo' ORDER BY user_tx_nome ASC");
    $usuariosHtml = "";
    $usuarioLogadoNaLista = false;
    if ($sqlUsers) {
        while ($rowUser = mysqli_fetch_assoc($sqlUsers)) {
            $nomeUser = trim($rowUser["user_tx_nome"]);
            $selected = (!empty($usuarioLogado) && $nomeUser === $usuarioLogado) ? ' selected' : '';
            if ($selected !== "") {
                $usuarioLogadoNaLista = true;
            }
            $usuariosHtml .= '<option value="' . htmlspecialchars($nomeUser) . '"' . $selected . '>' . htmlspecialchars($nomeUser) . '</option>';
        }
    }
    // Garante o usuário logado na lista, mesmo que não esteja ativo/cadastrado
    if (!empty($usuarioLogado) && !$usuarioLogadoNaLista) {
        $usuariosHtml = '<option value="' . htmlspecialchars($usuarioLogado) . '" selected>' . htmlspecialchars($usuarioLogado) . '</option>' . $usuariosHtml;
    }

    // ---- Grid ----
    $gridFields = [
        "CÓDIGO"          => "ss_e_nb_id",
        "COLABORADOR"     => "colaborador_nome",
        "EPI"             => "epi_nome",
        "VARIAÇÃO"        => "ss_e_tx_variacao",
        "QUANTIDADE"      => "ss_e_nb_quantidade",
        "DATA DEVOLUÇÃO"  => "data_devolucao_fmt",
        "JUSTIFICATIVA"   => "ss_e_tx_justificativa",
        "FILIAL"          => "filial_nome",
        "INSPEÇÃO"        => "inspecao_label",
        "RESPONSÁVEL"     => "responsavel_inspecao",
        "DATA INSPEÇÃO"   => "data_inspecao_fmt",
        "OBSERVAÇÃO"      => "ss_e_tx_obs_inspecao",
        "USUÁRIO REGISTRO" => "usuario_registro",
        "USUÁRIO INSPEÇÃO" => "usuario_inspecao"
    ];

    $camposBusca = [
        "busca_status"    => "dev.ss_e_tx_status_inspecao",
        "busca_filial"    => "dev.ss_e_nb_empresa_id",
        "busca_nome_like" => "dev.colaborador_nome",
        "busca_epi_like"  => "dev.epi_nome"
    ];

    $queryBase = "SELECT * FROM (
                    SELECT dev.ss_e_nb_id, col.enti_tx_nome AS colaborador_nome,
                           IFNULL(emp.empr_tx_nome, 'Matriz') AS filial_nome,
                           dev.ss_e_nb_empresa_id,
                           CONCAT(epi.ss_e_tx_grupo, ' / ', IFNULL(epi.ss_e_tx_subgrupo, ''), ' / ', IFNULL(epi.ss_e_tx_item, '')) AS epi_nome,
                           dev.ss_e_nb_quantidade,
                           IFNULL(dev.ss_e_tx_variacao, '-') AS ss_e_tx_variacao,
                           IFNULL(DATE_FORMAT(dev.ss_e_tx_data_devolucao, '%d/%m/%Y'), '-') AS data_devolucao_fmt,
                           dev.ss_e_tx_justificativa,
                           dev.ss_e_tx_status_inspecao,
                           CASE dev.ss_e_tx_status_inspecao
                               WHEN 'pendente' THEN 'Pendente'
                               WHEN 'aprovado' THEN 'Aprovado (estoque)'
                               WHEN 'descartado' THEN 'Descartado'
                               ELSE dev.ss_e_tx_status_inspecao
                           END AS inspecao_label,
                           IFNULL(dev.ss_e_tx_responsavel_inspecao, '-') AS responsavel_inspecao,
                           IFNULL(DATE_FORMAT(dev.ss_e_tx_data_inspecao, '%d/%m/%Y'), '-') AS data_inspecao_fmt,
                           IFNULL(dev.ss_e_tx_obs_inspecao, '-') AS ss_e_tx_obs_inspecao,
                           dev.ss_e_tx_estornado,
                           IFNULL(usr_cad.user_tx_nome, '-') AS usuario_registro,
                           IFNULL(usr_atu.user_tx_nome, '-') AS usuario_inspecao
                    FROM ss_epi_entrega dev
                    JOIN entidade col ON dev.ss_e_nb_colaborador_id = col.enti_nb_id
                    JOIN ss_epi epi ON dev.ss_e_nb_epi_id = epi.ss_e_nb_id
                    LEFT JOIN empresa emp ON dev.ss_e_nb_empresa_id = emp.empr_nb_id
                    LEFT JOIN user usr_cad ON dev.ss_e_nb_userCadastro = usr_cad.user_nb_id
                    LEFT JOIN user usr_atu ON dev.ss_e_nb_userAtualiza = usr_atu.user_nb_id
                    WHERE dev.ss_e_tx_destino = 'inspecao'
                  ) AS dev {$condPeriodo}";

    $gridFields["actions"] = [
        '<span class="fa fa-check acao-aprovar-inspecao" title="Aprovar — retornar ao estoque" style="color:#5cb85c; cursor:pointer; font-size:16px; margin-right:8px; display:none;"></span>',
        '<span class="fa fa-trash acao-descartar-inspecao" title="Descartar item" style="color:#d9534f; cursor:pointer; font-size:16px; display:none;"></span>'
    ];

    $usuariosJson = json_encode($usuariosHtml);

    $jsAcoes = '
        var funcoesInternas = function(){
            $("#result tbody tr").each(function() {
                var row = $(this);
                var statusCell = row.find("td").eq(8); // INSPEÇÃO é a coluna 8 (0-indexed)
                var isPendente = (statusCell.text().trim().toLowerCase().indexOf("pendente") !== -1);
                row.find(".acao-aprovar-inspecao, .acao-descartar-inspecao").css("display", isPendente ? "" : "none");
            });

            function abrirInspecao(id, decisao, titulo, texto) {
                var usuarios = ' . $usuariosJson . ';
                var html = "<div style=\"text-align: left;\">" +
                    "<p>" + texto + "</p>" +
                    "<div class=\"form-group\">" +
                        "<label for=\"swal_responsavel\">Responsável pela inspeção*:</label>" +
                        "<select id=\"swal_responsavel\" class=\"form-control\"><option value=\"\">Selecione</option>" + usuarios + "</select>" +
                    "</div>" +
                    "<div class=\"form-group\">" +
                        "<label for=\"swal_obs\">Observação da inspeção:</label>" +
                        "<textarea id=\"swal_obs\" class=\"form-control\" rows=\"3\" placeholder=\"Condição do item, avarias, resultado da análise...\"></textarea>" +
                    "</div>" +
                "</div>";
                Swal.fire({
                    title: titulo,
                    html: html,
                    icon: decisao === "aprovado" ? "success" : "error",
                    showCancelButton: true,
                    confirmButtonText: decisao === "aprovado" ? "Sim, retornar ao estoque" : "Sim, descartar",
                    cancelButtonText: "Cancelar",
                    preConfirm: () => {
                        const responsavel = document.getElementById("swal_responsavel").value;
                        if (!responsavel) {
                            Swal.showValidationMessage("Informe o responsável pela inspeção!");
                            return false;
                        }
                        return {
                            responsavel: responsavel,
                            obs: document.getElementById("swal_obs").value
                        };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitPost("", {
                            acao: "registrarInspecao",
                            id: id,
                            decisao: decisao,
                            responsavel: result.value.responsavel,
                            obs_inspecao: result.value.obs
                        });
                    }
                });
            }

            $(".acao-aprovar-inspecao").off("click").on("click", function(event) {
                event.stopPropagation();
                var id = $(this).closest("tr").attr("data-row-id");
                var epiNome = $(this).closest("tr").find("td").eq(2).text().trim();
                abrirInspecao(id, "aprovado", "Aprovar retorno ao estoque", "O item <b>" + epiNome + "</b> passou na inspeção e será devolvido ao estoque para nova entrega?");
            });

            $(".acao-descartar-inspecao").off("click").on("click", function(event) {
                event.stopPropagation();
                var id = $(this).closest("tr").attr("data-row-id");
                var epiNome = $(this).closest("tr").find("td").eq(2).text().trim();
                abrirInspecao(id, "descartado", "Descartar item", "O item <b>" + epiNome + "</b> NÃO passou na inspeção e será descartado (não retorna ao estoque)?");
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

    echo gridDinamico("tabelaInspecaoDevolucoes", $gridFields, $camposBusca, $queryBase, $jsAcoes);
    rodape();
}
