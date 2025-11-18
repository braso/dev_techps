<?php

    include "load_env.php";

    $empresas = [
        "ARMAZEMPARAIBA"		=> "armazem_paraiba",
        "BLUEROAD"			    => "blueroad",
        "BRASO"					=> "braso",
        "CARAU"		            => "carau_transporte",
        "COMAV"					=> "comav",
        "FEIJAOTURQUEZA"		=> "feijao_turqueza",
        "FSLOG"	                => "fs_log_transportes",
        "HN"                    => "hn_transportes",
        "IFRN"                  => "ifrn",
        "JRJ"	                => "jrj_organizacao",
        "LOGSYNC"	            => "logsync_techps",
        "LEMON"                 => 'lemon',
        "NH"	                => "nh_transportes",
        "OPAFRUTAS"				=> "opafrutas",
        "PKFMEDEIROS"			=> "pkf_medeiros",
        "QUALY"		            => "qualy_transportes",
        "SÃO LUCAS"             => "sao_lucas",       
        "TECHPS"				=> "techps",
        "DEMO"			        => "techps_demo",
        "TRAMPOLIMGAS"			=> "trampolim_gas",
        "TRANSCOPEL"			=> "transcopel",
    ];

    $empresasNomes = [
        "armazem_paraiba" 	=> "Armazem Paraíba",
        "blueroad"      	=> "Blue Road",
        "braso" 			=> "Braso",
        "carau_transporte" 	=> "Caraú Transportes",
        "comav" 			=> "COMAV",
        "feijao_turqueza" 	=> "Feijão Turqueza",
        "fs_log_transportes"=> "FS Log Transportes",
        "hn_transportes" 	=> "HN Transportes",
        "ifrn"              => "IFRN",
        "jrj_organizacao" 	=> "JRJ Organização",
        "logsync_techps" 	=> "Logsync - TechPS",
        "lemon"             => "Lemon",
        "nh_transportes" 	=> "NH Transportes",
        "opafrutas" 		=> "Opafrutas",
        "pkf_medeiros" 		=> "PKF Medeiros",
        "qualy_transportes" => "Qualy Transportes",
        "sao_lucas" 	    => "São Lucas",
        "techps" 			=> "TechPS",
        "techps_demo" 		=> "TechPS (Demo)",
        "trampolim_gas" 	=> "Trampolim Gás",
        "transcopel" 	    => "Transcopel",
    ];

    $empresa_array = array_values($empresas); //Utilizado nos arquivos que importam este.

    if(empty($_POST["empresa"]) && !empty($_GET["empresa"])){
        $_POST["empresa"] = $_GET["empresa"];
    }

    $empresasInput = 
        "<div class='form-group'>
            <select name='empresa' class='input-empresas form-control form-control-solid placeholder-no-fix' autofocus>
                <option value='' hidden>Empresa</option>"
    ;
    foreach($empresas as $key => $value){
        if(file_exists(__DIR__."/".$value)){
            $empresasInput .= "<option ".((!empty($_POST["empresa"]) && $_POST["empresa"] == $key)? "selected": "")." value='{$key}'>{$empresasNomes[$value]}</option>";
        }
    }
    $empresasInput .= "</select></div>";

    // $empresasInput = 
    //     "<div class='form-group'>
    //         <input
    //             focus
    //             autofocus
    //             class='input-empresas form-control form-control-solid placeholder-no-fix'
    //             type='text'
    //             autocomplete='off'
    //             placeholder='Empresa'
    //             name='empresa'
    //             ".(!empty($_POST["empresa"])? 'value='.$_POST["empresa"]: "")."
    //         />
    //     </div>"
    // ;
?>