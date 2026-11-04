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
	if(termos_pode_acessar("/documentos/pop/index.php")){
		$buttons[] = "<a href='../pop/index.php' class='btn btn-success'><span class='fa fa-book'></span> Biblioteca de POPs</a>";
	}
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

	$temCelular = termos_tabela_ativo_existe();
	$vinculosPorModelo = [];
	if($temCelular && termos_coluna_link_termo_existe()){
		$resVinc = query(
			"SELECT c.celu_nb_modelo_termo, c.celu_tx_nome, c.celu_tx_imei, e.enti_tx_nome AS responsavel
			 FROM celular c
			 LEFT JOIN entidade e ON e.enti_nb_id = c.celu_nb_entidade
			 WHERE c.celu_nb_modelo_termo IS NOT NULL AND c.celu_nb_modelo_termo > 0
			 ORDER BY c.celu_tx_nome ASC"
		);
		while($resVinc && ($rv = mysqli_fetch_assoc($resVinc))){
			$vinculosPorModelo[intval($rv["celu_nb_modelo_termo"])][] = $rv;
		}
	}

	$res = query(
		"SELECT m.*, t.tipo_tx_nome, t.tipo_tx_assinatura, t.tipo_tx_ativo, g.grup_tx_nome AS pop_setor_nome
		 FROM modelo_termo m
		 LEFT JOIN tipos_documentos t ON t.tipo_nb_id = m.mode_nb_tipo_doc
		 LEFT JOIN grupos_documentos g ON g.grup_nb_id = m.mode_nb_pop_setor
		 {$whereTipo}
		 ORDER BY m.mode_tx_dataCadastro DESC",
		$typesTipo,
		$varsTipo
	);

	echo "<h3>Modelos Cadastrados</h3>";
	echo "<div class='table-responsive'><table class='table table-bordered table-striped'>";
	echo "<thead><tr><th>ID</th><th>Nome</th><th>POP</th><th>Tipo de Documento</th><th>Equipamentos</th><th>Tipo exige assinatura</th><th>Status</th><th>Atualizado</th><th>Ações</th></tr></thead>";
	echo "<tbody>";

	if(!$res || mysqli_num_rows($res) == 0){
		echo "<tr><td colspan='9' class='text-center'>Nenhum modelo cadastrado ainda.</td></tr>";
	}

	while($res && ($row = mysqli_fetch_assoc($res))){
		$id = intval($row["mode_nb_id"]);
		$nome = termos_h($row["mode_tx_nome"] ?? "");
		$tipoNome = termos_h($row["tipo_tx_nome"] ?? "—");
		$ass = strtolower(trim(strval($row["tipo_tx_assinatura"] ?? "nao"))) === "sim" ? "Sim" : "Não";
		$status = strtolower(trim(strval($row["mode_tx_status"] ?? "inativo"))) === "ativo" ? "Ativo" : "Inativo";
		$data = date("d/m/Y H:i", strtotime(strval($row["mode_tx_dataAtualiza"] ?? $row["mode_tx_dataCadastro"])));
		$ehPop = strtolower(trim(strval($row["mode_tx_pop"] ?? "nao"))) === "sim";
		$popExibe = "<span class='text-muted'>—</span>";
		if($ehPop){
			$setorPop = trim(strval($row["pop_setor_nome"] ?? ""));
			$versaoPop = trim(strval($row["mode_tx_pop_versao"] ?? ""));
			$popExibe = "<span class='label label-success'><span class='fa fa-book'></span> POP</span>";
			if($setorPop !== ""){
				$popExibe .= "<br><small class='text-muted'>" . termos_h($setorPop) . "</small>";
			}
			if($versaoPop !== ""){
				$popExibe .= " <small class='text-muted'>v" . termos_h($versaoPop) . "</small>";
			}
		}

		$ativoExibe = "<span class='text-muted'>—</span>";
		$vinc = $vinculosPorModelo[$id] ?? [];
		if(!empty($vinc)){
			$nomesVinc = [];
			foreach($vinc as $v){
				$nomeEq = trim(strval($v["celu_tx_nome"] ?? ""));
				if($nomeEq === ""){
					$nomeEq = "Equipamento";
				}
				$respEq = trim(strval($v["responsavel"] ?? ""));
				$nomesVinc[] = $nomeEq . ($respEq !== "" ? " (" . $respEq . ")" : "");
			}
			$ativoExibe = "<span class='label label-info'>" . count($vinc) . "</span> "
				. "<span style='display:inline-block; max-width:280px; overflow-wrap:break-word;'>" . termos_h(implode(", ", $nomesVinc)) . "</span>";
		}

		$btnStatus = $status === "Ativo"
			? "<form method='post' style='display:inline;'><input type='hidden' name='id' value='{$id}'><input type='hidden' name='status' value='inativo'><input type='hidden' name='acao' value='alterar_status'><button type='submit' class='btn btn-xs btn-warning' title='Desativar'><span class='glyphicon glyphicon-off'></span></button></form>"
			: "<form method='post' style='display:inline;'><input type='hidden' name='id' value='{$id}'><input type='hidden' name='status' value='ativo'><input type='hidden' name='acao' value='alterar_status'><button type='submit' class='btn btn-xs btn-success' title='Ativar'><span class='glyphicon glyphicon-ok'></span></button></form>";

		$previewUrl = "preview_termo.php?modelo=" . $id;
		$nomeJs = json_encode($row["mode_tx_nome"] ?? "", JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		echo "<tr>";
		echo "<td>{$id}</td>";
		echo "<td><b>{$nome}</b></td>";
		echo "<td>{$popExibe}</td>";
		echo "<td>{$tipoNome}</td>";
		echo "<td>{$ativoExibe}</td>";
		echo "<td>{$ass}</td>";
		echo "<td>{$status}</td>";
		echo "<td>{$data}</td>";
		echo "<td>
				<form method='post' style='display:inline;'><input type='hidden' name='id' value='{$id}'><input type='hidden' name='acao' value='form'><button type='submit' class='btn btn-xs btn-default' title='Editar'><span class='glyphicon glyphicon-pencil'></span></button></form>
				<a href='{$previewUrl}' target='_blank' class='btn btn-xs btn-info' title='Pré-visualizar'><span class='glyphicon glyphicon-eye-open'></span></a>
				<button type='button' class='btn btn-xs btn-success' onclick='termosModalEnviar({$id}, {$nomeJs})' title='Enviar Termo'><span class='glyphicon glyphicon-send'></span></button>
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

	$opcoesModoEnvioHtml = termos_html_opcoes_assinatura_notificacao("assinatura_eletronica");
	$ajudaModoEnvio = termos_h(termos_ajuda_assinatura_notificacao());

	$termosIcpJsVer = @filemtime(__DIR__ . "/js/validar_assinatura_icp.js") ?: 1;
	echo "
	<script src='js/validar_assinatura_icp.js?v={$termosIcpJsVer}'></script>
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
					<h4 class='modal-title' id='termos_modal_titulo'>Enviar Termo</h4>
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
							<label class='control-label'>Assinatura/Notificação</label>
							<select id='termos_modal_modo_envio' class='form-control input-sm'>
								{$opcoesModoEnvioHtml}
							</select>
							<span class='help-block termos-ajuda' style='font-size:11px; margin-bottom:0;'>{$ajudaModoEnvio}</span>
						</div>
						<div class='col-sm-3'>
							<label class='control-label'>Enviar e-mail</label>
							<select id='termos_modal_email' class='form-control input-sm'>
								<option value='sim'>Sim</option>
								<option value='nao'>Não</option>
							</select>
							<span class='help-block termos-ajuda' style='font-size:11px; margin-bottom:0;'>Envia o e-mail com o link do documento para cada funcionário.</span>
						</div>
						<div class='col-sm-3'>
							<label class='control-label'>Forçar regeração</label>
							<select id='termos_modal_forcar' class='form-control input-sm'>
								<option value='nao'>Não</option>
								<option value='sim'>Sim</option>
							</select>
							<span class='help-block termos-ajuda' style='font-size:11px; margin-bottom:0;'>Não: pula quem já tem termo. Sim: gera de novo (novo PDF e novo envio).</span>
						</div>
						<div class='col-sm-3' style='padding-top:28px;'>
							<b id='termos_modal_selecionados'>0 selecionado(s)</b>
							<span class='help-block termos-ajuda' style='font-size:11px; margin-bottom:0;'>Badge mostra o status do termo de cada funcionário.</span>
						</div>
					</div>
					<div class='row' style='margin-top:6px;'>
						<div class='col-sm-3'>
							<label class='control-label'>Prazo para assinatura (dias)</label>
							<input type='number' id='termos_modal_prazo' class='form-control input-sm' min='0' max='30' step='1' value='1'>
							<span class='help-block termos-ajuda' style='font-size:11px; margin-bottom:0;'>Quantos dias o link do documento fica válido. 0 = sem prazo de expiração.</span>
						</div>
						<div class='col-sm-3'>
							<label class='control-label'>Assinatura por hierarquia</label>
							<select id='termos_modal_hierarquia' class='form-control input-sm'>
								<option value='nao'>Não</option>
								<option value='sim'>Sim</option>
							</select>
							<span class='help-block termos-ajuda' style='font-size:11px; margin-bottom:0;'>Sim: os responsáveis do funcionário assinam em sequência e, por último, o funcionário.</span>
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
						Enviar Termo
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
		var TERMOS_ICP_ENDPOINT = 'verificar_assinatura_icp.php';
		var TERMOS_TIPO_DOC_EDIT_BASE = '../../cadastro_tipo_doc.php';

		function termosModalValidarAssinaturaIcp(onOk) {
			if ($('#termos_modal_modo_envio').val() !== 'assinatura_icp') {
				if (typeof onOk === 'function') { onOk(); }
				return;
			}
			if (TERMOS_MODAL_MODELO <= 0) {
				alert('Modelo inválido.');
				return;
			}
			TermosIcp.verificar({
				modelos: [TERMOS_MODAL_MODELO],
				endpoint: TERMOS_ICP_ENDPOINT,
				tipoEditBase: TERMOS_TIPO_DOC_EDIT_BASE,
				onSuccess: onOk
			});
		}

		function termosEscapeHtml(s) {
			return $('<div>').text(s || '').html();
		}

		function termosModalEnviar(id, nome) {
			TERMOS_MODAL_MODELO = id;
			$('#termos_modal_titulo').text('Enviar Termo — ' + nome);
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
						var cor = (f.status_termo === 'assinado' || f.status_termo === 'visualizado') ? 'success' : ((f.status_termo === 'aguardando_assinatura' || f.status_termo === 'notificado') ? 'warning' : 'info');
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
			var params = {
				modelo_id: TERMOS_MODAL_MODELO,
				enviar_assinatura: 'sim',
				modo_envio: $('#termos_modal_modo_envio').val(),
				enviar_email: $('#termos_modal_email').val(),
				forcar: $('#termos_modal_forcar').val(),
				prazo_expiracao_dias: parseInt($('#termos_modal_prazo').val(), 10) || 0,
				hierarquia: $('#termos_modal_hierarquia').val()
			};
			function iniciar() {
				$('#termos_modal_btn_enviar').prop('disabled', true);
				$('#termos_modal_progresso').show();
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
			termosModalValidarAssinaturaIcp(iniciar);
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
			$('#termos_modal_modo_envio').on('change', function() {
				if (this.value === 'assinatura_icp' && TERMOS_MODAL_MODELO > 0) {
					TermosIcp.verificar({
						modelos: [TERMOS_MODAL_MODELO],
						endpoint: TERMOS_ICP_ENDPOINT,
						tipoEditBase: TERMOS_TIPO_DOC_EDIT_BASE
					});
				}
			});
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

function irPop() {
	header("Location: ../pop/index.php");
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
	$resTipos = query("SELECT tipo_nb_id, tipo_tx_nome, tipo_tx_assinatura, tipo_tx_ativo FROM tipos_documentos WHERE tipo_tx_status = 'ativo' ORDER BY tipo_tx_nome ASC");
	while($resTipos && ($t = mysqli_fetch_assoc($resTipos))){
		$suf = strtolower(trim(strval($t["tipo_tx_assinatura"] ?? "nao"))) === "sim" ? " (com assinatura)" : "";
		if(strtolower(trim(strval($t["tipo_tx_ativo"] ?? "nao"))) === "sim"){
			$suf .= " (com equipamento)";
		}
		$tipos[$t["tipo_nb_id"]] = $t["tipo_tx_nome"] . $suf;
	}

	$conteudo = termos_sanitizar_html(strval($a_mod["mode_tx_conteudo"] ?? ""));

	$placeholdersHtml = "";
	foreach(termos_lista_placeholders_gerais() as $token => $desc){
		$placeholdersHtml .= "<option value=\"" . termos_h($token) . "\">" . termos_h($token . " — " . $desc) . "</option>";
	}

	$ativoPlaceholders = [];
	foreach(termos_lista_placeholders_ativo() as $token => $desc){
		$ativoPlaceholders[$token] = $desc;
	}
	$ativoPlaceholdersJson = json_encode($ativoPlaceholders, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	$tipoDocPadrao = strval($a_mod["mode_nb_tipo_doc"] ?? "");
	if($id <= 0 && $tipoDocPadrao === ""){
		$tipoDocPadrao = strval($_POST["tipo_doc"] ?? $_GET["tipo_doc"] ?? "");
	}

	$popSelecionadoRaw = strval($a_mod["mode_tx_pop"] ?? "");
	if($id <= 0 && trim($popSelecionadoRaw) === ""){
		$popSelecionadoRaw = strval($_POST["pop"] ?? $_GET["pop"] ?? "nao");
	}
	$popSelecionado = in_array(strtolower(trim($popSelecionadoRaw)), ["sim", "1", "true", "yes"], true) ? "sim" : "nao";
	$popSetorSel = intval($a_mod["mode_nb_pop_setor"] ?? ($_POST["pop_setor"] ?? 0));
	$popCodigo = strval($a_mod["mode_tx_pop_codigo"] ?? ($_POST["pop_codigo"] ?? ""));
	$popVersao = strval($a_mod["mode_tx_pop_versao"] ?? ($_POST["pop_versao"] ?? "1.0"));
	$popVigencia = strval($a_mod["mode_dt_pop_vigencia"] ?? ($_POST["pop_vigencia"] ?? ""));
	$popObjetivo = strval($a_mod["mode_tx_pop_objetivo"] ?? ($_POST["pop_objetivo"] ?? ""));
	$popResponsavel = strval($a_mod["mode_tx_pop_responsavel"] ?? ($_POST["pop_responsavel"] ?? ""));
	$popTags = strval($a_mod["mode_tx_pop_tags"] ?? ($_POST["pop_tags"] ?? ""));
	$popFluxograma = strval($a_mod["mode_tx_pop_fluxograma"] ?? "");
	$popFluxogramaNome = strval($a_mod["mode_tx_pop_fluxograma_nome"] ?? "");

	$setoresPop = ["" => "Selecione..."];
	foreach(termos_opcoes_setores() as $kSetor => $vSetor){
		if($kSetor === ""){
			continue;
		}
		$setoresPop[intval($kSetor)] = $vSetor;
	}
	if($popSetorSel > 0 && !isset($setoresPop[$popSetorSel])){
		$resSetorAtual = query("SELECT grup_tx_nome FROM grupos_documentos WHERE grup_nb_id = ? LIMIT 1", "i", [$popSetorSel]);
		if($resSetorAtual instanceof mysqli_result && ($rowSetorAtual = mysqli_fetch_assoc($resSetorAtual))){
			$setoresPop[$popSetorSel] = strval($rowSetorAtual["grup_tx_nome"] ?? "") . " (inativo)";
		}
	}

	$fields = [
		"<input type='hidden' name='id' value='{$id}'>",
		campo("Nome do Modelo*", "nome", strval($a_mod["mode_tx_nome"] ?? ""), 5),
		combo("Tipo de Documento*", "tipo_doc", $tipoDocPadrao, 4, $tipos),
		combo("Status", "status", strval($a_mod["mode_tx_status"] ?? "ativo"), 3, ["ativo" => "Ativo", "inativo" => "Inativo"])
	];

	$fluxoExistenteHtml = "";
	if($popFluxograma !== ""){
		$cacheFluxo = (int)(strtotime(strval($a_mod["mode_tx_dataAtualiza"] ?? "")) ?: 0);
		$linkFluxo = "../pop/pop_arquivo.php?origem=termo&id={$id}&tipo=fluxograma&v={$cacheFluxo}";
		$nomeFluxo = $popFluxogramaNome !== "" ? $popFluxogramaNome : basename($popFluxograma);
		$fluxoExistenteHtml = "<div style='margin-top:6px; padding:8px 10px; background:#fff; border:1px solid #e5e7eb; border-radius:6px;'>
			<span class='fa fa-sitemap' style='color:#16a34a;'></span>
			Fluxograma atual: <a href='" . termos_h($linkFluxo) . "' target='_blank'>" . termos_h($nomeFluxo) . "</a>
			<label style='font-weight:normal; margin-left:12px; margin-bottom:0;'>
				<input type='checkbox' name='pop_remover_fluxograma' value='sim'> Remover fluxograma
			</label>
		</div>";
	}

	$fieldsPopLinha1 = [
		combo("Setor do POP*", "pop_setor", $popSetorSel > 0 ? strval($popSetorSel) : "", 3, $setoresPop),
		campo("Código do POP", "pop_codigo", $popCodigo, 3),
		campo("Versão", "pop_versao", $popVersao, 2),
		campo("Vigência", "pop_vigencia", $popVigencia, 4, "MASCARA_DATA")
	];
	$fieldsPopLinha2 = [
		campo("Responsável pelo POP", "pop_responsavel", $popResponsavel, 4),
		campo("Palavras-chave (separadas por vírgula)", "pop_tags", $popTags, 8)
	];
	$fieldsPopLinha3 = [
		"<div class='col-sm-12 margin-bottom-5'>
			<label>Objetivo / Resumo do POP</label>
			<textarea name='pop_objetivo' class='form-control input-sm' rows='3' placeholder='Explique em poucas linhas o que este procedimento garante e quando deve ser usado.'>" . termos_h($popObjetivo) . "</textarea>
			<span class='help-block' style='font-size:11px;'>Aparece na prévia da Biblioteca de POPs para ajudar o gestor a encontrar o documento certo.</span>
		</div>",
		"<div class='col-sm-12 margin-bottom-5'>
			<label>Fluxograma do POP (imagem ou PDF) <span class='text-muted'>— opcional</span></label>
			<input type='file' name='pop_fluxograma' class='form-control input-sm' accept='.png,.jpg,.jpeg,.webp,.pdf'>
			<span class='help-block' style='font-size:11px;'>Se anexado, a Biblioteca de POPs mostra uma aba exclusiva com o fluxograma do processo.</span>
			{$fluxoExistenteHtml}
		</div>"
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

	echo linha_form([
		combo("Este documento é um POP?", "pop", $popSelecionado, 4, ["nao" => "Não", "sim" => "Sim"], "id='termos_pop_flag' onchange='termosTogglePop()'"),
		"<div class='col-sm-8 margin-bottom-5' style='padding-top:22px;'>
			<div class='alert alert-success' style='margin:0; padding:8px 12px; font-size:12px;'>
				<span class='fa fa-book'></span> Marque <b>Sim</b> para classificar este documento como <b>POP</b>. Ele passa a aparecer na <b>Biblioteca de POPs</b>, organizado pelo setor escolhido e com prévia em PDF, fluxograma e envio para colaboradores.
			</div>
		</div>"
	]);

	echo "<div id='termos_pop_campos' style='display:none; margin:4px 0 14px; padding:14px 10px 4px; border-left:4px solid #16a34a; background:#f7fcf9; border-radius:6px;'>
		<div class='row' style='margin-bottom:6px;'>
			<div class='col-sm-12'>
				<h4 style='margin:0 0 4px; font-size:15px; color:#166534;'><span class='fa fa-book'></span> Classificação do POP</h4>
				<p class='text-muted' style='font-size:12px; margin:0;'>Esses campos alimentam as abas por setor, os filtros e a prévia da Biblioteca de POPs.</p>
			</div>
		</div>";
	echo linha_form($fieldsPopLinha1);
	echo linha_form($fieldsPopLinha2);
	echo linha_form($fieldsPopLinha3);
	echo "</div>";

	echo "<div class='alert alert-info' style='font-size:12px; margin-top:8px;'>
		<i class='fa fa-info-circle'></i> <b>Assinatura/Notificação:</b> o modo de envio é escolhido na hora de gerar/enviar o termo: <b>Assinatura Eletrônica</b> (coleta assinatura digital), <b>Assinatura/ICP</b> (assinatura com ICP-Brasil) ou <b>Notificação</b> (sem assinatura, apenas aviso para o funcionário visualizar e confirmar a ciência). Para usar <b>Assinatura/ICP</b>, o tipo de documento precisa estar com <b>Assinatura = Sim</b>; caso contrário, o sistema alerta e oferece um atalho para corrigir o tipo.
	</div>";

	echo "<br>";

	$editorHtml = "
	<div class='row'>
	<div class='col-md-12'>
		<div class='portlet light'>
			<div class='portlet-title'><span class='caption-subject font-dark bold uppercase'>Texto Padrão do Documento</span></div>
			<div class='portlet-body'>
				<p class='text-muted'>Escreva o texto padrão do documento e insira os campos pelo menu abaixo. <b>O modelo é salvo com os placeholders</b> (ex.: <code>{{funcionario_nome}}</code>) — os dados de cada funcionário são preenchidos automaticamente na hora de gerar. Os campos do equipamento (ex.: <code>{{equipamento_imei}}</code>) estão sempre disponíveis no menu e são preenchidos com os dados do aparelho <b>vinculado ao termo no Cadastro de Celulares</b>. Para conferir, use a Pré-visualização (escolhe o funcionário de amostra).</p>
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
						<select id='termos_placeholders' class='form-control input-sm' style='display:inline-block;width:auto;max-width:420px;' onchange='termosInserirPlaceholder(this)'>
							<option value=''>Inserir campo...</option>
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
		var TERMOS_PLACEHOLDERS_ATIVO = " . $ativoPlaceholdersJson . ";

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

		function termosTogglePop() {
			var sel = document.querySelector('select[name=\"pop\"]');
			var box = document.getElementById('termos_pop_campos');
			if (!sel || !box) { return; }
			box.style.display = sel.value === 'sim' ? '' : 'none';
		}

		function termosInicializarPlaceholdersAtivo() {
			var sel = document.getElementById('termos_placeholders');
			if (!sel) { return; }
			var og = document.getElementById('termos_optgroup_ativo');
			if (!og) {
				og = document.createElement('optgroup');
				og.id = 'termos_optgroup_ativo';
			}
			og.label = 'Dados do Equipamento';
			og.innerHTML = '';
			for (var token in TERMOS_PLACEHOLDERS_ATIVO) {
				if (!Object.prototype.hasOwnProperty.call(TERMOS_PLACEHOLDERS_ATIVO, token)) { continue; }
				var op = document.createElement('option');
				op.value = token;
				op.textContent = token + ' — ' + TERMOS_PLACEHOLDERS_ATIVO[token];
				og.appendChild(op);
			}
			sel.appendChild(og);
		}

		$(function() {
			var termosEditor = document.getElementById('editor_conteudo');
			termosEditor.innerHTML = TERMOS_CONTEUDO;
			var termosSync = function() {
				document.getElementById('conteudo').value = termosEditor.innerHTML;
			};
			termosEditor.addEventListener('input', termosSync);
			$('form[name=contex_form]').on('submit', termosSync);
			termosInicializarPlaceholdersAtivo();
			termosTogglePop();
			$('select[name=pop]').on('change', termosTogglePop);
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
	$modeloAtual = $id > 0 ? termos_carregar_modelo($id) : [];
	if($id > 0 && empty($modeloAtual)){
		set_status("ERRO: Modelo não encontrado.");
		index();
		exit;
	}

	$pop = strtolower(trim(strval($_POST["pop"] ?? "nao"))) === "sim" ? "sim" : "nao";
	$popSetor = $pop === "sim" ? intval($_POST["pop_setor"] ?? 0) : 0;
	$popCodigo = mb_substr(trim(strval($_POST["pop_codigo"] ?? "")), 0, 60, "UTF-8");
	$popVersao = mb_substr(trim(strval($_POST["pop_versao"] ?? "")), 0, 30, "UTF-8");
	$popVigencia = trim(strval($_POST["pop_vigencia"] ?? ""));
	$popObjetivo = trim(strval($_POST["pop_objetivo"] ?? ""));
	$popResponsavel = mb_substr(trim(strval($_POST["pop_responsavel"] ?? "")), 0, 255, "UTF-8");
	$popTags = mb_substr(trim(strval($_POST["pop_tags"] ?? "")), 0, 255, "UTF-8");
	$popFluxograma = $id > 0 ? trim(strval($modeloAtual["mode_tx_pop_fluxograma"] ?? "")) : "";
	$popFluxogramaNome = $id > 0 ? trim(strval($modeloAtual["mode_tx_pop_fluxograma_nome"] ?? "")) : "";

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
	if($pop === "sim" && $popSetor <= 0){
		set_status("ERRO: Selecione o setor do POP.");
		$_POST["id"] = $id;
		form();
		exit;
	}
	if($pop === "sim"){
		$resSetorValido = query("SELECT grup_tx_status FROM grupos_documentos WHERE grup_nb_id = ? LIMIT 1", "i", [$popSetor]);
		$rowSetorValido = ($resSetorValido instanceof mysqli_result) ? (mysqli_fetch_assoc($resSetorValido) ?: []) : [];
		if(empty($rowSetorValido)){
			set_status("ERRO: Setor do POP inválido.");
			$_POST["id"] = $id;
			form();
			exit;
		}
		$statusSetorValido = strtolower(trim(strval($rowSetorValido["grup_tx_status"] ?? "inativo")));
		$setorAtualModelo = intval($modeloAtual["mode_nb_pop_setor"] ?? 0);
		if($statusSetorValido !== "ativo" && ($id <= 0 || $setorAtualModelo !== $popSetor)){
			set_status("ERRO: O setor do POP está inativo. Escolha um setor ativo.");
			$_POST["id"] = $id;
			form();
			exit;
		}
	}

	$tipo = termos_carregar_tipo($tipoDoc);
	if(empty($tipo) || strtolower(trim(strval($tipo["tipo_tx_status"] ?? "inativo"))) !== "ativo"){
		set_status("ERRO: Tipo de documento inválido.");
		$_POST["id"] = $id;
		form();
		exit;
	}

	if($popVigencia !== "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $popVigencia)){
		$popVigencia = "";
	}

	if($pop === "nao" || !empty($_POST["pop_remover_fluxograma"])){
		$popFluxograma = "";
		$popFluxogramaNome = "";
	}

	$arquivoFluxograma = $_FILES["pop_fluxograma"] ?? null;
	if(is_array($arquivoFluxograma) && intval($arquivoFluxograma["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE){
		$uploadFluxograma = termos_salvar_arquivo_pop($arquivoFluxograma, "fluxogramas", ["png", "jpg", "jpeg", "webp", "pdf"], 20 * 1024 * 1024);
		if(empty($uploadFluxograma["ok"])){
			set_status("ERRO: " . strval($uploadFluxograma["erro"] ?? "Falha ao enviar o fluxograma."));
			$_POST["id"] = $id;
			form();
			exit;
		}
		$popFluxograma = strval($uploadFluxograma["caminho_rel"] ?? "");
		$popFluxogramaNome = strval($uploadFluxograma["nome"] ?? "");
	}

	$popValores = [
		$nome, $tipoDoc, $conteudo, $status,
		$pop, $popSetor, $popCodigo, $popVersao, $popVigencia,
		$popObjetivo, $popResponsavel, $popTags, $popFluxograma, $popFluxogramaNome
	];
	$popTipos = "";
	foreach($popValores as $valorTipo){
		$popTipos .= is_int($valorTipo) ? "i" : "s";
	}

	if($id > 0){
		$params = array_merge($popValores, [$user, $id]);
		termos_executar(
			"UPDATE modelo_termo SET
				mode_tx_nome = ?, mode_nb_tipo_doc = ?, mode_tx_conteudo = ?, mode_tx_status = ?,
				mode_tx_pop = ?, mode_nb_pop_setor = ?, mode_tx_pop_codigo = ?, mode_tx_pop_versao = ?,
				mode_dt_pop_vigencia = NULLIF(?, ''),
				mode_tx_pop_objetivo = ?, mode_tx_pop_responsavel = ?, mode_tx_pop_tags = ?,
				mode_tx_pop_fluxograma = NULLIF(?, ''), mode_tx_pop_fluxograma_nome = NULLIF(?, ''),
				mode_nb_userAtualiza = ?, mode_tx_dataAtualiza = NOW()
			 WHERE mode_nb_id = ?",
			$popTipos . "ii",
			$params
		);
		termos_log("modelo_atualizado", "Modelo #{$id} atualizado", ["nome" => $nome, "pop" => $pop]);
	}else{
		$params = array_merge($popValores, [$user]);
		$id = termos_inserir_id(
			"INSERT INTO modelo_termo
				(mode_tx_nome, mode_nb_tipo_doc, mode_tx_conteudo, mode_tx_status,
				 mode_tx_pop, mode_nb_pop_setor, mode_tx_pop_codigo, mode_tx_pop_versao, mode_dt_pop_vigencia,
				 mode_tx_pop_objetivo, mode_tx_pop_responsavel, mode_tx_pop_tags,
				 mode_tx_pop_fluxograma, mode_tx_pop_fluxograma_nome, mode_nb_userCadastro)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)",
			$popTipos . "i",
			$params
		);
		termos_log("modelo_criado", "Modelo #{$id} criado", ["nome" => $nome, "pop" => $pop]);
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
		if(function_exists("bancoTabelaExiste") && bancoTabelaExiste($conn, "pop_envio")){
			query("UPDATE pop_envio SET pev_tx_status = 'cancelado' WHERE pev_tx_origem = 'termo' AND pev_nb_doc = ?", "i", [$id]);
		}
		if(function_exists("bancoTabelaExiste") && bancoTabelaExiste($conn, "pop_favorito")){
			query("DELETE FROM pop_favorito WHERE popf_tx_origem = 'termo' AND popf_nb_doc = ?", "i", [$id]);
		}
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
		$popOrigem = strtolower(trim(strval($origem["mode_tx_pop"] ?? "nao"))) === "sim" ? "sim" : "nao";
		$valoresClone = [
			$nomeNovo,
			intval($origem["mode_nb_tipo_doc"] ?? 0),
			strval($origem["mode_tx_conteudo"] ?? ""),
			strval($origem["mode_tx_denominacao"] ?? ""),
			strval($origem["mode_tx_cidade_assinatura"] ?? ""),
			strtolower(trim(strval($origem["mode_tx_notificacao"] ?? "sim"))) === "nao" ? "nao" : "sim",
			intval($origem["mode_nb_ativo"] ?? 0),
			$popOrigem,
			intval($origem["mode_nb_pop_setor"] ?? 0),
			strval($origem["mode_tx_pop_codigo"] ?? ""),
			strval($origem["mode_tx_pop_versao"] ?? ""),
			strval($origem["mode_dt_pop_vigencia"] ?? ""),
			strval($origem["mode_tx_pop_objetivo"] ?? ""),
			strval($origem["mode_tx_pop_responsavel"] ?? ""),
			strval($origem["mode_tx_pop_tags"] ?? ""),
			strval($origem["mode_tx_pop_fluxograma"] ?? ""),
			strval($origem["mode_tx_pop_fluxograma_nome"] ?? ""),
			$user
		];
		$tiposClone = "";
		foreach($valoresClone as $valorClone){
			$tiposClone .= is_int($valorClone) ? "i" : "s";
		}
		$novoId = termos_inserir_id(
			"INSERT INTO modelo_termo
				(mode_tx_nome, mode_nb_tipo_doc, mode_tx_conteudo, mode_tx_denominacao, mode_tx_cidade_assinatura,
				 mode_tx_notificacao, mode_nb_ativo, mode_tx_pop, mode_nb_pop_setor, mode_tx_pop_codigo,
				 mode_tx_pop_versao, mode_dt_pop_vigencia, mode_tx_pop_objetivo, mode_tx_pop_responsavel,
				 mode_tx_pop_tags, mode_tx_pop_fluxograma, mode_tx_pop_fluxograma_nome, mode_tx_status, mode_nb_userCadastro)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), 'ativo', ?)",
			$tiposClone,
			$valoresClone
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