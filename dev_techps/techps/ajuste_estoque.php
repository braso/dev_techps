<?php
include "conecta.php";



function cadastra_ajuste(){
	global $a_mod;

	$nova_qtde = valor($_POST[quantidade]);
	
	$campos  = array(esto_nb_produto,esto_tx_qtdeDepois,esto_nb_userCadastro,esto_tx_dataCadastro,esto_tx_tipo,esto_tx_status);
	$valores = array($_POST[produto],$nova_qtde,$_SESSION[user_nb_id],date('Y-m-d H:i:s'),'Ajuste','ativo');

	$a_mod = array_combine($campos,$valores);
	$a_mod[ajus_tx_nome] = $_POST[descricao];


	if ( intval($_POST[produto])==0 ) {
		set_status('ATENÇÃO: Selecione um produto!');
		layout_ajuste();
		exit;
	}


	// SE PASSAR NA VALIDAÇÃO, VERIFICA SE O REGISTRO DO AJUSTE JÁ FOI CRIADO
	if ( $_POST[id]==0 ) {
		$campos_ajuste  = array(ajus_tx_nome,ajus_nb_userCadastro,ajus_tx_dataCadastro,ajus_tx_status);
		$valores_ajuste = array(addslashes($_POST[descricao]),$_SESSION[user_nb_id],date('Y-m-d H:i:s'),'ativo');
		inserir('ajuste',$campos_ajuste,$valores_ajuste);
		$_POST[id] = ultimo_reg('ajuste');
	}



	$a_produto = carregar('produto',$_POST[produto]);

	array_push($campos, esto_nb_ajuste,esto_tx_qtde,esto_tx_qtdeAntes);
	array_push($valores, $_POST[id],($nova_qtde-$a_produto[prod_tx_qtde]),$a_produto[prod_tx_qtde]);

	inserir('estoque',$campos,$valores);

	atualizar('produto',array(prod_tx_qtde),array($nova_qtde),$_POST[produto]);



	set_status('Ajuste lançado com sucesso!');

	unset($a_mod);
	modifica_ajuste();
	exit;
}



function modifica_ajuste(){
	global $a_mod;

	$a_mod = carregar('ajuste',$_POST[id]);

	layout_ajuste();
	exit;
}



function modifica_ajuste2(){
	global $a_mod;

	$a_mod = carregar('ajuste',$_POST[id]);
	$_POST[visualiza_ajuste] = 'sim';// SERVE PARA TRAVAR A TELA NO MODO DE VISUALIZAÇÃO

	layout_ajuste();
	exit;
}




function layout_ajuste(){
	global $a_mod;

	cabecalho("Ajuste de Estoque");


	if ( $_POST[visualiza_ajuste]=='sim' ) {
		$c[] = texto('Descrição',$a_mod[ajus_tx_nome],12);

	} else {
		$c[] = campo('Descrição','descricao',$a_mod[ajus_tx_nome],12);

		$c[] = combo_net('* Produto','produto',$a_mod[esto_nb_produto],6,'produto','',' AND prod_tx_servico IS NULL','prod_tx_codigoBarras');
		$c[] = campo('Nova Quantidade','quantidade',valor($a_mod[esto_tx_qtdeDepois]),2,'MASCARA_VALOR');

		$botao[] = botao('Gravar','cadastra_ajuste','id',$_POST[id]);
	}
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do Ajuste');
	linha_form($c);
	fecha_form($botao);


	if ( $a_mod[ajus_nb_id]>0 ) {
		$sql = "SELECT * FROM estoque,produto,user WHERE esto_tx_status != 'inativo' AND esto_nb_ajuste=$a_mod[ajus_nb_id] AND esto_nb_produto=prod_nb_id AND esto_nb_userCadastro=user_nb_id $extra";
		$cab = array('CÓDIGO','PRODUTO','ESTOQUE ANTERIOR','AJUSTE','ESTOQUE DEPOIS','USUÁRIO','DATA');
		$val = array('esto_nb_id','prod_tx_nome','valor(esto_tx_qtdeAntes,1)','valor(esto_tx_qtde,1)','valor(esto_tx_qtdeDepois,1)','user_tx_nome','data(esto_tx_dataCadastro,1)');

		grid($sql,$cab,$val,'','','0','desc');
	}

	rodape();
}




function index(){

	cabecalho("Ajuste de Estoque");

	if($_POST[busca_codigo])
		$extra .= " AND ajus_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND ajus_tx_nome LIKE '%$_POST[busca_nome]%'";

	if($_POST[busca_usuario])
		$extra .= " AND user_tx_nome LIKE '%$_POST[busca_usuario]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Descrição','busca_nome',$_POST[busca_nome],6);
	$c[] = campo('Usuário','busca_usuario',$_POST[busca_usuario],4);

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_ajuste');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM ajuste,user WHERE ajus_tx_status != 'inativo' AND ajus_nb_userCadastro=user_nb_id $extra";
	$cab = array('CÓDIGO','DESCRIÇÃO','USUÁRIO','DATA','');
	$val = array('ajus_nb_id','ajus_tx_nome','user_tx_nome','data(ajus_tx_dataCadastro,1)','icone_modificar(ajus_nb_id,modifica_ajuste2)');

	grid($sql,$cab,$val,'','','0','desc');

	rodape();

}