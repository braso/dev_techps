<?php
// Modo debug
ini_set("display_errors", 1);
error_reporting(E_ALL);

if(empty(session_id())){
    $lifetime = 30*60;
    ini_set('session.gc_maxlifetime', $lifetime);
}
if(empty(session_id())){
    session_start();
}

// 1. Carregar variáveis de ambiente.
$envLoaded = false;
$envFiles = [
    __DIR__.'/.env',
    __DIR__.'/../.env',
    __DIR__.'/../armazem_paraiba/.env'
];

foreach ($envFiles as $envFilePath) {
    if (file_exists($envFilePath)) {
        $env = parse_ini_file($envFilePath);
        if ($env) {
            foreach ($env as $attr => $val) {
                $v = is_string($val) ? trim($val) : $val;
                if (is_string($v) && strlen($v) >= 2) {
                    $first = $v[0];
                    $last = $v[strlen($v)-1];
                    if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
                        $v = substr($v, 1, -1);
                    }
                }
                putenv("{$attr}={$v}");
                $_ENV[$attr] = $v;
            }
            $envLoaded = true;
        }
    }
}

if (!isset($_ENV["APP_PATH"])) {
    $_ENV["APP_PATH"] = "/braso";
}
if (!isset($_ENV["URL_BASE"])) {
    $_ENV["URL_BASE"] = ($_SERVER["REQUEST_SCHEME"] ?? "http") . "://" . ($_SERVER["HTTP_HOST"] ?? "localhost");
}

global $_SESSION, $CONTEX, $conn;
date_default_timezone_set('America/Fortaleza');

$CONTEX['path'] = $_ENV["APP_PATH"] . ($_ENV["CONTEX_PATH"] ?? "/armazem_paraiba");

$_SESSION['last_activity'] = time();

$db_host = $_ENV["DB_HOST"] ?? "localhost";
$db_user = $_ENV["DB_USER"] ?? "root";
$db_pass = $_ENV["DB_PASSWORD"] ?? "";
$db_name = $_ENV["DB_NAME"] ?? "techpsjornada_dev";

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name) 
    or die("Erro na conexão com o banco de dados: " . mysqli_connect_error());
$conn->set_charset("utf8");

// --- MIGRATION AUTOMÁTICA DAS TABELAS DO MÓDULO (ss_...) ---

// 1. Tabela ss_colaborador (Colaboradores - independente)
// Usando prefixo ss_c para corresponder ao substr(tabela, 0, 4)
$sqlColaborador = "CREATE TABLE IF NOT EXISTS ss_colaborador (
    ss_c_nb_id INT AUTO_INCREMENT PRIMARY KEY,
    ss_c_tx_nome VARCHAR(100) NOT NULL,
    ss_c_tx_matricula VARCHAR(50) NULL,
    ss_c_tx_cpf VARCHAR(20) NULL,
    ss_c_tx_cargo VARCHAR(100) NULL,
    ss_c_tx_status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    ss_c_nb_userCadastro INT NULL,
    ss_c_tx_dataCadastro VARCHAR(30) NULL,
    ss_c_nb_userAtualiza INT NULL,
    ss_c_tx_dataAtualiza VARCHAR(30) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
mysqli_query($conn, $sqlColaborador) or die("Erro ao criar tabela ss_colaborador: " . mysqli_error($conn));

// 2. Tabela ss_epi (EPIs)
// Usando prefixo ss_e para corresponder ao substr(tabela, 0, 4)
$sqlEpi = "CREATE TABLE IF NOT EXISTS ss_epi (
    ss_e_nb_id INT AUTO_INCREMENT PRIMARY KEY,
    ss_e_tx_grupo VARCHAR(255) NULL,
    ss_e_tx_subgrupo VARCHAR(255) NULL,
    ss_e_tx_item VARCHAR(255) NULL,
    ss_e_tx_descricao TEXT NULL,
    ss_e_tx_fabricante VARCHAR(100) NULL,
    ss_e_tx_ca VARCHAR(50) NULL,
    ss_e_tx_validade_ca DATE NULL,
    ss_e_nb_vida_util INT NOT NULL DEFAULT 0,
    ss_e_tx_status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    ss_e_tx_cadastro_tipo ENUM('universal', 'estoque') NOT NULL DEFAULT 'estoque',
    ss_e_tx_foto VARCHAR(255) NULL,
    ss_e_tx_modelo VARCHAR(255) NULL,
    ss_e_nb_userCadastro INT NULL,
    ss_e_tx_dataCadastro VARCHAR(30) NULL,
    ss_e_nb_userAtualiza INT NULL,
    ss_e_tx_dataAtualiza VARCHAR(30) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
mysqli_query($conn, $sqlEpi) or die("Erro ao criar tabela ss_epi: " . mysqli_error($conn));

// Migração segura para quem já tinha a tabela antiga criada
$checkGrupo = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi LIKE 'ss_e_tx_grupo'");
if ($checkGrupo && mysqli_num_rows($checkGrupo) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi ADD COLUMN ss_e_tx_grupo VARCHAR(255) NULL AFTER ss_e_nb_id;");
    mysqli_query($conn, "ALTER TABLE ss_epi ADD COLUMN ss_e_tx_subgrupo VARCHAR(255) NULL AFTER ss_e_tx_grupo;");
    mysqli_query($conn, "ALTER TABLE ss_epi ADD COLUMN ss_e_tx_item VARCHAR(255) NULL AFTER ss_e_tx_subgrupo;");
    mysqli_query($conn, "ALTER TABLE ss_epi ADD COLUMN ss_e_tx_descricao TEXT NULL AFTER ss_e_tx_item;");
    
    // Se a coluna antiga ss_e_tx_nome existia, movemos os dados para ss_e_tx_grupo e a removemos
    $checkNome = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi LIKE 'ss_e_tx_nome'");
    if ($checkNome && mysqli_num_rows($checkNome) > 0) {
        mysqli_query($conn, "UPDATE ss_epi SET ss_e_tx_grupo = ss_e_tx_nome;");
        mysqli_query($conn, "ALTER TABLE ss_epi DROP COLUMN ss_e_tx_nome;");
    }
}

$checkTipoCadastro = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi LIKE 'ss_e_tx_cadastro_tipo'");
if ($checkTipoCadastro && mysqli_num_rows($checkTipoCadastro) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi ADD COLUMN ss_e_tx_cadastro_tipo ENUM('universal', 'estoque') NOT NULL DEFAULT 'estoque' AFTER ss_e_tx_status;");
    
    // Populate universal catalog from existing items
    mysqli_query($conn, "INSERT INTO ss_epi (ss_e_tx_grupo, ss_e_tx_subgrupo, ss_e_tx_item, ss_e_tx_status, ss_e_tx_cadastro_tipo)
                         SELECT DISTINCT ss_e_tx_grupo, ss_e_tx_subgrupo, ss_e_tx_item, 'ativo', 'universal'
                         FROM ss_epi
                         WHERE ss_e_tx_grupo IS NOT NULL AND ss_e_tx_grupo != '';");
}

$checkFotoEpi = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi LIKE 'ss_e_tx_foto'");
if ($checkFotoEpi && mysqli_num_rows($checkFotoEpi) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi ADD COLUMN ss_e_tx_foto VARCHAR(255) NULL AFTER ss_e_tx_cadastro_tipo;");
}

$checkModeloEpi = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi LIKE 'ss_e_tx_modelo'");
if ($checkModeloEpi && mysqli_num_rows($checkModeloEpi) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi ADD COLUMN ss_e_tx_modelo VARCHAR(255) NULL AFTER ss_e_tx_fabricante;");
}

// 3. Tabela ss_epi_estoque (Controle de Estoque/Movimentação)
// Usando prefixo ss_e para corresponder ao substr(tabela, 0, 4)
$sqlEstoque = "CREATE TABLE IF NOT EXISTS ss_epi_estoque (
    ss_e_nb_id INT AUTO_INCREMENT PRIMARY KEY,
    ss_e_nb_epi_id INT NOT NULL,
    ss_e_nb_quantidade INT NOT NULL,
    ss_e_tx_tipo ENUM('entrada', 'saida', 'substituicao') NOT NULL DEFAULT 'entrada',
    ss_e_db_valor_unitario DECIMAL(10,2) NULL,
    ss_e_db_valor_total DECIMAL(10,2) NULL,
    ss_e_tx_data VARCHAR(30) NULL,
    ss_e_tx_motivo VARCHAR(255) NULL,
    ss_e_tx_foto VARCHAR(255) NULL,
    ss_e_nb_userCadastro INT NULL,
    FOREIGN KEY (ss_e_nb_epi_id) REFERENCES ss_epi(ss_e_nb_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
mysqli_query($conn, $sqlEstoque) or die("Erro ao criar tabela ss_epi_estoque: " . mysqli_error($conn));

$checkValorUnitario = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_estoque LIKE 'ss_e_db_valor_unitario'");
if ($checkValorUnitario && mysqli_num_rows($checkValorUnitario) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi_estoque ADD COLUMN ss_e_db_valor_unitario DECIMAL(10,2) NULL AFTER ss_e_tx_tipo;");
    mysqli_query($conn, "ALTER TABLE ss_epi_estoque ADD COLUMN ss_e_db_valor_total DECIMAL(10,2) NULL AFTER ss_e_db_valor_unitario;");
}

$checkTipo = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_estoque LIKE 'ss_e_tx_tipo'");
if ($checkTipo && $rowTipo = mysqli_fetch_assoc($checkTipo)) {
    if (strpos($rowTipo['Type'], 'substituicao') === false) {
        mysqli_query($conn, "ALTER TABLE ss_epi_estoque MODIFY COLUMN ss_e_tx_tipo ENUM('entrada', 'saida', 'substituicao') NOT NULL DEFAULT 'entrada';");
    }
}

$checkFoto = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_estoque LIKE 'ss_e_tx_foto'");
if ($checkFoto && mysqli_num_rows($checkFoto) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi_estoque ADD COLUMN ss_e_tx_foto VARCHAR(255) NULL AFTER ss_e_tx_motivo;");
}

// 4. Tabela ss_epi_entrega (Entregas de EPIs)
// Usando prefixo ss_e para corresponder ao substr(tabela, 0, 4)
$sqlEntrega = "CREATE TABLE IF NOT EXISTS ss_epi_entrega (
    ss_e_nb_id INT AUTO_INCREMENT PRIMARY KEY,
    ss_e_nb_colaborador_id INT NOT NULL,
    ss_e_nb_epi_id INT NOT NULL,
    ss_e_tx_data_entrega DATE NOT NULL,
    ss_e_nb_quantidade INT NOT NULL DEFAULT 1,
    ss_e_tx_vencimento DATE NULL,
    ss_e_tx_status ENUM('ativo', 'substituido', 'devolvido', 'perdido') NOT NULL DEFAULT 'ativo',
    ss_e_tx_assinatura LONGTEXT NULL,
    ss_e_nb_userCadastro INT NULL,
    ss_e_tx_dataCadastro VARCHAR(30) NULL,
    ss_e_nb_userAtualiza INT NULL,
    ss_e_tx_dataAtualiza VARCHAR(30) NULL,
    FOREIGN KEY (ss_e_nb_epi_id) REFERENCES ss_epi(ss_e_nb_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
mysqli_query($conn, $sqlEntrega) or die("Erro ao criar tabela ss_epi_entrega: " . mysqli_error($conn));

// Migração segura para quem já tinha a tabela antiga criada com FK para ss_colaborador
$checkFK = mysqli_query($conn, "SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'ss_epi_entrega' AND CONSTRAINT_NAME = 'ss_epi_entrega_ibfk_1'");
if ($checkFK && mysqli_num_rows($checkFK) > 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi_entrega DROP FOREIGN KEY ss_epi_entrega_ibfk_1;");
}

$checkFotoEntrega = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_entrega LIKE 'ss_e_tx_foto'");
if ($checkFotoEntrega && mysqli_num_rows($checkFotoEntrega) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi_entrega ADD COLUMN ss_e_tx_foto VARCHAR(255) NULL AFTER ss_e_tx_assinatura;");
}

// 5. Tabela ss_kit (Kits)
$sqlKit = "CREATE TABLE IF NOT EXISTS ss_kit (
    ss_k_nb_id INT AUTO_INCREMENT PRIMARY KEY,
    ss_k_tx_nome VARCHAR(100) NOT NULL,
    ss_k_tx_status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    ss_k_nb_userCadastro INT NULL,
    ss_k_tx_dataCadastro VARCHAR(30) NULL,
    ss_k_nb_userAtualiza INT NULL,
    ss_k_tx_dataAtualiza VARCHAR(30) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
mysqli_query($conn, $sqlKit) or die("Erro ao criar tabela ss_kit: " . mysqli_error($conn));

// 6. Tabela ss_kit_item (Itens do Kit)
$sqlKitItem = "CREATE TABLE IF NOT EXISTS ss_kit_item (
    ss_ki_nb_id INT AUTO_INCREMENT PRIMARY KEY,
    ss_ki_nb_kit_id INT NOT NULL,
    ss_ki_nb_epi_id INT NOT NULL,
    ss_ki_nb_quantidade INT NOT NULL DEFAULT 1,
    FOREIGN KEY (ss_ki_nb_kit_id) REFERENCES ss_kit(ss_k_nb_id) ON DELETE CASCADE,
    FOREIGN KEY (ss_ki_nb_epi_id) REFERENCES ss_epi(ss_e_nb_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
mysqli_query($conn, $sqlKitItem) or die("Erro ao criar tabela ss_kit_item: " . mysqli_error($conn));

// 7. Modificar ss_epi_entrega para adicionar coluna de observação e alterar status ENUM
$checkObs = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_entrega LIKE 'ss_e_tx_observacao'");
if ($checkObs && mysqli_num_rows($checkObs) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi_entrega ADD COLUMN ss_e_tx_observacao VARCHAR(255) NULL AFTER ss_e_tx_foto;");
}

$checkStatusEntrega = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_entrega LIKE 'ss_e_tx_status'");
if ($checkStatusEntrega && $rowStatus = mysqli_fetch_assoc($checkStatusEntrega)) {
    if (strpos($rowStatus['Type'], 'inativo') === false) {
        mysqli_query($conn, "ALTER TABLE ss_epi_entrega MODIFY COLUMN ss_e_tx_status ENUM('ativo', 'substituido', 'devolvido', 'perdido', 'nao_entregue', 'inativo') NOT NULL DEFAULT 'ativo';");
    }
}

$checkJustificativa = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_entrega LIKE 'ss_e_tx_justificativa_exclusao'");
if ($checkJustificativa && mysqli_num_rows($checkJustificativa) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi_entrega ADD COLUMN ss_e_tx_justificativa_exclusao VARCHAR(255) NULL AFTER ss_e_tx_observacao;");
}

// 8. Modificações para controle por filial, data recebimento e chave NF
$checkRecebimento = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_estoque LIKE 'ss_e_tx_data_recebimento'");
if ($checkRecebimento && mysqli_num_rows($checkRecebimento) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi_estoque ADD COLUMN ss_e_tx_data_recebimento DATE NULL AFTER ss_e_db_valor_total;");
}
$checkChaveNf = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_estoque LIKE 'ss_e_tx_chave_nf'");
if ($checkChaveNf && mysqli_num_rows($checkChaveNf) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi_estoque ADD COLUMN ss_e_tx_chave_nf VARCHAR(100) NULL AFTER ss_e_tx_data_recebimento;");
}
$checkEmpresaEstoque = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_estoque LIKE 'ss_e_nb_empresa_id'");
if ($checkEmpresaEstoque && mysqli_num_rows($checkEmpresaEstoque) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi_estoque ADD COLUMN ss_e_nb_empresa_id INT NULL AFTER ss_e_nb_epi_id;");
}
$checkEmpresaEntrega = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_entrega LIKE 'ss_e_nb_empresa_id'");
if ($checkEmpresaEntrega && mysqli_num_rows($checkEmpresaEntrega) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi_entrega ADD COLUMN ss_e_nb_empresa_id INT NULL AFTER ss_e_nb_epi_id;");
}

$checkFornecedor = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_estoque LIKE 'ss_e_tx_fornecedor'");
if ($checkFornecedor && mysqli_num_rows($checkFornecedor) == 0) {
    mysqli_query($conn, "ALTER TABLE ss_epi_estoque ADD COLUMN ss_e_tx_fornecedor VARCHAR(255) NULL AFTER ss_e_tx_chave_nf;");
}

mysqli_query($conn, "ALTER TABLE ss_epi MODIFY COLUMN ss_e_tx_foto TEXT NULL;");
mysqli_query($conn, "ALTER TABLE ss_epi_entrega MODIFY COLUMN ss_e_tx_foto TEXT NULL;");

include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_grid.php";
include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_form.php";
include_once __DIR__ . "/funcoes_saude.php";
include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes.php";
?>
