<?php

/**
 * Sistema de logging centralizado para rastrear erros e eventos críticos
 * Logs são armazenados em JSON para fácil parsing e análise
 */
class Logger {
    private static $logDir = __DIR__ . '/../logs';
    private static $maxFileSize = 5242880; // 5MB
    private static $logFile = null;

    /**
     * Inicializa o diretório de logs
     */
    private static function initialize() {
        $today = date('Y-m-d');
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

        foreach ($candidates as $candidate) {
            if (!is_dir($candidate['dir'])) {
                @mkdir($candidate['dir'], 0755, true);
            }

            if (is_dir($candidate['dir']) && is_writable($candidate['dir'])) {
                self::$logDir = $candidate['dir'];
                self::$logFile = $candidate['dir'] . DIRECTORY_SEPARATOR . $candidate['file'];
                if (!file_exists(self::$logFile)) {
                    @touch(self::$logFile);
                }
                if (file_exists(self::$logFile)) {
                    return;
                }
            }
        }

        // Último recurso: diretório temporário padrão
        self::$logDir = sys_get_temp_dir();
        self::$logFile = self::$logDir . DIRECTORY_SEPARATOR . 'saldo_' . $today . '.log';
        @touch(self::$logFile);
        if (!file_exists(self::$logFile)) {
            error_log('Logger failed to initialize any writable log file');
        }
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

            $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $written = @file_put_contents(self::$logFile, $json . "\n", FILE_APPEND);
            if($written === false){
                @touch(self::$logFile);
                $written = @file_put_contents(self::$logFile, $json . "\n", FILE_APPEND);
            }
            if($written === false){
                @file_put_contents(self::$fallbackLogFile, $json . "\n", FILE_APPEND);
            }
            if($written === false){
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
