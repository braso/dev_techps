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

	function aceitarSolicitacao(){
		$idSolicitacao = mysqli_real_escape_string($GLOBALS['conn'], $_POST['id_solicitacao']);
		
		// Buscar detalhes da solicitação
		$sol = mysqli_fetch_assoc(query("
			SELECT s.*, e.enti_tx_matricula 
			FROM solicitacoes_ajuste s 
			JOIN entidade e ON s.id_motorista = e.enti_nb_id 
			WHERE s.id = '$idSolicitacao' 
			LIMIT 1
		"));

		if (!$sol) {
			set_status("ERRO: Solicitação não encontrada.");
			index();
			exit;
		}

		if ($sol['status'] === 'aceita' || $sol['status'] === 'nao_aceita') {
			set_status("ERRO: Esta solicitação já foi processada.");
			index();
			exit;
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
				$seg = explode(":", $temPonto["pont_tx_data"])[2];
				$seg = intval($seg) + 1;
				$newPonto["pont_tx_data"] = "{$sol['data_ajuste']} {$sol['hora_ajuste']}:" . str_pad(strval($seg), 2, "0", STR_PAD_LEFT);
			}

			// Inserir na tabela ponto
			inserir("ponto", array_keys($newPonto), array_values($newPonto));

			// Atualizar status da solicitação
			query("UPDATE solicitacoes_ajuste SET status = 'aceita', data_decisao = NOW(), id_superior = {$_SESSION['user_nb_id']} WHERE id = '$idSolicitacao'");

			set_status("Solicitação aprovada e ponto registrado com sucesso!");
		} catch (Exception $e) {
			set_status("ERRO: " . $e->getMessage());
		}

		index();
		exit;
	}

	function rejeitarSolicitacao(){
		$idSolicitacao = mysqli_real_escape_string($GLOBALS['conn'], $_POST['id_solicitacao']);
		
		query("UPDATE solicitacoes_ajuste SET status = 'nao_aceita', data_decisao = NOW(), id_superior = {$_SESSION['user_nb_id']} WHERE id = '$idSolicitacao'");
		
		set_status("Solicitação rejeitada com sucesso.");
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
		$linhas = [];

		while ($row = mysqli_fetch_assoc($result)) {
			$statusBadge = '';
			switch ($row['status']) {
				case 'enviada': $statusBadge = "<span class='badge badge-warning'>Enviada</span>"; break;
				case 'visualizada': $statusBadge = "<span class='badge badge-info'>Visualizada</span>"; break;
				case 'aceita': $statusBadge = "<span class='badge badge-success'>Aceita</span>"; break;
				case 'nao_aceita': $statusBadge = "<span class='badge badge-danger'>Rejeitada</span>"; break;
			}

			$acoes = "";
			if ($row['status'] == 'enviada' || $row['status'] == 'visualizada') {
				$acoes = "
					<div class='btn-group'>
						<button type='button' class='btn btn-xs btn-success' onclick=\"if(confirm('Aprovar este ajuste?')) { document.form_acao.id_solicitacao.value='{$row['id']}'; document.form_acao.acao.value='aceitarSolicitacao'; document.form_acao.submit(); }\"><i class='fa fa-check'></i> Aprovar</button>
						<button type='button' class='btn btn-xs btn-danger' onclick=\"if(confirm('Rejeitar este ajuste?')) { document.form_acao.id_solicitacao.value='{$row['id']}'; document.form_acao.acao.value='rejeitarSolicitacao'; document.form_acao.submit(); }\"><i class='fa fa-times'></i> Rejeitar</button>
					</div>
				";
			} else {
				$cor = ($row['status'] == 'aceita' ? 'green' : 'red');
				$acoes = "<small style='color:$cor'><b>" . ($row['status'] == 'aceita' ? 'Aprovado' : 'Rejeitado') . "</b></small><br>";
				$acoes .= "<small>Por: <b>" . ($row['superior_nome'] ?? 'Sistema') . "</b> em " . date('d/m/Y H:i', strtotime($row['data_decisao'])) . "</small>";
			}

			$cargoDisp = ($row['cargo_usuario'] == 'N/A') ? '' : $row['cargo_usuario'];
			$setorDisp = ($row['setor_usuario'] == 'N/A') ? '' : $row['setor_usuario'];
			$subSetorDisp = ($row['subsetor_usuario'] == 'N/A') ? '' : $row['subsetor_usuario'];

			$solicitanteInfo = "<b>Cargo:</b> $cargoDisp<br><b>Setor:</b> $setorDisp<br><b>Sub:</b> $subSetorDisp";

			$linhas[] = [
				date('d/m/Y H:i', strtotime($row['data_solicitacao'])),
				"<b>{$row['enti_tx_nome']}</b><br><small>{$row['enti_tx_matricula']}</small>",
				"<b>" . date('d/m/Y', strtotime($row['data_ajuste'])) . "</b><br>" . $row['hora_ajuste'],
				$row['macr_tx_nome'],
				$row['moti_tx_nome'],
				"<span title='{$row['justificativa']}' style='cursor:help;'>" . (strlen($row['justificativa']) > 30 ? substr($row['justificativa'], 0, 30) . "..." : $row['justificativa']) . "</span>",
				$solicitanteInfo,
				$statusBadge,
				$acoes
			];
		}

		$cabecalho_tabela = ["Solicitado em", "Funcionário", "Data/Hora Ajuste", "Tipo", "Motivo", "Justificativa", "Solicitante", "Status", "Ações"];

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

		// Script para dependência Setor -> Subsetor
		echo "
		<script>
		document.addEventListener('DOMContentLoaded', function() {
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

		// Marcar as 'enviada' como 'visualizada' para o usuário logado
		query("UPDATE solicitacoes_ajuste SET status = 'visualizada', data_visualizacao = NOW() WHERE status = 'enviada'");

		rodape();
	}

	index();
?>