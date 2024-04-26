<?php
	include_once "../load_env.php";
	
	$servername = $_ENV["DB_HOST"];
	$username = $_ENV["DB_USER"];
	$password = $_ENV["DB_PASSWORD"];
	$dbname = $_ENV["DB_NAME"];
	$conn = mysqli_connect($servername, $username, $password, $dbname) or die("Connection failed: " . mysqli_connect_error());
	$conn->set_charset("utf8");
	
?>