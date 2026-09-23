<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include_once __DIR__ . "/funcoes_termos.php";
include_once dirname(__DIR__, 2) . "/conecta.php";

function termos_hide_loading(): void {
	echo "
	<style>
		.loading { display: none !important; }
	</style>
	<script>
		function termosHideLoadingFinal() {
			var loadings = document.getElementsByClassName('loading');
			for (var i = 0; i < loadings.length; i++) {
				loadings[i].style.visibility = 'hidden';
				loadings[i].style.display = 'none';
			}
		}
		termosHideLoadingFinal();
		setTimeout(termosHideLoadingFinal, 500);
	</script>";
}

function termos_status_badge(string $status): string {
	$status = strtolower(trim($status));
	$mapa = [
		"gerado" => ["info", "Gerado"],
		"aguardando_assinatura" => ["warning", "Aguardando Assinatura"],
		"assinado" => ["success", "Assinado"],
		"erro" => ["danger", "Erro"],
		"cancelado" => ["default", "Cancelado"]
	];
	$item = $mapa[$status] ?? ["default", $status];
	return "<span class='label label-{$item[0]}'>" . termos_h($item[1]) . "</span>";
}

function termos_listar(array $filtros): array {
	$where = ["tg.terg_nb_id > 0"];
	$types = "";
	$vars = [];

	$modelo = intval($filtros["modelo"] ?? 0);
	$status = strval($filtros["status"] ?? "");
	$busca = trim(strval($filtros["busca"] ?? ""));

	if($modelo > 0){
		$where[] = "tg.terg_nb_modelo = ?";
		$types .= "i";
		$vars[] = $modelo;
	}
	if($status !== ""){
		$where[] = "tg.terg_tx_status = ?";
		$types .= "s";
		$vars[] = $status;
	}
	if($busca !== ""){
		$where[] = "(e.enti_tx_nome LIKE ? OR e.enti_tx_matricula LIKE ?)";
		$types .= "ss";
		$like = "%" . $busca . "%";
		$vars[] = $like;
		$vars[] = $like;
	}

	$sql = "SELECT
			tg.*, m.mode_tx_nome AS modelo_nome, t.tipo_tx_nome AS tipo_nome,
			e.enti_tx_nome AS func_nome, e.enti_tx_matricula, em.empr_tx_nome AS empresa_nome
		FROM termo_gerado tg
		LEFT JOIN modelo_termo m ON m.mode_nb_id = tg.terg_nb_modelo
		LEFT JOIN tipos_documentos t ON t.tipo_nb_id = tg.terg_nb_tipo_doc
		LEFT JOIN entidade e ON e.enti_nb_id = tg.terg_nb_entidade
		LEFT JOIN empresa em ON em.empr_nb_id = e.enti_nb_empresa
		WHERE " . implode(" AND ", $where) . "
		ORDER BY tg.terg_dt_geracao DESC
		LIMIT 500";

	$res = query($sql, $types, $vars);
	$out = [];
	while($res && ($r = mysqli_fetch_assoc($res))){
		$out[] = $r;
	}
	return $out;
}

function index() {
	global $conn;
	termos_ensure_tables($conn);
	termos_verificar_permissao("/documentos/termos/listar_termos.php");

	cabecalho("Termos Gerados");
	termos_hide_loading();

	$filtroModelo = intval($_POST["modelo"] ?? 0);
	$filtroStatus = strval($_POST["status"] ?? "");
	$filtroBusca = trim(strval($_POST["busca"] ?? ""));

	$modelos = ["" => "Todos"];
	$resModelos = query("SELECT mode_nb_id, mode_tx_nome FROM modelo_termo ORDER BY mode_tx_nome ASC");
	while($resModelos && ($r = mysqli_fetch_assoc($resModelos))){
		$modelos[intval($r["mode_nb_id"])] = $r["mode_tx_nome"];
	}

	$fields = [
		combo("Modelo", "modelo", $filtroModelo, 3, $modelos),
		combo("Status", "status", $filtroStatus, 2, [
			"" => "Todos",
			"gerado" => "Gerado",
			"aguardando_assinatura" => "Aguardando Assinatura",
			"assinado" => "Assinado",
			"erro" => "Erro",
			"cancelado" => "Cancelado"
		]),
		campo("Buscar (nome ou matrícula)", "busca", $filtroBusca, 3)
	];

	$buttons = [
		botao("Buscar", "index", "", "", "", false, "btn btn-primary"),
		botao("Limpar", "limparFiltros", "", "", "", false, "btn btn-default"),
		botao("Sincronizar Pendentes", "sincronizar_todos", "", "", "", false, "btn btn-warning"),
		"<a href='modelos_termo.php' class='btn btn-secondary'>Voltar</a>"
	];

	echo abre_form("Filtros");
	echo linha_form($fields);
	echo fecha_form($buttons);

	$linhas = termos_listar([
		"modelo" => $filtroModelo,
		"status" => $filtroStatus,
		"busca" => $filtroBusca
	]);

	echo "<h3>Termos Gerados (" . count($linhas) . ")</h3>";
	echo "<div class='table-responsive'><table class='table table-bordered table-striped'>";
	echo "<thead><tr><th>ID</th><th>Modelo</th><th>Funcionário</th><th>Matrícula</th><th>Empresa</th><th>Status</th><th>Gerado em</th><th>Ações</th></tr></thead>";
	echo "<tbody>";

	if(empty($linhas)){
		echo "<tr><td colspan='8' class='text-center'>Nenhum termo encontrado.</td></tr>";
	}

	foreach($linhas as $r){
		$id = intval($r["terg_nb_id"]);
		$modeloNome = termos_h($r["modelo_nome"] ?? "");
		$funcNome = termos_h($r["func_nome"] ?? "—");
		$mat = termos_h($r["enti_tx_matricula"] ?? "");
		$empresa = termos_h($r["empresa_nome"] ?? "—");
		$status = strval($r["terg_tx_status"] ?? "");
		$data = date("d/m/Y H:i", strtotime(strval($r["terg_dt_geracao"])));

		$pdfLink = termos_link_pdf($r);
		$acaoPdf = $pdfLink !== ""
			? "<a href='{$pdfLink}' target='_blank' class='btn btn-xs btn-info' title='Abrir PDF'><span class='glyphicon glyphicon-print'></span></a>"
			: "<span class='text-muted' title='PDF disponível após conclusão das assinaturas'><span class='glyphicon glyphicon-print'></span></span>";

		echo "<tr>";
		echo "<td>{$id}</td>";
		echo "<td>{$modeloNome}</td>";
		echo "<td>{$funcNome}</td>";
		echo "<td>{$mat}</td>";
		echo "<td>{$empresa}</td>";
		echo "<td>" . termos_status_badge($status) . "</td>";
		echo "<td>{$data}</td>";
		echo "<td>
				<form method='post' style='display:inline;'><input type='hidden' name='id' value='{$id}'><input type='hidden' name='acao' value='sincronizar'><button type='submit' class='btn btn-xs btn-default' title='Sincronizar status com o módulo de assinatura'><span class='glyphicon glyphicon-refresh'></span></button></form>
				{$acaoPdf}
				<form method='post' style='display:inline;' onsubmit='return confirm(\"Cancelar este termo?\");'><input type='hidden' name='id' value='{$id}'><input type='hidden' name='acao' value='cancelar'><button type='submit' class='btn btn-xs btn-danger' title='Cancelar'><span class='glyphicon glyphicon-ban-circle'></span></button></form>
			</td>";
		echo "</tr>";
	}

	echo "</tbody></table></div>";

	rodape();
}

function sincronizar() {
	global $conn;
	termos_ensure_tables($conn);
	termos_verificar_permissao("/documentos/termos/listar_termos.php");

	$id = intval($_POST["id"] ?? 0);
	$reg = termos_carregar_registro($id);
	if(!empty($reg)){
		$novo = termos_sincronizar_registro($reg);
		set_status("Termo #{$id} sincronizado. Status: " . strtolower(trim(strval($novo["terg_tx_status"] ?? ""))));
	}else{
		set_status("ERRO: Termo não encontrado.");
	}
	index();
	exit;
}

function sincronizar_todos() {
	global $conn;
	termos_ensure_tables($conn);
	termos_verificar_permissao("/documentos/termos/listar_termos.php");

	$res = query(
		"SELECT * FROM termo_gerado WHERE terg_tx_status = 'aguardando_assinatura' AND terg_nb_solicitacao_assinatura > 0 ORDER BY terg_nb_id ASC"
	);
	$atualizados = 0;
	while($res && ($r = mysqli_fetch_assoc($res))){
		$novo = termos_sincronizar_registro($r);
		if(strtolower(trim(strval($novo["terg_tx_status"] ?? ""))) !== "aguardando_assinatura"){
			$atualizados++;
		}
	}
	set_status("Sincronização concluída. Registros atualizados: {$atualizados}.");
	index();
	exit;
}

function cancelar() {
	global $conn;
	termos_ensure_tables($conn);
	termos_verificar_permissao("/documentos/termos/listar_termos.php");

	$id = intval($_POST["id"] ?? 0);
	if($id > 0){
		termos_atualizar_gerado($id, [
			"terg_tx_status" => "cancelado",
			"terg_tx_detalhe" => "Cancelado manualmente pelo usuário."
		]);
		termos_log("cancelado", "Termo #{$id} cancelado manualmente");
		set_status("Termo #{$id} cancelado.");
	}
	index();
	exit;
}