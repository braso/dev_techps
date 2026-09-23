<?php
include_once __DIR__ . "/funcoes_termos.php";
include_once dirname(__DIR__, 2) . "/conecta.php";

function termos_func_json(int $code, array $payload): void {
	http_response_code($code);
	header("Content-Type: application/json; charset=utf-8");
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

@set_time_limit(60);

termos_ensure_tables($conn);

if(!termos_pode_acessar("/documentos/termos/modelos_termo.php")){
	termos_func_json(403, ["ok" => false, "error" => "Sem permissão."]);
}

$raw = file_get_contents("php://input");
$dados = json_decode($raw, true);
if(!is_array($dados)){
	$dados = $_POST;
}

$empresas = $dados["empresas"] ?? [];
if(!is_array($empresas)){
	$empresas = [];
}
$empresas = array_values(array_filter(array_map("intval", $empresas)));

$cargos = $dados["cargos"] ?? [];
if(!is_array($cargos)){
	$cargos = [];
}
$cargos = array_values(array_filter(array_map("intval", $cargos)));
if(empty($cargos)){
	$cargoUnico = intval($dados["cargo"] ?? 0);
	if($cargoUnico > 0){
		$cargos = [$cargoUnico];
	}
}

$setores = $dados["setores"] ?? [];
if(!is_array($setores)){
	$setores = [];
}
$setores = array_values(array_filter(array_map("intval", $setores)));
if(empty($setores)){
	$setorUnico = intval($dados["setor"] ?? 0);
	if($setorUnico > 0){
		$setores = [$setorUnico];
	}
}

$busca = trim(strval($dados["busca"] ?? ""));
$modelo = intval($dados["modelo"] ?? 0);
$status = strval($dados["status"] ?? "ativo");

$where = ["e.enti_nb_id > 0"];
$types = "";
$vars = [];

if(!empty($empresas)){
	$in = implode(",", array_unique($empresas));
	$where[] = "e.enti_nb_empresa IN ({$in})";
}else{
	$where[] = "0";
}
if(in_array($status, ["ativo", "inativo"], true)){
	$where[] = "e.enti_tx_status = ?";
	$types .= "s";
	$vars[] = $status;
}
if(!empty($cargos)){
	$in = implode(",", array_unique($cargos));
	$where[] = "e.enti_tx_tipoOperacao IN ({$in})";
}
if(!empty($setores)){
	$in = implode(",", array_unique($setores));
	$where[] = "e.enti_setor_id IN ({$in})";
}
if($busca !== ""){
	$where[] = "(e.enti_tx_nome LIKE ? OR e.enti_tx_matricula LIKE ? OR e.enti_tx_cpf LIKE ?)";
	$types .= "sss";
	$like = "%" . $busca . "%";
	$vars[] = $like;
	$vars[] = $like;
	$vars[] = $like;
}

$res = query(
	"SELECT
		e.enti_nb_id, e.enti_tx_matricula, e.enti_tx_nome, e.enti_tx_cpf, e.enti_tx_email, e.enti_tx_status,
		em.empr_nb_id, em.empr_tx_nome AS empresa_nome, op.oper_tx_nome AS cargo_nome, g.grup_tx_nome AS setor_nome
	 FROM entidade e
	 LEFT JOIN empresa em ON em.empr_nb_id = e.enti_nb_empresa
	 LEFT JOIN operacao op ON op.oper_nb_id = e.enti_tx_tipoOperacao
	 LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
	 WHERE " . implode(" AND ", $where) . "
	 ORDER BY em.empr_tx_nome ASC, e.enti_tx_nome ASC
	 LIMIT 2000",
	$types,
	$vars
);

$funcionarios = [];
$mapa = [];
if($modelo > 0){
	$entidades = [];
	while($res && ($r = mysqli_fetch_assoc($res))){
		$entidades[] = $r;
	}
	$ids = [];
	foreach($entidades as $e){
		$ids[] = intval($e["enti_nb_id"]);
	}
	if(!empty($ids)){
		$in = implode(",", array_unique($ids));
		$resTermos = query(
			"SELECT terg_nb_entidade, terg_tx_status FROM termo_gerado
			 WHERE terg_nb_modelo = ? AND terg_nb_entidade IN ({$in}) AND terg_tx_status IN ('gerado','aguardando_assinatura','assinado')",
			"i",
			[$modelo]
		);
		while($resTermos && ($t = mysqli_fetch_assoc($resTermos))){
			$eid = intval($t["terg_nb_entidade"]);
			if(!isset($mapa[$eid])){
				$mapa[$eid] = $t["terg_tx_status"];
			}
		}
	}
}else{
	while($res && ($r = mysqli_fetch_assoc($res))){
		$entidades[] = $r;
	}
}

foreach(($entidades ?? []) as $e){
	$funcionarios[] = [
		"enti_nb_id" => intval($e["enti_nb_id"]),
		"matricula" => strval($e["enti_tx_matricula"] ?? ""),
		"nome" => strval($e["enti_tx_nome"] ?? ""),
		"cpf" => termos_formatar_cpf($e["enti_tx_cpf"] ?? ""),
		"email" => strval($e["enti_tx_email"] ?? ""),
		"cargo" => strval($e["cargo_nome"] ?? ""),
		"setor" => strval($e["setor_nome"] ?? ""),
		"empresa" => strval($e["empresa_nome"] ?? ""),
		"empresa_id" => intval($e["empr_nb_id"] ?? 0),
		"status_termo" => $mapa[intval($e["enti_nb_id"])] ?? ""
	];
}

termos_func_json(200, [
	"ok" => true,
	"total" => count($funcionarios),
	"funcionarios" => $funcionarios
]);