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
		$subSetor = mysqli_fetch_all(query(
			"SELECT sbgr_nb_id, sbgr_tx_nome
			FROM `sbgrupos_documentos`
			WHERE `sbgr_nb_idgrup` = {$a_mod["grup_nb_id"]}
			ORDER BY sbgr_tx_nome ASC"
		), MYSQLI_ASSOC);

		[$_POST["id"], $_POST["nome"], $_POST["status"], $_POST["subsetores"]] = [$a_mod["grup_nb_id"], $a_mod["grup_tx_nome"], $a_mod["grup_tx_status"], $subSetor];
		
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

			if (!empty($_POST['subsetores_excluir'])) {
				$idsExcluir = explode(',', $_POST['subsetores_excluir']);

				foreach ($idsExcluir as $id) {
					$id = trim($id);
					if (!empty($id)) {
						query("DELETE FROM sbgrupos_documentos WHERE sbgr_nb_id = $id");
					}
				}
			}

			if (!empty($_POST["subsetores"])) {

				// Busca registros existentes
				$result = query("
					SELECT sbgr_nb_id, sbgr_tx_nome
					FROM sbgrupos_documentos
					WHERE sbgr_nb_idgrup = {$_POST['id']}
					ORDER BY sbgr_tx_nome ASC
				");

				// Converte em array associativo com a chave = ID
				$subSetorExistente = [];
				while ($row = mysqli_fetch_assoc($result)) {
					$subSetorExistente[$row['sbgr_nb_id']] = $row;
				}

				foreach ($_POST["subsetores"] as $id => $nome) {
					if (empty($nome)) {
						continue;
					}

					$novoSubSetor = [
						"sbgr_tx_nome"   => $nome,
						"sbgr_nb_idgrup" => $_POST["id"],
						"sbgr_tx_status" => "ativo"
					];

					if (!empty($id) && isset($subSetorExistente[$id])) {
						// Atualiza registro existente
						atualizar(
							"sbgrupos_documentos",
							array_keys($novoSubSetor),
							array_values($novoSubSetor),
							$id
						);

					} else {
						// Insere novo registro
						inserir(
							"sbgrupos_documentos",
							array_keys($novoSubSetor),
							array_values($novoSubSetor)
						);
					}
				}
			}
		}else{

				$id = inserir("grupos_documentos", array_keys($novoSetor), array_values($novoSetor));
				
				if(!empty($_POST["subsetores"])){
					foreach($_POST["subsetores"] as $subsetor){
						if(!empty($subsetor)){
							$novoSubSetor = [
								"sbgr_tx_nome" => $subsetor,
								"sbgr_nb_idgrup" => $id[0],
								"sbgr_tx_status" => "ativo"
							];

							inserir("sbgrupos_documentos", array_keys($novoSubSetor), array_values($novoSubSetor));
						}
					}
				}
			}

		index();
		exit;
	}

	function campoSubSetor($nome, $variavel, $valores = [], $tamanho = 2) {
		static $contador = 0;
		$contador++;

		$idLista = "listaSubsetores_$contador";
		$campoExcluir = "{$variavel}_excluir";

		if (!is_array($valores)) {
			$valores = array_filter([$valores]);
		}

		$camposExistentes = '';

		if (!empty($valores)) {
			foreach ($valores as $index => $valor) {
				$id = isset($valor['sbgr_nb_id']) ? htmlspecialchars($valor['sbgr_nb_id'], ENT_QUOTES) : '';
				$nomeSubsetor = isset($valor['sbgr_tx_nome']) ? htmlspecialchars($valor['sbgr_tx_nome'], ENT_QUOTES) : '';

				$botao = $index === 0
					? "<button class='btn btn-success btn-sm adicionar-novo' type='button' title='Adicionar novo subsetor'
							style='height:40px; margin-top: -10px; margin-left:-1px; border-top-left-radius:0; border-bottom-left-radius:0;'>
							<span class='glyphicon glyphicon-plus'></span>
						</button>"
					: "<button class='btn btn-danger btn-sm remover' type='button' title='Remover subsetor'
							style='height:40px; margin-top: -10px; margin-left:-1px; border-top-left-radius:0; border-bottom-left-radius:0;'>
							<span class='glyphicon glyphicon-minus'></span>
						</button>";

				$camposExistentes .= "
				<div class='col-sm-{$tamanho} margin-bottom-5 subsetor-item' data-id='{$id}'>
					<label class='control-label'>{$nome}</label>
					<div style='display:flex; align-items:center;'>
						<input
							type='text'
							name='{$variavel}[{$id}]'
							class='form-control input-sm campo-fit-content'
							placeholder='Nome do subsetor'
							style='width:120px;'
							value='{$nomeSubsetor}'
						>
						{$botao}
					</div>
				</div>";
			}
		} else {
			// Campo inicial (novo subsetor)
			$camposExistentes = "
			<div class='col-sm-{$tamanho} margin-bottom-5 subsetor-item'>
				<label class='control-label'>{$nome}</label>
				<div style='display:flex; align-items:center;'>
					<input
						type='text'
						name='{$variavel}[]'
						class='form-control input-sm campo-fit-content'
						placeholder='Nome do subsetor'
						style='width:120px;'
					>
					<button class='btn btn-success btn-sm adicionar-novo' type='button' title='Adicionar novo subsetor'
						style='height:40px; margin-top: -10px; margin-left:-1px; border-top-left-radius:0; border-bottom-left-radius:0;'>
						<span class='glyphicon glyphicon-plus'></span>
					</button>
				</div>
			</div>";
		}

		$campo = "
		<div class='form-group row' id='{$idLista}'>
			{$camposExistentes}
			<input type='hidden' name='{$campoExcluir}' id='{$campoExcluir}' value=''>
		</div>";

		$js = "
		<script>
		(function($){
			$(function(){
				var lista = $('#{$idLista}');
				var campoExcluir = $('#{$campoExcluir}');

				// Adicionar novo subsetor
				lista.on('click', '.adicionar-novo', function(){
					var novo = `
					<div class='col-sm-{$tamanho} margin-bottom-5 subsetor-item'>
						<label class='control-label'>$nome</label>
						<div style='display:flex; align-items:center;'>
							<input type='text' name='{$variavel}[]' class='form-control input-sm campo-fit-content' placeholder='Nome do subsetor' style='width:120px;'>
							<button class='btn btn-danger btn-sm remover' type='button' title='Remover subsetor' style='height:40px; margin-top: -10px; margin-left:-1px; border-top-left-radius:0; border-bottom-left-radius:0;'>
								<span class='glyphicon glyphicon-minus'></span>
							</button>
						</div>
					</div>`;
					lista.append(novo);
				});

				// Excluir subsetor
				lista.on('click', '.remover', function(){
					var item = $(this).closest('.subsetor-item');
					var id = item.data('id');

					if (id) {
						var existentes = campoExcluir.val();
						campoExcluir.val(existentes ? existentes + ',' + id : id);
					}

					item.remove();
				});
			});
		})(jQuery);
		</script>";

		return $campo . $js;
	}

    function layout_setor() {
		global $a_mod;

		cabecalho("Cadastro de Setor");

		if (!empty($_POST["id"])) {
			$campoStatus = combo("Status", "busca_banco", $_POST["busca_banco"]?? "", 2, [ "ativo" => "Ativo", "inativo" => "Inativo"]);
		}

		$campos = [
			campo("Nome*", "nome", $_POST["nome"], 2),
			$campoStatus,
			campoSubSetor('Subsetores', 'subsetores', $_POST["subsetores"] ?? []),
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
			"SUBSETORES"    	=> "subgrupos",
        ];

        $camposBusca = [
            "busca_codigo" 		=> "grup_nb_id",
            "busca_nome_like" 	=> "grup_tx_nome",
            "busca_status" 		=> "grup_tx_status",
        ];

        $queryBase = 
            "SELECT ".implode(", ", array_values($gridFields))." FROM ( "
			."SELECT 
				g.grup_nb_id,
				g.grup_tx_nome,
				g.grup_tx_status,
				GROUP_CONCAT(s.sbgr_tx_nome SEPARATOR ', ') AS subgrupos
			FROM grupos_documentos g"
			." LEFT JOIN sbgrupos_documentos s 
				ON g.grup_nb_id = s.sbgr_nb_idgrup
						GROUP BY 
							g.grup_nb_id,
							g.grup_tx_nome,
							g.grup_tx_status
					) AS final "
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