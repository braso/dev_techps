<?php
/* ============================================================
   Visualização de documento notificado (sem coleta de assinatura).

   Aberta pelo link enviado na notificação (app/e-mail). Mostra o
   PDF do termo e registra dados de auditoria (data, hora, IP,
   user-agent, idioma, plataforma e geolocalização quando permitida)
   para comprovar que o funcionário visualizou o documento.

   Nada aqui altera o módulo de assinatura: a solicitação e o
   assinante são criados por ele (modo_envio = termo_notificacao) e
   o PDF é servido pelo próprio assinar_via_link.php?arquivo=1.
   ============================================================ */

$interno = true;
include_once __DIR__ . "/funcoes_termos.php";
include_once dirname(__DIR__, 2) . "/conecta.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

termos_ensure_tables($conn);

function termos_vis_h($v): string {
	return htmlspecialchars(strval($v), ENT_QUOTES, "UTF-8");
}

function termos_vis_plataforma(string $ua): string {
	$uaLower = strtolower($ua);
	if(strpos($uaLower, "android") !== false){
		return "Android";
	}
	if(preg_match('/iphone|ipad|ipod/', $uaLower)){
		return strpos($uaLower, "ipad") !== false ? "iPad" : "iPhone";
	}
	if(strpos($uaLower, "windows") !== false){
		return "Windows";
	}
	if(strpos($uaLower, "mac os") !== false){
		return "Mac";
	}
	if(strpos($uaLower, "linux") !== false){
		return "Linux";
	}
	return "Outro";
}

function termos_vis_dados_acesso(): array {
	$ua = substr(strval($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 2000);
	return [
		"ip" => termos_ip_cliente(),
		"ip_forwarded" => substr(strval($_SERVER["HTTP_X_FORWARDED_FOR"] ?? ""), 0, 255),
		"user_agent" => $ua,
		"accept_language" => substr(strval($_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? ""), 0, 120),
		"host" => substr(strval($_SERVER["HTTP_HOST"] ?? ""), 0, 255),
		"referer" => substr(strval($_SERVER["HTTP_REFERER"] ?? ""), 0, 500),
		"plataforma" => termos_vis_plataforma($ua)
	];
}

function termos_vis_hash(int $termoId, int $entiId, string $token, string $evento, array $d): string {
	return hash("sha256", implode("|", [
		$termoId,
		$entiId,
		$token,
		$evento,
		date("Y-m-d H:i:s"),
		strval($d["ip"] ?? ""),
		strval($d["user_agent"] ?? ""),
		bin2hex(random_bytes(8))
	]));
}

function termos_vis_registrar_evento(int $termoId, int $modeloId, int $entiId, int $solId, int $assId, string $evento, string $token, array $extra = []): int {
	global $conn;
	$d = termos_vis_dados_acesso();
	$hash = termos_vis_hash($termoId, $entiId, $token, $evento, $d);
	$user = intval($_SESSION["user_nb_id"] ?? 0);
	$login = substr(trim(strval($_SESSION["user_tx_login"] ?? "")), 0, 255);
	$lat = trim(strval($extra["latitude"] ?? ""));
	$lng = trim(strval($extra["longitude"] ?? ""));
	$prec = trim(strval($extra["precisao"] ?? ""));

	$sql = "INSERT INTO termo_visualizacao
		(tevi_nb_termo, tevi_nb_modelo, tevi_nb_entidade, tevi_nb_solicitacao, tevi_nb_assinante, tevi_tx_evento,
		 tevi_tx_ip, tevi_tx_ip_forwarded, tevi_tx_user_agent, tevi_tx_accept_language, tevi_tx_host, tevi_tx_referer,
		 tevi_tx_plataforma, tevi_nb_latitude, tevi_nb_longitude, tevi_nb_geo_precisao, tevi_tx_hash, tevi_nb_user, tevi_tx_login)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
	$stmt = mysqli_prepare($conn, $sql);
	if(!$stmt){
		return 0;
	}
	mysqli_stmt_bind_param(
		$stmt,
		"iiiii" . "ssssssssssss" . "is",
		$termoId,
		$modeloId,
		$entiId,
		$solId,
		$assId,
		$evento,
		$d["ip"],
		$d["ip_forwarded"],
		$d["user_agent"],
		$d["accept_language"],
		$d["host"],
		$d["referer"],
		$d["plataforma"],
		$lat,
		$lng,
		$prec,
		$hash,
		$user,
		$login
	);
	if(!mysqli_stmt_execute($stmt)){
		mysqli_stmt_close($stmt);
		return 0;
	}
	$id = intval(mysqli_stmt_insert_id($stmt));
	mysqli_stmt_close($stmt);
	return $id;
}

function termos_vis_pagina(string $conteudo, string $titulo = "Visualização de Documento"): void {
	echo "<!DOCTYPE html><html lang='pt-br'><head><meta charset='UTF-8'>";
	echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
	echo "<meta name='robots' content='noindex, nofollow'>";
	echo "<title>" . termos_vis_h($titulo) . "</title>";
	echo "<script src='https://cdn.tailwindcss.com'></script>";
	echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>";
	echo "</head><body class='bg-gray-100 min-h-screen font-sans'>";
	echo "<div class='max-w-5xl mx-auto px-4 py-6'>";
	echo "<div class='flex items-center justify-between mb-4'>";
	echo "<div class='bg-white p-2 rounded-lg shadow-sm border border-gray-200'><img src='../../assinatura/assets/logo.png' alt='TechPS' class='h-8' onerror=\"this.style.display='none'\"></div>";
	echo "<div class='text-xs text-gray-500'>Sistema de Documentos TechPS</div>";
	echo "</div>";
	echo $conteudo;
	echo "</div></body></html>";
	exit;
}

function termos_vis_botao_voltar(string $rotulo = "Voltar", string $classes = "inline-flex items-center justify-center px-5 py-3 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold"): string {
	return "<button type='button' onclick=\"if(window.history.length > 1){ history.back(); } else { window.close(); }\" class='" . $classes . "'>"
		. "<i class='fas fa-arrow-left mr-2'></i>" . termos_vis_h($rotulo)
		. "</button>";
}

function termos_vis_erro(string $titulo, string $mensagem): void {
	$html = "<div class='bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden'>
		<div class='bg-red-50 px-6 py-5 flex items-start gap-4'>
			<div class='h-12 w-12 rounded-xl flex items-center justify-center flex-shrink-0 bg-red-100 text-red-700'><i class='fas fa-triangle-exclamation text-xl'></i></div>
			<div><div class='text-lg font-bold text-gray-900'>" . termos_vis_h($titulo) . "</div>
			<div class='mt-1 text-sm text-gray-700 leading-relaxed'>" . termos_vis_h($mensagem) . "</div></div>
		</div>
		<div class='px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3'>
			<div class='text-xs text-gray-500'>Se você recebeu este link por e-mail, solicite um novo envio ao responsável.</div>
			" . termos_vis_botao_voltar() . "
		</div>
	</div>";
	termos_vis_pagina($html, $titulo);
}

$tokenRaw = $_REQUEST["token"] ?? "";
$token = is_string($tokenRaw) ? trim($tokenRaw) : "";
if($token === "" || !preg_match('/^[A-Za-z0-9]{16,128}$/', $token)){
	termos_vis_erro("Link inválido", "Token não informado. Use o link recebido na notificação.");
}

$resSol = query(
	"SELECT a.id AS assinante_id, a.enti_nb_id, a.nome, a.email, a.funcao, a.ordem, a.status AS status_assinante,
	        s.id AS solicitacao_id, s.caminho_arquivo, s.nome_arquivo_original, s.id_documento, s.modo_envio,
	        s.tipo_documento_id, s.expires_at, s.prazo_expiracao_dias, s.status AS status_solicitacao
	 FROM assinantes a
	 JOIN solicitacoes_assinatura s ON s.id = a.id_solicitacao
	 WHERE a.token = ? LIMIT 1",
	"s",
	[$token]
);
$vinculo = ($resSol instanceof mysqli_result) ? (mysqli_fetch_assoc($resSol) ?: []) : [];
if(empty($vinculo) || strtolower(trim(strval($vinculo["modo_envio"] ?? ""))) !== "termo_notificacao"){
	termos_vis_erro("Link inválido", "Documento não encontrado para este link de visualização.");
}

$solId = intval($vinculo["solicitacao_id"] ?? 0);
$assId = intval($vinculo["assinante_id"] ?? 0);
$entiId = intval($vinculo["enti_nb_id"] ?? 0);
$statusAssinante = strtolower(trim(strval($vinculo["status_assinante"] ?? "")));
$nomeArquivo = trim(strval($vinculo["nome_arquivo_original"] ?? "Documento.pdf"));
$idDocumento = trim(strval($vinculo["id_documento"] ?? ""));

$resTermo = query(
	"SELECT * FROM termo_gerado WHERE terg_nb_solicitacao_assinatura = ? OR terg_nb_assinante = ? ORDER BY terg_nb_id DESC LIMIT 1",
	"ii",
	[$solId, $assId]
);
$termo = ($resTermo instanceof mysqli_result) ? (mysqli_fetch_assoc($resTermo) ?: []) : [];
$termoId = intval($termo["terg_nb_id"] ?? 0);
$modeloId = intval($termo["terg_nb_modelo"] ?? 0);
$docuId = intval($termo["terg_nb_documento_funcionario"] ?? 0);
$jaVisualizado = strtolower(trim(strval($termo["terg_tx_status"] ?? ""))) === "visualizado" || trim(strval($termo["terg_dt_data_visualizacao"] ?? "")) !== "";

$expiresRaw = trim(strval($vinculo["expires_at"] ?? ""));
$expirado = false;
if($expiresRaw !== "" && $expiresRaw !== "0000-00-00 00:00:00"){
	try{
		$exp = new DateTimeImmutable($expiresRaw, new DateTimeZone("UTC"));
		$agoraUtc = new DateTimeImmutable("now", new DateTimeZone("UTC"));
		$expirado = $exp < $agoraUtc;
	}catch(Throwable $e){
		$expirado = false;
	}
}

if($expirado && !$jaVisualizado && $statusAssinante !== "dispensado"){
	termos_vis_erro("Prazo excedido", "O prazo para visualizar este documento foi excedido. Solicite um novo envio ao responsável.");
}

$acaoRaw = $_POST["termo_acao"] ?? ($_REQUEST["termo_acao"] ?? "");
$acao = is_string($acaoRaw) ? strtolower(trim($acaoRaw)) : "";

if($acao === "geo"){
	header("Content-Type: application/json; charset=utf-8");
	$lat = trim(strval($_REQUEST["latitude"] ?? ""));
	$lng = trim(strval($_REQUEST["longitude"] ?? ""));
	$prec = trim(strval($_REQUEST["precisao"] ?? ""));
	$ok = false;
	if($lat !== "" && $lng !== "" && $termoId > 0){
		$ok = termos_executar(
			"UPDATE termo_visualizacao
			 SET tevi_nb_latitude = ?, tevi_nb_longitude = ?, tevi_nb_geo_precisao = ?
			 WHERE tevi_nb_termo = ? AND tevi_tx_evento = 'abertura' AND (tevi_nb_latitude IS NULL OR tevi_nb_latitude = '')
			 ORDER BY tevi_nb_id DESC LIMIT 1",
			"sssi",
			[substr($lat, 0, 50), substr($lng, 0, 50), substr($prec, 0, 50), $termoId]
		);
	}
	echo json_encode(["ok" => (bool)$ok]);
	exit;
}

if($acao === "confirmar"){
	$declarou = strtolower(trim(strval($_POST["declarei"] ?? ""))) === "sim";
	if(!$declarou){
		termos_vis_erro("Confirmação pendente", "Marque a declaração de visualização antes de confirmar.");
	}
	if($jaVisualizado){
		header("Location: visualizar_termo.php?token=" . urlencode($token));
		exit;
	}
	if($expirado){
		termos_vis_erro("Prazo excedido", "O prazo para visualizar este documento foi excedido. Solicite um novo envio ao responsável.");
	}

	$dadosAcesso = termos_vis_dados_acesso();
	$geo = [
		"latitude" => substr(trim(strval($_POST["latitude"] ?? "")), 0, 50),
		"longitude" => substr(trim(strval($_POST["longitude"] ?? "")), 0, 50),
		"precisao" => substr(trim(strval($_POST["precisao"] ?? "")), 0, 50)
	];

	$eventoId = termos_vis_registrar_evento($termoId, $modeloId, $entiId, $solId, $assId, "confirmacao", $token, $geo);

	$hashTermo = termos_vis_hash($termoId, $entiId, $token, "visualizacao", $dadosAcesso);
	if($termoId > 0){
		termos_atualizar_gerado($termoId, [
			"terg_tx_status" => "visualizado",
			"terg_dt_data_visualizacao" => date("Y-m-d H:i:s"),
			"terg_tx_ip_visualizacao" => $dadosAcesso["ip"],
			"terg_tx_user_agent_visualizacao" => $dadosAcesso["user_agent"],
			"terg_tx_hash_visualizacao" => $hashTermo,
			"terg_tx_detalhe" => "Visualização confirmada pelo funcionário. Auditoria registrada (IP " . $dadosAcesso["ip"] . ", " . date("d/m/Y H:i:s") . ")."
		]);
	}

	if($docuId > 0){
		termos_executar(
			"UPDATE documento_funcionario SET docu_tx_visualizado = 'sim', docu_tx_dataVisualizacao = NOW() WHERE docu_nb_id = ?",
			"i",
			[$docuId]
		);
	}

	if($assId > 0){
		termos_executar("UPDATE assinantes SET status = 'dispensado' WHERE id = ? AND LOWER(TRIM(status)) <> 'assinado'", "i", [$assId]);
	}
	if($solId > 0){
		termos_executar(
			"UPDATE solicitacoes_assinatura SET status = 'concluido', data_assinatura = NOW(), status_final = 'visualizado' WHERE id = ? AND LOWER(TRIM(status)) IN ('pendente','em_progresso')",
			"i",
			[$solId]
		);
		termos_executar(
			"UPDATE notificacoes SET notf_tx_status = 'lida', notf_tx_dataLeitura = NOW() WHERE notf_nb_entidade = ? AND notf_tx_link LIKE ?",
			"is",
			[$entiId, "%visualizar_termo.php?token=" . $token . "%"]
		);
	}

	termos_log("visualizado", "Visualização confirmada pelo funcionário", [
		"termo" => $termoId,
		"entidade" => $entiId,
		"solicitacao" => $solId,
		"evento" => $eventoId,
		"ip" => $dadosAcesso["ip"]
	]);

	header("Location: visualizar_termo.php?token=" . urlencode($token) . "&confirmado=1");
	exit;
}

$pdfUrl = "../../assinatura/assinar_via_link.php?token=" . urlencode($token) . "&arquivo=1";

if(!$jaVisualizado){
	termos_vis_registrar_evento($termoId, $modeloId, $entiId, $solId, $assId, "abertura", $token);
}

$nomeFunc = trim(strval($vinculo["nome"] ?? ""));
$confirmado = strval($_GET["confirmado"] ?? "") === "1";
$dataVisAudit = trim(strval($termo["terg_dt_data_visualizacao"] ?? ""));
$ipAudit = trim(strval($termo["terg_tx_ip_visualizacao"] ?? ""));

$cabecalhoHtml = $jaVisualizado || $confirmado
	? "<div class='bg-green-50 px-6 py-5 flex items-start gap-4'>
			<div class='h-12 w-12 rounded-xl flex items-center justify-center flex-shrink-0 bg-green-100 text-green-700'><i class='fas fa-circle-check text-xl'></i></div>
			<div><div class='text-lg font-bold text-gray-900'>Visualização registrada</div>
			<div class='mt-1 text-sm text-gray-700'>Sua visualização já foi registrada com sucesso. Guarde este link como comprovante.</div></div>
		</div>"
	: "<div class='bg-blue-50 px-6 py-5 flex items-start gap-4'>
			<div class='h-12 w-12 rounded-xl flex items-center justify-center flex-shrink-0 bg-blue-100 text-blue-700'><i class='fas fa-file-lines text-xl'></i></div>
			<div><div class='text-lg font-bold text-gray-900'>Documento para visualização</div>
			<div class='mt-1 text-sm text-gray-700'>Leia o documento abaixo e confirme a visualização. Os dados do seu acesso serão registrados para auditoria.</div></div>
		</div>";

$rodapeAudit = "";
if($jaVisualizado && $dataVisAudit !== "" && $dataVisAudit !== "0000-00-00 00:00:00"){
	$tsVis = strtotime($dataVisAudit);
	$rodapeAudit = "<div class='mt-4 text-xs text-gray-600 border-t border-gray-100 pt-3'>
		<i class='fas fa-shield-halved mr-1'></i> Visualizado em <b>" . termos_vis_h($tsVis ? date("d/m/Y H:i:s", $tsVis) : $dataVisAudit) . "</b>" . ($ipAudit !== "" ? " — IP <b>" . termos_vis_h($ipAudit) . "</b>" : "") . "
	</div>";
}

$botaoConfirmar = "";
if(!$jaVisualizado && !$confirmado){
	$botaoConfirmar = "
	<form method='post' id='form_confirmar' class='mt-4'>
		<input type='hidden' name='token' value='" . termos_vis_h($token) . "'>
		<input type='hidden' name='termo_acao' value='confirmar'>
		<input type='hidden' name='latitude' id='geo_lat' value=''>
		<input type='hidden' name='longitude' id='geo_lng' value=''>
		<input type='hidden' name='precisao' id='geo_prec' value=''>
		<label class='flex items-start gap-2 text-sm text-gray-700 mb-3'>
			<input type='checkbox' name='declarei' value='sim' class='mt-1' required>
			<span>Declaro que li e visualizei o documento <b>" . termos_vis_h($nomeArquivo) . "</b>.</span>
		</label>
		<button type='submit' class='w-full sm:w-auto inline-flex items-center justify-center px-5 py-3 rounded-lg bg-blue-700 hover:bg-blue-800 text-white font-semibold'>
			<i class='fas fa-check mr-2'></i> Confirmar visualização
		</button>
	</form>";
}

$acoesFinal = "";
if($jaVisualizado || $confirmado){
	$acoesFinal = "<div class='mt-4 flex flex-col sm:flex-row gap-2'>" . termos_vis_botao_voltar("Voltar") . "</div>";
}

$conteudo = "
<div class='bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden'>
	{$cabecalhoHtml}
	<div class='px-6 py-5 border-b border-gray-100'>
		<div class='text-xs uppercase tracking-wide text-gray-500 mb-1'>Documento</div>
		<div class='text-base font-semibold text-gray-900 break-words'>" . termos_vis_h($nomeArquivo) . "</div>
		" . ($idDocumento !== "" ? "<div class='text-xs text-gray-500 mt-1'>ID: <span class='font-mono'>" . termos_vis_h($idDocumento) . "</span></div>" : "") . "
		" . ($nomeFunc !== "" ? "<div class='text-xs text-gray-500 mt-1'>Destinatário: " . termos_vis_h($nomeFunc) . "</div>" : "") . "
		{$rodapeAudit}
	</div>
	<div class='px-2 sm:px-6 py-4'>
		<iframe src='" . termos_vis_h($pdfUrl) . "' title='Documento' style='width:100%; height:70vh; border:1px solid #e5e7eb; border-radius:8px; background:#fff;'></iframe>
		<div class='mt-3'>
			<a href='" . termos_vis_h($pdfUrl) . "&download=1' download class='text-sm text-blue-700 hover:text-blue-900'><i class='fas fa-download mr-1'></i> Baixar PDF</a>
		</div>
		{$botaoConfirmar}
		{$acoesFinal}
	</div>
	<div class='px-6 py-4 bg-gray-50 text-[11px] leading-relaxed text-gray-500'>
		Ao abrir este documento e confirmar a visualização, o sistema registra automaticamente, para fins de auditoria: data e hora do acesso, endereço IP, navegador/dispositivo, idioma e, quando autorizado, a geolocalização aproximada.
	</div>
</div>
<script>
(function(){
	// Confirmação via AJAX: sem nova entrada no histórico, o botão Voltar retorna
	// direto para a tela anterior (ex.: registrar ponto), com um único toque.
	var form = document.getElementById('form_confirmar');
	if(form){
		form.addEventListener('submit', function(ev){
			ev.preventDefault();
			var botao = form.querySelector(\"button[type='submit']\");
			if(botao){ botao.disabled = true; }
			fetch(form.getAttribute('action') || window.location.href, {
				method: 'POST',
				body: new FormData(form),
				credentials: 'same-origin'
			}).then(function(resp){
				if(!resp.ok){ throw new Error('HTTP ' + resp.status); }
				window.location.reload();
			}).catch(function(){
				if(botao){ botao.disabled = false; }
				alert('Não foi possível registrar a confirmação agora. Tente novamente.');
			});
		});
	}
	if(!navigator.geolocation){ return; }
	navigator.geolocation.getCurrentPosition(function(pos){
		var lat = pos.coords.latitude, lng = pos.coords.longitude, prec = pos.coords.accuracy;
		var elLat = document.getElementById('geo_lat');
		var elLng = document.getElementById('geo_lng');
		var elPrec = document.getElementById('geo_prec');
		if(elLat){ elLat.value = lat; }
		if(elLng){ elLng.value = lng; }
		if(elPrec){ elPrec.value = prec; }
		try{
			fetch('visualizar_termo.php?termo_acao=geo&token=" . rawurlencode($token) . "&latitude=' + encodeURIComponent(lat) + '&longitude=' + encodeURIComponent(lng) + '&precisao=' + encodeURIComponent(prec), {credentials:'same-origin'});
		}catch(e){}
	}, function(){}, {enableHighAccuracy:false, timeout:8000, maximumAge:600000});
})();
</script>
";

termos_vis_pagina($conteudo);
