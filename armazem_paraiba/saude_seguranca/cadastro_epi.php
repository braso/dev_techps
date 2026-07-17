<?php
ob_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);

include "conecta.php";

// Função para Gravar/Atualizar Sub-EPIs do Grupo
function gravarSubEpi() {
    global $conn;
    $grupo_id = (int)$_POST["grupo_id"];
    $parent = carregar("ss_epi", $grupo_id);
    if (!$parent) {
        set_status("ERRO: Grupo não encontrado!");
        index();
        exit;
    }
    
    $subgrupo = trim($_POST["subgrupo"]);
    $item = trim($_POST["item"]);
    $descricao = trim($_POST["descricao"]);
    $status = $_POST["status"] ?? "ativo";
    $sub_id = !empty($_POST["sub_id"]) ? (int)$_POST["sub_id"] : 0;
    
    if (empty($subgrupo)) {
        set_status("ERRO: O campo EPI é obrigatório!");
        $_POST["id"] = $grupo_id;
        modificarEpi();
        exit;
    }
    if (empty($item)) {
        set_status("ERRO: O campo Descrição é obrigatório!");
        $_POST["id"] = $grupo_id;
        modificarEpi();
        exit;
    }
    
    // Validar se o mesmo EPI (subgrupo) com a mesma Descrição (item) já existe neste grupo
    $condId = $sub_id > 0 ? " AND ss_e_nb_id != {$sub_id}" : "";
    $sqlCheck = query("SELECT ss_e_nb_id FROM ss_epi WHERE ss_e_tx_cadastro_tipo = 'universal' AND ss_e_tx_grupo = '" . mysqli_real_escape_string($conn, $parent["ss_e_tx_grupo"]) . "' AND ss_e_tx_subgrupo = '" . mysqli_real_escape_string($conn, $subgrupo) . "' AND ss_e_tx_item = '" . mysqli_real_escape_string($conn, $item) . "' {$condId} LIMIT 1");
    if ($sqlCheck && mysqli_num_rows($sqlCheck) > 0) {
        set_status("ERRO: O EPI '{$subgrupo}' com a descrição '{$item}' já está cadastrado neste grupo!");
        $_POST["id"] = $grupo_id;
        modificarEpi();
        exit;
    }
    
    $epiData = [
        "ss_e_tx_grupo"         => $parent["ss_e_tx_grupo"],
        "ss_e_tx_subgrupo"      => $subgrupo,
        "ss_e_tx_item"          => $item,
        "ss_e_tx_descricao"     => $descricao,
        "ss_e_tx_status"        => $status,
        "ss_e_tx_cadastro_tipo" => "universal"
    ];
    
    if ($sub_id > 0) {
        atualizar("ss_epi", array_keys($epiData), array_values($epiData), $sub_id);
        set_status("EPI atualizado com sucesso!");
    } else {
        inserir("ss_epi", array_keys($epiData), array_values($epiData));
        set_status("EPI cadastrado com sucesso!");
    }
    
    $_POST["id"] = $grupo_id;
    modificarEpi();
    exit;
}

// Função para Excluir Sub-EPIs do Grupo
function excluirSubEpi() {
    if (!empty($_POST["sub_id"])) {
        $sub_id = (int)$_POST["sub_id"];
        query("DELETE FROM ss_epi WHERE ss_e_nb_id = {$sub_id}");
        set_status("EPI removido com sucesso!");
    }
    $_POST["id"] = $_POST["grupo_id"];
    modificarEpi();
    exit;
}

function cadastrarEpi() {
    $camposObrig = [
        "grupo" => "Grupo"
    ];
    $errorMsg = conferirCamposObrig($camposObrig, $_POST);
    if (!empty($errorMsg)) {
        set_status("ERRO: {$errorMsg}");
        modificarEpi();
        exit;
    }

    global $conn;
    $grupo = trim($_POST["grupo"]);
    $grupo_escaped = mysqli_real_escape_string($conn, $grupo);
    $id = !empty($_POST["id"]) ? (int)$_POST["id"] : 0;

    $shouldCheck = true;
    if ($id > 0) {
        $old_parent = carregar("ss_epi", $id);
        if ($old_parent && $old_parent["ss_e_tx_grupo"] === $grupo) {
            $shouldCheck = false; // Nome não mudou, não precisa validar duplicidade
        }
    }

    if ($shouldCheck) {
        $sqlCheck = query("SELECT ss_e_nb_id FROM ss_epi WHERE ss_e_tx_cadastro_tipo = 'universal' AND ss_e_tx_grupo = '{$grupo_escaped}' LIMIT 1");
        if ($sqlCheck && mysqli_num_rows($sqlCheck) > 0) {
            set_status("ERRO: Já existe um grupo cadastrado com o nome '{$grupo}'!");
            modificarEpi();
            exit;
        }
    }

    $epi = [
        "ss_e_tx_grupo"         => $_POST["grupo"],
        "ss_e_tx_subgrupo"      => "",
        "ss_e_tx_item"          => "",
        "ss_e_tx_descricao"     => $_POST["descricao"] ?? "",
        "ss_e_tx_status"        => $_POST["status"] ?? "ativo",
        "ss_e_tx_cadastro_tipo" => "universal"
    ];

    if (empty($_POST["id"])) {
        $res = inserir("ss_epi", array_keys($epi), array_values($epi));
        $id = (int)$res[0];
        set_status("Grupo cadastrado com sucesso!");
    } else {
        $id = (int)$_POST["id"];
        $old_parent = carregar("ss_epi", $id);
        atualizar("ss_epi", array_keys($epi), array_values($epi), $id);
        
        // Atualizar filhos se o nome do grupo mudou
        if ($old_parent["ss_e_tx_grupo"] !== $_POST["grupo"]) {
            $old_name_escaped = mysqli_real_escape_string($conn, $old_parent["ss_e_tx_grupo"]);
            $new_name_escaped = mysqli_real_escape_string($conn, $_POST["grupo"]);
            query("UPDATE ss_epi SET ss_e_tx_grupo = '{$new_name_escaped}' WHERE ss_e_tx_cadastro_tipo = 'universal' AND ss_e_tx_grupo = '{$old_name_escaped}'");
        }
        set_status("Grupo atualizado com sucesso!");
    }

    $_POST["id"] = $id;
    modificarEpi();
    exit;
}

function modificarEpi() {
    global $conn;
    $id = !empty($_POST["id"]) ? (int)$_POST["id"] : 0;
    
    if ($id > 0) {
        if (is_array($_POST["id"])) {
            $_POST["id"] = $_POST["id"][0];
            $id = (int)$_POST["id"];
        }
        $epi = carregar("ss_epi", $id);
        foreach ($epi as $key => $value) {
            $cleanedKey = str_replace("ss_e_tx_", "", $key);
            $cleanedKey = str_replace("ss_e_nb_", "", $cleanedKey);
            if (empty($_POST[$cleanedKey])) {
                $_POST[$cleanedKey] = $value;
            }
        }
    }

    cabecalho("Ficha de Grupo (Universal)");

    // Definição estruturada de campos
    $campo_grupo  = campo("Grupo*", "grupo", $_POST["grupo"] ?? "", 6, "", "maxlength='255' placeholder='Ex: A - EPI PARA PROTEÇÃO DA CABEÇA'");
    $campo_status = combo("Status", "status", $_POST["status"] ?? "ativo", 2, ["ativo" => "Ativo", "inativo" => "Inativo"]);
    $campo_obs    = textarea("Observações do Grupo", "descricao", $_POST["descricao"] ?? "", 4, "style='height: 38px;'");

    $buttons = [];
    $buttons[] = botao($id > 0 ? "Atualizar Grupo" : "Gravar Grupo", "cadastrarEpi", "id", $id, "", "", "btn btn-success");
    $buttons[] = criarBotaoVoltar();

    echo abre_form("Dados do Grupo");
    echo linha_form([$campo_grupo, $campo_status, $campo_obs]);
    echo fecha_form($buttons);

    // Se o grupo já está cadastrado, mostrar seção de EPIs
    if ($id > 0) {
        $grupo_nome = $_POST["grupo"];
        $grupo_nome_escaped = mysqli_real_escape_string($conn, $grupo_nome);
        
        $sqlEpis = query("SELECT ss_e_nb_id, ss_e_tx_subgrupo, ss_e_tx_item, ss_e_tx_descricao, ss_e_tx_status 
                          FROM ss_epi 
                          WHERE ss_e_tx_cadastro_tipo = 'universal' AND ss_e_tx_grupo = '{$grupo_nome_escaped}' AND ss_e_tx_subgrupo != '' AND ss_e_tx_subgrupo IS NOT NULL 
                          ORDER BY ss_e_tx_subgrupo ASC");
        
        echo '
        <div class="portlet light bordered" style="margin-top: 20px;">
            <div class="portlet-title">
                <div class="caption font-dark">
                    <i class="fa fa-list font-dark"></i>
                    <span class="caption-subject bold uppercase">EPIs cadastrados neste Grupo</span>
                </div>
            </div>
            <div class="portlet-body">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th style="width: 25%;">EPI</th>
                            <th style="width: 35%;">Descrição</th>
                            <th style="width: 25%;">Observações</th>
                            <th style="width: 15%; text-align: center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        $hasEpis = false;
        if ($sqlEpis) {
            while ($row = mysqli_fetch_assoc($sqlEpis)) {
                $hasEpis = true;
                $row_id = $row["ss_e_nb_id"];
                $sub_escaped = htmlspecialchars($row["ss_e_tx_subgrupo"]);
                $item_escaped = htmlspecialchars($row["ss_e_tx_item"]);
                $desc_escaped = htmlspecialchars($row["ss_e_tx_descricao"]);
                
                echo '
                <tr id="sub_row_' . $row_id . '">
                    <td>' . $sub_escaped . '</td>
                    <td>' . $item_escaped . '</td>
                    <td>' . $desc_escaped . '</td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-xs btn-primary btn-edit-sub" data-id="' . $row_id . '" data-subgrupo="' . $sub_escaped . '" data-item="' . $item_escaped . '" data-desc="' . $desc_escaped . '" title="Editar"><i class="fa fa-edit"></i></button>
                        <button type="button" class="btn btn-xs btn-danger btn-delete-sub" data-id="' . $row_id . '" title="Remover"><i class="fa fa-trash"></i></button>
                    </td>
                </tr>';
            }
        }
        
        if (!$hasEpis) {
            echo '<tr><td colspan="4" style="text-align: center; color: #999;">Nenhum EPI cadastrado neste grupo.</td></tr>';
        }
        
        echo '
                    </tbody>
                </table>
                
                <!-- Formulário para adicionar/editar EPI no grupo -->
                <div class="well" style="margin-top: 20px; background-color: #fcfcfc; border: 1px solid #e7ecf1;">
                    <h4 id="sub_form_title" style="margin-top: 0; font-weight: bold; color: #337ab7;">Adicionar EPI ao Grupo</h4>
                    <form id="sub_epi_form" action="cadastro_epi.php" method="post">
                        <input type="hidden" name="acao" value="gravarSubEpi">
                        <input type="hidden" name="grupo_id" value="' . $id . '">
                        <input type="hidden" name="sub_id" id="sub_id" value="">
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="control-label">EPI / Subgrupo*</label>
                                    <input type="text" name="subgrupo" id="sub_subgrupo" class="form-control input-sm" placeholder="Ex: A.1 - Capacete" required maxlength="255">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="control-label">Descrição / Item*</label>
                                    <input type="text" name="item" id="sub_item" class="form-control input-sm" placeholder="Ex: capacete de segurança" required maxlength="255">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="control-label">Observações</label>
                                    <input type="text" name="descricao" id="sub_desc" class="form-control input-sm" placeholder="Observações adicionais" maxlength="255">
                                </div>
                            </div>
                        </div>
                        <div class="row" style="margin-top: 10px;">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-sm btn-success" id="btn_salvar_sub">Gravar EPI</button>
                                <button type="button" class="btn btn-sm btn-default" id="btn_cancelar_sub" style="display: none;">Cancelar Edição</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
            $(document).ready(function() {
                // Editar sub-EPI
                $(".btn-edit-sub").on("click", function() {
                    var id = $(this).attr("data-id");
                    var sub = $(this).attr("data-subgrupo");
                    var item = $(this).attr("data-item");
                    var desc = $(this).attr("data-desc");
                    
                    $("#sub_id").val(id);
                    $("#sub_subgrupo").val(sub);
                    $("#sub_item").val(item);
                    $("#sub_desc").val(desc);
                    
                    $("#sub_form_title").text("Editar EPI no Grupo").css("color", "#d9534f");
                    $("#btn_salvar_sub").text("Atualizar EPI").removeClass("btn-success").addClass("btn-danger");
                    $("#btn_cancelar_sub").show();
                    
                    // Rolar até o formulário
                    $("html, body").animate({ scrollTop: $("#sub_form_title").offset().top - 100 }, 500);
                });
                
                // Cancelar edição
                $("#btn_cancelar_sub").on("click", function() {
                    $("#sub_id").val("");
                    $("#sub_subgrupo").val("");
                    $("#sub_item").val("");
                    $("#sub_desc").val("");
                    
                    $("#sub_form_title").text("Adicionar EPI ao Grupo").css("color", "#337ab7");
                    $("#btn_salvar_sub").text("Gravar EPI").removeClass("btn-danger").addClass("btn-success");
                    $(this).hide();
                });
                
                // Excluir sub-EPI
                $(".btn-delete-sub").on("click", function() {
                    var sub_id = $(this).attr("data-id");
                    Swal.fire({
                        title: "Confirmar exclusão?",
                        text: "Deseja remover este EPI do grupo?",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#d9534f",
                        cancelButtonColor: "#6c757d",
                        confirmButtonText: "Sim, remover!"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            submitPost("cadastro_epi.php", { acao: "excluirSubEpi", sub_id: sub_id, grupo_id: "' . $id . '" });
                        }
                    });
                });
            });
        </script>';
    }

    rodape();
}

function inativarEpi() {
    if (!empty($_POST["id"])) {
        global $conn;
        $id = (int)$_POST["id"];
        $parent = carregar("ss_epi", $id);
        if ($parent) {
            $grupo_escaped = mysqli_real_escape_string($conn, $parent["ss_e_tx_grupo"]);
            atualizar("ss_epi", ["ss_e_tx_status"], ["inativo"], $id);
            query("UPDATE ss_epi SET ss_e_tx_status = 'inativo' WHERE ss_e_tx_cadastro_tipo = 'universal' AND ss_e_tx_grupo = '{$grupo_escaped}'");
            set_status("Grupo e todos os seus EPIs foram inativados!");
        }
    }
    index();
    exit;
}

function ativarEpi() {
    if (!empty($_POST["id"])) {
        global $conn;
        $id = (int)$_POST["id"];
        $parent = carregar("ss_epi", $id);
        if ($parent) {
            $grupo_escaped = mysqli_real_escape_string($conn, $parent["ss_e_tx_grupo"]);
            atualizar("ss_epi", ["ss_e_tx_status"], ["ativo"], $id);
            query("UPDATE ss_epi SET ss_e_tx_status = 'ativo' WHERE ss_e_tx_cadastro_tipo = 'universal' AND ss_e_tx_grupo = '{$grupo_escaped}'");
            set_status("Grupo e todos os seus EPIs foram ativados!");
        }
    }
    index();
    exit;
}

function excluirEpi() {
    if (!empty($_POST["id"])) {
        global $conn;
        $id = (int)$_POST["id"];
        $parent = carregar("ss_epi", $id);
        if ($parent) {
            $grupo_escaped = mysqli_real_escape_string($conn, $parent["ss_e_tx_grupo"]);
            query("DELETE FROM ss_epi WHERE ss_e_tx_cadastro_tipo = 'universal' AND ss_e_tx_grupo = '{$grupo_escaped}'");
            set_status("Grupo e todos os seus EPIs foram excluídos com sucesso!");
        }
    }
    index();
    exit;
}

function index() {
    cabecalho("Cadastro de EPIs (Universal)");
    echo '<style>#btnExportPDF { display: none !important; }</style>';

    if (isset($_SESSION["import_csv_success"])) {
        $success = (int)$_SESSION["import_csv_success"];
        unset($_SESSION["import_csv_success"]);
        
        $errorsList = "";
        if (isset($_SESSION["import_csv_errors"]) && is_array($_SESSION["import_csv_errors"])) {
            $errs = $_SESSION["import_csv_errors"];
            unset($_SESSION["import_csv_errors"]);
            
            $errorsList = "<hr><h5 class='bold' style='text-align:left;'>Ocorrências / Linhas com Erro:</h5><ul style='max-height: 200px; overflow-y: auto; text-align: left; padding-left: 20px;'>";
            foreach ($errs as $err) {
                $errorsList .= "<li>" . htmlspecialchars($err) . "</li>";
            }
            $errorsList .= "</ul>";
        }
        
        $swalWidth = empty($errorsList) ? '400px' : '650px';
        $swalIcon = empty($errorsList) ? 'success' : 'warning';
        
        echo "
        <script>
        $(document).ready(function() {
            Swal.fire({
                title: 'Resultado da Importação',
                html: 'Importados com sucesso: <b>{$success}</b> itens.{$errorsList}',
                icon: '{$swalIcon}',
                width: '{$swalWidth}',
                confirmButtonText: 'OK'
            });
        });
        </script>
        ";
    }

    if (!isset($_POST["busca_status"])) {
        $_POST["busca_status"] = "ativo";
    }

    $fields = [
        campo("Código", "busca_codigo", $_POST["busca_codigo"] ?? "", 2, "MASCARA_NUMERO"),
        campo("Grupo", "busca_grupo", $_POST["busca_grupo"] ?? "", 4),
        campo("EPI", "busca_subgrupo", $_POST["busca_subgrupo"] ?? "", 4),
        combo("Status", "busca_status", $_POST["busca_status"] ?? "", 2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]),
        campo_hidden("busca_cadastro_tipo", "universal")
    ];

    $buttons = [];
    $buttons[] = botao("Buscar", "index");
    $buttons[] = botao("Cadastrar Grupo", "modificarEpi", "", "", "", "", "btn btn-success");

    echo abre_form("Filtros de Busca", 6);
    echo linha_form($fields);
    echo fecha_form($buttons);

    // Form for CSV Import
    $importFields = [
        '<div class="col-sm-12 margin-bottom-5">
            <label class="control-label">Selecionar Arquivo CSV de Importação</label>
            <input type="file" name="csv_import" accept=".csv" class="form-control input-sm" style="height: 30px; padding: 4px 10px;">
         </div>'
    ];
    $importButtons = [
        botao("Baixar Modelo CSV", "baixarModeloCsv", "", "", "formnovalidate", false, "btn btn-info"),
        botao("Importar Itens", "importarCsv", "", "", "", false, "btn btn-primary")
    ];

    echo abre_form("Importação de EPIs em Massa (CSV)", 6);
    echo linha_form($importFields);
    echo fecha_form($importButtons);

    $gridFields = [
        "CÓDIGO" => "ss_e_nb_id",
        "GRUPO"  => "ss_e_tx_grupo",
        "EPI(S)" => "ss_e_tx_subgrupo",
        "STATUS" => "ss_e_tx_status"
    ];

    $camposBusca = [
        "busca_codigo"        => "grp.ss_e_nb_id",
        "busca_grupo"         => "grp.ss_e_tx_grupo",
        "busca_subgrupo"      => "grp.ss_e_tx_subgrupo",
        "busca_status"        => "grp.ss_e_tx_status",
        "busca_cadastro_tipo" => "grp.ss_e_tx_cadastro_tipo"
    ];

    $queryBase = "SELECT * FROM (SELECT MAX(ss_e_nb_id) AS ss_e_nb_id, ss_e_tx_grupo, COALESCE(NULLIF(GROUP_CONCAT(DISTINCT NULLIF(ss_e_tx_subgrupo, '') ORDER BY ss_e_tx_subgrupo SEPARATOR ', '), ''), '-') AS ss_e_tx_subgrupo, MAX(ss_e_tx_status) AS ss_e_tx_status, ss_e_tx_cadastro_tipo FROM ss_epi WHERE ss_e_tx_cadastro_tipo = 'universal' GROUP BY ss_e_tx_grupo) AS grp";

    $gridFields["actions"] = [
        '<span class="fa fa-edit acao-editar-epi" title="Alterar" style="color:#337ab7; cursor:pointer; font-size:16px; margin-right:8px;"></span>',
        '<span class="fa fa-ban acao-inativar-epi" title="Inativar/Ativar" style="color:#f0ad4e; cursor:pointer; font-size:16px; margin-right:8px;"></span>',
        '<span class="fa fa-trash acao-excluir-epi" title="Excluir" style="color:#d9534f; cursor:pointer; font-size:16px;"></span>'
    ];

    $jsFunctions = '
        var funcoesInternas = function(){
            // Bind Alterar click
            $(".acao-editar-epi").off("click").on("click", function(event) {
                var id = $(this).closest("tr").attr("data-row-id");
                submitPost("", { acao: "modificarEpi", id: id });
            });

            // For each row, check status and customize the inativar/ativar icon
            $("#result tbody tr").each(function() {
                var row = $(this);
                var statusCell = row.find("td").eq(3); // STATUS is column index 3 (Código, Grupo, EPIs, Status)
                var statusText = statusCell.text().trim().toLowerCase();
                
                var inativarIcon = row.find(".acao-inativar-epi");
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
            $(".acao-inativar-epi").off("click").on("click", function(event) {
                var row = $(this).closest("tr");
                var id = row.attr("data-row-id");
                var grupo = row.find("td").eq(1).text().trim();
                var statusCell = row.find("td").eq(3);
                var statusText = statusCell.text().trim().toLowerCase();
                
                var isCurrentlyInactive = (statusText.indexOf("inativo") >= 0 || statusText === "inativo");
                var acaoLabel = isCurrentlyInactive ? "ativar" : "inativar";
                var acaoPHP = isCurrentlyInactive ? "ativarEpi" : "inativarEpi";
                var confirmBtnColor = isCurrentlyInactive ? "#5cb85c" : "#f0ad4e";
                
                Swal.fire({
                    title: "Tem certeza?",
                    html: "Deseja " + acaoLabel + " o Grupo <b>" + grupo + "</b> e todos os seus EPIs?",
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
            $(".acao-excluir-epi").off("click").on("click", function(event) {
                var row = $(this).closest("tr");
                var id = row.attr("data-row-id");
                var grupo = row.find("td").eq(1).text().trim();
                
                Swal.fire({
                    title: "Tem certeza?",
                    html: "Deseja excluir permanentemente o Grupo <b>" + grupo + "</b>?<br><br><span style=\'color:#d9534f;\'><b>Atenção:</b> Isso excluirá também todos os EPIs vinculados a este grupo!</span>",
                    icon: "error",
                    showCancelButton: true,
                    confirmButtonColor: "#d9534f",
                    cancelButtonColor: "#6c757d",
                    confirmButtonText: "Sim, excluir!",
                    cancelButtonText: "Cancelar"
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitPost("", { acao: "excluirEpi", id: id });
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

    echo gridDinamico("tabelaEpis", $gridFields, $camposBusca, $queryBase, $jsFunctions);

    rodape();
}

function baixarModeloCsv() {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=modelo_importacao_epis.csv');
    
    // Output UTF-8 BOM for Excel compatibility in PT-BR
    echo "\xEF\xBB\xBF";
    
    echo "# INSTRUÇÕES DE PREENCHIMENTO DO CSV:\r\n";
    echo "# - Campos OBRIGATÓRIOS: Grupo, EPI / Subgrupo, Descrição / Item.\r\n";
    echo "# - Campos OPCIONAIS: Observações do Grupo, Observações.\r\n";
    echo "# - Formato padrão do Grupo: PROTEÇÃO DA CABEÇA, PROTEÇÃO AUDITIVA, etc.\r\n";
    echo "# - A primeira linha de dados abaixo é uma linha de EXEMPLO e serve como orientação.\r\n";
    echo "# --------------------------------------------------------------------------\r\n";
    
    $headers = ["Grupo", "Observações do Grupo", "EPI / Subgrupo", "Descrição / Item", "Observações"];
    echo implode(';', $headers) . "\r\n";
    
    // Sample row
    echo "PROTEÇÃO DA CABEÇA (EXEMPLO DE PREENCHIMENTO);Observações gerais do grupo cabeça (Opcional);Capacete de Segurança;Capacete de proteção classe B com jugular;Jugular de tecido resistente (Opcional)\r\n";
    
    exit;
}

function importarCsv() {
    global $conn;
    if (empty($_FILES["csv_import"]["tmp_name"])) {
        set_status("ERRO: Nenhum arquivo CSV enviado!");
        index();
        exit;
    }
    
    $file = $_FILES["csv_import"]["tmp_name"];
    
    // Read the whole file content to handle encoding
    $content = file_get_contents($file);
    
    // Detect and convert encoding from Windows-1252/ISO-8859-1 to UTF-8 if necessary
    $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding ?: 'ISO-8859-1');
    }
    
    // Clean UTF-8 BOM if present
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    
    // Normalize line endings
    $content = str_replace("\r\n", "\n", $content);
    $content = str_replace("\r", "\n", $content);
    $lines = explode("\n", $content);
    
    // Find the header line (first line that does not start with # and is not empty)
    $headerLineIndex = 0;
    while ($headerLineIndex < count($lines) && (empty(trim($lines[$headerLineIndex])) || substr(trim($lines[$headerLineIndex]), 0, 1) === '#')) {
        $headerLineIndex++;
    }
    
    if ($headerLineIndex >= count($lines)) {
        set_status("ERRO: O arquivo CSV está vazio ou possui formato inválido.");
        index();
        exit;
    }
    
    $headerLine = $lines[$headerLineIndex];
    $separator = (strpos($headerLine, ';') !== false) ? ';' : ',';
    
    $successCount = 0;
    $errors = [];
    
    for ($i = $headerLineIndex + 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line) || substr($line, 0, 1) === '#') {
            continue; // Skip empty lines and comment lines
        }
        
        $row = str_getcsv($line, $separator);
        if (count($row) < 3) {
            $errors[] = "Linha " . ($i + 1) . ": Formato de linha inválido (menos de 3 colunas encontradas).";
            continue;
        }
        
        $grupo         = trim($row[0] ?? "");
        $grupo_obs     = trim($row[1] ?? "");
        $subgrupo      = trim($row[2] ?? "");
        $item          = trim($row[3] ?? "");
        $item_obs      = trim($row[4] ?? "");
        
        // Skip example row if user uploaded it back without editing
        if (strpos(mb_strtolower($grupo, 'UTF-8'), '(exemplo') !== false) {
            continue;
        }
        
        // Validation
        if (empty($grupo)) {
            $errors[] = "Linha " . ($i + 1) . ": O campo 'Grupo' é obrigatório.";
            continue;
        }
        if (empty($subgrupo)) {
            $errors[] = "Linha " . ($i + 1) . ": O campo 'EPI / Subgrupo' é obrigatório.";
            continue;
        }
        if (empty($item)) {
            $errors[] = "Linha " . ($i + 1) . ": O campo 'Descrição / Item' é obrigatório.";
            continue;
        }
        
        // Let's see if the group already exists in database
        $grupo_escaped = mysqli_real_escape_string($conn, $grupo);
        $sqlGroup = query("SELECT ss_e_nb_id, ss_e_tx_descricao FROM ss_epi WHERE ss_e_tx_cadastro_tipo = 'universal' AND ss_e_tx_grupo = '{$grupo_escaped}' AND (ss_e_tx_subgrupo = '' OR ss_e_tx_subgrupo IS NULL) LIMIT 1");
        
        if ($sqlGroup && mysqli_num_rows($sqlGroup) > 0) {
            $rowGroup = mysqli_fetch_assoc($sqlGroup);
            $grupo_id = (int)$rowGroup["ss_e_nb_id"];
            
            // If the CSV has observations for the group, and the group does not currently have observations, update it
            if (!empty($grupo_obs) && empty($rowGroup["ss_e_tx_descricao"])) {
                atualizar("ss_epi", ["ss_e_tx_descricao"], [$grupo_obs], $grupo_id);
            }
        } else {
            // Let's create the group first
            $newGroupData = [
                "ss_e_tx_grupo"         => $grupo,
                "ss_e_tx_subgrupo"      => "",
                "ss_e_tx_item"          => "",
                "ss_e_tx_descricao"     => $grupo_obs,
                "ss_e_tx_status"        => "ativo",
                "ss_e_tx_cadastro_tipo" => "universal"
            ];
            $res = inserir("ss_epi", array_keys($newGroupData), array_values($newGroupData));
            $grupo_id = (int)$res[0];
        }
        
        // Now let's check if the child item (subgrupo + item) already exists in this group
        $subgrupo_escaped = mysqli_real_escape_string($conn, $subgrupo);
        $item_escaped     = mysqli_real_escape_string($conn, $item);
        
        $sqlCheckChild = query("SELECT ss_e_nb_id FROM ss_epi WHERE ss_e_tx_cadastro_tipo = 'universal' AND ss_e_tx_grupo = '{$grupo_escaped}' AND ss_e_tx_subgrupo = '{$subgrupo_escaped}' AND ss_e_tx_item = '{$item_escaped}' LIMIT 1");
        
        if ($sqlCheckChild && mysqli_num_rows($sqlCheckChild) > 0) {
            $errors[] = "Linha " . ($i + 1) . ": O EPI '{$subgrupo}' com a descrição '{$item}' já está cadastrado no grupo '{$grupo}'.";
            continue;
        } else {
            // Create the child EPI
            $newChildData = [
                "ss_e_tx_grupo"         => $grupo,
                "ss_e_tx_subgrupo"      => $subgrupo,
                "ss_e_tx_item"          => $item,
                "ss_e_tx_descricao"     => $item_obs,
                "ss_e_tx_status"        => "ativo",
                "ss_e_tx_cadastro_tipo" => "universal"
            ];
            inserir("ss_epi", array_keys($newChildData), array_values($newChildData));
            $successCount++;
        }
    }
    
    // Save report in session so we can show it as an alert
    if (!empty($errors)) {
        $_SESSION["import_csv_errors"] = $errors;
    }
    $_SESSION["import_csv_success"] = $successCount;
    
    set_status("Importação concluída: {$successCount} itens cadastrados com sucesso.");
    index();
    exit;
}
?>
