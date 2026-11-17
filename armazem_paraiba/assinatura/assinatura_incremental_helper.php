<?php
/**
 * assinatura_incremental_helper.php
 * 
 * Suporte a Assinatura Incremental PAdES (ISO 32000-1 / PAdES)
 * Preserva assinaturas digitais pré-existentes (Gov.br, ICP-Brasil, etc.)
 * adicionando novas assinaturas via Append Incremental sem invalidar as anteriores.
 */

if (!function_exists('assinatura_pdfTemAssinaturaDigital')) {
    /**
     * Detecta se um arquivo PDF possui assinaturas digitais pré-existentes.
     * Verifica a presença de /ByteRange e estruturas de assinatura (/Sig, pkcs7, CAdES).
     *
     * @param string $caminhoAbsoluto Caminho absoluto do arquivo PDF.
     * @return bool True se contiver assinatura digital, false caso contrário.
     */
    function assinatura_pdfTemAssinaturaDigital(string $caminhoAbsoluto): bool {
        if (!file_exists($caminhoAbsoluto) || !is_file($caminhoAbsoluto)) {
            return false;
        }

        $tam = @filesize($caminhoAbsoluto);
        if ($tam === false || $tam < 100) {
            return false;
        }

        $handle = @fopen($caminhoAbsoluto, 'rb');
        if (!$handle) {
            return false;
        }

        // Lê até 6MB do arquivo (assinaturas costumam estar no fim ou início)
        $bytesParaLer = min($tam, 6 * 1024 * 1024);
        $buffer = @fread($handle, $bytesParaLer);
        @fclose($handle);

        if ($buffer === false) {
            return false;
        }

        // Critério seguro: /ByteRange é mandatório em assinaturas digitais PDF
        if (strpos($buffer, '/ByteRange') === false) {
            return false;
        }

        // Deve conter também indicador de assinatura
        if (
            strpos($buffer, '/Sig') !== false ||
            strpos($buffer, 'pkcs7') !== false ||
            strpos($buffer, 'ETSI.CAdES') !== false ||
            strpos($buffer, 'Adobe.PPKLite') !== false
        ) {
            return true;
        }

        return false;
    }
}

if (!function_exists('assinatura_executarComandoCli')) {
    /**
     * Executa um comando no sistema operacional com suporte a múltiplos métodos,
     * respeitando as restrições de disable_functions do php.ini (ex: hospedagens cPanel).
     *
     * @param string $cmd Comando a ser executado.
     * @param array &$out Linhas de saída do comando.
     * @param int &$exitCode Código de retorno do comando.
     * @return bool True se conseguiu invocar algum mecanismo de execução, false se todos estiverem desabilitados.
     */
    function assinatura_executarComandoCli(string $cmd, array &$out = [], int &$exitCode = 1): bool {
        $out = [];
        $exitCode = 1;

        // 1. Tenta exec() se disponível
        if (function_exists('exec')) {
            @exec($cmd, $out, $exitCode);
            return true;
        }

        // 2. Tenta proc_open() se disponível
        if (function_exists('proc_open')) {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            $process = @proc_open($cmd, $descriptors, $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);
                $combined = trim($stdout . ($stderr ? "\n" . $stderr : ""));
                $out = ($combined !== '') ? explode("\n", $combined) : [];
                return true;
            }
        }

        // 3. Tenta shell_exec() se disponível
        if (function_exists('shell_exec')) {
            $saida = @shell_exec($cmd . ' 2>&1');
            if ($saida !== null) {
                $saida = trim($saida);
                $out = ($saida !== '') ? explode("\n", $saida) : [];
                $exitCode = 0;
                return true;
            }
        }

        // 4. Tenta popen() se disponível
        if (function_exists('popen')) {
            $handle = @popen($cmd . ' 2>&1', 'r');
            if (is_resource($handle)) {
                $buffer = '';
                while (!feof($handle)) {
                    $buffer .= fread($handle, 4096);
                }
                $exitCode = pclose($handle);
                $buffer = trim($buffer);
                $out = ($buffer !== '') ? explode("\n", $buffer) : [];
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('assinatura_encontrarPython')) {
    /**
     * Localiza o executável do Python no ambiente do servidor.
     * Prioriza caminhos diretos do sistema de arquivos para evitar invocar CLI se desabilitada.
     *
     * @return string|null Caminho do executável do Python ou null se não encontrado.
     */
    function assinatura_encontrarPython(): ?string {
        static $cached = null;
        if ($cached !== null) {
            return ($cached === '') ? null : $cached;
        }

        $isWindows = (PHP_OS_FAMILY ?? "") === "Windows" || DIRECTORY_SEPARATOR === "\\";

        // 0. Verifica se há caminho configurado explicitamente no config_pfx.php
        $configFile = __DIR__ . "/config_pfx.php";
        if (file_exists($configFile)) {
            $cfg = @include($configFile);
            if (is_array($cfg) && !empty($cfg["python_path"]) && file_exists($cfg["python_path"])) {
                $cached = $cfg["python_path"];
                return $cached;
            }
        }

        $candidatos = [
            '/usr/bin/python3',
            '/usr/local/bin/python3',
            '/bin/python3',
            '/usr/bin/python3.12',
            '/usr/bin/python3.11',
            '/usr/bin/python3.10',
            '/usr/bin/python3.9',
            '/usr/bin/python3.8',
            '/opt/alt/python311/bin/python3',
            '/opt/alt/python310/bin/python3',
            '/opt/alt/python39/bin/python3',
            '/usr/local/cpanel/3rdparty/bin/python3',
            '/usr/bin/python',
            '/usr/local/bin/python',
            '/bin/python',
            'C:\\Users\\Ornilio Neto\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe'
        ];

        // 1. Primeiro verifica caminhos absolutos diretos (sem precisar de exec/which)
        foreach ($candidatos as $cmd) {
            if ($isWindows) {
                if (file_exists($cmd) && is_file($cmd)) {
                    $cached = $cmd;
                    return $cmd;
                }
            } else {
                if (strpos($cmd, '/') !== false && file_exists($cmd) && is_executable($cmd)) {
                    $cached = $cmd;
                    return $cmd;
                }
            }
        }

        // 2. Se não encontrou por caminho absoluto, tenta resolver dinamicamente via CLI se disponível
        if (function_exists('exec') || function_exists('proc_open') || function_exists('shell_exec') || function_exists('popen')) {
            foreach (['python3', 'python'] as $cmd) {
                $out = [];
                $code = 1;
                $whichCmd = $isWindows ? "where " . escapeshellarg($cmd) . " 2>NUL" : "which " . escapeshellarg($cmd) . " 2>/dev/null";
                if (assinatura_executarComandoCli($whichCmd, $out, $code) && $code === 0 && !empty($out)) {
                    $path = trim(strval($out[0]));
                    if ($path !== "" && ($isWindows ? is_file($path) : is_executable($path))) {
                        $cached = $path;
                        return $path;
                    }
                }
            }
        }

        $cached = '';
        return null;
    }
}

if (!function_exists('assinatura_incrementalDisponivel')) {
    /**
     * Verifica se o ambiente do servidor suporta a assinatura incremental via pyHanko.
     * Valida suporte a CLI, interpretador Python e presença da biblioteca pyHanko.
     *
     * @return array{disponivel: bool, motivo?: string, python?: string}
     */
    function assinatura_incrementalDisponivel(): array {
        static $statusCache = null;
        if ($statusCache !== null) {
            return $statusCache;
        }

        // Verifica se há alguma função de execução externa habilitada no PHP
        $hasCli = function_exists('exec') || function_exists('proc_open') || function_exists('shell_exec') || function_exists('popen');
        if (!$hasCli) {
            $statusCache = [
                "disponivel" => false,
                "motivo" => "Funções de execução de comando (exec/proc_open) desabilitadas no php.ini (disable_functions)."
            ];
            return $statusCache;
        }

        $python = assinatura_encontrarPython();
        if (!$python) {
            $statusCache = [
                "disponivel" => false,
                "motivo" => "Interpretador Python 3 não localizado no servidor."
            ];
            return $statusCache;
        }

        $scriptPython = __DIR__ . "/assinar_incremental.py";
        if (!file_exists($scriptPython)) {
            $statusCache = [
                "disponivel" => false,
                "motivo" => "Script assinar_incremental.py não encontrado."
            ];
            return $statusCache;
        }

        // Testa se o pyhanko está instalado no python encontrado
        $out = [];
        $code = 1;
        $testSnippet = 'import site, sys, os; p = site.getusersitepackages(); sys.path.insert(0, p) if (p and os.path.exists(p)) else None; import pyhanko; print("OK")';
        $testCmd = escapeshellarg($python) . ' -c ' . escapeshellarg($testSnippet) . ' 2>&1';
        $ok = assinatura_executarComandoCli($testCmd, $out, $code);
        $outputStr = implode(" ", $out);

        if (!$ok || $code !== 0 || strpos($outputStr, 'OK') === false) {
            $statusCache = [
                "disponivel" => false,
                "motivo" => "Biblioteca pyHanko não disponível no Python do servidor (" . ($outputStr ?: "código $code") . ")."
            ];
            return $statusCache;
        }

        $statusCache = [
            "disponivel" => true,
            "python" => $python
        ];
        return $statusCache;
    }
}

if (!function_exists('assinatura_executarAssinaturaIncremental')) {
    /**
     * Executa a assinatura digital incremental usando o script assinar_incremental.py.
     * Preserva 100% dos bytes e assinaturas anteriores do PDF.
     *
     * @param string $inputPdf Caminho absoluto do PDF de entrada.
     * @param string $outputPdf Caminho absoluto do PDF de saída.
     * @param string $pfxPath Caminho absoluto do arquivo .pfx.
     * @param string $pfxPass Senha do arquivo .pfx.
     * @param array $opts Opções adicionais (reason, location, url, no_stamp).
     * @return array{ok: bool, output_path?: string, error?: string, signer_name?: string}
     */
    function assinatura_executarAssinaturaIncremental(
        string $inputPdf,
        string $outputPdf,
        string $pfxPath,
        string $pfxPass,
        array $opts = []
    ): array {
        if (!file_exists($inputPdf)) {
            return ["ok" => false, "error" => "PDF de entrada não encontrado."];
        }
        if (!file_exists($pfxPath)) {
            return ["ok" => false, "error" => "Certificado PFX não encontrado."];
        }

        $disp = assinatura_incrementalDisponivel();
        if (!$disp["disponivel"]) {
            return ["ok" => false, "error" => $disp["motivo"] ?? "Ambiente não suporta assinatura incremental."];
        }

        $python = $disp["python"] ?? assinatura_encontrarPython();
        if (!$python) {
            return ["ok" => false, "error" => "Interpretador Python não encontrado no servidor."];
        }

        $scriptPython = __DIR__ . "/assinar_incremental.py";
        if (!file_exists($scriptPython)) {
            return ["ok" => false, "error" => "Script de assinatura incremental (assinar_incremental.py) não encontrado."];
        }

        $reason = strval($opts["reason"] ?? "Garantia de Autenticidade e Integridade ICP-Brasil");
        $location = strval($opts["location"] ?? "Sistema TechPS");
        $url = strval($opts["url"] ?? "https://validar.iti.gov.br/");
        $noStamp = !empty($opts["no_stamp"]) ? "--no-stamp" : "";

        $cmd = escapeshellarg($python) . " " . escapeshellarg($scriptPython)
            . " --input " . escapeshellarg($inputPdf)
            . " --output " . escapeshellarg($outputPdf)
            . " --pfx " . escapeshellarg($pfxPath)
            . " --password " . escapeshellarg($pfxPass)
            . " --reason " . escapeshellarg($reason)
            . " --location " . escapeshellarg($location)
            . " --url " . escapeshellarg($url);

        if ($noStamp !== "") {
            $cmd .= " " . $noStamp;
        }

        $out = [];
        $exitCode = 1;
        $executou = assinatura_executarComandoCli($cmd, $out, $exitCode);

        if (!$executou) {
            return ["ok" => false, "error" => "Falha ao invocar processo CLI de assinatura."];
        }

        $saidaTexto = implode("\n", $out);
        $resJson = @json_decode($saidaTexto, true);

        if (is_array($resJson) && !empty($resJson["ok"])) {
            return $resJson;
        }

        $erro = is_array($resJson) && !empty($resJson["error"])
            ? $resJson["error"]
            : ($saidaTexto !== "" ? $saidaTexto : "Falha na execução do processo de assinatura (código {$exitCode}).");

        return ["ok" => false, "error" => $erro];
    }
}
