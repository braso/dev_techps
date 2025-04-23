<?php
// Configurações
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desativar exibição de erros (log apenas)
ini_set('log_errors', 1);
ini_set('error_log', './arquivos/php_errors.log');

// Função para registrar logs com PID (Process ID)
function logMessage($message) {
    $pid = getmypid();
    file_put_contents(
        './arquivos/graficos_salvos.log', 
        date('[Y-m-d H:i:s] ') . "[PID:$pid] " . $message . PHP_EOL, 
        FILE_APPEND
    );
}

// Função para obter nome único de arquivo
function gerarNomeUnico($elementId, $dataGrafc, $userEntrada) {
    $pid = getmypid();
    $microtime = microtime(true);
    return sprintf(
        'grafico_%s_%s_%s.png',
        $elementId,
        $dataGrafc,
        $userEntrada,
    );
}

try {
    logMessage('Iniciando processamento da imagem');
    
    // Verificar dados obrigatórios
    $required = ['grafico', 'elementId', 'userEntrada', 'dataGrafc'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Campo {$field} é obrigatório");
        }
    }

    // Sanitizar entradas
    $elementId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['elementId']);
    $userEntrada = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['userEntrada']);
    $dataGrafc = preg_replace('/[^0-9-]/', '', $_POST['dataGrafc']);
    $imagemData = $_POST['grafico'];

    logMessage("Dados recebidos - Elemento: {$elementId}, Usuário: {$userEntrada}, Data: {$dataGrafc}");

    // Validar imagem
    if (strpos($imagemData, 'data:image/png;base64,') !== 0) {
        throw new Exception('Formato de imagem inválido');
    }

    // Decodificar imagem
    $imagemData = base64_decode(substr($imagemData, 22));
    if ($imagemData === false) {
        throw new Exception('Falha ao decodificar imagem');
    }

    // Criar diretório se não existir
    $diretorio = './arquivos/graficos/';
    if (!file_exists($diretorio)) {
        if (!mkdir($diretorio, 0755, true)) {
            throw new Exception('Falha ao criar diretório');
        }
        logMessage('Diretório criado: ' . $diretorio);
    }

    // Obter nome único para o arquivo
    $nomeArquivo = gerarNomeUnico($elementId, $dataGrafc, $userEntrada);
    $caminhoCompleto = $diretorio . $nomeArquivo;

    // Sistema de lock para escrita concorrente
    $lockFile = $diretorio . 'lock_' . md5($nomeArquivo);
    $lockHandle = fopen($lockFile, 'w+');

    if (!flock($lockHandle, LOCK_EX)) { // Lock exclusivo
        throw new Exception('Não foi possível obter lock para escrita');
    }

    try {
        // Tentar salvar o arquivo
        $tentativas = 0;
        $maxTentativas = 3;
        $salvo = false;

        while ($tentativas < $maxTentativas && !$salvo) {
            $tentativas++;
            $salvo = file_put_contents($caminhoCompleto, $imagemData);
            
            if (!$salvo && $tentativas < $maxTentativas) {
                usleep(rand(100000, 500000)); // Espera aleatória entre tentativas
                logMessage("Tentativa {$tentativas} falhou - nova tentativa");
            }
        }

        if (!$salvo) {
            throw new Exception("Falha ao salvar após {$tentativas} tentativas");
        }

        logMessage("Arquivo salvo com sucesso: {$caminhoCompleto}");

        // Resposta de sucesso
        echo json_encode([
            'status' => 'success',
            'message' => 'Gráfico salvo com sucesso',
            'fileName' => $nomeArquivo,
            'filePath' => $caminhoCompleto,
            'fileUrl' => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . 
                         $_SERVER['HTTP_HOST'] . 
                         str_replace($_SERVER['DOCUMENT_ROOT'], '', $caminhoCompleto)
        ]);

    } finally {
        // Liberar lock
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        unlink($lockFile);
    }

} catch (Exception $e) {
    http_response_code(500);
    $errorMsg = $e->getMessage();
    logMessage("ERRO: {$errorMsg}");
    
    echo json_encode([
        'status' => 'error',
        'message' => $errorMsg
    ]);
}
?>