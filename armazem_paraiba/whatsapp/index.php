<?php
require_once __DIR__ . '/bootstrap.php';

verificaPermissao('/whatsapp/index.php');

$usuarioId = (int)($_SESSION['user_nb_id'] ?? 0);
$cfg = wa_config();
$providerAtivo = wa_config_ativa();
$erroContato = '';

// Resolve atalhos vindos dos cadastros: ?entidade=, ?user= ou ?telefone=.
$contatoSelecionado = null;
if (isset($_GET['entidade'])) {
    $contatoSelecionado = wa_contato_resolver_por_entidade((int)$_GET['entidade']);
    if (!$contatoSelecionado) {
        $erroContato = 'Funcionário sem telefone válido para WhatsApp.';
    }
} elseif (isset($_GET['user'])) {
    $contatoSelecionado = wa_contato_resolver_por_user((int)$_GET['user']);
    if (!$contatoSelecionado) {
        $erroContato = 'Usuário sem telefone válido para WhatsApp.';
    }
} elseif (isset($_GET['telefone'])) {
    $contatoSelecionado = wa_contato_resolver_por_telefone(strval($_GET['telefone']));
    if (!$contatoSelecionado) {
        $erroContato = 'Telefone inválido para WhatsApp.';
    }
} elseif (!empty($_GET['contato'])) {
    $contatoSelecionado = wa_contato_por_id((int)$_GET['contato']);
}

if ($contatoSelecionado && empty($_GET['contato'])) {
    header('Location: index.php?contato=' . (int)$contatoSelecionado['cont_nb_id']);
    exit;
}

$conversaSelecionada = null;
$mensagens = [];
$conversaId = 0;
if ($contatoSelecionado) {
    $conversaId = wa_conversa_garantir((int)$contatoSelecionado['cont_nb_id']);
    if ($conversaId > 0) {
        $conversaSelecionada = wa_conversa_por_id($conversaId);
        wa_marcar_conversa_lida($conversaId);
        $mensagens = wa_mensagens_listar($conversaId);
    }
}

$conversas = wa_conversa_listar(['filtro' => 'todas', 'usuario' => $usuarioId]);
$totalNaoLidas = 0;
foreach ($conversas as $cv) {
    $totalNaoLidas += (int)($cv['conv_nb_nao_lidas'] ?? 0);
}
$ultimoId = 0;
foreach ($mensagens as $m) {
    $ultimoId = max($ultimoId, (int)$m['msg_nb_id']);
}

if (!function_exists('wa_render_status_msg')) {
    function wa_render_status_msg(string $status): string {
        return match ($status) {
            'lido' => '<i class="fa fa-check-double" style="color:#34b7f1;" title="Lido"></i>',
            'entregue' => '<i class="fa fa-check-double" style="color:#8a9aa8;" title="Entregue"></i>',
            'enviado' => '<i class="fa fa-check" style="color:#8a9aa8;" title="Enviado"></i>',
            'falhou' => '<i class="fa fa-exclamation-circle" style="color:#e74c3c;" title="Falhou"></i>',
            default => '<i class="fa fa-clock-o" style="color:#b0b7bf;" title="Pendente"></i>',
        };
    }
}

if (!function_exists('wa_render_midia_mensagem')) {
    function wa_render_midia_mensagem(array $m): string {
        $tipo = strval($m['msg_tx_tipo'] ?? 'documento');
        $conteudo = trim(strval($m['msg_tx_conteudo'] ?? ''));
        $arquivo = basename(strval($m['msg_tx_arquivo'] ?? ''));
        $nome = strval($m['anexo_nome'] ?? '') ?: $arquivo;
        $url = wa_module_url('arquivo.php');
        if (!empty($m['anexo_id'])) {
            $url .= '?anexo=' . (int)$m['anexo_id'];
        } else {
            $url .= '?msg=' . (int)($m['msg_nb_id'] ?? 0);
        }
        $urlEsc = wa_e($url);
        $nomeEsc = wa_e($nome !== '' ? $nome : 'arquivo');
        $legenda = $conteudo !== '' ? '<div class="wa-midia-caption">' . nl2br(wa_e($conteudo)) . '</div>' : '';

        if ($arquivo === '' || !is_file(wa_midia_pasta() . '/' . $arquivo)) {
            $motivo = trim(strval($m['msg_tx_erro'] ?? ''));
            return '<div class="wa-midia-indisponivel"><i class="fa fa-paperclip"></i> '
                . 'Mídia indisponível' . ($motivo !== '' ? ' — ' . wa_e($motivo) : '') . '</div>' . $legenda;
        }

        if ($tipo === 'imagem') {
            return '<a href="' . $urlEsc . '" target="_blank" rel="noopener" class="wa-midia-link">'
                . '<img src="' . $urlEsc . '" alt="' . $nomeEsc . '" class="wa-midia-img" loading="lazy">'
                . '</a>' . $legenda;
        }
        if ($tipo === 'audio') {
            return '<audio class="wa-midia-audio" controls preload="metadata" src="' . $urlEsc . '"></audio>' . $legenda;
        }
        if ($tipo === 'video') {
            return '<video class="wa-midia-video" controls preload="metadata" src="' . $urlEsc . '"></video>' . $legenda;
        }
        return '<a class="wa-midia-doc" href="' . $urlEsc . '&amp;download=1" target="_blank" rel="noopener">'
            . '<i class="fa fa-file-o"></i><span>' . $nomeEsc . '</span><i class="fa fa-download"></i>'
            . '</a>' . $legenda;
    }
}

if (!function_exists('wa_render_mensagem')) {
    function wa_render_mensagem(array $m): string {
        $direcao = strval($m['msg_tx_direcao'] ?? 'in');
        $tipo = strval($m['msg_tx_tipo'] ?? 'texto');
        $conteudo = strval($m['msg_tx_conteudo'] ?? '');
        $texto = nl2br(wa_e($conteudo));
        if (in_array($tipo, ['imagem', 'audio', 'video', 'documento'], true)) {
            $texto = wa_render_midia_mensagem($m);
        } elseif ($tipo !== 'texto' && $tipo !== 'template' && $tipo !== 'sistema') {
            $texto = '<em>' . wa_e($conteudo !== '' ? $conteudo : '[' . $tipo . ']') . '</em>';
        }
        $data = strtotime(strval($m['msg_dt_criacao'] ?? ''));
        $hora = $data ? date('d/m/Y H:i', $data) : '';
        $status = $direcao === 'out' ? wa_render_status_msg(strval($m['msg_tx_status'] ?? 'pendente')) : '';
        $autor = $direcao === 'out'
            ? wa_e($m['usuario_nome'] ?? ($_SESSION['user_tx_nome'] ?? 'Você'))
            : 'Contato';
        return '<div class="wa-msg wa-msg-' . ($direcao === 'out' ? 'out' : 'in') . '" data-id="' . (int)$m['msg_nb_id'] . '">'
            . '<div class="wa-msg-meta"><strong>' . $autor . '</strong><span>' . wa_e($hora) . '</span></div>'
            . '<div class="wa-msg-body">' . $texto . '</div>'
            . ($status !== '' ? '<div class="wa-msg-status">' . $status . '</div>' : '')
            . '</div>';
    }
}

cabecalho('CRM - TECHPS');
?>
<link rel="stylesheet" href="<?= wa_e($CONTEX['path']) ?>/whatsapp/assets/whatsapp.css?v=9">

<div class="wa-app">
    <aside class="wa-sidebar">
        <div class="wa-sidebar-head">
            <div>
                <div class="wa-eyebrow">Atendimento</div>
                <h2>
                    <i class="fa fa-whatsapp"></i> CRM - TECHPS
                    <span id="waTotalNaoLidas" class="wa-total-badge" style="<?= $totalNaoLidas > 0 ? '' : 'display:none;' ?>"><?= (int)$totalNaoLidas ?></span>
                </h2>
            </div>
            <button type="button" class="btn btn-success btn-sm" id="waNovoChat">
                <i class="fa fa-plus"></i> Novo
            </button>
        </div>

        <div class="wa-search">
            <i class="fa fa-search"></i>
            <input type="text" id="waBusca" placeholder="Buscar por nome ou telefone..." autocomplete="off">
        </div>

        <div class="wa-filtros">
            <button type="button" class="wa-filtro active" data-filtro="todas">Todas</button>
            <button type="button" class="wa-filtro" data-filtro="nao_lidas">Não lidas</button>
            <button type="button" class="wa-filtro" data-filtro="minhas">Minhas</button>
        </div>

        <div class="wa-lista" id="waListaConversas">
            <?php if (empty($conversas)): ?>
                <div class="wa-vazio-lista">
                    <i class="fa fa-comments-o"></i>
                    <p>Nenhuma conversa ainda.</p>
                    <small>Use o botão <strong>Novo</strong> ou abra o CRM - TECHPS por um cadastro.</small>
                </div>
            <?php else: ?>
                <?php foreach ($conversas as $cv): ?>
                    <?php
                    $cvId = (int)$cv['conv_nb_id'];
                    $naoLidas = (int)($cv['conv_nb_nao_lidas'] ?? 0);
                    $dataCv = strtotime(strval($cv['ultima_data'] ?? $cv['conv_dt_ultima_atividade'] ?? ''));
                    $horaCv = $dataCv ? date('d/m', $dataCv) : '';
                    $ultima = strval($cv['ultima_msg'] ?? $cv['conv_tx_ultima_mensagem'] ?? '');
                    ?>
                    <a class="wa-conversa <?= $conversaId === $cvId ? 'active' : '' ?>" href="index.php?contato=<?= (int)$cv['conv_nb_contato'] ?>" data-contato="<?= (int)$cv['conv_nb_contato'] ?>">
                        <div class="wa-avatar"><?= wa_e(mb_substr(strval($cv['cont_tx_nome'] ?? '?'), 0, 1)) ?></div>
                        <div class="wa-conversa-info">
                            <div class="wa-conversa-top">
                                <strong><?= wa_e($cv['cont_tx_nome'] ?? '') ?></strong>
                                <span><?= wa_e($horaCv) ?></span>
                            </div>
                            <div class="wa-conversa-bottom">
                                <span class="wa-preview"><?= wa_e($ultima !== '' ? $ultima : 'Sem mensagens') ?></span>
                                <?php if ($naoLidas > 0): ?><span class="wa-badge"><?= $naoLidas ?></span><?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <section class="wa-chat">
        <?php if (!$providerAtivo): ?>
            <div class="wa-alerta">
                <i class="fa fa-exclamation-triangle"></i>
                O provedor de WhatsApp do CRM - TECHPS ainda não está ativo.
                <a href="configuracoes.php">Configurar agora</a>
            </div>
        <?php endif; ?>
        <?php if ($erroContato !== ''): ?>
            <div class="wa-alerta">
                <i class="fa fa-info-circle"></i> <?= wa_e($erroContato) ?>
            </div>
        <?php endif; ?>

        <?php if ($conversaSelecionada): ?>
            <header class="wa-chat-head">
                <div class="wa-avatar wa-avatar-lg"><?= wa_e(mb_substr(strval($conversaSelecionada['cont_tx_nome'] ?? '?'), 0, 1)) ?></div>
                <div class="wa-chat-head-info">
                    <h3><?= wa_e($conversaSelecionada['cont_tx_nome'] ?? '') ?></h3>
                    <div class="wa-chat-head-meta">
                        <span><i class="fa fa-phone"></i> <?= wa_e(wa_formatar_telefone($conversaSelecionada['cont_tx_e164'] ?? $conversaSelecionada['cont_tx_telefone'] ?? '')) ?></span>
                        <?php if (!empty($conversaSelecionada['cont_tx_setor'])): ?><span><i class="fa fa-building-o"></i> <?= wa_e($conversaSelecionada['cont_tx_setor']) ?></span><?php endif; ?>
                        <?php if (!empty($conversaSelecionada['cont_tx_cargo'])): ?><span><i class="fa fa-briefcase"></i> <?= wa_e($conversaSelecionada['cont_tx_cargo']) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="wa-chat-head-actions">
                    <button type="button" class="btn btn-default btn-sm" id="waExportar" title="Exportar conversa em CSV" aria-label="Exportar conversa em CSV">
                        <i class="fa fa-download"></i>
                    </button>
                    <?php if (!empty($conversaSelecionada['cont_nb_entidade'])): ?>
                        <a class="btn btn-default btn-sm" href="../cadastro_funcionario.php?acao=modificarMotorista&id=<?= (int)$conversaSelecionada['cont_nb_entidade'] ?>" title="Abrir cadastro"><i class="fa fa-user"></i></a>
                    <?php elseif (!empty($conversaSelecionada['cont_nb_user'])): ?>
                        <a class="btn btn-default btn-sm" href="../cadastro_usuario.php?id=<?= (int)$conversaSelecionada['cont_nb_user'] ?>" title="Abrir cadastro"><i class="fa fa-user"></i></a>
                    <?php endif; ?>
                </div>
            </header>

            <div class="wa-mensagens" id="waMensagens">
                <?php if (empty($mensagens)): ?>
                    <div class="wa-conversa-vazia" id="waConversaVazia">
                        <i class="fa fa-comments-o"></i>
                        <p>Nenhuma mensagem nesta conversa.</p>
                        <small>Envie a primeira mensagem abaixo.</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($mensagens as $m) echo wa_render_mensagem($m); ?>
                <?php endif; ?>
            </div>

            <div class="wa-composer">
                <button type="button" class="btn btn-default wa-btn-anexar" id="waAnexar" title="Anexar imagem, áudio, vídeo ou documento" aria-label="Anexar arquivo" <?= $providerAtivo ? '' : 'disabled' ?>>
                    <i class="fa fa-paperclip"></i>
                </button>
                <input type="file" id="waArquivo" accept="image/jpeg,image/png,image/webp,audio/*,video/mp4,video/3gpp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" style="display:none;">
                <button type="button" class="btn btn-default wa-btn-camera" id="waCameraBtn" title="Tirar foto / usar câmera" aria-label="Tirar foto" <?= $providerAtivo ? '' : 'disabled' ?>>
                    <i class="fa fa-camera"></i>
                </button>
                <input type="file" id="waCamera" accept="image/*" capture="environment" style="display:none;">
                <button type="button" class="btn btn-default wa-btn-microfone" id="waGravarAudio" title="Gravar áudio" aria-label="Gravar áudio" <?= $providerAtivo ? '' : 'disabled' ?>>
                    <i class="fa fa-microphone"></i>
                </button>
                <div class="wa-composer-main">
                    <textarea id="waTexto" class="form-control" rows="2" maxlength="4096" placeholder="Escreva uma mensagem... (Enter envia, Shift+Enter quebra linha)" <?= $providerAtivo ? '' : 'disabled' ?>></textarea>
                    <div class="wa-gravacao-info" id="waGravacaoInfo" style="display:none;">
                        <span class="wa-gravacao-dot"></span>
                        <span>Gravando áudio <strong id="waGravacaoTempo">00:00</strong></span>
                        <button type="button" class="wa-gravacao-cancelar" id="waGravacaoCancelar" title="Cancelar gravação">Cancelar</button>
                    </div>
                    <div class="wa-arquivo-info" id="waArquivoInfo" style="display:none;">
                        <img id="waArquivoPreview" alt="" style="display:none;">
                        <div class="wa-arquivo-dados">
                            <strong id="waArquivoNome"></strong>
                            <small id="waArquivoTamanho"></small>
                        </div>
                        <button type="button" class="wa-arquivo-remover" id="waArquivoRemover" title="Remover arquivo">&times;</button>
                    </div>
                </div>
                <button type="button" class="btn btn-success" id="waEnviar" <?= $providerAtivo ? '' : 'disabled' ?>>
                    <i class="fa fa-paper-plane"></i> Enviar
                </button>
            </div>
        <?php else: ?>
            <div class="wa-empty">
                <div class="wa-empty-icon"><i class="fa fa-whatsapp"></i></div>
                <h3>Selecione uma conversa</h3>
                <p>Escolha um contato na lista ao lado ou inicie um novo chat.</p>
                <button type="button" class="btn btn-success" id="waNovoChatEmpty"><i class="fa fa-plus"></i> Novo chat</button>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Modal: novo chat -->
<div class="modal fade" id="waModalNovo" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-whatsapp"></i> Novo chat</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Buscar contato</label>
                    <input type="text" class="form-control" id="waBuscaContato" placeholder="Nome ou telefone...">
                </div>
                <div id="waResultadosContato" class="wa-resultados"></div>
                <hr>
                <div class="form-group">
                    <label>Ou cadastre um número</label>
                    <div class="row" style="margin:0;">
                        <div class="col-sm-6" style="padding-left:0;">
                            <input type="text" class="form-control" id="waNovoNome" placeholder="Nome (opcional)">
                        </div>
                        <div class="col-sm-6" style="padding-right:0;">
                            <input type="text" class="form-control" id="waNovoTelefone" placeholder="DDD + número">
                        </div>
                    </div>
                </div>
                <div id="waNovoErro" class="text-danger" style="font-size:12px;margin-top:6px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="waCriarContato"><i class="fa fa-plus"></i> Abrir conversa</button>
            </div>
        </div>
    </div>
</div>

<div class="wa-camera-modal" id="waCameraModal" aria-hidden="true">
    <div class="wa-camera-box" role="dialog" aria-modal="true" aria-label="Câmera">
        <div class="wa-camera-head">
            <strong><i class="fa fa-camera"></i> Câmera</strong>
            <button type="button" class="wa-camera-fechar" id="waCameraFechar" aria-label="Fechar">&times;</button>
        </div>
        <video id="waCameraVideo" autoplay playsinline muted></video>
        <div class="wa-camera-actions">
            <button type="button" class="btn btn-success" id="waCameraCapturar"><i class="fa fa-camera"></i> Tirar foto</button>
            <button type="button" class="btn btn-default" id="waCameraCancelar">Cancelar</button>
        </div>
    </div>
</div>

<div class="modal fade" id="waModalExportar" tabindex="-1" role="dialog" aria-labelledby="waModalExportarTitulo">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="waFormExportar" method="get" action="exportar.php" target="_blank">
                <input type="hidden" name="conversa" value="<?= (int)$conversaId ?>">
                <input type="hidden" name="csrf" value="<?= wa_e(wa_csrf_token()) ?>">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="waModalExportarTitulo"><i class="fa fa-download"></i> Exportar conversa</h4>
                </div>
                <div class="modal-body">
                    <p style="font-size:12px;color:#64748b;">O arquivo CSV inclui todas as mensagens da conversa no período escolhido, com data, direção, tipo, status e anexo.</p>
                    <div class="form-group">
                        <label style="display:block;">
                            <input type="radio" name="modo" value="dias" checked> Últimos
                        </label>
                        <input type="number" name="dias" id="waExportDias" class="form-control input-sm" min="1" max="3650" value="30" style="width:110px;display:inline-block;">
                        <span style="font-size:12px;color:#64748b;">dias</span>
                    </div>
                    <div class="form-group">
                        <label style="display:block;">
                            <input type="radio" name="modo" value="datas"> Intervalo de datas
                        </label>
                        <div class="row">
                            <div class="col-sm-6">
                                <label style="font-size:11px;">De</label>
                                <input type="date" name="inicio" id="waExportInicio" class="form-control input-sm" value="<?= date('Y-m-d', strtotime('-29 days')) ?>">
                            </div>
                            <div class="col-sm-6">
                                <label style="font-size:11px;">Até</label>
                                <input type="date" name="fim" id="waExportFim" class="form-control input-sm" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>
                    <div id="waExportErro" class="text-danger" style="font-size:12px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-download"></i> Exportar CSV</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.WA_CONFIG = {
    api: <?= json_encode(wa_e($CONTEX['path']) . '/whatsapp/api.php') ?>,
    csrf: <?= json_encode(wa_csrf_token()) ?>,
    conversaId: <?= (int)$conversaId ?>,
    contatoId: <?= (int)($conversaSelecionada['cont_nb_id'] ?? 0) ?>,
    ultimoId: <?= (int)$ultimoId ?>,
    usuarioId: <?= (int)$usuarioId ?>,
    ativo: <?= $providerAtivo ? 'true' : 'false' ?>,
    urlIndex: <?= json_encode(wa_e($CONTEX['path']) . '/whatsapp/index.php') ?>,
    urlContatos: <?= json_encode(wa_e($CONTEX['path']) . '/whatsapp/contatos.php') ?>,
    urlArquivo: <?= json_encode(wa_e($CONTEX['path']) . '/whatsapp/arquivo.php') ?>,
    maxMidia: <?= (int)wa_midia_limite_bytes() ?>,
    limitesMidia: <?= json_encode(wa_midia_limites(), JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= wa_e($CONTEX['path']) ?>/whatsapp/assets/whatsapp.js?v=9"></script>
<?php rodape(); ?>
