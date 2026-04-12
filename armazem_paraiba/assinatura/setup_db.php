<?php
include_once "../conecta.php";

$sql_create_table_solicitacoes = "
CREATE TABLE IF NOT EXISTS solicitacoes_assinatura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_temp VARCHAR(255),
    nome_arquivo_original VARCHAR(255),
    caminho_arquivo VARCHAR(255),
    status ENUM('pendente', 'concluido', 'cancelado') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$sql_create_table_assinantes = "
CREATE TABLE IF NOT EXISTS assinantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_solicitacao INT,
    nome VARCHAR(255),
    email VARCHAR(255),
    cpf VARCHAR(20),
    status ENUM('pendente', 'visualizado', 'assinado', 'recusado') DEFAULT 'pendente',
    token VARCHAR(64),
    ordem INT DEFAULT 1,
    data_assinatura DATETIME,
    ip_assinatura VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_solicitacao) REFERENCES solicitacoes_assinatura(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Executa a criação das tabelas
if (mysqli_query($conn, $sql_create_table_solicitacoes)) {
    echo "Tabela 'solicitacoes_assinatura' verificada/criada com sucesso.<br>";
} else {
    echo "Erro ao criar tabela 'solicitacoes_assinatura': " . mysqli_error($conn) . "<br>";
}

if (mysqli_query($conn, $sql_create_table_assinantes)) {
    echo "Tabela 'assinantes' verificada/criada com sucesso.<br>";
} else {
    echo "Erro ao criar tabela 'assinantes': " . mysqli_error($conn) . "<br>";
}

echo "<br><a href='index.php'>Voltar para o Dashboard</a>";
?>
