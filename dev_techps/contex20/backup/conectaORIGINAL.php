<?
global $CONTEX;

$CONTEX[path] = "/contex20";


/* INICIO CONEXAO BASE DE DADOS */
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "serverside";

$conn = mysqli_connect($servername, $username, $password, $dbname) or die("Connection failed: " . mysqli_connect_error());
$conn->set_charset("utf8");
/* FIM CONEXAO BASE DE DADOS */


include_once "funcoes_form.php";
include_once "funcoes.php";
include_once "funcoes_grid.php";

