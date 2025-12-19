<?php

class Logger
{
    private static string $path = __DIR__ . '/../storage/logs/app.log';

    private static array $levels = [
        'debug' => 1,
        'info'  => 2,
        'error' => 3,
    ];

    private static function currentLevel(): int
    {
        $level = Env::get('LOG_LEVEL', 'info');
        return self::$levels[$level] ?? self::$levels['info'];
    }

    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        if (self::$levels[$level] < self::currentLevel()) {
            return;
        }

        $date = date('Y-m-d H:i:s');

        $log = sprintf(
            "[%s] %s: %s %s%s",
            $date,
            strtoupper($level),
            $message,
            !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '',
            PHP_EOL
        );

        file_put_contents(self::$path, $log, FILE_APPEND);
    }
}
