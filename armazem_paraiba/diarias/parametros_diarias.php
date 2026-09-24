<?php
include_once __DIR__."/helpers_diarias.php";
include_once "../check_permission.php";

// Acesso seguro a chaves de array sem gerar aviso quando nao existir.
function dp($arr, $k, $d = '') {
    return (is_array($arr) && isset($arr[$k])) ? $arr[$k] : $d;
}

function dp_setFlash($mensagem, $erro) {
    $_SESSION['diarias_param_msg'] = strval($mensagem);
    $_SESSION['diarias_param_erro'] = ($erro ? 1 : 0);
}

function dp_getFlash() {
    $mensagem = strval(dp($_SESSION, 'diarias_param_msg', ''));
    $erro = intval(dp($_SESSION, 'diarias_param_erro', 0)) === 1;
    unset($_SESSION['diarias_param_msg']);
    unset($_SESSION['diarias_param_erro']);
    return array($mensagem, $erro);
}

// Classifica o tipo de campo de cada chave para renderizacao e validacao.
function dp_tipoCampo($chave) {
    $tipos = array(
        'limite_km_almoco' => 'inteiro',
        'distancia_pernoite_km' => 'inteiro',
        'autogerar_consumo' => 'simnao',
        'limite_dias_autogeracao' => 'data',
        'url_api_logistica' => 'texto'
    );
    return isset($tipos[$chave]) ? $tipos[$chave] : 'moeda';
}

// Unidade de medida exibida ao lado do campo (identifica campos de distancia).
function dp_unidadeCampo($chave) {
    $unidades = array(
        'limite_km_almoco' => 'km',
        'distancia_pernoite_km' => 'km'
    );
    return isset($unidades[$chave]) ? $unidades[$chave] : '';
}

// Persiste os valores informados na tela de parametros.
function dp_salvarParametros() {
    if (!diar_isSuperAdmin()) {
        return array('Apenas super administrador pode alterar os parametros.', true);
    }

    $chavesPermitidas = array_keys(diar_parametrosPadrao());
    $salvos = 0;

    foreach ($chavesPermitidas as $chave) {
        if (!isset($_POST[$chave])) {
            continue;
        }
        // Campo somente leitura (configurado pelo sistema).
        if ($chave === 'url_api_logistica') {
            continue;
        }
        $valor = trim(strval($_POST[$chave]));
        $tipo = dp_tipoCampo($chave);

        switch ($tipo) {
            case 'inteiro':
                $valor = strval(max(0, intval($valor)));
                break;
            case 'simnao':
                $valor = ($valor === 'sim') ? 'sim' : 'nao';
                break;
            case 'data':
                $valor = trim(strval($valor));
                if ($valor !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
                    $valor = '';
                }
                break;
            case 'texto':
                $valor = trim(strval($valor));
                break;
            default:
                $valor = ($valor === '') ? '0' : strval(max(0, diar_parseValorMonetario($valor)));
                break;
        }

        if (diar_salvarParametro($chave, $valor)) {
            $salvos++;
        }
    }

    diar_log_runtime("Parametros atualizados: {$salvos} item(ns) salvos");
    diar_logEvento('parametros_salvos', 'Parametros de diarias atualizados', array('itens_salvos' => $salvos));
    return array('Parametros salvos com sucesso ('.$salvos.' item(ns)).', false);
}

// Entry-point do Contex para acao do formulario (acao=salvarParametros).
function salvarParametros() {
    list($mensagem, $erro) = dp_salvarParametros();
    dp_setFlash($mensagem, $erro);
    header('Location: parametros_diarias.php');
    exit;
}

// Entry-point do Contex: zera todos os lancamentos de diarias (acao=zerarLancamentosDiarias).
// Exige super admin + senha do usuario logado + confirmacao digitada "ZERAR".
function zerarLancamentosDiarias() {
    if (!diar_isSuperAdmin()) {
        diar_logEvento('reset_negado', 'Tentativa de zerar lancamentos sem permissao de super admin');
        dp_setFlash('ERRO: Apenas super administrador pode zerar os lancamentos.', true);
        header('Location: parametros_diarias.php');
        exit;
    }

    $confirmacao = strtoupper(trim(strval(dp($_POST, 'texto_confirmacao', ''))));
    if ($confirmacao !== 'ZERAR') {
        dp_setFlash('ERRO: Digite ZERAR no campo de confirmacao para liberar a exclusao.', true);
        header('Location: parametros_diarias.php');
        exit;
    }

    if (!diar_verificarSenhaUsuario(dp($_POST, 'senha_confirmacao', ''))) {
        diar_logEvento('reset_negado', 'Senha incorreta na confirmacao da zeragem dos lancamentos');
        dp_setFlash('ERRO: Senha incorreta. Nenhum dado foi apagado.', true);
        header('Location: parametros_diarias.php');
        exit;
    }

    $resultado = diar_zerarLancamentos();
    if ($resultado['ok']) {
        diar_logEvento('reset_lancamentos', 'Lancamentos de diarias zerados pelo super admin', array(
            'consumos_apagados' => $resultado['consumos'],
            'depositos_apagados' => $resultado['depositos']
        ));
        dp_setFlash('Lancamentos zerados com sucesso: '.$resultado['consumos'].' consumo(s) e '.$resultado['depositos'].' deposito(s) apagados.', false);
    } else {
        diar_logEvento('reset_erro', 'Falha ao zerar os lancamentos de diarias');
        dp_setFlash('ERRO: Nao foi possivel zerar os lancamentos. Consulte os logs do modulo.', true);
    }
    header('Location: parametros_diarias.php');
    exit;
}

include_once "../conecta.php";

diar_ensureSchema();

if (!diar_isSuperAdmin()) {
    set_status("ERRO: Acesso restrito a super administradores.");
    header("Location: ../index.php");
    exit;
}

list($mensagem, $erro) = dp_getFlash();
$parametros = diar_buscarParametros();
$contagens = diar_contarLancamentos();

cabecalho("Parametros de Diarias");
?>

<div class="row">
    <div class="col-md-12">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <span class="caption-subject bold font-dark">Parametros de Diarias (Clausula Decima Quarta)</span>
                </div>
            </div>
            <div class="portlet-body">
                <?php if ($mensagem !== ''): ?>
                    <div class="alert <?php echo $erro ? 'alert-danger' : 'alert-success'; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
                <?php endif; ?>

                <div class="alert alert-info" style="font-size:13px;">
                    Valores fixados na clausula: A) com pernoite R$ 107,00 - intermunicipais e/ou interestaduais;
                    B) sem pernoite R$ 55,00 - retorno a base com km de ida ACIMA do limite (80 km);
                    C) almoco R$ 40,00 - retorno a base com km de ida ATE o limite (80 km).
                    Ajuste os valores abaixo somente em caso de nova convencao coletiva.
                </div>

                <form method="post">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Chave</th>
                                <th>Descricao</th>
                                <th style="width:220px;">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (diar_parametrosPadrao() as $chave => $dados): ?>
                            <?php
                                $valorAtual = strval(dp($parametros, $chave, $dados[0]));
                                $tipoCampo = dp_tipoCampo($chave);
                            ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($chave); ?></code></td>
                                <td><?php echo htmlspecialchars($dados[1]); ?></td>
                                <td>
                                    <?php if ($tipoCampo === 'moeda'): ?>
                                        <input type="text" class="form-control input-sm" name="<?php echo htmlspecialchars($chave); ?>"
                                               value="<?php echo htmlspecialchars(diar_formatarValor($valorAtual)); ?>" data-mask-money>
                                    <?php elseif ($tipoCampo === 'inteiro'): ?>
                                        <div class="input-group">
                                            <input type="number" class="form-control input-sm" name="<?php echo htmlspecialchars($chave); ?>"
                                                   value="<?php echo htmlspecialchars($valorAtual); ?>" min="0" step="1">
                                            <?php if (dp_unidadeCampo($chave) !== ''): ?>
                                                <span class="input-group-addon"><?php echo htmlspecialchars(dp_unidadeCampo($chave)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($tipoCampo === 'hora'): ?>
                                        <input type="time" class="form-control input-sm" name="<?php echo htmlspecialchars($chave); ?>"
                                               value="<?php echo htmlspecialchars($valorAtual); ?>">
                                    <?php elseif ($tipoCampo === 'data'): ?>
                                        <input type="date" class="form-control input-sm" name="<?php echo htmlspecialchars($chave); ?>"
                                               value="<?php echo htmlspecialchars($valorAtual); ?>">
                                    <?php elseif ($tipoCampo === 'texto'): ?>
                                        <input type="text" class="form-control input-sm" name="<?php echo htmlspecialchars($chave); ?>"
                                               value="<?php echo htmlspecialchars($valorAtual); ?>" placeholder="http://servidor:porta"
                                               <?php echo ($chave === 'url_api_logistica') ? 'readonly title="Campo somente leitura — configurado pelo sistema"' : ''; ?>>
                                    <?php else: ?>
                                        <?php
                                            $opcoes = array();
                                            if ($tipoCampo === 'simnao') {
                                                $opcoes = array('sim' => 'Sim', 'nao' => 'Nao');
                                            }
                                        ?>
                                        <select class="form-control input-sm" name="<?php echo htmlspecialchars($chave); ?>">
                                            <?php foreach ($opcoes as $opVal => $opLabel): ?>
                                                <option value="<?php echo htmlspecialchars($opVal); ?>"
                                                    <?php echo $valorAtual === $opVal ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($opLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="margin-top:10px;">
                        <?php echo botao('Salvar Parametros', 'salvarParametros', '', '', '', '', 'btn btn-success'); ?>
                        <a href="../index.php" class="btn btn-default">Cancelar</a>
                    </div>
                </form>

                <hr style="margin:25px 0 15px;">

                <div class="alert alert-danger" style="margin-bottom:0;">
                    <h4 style="margin-top:0;"><i class="fa fa-exclamation-triangle"></i> Zona de perigo — zerar lancamentos</h4>
                    <p style="margin-bottom:8px;">
                        Esta acao apaga <strong>todos os lancamentos</strong> de diarias: consumos
                        (manuais e gerados automaticamente) e depositos.
                        As configuracoes (parametros, bases e POI base) sao mantidas.
                    </p>
                    <p style="margin-bottom:12px;">
                        Atualmente existem <strong><?php echo intval($contagens['consumos']); ?></strong> consumo(s)
                        (<?php echo intval($contagens['consumos_auto']); ?> automatico(s)) e
                        <strong><?php echo intval($contagens['depositos']); ?></strong> deposito(s) lancados.
                        <br><strong>Esta acao nao pode ser desfeita.</strong>
                    </p>
                    <form method="post" id="form_zerar_diarias" class="form-inline">
                        <input type="hidden" name="acao" value="zerarLancamentosDiarias">
                        <div class="form-group" style="margin-right:8px;">
                            <input type="password" class="form-control input-sm" name="senha_confirmacao" id="senha_zerar"
                                   placeholder="Senha do super admin" autocomplete="new-password" style="width:220px;">
                        </div>
                        <div class="form-group" style="margin-right:8px;">
                            <input type="text" class="form-control input-sm" name="texto_confirmacao" id="texto_zerar"
                                   placeholder="Digite ZERAR" autocomplete="off" style="width:160px;">
                        </div>
                        <button type="submit" class="btn btn-danger btn-sm" id="btn_zerar_diarias" disabled>
                            <i class="fa fa-trash"></i> Zerar dados de diarias
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Aplica mascara monetaria nos campos de valor quando a lib estiver disponivel.
document.addEventListener('DOMContentLoaded', function () {
    var campos = document.querySelectorAll('[data-mask-money]');
    if (typeof jQuery !== 'undefined' && jQuery.fn.maskMoney) {
        jQuery(campos).maskMoney({prefix: 'R$', allowNegative: false, thousands: '.', decimal: ',', affixesStay: false});
    }
});

// Zona de perigo: libera o botao apenas com ZERAR digitado + senha, e confirma via SweetAlert2.
(function () {
    var form = document.getElementById('form_zerar_diarias');
    if (!form) { return; }
    var texto = document.getElementById('texto_zerar');
    var senha = document.getElementById('senha_zerar');
    var btn = document.getElementById('btn_zerar_diarias');

    function validar() {
        var ok = texto && texto.value.trim().toUpperCase() === 'ZERAR' && senha && senha.value !== '';
        if (btn) { btn.disabled = !ok; }
    }
    if (texto) { texto.addEventListener('input', validar); }
    if (senha) { senha.addEventListener('input', validar); }
    validar();

    form.addEventListener('submit', function (e) {
        if (!texto || texto.value.trim().toUpperCase() !== 'ZERAR' || !senha || senha.value === '') {
            e.preventDefault();
            return;
        }
        if (typeof Swal !== 'undefined') {
            e.preventDefault();
            Swal.fire({
                title: 'Zerar todos os lancamentos?',
                html: 'Todos os consumos e depositos serao apagados.<br><strong>Esta acao nao pode ser desfeita.</strong>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Sim, zerar tudo'
            }).then(function (r) {
                if (r.isConfirmed) { form.submit(); }
            });
        }
    });
})();
</script>

<?php rodape(); ?>
