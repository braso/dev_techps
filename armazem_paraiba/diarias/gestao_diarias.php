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

    $params = array();
    if ($empresa > 0) {
        $params[] = 'empresa='.$empresa;
    }
    if ($funcionario > 0) {
        $params[] = 'funcionario='.$funcionario;
    }

    // Preserva o intervalo de dias escolhido no calendario.
    $inicio = preg_replace('/[^0-9\-]/', '', strval(dg($_GET, 'inicio', '')));
    $fim = preg_replace('/[^0-9\-]/', '', strval(dg($_GET, 'fim', '')));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) {
        $params[] = 'inicio='.$inicio;
        $params[] = 'fim='.$fim;
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
    if (!in_array($tipo, array('cheia', 'sem_pernoite', 'almoco', 'outra'), true)) {
        $tipo = 'cheia';
    }

    // Tipos padrao usam o valor da CCT; "outro valor" vem digitado.
    $parametros = diar_buscarParametros();
    $valorMap = array(
        'cheia' => 'valor_diaria_cheia',
        'sem_pernoite' => 'valor_sem_pernoite',
        'almoco' => 'valor_almoco'
    );
    if (isset($valorMap[$tipo])) {
        $valor = diar_parseValorMonetario(diar_val($parametros, $valorMap[$tipo], '0'));
    } else {
        $valor = diar_parseValorMonetario(strval(dg($_POST, 'valor_consumo', 0)));
    }
    $observacao = trim(strval(dg($_POST, 'observacao_consumo', '')));

    // Campos km/pernoite/placa (lancamento manual completo).
    $kmRaw = trim(strval(dg($_POST, 'km_consumo', '')));
    $km = ($kmRaw === '') ? null : str_replace(',', '.', $kmRaw);
    $pernoite = strval(dg($_POST, 'pernoite_consumo', ''));
    if (!in_array($pernoite, array('sim', 'nao'), true)) {
        $pernoite = null;
    }
    $placa = strtoupper(trim(strval(dg($_POST, 'placa_consumo', ''))));
    if ($placa === '') {
        $placa = null;
    }

    if ($idEntidade <= 0 || $dataConsumo === '' || $valor <= 0) {
        dg_setFlashGestao('ERRO: Informe funcionario, data e valor do consumo.', true);
        header("Location: ".dg_urlRetorno());
        exit;
    }

    diar_query(
        "INSERT INTO diaria_consumo
            (dcon_nb_entidade, dcon_tx_placa, dcon_tx_data, dcon_tx_tipo, dcon_tx_origem,
             dcon_tx_valor, dcon_tx_km, dcon_tx_pernoite, dcon_tx_observacao, dcon_nb_user)
         VALUES (?, ?, ?, ?, 'manual', ?, ?, ?, ?, ?)",
        "isssdsssi",
        array($idEntidade, $placa, $dataConsumo, $tipo, $valor, $km, $pernoite, $observacao, $idUser)
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

// Edita um lancamento de consumo (correcao total, inclusive dos gerados automaticamente).
function dg_editarConsumo() {
    $idUser = intval(dg($_SESSION, 'user_nb_id', 0));
    if ($idUser <= 0) {
        header("Location: ../index.php");
        exit;
    }

    $idConsumo = intval(dg($_POST, 'id_consumo', 0));
    $dataConsumo = diar_dataParaSql(trim(strval(dg($_POST, 'data_consumo', ''))));
    $tipo = strval(dg($_POST, 'tipo_consumo', 'cheia'));
    if (!in_array($tipo, array('cheia', 'sem_pernoite', 'almoco', 'outra'), true)) {
        $tipo = 'cheia';
    }
    $valor = diar_parseValorMonetario(strval(dg($_POST, 'valor_consumo', 0)));
    $kmRaw = trim(strval(dg($_POST, 'km_consumo', '')));
    $km = ($kmRaw === '') ? null : str_replace(',', '.', $kmRaw);
    $pernoite = strval(dg($_POST, 'pernoite_consumo', ''));
    if (!in_array($pernoite, array('sim', 'nao'), true)) {
        $pernoite = null;
    }
    $placa = strtoupper(trim(strval(dg($_POST, 'placa_consumo', ''))));
    if ($placa === '') {
        $placa = null;
    }
    $observacao = trim(strval(dg($_POST, 'observacao_consumo', '')));

    if ($idConsumo <= 0 || $dataConsumo === '' || $valor <= 0) {
        dg_setFlashGestao('ERRO: Informe data e valor do consumo.', true);
        header("Location: ".dg_urlRetorno());
        exit;
    }

    diar_query(
        "UPDATE diaria_consumo
            SET dcon_tx_data = ?, dcon_tx_tipo = ?, dcon_tx_valor = ?, dcon_tx_km = ?,
                dcon_tx_pernoite = ?, dcon_tx_observacao = ?, dcon_tx_placa = ?, dcon_nb_user = ?
         WHERE dcon_nb_id = ?",
        "ssdssssii",
        array($dataConsumo, $tipo, $valor, $km, $pernoite, $observacao, $placa, $idUser, $idConsumo)
    );

    diar_log_runtime("Consumo {$idConsumo} editado: tipo {$tipo}, valor {$valor}, km ".var_export($km, true).", pernoite ".var_export($pernoite, true));
    dg_setFlashGestao('Consumo do dia '.$dataConsumo.' atualizado.', false);
    header("Location: ".dg_urlRetorno());
    exit;
}

// Roda o motor de regras e gera os consumos automaticos pendentes do funcionario.
function dg_processar() {
    $idUser = intval(dg($_SESSION, 'user_nb_id', 0));
    if ($idUser <= 0) {
        header("Location: ../index.php");
        exit;
    }

    $idEntidade = intval(dg($_POST, 'funcionario_id', 0));
    if ($idEntidade <= 0) {
        dg_setFlashGestao('ERRO: Selecione um funcionario para processar.', true);
        header("Location: ".dg_urlRetorno());
        exit;
    }

    $resultado = diar_gerarConsumosPendentes($idEntidade);
    $gerados = $resultado['gerados'];
    $pulados = $resultado['pulados'];
    $motivos = $resultado['motivos'];

    // Limpar registros futuros deste motorista.
    diar_limparConsumosInvalidos($idEntidade);

    $msg = 'Processamento concluido: '.$gerados.' consumo(s) gerado(s).';
    if ($pulados > 0) {
        $msg .= ' '.$pulados.' dia(s) sem dados suficientes — nada gerado.';
        if (!empty($motivos)) {
            $msg .= "\nDias sem dados:\n".implode("\n", array_slice($motivos, 0, 10));
            if (count($motivos) > 10) {
                $msg .= "\n...e mais ".(count($motivos) - 10)." dia(s).";
            }
        }
    }
    diar_log_runtime("Processamento manual diarias entidade {$idEntidade}: {$gerados} gerados, {$pulados} pulados");
    dg_setFlashGestao($msg, false);
    header("Location: ".dg_urlRetorno());
    exit;
}

// Entry-points do Contex para as acoes dos formularios.
function lancarConsumo() { dg_lancarConsumo(); }
function lancarDeposito() { dg_lancarDeposito(); }
function excluirConsumo() { dg_excluirConsumo(); }
function excluirDeposito() { dg_excluirDeposito(); }
function editarConsumo() { dg_editarConsumo(); }
function processar() { dg_processar(); }

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
if ($acao === 'editarConsumo') { dg_editarConsumo(); }
if ($acao === 'processar') { dg_processar(); }

list($mensagem, $erro) = dg_getFlashGestao();

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
$valoresTipo = array(
    'cheia' => $valorDiariaCheia,
    'sem_pernoite' => diar_parseValorMonetario(diar_val($parametros, 'valor_sem_pernoite', '55.00')),
    'almoco' => diar_parseValorMonetario(diar_val($parametros, 'valor_almoco', '40.00'))
);

// Processa automaticamente os dias pendentes de TODOS os motoristas ao abrir a tela, se habilitado.
$autogerar = (strtolower(trim(strval(diar_val($parametros, 'autogerar_consumo', 'sim')))) === 'sim');
if ($autogerar) {
    diar_gerarConsumosPendentesTodos('', 0);
}

// Periodo do controle: GET inicio/fim (intervalo escolhido no calendario).
// Compatibilidade: GET semana = segunda-feira da semana desejada.
// Padrao: semana atual (segunda a domingo). Limite de 92 dias por selecao.
$MAX_DIAS_CONTROLE = 92;
$inicioRaw = preg_replace('/[^0-9\-]/', '', strval(dg($_GET, 'inicio', '')));
$fimRaw = preg_replace('/[^0-9\-]/', '', strval(dg($_GET, 'fim', '')));
$semanaRaw = preg_replace('/[^0-9\-]/', '', strval(dg($_GET, 'semana', '')));

$periodoValido = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicioRaw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fimRaw));
if ($periodoValido) {
    if ($inicioRaw > $fimRaw) {
        $tmp = $inicioRaw; $inicioRaw = $fimRaw; $fimRaw = $tmp;
    }
    if ((strtotime($fimRaw) - strtotime($inicioRaw)) / 86400 + 1 > $MAX_DIAS_CONTROLE) {
        $periodoValido = false;
    }
}
if ($periodoValido) {
    $dataIniPeriodo = $inicioRaw;
    $dataFimPeriodo = $fimRaw;
} else {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $semanaRaw) && date('N', strtotime($semanaRaw)) == 1) {
        $dataIniPeriodo = $semanaRaw;
    } else {
        $diaSemanaHoje = intval(date('N'));
        $dataIniPeriodo = date('Y-m-d', strtotime('-'.($diaSemanaHoje - 1).' days'));
    }
    $dataFimPeriodo = date('Y-m-d', strtotime($dataIniPeriodo.' +6 days'));
}
$controlePeriodo = diar_controlePeriodo($funcionarioSel, $dataIniPeriodo, $dataFimPeriodo);
$resumoPeriodo = diar_resumoPeriodo($funcionarioSel, $dataIniPeriodo, $dataFimPeriodo);
$semanaAnteriorIni = date('Y-m-d', strtotime($dataIniPeriodo.' -7 days'));
$semanaAnteriorFim = date('Y-m-d', strtotime($dataFimPeriodo.' -7 days'));
$semanaProximaIni = date('Y-m-d', strtotime($dataIniPeriodo.' +7 days'));
$semanaProximaFim = date('Y-m-d', strtotime($dataFimPeriodo.' +7 days'));
$semanaAtualIni = date('Y-m-d', strtotime('-'.(intval(date('N')) - 1).' days'));
$semanaAtualFim = date('Y-m-d', strtotime($semanaAtualIni.' +6 days'));

// Lista de motoristas com pre-visualizacao (painel esquerdo).
$motoristas = diar_listarMotoristas($empresaSel);
$resumoFrota = array();
foreach (diar_resumoMotoristas($empresaSel, 'all') as $rFrota) {
    $resumoFrota[intval($rFrota['enti_nb_id'])] = $rFrota;
}

cabecalho("Gestao de Diarias");
?>

<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css" rel="stylesheet" type="text/css" />
<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js"></script>
<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-datepicker/locales/bootstrap-datepicker.pt-BR.min.js"></script>

<style>
.lista-motorista-card {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px;
    border: 1px solid #e4e9f0; border-radius: 8px; margin-bottom: 8px;
    text-decoration: none; color: #333; background: #fff; transition: all .15s;
}
.lista-motorista-card:hover { border-color: #36c6d3; box-shadow: 0 2px 8px rgba(0,0,0,.08); text-decoration: none; color: #333; }
.lista-motorista-card.ativa { border-color: #36c6d3; background: #f2fbfd; box-shadow: inset 3px 0 0 #36c6d3; }
.lm-avatar {
    width: 40px; height: 40px; border-radius: 50%; background: #183153; color: #fff;
    display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;
    flex: 0 0 40px; text-transform: uppercase;
}
.lm-info { flex: 1; min-width: 0; }
.lm-nome { font-weight: 600; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lm-meta { font-size: 11px; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lm-resumo { display: flex; gap: 8px; font-size: 11px; margin-top: 2px; }
.lm-saldo { font-weight: 700; }
.lm-saldo.pos { color: #26a269; }
.lm-saldo.neg { color: #e7505a; }
.lm-ultima { color: #888; }
#lista_motoristas::-webkit-scrollbar { width: 8px; }
#lista_motoristas::-webkit-scrollbar-thumb { background: #c2cad8; border-radius: 4px; }
.dg-aba { padding-top: 15px; }
.sdc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
    margin-bottom: 10px;
}
.sdc-col { min-width: 0; }
@media (max-width: 479px) {
    .sdc-grid { grid-template-columns: 1fr; }
}
.sdc-card {
    border: 1px solid #e4e9f0; border-radius: 10px; padding: 12px; margin-bottom: 12px;
    background: #fff; min-height: 118px; transition: all .15s; height: 100%;
}
.sdc-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.sdc-card.sdc-hoje { border: 2px solid #36c6d3; }
.sdc-card.sdc-cheia { border-left: 5px solid #27ae60; }
.sdc-card.sdc-sem-pernoite { border-left: 5px solid #f39c12; }
.sdc-card.sdc-almoco { border-left: 5px solid #5bc0de; }
.sdc-card.sdc-outra { border-left: 5px solid #95a5a6; }
.sdc-card.sdc-vazio { background: #fafbfc; border-style: dashed; }
.sdc-cabecalho { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; }
.sdc-cabecalho span { color: #888; }
.sdc-tipo { font-size: 12px; font-weight: 600; }
.sdc-valor { font-size: 18px; font-weight: 700; margin: 2px 0; }
.sdc-meta { font-size: 11px; color: #777; margin-top: 3px; }
.sdc-pernoite { color: #e74c3c; font-weight: 600; }
.sdc-pernoite-ok { color: #27ae60; }
.sdc-alerta { color: #e67e22; margin-left: 5px; }
.sdc-vazio-texto { color: #aaa; font-size: 12px; padding-top: 8px; }
.nav-tabs > li > a { border-radius: 6px 6px 0 0; }
</style>

<div class="row">
    <div class="col-md-12">
        <?php if ($mensagem !== ''): ?>
            <div class="alert <?php echo $erro ? 'alert-danger' : 'alert-success'; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- ==================== PAINEL ESQUERDO: MOTORISTAS ==================== -->
    <div class="col-md-4">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <span class="caption-subject bold font-dark"><i class="fa fa-users"></i> Motoristas</span>
                </div>
                <div class="actions">
                    <span class="label label-default" id="lbl_qtd_motoristas"><?php echo count($motoristas); ?></span>
                </div>
            </div>
            <div class="portlet-body">
                <div class="row">
                    <div class="col-sm-12 margin-bottom-10">
                        <label>Empresa</label>
                        <select class="form-control input-sm" id="combo_empresa">
                            <?php foreach ($empresas as $emp): ?>
                                <option value="<?php echo intval($emp['empr_nb_id']); ?>"
                                    <?php echo $empresaSel === intval($emp['empr_nb_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(strval($emp['empr_tx_nome'])); ?>
                                    <?php echo (strval($emp['empr_tx_Ehmatriz']) === 'sim') ? '[Matriz]' : '[Filial]'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 margin-bottom-10">
                        <label>Buscar</label>
                        <input type="text" class="form-control input-sm" id="busca_motorista" placeholder="Nome ou matricula...">
                    </div>
                    <div class="col-sm-6 margin-bottom-10">
                        <label>Situacao</label>
                        <select class="form-control input-sm" id="filtro_situacao">
                            <option value="todos">Todos</option>
                            <option value="negativo">Saldo negativo</option>
                            <option value="sem_consumo">Sem consumo no mes</option>
                            <option value="alertas">Com alerta Lei 13.103</option>
                        </select>
                    </div>
                </div>
                <div id="lista_motoristas" style="max-height:72vh;overflow-y:auto;margin-top:5px;">
                    <?php if (empty($motoristas)): ?>
                        <div style="color:#999;padding:20px;text-align:center;"><i class="fa fa-inbox"></i> Nenhum motorista nesta empresa.</div>
                    <?php else: foreach ($motoristas as $m): ?>
                        <?php
                            $mid = intval($m['enti_nb_id']);
                            $rsFrota = isset($resumoFrota[$mid]) ? $resumoFrota[$mid] : array();
                            $depFrota = round(floatval(diar_val($rsFrota, 'depositado', 0)), 2);
                            $conFrota = round(floatval(diar_val($rsFrota, 'consumido', 0)), 2);
                            $saldoFrota = round($depFrota - $conFrota, 2);
                            $diasFrota = intval(diar_val($rsFrota, 'dias_consumidos', 0));
                            $ultimaFrota = strval(diar_val($rsFrota, 'ultima_data', ''));
                            $temAlertaFrota = intval(diar_val($rsFrota, 'tem_alerta', 0));
                            $nomeM = strval(dg($m, 'user_nome', dg($m, 'enti_tx_nome', '')));
                            if ($nomeM === '') { $nomeM = strval(dg($m, 'enti_tx_nome', '')); }
                            $matriculaM = strval(dg($m, 'enti_tx_matricula', ''));
                            $iniciaisM = '';
                            foreach (array_slice(preg_split('/\s+/', trim($nomeM)), 0, 2) as $pM) {
                                if ($pM !== '') { $iniciaisM .= mb_strtoupper(mb_substr($pM, 0, 1)); }
                            }
                            if ($iniciaisM === '') { $iniciaisM = '?'; }
                            $badgeSituM = ($saldoFrota < 0)
                                ? "<span class='label label-danger' title='Saldo negativo'>Neg</span>"
                                : "<span class='label label-success' title='Saldo positivo'>Ok</span>";
                            $alertaIcone = $temAlertaFrota ? " <i class='fa fa-warning' style='color:#b33;' title='Com alerta Lei 13.103'></i>" : '';
                        ?>
                        <a href="gestao_diarias.php?empresa=<?php echo $empresaSel; ?>&funcionario=<?php echo $mid; ?>"
                           class="lista-motorista-card<?php echo $funcionarioSel === $mid ? ' ativa' : ''; ?>"
                           data-nome="<?php echo htmlspecialchars(mb_strtolower($nomeM)); ?>"
                           data-matricula="<?php echo htmlspecialchars(mb_strtolower($matriculaM)); ?>"
                           data-saldo="<?php echo $saldoFrota; ?>"
                           data-dias="<?php echo $diasFrota; ?>"
                           data-alerta="<?php echo $temAlertaFrota; ?>">
                            <div class="lm-avatar"><?php echo htmlspecialchars($iniciaisM); ?></div>
                            <div class="lm-info">
                                <div class="lm-nome"><?php echo htmlspecialchars($nomeM); ?><?php echo $alertaIcone; ?></div>
                                <div class="lm-meta"><?php echo htmlspecialchars($matriculaM); ?> &middot; <?php echo htmlspecialchars(strval(dg($m, 'empresa_nome', '-'))); ?></div>
                                <div class="lm-resumo">
                                    <span class="lm-saldo <?php echo $saldoFrota < 0 ? 'neg' : 'pos'; ?>"><?php echo diar_formatarValor($saldoFrota); ?></span>
                                    <?php if ($ultimaFrota !== ''): ?>
                                        <span class="lm-ultima">ult. <?php echo date('d/m', strtotime($ultimaFrota)); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php echo $badgeSituM; ?>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== PAINEL DIREITO: DETALHE ==================== -->
    <div class="col-md-8">
        <?php if (empty($funcionario)): ?>
            <div class="portlet light">
                <div class="portlet-body" style="text-align:center;padding:70px 20px;color:#999;">
                    <i class="fa fa-user-circle-o" style="font-size:64px;color:#d8dee6;"></i>
                    <h4 style="margin-top:15px;">Selecione um motorista para ver o detalhe</h4>
                    <p>Use a lista ao lado ou os filtros para localizar.</p>
                </div>
            </div>
        <?php else: ?>
            <?php
                list($setorFuncionario, $subsetorFuncionario) = diar_buscarSetorSubsetor($funcionarioSel);
                $saldoGeral = diar_saldoMotorista($funcionarioSel);
                $consumos = diar_buscarConsumos($funcionarioSel);
                $depositos = diar_buscarDepositos($funcionarioSel);
                $badgeSaldo = ($saldoGeral['saldo'] < 0)
                    ? "<span class='label label-danger'>Negativo</span>"
                    : "<span class='label label-success'>Positivo</span>";
                $classeSaldo = ($saldoGeral['saldo'] < 0) ? 'font-red' : 'font-green';
                $classeSaldoDias = ($saldoGeral['saldo_dias'] < 0) ? 'font-red' : 'font-blue';
            ?>

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
                        <form method="post" class="form-inline" style="margin:0;display:inline-block;">
                            <input type="hidden" name="acao" value="processar">
                            <input type="hidden" name="funcionario_id" value="<?php echo $funcionarioSel; ?>">
                            <button type="submit" class="btn yellow-casablanca btn-sm" title="Roda o motor de regras e gera os consumos automaticos dos dias pendentes">
                                <i class="fa fa-bolt"></i> Gerar diarias pendentes
                            </button>
                        </form>
                    </div>
                </div>
                <div class="portlet-body">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="javascript:;" onclick="dg_mudarAba('semana', this)"><i class="fa fa-calendar-week"></i> Semana</a></li>
                        <li><a href="javascript:;" onclick="dg_mudarAba('geral', this)"><i class="fa fa-dashboard"></i> Visao Geral</a></li>
                        <li><a href="javascript:;" onclick="dg_mudarAba('lancar', this)"><i class="fa fa-plus"></i> Lancar</a></li>
                        <li><a href="javascript:;" onclick="dg_mudarAba('extrato', this)"><i class="fa fa-list-alt"></i> Extrato</a></li>
                    </ul>

                    <!-- ===== ABA: SEMANA ===== -->
                    <div class="dg-aba" id="dg-aba-semana">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="alert alert-info" style="font-size:13px;padding:8px 12px;overflow:hidden;">
                                    <div class="row" style="display:flex;align-items:center;flex-wrap:wrap;">
                                        <div class="col-md-4" style="margin-bottom:5px;">
                                            <i class="fa fa-calendar"></i> Periodo selecionado:
                                            <strong><?php echo date('d/m/Y', strtotime($dataIniPeriodo)).' a '.date('d/m/Y', strtotime($dataFimPeriodo)); ?></strong>
                                            <span class="text-muted">(<?php echo intval($controlePeriodo['qtd_dias']); ?> dia(s))</span>
                                        </div>
                                        <div class="col-md-5" style="margin-bottom:5px;">
                                            <form method="get" id="form_periodo" class="form-inline" style="display:inline-block;">
                                                <input type="hidden" name="empresa" value="<?php echo intval($empresaSel); ?>">
                                                <input type="hidden" name="funcionario" value="<?php echo intval($funcionarioSel); ?>">
                                                <div class="input-group input-group-sm" style="width:135px;">
                                                    <span class="input-group-addon">De</span>
                                                    <input type="text" class="form-control" id="filtro_inicio" name="inicio"
                                                           value="<?php echo date('d/m/Y', strtotime($dataIniPeriodo)); ?>" placeholder="dd/mm/aaaa" autocomplete="off">
                                                </div>
                                                <div class="input-group input-group-sm" style="width:145px;">
                                                    <span class="input-group-addon">Ate</span>
                                                    <input type="text" class="form-control" id="filtro_fim" name="fim"
                                                           value="<?php echo date('d/m/Y', strtotime($dataFimPeriodo)); ?>" placeholder="dd/mm/aaaa" autocomplete="off">
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Aplicar</button>
                                            </form>
                                        </div>
                                        <div class="col-md-3" style="margin-bottom:5px;text-align:right;">
                                            <a href="gestao_diarias.php?empresa=<?php echo $empresaSel; ?>&funcionario=<?php echo $funcionarioSel; ?>&inicio=<?php echo $semanaAnteriorIni; ?>&fim=<?php echo $semanaAnteriorFim; ?>" class="btn btn-default btn-xs">&laquo; Semana anterior</a>
                                            <a href="gestao_diarias.php?empresa=<?php echo $empresaSel; ?>&funcionario=<?php echo $funcionarioSel; ?>&inicio=<?php echo $semanaAtualIni; ?>&fim=<?php echo $semanaAtualFim; ?>" class="btn btn-primary btn-xs">Semana atual</a>
                                            <a href="gestao_diarias.php?empresa=<?php echo $empresaSel; ?>&funcionario=<?php echo $funcionarioSel; ?>&inicio=<?php echo $semanaProximaIni; ?>&fim=<?php echo $semanaProximaFim; ?>" class="btn btn-default btn-xs">Proxima &raquo;</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="sdc-grid">
                            <?php foreach ($controlePeriodo['dias'] as $diaSemana): ?>
                                <?php
                                    $classeDia = ($diaSemana['data'] === date('Y-m-d')) ? 'sdc-hoje' : '';
                                    if ($diaSemana['tem_consumo']) {
                                        $classeTipo = array(
                                            'cheia' => 'sdc-cheia',
                                            'sem_pernoite' => 'sdc-sem-pernoite',
                                            'almoco' => 'sdc-almoco',
                                            'outra' => 'sdc-outra'
                                        );
                                        $classeDia .= ' '.($classeTipo[$diaSemana['tipo']] ?? 'sdc-outra');
                                    } else {
                                        $classeDia .= ' sdc-vazio';
                                    }
                                ?>
                                <div class="sdc-col">
                                    <div class="sdc-card <?php echo trim($classeDia); ?>">
                                        <div class="sdc-cabecalho">
                                            <strong><?php echo $diaSemana['dia_semana']; ?></strong>
                                            <span><?php echo date('d/m', strtotime($diaSemana['data'])); ?></span>
                                        </div>
                                        <?php if ($diaSemana['tem_consumo']): ?>
                                            <div class="sdc-tipo"><?php echo diar_consumoTipoLabel($diaSemana['tipo']); ?></div>
                                            <div class="sdc-valor"><?php echo diar_formatarValor($diaSemana['valor']); ?></div>
                                            <div class="sdc-meta">
                                                <?php if ($diaSemana['km'] !== null && $diaSemana['km'] !== ''): ?>
                                                    <span title="Km"><?php echo number_format(floatval($diaSemana['km']), 1, ',', '.'); ?> km</span>
                                                <?php endif; ?>
                                                <?php if ($diaSemana['pernoite'] === 'sim'): ?>
                                                    <span class="sdc-pernoite">Dormiu fora</span>
                                                <?php elseif ($diaSemana['pernoite'] === 'nao'): ?>
                                                    <span class="sdc-pernoite-ok">Na base</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="sdc-meta">
                                                <?php echo diar_consumoOrigemLabel($diaSemana['origem']); ?>
                                                <?php if (!empty($diaSemana['detalhes']['alertas']) && is_array($diaSemana['detalhes']['alertas'])): ?>
                                                    <span class="sdc-alerta" title="<?php echo htmlspecialchars(implode(' | ', $diaSemana['detalhes']['alertas'])); ?>"><i class="fa fa-warning"></i></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="sdc-vazio-texto">
                                                <?php echo $diaSemana['tem_jornada'] ? 'Jornada sem diaria' : 'Sem jornada'; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="portlet light" style="margin-top:5px;">
                                    <div class="portlet-title">
                                        <div class="caption">
                                            <span class="caption-subject bold font-dark"><i class="fa fa-calculator"></i> Resumo do Periodo</span>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <div class="row">
                                            <div class="col-md-2">
                                                <div class="dashboard-stat2 bordered">
                                                    <div class="display"><div class="number"><h3 class="font-yellow"><?php echo diar_formatarValor($controlePeriodo['total_consumido']); ?></h3><small>Total consumido</small></div><div class="icon"><i class="fa fa-level-up"></i></div></div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="dashboard-stat2 bordered">
                                                    <div class="display"><div class="number"><h3 class="font-dark"><?php echo $controlePeriodo['dias_com_diaria']; ?> / <?php echo intval($controlePeriodo['qtd_dias']); ?></h3><small>Dias com diaria</small></div><div class="icon"><i class="fa fa-calendar-check-o"></i></div></div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="dashboard-stat2 bordered">
                                                    <div class="display"><div class="number"><h3 class="font-green"><?php echo $controlePeriodo['qtd_cheia']; ?></h3><small>Cheias (R$107)</small></div><div class="icon"><i class="fa fa-bed"></i></div></div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="dashboard-stat2 bordered">
                                                    <div class="display"><div class="number"><h3 class="font-blue"><?php echo $controlePeriodo['qtd_sem_pernoite']; ?></h3><small>Sem pernoite (R$55)</small></div><div class="icon"><i class="fa fa-road"></i></div></div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="dashboard-stat2 bordered">
                                                    <div class="display"><div class="number"><h3 class="font-info"><?php echo $controlePeriodo['qtd_almoco']; ?></h3><small>Almoco (R$40)</small></div><div class="icon"><i class="fa fa-cutlery"></i></div></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== ABA: VISÃO GERAL ===== -->
                    <div class="dg-aba" id="dg-aba-geral" style="display:none;">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="dashboard-stat2 bordered">
                                    <div class="display">
                                        <div class="number"><h3 class="<?php echo $classeSaldo; ?>"><?php echo diar_formatarValor($saldoGeral['saldo']); ?></h3><small>Saldo geral</small></div>
                                        <div class="icon"><i class="fa fa-wallet"></i></div>
                                    </div>
                                    <div class="progress-info"><?php echo $badgeSaldo; ?><span style="margin-left:6px;font-size:12px;color:#AAB5BC;"><?php echo $saldoGeral['saldo_dias']; ?> dia(s) cobertos</span></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="dashboard-stat2 bordered">
                                    <div class="display"><div class="number"><h3 class="font-green"><?php echo diar_formatarValor($saldoGeral['depositado']); ?></h3><small>Total depositado</small></div><div class="icon"><i class="fa fa-level-down"></i></div></div>
                                    <div class="progress-info"><span style="font-size:12px;color:#AAB5BC;"><?php echo $saldoGeral['dias_depositados']; ?> dia(s)</span></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="dashboard-stat2 bordered">
                                    <div class="display"><div class="number"><h3 class="font-yellow"><?php echo diar_formatarValor($saldoGeral['consumido']); ?></h3><small>Total consumido</small></div><div class="icon"><i class="fa fa-level-up"></i></div></div>
                                    <div class="progress-info"><span style="font-size:12px;color:#AAB5BC;"><?php echo $saldoGeral['dias_consumidos']; ?> dia(s)</span></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="dashboard-stat2 bordered">
                                    <div class="display"><div class="number"><h3 class="<?php echo $classeSaldoDias; ?>"><?php echo $saldoGeral['saldo_dias']; ?></h3><small>Saldo em dias</small></div><div class="icon"><i class="fa fa-calendar-check-o"></i></div></div>
                                    <div class="progress-info"><span style="font-size:12px;color:#AAB5BC;"><?php echo $saldoGeral['saldo_dias'] < 0 ? 'consumiu alem do depositado' : 'dias cobertos'; ?></span></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="dashboard-stat2 bordered">
                                    <div class="display"><div class="number"><h3 class="font-dark"><?php echo count($consumos); ?></h3><small>Dias com diaria</small></div><div class="icon"><i class="fa fa-calendar-check-o"></i></div></div>
                                    <div class="progress-info"><span style="font-size:12px;color:#AAB5BC;">Consumos: <?php echo count($consumos); ?> dia(s) | Depositos: <?php echo count($depositos); ?></span></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="portlet light" style="margin-top:5px;">
                                    <div class="portlet-title">
                                        <div class="caption">
                                            <span class="caption-subject bold font-dark"><i class="fa fa-calendar"></i> Resumo do Periodo</span>
                                            <span class="caption-helper" style="margin-left:10px;">
                                                <?php echo date('d/m/Y', strtotime($resumoPeriodo['data_inicio'])).' a '.date('d/m/Y', strtotime($resumoPeriodo['data_fim'])); ?>
                                            </span>
                                        </div>
                                        </div>
                                    </div>
                                    <div class="portlet-body">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="dashboard-stat2 bordered">
                                                    <div class="display"><div class="number"><h3 class="font-green"><?php echo diar_formatarValor($resumoPeriodo['depositado']); ?></h3><small>Depositado no periodo</small></div><div class="icon"><i class="fa fa-level-down"></i></div></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="dashboard-stat2 bordered">
                                                    <div class="display"><div class="number"><h3 class="font-yellow"><?php echo diar_formatarValor($resumoPeriodo['consumido']); ?></h3><small>Consumido no periodo (<?php echo $resumoPeriodo['dias_consumidos']; ?> dia(s))</small></div><div class="icon"><i class="fa fa-level-up"></i></div></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="dashboard-stat2 bordered">
                                                    <div class="display"><div class="number"><h3 class="<?php echo $resumoPeriodo['saldo'] < 0 ? 'font-red' : 'font-blue'; ?>"><?php echo diar_formatarValor($resumoPeriodo['saldo']); ?></h3><small>Saldo do periodo</small></div><div class="icon"><i class="fa fa-wallet"></i></div></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="dashboard-stat2 bordered">
                                                    <div class="display"><div class="number"><h3 class="<?php echo $resumoPeriodo['complemento_sugerido'] > 0 ? 'font-red' : 'font-green'; ?>"><?php echo diar_formatarValor($resumoPeriodo['complemento_sugerido']); ?></h3><small>Complemento sugerido</small></div><div class="icon"><i class="fa fa-plus-circle"></i></div></div>
                                                    <div class="progress-info"><span style="font-size:12px;color:#AAB5BC;">O que falta para cobrir o consumo do periodo</span></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== ABA: LANÇAR ===== -->
                    <div class="dg-aba" id="dg-aba-lancar" style="display:none;">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="portlet light" style="margin-top:5px;">
                                    <div class="portlet-title">
                                        <div class="caption"><span class="caption-subject bold font-dark"><i class="fa fa-cutlery"></i> Lancar Consumo do Dia</span></div>
                                        <div class="actions"><span class="label label-default">Valor cheia: <?php echo htmlspecialchars(diar_formatarValor($valorDiariaCheia)); ?></span></div>
                                    </div>
                                    <div class="portlet-body">
                                        <form method="post">
                                            <input type="hidden" name="acao" value="lancarConsumo">
                                            <input type="hidden" name="funcionario_id" value="<?php echo $funcionarioSel; ?>">
                                            <?php
                                                echo linha_form(array(
                                                    campo_data('Data do Consumo', 'data_consumo', date('Y-m-d'), 4, "max='".date('Y-m-d')."'"),
                                                    combo('Tipo', 'tipo_consumo', 'cheia', 3, array(
                                                        'cheia' => 'Diaria cheia (pernoite)',
                                                        'sem_pernoite' => 'Diaria sem pernoite',
                                                        'almoco' => 'Diaria de almoco',
                                                        'outra' => 'Outro valor'
                                                    )),
                                                    campo('Valor (R$)', 'valor_consumo', number_format($valorDiariaCheia, 2, ',', '.'), 3, 'MASCARA_VALOR', "min='0' id='valor_consumo'"),
                                                    campo('Hodometro', 'km_consumo', '', 2, 'MASCARA_NUMERO', "min='0' step='0.1' id='km_consumo_lancar'"),
                                                    combo('Pernoite fora da base', 'pernoite_consumo', '', 3, array(
                                                        '' => 'Indefinido',
                                                        'sim' => 'Sim',
                                                        'nao' => 'Nao'
                                                    )),
                                                    campo('Placa', 'placa_consumo', '', 2, 'MASCARA_PLACA', ''),
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
                                <div class="portlet light" style="margin-top:5px;">
                                    <div class="portlet-title">
                                        <div class="caption"><span class="caption-subject bold font-dark"><i class="fa fa-money"></i> Lancar Deposito de Diarias</span></div>
                                        <div class="actions"><span class="label label-info">Saldo atual: <?php echo htmlspecialchars(diar_formatarValor($saldoGeral['saldo'])); ?></span></div>
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
                    </div>

                    <!-- ===== ABA: EXTRATO ===== -->
                    <div class="dg-aba" id="dg-aba-extrato" style="display:none;">
                        <div class="row">
                            <div class="col-md-7">
                                <div class="portlet light" style="margin-top:5px;">
                                    <div class="portlet-title">
                                        <div class="caption"><span class="caption-subject bold font-dark"><i class="fa fa-calendar-check-o"></i> Consumos (todos)</span></div>
                                        <div class="actions"><span class="label label-success"><?php echo count($consumos); ?> consumo(s)</span></div>
                                    </div>
                                    <div class="portlet-body">
                                        <div style="overflow:auto;">
                                            <table class="table table-striped table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Data</th>
                                                        <th>Origem</th>
                                                        <th>Tipo</th>
                                                        <th>Placa</th>
                                                        <th>Hodometro</th>
                                                        <th>Pernoite</th>
                                                        <th>Loc</th>
                                                        <th>Valor</th>
                                                        <th>Obs.</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php if (empty($consumos)): ?>
                                                    <tr><td colspan="10" style="text-align:center;color:#999;padding:20px;"><i class="fa fa-inbox"></i> Nenhum consumo lancado neste periodo.</td></tr>
                                                <?php else: foreach ($consumos as $c): ?>
                                                    <?php
                                                        $tipoConsumo = strval(dg($c, 'dcon_tx_tipo', ''));
                                                        $badgeTipo = array(
                                                            'cheia' => "<span class='label label-success'>Diaria cheia</span>",
                                                            'sem_pernoite' => "<span class='label label-warning'>Sem pernoite</span>",
                                                            'almoco' => "<span class='label label-info'>Almoco</span>",
                                                            'outra' => "<span class='label label-default'>Outro valor</span>"
                                                        );
                                                        $badgeTipo = isset($badgeTipo[$tipoConsumo]) ? $badgeTipo[$tipoConsumo] : "<span class='label label-default'>".htmlspecialchars($tipoConsumo)."</span>";
                                                        $pernoiteConsumo = strval(dg($c, 'dcon_tx_pernoite', ''));
                                                        $badgePernoite = ($pernoiteConsumo === 'sim')
                                                            ? "<span class='label label-danger'>Sim</span>"
                                                            : (($pernoiteConsumo === 'nao') ? "<span class='label label-default'>Nao</span>" : "<span class='text-muted'>-</span>");
                                                        $kmConsumo = dg($c, 'dcon_tx_km', '');
                                                        $kmConsumoShow = ($kmConsumo === '' || $kmConsumo === null) ? '-' : number_format(floatval($kmConsumo), 1, ',', '.').' km';
                                                        $dataBR = '';
                                                        $dataRaw = strval(dg($c, 'dcon_tx_data', ''));
                                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRaw)) {
                                                            $dataBR = date('d/m/Y', strtotime($dataRaw));
                                                        } else {
                                                            $dataBR = $dataRaw;
                                                        }
                                                        $motoristaNome = strval(dg($funcionario, 'enti_tx_nome', ''));
                                                        $matriculaFunc = strval(dg($funcionario, 'enti_tx_matricula', ''));
                                                        $cnpjEmpresa = strval(dg($empresaInfo, 'empr_tx_cnpj', ''));
                                                        $placaConsumo = strval(dg($c, 'dcon_tx_placa', ''));
                                                        $locUrl = '../logistica.php?motorista='.urlencode($motoristaNome).'&matricula='.urlencode($matriculaFunc).'&data='.urlencode($dataRaw).'&cnpj='.urlencode($cnpjEmpresa);
                                                        if ($placaConsumo !== '') {
                                                            $locUrl .= '&placa='.urlencode($placaConsumo);
                                                        }
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($dataBR); ?></td>
                                                        <td><?php echo diar_consumoOrigemLabel(dg($c, 'dcon_tx_origem', 'manual')); ?></td>
                                                        <td><?php echo $badgeTipo; ?></td>
                                                        <td><?php echo htmlspecialchars(strval(dg($c, 'dcon_tx_placa', ''))); ?></td>
                                                        <td><?php echo $kmConsumoShow; ?></td>
                                                        <td><?php echo $badgePernoite; ?></td>
                                                        <td><a href="<?php echo htmlspecialchars($locUrl); ?>" target="_blank" class="btn btn-xs btn-info" title="Ver no mapa"><i class="fa fa-map-marker"></i></a></td>
                                                        <td><strong><?php echo htmlspecialchars(diar_formatarValor(dg($c, 'dcon_tx_valor', 0))); ?></strong></td>
                                                        <td><?php echo htmlspecialchars(strval(dg($c, 'dcon_tx_observacao', ''))); ?></td>
                                                        <td style="white-space:nowrap;">
                                                            <button type="button" class="btn btn-primary btn-xs btn_editar_consumo" title="Editar"
                                                                data-id="<?php echo intval(dg($c, 'dcon_nb_id', 0)); ?>"
                                                                data-data="<?php echo htmlspecialchars(strval(dg($c, 'dcon_tx_data', ''))); ?>"
                                                                data-tipo="<?php echo htmlspecialchars($tipoConsumo); ?>"
                                                                data-valor="<?php echo htmlspecialchars(number_format(floatval(dg($c, 'dcon_tx_valor', 0)), 2, ',', '.')); ?>"
                                                                data-km="<?php echo htmlspecialchars(strval(dg($c, 'dcon_tx_km', ''))); ?>"
                                                                data-pernoite="<?php echo htmlspecialchars($pernoiteConsumo); ?>"
                                                                data-placa="<?php echo htmlspecialchars(strval(dg($c, 'dcon_tx_placa', ''))); ?>"
                                                                data-obs="<?php echo htmlspecialchars(strval(dg($c, 'dcon_tx_observacao', ''))); ?>"><i class="fa fa-pencil"></i></button>
                                                            <form method="post" onsubmit="return confirm('Excluir este lancamento de consumo?');" style="display:inline-block;margin:0;">
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
                                <div class="portlet light" style="margin-top:5px;">
                                    <div class="portlet-title">
                                        <div class="caption"><span class="caption-subject bold font-dark"><i class="fa fa-list-alt"></i> Depositos (todos)</span></div>
                                        <div class="actions"><span class="label label-primary"><?php echo count($depositos); ?> deposito(s)</span></div>
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
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($funcionarioSel > 0): ?>
<div class="modal fade" id="modal_editar_consumo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-pencil"></i> Editar Consumo do Dia</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="acao" value="editarConsumo">
                    <input type="hidden" name="id_consumo" id="edit_id_consumo">
                    <input type="hidden" name="empresa" value="<?php echo $empresaSel; ?>">
                    <input type="hidden" name="funcionario" value="<?php echo $funcionarioSel; ?>">
                    <?php
                        echo linha_form(array(
                            campo_data('Data do Consumo', 'data_consumo', date('Y-m-d'), 4, "id='edit_data_consumo'"),
                            combo('Tipo', 'tipo_consumo', 'cheia', 3, array(
                                'cheia' => 'Diaria cheia (pernoite)',
                                'sem_pernoite' => 'Diaria sem pernoite',
                                'almoco' => 'Diaria de almoco',
                                'outra' => 'Outro valor'
                            ), "id='edit_tipo_consumo'"),
                            campo('Valor (R$)', 'valor_consumo', '', 3, 'MASCARA_VALOR', "min='0' id='edit_valor_consumo'"),
                            campo('Hodometro', 'km_consumo', '', 2, 'MASCARA_NUMERO', "min='0' step='0.1' id='edit_km_consumo'"),
                            combo('Pernoite fora da base', 'pernoite_consumo', '', 3, array(
                                '' => 'Indefinido',
                                'sim' => 'Sim',
                                'nao' => 'Nao'
                            ), "id='edit_pernoite_consumo'"),
                            campo('Placa', 'placa_consumo', '', 3, 'MASCARA_PLACA', "id='edit_placa_consumo'"),
                            textarea('Observacoes', 'observacao_consumo', '', 12, "rows='2' id='edit_observacao_consumo'")
                        ));
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alteracoes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Valores padrao por tipo (Clausula Decima Quarta) para o lancamento manual.
var VALORES_TIPO = <?php echo json_encode($valoresTipo); ?>;

// Valor padrao cadastrado de um tipo, formatado para o campo (ex.: 107 -> 107,00).
function dg_valorTipo(tipo) {
    var v = VALORES_TIPO[tipo];
    if (v === undefined) { return null; }
    return Number(v).toFixed(2).replace('.', ',');
}

// Alterna o valor do consumo entre os tipos padrao (preenchido) e outro valor (digitado).
function dg_toggleTipoConsumo() {
    var tipo = document.querySelector('select[name="tipo_consumo"]');
    var valor = document.getElementById('valor_consumo');
    if (!tipo || !valor) { return; }
    var valorTipo = dg_valorTipo(tipo.value);
    if (valorTipo !== null) {
        valor.value = valorTipo;
        valor.readOnly = true;
    } else {
        valor.value = '';
        valor.readOnly = false;
        valor.focus();
    }
}

// Valor automatico no modal de edicao conforme o tipo (usa os valores ja cadastrados).
// $limparOutro = true limpa o campo ao trocar para "outro valor" (troca manual do tipo).
// Obs.: os helpers campo()/textarea() geram id duplicado com o name, entao buscamos pelo name no modal.
function dg_campoEdit(nome) {
    var modal = document.getElementById('modal_editar_consumo');
    return modal ? modal.querySelector('[name="' + nome + '"]') : null;
}

function dg_toggleTipoConsumoEdit(limparOutro) {
    var tipo = dg_campoEdit('tipo_consumo');
    var valor = dg_campoEdit('valor_consumo');
    if (!tipo || !valor) { return; }
    var valorTipo = dg_valorTipo(tipo.value);
    if (valorTipo !== null) {
        valor.value = valorTipo;
        valor.readOnly = true;
    } else {
        if (limparOutro) { valor.value = ''; }
        valor.readOnly = false;
    }
}

// Alterna a aba ativa do detalhe do motorista.
function dg_mudarAba(aba, el) {
    document.querySelectorAll('.dg-aba').forEach(function(d) { d.style.display = 'none'; });
    var alvo = document.getElementById('dg-aba-' + aba);
    if (alvo) { alvo.style.display = 'block'; }
    if (el) {
        var lis = el.closest('.nav-tabs').querySelectorAll('li');
        lis.forEach(function(li) { li.classList.remove('active'); });
        if (el.parentElement) { el.parentElement.classList.add('active'); }
    }
}

// Filtra os cards de motoristas por busca e situacao (dinamico, sem recarregar).
function dg_filtrarMotoristas() {
    var termo = (document.getElementById('busca_motorista') ? document.getElementById('busca_motorista').value : '').toLowerCase();
    var situ = document.getElementById('filtro_situacao') ? document.getElementById('filtro_situacao').value : 'todos';
    var cards = document.querySelectorAll('.lista-motorista-card');
    var visiveis = 0;
    cards.forEach(function(card) {
        var ok = true;
        if (termo !== '') {
            var nome = (card.getAttribute('data-nome') || '').toLowerCase();
            var mat = (card.getAttribute('data-matricula') || '').toLowerCase();
            if (nome.indexOf(termo) === -1 && mat.indexOf(termo) === -1) { ok = false; }
        }
        if (ok && situ === 'negativo' && parseFloat(card.getAttribute('data-saldo') || 0) >= 0) { ok = false; }
        if (ok && situ === 'sem_consumo' && parseInt(card.getAttribute('data-dias') || 0, 10) > 0) { ok = false; }
        if (ok && situ === 'alertas' && card.getAttribute('data-alerta') !== '1') { ok = false; }
        card.style.display = ok ? '' : 'none';
        if (ok) { visiveis++; }
    });
    var lbl = document.getElementById('lbl_qtd_motoristas');
    if (lbl) { lbl.textContent = visiveis + ' / ' + cards.length; }
}

document.addEventListener('DOMContentLoaded', function() {
    var comboEmpresa = document.getElementById('combo_empresa');
    var tipo = document.querySelector('select[name="tipo_consumo"]');

    if (comboEmpresa) {
        comboEmpresa.addEventListener('change', function() {
            if (this.value) {
                location.href = 'gestao_diarias.php?empresa=' + this.value;
            }
        });
    }

    var busca = document.getElementById('busca_motorista');
    if (busca) {
        busca.addEventListener('input', dg_filtrarMotoristas);
    }
    var filtroSitu = document.getElementById('filtro_situacao');
    if (filtroSitu) {
        filtroSitu.addEventListener('change', dg_filtrarMotoristas);
    }
    dg_filtrarMotoristas();

    // Calendario do periodo (De/Ate) com conversao dd/mm/aaaa -> ISO no envio.
    if (typeof jQuery !== 'undefined' && jQuery.fn.datepicker) {
        jQuery('#filtro_inicio, #filtro_fim').datepicker({
            format: 'dd/mm/yyyy',
            language: 'pt-BR',
            autoclose: true,
            todayHighlight: true,
            orientation: 'bottom auto'
        });
    }
    var formPeriodo = document.getElementById('form_periodo');
    if (formPeriodo) {
        formPeriodo.addEventListener('submit', function() {
            ['filtro_inicio', 'filtro_fim'].forEach(function(id) {
                var el = document.getElementById(id);
                if (!el) { return; }
                var m = el.value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
                if (m) { el.value = m[3] + '-' + m[2] + '-' + m[1]; }
            });
        });
    }

    if (tipo) {
        tipo.addEventListener('change', dg_toggleTipoConsumo);
        dg_toggleTipoConsumo();
    }

    var tipoEdit = dg_campoEdit('tipo_consumo');
    if (tipoEdit) {
        tipoEdit.addEventListener('change', function() { dg_toggleTipoConsumoEdit(true); });
    }

    document.querySelectorAll('.btn_editar_consumo').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = document.getElementById('edit_id_consumo');
            var data = dg_campoEdit('data_consumo');
            var tipo = dg_campoEdit('tipo_consumo');
            var valor = dg_campoEdit('valor_consumo');
            var km = dg_campoEdit('km_consumo');
            var pernoite = dg_campoEdit('pernoite_consumo');
            var placa = dg_campoEdit('placa_consumo');
            var obs = dg_campoEdit('observacao_consumo');
            if (id) id.value = btn.getAttribute('data-id') || '';
            if (data) data.value = btn.getAttribute('data-data') || '';
            if (tipo) tipo.value = btn.getAttribute('data-tipo') || 'cheia';
            if (valor) valor.value = btn.getAttribute('data-valor') || '';
            if (km) km.value = btn.getAttribute('data-km') || '';
            if (pernoite) pernoite.value = btn.getAttribute('data-pernoite') || '';
            if (placa) placa.value = btn.getAttribute('data-placa') || '';
            if (obs) obs.value = btn.getAttribute('data-obs') || '';
            // Sincroniza o valor com o tipo selecionado (valores ja cadastrados).
            dg_toggleTipoConsumoEdit(false);
            var modal = document.getElementById('modal_editar_consumo');
            if (modal && typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                jQuery(modal).modal('show');
            } else if (modal) {
                modal.style.display = 'block';
                modal.classList.add('in');
            }
        });
    });
});
</script>

<?php rodape(); ?>
