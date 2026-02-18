<?php
    include_once "load_env.php";
    include_once "conecta.php";
    mysqli_query($conn, "SET time_zone = '-3:00'");

    // Garante que a tabela exista
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rfids (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rfid_uid VARCHAR(255) NOT NULL UNIQUE,
        user_id INT DEFAULT NULL,
        status ENUM('ativo','inativo','bloqueado') DEFAULT 'ativo',
        descricao TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    function formRfid(){
        if(!empty($_POST["id"])){
            $rfid = mysqli_fetch_assoc(query("SELECT * FROM rfids WHERE id = {$_POST['id']};"));
            $_POST["rfid_uid"] = !empty($_POST["rfid_uid"])? $_POST["rfid_uid"]: $rfid["rfid_uid"];
            $_POST["status"] = !empty($_POST["status"])? $_POST["status"]: $rfid["status"];
            $_POST["descricao"] = !empty($_POST["descricao"])? $_POST["descricao"]: $rfid["descricao"];
        }

        echo abre_form();
        echo linha_form([
            campo_hidden("id", (!empty($_POST["id"])? $_POST["id"]: "")),
            campo("UID", "rfid_uid", (!empty($_POST["rfid_uid"])? $_POST["rfid_uid"]: ""), 4, "", "required"),
            combo_radio("Status", "status", (!empty($_POST["status"])? $_POST["status"]: 'ativo'), 2, [
                'ativo' => 'Ativo',
                'inativo' => 'Inativo',
                'bloqueado' => 'Bloqueado'
            ]),
            campo("Descrição", "descricao", (!empty($_POST["descricao"])? $_POST["descricao"]: ""), 6)
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
            "CÓDIGO" => "id",
            "UID" => "rfid_uid",
            "USER_ID" => "user_id",
            "STATUS" => "status",
            "DESCRIÇÃO" => "descricao",
            "CADASTRADO EM" => "DATE_FORMAT(created_at, '%d/%m/%Y %H:%i:%s')",
        ];

        $camposBusca = [
            "rfid_uid" => "rfid_uid",
            "status" => "status",
            "descricao" => "descricao"
        ];

        $queryBase = "SELECT ".implode(", ", array_values($gridFields))." FROM rfids";

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
            ["cadastro_rfid.php", "cadastro_rfid.php"],
            ["editarRfid()", "excluirRfid()"]
        );

        $gridFields["actions"] = $actions["tags"];

        $jsFunctions =
            "const funcoesInternas = function(){"
                .implode(" ", $actions["functions"])."
            }";

        echo gridDinamico("rfids", $gridFields, $camposBusca, $queryBase, $jsFunctions);
    }

    function cadastrarRfid(){
        $fields = ["rfid_uid", "status", "descricao"];
        foreach($fields as $field){
            $_POST[$field] = trim($_POST[$field]);
        }

        $errorMsg = conferirCamposObrig(["rfid_uid" => "UID"], $_POST);
        if(!empty($errorMsg)){
            set_status("ERRO: ". $errorMsg);
            unset($_POST["cadastrarRfid"]);
            index();
            exit;
        }

        $uidQuery = !empty($_POST["id"]) ?
            [
                "SELECT id FROM rfids WHERE rfid_uid = ? AND id != ?;",
                "si",
                [$_POST["rfid_uid"], (int)$_POST["id"]]
            ] :
            [
                "SELECT id FROM rfids WHERE rfid_uid = ?;",
                "s",
                [$_POST["rfid_uid"]]
            ];

        $uidExists = !empty(mysqli_fetch_assoc(query($uidQuery[0], $uidQuery[1], $uidQuery[2])));
        if($uidExists){
            set_status("<script>Swal.fire('Erro!', 'Este UID já está cadastrado.', 'error');</script>");
            index();
            exit;
        }

        $novo = [
            "rfid_uid" => $_POST["rfid_uid"],
            "status" => (!empty($_POST["status"]) ? $_POST["status"] : 'ativo'),
            "descricao" => $_POST["descricao"],
        ];

        if(!empty($_POST["id"])){
            atualizar("rfids", array_keys($novo), array_values($novo), $_POST["id"]);
            set_status("<script>Swal.fire('Sucesso!', 'RFID atualizado com sucesso.', 'success');</script>");
        } else {
            inserir("rfids", array_keys($novo), array_values($novo));
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
        query("DELETE FROM rfids WHERE id = {$_POST['id']};");
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
