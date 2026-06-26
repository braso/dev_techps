<?php


        ini_set("display_errors", 1);
        error_reporting(E_ALL);

        header("Expires: 01 Jan 2001 00:00:00 GMT");
        header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
        header("Pragma: no-cache"); // HTTP 1.0.



include_once "check_permission.php";
include_once "load_env.php";
include_once "utils/utils.php";
include_once "conecta.php";

function ensureFeriadoFuncionarioSchema(){
    $dbRow = mysqli_fetch_assoc(query("SELECT DATABASE() AS db"));
    $db = strval($dbRow["db"] ?? "");
    if($db === ""){
        return;
    }

    $exists = mysqli_fetch_assoc(query(
        "SELECT 1 AS ok
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = 'feriado_funcionario'
        LIMIT 1",
        "s",
        [$db]
    ));

    if(empty($exists)){
        query(
            "CREATE TABLE IF NOT EXISTS feriado_funcionario (
                fefi_nb_id INT AUTO_INCREMENT PRIMARY KEY,
                fefi_tx_nome VARCHAR(255) NOT NULL,
                fefi_tx_data DATE NOT NULL,
                fefi_nb_entidade INT NOT NULL,
                fefi_tx_status VARCHAR(10) DEFAULT 'ativo',
                fefi_nb_userCadastro INT DEFAULT NULL,
                fefi_tx_dataCadastro DATETIME DEFAULT NULL,
                UNIQUE KEY uk_feriado_funcionario_data_entidade (fefi_tx_data, fefi_nb_entidade)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;"
        );
    }
}

function index(){
    ensureFeriadoFuncionarioSchema();
    verificaPermissao('/cadastro_feriado_cargo.php');
    cabecalho("Cadastro de Feriado por Cargo");

    // --- FORMULÁRIO DE BUSCA ---
    if(!isset($_POST["busca_status"])){
        $_POST["busca_status"] = "ativo";
    }

    $filtros = [];
    if(!empty($_POST["busca_nome"])){
        $filtros[] = "f.fefi_tx_nome LIKE '%".mysqli_real_escape_string($GLOBALS['conn'], $_POST["busca_nome"])."%'";
    }
    if(!empty($_POST["busca_data"])){
        $filtros[] = "f.fefi_tx_data = '".mysqli_real_escape_string($GLOBALS['conn'], $_POST["busca_data"])."'";
    }
    if(!empty($_POST["busca_empresa"])){
        $filtros[] = "e.enti_nb_empresa = ".intval($_POST["busca_empresa"]);
    }
    if(!empty($_POST["busca_cargo"])){
        $filtros[] = "e.enti_tx_tipoOperacao = ".intval($_POST["busca_cargo"]);
    }
    if(!empty($_POST["busca_status"])){
        $filtros[] = "f.fefi_tx_status = '".mysqli_real_escape_string($GLOBALS['conn'], $_POST["busca_status"])."'";
    }

    $where = !empty($filtros) ? " AND " . implode(" AND ", $filtros) : "";

    $campos = [
        campo("Nome",     "busca_nome",     (empty($_POST["busca_nome"])? "": $_POST["busca_nome"]),     4, "", "maxlength='65'"),
        campo_data("Data",       "busca_data",     (empty($_POST["busca_data"])? "": $_POST["busca_data"]),     2),
        combo_net("Empresa", "busca_empresa", (empty($_POST["busca_empresa"])? "": $_POST["busca_empresa"]), 3, "empresa", "", " AND empr_tx_status = 'ativo'"),
        combo_bd("Cargo",     "busca_cargo",    (empty($_POST["busca_cargo"])? "": $_POST["busca_cargo"]),    3, "operacao"),
        combo("Status",     "busca_status",   (empty($_POST["busca_status"])? "": $_POST["busca_status"]),   2, ["ativo" => "Ativo", "inativo" => "Inativo"])
    ];

    $botoes = [
        botao("Buscar", "index"),
        botao("Limpar Filtro", "limparFiltrosFeriadoCargo"),
        botao("Inserir", "layout_feriado_cargo", "", "", "", "", "btn btn-success")
    ];

    echo abre_form();
    echo linha_form($campos);
    echo fecha_form($botoes);

    // --- GRID AGRUPADA ---
    $grupos = [];
    $sql = "SELECT
                f.fefi_tx_nome,
                f.fefi_tx_data,
                o.oper_tx_nome AS cargo,
                e.enti_tx_tipoOperacao AS cargo_id,
                emp.empr_tx_nome AS empresa,
                e.enti_nb_empresa AS empresa_id,
                para.para_tx_nome AS parametro,
                e.enti_nb_parametro AS parametro_id,
                GROUP_CONCAT(DISTINCT e.enti_tx_nome ORDER BY e.enti_tx_nome ASC SEPARATOR '||') AS funcionarios,
                COUNT(*) AS total
            FROM feriado_funcionario f
            LEFT JOIN entidade e ON e.enti_nb_id = f.fefi_nb_entidade
            LEFT JOIN operacao o ON o.oper_nb_id = e.enti_tx_tipoOperacao
            LEFT JOIN empresa emp ON emp.empr_nb_id = e.enti_nb_empresa
            LEFT JOIN parametro para ON para.para_nb_id = e.enti_nb_parametro
            WHERE f.fefi_tx_status = 'ativo'" . $where . "
            GROUP BY f.fefi_tx_nome, f.fefi_tx_data, o.oper_tx_nome, e.enti_tx_tipoOperacao, emp.empr_tx_nome, e.enti_nb_empresa, para.para_tx_nome, e.enti_nb_parametro
            ORDER BY f.fefi_tx_data DESC, f.fefi_tx_nome ASC";
    $res = query($sql);
    if($res !== false && $res !== true){
        $grupos = mysqli_fetch_all($res, MYSQLI_ASSOC);
    }

    echo "<div class='row'><div class='col-sm-12 margin-bottom-5 campo-fit-content'>";
    echo "<table class='table table-striped table-bordered'>";
    echo "<thead><tr>"
        ."<th>Nome</th>"
        ."<th>Data</th>"
        ."<th>Empresa</th>"
        ."<th>Parâmetro</th>"
        ."<th>Cargo</th>"
        ."<th>Funcionários</th>"
        ."<th style='text-align:center; width:120px'>Ações</th>"
        ."</tr></thead><tbody>";
    if(empty($grupos)){
        echo "<tr><td colspan='7' style='text-align:center'>Nenhum registro encontrado</td></tr>";
    }else{
        foreach($grupos as $g){
            $dataFmt = date("d/m/Y", strtotime($g["fefi_tx_data"]));
            $listaNomes = explode("||", $g["funcionarios"] ?? "");
            $primeiro = $listaNomes[0] ?? "-";
            $total = intval($g["total"]);
            $nomesEscapados = htmlspecialchars($g["funcionarios"] ?? "");
            $funcionariosLabel = $primeiro;
            if($total > 1){
                $funcionariosLabel .= " <a href='javascript:void(0)' class='ver-funcionarios' data-nomes='{$nomesEscapados}' style='color:#35A3BC; font-weight:bold; text-decoration:underline; cursor:pointer;'>+ " . ($total - 1) . " outro(s)</a>";
            }

            echo "<tr>"
                ."<td>".htmlspecialchars($g["fefi_tx_nome"])."</td>"
                ."<td>".$dataFmt."</td>"
                ."<td>".htmlspecialchars($g["empresa"] ?: "-")."</td>"
                ."<td>".htmlspecialchars($g["parametro"] ?: "-")."</td>"
                ."<td>".htmlspecialchars($g["cargo"] ?: "-")."</td>"
                ."<td>".$funcionariosLabel."</td>"
                ."<td style='text-align:center'>"
                    ."<form method='post' style='display:inline'>"
                        .campo_hidden("nome", $g["fefi_tx_nome"])
                        .campo_hidden("data", $g["fefi_tx_data"])
                        .campo_hidden("cargo", $g["cargo_id"] ?? 0)
                        .campo_hidden("empresa", $g["empresa_id"] ?? 0)
                        .campo_hidden("parametro", $g["parametro_id"] ?? 0)
                        ."<button type='submit' name='acao' value='editarFeriadoCargo' class='btn btn-xs btn-primary' style='margin-right:4px'><i class='glyphicon glyphicon-search'></i></button>"
                    ."</form>"
                    ."<form method='post' style='display:inline'>"
                        .campo_hidden("nome", $g["fefi_tx_nome"])
                        .campo_hidden("data", $g["fefi_tx_data"])
                        .campo_hidden("cargo", $g["cargo_id"] ?? 0)
                        .campo_hidden("empresa", $g["empresa_id"] ?? 0)
                        .campo_hidden("parametro", $g["parametro_id"] ?? 0)
                        ."<button type='submit' name='acao' value='excluirGrupoFeriado' class='btn btn-xs btn-danger' onclick='return confirm(\"Deseja excluir este feriado de TODOS os funcionários deste cargo?\");'><i class='glyphicon glyphicon-remove'></i></button>"
                    ."</form>"
                ."</td>"
                ."</tr>";
        }
    }
    echo "</tbody></table>";
    echo "</div></div>";

    // --- MODAL DE FUNCIONÁRIOS ---
    echo "
    <div id='modalFuncionarios' style='display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);'>
        <div style='background:#fff; margin:10% auto; padding:20px; border-radius:8px; width:400px; max-height:70%; overflow:auto; box-shadow:0 4px 20px rgba(0,0,0,0.3);'>
            <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;'>
                <h4 style='margin:0; font-weight:bold;'>Funcionários vinculados</h4>
                <button onclick='document.getElementById(\"modalFuncionarios\").style.display=\"none\"' style='background:none; border:none; font-size:20px; cursor:pointer;'>&times;</button>
            </div>
            <div id='listaFuncionariosModal' style='font-size:14px;'></div>
        </div>
    </div>
    <script>
    (function(){
        var modal = document.getElementById('modalFuncionarios');
        var lista = document.getElementById('listaFuncionariosModal');
        var links = document.querySelectorAll('.ver-funcionarios');
        links.forEach(function(link){
            link.addEventListener('click', function(e){
                e.preventDefault();
                var nomes = this.getAttribute('data-nomes').split('||');
                var html = '<ul style=\"padding-left:20px; margin:0;\">';
                nomes.forEach(function(nome){
                    html += '<li style=\"margin-bottom:6px;\">' + nome + '</li>';
                });
                html += '</ul>';
                lista.innerHTML = html;
                modal.style.display = 'block';
            });
        });
        modal.addEventListener('click', function(e){
            if(e.target === modal) modal.style.display = 'none';
        });
    })();
    </script>";

    rodape();
}

function limparFiltrosFeriadoCargo(){
    unset(
        $_POST["busca_nome"],
        $_POST["busca_data"],
        $_POST["busca_empresa"],
        $_POST["busca_cargo"],
        $_POST["busca_status"]
    );
    index();
    exit;
}

function layout_feriado_cargo(){
    ensureFeriadoFuncionarioSchema();
    global $a_mod;

    $isEdicao = !empty($_POST["nome"]) && !empty($_POST["data"]);
    $titulo = $isEdicao ? "Editar Feriado por Cargo" : "Cadastro de Feriado por Cargo";

    cabecalho($titulo);

    $nome = $_POST["nome"] ?? "";
    $data = $_POST["data"] ?? "";
    $empresa = $_POST["empresa"] ?? "";
    $parametro = $_POST["parametro"] ?? "";
    $cargo = $_POST["cargo"] ?? "";

    // Se está editando, carrega os funcionários já vinculados
    $selecionadosIds = [];
    if($isEdicao && (!empty($cargo) || !empty($parametro))){
        $resSel = query(
            "SELECT fefi_nb_entidade FROM feriado_funcionario
             WHERE fefi_tx_status = 'ativo'
               AND fefi_tx_nome = ?
               AND fefi_tx_data = ?",
            "ss",
            [$nome, $data]
        );
        if($resSel !== false && $resSel !== true){
            while($r = mysqli_fetch_assoc($resSel)){
                $selecionadosIds[] = intval($r["fefi_nb_entidade"]);
            }
        }
    }

    $funcionariosDisponiveis = [];
    if(!empty($cargo) || !empty($parametro)){
        $whereFunc = "enti_tx_status = 'ativo'";
        if(!empty($empresa)){
            $whereFunc .= " AND enti_nb_empresa = ".intval($empresa);
        }
        if(!empty($parametro)){
            $whereFunc .= " AND enti_nb_parametro = ".intval($parametro);
        }
        if(!empty($cargo)){
            $whereFunc .= " AND enti_tx_tipoOperacao = ".intval($cargo);
        }
        $resFunc = query(
            "SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula
             FROM entidade
             WHERE {$whereFunc}
             ORDER BY enti_tx_nome ASC"
        );
        if($resFunc !== false && $resFunc !== true){
            while($r = mysqli_fetch_assoc($resFunc)){
                $funcionariosDisponiveis[] = $r;
            }
        }
    }

    $extraNome = "maxlength='65' required";
    $extraData = "required";
    $extraEmpresa = "onchange='this.form.submit()'";
    $extraParametro = "onchange='this.form.submit()'";
    $extraCargo = "onchange='this.form.submit()'";

    $campos = [];
    if($isEdicao){
        $extraNome .= " readonly";
        $extraData .= " readonly";
        // Busca nome do cargo e empresa para exibir como campo readonly
        $cargoNome = "";
        $empresaNome = "";
        if(!empty($cargo)){
            $resCargo = query("SELECT oper_tx_nome FROM operacao WHERE oper_nb_id = ".intval($cargo)." LIMIT 1");
            if($resCargo !== false && $resCargo !== true){
                $rCargo = mysqli_fetch_assoc($resCargo);
                $cargoNome = $rCargo["oper_tx_nome"] ?? "";
            }
        }
        if(!empty($empresa)){
            $resEmp = query("SELECT empr_tx_nome FROM empresa WHERE empr_nb_id = ".intval($empresa)." LIMIT 1");
            if($resEmp !== false && $resEmp !== true){
                $rEmp = mysqli_fetch_assoc($resEmp);
                $empresaNome = $rEmp["empr_tx_nome"] ?? "";
            }
        }
        $campos = [
            campo("Nome*", "nome", $nome, 3, "", $extraNome),
            campo_data("Data*", "data", $data, 2, "", $extraData),
            campo("Empresa", "empresa_view", $empresaNome, 3, "", "readonly"),
            campo("Cargo", "cargo_view", $cargoNome, 3, "", "readonly"),
        ];
    }else{
        // Select customizado de empresas
        $optionsEmpresa = "<option value=''>Todas</option>";
        $resEmpresas = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome ASC");
        if($resEmpresas !== false && $resEmpresas !== true){
            while($r = mysqli_fetch_assoc($resEmpresas)){
                $selected = ($empresa == $r["empr_nb_id"]) ? " selected" : "";
                $optionsEmpresa .= "<option value='" . intval($r["empr_nb_id"]) . "'" . $selected . ">" . htmlspecialchars($r["empr_tx_nome"]) . "</option>";
            }
        }

        // Select customizado de parâmetros com contagem de funcionários ativos (filtrado por empresa)
        $sqlParametros = "
            SELECT p.para_nb_id, p.para_tx_nome, COUNT(e.enti_nb_id) AS total
            FROM parametro p
            LEFT JOIN entidade e ON e.enti_nb_parametro = p.para_nb_id AND e.enti_tx_status = 'ativo'";
        if(!empty($empresa)){
            $sqlParametros .= " AND e.enti_nb_empresa = ".intval($empresa);
        }
        $sqlParametros .= "
            WHERE p.para_tx_status = 'ativo'
            GROUP BY p.para_nb_id, p.para_tx_nome
            ORDER BY p.para_tx_nome ASC";
        $parametrosContagem = query($sqlParametros);
        $optionsParametro = "<option value=''>Selecione</option>";
        if($parametrosContagem !== false && $parametrosContagem !== true){
            while($r = mysqli_fetch_assoc($parametrosContagem)){
                $selected = ($parametro == $r["para_nb_id"]) ? " selected" : "";
                $label = htmlspecialchars($r["para_tx_nome"]) . " (" . intval($r["total"]) . " pessoas)";
                $optionsParametro .= "<option value='" . intval($r["para_nb_id"]) . "'" . $selected . ">" . $label . "</option>";
            }
        }

        // Select customizado com contagem de funcionários ativos por cargo (filtrado por empresa)
        $sqlCargos = "
            SELECT o.oper_nb_id, o.oper_tx_nome, COUNT(e.enti_nb_id) AS total
            FROM operacao o
            LEFT JOIN entidade e ON e.enti_tx_tipoOperacao = o.oper_nb_id AND e.enti_tx_status = 'ativo'";
        if(!empty($empresa)){
            $sqlCargos .= " AND e.enti_nb_empresa = ".intval($empresa);
        }
        $sqlCargos .= "
            WHERE o.oper_tx_status = 'ativo'
            GROUP BY o.oper_nb_id, o.oper_tx_nome
            ORDER BY o.oper_tx_nome ASC";
        $cargosContagem = query($sqlCargos);
        $optionsCargo = "<option value=''>Selecione</option>";
        if($cargosContagem !== false && $cargosContagem !== true){
            while($r = mysqli_fetch_assoc($cargosContagem)){
                $selected = ($cargo == $r["oper_nb_id"]) ? " selected" : "";
                $label = htmlspecialchars($r["oper_tx_nome"]) . " (" . intval($r["total"]) . " pessoas)";
                $optionsCargo .= "<option value='" . intval($r["oper_nb_id"]) . "'" . $selected . ">" . $label . "</option>";
            }
        }
        $campos = [
            campo("Nome*", "nome", $nome, 3, "", $extraNome),
            campo_data("Data*", "data", $data, 2, "", $extraData),
            "<div class='col-sm-3 margin-bottom-5 campo-fit-content'>
                <label>Empresa</label>
                <select name='empresa' id='empresa' class='form-control input-sm campo-fit-content' onchange='this.form.submit()'>
                    " . $optionsEmpresa . "
                </select>
            </div>",
            "<div class='col-sm-3 margin-bottom-5 campo-fit-content'>
                <label>Parâmetro</label>
                <select name='parametro' id='parametro' class='form-control input-sm campo-fit-content' onchange='this.form.submit()'>
                    " . $optionsParametro . "
                </select>
            </div>",
            "<div class='col-sm-3 margin-bottom-5 campo-fit-content'>
                <label>Cargo</label>
                <select name='cargo' id='cargo' class='form-control input-sm campo-fit-content' onchange='this.form.submit()'>
                    " . $optionsCargo . "
                </select>
            </div>"
        ];
    }

    echo abre_form($titulo);
    echo campo_hidden("HTTP_REFERER", $_POST["HTTP_REFERER"] ?? ($_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]."/cadastro_feriado_cargo.php"));
    if($isEdicao){
        echo campo_hidden("acao", "salvarFeriadoCargo");
        echo campo_hidden("modo", "editar");
        echo campo_hidden("empresa", $empresa);
        echo campo_hidden("cargo", $cargo);
    } else {
        echo campo_hidden("acao", "layout_feriado_cargo"); // recarrega form quando filtros mudam
    }
    echo linha_form($campos);

    // Dual list com tabela (mais robusto que grid Bootstrap)
    if(!empty($cargo) || !empty($parametro)){
        $totalDisponiveis = 0;
        $totalSelecionados = 0;
        foreach($funcionariosDisponiveis as $f){
            if(in_array(intval($f["enti_nb_id"]), $selecionadosIds)){ $totalSelecionados++; } else { $totalDisponiveis++; }
        }

        $dualListTitle = "Funcionários";
        if(!empty($parametro)){
            $dualListTitle = "Funcionários do parâmetro";
        }elseif(!empty($cargo)){
            $dualListTitle = "Funcionários do cargo";
        }

        echo "<style>
            .dual-card{border:1px solid #e3e8ee; border-radius:8px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.06); overflow:hidden;}
            .dual-card-header{padding:10px 14px; font-weight:bold; font-size:13px; color:#fff; display:flex; justify-content:space-between; align-items:center;}
            .dual-card-header.disp{background:#5b9bd5;}
            .dual-card-header.sel{background:#35A3BC;}
            .dual-card-header .badge{background:rgba(255,255,255,0.25); border-radius:10px; padding:2px 9px; font-size:12px;}
            .dual-card-body{padding:10px;}
            .dual-search{margin-bottom:8px;}
            .dual-list{height:280px; width:100%; border:1px solid #e3e8ee; border-radius:6px; box-shadow:none;}
            .dual-list option{padding:5px 8px; border-bottom:1px solid #f2f4f7;}
            .dual-btns .btn{display:block; width:46px; margin:0 auto 10px auto; font-weight:bold;}
            .dual-title{font-weight:bold; font-size:16px; margin-bottom:14px; color:#3a4248; border-left:4px solid #35A3BC; padding-left:10px;}
        </style>";

        echo "<div class='row'><div class='col-md-12' style='margin-top:20px'>"
            ."<div class='portlet light' style='padding:16px'>"
                ."<div class='dual-title'><i class='glyphicon glyphicon-user' style='margin-right:6px'></i>".$dualListTitle."</div>"
                ."<table style='width:100%; border-collapse:collapse;'>"
                    ."<tr>"
                        ."<td style='width:42%; vertical-align:top; padding:5px;'>"
                            ."<div class='dual-card'>"
                                ."<div class='dual-card-header disp'><span>Disponíveis</span><span class='badge' id='countPool'>".$totalDisponiveis."</span></div>"
                                ."<div class='dual-card-body'>"
                                    ."<input type='text' id='searchPool' class='form-control dual-search' placeholder='Buscar...'>"
                                    ."<select id='funcPool' multiple size='12' class='form-control dual-list'>";
        foreach($funcionariosDisponiveis as $f){
            $isSelecionado = in_array(intval($f["enti_nb_id"]), $selecionadosIds);
            if(!$isSelecionado){
                echo "<option value='".intval($f["enti_nb_id"])."'>[".htmlspecialchars($f["enti_tx_matricula"])."] ".htmlspecialchars($f["enti_tx_nome"])."</option>";
            }
        }
        echo                     "</select>"
                                ."</div>"
                            ."</div>"
                        ."</td>"
                        ."<td style='width:16%; vertical-align:middle; text-align:center; padding:5px;' class='dual-btns'>"
                            ."<button type='button' id='toSelected' class='btn btn-info' title='Adicionar selecionado'>&gt;</button>"
                            ."<button type='button' id='toPool' class='btn btn-default' title='Remover selecionado'>&lt;</button>"
                            ."<button type='button' id='toSelectedAll' class='btn btn-info' title='Adicionar todos'>&gt;&gt;</button>"
                            ."<button type='button' id='toPoolAll' class='btn btn-default' title='Remover todos'>&lt;&lt;</button>"
                        ."</td>"
                        ."<td style='width:42%; vertical-align:top; padding:5px;'>"
                            ."<div class='dual-card'>"
                                ."<div class='dual-card-header sel'><span>Selecionados</span><span class='badge' id='countSel'>".$totalSelecionados."</span></div>"
                                ."<div class='dual-card-body'>"
                                    ."<input type='text' id='searchSel' class='form-control dual-search' placeholder='Buscar...'>"
                                    ."<select id='funcSelected' name='funcionarios[]' multiple size='12' class='form-control dual-list'>";
        foreach($funcionariosDisponiveis as $f){
            $isSelecionado = in_array(intval($f["enti_nb_id"]), $selecionadosIds);
            if($isSelecionado){
                echo "<option value='".intval($f["enti_nb_id"])."' selected>[".htmlspecialchars($f["enti_tx_matricula"])."] ".htmlspecialchars($f["enti_tx_nome"])."</option>";
            }
        }
        echo                     "</select>"
                                ."</div>"
                            ."</div>"
                        ."</td>"
                    ."</tr>"
                ."</table>"
            ."</div>"
        ."</div></div>";

        echo "<script>(function(){
            var pool=document.getElementById('funcPool');
            var sel=document.getElementById('funcSelected');
            var countPool=document.getElementById('countPool');
            var countSel=document.getElementById('countSel');
            function atualizaContadores(){
                countPool.textContent = pool.options.length;
                countSel.textContent = sel.options.length;
            }
            function move(from,to){
                var opts=from.options;
                for(var i=opts.length-1;i>=0;i--){
                    if(opts[i].selected){
                        opts[i].selected=false;
                        to.appendChild(opts[i]);
                        if(to===sel){opts[i].selected=true;} else {opts[i].selected=false;}
                    }
                }
                atualizaContadores();
            }
            function moveAll(from,to){
                while(from.options && from.options.length>0){
                    var opt = from.options[0];
                    opt.selected = false;
                    to.appendChild(opt);
                }
                var optsTo = to.options;
                for(var j=0;j<optsTo.length;j++){
                    if(to===sel){optsTo[j].selected=true;} else {optsTo[j].selected=false;}
                }
                atualizaContadores();
            }
            document.getElementById('toSelected').onclick=function(){move(pool,sel);};
            document.getElementById('toPool').onclick=function(){move(sel,pool);};
            document.getElementById('toSelectedAll').onclick=function(){moveAll(pool,sel);};
            document.getElementById('toPoolAll').onclick=function(){moveAll(sel,pool);};

            // Filtros de busca
            function filtra(input, select){
                input.addEventListener('keyup', function(){
                    var termo = this.value.toLowerCase();
                    var opts = select.options;
                    for(var i=0;i<opts.length;i++){
                        var txt = opts[i].text.toLowerCase();
                        opts[i].style.display = (txt.indexOf(termo) > -1) ? '' : 'none';
                    }
                });
            }
            filtra(document.getElementById('searchPool'), pool);
            filtra(document.getElementById('searchSel'), sel);

            // Duplo clique move o item
            pool.addEventListener('dblclick', function(){ move(pool,sel); });
            sel.addEventListener('dblclick', function(){ move(sel,pool); });

            if(document.contex_form){
                document.contex_form.addEventListener('submit',function(){
                    var opts=sel.options;
                    for(var i=0;i<opts.length;i++){ opts[i].selected=true; }
                });
            }
        })();</script>";
    }

    $botoes = [
        botao($isEdicao ? "Salvar Alterações" : "Gravar", "salvarFeriadoCargo", "", "", "", "", "btn btn-success"),
        botao("Voltar", "voltarFeriadoCargo", "", "", "formnovalidate", "", "btn btn-default")
    ];
    echo fecha_form($botoes);
    rodape();
}

function salvarFeriadoCargo(){
    ensureFeriadoFuncionarioSchema();
    $isEdicao = ($_POST["modo"] ?? "") === "editar";

    $camposObrig = [
        "nome" => "Nome",
        "data" => "Data",
    ];

    $errorMsg = conferirCamposObrig($camposObrig, $_POST);
    if(!empty($errorMsg)){
        set_status("ERRO: ".$errorMsg);
        layout_feriado_cargo();
        exit;
    }

    $funcionarios = $_POST["funcionarios"] ?? [];
    if(empty($funcionarios) || !is_array($funcionarios)){
        set_status("ERRO: Selecione ao menos um funcionário.");
        layout_feriado_cargo();
        exit;
    }

    $nome = $_POST["nome"];
    $data = $_POST["data"];
    $empresa = $_POST["empresa"] ?? "";
    $parametro = $_POST["parametro"] ?? "";
    $cargo = $_POST["cargo"] ?? "";

    $inseridos = 0;
    $mantidos = 0;

    if($isEdicao){
        // Inativa os que não estão mais selecionados
        $idsSelecionados = array_map('intval', $funcionarios);
        $idsSelecionados = array_filter($idsSelecionados);
        if(!empty($idsSelecionados)){
            $inIds = implode(",", $idsSelecionados);
            query(
                "UPDATE feriado_funcionario SET fefi_tx_status = 'inativo'
                 WHERE fefi_tx_nome = ? AND fefi_tx_data = ? AND fefi_nb_entidade NOT IN ({$inIds})",
                "ss",
                [$nome, $data]
            );
        }else{
            query(
                "UPDATE feriado_funcionario SET fefi_tx_status = 'inativo'
                 WHERE fefi_tx_nome = ? AND fefi_tx_data = ?",
                "ss",
                [$nome, $data]
            );
        }
    }

    foreach($funcionarios as $funcId){
        $funcId = intval($funcId);
        if($funcId <= 0) continue;

        $resExiste = query(
            "SELECT fefi_nb_id, fefi_tx_status FROM feriado_funcionario
             WHERE fefi_tx_nome = ?
               AND fefi_tx_data = ?
               AND fefi_nb_entidade = ?
             LIMIT 1;",
            "ssi",
            [$nome, $data, $funcId]
        );
        $existe = null;
        if($resExiste !== false && $resExiste !== true){
            $existe = mysqli_fetch_assoc($resExiste);
        }
        if(!empty($existe)){
            if($existe["fefi_tx_status"] === "inativo"){
                query(
                    "UPDATE feriado_funcionario SET fefi_tx_status = 'ativo' WHERE fefi_nb_id = ?",
                    "i",
                    [$existe["fefi_nb_id"]]
                );
                $mantidos++;
            }else{
                $mantidos++;
            }
            continue;
        }

        $novo = [
            "fefi_tx_nome" => $nome,
            "fefi_tx_data" => $data,
            "fefi_nb_entidade" => $funcId,
            "fefi_tx_status" => "ativo",
            "fefi_nb_userCadastro" => $_SESSION["user_nb_id"] ?? null,
            "fefi_tx_dataCadastro" => date("Y-m-d H:i:s")
        ];
        inserir("feriado_funcionario", array_keys($novo), array_values($novo));
        $inseridos++;
    }

    if($inseridos > 0 || $mantidos > 0){
        set_status("Sucesso: {$inseridos} inserido(s), {$mantidos} mantido(s).");
    }else{
        set_status("ERRO: Nenhum funcionário foi processado.");
    }

    index();
    exit;
}

function editarFeriadoCargo(){
    ensureFeriadoFuncionarioSchema();
    if(empty($_POST["nome"]) || empty($_POST["data"])){
        set_status("ERRO: Dados do feriado não identificados.");
        index();
        exit;
    }
    layout_feriado_cargo();
    exit;
}

function excluirGrupoFeriado(){
    ensureFeriadoFuncionarioSchema();
    if(!empty($_POST["nome"]) && !empty($_POST["data"])){
        $nome = $_POST["nome"];
        $data = $_POST["data"];
        $empresa = $_POST["empresa"] ?? "";
        $cargo = $_POST["cargo"] ?? "";
        
        $where = "fefi_tx_nome = ? AND fefi_tx_data = ?";
        $params = [$nome, $data];
        $types = "ss";
        
        if(!empty($empresa)){
            $where .= " AND fefi_nb_entidade IN (SELECT enti_nb_id FROM entidade WHERE enti_nb_empresa = ?)";
            $params[] = intval($empresa);
            $types .= "i";
        }
        if(!empty($cargo)){
            $where .= " AND fefi_nb_entidade IN (SELECT enti_nb_id FROM entidade WHERE enti_tx_tipoOperacao = ?)";
            $params[] = intval($cargo);
            $types .= "i";
        }
        
        query(
            "UPDATE feriado_funcionario SET fefi_tx_status = 'inativo' WHERE " . $where,
            $types,
            $params
        );
        set_status("Feriado excluído de todos os funcionários com sucesso.");
    }
    index();
    exit;
}

function excluirFeriadoCargo(){
    ensureFeriadoFuncionarioSchema();
    if(!empty($_POST["id"])){
        remover("feriado_funcionario", $_POST["id"]);
        set_status("Feriado excluído com sucesso.");
    }
    index();
    exit;
}

function voltarFeriadoCargo(){
    unset($_POST);
    index();
    exit;
}

if(!empty($_POST["acao"])){
    $acao = $_POST["acao"];
    if(function_exists($acao)){
        $acao();
    }else{
        set_status("ERRO: Ação não encontrada.");
        index();
    }
}else{
    index();
}
