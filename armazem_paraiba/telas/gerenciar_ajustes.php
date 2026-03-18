<?php
	include "../funcoes_ponto.php";

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
		
		// Buscar detalhes da solicitação
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

			// Preparar os dados para inserir na tabela ponto
			// conferirErroPonto($matricula, DateTime $dataPonto, int $idMacro, int $motivo = 0, string $justificativa = "")
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
			
			// Conferir se já existe um ponto no mesmo segundo (lógica de ajuste_ponto.php)
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
				// Inativar o ponto antigo antes de inserir o novo (substituição)
				query("UPDATE ponto SET pont_tx_status = 'inativo' 
					   WHERE pont_tx_status = 'ativo'
					   AND pont_tx_matricula = '{$sol['enti_tx_matricula']}'
					   AND STR_TO_DATE(pont_tx_data, '%Y-%m-%d %H:%i') = STR_TO_DATE('{$sol['data_ajuste']} {$sol['hora_ajuste']}', '%Y-%m-%d %H:%i')");
			}

			// Inserir na tabela ponto
			inserir("ponto", array_keys($newPonto), array_values($newPonto));

			// Atualizar status da solicitação
			query("UPDATE solicitacoes_ajuste SET status = 'aceita', data_decisao = NOW(), id_superior = {$_SESSION['user_nb_id']} WHERE id = '$idSolicitacao'");

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
		query("UPDATE solicitacoes_ajuste SET status = 'nao_aceita', data_decisao = NOW(), id_superior = {$_SESSION['user_nb_id']} WHERE id = '$idSolicitacao'");
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

	function index(){
		include_once "../check_permission.php";
		// Como o arquivo está em uma pasta, a permissão deve considerar o caminho relativo ou absoluto
		// No banco de dados, geralmente salvamos o caminho relativo à raiz.
		verificaPermissao('/telas/gerenciar_ajustes.php');

		if (empty($_POST['msg_status']) && isset($_GET['msg_status']) && $_GET['msg_status'] !== '') {
			$_POST['msg_status'] = $_GET['msg_status'];
		}
		if (empty($_POST['motorista']) && isset($_GET['motorista']) && $_GET['motorista'] !== '') {
			$_POST['motorista'] = $_GET['motorista'];
		}
		if (empty($_POST['status_filtro']) && isset($_GET['status_filtro']) && $_GET['status_filtro'] !== '') {
			$_POST['status_filtro'] = $_GET['status_filtro'];
		}
		if (empty($_POST['cargo_filtro']) && isset($_GET['cargo_filtro']) && $_GET['cargo_filtro'] !== '') {
			$_POST['cargo_filtro'] = $_GET['cargo_filtro'];
		}
		if (empty($_POST['setor_filtro']) && isset($_GET['setor_filtro']) && $_GET['setor_filtro'] !== '') {
			$_POST['setor_filtro'] = $_GET['setor_filtro'];
		}
		if (empty($_POST['subsetor_filtro']) && isset($_GET['subsetor_filtro']) && $_GET['subsetor_filtro'] !== '') {
			$_POST['subsetor_filtro'] = $_GET['subsetor_filtro'];
		}

		cabecalho("Gerenciar Ajustes de Ponto");

		$listaTexto = function($valor){
			$valor = strval($valor ?? '');
			$partes = array_map('trim', explode(',', $valor));
			return array_values(array_filter($partes, function($v){ return $v !== ''; }));
		};
		$listaInt = function($valor){
			$lista = array_map('intval', array_map('trim', explode(',', strval($valor ?? ''))));
			$lista = array_values(array_filter($lista, function($v){ return $v > 0; }));
			return array_values(array_unique($lista));
		};

		// Filtros
		$idMotorista = $_POST['motorista'] ?? '';
		$statusFiltro = $_POST['status_filtro'] ?? 'pendentes'; // pendentes, aceitas, rejeitadas, todas
		$cargoFiltro = $_POST['cargo_filtro'] ?? '';
		$setorFiltro = $_POST['setor_filtro'] ?? '';
		$subsetorFiltro = $_POST['subsetor_filtro'] ?? '';

		$extra_sql = "";
		$motoristasSelecionados = $listaInt($idMotorista);
		if (!empty($motoristasSelecionados)) {
			$extra_sql .= " AND s.id_motorista IN (" . implode(',', $motoristasSelecionados) . ")";
		}

		if ($statusFiltro == 'pendentes') {
			$extra_sql .= " AND s.status IN ('enviada', 'visualizada')";
		} elseif ($statusFiltro == 'aceitas') {
			$extra_sql .= " AND s.status = 'aceita'";
		} elseif ($statusFiltro == 'rejeitadas') {
			$extra_sql .= " AND s.status = 'nao_aceita'";
		}

		$cargosSelecionados = $listaTexto($cargoFiltro);
		if (!empty($cargosSelecionados)) {
			$cargosSql = array_map(function($v){
				return "'" . mysqli_real_escape_string($GLOBALS['conn'], $v) . "'";
			}, $cargosSelecionados);
			$extra_sql .= " AND s.cargo_usuario IN (" . implode(',', $cargosSql) . ")";
		}
		$setoresSelecionados = $listaTexto($setorFiltro);
		if (!empty($setoresSelecionados)) {
			$setoresSql = array_map(function($v){
				return "'" . mysqli_real_escape_string($GLOBALS['conn'], $v) . "'";
			}, $setoresSelecionados);
			$extra_sql .= " AND s.setor_usuario IN (" . implode(',', $setoresSql) . ")";
		}
		$subsetoresSelecionados = $listaTexto($subsetorFiltro);
		if (!empty($subsetoresSelecionados)) {
			$subsetoresSql = array_map(function($v){
				return "'" . mysqli_real_escape_string($GLOBALS['conn'], $v) . "'";
			}, $subsetoresSelecionados);
			$extra_sql .= " AND s.subsetor_usuario IN (" . implode(',', $subsetoresSql) . ")";
		}

		// Buscar solicitações
		$sql = "
			SELECT 
				s.*, 
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
			WHERE 1 $extra_sql
			ORDER BY s.data_solicitacao DESC
		";

		$result = query($sql);
		$dados = ($result instanceof mysqli_result) ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
		if (!($result instanceof mysqli_result)) {
			set_status("ERRO: Falha ao buscar solicitações.");
		}
		
		// Identificar duplicidades (mesmo funcionário no mesmo dia)
		$contagemDuplicados = [];
		foreach ($dados as $d) {
			$chave = $d['id_motorista'] . '_' . $d['data_ajuste'];
			$contagemDuplicados[$chave] = ($contagemDuplicados[$chave] ?? 0) + 1;
		}

		$linhas = [];
		foreach ($dados as $row) {
			$statusBadge = '';
			switch ($row['status']) {
				case 'enviada': $statusBadge = "<span class='badge badge-warning'>Enviada</span>"; break;
				case 'visualizada': $statusBadge = "<span class='badge badge-info'>Visualizada</span>"; break;
				case 'aceita': $statusBadge = "<span class='badge badge-success'>Aceita</span>"; break;
				case 'nao_aceita': $statusBadge = "<span class='badge badge-danger'>Rejeitada</span>"; break;
			}

			// Alerta de duplicidade
			$alertaDuplicidade = "";
			$estiloCelulaDuplicada = "";
			$chave = $row['id_motorista'] . '_' . $row['data_ajuste'];
			if ($contagemDuplicados[$chave] > 1) {
				$alertaDuplicidade = "<i class='fa fa-exclamation-triangle text-danger' title='Existem {$contagemDuplicados[$chave]} solicitações para este funcionário neste mesmo dia.' style='cursor:help; margin-left:5px;'></i>";
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
						<button type='button' class='btn btn-xs btn-success' title='Aprovar' onclick=\"aprovarSolicitacao('{$row['id']}')\"><i class='fa fa-check'></i></button>
						<button type='button' class='btn btn-xs btn-danger' title='Rejeitar' onclick=\"if(confirm('Rejeitar este ajuste?')) { document.form_acao.id_solicitacao.value='{$row['id']}'; document.form_acao.acao.value='rejeitarSolicitacao'; document.form_acao.submit(); }\"><i class='fa fa-times'></i></button>
					</div>
				";
			} else {
				$cor = ($row['status'] == 'aceita' ? 'green' : 'red');
				$acoes = "<small style='color:$cor'><b>" . ($row['status'] == 'aceita' ? 'Aprovado' : 'Rejeitado') . "</b></small>";
			}

			$cargoDisp = ($row['cargo_usuario'] == 'N/A') ? '' : $row['cargo_usuario'];
			$setorDisp = ($row['setor_usuario'] == 'N/A') ? '' : $row['setor_usuario'];
			$subSetorDisp = ($row['subsetor_usuario'] == 'N/A') ? '' : $row['subsetor_usuario'];

			$solicitanteInfo = "<small><b>C:</b> $cargoDisp<br><b>S:</b> $setorDisp</small>";

			$linhas[] = [
				$checkbox,
				date('d/m/Y H:i', strtotime($row['data_solicitacao'])),
				"<div $estiloCelulaDuplicada><b>{$row['enti_tx_nome']}</b> $alertaDuplicidade<br><small>{$row['enti_tx_matricula']}</small></div>",
				"<b>" . date('d/m/Y', strtotime($row['data_ajuste'])) . "</b><br>" . $row['hora_ajuste'],
				$row['macr_tx_nome'],
				$row['moti_tx_nome'],
				"<span title='{$row['justificativa']}' style='cursor:help;'>" . (strlen($row['justificativa']) > 20 ? substr($row['justificativa'], 0, 20) . "..." : $row['justificativa']) . "</span>",
				$solicitanteInfo,
				$statusBadge,
				$acoes
			];
		}

		$cabecalho_tabela = ["<input type='checkbox' id='sel-tudo'>", "Solicitado", "Funcionário", "Data/Hora Ajuste", "Tipo", "Motivo", "Justificativa", "Solicitante", "Status", "Ações"];

		// Buscar opções para os combos de filtro
		$resCargos = query("SELECT DISTINCT cargo_usuario FROM solicitacoes_ajuste WHERE cargo_usuario IS NOT NULL AND cargo_usuario != '' AND cargo_usuario != 'N/A' ORDER BY cargo_usuario");
		$cargos = ($resCargos instanceof mysqli_result) ? mysqli_fetch_all($resCargos, MYSQLI_ASSOC) : [];
		$resSetores = query("SELECT DISTINCT setor_usuario FROM solicitacoes_ajuste WHERE setor_usuario IS NOT NULL AND setor_usuario != '' AND setor_usuario != 'N/A' ORDER BY setor_usuario");
		$setores = ($resSetores instanceof mysqli_result) ? mysqli_fetch_all($resSetores, MYSQLI_ASSOC) : [];
		
		$subsetores = [];
		if (!empty($setoresSelecionados)) {
			$setoresSql = array_map(function($v){
				return "'" . mysqli_real_escape_string($GLOBALS['conn'], $v) . "'";
			}, $setoresSelecionados);
			$resSub = query("SELECT DISTINCT subsetor_usuario FROM solicitacoes_ajuste WHERE setor_usuario IN (" . implode(',', $setoresSql) . ") AND subsetor_usuario IS NOT NULL AND subsetor_usuario != '' AND subsetor_usuario != 'N/A' ORDER BY subsetor_usuario");
			$subsetores = ($resSub instanceof mysqli_result) ? mysqli_fetch_all($resSub, MYSQLI_ASSOC) : [];
		}

		function montarComboFiltro($label, $nome, $valorAtual, $opcoes, $campoBanco, $tamanho = 2, $id = "", $disabled = false) {
			$idAttr = $id ? "id='$id'" : "";
			$disabledAttr = $disabled ? "disabled" : "";
			$html = "<div class='col-sm-$tamanho margin-bottom-5'>
				<label>$label</label>
				<select name='$nome' $idAttr $disabledAttr class='form-control input-sm'>
					<option value=''>Todos</option>";
			foreach ($opcoes as $opt) {
				$val = $opt[$campoBanco];
				$selected = ($valorAtual == $val) ? 'selected' : '';
				$html .= "<option value='$val' $selected>$val</option>";
			}
			$html .= "</select></div>";
			return $html;
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

		$resMotoristas = query("SELECT enti_nb_id, enti_tx_matricula, enti_tx_nome FROM entidade WHERE enti_tx_status = 'ativo' AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário') ORDER BY enti_tx_matricula");
		$motoristasRows = ($resMotoristas instanceof mysqli_result) ? mysqli_fetch_all($resMotoristas, MYSQLI_ASSOC) : [];
		$motoristasOpcoes = array_map(function($row){
			return [
				"value" => strval($row["enti_nb_id"]),
				"label" => trim(($row["enti_tx_matricula"] ?? '') . " - " . ($row["enti_tx_nome"] ?? ''))
			];
		}, $motoristasRows);

		$cargosOpcoes = array_map(function($row){
			return ["value" => strval($row["cargo_usuario"]), "label" => strval($row["cargo_usuario"])];
		}, $cargos);
		$setoresOpcoes = array_map(function($row){
			return ["value" => strval($row["setor_usuario"]), "label" => strval($row["setor_usuario"])];
		}, $setores);
		$subsetoresOpcoes = array_map(function($row){
			return ["value" => strval($row["subsetor_usuario"]), "label" => strval($row["subsetor_usuario"])];
		}, $subsetores);

		// Formulário de Filtros
		$campos_filtro = [
			montarMultiSelectFiltro("Funcionário", "motorista", array_map('strval', $motoristasSelecionados), $motoristasOpcoes, 3, "combo_motorista"),
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

		// Botões de Ação em Lote
		echo "
		<div class='row margin-bottom-10'>
			<div class='col-sm-12'>
				<button type='button' class='btn btn-sm btn-success' id='btn-aprovar-lote' style='display:none;' onclick=\"processarLote('aceitarLote')\"><i class='fa fa-check'></i> Aprovar Selecionados</button>
				<button type='button' class='btn btn-sm btn-danger' id='btn-rejeitar-lote' style='display:none;' onclick=\"processarLote('rejeitarLote')\"><i class='fa fa-times'></i> Rejeitar Selecionados</button>
			</div>
		</div>
		";

		// Script para dependência Setor -> Subsetor e Ações em Lote
		echo "
		<script>
		(function(){
			function closeAllMultiSelect(){
				document.querySelectorAll('.ms-menu').forEach(function(menu){
					menu.style.display = 'none';
				});
			}

			document.addEventListener('click', function(){
				closeAllMultiSelect();
			});

			window.__msCloseAll = closeAllMultiSelect;
		})();

		function aprovarSolicitacao(idSolicitacao){
			if(!confirm('Aprovar este ajuste?')) return;

			fetch('" . basename($_SERVER['PHP_SELF']) . "', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'acao=verificarPontoExistenteSolicitacao&id_solicitacao=' + encodeURIComponent(idSolicitacao)
			})
			.then(function(r){ return r.json(); })
			.then(function(payload){
				let permitir = '0';
				if(payload && payload.existe){
					const tipo = payload.tipo ? payload.tipo : 'este tipo de registro';
					const hora = payload.hora ? payload.hora : '';
					const msg = 'Já existe ' + tipo + (hora ? (' às ' + hora) : '') + ' registrado neste dia.\\n\\nDeseja substituir?';
					if(!confirm(msg)) return;
					permitir = '1';
				}
				document.form_acao.id_solicitacao.value = idSolicitacao;
				document.form_acao.acao.value = 'aceitarSolicitacao';
				document.form_acao.permitir_substituir.value = permitir;
				document.form_acao.submit();
			})
			.catch(function(){
				document.form_acao.id_solicitacao.value = idSolicitacao;
				document.form_acao.acao.value = 'aceitarSolicitacao';
				document.form_acao.permitir_substituir.value = '0';
				document.form_acao.submit();
			});
		}

		function processarLote(acao) {
			const selecionados = Array.from(document.querySelectorAll('.sel-lote:checked')).map(cb => cb.value);
			if (selecionados.length === 0) return;
			
			const confirmMsg = acao === 'aceitarLote' ? 'Aprovar todos os selecionados?' : 'Rejeitar todos os selecionados?';
			if (!confirm(confirmMsg)) return;

			if (acao === 'aceitarLote') {
				fetch('" . basename($_SERVER['PHP_SELF']) . "', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'acao=verificarPontoExistenteLote&ids=' + encodeURIComponent(selecionados.join(','))
				})
				.then(function(r){ return r.json(); })
				.then(function(payload){
					let permitir = '0';
					if(payload && payload.existe){
						const msg = 'Existem ' + payload.qtd + ' registros que já possuem ponto lançado.\\n\\nDeseja substituir para todos os selecionados?';
						if(!confirm(msg)) return;
						permitir = '1';
					}
					document.form_lote.ids_selecionados.value = selecionados.join(',');
					document.form_lote.acao_lote.value = acao;
					document.form_lote.permitir_substituir.value = permitir;
					document.form_lote.submit();
				})
				.catch(function(){
					document.form_lote.ids_selecionados.value = selecionados.join(',');
					document.form_lote.acao_lote.value = acao;
					document.form_lote.permitir_substituir.value = '0';
					document.form_lote.submit();
				});
				return;
			}

			document.form_lote.ids_selecionados.value = selecionados.join(',');
			document.form_lote.acao_lote.value = acao;
			document.form_lote.permitir_substituir.value = '0';
			document.form_lote.submit();
		}

		document.addEventListener('DOMContentLoaded', function() {
			// Lógica do Checkbox 'Selecionar Tudo'
			const selTudo = document.getElementById('sel-tudo');
			const checks = document.querySelectorAll('.sel-lote');
			const btnAprovar = document.getElementById('btn-aprovar-lote');
			const btnRejeitar = document.getElementById('btn-rejeitar-lote');

			function toggleBotoesLote() {
				const temSelecionado = document.querySelectorAll('.sel-lote:checked').length > 0;
				btnAprovar.style.display = temSelecionado ? 'inline-block' : 'none';
				btnRejeitar.style.display = temSelecionado ? 'inline-block' : 'none';
			}

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

				function sync(){
					const checked = Array.from(menu.querySelectorAll('input[type=\"checkbox\"]:checked')).map(cb => cb.value);
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
					if(e.target && e.target.matches('input[type=\"checkbox\"]')) sync();
				});

				sync();
				return { root, hidden, btn, countEl, menu, sync };
			}

			const msMotorista = initMultiSelect('combo_motorista');
			const msCargo = initMultiSelect('combo_cargo');
			const msSetor = initMultiSelect('combo_setor');
			const msSubsetor = initMultiSelect('combo_subsetor');

			function carregarSubsetores(){
				if(!msSetor || !msSubsetor) return;
				const setoresSelecionados = msSetor.hidden.value || '';

				if(!setoresSelecionados){
					msSubsetor.menu.innerHTML = '';
					msSubsetor.hidden.value = '';
					msSubsetor.btn.disabled = true;
					msSubsetor.menu.style.display = 'none';
					msSubsetor.sync();
					return;
				}

				msSubsetor.btn.disabled = true;
				msSubsetor.menu.innerHTML = '<li style=\"margin:2px 0; color:#777;\">Carregando...</li>';

				fetch('" . basename($_SERVER['PHP_SELF']) . "', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'acao=buscarSubsetores&setor=' + encodeURIComponent(setoresSelecionados)
				})
				.then(function(r){ return r.json(); })
				.then(function(data){
					const selecionadosAtuais = new Set((msSubsetor.hidden.value || '').split(',').filter(Boolean));
					msSubsetor.menu.innerHTML = '';

					if(Array.isArray(data) && data.length){
						data.forEach(function(sub){
							const li = document.createElement('li');
							li.style.margin = '2px 0';
							const label = document.createElement('label');
							label.style.display = 'flex';
							label.style.alignItems = 'center';
							label.style.gap = '6px';
							label.style.fontWeight = 'normal';
							label.style.margin = '0';
							label.style.cursor = 'pointer';

							const input = document.createElement('input');
							input.type = 'checkbox';
							input.value = sub;
							if(selecionadosAtuais.has(sub)) input.checked = true;
							input.style.width = '14px';
							input.style.height = '14px';
							input.style.margin = '0';

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
						li.style.margin = '2px 0';
						li.style.color = '#777';
						li.textContent = 'Nenhum item';
						msSubsetor.menu.appendChild(li);
						msSubsetor.btn.disabled = true;
					}

					msSubsetor.sync();
				})
				.catch(function(){
					msSubsetor.menu.innerHTML = '';
					msSubsetor.btn.disabled = false;
					msSubsetor.sync();
				});
			}

			if(msSetor && msSubsetor){
				msSetor.menu.addEventListener('change', function(e){
					if(e.target && e.target.matches('input[type=\"checkbox\"]')) carregarSubsetores();
				});
				carregarSubsetores();
			}
		});
		</script>";

		echo "<h3>Solicitações de Ajuste</h3>";
		echo montarTabelaPonto($cabecalho_tabela, $linhas);

		$idMotoristaEsc = htmlspecialchars(strval($idMotorista), ENT_QUOTES, 'UTF-8');
		$statusFiltroEsc = htmlspecialchars(strval($statusFiltro), ENT_QUOTES, 'UTF-8');
		$cargoFiltroEsc = htmlspecialchars(strval($cargoFiltro), ENT_QUOTES, 'UTF-8');
		$setorFiltroEsc = htmlspecialchars(strval($setorFiltro), ENT_QUOTES, 'UTF-8');
		$subsetorFiltroEsc = htmlspecialchars(strval($subsetorFiltro), ENT_QUOTES, 'UTF-8');

		// Formulário oculto para ações
		echo "<form name='form_acao' method='POST' style='display:none;'>
			<input type='hidden' name='acao' value=''>
			<input type='hidden' name='id_solicitacao' value=''>
			<input type='hidden' name='permitir_substituir' value='0'>
			<input type='hidden' name='motorista' value='{$idMotoristaEsc}'>
			<input type='hidden' name='status_filtro' value='{$statusFiltroEsc}'>
			<input type='hidden' name='cargo_filtro' value='{$cargoFiltroEsc}'>
			<input type='hidden' name='setor_filtro' value='{$setorFiltroEsc}'>
			<input type='hidden' name='subsetor_filtro' value='{$subsetorFiltroEsc}'>
		</form>";

		// Formulário oculto para ações em lote
		echo "<form name='form_lote' method='POST' style='display:none;'>
			<input type='hidden' name='acao' value='processarEmLote'>
			<input type='hidden' name='acao_lote' value=''>
			<input type='hidden' name='ids_selecionados' value=''>
			<input type='hidden' name='permitir_substituir' value='0'>
		</form>";

		// Marcar as 'enviada' como 'visualizada' para o usuário logado
		query("UPDATE solicitacoes_ajuste SET status = 'visualizada', data_visualizacao = NOW() WHERE status = 'enviada'");

		rodape();
	}

	index();
?>
