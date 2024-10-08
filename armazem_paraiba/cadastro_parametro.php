<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/
		
	include "conecta.php";

	function excluirParametro(){
		inactivateById("parametro", $_POST["id"]);
		index();
		exit;
	}

	function downloadArquivo() {
		// Verificar se o arquivo existe
		if(file_exists($_POST["caminho"])){
			// Configurar cabeçalhos para forçar o download
			header("Content-Description: File Transfer");
			header("Content-Type: application/octet-stream");
			header("Content-Disposition: attachment; filename=" . basename($_POST["caminho"]));
			header("Expires: 0");
			header("Cache-Control: must-revalidate");
			header("Pragma: public");
			header("Content-Length: " . filesize($_POST["caminho"]));

			// Lê o arquivo e o envia para o navegador
			readfile($_POST["caminho"]);
			exit;
		}else{
			echo "O arquivo não foi encontrado.";
		}
		$_POST["id"] = $_POST["idParametro"];
		modifica_parametro();
		exit;
	}

	function enviar_documento() {
		global $a_mod;

		if(empty($a_mod)){
			$a_mod = carregar("parametro", $_POST["id"]);
			$campos = [
				"nome",
				"jornadaSemanal",
				"jornadaSabado",
				"tolerancia",
				"percHESemanal",
				"percHEEx",
				"maxHESemanalDiario",
				"diariasCafe",
				"diariasAlmoco",
				"diariasJanta",
				"acordo",
				"inicioAcordo",
				"fimAcordo",
				"banco",
				"Obs"
			];
			foreach($campos as $campo){
				$a_mod["para_tx_".$campo] = $_POST[$campo];
			}
			$a_mod["para_nb_qDias"] = $_POST["para_nb_Qdias"];
			$a_mod["para_tx_horasLimite"] = $_POST["para_tx_horasLimite"];
			unset($campos);
		}

		$novoParametro = [
			"para_nb_id" => $_POST["idParametro"],
			"doc_tx_nome" => $_POST["file-name"],
			"doc_tx_descricao" => $_POST["description-text"],
			//doc_tx_caminho está mais abaixo
			"doc_tx_dataCadastro" => date("Y-m-d H:i:s")
		];
		
		$arquivo =  $_FILES["file"];
		$formatosImg = ["image/jpeg", "image/png", "application/msword", "application/pdf"];

		if (in_array($arquivo["type"], $formatosImg) && $arquivo["name"] != "") {
			$pasta_parametro = "arquivos/parametro/".$novoParametro["para_nb_id"]."/";
	
			if (!is_dir($pasta_parametro)) {
				mkdir($pasta_parametro, 0777, true);
			}
	
			$arquivo_temporario = $arquivo["tmp_name"];
			$extensao = pathinfo($arquivo["name"], PATHINFO_EXTENSION);
			$novoParametro["doc_tx_nome"] .= ".".$extensao;
			$novoParametro["doc_tx_caminho"] = $pasta_parametro.$novoParametro["doc_tx_nome"];
	
			if (move_uploaded_file($arquivo_temporario, $novoParametro["doc_tx_caminho"])) {
				inserir("documento_parametro", array_keys($novoParametro), array_values($novoParametro));
			}
		}

		$_POST["id"] = $novoParametro["para_nb_id"];
		modifica_parametro();
		exit;
	}

	function excluir_documento(){
		query("DELETE FROM documento_parametro WHERE doc_nb_id = ".$_POST["idArq"].";");
		
		$_POST["id"] = $_POST["idParametro"];
		modifica_parametro();
		exit;
	}

	function modifica_parametro(){
		global $a_mod;
		$a_mod=carregar("parametro",$_POST["id"]);
		layout_parametro();
		exit;
	}

	function cadastra_parametro(){

		//Conferir se os campos obrigatórios estão preenchidos{
			$camposObrig = [];

			if(!empty($_POST["acordo"]) && $_POST["acordo"] == "sim"){
				$camposObrig["inicioAcordo"] = "Início do Acordo";
				$camposObrig["fimAcordo"] = "Fim do Acordo";
			}
	
			if(!empty($_POST["banco"]) && $_POST["banco"] == "sim"){
				$camposObrig["quandDias"] = "Quantidade de Dias";
				$camposObrig["quandHoras"] = "Quantidade de Horas Limite";
			}
	
			$camposObrig = array_merge($camposObrig, [
				"nome" => "Nome",
				"jornadaSemanal" => "Jornada Semanal (Horas/Dia)",
				"jornadaSabado" => "Jornada Sábado (Horas/Dia)",
				"tolerancia" => "Tolerância de jornada Saldo diário (Minutos)",
				"percHESemanal" => "Hora Extra Semanal",
				"percHEEx" => "Hora Extra Extraordinária",
				"maxHESemanalDiario" => "Máx. de \"H.E. Semanal\" por dia"
			]);
			
			$errorMsg = conferirCamposObrig($camposObrig, $_POST);
			if(!empty($errorMsg)){
				set_status($errorMsg);
				layout_parametro();
				exit;
			}
			unset($camposObrig);
		//}

		//Conferir se as porcentagens de hora extra estão dentro do válido{
			// De acordo com a Lei n° 5452, artigo 59, parágrafo 1°
			if(intval($_POST["percHESemanal"]) < 50){
				$_POST["errorFields"][] = "percHESemanal";
				$errorMsg = "ERRO: O valor mínimo de Hora Extra Semanal é 50%.";
			}elseif(intval($_POST["percHEEx"]) < 50){
				$_POST["errorFields"][] = "percHEEx";
				$errorMsg = "ERRO: O valor mínimo de Hora Extra Extraodinária é 50%.";
			}
			if(!empty($errorMsg)){
				set_status($errorMsg);
				layout_parametro();
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
			"para_tx_nome" 					=> $_POST["nome"],
			"para_tx_jornadaSemanal" 		=> $_POST["jornadaSemanal"],
			"para_tx_jornadaSabado" 		=> $_POST["jornadaSabado"],
			"para_tx_percHESemanal" 		=> $_POST["percHESemanal"],
			"para_tx_percHEEx" 				=> $_POST["percHEEx"],
			"para_tx_maxHESemanalDiario" 	=> $_POST["maxHESemanalDiario"],
			"para_tx_pagarHEExComPerNeg"    => $_POST["pagarHEExComPerNeg"],
			"para_tx_tolerancia" 			=> $_POST["tolerancia"],
			"para_tx_acordo" 				=> $_POST["acordo"],
			"para_tx_inicioAcordo" 			=> $_POST["inicioAcordo"],
			"para_tx_fimAcordo" 			=> $_POST["fimAcordo"],
			"para_nb_userCadastro" 			=> intval($_SESSION["user_nb_id"]),
			"para_tx_dataCadastro" 			=> date("Y-m-d"),
			"para_tx_diariasCafe" 			=> $_POST["diariasCafe"],
			"para_tx_diariasAlmoco" 		=> $_POST["diariasAlmoco"],
			"para_tx_diariasJanta" 			=> $_POST["diariasJanta"],
			"para_tx_status" 				=> "ativo",
			"para_tx_banco" 				=> $_POST["banco"],
			"para_nb_qDias" 				=> $_POST["quandDias"],
			"para_tx_horasLimite" 			=> $_POST["quandHoras"],
			"para_tx_Obs" 					=> $_POST["Obs"],
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
		

		$novoParametro["para_nb_userAtualiza"] = $_SESSION["user_nb_id"];
		$novoParametro["para_tx_dataAtualiza"] = date("Y-m-d H:i:s");
		
		if(!empty($_POST["id"])){ //Se está editando

			$aParametro = carregar("parametro", $_POST["id"]);

			$motoristasNoPadrao = mysqli_fetch_all(
				query(
					"SELECT * FROM entidade 
						WHERE enti_tx_status = 'ativo'
							AND enti_nb_parametro = '".(int)$_POST["id"]."'"
				),
				MYSQLI_ASSOC
			);

			atualizar("parametro",array_keys($novoParametro),array_values($novoParametro),$_POST["id"]);
			
			foreach($motoristasNoPadrao as $motorista){
				// Se o motorista estava dentro do padrão do parâmetro antes da atualização, atualiza os parâmetros do motorista junto.
				if($aParametro["para_nb_id"] == $motorista["enti_nb_parametro"] && $motorista["enti_tx_ehPadrao"] == "sim"){
					atualizar(
						"entidade",
						["enti_tx_jornadaSemanal", "enti_tx_jornadaSabado", "enti_tx_percHESemanal", "enti_tx_percHEEx"],
						[$_POST["jornadaSemanal"], $_POST["jornadaSabado"], $_POST["percHESemanal"], $_POST["percHEEx"]],
						$motorista["enti_nb_id"]
					);
				}
			}
		} else {
			inserir("parametro",array_keys($novoParametro),array_values($novoParametro));
		}

		layout_parametro();
		exit;
	}

	function layout_parametro(){
		global $a_mod;

		if(empty($a_mod) && !empty($_POST["id"])){
			$a_mod = carregar("parametro", $_POST["id"]);
			$campos = [
				"nome", "jornadaSemanal", "jornadaSabado", "tolerancia", "percHESemanal",
				"percHEEx", "maxHESemanalDiario", "diariasCafe", "diariasAlmoco", "diariasJanta",
				"acordo", "inicioAcordo", "fimAcordo", "banco", "Obs"
			];
			foreach($campos as $campo){
				if(!empty($_POST[$campo])){
					$a_mod["para_tx_".$campo] = $_POST[$campo];
					
				}
			}
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

		cabecalho("Cadastro de Parâmetros");
		
		$campos = [
			[
				campo("Nome*", "nome", ($a_mod["para_tx_nome"]?? ""), 5),
				campo_hora("Jornada Semanal (Horas/Dia)*", "jornadaSemanal", ($a_mod["para_tx_jornadaSemanal"]?? ""), 2),
				campo_hora("Jornada Sábado (Horas/Dia)*", "jornadaSabado", ($a_mod["para_tx_jornadaSabado"]?? ""), 2),
				campo("Tolerância de jornada Saldo diário (Minutos)*", "tolerancia", ($a_mod["para_tx_tolerancia"]?? ""), 2,"MASCARA_NUMERO","maxlength='3'")
			],
			[
				campo("Hora Extra Semanal (%)*", "percHESemanal", ($a_mod["para_tx_percHESemanal"]?? ""), 3, "MASCARA_NUMERO", "maxlength='3'"),
				campo("Hora Extra Extraordinária (%)*", "percHEEx", ($a_mod["para_tx_percHEEx"]?? ""), 3, "MASCARA_NUMERO"),
				campo_hora("Máx. de \"H.E. Semanal\" por dia*", "maxHESemanalDiario", ($a_mod["para_tx_maxHESemanalDiario"]?? "02:00"), 3),
				combo("Pagar H.E. Ex. mesmo com Período Neg.*", "pagarHEExComPerNeg", ($a_mod["para_tx_pagarHEExComPerNeg"]?? "sim"), 3, ["sim" => "Sim", "nao" => "Não"])
			],
			[
				campo("Diária Café da Manhã(R$)", "diariasCafe", ($a_mod["para_tx_diariasCafe"]?? ""), 2, "MASCARA_DINHERO"),
				campo("Diária Almoço(R$)", "diariasAlmoco", ($a_mod["para_tx_diariasAlmoco"]?? ""), 2, "MASCARA_DINHERO"),
				campo("Diária Jantar(R$)", "diariasJanta", ($a_mod["para_tx_diariasJanta"]?? ""), 2, "MASCARA_DINHERO")
			],
			[
				combo("Acordo Sindical", "acordo", ($a_mod["para_tx_acordo"]?? ""), 1, ["sim" => "Sim", "nao" => "Não"]),
				campo_data("Início do Acordo*", "inicioAcordo", ($a_mod["para_tx_inicioAcordo"]?? ""), 1),
				campo_data("Fim do Acordo*", "fimAcordo", ($a_mod["para_tx_fimAcordo"]?? ""), 1)
			],
			[
				checkbox_banco("Utilizar regime de banco de horas?","banco",($a_mod["para_tx_banco"]?? ""),($a_mod["para_nb_qDias"]?? ""), ($a_mod["para_tx_horasLimite"]?? ""),2),
				ckeditor("Descrição", "Obs", ($a_mod["para_tx_Obs"]?? ""), 12,"maxlength='100' style='min-width:fit-content; max-width: 100%;'"),
				checkbox(
					"Ignorar intervalos",
					"ignorarCampos", (
						[
							"repouso" => "Repouso", 
							"descanso" => "Descanso",
							"espera" => "Espera",
							"repousoEmbarcado" => "Repouso Embarcado",
							"mdc" => "MDC",
						]
					),
					5,
					"checkbox",
					"",
					$a_mod["para_tx_ignorarCampos"] ?? ""
				)
			],
		];
		
		$botoes = [
			botao("Gravar","cadastra_parametro","id",($_POST["id"]?? ""),"","","btn btn-success"),
			botao("Voltar","voltar")
		];

		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], "cadastro_parametro.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_parametro.php";
			}
		}
		
		abre_form("Dados dos Parâmetros");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		fieldset("Geral");
		linha_form($campos[0]);
		fieldset("Hora Extra");
		linha_form($campos[1]);
		fieldset("Diárias");
		linha_form($campos[2]);
		fieldset("Acordo Sindical");
		linha_form($campos[3]);
		fieldset("Outros");
		linha_form($campos[4]);

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
				linha_form($cAtualiza);
			}
		}

		fecha_form($botoes);

		if (!empty($a_mod["para_nb_id"])) {
			$arquivos = mysqli_fetch_all(query(
				"SELECT * FROM documento_parametro"
					." WHERE para_nb_id = ".$a_mod["para_nb_id"]
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
			"<script type='text/javascript'>
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

	function index(){

		cabecalho("Cadastro de Parâmetros");

		$extra = ((!empty($_POST["busca_codigo"]))? " AND para_nb_id LIKE '%".$_POST["busca_codigo"]."%'" : "")
		.((!empty($_POST["busca_nome"])) ? " AND para_tx_nome LIKE '%" . $_POST["busca_nome"] . "%'" : "")
		.((!empty($_POST["busca_acordo"]) &&  $_POST["busca_acordo"] != "Todos") ? " AND para_tx_acordo = '".$_POST["busca_acordo"]."'" : "")
		.((!empty($_POST["busca_banco"]) &&  $_POST["busca_banco"] != "Todos") ? " AND para_tx_banco = '".$_POST["busca_banco"]."'" : "");

		$campos = [
			campo("Código", "busca_codigo", $_POST["busca_codigo"]?? "", 2, "MASCARA_NUMERO", "maxlength='6'"),
			campo("Nome", "busca_nome", $_POST["busca_nome"]?? "", 4, "", "maxlength='65'"),
      		combo("Acordo", "busca_acordo", $_POST["busca_acordo"]?? "", 2, ["" => "Todos", "sim" => "Sim", "nao" => "Não"]),
			combo("Banco de Horas", "busca_banco", $_POST["busca_banco"]?? "", 2, ["" => "Todos", "sim" => "Sim", "nao" => "Não"]),
			// combo("Vencidos", "busca_vencidos", $_POST["busca_vencidos"]?? "", 2, ["" => "Todos", "sim" => "Sim", "nao" => "Não"])
		];

		$botoes = [
			botao("Buscar", "index"),
			botao("Inserir", "layout_parametro","","","","","btn btn-success"),
		];
		
		abre_form();
		linha_form($campos);
		fecha_form($botoes);

		$sql = 
			"SELECT * FROM parametro"
				." WHERE para_tx_status = 'ativo' ".$extra
		;
		// if (isset($_POST["busca_vencidos"])){
		// 	$sql = 
		// 		"SELECT *, DATEDIFF('".date("Y-m-d")."' ,para_tx_setData) AS diferenca_em_dias FROM parametro"
		// 			." WHERE 1"
		// 				." AND DATEDIFF('".date("Y-m-d")."',para_tx_setData) ".($_POST["busca_vencidos"] === "sim"? "<": ">")." para_nb_qDias"
		// 				." OR DATEDIFF('".date("Y-m-d")."',para_tx_setData) IS NULL"
		// 				." ".$extra.";"
		// 	;
		// }

		$gridCols = [
			"CÓDIGO" 											=> "para_nb_id",
			"NOME" 												=> "para_tx_nome",
			"JORNADA SEMANAL/DIA" 								=> "para_tx_jornadaSemanal",
			"JORNADA SÁBADO" 									=> "para_tx_jornadaSabado",
			"H.E. SEMANAL" 										=> "formatPerc(para_tx_percHESemanal)",
			"H.E. EX." 											=> "formatPerc(para_tx_percHEEx)",
			"ACORDO" 											=> "para_tx_acordo",
			"INÍCIO" 											=> "data(para_tx_inicioAcordo)",
			"FIM" 												=> "data(para_tx_fimAcordo)",
			"<spam class='glyphicon glyphicon-search'></spam>" 	=> "icone_modificar(para_nb_id,modifica_parametro)",
			"<spam class='glyphicon glyphicon-remove'></spam>" 	=> "icone_excluir(para_nb_id,excluirParametro)"
		];

		grid($sql,array_keys($gridCols),array_values($gridCols));

		rodape();

	}