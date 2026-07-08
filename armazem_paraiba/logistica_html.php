<script>
document.addEventListener('DOMContentLoaded', function() {
    function getParameter(theParameter) {
        var params = window.location.search.substr(1).split('&');
        for (var i = 0; i < params.length; i++) {
            var p = params[i].split('=');
            if (p[0] === theParameter) {
                return decodeURIComponent(p[1]);
            }
        }
        return false;
    }

    var matricula = getParameter('matricula');
    var id = getParameter('id');
    var data = getParameter('data');

    if (matricula) {
        var motoristaSelect = document.getElementById('id');
        for (var i = 0; i < motoristaSelect.options.length; i++) {
            if (motoristaSelect.options[i].value === matricula) {
                motoristaSelect.selectedIndex = i;
                break;
            }
        }
    }

   if (data) {
        // Garante apenas a parte da data (YYYY-MM-DD)
        var formattedDate = data.split("T")[0].split(" ")[0];

        // Define a data e hora de início como 00:00:00
        var dateStart = formattedDate + "T00:00:00";

        // Define a data e hora de fim como 23:59:59
        var dateEnd = formattedDate + "T23:59:59";
        
        document.getElementById('date_start').value = dateStart;
        document.getElementById('date_end').value = dateEnd;
    }
});
</script>



<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Painel de Ajuste e Não Conformidades.</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/logistica_modal.css">
    <script src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js" type="module"></script>
<!-- Adicione isso ao head do seu HTML -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js" nomodule></script>
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<!-- Leaflet Routing Machine CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<!-- Leaflet Routing Machine JS -->
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>



</head>

<body>

    <!-- Exibe mensagens de erro ou sucesso -->
    <?=($erro)?"<div id='popupErro' class='popup popup-erro'>".htmlspecialchars($erro)."</div>":""?>
    <?=($sucesso)?"<div id='popupSucesso' class='popup popup-sucesso'>".htmlspecialchars($sucesso)."</div>":""?>

    <div id="loading-screen">
        <i class="fas fa-spinner fa-spin"></i>
        <p>Buscando dados, por favor, aguarde...</p>
    </div>

<!-- Adicione isso ao final do seu body -->
<!--<button  id="novoBotao" style="position:fixed; bottom:200px; left:5px; z-index:1000; background-color:#004173; color:white; border:none; padding:10px 5px; border-radius:5px;">Map Grafico 🗺️</button>-->
<button id="mapButton" style="display:none; position:fixed; bottom:150px; left:5px; z-index:1000; background-color:#004173; color:white; border:none; padding:10px 5px; border-radius:5px;">Posiçóes 📍</button>
<div id="mapPopup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:80%; height:80%; background-color:white; border:1px solid #ccc; z-index:1001;">
    <div id="map" style="width:100%; height:100%; position:relative; z-index:1;"></div>
    <button id="closeMapButton" style="position:absolute; top:2px; left:-42px; background-color:#004173; color:white; border:none; padding:10px 20px; border-radius:5px; z-index:1002;">X</button>
</div>

<!-- Menu de contexto do mapa -->
<div id="mapContextMenu" style="display:none; position:fixed; background:white; border:1px solid #ccc; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.2); z-index:2000; min-width:180px; padding:4px 0;">
    <div style="padding:8px 14px; cursor:pointer; font-size:14px; display:flex; align-items:center; gap:8px;" onclick="abrirCadastroPoi()">
        <span style="font-size:18px;">📌</span> Cadastrar POI
    </div>
</div>

<!-- Sidebar de cadastro de POI -->
<div id="poiSidebar" style="display:none; position:fixed; top:0; right:0; width:400px; max-width:95%; height:100vh; background:white; box-shadow:-4px 0 20px rgba(0,0,0,.2); z-index:2001; overflow-y:auto; padding:20px; transition:right .3s ease;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h4 style="margin:0; font-size:18px;">📌 Cadastrar POI</h4>
        <button type="button" onclick="fecharModalPoi()" style="background:none; border:none; font-size:22px; cursor:pointer; padding:4px 8px; color:#999;">&times;</button>
    </div>
    <form id="poiForm" onsubmit="return salvarPoi(event)">
        <input type="hidden" id="poi_id" name="id" value="0">
        <input type="hidden" id="poi_latitude" name="latitude">
        <input type="hidden" id="poi_longitude" name="longitude">
        <div style="margin-bottom:12px;">
            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Nome *</label>
            <input type="text" id="poi_nome" name="nome" required style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
        </div>
        <div style="margin-bottom:12px;">
            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Endereço</label>
            <input type="text" id="poi_endereco" name="endereco" style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
        </div>
        <div style="margin-bottom:12px; display:flex; gap:10px;">
            <div style="flex:1;">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">CEP</label>
                <input type="text" id="poi_cep" name="cep" style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">CNPJ</label>
                <input type="text" id="poi_cnpj" name="cnpj" style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
            </div>
        </div>
        <div style="margin-bottom:12px;">
            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Contato</label>
            <input type="text" id="poi_contato" name="contato" style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
        </div>
        <div style="margin-bottom:12px;">
            <div style="display:flex; gap:6px; align-items:flex-end;">
                <div style="flex:1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Latitude</label>
                    <input type="text" id="poi_lat_display" disabled style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px; background:#f5f5f5;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Longitude</label>
                    <input type="text" id="poi_lon_display" disabled style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px; background:#f5f5f5;">
                </div>
                <button type="button" onclick="escolherPontoMapa()" style="height:38px; padding:8px 10px; border:1px solid #004173; border-radius:6px; background:#004173; color:white; cursor:pointer; font-size:12px; white-space:nowrap;">📍 Mapa</button>
            </div>
        </div>
        <div style="margin-bottom:12px; display:flex; gap:10px;">
            <div style="flex:1;">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Raio (metros)</label>
                <input type="range" id="poi_raio_range" min="10" max="500" value="50" style="width:100%;">
                <input type="number" id="poi_raio" name="raio" value="50" min="1" style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px; margin-top:4px;">
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Tipo de POI</label>
                <select id="poi_icone" name="icone" style="width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
                    <option value="">Selecione o tipo</option>
                    <?php foreach($tiposPoi as $t): ?>
                    <option value="<?=htmlspecialchars($t['poti_tx_codigo'])?>" data-emoji="<?=htmlspecialchars($t['poti_tx_emoji'])?>"><?=$t['poti_tx_emoji']?> <?=htmlspecialchars($t['poti_tx_nome'])?></option>
                    <?php endforeach; ?>
                    <option value="__novo__" style="color:#004173; font-weight:600;">➕ Criar novo tipo...</option>
                </select>
            </div>
        </div>
        <div style="margin-bottom:12px;">
            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:3px;">Imagem do Local</label>
            <input type="file" id="poi_imagem" name="imagem" accept="image/png,image/jpeg,image/gif,image/webp" style="width:100%; padding:6px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px;">
            <div id="poi_imagem_preview" style="margin-top:4px;"></div>
        </div>
        <div style="display:flex; gap:10px; margin-top:16px;">
            <button type="button" onclick="fecharModalPoi()" style="flex:1; padding:10px; border:1px solid #ccc; border-radius:6px; background:#f5f5f5; cursor:pointer; font-size:14px;">Cancelar</button>
            <button type="submit" style="flex:1; padding:10px; border:none; border-radius:6px; background:#004173; color:white; cursor:pointer; font-size:14px;">Salvar POI</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
    const novoBotao = document.getElementById("novoBotao");

    novoBotao.addEventListener("click", function() {
        // Abre a página teste.html em uma nova guia
        window.open("teste.html", "_blank");
    });
});
</script>


<style>
.emojigrid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin-top:6px;max-height:220px;overflow-y:auto;padding:4px;border:1px solid #eee;border-radius:8px;background:#fafafa;}
.emojigrid button{font-size:24px;width:44px;height:44px;display:flex;align-items:center;justify-content:center;border:2px solid transparent;border-radius:8px;background:white;cursor:pointer;transition:all .15s;}
.emojigrid button:hover{border-color:#004173;background:#e8f0fe;transform:scale(1.12);}
.emojigrid button.selecionado{border-color:#004173;background:#d0e2ff;box-shadow:0 0 0 2px #004173;transform:scale(1.1);}
</style>
<div id="modalNovoTipoPoi" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.5); z-index:99999; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:12px; padding:24px; width:480px; max-width:95%; box-shadow:0 8px 30px rgba(0,0,0,.3); position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);">
        <h3 style="margin:0 0 4px 0; font-size:18px;">➕ Criar novo tipo de POI</h3>
        <p style="color:#666; font-size:13px; margin-bottom:14px;">Dê um nome e escolha um ícone.</p>
        <div style="margin-bottom:10px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">Nome <span style="color:red;">*</span></label>
            <input type="text" id="novoTipoNome" class="form-control" placeholder="Ex: Escola" style="width:100%;" autocomplete="off">
        </div>
        <div style="margin-bottom:14px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:4px;">Ícone <span style="color:red;">*</span> <span id="emojiSelecionado" style="font-size:18px; margin-left:6px;"></span></label>
            <div class="emojigrid" id="gradeEmojis">
                <button type="button" data-emoji="📦">📦</button><button type="button" data-emoji="🏢">🏢</button><button type="button" data-emoji="🏭">🏭</button><button type="button" data-emoji="🏪">🏪</button><button type="button" data-emoji="⛽">⛽</button><button type="button" data-emoji="🅿️">🅿️</button><button type="button" data-emoji="🏥">🏥</button>
                <button type="button" data-emoji="🏦">🏦</button><button type="button" data-emoji="🍽️">🍽️</button><button type="button" data-emoji="🏨">🏨</button><button type="button" data-emoji="🚚">🚚</button><button type="button" data-emoji="📍">📍</button><button type="button" data-emoji="🏁">🏁</button><button type="button" data-emoji="🏫">🏫</button>
                <button type="button" data-emoji="🛒">🛒</button><button type="button" data-emoji="⚕️">⚕️</button><button type="button" data-emoji="🔧">🔧</button><button type="button" data-emoji="⚙️">⚙️</button><button type="button" data-emoji="🛠️">🛠️</button><button type="button" data-emoji="🚛">🚛</button><button type="button" data-emoji="🚌">🚌</button>
                <button type="button" data-emoji="🚕">🚕</button><button type="button" data-emoji="✈️">✈️</button><button type="button" data-emoji="⚓">⚓</button><button type="button" data-emoji="🚢">🚢</button><button type="button" data-emoji="🚂">🚂</button><button type="button" data-emoji="🏗️">🏗️</button><button type="button" data-emoji="🏠">🏠</button>
                <button type="button" data-emoji="⛪">⛪</button><button type="button" data-emoji="🎓">🎓</button><button type="button" data-emoji="📚">📚</button><button type="button" data-emoji="📋">📋</button><button type="button" data-emoji="🛡️">🛡️</button><button type="button" data-emoji="🔒">🔒</button><button type="button" data-emoji="🔑">🔑</button>
                <button type="button" data-emoji="🪪">🪪</button><button type="button" data-emoji="📞">📞</button><button type="button" data-emoji="🖥️">🖥️</button><button type="button" data-emoji="🚧">🚧</button><button type="button" data-emoji="🧰">🧰</button><button type="button" data-emoji="🧲">🧲</button><button type="button" data-emoji="🔋">🔋</button>
                <button type="button" data-emoji="🍕">🍕</button><button type="button" data-emoji="🍔">🍔</button><button type="button" data-emoji="☕">☕</button><button type="button" data-emoji="🥤">🥤</button><button type="button" data-emoji="🧃">🧃</button><button type="button" data-emoji="🏟️">🏟️</button><button type="button" data-emoji="🎪">🎪</button>
                <button type="button" data-emoji="🎯">🎯</button><button type="button" data-emoji="🎳">🎳</button><button type="button" data-emoji="🎮">🎮</button><button type="button" data-emoji="🌲">🌲</button><button type="button" data-emoji="🌳">🌳</button><button type="button" data-emoji="🏔️">🏔️</button><button type="button" data-emoji="🏝️">🏝️</button>
                <button type="button" data-emoji="🏖️">🏖️</button><button type="button" data-emoji="🚁">🚁</button><button type="button" data-emoji="🛸">🛸</button><button type="button" data-emoji="🚤">🚤</button><button type="button" data-emoji="🚑">🚑</button><button type="button" data-emoji="🚒">🚒</button><button type="button" data-emoji="⚖️">⚖️</button>
                <button type="button" data-emoji="🏛️">🏛️</button><button type="button" data-emoji="📊">📊</button><button type="button" data-emoji="📜">📜</button><button type="button" data-emoji="🛋️">🛋️</button><button type="button" data-emoji="🛏️">🛏️</button><button type="button" data-emoji="🚿">🚿</button><button type="button" data-emoji="🧹">🧹</button>
                <button type="button" data-emoji="🩺">🩺</button><button type="button" data-emoji="💊">💊</button><button type="button" data-emoji="🔬">🔬</button><button type="button" data-emoji="🧪">🧪</button><button type="button" data-emoji="📡">📡</button><button type="button" data-emoji="📷">📷</button><button type="button" data-emoji="🎨">🎨</button>
                <button type="button" data-emoji="🖼️">🖼️</button><button type="button" data-emoji="🎵">🎵</button><button type="button" data-emoji="🎭">🎭</button><button type="button" data-emoji="📝">📝</button><button type="button" data-emoji="⚽">⚽</button><button type="button" data-emoji="🏀">🏀</button><button type="button" data-emoji="🎾">🎾</button>
                <button type="button" data-emoji="🏐">🏐</button><button type="button" data-emoji="🚴">🚴</button><button type="button" data-emoji="🏧">🏧</button><button type="button" data-emoji="💳">💳</button><button type="button" data-emoji="💰">💰</button><button type="button" data-emoji="🧯">🧯</button><button type="button" data-emoji="🗑️">🗑️</button>
            </div>
        </div>
        <div style="display:flex; gap:10px;">
            <button type="button" onclick="fecharModalNovoTipoPoi()" style="flex:1; padding:10px; border:1px solid #ccc; border-radius:6px; background:#f5f5f5; cursor:pointer; font-size:14px;">Cancelar</button>
            <button type="button" onclick="salvarNovoTipoPoi()" style="flex:1; padding:10px; border:none; border-radius:6px; background:#004173; color:white; cursor:pointer; font-size:14px;">Salvar Tipo</button>
        </div>
        <div id="novoTipoStatus" style="margin-top:12px; font-size:13px;"></div>
    </div>
</div>

<script>
var _emojiSelecionado = '📦';
function abrirModalNovoTipoPoi(){
    document.getElementById('novoTipoNome').value = '';
    _emojiSelecionado = '📦';
    document.querySelectorAll('#gradeEmojis button').forEach(function(b){ b.classList.remove('selecionado'); });
    var def = document.querySelector('#gradeEmojis button[data-emoji=\"📦\"]');
    if(def) def.classList.add('selecionado');
    document.getElementById('emojiSelecionado').textContent = '📦';
    document.getElementById('novoTipoStatus').innerHTML = '';
    document.getElementById('modalNovoTipoPoi').style.display = 'flex';
    setTimeout(function(){ document.getElementById('novoTipoNome').focus(); }, 100);
}
function fecharModalNovoTipoPoi(){
    document.getElementById('modalNovoTipoPoi').style.display = 'none';
}
document.addEventListener('DOMContentLoaded', function(){
    document.getElementById('gradeEmojis').addEventListener('click', function(e){
        var btn = e.target.closest('button');
        if(!btn) return;
        document.querySelectorAll('#gradeEmojis button').forEach(function(b){ b.classList.remove('selecionado'); });
        btn.classList.add('selecionado');
        _emojiSelecionado = btn.getAttribute('data-emoji') || '📌';
        document.getElementById('emojiSelecionado').textContent = _emojiSelecionado;
    });
    var sel = document.getElementById('poi_icone');
    if(sel){
        sel.addEventListener('change', function(){
            if(this.value === '__novo__'){
                this.value = '';
                abrirModalNovoTipoPoi();
            }
        });
    }
});
function salvarNovoTipoPoi(){
    var nome = document.getElementById('novoTipoNome').value.trim();
    var emoji = _emojiSelecionado;
    var statusEl = document.getElementById('novoTipoStatus');
    if(!nome){
        statusEl.innerHTML = '<span style="color:red;">Informe o nome do tipo.</span>';
        document.getElementById('novoTipoNome').focus();
        return;
    }
    var codigo = nome;
    statusEl.innerHTML = '<span style="color:#666;">Salvando...</span>';
    var formData = new FormData();
    formData.append('ajax_action', 'criar_tipo_poi');
    formData.append('codigo', codigo);
    formData.append('nome', nome);
    formData.append('emoji', emoji);
    fetch(window.basePath + '/ajax_poi_tipo.php', { method: 'POST', body: formData })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if(data.sucesso){
                statusEl.innerHTML = '<span style="color:green;">Tipo criado com sucesso!</span>';
                var sel = document.getElementById('poi_icone');
                var opt = document.createElement('option');
                opt.value = data.tipo.poti_tx_codigo;
                opt.textContent = data.tipo.poti_tx_emoji + ' ' + data.tipo.poti_tx_nome;
                opt.setAttribute('data-emoji', data.tipo.poti_tx_emoji);
                var novoItem = sel.querySelector('option[value="__novo__"]');
                sel.insertBefore(opt, novoItem);
                sel.value = data.tipo.poti_tx_codigo;
                setTimeout(fecharModalNovoTipoPoi, 800);
            }else{
                statusEl.innerHTML = '<span style="color:red;">' + (data.erro || 'Erro ao salvar.') + '</span>';
            }
        })
        .catch(function(err){
            statusEl.innerHTML = '<span style="color:red;">Erro na requisição.</span>';
            console.error('AJAX_ERRO', err);
        });
}
</script>

<div class="container">
    <div id="form_header" class="form_title">
        <img src="imagens/LGC.png" alt="Logo" class="logo">
        <h2 class="title-section">Painel de Não Conformidades Logísticas.</h2>
        <button type="button" class="btn btn-primary" id="toggleFormBtn">✒️</button>
    </div>

    <div class="table-container">
        <form id="filterForm" method="post">
            <div class="form-group">
                <label class="label-form" for="id">Motorista:</label>
                <select class="form-control field-form" id="id" name="id" disabled>
                    <?=$htmls["motoristas"]?>
                </select>
            </div>

            <div class="form-group plate-group">
                <label for="plate">Placa:</label>
                <select id="plate" name="plate" class="form-control field-form">
                    <?php if (!empty($plates)) { ?>
                        <option value="" disabled selected>Selecione</option>
                        <?php foreach ($plates as $placa) { ?>
                            <option value="<?=htmlspecialchars($placa)?>"><?=htmlspecialchars($placa)?></option>
                        <?php } ?>
                    <?php } else { ?>
                        <option value="" disabled selected>Nenhuma placa cadastrada</option>
                    <?php } ?>
                </select>
                <ul id="plate-suggestions" class="list-group" style="display:none"></ul>
            </div>
            <script>
                $(function(){
                    if($.fn.select2){
                        $.fn.select2.defaults.set('theme','bootstrap');
                        $('#plate').select2({placeholder:'Selecione', allowClear:true, width:'250px'});
                    }
                });
            </script>

<div class="form-group">
    <label class="label-form" for="date_start">Data e Hora Início:</label>
    <input type="datetime-local" class="form-control field-form" id="date_start" name="date_start" step="1">
</div>

<div class="form-group">
    <label class="label-form" for="date_end">Data e Hora Fim:</label>
    <input type="datetime-local" class="form-control field-form" id="date_end" name="date_end" step="1">
</div>

            <div class="form-group text-end button-search">
                <div class="btn-group">
                    <button type="submit" id="consultarBtn" class="btn btn-dark button-consulta">Consultar</button>
                </div>
            </div>
        </form>
    </div>
</div>



    <div id="messageDiv"></div>




    <div class="container">
        <div class="accordion" id="accordionExample">
            <!-- Accordion -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                        Ver Ponto registrado pelo colaborador <i class="fa-solid fa-arrow-down"></i>
                    </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne"
                    data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Placa</th>
                                    <th>Legenda</th>
                                    <th>Local</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?=$htmls["pontos"]?>
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
            <!-- Accordion Motoristas -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingMotoristas">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseMotoristas" aria-expanded="false" aria-controls="collapseMotoristas">
                        Motoristas vinculados à placa no período <i class="fa-solid fa-arrow-down"></i>
                    </button>
                </h2>
                <div id="collapseMotoristas" class="accordion-collapse collapse" aria-labelledby="headingMotoristas"
                    data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        <table class="table table-striped table-bordered" id="tabelaMotoristasPeriodo">
                            <thead>
                                <tr>
                                    <th>Placa</th>
                                    <th>Motorista</th>
                                    <th>Primeiro registro</th>
                                    <th>Último registro</th>
                                    <th>Tempo no veículo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="5" style="text-align:center">Consulte para exibir os motoristas</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container" id="results">
        <h2 class="title-section">Histórico de paradas</h2>

    </div>



 





    <div class="form-container" id="formContainer">
        <h3 id="formContainer">Inserir Ajuste de Ponto</h3>



        <!-- Formulário de Ajuste de Ponto -->
        <form id="adjustmentForm">
            <div class="form-group">
                <label for="motorista">Motorista:</label>
                <select class="form-control field-form  " id="motorista" name="motorista" disabled>
                    <?=$htmls["motoristas"]?>
                </select>
            </div>

            <div class="form-group">
                <label for="data">Data:</label>
                <input type="date" class="form-control field-form" id="data" name="data"
                    value="<?=date("Y-m-d")?>" disabled>
            </div>
            <div class="form-group">
                <label for="hora">Hora Inicio:</label>
                <input type="time" class="form-control field-form" id="hora" name="hora" required>
            </div>
            <div class="form-group">
                <label for="horaFim">Hora Fim:</label>
                <input type="time" class="form-control field-form" id="horaFim" name="horaFim" required>
            </div>
            <div class="form-group">
                <label for="latitude">Latitude:</label>
                <input type="text" class="form-control field-form" id="latitude" name="latitude" placeholder="Latitude"
                    disabled>
            </div>
            <div class="form-group">
                <label for="longitude">Longitude:</label>
                <input type="text" class="form-control field-form" id="longitude" name="longitude"
                    placeholder="Longitude" disabled>
            </div>

            <div class="form-group">
                <label for="idMacro">Tipo de Registro:</label>
                <select class="form-control field-form" id="idMacro" name="idMacro" required>
                    <option value="" disabled selected>Selecionar</option>
                    <?=$htmls["tipos"]?>
                </select>
            </div>
            <div class="form-group">
                <label for="motivo">Motivo:</label>
                <select class="form-control field-form" id="motivo" name="motivo" required>
                    <option value="" disabled selected>Selecionar</option>
                    <?=$htmls["motivos"]?>
                </select>
            </div>
            <div class="form-group">
                <label for="descricao">Justificativa:</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label for="coment">Comentário:</label>
                <textarea class="form-control" id="coment" name="coment" rows="3"></textarea>
            </div>

            <button type="button" class="btn btn-primary" id="addAdjustmentBtn">Adicionar à Lista</button>
        </form>
        <!-- Resumo -->
        <div class="resumo" id="resumoContainer">

        </div>
        
        <!-- Lista de Ajustes -->
        <div class="adjustment-list">
            <h3>Lista de Ajustes</h3>
            <table id="adjustmentTable">
                <thead>
                    <tr>
                        <th>Motorista</th>

                        <th>Data</th>
                        <th>Hora</th>
                        <th>Lat</th>
                        <th>Long</th>
                        <th>Tipo de Registro</th>
                        <th>Motivo</th>
                        <th>Justificativa</th>
                        <th>Placa</th>
                        <th>Excluir</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
            <button type="button" class="btn btn-success" id="submitAdjustmentsBtn">Salvar Ajustes</button>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="js/logistica.js"></script>
    <script src="js/logistica_modal.js"></script>

</body>

</html>

</style>
</style>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('plate');
    const suggestionsList = document.getElementById('plate-suggestions');

    // Array de placas vindo do PHP
    const plates = <?=json_encode($plates)?>;
    // Array de POIs vindo do PHP (global para acesso no mapa)
    window.pois = <?=json_encode($pois)?>;
    window.poiTipos = <?=json_encode($tiposPoi, JSON_UNESCAPED_UNICODE)?>;
    window.basePath = '<?=$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]?>';

    // Escuta o evento de input no campo de busca
    searchInput.addEventListener('input', function() {
        const filter = searchInput.value.toUpperCase(); // Converte o texto digitado em maiúsculas
        suggestionsList.innerHTML = ''; // Limpa as sugestões anteriores

        if (filter === '') return; // Se o campo de busca estiver vazio, não mostra nada

        // Filtra as placas com base no que foi digitado
        const filteredPlates = plates.filter(plate => plate.toUpperCase().includes(filter));

        // Exibe as sugestões filtradas
        filteredPlates.forEach(plate => {
            const li = document.createElement('li');
            li.textContent = plate;
            li.classList.add('list-group-item');
            suggestionsList.appendChild(li);

            // Quando uma sugestão for clicada, preenche o campo de texto e limpa as sugestões
            li.addEventListener('click', function() {
                searchInput.value = plate;
                suggestionsList.innerHTML = ''; // Limpa a lista de sugestões
            });
        });
    });
});


// Função para ocultar as mensagens após 5 segundos
function hideMessageAfterDelay(messageId) {
    var messageElement = document.getElementById(messageId);
    if (messageElement) {
        setTimeout(function() {
            messageElement.style.display = 'none';
        }, 5000); // 5000 milissegundos = 5 segundos
    }
}
// Chama a função para esconder mensagens de erro e sucesso
hideMessageAfterDelay('popupErro');
hideMessageAfterDelay('popupSucesso');


document.addEventListener('DOMContentLoaded', function() {
    // Exemplo de dados, substitua pelos dados reais
    const kmPercorrida = 120; // km
    const velocidadeMaxima = 80; // km/h
    const velocidadeMedia = 60; // km/h
    const tempoPercorrido = 2; // horas

    // Atualiza os valores dos cards
    document.getElementById('kmPercorrida').innerText = `${kmPercorrida} km`;
    document.getElementById('velocidadeMaxima').innerText = `${velocidadeMaxima} km/h`;
    document.getElementById('velocidadeMedia').innerText = `${velocidadeMedia} km/h`;
    document.getElementById('tempoPercorrido').innerText = `${tempoPercorrido} h`;
});


</script>
</script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>


<style>

#plate {
        width: 250px;
    }
        #adjustmentTable {
            display: none;
        }

        .field-form {
            border: 1px solid #35A3BC;
            border-radius: 10px;
            padding: 1rem;
            width: 250px;
            height: 40px;
        }
        .select2-container .select2-selection--single{
            border: 1px solid #35A3BC;
            border-radius: 10px;
            height: 40px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered{
            height: 40px;
            line-height: 40px;
            padding-left: 8px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow{
            height: 40px;
        }
        .select2-container{
            width: 250px !important;
        }
        .plate-group .select2-container .select2-selection--single{
            margin-top:0px;
            border: 1px solid #35A3BC;
        }

        #plate-suggestions {
            border: none;
        }

        .form-control[disabled] {
            background-color: white;
        }

        .label-form {
            padding: 10px;
        }

        .row div label {
            margin: 0;
            padding: 10px;
            text-transform: uppercase;
        }

        #consultarBtn {
            margin-top: 2.6rem;
            background: #35A3BC;
            border-radius: 10px;
            width: 100px;
            text-alight: center;
        }

        #toggleFormBtn {
            position: fixed;
            bottom: 9rem;
            /* Ajuste a posição conforme necessário */
            left: 0.5rem;
            /* Ajuste a posição conforme necessário */
            margin-top: 0;
            /* Remova o margin-top, pois a posição é fixa */
            background: #192942;
            border-radius: 5px;
            width: 60px;
            z-index: 1000;
            /* Garante que fique acima de outros elementos */
        }

        #toggleFormBtn:hover {
            background: #35A3BC;
            border-radius: 10px;
            width: 60px;
            transition: 0.5s ease;
            /* Ajustado para uma transição mais rápida e suave */
        }

        #consultarBtn:hover {
            margin-top: 2.6rem;
            background: #35A3BC;
            border-radius: 10px;
            width: 100px;
            text-alight: center;



        }

        .accordion-button {
            border: none;
            background: #192942;
            color: white;
            font-size: 18px;
            font-weight: 500;
            text-transform: uppercase;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            flex-wrap: nowrap;
            flex-direction: row;
            width: 100%;
            padding: 12px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .accordion-button:hover {
            background: #35A3BC;
        }

        .accordion-button.collapsed {
            background: #192942;
        }

        .accordion-button .fa-arrow-down {
            color: white;
            padding: 6px;
            background-color: #35A3BC;
            border-radius: 50%;
            font-size: 14px;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s ease;
        }

        .accordion-button:not(.collapsed) .fa-arrow-down {
            transform: rotate(180deg);
        }

        .accordion-item {
            border: none;
            margin-bottom: 10px;
            border-radius: 8px;
            overflow: hidden;
        }

        .accordion-header {
            margin-bottom: 0 !important;
        }

        .title-section {
            font-size: 24px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .title-section button {
            font-size: 18px;
            font-weight: 500;
            text-transform: uppercase;
        }

        /* Botão de adicionar POI no mapa */
        #addPoiBtnMap:hover {
            background: #f0f0f0 !important;
        }
