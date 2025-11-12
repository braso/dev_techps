<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Expires: 01 Jan 2001 00:00:00 GMT");
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
	//*/

	
	include "conecta.php";


	function carregarJSFormParametro(){
		global $a_mod;

		$camposHora = [
			campo("Inicio", "horaInicio", "", 1, "MASCARA_HORA", "maxlength='4'"),
			campo("Fim", "horaFim", "", 1, "MASCARA_HORA", "maxlength='4'"),
			campo("Intervalos", "intervaloInterno", "", 1, "MASCARA_HORA", "maxlength='4'"),
		];

		echo 
			"<script>
				camposHora = ".json_encode($camposHora).";
				diasEscala = ".json_encode($a_mod["diasEscala"]?? []).";

				semana = ['(Dom)', '(Seg)', '(Ter)', '(Qua)', '(Qui)', '(Sex)', '(Sab)'];

				function atualizarDiaSemana(){
					qtdLinhas = document.getElementById('periodicidade').value;

					dataSelecionada = document.getElementById('dataInicio').value.split('-');
					dataSelecionada = new Date(dataSelecionada[0], parseInt(dataSelecionada[1])-1, dataSelecionada[2]);
					if(qtdLinhas == 7){
						semanaInvertida = (semana.slice(dataSelecionada.getDay())).concat(semana.slice(0, dataSelecionada.getDay()));
						console.log(semanaInvertida);
					}else{
						semanaInvertida = ['', '', '', '', '', '', ''];
					}
					
					for(f = 0; f < document.getElementsByClassName('diaSemana').length && f < 7; f++){
						document.getElementsByClassName('diaSemana')[f].innerHTML = semanaInvertida[f];
					}
					
				}

				function atualizarLinhasEscala(qtdLinhas){
					if(document.getElementsByClassName('linhaDiaEscala').length > qtdLinhas){
						for(i = document.getElementsByClassName('linhaDiaEscala').length; i > qtdLinhas ; i--){
							document.getElementById('linhaDiaEscala_'+i).remove();
						}
					}

					for(i = 1; i <= qtdLinhas; i++){
						if(document.getElementById('linhaDiaEscala_'+i) != null){
							continue;
						}
						
						var linha = document.createElement('div');
						linha.className = 'linhaDiaEscala';
						linha.id = 'linhaDiaEscala_'+i;
						linha.innerHTML = '<div class=\"labelDiaEscala\">Dia '+i+'<div class=\"diaSemana\"></div></div>'+camposHora[0] + camposHora[1] + camposHora[2];
						document.getElementById('divHorasEscala').innerHTML += linha.outerHTML;
					}
					
					atualizarDiaSemana();

					linhasDiaEscala = document.getElementsByClassName('linhaDiaEscala');
					for(i = 1; i <= linhasDiaEscala.length; i++){
						linhasDiaEscala[i-1].getElementsByTagName('input')[0].name = 'horaInicio_'+i;
						linhasDiaEscala[i-1].getElementsByTagName('input')[1].name = 'horaFim_'+i;
						linhasDiaEscala[i-1].getElementsByTagName('input')[2].name = 'intervaloInterno_'+i;

						if(diasEscala[i-1] != undefined){
							linhasDiaEscala[i-1].getElementsByTagName('input')[0].value = diasEscala[i-1].esca_tx_horaInicio ?? diasEscala[i-1][0] ?? '';
							linhasDiaEscala[i-1].getElementsByTagName('input')[1].value = diasEscala[i-1].esca_tx_horaFim ?? diasEscala[i-1][1] ?? '';
							linhasDiaEscala[i-1].getElementsByTagName('input')[2].value = diasEscala[i-1].esca_tx_intervaloInterno ?? diasEscala[i-1][2] ?? '';
						}
					}
				}

				function camposEscala(campoSelecionado){
					tipo = (campoSelecionado == 'escala')+(campoSelecionado == 'horas_por_dia')*2;
					
					if(campoSelecionado == 'escala'){
						atualizarLinhasEscala(document.getElementById('periodicidade').value);
					}else if(campoSelecionado == 'horas_por_dia'){
						document.getElementById('divHorasEscala').innerHTML = '';
					}

					document.getElementById('periodicidade').parentElement.hidden 	= !(tipo == 1);
					document.getElementById('dataInicio').parentElement.hidden 		= !(tipo == 1);
					document.getElementsByName('divJornada')[0].hidden 	= !(tipo == 2);
				}

				".(empty($a_mod["para_tx_tipo"])? "camposEscala('horas_por_dia')": "camposEscala('{$a_mod["para_tx_tipo"]}')").";

				function remover_arquivo(id, idArq, arquivo, acao ){
					if (confirm('Deseja realmente excluir o arquivo '+arquivo+'?')){
						document.form_excluir_arquivo.idParametro.value = id;
						document.form_excluir_arquivo.idArq.value = idArq;
						document.form_excluir_arquivo.acao.value = acao;
						document.form_excluir_arquivo.submit();
					}
				}

				function downloadArquivo(id, caminho, acao){
					document.form_download_arquivo.idParametro.value = id;
					document.form_download_arquivo.caminho.value = caminho;
					document.form_download_arquivo.acao.value = acao;
					document.form_download_arquivo.submit();
				}
			</script>"
		;
	}
	
	function excluirParametro(){
		$usuariosParametro = mysqli_fetch_all(query(
			"SELECT enti_tx_nome FROM parametro"
				." JOIN entidade ON para_nb_id = enti_nb_parametro"
				." WHERE para_nb_id = ".$_POST["id"].";"
		), MYSQLI_ASSOC);

		if(empty($usuariosParametro)){
			inactivateById("parametro", $_POST["id"]);
		}else{
			$errorMsg = [];
			foreach($usuariosParametro as $usuarioParametro){
				$errorMsg[] = $usuarioParametro["enti_tx_nome"];
			}

			set_status("ERRO: Existem motoristas vinculados a esse parâmetro.<br><div style='text-align:left;'><br>- ".implode(",<br>- ", $errorMsg)."</div>");
		}
		index();
		exit;
	}

	function enviarDocumento() {
		global $a_mod;

		if(empty($a_mod)){
			$a_mod = carregar("parametro", $_POST["idRelacionado"]);
		}

		$errorMsg = "";
		if (isset($_POST["tipo_documento"]) && !empty($_POST["tipo_documento"])) {
			$obgVencimento = mysqli_fetch_all(query("
				SELECT tipo_tx_vencimento 
				FROM tipos_documentos 
				WHERE tipo_nb_id = " . intval($_POST["tipo_documento"])
			), MYSQLI_ASSOC);

			if (!empty($obgVencimento) && $obgVencimento[0]['tipo_tx_vencimento'] === 'sim') {
				if (empty($_POST['data_vencimento'])) {
					$errorMsg = "Campo obrigatório não preenchido: Data de Vencimento";
				}
			}
		}

		if(!empty($errorMsg)){
			set_status("ERRO: ".$errorMsg);
			$_POST["id"] = $_POST["idParametro"];
			modificarParametro();
			exit;
		}


		$novoParametro = [
			"para_nb_id" => (int) $_POST["idRelacionado"],
			"docu_tx_nome" => $_POST["file-name"] ?? '',
			"docu_tx_descricao" => $_POST["description-text"] ?? '',
			"docu_tx_dataCadastro" => date("Y-m-d H:i:s"),
			"docu_tx_datavencimento" => $_POST["data_vencimento"] ?? null,
			"docu_tx_tipo" => $_POST["tipo_documento"] ?? '',
			"docu_nb_sbgrupo" => (int) $_POST["sub-setor"] ?? null,
			"docu_tx_usuarioCadastro" => (int) $_POST["idUserCadastro"],
			"docu_tx_assinado" => "nao",
			"docu_tx_visivel" => $_POST["visibilidade"] ?? 'nao'
		];
		
		$arquivo = $_FILES["file"] ?? null;
		if (!$arquivo || $arquivo["error"] !== UPLOAD_ERR_OK) {
			set_status("Erro no upload do arquivo.");
			visualizarCadastro();
			exit;
		}

		// Tipos de arquivo permitidos
		$formatos = [
			"image/jpeg" => "jpg",
			"image/png" => "png",
			"application/msword" => "doc",
			"application/vnd.openxmlformats-officedocument.wordprocessingml.document" => "docx",
			"application/pdf" => "pdf"
		];

		// Valida tipo real do arquivo (mais seguro que apenas $_FILES["type"])
		$tipo = mime_content_type($arquivo["tmp_name"]);
		if (!array_key_exists($tipo, $formatos)) {
			set_status("Tipo de arquivo não permitido.");
			visualizarCadastro();
			exit;
		}

		// Usa o nome original do arquivo (mas sanitiza para evitar caracteres perigosos)
		$nomeOriginal = basename($arquivo["name"]); // remove possíveis caminhos
		$nomeSeguro = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '_', $nomeOriginal); // mantém letras, números, espaço, ponto, traço e underscore

		$pasta_funcionario = "arquivos/parametro/" . $novoParametro["para_nb_id"] . "/";
		if (!is_dir($pasta_funcionario)) {
			mkdir($pasta_funcionario, 0777, true);
		}

		// Caminho físico usa o nome original (sanitizado)
		$novoParametro["docu_tx_caminho"] = $pasta_funcionario . $nomeSeguro;

		// Se for PDF, verifica assinatura
		if ($tipo === "application/pdf" && function_exists("temAssinaturaRapido")) {
			if (temAssinaturaRapido($arquivo["tmp_name"])) {
				$novoParametro["docu_tx_assinado"] = "sim";
			}
		}

		// Evita sobrescrever arquivos já existentes
		if (file_exists($novoParametro["docu_tx_caminho"])) {
			$info = pathinfo($nomeSeguro);
			$base = $info["filename"];
			$ext = isset($info["extension"]) ? '.' . $info["extension"] : '';
			$nomeSeguro = $base . '_' . time() . $ext;
			$novoParametro["docu_tx_caminho"] = $pasta_funcionario . $nomeSeguro;
		}

		// Move o arquivo e salva no banco
		if (move_uploaded_file($arquivo["tmp_name"], $novoParametro["docu_tx_caminho"])) {
			inserir("documento_parametro", array_keys($novoParametro), array_values($novoParametro));
			set_status("Registro inserido com sucesso.");
		} else {
			set_status("Falha ao mover o arquivo para o diretório de destino.");
		}

		set_status("Registro inserido com sucesso.");
		$_POST["id"] = $novoParametro["para_nb_id"];
		modificarParametro();
		exit;
	}

	function excluir_documento(){
		query("DELETE FROM documento_parametro WHERE docu_nb_id = {$_POST["idArq"]}");
		$_POST["id"] = $_POST["idParametro"];
		modificarParametro();
		exit;
	}

	function cadastrarEscala($idParametro){
		$escalaExistente = mysqli_fetch_assoc(query(
			"SELECT * FROM escala WHERE esca_nb_parametro = {$idParametro};"
		));

		$novaEscala = [
			"esca_nb_parametro" => $idParametro,
			"esca_tx_dataInicio" => $_POST["dataInicio"],
			"esca_nb_periodicidade" => $_POST["periodicidade"]
		];

		if(!empty($escalaExistente)){
			$diasEscala = mysqli_fetch_all(query(
				"SELECT * FROM escala_dia WHERE esca_nb_escala = {$escalaExistente["esca_nb_id"]};"
			), MYSQLI_ASSOC);
			
			if(count($_POST["diasEscala"]) > count($diasEscala)){ //Inserir os dias que ainda não existem
				for($f = count($diasEscala); $f < count($_POST["diasEscala"]); $f++){
					$diaEscala = [
						"esca_nb_escala" 			=> $escalaExistente["esca_nb_id"],
						"esca_nb_numeroDia" 		=> $f+1,
						"esca_tx_horaInicio" 		=> !empty($_POST["diasEscala"][$f][0])? $_POST["diasEscala"][$f][0]: NULL,
						"esca_tx_horaFim" 			=> !empty($_POST["diasEscala"][$f][1])? $_POST["diasEscala"][$f][1]: NULL,
						"esca_tx_intervaloInterno" 	=> !empty($_POST["diasEscala"][$f][2])? $_POST["diasEscala"][$f][2]: NULL,
					];
					
					$idDia = inserir("escala_dia", array_keys($diaEscala), array_values($diaEscala));

					$diaEscala["esca_nb_id"] = $idDia[0];
					$diasEscala[] = $diaEscala;
				}
			}elseif(count($_POST["diasEscala"]) < count($diasEscala)){// Remover os dias que não serão mais utilizados
				$primDiaExcluir = count($_POST["diasEscala"])+1;
				for($f = $primDiaExcluir; $f <= count($diasEscala); $f++){
					query(
						"DELETE FROM escala_dia 
							WHERE esca_nb_numeroDia = {$f} 
								AND esca_nb_escala = {$escalaExistente["esca_nb_id"]};"
					);
				}
			}


			atualizar("escala", array_keys($novaEscala), array_values($novaEscala), $escalaExistente["esca_nb_id"]);

			for($f = 0; $f < count($_POST["diasEscala"]); $f++){
				$diaEscala = [
					"esca_nb_escala" => $escalaExistente["esca_nb_id"],
					"esca_nb_numeroDia" => strval($f+1),
					"esca_tx_horaInicio" => !empty($_POST["diasEscala"][$f][0])? $_POST["diasEscala"][$f][0]: NULL,
					"esca_tx_horaFim" => !empty($_POST["diasEscala"][$f][1])? $_POST["diasEscala"][$f][1]: NULL,
					"esca_tx_intervaloInterno" => !empty($_POST["diasEscala"][$f][2])? $_POST["diasEscala"][$f][2]: NULL,
				];

				atualizar("escala_dia", array_keys($diaEscala), array_values($diaEscala), $diasEscala[$f]["esca_nb_id"]);
			}
		}else{
			$idEscala = inserir("escala", array_keys($novaEscala), array_values($novaEscala));
			
			$f = 1;
			foreach($_POST["diasEscala"] as $dia){
				$diaEscala = [
					"esca_nb_escala" => $idEscala[0],
					"esca_nb_numeroDia" => strval($f++),
					"esca_tx_horaInicio" => !empty($dia[0])? $dia[0]: NULL,
					"esca_tx_horaFim" => !empty($dia[1])? $dia[1]: NULL,
					"esca_tx_intervaloInterno" => !empty($dia[2])? $dia[2]: NULL,
				];
				inserir("escala_dia", array_keys($diaEscala), array_values($diaEscala));
			}
		}
	}

	function modificarParametro(){
		global $a_mod;
		$a_mod = carregar("parametro", $_POST["id"]);
		mostrarFormParametro();
		exit;
	}

	function cadastrarParametro(){

		//Conferir se os campos obrigatórios estão preenchidos{
			$camposObrig = [];
			$camposObrig = array_merge($camposObrig, [
				"nome" => "Nome",
				"tipo" => "Tipo",
				// "tolerancia" => "Tolerância de atraso (Minutos)",
				"percHESemanal" => "Hora Extra Semanal",
				"percHEEx" => "Hora Extra Extraordinária",
				// "maxHESemanalDiario" => "Máx. de 'H.E. Semanal' por dia"
			]);

			if(!empty($_POST["acordo"]) && $_POST["acordo"] == "sim"){
				$camposObrig["inicioAcordo"] = "Início do Acordo";
				$camposObrig["fimAcordo"] = "Fim do Acordo";
			}
	
			if(!empty($_POST["banco"]) && $_POST["banco"] == "sim"){
				$camposObrig["quandDias"] = "Quantidade de Dias";
				$camposObrig["quandHoras"] = "Quantidade de Horas Limite";
			}

			if(!empty($_POST["tipo"])){
				if($_POST["tipo"] == "escala"){
					$camposObrig["periodicidade"] = "Periodicidade (em dias)";
					$camposObrig["dataInicio"] = "Dia 1";
					$camposObrig["diasEscala"] = "Horários de trabalho";

					$diasEscala = [];
					for($f = 1; $f <= intval($_POST["periodicidade"]); $f++){
						$diasEscala[] = [$_POST["horaInicio_".$f], $_POST["horaFim_".$f], $_POST["intervaloInterno_".$f]];
					}
					$_POST["diasEscala"] = $diasEscala;
				}elseif($_POST["tipo"] == "horas_por_dia"){
					$camposObrig["jornadaSemanal"] = "Dias Úteis (Hr/dia)";
					$camposObrig["jornadaSabado"] = "Sábado";
				}
			}

			
			$errorMsg = conferirCamposObrig($camposObrig, $_POST);
			if(empty($_POST["tolerancia"]) && $_POST["tolerancia"] != "0"){
				$_POST["errorFields"][] = "tolerancia";
			}
			if(empty($_POST["maxHESemanalDiario"]) && $_POST["maxHESemanalDiario"] != "00:00"){
				$_POST["errorFields"][] = "maxHESemanalDiario";
			}

			
			$_POST["adi5322"] = (empty($_POST["adi5322_sim"]))? "nao": "sim";
			unset($_POST["adi5322_sim"]);
			
			if(!empty($errorMsg)){
				set_status("ERRO: ".$errorMsg);
				mostrarFormParametro();
				exit;
			}
			unset($camposObrig);
		//}

		//Conferir se as porcentagens de hora extra estão dentro do válido{
			// De acordo com a Lei n° 5452, artigo 59, parágrafo 1°
			if(intval($_POST["percHESemanal"]) < 50){
				$_POST["errorFields"][] = "percHESemanal";
				$errorMsg = "ERRO: O valor mínimo de Hora Extra Semanal é 50%.";
			}
			if(intval($_POST["percHEEx"]) < 50){
				$_POST["errorFields"][] = "percHEEx";
				$errorMsg = "ERRO: O valor mínimo de Hora Extra Extraodinária é 50%.";
			}
			if(!empty($errorMsg)){
				set_status($errorMsg);
				mostrarFormParametro();
				exit;
			}
		//}

		if(is_int(strpos(implode(",",array_keys($_POST)), "ignorarCampos"))){
			$campos = ["descanso", "espera", "repouso", "repousoEmbarcado", "mdc"];
			$_POST["ignorarCampos"] = [];
			foreach($campos as $campo){
				if(isset($_POST["ignorarCampos_".$campo])){
					$_POST["ignorarCampos"][] = $campo;
				}
			}
			$_POST["ignorarCampos"] = implode(",",$_POST["ignorarCampos"]);
		}else{
			$_POST["ignorarCampos"] = null;
		}

		$novoParametro = [
			"para_tx_tipo" 						=> $_POST["tipo"],
			"para_tx_nome" 						=> $_POST["nome"],
			"para_tx_jornadaSemanal" 			=> $_POST["jornadaSemanal"],
			"para_tx_jornadaSabado" 			=> $_POST["jornadaSabado"],
			"para_tx_jornadaDomingoFacultativo" => (!empty($_POST["jornadaDomingoFacultativo"])? $_POST["jornadaDomingoFacultativo"]: null),
			"para_tx_percHESemanal" 			=> $_POST["percHESemanal"],
			"para_tx_percHEEx" 					=> $_POST["percHEEx"],
			"para_tx_maxHESemanalDiario" 		=> $_POST["maxHESemanalDiario"],
			"para_tx_pagarHEExComPerNeg"    	=> $_POST["pagarHEExComPerNeg"],
			"para_tx_tolerancia" 				=> $_POST["tolerancia"],
			"para_tx_descFaltas" 				=> $_POST["descFaltas"],
			"para_tx_acordo" 					=> $_POST["acordo"],
			"para_tx_inicioAcordo" 				=> $_POST["inicioAcordo"],
			"para_tx_fimAcordo" 				=> $_POST["fimAcordo"],
			"para_tx_diariasCafe" 				=> $_POST["diariasCafe"],
			"para_tx_diariasAlmoco" 			=> $_POST["diariasAlmoco"],
			"para_tx_diariasJanta" 				=> $_POST["diariasJanta"],
			"para_tx_banco" 					=> $_POST["banco"],
			"para_nb_qDias" 					=> $_POST["quandDias"],
			"para_tx_horasLimite" 				=> $_POST["quandHoras"],
			"para_tx_Obs" 						=> $_POST["Obs"],
			"para_tx_adi5322" 					=> $_POST["adi5322"],
			"para_tx_status" 					=> "ativo",
		];

		if(!empty($_POST["ignorarCampos"]) || $_POST["ignorarCampos"] == null){
			$novoParametro["para_tx_ignorarCampos"] = $_POST["ignorarCampos"];
		}

		if(!empty($_POST["banco"]) && $_POST["banco"] == "nao"){
			unset($novoParametro["para_nb_qDias"]);
			unset($novoParametro["para_tx_horasLimite"]);
		}

		if(!empty($_POST["acordo"]) && $_POST["acordo"] == "nao"){
			unset($novoParametro["para_tx_inicioAcordo"]);
			unset($novoParametro["para_tx_fimAcordo"]);
		}
		

		
		if(!empty($_POST["id"])){ //Se está editando
			$novoParametro["para_nb_userAtualiza"] = $_SESSION["user_nb_id"];
			$novoParametro["para_tx_dataAtualiza"] = date("Y-m-d H:i:s");

			if($novoParametro["para_tx_tipo"] == "escala"){
				$novoParametro["para_tx_jornadaSemanal"] = NULL;
				$novoParametro["para_tx_jornadaSabado"] = NULL;
			}

			atualizar("parametro", array_keys($novoParametro),array_values($novoParametro),$_POST["id"]);
			
			if($novoParametro["para_tx_tipo"] == "escala"){
				cadastrarEscala($_POST["id"]);
			}else{
				query("DELETE FROM escala WHERE esca_nb_parametro = {$_POST["id"]};");
			}
			
			
			$motoristasNoParametro = mysqli_fetch_all(query(
				"SELECT * FROM entidade 
					WHERE enti_tx_status = 'ativo'
						AND enti_nb_parametro = '{$_POST["id"]}'"
			), MYSQLI_ASSOC);
			
			$aParametro = carregar("parametro", $_POST["id"]);

			foreach($motoristasNoParametro as $motorista){
				// Se o motorista estava dentro do padrão do parâmetro antes da atualização, atualiza os parâmetros do motorista junto.
				if($aParametro["para_nb_id"] == $motorista["enti_nb_parametro"]){
					atualizar(
						"entidade",
						["enti_tx_jornadaSemanal", "enti_tx_jornadaSabado", "enti_tx_percHESemanal", "enti_tx_percHEEx"],
						[$_POST["jornadaSemanal"], $_POST["jornadaSabado"], $_POST["percHESemanal"], $_POST["percHEEx"]],
						$motorista["enti_nb_id"]
					);
				}
			}
			set_status("Registro atualizado com sucesso.");
			
		} else {
			$novoParametro["para_tx_dataCadastro"] = date("Y-m-d");
			$novoParametro["para_nb_userCadastro"] = $_SESSION["user_nb_id"];

			
			$id = inserir("parametro", array_keys($novoParametro), array_values($novoParametro));

			$_POST["id"] = $id[0];
			
			if($novoParametro["para_tx_tipo"] == "escala"){
				cadastrarEscala($id[0]);
			}

			set_status("Registro inserido com sucesso.");
		}

		mostrarFormParametro();
		exit;
	}

	function mostrarFormParametro(){
		global $a_mod;

		if(empty($a_mod) && !empty($_POST)){
			if(!empty($_POST["id"])){
				$a_mod = carregar("parametro", $_POST["id"]);
			}
			$campos = [
				"tipo", 
				"nome", "tolerancia", "jornadaSemanal", "jornadaSabado",
				"percHESemanal", "percHEEx", "maxHESemanalDiario", "diariasCafe", "diariasAlmoco", "diariasJanta",
				"acordo", "inicioAcordo", "fimAcordo", "banco", "adi5322", "Obs"
			];
			foreach($campos as $campo){
				if(!empty($_POST[$campo])){
					$a_mod["para_tx_".$campo] = $_POST[$campo];
				}
			}

			$a_mod["esca_nb_periodicidade"] = $_POST["periodicidade"]?? null;
			$a_mod["esca_tx_dataInicio"] = $_POST["dataInicio"]?? null;

			if(!empty($a_mod["para_tx_ignorarCampos"])){
				foreach(explode(",", $a_mod["para_tx_ignorarCampos"]) as $campo){
					$_POST["ignorarCampos_".$campo] = true;
				}
			}
			if(!empty($_POST["quandDias"])){
				$a_mod["para_nb_qDias"] = $_POST["quandDias"];
			}
			if(!empty($_POST["quandHoras"])){
				$a_mod["para_tx_horasLimite"] = $_POST["quandHoras"];
			}
			
			unset($campos);
		}
		
		if(!empty($a_mod["para_tx_tipo"]) && $a_mod["para_tx_tipo"] == "escala"){
			if(!empty($_POST["periodicidade"])){
				$a_mod["esca_nb_periodicidade"] = $_POST["periodicidade"];
				$a_mod["esca_tx_dataInicio"] = $_POST["dataInicio"];
				$a_mod["diasEscala"] = $_POST["diasEscala"];
			}else{
				if(!empty($_POST["id"])){
					$escalaExistente = mysqli_fetch_assoc(query(
						"SELECT * FROM escala WHERE esca_nb_parametro = {$_POST["id"]};"
					));

					$escalaExistente["diasEscala"] = mysqli_fetch_all(query(
						"SELECT * FROM escala_dia WHERE esca_nb_escala = {$escalaExistente["esca_nb_id"]};"
					), MYSQLI_ASSOC);

					$diasEscala = [];
					foreach($escalaExistente["diasEscala"] as $diaEscala){
						$diasEscala[] = [$diaEscala["esca_tx_horaInicio"], $diaEscala["esca_tx_horaFim"], $diaEscala["esca_tx_intervaloInterno"]];
					}
					$a_mod = array_merge($a_mod, $escalaExistente);
					$a_mod["diasEscala"] = $diasEscala;	
				}
			}
		}

		cabecalho("Cadastro de Parâmetros");

		$aparecerDomingoFacultativo = (
			$_ENV["URL_BASE"] == "http://localhost:8000" 
			|| $_ENV["APP_PATH"] == "/dev"
			|| ($_ENV["APP_PATH"] == "/gestaodeponto" && $_ENV["CONTEX_PATH"] == "/opafrutas")
		);
		$campoDomingoFacultativo = $aparecerDomingoFacultativo? campo_hora("Domingo/Feriado (facultativo)", "jornadaDomingoFacultativo", ($a_mod["para_tx_jornadaDomingoFacultativo"]?? ""), 2): "";

		$campos = [
			[
				"<div class='col-sm-12 margin-bottom-5 campo-fit-content' style='min-height: 50px;' id='tipo'>
					<div class='margin-bottom-5'>
						Tipo
					</div>"
					."<label>
						<input type='radio' name='tipo' value='escala' ".((!empty($a_mod["para_tx_tipo"]) && $a_mod["para_tx_tipo"] == "escala")? "checked": "")." onchange='camposEscala(\"escala\")'>
						Escala
					</label>"
					."<label>
						<input type='radio' name='tipo' value='horas_por_dia' ".((empty($a_mod["para_tx_tipo"]) || $a_mod["para_tx_tipo"] == "horas_por_dia")? "checked": "")." onchange='camposEscala(\"horas_por_dia\")'>
						Horas/Dia
					</label>
				</div>",
				"<div style='overflow: hidden;width: 100%;'>"
					.campo("Nome*", "nome", ($a_mod["para_tx_nome"]?? ""), 5)
					.campo("Tolerância de atraso (Minutos)*", "tolerancia", ($a_mod["para_tx_tolerancia"]?? ""), 2,"MASCARA_NUMERO", "maxlength='3'")
				."</div>",
				"<div name='divJornada' style='margin-left: 15px; margin-right: 15px; width: fit-content; overflow: hidden;'>"
					."<div style='font-weight: bold;'>Jornada</div>"
					.campo_hora("Dias Úteis (Hr/dia)*", "jornadaSemanal", ($a_mod["para_tx_jornadaSemanal"]?? ""), 2)
					.campo_hora("Sábado*", "jornadaSabado", ($a_mod["para_tx_jornadaSabado"]?? ""), 2)
					.$campoDomingoFacultativo
				."</div>",
				// campo("Periodicidade (em dias)*", "periodicidade", ($a_mod["esca_nb_periodicidade"]?? "1"), 2, "MASCARA_NUMERO", "max='31' min='1' onchange='atualizarLinhasEscala(this.value)"),
				"<div class='col-sm-2 margin-bottom-5 campo-fit-content'>
					<label>Periodicidade (em dias)*</label>
					<input name='periodicidade' id='periodicidade' value='".($a_mod["esca_nb_periodicidade"]?? "1")."' autocomplete='off' type='number' class='form-control input-sm campo-fit-content ".((!empty($_POST["errorFields"]) && in_array("periodicidade", $_POST["errorFields"]))? "error-field": "")."' max='31' min='1' onchange='atualizarLinhasEscala(this.value)'>
				</div>",
				campo_data("Dia 1*", "dataInicio", ($a_mod["esca_tx_dataInicio"]?? ""), 2, "onchange='atualizarDiaSemana()'"),
				"<div id='divHorasEscala' class='col-sm-2 margin-bottom-5 campo-fit-content'></div>"
			],
			[
				campo("Hora Extra Semanal (%)*", "percHESemanal", ($a_mod["para_tx_percHESemanal"]?? ""), 3, "MASCARA_NUMERO", "maxlength='3'"),
				campo("Hora Extra Extraordinária (%)*", "percHEEx", ($a_mod["para_tx_percHEEx"]?? ""), 3, "MASCARA_NUMERO"),
				campo_hora("Máx. de \"H.E. Semanal\" por dia*", "maxHESemanalDiario", ($a_mod["para_tx_maxHESemanalDiario"]?? "02:00"), 3),
				combo("Pagar H.E. Ex. mesmo com Período Neg.*", "pagarHEExComPerNeg", ($a_mod["para_tx_pagarHEExComPerNeg"]?? "sim"), 3, ["sim" => "Sim", "nao" => "Não"]),
				"<div class='col-sm-3 margin-bottom-5 campo-fit-content ".(!empty($_POST["errorFields"]) && in_array("descFaltas", $_POST["errorFields"]))."' style='min-width:fit-content; min-height: 50px;'>
					<label>Descontar horas por faltas não justificadas?</label><br>
					<label class='radio-inline'>
						<input type='radio' name='descFaltas' value='sim' ".((!empty($a_mod["para_tx_descFaltas"]) && $a_mod["para_tx_descFaltas"] == "sim")? "checked": "")."> Sim
					</label>
					<label class='radio-inline'>
						<input type='radio' name='descFaltas' value='nao' ".(empty($a_mod["para_tx_descFaltas"])? "checked": ((!empty($a_mod["para_tx_descFaltas"]) && $a_mod["para_tx_descFaltas"] == "nao")? "checked": ""))."> Não
					</label>
				</div>"
			],
			[
				campo("Café da Manhã", "diariasCafe", ($a_mod["para_tx_diariasCafe"]?? ""), 2, "MASCARA_DINHEIRO"),
				campo("Almoço", "diariasAlmoco", ($a_mod["para_tx_diariasAlmoco"]?? ""), 2, "MASCARA_DINHEIRO"),
				campo("Jantar", "diariasJanta", ($a_mod["para_tx_diariasJanta"]?? ""), 2, "MASCARA_DINHEIRO")
			],
			[
				combo("Acordo Sindical", "acordo", ($a_mod["para_tx_acordo"]?? ""), 1, ["sim" => "Sim", "nao" => "Não"]),
				campo_data("Início do Acordo*", "inicioAcordo", ($a_mod["para_tx_inicioAcordo"]?? ""), 1),
				campo_data("Fim do Acordo*", "fimAcordo", ($a_mod["para_tx_fimAcordo"]?? ""), 1)
			],
			[
				checkbox_banco("Utilizar regime de banco de horas?", "banco", ($a_mod["para_tx_banco"]?? ""), ($a_mod["para_nb_qDias"]?? ""), ($a_mod["para_tx_horasLimite"]?? ""),2),
				checkbox(
					"Ignorar intervalos",
					"ignorarCampos", (
						[
							"repouso" 			=> "Repouso", 
							"descanso" 			=> "Descanso",
							"espera" 			=> "Espera",
							"repousoEmbarcado" 	=> "Repouso Embarcado",
							"mdc" 				=> "MDC",
						]
					),
					12,
					"checkbox",
					"",
					$a_mod["para_tx_ignorarCampos"] ?? ""
				),
				checkbox(
					"Considerar a <a href='https://portal.trt3.jus.br/internet/jurisprudencia/repercussao-geral-e-controle-concentrado-adi-adc-e-adpf-stf/downloads/adi-5322-acordao.pdf'>ADI 5322</a>?",
					"adi5322",
					["sim" => "ADI 5322"],
					2,
					"checkbox",
					"",
					$a_mod["para_tx_adi5322"]?? "nao"
				),
				textarea("Descrição", "Obs", ($a_mod["para_tx_Obs"]?? ""), 12,"maxlength='100' style='min-width:fit-content; max-width: 100%;'")
			],
		];

		$botoes = [
			botao("Gravar","cadastrarParametro","id",($_POST["id"]?? ""),"","","btn btn-success"),
			criarBotaoVoltar()
		];
		
		echo abre_form("Dados dos Parâmetros");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		fieldset("Geral");
		echo linha_form($campos[0]);
		fieldset("Hora Extra");
		echo linha_form($campos[1]);
		fieldset("Diárias");
		echo linha_form($campos[2]);
		fieldset("Acordo Sindical");
		echo linha_form($campos[3]);
		fieldset("Outros");
		echo linha_form($campos[4]);

		if(!empty($a_mod["para_nb_userCadastro"])){
			$a_userCadastro = carregar("user", $a_mod["para_nb_userCadastro"]);
			if(!empty($a_userCadastro)){
				$cAtualiza = [
					texto(
						"Data de Cadastro",
						"Registro inserido por ".$a_userCadastro["user_tx_login"]." às ".data($a_mod["para_tx_dataCadastro"],1).".",
						5
					)
				];
				
				if($a_mod["para_nb_userAtualiza"] > 0){
					$a_userAtualiza = carregar("user",$a_mod["para_nb_userAtualiza"]);
					$cAtualiza[] = texto(
						"Última Atualização",
						"Registro atualizado por ".$a_userAtualiza["user_tx_login"]." às ".data($a_mod["para_tx_dataAtualiza"],1).".",
						5
					);
				}
				echo "<br>";
				echo linha_form($cAtualiza);
			}
		}

		echo fecha_form($botoes);

		if (!empty($a_mod["para_nb_id"])) {
			$arquivos = mysqli_fetch_all(query(
				"SELECT 
				documento_parametro.docu_nb_id,
				documento_parametro.para_nb_id,
				documento_parametro.docu_tx_dataCadastro,
				documento_parametro.docu_tx_dataVencimento,
				documento_parametro.docu_tx_caminho,
				documento_parametro.docu_tx_descricao,
				documento_parametro.docu_tx_nome,
				documento_parametro.docu_tx_visivel,
				documento_parametro.docu_tx_assinado,
				t.tipo_tx_nome,
				gd.grup_tx_nome,
				subg.sbgr_tx_nome
				FROM documento_parametro
				LEFT JOIN tipos_documentos t 
				ON documento_parametro.docu_tx_tipo = t.tipo_nb_id
				LEFT JOIN grupos_documentos gd 
				ON t.tipo_nb_grupo = gd.grup_nb_id
				LEFT JOIN sbgrupos_documentos subg
				ON subg.sbgr_nb_id = documento_parametro.docu_nb_sbgrupo
				WHERE documento_parametro.para_nb_id = ".$a_mod["para_nb_id"]
			),MYSQLI_ASSOC);
			echo "</div><div class='col-md-12'><div class='col-md-12 col-sm-12'>".arquivosParametro("Documentos", $a_mod["para_nb_id"], $arquivos);
		}
		rodape();

		echo 
			"<form name='form_excluir_arquivo' method='post' action='cadastro_parametro.php'>
				<input type='hidden' name='idParametro' value=''>
				<input type='hidden' name='idArq' value=''>
				<input type='hidden' name='acao' value=''>
			</form>

			<form name='form_download_arquivo' method='post' action='cadastro_parametro.php'>
				<input type='hidden' name='idParametro' value=''>
				<input type='hidden' name='caminho' value=''>
				<input type='hidden' name='acao' value=''>
			</form>"
		;

		echo 
			"<style>
				div#divHorasEscala {
					padding-top: 30px;
					width: 100%;
					display: flex;
					justify-content: flex-start;
					flex-wrap: wrap;
				}

				.linhaDiaEscala {
					padding: 0px 5px 0px 5px;
					margin: 5px 5px 5px 5px;
					overflow: hidden;
					border: 1px solid rgb(194, 202, 216);
					border-radius: 3px;
				}

				.linhaDiaEscala>.labelDiaEscala {
					display: flex;
				}

				.diaSemana{
					width: min-content;
					margin-left: 15px;
				}

				#horaInicio::-webkit-calendar-picker-indicator, #horaFim::-webkit-calendar-picker-indicator, #intervaloInterno::-webkit-calendar-picker-indicator{
					display: none;
				}

				.linhaDiaEscala>.col-sm-1{
					padding: 0;
				}

				input#horaInicio, input#horaFim, input#intervaloInterno {
					text-align: center;
				}
			</style>"
		;

		carregarJSFormParametro();
	}

	function index(){

		cabecalho("Cadastro de Parâmetros");

		$campos = [
			campo("Código", "busca_codigo", $_POST["busca_codigo"]?? "", 2, "MASCARA_NUMERO", "maxlength='6'"),
			campo("Nome", 	"busca_nome_like", $_POST["busca_nome_like"]?? "", 4, "", "maxlength='65'"),
      		combo("Acordo", "busca_acordo", $_POST["busca_acordo"]?? "", 2, ["" => "Todos", "sim" => "Sim", "nao" => "Não"]),
			combo("Banco de Horas", "busca_banco", $_POST["busca_banco"]?? "", 2, ["" => "Todos", "sim" => "Sim", "nao" => "Não"]),
			// combo("Vencidos", "busca_vencidos", $_POST["busca_vencidos"]?? "", 2, ["" => "Todos", "sim" => "Sim", "nao" => "Não"])
		];

		$botoes = [
			botao("Buscar", "index"),
			botao("Inserir", "mostrarFormParametro","","","","","btn btn-success"),
		];
		
		echo abre_form();
		echo linha_form($campos);
		echo fecha_form($botoes);

		//Grid dinâmico{
			$gridFields = [
				"CÓDIGO" 				=> "para_nb_id",
				"NOME" 					=> "para_tx_nome",
				"TIPO" 					=> "CONCAT('formatarTipo(\"', para_tx_tipo, '\")') AS para_tx_tipo",
				"H.E. SEMANAL" 			=> "CONCAT(para_tx_percHESemanal, '%') AS para_tx_percHESemanal",
				"H.E. EX." 				=> "CONCAT(para_tx_percHEEx, '%') AS para_tx_percHEEx",
				"ACORDO" 				=> "para_tx_acordo",
				"INÍCIO" 				=> "CONCAT('data(\"', para_tx_inicioAcordo, '\")') AS para_tx_inicioAcordo",
				"FIM" 					=> "CONCAT('data(\"', para_tx_fimAcordo, '\")') AS para_tx_fimAcordo",
				"STATUS" 				=> "para_tx_status"
			];

			$camposBusca = [
				"busca_codigo"		=> "para_nb_id",
				"busca_nome_like"	=> "para_tx_nome",
				"busca_acordo"		=> "para_tx_acordo",
				"busca_banco"		=> "para_tx_banco"
			];

			$queryBase = 
				"SELECT ".implode(", ", array_values($gridFields))." FROM parametro
					LEFT JOIN escala ON para_nb_id = esca_nb_parametro"
			;

			$actions = criarIconesGrid(
				["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
				["cadastro_parametro.php", "cadastro_parametro.php"],
				["modificarParametro()", "excluirParametro()"]
			);
	
			$actions["functions"][1] .= 
				"esconderInativar('glyphicon glyphicon-remove search-remove', 8);"
			;
	
			$gridFields["actions"] = $actions["tags"];
	
			$jsFunctions =
				"const funcoesInternas = function(){
					".implode(" ", $actions["functions"])."
				}"
			;

			echo gridDinamico("tabelaParametros", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		//}

		rodape();

	}