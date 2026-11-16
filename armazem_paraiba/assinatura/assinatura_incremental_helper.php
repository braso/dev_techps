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

if (!function_exists('assinatura_encontrarPython')) {
    /**
     * Localiza o executável do Python no ambiente do servidor.
     *
     * @return string|null Caminho do executável do Python ou null se não encontrado.
     */
    function assinatura_encontrarPython(): ?string {
        $candidatos = [
            'python3',
            'python',
            '/usr/bin/python3',
            '/usr/local/bin/python3',
            'C:\\Users\\Ornilio Neto\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe'
        ];

        $isWindows = (PHP_OS_FAMILY ?? "") === "Windows" || DIRECTORY_SEPARATOR === "\\";

        foreach ($candidatos as $cmd) {
            $out = [];
            $code = 1;

            if ($isWindows) {
                if (file_exists($cmd) && is_file($cmd)) {
                    return $cmd;
                }
                @exec("where " . escapeshellarg($cmd) . " 2>NUL", $out, $code);
            } else {
                if (file_exists($cmd) && is_executable($cmd)) {
                    return $cmd;
                }
                @exec("which " . escapeshellarg($cmd) . " 2>/dev/null", $out, $code);
            }

            if ($code === 0 && !empty($out)) {
                $path = trim(strval($out[0]));
                if ($path !== "") {
                    return $path;
                }
            }
        }

        return null;
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

        $python = assinatura_encontrarPython();
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
        @exec($cmd, $out, $exitCode);

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
