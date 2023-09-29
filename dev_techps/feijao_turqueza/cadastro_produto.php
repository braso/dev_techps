<?php

if ( $_GET['i']==1 && !isset($_POST['busca_situacaoEstoque']) ) {
	// SE ESTIVER VINDO DA TELA INICIAL DO SISTEMA
	$_POST['busca_situacaoEstoque']='Menor ou igual ao Mínimo';
}


include "conecta.php";

function exclui_produto(){

	remover('produto',$_POST[id]);
	index();
	exit;

}
function modifica_produto(){
	global $a_mod;

	$a_mod=carregar('produto',$_POST[id]);

	layout_produto();
	exit;
}




function cadastra_produto(){
	global $a_mod;
	
	$campos  = array(prod_tx_nome,prod_tx_codigoBarras,prod_tx_preco,prod_tx_qtdeMinima,prod_nb_marca,prod_nb_categoria,prod_nb_unidade,prod_tx_custo,prod_tx_validade,prod_tx_status);
	$valores = array(trim($_POST[nome]),$_POST[codigo_barras],valor($_POST[preco]),valor($_POST[estoque_minimo]),$_POST[marca],$_POST[categoria],$_POST[unidade],valor($_POST[custo]),data($_POST[validade]),'ativo');

	$a_mod = array_combine($campos,$valores);


	if ( trim($_POST[nome])=='' ) {
		set_status('ATENÇÃO: Informe o nome');
		layout_produto();
		exit;
	}
	if ( trim($_POST[categoria])=='' ) {
		set_status('ATENÇÃO: Informe a catagoria do produto');
		layout_produto();
		exit;
	}
	if ( intval($_POST[unidade])==0 ) {
		set_status('ATENÇÃO: Selecione uma unidade de medida');
		layout_produto();
		exit;
	}


	if($_POST[id]>0) {
		atualizar('produto',$campos,$valores,$_POST[id]);
	} else {
		array_push($campos, prod_nb_userCadastro,prod_tx_dataCadastro);
		array_push($valores, $_SESSION[user_nb_id],date("Y-m-d H:i:s"));

		inserir('produto',$campos,$valores);
	}


	index();
	exit;
}


function layout_produto(){
	global $a_mod;

	cabecalho("Cadastro de Produtos");

	if($a_mod[prod_nb_unidade]==0)
		$a_mod[prod_nb_unidade]=1;


	$c[] = campo('* Nome','nome',$a_mod[prod_tx_nome],5);
	$c[] = campo('Código Barras','codigo_barras',$a_mod[prod_tx_codigoBarras],3);
	$c[] = campo('Preço','preco',valor($a_mod[prod_tx_preco]),1,'MASCARA_VALOR');
	$c[] = campo('Esto.&nbsp;Minimo','estoque_minimo',valor($a_mod[prod_tx_qtdeMinima]),2,'MASCARA_VALOR');
	$c[] = texto('Estoque',valor($a_mod[prod_tx_qtde],1),1);
	
	$c[] = combo_net('Marca','marca',$a_mod[prod_nb_marca],3,'marca');
	$c[] = combo_bd('!* Categoria','categoria',$a_mod[prod_nb_categoria],3,'categoria');
	$c[] = combo_bd('!* Unidade','unidade',$a_mod[prod_nb_unidade],2,'unidade');
	$c[] = campo('Custo','custo',valor($a_mod[prod_tx_custo]),2,'MASCARA_VALOR');
	$c[] = campo('Validade','validade',data($a_mod[prod_tx_validade]),2,'MASCARA_DATA');

	$botao[] = botao('Gravar','cadastra_produto','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do Produto');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function carrega_categoria($id){
	if($id>0){
		$a_categoria = carregar('categoria',$id);
		
		return $a_categoria[cate_tx_nome];
	}
}

function carrega_unidade($id){
	if($id>0){
		$a_unidade = carregar('unidade',$id);
		
		return $a_unidade[unid_tx_nome];
	}
}

function index(){

	cabecalho("Cadastro de Produtos");

	if($_POST[busca_codigo])
		$extra .= " AND prod_nb_id = '$_POST[busca_codigo]'";

	if($_POST[busca_nome])
		$extra .= " AND prod_tx_nome LIKE '%$_POST[busca_nome]%'";

	if($_POST[validade_inicial])
		$extra .= " AND prod_tx_validade >= '".data($_POST[validade_inicial])."' ";

	if($_POST[validade_final])
		$extra .= " AND prod_tx_validade <= '".data($_POST[validade_final])."' ";

	if($_POST[busca_situacaoEstoque]=='Menor ou igual ao Mínimo')
		$extra .= " AND prod_tx_qtde <= prod_tx_qtdeMinima AND prod_tx_qtdeMinima>0 ";

	if($_POST[busca_situacaoEstoque]=='Maior que o Mínimo')
		$extra .= " AND prod_tx_qtde > prod_tx_qtdeMinima AND prod_tx_qtdeMinima>0 ";

	if($_POST[busca_situacaoEstoque]=='Negativo')
		$extra .= " AND prod_tx_qtde < 0 ";

	if($_POST[busca_situacaoEstoque]=='Sem Estoque')
		$extra .= " AND (prod_tx_qtde = 0 OR prod_tx_qtde IS NULL) ";

	if ($_POST[busca_marca]!='') {
		$a_busca_marca = carregar('marca',$_POST[busca_marca]);
		$extra .= " AND prod_nb_marca='$_POST[busca_marca]' ";
	}

	if ($_POST[busca_categoria]!='') {
		$a_busca_cate = carregar('categoria',$_POST[busca_categoria]);
		$extra .= " AND prod_nb_categoria='$_POST[busca_categoria]' ";
	}



	// NÃO EXIBE OS SERVIÇOS
	$extra .= " AND (prod_tx_servico IS NULL OR prod_tx_servico = '')";

	$c[] = campo('Código','busca_codigo',$_POST[busca_codigo],2,'MASCARA_NUMERO');
	$c[] = campo('Nome','busca_nome',$_POST[busca_nome],6);
	$c[] = campo('Validade Inicial','validade_inicial',$_POST[validade_inicial],2,'MASCARA_DATA');
	$c[] = campo('Validade Final','validade_final',$_POST[validade_final],2,'MASCARA_DATA');

	$c[] = combo('Situação Estoque','busca_situacaoEstoque',$_POST[busca_situacaoEstoque],3,array('','Menor ou igual ao Mínimo','Maior que o Mínimo','Negativo','Sem Estoque'));
	$c[] = combo_net('Marca','busca_marca',$a_busca_marca[marc_nb_id],3,'marca');
	$c[] = combo_bd('!Categoria','busca_categoria',$a_busca_cate[cate_nb_id],3,'categoria');


	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_produto');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM produto WHERE prod_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','CATEGORIA','UNIDADE','PREÇO','ESTOQUE ATUAL','ESTOQUE MINIMO','VALIDADE','','');
	$val = array('prod_nb_id','prod_tx_nome','carrega_categoria(prod_nb_categoria)','carrega_unidade(prod_nb_unidade)','valor(prod_tx_preco)','valor(prod_tx_qtde)','valor(prod_tx_qtdeMinima)','data(prod_tx_validade)','icone_modificar(prod_nb_id,modifica_produto)','icone_excluir(prod_nb_id,exclui_produto)');

	grid($sql,$cab,$val);

	rodape();

}