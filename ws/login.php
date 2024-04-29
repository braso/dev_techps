<?php

require 'vendor/autoload.php';
require_once "../load_env.php";
use Firebase\JWT\JWT;


$msg = '';
$retorno = new stdClass();

if(isset($_POST["username"]))
{
    
    $connect = new PDO("mysql:host=".$_ENV["DB_HOST"].";dbname=".$_ENV["DB_NAME"], $_ENV["DB_USER"], $_ENV["DB_PASSWORD"]);


	if(empty($_POST["username"])){
		$error = 'Please Enter login Details';
	} else if(empty($_POST["password"])){
		$error = 'Please Enter Password Details';
	} else {
		$query = "SELECT * FROM user WHERE user_tx_login = ?";
		$statement = $connect->prepare($query);
		$statement->execute([$_POST["username"]]);
		$data = $statement->fetch(PDO::FETCH_ASSOC);    
		if($data){
			if($data['user_tx_senha'] ===  md5($_POST['password'])){
				$key = $_ENV["APP_KEY"];
				$token = JWT::encode(
					array(
						'iat'		=>	time(),
						'nbf'		=>	time(),
						'exp'		=>	time() + 3600,
						'data'	=> array(
							'user_id'	=>	$data['user_nb_id'],
							'user_name'	=>	$data['user_tx_nome']
						)
					),
					$key,
					'HS256'
				);
				setcookie("token", $token, time() + 3600, "/", "", true, true);
				$retorno->token = $token;
                $msg = "Sucess Login";

			} else {
				$msg = 'Wrong Password';
			}
		} else {
			$msg = 'Wrong Username Address';
		}
	}
}
$retorno->message = $msg;
echo json_encode($retorno);