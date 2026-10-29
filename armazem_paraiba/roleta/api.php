<?php
require_once __DIR__ . '/db.php';

$action = $_REQUEST['action'] ?? '';

// Admin actions require super admin session
$adminActions = ['stats','list_premios_all','create_premio','edit_premio','delete_premio','ajustar_estoque',
    'list_leads','create_lead','edit_lead','check_documento','delete_lead','list_codigos','create_codigo','edit_codigo','delete_codigo',
    'list_qrcodes_all','create_qrcode','edit_qrcode','delete_qrcode',
    'get_config','set_config','historico','export','import','backup'];
$isAdminAction = in_array($action, $adminActions, true);

if ($isAdminAction) {
    include_once dirname(__DIR__) . '/conecta.php';
    include_once dirname(__DIR__) . '/check_permission.php';
    if (!usuarioPodeAcessar('/roleta/admin.php')) {
        roleta_json(['error' => 'nao_autorizado'], 401);
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// Helper to read JSON body
function roleta_body() {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : $_POST;
}

switch ($action) {

// ---------- PUBLIC ----------
case 'listar_premios':
    $rows = [];
    // inclui os esgotados para continuarem visíveis na roleta (não entram no sorteio)
    $q = mysqli_query($conn, "SELECT id,codigo,nome,foto,cor,peso,quantidade,girar_novamente FROM roleta_premios WHERE ativo=1 ORDER BY id");
    while ($r = mysqli_fetch_assoc($q)) {
        $r['id'] = (int)$r['id'];
        $r['peso'] = (int)$r['peso'];
        $r['girar_novamente'] = ((int)$r['girar_novamente'] === 1);
        $r['disponivel'] = ($r['girar_novamente'] || (int)$r['quantidade'] > 0);
        unset($r['quantidade']);
        $rows[] = $r;
    }
    roleta_json($rows);
    break;

case 'listar_qrcodes':
    $rows = [];
    $q = mysqli_query($conn, "SELECT id,titulo,imagem,logo FROM roleta_qrcodes WHERE ativo=1 ORDER BY id");
    while ($r = mysqli_fetch_assoc($q)) {
        $r['id'] = (int)$r['id'];
        $rows[] = $r;
    }
    roleta_json($rows);
    break;

case 'validar':
    $data = roleta_body();
    $codigo = strtoupper(trim($data['codigo'] ?? ''));
    $modoLivre = roleta_cfg($conn, 'modo_livre', '0') === '1';
    if ($modoLivre && $codigo === '') {
        roleta_json(['ok'=>true,'modo_livre'=>true,'giros_restantes'=>null,'primeiro_nome'=>null]);
    }
    if ($codigo === '') roleta_json(['error'=>'codigo_obrigatorio'], 400);
    $stmt = mysqli_prepare($conn, "SELECT c.*, l.nome AS lead_nome FROM roleta_codigos c LEFT JOIN roleta_leads l ON l.id=c.lead_id WHERE c.codigo=?");
    mysqli_stmt_bind_param($stmt, 's', $codigo);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$row) roleta_json(['error'=>'codigo_invalido'], 404);
    if (!$row['ativo']) roleta_json(['error'=>'codigo_desativado'], 403);
    if ($row['expira_em'] && strtotime($row['expira_em']) < time()) roleta_json(['error'=>'codigo_expirado'], 403);
    $rest = (int)$row['giros_permitidos'] - (int)$row['giros_usados'];
    if ($rest <= 0) roleta_json(['error'=>'sem_giros'], 403);
    roleta_json(['ok'=>true,'modo_livre'=>false,'giros_restantes'=>$rest,'primeiro_nome'=>roleta_primeiro_nome($row['lead_nome'])]);
    break;

case 'girar':
    $data = roleta_body();
    $codigo = strtoupper(trim($data['codigo'] ?? ''));
    $modoLivre = roleta_cfg($conn, 'modo_livre', '0') === '1';
    $codRow = null;
    if (!($modoLivre && $codigo === '')) {
        if ($codigo === '') roleta_json(['error'=>'codigo_obrigatorio'], 400);
        $stmt = mysqli_prepare($conn, "SELECT * FROM roleta_codigos WHERE codigo=?");
        mysqli_stmt_bind_param($stmt, 's', $codigo);
        mysqli_stmt_execute($stmt);
        $codRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$codRow) roleta_json(['error'=>'codigo_invalido'], 404);
        if (!$codRow['ativo']) roleta_json(['error'=>'codigo_desativado'], 403);
        if ($codRow['expira_em'] && strtotime($codRow['expira_em']) < time()) roleta_json(['error'=>'codigo_expirado'], 403);
        if ((int)$codRow['giros_usados'] >= (int)$codRow['giros_permitidos']) roleta_json(['error'=>'sem_giros'], 403);
    }
    // buscar itens (inclui o "Gire Novamente" mesmo sem estoque)
    $q = mysqli_query($conn, "SELECT * FROM roleta_premios WHERE ativo=1 AND (quantidade>0 OR girar_novamente=1)");
    $itens = [];
    while ($r = mysqli_fetch_assoc($q)) $itens[] = $r;
    if (empty($itens)) roleta_json(['error'=>'sem_premios'], 400);
    // bônus de chance do lead (aumenta o peso dos brindes selecionados)
    $bonusLead = [];
    if ($codRow) $bonusLead = roleta_bonus_lead($conn, (int)$codRow['lead_id']);
    // sorteio ponderado (escala x100 para manter pesos inteiros)
    $pesos = [];
    foreach ($itens as $it) {
        $bonus = $bonusLead[(int)$it['id']] ?? 0;
        $pesos[] = max(1, (int)$it['peso']) * (100 + $bonus);
    }
    $total = array_sum($pesos);
    $rand = mt_rand(1, $total);
    $acc = 0; $escolhido = null;
    foreach ($itens as $i => $it) { $acc += $pesos[$i]; if ($rand <= $acc) { $escolhido = $it; break; } }
    if (!$escolhido) $escolhido = end($itens);
    $girarNovamente = ((int)$escolhido['girar_novamente'] === 1);
    // debitar estoque apenas de prêmios normais
    if (!$girarNovamente) {
        $upd = mysqli_prepare($conn, "UPDATE roleta_premios SET quantidade = quantidade - 1 WHERE id=?");
        mysqli_stmt_bind_param($upd, 'i', $escolhido['id']); mysqli_stmt_execute($upd); mysqli_stmt_close($upd);
    }
    // registrar giro (inclusive o "Gire Novamente", para histórico/estatísticas)
    $ins = mysqli_prepare($conn, "INSERT INTO roleta_giros (codigo_liberacao, lead_id, premio_id, premio_nome, premio_foto, premio_cor) VALUES (?,?,?,?,?,?)");
    $leadId = $codRow ? $codRow['lead_id'] : null;
    $foto = $escolhido['foto'];
    $cor = $escolhido['cor'];
    $nome = $escolhido['nome'];
    $pid = (int)$escolhido['id'];
    $codLib = $codigo ?: null;
    mysqli_stmt_bind_param($ins, 'siisss', $codLib, $leadId, $pid, $nome, $foto, $cor);
    mysqli_stmt_execute($ins); mysqli_stmt_close($ins);
    // "Gire Novamente" não consome o giro do código
    if ($codRow && !$girarNovamente) {
        $upd2 = mysqli_prepare($conn, "UPDATE roleta_codigos SET giros_usados = giros_usados + 1 WHERE id=?");
        $cid = (int)$codRow['id'];
        mysqli_stmt_bind_param($upd2, 'i', $cid); mysqli_stmt_execute($upd2); mysqli_stmt_close($upd2);
    }
    $restantes = null;
    if ($codRow) $restantes = (int)$codRow['giros_permitidos'] - ((int)$codRow['giros_usados'] + ($girarNovamente ? 0 : 1));
    roleta_json(['premio'=>[
        'id'=>(int)$escolhido['id'],
        'codigo'=>$escolhido['codigo'],
        'nome'=>$escolhido['nome'],
        'foto'=>$escolhido['foto'],
        'cor'=>$escolhido['cor'],
    ], 'giros_restantes'=>$restantes, 'girar_novamente'=>$girarNovamente]);
    break;

// ---------- ADMIN ----------
case 'stats':
    $total_premios = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM roleta_premios"))[0];
    $estoque_total = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COALESCE(SUM(quantidade),0) FROM roleta_premios"))[0];
    $giros_hoje = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM roleta_giros WHERE DATE(created_at)=CURDATE()"))[0];
    $giros_total = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM roleta_giros"))[0];
    $codigos_ativos = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM roleta_codigos WHERE ativo=1"))[0];
    $leads_total = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM roleta_leads"))[0];
    roleta_json(['total_premios'=>$total_premios,'estoque_total'=>$estoque_total,'giros_hoje'=>$giros_hoje,'giros_total'=>$giros_total,'codigos_ativos'=>$codigos_ativos,'leads_total'=>$leads_total]);
    break;

case 'list_premios_all':
    $rows = [];
    $q = mysqli_query($conn, "SELECT * FROM roleta_premios ORDER BY id DESC");
    while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;
    foreach ($rows as &$r) { $r['id']=(int)$r['id']; $r['peso']=(int)$r['peso']; $r['quantidade']=(int)$r['quantidade']; $r['ativo']=(int)$r['ativo']; $r['girar_novamente']=(int)$r['girar_novamente']; }
    unset($r);
    roleta_json($rows);
    break;

case 'create_premio':
    $nome = trim($_POST['nome'] ?? '');
    $cor = trim($_POST['cor'] ?? '#30a0b8');
    $peso = max(1, (int)($_POST['peso'] ?? 1));
    $quantidade = max(0, (int)($_POST['quantidade'] ?? 0));
    $ativo = (($_POST['ativo'] ?? '1') === '1') ? 1 : 0;
    $girarNovamente = (($_POST['girar_novamente'] ?? '0') === '1') ? 1 : 0;
    if ($nome === '') roleta_json(['error'=>'nome_obrigatorio'], 400);
    $foto_path = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png','jpg','jpeg','gif','webp'])) roleta_json(['error'=>'formato_invalido'], 400);
        $fname = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = __DIR__ . '/uploads/' . $fname;
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) roleta_json(['error'=>'upload_falhou'], 500);
        $foto_path = 'uploads/' . $fname;
    }
    $codigo = roleta_gen_codigo();
    $stmt = mysqli_prepare($conn, "INSERT INTO roleta_premios (codigo,nome,foto,cor,peso,quantidade,ativo,girar_novamente) VALUES (?,?,?,?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'ssssiiii', $codigo, $nome, $foto_path, $cor, $peso, $quantidade, $ativo, $girarNovamente);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    $id = mysqli_insert_id($conn);
    $q = mysqli_query($conn, "SELECT * FROM roleta_premios WHERE id=$id");
    roleta_json(mysqli_fetch_assoc($q), 201);
    break;

case 'edit_premio':
    $id = (int)($_POST['id'] ?? 0);
    // load existing
    $stmt = mysqli_prepare($conn, "SELECT * FROM roleta_premios WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
    if (!$row) roleta_json(['error'=>'nao_encontrado'], 404);
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : $row['nome'];
    $cor = isset($_POST['cor']) ? trim($_POST['cor']) : $row['cor'];
    $peso = isset($_POST['peso']) ? max(1,(int)$_POST['peso']) : (int)$row['peso'];
    $quantidade = isset($_POST['quantidade']) ? max(0,(int)$_POST['quantidade']) : (int)$row['quantidade'];
    $ativo = isset($_POST['ativo']) ? (($_POST['ativo']==='1')?1:0) : (int)$row['ativo'];
    $girarNovamente = isset($_POST['girar_novamente']) ? (($_POST['girar_novamente']==='1')?1:0) : (int)$row['girar_novamente'];
    $foto_path = $row['foto'];
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png','jpg','jpeg','gif','webp'])) roleta_json(['error'=>'formato_invalido'], 400);
        $fname = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = __DIR__ . '/uploads/' . $fname;
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) roleta_json(['error'=>'upload_falhou'], 500);
        if ($row['foto'] && file_exists(__DIR__ . '/' . $row['foto'])) @unlink(__DIR__ . '/' . $row['foto']);
        $foto_path = 'uploads/' . $fname;
    }
    $stmt = mysqli_prepare($conn, "UPDATE roleta_premios SET nome=?,cor=?,peso=?,quantidade=?,ativo=?,girar_novamente=?,foto=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'ssiiiisi', $nome, $cor, $peso, $quantidade, $ativo, $girarNovamente, $foto_path, $id);
    // php 5.6+ splat; ensure version. Use call_user_func_array fallback not needed for 8.3
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    $q = mysqli_query($conn, "SELECT * FROM roleta_premios WHERE id=$id");
    roleta_json(mysqli_fetch_assoc($q));
    break;

case 'delete_premio':
    $data = roleta_body();
    $id = (int)($data['id'] ?? $_POST['id'] ?? 0);
    // get foto
    $stmt = mysqli_prepare($conn, "SELECT foto FROM roleta_premios WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
    if ($row && $row['foto'] && file_exists(__DIR__ . '/' . $row['foto'])) @unlink(__DIR__ . '/' . $row['foto']);
    $stmt = mysqli_prepare($conn, "DELETE FROM roleta_premios WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    roleta_json(['ok'=>true]);
    break;

case 'ajustar_estoque':
    $data = roleta_body();
    $id = (int)($data['id'] ?? 0);
    $delta = (int)($data['delta'] ?? 0);
    $stmt = mysqli_prepare($conn, "UPDATE roleta_premios SET quantidade = GREATEST(0, quantidade + ?) WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'ii', $delta, $id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    $stmt = mysqli_prepare($conn, "SELECT quantidade FROM roleta_premios WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
    roleta_json(['quantidade' => (int)$row['quantidade']]);
    break;

case 'list_leads':
    $rows = [];
    $q = mysqli_query($conn, "SELECT l.*,
        (SELECT codigo FROM roleta_codigos WHERE lead_id=l.id ORDER BY id DESC LIMIT 1) AS codigo,
        (SELECT giros_permitidos FROM roleta_codigos WHERE lead_id=l.id ORDER BY id DESC LIMIT 1) AS giros_permitidos,
        (SELECT giros_usados FROM roleta_codigos WHERE lead_id=l.id ORDER BY id DESC LIMIT 1) AS giros_usados,
        (SELECT expira_em FROM roleta_codigos WHERE lead_id=l.id ORDER BY id DESC LIMIT 1) AS expira_em,
        (SELECT GROUP_CONCAT(DISTINCT g.premio_nome) FROM roleta_giros g LEFT JOIN roleta_premios p ON p.id=g.premio_id WHERE g.lead_id=l.id AND (p.girar_novamente IS NULL OR p.girar_novamente=0)) AS premios_ganhos
        FROM roleta_leads l ORDER BY l.id DESC");
    while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;
    // bônus de chance por lead
    $bonusMap = [];
    $bq = mysqli_query($conn, "SELECT lp.lead_id, lp.premio_id, lp.bonus, p.nome FROM roleta_lead_premios lp INNER JOIN roleta_premios p ON p.id=lp.premio_id ORDER BY p.nome");
    while ($b = mysqli_fetch_assoc($bq)) {
        $bonusMap[(int)$b['lead_id']][] = ['premio_id'=>(int)$b['premio_id'], 'bonus'=>(int)$b['bonus'], 'nome'=>$b['nome']];
    }
    foreach ($rows as &$r) {
        $r['id']=(int)$r['id'];
        $r['bonus'] = $bonusMap[$r['id']] ?? [];
    }
    unset($r);
    roleta_json($rows);
    break;

case 'check_documento':
    $data = roleta_body();
    $documento = roleta_somente_digitos($data['documento'] ?? '');
    $leadId = (int)($data['id'] ?? 0);
    if ($documento === '') roleta_json(['error'=>'documento_obrigatorio'], 400);
    if (!roleta_valida_documento($documento)) roleta_json(['error'=>'documento_invalido'], 400);
    if ($leadId > 0) {
        $stmt = mysqli_prepare($conn, "SELECT id, nome FROM roleta_leads WHERE documento=? AND id<>? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'si', $documento, $leadId);
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, nome FROM roleta_leads WHERE documento=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $documento);
    }
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($row) roleta_json(['existe'=>true,'lead_id'=>(int)$row['id'],'nome'=>$row['nome']]);
    roleta_json(['existe'=>false]);
    break;

case 'create_lead':
    $data = roleta_body();
    $nome = trim($data['nome'] ?? '');
    $email = strtolower(trim($data['email'] ?? '')) ?: null;
    $telefone = roleta_somente_digitos($data['telefone'] ?? '') ?: null;
    $empresa = trim($data['empresa'] ?? '') ?: null;
    $documento = roleta_somente_digitos($data['documento'] ?? '') ?: null;
    $segmento = trim($data['segmento'] ?? '') ?: null;
    $funcao = trim($data['funcao'] ?? '') ?: null;
    $observacoes = trim($data['observacoes'] ?? '') ?: null;
    $giros = max(1, (int)($data['giros'] ?? 1));
    $expira = trim($data['expira_em'] ?? '') ?: null;
    $bonusPremios = roleta_parse_bonus_premios($data['bonus_premios'] ?? []);
    if ($nome === '') roleta_json(['error'=>'nome_obrigatorio'], 400);
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) roleta_json(['error'=>'email_invalido'], 400);
    if ($telefone !== null && !in_array(strlen($telefone), [10, 11], true)) roleta_json(['error'=>'telefone_invalido'], 400);
    if ($documento !== null) {
        if (!roleta_valida_documento($documento)) roleta_json(['error'=>'documento_invalido'], 400);
        $stmt = mysqli_prepare($conn, "SELECT id FROM roleta_leads WHERE documento=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $documento);
        mysqli_stmt_execute($stmt);
        $docExiste = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($docExiste) roleta_json(['error'=>'documento_duplicado'], 409);
    }
    mysqli_begin_transaction($conn);
    $stmt = mysqli_prepare($conn, "INSERT INTO roleta_leads (nome,email,telefone,empresa,documento,segmento,funcao,observacoes) VALUES (?,?,?,?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'ssssssss', $nome, $email, $telefone, $empresa, $documento, $segmento, $funcao, $observacoes);
    mysqli_stmt_execute($stmt);
    $leadId = mysqli_insert_id($conn); mysqli_stmt_close($stmt);
    roleta_sync_bonus_lead($conn, $leadId, $bonusPremios);
    $codigo = roleta_gen_liberacao($conn);
    $stmt = mysqli_prepare($conn, "INSERT INTO roleta_codigos (codigo,lead_id,giros_permitidos,expira_em) VALUES (?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'siis', $codigo, $leadId, $giros, $expira);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    mysqli_commit($conn);
    roleta_json(['lead_id'=>$leadId,'codigo'=>$codigo], 201);
    break;

case 'edit_lead':
    $data = roleta_body();
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) roleta_json(['error'=>'id_obrigatorio'], 400);
    $stmt = mysqli_prepare($conn, "SELECT * FROM roleta_leads WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
    if (!$row) roleta_json(['error'=>'nao_encontrado'], 404);
    $nome = isset($data['nome']) ? trim((string)$data['nome']) : $row['nome'];
    if ($nome === '') roleta_json(['error'=>'nome_obrigatorio'], 400);
    $email = array_key_exists('email', $data) ? (strtolower(trim((string)$data['email'])) ?: null) : $row['email'];
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) roleta_json(['error'=>'email_invalido'], 400);
    $telefone = array_key_exists('telefone', $data) ? (roleta_somente_digitos($data['telefone']) ?: null) : (roleta_somente_digitos($row['telefone']) ?: null);
    if ($telefone !== null && !in_array(strlen($telefone), [10, 11], true)) roleta_json(['error'=>'telefone_invalido'], 400);
    $empresa = array_key_exists('empresa', $data) ? (trim((string)$data['empresa']) ?: null) : $row['empresa'];
    $documento = array_key_exists('documento', $data) ? (roleta_somente_digitos($data['documento']) ?: null) : (roleta_somente_digitos($row['documento']) ?: null);
    if ($documento !== null) {
        if (!roleta_valida_documento($documento)) roleta_json(['error'=>'documento_invalido'], 400);
        $stmt = mysqli_prepare($conn, "SELECT id FROM roleta_leads WHERE documento=? AND id<>? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'si', $documento, $id);
        mysqli_stmt_execute($stmt);
        $docExiste = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($docExiste) roleta_json(['error'=>'documento_duplicado'], 409);
    }
    $segmento = array_key_exists('segmento', $data) ? (trim((string)$data['segmento']) ?: null) : $row['segmento'];
    $funcao = array_key_exists('funcao', $data) ? (trim((string)$data['funcao']) ?: null) : $row['funcao'];
    $observacoes = array_key_exists('observacoes', $data) ? (trim((string)$data['observacoes']) ?: null) : $row['observacoes'];
    mysqli_begin_transaction($conn);
    $stmt = mysqli_prepare($conn, "UPDATE roleta_leads SET nome=?,email=?,telefone=?,empresa=?,documento=?,segmento=?,funcao=?,observacoes=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'ssssssssi', $nome, $email, $telefone, $empresa, $documento, $segmento, $funcao, $observacoes, $id);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    if (array_key_exists('bonus_premios', $data)) {
        roleta_sync_bonus_lead($conn, $id, roleta_parse_bonus_premios($data['bonus_premios']));
    }
    // atualiza giros/expiração do código mais recente do lead
    if (array_key_exists('giros', $data) || array_key_exists('expira_em', $data)) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM roleta_codigos WHERE lead_id=? ORDER BY id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt);
        $codRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
        if ($codRow) {
            $fields = []; $types = ''; $vals = [];
            if (array_key_exists('giros', $data)) { $fields[]='giros_permitidos=?'; $types.='i'; $vals[]=max(1,(int)$data['giros']); }
            if (array_key_exists('expira_em', $data)) { $fields[]='expira_em=?'; $types.='s'; $vals[]=trim((string)$data['expira_em']) ?: null; }
            if ($fields) {
                $types .= 'i'; $vals[] = (int)$codRow['id'];
                $stmt = mysqli_prepare($conn, "UPDATE roleta_codigos SET ".implode(',', $fields)." WHERE id=?");
                mysqli_stmt_bind_param($stmt, $types, ...$vals);
                mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            }
        }
    }
    mysqli_commit($conn);
    roleta_json(['ok'=>true,'lead_id'=>$id]);
    break;

case 'delete_lead':
    $data = roleta_body();
    $id = (int)($data['id'] ?? 0);
    mysqli_query($conn, "DELETE FROM roleta_codigos WHERE lead_id=$id");
    $stmt = mysqli_prepare($conn, "DELETE FROM roleta_leads WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    roleta_json(['ok'=>true]);
    break;

case 'list_codigos':
    $rows = [];
    $q = mysqli_query($conn, "SELECT c.*, l.nome AS lead_nome, l.email AS lead_email, l.telefone AS lead_telefone, l.empresa AS lead_empresa FROM roleta_codigos c LEFT JOIN roleta_leads l ON l.id=c.lead_id ORDER BY c.id DESC");
    while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;
    foreach ($rows as &$r) { $r['id']=(int)$r['id']; $r['giros_permitidos']=(int)$r['giros_permitidos']; $r['giros_usados']=(int)$r['giros_usados']; $r['ativo']=(int)$r['ativo']; }
    roleta_json($rows);
    break;

case 'create_codigo':
    $data = roleta_body();
    $giros = max(1, (int)($data['giros'] ?? 1));
    $expira = trim($data['expira_em'] ?? '') ?: null;
    $leadId = null;
    $nome = trim($data['nome'] ?? '');
    if ($nome !== '') {
        $email = trim($data['email'] ?? '') ?: null;
        $telefone = trim($data['telefone'] ?? '') ?: null;
        $empresa = trim($data['empresa'] ?? '') ?: null;
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) roleta_json(['error'=>'email_invalido'], 400);
        $stmt = mysqli_prepare($conn, "INSERT INTO roleta_leads (nome,email,telefone,empresa) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $nome, $email, $telefone, $empresa);
        mysqli_stmt_execute($stmt); $leadId = mysqli_insert_id($conn); mysqli_stmt_close($stmt);
    } elseif (!empty($data['lead_id'])) {
        $leadId = (int)$data['lead_id'];
    }
    $codigo = roleta_gen_liberacao($conn);
    $stmt = mysqli_prepare($conn, "INSERT INTO roleta_codigos (codigo,lead_id,giros_permitidos,expira_em) VALUES (?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'siis', $codigo, $leadId, $giros, $expira);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    roleta_json(['codigo'=>$codigo], 201);
    break;

case 'edit_codigo':
    $data = roleta_body();
    $id = (int)($data['id'] ?? 0);
    $fields = []; $types = ''; $vals = [];
    if (isset($data['ativo'])) { $fields[]='ativo=?'; $types.='i'; $vals[] = $data['ativo'] ? 1 : 0; }
    if (isset($data['giros_permitidos'])) { $fields[]='giros_permitidos=?'; $types.='i'; $vals[] = max(1,(int)$data['giros_permitidos']); }
    if ($fields) {
        $sql = "UPDATE roleta_codigos SET " . implode(',', $fields) . " WHERE id=?";
        $types .= 'i'; $vals[] = $id;
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$vals);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    $stmt = mysqli_prepare($conn, "SELECT * FROM roleta_codigos WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt);
    roleta_json(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)));
    break;

case 'delete_codigo':
    $data = roleta_body();
    $id = (int)($data['id'] ?? 0);
    $stmt = mysqli_prepare($conn, "DELETE FROM roleta_codigos WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    roleta_json(['ok'=>true]);
    break;

case 'list_qrcodes_all':
    $rows = [];
    $q = mysqli_query($conn, "SELECT * FROM roleta_qrcodes ORDER BY id DESC");
    while ($r = mysqli_fetch_assoc($q)) {
        $r['id'] = (int)$r['id'];
        $r['ativo'] = (int)$r['ativo'];
        $rows[] = $r;
    }
    roleta_json($rows);
    break;

case 'create_qrcode':
    $titulo = trim($_POST['titulo'] ?? '');
    $ativo = (($_POST['ativo'] ?? '1') === '1') ? 1 : 0;
    if ($titulo === '') roleta_json(['error'=>'titulo_obrigatorio'], 400);
    $imagem = roleta_salvar_upload_qr($_FILES['imagem'] ?? null);
    if ($imagem === null) roleta_json(['error'=>'imagem_obrigatoria'], 400);
    if ($imagem === false) roleta_json(['error'=>'upload_falhou'], 400);
    $logo = roleta_salvar_upload_logo($_FILES['logo'] ?? null);
    if ($logo === false) {
        if ($imagem && file_exists(__DIR__ . '/' . $imagem)) @unlink(__DIR__ . '/' . $imagem);
        roleta_json(['error'=>'logo_upload_falhou'], 400);
    }
    $stmt = mysqli_prepare($conn, "INSERT INTO roleta_qrcodes (titulo,imagem,logo,ativo) VALUES (?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'sssi', $titulo, $imagem, $logo, $ativo);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    $id = mysqli_insert_id($conn);
    $q = mysqli_query($conn, "SELECT * FROM roleta_qrcodes WHERE id=$id");
    roleta_json(mysqli_fetch_assoc($q), 201);
    break;

case 'edit_qrcode':
    $id = (int)($_POST['id'] ?? 0);
    $stmt = mysqli_prepare($conn, "SELECT * FROM roleta_qrcodes WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
    if (!$row) roleta_json(['error'=>'nao_encontrado'], 404);
    $titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : $row['titulo'];
    if ($titulo === '') roleta_json(['error'=>'titulo_obrigatorio'], 400);
    $ativo = isset($_POST['ativo']) ? (($_POST['ativo'] === '1') ? 1 : 0) : (int)$row['ativo'];
    $imagem = $row['imagem'];
    $novaImagem = roleta_salvar_upload_qr($_FILES['imagem'] ?? null);
    if ($novaImagem === false) roleta_json(['error'=>'upload_falhou'], 400);
    $logo = $row['logo'] ?? null;
    $novaLogo = roleta_salvar_upload_logo($_FILES['logo'] ?? null);
    if ($novaLogo === false) {
        if ($novaImagem !== null && file_exists(__DIR__ . '/' . $novaImagem)) @unlink(__DIR__ . '/' . $novaImagem);
        roleta_json(['error'=>'logo_upload_falhou'], 400);
    }
    if ($novaImagem !== null) $imagem = $novaImagem;
    if ($novaLogo !== null) $logo = $novaLogo;
    $stmt = mysqli_prepare($conn, "UPDATE roleta_qrcodes SET titulo=?,imagem=?,logo=?,ativo=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'sssii', $titulo, $imagem, $logo, $ativo, $id);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    if ($novaImagem !== null && !empty($row['imagem']) && $row['imagem'] !== $imagem && file_exists(__DIR__ . '/' . $row['imagem'])) @unlink(__DIR__ . '/' . $row['imagem']);
    if ($novaLogo !== null && !empty($row['logo']) && $row['logo'] !== $logo && file_exists(__DIR__ . '/' . $row['logo'])) @unlink(__DIR__ . '/' . $row['logo']);
    $q = mysqli_query($conn, "SELECT * FROM roleta_qrcodes WHERE id=$id");
    roleta_json(mysqli_fetch_assoc($q));
    break;

case 'delete_qrcode':
    $data = roleta_body();
    $id = (int)($data['id'] ?? 0);
    $stmt = mysqli_prepare($conn, "SELECT imagem,logo FROM roleta_qrcodes WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
    if ($row && $row['imagem'] && file_exists(__DIR__ . '/' . $row['imagem'])) @unlink(__DIR__ . '/' . $row['imagem']);
    if ($row && !empty($row['logo']) && file_exists(__DIR__ . '/' . $row['logo'])) @unlink(__DIR__ . '/' . $row['logo']);
    $stmt = mysqli_prepare($conn, "DELETE FROM roleta_qrcodes WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    roleta_json(['ok'=>true]);
    break;

case 'get_config':
    roleta_json(['modo_livre' => roleta_cfg($conn,'modo_livre','0')==='1']);
    break;

case 'set_config':
    $data = roleta_body();
    if (isset($data['modo_livre'])) roleta_setcfg($conn, 'modo_livre', $data['modo_livre'] ? '1' : '0');
    if (!empty($data['senha'])) {
        $hash = password_hash($data['senha'], PASSWORD_DEFAULT);
        roleta_setcfg($conn, 'admin_password_hash', $hash);
    }
    roleta_json(['ok'=>true]);
    break;

case 'historico':
    $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));
    $rows = [];
    $q = mysqli_query($conn, "SELECT g.*, l.nome AS lead_nome FROM roleta_giros g LEFT JOIN roleta_leads l ON l.id=g.lead_id ORDER BY g.id DESC LIMIT $limit");
    while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;
    foreach ($rows as &$r) { $r['id']=(int)$r['id']; }
    roleta_json($rows);
    break;

case 'export':
    $data = [
        'premios' => [],
        'leads' => [],
        'lead_premios' => [],
        'codigos' => [],
        'giros' => [],
        'qrcodes' => [],
        'config' => [],
    ];
    $q = mysqli_query($conn, "SELECT * FROM roleta_premios"); while ($r = mysqli_fetch_assoc($q)) $data['premios'][] = $r;
    $q = mysqli_query($conn, "SELECT * FROM roleta_leads"); while ($r = mysqli_fetch_assoc($q)) $data['leads'][] = $r;
    $q = mysqli_query($conn, "SELECT * FROM roleta_lead_premios"); while ($r = mysqli_fetch_assoc($q)) $data['lead_premios'][] = $r;
    $q = mysqli_query($conn, "SELECT * FROM roleta_codigos"); while ($r = mysqli_fetch_assoc($q)) $data['codigos'][] = $r;
    $q = mysqli_query($conn, "SELECT * FROM roleta_giros"); while ($r = mysqli_fetch_assoc($q)) $data['giros'][] = $r;
    $q = mysqli_query($conn, "SELECT * FROM roleta_qrcodes"); while ($r = mysqli_fetch_assoc($q)) $data['qrcodes'][] = $r;
    $q = mysqli_query($conn, "SELECT * FROM roleta_config"); while ($r = mysqli_fetch_assoc($q)) $data['config'][] = $r;
    roleta_json($data);
    break;

case 'import':
    $data = roleta_body();
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, "DELETE FROM roleta_giros");
        mysqli_query($conn, "DELETE FROM roleta_codigos");
        mysqli_query($conn, "DELETE FROM roleta_lead_premios");
        mysqli_query($conn, "DELETE FROM roleta_leads");
        mysqli_query($conn, "DELETE FROM roleta_premios");
        mysqli_query($conn, "DELETE FROM roleta_qrcodes");
        mysqli_query($conn, "DELETE FROM roleta_config");
        foreach (($data['premios'] ?? []) as $p) {
            $stmt = mysqli_prepare($conn, "INSERT INTO roleta_premios (id,codigo,nome,foto,cor,peso,quantidade,ativo,girar_novamente,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $id=(int)($p['id']??0); $codigo=$p['codigo']; $nome=$p['nome']; $foto=$p['foto']??null; $cor=$p['cor']??'#30a0b8';
            $peso=(int)($p['peso']??1); $qtd=(int)($p['quantidade']??0); $ativo=(int)($p['ativo']??1);
            $girarNovamente=(int)($p['girar_novamente']??0); $ca=$p['created_at']??date('Y-m-d H:i:s');
            mysqli_stmt_bind_param($stmt, 'issssiiiis', $id,$codigo,$nome,$foto,$cor,$peso,$qtd,$ativo,$girarNovamente,$ca);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        foreach (($data['leads'] ?? []) as $l) {
            $stmt = mysqli_prepare($conn, "INSERT INTO roleta_leads (id,nome,email,telefone,empresa,documento,segmento,funcao,observacoes,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $id=(int)($l['id']??0); $nome=$l['nome']; $email=$l['email']??null;
            $tel=!empty($l['telefone'])?roleta_somente_digitos($l['telefone']):null;
            $emp=$l['empresa']??null; $doc=!empty($l['documento'])?roleta_somente_digitos($l['documento']):null;
            $seg=$l['segmento']??null; $fun=$l['funcao']??null; $obs=$l['observacoes']??null; $ca=$l['created_at']??date('Y-m-d H:i:s');
            mysqli_stmt_bind_param($stmt, 'isssssssss', $id,$nome,$email,$tel,$emp,$doc,$seg,$fun,$obs,$ca);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        foreach (($data['lead_premios'] ?? []) as $lp) {
            $stmt = mysqli_prepare($conn, "INSERT INTO roleta_lead_premios (id,lead_id,premio_id,bonus,created_at) VALUES (?,?,?,?,?)");
            $id=(int)($lp['id']??0); $lid=(int)($lp['lead_id']??0); $pid=(int)($lp['premio_id']??0); $bonus=(int)($lp['bonus']??0);
            $ca=$lp['created_at']??date('Y-m-d H:i:s');
            mysqli_stmt_bind_param($stmt, 'iiiis', $id,$lid,$pid,$bonus,$ca);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        foreach (($data['codigos'] ?? []) as $c) {
            $stmt = mysqli_prepare($conn, "INSERT INTO roleta_codigos (id,codigo,lead_id,giros_permitidos,giros_usados,expira_em,ativo,created_at) VALUES (?,?,?,?,?,?,?,?)");
            $id=(int)($c['id']??0); $codigo=$c['codigo']; $leadId=$c['lead_id']??null; $gp=(int)($c['giros_permitidos']??1);
            $gu=(int)($c['giros_usados']??0); $exp=$c['expira_em']??null; $ativo=(int)($c['ativo']??1); $ca=$c['created_at']??date('Y-m-d H:i:s');
            mysqli_stmt_bind_param($stmt, 'isiisiis', $id,$codigo,$leadId,$gp,$gu,$exp,$ativo,$ca);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        foreach (($data['giros'] ?? []) as $g) {
            $stmt = mysqli_prepare($conn, "INSERT INTO roleta_giros (id,codigo_liberacao,lead_id,premio_id,premio_nome,premio_foto,premio_cor,created_at) VALUES (?,?,?,?,?,?,?,?)");
            $id=(int)($g['id']??0); $cl=$g['codigo_liberacao']??null; $lid=$g['lead_id']??null; $pid=$g['premio_id']??null;
            $pn=$g['premio_nome']??null; $pf=$g['premio_foto']??null; $pc=$g['premio_cor']??null; $ca=$g['created_at']??date('Y-m-d H:i:s');
            mysqli_stmt_bind_param($stmt, 'isiissss', $id,$cl,$lid,$pid,$pn,$pf,$pc,$ca);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        foreach (($data['qrcodes'] ?? []) as $qr) {
            $stmt = mysqli_prepare($conn, "INSERT INTO roleta_qrcodes (id,titulo,imagem,logo,ativo,created_at) VALUES (?,?,?,?,?,?)");
            $id=(int)($qr['id']??0); $titulo=$qr['titulo']??''; $imagem=$qr['imagem']??''; $logo=$qr['logo']??null;
            $ativo=(int)($qr['ativo']??1); $ca=$qr['created_at']??date('Y-m-d H:i:s');
            mysqli_stmt_bind_param($stmt, 'isssis', $id,$titulo,$imagem,$logo,$ativo,$ca);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        foreach (($data['config'] ?? []) as $cfg) {
            $stmt = mysqli_prepare($conn, "INSERT INTO roleta_config (chave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)");
            mysqli_stmt_bind_param($stmt, 'ss', $cfg['chave'], $cfg['valor']);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        roleta_json(['error' => $e->getMessage()], 400);
    }
    roleta_json(['ok'=>true]);
    break;

default:
    roleta_json(['error'=>'acao_invalida'], 404);
}
?>