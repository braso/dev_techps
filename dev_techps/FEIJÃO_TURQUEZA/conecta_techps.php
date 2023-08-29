<?
include '';
global $CONTEX,$conn;

// session_start();



// error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

// date_default_timezone_set('America/Fortaleza');



// $CONTEX['path'] = "/techps/sistema";



/* INICIO CONEXAO BASE DE DADOS */


$servername = "localhost";

$username = "brasomo_techps";

$password = "techps!sistema";

$dbname = "brasomo_techps";



$conn = mysqli_connect($servername, $username, $password, $dbname) or die("Connection failed: " . mysqli_connect_error());



$conn->set_charset("utf8");

$cnpj = $_POST['cnpj'];

$query = "SELECT * FROM `empresa` WHERE empr_tx_cnpj = '$cnpj'";

$sql = mysqli_query($conn,$query) or die(mysqli_error($conn));
if($debug=='1'){
    echo $query;
}

$result = mysqli_fetch_all($sql, MYSQLI_ASSOC);


echo json_encode($result);

