<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);

		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0");
	//*/
	
	global $legendas;
	$legendas = [
		"" => "",
		"I" => "Incluída Manualmente",
		"P" => "Pré-Assinalada",
		"T" => "Outras fontes de marcação",
		"DSR" => "Descanso Semanal Remunerado e Abono"
	];
	
	include "conecta.php";

	function excluirMotivo(){
		remover("motivo",$_POST["id"]);
		index();
		exit;
	}
	function modificarMotivo(){
		$_POST = array_merge($_POST, mysqli_fetch_assoc(query("SELECT * FROM motivo WHERE moti_nb_id = {$_POST["id"]};")));	
		layout_motivo();
		exit;
	}

	function cadastra_motivo(){
		$camposObrig = [
			"nome" => "Nome",
			"tipo" => "Tipo",
			"legenda" => "Legenda de Marcação"
		];
		$errorMsg = conferirCamposObrig($camposObrig, $_POST);
		if(!empty($errorMsg)){
			set_status("ERRO: ".$errorMsg);
			layout_motivo();
			exit;
		}

		$novoMotivo = [
			"moti_tx_nome" => $_POST["nome"],
			"moti_tx_tipo" => $_POST["tipo"],
			"moti_tx_legenda" => $_POST["legenda"],
			"moti_tx_status" => "ativo"
		];
		

		if(!empty($_POST["id"])){
			atualizar("motivo", array_keys($novoMotivo), array_values($novoMotivo), $_POST["id"]);
		}else{
			$novoMotivo["moti_nb_userCadastro"] = $_SESSION["user_nb_id"];
			$novoMotivo["moti_tx_dataCadastro"] = date("Y-m-d H:i:s");
			inserir("motivo", array_keys($novoMotivo), array_values($novoMotivo));
		}

		index();
		exit;
	}

	function layout_motivo(){
		global $legendas;

		$_POST["moti_tx_legenda"] = "I";
		cabecalho("Cadastro de Motivo");

		$campos = [
			campo("Nome*", "nome", (!empty($_POST["moti_tx_nome"])? $_POST["moti_tx_nome"]: (!empty($_POST["nome"])? $_POST["nome"]: "")), 6),
			combo("Tipo*", "tipo", (!empty($_POST["moti_tx_tipo"])? $_POST["moti_tx_tipo"]: (!empty($_POST["tipo"])? $_POST["tipo"]: "")), 2, ["Ajuste","Abono"]),
			combo("Legenda de Marcação*", "legenda", !empty($_POST["moti_tx_legenda"])? array_key_exists($_POST["moti_tx_legenda"], $legendas): (!empty($_POST["legenda"])? array_search($_POST["legenda"], $legendas): ""), 4, $legendas)
		];

		$botoes = [
			botao("Gravar", "cadastra_motivo", "id", (!empty($_POST["id"])? $_POST["id"]: NULL), "", "", "btn btn-success"),
			criarBotaoVoltar()
		];

		echo abre_form("Dados do Motivo");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		echo linha_form($campos);
		echo fecha_form($botoes);

		rodape();
	}

	function index(){
		if(is_bool(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
			$_POST["returnValues"] = json_encode([
				"HTTP_REFERER" => $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/index.php"
			]);
			voltar();
		}
		
		global $legendas;

		cabecalho("Cadastro de Motivo");

		$campos = [
			campo("Código",		"busca_codigo",		(!empty($_POST["busca_codigo"])? 	$_POST["busca_codigo"]: ""), 2, "MASCARA_NUMERO", "maxlength='6'"),
			campo("Nome",		"busca_nome_like",	(!empty($_POST["busca_nome_like"])? $_POST["busca_nome_like"]: ""), 5, "", "maxlength='65'"),
			combo("Tipo",		"busca_tipo",		(!empty($_POST["busca_tipo"])? 		$_POST["busca_tipo"]: ""), 2, ["", "Ajuste", "Abono"]),
			combo("Legenda",	"busca_legenda",	(!empty($_POST["busca_legenda"])? 	$_POST["busca_legenda"]: ""), 3, $legendas)
		];
		$botoes = [
			botao("Buscar","index"),
			botao("Inserir","layout_motivo","","","","","btn btn-success")
		];

		echo abre_form();
		echo linha_form($campos);
		echo fecha_form($botoes);

		//Grid dinâmico{
			$gridFields = [
				"CÓDIGO" => "moti_nb_id",
				"NOME" => "moti_tx_nome",
				"TIPO" => "moti_tx_tipo",
				"LEGENDA" => "moti_tx_legenda",
			];

			$camposBusca = [
				"busca_codigo"		=> "moti_nb_id",
				"busca_nome_like"	=> "moti_tx_nome",
				"busca_tipo"		=> "moti_tx_tipo",
				"busca_legenda"		=> "moti_tx_legenda"
			];

			$queryBase = ("SELECT ".implode(", ", array_values($gridFields))." FROM motivo");

			$actions = criarIconesGrid(
				["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
				["cadastro_motivo.php", "cadastro_motivo.php"],
				["modificarMotivo()", "excluirMotivo()"]
			);
			$gridFields["actions"] = $actions["tags"];

			$jsFunctions =
				"const funcoesInternas = function(){
					".implode(" ", $actions["functions"])."
				}"
			;

			echo gridDinamico("tabelaFeriados", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		//}*/

		rodape();
	}
