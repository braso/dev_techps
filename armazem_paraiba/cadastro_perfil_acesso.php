<?php
/*

        ini_set("display_errors", 1);
        error_reporting(E_ALL);
*/
        header("Expires: 01 Jan 2001 00:00:00 GMT");
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");

include_once "load_env.php";
include_once "conecta.php";
mysqli_query($conn, "SET time_zone = '-3:00'");

function cadastrar(){
    $camposObrig = [
        "titulo" => "Título",
        "status" => "Status"
    ];
    $errorMsg = conferirCamposObrig($camposObrig, $_POST);
    if(!empty($errorMsg)){
        set_status("ERRO: ".$errorMsg);
        index();
        exit;
    }

    $_POST["titulo"] = trim($_POST["titulo"]);
    $_POST["descricao"] = isset($_POST["descricao"]) ? trim($_POST["descricao"]) : "";
    $status = in_array($_POST["status"], ["ativo","inativo"]) ? $_POST["status"] : "ativo";

    $dup = mysqli_fetch_assoc(query(
        (!empty($_POST["id"]) ?
            "SELECT perfil_nb_id FROM perfil_acesso WHERE perfil_tx_nome = ? AND perfil_nb_id != ?;" :
            "SELECT perfil_nb_id FROM perfil_acesso WHERE perfil_tx_nome = ?;"
        ),
        (!empty($_POST["id"]) ? "si" : "s"),
        (!empty($_POST["id"]) ? [$_POST["titulo"], (int)$_POST["id"]] : [$_POST["titulo"]])
    ));
    if(!empty($dup)){
        set_status("ERRO: Já existe um perfil com este título!");
        index();
        exit;
    }

    $novo = [
        "perfil_tx_nome" => $_POST["titulo"],
        "perfil_tx_descricao" => $_POST["descricao"],
        "perfil_tx_status" => $status,
        "perfil_tx_dataAtualiza" => date("Y-m-d H:i:s")
    ];

    if(!empty($_POST["id"])){
        atualizar("perfil_acesso", array_keys($novo), array_values($novo), $_POST["id"]);
        $perfilId = (int)$_POST["id"];
        set_status("<script>Swal.fire('Sucesso!', 'Perfil atualizado com sucesso.', 'success');</script>");
    }else{
        $novo["perfil_tx_dataCadastro"] = date("Y-m-d H:i:s");
        $ids = inserir("perfil_acesso", array_keys($novo), array_values($novo));
        $perfilId = (is_array($ids) && isset($ids[0])) ? (int)$ids[0] : 0;
        set_status("<script>Swal.fire('Sucesso!', 'Perfil inserido com sucesso.', 'success');</script>");
    }

    if(empty($perfilId) || $perfilId <= 0){
        $perfilId = ultimo_reg("perfil_acesso");
    }

    $routes = isset($_POST["routes_ver"]) ? $_POST["routes_ver"] : [];
    if($perfilId > 0){
        query("DELETE FROM perfil_menu_item WHERE perfil_nb_id = ?", "i", [$perfilId]);
    }
    if(is_array($routes)){
        foreach($routes as $mid){
            $mid = (int)$mid;
            if($mid > 0){
                query(
                    "INSERT INTO perfil_menu_item (perfil_nb_id, menu_nb_id, perm_ver, perm_inserir, perm_editar, perm_excluir) VALUES (?,?,?,?,?,?)",
                    "iiiiii",
                    [$perfilId, $mid, 1, 0, 0, 0]
                );
            }
        }
    }

    index();
    exit;
}

function editarPerfil(){
    index();
    exit;
}

function excluirPerfil(){
    query("DELETE FROM perfil_menu_item WHERE perfil_nb_id = ?", "i", [$_POST["id"]]);
    query("DELETE FROM perfil_acesso WHERE perfil_nb_id = ?", "i", [$_POST["id"]]);
    set_status("<script>Swal.fire('Sucesso!', 'Perfil excluído com sucesso.', 'info');</script>");
    unset($_POST["id"]);
    index();
    exit;
}

function voltarPerfil(){
    unset($_POST);
    index();
    exit;
}

function novoPerfil(){
    $_POST["_novo"] = 1;
    index();
    exit;
}

function limparFiltrosPerfil(){
    unset($_POST["busca_nome_like"], $_POST["busca_status"]);
    $_POST["busca_status"] = "ativo";
    index();
    exit;
}

function formPerfil(){
    $perfilId = (!empty($_POST["id"])) ? (int)$_POST["id"] : 0;
    if($perfilId > 0){
        $perfil = mysqli_fetch_assoc(query(
            "SELECT * FROM perfil_acesso WHERE perfil_nb_id = ?;", "i", [$perfilId]
        ));
    }
    query("UPDATE menu_item SET menu_tx_secao = 'Batida de Ponto' WHERE menu_tx_secao = 'motorista'");

    $menuPairs = [];
    $menuPath = __DIR__."/menu.php";
    if(file_exists($menuPath)){
        $txt = file_get_contents($menuPath);
        $secs = ["cadastros","ponto","painel","relatórios"];
        foreach($secs as $sec){
            if(preg_match('/"'.preg_quote($sec,'/').'"\s*=>\s*\[(.*?)\]/s', $txt, $m)){
                if(preg_match_all('/"([^"]+)"\s*=>\s*"([^"]+)"/', $m[1], $mm, PREG_SET_ORDER)){
                    foreach($mm as $e){ $menuPairs[] = [$sec, $e[2], $e[1]]; }
                }
            }
        }
        if(strpos($txt, '/cadastro_comunicado.php') !== false){ $menuPairs[] = ["cadastros","Comunicado","/cadastro_comunicado.php"]; }
    }
    $filtered = [];
    foreach($menuPairs as $pair){
        $full = __DIR__.$pair[2];
        if(file_exists($full)){ $filtered[] = $pair; }
    }
    $menuPairs = $filtered;
    $counts = [];
    foreach($menuPairs as $pair){
        $sec = $pair[0];
        $lab = $pair[1];
        $url = $pair[2];
        $full = __DIR__.$url;
        if(!file_exists($full)){ continue; }
        if(!isset($counts[$sec])){ $counts[$sec] = 0; }
        $ix = ++$counts[$sec];
        $dups = [];
        $rsPaths = query("SELECT menu_nb_id FROM menu_item WHERE menu_tx_path = ? ORDER BY menu_nb_id ASC", "s", [$url]);
        while($rsPaths && ($r = mysqli_fetch_assoc($rsPaths))){ $dups[] = (int)$r['menu_nb_id']; }
        if(empty($dups)){
            $slug = strtolower(preg_replace(['#/^\/#','#\.php$#'], ['', ''], $url));
            $slug = str_replace('/', '_', $slug);
            $slug = preg_replace('/[^a-z0-9_]+/i','_', $slug);
            query(
                "INSERT INTO menu_item (menu_tx_secao, menu_tx_label, menu_tx_path, menu_tx_chave, menu_tx_ativo, menu_tx_ordem) VALUES (?,?,?,?,?,?)",
                "ssssii",
                [$sec, $lab, $url, $slug, 1, $ix]
            );
        }else{
            $keepId = $dups[0];
            for($k=1;$k<count($dups);$k++){ query("DELETE FROM menu_item WHERE menu_nb_id = ?", "i", [$dups[$k]]); }
            query(
                "UPDATE menu_item SET menu_tx_secao = ?, menu_tx_label = ?, menu_tx_path = ?, menu_tx_ordem = ?, menu_tx_ativo = 1 WHERE menu_nb_id = ?",
                "sssii",
                [$sec, $lab, $url, $ix, $keepId]
            );
        }
    }

    $validPaths = array_map(function($p){ return $p[2]; }, $menuPairs);
    $rsAll = query("SELECT menu_nb_id, menu_tx_path FROM menu_item WHERE menu_tx_ativo = 1 ORDER BY menu_nb_id ASC");
    while($rsAll && ($rr = mysqli_fetch_assoc($rsAll))){
        $p = $rr["menu_tx_path"];
        $full = __DIR__.$p;
        if(!file_exists($full) || !in_array($p, $validPaths)){
            query("UPDATE menu_item SET menu_tx_ativo = 0 WHERE menu_nb_id = ?", "i", [$rr["menu_nb_id"]]);
        }
    }

    $selecionados = [];
    if($perfilId > 0){
        $rsSel = query("SELECT menu_nb_id FROM perfil_menu_item WHERE perfil_nb_id = ? AND perm_ver = 1", "i", [$perfilId]);
        while($rsSel && ($r = mysqli_fetch_assoc($rsSel))){ $selecionados[(int)$r["menu_nb_id"]] = true; }
    }

    $items = [];
    $res = query("SELECT menu_nb_id, menu_tx_secao, menu_tx_label FROM menu_item WHERE menu_tx_ativo = 1 ORDER BY menu_tx_secao, menu_tx_ordem");
    while($res && ($row = mysqli_fetch_assoc($res))){ $items[] = $row; }

    $grupo = [];
    foreach($items as $it){ $grupo[$it["menu_tx_secao"]][] = $it; }

    $checksSection = "<div class='row' style='margin-top:10px'>"
        
        ."</div>";
    foreach($grupo as $secao => $lista){
        $secSlug = strtolower(preg_replace('/[^a-z0-9_]+/i','_', $secao));
        $checksSection .= "<div class='row' style='margin-bottom:10px' data-sec='".$secSlug."'>"
            ."<div class='col-sm-12' style='display:flex; align-items:center; margin-bottom:6px; border-left:4px solid #4c6ef5; padding-left:8px'>".ucfirst($secao)
            ."<button type='button' style='margin-left:10px; display:inline-flex; align-items:center; gap:8px; font-size:12px; padding:4px 8px; border:1px solid #e5e7eb; border-radius:6px; background:#eaffea; cursor:pointer'>Marcar todos</button>"
            ."<button type='button' style='margin-left:6px; display:inline-flex; align-items:center; gap:8px; font-size:12px; padding:4px 8px; border:1px solid #e5e7eb; border-radius:6px; background:#ffeaeb; cursor:pointer'>Desmarcar todos</button>"
            ."</div>"
            ."<div class='col-sm-12' style='display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:10px'>";

        foreach($lista as $it){
            $mid = (int)$it["menu_nb_id"];
            $isChecked = isset($selecionados[$mid]);
            $bgStyle = $isChecked ? "background:#eaffea; border-color:#b7e1b7;" : "background:#f9fafb; border-color:#e5e7eb;";
            $checksSection .= "<label class='menu-check-item' style='border-radius:10px; padding:10px; border:1px solid; display:flex; align-items:center; gap:10px; " . $bgStyle . "'>";
            $checksSection .= "<input type='checkbox' name='routes_ver[]' value='".$mid."' ".($isChecked?"checked":"").">";
            $checksSection .= "<span style='font-weight:600'>".$it["menu_tx_label"]."</span>";
            $checksSection .= "</label>";
        }
        $checksSection .= "</div></div>";
    }
    $checksSection .= "</div>";
    $checksSection .= "<script>(function(){var sync=function(){document.querySelectorAll('.menu-check-item input[type=checkbox]').forEach(function(c){var l=c.closest('.menu-check-item');if(l){if(c.checked){l.style.background='#eaffea';l.style.borderColor='#b7e1b7';}else{l.style.background='#f9fafb';l.style.borderColor='#e5e7eb';}}})};sync();document.addEventListener('change',function(e){var c=e.target;if(c && c.matches('.menu-check-item input[type=checkbox]')){var l=c.closest('.menu-check-item');if(l){if(c.checked){l.style.background='#eaffea';l.style.borderColor='#b7e1b7';}else{l.style.background='#f9fafb';l.style.borderColor='#e5e7eb';}}}});document.addEventListener('click',function(e){var btn=e.target.closest('button');if(btn){var label=btn.textContent.trim();if(label==='Marcar todos'||label==='Desmarcar todos'){e.preventDefault();var sec=btn.getAttribute('data-sec');var scope;if(sec==='__global'){scope=document}else{scope=btn.closest('[data-sec]')||document} Array.prototype.forEach.call(scope.querySelectorAll('.menu-check-item input[type=checkbox]'),function(c){if(label==='Marcar todos' && !c.checked){c.click();} if(label==='Desmarcar todos' && c.checked){c.click();}});}}});})();</script>";

    $campos = [
        campo_hidden("id", ($perfilId > 0 ? $perfilId : "")),
        campo("Título*", "titulo", (!empty($perfil["perfil_tx_nome"]) ? $perfil["perfil_tx_nome"] : ""), 4, "", "required maxlength='100'"),
        campo("Descrição", "descricao", (!empty($perfil["perfil_tx_descricao"]) ? $perfil["perfil_tx_descricao"] : ""), 6, "", "maxlength='255'"),
        combo("Status*", "status", (!empty($perfil["perfil_tx_status"]) ? $perfil["perfil_tx_status"] : "ativo"), 2, ["ativo"=>"Ativo","inativo"=>"Inativo"], "required")
    ];

    $botoes =
        empty($_POST["id"]) ?
            [
                botao("Cadastrar", "cadastrar", "cadastrar_perfil", "", "class='btn btn-success'"),
                criarBotaoVoltar("cadastro_perfil_acesso.php", "voltarPerfil")
            ] :
            [
                botao("Atualizar", "cadastrar", "atualizar_perfil", "", "class='btn btn-success'"),
                criarBotaoVoltar("cadastro_perfil_acesso.php", "voltarPerfil")
            ]
    ;

    echo abre_form();
    echo linha_form($campos);
    echo $checksSection;
    echo fecha_form($botoes);
}

function listarPerfis(){
    $gridFields = [
        "ID" => "perfil_nb_id",
        "NOME" => "perfil_tx_nome",
        "STATUS" => "perfil_tx_status",
        "DATA DE CADASTRO" => "perfil_tx_dataCadastro",
        "DATA DE ALTERAÇÃO" => "perfil_tx_dataAtualiza"
    ];

    $camposBusca = [
        "busca_nome_like" => "perfil_tx_nome",
        "busca_status" => "perfil_tx_status"
    ];

    $queryBase = "SELECT ".implode(", ", array_values($gridFields))." FROM perfil_acesso";

    $actions = criarIconesGrid(
        ["glyphicon glyphicon-search search-button", "glyphicon glyphicon-remove search-remove"],
        ["cadastro_perfil_acesso.php", "cadastro_perfil_acesso.php"],
        ["editarPerfil()", "excluirPerfil()"]
    );

    $gridFields["actions"] = $actions["tags"];

    $jsFunctions = "const funcoesInternas = function(){".implode(" ", $actions["functions"])."}";

    echo gridDinamico("perfil_acesso", $gridFields, $camposBusca, $queryBase, $jsFunctions);
}

function index(){
        include "check_permission.php";
        verificaPermissao('/cadastro_perfil_acesso.php');

    $tituloCabecalho = !empty($_POST["_novo"]) ? "Cadastro novo perfil" : "Cadastro de Perfil de Acesso";
    cabecalho($tituloCabecalho);

    if(!empty($_POST["id"]) || !empty($_POST["_novo"])){
        formPerfil();
    } else {
        if(!isset($_POST["busca_status"])){
            $_POST["busca_status"] = "ativo";
        }

        $campos = [
            campo("Título", "busca_nome_like", (empty($_POST["busca_nome_like"]) ? "" : $_POST["busca_nome_like"]), 4, "", "maxlength='100'"),
            combo("Status", "busca_status", (empty($_POST["busca_status"]) ? "" : $_POST["busca_status"]), 2, ["ativo"=>"Ativo","inativo"=>"Inativo"])
        ];

        $botoes = [
            botao("Buscar", "index"),
            botao("Limpar Filtro", "limparFiltrosPerfil"),
            botao("Inserir", "novoPerfil", "", "", "", "", "btn btn-success")
        ];

        echo abre_form();
        echo linha_form($campos);
        echo fecha_form($botoes);

        listarPerfis();
    }

    rodape();
}

index();
?>
