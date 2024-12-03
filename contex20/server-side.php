<?php	

	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/
		header("Access-Control-Allow-Origin: *");
		header("Access-Control-Allow-Methods: POST");

	//Conferir campos obrigatórios{
		if(empty($_POST) || empty($_POST["path"]) || empty($_POST["columns"]) || empty($_REQUEST)){
			echo "Missing information. [".empty($_POST).", ".empty($_POST["path"]).", ".empty($_POST["columns"]).", ".empty($_REQUEST)."]";
			exit;
		}
	//}

	$interno = true; //Utilizado no conecta.php para reconhecer se quem está tentando acessar é uma tela ou uma query interna.
	include_once "../..".$_POST['path']."/conecta.php";
	
	$columns = $_POST["columns"];
	
	$totalQuery = !empty($_POST["sql"])? mysqli_fetch_all(query(base64_decode($_POST["sql"])), MYSQLI_ASSOC): [];


	$limit = ['start' => $_REQUEST['start'], 'length' => $_REQUEST['length']];
	$limitedQuery = [];
	if($limit['length'] != '-1'){
		for($f = $limit['start']; $f < $limit['start']+$limit['length']; $f++){
			if(!empty($totalQuery[$f])){
				$limitedQuery[] = $totalQuery[$f];
			}
		}
	}else{
		$limitedQuery = $totalQuery;
	}
	
	$data = [];
	//Ordenar{
		
		$orderBy = $_REQUEST['order'][0]['column'];

		$isInt = (!empty($columns[$orderBy]) && is_int(strpos($columns[$orderBy], "_nb_")));

		$orderArray = [];
		foreach($limitedQuery as $row){
			if($isInt){
				$orderArray[] = intval(array_values($row)[$orderBy]);
			}else{
				$orderArray[] = array_values($row)[$orderBy];
			}
		}
		array_multisort(
			$orderArray,
			($_REQUEST['order'][0]['dir'] == 'desc'? SORT_ASC: SORT_DESC),
			$limitedQuery
		);
	//}

	foreach($limitedQuery as $row){  // preparing an array
		$nestedData = [];
		for($i = 0; $i < count($columns); $i++){
			$row = (array)$row;
			$text = $columns[$i];

			//Pegue se o texto for uma função com seus parâmetros
			preg_match('/^\w+\((.*)\)$/', $text, $match);
			
			if(empty($match)){
				$nestedData[] = !empty($row[$columns[$i]])? $row[$columns[$i]]: '';
			}else{
				$parametros = explode(',', $match[1]);
				$parametros[0] = $row[$parametros[0]];
				if(!empty($parametros)){
					try{
						$result = call_user_func_array(explode("(", $match[0])[0], $parametros);
					}catch(TypeError $e){
						$result = "";
					}
				}
				$nestedData[] = $result;
			}
		}
		$data[] = $nestedData;
	}

	$json_data = [
		"draw"            => intval($_REQUEST['draw']), // for every request/draw by clientside , they send a number as a parameter, when they recieve a response/data they first check the draw number, so we are sending same number in draw. 
		"recordsTotal"    => count($totalQuery),  		// total number of records
		"recordsFiltered" => count($totalQuery), 		// total number of records after searching, if there is no searching then totalFiltered = totalData
		"data"            => $data						// total data array
	];

	echo json_encode($json_data);  // send data as json format
?>