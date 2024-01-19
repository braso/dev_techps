<?php
	// Modo debug
		// ini_set('display_errors', 1);
		// error_reporting(E_ALL);
	
	include "conecta.php";


	function exclui_motorista() {
		remover('entidade', $_POST['id']);

		index();
		exit;
	}


	function excluir_foto() {
		atualizar('entidade', array('enti_tx_foto'), array(''), $_POST['idEntidade']);
		$_POST['id'] = $_POST['idEntidade'];
		modifica_motorista();
		exit;
	}

	function excluir_cnh() {
		atualizar('entidade', array('enti_tx_cnhAnexo'), array(''), $_POST['idEntidade']);
		$_POST['id'] = $_POST['idEntidade'];
		modifica_motorista();
		exit;
	}

	function modifica_motorista(){
		global $a_mod;

		$a_mod = carregar('entidade', $_POST['id']);

		layout_motorista();
		exit;
	}

	function cadastra_motorista() {
		global $a_mod;

		if(!empty($_POST['matricula'])){
			$_POST['postMatricula'] = $_POST['matricula'];
		}


		$enti_campos = [
			'matricula', 'nome', 'nascimento', 'status', 
			'cpf','rg','civil','sexo','endereco','numero','complemento',
			'bairro','cidade','cep','fone1','fone2','email','ocupacao','salario','obs', 'empresa',
			'parametro','jornadaSemanal','jornadaSabado','percentualHE','percentualSabadoHE',
			'rgOrgao', 'rgDataEmissao', 'rgUf',
			'pai', 'mae', 'conjugue', 'tipoOperacao',
			'subcontratado', 'admissao', 'desligamento',
			'cnhRegistro', 'cnhValidade', 'cnhPrimeiraHabilitacao', 'cnhCategoria', 'cnhPermissao',
			'cnhObs', 'cnhCidade', 'cnhEmissao', 'cnhPontuacao', 'cnhAtividadeRemunerada',
			'banco', 'tipo'
		];
		
		$post_values = [
			'postMatricula', 'nome', 'nascimento', 'status',
			'cpf', 'rg', 'civil', 'sexo', 'endereco', 'numero', 'complemento',
			'bairro', 'cidade', 'cep', 'fone1', 'fone2', 'email', 'ocupacao', 'salario', 'obs', 'empresa',
			'parametro', 'jornadaSemanal', 'jornadaSabado', 'percentualHE', 'percentualSabadoHE',
			'rgOrgao', 'rgDataEmissao', 'rgUf',
			'pai', 'mae', 'conjugue', 'tipoOperacao',
			'subcontratado', 'admissao', 'desligamento',
			'cnhRegistro', 'cnhValidade', 'cnhPrimeiraHabilitacao', 'cnhCategoria', 'cnhPermissao',
			'cnhObs', 'cnhCidade', 'cnhEmissao', 'cnhPontuacao', 'cnhAtividadeRemunerada', 
			'setBanco', 'nivel',
		];

		for($f = 0; $f < sizeof($enti_campos); $f++){
			if(in_array($enti_campos[$f], ['cidade', 'empresa', 'parametro', 'cnhCidade'])){
				$bd_campo = 'enti_nb_'.$enti_campos[$f];	
			}else{
				$bd_campo = 'enti_tx_'.$enti_campos[$f];
			}
			if(isset($_POST[$enti_campos[$f]]) && !empty($_POST[$enti_campos[$f]])){
				$a_mod[$bd_campo] = $_POST[$enti_campos[$f]];
			}
			$enti_campos[$f] = $bd_campo;
		}

		//Conferir campos obrigatórios{
			$campos_obrigatorios = [
				'postMatricula' => 'Matrícula', 'nome' => 'Nome', 'nascimento' => 'Dt. Nascimento', 'parametro' => 'Parâmetros da Jornada', 'admissao' => 'Dt Admissão', 'cnhValidade' => 'Validade do CNH', 'cnhCategoria' => 'Categoria do CNH', 
				'cnhCidade' => 'Cidade do CNH', 'cnhEmissao' => 'Data de Emissão do CNH', 'jornadaSemanal' => 'Jornada Semanal', 'jornadaSabado' => 'Jornada Sábado', 'percentualHE' => 'Percentual da HE', 'percentualSabadoHE' => 'Percentual da HE Sábado', 
				'empresa' => 'Empresa', 'ocupacao' => 'Ocupação', 'cidade' => 'Cidade/UF', 'rg' => 'RG', 'endereco' => 'Endereço', 'cep' => 'CEP', 'bairro' => 'Bairro', 
				'email' => 'E-mail', 'cnhRegistro' => 'N° Registro da CNH'
			];
			$error = false;
			$emptyFields = '';
			foreach(array_keys($campos_obrigatorios) as $campo){
				if(!isset($_POST[$campo]) || empty($_POST[$campo])){
					$error = true;
					$emptyFields .= $campos_obrigatorios[$campo].', ';
				}
			}
			$emptyFields = substr($emptyFields, 0, strlen($emptyFields)-2);
			
			if($error){
				echo '<script>alert("Informações obrigatórias faltando: '.$emptyFields.'.")</script>';
				layout_motorista();
				exit;
			}

			unset($campos_obrigatorios);
		//}

		if(count(carregar('entidade', '', 'enti_tx_matricula', $_POST['postMatricula'])) > 0 && !isset($_POST['id'])){
			echo '<script>alert("Matrícula já cadastrada.")</script>';
			layout_motorista();
			exit;
		}
		if(!empty($_POST['login'])){
			$otherUser = mysqli_fetch_all(query("SELECT * FROM user WHERE user_tx_matricula = '".($_POST['login']?? $_POST['postMatricula'])."' LIMIT 1"), MYSQLI_ASSOC)[0];
			if (!empty($otherUser) && strval($otherUser['user_tx_matricula']) != strval($_POST['postMatricula']) && $otherUser['user_tx_login'] == $_POST['login']){
				set_status("ERRO: Login já cadastrado.");
				$a_mod = $_POST;
				modifica_motorista();
				exit;
			}
		}

		if(empty($_POST['salario'])){
			$_POST['salario'] = (float)0.0;
		}
		if(!isset($_POST['rgDataEmissao']) || empty($_POST['rgDataEmissao'])){
			$_POST['rgDataEmissao'] = '0000-00-00';
		}
		if(!isset($_POST['desligamento']) || empty($_POST['desligamento'])){
			$_POST['desligamento'] = '0000-00-00';
		}
		
		$_POST['nivel'] = 'Motorista';

		$enti_valores = [];
		for($f = 0; $f < sizeof($post_values); $f++){
			$enti_valores[] = $_POST[$post_values[$f]];
		}

		$cpfLimpo = str_replace(array('.', '-', '/'), "", $_POST['cpf']);
		

		if (!$_POST['id']) {//Se está criando um motorista novo
			$aEmpresa = carregar('empresa', $_POST['empresa']);
			if ($aEmpresa['empr_nb_parametro'] > 0) {
				$aParametro = carregar('parametro', $aEmpresa['empr_nb_parametro']);
				if (
					$aParametro['para_tx_jornadaSemanal'] != $a_mod['enti_tx_jornadaSemanal'] ||
					$aParametro['para_tx_jornadaSabado'] != $a_mod['enti_tx_jornadaSabado'] ||
					$aParametro['para_tx_percentualHE'] != $a_mod['enti_tx_percentualHE'] ||
					$aParametro['para_tx_percentualSabadoHE'] != $a_mod['enti_tx_percentualSabadoHE'] ||
					$aParametro['para_nb_id'] != $a_mod['enti_nb_parametro']
				) {
					$ehPadrao = 'Não';
				} else {
					$ehPadrao = 'Sim.';
				}
			}
			$enti_campos = array_merge($enti_campos, ['enti_nb_userCadastro', 'enti_tx_dataCadastro', 'enti_tx_ehPadrao']);
			$enti_valores = array_merge($enti_valores, [$_SESSION['user_nb_id'], date("Y-m-d H:i:s"), $ehPadrao]);
			$id = inserir('entidade', $enti_campos, $enti_valores);

			$user_infos = [
				'user_tx_matricula' 	=> $_POST['postMatricula'], 
				'user_tx_nome' 			=> $_POST['nome'], 
				'user_tx_login' 		=> (!empty($_POST['login'])? $_POST['login']: $_POST['postMatricula']), 
				'user_tx_nivel' 		=> $_POST['nivel'], 
				'user_tx_senha' 		=> md5($cpfLimpo), 
				'user_tx_status' 		=> $_POST['status'], 
				'user_nb_entidade' 		=> $id,
				'user_tx_nascimento' 	=> $_POST['nascimento'], 
				'user_tx_cpf' 			=> $_POST['cpf'], 
				'user_tx_rg' 			=> $_POST['rg'], 
				'user_nb_cidade' 		=> $_POST['cidade'], 
				'user_tx_email' 		=> $_POST['email'], 
				'user_nb_empresa' 		=> $_POST['empresa'],
				'user_nb_userCadastro' 	=> $_SESSION['user_nb_id'], 
				'user_tx_dataCadastro' 	=> date("Y-m-d H:i:s")
			];
			foreach($user_infos as $key => $value){
				if(empty($value)){
					unset($user_infos[$key]);
				}
			}

			// ADICIONA O USUARIO AO INSERIR NOVO motorista (USUARIO E SENHA = CPF) - PREENCHER A VARIAVEL USER_NB_ENTIDADE
			inserir('user', array_keys($user_infos), array_values($user_infos));
		}else{ // Se está editando um motorista existente

			$a_user = carrega_array(query(
				"SELECT * FROM user 
					WHERE user_nb_entidade = '$_POST[id]' 
						AND user_tx_nivel = 'Motorista'"
			));

			if($a_user['user_nb_id'] > 0){
				$user_infos = [
					'user_tx_nome' 			=> $_POST['nome'], 
					'user_tx_login' 		=> (!empty($_POST['login'])? $_POST['login']: $_POST['postMatricula']), 
					'user_tx_nivel' 		=> $_POST['nivel'], 
					'user_tx_senha' 		=> md5($cpfLimpo), 
					'user_tx_status' 		=> $_POST['status'], 
					'user_nb_entidade' 		=> $_POST['id'],
					'user_tx_nascimento' 	=> $_POST['nascimento'], 
					'user_tx_cpf' 			=> $_POST['cpf'], 
					'user_tx_rg' 			=> $_POST['rg'], 
					'user_nb_cidade' 		=> $_POST['cidade'], 
					'user_tx_email' 		=> $_POST['email'], 
					'user_nb_empresa' 		=> $_POST['empresa'],
					'user_nb_userAtualiza' 	=> $_SESSION['user_nb_id'], 
					'user_tx_dataAtualiza' 	=> date("Y-m-d H:i:s")
				];
				foreach($user_infos as $key => $value){
					if(empty($value)){
						unset($user_infos[$key]);
					}
				}
				atualizar('user', array_keys($user_infos), array_values($user_infos), $a_user['user_nb_id']);

			}
			$aEmpresa = carregar('empresa', $_POST['empresa']);
			if ($aEmpresa['empr_nb_parametro'] > 0) {
				$aParametro = carregar('parametro', $aEmpresa['empr_nb_parametro']);
				if (
					$aParametro['para_tx_jornadaSemanal'] != $a_mod['enti_tx_jornadaSemanal'] ||
					$aParametro['para_tx_jornadaSabado'] != $a_mod['enti_tx_jornadaSabado'] ||
					$aParametro['para_tx_percentualHE'] != $a_mod['enti_tx_percentualHE'] ||
					$aParametro['para_tx_percentualSabadoHE'] != $a_mod['enti_tx_percentualSabadoHE'] ||
					$aParametro['para_nb_id'] != $a_mod['enti_nb_parametro']
				) {
					$ehPadrao = 'Não';
				} else {
					$ehPadrao = 'Sim';
				}
			}
			$enti_campos = array_merge($enti_campos, ['enti_nb_userAtualiza', 'enti_tx_dataAtualiza', 'enti_tx_ehPadrao']);
			$enti_valores = array_merge($enti_valores, [$_SESSION['user_nb_id'], date("Y-m-d H:i:s"), $ehPadrao]);
			atualizar('entidade', $enti_campos, $enti_valores, $_POST['id']);
			$id = $_POST['id'];
		}

		$file_type = $_FILES['cnhAnexo']['type']; //returns the mimetype

		$allowed = array("image/jpeg", "image/gif", "image/png", "application/pdf");
		if (in_array($file_type, $allowed) && $_FILES['cnhAnexo']['name'] != '') {

			if (!is_dir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]")) {
				mkdir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]", 0777, true);
			}

			$arq = enviar('cnhAnexo', "arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]/", 'CNH_' . $id . '_' . $_POST['postMatricula']);
			if ($arq) {
				atualizar('entidade', array('enti_tx_cnhAnexo'), array($arq), $id);
			}
		}
		
		$idUserFoto = query("SELECT user_nb_id FROM `user` WHERE user_nb_entidade = $id")->fetch_assoc();
		$file_type = $_FILES['foto']['type']; //returns the mimetype

		$allowed = array("image/jpeg", "image/gif", "image/png");
		if (in_array($file_type, $allowed) && $_FILES['foto']['name'] != '') {

			if (!is_dir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]")) {
				mkdir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]", 0777, true);
			}

			$arq = enviar('foto', "arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]/", 'FOTO_' . $id . '_' . $_POST['postMatricula']);
			if ($arq) {
				atualizar('entidade', array('enti_tx_foto'), array($arq), $id);
				atualizar('user', array('user_tx_foto'), array($arq), $idUserFoto['user_nb_id']);
			}
		}

		$_POST['id'] = $id;
		index();
		exit;
	}

	function carrega_empresa() {
		$aEmpresa = carregar('empresa', (int)$_GET['emp']);
		if ($aEmpresa['empr_nb_parametro'] > 0) {
	?>
			<script type="text/javascript">
				parent.document.contex_form.parametro.value = '<?= $aEmpresa['empr_nb_parametro'] ?>';
				parent.document.contex_form.parametro.onchange();
			</script>
		<?
		}

		exit;
	}

	function carrega_parametro() {
		$aParam = carregar('parametro', (int)$_GET['parametro']);
		?>
		<script type="text/javascript">
			parent.document.contex_form.jornadaSemanal.value = '<?= $aParam['para_tx_jornadaSemanal'] ?>';
			parent.document.contex_form.jornadaSabado.value = '<?= $aParam['para_tx_jornadaSabado'] ?>';
			parent.document.contex_form.percentualHE.value = '<?= $aParam['para_tx_percentualHE'] ?>';
			parent.document.contex_form.percentualSabadoHE.value = '<?= $aParam['para_tx_percentualSabadoHE'] ?>';
		</script>
	<?

		exit;
	}


	function carrega_padrao() {
		$aEmpresa = carregar('empresa', (int)$_GET['idEmpresa']);
		$aParam = carregar('parametro', (int)$aEmpresa['empr_nb_parametro']);
		?>
		<script type="text/javascript">
			parent.document.contex_form.parametro.value = '<?= $aParam['para_nb_id'] ?>';
			parent.document.contex_form.jornadaSemanal.value = '<?= $aParam['para_tx_jornadaSemanal'] ?>';
			parent.document.contex_form.jornadaSabado.value = '<?= $aParam['para_tx_jornadaSabado'] ?>';
			parent.document.contex_form.percentualHE.value = '<?= $aParam['para_tx_percentualHE'] ?>';
			parent.document.contex_form.percentualSabadoHE.value = '<?= $aParam['para_tx_percentualSabadoHE'] ?>';
		</script>
		<?

		exit;
	}

	function carrega_matricula() {
		echo '<script>alert("carrega_matricula")</script>';

		$matricula = (int)$_GET['matricula'];
		$id = (int)$_GET['id'];

		$sql = query("SELECT * FROM entidade WHERE enti_tx_matricula = '$matricula' AND enti_nb_id != $id LIMIT 1");
		$a = carrega_array($sql);

		if ($a['enti_nb_id'] > 0) {
		?>
			<script type="text/javascript">
				if (confirm("Matrícula já cadastrada, deseja atualizar o registro?")) {
					parent.document.form_modifica.id.value = '<?= $a['enti_nb_id'] ?>';
					parent.document.form_modifica.submit();
				} else {
					parent.document.contex_form.matricula.value = '';
				}
			</script>
		<?
		}

		exit;
	}


	function busca_cep($cep) {
		$resultado = @file_get_contents('https://viacep.com.br/ws/' . urlencode($cep) . '/json/');
		$arr = json_decode($resultado, true);
		return $arr;
	}

	function carrega_endereco() {

		$arr = busca_cep($_GET['cep']);
		?>
		<script src="/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>
		<script type="text/javascript">
			parent.document.contex_form.endereco.value = '<?= $arr['logradouro'] ?>';
			parent.document.contex_form.bairro.value = '<?= $arr['bairro'] ?>';

			var selecionado = $('.cidade', parent.document);
			selecionado.empty();
			selecionado.append('<option value=<?= $arr['ibge'] ?>><?= "[$arr[uf]] " . $arr['localidade'] ?></option>');
			selecionado.val("<?= $arr['ibge'] ?>").trigger("change");
		</script>
		<?

		exit;
	}

	function layout_motorista() {
		global $a_mod;
		
		if(empty($a_mod)){
			$a_mod = carregar('entidade', $_POST['id']);
			
			$campos = ['matricula', 'nome','nascimento','cpf','rg','civil','sexo','endereco','numero','complemento',
				'bairro','cidade','cep','fone1','fone2','email','ocupacao','salario','obs',
				'tipo','status','matricula','empresa',
				'parametro','jornadaSemanal','jornadaSabado','percentualHE','percentualSabadoHE',
				'rgOrgao', 'rgDataEmissao', 'rgUf',
				'pai', 'mae', 'conjugue', 'tipoOperacao',
				'subcontratado', 'admissao', 'desligamento',
				'cnhRegistro', 'cnhValidade', 'cnhPrimeiraHabilitacao', 'cnhCategoria', 'cnhPermissao',
				'cnhObs', 'cnhCidade', 'cnhEmissao', 'cnhPontuacao', 'cnhAtividadeRemunerada'
			];
			foreach($campos as $campo){
				if(isset($_POST[$campo]) && !empty($_POST[$campo])){
					if(in_array($campo, ['cidade', 'empresa', 'parametro', 'cnhCidade'])){
						$a_mod['enti_nb_'.$campo] = $_POST[$campo];
					}else{
						$a_mod['enti_tx_'.$campo] = $_POST[$campo];
					}
				}
			}
		}
		if(!empty($a_mod['enti_nb_id'])){
			$login = mysqli_fetch_all(
				query(
					"SELECT user_tx_login FROM user 
						WHERE ".$a_mod['enti_nb_id']." = user_nb_entidade 
						LIMIT 1"
				),
				MYSQLI_ASSOC
			)[0];
			$a_mod['user_tx_login'] = $login['user_tx_login'];
		}
		
		cabecalho("Cadastro de Motorista");

		$data1 = new DateTime($a_mod['enti_tx_nascimento']);
		$data2 = new DateTime(date("Y-m-d"));

		$intervalo = $data1->diff($data2);

		$idade = "{$intervalo->y} anos, {$intervalo->m} meses e {$intervalo->d} dias";
		

		$uf = ['', 'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
		
		if($a_mod['enti_tx_foto']!=''){
			$img =texto(icone_excluir_foto($a_mod['enti_nb_id'], 'excluir_foto'), '<img style="width: 100%;" src="'.$a_mod['enti_tx_foto'].'" />', 2);
		}

		$c = [
			$img,
			campo('Nome*', 'nome', $a_mod['enti_tx_nome'], 3,'','maxlength="65" tabindex=02'),
			campo_data('Dt. Nascimento*', 'nascimento', $a_mod['enti_tx_nascimento'], 2, 'tabindex=03'),
			combo('status', 'status', $a_mod['enti_tx_status'], 2, array('ativo', 'inativo'), 'tabindex=04'),
			campo('Login','login', $a_mod['user_tx_login'],2, '', 'tabindex=05'),
			texto('Idade',$idade,2, 'tabindex=06'),

			campo('CPF*', 'cpf', $a_mod['enti_tx_cpf'], 2, 'MASCARA_CPF', 'tabindex=07'),
			campo('RG*', 'rg', $a_mod['enti_tx_rg'], 2, 'MASCARA_RG', 'tabindex=08, maxlength=11'),
			campo('Emissor RG', 'rgOrgao', $a_mod['enti_tx_rgOrgao'], 2,'','maxlength="6" tabindex=09'),
			campo_data('Data Emissão RG', 'rgDataEmissao', $a_mod['enti_tx_rgDataEmissao'], 2, 'tabindex=10'),
			combo('UF RG', 'rgUf', $a_mod['enti_tx_rgUf'], 2, $uf, 'tabindex=11'),
			combo('Estado Civil', 'civil', $a_mod['enti_tx_civil'], 2, ['', 'Casado(a)', 'Solteiro(a)', 'Divorciado(a)', 'Viúvo(a)'], 'tabindex=12'),

			combo('Sexo', 'sexo', $a_mod['enti_tx_sexo'], 2, ['', 'Feminino', 'Masculino'], 'tabindex=13'),
			campo('CEP*', 'cep', $a_mod['enti_tx_cep'], 2, 'MASCARA_CEP', 'onkeyup="carrega_cep(this.value);" tabindex=14'),
			campo('Endereço*', 'endereco', $a_mod['enti_tx_endereco'], 4, '', 'tabindex=15'),
			campo('Número', 'numero', $a_mod['enti_tx_numero'], 2, 'MASCARA_NUMERO', 'tabindex=16'),
			campo('Bairro*', 'bairro', $a_mod['enti_tx_bairro'], 2, '', 'tabindex=17'),

			campo('Complemento', 'complemento', $a_mod['enti_tx_complemento'], 2, '', 'tabindex=18'),
			campo('Ponto de Referência', 'referencia', $a_mod['enti_tx_referencia'], 3, '', 'tabindex=19'),
			combo_net('Cidade/UF*', 'cidade', $a_mod['enti_nb_cidade'], 3, 'cidade', 'tabindex=20', '', 'cida_tx_uf'),
			campo('Telefone 1*', 'fone1', $a_mod['enti_tx_fone1'], 2, 'MASCARA_CEL', 'tabindex=21'),
			campo('Telefone 2', 'fone2', $a_mod['enti_tx_fone2'], 2, 'MASCARA_CEL', 'tabindex=22'),
			campo('E-mail*', 'email', $a_mod['enti_tx_email'], 3, '', 'tabindex=23'),

			campo('Filiação Pai', 'pai', $a_mod['enti_tx_pai'], 3,'', 'maxlength="65" tabindex=24'),
			campo('Filiação Mãe', 'mae', $a_mod['enti_tx_mae'], 3,'', 'maxlength="65" tabindex=25'),
			campo('Nome do Cônjugue', 'conjugue', $a_mod['enti_tx_conjugue'], 3,'', 'maxlength="65" tabindex=26'),
			campo('Tipo de Operação', 'tipoOperacao', $a_mod['enti_tx_tipoOperacao'], 3,'', 'maxlength="40" tabindex=27'),
			arquivo('Foto (.png, .jpg)', 'foto', $a_mod['enti_tx_foto'], 4, 'tabindex=28'),
			ckeditor('Observações:', 'obs', $a_mod['enti_tx_obs'], 12, 'tabindex=29')
		];

		if(!empty($a_mod['enti_tx_matricula'])){
			array_splice($c, 1, 0, texto('Matrícula*', $a_mod['enti_tx_matricula'], 1, 'tabindex=01'));
		}else{
			array_splice($c, 1, 0, campo('Matrícula*', 'postMatricula', '', 1, '', 'tabindex=01'));
		}

		if ($_SESSION['user_nb_empresa'] > 0 && is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$extraEmpresa = " AND empr_nb_id = '$_SESSION[user_nb_empresa]'";
		}
		if (is_int(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$campoSalario = campo('Salário', 'salario', valor($a_mod['enti_tx_salario']), 1, 'MASCARA_VALOR', 'tabindex=32');
		}

		if (is_int(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$campoSalario = campo('Salário', 'salario', valor($a_mod['enti_tx_salario']), 1, 'MASCARA_VALOR', 'tabindex=32');
		}

		$cContratual = [
			combo_bd('Empresa*', 'empresa', $a_mod['enti_nb_empresa'], 3, 'empresa', 'onchange="carrega_empresa(this.value)" tabindex=30', $extraEmpresa),
			combo('Ocupação*', 'ocupacao', $a_mod['enti_tx_ocupacao'], 2, ["Motorista"], 'tabindex=31'), //TODO PRECISO SABER OS TIPOS DE MOTORISTA
			$campoSalario,
			combo('Subcontratado', 'subcontratado', $a_mod['enti_tx_subcontratado'], 2, ['', 'Sim', 'Não'], 'tabindex=33'),
			campo_data('Dt Admissão*', 'admissao', $a_mod['enti_tx_admissao'], 2, 'tabindex=34'),
			campo_data('Dt Desligamento', 'desligamento', $a_mod['enti_tx_desligamento'], 2, 'tabindex=35'),
			campo('Saldo de Horas', 'setBanco', $a_mod['enti_tx_banco']?? '00:00', 3, 'MASCARA_HORAS', 'placeholder="HH:mm" tabindex=36')
		];

		if ($a_mod['enti_nb_empresa']) {
			$icone_padronizar = icone_padronizar();
		}

		$cJornada = [
			combo_bd('Parâmetros da Jornada*' . $icone_padronizar, 'parametro', $a_mod['enti_nb_parametro'], 6, 'parametro', 'onchange="carrega_parametro()" tabindex=37'),
			campo_hora('Jornada Semanal (Horas/Dia)*', 'jornadaSemanal', $a_mod['enti_tx_jornadaSemanal'], 3, 'tabindex=38'),
			campo_hora('Jornada Sábado (Horas/Dia)*', 'jornadaSabado', $a_mod['enti_tx_jornadaSabado'], 3, 'tabindex=39'),
			campo('Percentual da HE(%)*', 'percentualHE', $a_mod['enti_tx_percentualHE'], 3, 'MASCARA_NUMERO', 'tabindex=40'),
			campo('Percentual da HE Sábado(%)*', 'percentualSabadoHE', $a_mod['enti_tx_percentualSabadoHE'], 3, 'MASCARA_NUMERO', 'tabindex=41')
		];
		$ehPadrao = '';
		if($a_mod['enti_nb_parametro'] > 0){
			$aEmpresa = carregar('empresa', (int)$a_mod['enti_nb_empresa']);
			$aParam = carregar('parametro', (int)$aEmpresa['empr_nb_parametro']);
			$aParametro = carregar('parametro', $a_mod['enti_nb_parametro']);
			
			if($aParam['para_nb_id'] != $aParametro ['para_nb_id']){

				$ehPadrao = 'Não';
			}else{
				$ehPadrao = 'Sim';
			}
			
			$cJornada[]=texto('Convenção Padrão?', $ehPadrao, 2);
		}

		if ($a_mod['enti_tx_cnhAnexo'])
			$iconeExcluirCnh = icone_excluirCnh($a_mod['enti_nb_id'], 'excluir_cnh');

		// exit;
		$cCNH = [
			campo('N° Registro*', 'cnhRegistro', $a_mod['enti_tx_cnhRegistro'], 3,'','maxlength="11" tabindex=42'),
			campo_data('Validade*', 'cnhValidade', $a_mod['enti_tx_cnhValidade'], 3, 'tabindex=43'),
			campo_data('1º Habilitação*', 'cnhPrimeiraHabilitacao', $a_mod['enti_tx_cnhPrimeiraHabilitacao'], 3, 'tabindex=44'),
			campo('Categoria*', 'cnhCategoria', $a_mod['enti_tx_cnhCategoria'], 3, '', 'tabindex=45'),
			campo('Permissão', 'cnhPermissao', $a_mod['enti_tx_cnhPermissao'], 3,'','maxlength="65" tabindex=46'),
			combo_net('Cidade/UF Emissão*', 'cnhCidade', $a_mod['enti_nb_cnhCidade'], 3, 'cidade', 'tabindex=47', '', 'cida_tx_uf'),
			campo_data('Data Emissão*', 'cnhEmissao', $a_mod['enti_tx_cnhEmissao'], 3, 'tabindex=48'),
			campo('Pontuação', 'cnhPontuacao', $a_mod['enti_tx_cnhPontuacao'], 3,'','maxlength="3" tabindex=49'),
			combo('Atividade Remunerada', 'cnhAtividadeRemunerada', $a_mod['enti_tx_cnhAtividadeRemunerada'], 3, ['', 'Sim', 'Não'], 'tabindex=50'),
			arquivo('CNH (.png, .jpg, .pdf)' . $iconeExcluirCnh, 'cnhAnexo', $a_mod['enti_tx_cnhAnexo'], 4, 'tabindex=51'),
			campo('Observações', 'cnhObs', $a_mod['enti_tx_cnhObs'], 3,'','maxlength="500" tabindex=52')
		];


		$b[] = botao('Gravar', 'cadastra_motorista', 'id, matricula', $_POST['id'].','.$a_mod['enti_tx_matricula'], 'tabindex=53');

		$b[] = botao('Voltar', 'index', '', '', 'tabindex=54');

		abre_form('Dados Cadastrais');
		linha_form($c);
		echo "<br>";
		fieldset('Dados Contratuais');
		linha_form($cContratual);
		echo "<br>";
		fieldset('CONVENÇÃO SINDICAL - JORNADA PADRÃO DO MOTORISTA');
		linha_form($cJornada);
		echo "<br>";
		fieldset('CARTEIRA NACIONAL DE HABILITAÇÃO');
		linha_form($cCNH);

		if ($a_mod['enti_nb_userCadastro'] > 0) {
			$a_userCadastro = carregar('user', $a_mod['enti_nb_userCadastro']);
			$txtCadastro = "Registro inserido por $a_userCadastro[user_tx_login] às " . data($a_mod['enti_tx_dataCadastro']) . ".";
			$cAtualiza[] = texto("Data de Cadastro", "$txtCadastro", 5);
			if ($a_mod['enti_nb_userAtualiza'] > 0) {
				$a_userAtualiza = carregar('user', $a_mod['enti_nb_userAtualiza']);
				$txtAtualiza = "Registro atualizado por $a_userAtualiza[user_tx_login] às " . data($a_mod['enti_tx_dataAtualiza'], 1) . ".";
				$cAtualiza[] = texto("Última Atualização", "$txtAtualiza", 5);
			}
			echo "<br>";
			linha_form($cAtualiza);
		}

		$path_parts = pathinfo(__FILE__);
		?>
		<iframe id=frame_parametro style="display: none;"></iframe>
		<script>
			function carrega_cep(cep) {
				var num = cep.replace(/[^0-9]/g, '');
				if (num.length == '8') {
					document.getElementById('frame_parametro').src = 'cadastro_motorista.php?acao=carrega_endereco&cep=' + num;
				}
			}

			function carrega_empresa(id) {
				document.getElementById('frame_parametro').src = 'cadastro_motorista.php?acao=carrega_empresa&emp=' + id;
			}

			function carrega_parametro() {
				id = document.getElementById('parametro').value
				document.getElementById('frame_parametro').src = 'cadastro_motorista.php?acao=carrega_parametro&parametro=' + id;
			}

			function carrega_padrao() {
				document.getElementById('frame_parametro').src = 'cadastro_motorista.php?acao=carrega_padrao&idEmpresa=<?= $a_mod['enti_nb_empresa'] ?>';
			}

			// //setup before functions
			// let typingTimer; //timer identifier
			// let doneTypingInterval = 1000; //time in ms (1 seconds)
			// let myInput = document.getElementById('matricula');

			// //on keyup, start the countdown
			// myInput.addEventListener('keyup', () => {
			// 	clearTimeout(typingTimer);
			// 	if (myInput.value) {
			// 		typingTimer = setTimeout(doneTyping, doneTypingInterval);
			// 	}
			// });

			// //user is "finished typing," do something
			// function doneTyping() {
			// 	let matricula = myInput.value;
			// 	document.getElementById('frame_parametro').src = 'cadastro_motorista.php?acao=carrega_matricula&matricula=' + matricula + '&id=<?/*$a_mod['enti_nb_id'] */?>';
			// }
		</script>
		<?php


		fecha_form($b);

		rodape();

		?>

		<form method="post" name="form_modifica" id="form_modifica">
			<input type="hidden" name="id" value="">
			<input type="hidden" name="acao" value="modifica_motorista">
		</form>

		<form name="form_excluir_arquivo" method="post" action="cadastro_motorista.php">
			<input type="hidden" name="idEntidade" value="">
			<input type="hidden" name="nome_arquivo" value="">
			<input type="hidden" name="acao" value="">
		</form>

		<script type="text/javascript">
			function remover_foto(id, acao, arquivo) {
				if (confirm('Deseja realmente excluir o arquivo ' + arquivo + '?')) {
					document.form_excluir_arquivo.idEntidade.value = id;
					document.form_excluir_arquivo.nome_arquivo.value = arquivo;
					document.form_excluir_arquivo.acao.value = acao;
					document.form_excluir_arquivo.submit();
				}
			}

			function remover_cnh(id, acao, arquivo) {
				if (confirm('Deseja realmente excluir o arquivo CNH ' + arquivo + '?')) {
					document.form_excluir_arquivo.idEntidade.value = id;
					document.form_excluir_arquivo.nome_arquivo.value = arquivo;
					document.form_excluir_arquivo.acao.value = acao;
					document.form_excluir_arquivo.submit();
				}
			}
		</script>


		<?

	}

	function icone_padronizar() {

		return "<a style='text-shadow: none; color: #337ab7;' onclick='javascript:carrega_padrao();' > (Padronizar) </a>";
	}

	function icone_excluirCnh($id, $acao, $campos = '', $valores = '', $target = '', $icone = 'glyphicon glyphicon-remove', $msg = 'Deseja excluir a CNH?') {

		$icone = 'class="' . $icone . '"';
		return "<a style='text-shadow: none; color: #337ab7;' onclick='javascript:remover_cnh(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\");' > (Excluir) </a>";
	}

	function icone_excluir_foto($id, $acao, $campos = '', $valores = '', $target = '', $icone = 'glyphicon glyphicon-remove', $msg = 'Deseja excluir o registro?') {
		$icone = 'class="' . $icone . '"';

		return "<a style='color:gray' onclick='javascript:remover_foto(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\");' ><spam $icone></spam>Excluir</a>";
	}

	function index() {
		cabecalho("Cadastro de Motorista");

		$extraEmpresa = '';
		if ($_SESSION['user_nb_empresa'] > 0 && is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$extraEmpresa = " AND empr_nb_id = '".$_SESSION['user_nb_empresa']."'";
		}

		$extra =
			((!empty($_POST['busca_codigo']))? 		" AND enti_nb_id LIKE '%".$_POST['busca_codigo']."%'": '').
			((!empty($_POST['busca_matricula']))? 	" AND enti_tx_matricula LIKE '%".$_POST['busca_matricula']."%'": '').
			((!empty($_POST['busca_empresa']))? 	" AND enti_nb_empresa = '".$_POST['busca_empresa']."'": '').
			((!empty($_POST['busca_nome']))? 		" AND enti_tx_nome LIKE '%".$_POST['busca_nome']."%'": '').
			((!empty($_POST['busca_cpf']))? 		" AND enti_tx_cpf = '".$_POST['busca_cpf']."'": '').
			((!empty($_POST['busca_ocupacao']))? 	" AND enti_tx_ocupacao = '".$_POST['busca_ocupacao']."'": '').
			((!empty($_POST['busca_parametro']))? 	" AND enti_nb_parametro = '".$_POST['busca_parametro']."'": '');

		if ($_POST['busca_status'] && $_POST['busca_status'] != 'Todos'){
			$extra .= " AND enti_tx_status = '".strtolower($_POST['busca_status'])."'";
		}
		
		if(!empty($_POST['busca_padrao']) && $_POST['busca_padrao'] != "Todos"){
			$extra .= " AND enti_tx_ehPadrao = '".$_POST['busca_padrao']."'";
		}

		$c[] = campo('Código', 'busca_codigo', $_POST['busca_codigo'], 1,'','maxlength="6"');
		$c[] = campo('Nome', 'busca_nome', $_POST['busca_nome'], 2,'','maxlength="65"');
		$c[] = campo('Matrícula', 'busca_matricula', $_POST['busca_matricula'], 1,'','maxlength="6"');
		$c[] = campo('CPF', 'busca_cpf', $_POST['busca_cpf'], 2, 'MASCARA_CPF');
		$c[] = combo_bd('!Empresa', 'busca_empresa', $_POST['busca_empresa'], 2, 'empresa', '', $extraEmpresa);
		$c[] = combo('Ocupação', 'busca_ocupacao', $_POST['busca_ocupacao'], 2, array("", "Motorista")); //TODO PRECISO SABER QUAIS AS OCUPACOES
		$c[] = combo('Convenção Padrão', 'busca_padrao', $_POST['busca_padrao'], 2, array('Todos', 'Sim', 'Não'));
		$c[] = combo_bd('!Parâmetros da Jornada', 'busca_parametro', $_POST['busca_parametro'], 6, 'parametro');
		$c[] = combo('Status', 'busca_status', $_POST['busca_status'], 2, ['Todos', 'Ativo', 'Inativo']);

		$b[] = botao('Buscar', 'index');
		$b[] = botao('Inserir', 'layout_motorista','','','','','btn btn-success');

		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($b);

		$sql = (
			"SELECT * FROM entidade 
				JOIN empresa ON enti_nb_empresa = empr_nb_id 
				JOIN parametro ON enti_nb_parametro = para_nb_id
				WHERE enti_tx_tipo = 'Motorista' 
					$extraEmpresa 
					$extra"
		);

		if (is_int(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$icone_excluir = 'icone_excluir(enti_nb_id,exclui_motorista)';
		}else
			$icone_excluir = '';

		$cab = ['CÓDIGO', 'NOME', 'MATRÍCULA', 'CPF', 'EMPRESA', 'FONE 1', 'FONE 2', 'OCUPAÇÃO', 'PARÂMETRO DA JORNADA', 'CONVENÇÃO PADRÃO', 'STATUS', '', ''];
		$val = [
			'enti_nb_id', 'enti_tx_nome', 'enti_tx_matricula', 'enti_tx_cpf', 'empr_tx_nome', 'enti_tx_fone1', 'enti_tx_fone2', 'enti_tx_ocupacao', 'para_tx_nome', 'enti_tx_ehPadrao', 'enti_tx_status', 'icone_modificar(enti_nb_id,modifica_motorista)',
			$icone_excluir
		];

		grid($sql, $cab, $val);

		rodape();
	}
?>