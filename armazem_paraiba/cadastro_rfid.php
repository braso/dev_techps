<?php
    include_once "load_env.php";
    include_once "conecta.php";
    mysqli_query($conn, "SET time_zone = '-3:00'");

    // Garante que a tabela exista
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rfids (
        rfids_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        rfids_tx_uid VARCHAR(255) NOT NULL UNIQUE,
        rfids_nb_entidade_id INT DEFAULT NULL,
        rfids_tx_status ENUM('ativo', 'disponivel', 'bloqueado', 'perdido', 'quebrado') DEFAULT 'disponivel',
        rfids_tx_descricao TEXT,
        rfid_dt_created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    function formRfid(){
        // Recebe o bilhete de retorno via POST
        $idRetorno = !empty($_POST["id_usuario_retorno"]) ? (int)$_POST["id_usuario_retorno"] : 0;

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
            
            // Recria o bilhete de retorno escondido no formulário para não perder a viagem
            campo_hidden("id_usuario_retorno", ($idRetorno > 0 ? $idRetorno : "")),
            
            campo("UID", "rfids_tx_uid", (!empty($_POST["rfids_tx_uid"]) ? $_POST["rfids_tx_uid"] : ""), 4, "", "required"),
            combo_radio("Status do cartão", "rfids_tx_status", (!empty($_POST["rfids_tx_status"]) ? $_POST["rfids_tx_status"] : 'disponivel'), 3, [
                'disponivel' => 'Em estoque (Disponível)',
                'ativo'      => 'Em Uso (Ativo)',
                'bloqueado'  => 'Bloqueado (Suspenso)',
                'perdido'    => 'Perdido',
                'quebrado'   => 'Danificado/Quebrado'
            ]),
            campo("Descrição", "rfids_tx_descricao", (!empty($_POST["rfids_tx_descricao"]) ? $_POST["rfids_tx_descricao"] : ""), 6)
        ]);

        $botoes = [];

        // Montagem inteligente: obriga o botão Atualizar a enviar o bilhete de volta junto!
        if (!empty($_POST["id"])) {
            if ($idRetorno > 0) {
                $botoes[] = botao("Atualizar", "cadastrarRfid", "id,id_usuario_retorno", $_POST["id"].",".$idRetorno, "", "", "btn btn-success");
            } else {
                $botoes[] = botao("Atualizar", "cadastrarRfid", "id", $_POST["id"], "", "", "btn btn-success");
            }
        } else {
            if ($idRetorno > 0) {
                $botoes[] = botao("Cadastrar", "cadastrarRfid", "id_usuario_retorno", $idRetorno, "", "", "btn btn-success");
            } else {
                $botoes[] = botao("Cadastrar", "cadastrarRfid", "", "", "", "", "btn btn-success");
            }
        }

        // BOTÃO VOLTAR CORRIGIDO: Redirecionamento limpo via JavaScript
        $botoes[] = "<button type='button' class='btn default' onclick='window.location.href=\"cadastro_rfid.php\"'>Voltar</button>";

        // O famoso Botão Laranja
        if ($idRetorno > 0) {
            $botoes[] = "<button type='button' class='btn btn-warning' onclick=\"var f=document.createElement('form');f.method='POST';f.action='cadastro_usuario.php';var i=document.createElement('input');i.type='hidden';i.name='id';i.value='{$idRetorno}';f.appendChild(i);var a=document.createElement('input');a.type='hidden';a.name='acao';a.value='modificarUsuario';f.appendChild(a);document.body.appendChild(f);f.submit();\">Voltar para Funcionário</button>";
        }

        echo fecha_form($botoes);
    }

    function listarRfids(){
        $gridFields = [
            "CÓDIGO"        => "rfids_nb_id",
            "UID"           => "rfids_tx_uid",
            "FUNCIONÁRIO"   => "IFNULL(user.user_tx_nome, '---')",
            "STATUS"        => "rfids_tx_status",
            "DESCRIÇÃO"     => "rfids_tx_descricao",
            "CADASTRADO EM" => "DATE_FORMAT(rfid_dt_created_at, '%d/%m/%Y %H:%i:%s')",
        ];

        $camposBusca = [
            "uid"       => "rfids_tx_uid",
            "funcionario" => "user.user_tx_nome",
            "status"    => "rfids_tx_status",
            "descricao" => "rfids_tx_descricao"
        ];

        $queryBase = "SELECT " . implode(", ", array_values($gridFields)) . " 
                      FROM rfids 
                      LEFT JOIN user ON rfids.rfids_nb_entidade_id = user.user_nb_id";

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
            ["cadastro_rfid.php", "cadastro_rfid.php"],
            ["editarRfid()", "excluirRfid()"]
        );

        $actions["functions"][1] = ""; 
        $actions["tags"][1] = '<span class="glyphicon glyphicon-remove search-remove" onclick="confirmarExclusaoRfid(this)" title="Excluir" style="color:#d9534f; cursor:pointer;"></span>';

        $gridFields["actions"] = $actions["tags"];
        $jsDoEditar = $actions["functions"][0];

        $jsFunctions = '
            const funcoesInternas = function(){
                try {
                    ' . $jsDoEditar . '
                } catch(e) { console.error(e); }
            };

            window.confirmarExclusaoRfid = function(elemento){
                var linha = $(elemento).closest("tr");
                var id = linha.find("td:eq(0)").text();

                Swal.fire({
                    title: "Tem certeza?",
                    text: "Excluir o RFID código: " + id + "?",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#d33",
                    cancelButtonColor: "#6c757d",
                    confirmButtonText: "Sim, excluir!",
                    cancelButtonText: "Cancelar"
                }).then((result) => {
                    if (result.isConfirmed) {
                        enviarExclusaoRfid(id);
                    }
                });
            };

            window.enviarExclusaoRfid = function(id){
                var form = document.createElement("form");
                form.method = "POST";
                form.action = ""; 
                
                var fieldAcao = document.createElement("input");
                fieldAcao.type = "hidden";
                fieldAcao.name = "acao";
                fieldAcao.value = "excluirRfid"; 
                form.appendChild(fieldAcao);

                var fieldId = document.createElement("input");
                fieldId.type = "hidden";
                fieldId.name = "id";
                fieldId.value = id; 
                form.appendChild(fieldId);

                document.body.appendChild(form);
                form.submit();
            };
        ';

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
            "rfids_tx_status"    => (!empty($_POST["rfids_tx_status"]) ? $_POST["rfids_tx_status"] : 'disponivel'),
            "rfids_tx_descricao" => $_POST["rfids_tx_descricao"],
        ];

        if(!empty($_POST["id"])){
            atualizar("rfids", array_keys($dados), array_values($dados), $_POST["id"], "rfids_nb_id");
            set_status("<script>Swal.fire('Sucesso!', 'RFID atualizado com sucesso.', 'success');</script>");
        } else {
            inserir("rfids", array_keys($dados), array_values($dados));
            set_status("<script>Swal.fire('Sucesso!', 'RFID inserido com sucesso.', 'success');</script>");
        }

        unset($_POST["acao"]); // Limpa a ação para o index renderizar limpo
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

    // function voltarRfid(){
    //     unset($_POST);
    //     index();
    //     exit;
    // }

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