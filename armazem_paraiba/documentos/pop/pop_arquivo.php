<?php
/**
 * Entrega os arquivos da Biblioteca de POPs.
 *
 * Dois modos:
 *  - token público (links enviados por e-mail) — valida pop_envio e expiração;
 *  - usuário logado com permissão no módulo POP ou no módulo de Termos.
 *
 * O arquivo nunca é exposto diretamente: tudo passa por este endpoint.
 */
$interno = true;
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include_once __DIR__ . "/funcoes_pop.php";
include_once dirname(__DIR__, 2) . "/conecta.php";

pop_ensure_schema();

$token = trim(strval($_GET["token"] ?? ""));
$origem = "";
$docId = 0;
$envio = [];

if($token !== ""){
	$envio = pop_envio_por_token($token);
	if(empty($envio)){
		http_response_code(404);
		exit("Link inválido ou expirado.");
	}
	$origem = strval($envio["pev_tx_origem"] ?? "");
	$docId = intval($envio["pev_nb_doc"] ?? 0);
}else{
	$origem = strtolower(trim(strval($_GET["origem"] ?? "")));
	$docId = intval($_GET["id"] ?? 0);
	$temPop = termos_pode_acessar("/documentos/pop/index.php");
	$temTermos = termos_pode_acessar("/documentos/termos/modelos_termo.php");
	$pode = $temPop || ($origem === "termo" && $temTermos);
	if(!$pode){
		http_response_code(403);
		exit("Acesso negado.");
	}
}

$doc = pop_obter_documento($origem, $docId);
if(empty($doc)){
	http_response_code(404);
	exit("POP não encontrado.");
}
if($token !== "" && strval($doc["status"] ?? "") !== "ativo"){
	http_response_code(404);
	exit("Este POP não está disponível.");
}
if($token !== "" && !empty($envio)){
	pop_registrar_visualizacao(intval($envio["pev_nb_id"] ?? 0));
}

$tipo = strtolower(trim(strval($_GET["tipo"] ?? "documento")));
$download = !empty($_GET["download"]);

if($tipo === "fluxograma"){
	if(empty($doc["tem_fluxograma"]) || trim(strval($doc["fluxograma_rel"] ?? "")) === ""){
		http_response_code(404);
		exit("Este POP não possui fluxograma.");
	}
	$nomeFluxo = trim(strval($doc["fluxograma_nome"] ?? ""));
	if($nomeFluxo === ""){
		$nomeFluxo = basename(strval($doc["fluxograma_rel"] ?? "fluxograma"));
	}
	pop_stream_arquivo(strval($doc["fluxograma_rel"] ?? ""), $nomeFluxo, $download);
	exit;
}

if($origem === "termo"){
	$modelo = termos_carregar_modelo($docId);
	if(empty($modelo)){
		http_response_code(404);
		exit("POP não encontrado.");
	}
	pop_render_modelo_pdf($modelo, $download);
	exit;
}

if($origem === "upload"){
	$arquivoRel = trim(strval($doc["arquivo_rel"] ?? ""));
	if($arquivoRel === ""){
		http_response_code(404);
		exit("Arquivo do POP não encontrado.");
	}
	$nomeArquivo = trim(strval($doc["arquivo_nome"] ?? ""));
	if($nomeArquivo === ""){
		$nomeArquivo = basename($arquivoRel);
	}
	pop_stream_arquivo($arquivoRel, $nomeArquivo, $download);
	exit;
}

http_response_code(404);
exit("POP não encontrado.");
