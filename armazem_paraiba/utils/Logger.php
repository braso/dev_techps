<?php

/**
 * Sistema de logging centralizado para rastrear erros e eventos críticos
 * Logs são armazenados em JSON para fácil parsing e análise
 */
class Logger {
    private static $logDir = __DIR__ . '/../logs';
    private static $fallbackLogFile = __DIR__ . '/../debug_log.txt';
    private static $diagnosticsFile = __DIR__ . '/../logger_diagnostics.txt';
    private static $maxFileSize = 5242880; // 5MB
    private static $logFile = null;
    private static $initialized = false;
    private static $diagnostics = [];

    /**
     * Inicializa o diretório de logs
     */
    private static function initialize() {
        if (self::$initialized) {
            return;
        }
        
        $today = date('Y-m-d');
        self::$diagnostics['start_time'] = date('Y-m-d H:i:s');
        self::$diagnostics['attempts'] = [];
        
        $candidates = [
            [
                'dir' => __DIR__ . '/../logs',
                'file' => 'saldo_' . $today . '.log',
            ],
            [
                'dir' => __DIR__ . '/..',
                'file' => 'debug_log.txt',
            ],
            [
                'dir' => rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dev_techps_logs',
                'file' => 'saldo_' . $today . '.log',
            ],
        ];

        foreach ($candidates as $idx => $candidate) {
            $attempt = [
                'index' => $idx,
                'dir' => $candidate['dir'],
                'file' => $candidate['file'],
                'is_dir' => is_dir($candidate['dir']),
                'is_writable' => false,
                'mkdir_attempted' => false,
                'mkdir_result' => false,
                'selected' => false,
            ];
            
            if (!is_dir($candidate['dir'])) {
                $attempt['mkdir_attempted'] = true;
                $attempt['mkdir_result'] = @mkdir($candidate['dir'], 0755, true);
            }

            if (is_dir($candidate['dir']) && is_writable($candidate['dir'])) {
                $attempt['is_writable'] = true;
                self::$logDir = $candidate['dir'];
                self::$logFile = $candidate['dir'] . DIRECTORY_SEPARATOR . $candidate['file'];
                @touch(self::$logFile);
                if (file_exists(self::$logFile)) {
                    $attempt['selected'] = true;
                    self::$diagnostics['attempts'][] = $attempt;
                    self::$diagnostics['selected_path'] = self::$logFile;
                    self::$initialized = true;
                    self::writeDiagnostics();
                    return;
                }
            }
            self::$diagnostics['attempts'][] = $attempt;
        }

        // Último recurso: diretório temporário padrão
        $tempPath = sys_get_temp_dir();
        self::$logDir = $tempPath;
        self::$logFile = $tempPath . DIRECTORY_SEPARATOR . 'saldo_' . $today . '.log';
        @touch(self::$logFile);
        self::$diagnostics['selected_path'] = self::$logFile;
        self::$diagnostics['fallback_to_temp'] = true;
        self::$initialized = true;
        
        if (!file_exists(self::$logFile)) {
            error_log('Logger failed to initialize any writable log file');
        }
        
        self::writeDiagnostics();
    }
    
    /**
     * Grava diagnostics em arquivo separado
     */
    private static function writeDiagnostics() {
        $diagContent = "=== LOGGER DIAGNOSTICS ===\n";
        $diagContent .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $diagContent .= "PHP Version: " . phpversion() . "\n";
        $diagContent .= "Current User: " . get_current_user() . "\n";
        $diagContent .= "PID: " . getmypid() . "\n";
        $diagContent .= json_encode(self::$diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        $diagContent .= "=== END ===\n\n";
        
        @file_put_contents(self::$diagnosticsFile, $diagContent, FILE_APPEND);
    }

    /**
     * Registra um log com nível, mensagem e contexto
     * 
     * @param string $level (INFO, WARNING, ERROR, DEBUG)
     * @param string $message Mensagem de log
     * @param array $context Dados adicionais (opcional)
     */
    public static function log($level, $message, $context = []) {
        self::initialize();
        
        try {
            // Verifica tamanho do arquivo e rotaciona se necessário
            if (file_exists(self::$logFile) && filesize(self::$logFile) > self::$maxFileSize) {
                self::rotateLog();
            }

            $entry = [
                'timestamp' => date('Y-m-d H:i:s.') . substr(microtime(), 2, 3),
                'level'     => strtoupper($level),
                'message'   => $message,
                'pid'       => getmypid(),
                'memory'    => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
                'context'   => $context
            ];

            $entry['logFile'] = self::$logFile;
            $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $written = @file_put_contents(self::$logFile, $json . "\n", FILE_APPEND);
            if ($written === false) {
                @touch(self::$logFile);
                $written = @file_put_contents(self::$logFile, $json . "\n", FILE_APPEND);
            }
            if ($written === false && !empty(self::$fallbackLogFile)) {
                @file_put_contents(self::$fallbackLogFile, $json . "\n", FILE_APPEND);
                @error_log($json . "\n", 3, self::$fallbackLogFile);
            }
            if ($written === false) {
                error_log('Logger failed to write file: ' . self::$logFile);
            }
        } catch (Throwable $e) {
            // Silencia erros de logging para não quebrar execução
            error_log('Logger error: ' . $e->getMessage());
        }
    }

    /**
     * Log de nível INFO
     */
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }

    /**
     * Log de nível WARNING
     */
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }

    /**
     * Log de nível ERROR
     */
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }

    /**
     * Log de nível DEBUG
     */
    public static function debug($message, $context = []) {
        self::log('DEBUG', $message, $context);
    }

    /**
     * Rotaciona log quando atinge tamanho máximo
     */
    private static function rotateLog() {
        if (!file_exists(self::$logFile)) {
            return;
        }

        $backup = self::$logFile . '.' . date('His');
        @rename(self::$logFile, $backup);

        // Mantém apenas os últimos 10 backups
        $files = glob(self::$logFile . '.??????') ?: [];
        if (count($files) > 10) {
            @unlink(array_shift($files));
        }
    }

    /**
     * Obtém caminho do diretório de logs
     */
    public static function getLogDir() {
        self::initialize();
        return self::$logDir;
    }

    /**
     * Obtém caminho do arquivo de log atual
     */
    public static function getLogFile() {
        self::initialize();
        return self::$logFile;
    }
}
