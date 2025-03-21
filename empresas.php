<?php

    include "load_env.php";

    $empresas = [
        "ARMAZEMPARAIBA"		=> "armazem_paraiba",
        "BRASO"					=> "braso",
        "CARAU"		            => "carau_transporte",
        "COMAV"					=> "comav",
        "FEIJAOTURQUEZA"		=> "feijao_turqueza",
        "FSLOG"	                => "fs_log_transportes",
        "HN"                    => "hn_transportes",
        "JRJ"	                => "jrj_organizacao",
        "LOGSYNC"	            => "logsync_techps",
        "NH"	                => "nh_transportes",
        "OPAFRUTAS"				=> "opafrutas",
        "PKFMEDEIROS"			=> "pkf_medeiros",
        "QUALY"		            => "qualy_transportes",
        "TECHPS"				=> "techps",
        "TRAMPOLIMGAS"			=> "trampolim_gas"
    ];

    $empresa_array = array_values($empresas); //Utilizado nos arquivos que importam este.

    if(empty($_POST["empresa"]) && !empty($_GET["empresa"])){
        $_POST["empresa"] = $_GET["empresa"];
    }

    $empresasInput = 
        "<div class='form-group'>
            <input
                focus
                autofocus
                class='input-empresas form-control form-control-solid placeholder-no-fix'
                type='text'
                autocomplete='off'
                placeholder='Empresa'
                name='empresa'
                ".(!empty($_POST["empresa"])? 'value='.$_POST["empresa"]: "")."
            />
        </div>"
    ;
?>