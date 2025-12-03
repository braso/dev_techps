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
			echo "ERRO: Função '{$nomeFuncao}' não existe!";
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
					if(document.getElementsByClassName('loading')[0] != undefined){
						if(document.getElementsByClassName('loading')[0].style.display != 'none'){
							document.getElementsByClassName('loading')[0].style.display = 'none';
						}
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
		
		if(substr($filename, -4) != ".csv"){
			$filename .= ".csv";
		}

		$endosso = fopen($_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/arquivos/endosso/".$filename, "r");

		if($endosso == false){
			set_status("ERRO: Endosso não encontrado. ({$filename})");
			$_POST["returnValues"] = json_encode([
				"HTTP_REFERER" => $_SERVER["HTTP_REFERER"],
				"msg_status" => $_POST["msg_status"]
			]);
			voltar();
			exit;
		}

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
			["diffRefeicao", "diffEspera", "diffDescanso", "diffRepouso", "diffJornada", "jornadaPrevista", "diffJornadaEfetiva", "maximoDirecaoContinua", "intersticio", "he50", "he100", "adicionalNoturno", "esperaIndenizada", "diffSaldo", "saldoAnterior", "saldoBruto", "he50APagar", "he100APagar"],
			["diffRefeicao", "diffEspera", "diffDescanso", "diffRepouso", "diffJornada", "jornadaPrevista", "diffJornadaEfetiva", "maximoDirecaoContinua", "intersticio", "he50", "he100", "adicionalNoturno", "esperaIndenizada", "diffSaldo", "saldoAnterior", "saldoBruto", "saldoFinal", "he50APagar", "he100APagar"],
			["diffRefeicao", "diffEspera", "diffDescanso", "diffRepouso", "diffJornada", "jornadaPrevista", "diffJornadaEfetiva", "maximoDirecaoContinua", "intersticio", "he50", "he100", "adicionalNoturno", "esperaIndenizada", "diffSaldo", "desconto_manual", "desconto_faltas_nao_justificadas", "saldoAnterior", "saldoBruto", "saldoFinal", "he50APagar", "he100APagar"],
		];

		switch(array_keys($endosso["totalResumo"])){
			case $versoesEndosso[0]:
			case $versoesEndosso[1]:
				$endosso["totalResumo"]["desconto_manual"] = "00:00";
				$endosso["totalResumo"]["desconto_faltas_nao_justificadas"] = "00:00";

				$endosso["totalResumo"]["saldoBruto"] = $endosso["totalResumo"]["saldoAtual"];
				unset($endosso["totalResumo"]["saldoAtual"]);
				[$endosso["totalResumo"]["he50APagar"], $endosso["totalResumo"]["he100APagar"]] = calcularHorasAPagar(strip_tags($endosso["totalResumo"]["diffSaldo"]), $endosso["totalResumo"]["saldoBruto"], $endosso["totalResumo"]["he50"], $endosso["totalResumo"]["he100"], (!empty($endosso["endo_tx_max50APagar"])? $endosso["endo_tx_max50APagar"]: "00:00"), ($endosso["totalResumo"]["para_tx_pagarHEExComPerNeg"]?? "nao"));
				$endosso["totalResumo"]["saldoFinal"] = operarHorarios([$endosso["totalResumo"]["saldoBruto"], $endosso["totalResumo"]["he50APagar"], $endosso["totalResumo"]["he100APagar"]], "-");
			break;
			case $versoesEndosso[2]:
				$endosso["totalResumo"]["saldoFinal"] = operarHorarios([$endosso["totalResumo"]["saldoBruto"], $endosso["totalResumo"]["he50APagar"], $endosso["totalResumo"]["he100APagar"]], "-");
			case $versoesEndosso[3]:
				$endosso["totalResumo"]["desconto_manual"] = "00:00";
				$endosso["totalResumo"]["desconto_faltas_nao_justificadas"] = "00:00";
			break;
			case $versoesEndosso[4]:
					//Versão atual
			break;
			default:
				// echo implode(", ", array_keys($endosso["totalResumo"]));
			break;
		}

		if(is_array($endosso["endo_tx_pontos"][0])){
			$keys = array_merge([
				"",
				"data",
				"diaSemana",
				"inicioJornada",
				"inicioRefeicao",
				"fimRefeicao",
				"fimJornada"
			], array_keys($endosso["totalResumo"]));

			foreach($endosso["endo_tx_pontos"] as &$row){
				if(is_object($row)){
					$row = (array)$row;
				}else{
					$newRow = [];
					for($f = 0; $f < count($row); $f++){
						if($keys[$f] == ""){
							$keys[$f] = 0;
						}
						$newRow[$keys[$f]] = $row[$f];
					}
					$row = (array)$newRow;
				}
			}
		}else{
			if(is_object($endosso["endo_tx_pontos"][0])){
				foreach($endosso["endo_tx_pontos"] as &$row){
					$row = (array)$row;
				}
			}
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
        
        return [mysqli_insert_id($conn)];
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
        if($tabela === 'usuario_perfil'){ $tab = 'uperf'; }
        if($tabela === 'perfil_acesso'){ $tab = 'perfil'; }
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
        if($tabela === 'usuario_perfil'){ $tab = 'uperf'; }
        if($tabela === 'perfil_acesso'){ $tab = 'perfil'; }
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
		return campo($nome,$variavel,$modificador,$tamanho,"MASCARA_COMPANY",$extra);
	}

	// function carrega_array($sql, $mode = MYSQLI_BOTH){
	// 	return mysqli_fetch_array($sql, $mode);
	// }

    function ultimo_reg($tabela){
        $tabIdName = substr($tabela,0,4)."_nb_id";
        if($tabela === 'usuario_perfil'){ $tabIdName = 'uperf_nb_id'; }
        if($tabela === 'perfil_acesso'){ $tabIdName = 'perfil_nb_id'; }
        $sql=query("SELECT {$tabIdName} FROM {$tabela} ORDER BY {$tabIdName} DESC LIMIT 1;");
        return mysqli_fetch_array($sql, MYSQLI_BOTH)[0];
    }

    function carregar($tabela, string $id="", $campo="", $valor="", $extra="", $exibe=0){
        $extraCondicoes = "";
        $prefixId = substr($tabela,0,4);
        if($tabela === 'usuario_perfil'){ $prefixId = 'uperf'; }
        if($tabela === 'perfil_acesso'){ $prefixId = 'perfil'; }
        $extra_id = (!empty($id))? " AND ".$prefixId."_nb_id = ".$id: "";

		if(!empty($campo[0])) {
			$a_campo = explode(",", $campo);
			$a_valor = explode(",", $valor);

			for($i = 0; $i < count($a_campo); $i++) {
				$extraCondicoes .= " AND ".str_replace(",", "", $a_campo[$i])." = '".str_replace(",", "", $a_valor[$i])."' ";
			}
		}

		$query = "SELECT * FROM ".$tabela." WHERE 1 ".$extra_id." ".$extraCondicoes." ".$extra." LIMIT 1;";

		if($exibe == 1){
			echo $query;
		}
		
		if(empty($extra_id) && empty($extraCondicoes) && empty($extra)){
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

	function userCadastro($idUser = null){
		if(!empty($idUser)){
			$userCadastro = mysqli_fetch_all(query(
				"SELECT user_tx_nome FROM `user`"
				." WHERE user_nb_id = ".$idUser.";"
			), MYSQLI_ASSOC);
			
			return $userCadastro[0]["user_tx_nome"];
		}

		return null;
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

	function normalizar($texto){
		$texto = preg_replace('/[áàãâä]/ui', 'a', $texto);
		$texto = preg_replace('/[éèêë]/ui', 'e', $texto);
		$texto = preg_replace('/[íìîï]/ui', 'i', $texto);
		$texto = preg_replace('/[óòõôö]/ui', 'o', $texto);
		$texto = preg_replace('/[úùûü]/ui', 'u', $texto);
		$texto = preg_replace('/[ç]/ui', 'c', $texto);
		return strtolower(trim(preg_replace('/\s+/', ' ', $texto)));
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
				$dataScript .= "$('[name=\"{$variavel}\"]').inputmask({clearIncomplete: false});";
				$type = "date";
			break;
			case "MASCARA_MES":
				$type = "month";
			break;
			case "MASCARA_PERIODO_SEM_LIMITE":
				$limite = 'data+1';
			case "MASCARA_PERIODO":
				$datas = [DateTime::createFromFormat("Y-m-d", date("Y-m-01")), DateTime::createFromFormat("Y-m-d", date("Y-m-d"))];

				if(empty($limite)){
					$limite = 'Date.now()';
				}

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
									return (data > {$limite} || data < new Date(2022, 11, 1).getTime());
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
				$type = "number";
				$regexValidChar = "";
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
			case "MASCARA_DINHEIRO":
				$dataScript .= 
					"$(function(){
						$('[name=\"$variavel\"]').maskMoney({
						allowNegative: true,
						thousands: '.',
						decimal: ',',
						prefix: 'R$',
						affixesStay: false
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
			// MASCARA COMPANY Tem que ser corrigido domainPrefix no if esta ficnado undefined

			case "MASCARA_COMPANY":
				$dataScript .= '$(document).ready(function() {
					var inputField = $("#nomeEmpresa");
					var domainPrefix = "' . $_SERVER['HTTP_ORIGIN'] . (strpos($_SERVER["REQUEST_URI"], "dev_") !== false ? '/dev_techps/' : '/techps/') . '";

					function updateDisplayedText() {
						var inputValue = inputField.val() || ""; // garante string

						if (inputValue.startsWith(domainPrefix)) {
							var displayedText = inputValue.substring(domainPrefix.length);
							inputField.val(displayedText);
						}
					}

					if (inputField.length) { // garante que o campo existe
						inputField.on("input", updateDisplayedText);
						updateDisplayedText();
					} else {
						console.warn("#nomeEmpresa não encontrado.");
					}
				});';
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
		if(!empty($regexValidChar)){
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
				}"
			;
		}
		$dataScript .= "</script>";

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
			"<div class='{$classeGeral} {$errorClasses["banco"]}' style='min-width:fit-content; min-height: 50px;'>
				<label>{$nome}</label><br>
				<label class='radio-inline'>
					<input type='radio' name='banco' value='sim'> Sim
				</label>
				<label class='radio-inline'>
					<input type='radio' name='banco' value='nao'> Não
				</label>
			</div>
			<div id='{$variavel}' class='{$classeGeral}' style='display: none;'>
					<label>Quantidade de Dias*:</label>
					<input class='form-control input-sm campo-fit-content {$errorClasses["quandDias"]}' type='number' value='{$modificadoCampo}' id='outroCampo' name='quandDias' autocomplete='off'>
			</div>
			<div id='limiteHoras' class='{$classeGeral}' style='display: none;'>
				<label>Quantidade de Horas Limite*:</label>
				<input class='form-control input-sm campo-fit-content {$errorClasses["quandHoras"]}' type='number' value='{$modificadoCampo2}' id='outroCampo' name='quandHoras' autocomplete='off'>
			</div>"
		;

		$data_input = 
			"<script>
				const radioSim = document.getElementsByName('banco')[0];
				const radioNao = document.getElementsByName('banco')[1];
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

		if(substr($modificadoCampo, 0, 1) == "[" && substr($modificadoCampo, strlen($modificadoCampo)-1, 1) == "]"
			|| substr($modificadoCampo, 0, 1) == "{" && substr($modificadoCampo, strlen($modificadoCampo)-1, 1) == "}"){
			$valoresMarcados = json_decode($modificadoCampo);
		}else{
			$valoresMarcados = explode(',', $modificadoCampo);
		}
		
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

	function texto($nome,$modificador,$tamanho='',$extra=''){//Campo de texto que não pode ser editado
		$campo =
			'<div class="col-sm-'.$tamanho.' margin-bottom-5 campo-fit-content text-field" '.$extra.'>
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

		$htmlOpcoes = "";


		foreach($opcoes as $key => $value){
			// Correção da chave para os casos em que a variável $campos é um array comum, e não um dicionário. Retirar quando for necessário utilizar um dicionário com chaves numerais
			// $key = is_int($key)? $value: $key;

			$selected = ($key == $modificador)? "selected": "";
			$htmlOpcoes .= "<option value='{$key}' {$selected}>{$value}</option>";
		}

		if($variavel == "ocupacao"){
		}

		$campo =
			"<div class='col-sm-{$tamanho} margin-bottom-5 campo-fit-content'>
				<label>{$nome}</label>
				<select name='{$variavel}' class='{$classe}' {$extra}>
					{$htmlOpcoes}
				</select>
			</div>";

		return $campo;
	}

	function combo_radio($nome, $variavel, $modificador, $tamanho, array $opcoes, $extra = ""): string{
		$combo = "<div class='col-sm-{$tamanho} margin-bottom-5 campo-fit-content ".(!empty($_POST["errorFields"]) && in_array($variavel, $_POST["errorFields"]))."' style='min-width:fit-content; min-height: 50px;' {$extra}>
			<label>{$nome}</label><br>";
			
		foreach ($opcoes as $value => $text) {
			$combo .= 
				"<label class='radio-inline'>
					<input type='radio' name='{$variavel}' value='{$value}' ".(($modificador === $value)? "checked" :"")."> {$text}
				</label>"
			;
		}
		$combo .= "</div>";

		return $combo;
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

	function combo_net($nome,$variavel,$modificador,$tamanho,$tabela,$selectProps='',$condicoes='',$colunas='',$ordem='',$limite='15'){
		global $CONTEX, $conn;

		$opt = "";

        if(!empty($modificador)){
            $tab = substr($tabela,0,4);
            if($tabela === 'usuario_perfil'){ $tab = 'uperf'; }
            if($tabela === 'perfil_acesso'){ $tab = 'perfil'; }
            if($colunas != ''){
                $extra_campo = ",$colunas";
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
			
			if($colunas != ''){
				$queryResult[0] = "[{$queryResult[1]}] {$queryResult[0]}";
			}
			if(!empty($queryResult)){
				$opt = "<option value='{$modificador}'>{$queryResult[0]}</option>";
			}
		}
		
		$classe = "col-sm-{$tamanho} margin-bottom-5 campo-fit-content";
		if(!empty($_POST["errorFields"]) && in_array($variavel, $_POST["errorFields"])){
			$classe .= " select-error-field";
		}
		$campo =
			'<div class="'.$classe.'">
				<label>'.$nome.'</label>
				<select class="'.$variavel.' form-control input-sm campo-fit-content" id="'.$variavel.'" style="width:100%" '.$selectProps.' name="'.$variavel.'">
				'.$opt.'
				</select>
			</div>'
		;

		
		$select2URL = 
			$_ENV['URL_BASE'].$_ENV['APP_PATH']."/contex20/select2.php"
			."?path=".urlencode($CONTEX['path'])
			."&tabela=".$tabela
			."&colunas=".urlencode($colunas)
			."&condicoes=".urlencode($condicoes)
			."&ordem=".urlencode($ordem)
			."&limite=".urlencode($limite)
		;

		$ajax = "{}";
		if(is_bool(strpos($selectProps, "startEmpty"))){
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

	function combo_bd($nome,$variavel,$modificador,$tamanho,$tabela,$extra="",$condicoes=""){

        $tab=substr($tabela,0,4);
        if($tabela === 'usuario_perfil'){ $tab = 'uperf'; }
        if($tabela === 'perfil_acesso'){ $tab = 'perfil'; }
        $htmlOpcoes = "";
		if($nome[0] == "!"){
			$nome=substr($nome, 1);
			$htmlOpcoes.="<option value=''></option>";
		}
		
		// if(stripos($condicoes,"order by") === false){
		// 	$condicoes=" ORDER BY ".$tab."_tx_nome ASC";
		// }

		if(empty($condicoes)){
			$condicoes = " ORDER BY {$tab}_tx_nome ASC";
		}
		
		
		$sql=query("SELECT {$tab}_nb_id, {$tab}_tx_nome FROM {$tabela} WHERE {$tab}_tx_status = 'ativo' {$condicoes}");

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
			"<div class='col-sm-{$tamanho} margin-bottom-5 campo-fit-content'>
				<label>{$nome}</label>
				<select name='{$variavel}' id='{$variavel}' class='{$classe}' {$extra}>
					{$htmlOpcoes}
				</select>
			</div>"
		;

		return $campo;
	}

	//Desenvolver funcionalidade e aplicar nas telas para substituir o combo_bd()
	function combo_bd2(
		string $nome,
		string $variavel,
		string $modificador,
		string $sql,
		string $divClasse = "col-sm-2 margin-bottom-5 campo-fit-content",
		string $selectClasse = "form-control input-sm campo-fit-content",
		string $selectProps = "",
		array $opcoes = []
	): string{

		$rows = mysqli_fetch_all(query($sql), MYSQLI_ASSOC);
		$campo = [
			"<div class='{$divClasse}'>
				<label>{$nome}</label>
				<select name='{$variavel}' id='{$variavel}' class='{$selectClasse}' {$selectProps}>"
		];
		
		$rows = array_merge($opcoes, $rows);

		foreach($rows as $row){
			$campo[] =
				"<option value='{$row["value"]}' ".($row["value"] == $modificador? "selected": "")." {$row["props"]}>
					{$row["text"]}
				</option>";
		}

		$campo[] = "</select>
			</div>";
		return implode("", $campo);
	}

	function gerarHTMLArquivos($nome, $destino, $idRelacionado, $arquivos, $nivelUsuario = 'Admin') {
			// --- 1. Lógica de Geração da Lista de Arquivos (Tabela) ---
			$arquivo_list = '';

			// Flag para verificar se o usuário é um funcionário com restrições
			$isFuncionario = ($nivelUsuario === 'Funcionário');

			if (!empty($arquivos)) {
				foreach ($arquivos as $arquivo) {

					// Regra de Visibilidade Específica para Funcionários:
					// Se o usuário é 'Funcionário' e o documento está marcado como 'nao' visível, pule a linha.
					if ($isFuncionario && ($arquivo['docu_tx_visivel'] ?? 'nao') === 'nao') {
						continue;
					}

					$dataHora = new DateTime($arquivo['docu_tx_dataCadastro']);
					$dataHoraFormatada = $dataHora->format('d/m/Y H:i:s');

					$dataHoraFormatadaVencimento = '';
					if (!empty($arquivo['docu_tx_datavencimento']) && $arquivo['docu_tx_datavencimento'] !== "0000-00-00 00:00:00") {
						$dataHoraVencimento = new DateTime($arquivo['docu_tx_datavencimento']);
						$dataHoraFormatadaVencimento = $dataHoraVencimento->format('d/m/Y');
					}

					// --- VERIFICAÇÃO DE EXISTÊNCIA DO ARQUIVO ---
					$mime_type_arquivo = '';
					$arquivoExiste = false;
					if (isset($arquivo["docu_tx_caminho"]) && file_exists($arquivo["docu_tx_caminho"])) { // <-- VERIFICAÇÃO AQUI
						$arquivoExiste = true;
						if (function_exists('finfo_open')) {
							$finfo = finfo_open(FILEINFO_MIME_TYPE);
							$mime_type_arquivo = finfo_file($finfo, $arquivo["docu_tx_caminho"]);
							finfo_close($finfo);
						} else {
							// Fallback caso finfo não esteja disponível
							$mime_type_arquivo = 'application/octet-stream';
						}
					}

					$formatosSuportados = [
						'application/pdf', 'image/jpeg', 'image/png', 'image/gif',
						'image/webp', 'text/plain', 'text/html'
					];

					// Inicialização de Ícones
					$iconePreview = '';
					$iconeDownload = '';
					$iconeExcluir = '';


					// Ícone de Download e Preview (Dependem da existência do arquivo)
					if ($arquivoExiste) {
						$caminhoDownload = htmlspecialchars($arquivo['docu_tx_caminho'], ENT_QUOTES, 'UTF-8');
						$iconeDownload = "
							<a class='text-info' style='cursor:pointer;'
							onclick=\"downloadArquivo($idRelacionado, '$caminhoDownload', 'downloadArquivo');\">
								<i class='glyphicon glyphicon-cloud-download' title='Download'></i>
							</a>";

						// Ícone de Preview (Depende da existência E do tipo MIME suportado)
						if (in_array($mime_type_arquivo, $formatosSuportados)) {
							$urlPreview = htmlspecialchars($arquivo['docu_tx_caminho'], ENT_QUOTES, 'UTF-8');
							$nomeArquivo = htmlspecialchars($arquivo['docu_tx_nome'], ENT_QUOTES, 'UTF-8');
							$iconePreview = "
								<a class='text-info' style='cursor:pointer;'
								onclick=\"abrirPreview('$urlPreview', '$mime_type_arquivo', '$nomeArquivo')\">
									<i class='far fa-eye' title='Visualizar Documento'></i>
								</a>";
						} else {
							$iconePreview = '<i class="fa-regular fa-eye-slash" title="Preview indisponível"></i>';
						}
					} else {
						$iconeDownload = '<i class="glyphicon glyphicon-cloud-download text-muted" title="Arquivo não encontrado"></i>';
						$iconePreview = '<i class="fa-regular fa-eye-slash" title="Preview indisponível"></i>';
					}


					// Ícone de Assinatura
					$IconeAssinatura = ($arquivo["docu_tx_assinado"] === "sim")
						? "&nbsp;<i class='fa-solid fa-file-contract text-success' title='Documento assinado digitalmente'></i>"
						: "&nbsp;<i class='fa-solid fa-file-signature text-danger' title='Documento não assinado'></i>";

					// Ícone de Excluir (Apenas para não-Funcionários)
					if (!$isFuncionario) {
						$nomeArquivoExcluir = htmlspecialchars($arquivo['docu_tx_nome'], ENT_QUOTES, 'UTF-8');
						$iconeExcluir = "
							&nbsp;<a class='text-danger' style='cursor:pointer;'
							onclick=\"remover_arquivo($idRelacionado, {$arquivo['docu_nb_id']}, '$nomeArquivoExcluir', 'excluir_documento');\">
								<i class='glyphicon glyphicon-trash' title='Excluir'></i>
							</a>";
					}

					// Linha da tabela
					$arquivo_list .= "
					<tr>
						<td>" . htmlspecialchars($arquivo['docu_tx_nome'] ?? '') . "</td>
						<td>" . htmlspecialchars($arquivo['docu_tx_descricao'] ?? '') . "</td>
						<td data-cadastro='{$dataHoraFormatada}'>$dataHoraFormatada</td>
						<td data-vencimento='{$dataHoraFormatadaVencimento}'>$dataHoraFormatadaVencimento</td>
						<td data-tipo='" . htmlspecialchars($arquivo['tipo_tx_nome'] ?? '') . "'>" . htmlspecialchars($arquivo['tipo_tx_nome'] ?? '') . "</td>
						<td data-setor='" . htmlspecialchars($arquivo['grup_tx_nome'] ?? '') . "'>" . htmlspecialchars($arquivo['grup_tx_nome'] ?? '') . "</td>
						<td data-subsetor='" . htmlspecialchars($arquivo['sbgr_tx_nome'] ?? '') . "'>" . htmlspecialchars($arquivo['sbgr_tx_nome'] ?? '') . "</td>
						<td class='text-center action-icons' style='white-space:nowrap;'>
							$iconeDownload
							$IconeAssinatura
							$iconePreview
							$iconeExcluir
						</td>
					</tr>";
				}
			}

			// --- 2. Consultas ao Banco de Dados (Mantidas para Popular Filtros e Modal) ---
			// OBS: Presume-se que as funções 'query' e 'mysqli_fetch_all' estão disponíveis.

			$tipo_documento = mysqli_fetch_all(query(
				"SELECT tipo_nb_id, tipo_tx_nome, tipo_tx_vencimento, tipo_nb_grupo, tipo_nb_sbgrupo
				FROM tipos_documentos
				ORDER BY tipo_tx_nome ASC"
			), MYSQLI_ASSOC);

			$setor_documento = mysqli_fetch_all(query(
				"SELECT grup_nb_id, grup_tx_nome
					FROM grupos_documentos ORDER BY grup_tx_nome ASC"
			), MYSQLI_ASSOC);

			$sbsetor_documento = mysqli_fetch_all(query(
				"SELECT sbgr_nb_id, sbgr_nb_idgrup, sbgr_tx_nome, sbgr_tx_status FROM sbgrupos_documentos ORDER BY sbgr_tx_nome ASC"
			), MYSQLI_ASSOC);


			// --- 3. Geração de Listas (Options) ---
			$list_tipos = "";
			foreach ($tipo_documento as $tipo) {
				$list_tipos .= "<option value='" . htmlspecialchars($tipo['tipo_tx_nome']) . "' data-id='{$tipo['tipo_nb_id']}'>" . htmlspecialchars($tipo['tipo_tx_nome']) . "</option>";
			}
			
			$list_tipos_modal_default = "<option value=''>Selecione um tipo</option>"; 

			// Lista para o filtro de Setor
			$list_setor_filtro = "<option value=''>Todos</option>";
			$list_setor_modal = "<option value=''></option>"; // Vazio no modal para forçar a seleção
			foreach ($setor_documento as $setor) {
				$idSetor = htmlspecialchars($setor['grup_nb_id'], ENT_QUOTES, 'UTF-8');
				$nomeSetor = htmlspecialchars($setor['grup_tx_nome'], ENT_QUOTES, 'UTF-8');

				$list_setor_filtro .= "<option value='{$nomeSetor}'>{$nomeSetor}</option>";
				$list_setor_modal .= "<option value='{$idSetor}' data-idSetor='{$idSetor}'>{$nomeSetor}</option>";
			}

			// Adicionar Arquivo (Apenas se não for Funcionário)
			$AbriAdicionarArquivo = '';
			if (!$isFuncionario) {
				$AbriAdicionarArquivo = '
				<tr class="add-row">
					<td colspan="8" class="text-center">
						<a href="#" data-toggle="modal" data-target="#myModal_' . $idRelacionado . '" title="Adicionar Novo Arquivo">
							<i class="glyphicon glyphicon-plus-sign" style="font-size: 20px;"></i>
						</a>
					</td>
				</tr>';
			}


			// --- 4. HTML da Tabela e Filtros ---
			$tabela = '
			<div class="portlet light">
				<div class="portlet-title">
					<div class="caption">
						<span class="caption-subject font-dark bold uppercase">' . htmlspecialchars($nome) . '</span>
					</div>
				</div>

				<div class="portlet-body">
					<div class="form-inline custom-filters" style="margin-bottom:15px;">
						<div class="form-group">
							<label for="filtroNome_' . $idRelacionado . '">Nome:</label>
							<input type="text" id="filtroNome_' . $idRelacionado . '" class="form-control" placeholder="Digite o nome">
						</div>

						<div class="form-group" style="margin-left:10px;">
							<label for="filtroCadastro_' . $idRelacionado . '">Data Cadastro:</label>
							<input type="date" id="filtroCadastro_' . $idRelacionado . '" class="form-control">
						</div>

						<div class="form-group" style="margin-left:10px;">
							<label for="filtroVencimento_' . $idRelacionado . '">Data Vencimento:</label>
							<input type="date" id="filtroVencimento_' . $idRelacionado . '" class="form-control">
						</div>

						<div class="form-group" style="margin-left:10px;">
							<label for="filtroTipo_' . $idRelacionado . '">Tipo:</label>
							<select id="filtroTipo_' . $idRelacionado . '" class="form-control">
								<option value="">Todos</option>
								' . $list_tipos . '
							</select>
						</div>

						<div class="form-group" style="margin-top:10px;">
							<label for="filtroSetor_' . $idRelacionado . '">Setor:</label>
							<select id="filtroSetor_' . $idRelacionado . '" class="form-control">
								' . $list_setor_filtro . '
							</select>
						</div>

						<div class="form-group" style="margin-top:10px; margin-left:10px;">
							<label for="filtroSubSetor_' . $idRelacionado . '">Sub-setor:</label>
							<select id="filtroSubSetor_' . $idRelacionado . '" class="form-control" disabled>
								<option value="">Selecione um setor</option>
							</select>
						</div>

						<button id="limparFiltros_' . $idRelacionado . '" class="btn btn-default" style="margin-left:10px; margin-top: 5px;">
							Limpar
						</button>
					</div>

					<div class="table-responsive">
						<table id="contex-grid_' . $idRelacionado . '" class="table table-striped table-bordered table-hover dt-responsive" width="100%">
							<thead>
								<tr>
									<th style="width:25%">NOME</th>
									<th style="width:30%">DESCRIÇÃO</th>
									<th style="width:15%">DATA CADASTRO</th>
									<th style="width:15%">DATA VENCIMENTO</th>
									<th style="width:20%">TIPO</th>
									<th style="width:20%">SETOR</th>
									<th style="width:20%">SUB-SETOR</th>
									<th style="width:20%; text-align:center; white-space:nowrap;">
										<i class="glyphicon glyphicon-cloud-download" title="Download"></i>
										&nbsp;<i class="fas fa-file-signature" title="Status de Assinatura"></i>
										&nbsp;<i class="far fa-eye" title="Visualizar"></i>
										' . ($isFuncionario ? '' : '&nbsp;<i class="glyphicon glyphicon-trash" title="Excluir"></i>') . '
									</th>
								</tr>
							</thead>
							<tbody>
								' . $arquivo_list . '
								' . $AbriAdicionarArquivo . '
							</tbody>
						</table>
					</div>
				</div>
			</div>';

			// --- 5. Modal de Preview (Compartilhado) ---
			$modal_preview = '
			<div id="previewModal" class="modal fade" tabindex="-1" role="dialog">
				<div class="modal-dialog modal-lg" role="document">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal">&times;</button>
							<h4 class="modal-title" id="previewTitle">Pré-visualização do Documento</h4>
						</div>
						<div class="modal-body" id="previewContent" style="text-align:center; min-height:400px;"></div>
						<div class="modal-footer">
							<button type="button" class="btn btn-default" data-dismiss="modal">Fechar</button>
						</div>
					</div>
				</div>
			</div>';


			// --- 6. Modal de Upload ---
			$modal_upload = "";
			$action_file = '';

			if ($destino === 'Funcionário') {
				$action_file = 'cadastro_funcionario.php';
			} elseif ($destino === 'Empresa') {
				$action_file = 'cadastro_empresa.php';
			} else {
				$action_file = 'cadastro_parametro.php';
			}


			if (!$isFuncionario) {
				$modal_upload = "
				<style>
					.dropzone-upload {
						position: relative;
						border: 2px dashed #ccc;
						border-radius: 6px;
						padding: 20px;
						text-align: center;
						cursor: pointer;
						transition: background-color 0.2s ease;
					}

					.dropzone-upload.dragover {
						background-color: #f5f5f5;
						border-color: #3c8dbc;
					}

					.dropzone-upload input {
						position: absolute;
						top: -9999px; /* Manda o input para longe da tela */
						left: -9999px;
						opacity: 0;
						/* Removidas width/height 100% */
					}
				</style>
				<div class='modal fade' id='myModal_" . $idRelacionado . "' tabindex='-1' role='dialog' aria-labelledby='myModalLabel'>
					<div class='modal-dialog' role='document'>
						<div class='modal-content'>
							<div class='modal-header'>
								<button type='button' class='close' data-dismiss='modal'>&times;</button>
								<h4 class='modal-title' id='myModalLabel'>Upload Arquivo para " . htmlspecialchars($nome) . "</h4>
							</div>
							<div class='modal-body'>
								<form name='form_enviar_arquivo_" . $idRelacionado . "' method='post' action='" . $action_file . "' enctype='multipart/form-data'>
									<div class='form-group'>
										<label>Nome do arquivo:</label>
										<input type='text' class='form-control' name='file-name'>
									</div>
									<div class='form-group'>
										<label>Descrição:</label>
										<textarea class='form-control' name='description-text'></textarea>
									</div>
									<div class='form-group' id='campo_setor'>
										<label>Setor (Obrigatório):</label>
										<select class='form-control' name='setor' id='setor_" . $idRelacionado . "'>{$list_setor_modal}</select>
									</div>
									<div class='form-group' id='campo_sub_setor'>
										<label>Sub-setor (Opcional):</label>
										<select name='sub-setor' id='sub-setor_" . $idRelacionado . "' class='form-control' disabled>
											<option value=''>Selecione um setor primeiro</option>
										</select>
									</div>
									<div class='form-group'>
										<label>Tipo de Documento (Obrigatório):</label>
										<select class='form-control' name='tipo_documento' id='tipo_documento_" . $idRelacionado . "' disabled>
											{$list_tipos_modal_default}
										</select>
									</div>
									<div class='form-group'>
										<label>Arquivo:</label>
										<div class='dropzone-upload' id='dropzone_" . $idRelacionado . "'>
											<p>Arraste o arquivo aqui ou clique para selecionar</p>
											<input type='file' name='file' id='fileInput_" . $idRelacionado . "'>
										</div>
									</div>
									<div class='form-group'>
										<label>Visível ao funcionário:</label>
										<select class='form-control' name='visibilidade'>
											<option value='sim'>Sim</option>
											<option value='nao' selected>Não</option>
										</select>
									</div>
									<div class='form-group' id='campo_vencimento_" . $idRelacionado . "' style='display:none;'>
										<label>Data de Vencimento:</label>
										<input type='date' class='form-control' name='data_vencimento' id='data_vencimento_" . $idRelacionado . "'>
									</div>
									<input type='hidden' name='acao' value='enviarDocumento'>
									<input type='hidden' name='idRelacionado' value='{$idRelacionado}'> 
									<input type='hidden' name='idUserCadastro' value='{$_SESSION['user_nb_id']}'>
									<input type='hidden' name='idSubSetorOuSetor' id='idSubSetorOuSetor_{$idRelacionado}' value=''>
								</form>
							</div>
							<div class='modal-footer'>
								<button type='button' class='btn btn-default' data-dismiss='modal'>Cancelar</button>
								<button type='button' class='btn btn-primary' onclick='validarEnvio_" . $idRelacionado . "()'>Salvar arquivo</button>
							</div>
						</div>
					</div>
				</div>";
			}


			// --- 7. Scripts (JavaScript) e Estilos (CSS) ---
			$scripts_e_estilos = "
			<style>
				#contex-grid_" . $idRelacionado . " td, #contex-grid_" . $idRelacionado . " th {
					vertical-align: middle !important;
				}
				#contex-grid_" . $idRelacionado . " .action-icons i {
					margin: 0 5px;
					font-size: 15px;
					vertical-align: middle;
				}
				#contex-grid_" . $idRelacionado . " .action-icons a {
					text-decoration: none;
				}
				#contex-grid_" . $idRelacionado . " .add-row td {
					background-color: #f9f9f9;
				}
				#contex-grid_" . $idRelacionado . " .add-row a {
					color: #5bc0de;
				}
				@media (max-width: 768px) {
					#contex-grid_" . $idRelacionado . " th:last-child, #contex-grid_" . $idRelacionado . " td:last-child {
						white-space: nowrap;
					}
					#contex-grid_" . $idRelacionado . " .action-icons i {
						font-size: 13px;
						margin: 0 3px;
					}
				}
			</style>
			<script>
			// *** INCLUSÃO DA FUNÇÃO abrirPreview (Definição Global) ***
			function abrirPreview(caminho, tipo, nomeArquivo) {
				let content = \"\";

				if (tipo === \"application/pdf\" || tipo === \"text/plain\") {
					content = \"<iframe src=\'\" + caminho + \"\' width=\'100%\' height=\'500px\' style=\'border:none;\'></iframe>\";
				} else if (tipo.match(/^image\\//)) {
					content = \"<img src=\'\" + caminho + \"\' style=\'max-width:100%; max-height:500px; border:1px solid #ddd; border-radius:4px;\'>\";
				} else {
					content = \"<p>Visualização não disponível para este tipo de arquivo.</p>\";
				}

				document.getElementById(\"previewTitle\").innerText = \"Pré-visualização: \" + nomeArquivo;
				document.getElementById(\"previewContent\").innerHTML = content;
				$(\"#previewModal\").modal(\"show\");
			}
			// *************************************************************

			// Lógica do Modal de Upload (Única por ID) - Só precisa se não for Funcionário
			" . (!$isFuncionario ? "
			function validarEnvio_" . $idRelacionado . "() {
				const tipoSelect = document.getElementById('tipo_documento_" . $idRelacionado . "');
				const dataVencInput = document.getElementById('data_vencimento_" . $idRelacionado . "');
				const setorSelect = document.getElementById('setor_" . $idRelacionado . "');
				const fileInput = document.getElementById('fileInput_" . $idRelacionado . "');
				const idHidden = document.getElementById('idSubSetorOuSetor_{$idRelacionado}');

				// **Validação de Setor (OBRIGATÓRIO)**
				if (!setorSelect.value) {
					alert('Por favor, selecione um Setor.');
					setorSelect.focus();
					return false;
				}
				
				// **Validação de Tipo de Documento (OBRIGATÓRIO)**
				if (!tipoSelect.value) {
					alert('Por favor, selecione um Tipo de Documento.');
					tipoSelect.focus();
					return false;
				}
				
				// Validação de Arquivo
				if (fileInput.files.length === 0) {
					alert('Por favor, selecione um Arquivo para upload.');
					return false;
				}

				// Validação de Vencimento
				const selectedOption = tipoSelect.options[tipoSelect.selectedIndex];
				const vencimento = selectedOption.getAttribute('data-vencimento');

				if (vencimento === 'sim' && !dataVencInput.value) {
					alert('O Tipo de Documento selecionado exige o preenchimento da Data de Vencimento.');
					dataVencInput.focus();
					return false;
				}
				
				// Garantir que o campo hidden de ID foi preenchido (deve ser o Setor ou Sub-setor)
				if (!idHidden.value) {
					alert('Erro interno: O ID do Setor/Sub-setor não foi definido. Recarregue a página.');
					return false;
				}


				// Submete o formulário
				document.forms['form_enviar_arquivo_" . $idRelacionado . "'].submit();
			}

			// Configuração do Dropzone e Vencimento no Modal (Única por ID)
			$(document).ready(function() {
				const idRelacionado = '{$idRelacionado}';
				const setorSelect = $('#setor_' + idRelacionado);
				const subSetorSelect = $('#sub-setor_' + idRelacionado);
				const tipoDocumentoSelect = $('#tipo_documento_' + idRelacionado);
				const campoVencimento = $('#campo_vencimento_' + idRelacionado);
				const dataVencimentoInput = $('#data_vencimento_' + idRelacionado);
				const dropzone = document.getElementById('dropzone_' + idRelacionado);
				const fileInput = document.getElementById('fileInput_' + idRelacionado);
				const idHidden = $('#idSubSetorOuSetor_' + idRelacionado);

				if (!dropzone) return;

				const message = dropzone.querySelector('p');

				// Dados do PHP (Transformados para JS)
				const todosSubSetores = " . json_encode($sbsetor_documento) . ";
				const todosTipos = " . json_encode($tipo_documento) . ";

				// --- Lógica de Vencimento (Mantida) ---
				tipoDocumentoSelect.on('change', function() {
					const selecionado = $(this).find(':selected');
					const vencimento = selecionado.data('vencimento');
					const isSelected = !!selecionado.val(); 

					if (isSelected && vencimento === 'sim') {
						campoVencimento.slideDown();
						dataVencimentoInput.prop('required', true);
					} else {
						campoVencimento.slideUp();
						dataVencimentoInput.prop('required', false).val('');
					}
				}).trigger('change');

				// --- Lógica Dropzone (Corrigida e Reforçada com jQuery) ---
				const dropzoneElement = $('#dropzone_' + idRelacionado);
				const fileInputElement = $('#fileInput_' + idRelacionado);

				// --- Lógica Dropzone (Corrigida 3.0: Minimalista) ---
            
				// Listener de Clique: Apenas dispara o clique no input
				dropzone.addEventListener('click', () => {
					fileInput.click();
				});

				// Listener de mudança de arquivo (Mantido)
				fileInput.addEventListener('change', () => {
					message.textContent = fileInput.files.length > 0 ? fileInput.files[0].name : 'Arraste o arquivo aqui ou clique para selecionar';
				});
				
				// Drag and Drop (Mantido)
				dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
				dropzone.addEventListener('dragleave', () => { dropzone.classList.remove('dragover'); });
				dropzone.addEventListener('drop', (e) => {
					e.preventDefault();
					dropzone.classList.remove('dragover');
					if (e.dataTransfer.files.length > 0) {
						fileInput.files = e.dataTransfer.files;
						message.textContent = e.dataTransfer.files[0].name;
					}
				});

				// --- Lógica Setor/Sub-Setor/Tipo (Dependência no Modal) ---
				
				function atualizarIdParaEnvio(setorId, subSetorId) {
					// Se o sub-setor foi selecionado, envia o ID dele
					if (subSetorId) {
						idHidden.val(subSetorId);
					} 
					// Senão, se o setor foi selecionado, envia o ID dele
					else if (setorId) {
						idHidden.val(setorId);
					}
					// Senão, limpa
					else {
						idHidden.val('');
					}
				}

				function carregarSubSetores(setorId) {
					// Opção padrão para Sub-setor
					subSetorSelect.html('<option value=\'\'>Selecione um sub-setor (Opcional)</option>').prop('disabled', true);
					// Tipo de Documento deve ser resetado e desabilitado até que os tipos sejam carregados
					tipoDocumentoSelect.html('<option value=\'\'>Selecione um setor</option>').prop('disabled', true).trigger('change'); 

					if (!setorId) {
						atualizarIdParaEnvio('', '');
						return;
					}

					// 1. Carrega o Sub-setor
					const subSetoresFiltrados = todosSubSetores.filter(subSetor =>
						String(subSetor.sbgr_nb_idgrup) === String(setorId)
					);
					
					if (subSetoresFiltrados.length > 0) {
						subSetorSelect.prop('disabled', false);
						subSetoresFiltrados.forEach(subSetor => {
							subSetorSelect.append(new Option(subSetor.sbgr_tx_nome, subSetor.sbgr_nb_id));
						});
					} else {
						subSetorSelect.html('<option value=\'\'>Nenhum sub-setor disponível</option>');
					}

					// 2. Carrega tipos com base APENAS no setor (sub-setor vazio)
					carregarTiposDocumento(setorId, ''); 
				}

				function carregarTiposDocumento(setorId, subSetorId) {
					tipoDocumentoSelect.html('<option value=\'\'>Selecione um tipo</option>').prop('disabled', true);
					
					if (!setorId) return; 

					let tiposFiltrados = [];

					if (subSetorId) {
						// REGRA 1: Sub-setor selecionado -> Filtra os tipos vinculados a ESSE sub-setor
						tiposFiltrados = todosTipos.filter(tipo => 
							(tipo.tipo_nb_sbgrupo && String(tipo.tipo_nb_sbgrupo) === String(subSetorId))
						);
					} else {
						// REGRA 2: Sub-setor NÃO selecionado -> Lista todos os tipos vinculados ao Setor
						tiposFiltrados = todosTipos.filter(tipo => 
							(tipo.tipo_nb_grupo && String(tipo.tipo_nb_grupo) === String(setorId))
						);
					}

					if (tiposFiltrados.length > 0) {
						tiposFiltrados.forEach(tipo => {
							tipoDocumentoSelect.append(
								$('<option>', {
									value: tipo.tipo_nb_id,
									text: tipo.tipo_tx_nome,
									'data-vencimento': tipo.tipo_tx_vencimento || 'nao',
									'data-grupo': tipo.tipo_nb_grupo || '',
									'data-subgrupo': tipo.tipo_nb_sbgrupo || ''
								})
							);
						});
						// Habilita o Tipo de Documento APENAS se tiver opções
						tipoDocumentoSelect.prop('disabled', false); 
					} else {
						tipoDocumentoSelect.html('<option value=\'\'>Nenhum tipo disponível</option>');
						// Mantém desabilitado se não houver tipos
					}
					
					// Atualiza o ID oculto para envio
					atualizarIdParaEnvio(setorId, subSetorId);
					tipoDocumentoSelect.trigger('change');
				}

				// Eventos de mudança nos selects
				setorSelect.on('change', function() {
					// Limpa e carrega Sub-setores, e depois os Tipos (baseado no Setor)
					carregarSubSetores($(this).val());
				});

				subSetorSelect.on('change', function() {
					const setorId = setorSelect.val();
					const subSetorId = $(this).val(); 
					
					// Carrega Tipos com base no Sub-setor OU Setor (se Sub-setor for vazio)
					carregarTiposDocumento(setorId, subSetorId); 
				});
				
				// Inicializa ao carregar a página/modal
				carregarSubSetores(setorSelect.val());
			});
			" : "") . "

			// Lógica de Filtro da Tabela (Única por ID)
			document.addEventListener('DOMContentLoaded', function() {
				const idRelacionado = '{$idRelacionado}';
				const filtroNome = document.getElementById('filtroNome_' + idRelacionado);
				const filtroCadastro = document.getElementById('filtroCadastro_' + idRelacionado);
				const filtroVencimento = document.getElementById('filtroVencimento_' + idRelacionado);
				const filtroTipo = document.getElementById('filtroTipo_' + idRelacionado);
				const filtroSetor = document.getElementById('filtroSetor_' + idRelacionado);
				const filtroSubSetor = document.getElementById('filtroSubSetor_' + idRelacionado); 
				const limparBtn = document.getElementById('limparFiltros_' + idRelacionado);
				const tabela = document.getElementById('contex-grid_' + idRelacionado);
				if (!tabela) return;

				const linhas = tabela.getElementsByTagName('tr');

				function formatarDataParaComparar(dataBr) {
					if (!dataBr) return '';
					const partes = dataBr.trim().split(' ')[0].split('/');
					if (partes.length === 3) {
						return `\${partes[2]}-\${partes[1]}-\${partes[0]}`;
					}
					return '';
				}

				function filtrarTabela() {
					const nomeValor = filtroNome.value.toLowerCase();
					const cadastroValor = filtroCadastro.value;
					const vencimentoValor = filtroVencimento.value;
					const tipoValor = filtroTipo.value.toLowerCase();
					const setorValor = filtroSetor.value.toLowerCase();
					const subSetorValor = filtroSubSetor.value.toLowerCase(); 

					for (let i = 1; i < linhas.length; i++) {
						const linha = linhas[i];
						if (linha.classList.contains('add-row')) continue;

						const celulas = linha.getElementsByTagName('td');
						if (celulas.length < 8) continue;

						const nome = celulas[0].textContent.toLowerCase();
						const cadastro = celulas[2].textContent.trim();
						const vencimento = celulas[3].textContent.trim();
						const tipo = celulas[4].textContent.toLowerCase();
						const setor = celulas[5].textContent.toLowerCase();
						const subsetor = celulas[6].textContent.toLowerCase(); 

						let exibir = true;

						if (nomeValor && !nome.includes(nomeValor)) exibir = false;
						if (cadastroValor && formatarDataParaComparar(cadastro) !== cadastroValor) exibir = false;
						if (vencimentoValor && formatarDataParaComparar(vencimento) !== vencimentoValor) exibir = false;
						if (tipoValor && tipo !== tipoValor) exibir = false;
						if (setorValor && setor !== setorValor) exibir = false;
						if (subSetorValor && subsetor !== subSetorValor) exibir = false; 

						linha.style.display = exibir ? '' : 'none';
					}
				}
				
				// Lógica de carregamento em cascata do filtro Sub-setor
				function carregarFiltroSubSetores() {
					const setorNome = filtroSetor.value.toLowerCase();
					
					// 1. Limpa e desabilita o Sub-setor
					filtroSubSetor.innerHTML = '<option value=\"\">Todos</option>';
					filtroSubSetor.disabled = true;
					
					if (!setorNome) {
						filtrarTabela();
						return; 
					}
					
					// 2. Mapeia os Sub-setores únicos disponíveis na tabela para o Setor selecionado
					const subSetoresUnicos = new Set();
					for (let i = 1; i < linhas.length; i++) {
						const linha = linhas[i];
						if (linha.classList.contains('add-row')) continue;
						
						const celulas = linha.getElementsByTagName('td');
						if (celulas.length < 8) continue;
						
						const linhaSetor = celulas[5].textContent.toLowerCase();
						const linhaSubSetor = celulas[6].textContent.trim(); 
						
						if (linhaSetor === setorNome && linhaSubSetor) {
							subSetoresUnicos.add(linhaSubSetor);
						}
					}
					
					// 3. Popula o Sub-setor com as opções encontradas
					if (subSetoresUnicos.size > 0) {
						filtroSubSetor.disabled = false;
						// Adiciona a opção 'Todos' antes dos sub-setores únicos
						filtroSubSetor.innerHTML = '<option value=\"\">Todos</option>';
						
						Array.from(subSetoresUnicos).sort().forEach(subSetor => {
							const option = document.createElement('option');
							option.value = subSetor.toLowerCase(); 
							option.textContent = subSetor;
							filtroSubSetor.appendChild(option);
						});
					} else {
						filtroSubSetor.innerHTML = '<option value=\"\">Nenhum sub-setor</option>';
					}
					
					// 4. Garante que o filtro da tabela seja aplicado após a mudança
					filtrarTabela();
				}

				// Eventos de filtro
				filtroNome.addEventListener('input', filtrarTabela);
				filtroCadastro.addEventListener('change', filtrarTabela);
				filtroVencimento.addEventListener('change', filtrarTabela);
				filtroTipo.addEventListener('change', filtrarTabela);
				filtroSetor.addEventListener('change', carregarFiltroSubSetores); 
				filtroSubSetor.addEventListener('change', filtrarTabela); 

				// Botão de limpar filtros
				limparBtn.addEventListener('click', function() {
					filtroNome.value = '';
					filtroCadastro.value = '';
					filtroVencimento.value = '';
					filtroTipo.selectedIndex = 0;
					filtroSetor.selectedIndex = 0;
					
					// Limpa o sub-setor e aplica o filtro
					filtroSubSetor.innerHTML = '<option value=\"\">Todos</option>';
					filtroSubSetor.disabled = true;
					
					filtrarTabela();
				});
				
				// Aplica o filtro inicial ao carregar
				carregarFiltroSubSetores(); // Chama para inicializar a lógica de filtro de sub-setor
			});
			</script>
			";

		return $tabela . $modal_preview . $modal_upload . $scripts_e_estilos;
	}

	// --- Funções de Interface (API) ---

	function arquivosParametro($nome, $idParametro, $arquivos) {
		// Supondo que $_SESSION['user_tx_nivel'] exista e contenha o nível do usuário
		return gerarHTMLArquivos($nome, '',$idParametro, $arquivos, $_SESSION['user_tx_nivel'] ?? 'Admin');
	}

	function arquivosEmpresa($nome, $idEmpresa, $arquivos) {
		return gerarHTMLArquivos($nome, 'Empresa',$idEmpresa, $arquivos, $_SESSION['user_tx_nivel'] ?? 'Admin');
	}

	function arquivosFuncionario($nome, $idFuncionario, $arquivos) {
		return gerarHTMLArquivos($nome, 'Funcionário',$idFuncionario, $arquivos, $_SESSION['user_tx_nivel'] ?? 'Funcionário');
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

	function query($query, $types = '', array $vars = []){
		global $conn;
		if(empty($types) || empty($vars)){
			$result = mysqli_query($conn,$query) or mysqli_error($conn);
		}else{
			$stmt = mysqli_prepare($conn, $query);
			mysqli_stmt_bind_param($stmt, $types, ...$vars);
			mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
		}
		return $result;
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

	function formatarTipo(string $tipo){
		$tipos = [
			"horas_por_dia" => "Horas/Dia",
			"escala" => "Escala"
		];

		return $tipos[$tipo];
	}

	function formatarColunaJornada(string $tipo, string $time1, string $time2, string $dias = ""){
		if($tipo == "escala"){
			$nomeDias = [
				"",
				"Domingo",
				"Segunda",
				"Terça",
				"Quarta",
				"Quinta",
				"Sexta",
				"Sábado"
			];

			$dias = json_decode($dias);
			$diasString = "";
			foreach($dias as $dia){
				$diasString .= $nomeDias[$dia].", ";
			}
			$diasString = substr($diasString, 0, strlen($diasString)-2).".";

			$retorno = "Início: {$time1}, Fim: {$time2}<br>Nos dias: {$diasString}";
		}
		if($tipo == "horas_por_dia"){
			$retorno = "{$time1} Semanal, {$time2} Sábado";
		}

		return $retorno;
	}

	function reordenarArrayPorChaves(array $arrayOriginal, array $ordemDesejada): array {
		$arrayReordenado = [];

		
		foreach ($ordemDesejada as $chave) {
			if (array_key_exists($chave, $arrayOriginal)) {
				$arrayReordenado[$chave] = $arrayOriginal[$chave];
			}
		}

		// Se quiser manter as chaves extras que não estão na ordem desejada, descomente abaixo:
		// foreach ($arrayOriginal as $chave => $valor) {
		//     if (!array_key_exists($chave, $arrayReordenado)) {
		//         $arrayReordenado[$chave] = $valor;
		//     }
		// }

		return $arrayReordenado;
	}
