<?php
ob_start();
/* Modo debug
    ini_set("display_errors", 1);
    error_reporting(E_ALL);
//*/

include "conecta.php";

function get_saldo() {
    $epi_id = (int)($_GET['epi_id'] ?? 0);
    $empresa_id = (int)($_GET['empresa_id'] ?? 0);
    ob_clean();
    echo obterSaldoEstoque($epi_id, $empresa_id);
    exit;
}

function obter_colaboradores_empresa() {
    $empId = $_GET["empresa_id"] ?? "";
    $cond = "";
    if ($empId === "" || $empId === "0" || $empId === "sem_filial") {
        $cond = " AND (e.enti_nb_empresa IS NULL OR e.enti_nb_empresa = 0)";
    } else {
        $cond = " AND e.enti_nb_empresa = " . (int)$empId;
    }
    $sqlColabs = query("SELECT e.enti_nb_id, e.enti_tx_nome, e.enti_tx_matricula 
                        FROM entidade e 
                        LEFT JOIN operacao o ON e.enti_tx_tipoOperacao = o.oper_nb_id
                        WHERE e.enti_tx_status = 'ativo' 
                          AND COALESCE(o.oper_tx_nome, '') <> 'Diretor'
                          {$cond} 
                        ORDER BY e.enti_tx_nome ASC");
    $list = [];
    if ($sqlColabs) {
        while ($row = mysqli_fetch_assoc($sqlColabs)) {
            $list[] = [
                "id" => $row["enti_nb_id"],
                "nome" => $row["enti_tx_nome"] . ($row["enti_tx_matricula"] ? " (Matrícula: " . $row["enti_tx_matricula"] . ")" : "")
            ];
        }
    }
    ob_clean();
    echo json_encode($list);
    exit;
}

function obterSaldosEpiFiliaisAjax() {
    $epi_id = isset($_GET["epi_id"]) ? (int)$_GET["epi_id"] : 0;
    if ($epi_id <= 0) {
        ob_clean();
        echo json_encode([]);
        exit;
    }
    
    $user_empresa = !empty($_SESSION["user_nb_empresa"]) ? (int)$_SESSION["user_nb_empresa"] : 0;
    
    $sql = query("
        SELECT 
            IFNULL(est.ss_e_nb_empresa_id, 0) AS empresa_id,
            IFNULL(SUM(CASE WHEN est.ss_e_tx_tipo = 'entrada' THEN est.ss_e_nb_quantidade ELSE -est.ss_e_nb_quantidade END), 0) AS saldo
        FROM ss_epi_estoque est
        WHERE est.ss_e_nb_epi_id = {$epi_id}
        GROUP BY empresa_id
        HAVING saldo > 0
    ");
    
    $saldos = [];
    
    $sqlNomes = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo'");
    $nomes = [];
    if ($sqlNomes) {
        while ($row = mysqli_fetch_assoc($sqlNomes)) {
            $nomes[(int)$row["empr_nb_id"]] = $row["empr_tx_nome"];
        }
    }
    if ($user_empresa > 0 && !isset($nomes[$user_empresa])) {
        $nomes[$user_empresa] = "Matriz";
    }
    if (!isset($nomes[0])) {
        $nomes[0] = "Matriz";
    }
    
    $tempSaldos = [];
    if ($sql) {
        while ($row = mysqli_fetch_assoc($sql)) {
            $emprId = (int)$row["empresa_id"];
            if ($emprId == 0 || $emprId == $user_empresa) {
                $emprId = $user_empresa;
            }
            if (!isset($tempSaldos[$emprId])) {
                $tempSaldos[$emprId] = 0;
            }
            $tempSaldos[$emprId] += (int)$row["saldo"];
        }
    }
    
    foreach ($tempSaldos as $emprId => $saldo) {
        if ($saldo <= 0) continue;
        $nome = $nomes[$emprId] ?? "Filial ID " . $emprId;
        $saldos[] = [
            "empresa_id" => $emprId,
            "empresa_nome" => $nome,
            "saldo" => $saldo
        ];
    }
    
    ob_clean();
    echo json_encode($saldos);
    exit;
}

function cadastrarEntregaLoteAjax() {
    $lotes = json_decode($_POST["lotes"] ?? "[]", true);
    $colaborador_id = (int)($_POST["colaborador_id"] ?? 0);
    $data_entrega = $_POST["data_entrega"];
    
    if (empty($data_entrega)) {
        echo json_encode(["status" => "error", "message" => "Data de entrega inválida."]);
        exit;
    }
    
    $sucessos = 0;
    $erros = [];
    
    $userCadastro = $_SESSION["user_nb_id"] ?? 0;
    $dataCadastro = date("Y-m-d H:i:s");
    
    $inserted_ids_by_colab = [];
    foreach ($lotes as $item) {
        $epi_id = (int)$item["epi_id"];
        $quantidade = (int)$item["quantidade"];
        $status = $item["status"] ?? "ativo";
        $assinatura = $item["assinatura"] ?? "";
        $foto = $item["foto"] ?? "";
        $empresa_id = !empty($item["empresa_id"]) ? (int)$item["empresa_id"] : null;
        if ($empresa_id === 0) {
            $empresa_id = null; // Matriz
        }
        
        $item_colaborador_id = !empty($item["colaborador_id"]) ? (int)$item["colaborador_id"] : $colaborador_id;
        if ($item_colaborador_id <= 0) {
            $erros[] = "Colaborador inválido para o item do EPI ID {$epi_id}.";
            continue;
        }
        
        $import_de = (isset($item["import_de"]) && $item["import_de"] !== "") ? (int)$item["import_de"] : null;
        if ($import_de === 0) {
            $import_de_clean = null; // Matriz
        } else {
            $import_de_clean = $import_de;
        }
        
        // 1. Process inter-branch stock transfer if specified
        if ($import_de !== null) {
            $saldoSource = obterSaldoEstoque($epi_id, $import_de_clean);
            if ($quantidade > $saldoSource) {
                $erros[] = "Transferência falhou: estoque insuficiente na origem para o EPI ID {$epi_id}. Saldo na origem: {$saldoSource}.";
                continue;
            }
            
            $descSaida = "Transferência inter-filial para Filial ID " . ($empresa_id ?? "Matriz") . " para entrega Colaborador ID: " . $item_colaborador_id;
            $sucessoSaida = registrarMovimentacaoEstoque($epi_id, $quantidade, 'saida', $descSaida, null, null, '', null, null, $import_de_clean);
            
            $descEntrada = "Transferência inter-filial recebida de Filial ID " . ($import_de_clean ?? "Matriz") . " para entrega Colaborador ID: " . $item_colaborador_id;
            $sucessoEntrada = registrarMovimentacaoEstoque($epi_id, $quantidade, 'entrada', $descEntrada, null, null, '', null, null, $empresa_id);
            
            if (!$sucessoSaida || !$sucessoEntrada) {
                $erros[] = "Erro interno ao realizar transferência de estoque para o EPI ID {$epi_id}.";
                continue;
            }
        }
        
        // 2. Register delivery
        $epi = carregar("ss_epi", $epi_id);
        if (empty($epi)) {
            $erros[] = "EPI não encontrado para ID {$epi_id}.";
            continue;
        }
        
        $saldoDest = obterSaldoEstoque($epi_id, $empresa_id);
        if ($quantidade > $saldoDest) {
            $erros[] = "Estoque insuficiente na filial selecionada para o EPI ID {$epi_id}. Saldo atual: {$saldoDest}.";
            continue;
        }
        
        $vencimento = calcularVencimentoEpi($data_entrega, (int)$epi["ss_e_nb_vida_util"]);
        
        $entrega = [
            "ss_e_nb_colaborador_id" => $item_colaborador_id,
            "ss_e_nb_epi_id"         => $epi_id,
            "ss_e_nb_empresa_id"     => $empresa_id,
            "ss_e_tx_data_entrega"   => $data_entrega,
            "ss_e_nb_quantidade"     => $quantidade,
            "ss_e_tx_vencimento"     => $vencimento,
            "ss_e_tx_status"         => $status,
            "ss_e_tx_assinatura"     => $assinatura,
            "ss_e_tx_foto"           => $foto,
            "ss_e_nb_userCadastro"   => $userCadastro,
            "ss_e_tx_dataCadastro"   => $dataCadastro
        ];
        
        $res = inserir("ss_epi_entrega", array_keys($entrega), array_values($entrega));
        if ($res && !is_a($res[0], 'Exception')) {
            $id = (int)$res[0];
            $inserted_ids_by_colab[$item_colaborador_id][] = $id;
            
            if (!empty($item["foto_payload"]) && strpos($item["foto_payload"], "data:image/") === 0) {
                $partes = explode(',', $item["foto_payload"]);
                if (count($partes) > 1) {
                    $base64_data = $partes[1];
                    $extensao = "jpg";
                    if (preg_match('/^data:image\/(\w+);base64/', $item["foto_payload"], $match)) {
                        $extensao = strtolower($match[1]);
                    }
                    $dir_foto = "arquivos/entrega_epi/";
                    $dir_foto_abs = $_SERVER["DOCUMENT_ROOT"] . $_ENV["APP_PATH"] . "/" . $dir_foto;
                    if (!is_dir($dir_foto_abs)) {
                        mkdir($dir_foto_abs, 0777, true);
                    }
                    $caminho_foto_abs = $dir_foto_abs . "ENTREGA_{$id}." . $extensao;
                    $conteudo = base64_decode($base64_data);
                    if (file_put_contents($caminho_foto_abs, $conteudo)) {
                        atualizar("ss_epi_entrega", ["ss_e_tx_foto"], [$dir_foto . "ENTREGA_{$id}." . $extensao], $id);
                    }
                }
            }
            
            $pularSubtracao = ($status === 'devolvido' && !empty($item["estornar_saldo"]));
            if (!$pularSubtracao) {
                registrarMovimentacaoEstoque($epi_id, $quantidade, 'saida', 'Entrega de EPI para colaborador ID: ' . $item_colaborador_id, null, null, '', null, null, $empresa_id);
            }
            $sucessos++;
        } else {
            $erros[] = "Erro ao registrar entrega do EPI ID {$epi_id} no banco de dados.";
        }
    }

    if ($sucessos > 0 && !empty($inserted_ids_by_colab)) {
        foreach ($inserted_ids_by_colab as $colab_id => $delivery_ids) {
            try {
                ss_enviar_ficha_para_assinatura($colab_id, $delivery_ids);
            } catch (Throwable $t) {
                $erros[] = "Erro ao enviar ficha para assinatura: " . $t->getMessage();
            }
        }
    }
    
    ob_clean();
    echo json_encode([
        "status" => count($erros) === 0 ? "success" : "partial",
        "sucessos" => $sucessos,
        "erros" => $erros
    ]);
    exit;
}

function cadastrarEntrega() {
    $colaborador_id = (int)$_POST["colaborador_id"];
    $data_entrega   = $_POST["data_entrega"];
    
    if ($colaborador_id <= 0) {
        $_POST["errorFields"][] = "colaborador_id";
        set_status("ERRO: Selecione um Colaborador válido.");
        modificarEntrega();
        exit;
    }

    if (empty($data_entrega)) {
        $_POST["errorFields"][] = "data_entrega";
        set_status("ERRO: Informe a Data de Entrega.");
        modificarEntrega();
        exit;
    }

    $tipo_entrega = $_POST["tipo_entrega"] ?? "individual";
    $temFiliais = ss_tem_filiais_cadastradas();
    $empresa_id = null;
    if ($temFiliais) {
        $empresa_id = !empty($_POST["empresa_id"]) ? (int)$_POST["empresa_id"] : null;
    } else {
        $empresa_id = !empty($_SESSION["user_nb_empresa"]) ? (int)$_SESSION["user_nb_empresa"] : null;
    }

    if ($tipo_entrega === 'kit') {
        $itensJson = $_POST["kit_itens_entrega_json"] ?? "";
        $itens = json_decode($itensJson, true);
        if (empty($itens) || !is_array($itens)) {
            set_status("ERRO: Nenhum item na lista do kit para registrar entrega.");
            modificarEntrega();
            exit;
        }

        // Processar fotos do kit completo se enviadas
        $new_kit_paths = [];
        if (!empty($_FILES["foto"]["name"][0])) {
            $total_files = count($_FILES["foto"]["name"]);
            $pasta_destino = $_SERVER["DOCUMENT_ROOT"] . $_ENV["APP_PATH"] . "/arquivos/entrega_epi/";
            if (!is_dir($pasta_destino)) {
                mkdir($pasta_destino, 0777, true);
            }
            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES["foto"]["error"][$i] === UPLOAD_ERR_OK) {
                    $arquivo = $_FILES["foto"];
                    $nomeOriginal = basename($arquivo["name"][$i]);
                    $nomeSeguro = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '_', $nomeOriginal);
                    
                    $caminho_final = $pasta_destino . $nomeSeguro;
                    if (file_exists($caminho_final)) {
                        $info = pathinfo($nomeSeguro);
                        $base = $info["filename"];
                        $ext = isset($info["extension"]) ? '.' . $info["extension"] : '';
                        $nomeSeguro = $base . '_' . time() . '_' . $i . $ext;
                        $caminho_final = $pasta_destino . $nomeSeguro;
                    }
                    
                    if (move_uploaded_file($arquivo["tmp_name"][$i], $caminho_final)) {
                        $new_kit_paths[] = "arquivos/entrega_epi/" . $nomeSeguro;
                    }
                }
            }
        }
        $foto_kit_completo = implode(",", $new_kit_paths);

        $userCadastro = $_SESSION["user_nb_id"] ?? 0;
        $dataCadastro = date("Y-m-d H:i:s");
        $assinatura   = $_POST["assinatura"] ?? "";

        // Validar estoque para itens selecionados
        foreach ($itens as $item) {
            if ($item["entregar"] == 1) {
                $epiId = (int)$item["epi_id"];
                $qtd = (int)$item["quantidade"];
                $saldo = obterSaldoEstoque($epiId, $empresa_id);
                if ($qtd > $saldo) {
                    set_status("ERRO: Estoque insuficiente para registrar entrega.");
                    modificarEntrega();
                    exit;
                }
            }
        }

        // Processar entregas
        $sucesso = 0;
        $inserted_delivery_ids = [];
        foreach ($itens as $item) {
            $epiId = (int)$item["epi_id"];
            $qtd = (int)$item["quantidade"];
            $entregar = (int)$item["entregar"];
            $justificativa = $item["justificativa"] ?? "";
            
            if ($entregar == 1) {
                $vencimento = calcularVencimentoEpi($data_entrega, (int)$item["vida_util"]);
                $entrega = [
                    "ss_e_nb_colaborador_id" => $colaborador_id,
                    "ss_e_nb_epi_id"         => $epiId,
                    "ss_e_nb_empresa_id"     => $empresa_id,
                    "ss_e_tx_data_entrega"   => $data_entrega,
                    "ss_e_nb_quantidade"     => $qtd,
                    "ss_e_tx_vencimento"     => $vencimento,
                    "ss_e_tx_status"         => 'ativo',
                    "ss_e_tx_assinatura"     => $assinatura,
                    "ss_e_tx_foto"           => $foto_kit_completo,
                    "ss_e_nb_userCadastro"   => $userCadastro,
                    "ss_e_tx_dataCadastro"   => $dataCadastro
                ];
                
                $res = inserir("ss_epi_entrega", array_keys($entrega), array_values($entrega));
                if ($res && !is_a($res[0], 'Exception')) {
                    $id = (int)$res[0];
                    $inserted_delivery_ids[] = $id;
                    if (!empty($item["foto_entrega"]) && strpos($item["foto_entrega"], "data:image/") === 0) {
                        $partes = explode(',', $item["foto_entrega"]);
                        if (count($partes) > 1) {
                            $base64_data = $partes[1];
                            $extensao = "jpg";
                            if (preg_match('/^data:image\/(\w+);base64/', $item["foto_entrega"], $match)) {
                                $extensao = strtolower($match[1]);
                            }
                            $dir_foto = "arquivos/entrega_epi/";
                            $dir_foto_abs = $_SERVER["DOCUMENT_ROOT"] . $_ENV["APP_PATH"] . "/" . $dir_foto;
                            if (!is_dir($dir_foto_abs)) {
                                mkdir($dir_foto_abs, 0777, true);
                            }
                            $caminho_foto_abs = $dir_foto_abs . "ENTREGA_{$id}." . $extensao;
                            $conteudo = base64_decode($base64_data);
                            if (file_put_contents($caminho_foto_abs, $conteudo)) {
                                atualizar("ss_epi_entrega", ["ss_e_tx_foto"], [$dir_foto . "ENTREGA_{$id}." . $extensao], $id);
                            }
                        }
                    }
                    registrarMovimentacaoEstoque($epiId, $qtd, 'saida', 'Entrega de Kit para colaborador ID: ' . $colaborador_id, null, null, '', null, null, $empresa_id);
                    $sucesso++;
                }
            } else {
                // Registrar não-entrega como 'nao_entregue'
                $entrega = [
                    "ss_e_nb_colaborador_id" => $colaborador_id,
                    "ss_e_nb_epi_id"         => $epiId,
                    "ss_e_nb_empresa_id"     => $empresa_id,
                    "ss_e_tx_data_entrega"   => $data_entrega,
                    "ss_e_nb_quantidade"     => $qtd,
                    "ss_e_tx_vencimento"     => null,
                    "ss_e_tx_status"         => 'nao_entregue',
                    "ss_e_tx_assinatura"     => $assinatura,
                    "ss_e_tx_observacao"     => $justificativa,
                    "ss_e_nb_userCadastro"   => $userCadastro,
                    "ss_e_tx_dataCadastro"   => $dataCadastro
                ];
                
                $res = inserir("ss_epi_entrega", array_keys($entrega), array_values($entrega));
                if ($res && !is_a($res[0], 'Exception')) {
                    $id = (int)$res[0];
                    $inserted_delivery_ids[] = $id;
                    $sucesso++;
                }
            }
        }

        if ($sucesso > 0 && !empty($inserted_delivery_ids)) {
            try {
                ss_enviar_ficha_para_assinatura($colaborador_id, $inserted_delivery_ids);
            } catch (Throwable $t) {
                // Captura erros silenciosamente para não quebrar a inserção da entrega
            }
        }

        set_status("Sucesso: {$sucesso} itens do Kit processados!");
        index();
        exit;
    } else {
        // Lógica normal de entrega individual
        $epi_id         = (int)$_POST["epi_id"];
        $quantidade     = (int)$_POST["quantidade"];
        $status         = $_POST["status"] ?? "ativo";
        $assinatura     = $_POST["assinatura"] ?? "";

        if ($epi_id <= 0) {
            $_POST["errorFields"][] = "epi_id";
            set_status("ERRO: Selecione um EPI válido.");
            modificarEntrega();
            exit;
        }

        if ($quantidade <= 0) {
            $_POST["errorFields"][] = "quantidade";
            set_status("ERRO: A quantidade deve ser maior que zero.");
            modificarEntrega();
            exit;
        }

        $epi = carregar("ss_epi", $epi_id);
        if (empty($epi)) {
            set_status("ERRO: EPI não localizado.");
            modificarEntrega();
            exit;
        }

        if (!empty($epi["ss_e_tx_validade_ca"])) {
            if (verificarCAVencido($data_entrega, $epi["ss_e_tx_validade_ca"])) {
                $_POST["errorFields"][] = "data_entrega";
                set_status("ERRO: Certificado de Aprovação (CA nº {$epi['ss_e_tx_ca']}) vencido em " . data($epi["ss_e_tx_validade_ca"]) . ". Entrega bloqueada.");
                modificarEntrega();
                exit;
            }
        }

        if (empty($_POST["id"])) {
            $saldo = obterSaldoEstoque($epi_id, $empresa_id);
            if ($quantidade > $saldo) {
                $_POST["errorFields"][] = "quantidade";
                set_status("ERRO: Estoque insuficiente para entrega. Saldo atual: {$saldo}.");
                modificarEntrega();
                exit;
            }
        }

        $foto_caminho = $_POST["foto"] ?? "";
        if (!empty($_POST["id"]) && !empty($_POST["remover_foto_subst_atual"]) && $_POST["remover_foto_subst_atual"] == 1) {
            $foto_caminho = "";
        }

        $fotos_mantidas = !empty($_POST["fotos_mantidas"]) ? $_POST["fotos_mantidas"] : "";
        $new_paths = [];
        if (!empty($_FILES["foto"]["name"][0])) {
            $total_files = count($_FILES["foto"]["name"]);
            $pasta_destino = $_SERVER["DOCUMENT_ROOT"] . $_ENV["APP_PATH"] . "/arquivos/entrega_epi/";
            if (!is_dir($pasta_destino)) {
                mkdir($pasta_destino, 0777, true);
            }
            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES["foto"]["error"][$i] === UPLOAD_ERR_OK) {
                    $arquivo = $_FILES["foto"];
                    $nomeOriginal = basename($arquivo["name"][$i]);
                    $nomeSeguro = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '_', $nomeOriginal);
                    
                    $caminho_final = $pasta_destino . $nomeSeguro;
                    if (file_exists($caminho_final)) {
                        $info = pathinfo($nomeSeguro);
                        $base = $info["filename"];
                        $ext = isset($info["extension"]) ? '.' . $info["extension"] : '';
                        $nomeSeguro = $base . '_' . time() . '_' . $i . $ext;
                        $caminho_final = $pasta_destino . $nomeSeguro;
                    }
                    
                    if (move_uploaded_file($arquivo["tmp_name"][$i], $caminho_final)) {
                        $new_paths[] = "arquivos/entrega_epi/" . $nomeSeguro;
                    }
                }
            }
        }
        $fotos_existentes_array = array_filter(explode(",", $fotos_mantidas));
        $final_paths = array_merge($fotos_existentes_array, $new_paths);
        $foto_caminho = implode(",", $final_paths);

        $vencimento = calcularVencimentoEpi($data_entrega, (int)$epi["ss_e_nb_vida_util"]);

        $entrega = [
            "ss_e_nb_colaborador_id" => $colaborador_id,
            "ss_e_nb_epi_id"         => $epi_id,
            "ss_e_nb_empresa_id"     => $empresa_id,
            "ss_e_tx_data_entrega"   => $data_entrega,
            "ss_e_nb_quantidade"     => $quantidade,
            "ss_e_tx_vencimento"     => $vencimento,
            "ss_e_tx_status"         => $status,
            "ss_e_tx_assinatura"     => $assinatura,
            "ss_e_tx_foto"           => $foto_caminho
        ];

        if (empty($_POST["id"])) {
            $entrega["ss_e_nb_userCadastro"] = $_SESSION["user_nb_id"] ?? 0;
            $entrega["ss_e_tx_dataCadastro"] = date("Y-m-d H:i:s");
            
            $res = inserir("ss_epi_entrega", array_keys($entrega), array_values($entrega));
            if ($res && !is_a($res[0], 'Exception')) {
                $id = (int)$res[0];
                $pularSubtracao = ($status === 'devolvido' && !empty($_POST["estornar_saldo"]));
                if (!$pularSubtracao) {
                    registrarMovimentacaoEstoque($epi_id, $quantidade, 'saida', 'Entrega de EPI para colaborador ID: ' . $colaborador_id, null, null, '', null, null, $empresa_id);
                }
                
                try {
                    ss_enviar_ficha_para_assinatura($colaborador_id, [$id]);
                } catch (Throwable $t) {
                    // Captura erros silenciosamente para não quebrar a inserção da entrega
                }
                
                set_status("Entrega registrada com sucesso!");
            } else {
                set_status("ERRO ao registrar entrega.");
            }
        } else {
            $entregaAnterior = carregar("ss_epi_entrega", $_POST["id"]);
            
            $entrega["ss_e_nb_userAtualiza"] = $_SESSION["user_nb_id"] ?? 0;
            $entrega["ss_e_tx_dataAtualiza"] = date("Y-m-d H:i:s");
            
            atualizar("ss_epi_entrega", array_keys($entrega), array_values($entrega), $_POST["id"]);
            
            if ($entregaAnterior["ss_e_tx_status"] !== 'devolvido' && $status === 'devolvido') {
                if (!empty($_POST["estornar_saldo"])) {
                    $empresa_id_prev = (int)($entregaAnterior["ss_e_nb_empresa_id"] ?? 0);
                    registrarMovimentacaoEstoque($epi_id, $quantidade, 'entrada', 'Devolução de EPI de colaborador ID: ' . $colaborador_id, null, null, '', null, null, $empresa_id_prev);
                }
            }
            
            set_status("Entrega atualizada com sucesso!");
        }

        index();
        exit;
    }
}

function modificarEntrega() {
    if (!empty($_POST["id"])) {
        if (is_array($_POST["id"])) {
            $_POST["id"] = $_POST["id"][0];
        }
        $entrega = carregar("ss_epi_entrega", $_POST["id"]);
        foreach ($entrega as $key => $value) {
            $cleanedKey = str_replace("ss_e_tx_", "", $key);
            $cleanedKey = str_replace("ss_e_nb_", "", $cleanedKey);
            if (empty($_POST[$cleanedKey])) {
                $_POST[$cleanedKey] = $value;
            }
        }
    }

    // Carregar Kits Ativos para a página
    $sqlKits = query("SELECT ss_k_nb_id, ss_k_tx_nome FROM ss_kit WHERE ss_k_tx_status = 'ativo' ORDER BY ss_k_tx_nome ASC");
    $kitsData = [];
    if ($sqlKits) {
        while ($row = mysqli_fetch_assoc($sqlKits)) {
            $kitId = (int)$row["ss_k_nb_id"];
            $sqlItens = query("SELECT ki.ss_ki_nb_epi_id, ki.ss_ki_nb_quantidade, 
                                      epi.ss_e_tx_grupo, epi.ss_e_tx_subgrupo, epi.ss_e_tx_item, epi.ss_e_tx_ca, epi.ss_e_tx_fabricante, epi.ss_e_nb_vida_util, epi.ss_e_tx_foto
                               FROM ss_kit_item ki
                               JOIN ss_epi epi ON ki.ss_ki_nb_epi_id = epi.ss_e_nb_id
                               WHERE ki.ss_ki_nb_kit_id = {$kitId} AND epi.ss_e_tx_status = 'ativo'");
            $itens = [];
            if ($sqlItens) {
                while ($itemRow = mysqli_fetch_assoc($sqlItens)) {
                    $epiId = (int)$itemRow["ss_ki_nb_epi_id"];
                    $saldo = obterSaldoEstoque($epiId);
                    $itens[] = [
                        "epi_id" => $epiId,
                        "quantidade" => (int)$itemRow["ss_ki_nb_quantidade"],
                        "grupo" => $itemRow["ss_e_tx_grupo"],
                        "subgrupo" => $itemRow["ss_e_tx_subgrupo"],
                        "item" => $itemRow["ss_e_tx_item"],
                        "ca" => $itemRow["ss_e_tx_ca"],
                        "fabricante" => $itemRow["ss_e_tx_fabricante"],
                        "vida_util" => (int)$itemRow["ss_e_nb_vida_util"],
                        "foto" => $itemRow["ss_e_tx_foto"],
                        "saldo" => $saldo
                    ];
                }
            }
            $kitsData[$kitId] = [
                "id" => $kitId,
                "nome" => $row["ss_k_tx_nome"],
                "itens" => $itens
            ];
        }
    }

    // Carregar mapa de fotos de EPIs
    $sqlEpisFotos = query("SELECT ss_e_nb_id, ss_e_tx_foto FROM ss_epi WHERE ss_e_tx_status = 'ativo' AND ss_e_tx_cadastro_tipo = 'estoque'");
    $epiFotosMap = [];
    if ($sqlEpisFotos) {
        while ($row = mysqli_fetch_assoc($sqlEpisFotos)) {
            $epiFotosMap[$row["ss_e_nb_id"]] = $row["ss_e_tx_foto"];
        }
    }

    cabecalho("Registro de Entrega de EPI");
    echo '<style>#btnExportPDF { display: none !important; }</style>';

    // Alerta de configuração do tipo de documento
    $tipoDocAtivo = ss_verificar_assinatura_ativa();
    if ($tipoDocAtivo <= 0) {
        echo '
        <div class="alert alert-warning" style="margin-bottom: 15px;">
            <i class="fa fa-exclamation-triangle"></i> <strong>Informativo:</strong> Para que o Recibo de EPI seja gerado e enviado para assinatura eletrônica, é necessário cadastrar um Tipo de Documento com o nome exato <strong>Recibo de EPI</strong> e marcar a opção <strong>Assinatura</strong> como "Sim" na página de <a href="../cadastro_tipo_doc.php" target="_blank" style="font-weight: bold; text-decoration: underline;">Cadastro de Tipo de Documento</a>.
        </div>';
    }

    // Carregar todas as empresas ativas
    $sqlEmpresas = query("SELECT empr_nb_id, empr_tx_nome, empr_tx_cnpj FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
    $empresaOptions = ["" => "Selecione a Empresa"];
    if ($sqlEmpresas) {
        while ($rowEmp = mysqli_fetch_assoc($sqlEmpresas)) {
            $empresaOptions[$rowEmp["empr_nb_id"]] = $rowEmp["empr_tx_nome"] . " (CNPJ: " . $rowEmp["empr_tx_cnpj"] . ")";
        }
    }
    
    // Campo de Empresa (obrigatório para associar ao Colaborador e obter Estoque)
    $disabledEmpresa = !empty($_POST["id"]) ? "disabled" : "";
    $campo_empresa = combo("Empresa*", "empresa_id", $_POST["empresa_id"] ?? ($_SESSION["user_nb_empresa"] ?? "0"), 4, $empresaOptions, $disabledEmpresa);

    // Campo de Colaborador (inicialmente vazio se for cadastro novo, preenchido via JS/AJAX)
    $colabOptions = ["" => "Selecione a Empresa primeiro..."];
    if (!empty($_POST["colaborador_id"])) {
        $colabObj = carregar("entidade", $_POST["colaborador_id"]);
        if ($colabObj) {
            $colabOptions[$colabObj["enti_nb_id"]] = $colabObj["enti_tx_nome"] . ($colabObj["enti_tx_matricula"] ? " (Matrícula: " . $colabObj["enti_tx_matricula"] . ")" : "");
        }
    }
    $campo_colaborador = combo("Colaborador*", "colaborador_id", $_POST["colaborador_id"] ?? "", 4, $colabOptions);
    
    if (!empty($_POST["id"])) {
        $campo_tipo_entrega = combo("Tipo de Entrega", "tipo_entrega", "individual", 3, ["individual" => "EPI Individual"], "disabled");
    } else {
        $campo_tipo_entrega = combo("Tipo de Ação", "tipo_entrega", "individual", 3, ["individual" => "EPI Individual", "kit" => "Kit de EPIs"]);
    }

    $temFiliais = ss_tem_filiais_cadastradas();

    $jsEmpresas = '{"0":"Matriz"}';
    if ($temFiliais && !empty($empresaOptions)) {
        $empresasJsArr = ["0" => "Matriz"];
        foreach ($empresaOptions as $eid => $ename) {
            if (empty($eid)) continue;
            $cleanName = preg_replace('/ \(CNPJ: .+\)$/', '', $ename);
            $empresasJsArr[$eid] = $cleanName;
        }
        $jsEmpresas = json_encode($empresasJsArr);
    }

    // Custom SQL para carregar todos os EPIs ativos
    $sqlAllEpis = query("SELECT ss_e_nb_id, CONCAT(ss_e_tx_grupo, ' / ', IFNULL(ss_e_tx_subgrupo, ''), ' / ', IFNULL(ss_e_tx_item, ''), ' (CA: ', IFNULL(ss_e_tx_ca, 'N/A'), ')') AS epi_nome 
                         FROM ss_epi 
                         WHERE ss_e_tx_status = 'ativo' AND ss_e_tx_cadastro_tipo = 'estoque'
                         ORDER BY ss_e_tx_grupo ASC");
    $allEpisArr = [];
    $epiOptions = ["" => "Selecione o EPI"];
    if ($sqlAllEpis) {
        while ($row = mysqli_fetch_assoc($sqlAllEpis)) {
            $allEpisArr[] = [
                "id" => $row["ss_e_nb_id"],
                "nome" => $row["epi_nome"]
            ];
            $epiOptions[$row["ss_e_nb_id"]] = $row["epi_nome"];
        }
    }

    // Consulta de saldos consolidados por filial
    $sqlBalances = query("SELECT 
                             ss_e_nb_epi_id,
                             IFNULL(ss_e_nb_empresa_id, 0) as empresa_id,
                             SUM(CASE WHEN ss_e_tx_tipo = 'entrada' THEN ss_e_nb_quantidade ELSE -ss_e_nb_quantidade END) as saldo
                         FROM ss_epi_estoque
                         GROUP BY ss_e_nb_epi_id, ss_e_nb_empresa_id");
    $epiBalancesMap = [];
    if ($sqlBalances) {
        $user_empresa = !empty($_SESSION["user_nb_empresa"]) ? (int)$_SESSION["user_nb_empresa"] : 0;
        while ($row = mysqli_fetch_assoc($sqlBalances)) {
            $epiId = (int)$row['ss_e_nb_epi_id'];
            $empId = (int)$row['empresa_id'];
            $saldo = (int)$row['saldo'];
            if ($empId == 0 || $empId == $user_empresa) {
                $empId = $user_empresa;
            }
            if (!isset($epiBalancesMap[$epiId])) {
                $epiBalancesMap[$epiId] = [];
            }
            if (!isset($epiBalancesMap[$epiId][$empId])) {
                $epiBalancesMap[$epiId][$empId] = 0;
            }
            $epiBalancesMap[$epiId][$empId] += $saldo;
        }
    }
    $campo_epi = combo("EPI*", "epi_id", $_POST["epi_id"] ?? "", 4, $epiOptions);

    // Dropdown de Kits
    $campo_kit = "
        <div class='col-sm-4 margin-bottom-5 campo-fit-content' id='container_kit' style='display: none;'>
            <label>Kit de EPIs*</label>
            <select name='kit_id' id='kit_id' class='form-control input-sm'>
                <option value=''>Selecione o Kit</option>
                ";
    foreach ($kitsData as $k) {
        $campo_kit .= "<option value='{$k['id']}'>{$k['nome']}</option>";
    }
    $campo_kit .= "
            </select>
        </div>
    ";

    $campo_data        = campo_data("Data de Entrega*", "data_entrega", $_POST["data_entrega"] ?? date("Y-m-d"), 2);
    $campo_quant       = campo("Quantidade*", "quantidade", $_POST["quantidade"] ?? "1", 2, "MASCARA_NUMERO");
    $campo_status      = combo("Status", "status", $_POST["status"] ?? "ativo", 2, [
        "ativo" => "Entregue", 
        "substituido" => "Substituído", 
        "devolvido" => "Devolvido", 
        "perdido" => "Perdido/Extraviado"
    ]);

    $campo_estorno = '
        <div class="col-sm-2 margin-bottom-5 campo-fit-content" id="container_estorno" style="display: none; padding-top: 23px;">
            <label style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-weight: bold;">
                <input type="checkbox" name="estornar_saldo" id="estornar_saldo" value="1">
                Estornar ao estoque?
            </label>
        </div>
    ';

    $foto_atual_html = "";
    if (!empty($_POST["foto"])) {
        $foto_atual_html = "
            <div style='margin-top: 5px;'>
                <a href='" . $_ENV["APP_PATH"] . "/" . htmlspecialchars($_POST["foto"]) . "' target='_blank' class='btn btn-xs btn-default'><i class='fa fa-picture-o'></i> Ver Foto Atual</a>
            </div>
        ";
    }

    $fotos = [];
    if (!empty($_POST["foto"])) {
        $fotos = array_filter(explode(",", $_POST["foto"]));
    }
    
    $foto_atual_html = "";
    foreach ($fotos as $f) {
        $src = ss_resolve_foto_url($f);
        $foto_atual_html .= "
            <div class='preview-item' data-path='" . htmlspecialchars($f) . "' style='display: inline-flex; align-items: center; gap: 5px; margin-right: 10px; margin-bottom: 10px; border: 1px solid #ccc; padding: 5px; border-radius: 4px;'>
                <img src='{$src}' style='max-height: 80px; max-width: 80px; object-fit: cover; cursor: pointer;' onclick='verImagemMaior(\"{$src}\")'>
                <button type='button' class='btn btn-danger btn-xs btn_remover_foto_existente' data-path='" . htmlspecialchars($f) . "' title='Remover'><i class='fa fa-remove'></i></button>
            </div>
        ";
    }

    $campo_foto = "
        <div class='col-sm-4 margin-bottom-5 campo-fit-content' id='container_foto' style='display: none;'>
            <label>Fotos do EPI Entregue (Selecione uma ou mais)</label>
            <input name='foto[]' id='foto' type='file' class='form-control input-sm' accept='image/*' multiple>
            <div id='existing_photos_container' style='margin-top: 10px; display: block;'>{$foto_atual_html}</div>
            <div id='new_photos_container' style='margin-top: 10px; display: block;'></div>
        </div>
    ";

    // Divs de previews das imagens
    $preview_epi_div = '
        <div class="col-sm-12 margin-bottom-5" id="preview_epi_individual_container" style="display: none; align-items: center; gap: 10px; margin-top: 10px;">
            <label style="display:block; font-weight:bold;">Visualização do EPI:</label>
            <img id="preview_epi_individual" src="" style="max-height: 80px; max-width: 80px; border-radius: 4px; border: 1px solid #ccc; cursor: pointer; object-fit: cover;" title="Clique para ampliar">
        </div>
    ';

    $hasFotoSubst = !empty($_POST["foto"]);
    $previewSubstStyle = $hasFotoSubst ? "display: block;" : "display: none;";
    $fotoSubstSrc = $hasFotoSubst ? ($_ENV["APP_PATH"] . '/' . $_POST["foto"]) : "";

    $preview_substituido_div = '';

    // Tabela de itens do Kit (inicialmente oculta)
    $kit_items_table_html = "
        <div class='col-sm-12' id='container_itens_kit_entrega' style='display: none; margin-top: 15px;'>
            <div class='portlet light bordered'>
                <div class='portlet-title'>
                    <div class='caption font-green-haze'>
                        <i class='fa fa-list font-green-haze'></i>
                        <span class='caption-subject bold uppercase'>Itens do Kit para Entrega</span>
                    </div>
                </div>
                <div class='portlet-body'>
                    <div class='table-responsive'>
                        <table class='table table-striped table-bordered table-hover' id='tabela_itens_kit_entrega'>
                            <thead>
                                <tr>
                                    <th style='width: 70px; text-align: center;'>Entregar?</th>
                                    <th style='width: 80px;'>Imagem</th>
                                    <th>EPI</th>
                                    <th style='width: 100px;'>Qtd. Kit</th>
                                    <th style='width: 150px;'>Saldo Estoque</th>
                                    <th style='width: 140px;'>Foto da Entrega</th>
                                    <th>Justificativa (se não entregar)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan='7' style='text-align: center; color: #999;'>Selecione um kit para carregar os itens.</td></tr>
                            </tbody>
                         </table>
                     </div>
                  </div>
              </div>
          </div>
      ";

    $buttons = [];
    if (!empty($_POST["id"])) {
        $buttons[] = botao("Atualizar", "cadastrarEntrega", "id", $_POST["id"], "", "", "btn btn-success");
        $buttons[] = criarBotaoVoltar();
    } else {
        $buttons[] = '<button type="button" class="btn btn-primary" id="btn_adicionar_item" onclick="adicionarItemALista()"><i class="fa fa-plus"></i> Adicionar à Lista</button>';
        $buttons[] = '<button type="button" class="btn btn-default" onclick="confirmarVoltar()"><i class="fa fa-arrow-left"></i> Voltar</button>';
    }

    echo abre_form("Dados da Entrega");
    echo campo_hidden("remover_foto_subst_atual", "0");
    echo campo_hidden("fotos_mantidas", $_POST["foto"] ?? "");
    echo linha_form([$campo_empresa, $campo_colaborador, $campo_tipo_entrega]);
    echo linha_form([$campo_epi, $campo_kit]);
    echo linha_form([$preview_epi_div]);
    echo linha_form([$campo_data, $campo_quant, $campo_status, $campo_estorno]);
    echo linha_form([$campo_foto]);
    
    $legenda_html = '
    <div class="col-sm-12" style="margin-top: 15px; margin-bottom: 15px;">
        <div class="alert alert-info" style="background-color: #f7f9fa; border-color: #e3e8ec; color: #333; border-radius: 6px; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h4 style="margin-top: 0; font-weight: bold; color: #31708f;"><i class="fa fa-info-circle"></i> Legenda de Situações / Status</h4>
            <ul style="padding-left: 20px; margin-bottom: 0; line-height: 1.6;">
                <li><strong>Entregue</strong>: Quando entrega o EPI pela primeira vez ao funcionário.</li>
                <li><strong>Substituído</strong>: Quando o EPI foi avariado, quebrado ou precisa ser trocado.</li>
                <li><strong>Perdido/Extraviado</strong>: De fato quando ocorrer a perda ou extravio do item.</li>
                <li><strong>Devolvido</strong>: Apenas para registrar que o item foi devolvido à empresa pelo funcionário. Por padrão, <strong>não altera o saldo do item no estoque</strong> (fica apenas como histórico do colaborador). Caso deseje devolver a quantidade ao saldo de estoque, marque a opção "Estornar ao estoque".</li>
            </ul>
        </div>
    </div>
    ';
    echo linha_form([$legenda_html]);
    
    if (!empty($_POST["id"])) {
        echo linha_form([$kit_items_table_html]);
    }
    
    echo '<input type="hidden" name="kit_itens_entrega_json" id="kit_itens_entrega_json" value="">';
    
    echo fecha_form($buttons);

    if (empty($_POST["id"])) {
        echo '<div id="container_listas_entregas" style="margin-top: 25px;"></div>';
        echo '
        <div id="container_acoes_globais" style="margin-top: 20px; display: none;">
            <div class="row">
                <div class="col-md-12 text-right">
                    <button type="button" class="btn btn-success btn-lg" onclick="salvarTodasAsEntregas()"><i class="fa fa-save"></i> Gravar Todas as Entregas</button>
                </div>
            </div>
        </div>';
    }

    echo "
    <script>
    var itensEntregaLote = [];
    var kitsData = " . json_encode($kitsData) . ";
    var empresasNomes = " . $jsEmpresas . ";
    var epiFotosMap = " . json_encode($epiFotosMap) . ";
    const userEmpresaId = " . json_encode($_SESSION["user_nb_empresa"] ?? "") . ";
    const isEditMode = " . (!empty($_POST["id"]) ? "true" : "false") . ";
    var allEpis = " . json_encode($allEpisArr) . ";
    var epiBalances = " . json_encode($epiBalancesMap) . ";

    $(document).ready(function() {
        if (typeof $.fn.select2 === 'function') {
            $.fn.select2.defaults.set('theme', 'bootstrap');
            
            // Formato customizado para opções de EPI
            const formatEpiOption = function(state) {
                if (!state.id) {
                    return state.text;
                }
                const element = state.element;
                if (element && $(element).attr('data-other-filial') === 'true') {
                    return $('<span style=\"color: #d97706; font-weight: bold; font-style: italic;\"><i class=\"fa fa-exchange\"></i> ' + state.text + '</span>');
                }
                return state.text;
            };

            $('select[name=\"colaborador_id\"], select[name=\"kit_id\"], select[name=\"empresa_id\"]').select2();
            
            $('select[name=\"epi_id\"]').select2({
                templateResult: formatEpiOption,
                templateSelection: formatEpiOption
            });
        }

        if (typeof window.verImagemMaior === 'undefined') {
            window.verImagemMaior = function(src) {
                Swal.fire({
                    imageUrl: src,
                    imageAlt: 'Imagem',
                    showConfirmButton: false,
                    showCloseButton: true,
                    background: '#fff',
                    backdrop: 'rgba(0,0,0,0.8)'
                });
            };
        }

        function atualizarDropdownEpis() {
            let empresaId = parseInt($('select[name=\"empresa_id\"]').val()) || 0;
            if (empresaId === 0 && typeof userEmpresaId !== 'undefined' && userEmpresaId) {
                empresaId = parseInt(userEmpresaId);
            }
            const epiSelect = $('select[name=\"epi_id\"]').first();
            const selectedVal = epiSelect.val();
            
            epiSelect.empty().append('<option value=\"\">Selecione o EPI</option>');
            
            allEpis.forEach(function(epi) {
                const balances = epiBalances[epi.id] || {};
                const currentSaldo = balances[empresaId] || 0;
                
                let otherHasSaldo = false;
                let totalSaldo = currentSaldo;
                Object.keys(balances).forEach(function(eid) {
                    const id = parseInt(eid);
                    if (id !== empresaId) {
                        const s = balances[id] || 0;
                        if (s > 0) {
                            otherHasSaldo = true;
                        }
                        totalSaldo += s;
                    }
                });
                
                if (currentSaldo > 0) {
                    const opt = $('<option>')
                        .val(epi.id)
                        .text(epi.nome + ' (Saldo: ' + currentSaldo + ')')
                        .attr('data-other-filial', 'false');
                    if (epi.id == selectedVal) {
                        opt.prop('selected', true);
                    }
                    epiSelect.append(opt);
                } else if (otherHasSaldo) {
                    const opt = $('<option>')
                        .val(epi.id)
                        .text(epi.nome + ' (Disponível em outra filial)')
                        .attr('data-other-filial', 'true');
                    if (epi.id == selectedVal) {
                        opt.prop('selected', true);
                    }
                    epiSelect.append(opt);
                }
            });
            
            epiSelect.trigger('change.select2');
        }
        
        function updateIndividualEpiPreview() {
            const epiId = $('select[name=\"epi_id\"]').val();
            const fotoPath = epiFotosMap[epiId];
            if (fotoPath) {
                let resolvedSrc = ssResolveFotoUrl(fotoPath);
                $('#preview_epi_individual').attr('src', resolvedSrc).attr('onclick', 'verImagemMaior(\'' + resolvedSrc + '\')');
                $('#preview_epi_individual_container').css('display', 'block');
            } else {
                $('#preview_epi_individual').attr('src', '');
                $('#preview_epi_individual_container').css('display', 'none');
            }
        }

        function updateIndividualEpiSaldo() {
            const epiId = $('select[name=\"epi_id\"]').val();
            const empresaId = $('select[name=\"empresa_id\"]').val() || userEmpresaId || '';
            if (epiId) {
                if (!$('#saldo_individual_badge').length) {
                    $('select[name=\"epi_id\"]').closest('.col-sm-4').find('label').append(' <span id=\"saldo_individual_badge\" class=\"label label-info\" style=\"display: none;\"></span>');
                }
                $.get('entrega_epi.php?acao=get_saldo&epi_id=' + epiId + '&empresa_id=' + empresaId, function(saldo) {
                    $('#saldo_individual_badge').html('Saldo: ' + saldo).show();
                });
            } else {
                $('#saldo_individual_badge').hide();
            }
        }

        function updateKitTableSaldos() {
            if (!$('#tabela_itens_kit_entrega').length) return;
            const empresaId = $('select[name=\"empresa_id\"]').val() || userEmpresaId || '';
            $('#tabela_itens_kit_entrega tbody tr').each(function() {
                const tr = $(this);
                const chk = tr.find('.chk_item_entregar');
                const epiId = chk.val();
                if (epiId) {
                    $.get('entrega_epi.php?acao=get_saldo&epi_id=' + epiId + '&empresa_id=' + empresaId, function(saldo) {
                        saldo = parseInt(saldo) || 0;
                        const tdSaldo = tr.find('td').eq(4);
                        const qtd = parseInt(tr.find('td').eq(3).text()) || 0;
                        if (saldo >= qtd) {
                            tdSaldo.html('<span class=\"label label-success\">Saldo: ' + saldo + '</span>');
                        } else {
                            tdSaldo.html('<span class=\"label label-danger\">Insuficiente: ' + saldo + '</span>');
                        }
                        if (typeof updateKitJson === 'function') {
                            updateKitJson();
                        }
                    });
                }
            });
        }
        
        $('select[name=\"epi_id\"]').on('change', function() {
            updateIndividualEpiPreview();
            updateIndividualEpiSaldo();
        });
        
        $('select[name=\"empresa_id\"]').on('change', function() {
            updateIndividualEpiSaldo();
            updateKitTableSaldos();
        });
        
        updateIndividualEpiPreview();
        updateIndividualEpiSaldo();

        $('#foto').on('change', function(event) {
            $('#new_photos_container').empty();
            const files = event.target.files;
            if (files && files.length > 0) {
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const imgHtml = '<div class=\"preview-item-new\" style=\"display: inline-flex; align-items: center; gap: 5px; margin-right: 10px; margin-bottom: 10px; border: 1px solid #aaa; padding: 5px; border-radius: 4px; background: #f9f9f9;\">' +
                            '<img src=\"' + e.target.result + '\" style=\"max-height: 80px; max-width: 80px; object-fit: cover; cursor: pointer;\" onclick=\"verImagemMaior(\'' + e.target.result + '\')\" title=\"Nova Imagem\">' +
                            '</div>';
                        $('#new_photos_container').append(imgHtml);
                    };
                    reader.readAsDataURL(file);
                }
            }
        });

        // Clique para remover foto existente
        $(document).on('click', '.btn_remover_foto_existente', function() {
            const pathToRemove = $(this).attr('data-path');
            $(this).closest('.preview-item').remove();
            
            let mantidas = $('#fotos_mantidas').val().split(',').filter(Boolean);
            mantidas = mantidas.filter(p => p !== pathToRemove);
            $('#fotos_mantidas').val(mantidas.join(','));
            $('#remover_foto_subst_atual').val('1'); // Sinaliza que fotos foram modificadas/removidas
        });
        
        $('#btn_remover_foto_subst').on('click', function() {
            $('#foto').val('');
            $('#preview_substituido').attr('src', '');
            $('#preview_substituido_container').hide();
            $('#remover_foto_subst_atual').val('1');
        });

        function toggleEntregaTipo() {
            let tipo = $('select[name=\"tipo_entrega\"]').val();
            if (tipo === 'kit') {
                $('select[name=\"epi_id\"]').closest('.col-sm-4').hide();
                $('input[name=\"quantidade\"]').closest('.col-sm-2').hide();
                $('select[name=\"status\"]').closest('.col-sm-2').hide();
                $('#preview_epi_individual_container').hide();
                $('#saldo_individual_badge').hide();
                
                $('#container_foto').show();
                $('#container_foto').find('label').text('Foto do Kit Completo (Opcional)');
                if ($('#preview_substituido').attr('src')) {
                    $('#preview_substituido_container').show();
                } else {
                    $('#preview_substituido_container').hide();
                }
                
                $('#container_kit').show();
                if (isEditMode) {
                    $('#container_itens_kit_entrega').show();
                } else {
                    $('#container_itens_kit_entrega').hide();
                }
            } else {
                $('select[name=\"epi_id\"]').closest('.col-sm-4').show();
                $('input[name=\"quantidade\"]').closest('.col-sm-2').show();
                $('select[name=\"status\"]').closest('.col-sm-2').show();
                
                $('#container_foto').show();
                $('#container_foto').find('label').text('Foto do EPI Entregue');
                if ($('#preview_substituido').attr('src')) {
                    $('#preview_substituido_container').show();
                } else {
                    $('#preview_substituido_container').hide();
                }
                updateIndividualEpiPreview();
                updateIndividualEpiSaldo();
                
                $('#container_kit').hide();
                $('#container_itens_kit_entrega').hide();
            }
        }
        
        $('select[name=\"tipo_entrega\"]').on('change', toggleEntregaTipo);
        toggleEntregaTipo();

        function toggleEstornoCheckbox() {
            let status = $('select[name=\"status\"]').val();
            let tipo = $('select[name=\"tipo_entrega\"]').val();
            if (status === 'devolvido' && tipo !== 'kit') {
                $('#container_estorno').show();
            } else {
                $('#container_estorno').hide();
                $('#estornar_saldo').prop('checked', false);
            }
        }
        $('select[name=\"status\"]').on('change', toggleEstornoCheckbox);
        $('select[name=\"tipo_entrega\"]').on('change', toggleEstornoCheckbox);
        toggleEstornoCheckbox();

        function carregarColaboradoresEmpresa(callback) {
            const empresaId = $('select[name=\"empresa_id\"]').val() || '0';
            const colabSelect = $('select[name=\"colaborador_id\"]');
            const selectedVal = colabSelect.val();
            
            colabSelect.empty().append('<option value=\"\">Carregando colaboradores...</option>').trigger('change');
            
            $.getJSON('entrega_epi.php?acao=obter_colaboradores_empresa&empresa_id=' + empresaId, function(data) {
                colabSelect.empty().append('<option value=\"\">Selecione o Colaborador</option>');
                let foundSelected = false;
                data.forEach(function(item) {
                    const opt = $('<option>').val(item.id).text(item.nome);
                    if (item.id == selectedVal) {
                        opt.prop('selected', true);
                        foundSelected = true;
                    }
                    colabSelect.append(opt);
                });
                colabSelect.trigger('change');
                if (typeof callback === 'function') {
                    callback();
                }
            });
        }

        $('select[name=\"empresa_id\"]').on('change', function() {
            atualizarDropdownEpis();
            carregarColaboradoresEmpresa(function() {
                updateIndividualEpiSaldo();
                updateKitTableSaldos();
            });
        });

        if (!isEditMode) {
            atualizarDropdownEpis();
            carregarColaboradoresEmpresa();
        } else {
            atualizarDropdownEpis();
            $('select[name=\"colaborador_id\"]').select2();
        }

        if (isEditMode) {
            $('#kit_id').on('change', function() {
                const kitId = $(this).val();
                const tbody = $('#tabela_itens_kit_entrega tbody');
                tbody.empty();
                if (!kitId || !kitsData[kitId]) {
                    tbody.append('<tr><td colspan=\"7\" style=\"text-align: center; color: #999;\">Selecione um kit para carregar os itens.</td></tr>');
                    $('#kit_itens_entrega_json').val('');
                    return;
                }
                const kit = kitsData[kitId];
                kit.itens.forEach((item, index) => {
                    const tr = $('<tr>');
                    const tdCheck = $('<td style=\"text-align: center;\">').append($('<input type=\"checkbox\" checked class=\"chk_item_entregar\">').val(item.epi_id));
                    let fotoHtml = '-';
                    if (item.foto) {
                        let resolved = ssResolveFotoUrl(item.foto);
                        fotoHtml = '<img src=\"' + resolved + '\" style=\"max-height: 60px; max-width: 60px; object-fit: cover; cursor: pointer;\" onclick=\"verImagemMaior(\'' + resolved + '\')\">';
                    }
                    const tdFoto = $('<td style=\"text-align: center;\">').append(fotoHtml);
                    const tdEpi = $('<td>').text(item.grupo + ' / ' + item.subgrupo + ' / ' + item.item + ' (CA: ' + (item.ca || 'N/A') + ')');
                    const tdQtd = $('<td>').text(item.quantidade);
                    const tdSaldo = $('<td>').html('<span class=\"label label-warning\">Buscando...</span>');
                    const tdFotoEntrega = $('<td style=\"text-align: center;\">').html('<input type=\"file\" class=\"file_foto_entrega\" accept=\"image/*\" style=\"max-width: 130px;\">');
                    const tdJust = $('<td>').html('<input type=\"text\" class=\"form-control input-sm txt_item_justificativa\" placeholder=\"Justificativa se desmarcado\" disabled style=\"width: 100%;\">');
                    
                    tr.append(tdCheck).append(tdFoto).append(tdEpi).append(tdQtd).append(tdSaldo).append(tdFotoEntrega).append(tdJust);
                    tbody.append(tr);
                });
                
                const empresaId = $('select[name=\"empresa_id\"]').val() || userEmpresaId || '';
                $('#tabela_itens_kit_entrega tbody tr').each(function() {
                    const tr = $(this);
                    const chk = tr.find('.chk_item_entregar');
                    const epiId = chk.val();
                    if (epiId) {
                        $.get('entrega_epi.php?acao=get_saldo&epi_id=' + epiId + '&empresa_id=' + empresaId, function(saldo) {
                            saldo = parseInt(saldo) || 0;
                            const tdSaldo = tr.find('td').eq(4);
                            const qtd = parseInt(tr.find('td').eq(3).text()) || 0;
                            if (saldo >= qtd) {
                                tdSaldo.html('<span class=\"label label-success\">Saldo: ' + saldo + '</span>');
                            } else {
                                tdSaldo.html('<span class=\"label label-danger\">Insuficiente: ' + saldo + '</span>');
                            }
                            updateKitJson();
                        });
                    }
                });
            });

            function updateKitJson() {
                const kitId = $('#kit_id').val();
                if (!kitId || !kitsData[kitId]) return;
                const rows = $('#tabela_itens_kit_entrega tbody tr');
                const itemsToDeliver = [];
                rows.each(function(index) {
                    const tr = $(this);
                    const checked = tr.find('.chk_item_entregar').is(':checked');
                    const justificativa = tr.find('.txt_item_justificativa').val();
                    const itemOriginal = kitsData[kitId].itens[index];
                    
                    const chkVal = tr.find('.chk_item_entregar').val();
                    const tdSaldo = tr.find('td').eq(4).text();
                    let currentSaldo = 0;
                    if (tdSaldo.indexOf('Saldo: ') !== -1) {
                        currentSaldo = parseInt(tdSaldo.replace('Saldo: ', '')) || 0;
                    } else if (tdSaldo.indexOf('Insuficiente: ') !== -1) {
                        currentSaldo = parseInt(tdSaldo.replace('Insuficiente: ', '')) || 0;
                    }
                    
                    itemsToDeliver.push({
                        epi_id: itemOriginal.epi_id,
                        quantidade: itemOriginal.quantidade,
                        vida_util: itemOriginal.vida_util,
                        entregar: checked ? 1 : 0,
                        saldo: currentSaldo,
                        justificativa: justificativa,
                        foto_entrega: itemOriginal.foto_entrega || ''
                    });
                });
                $('#kit_itens_entrega_json').val(JSON.stringify(itemsToDeliver));
            }

            $(document).on('change', '.chk_item_entregar', function() {
                const checked = $(this).is(':checked');
                const tr = $(this).closest('tr');
                const inputJust = tr.find('.txt_item_justificativa');
                inputJust.prop('disabled', checked);
                if (checked) {
                    inputJust.val('');
                }
                updateKitJson();
            });

            $(document).on('keyup change', '.txt_item_justificativa', updateKitJson);

            $(document).on('change', '.file_foto_entrega', function() {
                const file = this.files[0];
                const tr = $(this).closest('tr');
                const index = tr.index();
                const kitId = $('#kit_id').val();
                if (!file || !kitId || !kitsData[kitId]) return;
                const reader = new FileReader();
                reader.onload = function(e) {
                    const kitItens = JSON.parse($('#kit_itens_entrega_json').val() || '[]');
                    if (kitItens[index]) {
                        kitItens[index].foto_payload = e.target.result;
                        $('#kit_itens_entrega_json').val(JSON.stringify(kitItens));
                    }
                };
                reader.readAsDataURL(file);
            });

            $('form').on('submit', function(e) {
                let tipo = $('select[name=\"tipo_entrega\"]').val();
                if (tipo === 'kit') {
                    const kitId = $('#kit_id').val();
                    if (!kitId) {
                        e.preventDefault();
                        Swal.fire('Atenção', 'Selecione um Kit para entrega.', 'warning');
                        return;
                    }
                    updateKitJson();
                    const jsonStr = $('#kit_itens_entrega_json').val();
                    if (!jsonStr) {
                        e.preventDefault();
                        Swal.fire('Atenção', 'Erro ao processar itens do kit.', 'error');
                        return;
                    }
                    const items = JSON.parse(jsonStr);
                    let insufficientItems = [];
                    items.forEach(function(item) {
                        if (item.entregar === 1) {
                            if (item.saldo < item.quantidade) {
                                const kit = kitsData[kitId];
                                const matched = kit.itens.find(i => i.epi_id == item.epi_id);
                                const name = matched ? (matched.grupo + ' / ' + matched.subgrupo + ' / ' + matched.item) : 'EPI ID: ' + item.epi_id;
                                insufficientItems.push(name + ' (Necessário: ' + item.quantidade + ', Saldo: ' + item.saldo + ')');
                            }
                        }
                    });
                    if (insufficientItems.length > 0) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Estoque Insuficiente!',
                            html: 'Não há saldo em estoque suficiente para entregar os seguintes itens do kit:<br><br>' + insufficientItems.join('<br>'),
                            icon: 'error'
                        });
                        return;
                    }
                }
            });
        }
    });

    function adicionarItemALista() {
        var colabSelect = $('select[name=\"colaborador_id\"]');
        var colabId = colabSelect.val();
        var colabNome = colabSelect.find('option:selected').text();
        
        if (!colabId) {
            alert('Por favor, selecione um Colaborador.');
            return;
        }

        var tipoAcao = $('select[name=\"tipo_entrega\"]').val();
        if (tipoAcao === 'kit') {
            adicionarKitALista(colabId, colabNome);
            return;
        }
        
        var epiSelect = $('select[name=\"epi_id\"]');
        var epiId = epiSelect.val();
        var epiNome = epiSelect.find('option:selected').text();
        var quantidade = $('#quantidade').val();
        var status = $('select[name=\"status\"]').val();
        
        var empresaId = $('select[name=\"empresa_id\"]').length > 0 ? $('select[name=\"empresa_id\"]').val() : '0';
        if (empresaId === '') empresaId = '0';
        
        if (!epiId) {
            alert('Por favor, selecione um EPI.');
            return;
        }
        if (!quantidade || parseInt(quantidade, 10) <= 0) {
            alert('Por favor, informe uma quantidade maior que zero.');
            return;
        }
        
        var fotoInput = $('#foto')[0];
        var files = (fotoInput && fotoInput.files) ? fotoInput.files : [];
        
        if (files && files.length > 0) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var payload = e.target.result;
                addItemObj(colabId, colabNome, epiId, epiNome, quantidade, status, empresaId, payload);
            };
            reader.readAsDataURL(files[0]);
        } else {
            addItemObj(colabId, colabNome, epiId, epiNome, quantidade, status, empresaId, \"\");
        }
    }

    function adicionarKitALista(colabId, colabNome) {
        var kitId = $('#kit_id').val();
        if (!kitId) {
            alert('Por favor, selecione um Kit.');
            return;
        }
        
        var kit = kitsData[kitId];
        if (!kit || !kit.itens || kit.itens.length === 0) {
            alert('Kit selecionado não contém itens.');
            return;
        }
        
        var empresaId = $('select[name=\"empresa_id\"]').length > 0 ? $('select[name=\"empresa_id\"]').val() : '0';
        if (empresaId === '') empresaId = '0';
        
        kit.itens.forEach(function(item) {
            var epiNome = item.grupo + ' / ' + item.subgrupo + ' / ' + item.item + ' (CA: ' + (item.ca || 'N/A') + ')';
            addItemObj(colabId, colabNome, item.epi_id, epiNome, item.quantidade, 'ativo', empresaId, \"\");
        });
        
        $('#kit_id').val('').trigger('change');
    }

    function addItemObj(colabId, colabNome, epiId, epiNome, quantidade, status, empresaId, fotoPayload) {
        var uniqueId = new Date().getTime() + '_' + Math.random().toString(36).substr(2, 5);
        
        var item = {
            unique_id: uniqueId,
            colaborador_id: colabId,
            colaborador_nome: colabNome,
            epi_id: epiId,
            epi_nome: epiNome,
            quantidade: parseInt(quantidade, 10),
            status: status,
            empresa_id: empresaId,
            foto_payload: fotoPayload,
            import_de: \"\"
        };
        
        itensEntregaLote.push(item);
        
        $('select[name=\"epi_id\"]').val('').trigger('change');
        $('#quantidade').val('1');
        $('#foto').val('');
        $('#new_photos_container').empty();
        
        $.ajax({
            url: 'entrega_epi.php?acao=obterSaldosEpiFiliaisAjax',
            type: 'GET',
            data: { epi_id: epiId },
            dataType: 'json',
            success: function(saldos) {
                item.saldos_filiais = saldos;
                desenharListas();
            },
            error: function() {
                item.saldos_filiais = [];
                desenharListas();
            }
        });
    }

    function desenharListas() {
        var container = $('#container_listas_entregas');
        container.empty();
        
        if (itensEntregaLote.length === 0) {
            $('#container_acoes_globais').hide();
            return;
        }
        
        $('#container_acoes_globais').show();
        
        // Group items by collaborator_id
        var grupos = {};
        itensEntregaLote.forEach(function(item) {
            if (!grupos[item.colaborador_id]) {
                grupos[item.colaborador_id] = {
                    colaborador_id: item.colaborador_id,
                    colaborador_nome: item.colaborador_nome,
                    itens: []
                };
            }
            grupos[item.colaborador_id].itens.push(item);
        });
        
        var appPath = " . json_encode($_ENV["APP_PATH"]) . ";
        
        Object.keys(grupos).forEach(function(colabId) {
            var grupo = grupos[colabId];
            
            var tableHtml = '<div class=\"portlet box green-haze\" style=\"margin-bottom: 25px;\">' +
                '<div class=\"portlet-title\">' +
                    '<div class=\"caption\">' +
                        '<i class=\"fa fa-user\"></i> Funcionário: <strong>' + grupo.colaborador_nome + '</strong> (' + grupo.itens.length + ' item(ns))' +
                    '</div>' +
                '</div>' +
                '<div class=\"portlet-body\">' +
                    '<div class=\"table-responsive\">' +
                        '<table class=\"table table-striped table-bordered table-hover\">' +
                            '<thead>' +
                                '<tr>' +
                                    '<th style=\"width: 70px; text-align: center;\">Foto</th>' +
                                    '<th>EPI</th>' +
                                    '<th style=\"text-align: center; width: 80px;\">Qtd</th>' +
                                    '<th>Filial de Origem</th>' +
                                    '<th style=\"text-align: center;\">Status</th>' +
                                    '<th style=\"width: 250px;\">Estoque / Importação</th>' +
                                    '<th style=\"width: 50px; text-align: center;\">Ações</th>' +
                                '</tr>' +
                            '</thead>' +
                            '<tbody>';
                            
            grupo.itens.forEach(function(item) {
                var fotoHtml = '-';
                if (item.foto_payload) {
                    var resolvedSrc = ssResolveFotoUrl(item.foto_payload);
                    fotoHtml = '<img src=\"' + resolvedSrc + '\" style=\"max-height: 45px; max-width: 45px; border-radius: 4px; object-fit: cover; cursor: pointer;\" onclick=\"verImagemMaior(\'' + resolvedSrc + '\')\">';
                } else {
                    var defaultFoto = epiFotosMap[item.epi_id];
                    if (defaultFoto) {
                        var resolvedSrc = ssResolveFotoUrl(defaultFoto);
                        fotoHtml = '<img src=\"' + resolvedSrc + '\" style=\"max-height: 45px; max-width: 45px; border-radius: 4px; object-fit: cover; cursor: pointer;\" onclick=\"verImagemMaior(\'' + resolvedSrc + '\')\">';
                    }
                }
                
                var statusLabel = '<span class=\"label label-sm label-success\">Ativo</span>';
                if (item.status === 'devolvido') statusLabel = '<span class=\"label label-sm label-info\">Devolvido</span>';
                else if (item.status === 'perdido') statusLabel = '<span class=\"label label-sm label-danger\">Perdido</span>';
                else if (item.status === 'substituido') statusLabel = '<span class=\"label label-sm label-warning\">Substituído</span>';
                
                var origNome = empresasNomes[item.empresa_id] || 'Matriz';
                
                var saldos = item.saldos_filiais || [];
                var currentStockEntry = null;
                for (var i = 0; i < saldos.length; i++) {
                    if (saldos[i].empresa_id == item.empresa_id) {
                        currentStockEntry = saldos[i];
                        break;
                    }
                }
                var currentStock = currentStockEntry ? currentStockEntry.saldo : 0;
                
                var estoqueBadge = '';
                var importDropdown = '';
                
                if (currentStock >= item.quantidade) {
                    estoqueBadge = '<span class=\"label label-success\" style=\"font-weight: bold;\">Disponível: ' + currentStock + '</span>';
                } else {
                    estoqueBadge = '<span class=\"label label-danger\" style=\"font-weight: bold; display: block; margin-bottom: 5px;\">Insuficiente: ' + currentStock + '</span>';
                    
                    var options = '<option value=\"\">Não importar (Erro ao gravar)</option>';
                    saldos.forEach(function(s) {
                        if (s.empresa_id != item.empresa_id && s.saldo >= item.quantidade) {
                            var selectedAttr = item.import_de == s.empresa_id ? 'selected' : '';
                            options += '<option value=\"' + s.empresa_id + '\" ' + selectedAttr + '>Importar de ' + s.empresa_nome + ' (Saldo: ' + s.saldo + ')</option>';
                        }
                    });
                    
                    importDropdown = '<select class=\"form-control input-sm\" style=\"margin-top: 5px;\" onchange=\"definirImportOrigem(\'' + item.unique_id + '\', this.value)\">' + options + '</select>';
                }
                
                tableHtml += '<tr>' +
                    '<td style=\"text-align: center; vertical-align: middle;\">' + fotoHtml + '</td>' +
                    '<td style=\"vertical-align: middle;\">' + item.epi_nome + '</td>' +
                    '<td style=\"text-align: center; font-weight: bold; vertical-align: middle;\">' + item.quantidade + '</td>' +
                    '<td style=\"vertical-align: middle;\">' + origNome + '</td>' +
                    '<td style=\"text-align: center; vertical-align: middle;\">' + statusLabel + '</td>' +
                    '<td style=\"vertical-align: middle;\">' + estoqueBadge + importDropdown + '</td>' +
                    '<td style=\"text-align: center; vertical-align: middle;\">' +
                        '<button type=\"button\" class=\"btn btn-danger btn-xs\" onclick=\"removerItemEntrega(\'' + item.unique_id + '\')\"><i class=\"fa fa-trash\"></i></button>' +
                    '</td>' +
                '</tr>';
            });
            
            tableHtml += '</tbody>' +
                        '</table>' +
                    '</div>' +
                    '<div style=\"margin-top: 10px; text-align: right;\">' +
                        '<button type=\"button\" class=\"btn btn-primary\" onclick=\"salvarEntregasFuncionario(\'' + colabId + '\')\"><i class=\"fa fa-save\"></i> Gravar Entregas deste Funcionário</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
            
            container.append(tableHtml);
        });
    }
    
    function definirImportOrigem(uniqueId, val) {
        var item = itensEntregaLote.find(function(it) { return it.unique_id === uniqueId; });
        if (item) {
            item.import_de = val;
        }
    }
    
    function removerItemEntrega(uniqueId) {
        itensEntregaLote = itensEntregaLote.filter(function(it) { return it.unique_id !== uniqueId; });
        desenharListas();
    }

    function salvarEntregasFuncionario(colabId) {
        var dataEntrega = $('#data_entrega').val();
        if (!dataEntrega) {
            alert('Por favor, informe a Data de Entrega.');
            return;
        }
        
        var itensFunc = itensEntregaLote.filter(function(item) { return item.colaborador_id == colabId; });
        if (itensFunc.length === 0) return;
        
        var colabNome = itensFunc[0] ? itensFunc[0].colaborador_nome : '';
        
        var hasStockIssues = false;
        itensFunc.forEach(function(item) {
            var saldos = item.saldos_filiais || [];
            var currentStockEntry = null;
            for (var i = 0; i < saldos.length; i++) {
                if (saldos[i].empresa_id == item.empresa_id) {
                    currentStockEntry = saldos[i];
                    break;
                }
            }
            var currentStock = currentStockEntry ? currentStockEntry.saldo : 0;
            if (currentStock < item.quantidade && !item.import_de) {
                hasStockIssues = true;
            }
        });
        
        if (hasStockIssues) {
            alert('Erro: Um ou mais itens possuem estoque insuficiente e nenhuma filial de origem foi selecionada para importação.');
            return;
        }
        
        if (!confirm('Deseja salvar as entregas de ' + colabNome + '?')) return;
        
        $.ajax({
            url: 'entrega_epi.php?acao=cadastrarEntregaLoteAjax',
            type: 'POST',
            data: {
                colaborador_id: colabId,
                data_entrega: dataEntrega,
                lotes: JSON.stringify(itensFunc)
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' || response.status === 'partial') {
                    if (response.erros && response.erros.length > 0) {
                        alert('Avisos durante a gravação das entregas:\\n\\n' + response.erros.join('\\n'));
                    }
                    
                    // Remove these items from global array
                    itensEntregaLote = itensEntregaLote.filter(function(item) { return item.colaborador_id != colabId; });
                    desenharListas();
                    alert('Entregas de ' + colabNome + ' registradas com sucesso!');
                } else {
                    alert('Erro ao registrar entregas: ' + (response.message || ''));
                }
            },
            error: function() {
                alert('Erro na comunicação com o servidor.');
            }
        });
    }

    function salvarTodasAsEntregas() {
        var dataEntrega = $('#data_entrega').val();
        if (!dataEntrega) {
            alert('Por favor, informe a Data de Entrega.');
            return;
        }
        
        var hasStockIssues = false;
        itensEntregaLote.forEach(function(item) {
            var saldos = item.saldos_filiais || [];
            var currentStockEntry = null;
            for (var i = 0; i < saldos.length; i++) {
                if (saldos[i].empresa_id == item.empresa_id) {
                    currentStockEntry = saldos[i];
                    break;
                }
            }
            var currentStock = currentStockEntry ? currentStockEntry.saldo : 0;
            if (currentStock < item.quantidade && !item.import_de) {
                hasStockIssues = true;
            }
        });
        
        if (hasStockIssues) {
            alert('Erro: Um ou mais itens possuem estoque insuficiente e nenhuma filial de origem foi selecionada para importação.');
            return;
        }
        
        if (!confirm('Deseja salvar todas as entregas adicionadas na lista de todos os funcionários?')) return;
        
        $.ajax({
            url: 'entrega_epi.php?acao=cadastrarEntregaLoteAjax',
            type: 'POST',
            data: {
                colaborador_id: 0,
                data_entrega: dataEntrega,
                lotes: JSON.stringify(itensEntregaLote)
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' || response.status === 'partial') {
                    if (response.erros && response.erros.length > 0) {
                        alert('Avisos durante a gravação das entregas:\\n\\n' + response.erros.join('\\n'));
                    }
                    itensEntregaLote = [];
                    desenharListas();
                    alert('Todas as entregas registradas com sucesso!');
                    window.location.href = 'entrega_epi.php';
                } else {
                    alert('Erro ao registrar entregas: ' + (response.message || ''));
                }
            },
            error: function() {
                alert('Erro na comunicação com o servidor.');
            }
        });
    }

    function confirmarVoltar() {
        if (itensEntregaLote.length > 0) {
            if (confirm('Você possui entregas adicionadas na lista que ainda não foram gravadas. Deseja realmente sair sem salvar?')) {
                window.location.href = 'entrega_epi.php';
            }
        } else {
            window.location.href = 'entrega_epi.php';
        }
    }
    </script>
    ";

    rodape();
}

function excluirEntrega() {
    if (!empty($_POST["id"])) {
        $id = (int)$_POST["id"];
        $justificativa = $_POST["justificativa"] ?? "";
        $estornar = !empty($_POST["estornar"]);
        
        $entrega = carregar("ss_epi_entrega", $id);
        if ($entrega) {
            $epi_id = (int)$entrega["ss_e_nb_epi_id"];
            $quantidade = (int)$entrega["ss_e_nb_quantidade"];
            $empresa_id = !empty($entrega["ss_e_nb_empresa_id"]) ? (int)$entrega["ss_e_nb_empresa_id"] : null;
            
            if ($estornar) {
                // Reverter saldo no estoque (entrada)
                registrarMovimentacaoEstoque(
                    $epi_id, 
                    $quantidade, 
                    'entrada', 
                    'Estorno por exclusão de entrega ID: ' . $id . '. Justificativa: ' . $justificativa, 
                    null, 
                    null, 
                    '', 
                    null, 
                    null, 
                    $empresa_id
                );
                set_status("Entrega excluída com sucesso e estoque estornado!");
            } else {
                set_status("Entrega excluída com sucesso (sem estorno de estoque)!");
            }
            
            // Inativar a entrega e salvar justificativa
            atualizar("ss_epi_entrega", ["ss_e_tx_status", "ss_e_tx_justificativa_exclusao"], ["inativo", $justificativa], $id);
        }
    }
    index();
    exit;
}

function imprimirFicha() {
    global $conn;
    $colaborador_id = (int)($_GET["colaborador_id"] ?? 0);
    if ($colaborador_id <= 0) {
        echo "Colaborador inválido.";
        exit;
    }

    $delivery_ids = [];
    $delivRaw = trim(strval($_GET["delivery_ids"] ?? ""));
    if ($delivRaw !== "") {
        $delivery_ids = array_filter(array_map('intval', explode(',', $delivRaw)), fn($v) => $v > 0);
    }

    // Busca informações da última assinatura (se houver) consolidada
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

    $colabRaw = carregar("entidade", $colaborador_id);
    if (empty($colabRaw)) {
        echo "Colaborador não cadastrado.";
        exit;
    }
    $colaborador = [
        "ss_c_tx_nome"      => $colabRaw["enti_tx_nome"],
        "ss_c_tx_matricula" => $colabRaw["enti_tx_matricula"],
        "ss_c_tx_cpf"       => $colabRaw["enti_tx_cpf"],
        "ss_c_tx_cargo"     => $colabRaw["enti_tx_ocupacao"]
    ];

    $whereIds = "";
    if (!empty($delivery_ids)) {
        $whereIds = " AND ent.ss_e_nb_id IN (" . implode(",", $delivery_ids) . ") ";
    }

    // Query concatenando Grupo, Subgrupo e Item aliando como ss_e_tx_nome para compatibilidade
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
    $entregas = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $entregas[] = $row;
        }
    }

    $spans = [];
    $n = count($entregas);
    $i = 0;
    while ($i < $n) {
        $val = $entregas[$i]["ss_e_tx_identificador"];
        $count = 1;
        $j = $i + 1;
        while ($j < $n && $entregas[$j]["ss_e_tx_identificador"] === $val) {
            $count++;
            $j++;
        }
        $spans[$i] = $count;
        $i = $j;
    }

    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Recibo de EPI - <?=$colaborador["ss_c_tx_nome"]?></title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.4; color: #333; margin: 30px; }
            h1, h2 { text-align: center; margin-bottom: 5px; }
            .header-info { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
            .header-info table { width: 100%; border-collapse: collapse; }
            .header-info table td { padding: 4px 10px; }
            .nr6-text { font-size: 9pt; text-align: justify; border: 1px solid #ccc; padding: 10px; background: #f9f9f9; margin-bottom: 20px; border-radius: 5px; }
            table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            table.data-table th, table.data-table td { border: 1px solid #999; padding: 8px; text-align: left; font-size: 10pt; }
            table.data-table th { background-color: #f2f2f2; }
            .signatures { margin-top: 50px; width: 100%; }
            .signatures td { text-align: center; width: 50%; padding-top: 40px; }
            .signatures .line { width: 80%; border-top: 1px solid #000; margin: 0 auto; padding-top: 5px; }
            @media print {
                body { margin: 15px; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom: 20px; text-align: right;">
            <button onclick="window.print()" style="padding: 8px 16px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Imprimir Recibo</button>
        </div>
        
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
            <img src="../imagens/logo_topo_cliente.png" style="height: 40px; object-fit: contain;">
            <div style="text-align: right;">
                <h2 style="margin: 0; font-size: 16pt;">RECIBO DE ENTREGA DE EPI</h2>
                <p style="margin: 0; font-size: 9pt; font-weight: bold; color: #666;">(Norma Regulamentadora NR-6 - Portaria 3.214/78)</p>
            </div>
        </div>
        
        <div class="header-info">
            <table>
                <tr>
                    <td style="font-weight: bold; width: 15%;">Colaborador:</td>
                    <td><?=$colaborador["ss_c_tx_nome"]?></td>
                    <td style="font-weight: bold; width: 15%;">Matrícula:</td>
                    <td><?=$colaborador["ss_c_tx_matricula"] ?: '---'?></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">CPF:</td>
                    <td><?=$colaborador["ss_c_tx_cpf"] ? preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "$1.$2.$3-$4", $colaborador["ss_c_tx_cpf"]) : '---'?></td>
                    <td style="font-weight: bold;">Cargo/Função:</td>
                    <td><?=$colaborador["ss_c_tx_cargo"] ?: '---'?></td>
                </tr>
            </table>
        </div>
        
        <div class="nr6-text">
            <strong>Declaração do Empregado:</strong> Declaro que recebi gratuitamente os Equipamentos de Proteção Individual (EPI) abaixo relacionados, adequados ao risco de minhas atividades. Comprometo-me a usá-los obrigatoriamente durante o horário de trabalho, zelar pela sua guarda e conservação, e comunicar imediatamente ao setor de segurança qualquer alteração que os torne impróprios para uso, sob pena de infração disciplinar nos termos da legislação trabalhista brasileira (Art. 158 da CLT).
        </div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">Cód.</th>
                    <th style="width: 52%;">Equipamento (EPI)</th>
                    <th style="width: 15%;">CA MTE</th>
                    <th style="width: 15%;">Data Entrega</th>
                    <th style="width: 5%;">Quant.</th>
                    <th style="width: 8%;">Identificador</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entregas)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">Nenhum EPI entregue cadastrado no histórico deste colaborador.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entregas as $index => $e): ?>
                        <tr>
                            <td style="width: 5%;"><?=$e["ss_e_nb_id"]?></td>
                            <td style="width: 52%;"><?=$e["ss_e_tx_nome"]?></td>
                            <td style="width: 15%;"><?=$e["ss_e_tx_ca"] ?: '---'?></td>
                            <td style="width: 15%;"><?=data($e["ss_e_tx_data_entrega"])?></td>
                            <td style="width: 5%;"><?=$e["ss_e_nb_quantidade"]?></td>
                            <?php if (isset($spans[$index])): ?>
                                <td rowspan="<?=$spans[$index]?>" style="width: 8%; font-family: monospace; font-size: 8.5pt; vertical-align: middle; text-align: center;"><?=$e["ss_e_tx_identificador"]?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($assinaturaDados && $assinaturaDados['status'] === 'assinado'): ?>
            <?php 
            $cpfFmtSig = $assinaturaDados["cpf"] ? preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "$1.$2.$3-$4", $assinaturaDados["cpf"]) : '---';
            $dataAssinatura = !empty($assinaturaDados['data_assinatura']) ? date('d/m/Y H:i', strtotime($assinaturaDados['data_assinatura'])) : '';
            $ip = $assinaturaDados['ip_address'] ?? '---';
            $hash = $assinaturaDados['hash_assinatura'] ?? '---';
            ?>
            <div style="margin-top: 30px; border: 1.5px solid #0056b3; background-color: #f4f8fc; padding: 15px; border-radius: 5px; font-size: 10pt;">
                <h4 style="color: #0056b3; margin: 0 0 5px 0; font-size: 11pt;">ASSINATURA ELETRÔNICA</h4>
                <p style="margin: 0; line-height: 1.5;">
                    Este documento foi assinado eletronicamente por <strong><?=htmlspecialchars($assinaturaDados['nome'])?></strong> (CPF: <?=htmlspecialchars($cpfFmtSig)?>) em <strong><?=$dataAssinatura?></strong>.<br>
                    IP de Origem: <strong><?=htmlspecialchars($ip)?></strong> | Protocolo/Hash: <strong><?=htmlspecialchars($hash)?></strong>.<br>
                    <span style="color: #555; font-size: 8pt; display: block; margin-top: 5px;">A concordância expressa do colaborador valida legalmente a assinatura nos termos da Medida Provisória nº 2.200-2/2001.</span>
                </p>
            </div>
        <?php elseif ($assinaturaDados && $assinaturaDados['status'] === 'pendente'): ?>
            <div style="margin-top: 30px; border: 1.5px dashed #e87e04; background-color: #fffdf4; padding: 15px; border-radius: 5px; text-align: center; font-size: 10pt;">
                <h4 style="color: #e87e04; margin: 0 0 5px 0; font-size: 11pt;">ASSINATURA ELETRÔNICA PENDENTE</h4>
                <p style="margin: 0; color: #666; line-height: 1.5;">
                    Este documento foi gerado pelo sistema e aguarda a assinatura eletrônica do colaborador através do portal.
                </p>
            </div>
        <?php else: ?>
            <table class="signatures">
                <tr>
                    <td>
                        <div class="line">Setor de Saúde e Segurança do Trabalho</div>
                    </td>
                    <td>
                        <div class="line">Assinatura do Colaborador</div>
                        <span style="font-size: 8pt; color: #666;">Data de Emissão: <?=date("d/m/Y")?></span>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

function index() {
    cabecalho("Entregas de EPI");
    echo '<style>#btnExportPDF { display: none !important; }</style>';

    // Alerta de configuração do tipo de documento
    $tipoDocAtivo = ss_verificar_assinatura_ativa();
    if ($tipoDocAtivo <= 0) {
        echo '
        <div class="alert alert-warning" style="margin-bottom: 15px;">
            <i class="fa fa-exclamation-triangle"></i> <strong>Informativo:</strong> Para que o Recibo de EPI seja gerado e enviado para assinatura eletrônica, é necessário cadastrar um Tipo de Documento com o nome exato <strong>Recibo de EPI</strong> e marcar a opção <strong>Assinatura</strong> como "Sim" na página de <a href="../cadastro_tipo_doc.php" target="_blank" style="font-weight: bold; text-decoration: underline;">Cadastro de Tipo de Documento</a>.
        </div>';
    }

    if (!isset($_POST["busca_status"])) {
        $_POST["busca_status"] = "ativo";
    }

    // Custom SQL para dropdown de EPIs
    $sql = query("SELECT ss_e_nb_id, CONCAT(ss_e_tx_grupo, ' / ', IFNULL(ss_e_tx_subgrupo, ''), ' / ', IFNULL(ss_e_tx_item, '')) AS epi_nome 
                  FROM ss_epi 
                  WHERE ss_e_tx_status = 'ativo' AND ss_e_tx_cadastro_tipo = 'estoque'
                  ORDER BY ss_e_tx_grupo ASC");
    $epiOptions = ["" => "Todos"];
    if ($sql) {
        while ($row = mysqli_fetch_assoc($sql)) {
            $epiOptions[$row["ss_e_nb_id"]] = $row["epi_nome"];
        }
    }

    $temFiliais = ss_tem_filiais_cadastradas();

    // Carregar todas as empresas ativas para filtro de busca
    $empresaOptions = ["" => "Todas"];
    $sqlEmpresas = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
    if ($sqlEmpresas) {
        while ($rowEmp = mysqli_fetch_assoc($sqlEmpresas)) {
            $empresaOptions[$rowEmp["empr_nb_id"]] = $rowEmp["empr_tx_nome"];
        }
    }

    $fields = [
        combo_bd("!Colaborador", "busca_colaborador", $_POST["busca_colaborador"] ?? "", 4, "entidade", "id='busca_colaborador'", " AND enti_tx_status = 'ativo' AND COALESCE((SELECT oper_tx_nome FROM operacao WHERE oper_nb_id = enti_tx_tipoOperacao), '') <> 'Diretor' ORDER BY enti_tx_nome ASC"),
        combo("EPI", "busca_epi", $_POST["busca_epi"] ?? "", 3, $epiOptions)
    ];
    if ($temFiliais) {
        $fields[] = combo("Filial", "busca_filial", $_POST["busca_filial"] ?? "", 3, $empresaOptions);
    }
    $fields[] = combo("Status", "busca_status", $_POST["busca_status"] ?? "", 2, ["" => "Todos", "ativo" => "Entregue", "substituido" => "Substituído", "devolvido" => "Devolvido", "perdido" => "Perdido/Extraviado"]);

    $buttons = [];
    $buttons[] = botao("Buscar", "index");
    $buttons[] = botao("Lançar Entrega", "modificarEntrega", "", "", "", "", "btn btn-success");
    $buttons[] = botao("Gerenciar Kits", "listarKits", "", "", "", "", "btn btn-info");
    $buttons[] = '<button type="button" class="btn default" onclick="imprimirFichaEpi()">Imprimir Ficha</button>';

    $jsImprimir = "
        <script>
        var orderCol = 'ss_e_nb_id DESC';

        function imprimirFichaEpi() {
            var colSelect = document.getElementById('busca_colaborador');
            if (!colSelect || colSelect.value === '') {
                Swal.fire('Atenção', 'Selecione um colaborador na lista de filtros antes de imprimir a Ficha de EPI.', 'warning');
                return;
            }
            window.open('entrega_epi.php?acao=imprimirFicha&colaborador_id=' + colSelect.value, '_blank');
        }

        if (typeof window.verImagemMaior === 'undefined') {
            window.verImagemMaior = function(src) {
                Swal.fire({
                    imageUrl: src,
                    imageAlt: 'Imagem',
                    showConfirmButton: false,
                    showCloseButton: true,
                    background: '#fff',
                    backdrop: 'rgba(0,0,0,0.8)'
                });
            };
        }
        </script>
    ";

    echo abre_form("Filtros de Busca");
    echo linha_form($fields);
    echo fecha_form($buttons, $jsImprimir);

    $gridFields = [
        "CÓDIGO"              => "ss_e_nb_id",
        "COLABORADOR"         => "ss_c_tx_nome",
        "FILIAL"              => "filial_nome",
        "IMAGEM"              => "ss_grid_foto_render(ss_e_tx_foto_epi)",
        "EPI"                 => "epi_nome",
        "DATA ENTREGA"        => "ss_e_tx_data_entrega_formatado",
        "QUANTIDADE"          => "ss_e_nb_quantidade",
        "VENCIMENTO ESTIMADO" => "ss_e_tx_vencimento_formatado",
        "IDENTIFICADOR"       => "ss_e_tx_identificador",
        "EPI ENTREGUE"        => "ss_grid_foto_render(ss_e_tx_foto)",
        "OBSERVAÇÃO"          => "ss_e_tx_observacao"
    ];

    $camposBusca = [
        "busca_colaborador" => "ent.ss_e_nb_colaborador_id",
        "busca_epi"         => "ent.ss_e_nb_epi_id",
        "busca_status"      => "ent.ss_e_tx_status"
    ];

    $busca_filial = $_POST["busca_filial"] ?? "";
    $condFilial = "";
    if (!empty($busca_filial)) {
        $condFilial = " AND ent.ss_e_nb_empresa_id = " . (int)$busca_filial;
    }

    $queryBase = "SELECT * FROM (
                    SELECT ent.ss_e_nb_id, col.enti_tx_nome AS ss_c_tx_nome, IFNULL(emp.empr_tx_nome, 'Matriz') AS filial_nome, epi.ss_e_tx_foto AS ss_e_tx_foto_epi, CONCAT(epi.ss_e_tx_grupo, ' / ', IFNULL(epi.ss_e_tx_subgrupo, ''), ' / ', IFNULL(epi.ss_e_tx_item, '')) AS epi_nome, 
                           IFNULL(DATE_FORMAT(ent.ss_e_tx_data_entrega, '%d/%m/%Y'), '-') AS ss_e_tx_data_entrega_formatado, 
                           ent.ss_e_nb_quantidade, 
                           IFNULL(DATE_FORMAT(ent.ss_e_tx_vencimento, '%d/%m/%Y'), '-') AS ss_e_tx_vencimento_formatado, 
                           ent.ss_e_tx_status,
                           CASE ent.ss_e_tx_status 
                               WHEN 'ativo' THEN 'Entregue'
                               WHEN 'substituido' THEN 'Substituído'
                               WHEN 'devolvido' THEN 'Devolvido'
                               WHEN 'perdido' THEN 'Perdido/Extraviado'
                               ELSE ent.ss_e_tx_status 
                           END AS ss_e_tx_status_formatado, ent.ss_e_tx_foto, ent.ss_e_tx_observacao,
                           ent.ss_e_nb_colaborador_id, ent.ss_e_nb_epi_id,
                           IFNULL(s.id_documento, '-') AS ss_e_tx_identificador
                    FROM ss_epi_entrega ent 
                    JOIN entidade col ON ent.ss_e_nb_colaborador_id = col.enti_nb_id 
                    JOIN ss_epi epi ON ent.ss_e_nb_epi_id = epi.ss_e_nb_id
                    LEFT JOIN empresa emp ON ent.ss_e_nb_empresa_id = emp.empr_nb_id
                    LEFT JOIN solicitacoes_assinatura s ON ent.ss_e_nb_assinatura_id = s.id
                    WHERE ent.ss_e_tx_status <> 'inativo' {$condFilial}
                  ) AS ent";

    $gridFields["actions"] = [
        '<span class="fa fa-edit acao-editar-entrega" title="Alterar" style="color:#337ab7; cursor:pointer; font-size:16px; margin-right:8px;"></span>',
        '<span class="fa fa-trash acao-excluir-entrega" title="Excluir" style="color:#d9534f; cursor:pointer; font-size:16px;"></span>'
    ];

    $jsAcoes = '
        var funcoesInternas = function(){
            // Bind Alterar click
            $(".acao-editar-entrega").off("click").on("click", function(event) {
                var id = $(this).closest("tr").attr("data-row-id");
                submitPost("", { acao: "modificarEntrega", id: id });
            });

            // Bind Excluir click
            $(".acao-excluir-entrega").off("click").on("click", function(event) {
                var row = $(this).closest("tr");
                var id = row.attr("data-row-id");
                var colaborador = row.find("td").eq(1).text().trim(); // COLABORADOR is index 1
                
                Swal.fire({
                    title: "Justificativa de Exclusão",
                    html: "<div style=\"text-align: left;\">" +
                          "  <p>Deseja realmente excluir a entrega do colaborador <b>" + colaborador + "</b>?</p>" +
                          "  <div class=\"form-group\" style=\"margin-bottom: 15px;\">" +
                          "    <label for=\"swal_justificativa\">Justificativa*:</label>" +
                          "    <input id=\"swal_justificativa\" class=\"form-control\" placeholder=\"Motivo da exclusão...\" style=\"width: 100%;\">" +
                          "  </div>" +
                          "  <div class=\"checkbox\" style=\"margin-top: 15px; text-align: center;\">" +
                          "    <label>" +
                          "      <input type=\"checkbox\" id=\"swal_estornar\" checked> Estornar quantidade para o estoque" +
                          "    </label>" +
                          "  </div>" +
                          "</div>",
                    showCancelButton: true,
                    confirmButtonColor: "#d9534f",
                    cancelButtonColor: "#6c757d",
                    confirmButtonText: "Sim, excluir!",
                    cancelButtonText: "Cancelar",
                    preConfirm: () => {
                        const justificativa = document.getElementById("swal_justificativa").value;
                        const estornar = document.getElementById("swal_estornar").checked ? 1 : 0;
                        if (!justificativa || justificativa.trim() === "") {
                            Swal.showValidationMessage("Você precisa informar uma justificativa!");
                            return false;
                        }
                        return { justificativa: justificativa, estornar: estornar };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        var justificativa = result.value.justificativa;
                        var estornar = result.value.estornar;
                        submitPost("", { acao: "excluirEntrega", id: id, justificativa: justificativa, estornar: estornar });
                    }
                });
            });
        };

        if (typeof window.submitPost === "undefined") {
            window.submitPost = function(action, params) {
                var form = document.createElement("form");
                form.setAttribute("method", "post");
                form.setAttribute("action", action);
                for (var key in params) {
                    var input = document.createElement("input");
                    input.setAttribute("type", "hidden");
                    input.setAttribute("name", key);
                    input.setAttribute("value", params[key]);
                    form.appendChild(input);
                }
                $("form[name=\"contex_form\"] :input").each(function() {
                    if (this.name && this.value !== "" && this.name !== "acao" && params[this.name] === undefined) {
                        var input = document.createElement("input");
                        input.setAttribute("type", "hidden");
                        input.setAttribute("name", this.name);
                        input.setAttribute("value", this.value);
                        form.appendChild(input);
                    }
                });
                document.body.appendChild(form);
                form.submit();
            };
        }
    ';

    echo gridDinamico("tabelaEntregas", $gridFields, $camposBusca, $queryBase, $jsAcoes);

    rodape();
}

// --- CRUD DE KITS ---

function listarKits() {
    cabecalho("Gerenciamento de Kits de EPI");
    echo '<style>#btnExportPDF { display: none !important; }</style>';

    $fields = [
        campo("Nome do Kit", "busca_nome", $_POST["busca_nome"] ?? "", 4)
    ];

    $buttons = [];
    $buttons[] = botao("Buscar", "listarKits");
    $buttons[] = botao("Cadastrar Kit", "modificarKit", "", "", "", "", "btn btn-success");
    $buttons[] = botao("Voltar às Entregas", "index", "", "", "", "", "btn btn-default");

    echo abre_form("Filtros de Pesquisa");
    echo linha_form($fields);
    echo fecha_form($buttons);

    $gridFields = [
        "CÓDIGO"        => "ss_k_nb_id",
        "NOME"          => "ss_k_tx_nome",
        "TIPOS DE EPI"  => "qtd_tipos",
        "TOTAL DE EPIS" => "qtd_total",
        "EPIs NO KIT"   => "itens_detalhes",
        "STATUS"        => "ss_k_tx_status"
    ];

    $camposBusca = [
        "busca_nome" => "ss_k_tx_nome"
    ];

    $queryBase = "SELECT 
                    ss_k_nb_id, 
                    ss_k_tx_nome, 
                    ss_k_tx_status,
                    (SELECT COUNT(ki.ss_ki_nb_id) FROM ss_kit_item ki WHERE ki.ss_ki_nb_kit_id = ss_kit.ss_k_nb_id) AS qtd_tipos,
                    IFNULL((SELECT SUM(ki.ss_ki_nb_quantidade) FROM ss_kit_item ki WHERE ki.ss_ki_nb_kit_id = ss_kit.ss_k_nb_id), 0) AS qtd_total,
                    IFNULL((SELECT GROUP_CONCAT(CONCAT('• <small>', IFNULL(e.ss_e_tx_grupo, ''), ' / ', IFNULL(e.ss_e_tx_subgrupo, ''), ' / ', IFNULL(e.ss_e_tx_item, ''), '</small> <b>(x', ki.ss_ki_nb_quantidade, ')</b>') SEPARATOR '<br>') FROM ss_kit_item ki JOIN ss_epi e ON ki.ss_ki_nb_epi_id = e.ss_e_nb_id WHERE ki.ss_ki_nb_kit_id = ss_kit.ss_k_nb_id), '<span class=\"text-muted\">Nenhum item</span>') AS itens_detalhes
                  FROM ss_kit";

    $gridFields["actions"] = [
        '<span class="fa fa-edit acao-editar-kit" title="Alterar" style="color:#337ab7; cursor:pointer; font-size:16px; margin-right:8px;"></span>',
        '<span class="fa fa-ban acao-inativar-kit" title="Inativar/Ativar" style="color:#f0ad4e; cursor:pointer; font-size:16px; margin-right:8px;"></span>',
        '<span class="fa fa-trash acao-excluir-kit" title="Excluir" style="color:#d9534f; cursor:pointer; font-size:16px;"></span>'
    ];

    $jsAcoes = '
        var funcoesInternas = function(){
            // Bind Alterar click
            $(".acao-editar-kit").off("click").on("click", function(event) {
                var id = $(this).closest("tr").attr("data-row-id");
                submitPost("", { acao: "modificarKit", id: id });
            });

            // For each row, check status and customize the inativar/ativar icon
            $("#result tbody tr").each(function() {
                var row = $(this);
                var statusCell = row.find("td").eq(5); // STATUS column is index 5 (CÓDIGO, NOME, TIPOS, TOTAL, DETALHES, STATUS)
                var statusText = statusCell.text().trim().toLowerCase();
                
                var inativarIcon = row.find(".acao-inativar-kit");
                if (statusText.indexOf("inativo") >= 0 || statusText === "inativo") {
                    inativarIcon.removeClass("fa-ban").addClass("fa-check-circle");
                    inativarIcon.attr("title", "Ativar");
                    inativarIcon.css("color", "#5cb85c"); // green
                } else {
                    inativarIcon.removeClass("fa-check-circle").addClass("fa-ban");
                    inativarIcon.attr("title", "Inativar");
                    inativarIcon.css("color", "#f0ad4e"); // orange
                }
            });

            // Bind Inativar/Ativar click
            $(".acao-inativar-kit").off("click").on("click", function(event) {
                var row = $(this).closest("tr");
                var id = row.attr("data-row-id");
                var kitNome = row.find("td").eq(1).text().trim(); // NOME
                var statusCell = row.find("td").eq(5);
                var statusText = statusCell.text().trim().toLowerCase();
                
                var isCurrentlyInactive = (statusText.indexOf("inativo") >= 0 || statusText === "inativo");
                var acaoLabel = isCurrentlyInactive ? "ativar" : "inativar";
                var acaoPHP = isCurrentlyInactive ? "ativarKit" : "inativarKit";
                var confirmBtnColor = isCurrentlyInactive ? "#5cb85c" : "#f0ad4e";
                
                Swal.fire({
                    title: "Tem certeza?",
                    html: "Deseja " + acaoLabel + " o Kit <b>" + kitNome + "</b>?",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: confirmBtnColor,
                    cancelButtonColor: "#6c757d",
                    confirmButtonText: "Sim, " + acaoLabel + "!",
                    cancelButtonText: "Cancelar"
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitPost("", { acao: acaoPHP, id: id });
                    }
                });
            });

            // Bind Excluir click
            $(".acao-excluir-kit").off("click").on("click", function(event) {
                var row = $(this).closest("tr");
                var id = row.attr("data-row-id");
                var kitNome = row.find("td").eq(1).text().trim();
                
                Swal.fire({
                    title: "Tem certeza?",
                    html: "Deseja excluir permanentemente o Kit <b>" + kitNome + "</b>?<br><br><span style=\'color:#d9534f;\'><b>Atenção:</b> Isso excluirá o kit e todos os seus itens associados!</span>",
                    icon: "error",
                    showCancelButton: true,
                    confirmButtonColor: "#d9534f",
                    cancelButtonColor: "#6c757d",
                    confirmButtonText: "Sim, excluir!",
                    cancelButtonText: "Cancelar"
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitPost("", { acao: "excluirKit", id: id });
                    }
                });
            });
        };

        if (typeof window.submitPost === "undefined") {
            window.submitPost = function(action, params) {
                var form = document.createElement("form");
                form.setAttribute("method", "post");
                form.setAttribute("action", action);
                for (var key in params) {
                    var input = document.createElement("input");
                    input.setAttribute("type", "hidden");
                    input.setAttribute("name", key);
                    input.setAttribute("value", params[key]);
                    form.appendChild(input);
                }
                $("form[name=\"contex_form\"] :input").each(function() {
                    if (this.name && this.value !== "" && this.name !== "acao" && params[this.name] === undefined) {
                        var input = document.createElement("input");
                        input.setAttribute("type", "hidden");
                        input.setAttribute("name", this.name);
                        input.setAttribute("value", this.value);
                        form.appendChild(input);
                    }
                });
                document.body.appendChild(form);
                form.submit();
            };
        }
    ';

    echo gridDinamico("tabelaKits", $gridFields, $camposBusca, $queryBase, $jsAcoes);

    rodape();
}

function modificarKit() {
    if (!empty($_POST["id"])) {
        if (is_array($_POST["id"])) {
            $_POST["id"] = $_POST["id"][0];
        }
        $kit = carregar("ss_kit", $_POST["id"]);
        $_POST["nome_kit"] = $kit["ss_k_tx_nome"];
        $_POST["status"] = $kit["ss_k_tx_status"];

        $sqlItens = query("SELECT ss_ki_nb_epi_id, ss_ki_nb_quantidade 
                           FROM ss_kit_item 
                           WHERE ss_ki_nb_kit_id = " . (int)$_POST["id"]);
        $kitItens = [];
        if ($sqlItens) {
            while ($row = mysqli_fetch_assoc($sqlItens)) {
                $epi = carregar("ss_epi", $row["ss_ki_nb_epi_id"]);
                $epiNome = $epi["ss_e_tx_grupo"] . " / " . $epi["ss_e_tx_subgrupo"] . " / " . $epi["ss_e_tx_item"] . " (CA: " . ($epi["ss_e_tx_ca"] ?: 'N/A') . ")";
                $kitItens[] = [
                    "epi_id" => $row["ss_ki_nb_epi_id"],
                    "epi_nome" => $epiNome,
                    "quantidade" => $row["ss_ki_nb_quantidade"],
                    "foto" => $epi["ss_e_tx_foto"]
                ];
            }
        }
    } else {
        $kitItens = [];
    }

    // Carregar EPIs de estoque para o kit
    $sqlEpi = query("SELECT ss_e_nb_id, CONCAT(ss_e_tx_grupo, ' / ', IFNULL(ss_e_tx_subgrupo, ''), ' / ', IFNULL(ss_e_tx_item, ''), ' (CA: ', IFNULL(ss_e_tx_ca, 'N/A'), ')') AS epi_nome 
                     FROM ss_epi 
                     WHERE ss_e_tx_status = 'ativo' AND ss_e_tx_cadastro_tipo = 'estoque'
                     ORDER BY ss_e_tx_grupo ASC");
    $epiOptions = ["" => "Selecione o EPI"];
    if ($sqlEpi) {
        while ($row = mysqli_fetch_assoc($sqlEpi)) {
            $epiOptions[$row["ss_e_nb_id"]] = $row["epi_nome"];
        }
    }

    // Carregar mapa de fotos de EPIs
    $sqlEpisFotos = query("SELECT ss_e_nb_id, ss_e_tx_foto FROM ss_epi WHERE ss_e_tx_status = 'ativo' AND ss_e_tx_cadastro_tipo = 'estoque'");
    $epiFotosMap = [];
    if ($sqlEpisFotos) {
        while ($row = mysqli_fetch_assoc($sqlEpisFotos)) {
            $epiFotosMap[$row["ss_e_nb_id"]] = $row["ss_e_tx_foto"];
        }
    }

    cabecalho("Ficha de Kit de EPI");

    $campo_nome   = campo("Nome do Kit*", "nome_kit", $_POST["nome_kit"] ?? "", 6, "", "maxlength='100'");
    $campo_status = combo("Status", "status", $_POST["status"] ?? "ativo", 3, ["ativo" => "Ativo", "inativo" => "Inativo"]);
    
    $campo_epi = combo("EPI para Adicionar", "kit_epi_id", "", 6, $epiOptions);
    $campo_qtd = campo("Quantidade do Item", "kit_epi_qtd", "1", 2, "MASCARA_NUMERO");
    $btn_add   = '<div class="col-sm-4 margin-bottom-5 campo-fit-content" style="margin-top:23px;"><button type="button" class="btn btn-primary" id="btn_add_epi_kit">Adicionar ao Kit</button></div>';

    echo abre_form("Dados Gerais do Kit");
    echo linha_form([$campo_nome, $campo_status]);
    echo fecha_form([]);

    echo abre_form("Composição do Kit");
    echo linha_form([$campo_epi, $campo_qtd, $btn_add]);
    
    echo "
    <div class='table-responsive' style='margin-top: 15px;'>
        <table class='table table-striped table-bordered table-hover' id='tabela_itens_kit'>
            <thead>
                <tr>
                    <th style='width: 80px;'>Imagem</th>
                    <th>EPI</th>
                    <th style='width: 150px;'>Quantidade</th>
                    <th style='width: 100px;'>Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- Preenchido via JS -->
            </tbody>
        </table>
    </div>
    ";
    echo fecha_form([]);

    // Form final de envio
    echo abre_form("Salvar Kit");
    echo '<input type="hidden" name="kit_itens_json" id="kit_itens_json" value="">';
    echo '<input type="hidden" name="id" value="' . ($_POST["id"] ?? "") . '">';
    echo '<input type="hidden" name="nome_kit" id="final_nome_kit" value="">';
    echo '<input type="hidden" name="status" id="final_status" value="">';

    $final_buttons = [];
    $final_buttons[] = botao("Salvar Kit", "cadastrarKit", "", "", "", "", "btn btn-success");
    $final_buttons[] = botao("Voltar", "listarKits", "", "", "", "", "btn btn-default");
    echo fecha_form($final_buttons);

    echo "
    <script>
    $(document).ready(function() {
        $('#kit_itens_json').closest('form').attr('name', 'form_salvar_kit');
        if (typeof $.fn.select2 === 'function') {
            $.fn.select2.defaults.set('theme', 'bootstrap');
            $('select[name=\"kit_epi_id\"]').select2();
        }

        let kitItens = " . json_encode($kitItens) . ";
        const epiFotosMap = " . json_encode($epiFotosMap) . ";

        if (typeof window.verImagemMaior === 'undefined') {
            window.verImagemMaior = function(src) {
                Swal.fire({
                    imageUrl: src,
                    imageAlt: 'Imagem do EPI',
                    showConfirmButton: false,
                    showCloseButton: true,
                    background: '#fff',
                    backdrop: 'rgba(0,0,0,0.8)'
                });
            };
        }

        function renderKitTable() {
            const tbody = $('#tabela_itens_kit tbody');
            tbody.empty();
            
            if (kitItens.length === 0) {
                tbody.append('<tr><td colspan=\"4\" style=\"text-align: center; color: #999;\">Nenhum item adicionado ao kit.</td></tr>');
                $('#kit_itens_json').val('');
                return;
            }
            
            kitItens.forEach((item, index) => {
                const row = $('<tr>');
                
                let fotoHtml = '<span class=\"text-muted\">-</span>';
                if (item.foto) {
                    let resolvedSrc = ssResolveFotoUrl(item.foto);
                    fotoHtml = '<img src=\"' + resolvedSrc + '\" class=\"thumbnail-kit-item\" onclick=\"verImagemMaior(\'' + resolvedSrc + '\')\" style=\"max-height: 40px; max-width: 40px; border-radius: 4px; border: 1px solid #ccc; cursor: pointer; object-fit: cover;\" title=\"Clique para ampliar\">';
                }
                row.append($('<td>').html(fotoHtml));
                
                row.append($('<td>').text(item.epi_nome));
                row.append($('<td>').text(item.quantidade));
                
                const actionsTd = $('<td>');
                const deleteBtn = $('<button type=\"button\" class=\"btn btn-xs btn-danger\"><i class=\"fa fa-trash\"></i></button>');
                deleteBtn.on('click', function() {
                    kitItens.splice(index, 1);
                    renderKitTable();
                });
                actionsTd.append(deleteBtn);
                row.append(actionsTd);
                tbody.append(row);
            });
            
            $('#kit_itens_json').val(JSON.stringify(kitItens));
        }

        $('#btn_add_epi_kit').on('click', function() {
            const epiSelect = $('select[name=\"kit_epi_id\"]');
            const epiId = epiSelect.val();
            const epiNome = epiSelect.find('option:selected').text();
            const qtd = parseInt($('#kit_epi_qtd').val(), 10) || 0;
            
            if (!epiId) {
                Swal.fire('Atenção', 'Selecione um EPI para adicionar.', 'warning');
                return;
            }
            if (qtd <= 0) {
                Swal.fire('Atenção', 'A quantidade deve ser maior que zero.', 'warning');
                return;
            }
            
            const exists = kitItens.some(item => item.epi_id == epiId);
            if (exists) {
                Swal.fire('Atenção', 'Este EPI já foi adicionado ao kit.', 'warning');
                return;
            }
            
            const fotoPath = epiFotosMap[epiId] || '';
            kitItens.push({
                epi_id: epiId,
                epi_nome: epiNome,
                quantidade: qtd,
                foto: fotoPath
            });
            
            epiSelect.val('').trigger('change');
            $('#kit_epi_qtd').val('1');
            renderKitTable();
        });

        const initialKitItensStr = JSON.stringify(kitItens);
        const initialNomeKit = $('input[name=\"nome_kit\"]').val() || '';
        const initialStatus = $('select[name=\"status\"]').val() || 'ativo';
        let bypassKitValidation = false;

        $('form[name=\"form_salvar_kit\"]').on('submit', function(e) {
            if (bypassKitValidation) {
                return;
            }

            const activeBtn = $(document.activeElement);
            if (activeBtn.attr('name') === 'acao' && activeBtn.val() === 'listarKits') {
                const currentKitItensStr = JSON.stringify(kitItens);
                const currentNomeKit = $('input[name=\"nome_kit\"]').val() || '';
                const currentStatus = $('select[name=\"status\"]').val() || 'ativo';
                
                const hasChanges = (initialKitItensStr !== currentKitItensStr) || 
                                    (initialNomeKit !== currentNomeKit) || 
                                    (initialStatus !== currentStatus);
                
                if (hasChanges) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Sair sem salvar?',
                        text: 'Existem alterações não salvas no Kit. Deseja realmente voltar?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Sim, sair',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            bypassKitValidation = true;
                            var form = $('form[name=\"form_salvar_kit\"]');
                            var inputAcao = $('<input type=\"hidden\" name=\"acao\" value=\"listarKits\">');
                            form.append(inputAcao);
                            form.submit();
                        }
                    });
                }
                return;
            }

            const nomeKit = $('input[name=\"nome_kit\"]').val();
            if (!nomeKit || nomeKit.trim() === '') {
                e.preventDefault();
                Swal.fire('Atenção', 'O campo Nome do Kit é obrigatório.', 'warning');
                return;
            }
            if (kitItens.length === 0) {
                e.preventDefault();
                Swal.fire('Atenção', 'Adicione pelo menos um item à composição do kit.', 'warning');
                return;
            }
            
            $('#final_nome_kit').val(nomeKit);
            $('#final_status').val($('select[name=\"status\"]').val());
        });

        renderKitTable();
    });
    </script>
    ";

    rodape();
}

function cadastrarKit() {
    $camposObrig = [
        "nome_kit" => "Nome do Kit"
    ];
    $errorMsg = conferirCamposObrig($camposObrig, $_POST);
    if (!empty($errorMsg)) {
        set_status("ERRO: {$errorMsg}");
        modificarKit();
        exit;
    }

    $nome_kit = $_POST["nome_kit"];
    $status = $_POST["status"] ?? "ativo";
    $userCadastro = $_SESSION["user_nb_id"] ?? 0;
    $dataCadastro = date("Y-m-d H:i:s");

    $kit = [
        "ss_k_tx_nome" => $nome_kit,
        "ss_k_tx_status" => $status
    ];

    if (empty($_POST["id"])) {
        $kit["ss_k_nb_userCadastro"] = $userCadastro;
        $kit["ss_k_tx_dataCadastro"] = $dataCadastro;
        $res = inserir("ss_kit", array_keys($kit), array_values($kit));
        $kitId = $res[0];
    } else {
        $kitId = (int)$_POST["id"];
        $kit["ss_k_nb_userAtualiza"] = $userCadastro;
        $kit["ss_k_tx_dataAtualiza"] = $dataCadastro;
        atualizar("ss_kit", array_keys($kit), array_values($kit), $kitId);
    }

    // Atualizar itens
    query("DELETE FROM ss_kit_item WHERE ss_ki_nb_kit_id = {$kitId}");

    $itens = json_decode($_POST["kit_itens_json"] ?? "[]", true);
    if (is_array($itens)) {
        foreach ($itens as $item) {
            $epiId = (int)$item["epi_id"];
            $qtd = (int)$item["quantidade"];
            if ($epiId > 0 && $qtd > 0) {
                query("INSERT INTO ss_kit_item (ss_ki_nb_kit_id, ss_ki_nb_epi_id, ss_ki_nb_quantidade) 
                       VALUES ({$kitId}, {$epiId}, {$qtd})");
            }
        }
    }

    set_status("Kit salvo com sucesso!");
    listarKits();
    exit;
}

function excluirKit() {
    if (!empty($_POST["id"])) {
        $id = (int)$_POST["id"];
        query("DELETE FROM ss_kit WHERE ss_k_nb_id = {$id}");
        query("DELETE FROM ss_kit_item WHERE ss_ki_nb_kit_id = {$id}");
        set_status("Kit excluído permanentemente com sucesso!");
    }
    listarKits();
    exit;
}

function inativarKit() {
    if (!empty($_POST["id"])) {
        $id = (int)$_POST["id"];
        atualizar("ss_kit", ["ss_k_tx_status"], ["inativo"], $id);
        set_status("Kit inativado com sucesso!");
    }
    listarKits();
    exit;
}

function ativarKit() {
    if (!empty($_POST["id"])) {
        $id = (int)$_POST["id"];
        atualizar("ss_kit", ["ss_k_tx_status"], ["ativo"], $id);
        set_status("Kit ativado com sucesso!");
    }
    listarKits();
    exit;
}
?>
