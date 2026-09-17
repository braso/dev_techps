<?php
	// Evita cache do navegador (o formulário tem JS dinâmico)
	header("Cache-Control: no-cache, no-store, must-revalidate");
	header("Pragma: no-cache");
	header("Expires: 0");

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
			// Regra: ao ativar série, o link do vídeo geral é desconsiderado (vídeos ficam nos episódios)
			"trei_tx_serie" => isset($_POST["serie_videos"]) ? "sim" : "nao",
			"trei_tx_url_video" => isset($_POST["serie_videos"]) ? null : ($_POST["url_video"] ?? null),
			"trei_tx_tipo_video" => $_POST["tipo_video"] ?? "youtube",
			"trei_nb_carga_horaria" => (function() {
				$segundos = 0;
				if (!empty($_POST["carga_horaria"])) {
					$partes = array_map('intval', explode(":", $_POST["carga_horaria"]));
					$segundos = (int)($partes[0] ?? 0) * 3600 + (int)($partes[1] ?? 0) * 60 + (int)($partes[2] ?? 0);
				}
				return $segundos;
			})(),
			"trei_nb_dias_validade" => (int)($_POST["dias_validade"] ?? 365),
			"trei_tx_status" => $_POST["status"] ?? "ativo",
			"trei_tx_gerar_notificacao" => isset($_POST["gerar_notificacao"]) ? "sim" : "nao",
			"trei_nb_obrigatorio" => isset($_POST["obrigatorio"]) ? 1 : 0,
			"trei_nb_nota_minima_aprovacao" => (int)($_POST["nota_minima_aprovacao"] ?? 70),
			"trei_nb_max_tentativas" => max(0, (int)($_POST["max_tentativas"] ?? 2)),
			"trei_nb_quantidade_questoes_prova" => (int)($_POST["quantidade_questoes_prova"] ?? 5),
			"trei_dt_data_atualiza" => date("Y-m-d H:i:s")
		];

		$perfisPermitidos = $_POST["perfis_permitidos"] ?? [];
		$perfisPermitidos = array_map('intval', $perfisPermitidos);
		$novo["trei_tx_tipo_usuario_permitido"] = !empty($perfisPermitidos) ? json_encode($perfisPermitidos) : null;

		// Empresas habilitadas (vazio = todas)
		$empresasHab = $_POST["empresas_habilitadas"] ?? [];
		$empresasHab = array_values(array_filter(array_map('intval', $empresasHab)));
		$novo["trei_tx_empresas_habilitadas"] = !empty($empresasHab) ? json_encode($empresasHab) : null;

		// Instrutor responsável
		$novo["trei_tx_instrutor_tipo"] = $_POST["instrutor_tipo"] ?? "funcionario";
		$novo["trei_nb_instrutor_entidade_id"] = (int)($_POST["instrutor_entidade_id"] ?? 0) ?: null;
		$novo["trei_tx_instrutor_nome"] = !empty($_POST["instrutor_nome"]) ? $_POST["instrutor_nome"] : null;
		$novo["trei_tx_instrutor_cpf"] = !empty($_POST["instrutor_cpf"]) ? $_POST["instrutor_cpf"] : null;
		$novo["trei_tx_instrutor_capacitacao"] = !empty($_POST["instrutor_capacitacao"]) ? $_POST["instrutor_capacitacao"] : null;

		if (!empty($_POST["data_publicacao"])) {
			$dt = DateTime::createFromFormat('Y-m-d', $_POST["data_publicacao"]) ?: DateTime::createFromFormat('d/m/Y', $_POST["data_publicacao"]);
			$novo["trei_dt_data_publicacao"] = $dt ? $dt->format('Y-m-d') : null;
		}
		if (!empty($_POST["data_liberacao"])) {
			$dt = DateTime::createFromFormat('Y-m-d', $_POST["data_liberacao"]) ?: DateTime::createFromFormat('d/m/Y', $_POST["data_liberacao"]);
			$novo["trei_dt_data_liberacao"] = $dt ? $dt->format('Y-m-d') : null;
		}

		if (!empty($_POST["id"])) {
			atualizar("treinamento", array_keys($novo), array_values($novo), $_POST["id"]);
			$treinamentoId = $_POST["id"];
			registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "edicao", "Treinamento editado");
			set_status("Treinamento atualizado com sucesso!");
		} else {
			// Snapshot do criador (nome, cargo, setor)
			$userCriador = mysqli_fetch_assoc(query(
				"SELECT u.user_tx_nome, op.oper_tx_nome AS cargo, g.grup_tx_nome AS setor
				 FROM user u
				 LEFT JOIN entidade e ON e.enti_nb_id = u.user_nb_entidade
				 LEFT JOIN operacao op ON op.oper_nb_id = e.enti_tx_tipoOperacao
				 LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
				 WHERE u.user_nb_id = ?",
				"i", [$_SESSION["user_nb_id"]]
			));
			$novo["trei_nb_user_cadastro"] = (int)($_SESSION["user_nb_id"] ?? 0) ?: null;
			$novo["trei_tx_criador_nome"] = ($userCriador["user_tx_nome"] ?? "") ?: ($_SESSION["user_tx_nome"] ?? "");
			$novo["trei_tx_criador_cargo"] = $userCriador["cargo"] ?? "";
			$novo["trei_tx_criador_setor"] = $userCriador["setor"] ?? "";
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

		// Upload do material de apoio (aceita múltiplos arquivos)
		if (!empty($_FILES["material_arquivo"]) && !empty($_FILES["material_arquivo"]["name"][0])) {
			$materialDir = __DIR__ . "/uploads/materiais/" . $treinamentoId . "/";
			if (!is_dir($materialDir)) {
				mkdir($materialDir, 0755, true);
			}
			$cnt = mysqli_fetch_assoc(query(
				"SELECT COUNT(*) as total FROM treinamento_material WHERE tram_nb_treinamento_id = ?",
				"i", [$treinamentoId]
			));
			$ordem = (int)($cnt["total"] ?? 0) + 1;
			$nomes = is_array($_FILES["material_arquivo"]["name"]) ? $_FILES["material_arquivo"]["name"] : [$_FILES["material_arquivo"]["name"]];
			$tmpNames = is_array($_FILES["material_arquivo"]["tmp_name"]) ? $_FILES["material_arquivo"]["tmp_name"] : [$_FILES["material_arquivo"]["tmp_name"]];
			$errors = is_array($_FILES["material_arquivo"]["error"]) ? $_FILES["material_arquivo"]["error"] : [$_FILES["material_arquivo"]["error"]];
			$sizes = is_array($_FILES["material_arquivo"]["size"]) ? $_FILES["material_arquivo"]["size"] : [$_FILES["material_arquivo"]["size"]];
			$permitidos = ["pdf", "jpg", "jpeg", "png", "gif", "webp"];
			foreach ($nomes as $idx => $nomeOriginal) {
				if (empty($nomeOriginal) || ($errors[$idx] ?? 1) !== UPLOAD_ERR_OK) continue;
				$ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
				if (!in_array($ext, $permitidos)) continue;
				$tamanho = $sizes[$idx] ?? 0;
				$nomeSalvo = "mat_" . time() . "_" . rand(1000, 9999) . "." . $ext;
				if (move_uploaded_file($tmpNames[$idx], $materialDir . $nomeSalvo)) {
					inserir("treinamento_material",
						["tram_nb_treinamento_id", "tram_tx_nome", "tram_tx_descricao", "tram_tx_arquivo", "tram_tx_tipo_arquivo", "tram_nb_tamanho", "tram_nb_ordem"],
						[$treinamentoId, $nomeOriginal, "", "materiais/" . $treinamentoId . "/" . $nomeSalvo, $ext, $tamanho, $ordem++]
					);
					registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "material_upload", "Material '$nomeOriginal' adicionado");
				}
			}
		}

		// Questões da avaliação cadastradas junto com o treinamento (lote)
		if (!empty($_POST["q_avaliacao"]["pergunta"]) && is_array($_POST["q_avaliacao"]["pergunta"])) {
			$cntQ = mysqli_fetch_assoc(query(
				"SELECT COUNT(*) as total FROM treinamento_questao WHERE treq_nb_treinamento_id = ?",
				"i", [$treinamentoId]
			));
			$ordemQ = (int)($cntQ["total"] ?? 0) + 1;
			foreach ($_POST["q_avaliacao"]["pergunta"] as $i => $perguntaQ) {
				$perguntaQ = trim((string)$perguntaQ);
				if ($perguntaQ === "" || $ordemQ > 10) continue;
				$corretaQ = $_POST["q_avaliacao"]["correta"][$i] ?? null;
				if ($corretaQ === null) continue; // sem opção correta marcada
				$opcoesQ = [
					$_POST["q_avaliacao"]["opcao_1"][$i] ?? "",
					$_POST["q_avaliacao"]["opcao_2"][$i] ?? "",
					$_POST["q_avaliacao"]["opcao_3"][$i] ?? "",
					$_POST["q_avaliacao"]["opcao_4"][$i] ?? ""
				];
				$corretaQ = (int)$corretaQ;
				inserir("treinamento_questao",
					["treq_nb_treinamento_id", "treq_tx_pergunta", "treq_tx_opcoes", "treq_nb_resposta_correta", "treq_nb_ordem"],
					[$treinamentoId, $perguntaQ, json_encode($opcoesQ), $corretaQ, $ordemQ]
				);
				registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "questao_criada", "Questão #$ordemQ adicionada junto ao salvamento");
				$ordemQ++;
			}
		}

		// Episódios da série cadastrados junto (cadastro novo)
		if (isset($_POST["serie_videos"]) && !empty($_POST["novo_epi"]) && is_array($_POST["novo_epi"])) {
			$cntEp = mysqli_fetch_assoc(query(
				"SELECT COUNT(*) as total FROM treinamento_episodio WHERE trepi_nb_treinamento_id = ?",
				"i", [$treinamentoId]
			));
			$ordemEp = (int)($cntEp["total"] ?? 0) + 1;
			foreach ($_POST["novo_epi"] as $idxEp => $epDados) {
				$tituloEp = trim((string)($epDados["titulo"] ?? ""));
				if ($tituloEp === "") continue;
				$cargaEp = 0;
				if (!empty($epDados["carga_horaria"])) {
					$partesEp = array_map('intval', explode(":", (string)$epDados["carga_horaria"]));
					$cargaEp = (int)($partesEp[0] ?? 0) * 3600 + (int)($partesEp[1] ?? 0) * 60 + (int)($partesEp[2] ?? 0);
				}
				$notaEp = ((string)($epDados["nota_minima"] ?? "") !== "") ? (int)$epDados["nota_minima"] : null;
				$retInsEp = inserir("treinamento_episodio",
					["trepi_nb_treinamento_id", "trepi_tx_titulo", "trepi_tx_descricao", "trepi_tx_url_video", "trepi_tx_tipo_video", "trepi_nb_carga_horaria", "trepi_nb_nota_minima_aprovacao", "trepi_nb_ordem", "trepi_tx_status"],
					[$treinamentoId, $tituloEp, ($epDados["descricao"] ?? "") ?: null, ($epDados["url_video"] ?? "") ?: null, $epDados["tipo_video"] ?? "youtube", $cargaEp, $notaEp, $ordemEp, "ativo"]
				);
				$episodioNovoId = (int)($retInsEp[0] ?? 0);
				if ($episodioNovoId > 0) {
					registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "episodio_salvo", "Episódio #$episodioNovoId: $tituloEp (junto ao salvamento)");
					$ordemEp++;
					// Questões da avaliação deste episódio (cadastradas junto)
					if (!empty($epDados["questoes"]["pergunta"]) && is_array($epDados["questoes"]["pergunta"])) {
						$ordemQE = 1;
						foreach ($epDados["questoes"]["pergunta"] as $qi => $perguntaQE) {
							$perguntaQE = trim((string)$perguntaQE);
							if ($perguntaQE === "" || $ordemQE > 10) continue;
							$corretaQE = $epDados["questoes"]["correta"][$qi] ?? null;
							if ($corretaQE === null) continue; // sem opção correta marcada
							$opcoesQE = [
								$epDados["questoes"]["opcao_1"][$qi] ?? "",
								$epDados["questoes"]["opcao_2"][$qi] ?? "",
								$epDados["questoes"]["opcao_3"][$qi] ?? "",
								$epDados["questoes"]["opcao_4"][$qi] ?? ""
							];
							$corretaQE = (int)$corretaQE;
							inserir("treinamento_episodio_questao",
								["trepq_nb_episodio_id", "trepq_tx_pergunta", "trepq_tx_opcoes", "trepq_nb_resposta_correta", "trepq_nb_ordem"],
								[$episodioNovoId, $perguntaQE, json_encode($opcoesQE), $corretaQE, $ordemQE]
							);
							$ordemQE++;
						}
					}
				}
			}
		}

		// Bloqueios individuais: apenas usuários da origem (renderizados na aba Atribuições) que foram desmarcados
		// A lista de origem vem do formulário - se ela não estiver presente, nenhum bloqueio é criado (todos têm acesso)
		query("DELETE FROM treinamento_bloqueio WHERE trebl_nb_treinamento_id = ?", "i", [$treinamentoId]);
		$usuariosOrigem = $_POST["usuarios_origem"] ?? [];
		$usuariosMarcados = $_POST["usuarios_atribuidos"] ?? [];
		$usuariosOrigem = array_map('intval', $usuariosOrigem);
		$usuariosMarcados = array_map('intval', $usuariosMarcados);
		if (!empty($usuariosOrigem)) {
			$bloquearIds = array_diff($usuariosOrigem, $usuariosMarcados);
			foreach ($bloquearIds as $userId) {
				query(
					"INSERT IGNORE INTO treinamento_bloqueio (trebl_nb_treinamento_id, trebl_nb_usuario_id, trebl_dt_data_cadastro) VALUES (?, ?, ?)",
					"iis",
					[$treinamentoId, $userId, date("Y-m-d H:i:s")]
				);
			}
		}

		// Limpar para voltar Ã  listagem mantendo a mensagem de status
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
		$treinamentoId = $_POST["treinamento_id"] ?? $_POST["id"] ?? 0;
		$pergunta = trim($_POST["qtd_pergunta"] ?? "");
		$opcoes = [
			$_POST["qtd_opcao_1"] ?? "",
			$_POST["qtd_opcao_2"] ?? "",
			$_POST["qtd_opcao_3"] ?? "",
			$_POST["qtd_opcao_4"] ?? ""
		];
		$respostaCorreta = isset($_POST["qtd_resposta_correta"]) ? (int)$_POST["qtd_resposta_correta"] : null;

		if ($treinamentoId <= 0 || empty($pergunta)) {
			set_status("ERRO: Preencha a pergunta!");
			$_POST["aba_avaliacao"] = 1;
			editarForm();
			exit;
		}

		if ($respostaCorreta === null) {
			set_status("ERRO: Marque a opção correta da questão!");
			$_POST["aba_avaliacao"] = 1;
			editarForm();
			exit;
		}

		$cnt = mysqli_fetch_assoc(query(
			"SELECT COUNT(*) as total FROM treinamento_questao WHERE treq_nb_treinamento_id = ?",
			"i", [$treinamentoId]
		));
		$totalQuestoes = (int)($cnt["total"] ?? 0);
		if ($totalQuestoes >= 10) {
			set_status("ERRO: Máximo de 10 questões por avaliação!");
			$_POST["aba_avaliacao"] = 1;
			editarForm();
			exit;
		}

		$ordem = $totalQuestoes + 1;
		inserir("treinamento_questao",
			["treq_nb_treinamento_id", "treq_tx_pergunta", "treq_tx_opcoes", "treq_nb_resposta_correta", "treq_nb_ordem"],
			[$treinamentoId, $pergunta, json_encode($opcoes), $respostaCorreta, $ordem]
		);

		registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "questao_criada", "Questão #$ordem adicionada");
		set_status("Questão cadastrada com sucesso!");
		$_POST["treinamento_id"] = $treinamentoId;
		$_POST["aba_avaliacao"] = 1;
		unset($_POST["salvar_questao"]);
		editarForm();
		exit;
	}

	function excluirQuestao($questaoId = null, $treinamentoId = null) {
		if ($questaoId === null) $questaoId = $_POST["questao_id"] ?? $_GET["questao_id"] ?? 0;
		if ($treinamentoId === null) $treinamentoId = $_POST["treinamento_id"] ?? $_GET["treinamento_id"] ?? 0;
		$questaoId = (int)$questaoId;
		$treinamentoId = (int)$treinamentoId;
		if ($questaoId > 0) {
			query("DELETE FROM treinamento_questao WHERE treq_nb_id = ?", "i", [$questaoId]);
			registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "questao_excluida", "Questão #$questaoId removida");
			set_status("Questão removida com sucesso!");
		}
		$_POST["treinamento_id"] = $treinamentoId;
		$_POST["aba_avaliacao"] = 1;
		unset($_GET["acao_excluir_questao"], $_POST["acao_excluir_questao"]);
		editarForm();
		exit;
	}

	// =====================================================
	// EPISÓDIOS (séries de vídeos)
	// =====================================================

	function cadastrarEpisodio() {
		global $conn;
		$treinamentoId = (int)($_POST["treinamento_id"] ?? $_POST["id"] ?? 0);
		$episodioId = (int)($_POST["episodio_id"] ?? 0);
		$titulo = trim($_POST["epi_titulo"] ?? "");
		if ($treinamentoId <= 0 || empty($titulo)) {
			set_status("ERRO: Informe o título do episódio!");
			$_POST["episodio_edit"] = $episodioId;
			editarForm();
			exit;
		}

		$cargaHoraria = 0;
if (!empty($_POST["epi_carga_horaria"])) {
					$partes = array_map('intval', explode(":", $_POST["epi_carga_horaria"]));
					$cargaHoraria = (int)($partes[0] ?? 0) * 3600 + (int)($partes[1] ?? 0) * 60 + (int)($partes[2] ?? 0);
				}
		$notaMinima = ($_POST["epi_nota_minima"] ?? "") !== "" ? (int)$_POST["epi_nota_minima"] : null;

		$campos = [
			"trepi_nb_treinamento_id" => $treinamentoId,
			"trepi_tx_titulo" => $titulo,
			"trepi_tx_descricao" => $_POST["epi_descricao"] ?? null,
			"trepi_tx_url_video" => $_POST["epi_url_video"] ?? null,
			"trepi_tx_tipo_video" => $_POST["epi_tipo_video"] ?? "youtube",
			"trepi_nb_carga_horaria" => $cargaHoraria,
			"trepi_nb_nota_minima_aprovacao" => $notaMinima,
		];

		if ($episodioId > 0) {
			$campos["trepi_tx_status"] = "ativo";
			atualizar("treinamento_episodio", array_keys($campos), array_values($campos), $episodioId);
			set_status("Episódio atualizado com sucesso!");
		} else {
			$cnt = mysqli_fetch_assoc(query(
				"SELECT COUNT(*) as total FROM treinamento_episodio WHERE trepi_nb_treinamento_id = ?",
				"i", [$treinamentoId]
			));
			$campos["trepi_nb_ordem"] = ($cnt["total"] ?? 0) + 1;
			$campos["trepi_tx_status"] = "ativo";
			inserir("treinamento_episodio", array_keys($campos), array_values($campos));
			$episodioId = (int)mysqli_insert_id($conn);
			set_status("Episódio cadastrado com sucesso!");
		}

		registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "episodio_salvo", "Episódio #$episodioId: $titulo");

		// Questões da avaliação do episódio cadastradas junto (lote)
		if (!empty($_POST["epi_q_avaliacao"]["pergunta"]) && is_array($_POST["epi_q_avaliacao"]["pergunta"])) {
			$cntQE = mysqli_fetch_assoc(query(
				"SELECT COUNT(*) as total FROM treinamento_episodio_questao WHERE trepq_nb_episodio_id = ?",
				"i", [$episodioId]
			));
			$ordemQE = (int)($cntQE["total"] ?? 0) + 1;
			foreach ($_POST["epi_q_avaliacao"]["pergunta"] as $i => $perguntaQE) {
				$perguntaQE = trim((string)$perguntaQE);
				if ($perguntaQE === "" || $ordemQE > 10) continue;
				$corretaQE = $_POST["epi_q_avaliacao"]["correta"][$i] ?? null;
				if ($corretaQE === null) continue; // sem opção correta marcada
				$opcoesQE = [
					$_POST["epi_q_avaliacao"]["opcao_1"][$i] ?? "",
					$_POST["epi_q_avaliacao"]["opcao_2"][$i] ?? "",
					$_POST["epi_q_avaliacao"]["opcao_3"][$i] ?? "",
					$_POST["epi_q_avaliacao"]["opcao_4"][$i] ?? ""
				];
				$corretaQE = (int)$corretaQE;
				inserir("treinamento_episodio_questao",
					["trepq_nb_episodio_id", "trepq_tx_pergunta", "trepq_tx_opcoes", "trepq_nb_resposta_correta", "trepq_nb_ordem"],
					[$episodioId, $perguntaQE, json_encode($opcoesQE), $corretaQE, $ordemQE]
				);
				registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "questao_episodio_criada", "Questão #$ordemQE do episódio #$episodioId (junto ao salvamento)");
				$ordemQE++;
			}
		}

		$_POST["treinamento_id"] = $treinamentoId;
		$_POST["aba_episodios"] = 1;
		// Mantém o episódio em edição para cadastrar as questões da avaliação na sequência
		$_POST["episodio_edit"] = $episodioId;
		unset($_POST["salvar_episodio"]);
		editarForm();
		exit;
	}

	function excluirEpisodio($episodioId = null, $treinamentoId = null) {
		if ($episodioId === null) $episodioId = $_POST["episodio_id"] ?? $_GET["episodio_id"] ?? 0;
		if ($treinamentoId === null) $treinamentoId = $_POST["treinamento_id"] ?? $_GET["treinamento_id"] ?? 0;
		$episodioId = (int)$episodioId;
		$treinamentoId = (int)$treinamentoId;
		if ($episodioId > 0) {
			query("DELETE FROM treinamento_episodio WHERE trepi_nb_id = ?", "i", [$episodioId]);
			registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "episodio_excluido", "Episódio #$episodioId removido");
			set_status("Episódio removido com sucesso!");
		}
		$_POST["treinamento_id"] = $treinamentoId;
		$_POST["aba_episodios"] = 1;
		unset($_GET["acao_excluir_episodio"], $_POST["acao_excluir_episodio"]);
		editarForm();
		exit;
	}

	function cadastrarQuestaoEpisodio() {
		global $conn;
		$episodioId = (int)($_POST["episodio_id"] ?? 0);
		$treinamentoId = (int)($_POST["treinamento_id"] ?? $_POST["id"] ?? 0);
		$pergunta = trim($_POST["epi_qtd_pergunta"] ?? "");
		$opcoes = [
			$_POST["epi_qtd_opcao_1"] ?? "",
			$_POST["epi_qtd_opcao_2"] ?? "",
			$_POST["epi_qtd_opcao_3"] ?? "",
			$_POST["epi_qtd_opcao_4"] ?? ""
		];
		$respostaCorreta = isset($_POST["epi_qtd_resposta_correta"]) ? (int)$_POST["epi_qtd_resposta_correta"] : null;

		if ($episodioId <= 0 || empty($pergunta)) {
			set_status("ERRO: Preencha a pergunta da questão do episódio!");
			$_POST["episodio_edit"] = $episodioId;
			editarForm();
			exit;
		}

		if ($respostaCorreta === null) {
			set_status("ERRO: Marque a opção correta da questão do episódio!");
			$_POST["episodio_edit"] = $episodioId;
			editarForm();
			exit;
		}

		$cnt = mysqli_fetch_assoc(query(
			"SELECT COUNT(*) as total FROM treinamento_episodio_questao WHERE trepq_nb_episodio_id = ?",
			"i", [$episodioId]
		));
		$totalQuestoes = (int)($cnt["total"] ?? 0);
		if ($totalQuestoes >= 10) {
			set_status("ERRO: Máximo de 10 questões por episódio!");
			$_POST["episodio_edit"] = $episodioId;
			editarForm();
			exit;
		}

		$ordem = $totalQuestoes + 1;
		inserir("treinamento_episodio_questao",
			["trepq_nb_episodio_id", "trepq_tx_pergunta", "trepq_tx_opcoes", "trepq_nb_resposta_correta", "trepq_nb_ordem"],
			[$episodioId, $pergunta, json_encode($opcoes), $respostaCorreta, $ordem]
		);

		registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "questao_episodio_criada", "Questão #$ordem do episódio #$episodioId");
		set_status("Questão do episódio cadastrada com sucesso!");
		unset($_POST["salvar_questao_episodio"]);
		$_POST["episodio_edit"] = $episodioId;
		editarForm();
		exit;
	}

	function excluirQuestaoEpisodio($questaoId = null, $episodioId = null, $treinamentoId = null) {
		if ($questaoId === null) $questaoId = $_POST["questao_id"] ?? $_GET["questao_id"] ?? 0;
		if ($episodioId === null) $episodioId = $_POST["episodio_id"] ?? $_GET["episodio_id"] ?? 0;
		if ($treinamentoId === null) $treinamentoId = $_POST["treinamento_id"] ?? $_GET["treinamento_id"] ?? 0;
		$questaoId = (int)$questaoId;
		$episodioId = (int)$episodioId;
		$treinamentoId = (int)$treinamentoId;
		if ($questaoId > 0) {
			query("DELETE FROM treinamento_episodio_questao WHERE trepq_nb_id = ?", "i", [$questaoId]);
			set_status("Questão do episódio removida com sucesso!");
		}
		$_POST["episodio_edit"] = $episodioId;
		$_POST["aba_episodios"] = 1;
		$_POST["treinamento_id"] = $treinamentoId;
		unset($_GET["acao_excluir_questao_episodio"], $_POST["acao_excluir_questao_episodio"]);
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
		unset($_GET["acao_excluir_material"], $_POST["acao_excluir_material"]);
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
				$_GET["id"] = $id;
				index();
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
		$cargaHoraria = sprintf("%02d:%02d:%02d", floor($cargaHoraria / 3600), floor(($cargaHoraria % 3600) / 60), $cargaHoraria % 60);
		$diasValidade = $dados["trei_nb_dias_validade"] ?? 365;
		$thumbnail = $dados["trei_tx_thumbnail"] ?? "";
		$dataPublicacao = !empty($dados["trei_dt_data_publicacao"]) ? date("Y-m-d", strtotime($dados["trei_dt_data_publicacao"])) : date("Y-m-d");
		$dataLiberacao = !empty($dados["trei_dt_data_liberacao"]) ? date("Y-m-d", strtotime($dados["trei_dt_data_liberacao"])) : date("Y-m-d");
		$obrigatorio = $dados["trei_nb_obrigatorio"] ?? 0;
		$gerarNotificacao = ($dados["trei_tx_gerar_notificacao"] ?? "nao") === "sim";
		$ehSerie = ($dados["trei_tx_serie"] ?? "nao") === "sim";

		// Episódios da série
		$episodios = [];
		if ($isEdicao && $ehSerie) {
			$rsEpi = query("SELECT * FROM treinamento_episodio WHERE trepi_nb_treinamento_id = ? ORDER BY trepi_nb_ordem, trepi_nb_id", "i", [$dados["trei_nb_id"]]);
			while ($rsEpi && ($r = mysqli_fetch_assoc($rsEpi))) {
				$episodios[] = $r;
			}
		}

		// Episódio em edição (via GET/POST episodio_edit)
		$episodioEditId = (int)($_POST["episodio_edit"] ?? $_GET["episodio_edit"] ?? 0);
		if ($episodioEditId === 0 && isset($_POST["episodio_edit"]) && $_POST["episodio_edit"] === "nova") {
			$episodioEditId = -1; // novo episódio
		}
		if ($episodioEditId === 0 && isset($_GET["episodio_edit"]) && $_GET["episodio_edit"] === "nova") {
			$episodioEditId = -1;
		}
		$episodioEdit = null;
		if ($episodioEditId > 0) {
			$episodioEdit = carregar("treinamento_episodio", $episodioEditId);
		}

		// Questões do episódio em edição
		$episodioQuestoes = [];
		if (!empty($episodioEdit)) {
			$rsQ = query("SELECT * FROM treinamento_episodio_questao WHERE trepq_nb_episodio_id = ? ORDER BY trepq_nb_ordem, trepq_nb_id", "i", [$episodioEdit["trepi_nb_id"]]);
			while ($rsQ && ($r = mysqli_fetch_assoc($rsQ))) {
				$episodioQuestoes[] = $r;
			}
		}

		// Aba ativa: Episódios quando há episódio em edição ou quando se está gerenciando episódios
		$abaAtiva = ($isEdicao && $ehSerie && ($episodioEditId !== 0 || isset($_GET["aba_episodios"]) || isset($_POST["aba_episodios"]))) ? "episodios" : "dados";
		if ($isEdicao && !$ehSerie && (isset($_GET["aba_avaliacao"]) || isset($_POST["aba_avaliacao"]))) {
			$abaAtiva = "avaliacao";
		}

		// Questões do treinamento (não-série)
		$questoesTreinamento = [];
		if ($isEdicao && !$ehSerie) {
			$rsQTre = query("SELECT * FROM treinamento_questao WHERE treq_nb_treinamento_id = ? AND treq_tx_status = 'ativo' ORDER BY treq_nb_ordem, treq_nb_id", "i", [$dados["trei_nb_id"]]);
			while ($rsQTre && ($rQTre = mysqli_fetch_assoc($rsQTre))) {
				$questoesTreinamento[] = $rQTre;
			}
		}
		$status = $dados["trei_tx_status"] ?? "ativo";
		$notaMinima = $dados["trei_nb_nota_minima_aprovacao"] ?? 70;
		$maxTentativas = $dados["trei_nb_max_tentativas"] ?? 2;
		$qtdQuestoes = $dados["trei_nb_quantidade_questoes_prova"] ?? 5;
		$perfisPermitidos = !empty($dados["trei_tx_tipo_usuario_permitido"]) ? json_decode($dados["trei_tx_tipo_usuario_permitido"], true) : [];

		// Empresas habilitadas (vazio = todas)
		$empresasHabilitadas = [];
		if (!empty($dados["trei_tx_empresas_habilitadas"])) {
			$empresasHabilitadas = json_decode($dados["trei_tx_empresas_habilitadas"], true);
			if (!is_array($empresasHabilitadas)) $empresasHabilitadas = [];
		}

		// Buscar perfis de acesso cadastrados
		$perfis = [];
		$rsPerfis = query("SELECT perfil_nb_id, perfil_tx_nome FROM perfil_acesso WHERE perfil_tx_status = 'ativo' ORDER BY perfil_tx_nome");
		while ($row = mysqli_fetch_assoc($rsPerfis)) {
			$perfis[] = $row;
		}

		// Buscar empresas cadastradas
		$empresasCad = [];
		$rsEmpresas = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome");
		while ($row = mysqli_fetch_assoc($rsEmpresas)) {
			$empresasCad[] = $row;
		}

		// Funcionários para o instrutor (com empresa)
		$funcionariosInstrutor = [];
		$rsFuncInstr = query(
			"SELECT e.enti_nb_id, e.enti_tx_nome, e.enti_nb_empresa, emp.empr_tx_nome
			 FROM entidade e
			 LEFT JOIN empresa emp ON emp.empr_nb_id = e.enti_nb_empresa
			 WHERE e.enti_tx_status = 'ativo'
			 ORDER BY e.enti_tx_nome"
		);
		while ($rsFuncInstr && ($rFI = mysqli_fetch_assoc($rsFuncInstr))) {
			$funcionariosInstrutor[] = $rFI;
		}

		// Dados do instrutor
		$instrutorTipo = $dados["trei_tx_instrutor_tipo"] ?? "funcionario";
		$instrutorEntidadeId = (int)($dados["trei_nb_instrutor_entidade_id"] ?? 0);
		$instrutorNome = $dados["trei_tx_instrutor_nome"] ?? "";
		$instrutorCpf = $dados["trei_tx_instrutor_cpf"] ?? "";
		$instrutorCapacitacao = $dados["trei_tx_instrutor_capacitacao"] ?? "";
		$criadorNome = $dados["trei_tx_criador_nome"] ?? "";
		$criadorCargo = $dados["trei_tx_criador_cargo"] ?? "";
		$criadorSetor = $dados["trei_tx_criador_setor"] ?? "";

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
				 AND (". (empty($empresasHabilitadas) ? "1 = 1" : "u.user_nb_empresa IN (" . implode(",", array_map('intval', $empresasHabilitadas)) . ")") . ")
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
			/* ===== QUESTÕES DINÂMICAS (componente moderno) ===== */
			.questao-dinamica {
				background: linear-gradient(180deg, #ffffff, #f7fafc);
				border: 1px solid #e2e8f0;
				border-left: 4px solid #3c8dbc;
				border-radius: 10px;
				padding: 14px 16px;
				margin-bottom: 14px;
				box-shadow: 0 3px 10px rgba(15, 40, 80, 0.07);
				transition: box-shadow 0.2s ease, border-color 0.2s ease;
			}
			.questao-dinamica:hover {
				box-shadow: 0 6px 18px rgba(15, 40, 80, 0.13);
				border-color: #b9d4e8;
			}
			.questao-dinamica .qd-header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				margin-bottom: 12px;
			}
			.questao-dinamica .qd-numero {
				display: inline-flex;
				align-items: center;
				gap: 8px;
				background: linear-gradient(135deg, #3c8dbc, #2c6a86);
				color: #fff;
				border-radius: 20px;
				padding: 4px 16px;
				font-size: 13px;
				font-weight: 600;
				letter-spacing: 0.3px;
				box-shadow: 0 2px 8px rgba(60, 141, 188, 0.35);
			}
			.questao-dinamica label { font-weight: 600; color: #33475b; font-size: 13px; }
			.questao-dinamica textarea.qd-pergunta {
				min-height: 72px;
				resize: vertical;
				border: 1px solid #d1d9e6;
				border-radius: 8px;
				font-size: 14px;
				padding: 10px 12px;
				transition: border-color 0.2s, box-shadow 0.2s;
			}
			.questao-dinamica textarea.qd-pergunta:focus {
				border-color: #3c8dbc;
				box-shadow: 0 0 0 3px rgba(60, 141, 188, 0.15);
			}
			.questao-dinamica .qd-opcao { position: relative; margin-bottom: 10px; }
			.questao-dinamica .qd-opcao input[type=\"text\"] {
				width: 100%;
				min-height: 48px;
				font-size: 14px;
				padding: 11px 14px 11px 46px;
				border: 1px solid #d1d9e6;
				border-radius: 8px;
				transition: border-color 0.2s, box-shadow 0.2s;
			}
			.questao-dinamica .qd-opcao input[type=\"text\"]:focus {
				border-color: #3c8dbc;
				box-shadow: 0 0 0 3px rgba(60, 141, 188, 0.15);
			}
			.questao-dinamica .qd-radio {
				position: absolute;
				left: 12px;
				top: 50%;
				transform: translateY(-50%);
				z-index: 2;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 26px;
				height: 26px;
				background: #fff;
				border: 1px solid #cbd5e1;
				border-radius: 50%;
				cursor: pointer;
				transition: border-color 0.2s, background 0.2s;
			}
			.questao-dinamica .qd-radio:hover { border-color: #27ae60; background: #f0fdf4; }
			.questao-dinamica .qd-radio input[type=\"radio\"] {
				width: 15px;
				height: 15px;
				margin: 0;
				cursor: pointer;
				accent-color: #27ae60;
			}
			.questao-dinamica .qd-legenda {
				display: flex;
				align-items: center;
				justify-content: space-between;
				flex-wrap: wrap;
				gap: 8px;
				margin-top: 8px;
				padding-top: 10px;
				border-top: 1px dashed #e2e8f0;
			}
			.opcao-radio { cursor: pointer; font-weight: normal; }
			.opcao-radio input[type=\"radio\"] { accent-color: #27ae60; margin-right: 3px; }
			.material-item { display: flex; align-items: center; justify-content: space-between; padding: 8px; background: #f5f5f5; border-radius: 4px; margin-bottom: 5px; }
			.perfil-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
			.perfil-card-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: 10px; }
			.perfil-card-titulo { font-size: 14px; color: #2c6a86; }
			.perfil-card-contador { background: #f0f7fb; border: 1px solid #d5e6f2; border-radius: 15px; padding: 3px 12px; font-size: 12px; color: #555; }
			.perfil-contador-num { font-size: 14px; color: #3c8dbc; }
			.atribuicao-resumo { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 15px; }
			.atribuicao-resumo-item { flex: 1; min-width: 150px; background: linear-gradient(135deg, #3c8dbc, #2c6a86); border-radius: 10px; padding: 15px; text-align: center; color: #fff; box-shadow: 0 3px 8px rgba(60,141,188,0.3); }
			.atribuicao-resumo-item-sucesso { background: linear-gradient(135deg, #27ae60, #1e8449); box-shadow: 0 3px 8px rgba(39,174,96,0.3); }
			.atribuicao-resumo-item-info { background: linear-gradient(135deg, #337ab7, #23527c); box-shadow: 0 3px 8px rgba(51,122,183,0.3); }
			.atribuicao-resumo-num { font-size: 26px; font-weight: bold; }
			.atribuicao-resumo-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.9; }
		</style>

		<div class='box box-primary'>
			<div class='box-header with-border'>
				<h3 class='box-title'>" . ($isEdicao ? "Editar Treinamento" : "Novo Treinamento") . "</h3>
			</div>
			<form method='POST' enctype='multipart/form-data' id='formTreinamento' action='cadastro_treinamento.php'>
				<div class='box-body'>
					<ul class='nav nav-tabs'>
						<li class='" . ($abaAtiva === "dados" ? "active" : "") . "'><a href='#tab_dados' data-toggle='tab'>Dados Gerais</a></li>" .
						($isEdicao ? "<li class='" . ($abaAtiva === "atribuicao" ? "active" : "") . "'><a href='#tab_atribuicao' data-toggle='tab'>Atribuições</a></li>" : "") .
						($isEdicao && $ehSerie ? "<li class='" . ($abaAtiva === "episodios" ? "active" : "") . "'><a href='#tab_episodios' data-toggle='tab'>Episódios</a></li>" : ($isEdicao ? "" : "<li style='display:none;'><a href='#tab_episodios' data-toggle='tab'>Episódios</a></li>")) .
						(!$ehSerie ? "<li class='" . ($abaAtiva === "avaliacao" ? "active" : "") . "'><a href='#tab_avaliacao' data-toggle='tab'>Avaliação</a></li>" : "") .
					"</ul>
					<div class='tab-content'>
						<div class='tab-pane " . ($abaAtiva === "dados" ? "active" : "") . "' id='tab_dados'>
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
								<div class='col-md-3'>
									" . campo("Duração (hh:mm:ss)", "carga_horaria", $cargaHoraria, "col-md-12", "", "placeholder='00:00:00' onfocus='this.select()'") . "
								</div>
								<div class='col-md-2'>
									" . campo("Nota Mínima (%)", "nota_minima_aprovacao", $notaMinima, "col-md-12", "70") . "
								</div>
								<div class='col-md-2'>
									" . campo("Tentativas Avaliação", "max_tentativas", $maxTentativas, "col-md-12", "2") . "
								</div>
								<div class='col-md-5' style='margin-top:25px;'>
									<small class='text-muted'>
										<i class='fa fa-info-circle'></i> Tentativas da avaliação: <strong>2</strong> (padrão) - se errar todas, precisa <strong>reassistir o vídeo</strong>.
										Se informar <strong>0</strong>: 10 tentativas, bloqueio de <strong>1 hora</strong> e repete o ciclo.
										Para séries, vale para <strong>todos os episódios</strong>.
									</small>
									" . ($isEdicao && !$ehSerie ? "<a href='#tab_avaliacao' onclick=\"abrirAbaAvaliacao(); return false;\" class='btn btn-xs btn-info' style='margin-left:8px;'><i class='fa fa-clipboard-list'></i> Gerenciar Avaliação</a>" : "") . "
								</div>
							</div>
							<div class='row'>
								<div class='col-md-4'>
									" . campo("Dias de Validade", "dias_validade", $diasValidade, "col-md-12", "999") . "
								</div>
								<div class='col-md-4'>
									" . campo_data("Data Liberação", "data_liberacao", $dataLiberacao, "col-md-12") . "
								</div>
							</div>
							<div class='row' id='div_campos_video'>
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
							<div class='alert alert-info' id='div_aviso_serie' style='display:none;'>
								<i class='fa fa-video-camera'></i> <strong>Série de vídeos ativada!</strong> Os vídeos serão cadastrados como <strong>episódios</strong> (aba Episódios após salvar). Cada episódio terá sua própria avaliação com até 10 questões.
							</div>
							<div class='row'>
								<div class='col-md-4' style='margin-top:25px;'>
									<label>
										<input type='checkbox' name='obrigatorio' value='1' " . ($obrigatorio ? "checked" : "") . "> Obrigatório
									</label>
								</div>
								<div class='col-md-4' style='margin-top:25px;'>
									<label>
										<input type='checkbox' name='serie_videos' value='1' " . ($ehSerie ? "checked" : "") . "> <i class='fa fa-video-camera'></i> Série de vídeos (múltiplos episódios)
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
							<div class='row' style='margin-top:10px;'>
								<div class='col-md-12'>
									<label>Empresas Habilitadas:</label><br>
									<div style='max-height:150px;overflow-y:auto;border:1px solid #ddd;padding:10px;border-radius:4px;background:#f9f9f9;'>
										<div class='row' id='listaEmpresasHabilitadas'>";
										if (empty($empresasCad)) {
											echo "<p class='text-muted'>Nenhuma empresa cadastrada.</p>";
										} else {
											foreach ($empresasCad as $emp) {
												$checked = (empty($empresasHabilitadas) || in_array($emp["empr_nb_id"], $empresasHabilitadas)) ? " checked" : "";
												echo "
											<div class='col-md-6 col-sm-6'>
												<label style='font-weight:normal;cursor:pointer;'>
													<input type='checkbox' class='checkbox-empresa' name='empresas_habilitadas[]' value='{$emp["empr_nb_id"]}'{$checked}> " . htmlspecialchars($emp["empr_tx_nome"]) . "
												</label>
											</div>";
											}
										}
										echo "
										</div>
									</div>
									<div style='margin-top:5px;'>
										<button type='button' class='btn btn-xs btn-success btn-marcar-empresas' data-marcar='1'><i class='fa fa-check'></i> Marcar todas</button>
										<button type='button' class='btn btn-xs btn-default btn-marcar-empresas' data-marcar='0'><i class='fa fa-times'></i> Desmarcar todas</button>
									</div>
									<small class='text-muted'>Todas marcadas por padrão. Desmarque uma empresa para que seus funcionários NÃƒO recebam este treinamento (mesmo com o perfil habilitado).</small>
								</div>
							</div>
							<div class='row'>
								<div class='col-md-12'>
									<label>Material de Apoio (PDF ou Imagem):</label>
									<input type='file' name='material_arquivo[]' id='materialArquivo' multiple accept='.pdf,.jpg,.jpeg,.png,.gif,.webp' class='form-control'>
									<small class='text-muted'>" . ($isEdicao ? "Selecione um ou mais arquivos e clique em <strong>Enviar materiais</strong>." : "Selecione um ou mais arquivos - serão enviados ao <strong>salvar</strong> o treinamento.") . "</small>
									" . ($isEdicao ? "<div style='margin-top:6px;'>
										<button type='button' class='btn btn-sm btn-info' id='btnEnviarMateriais'><i class='fa fa-upload'></i> Enviar materiais</button>
									</div>" : "") . "
									<div id='filaMateriais' style='margin-top:8px;'></div>";
									if ($isEdicao) {
										$materiais = [];
										$rsMateriais = query(
											"SELECT * FROM treinamento_material WHERE tram_nb_treinamento_id = ? AND tram_tx_status = 'ativo' ORDER BY tram_nb_ordem",
											"i", [$dados["trei_nb_id"]]
										);
										while ($row = mysqli_fetch_assoc($rsMateriais)) {
											$materiais[] = $row;
										}
										echo "<div id='listaMateriaisExistentes' style='margin-top:10px;'>";
										if (empty($materiais)) {
											echo "<p class='text-muted' style='margin:0;'><i class='fa fa-file-o'></i> Nenhum material enviado ainda.</p>";
										} else {
											echo "<strong>Materiais já enviados:</strong><br>";
											foreach ($materiais as $m) {
												$tamanhoKB = round(($m["tram_nb_tamanho"] ?? 0) / 1024, 1);
												echo "<div class='material-item'>";
												echo "<span><i class='fa fa-file'></i> " . htmlspecialchars($m["tram_tx_nome"]) . " ({$tamanhoKB} KB)</span>";
												echo "<a href='cadastro_treinamento.php?acao_excluir_material={$m["tram_nb_id"]}&treinamento_id={$dados["trei_nb_id"]}' class='btn btn-danger btn-xs' onclick=\"return confirm('Excluir este material?');\"><i class='fa fa-trash'></i></a>";
												echo "</div>";
											}
										}
										echo "</div>";
									}
									echo "
								</div>
							</div>
							<div class='row' style='margin-top:10px;'>
								<div class='col-md-12'>
									<h4 style='border-bottom:1px solid #eee;padding-bottom:5px;'><i class='fa fa-user-tie'></i> Instrutor Responsável pelo Conteúdo</h4>
								</div>
								<div class='col-md-3'>
									" . combo("Tipo de Instrutor", "instrutor_tipo", $instrutorTipo, "col-md-12", ["funcionario" => "Funcionário da empresa", "externo" => "Externo"]) . "
								</div>
								<div class='col-md-9' id='div_instrutor_funcionario'>
									<label>Funcionário Instrutor:</label>
									<select name='instrutor_entidade_id' id='selectInstrutorFunc' class='form-control'>
										<option value=''>Selecione o funcionário...</option>";
										foreach ($funcionariosInstrutor as $fi) {
											$selInstr = ((int)$fi["enti_nb_id"] === $instrutorEntidadeId) ? " selected" : "";
											$empLabel = !empty($fi["empr_tx_nome"]) ? " (" . htmlspecialchars($fi["empr_tx_nome"]) . ")" : "";
											echo "<option value='{$fi["enti_nb_id"]}' data-empresa='{$fi["enti_nb_empresa"]}'{$selInstr}>" . htmlspecialchars($fi["enti_tx_nome"]) . $empLabel . "</option>";
										}
										echo "
									</select>
									<small class='text-muted'>Funcionários das empresas habilitadas acima.</small>
								</div>
								<div class='col-md-12' id='div_instrutor_externo' style='display:" . ($instrutorTipo === "externo" ? "block" : "none") . ";'>
									<div class='row'>
										<div class='col-md-4'>" . campo("Nome do Instrutor Externo *", "instrutor_nome", $instrutorNome, "col-md-12") . "</div>
										<div class='col-md-3'>" . campo("CPF", "instrutor_cpf", $instrutorCpf, "col-md-12", "MASCARA_CPF") . "</div>
										<div class='col-md-5'>" . campo("Capacitação / Formação", "instrutor_capacitacao", $instrutorCapacitacao, "col-md-12") . "</div>
									</div>
								</div>
							</div>
						</div>";

						if ($isEdicao) {
							echo "
						<div class='tab-pane " . ($abaAtiva === "atribuicao" ? "active" : "") . "' id='tab_atribuicao'>
							<div class='row'>
								<div class='col-md-12'>
									<p class='text-muted'>Os funcionários dos perfis selecionados já vêm marcados (acesso liberado). Desmarque para bloquear o acesso individual de um funcionário específico.</p>
									<div id='listaUsuariosAtribuicao'>";
									if (empty($perfisComUsuarios)) {
										echo "<div class='alert alert-warning'><i class='fa fa-info-circle'></i> Selecione pelo menos um perfil na aba <strong>Dados Gerais</strong> para listar os funcionários aqui.</div>";
									} else {
										$totalGeral = 0;
										$selecionadosGeral = 0;
										foreach ($perfisComUsuarios as $grupo) {
											$totalGrupo = count($grupo["usuarios"]);
											$selGrupo = 0;
											foreach ($grupo["usuarios"] as $u) {
												if (!in_array($u["user_nb_id"], $bloqueados)) $selGrupo++;
											}
											$totalGeral += $totalGrupo;
											$selecionadosGeral += $selGrupo;
											$pctGrupo = $totalGrupo > 0 ? round(($selGrupo / $totalGrupo) * 100) : 0;
											echo "
										<div class='perfil-card'>
											<div class='perfil-card-header'>
												<div class='perfil-card-titulo'><i class='fa fa-users'></i> <strong>" . htmlspecialchars($grupo["perfil_tx_nome"]) . "</strong></div>
												<div class='perfil-card-contador'>
													<span class='perfil-contador-num'><strong>{$selGrupo}</strong>/{$totalGrupo}</span> selecionados
												</div>
											</div>
											<div class='progress' style='height:6px;margin-bottom:10px;'>
												<div class='progress-bar progress-bar-success perfil-bar' role='progressbar' style='width:{$pctGrupo}%'></div>
											</div>
											<div class='row'>";
											foreach ($grupo["usuarios"] as $u) {
												$checked = in_array($u["user_nb_id"], $bloqueados) ? "" : " checked";
												echo "
												<div class='col-md-4 col-sm-6'>
													<label style='font-weight:normal;cursor:pointer;'>
														<input type='checkbox' class='checkbox-perfil' name='usuarios_atribuidos[]' value='{$u["user_nb_id"]}'{$checked}>
														<input type='hidden' name='usuarios_origem[]' value='{$u["user_nb_id"]}'>
														" . htmlspecialchars($u["user_tx_nome"]) . "
													</label>
												</div>";
											}
											echo "
											</div>
										</div>";
										}
										$pctGeral = $totalGeral > 0 ? round(($selecionadosGeral / $totalGeral) * 100) : 0;
										echo "
									<div class='atribuicao-resumo'>
										<div class='atribuicao-resumo-item'>
											<div class='atribuicao-resumo-num'>{$totalGeral}</div>
											<div class='atribuicao-resumo-label'>Total de Funcionários</div>
										</div>
										<div class='atribuicao-resumo-item atribuicao-resumo-item-sucesso'>
											<div class='atribuicao-resumo-num' id='resumoSelecionados'>{$selecionadosGeral}</div>
											<div class='atribuicao-resumo-label'>Selecionados (com acesso)</div>
										</div>
										<div class='atribuicao-resumo-item atribuicao-resumo-item-info'>
											<div class='atribuicao-resumo-num' id='resumoPercentual'>{$pctGeral}%</div>
											<div class='atribuicao-resumo-label'>% com Acesso</div>
										</div>
									</div>";
									}
									echo "
									</div>
								</div>
							</div>
						</div>";
						}

						if (!$ehSerie) {
							echo "
						<div class='tab-pane " . ($abaAtiva === "avaliacao" ? "active" : "") . "' id='tab_avaliacao'>
							<div class='row'>
								<div class='col-md-12'>
									<p class='text-muted'><i class='fa fa-clipboard-list'></i> Avaliação do treinamento: o usuário só pode realizar a avaliação após <strong>assistir 100% do vídeo</strong>. Nota mínima para aprovação: <strong>{$notaMinima}%</strong> (configurada nos Dados Gerais). Máximo de <strong>10 questões</strong>, cada uma com até 4 opções.</p>

									<div class='box box-success box-solid' style='margin-top:10px;'>
										<div class='box-header with-border'><h3 class='box-title'><i class='fa fa-plus-circle'></i> Cadastrar Questões da Avaliação</h3></div>
										<div class='box-body'>
											<p class='text-muted'>Cadastre uma ou mais questões - elas serão salvas <strong>junto com o treinamento</strong>.</p>
											<button type='button' class='btn btn-sm btn-info' onclick=\"adicionarQuestaoDinamica('q_avaliacao', 'novasQuestoesTreinamento');\"><i class='fa fa-plus'></i> Adicionar Questão</button>
											<div id='novasQuestoesTreinamento' style='margin-top:10px;'></div>
										</div>
									</div>";

									if ($isEdicao) {
										if (count($questoesTreinamento) >= 10) {
											echo "<div class='alert alert-warning'><i class='fa fa-exclamation-triangle'></i> Máximo de 10 questões atingido.</div>";
										}

										if (empty($questoesTreinamento)) {
											echo "<p class='text-muted' style='margin-top:10px;'>Nenhuma questão cadastrada. Use o bloco <strong>Cadastrar Questões da Avaliação</strong> acima para adicionar.</p>";
										} else {
											echo "<h5 style='margin-top:15px;'><i class='fa fa-list-ol'></i> Questões Cadastradas</h5>";
											foreach ($questoesTreinamento as $qi => $q) {
												$opcoesQ = json_decode($q["treq_tx_opcoes"], true);
												$corretaQ = (int)$q["treq_nb_resposta_correta"];
												echo "
											<div class='questao-item'>
												<div style='display:flex; justify-content:space-between;'>
													<strong>Q" . ($qi + 1) . ": " . htmlspecialchars($q["treq_tx_pergunta"]) . "</strong>
													<a href='cadastro_treinamento.php?acao_excluir_questao={$q["treq_nb_id"]}&treinamento_id={$dados["trei_nb_id"]}' class='btn btn-danger btn-xs' onclick=\"return confirm('Excluir esta questão?');\"><i class='fa fa-trash'></i></a>
												</div>
												<div class='opcoes'>";
												foreach ($opcoesQ as $oi => $op) {
													$icon = ($oi === $corretaQ) ? "fa-check-circle text-green" : "fa-circle-o text-muted";
													echo "<i class='fa {$icon}'></i> " . htmlspecialchars($op) . "<br>";
												}
												echo "</div></div>";
											}
										}
										}

										echo "
								</div>
							</div>
						</div>";
						}

						if ($ehSerie || !$isEdicao) {
							echo "
						<div class='tab-pane " . ($abaAtiva === "episodios" ? "active" : "") . "' id='tab_episodios'>";

							if (!$isEdicao) {
								// CADASTRO NOVO DE SÉRIE: episódios dinâmicos salvos junto
								echo "
							<div class='row'>
								<div class='col-md-12'>
									<p class='text-muted'><i class='fa fa-video-camera'></i> Série de vídeos: o usuário só assiste o próximo episódio após <strong>concluir o vídeo</strong> e <strong>ser aprovado na avaliação</strong> do anterior. Cadastre abaixo os episódios da série - todos serão salvos <strong>junto com o treinamento</strong>.</p>

									<div class='box box-success box-solid'>
										<div class='box-header with-border'><h3 class='box-title'><i class='fa fa-plus-circle'></i> Cadastrar Episódios da Série</h3></div>
										<div class='box-body'>
											<button type='button' class='btn btn-sm btn-success' onclick=\"adicionarEpisodioSerie();\"><i class='fa fa-plus'></i> Adicionar Episódio</button>
											<div id='novosEpisodiosSerie' style='margin-top:12px;'></div>
										</div>
									</div>
								</div>
							</div>";
							} else {
							echo "
							<div class='row'>
								<div class='col-md-12'>
									<p class='text-muted'><i class='fa fa-video-camera'></i> Série de vídeos: o usuário só assiste o próximo episódio após <strong>concluir o vídeo</strong> e <strong>ser aprovado na avaliação</strong> do anterior. Aprovação: nota mínima configurada (individual ou geral da série).</p>

									<!-- LISTA DE EPISÓDIOS -->
									<div class='row' style='margin-bottom:10px;'>
										<div class='col-md-8'><h4 style='margin:0;'><i class='fa fa-list-ol'></i> Episódios da série (" . count($episodios) . ")</h4></div>
										<div class='col-md-4 text-right'>
											<a href='cadastro_treinamento.php?id={$dados["trei_nb_id"]}&episodio_edit=nova' class='btn btn-sm btn-success'><i class='fa fa-plus'></i> Adicionar Episódio</a>
										</div>
									</div>";

									if (empty($episodios)) {
										echo "<div class='alert alert-warning'><i class='fa fa-info-circle'></i> Nenhum episódio cadastrado. Clique em <strong>Adicionar Episódio</strong> para criar o primeiro vídeo da série.</div>";
									} else {
										echo "
									<table class='table table-bordered table-striped'>
										<thead>
											<tr>
												<th style='width:50px;'>Ordem</th>
												<th>Título</th>
												<th>Duração</th>
												<th>Questões</th>
												<th>Nota Mínima</th>
												<th style='width:110px;'>Ações</th>
											</tr>
										</thead>
										<tbody>";
										foreach ($episodios as $ep) {
											$cntEpi = mysqli_fetch_assoc(query(
												"SELECT COUNT(*) as total FROM treinamento_episodio_questao WHERE trepq_nb_episodio_id = ?",
												"i", [$ep["trepi_nb_id"]]
											));
											$durEpi = sprintf("%02dm:%02ds", floor(($ep["trepi_nb_carga_horaria"] ?? 0) / 60), ($ep["trepi_nb_carga_horaria"] ?? 0) % 60);
											$notaEpi = !empty($ep["trepi_nb_nota_minima_aprovacao"]) ? $ep["trepi_nb_nota_minima_aprovacao"] . "% (individual)" : $dados["trei_nb_nota_minima_aprovacao"] . "% (série)";
											echo "
											<tr>
												<td class='text-center'><strong>#" . $ep["trepi_nb_ordem"] . "</strong></td>
												<td>" . htmlspecialchars($ep["trepi_tx_titulo"]) . "</td>
												<td>{$durEpi}</td>
												<td>" . ($cntEpi["total"] ?? 0) . "/10</td>
												<td>{$notaEpi}</td>
												<td>
													<a href='cadastro_treinamento.php?id={$dados["trei_nb_id"]}&episodio_edit={$ep["trepi_nb_id"]}' class='btn btn-xs btn-primary' title='Editar'><i class='fa fa-pencil'></i></a>
													<a href='cadastro_treinamento.php?acao_excluir_episodio={$ep["trepi_nb_id"]}&treinamento_id={$dados["trei_nb_id"]}' class='btn btn-xs btn-danger' onclick=\"return confirm('Excluir este episódio?');\" title='Excluir'><i class='fa fa-trash'></i></a>
												</td>
											</tr>";
										}
										echo "
										</tbody>
									</table>";
									}

									// FORMULÁRIO DO EPISÓDIO (quando episodio_edit está ativo)
									if ($episodioEditId !== 0) {
										$epiTitulo = $episodioEdit["trepi_tx_titulo"] ?? "";
										$epiDescricao = $episodioEdit["trepi_tx_descricao"] ?? "";
										$epiUrl = $episodioEdit["trepi_tx_url_video"] ?? "";
										$epiTipoVideo = $episodioEdit["trepi_tx_tipo_video"] ?? "youtube";
										$epiCarga = (int)($episodioEdit["trepi_nb_carga_horaria"] ?? 0);
										$epiCarga = sprintf("%02d:%02d:%02d", floor($epiCarga / 3600), floor(($epiCarga % 3600) / 60), $epiCarga % 60);
										$epiNota = $episodioEdit["trepi_nb_nota_minima_aprovacao"] ?? "";
										echo "
									<div class='box box-info box-solid' style='margin-top:15px;'>
										<div class='box-header with-border'><h3 class='box-title'>" . ($episodioEditId > 0 ? "Editar Episódio" : "Novo Episódio") . "</h3></div>
										<div class='box-body'>
											<input type='hidden' name='episodio_id' value='" . ($episodioEditId > 0 ? $episodioEdit["trepi_nb_id"] : "") . "'>
											<input type='hidden' name='episodio_edit' value='{$episodioEditId}'>
											<div class='alert alert-warning' style='padding:10px 14px;'>
												<i class='fa fa-exclamation-triangle'></i> <strong>Como salvar este episódio:</strong><br>
												Preencha os campos e clique em <strong>Salvar Episódio</strong> (botão abaixo) para gravar tudo junto
												(episódio + questões da avaliação).<br>
												<span class='text-danger'><i class='fa fa-times-circle'></i> O botão <strong>Salvar</strong> do final da página grava
												apenas os dados gerais do treinamento e <strong>descartará as alterações deste episódio</strong>.</span>
											</div>
											<div class='row'>
												<div class='col-md-6'>" . campo("Título do Episódio *", "epi_titulo", $epiTitulo, "col-md-12") . "</div>
												<div class='col-md-2'>" . combo("Tipo Vídeo", "epi_tipo_video", $epiTipoVideo, "col-md-12", ["youtube" => "YouTube", "vimeo" => "Vimeo", "upload" => "Upload Local"]) . "</div>
												<div class='col-md-2'>" . campo("Duração (hh:mm:ss)", "epi_carga_horaria", $epiCarga, "col-md-12", "", "placeholder='00:00:00' onfocus='this.select()'") . "</div>
												<div class='col-md-2'>" . campo("Nota Mínima (%)", "epi_nota_minima", $epiNota, "col-md-12", "70") . "</div>
											</div>
											<div class='row'>
												<div class='col-md-8'>" . campo("URL do Vídeo", "epi_url_video", $epiUrl, "col-md-12") . "</div>
											</div>
											<div class='row'>
												<div class='col-md-12'>" . textarea("Descrição do Episódio", "epi_descricao", $epiDescricao, "col-md-12") . "</div>
											</div>
											<div class='box box-success box-solid' style='margin-top:10px;'>
												<div class='box-header with-border'><h3 class='box-title'><i class='fa fa-plus-circle'></i> Cadastrar Questões da Avaliação do Episódio</h3></div>
												<div class='box-body'>
													<p class='text-muted'>Cadastre uma ou mais questões - elas serão salvas <strong>junto com o episódio</strong>.</p>
													<button type='button' class='btn btn-sm btn-info' onclick=\"adicionarQuestaoDinamica('epi_q_avaliacao', 'novasQuestoesEpisodio');\"><i class='fa fa-plus'></i> Adicionar Questão</button>
													<div id='novasQuestoesEpisodio' style='margin-top:10px;'></div>
												</div>
											</div>
											<small class='text-muted'>
												<i class='fa fa-info-circle'></i> Deixe a Nota Mínima em branco para usar a nota geral da série (" . $dados["trei_nb_nota_minima_aprovacao"] . "%).
											</small>
											<hr>
											<div style='display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;'>
												<div>
													<button type='button' name='salvar_episodio' value='1' class='btn btn-success btn-lg' onclick=\"return submitForm('salvar_episodio');\"><i class='fa fa-save'></i> Salvar Episódio</button>
													<a href='cadastro_treinamento.php?id={$dados["trei_nb_id"]}&aba_episodios=1' class='btn btn-default btn-lg'><i class='fa fa-arrow-left'></i> Cancelar e voltar à lista</a>
												</div>
												<small class='text-muted'><i class='fa fa-info-circle'></i> Use <strong>Salvar Episódio</strong> para não perder estas alterações.</small>
											</div>
										</div>
									</div>";
									}

									// QUESTÕES DO EPISÓDIO (quando editando um episódio existente)
									if ($episodioEditId > 0 && !empty($episodioEdit)) {
										$epiIdAtual = (int)$episodioEdit["trepi_nb_id"];
										echo "
									<div class='box box-success box-solid' style='margin-top:15px;'>
										<div class='box-header with-border'><h3 class='box-title'><i class='fa fa-list-ol'></i> Questões Cadastradas do Episódio (" . count($episodioQuestoes) . "/10)</h3></div>
										<div class='box-body'>";
											if (empty($episodioQuestoes)) {
												echo "<p class='text-muted' style='margin-top:10px;'>Nenhuma questão cadastrada para este episódio. Use o bloco <strong>Cadastrar Questões da Avaliação do Episódio</strong> acima para adicionar.</p>";
											} else {
												foreach ($episodioQuestoes as $qi => $q) {
													$opcoesQ = json_decode($q["trepq_tx_opcoes"], true);
													$corretaQ = (int)$q["trepq_nb_resposta_correta"];
													echo "
												<div class='questao-item'>
													<div style='display:flex; justify-content:space-between;'>
														<strong>Q" . ($qi + 1) . ": " . htmlspecialchars($q["trepq_tx_pergunta"]) . "</strong>
														<a href='cadastro_treinamento.php?acao_excluir_questao_episodio={$q["trepq_nb_id"]}&episodio_id={$epiIdAtual}&treinamento_id={$dados["trei_nb_id"]}' class='btn btn-danger btn-xs' onclick=\"return confirm('Excluir esta questão?');\"><i class='fa fa-trash'></i></a>
													</div>
													<div class='opcoes'>";
													foreach ($opcoesQ as $oi => $op) {
														$icon = ($oi === $corretaQ) ? "fa-check-circle text-green" : "fa-circle-o text-muted";
														echo "<i class='fa {$icon}'></i> " . htmlspecialchars($op) . "<br>";
													}
													echo "</div></div>";
												}
											}
											echo "
										</div>
									</div>";
									}
									} // fim do else (conteúdo da edição da série)
									echo "
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
					" . ($isEdicao && $ehSerie && $episodioEditId !== 0 ? "
					<div class='alert alert-warning' style='padding:8px 12px; margin-bottom:10px;'>
						<i class='fa fa-exclamation-triangle'></i> <strong>Você está editando um episódio.</strong>
						O botão <strong>Salvar</strong> grava apenas os dados do treinamento e <strong>descarta as alterações do episódio</strong>.
						Clique em <strong>Salvar Episódio</strong> (na aba Episódios) para gravar o episódio antes de salvar o treinamento.
					</div>" : "") . "
					<button type='button' name='salvar' value='1' class='btn btn-primary' onclick=\"return submitForm('salvar');\"><i class='fa fa-save'></i> Salvar</button>
					<a href='cadastro_treinamento.php' class='btn btn-default'><i class='fa fa-arrow-left'></i> Voltar</a>
				</div>
			</form>
		</div>

		<script>
			var episodioEditAtivo = " . (($isEdicao && $ehSerie && $episodioEditId !== 0) ? "true" : "false") . ";
			function submitForm(acao){
				// Proteção: se há episódio em edição e o usuário clica em Salvar (treinamento),
				// avisa que as alterações do episódio serão descartadas
				if(acao === 'salvar' && episodioEditAtivo) {
					Swal.fire({
						icon: 'warning',
						title: 'Atenção!',
						html: 'Você está <strong>editando um episódio</strong>.<br><br>O botão <strong>Salvar</strong> grava apenas os dados do treinamento e <strong>descartará as alterações do episódio</strong>.<br><br>Clique em <strong>Salvar Episódio</strong> (aba Episódios) para gravar o episódio.',
						showCancelButton: true,
						confirmButtonColor: '#3c8dbc',
						cancelButtonColor: '#d9534f',
						confirmButtonText: '<i class=\"fa fa-check\"></i> Entendi, salvar apenas o treinamento',
						cancelButtonText: '<i class=\"fa fa-times\"></i> Cancelar'
					}).then((result) => {
						if(result.isConfirmed) {
							executarSubmitForm(acao);
						}
					});
					return false;
				}
				return executarSubmitForm(acao);
			}
			function executarSubmitForm(acao){
				if(acao === 'salvar' || acao === 'salvar_episodio') {
					var problemas = validarQuestoesDinamicas();
					if(problemas.length > 0) {
						var lista = problemas.map(function(p){
							return '<div style=\"text-align:left;\"><i class=\"fa fa-circle-o text-danger\"></i> ' + jQuery('<span>').text(p).html() + '</div>';
						}).join('');
						Swal.fire({
							icon: 'warning',
							title: 'Atenção!',
							html: 'Marque a <strong>opção correta</strong> nas questões abaixo:<br><br>' + lista,
							confirmButtonColor: '#3c8dbc'
						});
						return false;
					}
				}
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
			// Abre a aba Avaliação (treinamento único)
			function abrirAbaAvaliacao(){
				var el = document.querySelector('a[href=\"#tab_avaliacao\"]');
				if(el && window.jQuery && jQuery.fn.tab) {
					jQuery(el).tab('show');
				}
			}

			// ===== QUESTÕES DINÂMICAS (cadastro junto com o salvamento) =====
			var contadorQuestaoDinamica = 0;

			function htmlQuestaoDinamica(prefixo) {
				var idx = contadorQuestaoDinamica++;
				var num = contadorQuestaoDinamica;
				var h = '<div class=\"questao-dinamica\" data-qd-idx=\"' + idx + '\">';
				h += '<div class=\"qd-header\">';
				h += '<span class=\"qd-numero\"><i class=\"fa fa-question-circle\"></i> Questão ' + num + '</span>';
				h += '<button type=\"button\" class=\"btn btn-danger btn-xs\" onclick=\"removerQuestaoDinamica(this)\"><i class=\"fa fa-trash\"></i> Remover</button>';
				h += '</div>';
				h += '<div class=\"row\"><div class=\"col-md-12\"><label>Pergunta *</label>';
				h += '<textarea name=\"' + prefixo + '[pergunta][' + idx + ']\" class=\"form-control qd-pergunta\" rows=\"2\"></textarea>';
				h += '</div></div>';
				h += '<div class=\"row\" style=\"margin-top:10px;\">';
				for(var i = 0; i < 4; i++) {
					h += '<div class=\"col-md-6\"><div class=\"qd-opcao\">';
					h += '<span class=\"qd-radio\" title=\"Marcar como correta\"><input type=\"radio\" name=\"' + prefixo + '[correta][' + idx + ']\" value=\"' + i + '\"></span>';
					h += '<input type=\"text\" name=\"' + prefixo + '[opcao_' + (i + 1) + '][' + idx + ']\" class=\"form-control\" placeholder=\"Opção ' + (i + 1) + '\">';
					h += '</div></div>';
				}
				h += '</div>';
				h += '<div class=\"qd-legenda\">';
				h += '<small class=\"text-muted\"><i class=\"fa fa-check-circle text-green\"></i> Marque <strong>1 (uma) opção</strong> como correta em cada questão.</small>';
				h += '</div></div>';
				return h;
			}

			function adicionarQuestaoDinamica(prefixo, containerId) {
				var container = document.getElementById(containerId);
				if(!container) return;
				container.insertAdjacentHTML('beforeend', htmlQuestaoDinamica(prefixo));
			}

			function renumerarQuestoesDinamicas() {
				var blocos = document.querySelectorAll('.questao-dinamica');
				Array.prototype.forEach.call(blocos, function(b, i) {
					var num = b.querySelector('.qd-numero');
					if(num) num.innerHTML = '<i class=\"fa fa-question-circle\"></i> Questão ' + (i + 1);
				});
			}

			function removerQuestaoDinamica(btn) {
				var bloco = btn.closest('.questao-dinamica');
				if(bloco) bloco.remove();
				renumerarQuestoesDinamicas();
			}

			// Valida se toda questão com pergunta preenchida tem a opção correta marcada
			function validarQuestoesDinamicas() {
				var problemas = [];
				jQuery('.questao-dinamica').each(function() {
					var bloco = jQuery(this);
					var pergunta = bloco.find('textarea').val().trim();
					if(pergunta === '') return;
					if(bloco.find('input[type=radio]:checked').length === 0) {
						problemas.push(pergunta.length > 60 ? pergunta.substring(0, 60) + '...' : pergunta);
					}
				});
				return problemas;
			}

			// ===== EPISÓDIOS DINÂMICOS (série cadastrada de uma vez) =====
			var contadorEpisodioSerie = 0;

			function adicionarQuestaoEpisodioSerie(idx) {
				var container = document.getElementById('questoesEpisodio_' + idx);
				if(!container) return;
				container.insertAdjacentHTML('beforeend', htmlQuestaoDinamica('novo_epi[' + idx + '][questoes]'));
			}

			function htmlEpisodioSerie(idx) {
				var h = '<div class=\"episodio-serie-dinamico\" style=\"border:1px solid #ddd;border-radius:6px;padding:10px;margin-bottom:12px;background:#fcfcfc;\">';
				h += '<div class=\"row\"><div class=\"col-md-12\"><strong><i class=\"fa fa-video-camera\"></i> Episódio #' + (idx + 1) + '</strong> ';
				h += '<button type=\"button\" class=\"btn btn-danger btn-xs\" onclick=\"removerEpisodioSerie(this)\"><i class=\"fa fa-trash\"></i> Remover episódio</button></div></div>';
				h += '<div class=\"row\" style=\"margin-top:6px;\">';
				h += '<div class=\"col-md-4\"><label>Título do Episódio *</label><input type=\"text\" name=\"novo_epi[' + idx + '][titulo]\" class=\"form-control input-sm\"></div>';
				h += '<div class=\"col-md-2\"><label>Tipo Vídeo</label><select name=\"novo_epi[' + idx + '][tipo_video]\" class=\"form-control input-sm\"><option value=\"youtube\">YouTube</option><option value=\"vimeo\">Vimeo</option><option value=\"upload\">Upload Local</option></select></div>';
				h += '<div class=\"col-md-2\"><label>Duração (hh:mm:ss)</label><input type=\"text\" name=\"novo_epi[' + idx + '][carga_horaria]\" class=\"form-control input-sm\" placeholder=\"00:00:00\" oninput=\"mascaraDuracao(this)\"></div>';
				h += '<div class=\"col-md-2\"><label>Nota Mínima (%)</label><input type=\"number\" name=\"novo_epi[' + idx + '][nota_minima]\" class=\"form-control input-sm\" placeholder=\"70\"></div>';
				h += '</div>';
				h += '<div class=\"row\" style=\"margin-top:6px;\"><div class=\"col-md-12\"><label>URL do Vídeo</label><input type=\"text\" name=\"novo_epi[' + idx + '][url_video]\" class=\"form-control input-sm\"></div></div>';
				h += '<div class=\"row\" style=\"margin-top:6px;\"><div class=\"col-md-12\"><label>Descrição</label><textarea name=\"novo_epi[' + idx + '][descricao]\" class=\"form-control input-sm\" rows=\"2\"></textarea></div></div>';
				h += '<div style=\"margin-top:10px;border-top:1px dashed #ccc;padding-top:8px;\">';
				h += '<button type=\"button\" class=\"btn btn-xs btn-info\" onclick=\"adicionarQuestaoEpisodioSerie(' + idx + ')\"><i class=\"fa fa-plus\"></i> Adicionar Questão deste Episódio</button>';
				h += '<div id=\"questoesEpisodio_' + idx + '\" style=\"margin-top:8px;\"></div>';
				h += '</div></div>';
				return h;
			}

			function adicionarEpisodioSerie() {
				var container = document.getElementById('novosEpisodiosSerie');
				if(!container) return;
				var idx = contadorEpisodioSerie++;
				container.insertAdjacentHTML('beforeend', htmlEpisodioSerie(idx));
			}

			function removerEpisodioSerie(btn) {
				var bloco = btn.closest('.episodio-serie-dinamico');
				if(bloco) bloco.remove();
			}

			// ===== MATERIAL DE APOIO (upload múltiplo) =====
			var treinamentoIdForm = jQuery('input[name=treinamento_id]').val() || jQuery('input[name=id]').val() || '';

			function renderFilaMateriais() {
				var input = document.getElementById('materialArquivo');
				var container = jQuery('#filaMateriais');
				if(!input || !input.files || !input.files.length) { container.html(''); return; }
				var html = '<strong>Arquivos selecionados:</strong><br>';
				Array.from(input.files).forEach(function(f, i) {
					var nome = jQuery('<span>').text(f.name).html();
					var kb = Math.round(f.size / 1024);
					html += '<div class=\"material-item\" style=\"margin-bottom:4px;\">' +
						'<span><i class=\"fa fa-file\"></i> ' + nome + ' (' + kb + ' KB)</span>' +
						'<button type=\"button\" class=\"btn btn-danger btn-xs\" onclick=\"removerArquivoFila(' + i + ')\"><i class=\"fa fa-trash\"></i></button>' +
					'</div>';
				});
				container.html(html);
			}

			function removerArquivoFila(idx) {
				var input = document.getElementById('materialArquivo');
				if(!input || !input.files) return;
				var arquivos = Array.from(input.files);
				arquivos.splice(idx, 1);
				if(window.DataTransfer) {
					var dt = new DataTransfer();
					arquivos.forEach(function(f){ dt.items.add(f); });
					input.files = dt.files;
				}
				renderFilaMateriais();
			}

			function adicionarMaterialLista(m) {
				var container = jQuery('#listaMateriaisExistentes');
				if(!container.length) return;
				if(container.find('.material-item').length === 0) container.html('');
				var nome = jQuery('<span>').text(m.nome).html();
				container.append('<div class=\"material-item\">' +
					'<span><i class=\"fa fa-file\"></i> ' + nome + ' (' + m.tamanho_kb + ' KB)</span>' +
					'<a href=\"cadastro_treinamento.php?acao_excluir_material=' + m.id + '&treinamento_id=' + treinamentoIdForm + '\" class=\"btn btn-danger btn-xs\" onclick=\"return confirm(\'Excluir este material?\');\"><i class=\"fa fa-trash\"></i></a>' +
				'</div>');
			}

			function enviarMateriaisAjax() {
				var input = document.getElementById('materialArquivo');
				if(!input || !input.files || !input.files.length) return;
				var formData = new FormData();
				formData.append('acao_material_upload', '1');
				formData.append('treinamento_id', treinamentoIdForm);
				Array.from(input.files).forEach(function(f){ formData.append('material_arquivo[]', f); });
				jQuery.ajax({
					url: window.location.pathname,
					method: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					dataType: 'json',
					success: function(data) {
						if(!data.success) {
							Swal.fire({ icon: 'error', title: 'Erro', text: data.message || 'Não foi possível enviar.' });
							return;
						}
						(data.resultados || []).forEach(function(m) {
							if(m.success) adicionarMaterialLista(m);
							else Swal.fire({ icon: 'warning', title: 'Atenção', text: m.nome + ': ' + m.message });
						});
						input.value = '';
						renderFilaMateriais();
					},
					error: function() {
						Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro ao conectar com o servidor.' });
					}
				});
			}

			jQuery('#btnEnviarMateriais').on('click', function() {
				if(treinamentoIdForm) {
					enviarMateriaisAjax();
				}
			});
			jQuery('#materialArquivo').on('change', function() {
				if(!treinamentoIdForm) renderFilaMateriais();
			});
			// Máscara de duração: preenche hh:mm:ss automaticamente Ã  medida que digita.
			// Os dígitos são lidos do FINAL (o último digitado é o de segundos):
			// 1 dígito â†’ 00:00:0X | 2 â†’ 00:00:XX | 4 â†’ 00:XX:XX | 6 â†’ XX:XX:XX
			function mascaraDuracao(el) {
				var digitos = el.value.replace(/[^\d]/g, '').slice(-6);
				var arr = [];
				for(var i = 0; i < 6 - digitos.length; i++) arr.push('0');
				arr = arr.concat(digitos.split(''));
				var h = arr.slice(0, 2).join('');
				var m = arr.slice(2, 4).join('');
				var s = arr.slice(4, 6).join('');
				el.value = h + ':' + m + ':' + s;
			}
			$('input[name=carga_horaria]').on('input', function() { mascaraDuracao(this); });
			$('input[name=epi_carga_horaria]').on('input', function() { mascaraDuracao(this); });

			// Empresas habilitadas: marcar/desmarcar todas (event delegation)
			$(document).on('click', '.btn-marcar-empresas', function(e) {
				e.preventDefault();
				var marcar = String($(this).attr('data-marcar')) === '1';
				$('.checkbox-empresa').prop('checked', marcar);
				// Atualiza o visual (Uniform do Metronic estiliza checkboxes com span .checker)
				$('.checkbox-empresa').each(function() {
					$(this).closest('.checker').find('span').toggleClass('checked', this.checked);
				});
				filtrarInstrutoresPorEmpresa();
			});

			// Instrutor: alternar funcionário / externo
			function alternarInstrutor() {
				var tipo = $('select[name=instrutor_tipo]').val();
				if(tipo === 'externo') {
					$('#div_instrutor_funcionario').hide();
					$('#div_instrutor_externo').show();
				} else {
					$('#div_instrutor_funcionario').show();
					$('#div_instrutor_externo').hide();
				}
			}
			$('select[name=instrutor_tipo]').on('change', alternarInstrutor);
			alternarInstrutor();

			// Filtra os funcionários instrutores pelas empresas habilitadas marcadas.
			// Reconstrói as <option> (display:none não funciona em option no Chrome/Edge)
			var opcoesInstrutorOriginal = null;
			function filtrarInstrutoresPorEmpresa() {
				var sel = document.getElementById('selectInstrutorFunc');
				if(!sel) return;
				if(opcoesInstrutorOriginal === null) {
					opcoesInstrutorOriginal = Array.from(sel.options).map(function(o) {
						return { value: o.value, text: o.text, empresa: o.getAttribute('data-empresa') };
					});
				}
				var empresasMarcadas = $('.checkbox-empresa:checked').map(function(){ return String($(this).val()); }).get();
				var valorAtual = sel.value;
				sel.innerHTML = '';
				opcoesInstrutorOriginal.forEach(function(o) {
					var visivel = (o.value === '') || (empresasMarcadas.length === 0) || (empresasMarcadas.indexOf(String(o.empresa)) !== -1);
					if(!visivel) return;
					var opt = document.createElement('option');
					opt.value = o.value;
					opt.text = o.text;
					if(o.empresa !== null) opt.setAttribute('data-empresa', o.empresa);
					if(o.value === valorAtual) opt.selected = true;
					sel.appendChild(opt);
				});
				// Atualiza o visual (Uniform do Metronic estiliza selects)
				if(typeof $.uniform !== 'undefined' && $.uniform.update) {
					$.uniform.update('#selectInstrutorFunc');
				}
			}
			$(document).on('change', '.checkbox-empresa', filtrarInstrutoresPorEmpresa);
			filtrarInstrutoresPorEmpresa();

			// Série de vídeos: esconde os campos de vídeo dos Dados Gerais (os vídeos ficam nos episódios)
			function alternarCamposSerie() {
				var ehSerie = $('input[name=serie_videos]').is(':checked');
				$('#div_campos_video').toggle(!ehSerie);
				$('#div_aviso_serie').toggle(ehSerie);
				// No cadastro novo, as abas aparecem conforme a flag de série
				if(!treinamentoIdForm) {
					jQuery('a[href=\"#tab_avaliacao\"]').closest('li').toggle(!ehSerie);
					jQuery('a[href=\"#tab_episodios\"]').closest('li').toggle(ehSerie);
				}
			}
			$('input[name=serie_videos]').on('change', function() {
				var ehSerie = $(this).is(':checked');
				var urlVideo = $('input[name=url_video]').val().trim();
				if(ehSerie && urlVideo !== '') {
					Swal.fire({
						title: 'Atenção!',
						html: 'O link do vídeo informado será <strong>desconsiderado</strong>.<br><br>Os vídeos da série devem ser cadastrados como <strong>episódios</strong> na aba <strong>Episódios</strong> (após salvar o treinamento).',
						icon: 'warning',
						confirmButtonColor: '#3c8dbc',
						confirmButtonText: '<i class=\"fa fa-check\"></i> Entendi'
					}).then(() => {
						// Regra: ao ativar série, o link do vídeo geral é desconsiderado
						$('input[name=url_video]').val('');
						$('#div_preview_video').hide();
						$('#video_preview_container').html('');
						alternarCamposSerie();
					});
				} else {
					alternarCamposSerie();
				}
			});
			alternarCamposSerie();

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
						var total = p.usuarios.length;
						html += '<div class=\"perfil-card\">' +
							'<div class=\"perfil-card-header\">' +
							'<div class=\"perfil-card-titulo\"><i class=\"fa fa-users\"></i> <strong>' + $('<span>').text(p.perfil_tx_nome).html() + '</strong></div>' +
							'<div class=\"perfil-card-contador\"><span class=\"perfil-contador-num\"><strong>0</strong>/' + total + '</span> selecionados</div>' +
							'</div>' +
							'<div class=\"progress\" style=\"height:6px;margin-bottom:10px;\">' +
							'<div class=\"progress-bar progress-bar-success perfil-bar\" role=\"progressbar\" style=\"width:0%\"></div>' +
							'</div>' +
							'<div class=\"row\">';
						p.usuarios.forEach(function(u) {
							var checked = data.bloqueados.indexOf(parseInt(u.user_nb_id)) === -1 ? ' checked' : '';
							html += '<div class=\"col-md-4 col-sm-6\">' +
								'<label style=\"font-weight:normal;cursor:pointer;\">' +
								'<input type=\"checkbox\" class=\"checkbox-perfil\" name=\"usuarios_atribuidos[]\" value=\"' + u.user_nb_id + '\"' + checked + '> ' +
								'<input type=\"hidden\" name=\"usuarios_origem[]\" value=\"' + u.user_nb_id + '\">' +
								$('<span>').text(u.user_tx_nome).html() +
								'</label></div>';
						});
						html += '</div></div>';
					});
					html += '<div class=\"atribuicao-resumo\">' +
						'<div class=\"atribuicao-resumo-item\"><div class=\"atribuicao-resumo-num\" id=\"resumoTotal\">0</div><div class=\"atribuicao-resumo-label\">Total de Funcionários</div></div>' +
						'<div class=\"atribuicao-resumo-item atribuicao-resumo-item-sucesso\"><div class=\"atribuicao-resumo-num\" id=\"resumoSelecionados\">0</div><div class=\"atribuicao-resumo-label\">Selecionados (com acesso)</div></div>' +
						'<div class=\"atribuicao-resumo-item atribuicao-resumo-item-info\"><div class=\"atribuicao-resumo-num\" id=\"resumoPercentual\">0%</div><div class=\"atribuicao-resumo-label\">% com Acesso</div></div>' +
						'</div>';
					container.html(html);
					atualizarContadores();
				}, 'json');
			}

			function atualizarContadores() {
				var totalGeral = 0;
				var selecionadosGeral = 0;
				$('.perfil-card').each(function() {
					var card = $(this);
					var total = card.find('.checkbox-perfil').length;
					var selecionados = card.find('.checkbox-perfil:checked').length;
					card.find('.perfil-contador-num strong').text(selecionados);
					var pct = total > 0 ? Math.round((selecionados / total) * 100) : 0;
					card.find('.perfil-bar').css('width', pct + '%');
					totalGeral += total;
					selecionadosGeral += selecionados;
				});
				$('#resumoTotal').text(totalGeral);
				$('#resumoSelecionados').text(selecionadosGeral);
				$('#resumoPercentual').text(totalGeral > 0 ? Math.round((selecionadosGeral / totalGeral) * 100) + '%' : '0%');
			}

			$(document).on('change', '.checkbox-perfil', atualizarContadores);

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
			"CONVERSAS" => "conversas_pendentes",
		];

		$camposBusca = [
			"busca_titulo_like" => "trei_tx_titulo",
			"busca_tipo" => "trei_tx_tipo",
			"busca_status" => "trei_tx_status",
		];

		// Coluna CONVERSAS: quantidade de mensagens de usuários ainda não visualizadas
		// (ou não respondidas) pelo gestor logado
		$usuarioIdGrid = (int)($_SESSION["user_nb_id"] ?? 0);
		$queryBase = "SELECT trei_nb_id, trei_tx_titulo, trei_tx_tipo, trei_tx_status, trei_nb_carga_horaria, trei_nb_obrigatorio,
			(SELECT COUNT(*) FROM treinamento_mensagem m
				WHERE m.trem_nb_treinamento_id = t.trei_nb_id
				AND m.trem_tx_usuario_nivel NOT LIKE '%Administrador%'
				AND m.trem_nb_id > COALESCE(GREATEST(
					(SELECT MAX(m2.trem_nb_id) FROM treinamento_mensagem m2
						WHERE m2.trem_nb_treinamento_id = t.trei_nb_id
						AND m2.trem_tx_usuario_nivel LIKE '%Administrador%'),
					(SELECT tl.trel_nb_ultimo_id_lido FROM treinamento_mensagem_leitura tl
						WHERE tl.trei_nb_id = t.trei_nb_id AND tl.user_nb_id = {$usuarioIdGrid})
				), 0)
			) AS conversas_pendentes
			FROM treinamento t";

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
			});
			$(document).on('click', '.btn-acompanhar-treinamento', function(event){
				event.preventDefault();
				var row = $(this).closest('tr');
				var id = row.attr('data-row-id');
				if(!id){
					id = row.find('td').first().text().trim();
				}
				if(!id) return;
				window.location.href = 'treinamento_acompanhamento.php?id=' + id;
			});
			$(document).on('click', '.btn-conversa-treinamento', function(event){
				event.preventDefault();
				var row = $(this).closest('tr');
				var id = row.attr('data-row-id');
				if(!id){
					id = row.find('td').first().text().trim();
				}
				if(!id) return;
				window.location.href = 'treinamento_chat_gestao.php?id=' + id;
			});";

		$gridFields["actions"] = [
			"<spam class='btn-acompanhar-treinamento' style='cursor:pointer;color:#27ae60;margin-right:5px;' title='Acompanhamento'><i class='fa fa-users'></i></spam>",
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
				$empresasHabAjax = [];
				if ($treinamentoId > 0) {
					$treinAjax = carregar("treinamento", $treinamentoId);
					if (!empty($treinAjax["trei_tx_empresas_habilitadas"])) {
						$empresasHabAjax = json_decode($treinAjax["trei_tx_empresas_habilitadas"], true);
						if (!is_array($empresasHabAjax)) $empresasHabAjax = [];
					}
				}
				$condEmpresaAjax = empty($empresasHabAjax) ? "1 = 1" : "u.user_nb_empresa IN (" . implode(",", array_map('intval', $empresasHabAjax)) . ")";
				$rs = query(
					"SELECT DISTINCT u.user_nb_id, u.user_tx_nome, up.perfil_nb_id, p.perfil_tx_nome
					 FROM user u
					 JOIN usuario_perfil up ON up.user_nb_id = u.user_nb_id
					 JOIN perfil_acesso p ON p.perfil_nb_id = up.perfil_nb_id
					 WHERE up.ativo = 1 AND u.user_tx_status = 'ativo'
					 AND up.perfil_nb_id IN ({$placeholders})
					 AND ({$condEmpresaAjax})
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

		// AJAX: upload de material de apoio (1 arquivo por requisição, aceita múltiplos no frontend)
		if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["acao_material_upload"])) {
			header('Content-Type: application/json');
			$treinamentoId = (int)($_POST["treinamento_id"] ?? 0);
			if ($treinamentoId <= 0 || empty($_FILES["material_arquivo"]["name"]) || !is_array($_FILES["material_arquivo"]["name"])) {
				echo json_encode(["success" => false, "message" => "Arquivo não informado."]);
				exit;
			}
			$materialDir = __DIR__ . "/uploads/materiais/" . $treinamentoId . "/";
			if (!is_dir($materialDir)) {
				mkdir($materialDir, 0755, true);
			}
			$permitidos = ["pdf", "jpg", "jpeg", "png", "gif", "webp"];
			$resultados = [];
			foreach ($_FILES["material_arquivo"]["name"] as $idx => $nomeOriginal) {
				if (empty($nomeOriginal) || ($_FILES["material_arquivo"]["error"][$idx] ?? 1) !== UPLOAD_ERR_OK) continue;
				$ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
				if (!in_array($ext, $permitidos)) {
					$resultados[] = ["success" => false, "nome" => $nomeOriginal, "message" => "Formato não permitido."];
					continue;
				}
				$tamanho = $_FILES["material_arquivo"]["size"][$idx] ?? 0;
				$nomeSalvo = "mat_" . time() . "_" . rand(1000, 9999) . "." . $ext;
				if (move_uploaded_file($_FILES["material_arquivo"]["tmp_name"][$idx], $materialDir . $nomeSalvo)) {
					$cnt = mysqli_fetch_assoc(query(
						"SELECT COUNT(*) as total FROM treinamento_material WHERE tram_nb_treinamento_id = ?",
						"i", [$treinamentoId]
					));
					$ordem = (int)($cnt["total"] ?? 0) + 1;
					$retIns = inserir("treinamento_material",
						["tram_nb_treinamento_id", "tram_tx_nome", "tram_tx_descricao", "tram_tx_arquivo", "tram_tx_tipo_arquivo", "tram_nb_tamanho", "tram_nb_ordem"],
						[$treinamentoId, $nomeOriginal, "", "materiais/" . $treinamentoId . "/" . $nomeSalvo, $ext, $tamanho, $ordem]
					);
					$novoId = (int)($retIns[0] ?? 0);
					registrarLogTreinamento($treinamentoId, $_SESSION["user_nb_id"], "material_upload", "Material '$nomeOriginal' adicionado");
					$resultados[] = [
						"success" => true,
						"nome" => $nomeOriginal,
						"id" => (int)$novoId,
						"tamanho_kb" => round($tamanho / 1024, 1)
					];
				} else {
					$resultados[] = ["success" => false, "nome" => $nomeOriginal, "message" => "Falha ao salvar o arquivo."];
				}
			}
			echo json_encode(["success" => true, "resultados" => $resultados]);
			exit;
		}

		// Salvamento do formulário (contorna o dispatcher do funcoes.php)
		if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["salvar"])) {
			cadastrar();
			return;
		}
		if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["salvar_questao"])) {
			cadastrarQuestao();
			return;
		}
		if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["salvar_episodio"])) {
			cadastrarEpisodio();
			return;
		}
		if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["salvar_questao_episodio"])) {
			cadastrarQuestaoEpisodio();
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
		if (isset($_GET["acao_excluir_episodio"]) && is_numeric($_GET["acao_excluir_episodio"])) {
			excluirEpisodio((int)$_GET["acao_excluir_episodio"], (int)($_GET["treinamento_id"] ?? 0));
			return;
		}
		if (isset($_GET["acao_excluir_questao"]) && is_numeric($_GET["acao_excluir_questao"])) {
			excluirQuestao((int)$_GET["acao_excluir_questao"], (int)($_GET["treinamento_id"] ?? 0));
			return;
		}
		if (isset($_GET["acao_excluir_questao_episodio"]) && is_numeric($_GET["acao_excluir_questao_episodio"])) {
			excluirQuestaoEpisodio((int)$_GET["acao_excluir_questao_episodio"], (int)($_GET["episodio_id"] ?? 0), (int)($_GET["treinamento_id"] ?? 0));
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
