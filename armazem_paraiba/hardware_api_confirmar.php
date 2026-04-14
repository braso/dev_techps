<?php
    /* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
    //*/

    require_once "../load_env.php";
    require_once "lib.php"; // Puxa os padrões de banco (get_data, etc)

    // 1. Cabeçalhos da API
    header('Content-Type: application/json; charset=utf-8');

    // 2. Lê o Payload (JSON enviado pelo Python)
    $json_recebido = file_get_contents('php://input');
    $dados = json_decode($json_recebido, true);

    // 3. Captura e Valida o Token de Segurança no Cabeçalho
    $headers = apache_request_headers();
    $token_recebido = '';

    if (isset($headers['Authorization'])) {
        $token_recebido = trim(str_replace('Bearer ', '', $headers['Authorization']));
    }

    if (empty($token_recebido)) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Acesso Negado: Token de seguranca ausente"]);
        exit;
    }

    // 4. Valida se os dados mínimos chegaram
    if (empty($dados['mac']) || empty($dados['id_tarefa']) || empty($dados['status_leitura'])) {
        http_response_code(400); // Bad Request
        echo json_encode(["status" => "error", "message" => "Dados enviados estao incompletos"]);
        exit;
    }

    $id_tarefa = (int) $dados['id_tarefa'];
    $status_leitura = $dados['status_leitura']; // 'sucesso' ou 'falha'
    $template_recebido = isset($dados['template']) ? $dados['template'] : null;

    // 5. Autenticação Dupla: MAC + Token
    $equipamento = get_data(
        "SELECT equip_nb_id FROM equipamentos 
         WHERE equip_tx_identificador = ? 
         AND equip_tx_token = ? 
         AND equip_tx_status = 'ativo' LIMIT 1;",
        [$dados['mac'], $token_recebido]
    );

    if (empty($equipamento)) {
        http_response_code(403); // Forbidden
        echo json_encode(["status" => "error", "message" => "Hardware não reconhecido ou Token inválido"]);
        exit;
    }
    
    $id_equipamento = $equipamento[0]['equip_nb_id'];

    // 6. Confirma a Tarefa no Banco
    $tarefa_atual = get_data(
        "SELECT digitais_tx_motivo_exclusao, digitais_bl_template 
         FROM digitais 
         WHERE digitais_nb_id = ? AND digitais_nb_equipamento_id = ? LIMIT 1;",
        [$id_tarefa, $id_equipamento]
    );

    if (empty($tarefa_atual)) {
        http_response_code(404); // Not Found
        echo json_encode(["status" => "error", "message" => "Tarefa inválida ou não pertence a este MAC"]);
        exit;
    }
    
    $dadosTarefa = $tarefa_atual[0];

    // =================================================================================
    // 7. O MOTOR DE ATUALIZAÇÃO (Regras de Negócio)
    // =================================================================================
    
    // CENÁRIO A: Hardware reportou falha (ex: dedo escorregou no Case do RH)
    if ($status_leitura == 'falha') {
        
        $log = [
            "dlog_nb_digital_id" => $id_tarefa,
            "dlog_tx_acao" => 'TENTATIVA_FALHA',
            "dlog_tx_status_anterior" => 'pendente_sync',
            "dlog_tx_status_novo" => 'pendente_sync',
            "dlog_tx_motivo" => 'Hardware reportou erro de leitura / timeout'
        ];
        
        insert_data("INSERT INTO digitais_log (" . implode(", ", array_keys($log)) . ") VALUES (:" . implode(", :", array_keys($log)) . ")", $log);
        
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Falha registrada no log"]);
        exit;
    }

    // CENÁRIO B: Hardware reportou Sucesso!
    $novo_status = 'ativo';
    $acao_log = 'SINCRONIZACAO_CONCLUIDA';
    $query_update = "";
    $update_params = [];

    if (!empty($dadosTarefa['digitais_tx_motivo_exclusao'])) {
        // Exclusão no Caminhão
        $novo_status = 'excluido';
        $acao_log = 'EXCLUSAO_CONCLUIDA';
        // Usa a função query() do seu legado para atualizar direto
        query("UPDATE digitais SET digitais_tx_status = '$novo_status', digitais_dt_updated_at = NOW() WHERE digitais_nb_id = $id_tarefa");
        
    } elseif (empty($dadosTarefa['digitais_bl_template']) && $template_recebido != null) {
        // Cadastro Novo no RH
        $acao_log = 'CADASTRO_CONCLUIDO';
        // Importante: No cadastro manual você usou a função atualizar(), vamos seguir o padrão
        $campos = ["digitais_tx_status", "digitais_bl_template", "digitais_dt_updated_at"];
        $valores = [$novo_status, $template_recebido, date('Y-m-d H:i:s')];
        atualizar("digitais", $campos, $valores, $id_tarefa, "digitais_nb_id");
        
    } else {
        // Confirmação de Download (Caminhão recebeu a digital)
        $acao_log = 'DOWNLOAD_CAMINHAO_CONCLUIDO';
        query("UPDATE digitais SET digitais_tx_status = '$novo_status', digitais_dt_updated_at = NOW() WHERE digitais_nb_id = $id_tarefa");
    }

    // 8. Grava o Histórico de Auditoria (Usando a sua função insert_data)
    $log_sucesso = [
        "dlog_nb_digital_id" => $id_tarefa,
        "dlog_tx_acao" => $acao_log,
        "dlog_tx_status_anterior" => 'pendente_sync',
        "dlog_tx_status_novo" => $novo_status,
        "dlog_tx_motivo" => 'Confirmacao recebida do hardware'
    ];
    
    insert_data("INSERT INTO digitais_log (" . implode(", ", array_keys($log_sucesso)) . ") VALUES (:" . implode(", :", array_keys($log_sucesso)) . ")", $log_sucesso);

    // 9. Finaliza
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Banco sincronizado com sucesso"]);
    exit;
?>