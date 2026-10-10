<?php
	session_start();
	$origemTotem = !empty($_SESSION["origem_totem"]);
	$_SESSION = [];
    session_destroy();
?>

<form name='logoutForm' action='<?=($origemTotem ? "totem_ponto.php" : "../index.php")?>' method='post'>
	<input type='hidden' name='sourcePage' value='<?=($_POST["sourcePage"]?? "")?>'>
</form>
<script>document.logoutForm.submit();</script>