<?php
    /* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
	include "conecta.php";

    function buscar_funcionarios(){
        // Filtros
        $where = ["enti_tx_status = 'ativo'"];
        
        if(!empty($_POST['empresa'])){
            $where[] = "enti_nb_empresa = ".intval($_POST['empresa']);
        }
        if(!empty($_POST['setor'])){
            $where[] = "enti_setor_id = ".intval($_POST['setor']);
        }
        if(!empty($_POST['subsetor'])){
            $where[] = "enti_subSetor_id = ".intval($_POST['subsetor']);
        }
        if(!empty($_POST['cargo'])){
            $where[] = "enti_tx_tipoOperacao = ".intval($_POST['cargo']);
        }

        $sql = "SELECT enti_nb_id, enti_tx_nome, oper_tx_nome, empr_tx_nome 
                FROM entidade 
                JOIN empresa ON enti_nb_empresa = empr_nb_id
                LEFT JOIN operacao ON enti_tx_tipoOperacao = oper_nb_id
                WHERE " . implode(" AND ", $where) . " ORDER BY enti_tx_nome ASC";

        $res = query($sql);
        $funcionarios = [];
        while($row = mysqli_fetch_assoc($res)){
            $funcionarios[] = $row;
        }
        
        echo json_encode($funcionarios);
        exit;
    }

	function excluirGrupo(){
		remover("grupo_acesso",$_POST["id"]);
		index();
		exit;
	}

	function modificarGrupo(){
		global $a_mod;
		$a_mod = carregar("grupo_acesso", $_POST["id"]);
		layout_grupo();
		exit;
	}

	function cadastra_grupo() {
		$campos = [
			"grup_tx_nome" => $_POST["nome"],
			"grup_tx_status" => "ativo",
            "grup_tx_dataAtualiza" => date("Y-m-d H:i:s"),
            "grup_nb_userAtualiza" => $_SESSION["user_nb_id"]
		];

		if(!empty($_POST["id"])){
			atualizar("grupo_acesso", array_keys($campos), array_values($campos), $_POST["id"]);
            $idGrupo = $_POST["id"];
            // Remove vinculos anteriores para recriar
            query("DELETE FROM grupo_acesso_funcionarios WHERE grfu_nb_grupo = $idGrupo");
		}else{
			$campos["grup_tx_dataCadastro"] = date("Y-m-d H:i:s");
			$campos["grup_nb_userCadastro"] = $_SESSION["user_nb_id"];
			$idGrupo = inserir("grupo_acesso", array_keys($campos), array_values($campos));
            $idGrupo = $idGrupo[0]; // inserir retorna array
		}

        // Inserir funcionarios vinculados
        if(!empty($_POST['funcionarios'])){
            $funcionarios = explode(',', $_POST['funcionarios']);
            foreach($funcionarios as $idFunc){
                if(empty($idFunc)) continue;
                $camposVinculo = [
                    "grfu_nb_grupo" => $idGrupo,
                    "grfu_nb_funcionario" => intval($idFunc),
                    "grfu_tx_dataCadastro" => date("Y-m-d H:i:s"),
                    "grfu_nb_userCadastro" => $_SESSION["user_nb_id"]
                ];
                $r = inserir("grupo_acesso_funcionarios", array_keys($camposVinculo), array_values($camposVinculo));
                if(isset($r[0]) && $r[0] instanceof Exception){
                    set_status("Erro ao vincular funcionário: ".$r[0]->getMessage());
                }
            }
        }

		index();
		exit;
	}

	function layout_grupo() {
		global $a_mod;

		cabecalho("Cadastro de Grupo de Acesso");

        // CSS Styles
        echo "
        <style>
            .filter-row {
                margin-bottom: 15px;
            }
            .filter-row label {
                font-weight: normal;
                color: #666;
            }
            .btn-search {
                margin-top: 5px;
            }
            .results-container {
                margin-top: 20px;
                border: 1px solid #eee;
                border-radius: 4px;
                padding: 0;
                max-height: 400px;
                overflow-y: auto;
                background: #fff;
                display: none;
            }
            .results-table {
                margin-bottom: 0;
            }
            .results-table th {
                background-color: #f8f9fa;
                border-bottom: 2px solid #dee2e6;
                color: #495057;
            }
            .action-bar {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #eee;
                text-align: right;
            }
            .vinculados-section {
                margin-top: 30px;
            }
            .section-header {
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
                margin-bottom: 20px;
                margin-top: 30px;
                color: #333;
                font-size: 18px;
                font-weight: 500;
            }
            .search-box {
                background: #f9f9f9;
                padding: 15px;
                border-radius: 5px;
                border: 1px solid #eee;
                margin-bottom: 20px;
            }
        </style>
        ";

        $idGrupo = $a_mod["grup_nb_id"] ?? 0;
        
        // Carregar funcionários já vinculados se for edição
        $funcionariosVinculados = [];
        if($idGrupo > 0){
            $sql = "SELECT gf.grfu_nb_funcionario as enti_nb_id, f.enti_tx_nome, o.oper_tx_nome, e.empr_tx_nome 
                    FROM grupo_acesso_funcionarios gf
                    LEFT JOIN entidade f ON gf.grfu_nb_funcionario = f.enti_nb_id
                    LEFT JOIN empresa e ON f.enti_nb_empresa = e.empr_nb_id
                    LEFT JOIN operacao o ON f.enti_tx_tipoOperacao = o.oper_nb_id
                    WHERE gf.grfu_nb_grupo = $idGrupo";
            $res = query($sql);
            while($row = mysqli_fetch_assoc($res)){
                $funcionariosVinculados[] = $row;
            }
        }

		$campos = [
			campo("Nome do Grupo*", "nome", $a_mod["grup_tx_nome"]?? "", 6),
		];

        // Filtros para adicionar funcionarios
        $empresas = [];
        $r = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome");
        while($row = mysqli_fetch_assoc($r)) $empresas[$row['empr_nb_id']] = $row['empr_tx_nome'];

        $setores = [];
        $r = query("SELECT grup_nb_id, grup_tx_nome FROM grupos_documentos ORDER BY grup_tx_nome");
        while($row = mysqli_fetch_assoc($r)) $setores[$row['grup_nb_id']] = $row['grup_tx_nome'];

        $cargos = [];
        $r = query("SELECT oper_nb_id, oper_tx_nome FROM operacao WHERE oper_tx_status = 'ativo' ORDER BY oper_tx_nome");
        while($row = mysqli_fetch_assoc($r)) $cargos[$row['oper_nb_id']] = $row['oper_tx_nome'];

        // Carregar todos os subsetores para uso no JS
        $allSubsetores = [];
        $r = query("SELECT sbgr_nb_id, sbgr_tx_nome, sbgr_nb_idgrup FROM sbgrupos_documentos ORDER BY sbgr_tx_nome");
        while($row = mysqli_fetch_assoc($r)) {
            $allSubsetores[] = [
                'id' => $row['sbgr_nb_id'],
                'nome' => $row['sbgr_tx_nome'],
                'idGrupo' => $row['sbgr_nb_idgrup']
            ];
        }

		$botoes = [];
		
		echo abre_form("Dados do Grupo");
		echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"]?? "");

        // Box Agrupado (Nome do Grupo + Filtros)
        echo "<div class='search-box'>";
            echo "<div class='row'>";
                // Nome do Grupo
                echo campo("Nome do Grupo*", "nome", $a_mod["grup_tx_nome"]?? "", 2);
                
                // Filtros para busca de funcionários
                echo combo("Empresa", "filtro_empresa", "", 2, [""=>"Todas"]+$empresas);
                echo combo("Setor", "filtro_setor", "", 2, [""=>"Todos"]+$setores);
                echo "<div class='col-sm-2 margin-bottom-5'><label>Subsetor</label><select id='filtro_subsetor' class='form-control input-sm'><option value=''>Todos</option></select></div>";
                echo combo("Cargo", "filtro_cargo", "", 2, [""=>"Todos"]+$cargos);
            echo "</div>";

            // Botões Flex
            echo "<div class='row' style='margin-top: 15px;'>";
                echo "<div class='col-sm-12' style='display: flex; gap: 10px; align-items: center;'>";
                    echo "<button type='button' class='btn btn-primary' onclick='buscarFuncionarios()'>Pesquisar</button>";
                    echo botao("Gravar", "cadastra_grupo", "id", $_POST["id"]?? "", "gravar()", "Gravar", "btn btn-success");
                    echo criarBotaoVoltar();
                echo "</div>";
            echo "</div>";

        echo "</div>"; // End search-box
          

        // Tabela de Resultados da Busca
        echo "<div id='resultado_busca' class='results-container'>";
        echo "<table class='table table-striped table-hover results-table' id='table_busca'>";
        echo "<thead><tr><th width='40'><input type='checkbox' id='check_all_busca' onclick='toggleAllBusca(this)'></th><th>Nome</th><th>Empresa</th><th>Cargo</th></tr></thead>";
        echo "<tbody></tbody>";
        echo "</table>";
        echo "</div>"; // Fim results-container

        echo "<div class='row' id='btn_add_selecionados' style='display:none; margin-top: 10px; margin-bottom: 20px;'>";
            echo "<div class='col-sm-2'>";
                echo "<button type='button' class='btn btn-success' onclick='adicionarSelecionados()'> Adicionar Selecionados</button>";
            echo "</div>";
        echo "</div>";

        // Tabela de Funcionários Vinculados
        echo "<div class='search-box vinculados-section'>";
            echo "<div class='section-header' style='margin-top: 0; border-bottom: 1px solid #ddd; margin-bottom: 15px; padding-bottom: 10px;'>";
                echo "<span class='caption-subject font-dark bold' style='font-size: 16px;'>Funcionários Vinculados</span>";
            echo "</div>";
            
            // DEBUG TEMPORARIO
            // echo "<div class='alert alert-warning'>...</div>";
            
            echo "<table class='table table-striped table-hover' id='table_vinculados' style='background: #fff;'>";
            echo "<thead><tr><th>Nome</th><th>Empresa</th><th>Cargo</th><th width='100'>Ação</th></tr></thead>";
            echo "<tbody>";
            foreach($funcionariosVinculados as $func){
                echo "<tr id='vinculo_{$func['enti_nb_id']}'>";
                $nome = $func['enti_tx_nome'] ?: "Funcionário não encontrado (ID: {$func['enti_nb_id']})";
                echo "<td>{$nome}</td>";
                echo "<td>{$func['empr_tx_nome']}</td>";
                echo "<td>{$func['oper_tx_nome']}</td>";
                echo "<td><button type='button' class='btn btn-danger btn-xs' onclick='removerVinculo({$func['enti_nb_id']})'><i class='glyphicon glyphicon-trash'></i> </button></td>";
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";
        echo "</div>"; // Fim search-box

        // Input hidden para armazenar os IDs
        $idsVinculados = array_column($funcionariosVinculados, 'enti_nb_id');
        echo "<input type='hidden' name='funcionarios' id='funcionarios_ids' value='".implode(',', $idsVinculados)."'>";

		echo fecha_form($botoes);

        // Scripts JS
        ?>
        <script>
            var todosSubsetores = <?php echo json_encode($allSubsetores); ?>;
            var funcionariosEncontrados = {};

            // Carregar subsetores ao mudar setor
            document.getElementById('filtro_setor').addEventListener('change', function(){
                var setorId = this.value;
                var subsetorSelect = document.getElementById('filtro_subsetor');
                subsetorSelect.innerHTML = "<option value=''>Todos</option>";
                
                if(setorId){
                    var filtrados = todosSubsetores.filter(function(sub){
                        return sub.idGrupo == setorId;
                    });
                    filtrados.forEach(function(sub){
                        var opt = document.createElement('option');
                        opt.value = sub.id;
                        opt.innerHTML = sub.nome;
                        subsetorSelect.appendChild(opt);
                    });
                } else {
                     // Se não tiver setor selecionado, mostra todos
                    todosSubsetores.forEach(function(sub){
                        var opt = document.createElement('option');
                        opt.value = sub.id;
                        opt.innerHTML = sub.nome;
                        subsetorSelect.appendChild(opt);
                    });
                }
            });

            function buscarFuncionarios(){
                var formData = new FormData();
                formData.append('acao', 'buscar_funcionarios');
                formData.append('empresa', document.getElementsByName('filtro_empresa')[0].value);
                formData.append('setor', document.getElementsByName('filtro_setor')[0].value);
                formData.append('subsetor', document.getElementById('filtro_subsetor').value);
                formData.append('cargo', document.getElementsByName('filtro_cargo')[0].value);

                fetch('cadastro_grupo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    var tbody = document.querySelector('#table_busca tbody');
                    tbody.innerHTML = '';
                    funcionariosEncontrados = {}; // Limpa cache anterior
                    
                    if(data.length > 0){
                        data.forEach(func => {
                            funcionariosEncontrados[func.enti_nb_id] = func; // Armazena no mapa global
                            
                            // Verifica se já está vinculado
                            if(!document.getElementById('vinculo_' + func.enti_nb_id)){
                                var tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td><input type='checkbox' class='check_busca' value='${func.enti_nb_id}'></td>
                                    <td>${func.enti_tx_nome}</td>
                                    <td>${func.empr_tx_nome}</td>
                                    <td>${func.oper_tx_nome || ''}</td>
                                `;
                                tbody.appendChild(tr);
                            }
                        });
                        document.getElementById('resultado_busca').style.display = 'block';
                        document.getElementById('btn_add_selecionados').style.display = 'block';
                    } else {
                        alert('Nenhum funcionário encontrado com os filtros selecionados.');
                        document.getElementById('resultado_busca').style.display = 'none';
                        document.getElementById('btn_add_selecionados').style.display = 'none';
                    }
                })
                .catch(error => console.error('Erro:', error));
            }

            function toggleAllBusca(source) {
                var checkboxes = document.querySelectorAll('.check_busca');
                for(var i=0;i<checkboxes.length;i++) {
                    checkboxes[i].checked = source.checked;
                }
            }

            function getIdsVinculados() {
                var val = document.getElementById('funcionarios_ids').value;
                return val ? val.split(',') : [];
            }

            function adicionarSelecionados(){
                var checkboxes = document.querySelectorAll('.check_busca:checked');
                if(checkboxes.length === 0){
                    alert('Selecione pelo menos um funcionário.');
                    return;
                }

                var tbody = document.querySelector('#table_vinculados tbody');
                var currentIds = getIdsVinculados();
                var added = false;

                checkboxes.forEach(chk => {
                    var id = chk.value;
                    var func = funcionariosEncontrados[id];
                    
                    if(func && currentIds.indexOf(String(id)) === -1){
                        var tr = document.createElement('tr');
                        tr.id = 'vinculo_' + id;
                        tr.innerHTML = `
                            <td>${func.enti_tx_nome}</td>
                            <td>${func.empr_tx_nome}</td>
                            <td>${func.oper_tx_nome || ''}</td>
                            <td><button type='button' class='btn btn-danger btn-xs' onclick='removerVinculo(${id})'><i class='glyphicon glyphicon-trash'></i> </button></td>
                        `;
                        tbody.appendChild(tr);
                        currentIds.push(String(id));
                        added = true;
                    }
                });

                if(added) {
                    document.getElementById('funcionarios_ids').value = currentIds.join(',');
                }
                
                // Limpa busca
                document.getElementById('resultado_busca').style.display = 'none';
                document.getElementById('btn_add_selecionados').style.display = 'none';
                document.querySelector('#table_busca tbody').innerHTML = '';
            }

            function removerVinculo(id){
                if(confirm('Remover funcionário do grupo?')){
                    var tr = document.getElementById('vinculo_' + id);
                    if(tr) tr.remove();
                    
                    var currentIds = getIdsVinculados();
                    var index = currentIds.indexOf(String(id));
                    if (index > -1) {
                        currentIds.splice(index, 1);
                        document.getElementById('funcionarios_ids').value = currentIds.join(',');
                    }
                }
            }

            function gravar(){
                var form = document.querySelector('form[name="contex_form"]');
                if(form){
                    form.submit();
                } else {
                    document.forms[0].submit();
                }
            }
        </script>
        <?php

		rodape();
	}

    function index() {
        // Handle AJAX request for employees
        if(isset($_POST['acao']) && $_POST['acao'] == 'buscar_funcionarios'){
            buscar_funcionarios();
        }

		global $CONTEX;
		
		//ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
        include "check_permission.php";
        // APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
        // verificaPermissao('/cadastro_grupo.php'); // Comentado para evitar bloqueio imediato se não tiver permissão configurada
		cabecalho("Cadastro de Grupos de Acesso");

		if(!isset($_POST["busca_status"])){
			$_POST["busca_status"] = "ativo";
		}

		$fields = [
			campo("Código", 		"busca_codigo", 	($_POST["busca_codigo"]?? ""), 	1, "", "maxlength='6'"),
			campo("Nome", 			"busca_nome_like", 		($_POST["busca_nome_like"]?? ""), 	6, "", "maxlength='65'"),
			combo("Status", 		"busca_status", 	($_POST["busca_status"]?? ""), 	2, ["" => "Todos", "ativo" => "Ativo", "inativo" => "Inativo"]),
		];
		
		$buttons[] = botao("Buscar", "index");
        $buttons[] = botao("Inserir", "layout_grupo","","","","","btn btn-success");

		echo abre_form();
		echo linha_form($fields);
		echo fecha_form($buttons);

        // Grid dinâmico
        $gridFields = [
            "CÓDIGO" 		=> "grup_nb_id",
            "NOME" 			=> "grup_tx_nome",
            "STATUS" 		=> "grup_tx_status"
        ];

        $camposBusca = [
            "busca_codigo" 		=> "grup_nb_id",
            "busca_nome_like" 	=> "grup_tx_nome",
            "busca_status" 		=> "grup_tx_status",
        ];

        $queryBase = "SELECT ".implode(", ", array_values($gridFields))." FROM grupo_acesso";

        $actions = criarIconesGrid(
            ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
            ["cadastro_grupo.php", "cadastro_grupo.php"],
            ["modificarGrupo()", "excluirGrupo()"]
        );

        $actions["functions"][1] .= "esconderInativar('glyphicon glyphicon-remove search-remove', 9);";

        $gridFields["actions"] = $actions["tags"];

        $jsFunctions = "const funcoesInternas = function(){ ".implode(" ", $actions["functions"])." }";
        
        echo gridDinamico("tabelaGrupos", $gridFields, $camposBusca, $queryBase, $jsFunctions);

		rodape();
	}
?>