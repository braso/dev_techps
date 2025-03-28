<?php
	include "conecta.php";

	// function exclui_macro(){

	// 	remover("macroponto",$_POST["id"]);
	// 	index();
	// 	exit;

	// }
	function verMacro(){
		$_POST = array_merge($_POST, carregar("macroponto",$_POST["id"]));

		layout_macro();
		exit;
	}

	// function cadastra_macro(){

	// 	$camposObrig = [
	// 		"nome" => "Nome", 
	// 		"codigoInterno" => "Código Interno", 
	// 		"codigoExterno" => "Código Externo",
	// 		"fonte" => "Fonte",
	// 		"status" => "Status"
	// 	];
	// 	$errorMsg = conferirCamposObrig($camposObrig, $_POST);
	// 	if(!empty($errorMsg)){
	// 		set_status("ERRO: ".$errorMsg);
	// 		layout_macro();
	// 		exit;
	// 	}

	// 	$novaMacro = [
	// 		"macr_tx_nome" => $_POST["nome"],
	// 		"macr_tx_codigoInterno" => $_POST["codigoInterno"],
	// 		"macr_tx_codigoExterno" => $_POST["codigoExterno"],
	// 		"macr_nb_user" => $_SESSION["user_nb_id"],
	// 		"macr_tx_data" => date("Y-m-d"),
	// 		"macr_tx_status" => "ativo"
	// 	];

	// 	if(!empty($_POST["id"])){
	// 		atualizar("macroponto",array_keys($novaMacro), array_values($novaMacro),$_POST["id"]);
	// 	}else{
	// 		inserir("macroponto",array_keys($novaMacro), array_values($novaMacro));
	// 	}

	// 	index();
	// 	exit;
	// }


	function layout_macro(){

		cabecalho("Cadastro Macro");

		$c = [
			texto("Nome*",($_POST["macr_tx_nome"]?? ""),5),
			texto("Código Interno*",($_POST["macr_tx_codigoInterno"]?? ""),3),
			texto("Código Externo*",($_POST["macr_tx_codigoExterno"]?? ""),3)
		];

		$botao = [
			// botao("Gravar","cadastra_macro","id",$_POST["id"],"","","btn btn-success"),
			criarBotaoVoltar()
		];
		
		echo abre_form("Dados do Macro");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		echo linha_form($c);
		echo fecha_form($botao);

		rodape();
	}

	function index(){

		cabecalho("Cadastro Macro");
		
		$campos = [
			campo("Código","busca_codigo",$_POST["busca_codigo"],2,"MASCARA_NUMERO"),
			campo("Nome","busca_nome_like",$_POST["busca_nome_like"],10)
		];

		$botoes = [
			botao("Buscar","index"),
			// botao("Inserir","layout_macro")
		];
		
		echo abre_form();
		echo linha_form($campos);
		echo fecha_form($botoes);

		//Grid dinâmico{
			$gridFields = [
				"CÓDIGO" => "macr_nb_id",
				"NOME" => "macr_tx_nome",
				"CÓD. INTERNO" => "macr_tx_codigoInterno",
				"CÓD. EXTERNO" => "macr_tx_codigoExterno",
			];

			$camposBusca = [
				"busca_codigo" 	=> "macr_nb_id",
				"busca_nome_like" 	=> "macr_tx_nome"
			];

			$queryBase = (
				"SELECT ".implode(", ", array_values($gridFields))." FROM macroponto"
			);

			$actions = criarIconesGrid(
				["glyphicon glyphicon-search search-button"],
				["cadastro_macro.php"],
				["verMacro()"]
			);
			$gridFields["actions"] = $actions["tags"];

			$jsFunctions =
				"const funcoesInternas = function(){
					".implode(" ", $actions["functions"])."
				}"
			;

			echo gridDinamico("tabelaMacros", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		//}*/



		rodape();

	}