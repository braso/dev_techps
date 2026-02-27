<?php
    include_once "load_env.php";
    include_once "conecta.php";
    mysqli_query($conn, "SET time_zone = '-3:00'");

    // Garante que a tabela exista
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rfids (
        rfids_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        rfids_tx_uid VARCHAR(255) NOT NULL UNIQUE,
        rfids_nb_user_id INT DEFAULT NULL,
        rfids_tx_status VARCHAR(20) DEFAULT 'ativo',
        rfids_tx_descricao TEXT,
        rfids_dt_created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    function formRfid(){
        if(!empty($_POST["id"])){
            $rfid = mysqli_fetch_assoc(query("SELECT * FROM rfids WHERE rfids_nb_id = ".(int)$_POST['id']));
            
            if($_POST["acao"] != "cadastrarRfid"){
                $_POST["rfids_tx_uid"]       = $rfid["rfids_tx_uid"];
                $_POST["rfids_tx_status"]    = $rfid["rfids_tx_status"];
                $_POST["rfids_tx_descricao"] = $rfid["rfids_tx_descricao"];
            }
        }
    
        echo abre_form();
        echo linha_form([

            campo_hidden("id", (!empty($_POST["id"]) ? $_POST["id"] : "")),
            campo("UID", "rfids_tx_uid", (!empty($_POST["rfids_tx_uid"]) ? $_POST["rfids_tx_uid"] : ""), 4, "", "required"),
            combo_radio("Status", "rfids_tx_status", (!empty($_POST["rfids_tx_status"]) ? $_POST["rfids_tx_status"] : 'ativo'), 2, [
                'ativo' => 'Ativo',
                'inativo' => 'Inativo'
            ]),
            campo("Descrição", "rfids_tx_descricao", (!empty($_POST["rfids_tx_descricao"]) ? $_POST["rfids_tx_descricao"] : ""), 6)
        ]);

        $botoes = !empty($_POST["id"]) ?
            [
                botao("Atualizar", "cadastrarRfid", "id", $_POST["id"], "", "", "btn btn-success"),
                criarBotaoVoltar("cadastro_rfid.php", "voltarRfid")
            ] :
            [botao("Cadastrar", "cadastrarRfid", "", "", "", "", "btn btn-success")];

        echo fecha_form($botoes);
    }

    function listarRfids(){
        $gridFields = [
            "CÓDIGO"        => "rfids_nb_id",
            "UID"           => "rfids_tx_uid",
            "STATUS"        => "rfids_tx_status",
            "DESCRIÇÃO"     => "rfids_tx_descricao",
            "CADASTRADO EM" => "DATE_FORMAT(rfids_dt_created_at, '%d/%m/%Y %H:%i:%s')",
            "ID USUÁRIO"    => "rfids_nb_user_id"
        ];

        $camposBusca = [
            "uid"       => "rfids_tx_uid",
            "status"    => "rfids_tx_status",
            "descricao" => "rfids_tx_descricao"
        ];

        $queryBase = "SELECT " . implode(", ", array_values($gridFields)) . " FROM rfids";

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
            ["cadastro_rfid.php", "cadastro_rfid.php"],
            ["editarRfid()", "excluirRfid()"]
        );

        $gridFields["actions"] = $actions["tags"];
        $jsFunctions = "const funcoesInternas = function(){ " . implode(" ", $actions["functions"]) . " }";

        echo gridDinamico("rfids", $gridFields, $camposBusca, $queryBase, $jsFunctions);
    }

    function cadastrarRfid(){
        $fields = ["rfids_tx_uid", "rfids_tx_status", "rfids_tx_descricao"];
        foreach($fields as $field){
            $_POST[$field] = trim($_POST[$field]);
        }

        $errorMsg = conferirCamposObrig(["rfids_tx_uid" => "UID"], $_POST);
        if(!empty($errorMsg)){
            set_status("ERRO: " . $errorMsg);
            unset($_POST["cadastrarRfid"]);
            index();
            exit;
        }

        // Validação de Duplicidade de UID
        $uidQuery = !empty($_POST["id"]) ?
            [
                "SELECT rfids_nb_id FROM rfids WHERE rfids_tx_uid = ? AND rfids_nb_id != ?;",
                "si",
                [$_POST["rfids_tx_uid"], (int)$_POST["id"]]
            ] :
            [
                "SELECT rfids_nb_id FROM rfids WHERE rfids_tx_uid = ?;",
                "s",
                [$_POST["rfids_tx_uid"]]
            ];

        $uidExists = !empty(mysqli_fetch_assoc(query($uidQuery[0], $uidQuery[1], $uidQuery[2])));
        if($uidExists){
            set_status("<script>Swal.fire('Erro!', 'Este UID já está cadastrado.', 'error');</script>");
            index();
            exit;
        }

        $dados = [
            "rfids_tx_uid"       => $_POST["rfids_tx_uid"],
            "rfids_tx_status"    => (!empty($_POST["rfids_tx_status"]) ? $_POST["rfids_tx_status"] : 'ativo'),
            "rfids_tx_descricao" => $_POST["rfids_tx_descricao"],
        ];
        if(!empty($_POST["id"])){
            
            atualizar("rfids", array_keys($dados), array_values($dados), $_POST["id"], "rfids_nb_id");
            set_status("<script>Swal.fire('Sucesso!', 'RFID atualizado com sucesso.', 'success');</script>");
        } else {
            inserir("rfids", array_keys($dados), array_values($dados));
            set_status("<script>Swal.fire('Sucesso!', 'RFID inserido com sucesso.', 'success');</script>");
        }

        unset($_POST);
        index();
        exit;
    }

    function editarRfid(){
        index();
        exit;
    }

    function excluirRfid(){
        query("DELETE FROM rfids WHERE rfids_nb_id = {$_POST['id']};");
        set_status("<script>Swal.fire('Sucesso!', 'RFID excluído com sucesso.', 'info');</script>");
        unset($_POST['id']);
        index();
        exit;
    }

    function voltarRfid(){
        unset($_POST);
        index();
        exit;
    }

    function index(){
        echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>";
        cabecalho("Cadastro de RFID");
        formRfid();
        if(empty($_POST["id"])){
            listarRfids();
        }
        rodape();
    }
?>