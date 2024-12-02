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
		$_POST = array_merge($_POST, carregar("motivo", $_POST["id"]));	
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
			botao("Voltar", "voltar")
		];

		if(empty($_POST["HTTP_REFERER"])){
			$_POST["HTTP_REFERER"] = $_SERVER["HTTP_REFERER"];
			if(is_int(strpos($_SERVER["HTTP_REFERER"], "cadastro_motivo.php"))){
				$_POST["HTTP_REFERER"] = $_ENV["URL_BASE"].$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_motivo.php";
			}
		}

		abre_form("Dados do Motivo");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		linha_form($campos);
		fecha_form($botoes);

		rodape();
	}

	function index(){
		global $legendas;

		cabecalho("Cadastro de Motivo");

		$extra = 
			(!empty($_POST["busca_codigo"])? 	" AND moti_nb_id = ".$_POST["busca_codigo"]."": "")
			.(!empty($_POST["busca_nome"])? 	" AND moti_tx_nome LIKE '%".$_POST["busca_nome"]."%'": "")
			.(!empty($_POST["busca_tipo"])? 	" AND moti_tx_tipo LIKE '%".$_POST["busca_tipo"]."%'": "")
			.(!empty($_POST["busca_legenda"])? 	" AND moti_tx_legenda LIKE '%".$legendas[$_POST["busca_legenda"]]."%'": "");

		$campos = [
			campo("Código", "busca_codigo", (!empty($_POST["busca_codigo"])? $_POST["busca_codigo"]: ""), 2, "MASCARA_NUMERO", "maxlength='6'"),
			campo("Nome", "busca_nome", (!empty($_POST["busca_nome"])? $_POST["busca_nome"]: ""), 5, "", "maxlength='65'"),
			combo("Tipo", "busca_tipo", (!empty($_POST["busca_tipo"])? $_POST["busca_tipo"]: ""), 2, ["", "Ajuste", "Abono"]),
			combo("Legenda", "busca_legenda", (!empty($_POST["busca_legenda"])? $_POST["busca_legenda"]: ""), 3, $legendas)
		];
		$botoes = [
			botao("Buscar","index"),
			botao("Inserir","layout_motivo","","","","","btn btn-success")
		];

		abre_form();
		linha_form($campos);
		fecha_form($botoes);

		$iconeModificar =	criarSQLIconeTabela("moti_nb_id", "modificarMotivo", "Modificar", "glyphicon glyphicon-search");
		$iconeExcluir = 	criarSQLIconeTabela("moti_nb_id", "excluirMotivo", "Excluir", "glyphicon glyphicon-remove", "Deseja inativar o registro?");

		$sql = 
			"SELECT *, 
				{$iconeModificar} as iconeModificar,
				IF(moti_tx_status = 'ativo', {$iconeExcluir}, NULL) as iconeExcluir
			FROM motivo 
				WHERE moti_tx_status = 'ativo' 
					{$extra};"
		;
		$gridParams = [
			"CÓDIGO" => "moti_nb_id",
			"NOME" => "moti_tx_nome",
			"TIPO" => "moti_tx_tipo",
			"LEGENDA" => "moti_tx_legenda",
			"<spam class='glyphicon glyphicon-search'></spam>" => "iconeModificar",
			"<spam class='glyphicon glyphicon-remove'></spam>" => "iconeExcluir"
		];

		grid($sql, array_keys($gridParams), array_values($gridParams), "", "12", 1, "desc");

		rodape();
	}
