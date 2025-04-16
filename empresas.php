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
        "TECHPSDEMO"			=> "techps_demo",
        "TRAMPOLIMGAS"			=> "trampolim_gas"
    ];

    $empresasNomes = [
        "armazem_paraiba" 	=> "Armazem Paraíba",
        "braso" 			=> "Braso",
        "carau_transporte" 	=> "Caraú Transportes",
        "comav" 			=> "COMAV",
        "feijao_turqueza" 	=> "Feijão Turqueza",
        "fs_log_transportes"=> "FS Log Transportes",
        "hn_transportes" 	=> "HN Transportes",
        "jrj_organizacao" 	=> "JRJ Organização",
        "logsync_techps" 	=> "Logsync - TechPS",
        "nh_transportes" 	=> "NH Transportes",
        "opafrutas" 		=> "Opafrutas",
        "pkf_medeiros" 		=> "PKF Medeiros",
        "qualy_transportes" => "Qualy Transportes",
        "techps" 			=> "TechPS",
        "techps_demo" 		=> "TechPS (Demo)",
        "trampolim_gas" 	=> "Trampolim Gás"
    ];

    $empresa_array = array_values($empresas); //Utilizado nos arquivos que importam este.

    if(empty($_POST["empresa"]) && !empty($_GET["empresa"])){
        $_POST["empresa"] = $_GET["empresa"];
    }

    $empresasInput = 
        "<div class='form-group'>
            <select name='empresa' class='input-empresas form-control form-control-solid placeholder-no-fix' autofocus>
                <option value='' hidden>Empresa</option>
                <option value='ARMAZEMPARAIBA'>Armazem Paraíba</option>
                <option value='BRASO'>Braso</option>
                <option value='CARAU'>Caraú Transportes</option>
                <option value='COMAV'>COMAV</option>
                <option value='FEIJAOTURQUEZA'>Feijão Turqueza</option>
                <option value='FSLOG'>FS Log Transportes</option>
                <option value='HN'>HN Transportes</option>
                <option value='JRJ'>JRJ Organização</option>
                <option value='LOGSYNC'>Logsync - TechPS</option>
                <option value='NH'>NH Transportes</option>
                <option value='OPAFRUTAS'>Opafrutas</option>
                <option value='PKFMEDEIROS'>PKF Medeiros</option>
                <option value='QUALY'>Qualy Transportes</option>
                <option value='TECHPS'>TechPS</option>
                <option value='TECHPSDEMO'>TechPS (Demo)</option>
                <option value='TRAMPOLIMGAS'>Trampolim Gás</option>
            </select>
        </div>"
    ;

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