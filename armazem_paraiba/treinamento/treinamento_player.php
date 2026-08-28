<?php
	include_once __DIR__."/../load_env.php";
	include_once __DIR__."/../conecta.php";

	// =====================================================
	// MÓDULO DE TREINAMENTO - Player de Vídeo
	// =====================================================

	$usuarioId = $_SESSION["user_nb_id"] ?? 0;
	$nivelUsuario = $_SESSION["user_tx_nivel"] ?? "";
	$isAdmin = (strpos($nivelUsuario, "Administrador") !== false);
	$treinamentoId = (int)($_GET["id"] ?? $_POST["treinamento_id"] ?? 0);

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

	function obterOuCriarProgresso($treinamentoId, $usuarioId) {
		$progresso = mysqli_fetch_assoc(query(
			"SELECT * FROM treinamento_progresso WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ?",
			"ii",
			[$treinamentoId, $usuarioId]
		));

		if (empty($progresso)) {
			inserir("treinamento_progresso",
				["trepr_nb_usuario_id", "trepr_nb_treinamento_id", "trepr_dt_data_inicio"],
				[$usuarioId, $treinamentoId, date("Y-m-d H:i:s")]
			);
			$progresso = mysqli_fetch_assoc(query(
				"SELECT * FROM treinamento_progresso WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ?",
				"ii",
				[$treinamentoId, $usuarioId]
			));
		}

		return $progresso;
	}

	function gerarEmbedVideo($url, $tipo) {
		if ($tipo === 'youtube') {
			preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $url, $matches);
			$videoId = $matches[1] ?? '';
			return "https://www.youtube.com/embed/{$videoId}?enablejsapi=1&playsinline=1";
		} elseif ($tipo === 'vimeo') {
			preg_match('/vimeo\.com\/(\d+)/', $url, $matches);
			$videoId = $matches[1] ?? '';
			return "https://player.vimeo.com/video/{$videoId}";
		}
		return $url;
	}

	// =====================================================
	// AJAX: ATUALIZAR PROGRESSO
	// =====================================================

	if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao"] ?? "") === "atualizarProgresso") {
		header('Content-Type: application/json');

		$treinamentoId = (int)($_POST["treinamento_id"] ?? 0);
		$tempoAssistido = (int)($_POST["tempo_assistido"] ?? 0);
		$porcentagem = (float)($_POST["porcentagem"] ?? 0);

		// Limitar atualização a 10 segundos por request (anti-fraude)
		$progresso = obterOuCriarProgresso($treinamentoId, $usuarioId);
		$tempoAnterior = (int)($progresso["trepr_nb_tempo_assistido"] ?? 0);
		$tempoMaximo = $tempoAnterior + 10;

		if ($tempoAssistido > $tempoMaximo) {
			$tempoAssistido = $tempoMaximo;
		}

		if ($porcentagem > 100) $porcentagem = 100;

		query(
			"UPDATE treinamento_progresso SET trepr_nb_tempo_assistido = ?, trepr_nb_porcentagem_assistida = ? WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ?",
			"diii",
			[$tempoAssistido, $porcentagem, $treinamentoId, $usuarioId]
		);

		echo json_encode(["success" => true, "tempo" => $tempoAssistido, "porcentagem" => $porcentagem]);
		exit;
	}

	// =====================================================
	// AJAX: SUBMETER AVALIAÇÃO
	// =====================================================

	if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao"] ?? "") === "submeterAvaliacao") {
		header('Content-Type: application/json');

		$treinamentoId = (int)($_POST["treinamento_id"] ?? 0);
		$respostas = $_POST["respostas"] ?? [];

		$treinamento = carregar("treinamento", $treinamentoId);
		$progresso = obterOuCriarProgresso($treinamentoId, $usuarioId);

		// Verificar tentativas
		$tentativas = (int)($progresso["trepr_nb_avaliacao_tentativas"] ?? 0);
		if ($tentativas >= 2) {
			echo json_encode(["success" => false, "message" => "Número máximo de tentativas atingido. Reinicie o treinamento."]);
			exit;
		}

		// Buscar questões embaralhadas
		$qtdQuestoes = (int)($treinamento["trei_nb_quantidade_questoes_prova"] ?? 5);
		$questoes = [];
		$rsQuestoes = query(
			"SELECT * FROM treinamento_questao WHERE treq_nb_treinamento_id = ? AND treq_tx_status = 'ativo' ORDER BY RAND() LIMIT ?",
			"ii",
			[$treinamentoId, $qtdQuestoes]
		);
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
			$respostaUsuario = (int)($respostas[$q["treq_nb_id"]] ?? -1);
			$respostaCorreta = (int)$q["treq_nb_resposta_correta"];
			$acertou = ($respostaUsuario === $respostaCorreta);
			if ($acertou) $acertos++;

			$respostasDetalhadas[] = [
				"questao_id" => $q["treq_nb_id"],
				"resposta_usuario" => $respostaUsuario,
				"resposta_correta" => $respostaCorreta,
				"acertou" => $acertou
			];
		}

		$nota = round(($acertos / count($questoes)) * 100, 2);
		$notaMinima = (int)($treinamento["trei_nb_nota_minima_aprovacao"] ?? 70);
		$aprovado = ($nota >= $notaMinima);

		// Atualizar progresso
		$novaTentativa = $tentativas + 1;
		$concluido = $aprovado ? 1 : 0;
		$dataConclusao = $aprovado ? date("Y-m-d H:i:s") : null;

		query(
			"UPDATE treinamento_progresso SET
				trepr_nb_avaliacao_tentativas = ?,
				trepr_tx_avaliacao_respostas_json = ?,
				trepr_nb_avaliacao_nota = ?,
				trepr_nb_avaliacao_aprovada = ?,
				trepr_nb_concluido = ?,
				trepr_dt_data_conclusao = ?
			WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ?",
			"isiiissi",
			[$novaTentativa, json_encode($respostasDetalhadas), $nota, $aprovado ? 1 : 0, $concluido, $dataConclusao, $treinamentoId, $usuarioId]
		);

		// Se reprovado e última tentativa, resetar progresso
		if (!$aprovado && $novaTentativa >= 2) {
			query(
				"UPDATE treinamento_progresso SET
					trepr_nb_tempo_assistido = 0,
					trepr_nb_porcentagem_assistida = 0,
					trepr_nb_avaliacao_aprovada = 0,
					trepr_nb_concluido = 0
				WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ?",
				"ii",
				[$treinamentoId, $usuarioId]
			);
		}

		registrarLogTreinamento($treinamentoId, $usuarioId, "avaliacao", "Nota: {$nota}% | Aprovado: " . ($aprovado ? "Sim" : "Não") . " | Tentativa: {$novaTentativa}");

		echo json_encode([
			"success" => true,
			"aprovado" => $aprovado,
			"nota" => $nota,
			"nota_minima" => $notaMinima,
			"acertos" => $acertos,
			"total" => count($questoes),
			"tentativa" => $novaTentativa,
			"max_tentativas" => 2,
			"respostas" => $respostasDetalhadas,
			"questoes" => array_map(function($q) {
				return [
					"id" => $q["treq_nb_id"],
					"pergunta" => $q["treq_tx_pergunta"],
					"opcoes" => json_decode($q["treq_tx_opcoes"], true),
					"resposta_correta" => (int)$q["treq_nb_resposta_correta"]
				];
			}, $questoes),
			"concluido" => $aprovado
		]);
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
	$progresso = obterOuCriarProgresso($treinamentoId, $usuarioId);

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

	// Buscar questões para avaliação
	$questoes = [];
	$rsQuestoes = query(
		"SELECT * FROM treinamento_questao WHERE treq_nb_treinamento_id = ? AND treq_tx_status = 'ativo' ORDER BY RAND()",
		"i",
		[$treinamentoId]
	);
	if ($rsQuestoes) {
		while ($row = mysqli_fetch_assoc($rsQuestoes)) {
			$questoes[] = $row;
		}
	}

	// Variáveis para o template
	$titulo = htmlspecialchars($treinamento["trei_tx_titulo"]);
	$descricao = htmlspecialchars($treinamento["trei_tx_descricao"] ?? "");
	$conteudoProgramatico = htmlspecialchars($treinamento["trei_tx_conteudo_programatico"] ?? "");
	$urlVideo = $treinamento["trei_tx_url_video"] ?? "";
	$tipoVideo = $treinamento["trei_tx_tipo_video"] ?? "youtube";
	$cargaHoraria = $treinamento["trei_nb_carga_horaria"] ?? 0;
	$obrigatorio = ($treinamento["trei_nb_obrigatorio"] ?? 0) == 1;
	$porcentagem = round($progresso["trepr_nb_porcentagem_assistida"] ?? 0, 1);
	$tempoAssistido = (int)($progresso["trepr_nb_tempo_assistido"] ?? 0);
	$concluido = ($progresso["trepr_nb_concluido"] ?? 0) == 1;
	$aprovado = ($progresso["trepr_nb_avaliacao_aprovada"] ?? 0) == 1;
	$tentativas = (int)($progresso["trepr_nb_avaliacao_tentativas"] ?? 0);
	$notaAtual = $progresso["trepr_nb_avaliacao_nota"] ?? null;
	$podeAvaliar = ($porcentagem >= 99 && !$aprovado && $tentativas < 2);
	$embedUrl = gerarEmbedVideo($urlVideo, $tipoVideo);

	// Log de acesso
	registrarLogTreinamento($treinamentoId, $usuarioId, "acesso", "Acesso ao player");

	// =====================================================
	// RENDERIZAR PÁGINA
	// =====================================================

	cabecalho("Treinamento: " . $titulo);

	echo "
	<style>
		.player-container {
			background: #000;
			border-radius: 8px;
			overflow: hidden;
			margin-bottom: 20px;
		}
		.player-container iframe {
			width: 100%;
			height: 500px;
			border: none;
		}
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
		}
		.material-item {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 10px;
			background: #f9f9f9;
			border-radius: 4px;
			margin-bottom: 8px;
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
		}
		.opcao-label {
			display: block;
			padding: 10px 15px;
			margin-bottom: 8px;
			border: 1px solid #ddd;
			border-radius: 4px;
			cursor: pointer;
			transition: all 0.2s;
		}
		.opcao-label:hover {
			background: #f0f0f0;
			border-color: #3c8dbc;
		}
		.opcao-label input {
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
	</style>

	<div class='container-fluid'>
		<div class='row'>
			<!-- COLUNA PRINCIPAL: Player -->
			<div class='col-md-8'>
				<!-- Player de Vídeo -->
				<div class='player-container'>
					<iframe id='videoPlayer' src='{$embedUrl}' allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' allowfullscreen></iframe>
				</div>

				<!-- Barra de Progresso -->
				<div class='info-card'>
					<div class='row'>
						<div class='col-md-8'>
							<strong>Progresso:</strong> {$porcentagem}%
							<div class='progress progress-bar-custom'>
								<div class='progress-bar progress-bar-striped progress-bar-animated' role='progressbar' style='width:{$porcentagem}%' id='progressBar'></div>
							</div>
						</div>
						<div class='col-md-4 text-right'>
							<strong>Tempo:</strong>
							<span class='tempo-display' id='tempoDisplay'>{$tempoAssistido}s</span>
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
					</ul>
					<div class='tab-content'>

						<!-- ABA: DESCRIÇÃO -->
						<div class='tab-pane active' id='tab_descricao'>
							<h4>{$titulo}</h4>
							<p>{$descricao}</p>
							" . (!empty($conteudoProgramatico) ? "<h5>Conteúdo Programático:</h5><p>" . nl2br($conteudoProgramatico) . "</p>" : "") . "
							<div class='row'>
								<div class='col-md-6'><strong>Carga Horária:</strong> {$cargaHoraria} minutos</div>
								<div class='col-md-6'><strong>Obrigatório:</strong> " . ($obrigatorio ? "Sim" : "Não") . "</div>
							</div>
						</div>";

				// ABA: MATERIAIS
				if (!empty($materiais)) {
					echo "
						<div class='tab-pane' id='tab_materiais'>";
					foreach ($materiais as $m) {
						$tamanhoKB = round(($m["tram_nb_tamanho"] ?? 0) / 1024, 1);
						$caminho = "treinamento/uploads/" . $m["tram_tx_arquivo"];
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
				if ($podeAvaliar || $tentativas > 0) {
					echo "
						<div class='tab-pane' id='tab_avaliacao'>";

					if ($concluido && $aprovado) {
						echo "
							<div class='alert alert-success'>
								<i class='fa fa-check-circle'></i> <strong>Avaliação Aprovada!</strong><br>
								Nota: <strong>{$notaAtual}%</strong>
							</div>";
					} elseif ($tentativas >= 2 && !$aprovado) {
						echo "
							<div class='alert alert-danger'>
								<i class='fa fa-times-circle'></i> <strong>Número máximo de tentativas atingido (2).</strong><br>
								É necessário reassistir o treinamento para tentar novamente.
							</div>";
					} elseif (!$podeAvaliar && $tentativas > 0) {
						echo "
							<div class='alert alert-warning'>
								<i class='fa fa-exclamation-triangle'></i> Você precisa assistir pelo menos 99% do treinamento para realizar a avaliação.
							</div>";
					} else {
						echo "
							<div class='alert alert-info'>
								<i class='fa fa-info-circle'></i> <strong>Avaliação:</strong> Responda as questões abaixo. Nota mínima para aprovação: <strong>{$treinamento["trei_nb_nota_minima_aprovacao"]}%</strong>.
								<br><small>Tentativa {$tentativas} de 2. Em caso de reprovação na 2ª tentativa, o progresso será resetado.</small>
							</div>
							<form id='formAvaliacao'>";

						if (!empty($questoes)) {
							$idx = 1;
							foreach ($questoes as $q) {
								$opcoes = json_decode($q["treq_tx_opcoes"], true);
								echo "
								<div class='questao-card'>
									<h4>Questão {$idx}: " . htmlspecialchars($q["treq_tx_pergunta"]) . "</h4>";
								if ($opcoes) {
									$opIdx = 0;
									foreach ($opcoes as $op) {
										if (!empty(trim($op))) {
											echo "
										<label class='opcao-label'>
											<input type='radio' name='resposta[{$q["treq_nb_id"]}]' value='{$opIdx}'> " . htmlspecialchars($op) . "
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
						<div class='col-xs-6'>{$porcentagem}%</div>
					</div>
					<div class='row'>
						<div class='col-xs-6'><strong>Tempo Assistido:</strong></div>
						<div class='col-xs-6'><span id='tempoLateral'>{$tempoAssistido}s</span></div>
					</div>
					<div class='row'>
						<div class='col-xs-6'><strong>Carga Horária:</strong></div>
						<div class='col-xs-6'>{$cargaHoraria} min</div>
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

				<div class='info-card'>
					<h4><i class='fa fa-graduation-cap'></i> Avaliação</h4>
					<p><strong>Questões:</strong> " . count($questoes) . "</p>
					<p><strong>Nota Mínima:</strong> {$treinamento["trei_nb_nota_minima_aprovacao"]}%</p>
					<p><strong>Tentativas:</strong> {$tentativas}/2</p>
					" . ($aprovado ? "<p class='text-success'><strong>Status:</strong> Aprovado</p>" : "") . "
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
		// CONTROLE DE PROGRESSO
		// =====================================================
		var temporizador = null;
		var tempoAtual = {$tempoAssistido};
		var porcentagemAtual = {$porcentagem};
		var treinamentoId = {$treinamentoId};
		var concluido = " . ($concluido ? "true" : "false") . ";

		function formatarTempo(segundos) {
			var h = Math.floor(segundos / 3600);
			var m = Math.floor((segundos % 3600) / 60);
			var s = segundos % 60;
			return (h > 0 ? h + ':' : '') + (m > 0 ? String(m).padStart(2,'0') + ':' : '00:') + String(s).padStart(2,'0');
		}

		function atualizarProgresso() {
			if(concluido) return;

			// Calcular porcentagem baseada no tempo (estimativa: 100% em carga_horaria * 60 segundos)
			var cargaHoraria = {$cargaHoraria};
			var tempoTotal = cargaHoraria * 60;
			if(tempoTotal > 0) {
				porcentagemAtual = Math.min(100, (tempoAtual / tempoTotal) * 100);
			} else {
				porcentagemAtual = Math.min(100, porcentagemAtual + 0.1);
			}

			// Atualizar display
			$('#tempoDisplay').text(formatarTempo(tempoAtual));
			$('#tempoLateral').text(tempoAtual + 's');
			$('#progressBar').css('width', porcentagemAtual.toFixed(1) + '%');

			// Enviar para servidor (máximo a cada 10 segundos)
			$.post(window.location.pathname, {
				acao: 'atualizarProgresso',
				treinamento_id: treinamentoId,
				tempo_assistido: tempoAtual,
				porcentagem: porcentagemAtual
			}, function(data) {
				if(data.success) {
					tempoAtual = data.tempo;
					porcentagemAtual = data.porcentagem;
				}
			}, 'json');

			// Verificar se pode avaliar
			if(porcentagemAtual >= 99 && !concluido) {
				// Recarregar para mostrar aba de avaliação
				if($('#tab_avaliacao').length === 0) {
					window.location.reload();
				}
			}
		}

		// Iniciar temporizador (a cada 1 segundo)
		if(!concluido) {
			temporizador = setInterval(function() {
				tempoAtual++;
				atualizarProgresso();
			}, 1000);
		}

		// =====================================================
		// AVALIAÇÃO
		// =====================================================

		function submeterAvaliacao() {
			// Verificar se todas as questões foram respondidas
			var totalQuestoes = {$count($questoes)};
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
				text: 'Você terá no máximo 2 tentativas.',
				icon: 'question',
				showCancelButton: true,
				confirmButtonColor: '#3085d6',
				cancelButtonColor: '#d33',
				confirmButtonText: 'Sim, enviar!',
				cancelButtonText: 'Cancelar'
			}).then((result) => {
				if (result.isConfirmed) {
					$.post(window.location.pathname, {
						acao: 'submeterAvaliacao',
						treinamento_id: treinamentoId,
						respostas: respostas
					}, function(data) {
						if(data.success) {
							var icon = data.aprovado ? 'success' : 'error';
							var title = data.aprovado ? 'Parabéns! Aprovado!' : 'Reprovado';
							var html = '<div style=\"text-align:center;\">' +
								'<h2 style=\"color:' + (data.aprovado ? '#28a745' : '#dc3545') + '\">' + data.nota + '%</h2>' +
								'<p>Acertos: ' + data.acertos + '/' + data.total + '</p>' +
								'<p>Nota mínima: ' + data.nota_minima + '%</p>' +
								'<p>Tentativa: ' + data.tentativa + '/' + data.max_tentativas + '</p>' +
								'</div>';

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
		// API DO YOUTUBE (para detectar fim do vídeo)
		// =====================================================
		var tag = document.createElement('script');
		tag.src = 'https://www.youtube.com/iframe_api';
		var firstScriptTag = document.getElementsByTagName('script')[0];
		firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

		var player;
		function onYouTubeIframeAPIReady() {
			// Só inicializar se for YouTube
			if('{$tipoVideo}' === 'youtube') {
				try {
					player = new YT.Player('videoPlayer', {
						events: {
							'onStateChange': onPlayerStateChange
						}
					});
				} catch(e) {
					console.log('Erro ao inicializar player YouTube:', e);
				}
			}
		}

		function onPlayerStateChange(event) {
			// Se o vídeo terminou, marcar 100%
			if(event.data === YT.PlayerState.ENDED) {
				porcentagemAtual = 100;
				atualizarProgresso();
			}
			// Se está pausado ou tocando, pode usar para controle
		}
	</script>";

	rodape();
