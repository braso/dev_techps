<?php

    include "load_env.php";

    $dominios = [
        "armazem_paraiba"		=> "Armazem Paraíba",
        "braso"					=> "Braso",
        "carau_transporte"		=> "Carau Transportes",
        "comav"					=> "Comav",
        "feijao_turqueza"		=> "Feijão Turqueza",
        "fs_log_transportes"	=> "Fs Log Transportes",
        "logsync_techps"	    => "Logsync",
        "opafrutas"				=> "Opafrutas",
        "pkf_medeiros"			=> "PKF Medeiros",
        "qualy_transportes"		=> "Qualy Transportes",
        "techps"				=> "Techps",
        "trampolim_gas"			=> "Trampolim Gás",
    ];

    $dominio_array = array_keys($dominios); //Utilizado nos arquivos que importam este.

    if(empty($_POST["dominio"]) && !empty($_GET["dominio"])){
        $_POST["dominio"] = $_GET["dominio"];
    }

    $dominiosInput = "<div class='form-group'>
        <select class='form-control' name='dominio'>
            <option value='' hidden selected>Domínio</option>";
    foreach($dominios as $key => $value){
        if((!empty($_POST["dominio"]) && $_POST["dominio"] == "/".$key."/index.php") || $key==$_ENV["APP_PATH"]){
            $selected = "selected";
        }else{
            $selected = "";
        }
        $file = $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/".$key."/index.php";
    
        if(file_exists($file)){
            $dominiosInput .= "<option value='"."/".$key."/index.php' ".$selected.">".$value."</option>";
        }
    }

    $dominiosInput .= "</select></div>";
?>