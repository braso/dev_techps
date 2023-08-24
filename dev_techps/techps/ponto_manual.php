<?php 

include "conecta.php";

function index(){
	global $CACTUX_CONF,$conn,$CONTEX;

	cabecalho('Registro de Ponto Manual',1);

	$c[] = campo('Código:', 'busca_codigo', $_POST[busca_codigo], 2);

    
	
// 	//BOTOES
// 	$b[] = botao("Voltar",'index');
// 	$b[] = botao("Gravar",'cadastra_abono');

	abre_form('Inicio de Jornada');
	linha_form($c);
	fecha_form($b);

	rodape();

	
	
}
?>