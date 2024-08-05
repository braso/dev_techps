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
		foreach($_POST as $key => $value){
			if(empty($a_mod[$key])){
				$a_mod[$key] = $value;
			}
		}
		layout_motivo();
		exit;
	}

	function cadastra_motivo(){
		$campos = ['moti_tx_nome', 'moti_tx_tipo', 'moti_tx_status'];
		$valores = [$_POST['nome'], $_POST['tipo'], 'ativo'];

		$legendas = [
			'' => '',
			'Incluída Manualmente' => 'I',
			'Pré-Assinalada' => 'P',
			'Outras fontes de marcação' => 'T',
			'Descanso Semanal Remunerado e Abono' => 'DSR'
		];

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

		$legendas = [
			'' => '',
			'Incluída Manualmente' => 'I',
			'Pré-Assinalada' => 'P',
			'Outras fontes de marcação' => 'T',
			'Descanso Semanal Remunerado e Abono' => 'DSR'
		];

		$c = [
			campo('Nome', 'nome', $a_mod['moti_tx_nome'], 6),
			combo('Tipo', 'tipo', $a_mod['moti_tx_tipo'], 2, ['Ajuste','Abono']),
			combo('Legenda de Marcação', 'legenda', array_search($a_mod['moti_tx_legenda'], $legendas), 4, array_keys($legendas))
		];
		$botao = [
			botao('Gravar', 'cadastra_motivo', 'id', $_POST['id'], '', '', 'btn btn-success'),
			botao('Voltar', 'voltar')
		];

		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], "cadastro_motivo.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_motivo.php";
			}
		}

		abre_form('Dados do Motivo');
		campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		linha_form($c);
		fecha_form($botao);

		rodape();
	}

	function index(){
		cabecalho("Cadastro de Motivo");

		$legendas = [
			"" => "",
			"Incluída Manualmente" => "I",
			"Pré-Assinalada" => "P",
			"Outras fontes de marcação" => "T",
			"Descanso Semanal Remunerado e Abono" => "DSR"
		];

		$extra = 
			(isset($_POST['busca_codigo']) 	&& !empty($_POST['busca_codigo'])? 	" AND moti_nb_id LIKE '%".$_POST['busca_codigo']."%'": '').
			(isset($_POST['busca_nome']) 	&& !empty($_POST['busca_nome'])? 	" AND moti_tx_nome LIKE '%".$_POST['busca_nome']."%'": '').
			(isset($_POST['busca_tipo']) 	&& !empty($_POST['busca_tipo'])? 	" AND moti_tx_tipo LIKE '%".$_POST['busca_tipo']."%'": '').
			(isset($_POST['busca_legenda']) && !empty($_POST['busca_legenda'])? " AND moti_tx_legenda LIKE '%".$legendas[$_POST['busca_legenda']]."%'": '');

		$c = [
			campo('Código','busca_codigo',$_POST['busca_codigo'],2,'MASCARA_NUMERO','maxlength="6"'),
			campo('Nome','busca_nome',$_POST['busca_nome'],5, '', 'maxlength="65"'),
			combo('Tipo','busca_tipo',$_POST['busca_tipo'],2,['','Ajuste','Abono']),
			combo('Legenda','busca_legenda',$_POST['busca_legenda'],3,['','Incluída Manualmente', 'Pré-Assinalada', 'Outras fontes de marcação', 'Descanso Semanal Remunerado e Abono'])
		];
		$botao = [
			botao('Buscar','index'),
			botao('Inserir','layout_motivo','','','','','btn btn-success')
		];

		abre_form('Filtro de Busca');
		linha_form($c);
		fecha_form($botao);

		$sql = "SELECT * FROM motivo WHERE moti_tx_status = 'ativo' $extra";
		$gridParams = [
			"CÓDIGO" => "moti_nb_id",
			"NOME" => "moti_tx_nome",
			"TIPO" => "moti_tx_tipo",
			"LEGENDA" => "moti_tx_legenda",
			"<spam class='glyphicon glyphicon-search'></spam>" => "icone_modificar(moti_nb_id,modifica_motivo)",
			"<spam class='glyphicon glyphicon-remove'></spam>" => "icone_excluir(moti_nb_id,exclui_motivo)"
		];

		grid($sql, array_keys($gridParams), array_values($gridParams), "", "12", 1, "desc");

		rodape();
	}
