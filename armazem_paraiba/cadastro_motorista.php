<?php
    /* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/
	include "conecta.php";

	function carregarEmpresa(){
		$aEmpresa = carregar("empresa", (int)$_GET["emp"]);
		if ($aEmpresa["empr_nb_parametro"] > 0) {
			echo 
				"<script type='text/javascript'>
					parent.document.contex_form.parametro.value = '".$aEmpresa["empr_nb_parametro"]."';
					parent.document.contex_form.parametro.onchange();
				</script>"
			;
		}
		exit;
	}

	function carregarParametroPadrao(int $idEmpresa = null){
		global $a_mod;
		if(!empty($idEmpresa) && !empty($a_mod["enti_nb_empresa"])){
			$idEmpresa = intval($a_mod["enti_nb_empresa"]);
		}else{
			$idEmpresa = -1;
		}

		$a_mod["parametroPadrao"] = mysqli_fetch_assoc(
			query(
				"SELECT parametro.* FROM empresa
					JOIN parametro ON empresa.empr_nb_parametro = parametro.para_nb_id
					WHERE para_tx_status = 'ativo'
						AND empresa.empr_nb_id = ".$idEmpresa."
					LIMIT 1;"
			)
		);
	}

	function carregarParametro(){
		if(empty($_GET["parametro"])){
			exit;
		}
		
		$parametro = carregar("parametro", (int)$_GET["parametro"]);
		
		if(empty($parametro)){
			exit;
		}
		echo 
			"<script type='text/javascript'>
				parent.document.contex_form.jornadaSemanal.value = '".$parametro["para_tx_jornadaSemanal"]."';
				parent.document.contex_form.jornadaSabado.value = '".$parametro["para_tx_jornadaSabado"]."';
				parent.document.contex_form.percHESemanal.value = '".$parametro["para_tx_percHESemanal"]."';
				parent.document.contex_form.percHEEx.value = '".$parametro["para_tx_percHEEx"]."';
			</script>"
		;
		exit;
	}
	function carregarEndereco(){
		global $CONTEX;

		echo 
	      	"<script src='".$CONTEX['path']."/../contex20/assets/global/plugins/jquery.min.js' type='text/javascript'></script>
			<script src='".$CONTEX['path']."/../contex20/assets/global/plugins/select2/js/select2.min.js'></script>
			<script src='".$CONTEX['path']."/../contex20/assets/global/plugins/jquery-inputmask/jquery.inputmask.bundle.min.js' type='text/javascript'></script>
			<script src='".$CONTEX['path']."/../contex20/assets/global/plugins/jquery-inputmask/maskMoney.js' type='text/javascript'></script>

			<script type='text/javascript'>
				jQuery.ajax({
					url: 'https://viacep.com.br/ws/".urlencode($_GET["cep"])."/json/',
					type: 'get',
        			dataType: 'json',
					success: function(data) {
						console.log(data.erro);
						if(data.erro == undefined){
							parent.document.contex_form.endereco.value = data.logradouro
							parent.document.contex_form.bairro.value = data.bairro;
							var selecionado = $('.cidade', parent.document);
							selecionado.empty();
							selecionado.append('<option value='+data.ibge+'>['+data.uf+'] '+data.localidade+'</option>');
							selecionado.val(data.ibge).trigger('change');
						}
					}
				});
			</script>"
    	;
		exit;
	}

	function cadastrarMotorista(){
		global $a_mod;

		if(!empty($_POST["matricula"])){
			$_POST["postMatricula"] = $_POST["matricula"];
		}
		while($_POST["postMatricula"][0] == "0"){
			$_POST["postMatricula"] = substr($_POST["postMatricula"], 1);
		}

		$enti_campos = [
			"enti_tx_matricula" 				=> "postMatricula", 
			"enti_tx_nome" 						=> "nome", 
			"enti_tx_nascimento" 				=> "nascimento", 
			"enti_tx_status" 					=> "status", 
			"enti_tx_status" 					=> "status", 
			"enti_tx_cpf" 						=> "cpf",
			"enti_tx_rg" 						=> "rg",
			"enti_tx_civil" 					=> "civil",
			"enti_tx_sexo" 						=> "sexo",
			"enti_tx_endereco" 					=> "endereco",
			"enti_tx_numero" 					=> "numero",
			"enti_tx_complemento" 				=> "complemento",
			"enti_tx_bairro" 					=> "bairro",
			"enti_nb_cidade" 					=> "cidade",
			"enti_tx_cep" 						=> "cep",
			"enti_tx_fone1" 					=> "fone1",
			"enti_tx_fone2" 					=> "fone2",
			"enti_tx_email" 					=> "email",
			"enti_tx_ocupacao" 					=> "ocupacao",
			"enti_nb_salario" 					=> "salario",
			"enti_nb_parametro" 				=> "parametro", 
			"enti_tx_obs" 						=> "obs", 
			"enti_nb_empresa" 					=> "empresa",
			"enti_tx_jornadaSemanal" 			=> "jornadaSemanal",
			"enti_tx_jornadaSabado" 			=> "jornadaSabado",
			"enti_tx_percHESemanal" 			=> "percHESemanal",
			"enti_tx_percHEEx" 		=> "percHEEx",
			"enti_tx_rgOrgao" 					=> "rgOrgao", 
			"enti_tx_rgDataEmissao" 			=> "rgDataEmissao", 
			"enti_tx_rgUf" 						=> "rgUf",
			"enti_tx_pai" 						=> "pai", 
			"enti_tx_mae" 						=> "mae", 
			"enti_tx_conjugue" 					=> "conjugue", 
			"enti_tx_tipoOperacao" 				=> "tipoOperacao",
			"enti_tx_subcontratado" 			=> "subcontratado", 
			"enti_tx_admissao" 					=> "admissao", 
			"enti_tx_desligamento" 				=> "desligamento",
			"enti_tx_cnhRegistro" 				=> "cnhRegistro", 
			"enti_tx_cnhValidade" 				=> "cnhValidade", 
			"enti_tx_cnhPrimeiraHabilitacao" 	=> "cnhPrimeiraHabilitacao", 
			"enti_tx_cnhCategoria" 				=> "cnhCategoria", 
			"enti_tx_cnhPermissao" 				=> "cnhPermissao",
			"enti_tx_cnhObs" 					=> "cnhObs", 
			"enti_nb_cnhCidade"			 		=> "cnhCidade", 
			"enti_tx_cnhEmissao" 				=> "cnhEmissao", 
			"enti_tx_cnhPontuacao" 				=> "cnhPontuacao", 
			"enti_tx_cnhAtividadeRemunerada" 	=> "cnhAtividadeRemunerada",
			"enti_tx_banco" 					=> "setBanco"
		];

		$novoMotorista = [];
		$post_values = array_values($enti_campos);
		for($f = 0; $f < sizeof(array_values($enti_campos)); $f++){
			$bd_campo = array_keys($enti_campos)[$f];
			if(isset($_POST[$post_values[$f]]) && !empty($_POST[$post_values[$f]])){
				$a_mod[$bd_campo] = $_POST[$post_values[$f]];
				$novoMotorista[$bd_campo] = $a_mod[$bd_campo];
			}
		}
		unset($enti_campos);

		if(isset($novoMotorista["enti_nb_salario"])){
			$novoMotorista["enti_nb_salario"] = str_replace([".", ","], ["", "."], $novoMotorista["enti_nb_salario"]);
		}



		//Conferir se os campos obrigatórios estão preenchidos{
			$camposObrig = [
				"nome" => "Nome", "nascimento" => "Dt. Nascimento",
				"cpf" => "CPF", "rg" => "RG", "bairro" => "Bairro",
				"cep" => "CEP", "endereco" => "Endereço", "cidade" => "Cidade/UF", "fone1" => "Telefone 1",
				"email" => "E-mail",
				"empresa" => "Empresa", "ocupacao" => "Ocupação", "admissao" => "Dt Admissão",
				"parametro" => "Parâmetro", "jornadaSemanal" => "Jornada Semanal", "jornadaSabado" => "Jornada Sábado",
				"cnhRegistro" => "N° Registro da CNH", "cnhValidade" => "Validade do CNH", "cnhCategoria" => "Categoria do CNH", 
				"cnhCidade" => "Cidade do CNH", "cnhEmissao" => "Data de Emissão do CNH"
			];
			$error = false;
			$emptyFields = "";

			if(empty($a_mod["enti_tx_matricula"])){
				$camposObrig["postMatricula"] = "Matrícula";
			}
			if($_POST["ocupacao"] == "Ajudante"){
				unset($camposObrig["cnhRegistro"], $camposObrig["cnhValidade"], $camposObrig["cnhCategoria"], $camposObrig["cnhCidade"], $camposObrig["cnhEmissao"]);
			}
			if(empty($_POST["percHESemanal"]) && $_POST["percHESemanal"] != "0"){
				$emptyFields .= "H.E. Semanal (%), ";
			}
			if(empty($_POST["percHEEx"]) && $_POST["percHEEx"] != "0"){
				$emptyFields .= "H.E. Extraordinária (%), ";
			}

			foreach(array_keys($camposObrig) as $campo){
				if(empty($_POST[$campo])){
					$error = true;
					$emptyFields .= $camposObrig[$campo].", ";
				}
			}
			$emptyFields = substr($emptyFields, 0, strlen($emptyFields)-2);
			
			if($error){
				set_status("ERRO: Insira os campos ".$emptyFields);
				visualizarCadastro();
				exit;
			}

			unset($camposObrig);
		//}

		$matriculaExistente = mysqli_fetch_assoc(query(
			"SELECT * FROM entidade"
				." WHERE enti_tx_matricula = '".($_POST["postMatricula"]?? "-1")."'"
			." LIMIT 1;"
		));
		$matriculaExistente = !empty($matriculaExistente);

		if($matriculaExistente && !isset($_POST["id"])){
			set_status("ERRO: Matrícula já cadastrada");
			visualizarCadastro();
			exit;
		}

		if(!empty($_POST["postMatricula"]) && strlen($_POST["postMatricula"]) > 10){
			set_status("ERRO: Matrícula com mais de 10 caracteres.");
			visualizarCadastro();
			exit;
		}

		if(!empty($_POST["login"])){
			$otherUser = mysqli_fetch_assoc(query(
				"SELECT user.* FROM user"
					." JOIN entidade ON user_nb_entidade = enti_nb_id"
					." WHERE user_tx_status = 'ativo'"
						." AND user_tx_login = '".$_POST["login"]."'"
						.(!empty($_POST["id"])? " AND enti_nb_id <> ".$_POST["id"]: "")
					." LIMIT 1;"
			));
			if(!empty($otherUser)){
				set_status("ERRO: Login já cadastrado.");
				$a_mod = $_POST;
				modificarMotorista();
				exit;
			}
		}

		if(empty($_POST["salario"])){
			$_POST["salario"] = (float)0.0;
		}
		if(!isset($_POST["rgDataEmissao"]) || empty($_POST["rgDataEmissao"])){
			$_POST["rgDataEmissao"] = "0000-00-00";
		}
		if(!isset($_POST["desligamento"]) || empty($_POST["desligamento"])){
			$_POST["desligamento"] = "0000-00-00";
		}

		$enti_valores = [];
		for($f = 0; $f < sizeof($post_values); $f++){
			$enti_valores[] = !empty($_POST[$post_values[$f]])? $_POST[$post_values[$f]]: "";
		}

		$_POST["cpf"] = preg_replace("/[^0-9]/is", "", $_POST["cpf"]);
		$_POST["rg"] = preg_replace("/[^0-9]/is", "", $_POST["rg"]);
		
		if (empty($_POST["id"])) {//Se está criando um motorista novo
			$aEmpresa = carregar("empresa", $_POST["empresa"]);
			if ($aEmpresa["empr_nb_parametro"] > 0) {
				$aParametro = carregar("parametro", $aEmpresa["empr_nb_parametro"]);
				if (
					$aParametro["para_tx_jornadaSemanal"] != $a_mod["enti_tx_jornadaSemanal"] ||
					$aParametro["para_tx_jornadaSabado"] != $a_mod["enti_tx_jornadaSabado"] ||
					$aParametro["para_tx_percHESemanal"] != $a_mod["enti_tx_percHESemanal"] ||
					$aParametro["para_tx_percHEEx"] != $a_mod["enti_tx_percHEEx"] ||
					$aParametro["para_nb_id"] != $a_mod["enti_nb_parametro"]
				) {
					$ehPadrao = "nao";
				} else {
					$ehPadrao = "sim";
				}
			}
			$novoMotorista["enti_nb_userCadastro"] = $_SESSION["user_nb_id"];
			$novoMotorista["enti_tx_dataCadastro"] = date("Y-m-d H:i:s");
			$novoMotorista["enti_tx_ehPadrao"] = $ehPadrao;
			$id = inserir("entidade", array_keys($novoMotorista), array_values($novoMotorista))[0];
			
			$user_infos = [
				"user_tx_matricula" 	=> $_POST["postMatricula"], 
				"user_tx_nome" 			=> $_POST["nome"], 
				"user_tx_nivel" 		=> $_POST["ocupacao"], 
				"user_tx_login" 		=> (!empty($_POST["login"])? $_POST["login"]: $_POST["postMatricula"]), 
				"user_tx_senha" 		=> md5($_POST["cpf"]), 
				"user_tx_status" 		=> $_POST["status"], 
				"user_nb_entidade" 		=> $id,
				"user_tx_nascimento" 	=> $_POST["nascimento"], 
				"user_tx_cpf" 			=> $_POST["cpf"],
				"user_tx_rg" 			=> $_POST["rg"],
				"user_nb_cidade" 		=> $_POST["cidade"], 
				"user_tx_email" 		=> $_POST["email"], 
				"user_tx_fone" 			=> $_POST["fone1"], 
				"user_nb_empresa" 		=> $_POST["empresa"],
				"user_nb_userCadastro" 	=> $_SESSION["user_nb_id"], 
				"user_tx_dataCadastro" 	=> date("Y-m-d H:i:s")
			];
			foreach($user_infos as $key => $value){
				if(empty($value)){
					unset($user_infos[$key]);
				}
			}

			inserir("user", array_keys($user_infos), array_values($user_infos));
		}else{ // Se está editando um motorista existente

			$a_user = carrega_array(query(
				"SELECT * FROM user 
					WHERE user_nb_entidade = ".$_POST["id"]."
						AND user_tx_nivel IN ('Motorista', 'Ajudante')"
			));

			$_POST["nivel"] = $_POST["ocupacao"];

			if($a_user["user_nb_id"] > 0){
				$user_infos = [
					"user_tx_nome" 			=> $_POST["nome"], 
					"user_tx_login" 		=> (!empty($_POST["login"])? $_POST["login"]: $_POST["postMatricula"]), 
					"user_tx_nivel" 		=> $_POST["nivel"],
					"user_tx_status" 		=> $_POST["status"], 
					"user_nb_entidade" 		=> $_POST["id"],
					"user_tx_nascimento" 	=> $_POST["nascimento"], 
					"user_tx_cpf" 			=> $_POST["cpf"], 
					"user_tx_rg" 			=> $_POST["rg"], 
					"user_nb_cidade" 		=> $_POST["cidade"], 
					"user_tx_email" 		=> $_POST["email"], 
					"user_tx_fone" 			=> $_POST["fone1"],
					"user_nb_empresa" 		=> $_POST["empresa"],
					"user_nb_userAtualiza" 	=> $_SESSION["user_nb_id"], 
					"user_tx_dataAtualiza" 	=> date("Y-m-d H:i:s")
				];
				foreach($user_infos as $key => $value){
					if(empty($value)){
						unset($user_infos[$key]);
					}
				}
				atualizar("user", array_keys($user_infos), array_values($user_infos), $a_user["user_nb_id"]);
			}
			$aEmpresa = carregar("empresa", $_POST["empresa"]);
			if ($aEmpresa["empr_nb_parametro"] > 0) {
				$aParametro = carregar("parametro", $aEmpresa["empr_nb_parametro"]);
				if (
					$aParametro["para_tx_jornadaSemanal"] != $a_mod["enti_tx_jornadaSemanal"] ||
					$aParametro["para_tx_jornadaSabado"] != $a_mod["enti_tx_jornadaSabado"] ||
					$aParametro["para_tx_percHESemanal"] != $a_mod["enti_tx_percHESemanal"] ||
					$aParametro["para_tx_percHEEx"] != $a_mod["enti_tx_percHEEx"] ||
					$aParametro["para_nb_id"] != $a_mod["enti_nb_parametro"]
				) {
					$ehPadrao = "nao";
				} else {
					$ehPadrao = "sim";
				}
			}
			$novoMotorista["enti_nb_userAtualiza"] = $_SESSION["user_nb_id"];
			$novoMotorista["enti_tx_dataAtualiza"] = date("Y-m-d H:i:s");
			$novoMotorista["enti_tx_ehPadrao"] = $ehPadrao;

			if (!empty($novoMotorista['enti_tx_status']) && $novoMotorista['enti_tx_status'] === 'inativo') {
				$novoMotorista['enti_tx_desligamento'] = date("Y-m-d H:i:s");
			} else {
				unset($novoMotorista['enti_tx_desligamento']);
			}

			atualizar("entidade", array_keys($novoMotorista), array_values($novoMotorista), $_POST["id"]);
			$id = $_POST["id"];
		}

		$file_type = $_FILES["cnhAnexo"]["type"]; //returns the mimetype

		$allowed = ["image/jpeg", "image/gif", "image/png", "application/pdf"];
		if (in_array($file_type, $allowed) && $_FILES["cnhAnexo"]["name"] != "") {

			if (!is_dir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]")) {
				mkdir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]", 0777, true);
			}

			$arq = enviar("cnhAnexo", "arquivos/empresa/".$_POST["empresa"]."/motoristas/".$_POST["matricula"]."/", "CNH_" . $id . "_" . $_POST["postMatricula"]);
			if ($arq) {
				atualizar("entidade", ["enti_tx_cnhAnexo"], [$arq], $id);
			}
		}
		
		
		$idUserFoto = mysqli_fetch_assoc(query("SELECT user_nb_id FROM user WHERE user_nb_entidade = '".$id."' LIMIT 1;"));
		$file_type = $_FILES["foto"]["type"]; //returns the mimetype

		$allowed = ["image/jpeg", "image/gif", "image/png"];
		if (in_array($file_type, $allowed) && $_FILES["foto"]["name"] != "") {

			if (!is_dir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]")) {
				mkdir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]", 0777, true);
			}

			$arq = enviar("foto", "arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]/", "FOTO_".$id."_".$_POST["postMatricula"]);
			if($arq){
				atualizar("entidade", ["enti_tx_foto"], [$arq], $id);
				atualizar("user", ["user_tx_foto"], [$arq], $idUserFoto["user_nb_id"]);
			}
		}

		$_POST["id"] = $id;
		index();
		exit;
	}

	function modificarMotorista(){
		global $a_mod;
		$a_mod = carregar("entidade", $_POST["id"]);

		visualizarCadastro();
		exit;
	}

	function excluirMotorista(){
		remover("entidade", $_POST["id"]);

		index();
		exit;
	}

	function excluirFoto(){
		atualizar("entidade", ["enti_tx_foto"], [""], $_POST["idEntidade"]);
		$_POST["id"] = $_POST["idEntidade"];
		modificarMotorista();
		exit;
	}

	function excluirCNH(){
		atualizar("entidade", ["enti_tx_cnhAnexo"], [""], $_POST["idEntidade"]);
		$_POST["id"] = $_POST["idEntidade"];
		modificarMotorista();
		exit;
	}

	function visualizarCadastro(){
		global $a_mod;
		
		if(!empty($a_mod["enti_nb_empresa"])){
			carregarParametroPadrao($a_mod["enti_nb_empresa"]);
		}
		
		if(empty($a_mod) && !empty($_POST)){
			if(isset($_POST["id"])){
				$a_mod = carregar("entidade", $_POST["id"]);
			}

			$campos = mysqli_fetch_all(query("SHOW COLUMNS FROM entidade;"));

			for($f = 0; $f < sizeof($campos); $f++){
				$campos[$f] = $campos[$f][0];
			}
			
			foreach($campos as $campo){
				if(isset($_POST[$campo]) && !empty($_POST[$campo])){
					$a_mod[$campo] = $_POST[str_replace(["enti_tx_", "enti_nb_"], "", $campo)];
				}
			}
		}

		if(!empty($a_mod["enti_nb_id"])){
			$login = mysqli_fetch_all(
				query(
					"SELECT user_tx_login FROM user 
						WHERE ".$a_mod["enti_nb_id"]." = user_nb_entidade 
						LIMIT 1"
				),
				MYSQLI_ASSOC
			)[0];
			$a_mod["user_tx_login"] = $login["user_tx_login"];
		}
		
		cabecalho("Cadastro de Motorista");

		if(!empty($a_mod["enti_tx_nascimento"])){
			$data1 = new DateTime($a_mod["enti_tx_nascimento"]);
			$data2 = new DateTime(date("Y-m-d"));
	
			$intervalo = $data1->diff($data2);
	
			$idade = "{$intervalo->y} anos, {$intervalo->m} meses e {$intervalo->d} dias";
		}
		

		$UFs = ["", "AC", "AL", "AP", "AM", "BA", "CE", "DF", "ES", "GO", "MA", "MT", "MS", "MG", "PA", "PB", "PR", "PE", "PI", "RJ", "RN", "RS", "RO", "RR", "SC", "SP", "SE", "TO"];
		
		if(!empty($a_mod["enti_tx_foto"])){
			$img = texto(
				"<a style='color:gray' onclick='javascript:remover_foto(\"".($a_mod["enti_nb_id"]?? "")."\",\"excluirFoto\",\"\",\"\",\"\",\"\");' >
					<spam class='glyphicon glyphicon-remove'></spam>
					Excluir
				</a>", 
				"<img style='width: 100%;' src='".($a_mod["enti_tx_foto"]?? "")."' />", 
				2
			);
		}else{
			$img = texto(
				"", 
				"<img style='width: 100%;' src='../contex20/img/driver.png' />",
				2
			);
		}
		
		$tabIndex = 1;

		$camposImg = [
			$img,
			arquivo("Arquivo (.png, .jpg)", "foto", ($a_mod["enti_tx_foto"]?? ""), 4, "tabindex=".sprintf("%02d", $tabIndex++))
		];

		$statusOpt = ["ativo" => "Ativo", "inativo" => "Inativo"];
		$estadoCivilOpt = ["", "Casado(a)", "Solteiro(a)", "Divorciado(a)", "Viúvo(a)"];
		$sexoOpt = ["", "Feminino", "Masculino"];

		$camposUsuario = [
			campo("E-mail*", 				"email", 			($a_mod["enti_tx_email"]?? ""),			2, "", 					"tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Telefone 1*", 			"fone1", 			($a_mod["enti_tx_fone1"]?? ""),			2, "MASCARA_CEL", 		"tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Telefone 2",  			"fone2", 			($a_mod["enti_tx_fone2"]?? ""),			2, "MASCARA_CEL", 		"tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Login",					"login", 			($a_mod["user_tx_login"]?? ""),			2, "", 					"tabindex=".sprintf("%02d", $tabIndex++)),
			combo("Status", 				"status", 			($a_mod["enti_tx_status"]?? ""),		1, $statusOpt, 			"tabindex=".sprintf("%02d", $tabIndex++))
		];

		$camposPessoais = [
			((!empty($_POST["id"]))?
				texto("Matrícula*", $a_mod["enti_tx_matricula"], 2, "tabindex=".sprintf("%02d", $tabIndex++)." maxlength=10"):
				campo("Matrícula*", "postMatricula", ($a_mod["enti_tx_matricula"]?? ""), 2, "", "tabindex=".sprintf("%02d", $tabIndex++)." maxlength=10")
			)
		];

		$camposPessoais = array_merge($camposPessoais, [
			campo(	  	"Nome*", 				"nome", 			($a_mod["enti_tx_nome"]?? ""),			4, "",					"maxlength='65' tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data(	"Dt. Nascimento*", 		"nascimento", 		($a_mod["enti_tx_nascimento"]?? ""),	2, 						"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"CPF*", 				"cpf", 				($a_mod["enti_tx_cpf"]?? ""),			2, "MASCARA_CPF", 		"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"RG*", 					"rg", 				($a_mod["enti_tx_rg"]?? ""),			2, "MASCARA_RG", 		"tabindex=".+sprintf("%02d", $tabIndex++).", maxlength=11"),
			combo(		"Estado Civil", 		"civil", 			($a_mod["enti_tx_civil"]?? ""),			2, $estadoCivilOpt, 	"tabindex=".sprintf("%02d", $tabIndex++)),
			combo(		"Sexo", 				"sexo", 			($a_mod["enti_tx_sexo"]?? ""),			2, $sexoOpt, 			"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Emissor RG", 			"rgOrgao", 			($a_mod["enti_tx_rgOrgao"]?? ""),		3, "",					"maxlength='6' tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data(	"Data Emissão RG", 		"rgDataEmissao", 	($a_mod["enti_tx_rgDataEmissao"]?? ""),	2, 						"tabindex=".sprintf("%02d", $tabIndex++)),
			combo(		"UF RG", 				"rgUf", 			($a_mod["enti_tx_rgUf"]?? ""),			2, $UFs, 				"tabindex=".sprintf("%02d", $tabIndex++)),
			"<div class='col-sm-2 margin-bottom-5' style='width:100%; height:25px'></div>",

			campo(	  	"CEP*", 				"cep", 				($a_mod["enti_tx_cep"]?? ""),			2, "MASCARA_CEP", 		"onfocusout='buscarCEP(this.value);' tabindex=".sprintf("%02d", $tabIndex++)),
			combo_net(	"Cidade/UF*", 			"cidade", 			($a_mod["enti_nb_cidade"]?? ""),		3, "cidade", 			"tabindex=".sprintf("%02d", $tabIndex++), "", "cida_tx_uf"),
			campo(	  	"Bairro*", 				"bairro", 			($a_mod["enti_tx_bairro"]?? ""),		2, "", 					"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Endereço*", 			"endereco", 		($a_mod["enti_tx_endereco"]?? ""),		3, "", 					"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Número", 				"numero", 			($a_mod["enti_tx_numero"]?? ""),		1, "MASCARA_NUMERO", 	"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Complemento", 			"complemento", 		($a_mod["enti_tx_complemento"]?? ""),	2, "", 					"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Ponto de Referência", 	"referencia", 		($a_mod["enti_tx_referencia"]?? ""),	3, "", 					"tabindex=".sprintf("%02d", $tabIndex++)),
			"<div class='col-sm-2 margin-bottom-5' style='width:100%; height:25px'></div>",

			campo(	  	"Filiação Pai", 		"pai", 				($a_mod["enti_tx_pai"]?? ""),			3, "", 					"maxlength='65' tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Filiação Mãe", 		"mae", 				($a_mod["enti_tx_mae"]?? ""),			3, "", 					"maxlength='65' tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Nome do Cônjuge",	 	"conjugue", 		($a_mod["enti_tx_conjugue"]?? ""),		3, "", 					"maxlength='65' tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Tipo de Operação", 	"tipoOperacao", 	($a_mod["enti_tx_tipoOperacao"]?? ""),	3, "", 					"maxlength='40' tabindex=".sprintf("%02d", $tabIndex++)),

			textarea(	"Observações:", "obs", ($a_mod["enti_tx_obs"]?? ""), 12, "tabindex=".sprintf("%02d", $tabIndex++))
		]);

		$extraEmpresa = "";
		if ($_SESSION["user_nb_empresa"] > 0 && is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
			$extraEmpresa = " AND empr_nb_id = '".$_SESSION["user_nb_empresa"]."'";
		}
		$campoSalario = "";
		if (is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
			$campoSalario = campo("Salário*", "salario", valor(($a_mod["enti_nb_salario"]?? "0")), 1, "MASCARA_VALOR", "tabindex=".sprintf("%02d", $tabIndex+2));
		}

		$cContratual = [
			combo_bd("Empresa*", "empresa", ($a_mod["enti_nb_empresa"]?? ""), 3, "empresa", "onchange='carregarEmpresa(this.value)' tabindex=".sprintf("%02d", $tabIndex++), $extraEmpresa),
			$campoSalario
		];
		$tabIndex++;
		$cContratual = array_merge($cContratual, [
			combo("Ocupação*", "ocupacao", ($a_mod["enti_tx_ocupacao"]?? ""), 2, ["Motorista", "Ajudante"], "tabindex=".sprintf("%02d", $tabIndex++)." onchange=checkOcupation(this.value)"),
			campo_data("Dt Admissão*", "admissao", ($a_mod["enti_tx_admissao"]?? ""), 2, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data("Dt. Desligamento", "desligamento", (($a_mod["enti_tx_desligamento"] || $data === '0001-01-01') ? "" : $a_mod["enti_tx_desligamento"]), 2, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Saldo de Horas", "setBanco", ($a_mod["enti_tx_banco"]?? "00:00"), 1, "MASCARA_HORAS", "placeholder='HH:mm' tabindex=".sprintf("%02d", $tabIndex++)),
			combo("Subcontratado", "subcontratado", ($a_mod["enti_tx_subcontratado"]?? ""), 2, ["" => "", "sim" => "Sim", "nao" => "Não"], "tabindex=".sprintf("%02d", $tabIndex++)),
		]);

		if (!empty($a_mod["enti_nb_empresa"])){
			$icone_padronizar = "<a id='padronizarParametro' style='text-shadow: none; color: #337ab7;' onclick='javascript:padronizarParametro();' > (Padronizar) </a>";
		}

		if(!empty($a_mod["parametroPadrao"])){
			$conferirPadraoJS = "conferirParametroPadrao(
				'".$a_mod["parametroPadrao"]["para_nb_id"]
				."', '".$a_mod["parametroPadrao"]["para_tx_jornadaSemanal"]
				."', '".$a_mod['parametroPadrao']['para_tx_jornadaSabado']
				."', '".$a_mod['parametroPadrao']['para_tx_percHESemanal']
				."', '".$a_mod['parametroPadrao']['para_tx_percHEEx']
			."')";
		}else{
			$conferirPadraoJS = "";
		}

		$cJornada = [
			combo_bd(	"Parâmetros da Jornada*".($icone_padronizar?? ""), "parametro", ($a_mod["enti_nb_parametro"]?? ""), 6, "parametro", "onfocusout='carregarParametro()' onchange='carregarParametro()' tabindex=".sprintf("%02d", $tabIndex++)),
			"<div class='col-sm-2 margin-bottom-5' style='width:100%; height:25px'></div>",
			campo_hora(	"Jornada Semanal (Horas/Dia)*", "jornadaSemanal", ($a_mod["enti_tx_jornadaSemanal"]?? ""), 2, "tabindex=".sprintf("%02d", $tabIndex++)." onchange='".$conferirPadraoJS."'"),
			campo_hora(	"Jornada Sábado (Horas/Dia)*", "jornadaSabado", ($a_mod["enti_tx_jornadaSabado"]?? ""), 2, "tabindex=".sprintf("%02d", $tabIndex++)." onchange='".$conferirPadraoJS."'"),
			campo(		"H.E. Semanal (%)*", "percHESemanal", ($a_mod["enti_tx_percHESemanal"]?? ""), 2, "MASCARA_NUMERO", "tabindex=".sprintf("%02d", $tabIndex++)." onchange='".$conferirPadraoJS."'"),
			campo(		"H.E. Extraordinária (%)*", "percHEEx", ($a_mod["enti_tx_percHEEx"]?? ""), 2, "MASCARA_NUMERO", "tabindex=".sprintf("%02d", $tabIndex++)." onchange='".$conferirPadraoJS."'")
		];
		if(!empty($a_mod["enti_nb_empresa"])){
			$aEmpresa = carregar("empresa", (int)$a_mod["enti_nb_empresa"]);
			$aParametro = carregar("parametro", $aEmpresa["empr_nb_parametro"]);

			$padronizado = (
				$a_mod["enti_tx_jornadaSemanal"] 		== $aParametro["para_tx_jornadaSemanal"] &&
				$a_mod["enti_tx_jornadaSabado"] 		== $aParametro["para_tx_jornadaSabado"] &&
				$a_mod["enti_tx_percHESemanal"] 			== $aParametro["para_tx_percHESemanal"] &&
				$a_mod["enti_tx_percHEEx"] 	== $aParametro["para_tx_percHEEx"]
			);
			
			$cJornada[]=texto("Convenção Padrão?", ($padronizado? "Sim": "Não"), 2, "name='textoParametroPadrao'");
		}

		$iconeExcluirCNH = "";
		if (!empty($a_mod["enti_tx_cnhAnexo"])){
			$iconeExcluirCNH = "<a style='text-shadow: none; color: #337ab7;' onclick='javascript:remover_cnh(\"".$a_mod["enti_nb_id"]."\",\"excluirCNH\",\"\",\"\",\"\",\"Deseja excluir a CNH?\");' > (Excluir) </a>";
		}

		$cCNH = [
			campo("N° Registro*", "cnhRegistro", ($a_mod["enti_tx_cnhRegistro"]?? ""), 3,"","maxlength='11' tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Categoria*", "cnhCategoria", ($a_mod["enti_tx_cnhCategoria"]?? ""), 3, "", "tabindex=".sprintf("%02d", $tabIndex++)),
			combo_net("Cidade/UF Emissão*", "cnhCidade", ($a_mod["enti_nb_cnhCidade"]?? ""), 3, "cidade", "tabindex=".sprintf("%02d", $tabIndex++), "", "cida_tx_uf"),
			campo_data("Data Emissão*", "cnhEmissao", ($a_mod["enti_tx_cnhEmissao"]?? ""), 3, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data("Validade*", "cnhValidade", ($a_mod["enti_tx_cnhValidade"]?? ""), 3, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data("1º Habilitação*", "cnhPrimeiraHabilitacao", ($a_mod["enti_tx_cnhPrimeiraHabilitacao"]?? ""), 3, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Permissão", "cnhPermissao", ($a_mod["enti_tx_cnhPermissao"]?? ""), 3,"","maxlength='65' tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Pontuação", "cnhPontuacao", ($a_mod["enti_tx_cnhPontuacao"]?? ""), 3,"","maxlength='3' tabindex=".sprintf("%02d", $tabIndex++)),
			combo("Atividade Remunerada", "cnhAtividadeRemunerada", ($a_mod["enti_tx_cnhAtividadeRemunerada"]?? ""), 3, ["" => "", "sim" => "Sim", "nao" => "Não"], "tabindex=".sprintf("%02d", $tabIndex++)),
			arquivo("CNH (.png, .jpg, .pdf)" . $iconeExcluirCNH, "cnhAnexo", ($a_mod["enti_tx_cnhAnexo"]?? ""), 4, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Observações", "cnhObs", ($a_mod["enti_tx_cnhObs"]?? ""), 3,"","maxlength='500' tabindex=".sprintf("%02d", $tabIndex++))
		];


		// $campos = [
		// 	"id" => $_POST["id"],
		// 	"matricula" => $a_mod["enti_tx_matricula"]
		// ];
		$botoesCadastro[] = botao(
			"Gravar", 
			"cadastrarMotorista", 
			((empty($_POST["id"]) || empty($a_mod["enti_tx_matricula"]))? "": "id,matricula"),
			((empty($_POST["id"]) || empty($a_mod["enti_tx_matricula"]))? "": $_POST["id"].",".$a_mod["enti_tx_matricula"]),
			"tabindex=53",
			"",
			"btn btn-success"
		);

		$botoesCadastro[] = botao("Voltar", "voltar", "", "", "tabindex=54");

		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], "cadastro_motorista.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_motorista.php";
			}
		}

		abre_form();
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		fieldset("Dados de Usuário");
		linha_form($camposUsuario);
		echo "<br>";
		fieldset("Dados Pessoais");
		linha_form($camposPessoais);
		echo "<br>";
		fieldset("Foto");
		echo "<div class='imageForm'>";
		linha_form($camposImg);
		echo "</div>";
		echo "<br>";
		fieldset("Dados Contratuais");
		linha_form($cContratual);
		echo "<br>";
		fieldset("CONVENÇÃO SINDICAL - JORNADA PADRÃO DO MOTORISTA");
		linha_form($cJornada);
		echo "<br>";
		echo "<div class='cnh-row'>";
			fieldset("CARTEIRA NACIONAL DE HABILITAÇÃO");
			linha_form($cCNH);
		echo "</div>";

		if (!empty($a_mod["enti_nb_userCadastro"])) {
			$a_userCadastro = carregar("user", $a_mod["enti_nb_userCadastro"]);
			$txtCadastro = "Registro inserido por $a_userCadastro[user_tx_login] às " . data($a_mod["enti_tx_dataCadastro"]) . ".";
			$cAtualiza[] = texto("Data de Cadastro", "$txtCadastro", 5);
			if ($a_mod["enti_nb_userAtualiza"] > 0) {
				$a_userAtualiza = carregar("user", $a_mod["enti_nb_userAtualiza"]);
				$txtAtualiza = "Registro atualizado por $a_userAtualiza[user_tx_login] às " . data($a_mod["enti_tx_dataAtualiza"], 1) . ".";
				$cAtualiza[] = texto("Última Atualização", strval($txtAtualiza), 5);
			}
			echo "<br>";
			linha_form($cAtualiza);
		}

		$path_parts = pathinfo(__FILE__);

		$params = [
			$a_mod["parametroPadrao"]["para_nb_id"],
			$a_mod["parametroPadrao"]["para_tx_jornadaSemanal"],
			$a_mod["parametroPadrao"]["para_tx_jornadaSabado"],
			$a_mod["parametroPadrao"]["para_tx_percHESemanal"],
			$a_mod["parametroPadrao"]["para_tx_percHEEx"]
		];

		echo 
			"<iframe id=frame_parametro style='display: none;'></iframe>
			<script>
				function buscarCEP(cep) {
					var num = cep.replace(/[^0-9]/g, '');
					if (num.length == '8') {
						document.getElementById('frame_parametro').src = '".$path_parts["basename"]."?acao=carregarEndereco&cep='+num;
					}
				}

				function carregarEmpresa(id) {
					document.getElementById('frame_parametro').src = 'cadastro_motorista.php?acao=carregarEmpresa&emp='+id;
					var empresaSelecionada = id;
				}

				function carregarParametro() {
					id = document.getElementById('parametro').value;
					document.getElementById('frame_parametro').src = 'cadastro_motorista.php?acao=carregarParametro&parametro='+id;"
					.((!empty($a_mod["parametroPadrao"]))? "conferirParametroPadrao('".implode("','", $params)."');":"")."
				}
				function padronizarParametro() {
					parent.document.contex_form.parametro.value 			= '".($a_mod["parametroPadrao"]["para_nb_id"]?? "")."';
					parent.document.contex_form.jornadaSemanal.value 		= '".($a_mod["parametroPadrao"]["para_tx_jornadaSemanal"]?? "")."';
					parent.document.contex_form.jornadaSabado.value 		= '".($a_mod["parametroPadrao"]["para_tx_jornadaSabado"]?? "")."';
					parent.document.contex_form.percHESemanal.value 			= '".($a_mod["parametroPadrao"]["para_tx_percHESemanal"]?? "")."';
					parent.document.contex_form.percHEEx.value 	= '".($a_mod["parametroPadrao"]["para_tx_percHEEx"]?? "")."';

					conferirParametroPadrao('".implode("','", $params)."');
				}

				function conferirParametroPadrao(idParametro, jornadaSemanal, jornadaSabado, percHESemanal, percHEEx){

					var padronizado = (
						idParametro == parent.document.contex_form.parametro.value &&
						jornadaSemanal == parent.document.contex_form.jornadaSemanal.value &&
						jornadaSabado == parent.document.contex_form.jornadaSabado.value &&
						percHESemanal == parent.document.contex_form.percHESemanal.value &&
						percHEEx == parent.document.contex_form.percHEEx.value
					);
					console.log([idParametro, jornadaSemanal, jornadaSabado, percHESemanal, percHEEx]);
					parent.document.getElementsByName('textoParametroPadrao')[0].getElementsByTagName('p')[0].innerText = (padronizado? 'Sim': 'Não');
				}

				function checkOcupation(ocupation){
					console.log(ocupation);
					if(ocupation == 'Ajudante'){
						document.getElementsByClassName('cnh-row')[0].setAttribute('style', 'display:none')
					}else{
						document.getElementsByClassName('cnh-row')[0].setAttribute('style', '')
					}
				}
			</script>"
		;

		fecha_form($botoesCadastro);
		rodape();
		
		echo 
			"<form method='post' name='form_modifica' id='form_modifica'>
				<input type='hidden' name='id' value=''>
				<input type='hidden' name='acao' value='modificarMotorista'>
			</form>

			<form name='form_excluir_arquivo' method='post' action='cadastro_motorista.php'>
				<input type='hidden' name='idEntidade' value=''>
				<input type='hidden' name='nome_arquivo' value=''>
				<input type='hidden' name='acao' value=''>
			</form>

			<script type='text/javascript'>
				function remover_foto(id, acao, arquivo) {
					if (confirm('Deseja realmente excluir o arquivo '+arquivo+'?')) {
						document.form_excluir_arquivo.idEntidade.value = id;
						document.form_excluir_arquivo.nome_arquivo.value = arquivo;
						document.form_excluir_arquivo.acao.value = acao;
						document.form_excluir_arquivo.submit();
					}
				}

				function remover_cnh(id, acao, arquivo) {
					if (confirm('Deseja realmente excluir o arquivo CNH '+arquivo+'?')) {
						document.form_excluir_arquivo.idEntidade.value = id;
						document.form_excluir_arquivo.nome_arquivo.value = arquivo;
						document.form_excluir_arquivo.acao.value = acao;
						document.form_excluir_arquivo.submit();
					}
				}
			</script>"
		;
	}

	function index(){
		cabecalho("Cadastro de Motorista");

		$extraEmpresa = "";
		if ($_SESSION["user_nb_empresa"] > 0 && is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
			$extraEmpresa = " AND empr_nb_id = '".$_SESSION["user_nb_empresa"]."'";
		}
		
		if(!empty($_POST["busca_cpf"])){
			$_POST["busca_cpf"] = preg_replace( "/[^0-9]/is", "", $_POST["busca_cpf"]);
		}

		$extra =
			((!empty($_POST["busca_codigo"]))? 		" AND enti_nb_id LIKE '%".$_POST["busca_codigo"]."%'": "").
			((!empty($_POST["busca_matricula"]))? 	" AND enti_tx_matricula LIKE '%".$_POST["busca_matricula"]."%'": "").
			((!empty($_POST["busca_empresa"]))? 	" AND enti_nb_empresa = '".$_POST["busca_empresa"]."'": "").
			((!empty($_POST["busca_nome"]))? 		" AND enti_tx_nome LIKE '%".$_POST["busca_nome"]."%'": "").
			((!empty($_POST["busca_cpf"]))? 		" AND enti_tx_cpf LIKE '%".$_POST["busca_cpf"]."%'": "").
			((!empty($_POST["busca_ocupacao"]))? 	" AND enti_tx_ocupacao = '".$_POST["busca_ocupacao"]."'": "").
			((!empty($_POST["busca_parametro"]))? 	" AND enti_nb_parametro = '".$_POST["busca_parametro"]."'": "").
			(!empty($_POST["busca_status"])?		" AND enti_tx_status = '".strtolower($_POST["busca_status"])."'": "").
			(!empty($_POST["busca_padrao"])?		" AND enti_tx_ehPadrao = '".$_POST["busca_padrao"]."'": "");

			$camposBusca = [ 
				campo("Código", "busca_codigo", ($_POST["busca_codigo"]?? ""), 1,"","maxlength='6'"),
				campo("Nome", "busca_nome", ($_POST["busca_nome"]?? ""), 2,"","maxlength='65'"),
				campo("Matrícula", "busca_matricula", ($_POST["busca_matricula"]?? ""), 1,"","maxlength='6'"),
				campo("CPF", "busca_cpf", ($_POST["busca_cpf"]?? ""), 2, "MASCARA_CPF"),
				combo_bd("!Empresa", "busca_empresa", ($_POST["busca_empresa"]?? ""), 2, "empresa", "", $extraEmpresa),
				combo("Ocupação", "busca_ocupacao", ($_POST["busca_ocupacao"]?? ""), 2, ["", "Motorista", "Ajudante"]),
				combo("Convenção Padrão", "busca_padrao", ($_POST["busca_padrao"]?? ""), 2, ["" => "todos", "sim" => "Sim", "nao" => "Não"]),
				combo_bd("!Parâmetros da Jornada", "busca_parametro", ($_POST["busca_parametro"]?? ""), 6, "parametro"),
				combo("Status", "busca_status", ($_POST["busca_status"]?? ""), 2, ["" => "todos", "ativo" => "Ativo", "inativo" => "Inativo"])
			];

		$botoesBusca = [
			botao("Buscar", "index"),
			botao("Inserir", "visualizarCadastro","","","","","btn btn-success")
		];

		abre_form("Filtro de Busca");
		linha_form($camposBusca);
		fecha_form($botoesBusca);

		$icone_modificar = "icone_modificar(enti_nb_id,modificarMotorista)";

		if (is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
			$icone_excluir = "icone_excluir(enti_nb_id,excluirMotorista)";
		}else{
			$icone_excluir = "";
		}

		$gridFields = [
			"CÓDIGO" 				=> "enti_nb_id", 
			"NOME" 					=> "enti_tx_nome", 
			"MATRÍCULA" 			=> "enti_tx_matricula", 
			"CPF" 					=> "enti_tx_cpf", 
			"EMPRESA" 				=> "empr_tx_nome", 
			"FONE 1" 				=> "enti_tx_fone1", 
			"FONE 2" 				=> "enti_tx_fone2", 
			"OCUPAÇÃO" 				=> "enti_tx_ocupacao",
			"DATA CADASTRO" 		=> "data(enti_tx_dataCadastro)",
			"DATA INATIVAÇÃO"       => "data(enti_tx_desligamento)",
			"PARÂMETRO DA JORNADA" 	=> "para_tx_nome", 
			"CONVENÇÃO PADRÃO" 		=> "enti_tx_ehPadrao",
			"STATUS" 				=> "enti_tx_status"
		];

		$sqlFields = [
			"enti_nb_id", 
			"enti_tx_nome", 
			"enti_tx_matricula", 
			"enti_tx_cpf", 
			"empr_tx_nome", 
			"enti_tx_fone1", 
			"enti_tx_fone2", 
			"enti_tx_ocupacao",
			"enti_tx_dataCadastro",
			"enti_tx_desligamento",
			"para_tx_nome", 
			"enti_tx_ehPadrao",
			"enti_tx_status"
		]; 

		$sql = ( 
			"SELECT ".implode(", ", array_values($sqlFields))." FROM entidade 
				JOIN empresa ON enti_nb_empresa = empr_nb_id 
				JOIN parametro ON enti_nb_parametro = para_nb_id 
				WHERE enti_tx_ocupacao IN ('Motorista', 'Ajudante') 
					$extraEmpresa 
					$extra"
		);

		$gridFields = array_merge($gridFields, [
			"<spam class='glyphicon glyphicon-search'></spam>" => $icone_modificar, 
			"<spam class='glyphicon glyphicon-remove'></spam>" => $icone_excluir
		]);
		
		grid($sql, array_keys($gridFields), array_values($gridFields));
		rodape();
	}