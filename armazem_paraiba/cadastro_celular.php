<?php
    include_once "load_env.php";
    include_once "conecta.php";
    mysqli_query($conn, "SET time_zone = '-3:00'");

    /* ============================================================
        FORMULÁRIO
    ============================================================ */
    function formCelular($camposOcultos = []) {

        if(!empty($_POST["id"])){
            $celular = mysqli_fetch_assoc(query(
                "SELECT * FROM celular WHERE celu_nb_id = {$_POST["id"]};"
            ));
            $_POST["nome_like"]          = $_POST["nome_like"] ?? $celular["celu_tx_nome"];
            $_POST["imei"]               = $_POST["imei"] ?? $celular["celu_tx_imei"];
            $_POST["numero"]             = $_POST["numero"] ?? $celular["celu_tx_numero"];
            $_POST["operadora"]          = $_POST["operadora"] ?? $celular["celu_tx_operadora"];
            $_POST["cimie"]              = $_POST["cimie"] ?? $celular["celu_tx_cimie"];
            $_POST["sistemaOperacional"] = $_POST["sistemaOperacional"] ?? $celular["celu_tx_sistemaOperacional"];
            $_POST["marcaModelo_like"]   = $_POST["marcaModelo_like"] ?? $celular["celu_tx_marcaModelo"];
            $_POST["entidade"]           = $_POST["entidade"] ?? $celular["celu_nb_entidade"];
        }

        echo abre_form();

        /* CAMPOS DO FORMULÁRIO */
        $campos = [
            "nome_like" => campo("Nome", "nome_like", $_POST["nome_like"] ?? "", 4, "", "required"),
            "imei" => campo("IMEI", "imei", $_POST["imei"] ?? "", 2, "", "required"),
            "numero" => campo("Número", "numero", $_POST["numero"] ?? "", 2, "", "required"),
            "operadora" => campo("Operadora", "operadora", $_POST["operadora"] ?? "", 2),
            "cimie" => campo("CIMIE", "cimie", $_POST["cimie"] ?? "", 2),
            "sistemaOperacional" => campo("Sistema Operacional", "sistemaOperacional", $_POST["sistemaOperacional"] ?? "", 3),
            "marcaModelo_like" => campo("Marca e Modelo", "marcaModelo_like", $_POST["marcaModelo_like"] ?? "", 2),
            "entidade" => combo_net("Responsável", "entidade", $_POST["entidade"] ?? "", 3, "entidade")
        ];

        /* REMOVE CAMPOS OCULTOS */
        foreach($camposOcultos as $campo){
            unset($campos[$campo]);
        }

        echo linha_form($campos);

        $botoes = 
            !empty($_POST["id"]) ?
                [
                    botao("Atualizar", "cadastrarCelular", "id", $_POST["id"], "", "", "btn btn-success"),
                    criarBotaoVoltar("cadastro_celular.php", "voltarCelular")
                ] :
                [ botao("Cadastrar", "cadastrarCelular", "", "", "", "", "btn btn-success") ];

        echo fecha_form($botoes);
    }

    /* ============================================================
        GRID
    ============================================================ */
    function listarCelulares($camposOcultos = []) {

        $gridFields = [
            "CÓDIGO"            => "celu_nb_id",
            "NOME"              => "celu_tx_nome",
            "IMEI"              => "celu_tx_imei",
            "NÚMERO"            => "celu_tx_numero",
            "OPERADORA"         => "celu_tx_operadora",
            "CIMIE"             => "celu_tx_cimie",
            "S.O."              => "celu_tx_sistemaOperacional",
            "MARCA/MODELO"      => "celu_tx_marcaModelo",
            "RESPONSÁVEL"       => "enti_tx_nome",
            "CADASTRADO EM"     => "CONCAT('data(\"', celu_tx_dataCadastro, '\", 1)') AS celu_tx_dataCadastro",
            "ATUALIZADO EM"     => "CONCAT('data(\"', celu_tx_dataAtualiza, '\", 1)') AS celu_tx_dataAtualiza",
        ];

        /* MAPEAMENTO campo → coluna */
        $map = [
            "imei" => "celu_tx_imei",
            "numero" => "celu_tx_numero",
            "operadora" => "celu_tx_operadora",
            "cimie" => "celu_tx_cimie",
            "sistemaOperacional" => "celu_tx_sistemaOperacional",
            "marcaModelo_like" => "celu_tx_marcaModelo",
        ];

        /* REMOVE COLUNAS OCULTAS */
        foreach($camposOcultos as $campo){
            if(isset($map[$campo])){
                $coluna = $map[$campo];
                foreach($gridFields as $label => $sql){
                    if(strpos($sql, $coluna) !== false){
                        unset($gridFields[$label]);
                    }
                }
            }
        }

        $camposBusca = [
            "nome_like" => "celu_tx_nome",
            "imei" => "celu_tx_imei",
            "numero" => "celu_tx_numero",
            "operadora" => "celu_tx_operadora",
            "cimie" => "celu_tx_cimie",
            "sistemaOperacional" => "celu_tx_sistemaOperacional",
            "marcaModelo_like" => "celu_tx_marcaModelo",
        ];

        $queryBase = "SELECT ".implode(", ", array_values($gridFields))." 
                      FROM celular JOIN entidade ON celu_nb_entidade = enti_nb_id";

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
            ["cadastro_celular.php", "cadastro_celular.php"],
            ["editarCelular()", "excluirCelular()"]
        );

        $gridFields["actions"] = $actions["tags"];

        $jsFunctions =
            "const funcoesInternas = function(){
                ".implode(" ", $actions["functions"])."
            }";

        echo gridDinamico("celular", $gridFields, $camposBusca, $queryBase, $jsFunctions);
    }

    /* ============================================================
        INDEX
    ============================================================ */
    function index(){

        include "check_permission.php";
        verificaPermissao('/cadastro_celular.php');

        echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>";

        cabecalho("Cadastro de Celulares");

        /* -----------------------------------------
           CAMPOS QUE VOCÊ QUER OCULTAR
           BASTA COLOCAR AQUI:
           ex: ["imei","operadora"]
        ----------------------------------------- */
        $camposOcultos = camposOcultosPerfil('/cadastro_celular.php');

        /* Renderiza a tela */
        formCelular($camposOcultos);

        if(empty($_POST["id"])){
            listarCelulares($camposOcultos);
        }

        rodape();
    }
?>
