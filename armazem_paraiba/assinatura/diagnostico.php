<?php
/**
 * diagnostico.php
 * Script de diagnóstico completo do ambiente para Assinatura Incremental (Gov.br + ICP-Brasil).
 * Pode ser executado via CLI (php diagnostico.php) ou via Navegador Web.
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/assinatura_incremental_helper.php';

$isCli = (php_sapi_name() === 'cli');

$resultados = [];

// 1. Informações do PHP
$sapi = php_sapi_name();
$phpVersion = PHP_VERSION;
$disableFunctions = ini_get('disable_functions');
$disabledList = array_filter(array_map('trim', explode(',', strval($disableFunctions))));

$resultados['php'] = [
    'versao' => $phpVersion,
    'sapi' => $sapi,
    'disable_functions' => empty($disabledList) ? 'Nenhuma (todas permitidas)' : implode(', ', $disabledList)
];

// 2. Funções de Execução CLI no PHP
$funcoesCli = ['exec', 'proc_open', 'shell_exec', 'popen'];
$funcoesDisponiveis = [];
foreach ($funcoesCli as $f) {
    $habilitada = function_exists($f) && !in_array($f, $disabledList, true);
    $funcoesDisponiveis[$f] = $habilitada;
}
$algumaFuncaoCli = in_array(true, $funcoesDisponiveis, true);
$resultados['execucao_cli'] = [
    'status' => $algumaFuncaoCli ? 'OK' : 'ERRO',
    'funcoes' => $funcoesDisponiveis,
    'mensagem' => $algumaFuncaoCli 
        ? 'Pelo menos uma função de execução está disponível.' 
        : 'CRÍTICO: Todas as funções de execução (exec, proc_open, etc.) estão desabilitadas no php.ini!'
];

// 3. Extensão OpenSSL
$opensslOk = extension_loaded('openssl');
$resultados['openssl'] = [
    'status' => $opensslOk ? 'OK' : 'ERRO',
    'mensagem' => $opensslOk ? 'Extensão openssl carregada no PHP.' : 'CRÍTICO: Extensão openssl não instalada no PHP.'
];

// 4. Detecção do Python
$pythonPath = assinatura_encontrarPython();
$resultados['python'] = [
    'status' => $pythonPath ? 'OK' : 'ERRO',
    'caminho' => $pythonPath ?: 'Não localizado',
    'mensagem' => $pythonPath ? "Python 3 localizado em: {$pythonPath}" : 'CRÍTICO: Interpretador Python 3 não foi localizado.'
];

// 5. Teste de Bibliotecas Python (pyhanko, qrcode, cryptography, Pillow)
$libsStatus = [];
$todasLibsOk = false;
if ($pythonPath && $algumaFuncaoCli) {
    $pyCode = 'import sys, os, site
p = site.getusersitepackages()
if p and os.path.exists(p) and p not in sys.path:
    sys.path.insert(0, p)
mods = ["pyhanko", "qrcode", "cryptography", "PIL"]
res = []
for m in mods:
    try:
        __import__(m)
        res.append(m + ":OK")
    except Exception as e:
        res.append(m + ":ERRO")
print(";".join(res))';
    $b64 = base64_encode($pyCode);
    $cmd = escapeshellarg($pythonPath) . ' -c "import base64; exec(base64.b64decode(\'' . $b64 . '\'))" 2>&1';
    $out = [];
    $code = 1;
    assinatura_executarComandoCli($cmd, $out, $code);
    $rawOut = trim(implode("\n", $out));
    
    $itens = explode(';', $rawOut);
    $errosLibs = [];
    foreach ($itens as $item) {
        $parts = explode(':', trim($item), 3);
        $mod = $parts[0] ?? '';
        $st = $parts[1] ?? 'ERRO';
        if ($mod !== '') {
            $libsStatus[$mod] = ($st === 'OK');
            if ($st !== 'OK') {
                $errosLibs[] = $mod;
            }
        }
    }
    foreach (["pyhanko", "qrcode", "cryptography", "PIL"] as $mObrigatorio) {
        if (empty($libsStatus[$mObrigatorio])) {
            $errosLibs[] = $mObrigatorio;
        }
    }
    $errosLibs = array_unique($errosLibs);
    $todasLibsOk = (count($errosLibs) === 0 && !empty($libsStatus));
    $resultados['python_libs'] = [
        'status' => $todasLibsOk ? 'OK' : 'ERRO',
        'detalhes' => $libsStatus,
        'mensagem' => $todasLibsOk 
            ? 'Todas as bibliotecas necessárias (pyhanko, qrcode, cryptography, Pillow) estão instaladas!' 
            : 'Faltam bibliotecas: ' . implode(', ', $errosLibs)
    ];
} else {
    $resultados['python_libs'] = [
        'status' => 'PULADO',
        'mensagem' => 'Não foi possível testar as bibliotecas porque o Python ou as funções CLI não estão disponíveis.'
    ];
}

// 6. Certificado PFX e Configuração
$configFile = __DIR__ . '/config_pfx.php';
$pfxConfigOk = false;
$pfxExiste = false;
$pfxLeituraOk = false;
$pfxMsg = '';

if (file_exists($configFile)) {
    $cfg = @include($configFile);
    if (is_array($cfg)) {
        $pfxPath = $cfg['pfx_path'] ?? '';
        $pfxPass = $cfg['pfx_password'] ?? '';
        $pfxExiste = (!empty($pfxPath) && file_exists($pfxPath));
        if ($pfxExiste && $opensslOk) {
            $certs = [];
            $pkcsOk = @openssl_pkcs12_read(file_get_contents($pfxPath), $certs, $pfxPass);
            $pfxLeituraOk = ($pkcsOk === true);
            $pfxMsg = $pfxLeituraOk 
                ? 'Certificado PFX lido com sucesso com a senha configurada.' 
                : 'Senha do certificado incorreta ou arquivo PFX corrompido.';
        } else {
            $pfxMsg = $pfxExiste ? 'OpenSSL não disponível para testar PFX.' : "Arquivo PFX não encontrado em: {$pfxPath}";
        }
        $pfxConfigOk = $pfxExiste && $pfxLeituraOk;
    } else {
        $pfxMsg = 'config_pfx.php não retornou um array válido.';
    }
} else {
    $pfxMsg = 'Arquivo config_pfx.php não existe.';
}

$resultados['certificado_pfx'] = [
    'status' => $pfxConfigOk ? 'OK' : 'ERRO',
    'arquivo_existe' => $pfxExiste,
    'leitura_e_senha' => $pfxLeituraOk,
    'mensagem' => $pfxMsg
];

// 7. Permissões de Pastas
$pastasParaTestar = [
    'uploads' => __DIR__ . '/uploads',
    'docAssinado' => __DIR__ . '/docAssinado',
    'tmp' => sys_get_temp_dir()
];
$pastasStatus = [];
$todasPastasOk = true;
foreach ($pastasParaTestar as $nome => $caminho) {
    $existe = file_exists($caminho);
    $gravavel = $existe && is_writable($caminho);
    $pastasStatus[$nome] = [
        'caminho' => $caminho,
        'existe' => $existe,
        'gravavel' => $gravavel
    ];
    if (!$gravavel) {
        $todasPastasOk = false;
    }
}
$resultados['pastas'] = [
    'status' => $todasPastasOk ? 'OK' : 'ALERTA',
    'detalhes' => $pastasStatus
];

// 8. Teste Global da Função Oficial assinatura_incrementalDisponivel()
$dispOficial = assinatura_incrementalDisponivel();
$resultados['diagnostico_geral'] = [
    'pronto_para_producao' => (!empty($dispOficial['disponivel']) && $todasLibsOk && $pfxConfigOk),
    'retorno_helper' => $dispOficial
];

if ($isCli) {
    echo "====================================================\n";
    echo "      DIAGNÓSTICO DO AMBIENTE DE ASSINATURA         \n";
    echo "====================================================\n";
    echo "PHP Versão: " . $resultados['php']['versao'] . " (" . $resultados['php']['sapi'] . ")\n";
    echo "disable_functions: " . $resultados['php']['disable_functions'] . "\n\n";

    echo "[CLI Exec]: " . $resultados['execucao_cli']['status'] . " - " . $resultados['execucao_cli']['mensagem'] . "\n";
    echo "[OpenSSL]:  " . $resultados['openssl']['status'] . " - " . $resultados['openssl']['mensagem'] . "\n";
    echo "[Python 3]: " . $resultados['python']['status'] . " - " . $resultados['python']['mensagem'] . "\n";
    echo "[Libs Py]:  " . $resultados['python_libs']['status'] . " - " . $resultados['python_libs']['mensagem'] . "\n";
    echo "[Cert PFX]: " . $resultados['certificado_pfx']['status'] . " - " . $resultados['certificado_pfx']['mensagem'] . "\n";
    echo "[Pastas]:   " . $resultados['pastas']['status'] . "\n\n";

    echo "----------------------------------------------------\n";
    if ($resultados['diagnostico_geral']['pronto_para_producao']) {
        echo ">>> RESULTADO FINAL: TUDO PRONTO! O ambiente suporta a assinatura incremental com preservação de Gov.br.\n";
    } else {
        echo ">>> RESULTADO FINAL: ATENÇÃO! Existem itens pendentes listados acima.\n";
    }
    echo "====================================================\n";
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico do Ambiente - Assinatura Digital</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; padding: 24px; color: #1e293b; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding: 24px; }
        h1 { margin-top: 0; font-size: 22px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; }
        .card { padding: 14px 18px; border-radius: 6px; margin-bottom: 14px; border: 1px solid #e2e8f0; }
        .badge { display: inline-block; padding: 4px 10px; font-weight: bold; border-radius: 4px; font-size: 13px; }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-erro { background: #fee2e2; color: #991b1b; }
        .badge-alerta { background: #fef3c7; color: #92400e; }
        .item-title { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
        .item-desc { font-size: 13px; color: #475569; }
        .banner { padding: 16px; border-radius: 6px; font-size: 16px; font-weight: 600; text-align: center; margin-top: 20px; }
        .banner-ok { background: #16a34a; color: white; }
        .banner-erro { background: #dc2626; color: white; }
    </style>
</head>
<body>
<div class="container">
    <h1>Diagnóstico do Ambiente de Assinatura (Web SAPI: <?= htmlspecialchars($sapi) ?>)</h1>

    <div class="card">
        <div class="item-title">1. PHP & Ambiente Web</div>
        <div class="item-desc">Versão: <strong><?= htmlspecialchars($phpVersion) ?></strong> | SAPI: <strong><?= htmlspecialchars($sapi) ?></strong></div>
        <div class="item-desc">disable_functions: <code><?= htmlspecialchars($resultados['php']['disable_functions']) ?></code></div>
    </div>

    <div class="card">
        <span class="badge <?= $algumaFuncaoCli ? 'badge-ok' : 'badge-erro' ?>"><?= $resultados['execucao_cli']['status'] ?></span>
        <div class="item-title" style="margin-top:6px;">2. Funções de Execução (exec / proc_open)</div>
        <div class="item-desc"><?= htmlspecialchars($resultados['execucao_cli']['mensagem']) ?></div>
        <div class="item-desc">Detalhes: exec=<?= $funcoesDisponiveis['exec'] ? 'SIM' : 'NÃO' ?> | proc_open=<?= $funcoesDisponiveis['proc_open'] ? 'SIM' : 'NÃO' ?> | shell_exec=<?= $funcoesDisponiveis['shell_exec'] ? 'SIM' : 'NÃO' ?></div>
    </div>

    <div class="card">
        <span class="badge <?= $opensslOk ? 'badge-ok' : 'badge-erro' ?>"><?= $resultados['openssl']['status'] ?></span>
        <div class="item-title" style="margin-top:6px;">3. Extensão OpenSSL</div>
        <div class="item-desc"><?= htmlspecialchars($resultados['openssl']['mensagem']) ?></div>
    </div>

    <div class="card">
        <span class="badge <?= $pythonPath ? 'badge-ok' : 'badge-erro' ?>"><?= $resultados['python']['status'] ?></span>
        <div class="item-title" style="margin-top:6px;">4. Interpretador Python 3</div>
        <div class="item-desc"><?= htmlspecialchars($resultados['python']['mensagem']) ?></div>
    </div>

    <div class="card">
        <span class="badge <?= $todasLibsOk ? 'badge-ok' : 'badge-erro' ?>"><?= $resultados['python_libs']['status'] ?></span>
        <div class="item-title" style="margin-top:6px;">5. Bibliotecas Python (pyHanko, qrcode, cryptography, Pillow)</div>
        <div class="item-desc"><?= htmlspecialchars($resultados['python_libs']['mensagem']) ?></div>
    </div>

    <div class="card">
        <span class="badge <?= $pfxConfigOk ? 'badge-ok' : 'badge-erro' ?>"><?= $resultados['certificado_pfx']['status'] ?></span>
        <div class="item-title" style="margin-top:6px;">6. Certificado PFX e Senha</div>
        <div class="item-desc"><?= htmlspecialchars($resultados['certificado_pfx']['mensagem']) ?></div>
    </div>

    <div class="card">
        <span class="badge <?= $todasPastasOk ? 'badge-ok' : 'badge-alerta' ?>"><?= $resultados['pastas']['status'] ?></span>
        <div class="item-title" style="margin-top:6px;">7. Permissões de Pastas</div>
        <div class="item-desc">uploads: <?= $pastasStatus['uploads']['gravavel'] ? 'Gravável' : 'NÃO GRAVÁVEL' ?> | docAssinado: <?= $pastasStatus['docAssinado']['gravavel'] ? 'Gravável' : 'NÃO GRAVÁVEL' ?></div>
    </div>

    <?php if ($resultados['diagnostico_geral']['pronto_para_producao']): ?>
        <div class="banner banner-ok">
            ✓ AMBIENTE 100% PRONTO PARA ASSINATURA INCREMENTAL!
        </div>
    <?php else: ?>
        <div class="banner banner-erro">
            ✗ ATENÇÃO: EXISTEM ITENS QUE IMPEDEM O FUNCIONAMENTO EM PRODUÇÃO.
        </div>
    <?php endif; ?>
</div>
</body>
</html>
