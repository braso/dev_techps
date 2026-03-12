<?php
	include "../funcoes_ponto.php";

	function buscarSubsetores(){
		$setor = mysqli_real_escape_string($GLOBALS['conn'], $_POST['setor']);
		$subsetores = mysqli_fetch_all(query("SELECT DISTINCT subsetor_usuario FROM solicitacoes_ajuste WHERE setor_usuario = '$setor' AND subsetor_usuario IS NOT NULL AND subsetor_usuario != '' AND subsetor_usuario != 'N/A' ORDER BY subsetor_usuario"), MYSQLI_ASSOC);
		
		$retorno = [];
		foreach($subsetores as $sub){
			$retorno[] = $sub['subsetor_usuario'];
		}
		
		echo json_encode($retorno);
		exit;
	}

	function aceitarSolicitacao($id = null){
		$idSolicitacao = $id ?: mysqli_real_escape_string($GLOBALS['conn'], $_POST['id_solicitacao']);
		
		// Buscar detalhes da solicitação
		$sol = mysqli_fetch_assoc(query("
			SELECT s.*, e.enti_tx_matricula 
			FROM solicitacoes_ajuste s 
			JOIN entidade e ON s.id_motorista = e.enti_nb_id 
			WHERE s.id = '$idSolicitacao' 
			LIMIT 1
		"));

		if (!$sol) {
			if(!$id) set_status("ERRO: Solicitação não encontrada.");
			if(!$id){ index(); exit; }
			return false;
		}

		if ($sol['status'] === 'aceita' || $sol['status'] === 'nao_aceita') {
			if(!$id) set_status("ERRO: Esta solicitação já foi processada.");
			if(!$id){ index(); exit; }
			return false;
		}

		try {
			// Preparar os dados para inserir na tabela ponto
			// conferirErroPonto($matricula, DateTime $dataPonto, int $idMacro, int $motivo = 0, string $justificativa = "")
			$dataPonto = new DateTime($sol['data_ajuste'] . ' ' . $sol['hora_ajuste']);
			$newPonto = conferirErroPonto($sol['enti_tx_matricula'], $dataPonto, $sol['id_macro'], $sol['id_motivo'], $sol['justificativa']);
			
			// Conferir se já existe um ponto no mesmo segundo (lógica de ajuste_ponto.php)
			$temPonto = mysqli_fetch_assoc(query("
				SELECT pont_tx_data FROM ponto
				WHERE pont_tx_status = 'ativo'
					AND pont_tx_matricula = '{$sol['enti_tx_matricula']}'
					AND STR_TO_DATE(pont_tx_data, '%Y-%m-%d %H:%i') = STR_TO_DATE('{$sol['data_ajuste']} {$sol['hora_ajuste']}', '%Y-%m-%d %H:%i')
				ORDER BY pont_tx_data DESC
				LIMIT 1
			"));

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

			if(!$id) set_status("Solicitação aprovada e ponto registrado com sucesso!");
			if(!$id){ index(); exit; }
			return true;
		} catch (Exception $e) {
			if(!$id) set_status("ERRO: " . $e->getMessage());
			if(!$id){ index(); exit; }
			return false;
		}
	}

	function rejeitarSolicitacao($id = null){
		$idSolicitacao = $id ?: mysqli_real_escape_string($GLOBALS['conn'], $_POST['id_solicitacao']);
		query("UPDATE solicitacoes_ajuste SET status = 'nao_aceita', data_decisao = NOW(), id_superior = {$_SESSION['user_nb_id']} WHERE id = '$idSolicitacao'");
		if(!$id) set_status("Solicitação rejeitada com sucesso.");
		if(!$id){ index(); exit; }
		return true;
	}

	function processarEmLote(){
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
			if ($acaoLote == 'aceitarLote') {
				if (aceitarSolicitacao($id)) $sucesso++; else $erro++;
			} elseif ($acaoLote == 'rejeitarLote') {
				if (rejeitarSolicitacao($id)) $sucesso++; else $erro++;
			}
		}

		$msg = "$sucesso solicitações processadas com sucesso.";
		if ($erro > 0) $msg .= " $erro falhas encontradas.";
		set_status($msg);
		
		index();
		exit;
	}

	function index(){
		include "../check_permission.php";
		// Como o arquivo está em uma pasta, a permissão deve considerar o caminho relativo ou absoluto
		// No banco de dados, geralmente salvamos o caminho relativo à raiz.
		verificaPermissao('/telas/gerenciar_ajustes.php');

		cabecalho("Gerenciar Ajustes de Ponto");

		// Filtros
		$idMotorista = $_POST['motorista'] ?? '';
		$statusFiltro = $_POST['status_filtro'] ?? 'pendentes'; // pendentes, aceitas, rejeitadas, todas
		$cargoFiltro = $_POST['cargo_filtro'] ?? '';
		$setorFiltro = $_POST['setor_filtro'] ?? '';
		$subsetorFiltro = $_POST['subsetor_filtro'] ?? '';

		$extra_sql = "";
		if (!empty($idMotorista)) {
			$extra_sql .= " AND s.id_motorista = '$idMotorista'";
		}

		if ($statusFiltro == 'pendentes') {
			$extra_sql .= " AND s.status IN ('enviada', 'visualizada')";
		} elseif ($statusFiltro == 'aceitas') {
			$extra_sql .= " AND s.status = 'aceita'";
		} elseif ($statusFiltro == 'rejeitadas') {
			$extra_sql .= " AND s.status = 'nao_aceita'";
		}

		if (!empty($cargoFiltro)) {
			$extra_sql .= " AND s.cargo_usuario = '$cargoFiltro'";
		}
		if (!empty($setorFiltro)) {
			$extra_sql .= " AND s.setor_usuario = '$setorFiltro'";
		}
		if (!empty($subsetorFiltro)) {
			$extra_sql .= " AND s.subsetor_usuario = '$subsetorFiltro'";
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
		$dados = mysqli_fetch_all($result, MYSQLI_ASSOC);
		
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
						<button type='button' class='btn btn-xs btn-success' title='Aprovar' onclick=\"if(confirm('Aprovar este ajuste?')) { document.form_acao.id_solicitacao.value='{$row['id']}'; document.form_acao.acao.value='aceitarSolicitacao'; document.form_acao.submit(); }\"><i class='fa fa-check'></i></button>
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
		$cargos = mysqli_fetch_all(query("SELECT DISTINCT cargo_usuario FROM solicitacoes_ajuste WHERE cargo_usuario IS NOT NULL AND cargo_usuario != '' AND cargo_usuario != 'N/A' ORDER BY cargo_usuario"), MYSQLI_ASSOC);
		$setores = mysqli_fetch_all(query("SELECT DISTINCT setor_usuario FROM solicitacoes_ajuste WHERE setor_usuario IS NOT NULL AND setor_usuario != '' AND setor_usuario != 'N/A' ORDER BY setor_usuario"), MYSQLI_ASSOC);
		
		$subsetores = [];
		if (!empty($setorFiltro)) {
			$subsetores = mysqli_fetch_all(query("SELECT DISTINCT subsetor_usuario FROM solicitacoes_ajuste WHERE setor_usuario = '$setorFiltro' AND subsetor_usuario IS NOT NULL AND subsetor_usuario != '' AND subsetor_usuario != 'N/A' ORDER BY subsetor_usuario"), MYSQLI_ASSOC);
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

		// Formulário de Filtros
		$campos_filtro = [
			combo_net("Funcionário", "motorista", $idMotorista, 3, "entidade", "", " AND enti_tx_status = 'ativo' AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')", "enti_tx_matricula"),
			"<div class='col-sm-2 margin-bottom-5'>
				<label>Status</label>
				<select name='status_filtro' class='form-control input-sm'>
					<option value='pendentes' " . ($statusFiltro == 'pendentes' ? 'selected' : '') . ">Pendentes</option>
					<option value='aceitas' " . ($statusFiltro == 'aceitas' ? 'selected' : '') . ">Aceitas</option>
					<option value='rejeitadas' " . ($statusFiltro == 'rejeitadas' ? 'selected' : '') . ">Rejeitadas</option>
					<option value='todas' " . ($statusFiltro == 'todas' ? 'selected' : '') . ">Todas</option>
				</select>
			</div>",
			montarComboFiltro("Cargo", "cargo_filtro", $cargoFiltro, $cargos, 'cargo_usuario', 2),
			montarComboFiltro("Setor", "setor_filtro", $setorFiltro, $setores, 'setor_usuario', 2, "combo_setor"),
			montarComboFiltro("Subsetor", "subsetor_filtro", $subsetorFiltro, $subsetores, 'subsetor_usuario', 3, "combo_subsetor", empty($setorFiltro) || empty($subsetores))
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
		function processarLote(acao) {
			const selecionados = Array.from(document.querySelectorAll('.sel-lote:checked')).map(cb => cb.value);
			if (selecionados.length === 0) return;
			
			const confirmMsg = acao === 'aceitarLote' ? 'Aprovar todos os selecionados?' : 'Rejeitar todos os selecionados?';
			if (confirm(confirmMsg)) {
				document.form_lote.ids_selecionados.value = selecionados.join(',');
				document.form_lote.acao_lote.value = acao;
				document.form_lote.submit();
			}
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

			const comboSetor = document.getElementById('combo_setor');
			const comboSubsetor = document.getElementById('combo_subsetor');

			if (comboSetor && comboSubsetor) {
				comboSetor.addEventListener('change', function() {
					const setorSelecionado = this.value;
					
					// Limpar e desabilitar enquanto carrega ou se estiver vazio
					comboSubsetor.innerHTML = '<option value=\"\">Carregando...</option>';
					comboSubsetor.disabled = true;

					if (setorSelecionado === '') {
						comboSubsetor.innerHTML = '<option value=\"\">Todos</option>';
						return;
					}

					// Buscar subsetores via AJAX (usando o próprio arquivo com uma ação específica)
					fetch('" . basename($_SERVER['PHP_SELF']) . "', {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: 'acao=buscarSubsetores&setor=' + encodeURIComponent(setorSelecionado)
					})
					.then(response => response.json())
					.then(data => {
						comboSubsetor.innerHTML = '<option value=\"\">Todos</option>';
						if (data.length > 0) {
							data.forEach(sub => {
								comboSubsetor.innerHTML += `<option value=\"\${sub}\">\${sub}</option>`;
							});
							comboSubsetor.disabled = false;
						} else {
							comboSubsetor.disabled = true;
						}
					})
					.catch(error => {
						console.error('Erro ao buscar subsetores:', error);
						comboSubsetor.innerHTML = '<option value=\"\">Todos</option>';
						comboSubsetor.disabled = true;
					});
				});
			}
		});
		</script>";

		echo "<h3>Solicitações de Ajuste</h3>";
		echo montarTabelaPonto($cabecalho_tabela, $linhas);

		// Formulário oculto para ações
		echo "<form name='form_acao' method='POST' style='display:none;'>
			<input type='hidden' name='acao' value=''>
			<input type='hidden' name='id_solicitacao' value=''>
			<input type='hidden' name='motorista' value='$idMotorista'>
			<input type='hidden' name='status_filtro' value='$statusFiltro'>
			<input type='hidden' name='cargo_filtro' value='$cargoFiltro'>
			<input type='hidden' name='setor_filtro' value='$setorFiltro'>
			<input type='hidden' name='subsetor_filtro' value='$subsetorFiltro'>
		</form>";

		// Formulário oculto para ações em lote
		echo "<form name='form_lote' method='POST' style='display:none;'>
			<input type='hidden' name='acao' value='processarEmLote'>
			<input type='hidden' name='acao_lote' value=''>
			<input type='hidden' name='ids_selecionados' value=''>
		</form>";

		// Marcar as 'enviada' como 'visualizada' para o usuário logado
		query("UPDATE solicitacoes_ajuste SET status = 'visualizada', data_visualizacao = NOW() WHERE status = 'enviada'");

		rodape();
	}

	index();
?>
