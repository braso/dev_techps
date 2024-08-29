<?php
	/* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

	include "conecta.php";

	function excluirEmpresa(){
		remover('empresa',$_POST['id']);
		index();
		exit;
	}

	function excluirLogo(){
		atualizar('empresa',array('empr_tx_logo'),array(''),$_POST['idEntidade']);
		$_POST['id']=$_POST['idEntidade'];
		modificarEmpresa();
		exit;
	}

	function downloadArquivo() {
		// Verificar se o arquivo existe
		if (file_exists($_POST['caminho'])) {
			// Configurar cabeçalhos para forçar o download
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename=' . basename($_POST['caminho']));
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . filesize($_POST['caminho']));

			// Lê o arquivo e o envia para o navegador
			readfile($_POST['caminho']);
			exit;
		} else {
			set_status("O arquivo não foi encontrado.");
		}
		$_POST['id'] = $_POST['idEmpresa'];
		modificarEmpresa();
		exit;
	}

	function enviar_documento() {
		// global $a_mod;

		$idEmpresa = $_POST['idEmpresa'];
		$arquivos =  $_FILES['file'];
		$novo_nome = $_POST['file-name'];
		$descricao = $_POST['description-text'];
		$mimeType = mime_content_type($arquivos['tmp_name']);

		$allowed = array(
			'image/jpeg',
			'image/png',
			'application/msword',
			'application/pdf',
			'application/vnd.android.package-archive',
			'application/zip',
			'application/octet-stream',
			'application/x-zip-compressed'
		);

		if ($arquivos['error'] !== UPLOAD_ERR_OK) {
			echo "Erro no upload: " . $arquivos['error'];
			exit;
		}

		if (in_array($mimeType, $allowed) && $arquivos['name'] != '') {
				$pasta_empresa = "arquivos/doc_empresa/$idEmpresa/";
		
				if (!is_dir($pasta_empresa)) {
					mkdir($pasta_empresa, 0755, true);
				}
		
				$arquivo_temporario = $arquivos['tmp_name'];
				$extensao = pathinfo($arquivos['name'], PATHINFO_EXTENSION); 
				$novo_nome_com_extensao = $novo_nome . '.' . $extensao;
				$caminho_destino = $pasta_empresa . $novo_nome_com_extensao;
		
				if (move_uploaded_file($arquivo_temporario, $caminho_destino)) {
					inserir('documento_empresa', ['empr_nb_id','doc_tx_nome','doc_tx_descricao','doc_tx_caminho','doc_tx_dataCadastro'],[$idEmpresa,$novo_nome_com_extensao,$descricao,$caminho_destino,date("Y-m-d H:i:s")]);
				}
		}

		$_POST['id'] = $idEmpresa;
		modificarEmpresa();
		exit;
	}

	function excluir_documento() {

		query("DELETE FROM documento_empresa WHERE doc_nb_id = $_POST[idArq]");
		
		$_POST['id'] = $_POST['idEmpresa'];
		modificarEmpresa();
		exit;
	}

	function modificarEmpresa(){
		global $a_mod;

		$a_mod=carregar('empresa',$_POST['id']);

		visualizarCadastro();
		exit;
	}

	function cadastrarEmpresa(){

		if (!empty($_POST['id'])) {
			$sqlCheckNivel = mysqli_fetch_assoc(query("SELECT empr_tx_Ehmatriz FROM empresa WHERE empr_nb_id = " . $_POST['id'] . " LIMIT 1;"));
		} else {
			$sqlCheckNivel = ['empr_tx_Ehmatriz' => 'nao'];
		}
		$camposObrig = ['cnpj', 'nome', 'cep', 'numero', 'email', 'parametro', 'cidade', 'endereco', 'bairro'];
		foreach ($camposObrig as $campo) {
			if (!isset($_POST[$campo]) && $sqlCheckNivel["empr_tx_Ehmatriz"] != 'sim' || empty($_POST[$campo])) {
				echo '<script>alert("Preencha todas as informações obrigatórias.")</script>';
				visualizarCadastro();
				exit;
			}
		}

		if (!isset($_POST['id']) || empty($_POST['id'])) {

			$empresa = [
				'empr_tx_Ehmatriz'	=> $_POST['matriz'],
				'empr_nb_parametro' => $_POST['parametro'],
				'empr_nb_cidade' 	=> $_POST['cidade'],
				'empr_tx_domain' 	=> $_SERVER['HTTP_ORIGIN'] . (is_int(strpos($_SERVER["REQUEST_URI"], 'dev_')) ? 'dev_techps/' : 'techps/') . $_POST['nomeDominio']
			];
			$campos = [
				'nome', 'fantasia', 'cnpj', 'cep', 'endereco', 'bairro', 'numero', 'complemento',
				'referencia', 'fone1', 'fone2', 'email', 'inscricaoEstadual', 'inscricaoMunicipal',
				'regimeTributario', 'status', 'status', 'contato',
				'ftpServer', 'ftpUsername', 'ftpUserpass', 'dataRegistroCNPJ'
			];

			foreach ($campos as $campo) {
				if (!empty($_POST[$campo])) {
					$empresa['empr_tx_' . $campo] = $_POST[$campo];
				}
			}


			$empty_ftp_inputs = empty($_POST['ftpServer']) + empty($_POST['ftpUsername']) + empty($_POST['ftpUserpass']) + 0;

			if ($empty_ftp_inputs == 3) {
				$_POST['ftpServer']   = 'ftp:ftp-jornadas.positronrt.com.br';
				$_POST['ftpUsername'] = 'u:08995631000108';
				$_POST['ftpUserpass'] = 'p:0899';
			} elseif ($empty_ftp_inputs > 0) {
				echo '<script>alert("Preencha os 3 campos de FTP.")</script>';
				visualizarCadastro();
				exit;
			}

			if (isset($_POST['id']) && !empty($_POST['id'])) {
				$empresa['empr_nb_userAtualiza'] = $_SESSION['user_nb_id'];
				$empresa['empr_tx_dataAtualiza'] = date('Y-m-d H:i:s');

				atualizar('empresa', array_keys($empresa), array_values($empresa), $_POST['id']);
				$id_empresa = $_POST['id'];
			} else {
				$empresa['empr_nb_userCadastro'] = $_SESSION['user_nb_id'];
				$empresa['empr_tx_dataCadastro'] = date('Y-m-d H:i:s');
				try {
					$id_empresa = inserir('empresa', array_keys($empresa), array_values($empresa))[0];
				} catch (Exception $e) {
					print_r($e);
				}
			}

			$file_type = $_FILES['logo']['type']; //returns the mimetype

			$allowed = array("image/jpeg", "image/gif", "image/png");
			if (in_array($file_type, $allowed) && $_FILES['logo']['name'] != '') {

				if (!is_dir("arquivos/empresa/$id_empresa/")) {
					mkdir("arquivos/empresa/$id_empresa/");
				}

				$arq = enviar('logo', "arquivos/empresa/$id_empresa/", $id_empresa);
				if ($arq) {
					atualizar('empresa', ['empr_tx_logo'], [$arq], $id_empresa);
				}
			}
		} else {
			$empresa = [
				'empr_nb_parametro' => $_POST['parametro'],
				'empr_nb_userAtualiza' => $_SESSION['user_nb_id'],
				'empr_tx_dataAtualiza' => date('Y-m-d H:i:s')
			];

			$campos = [
				'nome', 'fantasia', 'cnpj', 'cep', 'endereco', 'bairro', 'numero', 'complemento',
				'referencia', 'fone1', 'fone2', 'email', 'inscricaoEstadual', 'inscricaoMunicipal',
				'regimeTributario', 'status', 'status', 'contato',
				'ftpServer', 'ftpUsername', 'ftpUserpass', 'dataRegistroCNPJ'
			];

			foreach ($campos as $campo) {
				$empresa['empr_tx_' . $campo] = $_POST[$campo];
			}


			atualizar('empresa', array_keys($empresa), array_values($empresa), $_POST['id']);

			$id_empresa = $_POST['id'];

			$file_type = $_FILES['logo']['type']; //returns the mimetype

			$allowed = array("image/jpeg", "image/gif", "image/png");
			if (in_array($file_type, $allowed) && $_FILES['logo']['name'] != '') {

				if (!is_dir("arquivos/empresa/$id_empresa")) {
					mkdir("arquivos/empresa/$id_empresa");
				}


				$arq = enviar('logo', "arquivos/empresa/$id_empresa/", $id_empresa);
				if ($arq) {
					atualizar('empresa', ['empr_tx_logo'], [$arq], $id_empresa);
				}
			}
		}


		visualizarCadastro();
		exit;
	}


	function buscarCEP($cep){
		// 		$resultado = @file_get_contents('https://viacep.com.br/ws/'.urlencode($cep).'/json/');
				
		$url = 'https://viacep.com.br/ws/'.urlencode($cep).'/json/';
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			
		$resultado = curl_exec($ch);
		$arr = json_decode($resultado, true);
		return $arr;
	}

	function carregarEndereco(){
		global $CONTEX;
		
		$arr = buscarCEP($_GET['cep']);
		echo 
			"<script type='text/javascript'>
				parent.document.contex_form.endereco.value='".$arr['logradouro']."';
				parent.document.contex_form.bairro.value='".$arr['bairro']."';

				var selecionado = $('.cidade',parent.document);
				selecionado.empty();
				selecionado.append('<option value=".$arr['ibge'].">".[$arr['uf']]." ".$arr['localidade']."</option>');
				selecionado.val('".$arr['ibge']."').trigger('change');
			</script>";
		exit;
	}

	function checarCNPJ(){
		if(strlen($_GET['cnpj']) == 18 || strlen($_GET['cnpj']) == 14){
			$id = (int)$_GET['id'];
			$cnpj = substr($_GET['cnpj'], 0, 18);

			$sql = query("SELECT * FROM empresa WHERE empr_tx_cnpj = '$cnpj' AND empr_nb_id != $id LIMIT 1");
			$a = carrega_array($sql);
			
			if($a['empr_nb_id'] > 0){
				echo 
					"<script type='text/javascript'>
						if(confirm('CPF/CNPJ já cadastrado, deseja atualizar o registro?')){
							parent.document.form_modifica.id.value='".$a["empr_nb_id"]."';
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
						document.getElementById('frame_cep').src = '".$path_parts["basename"]."?acao=carregarEndereco&cep=' + num;
					}
				}
				
				function checarCNPJ(cnpj){
					if(cnpj.length == '18' || cnpj.length == '14'){
						document.getElementById('frame_cep').src='".$path_parts["basename"]."?acao=checarCNPJ&cnpj='+cnpj+'&id=".$a_mod["empr_nb_id"]."'
					}
				}
				$(document).ready(function() {
					$('#cnpj').on('blur', function(){
						var cnpj = $(this).val();

						$.ajax({
							url: 'conecta.php',
							method: 'POST',
							data: { cnpj: cnpj },
							dataType: 'json',
							success: function(response) {
								console.log(response);
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

		cabecalho('Cadastro Empresa/Filial');

		$regimes = ['', 'Simples Nacional', 'Lucro Presumido', 'Lucro Real'];

		if(empty($a_mod)){  //Não tem os dados de atualização, então está cadastrando
			$values = $_POST;
			$prefix = '';

			$input_values = [
				'ftpServer' => ($_POST['ftpServer']?? ''),
				'ftpUsername' => ($_POST['ftpUsername']?? ''),
				'ftpUserpass' => ($_POST['ftpUserpass']?? ''),
				'cidade' => ($_POST['cidade']?? ''),
				'dataRegistroCNPJ' => ($_POST['dataRegistroCNPJ']?? '')
			];
			$btn_txt = 'Cadastrar';
		}else{ //Tem os dados de atualização, então apenas mantém os valores.
			$values = $a_mod;
			$prefix = 'empr_tx_';

			$input_values = [
				'cidade' => $a_mod['empr_nb_cidade'],
				'dataRegistroCNPJ' => empty($a_mod['empr_tx_dataRegistroCNPJ'])? null: $a_mod['empr_tx_dataRegistroCNPJ'],
				'ftpServer' => $a_mod['empr_tx_ftpServer'] == 'ftp:ftp-jornadas.positronrt.com.br'? '': $a_mod['empr_tx_ftpServer'],
				'ftpUsername' => $a_mod['empr_tx_ftpUsername'] == 'u:08995631000108'? '': $a_mod['empr_tx_ftpUsername'],
				'ftpUserpass' => $a_mod['empr_tx_ftpUserpass'] == 'p:0899'? '': $a_mod['empr_tx_ftpUserpass']
			];

			$btn_txt = 'Atualizar';
		}

		$campos = [
			'status','cep','endereco','numero','bairro','cnpj',
			'nome','fantasia','complemento','referencia','fone1',
			'fone2','contato','email','inscricaoEstadual','inscricaoMunicipal',
			'regimeTributario','logo','domain', 'Ehmatriz',
			'ftpServer', 'ftpUsername'
		];
		foreach($campos as $campo){
			$input_values[$campo] = !empty($values[$prefix.$campo])? $values[$prefix.$campo]: "";
		}

		$input_values['ftpServer']	 = !empty($input_values['ftpServer'])? $input_values['ftpServer']: "---";
		$input_values['ftpUsername'] = !empty($input_values['ftpUsername'])? $input_values['ftpUsername']: "---";


		if(!empty($input_values['logo'])){
			$iconeExcluirLogo = gerarLogoExcluir($a_mod['empr_nb_id'], 'excluirLogo');
		}else{
			$iconeExcluirLogo = '';
		}

		if(is_int(strpos($_SESSION['user_tx_nivel'], "Super Administrador"))){
			$campo_dominio = campo_domain('Nome do Domínio','nomeDominio',$input_values['domain']?? '',2,'domain');
			$campo_EhMatriz = combo('É matriz?','matriz',$input_values['Ehmatriz']?? '',2,['sim' => 'Sim', 'nao' => 'Não']);
		}else{
			$campo_dominio = texto('Nome do Domínio',$input_values['domain']?? '',3);
			$campo_EhMatriz = texto('É matriz?',$input_values['Ehmatriz']?? '',2);
		}

		if(!empty($input_values['cidade'])){
			$cidade_query = query("SELECT * FROM cidade WHERE cida_tx_status = 'ativo' AND cida_nb_id = ".$input_values['cidade']);
			$cidade = mysqli_fetch_array($cidade_query);
		}else{
			$cidade = ['cida_tx_nome' => ''];
		}
		$campo_cidade = texto('Cidade/UF', "[".$cidade['cida_tx_uf']."] ".$cidade['cida_tx_nome'], 2);
    if (is_bool(strpos($_SESSION['user_tx_nivel'], "Super Administrador")) && (!empty($input_values['Ehmatriz']) && $input_values['Ehmatriz'] == 'sim')) {
			$c = [
				texto('CPF/CNPJ*',$input_values['cnpj'],2),
				texto('Nome*',$input_values['nome'],4),
				texto('Nome Fantasia',$input_values['fantasia'],3),
				texto('Status',$input_values['status'],2),
				texto('CEP*',$input_values['cep'],2),
				texto('Endereço*',$input_values['endereco'],4),
				texto('Número*',$input_values['numero'],2),
				texto('Bairro*',$input_values['bairro'],3),
				texto('Complemento',$input_values['complemento'],2),
				texto('Referência',$input_values['referencia'],4),
				$campo_cidade,
				texto('Telefone 1',$input_values['fone1'],2),
				texto('Telefone 2',$input_values['fone2'],2),
				texto('Contato',$input_values['contato'],3),
				texto('E-mail*',$input_values['email'],3),
				texto('Inscrição Estadual',$input_values['inscricaoEstadual'],3),
				texto('Inscrição Municipal',$input_values['inscricaoMunicipal'],3),
				texto('Regime Tributário',$input_values['regimeTributario'],3),
				texto('Data Reg. CNPJ',$input_values['dataRegistroCNPJ'],3),
				$campo_dominio,
				$campo_EhMatriz,
				
				texto('Servidor FTP',$input_values['ftpServer'], 3),
				texto('Usuário FTP',$input_values['ftpUsername'], 3)
			];
		
		}else{
			$c = [
				campo('CPF/CNPJ*','cnpj',$input_values['cnpj'],2,'MASCARA_CPF/CNPJ','onkeyup="checarCNPJ(this.value);"'),
				campo('Nome*','nome',$input_values['nome'],4,'','maxlength="65"'),
				campo('Nome Fantasia','fantasia',$input_values['fantasia'],4,'','maxlength="65"'),
				combo('Status','status',$input_values['status'],2,['ativo' => 'Ativo', 'inativo' => 'Inativo']),
				campo('CEP*','cep',$input_values['cep'],2,'MASCARA_CEP','onkeyup="carrega_cep(this.value);"'),
				campo('Endereço*','endereco',$input_values['endereco'],5,'','maxlength="100"'),
				campo('Número*','numero',$input_values['numero'],2),
				campo('Bairro*','bairro',$input_values['bairro'],3,'','maxlength="30"'),
				campo('Complemento','complemento',$input_values['complemento'],3),
				campo('Referência','referencia',$input_values['referencia'],2),
				combo_net('Cidade/UF*','cidade',$input_values['cidade'],3,'cidade','','','cida_tx_uf'),
				campo('Telefone 1','fone1',$input_values['fone1'],2,'MASCARA_FONE'),
				campo('Telefone 2','fone2',$input_values['fone2'],2,'MASCARA_FONE'),
				campo('Contato','contato',$input_values['contato'],3),
				campo('E-mail*','email',$input_values['email'],3),
				campo('Inscrição Estadual','inscricaoEstadual',$input_values['inscricaoEstadual'],3),
				campo('Inscrição Municipal','inscricaoMunicipal',$input_values['inscricaoMunicipal'],3),
				combo('Regime Tributário','regimeTributario',$input_values['regimeTributario'],3,$regimes),
				campo_data('Data Reg. CNPJ','dataRegistroCNPJ',$input_values['dataRegistroCNPJ'],3),
				arquivo('Logo (.png, .jpg)'.$iconeExcluirLogo,'logo',$input_values['logo'],4),
				$campo_dominio,
				$campo_EhMatriz,
				
				campo('Servidor FTP','ftpServer',$input_values['ftpServer'],3),
				campo('Usuário FTP','ftpUsername',$input_values['ftpUsername'],3),
				campo_senha('Senha FTP','ftpUserpass',$input_values['ftpUserpass'],3)
			];
		}
		

		
		$cJornada[]=combo_bd('Parâmetros da Jornada*','parametro',($a_mod['empr_nb_parametro']?? ''),6,'parametro','onchange="carregarParametro(this.value)"');
		// $cJornada[]=campo('Jornada Semanal (Horas)','jornadaSemanal',$a_mod['enti_tx_jornadaSemanal'],3,MASCARA_NUMERO,'disabled=disabled');
		// $cJornada[]=campo('Jornada Sábado (Horas)','jornadaSabado',$a_mod['enti_tx_jornadaSabado'],3,MASCARA_NUMERO,'disabled=disabled');
		// $cJornada[]=campo('Percentual da HE(%)','percentualHE',$a_mod['enti_tx_percentualHE'],3,MASCARA_NUMERO,'disabled=disabled');
		// $cJornada[]=campo('Percentual da HE Sábado(%)','percentualSabadoHE',$a_mod['enti_tx_percentualSabadoHE'],3,MASCARA_NUMERO,'disabled=disabled');

		$file = basename(__FILE__);
		$file = explode('.', $file);

		if (!empty($a_mod['empr_nb_id'])) {
			$sqlArquivos= query("SELECT * FROM documento_empresa WHERE empr_nb_id = $a_mod[empr_nb_id]");
			$arquivos = mysqli_fetch_all($sqlArquivos, MYSQLI_ASSOC);
		}

		$botao = [
			botao($btn_txt,'cadastrarEmpresa','id',($_POST['id']?? ''),'','','btn btn-success'),
			botao('Voltar','voltar')
		];

		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], "cadastro_empresa.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_empresa.php";
			}
		}
		
		abre_form("Dados da Empresa/Filial");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		linha_form($c);
		echo "<br>";
		fieldset("CONVEÇÃO SINDICAL - JORNADA DO MOTORISTA PADRÃO");

		if(!empty($a_mod['empr_nb_userCadastro'])){
			$a_userCadastro = carregar('user',$a_mod['empr_nb_userCadastro']);
			$txtCadastro = "Registro inserido por ".$a_userCadastro["user_tx_login"]." às ".data($a_mod['empr_tx_dataCadastro']).".";
			$cJornada[] = texto("Data de Cadastro",$txtCadastro,3);
			if(!empty($a_mod['empr_nb_userAtualiza'])){
				$atualizacaoUser = carregar('user',$a_mod['empr_nb_userAtualiza']);
				if(!empty($atualizacaoUser)){
					$txtAtualiza = "Registro atualizado por ".$atualizacaoUser['user_tx_login']." às ".data($a_mod['empr_tx_dataAtualiza'],1).".";
					$cJornada[] = texto("Última Atualização",$txtAtualiza,3);
				}
			}
		}

		linha_form($cJornada);

		fecha_form($botao);

		$path_parts = pathinfo( __FILE__ );
		echo 
			"<iframe id=frame_parametro style='display: none;'></iframe>
			<script>
				function carregarParametro(id){
					document.getElementById('frame_parametro').src='cadastro_motorista.php?acao=carregarParametro&parametro='+id;
				}
			</script>"
		;

		if (!empty($a_mod['empr_nb_id'])) {
			echo arquivosEmpresa("Documentos", $a_mod['empr_nb_id'], $arquivos);
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
					if (confirm('Deseja realmente excluir o arquivo ' + arquivo + '?')) {
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

	function gerarLogoExcluir(int $id, $acao, $campos='', $valores='', $target=''){
		
		$msg='Deseja excluir a CNH?';

		if(!empty($id)){
			return "<a style='text-shadow: none; color: #337ab7;' onclick='javascript:remover_foto(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\");' > (Excluir) </a>";
		}else{
			return '';
		}
	}

	function index(){

		cabecalho("Cadastro Empresa/Filial");

		if ($_SESSION['user_nb_empresa'] > 0 && is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$extraEmpresa = " AND empr_nb_id = '$_SESSION[user_nb_empresa]'";
		}

		$extra = 
			((!empty($_POST["busca_codigo"]))? 		" AND empr_nb_id = '".$_POST["busca_codigo"]."'": "").
			((!empty($_POST["busca_nome"]))? 		" AND empr_tx_nome LIKE '%".$_POST["busca_nome"]."%'": "").
			((!empty($_POST["busca_fantasia"]))? 	" AND empr_tx_fantasia LIKE '%".$_POST["busca_fantasia"]."%'": "").
			((!empty($_POST["busca_cnpj"]))? 		" AND empr_tx_cnpj = '".$_POST["busca_cnpj"]."'": "").
			((!empty($_POST["busca_status"]))? 		" AND empr_tx_status = '".$_POST["busca_status"]."'": "").
			((!empty($_POST["busca_uf"]))? 			" AND cida_tx_uf = '".$_POST["busca_uf"]."'": "")
		;
		

		$uf = ['', 'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
		

		$c = [
			campo("Código",			"busca_codigo",		($_POST["busca_codigo"]?? ""),		2, "MASCARA_NUMERO",	"maxlength='6'"),
			campo("Nome",			"busca_nome",		($_POST["busca_nome"]?? ""),		3, "",					"maxlength='65'"),
			campo("Nome Fantasia",	"busca_fantasia",	($_POST["busca_fantasia"]?? ""),	2, "",					"maxlength='65'"),
			campo("CPF/CNPJ",		"busca_cnpj",		($_POST["busca_cnpj"]?? ""),		2, "MASCARA_CPF/CNPJ"),
			combo("UF",				"busca_uf",			($_POST["busca_uf"]?? ""),			1, $uf),
			combo("Status",			"busca_status",		($_POST["busca_status"]?? ""),	2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"])
		];

		$botao = [
			botao('Buscar','index'),
			botao('Inserir','visualizarCadastro','','','','','btn btn-success')
		];
		
		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($botao);

		$sql = 
			"SELECT *, concat('[', cida_tx_uf, '] ', cida_tx_nome) as ufCidade FROM empresa
				JOIN cidade ON empr_nb_cidade = cida_nb_id
				WHERE 1 = 1
					$extra
				ORDER BY empr_tx_EhMatriz DESC, empr_nb_id";

		$gridCols = [
			'CÓDIGO' => 'empr_nb_id',
			'NOME' => 'empr_tx_nome',
			'FANTASIA' => 'empr_tx_fantasia',
			'CPF/CNPJ' => 'empr_tx_cnpj',
			'CIDADE/UF' => 'ufCidade',
			'STATUS' => 'empr_tx_status',
			'<spam class="glyphicon glyphicon-search"></spam>' => 'icone_modificar(empr_nb_id,modificarEmpresa)',
			'<spam class="glyphicon glyphicon-remove"></spam>' => 'icone_excluir(empr_nb_id,excluirEmpresa)'
		];
		
		grid($sql,array_keys($gridCols),array_values($gridCols),'','12',1,"desc",'10');

		rodape();

	}