<?php
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