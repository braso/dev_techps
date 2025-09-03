<?php
    //* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/

    // Incluindo arquivos de configuração
    include_once "load_env.php";
    include_once "conecta.php";
    mysqli_query($conn, "SET time_zone = '-3:00'");
    
    function cadastrar(){
        $camposObrig = [
            "placa" => "Placa",
            "veiculo" => "Veículo/Frota",
            "empresa" => "Empresa",
            "motorista" => "Motorista"
        ];
        $errorMsg = conferirCamposObrig($camposObrig, $_POST);
        if(!empty($errorMsg)){
            set_status("ERRO: ".$errorMsg);
            index();
            exit;
        }

        $_POST["placa"] = strtoupper(str_replace("-", "", $_POST["placa"]));


        //Verificar se a placa já existe para a empresa{
            $empresaPlacaExistente = mysqli_fetch_assoc(query(
                "SELECT empr_tx_nome FROM placa
                    JOIN empresa ON plac_nb_empresa = empr_nb_id
                    WHERE plac_tx_placa = '{$_POST["placa"]}'
                        ".(!empty($_POST["id"])? "AND plac_nb_id != {$_POST["id"]}": "")."
                    ;"
            ));
            if(!empty($empresaPlacaExistente)){
                set_status("ERRO: Já existe uma placa cadastrada para a empresa \"{$empresaPlacaExistente["empr_tx_nome"]}\"!");
                index();
                exit;
            }
        //}

        $novaPlaca = [
            "plac_tx_placa" => $_POST["placa"],
            "plac_tx_modelo" => $_POST["veiculo"],
            "plac_nb_empresa" => $_POST["empresa"],
            "plac_nb_entidade" => $_POST["motorista"],
            "plac_tx_dataAtualiza" => date("Y-m-d H:i:s")
        ];

        
        if(!empty($_POST["id"])){
            atualizar("placa", array_keys($novaPlaca), array_values($novaPlaca), $_POST["id"]);
            set_status("<script>Swal.fire('Sucesso!', 'Placa atualizada com sucesso.', 'success');</script>");
        }else{
            $novaPlaca["plac_tx_dataCadastro"] = date("Y-m-d H:i:s");

            inserir("placa", array_keys($novaPlaca), array_values($novaPlaca));
            set_status("<script>Swal.fire('Sucesso!', 'Placa inserida com sucesso.', 'success');</script>");
        }

        index();
        exit;
    }

    function editarPlaca(){
        index();
        exit;
    }

    function excluirPlaca(){
        query("DELETE FROM placa WHERE plac_nb_id = {$_POST["id"]};");
        set_status("<script>Swal.fire('Sucesso!', 'Placa excluída com sucesso.', 'info');</script>");
        unset($_POST["id"]);
        index();
        exit;
    }

    function voltarPlaca(){
        unset($_POST);
        index();
        exit;
    }

    // Função para exibir o formulário
    function formPlaca(){

        if(!empty($_POST["id"])){
            $placa = mysqli_fetch_assoc(query(
                "SELECT * FROM placa WHERE plac_nb_id = ?;", "i", [$_POST["id"]]
            ));
        }

        $campos = [
            campo_hidden("id", (!empty($_POST["id"])? $_POST["id"]: "")),
            campo("Placa*", "placa", !empty($placa["plac_tx_placa"])? $placa["plac_tx_placa"]: "", 1, "MASCARA_PLACA", "required"),
            campo("Veículo/Frota*", "veiculo", !empty($placa["plac_tx_modelo"])? $placa["plac_tx_modelo"]: "", 1, "", "required"),
            combo_net("Empresa*", "empresa", !empty($placa["plac_nb_empresa"])? $placa["plac_nb_empresa"]: "", 3, 'empresa', "required"),
            combo_net("Motorista*", "motorista", !empty($placa["plac_nb_entidade"])? $placa["plac_nb_entidade"]: "", 3, 'entidade', "required")
        ];

        $botoes = 
            empty($_POST["id"])?
                [botao("Cadastrar", "cadastrar", "cadastrar_placa", "", "class='btn btn-success'")]:
                [
                    botao("Atualizar", "cadastrar", "atualizar_placa", "", "class='btn btn-success'"),
                    criarBotaoVoltar("cadastro_placa.php", "voltarPlaca")
                ]
        ;

        echo abre_form();
        echo linha_form($campos);
        echo fecha_form($botoes);
    }

    // Função para listar as placas
    function listarPlacas() {
        $gridFields = [
            "ID" => "plac_nb_id",
            "PLACA" => "plac_tx_placa",
            "VEÍCULO" => "plac_tx_modelo",
            "MOTORISTA" => "plac_nb_entidade",
            "EMPRESA" => "plac_nb_empresa",
            "DATA DE CADASTRO" => "plac_tx_dataCadastro",
            "DATA DE ALTERAÇÃO" => "plac_tx_dataAtualiza"
        ];

        $camposBusca = [
            "busca_codigo"		=> "para_nb_id",
            "busca_nome_like"	=> "para_tx_nome",
            "busca_acordo"		=> "para_tx_acordo",
            "busca_banco"		=> "para_tx_banco"
        ];

        $queryBase = 
            "SELECT ".implode(", ", array_values($gridFields))." FROM placa
                JOIN entidade ON plac_nb_entidade = enti_nb_id
                JOIN empresa ON plac_nb_empresa = empr_nb_id"
        ;

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
            ["cadastro_placa.php", "cadastro_placa.php"],
            ["editarPlaca()", "excluirPlaca()"]
        );

        $gridFields["actions"] = $actions["tags"];

        $jsFunctions =
            "const funcoesInternas = function(){
                ".implode(" ", $actions["functions"])."
            }"
        ;


        echo gridDinamico("placa", $gridFields, $camposBusca, $queryBase, $jsFunctions);
    }

    function index(){
        cabecalho("Cadastro de Placas");

        formPlaca();

        if(empty($_POST["id"])){
            listarPlacas();
        }
        
        rodape();
    }
?>