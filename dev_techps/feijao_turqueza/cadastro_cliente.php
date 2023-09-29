<?php
include "conecta.php";


function busca_cep($cep){	
    $resultado = @file_get_contents('https://viacep.com.br/ws/'.urlencode($cep).'/json/');
    $arr = json_decode($resultado, true);
    return $arr;  
}

function carrega_endereco(){
	
	// cabecalho("Cadastro de Aluno");
	$arr = busca_cep($_GET[cep]);
	// print_r($arr);
	
	?>
	<script src="/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>
	<script type="text/javascript">
		
		parent.document.contex_form.endereco.value='<?=$arr[logradouro]?>';
		parent.document.contex_form.bairro.value='<?=$arr[bairro]?>';

		var selecionado = $('.cidade',parent.document);
		selecionado.empty();
		selecionado.append('<option value=<?=$arr[ibge]?>><?=$arr[localidade]?></option>');
		selecionado.val("<?=$arr[ibge]?>").trigger("change");

	</script>
	<?

	exit;
}



function exclui_cliente(){
	remover('entidade',$_POST[id]);

	index();
	exit;
}


function remover_arquivo(){

	$a_arquivo = carregar('doen',$_POST[id_arquivo]);
	$url = $a_arquivo[doen_tx_pasta].'/'.$a_arquivo[doen_tx_nome];

	remover('doen',$_POST[id_arquivo]);
	// unlink($url);
	
	layout_imagens();
	exit;
}

function modifica_cliente(){
	global $a_mod;

	$a_mod = carregar('entidade',$_POST[id]);

	layout_cliente();
	exit;
}


function cadastra_cliente(){
	global $a_mod;
	
	$campos = array(enti_tx_nome,enti_tx_nascimento,enti_tx_sexo,enti_tx_civil,enti_tx_rg,enti_tx_cpf,enti_tx_endereco,enti_tx_complemento,
		enti_tx_bairro,enti_tx_cep,enti_nb_cidade,enti_tx_fone1,enti_tx_cel,enti_tx_email,enti_nb_corretor,enti_nb_conjunto,enti_tx_obs,enti_tx_tipo,enti_tx_status);
	$valores = array($_POST[nome],data($_POST[nascimento]),$_POST[sexo],$_POST[civil],$_POST[rg],$_POST[cpf],$_POST[endereco],$_POST[complemento],
		$_POST[bairro],$_POST[cep],$_POST[cidade],$_POST[fone1],$_POST[cel],$_POST[email],$_POST[corretor],$_POST[conjunto],$_POST[obs],'Cliente','ativo');

	array_push($campos, enti_tx_nomeConjugue,enti_tx_nascimentoConjugue,enti_tx_sexoConjugue,enti_tx_rgConjugue,enti_tx_cpfConjugue,
						enti_tx_possui3AnosFgts,enti_tx_usaFgts);
	array_push($valores, $_POST[nome_conjugue],data($_POST[nascimento_conjugue]),$_POST[sexo_conjugue],$_POST[rg_conjugue],$_POST[cpf_conjugue],
						$_POST[possui_3anos_fgts],$_POST[utilizar_fgts]);


	if(!$_POST[id]){
		array_push($campos, enti_nb_userCadastro,enti_tx_dataCadastro);
		array_push($valores, $_SESSION[user_nb_id],date("Y-m-d"));

		$id=inserir('entidade',$campos,$valores);
		// $id2 = ultimo_reg('entidade');
		$acao='cadastro';
	}else{
		atualizar('entidade',$campos,$valores,$_POST[id]);
		$id=$_POST[id];
	}

	if($_FILES[arquivo][name]!=''){
		if(!is_dir("arquivos/cliente/$id")){
			mkdir("arquivos/cliente/$id");
		}

		$arq=enviar('arquivo',"arquivos/cliente/$id/");
		if($arq){
			atualizar('entidade',array(enti_tx_arquivo),array($arq),$id);
		}
	}


	if ( $acao=='cadastro' ) {
		?>
		<!-- <form name="form_processo" id="form_processo" method="post" action="gestao_visita.php">
			<input type="hidden" name="id_cliente" value="<?=$id?>">
			<input type="hidden" name="acao" value="layout_visita">
		</form>
		<script type="text/javascript">
			document.getElementById('form_processo').submit();
		</script> -->
		<?php
		// exit;
	}




	$_POST[id]=$id;
	index();
	exit;

}

function redimensionar($nomearquivo,$width,$height){
	// Determina as novas dimensões
	// $width = $this->width;
	// $height = $this->height;

	// Pegamos a largura e altura originais, além do tipo de imagem
	list($width_orig, $height_orig, $tipo, $atributo) = getimagesize($nomearquivo);

	// Se largura é maior que altura, dividimos a largura determinada 
	// pela original e multiplicamos a altura pelo resultado, para manter 
	// a proporção da imagem
	if($width_orig > $height_orig){
		$height = ($width/$width_orig)*$height_orig;
		// Se altura é maior que largura, dividimos a altura determinada 
		// pela original e multiplicamos a largura pelo resultado, para manter 
		// a proporção da imagem
	} elseif($width_orig < $height_orig) {
		$width = ($height/$height_orig)*$width_orig;
	}
	// Criando a imagem com o novo tamanho
	$novaimagem = imagecreatetruecolor($width, $height);
	
	switch($tipo){
		// Se o tipo da imagem for gif
		case 1:
			// Obtém a imagem gif original
			$origem = imagecreatefromgif($nomearquivo);
			// Copia a imagem original para a imagem com novo tamanho
			imagecopyresampled($novaimagem, $origem, 0, 0, 0, 0, $width, 
			$height, $width_orig, $height_orig);
			// Envia a nova imagem gif para o lugar da antiga
			imagegif($novaimagem, $nomearquivo);
		break;

		// Se o tipo da imagem for jpg
		case 2:
			// Obtém a imagem jpg original
			$origem = imagecreatefromjpeg($nomearquivo);
			// Copia a imagem original para a imagem com novo tamanho
			imagecopyresampled($novaimagem, $origem, 0, 0, 0, 0, $width, 
			$height, $width_orig, $height_orig);
			// Envia a nova imagem jpg para o lugar da antiga
			imagejpeg($novaimagem, $nomearquivo);
		break;

		// Se o tipo da imagem for png
		case 3:
			// Obtém a imagem png original
			$origem = imagecreatefrompng($nomearquivo);
			// Copia a imagem original para a imagem com novo tamanho
			imagecopyresampled($novaimagem, $origem, 0, 0, 0, 0, $width, 
			$height, $width_orig, $height_orig);
			// Envia a nova imagem png para o lugar da antiga
			imagepng($novaimagem, $nomearquivo);
		break;
	}

	// Destrói a imagem nova criada e já salva no lugar da original
	imagedestroy($novaimagem);
	// Destrói a cópia de nossa imagem original
	imagedestroy($origem);
}

function enviar_imagens(){

	// echo "<pre>";
	// 	print_r($_FILES);
	// echo "</pre>";

	$id=intval($_POST[id]);
	$pasta_arquivos = "arquivos/cliente/$id";
	if(!is_dir($pasta_arquivos)){
		mkdir($pasta_arquivos,0775,TRUE);
	}

	$qtde = count($_FILES["imagens"]["name"]);
	$permitidos = array("jpeg", "jpg", "png", "gif","pdf");// EXTENÇÕES PERMITIDAS
	$extimagens = array("jpeg", "jpg", "png", "gif");// EXTENÇÕES DE IMAGENS
	$width  = 1024;
	$height = 980;


	// FAR UM PRIMEIRO LAÇO PARA CONFERIR SE TODAS AS IMAGENS FORAM ENVIADAS CORRETAMENTE
	for ($i=0; $i<$qtde; $i++) {
		// VERIFICA SE OCORREU ALGUN ERRO NO ENVIO DO ARQUIVO
		$erro = $_FILES['imagens']['error'][$i];
		switch($erro){
			case 1:
				$mensagem = "O tamanho do arquivo é maior que o tamanho permitido.";
			break;
			case 2:
				$mensagem = "O tamanho do arquivo é maior que o tamanho permitido.";
			break;
			case 3:
				$mensagem = "O upload do arquivo foi feito parcialmente.";
			case 4:
				$mensagem = "Não foi feito o upload de arquivo.";
			break;
		}
		if ( $mensagem!='' ) {
			set_status('ERRO: '.$mensagem);
			layout_imagens();
			exit;
		}

		$ext = pathinfo($_FILES["imagens"]["name"][$i], PATHINFO_EXTENSION);
		$ext = strtolower($ext);
		// VERIFICA SE FORAM ENVIADAS APENAS EXTENÇÕES PERMITIDAS
		if ( array_search($ext, $permitidos) === false ) {
			set_status('ATENÇÃO: Formato de arquivo não permitido!');
			layout_imagens();
			exit;
		}

	}


	for ($i=0; $i<$qtde; $i++) {
		// FAZ A PARTE DO ENVIO DAS IMAGENS

		$ext = pathinfo($_FILES["imagens"]["name"][$i], PATHINFO_EXTENSION);
		$ext = strtolower($ext);

		$nome_arquivo = ($i+1).'_'.time().'.'.$ext;
		$arquivo = $pasta_arquivos.'/'.$nome_arquivo;
		$arquivo_temporario = $_FILES['imagens']['tmp_name'][$i];

		// echo "<hr>|$arquivo|<hr>";


		if ( array_search($ext, $extimagens) !== false ) {// SE NÃO FOR IMAGEM, NÃO TENTE REDIMENSIONAR
			// Pegamos sua largura e altura originais
			list($width_orig, $height_orig) = getimagesize($arquivo_temporario);
			//Comparamos sua largura e altura originais com as desejadas
			if($width_orig > $width || $height_orig > $height){
				// Chamamos a função que redimensiona a imagem
				redimensionar($arquivo_temporario,$width,$height);
			}
		}

		if (move_uploaded_file($arquivo_temporario, $arquivo)) {
			$campos  = array(doen_nb_entidade,doen_tx_nome,doen_tx_pasta,doen_tx_descricao,doen_tx_dataCadastro,doen_nb_userCadastro,doen_tx_status);
			$valores = array($id,$nome_arquivo,$pasta_arquivos,addslashes($_POST[descricao]),date('Y-m-d H:i:s'),$_SESSION[user_nb_id],'ativo');
			inserir('doen',$campos,$valores);
		} else {
			// SE NÃO SALVAR, EXIBE UMA MENSAGEM DE ERRO
			set_status('ERRO: Falha ao salvar o arquivo no servidor!');
			index();
			exit;
		}
	}


	set_status('Imagens enviadas com sucesso!');

	layout_imagens();
	exit;
}

function layout_imagens(){
	global $conn;

	if ( intval($_POST["id"])==0 ) {
		set_status('ERRO: Código não encontrado!');
		index();
		exit;
	}

	cabecalho("Cadastro de Cliente");

	$a_imov = carregar('entidade',$_POST["id"]);

	$c[]=texto('Nome',$a_imov["enti_tx_nome"],12);


	$campo_arquivo = '<div class="col-sm-6 margin-bottom-5">
				<label><b>Arquivos(jpg, png, gif, pdf)</b></label>
				<input name="imagens[]" value="" autocomplete="off" type="file" multiple="multiple" class="form-control input-sm" >
			</div>';

	$c[]=$campo_arquivo;

	$c[]=campo('Descrição (Opcional)','descricao','',6,'',' maxlength="150" ');
	
	$b[]=botao('Gravar','enviar_imagens','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Cliente');
	linha_form($c);
	fecha_form($b);



	$sql = "SELECT * FROM doen WHERE doen_nb_entidade='$_POST[id]' AND doen_tx_status='ativo' ORDER BY doen_nb_id DESC ";
	$query=mysqli_query($conn, $sql) or die(mysql_error());
	$qtde_linhas = mysqli_num_rows($query);


	if ( $qtde_linhas>0 ) {
		?><div class="col-md-12 col-sm-12">
			<div class="portlet light ">
				<div class="row"><?php
					while( $row=mysqli_fetch_array($query) ){
						$url_arquivo = $row["doen_tx_pasta"].'/'.$row["doen_tx_nome"];
						
						$ext = pathinfo($row["doen_tx_nome"], PATHINFO_EXTENSION);
						$ext = strtolower($ext);

						if ($ext=='pdf')
							$url_icone = 'imagens/icone-pdf.png';
						else
							$url_icone = $url_arquivo;
						?><div class="col-lg-3 col-md-4 col-6">
							<div class="thumbnail">
								<img src="<?=$url_icone?>" alt="" style="max-height: 150px">
								<?php
									if ( trim($row["doen_tx_descricao"])!='' ) {
										?><figcaption class="figure-caption"><?=$row["doen_tx_descricao"]?></figcaption><?php
									} else {
										?><figcaption class="figure-caption">&nbsp;</figcaption><?php
									}
								?>								
								<div class="caption">
									<a href="<?=$url_arquivo?>" target="_blank" class="btn btn-info btn-xs" role="button">Visualizar</a>
									<a href="javascript:remover_arquivo('<?=$row["doen_nb_id"]?>');" class="btn btn-danger btn-xs" role="button">Remover</a>
								</div>
							</div>
						</div><?php
					}

				?></div>
			</div>
		</div><?php
	}


	?>

	<form name="form_excluir_arquivo" method="post" action="cadastro_cliente.php">
		<input type="hidden" name="id_arquivo" value="">
		<input type="hidden" name="id" value="<?=$_POST[id]?>">
		<input type="hidden" name="acao" value="remover_arquivo">
	</form>

	<script type="text/javascript">
		function remover_arquivo(id){
			if(confirm('Deseja realmente excluir o arquivo?')){
				document.form_excluir_arquivo.id_arquivo.value=id;
				document.form_excluir_arquivo.submit();
			}
		}
	</script>
	<?

	rodape();
}

function layout_cliente(){
	global $a_mod;
	cabecalho("Cadastro de Cliente");


	if( $_POST[id]==0 && $_SESSION[user_tx_nivel]=='Corretor' ) {
		$a_user = carregar('user',$_SESSION[user_nb_id]);
		if ( $a_user[user_nb_entidade]>0 )
			$a_mod[enti_nb_corretor] = $a_user[user_nb_entidade];
	}


	$data1 = new DateTime ($a_mod[enti_tx_nascimento]);
	$data2 = new DateTime (date("Y-m-d"));

	$intervalo = $data1 -> diff($data2);

	$idade = "{$intervalo->y} anos, {$intervalo->m} meses e {$intervalo->d} dias";

	$c[]=campo('Nome','nome',$a_mod[enti_tx_nome],4);
	// $c[]=campo('Nascimento','nascimento',data($a_mod[enti_tx_nascimento]),2,MASCARA_DATA);
	$c[] = campo('Nascimento','nascimento',data($a_mod[enti_tx_nascimento]),2,'',' maxlength="10" onkeyup="mascara_data_tmp(this);" ');
	$c[]=combo('Sexo','sexo',$a_mod[enti_tx_sexo],2,array('','Masculino','Feminino'));
	$c[]=combo('Estado Civil','civil',$a_mod[enti_tx_civil],2,array('','Casado(a)','União Estável','Solteiro(a)','Viúvo(a)'),' onchange="verifica_estado_civil(this.value)" id="civil" ');
	$c[]=campo('RG','rg',$a_mod[enti_tx_rg],2);
	
	$c[]=campo('CPF/CNPJ','cpf',$a_mod[enti_tx_cpf],2,'',' maxlength="18" onkeyup="mascara_cnpj_tmp(this);" ');
	$c[]=campo('CEP','cep',$a_mod[enti_tx_cep],2,'','maxlength="9" onkeyup="mascara_cep_tmp(this);carrega_cep(this.value);"');
	$c[]=campo('Endereço','endereco',$a_mod[enti_tx_endereco],4);
	$c[]=campo('Complemento','complemento',$a_mod[enti_tx_complemento],4);
	
	$c[]=campo('Bairro','bairro',$a_mod[enti_tx_bairro],2);
	$c[]=combo_net('Cidade','cidade',$a_mod[enti_nb_cidade],3,'cidade');
	$c[]=campo('Telefone','fone1',$a_mod[enti_tx_fone1],2,'',' maxlength="15" onkeyup="mascara_telefone_tmp(this);" ');
	$c[]=campo('Celular','cel',$a_mod[enti_tx_cel],2,'',' maxlength="15" onkeyup="mascara_telefone_tmp(this);" ');
	$c[]=campo('E-mail','email',$a_mod[enti_tx_email],3);
	
	$c[]=combo_net('Conjunto','conjunto',$a_mod[enti_nb_conjunto],3,'conjunto');
	// $c[]=combo_net('Corretor','corretor',$a_mod[enti_nb_corretor],3,'entidade','',"AND (enti_tx_tipo = 'Corretor' OR enti_tx_tipo = 'Administrador') ");
	$c[]=combo('Possui mais de 3 anos de FGTS?','possui_3anos_fgts',$a_mod[enti_tx_possui3AnosFgts],3,array('','Sim','Não'));
	$c[]=combo('Utilizar FGTS?','utilizar_fgts',$a_mod[enti_tx_usaFgts],2,array('','Sim','Não'));


	$c2[]=campo('Nome','nome_conjugue',$a_mod[enti_tx_nomeConjugue],4);
	$c2[]=campo('Nascimento','nascimento_conjugue',data($a_mod[enti_tx_nascimentoConjugue]),2,'',' maxlength="10" onkeyup="mascara_data_tmp(this);" ');
	$c2[]=combo('Sexo','sexo_conjugue',$a_mod[enti_tx_sexoConjugue],2,array('','Masculino','Feminino'));
	$c2[]=campo('RG','rg_conjugue',$a_mod[enti_tx_rgConjugue],2);
	$c2[]=campo('CPF/CNPJ','cpf_conjugue',$a_mod[enti_tx_cpfConjugue],2,'',' maxlength="18" onkeyup="mascara_cnpj_tmp(this);" ');


	$b[]=botao('Gravar','cadastra_cliente','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Cliente');
	linha_form($c);
	echo "<span id='div_estado_civil' style='display:none'>";
		echo"<br>";
		fieldset('Dados do Cônjugue');
		linha_form($c2);
	echo "</span>";

	// SERVE PARA PERGER O NOME DO ARQUIVO ATUALO
	$path_parts = pathinfo( __FILE__ );


	?>
	<iframe id=frame_cep style="display: none;"></iframe>
	<script>

		function mascara_cpf_tmp(z){
			v = z.value; 
			v=v.replace(/\D/g,"");
			v=v.replace(/(\d{3})(\d)/,"$1.$2");
			v=v.replace(/(\d{3})(\d)/,"$1.$2");
			v=v.replace(/(\d{3})(\d{1,2})$/,"$1-$2");
			z.value = v; 
		}

		function mascara_cnpj_tmp(z){
			v = z.value;

			if( v.length <= 14 ) {
				mascara_cpf_tmp(z);
				return;
			}

			v=v.replace(/\D/g,"");
			v=v.replace(/^(\d{2})(\d)/,"$1.$2");
			v=v.replace(/^(\d{2})\.(\d{3})(\d)/,"$1.$2.$3");
			v=v.replace(/\.(\d{3})(\d)/,".$1/$2");
			v=v.replace(/(\d{4})(\d)/,"$1-$2");
			z.value = v; 
		}

		function mascara_cep_tmp(z){
			v = z.value;
			v=v.replace(/D/g,"");
			v=v.replace(/^(\d{5})(\d)/,"$1-$2");
			z.value = v; 
		}

		function mascara_telefone_tmp(z){
			v = z.value; 
			v=v.replace(/\D/g,"");
			v=v.replace(/^(\d\d)(\d)/g,"($1) $2");
			//    v=v.replace(/(\d{4})(\d)/,"$1-$2");
			v=v.replace(/(\d)(\d{4})$/,"$1-$2");
			z.value = v; 
		}

		function mascara_valor_tmp(z){
			v = z.value; 
			v=v.replace(/\D/g,"");
			v=v.replace(/[0-9]{12}/,"inválido");
			v=v.replace(/(\d{1})(\d{8})$/,"$1.$2");
			v=v.replace(/(\d{1})(\d{5})$/,"$1.$2");
			v=v.replace(/(\d{1})(\d{1,2})$/,"$1,$2");
			z.value = v; 
		}

		function mascara_data_tmp(z){
			v = z.value;
			v=v.replace(/\D/g,"");
			v=v.replace(/(\d{2})(\d)/,"$1/$2");
			v=v.replace(/(\d{2})(\d)/,"$1/$2");
			z.value = v; 
		}
	</script>
	<?php
	
	fecha_form($b);

	rodape();

	?>

	<form name="form_excluir_arquivo" method="post" action="cadastro_cliente.php">
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


		function carrega_cep(cep){
			var num = cep.replace(/[^0-9]/g,'');
			if(num.length == '8'){
				document.getElementById('frame_cep').src='<?=$path_parts['basename']?>?acao=carrega_endereco&cep='+num;
			}
		}


		function verifica_estado_civil(estado){
			if ( estado=='Casado(a)' || estado=='União Estável' ) {
				$('#div_estado_civil').show();
			} else {
				$('#div_estado_civil').hide();
			}
		}

		// SERVE PARA ATUALIZAR AO ABRIR A TELA
		verifica_estado_civil(document.getElementById('civil').value);
	</script>
	<?

}

function icone_excluir2($id,$acao,$campos='',$valores='',$target='',$icone='glyphicon glyphicon-remove',$msg='Deseja excluir o registro?'){
	$icone='class="'.$icone.'"';
	
	return "<a style='color:gray' onclick='javascript:remover_arquivo(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\");' ><spam $icone></spam></a>";
	
}

function index(){

	cabecalho("Cadastro de Cliente");

	$extra = '';
	if($_POST[busca_codigo])
		$extra .=" AND enti_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND enti_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_cpf])
		$extra .=" AND enti_tx_cpf = '$_POST[busca_cpf]'";

	if($_SESSION[user_tx_nivel]=='Corretor') {
		$a_user = carregar('user',$_SESSION[user_nb_id]);
		if ( $a_user[user_nb_entidade]>0 )
			$extra .= " AND enti_nb_corretor='$a_user[user_nb_entidade]' ";
	}
	
	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],9);
	$c[]=campo('CPF','busca_cpf',$_POST[busca_cpf],2,MASCARA_CPF);
	
	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_cliente');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM entidade WHERE enti_tx_status != 'inativo' AND enti_tx_tipo = 'Cliente' $extra";
	$cab = array('CÓDIGO','NOME','CPF','','','');
	$val = array('enti_nb_id','enti_tx_nome','enti_tx_cpf','icone_modificar(enti_nb_id,layout_imagens,,,,glyphicon glyphicon-open-file)','icone_modificar(enti_nb_id,modifica_cliente)',
		'icone_excluir(enti_nb_id,exclui_cliente)');

	grid($sql,$cab,$val);

	rodape();

}


?>