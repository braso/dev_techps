<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

include_once __DIR__ . "/funcoes_pop.php";
include_once dirname(__DIR__, 2) . "/conecta.php";

function pop_ocultar_loading(): void {
	echo "
	<style>.loading{display:none !important;}</style>
	<script>
		function popHideLoading() {
			var loadings = document.getElementsByClassName('loading');
			for (var i = 0; i < loadings.length; i++) {
				loadings[i].style.visibility = 'hidden';
				loadings[i].style.display = 'none';
			}
		}
		popHideLoading();
		setTimeout(popHideLoading, 500);
	</script>";
}

function pop_voltar_com_erro(string $mensagem, int $editarId = 0, bool $novo = false): void {
	set_status("ERRO: " . $mensagem);
	if($editarId > 0){
		$_GET["editar"] = $editarId;
	}elseif($novo){
		$_GET["novo"] = 1;
	}
	index();
	exit;
}

function pop_salvar_upload(): void {
	global $conn;
	pop_ensure_schema();
	pop_autorizar();

	$id = intval($_POST["id"] ?? 0);
	$titulo = mb_substr(trim(strval($_POST["titulo"] ?? "")), 0, 255, "UTF-8");
	$setorId = intval($_POST["setor"] ?? 0);
	$codigo = mb_substr(trim(strval($_POST["codigo"] ?? "")), 0, 60, "UTF-8");
	$versao = mb_substr(trim(strval($_POST["versao"] ?? "")), 0, 30, "UTF-8");
	$vigencia = trim(strval($_POST["vigencia"] ?? ""));
	$responsavel = mb_substr(trim(strval($_POST["responsavel"] ?? "")), 0, 255, "UTF-8");
	$tags = mb_substr(trim(strval($_POST["tags"] ?? "")), 0, 255, "UTF-8");
	$objetivo = trim(strval($_POST["objetivo"] ?? ""));
	$status = in_array(strval($_POST["status"] ?? "ativo"), ["ativo", "inativo"], true) ? strval($_POST["status"]) : "ativo";
	$user = intval($_SESSION["user_nb_id"] ?? 0);

	if($titulo === ""){
		pop_voltar_com_erro("Informe o título do POP.", $id, $id <= 0);
	}
	if($setorId <= 0){
		pop_voltar_com_erro("Selecione o setor do POP.", $id, $id <= 0);
	}
	if($vigencia !== "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vigencia)){
		$vigencia = "";
	}
	if($versao === ""){
		$versao = "1.0";
	}

	$atual = $id > 0 ? pop_carregar_upload($id) : [];
	if($id > 0 && empty($atual)){
		pop_voltar_com_erro("POP não encontrado.", 0, false);
	}

	$resSetorValido = query("SELECT grup_tx_status FROM grupos_documentos WHERE grup_nb_id = ? LIMIT 1", "i", [$setorId]);
	$rowSetorValido = ($resSetorValido instanceof mysqli_result) ? (mysqli_fetch_assoc($resSetorValido) ?: []) : [];
	if(empty($rowSetorValido)){
		pop_voltar_com_erro("Setor inválido. Selecione um setor cadastrado.", $id, $id <= 0);
	}
	$statusSetorValido = strtolower(trim(strval($rowSetorValido["grup_tx_status"] ?? "inativo")));
	$setorAtualDoc = intval($atual["pop_nb_setor"] ?? 0);
	if($statusSetorValido !== "ativo" && ($id <= 0 || $setorAtualDoc !== $setorId)){
		pop_voltar_com_erro("O setor selecionado está inativo. Escolha um setor ativo.", $id, $id <= 0);
	}

	$arquivoRel = trim(strval($atual["pop_tx_arquivo"] ?? ""));
	$arquivoNome = trim(strval($atual["pop_tx_arquivo_nome"] ?? ""));
	$fluxogramaRel = trim(strval($atual["pop_tx_fluxograma"] ?? ""));
	$fluxogramaNome = trim(strval($atual["pop_tx_fluxograma_nome"] ?? ""));

	$arquivoPdf = $_FILES["arquivo"] ?? null;
	if(is_array($arquivoPdf) && intval($arquivoPdf["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE){
		$uploadPdf = termos_salvar_arquivo_pop($arquivoPdf, "documentos", ["pdf"], 20 * 1024 * 1024);
		if(empty($uploadPdf["ok"])){
			pop_voltar_com_erro(strval($uploadPdf["erro"] ?? "Falha ao enviar o PDF."), $id, $id <= 0);
		}
		$arquivoRel = strval($uploadPdf["caminho_rel"] ?? "");
		$arquivoNome = strval($uploadPdf["nome"] ?? "");
	}
	if($arquivoRel === ""){
		pop_voltar_com_erro("Envie o arquivo PDF do POP.", $id, $id <= 0);
	}

	if(!empty($_POST["remover_fluxograma"])){
		$fluxogramaRel = "";
		$fluxogramaNome = "";
	}
	$arquivoFluxograma = $_FILES["fluxograma"] ?? null;
	if(is_array($arquivoFluxograma) && intval($arquivoFluxograma["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE){
		$uploadFluxograma = termos_salvar_arquivo_pop($arquivoFluxograma, "fluxogramas", ["png", "jpg", "jpeg", "webp", "pdf"], 20 * 1024 * 1024);
		if(empty($uploadFluxograma["ok"])){
			pop_voltar_com_erro(strval($uploadFluxograma["erro"] ?? "Falha ao enviar o fluxograma."), $id, $id <= 0);
		}
		$fluxogramaRel = strval($uploadFluxograma["caminho_rel"] ?? "");
		$fluxogramaNome = strval($uploadFluxograma["nome"] ?? "");
	}

	if($id > 0){
		query(
			"UPDATE pop_documento SET
				pop_tx_titulo = ?, pop_tx_codigo = ?, pop_nb_setor = ?, pop_tx_versao = ?,
				pop_dt_vigencia = NULLIF(?, ''), pop_tx_objetivo = ?, pop_tx_responsavel = ?, pop_tx_tags = ?,
				pop_tx_arquivo = ?, pop_tx_arquivo_nome = ?, pop_tx_fluxograma = NULLIF(?, ''),
				pop_tx_fluxograma_nome = NULLIF(?, ''), pop_tx_status = ?, pop_nb_userAtualiza = ?
			 WHERE pop_nb_id = ?",
			"ssissssssssssii",
			[
				$titulo, $codigo, $setorId, $versao,
				$vigencia, $objetivo, $responsavel, $tags,
				$arquivoRel, $arquivoNome, $fluxogramaRel,
				$fluxogramaNome, $status, $user, $id
			]
		);
		pop_log("upload_atualizado", "POP em PDF #{$id} atualizado", ["titulo" => $titulo, "setor" => $setorId]);
		set_status("POP atualizado com sucesso!");
	}else{
		$retorno = inserir("pop_documento", [
			"pop_tx_titulo",
			"pop_tx_codigo",
			"pop_nb_setor",
			"pop_tx_versao",
			"pop_dt_vigencia",
			"pop_tx_objetivo",
			"pop_tx_responsavel",
			"pop_tx_tags",
			"pop_tx_arquivo",
			"pop_tx_arquivo_nome",
			"pop_tx_fluxograma",
			"pop_tx_fluxograma_nome",
			"pop_tx_status",
			"pop_nb_userCadastro",
			"pop_tx_dataCadastro"
		], [
			$titulo,
			$codigo,
			$setorId,
			$versao,
			$vigencia !== "" ? $vigencia : null,
			$objetivo,
			$responsavel,
			$tags,
			$arquivoRel,
			$arquivoNome,
			$fluxogramaRel !== "" ? $fluxogramaRel : null,
			$fluxogramaNome !== "" ? $fluxogramaNome : null,
			$status,
			$user,
			date("Y-m-d H:i:s")
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
			pop_voltar_com_erro("Não foi possível cadastrar o POP.", 0, true);
		}
		pop_log("upload_criado", "POP em PDF #{$novoId} criado", ["titulo" => $titulo, "setor" => $setorId]);
		set_status("POP enviado com sucesso! Ele já está disponível na Biblioteca de POPs.");
	}

	unset($_GET["editar"], $_GET["novo"], $_GET["busca"], $_GET["fluxograma"], $_GET["favoritos"], $_GET["origem"]);
	$_GET["setor"] = strval($setorId);
	$_GET["status"] = $status;
	index();
	exit;
}

function pop_alterar_status_upload(): void {
	global $conn;
	pop_ensure_schema();
	pop_autorizar();

	$id = intval($_POST["id"] ?? 0);
	$status = in_array(strval($_POST["status"] ?? ""), ["ativo", "inativo"], true) ? strval($_POST["status"]) : "ativo";
	if($id > 0){
		query("UPDATE pop_documento SET pop_tx_status = ?, pop_nb_userAtualiza = ? WHERE pop_nb_id = ?", "sii", [$status, intval($_SESSION["user_nb_id"] ?? 0), $id]);
		pop_log("upload_status", "POP em PDF #{$id} → {$status}");
		set_status($status === "ativo" ? "POP reativado com sucesso!" : "POP desativado com sucesso.");
	}
	index();
	exit;
}

function pop_excluir_upload(): void {
	global $conn;
	pop_ensure_schema();
	pop_autorizar();

	$id = intval($_POST["id"] ?? 0);
	if($id > 0){
		$atual = pop_carregar_upload($id);
		if(!empty($atual)){
			termos_remover_arquivo_pop(strval($atual["pop_tx_arquivo"] ?? ""));
			termos_remover_arquivo_pop(strval($atual["pop_tx_fluxograma"] ?? ""));
		}
		query("DELETE FROM pop_favorito WHERE popf_tx_origem = 'upload' AND popf_nb_doc = ?", "i", [$id]);
		query("UPDATE pop_envio SET pev_tx_status = 'cancelado' WHERE pev_tx_origem = 'upload' AND pev_nb_doc = ?", "i", [$id]);
		query("DELETE FROM pop_documento WHERE pop_nb_id = ?", "i", [$id]);
		pop_log("upload_excluido", "POP em PDF #{$id} excluído");
		set_status("POP excluído com sucesso.");
	}
	index();
	exit;
}

function pop_enviar(): void {
	global $conn;
	@set_time_limit(120);
	pop_ensure_schema();
	pop_autorizar();

	$origem = strtolower(trim(strval($_POST["origem"] ?? "")));
	$docId = intval($_POST["id"] ?? 0);
	$mensagem = mb_substr(trim(strval($_POST["mensagem"] ?? "")), 0, 1000, "UTF-8");
	$user = intval($_SESSION["user_nb_id"] ?? 0);

	$doc = pop_obter_documento($origem, $docId);
	if(empty($doc)){
		set_status("ERRO: Selecione um POP válido para enviar.");
		index();
		exit;
	}
	if(in_array(strval($doc["status"] ?? ""), ["inativo"], true)){
		set_status("ERRO: Este POP está inativo. Reative-o antes de enviar.");
		index();
		exit;
	}

	$ids = $_POST["colaboradores"] ?? [];
	if(!is_array($ids)){
		$ids = [];
	}
	$ids = array_values(array_unique(array_filter(array_map("intval", $ids), fn($v) => $v > 0)));
	if(empty($ids)){
		set_status("ERRO: Selecione ao menos um colaborador.");
		index();
		exit;
	}
	$ids = array_slice($ids, 0, 100);

	$enviados = 0;
	$semEmail = 0;
	$falhas = 0;
	foreach($ids as $entidadeId){
		$res = query(
			"SELECT enti_tx_nome, enti_tx_email
			 FROM entidade WHERE enti_nb_id = ? AND enti_tx_status = 'ativo' LIMIT 1",
			"i",
			[$entidadeId]
		);
		$pessoa = ($res instanceof mysqli_result) ? (mysqli_fetch_assoc($res) ?: []) : [];
		if(empty($pessoa)){
			$falhas++;
			continue;
		}
		$nome = trim(strval($pessoa["enti_tx_nome"] ?? ""));
		$email = trim(strval($pessoa["enti_tx_email"] ?? ""));
		if($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)){
			$semEmail++;
			continue;
		}
		$token = pop_criar_envio($origem, $docId, $entidadeId, $nome, $email, $mensagem, $user);
		if($token === ""){
			$falhas++;
			continue;
		}
		$link = pop_link_publico($token);

		$setorDoc = trim(strval($doc["setor_nome"] ?? ""));
		$codigoDoc = trim(strval($doc["codigo"] ?? ""));
		$tituloDoc = trim(strval($doc["titulo"] ?? ""));
		$notificacaoTitulo = mb_substr("POP disponível: " . $tituloDoc, 0, 255, "UTF-8");
		$notificacaoMensagem = "O POP " . ($codigoDoc !== "" ? $codigoDoc . " — " : "") . $tituloDoc;
		if($setorDoc !== ""){
			$notificacaoMensagem .= " (" . $setorDoc . ")";
		}
		$notificacaoMensagem .= " está disponível para consulta.";
		query(
			"INSERT INTO notificacoes (notf_nb_entidade, notf_tx_titulo, notf_tx_mensagem, notf_tx_link, notf_tx_tipo, notf_tx_status, notf_tx_dataCadastro)
			 VALUES (?, ?, ?, ?, 'info', 'nao_lida', NOW())",
			"isss",
			[$entidadeId, $notificacaoTitulo, $notificacaoMensagem, $link]
		);

		if(pop_enviar_email_documento($email, $nome, strval($doc["titulo"] ?? ""), strval($doc["setor_nome"] ?? ""), $link, $mensagem)){
			$enviados++;
		}else{
			$falhas++;
		}
	}

	$partes = [];
	$partes[] = $enviados . " enviado(s) com sucesso";
	if($semEmail > 0){ $partes[] = $semEmail . " sem e-mail cadastrado"; }
	if($falhas > 0){ $partes[] = $falhas . " com falha"; }
	set_status(($enviados > 0 ? "" : "ERRO: ") . "POP \"" . pop_h(strval($doc["titulo"] ?? "")) . "\": " . implode(", ", $partes) . ".");
	pop_log("envio", "POP enviado para colaboradores", ["origem" => $origem, "doc" => $docId, "enviados" => $enviados, "sem_email" => $semEmail, "falhas" => $falhas]);
	index();
	exit;
}

function index() {
	global $conn;
	pop_ensure_schema();
	pop_autorizar();

	$userId = intval($_SESSION["user_nb_id"] ?? 0);
	$filtros = [
		"busca" => trim(strval($_GET["busca"] ?? "")),
		"setor" => trim(strval($_GET["setor"] ?? "")),
		"origem" => strtolower(trim(strval($_GET["origem"] ?? ""))),
		"status" => in_array(strval($_GET["status"] ?? "ativo"), ["ativo", "inativo", "todos"], true) ? strval($_GET["status"] ?? "ativo") : "ativo",
		"fluxograma" => !empty($_GET["fluxograma"]),
		"favoritos" => !empty($_GET["favoritos"]),
		"ordenar" => in_array(strval($_GET["ordenar"] ?? "recentes"), ["recentes", "nome", "vigencia", "setor"], true) ? strval($_GET["ordenar"] ?? "recentes") : "recentes"
	];
	if(!in_array($filtros["origem"], ["termo", "upload"], true)){
		$filtros["origem"] = "";
	}

	$todos = pop_listar_documentos();
	$favoritos = pop_favoritos_usuario($userId);
	$docs = pop_filtrar_documentos($todos, $filtros, $favoritos);
	$contagens = pop_contar_por_setor($todos);
	$stats = pop_estatisticas($todos);
	$setores = pop_listar_setores(false);
	uasort($setores, fn($a, $b) => strcasecmp(strval($a["nome"] ?? ""), strval($b["nome"] ?? "")));
	$podeTermos = termos_pode_acessar("/documentos/termos/modelos_termo.php");
	$editarId = intval($_GET["editar"] ?? 0);
	$formUpload = $editarId > 0 ? pop_carregar_upload($editarId) : [];
	$abrirUpload = !empty($formUpload) || !empty($_GET["novo"]);
	$cssVer = @filemtime(__DIR__ . "/assets/pop.css") ?: 1;

	$buildUrl = function(array $overrides = []) use ($filtros): string {
		$q = array_merge($filtros, $overrides);
		foreach(["busca", "setor", "origem", "status", "ordenar", "fluxograma", "favoritos"] as $chave){
			$valor = $q[$chave] ?? "";
			if($chave === "status" && $valor === "ativo"){ unset($q[$chave]); continue; }
			if($chave === "ordenar" && $valor === "recentes"){ unset($q[$chave]); continue; }
			if($chave === "setor" && ($valor === "" || $valor === "0")){ unset($q[$chave]); continue; }
			if($chave === "origem" && $valor === ""){ unset($q[$chave]); continue; }
			if($chave === "fluxograma" || $chave === "favoritos"){
				if(empty($valor)){ unset($q[$chave]); } else { $q[$chave] = 1; }
				continue;
			}
			if($valor === "" || $valor === null || $valor === false){ unset($q[$chave]); }
		}
		return "index.php" . (empty($q) ? "" : "?" . http_build_query($q));
	};

	cabecalho("Biblioteca de POPs");
	pop_ocultar_loading();

	echo "<link rel='stylesheet' href='assets/pop.css?v={$cssVer}'>";
	echo "<div class='pop-page'>";

	$msg = trim(strval($_POST["msg_status"] ?? ""));
	if($msg !== ""){
		$classe = strpos($msg, "ERRO") !== false ? "alert-danger" : "alert-success";
		echo "<div class='alert {$classe}' style='border-radius:12px;'>" . $msg . "</div>";
	}

	echo "
	<div class='pop-hero'>
		<div class='pop-hero__inner'>
			<div class='pop-hero__content'>
				<span class='pop-eyebrow'><i class='fa fa-book'></i> Central de Procedimentos</span>
				<h2>Biblioteca de POPs</h2>
				<p>Encontre, apresente e compartilhe os Procedimentos Operacionais Padrão da empresa. Os documentos criados no módulo de termos e os PDFs enviados aqui ficam organizados por setor, com prévia imediata e fluxograma quando existir.</p>
				<div class='pop-hero__actions'>
					<button type='button' class='pop-btn pop-btn--light' data-toggle='modal' data-target='#popUploadModal'>
						<i class='fa fa-cloud-upload'></i> Enviar POP em PDF
					</button>";

	if($podeTermos){
		echo "<a href='../termos/modelos_termo.php?acao=form&pop=sim' class='pop-btn pop-btn--ghost'><i class='fa fa-plus'></i> Criar POP no módulo de termos</a>";
	}
	echo "
					<a href='setores.php' class='pop-btn pop-btn--ghost'><i class='fa fa-building-o'></i> Gerenciar setores</a>
				</div>
			</div>
			<div class='pop-hero__art'><i class='fa fa-sitemap'></i></div>
		</div>
	</div>

	<div class='pop-stats'>
		<div class='pop-stat'>
			<div class='pop-stat__label'><i class='fa fa-files-o'></i> Documentos ativos</div>
			<div class='pop-stat__value'>{$stats["documentos"]}</div>
			<div class='pop-stat__hint'>{$stats["termos"]} do módulo de termos · {$stats["uploads"]} em PDF</div>
			<i class='fa fa-files-o pop-stat__icon'></i>
		</div>
		<div class='pop-stat'>
			<div class='pop-stat__label'><i class='fa fa-building-o'></i> Setores com POP</div>
			<div class='pop-stat__value'>{$stats["setores"]}</div>
			<div class='pop-stat__hint'>Organização por área da empresa</div>
			<i class='fa fa-building-o pop-stat__icon'></i>
		</div>
		<div class='pop-stat'>
			<div class='pop-stat__label'><i class='fa fa-sitemap'></i> Fluxogramas</div>
			<div class='pop-stat__value'>{$stats["fluxogramas"]}</div>
			<div class='pop-stat__hint'>Processos com visão visual</div>
			<i class='fa fa-sitemap pop-stat__icon'></i>
		</div>
		<div class='pop-stat'>
			<div class='pop-stat__label'><i class='fa fa-paper-plane'></i> Compartilhamentos</div>
			<div class='pop-stat__value'>{$stats["compartilhamentos"]}</div>
			<div class='pop-stat__hint'>{$stats["colaboradores"]} colaborador(es) alcançado(s)</div>
			<i class='fa fa-paper-plane pop-stat__icon'></i>
		</div>
	</div>

	<div class='pop-toolbar'>
		<form method='get' action='index.php'>
			<input type='hidden' name='setor' value='" . pop_h($filtros["setor"]) . "'>
			<div class='pop-search'>
				<i class='fa fa-search'></i>
				<input type='search' name='busca' value='" . pop_h($filtros["busca"]) . "' placeholder='Buscar por nome, código, setor, responsável ou palavra-chave...'>
			</div>
			<select name='origem' class='pop-select'>
				<option value='' " . ($filtros["origem"] === "" ? "selected" : "") . ">Todas as origens</option>
				<option value='termo' " . ($filtros["origem"] === "termo" ? "selected" : "") . ">Criados no módulo de termos</option>
				<option value='upload' " . ($filtros["origem"] === "upload" ? "selected" : "") . ">PDFs enviados</option>
			</select>
			<select name='ordenar' class='pop-select'>
				<option value='recentes' " . ($filtros["ordenar"] === "recentes" ? "selected" : "") . ">Mais recentes</option>
				<option value='nome' " . ($filtros["ordenar"] === "nome" ? "selected" : "") . ">Nome A–Z</option>
				<option value='vigencia' " . ($filtros["ordenar"] === "vigencia" ? "selected" : "") . ">Vigência</option>
				<option value='setor' " . ($filtros["ordenar"] === "setor" ? "selected" : "") . ">Setor</option>
			</select>
			<select name='status' class='pop-select'>
				<option value='ativo' " . ($filtros["status"] === "ativo" ? "selected" : "") . ">Ativos</option>
				<option value='inativo' " . ($filtros["status"] === "inativo" ? "selected" : "") . ">Inativos</option>
				<option value='todos' " . ($filtros["status"] === "todos" ? "selected" : "") . ">Todos</option>
			</select>
			<label class='pop-check " . (!empty($filtros["fluxograma"]) ? "is-active" : "") . "'>
				<input type='checkbox' name='fluxograma' value='1' " . (!empty($filtros["fluxograma"]) ? "checked" : "") . ">
				<i class='fa fa-sitemap'></i> Com fluxograma
			</label>
			<label class='pop-check " . (!empty($filtros["favoritos"]) ? "is-active" : "") . "'>
				<input type='checkbox' name='favoritos' value='1' " . (!empty($filtros["favoritos"]) ? "checked" : "") . ">
				<i class='fa fa-star'></i> Favoritos
			</label>
			<button type='submit' class='pop-btn pop-btn--green'><i class='fa fa-filter'></i> Filtrar</button>
			<a href='index.php' class='pop-btn pop-btn--outline pop-btn--xs' title='Limpar filtros'><i class='fa fa-eraser'></i> Limpar</a>
		</form>
	</div>

	<div class='pop-tabs'>
		<a href='" . pop_h($buildUrl(["setor" => ""])) . "' class='pop-tab " . ($filtros["setor"] === "" ? "is-active" : "") . "'>
			<i class='fa fa-th-large'></i> Todos <span class='pop-tab__count'>" . intval($contagens["todos"] ?? 0) . "</span>
		</a>";

	foreach($setores as $setor){
		$setorId = intval($setor["id"] ?? 0);
		$qtd = intval($contagens[$setorId] ?? 0);
		$isActive = $filtros["setor"] === strval($setorId);
		echo "<a href='" . pop_h($buildUrl(["setor" => strval($setorId)])) . "' class='pop-tab " . ($isActive ? "is-active" : "") . "'>
			<i class='fa fa-building-o'></i> " . pop_h(strval($setor["nome"] ?? "")) . " <span class='pop-tab__count'>{$qtd}</span>
		</a>";
	}
	if(intval($contagens["sem"] ?? 0) > 0){
		$isActive = $filtros["setor"] === "sem";
		echo "<a href='" . pop_h($buildUrl(["setor" => "sem"])) . "' class='pop-tab " . ($isActive ? "is-active" : "") . "'>
			<i class='fa fa-minus-circle'></i> Sem setor <span class='pop-tab__count'>" . intval($contagens["sem"] ?? 0) . "</span>
		</a>";
	}
	echo "</div>";

	echo "<div class='pop-result-bar'>
		<span><strong>" . count($docs) . "</strong> POP(s) encontrado(s)</span>
		<span>" . (!empty($filtros["favoritos"]) ? "<i class='fa fa-star' style='color:#f59e0b;'></i> Somente favoritos" : "Clique em um POP para abrir a prévia e apresentar ao colaborador.") . "</span>
	</div>";

	if(empty($docs)){
		$temFiltro = $filtros["busca"] !== ""
			|| $filtros["setor"] !== ""
			|| $filtros["origem"] !== ""
			|| $filtros["status"] !== "ativo"
			|| !empty($filtros["fluxograma"])
			|| !empty($filtros["favoritos"]);
		if(!empty($filtros["favoritos"])){
			$tituloVazio = "Nenhum POP favorito";
			$descVazio = "Marque a estrela em um POP para encontrá-lo rapidamente aqui.";
		}elseif($temFiltro){
			$tituloVazio = "Nenhum POP para estes filtros";
			$descVazio = "Ajuste a busca, o setor ou as opções de filtro para ver outros documentos.";
		}else{
			$tituloVazio = "Nenhum POP encontrado";
			$descVazio = "Cadastre o primeiro procedimento enviando um PDF ou crie um modelo no módulo de termos e marque a opção <b>Este documento é um POP?</b>.";
		}
		echo "
		<div class='pop-empty'>
			<i class='fa fa-book'></i>
			<h3>{$tituloVazio}</h3>
			<p>{$descVazio}</p>
			<div style='display:flex;gap:8px;justify-content:center;flex-wrap:wrap;'>
				<button type='button' class='pop-btn pop-btn--green' data-toggle='modal' data-target='#popUploadModal'><i class='fa fa-cloud-upload'></i> Enviar POP em PDF</button>";

		if($temFiltro){
			echo "<a href='index.php' class='pop-btn pop-btn--outline'><i class='fa fa-eraser'></i> Limpar filtros</a>";
		}elseif($podeTermos){
			echo "<a href='../termos/modelos_termo.php?acao=form&pop=sim' class='pop-btn pop-btn--outline'><i class='fa fa-plus'></i> Criar no módulo de termos</a>";
		}
		echo "</div></div>";
	}else{
		echo "<div class='pop-grid'>";
		foreach($docs as $doc){
			$key = strval($doc["key"] ?? "");
			$ehFavorito = isset($favoritos[$key]);
			$isUpload = strval($doc["origem"] ?? "") === "upload";
			$isInativo = strval($doc["status"] ?? "") === "inativo";
			$docJson = json_encode([
				"key" => $key,
				"origem" => strval($doc["origem"] ?? ""),
				"id" => intval($doc["id"] ?? 0),
				"titulo" => strval($doc["titulo"] ?? ""),
				"codigo" => strval($doc["codigo"] ?? ""),
				"setor" => strval($doc["setor_nome"] ?? ""),
				"versao" => strval($doc["versao"] ?? ""),
				"vigencia" => strval($doc["vigencia"] ?? ""),
				"vigencia_br" => pop_data_br(strval($doc["vigencia"] ?? "")),
				"responsavel" => strval($doc["responsavel"] ?? ""),
				"objetivo" => strval($doc["objetivo"] ?? ""),
				"status" => strval($doc["status"] ?? ""),
				"pdf_url" => strval($doc["pdf_url"] ?? ""),
				"fluxograma_url" => strval($doc["fluxograma_url"] ?? ""),
				"fluxograma_tipo" => strval($doc["fluxograma_tipo"] ?? ""),
				"tem_fluxograma" => !empty($doc["tem_fluxograma"]),
				"origem_label" => strval($doc["origem_label"] ?? "")
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$docAttr = pop_h(is_string($docJson) ? $docJson : "{}");
			$setorNome = trim(strval($doc["setor_nome"] ?? ""));
			$objetivo = pop_resumo(strval($doc["objetivo"] ?? ""), 135);
			if($objetivo === ""){
				$objetivo = "Sem resumo cadastrado. Abra a prévia para consultar o procedimento completo.";
			}
			$editarUrl = strval($doc["editar_url"] ?? "");
			$podeEditar = $isUpload || $podeTermos;

			echo "<article class='pop-card' data-pop-card='{$docAttr}' style='cursor:pointer;'>";
			echo "<button type='button' class='pop-fav " . ($ehFavorito ? "is-active" : "") . "' data-pop-fav data-origem='" . pop_h(strval($doc["origem"] ?? "")) . "' data-id='" . intval($doc["id"] ?? 0) . "' title='" . ($ehFavorito ? "Remover dos favoritos" : "Adicionar aos favoritos") . "' aria-pressed='" . ($ehFavorito ? "true" : "false") . "'>
				<i class='fa " . ($ehFavorito ? "fa-star" : "fa-star-o") . "'></i>
			</button>";

			echo "<div class='pop-card__top'>
				<div class='pop-card__icon " . ($isUpload ? "is-upload" : "") . "'><i class='fa " . pop_h(strval($doc["origem_icone"] ?? "fa-file-o")) . "'></i></div>
				<div class='pop-card__badges'>
					<span class='pop-badge pop-badge--green'>POP</span>
					<span class='pop-badge pop-badge--gray'>v" . pop_h(strval($doc["versao"] ?? "")) . "</span>
					<span class='pop-badge " . ($isUpload ? "pop-badge--rose" : "pop-badge--blue") . "'>" . ($isUpload ? "PDF" : "Termo") . "</span>" .
					(!empty($doc["tem_fluxograma"]) ? "<span class='pop-badge pop-badge--amber'><i class='fa fa-sitemap'></i> Fluxograma</span>" : "") .
					($isInativo ? "<span class='pop-badge pop-badge--gray'>Inativo</span>" : "") . "
				</div>
			</div>";

			echo "<h4>" . pop_h(strval($doc["titulo"] ?? "")) . "</h4>";
			echo "<div class='pop-card__meta'>
				<span><i class='fa fa-building-o'></i> <b>" . pop_h($setorNome !== "" ? $setorNome : "Sem setor") . "</b></span>
				<span><i class='fa fa-barcode'></i> " . pop_h(strval($doc["codigo"] ?? "")) . "</span>
				<span><i class='fa fa-calendar-o'></i> " . pop_h(pop_data_br(strval($doc["vigencia"] ?? ""))) . "</span>
			</div>";
			echo "<p class='pop-card__objective'>" . pop_h($objetivo) . "</p>";
			echo "<div class='pop-card__footer'>
				<button type='button' class='pop-btn pop-btn--green pop-btn--xs' data-pop-doc='{$docAttr}'><i class='fa fa-eye'></i> Visualizar</button>
				<a href='" . pop_h(strval($doc["pdf_url"] ?? "")) . "&download=1' class='pop-btn pop-btn--outline pop-btn--xs' title='Baixar PDF'><i class='fa fa-download'></i></a>
				<button type='button' class='pop-btn pop-btn--outline pop-btn--xs pop-send-trigger' data-pop-doc='{$docAttr}' title='Enviar para colaborador' " . ($isInativo ? "style='display:none;'" : "") . "><i class='fa fa-paper-plane'></i></button>";

			if($podeEditar && $editarUrl !== ""){
				echo "<a href='" . pop_h($editarUrl) . "' class='pop-btn pop-btn--outline pop-btn--xs' title='Editar POP'><i class='fa fa-pencil'></i></a>";
			}
			if($isUpload){
				$proximoStatus = $isInativo ? "ativo" : "inativo";
				echo "<form method='post' style='display:inline;'>
					<input type='hidden' name='acao' value='pop_alterar_status_upload'>
					<input type='hidden' name='id' value='" . intval($doc["id"] ?? 0) . "'>
					<input type='hidden' name='status' value='{$proximoStatus}'>
					<button type='submit' class='pop-btn pop-btn--outline pop-btn--xs' title='" . ($proximoStatus === "ativo" ? "Reativar POP" : "Desativar POP") . "'>
						<i class='fa " . ($proximoStatus === "ativo" ? "fa-check" : "fa-power-off") . "'></i>
					</button>
				</form>
				<form method='post' style='display:inline;' onsubmit='return confirm(\"Excluir este POP em PDF? Esta ação remove o arquivo enviado.\");'>
					<input type='hidden' name='acao' value='pop_excluir_upload'>
					<input type='hidden' name='id' value='" . intval($doc["id"] ?? 0) . "'>
					<button type='submit' class='pop-btn pop-btn--danger pop-btn--xs' title='Excluir POP'><i class='fa fa-trash'></i></button>
				</form>";
			}
			echo "<span class='pop-card__source'>" . pop_h(strval($doc["origem_label"] ?? "")) . "</span>
			</div>";
			echo "</article>";
		}
		echo "</div>";
	}

	// Dados do formulário de upload/edição.
	$formTitulo = strval($formUpload["pop_tx_titulo"] ?? ($_POST["titulo"] ?? ""));
	$formSetor = intval($formUpload["pop_nb_setor"] ?? ($_POST["setor"] ?? 0));
	$formCodigo = strval($formUpload["pop_tx_codigo"] ?? ($_POST["codigo"] ?? ""));
	$formVersao = strval($formUpload["pop_tx_versao"] ?? ($_POST["versao"] ?? "1.0"));
	$formVigencia = strval($formUpload["pop_dt_vigencia"] ?? ($_POST["vigencia"] ?? ""));
	$formResponsavel = strval($formUpload["pop_tx_responsavel"] ?? ($_POST["responsavel"] ?? ""));
	$formTags = strval($formUpload["pop_tx_tags"] ?? ($_POST["tags"] ?? ""));
	$formObjetivo = strval($formUpload["pop_tx_objetivo"] ?? ($_POST["objetivo"] ?? ""));
	$formStatus = strtolower(strval($formUpload["pop_tx_status"] ?? ($_POST["status"] ?? "ativo"))) === "inativo" ? "inativo" : "ativo";
	$formArquivo = strval($formUpload["pop_tx_arquivo_nome"] ?? "");
	$formFluxograma = strval($formUpload["pop_tx_fluxograma_nome"] ?? "");
	$isEditUpload = !empty($formUpload);

	if($formSetor > 0 && !isset($setores[$formSetor])){
		$resSetor = query("SELECT grup_nb_id, grup_tx_nome FROM grupos_documentos WHERE grup_nb_id = ? LIMIT 1", "i", [$formSetor]);
		if($resSetor instanceof mysqli_result && ($rowSetor = mysqli_fetch_assoc($resSetor))){
			$setores[$formSetor] = ["id" => $formSetor, "nome" => strval($rowSetor["grup_tx_nome"] ?? "") . " (inativo)", "status" => "inativo"];
		}
	}

	echo "
	<div class='modal fade pop-modal' id='popUploadModal' tabindex='-1' role='dialog'>
		<div class='modal-dialog modal-lg' role='document'>
			<div class='modal-content'>
				<form method='post' action='index.php' enctype='multipart/form-data'>
					<input type='hidden' name='acao' value='pop_salvar_upload'>
					<input type='hidden' name='id' value='" . ($isEditUpload ? intval($formUpload["pop_nb_id"] ?? 0) : 0) . "'>
					<div class='modal-header'>
						<button type='button' class='close' data-dismiss='modal'>&times;</button>
						<h4 class='modal-title'><i class='fa fa-cloud-upload' style='color:#16a34a;'></i> " . ($isEditUpload ? "Editar POP em PDF" : "Enviar POP em PDF") . "</h4>
					</div>
					<div class='modal-body'>
						<div class='pop-form-grid'>
							<div class='pop-field pop-field--8'>
								<label>Título do POP <span class='req'>*</span></label>
								<input type='text' name='titulo' value='" . pop_h($formTitulo) . "' maxlength='255' required placeholder='Ex.: Procedimento de abertura e fechamento do caixa'>
							</div>
							<div class='pop-field pop-field--4'>
								<label>Setor <span class='req'>*</span></label>
								<select name='setor' required>
									<option value=''>Selecione...</option>";
	foreach($setores as $setor){
		$sel = intval($setor["id"] ?? 0) === $formSetor ? "selected" : "";
		echo "<option value='" . intval($setor["id"] ?? 0) . "' {$sel}>" . pop_h(strval($setor["nome"] ?? "")) . "</option>";
	}
	echo "
								</select>
								<span class='hint'>Não encontrou o setor? <a href='setores.php' target='_blank'>Cadastre um novo setor</a>.</span>
							</div>
							<div class='pop-field pop-field--4'>
								<label>Código do POP</label>
								<input type='text' name='codigo' value='" . pop_h($formCodigo) . "' maxlength='60' placeholder='Ex.: POP-TI-001'>
							</div>
							<div class='pop-field pop-field--3'>
								<label>Versão</label>
								<input type='text' name='versao' value='" . pop_h($formVersao) . "' maxlength='30' placeholder='1.0'>
							</div>
							<div class='pop-field pop-field--5'>
								<label>Vigência</label>
								<input type='date' name='vigencia' value='" . pop_h($formVigencia) . "'>
							</div>
							<div class='pop-field pop-field--6'>
								<label>Responsável pelo POP</label>
								<input type='text' name='responsavel' value='" . pop_h($formResponsavel) . "' maxlength='255' placeholder='Nome ou cargo do responsável'>
							</div>
							<div class='pop-field pop-field--6'>
								<label>Status</label>
								<select name='status'>
									<option value='ativo' " . ($formStatus === "ativo" ? "selected" : "") . ">Ativo</option>
									<option value='inativo' " . ($formStatus === "inativo" ? "selected" : "") . ">Inativo</option>
								</select>
							</div>
							<div class='pop-field'>
								<label>Palavras-chave <span class='hint'>(separadas por vírgula)</span></label>
								<input type='text' name='tags' value='" . pop_h($formTags) . "' maxlength='255' placeholder='Ex.: caixa, fechamento, conferência'>
							</div>
							<div class='pop-field'>
								<label>Objetivo / Resumo</label>
								<textarea name='objetivo' rows='3' placeholder='Explique em poucas linhas o que este POP garante e quando deve ser usado.'>" . pop_h($formObjetivo) . "</textarea>
							</div>
							<div class='pop-upload-section-title'><i class='fa fa-paperclip'></i> Arquivos</div>
							<div class='pop-field pop-field--6'>
								<label>Documento em PDF " . ($isEditUpload ? "" : "<span class='req'>*</span>") . "</label>
								<input type='file' name='arquivo' accept='.pdf,application/pdf' " . ($isEditUpload ? "" : "required") . ">
								<span class='hint'>PDF de até 20 MB.</span>
								" . ($formArquivo !== "" ? "<div class='pop-file-current'><i class='fa fa-file-pdf-o' style='color:#be123c;'></i> Arquivo atual: <b>" . pop_h($formArquivo) . "</b></div>" : "") . "
							</div>
							<div class='pop-field pop-field--6'>
								<label>Fluxograma <span class='hint'>(opcional — imagem ou PDF)</span></label>
								<input type='file' name='fluxograma' accept='.png,.jpg,.jpeg,.webp,.pdf'>
								<span class='hint'>Anexe o desenho do processo para criar a aba de fluxograma na prévia.</span>
								" . ($formFluxograma !== "" ? "<div class='pop-file-current'><i class='fa fa-sitemap' style='color:#16a34a;'></i> Fluxograma atual: <b>" . pop_h($formFluxograma) . "</b><label style='display:block;margin-top:4px;font-weight:normal;'><input type='checkbox' name='remover_fluxograma' value='sim'> Remover fluxograma</label></div>" : "") . "
							</div>
						</div>
					</div>
					<div class='modal-footer'>
						<button type='button' class='pop-btn pop-btn--outline' data-dismiss='modal'>Cancelar</button>
						<button type='submit' class='pop-btn pop-btn--green'><i class='fa fa-check'></i> " . ($isEditUpload ? "Salvar alterações" : "Enviar POP") . "</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class='modal fade pop-preview-modal' id='popPreviewModal' tabindex='-1' role='dialog'>
		<div class='modal-dialog' role='document'>
			<div class='modal-content'>
				<div class='modal-header'>
					<button type='button' class='close' data-dismiss='modal'>&times;</button>
					<h4 class='modal-title' id='popPreviewTitle'>Prévia do POP</h4>
				</div>
				<div class='modal-body'>
					<div class='pop-preview-layout'>
						<div class='pop-preview-main'>
							<div class='pop-preview-tabs'>
								<button type='button' class='pop-preview-tab is-active' data-pop-tab='doc'><i class='fa fa-file-pdf-o'></i> Documento</button>
								<button type='button' class='pop-preview-tab' data-pop-tab='flow' id='popFlowTab' style='display:none;'><i class='fa fa-sitemap'></i> Fluxograma</button>
							</div>
							<iframe class='pop-preview-frame' id='popPreviewFrame' src='about:blank' title='Prévia do POP'></iframe>
							<iframe class='pop-preview-frame' id='popPreviewFlowFrame' src='about:blank' title='Fluxograma do POP' style='display:none;'></iframe>
							<div class='pop-preview-image-wrap' id='popPreviewImageWrap' style='display:none;'>
								<img id='popPreviewImage' src='' alt='Fluxograma do POP'>
							</div>
						</div>
						<aside class='pop-preview-side'>
							<h3 id='popPreviewDocTitle'>POP</h3>
							<div class='pop-preview-code' id='popPreviewCode'></div>
							<ul class='pop-detail-list' id='popPreviewDetails'></ul>
							<div class='pop-preview-objective' id='popPreviewObjective'></div>
							<div class='pop-preview-actions'>
								<a href='#' class='pop-btn pop-btn--green' id='popPreviewDownload'><i class='fa fa-download'></i> Baixar PDF</a>
								<a href='#' class='pop-btn pop-btn--outline' id='popPreviewOpen' target='_blank' rel='noopener'><i class='fa fa-external-link'></i> Abrir em nova aba</a>
								<button type='button' class='pop-btn pop-btn--outline' id='popPreviewCopy'><i class='fa fa-link'></i> Copiar link para enviar</button>
								<button type='button' class='pop-btn pop-btn--outline' id='popPreviewSend'><i class='fa fa-paper-plane'></i> Enviar para colaborador</button>
							</div>
						</aside>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class='modal fade pop-modal' id='popSendModal' tabindex='-1' role='dialog'>
		<div class='modal-dialog' role='document'>
			<div class='modal-content'>
				<form method='post' action='index.php' id='popSendForm'>
					<input type='hidden' name='acao' value='pop_enviar'>
					<input type='hidden' name='origem' id='popSendOrigem' value=''>
					<input type='hidden' name='id' id='popSendId' value=''>
					<div class='modal-header'>
						<button type='button' class='close' data-dismiss='modal'>&times;</button>
						<h4 class='modal-title'><i class='fa fa-paper-plane' style='color:#16a34a;'></i> Enviar POP para colaboradores</h4>
					</div>
					<div class='modal-body'>
						<div class='pop-send-doc'>
							<span id='popSendDocTitle'>POP</span>
							<small>O colaborador recebe um link seguro por e-mail, válido por 30 dias.</small>
						</div>
						<div class='pop-field' style='margin-bottom:12px;'>
							<label>Colaboradores <span class='req'>*</span></label>
							<select name='colaboradores[]' id='popColaboradores' multiple='multiple' style='width:100%;'></select>
							<span class='hint'>Digite nome, matrícula, CPF ou e-mail. Somente colaboradores com e-mail cadastrado recebem o envio.</span>
						</div>
						<div class='pop-field'>
							<label>Mensagem <span class='hint'>(opcional)</span></label>
							<textarea name='mensagem' rows='3' maxlength='1000' placeholder='Ex.: Leia o POP antes do treinamento de sexta-feira.'></textarea>
						</div>
					</div>
					<div class='modal-footer'>
						<button type='button' class='pop-btn pop-btn--outline' data-dismiss='modal'>Cancelar</button>
						<button type='submit' class='pop-btn pop-btn--green'><i class='fa fa-send'></i> Enviar por e-mail</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	";

	echo "
	<script>
	(function($){
		'use strict';
		var popDocAtual = null;

		function popEsc(valor) {
			return $('<div>').text(valor || '').html();
		}

		function popSetTab(tab) {
			$('.pop-preview-tab').removeClass('is-active').filter('[data-pop-tab=\"' + tab + '\"]').addClass('is-active');
			var isFlow = tab === 'flow';
			$('#popPreviewFrame').toggle(!isFlow);
			if (!isFlow) {
				$('#popPreviewFlowFrame, #popPreviewImageWrap').hide();
				return;
			}
			if (!popDocAtual) { return; }
			if (popDocAtual.fluxograma_tipo === 'pdf') {
				$('#popPreviewFlowFrame').show();
				$('#popPreviewImageWrap').hide();
				if ($('#popPreviewFlowFrame').attr('src') === 'about:blank') {
					$('#popPreviewFlowFrame').attr('src', popDocAtual.fluxograma_url);
				}
			} else {
				$('#popPreviewFlowFrame').hide();
				$('#popPreviewImageWrap').show();
				if (!$('#popPreviewImage').attr('src')) {
					$('#popPreviewImage').attr('src', popDocAtual.fluxograma_url);
				}
			}
		}

		function popAbrirPreview(doc) {
			popDocAtual = doc;
			$('#popPreviewTitle').text(doc.titulo || 'Prévia do POP');
			$('#popPreviewDocTitle').text(doc.titulo || 'POP');
			$('#popPreviewCode').text([doc.codigo, doc.setor].filter(Boolean).join(' · '));
			$('#popPreviewObjective').text(doc.objetivo || 'Sem resumo cadastrado.');
			$('#popPreviewFrame').attr('src', doc.pdf_url);
			$('#popPreviewFlowFrame').attr('src', 'about:blank');
			$('#popPreviewImage').attr('src', '');
			$('#popPreviewDownload').attr('href', doc.pdf_url + '&download=1');
			$('#popPreviewOpen').attr('href', doc.pdf_url);
			$('#popPreviewSend').toggle(doc.status !== 'inativo');
			$('#popFlowTab').toggle(!!doc.tem_fluxograma);
			popSetTab('doc');

			var detalhes = [
				['Setor', doc.setor || 'Sem setor'],
				['Código', doc.codigo || '—'],
				['Versão', doc.versao ? ('v' + doc.versao) : '—'],
				['Vigência', doc.vigencia_br ? doc.vigencia_br : '—'],
				['Responsável', doc.responsavel || '—'],
				['Origem', doc.origem_label || '—']
			];
			var html = '';
			detalhes.forEach(function(item){
				html += '<li><span>' + popEsc(item[0]) + '</span><b>' + popEsc(item[1]) + '</b></li>';
			});
			$('#popPreviewDetails').html(html);
			$('#popPreviewModal').modal('show');
		}

		$(document).on('click', '[data-pop-doc]:not(.pop-send-trigger)', function(e){
			e.preventDefault();
			try {
				popAbrirPreview(JSON.parse($(this).attr('data-pop-doc')));
			} catch (err) {}
		});

		$(document).on('click', '[data-pop-card]', function(e){
			if ($(e.target).closest('button, a, form, input, select, textarea').length) { return; }
			try {
				popAbrirPreview(JSON.parse($(this).attr('data-pop-card')));
			} catch (err) {}
		});

		$('.pop-preview-tab').on('click', function(){
			popSetTab($(this).attr('data-pop-tab'));
		});

		$('#popPreviewModal').on('hidden.bs.modal', function(){
			$('#popPreviewFrame, #popPreviewFlowFrame').attr('src', 'about:blank');
			$('#popPreviewImage').attr('src', '');
			popDocAtual = null;
		});

		$('.pop-send-trigger').on('click', function(e){
			e.preventDefault();
			var doc = null;
			try { doc = JSON.parse($(this).attr('data-pop-doc')); } catch (err) { return; }
			popDocAtual = doc;
			$('#popSendOrigem').val(doc.origem);
			$('#popSendId').val(doc.id);
			$('#popSendDocTitle').text(doc.titulo || 'POP');
			$('#popColaboradores').val(null).trigger('change');
			$('#popSendModal').modal('show');
		});

		$('#popPreviewSend').on('click', function(){
			if (!popDocAtual) { return; }
			var doc = popDocAtual;
			$('#popPreviewModal').modal('hide');
			$('#popSendOrigem').val(doc.origem);
			$('#popSendId').val(doc.id);
			$('#popSendDocTitle').text(doc.titulo || 'POP');
			$('#popColaboradores').val(null).trigger('change');
			setTimeout(function(){ $('#popSendModal').modal('show'); }, 250);
		});

		$('#popColaboradores').select2({
			dropdownParent: $('#popSendModal'),
			placeholder: 'Busque por nome, matrícula, CPF ou e-mail',
			allowClear: true,
			minimumInputLength: 1,
			ajax: {
				url: 'buscar_colaboradores.php',
				dataType: 'json',
				delay: 300,
				data: function(params){ return { q: params.term || '' }; },
				processResults: function(data){ return data; }
			}
		});

		function popCopiarTexto(texto, ok) {
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(texto).then(ok).catch(function(){ popCopiarFallback(texto, ok); });
			} else {
				popCopiarFallback(texto, ok);
			}
		}

		function popCopiarFallback(texto, ok) {
			var tmp = $('<textarea>').val(texto).css({position:'fixed', left:'-9999px', top:'0'}).appendTo('body');
			tmp[0].select();
			try { document.execCommand('copy'); ok(); } catch (e) { window.prompt('Copie o link abaixo:', texto); }
			tmp.remove();
		}

		$('#popPreviewCopy').on('click', function(){
			if (!popDocAtual) { return; }
			var btn = $(this).prop('disabled', true);
			$.post('pop_gerar_link.php', { origem: popDocAtual.origem, id: popDocAtual.id }, function(res){
				if (res && res.ok && res.url) {
					popCopiarTexto(res.url, function(){
						if (window.Swal) { Swal.fire({toast:true, position:'top-end', icon:'success', title:'Link copiado!', showConfirmButton:false, timer:2200}); }
					});
				} else {
					if (window.Swal) { Swal.fire('Atenção', (res && res.msg) ? res.msg : 'Não foi possível gerar o link.', 'warning'); }
				}
			}, 'json').fail(function(){
				if (window.Swal) { Swal.fire('Erro', 'Falha de comunicação ao gerar o link.', 'error'); }
			}).always(function(){ btn.prop('disabled', false); });
		});

		$(document).on('click', '[data-pop-fav]', function(e){
			e.preventDefault();
			var btn = $(this);
			$.post('pop_favoritar.php', { origem: btn.data('origem'), id: btn.data('id') }, function(res){
				if (!res || !res.ok) { return; }
				btn.toggleClass('is-active', !!res.favorito).attr('aria-pressed', res.favorito ? 'true' : 'false');
				btn.find('i').toggleClass('fa-star', !!res.favorito).toggleClass('fa-star-o', !res.favorito);
			}, 'json');
		});

		$('#popSendForm').on('submit', function(e){
			var ids = $('#popColaboradores').val() || [];
			if (!ids.length) {
				e.preventDefault();
				if (window.Swal) { Swal.fire('Atenção', 'Selecione ao menos um colaborador.', 'warning'); }
				else { alert('Selecione ao menos um colaborador.'); }
			}
		});
	})(jQuery);
	</script>
	";

	if($abrirUpload){
		echo "<script>jQuery(function(){ jQuery('#popUploadModal').modal('show'); });</script>";
	}

	echo "</div>";
	rodape();
}
