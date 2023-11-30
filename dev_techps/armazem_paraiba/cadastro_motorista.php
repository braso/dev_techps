<?php
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

	$enti_campos = [
		'matricula', 'nome', 'nascimento','cpf','rg','civil','sexo','endereco','numero','complemento',
		'bairro','cidade','cep','fone1','fone2','email','ocupacao','salario','obs',
		'tipo','status','empresa',
		'parametro','jornadaSemanal','jornadaSabado','percentualHE','percentualSabadoHE',
		'rgOrgao', 'rgDataEmissao', 'rgUf',
		'pai', 'mae', 'conjugue', 'tipoOperacao',
		'subcontratado', 'admissao', 'desligamento',
		'cnhRegistro', 'cnhValidade', 'cnhPrimeiraHabilitacao', 'cnhCategoria', 'cnhPermissao',
		'cnhObs', 'cnhCidade', 'cnhEmissao', 'cnhPontuacao', 'cnhAtividadeRemunerada','banco'
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

	$campos_obrigatorios = [
		'matricula', 'nome', 'nascimento', 'parametro', 'admissao', 'cnhValidade', 'cnhCategoria', 'cnhCidade', 'cnhEmissao', 'jornadaSemanal', 'jornadaSabado',
		'percentualHE', 'percentualSabadoHE', 'parametro', 'empresa', 'ocupacao', 'cidade', 'rg', 'endereco', 'cep', 'bairro', 'email', 'cnhRegistro'
	];
	foreach($campos_obrigatorios as $campo){
		if(!isset($_POST[$campo]) || empty($_POST[$campo])){
			echo '<script>alert("Preencha todas as informações obrigatórias.")</script>';
			layout_motorista();
			exit;
		}
	}

	if(count(carregar('entidade', '', 'enti_tx_matricula', $_POST['matricula'])) > 0 && !isset($_POST['id'])){
		echo '<script>alert("Matrícula já cadastrada.")</script>';
		layout_motorista();
		exit;
	}
	if(!empty($_POST['login'])){
		$sql = query("SELECT * FROM user WHERE user_tx_login = '".$_POST['login']."' LIMIT 1");
		if (num_linhas($sql) > 0){
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
	
	$post_values = [
		'matricula', 'nome', 'nascimento', 'cpf', 'rg', 'civil', 'sexo', 'endereco', 'numero', 'complemento',
		'bairro', 'cidade', 'cep', 'fone1', 'fone2', 'email', 'ocupacao', 'salario', 'obs',
		'nivel', 'status', 'empresa',
		'parametro', 'jornadaSemanal', 'jornadaSabado', 'percentualHE', 'percentualSabadoHE',
		'rgOrgao', 'rgDataEmissao', 'rgUf',
		'pai', 'mae', 'conjugue', 'tipoOperacao',
		'subcontratado', 'admissao', 'desligamento',
		'cnhRegistro', 'cnhValidade', 'cnhPrimeiraHabilitacao', 'cnhCategoria', 'cnhPermissao',
		'cnhObs', 'cnhCidade', 'cnhEmissao', 'cnhPontuacao', 'cnhAtividadeRemunerada', 'setBanco'
	];

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
				$ehPadrao = 'Sim';
			}
		}
		$enti_campos = array_merge($enti_campos, ['enti_nb_userCadastro', 'enti_tx_dataCadastro', 'enti_tx_ehPadrao']);
		$enti_valores = array_merge($enti_valores, [$_SESSION['user_nb_id'], date("Y-m-d H:i:s"), $ehPadrao]);
		$id = inserir('entidade', $enti_campos, $enti_valores);

		$user_infos = [
			'user_tx_matricula' 	=> $_POST['matricula'], 
			'user_tx_nome' 			=> $_POST['nome'], 
			'user_tx_login' 		=> (!empty($_POST['login'])? $_POST['login']: $_POST['matricula']), 
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

		$sql = query("SELECT * FROM user WHERE user_nb_entidade = '$_POST[id]' AND user_tx_nivel = 'Motorista'");
		$a_user = carrega_array($sql);

		if($a_user['user_nb_id'] > 0){
			$user_infos = [
				'user_tx_matricula' 	=> $_POST['matricula'], 
				'user_tx_nome' 			=> $_POST['nome'], 
				'user_tx_login' 		=> (!empty($_POST['login'])? $_POST['login']: $_POST['matricula']), 
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
		$enti_campos = array_merge($enti_campos, ['enti_nb_userCadastro', 'enti_tx_dataCadastro', 'enti_tx_ehPadrao']);
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

		$arq = enviar('cnhAnexo', "arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]/", 'CNH_' . $id . '_' . $_POST['matricula']);
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

		$arq = enviar('foto', "arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]/", 'FOTO_' . $id . '_' . $_POST['matricula']);
		if ($arq) {
			atualizar('entidade', array('enti_tx_foto'), array($arq), $id);
			atualizar('user', array('user_tx_foto'), array($arq), $idUserFoto['user_nb_id']);
		}
	}

	// if($_FILES[arquivo][name]!=''){
	// 	if(!is_dir("arquivos/funcionário/$id")){
	// 		mkdir("arquivos/funcionário/$id");
	// 	}

	// 	$arq=enviar('arquivo',"arquivos/funcionário/$id/");
	// 	if($arq){
	// 		atualizar('entidade',array(enti_tx_arquivo),array($arq),$id);
	// 	}
	// }

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
		$campos = ['nome','nascimento','cpf','rg','civil','sexo','endereco','numero','complemento',
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
	
	cabecalho("Cadastro de Motorista");

	
	// if(isset($a_mod['enti_nb_id'])){
	// 	$a_mod = array_merge($a_mod, carrega_array(query("SELECT * FROM user WHERE user_nb_entidade = ".$a_mod['enti_nb_id']." LIMIT 1;")));
	// }

	$data1 = new DateTime($a_mod['enti_tx_nascimento']);
	$data2 = new DateTime(date("Y-m-d"));

	$intervalo = $data1->diff($data2);

	$idade = "{$intervalo->y} anos, {$intervalo->m} meses e {$intervalo->d} dias";
	

	$uf = ['', 'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
	
	if($a_mod['enti_tx_foto']!=''){
		$img =texto(icone_excluir2($a_mod['enti_nb_id'], 'excluir_foto'), '<img style="width: 100%;" src="'.$a_mod['enti_tx_foto'].'" />', 2);
	}
	
	
	$c = [
	    $img,
		campo('Matrícula*', 'matricula', $a_mod['enti_tx_matricula'], 1, ''),
		campo('Nome*', 'nome', $a_mod['enti_tx_nome'], 3,'','maxlength="65"'),
		campo_data('Dt. Nascimento*', 'nascimento', $a_mod['enti_tx_nascimento'], 2),
		combo('status', 'status', $a_mod['enti_tx_status'], 2, array('ativo', 'inativo')),
		campo('Login','login', $a_mod['user_tx_login'],2),
		texto('Idade',$idade,2),

		campo('CPF*', 'cpf', $a_mod['enti_tx_cpf'], 2, 'MASCARA_CPF'),
		campo('RG*', 'rg', $a_mod['enti_tx_rg'], 2),
		campo('Emissor RG', 'rgOrgao', $a_mod['enti_tx_rgOrgao'], 2,'','maxlength="6"'),
		campo_data('Data Emissão RG', 'rgDataEmissao', $a_mod['enti_tx_rgDataEmissao'], 2),
		combo('UF RG', 'rgUf', $a_mod['enti_tx_rgUf'], 2, $uf),
		combo('Estado Civil', 'civil', $a_mod['enti_tx_civil'], 2, ['', 'Casado(a)', 'Solteiro(a)', 'Divorciado(a)', 'Viúvo(a)']),

		combo('Sexo', 'sexo', $a_mod['enti_tx_sexo'], 2, array('', 'Feminino', 'Masculino')),
		campo('CEP*', 'cep', $a_mod['enti_tx_cep'], 2, 'MASCARA_CEP', 'onkeyup="carrega_cep(this.value);"'),
		campo('Endereço*', 'endereco', $a_mod['enti_tx_endereco'], 4),
		campo('Número', 'numero', $a_mod['enti_tx_numero'], 2, 'MASCARA_NUMERO'),
		campo('Bairro*', 'bairro', $a_mod['enti_tx_bairro'], 2),

		campo('Complemento', 'complemento', $a_mod['enti_tx_complemento'], 2),
		campo('Ponto de Referência', 'referencia', $a_mod['enti_tx_referencia'], 3),
		combo_net('Cidade/UF*', 'cidade', $a_mod['enti_nb_cidade'], 3, 'cidade', '', '', 'cida_tx_uf'),
		campo('Telefone 1*', 'fone1', $a_mod['enti_tx_fone1'], 2, 'MASCARA_CEL'),
		campo('Telefone 2', 'fone2', $a_mod['enti_tx_fone2'], 2, 'MASCARA_CEL'),
		campo('E-mail*', 'email', $a_mod['enti_tx_email'], 3),

		campo('Filiação Pai', 'pai', $a_mod['enti_tx_pai'], 3,'', 'maxlength="65"'),
		campo('Filiação Mãe', 'mae', $a_mod['enti_tx_mae'], 3,'', 'maxlength="65"'),
		campo('Nome do Cônjugue', 'conjugue', $a_mod['enti_tx_conjugue'], 3,'', 'maxlength="65"'),
		campo('Tipo de Operação', 'tipoOperacao', $a_mod['enti_tx_tipoOperacao'], 3,'', 'maxlength="40"'),
		arquivo('Foto (.png, .jpg)', 'foto', $a_mod['enti_tx_foto'], 4),
		ckeditor('Observações:', 'obs', $a_mod['enti_tx_obs'], 12)
	];

	if ($_SESSION['user_nb_empresa'] > 0 && is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
		$extraEmpresa = " AND empr_nb_id = '$_SESSION[user_nb_empresa]'";
	}

	$cContratual = [
		combo_bd('Empresa*', 'empresa', $a_mod['enti_nb_empresa'], 3, 'empresa', 'onchange="carrega_empresa(this.value)"', $extraEmpresa),
		combo('Ocupação*', 'ocupacao', $a_mod['enti_tx_ocupacao'], 2, array("Motorista")), //TODO PRECISO SABER OS TIPOS DE MOTORISTA
		campo('Salário', 'salario', valor($a_mod['enti_tx_salario']), 1, 'MASCARA_VALOR'),
		combo('Subcontratado', 'subcontratado', $a_mod['enti_tx_subcontratado'], 2, array('', 'Sim', 'Não')),
		campo_data('Dt Admissão*', 'admissao', $a_mod['enti_tx_admissao'], 2),
		campo_data('Dt Desligamento', 'desligamento', $a_mod['enti_tx_desligamento'], 2),
		campo('Saldo de Horas', 'setBanco', $a_mod['enti_tx_banco'], 3, 'MASCARA_HORA', 'maxlength="8" placeholder="hh:mm"')
	];

	if ($a_mod['enti_nb_empresa']) {
		$icone_padronizar = icone_padronizar();
	}

	$cJornada = [
		combo_bd('Parâmetros da Jornada*' . $icone_padronizar, 'parametro', $a_mod['enti_nb_parametro'], 6, 'parametro', 'onchange="carrega_parametro()"'),
		campo_hora('Jornada Semanal (Horas/Dia)*', 'jornadaSemanal', $a_mod['enti_tx_jornadaSemanal'], 3),
		campo_hora('Jornada Sábado (Horas/Dia)*', 'jornadaSabado', $a_mod['enti_tx_jornadaSabado'], 3),
		// $cJornada[]=campo('Jornada Semanal (Horas)','jornadaSemanal',$a_mod['enti_tx_jornadaSemanal'],3,MASCARA_NUMERO),
		// $cJornada[]=campo('Jornada Sábado (Horas)','jornadaSabado',$a_mod['enti_tx_jornadaSabado'],3,MASCARA_NUMERO),
		campo('Percentual da HE(%)*', 'percentualHE', $a_mod['enti_tx_percentualHE'], 3, 'MASCARA_NUMERO'),
		campo('Percentual da HE Sábado(%)*', 'percentualSabadoHE', $a_mod['enti_tx_percentualSabadoHE'], 3, 'MASCARA_NUMERO')
	];

	if($a_mod['enti_nb_parametro'] > 0 ){
		$aParametro = carregar('parametro', $a_mod['enti_nb_parametro']);
		if( $aParametro['para_tx_jornadaSemanal'] != $a_mod['enti_tx_jornadaSemanal'] ||
			$aParametro['para_tx_jornadaSabado'] != $a_mod['enti_tx_jornadaSabado'] ||
			$aParametro['para_tx_percentualHE'] != $a_mod['enti_tx_percentualHE'] ||
			$aParametro['para_tx_percentualSabadoHE'] != $a_mod['enti_tx_percentualSabadoHE']){

			$ehPadrao = 'Não';
		}else{
			$ehPadrao = 'Sim';
		}
		
		$cJornada[]=texto('Convenção Padrão?', $ehPadrao, 2);
		
	}

	// echo icone_excluirCnh($a_mod['enti_nb_id'], 'excluir_cnh');
	if ($a_mod['enti_tx_cnhAnexo'])
		$iconeExcluirCnh = icone_excluirCnh($a_mod['enti_nb_id'], 'excluir_cnh');

	// exit;
	$cCNH = [
		campo('N° Registro*', 'cnhRegistro', $a_mod['enti_tx_cnhRegistro'], 3,'','maxlength="11"'),
		campo_data('Validade*', 'cnhValidade', $a_mod['enti_tx_cnhValidade'], 3),
		campo_data('1º Habilitação*', 'cnhPrimeiraHabilitacao', $a_mod['enti_tx_cnhPrimeiraHabilitacao'], 3),
		campo('Categoria*', 'cnhCategoria', $a_mod['enti_tx_cnhCategoria'], 3),
		campo('Permissão', 'cnhPermissao', $a_mod['enti_tx_cnhPermissao'], 3,'','maxlength="65"'),
		combo_net('Cidade/UF Emissão*', 'cnhCidade', $a_mod['enti_nb_cnhCidade'], 3, 'cidade', '', '', 'cida_tx_uf'),
		campo_data('Data Emissão*', 'cnhEmissao', $a_mod['enti_tx_cnhEmissao'], 3),
		campo('Pontuação', 'cnhPontuacao', $a_mod['enti_tx_cnhPontuacao'], 3,'','maxlength="3"'),
		combo('Atividade Remunerada', 'cnhAtividadeRemunerada', $a_mod['enti_tx_cnhAtividadeRemunerada'], 3, array('', 'Sim', 'Não')),
		arquivo('CNH (.png, .jpg, .pdf)' . $iconeExcluirCnh, 'cnhAnexo', $a_mod['enti_tx_cnhAnexo'], 4),
		campo('Observações', 'cnhObs', $a_mod['enti_tx_cnhObs'], 3,'','maxlength="500"')
	];


	$b[] = botao('Gravar', 'cadastra_motorista', 'id', $_POST['id']);
	$b[] = botao('Voltar', 'index');

	abre_form('Dados Cadastrais');
	linha_form($c);
	echo "<br>";
	fieldset('Dados Contratuais');
	linha_form($cContratual);
	echo "<br>";
	fieldset('CONVEÇÃO SINDICAL - JORNADA DO MOTORISTA PADRÃO');
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

		//setup before functions
		let typingTimer; //timer identifier
		let doneTypingInterval = 1000; //time in ms (1 seconds)
		let myInput = document.getElementById('matricula');

		//on keyup, start the countdown
		myInput.addEventListener('keyup', () => {
			clearTimeout(typingTimer);
			if (myInput.value) {
				typingTimer = setTimeout(doneTyping, doneTypingInterval);
			}
		});

		//user is "finished typing," do something
		function doneTyping() {
			let matricula = myInput.value;
			document.getElementById('frame_parametro').src = 'cadastro_motorista.php?acao=carrega_matricula&matricula=' + matricula + '&id=<?= $a_mod['enti_nb_id'] ?>';
		}
	</script>
	<?php


	// if($a_mod[enti_nb_id] > 0 && $a_mod[enti_tx_arquivo] != ''){
	// 	echo "<br>";
	// 	echo "<div class=portlet-title>";
	// 	echo"<span class='caption-subject font-dark bold uppercase' style='font-size:16px'> ARQUIVOS</span>";
	// 	echo"<hr>";
	// 	echo"</div>";
	// 	if ($handle = opendir("arquivos/funcionário/$a_mod[enti_nb_id]")) {

	// 		while (false !== ($arquivo = readdir($handle))) {

	// 			if ($arquivo != "." && $arquivo != "..") {

	// 				$c2[] = texto("Arquivo ".++$contador,"<a href='arquivos/funcionário/$a_mod[enti_nb_id]/$arquivo' target=_blank>".$arquivo."</a> <a class='glyphicon glyphicon-remove' onclick='javascript:remover_arquivo(\"$a_mod[enti_nb_id]\",\"excluir_arquivo_paciente\",\"$arquivo\")'></a>",6);
	// 			}
	// 		}

	// 		closedir($handle);
	// 		linha_form($c2);

	// 	}

	// }

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

function icone_excluir2($id, $acao, $campos = '', $valores = '', $target = '', $icone = 'glyphicon glyphicon-remove', $msg = 'Deseja excluir o registro?') {
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
		((!empty($_POST['busca_codigo']))? 		" AND enti_nb_id = '".$_POST['busca_codigo']."'": '').
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
	$c[] = combo('Padrão', 'busca_padrao', $_POST['busca_padrao'], 2, array('Todos', 'Sim', 'Não'));
	$c[] = combo_bd('!Parâmetros da Jornada', 'busca_parametro', $_POST['busca_parametro'], 6, 'parametro');
	$c[] = combo('Status', 'busca_status', $_POST['busca_status'], 2, array('Todos', 'Ativo', 'Inativo'));

	$b[] = botao('Buscar', 'index');
	$b[] = botao('Inserir', 'layout_motorista');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	
	/*
	$temp_sql = '';
	if(	(isset($_POST('enti_tx_jornadaSemanal')) 		&& !empty($_POST('enti_tx_jornadaSemanal')))
	 || (isset($_POST('enti_tx_jornadaSabado')) 		&& !empty($_POST('enti_tx_jornadaSabado')))
	 || (isset($_POST('enti_tx_percentualHE')) 			&& !empty($_POST('enti_tx_percentualHE')))
	 || (isset($_POST('enti_tx_percentualSabadoHE')) 	&& !empty($_POST('enti_tx_percentualSabadoHE')))
	 || (isset($_POST('enti_nb_parametro')) 			&& !empty($_POST('enti_nb_parametro')))){
		$temp_sql = 
			", CASE
				WHEN (".(!empty($_POST('enti_tx_jornadaSemanal'))? "para_tx_jornadaSemanal != '".$_POST('enti_tx_jornadaSemanal')."' OR": '')."
					".(!empty($_POST('enti_tx_jornadaSabado'))? "para_tx_jornadaSabado != '".$_POST('enti_tx_jornadaSabado')."' OR": '')."
					".(!empty($_POST('enti_tx_percentualHE'))? "para_tx_percentualHE != '".$_POST('enti_tx_percentualHE')."' OR": '')."
					".(!empty($_POST('enti_tx_percentualSabadoHE'))? "para_tx_percentualSabadoHE != '".$_POST('enti_tx_percentualSabadoHE')."' OR": '')."
					".(!empty($_POST('enti_nb_parametro'))? "empr_nb_parametro != '".$_POST('enti_nb_parametro')."'": '').")
				THEN 'Não'
				ELSE 'Sim'
			END AS enti_tx_ehPadrao"
		;
	}
	*/

	$sql = "SELECT * FROM entidade JOIN empresa ON enti_nb_empresa = empr_nb_id JOIN parametro ON enti_nb_parametro = para_nb_id
			WHERE enti_tx_tipo = 'Motorista' 
			$extraEmpresa $extra";

	$cab = ['CÓDIGO', 'NOME', 'MATRÍCULA', 'CPF', 'EMPRESA', 'FONE 1', 'FONE 2', 'OCUPAÇÃO', 'PARÂMETRO DA JORNADA', 'PADRÃO', 'STATUS', '', ''];
	$val = [
		'enti_nb_id', 'enti_tx_nome', 'enti_tx_matricula', 'enti_tx_cpf', 'empr_tx_nome', 'enti_tx_fone1', 'enti_tx_fone2', 'enti_tx_ocupacao', 'para_tx_nome', 'enti_tx_ehPadrao', 'enti_tx_status', 'icone_modificar(enti_nb_id,modifica_motorista)',
		'icone_excluir(enti_nb_id,exclui_motorista)'
	];

	grid($sql, $cab, $val);

	rodape();
}


?>