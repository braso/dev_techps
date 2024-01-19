<?php
// $servername = "localhost";
// $username = "conta402_contex2";
// $password = "contex000contex";
// $dbname = "conta402_contex20";

// $conn = mysqli_connect($servername, $username, $password, $dbname) or die("Connection failed: " . mysqli_connect_error());
// $conn->set_charset("utf8");
// GLOBAL $CONTEX;
include "..".$_GET['path']."/conecta.php";
GLOBAL $conn;

$sql = openssl_decrypt($_GET['q'], "aes-128-cbc", "techps");

$result = $conn->query($sql);

$json = array();
while($row = $result->fetch_assoc()){

	if($extra_busca != ''){
		$extra_exibe = "[$row[$extra_busca]] ";
	}
   	$json[] = array('id'=>$row[$col_tab.'_nb_id'], 'text'=>$extra_exibe.$row[$col_tab.'_tx_nome']);

}


echo json_encode($json);