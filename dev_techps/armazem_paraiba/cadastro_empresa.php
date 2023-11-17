<?php
include "conecta.php";

function exclui_empresa(){

	remover('empresa',$_POST['id']);
	index();
	exit;

}

function excluir_logo(){
	atualizar('empresa',array('empr_tx_logo'),array(''),$_POST['idEntidade']);
	$_POST['id']=$_POST['idEntidade'];
	modifica_empresa();
	exit;
}

function modifica_empresa(){
	global $a_mod;

	$a_mod=carregar('empresa',$_POST['id']);

	layout_empresa();
	exit;

}

function cadastra_empresa(){
    $sqlCheckNivel = query("SELECT empr_tx_Ehmatriz FROM empresa WHERE empr_nb_id = '$_POST[id]'")->fetch_assoc();
	$campos = ['cnpj', 'nome', 'cep', 'numero', 'email', 'parametro', 'cidade','endereco','bairro'];
	foreach($campos as $campo){
		if(!isset($_POST[$campo]) && $sqlCheckNivel["empr_tx_Ehmatriz"] != 'Sim' || empty($_POST[$campo]) && $sqlCheckNivel["empr_tx_Ehmatriz"] != 'Sim'){
			echo '<script>alert("Preencha todas as informações obrigatórias.")</script>';
			layout_empresa();
			exit;
		}
	}

	$campos=[
		'empr_tx_nome', 'empr_tx_fantasia', 'empr_tx_cnpj', 'empr_tx_cep', 'empr_nb_cidade', 'empr_tx_endereco', 'empr_tx_bairro', 'empr_tx_numero', 'empr_tx_complemento', 'empr_tx_referencia',
		'empr_tx_fone1', 'empr_tx_fone2', 'empr_tx_email', 'empr_tx_inscricaoEstadual', 'empr_tx_inscricaoMunicipal', 'empr_tx_regimeTributario', 'empr_tx_status', 'empr_tx_situacao', 'empr_nb_parametro', 'empr_tx_contato',
		'empr_tx_dataRegistroCNPJ', 'empr_tx_domain', 'empr_tx_ftpServer', 'empr_tx_ftpUsername', 'empr_tx_ftpUserpass', 'empr_tx_Ehmatriz'
	];
	
	$valores = [
		$_POST['nome'], $_POST['fantasia'], $_POST['cnpj'], $_POST['cep'], $_POST['cidade'], $_POST['endereco'], $_POST['bairro'], $_POST['numero'], $_POST['complemento'], $_POST['referencia'],
		$_POST['fone1'], $_POST['fone2'], $_POST['email'], $_POST['inscricaoEstadual'], $_POST['inscricaoMunicipal'], $_POST['regimeTributario'], 'ativo', $_POST['situacao'], $_POST['parametro'], $_POST['contato'],
		$_POST['dataRegistroCNPJ'], "https://braso.mobi/".(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))? 'dev_techps/': 'techps/').$_POST['nomeDominio'], $_POST['ftpServer'], $_POST['ftpUsername'], $_POST['ftpUserpass'],
		$_POST['matriz']
	];


	$empty_ftp_inputs = empty($_POST['ftpServer']) + empty($_POST['ftpUsername']) + empty($_POST['ftpUserpass']) + 0;

	if($empty_ftp_inputs == 3){
		$_POST['ftpServer']   = 'ftp-jornadas.positronrt.com.br';
		$_POST['ftpUsername'] = '08995631000108';
		$_POST['ftpUserpass'] = '0899';
	}elseif($empty_ftp_inputs > 0){
		echo '<script>alert("Preencha os 3 campos de FTP.")</script>';
		layout_empresa();
		exit;
	}

	if(isset($_POST['id']) && $_POST['id'] != ''){

		$file_type = $_FILES['logo']['type']; //returns the mimetype

		$allowed = array("image/jpeg", "image/gif", "image/png", "image/jpg");
		$id_empresa = $_POST['id'];

		if(in_array($file_type, $allowed) && $_FILES['logo']['name']!='') {
			if(!is_dir("arquivos/empresa/$id_empresa")){
				mkdir("arquivos/empresa/$id_empresa");
			}
			$arq=enviar('logo',"arquivos/empresa/$id_empresa/",$id_empresa);
			if($arq){
				$campos[] = 'empr_tx_logo';
				$valores[] = $arq;
			}
		}
// 		$sqlCheckNivel["empr_tx_Ehmatriz"]
        if(empty($sqlCheckNivel["empr_tx_Ehmatriz"]) || $sqlCheckNivel["empr_tx_Ehmatriz"] != 'Sim'){
			$campos=[
				'empr_tx_nome', 'empr_tx_fantasia', 'empr_tx_cnpj', 'empr_tx_cep', 'empr_nb_cidade', 'empr_tx_endereco', 'empr_tx_bairro', 'empr_tx_numero', 'empr_tx_complemento', 'empr_tx_referencia',
				'empr_tx_fone1', 'empr_tx_fone2', 'empr_tx_email', 'empr_tx_inscricaoEstadual', 'empr_tx_inscricaoMunicipal', 'empr_tx_regimeTributario', 'empr_tx_status', 'empr_tx_situacao', 'empr_nb_parametro', 'empr_tx_contato',
				'empr_tx_dataRegistroCNPJ', 'empr_tx_domain', 'empr_tx_ftpServer', 'empr_tx_ftpUsername', 'empr_tx_ftpUserpass', 'empr_tx_Ehmatriz'
			];
			
			$valores = [
				$_POST['nome'], $_POST['fantasia'], $_POST['cnpj'], $_POST['cep'], $_POST['cidade'], $_POST['endereco'], $_POST['bairro'], $_POST['numero'], $_POST['complemento'], $_POST['referencia'],
				$_POST['fone1'], $_POST['fone2'], $_POST['email'], $_POST['inscricaoEstadual'], $_POST['inscricaoMunicipal'], $_POST['regimeTributario'], 'ativo', $_POST['situacao'], $_POST['parametro'], $_POST['contato'],
				$_POST['dataRegistroCNPJ'], "https://braso.mobi/".(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))? 'dev_techps/': 'techps/').$_POST['nomeDominio'], $_POST['ftpServer'], $_POST['ftpUsername'], $_POST['ftpUserpass'],
				$_POST['matriz']
			];
		}else {
		    $campos = ['empr_nb_parametro'];
			
			$valores = [$_POST['parametro']];
		}

		$campos = array_merge($campos,array('empr_nb_userAtualiza','empr_tx_dataAtualiza'));
		$valores = array_merge($valores,array($_SESSION['user_nb_id'], date("Y-m-d H:i:s")));
		atualizar('empresa',$campos,$valores,$_POST['id']);
	}else{
		$campos = array_merge($campos,array('empr_nb_userCadastro','empr_tx_dataCadastro'));
		$valores = array_merge($valores,array($_SESSION['user_nb_id'], date("Y-m-d H:i:s")));
		try{
			$id_empresa = inserir('empresa',$campos,$valores);
		}catch(Exception $e){
			print_r($e);
		}
	}

	

	index();
	exit;
}



function busca_cep($cep){	
    $resultado = @file_get_contents('https://viacep.com.br/ws/'.urlencode($cep).'/json/');
    $arr = json_decode($resultado, true);
    return $arr;  
}

function carrega_endereco(){
	
	$arr = busca_cep($_GET['cep']);
	
	?>
	<script src="/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>
	<script type="text/javascript">
		parent.document.contex_form.endereco.value='<?=$arr['logradouro']?>';
		parent.document.contex_form.bairro.value='<?=$arr['bairro']?>';

		var selecionado = $('.cidade',parent.document);
		selecionado.empty();
		selecionado.append('<option value=<?=$arr['ibge']?>><?="[$arr[uf]] ".$arr['localidade']?></option>');
		selecionado.val("<?=$arr['ibge']?>").trigger("change");

	</script>
	<?

	exit;
}

function checa_cnpj(){
	if(strlen($_GET['cnpj']) == 18 || strlen($_GET['cnpj']) == 14){
		$id = (int)$_GET['id'];
		$cnpj = substr($_GET['cnpj'], 0, 18);

		$sql = query("SELECT * FROM empresa WHERE empr_tx_cnpj = '$cnpj' AND empr_nb_id != $id AND empr_tx_status = 'ativo' LIMIT 1");
		$a = carrega_array($sql);
		
		if($a['empr_nb_id'] > 0){
			?>
			<script type="text/javascript">
				if(confirm("CPF/CNPJ já cadastrado, deseja atualizar o registro?")){
					parent.document.form_modifica.id.value='<?=$a['empr_nb_id']?>';
					parent.document.form_modifica.submit();
				}else{
					parent.document.contex_form.cnpj.value='';
				}
			</script>
			<?
		}
	}

	exit;
}

function campo_domain($nome,$variavel,$modificador,$tamanho,$mascara='',$extra=''){

	if($mascara=="domain") {
		$data_input="<script>
			$(document).ready(function() {
				var inputField = $('#nomeDominio');
				var domainPrefix = 'https://braso.mobi/".(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))? 'dev_techps/': 'techps/')."';

				function updateDisplayedText() {
					var inputValue = inputField.val();

					if (inputValue.startsWith(domainPrefix)) {
						var displayedText = inputValue.substring(domainPrefix.length);
						inputField.val(displayedText);
					}
				}

				// Executar a função de atualização quando o campo for modificado
				inputField.on('input', updateDisplayedText);

				// Inicializar o campo com o valor correto
				updateDisplayedText();
			});
			</script>";
	}

	$campo='<div class="col-sm-'.$tamanho.' margin-bottom-5">
			<label><b>'.$nome.'</b></label>
			<input name="'.$variavel.'" id="'.$variavel.'" value="'.$modificador.'" autocomplete="off" type="text" class="form-control input-sm" '.$extra.'>
		</div>';

	

	return $campo.$data_input;

}


function layout_empresa(){
	global $a_mod;

	cabecalho('Cadastro Empresa/Filial');

	$regimes = ['', 'Simples Nacional', 'Lucro Presumido', 'Lucro Real'];
    if(empty($a_mod)){  //Não tem os dados de atualização, então significa que pode estar criando e deu um erro
        $input_values = [
        	'situacao' => $_POST['situacao'],
        	'cep' => $_POST['cep'],
        	'endereco' => $_POST['endereco'],
        	'numero' => $_POST['numero'],
        	'bairro' => $_POST['bairro'],
        	'cnpj' => $_POST['cnpj'],
        	'nome' => $_POST['nome'],
        	'fantasia' => $_POST['fantasia'],
        	'complemento' => $_POST['complemento'],
        	'referencia' => $_POST['referencia'],
        	'cidade' => $_POST['cidade'],
        	'fone1' => $_POST['fone1'],
        	'fone2' => $_POST['fone2'],
        	'contato' => $_POST['contato'],
        	'email' => $_POST['email'],
        	'inscricaoEstadual' => $_POST['inscricaoEstadual'],
        	'inscricaoMunicipal' => $_POST['inscricaoMunicipal'],
        	'regimeTributario' => $_POST['regimeTributario'],
        	'dataRegistroCNPJ' => empty($_POST['dataRegistroCNPJ'])? date('Y-m-d', 0): $_POST['dataRegistroCNPJ'],
        	'logo' => $_POST['logo'],
        	'domain' => $_POST['domain'],
        	'ftpServer' => $_POST['ftpServer'],
        	'ftpUsername' => $_POST['ftpUsername'],
        	'ftpUserpass' => $_POST['ftpUserpass']
        ];
		$btn_txt = 'Cadastrar';
    }else{ //Tem os dados de atualização, então apenas mantém os valores.
        $input_values = [
        	'situacao' => $a_mod['empr_tx_situacao'],
        	'cep' => $a_mod['empr_tx_cep'],
        	'endereco' => $a_mod['empr_tx_endereco'],
        	'numero' => $a_mod['empr_tx_numero'],
        	'bairro' => $a_mod['empr_tx_bairro'],
        	'cnpj' => $a_mod['empr_tx_cnpj'],
        	'nome' => $a_mod['empr_tx_nome'],
        	'fantasia' => $a_mod['empr_tx_fantasia'],
        	'complemento' => $a_mod['empr_tx_complemento'],
        	'referencia' => $a_mod['empr_tx_referencia'],
        	'cidade' => $a_mod['empr_nb_cidade'],
        	'fone1' => $a_mod['empr_tx_fone1'],
        	'fone2' => $a_mod['empr_tx_fone2'],
        	'contato' => $a_mod['empr_tx_contato'],
        	'email' => $a_mod['empr_tx_email'],
        	'inscricaoEstadual' => $a_mod['empr_tx_inscricaoEstadual'],
        	'inscricaoMunicipal' => $a_mod['empr_tx_inscricaoMunicipal'],
        	'regimeTributario' => $a_mod['empr_tx_regimeTributario'],
        	'dataRegistroCNPJ' => empty($a_mod['empr_tx_dataRegistroCNPJ'])? date('Y-m-d', 0): $a_mod['empr_tx_dataRegistroCNPJ'],
        	'logo' => $a_mod['empr_tx_logo'],
        	'domain' => $a_mod['empr_tx_domain'],
        	'ftpServer' => $a_mod['empr_tx_ftpServer'] == 'ftp-jornadas.positronrt.com.br'? '': $a_mod['empr_tx_ftpServer'],
        	'ftpUsername' => $a_mod['empr_tx_ftpUsername'] == '08995631000108'? '': $a_mod['empr_tx_ftpUsername'],
        	'ftpUserpass' => $a_mod['empr_tx_ftpUserpass'] == '0899'? '': $a_mod['empr_tx_ftpUserpass'],
			'matriz' => $a_mod['empr_tx_Ehmatriz']
        ];
		$btn_txt = 'Atualizar';
    }

	if($input_values['logo'] != ''){
		$iconeExcluirLogo = icone_excluirLogo($a_mod['empr_nb_id'], 'excluir_logo');
	}

	if(is_int(strpos($_SESSION['user_tx_nivel'], "Super Administrador"))){
		$campo_dominio = campo_domain('Nome do Domínio','nomeDominio',$input_values['domain'],2,'domain');
		$campo_EhMatriz = combo('É matriz?','matriz',$input_values['empr_tx_Ehmatriz'],2,['Sim','Não']);
	}else{
		$campo_dominio = texto('Nome do Domínio',$input_values['domain'],2);
		$campo_EhMatriz = texto('É matriz?',$input_values['empr_tx_Ehmatriz'],2);
	}

	if(isset($input_values['cidade'])){
		$cidade_query = query("SELECT * FROM `cidade` WHERE cida_tx_status = 'ativo' AND cida_nb_id = $input_values[cidade]");
		$cidade = mysqli_fetch_array($cidade_query);
	}else{
		$cidade = ['cida_tx_nome' => ''];
	}
	$campo_cidade = texto('Cidade/UF', $cidade['cida_tx_nome'], 2);

	if (strpos($_SESSION['user_tx_nivel'], "Super Administrador") !== TRUE && $input_values['matriz'] == 'Sim') {
		$c = [
			texto('CPF/CNPJ*',$input_values['cnpj'],2),
			texto('Nome*',$input_values['nome'],4),
			texto('Nome Fantasia',$input_values['fantasia'],3),
			texto('Situação',$input_values['situacao'],2),
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
			texto('Regime Tributário',$input_values['regimeTributario'],3,$regimes),
			texto('Data Reg. CNPJ',$input_values['dataRegistroCNPJ'],3),
			$campo_dominio,
			$campo_EhMatriz,
			
			texto('Servidor FTP',$input_values['ftpServer'],3),
			texto('Usuário FTP',$input_values['ftpUsername'],3)
		];
	
	}else{
		$c = [
			campo('CPF/CNPJ*','cnpj',$input_values['cnpj'],2,'MASCARA_CPF','onkeyup="checa_cnpj(this.value);"'),
			campo('Nome*','nome',$input_values['nome'],4,'','maxlength="65"'),
			campo('Nome Fantasia','fantasia',$input_values['fantasia'],4,'','maxlength="65"'),
			combo('Situação','situacao',$input_values['situacao'],2,array('Ativo','Inativo')),
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
    

	
	$cJornada[]=combo_bd('Parâmetros da Jornada*','parametro',$a_mod['empr_nb_parametro'],6,'parametro','onchange="carrega_parametro(this.value)"');
	// $cJornada[]=campo('Jornada Semanal (Horas)','jornadaSemanal',$a_mod['enti_tx_jornadaSemanal'],3,MASCARA_NUMERO,'disabled=disabled');
	// $cJornada[]=campo('Jornada Sábado (Horas)','jornadaSabado',$a_mod['enti_tx_jornadaSabado'],3,MASCARA_NUMERO,'disabled=disabled');
	// $cJornada[]=campo('Percentual da HE(%)','percentualHE',$a_mod['enti_tx_percentualHE'],3,MASCARA_NUMERO,'disabled=disabled');
	// $cJornada[]=campo('Percentual da HE Sábado(%)','percentualSabadoHE',$a_mod['enti_tx_percentualSabadoHE'],3,MASCARA_NUMERO,'disabled=disabled');

	$file = basename(__FILE__);
	$file = explode('.', $file);

	$botao[] = botao($btn_txt,'cadastra_empresa','id',$_POST['id']);
	$botao[] = botao('Voltar','index');
	
	abre_form("Dados da Empresa/Filial");
	linha_form($c);
	echo "<br>";
	fieldset("CONVEÇÃO SINDICAL - JORNADA DO MOTORISTA PADRÃO");
	linha_form($cJornada);

	if($a_mod['empr_nb_userCadastro'] > 0){
		$a_userCadastro = carregar('user',$a_mod['empr_nb_userCadastro']);
		$txtCadastro = "Registro inserido por $a_userCadastro[user_tx_login] às ".data($a_mod['empr_tx_dataCadastro']).".";
		$cAtualiza[] = texto("Data de Cadastro","$txtCadastro",5);
		if($a_mod['empr_nb_userAtualiza'] > 0){
			$a_userAtualiza = carregar('user',$a_mod['empr_nb_userAtualiza']);
			$txtAtualiza = "Registro atualizado por $a_userAtualiza[user_tx_login] às ".data($a_mod['empr_tx_dataAtualiza'],1).".";
			$cAtualiza[] = texto("Última Atualização","$txtAtualiza",5);
		}
		echo "<br>";
		linha_form($cAtualiza);
	}

	fecha_form($botao);

	$path_parts = pathinfo( __FILE__ );
	?>
	<iframe id=frame_parametro style="display: none;"></iframe>
	<script>
		function carrega_parametro(id){
			document.getElementById('frame_parametro').src='cadastro_motorista.php?acao=carrega_parametro&parametro='+id;
		}
	</script>
	<?php

	rodape();

	
	$path_parts = pathinfo( __FILE__ );
	?>
	<iframe id=frame_cep style="display: none;"></iframe>
	<form method="post" name="form_modifica" id="form_modifica">
		<input type="hidden" name="id" value="">
		<input type="hidden" name="acao" value="modifica_empresa">
	</form>
	<form name="form_excluir_arquivo" method="post" action="cadastro_empresa.php">
		<input type="hidden" name="idEntidade" value="">
		<input type="hidden" name="nome_arquivo" value="">
		<input type="hidden" name="acao" value="">
	</form>
	<script>
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
				document.getElementById('frame_cep').src = '<?= $path_parts['basename'] ?>?acao=carrega_endereco&cep=' + num;
			}
		}
		
		function checa_cnpj(cnpj){
			if(cnpj.length == '18' || cnpj.length == '14'){
				document.getElementById('frame_cep').src='<?=$path_parts['basename']?>?acao=checa_cnpj&cnpj='+cnpj+'&id=<?=$a_mod['empr_nb_id']?>'
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
						$('#situação').val(response[0].empr_tx_fantasia);
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
	</script>
	<?php
}

function concat($id){
	$a = carregar('cidade', $id);
	return "[$a[cida_tx_uf]]$a[cida_tx_nome]";
}

function icone_excluirLogo($id,$acao,$campos='',$valores='',$target='',$icone='glyphicon glyphicon-remove',$msg='Deseja excluir a CNH?'){
	$icone='class="'.$icone.'"';
	if($id > 0)
		return "<a style='text-shadow: none; color: #337ab7;' onclick='javascript:remover_foto(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\");' > (Excluir) </a>";
	else
		return '';
	
}

function index(){

	cabecalho("Cadastro Empresa/Filial");
	$extra = '';

	if ($_SESSION['user_nb_empresa'] > 0 && is_bool(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
		$extraEmpresa = " AND empr_nb_id = '$_SESSION[user_nb_empresa]'";
	}
	if($_POST['busca_situacao'] == '')
		$_POST['busca_situacao'] = 'Ativo';

	if($_POST['busca_codigo'])
		$extra .= " AND empr_nb_id = '".$_POST['busca_codigo']."'";
	if($_POST['busca_nome'])
		$extra .= " AND empr_tx_nome LIKE '%".$_POST['busca_nome']."%'";
	if($_POST['busca_fantasia'])
		$extra .= " AND empr_tx_fantasia LIKE '%".$_POST['busca_fantasia']."%'";
	if($_POST['busca_cnpj'])
		$extra .= " AND empr_tx_cnpj = '".$_POST['busca_cnpj']."'";
	if($_POST['busca_situacao'] && $_POST['busca_situacao'] != 'Todos')
		$extra .= " AND empr_tx_situacao = '".$_POST['busca_situacao']."'";
	if($_POST['busca_uf'])
		$extra .= " AND cida_tx_uf = '".$_POST['busca_uf']."'";
	

	$uf = array('', 'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO');
	

	$c[] = campo('Código','busca_codigo',$_POST['busca_codigo'],2,'MASCARA_NUMERO','maxlength="6"');
	$c[] = campo('Nome','busca_nome',$_POST['busca_nome'],3,'','maxlength="65"');
	$c[] = campo('Nome Fantasia','busca_fantasia',$_POST['busca_fantasia'],2,'','maxlength="65"');
	$c[] = campo('CPF/CNPJ','busca_cnpj',$_POST['busca_cnpj'],2,'MASCARA_CPF');
	$c[] = combo('UF','busca_uf',$_POST['busca_uf'],1,$uf);
	$c[] = combo('Situação','busca_situacao',$_POST['busca_situacao'],2,['Todos','Ativo','Inativo']);

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_empresa');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM empresa, cidade WHERE empr_tx_status != 'inativo' AND empr_nb_cidade = cida_nb_id $extra";
	$cab = ['CÓDIGO','NOME','FANTASIA','CPF/CNPJ','CIDADE/UF','SITUAÇÃO','',''];
	$val = ['empr_nb_id','empr_tx_nome','empr_tx_fantasia','empr_tx_cnpj','concat(cida_nb_id)','empr_tx_situacao','icone_modificar(empr_nb_id,modifica_empresa)','icone_excluir(empr_nb_id,exclui_empresa)'];

	grid($sql,$cab,$val);

	rodape();

}