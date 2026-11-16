<?php
/**
 * Administração da base de conhecimento do Assistente Virtual.
 */
require_once __DIR__ . '/bootstrap.php';

verificaPermissao('/assistente_virtual/admin.php');

global $CONTEX;
$iaBase = rtrim(strval($CONTEX['path'] ?? ''), '/');
$iaApi = ia_module_url('api.php');
$iaPage = ia_module_url('index.php');
$iaUploads = __DIR__ . '/uploads';
if (!is_dir($iaUploads)) {
    @mkdir($iaUploads, 0775, true);
}
if (!is_file($iaUploads . '/.htaccess')) {
    @file_put_contents($iaUploads . '/.htaccess', "Require all denied\nDeny from all\n");
}

$msg = '';
$msgTipo = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = trim(strval($_POST['ia_acao'] ?? ''));
    if (!ia_csrf_validar($_POST['csrf'] ?? '')) {
        $msg = 'Sessão expirada. Recarregue a página.';
        $msgTipo = 'erro';
    } elseif ($acao === 'upload') {
        $arquivo = $_FILES['arquivo'] ?? null;
        if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $msg = 'Selecione um arquivo válido.';
            $msgTipo = 'erro';
        } elseif (($arquivo['size'] ?? 0) > 20 * 1024 * 1024) {
            $msg = 'O arquivo excede o limite de 20 MB.';
            $msgTipo = 'erro';
        } else {
            $ext = strtolower(pathinfo(strval($arquivo['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($ext, ['docx', 'pdf', 'md', 'txt', 'html', 'htm'], true)) {
                $msg = 'Formato não suportado. Use DOCX, PDF, MD, TXT ou HTML.';
                $msgTipo = 'erro';
            } elseif ($ext === 'docx' && !ia_docx_disponivel()) {
                $msg = 'O servidor não consegue ler DOCX. Converta para PDF ou Markdown e envie novamente.';
                $msgTipo = 'erro';
            } else {
                $nomeSeguro = date('YmdHis') . '_' . preg_replace('/[^a-zA-Z0-9._-]+/', '_', basename(strval($arquivo['name'])));
                $destino = $iaUploads . '/' . $nomeSeguro;
                if (@move_uploaded_file($arquivo['tmp_name'], $destino)) {
                    $r = ia_ingestar_arquivo($destino, [
                        'titulo' => trim(strval($_POST['titulo'] ?? '')) ?: ia_titulo_de_arquivo($destino),
                        'fonte' => 'upload',
                        'modulo' => trim(strval($_POST['modulo'] ?? 'geral')),
                        'menu_path' => trim(strval($_POST['menu_path'] ?? '')),
                        'perfis' => trim(strval($_POST['perfis'] ?? 'todos')),
                        'owner' => trim(strval($_POST['owner'] ?? '')),
                        'versao' => trim(strval($_POST['versao'] ?? '1.0')),
                    ], false);
                    if (!empty($r['ok'])) {
                        $msg = !empty($r['skipped'])
                            ? 'Documento já estava indexado com o mesmo conteúdo.'
                            : 'Documento indexado com sucesso (' . (int)($r['chunks'] ?? 0) . ' trechos).';
                    } else {
                        $msg = 'Falha ao indexar: ' . ($r['erro'] ?? 'erro desconhecido');
                        $msgTipo = 'erro';
                    }
                } else {
                    $msg = 'Não foi possível salvar o arquivo enviado.';
                    $msgTipo = 'erro';
                }
            }
        }
    } elseif ($acao === 'reindexar') {
        $r = ia_reindexar_tudo(false);
        $msg = 'Reindexação concluída. Manual: ' . (!empty($r['manual']['ok']) ? 'ok' : 'falha')
            . '; documentos: ' . (int)($r['docs']['docs'] ?? 0) . ' novos, '
            . (int)($r['docs']['chunks'] ?? 0) . ' trechos.';
    } elseif ($acao === 'desativar_doc') {
        ia_desativar_documento((int)($_POST['doc_id'] ?? 0));
        $msg = 'Documento desativado.';
    } elseif ($acao === 'faq_salvar') {
        $faqId = (int)($_POST['faq_id'] ?? 0);
        $pergunta = trim(strval($_POST['pergunta'] ?? ''));
        $resposta = trim(strval($_POST['resposta'] ?? ''));
        if ($pergunta === '' || $resposta === '') {
            $msg = 'Preencha a pergunta e a resposta.';
            $msgTipo = 'erro';
        } elseif ($faqId > 0) {
            ia_db_query("UPDATE ia_faq SET faq_tx_pergunta=?, faq_tx_resposta=?, faq_dt_atualizacao=NOW() WHERE faq_nb_id=?", 'ssi', [$pergunta, $resposta, $faqId]);
            $msg = 'FAQ atualizada.';
        } else {
            ia_db_query("INSERT INTO ia_faq (faq_tx_pergunta, faq_tx_resposta, faq_tx_modulo, faq_tx_status, faq_dt_atualizacao) VALUES (?, ?, 'geral', 'ativo', NOW())", 'ss', [$pergunta, $resposta]);
            $msg = 'FAQ criada.';
        }
    } elseif ($acao === 'faq_excluir') {
        ia_db_query("UPDATE ia_faq SET faq_tx_status='inativo', faq_dt_atualizacao=NOW() WHERE faq_nb_id=?", 'i', [(int)($_POST['faq_id'] ?? 0)]);
        $msg = 'FAQ desativada.';
    } elseif ($acao === 'limpar_retencao') {
        ia_limpar_retencao();
        $msg = 'Retenção aplicada.';
    } elseif ($acao === 'testar_ollama') {
        $t0 = microtime(true);
        $cfgTeste = ia_settings();
        $r = ia_ollama_chat([
            ['role' => 'system', 'content' => 'Responda em português.'],
            ['role' => 'user', 'content' => 'Responda apenas: ok'],
        ], false, null, $cfgTeste['model']);
        $ms = (int)round((microtime(true) - $t0) * 1000);
        if (!empty($r['ok'])) {
            $msg = 'Ollama conectado — modelo ' . $cfgTeste['model'] . ' respondeu em ' . $ms . ' ms.';
        } else {
            $msg = 'Falha ao conectar no Ollama: ' . ($r['erro'] ?? 'erro desconhecido');
            $msgTipo = 'erro';
        }
    }
}

$stats = ia_estatisticas();
$cobertura = ia_relatorio_cobertura();
$docs = ia_db_all("SELECT * FROM ia_documento ORDER BY doc_nb_id DESC LIMIT 100");
$faqs = ia_db_all("SELECT * FROM ia_faq WHERE faq_tx_status='ativo' ORDER BY faq_nb_id DESC LIMIT 100");
$semResposta = ia_perguntas_sem_resposta(15);
$modelos = ia_ollama_modelos();
$config = ia_settings();
$modeloAtivo = in_array($config['model'], $modelos, true);
$modeloComplexoAtivo = in_array($config['model_complex'], $modelos, true);

include_once dirname(__DIR__) . '/../contex20/funcoes_form.php';
cabecalho('Base de Conhecimento (IA)');
?>
<link rel="stylesheet" href="<?= ia_e(ia_module_url('assets/assistente.css')) ?>?v=3">
<div class="ia-admin">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <h2 style="margin:0;color:#143f75;"><i class="fa fa-database"></i> Base de Conhecimento do Assistente</h2>
            <p class="ia-muted" style="margin:4px 0 0;">Manual, documentação, POPs, mapa de telas e FAQs. Tudo no MySQL do tenant.</p>
        </div>
        <a class="ia-btn" href="<?= ia_e($iaPage) ?>"><i class="fa fa-comments"></i> Abrir assistente</a>
    </div>

    <div class="ia-aviso">
        <strong>Beta:</strong> o acesso ao chat é controlado em <strong>Cadastros &gt; Perfil de Acesso &gt; Assistente Virtual</strong>. Administradores e Super Administradores têm acesso automático; os demais perfis precisam ser liberados individualmente.
    </div>

    <?php if ($msg !== ''): ?>
        <div class="ia-aviso <?= $msgTipo === 'erro' ? 'ia-erro' : '' ?>"><?= ia_e($msg) ?></div>
    <?php endif; ?>

    <?php if (empty($modelos)): ?>
        <div class="ia-aviso ia-erro">
            O Ollama não respondeu em <strong><?= ia_e($config['ollama_url']) ?></strong>. Verifique se o serviço está rodando e se o modelo <code><?= ia_e($config['model']) ?></code> foi baixado.
        </div>
    <?php elseif (!$modeloAtivo): ?>
        <div class="ia-aviso">
            O modelo configurado <code><?= ia_e($config['model']) ?></code> não aparece na lista do Ollama. Modelos disponíveis: <?= ia_e(implode(', ', $modelos)) ?>.
        </div>
    <?php elseif (!empty($config['router']) && !$modeloComplexoAtivo): ?>
        <div class="ia-aviso">
            O modelo complexo <code><?= ia_e($config['model_complex']) ?></code> não está instalado. Perguntas complexas usarão o fallback extrativo. Baixe com <code>ollama pull <?= ia_e($config['model_complex']) ?></code>.
        </div>
    <?php endif; ?>

    <div class="ia-cards">
        <div class="ia-card"><span>Documentos ativos</span><strong><?= (int)$stats['docs'] ?></strong></div>
        <div class="ia-card"><span>Trechos indexados</span><strong><?= (int)$stats['chunks'] ?></strong></div>
        <div class="ia-card"><span>FAQs ativas</span><strong><?= (int)$stats['faqs'] ?></strong></div>
        <div class="ia-card"><span>Perguntas hoje</span><strong><?= (int)$stats['perguntas_hoje'] ?></strong></div>
        <div class="ia-card"><span>Sem resposta hoje</span><strong><?= (int)$stats['sem_resposta'] ?></strong></div>
        <div class="ia-card"><span>Feedback 👍 / 👎</span><strong><?= (int)$stats['feedback_positivo'] ?> / <?= (int)$stats['feedback_negativo'] ?></strong></div>
        <div class="ia-card">
            <span>Ollama</span>
            <strong style="font-size:16px;color:<?= empty($modelos) ? '#a33' : ($modeloAtivo ? '#1f7a53' : '#7a5a12') ?>;">
                <?= empty($modelos) ? 'Desconectado' : ($modeloAtivo ? 'Conectado' : 'Modelo ausente') ?>
            </strong>
            <small class="ia-muted">
                <?= ia_e($config['model']) ?><?= !empty($config['router']) ? ' • complexo: ' . ia_e($config['model_complex']) . ($modeloComplexoAtivo ? ' ✓' : ' (ausente)') : '' ?>
            </small>
        </div>
    </div>

    <form method="post" style="margin-bottom:14px;">
        <input type="hidden" name="csrf" value="<?= ia_e(ia_csrf_token()) ?>">
        <input type="hidden" name="ia_acao" value="testar_ollama">
        <button class="ia-btn" type="submit"><i class="fa fa-plug"></i> Testar conexão com o Ollama</button>
    </form>

    <div class="ia-tabs">
        <button type="button" class="ia-tab ia-ativo" data-tab="visao">Visão geral</button>
        <button type="button" class="ia-tab" data-tab="docs">Documentos</button>
        <button type="button" class="ia-tab" data-tab="faq">FAQs</button>
        <button type="button" class="ia-tab" data-tab="teste">Testar pergunta</button>
    </div>

    <div class="ia-tab-content ia-ativo" data-tab-content="visao">
        <div class="ia-box">
            <h3>Cobertura da base</h3>
            <table>
                <thead><tr><th>Módulo</th><th>Fonte</th><th>Documentos</th><th>Trechos</th></tr></thead>
                <tbody>
                <?php foreach (($cobertura['linhas'] ?? []) as $linha): ?>
                    <tr>
                        <td><?= ia_e($linha['modulo'] ?? '') ?></td>
                        <td><?= ia_e($linha['fonte'] ?? '') ?></td>
                        <td><?= (int)($linha['docs'] ?? 0) ?></td>
                        <td><?= (int)($linha['chunks'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="ia-muted" style="margin-top:10px;">
                Última ingestão: <?= ia_e($cobertura['ultima_ingestao'] ?? 'nunca') ?>.
                Modelo de embeddings: <?= ia_e($config['embed_model']) ?> (<?= ia_embeddings_ativos() ? 'ativo' : 'desligado' ?>).
            </p>
            <form method="post" style="display:inline-block;margin-right:8px;">
                <input type="hidden" name="csrf" value="<?= ia_e(ia_csrf_token()) ?>">
                <input type="hidden" name="ia_acao" value="reindexar">
                <button class="ia-btn ia-btn-primario" type="submit"><i class="fa fa-refresh"></i> Reindexar manual + docs + menu</button>
            </form>
            <form method="post" style="display:inline-block;">
                <input type="hidden" name="csrf" value="<?= ia_e(ia_csrf_token()) ?>">
                <input type="hidden" name="ia_acao" value="limpar_retencao">
                <button class="ia-btn" type="submit"><i class="fa fa-eraser"></i> Aplicar retenção</button>
            </form>
        </div>

        <div class="ia-box">
            <h3>Perguntas sem resposta ou respondidas de forma extrativa</h3>
            <?php if (empty($semResposta)): ?>
                <p class="ia-muted">Nenhuma pergunta registrada ainda.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Pergunta</th><th>Ocorrências</th></tr></thead>
                    <tbody>
                    <?php foreach ($semResposta as $p): ?>
                        <tr><td><?= ia_e($p['pergunta'] ?? '') ?></td><td><?= (int)($p['total'] ?? 0) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="ia-muted" style="margin-top:8px;">Use essas perguntas para criar FAQs ou complementar o manual.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="ia-tab-content" data-tab-content="docs">
        <div class="ia-box">
            <h3>Enviar documento</h3>
            <p class="ia-muted">Formatos: DOCX, PDF, MD, TXT e HTML (até 20 MB). O DOCX exige a extensão ZIP no PHP.</p>
            <form method="post" enctype="multipart/form-data" class="ia-grid-form">
                <input type="hidden" name="csrf" value="<?= ia_e(ia_csrf_token()) ?>">
                <input type="hidden" name="ia_acao" value="upload">
                <label>Arquivo<input type="file" name="arquivo" required accept=".docx,.pdf,.md,.txt,.html,.htm"></label>
                <label>Título<input type="text" name="titulo" maxlength="200" placeholder="Ex.: Manual do Sistema — Férias"></label>
                <label>Módulo<input type="text" name="modulo" maxlength="60" placeholder="Ex.: ponto"></label>
                <label>Caminho da tela<input type="text" name="menu_path" maxlength="255" placeholder="Ex.: /cadastro_ferias.php"></label>
                <label>Perfis<input type="text" name="perfis" maxlength="255" placeholder="todos ou admin,rh,gestor"></label>
                <label>Responsável<input type="text" name="owner" maxlength="120" placeholder="Ex.: RH"></label>
                <label>Versão<input type="text" name="versao" maxlength="40" placeholder="Ex.: 1.0"></label>
                <div style="display:flex;align-items:flex-end;">
                    <button class="ia-btn ia-btn-primario" type="submit"><i class="fa fa-upload"></i> Enviar e indexar</button>
                </div>
            </form>
        </div>

        <div class="ia-box">
            <h3>Documentos indexados</h3>
            <table>
                <thead><tr><th>ID</th><th>Título</th><th>Fonte</th><th>Módulo</th><th>Versão</th><th>Status</th><th>Ação</th></tr></thead>
                <tbody>
                <?php foreach ($docs as $d): ?>
                    <tr>
                        <td><?= (int)$d['doc_nb_id'] ?></td>
                        <td><?= ia_e($d['doc_tx_titulo'] ?? '') ?><br><small class="ia-muted"><?= ia_e($d['doc_tx_arquivo'] ?? '') ?></small></td>
                        <td><?= ia_e($d['doc_tx_fonte'] ?? '') ?></td>
                        <td><?= ia_e($d['doc_tx_modulo'] ?? '') ?></td>
                        <td><?= ia_e($d['doc_tx_versao'] ?? '') ?></td>
                        <td><span class="ia-status <?= ($d['doc_tx_status'] ?? '') === 'ativo' ? '' : 'ia-off' ?>"><?= ia_e($d['doc_tx_status'] ?? '') ?></span></td>
                        <td>
                            <?php if (($d['doc_tx_status'] ?? '') === 'ativo'): ?>
                                <form method="post" onsubmit="return confirm('Desativar este documento?');">
                                    <input type="hidden" name="csrf" value="<?= ia_e(ia_csrf_token()) ?>">
                                    <input type="hidden" name="ia_acao" value="desativar_doc">
                                    <input type="hidden" name="doc_id" value="<?= (int)$d['doc_nb_id'] ?>">
                                    <button class="ia-btn" type="submit">Desativar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="ia-tab-content" data-tab-content="faq">
        <div class="ia-box">
            <h3>Nova FAQ / editar</h3>
            <form method="post" class="ia-grid-form">
                <input type="hidden" name="csrf" value="<?= ia_e(ia_csrf_token()) ?>">
                <input type="hidden" name="ia_acao" value="faq_salvar">
                <input type="hidden" name="faq_id" id="iaFaqId" value="0">
                <label style="grid-column:1/-1;">Pergunta<input type="text" name="pergunta" id="iaFaqPergunta" maxlength="500" required></label>
                <label style="grid-column:1/-1;">Resposta<textarea name="resposta" id="iaFaqResposta" required></textarea></label>
                <div style="display:flex;align-items:flex-end;gap:8px;">
                    <button class="ia-btn ia-btn-primario" type="submit"><i class="fa fa-save"></i> Salvar FAQ</button>
                    <button class="ia-btn" type="button" onclick="document.getElementById('iaFaqId').value=0;document.getElementById('iaFaqPergunta').value='';document.getElementById('iaFaqResposta').value='';">Limpar</button>
                </div>
            </form>
        </div>

        <div class="ia-box">
            <h3>FAQs ativas</h3>
            <table>
                <thead><tr><th>Pergunta</th><th>Resposta</th><th>Ação</th></tr></thead>
                <tbody>
                <?php foreach ($faqs as $f): ?>
                    <tr>
                        <td><?= ia_e($f['faq_tx_pergunta'] ?? '') ?></td>
                        <td><?= ia_e(mb_substr(strval($f['faq_tx_resposta'] ?? ''), 0, 220, 'UTF-8')) ?>…</td>
                        <td style="white-space:nowrap;">
                            <button class="ia-btn ia-faq-editar" type="button"
                                data-id="<?= (int)$f['faq_nb_id'] ?>"
                                data-pergunta="<?= ia_e($f['faq_tx_pergunta'] ?? '') ?>"
                                data-resposta="<?= ia_e($f['faq_tx_resposta'] ?? '') ?>">Editar</button>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Desativar esta FAQ?');">
                                <input type="hidden" name="csrf" value="<?= ia_e(ia_csrf_token()) ?>">
                                <input type="hidden" name="ia_acao" value="faq_excluir">
                                <input type="hidden" name="faq_id" value="<?= (int)$f['faq_nb_id'] ?>">
                                <button class="ia-btn" type="submit">Desativar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="ia-tab-content" data-tab-content="teste">
        <div class="ia-box">
            <h3>Testar pergunta</h3>
            <p class="ia-muted">A pergunta usa a mesma API do assistente. O resultado mostra a resposta e as fontes recuperadas.</p>
            <form id="iaTesteForm" class="ia-grid-form">
                <label style="grid-column:1/-1;">Pergunta<input type="text" id="iaTestePergunta" maxlength="2000" placeholder="Ex.: Como cadastrar um funcionário?" required></label>
                <div style="display:flex;align-items:flex-end;">
                    <button class="ia-btn ia-btn-primario" type="submit"><i class="fa fa-flask"></i> Testar</button>
                </div>
            </form>
            <div id="iaTesteResultado" style="margin-top:14px;"></div>
        </div>
    </div>
</div>

<script>
window.IA_ASSISTENTE = {
    api: <?= json_encode($iaApi, JSON_UNESCAPED_SLASHES) ?>,
    base: <?= json_encode($iaBase, JSON_UNESCAPED_SLASHES) ?>,
    csrf: <?= json_encode(ia_csrf_token()) ?>,
    tela: <?= json_encode(strval($_SERVER['REQUEST_URI'] ?? ''), JSON_UNESCAPED_SLASHES) ?>
};
function iaEditarFaq(id, pergunta, resposta) {
    document.getElementById('iaFaqId').value = id;
    document.getElementById('iaFaqPergunta').value = pergunta;
    document.getElementById('iaFaqResposta').value = resposta;
    document.getElementById('iaFaqPergunta').focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
document.querySelectorAll('.ia-faq-editar').forEach(function (btn) {
    btn.addEventListener('click', function () {
        iaEditarFaq(btn.dataset.id, btn.dataset.pergunta, btn.dataset.resposta);
    });
});
(function () {
    document.querySelectorAll('.ia-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.ia-tab').forEach(function (t) { t.classList.remove('ia-ativo'); });
            document.querySelectorAll('.ia-tab-content').forEach(function (c) { c.classList.remove('ia-ativo'); });
            tab.classList.add('ia-ativo');
            var alvo = document.querySelector('[data-tab-content="' + tab.dataset.tab + '"]');
            if (alvo) { alvo.classList.add('ia-ativo'); }
        });
    });

    var form = document.getElementById('iaTesteForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var pergunta = document.getElementById('iaTestePergunta').value.trim();
            var out = document.getElementById('iaTesteResultado');
            if (!pergunta) { return; }
            out.innerHTML = '<div class="ia-muted">Consultando a base local...</div>';
            fetch(window.IA_ASSISTENTE.api, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.IA_ASSISTENTE.csrf },
                body: JSON.stringify({ acao: 'ask', pergunta: pergunta, csrf: window.IA_ASSISTENTE.csrf, tela: window.IA_ASSISTENTE.tela })
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data.ok) { throw new Error(data.erro || 'Erro'); }
                var html = '<div class="ia-box" style="margin:0;"><h3>Resposta (' + data.modo + ')</h3>';
                html += '<div style="white-space:pre-wrap;font-size:13px;line-height:1.55;">' + (data.resposta || '').replace(/</g, '&lt;') + '</div>';
                if (data.fontes && data.fontes.length) {
                    html += '<h3 style="margin-top:14px;">Fontes</h3><ul style="font-size:12px;">';
                    data.fontes.forEach(function (f) {
                        html += '<li>' + (f.titulo || '') + (f.secao ? ' — ' + f.secao : '') + (f.caminho ? ' <code>' + f.caminho + '</code>' : '') + '</li>';
                    });
                    html += '</ul>';
                }
                html += '</div>';
                out.innerHTML = html;
            }).catch(function (err) {
                out.innerHTML = '<div class="ia-aviso ia-erro">' + (err.message || 'Erro ao consultar.') + '</div>';
            });
        });
    }
})();
</script>
<?php rodape(); ?>
