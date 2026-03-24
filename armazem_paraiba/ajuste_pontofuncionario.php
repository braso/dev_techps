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
		// Adicionar colunas em solicitacoes_ajuste se necessário
		$check = query("SHOW COLUMNS FROM solicitacoes_ajuste LIKE 'id_instancia_documento'");
		if ($check instanceof mysqli_result && mysqli_num_rows($check) == 0) {
			query("ALTER TABLE solicitacoes_ajuste ADD COLUMN id_instancia_documento INT NULL AFTER data_decisao");
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

	function processarDocumentoAgrupado($idMotorista, $data) {
		global $conn;

		// 1. Identificar o tipo de documento de ajuste
		// Priorizar busca exata conforme exibido na interface do usuário
		$tipoDoc = mysqli_fetch_assoc(query("SELECT tipo_nb_id FROM tipos_documentos WHERE tipo_tx_status = 'ativo' AND (tipo_tx_nome = 'Ajuste Ponto' OR tipo_tx_nome = 'Solicitação de Ajuste') LIMIT 1"));
		if (!$tipoDoc) {
			$tipoDoc = mysqli_fetch_assoc(query("SELECT tipo_nb_id FROM tipos_documentos WHERE tipo_tx_status = 'ativo' AND (tipo_tx_nome LIKE '%Ajuste Ponto%' OR tipo_tx_nome LIKE '%Solicitação de Ajuste%') LIMIT 1"));
		}
		$idTipo = $tipoDoc['tipo_nb_id'] ?? 0;

		if (!$idTipo) return; // Não há modelo de documento configurado

		// 2. Buscar todas as solicitações do dia para este motorista que estão em estado editável ('enviada')
		$solicitacoes = mysqli_fetch_all(query("
			SELECT sa.*, m.macr_tx_nome, mot.moti_tx_nome
			FROM solicitacoes_ajuste sa
			LEFT JOIN macroponto m ON sa.id_macro = m.macr_nb_id
			LEFT JOIN motivo mot ON sa.id_motivo = mot.moti_nb_id
			WHERE sa.id_motorista = $idMotorista 
			  AND sa.data_ajuste = '$data'
			  AND sa.status = 'enviada'
			ORDER BY sa.hora_ajuste ASC
		"), MYSQLI_ASSOC);

		if (empty($solicitacoes)) return;

		// 3. Tentar encontrar uma instância existente vinculada a estas solicitações
		$idInstancia = 0;
		foreach ($solicitacoes as $s) {
			if (!empty($s['id_instancia_documento'])) {
				$idInstancia = $s['id_instancia_documento'];
				break;
			}
		}

		// 4. Se não achou por vínculo direto, busca na tabela de instâncias por data/motorista
		if (!$idInstancia) {
			// Garantir que a coluna de vínculo existe
			$checkCol = query("SHOW COLUMNS FROM inst_documento_modulo LIKE 'inst_nb_entidade'");
			if (!($checkCol instanceof mysqli_result) || mysqli_num_rows($checkCol) == 0) {
				criarTabelaSolicitacoes();
			}

			$instancia = mysqli_fetch_assoc(query("
				SELECT inst_nb_id 
				FROM inst_documento_modulo 
				WHERE inst_nb_tipo_doc = $idTipo 
				  AND inst_nb_entidade = $idMotorista 
				  AND inst_tx_data_referencia = '$data'
				  AND inst_tx_status = 'ativo'
				LIMIT 1
			"));
			
			if ($instancia) {
				$idInstancia = $instancia['inst_nb_id'];
			}
		}

		// 5. Validar a instância encontrada (Trava de segurança: se houver solicitações não-enviadas nela, criar nova)
		if ($idInstancia) {
			$travado = mysqli_fetch_assoc(query("SELECT id FROM solicitacoes_ajuste WHERE id_instancia_documento = $idInstancia AND status != 'enviada' LIMIT 1"));
			if ($travado) {
				$idInstancia = 0;
			}
		}

		// 6. Persistir a instância (Criar se não existir)
		$motorista = mysqli_fetch_assoc(query("SELECT * FROM entidade WHERE enti_nb_id = $idMotorista"));
		$matricula = $motorista['enti_tx_matricula'];

		if (!$idInstancia) {
			query("INSERT INTO inst_documento_modulo (inst_nb_tipo_doc, inst_nb_user, inst_nb_entidade, inst_tx_data_referencia, inst_dt_criacao, inst_tx_status) 
				   VALUES ($idTipo, {$_SESSION['user_nb_id']}, $idMotorista, '$data', '".date('Y-m-d H:i:s')."', 'ativo')");
			$idInstancia = mysqli_insert_id($GLOBALS['conn']);
		}

		// 7. Vincular TODAS as solicitações do dia a esta instância
		query("UPDATE solicitacoes_ajuste SET id_instancia_documento = $idInstancia WHERE id_motorista = $idMotorista AND data_ajuste = '$data' AND status = 'enviada'");

		// 8. Montar o conteúdo textual consolidado
		$resumoAjustes = "Solicitações de Ajuste para o dia " . date('d/m/Y', strtotime($data)) . ":\n\n";
		foreach ($solicitacoes as $s) {
			$existente = verificarPontoExistente($matricula, $data, $s['hora_ajuste']);
			$resumoAjustes .= "- Horário: {$s['hora_ajuste']} | Tipo: {$s['macr_tx_nome']} | Motivo: {$s['moti_tx_nome']}\n";
			$resumoAjustes .= "  Justificativa: {$s['justificativa']}\n";
			$resumoAjustes .= "  Status no Banco: $existente\n\n";
		}

		// 9. Preencher/Atualizar os campos do documento
		$campos = mysqli_fetch_all(query("SELECT * FROM camp_documento_modulo WHERE camp_nb_tipo_doc = $idTipo AND camp_tx_status = 'ativo'"), MYSQLI_ASSOC);
		foreach ($campos as $c) {
			$label = mb_strtoupper($c['camp_tx_label'], 'UTF-8');
			$valor = '';

			if (strpos($label, 'DATA') !== false) {
				$valor = date('d/m/Y', strtotime($data));
			} elseif (strpos($label, 'NOME') !== false || strpos($label, 'FUNCIONÁRIO') !== false) {
				$valor = $motorista['enti_tx_nome'];
			} elseif (strpos($label, 'MATRÍCULA') !== false) {
				$valor = $matricula;
			} elseif (strpos($label, 'CPF') !== false) {
				$valor = $motorista['enti_tx_cpf'];
			} elseif (strpos($label, 'JUSTIFICATIVA') !== false || strpos($label, 'DESCRIÇÃO') !== false || strpos($label, 'DETALHE') !== false || strpos($label, 'RESUMO') !== false) {
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
				sa.data_solicitacao
			FROM solicitacoes_ajuste sa
			WHERE sa.id_motorista = {$idMotorista}
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
			}

			// Botão de ação (excluir) - só disponível se status = 'enviada'
			$acoes = '';
			if ($row['status'] == 'enviada') {
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
				$acoes
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
			"AÇÕES"
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

	function index() {

		// Handler para deletar solicitação
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deletar_solicitacao'])) {
			$idSolicitacao = mysqli_real_escape_string($GLOBALS['conn'], $_POST["idSolicitacao"] ?? '');
			
			if (!empty($idSolicitacao)) {
				// Verificar se a solicitação existe e pertence ao usuário logado
				$verificacao = mysqli_fetch_assoc(query("
					SELECT sa.id, sa.status, sa.id_motorista 
					FROM solicitacoes_ajuste sa
					WHERE sa.id = {$idSolicitacao} 
					AND sa.id_motorista = (SELECT enti_nb_id FROM entidade WHERE enti_nb_id = (SELECT user_nb_entidade FROM user WHERE user_nb_id = {$_SESSION['user_nb_id']}) LIMIT 1)
					LIMIT 1
				"));
				
				if ($verificacao && $verificacao['status'] == 'enviada') {
					// Deletar apenas se status for 'enviada'
					@mysqli_query($GLOBALS['conn'], "DELETE FROM solicitacoes_ajuste WHERE id = {$idSolicitacao}");
					
					// Sincronizar documento após exclusão
					processarDocumentoAgrupado($verificacao['id_motorista'], $verificacao['data_ajuste']);
					
					header("Location: " . basename($_SERVER['PHP_SELF']) . "?msg=deletado&idMotorista=" . $verificacao['id_motorista']);
					exit;
				}
			}
		}

		if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enviar_solicitacao'])) {
			try {
				$idMotorista = mysqli_real_escape_string($GLOBALS['conn'], $_POST["idMotorista"] ?? '');
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

				// Inserir solicitação
				$sql = "INSERT INTO solicitacoes_ajuste (id_motorista, data_ajuste, hora_ajuste, id_macro, id_motivo, justificativa, status, data_solicitacao, id_usuario_solicitante, cargo_usuario, setor_usuario, subsetor_usuario) 
						VALUES ('$idMotorista', '$data', '$hora', '$idMacro', '$motivo', '$justificativa', 'enviada', NOW(), '{$_SESSION['user_nb_id']}', '$cargo', '$setor', '$subsetor')";
				
				$resultado = @mysqli_query($GLOBALS['conn'], $sql);
				
				if ($resultado) {
					// Sincronizar documento após inserção
					processarDocumentoAgrupado($idMotorista, $data);
					
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

		$idMotorista = $_GET["idMotorista"] ?? $_POST["idMotorista"] ?? 0;

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

		$botoes[] = "<button type='submit' class='btn btn-success'>Enviar Solicitação</button>";
		$botoes[] = "<button type='button' id='btnUsarUltima' class='btn btn-info' style='display:none;' title='Uma solicitação já foi enviada para este dia. Clique para aplicar a mesma justificativa.'>Repetir Justificativa do Dia</button>";
		$botoes[] = criarBotaoVoltar("espelho_ponto.php");

		echo abre_form("Dados do Ajuste de Ponto");
		echo "<input type='hidden' name='idMotorista' value='{$idMotorista}'>";
		echo "<input type='hidden' name='enviar_solicitacao' value='1'>";
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
		echo "<h3>Solicitações de Ajuste</h3>";
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
