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
			"
			<style>
				// .table-responsive>.table>tbody>tr>td:nth-child(1){
				// 	background-color: white;
				// 	left: 0px;
				// 	position: sticky;
				// }
				// 
				// .table-responsive>.table>tbody>tr>td:nth-child(2){
				// 	background-color: white;
				// 	left: 40px;
				// 	position: sticky;
				// }

				.table-head{
					background-color: white;
					position: sticky;
					top: -1px;
				}
				// .table-responsive>.table{
				// 	border-collapse: separate;
				// }
			</style>
			<div class='table-responsive' style='max-height: 85vh;'>
				<table class='table w-auto text-xsmall table-bordered table-striped table-condensed flip-content table-hover compact'"/*.id=$rand*/.">
					<thead class='table-head'>"
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

		echo 
			"<form id='contex_icone_form' method='post' target='' action=''>
				<input type='hidden' name='id' value='0'>
				<input type='hidden' name='acao' value='sem_acao'>
				<input type='hidden' name='just' value=''>
				<input type='hidden' name='atualiza' value=''>
				<input type='hidden' id='hidden'>
			</form>
			
			<style type='text/css'>
				th { font-size: 10px !important; }
				td { font-size: 10px !important; }

				@media print{
					form > div:nth-child(2) > div:nth-child(1),
					form > div:nth-child(2) > div:nth-child(3),
					form > div:nth-child(2) > div:nth-child(5),
					form > div:nth-child(2) > div:nth-child(6),
					form > div:nth-child(3) > div,
					form > div.form-actions, 
					#contex-grid-{$rand}_length, #contex-grid-{$rand}_info
					body > div.scroll-to-top > i{
						display: none;
					}
					@page{
						size: landscape;
					}
				}
			</style>
			<!-- BEGIN EXAMPLE TABLE PORTLET-->
			<div class='col-md-{$col} col-sm-{$col}'>
				<div class='portlet light'>
					{$label}
					<div class='portlet-body'>
						<table id='contex-grid-{$rand}' class='table compact table-striped table-bordered table-hover dt-responsive' width='100%' id='sample_2'>
							<thead>
								<tr>
									{$cabecalho}
								</tr>
							</thead>
						</table>
					</div>
				</div>
			</div>
			<!-- END EXAMPLE TABLE PORTLET-->

			<!-- BEGIN PAGE LEVEL PLUGINS -->
			<script src='{$_ENV["APP_PATH"]}/contex20/assets/global/scripts/datatable.js' type='text/javascript'></script>
			<script src='{$_ENV["APP_PATH"]}/contex20/assets/global/plugins/datatables/datatables.min.js' type='text/javascript'></script>
			<script src='{$_ENV["APP_PATH"]}/contex20/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js' type='text/javascript'></script>
			<!-- END PAGE LEVEL PLUGINS -->

			<!-- BEGIN PAGE LEVEL SCRIPTS -->
			<script src='{$_ENV["APP_PATH"]}/contex20/assets/scripts/table-datatables-responsive.min.js' type='text/javascript'></script>
			<!-- END PAGE LEVEL SCRIPTS -->"
		;

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
							'pageLength': {$paginar},
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

	function gridDinamico(string $nomeTabela, array $campos, array $camposBusca, string $queryBase, string $jsFunctions = "", int $width = 12, $tabIndex = -1){
		$result = 
			"<link href='{$_ENV["URL_BASE"]}{$_ENV["APP_PATH"]}/contex20/css/grid_dinamico.css' rel='stylesheet' type='text/css' />
			<div class='col-md-{$width}'>
				<div class='portlet light'>
					<div class='table-div' style='margin-top: 8px;overflow-x: auto; border-radius: 10px; max-height: 87vh;'>
						<div class='table-loading-icon' style='place-items: center;position: absolute;width: 89vw;z-index: 2;top: 50px;'>
						</div>
						<div class='table-loading-icon' style='place-items: center'></div>
						<table name='{$nomeTabela}' id='result' class='table table-bordered grid-dinamico' id='sample_2'>
							<thead class='table-head'>
							</thead>
							<tbody>
							</tbody>
						</table>
					</div>
					<div class='grid-footer'>
						<div class='col-sm-1 margin-bottom-5' style='width: min-content;'>
							<label>Qtd. Registros</label>
							<input name='limit' id='limit' value='10' autocomplete='off' type='number' class='form-control input-sm' min='1' max='99' ".($tabIndex>0? "tabindex='".$tabIndex."'": "").">
						</div>
						<div class='tab-pagination'>
						</div>
					</div>
				</div>
			</div>"
		;
		
		if(!empty($campos["actions"])){
			$campos["actions"] = json_encode($campos["actions"]);
		}

		$result .=
			"<script>urlTableInfo = '{$_ENV["APP_PATH"]}{$_ENV["CONTEX_PATH"]}/getTableInfo.php';</script>
			<script>
				const searchFields = ".json_encode($camposBusca).";
				const fields = ".json_encode($campos).";
				const queryBase = '".base64_encode($queryBase." WHERE 1")."';
				{$jsFunctions}
			</script>
			<script src='{$_ENV["APP_PATH"]}/contex20/assets/global/plugins/jquery.min.js' type='text/javascript'></script>
			<script src='{$_ENV["APP_PATH"]}/contex20/js/grid_dinamico.js'></script>"
		;

		return $result;
	}

	function criarIconesGrid(array $classNames, array $actionFiles, array $actionFuncs){
		if(count($classNames) != count($actionFiles) || count($classNames) != count($actionFuncs)){
			throw new Exception("Os argumentos não possuem o mesmo tamanho.");
		}
		$result = [
			"tags" => [],
			"functions" => []
		];

		for($f = 0; $f < count($classNames); $f++){
			$result["tags"][] = "<spam class='{$classNames[$f]}'></spam>";
		}

		for($f = 0; $f < count($actionFuncs); $f++){
			$result["functions"][] = 
				"$('[class=\"{$classNames[$f]}\"]').click(function(event){
					form = document.createElement('form');
					form.setAttribute('method', 'post');
					form.setAttribute('action', '{$actionFiles[$f]}');
					
					idInput = document.createElement('input');
					idInput.setAttribute('name', 'id');
					idInput.setAttribute('value', $(event.target).parent().parent().children()[0].innerHTML);
					form.appendChild(idInput);
					
					actionInput = document.createElement('input');
					actionInput.setAttribute('name', 'acao');
					actionInput.setAttribute('value', '{$actionFuncs[$f]}');
					form.appendChild(actionInput);

					inputs = document.contex_form.getElementsByTagName('input');
					selects = document.contex_form.getElementsByTagName('select');

					if(inputs != undefined){
						for(key in inputs){
							if(inputs[key].value != undefined && inputs[key].value != ''){
								form.appendChild(inputs[key].cloneNode(true));
							}
						}
					}
					if(selects != undefined){
						for(key in selects){
							if(selects[key].value != undefined && selects[key].value != ''){
								form.appendChild(selects[key].cloneNode(true));
							}
						}
					}

					document.getElementsByTagName('body')[0].appendChild(form);
					form.submit();
				});"
			;
		}

		return $result;
	}

	function downloadArquivo() {
		// Verificar se o arquivo existe
		if(file_exists($_POST["caminho"])){
			// Configurar cabeçalhos para forçar o download
			header("Content-Description: File Transfer");
			header("Content-Type: application/octet-stream");
			header("Content-Disposition: attachment; filename=".basename($_POST["caminho"]));
			header("Expires: 0");
			header("Cache-Control: must-revalidate");
			header("Pragma: public");
			header("Content-Length: ".filesize($_POST["caminho"]));

			// Lê o arquivo e o envia para o navegador
			readfile($_POST["caminho"]);
		}else{
			set_status("O arquivo não foi encontrado.");
		}

		index();
		exit;
	}