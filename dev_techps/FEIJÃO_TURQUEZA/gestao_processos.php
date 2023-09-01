<?php
include "conecta.php";



function remover_arquivo(){

	$a_arquivo = carregar('dopr',$_POST[id_arquivo]);
	$url = $a_arquivo[dopr_tx_pasta].'/'.$a_arquivo[dopr_tx_nome];

	remover('dopr',$_POST[id_arquivo]);
	// unlink($url);
	
	layout_documentos();
	exit;
}

function remover_imagem(){

	// print_r($_POST);
	// exit;

	$a_imagem = carregar('fopr',$_POST[id_imagem]);
	$url = $a_imagem[fopr_tx_pasta].'/'.$a_imagem[fopr_tx_nome];

	remover('fopr',$_POST[id_imagem]);
	// unlink($url);
	
	atualiza_processo();
	exit;
}

function exclui_processo(){
	remover('processo',$_POST[id]);

	index();
	exit;
}

function exclui_atualizacao(){

	remover('atpr',$_POST[id]);

	$_POST[id] = $_POST[id2];

	atualiza_processo();
	exit;
}

function modifica_processo(){
	global $a_mod;

	$a_mod = carregar('processo',$_POST[id]);

	layout_processo();
	exit;
}

function ver_documento(){

	$a_atpr = carregar('atpr',$_POST[id]);

	echo "<script>window.location.href='$a_atpr[atpr_tx_pastadoc].'/'.$a_atpr[atpr_tx_nomedoc]'</script>";
	exit;
}

function cadastra_atualizacao(){
	global $a_mod;

	$campos = array(atpr_tx_nome,atpr_nb_processo,atpr_tx_dataCadastro,atpr_nb_userCadastro,atpr_tx_status);
	$valores = array($_POST[atualizacao],$_POST[id],date("Y-m-d H:i:s"),$_SESSION[user_nb_id],'ativo');

	inserir('atpr',$campos,$valores);	

	if ( $_POST[id]>0 ) {
		atualizar('processo',array(proc_tx_situacao),array($_POST[nova_situacao]),$_POST[id]);
	}

	
	atualiza_processo();
	exit;

}

function cadastra_processo(){
	global $a_mod;

	$campos = array(proc_tx_nome,proc_nb_entidade,proc_nb_finalidade,proc_nb_banco,proc_nb_imovel,proc_tx_obs,proc_tx_dataCadastro,proc_nb_userCadastro,
		proc_nb_corretor,proc_tx_situacao,proc_tx_status);
	$valores = array($_POST[descricao],$_POST[cliente],$_POST[finalidade],$_POST[banco],$_POST[imovel],$_POST[obs],date("Y-m-d"),$_SESSION[user_nb_id],
		$_POST[corretor],'Em Andamento','ativo');

	if(!$_POST[id]){
		$id=inserir('processo',$campos,$valores);		
	}else{
		atualizar('processo',$campos,$valores,$_POST[id]);
		$id=$_POST[id];
	}
	
	$_POST[id]=$id;
	index();
	exit;

}

function layout_processo(){
	global $a_mod;
	cabecalho("Gestão de Processos");

	if ( intval($_POST[id_cliente])>0 ) {
		// SERVE PARA SELECIONAR O CLIENTE DE FORMA AUTOMÁTICA APÓS REALIZAR O CADASTRO DELE
		$a_mod[proc_nb_entidade] = intval($_POST[id_cliente]);
	}


	if( $_POST[id]==0 && $_SESSION[user_tx_nivel]=='Corretor' ) {
		$a_user = carregar('user',$_SESSION[user_nb_id]);
		if ( $a_user[user_nb_entidade]>0 ){
			$a_mod[proc_nb_corretor] = $a_user[user_nb_entidade];
			$extra_cliente = " AND enti_nb_corretor='$a_user[user_nb_entidade]' ";
		}
	}


	$c[]=combo_net('Cliente','cliente',$a_mod[proc_nb_entidade],4,'entidade','',"AND enti_tx_tipo = 'Cliente' $extra_cliente ");
	$c[]=combo_bd('!Banco','banco',$a_mod[proc_nb_banco],2,'banco');
	$c[]=combo_bd('Finalidade','finalidade',$a_mod[proc_nb_finalidade],3,'finalidade');
	// $c[]=combo_net('Corretor','corretor',$a_mod[proc_nb_corretor],3,'entidade','',"AND (enti_tx_tipo = 'Corretor' OR enti_tx_tipo = 'Administrador') ");
	
	$c[]=ckeditor('Descrição','descricao',$a_mod[proc_tx_nome],12);
	// $c[]=combo_net('Imóvel','imovel',$a_mod[proc_nb_imovel],6,'imovel');
	$c[]=campo('Observação','obs',$a_mod[proc_tx_obs],6);							
			
	$b[]=botao('Gravar','cadastra_processo','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Imóvel');

	linha_form($c);
	fecha_form($b);

	rodape();

	?>

	<form name="form_excluir_arquivo" method="post" action="cadastro_imovel.php">
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

function layout_atualizacao(){
	global $a_mod;
	cabecalho("Gestão de Processos");

	$a_proc = carregar('processo',$_POST[id]);
	$a_clie = carregar('entidade',$a_proc[proc_nb_entidade]);
	$a_banc = carregar('banco',$a_proc[proc_nb_banco]);
	$a_imov = carregar('imovel',$a_proc[proc_nb_imovel]);
	// $a_atpr = carregar('atpr',$a_proc[proc_nb_id]);

	$c[]=texto('Código',$a_proc[proc_nb_id],1);
	$c[]=texto('Cliente',$a_clie[enti_tx_nome],3);
	$c[]=texto('Finalidade',$a_proc[proc_tx_finalidade],2);
	$c[]=texto('Banco',$a_banc[banc_tx_nome],2);
	$c[]=texto('Imóvel',$a_imov[imov_tx_nome],2);
	$c[]=texto('Situação',$a_proc[proc_tx_situacao],2);
	$c[]=texto('Descrição do Processo',$a_proc[proc_tx_nome],12);
	$c[]=texto('Observação',$a_proc[proc_tx_obs],6);
	$c[]=ckeditor('Atualização','atualizacao',$_POST[atualizacao],12);
	$c[]=combo('Nova Situação','nova_situacao',$a_proc[proc_tx_situacao],2,array("Em Andamento","Reprovado","Encerrado"));				
			
	$b[]=botao('Gravar','cadastra_atualizacao','id',$_POST[id]);
	$b[]=botao('Voltar','atualiza_processo','id',$_POST[id]);

	abre_form('Dados do Processo');

	linha_form($c);
	fecha_form($b);

	rodape();

	?>

	<form name="form_excluir_arquivo" method="post" action="cadastro_imovel.php">
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
	$pasta_arquivos = "arquivos/processo/$id";
	if(!is_dir($pasta_arquivos)){
		mkdir($pasta_arquivos,0775,TRUE);
	}

	$qtde = count($_FILES["imagens"]["name"]);
	$permitidos = array("jpeg", "jpg", "png", "gif");// EXTENÇÕES PERMITIDAS
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
			layout_documentos();
			exit;
		}

		$ext = pathinfo($_FILES["imagens"]["name"][$i], PATHINFO_EXTENSION);
		$ext = strtolower($ext);
		// VERIFICA SE FORAM ENVIADAS APENAS EXTENÇÕES PERMITIDAS
		if ( array_search($ext, $permitidos) === false ) {
			set_status('ATENÇÃO: Formato de arquivo não permitido!');
			layout_documentos();
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


		// Pegamos sua largura e altura originais
		list($width_orig, $height_orig) = getimagesize($arquivo_temporario);
		//Comparamos sua largura e altura originais com as desejadas
		if($width_orig > $width || $height_orig > $height){
			// Chamamos a função que redimensiona a imagem
			redimensionar($arquivo_temporario,$width,$height);
		}

		if (move_uploaded_file($arquivo_temporario, $arquivo)) {
			$campos  = array(fopr_nb_processo,fopr_tx_nome,fopr_tx_pasta,fopr_tx_dataCadastro,fopr_nb_userCadastro,fopr_tx_status);
			$valores = array($id,$nome_arquivo,$pasta_arquivos,date('Y-m-d H:i:s'),$_SESSION[user_nb_id],'ativo');
			inserir('fopr',$campos,$valores);
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

function enviar_documentos(){

	// print_r($_FILES);
	// exit;

	$id=intval($_POST[id]);
	$pasta_arquivos = "arquivos/processo/$id/documentos";
	if(!is_dir($pasta_arquivos)){
		mkdir($pasta_arquivos,0775,TRUE);
	}

	$qtde = count($_FILES["documentos"]["name"]);
	$permitidos = array("docx", "doc", "pdf", "xls", "xlsx");// EXTENÇÕES PERMITIDAS

	// FAR UM PRIMEIRO LAÇO PARA CONFERIR SE TODOS OS DOCUMENTOS FORAM ENVIADAS CORRETAMENTE
	for ($i=0; $i<$qtde; $i++) {
		// VERIFICA SE OCORREU ALGUN ERRO NO ENVIO DO ARQUIVO
		$erro = $_FILES['documentos']['error'][$i];
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
			layout_documentos();
			exit;
		}

		$ext = pathinfo($_FILES["documentos"]["name"][$i], PATHINFO_EXTENSION);
		$ext = strtolower($ext);
		// VERIFICA SE FORAM ENVIADAS APENAS EXTENÇÕES PERMITIDAS
		if ( array_search($ext, $permitidos) === false ) {
			set_status('ATENÇÃO: Formato de arquivo não permitido!');
			layout_documentos();
			exit;
		}

	}


	for ($i=0; $i<$qtde; $i++) {
		// FAZ A PARTE DO ENVIO DOS DOCUMENTOS

		$ext = pathinfo($_FILES["documentos"]["name"][$i], PATHINFO_EXTENSION);
		$ext = strtolower($ext);
		
		$nome_arquivo = ($i+1).'_'.time().'.'.$ext;
		$arquivo = $pasta_arquivos.'/'.$nome_arquivo;
		$arquivo_temporario = $_FILES['documentos']['tmp_name'][$i];

		if (move_uploaded_file($arquivo_temporario, $arquivo)) {
			$campos  = array(atpr_nb_processo,atpr_tx_nome,atpr_tx_nomedoc,atpr_tx_pastadoc,atpr_tx_dataCadastro,atpr_nb_userCadastro,atpr_tx_status);
			$valores = array($id,$_POST['descricao'],$nome_arquivo,$pasta_arquivos,date('Y-m-d H:i:s'),$_SESSION[user_nb_id],'ativo');
			inserir('atpr',$campos,$valores);
		} else {
			// SE NÃO SALVAR, EXIBE UMA MENSAGEM DE ERRO
			set_status('ERRO: Documento no servidor!');
			index();
			exit;
		}
	}


	set_status('Documentos enviados com sucesso!');

	atualiza_processo();
	exit;
}

function verifica_doc($id_atpr){
	
	$a_atpr = carregar('atpr',$id_atpr);

	$url_arquivo = $a_atpr[atpr_tx_pastadoc]."/".$a_atpr[atpr_tx_nomedoc];

	$icone = '';
	
	if ( strtolower($a_atpr[atpr_tx_nomedoc])!='' ) {
		$icone = icone_modificar($id_atpr,'ver_documento','','','_blank','glyphicon glyphicon-eye-open',$url_arquivo);

	}

	return $icone;
}

function atualiza_processo(){
	cabecalho("Gestão de Processos");
	
	$a_proc = carregar('processo',$_POST[id]);
	$a_clie = carregar('entidade',$a_proc[proc_nb_entidade]);
	$a_banc = carregar('banco',$a_proc[proc_nb_banco]);
	$a_imov = carregar('imovel',$a_proc[proc_nb_imovel]);

	$c[]=texto('Código',$a_proc[proc_nb_id],1);
	$c[]=texto('Cliente',$a_clie[enti_tx_nome],3);
	$c[]=texto('Finalidade',$a_proc[proc_tx_finalidade],2);
	$c[]=texto('Banco',$a_banc[banc_tx_nome],2);
	$c[]=texto('Imóvel',$a_imov[imov_tx_nome],2);
	$c[]=texto('Situação',$a_proc[proc_tx_situacao],2);
	$c[]=texto('Descrição do Processo',$a_proc[proc_tx_nome],12);
	$c[]=texto('Observação',$a_proc[proc_tx_obs],6);
				
	$b[]=botao('Voltar','index');
	$b[]=botao('Inserir Atualização','layout_atualizacao','id',$_POST[id]);
	$b[]=botao('Inserir Documento','layout_documentos','id',$_POST[id]);
	$b[]=botao('Inserir Imagem','layout_imagens','id',$_POST[id]);

	abre_form('Dados do Processo');
	linha_form($c);
	fecha_form($b);
	
	$sql = "SELECT * FROM atpr,user WHERE atpr_tx_status != 'inativo' AND atpr_nb_userCadastro = user_nb_id AND atpr_nb_processo = '$_POST[id]'";
	$cab = array('CÓDIGO','DESCRIÇÃO','DATA','USUÁRIO','','');
	$val = array('atpr_nb_id','atpr_tx_nome','data(atpr_tx_dataCadastro,1)','user_tx_nome','verifica_doc(atpr_nb_id)','icone_excluir(atpr_nb_id,exclui_atualizacao,id2,'.$_POST[id].')');		
	grid($sql,$cab,$val,'','',0,'desc');

	$sql2 = query("SELECT * FROM fopr WHERE fopr_nb_processo='$_POST[id]' AND fopr_tx_status='ativo'");

	if ( num_linhas($sql2) > 0 ) {
	?>
	<div class="col-md-12 col-sm-12">
		<div class="portlet light" style="border:none;">
			<h5 style="position: relative;margin: 0 0 1% 0;padding: 0.5% 0 1% 0;width: 100%;float: left;font-weight: bold;border-bottom: 1px solid silver;">IMAGENS ANEXADAS AO PROCESSO</h5>
			<div class="row">
				<?

				while($a_img = carrega_array($sql2)){
					$url = $a_img[fopr_tx_pasta].'/'.$a_img[fopr_tx_nome];

				?>
				<div class="col-lg-3 col-md-4 col-6">
					<div class="thumbnail">
						<img src="<?=$url?>" alt=""/>
						<div class="caption">
							<a href="<?=$url?>" target="_blank" class="btn btn-info btn-xs" role="button">Visualizar</a>
							<a href="javascript:remover_imagem('<?=$a_img[fopr_nb_id]?>');" class="btn btn-danger btn-xs" role="button">Remover</a>
						</div>
					</div>
				</div>
				<? } ?>
			</div>
		</div>
	</div>
	<?
	}

	?>

	<form name="form_excluir_imagem" method="post" action="gestao_processos.php">
		<input type="hidden" name="id_imagem" value="">
		<input type="hidden" name="id" value="<?=$_POST[id]?>">
		<input type="hidden" name="acao" value="remover_imagem">
	</form>

	<script type="text/javascript">
		function remover_imagem(id){
			if(confirm('Deseja realmente excluir o arquivo?')){
				document.form_excluir_imagem.id_imagem.value=id;
				document.form_excluir_imagem.submit();
			}
		}
	</script>
	<?

	rodape();

}

function layout_documentos(){
	global $conn;

	if ( intval($_POST[id])==0 ) {
		set_status('ERRO: Código não encontrado!');
		index();
		exit;
	}

	cabecalho("Gestão de Processos");

	$a_proc = carregar('processo',$_POST[id]);

	$c[]=texto('Descrição do Processo',$a_proc[proc_tx_nome],4);
	$c[]=campo('Descrição do(s) Documento(s)','descricao','',4);


	$campo_arquivo = '<div class="col-sm-4 margin-bottom-5">
				<label><b>Documento (.pdf/.doc/.docx)</b></label>
				<input name="documentos[]" value="" autocomplete="off" type="file" multiple="multiple" class="form-control input-sm" >
			</div>';

	$c[]=$campo_arquivo;


	$b[]=botao('Gravar','enviar_documentos','id',$_POST[id]);
	$b[]=botao('Voltar','atualiza_processo','id',$_POST[id]);

	abre_form('Dados do Processo');
	linha_form($c);
	fecha_form($b);

	rodape();
}

function layout_imagens(){
	global $conn;

	if ( intval($_POST[id])==0 ) {
		set_status('ERRO: Código não encontrado!');
		index();
		exit;
	}

	cabecalho("Gestão de Processos");

	$a_proc = carregar('processo',$_POST[id]);

	$c[]=texto('Descrição',$a_proc[proc_tx_nome],6);


	$campo_arquivo = '<div class="col-sm-6 margin-bottom-5">
				<label><b>Imagens </b></label>
				<input name="imagens[]" value="" autocomplete="off" type="file" multiple="multiple" class="form-control input-sm" >
			</div>';

	$c[]=$campo_arquivo;


	$b[]=botao('Gravar','enviar_imagens','id',$_POST[id]);
	$b[]=botao('Voltar','atualiza_processo','id',$_POST[id]);

	abre_form('Dados do Processo');
	linha_form($c);
	fecha_form($b);



	$sql = "SELECT * FROM fopr WHERE fopr_nb_processo='$_POST[id]' AND fopr_tx_status='ativo' ";
	$query=mysqli_query($conn, $sql) or die(mysql_error());
	$qtde_linhas = mysqli_num_rows($query);


	if ( $qtde_linhas>0 ) {
		?><div class="col-md-12 col-sm-12">
			<div class="portlet light ">
				<div class="row"><?php
					while( $row=mysqli_fetch_array($query) ){
						$url = $row[fopr_tx_pasta].'/'.$row[fopr_tx_nome];
						?><div class="col-lg-3 col-md-4 col-6">
							<div class="thumbnail">
								<img src="<?=$url?>" alt="">
								<div class="caption">
									<a href="<?=$url?>" target="_blank" class="btn btn-info btn-xs" role="button">Visualizar</a>
									<a href="javascript:remover_arquivo('<?=$row[fopr_nb_id]?>');" class="btn btn-danger btn-xs" role="button">Remover</a>
								</div>
							</div>
						</div><?php
					}

				?></div>
			</div>
		</div><?php
	}


	?>

	<form name="form_excluir_arquivo" method="post" action="gestao_processos.php">
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

function icone_excluir2($id,$acao,$campos='',$valores='',$target='',$icone='glyphicon glyphicon-remove',$msg='Deseja excluir o registro?'){
	$icone='class="'.$icone.'"';
	
	return "<a style='color:gray' onclick='javascript:remover_arquivo(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\");' ><spam $icone></spam></a>";
	
}

function index(){
	cabecalho("Gestão de Processos");

	$extra = '';
	if($_POST[busca_codigo])
		$extra .=" AND proc_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND proc_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_finalidade])
		$extra .=" AND proc_nb_finalidade = '$_POST[busca_finalidade]'";
	if($_POST[busca_cliente])
		$extra .=" AND proc_nb_entidade = '$_POST[busca_cliente]'";
	if($_POST[busca_situacao])
		$extra .=" AND proc_tx_situacao = '$_POST[busca_situacao]'";


	if($_SESSION[user_tx_nivel]=='Corretor') {
		$a_user = carregar('user',$_SESSION[user_nb_id]);
		if ( $a_user[user_nb_entidade]>0 )
			$extra .= " AND proc_nb_corretor='$a_user[user_nb_entidade]' ";
	}
		
	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=combo_net('Cliente','busca_cliente',$_POST[busca_cliente],4,'entidade','',"AND enti_tx_tipo = 'Cliente'");
	$c[]=campo('Descrição','busca_nome',$_POST[busca_nome],3);
	$c[]=combo_bd('!Finalidade','busca_finalidade',$_POST[busca_finalidade],2,'finalidade');
	$c[]=combo('Situação','busca_situacao',$_POST[busca_situacao],2,array("","Em Andamento","Encerrado"));
				
	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_processo');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM processo,entidade,finalidade WHERE proc_tx_status != 'inativo' AND proc_nb_finalidade = fina_nb_id AND proc_nb_entidade = enti_nb_id $extra";
	$cab = array('CÓDIGO','DESCRIÇÃO','CLIENTE','FINALIDADE','SITUAÇÃO','','','');
	$val = array('proc_nb_id','proc_tx_nome','enti_tx_nome','fina_tx_nome','proc_tx_situacao','icone_modificar(proc_nb_id,atualiza_processo,,,,glyphicon glyphicon-refresh)','icone_modificar(proc_nb_id,modifica_processo)','icone_excluir(proc_nb_id,exclui_processo)');		
	
	grid($sql,$cab,$val);

	rodape();

}


?>