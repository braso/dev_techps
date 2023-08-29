<?php
include "conecta.php";


function index(){
	cabecalho("Configurações");
	
	$c[]=campo('Quantidade de Caracteres Minimos','caracteresMinimos',$_POST[caracteresMinimos],1);
	$c[]=combo('Nível','busca_nivel',$_POST[busca_nivel],2,array("","Administrador","Funcionário"));

	$b[]=botao('Buscar','index');
	
	if($_SESSION[user_tx_nivel] == 'Administrador');
		$b[]=botao('Inserir','layout_usuario');

	abre_form('Filtro de Busca');
	linha_form($c);
	fecha_form($b);

	$sql = "SELECT * FROM user WHERE user_tx_status != 'inativo' AND user_nb_id > 1 $extra";
	$cab = array('CÓDIGO','NOME','LOGIN','NÍVEL','','');
	$val = array('user_nb_id','user_tx_nome','user_tx_login','user_tx_nivel','icone_modificar(user_nb_id,modifica_usuario)',
			'icone_excluir(user_nb_id,exclui_usuario)');

	

	grid($sql,$cab,$val);

	rodape();

}


?>