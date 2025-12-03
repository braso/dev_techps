<?php
    $rootDir = __DIR__.'/'; // Assuming the script is in the root directory
    $envFilePath = $rootDir.'.env';

    if(file_exists($envFilePath)){
        $env = parse_ini_file($envFilePath);
        foreach($env as $attr=>$val){
            $v = is_string($val) ? trim($val) : $val;
            if(is_string($v) && strlen($v) >= 2){
                $first = $v[0];
                $last = $v[strlen($v)-1];
                if(($first === "'" && $last === "'") || ($first === '"' && $last === '"')){
                    $v = substr($v, 1, -1);
                }
            }
            putenv("{$attr}={$v}");
            $_ENV[$attr] = $v;
        }
        $_ENV["URL_BASE"] = ($_SERVER["REQUEST_SCHEME"]?? "http")."://{$_SERVER["HTTP_HOST"]}";
    }
?>