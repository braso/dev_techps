<?php
include_once __DIR__."/../armazem_paraiba/conecta.php";

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
    $user_id = $_SESSION['user_nb_id'];
    $grid_name = $_POST['grid_name'];
    $columns = $_POST['columns']; // JSON string

    if(empty($user_id) || empty($grid_name) || empty($columns)){
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    // Use Prepared Statement
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
