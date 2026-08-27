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
			"trei_nb_carga_horaria" => (int)($_POST["carga_horaria"] ?? 0),
			"trei_nb_dias_validade" => (int)($_POST["dias_validade"] ?? 365),
			"trei_tx_status" => $_POST["status"] ?? "ativo",
			"trei_nb_obrigatorio" => isset($_POST["obrigatorio"]) ? 1 : 0,
			"trei_nb_nota_minima_aprovacao" => (int)($_POST["nota_minima_aprovacao"] ?? 70),
			"trei_nb_quantidade_questoes_prova" => (int)($_POST["quantidade_questoes_prova"] ?? 5),
			"trei_dt_data_atualiza" => date("Y-m-d H:i:s")
		];

		$perfisPermitidos = $_POST["perfis_permitidos"] ?? [];
		$novo["trei_tx_tipo_usuario_permitido"] = !empty($perfisPermitidos) ? json_encode($perfisPermitidos) : null;

		if (!empty($_POST["data_publicacao"])) {
			$dt = DateTime::createFromFormat('d/m/Y', $_POST["data_publicacao"]);
			$novo["trei_dt_data_publicacao"] = $dt ? $dt->format('Y-m-d') : null;
		}
		if (!empty($_POST["data_liberacao"])) {
			$dt = DateTime::createFromFormat('d/m/Y', $_POST["data_liberacao"]);
			$novo["trei_dt_data_liberacao"] = $dt ? $dt->format('Y-m-d') : null;
		}

		if (!empty($_FILES["thumbnail"]["name"])) {
			$ext = strtolower(pathinfo($_FILES["thumbnail"]["name"], PATHINFO_EXTENSION));
			$permitidos = ["jpg", "jpeg", "png", "gif", "webp"];
			if (in_array($ext, $permitidos)) {
				$nomeArquivo = "thumb_" . time() . "." . $ext;
				move_uploaded_file($_FILES["thumbnail"]["tmp_name"], $uploadDir . $nomeArquivo);
				$novo["trei_tx_thumbnail"] = $nomeArquivo;
			}
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

		if (($novo["trei_tx_tipo"] ?? $_POST["tipo"] ?? "") === "treinamento") {
			query("DELETE FROM treinamento_atribuicao WHERE treate_nb_treinamento_id = ?", "i", [$treinamentoId]);
			$usuariosAtribuidos = $_POST["usuarios_atribuidos"] ?? [];
			if (!empty($usuariosAtribuidos)) {
				foreach ($usuariosAtribuidos as $userId) {
					query(
						"INSERT IGNORE INTO treinamento_atribuicao (treate_nb_treinamento_id, treate_nb_usuario_id, treate_dt_data_cadastro) VALUES (?, ?, ?)",
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
		$cargaHoraria = $dados["trei_nb_carga_horaria"] ?? 0;
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

		$usuarios = [];
		$rsUsuarios = query("SELECT user_nb_id, user_tx_nome FROM user WHERE user_tx_status = 'ativo' ORDER BY user_tx_nome");
		while ($row = mysqli_fetch_assoc($rsUsuarios)) {
			$usuarios[] = $row;
		}

		$atribuidos = [];
		if ($isEdicao) {
			$rsAtribuidos = query(
				"SELECT treate_nb_usuario_id FROM treinamento_atribuicao WHERE treate_nb_treinamento_id = ?",
				"i", [$dados["trei_nb_id"]]
			);
			while ($row = mysqli_fetch_assoc($rsAtribuidos)) {
				$atribuidos[] = $row["treate_nb_usuario_id"];
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
						($isEdicao ? "<li><a href='#tab_questoes' data-toggle='tab'>Banco de Questões</a></li>" : "") .
						($isEdicao ? "<li><a href='#tab_materiais' data-toggle='tab'>Materiais de Apoio</a></li>" : "") .
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
									" . campo("Carga Horária (min)", "carga_horaria", $cargaHoraria, "col-md-12", "999") . "
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
								<div class='col-md-4'>
									" . campo("Qtd. Questões Prova", "quantidade_questoes_prova", $qtdQuestoes, "col-md-12", "9") . "
								</div>
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
								<div class='col-md-4'>
									<label>Thumbnail:</label><br>
									<input type='file' name='thumbnail' accept='image/*' class='form-control'>
									" . (!empty($thumbnail) ? "<br><img src='uploads/{$thumbnail}' class='video-preview'>" : "") . "
								</div>
							</div>
						</div>";

						if ($isEdicao) {
							echo "
						<div class='tab-pane' id='tab_atribuicao'>
							<div class='row'>
								<div class='col-md-12'>
									<p class='text-muted'>Selecione os usuários que terão acesso a este treinamento.</p>
									<select name='usuarios_atribuidos[]' id='selectUsuarios' multiple class='form-control' style='height:200px;'>";
									foreach ($usuarios as $u) {
										$selected = in_array($u["user_nb_id"], $atribuidos) ? " selected" : "";
										echo "<option value='{$u["user_nb_id"]}'{$selected}>{$u["user_tx_nome"]}</option>";
									}
									echo "
									</select>
								</div>
							</div>
						</div>";
						}

						if ($isEdicao) {
							$questoes = [];
							$rsQuestoes = query(
								"SELECT * FROM treinamento_questao WHERE treq_nb_treinamento_id = ? ORDER BY treq_nb_ordem",
								"i", [$dados["trei_nb_id"]]
							);
							while ($row = mysqli_fetch_assoc($rsQuestoes)) {
								$questoes[] = $row;
							}

							echo "
						<div class='tab-pane' id='tab_questoes'>
							<div class='row'>
								<div class='col-md-12'>
									<h4>Cadastrar Nova Questão</h4>
									<div class='box box-info box-solid'>
										<div class='box-body'>
											<div class='row'>
												<div class='col-md-12'>
													" . textarea("Pergunta", "qtd_pergunta", "", "col-md-12") . "
												</div>
											</div>
											<div class='row'>
												<div class='col-md-6'>" . campo("Opção 1", "qtd_opcao_1", "", "col-md-12") . "</div>
												<div class='col-md-6'>" . campo("Opção 2", "qtd_opcao_2", "", "col-md-12") . "</div>
											</div>
											<div class='row'>
												<div class='col-md-6'>" . campo("Opção 3", "qtd_opcao_3", "", "col-md-12") . "</div>
												<div class='col-md-6'>" . campo("Opção 4", "qtd_opcao_4", "", "col-md-12") . "</div>
											</div>
											<div class='row'>
												<div class='col-md-4'>
													" . combo("Resposta Correta", "qtd_resposta_correta", "0", "col-md-12", ["0" => "Opção 1", "1" => "Opção 2", "2" => "Opção 3", "3" => "Opção 4"]) . "
												</div>
												<div class='col-md-4' style='margin-top:25px;'>
													<button type='button' name='salvar_questao' value='1' class='btn btn-info' onclick=\"return submitForm('salvar_questao');\"><i class='fa fa-plus'></i> Adicionar Questão</button>
												</div>
											</div>
										</div>
									</div>
									<h4>Questões Cadastradas (" . count($questoes) . ")</h4>";

							if (empty($questoes)) {
								echo "<p class='text-muted'>Nenhuma questão cadastrada.</p>";
							} else {
								foreach ($questoes as $idx => $q) {
									$opcoes = json_decode($q["treq_tx_opcoes"], true);
									$correta = (int)$q["treq_nb_resposta_correta"];
									echo "
									<div class='questao-item'>
										<div style='display:flex; justify-content:space-between;'>
											<strong>Q" . ($idx + 1) . ": " . htmlspecialchars($q["treq_tx_pergunta"]) . "</strong>
											<a href='cadastro_treinamento.php?acao_excluir_questao={$q["treq_nb_id"]}&treinamento_id={$dados["trei_nb_id"]}' class='btn btn-danger btn-xs' onclick=\"return confirm('Excluir esta questão?');\"><i class='fa fa-trash'></i></a>
										</div>
										<div class='opcoes'>";
									foreach ($opcoes as $oi => $op) {
										$icon = ($oi === $correta) ? "fa-check-circle text-green" : "fa-circle-o text-muted";
										echo "<i class='fa {$icon}'></i> " . htmlspecialchars($op) . "<br>";
									}
									echo "</div></div>";
								}
							}
							echo "</div></div></div>";
						}

						if ($isEdicao) {
							$materiais = [];
							$rsMateriais = query(
								"SELECT * FROM treinamento_material WHERE tram_nb_treinamento_id = ? AND tram_tx_status = 'ativo' ORDER BY tram_nb_ordem",
								"i", [$dados["trei_nb_id"]]
							);
							while ($row = mysqli_fetch_assoc($rsMateriais)) {
								$materiais[] = $row;
							}

							echo "
						<div class='tab-pane' id='tab_materiais'>
							<div class='row'>
								<div class='col-md-12'>
									<h4>Enviar Novo Material</h4>
									<div class='box box-success box-solid'>
										<div class='box-body'>
											<div class='row'>
												<div class='col-md-4'>" . campo("Nome do Material", "material_nome", "", "col-md-12") . "</div>
												<div class='col-md-4'>" . campo("Descrição", "material_descricao", "", "col-md-12") . "</div>
												<div class='col-md-4'>
													<label>Arquivo:</label>
													<input type='file' name='material_arquivo' class='form-control' required>
												</div>
											</div>
											<div class='row'>
												<div class='col-md-4' style='margin-top:25px;'>
													<button type='button' name='salvar_material' value='1' class='btn btn-success' onclick=\"return submitForm('salvar_material');\"><i class='fa fa-upload'></i> Enviar Material</button>
												</div>
											</div>
										</div>
									</div>
									<h4>Materiais Cadastrados (" . count($materiais) . ")</h4>";

							if (empty($materiais)) {
								echo "<p class='text-muted'>Nenhum material cadastrado.</p>";
							} else {
								foreach ($materiais as $m) {
									$tamanhoKB = round(($m["tram_nb_tamanho"] ?? 0) / 1024, 1);
									echo "
									<div class='material-item'>
										<div>
											<i class='fa fa-file'></i>
											<strong>" . htmlspecialchars($m["tram_tx_nome"]) . "</strong>
											<span class='text-muted'> ({$tamanhoKB} KB)</span>
										</div>
										<div>
											<a href='cadastro_treinamento.php?acao_excluir_material={$m["tram_nb_id"]}&treinamento_id={$dados["trei_nb_id"]}' class='btn btn-danger btn-xs' onclick=\"return confirm('Excluir este material?');\"><i class='fa fa-trash'></i></a>
										</div>
									</div>";
								}
							}
							echo "</div></div></div>";
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

			if($('#selectUsuarios').length){
				$('#selectUsuarios').select2({ placeholder: 'Selecione os usuários...', allowClear: true, language: 'pt-BR' });
			}
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

		// Salvamento do formulário (contorna o dispatcher do funcoes.php)
		if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["salvar"])) {
			cadastrar();
			return;
		}
		if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["salvar_questao"])) {
			cadastrarQuestao();
			return;
		}
		if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["salvar_material"])) {
			uploadMaterial();
			return;
		}

		// Exclusões via GET (contorna o dispatcher do funcoes.php)
		if (isset($_GET["acao_excluir"]) && is_numeric($_GET["acao_excluir"])) {
			excluirTreinamento((int)$_GET["acao_excluir"]);
			return;
		}
		if (isset($_GET["acao_excluir_questao"]) && is_numeric($_GET["acao_excluir_questao"])) {
			excluirQuestao((int)$_GET["acao_excluir_questao"], (int)($_GET["treinamento_id"] ?? 0));
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
