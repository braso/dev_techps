<?php
include "conecta.php";


function exclui_motorista(){
	remover('entidade',$_POST[id]);

	index();
	exit;
}


function excluir_foto(){
	atualizar('entidade',array('enti_tx_foto'),array(''),$_POST[idEntidade]);
	$_POST[id]=$_POST[idEntidade];
	modifica_motorista();
	exit;
}

function excluir_cnh(){
	atualizar('entidade',array('enti_tx_cnhAnexo'),array(''),$_POST[idEntidade]);
	$_POST[id]=$_POST[idEntidade];
	modifica_motorista();
	exit;
}

function modifica_motorista(){
	global $a_mod;

	$a_mod = carregar('entidade',$_POST[id]);

	layout_motorista();
	exit;
}


function cadastra_motorista(){
	global $a_mod;

	$campos = array(enti_tx_nome,enti_tx_nascimento,enti_tx_cpf,enti_tx_rg,enti_tx_civil,enti_tx_sexo,enti_tx_endereco,enti_tx_numero,enti_tx_complemento,
		enti_tx_bairro,enti_nb_cidade,enti_tx_cep,enti_tx_fone1,enti_tx_fone2,enti_tx_email,enti_tx_ocupacao,enti_tx_salario,enti_tx_obs,
		enti_tx_tipo,enti_tx_status,enti_tx_matricula,enti_nb_empresa,
		enti_nb_parametro,enti_tx_jornadaSemanal,enti_tx_jornadaSabado,enti_tx_percentualHE,enti_tx_percentualSabadoHE,
		enti_tx_rgOrgao, enti_tx_rgDataEmissao, enti_tx_rgUf,
		enti_tx_pai, enti_tx_mae, enti_tx_conjugue, enti_tx_tipoOperacao,
		enti_tx_subcontratado, enti_tx_admissao, enti_tx_desligamento,
		enti_tx_cnhRegistro, enti_tx_cnhValidade, enti_tx_cnhPrimeiraHabilitacao, enti_tx_cnhCategoria, enti_tx_cnhPermissao,
		enti_tx_cnhObs, enti_nb_cnhCidade, enti_tx_cnhEmissao, enti_tx_cnhPontuacao, enti_tx_cnhAtividadeRemunerada
	);


	$nascimento = ($_POST['nascimento'] == '') ? '0000-00-00' : $_POST['nascimento'];

	$salario = ($_POST['salario'] == '') ? 0.0 : valor($_POST['salario']);

	$parametro = ($_POST['parametro'] === '') ? 0 : $_POST['parametro'];

	$rgDataEmissao = ($_POST[rgDataEmissao] === '') ? '0000-00-00' : $_POST[rgDataEmissao];

    $admissao = ($_POST[admissao] === '') ? '0000-00-00' :
        $_POST[admissao];

    $desligamento = ($_POST[desligamento] === '') ? '0000-00-00' : $_POST[desligamento];

    $cnhValidade= ($_POST[cnhValidade] === '') ? '0000-00-00' : $_POST[cnhValidade];

    $cnhPrimeiraHabilitacao = ($_POST[cnhPrimeiraHabilitacao] === '') ? '0000-00-00' : $_POST[cnhPrimeiraHabilitacao];

    $cnhCidade = ($_POST[cnhCidade] == '') ? 00 : $_POST[cnhCidade];

    $cnhEmissao = ($_POST[cnhEmissao] === '') ? '0000-00-00' : $_POST[cnhEmissao];

	   //valor($_POST[salario])
	$valores = array($_POST[nome],$nascimento,$_POST[cpf],$_POST[rg],$_POST[civil],$_POST[sexo],$_POST[endereco],$_POST[numero],$_POST[complemento],
		$_POST[bairro],$_POST[cidade],$_POST[cep],$_POST[fone1],$_POST[fone2],$_POST[email],$_POST[ocupacao],$salario,$_POST[obs],
		'Motorista','ativo',$_POST[matricula],$_POST[empresa],
		$parametro,$_POST[jornadaSemanal],$_POST[jornadaSabado],$_POST[percentualHE],$_POST[percentualSabadoHE],
		$_POST[rgOrgao], $rgDataEmissao, $_POST[rgUf],
		$_POST[pai], $_POST[mae], $_POST[conjugue], $_POST[tipoOperacao],
		$_POST[subcontratado], $admissao, $desligamento,
		$_POST[cnhRegistro], $cnhValidade, $cnhPrimeiraHabilitacao, $_POST[cnhCategoria], $_POST[cnhPermissao],
		$_POST[cnhObs], $cnhCidade, $cnhEmissao, $_POST[cnhPontuacao], $_POST[cnhAtividadeRemunerada]
	);

	if(!$_POST[id]){
		$campos = array_merge($campos,array(enti_nb_userCadastro,enti_tx_dataCadastro));
		$valores = array_merge($valores,array($_SESSION[user_nb_id], date("Y-m-d H:i:s")));
		$id=inserir('entidade',$campos,$valores);

		// ADICIONA O USUARIO AO INSERIR NOVO motorista (USUARIO E SENHA = CPF) - PREENCHER A VARIAVEL USER_NB_ENTIDADE
		$campos = array(user_tx_nome,user_tx_login,user_tx_nivel,user_tx_senha,user_tx_status, user_nb_entidade);
		$valores = array($_POST[nome],$_POST[cpf],$_POST[nivel],md5($_POST[cpf]),'ativo', $id);
		$id = inserir('user',$campos,$valores);

	}else{

		$campos = array_merge($campos,array(enti_nb_userAtualiza,enti_tx_dataAtualiza));
		$valores = array_merge($valores,array($_SESSION[user_nb_id], date("Y-m-d H:i:s")));
		atualizar('entidade',$campos,$valores,$_POST[id]);
		$id=$_POST[id];
	}

	$file_type = $_FILES['cnhAnexo']['type']; //returns the mimetype

	$allowed = array("image/jpeg", "image/gif", "image/png", "application/pdf");
	if(in_array($file_type, $allowed) && $_FILES[cnhAnexo][name]!='') {

		if(!is_dir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]")){
			mkdir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]", 0777, true);
		}

		$arq=enviar(cnhAnexo,"arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]/",'CNH_'.$id.'_'.$_POST[matricula]);
		if($arq){
			atualizar('entidade',array('enti_tx_cnhAnexo'),array($arq),$id);
		}
	
	}

	$file_type = $_FILES['foto']['type']; //returns the mimetype

	$allowed = array("image/jpeg", "image/gif", "image/png");
	if(in_array($file_type, $allowed) && $_FILES[foto][name]!='') {

		if(!is_dir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]")){
			mkdir("arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]", 0777, true);
		}

		$arq=enviar(foto,"arquivos/empresa/$_POST[empresa]/motoristas/$_POST[matricula]/",'FOTO_'.$id.'_'.$_POST[matricula]);
		if($arq){
			atualizar('entidade',array('enti_tx_foto'),array($arq),$id);
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

	$_POST[id]=$id;
	index();
	exit;

}


function carrega_empresa(){
	$aEmpresa = carregar('empresa', (int)$_GET[emp]);
	if($aEmpresa[empr_nb_parametro] > 0){
	?>
		<script type="text/javascript">
			parent.document.contex_form.parametro.value='<?=$aEmpresa[empr_nb_parametro]?>';
			parent.document.contex_form.parametro.onchange();
		</script>
		<?
	}

	exit;
}

function carrega_parametro(){
	$aParam = carregar('parametro', (int)$_GET[parametro]);
	?>
	<script type="text/javascript">
		parent.document.contex_form.jornadaSemanal.value='<?=$aParam[para_tx_jornadaSemanal]?>';
		parent.document.contex_form.jornadaSabado.value='<?=$aParam[para_tx_jornadaSabado]?>';
		parent.document.contex_form.percentualHE.value='<?=$aParam[para_tx_percentualHE]?>';
		parent.document.contex_form.percentualSabadoHE.value='<?=$aParam[para_tx_percentualSabadoHE]?>';
	</script>
	<?

	exit;
}

function carrega_matricula(){
	
	$matricula = (int)$_GET[matricula];
	$id = (int)$_GET[id];

	$sql = query("SELECT * FROM entidade WHERE enti_tx_matricula = '$matricula' AND enti_nb_id != $id AND enti_tx_status = 'ativo' LIMIT 1");
	$a = carrega_array($sql);
	
	if($a[enti_nb_id] > 0){
		?>
		<script type="text/javascript">
			if(confirm("Matrícula já cadastrada, deseja atualizar o registro?")){
				parent.document.form_modifica.id.value='<?=$a[enti_nb_id]?>';
				parent.document.form_modifica.submit();
			}else{
				parent.document.contex_form.matricula.value='';
			}
		</script>
		<?
	}

	exit;
}


function busca_cep($cep){	
    $resultado = @file_get_contents('https://viacep.com.br/ws/'.urlencode($cep).'/json/');
    $arr = json_decode($resultado, true);
    return $arr;  
}

function carrega_endereco(){
	
	$arr = busca_cep($_GET[cep]);
	?>
	<script src="/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>
	<script type="text/javascript">
		parent.document.contex_form.endereco.value='<?=$arr[logradouro]?>';
		parent.document.contex_form.bairro.value='<?=$arr[bairro]?>';

		var selecionado = $('.cidade',parent.document);
		selecionado.empty();
		selecionado.append('<option value=<?=$arr[ibge]?>><?="[$arr[uf]] ".$arr[localidade]?></option>');
		selecionado.val("<?=$arr[ibge]?>").trigger("change");

	</script>
	<?

	exit;
}

function layout_motorista(){
	global $a_mod;
	cabecalho("Cadastro de Motorista");

	$data1 = new DateTime ($a_mod[enti_tx_nascimento]);
	$data2 = new DateTime (date("Y-m-d"));

	$intervalo = $data1 -> diff($data2);

	$idade = "{$intervalo->y} anos, {$intervalo->m} meses e {$intervalo->d} dias";
	
	if($a_mod[enti_tx_foto]!=''){
		$c[]=texto(icone_excluir2($a_mod[enti_nb_id], 'excluir_foto'), '<img style="width: 100%;" src="'.$a_mod[enti_tx_foto].'" />', 2);
	}
	$c[]=campo('Matrícula','matricula',$a_mod[enti_tx_matricula],1,'');
	$c[]=campo('Nome','nome',$a_mod[enti_tx_nome],3);
	$c[]=campo_data('Dt. Nascimento','nascimento',$a_mod[enti_tx_nascimento],2);
	$c[]=combo('Situação','situacao',$a_mod[enti_tx_situacao],2,array('Ativo','Inativo'));
	// $c[]=texto('Idade',$idade,4);
	

	
	$uf = array('', 'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO');
	
	$c[]=campo('CPF','cpf',$a_mod[enti_tx_cpf],2,'MASCARA_CPF');
	$c[]=campo('RG','rg',$a_mod[enti_tx_rg],2);
	$c[]=campo('Emissor RG','rgOrgao',$a_mod[enti_tx_rgOrgao],2);
	$c[]=campo_data('Data Emissão RG','rgDataEmissao',$a_mod[enti_tx_rgDataEmissao],2);
	$c[]=combo('UF RG','rgUf',$a_mod[enti_tx_rgUf],2,$uf);
	$c[]=combo('Estado Civil','civil',$a_mod[enti_tx_civil],2,array('','Casado(a)','Solteiro(a)'));
	
	$c[]=combo('Sexo','sexo',$a_mod[enti_tx_sexo],2,array('','Feminino','Masculino'));
	$c[]=campo('CEP','cep',$a_mod[enti_tx_cep],2,MASCARA_CEP,'onkeyup="carrega_cep(this.value);"');
	$c[]=campo('Endereço','endereco',$a_mod[enti_tx_endereco],4);
	$c[]=campo('Número','numero',$a_mod[enti_tx_numero],2,'MASCARA_NUMERO');
	$c[]=campo('Bairro','bairro',$a_mod[enti_tx_bairro],2);
	
	$c[]=campo('Complemento','complemento',$a_mod[enti_tx_complemento],2);
	$c[]=campo('Ponto de Referência','referencia',$a_mod[enti_tx_referencia],3);
	$c[]=combo_net('Cidade/UF','cidade',$a_mod[enti_nb_cidade],3,'cidade','','','cida_tx_uf');
	$c[]=campo('Telefone 1','fone1',$a_mod[enti_tx_fone1],2,'MASCARA_CEL');
	$c[]=campo('Telefone 2','fone2',$a_mod[enti_tx_fone2],2,'MASCARA_CEL');
	$c[]=campo('E-mail','email',$a_mod[enti_tx_email],3);
	
	$c[]=campo('Filiação Pai','pai',$a_mod[enti_tx_pai],3);
	$c[]=campo('Filiação Mãe','mae',$a_mod[enti_tx_mae],3);
	$c[]=campo('Nome do Cônjugue','conjugue',$a_mod[enti_tx_conjugue],3);
	$c[]=campo('Tipo de Operação','tipoOperacao',$a_mod[enti_tx_tipoOperacao],3);
	$c[]=arquivo('Foto (.png, .jpg)','foto',$a_mod[enti_tx_foto],4);
	$c[]=ckeditor('Observações:','obs',$a_mod[enti_tx_obs],12);
	
	$cContratual[]=combo_bd('Empresa','empresa',$a_mod[enti_nb_empresa],3,'empresa','onchange="carrega_empresa(this.value)"');
	$cContratual[]=combo('Ocupação','ocupacao',$a_mod[enti_tx_ocupacao],2,array("Motorista")); //TODO PRECISO SABER OS TIPOS DE MOTORISTA
	$cContratual[]=campo('Salário','salario',valor($a_mod[enti_tx_salario]),1,MASCARA_VALOR);
	$cContratual[]=combo('Subcontratado','subcontratado',$a_mod[enti_tx_subcontratado],2,array('','Sim','Não'));
	$cContratual[]=campo_data('Dt Admissão','admissao',$a_mod[enti_tx_admissao],2);
	$cContratual[]=campo_data('Dt Desligamento','desligamento',$a_mod[enti_tx_desligamento],2);
	
	
	$cJornada[]=combo_bd('!Parâmetros da Jornada','parametro',$a_mod[enti_nb_parametro],6,'parametro','onchange="carrega_parametro(this.value)"');
	$cJornada[]=campo_hora('Jornada Semanal (Horas/Dia)','jornadaSemanal',$a_mod[enti_tx_jornadaSemanal],3);
	$cJornada[]=campo_hora('Jornada Sábado (Horas/Dia)','jornadaSabado',$a_mod[enti_tx_jornadaSabado],3);
	// $cJornada[]=campo('Jornada Semanal (Horas)','jornadaSemanal',$a_mod[enti_tx_jornadaSemanal],3,MASCARA_NUMERO);
	// $cJornada[]=campo('Jornada Sábado (Horas)','jornadaSabado',$a_mod[enti_tx_jornadaSabado],3,MASCARA_NUMERO);
	$cJornada[]=campo('Percentual da HE(%)','percentualHE',$a_mod[enti_tx_percentualHE],3,MASCARA_NUMERO);
	$cJornada[]=campo('Percentual da HE Sábado(%)','percentualSabadoHE',$a_mod[enti_tx_percentualSabadoHE],3,MASCARA_NUMERO);

	if($a_mod[enti_nb_parametro] > 0 ){
		$aParametro = carregar('parametro', $a_mod[enti_nb_parametro]);
		if( $aParametro[para_tx_jornadaSemanal] != $a_mod[enti_tx_jornadaSemanal] ||
			$aParametro[para_tx_jornadaSabado] != $a_mod[enti_tx_jornadaSabado] ||
			$aParametro[para_tx_percentualHE] != $a_mod[enti_tx_percentualHE] ||
			$aParametro[para_tx_percentualSabadoHE] != $a_mod[enti_tx_percentualSabadoHE]){

			$ehPadrão = 'Não';
		}else{
			$ehPadrão = 'Sim';
		}
		
		$cJornada[]=texto('Convenção Padrão?', $ehPadrão, 2);
		
	}

	// echo icone_excluirCnh($a_mod[enti_nb_id], 'excluir_cnh');
	if($a_mod[enti_tx_cnhAnexo])
		$iconeExcluirCnh = icone_excluirCnh($a_mod[enti_nb_id], 'excluir_cnh');

	// exit;
	$cCNH[]=campo('N° Registro','cnhRegistro',$a_mod[enti_tx_cnhRegistro],3);
	$cCNH[]=campo_data('Validade','cnhValidade',$a_mod[enti_tx_cnhValidade],3);
	$cCNH[]=campo_data('1º Habilitação','cnhPrimeiraHabilitacao',$a_mod[enti_tx_cnhPrimeiraHabilitacao],3);
	$cCNH[]=campo('Categoria HAB','cnhCategoria',$a_mod[enti_tx_cnhCategoria],3);
	$cCNH[]=campo('Permissão','cnhPermissao',$a_mod[enti_tx_cnhPermissao],3);
	$cCNH[]=combo_net('Cidade/UF Emissão','cnhCidade',$a_mod[enti_nb_cnhCidade],3,'cidade','','','cida_tx_uf');
	$cCNH[]=campo_data('Data Emissão','cnhEmissao',$a_mod[enti_tx_cnhEmissao],3);
	$cCNH[]=campo('Pontuação','cnhPontuacao',$a_mod[enti_tx_cnhPontuacao],3);
	$cCNH[]=combo('Atividade Remunerada','cnhAtividadeRemunerada',$a_mod[enti_tx_cnhAtividadeRemunerada],3,array('','Sim','Não'));
	$cCNH[]=arquivo('CNH (.png, .jpg, .pdf)' . $iconeExcluirCnh,'cnhAnexo',$a_mod[enti_tx_cnhAnexo],4);
	$cCNH[]=campo('Observações','cnhObs',$a_mod[enti_tx_cnhObs],3);


	$b[]=botao('Gravar','cadastra_motorista','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados Cadastrais');
	linha_form($c);
	echo "<br>";
	fieldset('Dados Contratuais');
	linha_form($cContratual);
	echo "<br>";
	fieldset('CONVEÇÃO SINDICAL - JORNADA DO MOTOTRISTA PADRÃO');
	linha_form($cJornada);
	echo "<br>";
	fieldset('CARTEIRA NACIONAL DE HABILITAÇÃO');
	linha_form($cCNH);

	if($a_mod[enti_nb_userCadastro] > 0){
		$a_userCadastro = carregar('user',$a_mod[enti_nb_userCadastro]);
		$txtCadastro = "Registro inserido por $a_userCadastro[user_tx_login] às ".data($a_mod[enti_tx_dataCadastro]).".";
		$cAtualiza[] = texto("Data de Cadastro","$txtCadastro",5);
		if($a_mod[enti_nb_userAtualiza] > 0){
			$a_userAtualiza = carregar('user',$a_mod[enti_nb_userAtualiza]);
			$txtAtualiza = "Registro atualizado por $a_userAtualiza[user_tx_login] às ".data($a_mod[enti_tx_dataAtualiza],1).".";
			$cAtualiza[] = texto("Última Atualização","$txtAtualiza",5);
		}
		echo "<br>";
		linha_form($cAtualiza);
	}

	$path_parts = pathinfo( __FILE__ );
	?>
	<iframe id=frame_parametro style="display: none;"></iframe>
	<script>

		function carrega_cep(cep){
			var num = cep.replace(/[^0-9]/g,'');
			if(num.length == '8'){
				document.getElementById('frame_parametro').src='<?=$path_parts['basename']?>?acao=carrega_endereco&cep='+num;
			}
		}

		function carrega_empresa(id){
			document.getElementById('frame_parametro').src='cadastro_motorista.php?acao=carrega_empresa&emp='+id;
		}
		
		function carrega_parametro(id){
			document.getElementById('frame_parametro').src='cadastro_motorista.php?acao=carrega_parametro&parametro='+id;
		}

		//setup before functions
		let typingTimer;                //timer identifier
		let doneTypingInterval = 1000;  //time in ms (1 seconds)
		let myInput = document.getElementById('matricula');

		//on keyup, start the countdown
		myInput.addEventListener('keyup', () => {
			clearTimeout(typingTimer);
			if (myInput.value) {
				typingTimer = setTimeout(doneTyping, doneTypingInterval);
			}
		});

		//user is "finished typing," do something
		function doneTyping () {
			let matricula = myInput.value;
			document.getElementById('frame_parametro').src='cadastro_motorista.php?acao=carrega_matricula&matricula='+matricula+'&id=<?=$a_mod[enti_nb_id]?>';
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
		function remover_foto(id,acao,arquivo){
			if(confirm('Deseja realmente excluir o arquivo '+arquivo+'?')){
				document.form_excluir_arquivo.idEntidade.value=id;
				document.form_excluir_arquivo.nome_arquivo.value=arquivo;
				document.form_excluir_arquivo.acao.value=acao;
				document.form_excluir_arquivo.submit();
			}
		}

		function remover_cnh(id,acao,arquivo){
			if(confirm('Deseja realmente excluir o arquivo CNH '+arquivo+'?')){
				document.form_excluir_arquivo.idEntidade.value=id;
				document.form_excluir_arquivo.nome_arquivo.value=arquivo;
				document.form_excluir_arquivo.acao.value=acao;
				document.form_excluir_arquivo.submit();
			}
		}
	</script>


	<?

}

function icone_excluirCnh($id,$acao,$campos='',$valores='',$target='',$icone='glyphicon glyphicon-remove',$msg='Deseja excluir a CNH?'){

	$icone='class="'.$icone.'"';
	if($id > 0)
		return "<a style='text-shadow: none; color: #337ab7;' onclick='javascript:remover_cnh(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\");' > (Excluir) </a>";
	else
		return '';
	
}

function icone_excluir2($id,$acao,$campos='',$valores='',$target='',$icone='glyphicon glyphicon-remove',$msg='Deseja excluir o registro?'){
	$icone='class="'.$icone.'"';
	
	return "<a style='color:gray' onclick='javascript:remover_foto(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\");' ><spam $icone></spam>Excluir</a>";
	
}

function index(){
	cabecalho("Cadastro de Motorista");
	
	$extra = '';

	if($_POST[busca_codigo])
		$extra .=" AND enti_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_matricula])
		$extra .=" AND enti_tx_matricula = '$_POST[busca_matricula]'";
	if($_POST[busca_empresa])
		$extra .=" AND enti_nb_empresa = '$_POST[busca_empresa]'";
	if($_POST[busca_nome])
		$extra .=" AND enti_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_ocupacao])
		$extra .=" AND enti_tx_ocupacao = '$_POST[busca_ocupacao]'";
	if($_POST[busca_cpf])
		$extra .=" AND enti_tx_cpf = '$_POST[busca_cpf]'";

	if($_POST[busca_situacao] == '')
		$_POST[busca_situacao] = 'Ativo';
	if($_POST[busca_situacao] && $_POST[busca_situacao] != 'Todos')
		$extra .= " AND empr_tx_situacao = '$_POST[busca_situacao]'";
	
	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],2);
	$c[]=campo('Matrícula','busca_matricula',$_POST[busca_matricula],1);
	$c[]=campo('CPF','busca_cpf',$_POST[busca_cpf],2,'MASCARA_CPF');
	$c[]=combo_bd('!Empresa','busca_empresa',$_POST[busca_empresa],2,'empresa');
	$c[]=combo('Ocupação','busca_ocupacao',$_POST[busca_ocupacao],2,array("","Motorista")); //TODO PRECISO SABER QUAIS AS OCUPACOES
	$c[] = combo('Situação','busca_situacao',$_POST[busca_situacao],2,array('Todos','Ativo','Inativo'));
	
	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_motorista');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM entidade, empresa WHERE enti_tx_status != 'inativo' AND enti_nb_empresa = empr_nb_id AND enti_tx_tipo = 'Motorista' $extra";
	$cab = array('CÓDIGO','NOME','MATRÍCULA','CPF','EMPRESA','FONE 1','FONE 2','OCUPAÇÃO','SITUAÇÃO','','');
	$val = array('enti_nb_id','enti_tx_nome','enti_tx_matricula','enti_tx_cpf','empr_tx_nome','enti_tx_fone1','enti_tx_fone2','enti_tx_ocupacao','enti_tx_situacao','icone_modificar(enti_nb_id,modifica_motorista)',
		'icone_excluir(enti_nb_id,exclui_motorista)');

	grid($sql,$cab,$val);

	rodape();

}


?>