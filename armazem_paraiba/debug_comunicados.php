<?php
    include "conecta.php";
    
    echo "<h1>Debug Comunicados</h1>";
    
    // Check Session
    echo "<h2>Session</h2>";
    echo "User Level: " . ($_SESSION["user_tx_nivel"] ?? "NOT SET") . "<br>";
    echo "User ID: " . ($_SESSION["user_nb_id"] ?? "NOT SET") . "<br>";
    
    // Check Table
    echo "<h2>Table: comunicado_interno</h2>";
    $result = mysqli_query($conn, "SELECT * FROM comunicado_interno");
    if($result){
        echo "<table border='1'><tr>";
        // Header
        while ($field = mysqli_fetch_field($result)) {
            echo "<th>{$field->name}</th>";
        }
        echo "</tr>";
        
        // Data
        while($row = mysqli_fetch_assoc($result)){
            echo "<tr>";
            foreach($row as $key => $value){
                echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "Error querying table: " . mysqli_error($conn);
    }
    
    // Test the logic used in menu.php
    echo "<h2>Test Logic</h2>";
    $nivel = trim($_SESSION["user_tx_nivel"] ?? '');
    
    // Logic 1: Exact Match + FIND_IN_SET (New Logic)
    $sql1 = "SELECT coin_tx_titulo, coin_tx_texto FROM comunicado_interno WHERE coin_tx_status = 'ativo' AND (FIND_IN_SET('todos', coin_tx_dest_perfis) > 0 OR FIND_IN_SET('$nivel', coin_tx_dest_perfis) > 0) ORDER BY coin_nb_id DESC LIMIT 1";
    echo "Query 1 (New Logic): $sql1<br>";
    $result1 = mysqli_query($conn, $sql1);
    if($result1 && mysqli_num_rows($result1) > 0){
        $row = mysqli_fetch_assoc($result1);
        echo "Found: " . $row['coin_tx_titulo'] . "<br>";
    } else {
        echo "Nothing found with Query 1.<br>";
    }

    echo "<hr>";

    // Logic 2: Using REPLACE for spaces
    $sql2 = "SELECT coin_tx_titulo, coin_tx_texto FROM comunicado_interno WHERE coin_tx_status = 'ativo' AND (FIND_IN_SET('todos', coin_tx_dest_perfis) > 0 OR FIND_IN_SET('$nivel', REPLACE(coin_tx_dest_perfis, ' ', '')) > 0) ORDER BY coin_nb_id DESC LIMIT 1";
    echo "Query 2 (With REPLACE): $sql2<br>";
    $result2 = mysqli_query($conn, $sql2);
    if($result2 && mysqli_num_rows($result2) > 0){
        $row = mysqli_fetch_assoc($result2);
        echo "Found: " . $row['coin_tx_titulo'] . "<br>";
    } else {
        echo "Nothing found with Query 2.<br>";
    }
?>