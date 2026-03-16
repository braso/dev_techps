<?php
include_once "utils/utils.php";
include_once "check_permission.php";
include_once "load_env.php";
include_once "conecta.php";

mysqli_query($conn, "SET time_zone = '-3:00'");


function index(){
    echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>";
    cabecalho("Cadastro de RFID");

    $camposBusca = [
        campo("Buscar por UID", "busca_uid_like", ($_POST["busca_uid_like"] ?? ""), 3, "", "maxlength='255'"),
        combo("Status", "busca_status_like", ($_POST["busca_status_like"] ?? "visivel"), 3, [
            "visivel"    => "Todos (Ocultar Lixeira)", 
            ""           => "Mostrar Todos", 
            "ativo"      => "Ativo (Em Uso)", 
            "disponivel" => "Disponível (Estoque)", 
            "bloqueado"  => "Bloqueado", 
            "perdido"    => "Perdido", 
            "quebrado"   => "Quebrado",
            "excluido"   => "Excluído (Lixeira)"
        ])
    ];
    $botoesBusca = [
        botao("Buscar", "index", "", "", "", "", "btn btn-info"),
        "<button type='button' class='btn default' onclick=\"window.location.href='cadastro_rfid.php';\">Limpar Filtros</button>",
        botao("Inserir", "visualizarCadastro", "", "", "", "", "btn btn-success")
    ];

    echo abre_form();
    echo linha_form($camposBusca);
    echo fecha_form([], "<hr><form>".implode(" ", $botoesBusca)."</form>");

    listarRfids();
    rodape();
};

function listarRfids(){
    $gridFields = [
        "CÓDIGO"        => "rfids_nb_id",
        "UID"           => "rfids_tx_uid",
        "ID USUÁRIO"    => "rfids_nb_entidade_id",
        "FUNCIONÁRIO"   => "IFNULL(user.user_tx_nome, '---') AS funcionario_nome",
        "STATUS"        => "rfids_tx_status",
        "DESCRIÇÃO"     => "IF(CHAR_LENGTH(rfids_tx_descricao) > 40, CONCAT(LEFT(rfids_tx_descricao, 40), '...'), rfids_tx_descricao) AS descricao_curta",
        "CADASTRADO EM" => "DATE_FORMAT(rfid_dt_created_at, '%d/%m/%Y %H:%i:%s') AS data_formatada",
    ];

    $camposBuscaGrid = [
        "busca_uid_like"    => "rfids_tx_uid",
        "busca_status_like" => "status_pesquisa"
    ];

    $queryBase = "SELECT * FROM (
                    SELECT " . implode(", ", array_values($gridFields)) . ",
                            CONCAT(rfids_tx_status, IF(rfids_tx_status != 'excluido', ' visivel', '')) AS status_pesquisa
                    FROM rfids 
                    LEFT JOIN user ON rfids.rfids_nb_entidade_id = user.user_nb_id
                    ) AS base_query";

    $acoesGrid = gerarAcoesComConfirmacao(
        "cadastro_rfid.php", 
        "modificarRfid", 
        "excluirRfid", 
        "Excluir o RFID código: ", 
        "CÓDIGO"
    );

    $gridFields["actions"] = $acoesGrid["tags"];
    $jsFunctions = $acoesGrid["js"];

    echo gridDinamico("rfids", $gridFields, $camposBuscaGrid, $queryBase, $jsFunctions);
}

// VISUALIZAR CADASTRO (criação e edição)
function modificarRfid(){
    $id = intval($_POST["id"] ?? 0);
    if($id > 0){
        $rfid = mysqli_fetch_assoc(query("SELECT * FROM rfids WHERE rfids_nb_id = {$id}"));
        $_POST["rfids_tx_uid"]       = $rfid["rfids_tx_uid"];
        $_POST["rfids_tx_status"]    = $rfid["rfids_tx_status"];
        $_POST["rfids_tx_descricao"] = $rfid["rfids_tx_descricao"];
    };

    visualizarCadastro();
    exit;
};

function visualizarCadastro(){
    echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>";
    cabecalho("Ficha de RFID");

    $idRetorno = !empty($_POST["id_usuario_retorno"]) ? (int)$_POST["id_usuario_retorno"] : 0;
    $telaOrigem = !empty($_POST["tela_origem"]) ? $_POST["tela_origem"] : "";

    // REGRA DE NEGÓCIO: Novo crachá nasce travado como "Disponível"
    $campo_status = "";
    if (empty($_POST["id"])) {
        $campo_status = texto("Status do cartão", "<span class='label label-success'>Em estoque (Disponível)</span>", 4) . 
                        campo_hidden("rfids_tx_status", "disponivel");
    } else {
        // Se for edição, libera o combo com as opções
        $campo_status = call_user_func(function() {
            $statusAtual = !empty($_POST["rfids_tx_status"]) ? $_POST["rfids_tx_status"] : 'disponivel';
            $opcoes_status = [
                'disponivel' => 'Em estoque (Disponível)',
                'bloqueado'  => 'Bloqueado (Suspenso)',
                'perdido'    => 'Perdido',
                'quebrado'   => 'Danificado/Quebrado'
            ];
            
            if ($statusAtual == 'ativo') {
                $opcoes_status['ativo'] = 'Em Uso (Ativo) - Ficha do Funcionário';
            } elseif ($statusAtual == 'excluido') {
                $opcoes_status['excluido'] = 'Excluído (Lixeira)';
            };
            
            return combo_radio("Status do cartão", "rfids_tx_status", $statusAtual, 4, $opcoes_status);
        });
    };

    echo abre_form();
    echo linha_form([
        campo_hidden("id", (!empty($_POST["id"]) ? $_POST["id"] : "")),
        campo_hidden("id_usuario_retorno", ($idRetorno > 0 ? $idRetorno : "")),
        campo_hidden("tela_origem", $telaOrigem),
        campo("UID*", "rfids_tx_uid", (!empty($_POST["rfids_tx_uid"]) ? $_POST["rfids_tx_uid"] : ""), 4, "", "required autofocus"),
        $campo_status,  
        campo("Descrição", "rfids_tx_descricao", (!empty($_POST["rfids_tx_descricao"]) ? $_POST["rfids_tx_descricao"] : ""), 4)
    ]);

    $botoes = [];
    $chaves = []; $valores = [];
    if(!empty($_POST["id"])) { $chaves[] = "id"; $valores[] = $_POST["id"]; }
    if($idRetorno > 0) { $chaves[] = "id_usuario_retorno"; $valores[] = $idRetorno; }
    if(!empty($telaOrigem)) { $chaves[] = "tela_origem"; $valores[] = $telaOrigem; }

    $strChaves = implode(",", $chaves);
    $strValores = implode(",", $valores);
    $textoBotao = !empty($_POST["id"]) ? "Atualizar" : "Cadastrar";

    $botoes[] = botao($textoBotao, "cadastrarRfid", $strChaves, $strValores, "", "", "btn btn-success");
    
    if ($telaOrigem == 'grid_funcionario') {
        $botoes[] = "<button type='button' class='btn btn-warning' onclick=\"window.location.href='cadastro_funcionario.php';\">Voltar para Funcionários</button>";
    } elseif ($idRetorno > 0) {
        $botoes[] = "<button type='button' class='btn btn-warning' onclick=\"var f=document.createElement('form');f.method='POST';f.action='cadastro_usuario.php';var i=document.createElement('input');i.type='hidden';i.name='id';i.value='{$idRetorno}';f.appendChild(i);var a=document.createElement('input');a.type='hidden';a.name='acao';a.value='modificarUsuario';f.appendChild(a);document.body.appendChild(f);f.submit();\">Voltar para Usuário</button>";
    } else {
        // O botão voltar padrão leva de volta para a tela de Busca (index) sem travar no "required"
        $botoes[] = "<button type='button' class='btn btn-warning' onclick=\"window.location.href='cadastro_rfid.php';\">Voltar</button>";
    }

    echo fecha_form($botoes);
    rodape();
};

// AÇÕES DE BANCO DE DADOS (COM AUDITORIA / LOG)
function cadastrarRfid(){
    $fields = ["rfids_tx_uid", "rfids_tx_status", "rfids_tx_descricao"];
    foreach($fields as $field){
        $_POST[$field] = trim($_POST[$field] ?? "");
    };

    $errorMsg = conferirCamposObrig(["rfids_tx_uid" => "UID"], $_POST);
    if(!empty($errorMsg)){
        set_status("ERRO: " . $errorMsg);
        visualizarCadastro();
        exit;
    };

    $uidQuery = !empty($_POST["id"]) ?
        [ "SELECT rfids_nb_id FROM rfids WHERE rfids_tx_uid = ? AND rfids_nb_id != ?;", "si", [$_POST["rfids_tx_uid"], (int)$_POST["id"]] ] :
        [ "SELECT rfids_nb_id FROM rfids WHERE rfids_tx_uid = ?;", "s", [$_POST["rfids_tx_uid"]] ];

    $uidExists = !empty(mysqli_fetch_assoc(query($uidQuery[0], $uidQuery[1], $uidQuery[2])));
    if($uidExists){
        set_status("<script>Swal.fire('Erro!', 'Este UID já está cadastrado.', 'error');</script>");
        visualizarCadastro();
        exit;
    };

    $dados = [
        "rfids_tx_uid"       => $_POST["rfids_tx_uid"],
        "rfids_tx_status"    => (!empty($_POST["rfids_tx_status"]) ? $_POST["rfids_tx_status"] : 'disponivel'),
        "rfids_tx_descricao" => $_POST["rfids_tx_descricao"],
    ];

    $idRetorno = !empty($_POST["id_usuario_retorno"]) ? (int)$_POST["id_usuario_retorno"] : 0;
    $telaOrigem = !empty($_POST["tela_origem"]) ? $_POST["tela_origem"] : "";

    $jsRedirect = "";
    if ($telaOrigem == 'grid_funcionario') {
        $jsRedirect = "window.location.href = 'cadastro_funcionario.php';";
    } elseif ($idRetorno > 0) {
        $jsRedirect = "var f = document.createElement('form'); f.method = 'POST'; f.action = 'cadastro_usuario.php'; var idInput = document.createElement('input'); idInput.type = 'hidden'; idInput.name = 'id'; idInput.value = '{$idRetorno}'; f.appendChild(idInput); var acaoInput = document.createElement('input'); acaoInput.type = 'hidden'; acaoInput.name = 'acao'; acaoInput.value = 'modificarUsuario'; f.appendChild(acaoInput); document.body.appendChild(f); f.submit();";
    } else {
        $jsRedirect = "window.location.href = 'cadastro_rfid.php';";
    };

    // SALVAR E GERAR LOG
    if(!empty($_POST["id"])){
        $id_rfid = (int)$_POST["id"];
        
        $cracha_antigo = mysqli_fetch_assoc(query("SELECT rfids_tx_status, rfids_nb_entidade_id FROM rfids WHERE rfids_nb_id = {$id_rfid}"));
        atualizar("rfids", array_keys($dados), array_values($dados), $id_rfid, "rfids_nb_id");
        
        $entidade_nova = $cracha_antigo["rfids_nb_entidade_id"];
        if (in_array($dados["rfids_tx_status"], ['disponivel', 'perdido', 'quebrado', 'bloqueado', 'excluido'])) {
            query("UPDATE rfids SET rfids_nb_entidade_id = NULL WHERE rfids_nb_id = {$id_rfid}");
            $entidade_nova = null;
        };
        
        registrarLogRfid($id_rfid, "STATUS_ALTERADO", $cracha_antigo["rfids_tx_status"], $dados["rfids_tx_status"], $cracha_antigo["rfids_nb_entidade_id"], $entidade_nova, "Alterado via ficha do RFID.");
        
        $jsEditarNovamente = "
            var f = document.createElement('form');
            f.method = 'POST';
            f.action = 'cadastro_rfid.php';
            
            var a = document.createElement('input'); a.type = 'hidden'; a.name = 'acao'; a.value = 'modificarRfid'; f.appendChild(a);
            var i = document.createElement('input'); i.type = 'hidden'; i.name = 'id'; i.value = '{$id_rfid}'; f.appendChild(i);
            var o = document.createElement('input'); o.type = 'hidden'; o.name = 'tela_origem'; o.value = '{$telaOrigem}'; f.appendChild(o);
            var u = document.createElement('input'); u.type = 'hidden'; u.name = 'id_usuario_retorno'; u.value = '{$idRetorno}'; f.appendChild(u);
            
            document.body.appendChild(f);
            f.submit();
        ";
        set_status(alertaSucessoAtualizacao('Sucesso!', 'RFID atualizado com sucesso.', $jsRedirect, $jsEditarNovamente));
        visualizarCadastro();
        exit;

    } else {
        $id_novo_rfid = inserir("rfids", array_keys($dados), array_values($dados))[0];
        registrarLogRfid($id_novo_rfid, "CADASTRO", "inexistente", $dados["rfids_tx_status"], null, null, "Crachá novo inserido no estoque.");
        
        set_status(alertaSucessoCadastro('Sucesso!', 'Crachá cadastrado com sucesso!', 'visualizarCadastro', 'cadastro_rfid.php'));
        
        unset($_POST["rfids_tx_uid"], $_POST["rfids_tx_status"], $_POST["rfids_tx_descricao"], $_POST["acao"]);
        visualizarCadastro();
        exit;
    };
    
    exit;
};

function excluirRfid(){
    $id_rfid = (int)$_POST['id'];
    $cracha_antigo = mysqli_fetch_assoc(query("SELECT rfids_tx_status, rfids_nb_entidade_id FROM rfids WHERE rfids_nb_id = {$id_rfid}"));
    
    // Exclusão Lógica (Soft Delete)
    query("UPDATE rfids SET rfids_tx_status = 'excluido', rfids_nb_entidade_id = NULL WHERE rfids_nb_id = {$id_rfid}");
    registrarLogRfid($id_rfid, "EXCLUSAO", $cracha_antigo["rfids_tx_status"], "excluido", $cracha_antigo["rfids_nb_entidade_id"], null, "Crachá movido para a lixeira.");
    
    set_status("<script>Swal.fire('Sucesso!', 'RFID movido para a lixeira.', 'info');</script>");
    index();
    exit;
};
?>