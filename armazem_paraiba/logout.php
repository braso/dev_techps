<?php
	session_start();
	$_SESSION = [];
    session_destroy();
?>

<form name='logoutForm' action='../index.php' method='post'>
	<input type='text' name='sourcePage' value='<?=($_POST["sourcePage"]?? "")?>'>
</form>
<script>document.logoutForm.submit();</script>