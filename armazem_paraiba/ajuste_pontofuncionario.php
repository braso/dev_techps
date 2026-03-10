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

	// Função para criar tabela se não existir
	function criarTabelaSolicitacoes() {
		$tabela_existe = mysqli_fetch_assoc(query("SHOW TABLES LIKE 'solicitacoes_ajuste'"));
		if (!$tabela_existe) {
			$sql = "CREATE TABLE solicitacoes_ajuste (
				id INT AUTO_INCREMENT PRIMARY KEY,
				id_motorista INT NOT NULL,
				data_ajuste DATE NOT NULL,
				hora_ajuste TIME NOT NULL,
				id_macro INT,
				id_motivo INT,
				justificativa TEXT,
				status ENUM('enviada', 'visualizada', 'aceita', 'nao_aceita') DEFAULT 'enviada',
				data_solicitacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				id_usuario_solicitante INT,
				cargo_usuario VARCHAR(255),
				setor_usuario VARCHAR(255),
				subsetor_usuario VARCHAR(255),
				id_superior INT NULL,
				data_visualizacao TIMESTAMP NULL,
				data_decisao TIMESTAMP NULL,
				INDEX idx_motorista_data (id_motorista, data_ajuste),
				INDEX idx_status (status)
			)";
			query($sql);
		}
	}

	// Criar tabela ao carregar a página
	criarTabelaSolicitacoes();

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
					$statusBadge = "<span class='badge badge-info' style='font-size:12px; padding:5px 10px;'>Visualizada</span>";
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
					// Redirecionar de volta para a mesma página (GET, não POST)
					header("Location: " . basename($_SERVER['PHP_SELF']) . "?msg=sucesso&idMotorista={$idMotorista}");
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

		$variableFields = [

			campo_data("Data*","data",($_POST["data"] ?? ""),2,"id='dataFiltro'"),
			campo_hora("Hora*","hora",($_POST["hora"] ?? ""),2),

			combo_bd("Tipo de Registro*","idMacro",($_POST["idMacro"] ?? ""),4,"macroponto","","ORDER BY macr_nb_id"),

			combo_bd("Motivo*","motivo",($_POST["motivo"] ?? ""),4,"motivo",""," AND moti_tx_tipo = 'Ajuste' ORDER BY moti_tx_nome")

		];

		$campoJust[] = textarea("Justificativa","justificativa",($_POST["justificativa"] ?? ""),12,'maxlength=680');

		$botoes[] = "<button type='submit' name='enviar_solicitacao' class='btn btn-success'>Enviar Solicitação</button>";
		$botoes[] = criarBotaoVoltar("espelho_ponto.php");

		echo abre_form("Dados do Ajuste de Ponto");
		echo "<input type='hidden' name='idMotorista' value='{$idMotorista}'>";
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

		rodape();

	}

	index();
?>

<script>

document.addEventListener("DOMContentLoaded",function(){

	const campoData = document.getElementById("dataFiltro");

	if(!campoData) return;

	campoData.addEventListener("change",filtrar);
	campoData.addEventListener("input",filtrar);

	function filtrar(){

		const dataSelecionada = campoData.value;

		const tabela = document.querySelector("#tabelaNaoConformidadeContainer table");

		if(!tabela) return;

		const linhas = tabela.querySelectorAll("tbody tr");

		if(!dataSelecionada){

			linhas.forEach(l=>l.style.display="");

			document.getElementById("mensagemSemDados").style.display="none";

			return;

		}

		const dataFormatada = new Date(dataSelecionada).toLocaleDateString("pt-BR");

		let encontrou=false;

		linhas.forEach(linha=>{

			const celulaData = linha.querySelector("td:nth-child(2)");

			if(!celulaData) return;

			if(linha.innerText.includes("TOTAL")){
				linha.style.display="none";
				return;
			}

			if(celulaData.textContent.trim() === dataFormatada){

				linha.style.display="";
				encontrou=true;

			}else{

				linha.style.display="none";

			}

		});

		document.getElementById("mensagemSemDados").style.display = encontrou ? "none" : "block";

	}

});

</script>

<?php
	rodape();
?>
</body>
</html>