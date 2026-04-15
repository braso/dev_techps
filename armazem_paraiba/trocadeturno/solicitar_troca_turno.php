<?php
// Escreve trilha tecnica no arquivo de debug do modulo de troca de turno.
function tt_log_runtime($mensagem) {
    $linha = date('Y-m-d H:i:s')." | ".$mensagem.PHP_EOL;
    @file_put_contents(__DIR__."/../debug_log_trocadeturno.txt", $linha, FILE_APPEND);
}

register_shutdown_function(function () {
    $e = error_get_last();
    if (!is_array($e)) {
        return;
    }
    $tiposFatais = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    if (!in_array(intval($e['type']), $tiposFatais, true)) {
        return;
    }
    tt_log_runtime("FATAL | ".$e['message']." | ".$e['file'].":".$e['line']);
});

include_once __DIR__."/helpers_troca_turno.php";
include_once "../conecta.php";
include_once $_SERVER["DOCUMENT_ROOT"].$_ENV["APP_PATH"]."/contex20/funcoes_form.php";

tt_ensureSchema();

// Acesso seguro a chaves de array sem avisos de indice indefinido.
function tt_s($arr, $k, $d = '') {
    return (is_array($arr) && isset($arr[$k])) ? $arr[$k] : $d;
}

// Normaliza datas vindas da tela para formato SQL (Y-m-d).
function tt_dataParaSql($valor) {
    $valor = trim(strval($valor));
    if ($valor === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
        return $valor;
    }
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor, $m)) {
        return $m[3].'-'.$m[2].'-'.$m[1];
    }
    return $valor;
}

// Fluxo principal de gravacao da solicitacao de troca de horario.
function tt_salvarSolicitacaoTela() {
    if (!function_exists('tt_buscarUsuarioAtual')) {
        include_once __DIR__."/helpers_troca_turno.php";
    }

    tt_log_runtime('POST iniciar envio solicitacao');

    $usuario = tt_buscarUsuarioAtual();
    if (empty($usuario)) {
        set_status("ERRO: Usuario nao identificado.");
        header("Location: solicitar_troca_turno.php");
        exit;
    }

    $idSolicitante = intval(tt_s($usuario, 'enti_nb_id', 0));
    $matriculaDestino = preg_replace('/[^A-Za-z0-9\-_]/', '', strval(tt_s($_POST, 'matricula_trabalhara', '')));
    $dataTroca = trim(strval(tt_s($_POST, 'data_troca', '')));
    $turnoTroca = trim(strval(tt_s($_POST, 'turno_troca', '')));
    $dataPagara = trim(strval(tt_s($_POST, 'data_pagara', '')));
    $turnoPagara = trim(strval(tt_s($_POST, 'turno_pagara', '')));
    $complemento = trim(strval(tt_s($_POST, 'complemento', '')));

    $dataTrocaSql = tt_dataParaSql($dataTroca);
    $dataPagaraSql = tt_dataParaSql($dataPagara);

    if ($matriculaDestino === '' || $dataTroca === '' || $turnoTroca === '') {
        set_status("ERRO: Preencha matricula, data da troca e turno da troca.");
        header("Location: solicitar_troca_turno.php");
        exit;
    }

    $destino = tt_buscarPorMatricula($matriculaDestino);
    if (empty($destino)) {
        set_status("ERRO: Matricula informada nao foi encontrada.");
        header("Location: solicitar_troca_turno.php");
        exit;
    }

    $idDestino = intval(tt_s($destino, 'enti_nb_id', 0));
    if ($idDestino === $idSolicitante) {
        set_status("ERRO: A matricula informada deve ser de outro colaborador.");
        header("Location: solicitar_troca_turno.php");
        exit;
    }

    list($setorSolicitante, $subsetorSolicitante) = tt_buscarSetorSubsetor($idSolicitante);
    list($setorDestino, $subsetorDestino) = tt_buscarSetorSubsetor($idDestino);

    $cpfRaw = preg_replace('/\D/', '', strval(tt_s($usuario, 'user_tx_cpf', '')));
    $cpfFmt = (strlen($cpfRaw) === 11)
        ? substr($cpfRaw, 0, 3).".".substr($cpfRaw, 3, 3).".".substr($cpfRaw, 6, 3)."-".substr($cpfRaw, 9, 2)
        : strval(tt_s($usuario, 'user_tx_cpf', ''));

    $campos = array(
        'soli_nb_entidade' => $idSolicitante,
        'soli_nb_entidade_destino' => $idDestino,
        'soli_tx_matricula_solicitante' => strval(tt_s($usuario, 'enti_tx_matricula', '')),
        'soli_tx_nome_solicitante' => strval(tt_s($usuario, 'user_tx_nome', '')),
        'soli_tx_setor_solicitante' => $setorSolicitante,
        'soli_tx_subsetor_solicitante' => $subsetorSolicitante,
        'soli_tx_cpf_solicitante' => $cpfFmt,
        'soli_tx_data_solicitacao' => date('Y-m-d'),
        'soli_tx_matricula_trabalhara' => strval(tt_s($destino, 'enti_tx_matricula', '')),
        'soli_tx_nome_trabalhara' => strval(tt_s($destino, 'user_tx_nome', '')),
        'soli_tx_setor_trabalhara' => $setorDestino,
        'soli_tx_subsetor_trabalhara' => $subsetorDestino,
        'soli_tx_data_troca' => $dataTrocaSql,
        'soli_tx_turno_troca' => $turnoTroca,
        'soli_tx_data_pagara' => ($dataPagaraSql !== '' ? $dataPagaraSql : ''),
        'soli_tx_turno_pagara' => $turnoPagara,
        'soli_tx_complemento' => $complemento,
        'soli_tx_aceite_status' => 'pendente',
        'soli_tx_status_gestor' => 'pendente',
        'soli_tx_dataCadastro' => date('Y-m-d H:i:s')
    );

    $resIns = inserir('solicitacao_troca_horario', array_keys($campos), array_values($campos));
    tt_log_runtime('POST retorno inserir recebido');
    $idSolicitacao = 0;
    if (is_array($resIns) && isset($resIns[0])) {
        if ($resIns[0] instanceof Exception) {
            set_status('ERRO: Falha ao salvar solicitacao. Verifique os dados e tente novamente.');
            header("Location: solicitar_troca_turno.php");
            exit;
        }
        if (is_numeric($resIns[0])) {
            $idSolicitacao = intval($resIns[0]);
        }
    }

    if ($idSolicitacao <= 0) {
        set_status("ERRO: Nao foi possivel salvar a solicitacao.");
        header("Location: solicitar_troca_turno.php");
        exit;
    }

    $gestores1 = tt_obterGestoresPorEntidade($idSolicitante);
    $gestores2 = tt_obterGestoresPorEntidade($idDestino);
    $gestores = tt_consolidarGestores($gestores1, $gestores2);

    foreach ($gestores as $g) {
        $idGestorEntidade = intval(tt_s($g, 'id', 0));
        if ($idGestorEntidade <= 0) {
            continue;
        }
        $tipo = (tt_s($g, 'tipo', 'setor') === 'cargo') ? 'cargo' : 'setor';

        tt_query(
            "INSERT INTO solicitacao_troca_horario_aprovadores
                (apro_nb_solicitacao, apro_nb_entidade, apro_tx_tipo, apro_tx_status)
             VALUES (?, ?, ?, 'pendente')
             ON DUPLICATE KEY UPDATE apro_tx_status = 'pendente', apro_nb_user_decisao = NULL, apro_tx_data_decisao = NULL",
            "iis",
            array($idSolicitacao, $idGestorEntidade, $tipo)
        );
    }
    tt_log_runtime('POST aprovadores processados');

    tt_criarNotificacao(
        $idSolicitacao,
        $idDestino,
        'destino',
        'Voce foi informado em uma solicitacao de troca de turno feita por '.strval(tt_s($usuario, 'user_tx_nome', '')).'.'
    );

    foreach ($gestores as $g) {
        $idGestorEntidade = intval(tt_s($g, 'id', 0));
        if ($idGestorEntidade <= 0) {
            continue;
        }
        tt_criarNotificacao(
            $idSolicitacao,
            $idGestorEntidade,
            'gestor',
            'Nova solicitacao de troca de turno aguardando decisao de gestor.'
        );
    }

    set_status('Solicitacao #'.$idSolicitacao.' enviada com sucesso.');
    tt_log_runtime('POST solicitacao enviada com sucesso: '.$idSolicitacao);
    header('Location: solicitar_troca_turno.php?sucesso=1&id='.$idSolicitacao);
    exit;
}

// Entry-point do Contex para acao de submit (acao=salvarSolicitacao).
function salvarSolicitacao() {
    tt_salvarSolicitacaoTela();
}

if (tt_s($_POST, 'acao', '') === 'salvarSolicitacao') {
    try {
        tt_salvarSolicitacaoTela();
    } catch (Exception $e) {
        tt_log_runtime('EXCEPTION | '.$e->getMessage().' | '.$e->getFile().':'.$e->getLine());
        set_status('ERRO: Falha inesperada ao enviar solicitacao.');
        header('Location: solicitar_troca_turno.php');
        exit;
    } catch (Error $e) {
        tt_log_runtime('ERROR | '.$e->getMessage().' | '.$e->getFile().':'.$e->getLine());
        set_status('ERRO: Falha inesperada ao enviar solicitacao.');
        header('Location: solicitar_troca_turno.php');
        exit;
    }
}

$usuario = tt_buscarUsuarioAtual();
if (empty($usuario)) {
    header('Location: ../batida_ponto.php');
    exit;
}

$idUsuarioEntidade = intval(tt_s($usuario, 'enti_nb_id', 0));
list($setorUsuario, $subsetorUsuario) = tt_buscarSetorSubsetor($idUsuarioEntidade);

$cpfRaw = preg_replace('/\D/', '', strval(tt_s($usuario, 'user_tx_cpf', '')));
$cpfFmt = (strlen($cpfRaw) === 11)
    ? substr($cpfRaw, 0, 3).".".substr($cpfRaw, 3, 3).".".substr($cpfRaw, 6, 3)."-".substr($cpfRaw, 9, 2)
    : strval(tt_s($usuario, 'user_tx_cpf', ''));

$notificacoes = tt_buscarNotificacoes($idUsuarioEntidade, 5);
$turnoSolicitante = tt_buscarTurnoEntidade($idUsuarioEntidade);

$resHistory = tt_query(
    "SELECT s.*,
            CASE WHEN s.soli_nb_entidade = ? THEN 'Enviada' ELSE 'Recebida' END AS tipo_visualizacao,
            u.user_tx_nome AS gestor_nome
     FROM solicitacao_troca_horario s
     LEFT JOIN user u ON u.user_nb_id = s.soli_nb_user_visto
     WHERE s.soli_nb_entidade = ? OR s.soli_nb_entidade_destino = ?
     ORDER BY s.soli_tx_dataCadastro DESC",
    "iii",
    array($idUsuarioEntidade, $idUsuarioEntidade, $idUsuarioEntidade)
);
$historico = ($resHistory instanceof mysqli_result) ? mysqli_fetch_all($resHistory, MYSQLI_ASSOC) : array();

cabecalho('Troca de Horario');

if (!empty($_GET['sucesso']) && !empty($_GET['id'])) {
    echo "<div class='alert alert-success'>Solicitacao #".intval($_GET['id'])." registrada com sucesso.</div>";
}

if (!empty($notificacoes)) {
    echo "<div class='row'><div class='col-sm-12'><div class='portlet light'>";
    echo "<div class='portlet-title'><div class='caption'><span class='caption-subject font-dark bold'>Notificacoes</span></div></div>";
    echo "<div class='portlet-body'><ul style='margin:0;padding-left:18px;'>";
    foreach ($notificacoes as $n) {
        echo "<li>".htmlspecialchars(strval(tt_s($n, 'noti_tx_mensagem', '')))."</li>";
    }
    echo "</ul></div></div></div></div>";
}

$turnos = array(
    "" => "Selecione...",
    "M" => "Manha - M",
    "T" => "Tarde - T",
    "V" => "Vespertino - V",
    "N" => "Noite - N",
    "D" => "Diurno - D"
);

$fieldsSolicitante = array(
    "<div class='col-sm-12'><h4 style='border-bottom:1px solid #ddd;padding-bottom:6px;margin-bottom:12px;'><i class='fa fa-user'></i> Solicitante</h4></div>",
    texto('Nome', strval(tt_s($usuario, 'user_tx_nome', '')), 4),
    texto('Matricula', strval(tt_s($usuario, 'enti_tx_matricula', '')), 2),
    texto('Setor', $setorUsuario, 2),
    texto('Subsetor', $subsetorUsuario, 2),
    texto('CPF', $cpfFmt, 2),
    texto('Data da Solicitacao', date('d/m/Y'), 2)
);

// Data minima d+1
$dataMinima = date('Y-m-d', strtotime('+1 day'));


$fieldsTroca = array(
    "<div class='col-sm-12'><h4 style='border-bottom:1px solid #ddd;padding-bottom:6px;margin-bottom:12px;margin-top:16px;'><i class='fa fa-exchange'></i> Trabalhara Para</h4></div>",
    "<div class='col-sm-1 margin-bottom-5'><label>Matricula</label><input type='text' name='matricula_trabalhara' id='matricula_trabalhara' class='form-control input-sm' maxlength='8' placeholder='Digite a matricula...' autocomplete='off'><small id='msg_busca_matricula' style='color:#888;'></small></div>",
    "<div class='col-sm-1 margin-bottom-5'><label>&nbsp;</label><button type='button' class='btn btn-sm btn-default form-control input-sm' id='btn_buscar_matricula'><i class='fa fa-search'></i></button></div>",
    "<div class='col-sm-3 margin-bottom-5'><label>Nome</label><input type='text' name='nome_trabalhara' id='nome_trabalhara' class='form-control input-sm' readonly></div>",
    "<div class='col-sm-2 margin-bottom-5'><label>Setor</label><input type='text' name='setor_trabalhara' id='setor_trabalhara' class='form-control input-sm' readonly></div>",
    "<div class='col-sm-2 margin-bottom-5'><label>Subsetor</label><input type='text' name='subsetor_trabalhara' id='subsetor_trabalhara' class='form-control input-sm' readonly></div>",
    campo_data('Data da Troca', 'data_troca', '', 2, "min='$dataMinima'"),
    combo('Turno', 'turno_troca', strval(tt_s($turnoSolicitante, 'codigo', '')), 2, $turnos),
    "<div class='col-sm-2 margin-bottom-5'><small id='msg_turno_troca' style='color:#888;'></small></div>",
    campo_data('Data que Pagara', 'data_pagara', '', 2, "min='$dataMinima'"),
    combo('Turno que Pagara', 'turno_pagara', '', 2, $turnos),
    "<div class='col-sm-2 margin-bottom-5'><small id='msg_turno_pagara' style='color:#888;'></small></div>",
    textarea('Complemento', 'complemento', '', 12, "rows='3' placeholder='Observacoes ou informacoes adicionais...'")
);

$buttons = array(
    botao('Enviar Solicitacao', 'salvarSolicitacao', '', '', '', '', 'btn btn-success'),
    "<a href='../batida_ponto.php' class='btn btn-default'>Cancelar</a>"
);

echo abre_form('Solicitacao de Troca de Horario');
echo linha_form($fieldsSolicitante);
echo linha_form($fieldsTroca);
echo fecha_form($buttons);

echo "<script>
// Busca dados do colaborador informado e preenche os campos da tela.
function tentarAbrirSelect(el){
 if(!el){return;}
 el.focus();
 try {
     if(typeof el.showPicker === 'function'){
         el.showPicker();
     } else {
         el.click();
     }
 } catch(e){
     try { el.click(); } catch(_e){}
 }
}

function preencherPorMatricula(){
 var matEl=document.getElementById('matricula_trabalhara');
 var msg=document.getElementById('msg_busca_matricula');
 var nome=document.getElementById('nome_trabalhara');
 var setor=document.getElementById('setor_trabalhara');
 var subsetor=document.getElementById('subsetor_trabalhara');
 var turnoPagara=document.getElementsByName('turno_pagara')[0];
 var msgTurnoPagara=document.getElementById('msg_turno_pagara');
 if(!matEl){return;}
 var matricula=(matEl.value||'').trim();
 if(matricula===''){ if(msg){msg.innerHTML='<span style=\'color:red;\'>Informe a matricula.</span>';} return; }
 if(msg){msg.innerHTML='<i class=\'fa fa-spinner fa-spin\'></i> Buscando...';}
 if(nome){nome.value='';} if(setor){setor.value='';} if(subsetor){subsetor.value='';}
 if(turnoPagara){turnoPagara.value='';}
 if(msgTurnoPagara){msgTurnoPagara.innerHTML='';}
 fetch('api_busca_matricula.php?matricula='+encodeURIComponent(matricula),{credentials:'same-origin'})
 .then(function(r){return r.text();})
 .then(function(text){
     var raw=(text||'').replace(/^\uFEFF/, '').trim();
     var res={};
     try{ res=JSON.parse(raw||'{}'); }
     catch(e){
         if(msg){msg.innerHTML='<span style=\'color:red;\'>Resposta invalida da API</span>';}
         return;
     }
     if(res.sucesso){
         if(nome){nome.value=res.nome||'';}
         if(setor){setor.value=res.setor||'';}
         if(subsetor){subsetor.value=res.subsetor||'';}
         if(turnoPagara){
             var turnoCodigo=(res.turno_codigo||'').toString().trim();
             if(turnoCodigo!==''){
                 turnoPagara.value=turnoCodigo;
                 if(msgTurnoPagara){msgTurnoPagara.innerHTML='<span style=\'color:green;\'>Turno preenchido automaticamente.</span>';}
             } else {
                 turnoPagara.value='';
                 tentarAbrirSelect(turnoPagara);
                 if(msgTurnoPagara){msgTurnoPagara.innerHTML='<span style=\'color:#b35a00;\'>Sem turno cadastrado. Selecione manualmente.</span>';}
             }
         }
         if(msg){msg.innerHTML='<span style=\'color:green;\'>Encontrado</span>';}
     } else {
         if(msg){msg.innerHTML='<span style=\'color:red;\'>'+(res.msg||'Nao encontrado')+'</span>';}
     }
 })
 .catch(function(){ if(msg){msg.innerHTML='<span style=\'color:red;\'>Erro ao buscar</span>';}});
}

// Liga os eventos da UI quando a pagina termina de carregar.
document.addEventListener('DOMContentLoaded', function(){
 var btn=document.getElementById('btn_buscar_matricula');
 var input=document.getElementById('matricula_trabalhara');
 var turnoTroca=document.getElementsByName('turno_troca')[0];
 var msgTurnoTroca=document.getElementById('msg_turno_troca');
 if(btn){btn.addEventListener('click', preencherPorMatricula);} 
 if(input){ input.addEventListener('keydown', function(ev){ if(ev.key==='Enter'){ev.preventDefault(); preencherPorMatricula();} }); }
 if(turnoTroca){
     if((turnoTroca.value||'').trim()!==''){
         if(msgTurnoTroca){msgTurnoTroca.innerHTML='<span style=\'color:green;\'>Turno do solicitante preenchido automaticamente.</span>';}
     } else {
         tentarAbrirSelect(turnoTroca);
         if(msgTurnoTroca){msgTurnoTroca.innerHTML='<span style=\'color:#b35a00;\'>Solicitante sem turno cadastrado. Selecione manualmente.</span>';}
     }
 }
});
</script>";

echo "<div class='row' style='margin-top:20px;'><div class='col-sm-12'><div class='portlet light'>";
echo "<div class='portlet-title'><div class='caption'><span class='caption-subject font-dark bold'>Historico de Solicitacoes</span></div></div>";
echo "<div class='portlet-body'><div class='table-responsive'><table class='table table-striped table-hover'>";
echo "<thead><tr><th>Data</th><th>Tipo</th><th>Solicitante</th><th>Troca com</th><th>Data Troca</th><th>Status Gestor</th><th>Gestor</th></tr></thead><tbody>";

if (empty($historico)) {
    echo "<tr><td colspan='7' class='text-center alert alert-info'>Nenhuma solicitacao encontrada.</td></tr>";
} else {
    foreach ($historico as $h) {
        $statusGestor = strval(tt_s($h, 'soli_tx_status_gestor', 'pendente'));
        $badge = "<span class='label label-warning'>Pendente</span>";
        if ($statusGestor === 'aprovado') { $badge = "<span class='label label-success'>Aprovado</span>"; }
        if ($statusGestor === 'rejeitado') { $badge = "<span class='label label-danger'>Rejeitado</span>"; }

        echo "<tr>";
        echo "<td>".htmlspecialchars(strval(tt_s($h, 'soli_tx_dataCadastro', '')))."</td>";
        echo "<td>".htmlspecialchars(strval(tt_s($h, 'tipo_visualizacao', '')))."</td>";
        echo "<td>".htmlspecialchars(strval(tt_s($h, 'soli_tx_nome_solicitante', '')))."</td>";
        echo "<td>".htmlspecialchars(strval(tt_s($h, 'soli_tx_nome_trabalhara', '')))."</td>";
        echo "<td>".htmlspecialchars(strval(tt_s($h, 'soli_tx_data_troca', '')))."</td>";
        echo "<td>{$badge}</td>";
        echo "<td>".htmlspecialchars(strval(tt_s($h, 'gestor_nome', '-')))."</td>";
        echo "</tr>";
    }
}

echo "</tbody></table></div></div></div></div></div>";

rodape();
