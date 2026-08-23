<?php
/* ============================================================
   Torre de Comando — tela própria (acesso direto via menu).
   O motor de dados e o HTML ficam em torre_comando.php, que
   também é usado direto na tela de boas-vindas pós-login
   (index.php::showWelcome()), sem exigir navegação nenhuma.
   ============================================================ */

include "conecta.php";

// ARQUIVO QUE VALIDA A PERMISSAO VIA PERFIL DE USUARIO VINCULADO
include "check_permission.php";
verificaPermissao('/dashboard.php');

include_once "torre_comando.php";

cabecalho("");
renderTorreDeComando();
rodape();
