<?php
/**
 * DIAGNÓSTICO DE ESCRITA - Execute em produção para identificar o problema
 * URL: https://seu-servidor.com/armazem_paraiba/diagnose.php
 */

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='background:#f0f0f0; padding:20px; font-family:monospace; white-space:pre-wrap;'>\n";
echo "=== DIAGNÓSTICO DE ESCRITA EM PRODUÇÃO ===\n\n";

// Info básicas
echo "PHP_VERSION: " . phpversion() . "\n";
echo "USUARIO: " . get_current_user() . "\n";
echo "PID: " . getmypid() . "\n";
echo "platform: " . php_uname() . "\n";
echo "CWD: " . getcwd() . "\n\n";

// Detalhes de ambiente
echo "=== OPEN_BASEDIR ===\n";
$openBasedir = ini_get('open_basedir');
echo ($openBasedir ? "ATIVO: $openBasedir\n" : "NÃO CONFIGURADO\n");

echo "\n=== PATHS A TESTAR ===\n";
$paths = [
    "logs_dir" => __DIR__ . '/logs',
    "debug_log_raiz" => __DIR__ . '/debug_log.txt',
    "debug_log_nome_expandido" => __DIR__ . '/debug_log_diagnose_' . date('Y-m-d_H-i-s') . '.txt',
    "temp_dir" => sys_get_temp_dir(),
    "error_log_php" => ini_get('error_log') ?: "(não configurado)",
];

// Testa cada caminho
foreach($paths as $nome => $caminho){
    echo "\n[$nome]\n";
    echo "  Path: $caminho\n";
    
    if(strpos($nome, 'error_log') !== false){
        echo "  Status: " . ($caminho !== "(não configurado)" ? "CONFIGURADO em $caminho" : $caminho) . "\n";
        continue;
    }
    
    $isDir = is_dir($caminho);
    echo "  Is Dir: " . ($isDir ? "SIM" : "NÃO") . "\n";
    
    if($isDir){
        $perms = substr(sprintf('%o', fileperms($caminho)), -4);
        echo "  Perms: $perms\n";
        echo "  Readable: " . (is_readable($caminho) ? "SIM" : "NÃO") . "\n";
        echo "  Writable: " . (is_writable($caminho) ? "SIM" : "NÃO") . "\n";
    } else {
        // Tenta criar a pasta
        echo "  Tentando criar folder...\n";
        $created = @mkdir($caminho, 0755, true);
        if($created){
            echo "    ✓ Pasta criada com sucesso!\n";
            $perms = substr(sprintf('%o', fileperms($caminho)), -4);
            echo "    Perms: $perms\n";
            echo "    Writable: " . (is_writable($caminho) ? "SIM" : "NÃO") . "\n";
        } else {
            echo "    ✗ FALHOU ao criar pasta\n";
        }
    }
}

echo "\n=== TESTE DE ESCRITA AGRESSIVO ===\n";

$testData = "TEST_" . date('Y-m-d H:i:s') . "_" . uniqid() . "\n";
$writeTests = [
    "logs_dir/test.log" => __DIR__ . '/logs/test_' . date('Y-m-d') . '.log',
    "debug_log.txt" => __DIR__ . '/debug_log.txt',
    "debug_log_novo.txt" => __DIR__ . '/debug_log_novo_' . date('Y-m-d_H-i-s') . '.txt',
    "temp_dir" => sys_get_temp_dir() . '/dev_techps_test_' . date('Y-m-d_H-i-s') . '.log',
];

foreach($writeTests as $nome => $arquivo){
    echo "\n[$nome]\n";
    echo "  Path: $arquivo\n";
    
    // Garante que o folder existe
    $folder = dirname($arquivo);
    if(!is_dir($folder)){
        @mkdir($folder, 0755, true);
    }
    
    // Tenta escrever
    $result = @file_put_contents($arquivo, $testData, FILE_APPEND);
    if($result !== false){
        echo "  ✓ file_put_contents: OK (bytes: $result)\n";
    } else {
        echo "  ✗ file_put_contents: FALHOU\n";
        
        // Fallback: error_log
        $errorLogResult = error_log($testData, 3, $arquivo);
        echo "    Tentando error_log(..., 3, ...): " . ($errorLogResult ? "OK" : "FALHOU") . "\n";
    }
    
    // Valida se arquivo foi criado
    if(file_exists($arquivo)){
        $size = filesize($arquivo);
        echo "  ✓ Arquivo criado: SIM (size: $size bytes)\n";
        echo "  Conteúdo (últimas 2 linhas):\n";
        $lines = array_slice(file($arquivo), -2);
        foreach($lines as $line){
            echo "    > " . trim($line) . "\n";
        }
    } else {
        echo "  ✗ Arquivo criado: NÃO\n";
    }
}

echo "\n=== TESTE COM $_SESSION ===\n";
session_start();
echo "SESSION_ID: " . session_id() . "\n";
echo "SESSION_USER: " . ($_SESSION['user_nb_id'] ?? 'NÃO CONFIGURADO') . "\n";

echo "\n=== RESUMO ===\n";
echo "Se nenhum arquivo foi criado acima:\n";
echo "  1. Verificar permissões da pasta com: ls -la armazem_paraiba/\n";
echo "  2. Verificar usuário do Apache/PHP: ps aux | grep apache\n";
echo "  3. Verificar logs do PHP/Apache em: /var/log/apache2/ ou /var/log/php-fpm/\n";
echo "  4. Testar escrita manual: touch armazem_paraiba/test.txt\n";
echo "\nSe algum arquivo foi criado:\n";
echo "  5. O problema está em saldo.php - precisa de ajustes\n";

echo "\n</pre>";
?>
