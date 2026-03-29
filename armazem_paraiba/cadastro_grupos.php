<?php
	include "conecta.php";

	function ensureSetorResponsavelSchema(): void{
		$dbRow = mysqli_fetch_assoc(query("SELECT DATABASE() AS db"));
		$db = strval($dbRow["db"] ?? "");
		if($db === ""){
			return;
		}

		$exists = mysqli_fetch_assoc(query(
			"SELECT 1 AS ok
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = 'setor_responsavel'
			LIMIT 1",
			"s",
			[$db]
		));

		if(empty($exists)){
			query(
				"CREATE TABLE IF NOT EXISTS setor_responsavel (
					sres_nb_id INT AUTO_INCREMENT PRIMARY KEY,
					sres_nb_setor_id INT NOT NULL,
					sres_nb_entidade_id INT NOT NULL,
					sres_tx_assinar_governanca ENUM('sim','nao') NOT NULL DEFAULT 'nao',
					sres_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
					sres_tx_dataCadastro DATETIME NOT NULL,
					UNIQUE KEY uniq_setor_entidade (sres_nb_setor_id, sres_nb_entidade_id),
					KEY idx_setor (sres_nb_setor_id),
					KEY idx_entidade (sres_nb_entidade_id)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);
		}

		$cols = mysqli_fetch_all(query(
			"SELECT COLUMN_NAME
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = 'setor_responsavel'",
			"s",
			[$db]
		), MYSQLI_ASSOC);

		$colNames = array_map(fn($r) => strval($r["COLUMN_NAME"] ?? ""), $cols ?: []);
		$has = array_flip($colNames);

		if(!isset($has["sres_nb_id"])){
			query("ALTER TABLE setor_responsavel ADD COLUMN sres_nb_id INT AUTO_INCREMENT PRIMARY KEY");
		}
		if(!isset($has["sres_nb_setor_id"])){
			query("ALTER TABLE setor_responsavel ADD COLUMN sres_nb_setor_id INT NOT NULL");
		}
		if(!isset($has["sres_nb_entidade_id"])){
			query("ALTER TABLE setor_responsavel ADD COLUMN sres_nb_entidade_id INT NOT NULL");
		}
		if(!isset($has["sres_tx_assinar_governanca"])){
			query("ALTER TABLE setor_responsavel ADD COLUMN sres_tx_assinar_governanca ENUM('sim','nao') NOT NULL DEFAULT 'nao'");
		}
		if(!isset($has["sres_tx_status"])){
			query("ALTER TABLE setor_responsavel ADD COLUMN sres_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo'");
		}
		if(!isset($has["sres_tx_dataCadastro"])){
			query("ALTER TABLE setor_responsavel ADD COLUMN sres_tx_dataCadastro DATETIME NOT NULL");
		}

		$idx = mysqli_fetch_all(query(
			"SELECT INDEX_NAME
			FROM information_schema.STATISTICS
			WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = 'setor_responsavel'",
			"s",
			[$db]
		), MYSQLI_ASSOC);
		$idxNames = array_map(fn($r) => strval($r["INDEX_NAME"] ?? ""), $idx ?: []);
		$idxHas = array_flip($idxNames);

		if(!isset($idxHas["uniq_setor_entidade"])){
			@query("ALTER TABLE setor_responsavel ADD UNIQUE KEY uniq_setor_entidade (sres_nb_setor_id, sres_nb_entidade_id)");
		}
		if(!isset($idxHas["idx_setor"])){
			@query("ALTER TABLE setor_responsavel ADD KEY idx_setor (sres_nb_setor_id)");
		}
		if(!isset($idxHas["idx_entidade"])){
			@query("ALTER TABLE setor_responsavel ADD KEY idx_entidade (sres_nb_entidade_id)");
		}
	}

	function ensureCargoResponsavelSchema(): void{
		$dbRow = mysqli_fetch_assoc(query("SELECT DATABASE() AS db"));
		$db = strval($dbRow["db"] ?? "");
		if($db === ""){
			return;
		}

		$exists = mysqli_fetch_assoc(query(
			"SELECT 1 AS ok
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = 'cargo_responsavel'
			LIMIT 1",
			"s",
			[$db]
		));

		if(empty($exists)){
			query(
				"CREATE TABLE IF NOT EXISTS cargo_responsavel (
					cres_nb_id INT AUTO_INCREMENT PRIMARY KEY,
					cres_nb_cargo_id INT NOT NULL,
					cres_nb_entidade_id INT NOT NULL,
					cres_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
					cres_tx_dataCadastro DATETIME NOT NULL,
					UNIQUE KEY uniq_cargo_entidade (cres_nb_cargo_id, cres_nb_entidade_id),
					KEY idx_cargo (cres_nb_cargo_id),
					KEY idx_entidade (cres_nb_entidade_id)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);
		}

		$cols = mysqli_fetch_all(query(
			"SELECT COLUMN_NAME
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = 'cargo_responsavel'",
			"s",
			[$db]
		), MYSQLI_ASSOC);

		$colNames = array_map(fn($r) => strval($r["COLUMN_NAME"] ?? ""), $cols ?: []);
		$has = array_flip($colNames);

		if(!isset($has["cres_nb_id"])){
			query("ALTER TABLE cargo_responsavel ADD COLUMN cres_nb_id INT AUTO_INCREMENT PRIMARY KEY");
		}
		if(!isset($has["cres_nb_cargo_id"])){
			query("ALTER TABLE cargo_responsavel ADD COLUMN cres_nb_cargo_id INT NOT NULL");
		}
		if(!isset($has["cres_nb_entidade_id"])){
			query("ALTER TABLE cargo_responsavel ADD COLUMN cres_nb_entidade_id INT NOT NULL");
		}
		if(!isset($has["cres_tx_status"])){
			query("ALTER TABLE cargo_responsavel ADD COLUMN cres_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo'");
		}
		if(!isset($has["cres_tx_dataCadastro"])){
			query("ALTER TABLE cargo_responsavel ADD COLUMN cres_tx_dataCadastro DATETIME NOT NULL");
		}

		$idx = mysqli_fetch_all(query(
			"SELECT INDEX_NAME
			FROM information_schema.STATISTICS
			WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = 'cargo_responsavel'",
			"s",
			[$db]
		), MYSQLI_ASSOC);
		$idxNames = array_map(fn($r) => strval($r["INDEX_NAME"] ?? ""), $idx ?: []);
		$idxHas = array_flip($idxNames);

		if(!isset($idxHas["uniq_cargo_entidade"])){
			@query("ALTER TABLE cargo_responsavel ADD UNIQUE KEY uniq_cargo_entidade (cres_nb_cargo_id, cres_nb_entidade_id)");
		}
		if(!isset($idxHas["idx_cargo"])){
			@query("ALTER TABLE cargo_responsavel ADD KEY idx_cargo (cres_nb_cargo_id)");
		}
		if(!isset($idxHas["idx_entidade"])){
			@query("ALTER TABLE cargo_responsavel ADD KEY idx_entidade (cres_nb_entidade_id)");
		}
	}

	function getGruposDocumentosEmpresaColumn(): string{
		$dbRow = mysqli_fetch_assoc(query("SELECT DATABASE() AS db"));
		$db = strval($dbRow["db"] ?? "");
		if($db === ""){
			return "";
		}

		$cols = mysqli_fetch_all(query(
			"SELECT COLUMN_NAME
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = 'grupos_documentos'
				AND (COLUMN_NAME LIKE '%empresa%' OR COLUMN_NAME LIKE '%empr%')",
			"s",
			[$db]
		), MYSQLI_ASSOC);

		$colNames = array_map(fn($r) => strval($r["COLUMN_NAME"] ?? ""), $cols ?: []);
		$has = array_flip($colNames);

		foreach(["grup_nb_empresa", "grup_nb_empresa_id", "grup_nb_empr_id", "grup_nb_empr", "grup_nb_idEmpresa"] as $candidate){
			if(isset($has[$candidate])){
				return $candidate;
			}
		}

		foreach($colNames as $c){
			if($c !== "" && (stripos($c, "empresa") !== false || stripos($c, "empr") !== false)){
				return $c;
			}
		}

		return "";
	}

	function carregarResponsaveisSetor(int $setorId, int $empresaId = 0): array{
		if($setorId <= 0){
			return [];
		}
		ensureSetorResponsavelSchema();

		$sql =
			"SELECT
				sr.sres_nb_entidade_id AS id,
				e.enti_tx_nome AS nome,
				e.enti_tx_email AS email
			FROM setor_responsavel sr
			LEFT JOIN entidade e ON e.enti_nb_id = sr.sres_nb_entidade_id
			WHERE sr.sres_nb_setor_id = ?
				AND sr.sres_tx_status = 'ativo'
				AND e.enti_tx_status = 'ativo'
				AND e.enti_tx_ocupacao IN ('Motorista','Ajudante','Funcionário')";
		$types = "i";
		$params = [$setorId];
		if($empresaId > 0){
			$sql .= " AND e.enti_nb_empresa = ?";
			$types .= "i";
			$params[] = $empresaId;
		}
		$sql .= " ORDER BY e.enti_tx_nome ASC";

		$rows = mysqli_fetch_all(query(
			$sql,
			$types,
			$params
		), MYSQLI_ASSOC);
		return is_array($rows) ? $rows : [];
	}

	function carregarResponsaveisCargo(int $cargoId, int $empresaId = 0): array{
		if($cargoId <= 0){
			return [];
		}
		ensureCargoResponsavelSchema();

		$sql =
			"SELECT
				cr.cres_nb_entidade_id AS id,
				e.enti_tx_nome AS nome,
				e.enti_tx_email AS email
			FROM cargo_responsavel cr
			LEFT JOIN entidade e ON e.enti_nb_id = cr.cres_nb_entidade_id
			WHERE cr.cres_nb_cargo_id = ?
				AND cr.cres_tx_status = 'ativo'
				AND e.enti_tx_status = 'ativo'
				AND e.enti_tx_ocupacao IN ('Motorista','Ajudante','Funcionário')";
		$types = "i";
		$params = [$cargoId];
		if($empresaId > 0){
			$sql .= " AND e.enti_nb_empresa = ?";
			$types .= "i";
			$params[] = $empresaId;
		}
		$sql .= " ORDER BY e.enti_tx_nome ASC";

		$rows = mysqli_fetch_all(query(
			$sql,
			$types,
			$params
		), MYSQLI_ASSOC);
		return is_array($rows) ? $rows : [];
	}

	function salvarResponsaveisSetor(int $setorId, array $responsaveisIds): void{
		if($setorId <= 0){
			return;
		}
		ensureSetorResponsavelSchema();
		$ids = array_values(array_unique(array_filter(array_map("intval", $responsaveisIds), fn($v) => $v > 0)));
		$agora = date("Y-m-d H:i:s");

		query(
			"UPDATE setor_responsavel
			SET sres_tx_status = 'inativo'
			WHERE sres_nb_setor_id = ?",
			"i",
			[$setorId]
		);

		if(empty($ids)){
			return;
		}

		foreach($ids as $entidadeId){
			query(
				"INSERT INTO setor_responsavel
					(sres_nb_setor_id, sres_nb_entidade_id, sres_tx_assinar_governanca, sres_tx_status, sres_tx_dataCadastro)
				VALUES
					(?, ?, 'nao', 'ativo', ?)
				ON DUPLICATE KEY UPDATE
					sres_tx_status = 'ativo',
					sres_tx_dataCadastro = VALUES(sres_tx_dataCadastro)",
				"iis",
				[$setorId, $entidadeId, $agora]
			);
		}
	}

	function salvarResponsaveisCargo(int $cargoId, array $responsaveisIds): void{
		if($cargoId <= 0){
			return;
		}
		ensureCargoResponsavelSchema();
		$ids = array_values(array_unique(array_filter(array_map("intval", $responsaveisIds), fn($v) => $v > 0)));
		$agora = date("Y-m-d H:i:s");

		query(
			"UPDATE cargo_responsavel
			SET cres_tx_status = 'inativo'
			WHERE cres_nb_cargo_id = ?",
			"i",
			[$cargoId]
		);

		if(empty($ids)){
			return;
		}

		foreach($ids as $entidadeId){
			query(
				"INSERT INTO cargo_responsavel
					(cres_nb_cargo_id, cres_nb_entidade_id, cres_tx_status, cres_tx_dataCadastro)
				VALUES
					(?, ?, 'ativo', ?)
				ON DUPLICATE KEY UPDATE
					cres_tx_status = 'ativo',
					cres_tx_dataCadastro = VALUES(cres_tx_dataCadastro)",
				"iis",
				[$cargoId, $entidadeId, $agora]
			);
		}
	}

	function api_responsaveis_setor(): void{
		header("Content-Type: application/json; charset=utf-8");
		$setorId = intval($_GET["setor_id"] ?? 0);
		$empresaId = intval($_GET["empresa_id"] ?? 0);
		$rows = carregarResponsaveisSetor($setorId, $empresaId);
		$out = [];
		foreach($rows as $r){
			$id = intval($r["id"] ?? 0);
			if($id <= 0){ continue; }
			$nome = trim(strval($r["nome"] ?? ""));
			$email = trim(strval($r["email"] ?? ""));
			$label = $nome !== "" ? $nome : ("ID " . $id);
			if($email !== ""){ $label .= " | " . $email; }
			$out[] = ["id" => $id, "text" => $label];
		}
		echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

	function api_responsaveis_cargo(): void{
		header("Content-Type: application/json; charset=utf-8");
		$cargoId = intval($_GET["cargo_id"] ?? 0);
		$empresaId = intval($_GET["empresa_id"] ?? 0);
		$rows = carregarResponsaveisCargo($cargoId, $empresaId);
		$out = [];
		foreach($rows as $r){
			$id = intval($r["id"] ?? 0);
			if($id <= 0){ continue; }
			$nome = trim(strval($r["nome"] ?? ""));
			$email = trim(strval($r["email"] ?? ""));
			$label = $nome !== "" ? $nome : ("ID " . $id);
			if($email !== ""){ $label .= " | " . $email; }
			$out[] = ["id" => $id, "text" => $label];
		}
		echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

	function salvarRespSetor(): void{
		$setorId = intval($_POST["setor_id"] ?? 0);
		$ids = $_POST["responsaveis_setor"] ?? [];
		$ids = is_array($ids) ? $ids : [];
		salvarResponsaveisSetor($setorId, $ids);
		set_status("Responsáveis do setor salvos.");
		$_POST["tab"] = "setor";
		index();
		exit;
	}

	function salvarRespCargo(): void{
		$cargoId = intval($_POST["cargo_id"] ?? 0);
		$ids = $_POST["responsaveis_cargo"] ?? [];
		$ids = is_array($ids) ? $ids : [];
		salvarResponsaveisCargo($cargoId, $ids);
		set_status("Responsáveis do cargo salvos.");
		$_POST["tab"] = "cargo";
		index();
		exit;
	}

	function index(): void{
		include "check_permission.php";
		verificaPermissao('/cadastro_grupos.php');

		cabecalho("Grupos");

		ensureSetorResponsavelSchema();
		ensureCargoResponsavelSchema();

		$tab = strtolower(trim(strval($_POST["tab"] ?? "setor")));
		if(!in_array($tab, ["setor","cargo"], true)){
			$tab = "setor";
		}

		$empresaId = intval($_POST["empresa_id"] ?? 0);
		if($empresaId <= 0){
			$empresaId = intval($_SESSION["user_nb_empresa"] ?? 0);
		}
		if($empresaId <= 0){
			$row = mysqli_fetch_assoc(query(
				"SELECT empr_nb_id AS id
				FROM empresa
				WHERE empr_tx_status = 'ativo'
				ORDER BY empr_tx_nome ASC
				LIMIT 1"
			));
			$empresaId = intval($row["id"] ?? 0);
		}

		$setorId = intval($_POST["setor_id"] ?? 0);
		$cargoId = intval($_POST["cargo_id"] ?? 0);

		$tabSetorActive = $tab === "setor" ? "active" : "";
		$tabCargoActive = $tab === "cargo" ? "active" : "";

		$extraEmpresa = "";
		if(intval($_SESSION["user_nb_empresa"] ?? 0) > 0 && is_bool(stripos($_SESSION["user_tx_nivel"] ?? "", "Administrador")) && is_bool(stripos($_SESSION["user_tx_nivel"] ?? "", "Super"))){
			$extraEmpresa = " AND empr_nb_id = '".intval($_SESSION["user_nb_empresa"])."'";
			$empresaId = intval($_SESSION["user_nb_empresa"]);
		}

		$colEmpresaSetor = getGruposDocumentosEmpresaColumn();
		$condSetor = " ORDER BY grup_tx_nome ASC";
		if($empresaId > 0 && $colEmpresaSetor !== ""){
			$condSetor = " AND {$colEmpresaSetor} = {$empresaId} ORDER BY grup_tx_nome ASC";
		}

		echo abre_form("Vincular responsáveis");
		echo campo_hidden("tab", $tab);

		echo "
			<div class='row'>".
				combo_bd("Empresa", "empresa_id", ($empresaId > 0 ? strval($empresaId) : ""), 3, "empresa", "id='empresa_id' onchange=\"document.contex_form.setor_id.value=''; document.contex_form.cargo_id.value=''; this.form.submit();\"", ($extraEmpresa." ORDER BY empr_tx_nome ASC")).
			"</div>
			<ul class='nav nav-tabs' style='margin-bottom: 15px;'>
				<li class='{$tabSetorActive}'><a href='#tab_setor' data-toggle='tab' onclick=\"document.contex_form.tab.value='setor'\">Vincular Responsável Setor</a></li>
				<li class='{$tabCargoActive}'><a href='#tab_cargo' data-toggle='tab' onclick=\"document.contex_form.tab.value='cargo'\">Vincular Responsável Cargo</a></li>
			</ul>
			<div class='tab-content'>
				<div class='tab-pane {$tabSetorActive}' id='tab_setor'>
					<div class='row'>".
						combo_bd("Setor", "setor_id", ($setorId > 0 ? strval($setorId) : ""), 3, "grupos_documentos", "id='setor_id' onchange=\"document.contex_form.tab.value='setor'\"", $condSetor).
						"<div class='col-sm-9 margin-bottom-5 campo-fit-content'>
							<label class='control-label'>Responsáveis do setor</label>
							<select class='form-control input-sm resp-setor' name='responsaveis_setor[]' multiple='multiple' style='width:100%;'></select>
							<div class='help-block'>Selecione um ou mais responsáveis.</div>
						</div>
					</div>
					<div class='row'>
						<div class='col-sm-12'>
							<button name='acao' value='salvarRespSetor' type='submit' class='btn btn-success'>Gravar</button>
						</div>
					</div>
				</div>
				<div class='tab-pane {$tabCargoActive}' id='tab_cargo'>
					<div class='row'>".
						combo_bd("Cargo", "cargo_id", ($cargoId > 0 ? strval($cargoId) : ""), 3, "operacao", "id='cargo_id' onchange=\"document.contex_form.tab.value='cargo'\"").
						"<div class='col-sm-9 margin-bottom-5 campo-fit-content'>
							<label class='control-label'>Responsáveis do cargo</label>
							<select class='form-control input-sm resp-cargo' name='responsaveis_cargo[]' multiple='multiple' style='width:100%;'></select>
							<div class='help-block'>Selecione um ou mais responsáveis.</div>
						</div>
					</div>
					<div class='row'>
						<div class='col-sm-12'>
							<button name='acao' value='salvarRespCargo' type='submit' class='btn btn-success'>Gravar</button>
						</div>
					</div>
				</div>
			</div>
		";

		echo "<script>
			(function($){
				if(!$ || !$.fn){ return; }
				$(function(){
					var baseUrl = ".json_encode($_ENV["URL_BASE"].$_ENV["APP_PATH"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";
					var contexPath = ".json_encode($_ENV["APP_PATH"].$_ENV["CONTEX_PATH"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";

					function sanitizeText(v){
						v = (v === null || v === undefined) ? '' : String(v);
						v = v.replace(/^\\s*\\[\\s*\\]\\s*/g, '');
						v = v.replace(/^\\s*\\[\\s*\\]\\s*/g, '');
						return v.trim();
					}

					function condResponsaveis(){
						var empresaId = parseInt($('#empresa_id').val() || '0', 10);
						var cond = \"AND enti_tx_status = 'ativo' AND enti_tx_ocupacao IN ('Motorista','Ajudante','Funcionário')\";
						if(empresaId && empresaId > 0){
							cond += \" AND enti_nb_empresa = \" + empresaId;
						}
						return encodeURIComponent(cond);
					}

					function initSelect($sel){
						if(!$sel || !$sel.length){ return; }
						if($.fn.select2){
							var urlSelect2 = baseUrl + '/contex20/select2.php?path=' + encodeURIComponent(contexPath) + '&tabela=entidade&ordem=&limite=15&condicoes=' + condResponsaveis() + '&colunas=enti_tx_matricula';
							$.fn.select2.defaults.set('theme','bootstrap');
							$sel.select2({
								language: 'pt-BR',
								placeholder: 'Selecione',
								allowClear: true,
								width: '100%',
								ajax: {
									url: urlSelect2,
									dataType: 'json',
									delay: 250,
									processResults: function (data) {
										(data || []).forEach(function(item){
											if(item && item.text !== undefined){
												item.text = sanitizeText(item.text);
											}
										});
										return { results: data };
									},
									cache: true
								},
								templateResult: function(item){
									if(!item){ return ''; }
									return sanitizeText(item.text || item.id || '');
								},
								templateSelection: function(item){
									if(!item){ return ''; }
									return sanitizeText(item.text || item.id || '');
								}
							});
						}
					}

					function setSelectedFromApi($sel, url){
						if(!$sel || !$sel.length){ return; }
						$sel.empty();
						if(!url){ $sel.trigger('change'); return; }
						$.getJSON(url, function(data){
							var items = data || [];
							items.forEach(function(it){
								if(!it || !it.id){ return; }
								var opt = new Option(sanitizeText(it.text || it.id), String(it.id), true, true);
								$sel.append(opt);
							});
							$sel.trigger('change');
						});
					}

					var $selSetor = $('.resp-setor');
					var $selCargo = $('.resp-cargo');
					initSelect($selSetor);
					initSelect($selCargo);

					function refreshSetor(){
						var empresaId = parseInt($('#empresa_id').val() || '0', 10);
						var id = parseInt($('#setor_id').val() || '0', 10);
						if(!id || id <= 0){
							setSelectedFromApi($selSetor, '');
							return;
						}
						var qs = 'cadastro_grupos.php?acao=api_responsaveis_setor&setor_id=' + id;
						if(empresaId && empresaId > 0){ qs += '&empresa_id=' + empresaId; }
						setSelectedFromApi($selSetor, qs);
					}
					function refreshCargo(){
						var empresaId = parseInt($('#empresa_id').val() || '0', 10);
						var id = parseInt($('#cargo_id').val() || '0', 10);
						if(!id || id <= 0){
							setSelectedFromApi($selCargo, '');
							return;
						}
						var qs = 'cadastro_grupos.php?acao=api_responsaveis_cargo&cargo_id=' + id;
						if(empresaId && empresaId > 0){ qs += '&empresa_id=' + empresaId; }
						setSelectedFromApi($selCargo, qs);
					}

					$('#setor_id').on('change', function(){ refreshSetor(); });
					$('#cargo_id').on('change', function(){ refreshCargo(); });

					refreshSetor();
					refreshCargo();

					$('a[data-toggle=\"tab\"]').on('shown.bs.tab', function(e){
						var target = $(e.target).attr('href') || '';
						if(target === '#tab_setor'){ refreshSetor(); }
						if(target === '#tab_cargo'){ refreshCargo(); }
					});
				});
			})(window.jQuery);
		</script>";

		echo fecha_form([]);
		rodape();
	}
