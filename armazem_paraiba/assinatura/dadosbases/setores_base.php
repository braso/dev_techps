<?php
$interno = true;
include_once "../../conecta.php";

header('Content-Type: application/json; charset=utf-8');

$statusSetor = $_GET["statusSetor"] ?? $_POST["statusSetor"] ?? "ativo";
$statusFuncionario = $_GET["statusFuncionario"] ?? $_POST["statusFuncionario"] ?? "ativo";
$somenteComEmail = ($_GET["somenteComEmail"] ?? $_POST["somenteComEmail"] ?? "nao") === "sim";
$empresaId = intval($_GET["empresaId"] ?? $_POST["empresaId"] ?? 0);

$whereSetor = [];
$typesSetor = "";
$varsSetor = [];

if(in_array($statusSetor, ["ativo", "inativo"], true)){
	$whereSetor[] = "g.grup_tx_status = ?";
	$typesSetor .= "s";
	$varsSetor[] = $statusSetor;
}

$whereSetorSql = empty($whereSetor) ? "1" : implode(" AND ", $whereSetor);

$rowsSetores = mysqli_fetch_all(query(
	"SELECT
		g.grup_nb_id AS setor_id,
		g.grup_tx_nome AS setor_nome,
		g.grup_tx_status AS setor_status,
		s.sbgr_nb_id AS subsetor_id,
		s.sbgr_tx_nome AS subsetor_nome,
		s.sbgr_tx_status AS subsetor_status
	FROM grupos_documentos g
	LEFT JOIN sbgrupos_documentos s
		ON s.sbgr_nb_idgrup = g.grup_nb_id
	WHERE {$whereSetorSql}
	ORDER BY g.grup_tx_nome ASC, s.sbgr_tx_nome ASC",
	$typesSetor,
	$varsSetor
), MYSQLI_ASSOC);

$whereFunc = [];
$typesFunc = "";
$varsFunc = [];

if(in_array($statusFuncionario, ["ativo", "inativo"], true)){
	$whereFunc[] = "e.enti_tx_status = ?";
	$typesFunc .= "s";
	$varsFunc[] = $statusFuncionario;
}

if($somenteComEmail){
	$whereFunc[] = "e.enti_tx_email IS NOT NULL AND e.enti_tx_email <> ''";
}

if($empresaId > 0){
	$whereFunc[] = "e.enti_nb_empresa = ?";
	$typesFunc .= "i";
	$varsFunc[] = $empresaId;
}

$whereFunc[] = "e.enti_setor_id IS NOT NULL AND e.enti_setor_id <> 0";

$whereFuncSql = empty($whereFunc) ? "1" : implode(" AND ", $whereFunc);

$rowsFuncionarios = mysqli_fetch_all(query(
	"SELECT
		e.enti_nb_id,
		e.enti_tx_nome,
		e.enti_tx_email,
		e.enti_tx_cpf,
		e.enti_setor_id,
		g.grup_tx_nome AS setor_nome,
		e.enti_subSetor_id,
		s.sbgr_tx_nome AS subsetor_nome
	FROM entidade e
	LEFT JOIN grupos_documentos g
		ON g.grup_nb_id = e.enti_setor_id
	LEFT JOIN sbgrupos_documentos s
		ON s.sbgr_nb_id = e.enti_subSetor_id
	WHERE {$whereFuncSql}
	ORDER BY g.grup_tx_nome ASC, s.sbgr_tx_nome ASC, e.enti_tx_nome ASC",
	$typesFunc,
	$varsFunc
), MYSQLI_ASSOC);

echo json_encode([
	"ok" => true,
	"setores" => $rowsSetores,
	"funcionarios" => $rowsFuncionarios
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

