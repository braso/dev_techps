<?php
include_once "utils/utils.php";
include_once "check_permission.php";
include_once "load_env.php";
include_once "conecta.php";

// TRAVA O RELÓGIO DO PHP NO FUSO correto
date_default_timezone_set('America/Fortaleza');

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
        "ID USUÁRIO"    => "rfids_nb_user_id",
        "USUÁRIO"       => "IFNULL(user.user_tx_nome, '---') AS usuario_nome", 
        "STATUS"        => "UPPER(IF(rfids_tx_status = 'excluido', CONCAT('EXCLUÍDO (', IFNULL(rfids_tx_motivo_exclusao, 'S/ Motivo'), ')'), rfids_tx_status)) AS status_view",
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
                    LEFT JOIN user ON rfids.rfids_nb_user_id = user.user_nb_id
                    ) AS base_query";

    $acoesGrid = gerarAcoesComConfirmacao(
        "cadastro_rfid.php", 
        "modificarRfid", 
        "excluirRfid", 
        "CÓDIGO", 
        "",
        "USUÁRIO", 
        ""
    );

    // O PULO DO GATO: Intercepta a exclusão genérica da utils.php e troca pela nossa customizada
    $acoesGrid["tags"][1] = str_replace(
        'confirmarExclusaoGenerica(this,', 
        'confirmarExclusaoRfidGrid(this,', 
        $acoesGrid["tags"][1]
    );

    $gridFields["actions"] = $acoesGrid["tags"];
    $jsFunctions = $acoesGrid["js"];

    echo gridDinamico("rfids", $gridFields, $camposBuscaGrid, $queryBase, $jsFunctions);
    
    // Imprime a estrutura do SweetAlert customizado
    imprimirScriptModalExclusao();
}

function modificarRfid(){
    $id = intval($_POST["id"] ?? 0);
    if($id > 0){
        $rfid = mysqli_fetch_assoc(query("SELECT * FROM rfids WHERE rfids_nb_id = {$id}"));
        $_POST["rfids_tx_uid"]             = $rfid["rfids_tx_uid"];
        $_POST["rfids_tx_status"]          = $rfid["rfids_tx_status"];
        $_POST["rfids_tx_descricao"]       = $rfid["rfids_tx_descricao"];
    };

    visualizarCadastro();
    exit;
};

function visualizarCadastro(){
    echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>";
    cabecalho("Ficha de RFID");

    $id = !empty($_POST["id"]) ? (int)$_POST["id"] : 0;
    $idRetorno = !empty($_POST["id_usuario_retorno"]) ? (int)$_POST["id_usuario_retorno"] : 0;
    $telaOrigem = !empty($_POST["tela_origem"]) ? $_POST["tela_origem"] : "";

    $statusAtual = !empty($_POST["rfids_tx_status"]) ? $_POST["rfids_tx_status"] : 'disponivel';
    $campo_status = "";
    
    if ($id == 0) {
        $campo_status = texto("Status do cartão", "<span class='label label-success'>Em estoque (Disponível)</span>", 4) . 
                        campo_hidden("rfids_tx_status", "disponivel");
    } else {
        // TELA ENXUTA: Agora a edição só tem Disponível e Ativo
        $opcoes_status = ['disponivel' => 'Em estoque (Disponível)'];
        if ($statusAtual == 'ativo') {
            $opcoes_status['ativo'] = 'Em Uso (Ativo) - Ficha do Funcionário';
        }
        
        if ($statusAtual == 'excluido') {
            $campo_status = texto("Status do cartão", "<span class='label label-danger'>Excluído (Inutilizado / Lixeira)</span>", 4) . 
                            campo_hidden("rfids_tx_status", "excluido");
        } else {
            $campo_status = combo_radio("Status do cartão", "rfids_tx_status", $statusAtual, 4, $opcoes_status);
        }
    };

    echo abre_form();
    echo linha_form([
        campo_hidden("id", ($id > 0 ? $id : "")),
        campo_hidden("id_usuario_retorno", ($idRetorno > 0 ? $idRetorno : "")),
        campo_hidden("tela_origem", $telaOrigem),
        campo("UID*", "rfids_tx_uid", (!empty($_POST["rfids_tx_uid"]) ? $_POST["rfids_tx_uid"] : ""), 4, "", "required autofocus"),
        $campo_status,  
        campo("Descrição", "rfids_tx_descricao", (!empty($_POST["rfids_tx_descricao"]) ? $_POST["rfids_tx_descricao"] : ""), 4)
    ]);

    $botoes = [];
    $chaves = []; $valores = [];
    if($id > 0) { $chaves[] = "id"; $valores[] = $id; };
    if($idRetorno > 0) { $chaves[] = "id_usuario_retorno"; $valores[] = $idRetorno; };
    if(!empty($telaOrigem)) { $chaves[] = "tela_origem"; $valores[] = $telaOrigem; };

    $strChaves = implode(",", $chaves);
    $strValores = implode(",", $valores);
    $textoBotao = ($id > 0) ? "Atualizar" : "Cadastrar";

    // O botão verde padrão de Salvar
    $botoes[] = botao($textoBotao, "cadastrarRfid", $strChaves, $strValores, "", "", "btn btn-success");
    
    // ==========================================================
    // GESTÃO DOS BOTÕES EXTRAS DE EXCLUSÃO/RESTAURAÇÃO
    // ==========================================================
    if ($id > 0) {
        if ($statusAtual == 'excluido') {
            // Se já está excluído, mostra Restaurar e Apagar do Banco
            $botoes[] = botaoAcaoComConfirmacao($id, 'restaurarRfid', 'Restaurar RFID', 'fa fa-recycle', 'btn btn-info', 'Restaurar RFID?', 'Este RFID sairá da lixeira e voltará para o Estoque (Disponível).');
            $botoes[] = botaoAcaoComConfirmacao($id, 'excluirDefinitivoRfid', 'Excluir Definitivamente', 'fa fa-trash', 'btn btn-danger', 'ATENÇÃO: Exclusão Permanente!', 'Isso vai apagar o RFID E TODO O HISTÓRICO DE AUDITORIA dele do banco de dados. Tem certeza?', 'warning', '#d9534f');
        } else {
            // Se NÃO está excluído, mostra o botão de enviar pra Lixeira (acessa o mesmo SweetAlert do Grid)
            $rfid_info = mysqli_fetch_assoc(query("SELECT rfids_tx_uid, IFNULL(user.user_tx_nome, '---') AS usuario_nome FROM rfids LEFT JOIN user ON rfids.rfids_nb_user_id = user.user_nb_id WHERE rfids_nb_id = {$id}"));
            $uid_tela = $rfid_info['rfids_tx_uid'];
            $usuario_tela = $rfid_info['usuario_nome'];
            
            $botoes[] = "<button type='button' class='btn btn-danger' onclick=\"dispararSweetAlertExclusao('{$id}', '{$uid_tela}', '{$usuario_tela}')\"><i class='fa fa-trash'></i> Excluir</button>";
        }
    }
    // ==========================================================

    if ($telaOrigem == 'grid_funcionario') {
        $botoes[] = "<button type='button' class='btn btn-warning' onclick=\"window.location.href='cadastro_funcionario.php';\">Voltar para Funcionários</button>";
    } elseif ($idRetorno > 0) {
        $botoes[] = "<button type='button' class='btn btn-warning' onclick=\"var f=document.createElement('form');f.method='POST';f.action='cadastro_usuario.php';var i=document.createElement('input');i.type='hidden';i.name='id';i.value='{$idRetorno}';f.appendChild(i);var a=document.createElement('input');a.type='hidden';a.name='acao';a.value='modificarUsuario';f.appendChild(a);document.body.appendChild(f);f.submit();\">Voltar para Usuário</button>";
    } else {
        $botoes[] = "<button type='button' class='btn btn-warning' onclick=\"window.location.href='cadastro_rfid.php';\">Voltar</button>";
    }
    echo fecha_form($botoes);

    if ($id > 0) {
        echo "<br><div class='row'><div class='col-md-12'>";
        fieldset("Histórico de Atualizações");

        $gridFieldsLog = [
            "ID"                  => "rlog_nb_id",
            "DATA / HORA"         => "data_formatada",
            "QUEM ATUALIZOU"      => "usuario_nome",
            "AÇÃO"                => "rlog_tx_acao",
            "STATUS ANTERIOR"     => "status_ant",
            "STATUS ATUAL"        => "status_nov",
            "DETALHES DA MUDANÇA" => "detalhe_mudanca"
        ];

        $queryBaseLog = "SELECT * FROM (
            SELECT 
                l.rlog_nb_id, DATE_FORMAT(l.rlog_dt_data, '%d/%m/%Y %H:%i:%s') AS data_formatada, IFNULL(u.user_tx_login, 'Sistema') AS usuario_nome,
                CASE l.rlog_tx_acao WHEN 'CADASTRO' THEN 'Cadastro Inicial' WHEN 'STATUS_ALTERADO' THEN 'Alteração de Status' WHEN 'EXCLUSAO' THEN 'Enviado para Lixeira' WHEN 'RESTAURACAO' THEN 'Restaurado para Estoque' ELSE l.rlog_tx_acao END AS rlog_tx_acao,
                UPPER(l.rlog_tx_status_anterior) AS status_ant, UPPER(l.rlog_tx_status_novo) AS status_nov,
                CONCAT(l.rlog_tx_motivo, IF(l.rlog_nb_user_anterior IS NOT NULL OR l.rlog_nb_user_novo IS NOT NULL, CONCAT('<br><small style=\"color: #666;\">Movido de: <b>', IFNULL(u_ant.user_tx_nome, 'Gaveta (Sem vínculo)'), '</b> &rarr; Para: <b>', IFNULL(u_nov.user_tx_nome, 'Gaveta (Sem vínculo)'), '</b></small>'), '')) AS detalhe_mudanca
            FROM rfids_log l
            LEFT JOIN user u ON l.rlog_nb_user_atualiza = u.user_nb_id
            LEFT JOIN user u_ant ON l.rlog_nb_user_anterior = u_ant.user_nb_id
            LEFT JOIN user u_nov ON l.rlog_nb_user_novo = u_nov.user_nb_id
            WHERE l.rlog_nb_rfid_id = {$id}
        ) AS base_log";

        echo gridDinamico("gridAuditoriaRfid", $gridFieldsLog, [], $queryBaseLog, "var orderCol = 'rlog_nb_id DESC';");
        echo "</div></div><div class='clearfix'></div>";
    };
    
    // Imprime a estrutura do SweetAlert customizado
    imprimirScriptModalExclusao();
    rodape();
};

function restaurarRfid(){
    $id_rfid = (int)$_POST['id'];
    $cracha_antigo = mysqli_fetch_assoc(query("SELECT rfids_tx_status, rfids_nb_user_id FROM rfids WHERE rfids_nb_id = {$id_rfid}"));

    query("UPDATE rfids SET rfids_tx_status = 'disponivel', rfids_tx_motivo_exclusao = NULL, rfids_nb_user_id = NULL WHERE rfids_nb_id = {$id_rfid}");
    registrarLogRfid($id_rfid, "RESTAURACAO", $cracha_antigo["rfids_tx_status"], "disponivel", $cracha_antigo["rfids_nb_user_id"], null, "RFID restaurado da lixeira para o estoque.");
    
    set_status("<script>Swal.fire('Restaurado!', 'O RFID voltou para o estoque e está disponível para uso.', 'success');</script>");
    index();
    exit;
};

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

    $status_final = !empty($_POST["rfids_tx_status"]) ? $_POST["rfids_tx_status"] : 'disponivel';

    $dados = [
        "rfids_tx_uid"       => $_POST["rfids_tx_uid"],
        "rfids_tx_status"    => $status_final,
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

    if(!empty($_POST["id"])){
        $id_rfid = (int)$_POST["id"];
        $cracha_antigo = mysqli_fetch_assoc(query("SELECT rfids_tx_status, rfids_nb_user_id FROM rfids WHERE rfids_nb_id = {$id_rfid}"));
        
        atualizar("rfids", array_keys($dados), array_values($dados), $id_rfid, "rfids_nb_id");
        
        $entidade_nova = $cracha_antigo["rfids_nb_user_id"];
        if ($dados["rfids_tx_status"] === 'disponivel') {
            query("UPDATE rfids SET rfids_nb_user_id = NULL WHERE rfids_nb_id = {$id_rfid}");
            $entidade_nova = null;
        };
        
        registrarLogRfid($id_rfid, "STATUS_ALTERADO", $cracha_antigo["rfids_tx_status"], $dados["rfids_tx_status"], $cracha_antigo["rfids_nb_user_id"], $entidade_nova, "Alterado via ficha do RFID.");
        
        $jsEditarNovamente = "
            var f = document.createElement('form'); f.method = 'POST'; f.action = 'cadastro_rfid.php';
            var a = document.createElement('input'); a.type = 'hidden'; a.name = 'acao'; a.value = 'modificarRfid'; f.appendChild(a);
            var i = document.createElement('input'); i.type = 'hidden'; i.name = 'id'; i.value = '{$id_rfid}'; f.appendChild(i);
            var o = document.createElement('input'); o.type = 'hidden'; o.name = 'tela_origem'; o.value = '{$telaOrigem}'; f.appendChild(o);
            var u = document.createElement('input'); u.type = 'hidden'; u.name = 'id_usuario_retorno'; u.value = '{$idRetorno}'; f.appendChild(u);
            document.body.appendChild(f); f.submit();
        ";
        set_status(alertaSucessoAtualizacao('Sucesso!', 'RFID atualizado com sucesso.', $jsRedirect, $jsEditarNovamente));
        visualizarCadastro();
        exit;

    } else {
        query("SET time_zone = '-03:00'");
        $id_novo_rfid = inserir("rfids", array_keys($dados), array_values($dados))[0];
        registrarLogRfid($id_novo_rfid, "CADASTRO", "inexistente", $dados["rfids_tx_status"], null, null, "RFID novo inserido no estoque.");
        set_status(alertaSucessoCadastro('Sucesso!', 'RFID cadastrado com sucesso!', 'visualizarCadastro', 'cadastro_rfid.php'));
        unset($_POST["rfids_tx_uid"], $_POST["rfids_tx_status"], $_POST["rfids_tx_descricao"], $_POST["acao"]);
        visualizarCadastro();
        exit;
    };
    exit;
};

// LÓGICA UNIFICADA DE EXCLUSÃO (Recebe o motivo do SweetAlert)
function excluirRfid(){
    $id_rfid = (int)$_POST['id'];
    $combo = $_POST['motivo_combo'] ?? '';
    $outros = $_POST['motivo_outros'] ?? '';
    
    $motivo_final = ($combo === 'Outros') ? $outros : $combo;
    $motivo_final = mb_substr(trim($motivo_final), 0, 100);
    
    $cracha_antigo = mysqli_fetch_assoc(query("SELECT rfids_tx_status, rfids_nb_user_id FROM rfids WHERE rfids_nb_id = {$id_rfid}"));
    
    query("UPDATE rfids SET rfids_tx_status = 'excluido', rfids_tx_motivo_exclusao = '{$motivo_final}', rfids_nb_user_id = NULL WHERE rfids_nb_id = {$id_rfid}");
    
    registrarLogRfid($id_rfid, "EXCLUSAO", $cracha_antigo["rfids_tx_status"], "excluido", $cracha_antigo["rfids_nb_user_id"], null, "Enviado para a lixeira. Justificativa: " . $motivo_final);
    
    set_status("<script>Swal.fire('Sucesso!', 'RFID movido para a lixeira.', 'info');</script>");
    index();
    exit;
};

// FUNÇÃO GLOBAL DE JAVASCRIPT: Imprime o SweetAlert Inteligente para usar no Grid e na Ficha
function imprimirScriptModalExclusao() {
    echo "
    <script>
    function dispararSweetAlertExclusao(id, uid, usuarioNome) {
        var textoAviso = '';
        if (usuarioNome && usuarioNome !== '---' && usuarioNome !== '') {
            textoAviso = '<div style=\"background-color:#f2dede; color:#a94442; padding:10px; border-radius:5px; margin-bottom:15px; text-align:left;\">' +
                         '<b>ATENÇÃO!</b> O crachá <b>' + uid + '</b> está em uso por:<br><b>' + usuarioNome + '</b><br><br>' +
                         'Ele será desvinculado e enviado para a lixeira.</div>';
        } else {
            textoAviso = '<div style=\"margin-bottom:15px; text-align:left;\">Deseja mover o crachá <b>' + uid + '</b> para a lixeira?</div>';
        }

        var htmlForm = textoAviso +
            '<div style=\"text-align: left;\">' +
            '<label style=\"font-weight:bold;\">Selecione o Motivo da Exclusão: *</label>' +
            '<select id=\"swal-motivo-combo\" class=\"form-control\" style=\"margin-bottom: 10px;\" onchange=\"if(this.value===\'Outros\'){document.getElementById(\'swal-motivo-outros\').style.display=\'block\';}else{document.getElementById(\'swal-motivo-outros\').style.display=\'none\';}\">' +
                '<option value=\"\">Selecione a justificativa...</option>' +
                '<option value=\"Perdido\">Perdido</option>' +
                '<option value=\"Quebrado\">Danificado / Quebrado</option>' +
                '<option value=\"Bloqueado\">Bloqueado (Suspenso)</option>' +
                '<option value=\"Erro de Cadastro\">Cadastrado por Engano</option>' +
                '<option value=\"Outros\">Outros (Descrever)</option>' +
            '</select>' +
            '<input type=\"text\" id=\"swal-motivo-outros\" class=\"form-control\" placeholder=\"Descreva o motivo (Max 100)\" maxlength=\"100\" style=\"display: none;\">' +
            '</div>';

        Swal.fire({
            title: 'Excluir RFID',
            html: htmlForm,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d9534f',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class=\"fa fa-trash\"></i> Confirmar Exclusão',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const combo = document.getElementById('swal-motivo-combo').value;
                const outros = document.getElementById('swal-motivo-outros').value;
                if (!combo) {
                    Swal.showValidationMessage('Por favor, selecione o motivo da exclusão.');
                    return false;
                }
                if (combo === 'Outros' && !outros.trim()) {
                    Swal.showValidationMessage('Por favor, descreva o motivo na caixinha.');
                    return false;
                }
                return { combo: combo, outros: outros };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'cadastro_rfid.php'; 
                
                var inputAcao = document.createElement('input'); inputAcao.type = 'hidden'; inputAcao.name = 'acao'; inputAcao.value = 'excluirRfid'; form.appendChild(inputAcao);
                var inputId = document.createElement('input'); inputId.type = 'hidden'; inputId.name = 'id'; inputId.value = id; form.appendChild(inputId);
                var inputCombo = document.createElement('input'); inputCombo.type = 'hidden'; inputCombo.name = 'motivo_combo'; inputCombo.value = result.value.combo; form.appendChild(inputCombo);
                var inputOutros = document.createElement('input'); inputOutros.type = 'hidden'; inputOutros.name = 'motivo_outros'; inputOutros.value = result.value.outros; form.appendChild(inputOutros);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Adaptador para interceptar o clique da lixeira vindo do utils.php
    function confirmarExclusaoRfidGrid(elemento) {
        var linha = $(elemento).closest('tr');
        var tabela = $(elemento).closest('table');
        var dadosDaLinha = {};
        
        // Mapeia o nome das colunas
        tabela.find('thead th').each(function(index) {
            dadosDaLinha[$(this).text().trim().toUpperCase()] = linha.find('td').eq(index).text().trim();
        });
        
        var id = linha.attr('data-row-id') || dadosDaLinha['CÓDIGO'];
        var uid = dadosDaLinha['UID'];
        var usuario = dadosDaLinha['USUÁRIO'];
        
        dispararSweetAlertExclusao(id, uid, usuario);
    }
    </script>
    ";
}

// EXCLUSÃO DEFINITIVA (Hard Delete) com Trava de Segurança
function excluirDefinitivoRfid(){
    $id_rfid = (int)$_POST['id'];
    
    if ($id_rfid > 0) {
        // 1. TRAVA DE SEGURANÇA: Verifica se o crachá realmente está na lixeira
        $cracha = mysqli_fetch_assoc(query("SELECT rfids_tx_status FROM rfids WHERE rfids_nb_id = {$id_rfid} LIMIT 1"));
        
        if ($cracha && $cracha['rfids_tx_status'] === 'excluido') {
            
            // 2. Apaga primeiro o histórico de logs desse crachá
            query("DELETE FROM rfids_log WHERE rlog_nb_rfid_id = {$id_rfid}");
            
            // 3. Apaga o crachá definitivamente da tabela principal
            query("DELETE FROM rfids WHERE rfids_nb_id = {$id_rfid} AND rfids_tx_status = 'excluido'");
            
            set_status("<script>Swal.fire('Excluído!', 'O crachá e todo o seu histórico foram apagados permanentemente do sistema.', 'success');</script>");
            
        } else {
            // Se alguém tentar burlar o sistema enviando um ID de um crachá ativo
            set_status("<script>Swal.fire('Acesso Negado!', 'Tentativa de exclusão bloqueada. Apenas crachás na lixeira podem ser apagados definitivamente.', 'error');</script>");
        }
    }
    
    index();
    exit;
};
?>