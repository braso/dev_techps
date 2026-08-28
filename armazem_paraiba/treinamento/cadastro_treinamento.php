<?php
	include_once __DIR__."/../load_env.php";
	include_once __DIR__."/../conecta.php";

	// Diretório de upload
	$uploadDir = __DIR__ . "/uploads/";
	if (!is_dir($uploadDir)) {
		mkdir($uploadDir, 0755, true);
	}

	// --- ROTEADOR DE AÇÕES ---
	if(!empty($_POST['acao'])){
		$acao = $_POST['acao'];
		$acao = preg_replace('/\(.*\)$/', '', $acao);
		if(function_exists($acao)){
			$acao();
			exit;
		}
	}

	// =====================================================
	// FUNÇÕES AUXILIARES
	// =====================================================

	function registrarLogTreinamento($treinamentoId, $usuarioId, $evento, $detalhe = "") {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		query(
			"INSERT INTO treinamento_log (trelog_nb_treinamento_id, trelog_nb_usuario_id, trelog_tx_evento, trelog_tx_detalhe, trelog_tx_ip, trelog_tx_user_agent) VALUES (?, ?, ?, ?, ?, ?)",
			"iissss",
			[$treinamentoId, $usuarioId, $evento, $detalhe, $ip, $userAgent]
		);
	}

	// =====================================================
	// CRUD
	// =====================================================

	function cadastrar() {
		global $conn, $uploadDir;

		$camposObrig = ["titulo" => "Título", "status" => "Status"];
		$errorMsg = conferirCamposObrig($camposObrig, $_POST);
		if (!empty($errorMsg)) {
			set_status("ERRO: " . $errorMsg);
			index();
			exit;
		}

		$dup = mysqli_fetch_assoc(query(
			"SELECT trei_nb_id FROM treinamento WHERE trei_tx_titulo = ? AND trei_nb_id != ?",
			"si",
			[$_POST["titulo"], $_POST["id"] ?? 0]
		));
		if (!empty($dup)) {
			set_status("ERRO: Já existe um treinamento com este título!");
			index();
			exit;
		}

		$novo = [
			"trei_tx_titulo" => $_POST["titulo"],
			"trei_tx_descricao" => $_POST["descricao"] ?? null,
			"trei_tx_conteudo_programatico" => $_POST["conteudo_programatico"] ?? null,
			"trei_tx_tipo" => $_POST["tipo"] ?? "treinamento",
			"trei_tx_tipo_treinamento" => $_POST["tipo_treinamento"] ?? "eventual",
			"trei_tx_url_video" => $_POST["url_video"] ?? null,
			"trei_tx_tipo_video" => $_POST["tipo_video"] ?? "youtube",
			"trei_nb_carga_horaria" => (function() {
				$segundos = 0;
				if (!empty($_POST["carga_horaria"])) {
					$partes = array_map('intval', explode(":", $_POST["carga_horaria"]));
					$segundos = (int)($partes[0] ?? 0) * 60 + (int)($partes[1] ?? 0);
				}
				return $segundos;
			})(),
			"trei_nb_dias_validade" => (int)($_POST["dias_validade"] ?? 365),
			"trei_tx_status" => $_POST["status"] ?? "ativo",
			"trei_nb_obrigatorio" => isset($_POST["obrigatorio"]) ? 1 : 0,
			"trei_nb_nota_minima_aprovacao" => (int)($_POST["nota_minima_aprovacao"] ?? 70),
			"trei_nb_quantidade_questoes_prova" => (int)($_POST["quantidade_questoes_prova"] ?? 5),
			"trei_dt_data_atualiza" => date("Y-m-d H:i:s")
		];

		$perfisPermitidos = $_POST["perfis_permitidos"] ?? [];
		$perfisPermitidos = array_map('intval', $perfisPermitidos);
		$novo["trei_tx_tipo_usuario_permitido"] = !empty($perfisPermitidos) ? json_encode($perfisPermitidos) : null;

		if (!empty($_POST["data_publicacao"])) {
			$dt = DateTime::createFromFormat('d/m/Y', $_POST["data_publicacao"]);
			$novo["trei_dt_data_publicacao"] = $dt ? $dt->format('Y-m-d') : null;
		}
		if (!empty($_POST["data_liberacao"])) {
			$dt = DateTime::createFromFormat('d/m/Y', $_POST["data_liberacao"]);
			$novo["trei_dt_data_liberacao"] = $dt ? $dt->format('Y-m-d') : null;
		}

		if (!empty($_POST["id"])) {
			atualizar("treinamento", array_keys($novo), array_values($novo), $_POST["id"]);
			$treinamentoId = $_POST["id"];
			registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "edicao", "Treinamento editado");
			set_status("Treinamento atualizado com sucesso!");
		} else {
			$novo["trei_dt_data_cadastro"] = date("Y-m-d H:i:s");
			$camposInsert = array_keys($novo);
			$valoresInsert = array_values($novo);
			$tiposInsert = "";
			foreach ($camposInsert as $campo) {
				$tiposInsert .= (strpos($campo, '_tx_') !== false || strpos($campo, '_dt_') !== false) ? "s" : "i";
			}
			$sqlInsert = "INSERT INTO treinamento (" . implode(", ", $camposInsert) . ") VALUES (" . implode(", ", array_fill(0, count($camposInsert), "?")) . ")";
			$result = query($sqlInsert, $tiposInsert, $valoresInsert);
			$treinamentoId = mysqli_insert_id($conn);
			registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "criacao", "Treinamento criado");
			set_status("Treinamento cadastrado com sucesso!");
		}

		// Upload do material de apoio (substitui o thumbnail)
		if (!empty($_FILES["material_arquivo"]["name"])) {
			$materialDir = __DIR__ . "/uploads/materiais/" . $treinamentoId . "/";
			if (!is_dir($materialDir)) {
				mkdir($materialDir, 0755, true);
			}
			$nomeOriginal = $_FILES["material_arquivo"]["name"];
			$ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
			$permitidos = ["pdf", "jpg", "jpeg", "png", "gif", "webp"];
			if (in_array($ext, $permitidos)) {
				$tamanho = $_FILES["material_arquivo"]["size"];
				$nomeSalvo = "mat_" . time() . "_" . rand(1000, 9999) . "." . $ext;
				if (move_uploaded_file($_FILES["material_arquivo"]["tmp_name"], $materialDir . $nomeSalvo)) {
					$cnt = mysqli_fetch_assoc(query(
						"SELECT COUNT(*) as total FROM treinamento_material WHERE tram_nb_treinamento_id = ?",
						"i", [$treinamentoId]
					));
					$ordem = ($cnt["total"] ?? 0) + 1;
					inserir("treinamento_material",
						["tram_nb_treinamento_id", "tram_tx_nome", "tram_tx_descricao", "tram_tx_arquivo", "tram_tx_tipo_arquivo", "tram_nb_tamanho", "tram_nb_ordem"],
						[$treinamentoId, $nomeOriginal, "", "materiais/" . $treinamentoId . "/" . $nomeSalvo, $ext, $tamanho, $ordem]
					);
				}
			}
		}

		// Bloqueios individuais: usuários dos perfis selecionados que foram desmarcados
		query("DELETE FROM treinamento_bloqueio WHERE trebl_nb_treinamento_id = ?", "i", [$treinamentoId]);
		if (!empty($perfisPermitidos)) {
			// Todos os usuários dos perfis selecionados
			$placeholders = implode(",", array_fill(0, count($perfisPermitidos), "?"));
			$rsPerfisUsers = query(
				"SELECT DISTINCT u.user_nb_id FROM user u
				 JOIN usuario_perfil up ON up.user_nb_id = u.user_nb_id
				 WHERE up.ativo = 1 AND u.user_tx_status = 'ativo'
				 AND up.perfil_nb_id IN ({$placeholders})",
				str_repeat("i", count($perfisPermitidos)),
				$perfisPermitidos
			);
			$usuariosMarcados = $_POST["usuarios_atribuidos"] ?? [];
			$usuariosMarcados = array_map('intval', $usuariosMarcados);
			while ($rsPerfisUsers && ($row = mysqli_fetch_assoc($rsPerfisUsers))) {
				$userId = (int)$row["user_nb_id"];
				if (!in_array($userId, $usuariosMarcados)) {
					query(
						"INSERT IGNORE INTO treinamento_bloqueio (trebl_nb_treinamento_id, trebl_nb_usuario_id, trebl_dt_data_cadastro) VALUES (?, ?, ?)",
						"iis",
						[$treinamentoId, $userId, date("Y-m-d H:i:s")]
					);
				}
			}
		}

		// Limpar para voltar à listagem mantendo a mensagem de status
		unset($_POST["id"], $_POST["_novo"], $_POST["salvar"]);
		index();
		exit;
	}

	function excluirTreinamento($id = null) {
		global $uploadDir;
		if ($id === null) {
			$id = $_POST["id"] ?? $_GET["id"] ?? 0;
		}
		$id = (int)$id;
		if ($id <= 0) {
			header("Location: cadastro_treinamento.php");
			exit;
		}
		$treinamento = carregar("treinamento", $id);
		if (!empty($treinamento["trei_tx_thumbnail"])) {
			$caminho = $uploadDir . $treinamento["trei_tx_thumbnail"];
			if (file_exists($caminho)) unlink($caminho);
		}
		atualizar("treinamento", ["trei_tx_status"], ["inativo"], $id);
		registrarLogTreinamento($id, $_SESSION["user_nb_id"], "exclusao", "Treinamento desativado");
		set_status("Treinamento removido com sucesso!");
		header("Location: cadastro_treinamento.php");
		exit;
	}

	function cadastrarQuestao() {
		$treinamentoId = $_POST["treinamento_id"];
		$pergunta = $_POST["qtd_pergunta"];
		$opcoes = [
			$_POST["qtd_opcao_1"] ?? "",
			$_POST["qtd_opcao_2"] ?? "",
			$_POST["qtd_opcao_3"] ?? "",
			$_POST["qtd_opcao_4"] ?? ""
		];
		$respostaCorreta = (int)($_POST["qtd_resposta_correta"] ?? 0);

		if (empty($pergunta)) {
			set_status("ERRO: Preencha a pergunta!");
			editarForm();
			exit;
		}

		$cnt = mysqli_fetch_assoc(query(
			"SELECT COUNT(*) as total FROM treinamento_questao WHERE treq_nb_treinamento_id = ?",
			"i", [$treinamentoId]
		));
		$ordem = ($cnt["total"] ?? 0) + 1;

		inserir("treinamento_questao",
			["treq_nb_treinamento_id", "treq_tx_pergunta", "treq_tx_opcoes", "treq_nb_resposta_correta", "treq_nb_ordem"],
			[$treinamentoId, $pergunta, json_encode($opcoes), $respostaCorreta, $ordem]
		);

		registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "questao_criada", "Questão #$ordem adicionada");
		set_status("Questão cadastrada com sucesso!");
		editarForm();
		exit;
	}

	function excluirQuestao($questaoId = null, $treinamentoId = null) {
		if ($questaoId === null) $questaoId = $_POST["questao_id"] ?? $_GET["questao_id"] ?? 0;
		if ($treinamentoId === null) $treinamentoId = $_POST["treinamento_id"] ?? $_GET["treinamento_id"] ?? 0;
		$questaoId = (int)$questaoId;
		$treinamentoId = (int)$treinamentoId;
		if ($questaoId > 0) {
			remover("treinamento_questao", $questaoId);
			registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "questao_excluida", "Questão #$questaoId removida");
			set_status("Questão removida com sucesso!");
		}
		$_POST["treinamento_id"] = $treinamentoId;
		editarForm();
		exit;
	}

	function uploadMaterial() {
		$treinamentoId = $_POST["treinamento_id"];
		$materialDir = __DIR__ . "/uploads/materiais/" . $treinamentoId . "/";
		if (!is_dir($materialDir)) {
			mkdir($materialDir, 0755, true);
		}

		if (!empty($_FILES["material_arquivo"]["name"])) {
			$nomeOriginal = $_FILES["material_arquivo"]["name"];
			$ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
			$tamanho = $_FILES["material_arquivo"]["size"];
			$nomeSalvo = "mat_" . time() . "_" . rand(1000, 9999) . "." . $ext;

			if (move_uploaded_file($_FILES["material_arquivo"]["tmp_name"], $materialDir . $nomeSalvo)) {
				$cnt = mysqli_fetch_assoc(query(
					"SELECT COUNT(*) as total FROM treinamento_material WHERE tram_nb_treinamento_id = ?",
					"i", [$treinamentoId]
				));
				$ordem = ($cnt["total"] ?? 0) + 1;

				inserir("treinamento_material",
					["tram_nb_treinamento_id", "tram_tx_nome", "tram_tx_descricao", "tram_tx_arquivo", "tram_tx_tipo_arquivo", "tram_nb_tamanho", "tram_nb_ordem"],
					[$treinamentoId, $_POST["material_nome"] ?? $nomeOriginal, $_POST["material_descricao"] ?? "", "materiais/" . $treinamentoId . "/" . $nomeSalvo, $ext, $tamanho, $ordem]
				);
				registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "material_upload", "Material '$nomeOriginal' adicionado");
				set_status("Material enviado com sucesso!");
			} else {
				set_status("ERRO: Falha ao enviar arquivo!");
			}
		} else {
			set_status("ERRO: Selecione um arquivo!");
		}
		editarForm();
		exit;
	}

	function excluirMaterial($materialId = null, $treinamentoId = null) {
		if ($materialId === null) $materialId = $_POST["material_id"] ?? $_GET["material_id"] ?? 0;
		if ($treinamentoId === null) $treinamentoId = $_POST["treinamento_id"] ?? $_GET["treinamento_id"] ?? 0;
		$materialId = (int)$materialId;
		$treinamentoId = (int)$treinamentoId;
		$material = carregar("treinamento_material", $materialId);
		if (!empty($material["tram_tx_arquivo"])) {
			$caminho = __DIR__ . "/uploads/" . $material["tram_tx_arquivo"];
			if (file_exists($caminho)) unlink($caminho);
		}
		remover("treinamento_material", $materialId);
		registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "material_excluido", "Material #$materialId removido");
		set_status("Material removido com sucesso!");
		$_POST["treinamento_id"] = $treinamentoId;
		editarForm();
		exit;
	}

	function limparFiltrosTreinamento() {
		$_POST = [];
		index();
		exit;
	}

	function novoTreinamento() {
		$_POST["_novo"] = 1;
		index();
		exit;
	}

	function editarForm() {
		$id = $_POST["id"] ?? $_POST["treinamento_id"] ?? 0;
		if (!empty($id)) {
			$dados = carregar("treinamento", $id);
			if (!empty($dados)) {
				$_POST["id"] = $id;
				formTreinamento($dados);
				return;
			}
		}
		set_status("ERRO: Treinamento não encontrado!");
		index();
	}

	// =====================================================
	// FORMULÁRIO
	// =====================================================

	function formTreinamento($dados = null) {
		$isEdicao = !empty($dados);
		$titulo = $dados["trei_tx_titulo"] ?? "";
		$descricao = $dados["trei_tx_descricao"] ?? "";
		$conteudoProgramatico = $dados["trei_tx_conteudo_programatico"] ?? "";
		$tipo = $dados["trei_tx_tipo"] ?? "treinamento";
		$tipoTreinamento = $dados["trei_tx_tipo_treinamento"] ?? "eventual";
		$urlVideo = $dados["trei_tx_url_video"] ?? "";
		$tipoVideo = $dados["trei_tx_tipo_video"] ?? "youtube";
		$cargaHoraria = (int)($dados["trei_nb_carga_horaria"] ?? 0);
		$cargaHoraria = sprintf("%02d:%02d", floor($cargaHoraria / 60), $cargaHoraria % 60);
		$diasValidade = $dados["trei_nb_dias_validade"] ?? 365;
		$thumbnail = $dados["trei_tx_thumbnail"] ?? "";
		$dataPublicacao = !empty($dados["trei_dt_data_publicacao"]) ? date("d/m/Y", strtotime($dados["trei_dt_data_publicacao"])) : date("d/m/Y");
		$dataLiberacao = !empty($dados["trei_dt_data_liberacao"]) ? date("d/m/Y", strtotime($dados["trei_dt_data_liberacao"])) : date("d/m/Y");
		$obrigatorio = $dados["trei_nb_obrigatorio"] ?? 0;
		$status = $dados["trei_tx_status"] ?? "ativo";
		$notaMinima = $dados["trei_nb_nota_minima_aprovacao"] ?? 70;
		$qtdQuestoes = $dados["trei_nb_quantidade_questoes_prova"] ?? 5;
		$perfisPermitidos = !empty($dados["trei_tx_tipo_usuario_permitido"]) ? json_decode($dados["trei_tx_tipo_usuario_permitido"], true) : [];

		// Buscar perfis de acesso cadastrados
		$perfis = [];
		$rsPerfis = query("SELECT perfil_nb_id, perfil_tx_nome FROM perfil_acesso WHERE perfil_tx_status = 'ativo' ORDER BY perfil_tx_nome");
		while ($row = mysqli_fetch_assoc($rsPerfis)) {
			$perfis[] = $row;
		}

		// Usuários dos perfis selecionados (para a aba de atribuições)
		$perfisComUsuarios = [];
		if (!empty($perfisPermitidos)) {
			$placeholders = implode(",", array_fill(0, count($perfisPermitidos), "?"));
			$rsUsuarios = query(
				"SELECT DISTINCT u.user_nb_id, u.user_tx_nome, u.user_tx_nivel, up.perfil_nb_id, p.perfil_tx_nome
				 FROM user u
				 JOIN usuario_perfil up ON up.user_nb_id = u.user_nb_id
				 JOIN perfil_acesso p ON p.perfil_nb_id = up.perfil_nb_id
				 WHERE up.ativo = 1 AND u.user_tx_status = 'ativo'
				 AND up.perfil_nb_id IN ({$placeholders})
				 ORDER BY p.perfil_tx_nome, u.user_tx_nome",
				str_repeat("i", count($perfisPermitidos)),
				$perfisPermitidos
			);
			while ($row = mysqli_fetch_assoc($rsUsuarios)) {
				$pid = $row["perfil_nb_id"];
				if (!isset($perfisComUsuarios[$pid])) {
					$perfisComUsuarios[$pid] = [
						"perfil_nb_id" => $pid,
						"perfil_tx_nome" => $row["perfil_tx_nome"],
						"usuarios" => []
					];
				}
				$perfisComUsuarios[$pid]["usuarios"][] = $row;
			}
		}

		// Usuários bloqueados individualmente (desmarcados na atribuição)
		$bloqueados = [];
		if ($isEdicao) {
			$rsBloqueados = query(
				"SELECT trebl_nb_usuario_id FROM treinamento_bloqueio WHERE trebl_nb_treinamento_id = ?",
				"i", [$dados["trei_nb_id"]]
			);
			while ($row = mysqli_fetch_assoc($rsBloqueados)) {
				$bloqueados[] = $row["trebl_nb_usuario_id"];
			}
		}

		echo "
		<style>
			.nav-tabs-custom > .nav-tabs > li.active > a { border-top-color: #3c8dbc; }
			.tab-content { padding: 15px; }
			.video-preview { max-width: 400px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
			.questao-item { background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 10px; margin-bottom: 10px; }
			.questao-item .opcoes { margin-left: 20px; }
			.material-item { display: flex; align-items: center; justify-content: space-between; padding: 8px; background: #f5f5f5; border-radius: 4px; margin-bottom: 5px; }
		</style>

		<div class='box box-primary'>
			<div class='box-header with-border'>
				<h3 class='box-title'>" . ($isEdicao ? "Editar Treinamento" : "Novo Treinamento") . "</h3>
			</div>
			<form method='POST' enctype='multipart/form-data' id='formTreinamento' action='cadastro_treinamento.php'>
				<div class='box-body'>
					<ul class='nav nav-tabs'>
						<li class='active'><a href='#tab_dados' data-toggle='tab'>Dados Gerais</a></li>" .
						($isEdicao ? "<li><a href='#tab_atribuicao' data-toggle='tab'>Atribuições</a></li>" : "") .
					"</ul>
					<div class='tab-content'>
						<div class='tab-pane active' id='tab_dados'>
							<div class='row'>
								<div class='col-md-8'>
									" . campo("Título *", "titulo", $titulo, "col-md-12") . "
								</div>
								<div class='col-md-4'>
									" . combo("Status *", "status", $status, "col-md-12", ["ativo" => "Ativo", "inativo" => "Inativo"]) . "
								</div>
							</div>
							<div class='row'>
								<div class='col-md-12'>
									" . textarea("Descrição", "descricao", $descricao, "col-md-12") . "
								</div>
							</div>
							<div class='row'>
								<div class='col-md-4'>
									" . combo("Tipo *", "tipo", $tipo, "col-md-12", ["dss" => "DSS", "treinamento" => "Treinamento"]) . "
								</div>
								<div class='col-md-4' id='div_tipo_treinamento'>
									" . combo("Tipo Treinamento", "tipo_treinamento", $tipoTreinamento, "col-md-12", ["inicial" => "Inicial", "periodico" => "Periódico", "eventual" => "Eventual"]) . "
								</div>
								<div class='col-md-4'>
									" . campo("Duração (mm:ss)", "carga_horaria", $cargaHoraria, "col-md-12", "00:00") . "
								</div>
							</div>
							<div class='row'>
								<div class='col-md-4'>
									" . campo("Dias de Validade", "dias_validade", $diasValidade, "col-md-12", "999") . "
								</div>
								<div class='col-md-4'>
									" . campo_data("Data Publicação", "data_publicacao", $dataPublicacao, "col-md-12") . "
								</div>
								<div class='col-md-4'>
									" . campo_data("Data Liberação", "data_liberacao", $dataLiberacao, "col-md-12") . "
								</div>
							</div>
							<div class='row'>
								<div class='col-md-4'>
									" . combo("Tipo Vídeo", "tipo_video", $tipoVideo, "col-md-12", ["youtube" => "YouTube", "vimeo" => "Vimeo", "upload" => "Upload Local"]) . "
								</div>
								<div class='col-md-8'>
									" . campo("URL do Vídeo", "url_video", $urlVideo, "col-md-12") . "
								</div>
							</div>
							<div class='row' id='div_preview_video' style='display:" . (!empty($urlVideo) ? 'block' : 'none') . ";'>
								<div class='col-md-12'>
									<label>Pré-visualização do Vídeo:</label><br>
									<div id='video_preview_container'></div>
								</div>
							</div>
							<div class='row'>
								<div class='col-md-4' style='margin-top:25px;'>
									<label>
										<input type='checkbox' name='obrigatorio' value='1' " . ($obrigatorio ? "checked" : "") . "> Obrigatório
									</label>
								</div>
							</div>
							<div class='row'>
								<div class='col-md-12'>
									<label>Perfis de Acesso Permitidos:</label><br>
									<select name='perfis_permitidos[]' id='selectPerfis' multiple class='form-control' style='height:120px;'>";
									foreach ($perfis as $p) {
										$selected = in_array($p["perfil_nb_id"], $perfisPermitidos) ? " selected" : "";
										echo "<option value='{$p["perfil_nb_id"]}'{$selected}>{$p["perfil_tx_nome"]}</option>";
									}
									echo "
									</select>
									<small class='text-muted'>Selecione os perfis de acesso que poderão visualizar este treinamento. Deixe vazio para permitir todos.</small>
								</div>
							</div>
							<div class='row'>
								<div class='col-md-12'>
									<label>Material de Apoio (PDF ou Imagem):</label>
									<input type='file' name='material_arquivo' accept='.pdf,.jpg,.jpeg,.png,.gif,.webp' class='form-control'>
									<small class='text-muted'>Envie um arquivo de apoio (PDF ou imagem) que será exibido junto ao treinamento.</small>";
									if ($isEdicao) {
										$materiais = [];
										$rsMateriais = query(
											"SELECT * FROM treinamento_material WHERE tram_nb_treinamento_id = ? AND tram_tx_status = 'ativo' ORDER BY tram_nb_ordem",
											"i", [$dados["trei_nb_id"]]
										);
										while ($row = mysqli_fetch_assoc($rsMateriais)) {
											$materiais[] = $row;
										}
										if (!empty($materiais)) {
											echo "<br><strong>Materiais já enviados:</strong><br>";
											foreach ($materiais as $m) {
												$tamanhoKB = round(($m["tram_nb_tamanho"] ?? 0) / 1024, 1);
												echo "<div class='material-item'>";
												echo "<span><i class='fa fa-file'></i> " . htmlspecialchars($m["tram_tx_nome"]) . " ({$tamanhoKB} KB)</span>";
												echo "<a href='cadastro_treinamento.php?acao_excluir_material={$m["tram_nb_id"]}&treinamento_id={$dados["trei_nb_id"]}' class='btn btn-danger btn-xs' onclick=\"return confirm('Excluir este material?');\"><i class='fa fa-trash'></i></a>";
												echo "</div>";
											}
										}
									}
									echo "
								</div>
							</div>
						</div>";

						if ($isEdicao) {
							echo "
						<div class='tab-pane' id='tab_atribuicao'>
							<div class='row'>
								<div class='col-md-12'>
									<p class='text-muted'>Os funcionários dos perfis selecionados já vêm marcados (acesso liberado). Desmarque para bloquear o acesso individual de um funcionário específico.</p>
									<div id='listaUsuariosAtribuicao'>";
									if (empty($perfisComUsuarios)) {
										echo "<div class='alert alert-warning'><i class='fa fa-info-circle'></i> Selecione pelo menos um perfil na aba <strong>Dados Gerais</strong> para listar os funcionários aqui.</div>";
									} else {
										foreach ($perfisComUsuarios as $grupo) {
											echo "<h5 style='margin-top:15px;border-bottom:1px solid #eee;padding-bottom:5px;'><i class='fa fa-users'></i> <strong>" . htmlspecialchars($grupo["perfil_tx_nome"]) . "</strong></h5>";
											echo "<div class='row'>";
											foreach ($grupo["usuarios"] as $u) {
												$checked = in_array($u["user_nb_id"], $bloqueados) ? "" : " checked";
												echo "<div class='col-md-4 col-sm-6'>";
												echo "<label style='font-weight:normal;cursor:pointer;'>";
												echo "<input type='checkbox' name='usuarios_atribuidos[]' value='{$u["user_nb_id"]}'{$checked}> ";
												echo htmlspecialchars($u["user_tx_nome"]);
												echo "</label>";
												echo "</div>";
											}
											echo "</div>";
										}
									}
									echo "
									</div>
								</div>
							</div>
						</div>";
						}

					echo "
					</div>
				</div>
				<div class='box-footer'>
					" . ($isEdicao ? "<input type='hidden' name='id' value='{$dados["trei_nb_id"]}'>
					<input type='hidden' name='treinamento_id' value='{$dados["trei_nb_id"]}'>" : "") . "
					<button type='button' name='salvar' value='1' class='btn btn-primary' onclick=\"return submitForm('salvar');\"><i class='fa fa-save'></i> Salvar</button>
					<a href='cadastro_treinamento.php' class='btn btn-default'><i class='fa fa-arrow-left'></i> Voltar</a>
				</div>
			</form>
		</div>

		<script>
			function submitForm(acao){
				var f = document.getElementById('formTreinamento');
				if(!f) return false;
				var h = document.createElement('input');
				h.type = 'hidden';
				h.name = acao;
				h.value = '1';
				f.appendChild(h);
				f.submit();
				return false;
			}
			$('input[name=carga_horaria]').on('input', function() {
				var v = $(this).val().replace(/[^\d]/g, '').slice(0, 4);
				if(v.length > 2) v = v.slice(0, 2) + ':' + v.slice(2);
				$(this).val(v);
			});

			// Carregar funcionários dos perfis selecionados (aba Atribuições)
			function carregarUsuariosAtribuicao() {
				var container = $('#listaUsuariosAtribuicao');
				if(!container.length) return;
				var perfis = $('#selectPerfis').val() || [];
				var treinamentoId = $('input[name=treinamento_id]').val() || $('input[name=id]').val() || '';
				$.get(window.location.pathname, {
					listar_usuarios_perfis: 1,
					perfis: JSON.stringify(perfis),
					treinamento_id: treinamentoId
				}, function(data) {
					if(!data.perfis || data.perfis.length === 0) {
						container.html('<div class=\"alert alert-warning\"><i class=\"fa fa-info-circle\"></i> Selecione pelo menos um perfil na aba <strong>Dados Gerais</strong> para listar os funcionários aqui.</div>');
						return;
					}
					var html = '';
					data.perfis.forEach(function(p) {
						html += '<h5 style=\"margin-top:15px;border-bottom:1px solid #eee;padding-bottom:5px;\"><i class=\"fa fa-users\"></i> <strong>' + $('<span>').text(p.perfil_tx_nome).html() + '</strong></h5>';
						html += '<div class=\"row\">';
						p.usuarios.forEach(function(u) {
							var checked = data.bloqueados.indexOf(parseInt(u.user_nb_id)) === -1 ? ' checked' : '';
							html += '<div class=\"col-md-4 col-sm-6\">' +
								'<label style=\"font-weight:normal;cursor:pointer;\">' +
								'<input type=\"checkbox\" name=\"usuarios_atribuidos[]\" value=\"' + u.user_nb_id + '\"' + checked + '> ' +
								$('<span>').text(u.user_tx_nome).html() +
								'</label></div>';
						});
						html += '</div>';
					});
					container.html(html);
				}, 'json');
			}

			$('#selectPerfis').on('change', carregarUsuariosAtribuicao);
			if($('#listaUsuariosAtribuicao').length) {
				carregarUsuariosAtribuicao();
			}
			$('select[name=tipo]').change(function(){
				$('#div_tipo_treinamento').toggle($(this).val() === 'treinamento');
			}).trigger('change');

			$('input[name=url_video], select[name=tipo_video]').change(function(){
				var url = $('input[name=url_video]').val();
				var tipo = $('select[name=tipo_video]').val();
				if(url){
					$('#div_preview_video').show();
					if(tipo === 'youtube'){
						var match = url.match(/(?:youtube\\.com\\/watch\\?v=|youtu\\.be\\/)([^&\\n?#]+)/);
						if(match) $('#video_preview_container').html('<iframe width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/' + match[1] + '\" frameborder=\"0\" allowfullscreen></iframe>');
					} else if(tipo === 'vimeo'){
						var match = url.match(/vimeo\\.com\\/(\\d+)/);
						if(match) $('#video_preview_container').html('<iframe width=\"560\" height=\"315\" src=\"https://player.vimeo.com/video/' + match[1] + '\" frameborder=\"0\" allowfullscreen></iframe>');
					}
				} else {
					$('#div_preview_video').hide();
				}
			}).trigger('change');

			if($('#selectPerfis').length){
				$('#selectPerfis').select2({ placeholder: 'Selecione os perfis...', allowClear: true, language: 'pt-BR' });
			}
		</script>";
	}

	// =====================================================
	// GRID DE LISTAGEM
	// =====================================================

	function listarTreinamentos() {
		global $conn;
		$gridFields = [
			"ID" => "trei_nb_id",
			"TÍTULO" => "trei_tx_titulo",
			"TIPO" => "trei_tx_tipo",
			"STATUS" => "trei_tx_status",
			"CARGA HORÁRIA" => "trei_nb_carga_horaria",
			"OBRIGATÓRIO" => "trei_nb_obrigatorio",
		];

		$camposBusca = [
			"busca_titulo_like" => "trei_tx_titulo",
			"busca_tipo" => "trei_tx_tipo",
			"busca_status" => "trei_tx_status",
		];

		$queryBase = "SELECT trei_nb_id, trei_tx_titulo, trei_tx_tipo, trei_tx_status, trei_nb_carga_horaria, trei_nb_obrigatorio FROM treinamento";

		$jsFunctions = "orderCol = 'trei_nb_id DESC';
			setTimeout(function(){ consultarRegistros(); }, 200);
			$(document).on('click', '.btn-editar-treinamento', function(event){
				event.preventDefault();
				var row = $(this).closest('tr');
				var id = row.attr('data-row-id');
				if(!id){
					id = row.find('td').first().text().trim();
				}
				if(id){
					window.location.href = 'cadastro_treinamento.php?id=' + id;
				}
			});
			$(document).on('click', '.btn-excluir-treinamento', function(event){
				event.preventDefault();
				var row = $(this).closest('tr');
				var id = row.attr('data-row-id');
				if(!id){
					id = row.find('td').first().text().trim();
				}
				if(!id) return;
				Swal.fire({
					title: 'Tem certeza?',
					text: 'Este treinamento será desativado!',
					icon: 'warning',
					showCancelButton: true,
					confirmButtonColor: '#d33',
					cancelButtonColor: '#3085d6',
					confirmButtonText: 'Sim, excluir!',
					cancelButtonText: 'Cancelar'
				}).then((result) => {
					if (result.isConfirmed) {
						window.location.href = 'cadastro_treinamento.php?acao_excluir=' + id;
					}
				});
			});";

		$gridFields["actions"] = [
			"<spam class='btn-editar-treinamento' style='cursor:pointer;color:#337ab7;margin-right:5px;' title='Alterar'><i class='fa fa-pencil'></i></spam>",
			"<spam class='btn-excluir-treinamento' style='cursor:pointer;color:#d9534f;' title='Excluir'><i class='fa fa-trash'></i></spam>"
		];

		echo gridDinamico("treinamento", $gridFields, $camposBusca, $queryBase, $jsFunctions);
	}

	// =====================================================
	// PONTO DE ENTRADA
	// =====================================================

	function index() {
		include_once __DIR__."/../check_permission.php";
		verificaPermissao('/treinamento/cadastro_treinamento.php');

		// AJAX: listar usuários dos perfis selecionados (aba Atribuições)
		// (colocado aqui pois o dispatcher do funcoes.php chama index() durante o include do conecta)
		if (isset($_GET["listar_usuarios_perfis"])) {
			header('Content-Type: application/json');

			$perfisParam = $_GET["perfis"] ?? "";
			$perfis = json_decode($perfisParam, true);
			if (!is_array($perfis)) {
				$perfis = array_map('intval', explode(",", (string)$perfisParam));
			}
			$perfis = array_values(array_filter(array_map('intval', $perfis)));
			$treinamentoId = (int)($_GET["treinamento_id"] ?? 0);

			$usuarios = [];
			$perfisComUsuarios = [];
			if (!empty($perfis)) {
				$placeholders = implode(",", array_fill(0, count($perfis), "?"));
				$rs = query(
					"SELECT DISTINCT u.user_nb_id, u.user_tx_nome, up.perfil_nb_id, p.perfil_tx_nome
					 FROM user u
					 JOIN usuario_perfil up ON up.user_nb_id = u.user_nb_id
					 JOIN perfil_acesso p ON p.perfil_nb_id = up.perfil_nb_id
					 WHERE up.ativo = 1 AND u.user_tx_status = 'ativo'
					 AND up.perfil_nb_id IN ({$placeholders})
					 ORDER BY p.perfil_tx_nome, u.user_tx_nome",
					str_repeat("i", count($perfis)),
					$perfis
				);
				while ($rs && ($row = mysqli_fetch_assoc($rs))) {
					$usuarios[] = $row;
					$pid = $row["perfil_nb_id"];
					if (!isset($perfisComUsuarios[$pid])) {
						$perfisComUsuarios[$pid] = [
							"perfil_nb_id" => $pid,
							"perfil_tx_nome" => $row["perfil_tx_nome"],
							"usuarios" => []
						];
					}
					$perfisComUsuarios[$pid]["usuarios"][] = $row;
				}
			}

			$bloqueados = [];
			if ($treinamentoId > 0) {
				$rsB = query("SELECT trebl_nb_usuario_id FROM treinamento_bloqueio WHERE trebl_nb_treinamento_id = ?", "i", [$treinamentoId]);
				while ($rsB && ($rowB = mysqli_fetch_assoc($rsB))) {
					$bloqueados[] = (int)$rowB["trebl_nb_usuario_id"];
				}
			}

			echo json_encode(["perfis" => array_values($perfisComUsuarios), "usuarios" => $usuarios, "bloqueados" => $bloqueados]);
			exit;
		}

		// Salvamento do formulário (contorna o dispatcher do funcoes.php)
		if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["salvar"])) {
			cadastrar();
			return;
		}

		// Exclusões via GET (contorna o dispatcher do funcoes.php)
		if (isset($_GET["acao_excluir"]) && is_numeric($_GET["acao_excluir"])) {
			excluirTreinamento((int)$_GET["acao_excluir"]);
			return;
		}
		if (isset($_GET["acao_excluir_material"]) && is_numeric($_GET["acao_excluir_material"])) {
			excluirMaterial((int)$_GET["acao_excluir_material"], (int)($_GET["treinamento_id"] ?? 0));
			return;
		}

		cabecalho("Cadastro de Treinamentos");

		$treinamentoId = $_POST["id"] ?? $_GET["id"] ?? 0;

		if (!empty($treinamentoId) || !empty($_POST["_novo"])) {
			if (!empty($treinamentoId)) {
				$dados = carregar("treinamento", $treinamentoId);
				formTreinamento($dados);
			} else {
				formTreinamento();
			}
		} else {
			$campos = [
				campo("Título", "busca_titulo_like", $_POST["busca_titulo_like"] ?? "", "col-md-4"),
				combo("Tipo", "busca_tipo", $_POST["busca_tipo"] ?? "", "col-md-3", ["dss" => "DSS", "treinamento" => "Treinamento"]),
				combo("Status", "busca_status", $_POST["busca_status"] ?? "", "col-md-3", ["ativo" => "Ativo", "inativo" => "Inativo"]),
			];
			$botoes = [
				botao("Buscar", "index"),
				botao("Limpar Filtro", "limparFiltrosTreinamento"),
				botao("Novo Treinamento", "novoTreinamento", "", "", "", "", "btn btn-success")
			];

			echo "<form method='POST' id='formBusca'>";
			echo abre_form();
			echo linha_form($campos);
			echo fecha_form($botoes);
			echo "</form>";

			listarTreinamentos();
		}

		rodape();
	}

	index();
