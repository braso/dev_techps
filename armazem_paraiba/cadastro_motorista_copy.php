<?php
    /* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/
	include "conecta.php";

	function verDetalhes(){
		dd($_POST, false);
		index();
		exit;
	}

	function removerMotorista(){
		dd("removerMotorista", false);
		index();
		exit;
	}

	function index(){
		cabecalho("Cadastro de Funcionário");

		$tabIndex = 1;
		$camposBusca = [ 
			campo("Código", "busca_codigo", ($_POST["busca_codigo"]?? ""), 1,"","maxlength='6' tabindex='".($tabIndex++)."'"),
			campo("Nome", "busca_nome_like", ($_POST["busca_nome"]?? ""), 2,"","maxlength='65' tabindex='".($tabIndex++)."'"),
			campo("Matrícula", "busca_matricula_like", ($_POST["busca_matricula"]?? ""), 1,"","maxlength='6' tabindex='".($tabIndex++)."'"),
			campo("CPF", "busca_cpf_like", ($_POST["busca_cpf"]?? ""), 2, "MASCARA_CPF", "tabindex='".($tabIndex++)."'"),
			combo("Ocupação", "busca_ocupacao", ($_POST["busca_ocupacao"]?? ""), 2, ["", "Motorista", "Ajudante", "Funcionário"], "tabindex='".($tabIndex++)."'"),
			combo("Convenção Padrão", "busca_padrao", ($_POST["busca_padrao"]?? ""), 2, ["" => "", "sim" => "Sim", "nao" => "Não"], "tabindex='".($tabIndex++)."'"),
			combo_bd("!Parâmetros da Jornada", "busca_parametro", ($_POST["busca_parametro"]?? ""), 6, "parametro", "tabindex='".($tabIndex++)."'"),
			combo("Status", "busca_status", ($_POST["busca_status"]?? ""), 2, ["" => "", "ativo" => "Ativo", "inativo" => "Inativo"], "tabindex='".($tabIndex++)."'")
		];

		echo abre_form();
		echo linha_form($camposBusca);
		echo fecha_form();

		$colunas = [
			"Código" => "enti_nb_id",
			"Nome" => "enti_tx_nome",
			"Matrícula" => "enti_tx_matricula",
			"CPF" => "enti_tx_cpf",
			"Ocupação" => "user_tx_nivel",
			"Parâmetro" => "para_tx_nome",
			"Convenção Padrão" => "IF(enti_tx_ehPadrao = \"sim\", \"Sim\", \"".htmlentities("Não")."\") as enti_tx_ehPadrao",
			"Status" => "enti_tx_status",
		];

		$camposBusca = [
			"busca_codigo" => "enti_nb_id",
			"busca_nome_like" => "enti_tx_nome",
			"busca_matricula_like" => "enti_tx_matricula",
			"busca_cpf_like" => "enti_tx_cpf",
			"busca_ocupacao" => "user_tx_nivel",
			"busca_padrao" => "enti_tx_ehPadrao",
			"busca_parametro" => "para_nb_id",
			"busca_status" => "enti_tx_status"
		];

		$queryBase = "SELECT ".implode(", ", array_values($colunas))." FROM entidade JOIN user ON enti_nb_id = user_nb_entidade LEFT JOIN parametro ON enti_nb_parametro = para_nb_id";

		$actions = criarIconesGrid(
			["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"], 
			["cadastro_motorista_copy.php", "cadastro_motorista_copy.php"], 
			["verDetalhes()", "removerMotorista()"]
		);

		$colunas["actions"] = $actions["tags"];

		$jsFunctions =
			"const funcoesInternas = function(){
				".implode(" ", $actions["functions"])."
			}"
		;

		echo gridDinamico("tabelaMotoristas", $colunas, $camposBusca, $queryBase, $jsFunctions, 12, $tabIndex++);
		rodape();
	}
?>