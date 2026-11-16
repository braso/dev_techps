<?php
require_once __DIR__ . '/bootstrap.php';

verificaPermissao('/whatsapp/contatos.php');

$busca = trim(strval($_GET['busca'] ?? ''));
$empresa = (int)($_GET['empresa'] ?? 0);
$setor = trim(strval($_GET['setor'] ?? ''));
$cargo = trim(strval($_GET['cargo'] ?? ''));
$somenteOptin = !empty($_GET['somente_optin']);

$contatos = wa_contatos_filtrados([
    'busca' => $busca,
    'empresa' => $empresa,
    'setor' => $setor,
    'cargo' => $cargo,
    'somente_optin' => $somenteOptin,
], 500);

$total = count($contatos);
$comWhatsapp = 0;
$optin = 0;
foreach ($contatos as $c) {
    if (trim(strval($c['cont_tx_e164'] ?? '')) !== '') {
        $comWhatsapp++;
    }
    if (strval($c['cont_tx_optin'] ?? 'nao') === 'sim') {
        $optin++;
    }
}

$empresas = wa_listar_empresas();
$setores = wa_listar_setores();
$cargos = wa_listar_cargos();
$ultimaSync = wa_meta_ler($conn, 'ultima_sincronizacao_contatos');

cabecalho('Contatos - CRM - TECHPS');
?>
<link rel="stylesheet" href="<?= wa_e($CONTEX['path']) ?>/whatsapp/assets/whatsapp.css?v=2">
<style>
.wa-contatos-top { display:flex; flex-wrap:wrap; gap:12px; align-items:stretch; margin-bottom:14px; }
.wa-stat { flex:1 1 160px; background:#fff; border:1px solid #e4e9ef; border-radius:14px; padding:14px 16px; box-shadow:0 2px 6px rgba(15,23,42,.04); }
.wa-stat small { display:block; font-size:10px; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; font-weight:800; }
.wa-stat strong { display:block; font-size:24px; color:#0f172a; line-height:1.2; margin-top:2px; }
.wa-filtros-card { background:#fff; border:1px solid #e4e9ef; border-radius:14px; padding:14px 16px; margin-bottom:14px; box-shadow:0 2px 6px rgba(15,23,42,.04); }
.wa-filtros-card .row { margin-bottom:0; }
.wa-tabela-card { background:#fff; border:1px solid #e4e9ef; border-radius:14px; overflow:hidden; box-shadow:0 2px 6px rgba(15,23,42,.04); }
.wa-tabela-head { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; padding:13px 16px; border-bottom:1px solid #eef2f6; }
.wa-tabela-head h3 { margin:0; font-size:15px; font-weight:800; color:#0f172a; }
.wa-tabela { width:100%; border-collapse:collapse; font-size:12px; }
.wa-tabela th { background:#f8fafc; color:#64748b; font-size:9.5px; text-transform:uppercase; letter-spacing:.8px; text-align:left; padding:9px 12px; border-bottom:1px solid #e5e7eb; white-space:nowrap; }
.wa-tabela td { padding:9px 12px; border-bottom:1px solid #f4f7fa; vertical-align:middle; }
.wa-tabela tr:hover td { background:#fbfcfe; }
.wa-tabela .wa-nome { font-weight:700; color:#1e293b; }
.wa-tabela .wa-sub { font-size:10px; color:#94a3b8; }
.wa-chip { display:inline-block; border-radius:999px; padding:2px 8px; font-size:9.5px; font-weight:800; }
.wa-chip-sim { background:#dcfce7; color:#15803d; }
.wa-chip-nao { background:#f1f5f9; color:#64748b; }
.wa-chip-sem { background:#fef3c7; color:#92400e; }
.wa-chip-origem { background:#e0f2fe; color:#075985; }
.wa-acoes { display:flex; gap:8px; align-items:center; }
.wa-acoes a, .wa-acoes button { color:#64748b; background:none; border:0; padding:0; cursor:pointer; font-size:14px; }
.wa-acoes a:hover, .wa-acoes button:hover { color:#16a34a; }
.wa-vazio { padding:34px 20px; text-align:center; color:#94a3b8; font-size:12.5px; }
.wa-check { width:15px; height:15px; cursor:pointer; }
</style>

<div class="wa-filtros-card">
    <form method="get" action="contatos.php">
        <div class="row">
            <div class="col-sm-4">
                <label>Buscar</label>
                <input type="text" class="form-control input-sm" name="busca" value="<?= wa_e($busca) ?>" placeholder="Nome, telefone ou e-mail">
            </div>
            <div class="col-sm-3">
                <label>Empresa</label>
                <select class="form-control input-sm" name="empresa">
                    <option value="0">Todas</option>
                    <?php foreach ($empresas as $e): ?>
                        <option value="<?= (int)$e['id'] ?>" <?= $empresa === (int)$e['id'] ? 'selected' : '' ?>><?= wa_e($e['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label>Setor</label>
                <select class="form-control input-sm" name="setor">
                    <option value="">Todos</option>
                    <?php foreach ($setores as $s): ?>
                        <option value="<?= wa_e($s) ?>" <?= $setor === $s ? 'selected' : '' ?>><?= wa_e($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label>Cargo</label>
                <select class="form-control input-sm" name="cargo">
                    <option value="">Todos</option>
                    <?php foreach ($cargos as $cg): ?>
                        <option value="<?= wa_e($cg) ?>" <?= $cargo === $cg ? 'selected' : '' ?>><?= wa_e($cg) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-1" style="display:flex;align-items:flex-end;gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm" title="Filtrar"><i class="fa fa-search"></i></button>
                <a href="contatos.php" class="btn btn-default btn-sm" title="Limpar"><i class="fa fa-eraser"></i></a>
            </div>
        </div>
        <label style="font-weight:normal;margin-top:8px;font-size:11.5px;">
            <input type="checkbox" name="somente_optin" value="1" <?= $somenteOptin ? 'checked' : '' ?>> Somente contatos com opt-in
        </label>
    </form>
</div>

<div class="wa-contatos-top">
    <div class="wa-stat"><small>Contatos no filtro</small><strong><?= $total ?></strong></div>
    <div class="wa-stat"><small>Com WhatsApp válido</small><strong><?= $comWhatsapp ?></strong></div>
    <div class="wa-stat"><small>Com opt-in</small><strong><?= $optin ?></strong></div>
    <div class="wa-stat" style="flex:1 1 240px;">
        <small>Última sincronização</small>
        <strong style="font-size:14px;"><?= $ultimaSync ? wa_e(date('d/m/Y H:i', strtotime($ultimaSync))) : 'Nunca' ?></strong>
        <button type="button" class="btn btn-success btn-xs" id="waSincronizar" style="margin-top:6px;">
            <i class="fa fa-refresh"></i> Sincronizar contatos
        </button>
    </div>
</div>

<div class="wa-tabela-card">
    <div class="wa-tabela-head">
        <h3><i class="fa fa-address-book-o"></i> Contatos</h3>
        <div style="display:flex;gap:8px;align-items:center;">
            <span class="text-muted" style="font-size:11px;" id="waSelecionados">0 selecionado(s)</span>
            <button type="button" class="btn btn-success btn-sm" id="waEnviarLote" disabled>
                <i class="fa fa-send"></i> Enviar em lote
            </button>
        </div>
    </div>

    <?php if (empty($contatos)): ?>
        <div class="wa-vazio">
            <i class="fa fa-address-book-o" style="font-size:28px;display:block;margin-bottom:8px;color:#cbd5e1;"></i>
            Nenhum contato encontrado. Clique em <strong>Sincronizar contatos</strong> para importar funcionários e usuários.
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="wa-tabela">
                <thead>
                    <tr>
                        <th style="width:34px;"><input type="checkbox" class="wa-check" id="waCheckTodos"></th>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>Empresa / Setor</th>
                        <th>Cargo</th>
                        <th>Origem</th>
                        <th>Opt-in</th>
                        <th style="width:70px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contatos as $c): ?>
                        <?php
                        $e164 = trim(strval($c['cont_tx_e164'] ?? ''));
                        $telefone = $e164 !== '' ? wa_formatar_telefone($e164) : strval($c['cont_tx_telefone'] ?? '');
                        $semNumero = $e164 === '';
                        ?>
                        <tr>
                            <td><input type="checkbox" class="wa-check wa-check-contato" value="<?= (int)$c['cont_nb_id'] ?>" <?= $semNumero ? 'disabled' : '' ?>></td>
                            <td>
                                <div class="wa-nome"><?= wa_e($c['cont_tx_nome'] ?? '') ?></div>
                                <div class="wa-sub"><?= wa_e($c['cont_tx_setor'] ?? '') ?></div>
                            </td>
                            <td>
                                <?php if ($semNumero): ?>
                                    <span class="wa-chip wa-chip-sem">Sem WhatsApp</span>
                                <?php else: ?>
                                    <?= wa_e($telefone) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= wa_e($c['cont_tx_empresa_nome'] ?? '') ?></div>
                                <div class="wa-sub"><?= wa_e($c['cont_tx_setor'] ?? '') ?></div>
                            </td>
                            <td><?= wa_e($c['cont_tx_cargo'] ?? '') ?></td>
                            <td><span class="wa-chip wa-chip-origem"><?= wa_e(ucfirst($c['cont_tx_origem'] ?? '')) ?></span></td>
                            <td>
                                <span class="wa-chip <?= ($c['cont_tx_optin'] ?? 'nao') === 'sim' ? 'wa-chip-sim' : 'wa-chip-nao' ?>">
                                    <?= ($c['cont_tx_optin'] ?? 'nao') === 'sim' ? 'Sim' : 'Não' ?>
                                </span>
                            </td>
                            <td>
                                <div class="wa-acoes">
                                    <?php if (!$semNumero): ?>
                                        <a href="index.php?contato=<?= (int)$c['cont_nb_id'] ?>" title="Abrir conversa"><i class="fa fa-comments"></i></a>
                                        <button type="button" class="wa-copiar" data-telefone="<?= wa_e($telefone) ?>" title="Copiar telefone"><i class="fa fa-copy"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
window.WA_CONTATOS = {
    api: <?= wa_json_script(wa_e($CONTEX['path']) . '/whatsapp/api.php') ?>,
    csrf: <?= wa_json_script(wa_csrf_token()) ?>,
    urlLote: <?= wa_json_script(wa_e($CONTEX['path']) . '/whatsapp/lote.php') ?>
};
</script>
<script>
(function () {
    var checkTodos = document.getElementById('waCheckTodos');
    var checks = function () { return Array.prototype.slice.call(document.querySelectorAll('.wa-check-contato:not(:disabled)')); };
    var label = document.getElementById('waSelecionados');
    var botao = document.getElementById('waEnviarLote');

    function selecionados() { return checks().filter(function (c) { return c.checked; }).map(function (c) { return c.value; }); }
    function atualizar() {
        var total = selecionados().length;
        if (label) label.textContent = total + ' selecionado(s)';
        if (botao) botao.disabled = total === 0;
    }

    if (checkTodos) {
        checkTodos.addEventListener('change', function () {
            checks().forEach(function (c) { c.checked = checkTodos.checked; });
            atualizar();
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('wa-check-contato')) atualizar();
    });

    if (botao) {
        botao.addEventListener('click', function () {
            var ids = selecionados();
            if (!ids.length) return;
            window.location.href = WA_CONTATOS.urlLote + '?ids=' + encodeURIComponent(ids.join(','));
        });
    }

    document.querySelectorAll('.wa-copiar').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tel = btn.getAttribute('data-telefone') || '';
            if (navigator.clipboard) {
                navigator.clipboard.writeText(tel).then(function () {
                    var old = btn.innerHTML;
                    btn.innerHTML = '<i class="fa fa-check"></i>';
                    setTimeout(function () { btn.innerHTML = old; }, 1200);
                });
            }
        });
    });

    var sincronizar = document.getElementById('waSincronizar');
    if (sincronizar) {
        sincronizar.addEventListener('click', function () {
            sincronizar.disabled = true;
            var original = sincronizar.innerHTML;
            sincronizar.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sincronizando...';
            fetch(WA_CONTATOS.api + '?wa_acao=sincronizar_contatos', {
                method: 'POST',
                headers: { 'X-CSRF-Token': WA_CONTATOS.csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'csrf=' + encodeURIComponent(WA_CONTATOS.csrf)
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Falha ao sincronizar.');
                    sincronizar.disabled = false;
                    sincronizar.innerHTML = original;
                }
            }).catch(function () {
                alert('Falha de comunicação.');
                sincronizar.disabled = false;
                sincronizar.innerHTML = original;
            });
        });
    }
})();
</script>
<?php rodape(); ?>
