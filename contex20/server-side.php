<?php

	$interno = true; //Utilizado no conecta.php para reconhecer se quem está tentando acessar é uma tela ou uma query interna.
	include_once $_POST['path']."/conecta.php";
	
	$columns = $_POST['columns'];
	
	$_POST['totalQuery'] = str_replace(["null", "\t", "\n", "\r"], ["\"\"", "", "", ""], $_POST['totalQuery']);

	$totalQuery = json_decode($_POST['totalQuery']);
	
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
	//EXEMPLO DO PREG_MATCH PARA EXTRAIR FUNCAO E SEUS PARAMETROS
	// $text = 'This is a line (an example between parenthesis)';
	// preg_match('/(.*)\((.*?)\)(.*)/', $text, $match);
	// echo "in parenthesis: " . $match[2] . "<br>";
	// echo "before and after: " . $match[1] . $match[3] . "<br>";

	$nomeEmpresaMatriz = mysqli_fetch_assoc(
		query(
			"SELECT empr_tx_nome FROM empresa
				WHERE empr_tx_Ehmatriz = 'sim'
			LIMIT 1"
		)
	);
	$nomeEmpresaMatriz = !empty($nomeEmpresaMatriz)? $nomeEmpresaMatriz['empr_tx_nome']: null;
	
	$data = [];
	foreach($limitedQuery as $row){  // preparing an array
		$nestedData = [];
		for($i = 0; $i < count($columns); $i++){
			$row = (array)$row;
			$text = $columns[$i];
			preg_match('/(.*)\((.*?)\)(.*)/', $text, $match);
			$isAFunction = !empty($match[2]);
			if(!$isAFunction){
				$nestedData[] = !empty($row[$columns[$i]])? $row[$columns[$i]]: '';
				if(!empty($nomeEmpresaMatriz)){
					if($text == 'empr_tx_nome'){
						$nomeEmpresaComIcone = $nomeEmpresaMatriz.'  <i class="fa fa-star" aria-hidden="true"></i>';
						if (in_array($nomeEmpresaMatriz, $nestedData)) {
							$indice = array_search($nomeEmpresaMatriz, $nestedData);
							$nestedData[$indice] = $nomeEmpresaComIcone;
						}
					}
				}
			}else{
				$parametros = explode(',', $match[2]);
				$parametros[0] = $row[$parametros[0]];
				$result = call_user_func_array($match[1], $parametros);
				$nestedData[] = $result;
			}
		}
		$data[] = $nestedData;
	}

	//Ordenar{
		$orderBy = $_REQUEST['order'][0]['column'];

		$isInt = (!empty($columns[$_REQUEST['order'][0]['column']]) && $columns[$_REQUEST['order'][0]['column']] == '');

		$orderArray = [];
		foreach($data as $row){
			if($isInt){
				$orderArray[] = intval($row[$orderBy]);
			}else{
				$orderArray[] = $row[$orderBy];
			}
		}
		array_multisort(
			$orderArray,
			($_REQUEST['order'][0]['dir'] == 'desc'? SORT_ASC: SORT_DESC),
			$data
		);
	//}

	$json_data = [
		"draw"            => intval($_REQUEST['draw']), // for every request/draw by clientside , they send a number as a parameter, when they recieve a response/data they first check the draw number, so we are sending same number in draw. 
		"recordsTotal"    => count($totalQuery),  		// total number of records
		"recordsFiltered" => count($totalQuery), 		// total number of records after searching, if there is no searching then totalFiltered = totalData
		"data"            => $data						// total data array
	];

	echo json_encode($json_data);  // send data as json format
?>