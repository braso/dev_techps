<?php

if ( $_GET['i']==1 && !isset($_POST['busca_data1']) ) {
	// SE ESTIVER VINDO DA TELA INICIAL DO SISTEMA
	$_POST['busca_data1']=date('Y-m-d');
	$_POST['busca_data2']=date('Y-m-d');
	$_POST['busca_situacao']='Ativos';
}


include "../conecta.php";





function excluir_alerta(){
	remover('alertavenda',$_POST[id]);

	index();
	exit;
}



function finaliza_alerta(){

	atualizar('alertavenda',array(aler_tx_status),array('finalizado'),$_POST[id]);

	set_status('Alerta Finalizado com sucesso!');
	index();
	exit;
}






function icone_finaliza($id,$acao,$campos='',$valores='',$target='',$icone='glyphicon glyphicon-search',$action='',$msg='',$title=''){
	$icone = 'glyphicon glyphicon-check';
	
	$title = 'Finalizar';

	$icone='class="'.$icone.'"';


	$a_alerta = carregar('alertavenda',$id);

	if ( $a_alerta[aler_tx_status]=='ativo' ) {
		$botao = "<center><a title=\"$title\" style='color:gray' onclick='javascript:contex_icone(\"$id\",\"$acao\",\"$campos\",\"$valores\",\"$target\",\"$msg\",\"$action\");' ><spam $icone></spam></a></center>";
	} else {
		$botao = "";
	}

	return $botao;
}





function index(){
	cabecalho("Relatório de Alertas");

	if($_POST[busca_codigo])
		$extra .=" AND aler_nb_id = '$_POST[busca_codigo]'";
	if($_POST[busca_nome])
		$extra .=" AND enti_tx_nome LIKE '%$_POST[busca_nome]%'";
	if($_POST[busca_cpf])
		$extra .=" AND enti_tx_cpf = '$_POST[busca_cpf]'";	
	if($_POST[busca_produto])
		$extra .=" AND prod_tx_nome LIKE '%$_POST[busca_produto]%'";

	if($_POST[busca_data1])
		$extra .= " AND aler_tx_data >= '$_POST[busca_data1]' ";
	if($_POST[busca_data2])
		$extra .= " AND aler_tx_data <= '$_POST[busca_data2]' ";

	if ($_POST[busca_situacao]=='Ativos') {
		$extra .= " AND aler_tx_status = 'ativo' ";
	} else if ($_POST[busca_situacao]=='Finalizados') {
		$extra .= " AND aler_tx_status = 'finalizado' ";
	} else {
		$extra .= " AND aler_tx_status != 'inativo' ";
	}

	
	$c[]=campo('Código','busca_codigo',$_POST[busca_codigo],2);
	$c[]=campo('Cliente','busca_nome',$_POST[busca_nome],4);
	$c[]=campo('CPF','busca_cpf',$_POST[busca_cpf],2,MASCARA_CPF);
	$c[]=campo('Produto/Serviço','busca_produto',$_POST[busca_produto],4);
	
	$c[]=campo_data('Data Inicial','busca_data1',$_POST[busca_data1],2);
	$c[]=campo_data('Data Final','busca_data2',$_POST[busca_data2],2);
	$c[]=combo('Situacao','busca_situacao',$_POST[busca_situacao],2,array('','Ativos','Finalizados'));
	
	$b[]=botao('Buscar','index');
	$b[]=botao('Inserir','layout_cliente');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM alertavenda,entidade,produto WHERE aler_nb_entidade=enti_nb_id AND aler_nb_produto=prod_nb_id AND enti_tx_status != 'inativo' AND prod_tx_status!='inativo' $extra";
	$cab = array('CÓDIGO','CLIENTE','CPF','PRODUTO','DATA ALERTA','SITUAÇÃO','','');
	$val = array('aler_nb_id','enti_tx_nome','enti_tx_cpf','prod_tx_nome','data(aler_tx_data)','ucfirst(aler_tx_status)','icone_finaliza(aler_nb_id,finaliza_alerta)',
		'icone_excluir(aler_nb_id,excluir_alerta)');

	grid($sql,$cab,$val);

	rodape();

}


?>