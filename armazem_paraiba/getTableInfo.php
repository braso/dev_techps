<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Garante que qualquer erro fatal retorna JSON, não HTML/vazio
register_shutdown_function(function(){
    $err = error_get_last();
    if($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])){
        if(!headers_sent()){
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
        }
        echo json_encode([
            "error" => "Erro interno PHP: " . $err['message'] . " em " . $err['file'] . ":" . $err['line']
        ], JSON_UNESCAPED_UNICODE);
    }
});

set_exception_handler(function(Throwable $e){
    if(!headers_sent()){
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
    }
    echo json_encode([
        "error" => "Exceção: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

$interno = true; // utilizado em conecta.php
include_once __DIR__ . "/conecta.php";
header('Content-Type: application/json; charset=utf-8');

// Garante que a tabela poi_acao_esperada existe para a grid de POIs
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS poi_acao_esperada (
	paes_nb_id INT AUTO_INCREMENT PRIMARY KEY,
	paes_nb_poi INT NOT NULL,
	paes_tx_codigo VARCHAR(50) NOT NULL,
	UNIQUE KEY uk_poi_acao (paes_nb_poi, paes_tx_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
	if(!isset($has["rfids_nb_user_id"])){
		mysqli_query($conn, "ALTER TABLE rfids ADD COLUMN rfids_nb_user_id INT(11) DEFAULT NULL");
	}
}

ensureRfidsNbEntidadeIdColumn($conn);

// Receber query e parâmetros
if(empty($_POST["query"]) || !is_array($_POST["query"]) || count($_POST["query"]) < 4){
    echo json_encode(["error" => "Parâmetros inválidos.", "rows" => [], "total" => 0]);
    exit;
}
$queryBase = base64_decode($_POST["query"][0]) . urldecode(base64_decode($_POST["query"][1]));
$limit = min(intval(base64_decode($_POST["query"][2])), 1000); // Limite máximo de 1000
$offset = max(0, intval(base64_decode($_POST["query"][3])));

// Contar total de registros
$countQuery = "SELECT COUNT(*) AS total FROM (" . $queryBase . ") AS sub";
$resCount = null;
try{
	$resCount = mysqli_query($conn, $countQuery);
}catch(Throwable $e){
	echo json_encode([
		"error" => "Erro ao contar registros: " . $e->getMessage(),
		"sql" => $countQuery
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

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

$res = null;
try{
	$res = mysqli_query($conn, $query);
}catch(Throwable $e){
	echo json_encode([
		"error" => "Erro na consulta: " . $e->getMessage(),
		"sql" => $query
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

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

            if (($key === 'ss_e_db_valor_unitario' || $key === 'ss_e_db_valor_total') && $data !== null && $data !== '') {
                $data = 'R$ ' . number_format((float)$data, 2, ',', '.');
            }

            if ($key === 'ss_e_tx_tipo') {
                switch ($data) {
                    case 'entrada':
                        $data = '<span class="label label-success">Entrada</span>';
                        break;
                    case 'saida':
                        $data = '<span class="label label-danger">Saída</span>';
                        break;
                }
            }

            if ($key === 'ss_e_tx_foto' || $key === 'ss_e_tx_foto_epi' || $key === 'poi_tx_imagem') {
                if ($data !== null && $data !== '') {
                    $resolvedSrc = $_ENV["APP_PATH"] . '/' . htmlspecialchars($data);
                    $data = '<img src="' . $resolvedSrc . '" onclick="verImagemMaior(\'' . $resolvedSrc . '\')" style="max-height: 40px; max-width: 40px; border-radius: 4px; border: 1px solid #ccc; cursor: pointer; object-fit: cover;" title="Clique para ampliar">';
                } else {
                    $data = '<span class="text-muted">-</span>';
                }
            }

            if ($key === 'ss_e_tx_status') {
                $isEntrega = (strpos($queryBase, 'ss_epi_entrega') !== false);
                if ($isEntrega) {
                    switch ($data) {
                        case 'ativo':
                            $data = '<span class="label label-success">Entregue/Ativo</span>';
                            break;
                        case 'substituido':
                            $data = '<span class="label label-warning">Substituído</span>';
                            break;
                        case 'devolvido':
                            $data = '<span class="label label-info">Devolvido</span>';
                            break;
                        case 'perdido':
                            $data = '<span class="label label-danger">Perdido/Extraviado</span>';
                            break;
                        case 'nao_entregue':
                            $data = '<span class="label label-default">Não Entregue</span>';
                            break;
                    }
                } else {
                    switch ($data) {
                        case 'ativo':
                            $data = '<span class="label label-success">Ativo</span>';
                            break;
                        case 'inativo':
                            $data = '<span class="label label-danger">Inativo</span>';
                            break;
                    }
                }
            }

            $tabelaRow[$key] = $data;
        }

        $tabela["rows"][] = $tabelaRow;
    }
}

// Retornar JSON
echo json_encode($tabela, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
