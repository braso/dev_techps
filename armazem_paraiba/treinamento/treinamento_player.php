<?php
	include_once __DIR__."/../load_env.php";
	include_once __DIR__."/../conecta.php";
	include_once __DIR__."/certificado.php";

	// =====================================================
	// MÓDULO DE TREINAMENTO - Player de Vídeo
	// =====================================================

	$usuarioId = $_SESSION["user_nb_id"] ?? 0;
	$nivelUsuario = $_SESSION["user_tx_nivel"] ?? "";
	$isAdmin = (strpos($nivelUsuario, "Administrador") !== false);
	$treinamentoId = (int)($_GET["id"] ?? $_GET["treinamento_id"] ?? $_POST["treinamento_id"] ?? 0);

	if (!$treinamentoId) {
		header("Location: treinamento_assistir.php");
		exit;
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

	function verificarAcesso($treinamentoId, $usuarioId, $isAdmin) {
		$treinamento = carregar("treinamento", $treinamentoId);
		if (empty($treinamento) || $treinamento["trei_tx_status"] !== "ativo") {
			return false;
		}

		// Verificar data de liberação
		if (!empty($treinamento["trei_dt_data_liberacao"])) {
			if (strtotime($treinamento["trei_dt_data_liberacao"]) > time()) {
				return false;
			}
		}

		if ($isAdmin) {
			return true;
		}

		// Verificar empresa habilitada (vazio = todas)
		$empresasHab = !empty($treinamento["trei_tx_empresas_habilitadas"])
			? json_decode($treinamento["trei_tx_empresas_habilitadas"], true)
			: [];
		if (!empty($empresasHab)) {
			$empresaUsuario = (int)($_SESSION["user_nb_empresa"] ?? 0);
			if (!in_array($empresaUsuario, array_map('intval', $empresasHab))) {
				return false;
			}
		}

		// Verificar se o usuário está bloqueado individualmente (desmarcado na atribuição)
		$bloqueado = mysqli_fetch_assoc(query(
			"SELECT 1 FROM treinamento_bloqueio WHERE trebl_nb_treinamento_id = ? AND trebl_nb_usuario_id = ?",
			"ii",
			[$treinamentoId, $usuarioId]
		));
		if (!empty($bloqueado)) {
			return false;
		}

		// Verificar se há perfis permitidos definidos
		$perfisPermitidos = !empty($treinamento["trei_tx_tipo_usuario_permitido"])
			? json_decode($treinamento["trei_tx_tipo_usuario_permitido"], true)
			: [];

		// Se nenhum perfil foi definido, todos têm acesso
		if (empty($perfisPermitidos)) {
			return true;
		}

		// Verificar se o perfil do usuário está na lista de perfis permitidos
		$perfilUsuario = 0;
		$rsPerfil = query("SELECT perfil_nb_id FROM usuario_perfil WHERE ativo = 1 AND user_nb_id = ? LIMIT 1", "i", [$usuarioId]);
		if ($rsPerfil && ($rowPerfil = mysqli_fetch_assoc($rsPerfil))) {
			$perfilUsuario = (int)$rowPerfil["perfil_nb_id"];
		}

		if ($perfilUsuario > 0 && in_array($perfilUsuario, $perfisPermitidos)) {
			return true;
		}

		// Verificar atribuição individual
		$atribuido = mysqli_fetch_assoc(query(
			"SELECT 1 FROM treinamento_atribuicao WHERE treate_nb_treinamento_id = ? AND treate_nb_usuario_id = ?",
			"ii",
			[$treinamentoId, $usuarioId]
		));

		return !empty($atribuido);
	}

	function obterOuCriarProgresso($treinamentoId, $usuarioId, $episodioId = 0) {
		$episodioId = (int)$episodioId;
		$episodioParam = $episodioId > 0 ? $episodioId : null;

		$progresso = mysqli_fetch_assoc(query(
			"SELECT * FROM treinamento_progresso WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ? AND trepr_nb_episodio_id <=> ?",
			"iii",
			[$treinamentoId, $usuarioId, $episodioParam]
		));

		if (empty($progresso)) {
			if ($episodioParam === null) {
				inserir("treinamento_progresso",
					["trepr_nb_usuario_id", "trepr_nb_treinamento_id", "trepr_dt_data_inicio"],
					[$usuarioId, $treinamentoId, date("Y-m-d H:i:s")]
				);
			} else {
				query(
					"INSERT INTO treinamento_progresso (trepr_nb_usuario_id, trepr_nb_treinamento_id, trepr_nb_episodio_id, trepr_dt_data_inicio) VALUES (?, ?, ?, ?)",
					"iiis",
					[$usuarioId, $treinamentoId, $episodioParam, date("Y-m-d H:i:s")]
				);
			}
			$progresso = mysqli_fetch_assoc(query(
				"SELECT * FROM treinamento_progresso WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ? AND trepr_nb_episodio_id <=> ?",
				"iii",
				[$treinamentoId, $usuarioId, $episodioParam]
			));
		}

		return $progresso;
	}

	function gerarEmbedVideo($url, $tipo) {
		if ($tipo === 'youtube') {
			preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $url, $matches);
			$videoId = $matches[1] ?? '';
			$origin = urlencode($_ENV["URL_BASE"] ?? "");
			return "https://www.youtube.com/embed/{$videoId}?enablejsapi=1&playsinline=1&origin={$origin}";
		} elseif ($tipo === 'vimeo') {
			preg_match('/vimeo\.com\/(\d+)/', $url, $matches);
			$videoId = $matches[1] ?? '';
			return "https://player.vimeo.com/video/{$videoId}?enablejsapi=1&player_id=vimeoPlayer";
		}
		return $url;
	}

	// =====================================================
	// AJAX: ATUALIZAR PROGRESSO
	// =====================================================

	if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao_player"] ?? "") === "atualizarProgresso") {
		header('Content-Type: application/json');

		$treinamentoId = (int)($_POST["treinamento_id"] ?? 0);
		$episodioId = (int)($_POST["episodio_id"] ?? 0);
		$tempoAssistido = (int)($_POST["tempo_assistido"] ?? 0);
		$porcentagem = (float)($_POST["porcentagem"] ?? 0);
		$duracaoRef = (float)($_POST["duracao_referencia"] ?? 0);
		if ($porcentagem > 100) $porcentagem = 100;

		$progresso = obterOuCriarProgresso($treinamentoId, $usuarioId, $episodioId);
		$tempoAnterior = (int)($progresso["trepr_nb_tempo_assistido"] ?? 0);

		// Anti-fraude 1: o tempo só avança 15s por request (sempre - inclusive com 100%).
		// Impede que um salvamento "restaure" o progresso após o reset de tentativas.
		$tempoMaximo = $tempoAnterior + 15;
		if ($tempoAssistido > $tempoMaximo) {
			$tempoAssistido = $tempoMaximo;
		}

		// Anti-fraude 2: a porcentagem não pode exceder o que o tempo assistido justifica.
		// Referência = duração real do vídeo (enviada pelo player), limitada pela carga horária.
		$treinamentoProg = carregar("treinamento", $treinamentoId);
		$cargaRef = (int)($treinamentoProg["trei_nb_carga_horaria"] ?? 0);
		if ($duracaoRef <= 0) $duracaoRef = $cargaRef;
		if ($cargaRef > 0 && $duracaoRef > $cargaRef) $duracaoRef = $cargaRef;
		if ($duracaoRef > 0) {
			$porcentagemMaximaTempo = min(100, ($tempoAssistido / $duracaoRef) * 100) + 3; // tolerância de 3%
			if ($porcentagem > $porcentagemMaximaTempo) {
				$porcentagem = round($porcentagemMaximaTempo, 2);
			}
		}

		// Conclusão automática ao assistir 100% do vídeo
		// (para séries, a conclusão do episódio não marca o treinamento geral - depende da avaliação)
		$concluido = (int)($progresso["trepr_nb_concluido"] ?? 0);
		$dataConclusao = $progresso["trepr_dt_data_conclusao"] ?? null;
		if ($porcentagem >= 100 && !$concluido && $episodioId <= 0) {
			$concluido = 1;
			$dataConclusao = date("Y-m-d H:i:s");
		}

		$whereEpiProg = $episodioId > 0 ? " AND trepr_nb_episodio_id = ?" : " AND trepr_nb_episodio_id IS NULL";
		$valsEpiProg = $episodioId > 0 ? [$episodioId] : [];
		$typesEpiProg = $episodioId > 0 ? "i" : "";

		query(
			"UPDATE treinamento_progresso SET
				trepr_nb_tempo_assistido = ?,
				trepr_nb_porcentagem_assistida = ?,
				trepr_nb_concluido = ?,
				trepr_dt_data_conclusao = ?
			WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ?{$whereEpiProg}",
			"diisii" . $typesEpiProg,
			array_merge([$tempoAssistido, $porcentagem, $concluido, $dataConclusao, $treinamentoId, $usuarioId], $valsEpiProg)
		);

		echo json_encode(["success" => true, "tempo" => $tempoAssistido, "porcentagem" => $porcentagem, "concluido" => $concluido]);
		exit;
	}

	// =====================================================
	// AJAX: SUBMETER AVALIAÇÃO
	// =====================================================

	if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao_player"] ?? "") === "submeterAvaliacao") {
		header('Content-Type: application/json');

		$treinamentoId = (int)($_POST["treinamento_id"] ?? 0);
		$episodioId = (int)($_POST["episodio_id"] ?? 0);
		$respostas = $_POST["respostas"] ?? [];

		$treinamento = carregar("treinamento", $treinamentoId);
		$ehSerie = ($treinamento["trei_tx_serie"] ?? "nao") === "sim";
		$progresso = obterOuCriarProgresso($treinamentoId, $usuarioId, $episodioId);

		// Verificar tentativas (máximo configurável no treinamento)
		// Regra: N > 0 = N tentativas; ao errar todas, precisa reassistir o vídeo.
		// N = 0 (segurança): 10 tentativas, bloqueio de 1 hora, repetindo o ciclo.
		$maxTentativasConfig = (int)($treinamento["trei_nb_max_tentativas"] ?? 2);
		$tentativas = (int)($progresso["trepr_nb_avaliacao_tentativas"] ?? 0);
		$ultimaTentativa = $progresso["trepr_dt_data_ultima_avaliacao"] ?? null;
		$limiteTentativas = ($maxTentativasConfig > 0) ? $maxTentativasConfig : 10;

		if ($tentativas >= $limiteTentativas) {
			if ($maxTentativasConfig <= 0) {
				// Modo segurança: bloqueio de 1h após 10 tentativas
				$bloqueadoAte = !empty($ultimaTentativa) ? strtotime($ultimaTentativa) + 3600 : 0;
				if ($bloqueadoAte > time()) {
					$restante = $bloqueadoAte - time();
					echo json_encode(["success" => false, "message" => "Limite de 10 tentativas atingido. Aguarde " . sprintf("%02d:%02d", floor($restante / 60), $restante % 60) . " para tentar novamente."]);
					exit;
				}
				// Passou 1 hora: libera novo ciclo de tentativas
				$tentativas = 0;
				query(
					"UPDATE treinamento_progresso SET trepr_nb_avaliacao_tentativas = 0 WHERE trepr_nb_id = ?",
					"i", [(int)$progresso["trepr_nb_id"]]
				);
			} else {
				echo json_encode(["success" => false, "message" => "Número máximo de tentativas atingido. Reassista o vídeo para tentar novamente."]);
				exit;
			}
		}

		// Buscar questões (banco do treinamento OU do episódio quando série)
		$notaMinima = (int)($treinamento["trei_nb_nota_minima_aprovacao"] ?? 70);
		$questoes = [];
		if ($ehSerie && $episodioId > 0) {
			$episodioAtualAval = carregar("treinamento_episodio", $episodioId);
			if (!empty($episodioAtualAval) && !empty($episodioAtualAval["trepi_nb_nota_minima_aprovacao"])) {
				$notaMinima = (int)$episodioAtualAval["trepi_nb_nota_minima_aprovacao"];
			}
			$rsQuestoes = query(
				"SELECT * FROM treinamento_episodio_questao WHERE trepq_nb_episodio_id = ? AND trepq_tx_status = 'ativo' ORDER BY RAND()",
				"i",
				[$episodioId]
			);
		} else {
			$qtdQuestoes = (int)($treinamento["trei_nb_quantidade_questoes_prova"] ?? 5);
			$rsQuestoes = query(
				"SELECT * FROM treinamento_questao WHERE treq_nb_treinamento_id = ? AND treq_tx_status = 'ativo' ORDER BY RAND() LIMIT ?",
				"ii",
				[$treinamentoId, $qtdQuestoes]
			);
		}
		if ($rsQuestoes) {
			while ($row = mysqli_fetch_assoc($rsQuestoes)) {
				$questoes[] = $row;
			}
		}

		if (empty($questoes)) {
			echo json_encode(["success" => false, "message" => "Nenhuma questão cadastrada para avaliação."]);
			exit;
		}

		// Calcular nota
		$acertos = 0;
		$respostasDetalhadas = [];
		foreach ($questoes as $idx => $q) {
			$idQuestao = $q["treq_nb_id"] ?? $q["trepq_nb_id"];
			$campoCorreta = $q["treq_nb_resposta_correta"] ?? $q["trepq_nb_resposta_correta"];
			$respostaUsuario = (int)($respostas[$idQuestao] ?? -1);
			$respostaCorreta = (int)$campoCorreta;
			$acertou = ($respostaUsuario === $respostaCorreta);
			if ($acertou) $acertos++;

			$respostasDetalhadas[] = [
				"questao_id" => $idQuestao,
				"resposta_usuario" => $respostaUsuario,
				"resposta_correta" => $respostaCorreta,
				"acertou" => $acertou
			];
		}

		$nota = round(($acertos / count($questoes)) * 100, 2);
		$aprovado = ($nota >= $notaMinima);

		// Atualizar progresso
		$novaTentativa = $tentativas + 1;
		$dataConclusao = $aprovado ? date("Y-m-d H:i:s") : null;

		$whereEpiAval = $episodioId > 0 ? " AND trepr_nb_episodio_id = ?" : " AND trepr_nb_episodio_id IS NULL";
		$valsEpiAval = $episodioId > 0 ? [$episodioId] : [];
		$typesEpiAval = $episodioId > 0 ? "i" : "";

		query(
			"UPDATE treinamento_progresso SET
				trepr_nb_avaliacao_tentativas = ?,
				trepr_tx_avaliacao_respostas_json = ?,
				trepr_nb_avaliacao_nota = ?,
				trepr_nb_avaliacao_aprovada = ?,
				trepr_dt_data_conclusao = ?,
				trepr_dt_data_ultima_avaliacao = ?
			WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ?{$whereEpiAval}",
			"isdissii" . $typesEpiAval,
			array_merge([$novaTentativa, json_encode($respostasDetalhadas), $nota, $aprovado ? 1 : 0, $dataConclusao, date("Y-m-d H:i:s"), $treinamentoId, $usuarioId], $valsEpiAval)
		);

		// Se reprovado e atingiu o limite (modo N > 0), resetar progresso (precisa reassistir)
		if (!$aprovado && $maxTentativasConfig > 0 && $novaTentativa >= $limiteTentativas) {
			$whereEpiReset = $episodioId > 0 ? " AND trepr_nb_episodio_id = ?" : " AND trepr_nb_episodio_id IS NULL";
			$valsEpiReset = $episodioId > 0 ? [$episodioId] : [];
			$typesEpiReset = $episodioId > 0 ? "i" : "";
			query(
				"UPDATE treinamento_progresso SET
					trepr_nb_tempo_assistido = 0,
					trepr_nb_porcentagem_assistida = 0,
					trepr_nb_avaliacao_aprovada = 0,
					trepr_nb_avaliacao_tentativas = 0,
					trepr_nb_concluido = 0
				WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ?{$whereEpiReset}",
				"ii" . $typesEpiReset,
				array_merge([$treinamentoId, $usuarioId], $valsEpiReset)
			);
		}

		// Conclusão do treinamento: não-série conclui ao aprovar; série conclui quando TODOS os episódios aprovados
		$concluidoGeral = 0;
		if ($aprovado) {
			if ($ehSerie) {
				$todosAprovados = true;
				$rsEpiCheck = query("SELECT trepi_nb_id FROM treinamento_episodio WHERE trepi_nb_treinamento_id = ? AND trepi_tx_status = 'ativo'", "i", [$treinamentoId]);
				while ($rsEpiCheck && ($rEpi = mysqli_fetch_assoc($rsEpiCheck))) {
					$progEpi = obterOuCriarProgresso($treinamentoId, $usuarioId, (int)$rEpi["trepi_nb_id"]);
					if (((int)($progEpi["trepr_nb_avaliacao_aprovada"] ?? 0)) !== 1) {
						$todosAprovados = false;
						break;
					}
				}
				if ($todosAprovados) {
					$concluidoGeral = 1;
					$progGeral = obterOuCriarProgresso($treinamentoId, $usuarioId);
					query(
						"UPDATE treinamento_progresso SET trepr_nb_concluido = 1, trepr_dt_data_conclusao = ? WHERE trepr_nb_id = ?",
						"si",
						[date("Y-m-d H:i:s"), $progGeral["trepr_nb_id"]]
					);
				}
			} else {
				$concluidoGeral = 1;
				query(
					"UPDATE treinamento_progresso SET trepr_nb_concluido = 1, trepr_dt_data_conclusao = ? WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ? AND trepr_nb_episodio_id IS NULL",
					"sii",
					[date("Y-m-d H:i:s"), $treinamentoId, $usuarioId]
				);
			}
		}

		registrarLogTreinamento($treinamentoId, $usuarioId, "avaliacao", "Nota: {$nota}% | Aprovado: " . ($aprovado ? "Sim" : "Não") . " | Tentativa: {$novaTentativa}" . ($episodioId > 0 ? " | Episódio: {$episodioId}" : ""));

		// Próximo episódio (série): retornar para o JS oferecer navegação
		$proximoEpiAval = 0;
		if ($ehSerie && $aprovado && $episodioId > 0) {
			$rsEpiProx = query(
				"SELECT trepi_nb_id FROM treinamento_episodio
				 WHERE trepi_nb_treinamento_id = ? AND trepi_tx_status = 'ativo'
				   AND trepi_nb_ordem > (SELECT trepi_nb_ordem FROM treinamento_episodio WHERE trepi_nb_id = ?)
				 ORDER BY trepi_nb_ordem LIMIT 1",
				"ii",
				[$treinamentoId, $episodioId]
			);
			if ($rsEpiProx && ($rEpiProx = mysqli_fetch_assoc($rsEpiProx))) {
				$proximoEpiAval = (int)$rEpiProx["trepi_nb_id"];
			}
		}

		echo json_encode([
			"success" => true,
			"aprovado" => $aprovado,
			"nota" => $nota,
			"nota_minima" => $notaMinima,
			"acertos" => $acertos,
			"total" => count($questoes),
			"tentativa" => $novaTentativa,
			"max_tentativas" => $limiteTentativas,
			"modo_seguranca" => ($maxTentativasConfig <= 0),
			"proximo_episodio" => $proximoEpiAval,
			"respostas" => $respostasDetalhadas,
			"questoes" => array_map(function($q) {
				$campoId = $q["treq_nb_id"] ?? $q["trepq_nb_id"];
				$campoPergunta = $q["treq_tx_pergunta"] ?? $q["trepq_tx_pergunta"];
				$campoOpcoes = $q["treq_tx_opcoes"] ?? $q["trepq_tx_opcoes"];
				$campoCorreta = $q["treq_nb_resposta_correta"] ?? $q["trepq_nb_resposta_correta"];
				return [
					"id" => $campoId,
					"pergunta" => $campoPergunta,
					"opcoes" => json_decode($campoOpcoes, true),
					"resposta_correta" => (int)$campoCorreta
				];
			}, $questoes),
			"concluido" => $concluidoGeral
		]);
		exit;
	}

	// =====================================================
	// AJAX: MENSAGENS DA CONVERSA (chat do treinamento)
	// =====================================================

	if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao_player"] ?? "") === "mensagem_enviar") {
		header('Content-Type: application/json');
		$treinamentoId = (int)($_POST["treinamento_id"] ?? 0);
		$texto = trim((string)($_POST["texto"] ?? ""));
		if ($treinamentoId <= 0 || $texto === "" || !verificarAcesso($treinamentoId, $usuarioId, $isAdmin)) {
			echo json_encode(["success" => false, "message" => "Não foi possível enviar a mensagem."]);
			exit;
		}
		query(
			"INSERT INTO treinamento_mensagem (trem_nb_treinamento_id, trem_nb_usuario_id, trem_tx_usuario_nome, trem_tx_usuario_login, trem_tx_usuario_nivel, trem_tx_tipo, trem_tx_mensagem) VALUES (?, ?, ?, ?, ?, 'texto', ?)",
			"iissss",
			[$treinamentoId, $usuarioId, $_SESSION["user_tx_nome"] ?? "", $_SESSION["user_tx_login"] ?? "", $_SESSION["user_tx_nivel"] ?? "", $texto]
		);
		registrarLogTreinamento($treinamentoId, $usuarioId, "mensagem", "Mensagem enviada na conversa");
		echo json_encode(["success" => true]);
		exit;
	}

	if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao_player"] ?? "") === "mensagem_anexo") {
		header('Content-Type: application/json');
		$treinamentoId = (int)($_POST["treinamento_id"] ?? 0);
		if ($treinamentoId <= 0 || !verificarAcesso($treinamentoId, $usuarioId, $isAdmin) || empty($_FILES["arquivo"]["name"])) {
			echo json_encode(["success" => false, "message" => "Não foi possível enviar o arquivo."]);
			exit;
		}
		$ext = strtolower(pathinfo($_FILES["arquivo"]["name"], PATHINFO_EXTENSION));
		$tiposImagem = ["jpg", "jpeg", "png", "gif", "webp"];
		$tiposAudio = ["mp3", "wav", "ogg", "oga", "m4a", "webm", "weba", "aac"];
		if (in_array($ext, $tiposImagem)) {
			$tipo = "imagem";
		} elseif (in_array($ext, $tiposAudio)) {
			$tipo = "audio";
		} else {
			echo json_encode(["success" => false, "message" => "Formato não permitido. Envie imagem ou áudio."]);
			exit;
		}
		$dir = __DIR__ . "/uploads/conversa/" . $treinamentoId . "/";
		if (!is_dir($dir)) mkdir($dir, 0755, true);
		$nomeSalvo = "conv_" . time() . "_" . rand(1000, 9999) . "." . $ext;
		if (!move_uploaded_file($_FILES["arquivo"]["tmp_name"], $dir . $nomeSalvo)) {
			echo json_encode(["success" => false, "message" => "Erro ao salvar o arquivo."]);
			exit;
		}
		query(
			"INSERT INTO treinamento_mensagem (trem_nb_treinamento_id, trem_nb_usuario_id, trem_tx_usuario_nome, trem_tx_usuario_login, trem_tx_usuario_nivel, trem_tx_tipo, trem_tx_arquivo, trem_tx_mensagem) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
			"iissssss",
			[$treinamentoId, $usuarioId, $_SESSION["user_tx_nome"] ?? "", $_SESSION["user_tx_login"] ?? "", $_SESSION["user_tx_nivel"] ?? "", $tipo, "conversa/" . $treinamentoId . "/" . $nomeSalvo, $_FILES["arquivo"]["name"]]
		);
		registrarLogTreinamento($treinamentoId, $usuarioId, "mensagem_anexo", "Anexo enviado na conversa ({$tipo})");
		echo json_encode(["success" => true]);
		exit;
	}

	if (($_GET["acao_player"] ?? "") === "mensagens_listar") {
		header('Content-Type: application/json');
		$treinamentoId = (int)($_GET["treinamento_id"] ?? 0);
		$aposId = (int)($_GET["apos_id"] ?? 0);
		if ($treinamentoId <= 0 || !verificarAcesso($treinamentoId, $usuarioId, $isAdmin)) {
			echo json_encode(["success" => false, "mensagens" => []]);
			exit;
		}
		$mensagens = [];
		$rs = query(
			"SELECT * FROM treinamento_mensagem WHERE trem_nb_treinamento_id = ? AND trem_nb_id > ? ORDER BY trem_nb_id ASC LIMIT 200",
			"ii",
			[$treinamentoId, $aposId]
		);
		while ($rs && ($row = mysqli_fetch_assoc($rs))) {
			$mensagens[] = $row;
		}
		echo json_encode(["success" => true, "mensagens" => $mensagens]);
		exit;
	}

	// =====================================================
	// VERIFICAR ACESSO
	// =====================================================

	if (!verificarAcesso($treinamentoId, $usuarioId, $isAdmin)) {
		header("Location: treinamento_assistir.php");
		exit;
	}

	// Buscar dados do treinamento
	$treinamento = carregar("treinamento", $treinamentoId);
	$ehSerie = ($treinamento["trei_tx_serie"] ?? "nao") === "sim";
	$episodioId = (int)($_GET["episodio"] ?? $_POST["episodio_id"] ?? 0);
	$episodiosSerie = [];
	$episodioAtual = null;
	$episodioIndex = 0;
	$notaMinimaVigente = (int)($treinamento["trei_nb_nota_minima_aprovacao"] ?? 70);

	if ($ehSerie) {
		$rsEpiLista = query(
			"SELECT * FROM treinamento_episodio WHERE trepi_nb_treinamento_id = ? AND trepi_tx_status = 'ativo' ORDER BY trepi_nb_ordem, trepi_nb_id",
			"i", [$treinamentoId]
		);
		while ($rsEpiLista && ($rEpi = mysqli_fetch_assoc($rsEpiLista))) {
			$episodiosSerie[] = $rEpi;
		}
		if (empty($episodiosSerie)) {
			header("Location: treinamento_assistir.php");
			exit;
		}

		// Escolher o episódio: se não informado, o primeiro não aprovado
		if ($episodioId <= 0) {
			foreach ($episodiosSerie as $idx => $ep) {
				$progEpi = obterOuCriarProgresso($treinamentoId, $usuarioId, (int)$ep["trepi_nb_id"]);
				if (((int)($progEpi["trepr_nb_avaliacao_aprovada"] ?? 0)) !== 1) {
					$episodioAtual = $ep;
					$episodioIndex = $idx;
					break;
				}
			}
			if (empty($episodioAtual)) {
				$episodioAtual = $episodiosSerie[0];
				$episodioIndex = 0;
			}
		} else {
			foreach ($episodiosSerie as $idx => $ep) {
				if ((int)$ep["trepi_nb_id"] === $episodioId) {
					$episodioAtual = $ep;
					$episodioIndex = $idx;
					break;
				}
			}
			if (empty($episodioAtual)) {
				header("Location: treinamento_assistir.php");
				exit;
			}
		}

		// Desbloqueio sequencial: todos os episódios anteriores devem estar aprovados
		for ($i = 0; $i < $episodioIndex; $i++) {
			$progAnt = obterOuCriarProgresso($treinamentoId, $usuarioId, (int)$episodiosSerie[$i]["trepi_nb_id"]);
			if (((int)($progAnt["trepr_nb_avaliacao_aprovada"] ?? 0)) !== 1) {
				$episodioAtual = $episodiosSerie[$i];
				$episodioIndex = $i;
				break;
			}
		}

		$episodioId = (int)$episodioAtual["trepi_nb_id"];
		if (!empty($episodioAtual["trepi_nb_nota_minima_aprovacao"])) {
			$notaMinimaVigente = (int)$episodioAtual["trepi_nb_nota_minima_aprovacao"];
		}
		$progresso = obterOuCriarProgresso($treinamentoId, $usuarioId, $episodioId);
	} else {
		$progresso = obterOuCriarProgresso($treinamentoId, $usuarioId);
	}

	// Buscar materiais
	$materiais = [];
	$rsMateriais = query(
		"SELECT * FROM treinamento_material WHERE tram_nb_treinamento_id = ? AND tram_tx_status = 'ativo' ORDER BY tram_nb_ordem",
		"i",
		[$treinamentoId]
	);
	if ($rsMateriais) {
		while ($row = mysqli_fetch_assoc($rsMateriais)) {
			$materiais[] = $row;
		}
	}

	// Buscar mensagens da conversa
	$mensagensChat = [];
	$rsChat = query(
		"SELECT * FROM treinamento_mensagem WHERE trem_nb_treinamento_id = ? ORDER BY trem_nb_id ASC LIMIT 200",
		"i",
		[$treinamentoId]
	);
	if ($rsChat) {
		while ($row = mysqli_fetch_assoc($rsChat)) {
			$mensagensChat[] = $row;
		}
	}
	$ultimoIdChat = !empty($mensagensChat) ? (int)end($mensagensChat)["trem_nb_id"] : 0;

	// Buscar questões para avaliação (banco do treinamento OU do episódio quando série)
	$questoes = [];
	if ($ehSerie && $episodioId > 0) {
		$rsQuestoes = query(
			"SELECT * FROM treinamento_episodio_questao WHERE trepq_nb_episodio_id = ? AND trepq_tx_status = 'ativo' ORDER BY trepq_nb_ordem, trepq_nb_id",
			"i",
			[$episodioId]
		);
	} else {
		$rsQuestoes = query(
			"SELECT * FROM treinamento_questao WHERE treq_nb_treinamento_id = ? AND treq_tx_status = 'ativo' ORDER BY RAND()",
			"i",
			[$treinamentoId]
		);
	}
	if ($rsQuestoes) {
		while ($row = mysqli_fetch_assoc($rsQuestoes)) {
			$questoes[] = $row;
		}
	}

	// Variáveis para o template
	if ($ehSerie) {
		$titulo = htmlspecialchars($episodioAtual["trepi_tx_titulo"]);
		$descricao = htmlspecialchars($episodioAtual["trepi_tx_descricao"] ?? "");
		$conteudoProgramatico = "";
		$urlVideo = $episodioAtual["trepi_tx_url_video"] ?? "";
		$tipoVideo = $episodioAtual["trepi_tx_tipo_video"] ?? "youtube";
		$cargaHoraria = (int)($episodioAtual["trepi_nb_carga_horaria"] ?? 0);
	} else {
		$titulo = htmlspecialchars($treinamento["trei_tx_titulo"]);
		$descricao = htmlspecialchars($treinamento["trei_tx_descricao"] ?? "");
		$conteudoProgramatico = htmlspecialchars($treinamento["trei_tx_conteudo_programatico"] ?? "");
		$urlVideo = $treinamento["trei_tx_url_video"] ?? "";
		$tipoVideo = $treinamento["trei_tx_tipo_video"] ?? "youtube";
		$cargaHoraria = $treinamento["trei_nb_carga_horaria"] ?? 0;
	}
	$obrigatorio = ($treinamento["trei_nb_obrigatorio"] ?? 0) == 1;
	$porcentagem = round($progresso["trepr_nb_porcentagem_assistida"] ?? 0, 1);
	$tempoAssistido = (int)($progresso["trepr_nb_tempo_assistido"] ?? 0);
	$concluido = ($progresso["trepr_nb_concluido"] ?? 0) == 1;
	$aprovado = ($progresso["trepr_nb_avaliacao_aprovada"] ?? 0) == 1;
	$tentativas = (int)($progresso["trepr_nb_avaliacao_tentativas"] ?? 0);
	$notaAtual = $progresso["trepr_nb_avaliacao_nota"] ?? null;
	// Máximo de tentativas configurado no treinamento (0 = 10 tentativas + bloqueio de 1h)
	$maxTentativasConfig = (int)($treinamento["trei_nb_max_tentativas"] ?? 2);
	$limiteTentativas = ($maxTentativasConfig > 0) ? $maxTentativasConfig : 10;
	$podeAvaliar = ($porcentagem >= 99 && !$aprovado && $tentativas < $limiteTentativas);
	// Modo segurança (0): calcular bloqueio de 1h
	$avaliacaoBloqueadaAte = 0;
	if ($maxTentativasConfig <= 0 && !$aprovado && $tentativas >= $limiteTentativas) {
		$ultimaTent = $progresso["trepr_dt_data_ultima_avaliacao"] ?? null;
		$bloqueadoAte = !empty($ultimaTent) ? strtotime($ultimaTent) + 3600 : 0;
		if ($bloqueadoAte > time()) {
			$avaliacaoBloqueadaAte = $bloqueadoAte;
		}
	}
	$embedUrl = gerarEmbedVideo($urlVideo, $tipoVideo);
	$videoIdYoutube = "";
	if ($tipoVideo === 'youtube') {
		preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $urlVideo, $m);
		$videoIdYoutube = $m[1] ?? "";
	}

	// Navegação de episódios (série)
	$totalEpisodios = count($episodiosSerie);
	$proximoEpisodio = null;
	$episodioLiberado = true; // se há episódio anterior não aprovado, o atual foi forçado para o anterior (bloqueio)
	if ($ehSerie && $episodioIndex + 1 < $totalEpisodios) {
		$proximoEpisodio = $episodiosSerie[$episodioIndex + 1];
	}
	// O episódio atual está "bloqueado" se existe anterior não aprovado (redirecionado pelo desbloqueio)
	if ($ehSerie && $episodioIndex > 0) {
		$progAnterior = obterOuCriarProgresso($treinamentoId, $usuarioId, (int)$episodiosSerie[$episodioIndex - 1]["trepi_nb_id"]);
		if (((int)($progAnterior["trepr_nb_avaliacao_aprovada"] ?? 0)) !== 1) {
			$episodioLiberado = false;
		}
	}

	// Instrutor responsável (visível para o usuário)
	$instrutorLabel = "Não informado";
	if (($treinamento["trei_tx_instrutor_tipo"] ?? "funcionario") === "externo") {
		$instrutorLabel = !empty($treinamento["trei_tx_instrutor_nome"]) ? $treinamento["trei_tx_instrutor_nome"] : "Não informado";
		if (!empty($treinamento["trei_tx_instrutor_capacitacao"])) {
			$instrutorLabel .= " — " . $treinamento["trei_tx_instrutor_capacitacao"];
		}
		if (!empty($treinamento["trei_tx_instrutor_cpf"])) {
			$instrutorLabel .= " (CPF: " . $treinamento["trei_tx_instrutor_cpf"] . ")";
		}
	} elseif (!empty($treinamento["trei_nb_instrutor_entidade_id"])) {
		$entiInstr = carregar("entidade", (int)$treinamento["trei_nb_instrutor_entidade_id"]);
		if (!empty($entiInstr["enti_tx_nome"])) {
			$instrutorLabel = $entiInstr["enti_tx_nome"];
		}
	}

	// Criador do treinamento (quem cadastrou)
	$criadorLabel = "";
	$criadorPartes = [];
	if (!empty($treinamento["trei_tx_criador_nome"])) $criadorPartes[] = $treinamento["trei_tx_criador_nome"];
	if (!empty($treinamento["trei_tx_criador_cargo"])) $criadorPartes[] = $treinamento["trei_tx_criador_cargo"];
	if (!empty($treinamento["trei_tx_criador_setor"])) $criadorPartes[] = $treinamento["trei_tx_criador_setor"];
	if (!empty($criadorPartes)) {
		$criadorLabel = implode(" — ", $criadorPartes);
	} else {
		$criadorLabel = "Não informado";
	}

	// Log de acesso
	registrarLogTreinamento($treinamentoId, $usuarioId, "acesso", "Acesso ao player");

	// Certificado: gera automaticamente quando o treinamento está concluído
	$certificadoRegistro = [];
	$certificadoCaminho = "";
	$certificadoStatus = "";
	try {
		if (treinamento_certificado_estaConcluido($treinamentoId, $usuarioId)) {
			$resultadoCertificado = treinamento_certificado_gerar($treinamentoId, $usuarioId);
			$certificadoRegistro = !empty($resultadoCertificado["registro"])
				? $resultadoCertificado["registro"]
				: treinamento_certificado_buscarRegistro($treinamentoId, $usuarioId);
			$certificadoCaminho = !empty($certificadoRegistro) ? treinamento_certificado_arquivo($certificadoRegistro) : "";
			$certificadoStatus = strval($certificadoRegistro["trece_tx_status"] ?? "");
		}
	} catch (Throwable $e) {
		$certificadoRegistro = [];
		$certificadoCaminho = "";
		$certificadoStatus = "";
	}

	// =====================================================
	// RENDERIZAR PÁGINA
	// =====================================================

	cabecalho("Treinamento: " . $titulo);

	if ($ehSerie) {
		echo "
	<div class='container-fluid' style='margin-bottom:10px;'>
		<div class='info-card'>
			<div class='row'>
				<div class='col-md-12'>
					<strong><i class='fa fa-video-camera'></i> Série: " . htmlspecialchars($treinamento["trei_tx_titulo"]) . "</strong>
					<span class='text-muted'> — Episódio " . ($episodioIndex + 1) . " de {$totalEpisodios}</span>
					<div class='episodios-navegacao' style='margin-top:10px; display:flex; flex-wrap:wrap; gap:6px;'>";
					// Um episódio fica acessível quando todos os anteriores estão aprovados
					// (ou é o episódio atual), permitindo navegar pelos já liberados
					$liberadoAte = true;
					foreach ($episodiosSerie as $idx => $ep) {
						$progEpi = obterOuCriarProgresso($treinamentoId, $usuarioId, (int)$ep["trepi_nb_id"]);
						$aprovEpi = ((int)($progEpi["trepr_nb_avaliacao_aprovada"] ?? 0)) === 1;
						$ativo = ((int)$ep["trepi_nb_id"] === $episodioId);
						$acessivel = ($aprovEpi || $ativo || $liberadoAte);

						if ($ativo) {
							$cls = "episodio-item ativo";
						} elseif ($aprovEpi) {
							$cls = "episodio-item aprovado";
						} elseif ($acessivel) {
							$cls = "episodio-item liberado";
						} else {
							$cls = "episodio-item";
						}

						if ($aprovEpi) {
							$icone = "<i class='fa fa-check'></i>";
						} elseif ($acessivel) {
							$icone = "<i class='fa fa-play'></i>";
						} else {
							$icone = "<i class='fa fa-lock'></i>";
						}

						$link = $acessivel ? "treinamento_player.php?id={$treinamentoId}&episodio={$ep["trepi_nb_id"]}" : "#";
						$tituloItem = htmlspecialchars($ep["trepi_tx_titulo"]);
						if (!$acessivel) {
							$tituloItem .= " (bloqueado - conclua e seja aprovado no episódio anterior)";
						} elseif (!$aprovEpi && !$ativo) {
							$tituloItem .= " (liberado)";
						}
						echo "<a href='{$link}' class='{$cls}' title='{$tituloItem}'>{$icone} #" . ($idx + 1) . " " . htmlspecialchars($ep["trepi_tx_titulo"]) . "</a>";

						// A partir do primeiro episódio não aprovado, os seguintes ficam bloqueados
						if (!$aprovEpi) {
							$liberadoAte = false;
						}
					}
					echo "
					</div>
				</div>
			</div>
		</div>
	</div>
	<style>
		.episodio-item { display:inline-block; padding:6px 12px; border-radius:15px; border:1px solid #ccc; color:#555; background:#fff; font-size:12px; text-decoration:none; }
		.episodio-item:hover { text-decoration:none; background:#f0f0f0; }
		.episodio-item.ativo { background:#3c8dbc; border-color:#3c8dbc; color:#fff; font-weight:bold; }
		.episodio-item.aprovado { background:#d4edda; border-color:#27ae60; color:#155724; }
		.episodio-item.liberado { background:#fff8e1; border-color:#f0ad4e; color:#8a6d3b; }
		.episodio-item.liberado:hover { background:#ffefc2; }
	</style>";
	}

	echo "
	<style>
		.player-container {
			background: #000;
			border-radius: 8px;
			overflow: hidden;
			margin-bottom: 20px;
			user-select: none;
			-webkit-user-select: none;
		}
		.player-container iframe {
			width: 100%;
			height: auto !important;
			aspect-ratio: 16 / 9;
			border: none;
			display: block;
		}
		.video-embed-placeholder {
			width: 100%;
			height: auto;
			aspect-ratio: 16 / 9;
			background: #000;
		}
		.video-embed-placeholder iframe {
			width: 100%;
			height: 100% !important;
			border: none;
			display: block;
		}
		.video-element {
			width: 100%;
			height: auto;
			aspect-ratio: 16 / 9;
			max-height: 70vh;
			background: #000;
			display: block;
		}
		.player-container video::-webkit-media-controls-panel { display: flex !important; }
		.player-container video::-webkit-media-controls-speed-list-button,
		.player-container video::-webkit-media-controls-seek-forward-button,
		.player-container video::-webkit-media-controls-seek-back-button { display: none !important; }
		.progress-bar-custom {
			height: 20px;
			border-radius: 10px;
			margin: 10px 0;
		}
		.info-card {
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 8px;
			padding: 15px;
			margin-bottom: 15px;
		}
		.info-card h4 {
			margin-top: 0;
			color: #333;
			overflow-wrap: anywhere;
			word-break: break-word;
		}
		.material-item {
			display: flex;
			align-items: center;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 8px;
			padding: 10px;
			background: #f9f9f9;
			border-radius: 4px;
			margin-bottom: 8px;
		}
		.material-item > div {
			min-width: 0;
			overflow-wrap: anywhere;
			word-break: break-word;
		}
		.questao-card {
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 8px;
			padding: 20px;
			margin-bottom: 20px;
		}
		.questao-card h4 {
			color: #333;
			margin-bottom: 15px;
			overflow-wrap: anywhere;
			word-break: break-word;
		}
		.questao-card .opcao-label {
			display: block;
			padding: 10px 15px;
			margin: 0 0 8px 0;
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 4px;
			cursor: pointer;
			transition: all 0.2s;
			text-wrap: wrap;
			white-space: normal;
			overflow-wrap: anywhere;
			word-break: break-word;
		}
		.questao-card .opcao-label:hover {
			background: #f0f0f0;
			border-color: #3c8dbc;
		}
		.questao-card .opcao-label input {
			margin-right: 10px;
		}
		.resultado-acerto {
			background: #d4edda;
			border-color: #c3e6cb;
			color: #155724;
		}
		.resultado-erro {
			background: #f8d7da;
			border-color: #f5c6cb;
			color: #721c24;
		}
		.tempo-display {
			font-family: monospace;
			font-size: 18px;
			font-weight: bold;
		}
		.tab-content { padding: 15px 0; }
		.nav-tabs-custom > .nav-tabs > li.active > a { border-top-color: #3c8dbc; }
		.chat-container { max-height: 350px; overflow-y: auto; border: 1px solid #ddd; border-radius: 6px; padding: 12px; background: #fafafa; }
		.chat-msg { margin-bottom: 12px; max-width: 80%; padding: 8px 12px; border-radius: 8px; }
		.chat-msg-outro { background: #e9f1f8; border: 1px solid #c9dcec; }
		.chat-msg-meu { background: #d4edda; border: 1px solid #b7dcc3; margin-left: auto; }
		.chat-msg-cabecalho { font-size: 12px; margin-bottom: 3px; color: #444; overflow-wrap: anywhere; word-break: break-word; }
		.chat-msg-corpo { font-size: 13px; overflow-wrap: anywhere; word-break: break-word; }
		.chat-imagem { max-width: min(220px, 100%); border-radius: 6px; border: 1px solid #ddd; }
		.chat-container audio { max-width: min(280px, 100%) !important; }
		.chat-form { margin-top: 12px; }
		.info-card .alert { overflow-wrap: anywhere; word-break: break-word; }
		.tab-pane p { overflow-wrap: anywhere; word-break: break-word; }
		.episodio-item { max-width: 100%; overflow-wrap: anywhere; word-break: break-word; }

		@media (max-width: 767px) {
			.info-card { padding: 12px; }
			.tab-content { padding: 10px 0; }
			.chat-container { max-height: 300px; }
			.chat-msg { max-width: 92%; }
			.material-item .btn { width: 100%; }
			.tempo-display { font-size: 16px; }
			.info-card .text-right { text-align: left; }
			#formAvaliacao .btn-lg { width: 100%; }
			.questao-card { padding: 14px; }
			.nav-tabs > li { float: none; display: block; }
			.nav-tabs > li > a {
				border: 1px solid #ddd;
				border-radius: 4px !important;
				margin-bottom: 4px;
			}
			.nav-tabs > li.active > a,
			.nav-tabs > li.active > a:hover,
			.nav-tabs > li.active > a:focus { border-bottom-color: #ddd; }
			.nav-tabs { border-bottom: none; }
		}
	</style>

	<div class='container-fluid'>
		<div class='row'>
			<!-- COLUNA PRINCIPAL: Player -->
			<div class='col-md-8'>
				<!-- Player de Vídeo -->
				<div class='player-container' id='playerContainer'>";
				if ($tipoVideo === 'upload' && !empty($urlVideo)) {
					echo "
					<video id='videoElement' class='video-element' controlsList='nodownload nofullscreen noremoteplayback nospeed' preload='metadata'>
						<source src='{$urlVideo}' type='video/mp4'>
						Seu navegador não suporta vídeo HTML5.
					</video>
					<div class='controles-custom' style='display:flex;justify-content:center;gap:10px;padding:10px;background:#111;'>
						<button type='button' class='btn btn-sm btn-primary' id='btnPlay'><i class='fa fa-play'></i> Play</button>
						<button type='button' class='btn btn-sm btn-warning' id='btnPause'><i class='fa fa-pause'></i> Pausa</button>
						<button type='button' class='btn btn-sm btn-default' id='btnMudo'><i class='fa fa-volume-up'></i> Mudo</button>
					</div>";
				} elseif ($tipoVideo === 'youtube') {
					echo "
					<div id='videoPlayer' class='video-embed-placeholder'></div>";
				} else {
					echo "
					<iframe id='videoPlayer' src='{$embedUrl}' allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' allowfullscreen></iframe>";
				}
				echo "
				</div>

				<!-- Barra de Progresso -->
				<div class='info-card'>
					<div class='row'>
						<div class='col-md-8'>
							<strong>Progresso:</strong> <span id='progressoTopo'>{$porcentagem}%</span>
							<div class='progress progress-bar-custom'>
								<div class='progress-bar progress-bar-striped progress-bar-animated' role='progressbar' style='width:{$porcentagem}%' id='progressBar'></div>
							</div>
						</div>
						<div class='col-md-4 text-right'>
							<strong>Tempo:</strong>
							<span class='tempo-display' id='tempoDisplay'>" . sprintf("%02d:%02d", floor($tempoAssistido / 60), $tempoAssistido % 60) . "</span>
						</div>
					</div>
				</div>";

				// Se concluído, mostrar mensagem
				if ($concluido) {
					echo "
				<div class='alert alert-success'>
					<i class='fa fa-check-circle'></i> <strong>Treinamento Concluído!</strong>
					" . ($aprovado ? "Avaliação aprovada com nota: <strong>{$notaAtual}%</strong>" : "") . "
				</div>";
				}

				// Certificado de conclusão
				if ($certificadoStatus === "aguardando_assinatura") {
					echo "
				<div class='alert alert-info'>
					<i class='fa fa-pencil-square-o'></i> <strong>Certificado enviado para assinatura!</strong>
					Verifique seu e-mail e assine o documento. Após a assinatura, o certificado ficará disponível em <strong>Meus Documentos</strong>.
				</div>";
				} elseif ($certificadoCaminho !== "") {
					$certificadoUrl = ($_ENV["URL_BASE"] ?? "") . ($CONTEX["path"] ?? "") . "/" . ltrim($certificadoCaminho, "/");
					echo "
				<div class='alert alert-success'>
					<i class='fa fa-certificate'></i> <strong>Certificado disponível!</strong>
					Seu certificado de conclusão já foi gerado e está salvo em <strong>Meus Documentos</strong>.
					<a href='{$certificadoUrl}' target='_blank' class='btn btn-success btn-sm' style='margin-left:8px;'><i class='fa fa-download'></i> Abrir certificado</a>
				</div>";
				}

				// Se pode avaliar
				if ($podeAvaliar) {
					echo "
				<div class='alert alert-warning'>
					<i class='fa fa-clipboard-check'></i> <strong>Você assistiu mais de 99% do treinamento!</strong>
					É hora de realizar a avaliação. Clique na aba \"Avaliação\" abaixo.
				</div>";
				}

				// Tabs: Descrição, Materiais, Avaliação
				echo "
				<div class='info-card'>
					<ul class='nav nav-tabs'>
						<li class='active'><a href='#tab_descricao' data-toggle='tab'>Descrição</a></li>
						" . (!empty($materiais) ? "<li><a href='#tab_materiais' data-toggle='tab'>Materiais (" . count($materiais) . ")</a></li>" : "") . "
						" . ($podeAvaliar || $tentativas > 0 ? "<li><a href='#tab_avaliacao' data-toggle='tab'>Avaliação</a></li>" : "") . "
						<li><a href='#tab_conversa' data-toggle='tab'><i class='fa fa-comments'></i> Conversa" . (!empty($mensagensChat) ? " (" . count($mensagensChat) . ")" : "") . "</a></li>
					</ul>
					<div class='tab-content'>

						<!-- ABA: DESCRIÇÃO -->
						<div class='tab-pane active' id='tab_descricao'>
							<h4>{$titulo}</h4>
							<p>{$descricao}</p>
							" . (!empty($conteudoProgramatico) ? "<h5>Conteúdo Programático:</h5><p>" . nl2br($conteudoProgramatico) . "</p>" : "") . "
							<div class='row'>
								<div class='col-md-6'><strong>Carga Horária:</strong> " . sprintf("%02dm:%02ds", floor($cargaHoraria / 60), $cargaHoraria % 60) . "</div>
								<div class='col-md-6'><strong>Obrigatório:</strong> " . ($obrigatorio ? "Sim" : "Não") . "</div>
							</div>
							<div class='row' style='margin-top:8px;'>
								<div class='col-md-6'><strong><i class='fa fa-user-tie'></i> Instrutor:</strong> " . htmlspecialchars($instrutorLabel) . "</div>
								<div class='col-md-6'><strong><i class='fa fa-user'></i> Cadastrado por:</strong> " . htmlspecialchars($criadorLabel) . "</div>
							</div>
						</div>";

				// ABA: MATERIAIS
				if (!empty($materiais)) {
					echo "
						<div class='tab-pane' id='tab_materiais'>";
					foreach ($materiais as $m) {
						$tamanhoKB = round(($m["tram_nb_tamanho"] ?? 0) / 1024, 1);
						$caminho = ($_ENV["URL_BASE"] ?? "") . ($CONTEX["path"] ?? "") . "/treinamento/uploads/" . $m["tram_tx_arquivo"];
						echo "
							<div class='material-item'>
								<div>
									<i class='fa fa-file'></i>
									<strong>" . htmlspecialchars($m["tram_tx_nome"]) . "</strong>
									<span class='text-muted'> ({$tamanhoKB} KB)</span>
									" . (!empty($m["tram_tx_descricao"]) ? "<br><small class='text-muted'>" . htmlspecialchars($m["tram_tx_descricao"]) . "</small>" : "") . "
								</div>
								<a href='{$caminho}' target='_blank' class='btn btn-sm btn-default'><i class='fa fa-download'></i> Baixar</a>
							</div>";
					}
					echo "
						</div>";
				}

				// ABA: AVALIAÇÃO
				if ($podeAvaliar || $tentativas > 0 || $aprovado) {
					echo "
						<div class='tab-pane' id='tab_avaliacao'>";

					if ($aprovado) {
						if ($ehSerie) {
							if (!empty($proximoEpisodio)) {
								echo "
							<div class='alert alert-success'>
								<i class='fa fa-check-circle'></i> <strong>Avaliação Aprovada!</strong><br>
								Nota: <strong>{$notaAtual}%</strong> — Episódio " . ($episodioIndex + 1) . " concluído. <br><br>
								<a href='treinamento_player.php?id={$treinamentoId}&episodio={$proximoEpisodio["trepi_nb_id"]}' class='btn btn-success'><i class='fa fa-play'></i> Assistir Próximo Episódio (#" . ($episodioIndex + 2) . ")</a>
							</div>";
							} else {
								echo "
							<div class='alert alert-success'>
								<i class='fa fa-trophy'></i> <strong>Parabéns! Você concluiu todos os episódios da série!</strong><br>
								Nota final do último episódio: <strong>{$notaAtual}%</strong>
							</div>";
							}
						} else {
							echo "
							<div class='alert alert-success'>
								<i class='fa fa-check-circle'></i> <strong>Treinamento Concluído!</strong><br>
								Nota: <strong>{$notaAtual}%</strong>
							</div>";
						}
					} elseif ($tentativas >= $limiteTentativas && !$aprovado && $maxTentativasConfig > 0) {
						echo "
							<div class='alert alert-danger'>
								<i class='fa fa-times-circle'></i> <strong>Número máximo de tentativas atingido ({$limiteTentativas}).</strong><br>
								É necessário reassistir o vídeo para tentar novamente.
							</div>";
					} elseif ($avaliacaoBloqueadaAte > 0 && !$aprovado) {
						$restanteSeg = $avaliacaoBloqueadaAte - time();
						echo "
							<div class='alert alert-danger'>
								<i class='fa fa-clock-o'></i> <strong>Limite de {$limiteTentativas} tentativas atingido.</strong><br>
								Por segurança, aguarde <strong id='tempoBloqueioAvaliacao'>" . sprintf("%02d:%02d", floor($restanteSeg / 60), $restanteSeg % 60) . "</strong> para tentar novamente.
							</div>
							<script>
								(function(){
									var restante = " . (int)$restanteSeg . ";
									var el = document.getElementById('tempoBloqueioAvaliacao');
									if(!el) return;
									var t = setInterval(function(){
										restante--;
										if(restante <= 0){ clearInterval(t); location.reload(); return; }
										var m = Math.floor(restante / 60), s = restante % 60;
										el.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
									}, 1000);
								})();
							</script>";
					} elseif (!$podeAvaliar && $tentativas > 0) {
						echo "
							<div class='alert alert-warning'>
								<i class='fa fa-exclamation-triangle'></i> Você precisa assistir pelo menos 99% do vídeo para realizar a avaliação.
							</div>";
					} else {
						$avisoTentativas = ($maxTentativasConfig > 0)
							? "Tentativa " . ($tentativas + 1) . " de {$limiteTentativas}. Em caso de reprovação em todas as tentativas, o progresso será resetado e você precisará reassistir o vídeo."
							: "Tentativa " . ($tentativas + 1) . " de {$limiteTentativas}. Se errar todas, haverá bloqueio de 1 hora (segurança).";
						echo "
							<div class='alert alert-info'>
								<i class='fa fa-info-circle'></i> <strong>Avaliação:</strong> Responda as questões abaixo. Nota mínima para aprovação: <strong>{$notaMinimaVigente}%</strong>.
								<br><small>{$avisoTentativas}</small>
							</div>
							<form id='formAvaliacao'>";

						if (!empty($questoes)) {
							$idx = 1;
							foreach ($questoes as $q) {
								$qId = $q["treq_nb_id"] ?? $q["trepq_nb_id"];
								$qPergunta = $q["treq_tx_pergunta"] ?? $q["trepq_tx_pergunta"];
								$qOpcoesJson = $q["treq_tx_opcoes"] ?? $q["trepq_tx_opcoes"];
								$opcoes = json_decode($qOpcoesJson, true);
								echo "
								<div class='questao-card'>
									<h4>Questão {$idx}: " . htmlspecialchars($qPergunta) . "</h4>";
								if ($opcoes) {
									$opIdx = 0;
									foreach ($opcoes as $op) {
										if (!empty(trim($op))) {
											echo "
										<label class='opcao-label'>
											<input type='radio' name='resposta[{$qId}]' value='{$opIdx}'> " . htmlspecialchars($op) . "
										</label>";
										}
										$opIdx++;
									}
								}
								echo "
								</div>";
								$idx++;
							}
						}

						echo "
								<button type='button' class='btn btn-primary btn-lg' onclick='submeterAvaliacao()'>
									<i class='fa fa-paper-plane'></i> Enviar Respostas
								</button>
							</form>";
					}

					echo "
						</div>";
				}

				// ABA: CONVERSA
				echo "
						<div class='tab-pane' id='tab_conversa'>
							<div class='row'>
								<div class='col-md-12'>
									<p class='text-muted'><i class='fa fa-comments'></i> Interaja com outros participantes deste treinamento. Todas as mensagens ficam registradas para auditoria.</p>
									<div id='chatContainer' class='chat-container'>";
									if (empty($mensagensChat)) {
										echo "<p class='text-muted text-center'><i class='fa fa-comment-o'></i> Nenhuma mensagem ainda. Seja o primeiro a interagir!</p>";
									} else {
										$chatBase = ($_ENV["URL_BASE"] ?? "") . ($CONTEX["path"] ?? "") . "/treinamento/uploads/";
										foreach ($mensagensChat as $msg) {
											$ehMeu = ((int)($msg["trem_nb_usuario_id"] ?? 0) === (int)$usuarioId);
											$tipo = $msg["trem_tx_tipo"] ?? "texto";
											$corpo = "";
											if ($tipo === "texto") {
												$corpo = nl2br(htmlspecialchars($msg["trem_tx_mensagem"] ?? ""));
											} elseif ($tipo === "imagem") {
												$corpo = "<a href='" . $chatBase . $msg["trem_tx_arquivo"] . "' target='_blank'><img src='" . $chatBase . $msg["trem_tx_arquivo"] . "' class='chat-imagem' alt='imagem'></a>";
											} elseif ($tipo === "audio") {
												$corpo = "<audio controls preload='none' style='max-width:280px;'><source src='" . $chatBase . $msg["trem_tx_arquivo"] . "'></audio>";
											}
											$nomeAutor = htmlspecialchars($msg["trem_tx_usuario_nome"] ?? "Usuário");
											$nivelAutor = $msg["trem_tx_usuario_nivel"] ?? "";
											$ehAutorAdmin = (strpos($nivelAutor, "Administrador") !== false);
											$badgeAutor = $ehAutorAdmin
												? "<span class='label label-primary' style='font-size:10px;margin-left:4px;'>Gestor</span>"
												: (!empty($nivelAutor) ? "<span class='label label-default' style='font-size:10px;margin-left:4px;'>" . htmlspecialchars($nivelAutor) . "</span>" : "");
											$dataMsg = date("d/m/Y H:i", strtotime($msg["trem_dt_data_cadastro"] ?? "now"));
											echo "
									<div class='chat-msg " . ($ehMeu ? "chat-msg-meu" : "chat-msg-outro") . "'>
										<div class='chat-msg-cabecalho'>
											<i class='fa fa-user-circle'></i> <strong>{$nomeAutor}</strong>{$badgeAutor}
											<span class='text-muted' style='font-size:11px;'> - {$dataMsg}</span>
										</div>
										<div class='chat-msg-corpo'>{$corpo}</div>
									</div>";
										}
									}
									echo "
									</div>
									<div class='chat-form'>
										<div class='input-group'>
											<input type='text' id='chatTexto' class='form-control' placeholder='Escreva sua mensagem...' maxlength='1000'>
											<span class='input-group-btn'>
												<button type='button' class='btn btn-primary' id='chatEnviar'><i class='fa fa-paper-plane'></i></button>
											</span>
										</div>
										<div style='margin-top:8px;'>
											<input type='file' id='chatArquivo' accept='image/*,audio/*' style='display:none;'>
											<button type='button' class='btn btn-sm btn-default' id='chatAnexar'><i class='fa fa-paperclip'></i> Anexar imagem/áudio</button>
											<button type='button' class='btn btn-sm btn-info' id='chatGravar'><i class='fa fa-microphone'></i> Gravar áudio</button>
											<button type='button' class='btn btn-sm btn-danger' id='chatParar' style='display:none;'><i class='fa fa-stop'></i> Parar e enviar</button>
											<small class='text-muted' id='chatGravando' style='display:none;margin-left:8px;'><i class='fa fa-circle text-danger'></i> Gravando...</small>
										</div>
									</div>
								</div>
							</div>
						</div>";

				echo "
					</div>
				</div>
			</div>

			<!-- COLUNA LATERAL: Info -->
			<div class='col-md-4'>
				<div class='info-card'>
					<h4><i class='fa fa-info-circle'></i> Informações</h4>
					<div class='row'>
						<div class='col-xs-6'><strong>Status:</strong></div>
						<div class='col-xs-6'>" . ($concluido ? "<span class='label label-success'>Concluído</span>" : ($porcentagem > 0 ? "<span class='label label-warning'>Em Andamento</span>" : "<span class='label label-info'>Não Iniciado</span>")) . "</div>
					</div>
					<div class='row'>
						<div class='col-xs-6'><strong>Progresso:</strong></div>
						<div class='col-xs-6'><span id='progressoLateral'>{$porcentagem}%</span></div>
					</div>
					<div class='row'>
						<div class='col-xs-6'><strong>Tempo Assistido:</strong></div>
						<div class='col-xs-6'><span id='tempoLateral'>" . sprintf("%02d:%02d", floor($tempoAssistido / 60), $tempoAssistido % 60) . "</span></div>
					</div>
					<div class='row'>
						<div class='col-xs-6'><strong>Carga Horária:</strong></div>
						<div class='col-xs-6'>" . sprintf("%02dm:%02ds", floor($cargaHoraria / 60), $cargaHoraria % 60) . "</div>
					</div>
					" . ($tentativas > 0 ? "
					<div class='row'>
						<div class='col-xs-6'><strong>Tentativas:</strong></div>
						<div class='col-xs-6'>{$tentativas}/2</div>
					</div>" : "") . "
					" . (!empty($notaAtual) ? "
					<div class='row'>
						<div class='col-xs-6'><strong>Nota Atual:</strong></div>
						<div class='col-xs-6'><strong>{$notaAtual}%</strong></div>
					</div>" : "") . "
				</div>

				<a href='treinamento_assistir.php' class='btn btn-default btn-block'>
					<i class='fa fa-arrow-left'></i> Voltar
				</a>
			</div>
		</div>
	</div>

	<!-- Modal de Resultado -->
	<div class='modal fade' id='modalResultado' tabindex='-1'>
		<div class='modal-dialog'>
			<div class='modal-content'>
				<div class='modal-header' id='modalHeader'>
					<h4 class='modal-title' id='modalTitle'></h4>
				</div>
				<div class='modal-body' id='modalBody'></div>
				<div class='modal-footer'>
					<button type='button' class='btn btn-default' data-dismiss='modal'>Fechar</button>
					" . ($concluido ? "<a href='treinamento_assistir.php' class='btn btn-success'>Ver Meus Treinamentos</a>" : "") . "
				</div>
			</div>
		</div>
	</div>

	<script>
		// =====================================================
		// TUDO dentro de um IIFE: impede acesso via console (F12)
		// às variáveis de controle (ultimoTempo, timers, etc.)
		// =====================================================
		(function() {
		// =====================================================
		// CONFIGURAÇÃO
		// =====================================================
		var treinamentoId = {$treinamentoId};
		var episodioId = {$episodioId};
		var tipoVideo = '{$tipoVideo}';
		var cargaHoraria = {$cargaHoraria};
		var referenceDuration = Math.max(1, cargaHoraria);
		var ultimoTempo = {$tempoAssistido};
		var porcentagemAtual = {$porcentagem};
		var concluido = " . ($concluido ? "true" : "false") . ";
		var hasReallyStartedPlayback = false;
		var ultimoEnvio = 0;
		var AVANCO_MAXIMO = 1.5;
		var youtubePlayer = null;
		var vimeoPlayer = null;
		var youtubeTrackingTimer = null;
		var vimeoTrackingTimer = null;
		var youtubeLastTempo = ultimoTempo;
		var vimeoLastTempo = ultimoTempo;
		var youtubeBlockSeeking = false;
		var vimeoBlockSeeking = false;
		var blockSeeking = false;
		var avaliacaoResetada = false;

		function formatarTempo(segundos) {
			var total = Math.floor(segundos);
			var h = Math.floor(total / 3600);
			var m = Math.floor((total % 3600) / 60);
			var s = total % 60;
			return (h > 0 ? h + ':' : '') + (m > 0 ? String(m).padStart(2,'0') + ':' : '00:') + String(s).padStart(2,'0');
		}

		function obterProgressoAtual() {
			return Math.min(ultimoTempo, referenceDuration);
		}

		function salvarProgresso(percent) {
			if(avaliacaoResetada) return;
			$.post(window.location.pathname, {
				acao_player: 'atualizarProgresso',
				treinamento_id: treinamentoId,
				episodio_id: episodioId,
				tempo_assistido: obterProgressoAtual(),
				porcentagem: Math.floor(percent),
				duracao_referencia: Math.round(referenceDuration)
			}, function(data) {
				if(data.success) {
					ultimoTempo = Math.max(ultimoTempo, data.tempo);
				}
			}, 'json');
		}

		// Salvar progresso quando o usuário sair da página (fechar aba, navegar, etc.)
		function salvarProgressoFinal() {
			if(avaliacaoResetada) return;
			var segundos = obterProgressoAtual();
			if(segundos <= 0 && !hasReallyStartedPlayback) return;
			var percent = Math.min(100, Math.floor((segundos / referenceDuration) * 100));
			var dados = new URLSearchParams();
			dados.append('acao_player', 'atualizarProgresso');
			dados.append('treinamento_id', treinamentoId);
			dados.append('episodio_id', episodioId);
			dados.append('tempo_assistido', segundos);
			dados.append('porcentagem', percent);
			dados.append('duracao_referencia', Math.round(referenceDuration));
			try {
				if(navigator.sendBeacon) {
					navigator.sendBeacon(window.location.pathname, dados);
				} else {
					fetch(window.location.pathname, { method: 'POST', body: dados, keepalive: true });
				}
			} catch(e) {}
		}

		window.addEventListener('pagehide', salvarProgressoFinal);
		window.addEventListener('beforeunload', salvarProgressoFinal);

		function atualizarDisplay(percent) {
			porcentagemAtual = Math.min(100, percent);
			var segundos = Math.min(ultimoTempo, referenceDuration);
			$('#tempoDisplay').text(formatarTempo(segundos));
			$('#tempoLateral').text(formatarTempo(segundos));
			$('#progressoTopo').text(porcentagemAtual.toFixed(1) + '%');
			$('#progressoLateral').text(porcentagemAtual.toFixed(1) + '%');
			$('#progressBar').css('width', porcentagemAtual.toFixed(1) + '%');
			if(porcentagemAtual >= 99 && !concluido && $('#tab_avaliacao').length === 0) {
				window.location.reload();
			}
		}

		// =====================================================
		// VÍDEO UPLOAD (HTML5) - BLOQUEIO DE ADIANTAMENTO E VELOCIDADE
		// =====================================================
		if(tipoVideo === 'upload') {
			var video = document.getElementById('videoElement');
			if(video) {
				video.controls = false;

				video.addEventListener('loadedmetadata', function() {
					var d = Math.floor(video.duration || 0);
					if(d > 0) {
						referenceDuration = cargaHoraria > 0 ? Math.max(1, Math.min(d, referenceDuration)) : d;
					}
					video.currentTime = Math.min(ultimoTempo, referenceDuration);
				});

				// Bloquear teclado que avança o vídeo
				document.addEventListener('keydown', function(e) {
					if(['ArrowRight', 'ArrowLeft', ' ', 'j', 'l', 'k'].indexOf(e.key) !== -1) {
						e.preventDefault();
						e.stopPropagation();
					}
				}, true);

				video.addEventListener('wheel', function(e) { e.preventDefault(); });
				video.addEventListener('contextmenu', function(e) { e.preventDefault(); });

				// BLOQUEIO PRINCIPAL DE ADIANTAMENTO (seeking)
				video.addEventListener('seeking', function() {
					var t = video.currentTime;
					if(t > ultimoTempo + 0.01) {
						blockSeeking = true;
						video.currentTime = ultimoTempo;
						setTimeout(function() { blockSeeking = false; }, 500);
					}
				});

				video.addEventListener('timeupdate', function() {
					var t = video.currentTime;
					if(blockSeeking) return;
					if(t > ultimoTempo + 0.01) {
						video.currentTime = ultimoTempo;
						return;
					}
					// Progresso baseado na posição MÁXIMA do vídeo (não no tempo decorrido)
					ultimoTempo = Math.max(ultimoTempo, t);
					var percent = Math.min(100, (ultimoTempo / referenceDuration) * 100);
					atualizarDisplay(percent);
					var agora = Date.now();
					if(agora - ultimoEnvio > 5000) { salvarProgresso(percent); ultimoEnvio = agora; }
				});

				video.addEventListener('play', function() {
					hasReallyStartedPlayback = true;
				});

				video.addEventListener('pause', function() {
					if(hasReallyStartedPlayback) {
						ultimoTempo = Math.max(ultimoTempo, video.currentTime);
						salvarProgresso(Math.min(100, (ultimoTempo / referenceDuration) * 100));
					}
				});

				video.addEventListener('ended', function() {
					if(!hasReallyStartedPlayback) return;
					ultimoTempo = referenceDuration;
					salvarProgresso(100);
					atualizarDisplay(100);
				});

				// BLOQUEIO DE VELOCIDADE - não pode ser contornado nem via console
				try {
					Object.defineProperty(video, 'playbackRate', {
						get: function() { return 1.0; },
						set: function(value) { return 1.0; },
						configurable: false
					});
					Object.defineProperty(video, 'defaultPlaybackRate', {
						get: function() { return 1.0; },
						set: function(value) { return 1.0; },
						configurable: false
					});
				} catch(e) {}
				video.addEventListener('ratechange', function() {
					try { video.playbackRate = 1.0; video.defaultPlaybackRate = 1.0; } catch(e) {}
				});
				var observer = new MutationObserver(function() {
					try { video.playbackRate = 1.0; video.defaultPlaybackRate = 1.0; } catch(e) {}
				});
				observer.observe(video, { attributes: true, attributeFilter: ['playbackRate', 'defaultPlaybackRate'] });

				// Re-assert periódico: mesmo que o usuário manipule o elemento via F12
				// (document.getElementById), a posição e a velocidade são corrigidas
				setInterval(function() {
					try {
						if(video.currentTime > ultimoTempo + 0.6) {
							video.currentTime = ultimoTempo;
						}
						if(video.playbackRate !== 1.0) { video.playbackRate = 1.0; }
						if(video.defaultPlaybackRate !== 1.0) { video.defaultPlaybackRate = 1.0; }
					} catch(e) {}
				}, 400);

				$('#btnPlay').on('click', function() { video.play(); });
				$('#btnPause').on('click', function() { video.pause(); });
				$('#btnMudo').on('click', function() {
					video.muted = !video.muted;
					$('#btnMudo i').toggleClass('fa-volume-up fa-volume-off');
				});
			}
		}

		// =====================================================
		// YOUTUBE - BLOQUEIO DE ADIANTAMENTO E VELOCIDADE
		// =====================================================
		if(tipoVideo === 'youtube') {
			function fallbackYouTubeEmbed() {
				if(youtubePlayer) return;
				var container = document.getElementById('videoPlayer');
				if(container) {
					container.innerHTML = '<iframe src=\"{$embedUrl}\" allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture\" allowfullscreen style=\"width:100%;height:100%;border:none;\"></iframe>';
				}
			}

			function carregarYouTubeApi() {
				if(window.YT && window.YT.Player) { inicializarYouTube(); return; }
				if(window.__ytLoading) return;
				window.__ytLoading = true;
				window.onYouTubeIframeAPIReady = function() { inicializarYouTube(); };
				var s = document.createElement('script');
				s.src = 'https://www.youtube.com/iframe_api';
				s.onerror = function() { fallbackYouTubeEmbed(); };
				document.head.appendChild(s);
				window.__ytTimeout = setTimeout(function() {
					if(!youtubePlayer) fallbackYouTubeEmbed();
				}, 8000);
			}

			function atualizarDuracaoYouTube() {
				if(!youtubePlayer || typeof youtubePlayer.getDuration !== 'function') return false;
				var d = Math.floor(youtubePlayer.getDuration() || 0);
				if(d > 0) {
					referenceDuration = cargaHoraria > 0 ? Math.max(1, Math.min(d, referenceDuration)) : d;
					return true;
				}
				return false;
			}

			function inicializarYouTube() {
				if(window.__ytTimeout) { clearTimeout(window.__ytTimeout); window.__ytTimeout = null; }
				var container = document.getElementById('videoPlayer');
				if(container) { container.innerHTML = ''; }
				youtubePlayer = new YT.Player('videoPlayer', {
					width: '100%',
					height: '100%',
					videoId: '{$videoIdYoutube}',
					playerVars: {
						enablejsapi: 1,
						playsinline: 1,
						rel: 0
					},
					events: {
						onReady: function() {
							atualizarDuracaoYouTube();
							var start = Math.min(ultimoTempo, referenceDuration);
							youtubePlayer.seekTo(start, true);
							youtubeLastTempo = start;
						},
						onStateChange: function(e) {
							if(e.data === YT.PlayerState.PLAYING) {
								hasReallyStartedPlayback = true;
								atualizarDuracaoYouTube();
								youtubeBlockSeeking = false;
								if(!youtubeTrackingTimer) {
									youtubeTrackingTimer = setInterval(function() {
										if(!youtubePlayer || typeof youtubePlayer.getCurrentTime !== 'function') return;
										var t = youtubePlayer.getCurrentTime();
										var delta = t - youtubeLastTempo;
										// Detectou pulo > tolerância: bloqueia e reverte
										if(delta > AVANCO_MAXIMO && !youtubeBlockSeeking) {
											youtubeBlockSeeking = true;
											var maxPermitido = youtubeLastTempo + AVANCO_MAXIMO;
											youtubePlayer.seekTo(maxPermitido, true);
											youtubeLastTempo = maxPermitido;
											setTimeout(function() { youtubeBlockSeeking = false; }, 1000);
											return;
										}
										// Progresso baseado na posição MÁXIMA do vídeo
										ultimoTempo = Math.max(ultimoTempo, t);
										youtubeLastTempo = t;
										var percent = Math.min(100, (ultimoTempo / referenceDuration) * 100);
										atualizarDisplay(percent);
										var agora = Date.now();
										if(agora - ultimoEnvio > 5000) { salvarProgresso(percent); ultimoEnvio = agora; }
									}, 500);
								}
							}
							if(e.data === YT.PlayerState.PAUSED || e.data === YT.PlayerState.ENDED) {
								if(hasReallyStartedPlayback && youtubePlayer) {
									var t = Math.max(0, Math.floor(youtubePlayer.getCurrentTime() || 0));
									ultimoTempo = Math.max(ultimoTempo, t);
									youtubeLastTempo = t;
									salvarProgresso(Math.min(100, (ultimoTempo / referenceDuration) * 100));
								}
								if(e.data === YT.PlayerState.ENDED) {
									if(!hasReallyStartedPlayback) return;
									ultimoTempo = referenceDuration;
									salvarProgresso(100);
									atualizarDisplay(100);
								}
								if(youtubeTrackingTimer) { clearInterval(youtubeTrackingTimer); youtubeTrackingTimer = null; }
							}
						}
					}
				});
			}

			// Bloqueio de velocidade do YouTube (verificação periódica agressiva)
			setInterval(function() {
				try {
					if(youtubePlayer && typeof youtubePlayer.getPlaybackRate === 'function') {
						if(youtubePlayer.getPlaybackRate() !== 1.0) {
							youtubePlayer.setPlaybackRate(1.0);
						}
					}
				} catch(e) {}
			}, 250);

			carregarYouTubeApi();
		}

		// =====================================================
		// VIMEO - BLOQUEIO DE ADIANTAMENTO E VELOCIDADE
		// =====================================================
		if(tipoVideo === 'vimeo') {
			function carregarVimeoApi() {
				if(window.Vimeo && window.Vimeo.Player) { inicializarVimeo(); return; }
				var s = document.createElement('script');
				s.src = 'https://player.vimeo.com/api/player.js';
				s.onload = function() { inicializarVimeo(); };
				document.head.appendChild(s);
			}

			function inicializarVimeo() {
				vimeoPlayer = new Vimeo.Player('videoPlayer');
				vimeoPlayer.getDuration().then(function(d) {
					d = Math.floor(d || 0);
					if(d > 0) {
						referenceDuration = cargaHoraria > 0 ? Math.max(1, Math.min(d, referenceDuration)) : d;
					}
					var start = Math.min(ultimoTempo, referenceDuration);
					vimeoPlayer.setCurrentTime(start).catch(function() {});
					vimeoLastTempo = start;
				}).catch(function() {});

				vimeoPlayer.on('play', function() {
					hasReallyStartedPlayback = true;
					if(!vimeoTrackingTimer) {
						vimeoTrackingTimer = setInterval(function() {
							vimeoPlayer.getCurrentTime().then(function(t) {
								var delta = t - vimeoLastTempo;
								if(delta > AVANCO_MAXIMO && !vimeoBlockSeeking) {
									vimeoBlockSeeking = true;
									var maxPermitido = vimeoLastTempo + AVANCO_MAXIMO;
									vimeoPlayer.setCurrentTime(maxPermitido).catch(function() {});
									vimeoLastTempo = maxPermitido;
									setTimeout(function() { vimeoBlockSeeking = false; }, 1000);
									return;
								}
								// Progresso baseado na posição MÁXIMA do vídeo
								ultimoTempo = Math.max(ultimoTempo, t);
								vimeoLastTempo = t;
								var percent = Math.min(100, (ultimoTempo / referenceDuration) * 100);
								atualizarDisplay(percent);
								var agora = Date.now();
								if(agora - ultimoEnvio > 5000) { salvarProgresso(percent); ultimoEnvio = agora; }
							}).catch(function() {});
						}, 500);
					}
				});

				vimeoPlayer.on('pause', function() {
					if(hasReallyStartedPlayback) {
						vimeoPlayer.getCurrentTime().then(function(t) {
							ultimoTempo = Math.max(ultimoTempo, t);
							vimeoLastTempo = t;
							salvarProgresso(Math.min(100, (ultimoTempo / referenceDuration) * 100));
						}).catch(function() {});
					}
					if(vimeoTrackingTimer) { clearInterval(vimeoTrackingTimer); vimeoTrackingTimer = null; }
				});

				vimeoPlayer.on('ended', function() {
					if(!hasReallyStartedPlayback) return;
					ultimoTempo = referenceDuration;
					salvarProgresso(100);
					atualizarDisplay(100);
				});

				// Bloqueio de velocidade do Vimeo
				setInterval(function() {
					try {
						if(vimeoPlayer && typeof vimeoPlayer.getPlaybackRate === 'function') {
							vimeoPlayer.getPlaybackRate().then(function(rate) {
								if(rate !== 1.0) {
									vimeoPlayer.setPlaybackRate(1.0).catch(function() {});
								}
							}).catch(function() {});
						}
					} catch(e) {}
				}, 250);
			}

			carregarVimeoApi();
		}

		// =====================================================
		// AVALIAÇÃO
		// =====================================================

		function submeterAvaliacao() {
			// Verificar se todas as questões foram respondidas
			var totalQuestoes = " . count($questoes) . ";
			var respondidas = 0;
			for(var i = 0; i < totalQuestoes; i++) {
				if($('input[name=\"respostas[]\"]:checked').length > 0 || $('input[type=\"radio\"]:checked').length > 0) {
					respondidas++;
				}
			}

			// Contar questões respondidas de forma mais precisa
			var form = $('#formAvaliacao');
			var todasRespondidas = true;
			form.find('input[type=\"radio\"]').each(function() {
				var name = $(this).attr('name');
				if(form.find('input[name=\"' + name + '\"]:checked').length === 0) {
					todasRespondidas = false;
				}
			});

			if(!todasRespondidas) {
				Swal.fire({
					icon: 'warning',
					title: 'Atenção',
					text: 'Responda todas as questões antes de enviar!'
				});
				return;
			}

			// Coletar respostas
			var respostas = {};
			form.find('input[type=\"radio\"]:checked').each(function() {
				var name = $(this).attr('name');
				var questaoId = name.replace('resposta[', '').replace(']', '');
				respostas[questaoId] = $(this).val();
			});

			Swal.fire({
				title: 'Confirmar envio?',
				text: " . ($maxTentativasConfig <= 0
					? "'Tentativa ' + (" . ($tentativas + 1) . ") + ' de {$limiteTentativas}. Se errar todas, haverá bloqueio de 1 hora (segurança).'"
					: "'Tentativa ' + (" . ($tentativas + 1) . ") + ' de {$limiteTentativas}. Se errar todas, será necessário reassistir o vídeo.'") . ",
				icon: 'question',
				showCancelButton: true,
				confirmButtonColor: '#3085d6',
				cancelButtonColor: '#d33',
				confirmButtonText: 'Sim, enviar!',
				cancelButtonText: 'Cancelar'
			}).then((result) => {
				if (result.isConfirmed) {
					$.post(window.location.pathname, {
						acao_player: 'submeterAvaliacao',
						treinamento_id: treinamentoId,
						episodio_id: episodioId,
						respostas: respostas
					}, function(data) {
						if(data.success) {
							// Reprovou na última tentativa: o backend resetou o progresso.
							// Bloqueia novos salvamentos para não restaurar a porcentagem antes do reload.
							if(!data.aprovado && !data.modo_seguranca && data.tentativa >= data.max_tentativas) {
								avaliacaoResetada = true;
							}
							var icon = data.aprovado ? 'success' : 'error';
							var title = data.aprovado ? 'Parabéns! Aprovado!' : 'Reprovado';
							var html = '<div style=\"text-align:center;\">' +
								'<h2 style=\"color:' + (data.aprovado ? '#28a745' : '#dc3545') + '\">' + data.nota + '%</h2>' +
								'<p>Acertos: ' + data.acertos + '/' + data.total + '</p>' +
								'<p>Nota mínima: ' + data.nota_minima + '%</p>' +
								'<p>Tentativa: ' + data.tentativa + '/' + data.max_tentativas + '</p>' +
								'</div>';

							if(data.aprovado && data.proximo_episodio) {
								html += '<br><a href=\"treinamento_player.php?id=' + treinamentoId + '&episodio=' + data.proximo_episodio + '\" class=\"btn btn-success\"><i class=\"fa fa-play\"></i> Assistir Próximo Episódio</a>';
							}

							Swal.fire({
								icon: icon,
								title: title,
								html: html,
								confirmButtonText: 'OK'
							}).then(() => {
								window.location.reload();
							});
						} else {
							Swal.fire({
								icon: 'error',
								title: 'Erro',
								text: data.message
							});
						}
					}, 'json');
				}
			});
		}

		// =====================================================
		// CONVERSA (CHAT DO TREINAMENTO)
		// =====================================================
		var chatBaseUrl = '{$_ENV["URL_BASE"]}{$CONTEX["path"]}/treinamento/uploads/';
		var ultimoIdChat = {$ultimoIdChat};
		var chatRecorder = null;
		var chatChunks = [];

		function chatAnexarHtml(m) {
			var tipo = m.trem_tx_tipo || 'texto';
			var corpo = '';
			if(tipo === 'texto') {
				corpo = $('<div>').text(m.trem_tx_mensagem || '').html().replace(/\\n/g, '<br>');
			} else if(tipo === 'imagem') {
				corpo = '<a href=\"' + chatBaseUrl + m.trem_tx_arquivo + '\" target=\"_blank\"><img src=\"' + chatBaseUrl + m.trem_tx_arquivo + '\" class=\"chat-imagem\" alt=\"imagem\"></a>';
			} else if(tipo === 'audio') {
				corpo = '<audio controls preload=\"none\" style=\"max-width:280px;\"><source src=\"' + chatBaseUrl + m.trem_tx_arquivo + '\"></audio>';
			}
			var ehMeu = (parseInt(m.trem_nb_usuario_id) === {$usuarioId});
			var nome = $('<span>').text(m.trem_tx_usuario_nome || 'Usuário').html();
			var nivel = m.trem_tx_usuario_nivel || '';
			var ehAdmin = (nivel.indexOf('Administrador') !== -1);
			var badgeNivel = '';
			if(ehAdmin) {
				badgeNivel = '<span class=\"label label-primary\" style=\"font-size:10px;margin-left:4px;\">Gestor</span>';
			} else if(nivel) {
				badgeNivel = '<span class=\"label label-default\" style=\"font-size:10px;margin-left:4px;\">' + $('<span>').text(nivel).html() + '</span>';
			}
			var data = new Date(m.trem_dt_data_cadastro);
			var dataLabel = '';
			if(!isNaN(data)) {
				dataLabel = String(data.getDate()).padStart(2,'0') + '/' + String(data.getMonth()+1).padStart(2,'0') + '/' + data.getFullYear() + ' ' + String(data.getHours()).padStart(2,'0') + ':' + String(data.getMinutes()).padStart(2,'0');
			}
			return '<div class=\"chat-msg ' + (ehMeu ? 'chat-msg-meu' : 'chat-msg-outro') + '\">' +
				'<div class=\"chat-msg-cabecalho\"><i class=\"fa fa-user-circle\"></i> <strong>' + nome + '</strong>' + badgeNivel +
				'<span class=\"text-muted\" style=\"font-size:11px;\"> - ' + dataLabel + '</span></div>' +
				'<div class=\"chat-msg-corpo\">' + corpo + '</div></div>';
		}

		function carregarMensagens() {
			$.get(window.location.pathname, {
				acao_player: 'mensagens_listar',
				treinamento_id: treinamentoId,
				apos_id: ultimoIdChat
			}, function(data) {
				if(!data.success || !data.mensagens || data.mensagens.length === 0) return;
				var container = $('#chatContainer');
				var estavaVazio = container.find('.chat-msg').length === 0;
				var tinhaPlaceholder = container.text().indexOf('Nenhuma mensagem') !== -1;
				if(tinhaPlaceholder) container.html('');
				data.mensagens.forEach(function(m) {
					container.append(chatAnexarHtml(m));
					ultimoIdChat = Math.max(ultimoIdChat, parseInt(m.trem_nb_id));
				});
				container.scrollTop(container[0].scrollHeight);
			}, 'json');
		}

		$('#chatEnviar').on('click', function() {
			var texto = $('#chatTexto').val().trim();
			if(!texto) return;
			$.post(window.location.pathname, {
				acao_player: 'mensagem_enviar',
				treinamento_id: treinamentoId,
				texto: texto
			}, function(data) {
				if(data.success) {
					$('#chatTexto').val('');
					carregarMensagens();
				} else {
					Swal.fire({ icon: 'error', title: 'Erro', text: data.message || 'Não foi possível enviar.' });
				}
			}, 'json');
		});

		$('#chatTexto').on('keydown', function(e) {
			if(e.key === 'Enter') $('#chatEnviar').click();
		});

		$('#chatAnexar').on('click', function() { $('#chatArquivo').click(); });

		$('#chatArquivo').on('change', function() {
			var arquivo = this.files[0];
			if(!arquivo) return;
			var formData = new FormData();
			formData.append('acao_player', 'mensagem_anexo');
			formData.append('treinamento_id', treinamentoId);
			formData.append('arquivo', arquivo);
			$.ajax({
				url: window.location.pathname,
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json',
				success: function(data) {
					if(data.success) {
						carregarMensagens();
					} else {
						Swal.fire({ icon: 'error', title: 'Erro', text: data.message || 'Não foi possível enviar o arquivo.' });
					}
					$('#chatArquivo').val('');
				}
			});
		});

		$('#chatGravar').on('click', function() {
			if(!navigator.mediaDevices || !window.MediaRecorder) {
				Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Gravação de áudio não suportada neste navegador. Você pode anexar um arquivo de áudio.' });
				return;
			}
			navigator.mediaDevices.getUserMedia({ audio: true }).then(function(stream) {
				chatRecorder = new MediaRecorder(stream);
				chatChunks = [];
				chatRecorder.ondataavailable = function(e) {
					if(e.data.size > 0) chatChunks.push(e.data);
				};
				chatRecorder.onstop = function() {
					stream.getTracks().forEach(function(t) { t.stop(); });
					var blob = new Blob(chatChunks, { type: 'audio/webm' });
					var formData = new FormData();
					formData.append('acao_player', 'mensagem_anexo');
					formData.append('treinamento_id', treinamentoId);
					formData.append('arquivo', new File([blob], 'gravacao_' + Date.now() + '.webm', { type: 'audio/webm' }));
					$.ajax({
						url: window.location.pathname,
						method: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						dataType: 'json',
						success: function(data) {
							if(data.success) {
								carregarMensagens();
							} else {
								Swal.fire({ icon: 'error', title: 'Erro', text: data.message || 'Não foi possível enviar o áudio.' });
							}
						}
					});
				};
				chatRecorder.start();
				$('#chatGravar').hide();
				$('#chatParar').show();
				$('#chatGravando').show();
			}).catch(function() {
				Swal.fire({ icon: 'error', title: 'Erro', text: 'Não foi possível acessar o microfone.' });
			});
		});

		$('#chatParar').on('click', function() {
			if(chatRecorder && chatRecorder.state === 'recording') {
				chatRecorder.stop();
			}
			$('#chatParar').hide();
			$('#chatGravando').hide();
			$('#chatGravar').show();
		});

		setInterval(carregarMensagens, 5000);
		setTimeout(function() {
			var container = $('#chatContainer');
			if(container.length) container.scrollTop(container[0].scrollHeight);
		}, 300);

		// Única exposição global necessária (botão Enviar Respostas usa onclick inline)
		window.submeterAvaliacao = submeterAvaliacao;
		})();
	</script>";

	rodape();
