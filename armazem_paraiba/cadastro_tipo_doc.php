<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	include "conecta.php";

    function excluirTipoDoc(){
		remover("tipos_documentos",$_POST["id"]);
		index();
		exit;
	}

    function modificarTipoDoc(){
		$a_mod = carregar("tipos_documentos", $_POST["id"]);
		[$_POST["id"], $_POST["nome"], $_POST["setor"], $_POST["vencimento"]] = [$a_mod["tipo_nb_id"], $a_mod["tipo_tx_nome"], $a_mod["tipo_nb_grupo"],$a_mod["tipo_tx_vencimento"], $a_mod["tipo_tx_status"]];
		
		layout_tipo_doc();
		exit;
	}

    function cadastra_tipo_doc() {
		$novoTipo= [
			"tipo_tx_nome" => $_POST["nome"],
			"tipo_nb_grupo" => $_POST["setor"],
			"tipo_tx_vencimento" => $_POST["vencimento"],
			"tipo_tx_status" => "ativo"
		];

		if(!empty($_POST["id"])){
			atualizar("tipos_documentos", array_keys($novoTipo), array_values($novoTipo), $_POST["id"]);
		}else{
			// $novoTipo["oper_nb_userCadastro"] = $_SESSION["user_nb_id"];
			// $novoTipo["oper_tx_dataCadastro"] = date("Y-m-d H:i:s");

			inserir("tipos_documentos", array_keys($novoTipo), array_values($novoTipo));
		}

		index();
		exit;
	}

    function layout_tipo_doc() {
		global $a_mod;

		cabecalho("Cadastro de Tipo de Documento");

		if (!empty($_POST["id"])) {
			$campoStatus = combo("Status", "busca_banco", $_POST["busca_banco"]?? "", 2, [ "ativo" => "Ativo", "inativo" => "Inativo"]);
		}
		
		if (empty($_POST["vencimento"])) {
			$_POST["vencimento"] = "nao";
		}

		$campos = [
			campo("Nome*", "nome", $_POST["nome"], 4),
            combo_bd("!Setor*", "setor", $_POST["setor"], 2, "grupos_documentos"),
			$campoStatus,
            combo_radio("Passivel de vencimento", "vencimento", $_POST["vencimento"],3,["sim" => "Sim", "nao" => "Não"]),
		];

		$botoes = [
			botao("Gravar", "cadastra_tipo_doc", "id", $_POST["id"], "", "", "btn btn-success"),
			criarBotaoVoltar()
		];
		
		echo abre_form("Dados do Tipo de Documento");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		echo linha_form($campos);
		echo fecha_form($botoes);

		rodape();
	}

    function index() {
        global $CONTEX;

        cabecalho("Cadastro de Operação");

        $fields = [
			campo("Código", 		"busca_codigo", 	($_POST["busca_codigo"]?? ""), 	1, "", "maxlength='6'"),
			campo("Nome", 			"busca_nome_like", 		($_POST["busca_nome_like"]?? ""), 	3, "", "maxlength='65'"),
			combo_bd("!Setor", 		"busca_setor",		(!empty($_POST["busca_setor"])? $_POST["busca_setor"]: ""), 4, "grupos_documentos"),
            combo("Status", 		"busca_status", 	($_POST["busca_status"]?? ""), 	2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]),
		];

		$buttons[] = botao("Buscar", "index");

		if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
			$buttons[] = botao("Inserir", "layout_tipo_doc()","","","","","btn btn-success");
		}

		// $buttons[] = '<button class="btn default" type="button" onclick="imprimirTabelaCompleta()">Imprimir</button>';

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons);

        $gridFields = [
            "CÓDIGO" 		=> "tipo_nb_id",
            "NOME" 			=> "tipo_tx_nome",
            "SETOR" 		=> "grup_tx_nome",
            "STATUS" 	    => "tipo_tx_status",
        ];

        $camposBusca = [
            "busca_codigo" 		=> "tipo_nb_id",
            "busca_nome_like" 	=> "tipo_tx_nome",
            "busca_status" 		=> "tipo_tx_status",
            "busca_setor" 		=> "tipo_nb_grupo",
        ];

        $queryBase = 
            "SELECT ".implode(", ", array_values($gridFields))." FROM tipos_documentos"
            ." LEFT JOIN grupos_documentos ON grupos_documentos.grup_nb_id = tipos_documentos.tipo_nb_grupo"
        ;

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
            ["cadastro_tipo_doc.php", "cadastro_tipo_doc.php"],
            ["modificarTipoDoc()", "excluirTipoDoc()"]
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

        echo gridDinamico("tabelaTiposDocumentos", $gridFields, $camposBusca, $queryBase, $jsFunctions);
        rodape();
    }