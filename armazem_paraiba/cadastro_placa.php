<?php
    /* Modo debug
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
            "placa_like" => "Placa",
            "empresa" => "Empresa"
        ];
        $errorMsg = conferirCamposObrig($camposObrig, $_POST);
        if(!empty($errorMsg)){
            set_status("ERRO: ".$errorMsg);
            layout_placa();
            exit;
        }

        $_POST["placa_like"] = strtoupper(str_replace("-", "", $_POST["placa_like"]));


        //Verificar se a placa já existe para a empresa{
            $empresaPlacaExistente = mysqli_fetch_assoc(query(
                "SELECT empr_tx_nome FROM placa
                    JOIN empresa ON plac_nb_empresa = empr_nb_id
                    WHERE plac_tx_placa = '{$_POST["placa_like"]}'
                        ".(!empty($_POST["id"])? "AND plac_nb_id != {$_POST["id"]}": "")."
                    ;"
            ));
            if(!empty($empresaPlacaExistente)){
                set_status("ERRO: Esta placa já está vinculada à empresa \"{$empresaPlacaExistente["empr_tx_nome"]}\"!");
                layout_placa();
                exit;
            }
        //}

        $novaPlaca = [
            "plac_tx_placa" => $_POST["placa_like"],
            "plac_tx_modelo" => $_POST["veiculo_like"],
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
        layout_placa();
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
    function layout_placa(){
        
        //ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
        include "check_permission.php";
        // APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
        verificaPermissao('/cadastro_placa.php');
        
        cabecalho("Cadastro de Placas");

        if(!empty($_POST["id"])){
            $placa = mysqli_fetch_assoc(query(
                "SELECT * FROM placa WHERE plac_nb_id = ?;", "i", [$_POST["id"]]
            ));
        }

        $campos = [
            campo_hidden("id", (!empty($_POST["id"])? $_POST["id"]: "")),
            campo("Placa*", "placa_like", !empty($placa["plac_tx_placa"])? $placa["plac_tx_placa"]: "", 1, "MASCARA_PLACA", "required"),
            campo("Veículo/Frota", "veiculo_like", !empty($placa["plac_tx_modelo"])? $placa["plac_tx_modelo"]: "", 1, "", ""),
            combo_net("Empresa*", "empresa", !empty($placa["plac_nb_empresa"])? $placa["plac_nb_empresa"]: "", 3, 'empresa', "required"),
            combo_net("Motorista", "motorista", !empty($placa["plac_nb_entidade"])? $placa["plac_nb_entidade"]: "", 3, 'entidade', "")
        ];

        $botoes = 
            empty($_POST["id"])?
                [
                    botao("Cadastrar", "cadastrar", "cadastrar_placa", "", "class='btn btn-success'"),
                    criarBotaoVoltar("cadastro_placa.php", "voltarPlaca")
                ]:
                [
                    botao("Atualizar", "cadastrar", "atualizar_placa", "", "class='btn btn-success'"),
                    criarBotaoVoltar("cadastro_placa.php", "voltarPlaca")
                ]
        ;

        echo abre_form();
        echo linha_form($campos);
        echo fecha_form($botoes);
        
        rodape();
    }

    function index(){
        
        //ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
        include "check_permission.php";
        // APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
        verificaPermissao('/cadastro_placa.php');
        
        cabecalho("Cadastro de Placas");

        // Filtros de busca
        $fields = [
            campo("Placa", "busca_placa_like", ($_POST["busca_placa_like"] ?? ""), 2, "", "oninput='this.value = this.value.toUpperCase()'"),
            campo("Veículo", "busca_veiculo_like", ($_POST["busca_veiculo_like"] ?? ""), 2),
            combo_net("Empresa", "busca_empresa", ($_POST["busca_empresa"] ?? ""), 3, 'empresa'),
            combo_net("Motorista", "busca_motorista", ($_POST["busca_motorista"] ?? ""), 3, 'entidade')
        ];

        $buttons = [
            botao("Buscar", "index"),
            botao("Inserir", "layout_placa", "", "", "", "", "btn btn-success")
        ];

        echo abre_form("Filtro de Busca");
        echo linha_form($fields);
        echo fecha_form($buttons);

        // Grid
        $gridFields = [
            "ID" => "plac_nb_id",
            "PLACA" => "plac_tx_placa",
            "VEÍCULO" => "plac_tx_modelo",
            "MOTORISTA" => "enti_tx_nome",
            "EMPRESA" => "empr_tx_nome",
            "DATA DE CADASTRO" => "DATE_FORMAT(plac_tx_dataCadastro, '%d/%m/%Y %H:%i:%s')",
            "DATA DE ALTERAÇÃO" => "DATE_FORMAT(plac_tx_dataAtualiza, '%d/%m/%Y %H:%i:%s')"
        ];

        $camposBusca = [
            "busca_placa_like"       => "plac_tx_placa",
            "busca_veiculo_like"     => "plac_tx_modelo",
            "busca_empresa"     => "plac_nb_empresa",
            "busca_motorista"   => "plac_nb_entidade"
        ];

        $queryBase = 
            "SELECT ".implode(", ", array_values($gridFields))." FROM placa
                LEFT JOIN entidade ON plac_nb_entidade = enti_nb_id
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


        echo gridDinamico("placa_like", $gridFields, $camposBusca, $queryBase, $jsFunctions);
        
        echo "
            <script>
                // Live search for plate
                let timeout = null;
                $('input[name=\"busca_placa_like\"]').on('keyup', function(){
                    var that = this;
                    clearTimeout(timeout);
                    timeout = setTimeout(function(){
                        $(that).trigger('change');
                    }, 500);
                });
            </script>
        ";

        rodape();
    }
?>