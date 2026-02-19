<?php
    require_once 'endpoints.php';
    require_once '../load_env.php';
    
    // um exemplo seria essa rota: http://192.168.99.100/braso/armazem_paraiba/api/login
    $path = str_replace($_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/", "", $_SERVER['REQUEST_URI']);
    //se torna isso após a linha acima /api/login
    $elements = explode('/', $path);
    //após a linha acima temos elements[0]=api e o elements[1]=login
    
    $rotas_permitidas = [
        'login', 'login_step1', 'login_step2', 'login_rfid', 'login_digital', 
        'refresh', 'users', 'journeys', 'delLastRegister'
    ];

    if(empty($elements[0]) || empty($elements[1]) || !in_array($elements[1], $rotas_permitidas)){
        echo "not found";
        exit;
    }

    //com base no elements[1] vamos fazer o switch case.
    switch($elements[1]){

        case 'login':
            make_login();
        break;
        
        case 'login_step1':
            make_login_step1(); // O Next.js vai chamar: /api/login_step1
        break;
        
        case 'login_step2':
            make_login_step2(); // O Next.js vai chamar: /api/login_step2
        break;
        
        case 'login_rfid':
            login_direto_rfid(); // O Embarcado vai chamar: /api/login_rfid
        break;

        case 'login_digital':
            login_direto_digital(); // O Embarcado vai chamar: /api/login_digital
        break;

        // --- OUTRAS ROTAS DO SISTEMA ---
        
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