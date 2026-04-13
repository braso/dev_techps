<?php
	include "../funcoes_ponto.php";
	@mysqli_query($GLOBALS['conn'], "ALTER TABLE solicitacoes_ajuste ADD COLUMN justificativa_gestor TEXT DEFAULT NULL");
	@mysqli_query($GLOBALS['conn'], "ALTER TABLE solicitacoes_ajuste ADD COLUMN id_instancia_documento INT NULL");
	@mysqli_query($GLOBALS['conn'], "ALTER TABLE solicitacoes_ajuste ADD COLUMN data_envio_documento DATETIME NULL");

	function ga_log_runtime(string $mensagem): void {
		$linha = date('Y-m-d H:i:s') . ' | ' . $mensagem . PHP_EOL;
		@file_put_contents(__DIR__ . '/../debug_log_ajustes.txt', $linha, FILE_APPEND);
	}

	function ga_normalizarTextoDocumento(string $texto): string {
		$texto = mb_strtolower(trim($texto), 'UTF-8');
		$mapa = array(
			'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
			'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
			'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
			'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
			'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
			'ç' => 'c'
		);
		$texto = strtr($texto, $mapa);
		$texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
		return trim(strval($texto));
	}

	function ga_formatarDataDocumento(string $valor): string {
		$valor = trim($valor);
		if ($valor === '' || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') {
			return '';
		}
		$ts = strtotime($valor);
		return $ts ? date('d/m/Y', $ts) : $valor;
	}

	function ga_buscarTipoDocumentoAjuste() {
		$res = query("SELECT tipo_nb_id, tipo_tx_nome, tipo_tx_logo FROM tipos_documentos WHERE tipo_tx_status = 'ativo' AND (tipo_tx_nome = 'Comunicação Interna' OR tipo_tx_nome = 'Comunicacao Interna' OR tipo_tx_nome = 'Solicitação de Ajuste' OR tipo_tx_nome = 'Solicitacao de Ajuste' OR tipo_tx_nome = 'Ajuste Ponto' OR tipo_tx_nome LIKE '%Ajuste%Ponto%' OR tipo_tx_nome LIKE '%Solicita%de Ajuste%') ORDER BY CASE WHEN tipo_tx_nome IN ('Comunicação Interna', 'Comunicacao Interna') THEN 1 WHEN tipo_tx_nome LIKE '%Solicita%de Ajuste%' THEN 2 WHEN tipo_tx_nome LIKE '%Ajuste%Ponto%' THEN 3 ELSE 4 END, tipo_nb_id ASC LIMIT 1");
		return ($res instanceof mysqli_result) ? mysqli_fetch_assoc($res) : [];
	}

	function ga_buscarCamposDocumentoAjuste(int $idTipo): array {
		if ($idTipo <= 0) {
			return [];
		}
		$res = query("SELECT * FROM camp_documento_modulo WHERE camp_nb_tipo_doc = {$idTipo} AND camp_tx_status = 'ativo' ORDER BY camp_nb_ordem ASC, camp_nb_id ASC");
		return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
	}

	function ga_buscarSolicitacaoAjuste(int $idSolicitacao): array {
		if ($idSolicitacao <= 0) {
			return [];
		}
		$res = query("SELECT s.*, e.enti_tx_nome AS motorista_nome, e.enti_tx_matricula AS motorista_matricula, e.enti_tx_cpf AS motorista_cpf, e.enti_tx_email AS motorista_email, m.macr_tx_nome, mo.moti_tx_nome, us.user_tx_nome AS solicitante_user_nome, ug.user_tx_nome AS superior_nome FROM solicitacoes_ajuste s JOIN entidade e ON s.id_motorista = e.enti_nb_id LEFT JOIN macroponto m ON s.id_macro = m.macr_nb_id LEFT JOIN motivo mo ON s.id_motivo = mo.moti_nb_id LEFT JOIN user us ON s.id_usuario_solicitante = us.user_nb_id LEFT JOIN user ug ON s.id_superior = ug.user_nb_id WHERE s.id = {$idSolicitacao} LIMIT 1");
		return ($res instanceof mysqli_result) ? (mysqli_fetch_assoc($res) ?: []) : [];
	}

	function ga_buscarEntidadePorUsuario(int $idUsuario): int {
		if ($idUsuario <= 0) {
			return 0;
		}
		$res = query("SELECT user_nb_entidade FROM user WHERE user_nb_id = {$idUsuario} LIMIT 1");
		$row = ($res instanceof mysqli_result) ? mysqli_fetch_assoc($res) : [];
		return intval($row['user_nb_entidade'] ?? 0);
	}

	function ga_colunaExiste(string $tabela, string $coluna): bool {
		$res = query("SHOW COLUMNS FROM `{$tabela}` LIKE '" . mysqli_real_escape_string($GLOBALS['conn'], $coluna) . "'");
		return ($res instanceof mysqli_result) ? mysqli_num_rows($res) > 0 : false;
	}

	function ga_resolverValorCampoAjuste(array $campo, array $solicitacao, string $nomeAprovador = ''): string {
		$label = ga_normalizarTextoDocumento(strval($campo['camp_tx_label'] ?? ''));
		$tipoCampo = ga_normalizarTextoDocumento(strval($campo['camp_tx_tipo'] ?? ''));
		$nomeMotorista = trim(strval($solicitacao['motorista_nome'] ?? ''));
		$matricula = trim(strval($solicitacao['motorista_matricula'] ?? ''));
		$cpf = trim(strval($solicitacao['motorista_cpf'] ?? ''));
		$setor = trim(strval($solicitacao['setor_usuario'] ?? ''));
		$subsetor = trim(strval($solicitacao['subsetor_usuario'] ?? ''));
		$cargo = trim(strval($solicitacao['cargo_usuario'] ?? ''));
		$dataAjuste = ga_formatarDataDocumento(strval($solicitacao['data_ajuste'] ?? ''));
		$dataSolicitacao = ga_formatarDataDocumento(strval($solicitacao['data_solicitacao'] ?? ''));
		$dataDecisao = ga_formatarDataDocumento(strval($solicitacao['data_decisao'] ?? ''));
		$hora = trim(strval($solicitacao['hora_ajuste'] ?? ''));
		$motivo = trim(strval($solicitacao['moti_tx_nome'] ?? ''));
		$macro = trim(strval($solicitacao['macr_tx_nome'] ?? ''));
		$justificativa = trim(strval($solicitacao['justificativa'] ?? ''));
		$solicitante = trim(strval($solicitacao['solicitante_user_nome'] ?? ''));
		$status = 'Aprovado';

		if ($label === '') {
			return '';
		}

		if (strpos($label, 'cpf') !== false) {
			return $cpf;
		}

		if (strpos($label, 'matricula') !== false || strpos($label, 'matr cula') !== false) {
			return $matricula;
		}

		if (strpos($label, 'cargo') !== false) {
			return $cargo;
		}

		if (strpos($label, 'subsetor') !== false) {
			return $subsetor;
		}

		if (strpos($label, 'setor') !== false) {
			return $setor;
		}

		if (strpos($label, 'hora') !== false) {
			return $hora;
		}

		if (strpos($label, 'motivo') !== false) {
			return $motivo;
		}

		if (strpos($label, 'macro') !== false || strpos($label, 'tipo') !== false || strpos($label, 'evento') !== false) {
			return $macro;
		}

		if (strpos($label, 'aprov') !== false || strpos($label, 'gestor') !== false || strpos($label, 'decisor') !== false || strpos($label, 'para') !== false) {
			return $nomeAprovador;
		}

		if (strpos($label, 'emit') !== false || strpos($label, 'criador') !== false || strpos($label, 'solicit') !== false || $label === 'de') {
			return $solicitante !== '' ? $solicitante : $nomeMotorista;
		}

		if (strpos($label, 'nome') !== false || strpos($label, 'funcionario') !== false || strpos($label, 'colaborador') !== false) {
			return $nomeMotorista;
		}

		if (strpos($label, 'data') !== false) {
			if (strpos($label, 'decis') !== false || strpos($label, 'aprov') !== false || strpos($label, 'gestor') !== false) {
				return $dataDecisao !== '' ? $dataDecisao : date('d/m/Y');
			}
			if (strpos($label, 'solicit') !== false || strpos($label, 'envio') !== false || strpos($label, 'cadastro') !== false || strpos($label, 'criacao') !== false) {
				return $dataSolicitacao;
			}
			return $dataAjuste;
		}

		if (strpos($label, 'justific') !== false || strpos($label, 'observ') !== false || strpos($label, 'descricao') !== false || strpos($label, 'detalhe') !== false || strpos($label, 'resumo') !== false || strpos($label, 'obs') !== false) {
			$html = '<table border="1" cellpadding="4" cellspacing="0" style="width:100%;">';
			$html .= '<tr><td width="25%"><b>Funcionário</b></td><td width="75%">' . htmlspecialchars($nomeMotorista, ENT_QUOTES, 'UTF-8') . '</td></tr>';
			$html .= '<tr><td><b>Matrícula</b></td><td>' . htmlspecialchars($matricula, ENT_QUOTES, 'UTF-8') . '</td></tr>';
			$html .= '<tr><td><b>Data/Hora</b></td><td>' . htmlspecialchars(trim($dataAjuste . ' ' . $hora), ENT_QUOTES, 'UTF-8') . '</td></tr>';
			$html .= '<tr><td><b>Tipo</b></td><td>' . htmlspecialchars($macro, ENT_QUOTES, 'UTF-8') . '</td></tr>';
			$html .= '<tr><td><b>Motivo</b></td><td>' . htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8') . '</td></tr>';
			$html .= '<tr><td><b>Justificativa</b></td><td>' . nl2br(htmlspecialchars($justificativa, ENT_QUOTES, 'UTF-8')) . '</td></tr>';
			$html .= '<tr><td><b>Solicitante</b></td><td>' . htmlspecialchars($solicitante, ENT_QUOTES, 'UTF-8') . '</td></tr>';
			$html .= '<tr><td><b>Gestor</b></td><td>' . htmlspecialchars($nomeAprovador, ENT_QUOTES, 'UTF-8') . '</td></tr>';
			$html .= '<tr><td><b>Status</b></td><td>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</td></tr>';
			$html .= '</table>';
			return $html;
		}

		if ($tipoCampo === 'data') {
			return $dataAjuste;
		}

		if ($tipoCampo === 'usuario') {
			return $nomeMotorista;
		}

		return $justificativa;
	}

	function ga_gerarArquivoPdfInstanciaDocumento(int $idInstancia): string {
		$idInstancia = intval($idInstancia);
		if ($idInstancia <= 0) {
			ga_log_runtime('PDF | instancia invalida: ' . $idInstancia);
			return '';
		}

		$resDados = query("SELECT i.*, t.tipo_tx_nome, t.tipo_tx_logo, u.user_tx_nome AS criador_nome FROM inst_documento_modulo i JOIN tipos_documentos t ON t.tipo_nb_id = i.inst_nb_tipo_doc LEFT JOIN user u ON u.user_nb_id = i.inst_nb_user WHERE i.inst_nb_id = {$idInstancia} LIMIT 1");
		$dados = ($resDados instanceof mysqli_result) ? mysqli_fetch_assoc($resDados) : [];
		if (empty($dados)) {
			ga_log_runtime('PDF | instancia nao encontrada: ' . $idInstancia);
			return '';
		}

		$resCampos = query("SELECT v.valo_tx_valor, c.camp_tx_label, c.camp_tx_tipo FROM valo_documento_modulo v JOIN camp_documento_modulo c ON c.camp_nb_id = v.valo_nb_campo WHERE v.valo_nb_instancia = {$idInstancia} ORDER BY c.camp_nb_ordem ASC, c.camp_nb_id ASC");
		$valores = [];
		if ($resCampos instanceof mysqli_result) {
			while ($row = mysqli_fetch_assoc($resCampos)) {
				$valores[] = $row;
			}
		}

		if (empty($valores)) {
			ga_log_runtime('PDF | sem valores para instancia: ' . $idInstancia);
			return '';
		}

		$tcpdfPath = dirname(__DIR__) . '/tcpdf/tcpdf.php';
		if (!file_exists($tcpdfPath)) {
			ga_log_runtime('PDF | tcpdf nao encontrado: ' . $tcpdfPath);
			return '';
		}
		require_once $tcpdfPath;

		if (!class_exists('GA_MYPDF')) {
			class GA_MYPDF extends TCPDF {
				public $custom_header = '';
				public $logo_path = '';

				public function Header() {
					if (!empty($this->logo_path)) {
						$logo = strval($this->logo_path);
						if (strpos($logo, '../') === 0) {
							$logo = realpath(dirname(__DIR__) . '/' . $logo);
						} else {
							$logo = realpath($logo);
						}

						if ($logo && file_exists($logo)) {
							$this->Image($logo, 15, 8, 30, 20, '', '', '', true);
						}
					}

					$this->SetY(15);
					$this->SetFont('helvetica', 'B', 14);
					$this->Cell(0, 15, mb_strtoupper($this->custom_header, 'UTF-8'), 0, false, 'C', 0, '', 0, false, 'M', 'M');
					$this->Line(15, 28, 195, 28);
				}

				public function Footer() {
					$this->SetY(-15);
					$this->SetFont('helvetica', 'I', 8);
					$this->Cell(0, 10, 'Gerado em ' . date('d/m/Y H:i:s') . ' | Pagina ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
				}
			}
		}

		$dirPdf = dirname(__DIR__) . '/assinatura/uploads/tmp';
		if (!is_dir($dirPdf)) {
			@mkdir($dirPdf, 0777, true);
		}

		$nomeArquivo = 'ajuste_' . $idInstancia . '_' . date('YmdHis') . '.pdf';
		$caminhoPdf = rtrim(str_replace('\\', '/', $dirPdf), '/') . '/' . $nomeArquivo;

		$pdf = new GA_MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		$pdf->custom_header = strval($dados['tipo_tx_nome'] ?? 'Ajuste de Ponto');
		$pdf->logo_path = strval($dados['tipo_tx_logo'] ?? '');
		$pdf->SetCreator(PDF_CREATOR);
		$pdf->SetAuthor('Sistema Braso');
		$pdf->SetTitle(strval($dados['tipo_tx_nome'] ?? 'Ajuste de Ponto'));
		$pdf->SetMargins(15, 35, 15);
		$pdf->SetAutoPageBreak(true, 15);
		$pdf->AddPage();
		$pdf->SetFont('helvetica', '', 11);

		$html = '<table cellpadding="3" border="0" style="width:100%;">';
		$html .= '<tr><td style="border-bottom:0.1pt solid #ddd;"><b>Data de Geração:</b> ' . date('d/m/Y H:i') . '</td></tr>';
		$html .= '<tr><td style="border-bottom:0.1pt solid #ddd;"><b>Emitido por:</b> ' . htmlspecialchars(strval($dados['criador_nome'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td></tr>';
		$html .= '</table><br><br>';

		foreach ($valores as $valor) {
			$conteudo = trim(strval($valor['valo_tx_valor'] ?? ''));
			if ($conteudo === '') {
				continue;
			}
			$label = htmlspecialchars(strval($valor['camp_tx_label'] ?? ''), ENT_QUOTES, 'UTF-8');
			if (strpos($conteudo, '<table') !== false) {
				$html .= '<br><b>' . $label . ':</b><br>' . $conteudo . '<br>';
			} else {
				$html .= '<table cellpadding="4" border="0" style="width:100%;">';
				$html .= '<tr>';
				$html .= '<td width="30%" style="border-bottom:0.1pt solid #eee;"><b>' . $label . ':</b></td>';
				$html .= '<td width="70%" style="border-bottom:0.1pt solid #eee;">' . htmlspecialchars($conteudo, ENT_QUOTES, 'UTF-8') . '</td>';
				$html .= '</tr>';
				$html .= '</table>';
			}
		}

		$pdf->writeHTML($html, true, false, true, false, '');
		$pdf->Output($caminhoPdf, 'F');
		ga_log_runtime('PDF | gerado: ' . $caminhoPdf . ' | existe=' . (file_exists($caminhoPdf) ? 'sim' : 'nao'));

		return file_exists($caminhoPdf) ? $caminhoPdf : '';
	}

	function ga_montarSignatariosAjuste(array $solicitacao, int $idUsuarioAprovador): array {
		$signatarios = [];
		$map = [];
		ga_log_runtime('SIGNATARIOS | montando para solicitacao=' . intval($solicitacao['id'] ?? 0) . ' aprovador=' . $idUsuarioAprovador);

		$idMotorista = intval($solicitacao['id_motorista'] ?? 0);
		$idEntAprovador = ga_buscarEntidadePorUsuario($idUsuarioAprovador);

		$itens = [
			['id' => $idMotorista, 'funcao' => 'Solicitante', 'user_id' => intval($solicitacao['id_usuario_solicitante'] ?? 0)],
			['id' => $idEntAprovador, 'funcao' => 'Gestor Aprovador', 'user_id' => $idUsuarioAprovador]
		];

		foreach ($itens as $item) {
			$idEnt = intval($item['id'] ?? 0);
			if ($idEnt <= 0 || isset($map[$idEnt])) {
				ga_log_runtime('SIGNATARIOS | item ignorado id=' . $idEnt . ' funcao=' . strval($item['funcao'] ?? ''));
				continue;
			}
			$signatarios[] = [
				'enti_nb_id' => $idEnt,
				'funcao' => strval($item['funcao'] ?? 'Signatário'),
				'ordem' => 1
			];
			$map[$idEnt] = true;
		}

		ga_log_runtime('SIGNATARIOS | total=' . count($signatarios));
		return $signatarios;
	}

	function ga_enviarAssinaturaAjusteAprovado(int $idSolicitacao, int $idUsuarioAprovador): array {
		$idSolicitacao = intval($idSolicitacao);
		$idUsuarioAprovador = intval($idUsuarioAprovador);
		if ($idSolicitacao <= 0 || $idUsuarioAprovador <= 0) {
			ga_log_runtime('ASSINATURA | parametros invalidos solicitacao=' . $idSolicitacao . ' aprovador=' . $idUsuarioAprovador);
			return ['ok' => false, 'error' => 'Solicitação ou aprovador inválido.'];
		}
		ga_log_runtime('ASSINATURA | inicio solicitacao=' . $idSolicitacao . ' aprovador=' . $idUsuarioAprovador);

		$solicitacao = ga_buscarSolicitacaoAjuste($idSolicitacao);
		if (empty($solicitacao)) {
			ga_log_runtime('ASSINATURA | solicitacao nao encontrada=' . $idSolicitacao);
			return ['ok' => false, 'error' => 'Solicitação de ajuste não encontrada.'];
		}

		$grupoEnvio = 'ajuste_ponto_' . $idSolicitacao;

		$idInstancia = intval($solicitacao['id_instancia_documento'] ?? 0);
		if ($idInstancia <= 0) {
			ga_log_runtime('ASSINATURA | criando instancia nova para solicitacao=' . $idSolicitacao);
			$tipo = ga_buscarTipoDocumentoAjuste();
			$idTipo = intval($tipo['tipo_nb_id'] ?? 0);
			if ($idTipo <= 0) {
				ga_log_runtime('ASSINATURA | nenhum tipo de documento ativo encontrado');
				return ['ok' => false, 'error' => 'Nenhum tipo de documento ativo foi encontrado para o ajuste.'];
			}

			$campos = ga_buscarCamposDocumentoAjuste($idTipo);
			if (empty($campos)) {
				ga_log_runtime('ASSINATURA | nenhum campo ativo encontrado para tipo=' . $idTipo);
				return ['ok' => false, 'error' => 'Nenhum layout ativo foi encontrado para o documento de ajuste.'];
			}

			$idUsuarioCriador = intval($solicitacao['id_usuario_solicitante'] ?? $idUsuarioAprovador);
			$sqlInstancia = "INSERT INTO inst_documento_modulo (inst_nb_tipo_doc, inst_nb_user, inst_dt_criacao, inst_tx_status) VALUES ({$idTipo}, {$idUsuarioCriador}, NOW(), 'ativo')";
			if (mysqli_query($GLOBALS['conn'], $sqlInstancia)) {
				$idInstancia = intval(mysqli_insert_id($GLOBALS['conn']));
				if ($idInstancia > 0 && ga_colunaExiste('inst_documento_modulo', 'inst_nb_entidade') && ga_colunaExiste('inst_documento_modulo', 'inst_tx_data_referencia')) {
					$dataReferencia = mysqli_real_escape_string($GLOBALS['conn'], strval($solicitacao['data_ajuste'] ?? date('Y-m-d')));
					$idEntidadeDocumento = intval($solicitacao['id_motorista'] ?? 0);
					query("UPDATE inst_documento_modulo SET inst_nb_entidade = {$idEntidadeDocumento}, inst_tx_data_referencia = '{$dataReferencia}' WHERE inst_nb_id = {$idInstancia} LIMIT 1");
				}
			} else {
				ga_log_runtime('ASSINATURA | erro insert instancia: ' . mysqli_error($GLOBALS['conn']));
			}

			if ($idInstancia <= 0) {
				ga_log_runtime('ASSINATURA | falha ao criar instancia para solicitacao=' . $idSolicitacao);
				return ['ok' => false, 'error' => 'Falha ao criar a instância do documento.'];
			}
			ga_log_runtime('ASSINATURA | instancia criada=' . $idInstancia . ' tipo=' . $idTipo);

			$nomeAprovador = trim(strval($solicitacao['superior_nome'] ?? ''));
			if ($nomeAprovador === '') {
				$resAprovador = query("SELECT user_tx_nome FROM user WHERE user_nb_id = {$idUsuarioAprovador} LIMIT 1");
				$dadosAprovador = ($resAprovador instanceof mysqli_result) ? mysqli_fetch_assoc($resAprovador) : [];
				$nomeAprovador = trim(strval($dadosAprovador['user_tx_nome'] ?? ''));
			}

			foreach ($campos as $campo) {
				$valor = ga_resolverValorCampoAjuste($campo, $solicitacao, $nomeAprovador);
				if ($valor === '') {
					continue;
				}
				$dadosValor = array(
					'valo_nb_instancia' => $idInstancia,
					'valo_nb_campo' => intval($campo['camp_nb_id'] ?? 0),
					'valo_tx_valor' => strval($valor),
					'valo_tx_status' => 'ativo'
				);
				inserir('valo_documento_modulo', array_keys($dadosValor), array_values($dadosValor));
			}

			query("UPDATE solicitacoes_ajuste SET id_instancia_documento = {$idInstancia} WHERE id = {$idSolicitacao} LIMIT 1");
			ga_log_runtime('ASSINATURA | instancia vinculada a solicitacao=' . $idSolicitacao . ' inst=' . $idInstancia);
		}

		$arquivoPdf = ga_gerarArquivoPdfInstanciaDocumento($idInstancia);
		if ($arquivoPdf === '') {
			ga_log_runtime('ASSINATURA | falha ao gerar pdf inst=' . $idInstancia);
			return ['ok' => false, 'error' => 'Não foi possível gerar o PDF do ajuste.'];
		}
		ga_log_runtime('ASSINATURA | pdf pronto=' . $arquivoPdf);

		require_once dirname(__DIR__) . '/assinatura/integracao/assinatura_integracao.php';
		if (!function_exists('assinatura_integracao_enviarDocumentoParaMultiplosAssinantes')) {
			return ['ok' => false, 'error' => 'Integração de assinatura indisponível.'];
		}

		$signatarios = ga_montarSignatariosAjuste($solicitacao, $idUsuarioAprovador);
		if (count($signatarios) < 2) {
			ga_log_runtime('ASSINATURA | signatarios insuficientes=' . count($signatarios));
			return ['ok' => false, 'error' => 'Não foi possível identificar os signatários do ajuste.'];
		}
		ga_log_runtime('ASSINATURA | enviando para integracao com ' . count($signatarios) . ' signatarios');

		$resAprovador = assinatura_integracao_enviarDocumentoParaMultiplosAssinantes(
			$GLOBALS['conn'],
			$arquivoPdf,
			$signatarios,
			array(
				'nome_arquivo_original' => 'ajuste_ponto_' . $idSolicitacao . '.pdf',
				'id_documento' => 'INST_' . $idInstancia,
				'grupo_envio' => $grupoEnvio,
				'modo_envio' => 'avulso',
				'validar_icp' => 'sim',
				'enviar_email' => 'nao',
				'salvar_documento_funcionario' => 'sim',
				'apagar_origem' => true
			)
		);

		if (empty($resAprovador['ok'])) {
			ga_log_runtime('ASSINATURA | integracao retornou erro=' . strval($resAprovador['error'] ?? 'sem detalhe'));
			return array_merge(array('ok' => false), $resAprovador);
		}
		ga_log_runtime('ASSINATURA | integracao ok solicitacao_assinatura=' . strval($resAprovador['id_solicitacao'] ?? ''));

		return array_merge(array('ok' => true, 'id_instancia_documento' => $idInstancia), $resAprovador);
	}
	
	function ensureSolicitacoesAjusteTable(){
		query("CREATE TABLE IF NOT EXISTS solicitacoes_ajuste (
			id INT AUTO_INCREMENT PRIMARY KEY,
			id_motorista INT NOT NULL,
			data_ajuste DATE NOT NULL,
			hora_ajuste TIME NOT NULL,
			id_macro INT NOT NULL,
			id_motivo INT NOT NULL,
			justificativa TEXT NULL,
			status VARCHAR(20) DEFAULT 'rascunho',
			data_solicitacao DATETIME NOT NULL,
			id_usuario_solicitante INT NOT NULL,
			cargo_usuario VARCHAR(100) NULL,
			setor_usuario VARCHAR(100) NULL,
			subsetor_usuario VARCHAR(100) NULL,
			data_decisao DATETIME NULL,
			id_superior INT NULL,
			justificativa_gestor TEXT NULL,
			data_visualizacao DATETIME NULL,
			id_instancia_documento INT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
		
		$check = query("SHOW COLUMNS FROM solicitacoes_ajuste LIKE 'id_instancia_documento'");
		if ($check instanceof mysqli_result && mysqli_num_rows($check) == 0) {
			@query("ALTER TABLE solicitacoes_ajuste ADD COLUMN id_instancia_documento INT NULL AFTER data_decisao");
		}
		
		$check = query("SHOW COLUMNS FROM solicitacoes_ajuste LIKE 'justificativa_gestor'");
		if ($check instanceof mysqli_result && mysqli_num_rows($check) == 0) {
			@query("ALTER TABLE solicitacoes_ajuste ADD COLUMN justificativa_gestor TEXT DEFAULT NULL AFTER id_instancia_documento");
		}
		
		$check = query("SHOW COLUMNS FROM solicitacoes_ajuste LIKE 'data_visualizacao'");
		if ($check instanceof mysqli_result && mysqli_num_rows($check) == 0) {
			@query("ALTER TABLE solicitacoes_ajuste ADD COLUMN data_visualizacao DATETIME NULL AFTER justificativa_gestor");
		}
	}
	ensureSolicitacoesAjusteTable();

	function buscarSubsetores(){
		$setorRaw = strval($_POST['setor'] ?? '');
		$setores = array_values(array_filter(array_map('trim', explode(',', $setorRaw)), function($v){ return $v !== ''; }));
		$setoresSql = array_map(function($v){
			return "'" . mysqli_real_escape_string($GLOBALS['conn'], $v) . "'";
		}, $setores);

		$where = "subsetor_usuario IS NOT NULL AND subsetor_usuario != '' AND subsetor_usuario != 'N/A'";
		if (!empty($setoresSql)) {
			$where .= " AND setor_usuario IN (" . implode(',', $setoresSql) . ")";
		}

		$res = query("SELECT DISTINCT subsetor_usuario FROM solicitacoes_ajuste WHERE {$where} ORDER BY subsetor_usuario");
		$subsetores = ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
		
		$retorno = [];
		foreach($subsetores as $sub){
			$retorno[] = $sub['subsetor_usuario'];
		}
		
		echo json_encode($retorno);
		exit;
	}

	function verificarPontoExistenteSolicitacao(){
		header('Content-Type: application/json; charset=utf-8');

		try {
			$idSolicitacao = intval($_POST['id_solicitacao'] ?? 0);
			if ($idSolicitacao <= 0) {
				echo json_encode(['existe' => false]);
				exit;
			}

			$resSol = query("
				SELECT s.*, e.enti_tx_matricula
				FROM solicitacoes_ajuste s
				JOIN entidade e ON s.id_motorista = e.enti_nb_id
				WHERE s.id = {$idSolicitacao}
				LIMIT 1
			");
			$sol = ($resSol instanceof mysqli_result) ? mysqli_fetch_assoc($resSol) : null;
			if (empty($sol['enti_tx_matricula']) || empty($sol['data_ajuste']) || empty($sol['id_macro'])) {
				echo json_encode(['existe' => false]);
				exit;
			}

			$idMacro = intval($sol['id_macro']);
			$resMacro = query("
				SELECT macr_tx_nome
				FROM macroponto
				WHERE macr_tx_status = 'ativo'
					AND macr_nb_id = {$idMacro}
				LIMIT 1
			");
			$macro = ($resMacro instanceof mysqli_result) ? mysqli_fetch_assoc($resMacro) : [];

			$matricula = mysqli_real_escape_string($GLOBALS['conn'], $sol['enti_tx_matricula']);
			$data = mysqli_real_escape_string($GLOBALS['conn'], $sol['data_ajuste']);

			$resPonto = query("
				SELECT p.pont_tx_data
				FROM ponto p
				JOIN macroponto m ON (
					p.pont_tx_tipo = m.macr_tx_codigoInterno
					OR p.pont_tx_tipo = m.macr_nb_id
					OR p.pont_tx_tipo = m.macr_tx_codigoExterno
					OR p.pont_tx_tipoOriginal = m.macr_tx_codigoExterno
				)
				WHERE p.pont_tx_status = 'ativo'
					AND m.macr_tx_status = 'ativo'
					AND p.pont_tx_matricula = '{$matricula}'
					AND p.pont_tx_data LIKE '{$data}%'
					AND m.macr_nb_id = {$idMacro}
				ORDER BY p.pont_tx_data DESC
				LIMIT 1
			");
			$ponto = ($resPonto instanceof mysqli_result) ? mysqli_fetch_assoc($resPonto) : [];

			if (!empty($ponto['pont_tx_data'])) {
				echo json_encode([
					'existe' => true,
					'tipo' => ($macro['macr_tx_nome'] ?? ''),
					'hora' => date('H:i', strtotime($ponto['pont_tx_data']))
				]);
				exit;
			}

			echo json_encode(['existe' => false]);
			exit;
		} catch (Throwable $e) {
			echo json_encode(['existe' => false]);
			exit;
		}
	}

	function verificarPontoExistenteLote(){
		header('Content-Type: application/json; charset=utf-8');
		try {
			$idsRaw = strval($_POST['ids'] ?? '');
			$ids = array_filter(array_map('intval', explode(',', $idsRaw)));
			if (empty($ids)) {
				echo json_encode(['existe' => false, 'qtd' => 0]);
				exit;
			}

			$qtd = 0;
			foreach ($ids as $idSolicitacao) {
				$resSol = query("
					SELECT s.*, e.enti_tx_matricula
					FROM solicitacoes_ajuste s
					JOIN entidade e ON s.id_motorista = e.enti_nb_id
					WHERE s.id = {$idSolicitacao}
					LIMIT 1
				");
				$sol = ($resSol instanceof mysqli_result) ? mysqli_fetch_assoc($resSol) : null;
				if (empty($sol['enti_tx_matricula']) || empty($sol['data_ajuste']) || empty($sol['id_macro'])) {
					continue;
				}

				$idMacro = intval($sol['id_macro']);
				$matricula = mysqli_real_escape_string($GLOBALS['conn'], $sol['enti_tx_matricula']);
				$data = mysqli_real_escape_string($GLOBALS['conn'], $sol['data_ajuste']);

				$resPonto = query("
					SELECT p.pont_tx_data
					FROM ponto p
					JOIN macroponto m ON (
						p.pont_tx_tipo = m.macr_tx_codigoInterno
						OR p.pont_tx_tipo = m.macr_nb_id
						OR p.pont_tx_tipo = m.macr_tx_codigoExterno
						OR p.pont_tx_tipoOriginal = m.macr_tx_codigoExterno
					)
					WHERE p.pont_tx_status = 'ativo'
						AND m.macr_tx_status = 'ativo'
						AND p.pont_tx_matricula = '{$matricula}'
						AND p.pont_tx_data LIKE '{$data}%'
						AND m.macr_nb_id = {$idMacro}
					ORDER BY p.pont_tx_data DESC
					LIMIT 1
				");
				$ponto = ($resPonto instanceof mysqli_result) ? mysqli_fetch_assoc($resPonto) : [];
				if (!empty($ponto['pont_tx_data'])) {
					$qtd++;
				}
			}

			echo json_encode(['existe' => ($qtd > 0), 'qtd' => $qtd]);
			exit;
		} catch (Throwable $e) {
			echo json_encode(['existe' => false, 'qtd' => 0]);
			exit;
		}
	}

	function aceitarSolicitacao($id = null){
		include_once "../check_permission.php";
		verificaPermissao('/telas/gerenciar_ajustes.php');

		$emLote = ($id !== null);
		$idSolicitacao = $emLote ? intval($id) : intval($_POST['id_solicitacao'] ?? 0);
		if ($idSolicitacao <= 0) {
			if(!$emLote) set_status("ERRO: Solicitação inválida.");
			if(!$emLote){ index(); exit; }
			return false;
		}
		
		$resSol = query("
			SELECT s.*, e.enti_tx_matricula 
			FROM solicitacoes_ajuste s 
			JOIN entidade e ON s.id_motorista = e.enti_nb_id 
			WHERE s.id = '$idSolicitacao' 
			LIMIT 1
		");
		$sol = ($resSol instanceof mysqli_result) ? mysqli_fetch_assoc($resSol) : null;

		if (!$sol) {
			if(!$emLote) set_status("ERRO: Solicitação não encontrada.");
			if(!$emLote){ index(); exit; }
			return false;
		}

		if ($sol['status'] === 'aceita' || $sol['status'] === 'nao_aceita') {
			if(!$emLote) set_status("ERRO: Esta solicitação já foi processada.");
			if(!$emLote){ index(); exit; }
			return false;
		}

		try {
			$permitirSubstituir = (($_POST['permitir_substituir'] ?? '') === '1');

			$idMacro = intval($sol['id_macro'] ?? 0);
			$dataDia = mysqli_real_escape_string($GLOBALS['conn'], $sol['data_ajuste']);
			$matricula = mysqli_real_escape_string($GLOBALS['conn'], $sol['enti_tx_matricula']);

			$resExistente = query("
				SELECT p.pont_tx_data
				FROM ponto p
				JOIN macroponto m ON (
					p.pont_tx_tipo = m.macr_tx_codigoInterno
					OR p.pont_tx_tipo = m.macr_nb_id
					OR p.pont_tx_tipo = m.macr_tx_codigoExterno
					OR p.pont_tx_tipoOriginal = m.macr_tx_codigoExterno
				)
				WHERE p.pont_tx_status = 'ativo'
					AND m.macr_tx_status = 'ativo'
					AND p.pont_tx_matricula = '{$matricula}'
					AND p.pont_tx_data LIKE '{$dataDia}%'
					AND m.macr_nb_id = {$idMacro}
				ORDER BY p.pont_tx_data DESC
				LIMIT 1
			");
			$pontoExistente = ($resExistente instanceof mysqli_result) ? mysqli_fetch_assoc($resExistente) : [];

			if (!empty($pontoExistente['pont_tx_data']) && !$permitirSubstituir) {
				if(!$id) set_status("ERRO: Já existe registro lançado. Confirme para substituir.");
				if(!$id){ index(); exit; }
				return false;
			}

			if (!empty($pontoExistente['pont_tx_data']) && $permitirSubstituir) {
				$resMacro = query("
					SELECT macr_tx_codigoInterno, macr_tx_codigoExterno
					FROM macroponto
					WHERE macr_tx_status = 'ativo'
						AND macr_nb_id = {$idMacro}
					LIMIT 1
				");
				$macro = ($resMacro instanceof mysqli_result) ? mysqli_fetch_assoc($resMacro) : [];
				$codigoInterno = mysqli_real_escape_string($GLOBALS['conn'], $macro['macr_tx_codigoInterno'] ?? '');
				$codigoExterno = mysqli_real_escape_string($GLOBALS['conn'], $macro['macr_tx_codigoExterno'] ?? '');

				query("UPDATE ponto SET pont_tx_status = 'inativo'
					WHERE pont_tx_status = 'ativo'
						AND pont_tx_matricula = '{$matricula}'
						AND pont_tx_data LIKE '{$dataDia}%'
						AND (
							pont_tx_tipo = '{$codigoInterno}'
							OR pont_tx_tipo = '{$idMacro}'
							OR pont_tx_tipo = '{$codigoExterno}'
							OR pont_tx_tipoOriginal = '{$codigoExterno}'
						)");
			}

			$dataPonto = new DateTime($sol['data_ajuste'] . ' ' . $sol['hora_ajuste']);
			try {
				$newPonto = conferirErroPonto($sol['enti_tx_matricula'], $dataPonto, intval($sol['id_macro']), intval($sol['id_motivo']), $sol['justificativa']);
			} catch (Throwable $eConferir) {
				$msg = $eConferir->getMessage();
				if ($permitirSubstituir && is_int(stripos($msg, "Jornada aberta já existente"))) {
					$dataRetry = mysqli_real_escape_string($GLOBALS['conn'], $dataPonto->format("Y-m-d H:i:s"));
					$resJornada = query("
						SELECT pont_nb_id, pont_tx_tipo
						FROM ponto
						WHERE pont_tx_status = 'ativo'
							AND pont_tx_matricula = '{$matricula}'
							AND pont_tx_tipo IN ('1', '2')
							AND pont_tx_data <= STR_TO_DATE('{$dataRetry}', '%Y-%m-%d %H:%i:%s')
						ORDER BY pont_tx_data DESC, pont_nb_id DESC
						LIMIT 1
					");
					$ultimaJornada = ($resJornada instanceof mysqli_result) ? mysqli_fetch_assoc($resJornada) : [];

					if (!empty($ultimaJornada['pont_nb_id']) && strval($ultimaJornada['pont_tx_tipo']) === '1') {
						$idPontoInativar = intval($ultimaJornada['pont_nb_id']);
						if ($idPontoInativar > 0) {
							query("UPDATE ponto SET pont_tx_status = 'inativo' WHERE pont_nb_id = {$idPontoInativar}");
						}
						$newPonto = conferirErroPonto($sol['enti_tx_matricula'], $dataPonto, intval($sol['id_macro']), intval($sol['id_motivo']), $sol['justificativa']);
					} else {
						throw $eConferir;
					}
				} else {
					throw $eConferir;
				}
			}
			
			$resTemPonto = query("
				SELECT pont_tx_data FROM ponto
				WHERE pont_tx_status = 'ativo'
					AND pont_tx_matricula = '{$sol['enti_tx_matricula']}'
					AND STR_TO_DATE(pont_tx_data, '%Y-%m-%d %H:%i') = STR_TO_DATE('{$sol['data_ajuste']} {$sol['hora_ajuste']}', '%Y-%m-%d %H:%i')
				ORDER BY pont_tx_data DESC
				LIMIT 1
			");
			$temPonto = ($resTemPonto instanceof mysqli_result) ? mysqli_fetch_assoc($resTemPonto) : [];

			if (!empty($temPonto["pont_tx_data"])) {
				query("UPDATE ponto SET pont_tx_status = 'inativo' 
					   WHERE pont_tx_status = 'ativo'
					   AND pont_tx_matricula = '{$sol['enti_tx_matricula']}'
					   AND STR_TO_DATE(pont_tx_data, '%Y-%m-%d %H:%i') = STR_TO_DATE('{$sol['data_ajuste']} {$sol['hora_ajuste']}', '%Y-%m-%d %H:%i')");
			}

			inserir("ponto", array_keys($newPonto), array_values($newPonto));

			$justificativaGestor = mysqli_real_escape_string($GLOBALS['conn'], $_POST['justificativa_gestor'] ?? '');
			query("UPDATE solicitacoes_ajuste SET status = 'aceita', data_decisao = NOW(), id_superior = {$_SESSION['user_nb_id']}, justificativa_gestor = '{$justificativaGestor}' WHERE id = '$idSolicitacao'");

			$assinaturaAjuste = ga_enviarAssinaturaAjusteAprovado($idSolicitacao, intval($_SESSION['user_nb_id'] ?? 0));
			if (empty($assinaturaAjuste['ok'])) {
				ga_log_runtime('APROVACAO | erro ao enviar para assinatura solicitacao=' . $idSolicitacao . ' msg=' . strval($assinaturaAjuste['error'] ?? 'desconhecido'));
				error_log('Ajuste Ponto: falha ao enviar solicitacao ' . $idSolicitacao . ' para assinatura: ' . strval($assinaturaAjuste['error'] ?? 'erro desconhecido'));
			} else {
				ga_log_runtime('APROVACAO | assinatura encaminhada solicitacao=' . $idSolicitacao . ' id_instancia=' . strval($assinaturaAjuste['id_instancia_documento'] ?? ''));
			}

			if(!$emLote){
				$params = [
					"msg_status" => "Solicitação aprovada e ponto registrado com sucesso!",
					"motorista" => ($_POST["motorista"] ?? ""),
					"status_filtro" => ($_POST["status_filtro"] ?? "pendentes"),
					"cargo_filtro" => ($_POST["cargo_filtro"] ?? ""),
					"setor_filtro" => ($_POST["setor_filtro"] ?? ""),
					"subsetor_filtro" => ($_POST["subsetor_filtro"] ?? "")
				];
				header("Location: " . basename($_SERVER["PHP_SELF"]) . "?" . http_build_query($params));
				exit;
			}
			return true;
		} catch (Throwable $e) {
			if(!$emLote){
				$params = [
					"msg_status" => "ERRO: " . $e->getMessage(),
					"motorista" => ($_POST["motorista"] ?? ""),
					"status_filtro" => ($_POST["status_filtro"] ?? "pendentes"),
					"cargo_filtro" => ($_POST["cargo_filtro"] ?? ""),
					"setor_filtro" => ($_POST["setor_filtro"] ?? ""),
					"subsetor_filtro" => ($_POST["subsetor_filtro"] ?? "")
				];
				header("Location: " . basename($_SERVER["PHP_SELF"]) . "?" . http_build_query($params));
				exit;
			}
			return false;
		}
	}

	function rejeitarSolicitacao($id = null){
		include_once "../check_permission.php";
		verificaPermissao('/telas/gerenciar_ajustes.php');

		$emLote = ($id !== null);
		$idSolicitacao = $emLote ? intval($id) : intval($_POST['id_solicitacao'] ?? 0);
		if ($idSolicitacao <= 0) {
			if(!$emLote) set_status("ERRO: Solicitação inválida.");
			if(!$emLote){ index(); exit; }
			return false;
		}
		$justificativaGestor = trim($_POST['justificativa_gestor'] ?? '');

if (empty($justificativaGestor)) {
			if(!$emLote) set_status("ERRO: A justificativa é obrigatória para rejeitar a solicitação.");
			if(!$emLote){ index(); exit; }
			return false;
		}

		$justificativaGestor = mysqli_real_escape_string($GLOBALS['conn'], $justificativaGestor);
		query("UPDATE solicitacoes_ajuste SET status = 'nao_aceita', data_decisao = NOW(), id_superior = {$_SESSION['user_nb_id']}, justificativa_gestor = '{$justificativaGestor}' WHERE id = '$idSolicitacao'");
		if(!$emLote){
			$params = [
				"msg_status" => "Solicitação rejeitada com sucesso.",
				"motorista" => ($_POST["motorista"] ?? ""),
				"status_filtro" => ($_POST["status_filtro"] ?? "pendentes"),
				"cargo_filtro" => ($_POST["cargo_filtro"] ?? ""),
				"setor_filtro" => ($_POST["setor_filtro"] ?? ""),
				"subsetor_filtro" => ($_POST["subsetor_filtro"] ?? "")
			];
			header("Location: " . basename($_SERVER["PHP_SELF"]) . "?" . http_build_query($params));
			exit;
		}
		return true;
	}

	function processarEmLote(){
		include_once "../check_permission.php";
		verificaPermissao('/telas/gerenciar_ajustes.php');

		$ids = $_POST['ids_selecionados'] ?? '';
		$acaoLote = $_POST['acao_lote'] ?? '';
		
		if (empty($ids)) {
			set_status("ERRO: Nenhuma solicitação selecionada.");
			index();
			exit;
		}

		$arrIds = explode(',', $ids);
		$sucesso = 0;
		$erro = 0;

		foreach ($arrIds as $id) {
			$id = intval(trim($id));
			if ($id <= 0) {
				$erro++;
				continue;
			}
			$_POST['permitir_substituir'] = ($_POST['permitir_substituir'] ?? '0');
			try {
				if ($acaoLote == 'aceitarLote') {
					if (aceitarSolicitacao($id)) $sucesso++; else $erro++;
				} elseif ($acaoLote == 'rejeitarLote') {
					if (rejeitarSolicitacao($id)) $sucesso++; else $erro++;
				}
			} catch (Throwable $e) {
				$erro++;
			}
		}

		$msg = "$sucesso solicitações processadas com sucesso.";
		if ($erro > 0) $msg .= " $erro falhas encontradas.";
		$params = [
			"msg_status" => $msg,
			"motorista" => ($_POST["motorista"] ?? ""),
			"status_filtro" => ($_POST["status_filtro"] ?? "pendentes"),
			"cargo_filtro" => ($_POST["cargo_filtro"] ?? ""),
			"setor_filtro" => ($_POST["setor_filtro"] ?? ""),
			"subsetor_filtro" => ($_POST["subsetor_filtro"] ?? "")
		];
		header("Location: " . basename($_SERVER["PHP_SELF"]) . "?" . http_build_query($params));
		exit;
	}

	function montarMultiSelectFiltro($label, $nome, array $selecionados, array $opcoes, int $tamanho, string $id, bool $disabled = false){
		$idEsc = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
		$nomeEsc = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
		$labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

		$selecionadosMap = [];
		foreach($selecionados as $v){
			$selecionadosMap[strval($v)] = true;
		}

		$hiddenVal = htmlspecialchars(implode(',', $selecionados), ENT_QUOTES, 'UTF-8');
		$countTxt = empty($selecionados) ? "Todos" : (count($selecionados) . " selecionados");
		$btnDisabled = $disabled ? "disabled" : "";

		$html = "<div class='col-sm-$tamanho margin-bottom-5'>
			<label>{$labelEsc}</label>
			<div class='ms' id='{$idEsc}' style='position:relative;'>
				<input type='hidden' class='ms-hidden' name='{$nomeEsc}' value='{$hiddenVal}'>
				<button type='button' class='form-control input-sm ms-btn' {$btnDisabled} style='width:100%; text-align:left; padding-right:26px; cursor:pointer; position:relative;'>
					<span class='ms-count'>{$countTxt}</span>
					<span class='ms-caret' style='position:absolute; right:10px; top:50%; transform:translateY(-50%); border-top:4px solid #555; border-left:4px solid transparent; border-right:4px solid transparent; height:0; width:0;'></span>
				</button>
				<ul class='ms-menu' style='display:none; position:absolute; left:0; right:0; top:100%; z-index:9999; width:100%; max-height:260px; overflow:auto; padding:6px 10px; margin:2px 0 0 0; background:#fff; border:1px solid #ccc; border-radius:3px; list-style:none;'>
				";

				$html .= "
					<li style='margin-bottom:6px; border-bottom:1px solid #eee; padding-bottom:4px;'>
						<button type='button' class='ms-clear'
							style='background:none; border:none; color:#d9534f; cursor:pointer; font-size:12px; padding:0;'>
							Limpar seleção
						</button>
					</li>
				";

		$temOpcao = false;
		foreach($opcoes as $i => $opt){
			$val = strval($opt['value'] ?? '');
			$lab = strval($opt['label'] ?? $val);
			if($val === '') continue;
			$temOpcao = true;
			$valEsc = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
			$labEsc = htmlspecialchars($lab, ENT_QUOTES, 'UTF-8');
			$checked = !empty($selecionadosMap[$val]) ? "checked" : "";
			$cbId = $idEsc . "_opt_" . $i;
			$html .= "<li style='margin:2px 0;'>
				<label for='{$cbId}' style='display:flex; align-items:center; gap:6px; font-weight:normal; margin:0; cursor:pointer;'>
					<input id='{$cbId}' type='checkbox' value='{$valEsc}' {$checked} style='width:14px; height:14px; margin:0;'>
					<span>{$labEsc}</span>
				</label>
			</li>";
		}

		if(!$temOpcao){
			$html .= "<li style='margin:2px 0; color:#777;'>Nenhum item</li>";
		}

		$html .= "</ul></div></div>";
		return $html;
	}

	function index(){
		include_once "../check_permission.php";
		verificaPermissao('/telas/gerenciar_ajustes.php');

		// Mesclar GET e POST para manter o estado do filtro
		$filtros = array_merge($_GET, $_POST);

		if (!empty($filtros['msg_status'])) {
			set_status(urldecode($filtros['msg_status']));
		}

		cabecalho("Gerenciar Ajustes de Ponto");

		$listaTexto = fn($v) => array_values(array_filter(array_map('trim', explode(',', strval($v ?? ''))), fn($el) => $el !== ''));
		$listaInt = fn($v) => array_values(array_unique(array_filter(array_map('intval', explode(',', strval($v ?? ''))), fn($el) => $el > 0)));

		// LÓGICA DE HIERARQUIA
		$idUsuarioLogado = $_SESSION['user_nb_id'] ?? null;
		$sql_perfil = "SELECT op.oper_tx_nome as cargo, gs.grup_tx_nome as setor FROM user u LEFT JOIN entidade e ON u.user_nb_entidade = e.enti_nb_id LEFT JOIN operacao op ON e.enti_tx_tipoOperacao = op.oper_nb_id LEFT JOIN grupos_documentos gs ON e.enti_setor_id = gs.grup_nb_id WHERE u.user_nb_id = {$idUsuarioLogado} LIMIT 1";
		$res_perfil = query($sql_perfil);
		$perfilUsuarioLogado = ($res_perfil instanceof mysqli_result) ? mysqli_fetch_assoc($res_perfil) : null;

		$regras_path = '../gestores/fluxo_aprovacao.php';
		$regras = file_exists($regras_path) ? include $regras_path : [];
		
	
		$condicoes_subsetor = [];
		$condicoes_setor = [];
		if (is_array($regras) && !empty($regras) && $perfilUsuarioLogado) {
			foreach ($regras as $regra) {
				$aprovadorDaRegra = $regra['aprovador'];
				$solicitanteDaRegra = $regra['solicitante'];
				$perfilCargo = trim($perfilUsuarioLogado['cargo'] ?? '');
				$perfilSetor = trim($perfilUsuarioLogado['setor'] ?? '');
				$regraCargo = trim($aprovadorDaRegra['cargo'] ?? '');
				$regraSetor = isset($aprovadorDaRegra['setor']) ? trim($aprovadorDaRegra['setor']) : null;
			
				
				if (($regraCargo == $perfilCargo) && ($regraSetor === null || $regraSetor == $perfilSetor)) {
					$condicao = [
						"TRIM(s.cargo_usuario) = '" . mysqli_real_escape_string($GLOBALS['conn'], trim($solicitanteDaRegra['cargo'])) . "'"
					];

					if (isset($solicitanteDaRegra['setor'])) {
						$condicao[] = "TRIM(s.setor_usuario) = '" . mysqli_real_escape_string($GLOBALS['conn'], trim($solicitanteDaRegra['setor'])) . "'";
					}

					if (isset($solicitanteDaRegra['subsetor'])) {

						$condicao[] = "TRIM(s.subsetor_usuario) = '" . mysqli_real_escape_string($GLOBALS['conn'], trim($solicitanteDaRegra['subsetor'])) . "'";

						$condicoes_subsetor[] = "(" . implode(' AND ', $condicao) . ")";

					} else {

						$condicoes_setor[] = "(" . implode(' AND ', $condicao) . ")";

					}
				}
			}
		}
		
		//$extra_sql_hierarquia = !empty($condicoes_hierarquia) ? " AND (" . implode(' OR ', $condicoes_hierarquia) . ")" : " AND 1=0";
		$subsetoresComRegra = [];

		foreach ($regras as $r) {
			if (!empty($r['solicitante']['subsetor'])) {
				$subsetoresComRegra[] = "'" . mysqli_real_escape_string(
					$GLOBALS['conn'],
					trim($r['solicitante']['subsetor'])
				) . "'";
			}
		}

		$condicoes = [];

		if (!empty($condicoes_subsetor)) {
			$condicoes[] = "(" . implode(" OR ", $condicoes_subsetor) . ")";
		}

		if (!empty($condicoes_setor)) {

			$bloqueioSubsetor = "";

			if (!empty($subsetoresComRegra)) {
				$bloqueioSubsetor =
					" AND TRIM(s.subsetor_usuario) NOT IN (" . implode(",", $subsetoresComRegra) . ")";
			}

			$condicoes[] =
				"(" . implode(" OR ", $condicoes_setor) . $bloqueioSubsetor . ")";
		}
		// informa os usuario s que terao acesso total, independente das regras de hierarquia
		$nivesLiberados = ['Super Administrador'];
		$isSuperAdmin = in_array($_SESSION['user_tx_nivel'] ?? '', $nivesLiberados);

		if ($isSuperAdmin){
			// Super Admin vê tudo
			$extra_sql_hierarquia = "";
		}else{
			// Usuário comum vê apenas o que as regras permitem
			if (!empty($condicoes)) {
				$extra_sql_hierarquia = " AND (" . implode(" OR ", $condicoes) . ")";
			} else {
				// Se não houver regras, não mostrar nada
				$extra_sql_hierarquia = " AND 1=0";
			}
		}
		// LÓGICA DE FILTROS DO USUÁRIO
		$statusFiltro = $filtros['status_filtro'] ?? 'pendentes';
		$extra_sql_filtros = "";
		$motoristasSelecionados = $listaInt($filtros['motorista'] ?? '');
		if (!empty($motoristasSelecionados)) {
			$extra_sql_filtros .= " AND s.id_motorista IN (" . implode(',', $motoristasSelecionados) . ")";
		}

		if ($statusFiltro == 'pendentes') {
			$extra_sql_filtros .= " AND s.status IN ('enviada', 'visualizada')";
		} elseif ($statusFiltro == 'aceitas') {
			$extra_sql_filtros .= " AND s.status = 'aceita'";
		} elseif ($statusFiltro == 'rejeitadas') {
			$extra_sql_filtros .= " AND s.status = 'nao_aceita'";
		} elseif ($statusFiltro != 'todas') {
			$extra_sql_filtros .= " AND s.status = '" . mysqli_real_escape_string($GLOBALS['conn'], $statusFiltro) . "'";
		}

		$cargosSelecionados = $listaTexto($filtros['cargo_filtro'] ?? '');
		if (!empty($cargosSelecionados)) {
			$cargosSql = array_map(fn($v) => "'" . mysqli_real_escape_string($GLOBALS['conn'], $v) . "'", $cargosSelecionados);
			$extra_sql_filtros .= " AND s.cargo_usuario IN (" . implode(',', $cargosSql) . ")";
		}
		$setoresSelecionados = $listaTexto($filtros['setor_filtro'] ?? '');
		if (!empty($setoresSelecionados)) {
			$setoresSql = array_map(fn($v) => "'" . mysqli_real_escape_string($GLOBALS['conn'], $v) . "'", $setoresSelecionados);
			$extra_sql_filtros .= " AND s.setor_usuario IN (" . implode(',', $setoresSql) . ")";
		}
		$subsetoresSelecionados = $listaTexto($filtros['subsetor_filtro'] ?? '');
		if (!empty($subsetoresSelecionados)) {
			$subsetoresSql = array_map(fn($v) => "'" . mysqli_real_escape_string($GLOBALS['conn'], $v) . "'", $subsetoresSelecionados);
			$extra_sql_filtros .= " AND s.subsetor_usuario IN (" . implode(',', $subsetoresSql) . ")";
		}

		$ordem = $filtros['ordem'] ?? 'ASC';
		$ordem = strtoupper($ordem) === 'DESC' ? 'DESC' : 'ASC';
		$sql = "
			SELECT 
				s.*,
				s.cargo_usuario,
				s.setor_usuario, 
				e.enti_tx_nome, 
				e.enti_tx_matricula,
				m.macr_tx_nome,
				mo.moti_tx_nome,
				u.user_tx_nome as superior_nome
			FROM solicitacoes_ajuste s
			JOIN entidade e ON s.id_motorista = e.enti_nb_id
			LEFT JOIN macroponto m ON s.id_macro = m.macr_nb_id
			LEFT JOIN motivo mo ON s.id_motivo = mo.moti_nb_id
			LEFT JOIN user u ON s.id_superior = u.user_nb_id
			WHERE 1 {$extra_sql_hierarquia} {$extra_sql_filtros}
			ORDER BY
				s.data_solicitacao {$ordem}
		";

		$result = query($sql);
		$dados = ($result instanceof mysqli_result) ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
		
		$ids_para_visualizar = [];

		if ($statusFiltro == 'pendentes') {
			$ids_para_visualizar = array_column(
				array_filter($dados, fn($s) => $s['status'] == 'enviada'),
				'id'
			);
		}
		if (!empty($ids_para_visualizar)) {
			query("UPDATE solicitacoes_ajuste SET status = 'visualizada', data_visualizacao = NOW(), id_superior = {$_SESSION['user_nb_id']} WHERE id IN (" . implode(',', $ids_para_visualizar) . ")");
			foreach ($dados as &$dado) {
				if (in_array($dado['id'], $ids_para_visualizar)) {
					$dado['status'] = 'visualizada';
				}
			}
			unset($dado);
		}

		$contagemDuplicados = [];
		foreach ($dados as $d) {
			$chave = $d['id_motorista'] . '_' . $d['data_ajuste'];
			$contagemDuplicados[$chave] = ($contagemDuplicados[$chave] ?? 0) + 1;
		}

		// RENDERIZAÇÃO DOS FILTROS
		$resMotoristas = query("SELECT enti_nb_id, enti_tx_matricula, enti_tx_nome FROM entidade WHERE enti_tx_status = 'ativo' ORDER BY enti_tx_nome");
		$motoristasOpcoes = array_map(fn($r) => ['value' => strval($r["enti_nb_id"]), 'label' => trim($r["enti_tx_matricula"] . " - " . $r["enti_tx_nome"])], ($resMotoristas instanceof mysqli_result) ? mysqli_fetch_all($resMotoristas, MYSQLI_ASSOC) : []);
		$resCargos = query("SELECT DISTINCT cargo_usuario FROM solicitacoes_ajuste WHERE cargo_usuario IS NOT NULL AND cargo_usuario != '' AND cargo_usuario != 'N/A' ORDER BY cargo_usuario");
		$cargosOpcoes = array_map(fn($r) => ['value' => $r['cargo_usuario'], 'label' => $r['cargo_usuario']], ($resCargos instanceof mysqli_result) ? mysqli_fetch_all($resCargos, MYSQLI_ASSOC) : []);
		$resSetores = query("SELECT DISTINCT setor_usuario FROM solicitacoes_ajuste WHERE setor_usuario IS NOT NULL AND setor_usuario != '' AND setor_usuario != 'N/A' ORDER BY setor_usuario");
		$setoresOpcoes = array_map(fn($r) => ['value' => $r['setor_usuario'], 'label' => $r['setor_usuario']], ($resSetores instanceof mysqli_result) ? mysqli_fetch_all($resSetores, MYSQLI_ASSOC) : []);
		$subsetoresOpcoes = [];
		if (!empty($setoresSelecionados)) {
			$setoresSql = array_map(fn($v) => "'" . mysqli_real_escape_string($GLOBALS['conn'], $v) . "'", $setoresSelecionados);
			$resSub = query("SELECT DISTINCT subsetor_usuario FROM solicitacoes_ajuste WHERE setor_usuario IN (" . implode(',', $setoresSql) . ") AND subsetor_usuario IS NOT NULL AND subsetor_usuario != '' AND subsetor_usuario != 'N/A' ORDER BY subsetor_usuario");
			$subsetoresOpcoes = array_map(fn($r) => ['value' => $r['subsetor_usuario'], 'label' => $r['subsetor_usuario']], ($resSub instanceof mysqli_result) ? mysqli_fetch_all($resSub, MYSQLI_ASSOC) : []);
		}

		$campos_filtro = [
			montarMultiSelectFiltro("Funcionário", "motorista", $motoristasSelecionados, $motoristasOpcoes, 3, "combo_motorista"),
			"<div class='col-sm-2 margin-bottom-5'>
				<label>Status</label>
				<select name='status_filtro' class='form-control input-sm'>
					<option value='pendentes' " . ($statusFiltro == 'pendentes' ? 'selected' : '') . ">Pendentes</option>
					<option value='aceitas' " . ($statusFiltro == 'aceitas' ? 'selected' : '') . ">Aceitas</option>
					<option value='rejeitadas' " . ($statusFiltro == 'rejeitadas' ? 'selected' : '') . ">Rejeitadas</option>
					<option value='todas' " . ($statusFiltro == 'todas' ? 'selected' : '') . ">Todas</option>
				</select>
			</div>",
			montarMultiSelectFiltro("Cargo", "cargo_filtro", $cargosSelecionados, $cargosOpcoes, 2, "combo_cargo"),
			montarMultiSelectFiltro("Setor", "setor_filtro", $setoresSelecionados, $setoresOpcoes, 2, "combo_setor"),
			montarMultiSelectFiltro("Subsetor", "subsetor_filtro", $subsetoresSelecionados, $subsetoresOpcoes, 3, "combo_subsetor", empty($setoresSelecionados))
		];

		echo abre_form("Filtros de Pesquisa");
		echo linha_form($campos_filtro);
		echo fecha_form([botao("Pesquisar", "index", "", "", "", "", "btn btn-primary")]);

		echo "
		<div class='row margin-bottom-10'>
			<div class='col-sm-12'>
				<button type='button' class='btn btn-sm btn-success' id='btn-aprovar-lote' style='display:none;'><i class='fa fa-check'></i> Aprovar Selecionados</button>
				<button type='button' class='btn btn-sm btn-danger' id='btn-rejeitar-lote' style='display:none;'><i class='fa fa-times'></i> Rejeitar Selecionados</button>
			</div>
		</div>
		";
		$linhas = [];
		$ultimoMotorista = null;
		$linhas = [];
		$pdfjaExibido = [];
		foreach ($dados as $row) {
			if ($ultimoMotorista !== $row['id_motorista']) {
				$linhas[] = [
					"<b style='color:#333;'>👤 {$row['enti_tx_nome']}</b>",
					"", "", "", "", "", "", "", "", "", "", ""
				];
				$ultimoMotorista = $row['id_motorista'];
			}
			$statusBadge = match ($row['status']) {
				'enviada' => "<span class='badge badge-warning'>Enviada</span>",
				'visualizada' => "<span class='badge badge-info'>Visualizada</span>",
				'aceita' => "<span class='badge badge-success'>Aceita</span>",
				'nao_aceita' => "<span class='badge badge-danger'>Rejeitada</span>",
				default => ''
			};

			$alertaDuplicidade = "";
			$estiloCelulaDuplicada = "";
			$chave = $row['id_motorista'] . '_' . $row['data_ajuste'];
			if ($contagemDuplicados[$chave] > 1) {
				//$alertaDuplicidade = "<i class='fa fa-exclamation-triangle text-danger' title='Existem {$contagemDuplicados[$chave]} solicitações para este funcionário neste mesmo dia.' style='cursor:help; margin-left:5px;'></i>";
				$estiloCelulaDuplicada = "style='background-color: #fff1f0;'";
			}

			$checkbox = "";
			if ($row['status'] == 'enviada' || $row['status'] == 'visualizada') {
				$checkbox = "<input type='checkbox' class='sel-lote' value='{$row['id']}'>";
			}

			$acoes = "";
			if ($row['status'] == 'enviada' || $row['status'] == 'visualizada') {
				$acoes = "
					<div class='btn-group'>
						<button type='button' class='btn btn-xs btn-success' title='Aprovar' onclick=\"abrirModalJustificativa('aprovar', '{$row['id']}')\"><i class='fa fa-check'></i></button>
						<button type='button' class='btn btn-xs btn-danger' title='Rejeitar' onclick=\"abrirModalJustificativa('rejeitar', '{$row['id']}')\"><i class='fa fa-times'></i></button>
					</div>
				";
			} else {
				$cor = ($row['status'] == 'aceita' ? 'green' : 'red');
				$titleObs = !empty($row['justificativa_gestor']) ? "title='Justificativa Gestor: " . htmlspecialchars($row['justificativa_gestor'], ENT_QUOTES) . "' style='cursor:help;'" : "";
				$acoes = "<small style='color:$cor' {$titleObs}><b>" . ($row['status'] == 'aceita' ? 'Aprovado' : 'Rejeitado') . " por {$row['superior_nome']}</b></small>";
			}

			$chavepdf = $row['id_motorista']. '-'. $row['data_ajuste'];
			if (!isset($pdfjaExibido[$chavepdf])) {
				$pdfjaExibido[$chavepdf] = true;
		
			$idInst = $row['id_instancia_documento'] ?? null;
			$docBtn = !empty($idInst)
				? "<a href='../documentos/processar_pdf.php?id={$idInst}' target='_blank' class='btn btn-xs btn-info' title='Visualizar / Baixar PDF'><span class='glyphicon glyphicon-print'></span> PDF</a>"
				: "<span style='color:#999;'>-</span>";
			} else {
				$docBtn = "-"; 
			}

			//$idInst = $row['id_instancia_documento'] ?? null;
			//$docBtn = !empty($idInst)
			//	? "<a href='../documentos/processar_pdf.php?id={$idInst}' target='_blank' class='btn btn-xs btn-info' title='Visualizar / Baixar PDF'><span class='glyphicon glyphicon-print'></span> PDF</a>"
			//	: "<span style='color:#999;'>-</span>";

			$linhas[] = [
				$checkbox,
				date('d/m/Y H:i', strtotime($row['data_solicitacao'])),
				"<div {$estiloCelulaDuplicada}><b>{$row['enti_tx_nome']}</b> {$alertaDuplicidade}<br><small>{$row['enti_tx_matricula']}</small></div>",
				"<b>" . date('d/m/Y', strtotime($row['data_ajuste'])) . "</b><br>" . $row['hora_ajuste'],
				$row['macr_tx_nome'],
				$row['moti_tx_nome'],
				"<span title='{$row['justificativa']}' style='cursor:help;'>" . (strlen($row['justificativa']) > 20 ? substr($row['justificativa'], 0, 20) . "..." : $row['justificativa']) . "</span>",
				"<small><b>C:</b> {$row['cargo_usuario']}<br><b>S:</b> {$row['setor_usuario']}</small>",
				$statusBadge,
				$acoes,
				$docBtn,
				$row['justificativa_gestor']?? '-'
			];
		}

		$cabecalho_tabela = ["<input type='checkbox' id='sel-tudo'>", "Solicitado", "Funcionário", "Data/Hora Ajuste", "Tipo", "Motivo", "Justificativa", "Solicitante", "Status", "Ações", "Documento","Justificativa"];
		echo "<h3>Solicitações de Ajuste</h3>";
		$novaOrdem = ($ordem === 'ASC') ? 'DESC' : 'ASC';
		$icone = ($ordem === 'ASC') ? '↑' : '↓';

		echo "
		<div class='row margin-bottom-10'>
			<div class='col-sm-12'>
				<form method='GET' style='display:inline;'>
		";
		foreach ($filtros as $k => $v) {
			if ($k === 'ordem') continue;
			echo "<input type='hidden' name='{$k}' value='" . htmlspecialchars($v) . "'>";
		}

		echo "
					<input type='hidden' name='ordem' value='{$novaOrdem}'>
					<button class='btn btn-sm btn-default'>
						Ordenar por data {$icone}
					</button>
				</form>
			</div>
		</div>
		";
		echo montarTabelaPonto($cabecalho_tabela, $linhas);

		?>
		<form name="form_acao" method="POST" style="display:none;">
			<input type="hidden" name="acao" value="">
			<input type="hidden" name="id_solicitacao" value="">
			<input type="hidden" name="permitir_substituir" value="0">
			<input type="hidden" name="justificativa_gestor" value="">
		</form>
		<form name="form_lote" method="POST" style="display:none;">
			<input type="hidden" name="acao" value="processarEmLote">
			<input type="hidden" name="acao_lote" value="">
			<input type="hidden" name="ids_selecionados" value="">
			<input type="hidden" name="permitir_substituir" value="0">
			<input type="hidden" name="justificativa_gestor" value="">
		</form>

		<div id="modalJustificativa" class="modal fade" tabindex="-1" role="dialog">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
						<h4 class="modal-title" id="modalTitJustificativa">Justificativa do Gestor</h4>
					</div>
					<div class="modal-body">
						<p id="modalMsgJustificativa"></p>
						<textarea id="textoJustificativa" class="form-control" rows="3" placeholder="Insira uma justificativa (opcional)"></textarea>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
						<button type="button" class="btn btn-primary" onclick="confirmarAcaoModal()">Confirmar</button>
					</div>
				</div>
			</div>
		</div>

		<script>
			(function(){
				function closeAllMultiSelect(){
					document.querySelectorAll('.ms-menu').forEach(function(menu){
						menu.style.display = 'none';
					});
				}

				document.addEventListener('click', function(){ closeAllMultiSelect(); });
				window.__msCloseAll = closeAllMultiSelect;
			})();

			let acaoAtualModal = null;

			function abrirModalJustificativa(tipo, idOuAcaoLote) {
				document.getElementById('textoJustificativa').value = '';
				acaoAtualModal = { tipo: tipo };
				
				if (tipo === 'aprovar') {
					acaoAtualModal.id = idOuAcaoLote;
					document.getElementById('modalTitJustificativa').innerText = 'Aprovar Solicitação';
					document.getElementById('modalMsgJustificativa').innerText = 'Deseja aprovar este ajuste?';
				} else if (tipo === 'rejeitar') {
					acaoAtualModal.id = idOuAcaoLote;
					document.getElementById('modalTitJustificativa').innerText = 'Rejeitar Solicitação';
					document.getElementById('modalMsgJustificativa').innerText = 'Deseja rejeitar este ajuste?';
				} else if (tipo === 'lote') {
					acaoAtualModal.acaoLote = idOuAcaoLote;
					const msg = idOuAcaoLote === 'aceitarLote' ? 'Aprovar todos os selecionados?' : 'Rejeitar todos os selecionados?';
					document.getElementById('modalTitJustificativa').innerText = 'Processar em Lote';
					document.getElementById('modalMsgJustificativa').innerText = msg;
				}
				
				$('#modalJustificativa').modal('show');
			}

			function confirmarAcaoModal() {
				const justificativa = document.getElementById('textoJustificativa').value;
				
				if (acaoAtualModal.tipo === 'aprovar') {
					const idSolicitacao = acaoAtualModal.id;
					$('#modalJustificativa').modal('hide');
					
					fetch('<?= basename($_SERVER['PHP_SELF']) ?>', {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: 'acao=verificarPontoExistenteSolicitacao&id_solicitacao=' + encodeURIComponent(idSolicitacao)
					})
					.then(r => r.json())
					.then(payload => {
						let permitir = '0';
						if(payload && payload.existe){
							const tipo = payload.tipo ? payload.tipo : 'este tipo de registro';
							const hora = payload.hora ? payload.hora : '';
							const msg = 'Já existe ' + tipo + (hora ? (' às ' + hora) : '') + ' registrado neste dia.\n\nDeseja substituir?';
							if(!confirm(msg)) return;
							permitir = '1';
						}
						document.form_acao.id_solicitacao.value = idSolicitacao;
						document.form_acao.acao.value = 'aceitarSolicitacao';
						document.form_acao.permitir_substituir.value = permitir;
						document.form_acao.justificativa_gestor.value = justificativa;
						document.form_acao.submit();
					})
					.catch(() => {
						document.form_acao.id_solicitacao.value = idSolicitacao;
						document.form_acao.acao.value = 'aceitarSolicitacao';
						document.form_acao.permitir_substituir.value = '0';
						document.form_acao.justificativa_gestor.value = justificativa;
						document.form_acao.submit();
					});

				

				} else if (acaoAtualModal.tipo === 'rejeitar') {
					const idSolicitacao = acaoAtualModal.id;

					if (!justificativa.trim()) {
						alert('A justificativa é obrigatória para rejeitar.');
						return;
					}
					
					$('#modalJustificativa').modal('hide');
					
					document.form_acao.id_solicitacao.value = idSolicitacao;
					document.form_acao.acao.value = 'rejeitarSolicitacao';
					document.form_acao.justificativa_gestor.value = justificativa;
					document.form_acao.submit();


				} else if (acaoAtualModal.tipo === 'lote') {
					const acaoLote = acaoAtualModal.acaoLote;

					if (acaoLote === 'rejeitarLote' && !justificativa.trim()) {
						alert('A justificativa é obrigatória para rejeitar em lote.');
						return;
					}
					
					$('#modalJustificativa').modal('hide');
					
					const selecionados = Array.from(document.querySelectorAll('.sel-lote:checked')).map(cb => cb.value);
					
					if (acaoLote === 'aceitarLote') {
						fetch('<?= basename($_SERVER['PHP_SELF']) ?>', {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: 'acao=verificarPontoExistenteLote&ids=' + encodeURIComponent(selecionados.join(','))
						})
						.then(r => r.json())
						.then(payload => {
							let permitir = '0';
							if(payload && payload.existe){
								const msg = 'Existem ' + payload.qtd + ' registros que já possuem ponto lançado.\n\nDeseja substituir para todos os selecionados?';
								if(!confirm(msg)) return;
								permitir = '1';
							}
							document.form_lote.ids_selecionados.value = selecionados.join(',');
							document.form_lote.acao_lote.value = acaoLote;
							document.form_lote.permitir_substituir.value = permitir;
							document.form_lote.justificativa_gestor.value = justificativa;
							document.form_lote.submit();
						})
						.catch(() => {
							document.form_lote.ids_selecionados.value = selecionados.join(',');
							document.form_lote.acao_lote.value = acaoLote;
							document.form_lote.permitir_substituir.value = '0';
							document.form_lote.justificativa_gestor.value = justificativa;
							document.form_lote.submit();
						});
						return;
					}

					document.form_lote.ids_selecionados.value = selecionados.join(',');
					document.form_lote.acao_lote.value = acaoLote;
					document.form_lote.permitir_substituir.value = '0';
					document.form_lote.justificativa_gestor.value = justificativa;
					document.form_lote.submit();
				}
			}

			function processarLote(acaoLote) {
				const selecionados = Array.from(document.querySelectorAll('.sel-lote:checked')).map(cb => cb.value);
				if (selecionados.length === 0) return;
				
				abrirModalJustificativa('lote', acaoLote);
			}

			document.addEventListener('DOMContentLoaded', function() {
				const selTudo = document.getElementById('sel-tudo');
				const checks = document.querySelectorAll('.sel-lote');
				const btnAprovar = document.getElementById('btn-aprovar-lote');
				const btnRejeitar = document.getElementById('btn-rejeitar-lote');

				function toggleBotoesLote() {
					const temSelecionado = document.querySelectorAll('.sel-lote:checked').length > 0;
					btnAprovar.style.display = temSelecionado ? 'inline-block' : 'none';
					btnRejeitar.style.display = temSelecionado ? 'inline-block' : 'none';
				}
				btnAprovar.onclick = () => processarLote('aceitarLote');
				btnRejeitar.onclick = () => processarLote('rejeitarLote');

				if (selTudo) {
					selTudo.addEventListener('change', function() {
						checks.forEach(cb => cb.checked = selTudo.checked);
						toggleBotoesLote();
					});
				}

				checks.forEach(cb => {
					cb.addEventListener('change', toggleBotoesLote);
				});

				function initMultiSelect(rootId){
					const root = document.getElementById(rootId);
					if(!root) return null;

					const hidden = root.querySelector('.ms-hidden');
					const btn = root.querySelector('.ms-btn');
					const countEl = root.querySelector('.ms-count');
					const menu = root.querySelector('.ms-menu');
					if(!hidden || !btn || !countEl || !menu) return null;

					const btnClear = root.querySelector('.ms-clear');

						if(btnClear){
							btnClear.addEventListener('click', function(e){
								e.preventDefault();
								e.stopPropagation();

								//  Desmarca todos os checkboxes
								const checkboxes = menu.querySelectorAll('input[type="checkbox"]');
								checkboxes.forEach(cb => {
									cb.checked = false;
								});

								//  Limpa valor
								hidden.value = '';

								//  Sincroniza 
								sync();

								// Fecha o dropdown
								menu.style.display = 'none';

								
								root.closest('form').submit();
							});
						}

					function sync(){
						const checked = Array.from(menu.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value);
						hidden.value = checked.join(',');
						countEl.textContent = checked.length ? (checked.length + ' selecionados') : 'Todos';
					}

					btn.addEventListener('click', function(e){
						e.preventDefault();
						e.stopPropagation();
						if(btn.disabled) return;

						const isOpen = menu.style.display === 'block';
						if(window.__msCloseAll) window.__msCloseAll();
						menu.style.display = isOpen ? 'none' : 'block';
					});

					menu.addEventListener('click', function(e){ e.stopPropagation(); });

					menu.addEventListener('change', function(e){
						if(e.target && e.target.matches('input[type="checkbox"]')) sync();
					});

					sync();

					return { root, hidden, btn, countEl, menu, sync };
				}

				const msSetor = initMultiSelect('combo_setor');
				const msSubsetor = initMultiSelect('combo_subsetor');

				function carregarSubsetores(){
					if(!msSetor || !msSubsetor) return;
					const setoresSelecionados = msSetor.hidden.value || '';

					if(!setoresSelecionados){
						msSubsetor.menu.innerHTML = '<li style="margin:2px 0; color:#777;">Nenhum item</li>';
						msSubsetor.hidden.value = '';
						msSubsetor.btn.disabled = true;
						msSubsetor.menu.style.display = 'none';
						msSubsetor.sync();
						return;
					}

					msSubsetor.btn.disabled = true;
					msSubsetor.menu.innerHTML = '<li style="margin:2px 0; color:#777;">Carregando...</li>';

					fetch('<?= basename($_SERVER['PHP_SELF']) ?>', {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: 'acao=buscarSubsetores&setor=' + encodeURIComponent(setoresSelecionados)
					})
					.then(r => r.json())
					.then(data => {
						const selecionadosAtuais = new Set((msSubsetor.hidden.value || '').split(',').filter(Boolean));
						msSubsetor.menu.innerHTML = '';

						if(Array.isArray(data) && data.length){
							data.forEach(function(sub, index){
								const cbId = `ms_subsetor_opt_${index}`;
								const li = document.createElement('li');
								li.style.margin = '2px 0';
								const label = document.createElement('label');
								label.style.cssText = 'display:flex; align-items:center; gap:6px; font-weight:normal; margin:0; cursor:pointer;';
								label.htmlFor = cbId;

								const input = document.createElement('input');
								input.type = 'checkbox';
								input.id = cbId;
								input.value = sub;
								if(selecionadosAtuais.has(sub)) input.checked = true;
								input.style.cssText = 'width:14px; height:14px; margin:0;';

								const span = document.createElement('span');
								span.textContent = sub;

								label.appendChild(input);
								label.appendChild(span);
								li.appendChild(label);
								msSubsetor.menu.appendChild(li);
							});
							msSubsetor.btn.disabled = false;
						} else {
							const li = document.createElement('li');
							li.style.cssText = 'margin:2px 0; color:#777;';
							li.textContent = 'Nenhum item';
							msSubsetor.menu.appendChild(li);
							msSubsetor.btn.disabled = true;
						}

						msSubsetor.sync();
					})
					.catch(() => {
						msSubsetor.menu.innerHTML = '';
						msSubsetor.btn.disabled = false;
						msSubsetor.sync();
					});
				}

				if(msSetor && msSubsetor){
					msSetor.menu.addEventListener('change', function(e){
						if(e.target && e.target.matches('input[type="checkbox"]')) carregarSubsetores();
					});
					initMultiSelect('combo_motorista');
					initMultiSelect('combo_cargo');
				}
			});
		</script>
		<?php

		rodape();
	}

	$acao = $_POST['acao'] ?? $_GET['acao'] ?? 'index';

	switch ($acao) {
		case 'aceitarSolicitacao':
			aceitarSolicitacao();
			break;
		case 'rejeitarSolicitacao':
			rejeitarSolicitacao();
			break;
		case 'processarEmLote':
			processarEmLote();
			break;
		case 'buscarSubsetores':
			buscarSubsetores();
			break;
		case 'verificarPontoExistenteSolicitacao':
			verificarPontoExistenteSolicitacao();
			break;
		case 'verificarPontoExistenteLote':
			verificarPontoExistenteLote();
			break;
		default:
			index();
			break;
	}
?>
