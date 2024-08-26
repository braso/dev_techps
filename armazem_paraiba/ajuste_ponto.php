<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	include_once 'funcoes_ponto.php';

	function cadastrarAjuste(){

		//Conferir se tem as informações necessárias{
			if(empty($_POST['id']) || empty($_POST['hora']) || empty($_POST['idMacro']) || empty($_POST['motivo'])){
				set_status("ERRO: Dados insuficientes!");
				index();
				exit;
			}
			$aMotorista = carregar('entidade',$_POST['id']);
			$aTipo = carregar('macroponto', $_POST['idMacro']);
			if(empty($aMotorista)){
				set_status("ERRO: Motorista não encontrado.");
				index();
				exit;
			}
			if(empty($aTipo)){
				set_status("ERRO: Macro não encontrado.");
				index();
				exit;
			}
		//}

		// //Conferir se tem as informações necessárias 2.0{
		// 	$camposObrig = [
		// 		"id" => "Motorista",
		// 		"hora" => "Hora",
		// 		"idMacro" => "Tipo de Registro",
		// 		"motivo" => "Motivo",
		// 	];
		// 	$errorMsg = conferirCamposObrig($camposObrig, $_POST);
		// 	if(!empty($errorMsg)){
		// 		set_status($errorMsg);
		// 		index();
		// 		exit;
		// 	}

		// 	var_dump($camposObrig); echo "<br><br>";
		// 	var_dump($_POST); echo "<br><br>";
		// 	var_dump($errorMsg); echo "<br><br>";

		// 	die("debug");

		// 	$aMotorista = carregar('entidade',$_POST['id']);
		// 	if(empty($aMotorista)){
		// 		set_status("ERRO: Motorista não encontrado.");
		// 		index();
		// 		exit;
		// 	}

		// 	$aTipo = carregar('macroponto', $_POST['idMacro']);
		// 	if(empty($aTipo)){
		// 		set_status("ERRO: Tipo de registro não encontrado.");
		// 		index();
		// 		exit;
		// 	}
		// //}
		
		//Tratamento de erros{
			$error = false;
			$errorMsg = 'ERRO: ';
			$codigosJornada = ['inicio' => 1, 'fim' => 2];
			//Conferir se há uma jornada aberta{
				$temJornadaAberta = mysqli_fetch_all(
					query(
						"SELECT * FROM ponto 
							WHERE pont_tx_tipo IN ('".$codigosJornada["inicio"]."', '".$codigosJornada['fim']."')
								AND pont_tx_status = 'ativo'
								AND pont_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
								AND pont_tx_data <= STR_TO_DATE('".$_POST['data'].' '.$_POST["hora"].":59', '%Y-%m-%d %H:%i:%s')
							ORDER BY pont_tx_data DESC
							LIMIT 1"
					),
					MYSQLI_ASSOC
				)[0];
				$temJornadaAberta = (!empty($temJornadaAberta) && intval($temJornadaAberta['pont_tx_tipo']) == $codigosJornada['inicio']);

				if($temJornadaAberta){
					if(intval($aTipo['macr_tx_codigoInterno']) == $codigosJornada['inicio']){ //Se tem jornada aberta e está tentando cadastrar uma abertura de jornada
						$error = true;
						$errorMsg .= 'Não é possível registrar um '.strtolower($aTipo['macr_tx_nome']).' sem fechar o anterior.';
					}elseif(intval($aTipo['macr_tx_codigoInterno']) == $codigosJornada['fim']){
						$jornadaFechada = mysqli_fetch_assoc(
							query(
								"SELECT * FROM ponto 
									WHERE pont_tx_tipo IN ('".$codigosJornada['inicio']."', '".$codigosJornada['fim']."')
										AND pont_tx_status = 'ativo'
										AND pont_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
										AND pont_tx_data >= STR_TO_DATE('".$_POST['data'].' '.$_POST['hora'].":00', '%Y-%m-%d %H:%i:%s')
									ORDER BY pont_tx_data ASC
									LIMIT 1"
							)
						);
						$jornadaFechada = (!empty($jornadaFechada) && $jornadaFechada['pont_tx_tipo'] == $codigosJornada['fim']);
						if(!empty($jornadaFechada)){
							$error = true;
							$errorMsg .= 'Esta jornada já foi fechada neste horário ou após ele.';
						}
					}else{
						$matchedTypes = [
							'inicios' => [3,5,7,9,11],
							'fins' => [4,6,8,10,12]
						];

						$matchedTypes = [
							'inicios' => mysqli_fetch_all(query("SELECT macr_tx_codigoInterno FROM macroponto WHERE macr_tx_nome LIKE 'Inicio%' AND macr_tx_nome NOT LIKE '%de Jornada'"), MYSQLI_ASSOC),
							'fins' 	  => mysqli_fetch_all(query("SELECT macr_tx_codigoInterno FROM macroponto WHERE macr_tx_nome LIKE 'Fim%'    AND macr_tx_nome NOT LIKE '%de Jornada'"),    MYSQLI_ASSOC),
						];
						for($f = 0; $f < count($matchedTypes['inicios']); $f++){
							$matchedTypes['inicios'][$f] = intval($matchedTypes['inicios'][$f]['macr_tx_codigoInterno']);
							$matchedTypes['fins'][$f]    = intval($matchedTypes['fins'][$f]['macr_tx_codigoInterno']);
						}

						$temPeriodoAberto = mysqli_fetch_assoc(
							query(
								"SELECT * FROM ponto 
									WHERE pont_tx_status = 'ativo'
										AND pont_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
										AND pont_tx_data LIKE '".$_POST['data']."%'
										AND pont_tx_data <= STR_TO_DATE('".$_POST['data'].' '.$_POST['hora'].":59', '%Y-%m-%d %H:%i:%s')
									ORDER BY pont_tx_data DESC, pont_nb_id DESC
									LIMIT 1"
							)
						);
						
						$temPeriodoAberto['pont_tx_tipo'] = !empty($temPeriodoAberto['pont_tx_tipo'])? intval($temPeriodoAberto['pont_tx_tipo']): $temPeriodoAberto['pont_tx_tipo'];
						$temPeriodoAberto['macr_tx_codigoInterno'] = !empty($temPeriodoAberto['macr_tx_codigoInterno'])? intval($temPeriodoAberto['macr_tx_codigoInterno']): $temPeriodoAberto['macr_tx_codigoInterno'];
						
						$openTypeValue = intval($aTipo['macr_tx_codigoInterno'])-(intval($aTipo['macr_tx_codigoInterno'])%2 == 0? 1: 0); //O código de abertura do tipo que está sendo registrado.
						$sameType = ($temPeriodoAberto['pont_tx_tipo'] == $openTypeValue || $temPeriodoAberto['pont_tx_tipo'] == $openTypeValue+1); //Se esse período encontrado é do mesmo tipo que está tentando ser cadastrado
						$temPeriodoAberto = in_array($temPeriodoAberto['pont_tx_tipo'], $matchedTypes['inicios']); //Se encontrou um período aberto
						
						if(in_array(intval($aTipo['macr_tx_codigoInterno']), $matchedTypes['inicios'])){ //Se está registrando uma abertura de período
							if($temPeriodoAberto){
								$error = true;
								$errorMsg .= 'Não é possível registrar um '.strtolower($aTipo['macr_tx_nome']).' sem fechar o anterior.';
							}
						}elseif(in_array(intval($aTipo['macr_tx_codigoInterno']), $matchedTypes['fins'])){ //Se está registrando um fechamento de período
							if(!$temPeriodoAberto || !$sameType){ //Se não tem um período aberto ou se o período aberto é de tipo diferente do que está sendo fechado 
								$error = true;
								$errorMsg .= 'Não é possível registrar um '.strtolower($aTipo['macr_tx_nome']).' sem abrir um período antes.';
							}
						}
					}
				}elseif($aTipo['macr_tx_codigoInterno'] != '1'){
					$error = true;
					$errorMsg .= 'Não é possível realizar ajustes sem uma jornada aberta.';
				}
			//}

			if($error){
				set_status($errorMsg);
				index();
				exit;
			}
		//}

		$temPonto = mysqli_fetch_assoc(query(
			"SELECT pont_nb_id, pont_tx_data FROM ponto
				WHERE pont_tx_status = 'ativo'
					AND pont_tx_matricula = '".$aMotorista['enti_tx_matricula']."'
					AND STR_TO_DATE(pont_tx_data, '%Y-%m-%d %H:%i') = STR_TO_DATE('".($_POST["data"]." ".$_POST["hora"])."', '%Y-%m-%d %H:%i')
				ORDER BY pont_tx_data DESC
				LIMIT 1"
		));

		$data = $_POST["data"]." ".$_POST["hora"];

		if(!empty($temPonto["pont_tx_data"])){
			$seg = explode(":", $temPonto["pont_tx_data"])[2];
			$seg = intval($seg)+1;
			$data = $data.":".str_pad(strval($seg), 2, "0", STR_PAD_LEFT);
		}else{
			$data = $data.":00";
		}


		
		$newPonto = [
			'pont_nb_user' 			=> $_SESSION['user_nb_id'],
			'pont_tx_matricula' 	=> $aMotorista['enti_tx_matricula'],
			'pont_tx_data' 			=> $data,
			'pont_tx_tipo' 			=> $aTipo['macr_tx_codigoInterno'],
			'pont_tx_status' 		=> 'ativo',
			'pont_tx_dataCadastro' 	=> date("Y-m-d H:i:s"),
			'pont_nb_motivo' 		=> $_POST['motivo'],
			'pont_tx_justificativa' => $_POST['descricao']
		];
		
		inserir('ponto', array_keys($newPonto), array_values($newPonto));
		index();
		exit;
	}
	
	function status() {
		return  
			"<style>
				#statusDiv{
					display: inline-flex;
				}
				#status-label{
				margin-right: 10px; 
				
				}
				#status {
					margin-top: -5px;
					width: 93px;
				}
				</style>
				<div id='statusDiv'>
					<label id='status-label'>Status:</label>
					<select name='status' id='status' class='form-control input-sm' onchange='atualizar_form(".$_POST['id'].", null, \"".$_POST['data_de']."\",  \"".$_POST['data_ate']."\", this.value)'>
						<option value='ativo'>Ativos</option>
						<option value='inativo' ".((!empty($_POST['status']) && $_POST['status'] == 'inativo')? 'selected': '').">Inativos</option>
					</select>
				</div>"
		;
	}

	function pegarSqlDia(string $matricula, array $cols): string{
		$condicoesPontoBasicas = [
			"ponto.pont_tx_status = 'ativo'",
			"ponto.pont_tx_matricula = '".$matricula."'"
		];

		$abriuJornadaHoje = mysqli_fetch_assoc(
			query(
				"SELECT pont_tx_data FROM ponto
					WHERE ".implode(" AND ", $condicoesPontoBasicas)."
						AND ponto.pont_tx_tipo = 1
						AND ponto.pont_tx_data LIKE '%".$_POST['data']."%'
					ORDER BY ponto.pont_tx_data ASC
					LIMIT 1;"
			)
		);

		//Definir data de início da query{
			//Se abriu jornada hoje, considera a partir da data de abertura da jornada.

			if(empty($abriuJornadaHoje)){
				//Se não abriu uma jornada hoje, confere se há uma jornada aberta que veio de antes do dia.
				$temJornadaAberta = mysqli_fetch_assoc(
					query(
						"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 1) as temJornadaAberta FROM ponto
							WHERE ".implode(" AND ", $condicoesPontoBasicas)."
								AND ponto.pont_tx_tipo IN (1,2)
								AND pont_tx_data <= STR_TO_DATE('".$_POST['data']." 00:00:00', '%Y-%m-%d %H:%i:%s')
							ORDER BY pont_tx_data DESC
							LIMIT 1;"
					)
				);

				//Se não tem uma jornada que veio de antes, considera a partir de meia-noite de hoje.
				$sqlDataInicio = $_POST['data']." 00:00:00";

				if(!empty($temJornadaAberta) && intval($temJornadaAberta['temJornadaAberta'])){
					//Se tem uma jornada que veio de antes do dia, confere se esta foi fechada hoje.
					$jornadaFechadaHoje = mysqli_fetch_assoc(
						query(
							"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 2) as jornadaFechadaHoje FROM ponto
								WHERE ".implode(" AND ", $condicoesPontoBasicas)."
									AND ponto.pont_tx_tipo IN (1,2)
									AND pont_tx_data LIKE '%".$_POST['data']."%'
								ORDER BY pont_tx_data ASC
								LIMIT 1;"
						)
					);

					//Se a jornada aberta antes do dia não foi fechada, pega desde o momento em que a jornada foi aberta.
					$sqlDataInicio = $temJornadaAberta['pont_tx_data'];
					if(!empty($jornadaFechadaHoje) && intval($jornadaFechadaHoje['jornadaFechadaHoje'])){
						//Se a jornada aberta antes do dia foi fechada, deve considerar apenas após esse fechamento.
						$sqlDataInicio = $jornadaFechadaHoje['pont_tx_data'];
					}
				}
			}
		//}

		//Definir data de fim da query{
			$sqlDataFim = $_POST['data'].' 23:59:59';
			if(!empty($abriuJornadaHoje)){
				$sqlDataInicio = $abriuJornadaHoje["pont_tx_data"];
				//Se abriu jornada hoje, confere se teve uma jornada aberta que seguiu pros dias seguintes
				$deixouJornadaAberta = mysqli_fetch_assoc(
					query(
						"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 1) as deixouJornadaAberta FROM ponto
							WHERE ".implode(" AND ", $condicoesPontoBasicas)."
								AND ponto.pont_tx_tipo IN (1,2)
								AND pont_tx_data <= STR_TO_DATE('".$_POST['data']." 23:59:59', '%Y-%m-%d %H:%i:%s')
							ORDER BY pont_tx_data DESC
							LIMIT 1;"
					)
				);
				if(!empty($deixouJornadaAberta) && intval($deixouJornadaAberta['deixouJornadaAberta'])){
					//Se deixou uma jornada aberta pros dias seguintes, confere se ela terminou.
					$fimJornada = mysqli_fetch_assoc(
						query(
							"SELECT ponto.pont_tx_data, (ponto.pont_tx_tipo = 2) as fimJornada FROM ponto
								WHERE ".implode(" AND ", $condicoesPontoBasicas)."
									AND ponto.pont_tx_tipo IN (1,2)
									AND pont_tx_data > STR_TO_DATE('".$_POST['data']." 23:59:59', '%Y-%m-%d %H:%i:%s')
								ORDER BY pont_tx_data ASC
								LIMIT 1;"
						)
					);
					if(!empty($fimJornada) && intval($fimJornada['fimJornada'])){
						//Se a jornada deixada aberta já foi finalizada, pega até o fechamento dessa jornada deixada.
						$sqlDataFim = $fimJornada['pont_tx_data'];
					}
				}
			}
		//}

		$condicoesPontoBasicas[0] = "ponto.pont_tx_status = '".$_POST['status']."'";
		
		$sql = 
			"SELECT DISTINCT pont_nb_id, ".implode(",", $cols)." FROM ponto
				JOIN macroponto ON ponto.pont_tx_tipo = macroponto.macr_tx_codigoInterno
				JOIN user ON ponto.pont_nb_user = user.user_nb_id
				LEFT JOIN motivo ON ponto.pont_nb_motivo = motivo.moti_nb_id
				WHERE ".implode(" AND ", $condicoesPontoBasicas)."
					AND ponto.pont_tx_data >= STR_TO_DATE('".$sqlDataInicio."', '%Y-%m-%d %H:%i:%s')
					AND ponto.pont_tx_data <= STR_TO_DATE('".$sqlDataFim."', '%Y-%m-%d %H:%i:%s')
				ORDER BY pont_tx_data ASC"
		;

		return $sql;
	}

	function index(){
		global $CONTEX;
		if(empty($_POST['data'])){
			$_POST['data'] = date("Y-m-d");
		}

		if(empty($_POST['HTTP_REFERER'])){
			$_POST['HTTP_REFERER'] = $_SERVER["HTTP_REFERER"];
		}
		
		if(empty($_POST['id'])){
			echo '<script>alert("ERRO: Deve ser selecionado um motorista para ajustar.")</script>';
			
			$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/espelho_ponto.php";

			voltar();
			exit;
		}else{
			$a_mod['data'] = $_POST['data'];
			$a_mod['id'] = $_POST['id'];
		}

		cabecalho('Ajuste de Ponto');

		if(empty($_POST['data_de']) && !empty($_POST['data'])){
			$_POST['data_de'] = $_POST['data'];
		}
		if(empty($_POST['data_ate']) && !empty($_POST['data'])){
			$_POST['data_ate'] = $_POST['data'];
		}

		$aMotorista = carregar('entidade', $_POST['id']);

		$sqlCheck = query("SELECT user_tx_login, endo_tx_dataCadastro FROM endosso, user 
			WHERE '".$_POST['data']."' BETWEEN endo_tx_de AND endo_tx_ate
				AND endo_nb_entidade = '".$aMotorista['enti_nb_id']."'
				AND endo_tx_matricula = '".$aMotorista['enti_tx_matricula']."' 
				AND endo_tx_status = 'ativo' 
				AND endo_nb_userCadastro = user_nb_id 
			LIMIT 1"
		);
		$aEndosso = carrega_array($sqlCheck);

		$botao_imprimir =
			'<button class="btn default" type="button" onclick="imprimir()">Imprimir</button >
					<script>
						function imprimir() {
							// Abrir a caixa de diálogo de impressão
							window.print();
						}
					</script>';


		if (empty($_POST['status'])) {
			$_POST['status'] = 'ativo';
		}

		$textFields[] = texto('Matrícula',$aMotorista['enti_tx_matricula'],2);
		$textFields[] = texto('Motorista',$aMotorista['enti_tx_nome'],5);
		$textFields[] = texto('CPF',$aMotorista['enti_tx_cpf'],3);

		$_POST['status'] = (!empty($_POST['status']) && $_POST['status'] != 'undefined'? $_POST['status']: 'ativo');

		$variableFields = [];
		$campoJust = [];

		if(!empty($aEndosso) && count($aEndosso) > 0){
			$variableFields[] = texto('Endosso',"Endossado por ".$aEndosso['user_tx_login']." em ".data($aEndosso['endo_tx_dataCadastro'],1),6);
		}else{
			$_POST["busca_data"] = substr($_POST['data'],0, -3);
			$botoes[] = botao(
				'Gravar',
				'cadastrarAjuste'
			);
      		$parametros = [
				"pont_nb_id",
				"excluir_ponto",
				"idEntidade",
				$_POST['data_de'],
				$_POST['data_ate'],
				$_POST["id"]
			];

			$iconeExcluir = "icone_excluir_ajuste(".implode(",", $parametros).")"; //Utilizado em grid()
			$variableFields[] = campo_data('Data', 'data', ($_POST['data']?? ""), 2, "onfocusout='atualizar_form(".$_POST['id'].", this.value, \"".$_POST['data_de']."\", \"".$_POST['data_ate']."\")', null");
			$variableFields[] = campo_hora('Hora','hora',($_POST['hora']?? ""),2);
			$variableFields[] = combo_bd('Tipo de Registro','idMacro',($_POST['idMacro']?? ""),4,"macroponto","","ORDER BY macr_nb_id");
			$variableFields[] = combo_bd('Motivo','motivo',($_POST['motivo']?? ""),4,'motivo','',' AND moti_tx_tipo = "Ajuste" ORDER BY moti_tx_nome');
	
			$campoJust[] = textarea('Justificativa','descricao',($_POST['descricao']?? ""),12);
		}

		$botoes[] = $botao_imprimir;
		$botoes[] = botao("Voltar", "voltar");
		
		$botoes[] = status();


		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], "ajuste_ponto.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/espelho_ponto.php";
			}
		}
		
		abre_form('Dados do Ajuste de Ponto');
		linha_form($textFields);
		echo campo_hidden("id", $_POST["id"]);
		echo campo_hidden("busca_motorista", $_POST["id"]);
		echo campo_hidden("busca_data", $_POST["data"]);
		echo campo_hidden("data_de", $_POST["data_de"]);
		echo campo_hidden("data_ate", $_POST["data_ate"]);
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		linha_form($variableFields);
		linha_form($campoJust);
		fecha_form($botoes);

		$sql = pegarSqlDia($aMotorista['enti_tx_matricula'], ["pont_nb_id", "pont_tx_data", "macr_tx_nome", "moti_tx_nome", 
		"moti_tx_legenda", "pont_tx_justificativa", "user_tx_login", "pont_tx_dataCadastro",
		"pont_tx_latitude", "pont_tx_longitude"]);


		$gridFields = [
			"CÓD"												=> "pont_nb_id",
			"DATA"												=> "data(pont_tx_data, 1)",
			"PLACA"                                             => "pont_tx_placa",
			"TIPO"												=> "macr_tx_nome",
			"MOTIVO"											=> "moti_tx_nome",
			"LEGENDA"											=> "moti_tx_legenda",
			"JUSTIFICATIVA"										=> "pont_tx_justificativa",
			"USUÁRIO"											=> "user_tx_login",
			"DATA CADASTRO"										=> "data(pont_tx_dataCadastro,1)",
			"LOCALIZAÇÃO"                                       => "map(pont_nb_id)",
			"<spam class='glyphicon glyphicon-remove'></spam>"	=> $iconeExcluir
		];
		
		grid($sql, array_keys($gridFields), array_values($gridFields), '', '12', 1, "desc", -1);

		echo
			"
			<div id='tituloRelatorio'>
				<img id='logo' style='width: 150px' src='$CONTEX[path]/imagens/logo_topo_cliente.png' alt='Logo Empresa Direita'>
			</div>
			<style>
				@media print {
					body {
						margin: 1cm;
						margin-right: 0cm; /* Ajuste o valor conforme necessário para afastar do lado direito */
						transform: scale(1.0);
						transform-origin: top left;
					}
					#tituloRelatorio{
						display: flex !important;
						position: absolute;
						top: 5px;
						right: 20px;
					}
					div:nth-child(10) > div:nth-child(1),
					div:nth-child(10) > div:nth-child(3),
					div:nth-child(10) > div:nth-child(5),
					div:nth-child(10) > div:nth-child(6),
					div:nth-child(11) > div{
						display: none;
					}

					.portlet.light {
						padding: 0px 20px 0px !important;
					}
					.row {
						margin: 0px 0px 0px 0px !important;
					}

					form > div:nth-child(1){
						display: flex;
						flex-wrap: wrap;
					}
					.col-sm-2,
					.col-sm-5,
					.col-sm-3 {
						width: 40% !important;
						padding-left: 0px;
					}
				}
				#tituloRelatorio{
					display: none;
				}
			</style>
			<form name='form_ajuste_status' action='".$_SERVER['HTTP_ORIGIN'].$CONTEX['path']."/ajuste_ponto.php' method='post'>
				<input type='hidden' name='acao' value='index'>
				<input type='hidden' name='id'>
				<input type='hidden' name='busca_motorista'>
				<input type='hidden' name='data'>
				<input type='hidden' name='busca_data'>
				<input type='hidden' name='data_de'>
				<input type='hidden' name='data_ate'>
				<input type='hidden' name='status'>
				<input type='hidden' name='HTTP_REFERER'>
			</form>
			<script>
				valorDataInicial = document.getElementById('data').value;
				valorStatusInicial = document.getElementById('status').value;
				function atualizar_form(motorista, data, data_de, data_ate, status) {
					if(data == null){
						data = document.getElementById('data').value;
					}
					if(status == null){
						status = document.getElementById('status').value;
					}

					if(valorDataInicial != data || valorStatusInicial != status){
						document.form_ajuste_status.id.value = motorista;
						document.form_ajuste_status.data.value = data;
						document.form_ajuste_status.data_de.value = data_de;
						document.form_ajuste_status.data_ate.value = data_ate;
						document.form_ajuste_status.status.value = status;
						document.form_ajuste_status.HTTP_REFERER.value = '".$_POST['HTTP_REFERER']."';
						document.getElementById('status').value = status;
						document.form_ajuste_status.submit();
					}
				}
			</script>"
		;

		rodape();
	}