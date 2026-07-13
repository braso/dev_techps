<?php
/* Modo debug
    ini_set("display_errors", 1);
    error_reporting(E_ALL);
//*/

include "conecta.php";

function cadastrarColaborador() {
    $camposObrig = [
        "nome" => "Nome"
    ];
    $errorMsg = conferirCamposObrig($camposObrig, $_POST);
    if (!empty($errorMsg)) {
        set_status("ERRO: {$errorMsg}");
        modificarColaborador();
        exit;
    }

    if (!empty($_POST["cpf"])) {
        $_POST["cpf"] = preg_replace("/[^0-9]/", "", $_POST["cpf"]);
        if (!validarCPF($_POST["cpf"])) {
            $_POST["errorFields"][] = "cpf";
            set_status("ERRO: CPF inválido.");
            modificarColaborador();
            exit;
        }
    }

    $colaborador = [
        "ss_c_tx_nome"      => $_POST["nome"],
        "ss_c_tx_matricula" => $_POST["matricula"],
        "ss_c_tx_cpf"       => $_POST["cpf"],
        "ss_c_tx_cargo"     => $_POST["cargo"],
        "ss_c_tx_status"    => $_POST["status"] ?? "ativo"
    ];

    if (empty($_POST["id"])) {
        $colaborador["ss_c_nb_userCadastro"] = $_SESSION["user_nb_id"] ?? 0;
        $colaborador["ss_c_tx_dataCadastro"] = date("Y-m-d H:i:s");
        inserir("ss_colaborador", array_keys($colaborador), array_values($colaborador));
        set_status("Colaborador cadastrado com sucesso!");
    } else {
        $colaborador["ss_c_nb_userAtualiza"] = $_SESSION["user_nb_id"] ?? 0;
        $colaborador["ss_c_tx_dataAtualiza"] = date("Y-m-d H:i:s");
        atualizar("ss_colaborador", array_keys($colaborador), array_values($colaborador), $_POST["id"]);
        set_status("Colaborador atualizado com sucesso!");
    }

    index();
    exit;
}

function modificarColaborador() {
    if (!empty($_POST["id"])) {
        if (is_array($_POST["id"])) {
            $_POST["id"] = $_POST["id"][0];
        }
        $colaborador = carregar("ss_colaborador", $_POST["id"]);
        foreach ($colaborador as $key => $value) {
            $cleanedKey = str_replace("ss_c_tx_", "", $key);
            $cleanedKey = str_replace("ss_c_nb_", "", $cleanedKey);
            if (empty($_POST[$cleanedKey])) {
                $_POST[$cleanedKey] = $value;
            }
        }
    }

    cabecalho("Ficha de Colaborador");

    $campo_nome      = campo("Nome*", "nome", $_POST["nome"] ?? "", 4, "", "maxlength='100'");
    $campo_matricula = campo("Matrícula", "matricula", $_POST["matricula"] ?? "", 2, "", "maxlength='50'");
    $campo_cpf       = campo("CPF", "cpf", $_POST["cpf"] ?? "", 2, "MASCARA_CPF");
    $campo_cargo     = campo("Cargo", "cargo", $_POST["cargo"] ?? "", 2, "", "maxlength='100'");
    $campo_status    = combo("Status", "status", $_POST["status"] ?? "ativo", 2, ["ativo" => "Ativo", "inativo" => "Inativo"]);

    $fields = [$campo_nome, $campo_matricula, $campo_cpf, $campo_cargo, $campo_status];

    $buttons = [];
    $buttons[] = botao(!empty($_POST["id"]) ? "Atualizar" : "Gravar", "cadastrarColaborador", "id", $_POST["id"] ?? "", "", "", "btn btn-success");
    $buttons[] = criarBotaoVoltar();

    echo abre_form("Dados do Colaborador");
    echo linha_form($fields);
    echo fecha_form($buttons);

    rodape();
}

function excluirColaborador() {
    if (!empty($_POST["id"])) {
        remover("ss_colaborador", $_POST["id"]);
        set_status("Colaborador inativado com sucesso!");
    }
    index();
    exit;
}

function index() {
    cabecalho("Cadastro de Colaboradores");

    if (!isset($_POST["busca_status"])) {
        $_POST["busca_status"] = "ativo";
    }

    $fields = [
        campo("Código", "busca_codigo", $_POST["busca_codigo"] ?? "", 1, "MASCARA_NUMERO"),
        campo("Nome", "busca_nome", $_POST["busca_nome"] ?? "", 4),
        campo("CPF", "busca_cpf", $_POST["busca_cpf"] ?? "", 2, "MASCARA_CPF"),
        campo("Cargo", "busca_cargo", $_POST["busca_cargo"] ?? "", 3),
        combo("Status", "busca_status", $_POST["busca_status"] ?? "", 2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"])
    ];

    $buttons = [];
    $buttons[] = botao("Buscar", "index");
    $buttons[] = botao("Inserir", "modificarColaborador", "", "", "", "", "btn btn-success");

    echo abre_form("Filtros de Busca");
    echo linha_form($fields);
    echo fecha_form($buttons);

    $gridFields = [
        "CÓDIGO"    => "ss_c_nb_id",
        "NOME"      => "ss_c_tx_nome",
        "MATRÍCULA" => "ss_c_tx_matricula",
        "CPF"       => "ss_c_tx_cpf",
        "CARGO"     => "ss_c_tx_cargo",
        "STATUS"    => "ss_c_tx_status"
    ];

    $camposBusca = [
        "busca_codigo" => "ss_c_nb_id",
        "busca_nome"   => "ss_c_tx_nome",
        "busca_cpf"    => "ss_c_tx_cpf",
        "busca_cargo"  => "ss_c_tx_cargo",
        "busca_status" => "ss_c_tx_status"
    ];

    $queryBase = "SELECT ss_c_nb_id, ss_c_tx_nome, ss_c_tx_matricula, ss_c_tx_cpf, ss_c_tx_cargo, ss_c_tx_status FROM ss_colaborador";

    $acoesGrid = ss_gerarAcoesComConfirmacao(
        "cadastro_colaborador.php",
        "modificarColaborador",
        "excluirColaborador",
        "CÓDIGO",
        "Deseja inativar o colaborador: {NOME}?"
    );

    $gridFields["actions"] = $acoesGrid["tags"];

    echo gridDinamico("tabelaColaboradores", $gridFields, $camposBusca, $queryBase, $acoesGrid["js"]);

    rodape();
}
?>
