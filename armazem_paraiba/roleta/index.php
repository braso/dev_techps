<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/db.php';
$modoLivre = roleta_cfg($conn, 'modo_livre', '0') === '1';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Roleta Techps</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="roleta-body">
  <header class="topbar">
    <div class="brand">Roleta <span>Techps</span></div>
    <nav>
      <a href="../index.php">Início</a>
    </nav>
  </header>
  <main class="roleta-main">
    <section class="hero">
      <h1>🎡 Roleta de Prêmios Techps</h1>
      <p>Gire e concorra a brindes exclusivos! Valide seu código abaixo e tente a sorte.</p>
    </section>

    <div class="wheel-section">
      <div class="wheel-wrap">
        <div class="pointer">▼</div>
        <canvas id="wheel" width="520" height="520"></canvas>
        <button class="spin-btn" id="spinBtn" disabled>GIRAR</button>
      </div>
      <div id="resultado" class="resultado hidden">
        <div class="resultado-inner">
          <h2>🎉 Você ganhou!</h2>
          <img id="resFoto" src="" alt="" class="res-foto">
          <div id="resNome" class="res-nome"></div>
          <button class="btn btn-primary" id="btnFecharResultado">Continuar</button>
        </div>
      </div>
    </div>

    <div class="code-panel" id="codeBox">
      <div class="welcome hidden" id="welcomeBox"></div>
      <h2>Digite seu código de liberação</h2>
      <div class="code-row">
        <input type="text" id="codigoInput" placeholder="Ex: ABCD1234" autocomplete="off">
        <button class="btn btn-primary" id="btnValidar">Validar</button>
      </div>
      <p id="codeMsg" class="msg"></p>
      <?php if ($modoLivre): ?><p class="muted small">Modo livre habilitado: você pode girar sem código.</p><?php endif; ?>
      <div class="info-bar">
        <span>Giros restantes: <strong id="girosRestantes">-</strong></span>
        <button class="btn btn-secondary btn-small" id="btnTrocarCodigo">Trocar código</button>
      </div>
    </div>
  </main>
  <script>window.ROLETA_API = 'api.php'; window.ROLETA_MODO_LIVRE = <?php echo $modoLivre?'true':'false'; ?>;</script>
  <script src="roleta.js"></script>
</body>
</html>