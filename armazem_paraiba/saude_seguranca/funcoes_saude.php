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
    ?string $fornecedor = null,
    ?string $validade = null
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
    $valValidade = ($validade !== null && $validade !== '') ? "'" . mysqli_real_escape_string($conn, $validade) . "'" : "NULL";
    
    $sql = "INSERT INTO ss_epi_estoque (ss_e_nb_epi_id, ss_e_nb_empresa_id, ss_e_nb_quantidade, ss_e_tx_tipo, ss_e_db_valor_unitario, ss_e_db_valor_total, ss_e_tx_data_recebimento, ss_e_tx_chave_nf, ss_e_tx_fornecedor, ss_e_tx_data, ss_e_tx_motivo, ss_e_tx_foto, ss_e_nb_userCadastro, ss_e_tx_validade)
            VALUES ({$idEpi}, {$valEmpresa}, {$quantidade}, '{$tipo}', {$valUnit}, {$valTot}, {$valDataReceb}, {$valChaveNf}, {$valFornecedor}, '{$dataAtual}', '{$motivo}', {$valFoto}, {$userCadastro}, {$valValidade})";
            
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

function ss_resolve_foto_url($p) {
    $p = trim($p);
    if (empty($p)) {
        return "";
    }
    
    // Se já for URL completa ou base64
    if (strpos($p, "data:image/") === 0 || strpos($p, "http") === 0) {
        return $p;
    }
    
    $docRoot = $_SERVER["DOCUMENT_ROOT"];
    $appPath = $_ENV["APP_PATH"] ?? "/braso";
    
    // Verifica se já tem saude_seguranca ou armazem_paraiba no caminho
    $hasSaudeSeguranca = (strpos($p, "saude_seguranca/") !== false || strpos($p, "armazem_paraiba/") !== false);
    
    if (!$hasSaudeSeguranca) {
        $pathWithModule = 'armazem_paraiba/saude_seguranca/' . $p;
        $fullPathWithModule = rtrim($docRoot, '/\\') . '/' . ltrim($appPath, '/\\') . '/' . $pathWithModule;
        
        if (file_exists($fullPathWithModule)) {
            return rtrim($appPath, '/') . '/' . $pathWithModule;
        }
    }
    
    return rtrim($appPath, '/') . '/' . $p;
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
        $src = ss_resolve_foto_url($p);
        if (empty($src)) continue;
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
        ss_e_tx_validade_epi DATE DEFAULT NULL,
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

    // Garante que a coluna ss_e_tx_validade_epi exista em bancos já criados
    $check_column = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi LIKE 'ss_e_tx_validade_epi'");
    if ($check_column && mysqli_num_rows($check_column) == 0) {
        mysqli_query($conn, "ALTER TABLE ss_epi ADD COLUMN ss_e_tx_validade_epi DATE DEFAULT NULL AFTER ss_e_tx_validade_ca");
    }

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
        ss_e_tx_validade DATE DEFAULT NULL,
        ss_e_tx_chave_nf VARCHAR(44) DEFAULT NULL,
        ss_e_tx_fornecedor VARCHAR(255) DEFAULT NULL,
        ss_e_tx_data DATETIME NOT NULL,
        ss_e_tx_motivo TEXT DEFAULT NULL,
        ss_e_tx_foto VARCHAR(255) DEFAULT NULL,
        ss_e_nb_userCadastro INT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    mysqli_query($conn, $sql_estoque);

    // Garante que a coluna ss_e_tx_validade exista em ss_epi_estoque
    $check_column_est = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_estoque LIKE 'ss_e_tx_validade'");
    if ($check_column_est && mysqli_num_rows($check_column_est) == 0) {
        mysqli_query($conn, "ALTER TABLE ss_epi_estoque ADD COLUMN ss_e_tx_validade DATE DEFAULT NULL AFTER ss_e_tx_data_recebimento");
    }

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

    // Garante que a coluna ss_e_nb_assinatura_id exista em ss_epi_entrega
    $check_column_ent = mysqli_query($conn, "SHOW COLUMNS FROM ss_epi_entrega LIKE 'ss_e_nb_assinatura_id'");
    if ($check_column_ent && mysqli_num_rows($check_column_ent) == 0) {
        mysqli_query($conn, "ALTER TABLE ss_epi_entrega ADD COLUMN ss_e_nb_assinatura_id INT DEFAULT NULL");
    }

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

function ss_verificar_assinatura_ativa() {
    global $conn;
    
    // Inclui a integração e garante as tabelas de assinatura para evitar erros de SQL em queries de LEFT JOIN
    $integPath = dirname(__DIR__) . "/assinatura/integracao/assinatura_integracao.php";
    if (file_exists($integPath)) {
        require_once $integPath;
        if (function_exists('assinatura_integracao_ensureTables')) {
            assinatura_integracao_ensureTables($conn);
        }
    }

    $res = mysqli_query($conn, "SELECT tipo_nb_id, tipo_tx_assinatura FROM tipos_documentos WHERE tipo_tx_nome = 'Recibo de EPI' AND tipo_tx_status = 'ativo' LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        if ($row['tipo_tx_assinatura'] === 'sim') {
            return (int)$row['tipo_nb_id'];
        }
    }
    return 0;
}

function ss_obter_responsaveis_colaborador($colaborador_id) {
    global $conn;
    $colaborador_id = (int)$colaborador_id;
    if ($colaborador_id <= 0) {
        return [];
    }
    
    $sql = "SELECT enti_respSetor_ids, enti_respCargo_ids FROM entidade WHERE enti_nb_id = {$colaborador_id} LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return [];
    }
    $row = mysqli_fetch_assoc($res);
    if (!$row) {
        return [];
    }
    
    $managerIds = [];
    foreach (['enti_respSetor_ids', 'enti_respCargo_ids'] as $col) {
        $csv = trim(strval($row[$col] ?? ""));
        if ($csv !== "") {
            foreach (explode(",", $csv) as $p) {
                $v = intval(trim($p));
                if ($v > 0) {
                    $managerIds[] = $v;
                }
            }
        }
    }
    
    $managerIds = array_values(array_unique($managerIds));
    if (empty($managerIds)) {
        return [];
    }
    
    $idsStr = implode(",", $managerIds);
    $sqlMgr = "SELECT enti_nb_id, enti_tx_nome, enti_tx_email FROM entidade WHERE enti_nb_id IN ($idsStr) AND enti_tx_status = 'ativo'";
    $resMgr = mysqli_query($conn, $sqlMgr);
    $managers = [];
    if ($resMgr) {
        while ($rm = mysqli_fetch_assoc($resMgr)) {
            $email = trim($rm['enti_tx_email']);
            if ($email !== "" && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $managers[] = [
                    'id' => (int)$rm['enti_nb_id'],
                    'nome' => trim($rm['enti_tx_nome']),
                    'email' => $email
                ];
            }
        }
    }
    return $managers;
}

function ss_gerar_pdf_ficha_epi($colaborador_id, $delivery_ids = [], $recibo_uuid = '') {
    global $conn;
    
    $colabRaw = carregar("entidade", $colaborador_id);
    if (empty($colabRaw)) {
        return "";
    }
    
    // Busca informações da assinatura associada a estas entregas (ou última se vazio)
    $tipoDocId = ss_verificar_assinatura_ativa();
    $assinaturaDados = null;
    if ($tipoDocId > 0) {
        if (!empty($delivery_ids)) {
            $firstId = (int)$delivery_ids[0];
            $sqlSig = "SELECT s.id, s.id_documento, a.status, a.data_assinatura, ae.ip_address, ae.hash_assinatura, a.cpf, a.nome
                       FROM ss_epi_entrega ent
                       JOIN solicitacoes_assinatura s ON ent.ss_e_nb_assinatura_id = s.id
                       JOIN assinantes a ON a.id_solicitacao = s.id AND a.enti_nb_id = ent.ss_e_nb_colaborador_id
                       LEFT JOIN assinatura_eletronica ae ON ae.id_documento COLLATE utf8mb4_general_ci = s.id_documento COLLATE utf8mb4_general_ci AND ae.cpf COLLATE utf8mb4_general_ci = a.cpf COLLATE utf8mb4_general_ci
                       WHERE ent.ss_e_nb_id = {$firstId}
                       ORDER BY s.id DESC LIMIT 1";
        } else {
            $sqlSig = "SELECT s.id, s.id_documento, a.status, a.data_assinatura, ae.ip_address, ae.hash_assinatura, a.cpf, a.nome
                       FROM solicitacoes_assinatura s
                       JOIN assinantes a ON a.id_solicitacao = s.id
                       LEFT JOIN assinatura_eletronica ae ON ae.id_documento COLLATE utf8mb4_general_ci = s.id_documento COLLATE utf8mb4_general_ci AND ae.cpf COLLATE utf8mb4_general_ci = a.cpf COLLATE utf8mb4_general_ci
                       WHERE a.enti_nb_id = {$colaborador_id} 
                         AND s.tipo_documento_id = {$tipoDocId}
                       ORDER BY s.id DESC LIMIT 1";
        }
        $resSig = mysqli_query($conn, $sqlSig);
        if ($resSig && $rowSig = mysqli_fetch_assoc($resSig)) {
            $assinaturaDados = $rowSig;
        }
    }

    // Configura paths
    $tcpdfPath = dirname(__DIR__) . '/tcpdf/tcpdf.php';
    if (!file_exists($tcpdfPath)) {
        return "";
    }
    require_once $tcpdfPath;

    if (!class_exists('SS_EPI_PDF')) {
        class SS_EPI_PDF extends TCPDF {
            public $custom_header = '';
            public function Header() {
                $this->SetY(10);
                $logoPath = dirname(__DIR__) . '/imagens/logo_topo_cliente.png';
                if (file_exists($logoPath)) {
                    $this->Image($logoPath, 15, 6, 25, 8); // x, y, width, height
                }
                $this->SetFont('helvetica', 'B', 11);
                $this->SetX(45);
                $this->Cell(0, 8, $this->custom_header, 0, false, 'L', 0, '', 0, false, 'T', 'M');
                $this->Line(15, 18, 195, 18);
            }
            public function Footer() {
                $this->SetY(-15);
                $this->SetFont('helvetica', 'I', 8);
                $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
            }
        }
    }

    $dirPdf = dirname(__DIR__) . '/arquivos/entrega_epi';
    if (!is_dir($dirPdf)) {
        @mkdir($dirPdf, 0777, true);
    }
    
    $parts = explode('-', $recibo_uuid);
    $lastBlock = trim(end($parts));
    if ($lastBlock === '') {
        $lastBlock = strtoupper(bin2hex(random_bytes(4)));
    }
    $nomeArquivo = 'Recibo_EPI_Colaborador_' . $lastBlock . '.pdf';
    $caminhoPdf = rtrim(str_replace('\\', '/', $dirPdf), '/') . '/' . $nomeArquivo;

    $pdf = new SS_EPI_PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->custom_header = "RECIBO DE ENTREGA DE EPI";
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Sistema Braso');
    $pdf->SetTitle('Recibo de EPI - ' . $colabRaw["enti_tx_nome"]);
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 9);

    // Render Employee Info
    $cpfFmt = $colabRaw["enti_tx_cpf"] ? preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "$1.$2.$3-$4", $colabRaw["enti_tx_cpf"]) : '---';
    $matricula = $colabRaw["enti_tx_matricula"] ?: '---';
    $cargo = $colabRaw["enti_tx_ocupacao"] ?: '---';
    
    $html = '
    <table cellpadding="3" border="0" style="width:100%; border:1px solid #ccc; background-color:#f9f9f9;">
        <tr>
            <td style="width:15%;"><b>Colaborador:</b></td>
            <td style="width:50%;">' . htmlspecialchars($colabRaw["enti_tx_nome"]) . '</td>
            <td style="width:15%;"><b>Matrícula:</b></td>
            <td style="width:20%;">' . htmlspecialchars($matricula) . '</td>
        </tr>
        <tr>
            <td><b>CPF:</b></td>
            <td>' . htmlspecialchars($cpfFmt) . '</td>
            <td><b>Cargo/Função:</b></td>
            <td>' . htmlspecialchars($cargo) . '</td>
        </tr>';
        
    if ($recibo_uuid !== '') {
        $html .= '
        <tr>
            <td><b>ID Recibo:</b></td>
            <td colspan="3"><span style="font-family:courier; font-weight:bold; color:#333;">' . htmlspecialchars($recibo_uuid) . '</span></td>
        </tr>';
    } elseif ($assinaturaDados && !empty($assinaturaDados['id_documento'])) {
        $html .= '
        <tr>
            <td><b>ID Recibo:</b></td>
            <td colspan="3"><span style="font-family:courier; font-weight:bold; color:#333;">' . htmlspecialchars($assinaturaDados['id_documento']) . '</span></td>
        </tr>';
    }
    
    $html .= '
    </table>
    <br><br>
    <div style="text-align:justify; font-size:8pt; border:1px solid #ccc; padding:8px; background-color:#fff;">
        <strong>Declaração do Empregado:</strong> Declaro que recebi gratuitamente os Equipamentos de Proteção Individual (EPI) abaixo relacionados, adequados ao risco de minhas atividades. Comprometo-me a usá-los obrigatoriamente durante o horário de trabalho, zelar pela sua guarda e conservação, e comunicar imediatamente ao setor de segurança qualquer alteração que os torne impróprios para uso, sob pena de infração disciplinar nos termos da legislação trabalhista brasileira (Art. 158 da CLT).
    </div>
    <br><br>
    <table cellpadding="4" border="1" style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="background-color:#f2f2f2; font-weight:bold; text-align:center;">
                <th style="width:10%;">Cód</th>
                <th style="width:55%;">Equipamento (EPI)</th>
                <th style="width:15%;">CA MTE</th>
                <th style="width:15%;">Entrega</th>
                <th style="width:5%;">Qtd</th>
            </tr>
        </thead>
        <tbody>';

    $whereIds = "";
    if (!empty($delivery_ids)) {
        $idsClean = array_map('intval', $delivery_ids);
        $whereIds = " AND ent.ss_e_nb_id IN (" . implode(",", $idsClean) . ") ";
    }

    $sql = "SELECT ent.ss_e_nb_id, 
                   CONCAT(epi.ss_e_tx_grupo, ' / ', IFNULL(epi.ss_e_tx_subgrupo, ''), ' / ', IFNULL(epi.ss_e_tx_item, '')) AS ss_e_tx_nome, 
                   epi.ss_e_tx_ca, 
                   ent.ss_e_tx_data_entrega, 
                   ent.ss_e_nb_quantidade, 
                   ent.ss_e_tx_vencimento, 
                   ent.ss_e_tx_status,
                   IFNULL(s.id_documento, '-') AS ss_e_tx_identificador
            FROM ss_epi_entrega ent 
            JOIN ss_epi epi ON ent.ss_e_nb_epi_id = epi.ss_e_nb_id 
            LEFT JOIN solicitacoes_assinatura s ON ent.ss_e_nb_assinatura_id = s.id
            WHERE ent.ss_e_nb_colaborador_id = {$colaborador_id} AND ent.ss_e_tx_status <> 'inativo' {$whereIds}
            ORDER BY ent.ss_e_tx_data_entrega DESC, ent.ss_e_nb_id DESC";
            
    $res = mysqli_query($conn, $sql);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }
    
    $hasRows = !empty($rows);

    if ($hasRows) {
        foreach ($rows as $index => $row) {
            $dataEnt = !empty($row["ss_e_tx_data_entrega"]) ? date('d/m/Y', strtotime($row["ss_e_tx_data_entrega"])) : '';
            
            $html .= '
            <tr>
                <td style="width:10%; text-align:center;">' . $row["ss_e_nb_id"] . '</td>
                <td style="width:55%;">' . htmlspecialchars($row["ss_e_tx_nome"]) . '</td>
                <td style="width:15%; text-align:center;">' . htmlspecialchars($row["ss_e_tx_ca"] ?: '---') . '</td>
                <td style="width:15%; text-align:center;">' . $dataEnt . '</td>
                <td style="width:5%; text-align:center;">' . $row["ss_e_nb_quantidade"] . '</td>
            </tr>';
        }
    }

    if (!$hasRows) {
        $html .= '<tr><td colspan="5" style="text-align:center; color:#999;">Nenhum registro encontrado.</td></tr>';
    }

    $html .= '
        </tbody>
    </table>';

    // Adiciona o termo/bloco de assinatura ao final do documento
    if ($assinaturaDados && $assinaturaDados['status'] === 'assinado') {
        $cpfFmtSig = $assinaturaDados["cpf"] ? preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "$1.$2.$3-$4", $assinaturaDados["cpf"]) : '---';
        $dataAssinatura = !empty($assinaturaDados['data_assinatura']) ? date('d/m/Y H:i', strtotime($assinaturaDados['data_assinatura'])) : '';
        $ip = $assinaturaDados['ip_address'] ?? '---';
        $hash = $assinaturaDados['hash_assinatura'] ?? '---';
        
        $html .= '
        <br><br>
        <table cellpadding="6" style="width:100%; border:1px solid #0056b3; background-color:#f4f8fc;">
            <tr>
                <td>
                    <span style="color:#0056b3; font-weight:bold; font-size:10pt;">ASSINATURA ELETRÔNICA</span><br>
                    <span style="font-size:8.5pt; line-height:1.4;">
                        Este documento foi assinado eletronicamente por <b>' . htmlspecialchars($assinaturaDados['nome']) . '</b> (CPF: ' . htmlspecialchars($cpfFmtSig) . ') em <b>' . $dataAssinatura . '</b>.<br>
                        IP de Origem: <b>' . htmlspecialchars($ip) . '</b> | Protocolo/Hash: <b>' . htmlspecialchars($hash) . '</b>.<br>
                        <span style="color:#555; font-size:7.5pt;">A concordância expressa do colaborador valida legalmente a assinatura nos termos da Medida Provisória nº 2.200-2/2001.</span>
                    </span>
                </td>
            </tr>
        </table>';
    } else if ($assinaturaDados && $assinaturaDados['status'] === 'pendente') {
        $html .= '
        <br><br>
        <table cellpadding="6" style="width:100%; border:1px dashed #e87e04; background-color:#fffdf4;">
            <tr>
                <td style="text-align:center;">
                    <span style="color:#e87e04; font-weight:bold; font-size:10pt;">ASSINATURA ELETRÔNICA PENDENTE</span><br>
                    <span style="font-size:8.5pt; color:#666;">
                        Este documento foi gerado pelo sistema e aguarda a assinatura eletrônica do colaborador através do portal.
                    </span>
                </td>
            </tr>
        </table>';
    } else {
        $html .= '
        <br><br><br>
        <table cellpadding="3" border="0" style="width:100%;">
            <tr>
                <td style="width:50%; text-align:center;">
                    ________________________________________________<br>
                    <b>Assinatura do Colaborador</b>
                </td>
                <td style="width:50%; text-align:center;">
                    ________________________________________________<br>
                    <b>Assinatura do Representante da Empresa</b>
                </td>
            </tr>
        </table>';
    }

    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Salva no arquivo
    $pdf->Output($caminhoPdf, 'F');
    
    return 'arquivos/entrega_epi/' . $nomeArquivo;
}

function ss_enviar_ficha_para_assinatura($colaborador_id, $delivery_ids) {
    global $conn;
    
    $tipoDocId = ss_verificar_assinatura_ativa();
    if ($tipoDocId <= 0) {
        return false;
    }
    
    // Gera um UUID único para o Recibo de EPI
    $recibo_uuid = 'REC-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
    
    // 1. Gera o PDF atualizado do recibo (contendo apenas os novos itens e o recibo_uuid)
    $caminhoRel = ss_gerar_pdf_ficha_epi($colaborador_id, $delivery_ids, $recibo_uuid);
    if (empty($caminhoRel)) {
        return false;
    }
    
    // 2. Busca responsáveis do colaborador (Setor ou Cargo)
    $managers = ss_obter_responsaveis_colaborador($colaborador_id);
    
    // 3. Chama integração da assinatura para múltiplos assinantes
    require_once dirname(__DIR__) . "/assinatura/integracao/assinatura_integracao.php";
    
    $res = signature_enviar_recibo_multiples($conn, $colaborador_id, $caminhoRel, $tipoDocId, $managers, $recibo_uuid);
    
    if (!empty($res["ok"]) && !empty($res["id_solicitacao"])) {
        $idSolicitacao = (int)$res["id_solicitacao"];
        
        $idsToUpdate = !empty($delivery_ids) ? $delivery_ids : [];
        if (!empty($idsToUpdate)) {
            $idsClean = array_unique(array_map('intval', $idsToUpdate));
            $sqlUp = "UPDATE ss_epi_entrega SET ss_e_nb_assinatura_id = {$idSolicitacao} WHERE ss_e_nb_id IN (" . implode(",", $idsClean) . ")";
            query($sqlUp);
        }
        
        return $idSolicitacao;
    }
    
    return false;
}

// Função auxiliar para estruturar o envio com múltiplos signatários (Funcionário + Responsáveis)
function signature_enviar_recibo_multiples($conn, $colaborador_id, $caminhoRel, $tipoDocId, $managers, $recibo_uuid) {
    $colaborador = carregar("entidade", $colaborador_id);
    
    $signatarios = [];
    // 1. Colaborador (Ordem 1)
    $signatarios[] = [
        "enti_nb_id" => $colaborador_id,
        "nome" => $colaborador["enti_tx_nome"],
        "email" => $colaborador["enti_tx_email"],
        "funcao" => "Funcionário",
        "ordem" => 1
    ];
    
    // 2. Responsáveis (Ordem 2)
    foreach ($managers as $mgr) {
        $signatarios[] = [
            "enti_nb_id" => $mgr["id"],
            "nome" => $mgr["nome"],
            "email" => $mgr["email"],
            "funcao" => "Responsável",
            "ordem" => 2
        ];
    }
    
    $parts = explode('-', $recibo_uuid);
    $lastBlock = trim(end($parts));
    if ($lastBlock === '') {
        $lastBlock = strtoupper(bin2hex(random_bytes(4)));
    }
    
    return assinatura_integracao_enviarDocumentoParaMultiplosAssinantes(
        $conn,
        $caminhoRel,
        $signatarios,
        [
            "nome_arquivo_original" => "Recibo_EPI_Colaborador_" . $lastBlock . ".pdf",
            "tipo_documento_id" => $tipoDocId,
            "validar_icp" => "nao",
            "modo_envio" => "avulso",
            "salvar_documento_funcionario" => "nao", // Evita salvar nos documentos dos responsáveis
            "id_documento" => $recibo_uuid // Define o identificador único na solicitação!
        ]
    );
}
?>
