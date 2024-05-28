<?php
    /* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/
	include "conecta.php";

	function carregarEmpresa(){
		$aEmpresa = carregar('empresa', (int)$_GET['emp']);
		if ($aEmpresa['empr_nb_parametro'] > 0) {
			?>
				<script type="text/javascript">
					parent.document.contex_form.parametro.value = '<?= $aEmpresa['empr_nb_parametro'] ?>';
					parent.document.contex_form.parametro.onchange();
				</script>
			<?php
		}
		exit;
	}

	function carregarParametroPadrao(int $idEmpresa = null){
		global $a_mod;
		if(!empty($idEmpresa) && !empty($a_mod['enti_nb_empresa'])){
			$idEmpresa = intval($a_mod['enti_nb_empresa']);
		}else{
			$idEmpresa = -1;
		}

		$a_mod['parametroPadrao'] = mysqli_fetch_assoc(
			query(
				'SELECT parametro.* FROM empresa
					JOIN parametro ON empresa.empr_nb_parametro = parametro.para_nb_id
					WHERE para_tx_status = "ativo"
						AND empresa.empr_nb_id = '.$idEmpresa.'
					LIMIT 1;'
			)
		);
	}

	function carregarParametro(){
		if(empty($_GET['parametro'])){
			exit;
		}
		
		$parametro = carregar('parametro', (int)$_GET['parametro']);
		
		if(empty($parametro)){
			exit;
		}
		?>
			<script type="text/javascript">
				parent.document.contex_form.jornadaSemanal.value = '<?= $parametro['para_tx_jornadaSemanal'] ?>';
				parent.document.contex_form.jornadaSabado.value = '<?= $parametro['para_tx_jornadaSabado'] ?>';
				parent.document.contex_form.percentualHE.value = '<?= $parametro['para_tx_percentualHE'] ?>';
				parent.document.contex_form.percentualSabadoHE.value = '<?= $parametro['para_tx_percentualSabadoHE'] ?>';
			</script>
		<?php
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
      	"<script src='".$_ENV["URL_BASE"].$_ENV["APP_PATH"]."/contex20/assets/global/plugins/jquery.min.js' type='text/javascript'></script>
			<script type='text/javascript'>
				parent.document.contex_form.endereco.value = '".$arr['logradouro']."';
				parent.document.contex_form.bairro.value = '".$arr['bairro']."';

				var selecionado = $('.cidade', parent.document);
				selecionado.empty();
				selecionado.append('<option value=\'".$arr['ibge']."\'>[".$arr['uf']."] ".$arr['localidade']."</option>');
				selecionado.val('".$arr['ibge']."').trigger('change');
			</script>"
    	;
		exit;
	}

	function cadastrarMotorista(){
		visualizarCadastro();
		exit;
		global $a_mod;
		

		if(!empty($_POST['matricula'])){
			$_POST['postMatricula'] = $_POST['matricula'];
		}


		$enti_campos = [
			'enti_tx_matricula' 				=> 'postMatricula', 
			'enti_tx_nome' 						=> 'nome', 
			'enti_tx_nascimento' 				=> 'nascimento', 
			'enti_tx_status' 					=> 'status', 
			'enti_tx_status' 					=> 'status', 
			'enti_tx_cpf' 						=> 'cpf',
			'enti_tx_rg' 						=> 'rg',
			'enti_tx_civil' 					=> 'civil',
			'enti_tx_sexo' 						=> 'sexo',
			'enti_tx_endereco' 					=> 'endereco',
			'enti_tx_numero' 					=> 'numero',
			'enti_tx_complemento' 				=> 'complemento',
			'enti_tx_bairro' 					=> 'bairro',
			'enti_nb_cidade' 					=> 'cidade',
			'enti_tx_cep' 						=> 'cep',
			'enti_tx_fone1' 					=> 'fone1',
			'enti_tx_fone2' 					=> 'fone2',
			'enti_tx_email' 					=> 'email',
			'enti_tx_ocupacao' 					=> 'ocupacao',
			'enti_tx_salario' 					=> 'salario',
			'enti_nb_parametro' 				=> 'parametro', 
			'enti_tx_obs' 						=> 'obs', 
			'enti_nb_empresa' 					=> 'empresa',
			'enti_tx_jornadaSemanal' 			=> 'jornadaSemanal',
			'enti_tx_jornadaSabado' 			=> 'jornadaSabado',
			'enti_tx_percentualHE' 				=> 'percentualHE',
			'enti_tx_percentualSabadoHE' 		=> 'percentualSabadoHE',
			'enti_tx_rgOrgao' 					=> 'rgOrgao', 
			'enti_tx_rgDataEmissao' 			=> 'rgDataEmissao', 
			'enti_tx_rgUf' 						=> 'rgUf',
			'enti_tx_pai' 						=> 'pai', 
			'enti_tx_mae' 						=> 'mae', 
			'enti_tx_conjugue' 					=> 'conjugue', 
			'enti_tx_tipoOperacao' 				=> 'tipoOperacao',
			'enti_tx_subcontratado' 			=> 'subcontratado', 
			'enti_tx_admissao' 					=> 'admissao', 
			'enti_tx_desligamento' 				=> 'desligamento',
			'enti_tx_cnhRegistro' 				=> 'cnhRegistro', 
			'enti_tx_cnhValidade' 				=> 'cnhValidade', 
			'enti_tx_cnhPrimeiraHabilitacao' 	=> 'cnhPrimeiraHabilitacao', 
			'enti_tx_cnhCategoria' 				=> 'cnhCategoria', 
			'enti_tx_cnhPermissao' 				=> 'cnhPermissao',
			'enti_tx_cnhObs' 					=> 'cnhObs', 
			'enti_nb_cnhCidade'			 		=> 'cnhCidade', 
			'enti_tx_cnhEmissao' 				=> 'cnhEmissao', 
			'enti_tx_cnhPontuacao' 				=> 'cnhPontuacao', 
			'enti_tx_cnhAtividadeRemunerada' 	=> 'cnhAtividadeRemunerada',
			'enti_tx_banco' 					=> 'setBanco'
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



		//Conferir campos obrigatórios{
			$campos_obrigatorios = [
				'nome' => 'Nome', 'nascimento' => 'Dt. Nascimento',
				'cpf' => 'CPF', 'rg' => 'RG', 'bairro' => 'Bairro',
				'cep' => 'CEP', 'endereco' => 'Endereço', 'cidade' => 'Cidade/UF', 'fone1' => 'Telefone 1',
				'email' => 'E-mail',
				'empresa' => 'Empresa', 'ocupacao' => 'Ocupação', 'admissao' => 'Dt Admissão',
				'parametro' => 'Parâmetro', 'jornadaSemanal' => 'Jornada Semanal', 'jornadaSabado' => 'Jornada Sábado', 
				'percentualHE' => 'Percentual da HE', 'percentualSabadoHE' => 'Percentual da HE Sábado', 
				'cnhRegistro' => 'N° Registro da CNH', 'cnhValidade' => 'Validade do CNH', 'cnhCategoria' => 'Categoria do CNH', 
				'cnhCidade' => 'Cidade do CNH', 'cnhEmissao' => 'Data de Emissão do CNH'
			];

			if(empty($a_mod['enti_tx_matricula'])){
				$campos_obrigatorios['postMatricula'] = 'Matrícula';
			}

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
				visualizarCadastro();
				exit;
			}

			unset($campos_obrigatorios);
		//}

		$matriculaExistente = mysqli_fetch_assoc(
			query(
				"SELECT * FROM entidade WHERE enti_tx_matricula = '".($_POST['postMatricula']?? "-1")."' LIMIT 1;"
			)
		);
		$matriculaExistente = !empty($matriculaExistente);

		if($matriculaExistente && !isset($_POST['id'])){
			echo '<script>alert("Matrícula já cadastrada.")</script>';
			visualizarCadastro();
			exit;
		}
		if(!empty($_POST['login'])){
			$otherUser = mysqli_fetch_assoc(query("SELECT * FROM user WHERE user_tx_matricula = '".($_POST['login']?? $_POST['postMatricula'])."' LIMIT 1"));
			if (!empty($otherUser) && strval($otherUser['user_tx_matricula']) != strval($_POST['postMatricula']) && $otherUser['user_tx_login'] == $_POST['login']){
				set_status("ERRO: Login já cadastrado.");
				$a_mod = $_POST;
				modificarMotorista();
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

		$enti_valores = [];
		for($f = 0; $f < sizeof($post_values); $f++){
			$enti_valores[] = !empty($_POST[$post_values[$f]])? $_POST[$post_values[$f]]: '';
		}

		$cpfLimpo = str_replace(array('.', '-', '/'), "", $_POST['cpf']);
		

		if (empty($_POST['id'])) {//Se está criando um motorista novo
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
					$ehPadrao = 'nao';
				} else {
					$ehPadrao = 'sim';
				}
			}
			$novoMotorista['enti_nb_userCadastro'] = $_SESSION['user_nb_id'];
			$novoMotorista['enti_tx_dataCadastro'] = date("Y-m-d H:i:s");
			$novoMotorista['enti_tx_ehPadrao'] = $ehPadrao;
			$id = inserir('entidade', array_keys($novoMotorista), array_values($novoMotorista))[0];
			
			
			$user_infos = [
				'user_tx_matricula' 	=> $_POST['postMatricula'], 
				'user_tx_nome' 			=> $_POST['nome'], 
				'user_tx_nivel' 		=> $_POST['nivel'], 
				'user_tx_login' 		=> (!empty($_POST['login'])? $_POST['login']: $_POST['postMatricula']), 
				'user_tx_senha' 		=> md5($cpfLimpo), 
				'user_tx_status' 		=> $_POST['status'], 
				'user_nb_entidade' 		=> $id,
				'user_tx_nascimento' 	=> $_POST['nascimento'], 
				'user_tx_cpf' 			=> $_POST['cpf'], 
				'user_tx_rg' 			=> $_POST['rg'], 
				'user_nb_cidade' 		=> $_POST['cidade'], 
				'user_tx_email' 		=> $_POST['email'], 
				'user_tx_fone' 			=> $_POST['fone1'], 
				'user_nb_empresa' 		=> $_POST['empresa'],
				'user_nb_userCadastro' 	=> $_SESSION['user_nb_id'], 
				'user_tx_dataCadastro' 	=> date("Y-m-d H:i:s")
			];
			foreach($user_infos as $key => $value){
				if(empty($value)){
					unset($user_infos[$key]);
				}
			}

			inserir('user', array_keys($user_infos), array_values($user_infos));
		}else{ // Se está editando um motorista existente

			$a_user = carrega_array(query(
				"SELECT * FROM user 
					WHERE user_nb_entidade = ".$_POST['id']."
						AND user_tx_nivel IN ('Motorista', 'Ajudante')"
			));

			$_POST['nivel'] = $_POST['ocupacao'];

			if($a_user['user_nb_id'] > 0){
				$user_infos = [
					'user_tx_nome' 			=> $_POST['nome'], 
					'user_tx_login' 		=> (!empty($_POST['login'])? $_POST['login']: $_POST['postMatricula']), 
					'user_tx_nivel' 		=> $_POST['nivel'],
					'user_tx_status' 		=> $_POST['status'], 
					'user_nb_entidade' 		=> $_POST['id'],
					'user_tx_nascimento' 	=> $_POST['nascimento'], 
					'user_tx_cpf' 			=> $_POST['cpf'], 
					'user_tx_rg' 			=> $_POST['rg'], 
					'user_nb_cidade' 		=> $_POST['cidade'], 
					'user_tx_email' 		=> $_POST['email'], 
					'user_tx_fone' 			=> $_POST['fone1'],
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
					$ehPadrao = 'nao';
				} else {
					$ehPadrao = 'sim';
				}
			}
			$novoMotorista['enti_nb_userAtualiza'] = $_SESSION['user_nb_id'];
			$novoMotorista['enti_tx_dataAtualiza'] = date("Y-m-d H:i:s");
			$novoMotorista['enti_tx_ehPadrao'] = $ehPadrao;
			atualizar('entidade', array_keys($novoMotorista), array_values($novoMotorista), $_POST['id']);
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
		
		
		$idUserFoto = mysqli_fetch_assoc(query("SELECT user_nb_id FROM `user` WHERE user_nb_entidade = '".$id."' LIMIT 1;"));
		$file_type = $_FILES['foto']['type']; //returns the mimetype

		$allowed = array("image/jpeg", "image/gif", "image/png");
		if (in_array($file_type, $allowed) && $_FILES['foto']['name'] != '') {

			if (!is_dir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]")) {
				mkdir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]", 0777, true);
			}

			$arq = enviar('foto', "arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]/", 'FOTO_'.$id.'_'.$_POST['postMatricula']);
			if($arq){
				atualizar('entidade', array('enti_tx_foto'), array($arq), $id);
				atualizar('user', array('user_tx_foto'), array($arq), $idUserFoto['user_nb_id']);
			}
		}

		$_POST['id'] = $id;
		index();
		exit;
	}

	function modificarMotorista(){
		global $a_mod;

		$a_mod = carregar('entidade', $_POST['id']);

		visualizarCadastro();
		exit;
	}

	function excluirMotorista(){
		remover('entidade', $_POST['id']);

		index();
		exit;
	}

	function excluirFoto(){
		atualizar('entidade', array('enti_tx_foto'), array(''), $_POST['idEntidade']);
		$_POST['id'] = $_POST['idEntidade'];
		modificarMotorista();
		exit;
	}

	function excluirCNH(){
		atualizar('entidade', array('enti_tx_cnhAnexo'), array(''), $_POST['idEntidade']);
		$_POST['id'] = $_POST['idEntidade'];
		modificarMotorista();
		exit;
	}

	function visualizarCadastro(){
		global $a_mod;
		
		if(!empty($a_mod['enti_nb_empresa'])){
			carregarParametroPadrao($a_mod['enti_nb_empresa']);
		}
		
		if(empty($a_mod) && !empty($_POST)){
			if(isset($_POST['id'])){
				$a_mod = carregar('entidade', $_POST['id']);
			}
			
			$campos = ['matricula', 'nome','nascimento','cpf','rg','civil','sexo','endereco','numero','complemento', 'bairro','cidade','cep','fone1','fone2','email','ocupacao','salario','obs',
				'tipo','status','empresa', 'parametro','jornadaSemanal','jornadaSabado','percentualHE','percentualSabadoHE', 'rgOrgao', 'rgDataEmissao', 'rgUf',
				'pai', 'mae', 'conjugue', 'tipoOperacao', 'subcontratado', 'admissao', 'desligamento', 'cnhRegistro', 'cnhValidade', 'cnhPrimeiraHabilitacao', 'cnhCategoria', 'cnhPermissao',
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
		// echo 
		// 	"<style>
		// 		form .row {
		// 			display: grid;
		// 			grid: auto / auto auto auto auto auto;
		// 			gap: 15px 10px;
		// 			margin: 0px;
		// 		}

		// 		.row:before{
		// 			content: none;
		// 		}
		// 	</style>"
		// ;

		if(!empty($a_mod['enti_tx_nascimento'])){
			$data1 = new DateTime($a_mod['enti_tx_nascimento']);
			$data2 = new DateTime(date("Y-m-d"));
	
			$intervalo = $data1->diff($data2);
	
			$idade = "{$intervalo->y} anos, {$intervalo->m} meses e {$intervalo->d} dias";
		}
		

		$UFs = ['', 'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
		
		if(!empty($a_mod['enti_tx_foto'])){
			$img = texto(
				"<a style='color:gray' onclick='javascript:remover_foto(\"".($a_mod['enti_nb_id']?? '')."\",\"excluirFoto\",\"\",\"\",\"\",\"\");' >
					<spam class='glyphicon glyphicon-remove'></spam>
					Excluir
				</a>", 
				'<img style="width: 100%;" src="'.($a_mod['enti_tx_foto']?? '').'" />', 
				2
			);
		}else{
			$img = texto(
				"", 
				'<img style="width: 100%;" src="../contex20/img/driver.png" />',
				2
			);
		}
		
		$tabIndex = 1;

		$camposImg = [
			$img,
			arquivo('Arquivo (.png, .jpg)', 'foto', ($a_mod['enti_tx_foto']?? ''), 4, 'tabindex='.sprintf("%02d", $tabIndex++))
		];

		$statusOpt = ['ativo' => 'Ativo', 'inativo' => 'Inativo'];
		$estadoCivilOpt = ['', 'Casado(a)', 'Solteiro(a)', 'Divorciado(a)', 'Viúvo(a)'];
		$sexoOpt = ['', 'Feminino', 'Masculino'];

		$camposUsuario = [
			campo(	  	'E-mail*', 				'email', 			($a_mod['enti_tx_email']?? ''),			2, '', 					'tabindex='.sprintf("%02d", $tabIndex++)),
			campo(	  	'Telefone 1*', 			'fone1', 			($a_mod['enti_tx_fone1']?? ''),			1, 'MASCARA_CEL', 		'tabindex='.sprintf("%02d", $tabIndex++)),
			campo(	  	'Telefone 2',  			'fone2', 			($a_mod['enti_tx_fone2']?? ''),			1, 'MASCARA_CEL', 		'tabindex='.sprintf("%02d", $tabIndex++)),
			campo(	  	'Login',				'login', 			($a_mod['user_tx_login']?? ''),			2, '', 					'tabindex='.sprintf("%02d", $tabIndex++)),
			combo(		'Status', 				'status', 			($a_mod['enti_tx_status']?? ''),		1, $statusOpt, 			'tabindex='.sprintf("%02d", $tabIndex++))
		];

		$camposPessoais = [
			((!empty($_POST['id']))?
				texto('Matrícula*', $a_mod['enti_tx_matricula'], 2, '" tabindex='.sprintf("%02d", $tabIndex++)):
				campo('Matrícula*', 'postMatricula', ($a_mod['enti_tx_matricula']?? ''), 2, '', 'tabindex='.sprintf("%02d", $tabIndex++))
			)
		];

		$camposPessoais = array_merge($camposPessoais, [
			campo(	  	'Nome*', 				'nome', 			($a_mod['enti_tx_nome']?? ''),			4, '',					'maxlength="65" tabindex='.sprintf("%02d", $tabIndex++)),
			campo_data(	'Dt. Nascimento*', 		'nascimento', 		($a_mod['enti_tx_nascimento']?? ''),	2, 						'tabindex='.sprintf("%02d", $tabIndex++)),
			campo(	  	'CPF*', 				'cpf', 				($a_mod['enti_tx_cpf']?? ''),			2, 'MASCARA_CPF', 		'tabindex='.sprintf("%02d", $tabIndex++)),
			campo(	  	'RG*', 					'rg', 				($a_mod['enti_tx_rg']?? ''),			2, 'MASCARA_RG', 		'tabindex='.+sprintf("%02d", $tabIndex++).', maxlength=11'),
			combo(		'Estado Civil', 		'civil', 			($a_mod['enti_tx_civil']?? ''),			2, $estadoCivilOpt, 	'tabindex='.sprintf("%02d", $tabIndex++)),
			combo(		'Sexo', 				'sexo', 			($a_mod['enti_tx_sexo']?? ''),			2, $sexoOpt, 			'tabindex='.sprintf("%02d", $tabIndex++)),
			campo(	  	'Emissor RG', 			'rgOrgao', 			($a_mod['enti_tx_rgOrgao']?? ''),		3, '',					'maxlength="6" tabindex='.sprintf("%02d", $tabIndex++)),
			campo_data(	'Data Emissão RG', 		'rgDataEmissao', 	($a_mod['enti_tx_rgDataEmissao']?? ''),	2, 						'tabindex='.sprintf("%02d", $tabIndex++)),
			combo(		'UF RG', 				'rgUf', 			($a_mod['enti_tx_rgUf']?? ''),			2, $UFs, 				'tabindex='.sprintf("%02d", $tabIndex++)),
			"<div class='col-sm-2 margin-bottom-5' style='width:100%; height:25px'></div>",

			campo(	  	'CEP*', 				'cep', 				($a_mod['enti_tx_cep']?? ''),			2, 'MASCARA_CEP', 		'onfocusout="buscarCEP(this.value);" tabindex='.sprintf("%02d", $tabIndex++)),
			combo_net(	'Cidade/UF*', 			'cidade', 			($a_mod['enti_nb_cidade']?? ''),		3, 'cidade', 			'tabindex='.sprintf("%02d", $tabIndex++), '', 'cida_tx_uf'),
			campo(	  	'Bairro*', 				'bairro', 			($a_mod['enti_tx_bairro']?? ''),		2, '', 					'tabindex='.sprintf("%02d", $tabIndex++)),
			campo(	  	'Endereço*', 			'endereco', 		($a_mod['enti_tx_endereco']?? ''),		3, '', 					'tabindex='.sprintf("%02d", $tabIndex++)),
			campo(	  	'Número', 				'numero', 			($a_mod['enti_tx_numero']?? ''),		1, 'MASCARA_NUMERO', 	'tabindex='.sprintf("%02d", $tabIndex++)),
			campo(	  	'Complemento', 			'complemento', 		($a_mod['enti_tx_complemento']?? ''),	2, '', 					'tabindex='.sprintf("%02d", $tabIndex++)),
			campo(	  	'Ponto de Referência', 	'referencia', 		($a_mod['enti_tx_referencia']?? ''),	3, '', 					'tabindex='.sprintf("%02d", $tabIndex++)),
			"<div class='col-sm-2 margin-bottom-5' style='width:100%; height:25px'></div>",

			campo(	  	'Filiação Pai', 		'pai', 				($a_mod['enti_tx_pai']?? ''),			3, '', 					'maxlength="65" tabindex='.sprintf("%02d", $tabIndex++)),
			campo(	  	'Filiação Mãe', 		'mae', 				($a_mod['enti_tx_mae']?? ''),			3, '', 					'maxlength="65" tabindex='.sprintf("%02d", $tabIndex++)),
			campo(	  	'Nome do Cônjuge',	 	'conjugue', 		($a_mod['enti_tx_conjugue']?? ''),		3, '', 					'maxlength="65" tabindex='.sprintf("%02d", $tabIndex++)),
			campo(	  	'Tipo de Operação', 	'tipoOperacao', 	($a_mod['enti_tx_tipoOperacao']?? ''),	3, '', 					'maxlength="40" tabindex='.sprintf("%02d", $tabIndex++)),

			ckeditor(	'Observações:', 'obs', ($a_mod['enti_tx_obs']?? ''), 12, 'tabindex='.sprintf("%02d", $tabIndex++))
		]);

		$extraEmpresa = '';
		if ($_SESSION['user_nb_empresa'] > 0 && is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$extraEmpresa = " AND empr_nb_id = '$_SESSION[user_nb_empresa]'";
		}
		$campoSalario = "";
		if (is_int(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$campoSalario = campo('Salário*', 'salario', valor(($a_mod['enti_tx_salario']?? '0')), 1, 'MASCARA_VALOR', 'tabindex='.sprintf("%02d", $tabIndex+2));
		}

		$cContratual = [
			combo_bd('Empresa*', 'empresa', ($a_mod['enti_nb_empresa']?? ''), 3, 'empresa', 'onchange="carregarEmpresa(this.value)" tabindex='.sprintf("%02d", $tabIndex++), $extraEmpresa),
			$campoSalario
		];
		$tabIndex++;
		$cContratual = array_merge($cContratual, [
			combo('Ocupação*', 'ocupacao', ($a_mod['enti_tx_ocupacao']?? ''), 2, ['Motorista', 'Ajudante'], 'tabindex='.sprintf("%02d", $tabIndex++)),
			campo_data('Dt Admissão*', 'admissao', ($a_mod['enti_tx_admissao']?? ''), 2, 'tabindex='.sprintf("%02d", $tabIndex++)),
			campo_data('Dt. Desligamento', 'desligamento', ($a_mod['enti_tx_desligamento']?? ''), 2, 'tabindex='.sprintf("%02d", $tabIndex++)),
			campo('Saldo de Horas', 'setBanco', ($a_mod['enti_tx_banco']?? '00:00'), 1, 'MASCARA_HORAS', 'placeholder="HH:mm" tabindex='.sprintf("%02d", $tabIndex++)),
			combo('Subcontratado', 'subcontratado', ($a_mod['enti_tx_subcontratado']?? ''), 2, ['' => '', 'sim' => 'Sim', 'nao' => 'Não'], 'tabindex='.sprintf("%02d", $tabIndex++)),
		]);

		if (!empty($a_mod['enti_nb_empresa'])){
			$icone_padronizar = "<a id='padronizarParametro' style='text-shadow: none; color: #337ab7;' onclick='javascript:padronizarParametro();' > (Padronizar) </a>";
		}

		if(!empty($a_mod['parametroPadrao'])){
			$conferirPadraoJS = 'conferirParametroPadrao("'.$a_mod['parametroPadrao']['para_nb_id'].'", "'.$a_mod['parametroPadrao']['para_tx_jornadaSemanal'].'", "'.$a_mod['parametroPadrao']['para_tx_jornadaSabado'].'", "'.$a_mod['parametroPadrao']['para_tx_percentualHE'].'", "'.$a_mod['parametroPadrao']['para_tx_percentualSabadoHE'].'")';
		}else{
			$conferirPadraoJS = '';
		}

		$cJornada = [
			combo_bd(	'Parâmetros da Jornada*'.($icone_padronizar?? ""), 'parametro', ($a_mod['enti_nb_parametro']?? ''), 6, 'parametro', 'onfocusout="carregarParametro()" onchange="carregarParametro()" tabindex='.sprintf("%02d", $tabIndex++)),
			"<div class='col-sm-2 margin-bottom-5' style='width:100%; height:25px'></div>",
			campo_hora(	'Jornada Semanal (Horas/Dia)*', 'jornadaSemanal', ($a_mod['enti_tx_jornadaSemanal']?? ''), 2, 'tabindex='.sprintf("%02d", $tabIndex++).' onchange=\''.$conferirPadraoJS.'\''),
			campo_hora(	'Jornada Sábado (Horas/Dia)*', 'jornadaSabado', ($a_mod['enti_tx_jornadaSabado']?? ''), 2, 'tabindex='.sprintf("%02d", $tabIndex++).' onchange=\''.$conferirPadraoJS.'\''),
			campo(		'Percentual da HE(%)*', 'percentualHE', ($a_mod['enti_tx_percentualHE']?? ''), 2, 'MASCARA_NUMERO', 'tabindex='.sprintf("%02d", $tabIndex++).' onchange=\''.$conferirPadraoJS.'\''),
			campo(		'Percentual da HE Sábado(%)*', 'percentualSabadoHE', ($a_mod['enti_tx_percentualSabadoHE']?? ''), 2, 'MASCARA_NUMERO', 'tabindex='.sprintf("%02d", $tabIndex++).' onchange=\''.$conferirPadraoJS.'\'')
		];
		if(!empty($a_mod['enti_nb_empresa'])){
			$aEmpresa = carregar('empresa', (int)$a_mod['enti_nb_empresa']);
			$aParametro = carregar('parametro', $aEmpresa['empr_nb_parametro']);

			$padronizado = (
				$a_mod['enti_tx_jornadaSemanal'] 		== $aParametro['para_tx_jornadaSemanal'] &&
				$a_mod['enti_tx_jornadaSabado'] 		== $aParametro['para_tx_jornadaSabado'] &&
				$a_mod['enti_tx_percentualHE'] 			== $aParametro['para_tx_percentualHE'] &&
				$a_mod['enti_tx_percentualSabadoHE'] 	== $aParametro['para_tx_percentualSabadoHE']
			);
			
			$cJornada[]=texto('Convenção Padrão?', ($padronizado? 'Sim': 'Não'), 2, 'name="textoParametroPadrao"');
		}

		$iconeExcluirCNH = '';
		if (!empty($a_mod['enti_tx_cnhAnexo'])){
			$iconeExcluirCNH = "<a style='text-shadow: none; color: #337ab7;' onclick='javascript:remover_cnh(\"".$a_mod['enti_nb_id']."\",\"excluirCNH\",\"\",\"\",\"\",\"Deseja excluir a CNH?\");' > (Excluir) </a>";
		}

		$cCNH = [
			campo('N° Registro*', 'cnhRegistro', ($a_mod['enti_tx_cnhRegistro']?? ''), 3,'','maxlength="11" tabindex='.sprintf("%02d", $tabIndex++)),
			campo('Categoria*', 'cnhCategoria', ($a_mod['enti_tx_cnhCategoria']?? ''), 3, '', 'tabindex='.sprintf("%02d", $tabIndex++)),
			combo_net('Cidade/UF Emissão*', 'cnhCidade', ($a_mod['enti_nb_cnhCidade']?? ''), 3, 'cidade', 'tabindex='.sprintf("%02d", $tabIndex++), '', 'cida_tx_uf'),
			campo_data('Data Emissão*', 'cnhEmissao', ($a_mod['enti_tx_cnhEmissao']?? ''), 3, 'tabindex='.sprintf("%02d", $tabIndex++)),
			campo_data('Validade*', 'cnhValidade', ($a_mod['enti_tx_cnhValidade']?? ''), 3, 'tabindex='.sprintf("%02d", $tabIndex++)),
			campo_data('1º Habilitação*', 'cnhPrimeiraHabilitacao', ($a_mod['enti_tx_cnhPrimeiraHabilitacao']?? ''), 3, 'tabindex='.sprintf("%02d", $tabIndex++)),
			campo('Permissão', 'cnhPermissao', ($a_mod['enti_tx_cnhPermissao']?? ''), 3,'','maxlength="65" tabindex='.sprintf("%02d", $tabIndex++)),
			campo('Pontuação', 'cnhPontuacao', ($a_mod['enti_tx_cnhPontuacao']?? ''), 3,'','maxlength="3" tabindex='.sprintf("%02d", $tabIndex++)),
			combo('Atividade Remunerada', 'cnhAtividadeRemunerada', ($a_mod['enti_tx_cnhAtividadeRemunerada']?? ''), 3, ['' => '', 'sim' => 'Sim', 'nao' => 'Não'], 'tabindex='.sprintf("%02d", $tabIndex++)),
			arquivo('CNH (.png, .jpg, .pdf)' . $iconeExcluirCNH, 'cnhAnexo', ($a_mod['enti_tx_cnhAnexo']?? ''), 4, 'tabindex='.sprintf("%02d", $tabIndex++)),
			campo('Observações', 'cnhObs', ($a_mod['enti_tx_cnhObs']?? ''), 3,'','maxlength="500" tabindex='.sprintf("%02d", $tabIndex++))
		];


		// $campos = [
		// 	'id' => $_POST['id'],
		// 	'matricula' => $a_mod['enti_tx_matricula']
		// ];
		$botoesCadastro[] = botao(
			'Gravar', 
			'cadastrarMotorista', 
			((empty($_POST['id']) || empty($a_mod['enti_tx_matricula']))? '': 'id,matricula'),
			((empty($_POST['id']) || empty($a_mod['enti_tx_matricula']))? '': $_POST['id'].','.$a_mod['enti_tx_matricula']),
			'tabindex=53',
			'',
			'btn btn-success'
		);

		$botoesCadastro[] = botao('Voltar', 'index', '', '', 'tabindex=54');

		abre_form();
		fieldset('Dados de Usuário');
		linha_form($camposUsuario);
		echo "<br>";
		fieldset('Dados Pessoais');
		linha_form($camposPessoais);
		echo "<br>";
		fieldset('Foto');
		echo "<div class='imageForm'>";
		linha_form($camposImg);
		echo "</div>";
		echo "<br>";
		fieldset('Dados Contratuais');
		linha_form($cContratual);
		echo "<br>";
		fieldset('CONVENÇÃO SINDICAL - JORNADA PADRÃO DO MOTORISTA');
		linha_form($cJornada);
		echo "<br>";
		fieldset('CARTEIRA NACIONAL DE HABILITAÇÃO');
		linha_form($cCNH);

		if (!empty($a_mod['enti_nb_userCadastro'])) {
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
			function buscarCEP(cep) {
				var num = cep.replace(/[^0-9]/g, '');
				if (num.length == '8') {
					document.getElementById('frame_parametro').src = '<?php echo $path_parts['basename'] ?>?acao=carregarEndereco&cep=' + num;
				}
			}

			function carregarEmpresa(id) {
				document.getElementById('frame_parametro').src = 'cadastro_motorista.php?acao=carregarEmpresa&emp=' + id;
				var empresaSelecionada = id;
			}

			function carregarParametro() {
				id = document.getElementById('parametro').value;
				document.getElementById('frame_parametro').src = 'cadastro_motorista.php?acao=carregarParametro&parametro=' + id;

				<?php
				if(!empty($a_mod['parametroPadrao'])){
					echo 
						"conferirParametroPadrao('"
							.$a_mod['parametroPadrao']['para_nb_id']."','"
							.$a_mod['parametroPadrao']['para_tx_jornadaSemanal']."','"
							.$a_mod['parametroPadrao']['para_tx_jornadaSabado']."','"
							.$a_mod['parametroPadrao']['para_tx_percentualHE']."','"
							.$a_mod['parametroPadrao']['para_tx_percentualSabadoHE']."'
						);"
					;
				}
				?>
			}

			function padronizarParametro() {
				parent.document.contex_form.parametro.value 			= '<?= ($a_mod['parametroPadrao']['para_nb_id']?? "	") ?>';
				parent.document.contex_form.jornadaSemanal.value 		= '<?= ($a_mod['parametroPadrao']['para_tx_jornadaSemanal']?? "	") ?>';
				parent.document.contex_form.jornadaSabado.value 		= '<?= ($a_mod['parametroPadrao']['para_tx_jornadaSabado']?? "	") ?>';
				parent.document.contex_form.percentualHE.value 			= '<?= ($a_mod['parametroPadrao']['para_tx_percentualHE']?? "	") ?>';
				parent.document.contex_form.percentualSabadoHE.value 	= '<?= ($a_mod['parametroPadrao']['para_tx_percentualSabadoHE']?? "	") ?>';

				conferirParametroPadrao(
					"<?= ($a_mod['parametroPadrao']['para_nb_id']?? "")?>",
					"<?= ($a_mod['parametroPadrao']['para_tx_jornadaSemanal']?? "")?>",
					"<?= ($a_mod['parametroPadrao']['para_tx_jornadaSabado']?? "")?>",
					"<?= ($a_mod['parametroPadrao']['para_tx_percentualHE']?? "")?>",
					"<?= ($a_mod['parametroPadrao']['para_tx_percentualSabadoHE']?? "")?>"
				);
			}

			function conferirParametroPadrao(idParametro, jornadaSemanal, jornadaSabado, percentualHE, percentualSabadoHE){

				var padronizado = (
					idParametro == parent.document.contex_form.parametro.value &&
					jornadaSemanal == parent.document.contex_form.jornadaSemanal.value &&
					jornadaSabado == parent.document.contex_form.jornadaSabado.value &&
					percentualHE == parent.document.contex_form.percentualHE.value &&
					percentualSabadoHE == parent.document.contex_form.percentualSabadoHE.value
				);
				console.log(idParametro);
				console.log(jornadaSemanal);
				console.log(jornadaSabado);
				console.log(percentualHE);
				console.log(percentualSabadoHE);
				parent.document.getElementsByName('textoParametroPadrao')[0].getElementsByTagName('p')[0].innerText = (padronizado? 'Sim': 'Não');
			}
		</script>
		<?php


		fecha_form($botoesCadastro);

		rodape();

		?>

		<form method="post" name="form_modifica" id="form_modifica">
			<input type="hidden" name="id" value="">
			<input type="hidden" name="acao" value="modificarMotorista">
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
		<?php
	}

	function index(){
		cabecalho("Cadastro de Motorista");

		$extraEmpresa = '';
		if ($_SESSION['user_nb_empresa'] > 0 && is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$extraEmpresa = " AND empr_nb_id = '".$_SESSION['user_nb_empresa']."'";
		}
		
		while(
			!empty($_POST['busca_cpf'])
			&& !empty($_POST['busca_cpf'][strlen($_POST['busca_cpf'])-1]) 
			&& in_array($_POST['busca_cpf'][strlen($_POST['busca_cpf'])-1], ['.', '-', ' '])
		){
			$_POST['busca_cpf'] = substr($_POST['busca_cpf'], 0, strlen($_POST['busca_cpf'])-1);
		}

		$extra =
			((!empty($_POST['busca_codigo']))? 		" AND enti_nb_id LIKE '%".$_POST['busca_codigo']."%'": '').
			((!empty($_POST['busca_matricula']))? 	" AND enti_tx_matricula LIKE '%".$_POST['busca_matricula']."%'": '').
			((!empty($_POST['busca_empresa']))? 	" AND enti_nb_empresa = '".$_POST['busca_empresa']."'": '').
			((!empty($_POST['busca_nome']))? 		" AND enti_tx_nome LIKE '%".$_POST['busca_nome']."%'": '').
			((!empty($_POST['busca_cpf']))? 		" AND enti_tx_cpf LIKE '%".$_POST['busca_cpf']."%'": '').
			((!empty($_POST['busca_ocupacao']))? 	" AND enti_tx_ocupacao = '".$_POST['busca_ocupacao']."'": '').
			((!empty($_POST['busca_parametro']))? 	" AND enti_nb_parametro = '".$_POST['busca_parametro']."'": '').
			(!empty($_POST['busca_status'])?		" AND enti_tx_status = '".strtolower($_POST['busca_status'])."'": '').
			(!empty($_POST['busca_padrao'])?		" AND enti_tx_ehPadrao = '".$_POST['busca_padrao']."'": '');

			$camposBusca = [ 
				campo('Código', 'busca_codigo', ($_POST['busca_codigo']?? ''), 1,'','maxlength="6"'),
				campo('Nome', 'busca_nome', ($_POST['busca_nome']?? ''), 2,'','maxlength="65"'),
				campo('Matrícula', 'busca_matricula', ($_POST['busca_matricula']?? ''), 1,'','maxlength="6"'),
				campo('CPF', 'busca_cpf', ($_POST['busca_cpf']?? ''), 2, 'MASCARA_CPF'),
				combo_bd('!Empresa', 'busca_empresa', ($_POST['busca_empresa']?? ''), 2, 'empresa', '', $extraEmpresa),
				combo('Ocupação', 'busca_ocupacao', ($_POST['busca_ocupacao']?? ''), 2, array("", "Motorista", "Ajudante")),
				combo('Convenção Padrão', 'busca_padrao', ($_POST['busca_padrao']?? ''), 2, ['' => 'todos', 'sim' => 'Sim', 'nao' => 'Não']),
				combo_bd('!Parâmetros da Jornada', 'busca_parametro', ($_POST['busca_parametro']?? ''), 6, 'parametro'),
				combo('Status', 'busca_status', ($_POST['busca_status']?? ''), 2, ['' => 'todos', 'ativo' => 'Ativo', 'inativo' => 'Inativo'])
			];

		$botoesBusca = [
			botao('Buscar', 'index'),
			botao('Inserir', 'visualizarCadastro','','','','','btn btn-success')
		];

		abre_form('Filtro de Busca');
		linha_form($camposBusca);
		fecha_form($botoesBusca);

		$sql = ( 
			"SELECT * FROM entidade 
				JOIN empresa ON enti_nb_empresa = empr_nb_id 
				JOIN parametro ON enti_nb_parametro = para_nb_id 
				WHERE enti_tx_ocupacao IN ('Motorista', 'Ajudante') 
					$extraEmpresa 
					$extra"
		);

		$icone_modificar = 'icone_modificar(enti_nb_id,modificarMotorista)';

		if (is_int(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
			$icone_excluir = 'icone_excluir(enti_nb_id,excluirMotorista)';
		}else{
			$icone_excluir = '';
		}

		$gridFields = [
			'CÓDIGO' 				=> 'enti_nb_id', 
			'NOME' 					=> 'enti_tx_nome', 
			'MATRÍCULA' 			=> 'enti_tx_matricula', 
			'CPF' 					=> 'enti_tx_cpf', 
			'EMPRESA' 				=> 'empr_tx_nome', 
			'FONE 1' 				=> 'enti_tx_fone1', 
			'FONE 2' 				=> 'enti_tx_fone2', 
			'OCUPAÇÃO' 				=> 'enti_tx_ocupacao', 
			'PARÂMETRO DA JORNADA' 	=> 'para_tx_nome', 
			'CONVENÇÃO PADRÃO' 		=> 'enti_tx_ehPadrao',
			'STATUS' 				=> 'enti_tx_status', 
			'<spam class="glyphicon glyphicon-search"></spam>' => $icone_modificar, 
			'<spam class="glyphicon glyphicon-remove"></spam>' => $icone_excluir
		];
		
		grid($sql, array_keys($gridFields), array_values($gridFields));
		rodape();
	}
?>