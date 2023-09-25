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





function exclui_corretor(){

	remover('entidade',$_POST[id]);



	index();

	exit;

}





function excluir_arquivo_corretor(){

	unlink("arquivos/corretor/$_POST[id_arquivo]/$_POST[nome_arquivo]");

	$_POST[id]=$_POST[id_arquivo];

	modifica_corretor();

	exit;

}



function modifica_corretor(){

	global $a_mod;



	$a_mod = carregar('entidade',$_POST[id]);



	layout_corretor();

	exit;

}





function cadastra_corretor(){

	global $a_mod;

	

	$sql = query("SELECT * FROM user WHERE user_tx_login = '$_POST[login]'");

	if(num_linhas($sql)>0){

		set_status("ERRO: Login já cadastrado!");

		layout_corretor();

		exit;

	}	



	if(!$_POST[senha] || !$_POST[senha2]){

		set_status("ERRO: Preecha o campo senha e confirme-a!");

		layout_corretor();

		exit;

	}



	$campos = array(enti_tx_nome,enti_tx_nascimento,enti_tx_sexo,enti_tx_civil,enti_tx_rg,enti_tx_cpf,enti_tx_endereco,enti_tx_complemento,

		enti_tx_bairro,enti_tx_cep,enti_nb_cidade,enti_tx_fone1,enti_tx_cel,enti_tx_email,enti_tx_obs,enti_tx_tipo,enti_tx_status);

	$valores = array($_POST[nome],data($_POST[nascimento]),$_POST[sexo],$_POST[civil],$_POST[rg],$_POST[cpf],$_POST[endereco],$_POST[complemento],

		$_POST[bairro],$_POST[cep],$_POST[cidade],$_POST[fone1],$_POST[cel],$_POST[email],$_POST[obs],'Corretor','ativo');



	if(!$_POST[id]){

		array_push($campos, enti_nb_userCadastro,enti_tx_dataCadastro);

		array_push($valores, $_SESSION[user_nb_id],date("Y-m-d"));



		$id=inserir('entidade',$campos,$valores);

		$id2 = ultimo_reg('entidade');



		inserir('user',array(user_tx_login,user_tx_nome,user_tx_senha,user_tx_nivel,user_nb_entidade,user_tx_status),array($_POST[login],$_POST[nome],md5($_POST[senha]),'Corretor',$id,'ativo'));



	}else{



		atualizar('entidade',$campos,$valores,$_POST[id]);

		$id=$_POST[id];



	}



	if($_FILES[arquivo][name]!=''){

		if(!is_dir("arquivos/corretor/$id")){

			mkdir("arquivos/corretor/$id");

		}



		$arq=enviar('arquivo',"arquivos/corretor/$id/");

		if($arq){

			atualizar('entidade',array(enti_tx_arquivo),array($arq),$id);

		}

	}



	$_POST[id]=$id;

	index();

	exit;



}



function layout_corretor(){

	global $a_mod;

	cabecalho("Cadastro de Corretor");



	$data1 = new DateTime ($a_mod[enti_tx_nascimento]);

	$data2 = new DateTime (date("Y-m-d"));



	$intervalo = $data1 -> diff($data2);



	$idade = "{$intervalo->y} anos, {$intervalo->m} meses e {$intervalo->d} dias";



	$c[]=campo('Nome','nome',$a_mod[enti_tx_nome],4);

	$c[]=campo('Nascimento','nascimento',data($a_mod[enti_tx_nascimento]),2,'',' maxlength="10" onkeyup="mascara_data_tmp(this);" ');

	$c[]=combo('Sexo','sexo',$a_mod[enti_tx_sexo],2,array('','Masculino','Feminino'));

	$c[]=combo('Estado Civil','civil',$a_mod[enti_tx_civil],2,array('','Casado(a)','Solteiro(a)','Viúvo(a)'));

	$c[]=campo('RG','rg',$a_mod[enti_tx_rg],2);

	$c[]=campo('CPF/CNPJ','cpf',$a_mod[enti_tx_cpf],2,'',' maxlength="18" onkeyup="mascara_cnpj_tmp(this);" ');

	$c[]=campo('Endereço','endereco',$a_mod[enti_tx_endereco],4);

	$c[]=campo('Complemento','complemento',$a_mod[enti_tx_complemento],4);

	$c[]=campo('Bairro','bairro',$a_mod[enti_tx_bairro],2);

	$c[]=campo('CEP','cep',$a_mod[enti_tx_cep],2,'','maxlength="9" onkeyup="mascara_cep_tmp(this);carrega_cep(this.value);" ');

	$c[]=combo_net('Cidade','cidade',$a_mod[enti_nb_cidade],3,'cidade');

	$c[]=campo('Telefone','fone1',$a_mod[enti_tx_fone1],2,'',' maxlength="15" onkeyup="mascara_telefone_tmp(this);" ');

	$c[]=campo('Celular','cel',$a_mod[enti_tx_cel],2,'',' maxlength="15" onkeyup="mascara_telefone_tmp(this);" ');

	$c[]=campo('E-mail','email',$a_mod[enti_tx_email],3);

	$c2[]=campo('Login','login',$a_mod[user_tx_login],4);

	$c2[]=campo_senha('Senha','senha',"",2);

	$c2[]=campo_senha('Confirmar Senha','senha2',"",2);

	// $c[]=arquivo('Arquivo','arquivo','',3);



	$b[]=botao('Gravar','cadastra_corretor','id',$_POST[id]);

	$b[]=botao('Voltar','index');



	abre_form('Dados do Corretor');

	linha_form($c);



	// SERVE PARA PERGER O NOME DO ARQUIVO ATUALO

	$path_parts = pathinfo( __FILE__ );



	if(is_int(strpos($_SESSION['user_tx_nivel'], 'Administrador'))){

		echo "<br><br>";



		if(!$_POST[id]){

			fieldset('Dados de Acesso');

			linha_form($c2);			

		}

	}





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



	<form name="form_excluir_arquivo" method="post" action="cadastro_corretor.php">

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







function cadastra_rubrica(){

	// print_r($_POST);exit;

	$data_uri = $_POST[image];

	$encoded_image = explode(",", $data_uri)[1];

	$decoded_image = base64_decode($encoded_image);

	file_put_contents("arquivos/assinatura/corretor/$_POST[id].png", $decoded_image);

	



	// atualizar('visita',array(visi_tx_situacao),array('encerrado'),$_POST[id]);



	index();

	exit;

}



function layout_rubrica(){

	

	cabecalho("Cadastro de Corretor");



	?><script src="https://cdn.jsdelivr.net/npm/signature_pad@2.3.2/dist/signature_pad.min.js"></script><?



	$a = carregar('entidade',$_POST[id]);

	



	$c[] = texto('Corretor',$a[enti_tx_nome],8);

	$c[] = texto('CPF',$a[enti_tx_cpf],4);

	$c[] = texto('Assinatura','<canvas id="signature-pad" style="border: solid;" class="signature-pad" width=500 height=200></canvas>','12');



	$b[]=botao('Voltar','index');

	$b[]="<button name=\"acao\" id=\"salvar\" value=\"cadastra_rubrica\" type=\"button\" class=\"btn default\">Gravar</button>";

	$b[]=botao('Limpar','layout_rubrica','id',$_POST[id]);

	if(is_file("arquivos/rubrica/$_POST[id].png")){

		$b[]="<a href=\"arquivos/assinatura/corretor/$_POST[id].png\" class=\"btn default\" target=_blank>Visualizar</a>";

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



function index(){

	cabecalho("Cadastro de Corretor");



	if($_POST[busca_codigo])

		$extra .=" AND enti_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])

		$extra .=" AND enti_tx_nome LIKE '%$_POST[busca_nome]%'";

	if($_POST[busca_cpf])

		$extra .=" AND enti_tx_cpf = '$_POST[busca_cpf]'";

	

	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);

	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],9);

	$c[]=campo('CPF','busca_cpf',$_POST[busca_cpf],2,MASCARA_CPF);

	

	$b[]=botao('Buscar','index');

	$b[]=botao('Inserir','layout_corretor');



	abre_form('Filtro de Busca');

	linha_form($c);

	fecha_form($b);



	$sql = "SELECT * FROM entidade WHERE enti_tx_status != 'inativo' AND enti_tx_tipo = 'Corretor' $extra";

	$cab = array('CÓDIGO','NOME','CPF','','','');

	$val = array('enti_nb_id','enti_tx_nome','enti_tx_cpf','icone_modificar(enti_nb_id,layout_rubrica,,,,glyphicon glyphicon-pencil)','icone_modificar(enti_nb_id,modifica_corretor)',

		'icone_excluir(enti_nb_id,exclui_corretor)');



	grid($sql,$cab,$val);



	rodape();



}





?>