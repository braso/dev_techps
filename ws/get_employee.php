<?php
/*
id: number;
registration: string;
company: string;
cpf: string;
rg: string;
cnh: string;
email: string;
city: string;
role: string;
journeys: Array<Journey>;
*/


require 'vendor/autoload.php';
require_once "../load_env.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$key = $_ENV["APP_KEY"];
$token = $_POST["token"];
if(!$token){
    $token = $_COOKIE['token'];
}

$decoded = JWT::decode($token, new Key($key, 'HS256'));
$retorno = new stdClass();

$connect = new PDO("mysql:host=".$_ENV["DB_HOST"].";dbname=".$_ENV["DB_NAME"], $_ENV["DB_USER"], $_ENV["DB_PASSWORD"]);

$query =  "SELECT id, matricula as registration,
SELECT  distinct user_nb_id as id,
coalesce(user_tx_matricula,enti_tx_matricula) as registration,
coalesce(e.empr_tx_nome) as company,
coalesce(u.user_tx_login,user_tx_cpf,enti.enti_tx_cpf) as cpf,
coalesce(enti.enti_tx_carteira,user_tx_rg) as rg,enti.enti_tx_cnhRegistro as cnh,
user_tx_email as email,user_tx_nivel as role From user u
join entidade enti on enti.enti_tx_cpf = u.user_tx_login   
or enti.enti_tx_cpf = u.user_tx_cpf 
join empresa e on enti_nb_empresa = e.empr_nb_idand u.user_nb_id =?";

$statement->execute([$decoded["user_id"]]);
$data = $statement->fetch(PDO::FETCH_ASSOC);    
if($data){
    $retorno->data = $data;
}
$token = JWT::encode(
    array(
        'iat'		=>	time(),
        'nbf'		=>	time(),
        'exp'		=>	time() + 3600,
        'data'	=> array(
            'user_id'	=>	$decoded['user_nb_id'],
            'user_name'	=>	$decoded['user_tx_nome']
        )
    ),
    $key,
    'HS256'
);
setcookie("token", $token, time() + 3600, "/", "", true, true);
$retorno->token = $token;
echo json_encode($retorno);