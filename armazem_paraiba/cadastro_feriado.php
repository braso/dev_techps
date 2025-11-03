<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
	include "conecta.php";


	function carregaJS(){

		$nomeCampoCidade = "busca_cidade";
		if(!empty($_POST["acao"]) && $_POST["acao"] == "layout_feriado()"){
			$nomeCampoCidade = "cidade";
		}

		return 
			"<script>
				function selecionaMunicipio(estado){
					let select3URL = 
						'{$_ENV["URL_BASE"]}{$_ENV["APP_PATH"]}/contex20/select3.php?'
						+'path={$_ENV["APP_PATH"]}{$_ENV["CONTEX_PATH"]}'
						+'&colunas='		+'".base64_encode("cida_nb_id AS id, CONCAT('[', cida_tx_uf, '] ', cida_tx_nome) AS text")."'
						+'&tabela='			+'".base64_encode("cidade")."'
						+'&condicoes='		+encodeURI('cida_tx_status = \"ativo\"'+ (estado!=''? ' AND cida_tx_uf = \"'+estado+'\"': ''))
						+'&ordem='			+'".base64_encode("cida_tx_uf ASC, cida_tx_nome ASC")."'
					;

					if(estado != ''){
						$('.{$nomeCampoCidade}').innerHTML = null;
					}

					$.fn.select2.defaults.set('theme', 'bootstrap');
					$('.{$nomeCampoCidade}').select2({
						language: 'pt-BR',
						placeholder: 'Selecione um item',
						allowClear: true,
						ajax: {
							url: select3URL,
							dataType: 'json',
							delay: 250,
							processResults: function(data){
								return {
									results: data
								};
							},
							cache: true
						}
					});
				}
				selecionaMunicipio('');
			</script>"
		;
	}

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


		// Conferir se já existe um feriado nessa data{
			$feriadoCadastrado = mysqli_fetch_assoc(query(
				"SELECT * FROM feriado
					WHERE feri_tx_status = 'ativo'
						AND feri_tx_data = '{$novoFeriado["feri_tx_data"]}'
						".((!empty($_POST["id"]))? "AND feri_nb_id != {$_POST["id"]}": "").";"
			));

			if(!empty($feriadoCadastrado)){
				set_status("ERRO: Já existe um feriado nesta data.");
				layout_feriado();
				exit;
			}
		//}

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


		$campos = [
			campo("Nome*", "nome", $_POST["nome"], 4, "", "maxlength='65'"),
			campo_data("Data*", "data", $_POST["data"], 2),
			combo("Estado", "uf", $_POST["uf"], 2, getUFs(), "onchange=selecionaMunicipio(this.value)"),
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

		carregaJS();
	}

	function index(){

		cabecalho("Cadastro de Feriado");

		echo "<style>
		form > div.row > div:nth-child(9){
		    display: none;
		}
		</style>";

		if(!isset($_POST["busca_status"])){
			$_POST["busca_status"] = "ativo";
		}

		$extra = "";

		$extra .= 
			 (!empty($_POST["busca_codigo"])? 		" AND feri_nb_id LIKE '%{$_POST["busca_codigo"]}%'": "")
			.(!empty($_POST["busca_nome_like"])?	" AND feri_tx_nome LIKE '%{$_POST["busca_nome_like"]}%'": "")
			.(!empty($_POST["busca_uf"])? 			" AND feri_tx_uf = '{$_POST["busca_uf"]}'": "")
			.(!empty($_POST["busca_cidade"])? 		" AND feri_nb_cidade = '{$_POST["busca_cidade"]}'": "")
		;

		$estados = getUFs();
		$estados[""] = "Todos";

		// $cidades = mysqli_fetch_all(query(
		// 	"SELECT cida_nb_id, cida_tx_nome FROM cidade ORDER BY cida_tx_nome ASC;"
		// ), MYSQLI_ASSOC);
		// $aux = ["" => "Todos"];
		// foreach($cidades as $cidade){
		// 	$aux[$cidade["cida_nb_id"]] = $cidade["cida_tx_nome"];
		// }
		// $cidades = $aux;

		$campos = [ 
			campo("Código", "busca_codigo", (empty($_POST["busca_codigo"])? "": $_POST["busca_codigo"]), 2, "MASCARA_NUMERO", "maxlength='6' min='0'"),
			campo("Nome", "busca_nome_like", (empty($_POST["busca_nome_like"])? "": $_POST["busca_nome_like"]), 4, "", "maxlength='45'"),
			combo("Estado", "busca_uf", (empty($_POST["busca_uf"])? "": $_POST["busca_uf"]), 2, $estados, "onchange=selecionaMunicipio(this.value)"),
			combo_net("Município", "busca_cidade", (empty($_POST["busca_cidade"])? "": $_POST["busca_cidade"]), 4, "cidade"),
			combo("Status", "busca_status", (empty($_POST["busca_status"])? "": $_POST["busca_status"]), 	2, ["ativo" => "Ativo"])
		];

		$botoes = [ 
			botao("Buscar", "index"),
			botao("Limpar Filtro", "limparFiltros"),
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
				"MUNICIPAL" => "cida_tx_nome",
				// "STATUS" 	=> "feri_tx_status"
			];

			$camposBusca = [
				"busca_codigo" 		=> "feri_nb_id",
				"busca_nome_like" 	=> "feri_tx_nome",
				"busca_uf" 			=> "feri_tx_uf",
				"busca_cidade" 		=> "cida_nb_id",
				"busca_status" 		=> "feri_tx_status",
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
				"orderCol = 'feri_tx_data DESC';
				const funcoesInternas = function(){
					".implode(" ", $actions["functions"])."
				}"
			;

			echo gridDinamico("tabelaFeriados", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		//}


		echo carregaJS();

		rodape();
	}