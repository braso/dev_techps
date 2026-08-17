<?php
ob_start();
include "conecta.php";

// Garante resposta JSON válida mesmo com erros PHP (avisos/fatais) no endpoint AJAX
if (in_array($_GET["acao"] ?? "", ["lancarEstoqueLoteAjax", "salvarFornecedorAjax", "transferirEstoqueAjax", "obterSaldoEpiAjax"])) {
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        if (ob_get_level() > 0) ob_clean();
        echo json_encode(["status" => "error", "message" => "Erro PHP ({$errno}): {$errstr} (linha {$errline} em " . basename($errfile) . ")"]);
        exit;
    });
    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err["type"], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            if (ob_get_level() > 0) ob_clean();
            echo json_encode(["status" => "error", "message" => "Erro fatal PHP: " . $err["message"] . " (linha " . $err["line"] . " em " . basename($err["file"]) . ")"]);
        }
    });
}

function parseBrValor($val) {
    if (empty($val)) return null;
    $val = str_replace(['R$', ' '], '', $val);
    if (strpos($val, ',') !== false) {
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
    }
    return floatval($val);
}

function lancarEstoqueLoteAjax() {
    $lotes = json_decode($_POST["lotes"] ?? "[]", true);
    $sucessos = 0;
    $erros = [];
    $saved_ids = [];
    
    foreach ($lotes as $item) {
        $epi_id = (int)$item["epi_id"];
        $quantidade = (int)$item["quantidade"];
        $tipo = $item["tipo"];
        $motivo = $item["motivo"] ?? "";
        $valor_unitario = parseBrValor($item["valor_unitario"] ?? "");
        $valor_total = parseBrValor($item["valor_total"] ?? "");
        $data_recebimento = !empty($item["data_recebimento"]) ? $item["data_recebimento"] : null;
        $chave_nf = !empty($item["chave_nf"]) ? $item["chave_nf"] : null;
        $fornecedor = !empty($item["fornecedor"]) ? $item["fornecedor"] : null;
        $validade_epi = !empty($item["validade_epi"]) ? $item["validade_epi"] : null;
        $variacao = !empty($item["variacao"]) ? trim($item["variacao"]) : null;
        
        $empresa_id = !empty($item["empresa_id"]) ? (int)$item["empresa_id"] : null;
        if ($empresa_id === 0) {
            $empresa_id = null; // Matriz
        }
        
        if ($epi_id <= 0 || $quantidade <= 0) {
            $erros[] = "EPI inválido ou quantidade inválida.";
            continue;
        }
        
        // Se o EPI possui variações cadastradas, exige informar a variação
        $epi = carregar("ss_epi", $epi_id);
        if (empty($epi)) {
            $erros[] = "EPI ID {$epi_id} não encontrado no cadastro.";
            continue;
        }
        $temVariacoes = !empty($epi["ss_e_tx_variacoes"] ?? "");
        if ($temVariacoes && empty($variacao)) {
            $erros[] = "O EPI ID {$epi_id} possui variações cadastradas. Selecione a variação (numeração/tamanho).";
            continue;
        }
        
        if ($tipo === 'saida') {
            $saldoAtual = obterSaldoEstoque($epi_id, $empresa_id, false, $variacao);
            if ($quantidade > $saldoAtual) {
                $erros[] = "Estoque insuficiente para saída do EPI ID {$epi_id}" . ($variacao ? " (variação: {$variacao})" : "") . ". Saldo atual: {$saldoAtual}.";
                continue;
            }
        }
        
        $sucesso = registrarMovimentacaoEstoque($epi_id, $quantidade, $tipo, $motivo, $valor_unitario, $valor_total, "", $data_recebimento, $chave_nf, $empresa_id, $fornecedor, $validade_epi, $variacao);
        if ($sucesso) {
            $sucessos++;
            if (!empty($item["unique_id"])) {
                $saved_ids[] = $item["unique_id"];
            }
        } else {
            $erros[] = "Erro ao registrar movimentação para o EPI ID {$epi_id}.";
        }
    }
    
    ob_clean();
    echo json_encode([
        "status" => count($erros) === 0 ? "success" : "partial",
        "sucessos" => $sucessos,
        "erros" => $erros,
        "ids" => $saved_ids
    ]);
    exit;
}

function salvarFornecedorAjax() {
    global $conn;

    $razao = trim($_POST["razao_social"] ?? "");
    $cnpjDigits = preg_replace('/[^0-9]/', '', trim($_POST["cnpj"] ?? ""));
    $fantasia = trim($_POST["nome_fantasia"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");

    if (empty($razao) || empty($cnpjDigits)) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Razão Social e CNPJ são obrigatórios."]);
        exit;
    }

    if (strlen($cnpjDigits) !== 14) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "CNPJ inválido. Informe os 14 dígitos."]);
        exit;
    }

    $razao_e = mysqli_real_escape_string($conn, $razao);
    $cnpj_e = mysqli_real_escape_string($conn, $cnpjDigits);
    $fantasia_e = mysqli_real_escape_string($conn, $fantasia);
    $telefone_e = mysqli_real_escape_string($conn, $telefone);
    $user = (int)($_SESSION["user_nb_id"] ?? 0);

    // Verifica duplicidade comparando apenas os dígitos (aceita formatos com/sem pontuação)
    $existe = query("SELECT ss_f_nb_id FROM ss_fornecedor WHERE REPLACE(REPLACE(REPLACE(ss_f_tx_cnpj, '.', ''), '/', ''), '-', '') = '{$cnpj_e}' LIMIT 1");
    if ($existe && mysqli_num_rows($existe) > 0) {
        $id = (int)mysqli_fetch_assoc($existe)["ss_f_nb_id"];
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Já existe um fornecedor cadastrado com este CNPJ.", "id" => $id]);
        exit;
    }

    $res = query(
        "INSERT INTO ss_fornecedor (ss_f_tx_razao_social, ss_f_tx_nome_fantasia, ss_f_tx_cnpj, ss_f_tx_telefone, ss_f_tx_status, ss_f_nb_userCadastro, ss_f_tx_dataCadastro)
         VALUES ('{$razao_e}', '{$fantasia_e}', '{$cnpj_e}', '{$telefone_e}', 'ativo', {$user}, NOW())"
    );
    if (!$res) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Erro ao cadastrar fornecedor: " . ($GLOBALS["last_sql_error"] ?? "erro desconhecido")]);
        exit;
    }

    $id = mysqli_insert_id($conn);
    ob_clean();
    echo json_encode(["status" => "success", "message" => "Fornecedor cadastrado com sucesso!", "id" => $id, "razao" => $razao, "cnpj" => formatarCnpj($cnpjDigits)]);
    exit;
}

function nomeEmpresa($empresaId) {
    if (empty($empresaId)) {
        return "Matriz";
    }
    $e = carregar("empresa", (string)$empresaId);
    return !empty($e["empr_tx_nome"]) ? $e["empr_tx_nome"] : "Empresa ID {$empresaId}";
}

function saldoEstoquePorEmpresa($epi_id, $empresaId, $variacao = null) {
    global $conn;
    $condEmpresa = ($empresaId === null || $empresaId === 0)
        ? "(ss_e_nb_empresa_id IS NULL OR ss_e_nb_empresa_id = 0)"
        : "ss_e_nb_empresa_id = " . (int)$empresaId;
    $condVar = "";
    if ($variacao !== null && $variacao !== "") {
        $condVar = " AND ss_e_tx_variacao = '" . mysqli_real_escape_string($conn, $variacao) . "'";
    }
    $sql = "SELECT SUM(CASE WHEN ss_e_tx_tipo = 'entrada' THEN ss_e_nb_quantidade ELSE -ss_e_nb_quantidade END) AS saldo
            FROM ss_epi_estoque
            WHERE ss_e_nb_epi_id = " . (int)$epi_id . " AND {$condEmpresa} {$condVar}";
    $res = mysqli_query($conn, $sql);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return (int)($row["saldo"] ?? 0);
}

function obterSaldoEpiAjax() {
    $epi_id = (int)($_GET["epi_id"] ?? 0);
    $empresa_id = (int)($_GET["empresa_id"] ?? 0);
    $variacao = trim($_GET["variacao"] ?? "");
    $saldo = saldoEstoquePorEmpresa($epi_id, $empresa_id === 0 ? null : $empresa_id, $variacao);
    ob_clean();
    echo json_encode(["status" => "success", "saldo" => $saldo]);
    exit;
}

function transferirEstoqueAjax() {
    global $conn;

    $epi_id = (int)($_POST["epi_id"] ?? 0);
    $quantidade = (int)($_POST["quantidade"] ?? 0);
    $origem = (int)($_POST["empresa_origem"] ?? 0);
    $destino = (int)($_POST["empresa_destino"] ?? 0);
    $variacao = !empty($_POST["variacao"]) ? trim($_POST["variacao"]) : null;
    $motivo = trim($_POST["motivo"] ?? "");

    $origemClean = $origem === 0 ? null : $origem;
    $destinoClean = $destino === 0 ? null : $destino;

    if ($epi_id <= 0) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Selecione um EPI válido."]);
        exit;
    }
    if ($quantidade <= 0) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "A quantidade deve ser maior que zero."]);
        exit;
    }
    if ($origem === $destino) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "A empresa de origem e a de destino devem ser diferentes."]);
        exit;
    }

    $epi = carregar("ss_epi", $epi_id);
    if (empty($epi)) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "EPI não encontrado no cadastro."]);
        exit;
    }
    if (!empty($epi["ss_e_tx_variacoes"] ?? "") && empty($variacao)) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Este EPI possui variações cadastradas. Selecione a variação."]);
        exit;
    }

    $saldoOrigem = saldoEstoquePorEmpresa($epi_id, $origemClean, $variacao);
    if ($quantidade > $saldoOrigem) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Estoque insuficiente na origem. Saldo atual: {$saldoOrigem} unidade(s)."]);
        exit;
    }

    $nomeOrigem = nomeEmpresa($origemClean);
    $nomeDestino = nomeEmpresa($destinoClean);
    $sufixo = ($variacao !== null && $variacao !== "") ? " (Variação: {$variacao})" : "";
    $motivo_e = mysqli_real_escape_string($conn, $motivo);

    $descSaida = "Transferência de estoque: {$nomeOrigem} → {$nomeDestino}{$sufixo}" . ($motivo_e !== "" ? " - {$motivo_e}" : "");
    $descEntrada = "Transferência recebida de {$nomeOrigem}{$sufixo}" . ($motivo_e !== "" ? " - {$motivo_e}" : "");

    mysqli_begin_transaction($conn);
    $s1 = registrarMovimentacaoEstoque($epi_id, $quantidade, 'saida', $descSaida, null, null, '', null, null, $origemClean, null, null, $variacao);
    if (!$s1) {
        mysqli_rollback($conn);
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Erro ao registrar a saída na origem."]);
        exit;
    }
    $s2 = registrarMovimentacaoEstoque($epi_id, $quantidade, 'entrada', $descEntrada, null, null, '', null, null, $destinoClean, null, null, $variacao);
    if (!$s2) {
        mysqli_rollback($conn);
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Erro ao registrar a entrada no destino. Nenhum registro foi salvo."]);
        exit;
    }

    $user = (int)($_SESSION["user_nb_id"] ?? 0);
    $origemSql = $origemClean === null ? "NULL" : (int)$origemClean;
    $destinoSql = $destinoClean === null ? "NULL" : (int)$destinoClean;
    $variacaoSql = ($variacao !== null && $variacao !== "") ? "'" . mysqli_real_escape_string($conn, $variacao) . "'" : "NULL";
    $motivoSql = $motivo_e !== "" ? "'{$motivo_e}'" : "NULL";

    $r = query(
        "INSERT INTO ss_epi_transferencia (ss_t_nb_epi_id, ss_t_nb_quantidade, ss_t_nb_empresa_origem, ss_t_nb_empresa_destino, ss_t_tx_variacao, ss_t_tx_motivo, ss_t_tx_data, ss_t_nb_userCadastro)
         VALUES ({$epi_id}, {$quantidade}, {$origemSql}, {$destinoSql}, {$variacaoSql}, {$motivoSql}, NOW(), {$user})"
    );
    if (!$r) {
        mysqli_rollback($conn);
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Erro ao registrar o histórico da transferência."]);
        exit;
    }

    mysqli_commit($conn);

    $saldoOrigemNovo = saldoEstoquePorEmpresa($epi_id, $origemClean, $variacao);
    $saldoDestinoNovo = saldoEstoquePorEmpresa($epi_id, $destinoClean, $variacao);
    ob_clean();
    echo json_encode([
        "status" => "success",
        "message" => "Transferência realizada com sucesso: {$quantidade} unidade(s) de {$nomeOrigem} para {$nomeDestino}.",
        "saldo_origem" => $saldoOrigemNovo,
        "saldo_destino" => $saldoDestinoNovo
    ]);
    exit;
}

function lancarEstoque() {
    $epi_id     = (int)$_POST["epi_id"];
    $quantidade = (int)$_POST["quantidade"];
    $tipo       = $_POST["tipo"];
    $motivo     = $_POST["motivo"] ?? "";
    $valor_unitario = parseBrValor($_POST["valor_unitario"] ?? "");
    $valor_total = parseBrValor($_POST["valor_total"] ?? "");

    $empresa_id = isset($_POST["empresa_id"]) ? (int)$_POST["empresa_id"] : 0;
    if ($empresa_id === 0) {
        $empresa_id = null; // Matriz
    }

    $data_recebimento = !empty($_POST["data_recebimento"]) ? $_POST["data_recebimento"] : null;
    $chave_nf = !empty($_POST["chave_nf"]) ? $_POST["chave_nf"] : null;
    $fornecedor = !empty($_POST["fornecedor"]) ? $_POST["fornecedor"] : null;
    $validade_epi = !empty($_POST["validade_epi"]) ? $_POST["validade_epi"] : null;
    $variacao = !empty($_POST["variacao"]) ? trim($_POST["variacao"]) : null;

    if ($epi_id <= 0) {
        $_POST["errorFields"][] = "epi_id";
        set_status("ERRO: Selecione um EPI válido.");
        redireciona("movimentacao_estoque.php");
        exit;
    }

    if ($quantidade <= 0) {
        $_POST["errorFields"][] = "quantidade";
        set_status("ERRO: A quantidade deve ser maior que zero.");
        redireciona("movimentacao_estoque.php");
        exit;
    }

    // Se o EPI possui variações cadastradas, exige informar a variação
    $epi = carregar("ss_epi", $epi_id);
    if (empty($epi)) {
        set_status("ERRO: EPI não encontrado no cadastro.");
        redireciona("movimentacao_estoque.php");
        exit;
    }
    if (!empty($epi["ss_e_tx_variacoes"] ?? "") && empty($variacao)) {
        set_status("ERRO: Este EPI possui variações cadastradas. Selecione a variação (numeração/tamanho).");
        redireciona("movimentacao_estoque.php");
        exit;
    }

    if ($tipo === 'saida') {
        $saldoAtual = obterSaldoEstoque($epi_id, $empresa_id, false, $variacao);
        if ($quantidade > $saldoAtual) {
            $_POST["errorFields"][] = "quantidade";
            set_status("ERRO: Estoque insuficiente. Saldo atual: {$saldoAtual}.");
            redireciona("movimentacao_estoque.php");
            exit;
        }
    }

    $sucesso = registrarMovimentacaoEstoque($epi_id, $quantidade, $tipo, $motivo, $valor_unitario, $valor_total, "", $data_recebimento, $chave_nf, $empresa_id, $fornecedor, $validade_epi, $variacao);
    if ($sucesso) {
        set_status("Movimentação registrada com sucesso!");
    } else {
        set_status("ERRO ao registrar movimentação.");
    }

    redireciona("estoque_epi.php");
    exit;
}

function index() {
    cabecalho("Lançar Movimentação de Estoque");

    $sql = query("SELECT ss_e_nb_id, CONCAT(IFNULL(ss_e_tx_subgrupo, ''), ' - ', IFNULL(ss_e_tx_item, ''), ' - ', IFNULL(ss_e_tx_ca, 'N/A')) AS epi_nome, ss_e_tx_variacoes 
                  FROM ss_epi 
                  WHERE ss_e_tx_status = 'ativo' AND ss_e_tx_cadastro_tipo = 'estoque'
                  ORDER BY ss_e_tx_subgrupo ASC, ss_e_tx_item ASC");
    $epiOptions = ["" => "Selecione o EPI"];
    $epiVariacoesMap = [];
    if ($sql) {
        while ($row = mysqli_fetch_assoc($sql)) {
            $epiOptions[$row["ss_e_nb_id"]] = $row["epi_nome"];
            $epiVariacoesMap[(int)$row["ss_e_nb_id"]] = $row["ss_e_tx_variacoes"] ?? "";
        }
    }

    // Carregar todas as empresas ativas
    $sqlEmpresas = query("SELECT empr_nb_id, empr_tx_nome, empr_tx_cnpj FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
    $empresaOptions = ["" => "Selecione a Empresa"];
    if ($sqlEmpresas) {
        while ($rowEmp = mysqli_fetch_assoc($sqlEmpresas)) {
            $empresaOptions[$rowEmp["empr_nb_id"]] = $rowEmp["empr_tx_nome"] . " (CNPJ: " . $rowEmp["empr_tx_cnpj"] . ")";
        }
    }

    $empresasJsArr = [];
    foreach ($empresaOptions as $eid => $ename) {
        if (empty($eid)) continue;
        $cleanName = preg_replace('/ \(CNPJ: .+\)$/', '', $ename);
        $empresasJsArr[$eid] = $cleanName;
    }
    $jsEmpresas = json_encode($empresasJsArr);

    // Opções de empresas para transferência: a empresa do usuário logado primeiro, depois as filiais
    $userEmpresaId = (int)($_SESSION["user_nb_empresa"] ?? 0);
    $userEmpresaNome = "";
    if ($userEmpresaId > 0) {
        $eUser = carregar("empresa", (string)$userEmpresaId);
        $userEmpresaNome = $eUser["empr_tx_nome"] ?? "";
    }

    $transferEmpresas = [];
    if ($userEmpresaId > 0 && !empty($userEmpresaNome)) {
        $transferEmpresas[$userEmpresaId] = $userEmpresaNome . " (Minha Empresa)";
    } else {
        $transferEmpresas[0] = "Minha Empresa (Matriz)";
    }
    foreach ($empresasJsArr as $eid => $ename) {
        if ($eid == $userEmpresaId) continue;
        $transferEmpresas[$eid] = $ename;
    }
    $transferEmpresasHtml = "";
    foreach ($transferEmpresas as $tid => $tname) {
        $transferEmpresasHtml .= '<option value="' . $tid . '">' . htmlspecialchars($tname) . '</option>';
    }

    // Opções de EPI (para o formulário de transferência)
    $epiOptionsHtml = "";
    foreach ($epiOptions as $eid => $ename) {
        $epiOptionsHtml .= '<option value="' . $eid . '">' . htmlspecialchars($ename) . '</option>';
    }

    // Select de origem: empresa do usuário já selecionada; destino: opção vazia inicial
    $transferOrigemHtml = "";
    $transferDestinoHtml = '<option value="">Selecione o destino</option>';
    foreach ($transferEmpresas as $tid => $tname) {
        $selOrigem = ($tid == $userEmpresaId) ? " selected" : "";
        $transferOrigemHtml .= '<option value="' . $tid . '"' . $selOrigem . '>' . htmlspecialchars($tname) . '</option>';
        $transferDestinoHtml .= '<option value="' . $tid . '">' . htmlspecialchars($tname) . '</option>';
    }

    $campo_empresa  = combo("Empresa*", "empresa_id", $_POST["empresa_id"] ?? ($_SESSION["user_nb_empresa"] ?? "0"), 3, $empresaOptions, "id='empresa_id'");
    $campo_epi      = combo("EPI*", "epi_id", $_POST["epi_id"] ?? "", 4, $epiOptions);
    $campo_quant    = campo("Quantidade*", "quantidade", $_POST["quantidade"] ?? "", 2, "MASCARA_NUMERO");
    $campo_tipo     = combo("Tipo*", "tipo", $_POST["tipo"] ?? "entrada", 3, ["entrada" => "Entrada (Compra/Ajuste)", "saida" => "Saída (Descarte/Ajuste)"]);
    $campo_variacao = '
        <div class="col-sm-12 margin-bottom-5" id="container_variacao" style="display: none;">
            <div class="portlet light bordered" style="margin-bottom: 10px;">
                <div class="portlet-title" style="margin-bottom: 5px;">
                    <div class="caption font-dark">
                        <i class="fa fa-th-list font-dark"></i>
                        <span class="caption-subject bold uppercase">Quantidade por Variação (Numeração/Tamanho)</span>
                    </div>
                </div>
                <div class="portlet-body">
                    <table class="table table-bordered" style="max-width: 500px; margin-bottom: 5px;">
                        <thead>
                            <tr>
                                <th>Variação</th>
                                <th style="width: 160px; text-align: center;">Quantidade</th>
                            </tr>
                        </thead>
                        <tbody id="corpo_variacoes"></tbody>
                    </table>
                    <span class="help-block" style="font-size: 11px;">Informe a quantidade para cada variação. Ex.: bota 42 → 10, bota 44 → 5, bota 46 → 3. Cada variação com quantidade vira um item no lançamento.</span>
                </div>
            </div>
        </div>';

    $campo_motivo   = campo("Motivo/Observação", "motivo", $_POST["motivo"] ?? "", 4, "", "maxlength='255'");
    $campo_valor_unitario = campo("Valor Unitário", "valor_unitario", $_POST["valor_unitario"] ?? "", 4, "MASCARA_VALOR");
    $campo_valor_total    = campo("Valor Total", "valor_total", $_POST["valor_total"] ?? "", 4, "MASCARA_VALOR");

    $campo_data_receb     = campo_data("Data de Recebimento", "data_recebimento", $_POST["data_recebimento"] ?? "", 3);
    $campo_chave_nf       = campo("Chave NF", "chave_nf", $_POST["chave_nf"] ?? "", 3, "", "maxlength='100'");

    // Fornecedores (lista com busca por nome/CNPJ e botão + para cadastro rápido)
    $sqlFornecedores = query("SELECT ss_f_nb_id, ss_f_tx_razao_social, ss_f_tx_nome_fantasia, ss_f_tx_cnpj, ss_f_tx_telefone FROM ss_fornecedor WHERE ss_f_tx_status = 'ativo' ORDER BY ss_f_tx_razao_social ASC");
    $fornecedorOptionsHtml = '<option value="">Selecione o Fornecedor</option>';
    $fornecedorSelecionado = $_POST["fornecedor"] ?? "";
    if ($sqlFornecedores) {
        while ($rowF = mysqli_fetch_assoc($sqlFornecedores)) {
            $razaoF = htmlspecialchars($rowF["ss_f_tx_razao_social"]);
            $cnpjF = htmlspecialchars(formatarCnpj($rowF["ss_f_tx_cnpj"] ?? ""));
            $selF = ($rowF["ss_f_tx_razao_social"] === $fornecedorSelecionado) ? " selected" : "";
            $fornecedorOptionsHtml .= "<option value=\"{$razaoF}\" data-cnpj=\"{$cnpjF}\"{$selF}>{$razaoF}" . (!empty($rowF["ss_f_tx_cnpj"]) ? " - CNPJ: {$cnpjF}" : "") . "</option>";
        }
    }

    $campo_fornecedor = '
        <style>
            #fornecedor + .select2-container { width: auto !important; flex: 1; min-width: 0; }
            #fornecedor + .select2-container .select2-selection--single { height: 30px; }
            #fornecedor + .select2-container .select2-selection__rendered { line-height: 28px; padding-left: 8px; }
            #fornecedor + .select2-container .select2-selection__arrow { height: 28px; }
        </style>
        <div class="col-sm-6 margin-bottom-5 campo-fit-content">
            <label>Fornecedor</label>
            <div style="display: flex; align-items: center; gap: 6px;">
                <select name="fornecedor" id="fornecedor" class="form-control input-sm">
                    ' . $fornecedorOptionsHtml . '
                </select>
                <button type="button" class="btn btn-success btn-sm" id="btn_novo_fornecedor" title="Cadastrar novo fornecedor" style="white-space: nowrap; flex-shrink: 0;"><i class="fa fa-plus"></i> Novo</button>
            </div>
        </div>';
    $campo_validade_epi   = campo_data("Validade do EPI", "validade_epi", $_POST["validade_epi"] ?? "", 3);

    $buttons = [];
    $buttons[] = '<button type="button" class="btn btn-primary" id="btn_adicionar_item" onclick="adicionarItemALista()"><i class="fa fa-plus"></i> Adicionar Item à Lista</button>';
    $buttons[] = '<button type="button" class="btn btn-default" onclick="confirmarVoltar()"><i class="fa fa-arrow-left"></i> Voltar</button>';

    echo abre_form("Movimentação de Estoque (Entrada / Saída)");
    echo linha_form([$campo_empresa, $campo_epi, $campo_quant, $campo_tipo]);
    echo linha_form([$campo_variacao]);
    echo linha_form([$campo_motivo, $campo_valor_unitario, $campo_valor_total]);
    echo linha_form([$campo_data_receb, $campo_chave_nf, $campo_validade_epi]);
    echo linha_form([$campo_fornecedor]);
    echo fecha_form($buttons);

    echo '
    <!-- Modal Novo Fornecedor -->
    <div class="modal fade" id="modal_novo_fornecedor" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 11000;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-truck"></i> Novo Fornecedor</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="control-label">Razão Social*</label>
                        <input type="text" class="form-control input-sm" id="nf_razao_social" maxlength="255" placeholder="Ex: Distribuidora de EPIs LTDA">
                    </div>
                    <div class="form-group">
                        <label class="control-label">Nome Fantasia</label>
                        <input type="text" class="form-control input-sm" id="nf_nome_fantasia" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label class="control-label">CNPJ*</label>
                        <input type="text" class="form-control input-sm" id="nf_cnpj" maxlength="18" placeholder="00.000.000/0000-00">
                    </div>
                    <div class="form-group">
                        <label class="control-label">Telefone</label>
                        <input type="text" class="form-control input-sm" id="nf_telefone" maxlength="15" placeholder="(00) 00000-0000">
                    </div>
                    <span class="help-block" style="font-size: 11px; margin-top: 0;">Os campos marcados com * são obrigatórios.</span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btn_salvar_fornecedor"><i class="fa fa-check"></i> Salvar Fornecedor</button>
                </div>
            </div>
        </div>
    </div>';

    echo '
    <!-- Transferência de Estoque entre Empresas -->
    <div class="portlet light bordered" style="margin-top: 20px;">
        <div class="portlet-title">
            <div class="caption font-dark">
                <i class="fa fa-exchange font-dark"></i>
                <span class="caption-subject bold uppercase">Transferência de Estoque entre Empresas</span>
            </div>
        </div>
        <div class="portlet-body">
            <form id="form_transferencia" onsubmit="return false;">
                <div class="row">
                    <div class="col-sm-3 margin-bottom-5">
                        <label class="control-label">EPI*</label>
                        <select name="transf_epi_id" id="transf_epi_id" class="form-control input-sm">
                            ' . $epiOptionsHtml . '
                        </select>
                    </div>
                    <div class="col-sm-2 margin-bottom-5">
                        <label class="control-label">Quantidade*</label>
                        <input type="number" name="transf_quantidade" id="transf_quantidade" class="form-control input-sm" min="1" step="1" style="height: 30px;">
                    </div>
                    <div class="col-sm-3 margin-bottom-5">
                        <label class="control-label">Empresa Origem*</label>
                        <select name="transf_empresa_origem" id="transf_empresa_origem" class="form-control input-sm">
                            ' . $transferOrigemHtml . '
                        </select>
                    </div>
                    <div class="col-sm-3 margin-bottom-5">
                        <label class="control-label">Empresa Destino*</label>
                        <select name="transf_empresa_destino" id="transf_empresa_destino" class="form-control input-sm">
                            ' . $transferDestinoHtml . '
                        </select>
                    </div>
                    <div class="col-sm-1 margin-bottom-5" style="padding-top: 23px;">
                        <button type="button" class="btn btn-warning btn-sm" id="btn_transferir" title="Transferir estoque" style="width: 100%;"><i class="fa fa-exchange"></i> Transferir</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-4 margin-bottom-5">
                        <label class="control-label">Variação (se o EPI tiver)</label>
                        <select name="transf_variacao" id="transf_variacao" class="form-control input-sm">
                            <option value="">Selecione a variação</option>
                        </select>
                    </div>
                    <div class="col-sm-4 margin-bottom-5">
                        <label class="control-label">Saldo na Origem</label>
                        <div id="transf_saldo_origem" class="form-control input-sm" style="font-weight: bold; color: #31708f; height: 30px; padding-top: 5px;">-</div>
                    </div>
                    <div class="col-sm-4 margin-bottom-5">
                        <label class="control-label">Motivo / Observação</label>
                        <input type="text" name="transf_motivo" id="transf_motivo" class="form-control input-sm" maxlength="255" style="height: 30px;">
                    </div>
                </div>
            </form>
        </div>
    </div>';

    echo '
    <!-- Lançamentos Adicionados -->
    <div class="portlet light bordered" id="container_listas_wrapper" style="margin-top: 20px; display: none;">
        <div class="portlet-title">
            <div class="caption font-green-haze">
                <i class="fa fa-list font-green-haze"></i>
                <span class="caption-subject bold uppercase">Lançamentos Adicionados</span>
            </div>
        </div>
        <div class="portlet-body">
            <div id="container_listas_filiais"></div>
            <div id="container_acoes_globais" style="margin-top: 20px; display: none;">
                <div class="row">
                    <div class="col-md-12 text-right">
                        <button type="button" class="btn btn-success btn-lg" onclick="salvarTodasAsListas()"><i class="fa fa-save"></i> Salvar Todos os Lançamentos</button>
                    </div>
                </div>
            </div>
        </div>
    </div>';

    ?>
    <script>
    var itensLote = {};
    var empresasNomes = <?php echo $jsEmpresas; ?>;
    var epiVariacoes = <?php echo json_encode($epiVariacoesMap); ?>;

    $(document).ready(function() {
        if (typeof $.fn.select2 === 'function') {
            $.fn.select2.defaults.set('theme', 'bootstrap');
            $('select[name="epi_id"], select[name="empresa_id"]').select2();
            $('select[name="fornecedor"]').select2({
                placeholder: 'Busque por nome ou CNPJ...',
                allowClear: true,
                matcher: function(params, data) {
                    if (!params.term || params.term.trim() === '') {
                        return data;
                    }
                    var term = params.term.trim().toLowerCase();
                    var termClean = term.replace(/[^0-9a-z]/g, '');
                    var text = (data.text || '').toLowerCase();
                    var cnpj = data.element ? (data.element.getAttribute('data-cnpj') || '') : '';
                    var cnpjClean = cnpj.toLowerCase().replace(/[^0-9a-z]/g, '');
                    if (text.indexOf(term) > -1 || cnpj.toLowerCase().indexOf(term) > -1 || cnpjClean.indexOf(termClean) > -1) {
                        return data;
                    }
                    return null;
                }
            });
        }

        // Cadastro rápido de fornecedor
        $('#btn_novo_fornecedor').on('click', function() {
            $('#modal_novo_fornecedor').modal('show');
            $('#nf_razao_social').focus();
        });

        if (typeof $.fn.inputmask === 'function') {
            $('#nf_cnpj').inputmask({ mask: ['99.999.999/9999-99'], placeholder: '00.000.000/000-00' });
            $('#nf_telefone').inputmask({ mask: ['(99) 9999-9999', '(99) 99999-9999'], placeholder: '' });
        }

        $('#btn_salvar_fornecedor').on('click', function() {
            var razao = $('#nf_razao_social').val().trim();
            var cnpj = $('#nf_cnpj').val().trim();
            if (!razao) {
                alert('Informe a Razão Social.');
                $('#nf_razao_social').focus();
                return;
            }
            if (!cnpj) {
                alert('Informe o CNPJ.');
                $('#nf_cnpj').focus();
                return;
            }

            var $btn = $(this).prop('disabled', true);

            $.ajax({
                url: 'movimentacao_estoque.php?acao=salvarFornecedorAjax',
                type: 'POST',
                data: {
                    razao_social: razao,
                    nome_fantasia: $('#nf_nome_fantasia').val().trim(),
                    cnpj: cnpj,
                    telefone: $('#nf_telefone').val().trim()
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp.status === 'success') {
                        var novoOption = new Option(resp.razao, resp.razao, false, true);
                        novoOption.setAttribute('data-cnpj', resp.cnpj || '');
                        $('#fornecedor').append(novoOption).trigger('change');
                        $('#nf_razao_social').val('');
                        $('#nf_nome_fantasia').val('');
                        $('#nf_cnpj').val('');
                        $('#nf_telefone').val('');
                        $('#modal_novo_fornecedor').modal('hide');
                        alert('Fornecedor cadastrado com sucesso!');
                    } else {
                        alert(resp.message || 'Erro ao cadastrar fornecedor.');
                    }
                },
                error: function(xhr) {
                    var msg = '';
                    try {
                        var parsed = JSON.parse(xhr.responseText);
                        msg = parsed.message || '';
                    } catch (e) {
                        msg = (xhr.responseText || '').substring(0, 500);
                    }
                    alert('Erro na comunicação com o servidor: ' + msg);
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });

        function atualizarVariacoes() {
            var epiId = $('select[name="epi_id"]').val();
            var variacoesStr = epiVariacoes[epiId] || '';
            var lista = variacoesStr.split(',').map(function(v) { return v.trim(); }).filter(Boolean);
            var corpo = $('#corpo_variacoes');
            corpo.empty();
            
            if (lista.length > 0) {
                lista.forEach(function(v) {
                    corpo.append(
                        '<tr>' +
                            '<td style="vertical-align: middle;"><strong>' + v + '</strong></td>' +
                            '<td style="text-align: center;"><input type="number" min="0" step="1" class="form-control input-sm qtd_variacao" data-variacao="' + v + '" value="0" style="text-align: center;"></td>' +
                        '</tr>'
                    );
                });
                $('#container_variacao').show();
                $('input[name="quantidade"]').prop('disabled', true).val('');
            } else {
                $('#container_variacao').hide();
                $('input[name="quantidade"]').prop('disabled', false);
            }
        }

        $(document).on('input change', '.qtd_variacao', function() {
            var total = 0;
            $('.qtd_variacao').each(function() {
                total += parseInt($(this).val(), 10) || 0;
            });
            $('#quantidade').val(total > 0 ? total : '');
            calcularTotal();
        });

        $('select[name="epi_id"]').on('change', atualizarVariacoes);

        function calcularTotal() {
            let quant = parseInt($('#quantidade').val(), 10) || 0;
            let unit = parseBrFloat($('#valor_unitario').val());
            if (quant > 0 && unit > 0) {
                let total = quant * unit;
                let totalStr = formatBrFloat(total);
                
                if (typeof $('#valor_total').maskMoney === 'function') {
                    $('#valor_total').maskMoney('mask', total);
                } else {
                    $('#valor_total').val(totalStr);
                }
            }
        }

        function parseBrFloat(str) {
            if (!str) return 0;
            let clean = str.replace(/R\$\s?/, '').replace(/\s/g, '');
            if (clean.indexOf(',') !== -1) {
                clean = clean.replace(/\./g, '').replace(/,/g, '.');
            }
            return parseFloat(clean) || 0;
        }

        function formatBrFloat(num) {
            return num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        $('#quantidade, #valor_unitario').on('keyup change input', calcularTotal);
    });

    function destacarItemEpi(nome) {
        // Formato esperado: Item - Sub - CA ou Item / Sub / Grupo (CA: X)
        var partes = nome.split(' - ');
        if (partes.length > 1) {
            return '<strong>' + partes[0] + '</strong> <small class=\"text-muted\">- ' + partes.slice(1).join(' - ') + '</small>';
        }
        partes = nome.split(' / ');
        if (partes.length > 1) {
            return '<strong>' + partes[0] + '</strong> <small class=\"text-muted\">/ ' + partes.slice(1).join(' / ') + '</small>';
        }
        return '<strong>' + nome + '</strong>';
    }

    function adicionarItemALista() {
        var epiSelect = $('select[name="epi_id"]');
        var epiId = epiSelect.val();
        var epiNome = epiSelect.find('option:selected').text();
        var tipo = $('select[name="tipo"]').val();
        var motivo = $('#motivo').val();
        var valorUnitario = $('#valor_unitario').val();
        var valorTotal = $('#valor_total').val();
        var dataRecebimento = $('#data_recebimento').val();
        var chaveNf = $('#chave_nf').val();
        var fornecedor = $('#fornecedor').val();
        var validadeEpi = $('#validade_epi').val();
        
        var empresaId = $('select[name="empresa_id"]').val() || '0';
        
        if (!epiId) {
            alert('Por favor, selecione um EPI.');
            return;
        }
        if (!empresaId) {
            alert('Por favor, selecione a Empresa.');
            return;
        }
        
        var variacoesStr = epiVariacoes[epiId] || '';
        var listaVariacoes = variacoesStr.split(',').map(function(v) { return v.trim(); }).filter(Boolean);
        
        var itensParaAdicionar = [];
        
        if (listaVariacoes.length > 0) {
            var total = 0;
            $('#corpo_variacoes .qtd_variacao').each(function() {
                var q = parseInt($(this).val(), 10) || 0;
                if (q > 0) {
                    total += q;
                    itensParaAdicionar.push({
                        variacao: $(this).attr('data-variacao'),
                        quantidade: q
                    });
                }
            });
            if (total <= 0) {
                alert('Informe a quantidade de pelo menos uma variação.');
                return;
            }
        } else {
            var quantidade = parseInt($('#quantidade').val(), 10) || 0;
            if (quantidade <= 0) {
                alert('Por favor, informe uma quantidade maior que zero.');
                return;
            }
            itensParaAdicionar.push({ variacao: '', quantidade: quantidade });
        }
        
        itensParaAdicionar.forEach(function(iva) {
            var item = {
                unique_id: new Date().getTime() + '_' + Math.random().toString(36).substr(2, 5),
                epi_id: epiId,
                epi_nome: epiNome,
                quantidade: iva.quantidade,
                tipo: tipo,
                variacao: iva.variacao,
                motivo: motivo,
                valor_unitario: valorUnitario,
                valor_total: valorTotal,
                data_recebimento: dataRecebimento,
                chave_nf: chaveNf,
                fornecedor: fornecedor,
                validade_epi: validadeEpi,
                empresa_id: empresaId
            };
            if (!itensLote[empresaId]) {
                itensLote[empresaId] = [];
            }
            itensLote[empresaId].push(item);
        });
        
        epiSelect.val('').trigger('change');
        $('#quantidade').val('');
        $('#valor_unitario').val('');
        $('#valor_total').val('');
        $('#motivo').val('');
        $('#validade_epi').val('');
        $('#corpo_variacoes .qtd_variacao').val('0');
        
        desenharListas();
    }

    function desenharListas() {
        var container = $('#container_listas_filiais');
        container.empty();
        
        var totalListas = 0;
        
        for (var empresaId in itensLote) {
            var itens = itensLote[empresaId];
            if (itens.length === 0) continue;
            
            totalListas++;
            var empresaNome = empresasNomes[empresaId] || ('Filial ID ' + empresaId);
            
            var panelHtml = '<div class="portlet box blue-hoki" id="panel_empresa_' + empresaId + '" style="margin-bottom: 20px;">' +
                '<div class="portlet-title">' +
                    '<div class="caption">' +
                        '<i class="fa fa-shopping-cart"></i> Lançamentos para: <strong>' + empresaNome + '</strong> (' + itens.length + ' item(ns))' +
                    '</div>' +
                    '<div class="actions" style="display: inline-block; float: right; margin-top: 4px;">' +
                        '<button type="button" class="btn btn-default btn-sm" style="background-color: #fff; color: #333;" onclick="salvarListaEmpresa(\'' + empresaId + '\')"><i class="fa fa-save"></i> Salvar Esta Empresa</button>' +
                    '</div>' +
                '</div>' +
                '<div class="portlet-body">' +
                    '<div class="table-responsive">' +
                        '<table class="table table-striped table-bordered table-hover">' +
                        '<thead>' +
                            '<tr>' +
                                '<th>EPI</th>' +
                                '<th style="text-align: center;">Operação</th>' +
                                '<th style="text-align: center; width: 80px;">Qtd</th>' +
                                '<th style="width: 100px;">Variação</th>' +
                                '<th>Fornecedor</th>' +
                                '<th>NF / Recebimento / Validade</th>' +
                                '<th>Valor (Unit/Total)</th>' +
                                '<th>Motivo</th>' +
                                '<th style="width: 50px; text-align: center;">Ações</th>' +
                            '</tr>' +
                        '</thead>' +
                            '<tbody>';
            
            for (var i = 0; i < itens.length; i++) {
                var it = itens[i];
                var badgeTipo = it.tipo === 'entrada'
                    ? '<span class="label label-sm label-success">Entrada</span>'
                    : '<span class="label label-sm label-danger">Saída</span>';
                    
                panelHtml += '<tr id="row_item_' + it.unique_id + '">' +
                                '<td style="vertical-align: middle;">' + destacarItemEpi(it.epi_nome) + '</td>' +
                                '<td style="text-align: center; vertical-align: middle;">' + badgeTipo + '</td>' +
                                '<td style="text-align: center; font-weight: bold; vertical-align: middle;">' + it.quantidade + '</td>' +
                                '<td style="vertical-align: middle;">' + (it.variacao ? '<span class="label label-info">' + it.variacao + '</span>' : '<span class="text-muted">-</span>') + '</td>' +
                                '<td style="vertical-align: middle;">' + (it.fornecedor || '-') + '</td>' +
                                '<td style="vertical-align: middle;">NF: ' + (it.chave_nf || '-') + '<br><small class="text-muted">receb: ' + (it.data_recebimento || '-') + '</small><br><small class="text-muted">validade: ' + (it.validade_epi || '-') + '</small></td>' +
                                '<td style="vertical-align: middle;">U: ' + (it.valor_unitario || '-') + '<br>T: ' + (it.valor_total || '-') + '</td>' +
                                '<td style="font-style: italic; vertical-align: middle;">' + (it.motivo || '-') + '</td>' +
                                '<td style="text-align: center; vertical-align: middle;">' +
                                    '<button type="button" class="btn btn-danger btn-xs" onclick="removerItem(\'' + empresaId + '\', \'' + it.unique_id + '\')"><i class="fa fa-trash"></i></button>' +
                                '</td>' +
                            '</tr>';
            }
            
            panelHtml += '</tbody>' +
                        '</table>' +
                    '</div>' +
                '</div>' +
            '</div>';
            
            container.append(panelHtml);
        }
        
        if (totalListas > 0) {
            $('#container_acoes_globais').show();
            $('#container_listas_wrapper').show();
        } else {
            $('#container_acoes_globais').hide();
            $('#container_listas_wrapper').hide();
        }
    }

    function removerItem(empresaId, uniqueId) {
        if (!itensLote[empresaId]) return;
        itensLote[empresaId] = itensLote[empresaId].filter(function(item) {
            return item.unique_id !== uniqueId;
        });
        desenharListas();
    }

    function salvarListaEmpresa(empresaId) {
        var itens = itensLote[empresaId];
        if (!itens || itens.length === 0) return;
        
        if (!confirm('Deseja salvar os lançamentos desta empresa/filial?')) return;
        
        enviarLotesAjax(itens, function(idsSalvos) {
            itensLote[empresaId] = itensLote[empresaId].filter(function(item) {
                return idsSalvos.indexOf(item.unique_id) === -1;
            });
            desenharListas();
        });
    }

    function salvarTodasAsListas() {
        var todosItens = [];
        for (var empresaId in itensLote) {
            todosItens = todosItens.concat(itensLote[empresaId]);
        }
        if (todosItens.length === 0) return;
        
        if (!confirm('Deseja salvar todos os lançamentos de todas as empresas/filiais?')) return;
        
        enviarLotesAjax(todosItens, function(idsSalvos) {
            for (var empId in itensLote) {
                itensLote[empId] = itensLote[empId].filter(function(item) {
                    return idsSalvos.indexOf(item.unique_id) === -1;
                });
            }
            desenharListas();
        });
    }

    function enviarLotesAjax(lotes, callbackSucesso) {
        $.ajax({
            url: 'movimentacao_estoque.php?acao=lancarEstoqueLoteAjax',
            type: 'POST',
            data: { lotes: JSON.stringify(lotes) },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' || response.status === 'partial') {
                    var msg = response.sucessos > 0
                        ? 'Lançamentos salvos com sucesso!'
                        : 'Nenhum lançamento foi salvo.';
                    if (response.erros && response.erros.length > 0) {
                        msg += '\n\nFalhas:\n' + response.erros.join('\n');
                    }
                    alert(msg);
                    callbackSucesso(response.ids || []);
                } else {
                    alert('Erro ao salvar lançamentos: ' + (response.message || 'Resposta inválida do servidor.'));
                }
            },
            error: function(xhr) {
                var msg = '';
                try {
                    var parsed = JSON.parse(xhr.responseText);
                    msg = parsed.message || '';
                } catch (e) {
                    msg = (xhr.responseText || '').substring(0, 500);
                }
                alert('Erro na comunicação com o servidor: ' + msg);
            }
        });
    }

    function confirmarVoltar() {
        var temItens = false;
        for (var empId in itensLote) {
            if (itensLote[empId].length > 0) {
                temItens = true;
                break;
            }
        }
        
        if (temItens) {
            if (confirm('Você possui itens nas listas que ainda não foram salvos. Deseja realmente sair sem salvar?')) {
                window.location.href = 'estoque_epi.php';
            }
        } else {
            window.location.href = 'estoque_epi.php';
        }
    }

    // ===== Transferência de estoque entre empresas =====
    (function() {
        if (typeof $.fn.select2 === 'function') {
            $('#transf_epi_id').select2();
            $('#transf_empresa_origem, #transf_empresa_destino').select2();
        }

        function atualizarVariacoesTransferencia() {
            var epiId = $('#transf_epi_id').val();
            var variacoesStr = epiVariacoes[epiId] || '';
            var lista = variacoesStr.split(',').map(function(v) { return v.trim(); }).filter(Boolean);
            var sel = $('#transf_variacao');
            sel.empty();
            if (lista.length === 0) {
                sel.append('<option value="">Sem variações</option>');
            } else {
                sel.append('<option value="">Selecione a variação</option>');
                lista.forEach(function(v) {
                    sel.append('<option value="' + v + '">' + v + '</option>');
                });
            }
            atualizarSaldoOrigem();
        }

        function atualizarSaldoOrigem() {
            var epiId = $('#transf_epi_id').val();
            var origem = $('#transf_empresa_origem').val();
            var variacao = $('#transf_variacao').val();
            if (!epiId || origem === undefined || origem === null || origem === '') {
                $('#transf_saldo_origem').text('-');
                return;
            }
            $.get('movimentacao_estoque.php?acao=obterSaldoEpiAjax', { epi_id: epiId, empresa_id: origem, variacao: variacao }, function(resp) {
                if (resp && resp.status === 'success') {
                    $('#transf_saldo_origem').text(resp.saldo + ' unidade(s)');
                } else {
                    $('#transf_saldo_origem').text('-');
                }
            }, 'json');
        }

        $('#transf_epi_id').on('change', atualizarVariacoesTransferencia);
        $('#transf_variacao').on('change', atualizarSaldoOrigem);
        $('#transf_empresa_origem').on('change', atualizarSaldoOrigem);

        $('#btn_transferir').on('click', function() {
            var epiId = $('#transf_epi_id').val();
            var quantidade = parseInt($('#transf_quantidade').val(), 10);
            var origem = $('#transf_empresa_origem').val();
            var destino = $('#transf_empresa_destino').val();

            if (!epiId) { alert('Selecione o EPI.'); return; }
            if (!quantidade || quantidade <= 0) { alert('Informe uma quantidade válida.'); return; }
            if (origem === undefined || origem === null || origem === '') { alert('Selecione a empresa de origem.'); return; }
            if (destino === undefined || destino === null || destino === '') { alert('Selecione a empresa de destino.'); return; }
            if (origem === destino) { alert('A empresa de origem e a de destino devem ser diferentes.'); return; }

            var $btn = $(this).prop('disabled', true);

            $.ajax({
                url: 'movimentacao_estoque.php?acao=transferirEstoqueAjax',
                type: 'POST',
                data: {
                    epi_id: epiId,
                    quantidade: quantidade,
                    empresa_origem: origem,
                    empresa_destino: destino,
                    variacao: $('#transf_variacao').val(),
                    motivo: $('#transf_motivo').val()
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp.status === 'success') {
                        alert(resp.message);
                        $('#transf_quantidade').val('');
                        $('#transf_motivo').val('');
                        atualizarSaldoOrigem();
                        if (typeof consultarRegistros === 'function') {
                            consultarRegistros();
                        }
                    } else {
                        alert(resp.message || 'Erro ao transferir estoque.');
                        if (resp.saldo_origem !== undefined) {
                            $('#transf_saldo_origem').text(resp.saldo_origem + ' unidade(s)');
                        }
                    }
                },
                error: function(xhr) {
                    var msg = '';
                    try {
                        var parsed = JSON.parse(xhr.responseText);
                        msg = parsed.message || '';
                    } catch (e) {
                        msg = (xhr.responseText || '').substring(0, 500);
                    }
                    alert('Erro na comunicação com o servidor: ' + msg);
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
    })();
    </script>

    <?php
    // ===== Histórico de Transferências =====
    $transfFields = [
        "CÓDIGO"     => "ss_t_nb_id",
        "EPI"        => "epi_nome",
        "QTD"        => "ss_t_nb_quantidade",
        "ORIGEM"     => "origem",
        "DESTINO"    => "destino",
        "VARIAÇÃO"   => "variacao",
        "DATA"       => "data_transf"
    ];

    $transfBusca = [
        "busca_transf_codigo"      => "t.ss_t_nb_id",
        "busca_transf_epi_like"    => "CONCAT(IFNULL(epi.ss_e_tx_subgrupo, ''), ' / ', IFNULL(epi.ss_e_tx_item, ''))",
        "busca_transf_origem"      => "COALESCE(t.ss_t_nb_empresa_origem, 0)",
        "busca_transf_destino"     => "COALESCE(t.ss_t_nb_empresa_destino, 0)"
    ];

    $transfQuery = "SELECT * FROM (
                        SELECT t.ss_t_nb_id,
                               CONCAT('<strong>', IFNULL(epi.ss_e_tx_subgrupo, ''), '</strong> <small class=\"text-muted\">/ ', IFNULL(epi.ss_e_tx_item, ''), '</small>') AS epi_nome,
                               t.ss_t_nb_quantidade,
                               COALESCE(o.empr_tx_nome, 'Matriz') AS origem,
                               COALESCE(d.empr_tx_nome, 'Matriz') AS destino,
                               IFNULL(t.ss_t_tx_variacao, '-') AS variacao,
                               DATE_FORMAT(t.ss_t_tx_data, '%d/%m/%Y %H:%i') AS data_transf
                        FROM ss_epi_transferencia t
                        JOIN ss_epi epi ON epi.ss_e_nb_id = t.ss_t_nb_epi_id
                        LEFT JOIN empresa o ON o.empr_nb_id = t.ss_t_nb_empresa_origem
                        LEFT JOIN empresa d ON d.empr_nb_id = t.ss_t_nb_empresa_destino
                        ORDER BY t.ss_t_nb_id DESC
                    ) AS sub";

    $filtros_transferencia = '
        <form name="contex_form" method="post" autocomplete="off">
            <div class="row">
                <div class="col-sm-2 margin-bottom-5">
                    <input type="text" name="busca_transf_codigo" value="' . htmlspecialchars($_POST["busca_transf_codigo"] ?? "") . '" class="form-control input-sm" placeholder="Código">
                </div>
                <div class="col-sm-3 margin-bottom-5">
                    <input type="text" name="busca_transf_epi_like" value="' . htmlspecialchars($_POST["busca_transf_epi_like"] ?? "") . '" class="form-control input-sm" placeholder="Buscar EPI...">
                </div>
                <div class="col-sm-3 margin-bottom-5">
                    <select name="busca_transf_origem" class="form-control input-sm">
                        <option value="">Origem: Todas</option>
                        ' . $transferEmpresasHtml . '
                    </select>
                </div>
                <div class="col-sm-3 margin-bottom-5">
                    <select name="busca_transf_destino" class="form-control input-sm">
                        <option value="">Destino: Todos</option>
                        ' . $transferEmpresasHtml . '
                    </select>
                </div>
                <div class="col-sm-1 margin-bottom-5">
                    <button type="submit" name="acao" value="index" class="btn btn-sm btn-default" style="width: 100%;">Buscar</button>
                </div>
            </div>
        </form>';

    echo '
    <!-- Histórico de Transferências -->
    <div class="portlet light bordered" style="margin-top: 20px;">
        <div class="portlet-title">
            <div class="caption font-dark">
                <i class="fa fa-history font-dark"></i>
                <span class="caption-subject bold uppercase">Histórico de Transferências</span>
            </div>
        </div>
        <div class="portlet-body">
            ' . $filtros_transferencia . '
            ' . gridDinamico("tabelaTransferencias", $transfFields, $transfBusca, $transfQuery) . '
        </div>
    </div>';

    rodape();
}
