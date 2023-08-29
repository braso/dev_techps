<?php
include"conecta.php";

// if(date('H')>=6 && date("H")<=12)
// 	$turno='Manhã';
// elseif(date('H')>=13 && date("H")<=18)
// 	$turno='Tarde';
// else
// 	$turno='Noite';

// $sql2=query("SELECT pont_nb_id FROM ponto WHERE pont_tx_status != 'inativo' AND pont_tx_data = '".date("Y-m-d")."' 
// 	AND pont_tx_turno = '$turno' AND pont_nb_user = '$_SESSION[user_nb_id]' LIMIT 1");

// if(num_linhas($sql2)>0){
// 	$a2=carrega_array($sql2);
// 	$dataSainda=date("Y-m-d H:i:s");
// 	atualizar('ponto',
// 		array(pont_tx_saida,pont_tx_status),
// 		array($dataSainda,'encerrado'),
// 		$a2[pont_nb_id]
// 	);
// }

session_destroy();
?>

<meta http-equiv="refresh" content="0; url=index.php" />
