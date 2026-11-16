<?php
/**
 * Widget flutuante do Assistente Virtual.
 * Incluído pelo menu.php quando ASSISTENTE_ENABLED=1 e o usuário tem permissão.
 */

if (defined('IA_WIDGET_INCLUIDO')) {
    return;
}
define('IA_WIDGET_INCLUIDO', true);

require_once __DIR__ . '/lib/config.php';

$ehSuperIaWidget = (
    (function_exists('ehSuperAdmin') && ehSuperAdmin()) ||
    (function_exists('ia_is_admin') && ia_is_admin()) ||
    stripos(strval($_SESSION['user_tx_nivel'] ?? ''), 'super administrador') !== false ||
    intval($_SESSION['user_nb_superadmin'] ?? 0) === 1
);

if (!$ehSuperIaWidget && ia_env('ASSISTENTE_ENABLED', '0') !== '1') {
    return;
}
if (!$ehSuperIaWidget && (!function_exists('usuarioPodeAcessar') || !ia_pode_usar())) {
    return;
}
if (strpos(strval($_SERVER['REQUEST_URI'] ?? ''), '/assistente_virtual/') !== false) {
    return;
}

global $CONTEX;
$iaBase = rtrim(strval($CONTEX['path'] ?? ''), '/');
$iaApi = ia_module_url('api.php');
$iaPage = ia_module_url('index.php');
$iaSuporte = $iaBase . '/suporte/index.php';
$iaTela = strval($_SERVER['REQUEST_URI'] ?? '');
$iaSugestoes = ia_sugestoes_por_tela($iaTela);
?>
<link rel="stylesheet" href="<?= ia_e(ia_module_url('assets/assistente.css')) ?>?v=3">
<div id="iaWidget" class="ia-widget">
    <button type="button" class="ia-widget-btn" id="iaWidgetBtn" title="Abrir Assistente Virtual" aria-label="Abrir Assistente Virtual">
        <i class="fa fa-comments"></i>
        <span class="ia-widget-badge">Beta</span>
    </button>

    <section class="ia-panel" id="iaWidgetPanel" aria-hidden="true" aria-label="Assistente Virtual">
        <header class="ia-panel-head">
            <div>
                <strong><i class="fa fa-robot"></i> Assistente Virtual</strong>
                <small>Dúvidas sobre o sistema, 100% interno</small>
            </div>
            <div class="ia-head-actions">
                <button type="button" class="ia-icon-btn" id="iaWidgetNova" title="Nova conversa" aria-label="Nova conversa">
                    <i class="fa fa-plus"></i>
                </button>
                <button type="button" class="ia-icon-btn" id="iaWidgetClose" title="Fechar" aria-label="Fechar">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </header>

        <div class="ia-messages" id="iaWidgetMessages" aria-live="polite">
            <div class="ia-msg ia-assistant" data-ia-welcome>
                <div class="ia-bubble">
                    <p><strong>Olá!</strong> Sou o assistente do sistema. Posso ajudar com dúvidas de acesso, configuração e uso das telas.</p>
                    <p class="ia-muted">As respostas vêm do manual e da documentação interna. Se eu não souber, encaminho para o suporte.</p>
                </div>
            </div>
        </div>

        <div class="ia-suggestions" id="iaWidgetSuggestions">
            <?php foreach (array_slice($iaSugestoes, 0, 3) as $sugestao): ?>
                <button type="button" class="ia-suggestion"><?= ia_e($sugestao) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="ia-composer" id="iaWidgetForm" autocomplete="off">
            <textarea id="iaWidgetInput" rows="1" maxlength="2000" placeholder="Digite sua dúvida sobre o sistema..." aria-label="Digite sua dúvida"></textarea>
            <button type="button" class="ia-send" id="iaWidgetSend" title="Enviar" aria-label="Enviar">
                <i class="fa fa-paper-plane"></i>
            </button>
        </div>

        <footer class="ia-panel-foot">
            <span>IA local • respostas com fonte</span>
            <a href="<?= ia_e($iaPage) ?>">Abrir página completa</a>
        </footer>
    </section>
</div>

<script>
window.IA_ASSISTENTE = {
    api: <?= json_encode($iaApi, JSON_UNESCAPED_SLASHES) ?>,
    base: <?= json_encode($iaBase, JSON_UNESCAPED_SLASHES) ?>,
    page: <?= json_encode($iaPage, JSON_UNESCAPED_SLASHES) ?>,
    csrf: <?= json_encode(ia_csrf_token()) ?>,
    tela: <?= json_encode($iaTela, JSON_UNESCAPED_SLASHES) ?>,
    suporte: <?= json_encode($iaSuporte, JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="<?= ia_e(ia_module_url('assets/assistente.js')) ?>?v=3"></script>
