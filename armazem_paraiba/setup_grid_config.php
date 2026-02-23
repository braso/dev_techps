<?php
$interno = true;
include "conecta.php";

$table = "grid_user_config";
$check = query("SHOW TABLES LIKE '$table'");
if(mysqli_num_rows($check) == 0){
    echo "Table does not exist. Creating...\n";
    $sql = "CREATE TABLE $table (
        guc_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        guc_nb_user INT NOT NULL,
        guc_tx_grid VARCHAR(100) NOT NULL,
        guc_tx_columns JSON NOT NULL,
        guc_tx_dataAtualiza DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_grid (guc_nb_user, guc_tx_grid)
    )";
    query($sql);
    echo "Table created.\n";
} else {
    echo "Table exists.\n";
}
?>