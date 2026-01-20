<?php

		ini_set("display_errors", 1);
		error_reporting(E_ALL);


	include "conecta.php";

	function excluirComunicado(){
		// remover("comunicado_interno", $_POST["id"]);
        $id = intval($_POST["id"]);
        query("UPDATE comunicado_interno SET coin_tx_status = 'inativo' WHERE coin_nb_id = $id LIMIT 1;");
		index();
		exit;
	}

	function modificarComunicado(){
		$_POST = array_merge($_POST, mysqli_fetch_assoc(query("SELECT * FROM comunicado_interno WHERE coin_nb_id = {$_POST["id"]};")));
		layout_comunicado();
		exit;
	}

	function cadastra_comunicado(){
		$camposObrig = [
			"titulo" => "Título",
			"tipo_conteudo" => "Tipo de Conteúdo"
		];
		$errorMsg = conferirCamposObrig($camposObrig, $_POST);
		
		if($_POST["tipo_conteudo"] == "texto" && empty($_POST["texto"])){
			$errorMsg .= (empty($errorMsg) ? "" : " ") . "O texto é obrigatório.";
		}
		if($_POST["tipo_conteudo"] == "imagem" && empty($_FILES["imagem"]["name"]) && empty($_POST["imagem_atual"])){
			$errorMsg .= (empty($errorMsg) ? "" : " ") . "A imagem é obrigatória.";
		}

		if(!empty($errorMsg)){
			set_status("ERRO: ".$errorMsg);
			layout_comunicado();
			exit;
		}

        $allowed = '<a><b><strong><i><em><u><br><p><ul><ol><li><h1><h2><h3><h4>';
        $html = isset($_POST["texto"]) ? $_POST["texto"] : "";
        $html = strip_tags($html, $allowed);
        $html = preg_replace('/on\\w+\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace('/href\s*=\s*"\s*javascript:[^"]*"/i', 'href="#"', $html);

		// Upload de imagem
		$caminhoImagem = isset($_POST["imagem_atual"]) ? $_POST["imagem_atual"] : null;
		if($_POST["tipo_conteudo"] == "imagem" && !empty($_FILES["imagem"]["name"])){
			$dir = "arquivos/comunicados/";
			if(!is_dir($dir)) mkdir($dir, 0777, true);
			
			$ext = pathinfo($_FILES["imagem"]["name"], PATHINFO_EXTENSION);
			$nomeArq = "comunicado_".date("YmdHis")."_".uniqid().".".$ext;
			
			if(move_uploaded_file($_FILES["imagem"]["tmp_name"], $dir.$nomeArq)){
				$caminhoImagem = $dir.$nomeArq;
			} else {
				set_status("ERRO: Falha ao fazer upload da imagem.");
				layout_comunicado();
				exit;
			}
		}

        $novo = [
            "coin_tx_titulo" => $_POST["titulo"],
            "coin_tx_tipo_conteudo" => $_POST["tipo_conteudo"],
            "coin_tx_texto" => ($_POST["tipo_conteudo"] == "texto" ? $html : null),
            "coin_tx_imagem" => ($_POST["tipo_conteudo"] == "imagem" ? $caminhoImagem : null),
            "coin_tx_dest_perfis" => !empty($_POST["perfis"]) ? (is_array($_POST["perfis"]) ? implode(",", $_POST["perfis"]) : $_POST["perfis"]) : null,
            "coin_tx_dest_setores" => !empty($_POST["setores"]) ? (is_array($_POST["setores"]) ? implode(",", $_POST["setores"]) : $_POST["setores"]) : null,
            "coin_tx_dest_subsetores" => !empty($_POST["subsetores"]) ? (is_array($_POST["subsetores"]) ? implode(",", $_POST["subsetores"]) : $_POST["subsetores"]) : null,
            "coin_tx_dest_cargos" => !empty($_POST["cargos"]) ? (is_array($_POST["cargos"]) ? implode(",", $_POST["cargos"]) : $_POST["cargos"]) : null,
            "coin_tx_status" => "ativo"
        ];
		
		// Determina um destino geral para exibição na grid (opcional)
		$destinos_resumo = [];
		if(!empty($novo["coin_tx_dest_perfis"])) $destinos_resumo[] = "Perfis";
		if(!empty($novo["coin_tx_dest_setores"])) $destinos_resumo[] = "Setores";
		if(!empty($novo["coin_tx_dest_cargos"])) $destinos_resumo[] = "Cargos";
		$novo["coin_tx_destino"] = !empty($destinos_resumo) ? implode(", ", $destinos_resumo) : "Todos";

        if(!empty($_POST["id"])){
            atualizar("comunicado_interno", array_keys($novo), array_values($novo), $_POST["id"]);
        }else{
            $novo["coin_nb_userCadastro"] = $_SESSION["user_nb_id"];
            $novo["coin_tx_dataCadastro"] = date("Y-m-d H:i:s");
            inserir("comunicado_interno", array_keys($novo), array_values($novo));
        }
        
		index();
		exit;
	}

	function layout_comunicado(){
		cabecalho("Cadastro de Comunicado Interno");

		// Carregar listas para os selects
		$perfis = [
			"todos" => "Todos",
			"Super Administrador" => "Super Administrador",
			"Administrador" => "Administrador",
			"Funcionário" => "Funcionário",
			"Motorista" => "Motorista",
			"Ajudante" => "Ajudante"
		];

		$setores = ["todos" => "Todos"];
		$r = query("SELECT grup_nb_id, grup_tx_nome FROM grupos_documentos ORDER BY grup_tx_nome");
		while($row = mysqli_fetch_assoc($r)) $setores[$row['grup_nb_id']] = $row['grup_tx_nome'];
		
		$subsetores = ["todos" => "Todos"];
		// Se já tiver setores selecionados, carrega os subsetores correspondentes
		if(!empty($_POST["coin_tx_dest_setores"]) || !empty($_POST["setores"])){
			$idSetor = !empty($_POST["setores"]) ? $_POST["setores"] : $_POST["coin_tx_dest_setores"];
			
			if($idSetor == 'todos'){
				$r = query("SELECT sbgr_nb_id, sbgr_tx_nome FROM sbgrupos_documentos ORDER BY sbgr_tx_nome");
				while($row = mysqli_fetch_assoc($r)) $subsetores[$row['sbgr_nb_id']] = $row['sbgr_tx_nome'];
			} elseif(!empty($idSetor)){
				$idSetor = intval($idSetor);
				$r = query("SELECT sbgr_nb_id, sbgr_tx_nome FROM sbgrupos_documentos WHERE sbgr_nb_idgrup = $idSetor ORDER BY sbgr_tx_nome");
				while($row = mysqli_fetch_assoc($r)) $subsetores[$row['sbgr_nb_id']] = $row['sbgr_tx_nome'];
			}
		}

		$cargos = ["todos" => "Todos"];
		$r = query("SELECT oper_nb_id, oper_tx_nome FROM operacao WHERE oper_tx_status = 'ativo' ORDER BY oper_tx_nome");
		while($row = mysqli_fetch_assoc($r)) $cargos[$row['oper_nb_id']] = $row['oper_tx_nome'];

		$tipos_conteudo = ["texto" => "Texto", "imagem" => "Imagem"];

        $initialTexto = (!empty($_POST["coin_tx_texto"]) ? $_POST["coin_tx_texto"] : (!empty($_POST["texto"]) ? $_POST["texto"] : ""));
        $editor = "<div id='div-editor' class='col-sm-12 margin-bottom-5'>"
            ."<label>Conteúdo (Texto)*</label>"
            ."<textarea name='texto' class='form-control' style='min-height:240px; resize:vertical; overflow:hidden;' oninput='this.style.height = \"\"; this.style.height = this.scrollHeight + \"px\"'>".htmlspecialchars($initialTexto)."</textarea>"
        ."</div>"
        ."<script>window.addEventListener('load', function(){ var tx = document.querySelector('textarea[name=\"texto\"]'); if(tx){ tx.style.height = tx.scrollHeight + 'px'; } });</script>";

		$imgAtual = !empty($_POST["coin_tx_imagem"]) ? $_POST["coin_tx_imagem"] : (!empty($_POST["imagem_atual"]) ? $_POST["imagem_atual"] : "");
		$campoImagem = "<div id='div-imagem' class='col-sm-12 margin-bottom-5'>"
			."<label>Conteúdo (Imagem)*</label>"
			."<input type='file' name='imagem' class='form-control' accept='image/*'>"
			.(!empty($imgAtual) ? "<div style='margin-top:5px'>Imagem Atual: <a href='$imgAtual' target='_blank'>Visualizar</a></div><input type='hidden' name='imagem_atual' value='$imgAtual'>" : "")
			."</div>";

		// Valores selecionados
		$selPerfis = !empty($_POST["coin_tx_dest_perfis"]) ? $_POST["coin_tx_dest_perfis"] : (!empty($_POST["perfis"]) ? $_POST["perfis"] : "");
		$selSetores = !empty($_POST["coin_tx_dest_setores"]) ? $_POST["coin_tx_dest_setores"] : (!empty($_POST["setores"]) ? $_POST["setores"] : "");
		$selSubsetores = !empty($_POST["coin_tx_dest_subsetores"]) ? $_POST["coin_tx_dest_subsetores"] : (!empty($_POST["subsetores"]) ? $_POST["subsetores"] : "");
		$selCargos = !empty($_POST["coin_tx_dest_cargos"]) ? $_POST["coin_tx_dest_cargos"] : (!empty($_POST["cargos"]) ? $_POST["cargos"] : "");
		$selTipo = !empty($_POST["coin_tx_tipo_conteudo"]) ? $_POST["coin_tx_tipo_conteudo"] : (!empty($_POST["tipo_conteudo"]) ? $_POST["tipo_conteudo"] : "texto");

        $campos = [
            campo("Título*", "titulo", (!empty($_POST["coin_tx_titulo"])? $_POST["coin_tx_titulo"]: (!empty($_POST["titulo"]) ? $_POST["titulo"] : "")), 12, "", "maxlength='150'"),
			combo("Tipo de Conteúdo*", "tipo_conteudo", $selTipo, 3, $tipos_conteudo, "onchange='toggleConteudo()'"),
			"<div class='clearfix'></div>",
			combo("Destino: Perfis", "perfis", $selPerfis, 3, $perfis, ""),
			combo("Destino: Setores", "setores", $selSetores, 3, $setores, "onchange='atualizaSubsetores()'"),
			combo("Destino: Subsetores", "subsetores", $selSubsetores, 3, $subsetores, ""),
			combo("Destino: Cargos", "cargos", $selCargos, 3, $cargos, ""),
			"<div class='clearfix'></div>",
            $editor,
			$campoImagem
        ];

		$botoes = [
			botao("Gravar", "cadastra_comunicado", "id", (!empty($_POST["coin_nb_id"]) ? $_POST["coin_nb_id"] : (!empty($_POST["id"]) ? $_POST["id"] : NULL)), "", "", "btn btn-success"),
			criarBotaoVoltar()
		];

		echo abre_form("Dados do Comunicado Interno");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"] ?? ( $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/index.php"));
		echo linha_form($campos);
		echo fecha_form($botoes);

		echo "
		<script>
		function toggleConteudo(){
			var tipo = document.getElementsByName('tipo_conteudo')[0].value;
			if(tipo == 'imagem'){
				document.getElementById('div-editor').style.display = 'none';
				document.getElementById('div-imagem').style.display = 'block';
			} else {
				document.getElementById('div-editor').style.display = 'block';
				document.getElementById('div-imagem').style.display = 'none';
			}
		}
		
		function atualizaSubsetores(){
			// Lógica para carregar subsetores via AJAX se necessário
			var setor = $('[name=\"setores\"]').val();
			var el = $('[name=\"subsetores\"]');
			
			if(!setor){
				el.empty();
				el.append(new Option(\"Todos\", \"todos\", false, false));
				return;
			}
			
			var url = '../contex20/select2.php?path=".$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."&tabela=sbgrupos_documentos';
			if(setor !== 'todos'){
				url += '&condicoes=' + encodeURIComponent('AND sbgr_nb_idgrup = ' + setor);
			}
			
			$.ajax({ url: url, dataType: 'json' }).done(function(data){
				el.empty();
				// Adiciona opção Todos
				el.append(new Option(\"Todos\", \"todos\", false, false));
				
				if (Array.isArray(data)) {
					data.forEach(function(item){
						var o = new Option(item.text, item.id, false, false);
						el.append(o);
					});
				}
			});
		}

		$(document).ready(function() {
			toggleConteudo();
		});
		</script>
		";

		rodape();
	}

	function index(){

			//ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
		include "check_permission.php";
		// APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
		verificaPermissao('/cadastro_comunicado_interno.php');

	
		cabecalho("Cadastro de Comunicado Interno");

		$destinos = [
			"" => "Todos",
			"Administrador" => "Administradores",
			"Funcionário" => "Funcionários"
		];

		$campos = [
			campo("Código", "busca_codigo", (!empty($_POST["busca_codigo"]) ? $_POST["busca_codigo"] : ""), 2, "MASCARA_NUMERO", "maxlength='6'"),
			campo("Título", "busca_titulo_like", (!empty($_POST["busca_titulo_like"]) ? $_POST["busca_titulo_like"] : ""), 6, "", "maxlength='150'"),
			combo("Destino", "busca_destino", (!empty($_POST["busca_destino"]) ? $_POST["busca_destino"] : ""), 3, $destinos),
			campo_hidden("busca_status", "ativo")
		];

		$botoes = [
			botao("Buscar", "index"),
			botao("Inserir", "layout_comunicado", "", "", "", "", "btn btn-success")
		];

		echo abre_form();
		echo linha_form($campos);
		echo fecha_form($botoes);

		$gridFields = [
			"CÓDIGO" => "coin_nb_id",
			"TÍTULO" => "coin_tx_titulo",
			"DESTINO" => "coin_tx_destino",
			"CADASTRO" => "coin_tx_dataCadastro"
		];

		$camposBusca = [
			"busca_codigo" => "coin_nb_id",
			"busca_titulo_like" => "coin_tx_titulo",
			"busca_destino" => "coin_tx_destino",
			"busca_status" => "coin_tx_status"
		];

		$queryBase = ("SELECT ".implode(", ", array_values($gridFields))." FROM comunicado_interno");

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-trash search-remove"],
            ["cadastro_comunicado_interno.php", "cadastro_comunicado_interno.php"],
            ["modificarComunicado()", "excluirComunicado()"]
        );

		$gridFields["actions"] = $actions["tags"];

		$jsFunctions =
			"const funcoesInternas = function(){".
				implode(" ", $actions["functions"]).
			"}";

		echo gridDinamico("tabelaComunicados", $gridFields, $camposBusca, $queryBase, $jsFunctions);

		rodape();
	}

	index();
?>