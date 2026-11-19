<?php
/**
 * Página completa do Assistente Virtual.
 */
require_once __DIR__ . '/bootstrap.php';

if (!ia_pode_ver_assistente()) {
    header('Location: ' . rtrim(strval($CONTEX['path'] ?? ''), '/') . paginaInicialPermitida());
    exit;
}

global $CONTEX;
$iaBase = rtrim(strval($CONTEX['path'] ?? ''), '/');
$iaApi = ia_module_url('api.php');
$iaSuporte = $iaBase . '/suporte/index.php';
$iaAdmin = ia_module_url('admin.php');
$iaTela = strval($_SERVER['REQUEST_URI'] ?? '');

include_once dirname(__DIR__) . '/../contex20/funcoes_form.php';
cabecalho('Assistente Virtual');
?>
<link rel="stylesheet" href="<?= ia_e(ia_module_url('assets/assistente.css')) ?>?v=3">
<div class="ia-page" id="iaPage">
    <aside class="ia-history">
        <h3><i class="fa fa-history"></i> Conversas</h3>
        <button type="button" class="ia-btn ia-btn-primario" id="iaPageNova" style="margin-bottom:10px;justify-content:center;">
            <i class="fa fa-plus"></i> Nova conversa
        </button>
        <div class="ia-history-list" id="iaHistoryList">
            <div class="ia-muted">Carregando...</div>
        </div>
        <?php if (ia_is_admin()): ?>
            <a class="ia-btn" href="<?= ia_e($iaAdmin) ?>" style="margin-top:10px;justify-content:center;">
                <i class="fa fa-database"></i> Base de conhecimento
            </a>
        <?php endif; ?>
    </aside>

    <main class="ia-chat">
        <header class="ia-chat-head">
            <div>
                <h2><i class="fa fa-robot"></i> Assistente Virtual TechPS</h2>
                <p>Dúvidas de acesso, configuração e uso do sistema — respostas com fonte, 100% interno.</p>
            </div>
            <span class="ia-status">Beta • IA local</span>
        </header>

        <div class="ia-messages" id="iaPageMessages" aria-live="polite">
            <div class="ia-msg ia-assistant" data-ia-welcome>
                <div class="ia-bubble">
                    <p><strong>Olá!</strong> Sou o assistente do sistema. Posso ajudar com dúvidas de acesso, configuração e uso das telas.</p>
                    <p class="ia-muted">As respostas vêm do manual e da documentação interna. Se eu não souber, encaminho para o suporte.</p>
                </div>
            </div>
        </div>

        <div class="ia-suggestions" id="iaPageSuggestions"></div>

        <form class="ia-composer" id="iaPageForm" autocomplete="off">
            <textarea id="iaPageInput" rows="1" maxlength="2000" placeholder="Digite sua dúvida sobre o sistema..." aria-label="Digite sua dúvida"></textarea>
            <button type="submit" class="ia-send" title="Enviar" aria-label="Enviar">
                <i class="fa fa-paper-plane"></i>
            </button>
        </form>

        <footer class="ia-panel-foot">
            <span>IA local • respostas com fonte</span>
            <a href="<?= ia_e($iaSuporte) ?>"><i class="fa fa-life-ring"></i> Abrir chamado de suporte</a>
        </footer>
    </main>
</div>

<script>
window.IA_ASSISTENTE = {
    api: <?= json_encode($iaApi, JSON_UNESCAPED_SLASHES) ?>,
    base: <?= json_encode($iaBase, JSON_UNESCAPED_SLASHES) ?>,
    page: <?= json_encode(ia_module_url('index.php'), JSON_UNESCAPED_SLASHES) ?>,
    csrf: <?= json_encode(ia_csrf_token()) ?>,
    tela: <?= json_encode($iaTela, JSON_UNESCAPED_SLASHES) ?>,
    suporte: <?= json_encode($iaSuporte, JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="<?= ia_e(ia_module_url('assets/assistente.js')) ?>?v=3"></script>
<?php rodape(); ?>
