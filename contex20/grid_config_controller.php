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

    if($grid_name === '' || $columns === ''){
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    if(empty($user_id)){
        $user_id = 0;
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
