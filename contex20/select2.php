<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/
	
	$interno = true; //Utilizado no conecta.php para reconhecer se quem está tentando acessar é uma tela ou uma query interna.
	include_once "../..".$_GET["path"]."/conecta.php";

	GLOBAL $conn;
	
	$tabela		= $_GET["tabela"];
	$tab		= substr($_GET["tabela"],0,4);
	$condicoes	= urldecode($_GET["condicoes"]);
	$colunas  	= urldecode($_GET["colunas"]);
	$ordem 		= !empty($_GET["ordem"])? "ORDER BY ".urldecode($_GET["ordem"]): "";
	$limite 	= !empty($_GET["limite"])? "LIMIT ".urldecode($_GET["limite"]): "";

	if(strpos($condicoes, "AND") < 6){
		$condicoes = substr($condicoes, strpos($condicoes, "AND")+3);
	}

	
	$sql = 
		"SELECT 
			{$tab}_nb_id as 'id', {$tab}_tx_nome as 'text' ".(!empty($colunas)? ",".$colunas: "")
			." FROM {$tabela}"
			." WHERE 1".(!empty($condicoes)? " AND {$condicoes}": "")
				.(!empty($_GET["q"])? " AND {$tab}_tx_nome LIKE '%{$_GET["q"]}%'": "")
			." {$ordem}"
			." {$limite}";

	$result = mysqli_fetch_all(
		query($sql),
		MYSQLI_ASSOC
	);

	$json = [];
	foreach($result as $row){
		$extra_exibe = "";
		if($colunas != ''){
			$row['text'] = "[".$row[$colunas]."]".$row['text'];
		}
		$json[] = $row;
	}

	echo json_encode($json);
?>