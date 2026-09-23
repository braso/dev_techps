<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include_once __DIR__ . "/funcoes_termos.php";
include_once dirname(__DIR__, 2) . "/conecta.php";

function termos_preview_hide_loading(): void {
	echo "
	<style>
		.loading { display: none !important; }
	</style>
	<script>
		function termosPreviewHideLoading() {
			var loadings = document.getElementsByClassName('loading');
			for (var i = 0; i < loadings.length; i++) {
				loadings[i].style.visibility = 'hidden';
				loadings[i].style.display = 'none';
			}
		}
		termosPreviewHideLoading();
		setTimeout(termosPreviewHideLoading, 500);
	</script>";
}

function index() {
	global $conn;
	termos_ensure_tables($conn);

	if(!termos_pode_acessar("/documentos/termos/modelos_termo.php")){
		header("Location: ../../dashboard.php");
		exit;
	}

	$modeloId = intval($_GET["modelo"] ?? 0);
	$modelo = termos_carregar_modelo($modeloId);
	if(empty($modelo)){
		die("Modelo não encontrado.");
	}

	$nomeModelo = termos_h($modelo["mode_tx_nome"] ?? "");
	$conteudo = termos_sanitizar_html(strval($modelo["mode_tx_conteudo"] ?? ""));
	if(trim(strip_tags($conteudo)) === ""){
		$conteudo = "<p><i>O modelo ainda não possui texto padrão.</i></p>";
	}

	$tagsUsadas = [];
	preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $conteudo, $m);
	foreach($m[1] as $t){
		$tagsUsadas[strtolower(trim($t))] = true;
	}

	$conteudoVis = preg_replace('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', '<span class="termo-tag">{{$1}}</span>', $conteudo);

	$placeholders = termos_lista_placeholders();
	$tagsHtml = "";
	if(!empty($tagsUsadas)){
		foreach(array_keys($tagsUsadas) as $t){
			$desc = $placeholders["{{" . $t . "}}"] ?? "Campo livre (não reconhecido pelo sistema)";
			$tagsHtml .= "<tr><td><code>{{" . termos_h($t) . "}}</code></td><td>" . termos_h($desc) . "</td></tr>";
		}
	}else{
		$tagsHtml = "<tr><td colspan='2' class='text-center text-muted'>Nenhum campo ({{...}}) inserido no texto.</td></tr>";
	}

	cabecalho("Pré-visualizar — " . $nomeModelo);
	termos_preview_hide_loading();

	echo "
	<style>
		.termo-preview-papel {
			background: #fff;
			border: 1px solid #ddd;
			box-shadow: 0 1px 8px rgba(0,0,0,.12);
			max-width: 900px;
			margin: 0 auto;
			padding: 36px 46px;
			font-family: Georgia, 'Times New Roman', serif;
			font-size: 15px;
			line-height: 1.7;
			color: #222;
			min-height: 600px;
		}
		.termo-tag {
			background: #fff3cd;
			color: #b02a2a;
			border: 1px solid #f0ad4e;
			border-radius: 3px;
			padding: 0 4px;
			font-family: Consolas, Menlo, Monaco, monospace;
			font-weight: 600;
			font-size: 13px;
		}
	</style>
	";

	echo "<div class='col-md-12'>";

	echo "<div class='alert alert-info'>
		<i class='fa fa-info-circle'></i> <b>Pré-visualização do texto do modelo.</b> Os campos destacados em amarelo (<span class='termo-tag'>{{...}}</span>) serão preenchidos automaticamente com os dados de cada funcionário na hora de gerar o documento.
	</div>";

	echo "<div class='termo-preview-papel'>" . $conteudoVis . "</div>";

	echo "<br>";

	echo "<div class='portlet light'>
		<div class='portlet-title'><span class='caption-subject font-dark bold uppercase'>Campos usados neste documento</span></div>
		<div class='portlet-body'>
			<div class='table-responsive'><table class='table table-bordered table-striped'>
				<thead><tr><th style='width:220px'>Campo</th><th>O que será preenchido</th></tr></thead>
				<tbody>" . $tagsHtml . "</tbody>
			</table></div>
			<a href='modelos_termo.php' class='btn btn-default'><span class='glyphicon glyphicon-arrow-left'></span> Voltar</a>
		</div>
	</div>";

	echo "</div>";

	rodape();
}