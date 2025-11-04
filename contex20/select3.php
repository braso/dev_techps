<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/
	
	$interno = true; //Utilizado no conecta.php para reconhecer se quem está tentando acessar é uma tela ou uma query interna.
	include_once "../..".$_GET["path"]."/conecta.php";

	GLOBAL $conn;

	$sql = 
		"SELECT ".base64_decode($_GET["colunas"])." FROM ".base64_decode($_GET["tabela"])
			." WHERE ".$_GET["condicoes"]
			." ORDER BY ".base64_decode($_GET["ordem"])
			.(!empty($_GET["limite"])? " LIMIT ".base64_decode($_GET["limite"]): "")
	;
	if(is_bool(strpos($sql, "AS id")) && is_bool(strpos($sql, "AS text"))){
		echo "Campos id e text faltando.";
		exit;
	}
	$result = mysqli_fetch_all(query($sql), MYSQLI_ASSOC);

	echo json_encode($result);
?>