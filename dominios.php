<?php 

if(isset($_SERVER['SCRIPT_URI'])){
	$server_base_link = substr($_SERVER['SCRIPT_URI'], 0, strrpos($_SERVER['SCRIPT_URI'], '/'));
}else{
	$server_base_link = substr($_SERVER['SCRIPT_FILENAME'], 0, strrpos($_SERVER['SCRIPT_FILENAME'], '/'));
}
include $server_base_link."/armazem_paraiba/conecta.php";

$dominio_array = [
    "techps",
    "braso",
    "armazem_paraiba",
    "opafrutas",
    "qualy_transportes",
    "feijao_turqueza"
];

$dominios = "<div class='form-group'>
    <select class='form-control' name='dominio'>
        <option value='' selected>Domínio</option>
        <option value='" . $server_base_link . "/techps/index.php'>Techps</option>
        <option value='" . $server_base_link . "/braso/index.php'>Braso</option>
        <option value='" . $server_base_link . "/armazem_paraiba/index.php'>Armazem Paraiba</option>
        <option value='" . $server_base_link . "/opafrutas/index.php'>Opafrutas</option>
        <option value='" . $server_base_link . "/qualy_transportes/index.php'>Qualy Transportes</option>
        <option value='" . $server_base_link . "/feijao_turqueza/index.php'>Feijão turqueza</option>
        <option value=''>Leroy Merlin</option>
    </select>
</div>";

?>
