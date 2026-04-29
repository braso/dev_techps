<?php

    include "load_env.php";

    $empresas = [
        "ARMPB"		            => "armazem_paraiba",
        "BLRD"			        => "blueroad",
        "BRASO"					=> "braso",
        "CARAU"		            => "carau_transporte",
        "COMAV"					=> "comav",
        "FTQZA"		            => "feijao_turqueza",
        "FSLOG"	                => "fs_log_transportes",
        "HN"                    => "hn_transportes",
        "IFRN"                  => "ifrn",
        "JRJ"	                => "jrj_organizacao",
        "LOGSYNC"	            => "logsync_techps",
        "LEMON"                 => 'lemon',
        "NH"	                => "nh_transportes",
        "OPAFTS"				=> "opafrutas",
        "PKFMS"			        => "pkf_medeiros",
        "QUALY"		            => "qualy_transportes",
        "HSLC"                  => "sao_lucas",       
        "TECHPS"				=> "techps",
        "DEMO"			        => "techps_demo",
        "TRGS"			        => "trampolim_gas",
        "TRSCPL"			    => "transcopel",
        "PBTRS"		            => "pb_transportes",
        "ODTGA"		            => "odontotangara",
        "CLGRL"		            => "clinica_gerlane",
        "IROL"		            => "iraneide_oliveira",
        "MDTAL"		            => "midia_digital",
        "ENOVE"                 => "enove",
        "TMILT"                 => "t_militao",
        "LAUTO"                 => "lauto"

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
        "pb_transportes" 	=> "PB Transportes",
        "odontotangara" 	=> "Odonto Tangara",
        "clinica_gerlane" 	=> "Clínica Gerlane",
        "iraneide_oliveira" => "Iraneide Oliveira",
        "midia_digital"     => "Midia Digital",
        "enove"             => "Enove",
        "t_militao"         => "T Militao",
        
        "lauto"             => "L Auto Cargo"
    ];

    $empresa_array = array_values($empresas); //Utilizado nos arquivos que importam este.

    if(empty($_POST["empresa"]) && !empty($_GET["empresa"])){
        $_POST["empresa"] = $_GET["empresa"];
    }

    $empresasInput = 
        "<div class='form-group'>
            <input
                autofocus
                class='input-empresas form-control form-control-solid placeholder-no-fix'
                type='text'
                autocomplete='off'
                placeholder='INSIRA SEU DOMÍNIO'
                name='empresa'
                style='text-transform:uppercase;'
                ".(!empty($_POST["empresa"])? "value='".$_POST["empresa"]."'": "")."
            />
        </div>"
    ;
?>