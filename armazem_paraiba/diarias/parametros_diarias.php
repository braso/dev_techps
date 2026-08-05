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
        $valor = trim(strval($_POST[$chave]));
        $valor = ($valor === '') ? '0' : $valor;

        if ($chave === 'limite_km_almoco') {
            $valor = strval(max(0, intval($valor)));
        } else {
            $valor = strval(max(0, diar_parseValorMonetario($valor)));
        }

        if (diar_salvarParametro($chave, $valor)) {
            $salvos++;
        }
    }

    diar_log_runtime("Parametros atualizados: {$salvos} item(ns) salvos");
    return array('Parametros salvos com sucesso ('.$salvos.' item(ns)).', false);
}

// Entry-point do Contex para acao do formulario (acao=salvarParametros).
function salvarParametros() {
    list($mensagem, $erro) = dp_salvarParametros();
    dp_setFlash($mensagem, $erro);
    header('Location: parametros_diarias.php');
    exit;
}

if (dp($_POST, 'acao', '') === 'salvarParametros') {
    salvarParametros();
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
                    B) sem pernoite R$ 55,00; C) almoco R$ 40,00 - percursos de ate 80 km (ida) com retorno a base.
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
                                $ehValor = ($chave !== 'limite_km_almoco');
                            ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($chave); ?></code></td>
                                <td><?php echo htmlspecialchars($dados[1]); ?></td>
                                <td>
                                    <?php if ($chave === 'limite_km_almoco'): ?>
                                        <input type="number" class="form-control input-sm" name="<?php echo htmlspecialchars($chave); ?>"
                                               value="<?php echo htmlspecialchars($valorAtual); ?>" min="0" step="1">
                                    <?php else: ?>
                                        <input type="text" class="form-control input-sm" name="<?php echo htmlspecialchars($chave); ?>"
                                               value="<?php echo htmlspecialchars(diar_formatarValor($valorAtual)); ?>" data-mask-money>
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
</script>

<?php rodape(); ?>
