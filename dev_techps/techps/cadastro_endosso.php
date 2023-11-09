<?php
	include "conecta.php";

	function voltar(){
		header('Location: '.$_SERVER['REQUEST_URI'].'/../endosso');
	}

	function cadastrar(){
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
		$show_error = False;
		$error_msg = 'Há campos obrigatórios não preenchidos: ';
		if(!isset($_POST['empresa'])){
			if($_SESSION['user_tx_nivel'] == 'Super Administrador'){
				$show_error = True;
				$error_msg .= 'Empresa, ';
			}else{
				$_POST['empresa'] = $_SESSION['user_tx_emprCnpj'];
			}
		}
		if(!isset($_POST['busca_motorista'])){
			$show_error = True;
			$error_msg .= 'Motorista, ';
		}
		if($_POST['data_de'] == '' || $_POST['data_ate'] == ''){
			$show_error = True;
			$error_msg .= 'Data, ';
		}
		if(!$show_error){
			$error_msg = '';
		}

		//Conferir se o endosso tem mais de um mês
		$difference = strtotime($_POST['data_de']) - strtotime($_POST['data_ate']);
    	$qttDays = floor($difference / (60 * 60 * 24));
		if($qttDays > 31){
			$show_error = True;
			$error_msg .= 'Não é possível cadastrar um endosso com mais de um mês.  ';
		}

		//Conferir se não está entrelaçada com outro endosso
		$endossos = mysqli_fetch_array(
			query("
				SELECT endo_tx_de, endo_tx_ate from endosso
					WHERE endo_nb_entidade = ".$_POST['busca_motorista']."
						AND NOT(
							(endo_tx_ate < '".$_POST['data_de']."') OR ('".$_POST['data_ate']."' < endo_tx_de)
						) LIMIT 1;
			")
		);

		// print_r(count($endossos));

		if(count($endossos) > 0){
			$show_error = True;
			$error_msg = 'Já há um endosso para este motorista nesta faixa de tempo.  ';
		}

		if($show_error){
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
			'endo_tx_status' => 'ativo',
			'endo_tx_pagarHoras' => $_POST['pagar_horas'],
			'endo_tx_horasApagar' => $_POST['quandHoras']
		];

		inserir('endosso', array_keys($novo_endosso), array_values($novo_endosso));

		index();
		return;
	}

	function js_functions(){
		global $CONTEX;
		?><script>
			function selecionaMotorista(idEmpresa) {
				let buscaExtra = encodeURI('AND enti_tx_tipo = "Motorista"'+
					(idEmpresa > 0? ' AND enti_nb_empresa = "'+idEmpresa+'"': '')
				);

				if ($('.busca_motorista').data('select2')) {// Verifica se o elemento está usando Select2 antes de destruí-lo
					$('.busca_motorista').select2('destroy');
				}

				$.fn.select2.defaults.set("theme", "bootstrap");
				$('.busca_motorista').select2({
					language: 'pt-BR',
					placeholder: 'Selecione um item',
					allowClear: true,
					ajax: {
						url: "/contex20/select2.php?path=<?=$CONTEX['path']?>&tabela=entidade&extra_ordem=&extra_limite=15&extra_bd="+buscaExtra+"&extra_busca=enti_tx_matricula",
						dataType: 'json',
						delay: 250,
						processResults: function(data){
							return {results: data};
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

		cabecalho('Cadastro Endosso');

		$extra_bd_motorista = ' AND enti_tx_tipo = "Motorista"';
		if($_SESSION['user_tx_nivel'] != 'Super Administrador'){
			$extra_bd_motorista .= 'AND enti_nb_empresa = '.$_SESSION['user_tx_emprCnpj'];
		}

		$c = [
			campo_data('De*:','data_de',$_POST['data_de'],2),
			campo_data('Ate*:','data_ate',$_POST['data_ate'],2),
			combo_net('Motorista*:','busca_motorista',$_POST['busca_motorista'],4,'entidade','',$extra_bd_motorista,'enti_tx_matricula'),
			checkbox('Pagar Horas Extras',"horasApagar",2)
		];
		if($_SESSION['user_tx_nivel'] == 'Super Administrador'){
			array_unshift($c, combo_net('Empresa*:','empresa',$_POST['empresa'],4,'empresa', 'onchange=selecionaMotorista(this.value)'));
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