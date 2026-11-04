<?php
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

include_once __DIR__ . "/funcoes_pop.php";
include_once dirname(__DIR__, 2) . "/conecta.php";

if(!termos_pode_acessar("/documentos/pop/index.php")){
	http_response_code(403);
	echo json_encode(["results" => [], "msg" => "Acesso negado."], JSON_UNESCAPED_UNICODE);
	exit;
}

pop_ensure_schema();

$busca = trim(strval($_GET["q"] ?? $_GET["term"] ?? ""));
$like = "%" . $busca . "%";

$sql = "SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_cpf, enti_tx_email
	FROM entidade
	WHERE enti_tx_status = 'ativo'
	  AND enti_tx_email IS NOT NULL
	  AND enti_tx_email <> ''";
$types = "";
$vars = [];
if($busca !== ""){
	$sql .= " AND (enti_tx_nome LIKE ? OR enti_tx_matricula LIKE ? OR enti_tx_cpf LIKE ? OR enti_tx_email LIKE ?)";
	$types = "ssss";
	$vars = [$like, $like, $like, $like];
}
$sql .= " ORDER BY enti_tx_nome ASC LIMIT 40";

$res = query($sql, $types, $vars);
$results = [];
while($res instanceof mysqli_result && ($row = mysqli_fetch_assoc($res))){
	$id = intval($row["enti_nb_id"] ?? 0);
	if($id <= 0){
		continue;
	}
	$nome = trim(strval($row["enti_tx_nome"] ?? ""));
	$matricula = trim(strval($row["enti_tx_matricula"] ?? ""));
	$cpf = trim(strval($row["enti_tx_cpf"] ?? ""));
	$email = trim(strval($row["enti_tx_email"] ?? ""));

	$texto = $nome !== "" ? $nome : ("Colaborador #" . $id);
	$detalhes = [];
	if($matricula !== ""){ $detalhes[] = "Mat. " . $matricula; }
	if($cpf !== ""){ $detalhes[] = "CPF " . $cpf; }
	if($email !== ""){ $detalhes[] = $email; }
	if(!empty($detalhes)){
		$texto .= " — " . implode(" · ", $detalhes);
	}

	$results[] = [
		"id" => $id,
		"text" => $texto,
		"nome" => $nome,
		"email" => $email
	];
}

echo json_encode(["results" => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
