<?php
	include "conecta.php";

	function cadastrar(){
		//print_r($_POST);
		//Array ( [empresa] => 3 [data_de] => 2023-09-01 [data_ate] => 2023-09-30 [motorista] => 99 [acao] => cadastrar )
		/*
			endo_nb_id: 			automático
			endo_nb_entidade:		$_POST['motorista']
			endo_tx_matricula:		fazer query
			endo_tx_mes:			mês de $_POST['data_de']
			endo_tx_saldo:			Nulo 							(temporário)
			endo_tx_de: 			$_POST['data_de']
			endo_tx_ate:			$_POST['data_ate']
			endo_tx_dataCadastro:	date('Y-m-d h:i:s')
			endo_nb_userCadastro:	$_SESSION['user_nb_id']
			endo_tx_status			'ativo'
		*/
		$error_msg = 'Há campos obrigatórios não preenchidos: ';
		if(!isset($_POST['empresa'])){
			if($_SESSION['user_tx_nivel'] == 'Super Administrador'){
				$error_msg .= 'Empresa, ';
			}else{
				$_POST['empresa'] = $_SESSION['user_tx_emprCnpj'];
			}
		}
		if(!isset($_POST['motorista'])){
			$error_msg .= 'Motorista, ';
		}
		if($_POST['data_de'] == '' || $_POST['data_ate'] == ''){
			$error_msg .= 'Data, ';
		}

		if(strlen($error_msg) > 43){
			echo "<script>alert('".substr($error_msg, 0, strlen($error_msg)-2)."')</script>";
			index();
			return;
		}

		index();
	}

	function index(){
		cabecalho('Cadastro Endosso'.(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))? ' (Dev)': ''));

		$extra_bd_motorista = ' AND enti_tx_tipo = "Motorista"';
		if($_SESSION['user_tx_nivel'] != 'Super Administrador'){
			$extra_bd_motorista .= 'AND enti_nb_empresa = '.$_SESSION['user_tx_emprCnpj'];
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