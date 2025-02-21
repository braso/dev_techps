<?php

$connProd = new PDO(
    "mysql:host=localhost;dbname=techpsjornada_opafrutas", "techpsjornada_opafrutas", "BpV,M%gSv{^*nyv!aA",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$connDev = new PDO(
    "mysql:host=localhost;dbname=techpsjornada_opafrutas_dev", "techpsjornada_opafrutas_dev", "^,B)g0[~r.0hy!yCr,", 
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Excluir os registros do Banco 2 para o mês atual e mês passado
$connDev->exec("DELETE FROM ponto WHERE DATE_FORMAT(pont_tx_data, '%Y-%m') >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m');");

// Buscar dados do Banco 1 apenas para os dois últimos meses
$query = $connProd->prepare(
    "SELECT * FROM ponto WHERE DATE_FORMAT(pont_tx_data, '%Y-%m') >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m');"
);
$query->execute();
$dados = $query->fetchAll(PDO::FETCH_ASSOC);

if (empty($dados)) {
    echo "Nenhum dado novo para sincronizar.\n <br>";
    exit;
}
// Definir as colunas para inserção (remover 'pont_nb_id' que é auto incrementada)
$colunas = array_keys($dados[0]);
$colunas = array_diff($colunas, ['pont_nb_id']); // Remover a coluna pont_nb_id
$colunasString = implode(", ", $colunas);
$placeholders = implode(", ", array_fill(0, count($colunas), '?'));

// Preparar a query de inserção
$stmt = $connDev->prepare("INSERT INTO ponto ($colunasString) VALUES ($placeholders)");

// Inserir os dados no Banco 2
foreach ($dados as &$linha) {
    unset($linha['pont_nb_id']); // Remover o campo pont_nb_id

    // Executa a inserção
    if (!$stmt->execute(array_values($linha))) {
        echo "Erro ao inserir dados: ".implode(", ", $stmt->errorInfo())."\n";
    }
}

echo "Sincronização concluída! Registros atualizados após exclusão.\n <br>";
?>
