<?php
include "conecta.php";

cabecalho("Página Teste");

abre_form('Filtro de Busc2');

// $text = 'strtolower(cida_tx_estado,1)';
// preg_match('/(.*)\((.*?)\)(.*)/', $text, $match);
// echo "in parenthesis: " . $match[2] . "<br>";
// echo "before and after: " . $match[1] . $match[3] . "<br>";
// $parametros = explode(',',$match[2]);
// print_r($parametros);


?>


											
												<div class="row">
													<div class="col-md-2 margin-bottom-10">
														<input type="text" class="form-control" placeholder="Código"> </div>
													<div class="col-md-3 margin-bottom-10">
														<input type="text" class="form-control" placeholder="Nome"> </div>
													<div class="col-md-4 margin-bottom-10">
														<input type="text" class="form-control" placeholder="Data"> </div>
													<div class="col-md-3 margin-bottom-10">
														<input type="text" class="form-control" placeholder="Login"> </div>
												</div>
												<div class="form-actions">
													<button type="submit" class="btn default">Buscar</button>
													<button type="submit" class="btn default">Voltar</button>
												</div>
<?
fecha_form();

$sql = "SELECT * FROM cidade WHERE 1=1";
$cab = array('ID','CIDADE','UF','STATUS','');
$val = array('cida_nb_id','cida_tx_nome','strtolower(cida_tx_estado)','cida_tx_status','');

grid($sql,$cab,$val);

?>




<?

rodape();


?>

