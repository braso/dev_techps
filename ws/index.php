<?php 
require_once 'endpoints.php';

$path = ltrim($_SERVER['PATH_INFO'], '/');    // Trim leading slash(es)
$elements = explode('/', $path);                // Split path on slashes
if(empty($elements[0])) {                       // No path elements means home
    header('HTTP/1.1 404 Not Found');
    echo "not found";
} else switch($elements[0])             // Pop off first item and switch
{
    case 'login':
        make_login();
        break;
    case 'refresh':
        refresh();
        break;
    case 'users':
        get_user($elements[1]);
        break;
    case 'journeys':
        $found = false;

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $found = true;
            begin_jorney();
        }
        elseif($_SERVER['REQUEST_METHOD'] === 'PUT' and count($elements)>1){
            if($elements[1]){
                $found = true;
                finish_jorney($elements[1]);
            }
        }
        elseif($_SERVER['REQUEST_METHOD'] === 'GET'){
            $found = true;
            get_jorneys($elements[1]);
        }
        if($found = true){
            break;
        }
    default:
        header('HTTP/1.1 404 Not Found');
        echo "not found";
}