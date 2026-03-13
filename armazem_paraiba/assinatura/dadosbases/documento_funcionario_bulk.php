<?php
$interno = true;
include_once "../../conecta.php";

header('Content-Type: application/json; charset=utf-8');

if(($_SERVER["REQUEST_METHOD"] ?? "") !== "POST"){
	echo json_encode(["ok" => false, "error" => "Método inválido."], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

$raw = file_get_contents("php://input");
$payload = json_decode($raw, true);
if(!is_array($payload)){
	echo json_encode(["ok" => false, "error" => "JSON inválido."], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

$tipoId = intval($payload["tipo_documento"] ?? 0);
$nome = trim(strval($payload["nome"] ?? ""));
$descricao = trim(strval($payload["descricao"] ?? ""));
$visivel = ($payload["visivel"] ?? "nao");
$visivel = in_array($visivel, ["sim", "nao"], true) ? $visivel : "nao";
$dataVencimento = $payload["data_vencimento"] ?? null;
$sbgrupo = isset($payload["sbgrupo"]) ? intval($payload["sbgrupo"]) : 0;
$usuarioCadastro = intval($payload["usuario_cadastro"] ?? ($_SESSION["user_nb_id"] ?? 0));

$items = $payload["items"] ?? null;
if($tipoId <= 0 || $usuarioCadastro <= 0 || empty($nome) || !is_array($items) || empty($items)){
	echo json_encode(["ok" => false, "error" => "Payload incompleto."], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

foreach($items as $item){
	if(!is_array($item)){
		echo json_encode(["ok" => false, "error" => "Item inválido."], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}
	$entidadeId = intval($item["entidade_id"] ?? 0);
	$caminho = trim(strval($item["caminho"] ?? ""));
	if($entidadeId <= 0 || empty($caminho)){
		echo json_encode(["ok" => false, "error" => "Item sem entidade_id/caminho."], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}
}

$tipo = mysqli_fetch_assoc(query(
	"SELECT tipo_nb_id, tipo_tx_status FROM tipos_documentos WHERE tipo_nb_id = ? LIMIT 1",
	"i",
	[$tipoId]
));
if(empty($tipo)){
	echo json_encode(["ok" => false, "error" => "Tipo de documento não encontrado."], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

query("START TRANSACTION;");

$insertSql =
	"INSERT INTO documento_funcionario
		(docu_nb_entidade, docu_tx_nome, docu_tx_descricao, docu_tx_dataCadastro, docu_tx_dataVencimento, docu_tx_tipo, docu_nb_sbgrupo, docu_tx_usuarioCadastro, docu_tx_assinado, docu_tx_visivel, docu_tx_caminho)
	VALUES
		(?, ?, ?, ?, ?, ?, ?, ?, 'nao', ?, ?)";

$agora = date("Y-m-d H:i:s");
$inserted = 0;

foreach($items as $item){
	$entidadeId = intval($item["entidade_id"]);
	$caminho = trim(strval($item["caminho"]));

	$dataVenc = $dataVencimento;
	if(isset($item["data_vencimento"])){
		$dataVenc = $item["data_vencimento"];
	}

	$nomeItem = $nome;
	if(isset($item["nome"]) && trim(strval($item["nome"])) !== ""){
		$nomeItem = trim(strval($item["nome"]));
	}

	$descricaoItem = $descricao;
	if(isset($item["descricao"]) && trim(strval($item["descricao"])) !== ""){
		$descricaoItem = trim(strval($item["descricao"]));
	}

	$sbgrupoItem = $sbgrupo;
	if(array_key_exists("sbgrupo", $item)){
		$sbgrupoItem = intval($item["sbgrupo"] ?? 0);
	}

	$res = query(
		$insertSql,
		"issssiiiss",
		[
			$entidadeId,
			$nomeItem,
			$descricaoItem,
			$agora,
			$dataVenc,
			$tipoId,
			$sbgrupoItem,
			$usuarioCadastro,
			$visivel,
			$caminho
		]
	);

	if(!$res){
		query("ROLLBACK;");
		echo json_encode(["ok" => false, "error" => "Falha ao inserir documento."], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

	$inserted++;
}

query("COMMIT;");

echo json_encode([
	"ok" => true,
	"inserted" => $inserted
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
