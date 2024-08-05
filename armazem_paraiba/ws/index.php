<?php
    require_once 'endpoints.php';
    require_once '../load_env.php';
    
    
    $path = str_replace($_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/", "", $_SERVER['REQUEST_URI']);
    $elements = explode('/', $path);
    
    if(empty($elements[0]) || empty($elements[1]) || !in_array($elements[1], ['login', 'refresh', 'users', 'journeys', 'delLastRegister'])){
        echo "not found";
        exit;
    }
    
    switch($elements[1]){
        case 'login':
            make_login();
        break;
        case 'refresh':
            refresh();
        break;
        case 'users':
            get_user($elements[2]);
        break;
        case 'journeys':

            if(empty($elements[2]) && $_SERVER['REQUEST_METHOD'] !== "GET"){
                // header('HTTP/1.0 400 Bad Request');
                echo "Open journey not found";
                exit;
            }

            if($_SERVER['REQUEST_METHOD'] === "POST" && $elements[2] == "begin"){
                begin_journey();
            }elseif($_SERVER['REQUEST_METHOD'] === "GET"){
                if(empty($elements[2])){
                    // header('HTTP/1.0 400 Bad Request');
                    echo "Open journey not found";
                    exit;
                }
                get_journeys();
            }elseif($_SERVER['REQUEST_METHOD'] === "PUT" && $elements[2] == "finish"){
                finish_journey($elements[2]);
            }
            
        break;
        
        case 'delLastRegister':
            echo delLastRegister($elements[2]);
        break;
    }