<?php
	include "conecta.php";

	function index(){
		cabecalho('Cadastro Endosso'.(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))? ' (Dev)': ''));

		var_dump($_SESSION);
		
		rodape();
	}
?>