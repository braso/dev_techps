<?php
    $rootDir = __DIR__.'/'; // Assuming the script is in the root directory
    $envFilePath = $rootDir.'.env';

    if(file_exists($envFilePath)){
        $env = parse_ini_file($envFilePath, false, INI_SCANNER_RAW);
        foreach($env as $attr=>$val){
            $val = trim($val);
            if(strlen($val) >= 2){
                $first = $val[0];
                $last = $val[strlen($val)-1];
                if(($first === "'" && $last === "'") || ($first === '"' && $last === '"')){
                    $val = substr($val, 1, -1);
                }
            }
            putenv("{$attr}={$val}");
            $_ENV[$attr] = $val;
        }
        $_ENV["URL_BASE"] = ($_SERVER["REQUEST_SCHEME"]?? "http")."://{$_SERVER["HTTP_HOST"]}";
    }
?>
 
