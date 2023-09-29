<?php
include "../conecta.php";


function exclui_entidade(){
	remover('entidade',$_POST[id]);

	index();
	exit;
}

function modifica_entidade(){
	global $a_mod;

	$a_mod = carregar('entidade',$_POST[id]);

	layout_entidade();
	exit;
}


function cadastra_entidade(){

	$campos = array(enti_tx_nome,enti_tx_cpf,enti_tx_cel,enti_tx_fone1,enti_tx_email,enti_tx_endereco,enti_tx_numero,
		enti_tx_bairro,enti_nb_cidade,enti_tx_tipo,enti_tx_status);
	$valores = array($_POST[nome],$_POST[cpf],$_POST[celular],$_POST[telefone],$_POST[email],$_POST[endereco],$_POST[numero],
		$_POST[bairro],$_POST[cidade],'Entidade','ativo');
	
	if(!$_POST[id]){
		inserir('entidade',$campos,$valores);
	}else{
		atualizar('entidade',$campos,$valores,$_POST[id]);
	}
		index();
		exit;

}



function layout_entidade(){
	global $a_mod;
	cabecalho("Cadastro de Entidade");

	$c[]=campo('Nome','nome',$a_mod[enti_tx_nome],4);
	$c[]=campo('CNPJ/CPF','cpf',$a_mod[enti_tx_cpf],2,'MASCARA_CPF');
	$c[]=campo('Celular','celular',$a_mod[enti_tx_cel],2,'MASCARA_CEL');
	$c[]=campo('Telefone','telefone',$a_mod[enti_tx_fone1],2,'MASCARA_FONE');
	$c[]=campo('E-mail','email',$a_mod[enti_tx_email],3);
	$c[]=campo('Endereço','endereco',$a_mod[enti_tx_endereco],4);
	$c[]=campo('Número','numero',$a_mod[enti_tx_numero],1,'MASCARA_NUMERO');
	$c[]=campo('Bairro','bairro',$a_mod[enti_tx_bairro],3);
	$c[]=combo_net('Cidade','cidade',$a_mod[enti_nb_cidade],3,'cidade');
	
	
	$b[]=botao('Gravar','cadastra_entidade','id',$_POST[id]);
	$b[]=botao('Voltar','index');

	abre_form('Dados do Entidade');
	linha_form($c);
	fecha_form($b);

	rodape();

}


function index(){
	cabecalho("Cadastro de Entidade");

	if($_POST[busca_codigo])
		$extra .=" AND enti_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND enti_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_tipo])
		$extra .=" AND enti_tx_tipo = '$_POST[busca_tipo]'";
	
	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],1);
	$c[]=campo('Nome','busca_nome',$_POST[busca_nome],4);
	$c[]=campo('CPF','busca_cpf',$_POST[busca_cpf],3,'MASCARA_CPF');
	$c[]=combo('Nível','nivel',$a_mod[user_tx_nivel],4,array("Entidade","Cliente","Administrador"));

	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_entidade');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM entidade WHERE enti_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','CPF','NÍVEL','','');
	$val = array('enti_nb_id','enti_tx_nome','enti_tx_cpf','enti_tx_tipo','icone_modificar(enti_nb_id,modifica_entidade)',
		'icone_excluir(enti_nb_id,exclui_entidade)');

	grid($sql,$cab,$val);

	rodape();

}


?>

