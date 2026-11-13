<?php
/**
 * Widget flutuante do WhatsApp.
 * Incluído pelo menu.php apenas quando WHATSAPP_ENABLED=1 e o usuário tem permissão.
 */

if (trim(strval($_ENV['WHATSAPP_ENABLED'] ?? '')) !== '1') {
    return;
}
if (!function_exists('usuarioPodeAcessar') || !usuarioPodeAcessar('/whatsapp/index.php')) {
    return;
}
if (!function_exists('wa_module_url')) {
    require_once __DIR__ . '/lib/seguranca.php';
}
if (strpos(strval($_SERVER['REQUEST_URI'] ?? ''), '/whatsapp/') !== false) {
    return;
}

$waWidgetApi = wa_module_url('api.php');
$waWidgetIndex = wa_module_url('index.php');
$waWidgetCsrf = wa_csrf_token();
?>
<link rel="stylesheet" href="<?= wa_e($CONTEX['path']) ?>/whatsapp/assets/widget.css?v=2">
<div id="waWidget" class="wa-widget">
    <button type="button" class="wa-widget-btn" id="waWidgetBtn" title="Abrir CRM - TECHPS" aria-label="Abrir CRM - TECHPS">
        <i class="fa fa-whatsapp"></i>
        <span class="wa-widget-badge" id="waWidgetBadge" style="display:none;">0</span>
    </button>

    <div class="wa-widget-panel" id="waWidgetPanel" aria-hidden="true">
        <div class="wa-widget-head">
            <div>
                <strong><i class="fa fa-whatsapp"></i> CRM - TECHPS</strong>
                <small>Atendimento rápido</small>
            </div>
            <button type="button" class="wa-widget-close" id="waWidgetClose" aria-label="Fechar">&times;</button>
        </div>

        <div class="wa-widget-body">
            <div id="waWidgetListaWrap">
                <div class="wa-widget-search">
                    <i class="fa fa-search"></i>
                    <input type="text" id="waWidgetBusca" placeholder="Buscar conversa..." autocomplete="off">
                </div>
                <div class="wa-widget-lista" id="waWidgetLista">
                    <div class="wa-widget-vazio">Carregando conversas...</div>
                </div>
            </div>

            <div id="waWidgetChat" class="wa-widget-chat" style="display:none;">
                <div class="wa-widget-chat-head">
                    <button type="button" class="wa-widget-back" id="waWidgetBack" aria-label="Voltar">&#8592;</button>
                    <div class="wa-widget-chat-info">
                        <strong id="waWidgetNome">Contato</strong>
                        <small id="waWidgetTelefone"></small>
                    </div>
                </div>
                <div class="wa-widget-mensagens" id="waWidgetMensagens"></div>
                <div class="wa-widget-composer">
                    <textarea id="waWidgetTexto" rows="1" maxlength="4096" placeholder="Escreva uma mensagem..."></textarea>
                    <button type="button" id="waWidgetEnviar" aria-label="Enviar"><i class="fa fa-paper-plane"></i></button>
                </div>
            </div>
        </div>

        <div class="wa-widget-footer">
            <a href="<?= wa_e($waWidgetIndex) ?>">Abrir módulo completo <i class="fa fa-external-link"></i></a>
        </div>
    </div>
</div>

<script>
window.WA_WIDGET = {
    api: <?= json_encode($waWidgetApi) ?>,
    index: <?= json_encode($waWidgetIndex) ?>,
    csrf: <?= json_encode($waWidgetCsrf) ?>
};
</script>
<script src="<?= wa_e($CONTEX['path']) ?>/whatsapp/assets/widget.js?v=2"></script>
