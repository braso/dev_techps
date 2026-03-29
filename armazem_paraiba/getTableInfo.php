<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$interno = true; // utilizado em conecta.php
include_once __DIR__ . "/conecta.php";
header('Content-Type: application/json; charset=utf-8');

function ensureRfidsNbEntidadeIdColumn(mysqli $conn): void{
	$dbRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT DATABASE() AS db"));
	$db = strval($dbRow["db"] ?? "");
	if($db === ""){
		return;
	}

	$colsRes = mysqli_query(
		$conn,
		"SELECT COLUMN_NAME
		FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = '".mysqli_real_escape_string($conn, $db)."'
			AND TABLE_NAME = 'rfids'"
	);
	if(!$colsRes){
		return;
	}
	$has = [];
	while($r = mysqli_fetch_assoc($colsRes)){
		$col = strval($r["COLUMN_NAME"] ?? "");
		if($col !== ""){
			$has[$col] = true;
		}
	}

	if(!isset($has["rfids_nb_entidade_id"])){
		mysqli_query($conn, "ALTER TABLE rfids ADD COLUMN rfids_nb_entidade_id INT DEFAULT NULL");
	}
}

ensureRfidsNbEntidadeIdColumn($conn);

// Receber query e parâmetros
$queryBase = base64_decode($_POST["query"][0]) . urldecode(base64_decode($_POST["query"][1]));
$limit = min(intval(base64_decode($_POST["query"][2])), 1000); // Limite máximo de 1000
$offset = max(0, intval(base64_decode($_POST["query"][3])));

// Contar total de registros
$countQuery = "SELECT COUNT(*) AS total FROM (" . $queryBase . ") AS sub";
$resCount = mysqli_query($conn, $countQuery);

if (!$resCount) {
    echo json_encode([
        "error" => "Erro ao contar registros: " . mysqli_error($conn),
        "sql" => $countQuery
    ]);
    exit;
}

$total = mysqli_fetch_assoc($resCount)['total'];

// Ajustar offset se ultrapassar total
if ($offset > $total) {
    $offset = max(0, $total - $limit);
}

// Adicionar LIMIT e OFFSET
$query = $queryBase . " LIMIT {$limit} OFFSET {$offset}";

$res = mysqli_query($conn, $query);

if (!$res) {
    echo json_encode([
        "error" => "Erro na consulta: " . mysqli_error($conn),
        "sql" => $query
    ]);
    exit;
}

// Montar tabela
$tabela = [
    "header" => [],
    "rows" => [],
    "total" => $total
];

$queryResult = mysqli_fetch_all($res, MYSQLI_ASSOC);

if (!empty($queryResult)) {

    $tabela["header"] = array_keys($queryResult[0]);

    foreach ($queryResult as $row) {
        $tabelaRow = [];
        foreach ($row as $key => $data) {
            // Aqui você pode adicionar lógica para converter valores em HTML, se quiser
            // Exemplo: colorir importância
            if ($key === 'habi_tx_importancia') {
                switch (strtolower($data)) {
                    case 'alta':
                        $data = '<span style="color:#d9534f"><i class="fa fa-circle"></i> Alta</span>';
                        break;
                    case 'media':
                        $data = '<span style="color:#f0ad4e"><i class="fa fa-circle"></i> Média</span>';
                        break;
                    case 'baixa':
                        $data = '<span style="color:#5cb85c"><i class="fa fa-circle"></i> Baixa</span>';
                        break;
                }
            }

            $tabelaRow[$key] = $data;
        }

        $tabela["rows"][] = $tabelaRow;
    }
}

// Retornar JSON
echo json_encode($tabela, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
