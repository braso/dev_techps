<?php

	global $CONTEX,$conn;

	session_start();
	// error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

	date_default_timezone_set('America/Fortaleza');

	$CONTEX['path'] = "/".(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))? "dev_techps": "techps");
	// $dominios = ['armazem_paraiba', 'feijao_turqueza', 'techps'];
	$CONTEX['path'] .= "/armazem_paraiba";

	/* INICIO CONEXAO BASE DE DADOS */

	$servername = "localhost";
	if(is_int(strpos($_SERVER["REQUEST_URI"], 'localhost'))){
		//Desenvolvedor, insira suas informações de BD local aqui
		$username = "root";
		$password = null;
		$dbname = "dev_techps";
	}else{
		$username = "brasomo_dev_techps_sistema";
		$password = "techps!sistema";
		$dbname = "brasomo_dev_techps_sistema";
	}
	$conn = mysqli_connect($servername, $username, $password, $dbname) or die("Connection failed: " . mysqli_connect_error());
	$conn->set_charset("utf8");

	/* FIM CONEXAO BASE DE DADOS */

	// Alterado em 06/09/2023, caso não haja um erro maior em um mês, esse comentário pode ser deletado
	// include_once $_SERVER['DOCUMENT_ROOT']."/techps/contex20/funcoes_grid.php";
	// include_once $_SERVER['DOCUMENT_ROOT']."/techps/contex20/funcoes_form.php";
	// include_once $_SERVER['DOCUMENT_ROOT']."/techps/contex20/funcoes.php";

	include_once (getcwd()."/../")."contex20/funcoes_grid.php";
	include_once (getcwd()."/../")."contex20/funcoes_form.php";
	include_once (getcwd()."/../")."contex20/funcoes.php";
?>