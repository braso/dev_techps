<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include "../conecta.php";

echo "Tabela: assinantes\n";
$sql = "SHOW COLUMNS FROM assinantes LIKE 'status'";
$res = mysqli_query($conn, $sql);
if ($row = mysqli_fetch_assoc($res)) {
    echo "Coluna 'status' (assinantes): " . $row['Type'] . "\n";
} else {
    echo "Coluna 'status' (assinantes) não encontrada.\n";
}

echo "\n";
echo "Tabela: solicitacoes_assinatura\n";
$sql = "SHOW COLUMNS FROM solicitacoes_assinatura LIKE 'status'";
$res = mysqli_query($conn, $sql);
if ($row = mysqli_fetch_assoc($res)) {
    echo "Coluna 'status' (solicitacoes_assinatura): " . $row['Type'] . "\n";
} else {
    echo "Coluna 'status' (solicitacoes_assinatura) não encontrada.\n";
}
?>