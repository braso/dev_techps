<?php
/*
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
*/

	include "conecta.php";

	function ensureSetorResponsavelSchema(){
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

	function carregarResponsaveisSetor(int $setorId): array {
		if($setorId <= 0){
			return [];
		}
		ensureSetorResponsavelSchema();
		$rows = mysqli_fetch_all(query(
			"SELECT
				sr.sres_nb_entidade_id AS id,
				sr.sres_tx_assinar_governanca AS assinar_governanca,
				e.enti_tx_nome AS nome,
				e.enti_tx_email AS email
			FROM setor_responsavel sr
			LEFT JOIN entidade e ON e.enti_nb_id = sr.sres_nb_entidade_id
			WHERE sr.sres_nb_setor_id = ?
				AND sr.sres_tx_status = 'ativo'
			ORDER BY e.enti_tx_nome ASC",
			"i",
			[$setorId]
		), MYSQLI_ASSOC);
		return is_array($rows) ? $rows : [];
	}

	function salvarResponsaveisSetor(int $setorId, array $responsaveisIds, array $assinaFlags): void {
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
			$assinar = "nao";
			if(isset($assinaFlags[$entidadeId]) && strtolower(trim(strval($assinaFlags[$entidadeId]))) === "sim"){
				$assinar = "sim";
			}

			query(
				"INSERT INTO setor_responsavel
					(sres_nb_setor_id, sres_nb_entidade_id, sres_tx_assinar_governanca, sres_tx_status, sres_tx_dataCadastro)
				VALUES
					(?, ?, ?, 'ativo', ?)
				ON DUPLICATE KEY UPDATE
					sres_tx_assinar_governanca = VALUES(sres_tx_assinar_governanca),
					sres_tx_status = 'ativo',
					sres_tx_dataCadastro = VALUES(sres_tx_dataCadastro)",
				"iiss",
				[$setorId, $entidadeId, $assinar, $agora]
			);
		}
	}

    function excluirSetor(){
		ensureSetorResponsavelSchema();
		if(!empty($_POST["id"])){
			query("DELETE FROM setor_responsavel WHERE sres_nb_setor_id = ?", "i", [intval($_POST["id"])]);
		}
		remover("grupos_documentos",$_POST["id"]);
		index();
		exit;
	}

    function modificarSetor(){
		$a_mod = carregar("grupos_documentos", $_POST["id"]);
        $subSetor = mysqli_fetch_all(query(
            "SELECT sbgr_nb_id, sbgr_tx_nome
            FROM `sbgrupos_documentos`
            WHERE `sbgr_nb_idgrup` = {$a_mod["grup_nb_id"]}
            ORDER BY sbgr_nb_id ASC"
        ), MYSQLI_ASSOC);

		$resps = carregarResponsaveisSetor(intval($a_mod["grup_nb_id"] ?? 0));
		$_POST["responsaveis"] = array_values(array_filter(array_map(fn($r) => intval($r["id"] ?? 0), $resps), fn($v) => $v > 0));
		$_POST["responsavel_assina"] = [];
		foreach($resps as $r){
			$id = intval($r["id"] ?? 0);
			if($id <= 0){
				continue;
			}
			$_POST["responsavel_assina"][$id] = (strtolower(trim(strval($r["assinar_governanca"] ?? "nao"))) === "sim") ? "sim" : "nao";
		}

		[$_POST["id"], $_POST["nome"], $_POST["status"], $_POST["subsetores"]] = [$a_mod["grup_nb_id"], $a_mod["grup_tx_nome"], $a_mod["grup_tx_status"], $subSetor];
		
		layout_Setor();
		exit;
	}

    function cadastra_setor() {

        $_POST["nome"] = trim($_POST["nome"] ?? "");
        $errorMsg = conferirCamposObrig(["nome" => "Nome"], $_POST);
        if(!empty($errorMsg)){
            set_status("ERRO: ".$errorMsg);
            error_log("cadastro_setor obrigatorios: ".$errorMsg);
            layout_setor();
            exit;
        }

        $novoSetor = [
            "grup_tx_nome" => $_POST["nome"],
            "grup_tx_status" => "ativo"
        ];
        error_log("cadastro_setor novoSetor: ".json_encode($novoSetor));

		$setorId = 0;

		if(!empty($_POST["id"])){
			atualizar("grupos_documentos", array_keys($novoSetor), array_values($novoSetor), $_POST["id"]);
			$setorId = intval($_POST["id"]);

            if (!empty($_POST['subsetores_excluir'])) {
                error_log("cadastro_setor subsetores_excluir: ".json_encode($_POST['subsetores_excluir']));
                $idsExcluir = array_filter(array_map(function($v){ return trim($v); }, explode(',', $_POST['subsetores_excluir'])));
                error_log("cadastro_setor idsExcluir: ".json_encode($idsExcluir));
                global $conn;

                foreach ($idsExcluir as $id) {
                    query("DELETE FROM sbgrupos_documentos WHERE sbgr_nb_id = ?", "i", [(int)$id]);
                    error_log("cadastro_setor excluir subsetor: id=".$id." affected=".mysqli_affected_rows($conn));
                }
            }

			if (!empty($_POST["subsetores"])) {

                // Busca registros existentes
                $result = query("SELECT sbgr_nb_id, sbgr_tx_nome FROM sbgrupos_documentos WHERE sbgr_nb_idgrup = ? ORDER BY sbgr_nb_id ASC", "i", [(int)$_POST['id']]);

				// Converte em array associativo com a chave = ID
				$subSetorExistente = [];
				while ($row = mysqli_fetch_assoc($result)) {
					$subSetorExistente[$row['sbgr_nb_id']] = $row;
				}

                foreach ($_POST["subsetores"] as $id => $nome) {
                    if (empty($nome)) {
                        continue;
                    }

					$novoSubSetor = [
						"sbgr_tx_nome"   => $nome,
						"sbgr_nb_idgrup" => $_POST["id"],
						"sbgr_tx_status" => "ativo"
					];

                    if (!empty($id) && isset($subSetorExistente[$id])) {
                        // Atualiza registro existente
                        atualizar(
                            "sbgrupos_documentos",
                            array_keys($novoSubSetor),
                            array_values($novoSubSetor),
                            $id
                        );
                        error_log("cadastro_setor atualizar subsetor: id=".$id);

					} else {
                        // Insere novo registro
                        $res = inserir(
                            "sbgrupos_documentos",
                            array_keys($novoSubSetor),
                            array_values($novoSubSetor)
                        );
                        error_log("cadastro_setor inserir subsetor res: ".json_encode($res));
                        if(gettype($res[0]) == "object"){
                            set_status("ERRO: ".$res[0]->getMessage());
                            error_log("cadastro_setor inserir subsetor erro: ".$res[0]->getMessage());
                            layout_setor();
                            exit;
                        }
					}
				}
			}
		}else{

                $id = inserir("grupos_documentos", array_keys($novoSetor), array_values($novoSetor));
                error_log("cadastro_setor inserir grupo res: ".json_encode($id));
                if(gettype($id[0]) == "object"){
                    set_status("ERRO: ".$id[0]->getMessage());
                    error_log("cadastro_setor inserir grupo erro: ".$id[0]->getMessage());
                    layout_setor();
                    exit;
                }
				$setorId = intval($id[0] ?? 0);
				
				if(!empty($_POST["subsetores"])){
                    error_log("cadastro_setor subsetores inserir: ".json_encode($_POST["subsetores"]));
                    foreach($_POST["subsetores"] as $subsetor){
                        if(!empty($subsetor)){
                            $novoSubSetor = [
                                "sbgr_tx_nome" => $subsetor,
                                "sbgr_nb_idgrup" => $id[0],
                                "sbgr_tx_status" => "ativo"
                            ];

                            $res = inserir("sbgrupos_documentos", array_keys($novoSubSetor), array_values($novoSubSetor));
                            error_log("cadastro_setor inserir subsetor res: ".json_encode($res));
                            if(gettype($res[0]) == "object"){
                                set_status("ERRO: ".$res[0]->getMessage());
                                error_log("cadastro_setor inserir subsetor erro: ".$res[0]->getMessage());
                                layout_setor();
                                exit;
                            }
                        }
                    }
                }
			}

		$responsaveis = $_POST["responsaveis"] ?? [];
		$responsaveis = is_array($responsaveis) ? $responsaveis : [];
		$assinaFlags = $_POST["responsavel_assina"] ?? [];
		$assinaFlags = is_array($assinaFlags) ? $assinaFlags : [];
		if(!empty($setorId)){
			salvarResponsaveisSetor($setorId, $responsaveis, $assinaFlags);
		}

		index();
		exit;
	}

function campoSubSetor($nome, $variavel, $valores = [], $tamanho = 2) {
    static $contador = 0;
    $contador++;

    $idLista = "listaSubsetores_$contador";
    $campoExcluir = "{$variavel}_excluir";

    if (!is_array($valores)) {
        $valores = array_filter([$valores]);
    }

    $camposExistentes = '';

    // Placeholder fixo "Novo Setor" (não enviado no POST)
    $camposExistentes .= "
    <div class='col-sm-{$tamanho} margin-bottom-5 subsetor-item subsetor-placeholder'>
        <label class='control-label'>{$nome}</label>
        <div style='display:flex; align-items:center;'>
            <input
                type='text'
                class='form-control input-sm campo-fit-content'
                placeholder='Novo Setor'
                style='width:120px;'
            >
            <button class='btn btn-primary btn-sm adicionar-novo' type='button' title='Adicionar novo subsetor'
                style='height:40px; margin-top: -10px; margin-left:-1px; border-top-left-radius:0; border-bottom-left-radius:0;'>
                <span class='glyphicon glyphicon-plus'></span>
            </button>
        </div>
    </div>";

    // Subsetores existentes (apenas com botão remover)
    if (!empty($valores)) {
        foreach ($valores as $valor) {
            $id = isset($valor['sbgr_nb_id']) ? htmlspecialchars($valor['sbgr_nb_id'], ENT_QUOTES) : '';
            $nomeSubsetor = isset($valor['sbgr_tx_nome']) ? htmlspecialchars($valor['sbgr_tx_nome'], ENT_QUOTES) : '';

            $remBtn = "<button class='btn btn-danger btn-sm remover' type='button' title='Remover subsetor'
                        style='height:40px; margin-top: -10px; margin-left:-1px; border-top-left-radius:0; border-bottom-left-radius:0;'>
                        <span class='glyphicon glyphicon-remove'></span>
                    </button>";

            $camposExistentes .= "
            <div class='col-sm-{$tamanho} margin-bottom-5 subsetor-item' data-id='{$id}'>
                <label class='control-label'>{$nome}</label>
                <div style='display:flex; align-items:center;'>
                    <input
                        type='text'
                        name='{$variavel}[{$id}]'
                        class='form-control input-sm campo-fit-content'
                        placeholder='Nome do subsetor'
                        style='width:120px;'
                        value='{$nomeSubsetor}'
                    >
                    {$remBtn}
                </div>
            </div>";
        }
    }

    $campo = "
    <div class='form-group row' id='{$idLista}'>
        {$camposExistentes}
        <input type='hidden' name='{$campoExcluir}' id='{$campoExcluir}' value=''>
    </div>";

    $js = "
    <script>
    (function($){
        $(function(){
            var lista = $('#{$idLista}');
            var campoExcluir = $('#{$campoExcluir}');

            // Adicionar novo subsetor usando o texto digitado no placeholder
            lista.on('click', '.adicionar-novo', function(){
                var inputOrigem = $(this).closest('.subsetor-item').find('input');
                var texto = inputOrigem.val().trim();
                if (texto === '') {
                    alert('Digite algo antes de adicionar.');
                    return;
                }
                var novo = `
                <div class='col-sm-{$tamanho} margin-bottom-5 subsetor-item'>
                    <label class='control-label'>$nome</label>
                    <div style='display:flex; align-items:center;'>
                        <input type='text' name='{$variavel}[]'
                            class='form-control input-sm campo-fit-content'
                            placeholder='Nome do subsetor'
                            style='width:120px;' value='` + texto + `'>
                        <button class='btn btn-danger btn-sm remover' type='button'
                                title='Remover subsetor'
                                style='height:40px; margin-top:-10px; margin-left:-1px; border-top-left-radius:0; border-bottom-left-radius:0;'>
                            <span class='glyphicon glyphicon-remove'></span>
                        </button>
                    </div>
                </div>`;
                lista.append(novo);
                inputOrigem.val('');
            });

            // Excluir subsetor
            lista.on('click', '.remover', function(){
                var item = $(this).closest('.subsetor-item');
                var id = item.data('id');

                if (id) {
                    var existentes = campoExcluir.val();
                    campoExcluir.val(existentes ? existentes + ',' + id : id);
                }

                item.remove();
            });
        });
    })(jQuery);
    </script>";

    return $campo . $js;
}

    function layout_setor() {
        global $a_mod;

        cabecalho("Cadastro de Setor");

		ensureSetorResponsavelSchema();

        $campoStatus = "";
        if (!empty($_POST["id"])) {
            $campoStatus = combo("Status", "busca_banco", $_POST["busca_banco"]?? "", 2, [ "ativo" => "Ativo", "inativo" => "Inativo"]);
        }

        if (empty($_POST["HTTP_REFERER"])) {
            $refer = !empty($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : ($_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_setor.php");
            $_POST["HTTP_REFERER"] = $refer;
        }

		$hasStatus = !empty($campoStatus);
		$nomeWidth = $hasStatus ? 3 : 4;
		$subsetorWidth = $hasStatus ? 7 : 8;

		$camposTopo = [
			campo("Nome*", "nome", $_POST["nome"], $nomeWidth),
			$campoStatus,
			"<div class='col-sm-{$subsetorWidth} margin-bottom-5 campo-fit-content'>"
				.campoSubSetor('Subsetores', 'subsetores', $_POST["subsetores"] ?? [])
			."</div>"
		];

		$camposAviso = [
			"<div class='col-sm-12 margin-bottom-5 campo-fit-content'>
				<div class='alert alert-info' style='margin:0;'>
					<b>Responsáveis do Setor (Governança)</b><br>
					Se algum responsável estiver marcado como <b>assina governança</b>, então documentos enviados por governança para funcionários deste setor também deverão ter a assinatura desse responsável na ordem escolhida.
				</div>
			</div>"
		];

		$camposResponsaveis = [
			"<div class='col-sm-6 margin-bottom-5 campo-fit-content'>
				<label class='control-label'>Responsáveis do setor</label>
				<select class='form-control input-sm resp-setor' name='responsaveis[]' multiple='multiple' style='width:100%;'></select>
				<div class='help-block'>Selecione um ou mais responsáveis.</div>
			</div>",
			"<div class='col-sm-6 margin-bottom-5 campo-fit-content'>
				<label class='control-label'>Assinatura na governança</label>
				<div id='lista-resp-assina' class='well well-sm' style='min-height:42px; margin-bottom:0;'></div>
				<div class='help-block'>Marque quem deve assinar documentos por governança na ordem escolhida Ex: o 1ª Marcado ser o primeiro a assinar e assim por diante.</div>
			</div>"
		];

		$botoes = [
			botao("Gravar", "cadastra_setor", "id", $_POST["id"], "", "", "btn btn-success"),
			criarBotaoVoltar()
		];
		
        echo abre_form("Dados do setor");
        echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		$preIds = $_POST["responsaveis"] ?? [];
		$preIds = is_array($preIds) ? array_values(array_unique(array_filter(array_map("intval", $preIds), fn($v) => $v > 0))) : [];
		$preAssina = $_POST["responsavel_assina"] ?? [];
		$preAssina = is_array($preAssina) ? $preAssina : [];
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

		echo "<script>
			(function($){
				if(!$ || !$.fn){ return; }
				$(function(){
					var \$sel = $('.resp-setor');
					if(!\$sel.length){ return; }

					var pre = ".json_encode(array_values($preIds), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";
					var labels = ".json_encode($preLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";
					var assinaMap = ".json_encode($preAssina, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";

					pre.forEach(function(id){
						var key = String(id);
						var text = labels[key] || labels[id] || ('ID ' + key);
						var opt = new Option(text, key, true, true);
						\$sel.append(opt);
					});

					if($.fn.select2){
						$.fn.select2.defaults.set('theme','bootstrap');
						var baseUrl = ".json_encode($_ENV["URL_BASE"].$_ENV["APP_PATH"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";
						var contexPath = ".json_encode($_ENV["APP_PATH"].$_ENV["CONTEX_PATH"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).";
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

					function renderAssina(){
						var box = document.getElementById('lista-resp-assina');
						if(!box){ return; }
						var selected = \$sel.val() || [];
						box.innerHTML = '';
						if(selected.length === 0){
							box.innerHTML = '<span class=\"text-muted\">Nenhum responsável selecionado.</span>';
							return;
						}
						selected.forEach(function(id){
							var key = String(id);
							var text = (labels[key] || labels[id] || (\$sel.find('option[value=\"'+key+'\"]:selected').text()) || ('ID ' + key));
							text = (typeof sanitizeText === 'function') ? sanitizeText(text) : text;
							var checked = (assinaMap && (assinaMap[key] === 'sim' || assinaMap[id] === 'sim')) ? 'checked' : '';

							var row = document.createElement('div');
							row.style.display = 'flex';
							row.style.alignItems = 'center';
							row.style.gap = '10px';
							row.style.marginBottom = '6px';
							row.innerHTML =
								'<label style=\"font-weight:normal; margin:0; flex:1;\">'+
									'<input data-resp-id=\"'+key+'\" type=\"checkbox\" name=\"responsavel_assina['+key+']\" value=\"sim\" '+checked+' style=\"margin-right:8px;\">'+
									'<span>'+text+'</span>'+
								'</label>';
							box.appendChild(row);
						});
					}

					\$('#lista-resp-assina').on('change', 'input[type=\"checkbox\"][data-resp-id]', function(){
						var id = this.getAttribute('data-resp-id');
						if(!id){ return; }
						assinaMap[id] = this.checked ? 'sim' : 'nao';
					});

					\$sel.on('change', function(){
						syncAssinaMapFromDom();
						var selected = \$sel.val() || [];
						Object.keys(assinaMap || {}).forEach(function(k){
							if(selected.indexOf(String(k)) === -1){
								delete assinaMap[k];
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
		verificaPermissao('/cadastro_setor.php');

        cabecalho("Cadastro de Setor");
		ensureSetorResponsavelSchema();

        $fields = [
			campo("Código", 		"busca_codigo", 	($_POST["busca_codigo"]?? ""), 	1, "", "maxlength='6'"),
			campo("Nome", 			"busca_nome_like", 		($_POST["busca_nome_like"]?? ""), 	3, "", "maxlength='65'"),
            combo("Status", 		"busca_status", 	($_POST["busca_status"]?? "ativo"), 	2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]),
		];

		$buttons[] = botao("Buscar", "index");

		$canInsert = false;
		include_once "check_permission.php";
		if(function_exists('temPermissaoMenu')){
			$canInsert = temPermissaoMenu('/cadastro_setor.php');
		}
		if(is_int(stripos($_SESSION['user_tx_nivel'] ?? '', 'administrador')) || is_int(stripos($_SESSION['user_tx_nivel'] ?? '', 'super'))){
			$canInsert = true;
		}
		if($canInsert){
			$buttons[] = botao("Inserir", "layout_setor()","","","","","btn btn-success");
		}

		// $buttons[] = '<button class="btn default" type="button" onclick="imprimirTabelaCompleta()">Imprimir</button>';

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons);

        $gridFields = [
            "CÓDIGO" 		=> "grup_nb_id",
            "NOME" 			=> "grup_tx_nome",
            "STATUS" 	    => "grup_tx_status",
			"SUBSETORES"    	=> "subgrupos",
			"RESPONSÁVEIS"    	=> "responsaveis",
        ];

        $camposBusca = [
            "busca_codigo" 		=> "grup_nb_id",
            "busca_nome_like" 	=> "grup_tx_nome",
            "busca_status" 		=> "grup_tx_status",
        ];

        $queryBase = 
            "SELECT ".implode(", ", array_values($gridFields))." FROM ( "
			."SELECT 
				g.grup_nb_id,
				g.grup_tx_nome,
				g.grup_tx_status,
				GROUP_CONCAT(s.sbgr_tx_nome SEPARATOR ', ') AS subgrupos,
				COALESCE(r.responsaveis, '') AS responsaveis
			FROM grupos_documentos g"
			." LEFT JOIN (
				SELECT
					sr.sres_nb_setor_id AS setor_id,
					GROUP_CONCAT(DISTINCT e.enti_tx_nome ORDER BY e.enti_tx_nome SEPARATOR ', ') AS responsaveis
				FROM setor_responsavel sr
				INNER JOIN entidade e ON e.enti_nb_id = sr.sres_nb_entidade_id
				WHERE sr.sres_tx_status = 'ativo'
				AND e.enti_tx_status = 'ativo'
				GROUP BY sr.sres_nb_setor_id
			) r ON r.setor_id = g.grup_nb_id"
			." LEFT JOIN sbgrupos_documentos s 
				ON g.grup_nb_id = s.sbgr_nb_idgrup
						GROUP BY 
							g.grup_nb_id,
							g.grup_tx_nome,
							g.grup_tx_status,
							r.responsaveis
					) AS final "
        ;

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
            ["cadastro_setor.php", "cadastro_setor.php"],
            ["modificarSetor()", "excluirSetor()"]
        );

        $actions["functions"][1] .= 
            "esconderInativar('glyphicon glyphicon-remove search-remove', 2);"
        ;

        $gridFields["actions"] = $actions["tags"];

        $jsFunctions =
            "const funcoesInternas = function(){
                ".implode(" ", $actions["functions"])."
            }"
        ;

        echo gridDinamico("tabelaSetor", $gridFields, $camposBusca, $queryBase, $jsFunctions);
        rodape();
    }
