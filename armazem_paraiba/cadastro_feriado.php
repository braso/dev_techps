<?php
	include "conecta.php";

	function excluirFeriado(){
		remover("feriado",$_POST["id"]);
		index();
		exit;

	}
	function modificarFeriado(){
		$a_mod = carregar("feriado", $_POST["id"]);
		
		[$_POST["id"], $_POST["nome"], $_POST["data"], $_POST["uf"], $_POST["cidade"]] = [$a_mod["feri_nb_id"], $a_mod["feri_tx_nome"], $a_mod["feri_tx_data"], $a_mod["feri_tx_uf"], $a_mod["feri_nb_cidade"]];
		
		layout_feriado();
		exit;
	}

	function cadastra_feriado(){
		$camposObrig = [
			"nome" => "Nome",
			"data" => "Data"
		];
		$errorMsg = conferirCamposObrig($camposObrig, $_POST);
		if(!empty($errorMsg)){
			set_status("ERRO: ".$errorMsg);
			layout_feriado();
			exit;
		}
		
		if (empty($_POST["cidade"])) {
			$_POST["cidade"] = NULL;
		}
		if (empty($_POST["uf"])) {
			$_POST["uf"] = NULL;
		}

		$novoFeriado = [
			"feri_tx_nome" => $_POST["nome"],
			"feri_tx_data" => $_POST["data"],
			"feri_tx_uf" => $_POST["uf"],
			"feri_nb_cidade" => $_POST["cidade"],
			"feri_tx_status" => "ativo"
		];

		if(!empty($_POST["id"])){
			atualizar("feriado", array_keys($novoFeriado), array_values($novoFeriado), $_POST["id"]);
		}else{
			$novoFeriado["feri_nb_userCadastro"] = $_SESSION["user_nb_id"];
			$novoFeriado["feri_tx_dataCadastro"] = date("Y-m-d H:i:s");

			inserir("feriado", array_keys($novoFeriado), array_values($novoFeriado));
		}

		index();
		exit;
	}


	function layout_feriado(){
		global $a_mod;

		cabecalho("Cadastro de Feriado");

		$ufs = ["", "AC", "AL", "AP", "AM", "BA", "CE", "DF", "ES", "GO", "MA", "MS", "MT", "MG", "PA", "PB", "PR", "PE", "PI", "RJ", "RN", "RS", "RO", "RR", "SC", "SP", "SE", "TO"];
		
		$campos = [
			campo("Nome*", "nome", $_POST["nome"], 4),
			campo_data("Data*", "data", $_POST["data"], 2),
			combo("Estado", "uf", $_POST["uf"], 2, $ufs),
			combo_net("Município", "cidade", $_POST["cidade"], 4, "cidade", "", "", "cida_tx_uf")
		];

		$botoes = [
			botao("Gravar", "cadastra_feriado", "id", $_POST["id"], "", "", "btn btn-success"),
			criarBotaoVoltar()
		];
		
		echo abre_form("Dados do Feriado");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]);
		echo linha_form($campos);
		echo fecha_form($botoes);

		rodape();

	}

	function index(){

		cabecalho("Cadastro de Feriado");
		$extra = "";

		$extra .= (($_POST["busca_codigo"])? " AND feri_nb_id LIKE '%".$_POST["busca_codigo"]."%'": "")
			.(($_POST["busca_nome_like"])?" AND feri_tx_nome LIKE '%".$_POST["busca_nome_like"]."%'": "")
			.(($_POST["busca_uf_like"])? " AND feri_tx_uf = '".$_POST["busca_uf_like"]."'": "")
			.(($_POST["busca_cidade_like"])? " AND feri_nb_cidade = '".$_POST["busca_cidade_like"]."'": "")
		;

		$campos = [ 
			campo("Código", "busca_codigo", $_POST["busca_codigo"], 2, "MASCARA_NUMERO", "maxlength='6'"),
			campo("Nome", "busca_nome_like", $_POST["busca_nome_like"], 4, "", "maxlength='65'"),
			campo("Estado", "busca_uf_like", $_POST["busca_uf_like"], 2),
			campo("Município", "busca_cidade_like", $_POST["busca_cidade_like"], 2)
		];

		$botoes = [ 
			botao("Buscar", "index"),
			botao("Inserir", "layout_feriado", "", "", "", "", "btn btn-success")
		];
		
		echo abre_form();
		echo linha_form($campos);
		echo fecha_form($botoes);

		//Grid dinâmica{
			$gridFields = [
				"CÓDIGO" 	=> "feri_nb_id",
				"NOME" 		=> "feri_tx_nome",
				"DATA" 		=> "CONCAT('data(\"', feri_tx_data, '\")') AS feri_tx_data",
				"ESTADUAL" 	=> "feri_tx_uf",
				"MUNICIPAL" => "cida_tx_nome"
			];

			$camposBusca = [
				"busca_codigo" 	=> "feri_nb_id",
				"busca_nome_like" 	=> "feri_tx_nome",
				"busca_uf_like" 		=> "feri_tx_uf",
				"busca_cidade_like" 	=> "cida_tx_nome"
			];

			$queryBase = (
				"SELECT ".implode(", ", array_values($gridFields))." FROM feriado
					LEFT JOIN cidade ON cida_nb_id = feri_nb_cidade"
			);

			$actions = criarIconesGrid(
				["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
				["cadastro_feriado.php", "cadastro_feriado.php"],
				["modificarFeriado()", "excluirFeriado()"]
			);
			$gridFields["actions"] = $actions["tags"];

			$jsFunctions =
				"const funcoesInternas = function(){
					".implode(" ", $actions["functions"])."
				}"
			;

			echo gridDinamico("tabelaFeriados", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		//}

		rodape();
	}