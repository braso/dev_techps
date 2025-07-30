<?php
    /* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/
	include "conecta.php";

    function index() {
		global $CONTEX;
		
		if (!empty($_GET["id"])){
			if ($_GET["id"] != $_SESSION["user_nb_id"]) {
				echo "ERRO: Usuário não autorizado!";
				echo "<script>window.location.replace('".$CONTEX["path"]."/index.php');</script>";
				exit;
			}
			$_POST["id"] = $_GET["id"];
			modificarUsuario();
			exit;
		}

		if (in_array($_SESSION["user_tx_nivel"], ["Motorista", "Ajudante", "Funcionário"])) {
			$_POST["id"] = $_SESSION["user_nb_id"];
			modificarUsuario();
			exit;
		}
		$extraEmpresa = " AND empr_tx_situacao = 'ativo' ORDER BY empr_tx_nome";

		if ($_SESSION["user_nb_empresa"] > 0 && is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
			$extraEmpresa .= " AND empr_nb_id = '".$_SESSION["user_nb_empresa"]."'";
		}

		cabecalho("Cadastro de Usuário");

		if(!isset($_POST["busca_status"])){
			$_POST["busca_status"] = "ativo";
		}

		$fields = [
			campo("Código", 		"busca_codigo", 	($_POST["busca_codigo"]?? ""), 	1, "", "maxlength='6'"),
			campo("Nome", 			"busca_nome_like", 		($_POST["busca_nome_like"]?? ""), 	3, "", "maxlength='65'"),
		];

		$buttons[] = botao("Buscar", "index");

		if(is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
			$buttons[] = botao("Inserir", "modificarUsuario","","","","","btn btn-success");
		}

		$buttons[] = '<button class="btn default" type="button" onclick="imprimirTabelaCompleta()">Imprimir</button>';

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons);

		$logoEmpresa = mysqli_fetch_assoc(query(
            "SELECT empr_tx_logo FROM empresa
                    WHERE empr_tx_status = 'ativo'
                        AND empr_tx_Ehmatriz = 'sim'
                    LIMIT 1;"
        ))["empr_tx_logo"];

		echo "<div id='tituloRelatorio' style='display: none;'>
                    <img style='width: 190px; height: 40px;' src='./imagens/logo_topo_cliente.png' alt='Logo Empresa Esquerda'>
					<h1>Cadastro de Usuário</h1>
                    <img style='width: 180px; height: 80px;' src='./$logoEmpresa' alt='Logo Empresa Direita'>
            </div>";

		/*/Grid{
			$iconeModificar = 	criarSQLIconeTabela("user_nb_id","modificarUsuario","Modificar","glyphicon glyphicon-search");
			$iconeExcluir = 	criarSQLIconeTabela("user_nb_id","excluirUsuario","Excluir","glyphicon glyphicon-remove","Deseja inativar o registro?");

			$sqlFields = [
				"user_nb_id",
				"user_tx_nome",
				"enti_tx_matricula",
				"user_tx_cpf",
				"user_tx_login",
				"user_tx_nivel",
				"user_tx_email",
				"user_tx_fone",
				"empr_tx_nome",
				"user_tx_status"
			];

			$sql = 
				"SELECT ".implode(", ", $sqlFields).",
					{$iconeModificar} as iconeModificar,
					IF(user_tx_status = 'ativo', {$iconeExcluir}, NULL) as iconeExcluir
				FROM user
					LEFT JOIN empresa ON empresa.empr_nb_id = user.user_nb_empresa
					LEFT JOIN entidade ON user_nb_entidade = enti_nb_id
					WHERE 1 {$extra};"
			;

			$gridFields = [
				"CÓDIGO" => "user_nb_id",
				"NOME" => "user_tx_nome",
				"MATRICULA" => "enti_tx_matricula",
				"CPF" => "user_tx_cpf",
				"LOGIN" => "user_tx_login",
				"NÍVEL" => "user_tx_nivel",
				"E-MAIL" => "user_tx_email",
				"TELEFONE" => "user_tx_fone",
				"EMPRESA" => "empr_tx_nome",
				"STATUS" => "user_tx_status",
				"<spam class='glyphicon glyphicon-search'></spam>" => "iconeModificar",
				"<spam class='glyphicon glyphicon-remove'></spam>" => "iconeExcluir"
			];
			
			grid($sql, array_keys($gridFields), array_values($gridFields));
		//}*/

		//Grid dinâmico{
			$gridFields = [
				"CÓDIGO" 		=> "user_nb_id",
				"NOME" 			=> "user_tx_nome",
				"MATRICULA" 	=> "enti_tx_matricula",
				"CPF" 			=> "user_tx_cpf",
				"LOGIN" 		=> "user_tx_login",
				"NÍVEL" 		=> "user_tx_nivel",
				"E-MAIL" 		=> "user_tx_email",
				"TELEFONE" 		=> "user_tx_fone",
				"EMPRESA" 		=> "empr_tx_nome",
				"STATUS" 		=> "user_tx_status"
			];

			$camposBusca = [
				"busca_codigo" 		=> "user_nb_id",
				"busca_nome_like" 	=> "user_tx_nome",
				"busca_cpf" 		=> "user_tx_cpf",
				"busca_login_like" 	=> "user_tx_login",
				"busca_nivel" 		=> "user_tx_nivel",
				"busca_status" 		=> "user_tx_status",
				"busca_empresa" 	=> "empr_nb_id"
			];

			$queryBase = 
				"SELECT ".implode(", ", array_values($gridFields))." FROM user"
				." LEFT JOIN empresa ON empresa.empr_nb_id = user.user_nb_empresa"
				." LEFT JOIN entidade ON user_nb_entidade = enti_nb_id"
			;

			$actions = criarIconesGrid(
				["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
				["cadastro_usuario.php", "cadastro_usuario.php"],
				["modificarUsuario()", "excluirUsuario()"]
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
			echo gridDinamico("tabelaMotoristas", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		//}


		rodape();
	}