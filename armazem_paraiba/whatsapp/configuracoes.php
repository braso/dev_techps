<?php
require_once __DIR__ . '/bootstrap.php';

verificaPermissao('/whatsapp/configuracoes.php');

$mensagem = '';
$erro = '';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['wa_form'] ?? '') === 'salvar') {
    if (!wa_csrf_validar(strval($_POST['csrf'] ?? ''))) {
        $erro = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        try {
            $providerPost = strval($_POST['provider'] ?? 'evolution');
            $dadosConfig = [
                'provider' => $providerPost,
                'status' => $_POST['status'] ?? 'inativo',
                'test_number' => $_POST['test_number'] ?? '',
                'retencao_dias' => $_POST['retencao_dias'] ?? 0,
            ];
            if ($providerPost === 'cloud') {
                $dadosConfig['base_url'] = $_POST['base_url_cloud'] ?? '';
                $dadosConfig['phone_number_id'] = $_POST['phone_number_id'] ?? '';
                $dadosConfig['waba_id'] = $_POST['waba_id'] ?? '';
                $dadosConfig['verify_token'] = $_POST['verify_token'] ?? '';
                $dadosConfig['token'] = $_POST['token_cloud'] ?? '';
                $dadosConfig['app_secret'] = $_POST['app_secret_cloud'] ?? '';
            } else {
                $dadosConfig['base_url'] = $_POST['base_url_evolution'] ?? '';
                $dadosConfig['instance'] = $_POST['instance'] ?? '';
                $dadosConfig['token'] = $_POST['token_evolution'] ?? '';
            }
            $resultado = wa_config_salvar($dadosConfig, (int)($_SESSION['user_nb_id'] ?? 0));
            if (!empty($resultado['success'])) {
                $mensagem = $resultado['message'];
                wa_log('config_atualizada', 'Configuração do provedor atualizada.', ['provider' => $_POST['provider'] ?? '']);
            } else {
                $erro = $resultado['message'] ?? 'Não foi possível salvar.';
            }
        } catch (Throwable $e) {
            wa_log('config_erro', $e->getMessage(), [], 'error');
            $erro = 'Não foi possível salvar a configuração: ' . $e->getMessage();
        }
    }
}

$cfg = wa_config(true);
$provider = strval($cfg['cfg_tx_provider'] ?? 'evolution');
$status = strval($cfg['cfg_tx_status'] ?? 'inativo');
$webhook = wa_webhook_url();
$logs = [];
$rsLogs = query("SELECT evt_nb_id, evt_tx_provider, evt_tx_tipo, evt_tx_status, evt_tx_erro, evt_dt_criacao FROM wa_evento ORDER BY evt_nb_id DESC LIMIT 20");
while ($rsLogs && ($row = mysqli_fetch_assoc($rsLogs))) {
    $logs[] = $row;
}
$tokenConfigurado = trim(strval($cfg['cfg_tx_token'] ?? '')) !== '';
$secretConfigurado = trim(strval($cfg['cfg_tx_app_secret'] ?? '')) !== '';

cabecalho('Configurações - CRM - TECHPS');
?>
<link rel="stylesheet" href="<?= wa_e($CONTEX['path']) ?>/whatsapp/assets/whatsapp.css?v=2">
<style>
.wa-config { background:#fff; border:1px solid #e4e9ef; border-radius:16px; box-shadow:0 4px 18px rgba(15,23,42,.06); overflow:hidden; }
.wa-config-head { padding:16px 20px; border-bottom:1px solid #eef2f6; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.wa-config-head h3 { margin:0; font-size:17px; font-weight:800; color:#0f172a; }
.wa-config-body { padding:18px 20px; }
.wa-config .nav-tabs { border-bottom:1px solid #eef2f6; padding:0 20px; background:#f8fafc; }
.wa-config .nav-tabs > li > a { border:0; color:#64748b; font-size:11.5px; font-weight:700; padding:11px 12px; }
.wa-config .nav-tabs > li.active > a { color:#15803d; background:transparent; border-bottom:2px solid #25d366; }
.wa-config .tab-content { padding:18px 20px; }
.wa-config .row { margin-bottom:12px; }
.wa-config label { font-size:11.5px; font-weight:700; color:#475569; }
.wa-config .form-control { font-size:12px; }
.wa-secao-titulo { font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#64748b; font-weight:800; margin:0 0 10px; }
.wa-token-status { font-size:10.5px; color:#15803d; font-weight:700; }
.wa-webhook { display:flex; gap:6px; }
.wa-webhook input { background:#f8fafc; font-size:11px; }
.wa-log { font-size:11px; }
.wa-log td { padding:6px 8px; border-bottom:1px solid #f4f7fa; }
.wa-log .wa-chip { font-size:9px; }
.wa-config-acoes { display:flex; gap:8px; flex-wrap:wrap; margin-top:6px; }
.wa-qr { max-width:260px; border:1px solid #e5e7eb; border-radius:12px; padding:10px; background:#fff; margin-top:10px; }
.wa-qr img { width:100%; height:auto; display:block; }
.wa-provider-box { border:1px solid #eef2f6; border-radius:12px; padding:12px 14px; margin-top:12px; background:#fbfcfe; transition:opacity .15s; }
.wa-provider-box.inactive { opacity:.5; }
.wa-provider-tag { display:none; margin-left:6px; border-radius:999px; padding:2px 8px; font-size:9px; letter-spacing:.3px; background:#dcfce7; color:#15803d; font-weight:800; vertical-align:middle; }
.wa-provider-box:not(.inactive) .wa-provider-tag { display:inline-block; }
</style>

<?php if ($mensagem !== ''): ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= wa_e($mensagem) ?></div>
<?php endif; ?>
<?php if ($erro !== ''): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= wa_e($erro) ?></div>
<?php endif; ?>

<div class="wa-config">
    <div class="wa-config-head">
        <h3><i class="fa fa-whatsapp" style="color:#25d366;"></i> Configuração do CRM - TECHPS</h3>
        <span class="label label-<?= $status === 'ativo' ? 'success' : 'default' ?>">
            <?= $status === 'ativo' ? 'Ativo' : 'Inativo' ?>
        </span>
    </div>

    <ul class="nav nav-tabs">
        <li class="active"><a href="#tabProvedor" data-toggle="tab">Provedor</a></li>
        <li><a href="#tabWebhook" data-toggle="tab">Webhook</a></li>
        <li><a href="#tabTemplates" data-toggle="tab">Templates</a></li>
        <li><a href="#tabLogs" data-toggle="tab">Logs</a></li>
        <li><a href="#tabRetencao" data-toggle="tab">Retenção</a></li>
    </ul>

    <form method="post" action="configuracoes.php" class="tab-content" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= wa_e(wa_csrf_token()) ?>">
        <input type="hidden" name="wa_form" value="salvar">

        <div class="tab-pane active" id="tabProvedor">
            <p class="wa-secao-titulo">Conexão</p>
            <div class="row">
                <div class="col-sm-3">
                    <label>Provedor</label>
                    <select name="provider" id="waProvider" class="form-control input-sm">
                        <option value="evolution" <?= $provider === 'evolution' ? 'selected' : '' ?>>Evolution API</option>
                        <option value="cloud" <?= $provider === 'cloud' ? 'selected' : '' ?>>Cloud API (Meta)</option>
                    </select>
                </div>
                <div class="col-sm-2">
                    <label>Status do módulo</label>
                    <select name="status" class="form-control input-sm">
                        <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        <option value="ativo" <?= $status === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    </select>
                </div>
                <div class="col-sm-3">
                    <label>Número para teste</label>
                    <input type="text" name="test_number" class="form-control input-sm" value="<?= wa_e($cfg['cfg_tx_test_number'] ?? '') ?>" placeholder="5511999999999">
                    <small class="text-muted" style="font-size:9.5px;">Usado pelo botão â€œEnviar testeâ€.</small>
                </div>
                <div class="col-sm-4">
                    <label>URL do webhook</label>
                    <div class="wa-webhook">
                        <input type="text" class="form-control input-sm" id="waWebhookUrl" value="<?= wa_e($webhook) ?>" readonly>
                        <button type="button" class="btn btn-default btn-sm" id="waCopiarWebhook" title="Copiar"><i class="fa fa-copy"></i></button>
                    </div>
                </div>
            </div>

            <p class="text-muted" style="font-size:10.5px;">Os campos dos dois provedores ficam visíveis; apenas o provedor selecionado é habilitado para edição e envio.</p>

            <div id="waCamposEvolution" class="wa-provider-box">
                <p class="wa-secao-titulo" style="margin-top:0;">Evolution API <span class="wa-provider-tag">Selecionado</span></p>
                <p style="font-size:10.5px;color:#92400e;margin:-4px 0 10px;">
                    Gateway não oficial, conectado por QR Code. Use para testes/homologação; para produção prefira a Cloud API.
                </p>
                <div class="row">
                    <div class="col-sm-6">
                        <label>Base URL</label>
                        <input type="text" name="base_url_evolution" class="form-control input-sm" value="<?= wa_e($provider === 'evolution' ? ($cfg['cfg_tx_base_url'] ?? '') : '') ?>" placeholder="https://evolution.suaempresa.com">
                    </div>
                    <div class="col-sm-3">
                        <label>Instância</label>
                        <input type="text" name="instance" class="form-control input-sm" value="<?= wa_e($cfg['cfg_tx_instance'] ?? '') ?>" placeholder="techps" autocomplete="off">
                        <small class="text-muted" style="font-size:9.5px;">Nome exato criado na Evolution, ex.: <strong>techps</strong>. Não use login.</small>
                    </div>
                    <div class="col-sm-3">
                        <label>API Key <?= $tokenConfigurado ? '<span class="wa-token-status">(configurada)</span>' : '' ?></label>
                        <input type="password" name="token_evolution" class="form-control input-sm" value="" placeholder="Deixe em branco para manter" autocomplete="new-password">
                        <small class="text-muted" style="font-size:9.5px;">Use a <strong>AUTHENTICATION_API_KEY</strong> da Evolution. Não use login.</small>
                    </div>
                </div>
            </div>

            <div id="waCamposCloud" class="wa-provider-box">
                <p class="wa-secao-titulo" style="margin-top:0;">Cloud API (Meta) <span class="wa-provider-tag">Selecionado</span></p>
                <p style="font-size:10.5px;color:#15803d;margin:-4px 0 10px;">
                    API oficial da Meta, recomendada para produção.
                </p>
                <div class="row">
                    <div class="col-sm-4">
                        <label>Phone Number ID</label>
                        <input type="text" name="phone_number_id" class="form-control input-sm" value="<?= wa_e($cfg['cfg_tx_phone_number_id'] ?? '') ?>">
                    </div>
                    <div class="col-sm-4">
                        <label>WABA ID</label>
                        <input type="text" name="waba_id" class="form-control input-sm" value="<?= wa_e($cfg['cfg_tx_waba_id'] ?? '') ?>">
                    </div>
                    <div class="col-sm-4">
                        <label>Verify Token</label>
                        <input type="text" name="verify_token" class="form-control input-sm" value="<?= wa_e($cfg['cfg_tx_verify_token'] ?? '') ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-4">
                        <label>Access Token <?= $tokenConfigurado ? '<span class="wa-token-status">(configurado)</span>' : '' ?></label>
                        <input type="password" name="token_cloud" class="form-control input-sm" value="" placeholder="Deixe em branco para manter" autocomplete="new-password">
                    </div>
                    <div class="col-sm-4">
                        <label>App Secret <?= $secretConfigurado ? '<span class="wa-token-status">(configurado)</span>' : '' ?></label>
                        <input type="password" name="app_secret_cloud" class="form-control input-sm" value="" placeholder="Deixe em branco para manter" autocomplete="new-password">
                    </div>
                    <div class="col-sm-4">
                        <label>Graph API Base URL</label>
                        <input type="text" name="base_url_cloud" class="form-control input-sm" value="<?= wa_e($provider === 'cloud' ? ($cfg['cfg_tx_base_url'] ?? 'https://graph.facebook.com/v21.0') : 'https://graph.facebook.com/v21.0') ?>" placeholder="https://graph.facebook.com/v21.0">
                    </div>
                </div>
                <p class="text-muted" style="font-size:10.5px;">Texto livre só é permitido dentro da janela de 24 h. Fora dela, use um template aprovado.</p>
            </div>

            <div class="wa-config-acoes">
                <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-save"></i> Salvar configuração</button>
                <button type="button" class="btn btn-primary btn-sm" id="waTestar"><i class="fa fa-plug"></i> Testar conexão</button>
                <button type="button" class="btn btn-default btn-sm" id="waEnviarTeste"><i class="fa fa-paper-plane"></i> Enviar teste</button>
                <button type="button" class="btn btn-default btn-sm" id="waConectar"><i class="fa fa-qrcode"></i> Conectar / QR Code</button>
                <button type="button" class="btn btn-default btn-sm" id="waEstado"><i class="fa fa-signal"></i> Ver estado</button>
            </div>
            <div id="waResultadoConexao" style="margin-top:10px;font-size:12px;"></div>
            <div id="waQrContainer"></div>
        </div>

        <div class="tab-pane" id="tabWebhook">
            <p class="wa-secao-titulo">URL para cadastrar no provedor</p>
            <p style="font-size:12px;color:#475569;">Copie a URL abaixo e cadastre no painel da Evolution API ou da Meta. Para a Cloud API, use o mesmo valor em <strong>Callback URL</strong> e informe o <strong>Verify Token</strong> na aba Provedor.</p>
            <div class="wa-webhook" style="max-width:760px;">
                <input type="text" class="form-control input-sm" value="<?= wa_e($webhook) ?>" readonly>
            </div>
            <p class="text-muted" style="font-size:10.5px;margin-top:10px;">A URL é pública, mas o POST exige assinatura (Cloud) ou apikey/token (Evolution).</p>
        </div>

        <div class="tab-pane" id="tabTemplates">
            <p class="wa-secao-titulo">Templates da Cloud API</p>
            <p style="font-size:12px;color:#475569;">Sincronize os templates aprovados na Meta para consultar nome, idioma e categoria.</p>
            <button type="button" class="btn btn-primary btn-sm" id="waSincronizarTemplates"><i class="fa fa-refresh"></i> Sincronizar templates</button>
            <div id="waResultadoTemplates" style="margin-top:10px;font-size:12px;"></div>
        </div>

        <div class="tab-pane" id="tabLogs">
            <p class="wa-secao-titulo">Últimos eventos recebidos</p>
            <?php if (empty($logs)): ?>
                <p class="text-muted" style="font-size:12px;">Nenhum evento registrado ainda.</p>
            <?php else: ?>
                <div style="max-height:320px;overflow-y:auto;">
                    <table class="wa-log" style="width:100%;border-collapse:collapse;">
                        <thead><tr><th style="text-align:left;font-size:9px;color:#94a3b8;text-transform:uppercase;">Data</th><th style="text-align:left;font-size:9px;color:#94a3b8;text-transform:uppercase;">Provedor</th><th style="text-align:left;font-size:9px;color:#94a3b8;text-transform:uppercase;">Tipo</th><th style="text-align:left;font-size:9px;color:#94a3b8;text-transform:uppercase;">Status</th><th style="text-align:left;font-size:9px;color:#94a3b8;text-transform:uppercase;">Erro</th></tr></thead>
                        <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= wa_e(date('d/m/Y H:i', strtotime($log['evt_dt_criacao']))) ?></td>
                                <td><?= wa_e($log['evt_tx_provider']) ?></td>
                                <td><?= wa_e($log['evt_tx_tipo']) ?></td>
                                <td><span class="wa-chip <?= $log['evt_tx_status'] === 'erro' ? 'wa-chip-nao' : 'wa-chip-sim' ?>"><?= wa_e($log['evt_tx_status']) ?></span></td>
                                <td style="color:#b91c1c;"><?= wa_e($log['evt_tx_erro'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-pane" id="tabRetencao">
            <p class="wa-secao-titulo">Retenção de mensagens e anexos</p>
            <div class="row">
                <div class="col-sm-3">
                    <label>Manter por (dias)</label>
                    <input type="number" name="retencao_dias" class="form-control input-sm" min="0" max="3650" value="<?= (int)($cfg['cfg_nb_retencao_dias'] ?? 0) ?>">
                    <p class="text-muted" style="font-size:10px;margin:4px 0 0;"><strong>0 = nunca apagar.</strong></p>
                </div>
            </div>
            <p class="text-muted" style="font-size:10.5px;">A limpeza é executada pelo cron do módulo. Com 0, o cron não remove mensagens nem anexos. Com valor maior que 0, mensagens e anexos mais antigos que o prazo são removidos.</p>
            <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-save"></i> Salvar retenção</button>
        </div>
    </form>
</div>

<script>
window.WA_CONFIG_PAGE = {
    api: <?= json_encode(wa_e($CONTEX['path']) . '/whatsapp/api.php') ?>,
    csrf: <?= json_encode(wa_csrf_token()) ?>
};
</script>
<script>
(function () {
    var provider = document.getElementById('waProvider');
    var camposEvolution = document.getElementById('waCamposEvolution');
    var camposCloud = document.getElementById('waCamposCloud');
    function alternar() {
        var cloud = provider && provider.value === 'cloud';
        if (camposEvolution) {
            camposEvolution.classList.toggle('inactive', cloud);
            camposEvolution.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = cloud; });
        }
        if (camposCloud) {
            camposCloud.classList.toggle('inactive', !cloud);
            camposCloud.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = !cloud; });
        }
    }
    if (provider) provider.addEventListener('change', alternar);
    alternar();

    function apiGet(params) {
        var qs = Object.keys(params).map(function (k) {
            var key = (k === 'acao') ? 'wa_acao' : k;
            return encodeURIComponent(key) + '=' + encodeURIComponent(params[k]);
        }).join('&');
        return fetch(WA_CONFIG_PAGE.api + '?' + qs, { headers: { 'X-CSRF-Token': WA_CONFIG_PAGE.csrf }, cache: 'no-store' }).then(function (r) { return r.json(); });
    }

    function apiPost(params, data) {
        var qs = Object.keys(params).map(function (k) {
            var key = (k === 'acao') ? 'wa_acao' : k;
            return encodeURIComponent(key) + '=' + encodeURIComponent(params[k]);
        }).join('&');
        var body = new URLSearchParams();
        body.append('csrf', WA_CONFIG_PAGE.csrf);
        Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
        return fetch(WA_CONFIG_PAGE.api + '?' + qs, {
            method: 'POST',
            headers: { 'X-CSRF-Token': WA_CONFIG_PAGE.csrf, 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    function mostrarResultado(id, texto, sucesso) {
        var el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = '<span style="color:' + (sucesso ? '#15803d' : '#b91c1c') + ';font-weight:700;">' + texto + '</span>';
    }

    var testar = document.getElementById('waTestar');
    if (testar) testar.addEventListener('click', function () {
        testar.disabled = true;
        mostrarResultado('waResultadoConexao', 'Testando...', true);
        apiGet({ acao: 'testar_provider' }).then(function (data) {
            mostrarResultado('waResultadoConexao', data.message || (data.success ? 'Conectado.' : 'Falha na conexão.'), !!data.success);
        }).catch(function () { mostrarResultado('waResultadoConexao', 'Falha de comunicação.', false); })
          .finally(function () { testar.disabled = false; });
    });

    var enviarTeste = document.getElementById('waEnviarTeste');
    if (enviarTeste) enviarTeste.addEventListener('click', function () {
        enviarTeste.disabled = true;
        mostrarResultado('waResultadoConexao', 'Enviando teste...', true);
        apiPost({ acao: 'enviar_teste' }, {}).then(function (data) {
            mostrarResultado('waResultadoConexao', data.message || (data.success ? 'Mensagem enviada.' : 'Falha no envio.'), !!data.success);
        }).catch(function () { mostrarResultado('waResultadoConexao', 'Falha de comunicação.', false); })
          .finally(function () { enviarTeste.disabled = false; });
    });

    var conectar = document.getElementById('waConectar');
    if (conectar) conectar.addEventListener('click', function () {
        conectar.disabled = true;
        mostrarResultado('waResultadoConexao', 'Gerando QR Code...', true);
        apiGet({ acao: 'qrcode' }).then(function (data) {
            var alvo = document.getElementById('waQrContainer');
            if (!data.success) {
                mostrarResultado('waResultadoConexao', data.message || 'Não foi possível gerar o QR Code.', false);
                return;
            }
            mostrarResultado('waResultadoConexao', data.message || 'QR Code gerado.', true);
            if (alvo) {
                var src = data.base64 || '';
                if (src && src.indexOf('data:image') !== 0) src = 'data:image/png;base64,' + src;
                alvo.innerHTML = src ? '<div class="wa-qr"><img src="' + src + '" alt="QR Code"></div>' : '<p style="font-size:11px;color:#64748b;">Código: ' + (data.code || '') + '</p>';
            }
        }).catch(function () { mostrarResultado('waResultadoConexao', 'Falha de comunicação.', false); })
          .finally(function () { conectar.disabled = false; });
    });

    var estado = document.getElementById('waEstado');
    if (estado) estado.addEventListener('click', function () {
        estado.disabled = true;
        apiGet({ acao: 'conexao' }).then(function (data) {
            mostrarResultado('waResultadoConexao', data.message || ('Estado: ' + (data.state || 'desconhecido')), !!data.success);
        }).catch(function () { mostrarResultado('waResultadoConexao', 'Falha de comunicação.', false); })
          .finally(function () { estado.disabled = false; });
    });

    var tpl = document.getElementById('waSincronizarTemplates');
    if (tpl) tpl.addEventListener('click', function () {
        tpl.disabled = true;
        mostrarResultado('waResultadoTemplates', 'Sincronizando...', true);
        apiGet({ acao: 'sincronizar_templates' }).then(function (data) {
            mostrarResultado('waResultadoTemplates', data.message || (data.success ? 'Templates sincronizados.' : 'Falha ao sincronizar.'), !!data.success);
        }).catch(function () { mostrarResultado('waResultadoTemplates', 'Falha de comunicação.', false); })
          .finally(function () { tpl.disabled = false; });
    });

    var copiar = document.getElementById('waCopiarWebhook');
    if (copiar) copiar.addEventListener('click', function () {
        var input = document.getElementById('waWebhookUrl');
        if (!input) return;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(input.value).then(function () {
                copiar.innerHTML = '<i class="fa fa-check"></i>';
                setTimeout(function () { copiar.innerHTML = '<i class="fa fa-copy"></i>'; }, 1200);
            });
        }
    });
})();
</script>
<?php rodape(); ?>
