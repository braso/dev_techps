<?php

include "load_env.php";

if(isset($_SERVER['SCRIPT_URI'])){
	$server_base_link = substr($_SERVER['SCRIPT_URI'], 0, strrpos($_SERVER['SCRIPT_URI'], '/'));
}else{
	$server_base_link = substr($_SERVER['SCRIPT_FILENAME'], 0, strrpos($_SERVER['SCRIPT_FILENAME'], '/'));
}

$dominios = [
    "armazem_paraiba"   => 'Armazem Paraíba',
    "braso"             => 'Braso',
    "carau_transporte"  => 'Carau Transportes',
    "feijao_turqueza"   => 'Feijão Turqueza',
    "opafrutas"         => 'Opafrutas',
    "qualy_transportes" => 'Qualy Transportes',
    "techps"            => 'Techps',
    "apx_solucoes"      => 'Fs Log Transportes',
];

$dominio_array = array_keys($dominios);

$dominiosInput = "<div class='form-group'>
    <select class='form-control' name='dominio'>
        <option value='' hidden selected>Domínio</option>";
foreach($dominios as $key => $value){
    if((!empty($_POST['dominio']) && $_POST['dominio'] == "/".$key."/index.php") || $key==$_ENV["APP_PATH"]){
        $selected = 'selected';
    }else{
        $selected = '';
    }
    $file = $_SERVER["DOCUMENT_ROOT"]."/".$key."/index.php";
  
    if(file_exists($file)){
        $dominiosInput .= "<option value='"."/".$key."/index.php' ".$selected.">".$value."</option>";
    }
}

$dominiosInput .= "</select></div>";
?>