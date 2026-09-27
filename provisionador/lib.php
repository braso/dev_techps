<?php
/* ==========================================================================
   Provisionador de empresas — biblioteca (somente linha de comando / cron)

   Cria uma empresa nova de ponta a ponta: banco + usuário, estrutura e dados
   de referência a partir de um banco modelo, pasta com os arquivos do sistema,
   .env, empresa matriz + usuário administrador e registro no login.

   Tudo o que é criado fica anotado no pedido (prov_tx_criados) para que
   "desfazer" remova exatamente o que foi criado, e nada além disso.
   ========================================================================== */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Acesso negado."); }

define('PROV_DIR', __DIR__);
define('PROV_ROOT', realpath(__DIR__ . '/..'));

class ProvErro extends Exception {}

// ---------------------------------------------------------------- .env
function prov_ler_env(string $arquivo): array {
    if (!is_file($arquivo)) return [];
    $env = @parse_ini_file($arquivo, false, INI_SCANNER_RAW);
    if (!is_array($env)) return [];
    $out = [];
    foreach ($env as $k => $v) {
        $v = trim((string)$v);
        if (strlen($v) >= 2) {
            $f = $v[0]; $l = $v[strlen($v) - 1];
            if (($f === "'" && $l === "'") || ($f === '"' && $l === '"')) $v = substr($v, 1, -1);
        }
        $out[$k] = $v;
    }
    return $out;
}

function prov_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $root = prov_ler_env(PROV_ROOT . '/.env');
    $get = function (string $k, string $def = '') use ($root) { return isset($root[$k]) && $root[$k] !== '' ? $root[$k] : $def; };

    $cfg = [
        'driver'           => strtolower($get('PROVISION_DRIVER', 'sql')),          // sql | uapi
        'db_prefix'        => $get('PROVISION_DB_PREFIX', ''),                       // ex.: tech1694_
        'admin_host'       => $get('PROVISION_DB_ADMIN_HOST', ''),
        'admin_user'       => $get('PROVISION_DB_ADMIN_USER', ''),
        'admin_password'   => $get('PROVISION_DB_ADMIN_PASSWORD', ''),
        'template_tenant'  => $get('PROVISION_TEMPLATE_TENANT', 'armazem_paraiba'),  // pasta cujo banco serve de modelo
        'master_tenant'    => $get('PROVISION_MASTER_TENANT', 'armazem_paraiba'),    // pasta cujo banco guarda a fila de pedidos
        'source_dir'       => rtrim($get('PROVISION_SOURCE_DIR', PROV_ROOT . '/armazem_paraiba'), '/\\'),
        'target_root'      => rtrim($get('PROVISION_TARGET_ROOT', PROV_ROOT), '/\\'),
        'registry_file'    => $get('PROVISION_REGISTRY_FILE', PROV_ROOT . '/empresas.json'),
        'copy_super_admins'=> $get('PROVISION_COPY_SUPER_ADMINS', '1') === '1',
        'uapi_bin'         => $get('PROVISION_UAPI_BIN', 'uapi'),
        'log_file'         => $get('PROVISION_LOG_FILE', PROV_DIR . '/logs/provisionador.log'),
        // Tabelas copiadas COM dados do banco modelo (cadastros de referência)
        'reference_tables' => array_values(array_filter(array_map('trim', explode(',', $get(
            'PROVISION_REFERENCE_TABLES',
            'cidade,macroponto,motivo,feriado,parametro,menu_item,perfil_acesso,perfil_menu_item,grupos_documentos,sbgrupos_documentos,tipos_documentos,poi_tipo,poi_acao_esperada,documento_parametro,diaria_parametro'
        ))))),
        // Caminhos (relativos à pasta da empresa) que NÃO são copiados: dados e segredos da empresa modelo
        'exclude_paths'    => ['.env', '.provisionado', 'arquivos', 'assinatura/uploads', 'assinatura/docAssinado', 'assinatura/docFinalizado',
                               'assinatura/rubricas', 'assinatura/pfx', 'assinatura/docs', 'treinamento/uploads', 'ws/app'],
        // Pastas vazias que a empresa nova precisa ter
        'empty_dirs'       => ['arquivos', 'assinatura/uploads', 'assinatura/docAssinado', 'assinatura/docFinalizado', 'assinatura/rubricas', 'ws/app'],
    ];
    return $cfg;
}

function prov_tenant_env(string $pasta): array {
    $cfg = prov_config();
    $env = prov_ler_env($cfg['target_root'] . '/' . $pasta . '/.env');
    if (empty($env['DB_NAME'])) throw new ProvErro("Não encontrei o .env (ou DB_NAME) da empresa '{$pasta}'.");
    return $env;
}

// ---------------------------------------------------------------- log
function prov_log(string $msg): void {
    $cfg = prov_config();
    $linha = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    $dir = dirname($cfg['log_file']);
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    @file_put_contents($cfg['log_file'], $linha, FILE_APPEND);
    echo $linha;
}

// ---------------------------------------------------------------- banco
function prov_conectar(string $host, string $user, string $pass, ?string $db = null): mysqli {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $c = new mysqli($host, $user, $pass, $db ?? '');
    } catch (Throwable $e) {
        throw new ProvErro("Falha ao conectar no banco" . ($db ? " '{$db}'" : "") . " como '{$user}': " . $e->getMessage());
    }
    $c->set_charset('utf8mb4');
    return $c;
}

function prov_master(): mysqli {
    static $c = null;
    if ($c instanceof mysqli) { try { if (@$c->ping()) return $c; } catch (Throwable $e) {} }
    $env = prov_tenant_env(prov_config()['master_tenant']);
    $c = prov_conectar($env['DB_HOST'], $env['DB_USER'], $env['DB_PASSWORD'], $env['DB_NAME']);
    prov_garantir_fila($c);
    return $c;
}

function prov_garantir_fila(mysqli $c): void {
    $c->query("CREATE TABLE IF NOT EXISTS provisionamento (
        prov_nb_id INT(11) NOT NULL AUTO_INCREMENT,
        prov_tx_nome VARCHAR(255) NOT NULL,
        prov_tx_sigla VARCHAR(30) NOT NULL,
        prov_tx_pasta VARCHAR(40) NOT NULL,
        prov_tx_cnpj VARCHAR(25) NULL,
        prov_tx_email VARCHAR(255) NULL,
        prov_tx_adminNome VARCHAR(255) NOT NULL,
        prov_tx_adminLogin VARCHAR(50) NOT NULL,
        prov_tx_adminSenhaHash VARCHAR(64) NULL,
        prov_tx_status VARCHAR(20) NOT NULL DEFAULT 'pendente',
        prov_tx_etapa VARCHAR(60) NULL,
        prov_tx_log LONGTEXT NULL,
        prov_tx_criados TEXT NULL,
        prov_nb_userCadastro INT(11) NULL,
        prov_tx_dataCadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        prov_tx_dataInicio DATETIME NULL,
        prov_tx_dataFim DATETIME NULL,
        PRIMARY KEY (prov_nb_id),
        KEY idx_prov_status (prov_tx_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $novas = [
        'prov_tx_dados'         => "ALTER TABLE provisionamento ADD COLUMN prov_tx_dados LONGTEXT NULL",
        'prov_tx_logo'          => "ALTER TABLE provisionamento ADD COLUMN prov_tx_logo VARCHAR(255) NULL",
        'prov_nb_empresaMestre' => "ALTER TABLE provisionamento ADD COLUMN prov_nb_empresaMestre INT(11) NULL",
        'prov_nb_progresso'     => "ALTER TABLE provisionamento ADD COLUMN prov_nb_progresso INT(3) NOT NULL DEFAULT 0",
    ];
    foreach ($novas as $col => $ddl) {
        $r = $c->query("SHOW COLUMNS FROM provisionamento LIKE '{$col}'");
        if ($r && $r->num_rows === 0) $c->query($ddl);
    }
}

function prov_pedido(int $id): ?array {
    $st = prov_master()->prepare("SELECT * FROM provisionamento WHERE prov_nb_id = ?");
    $st->bind_param('i', $id); $st->execute();
    $r = $st->get_result()->fetch_assoc();
    return $r ?: null;
}

function prov_atualizar(int $id, array $campos): void {
    if (!$campos) return;
    $sets = []; $vals = []; $types = '';
    foreach ($campos as $k => $v) { $sets[] = "`{$k}` = ?"; $vals[] = $v; $types .= 's'; }
    $vals[] = $id; $types .= 'i';
    $st = prov_master()->prepare("UPDATE provisionamento SET " . implode(', ', $sets) . " WHERE prov_nb_id = ?");
    $st->bind_param($types, ...$vals); $st->execute();
}

function prov_etapa(int $id, string $etapa, string $msg): void {
    // Somente para testes: PROVISION_FAIL_AT=<etapa> simula uma falha ao entrar na etapa
    static $falhou = false;
    $falharEm = getenv('PROVISION_FAIL_AT');
    if ($falharEm && !$falhou && $falharEm === $etapa) { $falhou = true; throw new ProvErro("Falha simulada na etapa {$etapa} (teste)."); }
    prov_log("[pedido {$id}] {$etapa}: {$msg}");
    $st = prov_master()->prepare("UPDATE provisionamento SET prov_tx_etapa = ?, prov_tx_log = CONCAT(COALESCE(prov_tx_log,''), ?) WHERE prov_nb_id = ?");
    $linha = '[' . date('H:i:s') . "] {$etapa}: {$msg}\n";
    $st->bind_param('ssi', $etapa, $linha, $id); $st->execute();
    $pct = prov_pct_etapa($etapa);
    if ($pct !== null) prov_progresso($id, $pct);
}

/** Percentual em que cada etapa COMEÇA (a tela mostra de 0 a 100%). */
function prov_pct_etapa(string $etapa): ?int {
    $mapa = ['1-validacao' => 5, '2-banco' => 12, '3-estrutura' => 20, '4-cadastro' => 45, '5-arquivos' => 50,
             '6-env' => 93, '7-registro' => 97, '8-concluido' => 100];
    return $mapa[$etapa] ?? null;
}

/** Avança o percentual do pedido (nunca volta). */
function prov_progresso(int $id, int $pct): void {
    $pct = max(0, min(100, $pct));
    $st = prov_master()->prepare("UPDATE provisionamento SET prov_nb_progresso = GREATEST(COALESCE(prov_nb_progresso, 0), ?) WHERE prov_nb_id = ?");
    $st->bind_param('ii', $pct, $id); $st->execute();
}

function prov_criados(int $id): array {
    $p = prov_pedido($id);
    $j = json_decode((string)($p['prov_tx_criados'] ?? ''), true);
    return is_array($j) ? $j : [];
}

function prov_anotar_criado(int $id, string $chave, $valor): void {
    $c = prov_criados($id);
    $c[$chave] = $valor;
    prov_atualizar($id, ['prov_tx_criados' => json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
}

// ---------------------------------------------------------------- validação
function prov_empresas_existentes(): array {
    $cfg = prov_config();
    $empresas = []; $empresasNomes = [];
    $arq = PROV_ROOT . '/empresas.php';
    if (is_file($arq)) {
        // empresas.php só define arrays e monta o HTML do select; isolamos saída e variáveis
        $f = function () use ($arq) {
            $_POST = $_POST ?? []; $_GET = $_GET ?? [];
            if (!isset($_SERVER['HTTP_HOST'])) $_SERVER['HTTP_HOST'] = 'localhost';
            ob_start();
            include $arq;
            ob_end_clean();
            return [isset($empresas) && is_array($empresas) ? $empresas : [], isset($empresasNomes) && is_array($empresasNomes) ? $empresasNomes : []];
        };
        [$empresas, $empresasNomes] = $f();
    }
    return ['siglas' => array_map('strtoupper', array_keys($empresas)), 'pastas' => array_values($empresas)];
}

function prov_validar(array $p): void {
    $cfg = prov_config();
    $pasta = (string)$p['prov_tx_pasta'];
    $sigla = (string)$p['prov_tx_sigla'];
    if (!preg_match('/^[a-z][a-z0-9_]{2,39}$/', $pasta)) throw new ProvErro("Nome da pasta inválido: use de 3 a 40 caracteres, minúsculas, números e _ (começando por letra).");
    if (!preg_match('/^[A-Z0-9][A-Z0-9 _]{1,29}$/', $sigla)) throw new ProvErro("Sigla inválida: use de 2 a 30 caracteres, maiúsculas, números, espaço ou _.");
    $reservadas = ['contex20', 'phpmailer', 'node_modules', 'face_models', 'provisionador', 'api', 'versoes', 'arquivos', 'ws', 'assinatura'];
    if (in_array($pasta, $reservadas, true)) throw new ProvErro("O nome de pasta '{$pasta}' é reservado.");
    if (!preg_match('/^[A-Za-z0-9._@-]{3,50}$/', (string)$p['prov_tx_adminLogin'])) throw new ProvErro("Login do administrador inválido.");
    if (trim((string)$p['prov_tx_nome']) === '') throw new ProvErro("Informe o nome da empresa.");

    $ex = prov_empresas_existentes();
    if (in_array($pasta, $ex['pastas'], true)) throw new ProvErro("Já existe uma empresa usando a pasta '{$pasta}'.");
    if (in_array(strtoupper($sigla), $ex['siglas'], true)) throw new ProvErro("Já existe uma empresa com a sigla '{$sigla}'.");
    if (file_exists($cfg['target_root'] . '/' . $pasta)) throw new ProvErro("A pasta '{$pasta}' já existe no servidor.");
    if (!is_dir($cfg['source_dir'])) throw new ProvErro("Pasta de origem dos arquivos não encontrada: {$cfg['source_dir']}");
}

function prov_nomes_banco(string $pasta): array {
    $r = prov_restricoes_banco();
    $db = substr($r['prefix'] . $pasta, 0, $r['max_db']);
    $user = substr($r['prefix'] . $pasta, 0, $r['max_user']);
    return [$db, $user];
}

function prov_senha(int $len = 28): string {
    // Somente caracteres seguros para .env e linha de comando
    $alf = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $alf[random_int(0, strlen($alf) - 1)];
    return $s;
}

// ---------------------------------------------------------------- driver de banco
/**
 * Executa um programa sem passar pelo shell. Usa proc_open, porque exec/shell_exec/system
 * costumam estar bloqueados (disable_functions) em hospedagem compartilhada.
 * Devolve [código de saída, saída padrão, saída de erro].
 */
function prov_executar(array $cmd): array {
    if (function_exists('proc_open')) {
        $p = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($p)) {
            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            return [proc_close($p), (string)$out, (string)$err];
        }
    }
    $desativadas = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (function_exists('exec') && !in_array('exec', $desativadas, true)) {
        $linhas = []; $code = 0;
        exec(implode(' ', array_map('escapeshellarg', $cmd)) . ' 2>&1', $linhas, $code);
        return [$code, implode("\n", $linhas), ''];
    }
    throw new ProvErro("O PHP não permite executar programas (proc_open e exec bloqueados). Não é possível chamar a UAPI do cPanel.");
}

function prov_uapi(string $modulo, string $funcao, array $args = []): array {
    $cfg = prov_config();
    $cmd = [$cfg['uapi_bin'], '--output=json', $modulo, $funcao];
    foreach ($args as $k => $v) $cmd[] = $k . '=' . $v;
    [$code, $out, $err] = prov_executar($cmd);
    $json = json_decode($out, true);
    $ok = is_array($json) && !empty($json['result']['status']);
    if (!$ok) {
        $erros = is_array($json) ? implode('; ', (array)($json['result']['errors'] ?? [])) : trim(substr($out . ' ' . $err, 0, 300));
        throw new ProvErro("UAPI {$modulo}::{$funcao} falhou: " . ($erros !== '' ? $erros : "código {$code}"));
    }
    return $json;
}

/** Prefixo e tamanhos máximos de nome de banco/usuário. No cPanel vêm da própria UAPI. */
function prov_restricoes_banco(): array {
    static $r = null;
    if ($r !== null) return $r;
    $cfg = prov_config();
    $r = ['prefix' => $cfg['db_prefix'], 'max_db' => 64, 'max_user' => 32];
    if ($cfg['driver'] === 'uapi') {
        $d = prov_uapi('Mysql', 'get_restrictions')['result']['data'] ?? [];
        if ($cfg['db_prefix'] === '' && isset($d['prefix'])) $r['prefix'] = (string)$d['prefix'];
        if (!empty($d['max_database_name_length'])) $r['max_db'] = (int)$d['max_database_name_length'];
        if (!empty($d['max_username_length'])) $r['max_user'] = (int)$d['max_username_length'];
    }
    return $r;
}

function prov_admin_conn(): mysqli {
    $cfg = prov_config();
    if ($cfg['admin_user'] === '') throw new ProvErro("PROVISION_DB_ADMIN_USER não configurado (driver sql).");
    $host = $cfg['admin_host'] !== '' ? $cfg['admin_host'] : prov_tenant_env($cfg['template_tenant'])['DB_HOST'];
    return prov_conectar($host, $cfg['admin_user'], $cfg['admin_password']);
}

function prov_criar_banco(int $id, string $db, string $user, string $senha): void {
    $cfg = prov_config();
    if (!preg_match('/^[A-Za-z0-9_]+$/', $db) || !preg_match('/^[A-Za-z0-9_]+$/', $user)) throw new ProvErro("Nome de banco/usuário inválido.");

    if ($cfg['driver'] === 'uapi') {
        foreach ((prov_uapi('Mysql', 'list_databases')['result']['data'] ?? []) as $b) {
            if (($b['database'] ?? '') === $db) throw new ProvErro("O banco '{$db}' já existe.");
        }
        foreach ((prov_uapi('Mysql', 'list_users')['result']['data'] ?? []) as $u) {
            if (($u['user'] ?? '') === $user) throw new ProvErro("O usuário de banco '{$user}' já existe.");
        }
        prov_uapi('Mysql', 'create_database', ['name' => $db]);
        prov_anotar_criado($id, 'banco', $db);
        prov_uapi('Mysql', 'create_user', ['name' => $user, 'password' => $senha]);
        prov_anotar_criado($id, 'usuario_banco', $user);
        prov_uapi('Mysql', 'set_privileges_on_database', ['user' => $user, 'database' => $db, 'privileges' => 'ALL PRIVILEGES']);
        return;
    }

    $a = prov_admin_conn();
    $r = $a->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '" . $a->real_escape_string($db) . "'");
    if ($r && $r->num_rows > 0) throw new ProvErro("O banco '{$db}' já existe.");
    $tpl = prov_tenant_env($cfg['template_tenant']);
    $cs = $a->query("SELECT DEFAULT_CHARACTER_SET_NAME cs, DEFAULT_COLLATION_NAME co FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '" . $a->real_escape_string($tpl['DB_NAME']) . "'")->fetch_assoc();
    $charset = preg_match('/^[a-z0-9_]+$/i', (string)($cs['cs'] ?? '')) ? $cs['cs'] : 'utf8mb4';
    $collate = preg_match('/^[a-z0-9_]+$/i', (string)($cs['co'] ?? '')) ? $cs['co'] : 'utf8mb4_general_ci';
    $a->query("CREATE DATABASE `{$db}` CHARACTER SET {$charset} COLLATE {$collate}");
    prov_anotar_criado($id, 'banco', $db);
    $senhaEsc = $a->real_escape_string($senha);
    $a->query("CREATE USER '{$user}'@'%' IDENTIFIED BY '{$senhaEsc}'");
    prov_anotar_criado($id, 'usuario_banco', $user);
    $a->query("GRANT ALL PRIVILEGES ON `{$db}`.* TO '{$user}'@'%'");
    $a->query("FLUSH PRIVILEGES");
}

function prov_remover_banco(string $db, string $user): array {
    $cfg = prov_config();
    $feito = [];
    $protegidos = [];
    foreach ([$cfg['template_tenant'], $cfg['master_tenant']] as $t) {
        try { $protegidos[] = prov_tenant_env($t)['DB_NAME']; } catch (Throwable $e) {}
    }
    if ($db !== '' && in_array($db, $protegidos, true)) throw new ProvErro("Recusado: '{$db}' é o banco modelo/mestre.");

    if ($cfg['driver'] === 'uapi') {
        if ($db !== '')   { try { prov_uapi('Mysql', 'delete_database', ['name' => $db]); $feito[] = "banco {$db}"; } catch (Throwable $e) { $feito[] = "banco {$db} (falhou: {$e->getMessage()})"; } }
        if ($user !== '') { try { prov_uapi('Mysql', 'delete_user', ['name' => $user]); $feito[] = "usuário {$user}"; } catch (Throwable $e) { $feito[] = "usuário {$user} (falhou: {$e->getMessage()})"; } }
        return $feito;
    }
    $a = prov_admin_conn();
    if ($db !== '' && preg_match('/^[A-Za-z0-9_]+$/', $db))     { $a->query("DROP DATABASE IF EXISTS `{$db}`"); $feito[] = "banco {$db}"; }
    if ($user !== '' && preg_match('/^[A-Za-z0-9_]+$/', $user)) { $a->query("DROP USER IF EXISTS '{$user}'@'%'"); $feito[] = "usuário {$user}"; }
    return $feito;
}

// ---------------------------------------------------------------- estrutura e dados de referência
function prov_importar_estrutura(int $id, mysqli $tpl, mysqli $novo): array {
    $cfg = prov_config();
    $novo->query("SET FOREIGN_KEY_CHECKS = 0");
    $novo->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
    $tabelas = [];
    $r = $tpl->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($row = $r->fetch_row()) $tabelas[] = $row[0];

    foreach ($tabelas as $t) {
        if ($t === 'provisionamento') continue; // fila do mestre não vai para a empresa
        $ddl = $tpl->query("SHOW CREATE TABLE `{$t}`")->fetch_row()[1];
        $ddl = preg_replace('/\sAUTO_INCREMENT=\d+/', '', $ddl);
        $novo->query($ddl);
    }

    $copiadas = [];
    foreach ($cfg['reference_tables'] as $t) {
        if (!in_array($t, $tabelas, true)) continue;
        $rs = $tpl->query("SELECT * FROM `{$t}`", MYSQLI_USE_RESULT);
        $lote = []; $cols = null; $total = 0;
        $flush = function () use (&$lote, &$cols, $novo, $t) {
            if (!$lote) return;
            $novo->query("INSERT INTO `{$t}` (`" . implode('`,`', $cols) . "`) VALUES " . implode(',', $lote));
            $lote = [];
        };
        while ($row = $rs->fetch_assoc()) {
            if ($cols === null) $cols = array_keys($row);
            $vals = [];
            foreach ($row as $v) $vals[] = $v === null ? 'NULL' : "'" . $novo->real_escape_string((string)$v) . "'";
            $lote[] = '(' . implode(',', $vals) . ')';
            $total++;
            if (count($lote) >= 200) $flush();
        }
        $rs->free();
        $flush();
        $copiadas[$t] = $total;
    }
    $novo->query("SET FOREIGN_KEY_CHECKS = 1");
    return ['tabelas' => count($tabelas), 'referencia' => $copiadas];
}

function prov_colunas(mysqli $c, string $tabela): array {
    $cols = [];
    $r = $c->query("SHOW COLUMNS FROM `{$tabela}`");
    while ($row = $r->fetch_assoc()) $cols[$row['Field']] = $row;
    return $cols;
}

function prov_inserir(mysqli $c, string $tabela, array $dados): int {
    $cols = prov_colunas($c, $tabela);
    $dados = array_intersect_key($dados, $cols); // só colunas que existem nesta versão do banco
    $nomes = array_keys($dados);
    $marc = implode(',', array_fill(0, count($nomes), '?'));
    $st = $c->prepare("INSERT INTO `{$tabela}` (`" . implode('`,`', $nomes) . "`) VALUES ({$marc})");
    $vals = array_values($dados);
    $st->bind_param(str_repeat('s', count($vals)), ...$vals);
    $st->execute();
    return (int)$c->insert_id;
}

/** Monta as colunas empr_* a partir dos dados do pedido (mesmos campos do Cadastro de Empresa). */
function prov_dados_empresa(array $p): array {
    $d = json_decode((string)($p['prov_tx_dados'] ?? ''), true);
    if (!is_array($d)) $d = [];
    $campos = ['nome', 'fantasia', 'cnpj', 'cep', 'endereco', 'numero', 'bairro', 'complemento', 'referencia', 'fone1', 'fone2',
               'contato', 'email', 'inscricaoEstadual', 'inscricaoMunicipal', 'regimeTributario', 'dataRegistroCNPJ', 'tipoAssinatura'];
    $base = [];
    foreach ($campos as $c) {
        if (isset($d[$c]) && trim((string)$d[$c]) !== '') $base['empr_tx_' . $c] = trim((string)$d[$c]);
    }
    // Pedidos criados pela linha de comando trazem só o básico
    if (empty($base['empr_tx_nome']))  $base['empr_tx_nome'] = (string)$p['prov_tx_nome'];
    if (empty($base['empr_tx_fantasia'])) $base['empr_tx_fantasia'] = $base['empr_tx_nome'];
    if (empty($base['empr_tx_cnpj']) && !empty($p['prov_tx_cnpj'])) $base['empr_tx_cnpj'] = (string)$p['prov_tx_cnpj'];
    if (empty($base['empr_tx_email']) && !empty($p['prov_tx_email'])) $base['empr_tx_email'] = (string)$p['prov_tx_email'];
    if (!empty($d['cidade'])) $base['empr_nb_cidade'] = (string)intval($d['cidade']); // código IBGE, igual em todos os bancos
    if (isset($base['empr_tx_tipoAssinatura']) && !in_array($base['empr_tx_tipoAssinatura'], ['cpf_rg', 'rubrica', 'ambos'], true)) unset($base['empr_tx_tipoAssinatura']);
    return [$base, $d];
}

function prov_garantir_coluna_tipo_assinatura(mysqli $c): void {
    $r = $c->query("SHOW COLUMNS FROM empresa LIKE 'empr_tx_tipoAssinatura'");
    if ($r && $r->num_rows === 0) {
        $c->query("ALTER TABLE empresa ADD COLUMN empr_tx_tipoAssinatura ENUM('cpf_rg','rubrica','ambos') NOT NULL DEFAULT 'cpf_rg'");
    }
}

function prov_semear(int $id, array $p, mysqli $tpl, mysqli $novo, string $urlDominio): array {
    $cfg = prov_config();
    [$base, $d] = prov_dados_empresa($p);
    $agora = date('Y-m-d H:i:s');

    // Parâmetro de jornada na empresa nova: o mesmo nome escolhido na tela; senão, o primeiro do cadastro
    $paramId = null;
    $pk = $novo->query("SHOW KEYS FROM `parametro` WHERE Key_name = 'PRIMARY'")->fetch_assoc();
    if ($pk) {
        $col = $pk['Column_name'];
        if (!empty($d['parametroNome'])) {
            $st = $novo->prepare("SELECT `{$col}` id FROM `parametro` WHERE para_tx_nome = ? LIMIT 1");
            $nomePar = (string)$d['parametroNome'];
            $st->bind_param('s', $nomePar); $st->execute();
            $r = $st->get_result()->fetch_assoc();
            if ($r) $paramId = (int)$r['id'];
        }
        if ($paramId === null) {
            $r = $novo->query("SELECT MIN(`{$col}`) m FROM `parametro`")->fetch_assoc();
            $paramId = $r && $r['m'] !== null ? (int)$r['m'] : null;
        }
    }

    // 1) Empresa matriz no banco da empresa nova
    prov_garantir_coluna_tipo_assinatura($novo);
    $filtro = function ($v) { return $v !== null && $v !== ''; };
    $empresaId = prov_inserir($novo, 'empresa', array_filter(array_merge($base, [
        'empr_tx_status'       => 'ativo',
        'empr_tx_Ehmatriz'     => 'sim',
        'empr_nb_parametro'    => $paramId,
        'empr_tx_domain'       => substr($urlDominio, 0, 100),
        'empr_tx_dataCadastro' => $agora,
    ]), $filtro));

    // 2) Registro no cadastro de empresas do domínio mestre (auditoria)
    $mestre = prov_master();
    prov_garantir_coluna_tipo_assinatura($mestre);
    $mestreId = prov_inserir($mestre, 'empresa', array_filter(array_merge($base, [
        'empr_tx_status'       => 'ativo',
        'empr_tx_Ehmatriz'     => 'nao',
        'empr_nb_parametro'    => !empty($d['parametro']) ? (string)intval($d['parametro']) : null,
        'empr_tx_domain'       => substr($urlDominio, 0, 100),
        'empr_nb_userCadastro' => !empty($p['prov_nb_userCadastro']) ? (string)intval($p['prov_nb_userCadastro']) : null,
        'empr_tx_dataCadastro' => $agora,
    ]), $filtro));
    prov_anotar_criado($id, 'empresa_mestre', $mestreId);
    prov_atualizar($id, ['prov_nb_empresaMestre' => (string)$mestreId]);

    // 3) Administrador inicial
    $adminId = prov_inserir($novo, 'user', array_filter([
        'user_tx_nome'         => $p['prov_tx_adminNome'],
        'user_tx_login'        => $p['prov_tx_adminLogin'],
        'user_tx_senha'        => $p['prov_tx_adminSenhaHash'],
        'user_tx_nivel'        => 'Administrador',
        'user_tx_status'       => 'ativo',
        'user_tx_email'        => $base['empr_tx_email'] ?? null,
        'user_nb_empresa'      => $empresaId,
        'user_tx_dataCadastro' => $agora,
    ], $filtro));

    $supers = 0;
    if ($cfg['copy_super_admins']) {
        $pkUser = $tpl->query("SHOW KEYS FROM `user` WHERE Key_name = 'PRIMARY'")->fetch_assoc()['Column_name'] ?? 'user_nb_id';
        $rs = $tpl->query("SELECT * FROM `user` WHERE user_tx_status = 'ativo' AND user_tx_nivel = 'Super Administrador'");
        while ($u = $rs->fetch_assoc()) {
            if ($u['user_tx_login'] === $p['prov_tx_adminLogin']) continue;
            unset($u[$pkUser]);
            $u['user_nb_empresa'] = $empresaId;
            if (array_key_exists('user_nb_entidade', $u)) $u['user_nb_entidade'] = null;
            if (array_key_exists('user_tx_face_descriptor', $u)) $u['user_tx_face_descriptor'] = null;
            if (array_key_exists('user_tx_foto', $u)) $u['user_tx_foto'] = null;
            $u = array_filter($u, function ($v) { return $v !== null; });
            prov_inserir($novo, 'user', $u);
            $supers++;
        }
    }
    return ['empresa_id' => $empresaId, 'empresa_mestre_id' => $mestreId, 'admin_id' => $adminId, 'super_admins' => $supers];
}

/** Copia a logo enviada na tela para a empresa nova e para o registro do domínio mestre. */
function prov_aplicar_logo(int $id, array $p, array $seed, mysqli $novo): string {
    $cfg = prov_config();
    $rel = trim((string)($p['prov_tx_logo'] ?? ''));
    if ($rel === '' || strpos($rel, '..') !== false) return 'sem logo';
    $origem = $cfg['target_root'] . '/' . $cfg['master_tenant'] . '/' . ltrim($rel, '/');
    if (!is_file($origem)) return 'logo não encontrada';
    $ext = strtolower(pathinfo($origem, PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true)) return 'logo ignorada (extensão)';

    // Empresa nova
    $relNovo = "arquivos/empresa/{$seed['empresa_id']}/logo.{$ext}";
    $dirNovo = $cfg['target_root'] . '/' . $p['prov_tx_pasta'] . '/' . dirname($relNovo);
    if (!is_dir($dirNovo)) @mkdir($dirNovo, 0775, true);
    if (@copy($origem, $cfg['target_root'] . '/' . $p['prov_tx_pasta'] . '/' . $relNovo)) {
        $st = $novo->prepare("UPDATE empresa SET empr_tx_logo = ? WHERE empr_nb_id = ?");
        $eid = (int)$seed['empresa_id'];
        $st->bind_param('si', $relNovo, $eid); $st->execute();
    }
    // Registro no domínio mestre
    $relMestre = "arquivos/empresa/{$seed['empresa_mestre_id']}/logo.{$ext}";
    $dirMestre = $cfg['target_root'] . '/' . $cfg['master_tenant'] . '/' . dirname($relMestre);
    if (!is_dir($dirMestre)) @mkdir($dirMestre, 0775, true);
    if (@copy($origem, $cfg['target_root'] . '/' . $cfg['master_tenant'] . '/' . $relMestre)) {
        $st = prov_master()->prepare("UPDATE empresa SET empr_tx_logo = ? WHERE empr_nb_id = ?");
        $mid = (int)$seed['empresa_mestre_id'];
        $st->bind_param('si', $relMestre, $mid); $st->execute();
        prov_anotar_criado($id, 'logo_mestre', $relMestre);
    }
    return 'logo aplicada';
}

// ---------------------------------------------------------------- arquivos
/**
 * Lista um diretório com scandir(). Os iteradores do SPL (DirectoryIterator etc.) devolvem listagem
 * incompleta em alguns volumes montados (Docker Desktop), por isso não são usados aqui.
 */
function prov_listar(string $dir): array {
    $itens = @scandir($dir);
    if ($itens === false) throw new ProvErro("Não consegui ler a pasta {$dir}.");
    return array_values(array_filter($itens, function ($f) { return $f !== '.' && $f !== '..'; }));
}

function prov_copiar_arquivos(string $origem, string $destino, array $excluir, ?callable $aoCopiar = null): int {
    $origem = rtrim(str_replace(chr(92), '/', $origem), '/');
    $destino = rtrim(str_replace(chr(92), '/', $destino), '/');
    $excluir = array_map(function ($e) { return trim(str_replace(chr(92), '/', $e), '/'); }, $excluir);
    if (!is_dir($destino) && !@mkdir($destino, 0755, true) && !is_dir($destino)) throw new ProvErro("Não consegui criar a pasta {$destino}.");

    $n = 0;
    $copiar = function (string $rel) use (&$copiar, &$n, $origem, $destino, $excluir, $aoCopiar) {
        foreach (prov_listar($origem . ($rel === '' ? '' : '/' . $rel)) as $nome) {
            $r = $rel === '' ? $nome : $rel . '/' . $nome;
            foreach ($excluir as $e) { if ($r === $e || strpos($r, $e . '/') === 0) continue 2; }
            $de = $origem . '/' . $r; $para = $destino . '/' . $r;
            if (is_link($de)) continue;
            if (is_dir($de)) {
                if (!is_dir($para) && !@mkdir($para, 0755, true) && !is_dir($para)) throw new ProvErro("Não consegui criar {$para}.");
                $copiar($r);
            } else {
                if (!@copy($de, $para)) throw new ProvErro("Falha ao copiar {$r}.");
                $n++;
                if ($aoCopiar && $n % 25 === 0) $aoCopiar($n);
            }
        }
    };
    $copiar('');
    return $n;
}

/** Quantos arquivos a origem tem (respeitando as exclusões) — usado para conferir a cópia. */
function prov_contar_arquivos(string $dir, array $excluir = [], string $rel = ''): int {
    $n = 0;
    foreach (prov_listar($dir . ($rel === '' ? '' : '/' . $rel)) as $nome) {
        $r = $rel === '' ? $nome : $rel . '/' . $nome;
        foreach ($excluir as $e) { $e = trim($e, '/'); if ($r === $e || strpos($r, $e . '/') === 0) continue 2; }
        $p = $dir . '/' . $r;
        if (is_link($p)) continue;
        $n += is_dir($p) ? prov_contar_arquivos($dir, $excluir, $r) : 1;
    }
    return $n;
}

function prov_remover_pasta(string $dir): void {
    $apagar = function (string $d) use (&$apagar) {
        foreach (prov_listar($d) as $nome) {
            $p = $d . '/' . $nome;
            if (is_dir($p) && !is_link($p)) { $apagar($p); @rmdir($p); } else { @unlink($p); }
        }
    };
    for ($tentativa = 0; $tentativa < 3 && is_dir($dir); $tentativa++) {
        $apagar($dir);
        @rmdir($dir);
        clearstatcache();
    }
    if (is_dir($dir)) throw new ProvErro("Não consegui remover toda a pasta {$dir} (arquivos em uso ou sem permissão).");
}

function prov_gravar_env(string $pasta, array $novos): void {
    $cfg = prov_config();
    $modelo = $cfg['target_root'] . '/' . $cfg['template_tenant'] . '/.env';
    $linhas = is_file($modelo) ? file($modelo, FILE_IGNORE_NEW_LINES) : [];
    $usadas = [];
    foreach ($linhas as $i => $l) {
        if (preg_match('/^\s*([A-Za-z0-9_]+)\s*=/', $l, $m) && array_key_exists($m[1], $novos)) {
            $linhas[$i] = $m[1] . " = '" . $novos[$m[1]] . "'";
            $usadas[$m[1]] = true;
        }
    }
    foreach ($novos as $k => $v) if (empty($usadas[$k])) $linhas[] = $k . " = '" . $v . "'";
    $arq = $cfg['target_root'] . '/' . $pasta . '/.env';
    if (@file_put_contents($arq, implode("\n", $linhas) . "\n") === false) throw new ProvErro("Não consegui gravar o .env da empresa.");
    @chmod($arq, 0640);
}

// ---------------------------------------------------------------- registro (login)
function prov_registro_ler(): array {
    $arq = prov_config()['registry_file'];
    if (!is_file($arq)) return [];
    $j = json_decode((string)file_get_contents($arq), true);
    return is_array($j) ? $j : [];
}

function prov_registro_gravar(array $lista): void {
    $arq = prov_config()['registry_file'];
    $tmp = $arq . '.tmp';
    if (@file_put_contents($tmp, json_encode(array_values($lista), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
        throw new ProvErro("Não consegui gravar o registro de empresas ({$arq}).");
    }
    @rename($tmp, $arq);
}

/**
 * Ambiente de desenvolvimento: quando a raiz das empresas é um repositório git, a pasta da empresa
 * criada entra na lista local de ignorados (.git/info/exclude), para não aparecer como arquivo novo.
 */
function prov_git_excluir(string $pasta, bool $incluir): void {
    $arq = prov_config()['target_root'] . '/.git/info/exclude';
    if (!is_file($arq) || !preg_match('/^[a-z][a-z0-9_]{2,39}$/', $pasta)) return;
    $linha = '/' . $pasta . '/';
    $linhas = array_values(array_filter(array_map('rtrim', (array)@file($arq)), function ($l) use ($linha) { return $l !== $linha; }));
    if ($incluir) $linhas[] = $linha;
    @file_put_contents($arq, implode("
", $linhas) . "
");
}

// ---------------------------------------------------------------- fluxo principal
function prov_processar_pedido(int $id): bool {
    $cfg = prov_config();
    $p = prov_pedido($id);
    if (!$p) throw new ProvErro("Pedido {$id} não encontrado.");
    if ($p['prov_tx_status'] !== 'pendente') { prov_log("[pedido {$id}] ignorado: status '{$p['prov_tx_status']}'."); return false; }

    prov_atualizar($id, ['prov_tx_status' => 'processando', 'prov_tx_dataInicio' => date('Y-m-d H:i:s'), 'prov_nb_progresso' => '1']);
    try {
        prov_etapa($id, '1-validacao', "pasta '{$p['prov_tx_pasta']}', sigla '{$p['prov_tx_sigla']}'");
        prov_validar($p);

        [$db, $dbUser] = prov_nomes_banco($p['prov_tx_pasta']);
        $senha = prov_senha();
        prov_etapa($id, '2-banco', "criando banco '{$db}' e usuário '{$dbUser}' (driver {$cfg['driver']})");
        prov_criar_banco($id, $db, $dbUser, $senha);

        $tplEnv = prov_tenant_env($cfg['template_tenant']);
        $tpl = prov_conectar($tplEnv['DB_HOST'], $tplEnv['DB_USER'], $tplEnv['DB_PASSWORD'], $tplEnv['DB_NAME']);
        $novo = prov_conectar($tplEnv['DB_HOST'], $dbUser, $senha, $db);

        prov_etapa($id, '3-estrutura', "importando estrutura do modelo '{$tplEnv['DB_NAME']}'");
        $imp = prov_importar_estrutura($id, $tpl, $novo);
        $ref = [];
        foreach ($imp['referencia'] as $t => $n) $ref[] = "{$t}={$n}";
        prov_etapa($id, '3-estrutura', "{$imp['tabelas']} tabelas; dados de referência: " . implode(', ', $ref));
        prov_progresso($id, 40);

        $appPath = rtrim((string)($tplEnv['APP_PATH'] ?? ''), '/');
        $urlBase = rtrim((string)(prov_ler_env(PROV_ROOT . '/.env')['URL_BASE'] ?? ($tplEnv['URL_BASE'] ?? '')), '/');
        prov_etapa($id, '4-cadastro', "empresa matriz, administrador '{$p['prov_tx_adminLogin']}' e registro no domínio mestre");
        $seed = prov_semear($id, $p, $tpl, $novo, $urlBase . $appPath . '/' . $p['prov_tx_pasta']);
        prov_etapa($id, '4-cadastro', "empresa #{$seed['empresa_id']} no banco novo; empresa #{$seed['empresa_mestre_id']} no cadastro do domínio mestre; admin #{$seed['admin_id']}; super administradores copiados: {$seed['super_admins']}");

        $destino = $cfg['target_root'] . '/' . $p['prov_tx_pasta'];
        prov_etapa($id, '5-arquivos', "preparando a cópia dos arquivos do sistema");
        prov_anotar_criado($id, 'pasta', $p['prov_tx_pasta']);
        $totalArquivos = max(1, prov_contar_arquivos($cfg['source_dir'], $cfg['exclude_paths']));
        $n = prov_copiar_arquivos($cfg['source_dir'], $destino, $cfg['exclude_paths'], function (int $feitos) use ($id, $totalArquivos) {
            prov_progresso($id, 50 + (int)floor(40 * min(1, $feitos / $totalArquivos)));
        });
        prov_progresso($id, 90);
        prov_etapa($id, '5-arquivos', "conferindo a cópia ({$n} arquivos)");
        foreach ($cfg['empty_dirs'] as $d) { if (!is_dir($destino . '/' . $d)) @mkdir($destino . '/' . $d, 0775, true); }
        @file_put_contents($destino . '/.provisionado', json_encode(['pedido' => $id, 'pasta' => $p['prov_tx_pasta'], 'data' => date('c')]));
        // ws/app precisa do encaminhador de rota
        if (is_file($cfg['source_dir'] . '/ws/app/index.php')) @copy($cfg['source_dir'] . '/ws/app/index.php', $destino . '/ws/app/index.php');
        $esperado = prov_contar_arquivos($cfg['source_dir'], $cfg['exclude_paths']);
        $copiados = prov_contar_arquivos($destino, array_merge($cfg['exclude_paths'], ['.provisionado']));
        if ($copiados < $esperado) throw new ProvErro("Cópia incompleta: {$copiados} de {$esperado} arquivos.");
        prov_etapa($id, '5-arquivos', "{$n} arquivos copiados (conferido: {$copiados} de {$esperado})");

        $resLogo = prov_aplicar_logo($id, prov_pedido($id), $seed, $novo);
        if ($resLogo !== 'sem logo') prov_etapa($id, '5-arquivos', $resLogo);

        prov_etapa($id, '6-env', "gravando .env");
        prov_gravar_env($p['prov_tx_pasta'], [
            'CONTEX_PATH' => '/' . $p['prov_tx_pasta'],
            'DB_HOST'     => $tplEnv['DB_HOST'],
            'DB_USER'     => $dbUser,
            'DB_PASSWORD' => $senha,
            'DB_NAME'     => $db,
            'APP_KEY'     => prov_senha(96),
            // A tela de criação de empresas só existe no domínio mestre
            'PROVISIONAMENTO_HABILITADO' => '0',
        ]);

        prov_etapa($id, '7-registro', "incluindo no login");
        $reg = prov_registro_ler();
        $reg[] = ['sigla' => strtoupper($p['prov_tx_sigla']), 'pasta' => $p['prov_tx_pasta'], 'nome' => $p['prov_tx_nome'], 'pedido' => $id, 'criado_em' => date('c')];
        prov_registro_gravar($reg);
        prov_anotar_criado($id, 'registro', true);
        prov_git_excluir($p['prov_tx_pasta'], true);

        prov_atualizar($id, ['prov_tx_status' => 'concluido', 'prov_tx_dataFim' => date('Y-m-d H:i:s'), 'prov_tx_adminSenhaHash' => null]);
        prov_etapa($id, '8-concluido', "empresa '{$p['prov_tx_nome']}' pronta em /{$p['prov_tx_pasta']}");
        return true;
    } catch (Throwable $e) {
        prov_etapa($id, 'ERRO', $e->getMessage());
        try {
            $desf = prov_desfazer_criados($id, 'falha');
            prov_etapa($id, 'ERRO', "desfeito: " . ($desf ? implode(', ', $desf) : 'nada a desfazer'));
        } catch (Throwable $e2) {
            prov_etapa($id, 'ERRO', "falha ao desfazer: " . $e2->getMessage());
        }
        prov_atualizar($id, ['prov_tx_status' => 'erro', 'prov_tx_dataFim' => date('Y-m-d H:i:s')]);
        return false;
    }
}

/**
 * Remove somente o que este pedido criou (anotado em prov_tx_criados).
 * $modo = 'falha'  : a criação não terminou; o registro no domínio mestre é apagado (a empresa nunca existiu).
 * $modo = 'manual' : empresa criada e depois removida; o registro no domínio mestre fica INATIVO, para auditoria.
 */
function prov_desfazer_criados(int $id, string $modo = 'manual'): array {
    $cfg = prov_config();
    $c = prov_criados($id);
    $feito = [];
    $restantes = []; // o que não foi possível remover continua anotado, para repetir o desfazer

    if (!empty($c['empresa_mestre'])) {
        $mid = (int)$c['empresa_mestre'];
        try {
            $m = prov_master();
            if ($modo === 'falha') {
                $st = $m->prepare("DELETE FROM empresa WHERE empr_nb_id = ? AND (empr_tx_Ehmatriz IS NULL OR empr_tx_Ehmatriz <> 'sim')");
                $st->bind_param('i', $mid); $st->execute();
                if (!empty($c['logo_mestre']) && strpos((string)$c['logo_mestre'], '..') === false) {
                    $arq = $cfg['target_root'] . '/' . $cfg['master_tenant'] . '/' . ltrim((string)$c['logo_mestre'], '/');
                    if (is_file($arq)) { @unlink($arq); @rmdir(dirname($arq)); }
                }
                prov_atualizar($id, ['prov_nb_empresaMestre' => null]);
                $feito[] = "registro #{$mid} no domínio mestre (apagado)";
            } else {
                $st = $m->prepare("UPDATE empresa SET empr_tx_status = 'inativo' WHERE empr_nb_id = ? AND (empr_tx_Ehmatriz IS NULL OR empr_tx_Ehmatriz <> 'sim')");
                $st->bind_param('i', $mid); $st->execute();
                $feito[] = "registro #{$mid} no domínio mestre (inativado, mantido para auditoria)";
            }
        } catch (Throwable $e) {
            $feito[] = "registro #{$mid} no domínio mestre (falhou: {$e->getMessage()})";
            $restantes['empresa_mestre'] = $mid;
        }
    }
    // Logo temporária enviada na tela
    $tmpLogo = $cfg['target_root'] . '/' . $cfg['master_tenant'] . '/arquivos/provisionamento/' . $id;
    if (is_dir($tmpLogo)) { try { prov_remover_pasta($tmpLogo); } catch (Throwable $e) {} }

    if (!empty($c['registro'])) {
        $reg = array_values(array_filter(prov_registro_ler(), function ($e) use ($id) { return intval($e['pedido'] ?? 0) !== $id; }));
        prov_registro_gravar($reg);
        $feito[] = 'registro no login';
    }
    if (!empty($c['pasta'])) {
        $pasta = (string)$c['pasta'];
        $dir = $cfg['target_root'] . '/' . $pasta;
        if (preg_match('/^[a-z][a-z0-9_]{2,39}$/', $pasta) && is_dir($dir)) {
            $marca = json_decode((string)@file_get_contents($dir . '/.provisionado'), true);
            $protegidas = [$cfg['template_tenant'], $cfg['master_tenant']];
            // Só apaga pasta criada por ESTE pedido (marcador) ou pasta incompleta sem .env
            $ehDestePedido = is_array($marca) && intval($marca['pedido'] ?? 0) === $id;
            $incompleta = !is_file($dir . '/.env') && !is_file($dir . '/.provisionado');
            if (!in_array($pasta, $protegidas, true) && ($ehDestePedido || $incompleta)) {
                try {
                    prov_remover_pasta($dir);
                    prov_git_excluir($pasta, false);
                    $feito[] = "pasta /{$pasta}";
                } catch (Throwable $e) {
                    $feito[] = "pasta /{$pasta} (remoção incompleta: repita o desfazer)";
                    $restantes['pasta'] = $pasta;
                }
            } else {
                $feito[] = "pasta /{$pasta} mantida (não pertence a este pedido)";
            }
        }
    }
    if (!empty($c['banco']) || !empty($c['usuario_banco'])) {
        $feito = array_merge($feito, prov_remover_banco((string)($c['banco'] ?? ''), (string)($c['usuario_banco'] ?? '')));
    }
    prov_atualizar($id, ['prov_tx_criados' => json_encode($restantes)]);
    return $feito;
}
