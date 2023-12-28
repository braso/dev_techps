<?php
// storing  request (ie, get/post) global array to a variable  
$requestData= $_REQUEST;


/* Database connection start */
include_once $_SERVER['DOCUMENT_ROOT'].$_POST['path']."/conecta.php";
include_once $_POST['arquivo'];

/* Database connection end */


$columns = $_POST['valores'];
$t_valores = count($columns);

// getting total number records without any search
$sql=$_POST['sql'];


$query=mysqli_query($conn, $sql) or die(mysqli_error($conn));
$totalData = mysqli_num_rows($query);
$totalFiltered = $totalData;  // when there is no search parameter then total number rows = total number filtered rows.

preg_match('/(.*)\((.*?)\)(.*)/',$columns[$requestData['order'][0]['column']], $match2);
if($match2[2]){
	
	$parametros = explode(',',$match2[2]);
	$order2 = $parametros[0];
}else{
	$order2 = $columns[$requestData['order'][0]['column']];
}


$query=mysqli_query($conn, $sql) or die(mysqli_error($conn));
$totalFiltered = mysqli_num_rows($query); // when there is a search parameter then we have to modify total number filtered rows as per search result. 

if(!empty($requestData['order'][0]['dir'])){
    $sql.=" ORDER BY ". $order2."   ".$requestData['order'][0]['dir']." $limit";
}
else{
    $sql.=$limit;
}
/* $requestData['order'][0]['column'] contains colmun index, $requestData['order'][0]['dir'] contains order such as asc/desc  */	
$query=mysqli_query($conn, $sql) or die(mysqli_error($conn));

$data = array();

//EXEMPLO DO PREG_MATCH PARA EXTRAIR FUNCAO E SEUS PARAMETROS
// $text = 'This is a line (an example between parenthesis)';
// preg_match('/(.*)\((.*?)\)(.*)/', $text, $match);
// echo "in parenthesis: " . $match[2] . "<br>";
// echo "before and after: " . $match[1] . $match[3] . "<br>";


while( $row=mysqli_fetch_array($query) ) {  // preparing an array
	$nestedData=array(); 


	for($i=0;$i<$t_valores;$i++){
		$text = $columns[$i];
		preg_match('/(.*)\((.*?)\)(.*)/', $text, $match);
		if(empty($match[2])){
			$nestedData[] = $row[$columns[$i]];
			print_r($nestedData);
		}
		else{
			$parametros = explode(',',$match[2]);
			$parametros[0] = $row[$parametros[0]];
			$result = call_user_func_array($match[1],$parametros);
			if($result=='')
				$result='';
			$nestedData[] = $result;
		}
	}
	
	
	$data[] = $nestedData;
}



$json_data = array(
			"draw"            => intval( $requestData['draw'] ),   // for every request/draw by clientside , they send a number as a parameter, when they recieve a response/data they first check the draw number, so we are sending same number in draw. 
			"recordsTotal"    => intval( $totalData ),  // total number of records
			"recordsFiltered" => intval( $totalFiltered ), // total number of records after searching, if there is no searching then totalFiltered = totalData
			"data"            => $data   // total data array
			);

echo json_encode($json_data);  // send data as json format

?>
