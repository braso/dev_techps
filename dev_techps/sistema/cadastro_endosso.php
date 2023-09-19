<?php
	include "conecta.php";

	function voltar(){
		header('Location: '.$_SERVER['REQUEST_URI'].'/../endosso');
	}

	function cadastrar(){
		$teste = true;
		//print_r($_POST);
		//Array ( [empresa] => 3 [data_de] => 2023-09-01 [data_ate] => 2023-09-30 [motorista] => 99 [acao] => cadastrar )
		/*
			endo_nb_id: 			automático
			endo_nb_entidade:		$_POST['busca_motorista']
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
		if(!isset($_POST['busca_motorista'])){
			$error_msg .= 'Motorista, ';
		}
		if($_POST['data_de'] == '' || $_POST['data_ate'] == ''){
			$error_msg .= 'Data, ';
		}

		if(strlen($error_msg) > 43 and !$teste){
			echo "<script>alert('".substr($error_msg, 0, strlen($error_msg)-2)."')</script>";
			index();
			return;
		}
		
		$matricula = query("SELECT endo_tx_matricula FROM endosso WHERE endo_nb_entidade = '".$_POST['busca_motorista']."' LIMIT 1;");
		$matricula = carrega_array($matricula);

		$novo_endosso = [
			'endo_nb_entidade' => $_POST['busca_motorista'],
			'endo_tx_matricula' => $matricula['endo_tx_matricula'],
			'endo_tx_mes' => substr($_POST['data_de'], 0, 8).'01',
			'endo_tx_de' => $_POST['data_de'],
			'endo_tx_ate' => $_POST['data_ate'],
			'endo_tx_dataCadastro' => date('Y-m-d h:i:s'),
			'endo_nb_userCadastro' => $_SESSION['user_nb_id'],
			'endo_tx_status' => 'ativo'
		];

		inserir('endosso', array_keys($novo_endosso), array_values($novo_endosso));

		index();
	}

	function js_functions(){
		?><script>

			function selecionaMotorista(idEmpresa) {
				let buscaExtra = '';
				if (idEmpresa > 0){
					buscaExtra = encodeURI('AND enti_tx_tipo = "Motorista" AND enti_nb_empresa = "' + idEmpresa + '"');
				}else{
					buscaExtra = encodeURI('AND enti_tx_tipo = "Motorista"');
				}

				if ($('.busca_motorista').data('select2')) {// Verifica se o elemento está usando Select2 antes de destruí-lo
					$('.busca_motorista').select2('destroy');
				}

				$.fn.select2.defaults.set("theme", "bootstrap");
				$('.busca_motorista').select2({
					language: 'pt-BR',
					placeholder: 'Selecione um item',
					allowClear: true,
					ajax: {
						url: "/contex20/select2.php?path=/dev_techps/armazem_paraiba&tabela=entidade&extra_ordem=&extra_limite=15&extra_bd=" + buscaExtra + "&extra_busca=enti_tx_matricula",
						dataType: 'json',
						delay: 250,
						processResults: function(data) {
							return {
								results: data
							};
						},
						cache: true
					}
				});
			}
		</script><?php

	}

	function index(){
		// $url = explode('/', $_SERVER['SCRIPT_URL']);
		// $url = implode('/', [$url[0], $url[1], $url[2]]);

		// print_r($url);

		cabecalho('Cadastro Endosso'.(is_int(strpos($_SERVER["REQUEST_URI"], 'dev_'))? ' (Dev)': ''));

		$extra_bd_motorista = ' AND enti_tx_tipo = "Motorista"';
		if($_SESSION['user_tx_nivel'] != 'Super Administrador'){
			$extra_bd_motorista .= 'AND enti_nb_empresa = '.$_SESSION['user_tx_emprCnpj'];
		}

		$c = [
			campo_data('De:','data_de',$_POST['data_de'],2),
			campo_data('Ate:','data_ate',$_POST['data_ate'],2),
			combo_net('Motorista:','busca_motorista',$_POST['busca_motorista'],4,'entidade','',$extra_bd_motorista,'enti_tx_matricula')
		];
		if($_SESSION['user_tx_nivel'] == 'Super Administrador'){
			array_unshift($c, combo_net('Empresa:','empresa',$_POST['empresa'],4,'empresa', 'onchange=selecionaMotorista(this.value)'));
		}
		$b = [
			botao('Voltar', 'voltar'),
			botao('Cadastrar Endosso', 'cadastrar')
		];

		abre_form();
		linha_form($c);
		fecha_form($b);
		
		rodape();

		js_functions();
	}
?>