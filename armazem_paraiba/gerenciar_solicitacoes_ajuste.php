<?php
	include "conecta.php";

	// Processar ação de aprovação/rejeição
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao_solicitacao'])) {
		$idSolicitacao = mysqli_real_escape_string($GLOBALS['conn'], $_POST['id_solicitacao']);
		$acao = mysqli_real_escape_string($GLOBALS['conn'], $_POST['acao_solicitacao']);
		
		if ($acao === 'aceita') {
			$novoStatus = 'aceita';
		} elseif ($acao === 'nao_aceita') {
			$novoStatus = 'nao_aceita';
		} else {
			echo "<script>alert('Ação inválida.');</script>";
			exit;
		}

		$sql = "UPDATE solicitacoes_ajuste SET status = '$novoStatus', data_decisao = NOW(), id_superior = {$_SESSION['user_nb_id']} WHERE id = '$idSolicitacao'";
		query($sql);

		echo "<script>alert('Solicitação " . ($acao === 'aceita' ? 'aprovada' : 'rejeitada') . " com sucesso!');window.location.reload();</script>";
	}

	// Marcar como visualizada quando acessa a página
	if (!empty($_SESSION['user_nb_id'])) {
		query("UPDATE solicitacoes_ajuste SET status = 'visualizada', data_visualizacao = NOW() WHERE status = 'enviada' AND (id_superior IS NULL OR id_superior = {$_SESSION['user_nb_id']})");
	}

	cabecalho("Gerenciar Solicitações de Ajuste");

	// Buscar solicitações de ajuste para o usuário logado
	$solicitacoes = [];
	$query_str = "
		SELECT s.*, e.enti_tx_nome, e.enti_tx_matricula, e.enti_tx_ocupacao
		FROM solicitacoes_ajuste s
		JOIN entidade e ON s.id_motorista = e.enti_nb_id
		WHERE s.status IN ('enviada', 'visualizada')
		ORDER BY s.data_solicitacao DESC
	";

	$resultado = query($query_str);
	$solicitacoes = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

	echo "<h2>Solicitações Pendentes de Aprovação</h2>";

	if (empty($solicitacoes)) {
		echo "<p style='color:green;'>✓ Nenhuma solicitação pendente de aprovação.</p>";
	} else {
		echo "<table class='table table-striped table-hover'>";
		echo "<thead>";
		echo "<tr>";
		echo "<th>Data Solicitação</th>";
		echo "<th>Motorista</th>";
		echo "<th>Matrícula</th>";
		echo "<th>Ocupação</th>";
		echo "<th>Data Ajuste</th>";
		echo "<th>Hora Ajuste</th>";
		echo "<th>Cargo Solicitante</th>";
		echo "<th>Setor</th>";
		echo "<th>Subsetor</th>";
		echo "<th>Justificativa</th>";
		echo "<th>Status</th>";
		echo "<th>Ação</th>";
		echo "</tr>";
		echo "</thead>";
		echo "<tbody>";

		foreach ($solicitacoes as $sol) {
			$statusBadge = '';
			switch ($sol['status']) {
				case 'enviada':
					$statusBadge = "<span style='background-color: #FFC107; color: black; padding: 5px 10px; border-radius: 5px;'>Enviada</span>";
					break;
				case 'visualizada':
					$statusBadge = "<span style='background-color: #17A2B8; color: white; padding: 5px 10px; border-radius: 5px;'>Visualizada</span>";
					break;
				case 'aceita':
					$statusBadge = "<span style='background-color: #28A745; color: white; padding: 5px 10px; border-radius: 5px;'>Aceita</span>";
					break;
				case 'nao_aceita':
					$statusBadge = "<span style='background-color: #DC3545; color: white; padding: 5px 10px; border-radius: 5px;'>Não Aceita</span>";
					break;
			}

			echo "<tr>";
			echo "<td>" . date('d/m/Y H:i', strtotime($sol['data_solicitacao'])) . "</td>";
			echo "<td>" . htmlspecialchars($sol['enti_tx_nome']) . "</td>";
			echo "<td>" . htmlspecialchars($sol['enti_tx_matricula']) . "</td>";
			echo "<td>" . htmlspecialchars($sol['enti_tx_ocupacao']) . "</td>";
			echo "<td>" . date('d/m/Y', strtotime($sol['data_ajuste'])) . "</td>";
			echo "<td>" . $sol['hora_ajuste'] . "</td>";
			echo "<td>" . htmlspecialchars($sol['cargo_usuario']) . "</td>";
			echo "<td>" . htmlspecialchars($sol['setor_usuario']) . "</td>";
			echo "<td>" . htmlspecialchars($sol['subsetor_usuario']) . "</td>";
			echo "<td>" . htmlspecialchars(substr($sol['justificativa'], 0, 50) . (strlen($sol['justificativa']) > 50 ? '...' : '')) . "</td>";
			echo "<td>" . $statusBadge . "</td>";
			echo "<td>";
			if ($sol['status'] === 'enviada' || $sol['status'] === 'visualizada') {
				echo "<form method='POST' style='display: inline;'>";
				echo "<input type='hidden' name='id_solicitacao' value='{$sol['id']}'>";
				echo "<button type='submit' name='acao_solicitacao' value='aceita' class='btn btn-success btn-sm'>Aprovar</button>";
				echo "<button type='submit' name='acao_solicitacao' value='nao_aceita' class='btn btn-danger btn-sm'>Rejeitar</button>";
				echo "</form>";
			}
			echo "</td>";
			echo "</tr>";
		}

		echo "</tbody>";
		echo "</table>";
	}

	// Mostrar histórico de decisões
	echo "<h2 style='margin-top: 40px;'>Histórico de Decisões</h2>";

	$resultado_historico = query("
		SELECT s.*, e.enti_tx_nome, e.enti_tx_matricula, e.enti_tx_ocupacao, u.user_tx_nome as superior_nome
		FROM solicitacoes_ajuste s
		JOIN entidade e ON s.id_motorista = e.enti_nb_id
		LEFT JOIN user u ON s.id_superior = u.user_nb_id
		WHERE s.status IN ('aceita', 'nao_aceita')
		ORDER BY s.data_decisao DESC
		LIMIT 50
	");

	$historico = mysqli_fetch_all($resultado_historico, MYSQLI_ASSOC);

	if (empty($historico)) {
		echo "<p style='color:#666;'>Nenhum histórico de decisões.</p>";
	} else {
		echo "<table class='table table-striped table-hover'>";
		echo "<thead>";
		echo "<tr>";
		echo "<th>Data Decisão</th>";
		echo "<th>Motorista</th>";
		echo "<th>Data Ajuste</th>";
		echo "<th>Status Final</th>";
		echo "<th>Aprovado por</th>";
		echo "</tr>";
		echo "</thead>";
		echo "<tbody>";

		foreach ($historico as $hist) {
			$statusBadge = $hist['status'] === 'aceita' 
				? "<span style='background-color: #28A745; color: white; padding: 5px 10px; border-radius: 5px;'>Aceita</span>"
				: "<span style='background-color: #DC3545; color: white; padding: 5px 10px; border-radius: 5px;'>Não Aceita</span>";

			echo "<tr>";
			echo "<td>" . date('d/m/Y H:i', strtotime($hist['data_decisao'])) . "</td>";
			echo "<td>" . htmlspecialchars($hist['enti_tx_nome']) . "</td>";
			echo "<td>" . date('d/m/Y', strtotime($hist['data_ajuste'])) . "</td>";
			echo "<td>" . $statusBadge . "</td>";
			echo "<td>" . htmlspecialchars($hist['superior_nome'] ?? 'N/A') . "</td>";
			echo "</tr>";
		}

		echo "</tbody>";
		echo "</table>";
	}

	rodape();
?>
