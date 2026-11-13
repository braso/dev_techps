<?php
require_once __DIR__ . '/bootstrap.php';

verificaPermissao('/whatsapp/lote.php');

// Exportação CSV do relatório de um lote.
$exportar = (int)($_GET['exportar'] ?? 0);
if ($exportar > 0) {
    $rs = query(
        "SELECT ld.ld_tx_telefone, ld.ld_tx_status, ld.ld_tx_erro, ld.ld_tx_provider_id, ld.ld_dt_atualizacao,
                c.cont_tx_nome
         FROM wa_lote_destinatario ld
         JOIN wa_contato c ON c.cont_nb_id = ld.ld_nb_contato
         WHERE ld.ld_nb_lote = ? ORDER BY ld.ld_nb_id ASC",
        "i",
        [$exportar]
    );
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="whatsapp-lote-' . $exportar . '.csv"');
    echo "\xEF\xBB\xBF";
    $saida = fopen('php://output', 'w');
    fputcsv($saida, ['Nome', 'Telefone', 'Status', 'Erro', 'ID do provedor', 'Atualizado em'], ';');
    while ($rs && ($row = mysqli_fetch_assoc($rs))) {
        fputcsv($saida, [
            $row['cont_tx_nome'] ?? '',
            $row['ld_tx_telefone'] ?? '',
            $row['ld_tx_status'] ?? '',
            $row['ld_tx_erro'] ?? '',
            $row['ld_tx_provider_id'] ?? '',
            $row['ld_dt_atualizacao'] ?? '',
        ], ';');
    }
    fclose($saida);
    exit;
}

$idsIniciais = [];
foreach (explode(',', strval($_GET['ids'] ?? '')) as $id) {
    $n = (int)$id;
    if ($n > 0) {
        $idsIniciais[$n] = $n;
    }
}
$idsIniciais = array_values($idsIniciais);
$empresas = wa_listar_empresas();
$setores = wa_listar_setores();
$cargos = wa_listar_cargos();
$isAdmin = (bool)preg_match('/administrador/i', strval($_SESSION['user_tx_nivel'] ?? ''));

cabecalho('Envio em Lote - CRM - TECHPS');
?>
<link rel="stylesheet" href="<?= wa_e($CONTEX['path']) ?>/whatsapp/assets/whatsapp.css?v=2">
<style>
.wa-wizard { background:#fff; border:1px solid #e4e9ef; border-radius:16px; box-shadow:0 4px 18px rgba(15,23,42,.06); overflow:hidden; }
.wa-steps { display:flex; border-bottom:1px solid #eef2f6; background:#f8fafc; }
.wa-step { flex:1 1 0; display:flex; align-items:center; gap:9px; padding:13px 16px; color:#94a3b8; font-size:11.5px; font-weight:700; border-right:1px solid #eef2f6; }
.wa-step:last-child { border-right:0; }
.wa-step-num { width:24px; height:24px; border-radius:50%; display:grid; place-items:center; background:#e2e8f0; color:#64748b; font-size:10px; font-weight:800; }
.wa-step.active { color:#15803d; background:#f0fdf4; }
.wa-step.active .wa-step-num { background:#25d366; color:#fff; }
.wa-step.done .wa-step-num { background:#16a34a; color:#fff; }
.wa-step-body { padding:20px 22px; }
.wa-step-pane { display:none; }
.wa-step-pane.active { display:block; }
.wa-wizard h3 { margin:0 0 4px; font-size:17px; font-weight:800; color:#0f172a; }
.wa-wizard .wa-sub { font-size:12px; color:#64748b; margin:0 0 16px; }
.wa-wizard .row { margin-bottom:12px; }
.wa-lote-tabela { width:100%; border-collapse:collapse; font-size:11.5px; }
.wa-lote-tabela th { background:#f8fafc; color:#64748b; font-size:9px; text-transform:uppercase; letter-spacing:.7px; text-align:left; padding:8px 10px; border-bottom:1px solid #e5e7eb; }
.wa-lote-tabela td { padding:8px 10px; border-bottom:1px solid #f4f7fa; }
.wa-lote-lista { max-height:320px; overflow-y:auto; border:1px solid #eef2f6; border-radius:10px; }
.wa-lote-resumo { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:14px; }
.wa-lote-resumo div { background:#f8fafc; border:1px solid #eef2f6; border-radius:10px; padding:11px 13px; }
.wa-lote-resumo small { display:block; font-size:9px; color:#94a3b8; text-transform:uppercase; letter-spacing:.8px; font-weight:800; }
.wa-lote-resumo strong { display:block; font-size:20px; color:#0f172a; }
.wa-preview-msg { background:#eef6f1; border:1px solid #d8ece2; border-radius:12px; padding:14px 16px; font-size:12.5px; color:#1e293b; white-space:pre-wrap; }
.wa-preview-msg small { display:block; color:#64748b; font-size:10px; margin-bottom:6px; text-transform:uppercase; letter-spacing:.7px; font-weight:800; }
.wa-wizard-actions { display:flex; justify-content:space-between; gap:10px; padding:14px 22px; border-top:1px solid #eef2f6; background:#fbfcfe; }
.wa-progress { height:10px; border-radius:999px; background:#eef1f6; overflow:hidden; margin:8px 0 14px; }
.wa-progress span { display:block; height:100%; width:0; background:linear-gradient(90deg,#16a34a,#25d366); transition:width .4s; }
.wa-badge-lote { border-radius:999px; padding:2px 8px; font-size:9px; font-weight:800; }
.wa-badge-enviado { background:#dcfce7; color:#15803d; }
.wa-badge-pendente { background:#f1f5f9; color:#64748b; }
.wa-badge-falhou { background:#fee2e2; color:#b91c1c; }
@media (max-width:700px) {
    .wa-steps { flex-wrap:wrap; }
    .wa-step { flex:1 1 50%; border-bottom:1px solid #eef2f6; }
    .wa-lote-resumo { grid-template-columns:1fr; }
}
</style>

<?php if (!wa_config_ativa()): ?>
    <div class="alert alert-warning">
        <i class="fa fa-exclamation-triangle"></i>
        O provedor de WhatsApp do CRM - TECHPS está inativo. O lote pode ser criado, mas ficará na fila até a configuração ser ativada.
    </div>
<?php endif; ?>

<div class="wa-wizard">
    <div class="wa-steps">
        <div class="wa-step active" data-step-indicator="1"><span class="wa-step-num">1</span> Destinatários</div>
        <div class="wa-step" data-step-indicator="2"><span class="wa-step-num">2</span> Mensagem</div>
        <div class="wa-step" data-step-indicator="3"><span class="wa-step-num">3</span> Prévia</div>
        <div class="wa-step" data-step-indicator="4"><span class="wa-step-num">4</span> Envio</div>
    </div>

    <div class="wa-step-body">
        <section class="wa-step-pane active" data-step="1">
            <h3>Escolha os destinatários</h3>
            <p class="wa-sub">Filtre por empresa, setor ou cargo e selecione quem deve receber a mensagem. Contatos sem WhatsApp válido não entram no lote.</p>
            <div class="row">
                <div class="col-sm-3">
                    <label>Empresa</label>
                    <select class="form-control input-sm" id="loteEmpresa">
                        <option value="0">Todas</option>
                        <?php foreach ($empresas as $e): ?><option value="<?= (int)$e['id'] ?>"><?= wa_e($e['nome']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-3">
                    <label>Setor</label>
                    <select class="form-control input-sm" id="loteSetor">
                        <option value="">Todos</option>
                        <?php foreach ($setores as $s): ?><option value="<?= wa_e($s) ?>"><?= wa_e($s) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-3">
                    <label>Cargo</label>
                    <select class="form-control input-sm" id="loteCargo">
                        <option value="">Todos</option>
                        <?php foreach ($cargos as $cg): ?><option value="<?= wa_e($cg) ?>"><?= wa_e($cg) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-3">
                    <label>Buscar</label>
                    <div style="display:flex;gap:6px;">
                        <input type="text" class="form-control input-sm" id="loteBusca" placeholder="Nome ou telefone">
                        <button type="button" class="btn btn-primary btn-sm" id="loteCarregar"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
            <label style="font-weight:normal;font-size:11.5px;margin-bottom:10px;display:block;">
                <input type="checkbox" id="loteSomenteOptin"> Mostrar somente contatos com opt-in
            </label>

            <div class="wa-lote-lista" id="loteLista">
                <div class="wa-vazio" style="padding:24px;text-align:center;color:#94a3b8;font-size:12px;">Clique em buscar para carregar os contatos.</div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
                <label style="font-weight:normal;font-size:11.5px;"><input type="checkbox" id="loteCheckTodos"> Selecionar todos os contatos exibidos</label>
                <span class="text-muted" style="font-size:11px;" id="loteContador">0 selecionado(s)</span>
            </div>
        </section>

        <section class="wa-step-pane" data-step="2">
            <h3>Escreva a mensagem</h3>
            <p class="wa-sub">Variáveis disponíveis: <strong>{nome}</strong>, <strong>{primeiro_nome}</strong>, <strong>{setor}</strong>, <strong>{cargo}</strong>, <strong>{empresa}</strong>, <strong>{telefone}</strong>.</p>
            <div class="row">
                <div class="col-sm-12">
                    <label>Título do lote (opcional)</label>
                    <input type="text" class="form-control input-sm" id="loteTitulo" maxlength="150" placeholder="Ex.: Aviso de treinamento">
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <label>Mensagem</label>
                    <textarea class="form-control" id="loteMensagem" rows="7" maxlength="4096" placeholder="Olá {primeiro_nome}, ..."></textarea>
                </div>
            </div>
            <div class="wa-preview-msg" id="lotePreviewMensagem"><small>Prévia</small>A prévia aparece aqui conforme você digita.</div>
        </section>

        <section class="wa-step-pane" data-step="3">
            <h3>Confira antes de enviar</h3>
            <p class="wa-sub">Revise os números e a mensagem. O envio é feito pela fila do módulo, com intervalo entre as mensagens.</p>
            <div class="wa-lote-resumo">
                <div><small>Destinatários selecionados</small><strong id="resumoTotal">0</strong></div>
                <div><small>Com opt-in</small><strong id="resumoOptin">0</strong></div>
                <div><small>Sem opt-in</small><strong id="resumoSemOptin">0</strong></div>
            </div>
            <div class="wa-preview-msg" id="resumoMensagem" style="margin-bottom:14px;"></div>
            <?php if ($isAdmin): ?>
                <label style="font-weight:normal;font-size:11.5px;display:block;">
                    <input type="checkbox" id="lotePermitirOptout"> Permitir envio para contatos sem opt-in (registrar justificativa no log)
                </label>
            <?php endif; ?>
            <div id="loteResumoLista" style="max-height:220px;overflow-y:auto;border:1px solid #eef2f6;border-radius:10px;"></div>
        </section>

        <section class="wa-step-pane" data-step="4">
            <h3>Envio em andamento</h3>
            <p class="wa-sub" id="loteStatusTexto">Criando o lote...</p>
            <?php if ($isAdmin): ?>
                <button type="button" class="btn btn-default btn-sm" id="loteProcessarFila" style="margin-bottom:10px;">
                    <i class="fa fa-play"></i> Processar fila agora
                </button>
            <?php endif; ?>
            <a href="#" class="btn btn-default btn-sm" id="loteExportar" style="margin-bottom:10px;display:none;">
                <i class="fa fa-download"></i> Exportar CSV
            </a>
            <div class="wa-progress"><span id="loteProgresso"></span></div>
            <div class="wa-lote-resumo">
                <div><small>Total</small><strong id="loteTotal">0</strong></div>
                <div><small>Enviados</small><strong id="loteEnviados">0</strong></div>
                <div><small>Falhas</small><strong id="loteFalhas">0</strong></div>
            </div>
            <div id="loteResultado" style="max-height:300px;overflow-y:auto;border:1px solid #eef2f6;border-radius:10px;"></div>
        </section>
    </div>

    <div class="wa-wizard-actions">
        <button type="button" class="btn btn-default" id="loteVoltar" style="visibility:hidden;"><i class="fa fa-arrow-left"></i> Voltar</button>
        <div>
            <a href="contatos.php" class="btn btn-default">Cancelar</a>
            <button type="button" class="btn btn-success" id="loteAvancar">Avançar <i class="fa fa-arrow-right"></i></button>
        </div>
    </div>
</div>

<script>
window.WA_LOTE = {
    api: <?= json_encode(wa_e($CONTEX['path']) . '/whatsapp/api.php') ?>,
    csrf: <?= json_encode(wa_csrf_token()) ?>,
    idsIniciais: <?= json_encode($idsIniciais) ?>,
    isAdmin: <?= $isAdmin ? 'true' : 'false' ?>,
    urlContatos: <?= json_encode(wa_e($CONTEX['path']) . '/whatsapp/contatos.php') ?>
};
</script>
<script src="<?= wa_e($CONTEX['path']) ?>/whatsapp/assets/lote.js?v=2"></script>
<?php rodape(); ?>
