<?php
	include "conecta.php";

	function cadastrar(){
		print_r($_POST);
		index();
	}

	function index(){
		cabecalho('Cadastro Endosso'.(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))? ' (Dev)': ''));

		$extra_bd_motorista = ' AND enti_tx_tipo = "Motorista"';
		if($_SESSION['user_tx_nivel'] != 'Super Administrador'){
			$extra_bd_motorista .= 'AND enti_tx_empresaCnpj = '.$_SESSION['user_tx_emprCnpj'];
		}

		$c = [
			campo_data('De:','data_de',$_POST['data_de'],2),
			campo_data('Ate:','data_ate',$_POST['data_ate'],2),
			combo_net('Motorista:','motorista',$_POST['motorista'],4,'entidade','',$extra_bd_motorista,'enti_tx_matricula')
		];
		if($_SESSION['user_tx_nivel'] == 'Super Administrador'){
			array_unshift($c, combo_net('Empresa:','empresa',$_POST['empresa'],4,'empresa'));
		}
		$b = [
			botao('Cadastrar Endosso', 'cadastrar')
		];

		abre_form();
		linha_form($c);
		fecha_form($b);
		
		rodape();
	}
?>