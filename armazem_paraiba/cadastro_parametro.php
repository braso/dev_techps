<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Expires: 01 Jan 2001 00:00:00 GMT");
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
	//*/

	
	include "conecta.php";
	
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
			"docu_tx_nome" => $_POST["file-name"],
			"docu_tx_descricao" => $_POST["description-text"],
			//docu_tx_caminho está mais abaixo
			"docu_tx_dataCadastro" => date("Y-m-d H:i:s")
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
			$novoParametro["docu_tx_nome"] .= ".".$extensao;
			$novoParametro["docu_tx_caminho"] = $pasta_parametro.$novoParametro["docu_tx_nome"];
	
			if (move_uploaded_file($arquivo_temporario, $novoParametro["docu_tx_caminho"])) {
				inserir("documento_parametro", array_keys($novoParametro), array_values($novoParametro));
			}
		}

		set_status("Registro inserido com sucesso.");
		$_POST["id"] = $novoParametro["para_nb_id"];
		modificarParametro();
		exit;
	}

	function excluir_documento(){
		remover("documento_parametro", $_POST["idArq"]);
		$_POST["id"] = $_POST["idParametro"];
		modificarParametro();
		exit;
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
					$camposObrig["horaInicio"] = "Jornada Dias Úteis (Hr/dia)";
					$camposObrig["horaFim"] = "Jornada Sábado";

					$_POST["dias"] = array_filter([
						null,
						$_POST["dias_1"]?? null,
						$_POST["dias_2"]?? null,
						$_POST["dias_3"]?? null,
						$_POST["dias_4"]?? null,
						$_POST["dias_5"]?? null,
						$_POST["dias_6"]?? null,
						$_POST["dias_7"]?? null
					], function ($valor){
						return $valor != null;
					});


					
					$_POST["dias"] = json_encode(array_keys($_POST["dias"]));

				}elseif($_POST["tipo"] == "horas_por_dia"){
					$camposObrig["jornadaSemanal"] = "Jornada Dias Úteis (Hr/dia)";
					$camposObrig["jornadaSabado"] = "Jornada Sábado";
				}
			}

			$camposObrig = array_merge($camposObrig, [
				"nome" => "Nome",
				// "tolerancia" => "Tolerância de atraso (Minutos)",
				"percHESemanal" => "Hora Extra Semanal",
				"percHEEx" => "Hora Extra Extraordinária",
				// "maxHESemanalDiario" => "Máx. de 'H.E. Semanal' por dia"
			]);
			
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
			"para_tx_nome" 					=> $_POST["nome"],
			"para_tx_jornadaSemanal" 		=> $_POST["jornadaSemanal"],
			"para_tx_jornadaSabado" 		=> $_POST["jornadaSabado"],
			"para_tx_percHESemanal" 		=> $_POST["percHESemanal"],
			"para_tx_percHEEx" 				=> $_POST["percHEEx"],
			"para_tx_maxHESemanalDiario" 	=> $_POST["maxHESemanalDiario"],
			"para_tx_pagarHEExComPerNeg"    => $_POST["pagarHEExComPerNeg"],
			"para_tx_tolerancia" 			=> $_POST["tolerancia"],
			"para_tx_descFaltas" 			=> $_POST["descFaltas"],
			"para_tx_acordo" 				=> $_POST["acordo"],
			"para_tx_inicioAcordo" 			=> $_POST["inicioAcordo"],
			"para_tx_fimAcordo" 			=> $_POST["fimAcordo"],
			"para_tx_diariasCafe" 			=> $_POST["diariasCafe"],
			"para_tx_diariasAlmoco" 		=> $_POST["diariasAlmoco"],
			"para_tx_diariasJanta" 			=> $_POST["diariasJanta"],
			"para_tx_banco" 				=> $_POST["banco"],
			"para_nb_qDias" 				=> $_POST["quandDias"],
			"para_tx_horasLimite" 			=> $_POST["quandHoras"],
			"para_tx_Obs" 					=> $_POST["Obs"],
			"para_tx_adi5322" 				=> $_POST["adi5322"],
			"para_tx_status" 				=> "ativo",
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

			$motoristasNoParametro = mysqli_fetch_all(query(
				"SELECT * FROM entidade 
					WHERE enti_tx_status = 'ativo'
						AND enti_nb_parametro = '{$_POST["id"]}'"
			),MYSQLI_ASSOC);

			atualizar("parametro",array_keys($novoParametro),array_values($novoParametro),$_POST["id"]);

			if($_POST["tipo"] == "escala"){
				$escalaParametro = mysqli_fetch_assoc(query(
					"SELECT * FROM escala WHERE esca_nb_parametro = {$_POST["id"]};"
				));

				if(!empty($escalaParametro)){
					$escalaParametro = [
						"esca_nb_id" => $escalaParametro["esca_nb_id"],
						"esca_tx_horaInicio" => $_POST["horaInicio"],
						"esca_tx_horaFim" => $_POST["horaFim"],
						"esca_tx_dias" => $_POST["dias"]
					];

					atualizar("escala",array_keys($escalaParametro),array_values($escalaParametro), $escalaParametro["esca_nb_id"]);
				}else{
					$novaEscala = [
						"esca_nb_parametro" => $_POST["id"],
						"esca_tx_horaInicio" => $_POST["horaInicio"],
						"esca_tx_horaFim" => $_POST["horaFim"],
						"esca_tx_dias" => $_POST["dias"]
					];

					inserir("escala",array_keys($novaEscala),array_values($novaEscala));
				}
			}
			
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

			$id = inserir("parametro",array_keys($novoParametro),array_values($novoParametro));

			if($_POST["tipo"] == "escala"){
				$novaEscala = [
					"esca_nb_parametro" => $id[0],
					"esca_tx_horaInicio" => $_POST["horaInicio"],
					"esca_tx_horaFim" => $_POST["horaFim"],
					"esca_tx_dias" => $_POST["dias"]
				];

				inserir("escala",array_keys($novaEscala),array_values($novaEscala));
			}

			set_status("Registro inserido com sucesso.");
		}

		mostrarFormParametro();
		exit;
	}

	function mostrarFormParametro(){
		global $a_mod;

		if(empty($a_mod) && !empty($_POST["id"])){
			$a_mod = carregar("parametro", $_POST["id"]);
			$campos = [
				"nome", "jornadaSemanal", "jornadaSabado", "tolerancia", "percHESemanal",
				"percHEEx", "maxHESemanalDiario", "diariasCafe", "diariasAlmoco", "diariasJanta",
				"acordo", "inicioAcordo", "fimAcordo", "banco", "adi5322", "Obs"
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

		if(!empty($a_mod["para_tx_tipo"]) && $a_mod["para_tx_tipo"] == "escala"){
			$escala = mysqli_fetch_assoc(query(
				"SELECT * FROM escala WHERE esca_nb_parametro = {$a_mod["para_nb_id"]};"
			));

			$a_mod = array_merge($a_mod, $escala);
		}

		$campos = [
			[
				"<div class='col-sm-12 margin-bottom-5 campo-fit-content' style='min-height: 50px;' id='tipo'>
					<div class='margin-bottom-5'>
						Tipo
					</div>"
					// ."<label>
					// 	<input type='radio' name='tipo' value='escala' ".((!empty($a_mod["para_tx_tipo"]) && $a_mod["para_tx_tipo"] == "escala")? "checked": "")." onchange='camposEscala(\"escala\")'>
					// 	Escala
					// </label>"
					."<label>
						<input type='radio' name='tipo' value='horas_por_dia' ".((empty($a_mod["para_tx_tipo"]) || $a_mod["para_tx_tipo"] == "horas_por_dia")? "checked": "")." onchange='camposEscala(\"horas_por_dia\")'>
						Horas/Dia
					</label>
				</div>",
				campo("Nome*", "nome", ($a_mod["para_tx_nome"]?? ""), 5),
				campo("Tolerância de atraso (Minutos)*", "tolerancia", ($a_mod["para_tx_tolerancia"]?? ""), 2,"MASCARA_NUMERO","maxlength='3'"),
				campo_hora("Jornada Dias Úteis (Hr/dia)*", "jornadaSemanal", ($a_mod["para_tx_jornadaSemanal"]?? ""), 2),
				campo_hora("Jornada Sábado*", "jornadaSabado", ($a_mod["para_tx_jornadaSabado"]?? ""), 2),

				campo_hora("Horário de início*", "horaInicio", ($a_mod["esca_tx_horaInicio"]?? ""), 2),
				campo_hora("Horário de Fim*", "horaFim", ($a_mod["esca_tx_horaFim"]?? ""), 2),
				checkbox(
					"Dias escalados",
					"dias", (
						[
							"1"		=> "Domingo",
							"2" 	=> "Segunda", 
							"3" 	=> "Terça",
							"4" 	=> "Quarta",
							"5" 	=> "Quinta",
							"6"		=> "Sexta",
							"7"		=> "Sábado"
						]
					),
					12,
					"checkbox",
					"",
					$a_mod["esca_tx_dias"] ?? ""
				),
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
				ckeditor("Descrição", "Obs", ($a_mod["para_tx_Obs"]?? ""), 12,"maxlength='100' style='min-width:fit-content; max-width: 100%;'")
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

				function camposEscala(campoSelecionado){
					if(campoSelecionado == 'escala'){
						document.getElementById('jornadaSemanal').parentElement.hidden = true;
						document.getElementById('jornadaSemanal').value = null;

						document.getElementById('jornadaSabado').parentElement.hidden = true;
						document.getElementById('jornadaSabado').value = null;

						document.getElementById('horaInicio').parentElement.hidden = false;
						document.getElementById('horaFim').parentElement.hidden = false;
						document.getElementById('dias').hidden = false;

					}else if(campoSelecionado == 'horas_por_dia'){
						document.getElementById('horaInicio').parentElement.hidden = true;
						document.getElementById('horaInicio').value = null;
						
						document.getElementById('horaFim').parentElement.hidden = true;
						document.getElementById('horaFim').value = null;
						
						document.getElementById('dias').hidden = true;
						document.getElementById('dias').value = null;

						document.getElementById('jornadaSemanal').parentElement.hidden = false;
						document.getElementById('jornadaSabado').parentElement.hidden = false;
					}
				}

				".(empty($a_mod["para_tx_tipo"])? "camposEscala('horas_por_dia');": "camposEscala('{$a_mod["para_tx_tipo"]}')")."

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
				"JORNADA" 				=> "IF(para_tx_tipo = 'horas_por_dia', 
					CONCAT('formatarColunaJornada(\"',para_tx_tipo,'\",\"',para_tx_jornadaSemanal,'\",\"',para_tx_jornadaSabado,'\")'),
					CONCAT('formatarColunaJornada(\"',para_tx_tipo,'\",\"',esca_tx_horaInicio,'\",\"',esca_tx_horaFim,'\",\"',esca_tx_dias,'\")')) AS para_tx_jornada",
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
				"esconderInativar('glyphicon glyphicon-remove search-remove', 9);"
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