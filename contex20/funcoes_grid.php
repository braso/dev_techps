<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/
	function mysql_escape_mimic($inp) {
		if(is_array($inp))
			return array_map(__METHOD__, $inp);

		if(!empty($inp) && is_string($inp)) {
			return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
		}

		return $inp;
	}

	function grid2($cabecalho,$valores){
		// $rand = md5($sql);
		echo '<div class="table-responsive">';
		echo "<table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact'"./*id=$rand.*/">";
		
		if(count($cabecalho)>0){

			echo "<thead><tr>";

			for($i=0;$i<count($cabecalho);$i++){
				echo "<th class='th-align'>$cabecalho[$i]</th>";
			}

			echo "</thead></tr>";
		}

		if(count($valores)>0){
			echo "<tbody>";
			
			for($i=0;$i<count($valores);$i++){
				echo "<tr>";
				for($j=0;$j<count($valores[$i]);$j++){
					echo "<td>".$valores[$i][$j]."</td>";
				}
				echo "</tr>";
			}

			echo "</tbody>";
		}

		echo "</table>";
		echo "(*): Registros excluídos manualmente.<br>";
		echo "(**): 00:00 Caso esteja dentro da tolerância";
		echo '</div>';

		
	?>
	<!-- 
	<form id='contex_icone_form' method="post" target="" action="">
		<input type="hidden" name="id" value="0">
		<input type="hidden" name="acao" value="sem_acao">
		<input type="hidden" id="hidden">
	</form>
	-->
	<?
	// js_contex_icone();

	}

	function js_contex_icone(){
		echo '
			<script type="text/javascript">
				function contex_icone(id,acao,campos=\'\',valores=\'\',target=\'\',msg=\'\',action=\'\',data_de=\'\',data_ate=\'\',just=\'\'){
					console.log(campos);
					if(msg){
						if(confirm(msg)){
							var form = document.getElementById("contex_icone_form"); 
							form.target=target;
							form.action=action;
							form.id.value=id;
							form.acao.value=acao;
							form.data_de.value=data_de;
							form.data_ate.value=data_ate;
							if(campos){
								form.hidden.value=valores;
								form.hidden.name=campos;
							}
							campos = campos.split(\',\');
							valores = valores.split(\',\');
							for(f = 0; f < campos.length; f++){
								form.append(\'<input type="hidden" name="\'+campos[f]+\'" value="\'+valores[f]+\'" /> \');
							}
							form.submit();
						}
					}else{
						var form = document.getElementById("contex_icone_form"); 
						form.target=target;
						form.action=action;
						form.id.value=id;
						form.acao.value=acao;
						form.data_de.value=data_de;
						form.data_ate.value=data_ate;
						form.just.value=just;
						if(campos){
							form.hidden.value=valores;
							form.hidden.name=campos;
						}
						campos = campos.split(\',\');
						valores = valores.split(\',\');
						for(f = 0; f < campos.length; f++){
							form.append(\'<input type="hidden" name="\'+campos[f]+\'" value="\'+valores[f]+\'" /> \');
						}
						form.submit();
					}

				}

			</script>
		';
	}


	function grid($sql,$cabecalho,$valores=[],$label='',$col='12',$ordenar_coluna=1,$ordenar_sentido='asc',$paginar='10'){		
		global $CONTEX;

		$paginar = ($paginar == '')? '10': $paginar;
		$col = ($col < 1)? '12': $col;
		
		?>

		<form id='contex_icone_form' method="post" target="" action="">
			<input type="hidden" name="id" value="0">
			<input type="hidden" name="acao" value="sem_acao">
			<input type="hidden" name="data_de" value="">
			<input type="hidden" name="data_ate" value="">
			<input type="hidden" name="just" value="">
			<input type="hidden" id="hidden">
		</form>
		<?
			js_contex_icone();

			$rand = md5($sql);

			for($i=0; $i<count($cabecalho); $i++){
				$cabecalho[$i] = "<th>$cabecalho[$i]</th>";
			}
			$cabecalho = implode('', $cabecalho);

			$valores = "'".implode("','", $valores)."'";
		?>
		<style type="text/css">
			th { font-size: 10px !important; }
			td { font-size: 10px !important; }

			@media print{
					body > div.page-container > div > div.page-content > div > div > div > div > div:nth-child(1) > div {
						display: none;
					}
					
					contex-grid-<?=$rand?>,
					contex-grid-<?=$rand?> * {
						display: block;
					}
					@page { size: landscape; }
				}
		</style>


											<!-- BEGIN EXAMPLE TABLE PORTLET-->
											<div class="col-md-<?=$col?> col-sm-<?=$col?>">
												<div class="portlet light ">
													<?if($label!=''){?>
													<div class="portlet-title">
															<div class="caption">
																<span class="caption-subject font-dark bold uppercase"><?=$label?></span>
															</div>
															<!-- <div class="tools"> </div> -->
													</div>
													<?}?>
													<div class="portlet-body">
														<table id="contex-grid-<?=$rand?>" class="table compact table-striped table-bordered table-hover dt-responsive" width="100%" id="sample_2">
															<thead>
																<tr>
																	<?=$cabecalho?>
																</tr>
															</thead>
														</table>
													</div>
												</div>
											</div>
											<!-- END EXAMPLE TABLE PORTLET-->

				<!-- BEGIN PAGE LEVEL PLUGINS -->
				<script src="/contex20/assets/global/scripts/datatable.js" type="text/javascript"></script>
				<script src="/contex20/assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
				<script src="/contex20/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
				<!-- END PAGE LEVEL PLUGINS -->

				<!-- BEGIN PAGE LEVEL SCRIPTS -->
				<script src="/contex20/assets/scripts/table-datatables-responsive.min.js" type="text/javascript"></script>
				<!-- END PAGE LEVEL SCRIPTS -->


		<?
		
		echo 
			"<script type=\"text/javascript\" language=\"javascript\" >
				$(document).ready(function() {
					var dataTable = $('#contex-grid-".$rand."').DataTable( {
						\"processing\": true,
						\"serverSide\": true,
						\"bFilter\": false,
						\"sEcho\": true,
						\"lengthMenu\": [ [10, 25, 50, 100, -1], [10, 25, 50, 100, \"Todos\"] ],
						\"pageLength\": ".$paginar.",
						// \"stateSave\": true,
						\"order\": [[ ".$ordenar_coluna.", \"".$ordenar_sentido."\" ]],
						\"ajax\":{
							url :\"".$CONTEX['path']."/../contex20/server-side.php\", // json datasource
							type: \"post\",  // method  , by default get
							data: {
								path: '".$CONTEX['path']."',
								arquivo: '".$_SERVER['DOCUMENT_ROOT'].strtok($_SERVER['SCRIPT_NAME'], '?')."',
								sql: '".mysql_escape_mimic($sql)."',
								valores: [".$valores."]
								
								
							},
							error: function (request, error) {
								alert(\"Falha ao carregar dados: \" + request.responseText);
								console.log(request);
							}
						}
					} );
				} );
			</script>"
		;


		/*server-side{
			$requestData = [
				'order' => [[$ordenar_coluna,$ordenar_sentido]],
				'start' => ,
				'length' => ,
			];

			//Database connection{
				include_once $_SERVER['DOCUMENT_ROOT'].$_POST['path']."/conecta.php";
				include_once $_POST['arquivo'];
			//}


			$columns = $_POST['valores'];
			$t_valores = count($columns);

			// getting total number records without any search
			$sql=$_POST['sql'];


			$query=mysqli_query($conn, $sql) or die(mysqli_error($conn));
			$totalData = mysqli_num_rows($query);
			$totalFiltered = $totalData;  // when there is no search parameter then total number rows = total number filtered rows.

			preg_match('/(.*)\((.*?)\)(.*)/',$columns[$requestData['order'][0]['column']], $match2);
			if(isset($match2[2])){
				$parametros = explode(',',$match2[2]);
				$order2 = $parametros[0];
			}else{
				$order2 = $columns[$requestData['order'][0]['column']];
			}


			$query=mysqli_query($conn, $sql) or die(mysqli_error($conn));
			$totalFiltered = mysqli_num_rows($query); // when there is a search parameter then we have to modify total number filtered rows as per search result. 

			if($requestData['length'] != '-1'){
				$limit =  " LIMIT ".$requestData['start']." ,".$requestData['length'];
			}else{
				$limit = " LIMIT 10";
			}
			$sql.=" ORDER BY ". $order2."   ".(!empty($requestData['order'][0]['dir'])? $requestData['order'][0]['dir']: '')." $limit";


			// $requestData['order'][0]['column'] contains colmun index, $requestData['order'][0]['dir'] contains order such as asc/desc
			$query=mysqli_query($conn, $sql) or die(mysqli_error($conn));

			$data = array();

			//EXEMPLO DO PREG_MATCH PARA EXTRAIR FUNCAO E SEUS PARAMETROS
			// $text = 'This is a line (an example between parenthesis)';
			// preg_match('/(.*)\((.*?)\)(.*)/', $text, $match);
			// echo "in parenthesis: " . $match[2] . "<br>";
			// echo "before and after: " . $match[1] . $match[3] . "<br>";


			while( $row=mysqli_fetch_array($query) ) {  // preparing an array
				$nestedData=[];


				for($i=0;$i<$t_valores;$i++){
					$text = $columns[$i];
					preg_match('/(.*)\((.*?)\)(.*)/', $text, $match);
					if(empty($match[2])){
						$nestedData[] = $row[$columns[$i]];
						$nome_sql = 'SELECT empr_tx_nome FROM `empresa` WHERE empr_tx_Ehmatriz = "sim" LIMIT 1';
						$query_empresa=mysqli_query($conn, $nome_sql ) or die(mysqli_error($conn));
						$nome_empresa = mysqli_fetch_all($query_empresa, MYSQLI_ASSOC);
						$novoNome =  $nome_empresa[0]['empr_tx_nome'].'  <i class="fa fa-star" aria-hidden="true"></i>';
						if (in_array($nome_empresa[0]['empr_tx_nome'], $nestedData)) {
							$indice = array_search($nome_empresa[0]['empr_tx_nome'], $nestedData);
						
							$nestedData[$indice] = $novoNome;

						}
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
				
			// 	if($nestedData[0][1]){
			// 	    $nestedData[1][1] = "$nestedData[1][1] <i class='fa fa-star' aria-hidden='true'></i>";
			// 	}

				$data[] = $nestedData;
			}



			$json_data = array(
						"draw"            => intval( $requestData['draw'] ),   // for every request/draw by clientside , they send a number as a parameter, when they recieve a response/data they first check the draw number, so we are sending same number in draw. 
						"recordsTotal"    => intval( $totalData ),  // total number of records
						"recordsFiltered" => intval( $totalFiltered ), // total number of records after searching, if there is no searching then totalFiltered = totalData
						"data"            => $data   // total data array
						);

			echo json_encode($json_data);  // send data as json format
		//}*/
	}
?>