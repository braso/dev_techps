<?php
include_once __DIR__."/helpers_troca_turno.php";
include_once "../check_permission.php";

// Acesso seguro a chaves de array sem gerar aviso quando nao existir.
function tg($arr, $k, $d = '') {
    return (is_array($arr) && isset($arr[$k])) ? $arr[$k] : $d;
}

// Identifica super admin usando flag dedicada e nivel textual da sessao.
function tt_isSuperAdmin() {
    if (intval($_SESSION['user_nb_superadmin'] ?? 0) === 1) {
        return true;
    }

    $nivel = trim(strval($_SESSION['user_tx_nivel'] ?? ''));
    return (bool)preg_match('/super\s+administrador/i', $nivel);
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
    $isSuperAdmin = tt_isSuperAdmin();

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

        if ($isSuperAdmin) {
                $perm = tt_fetch_assoc_safe(tt_query(
                        "SELECT soli_nb_id
                         FROM solicitacao_troca_horario
                         WHERE soli_nb_id = ?
                             AND soli_tx_status_gestor = 'pendente'
                         LIMIT 1",
                        "i",
                        array($idSolicitacao)
                ));
        } else {
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
        }

    if (empty($perm)) {
        return array('Voce nao possui solicitacao pendente para este item.', true);
    }

    if ($decisao === 'aprovado') {
        $conflitoTroca = tt_validarConflitosTroca($idSolicitacao);
        if ($conflitoTroca !== '') {
            return array('Conflito de escala: '.$conflitoTroca, true);
        }
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

// True quando a solicitacao aprovada ainda pode ser excluida: ate o dia anterior
// a primeira data da troca (data troca e, se houver, data que pagara).
function tt_trocaPodeSerExcluida($solicitacao) {
    $amanha = date('Y-m-d', strtotime('+1 day'));
    $dataTroca = substr(strval(tg($solicitacao, 'soli_tx_data_troca', '')), 0, 10);
    $dataPagara = substr(strval(tg($solicitacao, 'soli_tx_data_pagara', '')), 0, 10);

    if ($dataTroca === '' || $dataTroca < $amanha) {
        return false;
    }
    if ($dataPagara !== '' && $dataPagara < $amanha) {
        return false;
    }

    return true;
}

// Remove documentos/assinaturas pendentes gerados pela troca aprovada excluida.
function tt_removerAssinaturasTroca($idSolicitacao, $idInstancia) {
    $idSolicitacao = intval($idSolicitacao);
    $idInstancia = intval($idInstancia);

    if (tt_tabelaExiste('solicitacoes_assinatura')) {
        $grupo = 'troca_turno_'.$idSolicitacao;
        $ids = array();
        $res = tt_query(
            "SELECT id FROM solicitacoes_assinatura WHERE grupo_envio = ?",
            "s",
            array($grupo)
        );
        while ($res && ($r = mysqli_fetch_assoc($res))) {
            $ids[] = intval(tg($r, 'id', 0));
        }

        if (!empty($ids)) {
            $in = implode(',', array_filter($ids));
            if ($in !== '') {
                if (tt_tabelaExiste('assinantes')) {
                    tt_query("DELETE FROM assinantes WHERE id_solicitacao IN (".$in.")");
                }
                tt_query("DELETE FROM solicitacoes_assinatura WHERE id IN (".$in.")");
            }
        }
    }

    if ($idInstancia > 0) {
        if (tt_tabelaExiste('valo_documento_modulo')) {
            tt_query("DELETE FROM valo_documento_modulo WHERE valo_nb_instancia = ?", "i", array($idInstancia));
        }
        if (tt_tabelaExiste('inst_documento_modulo')) {
            tt_query("DELETE FROM inst_documento_modulo WHERE inst_nb_id = ?", "i", array($idInstancia));
        }
    }
}

// Exclui solicitacao aprovada antes do dia da troca; sem a linha aprovada o espelho
// deixa de aplicar a troca e as previsoes de trabalho voltam ao normal.
function tt_processarExclusaoTroca() {
    $idUser = intval(tg($_SESSION, 'user_nb_id', 0));
    $idEntidade = intval(tg($_SESSION, 'user_nb_entidade', 0));
    $isSuperAdmin = tt_isSuperAdmin();

    if ($idUser <= 0 || (!$isSuperAdmin && $idEntidade <= 0)) {
        header("Location: ../index.php");
        exit;
    }

    $idSolicitacao = intval(tg($_POST, 'id_solicitacao', 0));
    if ($idSolicitacao <= 0) {
        return array('Solicitacao invalida para exclusao.', true);
    }

    $solicitacao = tt_fetch_assoc_safe(tt_query(
        "SELECT soli_nb_id, soli_nb_entidade, soli_nb_entidade_destino,
                soli_tx_status_gestor, soli_tx_data_troca, soli_tx_data_pagara,
                soli_nb_id_instancia
         FROM solicitacao_troca_horario
         WHERE soli_nb_id = ? LIMIT 1",
        "i",
        array($idSolicitacao)
    ));

    if (empty($solicitacao)) {
        return array('Solicitacao nao encontrada.', true);
    }
    if (strval(tg($solicitacao, 'soli_tx_status_gestor', '')) !== 'aprovado') {
        return array('Somente solicitacoes aprovadas podem ser excluidas por aqui.', true);
    }

    if (!$isSuperAdmin) {
        $perm = tt_fetch_assoc_safe(tt_query(
            "SELECT apro_nb_id
             FROM solicitacao_troca_horario_aprovadores
             WHERE apro_nb_solicitacao = ? AND apro_nb_entidade = ?
             LIMIT 1",
            "ii",
            array($idSolicitacao, $idEntidade)
        ));
        if (empty($perm)) {
            return array('Voce nao tem permissao para excluir esta solicitacao.', true);
        }
    }

    if (!tt_trocaPodeSerExcluida($solicitacao)) {
        return array('Nao e mais possivel excluir: a troca ja iniciou ou esta prevista para hoje.', true);
    }

    $idSolicitante = intval(tg($solicitacao, 'soli_nb_entidade', 0));
    $idDestino = intval(tg($solicitacao, 'soli_nb_entidade_destino', 0));
    $idInstancia = intval(tg($solicitacao, 'soli_nb_id_instancia', 0));

    tt_removerAssinaturasTroca($idSolicitacao, $idInstancia);

    tt_query("DELETE FROM solicitacao_troca_horario_aprovadores WHERE apro_nb_solicitacao = ?", "i", array($idSolicitacao));
    tt_query("DELETE FROM notificacao_troca_turno WHERE noti_nb_solicitacao = ?", "i", array($idSolicitacao));
    // A linha guarda as duas pontas da troca (data_troca e data_pagara). Como o espelho
    // so aplica trocas com soli_tx_status_gestor = 'aprovado', remover a linha devolve a
    // previsao de jornada normal nas DUAS datas — inclusive na data que pagara.
    tt_query("DELETE FROM solicitacao_troca_horario WHERE soli_nb_id = ? AND soli_tx_status_gestor = 'aprovado'", "i", array($idSolicitacao));

    $aindaExiste = tt_fetch_assoc_safe(tt_query(
        "SELECT soli_nb_id FROM solicitacao_troca_horario WHERE soli_nb_id = ? LIMIT 1",
        "i",
        array($idSolicitacao)
    ));
    if (!empty($aindaExiste)) {
        return array('Nao foi possivel excluir a solicitacao.', true);
    }

    $msgCancelamento = 'A troca de turno aprovada foi cancelada pelo gestor. As previsoes de trabalho voltaram ao normal.';
    tt_criarNotificacao($idSolicitacao, $idSolicitante, 'resultado', $msgCancelamento);
    tt_criarNotificacao($idSolicitacao, $idDestino, 'resultado', $msgCancelamento);

    return array('Solicitacao #'.$idSolicitacao.' excluida e previsoes de trabalho restauradas.', false);
}

// Entry-point do Contex para acao do formulario (acao=decidir).
function decidir() {
    list($mensagem, $erro) = tt_processarDecisaoGestor();
    tt_setFlashGestao($mensagem, $erro);
    header('Location: gestao_troca_turno.php');
    exit;
}

// Entry-point do Contex para exclusao de troca aprovada (acao=excluirTrocaAprovada).
function excluirTrocaAprovada() {
    list($mensagem, $erro) = tt_processarExclusaoTroca();
    tt_setFlashGestao($mensagem, $erro);
    header('Location: gestao_troca_turno.php');
    exit;
}

include_once "../conecta.php";

tt_ensureSchema();

$idUser = intval(tg($_SESSION, 'user_nb_id', 0));
$idEntidade = intval(tg($_SESSION, 'user_nb_entidade', 0));
$isSuperAdmin = tt_isSuperAdmin();

if ($idUser <= 0) {
    header("Location: ../index.php");
    exit;
}

list($mensagem, $erro) = tt_getFlashGestao();

$filtroPadrao = $isSuperAdmin ? 'todas' : 'pendente';
$filtro = strtolower(trim(strval(tg($_GET, 'status', $filtroPadrao))));
if (!in_array($filtro, array('pendente', 'aprovado', 'rejeitado', 'todas'), true)) {
    $filtro = $filtroPadrao;
}

if ($isSuperAdmin) {
    $where = "WHERE 1=1";
    $types = "";
    $vars = array();

    if ($filtro === 'pendente') {
        $where .= " AND s.soli_tx_status_gestor = 'pendente'";
    } elseif ($filtro === 'aprovado') {
        $where .= " AND s.soli_tx_status_gestor = 'aprovado'";
    } elseif ($filtro === 'rejeitado') {
        $where .= " AND s.soli_tx_status_gestor = 'rejeitado'";
    }

    $res = query(
        "SELECT s.*, s.soli_tx_status_gestor AS apro_tx_status,
              COALESCE(sol.enti_tx_nome, s.soli_tx_nome_solicitante) AS solicitante_nome,
              COALESCE(sol.enti_tx_matricula, s.soli_tx_matricula_solicitante) AS solicitante_matricula,
              COALESCE(dest.enti_tx_nome, s.soli_tx_nome_trabalhara) AS destino_nome,
              COALESCE(dest.enti_tx_matricula, s.soli_tx_matricula_trabalhara) AS destino_matricula,
                u.user_tx_nome AS gestor_decisor
         FROM solicitacao_troca_horario s
          LEFT JOIN entidade sol ON sol.enti_nb_id = s.soli_nb_entidade
         LEFT JOIN entidade dest ON dest.enti_nb_id = s.soli_nb_entidade_destino
         LEFT JOIN user u ON u.user_nb_id = s.soli_nb_user_visto
         {$where}
         ORDER BY s.soli_tx_dataCadastro DESC",
        $types,
        $vars
    );
} else {
    $where = "WHERE a.apro_nb_entidade = ?";
    $types = "i";
    $vars = array($idEntidade);

    if ($filtro === 'pendente') {
        $where .= " AND a.apro_tx_status = 'pendente'";
    } elseif ($filtro === 'aprovado') {
        $where .= " AND a.apro_tx_status = 'aceito'";
    } elseif ($filtro === 'rejeitado') {
        $where .= " AND a.apro_tx_status = 'rejeitado'";
    }

    $res = query(
        "SELECT s.*, a.apro_tx_status,
              COALESCE(sol.enti_tx_nome, s.soli_tx_nome_solicitante) AS solicitante_nome,
              COALESCE(sol.enti_tx_matricula, s.soli_tx_matricula_solicitante) AS solicitante_matricula,
              COALESCE(dest.enti_tx_nome, s.soli_tx_nome_trabalhara) AS destino_nome,
              COALESCE(dest.enti_tx_matricula, s.soli_tx_matricula_trabalhara) AS destino_matricula,
                u.user_tx_nome AS gestor_decisor
         FROM solicitacao_troca_horario_aprovadores a
         JOIN solicitacao_troca_horario s ON s.soli_nb_id = a.apro_nb_solicitacao
          LEFT JOIN entidade sol ON sol.enti_nb_id = s.soli_nb_entidade
         LEFT JOIN entidade dest ON dest.enti_nb_id = s.soli_nb_entidade_destino
         LEFT JOIN user u ON u.user_nb_id = s.soli_nb_user_visto
         {$where}
         ORDER BY s.soli_tx_dataCadastro DESC",
        $types,
        $vars
    );
}

$solicitacoes = ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
$tituloLista = $isSuperAdmin ? 'Todas as solicitacoes de troca de turno' : 'Solicitacoes sob sua responsabilidade';

cabecalho("Gestao de Troca de Turno");
?>

<div class="row">
    <div class="col-md-12">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <span class="caption-subject bold font-dark"><?php echo htmlspecialchars($tituloLista); ?></span>
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
                                <th>Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($solicitacoes)): ?>
                            <tr><td colspan="9" style="text-align:center;color:#666;">Nenhuma solicitacao encontrada.</td></tr>
                        <?php else: foreach ($solicitacoes as $s): ?>
                            <?php
                                $statusGlobal = strval(tg($s, 'soli_tx_status_gestor', 'pendente'));
                                $badge = "<span class='label label-warning'>Pendente</span>";
                                if ($statusGlobal === 'aprovado') $badge = "<span class='label label-success'>Aprovado</span>";
                                if ($statusGlobal === 'rejeitado') $badge = "<span class='label label-danger'>Rejeitado</span>";
                                $podeExcluirTroca = ($statusGlobal === 'aprovado' && tt_trocaPodeSerExcluida($s));
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
                                <td style="min-width:120px;">
                                    <?php if ($podeExcluirTroca): ?>
                                        <form method="post" style="margin:0;" onsubmit="return confirm('Excluir a solicitacao aprovada #<?php echo intval(tg($s, 'soli_nb_id', 0)); ?>? As previsoes de trabalho voltarao ao normal.');">
                                            <input type="hidden" name="acao" value="excluirTrocaAprovada">
                                            <input type="hidden" name="id_solicitacao" value="<?php echo intval(tg($s, 'soli_nb_id', 0)); ?>">
                                            <button class="btn btn-danger btn-sm" type="submit"><i class="fa fa-trash"></i> Excluir</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:#aaa;">-</span>
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
