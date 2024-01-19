<?php
include "conecta.php";

function exclui_feriado(){

	remover('feriado',$_POST['id']);
	index();
	exit;

}
function modifica_feriado(){
	global $a_mod;

	$a_mod=carregar('feriado',$_POST['id']);

	layout_feriado();
	exit;

}

function cadastra_feriado(){
	
	$campos=array('feri_tx_nome','feri_tx_data','feri_tx_uf','feri_nb_cidade','feri_tx_status');
	
	$data = ($_POST['data'] == '') ? '0000-00-00' : $_POST['data'];
	$cidade = ($_POST['cidade'] == '') ? 00 : $_POST['cidade'];
	
	$valores=array($_POST['nome'],$data,$_POST['uf'],$cidade,'ativo');
	


	if($_POST['id']>0) {
		atualizar('feriado',$campos,$valores,$_POST['id']);
	} else {
		array_push($campos, 'feri_nb_userCadastro','feri_tx_dataCadastro');
		array_push($valores, $_SESSION['user_nb_id'],date("Y-m-d H:i:s"));

		inserir('feriado',$campos,$valores);
	}

	index();
	exit;
}


function layout_feriado(){
	global $a_mod;

	cabecalho("Cadastro de Feriado");

	$uf = array ('','AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MS', 'MT', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO');
	
	$c[] = campo('Nome','nome',$a_mod['feri_tx_nome'],4);
	$c[] = campo_data('Data','data',$a_mod['feri_tx_data'],2);
	$c[] = combo('Estadual','uf',$a_mod['feri_tx_uf'],2,$uf);
	$c[] = combo_net('Municipal','cidade',$a_mod['feri_nb_cidade'],4,'cidade','','','cida_tx_uf');

	$botao[] = botao('Gravar','cadastra_feriado','id',$_POST['id'],'','','btn btn-success');
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do Feriado');
	linha_form($c);
	fecha_form($botao);

	rodape();

}

function index(){

	cabecalho("Cadastro de Feriado");
	$extra = '';

	if($_POST['busca_codigo'])
		$extra .= " AND feri_nb_id LIKE '%$_POST[busca_codigo]%'";

	if($_POST['busca_nome'])
		$extra .= " AND feri_tx_nome LIKE '%$_POST[busca_nome]%'";

	if($_POST['busca_uf']){
		$extra .= " AND feri_tx_uf = '$_POST[busca_uf]'";
	}
	if($_POST['busca_cidade']){
		$extra .= " AND feri_nb_cidade = '$_POST[busca_cidade]'";
	}

	// EXIBE APENAS OS FeriadoS
	// $extra .= " AND feri_tx_feriado = 'sim'";

	$uf = array ('','AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MS', 'MT', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO');
	
	

	$c[] = campo('Código','busca_codigo',$_POST['busca_codigo'],2,'MASCARA_NUMERO','maxlength="6"');

	$c[] = campo('Nome','busca_nome',$_POST['busca_nome'],4, '', 'maxlength="65"');
	$c[] = combo('Estadual','busca_uf',$_POST['busca_uf'],2,$uf);
	$c[] = combo_net('Municipal','busca_cidade',$_POST['busca_cidade'],4,'cidade','','','cida_tx_uf');

	$botao[] = botao('Buscar','index');
	$botao[] = botao('Inserir','layout_feriado','','','','','btn btn-success');
	
	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($botao);

	$sql = "SELECT * FROM feriado LEFT JOIN cidade ON cida_nb_id = feri_nb_cidade WHERE feri_tx_status != 'inativo' $extra";
	$cab = array('CÓDIGO','NOME','DATA','ESTADUAL','MUNICIPAL','','');
	$val = array('feri_nb_id','feri_tx_nome','data(feri_tx_data)','feri_tx_uf','cida_tx_nome','icone_modificar(feri_nb_id,modifica_feriado)','icone_excluir(feri_nb_id,exclui_feriado)');

	grid($sql,$cab,$val);

	rodape();

}