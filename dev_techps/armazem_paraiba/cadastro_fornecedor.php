<?php
include "conecta.php";


function exclui_fornecedor(){
	remover('entidade',$_POST[id]);

	index();
	exit;
}


function excluir_arquivo_fornecedor(){
	unlink("arquivos/fornecedor/$_POST[id_arquivo]/$_POST[nome_arquivo]");
	$_POST[id]=$_POST[id_arquivo];
	modifica_fornecedor();
	exit;
}

function modifica_fornecedor(){
	global $a_mod;

	$a_mod = carregar('entidade',$_POST[id]);

	layout_fornecedor();
	exit;
}


function cadastra_fornecedor(){
	global $a_mod;

	$campos = array(enti_tx_nome,enti_tx_cpf,enti_tx_endereco,enti_tx_numero,enti_tx_complemento,enti_tx_bairro,enti_nb_cidade,enti_tx_cep,enti_tx_encarregado,
		enti_tx_fone1,enti_tx_fone2,enti_tx_email,enti_tx_site,enti_tx_obs,enti_tx_tipo,enti_nb_userCadastro,enti_tx_dataCadastro,enti_tx_status);
	$valores = array($_POST[nome],$_POST[cpf],$_POST[endereco],$_POST[numero],$_POST[complemento],$_POST[bairro],$_POST[cidade],$_POST[cep],$_POST[encarregado],
		$_POST[fone1],$_POST[fone2],$_POST[email],$_POST[site],$_POST[obs],'Fornecedor',$_SESSION[user_nb_id],date("Y-m-d"),'ativo');

	if(!$_POST[id]){
		$id=inserir('entidade',$campos,$valores);
		$id2 = ultimo_reg('entidade');
	}else{
		atualizar('entidade',$campos,$valores,$_POST[id]);
		$id=$_POST[id];
	}

	if($_FILES[arquivo][name]!=''){
		if(!is_dir("arquivos/fornecedor/$id")){
			mkdir("arquivos/fornecedor/$id");
		}

		$arq=enviar('arquivo',"arquivos/fornecedor/$id/");
		if($arq){
			atualizar('entidade',array(enti_tx_arquivo),array($arq),$id);
		}
	}

	$_POST[id]=$id;
	index();
	exit;

}

function layout_fornecedor(){
	global $a_mod;
	cabecalho("Cadastro de Fornecedor");

	$c[]=campo('Nome','nome',$a_mod[enti_tx_nome],4);
	$c[]=campo('CPF/CNPJ','cpf',$a_mod[enti_tx_cpf],2,MASCARA_CPF);
	$c[]=campo('Endereço','endereco',$a_mod[enti_tx_endereco],4);
	$c[]=campo('Número','numero',$a_mod[enti_tx_numero],2,MASCARA_NUMERO);
	$c[]=campo('Complemento','complemento',$a_mod[enti_tx_complemento],3);
	$c[]=campo('Bairro','bairro',$a_mod[enti_tx_bairro],2);
	$c[]=combo_net('Cidade','cidade',$a_mod[enti_nb_cidade],3,'cidade');
	$c[]=campo('CEP','cep',$a_mod[enti_tx_cep],2,MASCARA_CEP);
	$c[]=campo('Encarregado','encarregado',$a_mod[enti_tx_encarregado],2);
	$c[]=campo('Telefone 1','fone1',$a_mod[enti_tx_fone1],2,MASCARA_CEL);
	$c[]=campo('Telefone 2','fone2',$a_mod[enti_tx_fone2],2,MASCARA_CEL);
	$c[]=campo('E-mail','email',$a_mod[enti_tx_email],5);
	$c[]=campo('Site','site',$a_mod[enti_tx_site],3);
	$c[]=ckeditor('Observação','obs',$a_mod[enti_tx_obs],12);
	$c[]=arquivo('Arquivo','arquivo','',3);
	
	
	$b[]=botao('Gravar','cadastra_fornecedor','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Fornecedor');
	linha_form($c);

	if($a_mod[enti_nb_id]>0 && $a_mod[enti_tx_arquivo] != ''){
		echo "<br>";
		echo "<div class=portlet-title>";
		echo"<span class='caption-subject font-dark bold uppercase' style='font-size:16px'> ARQUIVOS</span>";
		echo"<hr>";
		echo"</div>";
		if ($handle = opendir("arquivos/fornecedor/$a_mod[enti_nb_id]")) {

			while (false !== ($arquivo = readdir($handle))) {

				if ($arquivo != "." && $arquivo != "..") {

					$c2[] = texto("Arquivo ".++$contador,"<a href='arquivos/fornecedor/$a_mod[enti_nb_id]/$arquivo' target=_blank>".$arquivo."</a> <a class='glyphicon glyphicon-remove' onclick='javascript:remover_arquivo(\"$a_mod[enti_nb_id]\",\"excluir_arquivo_fornecedor\",\"$arquivo\")'></a>",6);
				}
			}

			closedir($handle);
			linha_form($c2);
			
		}

	}

	fecha_form($b);




	rodape();

	?>

	<form name="form_excluir_arquivo" method="post" action="cadastro_fornecedor.php">
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
	cabecalho("Cadastro de Fornecedor");

	if($_POST[busca_codigo])
		$extra .=" AND enti_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND enti_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_cpf])
		$extra .=" AND enti_tx_cpf LIKE '%$_POST[busca_cpf]%'";
		
	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],9);
	$c[]=campo('CPF/CNPJ','busca_cpf',$_POST[busca_cpf],2,MASCARA_CPF);
		
	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_fornecedor');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM entidade WHERE enti_tx_status != 'inativo' AND enti_tx_tipo = 'Fornecedor' $extra";
	$cab = array('CÓDIGO','NOME','CPF/CNPJ','','');
	$val = array('enti_nb_id','enti_tx_nome','enti_tx_cpf','icone_modificar(enti_nb_id,modifica_fornecedor)',
		'icone_excluir(enti_nb_id,exclui_fornecedor)');

	grid($sql,$cab,$val);

	rodape();

}


?>