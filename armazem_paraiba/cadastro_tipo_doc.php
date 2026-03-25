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
		[$_POST["id"], $_POST["nome"], $_POST["setor"], $_POST["vencimento"], $_POST["assinatura"], $_POST["sub-setor"], $_POST["status"]] = [$a_mod["tipo_nb_id"], $a_mod["tipo_tx_nome"], $a_mod["tipo_nb_grupo"],$a_mod["tipo_tx_vencimento"], $a_mod["tipo_tx_assinatura"], $a_mod["tipo_nb_sbgrupo"], $a_mod["tipo_tx_status"]];
		layout_tipo_doc();
		exit;
	}

    function cadastra_tipo_doc() {
        $logEntry = date("Y-m-d H:i:s") . " POST: " . print_r($_POST, true) . "\n";
        file_put_contents(__DIR__ . "/debug_log.txt", $logEntry, FILE_APPEND);

        $_POST["nome"] = trim($_POST["nome"] ?? "");
        $_POST["vencimento"] = in_array($_POST["vencimento"] ?? "nao", ["sim","nao"]) ? $_POST["vencimento"] : "nao";
        $_POST["assinatura"] = in_array($_POST["assinatura"] ?? "nao", ["sim","nao"]) ? $_POST["assinatura"] : "nao";

        $errorMsg = conferirCamposObrig(["nome" => "Nome", "setor" => "Setor"], $_POST);
        if(!empty($errorMsg)){
            set_status("ERRO: ".$errorMsg);
            layout_tipo_doc();
            exit;
        }

        $setorId = !empty($_POST["setor"]) ? (int)$_POST["setor"] : null;
        $subSetorId = !empty($_POST["sub-setor"]) ? (int)$_POST["sub-setor"] : null;

        if(!empty($subSetorId)){
            $row = mysqli_fetch_assoc(query(
                "SELECT sbgr_nb_idgrup FROM sbgrupos_documentos WHERE sbgr_nb_id = ? LIMIT 1",
                "i",
                [$subSetorId]
            ));
            if(empty($row) || (int)$row["sbgr_nb_idgrup"] !== $setorId){
                set_status("ERRO: Subsetor não pertence ao setor selecionado.");
                layout_tipo_doc();
                exit;
            }
        }

        $dupSql = !empty($_POST["id"]) ?
            [
                "SELECT tipo_nb_id FROM tipos_documentos WHERE tipo_tx_nome = ? AND tipo_nb_id != ? LIMIT 1;",
                "si",
                [$_POST["nome"], (int)$_POST["id"]]
            ] :
            [
                "SELECT tipo_nb_id FROM tipos_documentos WHERE tipo_tx_nome = ? LIMIT 1;",
                "s",
                [$_POST["nome"]]
            ];
        $jaExiste = mysqli_fetch_assoc(query($dupSql[0], $dupSql[1], $dupSql[2]));
        if(!empty($jaExiste)){
            set_status("ERRO: Já existe um tipo de documento com este nome.");
            layout_tipo_doc();
            exit;
        }

        $novoTipo= [
            "tipo_tx_nome" => $_POST["nome"],
            "tipo_nb_grupo" => $setorId,
            "tipo_nb_sbgrupo" => $subSetorId,
            "tipo_tx_vencimento" => $_POST["vencimento"],
            "tipo_tx_assinatura" => $_POST["assinatura"],
            "tipo_tx_status" => "ativo"
        ];

        if(!empty($_POST["id"])){
            atualizar("tipos_documentos", array_keys($novoTipo), array_values($novoTipo), $_POST["id"]);
        }else{
            $res = inserir("tipos_documentos", array_keys($novoTipo), array_values($novoTipo));
            if(gettype($res[0]) == "object"){
                set_status("ERRO: ".$res[0]->getMessage());
                layout_tipo_doc();
                exit;
            }
        }

        index();
        exit;
    }

	function criaSectionSubSetor($subSetores, $subSetorSelecionado = null, $tamanho) {
		$js = "
		<script>
		// Dados dos sub-setores
		const seusDados = ". json_encode($subSetores) . ";

		function carregarSubSetores(setorId, subSetorSelecionado = null) {
			const selectSubSetor = document.getElementById('sub-setor');
			selectSubSetor.innerHTML = '<option value=\"\">Selecione um sub-setor</option>';
			
			const subSetoresFiltrados = seusDados.filter(subSetor => 
				subSetor.sbgr_nb_idgrup === setorId
			);
			
			subSetoresFiltrados.forEach(subSetor => {
				const option = document.createElement('option');
				option.value = subSetor.sbgr_nb_id;
				option.textContent = subSetor.sbgr_tx_nome;
				
				// Marcar como selecionado se for o sub-setor que deve estar selecionado
				if (subSetorSelecionado && subSetorSelecionado === subSetor.sbgr_nb_id) {
					option.selected = true;
				}
				
				selectSubSetor.appendChild(option);
			});
			
			if (subSetoresFiltrados.length === 0) {
				selectSubSetor.innerHTML = '<option value=\"\">Nenhum sub-setor disponível</option>';
			}
		}

		// Event listener
		document.getElementById('setor').addEventListener('change', function() {
			const setorSelecionado = this.value;
			if (setorSelecionado) {
				carregarSubSetores(setorSelecionado);
			} else {
				document.getElementById('sub-setor').innerHTML = '<option value=\"\">Selecione um setor primeiro</option>';
			}
		});

		// Inicializar
		document.addEventListener('DOMContentLoaded', function() {
			const setorInicial = document.getElementById('setor').value;
			const subSetorInicial = '" . ($subSetorSelecionado ?: '') . "';
			
			if (setorInicial) {
				carregarSubSetores(setorInicial, subSetorInicial);
			}
		});
		</script>
		";

		$html = '
		<div class="col-sm-'.$tamanho.' margin-bottom-5 campo-fit-content">
			<label>Sub-setor</label>
			<select name="sub-setor" id="sub-setor" class="form-control input-sm campo-fit-content">
				<option value="">Selecione um setor primeiro</option>
			</select>
		</div>';

		return $js . $html;
	}

    function layout_tipo_doc() {
		global $a_mod;

		cabecalho("Cadastro de Tipo de Documento");

		if (empty($_POST["id"])) {
			$campoStatus = combo("Status", "busca_banco", $_POST["busca_banco"]?? "", 2, [ "ativo" => "Ativo", "inativo" => "Inativo"]);
		}
		
        if($_POST["vencimento"] == '1') $_POST["vencimento"] = 'sim';
        if($_POST["vencimento"] == '0') $_POST["vencimento"] = 'nao';
		if (empty($_POST["vencimento"])) {
			$_POST["vencimento"] = "nao";
		}

        if($_POST["assinatura"] == '1') $_POST["assinatura"] = 'sim';
        if($_POST["assinatura"] == '0') $_POST["assinatura"] = 'nao';
		if (empty($_POST["assinatura"])) {
			$_POST["assinatura"] = "nao";
		}

		$sbsetor_documento = mysqli_fetch_all(query(
			"SELECT sbgr_nb_id,sbgr_nb_idgrup, sbgr_tx_nome, sbgr_tx_status FROM sbgrupos_documentos ORDER BY sbgr_tx_nome ASC"
		), MYSQLI_ASSOC);

		$campos = [
			campo("Nome*", "nome", $_POST["nome"], 4),
            combo_bd("!Setor*", "setor", $_POST["setor"], 2, "grupos_documentos"),
			criaSectionSubSetor($sbsetor_documento, $_POST["sub-setor"], 2),
			$campoStatus,
            combo("Vencimento", "vencimento", $_POST["vencimento"], 2, ["" => "", "sim" => "Sim", "nao" => "Não"]),
            combo("Assinatura", "assinatura", $_POST["assinatura"], 2, ["" => "", "sim" => "Sim", "nao" => "Não"]),
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
		
        //ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
        include "check_permission.php";
        // APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
        verificaPermissao('/cadastro_tipo_doc.php');
        
        cabecalho("Cadastro Tipo de Documento");

        $fields = [
			campo("Código", 		"busca_codigo", 	($_POST["busca_codigo"]?? ""), 	1, "", "maxlength='6'"),
			campo("Nome", 			"busca_nome_like", 		($_POST["busca_nome_like"]?? ""), 	3, "", "maxlength='65'"),
			combo_bd("!Setor", 		"busca_setor",		(!empty($_POST["busca_setor"])? $_POST["busca_setor"]: ""), 4, "grupos_documentos"),
			combo_bd("!Subsetor", "busca_subsetor", ($_POST["busca_subsetor"]?? ""), 2, "sbgrupos_documentos", "", "ORDER BY sbgr_tx_nome ASC"),
            combo("Status", 		"busca_status", 	($_POST["busca_status"]?? ""), 	2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]),
		];

		$buttons[] = botao("Buscar", "index");

		$canInsert = false;
		include_once "check_permission.php";
		if(function_exists('temPermissaoMenu')){
			$canInsert = temPermissaoMenu('/cadastro_tipo_doc.php');
		}
		if(is_int(stripos($_SESSION['user_tx_nivel'] ?? '', 'administrador')) || is_int(stripos($_SESSION['user_tx_nivel'] ?? '', 'super'))){
			$canInsert = true;
		}
		if($canInsert){
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
			"SUBSETOR" 		=> "sbgr_tx_nome",
            "STATUS" 	    => "tipo_tx_status",
            "CAMPOS"        => "qtd_campos",
        ];

        $camposBusca = [
            "busca_codigo" 		=> "tipo_nb_id",
            "busca_nome_like" 	=> "tipo_tx_nome",
            "busca_status" 		=> "tipo_tx_status",
            "busca_setor" 		=> "tipo_nb_grupo",
            "busca_subsetor" 	=> "sbgr_nb_id",
        ];

        $queryBase = 
            "SELECT t.tipo_nb_id, t.tipo_tx_nome, g.grup_tx_nome, s.sbgr_tx_nome, t.tipo_tx_status,
                   (SELECT COUNT(*) FROM camp_documento_modulo WHERE camp_nb_tipo_doc = t.tipo_nb_id AND camp_tx_status = 'ativo') as qtd_campos
             FROM tipos_documentos t
             LEFT JOIN grupos_documentos g ON g.grup_nb_id = t.tipo_nb_grupo
             LEFT JOIN sbgrupos_documentos s ON t.tipo_nb_sbgrupo = s.sbgr_nb_id"
        ;

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-th-list", "glyphicon glyphicon-remove search-remove"],
            ["cadastro_tipo_doc.php", "documentos/configurar_layout.php", "cadastro_tipo_doc.php"],
            ["modificarTipoDoc()", "index", "excluirTipoDoc()"],
            ["", "id", ""]
        );

        $actions["functions"][1] .= 
            "esconderInativar('glyphicon glyphicon-remove search-remove', 4);"
        ;

        $gridFields["actions"] = $actions["tags"];

        $jsFunctions =
            "const funcoesInternas = function(){
                // Identificar a coluna QTD (penúltima antes das ações) e a tabela
                $('#result tbody tr').each(function(){
                    var row = $(this);
                    var cellQtd = row.find('td').eq(5); // Índice 5 é o QTD (0-indexed)
                    var qtd = parseInt(cellQtd.text()) || 0;
                    
                    if(qtd > 0){
                        // Se houver campos, destaca o ícone de Doc Layout (segundo ícone)
                        var iconLayout = row.find('.glyphicon-th-list');
                        iconLayout.css('color', '#337ab7').attr('title', 'Layout Configurado (' + qtd + ' campos)');
                        
                        // Badge padrão Bootstrap para o contador de campos
                        if(iconLayout.parent().find('.badge-qtd').length == 0){
                            iconLayout.after('<span class=\"badge badge-qtd\" style=\"background-color:#337ab7; font-size:9px; padding: 1px 4px; margin-left:-8px; vertical-align:top; border:1px solid white;\">' + qtd + '</span>');
                        }
                    }
                    
                    // Esconde a coluna CAMPOS que usamos apenas para a lógica
                    cellQtd.hide();
                });
                // Esconde o cabeçalho da coluna CAMPOS
                $('#result thead th').eq(5).hide();
                // Adiciona cabeçalhos para as colunas de ação (remove antes pra evitar duplicata)
                var headerRow = $('#result thead tr');
                headerRow.find('.th-action-col').remove();
                headerRow.append('<th class=\"th-action-col table-col-head\" style=\"text-align:center;\">VISUALIZAR</th>');
                headerRow.append('<th class=\"th-action-col table-col-head\" style=\"text-align:center;\">DOC.LAYOUT</th>');
                headerRow.append('<th class=\"th-action-col table-col-head\" style=\"text-align:center;\">EXCLUIR</th>');

                ".implode(" ", $actions["functions"])."
            }"
        ;

        echo gridDinamico("tabelaTiposDocumentos", $gridFields, $camposBusca, $queryBase, $jsFunctions);
        rodape();
    }
