<?php
	include "conecta.php";

	global $CONTEX;

	$_SESSION = [];
    session_destroy();
?>
<meta http-equiv="refresh" content="0; url=./../index2.php" />