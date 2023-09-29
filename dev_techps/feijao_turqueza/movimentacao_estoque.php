<?php
include "conecta.php";






function adiciona_produto(){
	// global $a_prod;

	if ( intval($_POST[id])==0 ) {
		set_status('ERRO: Falha ao carregar os dados da movimentação!');
		index();
		exit;
	}

	$qtde_movimento = valor($_POST[quantidade]);

	if ( $_POST[tipo]!='Entrada' ) {// SE FOR UMA SAÍDA, LANÇA O VALOR COMO NEGATIVO
		$qtde_movimento = $qtde_movimento*(-1);
	}

	
	$campos  = array(esto_nb_movimentacao,esto_nb_produto,esto_tx_qtde,esto_nb_userCadastro,esto_tx_dataCadastro,esto_tx_tipo,esto_tx_status);
	$valores = array($_POST[id],$_POST[produto],$qtde_movimento,$_SESSION[user_nb_id],date('Y-m-d H:i:s'),'Movimentação','ativo');

	// $a_prod = array_combine($campos,$valores);
	// $a_prod[ajus_tx_nome] = $_POST[descricao];


	if ( intval($_POST[produto])==0 ) {
		set_status('ATENÇÃO: Selecione um produto!');
		layout_produtos();
		exit;
	}
	if ( valor($_POST[quantidade])==0 ) {
		set_status('ATENÇÃO: Informe a quantidade movimentada!');
		layout_produtos();
		exit;
	}


	$a_produto = carregar('produto',$_POST[produto]);

	$nova_qtde = ($a_produto[prod_tx_qtde]+$qtde_movimento);

	array_push($campos, esto_tx_qtdeDepois,esto_tx_qtdeAntes);
	array_push($valores, $nova_qtde,$a_produto[prod_tx_qtde]);

	inserir('estoque',$campos,$valores);

	atualizar('produto',array(prod_tx_qtde),array($nova_qtde),$_POST[produto]);



	set_status('Movimentação lançada com sucesso!');

	// unset($a_prod);
	layout_produtos();
	exit;
}




function layout_produtos(){
	global $a_prod;


	if ( intval($_POST[id])==0 ) {
		set_status('ERRO: Falha ao carregar os dados da movimentação!');
		index();
		exit;
	}

	cabecalho("Movimentação de Estoque");


	$a_movi = carregar('movimentacao',$_POST[id]);


	$c[] = texto('Tipo',$a_movi[movi_tx_tipo],2);
	$c[] = texto('Descrição',$a_movi[movi_tx_nome],10);


	if ( $_POST[visualiza_ajuste]=='sim' ) {
		$botao[] = botao('Voltar','index');
	} else {

		$c[] = combo_net('* Produto','produto',$a_prod[esto_nb_produto],6,'produto','',' AND prod_tx_servico IS NULL','prod_tx_codigoBarras');
		$c[] = campo('* Quantidade','quantidade',valor($a_prod[esto_tx_qtdeDepois]),2,'MASCARA_VALOR');

		$botao[] = botao('Gravar','adiciona_produto','id,tipo',"$_POST[id],$a_movi[movi_tx_tipo]");
		$botao[] = botao('Finalizar','index');
	}
	
	abre_form('Dados da Movimentação');
	linha_form($c);
	fecha_form($botao);


	// LISTA OS PRODUTOS QUE FORAM LANÇADOS NA MOVIMENTAÇÃO
	$sql = "SELECT * FROM estoque,produto,user WHERE esto_tx_status != 'inativo' AND esto_nb_movimentacao=$a_movi[movi_nb_id] AND esto_nb_produto=prod_nb_id AND esto_nb_userCadastro=user_nb_id ";
	$cab = array('CÓDIGO','PRODUTO','QTDE MOVIMENTADA','USUÁRIO','DATA');
	$val = array('esto_nb_id','prod_tx_nome','valor(esto_tx_qtde,1)','user_tx_nome','data(esto_tx_dataCadastro,1)');

	grid($sql,$cab,$val,'','','0','desc');

	rodape();
}




function cadastra_movimentacao(){
	global $a_mod;

	$nova_qtde = valor($_POST[quantidade]);
	
	$campos  = array(movi_tx_nome,movi_tx_tipo);
	$valores = array(addslashes($_POST[descricao]),$_POST[tipo]);

	$a_mod = array_combine($campos,$valores);


	if ( $_POST[tipo]=='' ) {
		set_status('ATENÇÃO: Selecione o tipo da movimentação!');
		layout_movimentacao();
		exit;
	}


	if ( $_POST[id]>0 ) {
		atualizar('movimentacao',$campos,$valores,$_POST[id]);

	} else {
		array_push($campos, movi_nb_userCadastro,movi_tx_dataCadastro,movi_tx_status);
		array_push($valores, $_SESSION[user_nb_id],date('Y-m-d H:i:s'),'ativo');

		inserir('movimentacao',$campos,$valores);
		$_POST[id] = ultimo_reg('movimentacao');
	}

	set_status('Movimentação lançada com sucesso!');


	layout_produtos();
	exit;
}





function modifica_movimentacao(){
	global $a_mod;

	$a_mod = carregar('movimentacao',$_POST[id]);

	layout_movimentacao();
	exit;
}


function modifica_movimentacao2(){

	$_POST[visualiza_ajuste] = 'sim';// SERVE PARA TRAVAR A TELA NO MODO DE VISUALIZAÇÃO
	layout_produtos();
	exit;
}




function layout_movimentacao(){
	global $a_mod;

	cabecalho("Movimentação de Estoque");

	$c[] = combo('* Tipo','tipo',$a_mod[movi_tx_tipo],2,array('','Entrada','Saída'));
	$c[] = campo('Descrição','descricao',$a_mod[movi_tx_nome],10);

	$botao[] = botao('Avançar','cadastra_movimentacao','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados da Movimentação');
	linha_form($c);
	fecha_form($botao);

	rodape();
}




function index(){

	cabecalho("Movimentação de Estoque");

	if($_POST[busca_codigo])
		$extra .= " AND movi_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND movi_tx_nome LIKE '%$_POST[busca_nome]%'";

	if($_POST[busca_usuario])
		$extra .= " AND user_tx_nome LIKE '%$_POST[busca_usuario]%'";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Descrição','busca_nome',$_POST[busca_nome],6);
	$c[] = campo('Usuário','busca_usuario',$_POST[busca_usuario],4);

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_movimentacao');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM movimentacao,user WHERE movi_tx_status != 'inativo' AND movi_nb_userCadastro=user_nb_id $extra";
	$cab = array('CÓDIGO','DESCRIÇÃO','TIPO','USUÁRIO','DATA','');
	$val = array('movi_nb_id','movi_tx_nome','movi_tx_tipo','user_tx_nome','data(movi_tx_dataCadastro,1)','icone_modificar(movi_nb_id,modifica_movimentacao2)');

	grid($sql,$cab,$val,'','','0','desc');

	rodape();

}