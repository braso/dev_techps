<?php
    if(empty(session_id())){
        $lifetime = 30*60;
        ini_set('session.gc_maxlifetime', $lifetime);
    }
    if(empty(session_id())){
        session_start();
    }
	
	include_once __DIR__."/load_env.php";
    include __DIR__."conecta.php";
    
    if(file_exists($_SERVER["DOCUMENT_ROOT"].$_SERVER["REQUEST_URI"])){
        include $_SERVER["DOCUMENT_ROOT"].$_SERVER["REQUEST_URI"];
        exit;
    }

    echo "404";
?>