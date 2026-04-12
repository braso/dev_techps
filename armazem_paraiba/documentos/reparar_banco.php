<?php
include_once "../conecta.php";

echo "<h2>Reparando Banco de Dados - Tabelas de Documentos</h2>";

function colunaExisteLocal($conn, $tabela, $coluna) {
    try {
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `$tabela` LIKE '$coluna'");
        return mysqli_num_rows($res) > 0;
    } catch (Exception $e) {
        return false;
    }
}

// 1. Verificar documento_campo
echo "Verificando 'documento_campo'...<br>";
if (!colunaExisteLocal($conn, 'documento_campo', 'camp_nb_tipo_doc')) {
    echo "COLUNA 'camp_nb_tipo_doc' NÃO ENCONTRADA!<br>";
    // Tenta ver se existe com outro nome
    $res = mysqli_query($conn, "SHOW COLUMNS FROM documento_campo");
    echo "Colunas encontradas:<br>";
    $cols = [];
    while($row = mysqli_fetch_assoc($res)) {
        echo "- " . $row['Field'] . "<br>";
        $cols[] = $row['Field'];
    }
    
    // Se tiver 'camp_nb_tipo', renomeia
    if (in_array('camp_nb_tipo', $cols)) {
        echo "Renomeando 'camp_nb_tipo' para 'camp_nb_tipo_doc'...<br>";
        mysqli_query($conn, "ALTER TABLE documento_campo CHANGE camp_nb_tipo camp_nb_tipo_doc INT(11) NOT NULL");
    } else {
        echo "Adicionando 'camp_nb_tipo_doc'...<br>";
        mysqli_query($conn, "ALTER TABLE documento_campo ADD COLUMN camp_nb_tipo_doc INT(11) NOT NULL AFTER camp_nb_id");
    }
} else {
    echo "Coluna 'camp_nb_tipo_doc' ok.<br>";
}

// Garante que os outros campos existem
$campos_documento = [
    'camp_tx_label' => 'VARCHAR(255) NOT NULL',
    'camp_tx_tipo' => "ENUM('texto_curto', 'texto_longo', 'data', 'selecao', 'usuario', 'setor', 'number') NOT NULL",
    'camp_tx_opcoes' => 'TEXT DEFAULT NULL',
    'camp_nb_ordem' => 'INT(11) DEFAULT 0',
    'camp_tx_obrigatorio' => "ENUM('sim', 'nao') DEFAULT 'nao'",
    'camp_tx_placeholder' => 'VARCHAR(255) DEFAULT NULL'
];

foreach ($campos_documento as $col => $def) {
    if (!colunaExisteLocal($conn, 'documento_campo', $col)) {
        echo "Adicionando '$col'...<br>";
        mysqli_query($conn, "ALTER TABLE documento_campo ADD COLUMN $col $def");
    }
}

echo "<br><b>Operação concluída. Tente acessar o Layout novamente.</b>";
echo "<br><a href='configurar_layout.php'>Voltar ao Layout</a>";
?>
