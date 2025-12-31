<?php
/*
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	*/
	include "conecta.php";

	function excluirEmpresa(){
		remover("empresa",$_POST["id"]);
		index();
		exit;
	}

	function excluirLogo(){
		atualizar("empresa",array("empr_tx_logo"),[""],$_POST["idEntidade"]);
		$_POST["id"]=$_POST["idEntidade"];
		modificarEmpresa();
		exit;
	}

	if(!function_exists('normalizar')){
		function normalizar($texto){
			$texto = preg_replace('/[áàãâä]/ui', 'a', $texto);
			$texto = preg_replace('/[éèêë]/ui', 'e', $texto);
			$texto = preg_replace('/[íìîï]/ui', 'i', $texto);
			$texto = preg_replace('/[óòõôö]/ui', 'o', $texto);
			$texto = preg_replace('/[úùûü]/ui', 'u', $texto);
			$texto = preg_replace('/[ç]/ui', 'c', $texto);
			return strtolower(trim(preg_replace('/\s+/', ' ', $texto)));
		}
	}

	function enviarDocumento() {
		global $a_mod;

		$errorMsg = "";
		if(empty($_POST['data_vencimento'])){
			$obgVencimento = mysqli_fetch_all(query("SELECT tipo_tx_vencimento FROM `tipos_documentos` 
			WHERE tipo_nb_id = {$_POST["tipo_documento"]}"), MYSQLI_ASSOC);

			if($obgVencimento[0]['tipo_tx_vencimento'] == 'sim' && (empty($_POST["data_vencimento"]) || $_POST["data_vencimento"] == "0000-00-00")){
				$errorMsg = "Campo obrigatório não preenchidos: Data de Vencimento";
			}
		}

		if(!empty($_POST["tipo_documento"]) && !empty($_POST["sub-setor"])) {

			$nomes_documentos_subsetor = mysqli_fetch_all(query(
				"SELECT docu_tx_nome
				FROM documento_empresa
				WHERE docu_tx_tipo = {$_POST["tipo_documento"]} AND docu_nb_sbgrupo = {$_POST["sub-setor"]} AND empr_nb_id = {$_POST["idRelacionado"]}" 
			), MYSQLI_ASSOC);

			$buscaNormalizada = normalizar($_POST["file-name"]);

			$encontrado = array_filter(
				array_column($nomes_documentos_subsetor, 'docu_tx_nome'),
				fn($nome) => normalizar($nome) === $buscaNormalizada
			);

			if (!empty($encontrado)) {
				$errorMsg = "Já existe um documento com esse nome para o tipo selecionado.";
			} 

		} else if(!empty($_POST["tipo_documento"])){

			$nomes_documentos_setor = mysqli_fetch_all(query(
				"SELECT docu_tx_nome
				FROM documento_empresa
				WHERE docu_tx_tipo = {$_POST["tipo_documento"]}
				AND (docu_nb_sbgrupo IS NULL OR docu_nb_sbgrupo = 0) AND empr_nb_id = {$_POST["idRelacionado"]}"
			), MYSQLI_ASSOC);

			$buscaNormalizada = normalizar($_POST["file-name"]);

			$encontrado = array_filter(
				array_column($nomes_documentos_setor, 'docu_tx_nome'),
				fn($nome) => normalizar($nome) === $buscaNormalizada
			);

			if (!empty($encontrado)) {
				$errorMsg = "Já existe um documento com esse nome para o tipo selecionado.";
			} 

		}

		if(!empty($errorMsg)){
			set_status("ERRO: ".$errorMsg);
			$_POST["id"] = $_POST["idRelacionado"];
			modificarEmpresa();
			exit;
		}

		$novoParametro = [
			"empr_nb_id" => (int) $_POST["idRelacionado"],
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

		$pasta_funcionario = "arquivos/docu_empresa/" . $novoParametro["empr_nb_id"] . "/";
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
			inserir("documento_empresa", array_keys($novoParametro), array_values($novoParametro));
			set_status("Registro inserido com sucesso.");
		} else {
			set_status("Falha ao mover o arquivo para o diretório de destino.");
		}

		$_POST["id"] = $_POST["idRelacionado"];
		modificarEmpresa();
		exit;
	}

	function excluir_documento() {

		query("DELETE FROM documento_empresa WHERE docu_nb_id = $_POST[idArq]");
		
		$_POST["id"] = $_POST["idRelacionado"];
		modificarEmpresa();
		exit;
	}
	
	function atualizarDocumento() {
		$idDoc = intval($_POST["idDoc"] ?? 0);
		$nome = $_POST["file-name-edit"] ?? '';
		$descricao = $_POST["description-text-edit"] ?? '';
		$visibilidade = $_POST["visibilidade-edit"] ?? 'nao';
		$dataVenc = $_POST["data_vencimento_edit"] ?? null;
		$doc = mysqli_fetch_assoc(query("SELECT * FROM documento_empresa WHERE docu_nb_id = {$idDoc} LIMIT 1;"));
		if (empty($doc)) {
			set_status("Registro não encontrado.");
			$_POST["id"] = $_POST["idRelacionado"];
			modificarEmpresa();
			exit;
		}
		$errorMsg = "";
		$novoTipo = isset($_POST["tipo_documento_edit"]) ? intval($_POST["tipo_documento_edit"]) : 0;
		$novoSetor = isset($_POST["setor-edit"]) ? intval($_POST["setor-edit"]) : 0;
		$novoSubSetor = isset($_POST["sub-setor-edit"]) ? intval($_POST["sub-setor-edit"]) : 0;
		$tipoUsado = $novoTipo > 0 ? $novoTipo : intval($doc["docu_tx_tipo"]);
		$subgrupoUsado = $novoSubSetor > 0 ? $novoSubSetor : 0;
		$obg = mysqli_fetch_assoc(query("SELECT tipo_tx_vencimento FROM tipos_documentos WHERE tipo_nb_id = {$tipoUsado} LIMIT 1;"));
		if (($obg["tipo_tx_vencimento"] ?? 'nao') === 'sim' && (empty($dataVenc) || $dataVenc === "0000-00-00")) {
			$errorMsg = "Campo obrigatório não preenchidos: Data de Vencimento";
		}
		if ($subgrupoUsado > 0) {
			$rows = mysqli_fetch_all(query(
				"SELECT docu_tx_nome FROM documento_empresa WHERE docu_tx_tipo = {$tipoUsado}
				AND docu_nb_sbgrupo = {$subgrupoUsado} AND empr_nb_id = {$doc["empr_nb_id"]}
				AND docu_nb_id <> {$idDoc}"), MYSQLI_ASSOC);
		} else {
			$rows = mysqli_fetch_all(query(
				"SELECT docu_tx_nome FROM documento_empresa WHERE docu_tx_tipo = {$tipoUsado}
				AND (docu_nb_sbgrupo IS NULL OR docu_nb_sbgrupo = 0) AND empr_nb_id = {$doc["empr_nb_id"]}
				AND docu_nb_id <> {$idDoc}"), MYSQLI_ASSOC);
		}
		$buscaNormalizada = normalizar($nome);
		$encontrado = array_filter(array_column($rows, 'docu_tx_nome'), fn($n) => normalizar($n) === $buscaNormalizada);
		if (!empty($encontrado)) {
			$errorMsg = "Já existe um documento com esse nome para o tipo selecionado.";
		}
		$novoCaminho = null;
		$novoAssinado = $doc["docu_tx_assinado"] ?? "nao";
		$arquivoEdit = $_FILES["file-edit"] ?? null;
		if (!$errorMsg && $arquivoEdit && $arquivoEdit["error"] === UPLOAD_ERR_OK) {
			$formatos = [
				"image/jpeg" => "jpg",
				"image/png" => "png",
				"application/msword" => "doc",
				"application/vnd.openxmlformats-officedocument.wordprocessingml.document" => "docx",
				"application/pdf" => "pdf"
			];
			$tipo = mime_content_type($arquivoEdit["tmp_name"]);
			if (!array_key_exists($tipo, $formatos)) {
				$errorMsg = "Tipo de arquivo não permitido.";
			} else {
				$nomeOriginal = basename($arquivoEdit["name"]);
				$nomeSeguro = preg_replace('/[^\p{L}\p{N}\s\.\-\_]/u', '_', $nomeOriginal);
				$pasta = "arquivos/docu_empresa/" . intval($doc["empr_nb_id"]) . "/";
				if (!is_dir($pasta)) { mkdir($pasta, 0777, true); }
				$novoCaminho = $pasta . $nomeSeguro;
				if (file_exists($novoCaminho)) {
					$info = pathinfo($nomeSeguro);
					$base = $info["filename"];
					$ext = isset($info["extension"]) ? '.' . $info["extension"] : '';
					$nomeSeguro = $base . '_' . time() . $ext;
					$novoCaminho = $pasta . $nomeSeguro;
				}
				if ($tipo === "application/pdf" && function_exists("temAssinaturaRapido")) {
					$novoAssinado = temAssinaturaRapido($arquivoEdit["tmp_name"]) ? "sim" : "nao";
				} else {
					$novoAssinado = "nao";
				}
				if (!move_uploaded_file($arquivoEdit["tmp_name"], $novoCaminho)) {
					$errorMsg = "Falha ao mover o arquivo para o diretório de destino.";
					$novoCaminho = null;
				}
			}
		}
		if (!empty($errorMsg)) {
			set_status("ERRO: ".$errorMsg);
			$_POST["id"] = $doc["empr_nb_id"];
			modificarEmpresa();
			exit;
		}
		$campos = ["docu_tx_nome","docu_tx_descricao","docu_tx_datavencimento","docu_tx_visivel","docu_tx_tipo","docu_nb_sbgrupo"];
		$valores = [$nome,$descricao,$dataVenc,$visibilidade,$tipoUsado,($subgrupoUsado > 0 ? $subgrupoUsado : null)];
		if ($novoCaminho) {
			$campos[] = "docu_tx_caminho";
			$campos[] = "docu_tx_assinado";
			$valores[] = $novoCaminho;
			$valores[] = $novoAssinado;
			if (!empty($doc["docu_tx_caminho"]) && $doc["docu_tx_caminho"] !== $novoCaminho && file_exists($doc["docu_tx_caminho"])) {
				@unlink($doc["docu_tx_caminho"]);
			}
		}
		atualizar("documento_empresa", $campos, $valores, $idDoc);
		set_status("Registro atualizado com sucesso.");
		$_POST["id"] = $doc["empr_nb_id"];
		modificarEmpresa();
		exit;
	}

	function modificarEmpresa(){
		global $a_mod;

		$a_mod=carregar("empresa", $_POST["id"]);

		visualizarCadastro();
		exit;
	}

	function cadastrarEmpresa(){

		$camposObrig = [
			"cnpj" => "CNPJ",
			"nome" => "Nome",
			"cep" => "CEP",
			"numero" => "Número",
			"email" => "Email",
			"parametro" => "Parâmetro",
			"cidade" => "Cidade",
			"endereco" => "Endereço",
			"bairro" => "Bairro"
		];
		$errorMsg = conferirCamposObrig($camposObrig, $_POST);
		if(!empty($errorMsg)){
			set_status("ERRO: ".$errorMsg);
			visualizarCadastro();
			exit;
		}

        $cnpjMatriz = mysqli_fetch_assoc(query(
            "SELECT empr_tx_cnpj FROM empresa
                WHERE empr_tx_status = 'ativo'
                    AND empr_tx_Ehmatriz = 'sim'
                LIMIT 1;"
        ));

        if(!empty($cnpjMatriz)){
            $cnpjMatriz = preg_replace('/[^0-9]/', '', $cnpjMatriz["empr_tx_cnpj"]);
            $_POST["cnpj"] = preg_replace('/[^0-9]/', '', $_POST["cnpj"]);
            if($_SESSION['user_tx_nivel'] != 'Super Administrador' && substr($cnpjMatriz, 0, 8) != substr($_POST["cnpj"], 0, 8)){
                $errorMsg = "Os primeiros 8 dígitos do CNPJ devem ser os mesmos da empresa matriz.";
                $_POST["errorFields"][] = "cnpj";
            }
        }

		if(!empty($errorMsg)){
			set_status("ERRO: ".$errorMsg);
			visualizarCadastro();
			exit;
		}

		$campos = [
			"nome", "fantasia", "cnpj", "cep", "endereco", "bairro", "numero", "complemento",
			"referencia", "fone1", "fone2", "email", "inscricaoEstadual", "inscricaoMunicipal",
			"regimeTributario", "status", "contato",
			"ftpServer", "ftpUsername", "ftpUserpass", "dataRegistroCNPJ"
		];

		foreach($campos as $campo){
			if (!empty($_POST[$campo])) {
				$empresa["empr_tx_".$campo] = $_POST[$campo];
			}
		}
		if(empty($_POST["id"])){
			$empresa = array_merge($empresa, [
				"empr_tx_Ehmatriz"		=> $_POST["matriz"],
				"empr_nb_parametro" 	=> $_POST["parametro"],
				"empr_nb_cidade" 		=> $_POST["cidade"],
				"empr_tx_domain" 		=> $_SERVER["HTTP_ORIGIN"].(is_int(strpos($_SERVER["REQUEST_URI"], "dev_")) ? "dev_techps/" : "techps/").$_POST["nomeDominio"],
				"empr_nb_userCadastro" 	=> $_SESSION["user_nb_id"],
				"empr_tx_dataCadastro" 	=> date("Y-m-d H:i:s")
			]);

			$empty_ftp_inputs = empty($_POST["ftpServer"])+empty($_POST["ftpUsername"])+empty($_POST["ftpUserpass"])+0;

			if($empty_ftp_inputs == 3){
				$_POST["ftpServer"]   = "ftp:ftp-jornadas.positronrt.com.br";
				$_POST["ftpUsername"] = "u:08995631000108";
				$_POST["ftpUserpass"] = "p:0899";
			}elseif($empty_ftp_inputs > 0){
				$_POST["errorFields"] = array_merge(!empty($_POST["errorFields"])? $_POST["errorFields"]: [], ["ftpServer", "ftpUsername", "ftpUserpass"]);
				set_status("ERRO: Preencha os 3 campos de FTP.");
				visualizarCadastro();
				exit;
			}

			try{
				$id_empresa = inserir("empresa", array_keys($empresa), array_values($empresa))[0];
			}catch(Exception $e){
				print_r($e);
			}

			$file_type = $_FILES["logo"]["type"]; //returns the mimetype

			$allowed = array("image/jpeg", "image/gif", "image/png");
			if (in_array($file_type, $allowed) && $_FILES["logo"]["name"] != "") {

				$dir_destino = __DIR__ . "/arquivos/empresa/{$id_empresa}/";
				$caminho_relativo = "arquivos/empresa/{$id_empresa}/";

				if (!is_dir($dir_destino)) {
					mkdir($dir_destino, 0777, true);
				}

				$arq = enviar("logo", $dir_destino, $id_empresa);
				if ($arq) {
					$nome_arquivo = basename($arq);
					$caminho_para_banco = $caminho_relativo . $nome_arquivo;

					atualizar("empresa", ["empr_tx_logo"], [$caminho_para_banco], $id_empresa);
				}
			}
		} else {
			$empresa = array_merge($empresa, [
				"empr_nb_parametro" => $_POST["parametro"],
				"empr_nb_userAtualiza" => $_SESSION["user_nb_id"],
				"empr_tx_dataAtualiza" => date("Y-m-d H:i:s")
			]);


			atualizar("empresa", array_keys($empresa), array_values($empresa), $_POST["id"]);

			$id_empresa = $_POST["id"];

			$file_type = $_FILES["logo"]["type"]; //returns the mimetype

			$allowed = array("image/jpeg", "image/gif", "image/png");
			if (in_array($file_type, $allowed) && $_FILES["logo"]["name"] != "") {
				
				$dir_destino = __DIR__ . "/arquivos/empresa/{$id_empresa}/";
				$caminho_relativo = "arquivos/empresa/{$id_empresa}/";

				if (!is_dir($dir_destino)) {
					mkdir($dir_destino, 0777, true);
				}

				$arq = enviar("logo", $dir_destino, $id_empresa);
				if ($arq) {
					$nome_arquivo = basename($arq);
					$caminho_para_banco = $caminho_relativo . $nome_arquivo;
					
					atualizar("empresa", ["empr_tx_logo"], [$caminho_para_banco], $id_empresa);
				}
			}
		}


		index();
		exit;
	}

	function carregarEndereco(){
		global $CONTEX;
		echo 
			"<script src='{$CONTEX["path"]}/../contex20/assets/global/plugins/jquery.min.js' type='text/javascript'></script>
			<script src='{$CONTEX["path"]}/../contex20/assets/global/plugins/select2/js/select2.min.js'></script>
			<script src='{$CONTEX["path"]}/../contex20/assets/global/plugins/jquery-inputmask/jquery.inputmask.bundle.min.js' type='text/javascript'></script>
			<script src='{$CONTEX["path"]}/../contex20/assets/global/plugins/jquery-inputmask/maskMoney.js' type='text/javascript'></script>

			<script>
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
			</script>";
		exit;
	}

	function checarCNPJ($id, $cnpj){
		if(strlen($cnpj) == 18 || strlen($cnpj) == 14){
			$cnpj = substr($cnpj, 0, 18);

			$a = mysqli_fetch_array(query(
				"SELECT * FROM empresa"
					." WHERE empr_tx_cnpj = '{$cnpj}'"
						." AND empr_nb_id != {$id}"
					." LIMIT 1;"
			), MYSQLI_BOTH);
			
			if($a["empr_nb_id"] > 0){
				echo 
					"<script type='text/javascript'>
						if(confirm('CPF/CNPJ já cadastrado, deseja atualizar o registro?')){
							parent.document.form_modifica.id.value='{$a["empr_nb_id"]}';
							parent.document.form_modifica.submit();
						}else{
							parent.document.contex_form.cnpj.value = '';
						}
					</script>"
				;
			}
		}

		exit;
	}

	function carregarCadastroJS($a_mod){
		$path_parts = pathinfo( __FILE__ );

		echo 
			"<script>
				function remover_foto(id,acao,arquivo){
					if(confirm('Deseja realmente excluir a logo '+arquivo+'?')){
						document.form_excluir_arquivo.idEntidade.value=id;
						document.form_excluir_arquivo.nome_arquivo.value=arquivo;
						document.form_excluir_arquivo.acao.value=acao;
						document.form_excluir_arquivo.submit();
					}
				}

				
				function carrega_cep(cep) {
					var num = cep.replace(/[^0-9]/g, '');
					if (num.length == '8') {
						document.getElementById('frame_cep').src = '{$path_parts["basename"]}?acao=carregarEndereco()&cep='+num;
					}
				}
				
				function checarCNPJ(cnpj){
					// cnpj = cnpj.replace(/[^0-9]/g, '');
					if(cnpj.length == '18' || cnpj.length == '14'){
						document.getElementById('frame_cep').src='{$path_parts["basename"]}?acao=checarCNPJ&cnpj='+cnpj+'&id={$a_mod["empr_nb_id"]}'
					}
				}
				$(document).ready(function(){
					$('#cnpj').on('blur', function(){
						var cnpj = $(this).val();

						$.ajax({
							url: 'conecta.php',
							method: 'POST',
							data: { cnpj: cnpj },
							dataType: 'json',
							success: function(response) {
								$('#nome').val(response[0].empr_tx_nome);
								$('#fantasia').val(response[0].empr_tx_fantasia);
								$('#status').val(response[0].empr_tx_status);
								$('#cep').val(response[0].empr_tx_cep);
								$('#numero').val(response[0].empr_tx_email);
								$('#complemento').val(response[0].empr_tx_complemento);
								$('#referencia').val(response[0].empr_tx_referencia);
								$('#fone1').val(response[0].empr_tx_fone1);
								$('#fone2').val(response[0].empr_tx_fone2);
								$('#contato').val(response[0].empr_tx_contato);
								$('#email').val(response[0].empr_tx_email);
								$('#inscricaoEstadual').val(response[0].empr_tx_inscricaoEstadual);
								$('#inscricaoMunicipal').val(response[0].empr_tx_inscricaoMunicipal);
								$('#regimeTributario').val(response[0].empr_tx_regimeTributario);
								$('#dataRegistroCNPJ').val(response[0].empr_tx_dataRegistroCNPJ);
								$('#nomeDominio').val(response[0].empr_tx_domain);
							},
							error: function(error) {
								console.error('Erro na consulta:', error);
							}
						});
					});
				});
			</script>"
		;
	}

	function visualizarCadastro(){
		global $a_mod;

		cabecalho("Cadastro Empresa/Filial");

		$regimes = [
			"" => "Selecione",
			"Simples Nacional" => "Simples Nacional",
			"Lucro Presumido" => "Lucro Presumido",
			"Lucro Real" => "Lucro Real"
		];

		if(empty($a_mod)){  //Não tem os dados de atualização, então está cadastrando
			$values = $_POST;
			$prefix = "";

			$input_values = [
				"nome" 				=> (!empty($_POST["busca_nome_like"])? $_POST["busca_nome_like"]: ""),
				"fantasia" 			=> (!empty($_POST["busca_fantasia_like"])? $_POST["busca_fantasia_like"]: ""),
				"cnpj" 				=> (!empty($_POST["busca_cnpj"])? $_POST["busca_cnpj"]: ""),
				"uf" 				=> (!empty($_POST["uf"])? $_POST["uf"]: ""),

				"ftpServer" 		=> (!empty($_POST["ftpServer"])? $_POST["ftpServer"]: ""),
				"ftpUsername" 		=> (!empty($_POST["ftpUsername"])? $_POST["ftpUsername"]: ""),
				"ftpUserpass" 		=> (!empty($_POST["ftpUserpass"])? $_POST["ftpUserpass"]: ""),
				"cidade" 			=> (!empty($_POST["cidade"])? $_POST["cidade"]: ""),
				"dataRegistroCNPJ" 	=> (!empty($_POST["dataRegistroCNPJ"])? $_POST["dataRegistroCNPJ"]: "")
			];

			$btn_txt = "Cadastrar";
		}else{ //Tem os dados de atualização, então apenas mantém os valores.
			$values = $a_mod;
			$prefix = "empr_tx_";

			$input_values = [
				"cidade" => $a_mod["empr_nb_cidade"],
				"dataRegistroCNPJ" => empty($a_mod["empr_tx_dataRegistroCNPJ"])? null: $a_mod["empr_tx_dataRegistroCNPJ"],
				"ftpServer" => $a_mod["empr_tx_ftpServer"] == "ftp:ftp-jornadas.positronrt.com.br"? "": $a_mod["empr_tx_ftpServer"],
				"ftpUsername" => $a_mod["empr_tx_ftpUsername"] == "u:08995631000108"? "": $a_mod["empr_tx_ftpUsername"],
				"ftpUserpass" => $a_mod["empr_tx_ftpUserpass"] == "p:0899"? "": $a_mod["empr_tx_ftpUserpass"]
			];

			$btn_txt = "Atualizar";
		}

		$campos = [
			"status", "cep", "endereco", "numero", "bairro", "cnpj",
			"nome", "fantasia", "complemento", "referencia", "fone1",
			"fone2", "contato", "email", "inscricaoEstadual", "inscricaoMunicipal",
			"regimeTributario", "logo", "domain", "Ehmatriz",
			"ftpServer", "ftpUsername"
		];
		foreach($campos as $campo){
			if(empty($input_values[$campo])){
				$input_values[$campo] = !empty($values[$prefix.$campo])? $values[$prefix.$campo]: "";
			}
		}

		$input_values["ftpServer"]	 = !empty($input_values["ftpServer"])? $input_values["ftpServer"]: "";
		$input_values["ftpUsername"] = !empty($input_values["ftpUsername"])? $input_values["ftpUsername"]: "";


		$iconeExcluirLogo = (!empty($input_values["logo"]))? gerarLogoExcluir($a_mod["empr_nb_id"], "excluirLogo"): "";

        if(is_int(strpos($_SESSION["user_tx_nivel"], "Super Administrador"))){
            $campo_dominio = campo_domain("Nome","nomeDominio",$input_values["domain"]?? "",2,"domain");
        }else{
            $campo_dominio = texto("Nome",$input_values["domain"]?? "",3);
        }
        $existeMatrizQtd = mysqli_fetch_assoc(query("SELECT COUNT(*) AS qtd FROM empresa WHERE empr_tx_Ehmatriz = 'sim';"));
        $ehMatrizDefault = !empty($input_values["Ehmatriz"]) ? $input_values["Ehmatriz"] : ((intval($existeMatrizQtd["qtd"]?? 0) > 0) ? "nao" : "sim");
        $campo_Ehmatriz = combo("É matriz?","matriz", $ehMatrizDefault, 2, ["sim" => "Sim", "nao" => "Não"]);

		$cidade = [
			"cida_tx_uf" => "",
			"cida_tx_nome" => ""
		];
        if(!empty($input_values["cidade"])){
            $cidade_query = query("SELECT * FROM cidade WHERE cida_tx_status = 'ativo' AND cida_nb_id = ".$input_values["cidade"]);
            $cidade = mysqli_fetch_array($cidade_query, MYSQLI_ASSOC);
        }
        if(empty($cidade) || !is_array($cidade)){
            $cidade = ["cida_tx_uf" => "", "cida_tx_nome" => ""];
        }

        $campo_cidade = texto("Cidade/UF", "[".$cidade["cida_tx_uf"]."] ".$cidade["cida_tx_nome"], 2);
    	if (is_bool(strpos($_SESSION["user_tx_nivel"], "Super Administrador")) && (!empty($input_values["Ehmatriz"]) && $input_values["Ehmatriz"] == "sim")) {
			$c = [
				texto("CPF/CNPJ*",				$input_values["cnpj"],2),
				texto("Nome*",					$input_values["nome"],4),
				texto("Nome Fantasia",			$input_values["fantasia"],3),
				texto("Status",					$input_values["status"],2),
				texto("CEP*",					$input_values["cep"],2),
				texto("Endereço*",				$input_values["endereco"],4),
				texto("Número*",				$input_values["numero"],2),
				texto("Bairro*",				$input_values["bairro"],3),
				texto("Complemento",			$input_values["complemento"],2),
				texto("Referência",				$input_values["referencia"],4),
				$campo_cidade,
				texto("Telefone 1",				$input_values["fone1"],2),
				texto("Telefone 2",				$input_values["fone2"],2),
				texto("Contato",				$input_values["contato"],3),
				texto("E-mail*",				$input_values["email"],3),
				texto("Inscrição Estadual",		$input_values["inscricaoEstadual"],3),
				texto("Inscrição Municipal",	$input_values["inscricaoMunicipal"],3),
				texto("Regime Tributário",		$input_values["regimeTributario"],3),
				texto("Data Reg. CNPJ",			$input_values["dataRegistroCNPJ"],3),
				$campo_dominio,
				$campo_Ehmatriz,
				
				texto("Servidor FTP",$input_values["ftpServer"], 3),
				texto("Usuário FTP",$input_values["ftpUsername"], 3)
			];
		
		}else{
			$c = [
				campo("CPF/CNPJ*","cnpj",							$input_values["cnpj"],2,"MASCARA_CPF/CNPJ","onkeyup='checarCNPJ(this.value);'"),
				campo("Nome*","nome",								$input_values["nome"],4,"","maxlength='65'"),
				campo("Nome Fantasia","fantasia",					$input_values["fantasia"],4,"","maxlength='65'"),
				combo("Status","status",							$input_values["status"],2,["ativo" => "Ativo", "inativo" => "Inativo"]),
				campo("CEP*","cep",									$input_values["cep"],2,"MASCARA_CEP","onkeyup='carrega_cep(this.value);'"),
				campo("Endereço*","endereco",						$input_values["endereco"],5,"","maxlength='100'"),
				campo("Número*","numero",							$input_values["numero"],2),
				campo("Bairro*","bairro",							$input_values["bairro"],3,"","maxlength='30'"),
				campo("Complemento","complemento",					$input_values["complemento"],3),
				campo("Referência","referencia",					$input_values["referencia"],2),
				combo_net("Cidade/UF*","cidade",					$input_values["cidade"],3,"cidade","","","cida_tx_uf"),
				campo("Telefone 1","fone1",							$input_values["fone1"],2,"MASCARA_FONE"),
				campo("Telefone 2","fone2",							$input_values["fone2"],2,"MASCARA_FONE"),
				campo("Contato","contato",							$input_values["contato"],3),
				campo("E-mail*","email",							$input_values["email"],3),
				campo("Inscrição Estadual","inscricaoEstadual",		$input_values["inscricaoEstadual"],3),
				campo("Inscrição Municipal","inscricaoMunicipal",	$input_values["inscricaoMunicipal"],3),
				combo("Regime Tributário","regimeTributario",		$input_values["regimeTributario"],3,$regimes),
				campo_data("Data Reg. CNPJ","dataRegistroCNPJ",		$input_values["dataRegistroCNPJ"],3),

				arquivo("Logo (.png, .jpeg)".$iconeExcluirLogo,"logo",$input_values["logo"],4),
				$campo_dominio,
				$campo_Ehmatriz,
				
				campo("Servidor FTP","ftpServer",$input_values["ftpServer"],3),
				campo("Usuário FTP","ftpUsername",$input_values["ftpUsername"],3),
				campo_senha("Senha FTP","ftpUserpass",$input_values["ftpUserpass"],3)
			];
		}
		

		
		$cJornada[] = combo_bd("Parâmetros da Jornada*","parametro",($a_mod["empr_nb_parametro"]?? ""), 6, "parametro","onchange='carregarParametro(this.value)'");

		$file = basename(__FILE__);
		$file = explode(".", $file);

		if (!empty($a_mod["empr_nb_id"])) {
			$sqlArquivos= query("SELECT 
			documento_empresa.docu_nb_id,
			documento_empresa.empr_nb_id,
			documento_empresa.docu_tx_dataCadastro,
			documento_empresa.docu_tx_dataVencimento,
			documento_empresa.docu_tx_caminho,
			documento_empresa.docu_tx_descricao,
			documento_empresa.docu_tx_nome,
			documento_empresa.docu_tx_visivel,
			documento_empresa.docu_tx_assinado,
			t.tipo_tx_nome,
			gd.grup_tx_nome,
			subg.sbgr_tx_nome,
			t.tipo_nb_id,
			gd.grup_nb_id,
			subg.sbgr_nb_id
			FROM documento_empresa
			LEFT JOIN tipos_documentos t 
			ON documento_empresa.docu_tx_tipo = t.tipo_nb_id
			LEFT JOIN grupos_documentos gd 
			ON t.tipo_nb_grupo = gd.grup_nb_id
			LEFT JOIN sbgrupos_documentos subg
			ON subg.sbgr_nb_id = documento_empresa.docu_nb_sbgrupo
			WHERE documento_empresa.empr_nb_id = ".$a_mod["empr_nb_id"]);
			$arquivos = mysqli_fetch_all($sqlArquivos, MYSQLI_ASSOC);
		}

		$botao = [
			botao($btn_txt,"cadastrarEmpresa","id",($_POST["id"]?? ""),"","","btn btn-success"),
			criarBotaoVoltar("cadastro_empresa.php")
		];
		
		echo abre_form("Dados da Empresa/Filial");
		echo linha_form($c);
		echo "<br>";
		fieldset("CONVEÇÃO SINDICAL - JORNADA DO FUNCIONÁRIO PADRÃO");

		if(!empty($a_mod["empr_nb_userCadastro"])){
			$a_userCadastro = carregar("user",$a_mod["empr_nb_userCadastro"]);
			$txtCadastro = "Registro inserido por ".$a_userCadastro["user_tx_login"]." às ".data($a_mod["empr_tx_dataCadastro"]).".";
			$cJornada[] = texto("Data de Cadastro",$txtCadastro,3);
			if(!empty($a_mod["empr_nb_userAtualiza"])){
				$atualizacaoUser = carregar("user",$a_mod["empr_nb_userAtualiza"]);
				if(!empty($atualizacaoUser)){
					$txtAtualiza = "Registro atualizado por ".$atualizacaoUser["user_tx_login"]." às ".data($a_mod["empr_tx_dataAtualiza"],1).".";
					$cJornada[] = texto("Última Atualização",$txtAtualiza,3);
				}
			}
		}

		echo linha_form($cJornada);

		echo fecha_form($botao);

		echo 
			"<iframe id=frame_parametro style='display: none;'></iframe>
			<script>
				function carregarParametro(id){
					document.getElementById('frame_parametro').src='cadastro_funcionario.php?acao=carregarParametro&parametro='+id;
				}
			</script>"
		;

		if (!empty($a_mod["empr_nb_id"])) {
			echo "</div><div class='col-md-12'><div class='col-md-12 col-sm-12'>".arquivosEmpresa("Documentos", $a_mod["empr_nb_id"], $arquivos);
		}

		rodape();

		
		echo 
			"<form name='form_excluir_arquivo2' method='post' action='cadastro_empresa.php'>
				<input type='hidden' name='idEmpresa' value=''>
				<input type='hidden' name='idArq' value=''>
				<input type='hidden' name='acao' value=''>
			</form>

			<form name='form_download_arquivo' method='post' action='cadastro_empresa.php'>
				<input type='hidden' name='idEmpresa' value=''>
				<input type='hidden' name='caminho' value=''>
				<input type='hidden' name='acao' value=''>
			</form>

			<script type='text/javascript'>
				function remover_arquivo(id, idArq, arquivo, acao ) {
					if (confirm('Deseja realmente excluir o arquivo '+arquivo+'?')) {
						document.form_excluir_arquivo2.idEmpresa.value = id;
						document.form_excluir_arquivo2.idArq.value = idArq;
						document.form_excluir_arquivo2.acao.value = acao;
						document.form_excluir_arquivo2.submit();
					}
				}

				function downloadArquivo(id, caminho, acao) {
					document.form_download_arquivo.idEmpresa.value = id;
					document.form_download_arquivo.caminho.value = caminho;
					document.form_download_arquivo.acao.value = acao;
					document.form_download_arquivo.submit();
				}
			</script>

			<iframe id=frame_cep style='display: none;'></iframe>
			<form method='post' name='form_modifica' id='form_modifica'>
				<input type='hidden' name='id' value=''>
				<input type='hidden' name='acao' value='modificarEmpresa'>
			</form>
			<form name='form_excluir_arquivo' method='post' action='cadastro_empresa.php'>
				<input type='hidden' name='idEntidade' value=''>
				<input type='hidden' name='nome_arquivo' value=''>
				<input type='hidden' name='acao' value=''>
			</form>"
		;

		carregarCadastroJS($a_mod);
	}

	function gerarLogoExcluir(int $id, $acao, $campos="", $valores="", $target=""){
		
		$msg="Deseja excluir a CNH?";

		if(!empty($id)){
			return "<a style='text-shadow: none; color: #337ab7;' onclick='javascript:remover_foto(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\");' > (Excluir) </a>";
		}else{
			return "";
		}
	}

    function index(){
        	//ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
		include "check_permission.php";
		// APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
		verificaPermissao('/cadastro_empresa.php');
		
        cabecalho("Cadastro Empresa/Filial");

		$extra = 
			((!empty($_POST["busca_codigo"]))? 			" AND empr_nb_id = {$_POST["busca_codigo"]}'": "").
			((!empty($_POST["busca_nome_like"]))? 		" AND empr_tx_nome LIKE '%{$_POST["busca_nome_like"]}%'": "").
			((!empty($_POST["busca_fantasia_like"]))? 	" AND empr_tx_fantasia LIKE '%{$_POST["busca_fantasia_like"]}'": "").
			((!empty($_POST["busca_cnpj"]))? 			" AND empr_tx_cnpj = '{$_POST["busca_cnpj"]}'": "").
			((!empty($_POST["busca_uf"]))? 				" AND cida_tx_uf = '{$_POST["busca_uf"]}'": "")
		;

		if(empty($_POST["busca_status"])){
			$extra .= " AND empr_tx_status = 'ativo'";
		}elseif(!empty($_POST["busca_status"])){
			$extra .= " AND empr_tx_status = '{$_POST["busca_status"]}'";
		}

		$c = [
			campo("Código",			"busca_codigo",			($_POST["busca_codigo"]?? ""),			2, "MASCARA_NUMERO",	"maxlength='6' min='0'"),
			campo("Nome",			"busca_nome_like",		($_POST["busca_nome_like"]?? ""),		3, "",					"maxlength='65'"),
			campo("Nome Fantasia",	"busca_fantasia_like",	($_POST["busca_fantasia_like"]?? ""),	2, "",					"maxlength='65'"),
			campo("CPF/CNPJ",		"busca_cnpj",			($_POST["busca_cnpj"]?? ""),			2, "MASCARA_CPF/CNPJ"),
			combo("UF",				"busca_uf",				($_POST["busca_uf"]?? ""),				1, getUFs()),
			combo("Status",			"busca_status",			($_POST["busca_status"]?? "ativo"),		2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"])
		];

		$botao = [
			botao("Buscar","index"),
			botao("Limpar Filtro","limparFiltros"),
			botao("Inserir","visualizarCadastro","","","","","btn btn-success")
		];
		
		echo abre_form();
		echo linha_form($c);
		echo fecha_form($botao);

		//Configuração da tabela dinâmica{
			$gridFields = [
				"CÓDIGO" 		=> "empr_nb_id",
				"NOME" 			=> "IF(empr_tx_Ehmatriz = 'sim', CONCAT('<i class=\"fa fa-star\" aria-hidden=\"true\"></i> ', empr_tx_nome), empr_tx_nome) AS empr_tx_nome",
				"FANTASIA" 		=> "empr_tx_fantasia",
				"CPF/CNPJ" 		=> "empr_tx_cnpj",
				"CIDADE/UF" 	=> "CONCAT('[', cida_tx_uf, '] ', cida_tx_nome) AS ufCidade",
				"STATUS" 		=> "empr_tx_status"
			];
			$camposBusca = [
				"busca_codigo" 			=> "empr_nb_id",
				"busca_nome_like"		=> "empr_tx_nome",
				"busca_fantasia_like" 	=> "empr_tx_fantasia",
				"busca_cnpj" 			=> "empr_tx_cnpj",
				"busca_uf" 				=> "cida_tx_uf",
				"busca_status" 			=> "empr_tx_status"
			];
			$queryBase = (
				"SELECT ".implode(", ", array_values($gridFields))." FROM empresa
					JOIN cidade ON empr_nb_cidade = cida_nb_id"
			);


			$actions = criarIconesGrid(
				["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
				["cadastro_empresa.php", "cadastro_empresa.php"],
				["modificarEmpresa()", "excluirEmpresa()"]
			);
			$actions["functions"][1] .= "esconderInativar('glyphicon glyphicon-remove search-remove', 5);";

			$gridFields["actions"] = $actions["tags"];

			$jsFunctions =
				"const funcoesInternas = function(){
					".implode(" ", $actions["functions"])."
				}"
			;

			echo gridDinamico("tabelaEmpresas", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		//}

		rodape();
	}
