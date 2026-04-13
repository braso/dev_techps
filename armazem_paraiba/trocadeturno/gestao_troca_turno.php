<?php
include_once __DIR__."/helpers_troca_turno.php";
include_once "../check_permission.php";

// Acesso seguro a chaves de array sem gerar aviso quando nao existir.
function tg($arr, $k, $d = '') {
    return (is_array($arr) && isset($arr[$k])) ? $arr[$k] : $d;
}

function tt_setFlashGestao($mensagem, $erro) {
    $_SESSION['tt_gestao_msg'] = strval($mensagem);
    $_SESSION['tt_gestao_erro'] = ($erro ? 1 : 0);
}

// Le e limpa mensagem temporaria de retorno da acao de gestor.
function tt_getFlashGestao() {
    $mensagem = strval(tg($_SESSION, 'tt_gestao_msg', ''));
    $erro = intval(tg($_SESSION, 'tt_gestao_erro', 0)) === 1;
    unset($_SESSION['tt_gestao_msg']);
    unset($_SESSION['tt_gestao_erro']);
    return array($mensagem, $erro);
}

// Processa aprovacao/reprovacao da solicitacao pelo gestor logado.
function tt_processarDecisaoGestor() {
    $idUser = intval(tg($_SESSION, 'user_nb_id', 0));
    $idEntidade = intval(tg($_SESSION, 'user_nb_entidade', 0));
    $isSuperAdmin = intval($_SESSION['user_nb_superadmin'] ?? 0) === 1;

    if ($idUser <= 0 || (!$isSuperAdmin && $idEntidade <= 0)) {
        header("Location: ../index.php");
        exit;
    }

    $idSolicitacao = intval(tg($_POST, 'id_solicitacao', 0));
    $decisao = strtolower(trim(strval(tg($_POST, 'novo_status', ''))));

    if ($idSolicitacao <= 0 || !in_array($decisao, array('aprovado', 'rejeitado'), true)) {
        return array('Dados invalidos para decisao.', true);
    }

    $statusAprovador = ($decisao === 'aprovado') ? 'aceito' : 'rejeitado';

    $perm = tt_fetch_assoc_safe(tt_query(
        "SELECT apro_nb_id
         FROM solicitacao_troca_horario_aprovadores
         WHERE apro_nb_solicitacao = ?
           AND apro_nb_entidade = ?
           AND apro_tx_status = 'pendente'
         LIMIT 1",
        "ii",
        array($idSolicitacao, $idEntidade)
    ));

    if (empty($perm)) {
        return array('Voce nao possui solicitacao pendente para este item.', true);
    }

    $agora = date('Y-m-d H:i:s');

    tt_query(
        "UPDATE solicitacao_troca_horario_aprovadores
         SET apro_tx_status = ?, apro_nb_user_decisao = ?, apro_tx_data_decisao = ?
         WHERE apro_nb_solicitacao = ?",
        "sisi",
        array($statusAprovador, $idUser, $agora, $idSolicitacao)
    );

    tt_query(
        "UPDATE solicitacao_troca_horario
         SET soli_tx_status_gestor = ?, soli_nb_user_visto = ?, soli_tx_data_decisao = ?
         WHERE soli_nb_id = ?",
        "sisi",
        array($decisao, $idUser, $agora, $idSolicitacao)
    );

    $sol = tt_fetch_assoc_safe(tt_query(
        "SELECT soli_nb_entidade, soli_nb_entidade_destino
         FROM solicitacao_troca_horario
         WHERE soli_nb_id = ? LIMIT 1",
        "i",
        array($idSolicitacao)
    ));

    if (!empty($sol)) {
        $msg = ($decisao === 'aprovado')
            ? "Sua solicitacao de troca de turno foi APROVADA por um gestor."
            : "Sua solicitacao de troca de turno foi REJEITADA por um gestor.";

        tt_criarNotificacao($idSolicitacao, intval(tg($sol, 'soli_nb_entidade', 0)), 'resultado', $msg);
        tt_criarNotificacao($idSolicitacao, intval(tg($sol, 'soli_nb_entidade_destino', 0)), 'resultado', $msg);
    }

    if ($decisao === 'aprovado') {
        $idInstancia = tt_gerarDocumentoTrocaHorario($idSolicitacao, $idUser);
        if ($idInstancia > 0) {
            tt_enviarDocumentoTrocaHorarioParaAssinatura($idSolicitacao, $idInstancia);
        }
    }

    return array('Solicitacao '.($decisao === 'aprovado' ? 'aprovada' : 'rejeitada').' com sucesso.', false);
}

// Entry-point do Contex para acao do formulario (acao=decidir).
function decidir() {
    list($mensagem, $erro) = tt_processarDecisaoGestor();
    tt_setFlashGestao($mensagem, $erro);
    header('Location: gestao_troca_turno.php');
    exit;
}

include_once "../conecta.php";

tt_ensureSchema();

$idUser = intval(tg($_SESSION, 'user_nb_id', 0));
$idEntidade = intval(tg($_SESSION, 'user_nb_entidade', 0));
$isSuperAdmin = intval($_SESSION['user_nb_superadmin'] ?? 0) === 1;

if ($idUser <= 0) {
    header("Location: ../index.php");
    exit;
}

list($mensagem, $erro) = tt_getFlashGestao();

$filtro = strtolower(trim(strval(tg($_GET, 'status', 'pendente'))));
if (!in_array($filtro, array('pendente', 'aprovado', 'rejeitado', 'todas'), true)) {
    $filtro = 'pendente';
}

if ($isSuperAdmin) {
    $where = "WHERE 1=1";
    $types = "";
    $vars = array();
} else {
    $where = "WHERE a.apro_nb_entidade = ?";
    $types = "i";
    $vars = array($idEntidade);
}

if ($filtro === 'pendente') {
    $where .= " AND a.apro_tx_status = 'pendente'";
} elseif ($filtro === 'aprovado') {
    $where .= " AND a.apro_tx_status = 'aceito'";
} elseif ($filtro === 'rejeitado') {
    $where .= " AND a.apro_tx_status = 'rejeitado'";
}

$res = query(
    "SELECT s.*, a.apro_tx_status,
            sol.enti_tx_nome AS solicitante_nome, sol.enti_tx_matricula AS solicitante_matricula,
            dest.enti_tx_nome AS destino_nome, dest.enti_tx_matricula AS destino_matricula,
            u.user_tx_nome AS gestor_decisor
     FROM solicitacao_troca_horario_aprovadores a
     JOIN solicitacao_troca_horario s ON s.soli_nb_id = a.apro_nb_solicitacao
     JOIN entidade sol ON sol.enti_nb_id = s.soli_nb_entidade
     LEFT JOIN entidade dest ON dest.enti_nb_id = s.soli_nb_entidade_destino
     LEFT JOIN user u ON u.user_nb_id = s.soli_nb_user_visto
     {$where}
     ORDER BY s.soli_tx_dataCadastro DESC",
    $types,
    $vars
);

$solicitacoes = ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();

cabecalho("Gestao de Troca de Turno");
?>

<div class="row">
    <div class="col-md-12">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <span class="caption-subject bold font-dark">Solicitacoes sob sua responsabilidade</span>
                </div>
            </div>
            <div class="portlet-body">
                <?php if ($mensagem !== ''): ?>
                    <div class="alert <?php echo $erro ? 'alert-danger' : 'alert-success'; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
                <?php endif; ?>

                <form method="get" class="form-inline" style="margin-bottom:15px;">
                    <label for="status">Filtrar por:&nbsp;</label>
                    <select class="form-control" id="status" name="status">
                        <option value="pendente" <?php echo $filtro === 'pendente' ? 'selected' : ''; ?>>Pendentes</option>
                        <option value="aprovado" <?php echo $filtro === 'aprovado' ? 'selected' : ''; ?>>Aprovadas</option>
                        <option value="rejeitado" <?php echo $filtro === 'rejeitado' ? 'selected' : ''; ?>>Rejeitadas</option>
                        <option value="todas" <?php echo $filtro === 'todas' ? 'selected' : ''; ?>>Todas</option>
                    </select>
                    <button type="submit" class="btn blue" style="margin-left:8px;">Aplicar</button>
                </form>

                <div style="overflow:auto;">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Data solicitacao</th>
                                <th>Solicitante</th>
                                <th>Troca com</th>
                                <th>Data troca</th>
                                <th>Turno troca</th>
                                <th>Turno pagara</th>
                                <th>Status</th>
                                <th>Decisao</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($solicitacoes)): ?>
                            <tr><td colspan="8" style="text-align:center;color:#666;">Nenhuma solicitacao encontrada.</td></tr>
                        <?php else: foreach ($solicitacoes as $s): ?>
                            <?php
                                $statusGlobal = strval(tg($s, 'soli_tx_status_gestor', 'pendente'));
                                $badge = "<span class='label label-warning'>Pendente</span>";
                                if ($statusGlobal === 'aprovado') $badge = "<span class='label label-success'>Aprovado</span>";
                                if ($statusGlobal === 'rejeitado') $badge = "<span class='label label-danger'>Rejeitado</span>";
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars(strval(tg($s, 'soli_tx_dataCadastro', ''))); ?></td>
                                <td><?php echo htmlspecialchars(strval(tg($s, 'solicitante_nome', '')).' ('.strval(tg($s, 'solicitante_matricula', '')).')'); ?></td>
                                <td><?php echo htmlspecialchars(strval(tg($s, 'destino_nome', '')).' ('.strval(tg($s, 'destino_matricula', '')).')'); ?></td>
                                <td><?php echo htmlspecialchars(strval(tg($s, 'soli_tx_data_troca', ''))); ?></td>
                                <td><?php echo htmlspecialchars(strval(tg($s, 'soli_tx_turno_troca', ''))); ?></td>
                                <td><?php echo htmlspecialchars(strval(tg($s, 'soli_tx_turno_pagara', ''))); ?></td>
                                <td><?php echo $badge; ?></td>
                                <td style="min-width:240px;">
                                    <?php if (strval(tg($s, 'apro_tx_status', '')) === 'pendente' && strval(tg($s, 'soli_tx_status_gestor', '')) === 'pendente'): ?>
                                        <form method="post">
                                            <input type="hidden" name="acao" value="decidir">
                                            <input type="hidden" name="id_solicitacao" value="<?php echo intval(tg($s, 'soli_nb_id', 0)); ?>">
                                            <div style="display:flex;gap:8px;">
                                                <button class="btn btn-success btn-sm" type="submit" name="novo_status" value="aprovado">Aprovar</button>
                                                <button class="btn btn-danger btn-sm" type="submit" name="novo_status" value="rejeitado">Rejeitar</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div><strong>Decisor:</strong> <?php echo htmlspecialchars(strval(tg($s, 'gestor_decisor', '-'))); ?></div>
                                        <div><strong>Em:</strong> <?php echo htmlspecialchars(strval(tg($s, 'soli_tx_data_decisao', '-'))); ?></div>
                                    <?php endif; ?>
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

<?php rodape(); ?>
