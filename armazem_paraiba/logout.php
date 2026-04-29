<?php
    session_start();

    // Pega a empresa salva na sessão antes de destruir
    $empresaKey = $_SESSION["empresa_key"] ?? "";

    $_SESSION = [];
    session_destroy();

    $queryEmpresa = $empresaKey !== "" ? "?empresa=" . urlencode($empresaKey) : "";
?>
<form name='logoutForm' action='../index.php<?= $queryEmpresa ?>' method='post'>
    <input type='hidden' name='sourcePage' value='<?= htmlspecialchars($_POST["sourcePage"] ?? "") ?>'>
</form>
<script>document.logoutForm.submit();</script>
