<?php
$interno = true;
include 'armazem_paraiba/conecta.php';
$res = query('DESCRIBE parametro');
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . "\n";
}
?>