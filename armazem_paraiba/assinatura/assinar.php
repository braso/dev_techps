<?php
// Configuração de tratamento de erros para debug
ini_set('display_errors', 0); // Não exibir erros no output padrão (quebra o JSON)
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Função de log em arquivo para debug
function logDebug($msg) {
    $logFile = __DIR__ . '/log_assinar.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $msg" . PHP_EOL, FILE_APPEND);
}

logDebug("Iniciando script assinar.php");

// Inicia buffer de saída para evitar que erros/warnings quebrem o JSON
ob_start();

header('Content-Type: application/json');

// Função para capturar erros fatais e retornar JSON
function shutdownHandler() {
    $error = error_get_last();
    $output = ob_get_contents();
    ob_end_clean(); // Limpa o buffer atual

    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Erro fatal
        logDebug("ERRO FATAL: " . $error['message'] . " em " . $error['file'] . ":" . $error['line']);
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro fatal no servidor: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    } elseif (!empty($output) && (empty($output[0]) || $output[0] !== '{')) {
        // Se houve saída mas não parece ser JSON (provavelmente erro do PHP, warning ou die())
        logDebug("SAÍDA INESPERADA: " . strip_tags($output));
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro inesperado: ' . strip_tags($output) // Remove tags HTML se houver
        ]);
    } else {
        // Saída normal (JSON válido)
        echo $output;
    }
}
register_shutdown_function('shutdownHandler');

try {
    // Define interno como true para evitar redirecionamento de login/logout no conecta.php
    $interno = true;

    logDebug("Incluindo conecta.php");
    // Inclui a conexão com o banco de dados
    if (!file_exists("../conecta.php")) {
        throw new Exception("Arquivo de conexão (../conecta.php) não encontrado.");
    }
    include "../conecta.php"; 
    logDebug("Conecta.php incluído com sucesso");

    logDebug("Incluindo email_config.php");
    include "email_config.php";
    logDebug("email_config.php incluído com sucesso");

    // Carrega bibliotecas do PHPMailer
    logDebug("Carregando PHPMailer");
    $phpmailerPath = __DIR__ . '/../../PHPMailer/src/';
    if (!file_exists($phpmailerPath . 'Exception.php')) throw new Exception("PHPMailer Exception.php não encontrado");
    if (!file_exists($phpmailerPath . 'PHPMailer.php')) throw new Exception("PHPMailer PHPMailer.php não encontrado");
    if (!file_exists($phpmailerPath . 'SMTP.php')) throw new Exception("PHPMailer SMTP.php não encontrado");

    require $phpmailerPath . 'Exception.php';
    require $phpmailerPath . 'PHPMailer.php';
    require $phpmailerPath . 'SMTP.php';
    logDebug("PHPMailer carregado");

    require_once 'email_helper.php';
    
    // use PHPMailer\PHPMailer\PHPMailer; // REMOVIDO: use deve ser global
    // use PHPMailer\PHPMailer\Exception; // REMOVIDO: use deve ser global
    
    if (!isset($conn)) {
        throw new Exception("Falha na conexão com o banco de dados.");
    }

    // Auto-correção: Verifica se a tabela 'assinatura_eletronica' existe e cria se necessário
    logDebug("Verificando tabela assinatura_eletronica");
    $checkTable = "SHOW TABLES LIKE 'assinatura_eletronica'";
    $resultTable = mysqli_query($conn, $checkTable);
    if (mysqli_num_rows($resultTable) == 0) {
        logDebug("Criando tabela assinatura_eletronica");
        $sqlCreateTable = "CREATE TABLE IF NOT EXISTS assinatura_eletronica (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_documento VARCHAR(255) NOT NULL,
            cpf VARCHAR(20) NOT NULL,
            rg VARCHAR(20),
            ip_address VARCHAR(45),
            latitude VARCHAR(50),
            longitude VARCHAR(50),
            user_agent TEXT,
            device_info TEXT,
            data_assinatura DATETIME DEFAULT CURRENT_TIMESTAMP,
            hash_assinatura VARCHAR(64) NOT NULL,
            caminho_arquivo VARCHAR(255)
        )";
        if (!mysqli_query($conn, $sqlCreateTable)) {
             throw new Exception("Erro ao criar tabela 'assinatura_eletronica': " . mysqli_error($conn));
        }
    }

    // Auto-correção: Garantir que coluna status seja VARCHAR para aceitar 'concluido'
    $tablesToFix = ['solicitacoes_assinatura', 'assinantes'];
    foreach ($tablesToFix as $tableName) {
        // Log para debug
        logDebug("Verificando tabela $tableName...");
        $checkTable = "SHOW TABLES LIKE '$tableName'";
        $resCheckTable = mysqli_query($conn, $checkTable);
        
        if (mysqli_num_rows($resCheckTable) > 0) {
            $checkCol = "SHOW COLUMNS FROM $tableName LIKE 'status'";
            $resCol = mysqli_query($conn, $checkCol);
            
            if ($rowCol = mysqli_fetch_assoc($resCol)) {
                $type = strtoupper($rowCol['Type']);
                logDebug("Coluna status da tabela $tableName é do tipo: $type");
                
                // Se NÃO for VARCHAR(50) exato, força a alteração
                if ($type !== 'VARCHAR(50)') {
                    logDebug("Alterando coluna status da tabela $tableName para VARCHAR(50)...");
                    $sqlAlter = "ALTER TABLE $tableName MODIFY COLUMN status VARCHAR(50) DEFAULT 'pendente'";
                    if (mysqli_query($conn, $sqlAlter)) {
                        logDebug("Sucesso ao alterar tabela $tableName.");
                    } else {
                        logDebug("ERRO ao alterar tabela $tableName: " . mysqli_error($conn));
                    }
                } else {
                    logDebug("Tabela $tableName já está correta.");
                }
            } else {
                logDebug("Coluna status não encontrada na tabela $tableName.");
            }
        } else {
            logDebug("Tabela $tableName não encontrada.");
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        logDebug("Processando POST");
        // Limpa buffer antes de processar para garantir que warnings não sujem o JSON
        ob_clean(); 
        
        // Recebimento dos dados
        $id_documento = $_POST['id_documento'] ?? '';
        $cpf = $_POST['cpf'] ?? '';
        $rg = $_POST['rg'] ?? '';
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;
        $device_info_client = $_POST['device_info'] ?? '';
        $token_solicitacao = $_POST['token_solicitacao'] ?? '';

        // Dados de Auditoria do Servidor
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT']; 
        $data_hora = date('Y-m-d H:i:s');

        // Validação básica
        if (empty($cpf) || empty($rg)) {
            echo json_encode(['status' => 'error', 'message' => 'Dados incompletos (CPF ou RG faltando).']);
            exit;
        }

        // Se não tiver ID do documento (caso de upload novo), gera um
        if (empty($id_documento)) {
            $id_documento = 'DOC-' . date('YmdHis') . '-' . uniqid();
        }
        
        // Gera um hash único da assinatura
        $hash_assinatura = $_POST['hash_assinatura'] ?? '';
        $data_hora_cliente = $_POST['data_hora'] ?? '';

        if (!empty($data_hora_cliente)) {
            $timestamp = strtotime($data_hora_cliente);
            if ($timestamp) {
                $data_hora = date('Y-m-d H:i:s', $timestamp);
            } else {
                 $data_hora = date('Y-m-d H:i:s');
            }
        }
        
        if (empty($hash_assinatura)) {
            $salt = "AssinaturaDigitalTechPS2024";
            $hash_assinatura = md5($id_documento . $cpf . $data_hora . $salt);
        }
        
        // Processamento do Arquivo Assinado (Upload)
        $caminho_arquivo_final = null;
        if (isset($_FILES['arquivo_assinado']) && $_FILES['arquivo_assinado']['error'] === UPLOAD_ERR_OK) {
            logDebug("Processando upload de arquivo");
            $uploadDir = __DIR__ . '/docAssinado/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $extension = pathinfo($_FILES['arquivo_assinado']['name'], PATHINFO_EXTENSION);
            // Nome do arquivo com timestamp para evitar cache e conflito
            $fileName = 'assinado_' . time() . '_' . $hash_assinatura . '.' . $extension;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['arquivo_assinado']['tmp_name'], $targetPath)) {
                $caminho_arquivo_final = 'docAssinado/' . $fileName;
                logDebug("Arquivo salvo em: " . $caminho_arquivo_final);
            } else {
                 logDebug("Falha ao mover arquivo assinado.");
                 error_log("Falha ao mover arquivo assinado.");
            }
        }

        // ---------------------------------------------------------
        // Lógica de Assinatura (Multi-Signatário vs Legado)
        // ---------------------------------------------------------

        $isMultiSignatario = false;
        $dadosAssinante = null;

        // Verifica se o token pertence à tabela de assinantes
        if (!empty($token_solicitacao)) {
            logDebug("Verificando token solicitacao: " . $token_solicitacao);
            $sqlCheckAssinante = "SELECT * FROM assinantes WHERE token = ?";
            $stmtCheck = mysqli_prepare($conn, $sqlCheckAssinante);
            mysqli_stmt_bind_param($stmtCheck, "s", $token_solicitacao);
            mysqli_stmt_execute($stmtCheck);
            $resCheck = mysqli_stmt_get_result($stmtCheck);
            if ($row = mysqli_fetch_assoc($resCheck)) {
                $isMultiSignatario = true;
                $dadosAssinante = $row;
                logDebug("Assinante encontrado: " . $row['nome']);
            } else {
                logDebug("Token não encontrado na tabela assinantes");
            }
        }

        $id_assinatura_inserida = 0;

        // 1. Inserção na Tabela de Auditoria (assinatura_eletronica) - Sempre ocorre
        logDebug("Inserindo auditoria");
        $sql = "INSERT INTO assinatura_eletronica (
                    id_documento, cpf, rg, ip_address, latitude, longitude, 
                    user_agent, device_info, data_assinatura, hash_assinatura, caminho_arquivo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssssssss", 
                $id_documento, $cpf, $rg, $ip_address, $latitude, $longitude, 
                $user_agent, $device_info_client, $data_hora, $hash_assinatura, $caminho_arquivo_final
            );
            if (mysqli_stmt_execute($stmt)) {
                $id_assinatura_inserida = mysqli_insert_id($conn);
                logDebug("Auditoria inserida com ID: " . $id_assinatura_inserida);
            } else {
                logDebug("Erro ao salvar auditoria: " . mysqli_error($conn));
                throw new Exception("Erro ao salvar auditoria: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
        }

        // 2. Atualização do Fluxo
        if ($isMultiSignatario && $dadosAssinante) {
            // --- FLUXO MULTI-SIGNATÁRIO ---
            logDebug("Iniciando fluxo multi-signatário");
            
            // a) Atualiza status do assinante atual
            $sqlUpdateAssinante = "UPDATE assinantes SET status = 'assinado', data_assinatura = ?, ip = ?, metadados = ? WHERE id = ?";
            $metadados = json_encode(['user_agent' => $user_agent, 'lat' => $latitude, 'long' => $longitude, 'hash' => $hash_assinatura]);
            $stmtUp = mysqli_prepare($conn, $sqlUpdateAssinante);
            mysqli_stmt_bind_param($stmtUp, "sssi", $data_hora, $ip_address, $metadados, $dadosAssinante['id']);
            mysqli_stmt_execute($stmtUp);
            
            // b) Atualiza o arquivo no processo pai (para o próximo assinar a versão atualizada)
            $idSolicitacao = $dadosAssinante['id_solicitacao'];
            if ($caminho_arquivo_final) {
                $sqlUpdateSol = "UPDATE solicitacoes_assinatura SET caminho_arquivo = ?, id_documento = ? WHERE id = ?";
                $stmtSol = mysqli_prepare($conn, $sqlUpdateSol);
                mysqli_stmt_bind_param($stmtSol, "ssi", $caminho_arquivo_final, $id_documento, $idSolicitacao);
                mysqli_stmt_execute($stmtSol);
            }

            // c) Verifica Próximo Signatário
            $ordemAtual = $dadosAssinante['ordem'];
            $sqlProx = "SELECT * FROM assinantes WHERE id_solicitacao = ? AND ordem > ? ORDER BY ordem ASC LIMIT 1";
            $stmtProx = mysqli_prepare($conn, $sqlProx);
            mysqli_stmt_bind_param($stmtProx, "ii", $idSolicitacao, $ordemAtual);
            mysqli_stmt_execute($stmtProx);
            $resProx = mysqli_stmt_get_result($stmtProx);
            $proximo = mysqli_fetch_assoc($resProx);

            if ($proximo) {
                // --- EXISTE PRÓXIMO: Notificar ---
                logDebug("Encontrado próximo signatário: " . $proximo['nome']);
                enviarEmailProximo($proximo['email'], $proximo['nome'], $proximo['token'], $dadosAssinante['nome_arquivo_original'] ?? 'Documento', $id_documento, $proximo['funcao'], $caminho_arquivo_final);
                
                $msgRetorno = "Assinatura registrada! Um e-mail foi enviado para o próximo signatário (" . $proximo['funcao'] . ").";
                
            } else {
                // --- NÃO EXISTE PRÓXIMO: Finalizar ---
                logDebug("Último signatário. Finalizando processo.");
                $sqlUpdateFinal = "UPDATE solicitacoes_assinatura SET status = 'concluido', data_assinatura = ? WHERE id = ?";
                $stmtFinal = mysqli_prepare($conn, $sqlUpdateFinal);
                mysqli_stmt_bind_param($stmtFinal, "si", $data_hora, $idSolicitacao);
                mysqli_stmt_execute($stmtFinal);

                // Enviar e-mail final para TODOS
                logDebug("Enviando e-mail de finalização.");
                enviarEmailFinalizacao($conn, $idSolicitacao, $id_documento, $caminho_arquivo_final);
                
                $msgRetorno = "Processo concluído! Todos os signatários assinaram.";
            }

        } else {
            // --- FLUXO LEGADO (Único) ---
            logDebug("Fluxo legado (único)");
            if (!empty($token_solicitacao)) {
                $sql_update = "UPDATE solicitacoes_assinatura SET status = 'assinado', data_assinatura = ?, id_documento = ?, caminho_arquivo = ? WHERE token = ?";
                $stmt_update = mysqli_prepare($conn, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "ssss", $data_hora, $id_documento, $caminho_arquivo_final, $token_solicitacao);
                mysqli_stmt_execute($stmt_update);
            }
            $msgRetorno = "Assinatura realizada com sucesso.";
        }

        // Retorno JSON Sucesso
        logDebug("Sucesso. Retornando JSON.");
        echo json_encode([
            'status' => 'success',
            'message' => $msgRetorno,
            'protocolo' => $hash_assinatura,
            'id_assinatura' => $id_assinatura_inserida,
            'data_hora' => $data_hora,
            'caminho_arquivo' => $caminho_arquivo_final
        ]);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
    }

} catch (Exception $e) {
    logDebug("EXCEÇÃO CAPTURADA: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro exceção: ' . $e->getMessage()
    ]);
}

?>