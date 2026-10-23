<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/db.php';
include_once dirname(__DIR__) . '/conecta.php';

$nivel = $_SESSION['user_tx_nivel'] ?? '';
if (stripos($nivel, 'Super Administrador') === false) {
    http_response_code(403);
    echo '<h1>Acesso negado</h1><p>Apenas Super Administradores podem acessar a administração da Roleta.</p>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel Admin - Roleta Techps</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="topbar">
    <div class="brand">Roleta <span>Techps — Admin</span></div>
    <nav>
      <a href="index.php" target="_blank">Ver Roleta</a>
      <a href="../index.php">Início</a>
    </nav>
  </header>
  <main class="container">
    <!-- same tabs as before -->
    <div class="tabs">
      <button class="tab active" data-tab="dashboard">Dashboard</button>
      <button class="tab" data-tab="premios">Prêmios</button>
      <button class="tab" data-tab="leads">Cadastros / Leads</button>
      <button class="tab" data-tab="codigos">Códigos de Liberação</button>
      <button class="tab" data-tab="historico">Histórico</button>
      <button class="tab" data-tab="config">Configurações</button>
    </div>
    <!-- dashboard -->
    <section id="tab-dashboard" class="tabpane active">
      <h2>Dashboard</h2>
      <div class="cards">
        <div class="card"><div class="card-label">Prêmios cadastrados</div><div class="card-value" id="st-premios">-</div></div>
        <div class="card"><div class="card-label">Estoque total</div><div class="card-value" id="st-estoque">-</div></div>
        <div class="card"><div class="card-label">Giros hoje</div><div class="card-value" id="st-hoje">-</div></div>
        <div class="card"><div class="card-label">Giros total</div><div class="card-value" id="st-total">-</div></div>
        <div class="card"><div class="card-label">Códigos ativos</div><div class="card-value" id="st-codigos">-</div></div>
        <div class="card"><div class="card-label">Cadastros (leads)</div><div class="card-value" id="st-leads">-</div></div>
      </div>
      <button class="btn btn-secondary" onclick="carregarStats()">Atualizar</button>
    </section>
    <!-- premios -->
    <section id="tab-premios" class="tabpane">
      <div class="section-head"><h2>Prêmios</h2><button class="btn btn-primary" onclick="abrirFormPremio()">+ Novo Prêmio</button></div>
      <form id="formPremio" class="form-card hidden">
        <h3 id="formPremioTitulo">Novo Prêmio</h3>
        <input type="hidden" id="premioId">
        <div class="grid2">
          <div><label>Nome (chamativo)</label><input type="text" id="pNome" required></div>
          <div><label>Cor do setor</label><input type="color" id="pCor" value="#30a0b8"></div>
          <div><label>Peso (probabilidade)</label><input type="number" id="pPeso" min="1" value="1"></div>
          <div><label>Quantidade (estoque)</label><input type="number" id="pQtd" min="0" value="0"></div>
          <div><label>Foto</label><input type="file" id="pFoto" accept="image/*"></div>
          <div><label>Ativo</label><select id="pAtivo"><option value="1">Sim</option><option value="0">Não</option></select></div>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Salvar</button><button type="button" class="btn btn-secondary" onclick="fecharFormPremio()">Cancelar</button></div>
      </form>
      <div class="table-wrap"><table><thead><tr><th>Foto</th><th>Código</th><th>Nome</th><th>Cor</th><th>Peso</th><th>%</th><th>Estoque</th><th>Ativo</th><th>Ações</th></tr></thead><tbody id="tbodyPremios"></tbody></table></div>
    </section>
    <!-- leads -->
    <section id="tab-leads" class="tabpane">
      <div class="section-head"><h2>Cadastros (Leads)</h2><button class="btn btn-primary" onclick="abrirFormLead()">+ Novo Cadastro</button></div>
      <form id="formLead" class="form-card hidden">
        <h3>Novo Cadastro (gera código)</h3>
        <div class="grid2">
          <div><label>Nome completo *</label><input type="text" id="lNome" required></div>
          <div><label>E-mail</label><input type="email" id="lEmail"></div>
          <div><label>Telefone</label><input type="text" id="lTelefone"></div>
          <div><label>Empresa</label><input type="text" id="lEmpresa"></div>
          <div><label>Giros</label><input type="number" id="lGiros" min="1" value="1"></div>
          <div><label>Expira em</label><input type="datetime-local" id="lExpira"></div>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Cadastrar e Gerar Código</button><button type="button" class="btn btn-secondary" onclick="fecharFormLead()">Cancelar</button></div>
        <p id="leadMsg" class="msg"></p>
      </form>
      <div class="table-wrap"><table><thead><tr><th>Nome</th><th>E-mail</th><th>Telefone</th><th>Empresa</th><th>Código</th><th>Prêmio(s)</th><th>Data</th><th>Ações</th></tr></thead><tbody id="tbodyLeads"></tbody></table></div>
    </section>
    <!-- codigos -->
    <section id="tab-codigos" class="tabpane">
      <div class="section-head"><h2>Códigos de Liberação</h2><button class="btn btn-primary" onclick="abrirFormCodigo()">+ Gerar Código</button></div>
      <form id="formCodigo" class="form-card hidden">
        <h3>Novo Código</h3>
        <div class="grid2">
          <div><label>Giros</label><input type="number" id="cGiros" min="1" value="1"></div>
          <div><label>Expira em</label><input type="datetime-local" id="cExpira"></div>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Gerar</button><button type="button" class="btn btn-secondary" onclick="fecharFormCodigo()">Cancelar</button></div>
      </form>
      <div class="table-wrap"><table><thead><tr><th>Código</th><th>Dono</th><th>Giros</th><th>Usados</th><th>Restantes</th><th>Expira</th><th>Ativo</th><th>Ações</th></tr></thead><tbody id="tbodyCodigos"></tbody></table></div>
    </section>
    <!-- historico -->
    <section id="tab-historico" class="tabpane">
      <div class="section-head"><h2>Histórico de Giros</h2><button class="btn btn-secondary" onclick="carregarHistorico()">Atualizar</button></div>
      <div class="table-wrap"><table><thead><tr><th>#</th><th>Data/Hora</th><th>Código</th><th>Lead</th><th>Prêmio</th></tr></thead><tbody id="tbodyHistorico"></tbody></table></div>
    </section>
    <!-- config -->
    <section id="tab-config" class="tabpane">
      <h2>Configurações</h2>
      <div class="form-card">
        <label class="checkbox"><input type="checkbox" id="cfgModoLivre"> Modo livre (sem código)</label>
        <div class="form-actions"><button class="btn btn-primary" onclick="salvarConfig()">Salvar</button></div>
        <p id="cfgMsg" class="msg"></p>
      </div>
    </section>
  </main>
  <script>window.ROLETA_API = 'api.php';</script>
  <script src="admin.js"></script>
</body>
</html>