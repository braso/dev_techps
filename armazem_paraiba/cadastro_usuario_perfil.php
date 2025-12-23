<?php

/*
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
*/
		header("Expires: 01 Jan 2001 00:00:00 GMT");
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.



include_once "load_env.php";
include_once "conecta.php";
mysqli_query($conn, "SET time_zone = '-3:00'");

function cadastrar(){
    $modo = isset($_POST["modo"]) ? $_POST["modo"] : "individual";
    $perfil  = isset($_POST["perfil"]) ? (int)$_POST["perfil"] : 0;
    $statusV = isset($_POST["status"]) ? $_POST["status"] : "ativo";
    $ativo   = ($statusV === "ativo") ? 1 : 0;

    set_status("<script>console.log('DBG POST resumo', ".json_encode([
        'modo' => isset($_POST["modo"]) ? $_POST["modo"] : null,
        'perfil' => $perfil,
        'status' => $statusV,
        'usuario' => isset($_POST["usuario"]) ? $_POST["usuario"] : null,
        'usuarios_len' => (isset($_POST["usuarios"]) && is_array($_POST["usuarios"])) ? count($_POST["usuarios"]) : 0,
        'usuarios_csv' => isset($_POST["usuarios_csv"]) ? $_POST["usuarios_csv"] : ''
    ])." );</script>");

    $listaA = (!empty($_POST["usuarios"]) && is_array($_POST["usuarios"])) ? $_POST["usuarios"] : [];
    $listaB = (!empty($_POST["usuarios_csv"])) ? explode(',', $_POST["usuarios_csv"]) : [];
    $usuarios = array_unique(array_filter(array_map('intval', array_merge($listaA, $listaB))));
    $isGrupo = ($modo === "grupo") || (!empty($usuarios));

    set_status("<script>console.log('DBG grupo detectado', ".json_encode([
        'isGrupo' => $isGrupo,
        'usuarios' => $usuarios
    ])." );</script>");

    if($isGrupo){
        set_status("<script>console.log('DBG fluxo grupo iniciado');</script>");
        if($perfil <= 0 || empty($usuarios)){
            set_status("<script>console.log('DBG validação falhou', {perfil:".$perfil.", usuarios_vazios:".(empty($usuarios)?'true':'false')."});</script>");
            set_status("ERRO: Selecione um perfil e pelo menos um usuário.");
            index();
            exit;
        }
        set_status("<script>console.log('DBG START TRANSACTION');</script>");
        query("START TRANSACTION");
        $aplicados = 0;
        $idsProcessados = [];
        foreach($usuarios as $uid){
            $usuario = (int)$uid;
            if($usuario <= 0) continue;
            $exist = mysqli_fetch_assoc(query(
                "SELECT uperf_nb_id FROM usuario_perfil WHERE user_nb_id = ? LIMIT 1;",
                "i",
                [$usuario]
            ));
            if(!empty($exist)){
                atualizar("usuario_perfil", ["user_nb_id","perfil_nb_id","ativo"], [$usuario, $perfil, $ativo], (int)$exist["uperf_nb_id"]);
                $aplicados++;
                $idsProcessados[] = $usuario;
                set_status("<script>console.log('DBG update', {usuario:".$usuario.", uperf_nb_id:".(int)$exist["uperf_nb_id"].", perfil:".$perfil.", ativo:".$ativo."});</script>");
            }else{
                $res = inserir("usuario_perfil", ["user_nb_id","perfil_nb_id","ativo"], [$usuario, $perfil, $ativo]);
                $aplicados++;
                $idsProcessados[] = $usuario;
                $newId = (is_array($res) && isset($res[0])) ? (int)$res[0] : 0;
                set_status("<script>console.log('DBG insert', {usuario:".$usuario.", novo_uperf_nb_id:".$newId.", perfil:".$perfil.", ativo:".$ativo."});</script>");
            }
        }
        if($aplicados > 0){
            set_status("<script>console.log('DBG COMMIT', {aplicados:".$aplicados.", ids:".json_encode($idsProcessados)."});</script>");
            query("COMMIT");
            set_status("<script>Swal.fire('Sucesso!', 'Perfil aplicado para ".$aplicados." usuário(s): ".implode(', ', $idsProcessados).".', 'success');</script>");
        }else{
            set_status("<script>console.log('DBG ROLLBACK - nenhum aplicado');</script>");
            query("ROLLBACK");
            set_status("ERRO: Nenhum usuário processado.");
        }
        index();
        exit;
    }

    $camposObrig = [
        "usuario" => "Usuário",
        "perfil" => "Perfil",
        "status" => "Status"
    ];
    $errorMsg = conferirCamposObrig($camposObrig, $_POST);
    if(!empty($errorMsg)){
        set_status("ERRO: ".$errorMsg);
        index();
        exit;
    }

    $usuario = (int)$_POST["usuario"];
    if($usuario <= 0 || $perfil <= 0){
        set_status("ERRO: Selecione usuário e perfil válidos.");
        index();
        exit;
    }

    $exist = mysqli_fetch_assoc(query(
        (!empty($_POST["id"]) ?
            "SELECT uperf_nb_id FROM usuario_perfil WHERE uperf_nb_id = ?;" :
            "SELECT uperf_nb_id FROM usuario_perfil WHERE user_nb_id = ?;"
        ),
        "i",
        [(!empty($_POST["id"]) ? (int)$_POST["id"] : $usuario)]
    ));

    if(!empty($_POST["id"]) || !empty($exist)){
        $id = !empty($_POST["id"]) ? (int)$_POST["id"] : (int)$exist["uperf_nb_id"];
        atualizar("usuario_perfil", ["user_nb_id","perfil_nb_id","ativo"], [$usuario, $perfil, $ativo], $id);
        set_status("<script>Swal.fire('Sucesso!', 'Vínculo atualizado com sucesso.', 'success');</script>");
    }else{
        inserir("usuario_perfil", ["user_nb_id","perfil_nb_id","ativo"], [$usuario, $perfil, $ativo]);
        set_status("<script>Swal.fire('Sucesso!', 'Vínculo inserido com sucesso.', 'success');</script>");
    }

    index();
    exit;
}

function editarUsuarioPerfil(){
    if(empty($_POST["id"])){
        set_status("Registro não identificado.");
        index();
        exit;
    }

    $registro = mysqli_fetch_assoc(query(
        "SELECT uperf_nb_id, user_nb_id FROM usuario_perfil WHERE uperf_nb_id = ? LIMIT 1;",
        "i",
        [$_POST["id"]]
    ));
    if(empty($registro)){
        set_status("Registro não encontrado.");
        index();
        exit;
    }

    $usuario = mysqli_fetch_assoc(query(
        "SELECT user_nb_id, user_tx_login, user_tx_nome, user_nb_empresa FROM user WHERE user_nb_id = ? LIMIT 1;",
        "i",
        [$registro["user_nb_id"]]
    ));
    $empresaNome = "";
    if(!empty($usuario["user_nb_empresa"])){
        $emp = mysqli_fetch_assoc(query(
            "SELECT empr_tx_nome FROM empresa WHERE empr_nb_id = ? LIMIT 1;",
            "i",
            [(int)$usuario["user_nb_empresa"]]
        ));
        if(!empty($emp)){ $empresaNome = $emp["empr_tx_nome"]; }
    }

    $permissoes = mysqli_fetch_all(query(
        "SELECT uperf.uperf_nb_id, p.perfil_tx_nome, uperf.ativo
         FROM usuario_perfil uperf
         JOIN perfil_acesso p ON p.perfil_nb_id = uperf.perfil_nb_id
         WHERE uperf.user_nb_id = ?
         ORDER BY p.perfil_tx_nome",
        "i",
        [$registro["user_nb_id"]]
    ), MYSQLI_ASSOC);

    cabecalho("Permisoes de usuarios");

    $titulo = "Permissões do usuário: ".(!empty($usuario["user_tx_login"])? $usuario["user_tx_login"]: $registro["user_nb_id"]);
    echo abre_form($titulo);

    echo "<div class='row'>"
        ."<div class='col-sm-12 margin-bottom-5 campo-fit-content' style='margin-bottom:12px'>"
        ."<div style='display:flex; gap:24px; flex-wrap:wrap; align-items:center; background:#f9f9f9; border:1px solid #eee; border-radius:8px; padding:12px'>"
            ."<div><label>Nome</label><div>".htmlspecialchars(!empty($usuario["user_tx_nome"]) ? $usuario["user_tx_nome"] : "-")."</div></div>"
            ."<div><label>Login</label><div>".htmlspecialchars(!empty($usuario["user_tx_login"]) ? $usuario["user_tx_login"] : "-")."</div></div>"
            ."<div><label>Empresa</label><div>".htmlspecialchars(!empty($empresaNome)? $empresaNome : "-")."</div></div>"
        ."</div>"
        ."</div>"
    ."</div>";

    $thead = "<thead><tr><th>ID</th><th>USUÁRIO</th><th>PERFIL</th><th>STATUS</th></tr></thead>";
    $tbody = "<tbody>";
    foreach($permissoes as $p){
        $statusTxt = (intval($p["ativo"]) === 1 ? "Ativo" : "Inativo");
        $tbody .= "<tr><td>".$p["uperf_nb_id"]."</td><td>".htmlspecialchars($usuario["user_tx_login"])."</td><td>".htmlspecialchars($p["perfil_tx_nome"])."</td><td>".$statusTxt."</td></tr>";
    }
    $tbody .= "</tbody>";

    echo "<div class='row'><div class='col-sm-12 margin-bottom-5 campo-fit-content'>"
        ."<table class='table table-striped table-bordered'>"
        .$thead.$tbody
        ."</table>"
        ."</div></div>";

    $perfilIds = [];
    $rsPerfUser = query("SELECT perfil_nb_id FROM usuario_perfil WHERE user_nb_id = ? AND ativo = 1", "i", [$registro["user_nb_id"]]);
    while($rsPerfUser && ($r = mysqli_fetch_assoc($rsPerfUser))){ $perfilIds[] = (int)$r["perfil_nb_id"]; }

    if(!empty($perfilIds)){
        $in = implode(",", array_map("intval", $perfilIds));
        $itens = mysqli_fetch_all(query(
            "SELECT mi.menu_tx_secao, mi.menu_tx_label
             FROM perfil_menu_item pmi
             JOIN menu_item mi ON mi.menu_nb_id = pmi.menu_nb_id
             WHERE pmi.perfil_nb_id IN (".$in.")
               AND pmi.perm_ver = 1
               AND mi.menu_tx_ativo = 1
             ORDER BY mi.menu_tx_secao, mi.menu_tx_ordem"
        ), MYSQLI_ASSOC);
        $grupo = [];
        foreach($itens as $it){ $grupo[$it["menu_tx_secao"]][] = $it["menu_tx_label"]; }

        echo "<div class='row'><div class='col-sm-12 margin-bottom-5 campo-fit-content'>"
            ."<div class='portlet light' style='padding:12px'>"
            ."<div style='font-weight:bold; font-size:16px; margin-bottom:10px'>Permissões de visualização</div>";
        foreach($grupo as $sec=>$labels){
            echo "<div class='row' style='margin-bottom:10px'>"
                ."<div class='col-sm-12' style='display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; border-left:4px solid #4c6ef5; padding-left:8px'>"
                ."<span>".htmlspecialchars(ucfirst($sec))."</span>"
                ."</div>"
                ."<div class='col-sm-12' style='display:grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap:8px'>";
            foreach($labels as $lab){
                echo "<span style='display:block; background:#f9f9f9; border:1px solid #eee; border-radius:8px; padding:8px'>".htmlspecialchars($lab)."</span>";
            }
            echo "</div></div>";
        }
        echo "</div></div></div>";
    }

    $optsPerfis = [];
    $rsPerf = query("SELECT perfil_nb_id, perfil_tx_nome FROM perfil_acesso WHERE perfil_tx_status='ativo' ORDER BY perfil_tx_nome");
    while($rsPerf && ($pr = mysqli_fetch_assoc($rsPerf))){ $optsPerfis[$pr["perfil_nb_id"]] = $pr["perfil_tx_nome"]; }

    $fieldsTroca = [
        campo_hidden("usuario", $registro["user_nb_id"]),
        combo("Perfil*", "perfil", (isset($perfilIds[0]) ? $perfilIds[0] : ""), 4, $optsPerfis, "required"),
        combo("Status*", "status", "ativo", 2, ["ativo"=>"Ativo","inativo"=>"Inativo"], "required")
    ];
    echo linha_form($fieldsTroca);
    echo fecha_form([
        botao("Atualizar Perfil", "cadastrar", "atualizar_usuario_perfil", "", "class='btn btn-success'"),
        botao("Voltar", "voltarUsuarioPerfil", "", "", "class='btn btn-default' formnovalidate")
    ]);

    rodape();
    exit;
}

function excluirUsuarioPerfil(){
    query("DELETE FROM usuario_perfil WHERE uperf_nb_id = ?", "i", [$_POST["id"]]);
    set_status("<script>Swal.fire('Sucesso!', 'Vínculo excluído com sucesso.', 'info');</script>");
    unset($_POST["id"]);
    index();
    exit;
}

function voltarUsuarioPerfil(){
    unset($_POST);
    index();
    exit;
}

function novoUsuarioPerfil(){
    $_POST["_novo"] = 1;
    index();
    exit;
}

function limparFiltrosUsuarioPerfil(){
    unset($_POST["busca_usuario_like"], $_POST["busca_perfil_like"], $_POST["busca_usuario"], $_POST["busca_perfil"], $_POST["busca_status"]);
    $_POST["busca_status"] = 1;
    index();
    exit;
}

function formUsuarioPerfil(){
    $dados = [];
    if(!empty($_POST["id"])){
        $dados = mysqli_fetch_assoc(query(
            "SELECT * FROM usuario_perfil WHERE uperf_nb_id = ?", "i", [$_POST["id"]]
        ));
    }

    $optsPerfis = [];
    $rsPerf = query("SELECT perfil_nb_id, perfil_tx_nome FROM perfil_acesso WHERE perfil_tx_status='ativo' ORDER BY perfil_tx_nome");
    while($rsPerf && ($r = mysqli_fetch_assoc($rsPerf))){ $optsPerfis[$r["perfil_nb_id"]] = $r["perfil_tx_nome"]; }

    $empresa = (!empty($_POST["empresa"]) ? (int)$_POST["empresa"] : (isset($_SESSION["user_nb_empresa"]) ? (int)$_SESSION["user_nb_empresa"] : 0));

    $allUsers = [];
    $rsUsers = query(
        "SELECT 
            u.user_nb_id AS uid,
            e.enti_nb_id AS eid,
            COALESCE(NULLIF(u.user_tx_nome,''), e.enti_tx_nome, u.user_tx_login) AS nome_label
         FROM entidade e
         LEFT JOIN user u 
            ON u.user_nb_entidade = e.enti_nb_id
           AND u.user_tx_status = 'ativo'
         WHERE e.enti_tx_status = 'ativo'"
         .($empresa>0? " AND e.enti_nb_empresa = {$empresa}": "")
         ." ORDER BY nome_label ASC"
    );
    while($rsUsers && ($r = mysqli_fetch_assoc($rsUsers))){ $allUsers[] = $r; }

    $duallist = "<div class='col-md-12' style='margin-top:20px'>"
        ."<div class='portlet light' style='padding:12px'>"
            ."<div style='font-weight:bold; font-size:16px; margin-bottom:10px'>Grupo de usuários</div>"
            ."<div class='row' style='gap:10px'>"
                ."<div class='col-sm-5'>"
                    ."<label>Todos os usuários</label>"
                    ."<select id='usersPool' multiple size='12' class='form-control' style='height:auto'>";
    foreach($allUsers as $u){ 
        $val = intval($u["uid"]);
        $label = htmlspecialchars($u["nome_label"]);
        if($val > 0){
            $duallist .= "<option value='".$val."'>".$label."</option>";
        }else{
            $duallist .= "<option value='' disabled>".$label." (sem usuário)</option>";
        }
    }
    $duallist .= "</select>"
                ."</div>"
                ."<div class='col-sm-2' style='display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px'>"
                    ."<button type='button' id='toSelected' class='btn btn-default'><i class='glyphicon glyphicon-arrow-right'></i></button>"
                    ."<button type='button' id='toPool' class='btn btn-default'><i class='glyphicon glyphicon-arrow-left'></i></button>"
                    ."<button type='button' id='toSelectedAll' class='btn btn-default'>Enviar todos</button>"
                    ."<button type='button' id='toPoolAll' class='btn btn-default'>Voltar todos</button>"
                ."</div>"
                ."<div class='col-sm-5'>"
                    ."<label>Selecionados</label>"
                    ."<select id='usersSelected' name='usuarios[]' multiple size='12' class='form-control' style='height:auto'></select>"
                ."</div>"
            ."</div>"
        ."</div>"
    ."</div>"
    ."<script>(function(){
        var modo=document.querySelector('[name=modo]');
        var pool=document.getElementById('usersPool');
        var sel=document.getElementById('usersSelected');
        var usr=document.querySelector('[name=usuario]');
        function move(from,to){
            var opts=from.options;
            for(var i=opts.length-1;i>=0;i--){
                if(opts[i].selected){
                    opts[i].selected=false;
                    to.appendChild(opts[i]);
                    if(to===sel){opts[i].selected=true;} else {opts[i].selected=false;}
                }
            }
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
        }
        var btnToSel=document.getElementById('toSelected');
        var btnToPool=document.getElementById('toPool');
        var btnToSelAll=document.getElementById('toSelectedAll');
        var btnToPoolAll=document.getElementById('toPoolAll');
        if(btnToSel){btnToSel.onclick=function(){move(pool,sel); fillCsv();} }
        if(btnToPool){btnToPool.onclick=function(){move(sel,pool); fillCsv();} }
        if(btnToSelAll){btnToSelAll.onclick=function(){moveAll(pool,sel); fillCsv();} }
        if(btnToPoolAll){btnToPoolAll.onclick=function(){moveAll(sel,pool); fillCsv();} }
        function toggle(){
            var grp=document.getElementById('grpBox');
            if(!grp) return;
            var v=(modo&&modo.value==='grupo');
            grp.style.display=v?'block':'none';
            var ind=document.getElementById('indBox');
            if(ind) ind.style.display=v?'none':'block';
            if(usr){ if(v){ usr.required=false; usr.disabled=true; } else { usr.disabled=false; usr.required=true; } }
        }
        document.addEventListener('change',function(e){ if(e.target&&e.target.name==='modo'){ toggle(); } });
        toggle();
        function fillCsv(){
            var csv=document.querySelector('[name=usuarios_csv]');
            if(csv&&sel){
                var ids=[]; var opts=sel.options;
                for(var i=0;i<opts.length;i++){ if(opts[i].value && opts[i].value.length>0){ ids.push(opts[i].value); } }
                csv.value=ids.join(',');
            }
        }
        if(document.contex_form){
            var submitting=false;
            document.contex_form.addEventListener('submit',function(e){
                if(submitting) return;
                var submitter = e.submitter || document.activeElement;
                var isVoltar = false;
                if(submitter){
                    var n = submitter.getAttribute('name');
                    var v = submitter.getAttribute('value');
                    isVoltar = (n === 'acao' && v === 'voltarUsuarioPerfil');
                }
                if(modo && modo.value === 'grupo' && !isVoltar){
                    fillCsv();
                    var perfil = document.querySelector('[name=perfil]');
                    var count = sel ? sel.options.length : 0;
                    var name = (perfil && perfil.selectedIndex >= 0) ? perfil.options[perfil.selectedIndex].text : '';
                    if(typeof Swal !== 'undefined'){
                        e.preventDefault();
                        Swal.fire({title:'Confirmar',text:'Aplicar \"'+name+'\" para '+count+' usuário(s)?',icon:'question',showCancelButton:true,confirmButtonText:'Aplicar',cancelButtonText:'Cancelar'}).then(function(r){
                          if(r && r.isConfirmed){
                            for(var i=0; i<(sel?sel.options.length:0); i++){ sel.options[i].selected=true; }
                            fillCsv();
                            submitting = true;
                            if(submitter && typeof submitter.click === 'function'){
                                submitter.click();
                            }else{
                                var hiddenAcao = document.createElement('input');
                                hiddenAcao.type = 'hidden';
                                hiddenAcao.name = 'acao';
                                hiddenAcao.value = 'cadastrar';
                                document.contex_form.appendChild(hiddenAcao);
                                document.contex_form.submit();
                            }
                          }
                        });
                    }else{
                        for(var i=0;i<(sel?sel.options.length:0);i++){ sel.options[i].selected=true; }
                        fillCsv();
                    }
                }
            });
        }
    })();</script>";

    $campos = [
        campo_hidden("id", (!empty($_POST["id"]) ? $_POST["id"] : "")),
        campo_hidden("usuarios_csv", ""),
        (!empty($_POST["_novo"]) ? campo_hidden("_novo", "1") : ""),
        combo_net("Empresa*", "empresa", ($empresa>0? $empresa: ""), 3, 'empresa', "required onchange='document.contex_form.submit()'"),
        combo("Operação*", "modo", (isset($_POST["modo"]) ? $_POST["modo"] : "individual"), 3, ["individual"=>"Individual","grupo"=>"Grupo"], "required"),
        (function() use ($allUsers, $dados){
            $sel = "<div id='indBox'><div class='col-sm-4 margin-bottom-5 campo-fit-content'>"
                 ."<label>Usuário*</label>"
                 ."<select name='usuario' class='form-control input-sm campo-fit-content' required>";
            $sel .= "<option value='' ".(empty($dados["user_nb_id"]) ? "selected" : "")." disabled>Selecione</option>";
            foreach($allUsers as $u){
                $uid = intval($u["uid"]);
                $selected = (!empty($dados["user_nb_id"]) && intval($dados["user_nb_id"]) === $uid) ? "selected" : "";
                if($uid > 0){
                    $sel .= "<option value='".$uid."' ".$selected.">".htmlspecialchars($u["nome_label"])."</option>";
                }else{
                    $sel .= "<option value='' disabled>".htmlspecialchars($u["nome_label"])." (sem usuário)</option>";
                }
            }
            $sel .= "</select></div></div>";
            return $sel;
        })(),
        combo("Perfil*", "perfil", (!empty($dados["perfil_nb_id"]) ? $dados["perfil_nb_id"] : ""), 4, $optsPerfis, "required"),
        combo("Status*", "status", ((!empty($dados) && intval($dados["ativo"])===1)? "ativo" : "ativo"), 2, ["ativo"=>"Ativo","inativo"=>"Inativo"], "required"),
        "<div id='grpBox' style='display:none'>".$duallist."</div>"
    ];

    $botoes =
        empty($_POST["id"]) ?
            [
                botao("Cadastrar", "cadastrar", "cadastrar_usuario_perfil", "", "class='btn btn-success'"),
                botao("Voltar", "voltarUsuarioPerfil", "", "", "class='btn btn-default' formnovalidate")
            ] :
            [
                botao("Atualizar", "cadastrar", "atualizar_usuario_perfil", "", "class='btn btn-success'"),
                botao("Voltar", "voltarUsuarioPerfil", "", "", "class='btn btn-default' formnovalidate")
            ]
    ;

    echo abre_form();
    echo linha_form($campos);
    echo fecha_form($botoes);
}

function listarUsuarioPerfis(){
    $gridFields = [
        "ID" => "uperf_nb_id",
        "USUÁRIO" => "user_tx_nome",
        "PERFIL" => "perfil_tx_nome",
        "STATUS" => "statusTxt"
    ];

    $camposBusca = [
        "busca_usuario" => "u.user_nb_id",
        "busca_perfil" => "p.perfil_nb_id",
        "busca_status" => "uperf.ativo"
    ];

    $queryBase =
        "SELECT 
            uperf.uperf_nb_id, 
            u.user_tx_login, 
            u.user_tx_nome, 
            p.perfil_tx_nome, 
            IF(uperf.ativo = 1, 'Ativo', 'Inativo') AS statusTxt
         FROM usuario_perfil uperf
         JOIN user u ON u.user_nb_id = uperf.user_nb_id
         JOIN perfil_acesso p ON p.perfil_nb_id = uperf.perfil_nb_id";

    $actions = criarIconesGrid(
        ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
        ["cadastro_usuario_perfil.php", "cadastro_usuario_perfil.php"],
        ["editarUsuarioPerfil()", "excluirUsuarioPerfil()"]
    );

    $gridFields["actions"] = $actions["tags"];

    $jsFunctions = "const funcoesInternas = function(){".implode(" ", $actions["functions"])."}";

    echo gridDinamico("usuario_perfil", $gridFields, $camposBusca, $queryBase, $jsFunctions);
}

function index(){
        
        //ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
        include "check_permission.php";
        // APATH QUE O USER ESTA TENTANDO ACESSAR PARA VERIFICAR NO PERFIL SE TEM ACESSO2
        verificaPermissao('/cadastro_usuario_perfil.php');
        
        $tituloCabecalho = !empty($_POST["_novo"]) ? "Vincular perfil ao usuário" : "Permisoes de usuarios";
        cabecalho($tituloCabecalho);

    if(!empty($_POST["id"]) || !empty($_POST["_novo"])){
        formUsuarioPerfil();
    } else {
        if(!isset($_POST["busca_status"])){
            $_POST["busca_status"] = 1;
        }

        $optsUsers = [""=>"Todos"];
        $rsU = query(
            "SELECT DISTINCT u.user_nb_id, COALESCE(NULLIF(u.user_tx_nome,''), e.enti_tx_nome, u.user_tx_login) AS nome_label"
            ." FROM entidade e"
            ." LEFT JOIN user u ON u.user_nb_entidade = e.enti_nb_id AND u.user_tx_status = 'ativo'"
            ." WHERE e.enti_tx_status = 'ativo'"
            ." ORDER BY nome_label"
        );
        while($rsU && ($r = mysqli_fetch_assoc($rsU))){
            if(!empty($r["user_nb_id"])) $optsUsers[(int)$r["user_nb_id"]] = $r["nome_label"];
        }

        $optsPerfis = [""=>"Todos"];
        $rsP = query("SELECT perfil_nb_id, perfil_tx_nome FROM perfil_acesso WHERE perfil_tx_status='ativo' ORDER BY perfil_tx_nome");
        while($rsP && ($r = mysqli_fetch_assoc($rsP))){ $optsPerfis[(int)$r["perfil_nb_id"]] = $r["perfil_tx_nome"]; }

        $campos = [
            combo("Usuário", "busca_usuario", (isset($_POST["busca_usuario"]) ? $_POST["busca_usuario"] : ""), 4, $optsUsers, "class='select2 filtro-select'"),
            combo("Perfil", "busca_perfil", (isset($_POST["busca_perfil"]) ? $_POST["busca_perfil"] : ""), 4, $optsPerfis, "class='select2 filtro-select'"),
            combo("Status", "busca_status", (isset($_POST["busca_status"]) ? $_POST["busca_status"] : 1), 2, [1=>"Ativo",0=>"Inativo"], "class='filtro-select'")
        ];

        $botoes = [
            botao("Buscar", "index"),
            botao("Limpar Filtro", "limparFiltrosUsuarioPerfil"),
            botao("Inserir", "novoUsuarioPerfil", "", "", "", "", "btn btn-success")
        ];

        echo abre_form();
        echo linha_form($campos);
        echo fecha_form($botoes);
        echo "<script>$(function(){ if($.fn.select2){ $.fn.select2.defaults.set('theme','bootstrap'); $('[name=busca_usuario],[name=busca_perfil]').select2({placeholder:'Selecione',allowClear:true}); $('[name=busca_usuario],[name=busca_perfil],[name=busca_status]').on('change', function(){ document.contex_form.submit(); }); } });</script>";

        listarUsuarioPerfis();
    }
    rodape();
}

index();
?>
