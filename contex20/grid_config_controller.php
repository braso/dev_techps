<?php
$interno = true;

// Descobre o diretório do cliente (tenant) com base na URL de origem
$baseDir = dirname(__DIR__); // .../gestaodeponto
$tenantDir = null;

if (!empty($_SERVER['HTTP_REFERER'])) {
    $refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    $parts = explode('/', trim($refPath, '/')); // ex: 'gestaodeponto/braso/cadastro_funcionario.php'
    // Índice 0 deve ser 'gestaodeponto', índice 1 o tenant (braso, comav, etc.)
    if (isset($parts[1]) && $parts[1] !== 'contex20') {
        $tenantDir = $parts[1];
    }
}

if (empty($tenantDir)) {
    http_response_code(400);
    echo json_encode(['error' => 'Cliente não identificado']);
    exit;
}

include_once $baseDir . "/{$tenantDir}/conecta.php";

// Ensure table exists
$checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'grid_user_config'");
if(mysqli_num_rows($checkTable) == 0){
    $sql = "CREATE TABLE grid_user_config (
        guc_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        guc_nb_user INT NOT NULL,
        guc_tx_grid VARCHAR(100) NOT NULL,
        guc_tx_columns JSON NOT NULL,
        guc_tx_dataAtualiza DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_grid (guc_nb_user, guc_tx_grid)
    )";
    mysqli_query($conn, $sql);
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $user_id = $_SESSION['user_nb_id'] ?? null;
    $grid_name = $_POST['grid_name'] ?? '';
    $columns = $_POST['columns'] ?? ''; // JSON string

    if(empty($user_id) && !empty($_SESSION['user_tx_login'] ?? '')){
        $stmtUser = $conn->prepare("SELECT user_nb_id FROM user WHERE user_tx_login = ? AND user_tx_status = 'ativo' LIMIT 1");
        $login = $_SESSION['user_tx_login'];
        $stmtUser->bind_param("s", $login);
        if($stmtUser->execute()){
            $resUser = $stmtUser->get_result();
            if($resUser && $rowUser = $resUser->fetch_assoc()){
                $user_id = (int)$rowUser['user_nb_id'];
                $_SESSION['user_nb_id'] = $user_id;
            }
        }
    }

    if(empty($user_id)){
        $user_id = 0;
    }

    // ============================================================
    // PADRÕES DE GRID (3 por usuário, globais em todos os grids)
    // ============================================================
    $gucAcao = $_POST['guc_acao'] ?? ($_GET['guc_acao'] ?? '');

    if($gucAcao === 'listar_padroes'){
        grid_ensure_padroes((int)$user_id);
        echo json_encode(['success' => true, 'padroes' => grid_padroes_usuario((int)$user_id)]);
        exit;
    }

    if($gucAcao === 'salvar_padrao'){
        $ordem = (int)($_POST['ordem'] ?? 1);
        if($ordem < 1 || $ordem > 3 || $grid_name === '' || $columns === ''){
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }
        grid_ensure_padroes((int)$user_id);
        $padrao = mysqli_fetch_assoc(query("SELECT gup_nb_id, gup_tx_configs FROM grid_user_padrao WHERE gup_nb_user = ? AND gup_nb_ordem = ?", "ii", [(int)$user_id, $ordem]));
        if(empty($padrao)){
            http_response_code(400);
            echo json_encode(['error' => 'Padrão não encontrado']);
            exit;
        }
        $configs = json_decode(strval($padrao["gup_tx_configs"] ?? "{}"), true);
        if(!is_array($configs)) $configs = [];
        $configs[$grid_name] = json_decode($columns, true);
        query("UPDATE grid_user_padrao SET gup_tx_configs = ? WHERE gup_nb_id = ?", "si", [json_encode($configs), $padrao["gup_nb_id"]]);

        // Mantém a config ativa sincronizada (compatibilidade)
        $stmt = $conn->prepare("INSERT INTO grid_user_config (guc_nb_user, guc_tx_grid, guc_tx_columns) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE guc_tx_columns = VALUES(guc_tx_columns)");
        $stmt->bind_param("iss", $user_id, $grid_name, $columns);
        $stmt->execute();

        echo json_encode(['success' => true]);
        exit;
    }

    if($gucAcao === 'renomear_padrao'){
        $ordem = (int)($_POST['ordem'] ?? 1);
        $nome = trim(strval($_POST['nome'] ?? ''));
        if($ordem < 1 || $ordem > 3 || $nome === ''){
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }
        grid_ensure_padroes((int)$user_id);
        query("UPDATE grid_user_padrao SET gup_tx_nome = ? WHERE gup_nb_user = ? AND gup_nb_ordem = ?", "sii", [$nome, (int)$user_id, $ordem]);
        echo json_encode(['success' => true]);
        exit;
    }

    if($gucAcao === 'ativar_padrao'){
        $ordem = (int)($_POST['ordem'] ?? 1);
        if($ordem < 1 || $ordem > 3){
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }
        grid_ensure_padroes((int)$user_id);
        query("UPDATE grid_user_padrao SET gup_tx_ativo = 'nao' WHERE gup_nb_user = ?", "i", [(int)$user_id]);
        query("UPDATE grid_user_padrao SET gup_tx_ativo = 'sim' WHERE gup_nb_user = ? AND gup_nb_ordem = ?", "ii", [(int)$user_id, $ordem]);

        // Sincroniza as configs do padrão ativado com a config ativa (todos os grids)
        $padrao = mysqli_fetch_assoc(query("SELECT gup_tx_configs FROM grid_user_padrao WHERE gup_nb_user = ? AND gup_nb_ordem = ?", "ii", [(int)$user_id, $ordem]));
        if(!empty($padrao)){
            $configs = json_decode(strval($padrao["gup_tx_configs"] ?? "{}"), true);
            if(is_array($configs)){
                foreach($configs as $grid => $colunas){
                    if(is_string($grid) && $grid !== ''){
                        $colJson = json_encode($colunas);
                        $stmt = $conn->prepare("INSERT INTO grid_user_config (guc_nb_user, guc_tx_grid, guc_tx_columns) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE guc_tx_columns = VALUES(guc_tx_columns)");
                        $stmt->bind_param("iss", $user_id, $grid, $colJson);
                        $stmt->execute();
                    }
                }
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if($grid_name === '' || $columns === ''){
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    // Salvar no padrão ativo (comportamento padrão ao configurar colunas)
    grid_ensure_padroes((int)$user_id);
    $padraoAtivo = grid_padrao_ativo((int)$user_id);
    if(!empty($padraoAtivo)){
        $configs = json_decode(strval($padraoAtivo["gup_tx_configs"] ?? "{}"), true);
        if(!is_array($configs)) $configs = [];
        $configs[$grid_name] = json_decode($columns, true);
        query("UPDATE grid_user_padrao SET gup_tx_configs = ? WHERE gup_nb_id = ?", "si", [json_encode($configs), $padraoAtivo["gup_nb_id"]]);
    }

    $stmt = $conn->prepare("INSERT INTO grid_user_config (guc_nb_user, guc_tx_grid, guc_tx_columns) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE guc_tx_columns = VALUES(guc_tx_columns)");
    $stmt->bind_param("iss", $user_id, $grid_name, $columns);
    
    if($stmt->execute()){
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $stmt->error]);
    }
}
?>
