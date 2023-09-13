<?

global $CONTEX,$conn;

session_start();
// error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

date_default_timezone_set('America/Fortaleza');

$CONTEX['path'] = "/techps/sistema";

/* INICIO CONEXAO BASE DE DADOS */

$servername = "localhost";

$username = "brasomo_dev_techps_sistema";
$password = "techps!sistema";
$dbname = "brasomo_dev_techps_sistema";


// $servername = "localhost";
// $username = "techps_sistema";
// $password = "techps!sistema";
// $dbname = "techps_sistema";


$conn = mysqli_connect($servername, $username, $password, $dbname) or die("Connection failed: " . mysqli_connect_error());
$conn->set_charset("utf8");

/* FIM CONEXAO BASE DE DADOS */

// Alterado em 06/09/2023, caso não haja um erro maior em um mês, esse comentário pode ser deletado
// include_once $_SERVER['DOCUMENT_ROOT']."/techps/contex20/funcoes_grid.php";
// include_once $_SERVER['DOCUMENT_ROOT']."/techps/contex20/funcoes_form.php";
// include_once $_SERVER['DOCUMENT_ROOT']."/techps/contex20/funcoes.php";

include_once (getcwd()."/../")."contex20/funcoes_grid.php";
include_once (getcwd()."/../")."contex20/funcoes_form.php";
include_once (getcwd()."/../")."contex20/funcoes.php";