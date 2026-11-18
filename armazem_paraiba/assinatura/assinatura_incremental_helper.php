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
            '/opt/alt/python311/bin/python3',
            '/opt/alt/python310/bin/python3',
            '/opt/alt/python39/bin/python3',
            '/opt/alt/python38/bin/python3',
            '/usr/bin/python3.12',
            '/usr/bin/python3.11',
            '/usr/bin/python3.10',
            '/usr/bin/python3.9',
            '/usr/bin/python3.8',
            '/usr/local/bin/python3',
            '/usr/bin/python3',
            '/bin/python3',
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

        // Testa se o pyhanko está instalado no python encontrado (usa base64 para evitar quebra de aspas pelo OS)
        $out = [];
        $code = 1;
        $testSnippet = 'import site, sys, os; p = site.getusersitepackages(); sys.path.insert(0, p) if (p and os.path.exists(p)) else None; import pyhanko; print("OK")';
        $b64Snippet = base64_encode($testSnippet);
        $testCmd = escapeshellarg($python) . ' -c "import base64; exec(base64.b64decode(\'' . $b64Snippet . '\'))" 2>&1';
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

        $signerName = trim(strval($opts["signer_name"] ?? ""));
        $signerCpf = trim(strval($opts["signer_cpf"] ?? ""));
        $signerRg = trim(strval($opts["signer_rg"] ?? ""));
        $rubricaPath = trim(strval($opts["rubrica_path"] ?? ""));
        $marcasJson = trim(strval($opts["marcas_json"] ?? ""));

        if ($signerName !== "") {
            $cmd .= " --signer-name " . escapeshellarg($signerName);
        }
        if ($signerCpf !== "") {
            $cmd .= " --signer-cpf " . escapeshellarg($signerCpf);
        }
        if ($signerRg !== "") {
            $cmd .= " --signer-rg " . escapeshellarg($signerRg);
        }
        if ($rubricaPath !== "" && file_exists($rubricaPath)) {
            $cmd .= " --rubrica " . escapeshellarg($rubricaPath);
        }
        if ($marcasJson !== "" && $marcasJson !== "[]") {
            $cmd .= " --marcas-json " . escapeshellarg($marcasJson);
        }
        $appendPage = trim(strval($opts["append_page"] ?? ""));
        if ($appendPage !== "" && file_exists($appendPage)) {
            $cmd .= " --append-page " . escapeshellarg($appendPage);
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

if (!function_exists('assinatura_gerarFolhaComprovantesPdf')) {
    /**
     * Gera uma folha PDF avulsa contendo os Comprovantes de Assinatura Eletrônica,
     * pronta para ser anexada ao documento final via append incremental do pyHanko.
     *
     * @param string $caminhoSaidaPdf Caminho do arquivo PDF temporário a ser gerado.
     * @param array $dados Metadados do documento e signatário.
     * @return bool True se o arquivo foi gerado com sucesso, false caso contrário.
     */
    function assinatura_gerarFolhaComprovantesPdf(string $caminhoSaidaPdf, array $dados): bool {
        if (!class_exists('TCPDF')) {
            $tcpdfVendor = __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
            $tcpdfLegacy = dirname(__DIR__) . '/tcpdf/tcpdf.php';
            if (file_exists($tcpdfVendor)) {
                require_once $tcpdfVendor;
            } elseif (file_exists($tcpdfLegacy)) {
                require_once $tcpdfLegacy;
            } else {
                return false;
            }
        }

        $docNome = trim(strval($dados['nome_documento'] ?? 'documento.pdf'));
        $docId = trim(strval($dados['id_documento'] ?? ''));
        $signerNome = trim(strval($dados['signer_name'] ?? 'Signatário'));
        $signerCpf = trim(strval($dados['signer_cpf'] ?? ''));
        $signerRg = trim(strval($dados['signer_rg'] ?? ''));
        $signDate = trim(strval($dados['data_hora'] ?? date('d/m/Y H:i:s')));
        $ip = trim(strval($dados['ip'] ?? '127.0.0.1'));
        $hash = trim(strval($dados['hash'] ?? ''));
        $userAgent = trim(strval($dados['user_agent'] ?? ''));
        $rubricaPath = trim(strval($dados['rubrica_path'] ?? ''));

        $pdf = new TCPDF('P', 'pt', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(36, 36, 36);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage('P', [595.28, 841.89]);

        $logoPath = __DIR__ . '/assets/logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 36, 30, 60, 0, 'PNG');
        }

        // Cabeçalho
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(file_exists($logoPath) ? 105 : 36, 36);
        $pdf->Cell(0, 16, 'COMPROVANTES DE ASSINATURA ELETRÔNICA', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetXY(36, 60);
        $pdf->Cell(0, 12, 'Documento: ' . substr($docNome, 0, 75), 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetXY(36, 72);
        $pdf->Cell(0, 12, 'ID: ' . ($docId !== '' ? 'DOC-' . $docId : 'N/A'), 0, 1, 'L');

        $pdf->SetLineStyle(['width' => 0.5, 'color' => [200, 200, 200]]);
        $pdf->Line(36, 88, 595.28 - 36, 88);

        // Card do Signatário
        $cardY = 98;
        $cardW = 595.28 - 72;
        $cardH = 125;
        $pdf->SetFillColor(250, 250, 250);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->Rect(36, $cardY, $cardW, $cardH, 'DF');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(46, $cardY + 8);
        $pdf->Cell(0, 12, '1. ' . $signerNome . ' (Signatário)', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(50, 50, 50);
        $cpfRgParts = [];
        if ($signerCpf !== '') $cpfRgParts[] = 'CPF: ' . $signerCpf;
        if ($signerRg !== '') $cpfRgParts[] = 'RG: ' . $signerRg;
        $cpfRgParts[] = 'CPF/RG + rubrica';
        $pdf->SetXY(46, $cardY + 24);
        $pdf->Cell(0, 10, implode(' • ', $cpfRgParts), 0, 1, 'L');
        $pdf->SetXY(46, $cardY + 36);
        $pdf->Cell(0, 10, 'Data/Hora: ' . $signDate, 0, 1, 'L');
        $pdf->SetXY(46, $cardY + 48);
        $pdf->Cell(0, 10, 'IP: ' . $ip, 0, 1, 'L');
        $pdf->SetXY(46, $cardY + 60);
        $pdf->Cell(0, 10, 'Hash: ' . ($hash !== '' ? $hash : 'N/A'), 0, 1, 'L');

        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY(46, $cardY + 76);
        $pdf->MultiCell($cardW - 160, 20, 'Declaração: O(s) signatário(s) declarou(aram) ter lido e concordado com o teor do documento. Validade jurídica assegurada pela MP nº 2.200-2/2001.', 0, 'L');

        // Imagem da Rubrica dentro do card
        if ($rubricaPath !== '' && file_exists($rubricaPath)) {
            $rubricaBoxW = 135;
            $rubricaBoxH = 95;
            $rubricaBoxX = 36 + $cardW - $rubricaBoxW - 10;
            $rubricaBoxY = $cardY + 10;
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->Rect($rubricaBoxX, $rubricaBoxY, $rubricaBoxW, $rubricaBoxH, 'DF');

            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY($rubricaBoxX + 6, $rubricaBoxY + 4);
            $pdf->Cell(0, 8, 'RUBRICA', 0, 1, 'L');

            @$pdf->Image($rubricaPath, $rubricaBoxX + 10, $rubricaBoxY + 12, $rubricaBoxW - 20, 45, '', '', '', false, 300, '', false, false, 0, 'CM');

            $pdf->SetLineStyle(['width' => 0.5, 'color' => [210, 210, 210]]);
            $pdf->Line($rubricaBoxX + 6, $rubricaBoxY + 62, $rubricaBoxX + $rubricaBoxW - 6, $rubricaBoxY + 62);

            $pdf->SetFont('helvetica', 'B', 6.5);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetXY($rubricaBoxX + 6, $rubricaBoxY + 65);
            $pdf->Cell($rubricaBoxW - 12, 8, substr($signerNome, 0, 30), 0, 1, 'L');

            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetXY($rubricaBoxX + 6, $rubricaBoxY + 74);
            $pdf->Cell($rubricaBoxW - 12, 7, 'CPF: ' . $signerCpf, 0, 1, 'L');
            $pdf->SetXY($rubricaBoxX + 6, $rubricaBoxY + 82);
            $pdf->Cell($rubricaBoxW - 12, 7, 'RG: ' . $signerRg, 0, 1, 'L');
        }

        // Rodapé
        $pdf->SetLineStyle(['width' => 0.5, 'color' => [230, 230, 230]]);
        $pdf->Line(36, 841.89 - 30, 595.28 - 36, 841.89 - 30);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY(36, 841.89 - 22);
        $pdf->Cell(0, 10, 'TechPS - Tecnologia e Sistemas', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetXY(200, 841.89 - 22);
        $pdf->Cell(0, 10, 'Gerado em: ' . $signDate, 0, 1, 'L');

        // === CARIMBO ICP-BRASIL NO CANTO INFERIOR DIREITO ===
        // Conversão de mm para pt (1 mm = 72 / 25.4 pt) para manter as mesmas proporções do fluxo padrão
        $mmToPt = 72.0 / 25.4;
        $certSignerName = trim(strval($dados['cert_signer_name'] ?? 'TECH PS LTDA:42390531000188'));
        $issuerName = trim(strval($dados['cert_issuer'] ?? 'AC SyngularID Multipla'));
        $shortHash = trim(strval($dados['short_hash'] ?? (substr($hash, 0, 20) . '...')));
        $verificationCode = trim(strval($dados['verification_code'] ?? strtoupper(substr(md5($docNome . $signDate . $docId), 0, 16))));

        $iti_url = "https://validar.iti.gov.br/";
        $qrSize = 24.0 * $mmToPt;
        $w = 150.0 * $mmToPt;
        $h = 36.0 * $mmToPt;
        $margin = 10.0 * $mmToPt;
        $linkH = 6.0 * $mmToPt;

        $x = $pdf->getPageWidth() - $w - $margin;
        $y = $pdf->getPageHeight() - $h - $linkH - $margin;

        $pdf->SetAlpha(1);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetLineStyle(["width" => 0.2 * $mmToPt, "cap" => "butt", "join" => "miter", "dash" => 0, "color" => [0, 0, 0]]);
        $pdf->RoundedRect($x, $y, $w, $h, 2 * $mmToPt, "1111", "DF");

        $icpLogo = __DIR__ . "/assets/icp.png";
        if (file_exists($icpLogo)) {
            $pdf->Image($icpLogo, $x + (2.0 * $mmToPt), $y + (4.0 * $mmToPt), 18.0 * $mmToPt, 0, "PNG");
        }

        $textX = $x + (30.0 * $mmToPt);
        $textW = $w - (32.0 * $mmToPt) - $qrSize - (4.0 * $mmToPt);
        $currentY = $y + (3.0 * $mmToPt);

        $pdf->SetFont("helvetica", "B", 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($textX, $currentY);
        $pdf->Cell(0, 0, "ASSINADO DIGITALMENTE", 0, 1, "L");

        $currentY += 4.0 * $mmToPt;
        $pdf->SetFont("helvetica", "B", 8);
        $pdf->SetXY($textX, $currentY);
        $pdf->Cell($textW, 0, substr(strtoupper(strval($certSignerName)), 0, 40), 0, 1, "L");

        $currentY += 4.0 * $mmToPt;
        $pdf->SetFont("helvetica", "", 6);
        $pdf->SetXY($textX, $currentY);
        $pdf->Cell($textW, 0, "Data: " . $signDate, 0, 1, "L");

        $currentY += 3.0 * $mmToPt;
        $pdf->SetXY($textX, $currentY);
        $pdf->Cell($textW, 0, "Emissor: " . substr(strval($issuerName), 0, 45), 0, 1, "L");

        $currentY += 3.0 * $mmToPt;
        $pdf->SetXY($textX, $currentY);
        $pdf->Cell($textW, 0, "Hash Doc: " . $shortHash, 0, 1, "L");

        $currentY += 3.0 * $mmToPt;
        $pdf->SetFont("helvetica", "B", 6);
        $pdf->SetXY($textX, $currentY);
        $pdf->Cell($textW, 0, "Cód. Verificação: " . $verificationCode, 0, 1, "L");

        $pdf->SetFont("helvetica", "", 5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY($x + (2.0 * $mmToPt), $y + $h - (7.0 * $mmToPt));
        $pdf->MultiCell($textW + (28.0 * $mmToPt), 8.0 * $mmToPt, "Documento assinado digitalmente conforme MP 2.200-2/2001 e Lei 14.063/2020.\nValidade jurídica assegurada.", 0, "L");

        $qrX = $x + $w - $qrSize - (3.0 * $mmToPt);
        $qrY = $y + ($h - $qrSize) / 2.0;
        $qrStyle = [
            "border" => false,
            "vpadding" => 0,
            "hpadding" => 0,
            "fgcolor" => [0, 0, 0],
            "bgcolor" => [255, 255, 255],
            "module_width" => 1,
            "module_height" => 1,
        ];
        $pdf->write2DBarcode($iti_url, "QRCODE,H", $qrX, $qrY, $qrSize, $qrSize, $qrStyle, "N");

        $pdf->SetLineStyle(["width" => 0.1 * $mmToPt, "color" => [180, 180, 180]]);
        $linkY = $y + $h + (1.0 * $mmToPt);
        $pdf->SetFont("helvetica", "", 5.5);
        $pdf->SetTextColor(30, 80, 180);
        $pdf->SetXY($x, $linkY);
        $pdf->Cell($w, $linkH, "Valide o documento: " . $iti_url, 0, 1, "C", false, $iti_url);

        @$pdf->Output($caminhoSaidaPdf, 'F');
        return file_exists($caminhoSaidaPdf) && filesize($caminhoSaidaPdf) > 0;
    }
}
