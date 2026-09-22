<?php
	// =====================================================
	// MÓDULO DE TREINAMENTO - Certificados
	// Gera o certificado de conclusão do treinamento seguindo o padrão de PDF
	// do módulo Documentos (tipos_documentos + TCPDF) e, quando o tipo de
	// documento exigir assinatura, consome o módulo de assinatura existente.
	// O arquivo final é salvo na pasta do funcionário (aba Documentos).
	// =====================================================

	function treinamento_certificado_log($treinamentoId, $usuarioId, $evento, $detalhe = "") {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		query(
			"INSERT INTO treinamento_log (trelog_nb_treinamento_id, trelog_nb_usuario_id, trelog_tx_evento, trelog_tx_detalhe, trelog_tx_ip, trelog_tx_user_agent) VALUES (?, ?, ?, ?, ?, ?)",
			"iissss",
			[$treinamentoId, $usuarioId, $evento, $detalhe, $ip, $userAgent]
		);
	}

	function treinamento_certificado_normalizar($texto) {
		$texto = strval($texto);
		if (function_exists('iconv')) {
			$convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
			if ($convertido !== false) {
				$texto = $convertido;
			}
		}
		return strtolower(trim($texto));
	}

	function treinamento_certificado_sanitizarArquivo($nome) {
		$nome = trim(strval($nome));
		$nome = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '_', $nome);
		$nome = preg_replace('/\s+/', ' ', strval($nome));
		$nome = trim($nome, " .");
		if ($nome === '') {
			$nome = 'Certificado';
		}
		if (strlen($nome) > 150) {
			$nome = substr($nome, 0, 150);
		}
		return $nome . '.pdf';
	}

	// Localiza o tipo de documento usado para os certificados (nome "Certificado"/"Certificados").
	function treinamento_certificado_buscarTipo() {
		$res = query(
			"SELECT tipo_nb_id, tipo_tx_nome, tipo_tx_assinatura, tipo_tx_vencimento, tipo_tx_logo,
				tipo_tx_cabecalho, tipo_tx_rodape, tipo_nb_grupo, tipo_nb_sbgrupo
			 FROM tipos_documentos
			 WHERE LOWER(TRIM(tipo_tx_status)) = 'ativo'
			   AND (tipo_tx_nome = 'Certificado' OR tipo_tx_nome = 'Certificados' OR tipo_tx_nome LIKE 'Certificado%')
			 ORDER BY CASE WHEN tipo_tx_nome IN ('Certificado', 'Certificados') THEN 1 ELSE 2 END, tipo_nb_id ASC
			 LIMIT 1"
		);
		return ($res instanceof mysqli_result) ? (mysqli_fetch_assoc($res) ?: []) : [];
	}

	function treinamento_certificado_tipoRequerAssinatura($tipo) {
		return strtolower(trim(strval($tipo['tipo_tx_assinatura'] ?? 'nao'))) === 'sim';
	}

	// Verifica se o treinamento foi concluído pelo usuário: vídeo finalizado e
	// avaliação aprovada (quando o treinamento possuir questões de avaliação).
	function treinamento_certificado_estaConcluido($treinamentoId, $usuarioId) {
		$treinamentoId = (int)$treinamentoId;
		$usuarioId = (int)$usuarioId;
		if ($treinamentoId <= 0 || $usuarioId <= 0) {
			return false;
		}

		$treinamento = carregar("treinamento", $treinamentoId);
		if (empty($treinamento)) {
			return false;
		}

		$progresso = mysqli_fetch_assoc(query(
			"SELECT trepr_nb_concluido, trepr_nb_avaliacao_aprovada FROM treinamento_progresso
			 WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ? AND trepr_nb_episodio_id IS NULL
			 ORDER BY trepr_nb_id DESC LIMIT 1",
			"ii",
			[$treinamentoId, $usuarioId]
		));

		if (($treinamento['trei_tx_serie'] ?? 'nao') === 'sim') {
			// Séries: concluído quando todos os episódios ativos estão aprovados
			// (a aprovação do episódio exige o vídeo assistido).
			if (((int)($progresso['trepr_nb_concluido'] ?? 0)) === 1) {
				return true;
			}
			$rs = query(
				"SELECT trepi_nb_id FROM treinamento_episodio WHERE trepi_nb_treinamento_id = ? AND trepi_tx_status = 'ativo'",
				"i",
				[$treinamentoId]
			);
			$total = 0;
			$aprovados = 0;
			while ($rs && ($ep = mysqli_fetch_assoc($rs))) {
				$total++;
				$progEpi = mysqli_fetch_assoc(query(
					"SELECT trepr_nb_avaliacao_aprovada FROM treinamento_progresso
					 WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ? AND trepr_nb_episodio_id = ?
					 ORDER BY trepr_nb_id DESC LIMIT 1",
					"iii",
					[$treinamentoId, $usuarioId, (int)$ep['trepi_nb_id']]
				));
				if (((int)($progEpi['trepr_nb_avaliacao_aprovada'] ?? 0)) === 1) {
					$aprovados++;
				}
			}
			return ($total > 0 && $aprovados >= $total);
		}

		// Vídeo único: exige o vídeo finalizado e, havendo questões cadastradas,
		// também a aprovação na avaliação.
		if (((int)($progresso['trepr_nb_concluido'] ?? 0)) !== 1) {
			return false;
		}
		if (treinamento_certificado_temQuestoes($treinamentoId)) {
			return ((int)($progresso['trepr_nb_avaliacao_aprovada'] ?? 0)) === 1;
		}
		return true;
	}

	// Indica se o treinamento (vídeo único) possui questões de avaliação ativas.
	function treinamento_certificado_temQuestoes($treinamentoId) {
		$res = mysqli_fetch_assoc(query(
			"SELECT COUNT(*) AS total FROM treinamento_questao WHERE treq_nb_treinamento_id = ? AND treq_tx_status = 'ativo'",
			"i",
			[(int)$treinamentoId]
		));
		return ((int)($res['total'] ?? 0)) > 0;
	}

	function treinamento_certificado_instrutorLabel($treinamento) {
		if (($treinamento['trei_tx_instrutor_tipo'] ?? 'funcionario') === 'externo') {
			$label = !empty($treinamento['trei_tx_instrutor_nome']) ? $treinamento['trei_tx_instrutor_nome'] : 'Não informado';
			if (!empty($treinamento['trei_tx_instrutor_capacitacao'])) {
				$label .= ' — ' . $treinamento['trei_tx_instrutor_capacitacao'];
			}
			return $label;
		}
		if (!empty($treinamento['trei_nb_instrutor_entidade_id'])) {
			$enti = carregar("entidade", (int)$treinamento['trei_nb_instrutor_entidade_id']);
			if (!empty($enti['enti_tx_nome'])) {
				return $enti['enti_tx_nome'];
			}
		}
		return 'Não informado';
	}

	function treinamento_certificado_criadorLabel($treinamento) {
		$partes = [];
		if (!empty($treinamento['trei_tx_criador_nome'])) $partes[] = $treinamento['trei_tx_criador_nome'];
		if (!empty($treinamento['trei_tx_criador_cargo'])) $partes[] = $treinamento['trei_tx_criador_cargo'];
		if (!empty($treinamento['trei_tx_criador_setor'])) $partes[] = $treinamento['trei_tx_criador_setor'];
		return !empty($partes) ? implode(' — ', $partes) : 'Não informado';
	}

	function treinamento_certificado_formatarCarga($segundos) {
		$segundos = max(0, (int)$segundos);
		$horas = floor($segundos / 3600);
		$minutos = floor(($segundos % 3600) / 60);
		if ($horas > 0 && $minutos > 0) {
			return $horas . 'h ' . $minutos . 'min';
		}
		if ($horas > 0) {
			return $horas . 'h';
		}
		return $minutos . 'min';
	}

	// Reúne todos os dados do treinamento, do usuário/funcionário e da conclusão.
	function treinamento_certificado_dados($treinamentoId, $usuarioId) {
		$treinamentoId = (int)$treinamentoId;
		$usuarioId = (int)$usuarioId;
		$treinamento = carregar("treinamento", $treinamentoId);
		if (empty($treinamento)) {
			return [];
		}

		$usuario = mysqli_fetch_assoc(query(
			"SELECT u.user_nb_id, u.user_tx_nome, u.user_tx_login, u.user_nb_entidade, u.user_nb_empresa,
				e.enti_nb_id, e.enti_tx_nome, e.enti_tx_cpf, e.enti_tx_matricula, e.enti_tx_ocupacao,
				e.enti_tx_email, e.enti_setor_id,
				COALESCE(NULLIF(e.enti_nb_empresa, 0), u.user_nb_empresa) AS empresa_id,
				emp.empr_tx_nome, emp.empr_tx_logo,
				g.grup_tx_nome AS setor_nome
			 FROM user u
			 LEFT JOIN entidade e ON e.enti_nb_id = u.user_nb_entidade
			 LEFT JOIN empresa emp ON emp.empr_nb_id = COALESCE(NULLIF(e.enti_nb_empresa, 0), u.user_nb_empresa)
			 LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
			 WHERE u.user_nb_id = ? LIMIT 1",
			"i",
			[$usuarioId]
		));
		if (empty($usuario)) {
			return [];
		}

		$ehSerie = ($treinamento['trei_tx_serie'] ?? 'nao') === 'sim';

		$progresso = mysqli_fetch_assoc(query(
			"SELECT * FROM treinamento_progresso
			 WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ? AND trepr_nb_episodio_id IS NULL
			 ORDER BY trepr_nb_id DESC LIMIT 1",
			"ii",
			[$treinamentoId, $usuarioId]
		));

		$cargaHoraria = (int)($treinamento['trei_nb_carga_horaria'] ?? 0);
		$cargaEpisodios = 0;
		$totalEpisodios = 0;
		$notaSerie = 0.0;
		$notaSerieCount = 0;
		$episodios = [];
		$ultimaConclusaoEpi = '';

		if ($ehSerie) {
			$rsEpi = query(
				"SELECT trepi_nb_id, trepi_nb_ordem, trepi_tx_titulo, trepi_nb_carga_horaria FROM treinamento_episodio
				 WHERE trepi_nb_treinamento_id = ? AND trepi_tx_status = 'ativo'
				 ORDER BY trepi_nb_ordem, trepi_nb_id",
				"i",
				[$treinamentoId]
			);
			while ($rsEpi && ($ep = mysqli_fetch_assoc($rsEpi))) {
				$totalEpisodios++;
				$cargaEpi = (int)($ep['trepi_nb_carga_horaria'] ?? 0);
				$cargaEpisodios += $cargaEpi;

				$progEpi = mysqli_fetch_assoc(query(
					"SELECT trepr_nb_avaliacao_nota, trepr_nb_avaliacao_aprovada, trepr_dt_data_conclusao, trepr_dt_data_inicio
					 FROM treinamento_progresso
					 WHERE trepr_nb_treinamento_id = ? AND trepr_nb_usuario_id = ? AND trepr_nb_episodio_id = ?
					 ORDER BY trepr_nb_id DESC LIMIT 1",
					"iii",
					[$treinamentoId, $usuarioId, (int)$ep['trepi_nb_id']]
				));

				$notaEpi = null;
				$aprovadoEpi = false;
				if ($progEpi && $progEpi['trepr_nb_avaliacao_nota'] !== null && $progEpi['trepr_nb_avaliacao_nota'] !== '') {
					$notaEpi = (float)$progEpi['trepr_nb_avaliacao_nota'];
				}
				if ($progEpi && ((int)($progEpi['trepr_nb_avaliacao_aprovada'] ?? 0)) === 1) {
					$aprovadoEpi = true;
					if ($notaEpi !== null) {
						$notaSerie += $notaEpi;
						$notaSerieCount++;
					}
				}

				$dataEpi = trim(strval($progEpi['trepr_dt_data_conclusao'] ?? ''));
				if ($dataEpi === '') {
					$dataEpi = trim(strval($progEpi['trepr_dt_data_inicio'] ?? ''));
				}
				if ($dataEpi !== '' && $dataEpi > $ultimaConclusaoEpi) {
					$ultimaConclusaoEpi = $dataEpi;
				}

				$episodios[] = [
					"ordem" => (int)($ep['trepi_nb_ordem'] ?? $totalEpisodios),
					"titulo" => trim(strval($ep['trepi_tx_titulo'] ?? '')),
					"carga" => $cargaEpi,
					"carga_label" => treinamento_certificado_formatarCarga($cargaEpi),
					"conclusao" => $dataEpi,
					"conclusao_label" => $dataEpi !== '' ? date("d/m/Y", strtotime($dataEpi)) : '',
					"nota" => $notaEpi,
					"aprovado" => $aprovadoEpi
				];
			}
		}
		if ($cargaHoraria <= 0) {
			$cargaHoraria = $cargaEpisodios;
		}

		$conclusao = trim(strval($progresso['trepr_dt_data_conclusao'] ?? ''));
		if ($conclusao === '' && $ehSerie && $ultimaConclusaoEpi !== '') {
			$conclusao = $ultimaConclusaoEpi;
		}
		if ($conclusao === '') {
			$conclusao = date("Y-m-d H:i:s");
		}

		$nota = null;
		if (!$ehSerie && $progresso && $progresso['trepr_nb_avaliacao_nota'] !== null && $progresso['trepr_nb_avaliacao_nota'] !== '') {
			$nota = (float)$progresso['trepr_nb_avaliacao_nota'];
		} elseif ($ehSerie && $notaSerieCount > 0) {
			$nota = round($notaSerie / $notaSerieCount, 1);
		}

		$diasValidade = (int)($treinamento['trei_nb_dias_validade'] ?? 0);
		$validoAte = '';
		if ($diasValidade > 0) {
			$validoAte = date("d/m/Y", strtotime($conclusao . ' +' . $diasValidade . ' days'));
		}

		$codigo = 'CERT-' . $treinamentoId . '-' . $usuarioId . '-' . strtoupper(substr(sha1($treinamentoId . '|' . $usuarioId . '|' . $conclusao), 0, 8));

		return [
			"treinamento" => $treinamento,
			"usuario" => $usuario,
			"progresso" => $progresso ?: [],
			"entidade_id" => (int)($usuario['enti_nb_id'] ?? 0),
			"nome" => trim(strval($usuario['enti_tx_nome'] ?? $usuario['user_tx_nome'] ?? '')),
			"cpf" => trim(strval($usuario['enti_tx_cpf'] ?? '')),
			"matricula" => trim(strval($usuario['enti_tx_matricula'] ?? '')),
			"ocupacao" => trim(strval($usuario['enti_tx_ocupacao'] ?? '')),
			"email" => trim(strval($usuario['enti_tx_email'] ?? '')),
			"empresa" => trim(strval($usuario['empr_tx_nome'] ?? '')),
			"empresa_logo" => trim(strval($usuario['empr_tx_logo'] ?? '')),
			"setor" => trim(strval($usuario['setor_nome'] ?? '')),
			"titulo" => trim(strval($treinamento['trei_tx_titulo'] ?? '')),
			"tipo" => (($treinamento['trei_tx_tipo'] ?? 'treinamento') === 'dss') ? 'DSS' : 'Treinamento',
			"tipo_treinamento" => ucfirst(strval($treinamento['trei_tx_tipo_treinamento'] ?? '')),
			"eh_serie" => $ehSerie,
			"total_episodios" => $totalEpisodios,
			"episodios" => $episodios,
			"carga_episodios" => $cargaEpisodios,
			"carga_horaria" => (int)$cargaHoraria,
			"carga_label" => treinamento_certificado_formatarCarga($cargaHoraria),
			"conclusao" => $conclusao,
			"conclusao_label" => date("d/m/Y", strtotime($conclusao)),
			"conclusao_data" => substr($conclusao, 0, 10),
			"nota" => $nota,
			"valido_ate" => $validoAte,
			"dias_validade" => $diasValidade,
			"instrutor" => treinamento_certificado_instrutorLabel($treinamento),
			"criador" => treinamento_certificado_criadorLabel($treinamento),
			"codigo" => $codigo,
			"descricao" => trim(strval($treinamento['trei_tx_descricao'] ?? '')),
			"conteudo_programatico" => trim(strval($treinamento['trei_tx_conteudo_programatico'] ?? '')),
		];
	}

	// Resolve o valor de um campo configurado no layout do tipo de documento.
	function treinamento_certificado_valorCampo($label, $tipoCampo, $dados) {
		$l = treinamento_certificado_normalizar($label);
		$t = treinamento_certificado_normalizar($tipoCampo);

		if ($l === '') {
			return '';
		}
		if (strpos($l, 'cpf') !== false) {
			return $dados['cpf'];
		}
		if (strpos($l, 'matricula') !== false || strpos($l, 'matr cula') !== false) {
			return $dados['matricula'];
		}
		if (strpos($l, 'empresa') !== false || strpos($l, 'filial') !== false) {
			return $dados['empresa'];
		}
		if (strpos($l, 'subsetor') !== false) {
			return $dados['setor'];
		}
		if (strpos($l, 'setor') !== false) {
			return $dados['setor'];
		}
		if (strpos($l, 'cargo') !== false || strpos($l, 'funcao') !== false || strpos($l, 'ocupacao') !== false || strpos($l, 'profissao') !== false) {
			return $dados['ocupacao'];
		}
		if (strpos($l, 'carga') !== false || strpos($l, 'duracao') !== false || strpos($l, 'horaria') !== false) {
			return $dados['carga_label'];
		}
		if (strpos($l, 'instrutor') !== false || strpos($l, 'responsavel') !== false) {
			return $dados['instrutor'];
		}
		if (strpos($l, 'validade') !== false || strpos($l, 'vencimento') !== false) {
			return $dados['valido_ate'];
		}
		if (strpos($l, 'nota') !== false || strpos($l, 'aproveitamento') !== false || strpos($l, 'percentual') !== false) {
			return ($dados['nota'] !== null) ? ($dados['nota'] . '%') : '';
		}
		if (strpos($l, 'codigo') !== false || strpos($l, 'autentic') !== false || strpos($l, 'protocolo') !== false) {
			return $dados['codigo'];
		}
		if (strpos($l, 'conteudo') !== false || strpos($l, 'programatico') !== false || strpos($l, 'descricao') !== false || strpos($l, 'observ') !== false) {
			return $dados['conteudo_programatico'] !== '' ? $dados['conteudo_programatico'] : $dados['descricao'];
		}
		if (strpos($l, 'emit') !== false || strpos($l, 'criador') !== false || strpos($l, 'cadastr') !== false) {
			return $dados['criador'];
		}
		if (strpos($l, 'treinamento') !== false || strpos($l, 'curso') !== false || strpos($l, 'evento') !== false) {
			return $dados['titulo'];
		}
		if (strpos($l, 'tipo') !== false) {
			return $dados['tipo'];
		}
		if (strpos($l, 'data') !== false || strpos($l, 'conclusao') !== false || strpos($l, 'realizacao') !== false || strpos($l, 'emissao') !== false || strpos($l, 'geracao') !== false) {
			if (strpos($l, 'emissao') !== false || strpos($l, 'geracao') !== false || strpos($l, 'cadastro') !== false) {
				return date("d/m/Y");
			}
			return $dados['conclusao_label'];
		}
		if (strpos($l, 'nome') !== false || strpos($l, 'participante') !== false || strpos($l, 'funcionario') !== false || strpos($l, 'colaborador') !== false || strpos($l, 'aluno') !== false) {
			return $dados['nome'];
		}

		if ($t === 'data') {
			return $dados['conclusao_label'];
		}
		if ($t === 'usuario') {
			return $dados['nome'];
		}
		return '';
	}

	function treinamento_certificado_camposPadrao($dados) {
		$campos = [
			["label" => "Nome", "valor" => $dados['nome']],
			["label" => "CPF", "valor" => $dados['cpf']],
			["label" => "Matrícula", "valor" => $dados['matricula']],
			["label" => "Empresa", "valor" => $dados['empresa']],
			["label" => "Função", "valor" => $dados['ocupacao']],
			["label" => "Treinamento", "valor" => $dados['titulo']],
			["label" => "Tipo", "valor" => $dados['tipo']],
			["label" => "Carga Horária", "valor" => $dados['carga_label']],
		];
		if (!empty($dados['eh_serie']) && (int)$dados['total_episodios'] > 0) {
			$campos[] = ["label" => "Episódios", "valor" => (string)(int)$dados['total_episodios']];
		}
		$campos[] = ["label" => "Data de Conclusão", "valor" => $dados['conclusao_label']];
		if ($dados['nota'] !== null) {
			$campos[] = ["label" => "Aproveitamento", "valor" => $dados['nota'] . '%'];
		}
		$campos[] = ["label" => "Instrutor", "valor" => $dados['instrutor']];
		$campos[] = ["label" => "Emitido por", "valor" => $dados['criador']];
		if ($dados['dias_validade'] > 0) {
			$campos[] = ["label" => "Válido até", "valor" => $dados['valido_ate']];
		}
		$campos[] = ["label" => "Código de Autenticação", "valor" => $dados['codigo']];
		return $campos;
	}

	function treinamento_certificado_resolverArquivo($caminho) {
		$caminho = trim(strval($caminho));
		if ($caminho === '') {
			return '';
		}
		if (preg_match('#^(https?:)?//#i', $caminho)) {
			return '';
		}
		$candidatos = [];
		$ehCaminhoWindows = (strlen($caminho) > 2 && $caminho[1] === ':' && ctype_alpha($caminho[0]) && ($caminho[2] === '\\' || $caminho[2] === '/'));
		if ($caminho[0] === '/' || $ehCaminhoWindows) {
			$candidatos[] = $caminho;
		} else {
			$candidatos[] = $caminho;
			$candidatos[] = __DIR__ . '/' . $caminho;
			$candidatos[] = dirname(__DIR__) . '/' . ltrim($caminho, '/\\');
		}
		foreach ($candidatos as $candidato) {
			$real = realpath($candidato);
			if ($real && file_exists($real)) {
				return $real;
			}
			if (file_exists($candidato)) {
				return $candidato;
			}
		}
		return '';
	}

	// Desenha uma logo preservando a proporção (apenas formatos suportados pelo TCPDF).
	function treinamento_certificado_desenharImagem($pdf, $caminho, $x, $y, $larguraMax, $alturaMax, $alinharDireita = false) {
		$arquivo = treinamento_certificado_resolverArquivo($caminho);
		if ($arquivo === '') {
			return;
		}
		$info = @getimagesize($arquivo);
		if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
			return;
		}
		$largura = $larguraMax;
		$altura = $alturaMax;
		if ($info[0] > 0 && $info[1] > 0) {
			$proporcao = $info[0] / $info[1];
			if (($larguraMax / $proporcao) <= $alturaMax) {
				$altura = $larguraMax / $proporcao;
			} else {
				$largura = $alturaMax * $proporcao;
			}
		}
		if ($alinharDireita) {
			$x = $x - $largura;
		}
		$pdf->Image($arquivo, $x, $y, $largura, $altura, '', '', '', true, 300, '', false, false, 0, false, false, false);
	}

	// Gera o PDF do certificado no padrão do módulo Documentos (TCPDF + cabeçalho/rodapé do tipo).
	function treinamento_certificado_gerarPdf($dados, $tipo, $caminhoAbs) {
		$caminhoAbs = strval($caminhoAbs);
		if ($caminhoAbs === '' || empty($dados)) {
			return false;
		}

		require_once __DIR__ . "/../tcpdf/tcpdf.php";

		if (!class_exists('TreinamentoCertificadoMYPDF')) {
			class TreinamentoCertificadoMYPDF extends TCPDF {
				public $custom_header = '';
				public $custom_footer = '';
				public $logo_path = '';
				public $empresa_logo = '';
				public $codigo_autenticacao = '';

				public function Header() {
					// Moldura dupla do certificado
					$this->SetLineWidth(0.8);
					$this->SetDrawColor(44, 106, 134);
					$this->Rect(7, 7, $this->getPageWidth() - 14, $this->getPageHeight() - 14, 'D');
					$this->SetLineWidth(0.3);
					$this->SetDrawColor(120, 170, 200);
					$this->Rect(9.5, 9.5, $this->getPageWidth() - 19, $this->getPageHeight() - 19, 'D');
					$this->SetLineWidth(0.2);
					$this->SetDrawColor(0, 0, 0);

					// Logo do cliente (esquerda) e logo da empresa (direita)
					treinamento_certificado_desenharImagem($this, $this->logo_path, 14, 12, 32, 14, false);
					treinamento_certificado_desenharImagem($this, $this->empresa_logo, $this->getPageWidth() - 14, 12, 32, 14, true);

					// Cabeçalho central (somente quando configurado no tipo de documento)
					if (trim(strval($this->custom_header)) !== '') {
						$this->SetY(13);
						$this->SetFont('helvetica', 'B', 12);
						$this->SetTextColor(44, 106, 134);
						$this->Cell(0, 12, mb_strtoupper($this->custom_header, 'UTF-8'), 0, false, 'C', 0, '', 0, false, 'M', 'M');
						$this->SetTextColor(0, 0, 0);
					}
					$this->SetDrawColor(44, 106, 134);
					$this->SetLineWidth(0.4);
					$this->Line(40, 30, $this->getPageWidth() - 40, 30);
					$this->SetDrawColor(0, 0, 0);
					$this->SetLineWidth(0.2);
				}

				public function Footer() {
					$this->SetFont('helvetica', 'I', 7.5);
					$this->SetDrawColor(180, 180, 180);
					$this->SetLineWidth(0.2);
					$this->Line(20, $this->getPageHeight() - 15, $this->getPageWidth() - 20, $this->getPageHeight() - 15);
					$this->SetDrawColor(0, 0, 0);
					$textoRodape = trim(strval($this->custom_footer));
					if ($textoRodape !== '') {
						$textoRodape .= ' | ';
					}
					if ($this->codigo_autenticacao !== '') {
						$textoRodape .= 'Código de autenticação: ' . $this->codigo_autenticacao . ' | ';
					}
					$this->SetY(-14);
					$this->Cell(0, 4, $textoRodape . 'Gerado em ' . date('d/m/Y H:i:s') . ' | Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
				}
			}
		}

		$pdf = new TreinamentoCertificadoMYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		$pdf->custom_header = trim(strip_tags(strval($tipo['tipo_tx_cabecalho'] ?? '')));
		$pdf->custom_footer = trim(strip_tags(strval($tipo['tipo_tx_rodape'] ?? '')));
		$pdf->logo_path = trim(strval($tipo['tipo_tx_logo'] ?? ''));
		if ($pdf->logo_path === '') {
			$pdf->logo_path = __DIR__ . '/../imagens/logo_topo_cliente.png';
		}
		$pdf->empresa_logo = strval($dados['empresa_logo'] ?? '');
		$pdf->codigo_autenticacao = strval($dados['codigo'] ?? '');

		$pdf->SetCreator(PDF_CREATOR);
		$pdf->SetAuthor('Sistema Braso');
		$pdf->SetTitle('Certificado - ' . strval($dados['titulo'] ?? ''));
		$pdf->SetMargins(15, 35, 15);
		$pdf->SetAutoPageBreak(true, 15);
		$pdf->AddPage();
		$pdf->SetFont('helvetica', '', 10);

		$nome = htmlspecialchars(strval($dados['nome'] ?? ''), ENT_QUOTES, 'UTF-8');
		$cpf = trim(strval($dados['cpf'] ?? ''));
		$titulo = htmlspecialchars(strval($dados['titulo'] ?? ''), ENT_QUOTES, 'UTF-8');
		$empresa = htmlspecialchars(strval($dados['empresa'] ?? ''), ENT_QUOTES, 'UTF-8');
		$matricula = htmlspecialchars(strval($dados['matricula'] ?? ''), ENT_QUOTES, 'UTF-8');
		$carga = htmlspecialchars(strval($dados['carga_label'] ?? ''), ENT_QUOTES, 'UTF-8');
		$conclusao = htmlspecialchars(strval($dados['conclusao_label'] ?? ''), ENT_QUOTES, 'UTF-8');

		$html = '<h1 style="text-align:center;font-size:22pt;letter-spacing:3px;color:#2c6a86;margin-bottom:2px;">CERTIFICADO</h1>';
		$html .= '<p style="text-align:center;font-size:10pt;color:#555;margin-top:0;">Certificado de Conclusão de Treinamento</p>';
		$html .= '<br>';
		$html .= '<p style="text-align:justify;font-size:11pt;line-height:1.5;">';
		$html .= 'Certificamos que <b>' . $nome . '</b>';
		if ($cpf !== '') {
			$html .= ', CPF n° <b>' . htmlspecialchars($cpf, ENT_QUOTES, 'UTF-8') . '</b>';
		}
		if ($matricula !== '') {
			$html .= ', matrícula <b>' . $matricula . '</b>';
		}
		if ($empresa !== '') {
			$html .= ', colaborador(a) da empresa <b>' . $empresa . '</b>';
		}
		$html .= ', concluiu o treinamento <b>' . $titulo . '</b>';
		if ($carga !== '') {
			$html .= ', com carga horária de <b>' . $carga . '</b>';
		}
		$html .= ', em <b>' . $conclusao . '</b>.';
		if (!empty($dados['eh_serie']) && (int)$dados['total_episodios'] > 1) {
			$html .= ' Série composta por <b>' . (int)$dados['total_episodios'] . ' episódios</b>.';
		}
		if ($dados['nota'] !== null) {
			$html .= ' Aproveitamento na avaliação: <b>' . htmlspecialchars(strval($dados['nota']), ENT_QUOTES, 'UTF-8') . '%</b>.';
		}
		$html .= '</p><br>';

		$camposLayout = query(
			"SELECT camp_tx_label, camp_tx_tipo FROM camp_documento_modulo
			 WHERE camp_nb_tipo_doc = ? AND camp_tx_status = 'ativo'
			 ORDER BY camp_nb_ordem ASC, camp_nb_id ASC",
			"i",
			[(int)($tipo['tipo_nb_id'] ?? 0)]
		);
		$valores = [];
		if ($camposLayout instanceof mysqli_result && mysqli_num_rows($camposLayout) > 0) {
			while ($campo = mysqli_fetch_assoc($camposLayout)) {
				$valor = treinamento_certificado_valorCampo($campo['camp_tx_label'] ?? '', $campo['camp_tx_tipo'] ?? '', $dados);
				if (trim(strval($valor)) === '') {
					continue;
				}
				$valores[] = ["label" => $campo['camp_tx_label'], "valor" => $valor];
			}
		}
		if (empty($valores)) {
			$valores = treinamento_certificado_camposPadrao($dados);
		}

		$html .= '<table cellpadding="3" border="0" style="width:100%;">';
		foreach ($valores as $item) {
			$label = htmlspecialchars(strval($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
			$valor = htmlspecialchars(strval($item['valor'] ?? ''), ENT_QUOTES, 'UTF-8');
			if ($label === '' || trim($valor) === '') {
				continue;
			}
			$html .= '<tr>';
			$html .= '<td width="32%" style="border-bottom:0.1pt solid #eee;"><b>' . $label . ':</b></td>';
			$html .= '<td width="68%" style="border-bottom:0.1pt solid #eee;">' . $valor . '</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';

		// Séries: lista cada episódio (título, duração, conclusão e aproveitamento)
		if (!empty($dados['eh_serie']) && !empty($dados['episodios'])) {
			$html .= '<br><h3 style="font-size:12pt;">Episódios do Treinamento</h3>';
			$html .= '<table border="1" cellpadding="3" cellspacing="0" style="width:100%;">';
			$html .= '<thead><tr style="background-color:#f0f0f0;">';
			$html .= '<th width="7%" style="text-align:center;"><b>#</b></th>';
			$html .= '<th width="45%" style="text-align:left;"><b>Episódio</b></th>';
			$html .= '<th width="14%" style="text-align:center;"><b>Duração</b></th>';
			$html .= '<th width="16%" style="text-align:center;"><b>Conclusão</b></th>';
			$html .= '<th width="18%" style="text-align:center;"><b>Aproveitamento</b></th>';
			$html .= '</tr></thead><tbody>';
			foreach ($dados['episodios'] as $idxEp => $ep) {
				$html .= '<tr>';
				$html .= '<td style="text-align:center;">' . ($idxEp + 1) . '</td>';
				$html .= '<td>' . htmlspecialchars(strval($ep['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
				$html .= '<td style="text-align:center;">' . htmlspecialchars(strval($ep['carga_label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
				$html .= '<td style="text-align:center;">' . htmlspecialchars(strval($ep['conclusao_label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
				$html .= '<td style="text-align:center;">' . ($ep['nota'] !== null ? htmlspecialchars(strval($ep['nota']), ENT_QUOTES, 'UTF-8') . '%' : '-') . '</td>';
				$html .= '</tr>';
			}
			$html .= '<tr style="background-color:#f7f7f7;">';
			$html .= '<td colspan="2" style="text-align:right;"><b>Total</b></td>';
			$html .= '<td style="text-align:center;"><b>' . htmlspecialchars(treinamento_certificado_formatarCarga((int)($dados['carga_episodios'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</b></td>';
			$html .= '<td></td>';
			$html .= '<td style="text-align:center;"><b>' . ($dados['nota'] !== null ? htmlspecialchars(strval($dados['nota']), ENT_QUOTES, 'UTF-8') . '%' : '-') . '</b></td>';
			$html .= '</tr>';
			$html .= '</tbody></table>';
		}

		$pdf->writeHTML($html, true, false, true, false, '');

		try {
			$pdf->Output($caminhoAbs, 'F');
		} catch (Throwable $e) {
			return false;
		}
		return file_exists($caminhoAbs);
	}

	function treinamento_certificado_registrarDocumentoFuncionario($entidadeId, $tipoId, $sbgrupoId, $nomeDoc, $descricao, $caminhoRel, $vencimento, $assinado = 'nao') {
		$entidadeId = (int)$entidadeId;
		if ($entidadeId <= 0 || $caminhoRel === '') {
			return 0;
		}

		$existente = mysqli_fetch_assoc(query(
			"SELECT docu_nb_id FROM documento_funcionario WHERE docu_nb_entidade = ? AND docu_tx_caminho = ? LIMIT 1",
			"is",
			[(string)$entidadeId, $caminhoRel]
		));
		if (!empty($existente['docu_nb_id'])) {
			return (int)$existente['docu_nb_id'];
		}

		$dados = [
			"docu_nb_entidade" => (string)$entidadeId,
			"docu_tx_nome" => mb_substr(trim(strval($nomeDoc)), 0, 250, 'UTF-8'),
			"docu_tx_descricao" => $descricao,
			"docu_tx_dataCadastro" => date("Y-m-d H:i:s"),
			"docu_tx_dataVencimento" => $vencimento !== '' ? $vencimento : null,
			"docu_tx_tipo" => (string)(int)$tipoId,
			"docu_nb_sbgrupo" => (int)$sbgrupoId,
			"docu_tx_usuarioCadastro" => 0,
			"docu_tx_assinado" => $assinado === 'sim' ? 'sim' : 'nao',
			"docu_tx_visivel" => "sim",
			"docu_tx_caminho" => $caminhoRel
		];

		$retorno = inserir("documento_funcionario", array_keys($dados), array_values($dados));
		if (is_array($retorno) && isset($retorno[1]) && is_int($retorno[1])) {
			return (int)$retorno[1];
		}
		if (is_array($retorno) && isset($retorno[0]) && is_int($retorno[0])) {
			return (int)$retorno[0];
		}
		if (is_numeric($retorno)) {
			return (int)$retorno;
		}
		return 0;
	}

	function treinamento_certificado_buscarRegistro($treinamentoId, $usuarioId) {
		$res = query(
			"SELECT * FROM treinamento_certificado WHERE trece_nb_treinamento_id = ? AND trece_nb_usuario_id = ? LIMIT 1",
			"ii",
			[(int)$treinamentoId, (int)$usuarioId]
		);
		return ($res instanceof mysqli_result) ? (mysqli_fetch_assoc($res) ?: []) : [];
	}

	function treinamento_certificado_salvarRegistro($treinamentoId, $usuarioId, $dados) {
		global $conn;

		$treinamentoId = (int)$treinamentoId;
		$usuarioId = (int)$usuarioId;
		$existente = treinamento_certificado_buscarRegistro($treinamentoId, $usuarioId);
		if (!empty($existente)) {
			$sets = [];
			$valores = [];
			$tipos = "";
			foreach ($dados as $campo => $valor) {
				if ($valor === null) {
					$sets[] = "{$campo} = NULL";
					continue;
				}
				$sets[] = "{$campo} = ?";
				$valores[] = $valor;
				$tipos .= is_int($valor) ? "i" : "s";
			}
			$valores[] = (int)$existente['trece_nb_id'];
			$tipos .= "i";
			query(
				"UPDATE treinamento_certificado SET " . implode(", ", $sets) . " WHERE trece_nb_id = ?",
				$tipos,
				$valores
			);
			return (int)$existente['trece_nb_id'];
		}

		$dados = array_merge([
			"trece_nb_treinamento_id" => $treinamentoId,
			"trece_nb_usuario_id" => $usuarioId
		], $dados);

		$colunas = [];
		$places = [];
		$valores = [];
		$tipos = "";
		foreach ($dados as $campo => $valor) {
			$colunas[] = $campo;
			if ($valor === null) {
				$places[] = "NULL";
				continue;
			}
			$places[] = "?";
			$valores[] = $valor;
			$tipos .= is_int($valor) ? "i" : "s";
		}
		$sql = "INSERT INTO treinamento_certificado (" . implode(", ", $colunas) . ") VALUES (" . implode(", ", $places) . ")";
		if (!empty($valores)) {
			query($sql, $tipos, $valores);
		} else {
			query($sql);
		}
		$id = (int)mysqli_insert_id($conn);
		if ($id > 0) {
			return $id;
		}
		$novo = treinamento_certificado_buscarRegistro($treinamentoId, $usuarioId);
		return (int)($novo['trece_nb_id'] ?? 0);
	}

	// Atualiza o status local quando a assinatura do certificado é concluída no módulo de assinatura.
	function treinamento_certificado_sincronizar($registro) {
		if (empty($registro) || strval($registro['trece_tx_status'] ?? '') !== 'aguardando_assinatura') {
			return $registro;
		}
		$idSolicitacao = (int)($registro['trece_nb_solicitacao_assinatura'] ?? 0);
		if ($idSolicitacao <= 0) {
			return $registro;
		}
		$solicitacao = mysqli_fetch_assoc(query(
			"SELECT status, status_final FROM solicitacoes_assinatura WHERE id = ? LIMIT 1",
			"i",
			[$idSolicitacao]
		));
		if (empty($solicitacao)) {
			return $registro;
		}
		$status = strtolower(trim(strval($solicitacao['status'] ?? '')));
		$statusFinal = strtolower(trim(strval($solicitacao['status_final'] ?? '')));
		if (in_array($statusFinal, ['finalizado', 'assinado', 'concluido'], true) || in_array($status, ['assinado', 'finalizado', 'concluido'], true)) {
			query(
				"UPDATE treinamento_certificado SET trece_tx_status = 'assinado', trece_dt_data_assinatura = NOW() WHERE trece_nb_id = ?",
				"i",
				[(int)$registro['trece_nb_id']]
			);
			$registro['trece_tx_status'] = 'assinado';
			$registro['trece_dt_data_assinatura'] = date("Y-m-d H:i:s");
		}
		return $registro;
	}

	// Retorna o caminho relativo do arquivo final do certificado (assinado ou não).
	function treinamento_certificado_arquivo($registro) {
		if (empty($registro)) {
			return '';
		}
		$caminho = trim(strval($registro['trece_tx_caminho'] ?? ''));
		if ($caminho !== '' && file_exists(__DIR__ . '/../' . ltrim($caminho, '/'))) {
			return $caminho;
		}

		$entidadeId = (int)($registro['trece_nb_entidade_id'] ?? 0);
		$tipoId = (int)($registro['trece_nb_tipo_documento'] ?? 0);
		if ($entidadeId > 0) {
			$doc = mysqli_fetch_assoc(query(
				"SELECT docu_tx_caminho FROM documento_funcionario
				 WHERE docu_nb_entidade = ? AND (docu_tx_tipo = ? OR ? = 0)
				 ORDER BY docu_nb_id DESC LIMIT 1",
				"isi",
				[(string)$entidadeId, (string)$tipoId, $tipoId]
			));
			if (!empty($doc['docu_tx_caminho'])) {
				return trim(strval($doc['docu_tx_caminho']));
			}
		}

		return $caminho;
	}

	/**
	 * Gera (ou recupera) o certificado do usuário para o treinamento concluído.
	 * - Tipo de documento sem assinatura: salva o PDF em arquivos/Funcionarios/{id}
	 *   e registra em documento_funcionario (aba Documentos do funcionário).
	 * - Tipo de documento com assinatura: envia o PDF para o módulo de assinatura
	 *   (que devolve o arquivo assinado para a mesma pasta ao concluir).
	 */
	function treinamento_certificado_gerar($treinamentoId, $usuarioId, $forcar = false) {
		global $conn;

		$treinamentoId = (int)$treinamentoId;
		$usuarioId = (int)$usuarioId;
		if ($treinamentoId <= 0 || $usuarioId <= 0) {
			return ["ok" => false, "message" => "Treinamento ou usuário inválido."];
		}

		$registro = treinamento_certificado_buscarRegistro($treinamentoId, $usuarioId);
		if (!empty($registro) && !$forcar && strval($registro['trece_tx_status']) !== 'erro') {
			$registro = treinamento_certificado_sincronizar($registro);
			return ["ok" => true, "status" => $registro['trece_tx_status'], "registro" => $registro, "existente" => true];
		}

		if (!treinamento_certificado_estaConcluido($treinamentoId, $usuarioId)) {
			return ["ok" => false, "message" => "Treinamento ainda não concluído."];
		}

		$tipo = treinamento_certificado_buscarTipo();
		if (empty($tipo)) {
			treinamento_certificado_log($treinamentoId, $usuarioId, "certificado_erro", "Tipo de documento 'Certificados' não encontrado/ativo.");
			return ["ok" => false, "message" => "Tipo de documento 'Certificados' não encontrado ou inativo."];
		}

		$dados = treinamento_certificado_dados($treinamentoId, $usuarioId);
		if (empty($dados)) {
			return ["ok" => false, "message" => "Treinamento ou usuário não encontrado."];
		}
		if ((int)$dados['entidade_id'] <= 0) {
			treinamento_certificado_log($treinamentoId, $usuarioId, "certificado_erro", "Usuário sem funcionário (entidade) vinculado.");
			return ["ok" => false, "message" => "Usuário sem funcionário vinculado para salvar o certificado."];
		}

		$tipoId = (int)$tipo['tipo_nb_id'];
		$sbgrupoId = (int)($tipo['tipo_nb_sbgrupo'] ?? 0);
		$requerAssinatura = treinamento_certificado_tipoRequerAssinatura($tipo);
		$vencimento = '';
		if (strtolower(trim(strval($tipo['tipo_tx_vencimento'] ?? 'nao'))) === 'sim' && (int)$dados['dias_validade'] > 0) {
			$vencimento = date("Y-m-d", strtotime($dados['conclusao'] . ' +' . (int)$dados['dias_validade'] . ' days'));
		}

		$nomeArquivo = treinamento_certificado_sanitizarArquivo('Certificado - ' . $dados['titulo'] . ' - ' . $dados['nome']);
		$descricao = 'Certificado de conclusão do treinamento: ' . $dados['titulo'];
		$baseRegistro = [
			"trece_nb_entidade_id" => (int)$dados['entidade_id'],
			"trece_nb_tipo_documento" => $tipoId,
			"trece_tx_nome_arquivo" => $nomeArquivo,
			"trece_tx_codigo_autenticacao" => $dados['codigo'],
			"trece_dt_data_conclusao" => $dados['conclusao']
		];

		if ($requerAssinatura) {
			$tmpDir = __DIR__ . "/../assinatura/uploads/tmp/";
			if (!is_dir($tmpDir)) {
				@mkdir($tmpDir, 0777, true);
			}
			$tmpPdf = rtrim(str_replace("\\", "/", $tmpDir), "/") . "/certificado_" . $treinamentoId . "_" . $usuarioId . "_" . date("YmdHis") . ".pdf";
			if (!treinamento_certificado_gerarPdf($dados, $tipo, $tmpPdf)) {
				treinamento_certificado_salvarRegistro($treinamentoId, $usuarioId, array_merge($baseRegistro, [
					"trece_tx_status" => "erro",
					"trece_tx_detalhe" => "Falha ao gerar o PDF do certificado.",
					"trece_tx_caminho" => null
				]));
				treinamento_certificado_log($treinamentoId, $usuarioId, "certificado_erro", "Falha ao gerar o PDF do certificado.");
				return ["ok" => false, "message" => "Falha ao gerar o PDF do certificado."];
			}

			require_once __DIR__ . "/../assinatura/integracao/assinatura_integracao.php";
			if (!function_exists('assinatura_integracao_enviarDocumentoParaAssinatura')) {
				@unlink($tmpPdf);
				return ["ok" => false, "message" => "Integração de assinatura indisponível."];
			}

			$resultado = assinatura_integracao_enviarDocumentoParaAssinatura($conn, (int)$dados['entidade_id'], $tmpPdf, [
				"tipo_documento_id" => $tipoId,
				"nome_arquivo_original" => $nomeArquivo,
				"validar_icp" => "nao",
				"modo_envio" => "avulso",
				"grupo_envio" => "treinamento_" . $treinamentoId,
				"funcao" => "Participante",
				"salvar_documento_funcionario" => "sim",
				"enviar_email" => (defined('TREINAMENTO_CERTIFICADO_ENVIAR_EMAIL') && TREINAMENTO_CERTIFICADO_ENVIAR_EMAIL === false) ? "nao" : "sim",
				"apagar_origem" => true
			]);

			if (empty($resultado['ok'])) {
				@unlink($tmpPdf);
				$mensagem = strval($resultado['error'] ?? 'Falha ao enviar o certificado para assinatura.');
				treinamento_certificado_salvarRegistro($treinamentoId, $usuarioId, array_merge($baseRegistro, [
					"trece_tx_status" => "erro",
					"trece_tx_detalhe" => $mensagem,
					"trece_tx_caminho" => null
				]));
				treinamento_certificado_log($treinamentoId, $usuarioId, "certificado_erro", "Assinatura: " . $mensagem);
				return ["ok" => false, "message" => $mensagem];
			}

			treinamento_certificado_salvarRegistro($treinamentoId, $usuarioId, array_merge($baseRegistro, [
				"trece_tx_status" => "aguardando_assinatura",
				"trece_tx_detalhe" => "Certificado enviado para assinatura eletrônica.",
				"trece_tx_caminho" => null,
				"trece_nb_solicitacao_assinatura" => (int)($resultado['id_solicitacao'] ?? 0),
				"trece_tx_id_documento" => strval($resultado['id_documento'] ?? '')
			]));
			treinamento_certificado_log($treinamentoId, $usuarioId, "certificado_gerado", "Certificado enviado para assinatura (solicitação #" . (int)($resultado['id_solicitacao'] ?? 0) . ").");
			return ["ok" => true, "status" => "aguardando_assinatura", "message" => "Certificado enviado para assinatura."];
		}

		$dirFinal = __DIR__ . "/../arquivos/Funcionarios/" . (int)$dados['entidade_id'] . "/";
		if (!is_dir($dirFinal)) {
			@mkdir($dirFinal, 0777, true);
		}
		$caminhoAbs = rtrim(str_replace("\\", "/", $dirFinal), "/") . "/" . $nomeArquivo;
		if (file_exists($caminhoAbs)) {
			$info = pathinfo($nomeArquivo);
			$nomeArquivo = $info['filename'] . '_' . date("YmdHis") . '.' . ($info['extension'] ?? 'pdf');
			$caminhoAbs = rtrim(str_replace("\\", "/", $dirFinal), "/") . "/" . $nomeArquivo;
		}

		if (!treinamento_certificado_gerarPdf($dados, $tipo, $caminhoAbs)) {
			treinamento_certificado_salvarRegistro($treinamentoId, $usuarioId, array_merge($baseRegistro, [
				"trece_tx_status" => "erro",
				"trece_tx_detalhe" => "Falha ao gerar o PDF do certificado.",
				"trece_tx_caminho" => null
			]));
			treinamento_certificado_log($treinamentoId, $usuarioId, "certificado_erro", "Falha ao gerar o PDF do certificado.");
			return ["ok" => false, "message" => "Falha ao gerar o PDF do certificado."];
		}

		$caminhoRel = "arquivos/Funcionarios/" . (int)$dados['entidade_id'] . "/" . $nomeArquivo;
		$docuId = treinamento_certificado_registrarDocumentoFuncionario(
			(int)$dados['entidade_id'],
			$tipoId,
			$sbgrupoId,
			'Certificado - ' . $dados['titulo'],
			$descricao,
			$caminhoRel,
			$vencimento,
			"nao"
		);

		treinamento_certificado_salvarRegistro($treinamentoId, $usuarioId, array_merge($baseRegistro, [
			"trece_nb_documento_funcionario" => $docuId,
			"trece_tx_caminho" => $caminhoRel,
			"trece_tx_status" => "gerado",
			"trece_tx_detalhe" => null
		]));
		treinamento_certificado_log($treinamentoId, $usuarioId, "certificado_gerado", "Certificado gerado em " . $caminhoRel . ".");
		return ["ok" => true, "status" => "gerado", "caminho" => $caminhoRel, "message" => "Certificado gerado com sucesso."];
	}
