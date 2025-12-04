<?php
    /* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/
	include "conecta.php";

	function excluirOperacao(){
		remover("operacao",$_POST["id"]);
		index();
		exit;

	}
	function modificarOperacao(){
		$a_mod = carregar("operacao", $_POST["id"]);
		[$_POST["id"], $_POST["nome"], $_POST["status"]] = [$a_mod["oper_nb_id"], $a_mod["oper_tx_nome"], $a_mod["oper_tx_status"]];
		
		layout_operacao();
		exit;
	}

	function cadastra_operacao() {
		$novoFeriado = [
			"oper_tx_nome" => $_POST["nome"],
			"oper_tx_status" => "ativo"
		];

		if(!empty($_POST["id"])){
			atualizar("operacao", array_keys($novoFeriado), array_values($novoFeriado), $_POST["id"]);
		}else{
			$novoFeriado["oper_nb_userCadastro"] = $_SESSION["user_nb_id"];
			$novoFeriado["oper_tx_dataCadastro"] = date("Y-m-d H:i:s");

			inserir("operacao", array_keys($novoFeriado), array_values($novoFeriado));
		}

		index();
		exit;
	}

	function layout_operacao() {
		global $a_mod;

		cabecalho("Cadastro de Feriado");

		$campos = [
			campo("Nome*", "nome", $_POST["nome"], 4),
		];

		$botoes = [
			botao("Gravar", "cadastra_operacao", "id", $_POST["id"], "", "", "btn btn-success"),
			criarBotaoVoltar()
		];
		
		echo abre_form("Dados do Cargo");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		echo linha_form($campos);
		echo fecha_form($botoes);

		rodape();
	}

    function index() {
		global $CONTEX;
		
		//ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
        include "check_permission.php";
        // APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
        verificaPermissao('/cadastro_operacao.php');
		cabecalho("Cadastro de Cargos");

		if(!isset($_POST["busca_status"])){
			$_POST["busca_status"] = "ativo";
		}

		$fields = [
			campo("Código", 		"busca_codigo", 	($_POST["busca_codigo"]?? ""), 	1, "", "maxlength='6'"),
			campo("Nome", 			"busca_nome_like", 		($_POST["busca_nome_like"]?? ""), 	8, "", "maxlength='65'"),
			combo("Status", 		"busca_status", 	($_POST["busca_status"]?? ""), 	2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]),
			//combo_net("Funcionário", "busca_usuario", $_POST["busca_usuario"]?? "", 4, "entidade", "", "", "enti_tx_matricula"),
		];

		$buttons[] = botao("Buscar", "index");

		if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
			$buttons[] = botao("Inserir", "layout_operacao","","","","","btn btn-success");
		}

		$buttons[] = '<button class="btn default" type="button" onclick="imprimirTabelaCompleta()">Imprimir</button>';

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons);

		// $logoEmpresa = mysqli_fetch_assoc(query(
        //     "SELECT empr_tx_logo FROM empresa
        //             WHERE empr_tx_status = 'ativo'
        //                 AND empr_tx_Ehmatriz = 'sim'
        //             LIMIT 1;"
        // ))["empr_tx_logo"];

		// echo "<div id='tituloRelatorio' style='display: none;'>
        //             <img style='width: 190px; height: 40px;' src='./imagens/logo_topo_cliente.png' alt='Logo Empresa Esquerda'>
		// 			<h1>Cadastro de Usuário</h1>
        //             <img style='width: 180px; height: 80px;' src='./$logoEmpresa' alt='Logo Empresa Direita'>
        //     </div>";

		//Grid dinâmico{
			$gridFields = [
				"CÓDIGO" 		=> "oper_nb_id",
				"NOME" 			=> "oper_tx_nome",
				// "USUÁRIO CADASTRO" 	=> "userCadastro(oper_nb_userCadastro)",
				"STATUS" 		=> "oper_tx_status"
			];

			$camposBusca = [
				"busca_codigo" 		=> "oper_nb_id",
				"busca_nome_like" 	=> "oper_tx_nome",
				"busca_usuario" 	=> "oper_nb_userCadastro",
				"busca_status" 		=> "oper_tx_status",
			];

			$queryBase = 
				"SELECT ".implode(", ", array_values($gridFields))." FROM operacao"
			;

			$actions = criarIconesGrid(
				["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
				["cadastro_operacao.php", "cadastro_operacao.php"],
				["modificarOperacao()", "excluirOperacao()"]
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
			echo gridDinamico("tabelaOperacao", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		//}


		rodape();
	}