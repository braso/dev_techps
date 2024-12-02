<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);c
	//*/
	function mysql_escape_mimic($inp) {
		if(is_array($inp))
			return array_map(__METHOD__, $inp);

		if(!empty($inp) && is_string($inp)) {
			return str_replace(["\\", "\0", "\n", "\r", "'", "'", "\x1a"], ["\\\\", "\\0", "\\n", "\\r", "\\'", "\\'", "\\Z"], $inp);
		}

		return $inp;
	}

	// function conferirParametroPadrao($idEmpresa, $jornadaSemanal, $jornadaSabado, $percHESemanal, $percHEEx){

	// 	echo "<script>
	// 		function conferirParametroPadrao(jornadaSemanal, jornadaSabado, percHESemanal, percHEEx){
	// 			var padronizado = (
	// 				jornadaSemanal == parent.document.contex_form.jornadaSemanal.value &&
	// 				jornadaSabado == parent.document.contex_form.jornadaSabado.value &&
	// 				percHESemanal == parent.document.contex_form.percHESemanal.value &&
	// 				percHEEx == parent.document.contex_form.percHEEx.value
	// 			);
	// 			parent.document.getElementsByName('textoParametroPadrao')[0].getElementsByTagName('p')[0].innerText = (padronizado? 'Sim': 'Não');
	// 		}
	// 		</script>"
	// 	;
	// }

	function montarTabelaPonto(array $cabecalho, array $valores): string{
		// $rand = md5($sql);
		$grid = 
			"<div class='table-responsive'>
				<table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact'"/*.id=$rand*/.">
					<thead>"
						.(!empty($cabecalho)?"<tr><th class='th-align'>".implode("</th><th class='th-align'>", $cabecalho)."</th></tr>": "").
					"</thead>
					<tbody>"
						.implode("", array_map(function($valor){return "<tr><td>".implode("</td><td>", $valor)."</td></tr>";}, $valores)).
					"</tbody>
				</table>
				(*): Registros excluídos manualmente.<br>
				(**): 00:00 Caso esteja dentro da tolerância
			</div>"
		;

		return $grid;
	}

	function js_contex_icone(){
		echo "
			<script type='text/javascript'>
				function contex_icone(id, acao, msgConfirm=''){
					var form = document.getElementById('contex_icone_form'); 
					form.id.value = id;
					form.acao.value = acao;
					if(!msgConfirm || confirm(msgConfirm)){
						form.submit();
					}
				}
			</script>
		";
	}

	function grid($sql, $cabecalho, $valores=[], $label="", $col="12", $numColunaOrdem=1, string $sentidoOrdem = "asc", $paginar="10"){
		global $CONTEX;

		$sql = urldecode(str_replace(["%0D", "%0A", "%09"], " ", urlencode($sql)));

		$paginar = (empty($paginar))? "10": $paginar;
		$col = ($col < 1)? "12": $col;

		
		js_contex_icone();
		
		$rand = md5($sql);

		$cabecalho = "<th>".implode("</th><th>", $cabecalho)."</th>";
		
		if(!empty($label)){
			$label = 
				"<div class='portlet-title'>
						<div class='caption'>
							<span class='caption-subject font-dark bold uppercase'>".$label."</span>
						</div>
						<!-- <div class='tools'> </div> -->
				</div>"
			;
		}

		?>
		<form id='contex_icone_form' method='post' target='' action=''>
			<input type='hidden' name='id' value='0'>
			<input type='hidden' name='acao' value='sem_acao'>
			<input type='hidden' name='just' value=''>
			<input type='hidden' name='atualiza' value=''>
			<input type='hidden' id='hidden'>
		</form>
		
		<style type="text/css">
			th { font-size: 10px !important; }
			td { font-size: 10px !important; }

			@media print{
				form > div:nth-child(2) > div:nth-child(1),
				form > div:nth-child(2) > div:nth-child(3),
				form > div:nth-child(2) > div:nth-child(5),
				form > div:nth-child(2) > div:nth-child(6),
				form > div:nth-child(3) > div,
				form > div.form-actions
				<?=", #contex-grid-".$rand."_length, #contex-grid-".$rand."_info"?>
				body > div.scroll-to-top > i{
					display: none;
				}
				@page{
					size: landscape;
				}
			}
		</style>
		<!-- BEGIN EXAMPLE TABLE PORTLET-->
		<div class="col-md-<?=$col?> col-sm-<?=$col?>">
			<div class="portlet light ">
				<?=$label?>
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
		<script src="<?=$_ENV["APP_PATH"]?>/contex20/assets/global/scripts/datatable.js" type="text/javascript"></script>
		<script src="<?=$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
		<script src="<?=$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
		<!-- END PAGE LEVEL PLUGINS -->

		<!-- BEGIN PAGE LEVEL SCRIPTS -->
		<script src="<?=$_ENV["APP_PATH"]?>/contex20/assets/scripts/table-datatables-responsive.min.js" type="text/javascript"></script>
		<!-- END PAGE LEVEL SCRIPTS -->
		<?php

		include_once "conecta.php";


		preg_match('/(.*)\((.*?)\)(.*)/', $numColunaOrdem, $match);
		if(isset($match[2])){
			$parametros = explode(',',$match[2]);
			$numColunaOrdem = $parametros[0];
		}


		echo 
			"<div id='ajaxCall'>
				<script type='text/javascript' language='javascript'>
					$(document).ready(function(){
						var dataTable = $('#contex-grid-{$rand}').DataTable({
							'processing': true,
							'serverSide': true,
							'bFilter': false,
							'sEcho': true,
							'lengthMenu': [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
							
							'order': [{$numColunaOrdem}, '{$sentidoOrdem}'],
							'ajax':{
								'url' :'{$_ENV["URL_BASE"]}{$_ENV["APP_PATH"]}/contex20/server-side.php', // json datasource
								'type': 'post',  // method, by default get
								'data': {
									'path': '{$CONTEX["path"]}',
									'arquivo': '{$_SERVER["DOCUMENT_ROOT"]}".strtok($_SERVER["SCRIPT_NAME"], "?")."',
									'sql': '".base64_encode($sql)."',
									'columns': ['".implode("','", $valores)."']
								},
								error: function (request, error) {
									console.log(request);
									console.log(error);
								}
							}
						});
					});
					
					document.getElementById('ajaxCall').innerHTML = '';
				</script>
			</div>"
		;
	}