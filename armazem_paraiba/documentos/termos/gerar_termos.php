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

function termos_opcoes_modelos(): array {
	$out = ["" => "Selecione..."];
	$res = query(
		"SELECT m.mode_nb_id, m.mode_tx_nome, t.tipo_tx_assinatura
		 FROM modelo_termo m
		 LEFT JOIN tipos_documentos t ON t.tipo_nb_id = m.mode_nb_tipo_doc
		 WHERE m.mode_tx_status = 'ativo'
		 ORDER BY m.mode_tx_nome ASC"
	);
	while($res && ($r = mysqli_fetch_assoc($res))){
		$suf = strtolower(trim(strval($r["tipo_tx_assinatura"] ?? "nao"))) === "sim" ? " [assinatura]" : "";
		$out[intval($r["mode_nb_id"])] = $r["mode_tx_nome"] . $suf;
	}
	return $out;
}

function termos_buscar_entidades(array $filtros): array {
	$where = ["e.enti_nb_id > 0"];
	$types = "";
	$vars = [];

	$modeloId = intval($filtros["modelo"] ?? 0);

	$empresas = $filtros["empresas"] ?? [];
	if(!is_array($empresas)){
		$empresas = [];
	}
	$empresas = array_values(array_filter(array_map("intval", $empresas)));
	if(empty($empresas) && intval($filtros["empresa"] ?? 0) > 0){
		$empresas = [intval($filtros["empresa"])];
	}

	$cargos = $filtros["cargos"] ?? [];
	if(!is_array($cargos)){
		$cargos = [];
	}
	$cargos = array_values(array_filter(array_map("intval", $cargos)));
	if(empty($cargos) && intval($filtros["cargo"] ?? 0) > 0){
		$cargos = [intval($filtros["cargo"])];
	}

	$setores = $filtros["setores"] ?? [];
	if(!is_array($setores)){
		$setores = [];
	}
	$setores = array_values(array_filter(array_map("intval", $setores)));
	if(empty($setores) && intval($filtros["setor"] ?? 0) > 0){
		$setores = [intval($filtros["setor"])];
	}

	$status = strval($filtros["status"] ?? "ativo");
	$busca = trim(strval($filtros["busca"] ?? ""));

	if(!empty($empresas)){
		$where[] = "e.enti_nb_empresa IN (" . implode(",", array_unique($empresas)) . ")";
	}
	if(!empty($cargos)){
		$where[] = "e.enti_tx_tipoOperacao IN (" . implode(",", array_unique($cargos)) . ")";
	}
	if(!empty($setores)){
		$where[] = "e.enti_setor_id IN (" . implode(",", array_unique($setores)) . ")";
	}
	if(in_array($status, ["ativo", "inativo"], true)){
		$where[] = "e.enti_tx_status = ?";
		$types .= "s";
		$vars[] = $status;
	}
	if($busca !== ""){
		$where[] = "(e.enti_tx_nome LIKE ? OR e.enti_tx_matricula LIKE ? OR e.enti_tx_cpf LIKE ?)";
		$types .= "sss";
		$like = "%" . $busca . "%";
		$vars[] = $like;
		$vars[] = $like;
		$vars[] = $like;
	}

	$sql = "SELECT
			e.enti_nb_id, e.enti_tx_matricula, e.enti_tx_nome, e.enti_tx_cpf, e.enti_tx_email, e.enti_tx_status,
			em.empr_tx_nome AS empresa_nome, op.oper_tx_nome AS cargo_nome, g.grup_tx_nome AS setor_nome
		FROM entidade e
		LEFT JOIN empresa em ON em.empr_nb_id = e.enti_nb_empresa
		LEFT JOIN operacao op ON op.oper_nb_id = e.enti_tx_tipoOperacao
		LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
		WHERE " . implode(" AND ", $where) . "
		ORDER BY e.enti_tx_nome ASC";

	$res = query($sql, $types, $vars);
	$out = [];
	while($res && ($r = mysqli_fetch_assoc($res))){
		$out[] = $r;
	}
	return $out;
}

function termos_filtro_checkboxes(string $titulo, string $nome, array $opcoes, array $selecionados, string $classe, int $tamanho): string {
	$html = "<div class='col-sm-{$tamanho} margin-bottom-5 campo-fit-content'>
		<label>{$titulo}</label>
		<div style='margin:2px 0;'>
			<button type='button' class='btn btn-default btn-xs' onclick='termosFiltroMarcar(\"{$classe}\", true)'>Marcar todos</button>
			<button type='button' class='btn btn-default btn-xs' onclick='termosFiltroMarcar(\"{$classe}\", false)'>Desmarcar todos</button>
		</div>
		<div style='max-height:110px; overflow-y:auto; border:1px solid #eee; padding:4px; background:#fcfcfc; overflow-wrap:break-word;'>";
	$set = array_flip($selecionados);
	foreach($opcoes as $k => $v){
		if($k === "" || $k === null){
			continue;
		}
		$checked = isset($set[(int)$k]) ? "checked" : "";
		$html .= "<label style='font-weight:normal; margin:0 10px 2px 0; display:inline-block;'><input type='checkbox' name='{$nome}[]' class='{$classe}' value='" . (int)$k . "' {$checked}> " . termos_h($v) . "</label>";
	}
	$html .= "</div></div>";
	return $html;
}

function termos_mapa_termos_existentes(int $modeloId, array $entidades): array {
	$out = [];
	if($modeloId <= 0 || empty($entidades)){
		return $out;
	}
	$ids = [];
	foreach($entidades as $e){
		$ids[] = intval($e["enti_nb_id"]);
	}
	$in = implode(",", array_unique($ids));
	$res = query(
		"SELECT terg_nb_entidade, terg_tx_status FROM termo_gerado
		 WHERE terg_nb_modelo = ? AND terg_nb_entidade IN ({$in}) AND terg_tx_status IN ('gerado','aguardando_assinatura','assinado')",
		"i",
		[$modeloId]
	);
	while($res && ($r = mysqli_fetch_assoc($res))){
		$eid = intval($r["terg_nb_entidade"]);
		if(!isset($out[$eid])){
			$out[$eid] = $r["terg_tx_status"];
		}
	}
	return $out;
}

function index() {
	global $conn;
	termos_ensure_tables($conn);
	termos_verificar_permissao("/documentos/termos/gerar_termos.php");

	cabecalho("Gerar Termos");
	termos_hide_loading();

	$filtroModelo = intval($_POST["modelo"] ?? 0);

	$filtroEmpresas = $_POST["empresas"] ?? [];
	if(!is_array($filtroEmpresas)){
		$filtroEmpresas = [];
	}
	$filtroEmpresas = array_values(array_filter(array_map("intval", $filtroEmpresas)));
	if(empty($filtroEmpresas) && intval($_POST["empresa"] ?? 0) > 0){
		$filtroEmpresas = [intval($_POST["empresa"])];
	}

	$filtroCargos = $_POST["cargos"] ?? [];
	if(!is_array($filtroCargos)){
		$filtroCargos = [];
	}
	$filtroCargos = array_values(array_filter(array_map("intval", $filtroCargos)));
	if(empty($filtroCargos) && intval($_POST["cargo"] ?? 0) > 0){
		$filtroCargos = [intval($_POST["cargo"])];
	}

	$filtroSetores = $_POST["setores"] ?? [];
	if(!is_array($filtroSetores)){
		$filtroSetores = [];
	}
	$filtroSetores = array_values(array_filter(array_map("intval", $filtroSetores)));
	if(empty($filtroSetores) && intval($_POST["setor"] ?? 0) > 0){
		$filtroSetores = [intval($_POST["setor"])];
	}

	$filtroStatus = strval($_POST["status"] ?? "ativo");
	$filtroBusca = trim(strval($_POST["busca"] ?? ""));

	$modelos = termos_opcoes_modelos();
	$empresas = termos_opcoes_empresas();
	$cargos = termos_opcoes_cargos();
	$setores = termos_opcoes_setores();

	$temBusca = (isset($_POST["buscar"]) || $filtroModelo > 0 || !empty($filtroEmpresas) || !empty($filtroCargos) || !empty($filtroSetores) || $filtroBusca !== "");

	$fields = [
		combo("Modelo de Termo*", "modelo", $filtroModelo, 12, $modelos)
	];
	$fieldsFiltros = [
		termos_filtro_checkboxes("Empresa", "empresas", $empresas, $filtroEmpresas, "filtro-empresa", 4),
		termos_filtro_checkboxes("Cargo", "cargos", $cargos, $filtroCargos, "filtro-cargo", 4),
		termos_filtro_checkboxes("Setor", "setores", $setores, $filtroSetores, "filtro-setor", 4)
	];
	$fieldsExtras = [
		combo("Status", "status", $filtroStatus, 3, ["ativo" => "Ativo", "inativo" => "Inativo"]),
		campo("Buscar (nome, matrícula ou CPF)", "busca", $filtroBusca, 9)
	];

	$buttons = [
		botao("Buscar", "index", "", "", "", false, "btn btn-primary"),
		botao("Limpar", "limparFiltros", "", "", "", false, "btn btn-default")
	];

	echo abre_form("Seleção de Funcionários");
	echo linha_form($fields);
	echo linha_form($fieldsFiltros);
	echo linha_form($fieldsExtras);
	echo fecha_form($buttons);

	echo "
	<style>
		.termos-opcoes-linha > div,
		.termos-opcoes-linha .campo-fit-content {
			min-width: 0 !important;
			width: auto;
		}
		.termos-opcoes-linha select.form-control,
		.termos-opcoes-linha input.form-control {
			min-width: 0;
			width: 100% !important;
		}
	</style>
	<script>
		function termosFiltroMarcar(classe, marcar) {
			$('.' + classe).prop('checked', marcar);
			termosFiltroUniform();
		}
		function termosFiltroUniform() {
			try {
				if (window.jQuery && jQuery.uniform && typeof jQuery.uniform.update === 'function') {
					jQuery.uniform.update();
				}
			} catch (e) {}
		}
	</script>
	";

	$entidades = [];
	$mapaExistentes = [];
	if($temBusca && $filtroModelo > 0){
		$entidades = termos_buscar_entidades([
			"modelo" => $filtroModelo,
			"empresas" => $filtroEmpresas,
			"cargos" => $filtroCargos,
			"setores" => $filtroSetores,
			"status" => $filtroStatus,
			"busca" => $filtroBusca
		]);
		$mapaExistentes = termos_mapa_termos_existentes($filtroModelo, $entidades);
	}

	$validarIcp = strtolower(trim(strval($_POST["validar_icp"] ?? "nao")));
	if($validarIcp !== "sim"){
		$validarIcp = "nao";
	}
	$enviarEmail = strtolower(trim(strval($_POST["enviar_email"] ?? "sim")));
	if($enviarEmail !== "nao"){
		$enviarEmail = "sim";
	}
	$forcar = strtolower(trim(strval($_POST["forcar"] ?? "nao")));
	if($forcar !== "sim"){
		$forcar = "nao";
	}
	$lote = max(1, intval($_POST["tamanho_lote"] ?? 5));

	if($temBusca && $filtroModelo <= 0){
		echo "<div class='alert alert-warning'>Selecione um modelo de termo para listar os funcionários.</div>";
	}

	if($filtroModelo > 0){
		echo "<h3>" . count($entidades) . " funcionário(s) encontrado(s)</h3>";

		if($temBusca && $filtroModelo > 0 && empty($entidades)){
			echo "<div class='alert alert-warning'>Nenhum funcionário encontrado para os filtros selecionados.</div>";
		}

		echo "<div class='col-md-12'><div class='portlet light'><div class='portlet-body'>";

		echo "<div class='row' style='margin-bottom:8px;'>
			<div class='col-sm-12'>
				<button type='button' class='btn btn-default btn-xs' onclick='termosMarcar(true)'>Marcar todos</button>
				<button type='button' class='btn btn-default btn-xs' onclick='termosMarcar(false)'>Desmarcar todos</button>
				<button type='button' class='btn btn-default btn-xs' onclick='termosMarcarSemTermo()'>Marcar somente os sem termo</button>
			</div>
		</div>";

		echo "<div class='table-responsive'><table class='table table-bordered table-striped'>
			<thead><tr><th style='width:30px'></th><th>Matrícula</th><th>Nome</th><th>CPF</th><th>Cargo</th><th>Setor</th><th>Empresa</th><th>Termo</th><th>Prévia</th></tr></thead>
			<tbody>";

		foreach($entidades as $e){
			$eid = intval($e["enti_nb_id"]);
			$nome = termos_h($e["enti_tx_nome"]);
			$cpf = termos_formatar_cpf($e["enti_tx_cpf"]);
			$mat = termos_h($e["enti_tx_matricula"]);
			$cargo = termos_h($e["cargo_nome"] ?? $e["enti_tx_ocupacao"] ?? "");
			$setorNome = termos_h($e["setor_nome"] ?? "");
			$empresaNome = termos_h($e["empresa_nome"] ?? "");
			$email = termos_h($e["enti_tx_email"]);
			$statusTermo = $mapaExistentes[$eid] ?? "";

			$badge = "";
			if($statusTermo !== ""){
				$cor = $statusTermo === "assinado" ? "success" : ($statusTermo === "aguardando_assinatura" ? "warning" : "info");
				$badge = "<span class='label label-{$cor}'>" . termos_h($statusTermo) . "</span>";
			}else{
				$badge = "<span class='label label-default'>sem termo</span>";
			}

			$previewUrl = "preview_termo.php?modelo=" . $filtroModelo;

			echo "<tr>
				<td><input type='checkbox' class='termo-entidade' value='{$eid}' data-nome='" . termos_h($nome) . "'></td>
				<td>{$mat}</td>
				<td>{$nome}</td>
				<td>{$cpf}</td>
				<td>{$cargo}</td>
				<td>{$setorNome}</td>
				<td>{$empresaNome}</td>
				<td>{$badge}</td>
				<td><a href='{$previewUrl}' target='_blank' class='btn btn-xs btn-info' title='Pré-visualizar texto do modelo'><span class='glyphicon glyphicon-eye-open'></span></a></td>
			</tr>";
		}

		echo "</tbody></table></div>";
		echo "</div></div></div>";

		echo "<div class='col-md-12'><div class='portlet light'><div class='portlet-body'>
			<div class='row termos-opcoes-linha'>
				<div class='col-sm-2'>" . combo("Validar ICP", "validar_icp", $validarIcp, 12, ["nao" => "Não", "sim" => "Sim"]) . "</div>
				<div class='col-sm-2'>" . combo("Enviar e-mail", "enviar_email", $enviarEmail, 12, ["sim" => "Sim", "nao" => "Não"]) . "</div>
				<div class='col-sm-2'>" . combo("Forçar regeração", "forcar", $forcar, 12, ["nao" => "Não", "sim" => "Sim"]) . "</div>
				<div class='col-sm-2'>" . campo("Func. por lote", "tamanho_lote", $lote, 12, "MASCARA_NUMERO") . "</div>
				<div class='col-sm-4' style='padding-top:22px;'>
					<button type='button' id='btn_processar' class='btn btn-success btn-block' onclick='termosProcessar()'>
						Gerar Selecionados
					</button>
				</div>
			</div>
			<div class='row'>
				<div class='col-sm-12 text-muted' style='font-size:11px; margin-top:4px; overflow-wrap:break-word; word-wrap:break-word;'>
					<i class='fa fa-info-circle'></i> <b>Assinatura:</b> segue a configuração do <b>Tipo de Documento</b> (Cadastros &gt; Tipo de Documento &gt; Assinatura = Sim/Não). &nbsp;
					<b>Validar ICP:</b> assina o PDF final com certificado digital ICP-Brasil ao concluir as assinaturas. &nbsp;
					<b>Enviar e-mail:</b> envia para cada funcionário o e-mail com o link do documento dele. &nbsp;
					<b>Forçar regeração:</b> Não pula quem já tem termo gerado/assinado; Sim gera de novo (novo PDF e nova assinatura) mesmo para quem já tem. &nbsp;
					<b>Func. por lote:</b> quantos funcionários são processados por requisição.
				</div>
			</div>
			<div id='termos_progresso' style='display:none; margin-top:14px;'>
				<div class='progress' style='margin-bottom:4px;'>
					<div id='termos_barra' class='progress-bar progress-bar-success' style='width:0%'></div>
				</div>
				<div id='termos_progresso_texto' class='text-muted'></div>
				<div id='termos_resumo' style='margin-top:8px; max-height:260px; overflow:auto; font-size:12px;'></div>
			</div>
		</div></div></div>";

		echo "
		<script>
			var TERMOS_MODELO_ID = " . $filtroModelo . ";

			function termosAtualizarUniform() {
				try {
					if (window.jQuery && jQuery.uniform && typeof jQuery.uniform.update === 'function') {
						jQuery.uniform.update();
					}
				} catch (e) {}
			}

			function termosMarcar(marcar) {
				$('.termo-entidade').prop('checked', marcar);
				termosAtualizarUniform();
			}

			function termosMarcarSemTermo() {
				$('.termo-entidade').each(function() {
					var tr = $(this).closest('tr');
					var semTermo = tr.find('.label-default').length > 0;
					$(this).prop('checked', semTermo);
				});
				termosAtualizarUniform();
			}

			function termosProcessar() {
				var ids = [];
				$('.termo-entidade:checked').each(function() { ids.push(parseInt($(this).val(), 10)); });
				if (ids.length === 0) {
					alert('Selecione ao menos um funcionário.');
					return;
				}
				if (TERMOS_MODELO_ID <= 0) {
					alert('Selecione um modelo de termo.');
					return;
				}
				var lote = parseInt($('#tamanho_lote').val() || '5', 10);
				if (isNaN(lote) || lote < 1) { lote = 5; }
				var params = {
					modelo_id: TERMOS_MODELO_ID,
					validar_icp: $('select[name=validar_icp]').val(),
					enviar_email: $('select[name=enviar_email]').val(),
					forcar: $('select[name=forcar]').val()
				};
				$('#btn_processar').prop('disabled', true);
				$('#termos_progresso').show();
				$('#termos_resumo').html('').hide();
				var total = ids.length, feitos = 0, okC = 0, errC = 0;
				var resumo = [];

				function proximo(inicio) {
					if (inicio >= total) {
						$('#termos_barra').css('width', '100%');
						$('#termos_progresso_texto').text('Concluído: ' + total + ' processado(s).');
						var html = resumo.map(function(r) { return r; }).join('<br>');
						$('#termos_resumo').html('<b>Resumo: ' + okC + ' OK | ' + errC + ' erro(s)</b><br>' + html).show();
						$('#btn_processar').prop('disabled', false);
						return;
					}
					var fim = Math.min(inicio + lote, total);
					var chunk = ids.slice(inicio, fim);
					$.ajax({
						url: 'processar_termos.php',
						method: 'POST',
						contentType: 'application/json',
						data: JSON.stringify($.extend({ entidades: chunk }, params)),
						dataType: 'json'
					}).done(function(res) {
						if (res && Array.isArray(res.resultados)) {
							$.each(res.resultados, function(i, r) {
								feitos++;
								if (r.ok) { okC++; } else { errC++; }
								resumo.push((r.ok ? '<span class=\"text-success\">OK</span>' : '<span class=\"text-danger\">ERRO</span>') + ' - ' + (r.msg || ''));
							});
						}
						var pct = Math.round(feitos * 100 / total);
						$('#termos_barra').css('width', pct + '%');
						$('#termos_progresso_texto').text('Processando ' + feitos + ' de ' + total + '...');
						proximo(fim);
					}).fail(function(xhr) {
						feitos += chunk.length;
						errC += chunk.length;
						resumo.push('<span class=\"text-danger\">ERRO</span> - Falha de comunicação no lote ' + fim + ' de ' + total + ' (verifique os logs).');
						var pct = Math.round(feitos * 100 / total);
						$('#termos_barra').css('width', pct + '%');
						proximo(fim);
					});
				}
				proximo(0);
			}
		</script>
		";
	}

	rodape();
}