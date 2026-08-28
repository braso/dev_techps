<?php
include_once __DIR__."/helpers_diarias.php";
include_once "../check_permission.php";

// Acesso seguro a chaves de array sem gerar aviso quando nao existir.
function db($arr, $k, $d = '') {
    return (is_array($arr) && isset($arr[$k])) ? $arr[$k] : $d;
}

function db_setFlash($mensagem, $erro) {
    $_SESSION['diarias_base_msg'] = strval($mensagem);
    $_SESSION['diarias_base_erro'] = ($erro ? 1 : 0);
}

function db_getFlash() {
    $mensagem = strval(db($_SESSION, 'diarias_base_msg', ''));
    $erro = intval(db($_SESSION, 'diarias_base_erro', 0)) === 1;
    unset($_SESSION['diarias_base_msg']);
    unset($_SESSION['diarias_base_erro']);
    return array($mensagem, $erro);
}

function db_buscarTodas($empresaId = 0) {
    $filtro = '';
    $vars = array();
    if (intval($empresaId) > 0) {
        $filtro = " WHERE diba_nb_empresa = ?";
        $vars = array(intval($empresaId));
    }
    $res = diar_query(
        "SELECT b.*, e.empr_tx_nome
         FROM diaria_base b
         LEFT JOIN empresa e ON e.empr_nb_id = b.diba_nb_empresa
         {$filtro}
         ORDER BY b.diba_tx_status ASC, e.empr_tx_nome ASC, b.diba_tx_nome ASC",
        ($vars ? "i" : ""),
        $vars
    );
    return ($res instanceof mysqli_result) ? mysqli_fetch_all($res, MYSQLI_ASSOC) : array();
}

function db_salvar() {
    $idUser = intval(db($_SESSION, 'user_nb_id', 0));
    if ($idUser <= 0) {
        header("Location: ../index.php");
        exit;
    }

    $id = intval(db($_POST, 'id', 0));
    $nome = trim(strval(db($_POST, 'nome', '')));
    $empresa = intval(db($_POST, 'empresa', 0));
    $latitude = str_replace(',', '.', trim(strval(db($_POST, 'latitude', ''))));
    $longitude = str_replace(',', '.', trim(strval(db($_POST, 'longitude', ''))));
    $status = (strval(db($_POST, 'status', 'ativo')) === 'inativo') ? 'inativo' : 'ativo';
    $ehbase = (strval(db($_POST, 'ehbase', 'nao')) === 'sim') ? 'sim' : 'nao';
    $poiId = intval(db($_POST, 'poi_id', 0));

    // O raio vem exclusivamente do POI selecionado (cadastro do POI). Default 1000 se sem POI.
    $raio = 1000;
    if ($poiId > 0) {
        $rPoi = diar_fetch_assoc_safe(diar_query(
            "SELECT poi_nb_raio FROM poi WHERE poi_nb_id = ? LIMIT 1",
            "i", array($poiId)
        ));
        if (!empty($rPoi['poi_nb_raio'])) {
            $raio = intval($rPoi['poi_nb_raio']);
        }
    }

    if ($nome === '' || $empresa <= 0 || !is_numeric($latitude) || !is_numeric($longitude)) {
        db_setFlash('ERRO: Preencha nome, empresa, latitude e longitude.', true);
        header("Location: bases_diarias.php");
        exit;
    }

    if ($id > 0) {
        diar_query(
            "UPDATE diaria_base
                SET diba_tx_nome = ?, diba_nb_empresa = ?, diba_tx_latitude = ?, diba_tx_longitude = ?,
                    diba_nb_raio = ?, diba_tx_status = ?
             WHERE diba_nb_id = ?",
            "sissisi",
            array($nome, $empresa, $latitude, $longitude, $raio, $status, $id)
        );
        diar_log_runtime("Base {$id} atualizada: {$nome}");
    } else {
        diar_query(
            "INSERT INTO diaria_base (diba_nb_empresa, diba_tx_nome, diba_tx_latitude, diba_tx_longitude, diba_nb_raio, diba_tx_status, diba_nb_user)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            "isssisi",
            array($empresa, $nome, $latitude, $longitude, $raio, $status, $idUser)
        );
        diar_log_runtime("Base criada: {$nome} (empresa {$empresa})");
    }

    // Se ehbase=sim, marcar o POI selecionado como base (desliga os demais).
    if ($ehbase === 'sim') {
        $poiId = intval(db($_POST, 'poi_id', 0));
        if ($poiId > 0) {
            diar_marcarPoiBase($poiId);
        }
    }

    db_setFlash('Base salva com sucesso.', false);
    header("Location: bases_diarias.php");
    exit;
}

function db_excluir() {
    $id = intval(db($_POST, 'id', 0));
    if ($id <= 0) {
        db_setFlash('ERRO: Base invalida.', true);
        header("Location: bases_diarias.php");
        exit;
    }
    diar_query("UPDATE diaria_base SET diba_tx_status = 'inativo' WHERE diba_nb_id = ?", "i", array($id));
    diar_log_runtime("Base {$id} desativada");
    db_setFlash('Base desativada.', false);
    header("Location: bases_diarias.php");
    exit;
}

// Entry-points do Contex: o framework chama a funcao com o nome da acao do botao.
function salvar() { db_salvar(); }
function excluir() { db_excluir(); }
function marcarPoiBase() {
    $id = intval(db($_POST, 'poi_id', 0));
    if ($id <= 0) {
        db_setFlash('ERRO: POI invalido.', true);
        header("Location: bases_diarias.php");
        exit;
    }
    diar_marcarPoiBase($id);
    db_setFlash('POI marcado como base de referencia.', false);
    header("Location: bases_diarias.php");
    exit;
}

include_once "../conecta.php";

diar_ensureSchema();

$idUser = intval(db($_SESSION, 'user_nb_id', 0));
if ($idUser <= 0) {
    header("Location: ../index.php");
    exit;
}

list($mensagem, $erro) = db_getFlash();

$empresas = diar_buscarEmpresas();
$bases = db_buscarTodas();
$pois = diar_buscarPois();

cabecalho("Bases para Diarias");
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($mensagem !== ''): ?>
            <div class="alert <?php echo $erro ? 'alert-danger' : 'alert-success'; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <span class="caption-subject bold font-dark"><i class="fa fa-home"></i> Cadastro de Bases</span>
                </div>
                <div class="actions">
                    <span class="label label-info">Base = referencia para detectar "pernoite fora da base"</span>
                </div>
            </div>
            <div class="portlet-body">
                <div class="alert alert-info" style="font-size:13px;">
                    A base e o local de referencia para detectar pernoite. Selecione o POI
                    e marque como base. O raio considerado "dentro da base" e o do cadastro do POI.
                    Informe latitude e longitude da base.
                </div>
                <form method="post">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="id" value="0" id="base_id">
                    <input type="hidden" name="poi_id" value="0" id="poi_id_field">
                    <div class="row">
                        <div class="col-sm-12">
                            <label>Aproveitar dados de um POI cadastrado</label>
                            <select class="form-control input-sm" id="select_poi">
                                <option value="">-- Selecione um POI para preencher --</option>
                                <?php if (empty($pois)): ?>
                                    <option value="" disabled>Nenhum POI ativo com coordenadas.</option>
                                <?php else: foreach ($pois as $poi): ?>
                                    <option value="<?php echo intval($poi['poi_nb_id']); ?>"
                                        data-nome="<?php echo htmlspecialchars(strval($poi['poi_tx_nome'])); ?>"
                                        data-lat="<?php echo htmlspecialchars(strval($poi['poi_tx_latitude'])); ?>"
                                        data-lon="<?php echo htmlspecialchars(strval($poi['poi_tx_longitude'])); ?>"
                                        data-raio="<?php echo intval($poi['poi_nb_raio']); ?>">
                                        <?php echo htmlspecialchars(strval($poi['poi_tx_nome'])); ?>
                                        <?php if (!empty($poi['poi_tx_endereco'])): ?>
                                            - <?php echo htmlspecialchars(strval($poi['poi_tx_endereco'])); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; endif; ?>
                            </select>
                            <small style="color:#888;">Ao selecionar, nome, latitude, longitude e raio sao preenchidos automaticamente. Edite se precisar.</small>
                        </div>
                    </div>
                    <?php
                        echo linha_form(array(
                            campo('Nome*', 'nome', '', 3, '', "placeholder='Ex.: Matriz Natal'"),
                            combo_net('Empresa*', 'empresa', 0, 3, 'empresa', ""),
                            campo('Latitude*', 'latitude', '', 2, '', "placeholder='-5.79448' step='any'"),
                            campo('Longitude*', 'longitude', '', 2, '', "placeholder='-35.21100' step='any'"),
                            combo('Status', 'status', 'ativo', 1, array('ativo' => 'Ativo', 'inativo' => 'Inativo')),
                            combo('E base?', 'ehbase', 'nao', 1, array('nao' => 'Nao (comum)', 'sim' => 'Sim (base de referencia)')
                                , "id='ehbase'")
                        ));
                        echo "<div class='form-actions'><div class='fecha-form-btn'>";
                        echo botao('Salvar Base', 'salvar', '', '', '', '', 'btn btn-success');
                        echo " <a href='bases_diarias.php' class='btn btn-default'>Limpar</a>";
                        echo "</div></div>";
                    ?>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <span class="caption-subject bold font-dark"><i class="fa fa-list"></i> Bases Cadastradas</span>
                </div>
                <div class="actions">
                    <span class="label label-default"><?php echo count($bases); ?> base(s)</span>
                </div>
            </div>
            <div class="portlet-body">
                <div style="overflow:auto;">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Empresa</th>
                                <th>Latitude</th>
                                <th>Longitude</th>
                                <th>Raio (m)</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($bases)): ?>
                            <tr><td colspan="7" style="text-align:center;color:#999;padding:20px;"><i class="fa fa-inbox"></i> Nenhuma base cadastrada.</td></tr>
                        <?php else: foreach ($bases as $b): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(strval(db($b, 'diba_tx_nome', ''))); ?></td>
                                <td><?php echo htmlspecialchars(strval(db($b, 'empr_tx_nome', '-'))); ?></td>
                                <td><?php echo htmlspecialchars(strval(db($b, 'diba_tx_latitude', ''))); ?></td>
                                <td><?php echo htmlspecialchars(strval(db($b, 'diba_tx_longitude', ''))); ?></td>
                                <td><?php echo intval(db($b, 'diba_nb_raio', 0)); ?></td>
                                <td><?php echo (strval(db($b, 'diba_tx_status', '')) === 'ativo')
                                    ? "<span class='label label-success'>Ativo</span>"
                                    : "<span class='label label-default'>Inativo</span>"; ?></td>
                                <td style="white-space:nowrap;">
                                    <button type="button" class="btn btn-primary btn-xs btn_editar_base" title="Editar"
                                        data-id="<?php echo intval(db($b, 'diba_nb_id', 0)); ?>"
                                        data-nome="<?php echo htmlspecialchars(strval(db($b, 'diba_tx_nome', ''))); ?>"
                                        data-empresa="<?php echo intval(db($b, 'diba_nb_empresa', 0)); ?>"
                                        data-latitude="<?php echo htmlspecialchars(strval(db($b, 'diba_tx_latitude', ''))); ?>"
                                        data-longitude="<?php echo htmlspecialchars(strval(db($b, 'diba_tx_longitude', ''))); ?>"
                                        data-raio="<?php echo intval(db($b, 'diba_nb_raio', 0)); ?>"
                                        data-status="<?php echo htmlspecialchars(strval(db($b, 'diba_tx_status', 'ativo'))); ?>"><i class="fa fa-pencil"></i></button>
                                    <form method="post" onsubmit="return confirm('Desativar esta base?');" style="display:inline-block;margin:0;">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?php echo intval(db($b, 'diba_nb_id', 0)); ?>">
                                        <button class="btn btn-danger btn-xs" type="submit" title="Desativar"><i class="fa fa-trash"></i></button>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    var selPoi = document.getElementById('select_poi');
    if (selPoi) {
        selPoi.addEventListener('change', function() {
            var poiId = document.getElementById('poi_id_field');
            if (!this.value) { if (poiId) poiId.value = '0'; return; }
            var opt = this.options[this.selectedIndex];
            if (!opt) { if (poiId) poiId.value = '0'; return; }
            if (poiId) poiId.value = this.value;
            var nome = document.querySelector('input[name="nome"]');
            var lat = document.querySelector('input[name="latitude"]');
            var lon = document.querySelector('input[name="longitude"]');
            if (nome) { nome.value = opt.getAttribute('data-nome') || ''; }
            if (lat) { lat.value = opt.getAttribute('data-lat') || ''; }
            if (lon) { lon.value = opt.getAttribute('data-lon') || ''; }
        });
    }

    document.querySelectorAll('.btn_editar_base').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (selPoi) { selPoi.value = ''; }
            document.getElementById('base_id').value = btn.getAttribute('data-id') || '0';
            document.querySelector('input[name="nome"]').value = btn.getAttribute('data-nome') || '';
            document.querySelector('select[name="empresa"]').value = btn.getAttribute('data-empresa') || '0';
            document.querySelector('input[name="latitude"]').value = btn.getAttribute('data-latitude') || '';
            document.querySelector('input[name="longitude"]').value = btn.getAttribute('data-longitude') || '';
            document.querySelector('select[name="status"]').value = btn.getAttribute('data-status') || 'ativo';
            window.scrollTo({top: 0, behavior: 'smooth'});
        });
    });
});
</script>

<?php rodape(); ?>
