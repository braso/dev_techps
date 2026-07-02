<?php

    include "load_env.php";

    // true  = exibe SELECT com lista de empresas
    // false = exibe INPUT para digitar a abreviação do domínio
    define('LOGIN_EMPRESA_SELECT', true);

    $empresas = [
        "ARMPB"		            => "armazem_paraiba",
        "BLRD"			        => "blueroad",
        "BRASO"					=> "braso",
        "CARAU"		            => "carau_transporte",
        "COMAV"					=> "comav",
        "FTQZA"		            => "feijao_turqueza",
        "FSLOG"	                => "fs_log_transportes",
        "HNTP"                  => "hn_transportes",
        "IFRN"                  => "ifrn",
        "JRJ"	                => "jrj_organizacao",
        "LOGSYNC"	            => "logsync_techps",
        "LEMON"                 => 'lemon',
        "NHO"	                => "nh_transportes",
        "OPAFRUTAS"				=> "opafrutas",
        "PKFMS"			        => "pkf_medeiros",
        "QUALY"		            => "qualy_transportes",
        "CSSL"                  => "sao_lucas",       
        "TECHPS"				=> "techps",
        "DEMO"			        => "techps_demo",
        "TPGAS"			        => "trampolim_gas",
        "TRANSCOPEL"			=> "transcopel",
        "PBTRANSP"		        => "pb_transportes",
        "ODTGA"		            => "odontotangara",
        "CLGRL"		            => "clinica_gerlane",
        "IROL"		            => "iraneide_oliveira",
        "MD"		            => "midia_digital",
        "ENOVE"                 => "enove",
        "TMILITAO"              => "t_militao",
        "LAUTO"                 => "lauto",
        "DEMO"                  => "demo",
        "GST"                   => "gst",
        "ARMAPLAST"             => "armaplast",
        "HARMONY VET"           => "harmonyvet",

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
        "qualy_transportes" =>  "Qualy Transportes",
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
        "lauto"             => "L Auto Cargo",
        "demo"              => "Demo",
        "gst"               => "GST",
        "armaplast"         => "ARMAPLAST",
        "harmonyvet"         => "HARMONY VET"
    ];

    $empresa_array = array_values($empresas); //Utilizado nos arquivos que importam este.

    if(empty($_POST["empresa"]) && !empty($_GET["empresa"])){
        $_POST["empresa"] = $_GET["empresa"];
    }

    if (LOGIN_EMPRESA_SELECT) {
        $empresasInput = "<div class='form-group'>
            <select name='empresa' class='input-empresas form-control form-control-solid placeholder-no-fix' autofocus>
                <option value='' hidden>Empresa</option>";
        foreach ($empresas as $key => $value) {
            if (file_exists(__DIR__."/".$value)) {
                $selected = (!empty($_POST["empresa"]) && $_POST["empresa"] == $key) ? "selected" : "";
                $empresasInput .= "<option {$selected} value='{$key}'>{$empresasNomes[$value]}</option>";
            }
        }
        $empresasInput .= "</select></div>";
    } else {
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
    }
?>