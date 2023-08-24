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


function exclui_construtor(){
	remover('construtor',$_POST[id]);

	index();
	exit;
}


function remover_arquivo(){

	$a_arquivo = carregar('doco',$_POST[id_arquivo]);
	$url = $a_arquivo[doco_tx_pasta].'/'.$a_arquivo[doco_tx_nome];

	remover('doco',$_POST[id_arquivo]);
	// unlink($url);
	
	layout_imagens();
	exit;
}

function modifica_construtor(){
	global $a_mod;

	$a_mod = carregar('construtor',$_POST[id]);

	layout_construtor();
	exit;
}


function cadastra_construtor(){
	global $a_mod;
	
	$campos = array(cons_tx_nome,cons_tx_nascimento,cons_tx_sexo,cons_tx_civil,cons_tx_rg,cons_tx_cpf,cons_tx_endereco,cons_tx_complemento,
		cons_tx_bairro,cons_tx_cep,cons_nb_cidade,cons_tx_fone1,cons_tx_cel,cons_tx_email,cons_tx_status);
	$valores = array($_POST[nome],data($_POST[nascimento]),$_POST[sexo],$_POST[civil],$_POST[rg],$_POST[cpf],$_POST[endereco],$_POST[complemento],
		$_POST[bairro],$_POST[cep],$_POST[cidade],$_POST[fone1],$_POST[cel],$_POST[email],'ativo');

	if(!$_POST[id]){
		array_push($campos, cons_nb_userCadastro,cons_tx_dataCadastro);
		array_push($valores, $_SESSION[user_nb_id],date("Y-m-d"));

		$id=inserir('construtor',$campos,$valores);
		$id2 = ultimo_reg('construtor');
	}else{
		atualizar('construtor',$campos,$valores,$_POST[id]);
		$id=$_POST[id];
	}

	if($_FILES[arquivo][name]!=''){
		if(!is_dir("arquivos/construtor/$id")){
			mkdir("arquivos/construtor/$id");
		}

		$arq=enviar('arquivo',"arquivos/construtor/$id/");
		if($arq){
			atualizar('construtor',array(cons_tx_arquivo),array($arq),$id);
		}
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
	$pasta_arquivos = "arquivos/construtor/$id";
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
			$campos  = array(doco_nb_construtor,doco_tx_nome,doco_tx_pasta,doco_tx_dataCadastro,doco_nb_userCadastro,doco_tx_status);
			$valores = array($id,$nome_arquivo,$pasta_arquivos,date('Y-m-d H:i:s'),$_SESSION[user_nb_id],'ativo');
			inserir('doco',$campos,$valores);
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

	cabecalho("Cadastro de Construtor");

	$a_cons = carregar('construtor',$_POST[id]);

	$c[]=texto('Nome',$a_cons[cons_tx_nome],6);


	$campo_arquivo = '<div class="col-sm-6 margin-bottom-5">
				<label><b>Imagens (500 X 334)</b></label>
				<input name="imagens[]" value="" autocomplete="off" type="file" multiple="multiple" class="form-control input-sm" >
			</div>';

	$c[]=$campo_arquivo;


	$b[]=botao('Gravar','enviar_imagens','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Construtor');
	linha_form($c);
	fecha_form($b);



	$sql = "SELECT * FROM doco WHERE doco_nb_construtor='$_POST[id]' AND doco_tx_status='ativo' ";
	$query=mysqli_query($conn, $sql) or die(mysql_error());
	$qtde_linhas = mysqli_num_rows($query);


	if ( $qtde_linhas>0 ) {
		?><div class="col-md-12 col-sm-12">
			<div class="portlet light ">
				<div class="row"><?php
					while( $row=mysqli_fetch_array($query) ){
						$url = $row[doco_tx_pasta].'/'.$row[doco_tx_nome];
						?><div class="col-lg-3 col-md-4 col-6">
							<!-- <a href="<?=$url?>" class="d-block mb-4 h-100">
								<img class="img-fluid img-thumbnail" src="<?=$url?>" alt="">
							</a> -->
							<div class="thumbnail">
								<img src="<?=$url?>" alt="">
								<div class="caption">
									<a href="<?=$url?>" target="_blank" class="btn btn-info btn-xs" role="button">Visualizar</a>
									<a href="javascript:remover_arquivo('<?=$row[doco_nb_id]?>');" class="btn btn-danger btn-xs" role="button">Remover</a>
								</div>
							</div>
						</div><?php
					}

				?></div>
			</div>
		</div><?php
	}


	?>

	<form name="form_excluir_arquivo" method="post" action="cadastro_construtor.php">
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

function cadastra_rubrica(){
	// print_r($_POST);exit;
	$data_uri = $_POST[image];
	$encoded_image = explode(",", $data_uri)[1];
	$decoded_image = base64_decode($encoded_image);
	file_put_contents("arquivos/assinatura/construtor/$_POST[id].png", $decoded_image);
	

	// atualizar('visita',array(visi_tx_situacao),array('encerrado'),$_POST[id]);

	index();
	exit;
}

function layout_rubrica(){
	
	cabecalho("Cadastro de Construtor");

	?><script src="https://cdn.jsdelivr.net/npm/signature_pad@2.3.2/dist/signature_pad.min.js"></script><?

	$a = carregar('construtor',$_POST[id]);
	

	$c[] = texto('Construtor',$a[cons_tx_nome],8);
	$c[] = texto('CPF',$a[cons_tx_cpf],4);
	$c[] = texto('Assinatura','<canvas id="signature-pad" style="border: solid;" class="signature-pad" width=500 height=200></canvas>','12');

	$b[]=botao('Voltar','index');
	$b[]="<button name=\"acao\" id=\"salvar\" value=\"cadastra_rubrica\" type=\"button\" class=\"btn default\">Gravar</button>";
	$b[]=botao('Limpar','layout_rubrica','id',$_POST[id]);
	if(is_file("arquivos/assinatura/construtor/$_POST[id].png")){
		$b[]="<a href=\"arquivos/construtor/assinaturas/$_POST[id].png\" class=\"btn default\" target=_blank>Visualizar</a>";
	}

	abre_form('Filtro de Busca');
	linha_form($c);

	fecha_form($b);

	rodape();

	?>

	<form name="form_rubrica" method="post">
		<input type="hidden" name="acao">
		<input type="hidden" name="image">
		<input type="hidden" name="id">
	</form>
	
	<script type="text/javascript">
		var signaturePad = new SignaturePad(document.getElementById('signature-pad'), {
			backgroundColor: 'rgba(255, 255, 255, 0)',
			penColor: 'rgb(0, 0, 0)'
		});
		var saveButton = document.getElementById('salvar');
		// var cancelButton = document.getElementById('clear');

		saveButton.addEventListener('click', function (event) {
			var imageData = signaturePad.toDataURL();
			document.form_rubrica.image.value=imageData;
			document.form_rubrica.acao.value='cadastra_rubrica';
			document.form_rubrica.id.value='<?=$_POST[id]?>';
			document.form_rubrica.submit();
			// Send data to server instead...
			// window.open(data);
		});

		// cancelButton.addEventListener('click', function (event) {
		// 	signaturePad.clear();
		// });


		// window.onresize = function()
		// {
			
		//     var canvs = document.getElementById("signature-pad");
		// 	canvs.width = window.innerWidth;
		// 	canvs.height = window.innerHeight;

		// }

	</script>

	<style type="text/css">
		/*.wrapper {
			position: relative;
			width: 500px;
			height: 200px;
			-moz-user-select: none;
			-webkit-user-select: none;
			-ms-user-select: none;
			user-select: none;
		}

		.signature-pad {
			position: absolute;
			left: 0;
			top: 0;
			width:500px;
			height:200px;
		}*/
	</style>


	<?

}

function layout_construtor(){
	global $a_mod;
	cabecalho("Cadastro de Construtor");

	$data1 = new DateTime ($a_mod[cons_tx_nascimento]);
	$data2 = new DateTime (date("Y-m-d"));

	$intervalo = $data1 -> diff($data2);

	$idade = "{$intervalo->y} anos, {$intervalo->m} meses e {$intervalo->d} dias";

	$c[]=campo('Nome','nome',$a_mod[cons_tx_nome],4);
	// $c[]=campo('Nascimento','nascimento',data($a_mod[cons_tx_nascimento]),2,MASCARA_DATA);
	$c[] = campo('Nascimento','nascimento',data($a_mod[cons_tx_nascimento]),2,'',' maxlength="10" onkeyup="mascara_data_tmp(this);" ');
	$c[]=combo('Sexo','sexo',$a_mod[cons_tx_sexo],2,array('','Masculino','Feminino'));
	$c[]=combo('Estado Civil','civil',$a_mod[cons_tx_civil],2,array('','Casado(a)','Solteiro(a)','Viúvo(a)'));
	$c[]=campo('RG','rg',$a_mod[cons_tx_rg],2);
	$c[]=campo('CPF/CNPJ','cpf',$a_mod[cons_tx_cpf],2,'',' maxlength="18" onkeyup="mascara_cnpj_tmp(this);" ');
	$c[]=campo('Endereço','endereco',$a_mod[cons_tx_endereco],4);
	$c[]=campo('Complemento','complemento',$a_mod[cons_tx_complemento],4);
	$c[]=campo('Bairro','bairro',$a_mod[cons_tx_bairro],2);
	$c[]=campo('CEP','cep',$a_mod[cons_tx_cep],2,'','maxlength="9" onkeyup="mascara_cep_tmp(this);carrega_cep(this.value);" ');
	$c[]=combo_net('Cidade','cidade',$a_mod[cons_nb_cidade],3,'cidade');
	$c[]=campo('Telefone','fone1',$a_mod[cons_tx_fone1],2,'',' maxlength="15" onkeyup="mascara_telefone_tmp(this);" ');
	$c[]=campo('Celular','cel',$a_mod[cons_tx_cel],2,'',' maxlength="15" onkeyup="mascara_telefone_tmp(this);" ');
	$c[]=campo('E-mail','email',$a_mod[cons_tx_email],3);
	// $c[]=arquivo('Arquivo','arquivo','',3);

	$b[]=botao('Gravar','cadastra_construtor','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Construtor');
	linha_form($c);
	linha_form($c2);

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

	<form name="form_excluir_arquivo" method="post" action="cadastro_construtor.php">
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
	</script>
	<?

}

function icone_excluir2($id,$acao,$campos='',$valores='',$target='',$icone='glyphicon glyphicon-remove',$msg='Deseja excluir o registro?'){
	$icone='class="'.$icone.'"';
	
	return "<a style='color:gray' onclick='javascript:remover_arquivo(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\");' ><spam $icone></spam></a>";
	
}

function index(){
	cabecalho("Cadastro de Construtor");

	if($_POST[busca_codigo])
		$extra .=" AND cons_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND cons_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_cpf])
		$extra .=" AND cons_tx_cpf = '$_POST[busca_cpf]'";
	
	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],9);
	$c[]=campo('CPF','busca_cpf',$_POST[busca_cpf],2,MASCARA_CPF);
	
	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_construtor');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM construtor WHERE cons_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','CPF','','','');
	$val = array('cons_nb_id','cons_tx_nome','cons_tx_cpf','icone_modificar(cons_nb_id,layout_rubrica,,,,glyphicon glyphicon-pencil)','icone_modificar(cons_nb_id,layout_imagens,,,,glyphicon glyphicon-open-file)','icone_modificar(cons_nb_id,modifica_construtor)',
		'icone_excluir(cons_nb_id,exclui_construtor)');

	grid($sql,$cab,$val);

	rodape();

}


?>