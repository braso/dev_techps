<?php
    include_once "utils/utils.php";
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
        // Recebe os bilhetes de retorno via POST
        $idRetorno = !empty($_POST["id_usuario_retorno"]) ? (int)$_POST["id_usuario_retorno"] : 0;
        $telaOrigem = !empty($_POST["tela_origem"]) ? $_POST["tela_origem"] : "";

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
            campo_hidden("id_usuario_retorno", ($idRetorno > 0 ? $idRetorno : "")),
            campo_hidden("tela_origem", $telaOrigem),
            campo("UID", "rfids_tx_uid", (!empty($_POST["rfids_tx_uid"]) ? $_POST["rfids_tx_uid"] : ""), 4, "", "required"),
            
            call_user_func(function() {
                $statusAtual = !empty($_POST["rfids_tx_status"]) ? $_POST["rfids_tx_status"] : 'disponivel';
                $opcoes_status = [
                    'disponivel' => 'Em estoque (Disponível)',
                    'bloqueado'  => 'Bloqueado (Suspenso)',
                    'perdido'    => 'Perdido',
                    'quebrado'   => 'Danificado/Quebrado'
                ];
                
                // O status 'ativo' SÓ aparece se o crachá já estiver ativo.
                if ($statusAtual == 'ativo') {
                    $opcoes_status['ativo'] = 'Em Uso (Ativo) - Ficha do Funcionário';
                }
                
                return combo_radio("Status do cartão", "rfids_tx_status", $statusAtual, 4, $opcoes_status);
            }),
            
            // DESCRIÇÃO AJUSTADA (Tamanho 4 - para fechar as 12 colunas perfeitas)
            campo("Descrição", "rfids_tx_descricao", (!empty($_POST["rfids_tx_descricao"]) ? $_POST["rfids_tx_descricao"] : ""), 4)
        ]);

        $botoes = [];

        // Montagem inteligente: Junta as chaves dinamicamente para o botão Atualizar/Cadastrar
        $chaves = []; $valores = [];
        if(!empty($_POST["id"])) { $chaves[] = "id"; $valores[] = $_POST["id"]; }
        if($idRetorno > 0) { $chaves[] = "id_usuario_retorno"; $valores[] = $idRetorno; }
        if(!empty($telaOrigem)) { $chaves[] = "tela_origem"; $valores[] = $telaOrigem; }

        $strChaves = implode(",", $chaves);
        $strValores = implode(",", $valores);
        $textoBotao = !empty($_POST["id"]) ? "Atualizar" : "Cadastrar";

        $botoes[] = botao($textoBotao, "cadastrarRfid", $strChaves, $strValores, "", "", "btn btn-success");
        
        // Botão voltar customizado dependendo de onde o cara veio
        if ($telaOrigem == 'grid_funcionario') {
            $botoes[] = "<button type='button' class='btn btn-warning' onclick=\"window.location.href='cadastro_funcionario.php';\">Voltar para Funcionários</button>";
        } elseif ($idRetorno > 0) {
            $botoes[] = "<button type='button' class='btn btn-warning' onclick=\"var f=document.createElement('form');f.method='POST';f.action='cadastro_usuario.php';var i=document.createElement('input');i.type='hidden';i.name='id';i.value='{$idRetorno}';f.appendChild(i);var a=document.createElement('input');a.type='hidden';a.name='acao';a.value='modificarUsuario';f.appendChild(a);document.body.appendChild(f);f.submit();\">Voltar para Usuário</button>";
        }

        echo fecha_form($botoes);
    }

    function listarRfids(){
        $gridFields = [
            "CÓDIGO"        => "rfids_nb_id",
            "UID"           => "rfids_tx_uid",
            "FUNCIONÁRIO"   => "IFNULL(user.user_tx_nome, '---')",
            "STATUS"        => "rfids_tx_status",
            "DESCRIÇÃO"     => "IF(CHAR_LENGTH(rfids_tx_descricao) > 40, CONCAT(LEFT(rfids_tx_descricao, 40), '...'), rfids_tx_descricao)",
            "CADASTRADO EM" => "DATE_FORMAT(rfid_dt_created_at, '%d/%m/%Y %H:%i:%s')",
            "ID USUÁRIO"    => "rfids_nb_entidade_id"
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

        // 1. Chamamos a nossa função mágica da pasta utils
        $acoesGrid = gerarAcoesComConfirmacao(
            "cadastro_rfid.php", 
            "editarRfid", 
            "excluirRfid", 
            "Excluir o RFID código: ", 
            "CÓDIGO"
        );

        // 2. Injetamos as tags HTML e o JS no grid
        $gridFields["actions"] = $acoesGrid["tags"];
        $jsFunctions = $acoesGrid["js"];

        // 3. Renderiza o grid normalmente
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

        $idRetorno = !empty($_POST["id_usuario_retorno"]) ? (int)$_POST["id_usuario_retorno"] : 0;
        $telaOrigem = !empty($_POST["tela_origem"]) ? $_POST["tela_origem"] : "";

        // JS Dinâmico: Decide para onde voltar ao clicar no "OK" do SweetAlert
        $jsRedirect = "";
        if ($telaOrigem == 'grid_funcionario') {
            // Se veio da listagem de funcionários, recarrega a grid de funcionários
            $jsRedirect = "window.location.href = 'cadastro_funcionario.php';";
        } elseif ($idRetorno > 0) {
            // Se veio da ficha de edição de usuário, submete o form oculto para abrir o usuário
            $jsRedirect = "
                var f = document.createElement('form');
                f.method = 'POST'; f.action = 'cadastro_usuario.php';
                var idInput = document.createElement('input'); idInput.type = 'hidden'; idInput.name = 'id'; idInput.value = '{$idRetorno}'; f.appendChild(idInput);
                var acaoInput = document.createElement('input'); acaoInput.type = 'hidden'; acaoInput.name = 'acao'; acaoInput.value = 'modificarUsuario'; f.appendChild(acaoInput);
                document.body.appendChild(f);
                f.submit();
            ";
        } else {
            // Se editou pela tela raiz de RFID, recarrega o grid de RFID
            $jsRedirect = "window.location.href = 'cadastro_rfid.php';";
        }

        // ==========================================
        // O BLOCO DE SALVAR QUE ESTAVA FALTANDO
        // ==========================================
        if(!empty($_POST["id"])){
            atualizar("rfids", array_keys($dados), array_values($dados), $_POST["id"], "rfids_nb_id");
            
            // REGRA EIDER Desvincula da pessoa se o crachá saiu de circulação
            if (in_array($dados["rfids_tx_status"], ['disponivel', 'perdido', 'quebrado', 'bloqueado'])) {
                query("UPDATE rfids SET rfids_nb_entidade_id = NULL WHERE rfids_nb_id = " . (int)$_POST["id"]);
            }
            
            // SweetAlert aguardando o clique do OK (.then)
            set_status("<script>
                Swal.fire({ title: 'Sucesso!', text: 'RFID atualizado com sucesso.', icon: 'success' })
                .then(() => { {$jsRedirect} });
            </script>");
        } else {
            inserir("rfids", array_keys($dados), array_values($dados));
            set_status("<script>
                Swal.fire({ title: 'Sucesso!', text: 'RFID inserido com sucesso.', icon: 'success' })
                .then(() => { {$jsRedirect} });
            </script>");
        }
        unset($_POST["rfids_tx_uid"], $_POST["rfids_tx_status"], $_POST["rfids_tx_descricao"], $_POST["acao"]);
        
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