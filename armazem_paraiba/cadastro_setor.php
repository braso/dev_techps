<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	include "conecta.php";

    function excluirSetor(){
		remover("grupos_documentos",$_POST["id"]);
		index();
		exit;
	}

    function modificarSetor(){
		$a_mod = carregar("grupos_documentos", $_POST["id"]);
		[$_POST["id"], $_POST["nome"], $_POST["status"]] = [$a_mod["grup_nb_id"], $a_mod["grup_tx_nome"], $a_mod["grup_tx_status"]];
		
		layout_Setor();
		exit;
	}

    function cadastra_setor() {
		$novoSetor = [
			"grup_tx_nome" => $_POST["nome"],
			"grup_tx_status" => "ativo"
		];

		if(!empty($_POST["id"])){
			atualizar("grupos_documentos", array_keys($novoSetor), array_values($novoSetor), $_POST["id"]);
		}else{
			// $novoSetor["oper_nb_userCadastro"] = $_SESSION["user_nb_id"];
			// $novoSetor["oper_tx_dataCadastro"] = date("Y-m-d H:i:s");

			inserir("grupos_documentos", array_keys($novoSetor), array_values($novoSetor));
		}

		index();
		exit;
	}

    function layout_setor() {
		global $a_mod;

		cabecalho("Cadastro de Setor");

		if (!empty($_POST["id"])) {
			$campoStatus = combo("Status", "busca_banco", $_POST["busca_banco"]?? "", 2, [ "ativo" => "Ativo", "inativo" => "Inativo"]);
		}

		$campos = [
			campo("Nome*", "nome", $_POST["nome"], 4),
			$campoStatus
		];

		$botoes = [
			botao("Gravar", "cadastra_setor", "id", $_POST["id"], "", "", "btn btn-success"),
			criarBotaoVoltar()
		];
		
		echo abre_form("Dados do setor");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		echo linha_form($campos);
		echo fecha_form($botoes);

		rodape();
	}

    function index() {
        global $CONTEX;

        cabecalho("Cadastro de Setor");

        $fields = [
			campo("Código", 		"busca_codigo", 	($_POST["busca_codigo"]?? ""), 	1, "", "maxlength='6'"),
			campo("Nome", 			"busca_nome_like", 		($_POST["busca_nome_like"]?? ""), 	3, "", "maxlength='65'"),
            combo("Status", 		"busca_status", 	($_POST["busca_status"]?? ""), 	2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]),
		];

		$buttons[] = botao("Buscar", "index");

		if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
			$buttons[] = botao("Inserir", "layout_setor()","","","","","btn btn-success");
		}

		// $buttons[] = '<button class="btn default" type="button" onclick="imprimirTabelaCompleta()">Imprimir</button>';

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons);

        $gridFields = [
            "CÓDIGO" 		=> "grup_nb_id",
            "NOME" 			=> "grup_tx_nome",
            "STATUS" 	    => "grup_tx_status",
        ];

        $camposBusca = [
            "busca_codigo" 		=> "grup_nb_id",
            "busca_nome_like" 	=> "grup_tx_nome",
            "busca_status" 		=> "grup_tx_status",
        ];

        $queryBase = 
            "SELECT ".implode(", ", array_values($gridFields))." FROM grupos_documentos"
        ;

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
            ["cadastro_setor.php", "cadastro_setor.php"],
            ["modificarSetor()", "excluirSetor()"]
        );

        $actions["functions"][1] .= 
            "esconderInativar('glyphicon glyphicon-remove search-remove', 9);"
        ;

        $gridFields["actions"] = $actions["tags"];

        $jsFunctions =
            "const funcoesInternas = function(){
                ".implode(" ", $actions["functions"])."
            }"
        ;

        echo gridDinamico("tabelaSetor", $gridFields, $camposBusca, $queryBase, $jsFunctions);
        rodape();
    }