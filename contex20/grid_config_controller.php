<?php
$interno = true;

// Garante a sessão ativa (pode vir de um AJAX sem passar pelo conecta.php ainda)
if (empty(session_id())) {
    session_start();
}

// Descobre o diretório do cliente (tenant) com base na sessão, no referer ou no path atual
$baseDir = dirname(__DIR__); // .../gestaodeponto
$tenantDir = null;

// 1º: sessão (mais confiável) — domain = APP_PATH/CONTEX_PATH, ex: /braso/armazem_paraiba
$domainSess = trim(strval($_SESSION["domain"] ?? ""));
if ($domainSess !== "") {
    $partsS = explode('/', trim($domainSess, '/'));
    $candidato = end($partsS);
    if (is_string($candidato) && $candidato !== '' && $candidato !== 'contex20'
        && is_dir($baseDir . '/' . $candidato)) {
        $tenantDir = $candidato;
    }
}

// 2º: HTTP_REFERER — ex: 'gestaodeponto/braso/cadastro_funcionario.php' (índice 1 = tenant)
if (empty($tenantDir) && !empty($_SERVER['HTTP_REFERER'])) {
    $refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    $parts = explode('/', trim($refPath, '/')); // ex: 'gestaodeponto/braso/cadastro_funcionario.php'
    if (isset($parts[1]) && $parts[1] !== 'contex20'
        && is_dir($baseDir . '/' . $parts[1])) {
        $tenantDir = $parts[1];
    }
}

// 3º: path da requisição atual — segmento imediatamente anterior a 'contex20'
if (empty($tenantDir)) {
    $scriptPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $parts = explode('/', trim(strval($scriptPath), '/'));
    $idxContex = array_search('contex20', $parts, true);
    if ($idxContex !== false && $idxContex > 0
        && is_dir($baseDir . '/' . $parts[$idxContex - 1])) {
        $tenantDir = $parts[$idxContex - 1];
    }
}

if (empty($tenantDir)) {
    http_response_code(400);
    echo json_encode(['error' => 'Cliente não identificado']);
    exit;
}

include_once $baseDir . "/{$tenantDir}/conecta.php";

// Ensure table exists
$checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'grid_user_config'");
if(mysqli_num_rows($checkTable) == 0){
    $sql = "CREATE TABLE grid_user_config (
        guc_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        guc_nb_user INT NOT NULL,
        guc_tx_grid VARCHAR(100) NOT NULL,
        guc_tx_columns JSON NOT NULL,
        guc_tx_dataAtualiza DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_grid (guc_nb_user, guc_tx_grid)
    )";
    mysqli_query($conn, $sql);
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $user_id = $_SESSION['user_nb_id'] ?? null;
    $grid_name = $_POST['grid_name'] ?? '';
    $columns = $_POST['columns'] ?? ''; // JSON string

    // Fallback: o próprio frontend envia o id do usuário logado (o grid o injeta na página)
    if(empty($user_id) && !empty($_POST['guc_user_id'])){
        $user_id = (int)$_POST['guc_user_id'];
    }

    if(empty($user_id) && !empty($_SESSION['user_tx_login'] ?? '')){
        $stmtUser = $conn->prepare("SELECT user_nb_id FROM user WHERE user_tx_login = ? AND user_tx_status = 'ativo' LIMIT 1");
        $login = $_SESSION['user_tx_login'];
        $stmtUser->bind_param("s", $login);
        if($stmtUser->execute()){
            $resUser = $stmtUser->get_result();
            if($resUser && $rowUser = $resUser->fetch_assoc()){
                $user_id = (int)$rowUser['user_nb_id'];
                $_SESSION['user_nb_id'] = $user_id;
            }
        }
    }

    if(empty($user_id)){
        $user_id = 0;
    }

    // ============================================================
    // PADRÕES DE GRID (3 por usuário, globais em todos os grids)
    // ============================================================
    $gucAcao = $_POST['guc_acao'] ?? ($_GET['guc_acao'] ?? '');

    if($gucAcao === 'listar_padroes'){
        grid_ensure_padroes((int)$user_id);
        echo json_encode(['success' => true, 'padroes' => grid_padroes_usuario((int)$user_id)]);
        exit;
    }

    if($gucAcao === 'salvar_padrao'){
        $ordem = (int)($_POST['ordem'] ?? 1);
        if($ordem < 1 || $ordem > 3 || $grid_name === '' || $columns === ''){
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }
        grid_ensure_padroes((int)$user_id);
        $padrao = mysqli_fetch_assoc(query("SELECT gup_nb_id, gup_tx_configs FROM grid_user_padrao WHERE gup_nb_user = ? AND gup_nb_ordem = ?", "ii", [(int)$user_id, $ordem]));
        if(empty($padrao)){
            http_response_code(400);
            $totalPadroes = 0;
            $rsTotal = query("SELECT COUNT(*) AS c, GROUP_CONCAT(gup_nb_ordem) AS ordens FROM grid_user_padrao WHERE gup_nb_user = ?", "i", [(int)$user_id]);
            if ($rsTotal) {
                $rowTotal = mysqli_fetch_assoc($rsTotal);
                $totalPadroes = (int)($rowTotal["c"] ?? 0);
                $ordensExistentes = strval($rowTotal["ordens"] ?? "");
            }
            echo json_encode([
                'error' => 'Padrão não encontrado',
                'diagnostico' => [
                    'user_id' => (int)$user_id,
                    'login_sessao' => $_SESSION['user_tx_login'] ?? '',
                    'domain_sessao' => $_SESSION['domain'] ?? '',
                    'ordem_solicitada' => $ordem,
                    'total_padroes_banco' => $totalPadroes,
                    'ordens_existentes' => $ordensExistentes ?? '',
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $configs = json_decode(strval($padrao["gup_tx_configs"] ?? "{}"), true);
        if(!is_array($configs)) $configs = [];
        $configs[$grid_name] = json_decode($columns, true);
        query("UPDATE grid_user_padrao SET gup_tx_configs = ? WHERE gup_nb_id = ?", "si", [json_encode($configs), $padrao["gup_nb_id"]]);

        // Mantém a config ativa sincronizada (compatibilidade)
        $stmt = $conn->prepare("INSERT INTO grid_user_config (guc_nb_user, guc_tx_grid, guc_tx_columns) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE guc_tx_columns = VALUES(guc_tx_columns)");
        $stmt->bind_param("iss", $user_id, $grid_name, $columns);
        $stmt->execute();

        echo json_encode(['success' => true]);
        exit;
    }

    if($gucAcao === 'renomear_padrao'){
        $ordem = (int)($_POST['ordem'] ?? 1);
        $nome = trim(strval($_POST['nome'] ?? ''));
        if($ordem < 1 || $ordem > 3 || $nome === ''){
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }
        grid_ensure_padroes((int)$user_id);
        query("UPDATE grid_user_padrao SET gup_tx_nome = ? WHERE gup_nb_user = ? AND gup_nb_ordem = ?", "sii", [$nome, (int)$user_id, $ordem]);
        echo json_encode(['success' => true]);
        exit;
    }

    if($gucAcao === 'ativar_padrao'){
        $ordem = (int)($_POST['ordem'] ?? 1);
        if($ordem < 1 || $ordem > 3){
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }
        grid_ensure_padroes((int)$user_id);
        query("UPDATE grid_user_padrao SET gup_tx_ativo = 'nao' WHERE gup_nb_user = ?", "i", [(int)$user_id]);
        query("UPDATE grid_user_padrao SET gup_tx_ativo = 'sim' WHERE gup_nb_user = ? AND gup_nb_ordem = ?", "ii", [(int)$user_id, $ordem]);

        // Sincroniza as configs do padrão ativado com a config ativa (todos os grids)
        $padrao = mysqli_fetch_assoc(query("SELECT gup_tx_configs FROM grid_user_padrao WHERE gup_nb_user = ? AND gup_nb_ordem = ?", "ii", [(int)$user_id, $ordem]));
        if(!empty($padrao)){
            $configs = json_decode(strval($padrao["gup_tx_configs"] ?? "{}"), true);
            if(is_array($configs)){
                foreach($configs as $grid => $colunas){
                    if(is_string($grid) && $grid !== ''){
                        $colJson = json_encode($colunas);
                        $stmt = $conn->prepare("INSERT INTO grid_user_config (guc_nb_user, guc_tx_grid, guc_tx_columns) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE guc_tx_columns = VALUES(guc_tx_columns)");
                        $stmt->bind_param("iss", $user_id, $grid, $colJson);
                        $stmt->execute();
                    }
                }
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if($grid_name === '' || $columns === ''){
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    // Salvar no padrão ativo (comportamento padrão ao configurar colunas)
    grid_ensure_padroes((int)$user_id);
    $padraoAtivo = grid_padrao_ativo((int)$user_id);
    if(!empty($padraoAtivo)){
        $configs = json_decode(strval($padraoAtivo["gup_tx_configs"] ?? "{}"), true);
        if(!is_array($configs)) $configs = [];
        $configs[$grid_name] = json_decode($columns, true);
        query("UPDATE grid_user_padrao SET gup_tx_configs = ? WHERE gup_nb_id = ?", "si", [json_encode($configs), $padraoAtivo["gup_nb_id"]]);
    }

    $stmt = $conn->prepare("INSERT INTO grid_user_config (guc_nb_user, guc_tx_grid, guc_tx_columns) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE guc_tx_columns = VALUES(guc_tx_columns)");
    $stmt->bind_param("iss", $user_id, $grid_name, $columns);
    
    if($stmt->execute()){
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $stmt->error]);
    }
}
?>
