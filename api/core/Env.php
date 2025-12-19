<?php

class Env {
    public static function load(string $path): void{
        if(!file_exists($path)){
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach($lines as $line){
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            [$key, $value] = explode('=',$line,2);

            $key = trim($key);
            $value = trim($value, "\t\n\r\0\x0B\"'");

            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    public static function get(string $key, $default = null){
        return $_ENV[$key] ?? getenv($key) ?? $default;
    }
}