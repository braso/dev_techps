<?php
	include "conecta.php";
	include_once "check_permission.php";

	$nivelUser = trim(strval($_SESSION["user_tx_nivel"] ?? ""));
	$ehSuperAdmin = (stripos($nivelUser, "Super Administrador") !== false);

	if(!$ehSuperAdmin){
		cabecalho("Logs do Sistema");
		echo "<div class='alert alert-danger' style='margin:20px;'>Acesso restrito ao Super Administrador.</div>";
		exit;
	}

	// Filtros (GET)
	$fModulo = trim(strval($_GET["modulo"] ?? ""));
	$fNivel  = trim(strval($_GET["nivel"] ?? ""));
	$fEvento = trim(strval($_GET["evento"] ?? ""));
	$fLogin  = trim(strval($_GET["login"] ?? ""));
	$fDe     = trim(strval($_GET["de"] ?? ""));
	$fAte    = trim(strval($_GET["ate"] ?? ""));
	$pagina  = max(1, intval($_GET["p"] ?? 1));
	$porPagina = 50;

	// Lista de modulos distintos para o filtro
	$rsMod = query("SELECT DISTINCT log_tx_modulo FROM log_sistema ORDER BY log_tx_modulo ASC");
	$modulos = [];
	while($rsMod && ($r = mysqli_fetch_assoc($rsMod))){ $modulos[] = $r["log_tx_modulo"]; }

	// Monta WHERE dinamico
	$where = [];
	$params = [];
	$types = "";
	if($fModulo !== ""){
		$where[] = "log_tx_modulo = ?"; $params[] = $fModulo; $types .= "s";
	}
	if($fNivel !== ""){
		$where[] = "log_tx_nivel = ?"; $params[] = $fNivel; $types .= "s";
	}
	if($fEvento !== ""){
		$where[] = "log_tx_evento LIKE ?"; $params[] = "%".$fEvento."%"; $types .= "s";
	}
	if($fLogin !== ""){
		$where[] = "log_tx_login LIKE ?"; $params[] = "%".$fLogin."%"; $types .= "s";
	}
	if($fDe !== "" && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDe)){
		$where[] = "log_tx_dataCadastro >= ?"; $params[] = $fDe." 00:00:00"; $types .= "s";
	}
	if($fAte !== "" && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fAte)){
		$where[] = "log_tx_dataCadastro <= ?"; $params[] = $fAte." 23:59:59"; $types .= "s";
	}
	$sqlWhere = empty($where) ? "" : (" WHERE " . implode(" AND ", $where));

	// Total
	$rsTot = empty($params)
		? query("SELECT COUNT(*) AS c FROM log_sistema".$sqlWhere)
		: query("SELECT COUNT(*) AS c FROM log_sistema".$sqlWhere, $types, $params);
	$total = 0;
	if($rsTot && ($rt = mysqli_fetch_assoc($rsTot))){ $total = intval($rt["c"]); }
	$totalPaginas = max(1, (int)ceil($total / $porPagina));
	if($pagina > $totalPaginas){ $pagina = $totalPaginas; }
	$offset = ($pagina - 1) * $porPagina;

	// Dados
	$sqlDados = "SELECT * FROM log_sistema".$sqlWhere." ORDER BY log_nb_id DESC LIMIT ? OFFSET ?";
	$paramsDados = array_merge($params, [$porPagina, $offset]);
	$typesDados = $types . "ii";
	$rs = query($sqlDados, $typesDados, $paramsDados);

	cabecalho("Logs do Sistema");

	$coresNivel = [
		"debug" => "#888", "info" => "#3c8dbc", "warning" => "#f0ad4e",
		"error" => "#c0392b", "critical" => "#7b1fa2"
	];
?>
<style>
	.log-wrap{margin:16px}
	.log-filtros{background:#fff;border:1px solid #e2e2e2;border-radius:6px;padding:12px;margin-bottom:14px}
	.log-filtros label{font-size:11px;font-weight:600;color:#555;margin:0 4px 0 0}
	.log-filtros input,.log-filtros select{font-size:12px;padding:4px 6px;border:1px solid #ccc;border-radius:4px}
	.log-tabela{width:100%;border-collapse:collapse;font-size:12px;background:#fff}
	.log-tabela th{background:#f7f7f7;text-align:left;padding:6px;border-bottom:1px solid #e2e2e2;font-size:11px;text-transform:uppercase;color:#777;white-space:nowrap}
	.log-tabela td{padding:6px;border-bottom:1px solid #f0f0f0;vertical-align:top}
	.log-badge{display:inline-block;padding:1px 6px;border-radius:8px;font-size:10.5px;font-weight:600;color:#fff}
	.log-detalhe{max-width:420px;word-break:break-word}
	.log-extra{font-family:monospace;font-size:11px;color:#555;max-width:300px;word-break:break-all}
	.log-pag{margin:10px 0;text-align:center}
	.log-pag a{margin:0 3px;padding:4px 8px;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#333;font-size:12px}
	.log-pag a.atual{background:#3c8dbc;color:#fff;border-color:#3c8dbc}
</style>

<div class="log-wrap">
	<div class="log-filtros">
		<form method="get" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
			<label>Módulo</label>
			<select name="modulo">
				<option value="">Todos</option>
				<?php foreach($modulos as $m): ?>
					<option value="<?php echo htmlspecialchars($m, ENT_QUOTES); ?>" <?php echo $fModulo===$m?"selected":""; ?>><?php echo htmlspecialchars($m, ENT_QUOTES); ?></option>
				<?php endforeach; ?>
			</select>
			<label>Nível</label>
			<select name="nivel">
				<option value="">Todos</option>
				<?php foreach(["debug","info","warning","error","critical"] as $nv): ?>
					<option value="<?php echo $nv; ?>" <?php echo $fNivel===$nv?"selected":""; ?>><?php echo $nv; ?></option>
				<?php endforeach; ?>
			</select>
			<label>Evento</label>
			<input type="text" name="evento" value="<?php echo htmlspecialchars($fEvento, ENT_QUOTES); ?>" style="width:130px">
			<label>Usuário</label>
			<input type="text" name="login" value="<?php echo htmlspecialchars($fLogin, ENT_QUOTES); ?>" style="width:120px">
			<label>De</label>
			<input type="date" name="de" value="<?php echo htmlspecialchars($fDe, ENT_QUOTES); ?>">
			<label>Até</label>
			<input type="date" name="ate" value="<?php echo htmlspecialchars($fAte, ENT_QUOTES); ?>">
			<button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
			<a href="logs_sistema.php" class="btn btn-default btn-sm">Limpar</a>
		</form>
		<div style="margin-top:6px;font-size:11px;color:#888">Total de registros: <?php echo $total; ?></div>
	</div>

	<table class="log-tabela">
		<thead>
			<tr>
				<th>Data</th>
				<th>Nível</th>
				<th>Módulo</th>
				<th>Evento</th>
				<th>Usuário</th>
				<th>IP</th>
				<th>Detalhe</th>
			</tr>
		</thead>
		<tbody>
		<?php if($rs): while($row = mysqli_fetch_assoc($rs)): ?>
			<tr>
				<td style="white-space:nowrap"><?php
					$dt = $row["log_tx_dataCadastro"];
					echo htmlspecialchars($dt ? date("d/m/Y H:i:s", strtotime(strval($dt))) : "", ENT_QUOTES);
				?></td>
				<td><span class="log-badge" style="background:<?php echo $coresNivel[$row["log_tx_nivel"]] ?? "#888"; ?>"><?php echo htmlspecialchars(strval($row["log_tx_nivel"]), ENT_QUOTES); ?></span></td>
				<td><?php echo htmlspecialchars(strval($row["log_tx_modulo"]), ENT_QUOTES); ?></td>
				<td><?php echo htmlspecialchars(strval($row["log_tx_evento"]), ENT_QUOTES); ?></td>
				<td><?php echo htmlspecialchars(strval($row["log_tx_login"] ?? ""), ENT_QUOTES); ?></td>
				<td><?php echo htmlspecialchars(strval($row["log_tx_ip"] ?? ""), ENT_QUOTES); ?></td>
				<td class="log-detalhe"><?php echo htmlspecialchars(strval($row["log_tx_detalhe"] ?? ""), ENT_QUOTES); ?></td>
			</tr>
		<?php endwhile; else: ?>
			<tr><td colspan="7" style="text-align:center;padding:20px;color:#888">Nenhum registro encontrado.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<div class="log-pag">
		<?php
		// monta query string preservando filtros
		$qs = $_GET; unset($qs["p"]);
		$base = "logs_sistema.php?" . http_build_query($qs);
		if($pagina > 1){ echo '<a href="'.$base.'&p='.($pagina-1).'">&laquo;</a>'; }
		for($i = max(1,$pagina-3); $i <= min($totalPaginas,$pagina+3); $i++){
			$cls = ($i === $pagina) ? " atual" : "";
			echo '<a class="'.$cls.'" href="'.$base.'&p='.$i.'">'.$i.'</a>';
		}
		if($pagina < $totalPaginas){ echo '<a href="'.$base.'&p='.($pagina+1).'">&raquo;</a>'; }
		?>
	</div>
</div>
