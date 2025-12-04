<?php
	/* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

	include "funcoes_ponto.php";

	function carregarJS(){
		echo 
			"<script>
					function selecionaMotorista(idEmpresa) {
					let condicoes = encodeURI('AND enti_tx_ocupacao IN (\"Motorista\", \"Ajudante\", \"Funcionário\")' +
						(idEmpresa > 0 ? ' AND enti_nb_empresa = \"' + idEmpresa + '\"' : '')
					);

					if ($('.busca_motorista').data('select2')) {// Verifica se o elemento está usando Select2 antes de destruí-lo
						$('.busca_motorista').select2('destroy');
						$('.busca_motorista').html('');
						$('.busca_motorista').val('');
					}

					$.fn.select2.defaults.set('theme', 'bootstrap');
					$('.busca_motorista').select2({
						language: 'pt-BR',
						placeholder: 'Selecione um item',
						allowClear: true,
						ajax: {
							url: appPath + '/contex20/select2.php?path=' + contexPath + '&tabela=entidade&ordem=&limite=15&condicoes=' + condicoes + '&colunas=enti_tx_matricula',
							dataType: 'json',
							delay: 250,
							processResults: function (data) {
								return { results: data };
							},
							cache: true,
							success: function (result) {
							},
							error: function (jqxhr, status, exception) {
								alert('Exception:', exception);
							}
						}
					});
				}
			</script>"
		;
	}

	function cadastra_ferias(){
		// Conferir se os campos obrigatórios estão preenchidos{
			
			$camposObrig = [
				"motorista" => "Funcionário",
				"periodo_ferias" => "Data"
			];

			$errorMsg = conferirCamposObrig($camposObrig, $_POST);

			if(!empty($errorMsg)){
				set_status("ERRO: ".$errorMsg);
				layout_ferias();
				exit;
			}
		// }

		$_POST["busca_motorista"] = $_POST["motorista"];

		$begin = $_POST["periodo_ferias"][0];
		$end = $_POST["periodo_ferias"][1];
		//Conferir se há um endosso entrelaçado com essa data{
			$endosso = mysqli_fetch_assoc(
				query(
					"SELECT endo_tx_de, endo_tx_ate FROM endosso
						WHERE endo_tx_status = 'ativo'
							AND endo_nb_entidade = ".$_POST["motorista"]."
							AND (
								'{$begin}' BETWEEN endo_tx_de AND endo_tx_ate
								OR '{$end}' BETWEEN endo_tx_de AND endo_tx_ate
								OR endo_tx_de BETWEEN '{$begin}' AND '{$end}'
								OR endo_tx_ate BETWEEN '{$begin}' AND '{$end}'
							)
						LIMIT 1;"
				)
			);

			if(!empty($endosso)){
				$endosso["endo_tx_de"] = explode("-", $endosso["endo_tx_de"]);
				$endosso["endo_tx_de"] = $endosso["endo_tx_de"][2]."/".$endosso["endo_tx_de"][1]."/".$endosso["endo_tx_de"][0];

				$endosso["endo_tx_ate"] = explode("-", $endosso["endo_tx_ate"]);
				$endosso["endo_tx_ate"] = $endosso["endo_tx_ate"][2]."/".$endosso["endo_tx_ate"][1]."/".$endosso["endo_tx_ate"][0];

				$_POST["errorFields"][] = "periodo_ferias";
				set_status("ERRO: Possui um endosso de ".$endosso["endo_tx_de"]." até ".$endosso["endo_tx_ate"].".");
				layout_ferias();
				exit;
			}
		//}

		$feriasExistente = mysqli_fetch_assoc(query(
			"SELECT * FROM ferias 
				WHERE feri_tx_status = 'ativo'
					AND feri_nb_entidade = '{$_POST["motorista"]}'
					AND (
						'{$begin}' BETWEEN feri_tx_dataInicio AND feri_tx_dataFim
						OR '{$end}' BETWEEN feri_tx_dataInicio AND feri_tx_dataFim
						OR feri_tx_dataInicio BETWEEN '{$begin}' AND '{$end}'
						OR feri_tx_dataFim BETWEEN '{$begin}' AND '{$end}'
					)
				LIMIT 1;"
		));

		$novasFerias = [
			"feri_nb_entidade" 		=> $_POST["motorista"],
			"feri_tx_dataInicio" 	=> $begin,
			"feri_tx_dataFim" 		=> $end,
			"feri_tx_status" 		=> "ativo"
		];

		if(!empty($feriasExistente) && empty($_POST["id"])){//Tentando cadastrar novas férias em datas com férias já existentes
			$inicio = DateTime::createFromFormat("Y-m-d", $feriasExistente["feri_tx_dataInicio"])->format("d/m/Y");
			$fim = DateTime::createFromFormat("Y-m-d", $feriasExistente["feri_tx_dataFim"])->format("d/m/Y");

			$_POST["errorFields"][] = "periodo_ferias";
			set_status("ERRO: Já existe férias cadastradas de {$inicio} a {$fim}.");
			layout_ferias();
		}elseif(!empty($_POST["id"])){//Modificando férias já existentes
			atualizar("ferias", array_keys($novasFerias), array_values($novasFerias), $_POST["id"]);
			set_status("Registro atualizado com sucesso.");
		}else{//Cadastrando novas férias
			inserir("ferias", array_keys($novasFerias), array_values($novasFerias));
			set_status("Registro inserido com sucesso.");
		}

		index();
		exit;
	}

	function modificarFerias(){
		$ferias = mysqli_fetch_assoc(query(
			"SELECT * from ferias 
				WHERE feri_tx_status = 'ativo'
					AND feri_nb_id = '{$_POST["id"]}'
			LIMIT 1;"
		));

		if(!empty($ferias)){
			$_POST = array_merge($_POST, $ferias);

			$_POST["motorista"] = $_POST["feri_nb_entidade"];
			$_POST["periodo_ferias"] = [$_POST["feri_tx_dataInicio"], $_POST["feri_tx_dataFim"]];
		}

		layout_ferias();
		exit;
	}

	function excluirFerias(){
		$ferias = mysqli_fetch_assoc(query("SELECT * from ferias LIMIT 1;"));

		if(empty($ferias)){
			set_status("Férias já inativada.");
			index();
			exit;
		}

		atualizar("ferias", ["feri_tx_status"], ["inativo"], $_POST["id"]);
		index();
		exit;
	}

	function layout_ferias(){
    	
		cabecalho("Cadastro de Férias");

		$campos[0] = [
			combo_net(
				"Funcionário*",
				"motorista",
				(!empty($_POST["motorista"])? $_POST["motorista"]: $_POST["busca_motorista"]?? ""),
				4,
				"entidade",
				"",
				" AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')",
				"enti_tx_matricula"
			),
			campo("Período*", "periodo_ferias", ($_POST["periodo_ferias"]?? ""),3, "MASCARA_PERIODO_SEM_LIMITE"),
			campo_hidden("id", ($_POST["id"]?? "")),
		];

		echo abre_form();
		echo linha_form($campos[0]);


		//BOTOES{
    		$b[] = botao("Inserir novo", "cadastra_ferias", "", "", "", "", "btn btn-success");
			unset($_POST["errorFields"]);
			$_POST["busca_periodo"] = !empty($_POST["busca_periodo"])? implode(" - ", $_POST["busca_periodo"]): null;
			$b[] = criarBotaoVoltar("espelho_ponto.php");
		//}
		echo fecha_form($b);
		
		rodape();
		exit;
	}
	
	function index(){
		
		//ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
		include "check_permission.php";
		// APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
		verificaPermissao('/cadastro_ferias.php');
		
		cabecalho("Férias");

		$camposBusca = [
			campo("Código",	"busca_codigo",	(!empty($_POST["busca_codigo"])? $_POST["busca_codigo"]: ""), 1,"","maxlength='6'"),
			campo("Nome do Funcionário", "busca_nome_like", ($a_mod["enti_tx_nome"]?? ""), 4, "", "maxlength='65'"),
			combo("Status",	"busca_status",	(isset($_POST["busca_status"])? $_POST["busca_status"]: "ativo"), 2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"])
		];

		$botoesBusca = [
			botao("Inserir", "layout_ferias","","","","","btn btn-success"),
			botao("Limpar Filtros", "limparFiltros")
		];

		echo abre_form();
		echo linha_form($camposBusca);
		echo fecha_form([], "<hr><form>".implode(" ", $botoesBusca)."</form>");

		//Configuração da tabela dinâmica{
			$gridFields = [
				"CÓDIGO" 				=> "feri_nb_id",
				"Funcionário" 			=> "enti_tx_nome",
				"Início" 				=> "CONCAT('data(\"', feri_tx_dataInicio, '\")') AS feri_tx_dataInicio",
				"Fim" 					=> "CONCAT('data(\"', feri_tx_dataFim, '\")') AS feri_tx_dataFim",
				"Qtd. Dias" 			=> "DATEDIFF(feri_tx_dataFim, feri_tx_dataInicio) AS qtdDias",
				"STATUS" 				=> "feri_tx_status",
			];
	
			$camposBusca = [
				"busca_codigo" => "feri_nb_id",
				"busca_nome_like" => "enti_tx_nome",
				"busca_status" => "feri_tx_status"
			];
	
			$queryBase = ("SELECT ".implode(", ", array_values($gridFields))." FROM ferias JOIN entidade ON feri_nb_entidade = enti_nb_id");
	
			$actions = criarIconesGrid(
				["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
				["cadastro_ferias.php", "cadastro_ferias.php"],
				["modificarFerias()", "excluirFerias()"]
			);
	
			$actions["functions"][1] .= 
				"esconderInativar('glyphicon glyphicon-remove search-remove', ".array_search("STATUS", array_keys($gridFields)).");"
			;
	
			$gridFields["actions"] = $actions["tags"];
	
			$jsFunctions =
				"orderCol = 'feri_tx_dataInicio DESC';
				const funcoesInternas = function(){
					".implode(" ", $actions["functions"])."
				}"
			;
	
			echo gridDinamico("tabelaFerias", $gridFields, $camposBusca, $queryBase, $jsFunctions);
		//}

		
		rodape();

		carregarJS();
	}