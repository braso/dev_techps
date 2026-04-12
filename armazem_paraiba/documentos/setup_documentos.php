<?php
include_once "../conecta.php";

echo "<h2>Inicializando Banco de Dados - Módulo de Documentos</h2>";

// Verifica se uma coluna existe para aplicacao segura de migracoes.
function colunaExiste($conn, $tabela, $coluna) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$tabela` LIKE '$coluna'");
    return mysqli_num_rows($res) > 0;
}

// 0. Atualizar tabela de tipos existente para suportar layout
echo "Verificando 'tipos_documentos'...<br>";
$colunas_novas = [
    'tipo_tx_logo' => 'VARCHAR(255) DEFAULT NULL',
    'tipo_tx_cabecalho' => 'TEXT DEFAULT NULL',
    'tipo_tx_rodape' => 'TEXT DEFAULT NULL'
];

foreach ($colunas_novas as $col => $def) {
    if (!colunaExiste($conn, 'tipos_documentos', $col)) {
        $sql = "ALTER TABLE tipos_documentos ADD COLUMN $col $def";
        if (mysqli_query($conn, $sql)) {
            echo "Coluna '$col' adicionada.<br>";
        } else {
            echo "Erro ao adicionar '$col': " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "Coluna '$col' já existe.<br>";
    }
}

// 1. Tabela de Definição de Campos
$sql1 = "CREATE TABLE IF NOT EXISTS camp_documento_modulo (
    camp_nb_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    camp_nb_tipo_doc INT(11) NOT NULL,
    camp_tx_label VARCHAR(255) NOT NULL,
    camp_tx_tipo ENUM('texto_curto', 'texto_longo', 'data', 'selecao', 'usuario', 'setor', 'number') NOT NULL,
    camp_tx_opcoes TEXT DEFAULT NULL,
    camp_nb_ordem INT(11) DEFAULT 0,
    camp_tx_obrigatorio ENUM('sim', 'nao') DEFAULT 'nao',
    camp_tx_placeholder VARCHAR(255) DEFAULT NULL,
    camp_tx_status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    FOREIGN KEY (camp_nb_tipo_doc) REFERENCES tipos_documentos(tipo_nb_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

if (mysqli_query($conn, $sql1)) {
    echo "Tabela 'camp_documento_modulo' ok.<br>";
} else {
    echo "Erro na tabela 'camp_documento_modulo': " . mysqli_error($conn) . "<br>";
}

// 2. Tabela de Instâncias de Documentos
$sql2 = "CREATE TABLE IF NOT EXISTS inst_documento_modulo (
    inst_nb_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    inst_nb_tipo_doc INT(11) NOT NULL,
    inst_nb_user INT(11) NOT NULL,
    inst_dt_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    inst_tx_status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    FOREIGN KEY (inst_nb_tipo_doc) REFERENCES tipos_documentos(tipo_nb_id) ON DELETE CASCADE,
    FOREIGN KEY (inst_nb_user) REFERENCES user(user_nb_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

if (mysqli_query($conn, $sql2)) {
    echo "Tabela 'inst_documento_modulo' ok.<br>";
} else {
    echo "Erro na tabela 'inst_documento_modulo': " . mysqli_error($conn) . "<br>";
}

// 3. Tabela de Valores Preenchidos
$sql3 = "CREATE TABLE IF NOT EXISTS valo_documento_modulo (
    valo_nb_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    valo_nb_instancia INT(11) NOT NULL,
    valo_nb_campo INT(11) NOT NULL,
    valo_tx_valor TEXT DEFAULT NULL,
    valo_tx_status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    FOREIGN KEY (valo_nb_instancia) REFERENCES inst_documento_modulo(inst_nb_id) ON DELETE CASCADE,
    FOREIGN KEY (valo_nb_campo) REFERENCES camp_documento_modulo(camp_nb_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

if (mysqli_query($conn, $sql3)) {
    echo "Tabela 'valo_documento_modulo' ok.<br>";
} else {
    echo "Erro na tabela 'valo_documento_modulo': " . mysqli_error($conn) . "<br>";
}

echo "<br><b>Concluído!</b><br>";
echo "<br><a href='../cadastro_tipo_doc.php'>Voltar ao Sistema</a>";
?>
