<?php
/**
 * Página pública (com token) de leitura do POP enviado ao colaborador.
 * Não exige login: o acesso é liberado exclusivamente pelo token do envio.
 */
$interno = true;
include_once __DIR__ . "/funcoes_pop.php";
include_once dirname(__DIR__, 2) . "/conecta.php";

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Robots-Tag: noindex, nofollow, noarchive");

pop_ensure_schema();

$token = trim(strval($_GET["token"] ?? ""));
$envio = pop_envio_por_token($token);
$doc = [];
if(!empty($envio)){
	$doc = pop_obter_documento(strval($envio["pev_tx_origem"] ?? ""), intval($envio["pev_nb_doc"] ?? 0));
}

$valido = !empty($doc) && strval($doc["status"] ?? "") === "ativo";
if(!$valido){
	http_response_code(404);
}
$logo = @file_exists(dirname(__DIR__, 2) . "/imagens/logo_topo_cliente.png");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow, noarchive">
	<title><?= $valido ? "POP — " . pop_h(strval($doc["titulo"] ?? "")) : "POP indisponível" ?></title>
	<style>
		*{box-sizing:border-box;}
		body{
			margin:0;
			min-height:100vh;
			background:
				radial-gradient(circle at 12% 0%, rgba(22,163,74,.20), transparent 32%),
				radial-gradient(circle at 92% 8%, rgba(37,99,235,.16), transparent 30%),
				#eef2f7;
			font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
			color:#0f172a;
		}
		.share-wrap{max-width:1180px;margin:0 auto;padding:22px 16px 40px;}
		.share-top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;}
		.share-brand{display:flex;align-items:center;gap:10px;color:#475569;font-size:12px;font-weight:600;}
		.share-brand img{max-height:30px;max-width:160px;object-fit:contain;}
		.share-brand span{letter-spacing:.06em;text-transform:uppercase;}
		.share-secure{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid #bbf7d0;border-radius:999px;background:#f0fdf4;color:#166534;font-size:11px;font-weight:700;}
		.share-card{overflow:hidden;background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 24px 60px rgba(15,23,42,.10);}
		.share-hero{
			padding:28px 30px 24px;
			color:#fff;
			background:radial-gradient(circle at 88% 0%, rgba(74,222,128,.30), transparent 36%), linear-gradient(135deg,#0b1220,#123b34 58%,#166534);
		}
		.share-hero h1{margin:10px 0 8px;font-size:clamp(22px,3vw,34px);line-height:1.16;letter-spacing:-.02em;}
		.share-hero p{margin:0;color:rgba(255,255,255,.76);font-size:14px;line-height:1.5;}
		.share-kicker{display:inline-flex;align-items:center;gap:6px;padding:4px 9px;border:1px solid rgba(255,255,255,.24);border-radius:999px;background:rgba(255,255,255,.10);font-size:10px;font-weight:800;letter-spacing:.10em;text-transform:uppercase;}
		.share-body{padding:22px 30px 30px;}
		.share-meta{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;}
		.share-chip{display:inline-flex;align-items:center;gap:5px;padding:7px 10px;border:1px solid #e2e8f0;border-radius:9px;background:#f8fafc;color:#475569;font-size:12px;}
		.share-chip b{color:#0f172a;font-weight:700;}
		.share-objective{margin:0 0 20px;padding:14px 16px;border-left:4px solid #16a34a;border-radius:0 9px 9px 0;background:#f0fdf4;color:#475569;font-size:13px;line-height:1.6;}
		.share-message{margin:0 0 18px;padding:12px 14px;border:1px solid #dbeafe;border-radius:9px;background:#eff6ff;color:#1e3a5f;font-size:13px;line-height:1.55;}
		.share-message b{display:block;margin-bottom:3px;color:#1d4ed8;font-size:11px;letter-spacing:.05em;text-transform:uppercase;}
		.share-actions{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;}
		.share-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:40px;padding:9px 14px;border:1px solid #e2e8f0;border-radius:9px;background:#fff;color:#334155!important;font-size:13px;font-weight:700;text-decoration:none!important;transition:.15s ease;}
		.share-btn:hover{transform:translateY(-1px);border-color:#94a3b8;background:#f8fafc;}
		.share-btn--green{border-color:#16a34a;background:#16a34a;color:#fff!important;box-shadow:0 6px 16px rgba(22,163,74,.24);}
		.share-btn--green:hover{background:#15803d;border-color:#15803d;}
		.share-frame{width:100%;height:72vh;min-height:440px;border:1px solid #cbd5e1;border-radius:12px;background:#525659;}
		.share-footer{margin-top:14px;text-align:center;color:#94a3b8;font-size:11px;line-height:1.6;}
		.share-error{max-width:560px;margin:70px auto;padding:34px 28px;text-align:center;background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 24px 60px rgba(15,23,42,.10);}
		.share-error__icon{display:inline-flex;align-items:center;justify-content:center;width:70px;height:70px;margin-bottom:14px;border-radius:50%;background:#fff1f2;color:#be123c;font-size:30px;font-weight:800;}
		.share-error h1{margin:0 0 8px;font-size:23px;}
		.share-error p{margin:0;color:#64748b;font-size:14px;line-height:1.6;}
		@media (max-width:700px){
			.share-hero{padding:22px 20px;}
			.share-body{padding:18px 20px 22px;}
			.share-frame{height:62vh;min-height:360px;}
			.share-top{align-items:flex-start;flex-direction:column;}
		}
	</style>
</head>
<body>
<?php if(!$valido): ?>
	<div class="share-error">
		<div class="share-error__icon">!</div>
		<h1>POP indisponível</h1>
		<p>Este link é inválido, expirou ou o documento foi desativado. Solicite um novo envio ao setor responsável.</p>
	</div>
<?php else: ?>
	<?php
	$titulo = strval($doc["titulo"] ?? "");
	$setor = trim(strval($doc["setor_nome"] ?? ""));
	$codigo = trim(strval($doc["codigo"] ?? ""));
	$versao = trim(strval($doc["versao"] ?? ""));
	$vigencia = trim(strval($doc["vigencia"] ?? ""));
	$responsavel = trim(strval($doc["responsavel"] ?? ""));
	$objetivo = trim(strval($doc["objetivo"] ?? ""));
	$cacheDoc = intval($doc["atualizado_ts"] ?? 0);
	$pdfUrl = "pop_arquivo.php?token=" . urlencode($token) . "&v=" . $cacheDoc;
	$flowUrl = $pdfUrl . "&tipo=fluxograma";
	$temFluxo = !empty($doc["tem_fluxograma"]);
	$nomeDestinatario = trim(strval($envio["pev_tx_nome"] ?? ""));
	$expira = trim(strval($envio["pev_dt_expira"] ?? ""));
	?>
	<div class="share-wrap">
		<div class="share-top">
			<div class="share-brand">
				<?php if($logo): ?>
					<img src="../../imagens/logo_topo_cliente.png" alt="Empresa">
				<?php else: ?>
					<span>TechPS</span>
				<?php endif; ?>
				<span>Biblioteca de POPs</span>
			</div>
			<div class="share-secure">Acesso seguro por link</div>
		</div>

		<article class="share-card">
			<header class="share-hero">
				<span class="share-kicker">Procedimento Operacional Padrão</span>
				<h1><?= pop_h($titulo) ?></h1>
				<p>
					<?= $nomeDestinatario !== "" ? "Olá, " . pop_h($nomeDestinatario) . ". " : "" ?>
					Este documento foi compartilhado para consulta e treinamento.
				</p>
			</header>
			<div class="share-body">
				<div class="share-meta">
					<?php if($setor !== ""): ?><span class="share-chip"><b>Setor:</b> <?= pop_h($setor) ?></span><?php endif; ?>
					<?php if($codigo !== ""): ?><span class="share-chip"><b>Código:</b> <?= pop_h($codigo) ?></span><?php endif; ?>
					<span class="share-chip"><b>Versão:</b> <?= pop_h($versao !== "" ? $versao : "1.0") ?></span>
					<?php if($vigencia !== ""): ?><span class="share-chip"><b>Vigência:</b> <?= pop_h(pop_data_br($vigencia)) ?></span><?php endif; ?>
					<?php if($responsavel !== ""): ?><span class="share-chip"><b>Responsável:</b> <?= pop_h($responsavel) ?></span><?php endif; ?>
				</div>

				<?php if($objetivo !== ""): ?>
					<p class="share-objective"><?= nl2br(pop_h($objetivo)) ?></p>
				<?php endif; ?>

				<?php
				$mensagemEnvio = trim(strval($envio["pev_tx_mensagem"] ?? ""));
				if($mensagemEnvio !== ""):
				?>
					<div class="share-message">
						<b>Mensagem do responsável</b>
						<?= nl2br(pop_h($mensagemEnvio)) ?>
					</div>
				<?php endif; ?>

				<div class="share-actions">
					<a href="<?= pop_h($pdfUrl . "&download=1") ?>" class="share-btn share-btn--green">Baixar PDF</a>
					<a href="<?= pop_h($pdfUrl) ?>" target="_blank" rel="noopener" class="share-btn">Abrir em nova aba</a>
					<?php if($temFluxo): ?>
						<a href="<?= pop_h($flowUrl) ?>" target="_blank" rel="noopener" class="share-btn">Ver fluxograma</a>
					<?php endif; ?>
				</div>

				<iframe class="share-frame" src="<?= pop_h($pdfUrl) ?>" title="Documento POP"></iframe>
			</div>
		</article>

		<footer class="share-footer">
			Documento compartilhado automaticamente pela Biblioteca de POPs TechPS.<br>
			<?php if($expira !== ""): ?>Link válido até <?= pop_h(pop_data_br($expira, true)) ?>.<?php endif; ?>
			Para solicitar uma nova versão, procure o responsável pelo setor.
		</footer>
	</div>
<?php endif; ?>
</body>
</html>
