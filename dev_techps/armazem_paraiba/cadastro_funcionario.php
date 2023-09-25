<?php
include "conecta.php";


function exclui_funcionario(){
	remover('entidade',$_POST[id]);

	index();
	exit;
}


function excluir_arquivo_paciente(){
	unlink("arquivos/funcionário/$_POST[id_arquivo]/$_POST[nome_arquivo]");
	$_POST[id]=$_POST[id_arquivo];
	modifica_funcionario();
	exit;
}

function modifica_funcionario(){
	global $a_mod;

	$a_mod = carregar('entidade',$_POST[id]);

	layout_funcionario();
	exit;
}


function cadastra_funcionario(){
	global $a_mod;

	$campos = array(enti_tx_nome,enti_tx_nascimento,enti_tx_cpf,enti_tx_rg,enti_tx_civil,enti_tx_sexo,enti_tx_endereco,enti_tx_numero,enti_tx_complemento,
		enti_tx_bairro,enti_nb_cidade,enti_tx_cep,enti_tx_fone1,enti_tx_fone2,enti_tx_email,enti_tx_ocupacao,enti_tx_salario,enti_tx_cargahoraria,enti_tx_obs,
		enti_tx_tipo,enti_nb_userCadastro,enti_tx_dataCadastro,enti_tx_status,enti_tx_matricula,enti_tx_empresaCnpj);
	$valores = array($_POST[nome],$_POST[nascimento],$_POST[cpf],$_POST[rg],$_POST[civil],$_POST[sexo],$_POST[endereco],$_POST[numero],$_POST[complemento],
		$_POST[bairro],$_POST[cidade],$_POST[cep],$_POST[fone1],$_POST[fone2],$_POST[email],$_POST[ocupacao],valor($_POST[salario]),$_POST[cargahoraria],$_POST[obs],
		'Funcionário',$_SESSION[user_nb_id],date("Y-m-d"),'ativo',$_POST[matricula],$_POST[empresa]);

	if(!$_POST[id]){
		$id=inserir('entidade',$campos,$valores);

		// ADICIONA O USUARIO AO INSERIR NOVO FUNCIONARIO (USUARIO E SENHA = CPF) - PREENCHER A VARIAVEL USER_NB_ENTIDADE
		$campos = array(user_tx_nome,user_tx_login,user_tx_nivel,user_tx_senha,user_tx_status, user_nb_entidade);
		$valores = array($_POST[nome],$_POST[cpf],$_POST[nivel],md5($_POST[cpf]),'ativo', $id);
		inserir('user',$campos,$valores);

	}else{
		atualizar('entidade',$campos,$valores,$_POST[id]);
		$id=$_POST[id];
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

function layout_funcionario(){
	global $a_mod;
	cabecalho("Cadastro de Funcionário");

	$data1 = new DateTime ($a_mod[enti_tx_nascimento]);
	$data2 = new DateTime (date("Y-m-d"));

	$intervalo = $data1 -> diff($data2);

	$idade = "{$intervalo->y} anos, {$intervalo->m} meses e {$intervalo->d} dias";

	$c[]=campo('Nome','nome',$a_mod[enti_tx_nome],6);
	$c[]=campo_data('Dt. Nascimento','nascimento',$a_mod[enti_tx_nascimento],2);
	$c[]=texto('Idade',$idade,4);
	$c[]=campo('CPF','cpf',$a_mod[enti_tx_cpf],2,'MASCARA_CPF');
	$c[]=campo('RG','rg',$a_mod[enti_tx_rg],2);
	$c[]=combo('Estado Civil','civil',$a_mod[enti_tx_civil],2,array('','Casado(a)','Solteiro(a)'));
	$c[]=combo('Sexo','sexo',$a_mod[enti_tx_sexo],2,array('','Feminino','Masculino'));
	$c[]=campo('Endereço','endereco',$a_mod[enti_tx_endereco],4);
	$c[]=campo('Número','numero',$a_mod[enti_tx_numero],2,'MASCARA_NUMERO');
	$c[]=campo('Complemento','complemento',$a_mod[enti_tx_complemento],3);
	$c[]=campo('Bairro','bairro',$a_mod[enti_tx_bairro],2);
	$c[]=combo_net('Cidade','cidade',$a_mod[enti_nb_cidade],3,'cidade');
	$c[]=campo('CEP','cep',$a_mod[enti_tx_cep],2,MASCARA_CEP);
	$c[]=campo('Telefone 1','fone1',$a_mod[enti_tx_fone1],2,'MASCARA_CEL');
	$c[]=campo('Telefone 2','fone2',$a_mod[enti_tx_fone2],2,'MASCARA_CEL');
	$c[]=campo('E-mail','email',$a_mod[enti_tx_email],3);
	$c[]=ckeditor('Observação:','obs',$a_mod[enti_tx_obs],12);
	
	$cContratual[]=combo_bd('Empresa','empresa',$a_mod[enti_tx_empresaCnpj],3,'empresa');
	$cContratual[]=combo('Ocupação','ocupacao',$a_mod[enti_tx_ocupacao],2,array("","Administrador","Funcionário"));
	$cContratual[]=campo('Salário','salario',valor($a_mod[enti_tx_salario]),2,MASCARA_VALOR);
	$cContratual[]=campo('Carga Horária(hs)','cargahoraria',$a_mod[enti_tx_cargahoraria],2,MASCARA_NUMERO);
	$cContratual[]=campo('Matrícula','matricula',$a_mod[enti_tx_matricula],2);

	$b[]=botao('Gravar','cadastra_funcionario','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Funcionário');
	linha_form($c);
	fieldset('Dados Contratuais');
	linha_form($cContratual);


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

	<form name="form_excluir_arquivo" method="post" action="cadastro_funcionario.php">
		<input type="hidden" name="id_arquivo" value="">
		<input type="hidden" name="nome_arquivo" value="">
		<input type="hidden" name="acao" value="">
	</form>

	<script type="text/javascript">
		function remover_arquivo(id,acao,arquivo){
			if(confirm('Deseja realmente excluir o arquivo '+arquivo+'?')){
				document.form_excluir_arquivo.id_arquivo.value=id;
				document.form_excluir_arquivo.nome_arquivo.value=arquivo;
				document.form_excluir_arquivo.acao.value=acao;
				document.form_excluir_arquivo.submit();
			}
		}
	</script>
	<?

}

function icone_excluir2($id,$acao,$campos='',$valores='',$target='',$icone='glyphicon glyphicon-remove',$msg='Deseja excluir o registro?'){
	$icone='class="'.$icone.'"';
	
	return "<a style='color:gray' onclick='javascript:remover_arquivo(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\");' ><spam $icone></spam></a>";
	
}

function index(){
	cabecalho("Cadastro de Funcionário");

	if($_POST[busca_codigo])
		$extra .=" AND enti_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_matricula])
		$extra .=" AND enti_tx_matricula = '$_POST[busca_matricula]'";
	if($_POST[busca_empresa])
		$extra .=" AND enti_tx_empresaCnpj = '$_POST[busca_empresa]'";
	if($_POST[busca_nome])
		$extra .=" AND enti_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_ocupacao])
		$extra .=" AND enti_tx_ocupacao = '$_POST[busca_ocupacao]'";
	
	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],3);
	$c[]=campo('Matrícula','busca_matricula',$_POST[busca_matricula],2);
	$c[]=combo_bd('!Empresa','busca_empresa',$_POST[busca_empresa],3,'empresa');
	$c[]=combo('Ocupação','busca_ocupacao',$_POST[busca_ocupacao],3,array("","Administrador","Funcionário"));
	
	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_funcionario');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM entidade, empresa WHERE enti_tx_status != 'inativo' AND enti_tx_empresaCnpj = empr_nb_id AND enti_tx_tipo = 'Funcionário' $extra";
	$cab = array('CÓDIGO','NOME','MATRÍCULA','EMPRESA','FONE 1','FONE 2','OCUPAÇÃO','','');
	$val = array('enti_nb_id','enti_tx_nome','enti_tx_matricula','empr_tx_nome','enti_tx_fone1','enti_tx_fone2','enti_tx_ocupacao','icone_modificar(enti_nb_id,modifica_funcionario)',
		'icone_excluir(enti_nb_id,exclui_funcionario)');

	grid($sql,$cab,$val);

	rodape();

}


?>