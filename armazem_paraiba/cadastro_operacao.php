<?php
    /* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/
	include "conecta.php";

	function ensureOperacaoResponsavelSchema(){
		$dbRow = mysqli_fetch_assoc(query("SELECT DATABASE() AS db"));
		$db = strval($dbRow["db"] ?? "");
		if($db === ""){
			return;
		}

		$exists = mysqli_fetch_assoc(query(
			"SELECT 1 AS ok
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = 'operacao_responsavel'
			LIMIT 1",
			"s",
			[$db]
		));

		if(empty($exists)){
			query(
				"CREATE TABLE IF NOT EXISTS operacao_responsavel (
					opre_nb_id INT AUTO_INCREMENT PRIMARY KEY,
					opre_nb_operacao_id INT NOT NULL,
					opre_nb_entidade_id INT NOT NULL,
					opre_tx_assinar_governanca ENUM('sim','nao') NOT NULL DEFAULT 'nao',
					opre_nb_ordem INT NOT NULL DEFAULT 0,
					opre_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
					opre_tx_dataCadastro DATETIME NOT NULL,
					UNIQUE KEY uniq_operacao_entidade (opre_nb_operacao_id, opre_nb_entidade_id),
					KEY idx_operacao (opre_nb_operacao_id),
					KEY idx_entidade (opre_nb_entidade_id)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);
		}

		$cols = mysqli_fetch_all(query(
			"SELECT COLUMN_NAME
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = 'operacao_responsavel'",
			"s",
			[$db]
		), MYSQLI_ASSOC);

		$colNames = array_map(fn($r) => strval($r["COLUMN_NAME"] ?? ""), $cols ?: []);
		$has = array_flip($colNames);

		if(!isset($has["opre_nb_id"])){
			query("ALTER TABLE operacao_responsavel ADD COLUMN opre_nb_id INT AUTO_INCREMENT PRIMARY KEY");
		}
		if(!isset($has["opre_nb_operacao_id"])){
			query("ALTER TABLE operacao_responsavel ADD COLUMN opre_nb_operacao_id INT NOT NULL");
		}
		if(!isset($has["opre_nb_entidade_id"])){
			query("ALTER TABLE operacao_responsavel ADD COLUMN opre_nb_entidade_id INT NOT NULL");
		}
		if(!isset($has["opre_tx_assinar_governanca"])){
			query("ALTER TABLE operacao_responsavel ADD COLUMN opre_tx_assinar_governanca ENUM('sim','nao') NOT NULL DEFAULT 'nao'");
		}
		if(!isset($has["opre_tx_status"])){
			query("ALTER TABLE operacao_responsavel ADD COLUMN opre_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo'");
		}
		if(!isset($has["opre_tx_dataCadastro"])){
			query("ALTER TABLE operacao_responsavel ADD COLUMN opre_tx_dataCadastro DATETIME NOT NULL");
		}
		if(!isset($has["opre_nb_ordem"])){
			query("ALTER TABLE operacao_responsavel ADD COLUMN opre_nb_ordem INT NOT NULL DEFAULT 0");
		}

		$idx = mysqli_fetch_all(query(
			"SELECT INDEX_NAME
			FROM information_schema.STATISTICS
			WHERE TABLE_SCHEMA = ?
				AND TABLE_NAME = 'operacao_responsavel'",
			"s",
			[$db]
		), MYSQLI_ASSOC);
		$idxNames = array_map(fn($r) => strval($r["INDEX_NAME"] ?? ""), $idx ?: []);
		$idxHas = array_flip($idxNames);

		if(isset($idxHas["uniq_operacao"])){
			@query("ALTER TABLE operacao_responsavel DROP INDEX uniq_operacao");
		}

		if(!isset($idxHas["uniq_operacao_entidade"])){
			@query("ALTER TABLE operacao_responsavel ADD UNIQUE KEY uniq_operacao_entidade (opre_nb_operacao_id, opre_nb_entidade_id)");
		}
		if(!isset($idxHas["idx_operacao"])){
			@query("ALTER TABLE operacao_responsavel ADD KEY idx_operacao (opre_nb_operacao_id)");
		}
		if(!isset($idxHas["idx_entidade"])){
			@query("ALTER TABLE operacao_responsavel ADD KEY idx_entidade (opre_nb_entidade_id)");
		}
	}

	function carregarResponsaveisOperacao(int $operacaoId): array{
		if($operacaoId <= 0){
			return [];
		}
		ensureOperacaoResponsavelSchema();
		$rows = mysqli_fetch_all(query(
			"SELECT
				orv.opre_nb_entidade_id AS id,
				orv.opre_tx_assinar_governanca AS assinar_governanca,
				orv.opre_nb_ordem AS ordem,
				e.enti_tx_nome AS nome,
				e.enti_tx_email AS email,
				g.grup_tx_nome AS setor_nome,
				o.oper_tx_nome AS cargo_nome
			FROM operacao_responsavel orv
			LEFT JOIN entidade e ON e.enti_nb_id = orv.opre_nb_entidade_id
			LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
			LEFT JOIN operacao o ON o.oper_nb_id = e.enti_tx_tipoOperacao
			WHERE orv.opre_nb_operacao_id = ?
				AND orv.opre_tx_status = 'ativo'
			ORDER BY e.enti_tx_nome ASC",
			"i",
			[$operacaoId]
		), MYSQLI_ASSOC);
		return is_array($rows) ? $rows : [];
	}

	function api_responsaveis_cargo(){
		header("Content-Type: application/json; charset=utf-8");
		$cargoId = intval($_GET["cargo_id"] ?? 0);
		if($cargoId <= 0){
			http_response_code(400);
			echo json_encode(["error" => "cargo_id inválido"]);
			return;
		}

		$rows = carregarResponsaveisOperacao($cargoId);
		$out = [];
		foreach(($rows ?: []) as $r){
			$id = intval($r["id"] ?? 0);
			if($id <= 0){
				continue;
			}
			$nome = trim(strval($r["nome"] ?? ""));
			$email = trim(strval($r["email"] ?? ""));
			$setorNome = trim(strval($r["setor_nome"] ?? ""));
			$cargoNome = trim(strval($r["cargo_nome"] ?? ""));
			$label = $nome !== "" ? $nome : ("ID " . $id);
			if($setorNome !== ""){ $label .= " - S: " . $setorNome; }
			if($cargoNome !== ""){ $label .= " - C: " . $cargoNome; }
			if($email !== ""){
				$label .= " | " . $email;
			}
			$out[] = ["id" => $id, "text" => $label];
		}
		echo json_encode($out);
	}

	function salvarResponsaveisOperacao(int $operacaoId, array $responsaveisIds, array $assinaFlags, array $ordens): void{
		if($operacaoId <= 0){
			return;
		}
		ensureOperacaoResponsavelSchema();

		$ids = array_values(array_unique(array_filter(array_map("intval", $responsaveisIds), fn($v) => $v > 0)));
		query(
			"UPDATE operacao_responsavel
			SET opre_tx_status = 'inativo'
			WHERE opre_nb_operacao_id = ?",
			"i",
			[$operacaoId]
		);

		if(empty($ids)){
			return;
		}

		$agora = date("Y-m-d H:i:s");
		$assinaNorm = [];
		foreach($ids as $entidadeId){
			$assinar = "nao";
			if(isset($assinaFlags[$entidadeId]) && strtolower(trim(strval($assinaFlags[$entidadeId]))) === "sim"){
				$assinar = "sim";
			}
			$assinaNorm[$entidadeId] = $assinar;
		}

		$ordemNorm = [];
		$signers = [];
		foreach($ids as $entidadeId){
			if(($assinaNorm[$entidadeId] ?? "nao") !== "sim"){
				continue;
			}
			$o = intval($ordens[$entidadeId] ?? 0);
			$signers[] = [
				"id" => $entidadeId,
				"ordem" => $o > 0 ? $o : PHP_INT_MAX
			];
		}
		usort($signers, function($a, $b){
			$ao = intval($a["ordem"] ?? PHP_INT_MAX);
			$bo = intval($b["ordem"] ?? PHP_INT_MAX);
			if($ao === $bo){
				return intval($a["id"] ?? 0) <=> intval($b["id"] ?? 0);
			}
			return $ao <=> $bo;
		});
		$pos = 1;
		foreach($signers as $s){
			$eid = intval($s["id"] ?? 0);
			if($eid <= 0){
				continue;
			}
			$ordemNorm[$eid] = $pos;
			$pos++;
		}

		foreach($ids as $entidadeId){
			$assinar = $assinaNorm[$entidadeId] ?? "nao";
			$ordem = ($assinar === "sim") ? intval($ordemNorm[$entidadeId] ?? 0) : 0;

			query(
				"INSERT INTO operacao_responsavel
					(opre_nb_operacao_id, opre_nb_entidade_id, opre_tx_assinar_governanca, opre_nb_ordem, opre_tx_status, opre_tx_dataCadastro)
				VALUES
					(?, ?, ?, ?, 'ativo', ?)
				ON DUPLICATE KEY UPDATE
					opre_tx_assinar_governanca = VALUES(opre_tx_assinar_governanca),
					opre_nb_ordem = VALUES(opre_nb_ordem),
					opre_tx_status = 'ativo',
					opre_tx_dataCadastro = VALUES(opre_tx_dataCadastro)",
				"iisis",
				[$operacaoId, $entidadeId, $assinar, $ordem, $agora]
			);
		}
	}

	function excluirOperacao(){
		ensureOperacaoResponsavelSchema();
		if(!empty($_POST["id"])){
			query("DELETE FROM operacao_responsavel WHERE opre_nb_operacao_id = ?", "i", [intval($_POST["id"])]);
		}
		remover("operacao",$_POST["id"]);
		index();
		exit;

	}
	function modificarOperacao(){
		$a_mod = carregar("operacao", $_POST["id"]);
		$resps = carregarResponsaveisOperacao(intval($a_mod["oper_nb_id"] ?? 0));
		$_POST["responsaveis"] = array_values(array_filter(array_map(fn($r) => intval($r["id"] ?? 0), $resps), fn($v) => $v > 0));
		$_POST["responsavel_assina"] = [];
		$_POST["responsavel_ordem"] = [];
		foreach($resps as $r){
			$id = intval($r["id"] ?? 0);
			if($id <= 0){
				continue;
			}
			$_POST["responsavel_assina"][$id] = (strtolower(trim(strval($r["assinar_governanca"] ?? "nao"))) === "sim") ? "sim" : "nao";
			$_POST["responsavel_ordem"][$id] = intval($r["ordem"] ?? 0);
		}
		[$_POST["id"], $_POST["nome"], $_POST["status"]] = [$a_mod["oper_nb_id"], $a_mod["oper_tx_nome"], $a_mod["oper_tx_status"]];
		
		layout_operacao();
		exit;
	}

	function cadastra_operacao() {
		global $conn;

		$novoFeriado = [
			"oper_tx_nome" => $_POST["nome"],
			"oper_tx_status" => "ativo"
		];

		$operacaoId = 0;
		if(!empty($_POST["id"])){
			atualizar("operacao", array_keys($novoFeriado), array_values($novoFeriado), $_POST["id"]);
			$operacaoId = intval($_POST["id"]);
		}else{
			$novoFeriado["oper_nb_userCadastro"] = $_SESSION["user_nb_id"];
			$novoFeriado["oper_tx_dataCadastro"] = date("Y-m-d H:i:s");

			inserir("operacao", array_keys($novoFeriado), array_values($novoFeriado));
			$operacaoId = !empty($conn) ? intval(mysqli_insert_id($conn)) : 0;
			if($operacaoId <= 0){
				$last = mysqli_fetch_assoc(query("SELECT MAX(oper_nb_id) AS id FROM operacao"));
				$operacaoId = intval($last["id"] ?? 0);
			}
		}

		$responsaveis = $_POST["responsaveis"] ?? [];
		$responsaveis = is_array($responsaveis) ? $responsaveis : [];
		$assinaFlags = $_POST["responsavel_assina"] ?? [];
		$assinaFlags = is_array($assinaFlags) ? $assinaFlags : [];
		$ordens = $_POST["responsavel_ordem"] ?? [];
		$ordens = is_array($ordens) ? $ordens : [];
		if($operacaoId > 0){
			salvarResponsaveisOperacao($operacaoId, $responsaveis, $assinaFlags, $ordens);
		}

		index();
		exit;
	}

	function layout_operacao() {
		global $a_mod;

		cabecalho("Cadastro de Feriado");

		ensureOperacaoResponsavelSchema();

		$camposAviso = [
			"<div class='col-sm-12 margin-bottom-5 campo-fit-content'>
				<div class='alert alert-info' style='margin:0;'>
					<b>Responsáveis do Cargo (Governança)</b><br>
					Se algum responsável estiver marcado como <b>assina governança</b>, então documentos enviados por governança para funcionários deste cargo também deverão ter a assinatura desse responsável na ordem escolhida.
				</div>
			</div>"
		];

		$camposTopo = [
			campo("Nome*", "nome", $_POST["nome"], 4),
		];

		$camposResponsaveis = [
			"<div class='col-sm-6 margin-bottom-5 campo-fit-content'>
				<label class='control-label'>Responsáveis do cargo</label>
				<select class='form-control input-sm resp-operacao' name='responsaveis[]' multiple='multiple' style='width:100%;'></select>
				<div class='help-block'>Selecione um ou mais responsáveis.</div>
			</div>",
			"<div class='col-sm-6 margin-bottom-5 campo-fit-content'>
				<label class='control-label'>Assinatura na governança</label>
				<div id='lista-resp-assina' class='well well-sm' style='min-height:42px; margin-bottom:0;'></div>
				<div class='help-block'>Marque quem deve assinar documentos por governança na ordem escolhida Ex: o 1ª Marcado ser o primeiro a assinar e assim por diante.</div>
			</div>"
		];

		$botoes = [
			botao("Gravar", "cadastra_operacao", "id", $_POST["id"], "", "", "btn btn-success"),
			criarBotaoVoltar()
		];
		
		echo abre_form("Dados do Cargo");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		$preIds = $_POST["responsaveis"] ?? [];
		$preIds = is_array($preIds) ? array_values(array_unique(array_filter(array_map("intval", $preIds), fn($v) => $v > 0))) : [];
		$preAssina = $_POST["responsavel_assina"] ?? [];
		$preAssina = is_array($preAssina) ? $preAssina : [];
		$preOrdens = $_POST["responsavel_ordem"] ?? [];
		$preOrdens = is_array($preOrdens) ? $preOrdens : [];
		$preLabels = [];
		if(!empty($preIds)){
			$placeholders = implode(",", array_fill(0, count($preIds), "?"));
			$types = str_repeat("i", count($preIds));
			$rows = mysqli_fetch_all(query(
				"SELECT enti_nb_id, enti_tx_nome, enti_tx_email
				FROM entidade
				WHERE enti_nb_id IN ({$placeholders})
				ORDER BY enti_tx_nome ASC",
				$types,
				$preIds
			), MYSQLI_ASSOC);
			foreach($rows as $r){
				$id = intval($r["enti_nb_id"] ?? 0);
				if($id <= 0){ continue; }
				$nome = trim(strval($r["enti_tx_nome"] ?? ""));
				$email = trim(strval($r["enti_tx_email"] ?? ""));
				$label = $nome !== "" ? $nome : ("ID " . $id);
				if($email !== ""){ $label .= " | " . $email; }
				$preLabels[$id] = $label;
			}
		}

		$baseUrl = strval(($_ENV["URL_BASE"] ?? "").($_ENV["APP_PATH"] ?? ""));
		$contexPath = strval(($_ENV["APP_PATH"] ?? "").($_ENV["CONTEX_PATH"] ?? ""));
		echo "<script>
			(function($){
				if(!$ || !$.fn){ return; }
				$(function(){
					var \$sel = $('.resp-operacao');
					if(!\$sel.length){ return; }

					var pre = ".json_encode(array_values($preIds), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";
					var labels = ".json_encode($preLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";
					var assinaMap = ".json_encode($preAssina, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";
					var ordemMap = ".json_encode($preOrdens, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";

					pre.forEach(function(id){
						var key = String(id);
						var text = labels[key] || labels[id] || ('ID ' + key);
						var opt = new Option(text, key, true, true);
						\$sel.append(opt);
					});

					if($.fn.select2){
						$.fn.select2.defaults.set('theme','bootstrap');
						var baseUrl = ".json_encode($baseUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";
						var contexPath = ".json_encode($contexPath, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";
						var cond = encodeURIComponent(\"AND enti_tx_status = 'ativo'\");

						function sanitizeText(v){
							v = (v === null || v === undefined) ? '' : String(v);
							v = v.replace(/^\\s*\\[\\s*\\]\\s*/g, '');
							v = v.replace(/^\\s*\\[\\s*\\]\\s*/g, '');
							return v.trim();
						}

						\$sel.select2({
							language: 'pt-BR',
							placeholder: 'Selecione',
							allowClear: true,
							width: '100%',
							ajax: {
								url: baseUrl + '/contex20/select2.php?path=' + encodeURIComponent(contexPath) + '&tabela=entidade&ordem=&limite=15&condicoes=' + cond,
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

					function syncAssinaMapFromDom(){
						var box = document.getElementById('lista-resp-assina');
						if(!box){ return; }
						var inputs = box.querySelectorAll('input[type=\"checkbox\"][data-resp-id]');
						inputs.forEach(function(inp){
							var id = inp.getAttribute('data-resp-id');
							if(!id){ return; }
							assinaMap[id] = inp.checked ? 'sim' : 'nao';
						});
					}

					function calcularPosicoes(selected){
						var checked = [];
						selected.forEach(function(id, idx){
							var key = String(id);
							var isChecked = (assinaMap && (assinaMap[key] === 'sim' || assinaMap[id] === 'sim'));
							if(!isChecked){ return; }
							var o = parseInt((ordemMap && (ordemMap[key] || ordemMap[id])) || '0', 10);
							checked.push({
								id: key,
								idx: idx,
								ordem: (isFinite(o) && o > 0) ? o : 999999
							});
						});
						checked.sort(function(a, b){
							if(a.ordem === b.ordem){
								return a.idx - b.idx;
							}
							return a.ordem - b.ordem;
						});
						var pos = {};
						checked.forEach(function(item, i){
							pos[item.id] = i + 1;
						});
						return pos;
					}

					function ordinal(n){
						n = parseInt(n || '0', 10);
						if(!isFinite(n) || n <= 0){ return ''; }
						return String(n) + 'ª';
					}

					function renderAssina(){
						var box = document.getElementById('lista-resp-assina');
						if(!box){ return; }
						var selected = \$sel.val() || [];
						box.innerHTML = '';
						if(selected.length === 0){
							box.innerHTML = '<span class=\"text-muted\">Nenhum responsável selecionado.</span>';
							return;
						}
						var pos = calcularPosicoes(selected);
						selected.forEach(function(id){
							var key = String(id);
							var text = (labels[key] || labels[id] || (\$sel.find('option[value=\"'+key+'\"]:selected').text()) || ('ID ' + key));
							text = (typeof sanitizeText === 'function') ? sanitizeText(text) : text;
							var isChecked = (assinaMap && (assinaMap[key] === 'sim' || assinaMap[id] === 'sim'));
							var checked = isChecked ? 'checked' : '';
							var badge = isChecked ? ordinal(pos[key] || 0) : '';
							var ordemVal = isChecked ? (pos[key] || 0) : 0;

							var row = document.createElement('div');
							row.style.display = 'flex';
							row.style.alignItems = 'center';
							row.style.gap = '10px';
							row.style.marginBottom = '6px';
							row.innerHTML =
								'<span class=\"label label-primary\" style=\"min-width:34px; text-align:center;\">'+(badge || '—')+'</span>'+
								'<label style=\"font-weight:normal; margin:0; flex:1;\">'+
									'<input data-resp-id=\"'+key+'\" type=\"checkbox\" name=\"responsavel_assina['+key+']\" value=\"sim\" '+checked+' style=\"margin-right:8px;\">'+
									'<span>'+text+'</span>'+
								'</label>'+
								'<input type=\"hidden\" name=\"responsavel_ordem['+key+']\" value=\"'+String(ordemVal)+'\">';
							box.appendChild(row);
						});
					}

					\$('#lista-resp-assina').on('change', 'input[type=\"checkbox\"][data-resp-id]', function(){
						var id = this.getAttribute('data-resp-id');
						if(!id){ return; }
						assinaMap[id] = this.checked ? 'sim' : 'nao';
						if(!this.checked){
							ordemMap[id] = 0;
						}
						renderAssina();
					});

					\$sel.on('change', function(){
						syncAssinaMapFromDom();
						var selected = \$sel.val() || [];
						Object.keys(assinaMap || {}).forEach(function(k){
							if(selected.indexOf(String(k)) === -1){
								delete assinaMap[k];
								delete ordemMap[k];
							}
						});
						renderAssina();
					});

					\$sel.on('select2:select', function(e){
						var d = e && e.params ? e.params.data : null;
						if(d && d.id){
							var t = d.text || labels[String(d.id)] || ('ID ' + d.id);
							t = (typeof sanitizeText === 'function') ? sanitizeText(t) : t;
							labels[String(d.id)] = t;
							var opt = \$sel.find('option[value=\"'+String(d.id)+'\"]');
							if(opt && opt.length){
								opt.text(t);
							}
						}
						renderAssina();
					});

					\$sel.on('select2:unselect', function(e){
						syncAssinaMapFromDom();
						var d = e && e.params ? e.params.data : null;
						if(d && d.id && assinaMap){ delete assinaMap[String(d.id)]; }
						renderAssina();
					});

					renderAssina();
				});
			})(window.jQuery);
		</script>";

		echo linha_form($camposTopo);
		echo linha_form($camposAviso);
		echo linha_form($camposResponsaveis);
		echo fecha_form($botoes);

		rodape();
	}


    function index() {
		global $CONTEX;
		
		//ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
        include "check_permission.php";
        // APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
        verificaPermissao('/cadastro_operacao.php');
		cabecalho("Cadastro de Cargos");
		ensureOperacaoResponsavelSchema();

		if(!isset($_POST["busca_status"])){
			$_POST["busca_status"] = "ativo";
		}

		$fields = [
			campo("Código", 		"busca_codigo", 	($_POST["busca_codigo"]?? ""), 	1, "", "maxlength='6'"),
			campo("Nome", 			"busca_nome_like", 		($_POST["busca_nome_like"]?? ""), 	8, "", "maxlength='65'"),
			combo("Status", 		"busca_status", 	($_POST["busca_status"]?? ""), 	2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]),
			//combo_net("Funcionário", "busca_usuario", $_POST["busca_usuario"]?? "", 4, "entidade", "", "", "enti_tx_matricula"),
		];
		
		$buttons[] = botao("Buscar", "index");

		$canInsert = false;
		include_once "check_permission.php";
		if(function_exists('temPermissaoMenu')){
			$canInsert = temPermissaoMenu('/cadastro_operacao.php');
		}
		if(is_int(stripos($_SESSION['user_tx_nivel'] ?? '', 'administrador')) || is_int(stripos($_SESSION['user_tx_nivel'] ?? '', 'super'))){
			$canInsert = true;
		}
		if($canInsert){
			$buttons[] = botao("Inserir", "layout_operacao","","","","","btn btn-success");
		}

		$buttons[] = '<button class="btn default" type="button" onclick="imprimirTabelaCompleta()">Imprimir</button>';

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons);

		// $logoEmpresa = mysqli_fetch_assoc(query(
        //     "SELECT empr_tx_logo FROM empresa
        //             WHERE empr_tx_status = 'ativo'
        //                 AND empr_tx_Ehmatriz = 'sim'
        //             LIMIT 1;"
        // ))["empr_tx_logo"];

		// echo "<div id='tituloRelatorio' style='display: none;'>
        //             <img style='width: 190px; height: 40px;' src='./imagens/logo_topo_cliente.png' alt='Logo Empresa Esquerda'>
		// 			<h1>Cadastro de Usuário</h1>
        //             <img style='width: 180px; height: 80px;' src='./$logoEmpresa' alt='Logo Empresa Direita'>
        //     </div>";

		//Grid dinâmico{
			$gridFields = [
				"CÓDIGO" 		=> "oper_nb_id",
				"NOME" 			=> "oper_tx_nome",
				// "USUÁRIO CADASTRO" 	=> "userCadastro(oper_nb_userCadastro)",
				"STATUS" 		=> "oper_tx_status",
				"RESPONSÁVEIS"	=> "responsaveis"
			];

			$camposBusca = [
				"busca_codigo" 		=> "oper_nb_id",
				"busca_nome_like" 	=> "oper_tx_nome",
				"busca_usuario" 	=> "oper_nb_userCadastro",
				"busca_status" 		=> "oper_tx_status",
			];

			$queryBase =
				"SELECT ".implode(", ", array_values($gridFields))." FROM ( "
				."SELECT
					o.oper_nb_id,
					o.oper_tx_nome,
					o.oper_tx_status,
					o.oper_nb_userCadastro,
					COALESCE(r.responsaveis, '') AS responsaveis
				FROM operacao o
				LEFT JOIN (
					SELECT
						orv.opre_nb_operacao_id AS operacao_id,
						GROUP_CONCAT(DISTINCT e.enti_tx_nome ORDER BY e.enti_tx_nome SEPARATOR ', ') AS responsaveis
					FROM operacao_responsavel orv
					INNER JOIN entidade e ON e.enti_nb_id = orv.opre_nb_entidade_id
					WHERE orv.opre_tx_status = 'ativo'
					GROUP BY orv.opre_nb_operacao_id
				) r ON r.operacao_id = o.oper_nb_id
				) t";

			$actions = criarIconesGrid(
				["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
				["cadastro_operacao.php", "cadastro_operacao.php"],
				["modificarOperacao()", "excluirOperacao()"]
			);
	
			$actions["functions"][1] .= 
				"esconderInativar('glyphicon glyphicon-remove search-remove', 9);"
			;
	
			$gridFields["actions"] = $actions["tags"];
	
			$jsFunctions =
				"const funcoesInternas = function(){
					".implode(" ", $actions["functions"])."
				}"
			;
			echo gridDinamico("tabelaOperacao", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		//}


		rodape();
	}
