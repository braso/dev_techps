<?php

function cadastro_ftp(){
    
    $campos=array(ftp_tx_host,ftp_tx_username,ftp_vb_password);

	$valores=array($_POST[host],$_POST[usuario], $_POST[senha]);

	// if($_POST[id]>0){
	// 	atualizar('macroponto',$campos,$valores,$_POST[id]);
	// }

	index();
	exit;
}

function index(){
	global $a_mod;

	cabecalho("Cadastro FTP");

	$c[] = campo('Host','host',$a_mod[ftp_tx_host],6,'','');
	$c[] = campo('Usuário','usuario',$a_mod[ftp_tx_username],3,'','readonly=readonly');
	$c[] = campo('Senha','senha',$a_mod[ftp_vb_password],3);

	$botao[] = botao('Gravar','cadastra_macro','id',$_POST[id]);
	$botao[] = botao('Voltar','index');
	
	abre_form('Dados do FTP');
	linha_form($c);
	fecha_form($botao);

	rodape();

}



// INSERT INTO ftp (ftp_tx_host,ftp_tx_username,ftp_vb_password) VALUE ("ftp-jornadas.positronrt.com.br",08995631000108, AES_ENCRYPT(0899,08995631000108));

// SELECT AES_DECRYPT(ftp_vb_password,8995631000108) AS ftp_vb_password FROM ftp WHERE ftp_tx_username = 8995631000108;

?>