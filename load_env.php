<?php
$rootDir = __DIR__.'/';
$envFilePath = $rootDir.'.env';

if(file_exists($envFilePath)){
    $env = parse_ini_file($envFilePath);
    foreach($env as $attr => $val){
        // Remove aspas simples ou duplas
        $val = trim($val, "\"'");

        putenv("{$attr}={$val}");
        $_ENV[$attr] = $val;
    }

    $_ENV["URL_BASE"] = ($_SERVER["REQUEST_SCHEME"] ?? "http") . "://{$_SERVER["HTTP_HOST"]}";
}
?>
