<?php
require_once __DIR__ . '/bootstrap.php';

verificaPermissao('/whatsapp/mensagens_prontas.php');

$usuarioId = (int)($_SESSION['user_nb_id'] ?? 0);
$mensagem = trim(strval($_GET['ok'] ?? ''));
$erro = trim(strval($_GET['erro'] ?? ''));

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!wa_csrf_validar(isset($_POST['csrf']) ? strval($_POST['csrf']) : '')) {
        header('Location: mensagens_prontas.php?erro=' . rawurlencode('Sessão expirada. Atualize a página.'));
        exit;
    }

    $acao = strval($_POST['wa_prontas_acao'] ?? '');
    if ($acao === 'salvar') {
        $resultado = wa_mensagem_pronta_salvar([
            'id' => (int)($_POST['id'] ?? 0),
            'titulo' => $_POST['titulo'] ?? '',
            'subtitulo' => $_POST['subtitulo'] ?? '',
            'mensagem' => $_POST['mensagem'] ?? '',
            'status' => $_POST['status'] ?? 'ativo',
        ], $usuarioId);
        $param = !empty($resultado['success']) ? 'ok' : 'erro';
        header('Location: mensagens_prontas.php?' . $param . '=' . rawurlencode(strval($resultado['message'] ?? 'Operação concluída.')));
        exit;
    }

    if ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        $ok = wa_mensagem_pronta_excluir($id);
        header('Location: mensagens_prontas.php?' . ($ok ? 'ok' : 'erro') . '=' . rawurlencode($ok ? 'Mensagem excluída.' : 'Não foi possível excluir.'));
        exit;
    }

    header('Location: mensagens_prontas.php?erro=' . rawurlencode('Ação inválida.'));
    exit;
}

$prontas = wa_mensagens_prontas_listar(false);

// Títulos funcionam como tags reutilizáveis; os subtítulos alimentam o
// datalist conforme a tag escolhida.
$tagsTitulos = [];
$subtagsPorTitulo = [];
$todasSubtags = [];
foreach ($prontas as $p) {
    $tituloTag = trim(strval($p['mpr_tx_titulo'] ?? ''));
    $subTag = trim(strval($p['mpr_tx_subtitulo'] ?? ''));
    if ($tituloTag !== '') {
        $tagsTitulos[$tituloTag] = $tituloTag;
        if (!isset($subtagsPorTitulo[$tituloTag])) {
            $subtagsPorTitulo[$tituloTag] = [];
        }
        if ($subTag !== '') {
            $subtagsPorTitulo[$tituloTag][$subTag] = $subTag;
            $todasSubtags[$subTag] = $subTag;
        }
    }
}
$tagsTitulos = array_values($tagsTitulos);
$todasSubtags = array_values($todasSubtags);
foreach ($subtagsPorTitulo as $k => $lista) {
    $subtagsPorTitulo[$k] = array_values($lista);
}

cabecalho('Mensagens Prontas - CRM - TECHPS');
?>
<link rel="stylesheet" href="<?= wa_e($CONTEX['path']) ?>/whatsapp/assets/whatsapp.css?v=23">
<style>
.wa-prontas-card { background:#fff; border:1px solid #e4e9ef; border-radius:16px; box-shadow:0 4px 18px rgba(15,23,42,.06); overflow:hidden; margin:4px 0 20px; }
.wa-prontas-head { padding:16px 20px; border-bottom:1px solid #eef2f6; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.wa-prontas-head h3 { margin:0; font-size:17px; font-weight:800; color:#0f172a; }
.wa-prontas-head p { margin:4px 0 0; font-size:11.5px; color:#64748b; }
.wa-prontas-body { padding:0 20px 18px; }
.wa-prontas-tabela { width:100%; border-collapse:collapse; font-size:11.5px; }
.wa-prontas-tabela th { background:#f8fafc; color:#64748b; font-size:9px; text-transform:uppercase; letter-spacing:.7px; text-align:left; padding:8px 9px; border-bottom:1px solid #e5e7eb; }
.wa-prontas-tabela td { padding:9px; border-bottom:1px solid #f4f7fa; vertical-align:top; }
.wa-prontas-tabela tr:last-child td { border-bottom:0; }
.wa-prontas-titulo { font-weight:700; color:#1e293b; }
.wa-prontas-sub { color:#2563eb; font-weight:700; }
.wa-prontas-texto { max-width:420px; color:#475569; white-space:pre-wrap; }
.wa-prontas-acoes { display:flex; gap:5px; flex-wrap:wrap; }
.wa-prontas-vazio { padding:34px 20px; text-align:center; color:#94a3b8; font-size:12px; }
.wa-prontas-ajuda { background:#f8fafc; border:1px solid #eef2f6; border-radius:10px; padding:10px 12px; margin:12px 0; font-size:11px; color:#64748b; }
.wa-prontas-ajuda code { color:#15803d; }
.wa-prontas-tags-lista { display:flex; flex-wrap:wrap; gap:5px; margin-top:6px; max-height:96px; overflow-y:auto; }
.wa-prontas-tags-lista > span { font-size:10px; color:#94a3b8; width:100%; }
.wa-tag-opcao {
    border:1px solid #dbeafe;
    background:#eff6ff;
    color:#1d4ed8;
    border-radius:999px;
    padding:4px 9px;
    font-size:10px;
    font-weight:700;
    max-width:100%;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    cursor:pointer;
}
.wa-tag-opcao:hover { background:#dbeafe; border-color:#bfdbfe; }
.wa-prontas-tag-vazia { font-size:10px; color:#94a3b8; }
.wa-variaveis {
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:5px;
    margin-top:6px;
    padding:8px;
    border:1px dashed #bbf7d0;
    border-radius:9px;
    background:#f0fdf4;
}
.wa-variaveis > span {
    width:100%;
    font-size:9px;
    font-weight:800;
    letter-spacing:.7px;
    text-transform:uppercase;
    color:#15803d;
}
.wa-variavel {
    border:1px solid #86efac;
    background:#fff;
    color:#15803d;
    border-radius:999px;
    padding:4px 8px;
    font-family:Consolas, monospace;
    font-size:10px;
    font-weight:700;
    cursor:pointer;
}
.wa-variavel:hover { background:#dcfce7; }
.wa-variavel:active { transform:translateY(1px); }
</style>

<div class="wa-prontas-card">
    <div class="wa-prontas-head">
        <div>
            <h3><i class="fa fa-commenting-o" style="color:#25d366;"></i> Mensagens Prontas</h3>
            <p>O título indica quando usar; o subtítulo é a variação que aparece no lote e no comando <code>/</code> do chat.</p>
        </div>
        <button type="button" class="btn btn-success btn-sm" id="waNovaPronta" data-toggle="modal" data-target="#waModalPronta">
            <i class="fa fa-plus"></i> Nova mensagem
        </button>
    </div>
    <div class="wa-prontas-body">
        <?php if ($mensagem !== ''): ?>
            <div class="alert alert-success" style="margin-top:14px;"><i class="fa fa-check-circle"></i> <?= wa_e($mensagem) ?></div>
        <?php endif; ?>
        <?php if ($erro !== ''): ?>
            <div class="alert alert-danger" style="margin-top:14px;"><i class="fa fa-exclamation-circle"></i> <?= wa_e($erro) ?></div>
        <?php endif; ?>

        <div class="wa-prontas-ajuda">
            Variáveis disponíveis: <code>{nome}</code>, <code>{primeiro_nome}</code>, <code>{setor}</code>, <code>{cargo}</code>, <code>{empresa}</code> e <code>{telefone}</code>.
            No chat, digite <code>/</code> para abrir a lista. No lote, escolha o título e depois o subtítulo.
        </div>

        <?php if (empty($prontas)): ?>
            <div class="wa-prontas-vazio"><i class="fa fa-commenting-o" style="font-size:30px;display:block;margin-bottom:8px;color:#cbd5e1;"></i> Nenhuma mensagem pronta cadastrada.</div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="wa-prontas-tabela">
                    <thead>
                        <tr><th>Título / quando usar</th><th>Subtítulo</th><th>Mensagem</th><th>Status</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($prontas as $p): ?>
                        <tr>
                            <td class="wa-prontas-titulo"><?= wa_e($p['mpr_tx_titulo'] ?? '') ?></td>
                            <td class="wa-prontas-sub"><?= wa_e($p['mpr_tx_subtitulo'] ?? '') ?></td>
                            <td><div class="wa-prontas-texto"><?= wa_e($p['mpr_tx_mensagem'] ?? '') ?></div></td>
                            <td><span class="label label-<?= strval($p['mpr_tx_status'] ?? '') === 'ativo' ? 'success' : 'default' ?>"><?= strval($p['mpr_tx_status'] ?? '') === 'ativo' ? 'Ativo' : 'Inativo' ?></span></td>
                            <td>
                                <div class="wa-prontas-acoes">
                                    <button type="button" class="btn btn-default btn-xs wa-editar-pronta"
                                        data-id="<?= (int)($p['mpr_nb_id'] ?? 0) ?>"
                                        data-titulo="<?= wa_e($p['mpr_tx_titulo'] ?? '') ?>"
                                        data-subtitulo="<?= wa_e($p['mpr_tx_subtitulo'] ?? '') ?>"
                                        data-mensagem="<?= wa_e($p['mpr_tx_mensagem'] ?? '') ?>"
                                        data-status="<?= wa_e($p['mpr_tx_status'] ?? 'ativo') ?>"
                                        data-toggle="modal" data-target="#waModalPronta">
                                        <i class="fa fa-pencil"></i> Editar
                                    </button>
                                    <form method="post" action="mensagens_prontas.php" style="display:inline;" onsubmit="return confirm('Excluir esta mensagem pronta?');">
                                        <input type="hidden" name="csrf" value="<?= wa_e(wa_csrf_token()) ?>">
                                        <input type="hidden" name="wa_prontas_acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int)($p['mpr_nb_id'] ?? 0) ?>">
                                        <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i> Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="waModalPronta" tabindex="-1" role="dialog" aria-labelledby="waModalProntaTitulo">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="mensagens_prontas.php" id="waFormPronta">
                <input type="hidden" name="csrf" value="<?= wa_e(wa_csrf_token()) ?>">
                <input type="hidden" name="wa_prontas_acao" value="salvar">
                <input type="hidden" name="id" id="waProntaId" value="0">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="waModalProntaTitulo"><i class="fa fa-commenting-o"></i> Mensagem pronta</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Título / tag *</label>
                        <input type="text" name="titulo" id="waProntaTitulo" class="form-control input-sm" maxlength="180" required list="waTagsTitulos" autocomplete="off" placeholder="Selecione uma tag ou digite uma nova">
                        <datalist id="waTagsTitulos">
                            <?php foreach ($tagsTitulos as $tag): ?><option value="<?= wa_e($tag) ?>"></option><?php endforeach; ?>
                        </datalist>
                        <div class="wa-prontas-tags-lista">
                            <span>Tags existentes — clique para reutilizar:</span>
                            <?php if (empty($tagsTitulos)): ?>
                                <span class="wa-prontas-tag-vazia">Nenhuma tag cadastrada ainda.</span>
                            <?php else: ?>
                                <?php foreach ($tagsTitulos as $tag): ?>
                                    <button type="button" class="wa-tag-opcao" data-tag="<?= wa_e($tag) ?>" title="<?= wa_e($tag) ?>"><?= wa_e($tag) ?></button>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted" style="font-size:10px;">A tag agrupa as variações; digite uma nova se necessário.</small>
                    </div>
                    <div class="form-group">
                        <label>Subtítulo *</label>
                        <input type="text" name="subtitulo" id="waProntaSubtitulo" class="form-control input-sm" maxlength="180" required list="waTagsSubtitulos" autocomplete="off" placeholder="Ex.: T1 Template único, mensagem personalizada">
                        <datalist id="waTagsSubtitulos">
                            <?php foreach ($todasSubtags as $sub): ?><option value="<?= wa_e($sub) ?>"></option><?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label>Mensagem *</label>
                        <textarea name="mensagem" id="waProntaMensagem" class="form-control" rows="7" maxlength="4096" required placeholder="Olá {primeiro_nome}, ..."></textarea>
                        <div class="wa-variaveis" id="waVariaveisMensagem">
                            <span>Inserir variável na posição do cursor</span>
                            <button type="button" class="wa-variavel" data-variavel="{nome}">{nome}</button>
                            <button type="button" class="wa-variavel" data-variavel="{primeiro_nome}">{primeiro_nome}</button>
                            <button type="button" class="wa-variavel" data-variavel="{setor}">{setor}</button>
                            <button type="button" class="wa-variavel" data-variavel="{cargo}">{cargo}</button>
                            <button type="button" class="wa-variavel" data-variavel="{empresa}">{empresa}</button>
                            <button type="button" class="wa-variavel" data-variavel="{telefone}">{telefone}</button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" id="waProntaStatus" class="form-control input-sm">
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var form = document.getElementById('waFormPronta');
    var subtagsPorTag = <?= wa_json_script($subtagsPorTitulo) ?>;
    var todasSubtags = <?= wa_json_script($todasSubtags) ?>;

    function atualizarSubtags(titulo) {
        var datalist = document.getElementById('waTagsSubtitulos');
        if (!datalist) return;
        var lista = subtagsPorTag[titulo] || todasSubtags;
        datalist.innerHTML = '';
        (lista || []).forEach(function (sub) {
            var opt = document.createElement('option');
            opt.value = sub;
            datalist.appendChild(opt);
        });
    }

    var campoMensagem = document.getElementById('waProntaMensagem');
    var ultimaPosicaoMensagem = campoMensagem ? campoMensagem.value.length : 0;

    function guardarPosicaoMensagem() {
        if (!campoMensagem) return;
        if (typeof campoMensagem.selectionStart === 'number') {
            ultimaPosicaoMensagem = campoMensagem.selectionStart;
        }
    }

    function inserirVariavel(variavel) {
        if (!campoMensagem || !variavel) return;
        var valor = campoMensagem.value;
        var focado = document.activeElement === campoMensagem;
        var inicio = focado && typeof campoMensagem.selectionStart === 'number' ? campoMensagem.selectionStart : ultimaPosicaoMensagem;
        var fim = focado && typeof campoMensagem.selectionEnd === 'number' ? campoMensagem.selectionEnd : inicio;
        if (inicio < 0 || inicio > valor.length) inicio = valor.length;
        if (fim < inicio || fim > valor.length) fim = inicio;
        campoMensagem.value = valor.slice(0, inicio) + variavel + valor.slice(fim);
        var novaPosicao = inicio + variavel.length;
        campoMensagem.focus();
        if (campoMensagem.setSelectionRange) campoMensagem.setSelectionRange(novaPosicao, novaPosicao);
        ultimaPosicaoMensagem = novaPosicao;
    }

    if (campoMensagem) {
        ['click', 'keyup', 'input', 'select', 'blur'].forEach(function (evento) {
            campoMensagem.addEventListener(evento, guardarPosicaoMensagem);
        });
    }

    document.querySelectorAll('.wa-variavel').forEach(function (btn) {
        btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
        btn.addEventListener('click', function () {
            inserirVariavel(btn.getAttribute('data-variavel') || '');
        });
    });

    function limpar() {
        document.getElementById('waProntaId').value = '0';
        document.getElementById('waProntaTitulo').value = '';
        document.getElementById('waProntaSubtitulo').value = '';
        document.getElementById('waProntaMensagem').value = '';
        document.getElementById('waProntaStatus').value = 'ativo';
        document.getElementById('waModalProntaTitulo').innerHTML = '<i class="fa fa-commenting-o"></i> Nova mensagem pronta';
        ultimaPosicaoMensagem = 0;
        atualizarSubtags('');
    }

    var nova = document.getElementById('waNovaPronta');
    if (nova) nova.addEventListener('click', limpar);

    document.querySelectorAll('.wa-tag-opcao').forEach(function (tag) {
        tag.addEventListener('click', function () {
            var titulo = tag.getAttribute('data-tag') || '';
            var campo = document.getElementById('waProntaTitulo');
            if (campo) {
                campo.value = titulo;
                atualizarSubtags(titulo);
            }
            var sub = document.getElementById('waProntaSubtitulo');
            if (sub) sub.focus();
        });
    });

    var campoTitulo = document.getElementById('waProntaTitulo');
    if (campoTitulo) {
        campoTitulo.addEventListener('input', function () {
            atualizarSubtags(this.value.trim());
        });
    }

    document.querySelectorAll('.wa-editar-pronta').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var titulo = btn.getAttribute('data-titulo') || '';
            document.getElementById('waProntaId').value = btn.getAttribute('data-id') || '0';
            document.getElementById('waProntaTitulo').value = titulo;
            document.getElementById('waProntaSubtitulo').value = btn.getAttribute('data-subtitulo') || '';
            document.getElementById('waProntaMensagem').value = btn.getAttribute('data-mensagem') || '';
            ultimaPosicaoMensagem = document.getElementById('waProntaMensagem').value.length;
            document.getElementById('waProntaStatus').value = btn.getAttribute('data-status') || 'ativo';
            document.getElementById('waModalProntaTitulo').innerHTML = '<i class="fa fa-pencil"></i> Editar mensagem pronta';
            atualizarSubtags(titulo);
        });
    });
})();
</script>
<?php rodape(); ?>
