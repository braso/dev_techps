<?php
// Garante a criação de todas as tabelas do módulo Saúde e Segurança se elas não existirem
ss_inicializar_tabelas();

/**
 * Retorna o saldo líquido atual de um EPI em estoque.
 * Soma as entradas e subtrai as saídas.
 *
 * @param int $idEpi
 * @return int
 */
function obterSaldoEstoque(int $idEpi, ?int $empresaId = null, bool $conferirTodasFiliais = false): int {
    global $conn;
    
    $idEpi = (int)$idEpi;
    if ($idEpi <= 0) return 0;
    
    $user_empresa = !empty($_SESSION["user_nb_empresa"]) ? (int)$_SESSION["user_nb_empresa"] : 0;
    
    $cond = "";
    if (!$conferirTodasFiliais) {
        if ($empresaId !== null && $empresaId > 0) {
            if ($user_empresa > 0 && $empresaId == $user_empresa) {
                $cond = " AND (ss_e_nb_empresa_id = {$empresaId} OR ss_e_nb_empresa_id IS NULL OR ss_e_nb_empresa_id = 0)";
            } else {
                $cond = " AND ss_e_nb_empresa_id = {$empresaId}";
            }
        } else {
            if ($user_empresa > 0) {
                $cond = " AND (ss_e_nb_empresa_id IS NULL OR ss_e_nb_empresa_id = 0 OR ss_e_nb_empresa_id = {$user_empresa})";
            } else {
                $cond = " AND (ss_e_nb_empresa_id IS NULL OR ss_e_nb_empresa_id = 0)";
            }
        }
    }
    
    $sql = "SELECT 
                SUM(CASE WHEN ss_e_tx_tipo = 'entrada' THEN ss_e_nb_quantidade ELSE -ss_e_nb_quantidade END) as saldo
            FROM ss_epi_estoque 
            WHERE ss_e_nb_epi_id = {$idEpi} {$cond}";
            
    $res = mysqli_query($conn, $sql);
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        return (int)($row['saldo'] ?? 0);
    }
    
    return 0;
}

/**
 * Registra uma movimentação no estoque do EPI.
 *
 * @param int $idEpi
 * @param int $quantidade
 * @param string $tipo ('entrada' ou 'saida')
 * @param string $motivo
 * @return bool
 */
function registrarMovimentacaoEstoque(
    int $idEpi, 
    int $quantidade, 
    string $tipo, 
    string $motivo = '', 
    ?float $valorUnitario = null, 
    ?float $valorTotal = null, 
    string $foto = '',
    ?string $dataRecebimento = null,
    ?string $chaveNf = null,
    ?int $empresaId = null,
    ?string $fornecedor = null
): bool {
    global $conn;
    
    $idEpi = (int)$idEpi;
    $quantidade = (int)$quantidade;
    
    if ($tipo === 'saida') {
        $tipo = 'saida';
    } else {
        $tipo = 'entrada';
    }
    
    $motivo = mysqli_real_escape_string($conn, $motivo);
    $foto = mysqli_real_escape_string($conn, $foto);
    $userCadastro = (int)($_SESSION['user_nb_id'] ?? 0);
    $dataAtual = date("Y-m-d H:i:s");
    
    if ($idEpi <= 0 || $quantidade <= 0) return false;
    
    $valUnit = ($valorUnitario !== null) ? (float)$valorUnitario : "NULL";
    $valTot = ($valorTotal !== null) ? (float)$valorTotal : "NULL";
    $valFoto = ($foto !== '') ? "'{$foto}'" : "NULL";
    $valDataReceb = ($dataRecebimento !== null && $dataRecebimento !== '') ? "'" . mysqli_real_escape_string($conn, $dataRecebimento) . "'" : "NULL";
    $valChaveNf = ($chaveNf !== null && $chaveNf !== '') ? "'" . mysqli_real_escape_string($conn, $chaveNf) . "'" : "NULL";
    $valEmpresa = ($empresaId !== null && $empresaId > 0) ? (int)$empresaId : "NULL";
    $valFornecedor = ($fornecedor !== null && $fornecedor !== '') ? "'" . mysqli_real_escape_string($conn, $fornecedor) . "'" : "NULL";
    
    $sql = "INSERT INTO ss_epi_estoque (ss_e_nb_epi_id, ss_e_nb_empresa_id, ss_e_nb_quantidade, ss_e_tx_tipo, ss_e_db_valor_unitario, ss_e_db_valor_total, ss_e_tx_data_recebimento, ss_e_tx_chave_nf, ss_e_tx_fornecedor, ss_e_tx_data, ss_e_tx_motivo, ss_e_tx_foto, ss_e_nb_userCadastro)
            VALUES ({$idEpi}, {$valEmpresa}, {$quantidade}, '{$tipo}', {$valUnit}, {$valTot}, {$valDataReceb}, {$valChaveNf}, {$valFornecedor}, '{$dataAtual}', '{$motivo}', {$valFoto}, {$userCadastro})";
            
    return (bool)mysqli_query($conn, $sql);
}

/**
 * Calcula a data estimada de vencimento de um EPI entregue.
 *
 * @param string $dataEntrega ('Y-m-d')
 * @param int $vidaUtilDias
 * @return string ('Y-m-d')
 */
function calcularVencimentoEpi(string $dataEntrega, int $vidaUtilDias): string {
    $vidaUtilDias = (int)$vidaUtilDias;
    if ($vidaUtilDias <= 0) return $dataEntrega;
    
    $date = new DateTime($dataEntrega);
    $date->modify("+{$vidaUtilDias} days");
    return $date->format('Y-m-d');
}

/**
 * Verifica se o Certificado de Aprovação (CA) está vencido na data de entrega do EPI.
 *
 * @param string $dataEntrega ('Y-m-d')
 * @param string|null $dataValidadeCA ('Y-m-d')
 * @return bool True se o CA estiver vencido, False caso contrário.
 */
function verificarCAVencido(string $dataEntrega, ?string $dataValidadeCA): bool {
    if (empty($dataValidadeCA)) return false;
    
    $entrega = new DateTime($dataEntrega);
    $validade = new DateTime($dataValidadeCA);
    
    return $entrega > $validade;
}

/**
 * Configura os botões de Editar (Lupa) e Excluir (Lixeira/SweetAlert) de forma autônoma para o módulo.
 */
function ss_gerarAcoesComConfirmacao(
    $arquivoFuncaoEditarExcluir, 
    $nomeAcaoEditar, 
    $nomeAcaoExcluir, 
    $nomeColunaTituloId = "CÓDIGO",
    $templatePadrao = "Deseja excluir o registro {CÓDIGO}?"
) {
    // Cria ícones nativos usando contex20
    $actions = criarIconesGrid(
        ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"], 
        [$arquivoFuncaoEditarExcluir, "javascript:void(0)"],
        [$nomeAcaoEditar . "()", ""] 
    );

    $encPadrao = rawurlencode($templatePadrao);

    $actions["tags"][1] = '<span class="glyphicon glyphicon-remove search-button" ' . 
                          'onclick="confirmarExclusaoEpi(this, \'' . $nomeAcaoExcluir . '\', \'' . $nomeColunaTituloId . '\', \'' . $encPadrao . '\')" ' . 
                          'title="Excluir" style="color:#d9534f; cursor:pointer;"></span>';

    $jsDoEditar = $actions["functions"][0];

    $jsFunctions = '
        var funcoesInternas = function(){
            try { ' . $jsDoEditar . ' } catch(e) { console.error(e); }
        };

        if (typeof window.confirmarExclusaoEpi === "undefined") {
            window.confirmarExclusaoEpi = function(elemento, acaoPHP, nomeColunaTituloId, encPadrao) {
                var linha = $(elemento).closest("tr");
                var tabela = $(elemento).closest("table");
                
                var dadosDaLinha = {};
                tabela.find("thead th").each(function(index) {
                    var nomeCol = $(this).text().trim().toUpperCase();
                    var valorCol = linha.find("td").eq(index).text().trim();
                    dadosDaLinha[nomeCol] = valorCol;
                });

                var id = linha.attr("data-row-id"); 
                if (!id || id === "") {
                    id = dadosDaLinha[nomeColunaTituloId.toUpperCase()] || "";
                }
                
                var textoSwal = decodeURIComponent(encPadrao).replace(/\{([^}]+)\}/g, function(match, nomeVariavel) {
                    var valorDaColuna = dadosDaLinha[nomeVariavel.toUpperCase()];
                    return (valorDaColuna === undefined || valorDaColuna === "") ? "" : valorDaColuna;
                });

                Swal.fire({
                    title: "Tem certeza?",
                    html: textoSwal, 
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#d33",
                    cancelButtonColor: "#6c757d",
                    confirmButtonText: "Sim, excluir!",
                    cancelButtonText: "Cancelar"
                }).then((result) => {
                    if (result.isConfirmed) {
                        var form = document.createElement("form");
                        form.method = "POST";
                        form.action = ""; 
                        var a = document.createElement("input"); a.type = "hidden"; a.name = "acao"; a.value = acaoPHP; form.appendChild(a);
                        var i = document.createElement("input"); i.type = "hidden"; i.name = "id"; i.value = id; form.appendChild(i);
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            };
        }
    ';
    return [
        "tags" => $actions["tags"],
        "js"   => $jsFunctions 
    ];
}

/**
 * Verifica se existem múltiplas filiais (empresas ativas) cadastradas no sistema.
 * Retorna true se houver mais de uma.
 *
 * @return bool
 */
function ss_tem_filiais_cadastradas() {
    global $conn;
    $user_empresa = !empty($_SESSION["user_nb_empresa"]) ? (int)$_SESSION["user_nb_empresa"] : 0;
    $sql = mysqli_query($conn, "SELECT COUNT(*) as total FROM empresa WHERE empr_tx_status = 'ativo' AND empr_nb_id != {$user_empresa}");
    if ($sql) {
        $row = mysqli_fetch_assoc($sql);
        return ((int)$row["total"] > 0);
    }
    return false;
}

function ss_grid_foto_render($foto_paths) {
    if (empty($foto_paths)) {
        return '<span class="text-muted">Sem Foto</span>';
    }
    
    $paths = array_filter(explode(",", $foto_paths));
    if (empty($paths)) {
        return '<span class="text-muted">Sem Foto</span>';
    }
    
    $html = '<div style="display: flex; gap: 4px; flex-wrap: wrap;">';
    foreach ($paths as $p) {
        $src = $_ENV["APP_PATH"] . '/' . $p;
        $html .= '<img src="' . $src . '" style="max-height: 35px; max-width: 35px; border-radius: 4px; border: 1px solid #ccc; object-fit: cover; cursor: pointer;" onclick="verImagemMaior(\'' . $src . '\')">';
    }
    $html .= '</div>';
    
    return $html;
}

/**
 * Cria todas as tabelas necessárias para o módulo de Saúde e Segurança caso não existam no banco.
 */
function ss_inicializar_tabelas() {
    global $conn;
    
    // 1. Tabela ss_epi
    $sql_epi = "CREATE TABLE IF NOT EXISTS ss_epi (
        ss_e_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        ss_e_tx_grupo VARCHAR(255) NOT NULL,
        ss_e_tx_subgrupo VARCHAR(255) DEFAULT NULL,
        ss_e_tx_item VARCHAR(255) DEFAULT NULL,
        ss_e_tx_fabricante VARCHAR(255) DEFAULT NULL,
        ss_e_tx_ca VARCHAR(50) DEFAULT NULL,
        ss_e_tx_validade_ca DATE DEFAULT NULL,
        ss_e_nb_vida_util_dias INT DEFAULT 0,
        ss_e_tx_foto VARCHAR(255) DEFAULT NULL,
        ss_e_tx_status VARCHAR(30) DEFAULT 'ativo',
        ss_e_tx_cadastro_tipo VARCHAR(50) DEFAULT 'estoque',
        ss_e_nb_userCadastro INT DEFAULT NULL,
        ss_e_tx_dataCadastro DATETIME DEFAULT NULL,
        ss_e_nb_userAtualiza INT DEFAULT NULL,
        ss_e_tx_dataAtualiza DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    mysqli_query($conn, $sql_epi);

    // 2. Tabela ss_colaborador
    $sql_colaborador = "CREATE TABLE IF NOT EXISTS ss_colaborador (
        ss_c_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        ss_c_tx_nome VARCHAR(255) NOT NULL,
        ss_c_tx_cpf VARCHAR(14) DEFAULT NULL,
        ss_c_tx_matricula VARCHAR(50) DEFAULT NULL,
        ss_c_tx_cargo VARCHAR(255) DEFAULT NULL,
        ss_c_tx_status VARCHAR(30) DEFAULT 'ativo',
        ss_c_nb_empresa_id INT DEFAULT NULL,
        ss_c_nb_userCadastro INT DEFAULT NULL,
        ss_c_tx_dataCadastro DATETIME DEFAULT NULL,
        ss_c_nb_userAtualiza INT DEFAULT NULL,
        ss_c_tx_dataAtualiza DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    mysqli_query($conn, $sql_colaborador);

    // 3. Tabela ss_epi_estoque
    $sql_estoque = "CREATE TABLE IF NOT EXISTS ss_epi_estoque (
        ss_e_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        ss_e_nb_epi_id INT NOT NULL,
        ss_e_nb_empresa_id INT DEFAULT NULL,
        ss_e_nb_quantidade INT NOT NULL,
        ss_e_tx_tipo VARCHAR(10) NOT NULL,
        ss_e_db_valor_unitario DECIMAL(10,2) DEFAULT NULL,
        ss_e_db_valor_total DECIMAL(10,2) DEFAULT NULL,
        ss_e_tx_data_recebimento DATE DEFAULT NULL,
        ss_e_tx_chave_nf VARCHAR(44) DEFAULT NULL,
        ss_e_tx_fornecedor VARCHAR(255) DEFAULT NULL,
        ss_e_tx_data DATETIME NOT NULL,
        ss_e_tx_motivo TEXT DEFAULT NULL,
        ss_e_tx_foto VARCHAR(255) DEFAULT NULL,
        ss_e_nb_userCadastro INT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    mysqli_query($conn, $sql_estoque);

    // 4. Tabela ss_epi_entrega
    $sql_entrega = "CREATE TABLE IF NOT EXISTS ss_epi_entrega (
        ss_e_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        ss_e_nb_colaborador_id INT NOT NULL,
        ss_e_nb_epi_id INT NOT NULL,
        ss_e_nb_empresa_id INT DEFAULT NULL,
        ss_e_tx_data_entrega DATE NOT NULL,
        ss_e_nb_quantidade INT NOT NULL,
        ss_e_tx_vencimento DATE DEFAULT NULL,
        ss_e_tx_status VARCHAR(30) DEFAULT 'ativo',
        ss_e_tx_foto TEXT DEFAULT NULL,
        ss_e_tx_observacao TEXT DEFAULT NULL,
        ss_e_nb_userCadastro INT DEFAULT NULL,
        ss_e_tx_dataCadastro DATETIME DEFAULT NULL,
        ss_e_nb_userAtualiza INT DEFAULT NULL,
        ss_e_tx_dataAtualiza DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    mysqli_query($conn, $sql_entrega);

    // 5. Tabela ss_kit
    $sql_kit = "CREATE TABLE IF NOT EXISTS ss_kit (
        ss_k_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        ss_k_tx_nome VARCHAR(255) NOT NULL,
        ss_k_tx_status VARCHAR(30) DEFAULT 'ativo',
        ss_k_nb_userCadastro INT DEFAULT NULL,
        ss_k_tx_dataCadastro DATETIME DEFAULT NULL,
        ss_k_nb_userAtualiza INT DEFAULT NULL,
        ss_k_tx_dataAtualiza DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    mysqli_query($conn, $sql_kit);

    // 6. Tabela ss_kit_item
    $sql_kit_item = "CREATE TABLE IF NOT EXISTS ss_kit_item (
        ss_ki_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        ss_ki_nb_kit_id INT NOT NULL,
        ss_ki_nb_epi_id INT NOT NULL,
        ss_ki_nb_quantidade INT NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    mysqli_query($conn, $sql_kit_item);
}
?>
