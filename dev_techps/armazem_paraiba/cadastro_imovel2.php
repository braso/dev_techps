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



function remover_arquivo(){

	$a_arquivo = carregar('foim',$_POST[id_arquivo]);
	$url = $a_arquivo[foim_tx_pasta].'/'.$a_arquivo[foim_tx_nome];

	remover('foim',$_POST[id_arquivo]);
	// unlink($url);
	
	layout_imagens();
	exit;
}


function exclui_imovel(){
	remover('imovel',$_POST[id]);

	index();
	exit;
}

function modifica_imovel(){
	global $a_mod;

	$a_mod = carregar('imovel',$_POST[id]);

	layout_imovel();
	exit;
}


function cadastra_imovel(){
	global $a_mod;

	$campos = array(imov_tx_nome,imov_tx_financiavel,imov_tx_tipo,imov_tx_finalidade,imov_tx_endereco,imov_tx_bairro,imov_tx_cep,imov_nb_cidade,imov_tx_obs,imov_tx_quadra,imov_tx_lote,imov_tx_areautil,imov_tx_areaconstruida,imov_tx_terreno,imov_tx_vlrvenda,imov_tx_vlrlocacao,imov_tx_vlriptu,imov_tx_vlrcondominio,imov_tx_comissao,imov_tx_dormitorios,imov_tx_suites,imov_tx_salas,imov_tx_banheiros,imov_tx_varanda,imov_tx_pavimentos,imov_tx_vagasgaragem,imov_tx_vagasvisitantes,imov_tx_deposito,imov_tx_elevador,imov_tx_piscina,imov_tx_churrasqueira,imov_tx_quadrapoliesportiva,imov_tx_situacao,imov_tx_dataCadastro,imov_nb_userCadastro,imov_tx_status);
	$valores = array($_POST[descricao],$_POST[financiavel],$_POST[tipo],$_POST[finalidade],$_POST[endereco],$_POST[bairro],$_POST[cep],$_POST[cidade],$_POST[obs],$_POST[quadra],
		$_POST[lote],valor($_POST[areautil]),valor($_POST[areaconstruida]),valor($_POST[terreno]),valor($_POST[vlrvenda]),valor($_POST[vlrlocacao]),valor($_POST[vlriptu]),valor($_POST[vlrcondominio]),valor($_POST[comissao]),
		$_POST[dormitorios],$_POST[suites],$_POST[salas],$_POST[banheiros],$_POST[varanda],$_POST[pavimentos],$_POST[vagasgaragem],$_POST[vagasvisitantes],
		$_POST[deposito],$_POST[elevador],$_POST[piscina],$_POST[churrasqueira],$_POST[quadrapoliesportiva],$_POST[situacao],date("Y-m-d"),
		$_SESSION[user_nb_id],'ativo');


	if(!$_POST[id]){
		$id=inserir('imovel',$campos,$valores);		
	}else{
		atualizar('imovel',$campos,$valores,$_POST[id]);
		$id=$_POST[id];
	}



	// PERCORRE OS CAMPOS DE DESCRIÇÕES DE VALORES PARA REALIZAR AS AÇÕES NECESSÁRIAS
	foreach ($_POST[descricao_valores] as $posicao => $descricao) {

		$id_descricao    = intval($_POST['id_valores'][$posicao]);
		$valor_descricao = valor($_POST['valor_descricao'][$posicao]);
		$descricao       = addslashes($descricao);// SERVE PARA EVITAR ERROS COM ASPAS E OUTROS CARACTERES NA STRING

		$campos_descricao  = array(desc_tx_nome,desc_nb_imovel,desc_tx_valor,desc_tx_status);
		$valores_descricao = array($descricao,$id,$valor_descricao,'ativo');

		if ( $valor_descricao>0 ) {
			if ( $id_descricao>0 ) {
				atualizar('descricaovalor',$campos_descricao,$valores_descricao,$id_descricao);
			} else {				
				inserir('descricaovalor',$campos_descricao,$valores_descricao);
			}

		} elseif ( $id_descricao>0 ) {
			// SE NÃO TEM VALOR E POSSUI ID, TEM QUE REMOVER
			remover('descricaovalor',$id_descricao);
		}
	}

	
	$_POST[id]=$id;
	index();
	exit;

}

function layout_imovel(){
	global $a_mod;
	cabecalho("Cadastro de Imóvel");




	$c[]=ckeditor('Descrição','descricao',$a_mod[imov_tx_nome],12);
	$c1[]=combo('Financiável?','financiavel',$a_mod[imov_tx_financiavel],2,array("","Sim","Não"));
	$c1[]=combo('Tipo','tipo',$a_mod[imov_tx_tipo],2,array("","Apartamento","Casa","Terrenos","Sítios","Fazendas","Comércio","Indústria","Temporada"));
	$c1[]=combo('Finalidade','finalidade',$a_mod[imov_tx_finalidade],2,array("","Troca","Locação","Venda","Venda ou Locação"));
	$c1[]=campo('Endereço','endereco',$a_mod[imov_tx_endereco],6);							
	$c1[]=campo('Bairro','bairro',$a_mod[imov_tx_bairro],2);							
	$c1[]=campo('CEP','cep',$a_mod[imov_tx_cep],2,'','maxlength="9" onkeyup="mascara_cep_tmp(this);carrega_cep(this.value);" ');
	$c1[]=combo_net('Cidade','cidade',$a_mod[imov_nb_cidade],2,'cidade');
	$c1[]=campo('OBS','obs',$a_mod[imov_tx_obs],6);
	$c1[]=campo('Quadra','quadra',$a_mod[imov_tx_quadra],1);
	$c1[]=campo('Lote','lote',$a_mod[imov_tx_lote],1);
	
	$c2[]=campo('Área Útil','areautil',valor($a_mod[imov_tx_areautil]),2,'',' maxlength="14" onkeyup="mascara_valor_tmp(this);" ');
	$c2[]=campo('Área Construída','areaconstruida',valor($a_mod[imov_tx_areaconstruida]),2,'',' maxlength="14" onkeyup="mascara_valor_tmp(this);" ');
	$c2[]=campo('Terreno','terreno',valor($a_mod[imov_tx_terreno]),2,'',' maxlength="14" onkeyup="mascara_valor_tmp(this);" ');
	
	$c3[]=campo('Vlr Venda','vlrvenda',valor($a_mod[imov_tx_vlrvenda]),2,'',' maxlength="14" onkeyup="mascara_valor_tmp(this);" ');
	$c3[]=campo('Vlr Locação','vlrlocacao',valor($a_mod[imov_tx_vlrlocacao]),2,'',' maxlength="14" onkeyup="mascara_valor_tmp(this);" ');
	$c3[]=campo('Vlr IPTU','vlriptu',valor($a_mod[imov_tx_vlriptu]),2,'',' maxlength="14" onkeyup="mascara_valor_tmp(this);" ');
	$c3[]=campo('Vlr Condomínio','vlrcondominio',valor($a_mod[imov_tx_vlrcondominio]),2,'',' maxlength="14" onkeyup="mascara_valor_tmp(this);" ');
	$c3[]=campo('Comissão (%)','comissao',valor($a_mod[imov_tx_comissao]),2,'',' maxlength="14" onkeyup="mascara_valor_tmp(this);" ');

	$c4[]=campo('Dormitórios','dormitorios',$a_mod[imov_tx_dormitorios],2);
	$c4[]=campo('Suítes','suites',$a_mod[imov_tx_suites],2);
	$c4[]=campo('Salas','salas',$a_mod[imov_tx_salas],2);
	$c4[]=campo('Banheiros','banheiros',$a_mod[imov_tx_banheiros],2);
	$c4[]=combo('Varanda','varanda',$a_mod[imov_tx_varanda],2,array("","Sim","Não"));
	$c4[]=campo('Pavimentos','pavimentos',$a_mod[imov_tx_pavimentos],2);
	$c4[]=campo('Vagas Garagem','vagasgaragem',$a_mod[imov_tx_vagasgaragem],2);
	$c4[]=campo('Vagas Visitantes','vagasvisitantes',$a_mod[imov_tx_vagasvisitantes],2);
	$c4[]=combo('Depósito','deposito',$a_mod[imov_tx_deposito],2,array("","Sim","Não"));
	$c4[]=combo('Elevador','elevador',$a_mod[imov_tx_elevador],2,array("","Sim","Não"));
	
	$c5[]=combo('Piscina','piscina',$a_mod[imov_tx_piscina],2,array("","Sim","Não"));
	$c5[]=combo('Churrasqueira','churrasqueira',$a_mod[imov_tx_churrasqueira],2,array("","Sim","Não"));
	$c5[]=combo('Quadra Poliesportiva','quadrapoliesportiva',$a_mod[imov_tx_quadrapoliesportiva],2,array("","Sim","Não"));
	
	$c6[]=combo('Situação','situacao',$a_mod[imov_tx_situacao],2,array("","Ocupado","Disponível"));
		
	$b[]=botao('Gravar','cadastra_imovel','id',$_POST[id]);
	$b[]=botao('Voltar','index');


	abre_form('Dados do Imóvel');

	linha_form($c);
	echo "<br><br>";

	fieldset('Detalhes');
	linha_form($c1);
	echo "<br><br>";

	fieldset('Áreas (m2)');
	linha_form($c2);
	echo "<br><br>";

	fieldset('Valores');
	linha_form($c3);
	echo "<br><br>";

	// CRIA OS CAMPOS DE DESCRIÇÃO DE VALORES
	fieldset('Descrição de Valores');
	linha_form($d1);

		$qtde_linhas = 20;
		if ( $a_mod[imov_nb_id]>0 ) {
			$sql_descricao = query("SELECT * FROM descricaovalor WHERE desc_nb_imovel='$a_mod[imov_nb_id]' AND desc_tx_status!='inativo' ORDER BY desc_nb_id ASC ");
			$qtde_reg += num_linhas($sql_descricao);// AUMENTA A QUANTIDADE PERMITIDA DE LINHAS DE ACORDO COM A QUANTIDADE DE OBSERVAÇṌES CADASTRADAS
			$qtde_linhas += $qtde_reg;
		}
		for ($i=0; $i<$qtde_linhas; $i++) {
			if ( $a_mod[imov_nb_id]>0 ) {
				$a_descricao = carrega_array($sql_descricao);
			}
			$cpd[]=campo('Descrição','descricao_valores[]',$a_descricao[desc_tx_nome],6);
			$cpd[]=campo('Valor','valor_descricao[]',valor($a_descricao[desc_tx_valor]),2,'',' maxlength="14" onkeyup="mascara_valor_tmp(this);" ');
			
			if ( $a_descricao[desc_nb_id]==0 || $i==($qtde_reg-1) ) {
				$cpd[] = texto('&nbsp;','<a style="font-size:16px; color:gray;" id="bt_mais_descricao_'.$i.'" onclick="exibe_campo_descricao('.($i+1).')" title="Nova Descrição" class="glyphicon glyphicon-plus"></a>',1);
			}
			campo_hidden('id_valores[]',$a_descricao[desc_nb_id]);


			if($a_descricao[desc_nb_id]>0 || $i==1)
				$display = 'inline';
			else
				$display = 'none';

			echo "<div id=div_descricao_$i style='display:$display;'>";
				linha_form($cpd);
				unset($cpd);
			fieldset();
			echo "</div>";
		}
	echo "<br><br>";

	fieldset('Dependências');
	linha_form($c4);
	echo "<br><br>";

	fieldset('Outros');
	linha_form($c5);
	echo "<br><br>";

	linha_form($c6);

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


		function carrega_cep(cep){
			var num = cep.replace(/[^0-9]/g,'');
			if(num.length == '8'){
				document.getElementById('frame_cep').src='<?=$path_parts['basename']?>?acao=carrega_endereco&cep='+num;
			}
		}


		function exibe_campo_descricao(id_div){
			document.getElementById('div_descricao_'+id_div).style.display='inline';
			document.getElementById('bt_mais_descricao_'+(id_div-1)).style.display='none';
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
	$pasta_arquivos = "arquivos/imovel/$id";
	if(!is_dir($pasta_arquivos)){
		mkdir($pasta_arquivos);
	}

	$qtde = count($_FILES["imagens"]["name"]);
	$permitidos = array("jpeg", "jpg", "png", "gif");// EXTENÇÕES PERMITIDAS
	$width  = 500;
	$height = 334;


	// FAR UM PRIMEIRO LAÇO PARA CONFERIR SE TODAS AS IMAGENS FORAM ENVIADAS CORRETAMENTE
	for ($i=0; $i<$qtde; $i++) {
		// VERIFICA SE OCORREU ALGUN ERRO NO ENVIO DO ARQUIVO
		$erro = $_FILES['imagens']['error'][$i];
		switch($file['error']){
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


		// Pegamos sua largura e altura originais
		list($width_orig, $height_orig) = getimagesize($arquivo_temporario);
		//Comparamos sua largura e altura originais com as desejadas
		if($width_orig > $width || $height_orig > $height){
			// Chamamos a função que redimensiona a imagem
			redimensionar($arquivo_temporario,$width,$height);
		}

		if (move_uploaded_file($arquivo_temporario, $arquivo)) {
			$campos  = array(foim_nb_imovel,foim_tx_nome,foim_tx_pasta,foim_tx_dataCadastro,foim_nb_userCadastro,foim_tx_status);
			$valores = array($id,$nome_arquivo,$pasta_arquivos,date('Y-m-d H:i:s'),$_SESSION[user_nb_id],'ativo');
			inserir('foim',$campos,$valores);
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

	if ( intval($_POST[id])==0 ) {
		set_status('ERRO: Código não encontrado!');
		index();
		exit;
	}

	cabecalho("Cadastro de Imóvel");

	$a_imov = carregar('imovel',$_POST[id]);

	$c[]=texto('Descrição',$a_imov[imov_tx_nome],6);


	$campo_arquivo = '<div class="col-sm-6 margin-bottom-5">
				<label><b>Imagens (500 X 334)</b></label>
				<input name="imagens[]" value="" autocomplete="off" type="file" multiple="multiple" class="form-control input-sm" >
			</div>';

	$c[]=$campo_arquivo;


	$b[]=botao('Gravar','enviar_imagens','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Imóvel');
	linha_form($c);
	fecha_form($b);



	$sql = "SELECT * FROM foim WHERE foim_nb_imovel='$_POST[id]' AND foim_tx_status='ativo' ";
	$query=mysqli_query($conn, $sql) or die(mysql_error());
	$qtde_linhas = mysqli_num_rows($query);


	if ( $qtde_linhas>0 ) {
		?><div class="col-md-12 col-sm-12">
			<div class="portlet light ">
				<div class="row"><?php
					while( $row=mysqli_fetch_array($query) ){
						$url = $row[foim_tx_pasta].'/'.$row[foim_tx_nome];
						?><div class="col-lg-3 col-md-4 col-6">
							<!-- <a href="<?=$url?>" class="d-block mb-4 h-100">
								<img class="img-fluid img-thumbnail" src="<?=$url?>" alt="">
							</a> -->
							<div class="thumbnail">
								<img src="<?=$url?>" alt="">
								<div class="caption">
									<a href="<?=$url?>" target="_blank" class="btn btn-info btn-xs" role="button">Visualizar</a>
									<a href="javascript:remover_arquivo('<?=$row[foim_nb_id]?>');" class="btn btn-danger btn-xs" role="button">Remover</a>
								</div>
							</div>
						</div><?php
					}

				?></div>
			</div>
		</div><?php
	}


	?>

	<form name="form_excluir_arquivo" method="post" action="cadastro_imovel.php">
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
	cabecalho("Cadastro de Imóvel");

	if($_POST[busca_codigo])
		$extra .=" AND imov_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND imov_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_tipo])
		$extra .=" AND imov_tx_tipo = '$_POST[busca_tipo]'";
	if($_POST[busca_finalidade])
		$extra .=" AND imov_tx_finalidade = '$_POST[busca_finalidade]'";
	if($_POST[busca_financiavel])
		$extra .=" AND imov_tx_financiavel = '$_POST[busca_financiavel]'";
	if($_POST[busca_situacao])
		$extra .=" AND imov_tx_situacao = '$_POST[busca_situacao]'";
		
	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Descrição','busca_nome',$_POST[busca_nome],5);
	$c[]=combo('Tipo','busca_tipo',$_POST[busca_tipo],2,array("","Apartamento","Casa","Terrenos","Sítios","Fazendas","Comércio","Indústria","Temporada"));
	$c[]=combo('Finalidade','busca_finalidade',$_POST[busca_finalidade],2,array("","Troca","Locação","Venda","Venda ou Locação"));
	$c[]=combo('Financiável','busca_financiavel',$_POST[busca_financiavel],2,array("","Sim","Não"));
	$c[]=combo('Situação','busca_situacao',$_POST[busca_situacao],2,array("","Ocupado","Disponível"));
				
	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_imovel');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM imovel WHERE imov_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','DESCRIÇÃO','TIPO','FINALIDADE','SITUAÇÃO','FINANCIÁVEL','','','');
	$val = array('imov_nb_id','imov_tx_nome','imov_tx_tipo','imov_tx_finalidade','imov_tx_situacao','imov_tx_financiavel','icone_modificar(imov_nb_id,layout_imagens,,,,glyphicon glyphicon-open-file)','icone_modificar(imov_nb_id,modifica_imovel)','icone_excluir(imov_nb_id,exclui_imovel)');		
	
	grid($sql,$cab,$val);

	rodape();

}


?>