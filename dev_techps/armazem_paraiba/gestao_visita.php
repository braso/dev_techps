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
	
	assina_visita();
	exit;
}

function exclui_visita(){
	remover('visita',$_POST[id]);

	index();
	exit;
}

function exclui_atualizacao(){

	remover('atpr',$_POST[id]);

	$_POST[id] = $_POST[id2];

	assina_visita();
	exit;
}

function modifica_visita(){
	global $a_mod;

	$a_mod = carregar('visita',$_POST[id]);

	layout_visita();
	exit;
}

function imprime_visita(){

	$a_visi = carregar('visita',$_POST[id]);
	$a_clie = carregar('entidade',$a_visi[visi_nb_entidade]);
	$a_corr = carregar('entidade',$a_visi[visi_nb_corretor]);
	$a_cida = carregar('cidade',$a_visi[visi_nb_cidade]);

	?>
	<!-- <!DOCTYPE html> -->
<!-- <html lang="pt-br"> -->
<!-- <head> -->
	<!-- <title>IMPRESSÃO VISITA</title> -->
	<!-- <meta charset="utf-8"> -->
	<!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
	<style type="text/css">
		body .container{
			background-image: url(fundo.png);
			background-repeat: no-repeat;
			background-size: contain;
			background-position: 75% 100%;
			color: #663399;
		}
		header{
			padding: 1% 0;
		}
		.sub_linha{
			border-bottom: 1px solid #663399;
		}
		.linha_centro{
			position:relative;
			margin:0;
			padding: 1% 0;
			float:left;
			text-align: center;
		}
		.op_venda{
			text-align: right;
			font-weight: bold;
		}
		.op_aluguel{
			text-align: left;
			font-weight: bold;	
		}
		footer{
			padding: 5% 0 2% 0;
		}
		.side_footer{
			padding-left: 15%;
		}
		.side_footer .content{
			width:50%;
			border-top: 1px solid #663399;
		}
		.linha_separa_001{
			width: 100%;
			height: 150px;
		}
		.linha_separa_002{
			width: 100%;
			height: 70px;
		}
		.linha_separa_003{
			width: 100%;
			height: 80px;
		}
		.imagem-assinatura {
			position:absolute;
			margin: -7.5% 0 0 -30%;
			max-width: 200px;
			z-index:9999;
		}
		@media print{
			header figure{
				width: 40%;
			}
			.ajuste001{
				width: 15%;
				float: left;
			}
			.ajuste002{
				width: 85%;
				float: left;
			}
			.ajuste003{
				width: 10%;
				float: left;
			}
			.ajuste004{
				width: 40%;
				float: left;
			}
			.ajuste005{
				width: 15%;
				float: left;
			}
			.ajuste006{
				width: 35%;
				float: left;
			}
			.ajuste007{
				width: 11%;
				float: left;
			}
			.ajuste008{
				width: 34%;
				float: left;
			}
			.ajuste009{
				width: 9%;
				float: left;
			}
			.ajuste010{
				width: 15%;
				float: left;
			}
			.ajuste011{
				width: 21%;
				float: left;
			}
			.ajuste012{
				width: 10%;
				float: left;
			}
			.ajuste013{
				width: 20%;
				float: left;
				text-align: right;
			}
			.ajuste014{
				width: 80%;
				float: left;
			}
			.ajuste015{
				width: 20%;
				float: left;
				text-align: right;
			}
			.ajuste016{
				width: 80%;
				float: left;
				padding-left: 20%;
			}
			.linha_separa_001{
				height: 20px;
				float: left;
			}
			.op_venda{
				width: 50%;
				float: left;
				text-align: center;
			}
			.op_aluguel{
				width: 50%;
				float: left;
				text-align: center;
			}
			.linha_separa_002{
				height: 20px;
				float: left;
			}
			.linha_separa_003{
				height: 30px;
				float: left;
			}
			.side_footer{
				width: 50%;
				float: left;
				padding-left: 0;
			}
			.side_footer .content{
				width: 100%;
				float: left;
			}
			.imagem-assinatura {
				margin: -13% 0 0 -20%;
				/*width: 150px;*/
				max-width: 150px !important;
			}
		}
	</style>
<!-- </head> -->
<!-- <body> -->
	<div class="container col-md-12">
		<header class="col-md-12">
			<figure class="col-md-6 col-sm-12">
				<img src="logo.png" alt="logo.png" />
			</figure>
		</header>
		<section class="col-md-12">
			<p class="col-md-12">
				<div class="col-md-1 ajuste001">
					Cliente(s):
				</div>
				<div class="col-md-11 sub_linha ajuste002">
					<?=$a_clie[enti_tx_nome]?>
				</div>
			</p>
			<p class="col-md-12">
				<div class="col-md-1 ajuste003">
					Tel.:
				</div>
				<div class="col-md-5 sub_linha ajuste004">
					<?=$a_clie[enti_tx_fone1]?>
				</div>
				<div class="col-md-1 text-center ajuste005">
					Corretor:
				</div>
				<div class="col-md-5 sub_linha ajuste006">
					<?=$a_corr[enti_tx_nome]?>
				</div>
			</p>
			<p class="col-md-12">
				<div class="col-md-1 ajuste001">
					Bairro:
				</div>
				<div class="col-md-11 sub_linha ajuste002">
					<?=ucfirst($a_visi[visi_tx_bairro])?>
				</div>
			</p>
			<p class="col-md-12">
				<div class="col-md-1 ajuste007">
					Cidade:
				</div>
				<div class="col-md-3 sub_linha ajuste008">
					<?=$a_cida[cida_tx_nome]?>
				</div>
				<div class="col-md-1 text-center ajuste009">
					Data:
				</div>
				<div class="col-md-3 sub_linha ajuste010">
					<?=data($a_visi[visi_tx_dataAgenda])?>
				</div>
				<div class="col-md-1 text-center ajuste011">
					Hora da Visita:
				</div>
				<div class="col-md-3 sub_linha ajuste012">
					<?=$a_visi[visi_tx_horaAgenda]?>
				</div>
			</p>
			<div class="linha_separa_001"></div>
			<p class="col-md-12 linha_centro">

				<?
					// if($a_visi[visi_tx_finalidade] == 'Venda'){
					// 	$marca1 = 'X';
					// 	$marca2 = '';
					// }elseif($a_visi[visi_tx_finalidade] == 'Aluguel'){
					// 	$marca1 = '';
					// 	$marca2 = 'X';
					// }
				?>

				<!-- <div class="col-md-6 col-sm-6 op_venda">
					<span>Venda ( <?=$marca1?> )</span>
				</div>
				<div class="col-md-6 col-sm-6 op_aluguel">
					<span>Aluguel ( <?=$marca2?> )</span>					
				</div> -->
				<div class="col-md-6 col-sm-6 op_venda">
					<span>Tipo: <?=$a_visi[visi_tx_finalidade]?></span>					
				</div>
			</p>
			<div class="linha_separa_002"></div>
			<?
					// if($a_visi[visi_tx_tipo] == 'Casa'){
					// 	$tipo1 = 'X';
					// 	$tipo2 = '';
					// 	$tipo3 = '';
					// 	$tipo4 = '';
					// }elseif($a_visi[visi_tx_tipo] == 'Prédio ou Casa Comercial'){
					// 	$tipo1 = '';
					// 	$tipo2 = 'X';
					// 	$tipo3 = '';
					// 	$tipo4 = '';
					// }elseif($a_visi[visi_tx_tipo] == 'Apartamento'){
					// 	$tipo1 = '';
					// 	$tipo2 = '';
					// 	$tipo3 = 'X';
					// 	$tipo4 = '';
					// }elseif($a_visi[visi_tx_tipo] == 'Terreno'){
					// 	$tipo1 = '';
					// 	$tipo2 = '';
					// 	$tipo3 = '';
					// 	$tipo4 = 'X';
					// }
				?>
			<!-- <p class="col-md-12">
				<div class="col-md-6 col-sm-6 text-left ajuste013">
					<span>Casa ( <?=$tipo1?> )</span>
				</div>
				<div class="col-md-6 col-sm-6 text-left ajuste014">
					<span>Prédio ou Casa Comercial ( <?=$tipo2?> )</span>					
				</div>
			</p> -->
			<!-- <p>
				<div class="col-md-6 col-sm-6 text-left ajuste015">
					<span>Apartamento ( <?=$tipo3?> )</span>
				</div>
				<div class="col-md-6 col-sm-6 text-left ajuste016">
					<span>Terreno ( <?=$tipo4?> )</span>					
				</div>
			</p> -->
			<?php

			$sql_item = query("SELECT * FROM itemvisita WHERE item_nb_visita='$a_visi[visi_nb_id]' AND item_tx_status!='inativo' AND item_tx_situacao='encerrado' ORDER BY item_nb_id DESC ");
			while ( $a_item = carrega_array($sql_item) ) {

				$nome_imovel     = '';
				$nome_cidade     = '';
				$nome_bairro     = '';
				$nome_tipo       = '';
				$nome_construtor = '';

				// VERIFICA SE IDENTIFICOU O IMÓVEL
				if ( $a_item[item_nb_imovel]>0 ) {
					$a_imovel = carregar('imovel',$a_item[item_nb_imovel]);
					$nome_imovel = $a_imovel[imov_tx_nome];

					if ($a_imovel[imov_nb_cidade]>0) {
						$a_cidade = carregar('cidade',$a_imovel[imov_nb_cidade]);
						$nome_cidade = $a_cidade[cida_tx_nome];
					}
					$nome_bairro = $a_imovel[imov_tx_bairro];

					if ($a_imovel[imov_nb_tipoimovel]>0) {
						$a_tipo = carregar('tipoimovel',$a_imovel[imov_nb_tipoimovel]);
						$nome_tipo = $a_tipo[tipo_tx_nome];
					}
					if ($a_imovel[imov_nb_construtor]>0) {
						$a_construtor = carregar('construtor',$a_imovel[imov_nb_construtor]);
						$nome_construtor = $a_construtor[cons_tx_nome];
					}

				} else {
					// SE TIVER PREENCHIDO OS CAMPOS
					$nome_imovel = $a_item[item_tx_descricao];

					if ($a_item[item_nb_cidade]>0) {
						$a_cidade = carregar('cidade',$a_item[item_nb_cidade]);
						$nome_cidade = $a_cidade[cida_tx_nome];
					}
					$nome_bairro = $a_item[item_tx_bairro];

					if ($a_item[item_nb_tipoimovel]>0) {
						$a_tipo = carregar('tipoimovel',$a_item[item_nb_tipoimovel]);
						$nome_tipo = $a_tipo[tipo_tx_nome];
					}
					if ($a_item[item_nb_construtor]>0) {
						$a_construtor = carregar('construtor',$a_item[item_nb_construtor]);
						$nome_construtor = $a_construtor[cons_tx_nome];
					}
				}


				?>
					<div class="linha_separa_003"></div>
					<p class="col-md-12">
						<div class="col-md-1">
							Imóvel:
						</div>
						<div class="col-md-11 sub_linha">
							<?=$nome_imovel.';&nbsp;'.$nome_tipo.';&nbsp;'.$nome_cidade.';&nbsp;'.$nome_bairro.';&nbsp;'.$nome_construtor?>
						</div>
					</p>
				<?php
			}

			?>
			<!-- <div class="linha_separa_003"></div>
			<p class="col-md-12">
				<div class="col-md-1">
					Imóvel:
				</div>
				<div class="col-md-11 sub_linha">
					<?=$a_visi[visi_tx_descricao]?>
				</div>
			</p> -->
		</section>
		<footer class="col-md-12">
			<div class="col-md-6 col-sm-6 text-center side_footer">
				<?
					if(is_file("arquivos/rubrica/".$a_visi[visi_nb_corretor].".png")){
						?><img class="imagem-assinatura" src="arquivos/rubrica/<?=$a_visi[visi_nb_corretor]?>.png"/><?
					}
				?>
				<div class="col-md-12 text-center content">Ass. do Corretor</div>
			</div>
			<div class="col-md-6 col-sm-6 text-center side_footer">
				<?
					if(is_file("arquivos/assinatura/visita/".$a_visi[visi_nb_id].".png")){
						?><img class="imagem-assinatura" src="arquivos/assinatura/visita/<?=$a_visi[visi_nb_id]?>.png"/><?
					}
				?>
				<div class="col-md-12 text-center content">Ass. do Cliente</div>
			</div>
		</footer>
	</div>
<!-- </body> -->
<!-- </html> -->
	<?
}

function cadastra_atualizacao(){
	global $a_mod;

	$campos = array(atpr_tx_nome,atpr_nb_processo,atpr_tx_dataCadastro,atpr_nb_userCadastro,atpr_tx_status);
	$valores = array($_POST[atualizacao],$_POST[id],date("Y-m-d H:i:s"),$_SESSION[user_nb_id],'ativo');

	inserir('atpr',$campos,$valores);		
	
	assina_visita();
	exit;

}

function cadastra_item_visita(){
	global $a_mod;

	$campos  = array(visi_nb_entidade,visi_nb_corretor,visi_tx_finalidade,
		visi_tx_dataAgenda,visi_tx_horaAgenda,visi_tx_status);
	$valores = array($_POST[cliente],$_POST[corretor],$_POST[finalidade],
		$_POST[data],$_POST[hora],'ativo');

	if(!$_POST[id]){
		array_push($campos, visi_tx_situacao,visi_tx_dataCadastro,visi_nb_userCadastro);
		array_push($valores, 'pendente',date("Y-m-d"),$_SESSION[user_nb_id]);

		$id=inserir('visita',$campos,$valores);

	}else{
		atualizar('visita',$campos,$valores,$_POST[id]);
		$id=$_POST[id];
	}


	// GERA O ITEM DA VISITA
	if ( $_POST[imovel]>0 || trim($_POST[descricao])!='' ) {// EVITA O SURGIMENTO DE REGISTROS EM BRANCO
		$campos_item = array(item_nb_imovel,item_nb_visita,
			item_tx_descricao,item_nb_cidade,item_tx_bairro,item_nb_tipoimovel,item_nb_construtor,
			item_nb_userCadastro,item_tx_dataCadastro,item_tx_situacao,item_tx_status);

		$valores_item= array($_POST[imovel],$id,
			$_POST[descricao],$_POST[cidade],$_POST[bairro],$_POST[tipo],$_POST[construtor],
			$_SESSION[user_nb_id],date('Y-m-d H:i:s'),'pendente','ativo');

		inserir('itemvisita',$campos_item,$valores_item);
	}


	if ( $_POST[finaliza_visita]=='sim' ) {
		index();
	} else {
		$_POST[id]=$id;
		modifica_visita();
	}
	exit;

}

function cadastra_cliente(){
	global $a_mod;
	
	$campos = array(enti_tx_nome,enti_tx_nascimento,enti_tx_sexo,enti_tx_civil,enti_tx_rg,enti_tx_cpf,enti_tx_endereco,enti_tx_complemento,
		enti_tx_bairro,enti_tx_cep,enti_nb_cidade,enti_tx_fone1,enti_tx_cel,enti_tx_email,enti_nb_corretor,enti_tx_obs,enti_tx_tipo,enti_tx_status);
	$valores = array($_POST[nome],data($_POST[nascimento]),$_POST[sexo],$_POST[civil],$_POST[rg],$_POST[cpf],$_POST[endereco],$_POST[complemento],
		$_POST[bairro],$_POST[cep],$_POST[cidade],$_POST[fone1],$_POST[cel],$_POST[email],$_POST[corretor],$_POST[obs],'Cliente','ativo');

	array_push($campos, enti_tx_nomeConjugue,enti_tx_nascimentoConjugue,enti_tx_sexoConjugue,enti_tx_rgConjugue,enti_tx_cpfConjugue,
						enti_tx_possui3AnosFgts,enti_tx_usaFgts);
	array_push($valores, $_POST[nome_conjugue],data($_POST[nascimento_conjugue]),$_POST[sexo_conjugue],$_POST[rg_conjugue],$_POST[cpf_conjugue],
						$_POST[possui_3anos_fgts],$_POST[utilizar_fgts]);

	if(!$_POST[id]){
		array_push($campos, enti_nb_userCadastro,enti_tx_dataCadastro);
		array_push($valores, $_SESSION[user_nb_id],date("Y-m-d"));

		$id=inserir('entidade',$campos,$valores);
		$id2 = ultimo_reg('entidade');
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

	$_POST[id_cliente]=$id;
	layout_visita();
	exit;

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
	$c[]=campo('Nascimento','nascimento',data($a_mod[enti_tx_nascimento]),2,'',' maxlength="10" onkeyup="mascara_data_tmp(this);" ');
	$c[]=combo('Sexo','sexo',$a_mod[enti_tx_sexo],2,array('','Masculino','Feminino'));
	// $c[]=combo('Estado Civil','civil',$a_mod[enti_tx_civil],2,array('','Casado(a)','Solteiro(a)','Viúvo(a)'));
	$c[]=combo('Estado Civil','civil',$a_mod[enti_tx_civil],2,array('','Casado(a)','União Estável','Solteiro(a)','Viúvo(a)'),' onchange="verifica_estado_civil(this.value)" id="civil" ');
	$c[]=campo('RG','rg',$a_mod[enti_tx_rg],2);
	
	$c[]=campo('CPF/CNPJ','cpf',$a_mod[enti_tx_cpf],2,'',' maxlength="18" onkeyup="mascara_cnpj_tmp(this);" ');
	$c[]=campo('Endereço','endereco',$a_mod[enti_tx_endereco],4);
	$c[]=campo('Complemento','complemento',$a_mod[enti_tx_complemento],4);
	$c[]=campo('Bairro','bairro',$a_mod[enti_tx_bairro],2);
	
	// $c[]=campo('CEP','cep',$a_mod[enti_tx_cep],2,'','maxlength="9" onkeyup="mascara_cep_tmp(this);"');
	$c[]=campo('CEP','cep',$a_mod[enti_tx_cep],2,'','maxlength="9" onkeyup="mascara_cep_tmp(this);carrega_cep(this.value);"');
	$c[]=combo_net('Cidade','cidade',$a_mod[enti_nb_cidade],3,'cidade');
	$c[]=campo('Telefone','fone1',$a_mod[enti_tx_fone1],2,'',' maxlength="15" onkeyup="mascara_telefone_tmp(this);" ');
	$c[]=campo('Celular','cel',$a_mod[enti_tx_cel],2,'',' maxlength="15" onkeyup="mascara_telefone_tmp(this);" ');
	$c[]=campo('E-mail','email',$a_mod[enti_tx_email],3);
	
	$c[]=combo_net('Corretor','corretor',$a_mod[enti_nb_corretor],3,'entidade','',"AND enti_tx_tipo='Corretor'");
	$c[]=combo('Possui mais de 3 anos de FGTS?','possui_3anos_fgts',$a_mod[enti_tx_possui3AnosFgts],3,array('','Sim','Não'));
	$c[]=combo('Utilizar FGTS?','utilizar_fgts',$a_mod[enti_tx_usaFgts],2,array('','Sim','Não'));
	// $c[]=arquivo('Arquivo','arquivo','',3);


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




function excluir_item(){
	remover('itemvisita',$_POST[id]);

	$_POST[id] = $_POST[id_visita];

	modifica_visita();
	exit;
}





function layout_visita(){
	global $a_mod;
	cabecalho("Gestão de Visitas");



	if( $_POST[id]==0 && $_SESSION[user_tx_nivel]=='Corretor' ) {
		$a_user = carregar('user',$_SESSION[user_nb_id]);
		if ( $a_user[user_nb_entidade]>0 ){
			$a_mod[visi_nb_corretor] = $a_user[user_nb_entidade];
			$extra_cliente = " AND enti_nb_corretor='$a_user[user_nb_entidade]' ";
		}
	}

	if ( $_POST[id_cliente]>0 ) {
		$a_mod[visi_nb_entidade] = $_POST[id_cliente];
	}


	// DADOS DA VISITA
	$c[]=campo_data('Data','data',$a_mod[visi_tx_dataAgenda],2);
	$c[]=campo_hora('Hora','hora',$a_mod[visi_tx_horaAgenda],1);
	$c[]=combo_net('Cliente','cliente',$a_mod[visi_nb_entidade],4,'entidade','',"AND enti_tx_tipo = 'Cliente' $extra_cliente");
	$c[]=combo_net('Corretor','corretor',$a_mod[visi_nb_corretor],3,'entidade','',"AND (enti_tx_tipo = 'Corretor' OR enti_tx_tipo = 'Administrador') ");
	$c[]=combo('Finalidade','finalidade',$a_mod[visi_tx_finalidade],2,array("","Venda","Aluguel"));

	// DADOS DO IMÓVEL
	$c2[]=ckeditor('Descrição Imóvel','descricao','',12);
	$c2[]=combo_net('Imóvel','imovel','',3,'imovel');
	$c2[]=combo_net('Cidade','cidade','',3,'cidade');
	$c2[]=campo('Bairro','bairro','',2);
	$c2[]=combo_bd('!Tipo','tipo','',2,'tipoimovel');
	$c2[]=combo_bd('!Construtor','construtor','',2,'construtor');

	// BOTÕES
	if ( $_POST[id]>0 ) {// SÓ CRIA O BOTÃO DE FINALIZAR SE ADICIONAR ALGUMN IMÓVEL
		$b[]='<button onclick="finaliza_visita('.$_POST[id].');" type="button" class="btn default">Finalizar</button>';
	}
	$b[]=botao('Adicionar','cadastra_item_visita','id',$_POST[id]);
	$b[]=botao('Inserir Cliente','layout_cliente');
	$b[]=botao('Voltar','index');


	abre_form('Dados da Visita');

	linha_form($c);
	echo "<br>";
	fieldset('Dados do imóvel');
	linha_form($c2);
	fecha_form($b);


	if ( $_POST[id]>0 ) {
		$sql_item = query("SELECT * FROM itemvisita WHERE item_nb_visita='$_POST[id]' AND item_tx_status!='inativo' ORDER BY item_nb_id DESC ");
		while ( $a_item = carrega_array($sql_item) ) {

			$nome_imovel     = '';
			$nome_cidade     = '';
			$nome_bairro     = '';
			$nome_tipo       = '';
			$nome_construtor = '';

			// VERIFICA SE IDENTIFICOU O IMÓVEL
			if ( $a_item[item_nb_imovel]>0 ) {
				$a_imovel = carregar('imovel',$a_item[item_nb_imovel]);
				$nome_imovel = $a_imovel[imov_tx_nome];

				if ($a_imovel[imov_nb_cidade]>0) {
					$a_cidade = carregar('cidade',$a_imovel[imov_nb_cidade]);
					$nome_cidade = $a_cidade[cida_tx_nome];
				}
				$nome_bairro = $a_imovel[imov_tx_bairro];

				if ($a_imovel[imov_nb_tipoimovel]>0) {
					$a_tipo = carregar('tipoimovel',$a_imovel[imov_nb_tipoimovel]);
					$nome_tipo = $a_tipo[tipo_tx_nome];
				}
				if ($a_imovel[imov_nb_construtor]>0) {
					$a_construtor = carregar('construtor',$a_imovel[imov_nb_construtor]);
					$nome_construtor = $a_construtor[cons_tx_nome];
				}

			} else {
				// SE TIVER PREENCHIDO OS CAMPOS
				$nome_imovel = $a_item[item_tx_descricao];

				if ($a_item[item_nb_cidade]>0) {
					$a_cidade = carregar('cidade',$a_item[item_nb_cidade]);
					$nome_cidade = $a_cidade[cida_tx_nome];
				}
				$nome_bairro = $a_item[item_tx_bairro];

				if ($a_item[item_nb_tipoimovel]>0) {
					$a_tipo = carregar('tipoimovel',$a_item[item_nb_tipoimovel]);
					$nome_tipo = $a_tipo[tipo_tx_nome];
				}
				if ($a_item[item_nb_construtor]>0) {
					$a_construtor = carregar('construtor',$a_item[item_nb_construtor]);
					$nome_construtor = $a_construtor[cons_tx_nome];
				}
			}


			$icone_excluir = icone_excluir($a_item[item_nb_id],'excluir_item','id_visita',$_POST[id]);

			$a_valores[] = array($a_item[item_nb_id],$nome_imovel,$nome_cidade,$nome_bairro,
							$nome_tipo,$nome_construtor,$icone_excluir);
		}

		$cabecalho = array('CÓD.','IMÓVEL','CIDADE','BAIRRO','TIPO','CONSTRUTOR','');
		grid2($cabecalho,$a_valores);
	}



	rodape();

	?>
	<form id='contex_icone_form' method="post" target="" action="">
		<input type="hidden" name="id" value="0">
		<input type="hidden" name="acao" value="sem_acao">
		<input type="hidden" id="hidden">
	</form>

	<input type="hidden" id="operacoes" value="">


	<!-- <style type="text/css">
		th { font-size: 10px !important; }
		td { font-size: 10px !important; }
	</style> -->
	<script type="text/javascript">
		function contex_icone(id,acao,campos='',valores='',target='',msg='',action=''){
			if(msg){
				if(confirm(msg)){
					var form = document.getElementById("contex_icone_form"); 
					form.target=target;
					form.action=action;
					form.id.value=id;
					form.acao.value=acao;
					if(campos){
						form.hidden.value=valores;
						form.hidden.name=campos;
					}
						// form.append('<input type="hidden" name="'+campos+'" value="'+valores+'" /> ');
					form.submit();
				}
			}else{
				var form = document.getElementById("contex_icone_form"); 
				form.target=target;
				form.action=action;
				form.id.value=id;
				form.acao.value=acao;
				if(campos){
					form.hidden.value=valores;
					form.hidden.name=campos;
				}
					// form.append('<input type="hidden" name="'+campos+'" value="'+valores+'" /> ');
				form.submit();
			}
		}
	</script>


	<script type="text/javascript">
		function remover_arquivo(id,acao,arquivo){
			if(confirm('Deseja realmente excluir o arquivo '+arquivo+'?')){
				document.form_excluir_arquivo.id_arquivo.value=id;
				document.form_excluir_arquivo.nome_arquivo.value=arquivo;
				document.form_excluir_arquivo.acao.value=acao;
				document.form_excluir_arquivo.submit();
			}
		}


		function finaliza_visita(id_visita){
			if ( confirm("Deseja concluir o cadastro da visita?") ) {
				var input0 = document.createElement('input');
				input0.type = 'hidden';
				input0.name = 'id';
				input0.value = id_visita;
				document.forms[0].appendChild(input0);

				var input0 = document.createElement('input');
				input0.type = 'hidden';
				input0.name = 'finaliza_visita';
				input0.value = 'sim';
				document.forms[0].appendChild(input0);

				var input0 = document.createElement('input');
				input0.type = 'hidden';
				input0.name = 'acao';
				input0.value = 'cadastra_item_visita';
				document.forms[0].appendChild(input0);
				
				document.forms[0].submit();
			}
		}
	</script>
	<?

}







function layout_atualizacao(){
	global $a_mod;
	cabecalho("Gestão de Visitas");

	$a_proc = carregar('visita',$_POST[id]);
	$a_clie = carregar('entidade',$a_proc[proc_nb_entidade]);
	$a_banc = carregar('banco',$a_proc[proc_nb_banco]);
	$a_imov = carregar('imovel',$a_proc[proc_nb_imovel]);
	// $a_atpr = carregar('atpr',$a_proc[visi_nb_id]);

	$c[]=texto('Código',$a_proc[visi_nb_id],1);
	$c[]=texto('Cliente',$a_clie[enti_tx_nome],3);
	$c[]=texto('Finalidade',$a_proc[proc_tx_finalidade],2);
	$c[]=texto('Banco',$a_banc[banc_tx_nome],2);
	$c[]=texto('Imóvel',$a_imov[imov_tx_nome],2);
	$c[]=texto('Situação',$a_proc[visi_tx_situacao],2);
	$c[]=texto('Descrição do Processo',$a_proc[proc_tx_nome],12);
	$c[]=texto('Observação',$a_proc[proc_tx_obs],6);
	$c[]=ckeditor('Atualização','atualizacao',$_POST[atualizacao],12);					
			
	$b[]=botao('Gravar','cadastra_atualizacao','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Processo');

	linha_form($c);
	fecha_form($b);

	rodape();

	?>

	<form name="form_excluir_arquivo" method="post" action="gestao_visita.php">
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
		mkdir($pasta_arquivos);
	}

	$qtde = count($_FILES["documentos"]["name"]);
	$permitidos = array("docx", "doc", "pdf", "xls", "xlsx");// EXTENÇÕES PERMITIDAS
	// $width  = 500;
	// $height = 334;


	// FAR UM PRIMEIRO LAÇO PARA CONFERIR SE TODAS AS IMAGENS FORAM ENVIADAS CORRETAMENTE
	for ($i=0; $i<$qtde; $i++) {
		// VERIFICA SE OCORREU ALGUN ERRO NO ENVIO DO ARQUIVO
		$erro = $_FILES['documentos']['error'][$i];
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
		// FAZ A PARTE DO ENVIO DAS IMAGENS

		$ext = pathinfo($_FILES["documentos"]["name"][$i], PATHINFO_EXTENSION);
		$ext = strtolower($ext);

		$nome_arquivo = ($i+1).'_'.time().'.'.$ext;
		$arquivo = $pasta_arquivos.'/'.$nome_arquivo;
		$arquivo_temporario = $_FILES['documentos']['tmp_name'][$i];

		// echo "<hr>|$arquivo|<hr>";


		// Pegamos sua largura e altura originais
		// list($width_orig, $height_orig) = getimagesize($arquivo_temporario);
		//Comparamos sua largura e altura originais com as desejadas
		// if($width_orig > $width || $height_orig > $height){
			// Chamamos a função que redimensiona a imagem
			// redimensionar($arquivo_temporario,$width,$height);
		// }

		if (move_uploaded_file($arquivo_temporario, $arquivo)) {
			$campos  = array(atpr_nb_processo,atpr_tx_nome,atpr_tx_nomedoc,atpr_tx_pastadoc,atpr_tx_dataCadastro,atpr_nb_userCadastro,atpr_tx_status);
			$valores = array($id,'Juntada de Documentos',$nome_arquivo,$pasta_arquivos,date('Y-m-d H:i:s'),$_SESSION[user_nb_id],'ativo');
			inserir('atpr',$campos,$valores);
		} else {
			// SE NÃO SALVAR, EXIBE UMA MENSAGEM DE ERRO
			set_status('ERRO: Falha ao salvar o arquivo no servidor!');
			index();
			exit;
		}
	}


	set_status('Documentos enviados com sucesso!');

	assina_visita();
	exit;
}

function assina_visita(){
	cabecalho("Gestão de Visitas");
	
	$a_proc = carregar('visita',$_POST[id]);
	$a_clie = carregar('entidade',$a_proc[proc_nb_entidade]);
	$a_banc = carregar('banco',$a_proc[proc_nb_banco]);
	$a_imov = carregar('imovel',$a_proc[proc_nb_imovel]);

	$c[]=texto('Código',$a_proc[visi_nb_id],1);
	$c[]=texto('Cliente',$a_clie[enti_tx_nome],3);
	$c[]=texto('Finalidade',$a_proc[proc_tx_finalidade],2);
	$c[]=texto('Banco',$a_banc[banc_tx_nome],2);
	$c[]=texto('Imóvel',$a_imov[imov_tx_nome],2);
	$c[]=texto('Situação',$a_proc[visi_tx_situacao],2);
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
	$val = array('atpr_nb_id','atpr_tx_nome','data(atpr_tx_dataCadastro,1)','user_tx_nome','icone_modificar(visi_nb_id,assina_visita,,,,glyphicon glyphicon-eye-open)','icone_excluir(atpr_nb_id,exclui_atualizacao,id2,'.$_POST[id].')');		
	
	grid($sql,$cab,$val);

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

	cabecalho("Gestão de Visitas");

	$a_proc = carregar('visita',$_POST[id]);

	$c[]=texto('Descrição',$a_proc[proc_tx_nome],6);


	$campo_arquivo = '<div class="col-sm-6 margin-bottom-5">
				<label><b>Documento (.pdf/.doc/.docx)</b></label>
				<input name="documentos[]" value="" autocomplete="off" type="file" multiple="multiple" class="form-control input-sm" >
			</div>';

	$c[]=$campo_arquivo;


	$b[]=botao('Gravar','enviar_documentos','id',$_POST[id]);
	$b[]=botao('Voltar','index');

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

	cabecalho("Gestão de Visitas");

	$a_proc = carregar('visita',$_POST[id]);

	$c[]=texto('Descrição',$a_proc[proc_tx_nome],6);


	$campo_arquivo = '<div class="col-sm-6 margin-bottom-5">
				<label><b>Imagens (500 X 334)</b></label>
				<input name="imagens[]" value="" autocomplete="off" type="file" multiple="multiple" class="form-control input-sm" >
			</div>';

	$c[]=$campo_arquivo;


	$b[]=botao('Gravar','enviar_imagens','id',$_POST[id]);
	$b[]=botao('Voltar','assina_visita','id',$_POST[id]);

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
							<!-- <a href="<?=$url?>" class="d-block mb-4 h-100">
								<img class="img-fluid img-thumbnail" src="<?=$url?>" alt="">
							</a> -->
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

function carrega_corretor($id_corretor){

	$a_corr = carregar('entidade',$id_corretor);

	return $a_corr[enti_tx_nome];
}





function cadastra_remarcacao(){

	if ( count($_POST[item])==0 ) {
		set_status('ATENÇÃO: Selecione quais imóveis devem ser remarcados!');
		layout_confirma_visita();
		exit;
	}


	$string_itens = @implode(',', $_POST[item]);


	$a_visita = carregar('visita',$_POST[id]);


	// CRIA UMA NOVA VISITA COM OS MESMOS DADOS (EXCETO DATA/HORA)
	$campos = array(visi_nb_entidade,visi_nb_corretor,visi_tx_finalidade,
		visi_tx_situacao,visi_tx_dataAgenda,visi_tx_horaAgenda,
		visi_tx_dataCadastro,visi_nb_userCadastro,visi_tx_status);
	
	$valores = array($a_visita[visi_nb_entidade],$a_visita[visi_nb_corretor],$a_visita[visi_tx_finalidade],
		'pendente',$_POST[data],$_POST[hora],
		date("Y-m-d"),$_SESSION[user_nb_id],'ativo');

	$id=inserir('visita',$campos,$valores);

	if ( $id>0 ) {
		// SE DER CERTO, ASSOCIA A VISITA REMARCADA COM A NOVA
		atualizar('visita',array(visi_nb_novaVisita,visi_tx_situacao),array($id,'remarcada'),$a_visita[visi_nb_id]);


		// PERCORRE OS IMÓVEIS SELECIONADOS REALIZANDO A REMARCAÇÃO DE CADA UM
		foreach ($_POST[item] as $key => $id_item) {
			$a_item = carregar('itemvisita',$id_item);

			// GERA O ITEM DA VISITA
			$campos_item = array(item_nb_imovel,item_nb_visita,
				item_tx_descricao,item_nb_cidade,item_tx_bairro,
				item_nb_tipoimovel,item_nb_construtor,
				item_nb_userCadastro,item_tx_dataCadastro,item_tx_situacao,item_tx_status);

			$valores_item= array($a_item[item_nb_imovel],$id,
				$a_item[item_tx_descricao],$a_item[item_nb_cidade],$a_item[item_tx_bairro],
				$a_item[item_nb_tipoimovel],$a_item[item_nb_construtor],
				$_SESSION[user_nb_id],date('Y-m-d H:i:s'),'pendente','ativo');

			// INSERE O NOVO ITEM
			$id_item_novo = inserir('itemvisita',$campos_item,$valores_item);

			// ATUALIZA O ITEM QUE FOI REMARCADO
			atualizar('itemvisita',array(item_nb_novoItem,item_tx_situacao),array($id_item_novo,'remarcado'),$a_item[item_nb_id]);
		}


		// ENCERRA OS ITENS QUE FORAM VISITADOS
		$sql_visitados = query("SELECT * FROM itemvisita WHERE item_nb_id NOT IN ($string_itens) AND item_nb_visita='$_POST[id]' AND item_tx_status!='inativo' ");
		while ( $a_item = carrega_array($sql_visitados) ) {
			atualizar('itemvisita',array(item_tx_situacao),array('encerrado'),$a_item[item_nb_id]);
		}

	} else {
		set_status('ATENÇÃO: FALHA AO REMARCAR A VISITA!');
		layout_confirma_visita();
		exit;
	}

	index();
	exit;
}





function layout_confirma_visita(){	
	cabecalho("Gestão de Visitas");



	$a_visita = carregar('visita',$_POST[id]);
	if ($a_visita[visi_nb_entidade]>0)
		$a_entidade = carregar('entidade',$a_visita[visi_nb_entidade]);
	if ($a_visita[visi_nb_corretor]>0)
		$a_corretor = carregar('entidade',$a_visita[visi_nb_corretor]);


	// DADOS DA VISITA
	$c[]=texto('Data',data($a_visita[visi_tx_dataAgenda]),2);
	$c[]=texto('Hora',$a_visita[visi_tx_horaAgenda],1);
	$c[]=texto('Cliente',$a_entidade[enti_tx_nome],3);
	$c[]=texto('Corretor',$a_corretor[enti_tx_nome],3);


	$c2[]=campo_data('Data','data','',2);
	$c2[]=campo_hora('Hora','hora','',1);


	// BOTÕES
	$b[]=botao('Gravar','cadastra_remarcacao','id',$_POST[id]);
	$b[]=botao('Voltar','index');


	abre_form('Dados da Visita');


	$sql_item = query("SELECT * FROM itemvisita WHERE item_nb_visita='$_POST[id]' AND item_tx_status!='inativo' ORDER BY item_nb_id DESC ");
	while ( $a_item = carrega_array($sql_item) ) {

		$nome_imovel     = '';
		$nome_cidade     = '';
		$nome_bairro     = '';
		$nome_tipo       = '';
		$nome_construtor = '';

		// VERIFICA SE IDENTIFICOU O IMÓVEL
		if ( $a_item[item_nb_imovel]>0 ) {
			$a_imovel = carregar('imovel',$a_item[item_nb_imovel]);
			$nome_imovel = $a_imovel[imov_tx_nome];

			if ($a_imovel[imov_nb_cidade]>0) {
				$a_cidade = carregar('cidade',$a_imovel[imov_nb_cidade]);
				$nome_cidade = $a_cidade[cida_tx_nome];
			}
			$nome_bairro = $a_imovel[imov_tx_bairro];

			if ($a_imovel[imov_nb_tipoimovel]>0) {
				$a_tipo = carregar('tipoimovel',$a_imovel[imov_nb_tipoimovel]);
				$nome_tipo = $a_tipo[tipo_tx_nome];
			}
			if ($a_imovel[imov_nb_construtor]>0) {
				$a_construtor = carregar('construtor',$a_imovel[imov_nb_construtor]);
				$nome_construtor = $a_construtor[cons_tx_nome];
			}

		} else {
			// SE TIVER PREENCHIDO OS CAMPOS
			$nome_imovel = $a_item[item_tx_descricao];

			if ($a_item[item_nb_cidade]>0) {
				$a_cidade = carregar('cidade',$a_item[item_nb_cidade]);
				$nome_cidade = $a_cidade[cida_tx_nome];
			}
			$nome_bairro = $a_item[item_tx_bairro];

			if ($a_item[item_nb_tipoimovel]>0) {
				$a_tipo = carregar('tipoimovel',$a_item[item_nb_tipoimovel]);
				$nome_tipo = $a_tipo[tipo_tx_nome];
			}
			if ($a_item[item_nb_construtor]>0) {
				$a_construtor = carregar('construtor',$a_item[item_nb_construtor]);
				$nome_construtor = $a_construtor[cons_tx_nome];
			}
		}


		$check = '<input type="checkbox" name="item[]" value="'.$a_item[item_nb_id].'">';

		$a_valores[] = array($check,$a_item[item_nb_id],$nome_imovel,$nome_cidade,$nome_bairro,
						$nome_tipo,$nome_construtor);
	}

	$cabecalho = array('&nbsp;','CÓD.','IMÓVEL','CIDADE','BAIRRO','TIPO','CONSTRUTOR');


	linha_form($c);

	echo "<br>";
	fieldset('Dados da nova visita');
	linha_form($c2);

	echo "<br>";
	fieldset('Confirme quais imóveis devem ser remarcados!');
	grid2($cabecalho,$a_valores);

	fecha_form($b);



	rodape();
}





function cadastra_assinatura(){
	// print_r($_POST);exit;
	$data_uri = $_POST[image];
	$encoded_image = explode(",", $data_uri)[1];
	$decoded_image = base64_decode($encoded_image);
	file_put_contents("arquivos/assinatura/visita/$_POST[id].png", $decoded_image);
	

	atualizar('visita',array(visi_tx_situacao),array('encerrado'),$_POST[id]);


	// VERIFICA SE TODOS OS IMÓVEIS FORAM VISITADOS
	if ($_POST[visita_completa]=='sim'){		

		// ENCERRA OS ITENS QUE FORAM VISITADOS
		$sql_visitados = query("SELECT * FROM itemvisita WHERE item_nb_visita='$_POST[id]' AND item_tx_status!='inativo' ");
		while ( $a_item = carrega_array($sql_visitados) ) {
			atualizar('itemvisita',array(item_tx_situacao),array('encerrado'),$a_item[item_nb_id]);
		}

		index();
	} else {
		layout_confirma_visita();
	}
	exit;
}



function layout_assina(){
	
	// print_r($_POST);
	// exit;

	cabecalho("Gestão de Visitas");

	?><script src="https://cdn.jsdelivr.net/npm/signature_pad@2.3.2/dist/signature_pad.min.js"></script><?


	// SCRIPTS DE CRIAÇÃO DOS MODAIS
	?>
		<link href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.4/jquery-ui.css" rel="stylesheet"/>
		<link href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.4/jquery-ui.theme.css" rel="stylesheet"/>
		<!-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script> -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.4/jquery-ui.js"></script>
		
		<div id="msgVisitados" style="display: none;">
			Todos os imóveis foram visitados?
		</div>

		<style type="text/css">
			.ui-widget {
				font-size: 80% !important;
			}
		</style>

	<?


	$a = carregar('visita',$_POST[id]);
	$a_clie = carregar('entidade',$a[visi_nb_entidade]);
	$a_corr = carregar('entidade',$a[visi_nb_corretor]);

	$c[] = texto('Cliente',$a_clie[enti_tx_nome],8);
	$c[] = texto('CPF',$a_enti[enti_tx_cpf],4);
	$c[] = texto('Assinatura','<canvas id="signature-pad" style="border: solid;" class="signature-pad" width=500 height=200></canvas>','12');

	$b[]=botao('Voltar','index');
	$b[]="<button name=\"acao\" id=\"salvar\" value=\"cadastra_assinatura\" type=\"button\" class=\"btn default\">Gravar</button>";
	$b[]=botao('Limpar','layout_assina','id',$_POST[id]);
	if(is_file("arquivos/assinatura/visita/$_POST[id].png")){
		$b[]="<a href=\"arquivos/assinatura/visita/$_POST[id].png\" class=\"btn default\" target=_blank>Visualizar</a>";
	}

	abre_form('Ficha de Visita');
	linha_form($c);

	fecha_form($b);

	rodape();

	?>

	<form name="form_assinatura" method="post">
		<input type="hidden" name="acao">
		<input type="hidden" name="image">
		<input type="hidden" name="visita_completa">
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

			var msgVisitados = $('#msgVisitados');
			
			msgVisitados.dialog({
				modal: true,
				buttons: {
					"Sim": function () {
						$(this).dialog('close');
						envia_assinatura('sim');
					},
					"Não": function () {
						$(this).dialog('close');
						envia_assinatura('nao');
					}
				}
			});
		});


		function envia_assinatura(conf_visita_completa){
			var imageData = signaturePad.toDataURL();
			document.form_assinatura.image.value=imageData;
			document.form_assinatura.acao.value='cadastra_assinatura';
			document.form_assinatura.visita_completa.value=conf_visita_completa;
			document.form_assinatura.id.value='<?=$_POST[id]?>';
			document.form_assinatura.submit();
		}


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
	cabecalho("Gestão de Visitas");



	if($_POST[busca_codigo])
		$extra .=" AND visi_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND enti_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_situacao])
		$extra .=" AND visi_tx_situacao = '$_POST[busca_situacao]'";

	if($_SESSION[user_tx_nivel]=='Corretor') {
		$a_user = carregar('user',$_SESSION[user_nb_id]);
		if ( $a_user[user_nb_entidade]>0 )
			$extra .= " AND visi_nb_corretor='$a_user[user_nb_entidade]' ";
	}

		
	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Cliente','busca_nome',$_POST[busca_nome],9);
	// $c[]=combo('Finalidade','busca_finalidade',$_POST[busca_finalidade],2,array("","Troca","Locação","Venda","Venda ou Locação"));
	$c[]=combo('Situação','busca_situacao',$_POST[busca_situacao],2,array("","Pendente","Encerrada","Remarcada"));
				
	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_visita');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM visita,entidade WHERE visi_tx_status != 'inativo' AND visi_nb_entidade = enti_nb_id $extra";
	
	$cab = array('CÓDIGO','CLIENTE','CORRETOR','DATA','HORA','SITUAÇÃO','','','','');
	
	$val = array('visi_nb_id','enti_tx_nome','carrega_corretor(visi_nb_corretor)','data(visi_tx_dataAgenda)','visi_tx_horaAgenda','visi_tx_situacao',
		'icone_modificar(visi_nb_id,imprime_visita,,,_blank,glyphicon glyphicon-print)',
		'icone_modificar(visi_nb_id,layout_assina,,,,glyphicon glyphicon-pencil)',
		'icone_modificar(visi_nb_id,modifica_visita)',
		'icone_excluir(visi_nb_id,exclui_visita)');		
	
	grid($sql,$cab,$val);

	rodape();

}


?>