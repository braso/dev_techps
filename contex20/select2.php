<?php
	// $servername = "localhost";
	// $username = "conta402_contex2";
	// $password = "contex000contex";
	// $dbname = "conta402_contex20";

	// $conn = mysqli_connect($servername, $username, $password, $dbname) or die("Connection failed: " . mysqli_connect_error());
	// $conn->set_charset("utf8");
	// GLOBAL $CONTEX;
	$interno = true; //Utilizado no conecta.php para reconhecer se quem está tentando acessar é uma tela ou uma query interna.
	include_once "../..".$_GET['path']."/conecta.php";
	GLOBAL $conn;

	$tabela 	  = $_GET['tabela'];
	$tab 		  = substr($_GET['tabela'],0,4);
	$extra_bd 	  = urldecode($_GET['extra_bd']);
	$extra_busca  = urldecode($_GET['extra_busca']);

	$extra = " AND (".$tab."_tx_nome LIKE '%".$_GET['q']."%'";
	if(!empty($extra_busca)){
		$extra_campo = ",".$extra_busca;
		$extra .= " OR $extra_busca LIKE '%".$_GET['q']."%')";
	}else{
		$extra_campo = "";
		$extra .= ")";
	}

	if($tabela == 'servico' && $_GET['path'] == '/imagem'){
		$sql = 
			"SELECT ".$tab."_nb_id,CONCAT(".$tab."_tx_nome,' | ',".$tab."_tx_tipo) AS ".$tab."_tx_nome FROM ".$tabela." 
				WHERE ".$tab."_tx_nome LIKE '%".$_GET['q']."%'"; 
	}else{
		$sql = 
			"SELECT ".$tab."_nb_id, ".$tab."_tx_nome ".(!empty($extra_busca)? ",".$extra_busca: "")." FROM ".$tabela." 
				WHERE 1 $extra"
		;
	}

	$sql .= " AND ".$tab."_tx_status != 'inativo' 
			$extra_bd
		".(!empty(urldecode($_GET['extra_ordem']))? urldecode($_GET['extra_ordem']): " ORDER BY ".$tab."_tx_nome ASC")." 
		".(!empty(urldecode($_GET['extra_limite']))? " LIMIT ".urldecode($_GET['extra_limite']): '').";"
	;

	$result = mysqli_fetch_all(
		query($sql),
		MYSQLI_ASSOC
	);

	$json = [];
	foreach($result as $row){
		if($extra_busca != ''){
			$extra_exibe = "[".$row[$extra_busca]."]";
		}
		$json[] = ['id'=>$row[$tab.'_nb_id'], 'text'=>$extra_exibe.$row[$tab.'_tx_nome']];

	}

	echo json_encode($json);
?>