<?php
    /* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
    //*/

    require_once "../load_env.php";
    require_once "lib.php"; // Usa a sua biblioteca de banco de dados

    header('Content-Type: application/json; charset=utf-8');

    // 1. Recebe o MAC Address da URL
    $mac_equipamento = isset($_GET['mac']) ? trim($_GET['mac']) : '';

    if(empty($mac_equipamento)) {
        http_response_code(400); // Bad Request (Padrão do seu legado)
        echo json_encode(["status" => "error", "message" => "MAC do equipamento não informado"]);
        exit;
    }

    // 2. Captura o Token do Cabeçalho (Bearer Token)
    $headers = apache_request_headers();
    $token_recebido = '';

    if (isset($headers['Authorization'])) {
        $token_recebido = trim(str_replace('Bearer ', '', $headers['Authorization']));
    }

    if (empty($token_recebido)) {
        http_response_code(401); // Unauthorized
        echo json_encode(["status" => "error", "message" => "Acesso Negado: Token de seguranca ausente"]);
        exit;
    }

    // 3. Validação de Equipamento (Substituindo mysqli_ pelo seu get_data)
    // O get_data() já faz a proteção contra SQL Injection internamente via binding (?)
    $equipamento = get_data(
        "SELECT equip_nb_id FROM equipamentos 
         WHERE equip_tx_identificador = ? 
         AND equip_tx_token = ? 
         AND equip_tx_status = 'ativo' LIMIT 1;",
        [$mac_equipamento, $token_recebido]
    );

    if(empty($equipamento)) {
        http_response_code(403); // Forbidden
        echo json_encode(["status" => "error", "message" => "Equipamento não encontrado, inativo ou Token inválido"]);
        exit;
    }

    $id_equipamento = $equipamento[0]['equip_nb_id'];

    // 4. Busca a Tarefa Pendente
    $tarefa = get_data(
        "SELECT 
            digitais_nb_id AS id_tarefa,
            digitais_nb_id_sensor AS gaveta,
            digitais_tx_motivo_exclusao AS motivo_exclusao,
            digitais_bl_template AS template_base64
         FROM digitais 
         WHERE digitais_nb_equipamento_id = ? 
         AND digitais_tx_status = 'pendente_sync' 
         ORDER BY digitais_nb_id ASC LIMIT 1;",
        [$id_equipamento]
    );

    // 5. Resposta Mastigada para a Raspberry Pi
    if(!empty($tarefa)) {
        $dadosTarefa = $tarefa[0];
        $acao = "NADA";
        
        if (!empty($dadosTarefa['motivo_exclusao'])) {
            $acao = "APAGAR"; 
        } elseif (empty($dadosTarefa['template_base64'])) {
            $acao = "CADASTRAR_NOVO"; 
        } else {
            $acao = "GRAVAR_EXISTENTE"; 
        }

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "tem_tarefa" => true,
            "comando" => $acao,
            "dados" => [
                "id_tarefa_banco" => $dadosTarefa['id_tarefa'],
                "posicao_gaveta" => $dadosTarefa['gaveta'],
                "template_base64" => $dadosTarefa['template_base64']
            ]
        ]);
        
    } else {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "tem_tarefa" => false
        ]);
    }
    exit;
?>