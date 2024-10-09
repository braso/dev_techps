<?php
	include "conecta.php";

	function exclui_macro(){

		remover("macroponto",$_POST["id"]);
		index();
		exit;

	}
	function modifica_macro(){
		$_POST = array_merge($_POST, carregar("macroponto",$_POST["id"]));

		layout_macro();
		exit;
	}

	function cadastra_macro(){

		$camposObrig = [
			"nome" => "Nome", 
			"codigoInterno" => "Código Interno", 
			"codigoExterno" => "Código Externo"
		];
		$errorMsg = "ERRO: Campos obrigatórios não preenchidos: ";
		foreach($camposObrig as $key => $value){
			if(empty($_POST[$key])){
				$_POST["errorFields"][] = $key;
				$errorMsg .= $value.", ";
			}
		}

		if(!empty($_POST["errorFields"])){
			set_status(substr($errorMsg, 0, -2).".");
			layout_macro();
			exit;
		}

		$novaMacro = [
			"macr_tx_nome" => $_POST["nome"],
			"macr_tx_codigoInterno" => $_POST["codigoInterno"],
			"macr_tx_codigoExterno" => $_POST["codigoExterno"],
			"macr_nb_user" => $_SESSION["user_nb_id"],
			"macr_tx_data" => date("Y-m-d"),
			"macr_tx_status" => "ativo"
		];

		if(!empty($_POST["id"])){
			atualizar("macroponto",array_keys($novaMacro), array_values($novaMacro),$_POST["id"]);
		}else{
			inserir("macroponto",array_keys($novaMacro), array_values($novaMacro));
		}

		index();
		exit;
	}


	function layout_macro(){

		cabecalho("Cadastro Macro");

		$c = [
			campo("Nome*","nome",($_POST["macr_tx_nome"]?? ""),6,"","readonly=readonly"),
			campo("Código Interno*","codigoInterno",($_POST["macr_tx_codigoInterno"]?? ""),3,"","readonly=readonly"),
			campo("Código Externo*","codigoExterno",($_POST["macr_tx_codigoExterno"]?? ""),3)
		];

		$botao = [
			// botao("Gravar","cadastra_macro","id",$_POST["id"],"","","btn btn-success"),
			botao("Voltar","index")
		];

		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], "cadastro_macro.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_macro.php";
			}
		}
		
		abre_form("Dados do Macro");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		linha_form($c);
		fecha_form($botao);

		rodape();

	}

	function index(){

		cabecalho("Cadastro Macro");
		
		$extra = 
			(($_POST["busca_codigo"])? " AND macr_nb_id = ".$_POST["busca_codigo"]: "")
			.(($_POST["busca_nome"])? " AND macr_tx_nome LIKE '%".$_POST["busca_nome"]."%'": "")
		;

		$campos = [
			campo("Código","busca_codigo",$_POST["busca_codigo"],2,"MASCARA_NUMERO"),
			campo("Nome","busca_nome",$_POST["busca_nome"],10)
		];

		$botoes = [
			botao("Buscar","index"),
			// botao("Inserir","layout_macro")
		];
		
		abre_form();
		linha_form($campos);
		fecha_form($botoes);

		$sql = "SELECT * FROM macroponto WHERE macr_tx_status = 'ativo' ".$extra;
		$cols = [
			"CÓDIGO" => "macr_nb_id",
			"NOME" => "macr_tx_nome",
			"CÓD. INTERNO" => "macr_tx_codigoInterno",
			"CÓD. EXTERNO" => "macr_tx_codigoExterno",
			"<spam class='glyphicon glyphicon-search'></spam>" => "icone_modificar(macr_nb_id,modifica_macro)",
		];

		grid($sql, array_keys($cols), array_values($cols), "", "", 0, "desc", 10);

		rodape();

	}