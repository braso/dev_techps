<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	if(isset($_GET["acao"])){
		if(empty($_POST["acao"])){
			$_POST["acao"] = $_GET["acao"];
		}
		
		if(($_POST["acao"] == $_GET["acao"] || in_array($_GET["acao"], ["index", "index()"]))){
			foreach($_GET as $key => $value) {
				if($key != "acao" && $value != ""){
					$_POST[$key] = $value;
				}
			}
		}
	}
	if(!empty($_POST["acao"])){
		$nomeFuncao = $_POST["acao"];
		if(is_int(strpos($_POST["acao"], "(")) && is_int(strpos($_POST["acao"], ")"))){
			$nomeFuncao = substr($_POST["acao"], 0, strpos($_POST["acao"], "("));
		}else{
			$_POST["acao"] .= "()";
		}
		if(function_exists($nomeFuncao)){
			if(preg_match_all("/^((.+[^\n])*)\((.*)\)$/", $_POST["acao"])){
				eval($_POST["acao"].";");
			}else{
				echo "ERRO: função mal formatada: ".$_POST["acao"];
			}
		}else{
			echo "ERRO: Função '".$nomeFuncao."' não existe!";
		}
		exit;
	}
	if(function_exists("index")){
		index();
		exit;
	}

	/**
	 * Pôe uma variável em um <pre></pre> para ser exibido de forma identada. 
	 * $die indica se o código deve parar logo após exibir essa variável.
	 */
	function dd($variavel, bool $die = true){
		echo "<pre>";
		var_dump($variavel);
		echo "</pre>";
		if($die){
			echo 
				"<script>
					if(document.getElementsByClassName('loading')[0].style.display != 'none'){
						document.getElementsByClassName('loading')[0].style.display = 'none';
					}
				</script>"
			;
			die();
		}
	}

	function validarCPF(string $cpf): bool{
		// Extrai somente os números
		$cpf = preg_replace( "/[^0-9]/is", "", $cpf);
		
		if(strlen($cpf) != 11 || preg_match_all("/\d{11}/", $cpf) === false){
			return false;
		}

		$digitosVerificadores = [$cpf[9], $cpf[10]];
		$verificadores = [0,0];

		for($f=0; $f<2; $f++){
			for($f2 = 0; $f2 < $f+9; $f2++){
				$verificadores[$f] += $cpf[$f2]*($f-$f2+10);
			}
			$verificadores[$f] = (($verificadores[$f]*10)%11)%10;
			if($digitosVerificadores[$f] != $verificadores[$f]){
				return false;
			}
		}

		$estados = [
			"Rio Grande do Sul",
			"Distrito Federal, Goiás, Mato Grosso do Sul ou Tocantins",
			"Pará, Amazonas, Acre, Amapá, Rondônia ou Roraima",
			"Ceará, Maranhão ou Piauí",
			"Pernambuco, Rio Grande do Norte, Paraíba ou Alagoas",
			"Bahia ou Sergipe",
			"Minas Gerais",
			"Rio de Janeiro ou Espírito Santo",
			"São Paulo",
			"Paraná ou Santa Catarina"
		];
		// echo "CPF do ".$estados[intval($cpf[8])];

		return true;
	}

	function formatToTime(int $hours, int $minutes, int $seconds = 0): string{
		if($seconds > 59){
			$qtdMinutes = intval($seconds/60);
			$seconds -= $qtdMinutes*60;
			$minutes += $qtdMinutes;
		}
		if($minutes > 59){
			$qtdHours = intval($minutes/60);
			$minutes -= $qtdHours*60;
			$hours += $qtdHours;
		}

		return sprintf("%02d:%02d", $hours, $minutes, ($seconds != 0? $seconds: "")).($seconds != 0? ":".sprintf("%02d", $seconds): "");
	}

	function lerEndossoCSV(string $filename){
		global $CONTEX;
		
		if(substr($filename, -4) != ".csv"){
			$filename .= ".csv";
		}

		$endosso = fopen($_SERVER["DOCUMENT_ROOT"].$CONTEX["path"]."/arquivos/endosso/".$filename, "r");
		$keys = fgetcsv($endosso);
		$values = fgetcsv($endosso);
		$endosso = [];
		for($j = 0; $j < count($keys); $j++){
			$endosso[$keys[$j]] = $values[$j];
		}

		$endosso["endo_tx_pontos"] = (array)json_decode($endosso["endo_tx_pontos"]);
		$endosso["totalResumo"] = (array)json_decode($endosso["totalResumo"]);

		//Referente a $endosso["totalResumo"]
		$versoesEndosso = [
			["diffRefeicao", "diffEspera", "diffDescanso", "diffRepouso", "diffJornada", "jornadaPrevista", "diffJornadaEfetiva", "maximoDirecaoContinua", "intersticio", "he50", "he100", "adicionalNoturno", "esperaIndenizada", "diffSaldo", "saldoAnterior", "saldoAtual"],
			["diffRefeicao", "diffEspera", "diffDescanso", "diffRepouso", "diffJornada", "jornadaPrevista", "diffJornadaEfetiva", "maximoDirecaoContinua", "intersticio", "he50", "he100", "adicionalNoturno", "esperaIndenizada", "diffSaldo", "saldoAnterior", "saldoAtual", "he50APagar", "he100APagar"],
			["diffRefeicao", "diffEspera", "diffDescanso", "diffRepouso", "diffJornada", "jornadaPrevista", "diffJornadaEfetiva", "maximoDirecaoContinua", "intersticio", "he50", "he100", "adicionalNoturno", "esperaIndenizada", "diffSaldo", "saldoAnterior", "saldoBruto", "he50APagar", "he100APagar"]
		];

		switch(array_keys($endosso["totalResumo"])){
			case $versoesEndosso[0]:
			case $versoesEndosso[1]:
				$endosso["totalResumo"]["saldoBruto"] = $endosso["totalResumo"]["saldoAtual"];
				unset($endosso["totalResumo"]["saldoAtual"]);
				[$endosso["totalResumo"]["he50APagar"], $endosso["totalResumo"]["he100APagar"]] = calcularHorasAPagar($endosso["totalResumo"]["saldoBruto"], $endosso["totalResumo"]["he50"], $endosso["totalResumo"]["he100"], (!empty($endosso["endo_tx_max50APagar"])? $endosso["endo_tx_max50APagar"]: "00:00"), ($endosso["totalResumo"]["para_tx_pagarHEExComPerNeg"]?? "nao"));
				$endosso["totalResumo"]["saldoFinal"] = operarHorarios([$endosso["totalResumo"]["saldoBruto"], $endosso["totalResumo"]["he50APagar"], $endosso["totalResumo"]["he100APagar"]], "-");
			break;
			case $versoesEndosso[2]:
				//Versão atual
				$endosso["totalResumo"]["saldoFinal"] = operarHorarios([$endosso["totalResumo"]["saldoBruto"], $endosso["totalResumo"]["he50APagar"], $endosso["totalResumo"]["he100APagar"]], "-");
			break;
			default:
				echo implode(", ", array_keys($endosso["totalResumo"]));
			break;
		}
		
		return $endosso;
	}

	function modal_alert(string $title, string $msg, array $formValues = []){
		$formValuesJS = "";
		foreach($formValues as $key => $value){
			if($key == 'acao'){
				$formValuesJS .= 
					"form.action = '".$value."'";	
				;
				continue;
			}


			$formValuesJS .= 
				"input = document.createElement('input');"
				."input.name = '".$key."';"
				."input.setAttribute('value', '".$value."');"
				."form.appendChild(input);"
			;
		}
		$title; $msg;
		include "modal_alert.php";
	}

	function inserir(string $tabela, array $campos, array $valores): array{
		return insertInto($tabela, $campos, $valores);
	}
	function insertInto(string $tabela, array $campos, array $valores): array{

		global $conn;

		if(count($campos) != count($valores)){
			echo "ERRO: Número de campos não confere com número de linhas na função de inserir!";
			return [];
		}

		$types = "";
		foreach($campos as $key => $campo){
			if(is_int(strpos($campo, "_tx_"))){
				$types .= "s";
			}else{
				$types .= "d";
			}
			$novoRegistro[$campo] = $valores[$key];
		}

		try{
			$statement = mysqli_prepare(
				$conn,
				"INSERT INTO $tabela (".implode(", ", array_keys($novoRegistro)).")"
				." VALUES (".implode(", ", array_pad([], count($novoRegistro), "?")).");"
			);
			mysqli_stmt_bind_param($statement, $types, ...array_values($novoRegistro));
			$registered = mysqli_stmt_execute($statement);
			
			if(!$registered){
				return [new Exception($statement->error)];
			}
			mysqli_stmt_close($statement);
			
		}catch(Exception $e){
			set_status("ERRO ao registrar.");
			return [$e];
		}

		$tabIdName = substr($tabela, 0, 4)."_nb_id";
		$result = mysqli_fetch_assoc(query(
			"SELECT {$tabIdName} FROM {$tabela} ORDER BY {$tabIdName} DESC LIMIT 1;"
		));

		return (is_array($result)? [$result[$tabIdName]]: []);
	}

	//Insere um valor no meio de um array. Somente para arrays com chaves numéricas.
	function addToArray(array $array, int $position, $value): array{
		$array = array_merge(array_slice($array, 0, $position, true), [$value], array_slice($array, $position, count($array)-$position, true));
		return $array;
	}

	function remFromArray(array $array, int $position): array{
		$array = array_merge(array_slice($array, 0, $position, true), array_slice($array, $position+1, count($array)-$position, true));
		return $array;
	}

	function atualizar(string $tabela, array $campos, array $valores, string $id): void{
		updateById($tabela, $campos, $valores, $id);
	}
	function updateById(string $tabela, array $campos, array $valores, string $id): void{
		if(count($campos) != count($valores)){
			set_status("ERRO: Número de campos não confere com número de linhas na função de atualizar!");
			exit;
		}

		if(count($campos) == 0){
			set_status("ERRO: Campos para atualização não informados.");
			exit;
		}

		$tab = substr($tabela,0,4);
		$camposString = "";
		for($i=0;$i<count($campos);$i++){
			$camposString .= ", ".$campos[$i]." = ";

			if(is_int(strpos($campos[$i], "_nb_")) && !empty($valores[$i])){
				$camposString .= $valores[$i];
			}elseif(empty($valores[$i])){
				$camposString .= "NULL";
			}else{
				$camposString .= "'".$valores[$i]."'";
			}
		}
		if(strlen($camposString) > 2){
			$camposString = substr($camposString, 2);
		}

		try{
			query("UPDATE ".$tabela." SET ".$camposString." WHERE ".$tab."_nb_id = ".$id);
			set_status("Registro atualizado com sucesso!");
		}catch(Exception $e){
			set_status("Falha ao atualizar.");
		}
	}

	function remover(string $tabela, string $id): int{
		return inactivateById($tabela, $id);
	}
	function inactivateById(string $tabela, string $id): int{
		$tab = substr($tabela,0,4);
		query("UPDATE {$tabela} SET {$tab}_tx_status = 'inativo' WHERE {$tab}_nb_id = {$id} LIMIT 1;");
		return $id;
	}

	// function remover_ponto(int $id,$just,$atualizar = null){
	// 	$tab = substr("ponto", 0, 4);

	// 	$campos = [
	// 		$tab."_tx_status" 			=> "inativo",
	// 		$tab."_tx_justificativa" 	=> $just,
	// 		$tab."_tx_dataAtualiza" 	=> $atualizar
	// 	];

	// 	updateById("ponto", array_keys($campos), array_values($campos), $id);
	// }

	function campo_domain($nome,$variavel,$modificador,$tamanho,$mascara="",$extra=""){
		return campo($nome,$variavel,$modificador,$tamanho,"MASCARA_DOMAIN",$extra);
	}

	// function carrega_array($sql, $mode = MYSQLI_BOTH){
	// 	return mysqli_fetch_array($sql, $mode);
	// }

	function ultimo_reg($tabela){
		$tabIdName = substr($tabela,0,4)."_nb_id";

		$sql=query("SELECT {$tabIdName} FROM {$tabela} ORDER BY {$tabIdName} DESC LIMIT 1;");
		return mysqli_fetch_array($sql, MYSQLI_BOTH)[0];
	}

	function carregar($tabela, $id="", $campo="", $valor="", $extra="", $exibe=0){
		$ext = "";
		$extra_id = (!empty($id))? " AND ".substr($tabela,0,4)."_nb_id"." = ".$id: "";

		if(!empty($campo[0])) {
			$a_campo = explode(",", $campo);
			$a_valor = explode(",", $valor);

			for($i = 0; $i < count($a_campo); $i++) {
				$ext .= " AND ".str_replace(",", "", $a_campo[$i])." = '".str_replace(",", "", $a_valor[$i])."' ";
			}
		}

		$query = "SELECT * FROM ".$tabela." WHERE 1 ".$extra_id." ".$ext." ".$extra." LIMIT 1;";

		if($exibe == 1){
			echo $query;
		}
		
		if(empty($extra_id) && empty($ext) && empty($extra)){
			return [];
		}else{
			return mysqli_fetch_array(query($query));
		}
	}

	function valor($valor,$mostrar=0){

		if(floatval(@str_replace(array(","), array("."), $valor)) ){
			$mostrar = 1;//SEMPRE VAI EXIBIR
		}

		if($mostrar == 1 || $valor > 0 ) {
			// nosso formato
			if(substr($valor, -3, 1) == ",")
				return @str_replace(array(".", ","), array("", "."), $valor); // retorna 100000.50
			else
				return @number_format($valor, 2, ",", "."); // retorna 100.000,50
		}else
			return "";
	}

	function map($idPonto){
		$location = mysqli_fetch_all(query(
			"SELECT pont_tx_latitude, pont_tx_longitude FROM ponto"
			." WHERE pont_nb_id = ".$idPonto.";"
		), MYSQLI_ASSOC);

		if(!empty($location[0]['pont_tx_latitude']) && !empty($location[0]['pont_tx_longitude'])){
			$url = "https://www.google.com/maps?q=".$location[0]['pont_tx_latitude'].","
			.$location[0]['pont_tx_longitude'];

			return 
				"<center>"
					."<a href='$url' target='_blank'>"
						."<i class='fa fa-map-marker' aria-hidden='true' style='color: black; font-size: 20px;'></i>"
					."</a>"
				."</center>"
			;
		}

		return "";
	}

	function data($data, $hora = 0): string{

		if(in_array($data, ["0000-00-00", "00/00/0000"]) || empty($data)){
			return "";
		}

		switch($hora){
			case 1:
				$hora="&nbsp;(".substr($data,11).")";
			break;
			case 2:
				return substr($data, 11);
			break;
			case 3:
				return substr($data, 11, -3);
			break;
			default:
				$hora = "";
			break;
		}

		$data = substr($data, 0, 10);
		if(is_int(strpos($data, "/"))){
			$data = implode("-", array_reverse(explode("/", $data)));
		}elseif(is_int(strpos($data, "-"))){
			$data = implode("/", array_reverse(explode("-", $data)));
		}

		return $data.$hora;

	}

	function destacarJornadas(string $texto){
		if(in_array($texto, ["Inicio de Jornada", "Fim de Jornada"])){
			$texto = "<b>".$texto."</b>";
		}
		return $texto;
	}

	function formatPerc(int $value): string{
		// $res = "";
		// if($value < 1){
		// 	$value = ($value*100);
		// }
		$res = $value."%";

		return $res;
	}

	function fieldset($nome=""){
		echo 
			"<div class=portlet-title>
				<span class='caption-subject font-dark bold uppercase'> $nome</span>
			</div>
			<hr style='margin:6px;'>"
		;
	}

	function set_status($msg='') {
		if(empty($msg)){
			global $msg;
		}
		if(is_int(strrpos($msg, 'ERRO'))){
			$msg = substr($msg, 0, strpos($msg, 'ERRO')).'<b style="color: red">'.substr($msg, strpos($msg, 'ERRO')).'</b>';
		}
		$_POST['msg_status'] = $msg;
	}

	function campo($nome,$variavel,$modificador,$tamanho,$mascara='',$extra=''){
		global $CONTEX;

		$classe = "form-control input-sm campo-fit-content";

		if(!empty($_POST["errorFields"]) && in_array($variavel, $_POST["errorFields"])){
			$classe .= " error-field";
		}

		$regexValidChar = "\"[^!-']\"";

		$dataScript = "<script>";

		switch($mascara){
			case "MASCARA_DATA";
				$dataScript .= "$('[name=\"$variavel\"]').inputmask({clearIncomplete: false});";
				$type = "date";
			break;
			case "MASCARA_MES":
				$type = "month";
			break;
			case "MASCARA_PERIODO":
				$datas = [DateTime::createFromFormat("Y-m-d", date("Y-m-01")), DateTime::createFromFormat("Y-m-d", date("Y-m-d"))];
				if(!empty($modificador)){
					if(!is_array($modificador) || count($modificador) != 2){
						if(preg_match_all("/\d{4}-\d{2}-\d{2}/", $_POST["busca_periodo"], $matches)){
							$modificador = [
								$matches[0][0],
								$matches[0][1]
							];
						}else{
							set_status("ERRO: ".$variavel." formatado incorretamente.");
							return;
						}
					}
					$datas = [
						DateTime::createFromFormat("Y-m-d", $modificador[0]),
						DateTime::createFromFormat("Y-m-d", $modificador[1]),
					];
					if(in_array(false, $datas)){
						$datas = [
							DateTime::createFromFormat("Y-m-d", date("Y-m-01")),
							DateTime::createFromFormat("Y-m-d", date("Y-m-d"))
						];
					}
				}

				$dataScript = 
					"<script type='text/javascript' src='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/js/moment.min.js'></script>
					<script type='text/javascript' src='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/js/daterangepicker.min.js'></script>
					<link rel='stylesheet' type='text/css' href='".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/js/daterangepicker.css' />

					<script>
						$(function() {
							$('input[name=\"$variavel\"]').daterangepicker({
								opens: 'left',
								'startDate': '".$datas[0]->format("d/m/Y")."',
								'endDate': '".$datas[1]->format("d/m/Y")."',
								'minYear': 2023,
								'autoApply': true,
								'locale': {
									'format': 'DD/MM/YYYY',
									'separator': ' - ',
									'applyLabel': 'Aplicar',
									'cancelLabel': 'Cancelar',
									'fromLabel': 'De',
									'toLabel': 'Até',
									'customRangeLabel': 'Custom',
									'daysOfWeek': ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
									'monthNames': ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
									'firstDay': 1
								},
								'isInvalidDate': function(date){
									data = new Date(date._i[0], date._i[1], date._i[2]).valueOf();
									return (data > Date.now() || data < new Date(2022, 11, 1).valueOf());
								}
							}, function(start, end, label) {
								$('input[name=\"".$variavel."[]\"]')[0].value = start.format('YYYY-MM-DD');
								$('input[name=\"".$variavel."[]\"]')[1].value = end.format('YYYY-MM-DD');
							});

							$('input[name=\"$variavel\"]').isOverwriteMode = true;

							var date = new Date();
							$('input[name=\"$variavel\"]').inputmask({mask: ['99/99/9999 - 99/99/9999'], placeholder: '01/01/2023 - 01/01/2023'});
							$('input[name=\"$variavel\"]').css('min-width', 'max-content');

							// $('input[name=\"$variavel\"]').on('apply.daterangepicker', function(ev, picker) {
							// 	console.log(picker.startDate.format('YYYY-MM-DD')+' - '+picker.endDate.format('YYYY-MM-DD'));
							// });
						});"
				;

				$campo = 
					"<div class='col-sm-".$tamanho." margin-bottom-5 campo-fit-content'>
						<label>".$nome."</label>
						<input name='".$variavel."' id='".$variavel."' autocomplete='off' type='text' class='".$classe."' ".$extra.">
						<input name='".$variavel."[]' value='".$datas[0]->format("Y-m-d")."' autocomplete='off' type='hidden' class='".$classe."' ".$extra.">
						<input name='".$variavel."[]' value='".$datas[1]->format("Y-m-d")."' autocomplete='off' type='hidden' class='".$classe."' ".$extra.">
					</div>"
				;
			break;
			case "MASCARA_VALOR":
				$dataScript .= "$('[name=\"$variavel\"]').maskMoney({prefix: 'R$', allowNegative: true, thousands:'.', decimal:',', affixesStay: false});";
			break;
			case "MASCARA_CEL":
			case "MASCARA_FONE":
				$dataScript .= "$('[name=\"$variavel\"]').inputmask({mask: ['(99) 9999-9999', '(99) 99999-9999'], placeholder: ''});";
			break;
			case "MASCARA_NUMERO":
				$dataScript .= "$('[name=\"$variavel\"]').inputmask(\"numeric\", {rightAlign: false});";
			break;
			case "MASCARA_CEP":
				$dataScript .= "$('[name=\"$variavel\"]').inputmask({mask: '99999-999', placeholder: \"00000-000\" });";
			break;
			case "MASCARA_CPF":
				$dataScript .= "$('[name=\"$variavel\"]').inputmask({mask: ['999.999.999-99']});";
			break;
			case "MASCARA_CNPJ":
				$dataScript .= "$('[name=\"$variavel\"]').inputmask({mask: ['99.999.999/9999-99'], placeholder: \"00.000.000/000-00\" });";
			break;
			case "MASCARA_CPF/CNPJ":
				$dataScript .= "$('[name=\"$variavel\"]').inputmask({mask: ['999.999.999-99', '99.999.999/9999-99']});";
			break;
			case "MASCARA_RG":
				$dataScript .= "$('[name=\"$variavel\"]').inputmask({mask: ['999.999.999'], numericInput: true, rightAlign: false});";
			break;
			case "MASCARA_DINHERO":
				$dataScript .= 
					"$(function(){
						$('[name=\"$variavel\"]').maskMoney({
						allowNegative: true,
						thousands: '.',
						decimal: ','
						});
					});"
				;
			break;
			case "MASCARA_HORAS":
				$dataScript .= "$('[name=\"$variavel\"]').inputmask({mask: ['99:99', '-99:99', '999:99', '-999:99'], placeholder: \"\"});";
			break;
			case "MASCARA_HORA":
				$type = "time";
			break;
			case "MASCARA_DOMAIN":
				$dataScript .= "$(document).ready(function() {
						var inputField = $('#nomeDominio');
						var domainPrefix = '".$_SERVER['HTTP_ORIGIN'].(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))? '/dev_techps/': '/techps/')."';

						function updateDisplayedText() {
							var inputValue = inputField.val();

							if(inputValue.startsWith(domainPrefix)) {
								var displayedText = inputValue.substring(domainPrefix.length);
								inputField.val(displayedText);
							}
						}

						// Executar a função de atualização quando o campo for modificado
						inputField.on('input', updateDisplayedText);

						// Inicializar o campo com o valor correto
						updateDisplayedText();
					});";
			break;
			case "MASCARA_HIDDEN":
				$type = "hidden";
				$campo = '<input name="'.$variavel.'" id="'.$variavel.'" value="'.$modificador.'" autocomplete="off" type="'.$type.'" class="'.$classe.'" '.$extra.'>';
			break;
			case "MASCARA_SENHA":
				$type = "password";
				$regexValidChar = "\"*\"";
			break;
			case "MASCARA_PLACA":
				$dataScript .= "$('[name=\"$variavel\"]').inputmask({mask: ['AAA-9A99', 'AAA-9999']});";
			break;
		}
		$variavel = str_replace(["[", "]"], ["\\\[", "\\\]"], $variavel);
		$dataScript .= 
				"field = document.querySelector('#".$variavel."');
				if(typeof field.addEventListener !== 'undefined'){
					field.addEventListener('keypress', function(e){
						if(!validChar(e, ".$regexValidChar.")){
							e.preventDefault();
						}
					});
					field.addEventListener('paste', function(e){
						e.srcElement.value = e.clipboardData.getData('Text').replaceAll(/[!-\']/g, '');
						e.preventDefault();
					});
				}
			</script>"
		;

		if(empty($type)){
			$type = "text";
		}

		if(empty($campo)){
			$campo = 
				"<div class='col-sm-".$tamanho." margin-bottom-5 campo-fit-content'>
					<label>".$nome."</label>
					<input name='".$variavel."' id='".$variavel."' value='".$modificador."' autocomplete='off' type='".$type."' class='".$classe."' ".$extra.">
				</div>"
			;
		}

		return $campo.$dataScript;

	}
	function campo_data($nome,$variavel,$modificador,$tamanho,$extra=''){
		return campo($nome, $variavel, $modificador, $tamanho, 'MASCARA_DATA', $extra.' max="9999-12-31"');
	}
	function campo_hora($nome,$variavel,$modificador,$tamanho,$extra='',$intervalo=''){
		return campo($nome,$variavel,$modificador,$tamanho, 'MASCARA_HORA', $extra.' step="'.$intervalo.'"');
	}
	function campo_mes($nome,$variavel,$modificador,$tamanho,$extra=''){
		return campo($nome, $variavel, $modificador, $tamanho, 'MASCARA_MES', $extra);
	}
	function campo_hidden($nome,$valor): string{
		return campo($nome, $nome, $valor, 0, "MASCARA_HIDDEN");
	}
	function campo_senha($nome,$variavel,$modificador,$tamanho,$extra=''){
		return campo($nome, $variavel, $modificador, $tamanho, "MASCARA_SENHA", $extra);
	}

	function checkbox_banco($nome, $variavel, $modificadoRadio, $modificadoCampo=0, $modificadoCampo2=0, $tamanho=3){
		$classeGeral = "col-sm-".$tamanho." margin-bottom-5 campo-fit-content";

		$_POST["errorFields"][] = "banco";

		$errorClasses = [
			"banco" => "",
			"quandDias" => "",
			"quandHoras" => "",
		];
		foreach(array_keys($errorClasses) as $key){
			if(!empty($_POST["errorFields"]) && in_array($key, $_POST["errorFields"])){
				$errorClasses[$key] = "error-field";
			}
		}
		$campo = 
			"<div class='".$classeGeral." ".$errorClasses["banco"]."' style='min-width:fit-content; min-height: 50px;'>
				<label>".$nome."</label><br>
				<label class='radio-inline'>
					<input type='radio' id='sim' name='banco' value='sim'> Sim
				</label>
				<label class='radio-inline'>
					<input type='radio' id='nao' name='banco' value='nao'> Não
				</label>
			</div>
			<div id='".$variavel."' class='".$classeGeral."' style='display: none;'>
					<label>Quantidade de Dias*:</label>
					<input class='form-control input-sm campo-fit-content ".$errorClasses["quandDias"]."' type='number' value='".$modificadoCampo."' id='outroCampo' name='quandDias' autocomplete='off'>
			</div>
			<div id='limiteHoras' class='".$classeGeral."' style='display: none;'>
				<label>Quantidade de Horas Limite*:</label>
				<input class='form-control input-sm campo-fit-content ".$errorClasses["quandHoras"]."' type='number' value='".$modificadoCampo2."' id='outroCampo' name='quandHoras' autocomplete='off'>
			</div>"
		;

		$data_input = 
			"<script>
				const radioSim = document.getElementById('sim');
				const radioNao = document.getElementById('nao');
				const campo = document.getElementById('{$variavel}');
				const campo2 = document.getElementById('limiteHoras');
				if('{$modificadoRadio}' === 'sim'){
					radioSim.checked = true;
				}else{
					radioNao.checked = true;
				}
				if(radioSim.checked) {
						campo.style.display = ''; // Exibe o campo quando 'Mostrar Campo' é selecionado
						campo2.style.display = ''; 
				}
				// Adicionando um ouvinte de eventos aos elementos de rádio
				radioSim.addEventListener('change', function() {
					if(radioSim.checked) {
						campo.style.display = ''; // Exibe o campo quando 'Mostrar Campo' é selecionado
						campo2.style.display = '';
					}
				});
				radioNao.addEventListener('change', function() {
				if(radioNao.checked) {
					campo.style.display = 'none'; // Oculta o campo quando 'Não Mostrar Campo' é selecionado
					campo2.style.display = 'none'; 
				}
				});
			</script>"
		;
		//  Utiliza regime de banco de horas?

		return $campo.$data_input;
	}

	function checkbox(string $titulo, string $variavel, array $opcoes, int $tamanho=3, string $tipo = "checkbox", string $extra='', string $modificadoCampo = ''){
		$campo = 
			"<div {$extra} class='col-sm-{$tamanho} margin-bottom-5 campo-fit-content' style='min-height: 50px;' id='{$variavel}'>
			<div class='margin-bottom-5'>
				{$titulo}
			</div>"
		;
		
		
		$valoresMarcados = explode(',', $modificadoCampo);

		foreach($opcoes as $key => $value){
			$name = $variavel."_".$key;
			if(empty($key)){
				$name = $variavel;
			}
			
			$campo .=
				"<label>
					<input 
						type='{$tipo}' 
						id='{$key}' 
						name='{$name}' 
						value='true' ".((in_array($key,$valoresMarcados) && !empty($valoresMarcados))? 'checked': '')."
					>
					{$value}
				</label>"
			;
		}
		$campo .= "</div>";

		return $campo;
	}

	

	function datepick($nome,$variavel,$modificador,$tamanho,$extra=''){
		global $CONTEX;

		$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5 campo-fit-content">
				<label>'.$nome.'</label>
				<input name="'.$variavel.'" id="'.$variavel.'" value="'.$modificador.'" size="16" readonly style="background-color:white;" autocomplete="off" type="text" class="form-control input-sm campo-fit-content" '.$extra.'>
			</div>

			<script src="'.$CONTEX['path'].'/../contex20/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js" type="text/javascript"></script>
			<script src="'.$CONTEX['path'].'/../contex20/assets/global/plugins/bootstrap-datepicker/locales/bootstrap-datepicker.pt-BR.min.js" type="text/javascript"></script>
			<script>
				if(jQuery().datepicker) {
				$("#'.$variavel.'").datepicker({
					orientation: "left",
					autoclose: true,
					format: "dd/mm/yyyy",
					language: "pt-BR"
				});
				
			}
			</script>
			';



		return $campo;

	}

	function textarea($nome,$variavel,$modificador,$tamanho,$extra=''){

		$campo =
			'<div class="col-sm-'.$tamanho.' margin-bottom-5 campo-fit-content">
				<label>'.$nome.'</label>
				<textarea name="'.$variavel.'" id="'.$variavel.'" autocomplete="off" type="password" class="form-control input-sm campo-fit-content" '.$extra.'>'.$modificador.'</textarea>
			</div>'
		;

		return $campo;
	}

	function ckeditor($nome,$variavel,$modificador,$tamanho,$extra=''){//Obsoleto
		$campo=
			'<script src="/ckeditor/ckeditor.js"></script>
			<div class="col-sm-'.$tamanho.' margin-bottom-5 campo-fit-content">
				<label>'.$nome.'</label>
				<textarea id="'.$variavel.'" name="'.$variavel.'" class="form-control input-sm campo-fit-content" '.$extra.'>'.$modificador.'</textarea>
			</div>
			<script>
				CKEDITOR.replace( "'.$variavel.'" );
			</script>'
		;

		return $campo;

	}

	function texto($nome,$modificador,$tamanho='',$extra=''){//Campo de texto que não pode ser editado
		$campo =
			'<div class="col-sm-'.$tamanho.' margin-bottom-5 campo-fit-content" '.$extra.'>
				<label>'.$nome.'</label><br>
				<p class="text-left">'.$modificador.'</p>
			</div>';

		return $campo;
	}

	function combo($nome, $variavel, $modificador, $tamanho, array $opcoes, $extra = ""){
		$classe = "form-control input-sm campo-fit-content";

		if(!empty($_POST["errorFields"]) && in_array($variavel, $_POST["errorFields"])){
			$classe .= " error-field";
		}

		$res = "";
		foreach($opcoes as $key => $value){
			//Correção da chave para os casos em que a variável $campos é um array comum, e não um dicionário. Retirar quando for necessário utilizar um dicionário com chaves numerais
			$key = is_int($key)? $value: $key;

			$selected = ($key != $modificador)? '': 'selected';
			$res .= '<option value="'.$key.'" '.$selected.'>'.$value.'</option>';
		}

		$campo=
			'<div class="col-sm-'.$tamanho.' margin-bottom-5 campo-fit-content">
				<label>'.$nome.'</label>
				<select name="'.$variavel.'" class="'.$classe.'" '.$extra.'>
					'.$res.'
				</select>
			</div>';

		return $campo;

	}

	// function combo_2($nome, $variavel, $modificador, $tamanho, array $opcoes, $extra = ''){
		// $res = '';
		// foreach($opcoes as $key => $value){
		// 	//Correção da chave para os casos em que a variável $campos é um array comum, e não um dicionário. Retirar quando for necessário utilizar um dicionário com chaves numerais
		// 	$key = is_int($key)? $value: $key;

		// 	$selected = ($key != $modificador)? '': 'selected';
		// 	$res .= '<option value="'.$key.'" '.$selected.'>'.$value.'</option>';
		// }

		// $campo=
		// 	'<div class="margin-bottom-5" style="width:'.$tamanho.'px">
		// 		<label>'.$nome.'</label>
		// 		<select name="'.$variavel.'" class="form-control input-sm campo-fit-content" '.$extra.'>
		// 			'.$res.'
		// 		</select>
		// 	</div>';

		// return $campo;
	// }

	function combo_net($nome,$variavel,$modificador,$tamanho,$tabela,$extra='',$extra_bd='',$extra_busca='',$extra_ordem='',$extra_limite='15'){
		global $CONTEX, $conn;

		if(!empty($modificador)){
			$tab = substr($tabela,0,4);
			if($extra_busca != ''){
				$extra_campo = ",$extra_busca";
			}else{
				$extra_campo = '';
			}

			$queryResult = mysqli_fetch_array(
				query(
					"SELECT {$tab}_tx_nome {$extra_campo} FROM {$tabela} 
						WHERE {$tab}_tx_status = 'ativo'
							AND {$tab}_nb_id = {$modificador};"
				)
			);
			
			if($extra_busca != ''){
				$queryResult[0] = "[{$queryResult[1]}] {$queryResult[0]}";
			}
			if(!empty($queryResult)){
				$opt = "<option value='{$modificador}'>{$queryResult[0]}</option>";
			}else{
				$opt = "";
			}
		}else{
			$opt = "";
		}
		
		$classe = 'col-sm-'.$tamanho.' margin-bottom-5 campo-fit-content';
		if(!empty($_POST["errorFields"]) && in_array($variavel, $_POST["errorFields"])){
			$classe .= " select-error-field";
		}
		$campo =
			'<div class="'.$classe.'">
				<label>'.$nome.'</label>
				<select class="'.$variavel.' form-control input-sm campo-fit-content" id="'.$variavel.'" style="width:100%" '.$extra.' name="'.$variavel.'">
				'.$opt.'
				</select>
			</div>'
		;

		
		$select2URL = 
			$_ENV['URL_BASE'].$_ENV['APP_PATH']."/contex20/select2.php"
			."?path=".$CONTEX['path']
			."&tabela=".$tabela
			."&extra_ordem=".$extra_ordem
			."&extra_limite=".$extra_limite
			."&extra_bd=".urlencode($extra_bd)
			."&extra_busca=".urlencode($extra_busca);

		$ajax = "{}";
		if(is_bool(strpos($extra, "startEmpty"))){
			$ajax = "{
				url: '".$select2URL."',
				dataType: 'json',
				delay: 250,
				processResults: function(data) {
					return {
						results: data
					};
				},
				cache: true
			}";
		}

		echo "
			<script src='{$CONTEX["path"]}/../contex20/assets/global/plugins/jquery.min.js' type='text/javascript'></script>
			<script src='{$CONTEX["path"]}/../contex20/assets/global/plugins/select2/js/select2.min.js'></script>
			<script src='{$CONTEX["path"]}/../contex20/assets/global/plugins/jquery-inputmask/jquery.inputmask.bundle.min.js' type='text/javascript'></script>
			<script src='{$CONTEX["path"]}/../contex20/assets/global/plugins/jquery-inputmask/maskMoney.js' type='text/javascript'></script>
			<script type=\"text/javascript\" language=\"javascript\">
				$.fn.select2.defaults.set(\"theme\", \"bootstrap\");
				$(window).bind(\"load\", function() {
					var res = $('.{$variavel}').select2({
						language: 'pt-BR',
						placeholder: 'Selecione um item',
						allowClear: true,
						ajax: {$ajax}
					});
				});
			</script>
		";

		return $campo;
	}

	function combo_bd($nome,$variavel,$modificador,$tamanho,$tabela,$extra='',$extra_bd=''){

		$tab=substr($tabela,0,4);
		$htmlOpcoes = '';
		if($nome[0] == "!"){
			$nome=substr($nome, 1);
			$htmlOpcoes.="<option value=''></option>";
		}
		
		// if(stripos($extra_bd,"order by") === false){
		// 	$extra_bd=" ORDER BY ".$tab."_tx_nome ASC";
		// }

		if($extra_bd == ''){
			$extra_bd = " ORDER BY ".$tab."_tx_nome ASC";
		}

		
		$sql=query("SELECT ".$tab."_nb_id, ".$tab."_tx_nome FROM $tabela WHERE ".$tab."_tx_status = 'ativo' $extra_bd");
		while($a=mysqli_fetch_array($sql)){

			if($a[0] == $modificador || $a[1] == $modificador){
				$selected="selected";
			}else{
				$selected='';
			}
			$htmlOpcoes .= '<option value="'.$a[0].'" '.$selected.'>'.$a[1].'</option>';
		}

		$classe = "form-control input-sm campo-fit-content";
		if(!empty($_POST["errorFields"]) && in_array($variavel, $_POST["errorFields"])){
			$classe .= " error-field";
		}

		$campo=
			'<div class="col-sm-'.$tamanho.' margin-bottom-5 campo-fit-content">
				<label>'.$nome.'</label>
				<select name="'.$variavel.'" id="'.$variavel.'" class="'.$classe.'" '.$extra.'>
					'.$htmlOpcoes.'
				</select>
			</div>'
		;

		return $campo;
	}

	function arquivosParametro($nome,$idParametro,$arquivos){

		$arquivo_list = '';
		if(!empty($arquivos)) {
			foreach($arquivos as $arquivo){
				$dataHoraOriginal = $arquivo['docu_tx_dataCadastro'];
				$dataHora = new DateTime($dataHoraOriginal);
				$dataHoraFormatada = $dataHora->format('d/m/Y H:i:s');
				$arquivo_list .= "
				<tr role='row' class='odd'>
				<td>$arquivo[docu_tx_nome]</td>
				<td>$arquivo[docu_tx_descricao]</td>
				<td>$dataHoraFormatada</td>
				<td>
					<a style='color: steelblue;' onclick=\"javascript:downloadArquivo($idParametro,'$arquivo[docu_tx_caminho]','downloadArquivo');\"><i class='glyphicon glyphicon-cloud-download'></i></a>
				</td>
				<td>
					<a style='color: red;' onclick=\"javascript:remover_arquivo($idParametro,$arquivo[docu_nb_id],'$arquivo[docu_tx_nome]','excluir_documento');\"><i class='glyphicon glyphicon-trash'></i></a>
				</td>";
			}
		}


		$tabela='
			<div class="portlet light ">
				<div class="portlet-title">
				<div class="caption">
					<span class="caption-subject font-dark bold uppercase">'.$nome.'</span>
				</div>
				</div>
				<div class="portlet-body">
					<table id="contex-grid" class="table compact table-striped table-bordered table-hover dt-responsive"
						width="100%" id="sample_2">
						<thead>
							<tr role="row">
								<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
									aria-label="NOME: activate to sort column ascending" style="width: 40px;">NOME</th>
								<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
									aria-label="DESCRIÇÃO: activate to sort column ascending" style="width: 40px;">
									DESCRIÇÃO</th>
								<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
									aria-label="DATA CADASTRO: activate to sort column ascending" style="width: 40px;">
									DATA CADASTRO</th>
								<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
									aria-label="DOWNLOAD: activate to sort column ascending" style="width: 40px;"><i
										class="glyphicon glyphicon-cloud-download"></i></th>
								<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
									aria-label="DOWNLOAD: activate to sort column ascending" style="width: 40px;"><i
										class="glyphicon glyphicon-trash"></i></th>
							</tr>
						</thead>
						<thbody>
						'.$arquivo_list.'
						<tr role="row" class="even">
						<td>
						<a href="#" data-toggle="modal" data-target="#myModal">
						<i class="glyphicon glyphicon-plus-sign"></i>
						</a>
						</td>
						</thbody>
						</table>
		';

		$modal = "
		<div class='modal fade' id='myModal' tabindex='-1' role='dialog' aria-labelledby='myModalLabel'>
			<div class='modal-dialog' role='document'>
				<div class='modal-content'>
					<div class='modal-header'>
					<button type='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
					<h4 class='modal-title' id='myModalLabel'>Upload Arquivo</h4>
					</div>
					<div class='modal-body'>
					<form name='form_enviar_arquivo' method='post' action='cadastro_parametro.php' enctype='multipart/form-data'>
						<div class='form-group'>
							<label for='file-name' class='control-label'>Nome do arquivo:</label>
							<input type='text' class='form-control' name='file-name'>
						</div>
						<div class='form-group'>
							<label for='description-text' class='control-label'>Descrição:</label>
							<textarea class='form-control' name='description-text'></textarea>
						</div>
						<div class='form-group'>
							<label for='file' class='control-label'>Arquivo:</label>
							<input type='file' class='form-control' name='file'>
						</div>
						
						<input type='hidden' name='acao' value='enviarDocumento'>
						
						<input type='hidden' name='idParametro' value='$idParametro'>
					</form>
					</div>
					<div class='modal-footer'>
						<button type='button' class='btn btn-default' data-dismiss='modal'>Cancelar</button>
						<button type='button' class='btn btn-primary' data-dismiss='modal' 
						onclick=\"javascript:enviar_arquivo();\">Salvar arquivo</button>
					</div>
				</div>
			</div>
		</div>
		
		<script type='text/javascript'>
		function enviar_arquivo() {
			document.form_enviar_arquivo.submit();
		}
		
		</script>
		";

			return $tabela.$modal;

	}

	function arquivosEmpresa($nome,$idEmpresa,$arquivos){

		$arquivo_list = '';
		if(!empty($arquivos)) {
			foreach($arquivos as $arquivo){
				$dataHoraOriginal = $arquivo['docu_tx_dataCadastro'];
				$dataHora = new DateTime($dataHoraOriginal);
				$dataHoraFormatada = $dataHora->format('d/m/Y H:i:s');
				$arquivo_list .= "
				<tr role='row' class='odd'>
				<td>$arquivo[docu_tx_nome]</td>
				<td>$arquivo[docu_tx_descricao]</td>
				<td>$dataHoraFormatada</td>
				<td>
					<a style='color: steelblue;' onclick=\"javascript:downloadArquivo($idEmpresa,'$arquivo[docu_tx_caminho]','downloadArquivo');\"><i class='glyphicon glyphicon-cloud-download'></i></a>
				</td>
				<td>
					<a style='color: red;' onclick=\"javascript:remover_arquivo($idEmpresa,$arquivo[docu_nb_id],'$arquivo[docu_tx_nome]','excluir_documento');\"><i class='glyphicon glyphicon-trash'></i></a>
				</td>";
			}
		}


		$tabela='
			<div class="portlet light ">
				<div class="portlet-title">
				<div class="caption">
					<span class="caption-subject font-dark bold uppercase">'.$nome.'</span>
				</div>
				</div>
				<div class="portlet-body">
					<table id="contex-grid" class="table compact table-striped table-bordered table-hover dt-responsive"
						width="100%" id="sample_2">
						<thead>
							<tr role="row">
								<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
									aria-label="NOME: activate to sort column ascending" style="width: 40px;">NOME</th>
								<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
									aria-label="DESCRIÇÃO: activate to sort column ascending" style="width: 40px;">
									DESCRIÇÃO</th>
								<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
									aria-label="DATA CADASTRO: activate to sort column ascending" style="width: 40px;">
									DATA CADASTRO</th>
								<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
									aria-label="DOWNLOAD: activate to sort column ascending" style="width: 40px;"><i
										class="glyphicon glyphicon-cloud-download"></i></th>
								<th class="sorting" tabindex="0" aria-controls="contex-grid" rowspan="1" colspan="1"
									aria-label="DOWNLOAD: activate to sort column ascending" style="width: 40px;"><i
										class="glyphicon glyphicon-trash"></i></th>
							</tr>
						</thead>
						<thbody>
						'.$arquivo_list.'
						<tr role="row" class="even">
						<td>
						<a href="#" data-toggle="modal" data-target="#myModal">
						<i class="glyphicon glyphicon-plus-sign"></i>
						</a>
						</td>
						</thbody>
						</table>
		';

		$modal = "
		<div class='modal fade' id='myModal' tabindex='-1' role='dialog' aria-labelledby='myModalLabel'>
			<div class='modal-dialog' role='document'>
				<div class='modal-content'>
					<div class='modal-header'>
					<button type='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
					<h4 class='modal-title' id='myModalLabel'>Upload Arquivo</h4>
					</div>
					<div class='modal-body'>
					<form name='form_enviar_arquivo2' method='post' action='cadastro_empresa.php' enctype='multipart/form-data'>
						<div class='form-group'>
							<label for='file-name' class='control-label'>Nome do arquivo:</label>
							<input type='text' class='form-control' name='file-name'>
						</div>
						<div class='form-group'>
							<label for='description-text' class='control-label'>Descrição:</label>
							<textarea class='form-control' name='description-text'></textarea>
						</div>
						<div class='form-group'>
							<label for='file' class='control-label'>Arquivo:</label>
							<input type='file' class='form-control' name='file'>
						</div>
						
						<input type='hidden' name='acao' value='enviarDocumento'>
						
						<input type='hidden' name='idEmpresa' value='$idEmpresa'>
					</form>
					</div>
					<div class='modal-footer'>
						<button type='button' class='btn btn-default' data-dismiss='modal'>Cancelar</button>
						<button type='button' class='btn btn-primary' data-dismiss='modal' 
						onclick=\"javascript:enviar_arquivo();\">Salvar arquivo</button>
					</div>
				</div>
			</div>
		</div>
		
		<script type='text/javascript'>
		function enviar_arquivo() {
			document.form_enviar_arquivo2.submit();
		}
		
		</script>
		";

			return $tabela.$modal;

	}

	function arquivo($nome,$variavel,$modificador = '',$tamanho=4, $extra=''){
		global $CONTEX;
		$ver = '';
		if(!empty($modificador)){
			$ver = "<a href=$CONTEX[path]/$modificador target=_blank>(Ver)</a>";
		}

		$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5 campo-fit-content">
					<label>'.$nome.$ver.'</label>
					<input name="'.$variavel.'" value="'.$CONTEX['path']."/".$modificador.'" autocomplete="off" type="file" class="form-control input-sm campo-fit-content" '.$extra.'>
				</div>';

		return $campo;
	}

	function enviar($arquivo,$diretorio,$nome='') {

		$target_path = "$diretorio";
		
		$extensao = pathinfo($target_path.basename($_FILES[$arquivo]['name'], PATHINFO_EXTENSION));
		// if('.php', '.php3', '.php4', '.phtml', '.pl', '.py', '.jsp', '.asp', '.htm', '.shtml', '.sh', '.cgi')

		if($nome!='') {
			$target_path .= "$nome.$extensao[extension]";
		}else {
			$target_path .= basename($_FILES[$arquivo]['name']);
		}
		
		if(move_uploaded_file($_FILES[$arquivo]['tmp_name'], $target_path)) {
			set_status("O arquivo ".  basename( $_FILES[$arquivo]['name']). " foi enviado");
			return $target_path;
		} else{
			echo("Ocorreu um erro ao tentar enviar o arquivo!");
			exit;

		}

	}

	function botao($nome, $acao, $campos='', $valores='', $extra='', bool $salvar = false, $botaoCor='btn btn-secondary'): string{
		global $idsBotaoContex;
		$hidden = '';
		$funcaoOnClick = '';
		if(!empty($campos[0])){
			$a_campos=explode(',',$campos);
			$a_valores=explode(',',$valores);
			for($i=0; $i<count($a_campos); $i++){
				$hidden .= "
					var input{$i} = document.createElement('input');
					input{$i}.type = 'hidden';
					input{$i}.name = '".$a_campos[$i]."';
					input{$i}.value = '".$a_valores[$i]."';
					document.forms[0].appendChild(input{$i});
					"
				;
			}
		}

		if($salvar){
			echo 
				"<script type='text/javascript'>
				function criarGET() {
					var form = document.forms[0];
					var elements = form.elements;
					var values = [];
					var primeiraAcao = '';

					for(var i = 0; i < elements.length; i++){
						if(elements[i].name == 'acao' && elements[i].value  != 'index'){
							continue;
						}
						values.push(encodeURIComponent(elements[i].name) + '=' + encodeURIComponent(elements[i].value));
					}
					form.action = '?' + values.join('&');
				}
				</script>"
			;
			$funcaoOnClick = 'criarGET();';
		}

		$nomeFuncao='b'.md5($nome.$campos.$valores);
		if($hidden!=''){
			$funcaoJs="
				<script>
				function $nomeFuncao(){
					$hidden
				}
				</script>
			";
			$funcaoOnClick .= $nomeFuncao.'();';
		}else{
			$funcaoJs = '';
		}

		
		if(!empty($funcaoOnClick)){
			$funcaoOnClick = 'onclick="'.$funcaoOnClick.'"';
		}else{
			$funcaoOnClick = '';
		}

		return $funcaoJs.'<button '.$funcaoOnClick.' name="acao" id="botaoContex'.$nome.'" value="'.$acao.'"  type="submit" '.$extra.'  class="'.$botaoCor.'">'.$nome.'</button>';
	}

	function query($query,$debug=''){
		global $conn;
		$sql = mysqli_query($conn,$query) or die(mysqli_error($conn));
		if($debug=='1'){
			echo $query;
		}
		return $sql;
	}

	//Essa função retornará um ícone que será utilizado no SQL da tabela para retornar uma coluna com o ícone
	function criarSQLIconeTabela($id, $acao, $title, $icone, $msgConfirm="\'\'", $onClick = ""): string{
		if(empty($onClick)){
			if($msgConfirm != "\'\'"){
				$msgConfirm = "\'{$msgConfirm}\'";
			}
			$onClick = "contex_icone(',{$id},',\'{$acao}\',{$msgConfirm})";
		}
		//Se $msgConfirm vazio, não precisa de confirmação para executar o comando do ícone.

		return "CONCAT('<a title=\"{$title}\" style=\"color:gray; display:block;\" onclick=\"{$onClick}\"><spam class=\"{$icone}\"></spam></a>')";
	}

	function icone_download($aquivo=''){
		global $CONTEX;

		$style = '<style>
			/* Estilos para tornar o ícone clicável */
			.glyphicon-clickable {
				cursor: pointer;
				transition: color 0.3s ease-in-out;
				font-size: 16px;
			}

			.glyphicon-clickable:hover {
				color: blue; /* Altere a cor conforme necessário */
			}
		</style>';

		$script = "<script>
			function download(arquivo) {
				// Caminho do arquivo CSV no servidor
				var filePath = './arquivos/pontos/' + arquivo // Substitua pelo caminho do seu arquivo

				// Cria um link para download
				var link = document.createElement('a');

				// Configurações do link
				link.setAttribute('href', filePath);
				link.setAttribute('download', arquivo);

				// Adiciona o link ao documento
				document.body.appendChild(link);

				// Simula um clique no link para iniciar o download
				link.click();

				// Remove o link
				document.body.removeChild(link);
			}
		</script>";

		return $style."<spam class='glyphicon glyphicon-download glyphicon-clickable' onclick=\"download('$aquivo')\"></spam>".$script;
	}