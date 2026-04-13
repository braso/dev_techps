<?php
	include_once "funcoes_ponto.php";

	// Definir a função updateTimer ANTES de qualquer output
	ob_start();
	?><script>
		var timeoutId;
		window.updateTimer = function() { 
			if(typeof timeoutId !== 'undefined' && timeoutId) {
				clearTimeout(timeoutId);
			}
			timeoutId = setTimeout(function(){
				let form = document.getElementById('loginTimeoutForm');
				if(form) form.submit();
			}, 15*60*1000);
		}
	</script><?php
	$scriptContent = ob_get_clean();
	$GLOBALS['updateTimerScript'] = $scriptContent;

	// Função para criar/atualizar tabelas se não existir
	function criarTabelaSolicitacoes() {
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
			data_envio_documento DATETIME NULL,
			id_instancia_documento INT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		// Adicionar colunas em solicitacoes_ajuste se necessário
		$check = query("SHOW COLUMNS FROM solicitacoes_ajuste LIKE 'id_instancia_documento'");
		if ($check instanceof mysqli_result && mysqli_num_rows($check) == 0) {
			query("ALTER TABLE solicitacoes_ajuste ADD COLUMN id_instancia_documento INT NULL AFTER data_decisao");
		}

		$check = query("SHOW COLUMNS FROM solicitacoes_ajuste LIKE 'justificativa_gestor'");
		if ($check instanceof mysqli_result && mysqli_num_rows($check) == 0) {
			query("ALTER TABLE solicitacoes_ajuste ADD COLUMN justificativa_gestor TEXT DEFAULT NULL AFTER id_instancia_documento");
		}

		$check = query("SHOW COLUMNS FROM solicitacoes_ajuste LIKE 'data_envio_documento'");
		if ($check instanceof mysqli_result && mysqli_num_rows($check) == 0) {
			query("ALTER TABLE solicitacoes_ajuste ADD COLUMN data_envio_documento DATETIME NULL AFTER data_visualizacao");
		}

		// Atualizar inst_documento_modulo
		$check = query("SHOW COLUMNS FROM inst_documento_modulo LIKE 'inst_nb_entidade'");
		if ($check instanceof mysqli_result && mysqli_num_rows($check) == 0) {
			query("ALTER TABLE inst_documento_modulo ADD COLUMN inst_nb_entidade INT NULL AFTER inst_nb_user");
		}
		
		$check = query("SHOW COLUMNS FROM inst_documento_modulo LIKE 'inst_tx_data_referencia'");
		if ($check instanceof mysqli_result && mysqli_num_rows($check) == 0) {
			query("ALTER TABLE inst_documento_modulo ADD COLUMN inst_tx_data_referencia DATE NULL AFTER inst_nb_entidade");
		}
	}
	
	criarTabelaSolicitacoes();
	
	function apf_isAdminNivel(): bool {
		$nivel = strtolower(trim(strval($_SESSION['user_tx_nivel'] ?? '')));
		return (strpos($nivel, 'administrador') !== false) || (strpos($nivel, 'super administrador') !== false);
	}

	function apf_getEntidadeLogadaId(): int {
		return intval($_SESSION['user_nb_entidade'] ?? 0);
	}

	function apf_resolverMotoristaAlvo($idMotoristaInformado): int {
		$idInformado = intval($idMotoristaInformado);
		$idEntidadeLogada = apf_getEntidadeLogadaId();

		if (!apf_isAdminNivel()) {
			return $idEntidadeLogada;
		}

		return $idInformado > 0 ? $idInformado : $idEntidadeLogada;
	}

	function verificarPontoExistente($matricula, $data, $hora) {
		$ponto = mysqli_fetch_assoc(query("
			SELECT p.pont_tx_data, m.macr_tx_nome
			FROM ponto p
			JOIN macroponto m ON (p.pont_tx_tipo = m.macr_tx_codigoInterno OR p.pont_tx_tipo = m.macr_nb_id)
			WHERE p.pont_tx_status = 'ativo'
				AND p.pont_tx_matricula = '{$matricula}'
				AND p.pont_tx_data LIKE '{$data}%'
			ORDER BY ABS(TIMESTAMPDIFF(MINUTE, p.pont_tx_data, '{$data} {$hora}')) ASC
			LIMIT 1
		"));

		if ($ponto) {
			$horaExistente = date('H:i', strtotime($ponto['pont_tx_data']));
			// Se a diferença for menor que 2 horas, consideramos que o ponto existe para esse "turno"
			$diff = abs(strtotime($ponto['pont_tx_data']) - strtotime("$data $hora")) / 60;
			if ($diff < 120) {
				return "Já existe registro de {$ponto['macr_tx_nome']} às {$horaExistente}.";
			}
		}
		return "Nenhum registro próximo encontrado.";
	}

	function obterGestoresSolicitanteTexto($idUsuarioSolicitante) {
		$idUsuarioSolicitante = intval($idUsuarioSolicitante);
		if ($idUsuarioSolicitante <= 0) {
			return 'N/A';
		}

		$sql = "SELECT e.enti_setor_id, e.enti_tx_tipoOperacao
				FROM user u
				LEFT JOIN entidade e ON e.enti_nb_id = u.user_nb_entidade
				WHERE u.user_nb_id = {$idUsuarioSolicitante}
				LIMIT 1";
		$res = query($sql);
		$ent = ($res instanceof mysqli_result) ? mysqli_fetch_assoc($res) : [];
		$setorId = intval($ent['enti_setor_id'] ?? 0);
		$operacaoId = intval($ent['enti_tx_tipoOperacao'] ?? 0);

		$gestores = [];
		if ($setorId > 0) {
			$resSetor = query("SELECT e.enti_nb_id, e.enti_tx_nome
							   FROM setor_responsavel sr
							   INNER JOIN entidade e ON e.enti_nb_id = sr.sres_nb_entidade_id
							   WHERE sr.sres_nb_setor_id = {$setorId}
							     AND sr.sres_tx_status = 'ativo'
							   ORDER BY COALESCE(sr.sres_nb_ordem,0), e.enti_tx_nome");
			if ($resSetor instanceof mysqli_result) {
				while ($r = mysqli_fetch_assoc($resSetor)) {
					$id = intval($r['enti_nb_id'] ?? 0);
					$nome = trim(strval($r['enti_tx_nome'] ?? ''));
					if ($id > 0 && $nome !== '') {
						$gestores[$id] = $nome;
					}
				}
			}
		}

		if ($operacaoId > 0) {
			$resOper = query("SELECT e.enti_nb_id, e.enti_tx_nome
							  FROM operacao_responsavel orv
							  INNER JOIN entidade e ON e.enti_nb_id = orv.opre_nb_entidade_id
							  WHERE orv.opre_nb_operacao_id = {$operacaoId}
							    AND orv.opre_tx_status = 'ativo'
							  ORDER BY COALESCE(orv.opre_nb_ordem,0), e.enti_tx_nome");
			if ($resOper instanceof mysqli_result) {
				while ($r = mysqli_fetch_assoc($resOper)) {
					$id = intval($r['enti_nb_id'] ?? 0);
					$nome = trim(strval($r['enti_tx_nome'] ?? ''));
					if ($id > 0 && $nome !== '') {
						$gestores[$id] = $nome;
					}
				}
			}
		}

		if (empty($gestores)) {
			return 'N/A';
		}

		return implode(' / ', array_values($gestores));
	}

	function normalizarChavePdf(string $txt): string {
		$txt = trim($txt);
		$map = [
			'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','Á'=>'a','À'=>'a','Ã'=>'a','Â'=>'a','Ä'=>'a',
			'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e',
			'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','Í'=>'i','Ì'=>'i','Î'=>'i','Ï'=>'i',
			'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','Ó'=>'o','Ò'=>'o','Ô'=>'o','Õ'=>'o','Ö'=>'o',
			'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u',
			'ç'=>'c','Ç'=>'c'
		];
		$txt = strtr($txt, $map);
		$txt = strtolower($txt);
		$txt = preg_replace('/[^a-z0-9]+/', ' ', $txt);
		return trim(strval($txt));
	}

	function processarDocumentoAgrupado($idMotorista, $loteDocumento) {
		global $conn;
		$loteDocumento = trim(strval($loteDocumento));

		// 1. Identificar o tipo de documento de ajuste
		// Priorizar busca exata conforme exibido na interface do usuário
		$tipoDoc = mysqli_fetch_assoc(query("SELECT tipo_nb_id FROM tipos_documentos WHERE tipo_tx_status = 'ativo' AND (tipo_tx_nome = 'Comunicação Interna' OR tipo_tx_nome = 'Comunicacao Interna' OR tipo_tx_nome = 'Ajuste Ponto' OR tipo_tx_nome = 'Solicitação de Ajuste') LIMIT 1"));
		// Nome do documento criado em tipo de documento
		if (!$tipoDoc) {
			$tipoDoc = mysqli_fetch_assoc(query("SELECT tipo_nb_id FROM tipos_documentos WHERE tipo_tx_status = 'ativo' AND (tipo_tx_nome LIKE '%Comunic%Interna%' OR tipo_tx_nome LIKE '%Ajuste Ponto%' OR tipo_tx_nome LIKE '%Solicita%de Ajuste%') LIMIT 1"));
		}
		$idTipo = $tipoDoc['tipo_nb_id'] ?? 0;

		if (!$idTipo) return; // Não há modelo de documento configurado

		if ($loteDocumento === '') {
			$loteDocumento = date('Y-m-d H:i:s');
		}

		// 2. Buscar todas as solicitações do lote enviado para este motorista.
		$solicitacoes = mysqli_fetch_all(query("
			SELECT sa.*, m.macr_tx_nome, mot.moti_tx_nome
			FROM solicitacoes_ajuste sa
			LEFT JOIN macroponto m ON sa.id_macro = m.macr_nb_id
			LEFT JOIN motivo mot ON sa.id_motivo = mot.moti_nb_id
			WHERE sa.id_motorista = $idMotorista 
			  AND sa.data_envio_documento = '$loteDocumento'
			  AND sa.status = 'enviada'
			ORDER BY sa.hora_ajuste ASC
		"), MYSQLI_ASSOC);

		if (empty($solicitacoes)) return;

		$idUsuarioSolicitanteDoc = intval($solicitacoes[0]['id_usuario_solicitante'] ?? ($_SESSION['user_nb_id'] ?? 0));

		// 3. Tentar encontrar uma instância existente vinculada a estas solicitações
		$idInstancia = 0;
		foreach ($solicitacoes as $s) {
			if (!empty($s['id_instancia_documento'])) {
				$idInstancia = $s['id_instancia_documento'];
				break;
			}
		}

		// 4. Persistir a instância (Criar se não existir)
		$motorista = mysqli_fetch_assoc(query("SELECT * FROM entidade WHERE enti_nb_id = $idMotorista"));
		$matricula = $motorista['enti_tx_matricula'];

		if (!$idInstancia) {
			$dataReferencia = date('Y-m-d', strtotime($loteDocumento));
			query("INSERT INTO inst_documento_modulo (inst_nb_tipo_doc, inst_nb_user, inst_nb_entidade, inst_tx_data_referencia, inst_dt_criacao, inst_tx_status) 
				   VALUES ($idTipo, {$_SESSION['user_nb_id']}, $idMotorista, '$dataReferencia', '".date('Y-m-d H:i:s')."', 'ativo')");
			$idInstancia = mysqli_insert_id($GLOBALS['conn']);
		}

		// 5. Vincular TODAS as solicitações do lote a esta instância
		$queryLote = "UPDATE solicitacoes_ajuste SET id_instancia_documento = $idInstancia WHERE id_motorista = $idMotorista AND status = 'enviada' AND data_envio_documento = '" . mysqli_real_escape_string($GLOBALS['conn'], $loteDocumento) . "'";
		query($queryLote);

		// 6. Montar o conteúdo textual consolidado (Formato HTML Profissional para PDF)
		$resumoAjustes = '<h3 style="text-align:center;">Solicitações de Ajuste - Data: ' . date('d/m/Y', strtotime($loteDocumento)) . '</h3>';
		$resumoAjustes .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;">';
		$resumoAjustes .= '<tr style="background-color:#eeeeee; font-weight:bold; text-align:center;">
							<th width="12%">Data</th>
							<th width="12%">Horário</th>
							<th width="18%">Tipo</th>
							<th width="18%">Motivo</th>
							<th width="28%">Justificativa</th>
							<th width="12%">Status no Banco</th>
						   </tr>';
		
		foreach ($solicitacoes as $s) {
			$existente = verificarPontoExistente($matricula, $s['data_ajuste'], $s['hora_ajuste']);
			$resumoAjustes .= '<tr>
								<td style="text-align:center;">' . date('d/m/Y', strtotime($s['data_ajuste'])) . '</td>
								<td style="text-align:center;">' . $s['hora_ajuste'] . '</td>
								<td>' . $s['macr_tx_nome'] . '</td>
								<td>' . $s['moti_tx_nome'] . '</td>
								<td>' . nl2br($s['justificativa']) . '</td>
								<td style="font-size:9px;">' . $existente . '</td>
							   </tr>';
		}
		$resumoAjustes .= '</table>';

		// 9. Preencher/Atualizar os campos do documento
		$campos = mysqli_fetch_all(query("SELECT * FROM camp_documento_modulo WHERE camp_nb_tipo_doc = $idTipo AND camp_tx_status = 'ativo'"), MYSQLI_ASSOC);
		foreach ($campos as $c) {
			$label = normalizarChavePdf(strval($c['camp_tx_label'] ?? ''));
			$valor = '';

			if ($label === 'data') {
				$valor = date('d/m/Y', strtotime($loteDocumento));
			} elseif ($label === 'nome' || $label === 'funcionario') {
				$valor = $motorista['enti_tx_nome'];
			} elseif ($label === 'matricula') {
				$valor = $matricula;
			} elseif ($label === 'cpf') {
				$valor = $motorista['enti_tx_cpf'];
			} elseif ($label === 'para') {
				$valor = obterGestoresSolicitanteTexto($idUsuarioSolicitanteDoc);
			} elseif ($label === 'de') {
				$solicitante = mysqli_fetch_assoc(query("SELECT user_tx_nome FROM user WHERE user_nb_id = " . $idUsuarioSolicitanteDoc . " LIMIT 1"));
				$valor = trim(strval($solicitante['user_tx_nome'] ?? ''));
			} elseif ($label === 'justificativa' || $label === 'descricao' || $label === 'detalhe' || $label === 'resumo' || $label === 'observacoes' || $label === 'observacao' || $label === 'obs') {
				$valor = $resumoAjustes;
			}

			if ($valor !== '') {
				$val_existe = mysqli_fetch_assoc(query("SELECT valo_nb_id FROM valo_documento_modulo WHERE valo_nb_instancia = $idInstancia AND valo_nb_campo = {$c['camp_nb_id']} LIMIT 1"));
				if ($val_existe) {
					query("UPDATE valo_documento_modulo SET valo_tx_valor = '" . mysqli_real_escape_string($GLOBALS['conn'], $valor) . "' WHERE valo_nb_id = {$val_existe['valo_nb_id']}");
				} else {
					$dados_valor = [
						'valo_nb_instancia' => $idInstancia,
						'valo_nb_campo' => $c['camp_nb_id'],
						'valo_tx_valor' => $valor,
						'valo_tx_status' => 'ativo'
					];
					inserir('valo_documento_modulo', array_keys($dados_valor), array_values($dados_valor));
				}
			}
		}
	}

	// criarTabelaSolicitacoes() foi movida para o topo

	$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
	if ($acao == 'buscarUltimaJustificativa') {
		buscarUltimaJustificativa();
	} elseif ($acao == 'verificarPontoExistentePorMacro') {
		verificarPontoExistentePorMacro();
	}

	function verificarDiaComErro($motorista, $data) {
		$aDetalhado = diaDetalhePonto($motorista, $data);
		$row = [verificaTolerancia($aDetalhado["diffSaldo"], $data, $motorista['enti_nb_id'])] + $aDetalhado;

		$qtdErros = 0;
		foreach ($row as $value) {
			preg_match_all("/(?<=<)([^<|>])+(?=>)/", $value, $tags);
			if (!empty($tags[0])) {
				foreach ($tags[0] as $tag) {
					$qtdErros += substr_count($tag, "fa-warning") * (substr_count($tag, "color:red;") || substr_count($tag, "color:orange;"))
					+ ((is_int(strpos($tag, "fa-info-circle"))) * (substr_count($tag, "color:red;") || substr_count($tag, "color:orange;")));
				}
			}
		}

		if (is_int(strpos($row["inicioJornada"] ?? "", "Batida início de jornada não registrada!")) 
		&& is_int(strpos($row["jornadaPrevista"] ?? "", "Abono: "))) {
			$qtdErros = 0;
		}

		return ($qtdErros > 0);
	}

	function obterDatasNaoConformidade($motorista, $dataMes) {
		// Retorna um array com as datas que têm não conformidades
		$monthDate = new DateTime($dataMes . "-01");
		$dataAdmissao = new DateTime($motorista["enti_tx_admissao"] ?? date("Y-m-d"));
		
		$datasComErro = [];

		for ($date = new DateTime($monthDate->format("Y-m-1")); $date->format("Y-m-d") <= $monthDate->format("Y-m-t"); $date->modify("+1 day")) {

			if ($monthDate->format("Y-m") < $dataAdmissao->format("Y-m")) {
				continue;
			}

			if ($date->format("Y-m-d") > date("Y-m-d")) {
				break;
			}

			if (verificarDiaComErro($motorista, $date->format("Y-m-d"))) {
				$datasComErro[] = $date->format("Y-m-d");
			}
		}

		return $datasComErro;
	}

	function gerarTabelaSolicitacoes($idMotorista) {
		$idSolicitanteLogado = intval($_SESSION['user_nb_id'] ?? 0);
		// Buscar todas as solicitações do motorista
		$sql = "
			SELECT 
				sa.id,
				sa.data_ajuste,
				sa.hora_ajuste,
				sa.id_macro,
				sa.id_motivo,
				sa.justificativa,
				sa.status,
				sa.data_solicitacao,
				sa.justificativa_gestor
			FROM solicitacoes_ajuste sa
			WHERE sa.id_motorista = {$idMotorista}
			  AND sa.id_usuario_solicitante = {$idSolicitanteLogado}
			ORDER BY sa.data_solicitacao DESC
		";

		$result = query($sql);
		$linhas = [];

		if (!$result || mysqli_num_rows($result) == 0) {
			return "<p style='color:#999;'>Nenhuma solicitação de ajuste registrada.</p>";
		}

		while ($row = mysqli_fetch_assoc($result)) {
			// Mapear status para cores e textos
			$statusBadge = '';
			switch ($row['status']) {
				case 'enviada':
					$statusBadge = "<span class='badge badge-warning' style='font-size:12px; padding:5px 10px;'>Enviada</span>";
					break;
				case 'visualizada':
					$statusBadge = "<span class='badge badge-info' style='font-size:12px; padding:5px 10px;'>Em Análise</span>";
					break;
				case 'aceita':
					$statusBadge = "<span class='badge badge-success' style='font-size:12px; padding:5px 10px;'>Aceita</span>";
					break;
				case 'nao_aceita':
					$statusBadge = "<span class='badge badge-danger' style='font-size:12px; padding:5px 10px;'>Rejeitada</span>";
					break;
				case 'rascunho':
					$statusBadge = "<span class='badge badge-secondary'>Rascunho</span>";
					break;
			}

			// Botão de ação (excluir) - só disponível se status = 'enviada'
			$acoes = '';
			if ($row['status'] == 'enviada' || $row['status'] == 'rascunho') {
				$acoes = "<button type='button' class='btn btn-xs btn-danger' onclick=\"if(confirm('Tem certeza que deseja excluir esta solicitação?')) { document.getElementById('formDeleta').idSolicitacao.value = '{$row['id']}'; document.getElementById('formDeleta').submit(); }\">
					<i class='fa fa-trash'></i> Excluir
				</button>";
			} else {
				$acoes = "<span style='color:#999; font-size:12px;'>-</span>";
			}

			$linhas[] = [
				date('d/m/Y', strtotime($row['data_ajuste'])),
				$row['hora_ajuste'],
				$row['id_macro'] ? mysqli_fetch_assoc(query("SELECT macr_tx_nome FROM macroponto WHERE macr_nb_id = {$row['id_macro']} LIMIT 1"))['macr_tx_nome'] : 'N/A',
				$row['id_motivo'] ? mysqli_fetch_assoc(query("SELECT moti_tx_nome FROM motivo WHERE moti_nb_id = {$row['id_motivo']} LIMIT 1"))['moti_tx_nome'] : 'N/A',
				substr($row['justificativa'] ?? '', 0, 50) . (strlen($row['justificativa'] ?? '') > 50 ? '...' : ''),
				$statusBadge,
				date('d/m/Y H:i', strtotime($row['data_solicitacao'])),
				$row['justificativa_gestor'] ?? '-'
			];
		}

		$cabecalho = [
			"DATA DO AJUSTE",
			"HORA",
			"Tipo de Registro",
			"Motivo",
			"JUSTIFICATIVA",
			"STATUS",
			"DATA DA SOLICITAÇÃO",
			"JUSTIFICATIVA GESTOR"
		];

		return montarTabelaPonto($cabecalho, $linhas);
	}

	function gerarTabelaNaoConformidade($motorista, $dataMes) {

		$monthDate = new DateTime($dataMes . "-01");
		$rows = [];

		// Buscar solicitações para o mês
		$solicitacoes = [];
		$result = query("SELECT data_ajuste, status FROM solicitacoes_ajuste WHERE id_motorista = {$motorista['enti_nb_id']} AND DATE_FORMAT(data_ajuste, '%Y-%m') = '$dataMes'");
		while ($s = mysqli_fetch_assoc($result)) {
			$solicitacoes[$s['data_ajuste']] = $s['status'];
		}

		$dataAdmissao = new DateTime($motorista["enti_tx_admissao"]);

		for ($date = new DateTime($monthDate->format("Y-m-1")); $date->format("Y-m-d") <= $monthDate->format("Y-m-t"); $date->modify("+1 day")) {

			if ($monthDate->format("Y-m") < $dataAdmissao->format("Y-m")) {
				continue;
			}

			if ($date->format("Y-m-d") > date("Y-m-d")) {
				break;
			}

			$aDetalhado = diaDetalhePonto($motorista, $date->format("Y-m-d"));

			$statusSolicitacao = $solicitacoes[$date->format("Y-m-d")] ?? '';

			$colunasAManterZeros = ["inicioJornada","inicioRefeicao","fimRefeicao","fimJornada","jornadaPrevista","diffSaldo"];

			unset($aDetalhado['inicioEscala'],$aDetalhado['fimEscala']);

			foreach ($aDetalhado as $key => $value) {

				if (in_array($key,$colunasAManterZeros)) {
					continue;
				}

				if ($aDetalhado[$key] == "00:00") {
					$aDetalhado[$key] = "";
				}
			}

			$row = array_merge(
				[verificaTolerancia($aDetalhado["diffSaldo"],$date->format("Y-m-d"),$motorista["enti_nb_id"])],
				$aDetalhado,
				[$statusSolicitacao]
			);

			$qtdErros = 0;

			foreach ($row as $value) {

				preg_match_all("/(?<=<)([^<|>])+(?=>)/",$value,$tags);

				if (!empty($tags[0])) {

					foreach ($tags[0] as $tag) {

						$qtdErros += substr_count($tag,"fa-warning") * (substr_count($tag,"color:red;") || substr_count($tag,"color:orange;"))
						+ ((is_int(strpos($tag,"fa-info-circle"))) * (substr_count($tag,"color:red;") || substr_count($tag,"color:orange;")));

					}

				}

			}

			if (is_int(strpos($row["inicioJornada"] ?? "","Batida início de jornada não registrada!")) 
			&& is_int(strpos($row["jornadaPrevista"] ?? "","Abono: "))) {

				$qtdErros = 0;

			}

			if ($qtdErros > 0) {

				$rows[] = $row;

			}

		}

		if (empty($rows)) {

			return "<p style='color:green;'>✓ Nenhuma não conformidade encontrada para este mês.</p>";

		}

		$totalResumo = setTotalResumo(array_slice(array_keys($rows[0]),7));

		somarTotais($totalResumo,$rows);

		$cabecalho = [
			"","DATA","<div style='margin:11px'>DIA</div>","INÍCIO JORNADA","INÍCIO REFEIÇÃO","FIM REFEIÇÃO","FIM JORNADA",
			"REFEIÇÃO","DESCANSO","JORNADA",
			"JORNADA PREVISTA","JORNADA EFETIVA","INTERSTÍCIO",
			"H.E. {$motorista["enti_tx_percHESemanal"]}%","H.E. {$motorista["enti_tx_percHEEx"]}%",
			"ADICIONAL NOT.","SALDO DIÁRIO(**)","STATUS SOLICITAÇÃO"
		];

		if (in_array($motorista["enti_tx_ocupacao"],["Ajudante","Motorista"])) {

			$cabecalho = array_merge(
				array_slice($cabecalho,0,8),
				["ESPERA"],
				array_slice($cabecalho,8,1),
				["REPOUSO"],
				array_slice($cabecalho,9,3),
				["MDC"],
				array_slice($cabecalho,12,4),
				["ESPERA INDENIZADA"],
				array_slice($cabecalho,16,count($cabecalho))
			);

		}

		$rows[] = array_values(array_merge(["","","","","","","<b>TOTAL</b>"],$totalResumo));

		return montarTabelaPonto($cabecalho,$rows);

	}


	function buscarUltimaJustificativa(){
		$idMotorista = mysqli_real_escape_string($GLOBALS['conn'], $_POST['idMotorista']);
		$data = mysqli_real_escape_string($GLOBALS['conn'], $_POST['data']);
		
		$res = mysqli_fetch_assoc(query("
			SELECT justificativa 
			FROM solicitacoes_ajuste 
			WHERE id_motorista = '$idMotorista' 
			AND data_ajuste = '$data' 
			ORDER BY data_solicitacao DESC 
			LIMIT 1
		"));
		
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['justificativa' => $res['justificativa'] ?? '']);
		exit;
	}

	function verificarPontoExistentePorMacro(){
		$idMotorista = intval($_POST['idMotorista'] ?? 0);
		$idMacro = intval($_POST['idMacro'] ?? 0);
		$dataRaw = strval($_POST['data'] ?? '');
		$data = '';
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRaw)) {
			$data = $dataRaw;
		} elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dataRaw)) {
			$partes = explode('/', $dataRaw);
			$data = $partes[2] . '-' . $partes[1] . '-' . $partes[0];
		}

		header('Content-Type: application/json; charset=utf-8');

		if ($idMotorista <= 0 || empty($data) || $idMacro <= 0) {
			echo json_encode(['existe' => false]);
			exit;
		}

		$motorista = mysqli_fetch_assoc(query("
			SELECT enti_tx_matricula
			FROM entidade
			WHERE enti_nb_id = {$idMotorista}
			LIMIT 1
		"));
		if (empty($motorista['enti_tx_matricula'])) {
			echo json_encode(['existe' => false]);
			exit;
		}

		$macro = mysqli_fetch_assoc(query("
			SELECT macr_tx_nome, macr_tx_codigoInterno
			FROM macroponto
			WHERE macr_tx_status = 'ativo'
				AND macr_nb_id = {$idMacro}
			LIMIT 1
		"));
		if (empty($macro['macr_tx_codigoInterno'])) {
			echo json_encode(['existe' => false]);
			exit;
		}

		$matricula = mysqli_real_escape_string($GLOBALS['conn'], $motorista['enti_tx_matricula']);

		$ponto = mysqli_fetch_assoc(query("
			SELECT p.pont_tx_data
			FROM ponto p
			JOIN macroponto m ON (p.pont_tx_tipo = m.macr_tx_codigoInterno OR p.pont_tx_tipo = m.macr_nb_id)
			WHERE p.pont_tx_status = 'ativo'
				AND m.macr_tx_status = 'ativo'
				AND p.pont_tx_matricula = '{$matricula}'
				AND p.pont_tx_data LIKE '{$data}%'
				AND m.macr_nb_id = {$idMacro}
			ORDER BY p.pont_tx_data DESC
			LIMIT 1
		"));

		if (!empty($ponto['pont_tx_data'])) {
			echo json_encode([
				'existe' => true,
				'tipo' => $macro['macr_tx_nome'],
				'hora' => date('H:i', strtotime($ponto['pont_tx_data']))
			]);
			exit;
		}

		echo json_encode(['existe' => false]);
		exit;
	}

	function gerarTabelaRascunhos($idMotorista) {
		$idSolicitanteLogado = intval($_SESSION['user_nb_id'] ?? 0);

		$sql = "
			SELECT 
				sa.id,
				sa.data_ajuste,
				sa.hora_ajuste,
				sa.id_macro,
				sa.id_motivo,
				sa.justificativa,
				sa.data_solicitacao
			FROM solicitacoes_ajuste sa
			WHERE sa.id_motorista = {$idMotorista}
			AND sa.id_usuario_solicitante = {$idSolicitanteLogado}
			AND sa.status = 'rascunho'
			ORDER BY sa.data_solicitacao DESC
		";

		$result = query($sql);
		$linhas = [];

		if (!$result || mysqli_num_rows($result) == 0) {
			return "<p style='color:#999;'>Nenhum item na lista.</p>";
		}

		while ($row = mysqli_fetch_assoc($result)) {

			$acoes = "
				<button type='button' class='btn btn-xs btn-danger'
				onclick=\"if(confirm('Remover da lista?')) {
					document.getElementById('formDeleta').idSolicitacao.value = '{$row['id']}';
					document.getElementById('formDeleta').submit();
				}\">
				<i class='fa fa-trash'></i>
				</button>
			";

			$linhas[] = [
				date('d/m/Y', strtotime($row['data_ajuste'])),
				$row['hora_ajuste'],
				mysqli_fetch_assoc(query("SELECT macr_tx_nome FROM macroponto WHERE macr_nb_id = {$row['id_macro']}"))['macr_tx_nome'] ?? '',
				mysqli_fetch_assoc(query("SELECT moti_tx_nome FROM motivo WHERE moti_nb_id = {$row['id_motivo']}"))['moti_tx_nome'] ?? '',
				substr($row['justificativa'], 0, 40),
				date('d/m/Y H:i', strtotime($row['data_solicitacao'])),
				$acoes
			];
		}

		$cabecalho = [
			"DATA",
			"HORA",
			"TIPO",
			"MOTIVO",
			"JUSTIFICATIVA",
			"CRIADO EM",
			"AÇÃO"
		];

		return montarTabelaPonto($cabecalho, $linhas);
	}

	function index() {

		// Handler para deletar solicitação
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deletar_solicitacao'])) {
			$idSolicitacao = mysqli_real_escape_string($GLOBALS['conn'], $_POST["idSolicitacao"] ?? '');
			
			if (!empty($idSolicitacao)) {
				$verificacao = mysqli_fetch_assoc(query("
					SELECT sa.id, sa.status, sa.id_motorista, sa.data_ajuste
					FROM solicitacoes_ajuste sa
					WHERE sa.id = {$idSolicitacao} 
					AND sa.id_motorista = (
						SELECT enti_nb_id 
						FROM entidade 
						WHERE enti_nb_id = (
							SELECT user_nb_entidade 
							FROM user 
							WHERE user_nb_id = {$_SESSION['user_nb_id']}
						) LIMIT 1
					)
					LIMIT 1
				"));
				
				if ($verificacao && in_array($verificacao['status'], ['enviada','rascunho'])) {
					
					@mysqli_query($GLOBALS['conn'], "DELETE FROM solicitacoes_ajuste WHERE id = {$idSolicitacao}");

					// 🔥 Só atualiza documento se for enviada
					if ($verificacao['status'] == 'enviada') {
						processarDocumentoAgrupado($verificacao['id_motorista'], $verificacao['data_ajuste']);
					}

					header("Location: " . basename($_SERVER['PHP_SELF']) . "?msg=deletado&idMotorista=" . $verificacao['id_motorista']);
					exit;
				}
			}
		}

		if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enviar_lote'])) {
			try {
				$idMotorista = intval($_POST["idMotorista"] ?? 0);
				$idMotorista = apf_resolverMotoristaAlvo($idMotorista);
				$idMotoristaSql = mysqli_real_escape_string($GLOBALS['conn'], strval($idMotorista));
				$idSolicitanteLogado = intval($_SESSION['user_nb_id'] ?? 0);

				if ($idMotorista <= 0 || $idSolicitanteLogado <= 0) {
					echo "<script>alert('Motorista inválido');</script>";
					exit;
				}

				// 🔥 Buscar todas as datas com rascunho
				$datas = mysqli_fetch_all(query("
					SELECT DISTINCT data_ajuste 
					FROM solicitacoes_ajuste
					WHERE id_motorista = '$idMotoristaSql'
					AND id_usuario_solicitante = {$idSolicitanteLogado}
					AND status = 'rascunho'
				"), MYSQLI_ASSOC);

				if (empty($datas)) {
					echo "<script>alert('Nenhuma solicitação pendente para envio');</script>";
					exit;
				}

				// Atualizar status para 'enviada' (PDF será gerado apenas na aprovação do gestor)
				$loteDocumento = date('Y-m-d H:i:s');
				mysqli_query($GLOBALS['conn'], "
					UPDATE solicitacoes_ajuste 
					SET status = 'enviada',
					    data_envio_documento = '{$loteDocumento}'
					WHERE id_motorista = '$idMotoristaSql'
					AND id_usuario_solicitante = {$idSolicitanteLogado}
					AND status = 'rascunho'
				");

				header("Location: " . basename($_SERVER['PHP_SELF']) . "?msg=enviado&idMotorista={$idMotorista}");
				exit;

			} catch (Exception $e) {
				echo "<script>alert('Erro: " . addslashes($e->getMessage()) . "');</script>";
				exit;
			}
		}

		if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_rascunho'])) {
			try {
				$idMotorista = intval($_POST["idMotorista"] ?? 0);
				$idMotorista = apf_resolverMotoristaAlvo($idMotorista);
				$idMotorista = mysqli_real_escape_string($GLOBALS['conn'], strval($idMotorista));
				$data = mysqli_real_escape_string($GLOBALS['conn'], $_POST["data"] ?? '');
				$hora = mysqli_real_escape_string($GLOBALS['conn'], $_POST["hora"] ?? '');
				$idMacro = mysqli_real_escape_string($GLOBALS['conn'], $_POST["idMacro"] ?? '');
				$motivo = mysqli_real_escape_string($GLOBALS['conn'], $_POST["motivo"] ?? '');
				$justificativa = mysqli_real_escape_string($GLOBALS['conn'], $_POST["justificativa"] ?? '');

				if (empty($idMotorista) || empty($data) || empty($hora) || empty($idMacro) || empty($motivo)) {
					echo "<script>alert('Preencha todos os campos obrigatórios');</script>";
					exit;
				}
				
				$cargo = 'N/A';
				$setor = 'N/A';
				$subsetor = 'N/A';

				$sql_entidade = "SELECT enti_tx_tipoOperacao, enti_setor_id, enti_subSetor_id 
				FROM entidade 
				WHERE enti_nb_id = (SELECT user_nb_entidade FROM user WHERE user_nb_id = {$_SESSION['user_nb_id']}) 
				LIMIT 1";

				$entidade = mysqli_fetch_assoc(query($sql_entidade));

				if ($entidade && $entidade['enti_tx_tipoOperacao']) {
					$op = mysqli_fetch_assoc(query("SELECT oper_tx_nome FROM operacao WHERE oper_nb_id = {$entidade['enti_tx_tipoOperacao']} LIMIT 1"));
					if ($op) $cargo = $op['oper_tx_nome'];
				}

				if ($entidade && $entidade['enti_setor_id']) {
					$gr = mysqli_fetch_assoc(query("SELECT grup_tx_nome FROM grupos_documentos WHERE grup_nb_id = {$entidade['enti_setor_id']} LIMIT 1"));
					if ($gr) $setor = $gr['grup_tx_nome'];
				}

				if ($entidade && $entidade['enti_subSetor_id']) {
					$sb = mysqli_fetch_assoc(query("SELECT sbgr_tx_nome FROM sbgrupos_documentos WHERE sbgr_nb_id = {$entidade['enti_subSetor_id']} LIMIT 1"));
					if ($sb) $subsetor = $sb['sbgr_tx_nome'];
				}
				$sql = "INSERT INTO solicitacoes_ajuste 
				(id_motorista, data_ajuste, hora_ajuste, id_macro, id_motivo, justificativa, status, data_solicitacao, id_usuario_solicitante, cargo_usuario, setor_usuario, subsetor_usuario) 
				VALUES 
				('$idMotorista', '$data', '$hora', '$idMacro', '$motivo', '$justificativa', 'rascunho', NOW(), '{$_SESSION['user_nb_id']}', '$cargo', '$setor', '$subsetor')";
				
				mysqli_query($GLOBALS['conn'], $sql);

				header("Location: " . basename($_SERVER['PHP_SELF']) . "?msg=rascunho&idMotorista={$idMotorista}&data_p={$data}");
				exit;

			} catch (Exception $e) {
				echo "<script>alert('Erro: " . addslashes($e->getMessage()) . "');</script>";
				exit;
			}
		}

		if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enviar_solicitacao'])) {
			try {
				$idMotorista = intval($_POST["idMotorista"] ?? 0);
				$idMotorista = apf_resolverMotoristaAlvo($idMotorista);
				$idMotorista = mysqli_real_escape_string($GLOBALS['conn'], strval($idMotorista));
				$data = mysqli_real_escape_string($GLOBALS['conn'], $_POST["data"] ?? '');
				$hora = mysqli_real_escape_string($GLOBALS['conn'], $_POST["hora"] ?? '');
				$idMacro = mysqli_real_escape_string($GLOBALS['conn'], $_POST["idMacro"] ?? '');
				$motivo = mysqli_real_escape_string($GLOBALS['conn'], $_POST["motivo"] ?? '');
				$justificativa = mysqli_real_escape_string($GLOBALS['conn'], $_POST["justificativa"] ?? '');

				// Verificar se usuário está logado
				if (!isset($_SESSION['user_nb_id'])) {
					echo "<script>alert('Erro: Usuário não logado.');</script>";
					error_log("Ajuste Ponto: Usuário não logado");
					exit;
				}

				// Validar dados obrigatórios
				if (empty($idMotorista) || empty($data) || empty($hora) || empty($idMacro) || empty($motivo)) {
					echo "<script>alert('Erro: Todos os campos obrigatórios devem ser preenchidos.');</script>";
					error_log("Ajuste Ponto: Campos obrigatórios não preenchidos");
					exit;
				}

				// Buscar dados do motorista para validação
				$motorista_validacao = mysqli_fetch_assoc(query("
					SELECT enti_nb_id, enti_tx_matricula, enti_tx_nome, enti_tx_ocupacao,
					enti_tx_cpf, enti_tx_admissao, enti_tx_jornadaSemanal,
					enti_tx_jornadaSabado, enti_tx_percHESemanal,
					enti_tx_percHEEx, enti_nb_parametro, enti_nb_empresa
					FROM entidade
					WHERE enti_nb_id = {$idMotorista}
					LIMIT 1
				"));

				if (!$motorista_validacao) {
					echo "<script>alert('Erro: Motorista não encontrado.'); window.location.href = '" . basename($_SERVER['PHP_SELF']) . "?idMotorista={$idMotorista}';</script>";
					exit;
				}

				// Obter dados do usuário solicitante
				$sql_usuario = "SELECT user_nb_id, user_tx_nome FROM user WHERE user_nb_id = {$_SESSION['user_nb_id']} LIMIT 1";
				$usuario_base = mysqli_fetch_assoc(query($sql_usuario));

				if (!$usuario_base) {
					echo "<script>alert('Erro: Usuário não encontrado no sistema.');</script>";
					error_log("Ajuste Ponto: Usuário não encontrado");
					exit;
				}

				// Tentar buscar entidade do usuário para pegar cargo, setor, subsetor
				$cargo = 'N/A';
				$setor = 'N/A';
				$subsetor = 'N/A';

				$sql_entidade = "SELECT enti_tx_tipoOperacao, enti_setor_id, enti_subSetor_id FROM entidade WHERE enti_nb_id = (SELECT user_nb_entidade FROM user WHERE user_nb_id = {$_SESSION['user_nb_id']}) LIMIT 1";
				$entidade = mysqli_fetch_assoc(query($sql_entidade));

				if ($entidade && $entidade['enti_tx_tipoOperacao']) {
					$op = mysqli_fetch_assoc(query("SELECT oper_tx_nome FROM operacao WHERE oper_nb_id = {$entidade['enti_tx_tipoOperacao']} LIMIT 1"));
					if ($op) $cargo = $op['oper_tx_nome'];
				}

				if ($entidade && $entidade['enti_setor_id']) {
					$gr = mysqli_fetch_assoc(query("SELECT grup_tx_nome FROM grupos_documentos WHERE grup_nb_id = {$entidade['enti_setor_id']} LIMIT 1"));
					if ($gr) $setor = $gr['grup_tx_nome'];
				}

				if ($entidade && $entidade['enti_subSetor_id']) {
					$sb = mysqli_fetch_assoc(query("SELECT sbgr_tx_nome FROM sbgrupos_documentos WHERE sbgr_nb_id = {$entidade['enti_subSetor_id']} LIMIT 1"));
					if ($sb) $subsetor = $sb['sbgr_tx_nome'];
				}

				// Escapar valores finais
				$cargo = mysqli_real_escape_string($GLOBALS['conn'], $cargo);
				$setor = mysqli_real_escape_string($GLOBALS['conn'], $setor);
				$subsetor = mysqli_real_escape_string($GLOBALS['conn'], $subsetor);

				// Inserir solicitação (PDF será gerado apenas na aprovação do gestor)
				$loteDocumento = date('Y-m-d H:i:s');
				$sql = "INSERT INTO solicitacoes_ajuste (id_motorista, data_ajuste, hora_ajuste, id_macro, id_motivo, justificativa, status, data_solicitacao, data_envio_documento, id_usuario_solicitante, cargo_usuario, setor_usuario, subsetor_usuario) 
						VALUES ('$idMotorista', '$data', '$hora', '$idMacro', '$motivo', '$justificativa', 'enviada', NOW(), '$loteDocumento', '{$_SESSION['user_nb_id']}', '$cargo', '$setor', '$subsetor')";
				
				$resultado = @mysqli_query($GLOBALS['conn'], $sql);
				
				if ($resultado) {
					// Redirecionar de volta para a mesma página, mantendo data e justificativa na URL
					$justEncoded = urlencode($justificativa);
					header("Location: " . basename($_SERVER['PHP_SELF']) . "?msg=sucesso&idMotorista={$idMotorista}&data_p={$data}&just_p={$justEncoded}");
					exit;
				} else {
					$erro = mysqli_error($GLOBALS['conn']);
					echo "<script>alert('Erro ao enviar: " . addslashes($erro) . "');</script>";
				}
				exit;
			} catch (Exception $e) {
				echo "<script>alert('Erro inesperado: " . addslashes($e->getMessage()) . "');</script>";
				exit;
			}
		}

		// Buffer the output to inject script in head
		ob_start();
		cabecalho("Ajuste de Ponto");
		$cabecalho_html = ob_get_clean();

		// Injetar script no head antes de fechar
		$script_updateTimer = "
		<script>
			var timeoutId;
			window.updateTimer = function() { 
				if(typeof timeoutId !== 'undefined' && timeoutId) {
					clearTimeout(timeoutId);
				}
				timeoutId = setTimeout(function(){
					let form = document.getElementById('loginTimeoutForm');
					if(form) form.submit();
				}, 15*60*1000);
			}
		</script>";
		
		// Injetar o script antes de </head>
		$cabecalho_html = str_replace("</head>", $script_updateTimer . "\n</head>", $cabecalho_html);
		echo $cabecalho_html;

		// Mensagem de sucesso se houver
		if (isset($_GET['msg']) && $_GET['msg'] === 'sucesso') {
			echo "<script>alert('Solicitação de ajuste enviada com sucesso!');</script>";
		}
		if (isset($_GET['msg']) && $_GET['msg'] === 'deletado') {
			echo "<script>alert('Solicitação de ajuste excluída com sucesso!');</script>";
		}
		if (isset($_GET['msg']) && $_GET['msg'] === 'rascunho') {
			echo "<script>alert('Adicionado à lista!');</script>";
		}

		if (isset($_GET['msg']) && $_GET['msg'] === 'enviado') {
			echo "<script>alert('Todas as solicitações foram enviadas!');</script>";
		}

		$idMotorista = $_GET["idMotorista"] ?? $_POST["idMotorista"] ?? 0;
		$idMotorista = apf_resolverMotoristaAlvo($idMotorista);

		$motorista = mysqli_fetch_assoc(query("
			SELECT enti_nb_id, enti_tx_matricula, enti_tx_nome, enti_tx_ocupacao,
			enti_tx_cpf, enti_tx_admissao, enti_tx_jornadaSemanal,
			enti_tx_jornadaSabado, enti_tx_percHESemanal,
			enti_tx_percHEEx, enti_nb_parametro
			FROM entidade
			WHERE enti_tx_status = 'ativo'
			AND enti_nb_id = {$idMotorista}
			LIMIT 1
		"));

		$textFields = [];
		$campoJust = [];
		$botoes = [];

		$textFields[] = texto("Matrícula",$motorista["enti_tx_matricula"] ?? "",2);
		$textFields[] = texto($motorista["enti_tx_ocupacao"] ?? "Motorista",$motorista["enti_tx_nome"] ?? "",5);
		$textFields[] = texto("CPF",$motorista["enti_tx_cpf"] ?? "",3);

		// Pegar valores da URL se existirem para manter após redirecionamento
		$valData = $_GET['data_p'] ?? ($_POST["data"] ?? "");
		$valJust = $_GET['just_p'] ?? ($_POST["justificativa"] ?? "");

		$variableFields = [

			campo_data("Data*","data",$valData,2,"id='dataFiltro'"),
			campo_hora("Hora*","hora",($_POST["hora"] ?? ""),2),

			combo_bd("Tipo de Registro*","idMacro",($_POST["idMacro"] ?? ""),4,"macroponto","","ORDER BY macr_nb_id"),

			combo_bd("Motivo*","motivo",($_POST["motivo"] ?? ""),4,"motivo",""," AND moti_tx_tipo = 'Ajuste' ORDER BY moti_tx_nome"),

			"<div class='col-sm-12' style='margin-bottom:10px;'>
				<div class='alert alert-info' style='margin-bottom:0; padding:10px;'>
					<i class='fa fa-info-circle'></i> <b>Nota:</b> Solicitações para o mesmo dia serão agrupadas em um único documento de 'Solicitação de Ajuste'.
				</div>
			</div>"

		];

		$campoJust[] = textarea("Justificativa","justificativa",$valJust,12,'maxlength=680 id="campoJustificativa"');

		$botoes[] = "<button type='submit' name='salvar_rascunho' id='btnRascunho' class='btn btn-primary'>Adicionar à Lista</button>";
		$botoes[] = "<button type='submit' name='enviar_lote' class='btn btn-success'>Enviar Todas</button>";
		$botoes[] = "<button type='button' id='btnUsarUltima' class='btn btn-info' style='display:none;' title='Uma solicitação já foi enviada para este dia. Clique para aplicar a mesma justificativa.'>Repetir Justificativa do Dia</button>";
		$botoes[] = criarBotaoVoltar("espelho_ponto.php");

		echo abre_form("Dados do Ajuste de Ponto");
		echo "<input type='hidden' name='idMotorista' value='{$idMotorista}'>";
		//echo "<input type='hidden' name='enviar_solicitacao' value='1'>";
		echo "<input type='hidden' name='confirmado_existente' id='confirmado_existente' value='0'>";
		echo linha_form($textFields);
		echo linha_form($variableFields);
		echo linha_form($campoJust);
		echo fecha_form($botoes);

		// Formulário hidden para delete
		echo "<form id='formDeleta' method='POST' style='display:none;'>
			<input type='hidden' name='deletar_solicitacao' value='true'>
			<input type='hidden' name='idSolicitacao' value=''>
		</form>";

		$dataMes = date("Y-m");

		echo "<h3>Não Conformidades </h3>";//.date("m/Y",strtotime($dataMes."-01"))."</h3>";

		echo "<div id='tabelaNaoConformidadeContainer'>";
		echo gerarTabelaNaoConformidade($motorista,$dataMes);
		echo "</div>";

		echo "<div id='mensagemSemDados' style='display:none'>
		<p style='color:green;'>✓ Nenhuma não conformidade encontrada para o dia selecionado.</p>
		</div>";

		// Nova tabela de solicitações
		echo "<hr style='margin-top:40px;'>";
		echo "<hr style='margin-top:40px;'>";
		echo "<h3>🟡 Lista de Envio (Pendentes)</h3>";
		echo "<div>";
		$idMotoristaAtual = $motorista['enti_nb_id'] ?? $idMotorista ?? 0;
		echo gerarTabelaRascunhos($idMotoristaAtual);
		echo "</div>";
		echo "<h3 style='margin-top:30px;'>📄 Histórico de Solicitações</h3>";
		echo "<div id='tabelaSolicitacoesContainer'>";
		echo gerarTabelaSolicitacoes($motorista['enti_nb_id']);
		echo "</div>";

		echo "
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			const form = document.querySelector('form[name=\"contex_form\"]');
			const campoData = (form && form.elements && form.elements['data']) ? form.elements['data'] : document.querySelector('[name=\"data\"]');
			const campoMacro = (form && form.elements && form.elements['idMacro']) ? form.elements['idMacro'] : document.querySelector('[name=\"idMacro\"]');
			const campoJustificativa = (form && form.elements && form.elements['justificativa']) ? form.elements['justificativa'] : document.querySelector('[name=\"justificativa\"]');
			const idMotoristaEl = document.querySelector('input[name=\"idMotorista\"]');
			const confirmadoEl = document.getElementById('confirmado_existente');
			const btnUsarUltima = document.getElementById('btnUsarUltima');

			const idMotorista = idMotoristaEl ? idMotoristaEl.value : '';

			function checarJustificativaExistente() {
				if (!campoData || !btnUsarUltima || !campoJustificativa || !idMotorista) return;

				const data = campoData.value;
				if (!data) {
					btnUsarUltima.style.display = 'none';
					return;
				}

				fetch(window.location.pathname, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'acao=buscarUltimaJustificativa&idMotorista=' + encodeURIComponent(idMotorista) + '&data=' + encodeURIComponent(data)
				})
				.then(function (response) { return response.json(); })
				.then(function (payload) {
					if (payload && payload.justificativa) {
						btnUsarUltima.style.display = 'inline-block';
						btnUsarUltima.onclick = function () {
							campoJustificativa.value = payload.justificativa;
						};
					} else {
						btnUsarUltima.style.display = 'none';
					}
				})
				.catch(function () {
					btnUsarUltima.style.display = 'none';
				});
			}

			function filtrar() {
				if (!campoData) return;

				const dataSelecionada = campoData.value;
				const tabela = document.querySelector('#tabelaNaoConformidadeContainer table');
				if (!tabela) return;

				const linhas = tabela.querySelectorAll('tbody tr');
				if (!dataSelecionada) {
					linhas.forEach(function (l) { l.style.display = ''; });
					const msg = document.getElementById('mensagemSemDados');
					if (msg) msg.style.display = 'none';
					return;
				}

				const dataFormatada = new Date(dataSelecionada).toLocaleDateString('pt-BR', { timeZone: 'UTC' });
				let encontrou = false;

				linhas.forEach(function (linha) {
					const celulaData = linha.querySelector('td:nth-child(2)');
					if (!celulaData) return;

					if (linha.innerText.includes('TOTAL')) {
						linha.style.display = 'none';
						return;
					}

					if (celulaData.textContent.trim() === dataFormatada) {
						linha.style.display = '';
						encontrou = true;
					} else {
						linha.style.display = 'none';
					}
				});

				const msg = document.getElementById('mensagemSemDados');
				if (msg) msg.style.display = encontrou ? 'none' : 'block';
			}

			if (campoData) {
				campoData.addEventListener('change', function () { filtrar(); checarJustificativaExistente(); });
				campoData.addEventListener('input', function () { filtrar(); checarJustificativaExistente(); });
				if (campoData.value) checarJustificativaExistente();
			}

			if (form) {
				form.addEventListener('submit', function (event) {

						const btnName = document.activeElement ? document.activeElement.name : '';

						if (btnName === 'salvar_rascunho' || btnName === 'enviar_lote') {
							return; // 🔥 deixa seguir direto pro PHP
						}

						if (!confirmadoEl || confirmadoEl.value === '1') return;

						const data = campoData ? campoData.value : '';
						const idMacro = campoMacro ? campoMacro.value : '';
						if (!data || !idMotorista || !idMacro) return;

						event.preventDefault();

						fetch(window.location.pathname, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: 'acao=verificarPontoExistentePorMacro&idMotorista=' + encodeURIComponent(idMotorista) + '&data=' + encodeURIComponent(data) + '&idMacro=' + encodeURIComponent(idMacro)
						})
					.then(function (response) { return response.json(); })
					.then(function (payload) {
						if (payload && payload.existe) {
							const tipo = payload.tipo ? payload.tipo : 'este tipo de registro';
							const hora = payload.hora ? payload.hora : '';
							const msg = 'Já existe ' + tipo + (hora ? (' às ' + hora) : '') + ' registrado neste dia.\\n\\nDeseja prosseguir com a solicitação para substituir?';
							if (!confirm(msg)) return;
						}

						confirmadoEl.value = '1';
						form.submit();
					})
					.catch(function () {
						const msg = 'Não foi possível validar se já existe ponto para este dia.\\n\\nDeseja enviar a solicitação mesmo assim?';
						if (!confirm(msg)) return;
						confirmadoEl.value = '1';
						form.submit();
					});
				});
			}
		});
		</script>
		";

		rodape();

	}

	index();
?>
