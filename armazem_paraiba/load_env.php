<?php
    $rootDir = __DIR__.'/';
    $envFilePath = $rootDir.'.env';

    if(file_exists($envFilePath)){
        $env = parse_ini_file($envFilePath, false, INI_SCANNER_RAW);
        foreach($env as $attr=>$val){
            $v = is_string($val) ? trim($val) : $val;
            putenv("{$attr}={$v}");
            $_ENV[$attr] = $v;
        }
        $_ENV["URL_BASE"] = ($_SERVER["REQUEST_SCHEME"]?? "http")."://".$_SERVER["HTTP_HOST"];
    }
?>
