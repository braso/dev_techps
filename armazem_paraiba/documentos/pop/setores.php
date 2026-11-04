<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include_once __DIR__ . "/funcoes_pop.php";
include_once dirname(__DIR__, 2) . "/conecta.php";

function setores_ocultar_loading(): void {
	echo "
	<style>.loading{display:none !important;}</style>
	<script>
		function setoresHideLoading() {
			var loadings = document.getElementsByClassName('loading');
			for (var i = 0; i < loadings.length; i++) {
				loadings[i].style.visibility = 'hidden';
				loadings[i].style.display = 'none';
			}
		}
		setoresHideLoading();
		setTimeout(setoresHideLoading, 500);
	</script>";
}

function setor_salvar(): void {
	global $conn;
	pop_ensure_schema();
	pop_autorizar();

	$id = intval($_POST["id"] ?? 0);
	$nome = mb_substr(trim(strval($_POST["nome"] ?? "")), 0, 150, "UTF-8");
	$status = in_array(strval($_POST["status"] ?? "ativo"), ["ativo", "inativo"], true) ? strval($_POST["status"]) : "ativo";

	if($nome === ""){
		set_status("ERRO: Informe o nome do setor.");
		index();
		exit;
	}

	$dup = query(
		$id > 0
			? "SELECT grup_nb_id FROM grupos_documentos WHERE grup_tx_nome = ? AND grup_nb_id <> ? LIMIT 1"
			: "SELECT grup_nb_id FROM grupos_documentos WHERE grup_tx_nome = ? LIMIT 1",
		$id > 0 ? "si" : "s",
		$id > 0 ? [$nome, $id] : [$nome]
	);
	if($dup instanceof mysqli_result && mysqli_num_rows($dup) > 0){
		set_status("ERRO: Já existe um setor com este nome.");
		index();
		exit;
	}

	if($id > 0){
		query(
			"UPDATE grupos_documentos SET grup_tx_nome = ?, grup_tx_status = ? WHERE grup_nb_id = ?",
			"sii",
			[$nome, $status, $id]
		);
		pop_log("setor_atualizado", "Setor #{$id} atualizado", ["nome" => $nome, "status" => $status]);
		set_status("Setor atualizado com sucesso!");
	}else{
		$retorno = inserir("grupos_documentos", [
			"grup_tx_nome",
			"grup_tx_status"
		], [
			$nome,
			$status
		]);
		$novoId = 0;
		if(is_array($retorno)){
			foreach($retorno as $valor){
				if(is_int($valor) && $valor > 0){
					$novoId = $valor;
					break;
				}
			}
		}
		if($novoId <= 0){
			set_status("ERRO: Não foi possível cadastrar o setor.");
			index();
			exit;
		}
		pop_log("setor_criado", "Setor #{$novoId} criado", ["nome" => $nome]);
		set_status("Setor cadastrado com sucesso! Ele já está disponível nas abas e nos filtros de POP.");
	}

	unset($_GET["editar"]);
	index();
	exit;
}

function setor_status(): void {
	global $conn;
	pop_ensure_schema();
	pop_autorizar();

	$id = intval($_POST["id"] ?? 0);
	$status = in_array(strval($_POST["status"] ?? ""), ["ativo", "inativo"], true) ? strval($_POST["status"]) : "ativo";
	if($id > 0){
		query("UPDATE grupos_documentos SET grup_tx_status = ? WHERE grup_nb_id = ?", "si", [$status, $id]);
		pop_log("setor_status", "Setor #{$id} → {$status}");
		set_status($status === "ativo" ? "Setor reativado." : "Setor desativado.");
	}
	index();
	exit;
}

function setor_excluir(): void {
	global $conn;
	pop_ensure_schema();
	pop_autorizar();

	$id = intval($_POST["id"] ?? 0);
	if($id > 0){
		$totalDocs = pop_contar_documentos_setor($id);
		if($totalDocs > 0){
			set_status("ERRO: Este setor possui {$totalDocs} POP(s) vinculado(s). Desative-o ou mova os documentos antes de excluir.");
			index();
			exit;
		}
		$resEnt = query("SELECT COUNT(*) AS total FROM entidade WHERE enti_setor_id = ?", "i", [$id]);
		$totalEnt = 0;
		if($resEnt instanceof mysqli_result && ($rowEnt = mysqli_fetch_assoc($resEnt))){
			$totalEnt = intval($rowEnt["total"] ?? 0);
		}
		if($totalEnt > 0){
			set_status("ERRO: Este setor possui {$totalEnt} colaborador(es) vinculado(s) e não pode ser excluído.");
			index();
			exit;
		}
		$resSub = query("SELECT COUNT(*) AS total FROM sbgrupos_documentos WHERE sbgr_nb_idgrup = ?", "i", [$id]);
		$totalSub = 0;
		if($resSub instanceof mysqli_result && ($rowSub = mysqli_fetch_assoc($resSub))){
			$totalSub = intval($rowSub["total"] ?? 0);
		}
		if($totalSub > 0){
			set_status("ERRO: Este setor possui {$totalSub} subsetor(es). Ajuste os subsetores antes de excluir.");
			index();
			exit;
		}
		if(bancoTabelaExiste($conn, "setor_responsavel")){
			$resResp = query("SELECT COUNT(*) AS total FROM setor_responsavel WHERE sres_nb_setor_id = ?", "i", [$id]);
			$totalResp = 0;
			if($resResp instanceof mysqli_result && ($rowResp = mysqli_fetch_assoc($resResp))){
				$totalResp = intval($rowResp["total"] ?? 0);
			}
			if($totalResp > 0){
				set_status("ERRO: Este setor possui responsáveis vinculados. Remova-os no cadastro completo de setores antes de excluir.");
				index();
				exit;
			}
		}
		query("DELETE FROM grupos_documentos WHERE grup_nb_id = ?", "i", [$id]);
		pop_log("setor_excluido", "Setor #{$id} excluído");
		set_status("Setor excluído com sucesso.");
	}
	index();
	exit;
}

function index() {
	global $conn;
	pop_ensure_schema();
	pop_autorizar();

	$setores = pop_listar_setores(true);
	uasort($setores, function($a, $b){
		$sa = strval($a["status"] ?? "");
		$sb = strval($b["status"] ?? "");
		if($sa !== $sb){
			return $sa === "ativo" ? -1 : 1;
		}
		return strcasecmp(strval($a["nome"] ?? ""), strval($b["nome"] ?? ""));
	});

	$editarId = intval($_GET["editar"] ?? 0);
	$editar = $setores[$editarId] ?? [];
	$nomeForm = strval($editar["nome"] ?? ($_POST["nome"] ?? ""));
	$statusForm = strval($editar["status"] ?? ($_POST["status"] ?? "ativo"));
	if($statusForm !== "inativo"){
		$statusForm = "ativo";
	}
	$cssVer = @filemtime(__DIR__ . "/assets/pop.css") ?: 1;

	cabecalho("Setores de POP");
	setores_ocultar_loading();

	echo "<link rel='stylesheet' href='assets/pop.css?v={$cssVer}'>";
	echo "<div class='pop-page'>";

	$msg = trim(strval($_POST["msg_status"] ?? ""));
	if($msg !== ""){
		$classe = strpos($msg, "ERRO") !== false ? "alert-danger" : "alert-success";
		echo "<div class='alert {$classe}' style='border-radius:12px;'>" . $msg . "</div>";
	}

	echo "
	<div class='pop-hero' style='padding:24px 28px;'>
		<div class='pop-hero__inner'>
			<div class='pop-hero__content'>
				<span class='pop-eyebrow'><i class='fa fa-building-o'></i> Organização da Biblioteca</span>
				<h2 style='font-size:25px;'>Setores de POP</h2>
				<p>Crie os setores que aparecem como abas na Biblioteca de POPs (ex.: Tecnologia, Administrativo, RH). O mesmo setor é usado no cadastro de colaboradores e nos filtros do módulo de termos.</p>
				<div class='pop-hero__actions'>
					<a href='index.php' class='pop-btn pop-btn--light'><i class='fa fa-arrow-left'></i> Voltar para a Biblioteca</a>
					<a href='../../cadastro_setor.php' class='pop-btn pop-btn--ghost'><i class='fa fa-sliders'></i> Cadastro completo de setores</a>
				</div>
			</div>
			<div class='pop-hero__art' style='width:110px;height:110px;flex-basis:110px;'><i class='fa fa-building-o' style='font-size:46px;'></i></div>
		</div>
	</div>

	<div class='pop-sector-grid'>
		<div class='pop-panel'>
			<div class='pop-panel__head'>
				<h3><i class='fa fa-list-ul' style='color:#16a34a;'></i> Setores cadastrados</h3>
				<span class='pop-badge pop-badge--gray'>" . count($setores) . " setor(es)</span>
			</div>
			<div class='pop-panel__body'>
				<ul class='pop-sector-list'>";

	if(empty($setores)){
		echo "<li class='text-center text-muted' style='padding:24px 0;'>Nenhum setor cadastrado ainda. Use o formulário ao lado para criar o primeiro.</li>";
	}

	foreach($setores as $setor){
		$id = intval($setor["id"] ?? 0);
		$nome = strval($setor["nome"] ?? "");
		$status = strval($setor["status"] ?? "inativo");
		$qtd = pop_contar_documentos_setor($id);
		$proximoStatus = $status === "ativo" ? "inativo" : "ativo";

		echo "<li class='pop-sector-item'>
			<div class='pop-sector-item__icon'><i class='fa fa-building-o'></i></div>
			<div class='pop-sector-item__info'>
				<b>" . pop_h($nome) . "</b>
				<small>{$qtd} POP(s) · " . ($status === "ativo" ? "Ativo" : "Inativo") . "</small>
			</div>
			<div class='pop-sector-item__actions'>
				<a href='setores.php?editar={$id}' class='pop-btn pop-btn--outline pop-btn--xs' title='Editar setor'><i class='fa fa-pencil'></i></a>
				<form method='post' style='display:inline;'>
					<input type='hidden' name='acao' value='setor_status'>
					<input type='hidden' name='id' value='{$id}'>
					<input type='hidden' name='status' value='{$proximoStatus}'>
					<button type='submit' class='pop-btn pop-btn--outline pop-btn--xs' title='" . ($proximoStatus === "ativo" ? "Reativar" : "Desativar") . "'>
						<i class='fa " . ($proximoStatus === "ativo" ? "fa-check" : "fa-power-off") . "'></i>
					</button>
				</form>
				<form method='post' style='display:inline;' onsubmit='return confirm(\"Excluir este setor? Só é permitido quando não há POPs nem colaboradores vinculados.\");'>
					<input type='hidden' name='acao' value='setor_excluir'>
					<input type='hidden' name='id' value='{$id}'>
					<button type='submit' class='pop-btn pop-btn--danger pop-btn--xs' title='Excluir setor'><i class='fa fa-trash'></i></button>
				</form>
			</div>
		</li>";
	}

	echo "
				</ul>
			</div>
		</div>

		<div class='pop-panel'>
			<div class='pop-panel__head'>
				<h3><i class='fa " . (!empty($editar) ? "fa-pencil" : "fa-plus-circle") . "' style='color:#16a34a;'></i> " . (!empty($editar) ? "Editar setor" : "Novo setor") . "</h3>
			</div>
			<div class='pop-panel__body'>
				<form method='post' action='setores.php'>
					<input type='hidden' name='acao' value='setor_salvar'>
					<input type='hidden' name='id' value='" . intval($editar["id"] ?? 0) . "'>
					<div class='pop-field' style='margin-bottom:10px;'>
						<label>Nome do setor <span class='req'>*</span></label>
						<input type='text' name='nome' value='" . pop_h($nomeForm) . "' maxlength='150' required placeholder='Ex.: Tecnologia'>
					</div>
					<div class='pop-field' style='margin-bottom:14px;'>
						<label>Status</label>
						<select name='status'>
							<option value='ativo' " . ($statusForm === "ativo" ? "selected" : "") . ">Ativo</option>
							<option value='inativo' " . ($statusForm === "inativo" ? "selected" : "") . ">Inativo</option>
						</select>
					</div>
					<div style='display:flex;gap:8px;flex-wrap:wrap;'>
						<button type='submit' class='pop-btn pop-btn--green'><i class='fa fa-check'></i> " . (!empty($editar) ? "Salvar alterações" : "Cadastrar setor") . "</button>
						" . (!empty($editar) ? "<a href='setores.php' class='pop-btn pop-btn--outline'>Cancelar edição</a>" : "") . "
					</div>
				</form>

				<div class='alert alert-info' style='margin:18px 0 0;border-radius:10px;font-size:11px;line-height:1.5;'>
					<i class='fa fa-info-circle'></i> O setor criado aqui é o mesmo usado em <b>Cadastros &gt; Setor</b> e no perfil dos colaboradores. A exclusão só é liberada quando o setor não tem POPs nem colaboradores vinculados.
				</div>
			</div>
		</div>
	</div>";

	echo "</div>";
	rodape();
}
