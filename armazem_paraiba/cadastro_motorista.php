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
					parent.document.contex_form.parametro.value = '{$aEmpresa["empr_nb_parametro"]}';
					parent.document.contex_form.parametro.onchange();
				</script>"
			;
		}
		exit;
	}

	function carregarParametroPadrao(int $idEmpresa = 0){
		global $a_mod;

		if(!empty($idEmpresa) && !empty($a_mod["enti_nb_empresa"])){
			$idEmpresa = intval($a_mod["enti_nb_empresa"]);
		}else{
			$idEmpresa = -1;
		}

		$a_mod["parametroPadrao"] = mysqli_fetch_assoc(query(
			"SELECT parametro.* FROM empresa
				JOIN parametro ON empresa.empr_nb_parametro = parametro.para_nb_id
				WHERE para_tx_status = 'ativo'
					AND empresa.empr_nb_id = {$idEmpresa}
				LIMIT 1;"
		));
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
						console.log(data);
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

	function showError(string $msg, array $errorFields){
		$_POST["errorFields"] = $errorFields;
		set_status("ERRO: {$msg}");
		visualizarCadastro();
		exit;
	}

	function cadastrarMotorista(){
		global $a_mod;

		if(!empty($_POST["matricula"])){
			$_POST["postMatricula"] = $_POST["matricula"];
		}
		if(!in_array($_ENV["CONTEX_PATH"], ["/comav"])){
			while($_POST["postMatricula"][0] == "0"){
				$_POST["postMatricula"] = substr($_POST["postMatricula"], 1);
			}
		}

		$_POST["cpf"] = preg_replace("/[^0-9]/is", "", $_POST["cpf"]);
		$_POST["rg"] = preg_replace("/[^0-9]/is", "", $_POST["rg"]);

		$enti_campos = [
			"enti_tx_matricula" 				=> "postMatricula", 
			"enti_tx_nome" 						=> "nome", 
			"enti_tx_nascimento" 				=> "nascimento", 
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
			"enti_tx_percHEEx" 					=> "percHEEx",
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
		$postKeys = array_values($enti_campos);

		foreach($enti_campos as $bdKey => $postKey){
			if(!empty($_POST[$postKey])){
				$a_mod[$bdKey] = $_POST[$postKey];
				$novoMotorista[$bdKey] = $_POST[$postKey];
			}
		}
		if(!empty($_POST["desligamento"])){
			$novoMotorista["enti_tx_desligamento"] = $_POST["desligamento"];
		}
		unset($enti_campos);

		if(isset($novoMotorista["enti_nb_salario"])){
			$novoMotorista["enti_nb_salario"] = str_replace([".", ","], ["", "."], $novoMotorista["enti_nb_salario"]);
		}


		//Conferir se os campos obrigatórios estão preenchidos{
			$camposObrig = [
				"nome" 						=> "Nome", 
				"nascimento" 				=> "Nascido em",
				"cpf" 						=> "CPF",
				"rg" 						=> "RG",
				"bairro" 					=> "Bairro",
				"cep" 						=> "CEP",
				"endereco" 					=> "Endereço",
				"cidade" 					=> "Cidade/UF",
				"fone1" 					=> "Telefone 1",
				"email" 					=> "E-mail",
				"empresa" 					=> "Empresa",
				"salario" 					=> "Salário",
				"ocupacao" 					=> "Ocupação",
				"admissao" 					=> "Dt Admissão",
				"parametro" 				=> "Parâmetro",
				"jornadaSemanal" 			=> "Jornada Semanal",
				"jornadaSabado" 			=> "Jornada Sábado",
				"percHESemanal" 			=> "H.E. Semanal",
				"percHEEx" 					=> "H.E. Extraordinária",
				"cnhRegistro" 				=> "N° Registro da CNH",
				"cnhCategoria" 				=> "Categoria do CNH",
				"cnhCidade" 				=> "Cidade do CNH",
				"cnhEmissao" 				=> "Data de Emissão do CNH",
				"cnhValidade" 				=> "Validade do CNH",
				"cnhPrimeiraHabilitacao" 	=> "1° Habilitação"
			];
			if(empty($novoMotorista["enti_tx_matricula"])){
				$camposObrig["postMatricula"] = "Matrícula";
			}
			if(in_array($_POST["ocupacao"], ["Ajudante", "Funcionário"])){
				unset(
					$camposObrig["cnhRegistro"],
					$camposObrig["cnhCategoria"],
					$camposObrig["cnhCidade"],
					$camposObrig["cnhEmissao"],
					$camposObrig["cnhValidade"],
					$camposObrig["cnhPrimeiraHabilitacao"]
				);
			}
			if($_POST["status"] == "inativo"){
				$camposObrig["desligamento"] = "Desligamento";
			}
			$errorMsg = conferirCamposObrig($camposObrig, $_POST);
			if(!empty($errorMsg)){
				showError($errorMsg, $_POST["errorFields"]);
			}
			unset($camposObrig);
		//}

		//Conferir se a matrícula já existe{
			if(!isset($_POST["id"]) && !empty($_POST["postMatricula"])){
				$matriculaExistente = !empty(mysqli_fetch_assoc(query(
					"SELECT * FROM entidade"
						." WHERE enti_tx_matricula = '".($_POST["postMatricula"]?? "-1")."'"
					." LIMIT 1;"
				)));

				$errorMsg = "";
				if($matriculaExistente){
					$errorMsg = "Matrícula já cadastrada.";
				}
				if(strlen($_POST["postMatricula"]) > 11){
					$errorMsg = "Matrícula com mais de 11 caracteres.";
				}

				if(!empty($errorMsg)){
					$_POST["errorFields"][] = "postMatricula";
					showError($errorMsg, ["postMatricula"]);
				}
			}
		//}

		//Conferir se o login já existe{
			if(!empty($_POST["login"])){
				$otherUser = mysqli_fetch_assoc(query(
					"SELECT user.* FROM user
						JOIN entidade ON user_nb_entidade = enti_nb_id
						WHERE user_tx_status = 'ativo'
							AND user_tx_login = '{$_POST["login"]}'"
							.(!empty($_POST["id"])? " AND enti_nb_id <> ".$_POST["id"]: "")
						." LIMIT 1;"
				));
				if(!empty($otherUser)){
					showError("Login já cadastrado.", ["login"]);
				}
			}
		//}

		//Conferir se o CPF é válido{
			if(!validarCPF($novoMotorista["enti_tx_cpf"])){
				showError("CPF inválido.", ["cpf"]);
			}
		//}

		//Conferir se o RG é válido{
			$_POST["rg"] = preg_replace( "/[^0-9]/is", "", $_POST["rg"]);
			if(strlen($_POST["rg"]) < 3){
				$_POST["errorFields"][] = "rg";
				showError("RG Parcial.", ["rg"]);
			}
		//}

		//Conferir se está ativo e a data de demissão é anterior a atual{
			if(!empty($novoMotorista["enti_tx_desligamento"]) && $novoMotorista["enti_tx_status"] == "ativo" && $novoMotorista["enti_tx_desligamento"] < date("Y-m-d")){
				showError("Não é possível colocar uma data de desligamento anterior a hoje enquanto o motorista estiver ativo.", ["status", "desligamento"]);
			}
		//}

		if (empty($_POST["id"])) {//Se está criando um motorista novo
			$aEmpresa = carregar("empresa", $_POST["empresa"]);
			if($aEmpresa["empr_nb_parametro"] > 0){
				$aParametro = carregar("parametro", $aEmpresa["empr_nb_parametro"]);
				if (
					[$aParametro["para_tx_jornadaSemanal"], $aParametro["para_tx_jornadaSabado"], $aParametro["para_tx_percHESemanal"], $aParametro["para_tx_percHEEx"], $aParametro["para_nb_id"]] ==
					[$novoMotorista["enti_tx_jornadaSemanal"], $novoMotorista["enti_tx_jornadaSabado"], $novoMotorista["enti_tx_percHESemanal"], $novoMotorista["enti_tx_percHEEx"], $novoMotorista["enti_nb_parametro"]]
				){
					$ehPadrao = "sim";
				}else{
					$ehPadrao = "nao";
				}
			}
			$novoMotorista["enti_nb_userCadastro"] = $_SESSION["user_nb_id"];
			$novoMotorista["enti_tx_dataCadastro"] = date("Y-m-d H:i:s");
			$novoMotorista["enti_tx_ehPadrao"] = $ehPadrao;
			$id = inserir("entidade", array_keys($novoMotorista), array_values($novoMotorista))[0];

			if(empty($id) || (is_array($id) && get_class($id[0]) == Exception::class)){
				set_status("ERRO ao cadastrar motorista.");
				index();
				exit;
			}
			
			$newUser = [
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
			foreach($newUser as $key => $value){
				if(empty($value)){
					unset($newUser[$key]);
				}
			}

			inserir("user", array_keys($newUser), array_values($newUser));
		}else{ // Se está editando um motorista existente

			$a_user = mysqli_fetch_array(query(
				"SELECT * FROM user 
					WHERE user_nb_entidade = ".$_POST["id"]."
						AND user_tx_nivel IN ('Motorista', 'Ajudante','Funcionário')"
			), MYSQLI_BOTH);

			$_POST["nivel"] = $_POST["ocupacao"];

			if($a_user["user_nb_id"] > 0){
				$newUser = [
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
				foreach($newUser as $key => $value){
					if(empty($value)){
						unset($newUser[$key]);
					}
				}
				atualizar("user", array_keys($newUser), array_values($newUser), $a_user["user_nb_id"]);
			}
			$aEmpresa = carregar("empresa", $_POST["empresa"]);
			if ($aEmpresa["empr_nb_parametro"] > 0) {
				$aParametro = carregar("parametro", $aEmpresa["empr_nb_parametro"]);
				if (
					$aParametro["para_tx_jornadaSemanal"] != $novoMotorista["enti_tx_jornadaSemanal"] ||
					$aParametro["para_tx_jornadaSabado"] != $novoMotorista["enti_tx_jornadaSabado"] ||
					$aParametro["para_tx_percHESemanal"] != $novoMotorista["enti_tx_percHESemanal"] ||
					$aParametro["para_tx_percHEEx"] != $novoMotorista["enti_tx_percHEEx"] ||
					$aParametro["para_nb_id"] != $novoMotorista["enti_nb_parametro"]
				) {
					$ehPadrao = "nao";
				} else {
					$ehPadrao = "sim";
				}
			}
			$novoMotorista["enti_nb_userAtualiza"] = $_SESSION["user_nb_id"];
			$novoMotorista["enti_tx_dataAtualiza"] = date("Y-m-d H:i:s");
			$novoMotorista["enti_tx_ehPadrao"] = $ehPadrao;

			atualizar("entidade", array_keys($novoMotorista), array_values($novoMotorista), $_POST["id"]);
			$id = $_POST["id"];
		}

		$file_type = $_FILES["cnhAnexo"]["type"]; //returns the mimetype

		$allowed = ["image/jpeg", "image/gif", "image/png", "application/pdf"];
		if (in_array($file_type, $allowed) && $_FILES["cnhAnexo"]["name"] != "") {

			if (!is_dir("arquivos/empresa/{$_POST["empresa"]}/motoristas/{$_POST["matricula"]}")) {
				mkdir("arquivos/empresa/{$_POST["empresa"]}/motoristas/{$_POST["matricula"]}", 0777, true);
			}

			$arq = enviar("cnhAnexo", "arquivos/empresa/{$_POST["empresa"]}/motoristas/{$_POST["matricula"]}/", "CNH_{$id}_{$_POST["postMatricula"]}");
			if ($arq) {
				atualizar("entidade", ["enti_tx_cnhAnexo"], [$arq], $id);
			}
		}
		
		
		$idUserFoto = mysqli_fetch_assoc(query("SELECT user_nb_id FROM user WHERE user_nb_entidade = '{$id}' LIMIT 1;"));
		$file_type = $_FILES["foto"]["type"]; //returns the mimetype

		$allowed = ["image/jpeg", "image/gif", "image/png"];
		if (in_array($file_type, $allowed) && $_FILES["foto"]["name"] != "") {

			if (!is_dir("arquivos/empresa/{$_POST["empresa"]}/motoristas/{$_POST["matricula"]}")) {
				mkdir("arquivos/empresa/{$_POST["empresa"]}/motoristas/{$_POST["matricula"]}", 0777, true);
			}

			$arq = enviar("foto", "arquivos/empresa/{$_POST["empresa"]}/motoristas/{$_POST["matricula"]}/", "FOTO_{$id}_{$_POST["postMatricula"]}");
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
		$motorista = mysqli_fetch_assoc(query(
			"SELECT enti_tx_desligamento, user_nb_id FROM entidade 
				LEFT JOIN user ON enti_nb_id = user_nb_entidade
				WHERE enti_tx_status = 'ativo'
					AND enti_nb_id = ".$_POST["id"]."
				LIMIT 1;"
		));

		if(empty($motorista)){
			set_status("Funcionário já inativado.");
			index();
			exit;
		}

		if(empty($motorista["enti_tx_desligamento"]) || $motorista["enti_tx_desligamento"] > date("Y-m-d")){
			$motorista["enti_tx_desligamento"] = date("Y-m-d");
		}
		
		atualizar("entidade", ["enti_tx_status", "enti_tx_desligamento"], ["inativo", $motorista["enti_tx_desligamento"]], $_POST["id"]);
		if(!empty($motorista["user_nb_id"])){
			atualizar("user", ["user_tx_status"], ["inativo"], $motorista["user_nb_id"]);
		}
		
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
		
		cabecalho("Cadastro de Funcionário");

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
				texto("Matrícula*", $a_mod["enti_tx_matricula"], 2, "tabindex=".sprintf("%02d", $tabIndex++)." maxlength=11"):
				campo("Matrícula*", "postMatricula", ($a_mod["enti_tx_matricula"]?? ""), 2, "", "tabindex=".sprintf("%02d", $tabIndex++)." maxlength=11")
			)
		];

		$camposPessoais = array_merge($camposPessoais, [
			campo(	  	"Nome*", 				"nome", 			($a_mod["enti_tx_nome"]?? ""),			4, "",					"maxlength='65' tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data(	"Nascido em*",	 		"nascimento", 		($a_mod["enti_tx_nascimento"]?? ""),	2, 						"tabindex=".sprintf("%02d", $tabIndex++)),
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
			$a_mod["enti_nb_salario"] = str_replace(".", ",", $a_mod["enti_nb_salario"]);
			$campoSalario = campo("Salário*", "salario", (!empty($a_mod["enti_nb_salario"])? $a_mod["enti_nb_salario"] : "0"), 1, "MASCARA_VALOR", "tabindex=".sprintf("%02d", $tabIndex+2));
		}

		$cContratual = [
			combo_bd("Empresa*", "empresa", ($a_mod["enti_nb_empresa"]?? ""), 3, "empresa", "onchange='carregarEmpresa(this.value)' tabindex=".sprintf("%02d", $tabIndex++), $extraEmpresa),
			$campoSalario
		];
		$tabIndex++;
		$cContratual = array_merge($cContratual, [
			combo(		"Ocupação*", 		"ocupacao", 		(!empty($a_mod["enti_tx_ocupacao"])? $a_mod["enti_tx_ocupacao"]		 		:""), 		2, ["Motorista", "Ajudante", "Funcionário"], "tabindex=".sprintf("%02d", $tabIndex++)." onchange=checkOcupation(this.value)"),
			campo_data(	"Dt Admissão*", 	"admissao", 		(!empty($a_mod["enti_tx_admissao"])? $a_mod["enti_tx_admissao"]		 		:""), 		2, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data(	"Dt. Desligamento", "desligamento", 	(!empty($a_mod["enti_tx_desligamento"])? $a_mod["enti_tx_desligamento"] 	:""), 		2, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo(		"Saldo de Horas", 	"setBanco", 		(!empty($a_mod["enti_tx_banco"])? $a_mod["enti_tx_banco"] 					:"00:00"), 	1, "MASCARA_HORAS", "placeholder='HH:mm' tabindex=".sprintf("%02d", $tabIndex++)),
			combo(		"Subcontratado", 	"subcontratado", 	(!empty($a_mod["enti_tx_subcontratado"])? $a_mod["enti_tx_subcontratado"] 	:""), 		2, ["" => "", "sim" => "Sim", "nao" => "Não"], "tabindex=".sprintf("%02d", $tabIndex++)),
		]);

		if (!empty($a_mod["enti_nb_empresa"])){
			$icone_padronizar = "<a id='padronizarParametro' style='text-shadow: none; color: #337ab7;' onclick='javascript:padronizarParametro();' > (Padronizar) </a>";
		}

		$conferirPadraoJS = "";
		if(!empty($a_mod["parametroPadrao"])){
			$conferirPadraoJS = 
				"conferirParametroPadrao(
					\"{$a_mod["parametroPadrao"]["para_nb_id"]}\",
					\"{$a_mod["parametroPadrao"]["para_tx_jornadaSemanal"]}\",
					\"{$a_mod["parametroPadrao"]["para_tx_jornadaSabado"]}\",
					\"{$a_mod["parametroPadrao"]["para_tx_percHESemanal"]}\",
					\"{$a_mod["parametroPadrao"]["para_tx_percHEEx"]}\"
				);";
		}

		$cJornada = [
			combo_bd(	"!Parâmetros da Jornada*".($icone_padronizar?? ""), "parametro", ($a_mod["enti_nb_parametro"]?? ""), 6, "parametro", "onfocusout='carregarParametro()' onchange='carregarParametro()' tabindex=".sprintf("%02d", $tabIndex++)), "<div class='col-sm-2 margin-bottom-5' style='width:100%; height:25px'></div>",
			campo_hora(	"Jornada Semanal (Horas/Dia)*", "jornadaSemanal", ($a_mod["enti_tx_jornadaSemanal"]?? ""), 2, "tabindex=".sprintf("%02d", $tabIndex++)." onchange='{$conferirPadraoJS}'"),
			campo_hora(	"Jornada Sábado (Horas/Dia)*", "jornadaSabado", ($a_mod["enti_tx_jornadaSabado"]?? ""), 2, "tabindex=".sprintf("%02d", $tabIndex++)." onchange='{$conferirPadraoJS}'"),
			campo(		"H.E. Semanal (%)*", "percHESemanal", ($a_mod["enti_tx_percHESemanal"]?? ""), 2, "MASCARA_NUMERO", "tabindex=".sprintf("%02d", $tabIndex++)." onchange='{$conferirPadraoJS}'"),
			campo(		"H.E. Extraordinária (%)*", "percHEEx", ($a_mod["enti_tx_percHEEx"]?? ""), 2, "MASCARA_NUMERO", "tabindex=".sprintf("%02d", $tabIndex++)." onchange='{$conferirPadraoJS}'")
		];
		if(!empty($a_mod["enti_nb_empresa"])){
			$aEmpresa = carregar("empresa", (int)$a_mod["enti_nb_empresa"]);
			$aParametro = carregar("parametro", $aEmpresa["empr_nb_parametro"]);

			$padronizado = (
				$a_mod["enti_tx_jornadaSemanal"] 		== $aParametro["para_tx_jornadaSemanal"] &&
				$a_mod["enti_tx_jornadaSabado"] 		== $aParametro["para_tx_jornadaSabado"] &&
				$a_mod["enti_tx_percHESemanal"] 		== $aParametro["para_tx_percHESemanal"] &&
				$a_mod["enti_tx_percHEEx"] 	== $aParametro["para_tx_percHEEx"]
			);
			
			$cJornada[]=texto("Convenção Padrão?", ($padronizado? "Sim": "Não"), 2, "name='textoParametroPadrao'");
		}

		$iconeExcluirCNH = "";
		if (!empty($a_mod["enti_tx_cnhAnexo"])){
			$iconeExcluirCNH = "<a style='text-shadow: none; color: #337ab7;' onclick='javascript:remover_cnh(\"".$a_mod["enti_nb_id"]."\",\"excluirCNH\",\"\",\"\",\"\",\"Deseja excluir a CNH?\");' > (Excluir) </a>";
		}

		$camposCNH = [
			campo("N° Registro*", "cnhRegistro", ($a_mod["enti_tx_cnhRegistro"]?? ""), 3,"","maxlength='11' tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Categoria*", "cnhCategoria", ($a_mod["enti_tx_cnhCategoria"]?? ""), 3, "", "tabindex=".sprintf("%02d", $tabIndex++)),
			combo_net("Cidade/UF Emissão*", "cnhCidade", ($a_mod["enti_nb_cnhCidade"]?? ""), 3, "cidade", "tabindex=".sprintf("%02d", $tabIndex++), "", "cida_tx_uf"),
			campo_data("Data Emissão*", "cnhEmissao", ($a_mod["enti_tx_cnhEmissao"]?? ""), 3, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data("Validade*", "cnhValidade", ($a_mod["enti_tx_cnhValidade"]?? ""), 3, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data("1º Habilitação*", "cnhPrimeiraHabilitacao", ($a_mod["enti_tx_cnhPrimeiraHabilitacao"]?? ""), 3, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Permissão", "cnhPermissao", ($a_mod["enti_tx_cnhPermissao"]?? ""), 3,"","maxlength='65' tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Pontuação", "cnhPontuacao", ($a_mod["enti_tx_cnhPontuacao"]?? ""), 3,"","maxlength='3' tabindex=".sprintf("%02d", $tabIndex++)),
			combo("Atividade Remunerada", "cnhAtividadeRemunerada", ($a_mod["enti_tx_cnhAtividadeRemunerada"]?? ""), 3, ["" => "", "sim" => "Sim", "nao" => "Não"], "tabindex=".sprintf("%02d", $tabIndex++)),
			arquivo("CNH (.png, .jpg, .pdf)".$iconeExcluirCNH, "cnhAnexo", ($a_mod["enti_tx_cnhAnexo"]?? ""), 4, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Observações", "cnhObs", ($a_mod["enti_tx_cnhObs"]?? ""), 3,"","maxlength='500' tabindex=".sprintf("%02d", $tabIndex++))
		];


		$botoesCadastro[] = botao(
			"Gravar", 
			"cadastrarMotorista", 
			((empty($_POST["id"]) || empty($a_mod["enti_tx_matricula"]))? "": "id,matricula"),
			((empty($_POST["id"]) || empty($a_mod["enti_tx_matricula"]))? "": $_POST["id"].",".$a_mod["enti_tx_matricula"]),
			"tabindex=53",
			"",
			"btn btn-success"
		);

		$botoesCadastro[] = criarBotaoVoltar(null, null, "tabindex=54");

		echo abre_form();
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		fieldset("Dados de Usuário");
		echo linha_form($camposUsuario);
		echo "<br>";
		fieldset("Dados Pessoais");
		echo linha_form($camposPessoais);
		echo "<br>";
		fieldset("Foto");
		echo "<div class='imageForm'>";
		echo linha_form($camposImg);
		echo "</div>";
		echo "<br>";
		fieldset("Dados Contratuais");
		echo linha_form($cContratual);
		echo "<br>";
		fieldset("CONVENÇÃO SINDICAL - JORNADA PADRÃO DO FUNCIONÁRIO");
		echo linha_form($cJornada);
		echo "<br>";
		echo "<div class='cnh-row'>";
			fieldset("CARTEIRA NACIONAL DE HABILITAÇÃO");
			echo linha_form($camposCNH);
		echo "</div>";

		if (!empty($a_mod["enti_nb_userCadastro"])) {
			$a_userCadastro = carregar("user", $a_mod["enti_nb_userCadastro"]);
			$txtCadastro = "Registro inserido por $a_userCadastro[user_tx_login] às ".data($a_mod["enti_tx_dataCadastro"]).".";
			$cAtualiza[] = texto("Data de Cadastro", "$txtCadastro", 5);
			if ($a_mod["enti_nb_userAtualiza"] > 0) {
				$a_userAtualiza = carregar("user", $a_mod["enti_nb_userAtualiza"]);
				$txtAtualiza = "Registro atualizado por $a_userAtualiza[user_tx_login] às ".data($a_mod["enti_tx_dataAtualiza"], 1).".";
				$cAtualiza[] = texto("Última Atualização", strval($txtAtualiza), 5);
			}
			echo "<br>";
			echo linha_form($cAtualiza);
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
					parent.document.contex_form.percHESemanal.value 		= '".($a_mod["parametroPadrao"]["para_tx_percHESemanal"]?? "")."';
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
					if(ocupation == 'Ajudante' || ocupation == 'Funcionário'){
						document.getElementsByClassName('cnh-row')[0].setAttribute('style', 'display:none')
					}else{
						document.getElementsByClassName('cnh-row')[0].setAttribute('style', '')
					}
				}
			</script>"
		;

		echo fecha_form($botoesCadastro);
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
		cabecalho("Cadastro de Funcionário");

		$extraEmpresa = "";
		if ($_SESSION["user_nb_empresa"] > 0 && is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
			$extraEmpresa = " AND empr_nb_id = '".$_SESSION["user_nb_empresa"]."'";
		}
		
		if(!empty($_POST["busca_cpf"])){
			$_POST["busca_cpf"] = preg_replace( "/[^0-9]/is", "", $_POST["busca_cpf"]);
		}

		$camposBusca = [
			campo("Código",						"busca_codigo",			(!empty($_POST["busca_codigo"])? $_POST["busca_codigo"]: ""), 1,"","maxlength='6'"),
			campo("Nome",						"busca_nome_like",		(!empty($_POST["busca_nome_like"])? $_POST["busca_nome_like"]: ""), 2,"","maxlength='65'"),
			campo("Matrícula",					"busca_matricula_like",	(!empty($_POST["busca_matricula_like"])? $_POST["busca_matricula_like"]: ""), 1,"","maxlength='6'"),
			campo("CPF",						"busca_cpf",			(!empty($_POST["busca_cpf"])? $_POST["busca_cpf"]: ""), 2, "MASCARA_CPF"),
			combo_bd("!Empresa",				"busca_empresa",		(isset($_POST["busca_empresa"])? $_POST["busca_empresa"]: ""), 2, "empresa", "", $extraEmpresa),
			combo("Ocupação",					"busca_ocupacao",		(isset($_POST["busca_ocupacao"])? $_POST["busca_ocupacao"]: ""), 2, ["", "Motorista", "Ajudante", "Funcionário"]),
			combo("Convenção Padrão",			"busca_padrao",			(isset($_POST["busca_padrao"])? $_POST["busca_padrao"]: ""), 2, ["" => "", "sim" => "Sim", "nao" => "Não"]),
			combo_bd("!Parâmetros da Jornada", 	"busca_parametro",		(isset($_POST["busca_parametro"])? $_POST["busca_parametro"]: ""), 6, "parametro"),
			combo("Status",						"busca_status",			(isset($_POST["busca_status"])? $_POST["busca_status"]: "ativo"), 2, ["" => "", "ativo" => "Ativo", "inativo" => "Inativo"])
		];

		$botoesBusca = [
			botao("<spam class='glyphicon glyphicon-plus'></spam>", "visualizarCadastro","","","","","btn btn-success")
		];

		echo abre_form();
		echo linha_form($camposBusca);
		echo fecha_form([], "<hr><form>".implode(" ", $botoesBusca)."</form>");
		

		//Configuração da tabela dinâmica{
			$gridFields = [
				"CÓDIGO" 				=> "enti_nb_id",
				"NOME" 					=> "enti_tx_nome",
				"MATRÍCULA" 			=> "enti_tx_matricula",
				"CPF" 					=> "enti_tx_cpf",
				"EMPRESA" 				=> "empr_tx_nome",
				"FONE 1" 				=> "enti_tx_fone1",
				"OCUPAÇÃO" 				=> "enti_tx_ocupacao",
				"DATA CADASTRO" 		=> "CONCAT('data(\"', enti_tx_dataCadastro, '\")') AS enti_tx_dataCadastro",
				"PARÂMETRO DA JORNADA" 	=> "para_tx_nome",
				"CONVENÇÃO PADRÃO" 		=> "IF(enti_tx_ehPadrao = \"sim\", \"Sim\", \"Não\") AS enti_tx_ehPadrao",
				"STATUS" 				=> "enti_tx_status"
			];
	
			$camposBusca = [
				"busca_codigo" 			=> "enti_nb_id",
				"busca_nome_like" 		=> "enti_tx_nome",
				"busca_matricula_like" 	=> "enti_tx_matricula",
				"busca_cpf" 			=> "enti_tx_cpf",
				"busca_empresa" 		=> "enti_nb_empresa",
				"busca_ocupacao" 		=> "enti_tx_ocupacao",
				"busca_padrao" 			=> "enti_tx_ehPadrao",
				"busca_parametro" 		=> "enti_nb_parametro",
				"busca_status" 			=> "enti_tx_status"
			];
	
			$queryBase = (
				"SELECT ".implode(", ", array_values($gridFields))." FROM entidade"
					." LEFT JOIN user ON enti_nb_id = user_nb_entidade"
					." JOIN empresa ON enti_nb_empresa = empr_nb_id"
					." LEFT JOIN parametro ON enti_nb_parametro = para_nb_id"
			);
	
			$actions = criarIconesGrid(
				["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
				["cadastro_motorista.php", "cadastro_motorista.php"],
				["modificarMotorista()", "excluirMotorista()"]
			);
	
			$actions["functions"][1] .= 
				"esconderInativar('glyphicon glyphicon-remove search-remove', 11);"
			;
	
			$gridFields["actions"] = $actions["tags"];
	
			$jsFunctions =
				"const funcoesInternas = function(){
					".implode(" ", $actions["functions"])."
				}"
			;
	
			echo gridDinamico("tabelaMotoristas", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		//}

		rodape();
	}