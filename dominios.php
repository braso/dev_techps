<?php
include_once "load_env.php";


if($_ENV['URL_BASE']){
    $server_base_link = $_ENV['URL_BASE'];
}
elseif(isset($_SERVER['SCRIPT_URI'])){
	$server_base_link = substr($_SERVER['SCRIPT_URI'], 0, strrpos($_SERVER['SCRIPT_URI'], '/'));
    
}else{
	$server_base_link = substr($_SERVER['SCRIPT_FILENAME'], 0, strrpos($_SERVER['SCRIPT_FILENAME'], '/'));
}

$dominios = [
    "techps"            => 'Techps',
    "braso"             => 'Braso',
    "armazem_paraiba"   => 'Armazem Paraíba', 
    "opafrutas"         => 'Opafrutas',
    "qualy_transportes" => 'Qualy Transportes',
    "feijao_turqueza"   => 'Feijão Turqueza'
];

$dominiosInput = "<div class='form-group'>
    <select class='form-control' name='dominio'>
        <option value='' hidden selected>Domínio</option>";
foreach($dominios as $key => $value){
    if(!empty($_POST['dominio']) && $_POST['dominio'] == $server_base_link."/".$key."/index.php"){
        $selected = 'selected';
    }else{
        $selected = '';
    }

    $dominiosInput .= "<option value='".$server_base_link."/".$key."/index.php' ".$selected.">".$value."</option>";
}

$dominiosInput .= "</select></div>";
?>
