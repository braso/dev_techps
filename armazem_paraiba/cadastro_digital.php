<?php
include_once "utils/utils.php";
include_once "check_permission.php";
include_once "load_env.php";
include_once "conecta.php";

date_default_timezone_set('America/Fortaleza');

function index(){
    echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>";
    cabecalho("Gestão de Biometria (Digitais)");

    // Buscando equipamentos para o filtro
    $sqlEquipamentos = query("SELECT equip_nb_id, equip_tx_nome FROM equipamentos WHERE equip_tx_status = 'ativo'");
    $opcoesEquipamentos = ["$" => "Todos os Equipamentos"];
    while($eq = mysqli_fetch_assoc($sqlEquipamentos)){
        $opcoesEquipamentos[$eq['equip_nb_id']] = $eq['equip_tx_nome'];
    }

    $camposBusca = [
        campo("Buscar Usuário", "busca_usuario_like", ($_POST["busca_usuario_like"] ?? ""), 3, "", "maxlength='255'"),
        combo("Equipamento", "busca_equipamento", ($_POST["busca_equipamento"] ?? ""), 3, $opcoesEquipamentos),
        combo("Status", "busca_status_like", ($_POST["busca_status_like"] ?? "visivel"), 3, [
            "visivel"       => "Todos (Ocultar Lixeira)", 
            ""              => "Mostrar Todos", 
            "ativo"         => "Ativo (Em Uso)", 
            "pendente_sync" => "Pendente de Sincronização",
            "excluido"      => "Excluído (Lixeira)"
        ])
    ];
    $botoesBusca = [
        botao("Buscar", "index", "", "", "", "", "btn btn-info"),
        "<button type='button' class='btn default' onclick=\"window.location.href='cadastro_digital.php';\">Limpar Filtros</button>",
        botao("Novo Vínculo", "visualizarCadastro", "", "", "", "", "btn btn-success")
    ];

    echo abre_form();
    echo linha_form($camposBusca);
    echo fecha_form([], "<hr><form>".implode(" ", $botoesBusca)."</form>");

    listarDigitais();
    rodape();
};

function listarDigitais(){
    // 1. Array dedicado apenas para montar o SELECT do banco de dados (COM OS 'AS')
    $sqlSelectFields = [
        "digitais_nb_id",
        "digitais_tx_dedo",
        "digitais_nb_id_sensor",
        "IFNULL(user.user_tx_nome, '---') AS usuario_nome", 
        "IFNULL(equipamentos.equip_tx_nome, '---') AS equipamento_nome",
        "UPPER(IF(digitais_tx_status = 'excluido', CONCAT('EXCLUÍDO (', IFNULL(digitais_tx_motivo_exclusao, 'S/ Motivo'), ')'), digitais_tx_status)) AS status_view",
        "DATE_FORMAT(digitais_dt_created_at, '%d/%m/%Y %H:%i') AS data_cadastro",
        "DATE_FORMAT(digitais_dt_ultimo_acesso, '%d/%m/%Y %H:%i') AS ultimo_acesso",
        "IFNULL(user_log.user_tx_login, 'Sistema/API') AS quem_atualizou"
    ];

    // 2. O gridFields limpo, com as chaves exatas para o JS gerar o CSV (SEM OS 'AS')
    $gridFields = [
        "CÓDIGO"           => "digitais_nb_id",
        "USUÁRIO"          => "usuario_nome", 
        "EQUIPAMENTO"      => "equipamento_nome",
        "DEDO"             => "digitais_tx_dedo",
        "GAVETA (SENSOR)"  => "digitais_nb_id_sensor",
        "STATUS"           => "status_view",
        "ÚLTIMO ACESSO"    => "ultimo_acesso",
        "CADASTRADO EM"    => "data_cadastro",
        "ÚLT. ATUALIZAÇÃO" => "quem_atualizou"
    ];

    $camposBuscaGrid = [
        "busca_usuario_like" => "user.user_tx_nome",
        "busca_equipamento"  => "digitais_nb_equipamento_id",
        "busca_status_like"  => "status_pesquisa"
    ];

    $queryBase = "SELECT * FROM (
                    SELECT " . implode(", ", $sqlSelectFields) . ",
                           CONCAT(digitais_tx_status, IF(digitais_tx_status != 'excluido', ' visivel', '')) AS status_pesquisa
                    FROM digitais 
                    LEFT JOIN user ON digitais.digitais_nb_user_id = user.user_nb_id
                    LEFT JOIN equipamentos ON digitais.digitais_nb_equipamento_id = equipamentos.equip_nb_id
                    
                    /* Subconsulta para pegar o log mais recente */
                    LEFT JOIN (
                        SELECT dlog_nb_digital_id, MAX(dlog_nb_id) AS max_id 
                        FROM digitais_log 
                        GROUP BY dlog_nb_digital_id
                    ) AS ult_log ON digitais_nb_id = ult_log.dlog_nb_digital_id
                    LEFT JOIN digitais_log AS log_recente ON ult_log.max_id = log_recente.dlog_nb_id
                    LEFT JOIN user AS user_log ON log_recente.dlog_nb_user_atualiza = user_log.user_nb_id
                    ) AS base_query";

    $acoesGrid = gerarAcoesComConfirmacao("cadastro_digital.php", "modificarDigital", "excluirDigital", "CÓDIGO", "", "USUÁRIO", "");

    // Intercepta a exclusão genérica e troca pela nossa customizada do SweetAlert
    $acoesGrid["tags"][1] = str_replace('confirmarExclusaoGenerica(this,', 'confirmarExclusaoDigitalGrid(this,', $acoesGrid["tags"][1]);

    $gridFields["actions"] = $acoesGrid["tags"];
    $jsFunctions = $acoesGrid["js"];

    echo gridDinamico("digitais", $gridFields, $camposBuscaGrid, $queryBase, $jsFunctions);
    imprimirScriptModalExclusaoDigital();
}

function modificarDigital(){
    $id = intval($_POST["id"] ?? 0);
    if($id > 0){
        $digital = mysqli_fetch_assoc(query("SELECT * FROM digitais WHERE digitais_nb_id = {$id}"));
        $_POST["digitais_nb_user_id"]        = $digital["digitais_nb_user_id"];
        $_POST["digitais_nb_equipamento_id"] = $digital["digitais_nb_equipamento_id"];
        $_POST["digitais_nb_id_sensor"]      = $digital["digitais_nb_id_sensor"];
        $_POST["digitais_tx_dedo"]           = $digital["digitais_tx_dedo"];
        $_POST["digitais_tx_status"]         = $digital["digitais_tx_status"];
    };
    visualizarCadastro();
    exit;
};

function visualizarCadastro(){
    echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>";
    cabecalho("Ficha de Digital");

    $id = !empty($_POST["id"]) ? (int)$_POST["id"] : 0;
    $telaOrigem = !empty($_POST["tela_origem"]) ? $_POST["tela_origem"] : "";

    // Combos de Relacionamento (Adaptar conforme suas funções de combo no utils.php)
    $sqlUsuarios = query("SELECT user_nb_id, user_tx_nome FROM user WHERE user_tx_status != 'inativo' ORDER BY user_tx_nome");
    $opcoesUsers = ["$" => "Selecione o Usuário"];
    while($u = mysqli_fetch_assoc($sqlUsuarios)){ $opcoesUsers[$u['user_nb_id']] = $u['user_tx_nome']; }

    $sqlEquipamentos = query("SELECT equip_nb_id, equip_tx_nome FROM equipamentos WHERE equip_tx_status = 'ativo' ORDER BY equip_tx_nome");
    $opcoesEquipamentos = ["$" => "Selecione o Equipamento"];
    while($eq = mysqli_fetch_assoc($sqlEquipamentos)){ $opcoesEquipamentos[$eq['equip_nb_id']] = $eq['equip_tx_nome']; }

    $opcoesDedos = [
        "Polegar Direito" => "Polegar Direito", "Indicador Direito" => "Indicador Direito", "Medio Direito" => "Médio Direito", "Anelar Direito" => "Anelar Direito", "Minimo Direito" => "Mínimo Direito",
        "Polegar Esquerdo" => "Polegar Esquerdo", "Indicador Esquerdo" => "Indicador Esquerdo", "Medio Esquerdo" => "Médio Esquerdo", "Anelar Esquerdo" => "Anelar Esquerdo", "Minimo Esquerdo" => "Mínimo Esquerdo"
    ];

    $statusAtual = !empty($_POST["digitais_tx_status"]) ? $_POST["digitais_tx_status"] : 'pendente_sync';
    
    // Status visual
    if ($statusAtual == 'excluido') {
        $campo_status = texto("Status", "<span class='label label-danger'>Excluído (Lixeira)</span>", 3) . campo_hidden("digitais_tx_status", "excluido");
    } else {
        $campo_status = combo_radio("Status", "digitais_tx_status", $statusAtual, 3, ['pendente_sync' => 'Pendente Sync', 'ativo' => 'Ativo no Sensor']);
    }

    echo abre_form();
    echo linha_form([
        campo_hidden("id", ($id > 0 ? $id : "")),
        campo_hidden("tela_origem", $telaOrigem),
        combo("Usuário*", "digitais_nb_user_id", ($_POST["digitais_nb_user_id"] ?? ""), 3, $opcoesUsers),
        combo("Equipamento*", "digitais_nb_equipamento_id", ($_POST["digitais_nb_equipamento_id"] ?? ""), 3, $opcoesEquipamentos),
        combo("Dedo*", "digitais_tx_dedo", ($_POST["digitais_tx_dedo"] ?? ""), 3, $opcoesDedos)
    ]);

    echo linha_form([
        campo("Gaveta Sensor (ID Interno)*", "digitais_nb_id_sensor", ($_POST["digitais_nb_id_sensor"] ?? ""), 3, "", "type='number' min='1' max='5000'"),
        $campo_status,
        texto("Captura Biométrica", "<button type='button' class='btn btn-default' disabled><i class='fa fa-fingerprint'></i> Aguardando API (Leitor)</button>", 4)
    ]);

    // Botões
    $botoes = [botao(($id > 0 ? "Atualizar" : "Cadastrar"), "cadastrarDigital", "id", $id, "", "", "btn btn-success")];
    
    if ($id > 0) {
        if ($statusAtual == 'excluido') {
            $botoes[] = botaoAcaoComConfirmacao($id, 'restaurarDigital', 'Restaurar', 'fa fa-recycle', 'btn btn-info', 'Restaurar Digital?', 'A digital voltará para status de Pendente Sync.');
            $botoes[] = botaoAcaoComConfirmacao($id, 'excluirDefinitivoDigital', 'Excluir Definitivamente', 'fa fa-trash', 'btn btn-danger', 'ATENÇÃO!', 'Isso apagará o BLOB e todo o histórico do banco. Tem certeza?', 'warning', '#d9534f');
        } else {
            // Pega nome para o SweetAlert
            $nomeUser = $opcoesUsers[$_POST["digitais_nb_user_id"]];
            $botoes[] = "<button type='button' class='btn btn-danger' onclick=\"dispararSweetAlertExclusaoDigital('{$id}', '{$_POST["digitais_tx_dedo"]}', '{$nomeUser}')\"><i class='fa fa-trash'></i> Excluir</button>";
        }
    }

    $botoes[] = "<button type='button' class='btn btn-warning' onclick=\"window.location.href='cadastro_digital.php';\">Voltar</button>";
    echo fecha_form($botoes);

    // GRID DE AUDITORIA
    if ($id > 0) {
        echo "<br><div class='row'><div class='col-md-12'>";
        fieldset("Histórico de Atualizações");

        $gridFieldsLog = [
            "ID"                  => "dlog_nb_id",
            "DATA / HORA"         => "data_formatada",
            "QUEM ATUALIZOU"      => "usuario_nome",
            "AÇÃO"                => "dlog_tx_acao",
            "STATUS ANTERIOR"     => "status_ant",
            "STATUS ATUAL"        => "status_nov",
            "DETALHES DA MUDANÇA" => "dlog_tx_motivo"
        ];

        $queryBaseLog = "SELECT * FROM (
            SELECT 
                l.dlog_nb_id, DATE_FORMAT(l.dlog_dt_data, '%d/%m/%Y %H:%i:%s') AS data_formatada, IFNULL(u.user_tx_login, 'Sistema/API') AS usuario_nome,
                l.dlog_tx_acao, UPPER(l.dlog_tx_status_anterior) AS status_ant, UPPER(l.dlog_tx_status_novo) AS status_nov, l.dlog_tx_motivo
            FROM digitais_log l
            LEFT JOIN user u ON l.dlog_nb_user_atualiza = u.user_nb_id
            WHERE l.dlog_nb_digital_id = {$id}
        ) AS base_log";

        echo gridDinamico("gridAuditoriaDigital", $gridFieldsLog, [], $queryBaseLog, "var orderCol = 'dlog_nb_id DESC';");
        echo "</div></div><div class='clearfix'></div>";
    }
    
    imprimirScriptModalExclusaoDigital();
    rodape();
};

function cadastrarDigital(){
    // Em um fluxo normal, quem faz o insert é a API enviando o BLOB. 
    // Este método permite correções manuais pelo RH no painel web.
    $camposObrig = ["digitais_nb_user_id" => "Usuário", "digitais_nb_equipamento_id" => "Equipamento", "digitais_tx_dedo" => "Dedo", "digitais_nb_id_sensor" => "Gaveta (Sensor)"];
    $errorMsg = conferirCamposObrig($camposObrig, $_POST);

    if(!empty($errorMsg)){
        set_status("ERRO: " . $errorMsg);
        visualizarCadastro(); exit;
    }

    $dados = [
        "digitais_nb_user_id"        => $_POST["digitais_nb_user_id"],
        "digitais_nb_equipamento_id" => $_POST["digitais_nb_equipamento_id"],
        "digitais_nb_id_sensor"      => $_POST["digitais_nb_id_sensor"],
        "digitais_tx_dedo"           => $_POST["digitais_tx_dedo"],
        "digitais_tx_status"         => $_POST["digitais_tx_status"] ?? 'pendente_sync'
    ];

    $usuario_logado = $_SESSION['user_nb_id'] ?? null; // Ajuste para sua variável de sessão
    $id = !empty($_POST["id"]) ? (int)$_POST["id"] : 0;

    if($id > 0){
        $digital_antiga = mysqli_fetch_assoc(query("SELECT digitais_tx_status FROM digitais WHERE digitais_nb_id = {$id}"));
        atualizar("digitais", array_keys($dados), array_values($dados), $id, "digitais_nb_id");
        
        if($digital_antiga["digitais_tx_status"] != $dados["digitais_tx_status"]) {
            query("INSERT INTO digitais_log (dlog_nb_digital_id, dlog_tx_acao, dlog_tx_status_anterior, dlog_tx_status_novo, dlog_nb_user_atualiza, dlog_tx_motivo) 
                   VALUES ({$id}, 'STATUS_ALTERADO', '{$digital_antiga["digitais_tx_status"]}', '{$dados["digitais_tx_status"]}', '{$usuario_logado}', 'Alteração manual no painel')");
        }
        set_status(alertaSucessoAtualizacao('Sucesso!', 'Digital atualizada.', "window.location.href='cadastro_digital.php';", ""));
    } else {
        $id_nova = inserir("digitais", array_keys($dados), array_values($dados))[0];
        query("INSERT INTO digitais_log (dlog_nb_digital_id, dlog_tx_acao, dlog_tx_status_anterior, dlog_tx_status_novo, dlog_nb_user_atualiza, dlog_tx_motivo) 
               VALUES ({$id_nova}, 'CADASTRO_MANUAL', 'inexistente', '{$dados["digitais_tx_status"]}', '{$usuario_logado}', 'Criado manualmente pelo painel RH')");
        set_status(alertaSucessoCadastro('Sucesso!', 'Vínculo cadastrado!', 'visualizarCadastro', 'cadastro_digital.php'));
    }
    visualizarCadastro(); exit;
}

function excluirDigital(){
    $id = (int)$_POST['id'];
    $motivo = mb_substr(trim($_POST['motivo_combo'] ?? ''), 0, 100);
    $usuario_logado = $_SESSION['user_nb_id'] ?? 'NULL';
    
    $digital_antiga = mysqli_fetch_assoc(query("SELECT digitais_tx_status FROM digitais WHERE digitais_nb_id = {$id}"));
    
    query("UPDATE digitais SET digitais_tx_status = 'excluido', digitais_tx_motivo_exclusao = '{$motivo}' WHERE digitais_nb_id = {$id}");
    
    query("INSERT INTO digitais_log (dlog_nb_digital_id, dlog_tx_acao, dlog_tx_status_anterior, dlog_tx_status_novo, dlog_nb_user_atualiza, dlog_tx_motivo) 
           VALUES ({$id}, 'EXCLUSAO_SOFT', '{$digital_antiga["digitais_tx_status"]}', 'excluido', {$usuario_logado}, 'Enviado para Lixeira. Motivo: {$motivo}')");
           
    set_status("<script>Swal.fire('Lixeira!', 'Digital enviada para a lixeira.', 'info');</script>");
    index(); exit;
}

function restaurarDigital(){
    $id = (int)$_POST['id'];
    $usuario_logado = $_SESSION['user_nb_id'] ?? 'NULL';
    query("UPDATE digitais SET digitais_tx_status = 'pendente_sync', digitais_tx_motivo_exclusao = NULL WHERE digitais_nb_id = {$id}");
    
    query("INSERT INTO digitais_log (dlog_nb_digital_id, dlog_tx_acao, dlog_tx_status_anterior, dlog_tx_status_novo, dlog_nb_user_atualiza, dlog_tx_motivo) 
           VALUES ({$id}, 'RESTAURACAO', 'excluido', 'pendente_sync', {$usuario_logado}, 'Restaurado da lixeira. Aguardando sincronização.')");
           
    set_status("<script>Swal.fire('Restaurado!', 'Digital pronta para nova sincronização.', 'success');</script>");
    index(); exit;
}

function excluirDefinitivoDigital(){
    $id = (int)$_POST['id'];
    if ($id > 0) {
        $digital = mysqli_fetch_assoc(query("SELECT digitais_tx_status FROM digitais WHERE digitais_nb_id = {$id} LIMIT 1"));
        if ($digital && $digital['digitais_tx_status'] === 'excluido') {
            query("DELETE FROM digitais_log WHERE dlog_nb_digital_id = {$id}"); // Se o CASCADE não tiver ativo, isso garante
            query("DELETE FROM digitais WHERE digitais_nb_id = {$id} AND digitais_tx_status = 'excluido'");
            set_status("<script>Swal.fire('Excluído!', 'Registro e BLOB removidos permanentemente.', 'success');</script>");
        }
    }
    index(); exit;
}

// SCRIPTS DO SWEETALERT
function imprimirScriptModalExclusaoDigital() {
    echo "
    <script>
    function dispararSweetAlertExclusaoDigital(id, dedo, usuarioNome) {
        var textoAviso = '<div style=\"margin-bottom:15px; text-align:left;\">Deseja remover o <b>' + dedo + '</b> do usuário <b>' + usuarioNome + '</b>?<br><small>A placa correspondente será avisada para liberar o espaço na memória (Sensor).</small></div>';

        var htmlForm = textoAviso +
            '<div style=\"text-align: left;\">' +
            '<label style=\"font-weight:bold;\">Motivo da Exclusão: *</label>' +
            '<select id=\"swal-motivo-combo\" class=\"form-control\" style=\"margin-bottom: 10px;\">' +
                '<option value=\"\">Selecione...</option>' +
                '<option value=\"Demitido\">Funcionário Desligado</option>' +
                '<option value=\"Digital Falhando\">Digital Falhando/Baixa Qualidade</option>' +
                '<option value=\"Espaço Liberado (LRU)\">Liberar Espaço no Hardware</option>' +
                '<option value=\"Erro de Cadastro\">Erro de Cadastro</option>' +
            '</select></div>';

        Swal.fire({
            title: 'Excluir Biometria',
            html: htmlForm,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d9534f',
            confirmButtonText: '<i class=\"fa fa-trash\"></i> Enviar para Lixeira',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const combo = document.getElementById('swal-motivo-combo').value;
                if (!combo) { Swal.showValidationMessage('Selecione um motivo.'); return false; }
                return combo;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST'; form.action = 'cadastro_digital.php'; 
                var iAcao = document.createElement('input'); iAcao.type = 'hidden'; iAcao.name = 'acao'; iAcao.value = 'excluirDigital'; form.appendChild(iAcao);
                var iId = document.createElement('input'); iId.type = 'hidden'; iId.name = 'id'; iId.value = id; form.appendChild(iId);
                var iMotivo = document.createElement('input'); iMotivo.type = 'hidden'; iMotivo.name = 'motivo_combo'; iMotivo.value = result.value; form.appendChild(iMotivo);
                document.body.appendChild(form); form.submit();
            }
        });
    }

    function confirmarExclusaoDigitalGrid(elemento) {
        var linha = $(elemento).closest('tr');
        var tabela = $(elemento).closest('table');
        var dadosDaLinha = {};
        tabela.find('thead th').each(function(index) {
            dadosDaLinha[$(this).text().trim().toUpperCase()] = linha.find('td').eq(index).text().trim();
        });
        
        var id = linha.attr('data-row-id') || dadosDaLinha['CÓDIGO'];
        var dedo = dadosDaLinha['DEDO'];
        var usuario = dadosDaLinha['USUÁRIO'];
        dispararSweetAlertExclusaoDigital(id, dedo, usuario);
    }
    </script>";
}
?>