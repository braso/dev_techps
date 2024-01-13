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
				function contex_icone(id,acao,campos=\'\',valores=\'\',target=\'\',msg=\'\',action=\'\',just){
					console.log(target);
					if(msg){
						if(confirm(msg)){
							var form = document.getElementById("contex_icone_form"); 
							form.target=target;
							form.action=action;
							form.id.value=id;
							form.acao.value=acao;
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
					}else{
						var form = document.getElementById("contex_icone_form"); 
						form.target=target;
						form.action=action;
						form.id.value=id;
						form.acao.value=acao;
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
						form.submit();
					}

				}

			</script>
		';
	}

function grid3($cabecalho,$valores,$reg='10'){
	$rand = md5($sql);

	echo "<div class='col-md-12 col-sm-12'>";
	echo "<div class='portlet light'>";
	echo "<table class='table table-bordered table-striped table-condensed flip-content table-hover' id=$rand>";
	
	if(count($cabecalho)>0){

		echo "<thead><tr>";

		for($i=0;$i<count($cabecalho);$i++){
			echo "<th>$cabecalho[$i]</th>";
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
	echo "<nav aria-label='Page navigation example'>
  <ul class='pagination'>
    <li class='page-item'><a class='page-link' href='#'>Previous</a></li>
    <li class='page-item'><a class='page-link' href='#'>1</a></li>
    <li class='page-item'><a class='page-link' href='#'>2</a></li>
    <li class='page-item'><a class='page-link' href='#'>3</a></li>
    <li class='page-item'><a class='page-link' href='#'>Next</a></li>
  </ul>
</nav>";

echo '</div>';
echo '</div>';
}


	function grid($sql,$cabecalho,$valores,$label='',$col='12',$ordenar_coluna=1,$ordenar_sentido='asc',$paginar='10'){		
		global $CONTEX;

		$paginar = ($paginar == '')? '10': $paginar;
		$col = ($col < 1)? '12': $col;
		
		?>

		<form id='contex_icone_form' method="post" target="" action="">
			<input type="hidden" name="id" value="0">
			<input type="hidden" name="acao" value="sem_acao">
			<input type="hidden" name="just" value="">
			<input type="hidden" id="hidden">
		</form>
		<style type="text/css">
			th { font-size: 10px !important; }
			td { font-size: 10px !important; }
		</style>
		<?
			js_contex_icone();

			$rand = md5($sql);

			for($i=0; $i<count($cabecalho); $i++){
				$cabecalho[$i] = "<th>$cabecalho[$i]</th>";
			}
			$cabecalho = implode('', $cabecalho);

			$valores = "'".implode("','", $valores)."'";
		?>


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



		<script type="text/javascript" language="javascript" >

			$(document).ready(function() {
				var dataTable = $('#contex-grid-<?=$rand?>').DataTable( {
					"processing": true,
					"serverSide": true,
					"bFilter": false,
					"sEcho": true,
					"lengthMenu": [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"] ],
					"pageLength": <?=$paginar?>,
					// "stateSave": true,
					"order": [[ <?=$ordenar_coluna?>, "<?=$ordenar_sentido?>" ]],
					"ajax":{
						url :"<?=$CONTEX['path']?>/../contex20/server-side.php", // json datasource
						type: "post",  // method  , by default get
						data: {
							path: '<?=$CONTEX['path']?>',
							arquivo: '<?=$_SERVER['DOCUMENT_ROOT'].strtok($_SERVER['SCRIPT_NAME'], '?')?>',
							sql: '<?=mysql_escape_mimic($sql)?>',
							valores: [<?=$valores?>]
							
							
						},
						error: function (request, error) {
							
							alert("Falha ao carregar dados: " + request.responseText);
							console.log(request.responseText);
						}
					}
				} );
			} );
		</script>

			
		<?
	}
?>