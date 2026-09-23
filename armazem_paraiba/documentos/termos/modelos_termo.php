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

function index() {
	global $conn;
	termos_ensure_tables($conn);
	termos_verificar_permissao("/documentos/termos/modelos_termo.php");

	$filtroTipoDoc = intval($_POST["tipo_doc"] ?? $_GET["tipo_doc"] ?? 0);

	cabecalho("Modelos de Termos");
	termos_hide_loading();

	$buttons = [
		botao("Novo Modelo", "form", "", "", "", false, "btn btn-primary"),
		botao("Gerar Termos", "irGerar", "", "", "", false, "btn btn-info"),
		botao("Termos Gerados", "irLista", "", "", "", false, "btn btn-info")
	];
	echo abre_form();
	if($filtroTipoDoc > 0){
		echo "<input type='hidden' name='tipo_doc' value='{$filtroTipoDoc}'>";
	}
	echo fecha_form($buttons);

	if($filtroTipoDoc > 0){
		$aTipoF = termos_carregar_tipo($filtroTipoDoc);
		echo "<div class='alert alert-info'>
			<i class='fa fa-filter'></i> Modelos do tipo de documento: <b>" . termos_h($aTipoF["tipo_tx_nome"] ?? "?") . "</b>
			<a href='modelos_termo.php' class='btn btn-xs btn-default' style='margin-left:10px;'>Ver todos</a>
		</div>";
	}

	$whereTipo = "";
	$typesTipo = "";
	$varsTipo = [];
	if($filtroTipoDoc > 0){
		$whereTipo = "WHERE m.mode_nb_tipo_doc = ?";
		$typesTipo = "i";
		$varsTipo = [$filtroTipoDoc];
	}

	$res = query(
		"SELECT m.*, t.tipo_tx_nome, t.tipo_tx_assinatura
		 FROM modelo_termo m
		 LEFT JOIN tipos_documentos t ON t.tipo_nb_id = m.mode_nb_tipo_doc
		 {$whereTipo}
		 ORDER BY m.mode_tx_dataCadastro DESC",
		$typesTipo,
		$varsTipo
	);

	echo "<h3>Modelos Cadastrados</h3>";
	echo "<div class='table-responsive'><table class='table table-bordered table-striped'>";
	echo "<thead><tr><th>ID</th><th>Nome</th><th>Tipo de Documento</th><th>Assinatura</th><th>Status</th><th>Atualizado</th><th>Ações</th></tr></thead>";
	echo "<tbody>";

	if(!$res || mysqli_num_rows($res) == 0){
		echo "<tr><td colspan='7' class='text-center'>Nenhum modelo cadastrado ainda.</td></tr>";
	}

	while($res && ($row = mysqli_fetch_assoc($res))){
		$id = intval($row["mode_nb_id"]);
		$nome = termos_h($row["mode_tx_nome"] ?? "");
		$tipoNome = termos_h($row["tipo_tx_nome"] ?? "—");
		$ass = strtolower(trim(strval($row["tipo_tx_assinatura"] ?? "nao"))) === "sim" ? "Sim" : "Não";
		$status = strtolower(trim(strval($row["mode_tx_status"] ?? "inativo"))) === "ativo" ? "Ativo" : "Inativo";
		$data = date("d/m/Y H:i", strtotime(strval($row["mode_tx_dataAtualiza"] ?? $row["mode_tx_dataCadastro"])));

		$btnStatus = $status === "Ativo"
			? "<form method='post' style='display:inline;'><input type='hidden' name='id' value='{$id}'><input type='hidden' name='status' value='inativo'><input type='hidden' name='acao' value='alterar_status'><button type='submit' class='btn btn-xs btn-warning' title='Desativar'><span class='glyphicon glyphicon-off'></span></button></form>"
			: "<form method='post' style='display:inline;'><input type='hidden' name='id' value='{$id}'><input type='hidden' name='status' value='ativo'><input type='hidden' name='acao' value='alterar_status'><button type='submit' class='btn btn-xs btn-success' title='Ativar'><span class='glyphicon glyphicon-ok'></span></button></form>";

		$previewUrl = "preview_termo.php?modelo=" . $id;
		$nomeJs = json_encode($row["mode_tx_nome"] ?? "", JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		echo "<tr>";
		echo "<td>{$id}</td>";
		echo "<td><b>{$nome}</b></td>";
		echo "<td>{$tipoNome}</td>";
		echo "<td>{$ass}</td>";
		echo "<td>{$status}</td>";
		echo "<td>{$data}</td>";
		echo "<td>
				<form method='post' style='display:inline;'><input type='hidden' name='id' value='{$id}'><input type='hidden' name='acao' value='form'><button type='submit' class='btn btn-xs btn-default' title='Editar'><span class='glyphicon glyphicon-pencil'></span></button></form>
				<a href='{$previewUrl}' target='_blank' class='btn btn-xs btn-info' title='Pré-visualizar'><span class='glyphicon glyphicon-eye-open'></span></a>
				<button type='button' class='btn btn-xs btn-success' onclick='termosModalEnviar({$id}, {$nomeJs})' title='Enviar para Assinatura'><span class='glyphicon glyphicon-send'></span></button>
				<button type='button' class='btn btn-xs btn-default' onclick='termosModalClonar()' title='Clonar modelos de termo'>Clonar</button>
				{$btnStatus}
				<form method='post' style='display:inline;' onsubmit='return confirm(\"Deseja excluir este modelo?\");'><input type='hidden' name='id' value='{$id}'><input type='hidden' name='acao' value='excluir'><button type='submit' class='btn btn-xs btn-danger' title='Excluir'><span class='glyphicon glyphicon-trash'></span></button></form>
			</td>";
		echo "</tr>";
	}

	echo "</tbody></table></div>";

	$modelosCloneHtml = "";
	$resClone = query(
		"SELECT m.mode_nb_id, m.mode_tx_nome, m.mode_tx_status, t.tipo_tx_nome, t.tipo_tx_assinatura
		 FROM modelo_termo m
		 LEFT JOIN tipos_documentos t ON t.tipo_nb_id = m.mode_nb_tipo_doc
		 ORDER BY m.mode_tx_nome ASC"
	);
	if($resClone && mysqli_num_rows($resClone) == 0){
		$modelosCloneHtml = "<div class='text-muted'>Nenhum modelo cadastrado.</div>";
	}
	while($resClone && ($rc = mysqli_fetch_assoc($resClone))){
		$rcId = intval($rc["mode_nb_id"]);
		$rcStatus = strtolower(trim(strval($rc["mode_tx_status"] ?? "inativo"))) === "ativo" ? "" : " (inativo)";
		$modelosCloneHtml .= "<label class='clonar-modelo-item' data-id='{$rcId}' style='font-weight:normal; display:block; margin:2px 0;'>"
			. "<input type='checkbox' class='clonar-modelo' value='{$rcId}'> "
			. termos_h($rc["mode_tx_nome"]) . $rcStatus
			. " <span class='text-muted'>— " . termos_h($rc["tipo_tx_nome"] ?? "sem tipo") . "</span>"
			. "</label>";
	}

	$userId = intval($_SESSION["user_nb_id"] ?? 0);
	$empresaPadrao = 0;
	if($userId > 0){
		$resU = query("SELECT user_nb_empresa FROM user WHERE user_nb_id = ? LIMIT 1", "i", [$userId]);
		if($resU instanceof mysqli_result){
			$rowU = mysqli_fetch_assoc($resU);
			$empresaPadrao = intval($rowU["user_nb_empresa"] ?? 0);
		}
	}

	$empresasHtml = "";
	$resEmp = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
	while($resEmp && ($emp = mysqli_fetch_assoc($resEmp))){
		$checked = intval($emp["empr_nb_id"]) === $empresaPadrao ? "checked" : "";
		$empresasHtml .= "<label class='checkbox-inline' style='margin-left:0; margin-right:14px;'><input type='checkbox' class='termos-empresa' value='" . intval($emp["empr_nb_id"]) . "' {$checked}> " . termos_h($emp["empr_tx_nome"]) . "</label>";
	}

	$optsCargos = "";
	foreach(termos_opcoes_cargos() as $k => $v){
		if($k === ""){
			continue;
		}
		$optsCargos .= "<option value='" . termos_h($k) . "'>" . termos_h($v) . "</option>";
	}
	$optsSetores = "";
	foreach(termos_opcoes_setores() as $k => $v){
		if($k === ""){
			continue;
		}
		$optsSetores .= "<option value='" . termos_h($k) . "'>" . termos_h($v) . "</option>";
	}
	$cargosHtml = "";
	foreach(termos_opcoes_cargos() as $k => $v){
		if($k === ""){
			continue;
		}
		$cargosHtml .= "<label style='font-weight:normal; margin:0 12px 2px 0; display:inline-block;'><input type='checkbox' class='termos-cargo' value='" . intval($k) . "'> " . termos_h($v) . "</label>";
	}
	$setoresHtml = "";
	foreach(termos_opcoes_setores() as $k => $v){
		if($k === ""){
			continue;
		}
		$setoresHtml .= "<label style='font-weight:normal; margin:0 12px 2px 0; display:inline-block;'><input type='checkbox' class='termos-setor' value='" . intval($k) . "'> " . termos_h($v) . "</label>";
	}

	echo "
	<style>
		.termos-ajuda { overflow-wrap: break-word; word-wrap: break-word; }
		#termos_modal_titulo { overflow-wrap: break-word; word-wrap: break-word; }
		#termos_modal_empresas label { overflow-wrap: break-word; word-wrap: break-word; }
		.termos-form-acoes .form-actions { text-align: center; }
		.termos-form-acoes .fecha-form-btn { display: inline-block; width: auto; margin: 6px 8px; }
	</style>
	<div class='modal fade' id='modal_enviar_assinatura' tabindex='-1' role='dialog'>
		<div class='modal-dialog modal-lg' role='document'>
			<div class='modal-content'>
				<div class='modal-header'>
					<button type='button' class='close' data-dismiss='modal'>&times;</button>
					<h4 class='modal-title' id='termos_modal_titulo'>Enviar para Assinatura</h4>
				</div>
				<div class='modal-body'>
					<div class='row'>
						<div class='col-sm-12' style='margin-bottom:10px;'>
							<b>Empresas</b> <span class='text-muted'>(a da sua sessão já vem marcada — pode marcar várias)</span>
							<div style='margin:4px 0; display:flex; flex-wrap:wrap; gap:4px; align-items:center;'>
								<button type='button' class='btn btn-default btn-xs' onclick='termosModalEmpresasMarcar(true)'>Marcar todas</button>
								<button type='button' class='btn btn-default btn-xs' onclick='termosModalEmpresasMarcar(false)'>Desmarcar todas</button>
								<span style='margin-left:10px;'>Selecionar as</span>
								<input type='number' id='termos_modal_qtd_empresas' class='form-control input-sm' style='display:inline-block; width:60px;' min='1' placeholder='N'>
								<span>primeiras</span>
								<button type='button' class='btn btn-default btn-xs' onclick='termosModalEmpresasSelecionarQtd()'>Aplicar</button>
							</div>
							<div id='termos_modal_empresas'>{$empresasHtml}</div>
						</div>
					</div>
					<div class='row' style='margin-bottom:8px;'>
						<div class='col-sm-4'>
							<b>Cargo</b>
							<div style='margin:2px 0;'>
								<button type='button' class='btn btn-default btn-xs' onclick='termosModalCargosMarcar(true)'>Marcar todos</button>
								<button type='button' class='btn btn-default btn-xs' onclick='termosModalCargosMarcar(false)'>Desmarcar todos</button>
							</div>
							<div id='termos_modal_cargos' style='max-height:120px; overflow-y:auto; border:1px solid #eee; padding:4px; background:#fcfcfc;'>
								{$cargosHtml}
							</div>
						</div>
						<div class='col-sm-4'>
							<b>Setor</b>
							<div style='margin:2px 0;'>
								<button type='button' class='btn btn-default btn-xs' onclick='termosModalSetoresMarcar(true)'>Marcar todos</button>
								<button type='button' class='btn btn-default btn-xs' onclick='termosModalSetoresMarcar(false)'>Desmarcar todos</button>
							</div>
							<div id='termos_modal_setores' style='max-height:120px; overflow-y:auto; border:1px solid #eee; padding:4px; background:#fcfcfc;'>
								{$setoresHtml}
							</div>
						</div>
						<div class='col-sm-4'>
							<b>Buscar funcionário</b>
							<input id='termos_modal_busca' class='form-control input-sm' placeholder='Nome, matrícula ou CPF'>
							<div class='text-muted' style='font-size:11px; margin-top:2px;'>Vazio = todos os funcionários das empresas selecionadas.</div>
							<div style='margin-top:6px;'>
								<button type='button' class='btn btn-default btn-xs' onclick='termosModalMarcarTodos(true)'>Marcar todos</button>
								<button type='button' class='btn btn-default btn-xs' onclick='termosModalMarcarTodos(false)'>Desmarcar todos</button>
								<button type='button' class='btn btn-default btn-xs' onclick='termosModalMarcarSemTermo()'>Somente sem termo</button>
							</div>
						</div>
					</div>
					<div id='termos_modal_lista' style='min-height:60px;'>
						<div class='text-muted'>Selecione uma ou mais empresas para carregar os funcionários.</div>
					</div>
					<div class='row' style='margin-top:10px;'>
						<div class='col-sm-3'>
							<label class='control-label'>Validar ICP</label>
							<select id='termos_modal_icp' class='form-control input-sm'>
								<option value='nao'>Não</option>
								<option value='sim'>Sim</option>
							</select>
							<span class='help-block termos-ajuda' style='font-size:11px; margin-bottom:0;'>Aplica assinatura digital ICP-Brasil no PDF final ao concluir as assinaturas.</span>
						</div>
						<div class='col-sm-3'>
							<label class='control-label'>Enviar e-mail</label>
							<select id='termos_modal_email' class='form-control input-sm'>
								<option value='sim'>Sim</option>
								<option value='nao'>Não</option>
							</select>
							<span class='help-block termos-ajuda' style='font-size:11px; margin-bottom:0;'>Envia o e-mail com o link de assinatura para cada funcionário.</span>
						</div>
						<div class='col-sm-3'>
							<label class='control-label'>Forçar regeração</label>
							<select id='termos_modal_forcar' class='form-control input-sm'>
								<option value='nao'>Não</option>
								<option value='sim'>Sim</option>
							</select>
							<span class='help-block termos-ajuda' style='font-size:11px; margin-bottom:0;'>Não: pula quem já tem termo. Sim: gera de novo (novo PDF e nova assinatura).</span>
						</div>
						<div class='col-sm-3' style='padding-top:28px;'>
							<b id='termos_modal_selecionados'>0 selecionado(s)</b>
							<span class='help-block termos-ajuda' style='font-size:11px; margin-bottom:0;'>Badge mostra o status do termo de cada funcionário.</span>
						</div>
					</div>
					<div id='termos_modal_progresso' style='display:none; margin-top:12px;'>
						<div class='progress' style='margin-bottom:4px;'>
							<div id='termos_modal_barra' class='progress-bar progress-bar-success' style='width:0%'></div>
						</div>
						<div id='termos_modal_texto' class='text-muted'></div>
						<div id='termos_modal_resultado' style='margin-top:8px; max-height:200px; overflow:auto; font-size:12px;'></div>
					</div>
				</div>
				<div class='modal-footer'>
					<button type='button' class='btn btn-default' data-dismiss='modal'>Fechar</button>
					<button type='button' id='termos_modal_btn_enviar' class='btn btn-success' onclick='termosModalEnviarAssinatura()'>
						Enviar para Assinatura
					</button>
				</div>
			</div>
		</div>
	</div>

	<div class='modal fade' id='modal_clonar_modelos' tabindex='-1' role='dialog'>
		<div class='modal-dialog' role='document'>
			<div class='modal-content'>
				<div class='modal-header'>
					<button type='button' class='close' data-dismiss='modal'>&times;</button>
					<h4 class='modal-title'>Clonar Modelos de Termo</h4>
				</div>
				<div class='modal-body'>
					<div class='alert alert-info' style='padding:8px 12px; font-size:12px;'>
						<i class='fa fa-copy'></i> Selecione um ou mais modelos para criar cópias. A cópia preserva o texto, o tipo de documento e os assinantes.
					</div>
					<div style='margin:4px 0;'>
						<button type='button' class='btn btn-default btn-xs' onclick='termosModalClonarMarcar(true)'>Marcar todos</button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosModalClonarMarcar(false)'>Desmarcar todos</button>
					</div>
					<div id='clonar_modelos_lista' style='max-height:320px; overflow-y:auto; border:1px solid #eee; padding:6px; background:#fcfcfc;'>
						{$modelosCloneHtml}
					</div>
				</div>
				<div class='modal-footer'>
					<button type='button' class='btn btn-default' data-dismiss='modal'>Fechar</button>
					<button type='button' class='btn btn-success' onclick='termosModalClonarEnviar()'>Clonar Selecionados</button>
				</div>
			</div>
		</div>
	</div>

	<script>
		var TERMOS_MODAL_MODELO = 0;

		function termosEscapeHtml(s) {
			return $('<div>').text(s || '').html();
		}

		function termosModalEnviar(id, nome) {
			TERMOS_MODAL_MODELO = id;
			$('#termos_modal_titulo').text('Enviar para Assinatura — ' + nome);
			$('#termos_modal_progresso').hide();
			$('#termos_modal_resultado').html('').hide();
			$('#termos_modal_lista').html('<div class=\"text-muted\">Selecione uma ou mais empresas para carregar os funcionários.</div>');
			termosModalAtualizarContagem();
			$('#modal_enviar_assinatura').modal('show');
			termosCarregarFuncionarios();
		}

		function termosModalEmpresasSelecionadas() {
			var empresas = [];
			$('#termos_modal_empresas .termos-empresa:checked').each(function() {
				empresas.push(parseInt($(this).val(), 10));
			});
			return empresas;
		}

function termosModalCargosSelecionados() {
				var cargos = [];
				$('#termos_modal_cargos .termos-cargo:checked').each(function() {
					cargos.push(parseInt($(this).val(), 10));
				});
				return cargos;
			}

			function termosModalSetoresSelecionados() {
				var setores = [];
				$('#termos_modal_setores .termos-setor:checked').each(function() {
					setores.push(parseInt($(this).val(), 10));
				});
				return setores;
			}

			function termosModalCargosMarcar(marcar) {
				$('#termos_modal_cargos .termos-cargo').prop('checked', marcar);
				termosModalAtualizarUniform();
				termosCarregarFuncionarios();
			}

			function termosModalSetoresMarcar(marcar) {
				$('#termos_modal_setores .termos-setor').prop('checked', marcar);
				termosModalAtualizarUniform();
				termosCarregarFuncionarios();
			}

			function termosCarregarFuncionarios() {
				var empresas = termosModalEmpresasSelecionadas();
				if (empresas.length === 0) {
					$('#termos_modal_lista').html('<div class=\"text-muted\">Nenhuma empresa selecionada.</div>');
					termosModalAtualizarContagem();
					return;
				}
				var marcados = {};
				$('#termos_modal_lista .termos-func:checked').each(function() {
					marcados[$(this).val()] = true;
				});
				$('#termos_modal_lista').html('<div class=\"text-muted\">Carregando funcionários...</div>');
				$.ajax({
					url: 'buscar_funcionarios.php',
					method: 'POST',
					contentType: 'application/json',
					data: JSON.stringify({
						empresas: empresas,
						cargos: termosModalCargosSelecionados(),
						setores: termosModalSetoresSelecionados(),
						busca: $('#termos_modal_busca').val() || '',
						modelo: TERMOS_MODAL_MODELO
					}),
					dataType: 'json'
				}).done(function(res) {
				if (!res || !res.ok) {
					$('#termos_modal_lista').html('<div class=\"alert alert-danger\">Falha ao carregar funcionários.</div>');
					return;
				}
				var html = '<div class=\"text-muted\" style=\"margin-bottom:4px;\">' + res.total + ' funcionário(s).</div>';
				html += '<div class=\"table-responsive\" style=\"max-height:320px; overflow:auto;\"><table class=\"table table-bordered table-striped table-condensed\">';
				html += '<thead><tr><th style=\"width:30px\"></th><th>Matrícula</th><th>Nome</th><th>CPF</th><th>Cargo</th><th>Setor</th><th>Empresa</th><th>Termo</th></tr></thead><tbody>';
				$.each(res.funcionarios, function(i, f) {
					var badge = '';
					if (f.status_termo) {
						var cor = f.status_termo === 'assinado' ? 'success' : (f.status_termo === 'aguardando_assinatura' ? 'warning' : 'info');
						badge = '<span class=\"label label-' + cor + '\">' + termosEscapeHtml(f.status_termo) + '</span>';
					} else {
						badge = '<span class=\"label label-default\">sem termo</span>';
					}
					var checked = marcados[f.enti_nb_id] ? 'checked' : '';
					html += '<tr><td><input type=\"checkbox\" class=\"termos-func\" value=\"' + f.enti_nb_id + '\" ' + checked + '></td>'
						+ '<td>' + termosEscapeHtml(f.matricula) + '</td>'
						+ '<td>' + termosEscapeHtml(f.nome) + '</td>'
						+ '<td>' + termosEscapeHtml(f.cpf) + '</td>'
						+ '<td>' + termosEscapeHtml(f.cargo) + '</td>'
						+ '<td>' + termosEscapeHtml(f.setor) + '</td>'
						+ '<td>' + termosEscapeHtml(f.empresa) + '</td>'
						+ '<td>' + badge + '</td></tr>';
				});
				html += '</tbody></table></div>';
				$('#termos_modal_lista').html(html);
				termosModalAtualizarUniform();
				termosModalAtualizarContagem();
			}).fail(function() {
				$('#termos_modal_lista').html('<div class=\"alert alert-danger\">Falha de comunicação ao carregar funcionários.</div>');
			});
		}

		function termosModalAtualizarContagem() {
			$('#termos_modal_selecionados').text($('#termos_modal_lista .termos-func:checked').length + ' selecionado(s)');
		}

		function termosModalAtualizarUniform() {
			try {
				if (window.jQuery && jQuery.uniform && typeof jQuery.uniform.update === 'function') {
					jQuery.uniform.update();
				}
			} catch (e) {}
		}

		function termosModalEmpresasMarcar(marcar) {
			$('#termos_modal_empresas .termos-empresa').prop('checked', marcar);
			termosModalAtualizarUniform();
			termosCarregarFuncionarios();
		}

		function termosModalEmpresasSelecionarQtd() {
			var qtd = parseInt($('#termos_modal_qtd_empresas').val() || '0', 10);
			if (isNaN(qtd) || qtd < 1) {
				alert('Informe a quantidade de empresas.');
				return;
			}
			var boxes = $('#termos_modal_empresas .termos-empresa');
			boxes.prop('checked', false);
			boxes.slice(0, qtd).prop('checked', true);
			termosModalAtualizarUniform();
			termosCarregarFuncionarios();
		}

		function termosModalMarcarTodos(marcar) {
			$('#termos_modal_lista .termos-func').prop('checked', marcar);
			termosModalAtualizarUniform();
			termosModalAtualizarContagem();
		}

		function termosModalMarcarSemTermo() {
			$('#termos_modal_lista .termos-func').each(function() {
				var tr = $(this).closest('tr');
				$(this).prop('checked', tr.find('.label-default').length > 0);
			});
			termosModalAtualizarUniform();
			termosModalAtualizarContagem();
		}

		function termosModalEnviarAssinatura() {
			var ids = [];
			$('#termos_modal_lista .termos-func:checked').each(function() {
				ids.push(parseInt($(this).val(), 10));
			});
			if (TERMOS_MODAL_MODELO <= 0) {
				alert('Modelo inválido.');
				return;
			}
			if (ids.length === 0) {
				alert('Selecione ao menos um funcionário.');
				return;
			}
			$('#termos_modal_btn_enviar').prop('disabled', true);
			$('#termos_modal_progresso').show();
			var params = {
				modelo_id: TERMOS_MODAL_MODELO,
				enviar_assinatura: 'sim',
				validar_icp: $('#termos_modal_icp').val(),
				enviar_email: $('#termos_modal_email').val(),
				forcar: $('#termos_modal_forcar').val()
			};
			var total = ids.length, feitos = 0, okC = 0, errC = 0, resumo = [];
			function proximo(inicio) {
				if (inicio >= total) {
					$('#termos_modal_barra').css('width', '100%');
					$('#termos_modal_texto').text('Concluído: ' + total + ' processado(s).');
					$('#termos_modal_resultado').html('<b>Resumo: ' + okC + ' OK | ' + errC + ' erro(s)</b><br>' + resumo.join('<br>')).show();
					$('#termos_modal_btn_enviar').prop('disabled', false);
					termosCarregarFuncionarios();
					return;
				}
				var fim = Math.min(inicio + 5, total);
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
							resumo.push((r.ok ? 'OK' : 'ERRO') + ' - ' + (r.msg || ''));
						});
					}
					$('#termos_modal_barra').css('width', Math.round(feitos * 100 / total) + '%');
					$('#termos_modal_texto').text('Processando ' + feitos + ' de ' + total + '...');
					proximo(fim);
				}).fail(function() {
					feitos += chunk.length;
					errC += chunk.length;
					resumo.push('ERRO - falha de comunicação no lote ' + fim + ' de ' + total);
					proximo(fim);
				});
			}
			proximo(0);
		}

		function termosModalClonar() {
			$('#clonar_modelos_lista .clonar-modelo-item').show();
			$('#clonar_modelos_lista .clonar-modelo').prop('checked', false);
			termosModalAtualizarUniform();
			$('#modal_clonar_modelos').modal('show');
		}

		function termosModalClonarMarcar(marcar) {
			$('#clonar_modelos_lista .clonar-modelo:visible').prop('checked', marcar);
			termosModalAtualizarUniform();
		}

		function termosModalClonarEnviar() {
			var ids = [];
			$('#clonar_modelos_lista .clonar-modelo:checked').each(function() {
				ids.push(parseInt($(this).val(), 10));
			});
			if (ids.length === 0) {
				alert('Selecione ao menos um modelo para clonar.');
				return;
			}
			var form = document.createElement('form');
			form.method = 'post';
			form.action = 'modelos_termo.php';
			for (var i = 0; i < ids.length; i++) {
				var inp = document.createElement('input');
				inp.type = 'hidden';
				inp.name = 'ids[]';
				inp.value = ids[i];
				form.appendChild(inp);
			}
			var acao = document.createElement('input');
			acao.type = 'hidden';
			acao.name = 'acao';
			acao.value = 'clonar_modelos';
			form.appendChild(acao);
			document.body.appendChild(form);
			form.submit();
		}

		$(function() {
			$('#termos_modal_empresas').on('change', '.termos-empresa', termosCarregarFuncionarios);
			$('#termos_modal_cargos').on('change', '.termos-cargo', termosCarregarFuncionarios);
			$('#termos_modal_setores').on('change', '.termos-setor', termosCarregarFuncionarios);
			$('#termos_modal_lista').on('change', '.termos-func', termosModalAtualizarContagem);
			var termosTimerBusca = null;
			$('#termos_modal_busca').on('input', function() {
				clearTimeout(termosTimerBusca);
				termosTimerBusca = setTimeout(termosCarregarFuncionarios, 350);
			});
		});
	</script>
	";

	rodape();
}

function irGerar() {
	header("Location: gerar_termos.php");
	exit;
}

function irLista() {
	header("Location: listar_termos.php");
	exit;
}

function form() {
	global $conn;
	termos_ensure_tables($conn);
	termos_verificar_permissao("/documentos/termos/modelos_termo.php");

	$id = intval($_POST["id"] ?? 0);
	$a_mod = [];
	if($id > 0){
		$a_mod = termos_carregar_modelo($id);
		if(empty($a_mod)){
			set_status("ERRO: Modelo não encontrado.");
			index();
			exit;
		}
		$_POST = array_merge($_POST, $a_mod);
	}

	cabecalho($id > 0 ? "Editar Modelo de Termo" : "Novo Modelo de Termo");
	termos_hide_loading();

	$tipos = ["" => "Selecione..."];
	$resTipos = query("SELECT tipo_nb_id, tipo_tx_nome, tipo_tx_assinatura FROM tipos_documentos WHERE tipo_tx_status = 'ativo' ORDER BY tipo_tx_nome ASC");
	while($resTipos && ($t = mysqli_fetch_assoc($resTipos))){
		$suf = strtolower(trim(strval($t["tipo_tx_assinatura"] ?? "nao"))) === "sim" ? " (com assinatura)" : "";
		$tipos[$t["tipo_nb_id"]] = $t["tipo_tx_nome"] . $suf;
	}

	$conteudo = termos_sanitizar_html(strval($a_mod["mode_tx_conteudo"] ?? ""));

	$placeholdersHtml = "";
	foreach(termos_lista_placeholders() as $token => $desc){
		$placeholdersHtml .= "<option value=\"" . termos_h($token) . "\">" . termos_h($token . " — " . $desc) . "</option>";
	}

	$tipoDocPadrao = strval($a_mod["mode_nb_tipo_doc"] ?? "");
	if($id <= 0 && $tipoDocPadrao === ""){
		$tipoDocPadrao = strval($_POST["tipo_doc"] ?? $_GET["tipo_doc"] ?? "");
	}

	$fields = [
		"<input type='hidden' name='id' value='{$id}'>",
		campo("Nome do Modelo*", "nome", strval($a_mod["mode_tx_nome"] ?? ""), 5),
		combo("Tipo de Documento*", "tipo_doc", $tipoDocPadrao, 4, $tipos),
		combo("Status", "status", strval($a_mod["mode_tx_status"] ?? "ativo"), 2, ["ativo" => "Ativo", "inativo" => "Inativo"])
	];

	$buttons = [
		botao("Gravar Modelo", "salvar", "", "", "", false, "btn btn-success"),
		botao("Voltar", "index", "", "", "", false, "btn btn-secondary")
	];

	echo "
	<style>
		.termos-form-acoes .form-actions { text-align: center; }
		.termos-form-acoes .fecha-form-btn { display: inline-block; width: auto; margin: 6px 8px; }
	</style>
	";

	echo abre_form("Dados do Modelo");
	echo "<input type='hidden' name='id' value='{$id}'>";
	echo linha_form($fields);

	echo "<br>";

	$editorHtml = "
	<div class='row'>
	<div class='col-md-12'>
		<div class='portlet light'>
			<div class='portlet-title'><span class='caption-subject font-dark bold uppercase'>Texto Padrão do Documento</span></div>
			<div class='portlet-body'>
				<p class='text-muted'>Escreva o texto padrão do documento e insira os campos pelo menu abaixo. <b>O modelo é salvo com os placeholders</b> (ex.: <code>{{funcionario_nome}}</code>) — os dados de cada funcionário são preenchidos automaticamente na hora de gerar. Para conferir, use a Pré-visualização (escolhe o funcionário de amostra).</p>
				<div class='row' style='margin-bottom:6px;'>
					<div class='col-sm-12' style='display:flex; flex-wrap:wrap; gap:4px; align-items:center;'>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"bold\")' title='Negrito'><b>B</b></button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"italic\")' title='Itálico'><i>I</i></button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"underline\")' title='Sublinhado'><u>U</u></button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"strikeThrough\")' title='Riscado'><s>S</s></button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"justifyLeft\")' title='Esquerda'><span class='glyphicon glyphicon-align-left'></span></button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"justifyCenter\")' title='Centralizar'><span class='glyphicon glyphicon-align-center'></span></button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"justifyRight\")' title='Direita'><span class='glyphicon glyphicon-align-right'></span></button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"justifyFull\")' title='Justificado'><span class='glyphicon glyphicon-align-justify'></span></button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"insertUnorderedList\")' title='Lista'>&bull; Lista</button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"insertOrderedList\")' title='Lista numerada'>1. Lista</button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"formatBlock\", \"h2\")' title='Título'>Título</button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"formatBlock\", \"p\")' title='Parágrafo'>&para;</button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"undo\")' title='Desfazer'>&#8630;</button>
						<button type='button' class='btn btn-default btn-xs' onclick='termosExec(\"redo\")' title='Refazer'>&#8631;</button>
						<select id='termos_placeholders' class='form-control input-sm' style='display:inline-block;width:auto;max-width:380px;' onchange='termosInserirPlaceholder(this)'>
							<option value=''>Inserir campo do funcionário...</option>
							{$placeholdersHtml}
						</select>
					</div>
				</div>
				<div id='editor_conteudo' contenteditable='true' style='border:1px solid #ccc; min-height:420px; padding:14px; background:#fff; overflow:auto; font-size:14px; line-height:1.6;'>{$conteudo}</div>
				<textarea name='conteudo' id='conteudo' style='display:none;'></textarea>
			</div>
		</div>
	</div>
	</div>";

	echo $editorHtml;

	echo "<div class='termos-form-acoes'>" . fecha_form($buttons) . "</div>";

	echo "
	<script>
		var TERMOS_CONTEUDO = " . json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";

		function termosExec(cmd, arg) {
			document.execCommand(cmd, false, arg || null);
			document.getElementById('editor_conteudo').focus();
		}

		function termosInserirPlaceholder(sel) {
			var v = sel.value;
			if (v === '') { return; }
			document.execCommand('insertText', false, v);
			sel.value = '';
		}

		$(function() {
			var termosEditor = document.getElementById('editor_conteudo');
			termosEditor.innerHTML = TERMOS_CONTEUDO;
			var termosSync = function() {
				document.getElementById('conteudo').value = termosEditor.innerHTML;
			};
			termosEditor.addEventListener('input', termosSync);
			$('form[name=contex_form]').on('submit', termosSync);
		});
	</script>
	";

	rodape();
}

function salvar() {
	global $conn;
	termos_ensure_tables($conn);
	termos_verificar_permissao("/documentos/termos/modelos_termo.php");

	$id = intval($_POST["id"] ?? 0);
	$nome = trim(strval($_POST["nome"] ?? ""));
	$tipoDoc = intval($_POST["tipo_doc"] ?? 0);
	$status = in_array(strval($_POST["status"] ?? "ativo"), ["ativo", "inativo"], true) ? strval($_POST["status"]) : "ativo";
	$conteudo = termos_sanitizar_html(strval($_POST["conteudo"] ?? ""));
	$user = intval($_SESSION["user_nb_id"] ?? 0);

	if($nome === ""){
		set_status("ERRO: Informe o nome do modelo.");
		$_POST["id"] = $id;
		form();
		exit;
	}
	if($tipoDoc <= 0){
		set_status("ERRO: Selecione o tipo de documento.");
		$_POST["id"] = $id;
		form();
		exit;
	}

	$tipo = termos_carregar_tipo($tipoDoc);
	if(empty($tipo) || strtolower(trim(strval($tipo["tipo_tx_status"] ?? "inativo"))) !== "ativo"){
		set_status("ERRO: Tipo de documento inválido.");
		$_POST["id"] = $id;
		form();
		exit;
	}

	if($id > 0){
		termos_executar(
			"UPDATE modelo_termo SET mode_tx_nome = ?, mode_nb_tipo_doc = ?, mode_tx_conteudo = ?, mode_tx_status = ?, mode_nb_userAtualiza = ?, mode_tx_dataAtualiza = NOW() WHERE mode_nb_id = ?",
			"sisssi",
			[$nome, $tipoDoc, $conteudo, $status, $user, $id]
		);
		termos_log("modelo_atualizado", "Modelo #{$id} atualizado", ["nome" => $nome]);
	}else{
		$id = termos_inserir_id(
			"INSERT INTO modelo_termo (mode_tx_nome, mode_nb_tipo_doc, mode_tx_conteudo, mode_tx_status, mode_nb_userCadastro) VALUES (?, ?, ?, ?, ?)",
			"sissi",
			[$nome, $tipoDoc, $conteudo, $status, $user]
		);
		termos_log("modelo_criado", "Modelo #{$id} criado", ["nome" => $nome]);
	}

	set_status("Modelo gravado com sucesso!");
	index();
	exit;
}

function alterar_status() {
	global $conn;
	termos_ensure_tables($conn);
	termos_verificar_permissao("/documentos/termos/modelos_termo.php");

	$id = intval($_POST["id"] ?? 0);
	$status = in_array(strval($_POST["status"] ?? ""), ["ativo", "inativo"], true) ? strval($_POST["status"]) : "ativo";
	if($id > 0){
		termos_executar("UPDATE modelo_termo SET mode_tx_status = ?, mode_nb_userAtualiza = ?, mode_tx_dataAtualiza = NOW() WHERE mode_nb_id = ?", "sii", [$status, intval($_SESSION["user_nb_id"] ?? 0), $id]);
		termos_log("modelo_status", "Modelo #{$id} → " . $status);
		set_status("Status do modelo atualizado.");
	}
	index();
	exit;
}

function excluir() {
	global $conn;
	termos_ensure_tables($conn);
	termos_verificar_permissao("/documentos/termos/modelos_termo.php");

	$id = intval($_POST["id"] ?? 0);
	if($id > 0){
		termos_executar("DELETE FROM modelo_termo_assinante WHERE moas_nb_modelo = ?", "i", [$id]);
		termos_executar("DELETE FROM modelo_termo WHERE mode_nb_id = ?", "i", [$id]);
		termos_log("modelo_excluido", "Modelo #{$id} excluído permanentemente");
		set_status("Modelo excluído com sucesso!");
	}
	index();
	exit;
}

function termos_nome_clonado(string $nome): string {
	$base = trim($nome);
	if($base === ""){
		$base = "Modelo";
	}
	$candidato = $base . " (cópia)";
	$i = 2;
	while(true){
		$res = query("SELECT mode_nb_id FROM modelo_termo WHERE mode_tx_nome = ? LIMIT 1", "s", [$candidato]);
		if(!($res instanceof mysqli_result) || mysqli_num_rows($res) == 0){
			break;
		}
		$candidato = $base . " (cópia " . $i . ")";
		$i++;
	}
	return $candidato;
}

function clonar_modelos() {
	global $conn;
	termos_ensure_tables($conn);
	termos_verificar_permissao("/documentos/termos/modelos_termo.php");

	$ids = $_POST["ids"] ?? [];
	if(!is_array($ids)){
		$ids = [];
	}
	$ids = array_values(array_filter(array_map("intval", $ids)));
	$user = intval($_SESSION["user_nb_id"] ?? 0);
	$clonados = 0;

	foreach($ids as $id){
		$origem = termos_carregar_modelo($id);
		if(empty($origem)){
			continue;
		}
		$nomeNovo = termos_nome_clonado(strval($origem["mode_tx_nome"] ?? ""));
		$novoId = termos_inserir_id(
			"INSERT INTO modelo_termo (mode_tx_nome, mode_nb_tipo_doc, mode_tx_conteudo, mode_tx_denominacao, mode_tx_cidade_assinatura, mode_tx_status, mode_nb_userCadastro) VALUES (?, ?, ?, ?, ?, 'ativo', ?)",
			"sisssi",
			[
				$nomeNovo,
				intval($origem["mode_nb_tipo_doc"] ?? 0),
				strval($origem["mode_tx_conteudo"] ?? ""),
				strval($origem["mode_tx_denominacao"] ?? ""),
				strval($origem["mode_tx_cidade_assinatura"] ?? ""),
				$user
			]
		);
		if($novoId <= 0){
			continue;
		}

		$assinantes = termos_carregar_assinantes($id);
		foreach($assinantes as $as){
			$entiOrigem = intval($as["moas_nb_entidade"] ?? 0);
			termos_executar(
				"INSERT INTO modelo_termo_assinante (moas_nb_modelo, moas_tx_tipo, moas_tx_funcao, moas_nb_ordem, moas_nb_entidade, moas_tx_nome, moas_tx_email) VALUES (?, ?, ?, ?, ?, ?, ?)",
				"issiiss",
				[
					$novoId,
					strval($as["moas_tx_tipo"] ?? "funcionario"),
					strval($as["moas_tx_funcao"] ?? "Signatário"),
					intval($as["moas_nb_ordem"] ?? 1),
					$entiOrigem > 0 ? $entiOrigem : null,
					strval($as["moas_tx_nome"] ?? ""),
					strval($as["moas_tx_email"] ?? "")
				]
			);
		}

		termos_log("modelo_clonado", "Modelo #{$id} clonado para #{$novoId}", ["nome" => $nomeNovo]);
		$clonados++;
	}

	set_status($clonados > 0 ? "{$clonados} modelo(s) clonado(s) com sucesso!" : "Nenhum modelo foi clonado.");
	index();
	exit;
}