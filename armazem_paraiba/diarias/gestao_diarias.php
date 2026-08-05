<?php
include_once __DIR__."/helpers_diarias.php";
include_once "../check_permission.php";

// Acesso seguro a chaves de array sem gerar aviso quando nao existir.
function dg($arr, $k, $d = '') {
    return (is_array($arr) && isset($arr[$k])) ? $arr[$k] : $d;
}

function dg_setFlashGestao($mensagem, $erro) {
    $_SESSION['diarias_gestao_msg'] = strval($mensagem);
    $_SESSION['diarias_gestao_erro'] = ($erro ? 1 : 0);
}

// Le e limpa mensagem temporaria de retorno das acoes do gestor.
function dg_getFlashGestao() {
    $mensagem = strval(dg($_SESSION, 'diarias_gestao_msg', ''));
    $erro = intval(dg($_SESSION, 'diarias_gestao_erro', 0)) === 1;
    unset($_SESSION['diarias_gestao_msg']);
    unset($_SESSION['diarias_gestao_erro']);
    return array($mensagem, $erro);
}

// Monta URL de retorno mantendo empresa, funcionario e periodo selecionados.
function dg_urlRetorno() {
    $empresa = intval(dg($_GET, 'empresa', 0));
    $funcionario = intval(dg($_GET, 'funcionario', 0));
    $mes = preg_replace('/[^0-9\-]/', '', strval(dg($_GET, 'mes', date('Y-m'))));

    $params = array();
    if ($empresa > 0) {
        $params[] = 'empresa='.$empresa;
    }
    if ($funcionario > 0) {
        $params[] = 'funcionario='.$funcionario;
    }
    if ($mes !== '') {
        $params[] = 'mes='.$mes;
    }

    return 'gestao_diarias.php'.(empty($params) ? '' : '?'.implode('&', $params));
}

// Lanca o consumo diario da diaria do funcionario.
function dg_lancarConsumo() {
    $idUser = intval(dg($_SESSION, 'user_nb_id', 0));
    if ($idUser <= 0) {
        header("Location: ../index.php");
        exit;
    }

    $idEntidade = intval(dg($_POST, 'funcionario_id', 0));
    $dataConsumo = diar_dataParaSql(trim(strval(dg($_POST, 'data_consumo', ''))));
    $tipo = strval(dg($_POST, 'tipo_consumo', 'cheia'));
    if (!in_array($tipo, array('cheia', 'outra'), true)) {
        $tipo = 'cheia';
    }

    // Diaria cheia usa sempre o valor configurado; outro valor vem digitado.
    $parametros = diar_buscarParametros();
    $valor = ($tipo === 'cheia')
        ? diar_parseValorMonetario(diar_val($parametros, 'valor_diaria_cheia', '107.00'))
        : diar_parseValorMonetario(strval(dg($_POST, 'valor_consumo', 0)));
    $observacao = trim(strval(dg($_POST, 'observacao_consumo', '')));

    if ($idEntidade <= 0 || $dataConsumo === '' || $valor <= 0) {
        dg_setFlashGestao('ERRO: Informe funcionario, data e valor do consumo.', true);
        header("Location: ".dg_urlRetorno());
        exit;
    }

    diar_query(
        "INSERT INTO diaria_consumo
            (dcon_nb_entidade, dcon_tx_data, dcon_tx_tipo, dcon_tx_valor, dcon_tx_observacao, dcon_nb_user)
         VALUES (?, ?, ?, ?, ?, ?)",
        "issdsi",
        array($idEntidade, $dataConsumo, $tipo, $valor, $observacao, $idUser)
    );

    diar_log_runtime("Consumo lancado: entidade {$idEntidade}, data {$dataConsumo}, tipo {$tipo}, valor {$valor}");
    dg_setFlashGestao('Consumo do dia '.$dataConsumo.' lancado com sucesso.', false);
    header("Location: ".dg_urlRetorno());
    exit;
}

// Lanca um deposito (valor pago + quantidade de dias referentes).
function dg_lancarDeposito() {
    $idUser = intval(dg($_SESSION, 'user_nb_id', 0));
    if ($idUser <= 0) {
        header("Location: ../index.php");
        exit;
    }

    $idEntidade = intval(dg($_POST, 'funcionario_id', 0));
    $dataDeposito = diar_dataParaSql(trim(strval(dg($_POST, 'data_deposito', ''))));
    $valorTotal = diar_parseValorMonetario(strval(dg($_POST, 'valor_deposito', 0)));
    $dias = intval(dg($_POST, 'dias_deposito', 0));
    $observacao = trim(strval(dg($_POST, 'observacao_deposito', '')));

    if ($idEntidade <= 0 || $dataDeposito === '' || $valorTotal <= 0 || $dias <= 0) {
        dg_setFlashGestao('ERRO: Informe funcionario, data, valor e a quantidade de dias.', true);
        header("Location: ".dg_urlRetorno());
        exit;
    }

    $valorDia = round($valorTotal / $dias, 2);

    diar_query(
        "INSERT INTO diaria_deposito
            (depr_nb_entidade, depr_tx_data, depr_nb_dias, depr_tx_valor_total, depr_tx_valor_dia, depr_tx_observacao, depr_nb_user)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        "isiddsi",
        array($idEntidade, $dataDeposito, $dias, $valorTotal, $valorDia, $observacao, $idUser)
    );

    diar_log_runtime("Deposito lancado: entidade {$idEntidade}, data {$dataDeposito}, dias {$dias}, valor {$valorTotal}");
    dg_setFlashGestao('Deposito de '.diar_formatarValor($valorTotal).' referente a '.$dias.' dia(s) lancado com sucesso.', false);
    header("Location: ".dg_urlRetorno());
    exit;
}

// Exclui um lancamento de consumo.
function dg_excluirConsumo() {
    $idUser = intval(dg($_SESSION, 'user_nb_id', 0));
    if ($idUser <= 0) {
        header("Location: ../index.php");
        exit;
    }

    $idConsumo = intval(dg($_POST, 'id_consumo', 0));
    if ($idConsumo <= 0) {
        dg_setFlashGestao('ERRO: Lancamento de consumo invalido.', true);
        header("Location: ".dg_urlRetorno());
        exit;
    }

    diar_query("DELETE FROM diaria_consumo WHERE dcon_nb_id = ?", "i", array($idConsumo));
    diar_log_runtime("Consumo {$idConsumo} excluido pelo usuario {$idUser}");
    dg_setFlashGestao('Lancamento de consumo excluido.', false);
    header("Location: ".dg_urlRetorno());
    exit;
}

// Exclui um lancamento de deposito.
function dg_excluirDeposito() {
    $idUser = intval(dg($_SESSION, 'user_nb_id', 0));
    if ($idUser <= 0) {
        header("Location: ../index.php");
        exit;
    }

    $idDeposito = intval(dg($_POST, 'id_deposito', 0));
    if ($idDeposito <= 0) {
        dg_setFlashGestao('ERRO: Lancamento de deposito invalido.', true);
        header("Location: ".dg_urlRetorno());
        exit;
    }

    diar_query("DELETE FROM diaria_deposito WHERE depr_nb_id = ?", "i", array($idDeposito));
    diar_log_runtime("Deposito {$idDeposito} excluido pelo usuario {$idUser}");
    dg_setFlashGestao('Lancamento de deposito excluido.', false);
    header("Location: ".dg_urlRetorno());
    exit;
}

// Entry-points do Contex para as acoes dos formularios.
function lancarConsumo() { dg_lancarConsumo(); }
function lancarDeposito() { dg_lancarDeposito(); }
function excluirConsumo() { dg_excluirConsumo(); }
function excluirDeposito() { dg_excluirDeposito(); }

include_once "../conecta.php";

diar_ensureSchema();

$idUser = intval(dg($_SESSION, 'user_nb_id', 0));
if ($idUser <= 0) {
    header("Location: ../index.php");
    exit;
}

$acao = strval(dg($_POST, 'acao', ''));
if ($acao === 'lancarConsumo') { dg_lancarConsumo(); }
if ($acao === 'lancarDeposito') { dg_lancarDeposito(); }
if ($acao === 'excluirConsumo') { dg_excluirConsumo(); }
if ($acao === 'excluirDeposito') { dg_excluirDeposito(); }

list($mensagem, $erro) = dg_getFlashGestao();

$mesFiltro = preg_replace('/[^0-9\-]/', '', strval(dg($_GET, 'mes', date('Y-m'))));
if (!preg_match('/^\d{4}-\d{2}$/', $mesFiltro)) {
    $mesFiltro = date('Y-m');
}

$empresas = diar_buscarEmpresas();
$empresaSel = intval(dg($_GET, 'empresa', 0));

// Compatibilidade: parametro antigo "motorista" vira "funcionario".
$funcionarioSel = intval(dg($_GET, 'funcionario', 0));
if ($funcionarioSel <= 0 && intval(dg($_GET, 'motorista', 0)) > 0) {
    $funcionarioSel = intval(dg($_GET, 'motorista', 0));
}

// Padrao: empresa matriz.
if ($empresaSel <= 0) {
    $empresaSel = diar_empresaMatrizId();
}

// Se o funcionario pertence a outra empresa, ajusta a empresa selecionada.
$funcionarios = array();
if ($funcionarioSel > 0) {
    $rowFunc = diar_fetch_assoc_safe(diar_query(
        "SELECT enti_nb_empresa FROM entidade WHERE enti_nb_id = ? LIMIT 1",
        "i",
        array($funcionarioSel)
    ));
    $empresaDoFunc = intval(diar_val($rowFunc, 'enti_nb_empresa', 0));
    if ($empresaDoFunc > 0) {
        $empresaSel = $empresaDoFunc;
    }
}
if ($empresaSel > 0) {
    $funcionarios = diar_buscarFuncionariosEmpresa($empresaSel);
}

$empresaInfo = diar_buscarEmpresa($empresaSel);
$funcionario = array();
if ($funcionarioSel > 0) {
    $funcionario = diar_buscarPorEntidade($funcionarioSel);
    if (empty($funcionario)) {
        $funcionarioSel = 0;
    }
}

$parametros = diar_buscarParametros();
$valorDiariaCheia = diar_parseValorMonetario(diar_val($parametros, 'valor_diaria_cheia', '107.00'));
$motoristasComLancamento = diar_buscarMotoristasLancamento();

cabecalho("Gestao de Diarias");
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($mensagem !== ''): ?>
            <div class="alert <?php echo $erro ? 'alert-danger' : 'alert-success'; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <span class="caption-subject bold font-dark"><i class="fa fa-search"></i> Consulta de Funcionario</span>
                </div>
                <div class="actions">
                    <span class="label label-info">Empresa selecionada: <?php echo htmlspecialchars(strval(dg($empresaInfo, 'empr_tx_nome', 'matriz'))); ?></span>
                </div>
            </div>
            <div class="portlet-body">
                <div class="row">
                    <div class="col-sm-4 margin-bottom-5">
                        <label>Empresa</label>
                        <select class="form-control input-sm" id="combo_empresa">
                            <option value="">Selecione a empresa...</option>
                            <?php foreach ($empresas as $emp): ?>
                                <option value="<?php echo intval($emp['empr_nb_id']); ?>"
                                    <?php echo $empresaSel === intval($emp['empr_nb_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(strval($emp['empr_tx_nome'])); ?>
                                    <?php echo (strval($emp['empr_tx_Ehmatriz']) === 'sim') ? '[Matriz]' : '[Filial]'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#888;">Padrao: empresa matriz.</small>
                    </div>
                    <div class="col-sm-4 margin-bottom-5">
                        <label>Funcionario</label>
                        <select class="form-control input-sm" id="combo_funcionario">
                            <option value="">Selecione o funcionario...</option>
                            <?php foreach ($funcionarios as $f): ?>
                                <option value="<?php echo intval($f['enti_nb_id']); ?>"
                                    <?php echo $funcionarioSel === intval($f['enti_nb_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(strval($f['user_nome'])); ?>
                                    (<?php echo htmlspecialchars(strval($f['enti_tx_matricula'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($empresaSel > 0 && empty($funcionarios)): ?>
                            <small style="color:#b35a00;">Nenhum funcionario ativo nesta empresa.</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-2 margin-bottom-5">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-sm btn-success btn-block" id="btn_selecionar" disabled>Selecionar</button>
                    </div>
                    <div class="col-sm-2 margin-bottom-5">
                        <label>&nbsp;</label>
                        <a href="gestao_diarias.php?mes=<?php echo htmlspecialchars($mesFiltro); ?>" class="btn btn-sm btn-default btn-block">Limpar</a>
                    </div>
                </div>
                <?php if (!empty($motoristasComLancamento)): ?>
                    <hr style="margin:12px 0;">
                    <div class="row">
                        <div class="col-sm-5">
                            <label><i class="fa fa-bolt"></i> Ou escolha um funcionario com lancamentos</label>
                            <select class="form-control input-sm" id="combo_motoristas" onchange="if(this.value){ location.href='gestao_diarias.php?funcionario='+this.value+'&mes=<?php echo htmlspecialchars($mesFiltro); ?>'; }">
                                <option value="">Selecione...</option>
                                <?php foreach ($motoristasComLancamento as $m): ?>
                                    <option value="<?php echo intval($m['enti_nb_id']); ?>">
                                        <?php echo htmlspecialchars(strval($m['user_nome'])).' ('.htmlspecialchars(strval($m['enti_tx_matricula'])).')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($funcionario)): ?>
    <?php
        list($setorFuncionario, $subsetorFuncionario) = diar_buscarSetorSubsetor($funcionarioSel);
        $saldoPeriodo = diar_saldoMotorista($funcionarioSel, $mesFiltro);
        $saldoGeral = diar_saldoMotorista($funcionarioSel);
        $consumos = diar_buscarConsumos($funcionarioSel, $mesFiltro);
        $depositos = diar_buscarDepositos($funcionarioSel, $mesFiltro);
        $badgeSaldo = ($saldoPeriodo['saldo'] < 0)
            ? "<span class='label label-danger'>Negativo</span>"
            : "<span class='label label-success'>Positivo</span>";
        $classeSaldo = ($saldoPeriodo['saldo'] < 0) ? 'font-red' : 'font-green';
        $classeSaldoDias = ($saldoPeriodo['saldo_dias'] < 0) ? 'font-red' : 'font-blue';
    ?>

    <div class="row">
        <div class="col-md-12">
            <div class="portlet light">
                <div class="portlet-title">
                    <div class="caption">
                        <span class="caption-subject bold font-dark"><i class="fa fa-user"></i> <?php echo htmlspecialchars(strval(dg($funcionario, 'enti_tx_nome', ''))); ?> (<?php echo htmlspecialchars(strval(dg($funcionario, 'enti_tx_matricula', ''))); ?>)</span>
                        <span class="caption-helper" style="margin-left:10px;">
                            <i class="fa fa-building"></i> <?php echo htmlspecialchars(strval(dg($empresaInfo, 'empr_tx_nome', ''))); ?>
                            &nbsp;|&nbsp; <i class="fa fa-sitemap"></i> <?php echo htmlspecialchars($setorFuncionario.' / '.$subsetorFuncionario); ?>
                        </span>
                    </div>
                    <div class="actions">
                        <form method="get" class="form-inline" style="margin:0;">
                            <input type="hidden" name="empresa" value="<?php echo $empresaSel; ?>">
                            <input type="hidden" name="funcionario" value="<?php echo $funcionarioSel; ?>">
                            <label for="mes" style="font-weight:600;">Periodo:&nbsp;</label>
                            <input type="month" class="form-control input-sm" id="mes" name="mes" value="<?php echo htmlspecialchars($mesFiltro); ?>">
                            <button type="submit" class="btn blue btn-sm" style="margin-left:6px;">Aplicar</button>
                        </form>
                    </div>
                </div>
                <div class="portlet-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="dashboard-stat2 bordered">
                                <div class="display">
                                    <div class="number">
                                        <h3 class="<?php echo $classeSaldo; ?>"><?php echo diar_formatarValor($saldoPeriodo['saldo']); ?></h3>
                                        <small>Saldo do periodo</small>
                                    </div>
                                    <div class="icon"><i class="fa fa-wallet"></i></div>
                                </div>
                                <div class="progress-info">
                                    <?php echo $badgeSaldo; ?>
                                    <span style="margin-left:6px;font-size:12px;color:#AAB5BC;"><?php echo $saldoPeriodo['saldo_dias']; ?> dia(s) cobertos</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="dashboard-stat2 bordered">
                                <div class="display">
                                    <div class="number">
                                        <h3 class="font-green"><?php echo diar_formatarValor($saldoPeriodo['depositado']); ?></h3>
                                        <small>Depositado</small>
                                    </div>
                                    <div class="icon"><i class="fa fa-level-down"></i></div>
                                </div>
                                <div class="progress-info">
                                    <span style="font-size:12px;color:#AAB5BC;"><?php echo $saldoPeriodo['dias_depositados']; ?> dia(s)</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="dashboard-stat2 bordered">
                                <div class="display">
                                    <div class="number">
                                        <h3 class="font-yellow"><?php echo diar_formatarValor($saldoPeriodo['consumido']); ?></h3>
                                        <small>Consumido</small>
                                    </div>
                                    <div class="icon"><i class="fa fa-level-up"></i></div>
                                </div>
                                <div class="progress-info">
                                    <span style="font-size:12px;color:#AAB5BC;"><?php echo $saldoPeriodo['dias_consumidos']; ?> dia(s)</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="dashboard-stat2 bordered">
                                <div class="display">
                                    <div class="number">
                                        <h3 class="<?php echo $classeSaldoDias; ?>"><?php echo $saldoPeriodo['saldo_dias']; ?></h3>
                                        <small>Saldo em dias</small>
                                    </div>
                                    <div class="icon"><i class="fa fa-calendar-check-o"></i></div>
                                </div>
                                <div class="progress-info">
                                    <span style="font-size:12px;color:#AAB5BC;"><?php echo $saldoPeriodo['saldo_dias'] < 0 ? 'consumiu alem do depositado' : 'dias cobertos'; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="dashboard-stat2 bordered">
                                <div class="display">
                                    <div class="number">
                                        <h3 class="font-dark"><?php echo diar_formatarValor($saldoGeral['saldo']); ?></h3>
                                        <small>Saldo geral</small>
                                    </div>
                                    <div class="icon"><i class="fa fa-database"></i></div>
                                </div>
                                <div class="progress-info">
                                    <span style="font-size:12px;color:#AAB5BC;">Todos os periodos | <?php echo $saldoGeral['saldo_dias']; ?> dia(s)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6">
            <div class="portlet light">
                <div class="portlet-title">
                    <div class="caption">
                        <span class="caption-subject bold font-dark"><i class="fa fa-cutlery"></i> Lancar Consumo do Dia</span>
                    </div>
                    <div class="actions">
                        <span class="label label-default">Valor cheia: <?php echo htmlspecialchars(diar_formatarValor($valorDiariaCheia)); ?></span>
                    </div>
                </div>
                <div class="portlet-body">
                    <form method="post">
                        <input type="hidden" name="acao" value="lancarConsumo">
                        <input type="hidden" name="funcionario_id" value="<?php echo $funcionarioSel; ?>">
                        <?php
                            echo linha_form(array(
                                campo_data('Data do Consumo', 'data_consumo', date('Y-m-d'), 4, "max='".date('Y-m-d')."'"),
                                combo('Tipo', 'tipo_consumo', 'cheia', 3, array('cheia' => 'Diaria cheia', 'outra' => 'Outro valor')),
                                campo('Valor (R$)', 'valor_consumo', number_format($valorDiariaCheia, 2, ',', '.'), 3, 'MASCARA_VALOR', "min='0' id='valor_consumo'"),
                                textarea('Observacoes', 'observacao_consumo', '', 12, "rows='2' placeholder='Rotas, cidades, ocorrencias do dia...'")
                            ));
                            echo "<div class='form-actions'><div class='fecha-form-btn'>";
                            echo botao('Lancar Consumo', 'lancarConsumo', '', '', '', '', 'btn btn-success');
                            echo "</div></div>";
                        ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="portlet light">
                <div class="portlet-title">
                    <div class="caption">
                        <span class="caption-subject bold font-dark"><i class="fa fa-money"></i> Lancar Deposito de Diarias</span>
                    </div>
                    <div class="actions">
                        <span class="label label-info">Saldo atual: <?php echo htmlspecialchars(diar_formatarValor($saldoGeral['saldo'])); ?></span>
                    </div>
                </div>
                <div class="portlet-body">
                    <div class="alert alert-info" style="font-size:13px;padding:8px 12px;margin-bottom:15px;">
                        <i class="fa fa-info-circle"></i> Informe o valor pago e a quantos dias ele e referente (ex.: 10 dias).
                        Saldo negativo: deposite 10 dias + o saldo. Saldo positivo: complete os 10 dias ou lance
                        qualquer valor desejado.
                    </div>
                    <form method="post">
                        <input type="hidden" name="acao" value="lancarDeposito">
                        <input type="hidden" name="funcionario_id" value="<?php echo $funcionarioSel; ?>">
                        <?php
                            echo linha_form(array(
                                campo_data('Data do Deposito', 'data_deposito', date('Y-m-d'), 3),
                                campo('Valor Total (R$)', 'valor_deposito', '', 3, 'MASCARA_VALOR', "min='0'"),
                                campo('Quantidade de Dias', 'dias_deposito', '', 2, 'MASCARA_NUMERO', "min='1' step='1'"),
                                textarea('Observacoes', 'observacao_deposito', '', 12, "rows='2' placeholder='Periodo que o deposito cobre...'")
                            ));
                            echo "<div class='form-actions'><div class='fecha-form-btn'>";
                            echo botao('Lancar Deposito', 'lancarDeposito', '', '', '', '', 'btn btn-primary');
                            echo "</div></div>";
                        ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="portlet light">
                <div class="portlet-title">
                    <div class="caption">
                        <span class="caption-subject bold font-dark"><i class="fa fa-calendar-check-o"></i> Consumos do Periodo</span>
                    </div>
                    <div class="actions">
                        <span class="label label-success"><?php echo count($consumos); ?> consumo(s)</span>
                    </div>
                </div>
                <div class="portlet-body">
                    <div style="overflow:auto;">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Valor</th>
                                    <th>Observacoes</th>
                                    <th>Lancado por</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($consumos)): ?>
                                <tr><td colspan="6" style="text-align:center;color:#999;padding:20px;"><i class="fa fa-inbox"></i> Nenhum consumo lancado neste periodo.</td></tr>
                            <?php else: foreach ($consumos as $c): ?>
                                <?php
                                    $badgeTipo = (strval(dg($c, 'dcon_tx_tipo', '')) === 'cheia')
                                        ? "<span class='label label-success'>Diaria cheia</span>"
                                        : "<span class='label label-default'>Outro valor</span>";
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(strval(dg($c, 'dcon_tx_data', ''))); ?></td>
                                    <td><?php echo $badgeTipo; ?></td>
                                    <td><strong><?php echo htmlspecialchars(diar_formatarValor(dg($c, 'dcon_tx_valor', 0))); ?></strong></td>
                                    <td><?php echo htmlspecialchars(strval(dg($c, 'dcon_tx_observacao', ''))); ?></td>
                                    <td><?php echo htmlspecialchars(strval(dg($c, 'gestor_nome', '-'))); ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Excluir este lancamento de consumo?');">
                                            <input type="hidden" name="acao" value="excluirConsumo">
                                            <input type="hidden" name="id_consumo" value="<?php echo intval(dg($c, 'dcon_nb_id', 0)); ?>">
                                            <button class="btn btn-danger btn-xs" type="submit" title="Excluir"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="portlet light">
                <div class="portlet-title">
                    <div class="caption">
                        <span class="caption-subject bold font-dark"><i class="fa fa-list-alt"></i> Depositos do Periodo</span>
                    </div>
                    <div class="actions">
                        <span class="label label-primary"><?php echo count($depositos); ?> deposito(s)</span>
                    </div>
                </div>
                <div class="portlet-body">
                    <div style="overflow:auto;">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Valor</th>
                                    <th>Dias</th>
                                    <th>Valor/Dia</th>
                                    <th>Obs.</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($depositos)): ?>
                                <tr><td colspan="6" style="text-align:center;color:#999;padding:20px;"><i class="fa fa-inbox"></i> Nenhum deposito lancado neste periodo.</td></tr>
                            <?php else: foreach ($depositos as $d): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(strval(dg($d, 'depr_tx_data', ''))); ?></td>
                                    <td><strong><?php echo htmlspecialchars(diar_formatarValor(dg($d, 'depr_tx_valor_total', 0))); ?></strong></td>
                                    <td><?php echo intval(dg($d, 'depr_nb_dias', 0)); ?></td>
                                    <td><span class="text-muted"><?php echo htmlspecialchars(diar_formatarValor(dg($d, 'depr_tx_valor_dia', 0))); ?></span></td>
                                    <td><?php echo htmlspecialchars(strval(dg($d, 'depr_tx_observacao', ''))); ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Excluir este lancamento de deposito?');">
                                            <input type="hidden" name="acao" value="excluirDeposito">
                                            <input type="hidden" name="id_deposito" value="<?php echo intval(dg($d, 'depr_nb_id', 0)); ?>">
                                            <button class="btn btn-danger btn-xs" type="submit" title="Excluir"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// Alterna o valor do consumo entre diaria cheia (preenchido) e outro valor (digitado).
function dg_toggleTipoConsumo() {
    var tipo = document.querySelector('select[name="tipo_consumo"]');
    var valor = document.getElementById('valor_consumo');
    if (!tipo || !valor) { return; }
    if (tipo.value === 'cheia') {
        valor.value = '<?php echo number_format($valorDiariaCheia, 2, ',', '.'); ?>';
        valor.readOnly = true;
    } else {
        valor.value = '';
        valor.readOnly = false;
        valor.focus();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var comboEmpresa = document.getElementById('combo_empresa');
    var comboFuncionario = document.getElementById('combo_funcionario');
    var btnSelecionar = document.getElementById('btn_selecionar');
    var tipo = document.querySelector('select[name="tipo_consumo"]');

    if (comboEmpresa) {
        comboEmpresa.addEventListener('change', function() {
            if (this.value) {
                location.href = 'gestao_diarias.php?empresa=' + this.value + '&mes=<?php echo htmlspecialchars($mesFiltro); ?>';
            }
        });
    }
    if (comboFuncionario) {
        comboFuncionario.addEventListener('change', function() {
            if (this.value) {
                location.href = 'gestao_diarias.php?empresa=' + (comboEmpresa ? comboEmpresa.value : '') + '&funcionario=' + this.value + '&mes=<?php echo htmlspecialchars($mesFiltro); ?>';
            }
        });
        if (comboFuncionario.value) {
            btnSelecionar.disabled = false;
        }
    }
    if (btnSelecionar) {
        btnSelecionar.addEventListener('click', function() {
            var id = comboFuncionario ? comboFuncionario.value : '';
            if (id) {
                location.href = 'gestao_diarias.php?empresa=' + (comboEmpresa ? comboEmpresa.value : '') + '&funcionario=' + id + '&mes=<?php echo htmlspecialchars($mesFiltro); ?>';
            }
        });
    }
    if (tipo) {
        tipo.addEventListener('change', dg_toggleTipoConsumo);
        dg_toggleTipoConsumo();
    }
});
</script>

<?php rodape(); ?>
