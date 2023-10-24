<?php

include "conecta.php";

function exclui_motivo(){
	remover('motivo',$_POST['id']);
	index();
	exit;
}

function modifica_motivo(){
	global $a_mod;
	$a_mod = carregar('motivo',$_POST['id']);
	layout_motivo();
	exit;
}

function cadastra_motivo(){	
	$campos = ['moti_tx_nome', 'moti_tx_tipo', 'moti_tx_status'];
	$valores = [$_POST['nome'], $_POST['tipo'], 'ativo'];

	$legendas = [
		'Incluída Manualmente' => 'I',
		'Pré-Assinalada' => 'P',
		'Outras fontes de marcação' => 'T',
		'Descanso Semanal Remunerado e Abono' => 'DSR'
	];
	//Testes
		print_r('post legenda: '.$_POST['legenda'].'<br>'.
			'legendas: '.var_dump($legendas).'<br>'.
			'legendas[incluida manualmente]: '.$legendas['Incluída Manualmente'].'<br>'.
			'res: '.$legendas[$_POST['legenda']].'<br>'
		);
		index();
		exit;
	//

	$campos[] = 'moti_tx_legenda';
	$valores[] = $legendas[$_POST['legenda']];
	
	if($_POST['id']>0) {
		atualizar('motivo',$campos,$valores,$_POST['id']);
	} else {
		array_push($campos, 'moti_nb_userCadastro','moti_tx_dataCadastro');
		array_push($valores, $_SESSION['user_nb_id'],date("Y-m-d H:i:s"));
		inserir('motivo',$campos,$valores);
	}
	index();
	exit;
}

function layout_motivo(){
	global $a_mod;
	cabecalho("Cadastro de Motivo");

	$c = [
		campo('Nome','nome',$a_mod['moti_tx_nome'],6),
		combo('Tipo','tipo',$a_mod['moti_tx_tipo'],2,['Ajuste','Abono']),
		combo('Legenda de Marcação','legenda',$a_mod['moti_nb_legenda'],4,mb_convert_encoding(['Incluída Manualmente', 'Pré-assinalada', 'Outras fontes de marcação', 'Descanso Semanal Remunerado e Abono'], 'UTF-8'))
	];
	$botao = [
		botao('Gravar','cadastra_motivo','id',$_POST['id']),
		botao('Voltar','index')
	];

	abre_form('Dados do Motivo');
	linha_form($c);
	fecha_form($botao);

	rodape();
}

function index(){
	cabecalho("Cadastro de Motivo");

	$extra = 
		(isset($_POST['busca_codigo'])? " AND moti_nb_id = '".$_POST['busca_codigo']."'": '').
		(isset($_POST['busca_nome'])? " AND moti_tx_nome LIKE '%".$_POST['busca_nome']."%'": '').
		(isset($_POST['busca_tipo'])? " AND moti_tx_tipo LIKE '%".$_POST['busca_tipo']."%'": '');

	$c = [
		campo('Código','busca_codigo',$_POST['busca_codigo'],2,'MASCARA_NUMERO'),
		campo('Nome','busca_nome',$_POST['busca_nome'],6),
		combo('Tipo','busca_tipo',$_POST['busca_tipo'],4,array('','Ajuste','Abono'))
	];
	$botao = [
		botao('Buscar','index'),
		botao('Inserir','layout_motivo')
	];

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM motivo WHERE moti_tx_status != 'inativo' $extra";
	$cab = ['CÓDIGO','NOME','TIPO','',''];
	$val = ['moti_nb_id','moti_tx_nome','moti_tx_tipo','icone_modificar(moti_nb_id,modifica_motivo)','icone_excluir(moti_nb_id,exclui_motivo)'];
	grid($sql,$cab,$val);

	rodape();
}
?>