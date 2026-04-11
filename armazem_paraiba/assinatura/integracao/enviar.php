<?php

include __DIR__ . "/../../conecta.php";
require_once __DIR__ . "/assinatura_integracao.php";

function assinatura_integracao_wantsJson(): bool {
	$accept = strtolower(strval($_SERVER["HTTP_ACCEPT"] ?? ""));
	if(strpos($accept, "application/json") !== false){
		return true;
	}
	$fmt = strtolower(trim(strval($_REQUEST["format"] ?? "")));
	return in_array($fmt, ["json", "api"], true);
}

function assinatura_integracao_respJson(int $code, array $payload): void {
	http_response_code($code);
	header("Content-Type: application/json; charset=utf-8");
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

function assinatura_integracao_redirect(string $url, array $params): void {
	$u = trim($url);
	if($u === ""){
		$u = strval($_SERVER["HTTP_REFERER"] ?? "");
	}
	if($u === ""){
		$u = "../consultar.php";
	}

	$sep = (strpos($u, "?") === false) ? "?" : "&";
	$q = http_build_query($params);
	header("Location: " . $u . ($q !== "" ? $sep . $q : ""));
	exit;
}

if($_SERVER["REQUEST_METHOD"] !== "POST"){
	if(assinatura_integracao_wantsJson()){
		assinatura_integracao_respJson(405, ["ok" => false, "error" => "Método não permitido. Use POST."]);
	}
	assinatura_integracao_redirect("", ["assinatura_integracao" => "error", "msg" => "Método não permitido. Use POST."]);
}

$entiNbId = intval($_POST["enti_nb_id"] ?? 0);
$modoEnvio = strtolower(trim(strval($_POST["modo_envio"] ?? "avulso")));
$tipoDocumentoId = intval($_POST["tipo_documento_id"] ?? 0);
$validarIcp = strtolower(trim(strval($_POST["validar_icp"] ?? "nao"))) === "sim" ? "sim" : "nao";
$funcao = trim(strval($_POST["funcao"] ?? "Funcionário"));
$nomeArquivoOriginal = trim(strval($_POST["nome_arquivo_original"] ?? ""));
$grupoEnvio = trim(strval($_POST["grupo_envio"] ?? ""));
$caminho = trim(strval($_POST["caminho"] ?? ""));
$retorno = trim(strval($_POST["retorno"] ?? ""));

$tempToDelete = "";
if($caminho === "" && isset($_FILES["arquivo"]) && ($_FILES["arquivo"]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK){
	$tmp = strval($_FILES["arquivo"]["tmp_name"] ?? "");
	$orig = strval($_FILES["arquivo"]["name"] ?? "");
	if($tmp !== "" && is_uploaded_file($tmp)){
		$nome = assinatura_integracao_sanitizarNomeArquivo($nomeArquivoOriginal !== "" ? $nomeArquivoOriginal : $orig);
		$assinaturaDir = assinatura_integracao_baseAssinatura();
		$dirTmp = $assinaturaDir . "/uploads/tmp/";
		if(!is_dir($dirTmp)){
			@mkdir($dirTmp, 0777, true);
		}
		$dest = rtrim(str_replace("\\", "/", $dirTmp), "/") . "/" . date("YmdHis") . "_" . bin2hex(random_bytes(4)) . "_" . $nome;
		if(@move_uploaded_file($tmp, $dest)){
			$caminho = $dest;
			$tempToDelete = $dest;
			if($nomeArquivoOriginal === ""){
				$nomeArquivoOriginal = $nome;
			}
		}
	}
}

if($entiNbId <= 0 || $caminho === ""){
	$msg = $entiNbId <= 0 ? "Selecione o funcionário." : "Informe o caminho do PDF (ou envie o arquivo).";
	if($tempToDelete !== ""){
		@unlink($tempToDelete);
	}
	if(assinatura_integracao_wantsJson()){
		assinatura_integracao_respJson(400, ["ok" => false, "error" => $msg]);
	}
	assinatura_integracao_redirect($retorno, ["assinatura_integracao" => "error", "msg" => $msg]);
}

$opts = [
	"modo_envio" => $modoEnvio,
	"tipo_documento_id" => $tipoDocumentoId,
	"validar_icp" => $validarIcp,
	"funcao" => $funcao,
	"grupo_envio" => $grupoEnvio,
	"nome_arquivo_original" => $nomeArquivoOriginal,
	"salvar_documento_funcionario" => "sim",
	"apagar_origem" => ($tempToDelete !== "")
];

$res = assinatura_integracao_enviarDocumentoParaAssinatura($conn, $entiNbId, $caminho, $opts);

if(assinatura_integracao_wantsJson()){
	$code = !empty($res["ok"]) ? 200 : 400;
	assinatura_integracao_respJson($code, $res);
}

if(!empty($res["ok"])){
	assinatura_integracao_redirect($retorno, [
		"assinatura_integracao" => "ok",
		"id_solicitacao" => strval($res["id_solicitacao"] ?? ""),
		"id_documento" => strval($res["id_documento"] ?? "")
	]);
}

assinatura_integracao_redirect($retorno, ["assinatura_integracao" => "error", "msg" => strval($res["error"] ?? "Falha ao enviar para assinatura.")]);

