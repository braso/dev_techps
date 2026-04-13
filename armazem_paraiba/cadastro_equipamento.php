<?php
include_once "utils/utils.php";
include_once "check_permission.php";
include_once "load_env.php";
include_once "conecta.php";

date_default_timezone_set('America/Fortaleza');

function index(){
    echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>";
    cabecalho("Gestão de Equipamentos (IoT)");

    // CORREÇÃO: Removido o "$" e substituído por "" para o server-side ignorar o filtro quando estiver em "Todos"
    $camposBusca = [
        campo("Nome do Equipamento", "busca_nome_like", ($_POST["busca_nome_like"] ?? ""), 4, "", "maxlength='255'"),
        combo("Tipo", "busca_tipo", ($_POST["busca_tipo"] ?? ""), 4, ["" => "Todos", "embarcado" => "Embarcado (Veículo)", "estacao_fixa" => "Estação Fixa"]),
        combo("Status", "busca_status", ($_POST["busca_status"] ?? ""), 4, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo", "manutencao" => "Manutenção"])
    ];

    $botoesBusca = [
        botao("Buscar", "index", "", "", "", "", "btn btn-info"),
        "<button type='button' class='btn default' onclick=\"window.location.href='cadastro_equipamento.php';\">Limpar</button>",
        botao("Novo Equipamento", "visualizarCadastro", "", "", "", "", "btn btn-success")
    ];

    echo abre_form();
    echo linha_form($camposBusca);
    echo fecha_form([], "<hr><form>".implode(" ", $botoesBusca)."</form>");

    listarEquipamentos();
    rodape();
}

function listarEquipamentos(){
    // 1. Array com as colunas reais E as maquiadas (aliases)
    $sqlSelectFields = [
        "equip_nb_id",
        "equip_tx_nome",
        "equip_tx_identificador",
        "equip_tx_tipo", 
        "equip_tx_status", 
        "UPPER(equip_tx_tipo) AS tipo_view",
        "UPPER(equip_tx_status) AS status_view",
        "DATE_FORMAT(equip_dt_created_at, '%d/%m/%Y %H:%i') AS data_cadastro"
    ];

    // 2. Mapeamento das chaves do JS (SEM os 'AS')
    $gridFields = [
        "ID"                  => "equip_nb_id",
        "NOME"                => "equip_tx_nome",
        "TIPO"                => "tipo_view",
        "IDENTIFICADOR (MAC)" => "equip_tx_identificador",
        "STATUS"              => "status_view",
        "DATA CADASTRO"       => "data_cadastro"
    ];

    // 3. Filtros atrelados às colunas REAIS do banco
    $camposBuscaGrid = [
        "busca_nome_like" => "equip_tx_nome",
        "busca_tipo"      => "equip_tx_tipo",
        "busca_status"    => "equip_tx_status"
    ];

    // CORREÇÃO: A subconsulta foi restaurada. Ela é OBRIGATÓRIA para o server-side.php não quebrar ao buscar pelos Aliases
    $queryBase = "SELECT * FROM (
                    SELECT " . implode(", ", $sqlSelectFields) . "
                    FROM equipamentos
                  ) AS base_query";

    $acoesGrid = gerarAcoesComConfirmacao("cadastro_equipamento.php", "modificarEquipamento", "excluirEquipamento", "ID", "", "NOME", "");

    $gridFields["actions"] = $acoesGrid["tags"];
    
    echo gridDinamico("equipamentos", $gridFields, $camposBuscaGrid, $queryBase, $acoesGrid["js"]);
}

function visualizarCadastro(){
    cabecalho("Ficha do Equipamento");

    $id = !empty($_POST["id"]) ? (int)$_POST["id"] : 0;
    
    if($id > 0 && empty($_POST['equip_tx_nome'])){
        $dados = mysqli_fetch_assoc(query("SELECT * FROM equipamentos WHERE equip_nb_id = $id"));
        if ($dados) {
            $_POST = array_merge($_POST, $dados);
        }
    }

    echo abre_form();
    echo linha_form([
        campo_hidden("id", $id),
        campo("Nome do Equipamento*", "equip_tx_nome", ($_POST["equip_tx_nome"] ?? ""), 4),
        combo("Tipo*", "equip_tx_tipo", ($_POST["equip_tx_tipo"] ?? "embarcado"), 4, ["embarcado" => "Embarcado (Caminhão)", "estacao_fixa" => "Estação Fixa (RH)"]),
        campo("Identificador (MAC)*", "equip_tx_identificador", ($_POST["equip_tx_identificador"] ?? ""), 4, "", "placeholder='B8:27:EB...'")
    ]);

    echo linha_form([
        combo("Status*", "equip_tx_status", ($_POST["equip_tx_status"] ?? "ativo"), 4, ["ativo" => "Ativo", "inativo" => "Inativo", "manutencao" => "Manutenção"]),
        texto("Token de Segurança", ($id > 0 ? "<code>" . ($_POST['equip_tx_token'] ?? '---') . "</code>" : "<span class='text-muted'>Será gerado no cadastro</span>"), 8)
    ]);

    $botoes = [
        botao(($id > 0 ? "Atualizar" : "Cadastrar e Gerar Token"), "cadastrarEquipamento", "id", $id, "", "", "btn btn-success"),
        "<button type='button' class='btn btn-warning' onclick=\"window.location.href='cadastro_equipamento.php';\">Voltar</button>"
    ];

    echo fecha_form($botoes);
    rodape();
}

function cadastrarEquipamento(){
    global $conn;
    $id = (int)$_POST["id"];
    
    $dados = [
        "equip_tx_nome"          => trim($_POST["equip_tx_nome"]),
        "equip_tx_tipo"          => $_POST["equip_tx_tipo"],
        "equip_tx_identificador" => trim($_POST["equip_tx_identificador"]),
        "equip_tx_status"        => $_POST["equip_tx_status"]
    ];

    if($id == 0){
        // Geração do Token Único apenas no cadastro inicial
        $dados["equip_tx_token"] = bin2hex(random_bytes(16));
        inserir("equipamentos", array_keys($dados), array_values($dados));
        
        set_status("<script>
            Swal.fire({
                title: 'Cadastrado!',
                html: 'Equipamento salvo. <b>COPIE O TOKEN:</b><br><br><code style=\"font-size:20px\">{$dados["equip_tx_token"]}</code>',
                icon: 'success'
            });
        </script>");
    } else {
        atualizar("equipamentos", array_keys($dados), array_values($dados), $id, "equip_nb_id");
        set_status(alertaSucessoAtualizacao('Sucesso!', 'Dados atualizados.', "window.location.href='cadastro_equipamento.php';", ""));
    }

    index(); exit;
}

function modificarEquipamento(){
    visualizarCadastro(); exit;
}

function excluirEquipamento(){
    $id = (int)$_POST['id'];
    query("DELETE FROM equipamentos WHERE equip_nb_id = $id");
    set_status("<script>Swal.fire('Removido!', 'Equipamento excluído.', 'success');</script>");
    index(); exit;
}
?>