<?php
    /* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/
	include "conecta.php";

	function carregarJS(){
		global $a_mod;

		$path_parts = pathinfo(__FILE__);

		$parametroPadrao = $a_mod["parametroPadrao"];

		$displayCamposJornada = "block";
		if(!empty($a_mod["enti_nb_parametro"])){
			$displayCamposJornada = mysqli_fetch_assoc(query(
				"SELECT para_tx_tipo FROM parametro WHERE para_nb_id = ?",
				"i",
				[$a_mod["enti_nb_parametro"]]
			));
			$displayCamposJornada = ($displayCamposJornada["para_tx_tipo"] == "horas_por_dia")? "block": "none";
		}

		echo 
			"



			
			<form name='form_excluir_arquivo' method='post' action='cadastro_funcionario.php'>
				<input type='hidden' name='idEntidade' value=''>
				<input type='hidden' name='idArq' value=''>
				<input type='hidden' name='acao' value=''>
			</form>

			<form name='form_download_arquivo' method='post' action='cadastro_funcionario.php'>
				<input type='hidden' name='idEntidade' value=''>
				<input type='hidden' name='caminho' value=''>
				<input type='hidden' name='acao' value=''>
			</form>

			<script>

				function downloadArquivo(id, caminho, acao) {
					document.form_download_arquivo.idEntidade.value = id;
					document.form_download_arquivo.caminho.value = caminho;
					document.form_download_arquivo.acao.value = acao;
					document.form_download_arquivo.submit();
				}

				function remover_arquivo(id, idArq, arquivo, acao ) {
					if (confirm('Deseja realmente excluir o arquivo '+arquivo+'?')) {
						document.form_excluir_arquivo.idEntidade.value = id;
						document.form_excluir_arquivo.idArq.value = idArq;
						document.form_excluir_arquivo.acao.value = acao;
						document.form_excluir_arquivo.submit();
					}
				}

				function buscarCEP(cep) {
					var num = cep.replace(/[^0-9]/g, '');
					if (num.length == '8') {
						document.getElementById('frame_parametro').src = '{$path_parts["basename"]}?acao=carregarEndereco&cep='+num;
					}
				}

				function carregarEmpresa(id) {
					document.getElementById('frame_parametro').src = 'cadastro_funcionario.php?acao=carregarEmpresa&emp='+id;
				}

				function carregarParametro() {
					id = document.getElementsByName('parametro')[0].value;
					document.getElementById('frame_parametro').src = 'cadastro_funcionario.php?acao=carregarParametro&parametro='+id;
					conferirParametroPadrao();
				}

				var parametroPadrao = ".json_encode($parametroPadrao).";
				function padronizarParametro() {
					var padraoDisplayJornada = (parametroPadrao.para_tx_tipo == 'escala')? 'none': 'block';
					parent.document.getElementsByName('divJornada')[0].style.display = padraoDisplayJornada;
					
					parent.document.contex_form.parametro.value 		= parametroPadrao.para_nb_id;
					parent.document.contex_form.jornadaSemanal.value 	= parametroPadrao.para_tx_jornadaSemanal;
					parent.document.contex_form.jornadaSabado.value 	= parametroPadrao.para_tx_jornadaSabado;
					parent.document.contex_form.percHESemanal.value 	= parametroPadrao.para_tx_percHESemanal;
					parent.document.contex_form.percHEEx.value 			= parametroPadrao.para_tx_percHEEx;

					conferirParametroPadrao();
				}

				function conferirParametroPadrao(){
					var padronizado = (
						parametroPadrao.para_nb_id == parent.document.contex_form.parametro.value &&
						parametroPadrao.para_tx_jornadaSemanal == parent.document.contex_form.jornadaSemanal.value &&
						parametroPadrao.para_tx_jornadaSabado == parent.document.contex_form.jornadaSabado.value &&
						parametroPadrao.para_tx_percHESemanal == parent.document.contex_form.percHESemanal.value &&
						parametroPadrao.para_tx_percHEEx == parent.document.contex_form.percHEEx.value
					);
					parent.document.getElementsByName('textoParametroPadrao')[0].getElementsByTagName('p')[0].innerText = (padronizado? 'Sim': 'Não');
				}
				conferirParametroPadrao();


				function checkOcupation(ocupation){
					console.log(ocupation);
					if(ocupation == 'Motorista'){
						document.getElementsByClassName('cnh-row')[0].setAttribute('style', '')
					}else{
						document.getElementsByClassName('cnh-row')[0].setAttribute('style', 'display:none')
					}
				}

				checkOcupation(document.getElementsByName('ocupacao')[0].value);

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

				parent.document.getElementsByName('divJornada')[0].style.display = '{$displayCamposJornada}';

				
				function imprimir() {
					// Abrir a caixa de diálogo de impressão
					// window.print();
					const id = '$_POST[id]';
					var form = document.createElement('form');
					form.method = 'POST';
					form.action = './impressao/ficha_funcionario.php';
					form.target = '_blank';

					var inputId = document.createElement('input');
					inputId.type = 'hidden';
					inputId.name = 'id';
					inputId.value = id;
					form.appendChild(inputId);

					document.body.appendChild(form);
					form.submit();
					document.body.removeChild(form);
				}


				
			</script>
				
			<script>
				document.addEventListener('DOMContentLoaded', function () {
				document.querySelectorAll('select').forEach(function (select) {
					const first = select.options[0];
					if (!first) return;

				
					const isPlaceholder = (first.value === '' || /selecion(e|e um item)/i.test(first.textContent.trim()));

					if (isPlaceholder) {
					// Torna a primeira opção desabilitada
					first.disabled = true;

					// Define a primeira opção como selecionada (placeholder visível)
					//   select.selectedIndex = 0;

					// Se estiver usando Select2, força a atualização visual
					if (window.jQuery && jQuery(select).data('select2')) {
						jQuery(select).val('').trigger('change');
					}
					}
				});
				});
			</script>"
		;

		return;
	}

	function carregarEmpresa(){
		$aEmpresa = carregar("empresa", (int)$_GET["emp"]);
		if ($aEmpresa["empr_nb_parametro"] > 0) {
			echo 
				"<script type='text/javascript'>
					var parametroEmpresa = '{$aEmpresa["empr_nb_parametro"]}';

					parent.document.contex_form.parametro.value = parametroEmpresa;
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
					AND empresa.empr_nb_id = ?
				LIMIT 1;",
			"i",
			[$idEmpresa]
		));
	}

	function carregarParametro(){
		if(empty($_GET["parametro"])){
			exit;
		}

		$parametro = mysqli_fetch_assoc(query(
			"SELECT * FROM parametro
				LEFT JOIN escala ON para_nb_id = esca_nb_parametro
				WHERE para_nb_id = {$_GET["parametro"]}
			LIMIT 1;"
		));
		
		if(empty($parametro)){
			exit;
		}

		echo
			"<script type='text/javascript'>
				var parametroCarregado = ".json_encode($parametro).";

				console.log(parametroCarregado);

				console.log(document.getElementsByName('divJornada'));
				parent.document.getElementsByName('divJornada')[0].style.display	= ((parametroCarregado.para_tx_tipo == 'escala')? 'none': 'block');
				
				parent.document.contex_form.jornadaSemanal.value						= parametroCarregado.para_tx_jornadaSemanal;
				parent.document.contex_form.jornadaSabado.value							= parametroCarregado.para_tx_jornadaSabado;
				parent.document.contex_form.percHESemanal.value							= parametroCarregado.para_tx_percHESemanal;
				parent.document.contex_form.percHEEx.value								= parametroCarregado.para_tx_percHEEx;
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
				// "jornadaSemanal" 			=> "Jornada Semanal",
				// "jornadaSabado" 			=> "Sábado",
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

			if(!empty($_POST["parametro"])){
				$parametro = mysqli_fetch_assoc(query("SELECT para_tx_tipo FROM parametro WHERE para_nb_id = {$_POST["parametro"]}"));

				if($parametro["para_tx_tipo"] == "horas_por_dia"){
					$camposObrig["jornadaSemanal"] = "Jornada Semanal";
					$camposObrig["jornadaSabado"] = "Sábado";
				}
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

			if(!empty($a_user["user_nb_id"])){
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

	function excluir_documento() {

		query("DELETE FROM documento_funcionario WHERE docu_nb_id = $_POST[idArq]");
		
		// Após excluir, apenas recarrega a tela do funcionário
		$_POST["id"] = $_POST["idEntidade"];
		modificarMotorista();
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

	function temAssinaturaRapido($filePath) {
		if (!file_exists($filePath)) {
			return false;
		}

		$conteudo = file_get_contents($filePath);
		
		// Procura por duas chaves que são obrigatórias em PDFs assinados.
		if (strpos($conteudo, '/AcroForm') !== false && strpos($conteudo, '/Sig') !== false) {
			return true;
		}
		
		return false;
	}

	function enviarDocumento() {
		global $a_mod;

		if (empty($a_mod) && isset($_POST["idRelacionado"])) {
			$a_mod = carregar("entidade", $_POST["idRelacionado"]);
		}

		$errorMsg = "";
		if(isset($_POST["tipo_documento"]) && !empty($_POST["tipo_documento"])){
			$obgVencimento = mysqli_fetch_all(query("SELECT tipo_tx_vencimento FROM `tipos_documentos` 
			WHERE tipo_nb_id = {$_POST["tipo_documento"]}"), MYSQLI_ASSOC);

			if($obgVencimento[0]['tipo_tx_vencimento'] == 'sim'){
				$errorMsg = "Campo obrigatório não preenchidos: Data de Vencimento";
			}
		}

		if(!empty($errorMsg)){
			set_status("ERRO: ".$errorMsg);
			$_POST["id"] = $_POST["idRelacionado"];
			visualizarCadastro();
			exit;
		}

		$novoArquivo = [
			"docu_nb_entidade" => (int) $_POST["idRelacionado"],
			"docu_tx_nome" => $_POST["file-name"] ?? '',
			"docu_tx_descricao" => $_POST["description-text"] ?? '',
			"docu_tx_dataCadastro" => date("Y-m-d H:i:s"),
			"docu_tx_dataVencimento" => $_POST["data_vencimento"] ?? null,
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

		$pasta_funcionario = "arquivos/Funcionarios/" . $novoArquivo["docu_nb_entidade"] . "/";
		if (!is_dir($pasta_funcionario)) {
			mkdir($pasta_funcionario, 0777, true);
		}

		// Caminho físico usa o nome original (sanitizado)
		$novoArquivo["docu_tx_caminho"] = $pasta_funcionario . $nomeSeguro;

		// Se for PDF, verifica assinatura
		if ($tipo === "application/pdf" && function_exists("temAssinaturaRapido")) {
			if (temAssinaturaRapido($arquivo["tmp_name"])) {
				$novoArquivo["docu_tx_assinado"] = "sim";
			}
		}

		// Evita sobrescrever arquivos já existentes
		if (file_exists($novoArquivo["docu_tx_caminho"])) {
			$info = pathinfo($nomeSeguro);
			$base = $info["filename"];
			$ext = isset($info["extension"]) ? '.' . $info["extension"] : '';
			$nomeSeguro = $base . '_' . time() . $ext;
			$novoArquivo["docu_tx_caminho"] = $pasta_funcionario . $nomeSeguro;
		}

		// Move o arquivo e salva no banco
		if (move_uploaded_file($arquivo["tmp_name"], $novoArquivo["docu_tx_caminho"])) {
			inserir("documento_funcionario", array_keys($novoArquivo), array_values($novoArquivo));
			set_status("Registro inserido com sucesso.");
		} else {
			set_status("Falha ao mover o arquivo para o diretório de destino.");
		}

		$_POST["id"] = $novoArquivo["docu_nb_entidade"];
		visualizarCadastro();
		exit;
	}

	function visualizarCadastro(){
		global $a_mod;

		echo '<style>
		@media print{
			input, select, textarea {
				background: transparent !important;
				box-shadow: none !important;
				color: black !important;
				font-size: 10pt !important;
				// margin: 0 !important;
				width: 100% !important;
			}

			 label, .form-label {
				font-weight: bold !important;
				color: #000 !important;
				padding-bottom: 10px !important;
			}

			.form-group,
			.row,
			.linha-campos,
			.form-row,
			.form-section {
				display: flex !important;
				flex-wrap: wrap !important;
				// gap: 10px !important;
				width: 100% !important;
			}
			
			#email, #nome{
				width: 240px !important;
			}
			
			select {
				appearance: none !important;        /* Padrão moderno */
				-webkit-appearance: none !important; /* Safari/Chrome */
				-moz-appearance: none !important;    /* Firefox */
			}

			input[type="date"]::-webkit-calendar-picker-indicator {
				display: none !important;
				-webkit-appearance: none !important;
			}

			.select2-selection__clear,
			div.col-sm-4.margin-bottom-5.campo-fit-content,
			form > div.form-actions,
			span.select2-selection__arrow,
			form > div.msg-status-text,
			#padronizarParametro,
			.scroll-to-top {
				display: none !important;
			}
			
			.portlet.light{
			    padding: 12px 0px 0px !important;
			}
			
			.portlet{
				margin-bottom: 0px !important;
			}
			form > div.cnh-row > div.row,
			form > div:nth-child(21),
			form > div:nth-child(25){
			    margin: 0px 0px 0px 0px !important;
			}

			div.col-sm-2.margin-bottom-5.campo-fit-content.text-field > p > img{
				width: 350px !important;
			}
			
			form > div:nth-child(11),
			form > div:nth-child(19){
				margin-top: 100px;
			}

			div.imageForm > div > div.col-sm-2.margin-bottom-5.campo-fit-content.text-field{
				padding-left: 350px !important;
			}

			// div:nth-child(20) > label{
			// 	padding-bottom: 10px;
			// }
			
			.form-group > div,
			.form-row > div,
			div > .campo-fit-content {
				// width: 240px !important;  /* largura fixa para todos os campos */
				box-sizing: border-box;
				margin-right: 10px; /* espaçamento lateral */
			}

			.container,
			.card,
			.card-body,
			.section-wrapper {
				width: 100% !important;
				margin: 0 !important;
				padding: 0 !important;
			}

			/* Oculta elementos desnecessários na impressão */
			.no-print,
			.btn,
			nav,
			footer {
				display: none !important;
			}

			.container-fluid
			{
			    padding-left: 0px !important;
        		padding-right: 0px !important;
			}
			.page-content{
				min-height: 0px !important;
			}
			
			form > div:nth-child(25) > div:nth-child(1),
			form > div:nth-child(9) > div.col-sm-2.margin-bottom-5.campo-fit-content.text-field{
			    margin-right: 17px;
			}
			
			

			@page{
				size: A4 landscape;
				// margin: 1cm;
			}
		}
		</style>';

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
		$estadoCivilOpt = [
			"" => "Selecione", 
			"Casado(a)" => "Casado(a)", 
			"Solteiro(a)" => "Solteiro(a)", 
			"Divorciado(a)" => "Divorciado(a)", 
			"Viúvo(a)" => "Viúvo(a)"
		];

		$sexoOpt = ["" => "Selecione", "Feminino" => "Feminino", "Masculino" => "Masculino"];

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

		$estados = mysqli_fetch_all(query(
			"SELECT DISTINCT cida_tx_uf FROM cidade ORDER BY cida_tx_uf;"
		), MYSQLI_ASSOC);
		$aux = ["" => ""];
		foreach($estados as $estado){
			$aux[$estado["cida_tx_uf"]] = $estado["cida_tx_uf"];
		}
		$estados = $aux;

		$camposPessoais = array_merge($camposPessoais, [
			campo(	  	"Nome*", 				"nome", 			($a_mod["enti_tx_nome"]?? ""),			4, "",					"maxlength='65' tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data(	"Nascido em*",	 		"nascimento", 		($a_mod["enti_tx_nascimento"]?? ""),	2, 						"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"CPF*", 				"cpf", 				($a_mod["enti_tx_cpf"]?? ""),			2, "MASCARA_CPF", 		"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"RG*", 					"rg", 				($a_mod["enti_tx_rg"]?? ""),			2, "MASCARA_RG", 		"tabindex=".+sprintf("%02d", $tabIndex++).", maxlength=11"),
			combo(		"Estado Civil", 		"civil", 			($a_mod["enti_tx_civil"]?? ""),			2, $estadoCivilOpt, 	"tabindex=".sprintf("%02d", $tabIndex++)),
			combo(		"Sexo", 				"sexo", 			($a_mod["enti_tx_sexo"]?? ""),			2, $sexoOpt, 			"tabindex=".sprintf("%02d", $tabIndex++)),
			campo(	  	"Emissor RG", 			"rgOrgao", 			($a_mod["enti_tx_rgOrgao"]?? ""),		3, "",					"maxlength='6' tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data(	"Data Emissão RG", 		"rgDataEmissao", 	($a_mod["enti_tx_rgDataEmissao"]?? ""),	2, 						"tabindex=".sprintf("%02d", $tabIndex++)),
			combo(		"UF RG", 				"rgUf", 			($a_mod["enti_tx_rgUf"]?? ""),			2, getUFs(), 			"tabindex=".sprintf("%02d", $tabIndex++)),
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
			combo_bd( "!Tipo de Operação", 	"tipoOperacao",		(isset($_POST["enti_tx_tipoOperacao"])? $_POST["enti_tx_tipoOperacao"]: ""), 3, "operacao"),

			textarea(	"Observações:", "obs", ($a_mod["enti_tx_obs"]?? ""), 12, "tabindex=".sprintf("%02d", $tabIndex++))
		]);

		$extraEmpresa = "";
		if ($_SESSION["user_nb_empresa"] > 0 && is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
			$extraEmpresa = " AND empr_nb_id = '".$_SESSION["user_nb_empresa"]."'";
		}
		$campoSalario = "";
		if (is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
			$a_mod["enti_nb_salario"] = str_replace(".", ",", (!empty($a_mod["enti_nb_salario"])? $a_mod["enti_nb_salario"] : ""));
			$campoSalario = campo("Salário*", "salario", $a_mod["enti_nb_salario"], 1, "MASCARA_DINHEIRO", "tabindex=".sprintf("%02d", $tabIndex+2));
		}

		$cContratual = [
			combo_bd("Empresa*", "empresa", ($a_mod["enti_nb_empresa"]?? $_SESSION["user_nb_empresa"]), 3, "empresa", "onchange='carregarEmpresa(this.value)' tabindex=".sprintf("%02d", $tabIndex++), $extraEmpresa),
			$campoSalario
		];
		$tabIndex++;

		$cContratual = array_merge($cContratual, [
			combo(		"Ocupação*", 		"ocupacao", 		(!empty($a_mod["enti_tx_ocupacao"])? $a_mod["enti_tx_ocupacao"]	:""), 		2, ["" => "Selecione", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário"], "tabindex=".sprintf("%02d", $tabIndex++)." onchange=checkOcupation(this.value)"),
			campo_data(	"Dt Admissão*", 	"admissao", 		(!empty($a_mod["enti_tx_admissao"])? $a_mod["enti_tx_admissao"]		 		:""), 		2, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo_data(	"Dt. Desligamento", "desligamento", 	(!empty($a_mod["enti_tx_desligamento"])? $a_mod["enti_tx_desligamento"] 	:""), 		2, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo(		"Saldo de Horas", 	"setBanco", 		(!empty($a_mod["enti_tx_banco"])? $a_mod["enti_tx_banco"] 					:"00:00"), 	1, "MASCARA_HORAS", "placeholder='HH:mm' tabindex=".sprintf("%02d", $tabIndex++)),
			combo(		"Subcontratado", 	"subcontratado", 	(!empty($a_mod["enti_tx_subcontratado"])? $a_mod["enti_tx_subcontratado"] 	:""), 		2, ["" => "Selecione", "sim" => "Sim", "nao" => "Não"], "tabindex=".sprintf("%02d", $tabIndex++)),
		]);

		$conferirPadraoJS = "";
		if (!empty($a_mod["enti_nb_empresa"])){
			$icone_padronizar = "<a id='padronizarParametro' style='text-shadow: none; color: #337ab7;' onclick='javascript:padronizarParametro();' title='Utilizar parâmetro padrão da empresa.' > (Padronizar) </a>";
			$conferirPadraoJS = "conferirParametroPadrao();";
		}

		$parametros = mysqli_fetch_all(query(
			"SELECT para_nb_id, para_tx_nome FROM parametro WHERE para_tx_status = 'ativo';"
		), MYSQLI_ASSOC);

		$aux = ["" => "Selecione"];
		foreach($parametros as $parametro){
			$aux[strval($parametro["para_nb_id"])] = $parametro["para_tx_nome"];
		}
		$parametros = $aux;


		// NÃO ESTÁ CONSEGUINDO ATUALIZAR COM A FUNÇÃO carregarParametro()
		$cJornada = [
			"<div style='overflow: hidden;'>"
				.combo("Parâmetros da Jornada".($icone_padronizar?? ""), "parametro", ($a_mod["enti_nb_parametro"]?? ""), 6, $parametros, "onchange='carregarParametro()' tabindex=".sprintf("%02d", $tabIndex++))
			."</div>",
			// combo_bd(	"!Parâmetros da Jornada*".($icone_padronizar?? ""), "parametro", ($a_mod["enti_nb_parametro"]?? ""), 6, "parametro", "onchange='carregarParametro()' tabindex=".sprintf("%02d", $tabIndex++)), 
			
			// combo_bd2(
			// 	"Parâmetros da Jornada*".($icone_padronizar?? ""), 
			// 	"parametro", 
			// 	($a_mod["enti_nb_parametro"]?? ""), 
			// 	"SELECT para_nb_id as 'value', para_tx_nome as 'text' FROM parametro WHERE para_tx_status = 'ativo'", 
			// 	"col-sm-6 margin-bottom-5 campo-fit-content",
			// 	"form-control input-sm campo-fit-content", 
			// 	"onchange='carregarParametro()' tabindex=".sprintf("%02d", $tabIndex++),
			// 	[
			// 		["value" => "", "text" => "Selecione", "props" => "disabled"]
			// 	]
			// ),
			texto(		"Escala", ($a_mod["textoEscala"]?? ""), 4, "name='textoEscala' style='display:none;'"),
			"<div name='divJornada' style='margin: 15px; width: fit-content; overflow: hidden;'>"
				."<div style='font-weight: bold;'>Jornada</div>"
				.campo_hora(	"Dias Úteis (Hr/dia)*", "jornadaSemanal", ($a_mod["enti_tx_jornadaSemanal"]?? ""), 2, "tabindex=".sprintf("%02d", $tabIndex++)." onchange='{$conferirPadraoJS}'")
				.campo_hora(	"Sábado*", "jornadaSabado", ($a_mod["enti_tx_jornadaSabado"]?? ""), 2, "tabindex=".sprintf("%02d", $tabIndex++)." onchange='{$conferirPadraoJS}'")
			."</div>",
			campo(		"H.E. Semanal (%)*", "percHESemanal", ($a_mod["enti_tx_percHESemanal"]?? ""), 2, "MASCARA_NUMERO", "tabindex=".sprintf("%02d", $tabIndex++)." onchange='{$conferirPadraoJS}'"),
			campo(		"H.E. Extraordinária (%)*", "percHEEx", ($a_mod["enti_tx_percHEEx"]?? ""), 2, "MASCARA_NUMERO", "tabindex=".sprintf("%02d", $tabIndex++)." onchange='{$conferirPadraoJS}'")
		];
		
		$cJornada[]=texto("Convenção Padrão?", "...", 2, "name='textoParametroPadrao'");
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
			combo("Atividade Remunerada", "cnhAtividadeRemunerada", ($a_mod["enti_tx_cnhAtividadeRemunerada"]?? ""), 3, ["" => "Selecione", "sim" => "Sim", "nao" => "Não"], "tabindex=".sprintf("%02d", $tabIndex++)),
			arquivo("CNH (.png, .jpg, .pdf)".$iconeExcluirCNH, "cnhAnexo", ($a_mod["enti_tx_cnhAnexo"]?? ""), 4, "tabindex=".sprintf("%02d", $tabIndex++)),
			campo("Observações", "cnhObs", ($a_mod["enti_tx_cnhObs"]?? ""), 3,"","maxlength='500' tabindex=".sprintf("%02d", $tabIndex++))
		];


		$botoesCadastro[] = botao(
			"Gravar", 
			"cadastrarMotorista", 
			((empty($_POST["id"]) || empty($a_mod["enti_tx_matricula"]))? "": "id,matricula"),
			((empty($_POST["id"]) || empty($a_mod["enti_tx_matricula"]))? "": $_POST["id"].",".$a_mod["enti_tx_matricula"]),
			"tabindex=".sprintf("%02d", $tabIndex++),
			"",
			"btn btn-success"
		);

		$botoesCadastro[] = criarBotaoVoltar(null, null, "tabindex=".sprintf("%02d", $tabIndex++));

		if (!empty($_POST["id"])) {
			$botoesCadastro[] = '<button class="btn default" type="button" onclick="imprimir()">Imprimir</button>';
		}

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

		echo "<iframe id=frame_parametro style='display: none;'></iframe>";

		echo fecha_form($botoesCadastro);

		if (!empty($a_mod["enti_nb_id"])) {
			$arquivos = mysqli_fetch_all(query(
				"SELECT 
				documento_funcionario.docu_nb_id,
				documento_funcionario.docu_nb_entidade,
				documento_funcionario.docu_tx_dataCadastro,
				documento_funcionario.docu_tx_dataVencimento,
				documento_funcionario.docu_tx_caminho,
				documento_funcionario.docu_tx_descricao,
				documento_funcionario.docu_tx_nome,
				documento_funcionario.docu_tx_visivel,
				documento_funcionario.docu_tx_assinado,
				t.tipo_tx_nome,
				gd.grup_tx_nome,
				subg.sbgr_tx_nome
				FROM documento_funcionario
				LEFT JOIN tipos_documentos t 
				ON documento_funcionario.docu_tx_tipo = t.tipo_nb_id
				LEFT JOIN grupos_documentos gd 
				ON t.tipo_nb_grupo = gd.grup_nb_id
				LEFT JOIN sbgrupos_documentos subg
				ON subg.sbgr_nb_id = documento_parametro.docu_nb_sbgrupo
				WHERE documento_funcionario.docu_nb_entidade = ".$a_mod["enti_nb_id"]
			),MYSQLI_ASSOC);
			echo "</div><div class='col-md-12'><div class='col-md-12 col-sm-12'>".arquivosFuncionario("Documentos", $a_mod["enti_nb_id"], $arquivos);
		}
		rodape();
		
		echo 
			"<form method='post' name='form_modifica' id='form_modifica'>
				<input type='hidden' name='id' value=''>
				<input type='hidden' name='acao' value='modificarMotorista'>
			</form>"
		;

		carregarJS();
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
			campo("Matrícula",					"busca_matricula_like",	(!empty($_POST["busca_matricula_like"])? $_POST["busca_matricula_like"]: ""), 1,"","maxlength='20'"),
			campo("CPF",						"busca_cpf",			(!empty($_POST["busca_cpf"])? $_POST["busca_cpf"]: ""), 2, "MASCARA_CPF"),
			combo_bd("!Empresa",				"busca_empresa",		(!empty($_POST["busca_empresa"])? $_POST["busca_empresa"]: ""), 2, "empresa", "", $extraEmpresa),
			combo("Ocupação",					"busca_ocupacao",		(!empty($_POST["busca_ocupacao"])? $_POST["busca_ocupacao"]: ""), 2, ["" => "Todos", "Motorista" => "Motorista", "Ajudante" => "Ajudante", "Funcionário" => "Funcionário"]),
			combo("Convenção Padrão",			"busca_padrao",			(!empty($_POST["busca_padrao"])? $_POST["busca_padrao"]: ""), 2, ["" => "Todos", "sim" => "Sim", "nao" => "Não"]),
			combo_bd("!Parâmetros da Jornada", 	"busca_parametro",		(!empty($_POST["busca_parametro"])? $_POST["busca_parametro"]: ""), 4, "parametro"),
			combo("Status",						"busca_status",			(!empty($_POST["busca_status"])? $_POST["busca_status"]: "Todos"), 2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]),
			combo_bd("!Tipo de Operação", 		"busca_operacao",		(!empty($_POST["busca_operacao"])? $_POST["busca_operacao"]: ""), 2, "operacao")
		];

		$botoesBusca = [
			botao("Inserir", "visualizarCadastro","","","","","btn btn-success"),
			'<button class="btn default" type="button" onclick="imprimirTabelaCompleta()">Imprimir</button>',
			botao("Limpar Filtros", "limparFiltros")
		];

		echo abre_form();
		echo linha_form($camposBusca);
		echo fecha_form([], "<hr><form>".implode(" ", $botoesBusca)."</form>");

		$logoEmpresa = mysqli_fetch_assoc(query(
            "SELECT empr_tx_logo FROM empresa
                    WHERE empr_tx_status = 'ativo'
                        AND empr_tx_Ehmatriz = 'sim'
                    LIMIT 1;"
        ))["empr_tx_logo"];


		echo "<div id='tituloRelatorio' style='display: none;'>
                    <img style='width: 190px; height: 40px;' src='./imagens/logo_topo_cliente.png' alt='Logo Empresa Esquerda'>
					<h1>Cadastro de Funcionários</h1>
                    <img style='width: 180px; height: 80px;' src='./$logoEmpresa' alt='Logo Empresa Direita'>
            </div>";
		

		//Configuração da tabela dinâmica{
			$gridFields = [
				"CÓDIGO" 				=> "enti_nb_id",
				"NOME" 					=> "enti_tx_nome",
				"MATRÍCULA" 			=> "enti_tx_matricula",
				"CPF" 					=> "enti_tx_cpf",
				"EMPRESA" 				=> "empr_tx_nome",
				"FONE 1" 				=> "enti_tx_fone1",
				"OCUPAÇÃO" 				=> "enti_tx_ocupacao",
				"TIPO DE OPERAÇÃO" 		=> "oper_tx_nome",
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
				"busca_status" 			=> "enti_tx_status",
				"busca_operacao" 		=> "enti_tx_tipoOperacao"
			];
	
			$queryBase = (
				"SELECT ".implode(", ", array_values($gridFields))." FROM entidade"
					." LEFT JOIN user ON enti_nb_id = user_nb_entidade"
					." JOIN empresa ON enti_nb_empresa = empr_nb_id"
					." LEFT JOIN parametro ON enti_nb_parametro = para_nb_id"
					." LEFT JOIN operacao ON enti_tx_tipoOperacao = oper_nb_id"
			);
	
			$actions = criarIconesGrid(
				["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
				["cadastro_funcionario.php", "cadastro_funcionario.php"],
				["modificarMotorista()", "excluirMotorista()"]
			);
	
			$actions["functions"][1] .= 
				"esconderInativar('glyphicon glyphicon-remove search-remove', 10);"
			;
	
			$gridFields["actions"] = $actions["tags"];
	
			$jsFunctions =
				"orderCol = 'enti_tx_nome ASC'
				const funcoesInternas = function(){
					".implode(" ", $actions["functions"])."
				}"
			;
	
			echo gridDinamico("tabelaMotoristas", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		//}

		rodape();
	}