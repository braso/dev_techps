<?php


/*  Cadastro de Biometria Facial */

// ── Ações AJAX via GET (padrão do sistema) ────────────────────────────────────
$acaoAtual = trim($_GET["acao"] ?? $_POST["acao"] ?? "");

function facialJsonResponse($data){
    // Evita que warnings/echo acidentais quebrem o JSON do AJAX
    if(ob_get_level() > 0){
        @ob_clean();
    }
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function listarFuncionarios(){
    ob_start();
    $interno = true;
    include "conecta.php";

    $empresaId = intval($_GET["empresa_id"] ?? $_POST["empresa_id"] ?? $_POST["busca_empresa"] ?? 0);
    if ($empresaId <= 0) { facialJsonResponse([]); }

    // Garante coluna
    $chkCol = query("SHOW COLUMNS FROM user LIKE 'user_tx_face_descriptor'");
    if ($chkCol && mysqli_num_rows($chkCol) == 0) {
        query("ALTER TABLE user ADD COLUMN user_tx_face_descriptor LONGTEXT DEFAULT NULL");
    }

    $rs = query(
        "SELECT e.enti_nb_id, e.enti_tx_nome, e.enti_tx_matricula, e.enti_tx_foto,
                e.enti_tx_ocupacao,
                u.user_nb_id, u.user_tx_login, u.user_tx_foto AS user_foto,
                IF(u.user_tx_face_descriptor IS NOT NULL AND u.user_tx_face_descriptor != '', 1, 0) AS tem_biometria
         FROM entidade e
         LEFT JOIN user u ON u.user_nb_entidade = e.enti_nb_id AND u.user_tx_status = 'ativo'
         WHERE e.enti_tx_status = 'ativo'
           AND e.enti_nb_empresa = {$empresaId}
           AND e.enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')
         ORDER BY e.enti_tx_nome ASC"
    );
    $lista = [];
    if ($rs) {
        while ($r = mysqli_fetch_assoc($rs)) {
            $lista[] = [
                "enti_nb_id"    => $r["enti_nb_id"],
                "user_nb_id"    => $r["user_nb_id"],
                "user_tx_nome"  => !empty($r["enti_tx_nome"]) ? $r["enti_tx_nome"] : ($r["user_tx_login"] ?? "Sem nome"),
                "user_tx_login" => !empty($r["user_tx_login"]) ? $r["user_tx_login"] : "(sem usuário)",
                "ocupacao"      => $r["enti_tx_ocupacao"],
                "user_tx_foto"  => !empty($r["user_foto"]) ? $r["user_foto"] : $r["enti_tx_foto"],
                "matricula"     => $r["enti_tx_matricula"],
                "tem_biometria" => (int)$r["tem_biometria"],
            ];
        }
    }
    facialJsonResponse($lista);
}

function salvarDescritor(){
    ob_start();
    $interno = true;
    include "conecta.php";

    $userId    = intval($_POST["user_id"] ?? 0);
    $descritor = $_POST["descritor"] ?? "";
    if ($userId <= 0 || empty($descritor)) { facialJsonResponse(["ok"=>false,"msg"=>"Dados inválidos."]); }
    $arr = json_decode($descritor, true);
    if (!is_array($arr) || count($arr) < 10) { facialJsonResponse(["ok"=>false,"msg"=>"Descritor inválido."]); }
    $safe = mysqli_real_escape_string($conn, $descritor);
    query("UPDATE user SET user_tx_face_descriptor = '{$safe}' WHERE user_nb_id = {$userId}");
    facialJsonResponse(["ok"=>true,"msg"=>"Biometria salva com sucesso!"]);
}

function removerBiometria(){
    ob_start();
    $interno = true;
    include "conecta.php";

    $userId = intval($_POST["user_id"] ?? 0);
    if ($userId <= 0) { facialJsonResponse(["ok"=>false,"msg"=>"ID inválido."]); }
    query("UPDATE user SET user_tx_face_descriptor = NULL WHERE user_nb_id = {$userId}");
    facialJsonResponse(["ok"=>true]);
}

if ($acaoAtual === "listarFuncionarios" || $acaoAtual === "listarFuncionarios()") {
    listarFuncionarios();
}
if ($acaoAtual === "salvarDescritor" || $acaoAtual === "salvarDescritor()") {
    salvarDescritor();
}
if ($acaoAtual === "removerBiometria" || $acaoAtual === "removerBiometria()") {
    removerBiometria();
}

// ── Página normal ─────────────────────────────────────────────────────────────
include_once "utils/utils.php";
include_once "check_permission.php";
include "conecta.php";

$chkCol = query("SHOW COLUMNS FROM user LIKE 'user_tx_face_descriptor'");
if ($chkCol && mysqli_num_rows($chkCol) == 0) {
    query("ALTER TABLE user ADD COLUMN user_tx_face_descriptor LONGTEXT DEFAULT NULL");
}

// ── HTML ──────────────────────────────────────────────────────────────────────
cabecalho("Cadastro de Biometria Facial");
?>
<style>
.facial-wrap{display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap}
/* sidebar */
.facial-sidebar{flex:0 0 290px;min-width:250px}
.fs-card{background:#fff;border:1px solid #e2e2e2;border-radius:8px;padding:16px;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.fs-card-title{font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:#999;margin-bottom:8px;display:flex;align-items:center;gap:6px}
/* lista */
.user-list-wrap{background:#fff;border:1px solid #e2e2e2;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.user-list-search{padding:10px 12px;border-bottom:1px solid #f0f0f0}
.user-list-search input{width:100%;border:1px solid #ddd;border-radius:20px;padding:5px 12px;font-size:12px;outline:none;transition:border .2s}
.user-list-search input:focus{border-color:#3c8dbc}
.user-list-body{max-height:430px;overflow-y:auto}
.user-list-body::-webkit-scrollbar{width:4px}
.user-list-body::-webkit-scrollbar-thumb{background:#ddd;border-radius:4px}
.user-card{display:flex;align-items:center;gap:10px;padding:9px 14px;cursor:pointer;border-bottom:1px solid #f5f5f5;transition:background .15s}
.user-card:last-child{border-bottom:none}
.user-card:hover{background:#f4f8ff}
.user-card.selected{background:#e8f1fb;border-left:3px solid #3c8dbc}
.user-card .uc-av{width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #ddd;flex-shrink:0}
.user-card.selected .uc-av{border-color:#3c8dbc}
.uc-nome{font-size:13px;font-weight:600;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.uc-sub{font-size:11px;color:#aaa}
.badge-bio{font-size:10px;padding:2px 7px;border-radius:10px;background:#27ae60;color:#fff;white-space:nowrap;flex-shrink:0}
.badge-nobio{font-size:10px;padding:2px 7px;border-radius:10px;background:#bbb;color:#fff;white-space:nowrap;flex-shrink:0}
.list-empty{padding:28px;text-align:center;color:#bbb;font-size:13px}
/* main */
.facial-main{flex:1;min-width:300px}
/* card funcionário selecionado */
.suc-card{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid #e2e2e2;border-radius:8px;padding:14px 18px;margin-bottom:16px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.suc-card img{width:54px;height:54px;border-radius:50%;object-fit:cover;border:3px solid #3c8dbc;flex-shrink:0}
.suc-nome{font-size:15px;font-weight:700;color:#333}
.suc-sub{font-size:12px;color:#999;margin-top:2px}
/* câmera */
.cam-card{background:#fff;border:1px solid #e2e2e2;border-radius:8px;padding:18px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.cam-card-title{font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:#999;margin-bottom:14px;display:flex;align-items:center;gap:6px}
#video-box{position:relative;border-radius:8px;overflow:hidden;background:#111;display:block;max-width:480px}
#videoEl{display:block;width:100%;border-radius:8px}
#overlay-canvas{position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none}
/* status */
.face-bar{display:flex;align-items:center;gap:8px;margin-top:10px;padding:8px 12px;border-radius:6px;background:#f8f8f8;border:1px solid #eee;font-size:13px;min-height:38px;transition:all .2s}
.face-bar.ok{background:#eafaf1;border-color:#a9dfbf;color:#1e8449}
.face-bar.err{background:#fdf2f2;border-color:#f5b7b1;color:#c0392b}
.face-bar .dot{width:10px;height:10px;border-radius:50%;background:#ccc;flex-shrink:0;transition:background .2s}
.face-bar.ok .dot{background:#27ae60;box-shadow:0 0 0 3px rgba(39,174,96,.2)}
.face-bar.err .dot{background:#e74c3c}
/* progresso */
.prog-wrap{margin-top:12px}
.prog-label{font-size:11px;color:#888;margin-bottom:4px}
.prog-track{height:6px;border-radius:3px;background:#eee;overflow:hidden}
.prog-fill{height:100%;background:linear-gradient(90deg,#3c8dbc,#27ae60);border-radius:3px;transition:width .3s}
/* botões */
.cam-actions{display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;align-items:center}
/* miniaturas */
.thumb-strip{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.thumb-strip img{width:68px;height:68px;border-radius:6px;object-fit:cover;border:2px solid #27ae60;box-shadow:0 1px 4px rgba(0,0,0,.12);transition:transform .15s}
.thumb-strip img:hover{transform:scale(1.1)}
/* loading */
#loading-models{text-align:center;padding:40px 20px;color:#aaa}
#loading-models .spin-i{font-size:32px;color:#3c8dbc;margin-bottom:10px}
/* placeholder */
.cam-ph{text-align:center;padding:60px 20px;color:#ccc}
.cam-ph i{font-size:52px;margin-bottom:14px;display:block}
.cam-ph p{font-size:13px;margin:0;line-height:1.6}
</style>

<div class="portlet light">
    <div class="portlet-title">
        <div class="caption">
            <i class="fa fa-eye font-blue"></i>
            <span class="caption-subject font-blue bold uppercase">Cadastro de Biometria Facial</span>
        </div>
        <div class="tools" style="line-height:32px">
            <small class="text-muted" style="font-size:11px;font-weight:normal">
                Selecione a empresa &rarr; funcionário &rarr; capture o rosto
            </small>
        </div>
    </div>
    <div class="portlet-body">
        <div class="facial-wrap">

            <!-- ══ SIDEBAR ══ -->
            <div class="facial-sidebar">

                <div class="fs-card">
                    <div class="fs-card-title"><i class="fa fa-building-o"></i> Empresa</div>
                    <select id="sel-empresa" class="form-control" style="border-radius:6px;font-size:13px">
                        <option value="">— Selecione —</option>
                        <?php
                        $rsEmp = query("SELECT empr_nb_id, empr_tx_nome FROM empresa WHERE empr_tx_status = 'ativo' ORDER BY empr_tx_nome");
                        while ($e = mysqli_fetch_assoc($rsEmp)) {
                            $sel = ($_SESSION["user_nb_empresa"] == $e["empr_nb_id"]) ? "selected" : "";
                            echo "<option value='{$e["empr_nb_id"]}' {$sel}>" . htmlspecialchars($e["empr_tx_nome"]) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="user-list-wrap" id="user-list-wrap" style="display:none">
                    <div class="user-list-search">
                        <input type="text" id="busca-user" placeholder="Buscar funcionário...">
                    </div>
                    <div class="user-list-body" id="lista-usuarios">
                        <div class="list-empty"><i class="fa fa-spinner fa-spin"></i><br>Carregando...</div>
                    </div>
                </div>

            </div>

            <!-- ══ MAIN ══ -->
            <div class="facial-main">

                <div id="cam-ph" class="cam-ph">
                    <i class="fa fa-user-circle-o"></i>
                    <p>Selecione uma empresa e um funcionário<br>para iniciar o cadastro de biometria facial.</p>
                </div>

                <div id="painel-camera" style="display:none">

                    <!-- Funcionário selecionado -->
                    <div class="suc-card" id="suc-card"></div>

                    <!-- Câmera -->
                    <div class="cam-card">
                        <div class="cam-card-title">
                            <i class="fa fa-video-camera"></i> Captura de Rosto
                            <span style="margin-left:auto;text-transform:none;letter-spacing:0;font-size:11px;color:#bbb">
                                Mínimo 3 capturas &mdash; ideal 5 ou mais
                            </span>
                        </div>

                        <div id="loading-models">
                            <div class="spin-i"><i class="fa fa-circle-o-notch fa-spin"></i></div>
                            <p>Carregando modelos de IA...<br><small>Aguarde, isso ocorre apenas uma vez por sessão.</small></p>
                        </div>

                        <div id="camera-area" style="display:none">
                            <div id="video-box">
                                <video id="videoEl" autoplay muted playsinline></video>
                                <canvas id="overlay-canvas"></canvas>
                            </div>

                            <div class="face-bar" id="face-bar">
                                <span class="dot"></span>
                                <span id="face-txt">Posicione o rosto na câmera...</span>
                            </div>

                            <div class="prog-wrap">
                                <div class="prog-label">Capturas: <b id="count-cap">0</b> / 5</div>
                                <div class="prog-track"><div class="prog-fill" id="prog-fill" style="width:0%"></div></div>
                            </div>

                            <div class="cam-actions">
                                <button id="btn-cap" class="btn btn-primary" disabled>
                                    <i class="fa fa-camera"></i> Capturar
                                </button>
                                <button id="btn-salvar" class="btn btn-success" disabled style="display:none">
                                    <i class="fa fa-check"></i> Salvar Biometria
                                </button>
                                <button id="btn-limpar" class="btn btn-default">
                                    <i class="fa fa-refresh"></i> Recomeçar
                                </button>
                            </div>

                            <div class="thumb-strip" id="thumb-strip"></div>
                            <div id="msg-res" style="margin-top:14px"></div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
(function(){
    const MODEL_URL = '<?= $_ENV["URL_BASE"].$_ENV["APP_PATH"] ?>/face_models';
    const APP_PATH  = '<?= $_ENV["APP_PATH"].$_ENV["CONTEX_PATH"] ?>';

    let modelsLoaded=false, stream=null, amostras=[], usuarioAtual=null, detLoop=null, rostoOk=false;

    const selEmpresa   = document.getElementById('sel-empresa');
    const userListWrap = document.getElementById('user-list-wrap');
    const listaEl      = document.getElementById('lista-usuarios');
    const buscaEl      = document.getElementById('busca-user');
    const camPh        = document.getElementById('cam-ph');
    const painelCam    = document.getElementById('painel-camera');
    const loadingEl    = document.getElementById('loading-models');
    const cameraArea   = document.getElementById('camera-area');
    const videoEl      = document.getElementById('videoEl');
    const canvas       = document.getElementById('overlay-canvas');
    const faceBar      = document.getElementById('face-bar');
    const faceTxt      = document.getElementById('face-txt');
    const btnCap       = document.getElementById('btn-cap');
    const btnSalvar    = document.getElementById('btn-salvar');
    const btnLimpar    = document.getElementById('btn-limpar');
    const thumbStrip   = document.getElementById('thumb-strip');
    const countCap     = document.getElementById('count-cap');
    const progFill     = document.getElementById('prog-fill');
    const msgRes       = document.getElementById('msg-res');
    const sucCard      = document.getElementById('suc-card');

    function syncCanvas(){ canvas.width=videoEl.videoWidth||videoEl.offsetWidth; canvas.height=videoEl.videoHeight||videoEl.offsetHeight; }
    function setStatus(txt,cls){ faceTxt.textContent=txt; faceBar.className='face-bar'+(cls?' '+cls:''); }

    // ── Modelos ───────────────────────────────────────────────────────────────
    async function carregarModelos(){
        try{
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
            ]);
            modelsLoaded=true;
            loadingEl.style.display='none';
            cameraArea.style.display='block';
            iniciarCamera();
        }catch(e){
            loadingEl.innerHTML='<div style="color:#e74c3c"><i class="fa fa-exclamation-triangle"></i> Erro ao carregar modelos. Verifique a pasta <code>face_models</code>.</div>';
        }
    }

    // ── Câmera ────────────────────────────────────────────────────────────────
    async function iniciarCamera(){
        if(stream) stream.getTracks().forEach(t=>t.stop());
        try{
            stream=await navigator.mediaDevices.getUserMedia({video:{width:{ideal:480},height:{ideal:360},facingMode:'user'}});
            videoEl.srcObject=stream;
            videoEl.onloadedmetadata=()=>{ syncCanvas(); iniciarLoop(); };
        }catch(e){ setStatus('Câmera indisponível: '+e.message,'err'); }
    }
    function pararCamera(){ if(detLoop) clearInterval(detLoop); if(stream){stream.getTracks().forEach(t=>t.stop());stream=null;} }

    // ── Loop detecção ─────────────────────────────────────────────────────────
    function iniciarLoop(){
        if(detLoop) clearInterval(detLoop);
        const ctx=canvas.getContext('2d');
        detLoop=setInterval(async()=>{
            if(!modelsLoaded||videoEl.readyState<2) return;
            syncCanvas();
            const det=await faceapi.detectSingleFace(videoEl,new faceapi.TinyFaceDetectorOptions({inputSize:320,scoreThreshold:0.5})).withFaceLandmarks();
            ctx.clearRect(0,0,canvas.width,canvas.height);
            if(det){
                rostoOk=true;
                const b=det.detection.box;
                ctx.strokeStyle='#27ae60'; ctx.lineWidth=3;
                ctx.shadowColor='rgba(39,174,96,.5)'; ctx.shadowBlur=10;
                ctx.strokeRect(b.x,b.y,b.width,b.height);
                ctx.shadowBlur=0;
                setStatus('✔ Rosto detectado — clique em Capturar','ok');
                btnCap.disabled=false;
            }else{
                rostoOk=false;
                setStatus('Posicione o rosto na câmera...','');
                btnCap.disabled=true;
            }
        },300);
    }

    // ── Capturar ──────────────────────────────────────────────────────────────
    btnCap.addEventListener('click',async()=>{
        if(!rostoOk) return;
        btnCap.disabled=true;
        setStatus('Processando...','');
        const det=await faceapi.detectSingleFace(videoEl,new faceapi.TinyFaceDetectorOptions({inputSize:320})).withFaceLandmarks().withFaceDescriptor();
        if(!det){ setStatus('Rosto não encontrado. Tente novamente.','err'); btnCap.disabled=false; return; }
        amostras.push(det.descriptor);
        // miniatura
        const tmp=document.createElement('canvas'); const b=det.detection.box;
        tmp.width=68; tmp.height=68;
        tmp.getContext('2d').drawImage(videoEl,b.x,b.y,b.width,b.height,0,0,68,68);
        const img=document.createElement('img'); img.src=tmp.toDataURL(); img.title='Captura '+amostras.length;
        thumbStrip.appendChild(img);
        // progresso
        countCap.textContent=amostras.length;
        progFill.style.width=Math.min(amostras.length/5*100,100)+'%';
        setStatus('✔ Captura '+amostras.length+' registrada!','ok');
        btnCap.disabled=false;
        if(amostras.length>=3){ btnSalvar.style.display='inline-block'; btnSalvar.disabled=false; }
    });

    // ── Salvar ────────────────────────────────────────────────────────────────
    btnSalvar.addEventListener('click',async()=>{
        if(!usuarioAtual||amostras.length<3) return;
        if(!usuarioAtual.user_nb_id){ msgRes.innerHTML="<div class='alert alert-warning'><i class='fa fa-warning'></i> Este funcionário não possui usuário cadastrado. Cadastre um usuário para ele primeiro.</div>"; return; }
        btnSalvar.disabled=true;
        btnSalvar.innerHTML='<i class="fa fa-spinner fa-spin"></i> Salvando...';
        const len=amostras[0].length, media=new Float32Array(len);
        amostras.forEach(d=>{ for(let i=0;i<len;i++) media[i]+=d[i]; });
        for(let i=0;i<len;i++) media[i]/=amostras.length;

        var url = 'cadastro_facial.php?acao=salvarDescritor';
        $.ajax({
            url: url,
            method: 'POST',
            data: { user_id: usuarioAtual.user_nb_id, descritor: JSON.stringify(Array.from(media)) },
            dataType: 'json'
        }).done(function(json){
            if(json.ok){
                msgRes.innerHTML="<div class='alert alert-success'><i class='fa fa-check-circle'></i> <b>Biometria salva!</b> O funcionário já pode usar reconhecimento facial no login.</div>";
                var card=document.querySelector('.user-card[data-id="'+usuarioAtual.enti_nb_id+'"]');
                if(card){ var bg=card.querySelector('.badge-nobio,.badge-bio'); if(bg){bg.className='badge-bio';bg.textContent='✔ Biometria';} }
                var bg2=sucCard.querySelector('.badge-nobio,.badge-bio');
                if(bg2){bg2.className='badge-bio';bg2.textContent='✔ Biometria cadastrada';}
                limpar(false);
            }else{
                msgRes.innerHTML="<div class='alert alert-danger'><i class='fa fa-times-circle'></i> "+json.msg+"</div>";
            }
        }).fail(function(){
            msgRes.innerHTML="<div class='alert alert-danger'>Erro de comunicação com o servidor.</div>";
        }).always(function(){
            btnSalvar.innerHTML='<i class="fa fa-check"></i> Salvar Biometria';
            btnSalvar.disabled=false;
        });
    });

    // ── Limpar ────────────────────────────────────────────────────────────────
    btnLimpar.addEventListener('click',()=>limpar(true));
    function limpar(limparMsg){
        amostras=[]; thumbStrip.innerHTML='';
        countCap.textContent='0'; progFill.style.width='0%';
        btnSalvar.style.display='none'; btnSalvar.disabled=true;
        if(limparMsg) msgRes.innerHTML='';
    }

    // ── Empresa ───────────────────────────────────────────────────────────────
    selEmpresa.addEventListener('change',function(){
        const id=this.value;
        pararCamera(); camPh.style.display='block'; painelCam.style.display='none';
        limpar(true); usuarioAtual=null;
        if(!id){ userListWrap.style.display='none'; return; }
        userListWrap.style.display='block';
        carregarUsuarios(id);
    });

    async function carregarUsuarios(empresaId){
        listaEl.innerHTML='<div class="list-empty"><i class="fa fa-spinner fa-spin"></i><br>Carregando...</div>';
        var url = 'cadastro_facial.php?acao=listarFuncionarios&empresa_id=' + encodeURIComponent(empresaId);
        $.ajax({ url: url, dataType: 'text' })
            .done(function(resp){
                let lista = null;
                try{
                    lista = JSON.parse(resp);
                }catch(e){
                    // Fallback: tenta extrair apenas o trecho JSON quando houver ruído (warning/HTML) no retorno
                    const iniArray = resp.indexOf('[');
                    const fimArray = resp.lastIndexOf(']');
                    const iniObj = resp.indexOf('{');
                    const fimObj = resp.lastIndexOf('}');
                    try{
                        if(iniArray >= 0 && fimArray > iniArray){
                            lista = JSON.parse(resp.slice(iniArray, fimArray + 1));
                        }else if(iniObj >= 0 && fimObj > iniObj){
                            const parsed = JSON.parse(resp.slice(iniObj, fimObj + 1));
                            lista = Array.isArray(parsed) ? parsed : [];
                        }
                    }catch(err2){
                        lista = null;
                    }
                }
                if(Array.isArray(lista)){ renderLista(lista); }
                else {
                    var preview = String(resp || '').replace(/</g,'&lt;').replace(/>/g,'&gt;').trim();
                    if(preview.length > 320){ preview = preview.substring(0,320) + '...'; }
                    listaEl.innerHTML='<div class="list-empty" style="color:#e74c3c">Resposta inválida do servidor.<br><small style="display:block;margin-top:8px;color:#999;word-break:break-word">Preview: '+(preview || '(vazio)')+'</small></div>';
                }
            })
            .fail(function(xhr){ listaEl.innerHTML='<div class="list-empty" style="color:#e74c3c">Erro ao carregar. ('+xhr.status+')</div>'; });
    }

    function renderLista(lista){
        listaEl.innerHTML='';
        if(!lista.length){ listaEl.innerHTML='<div class="list-empty"><i class="fa fa-user-times"></i><br>Nenhum funcionário encontrado.</div>'; return; }
        lista.forEach(u=>{
            const card=document.createElement('div');
            card.className='user-card'; card.dataset.id=u.enti_nb_id; card.dataset.nome=u.user_tx_nome;
            const foto=u.user_tx_foto?u.user_tx_foto:'../contex20/img/user.png';
            const semUser=!u.user_nb_id?"<span style='font-size:10px;color:#e67e22;margin-left:4px'><i class='fa fa-exclamation-triangle'></i> sem usuário</span>":'';
            const badge=u.tem_biometria?"<span class='badge-bio'>✔ Biometria</span>":"<span class='badge-nobio'>Sem biometria</span>";
            card.innerHTML=`<img class="uc-av" src="${foto}" onerror="this.src='../contex20/img/user.png'">
                <div style="flex:1;min-width:0">
                    <div class="uc-nome">${u.user_tx_nome}${semUser}</div>
                    <div class="uc-sub">${u.matricula ? '['+u.matricula+'] ' : ''}${u.user_tx_login}</div>
                </div>
                ${badge}`;
            card.addEventListener('click',()=>selecionarUsuario(u,card));
            listaEl.appendChild(card);
        });
    }

    buscaEl.addEventListener('input',function(){
        const q=this.value.toLowerCase();
        document.querySelectorAll('.user-card').forEach(c=>{ c.style.display=c.dataset.nome.toLowerCase().includes(q)?'':'none'; });
    });

    // ── Selecionar funcionário ────────────────────────────────────────────────
    function selecionarUsuario(u,card){
        document.querySelectorAll('.user-card').forEach(c=>c.classList.remove('selected'));
        card.classList.add('selected');
        usuarioAtual=u; limpar(true);
        const foto=u.user_tx_foto?u.user_tx_foto:'../contex20/img/user.png';
        const badge=u.tem_biometria?"<span class='badge-bio'>✔ Biometria cadastrada</span>":"<span class='badge-nobio'>Sem biometria</span>";
        const semUser=!u.user_nb_id?"<div style='margin-top:4px;font-size:11px;color:#e67e22'><i class='fa fa-exclamation-triangle'></i> Funcionário sem usuário — cadastre um usuário para habilitar a biometria.</div>":'';
        sucCard.innerHTML=`<img src="${foto}" onerror="this.src='../contex20/img/user.png'">
            <div>
                <div class="suc-nome">${u.user_tx_nome}</div>
                <div class="suc-sub">${u.matricula?'Matrícula: '+u.matricula+' &nbsp;|&nbsp; ':''}Login: ${u.user_tx_login}${u.user_tx_nivel?' &nbsp;|&nbsp; '+u.user_tx_nivel:''}</div>
                <div style="margin-top:6px">${badge}</div>
                ${semUser}
            </div>`;
        camPh.style.display='none'; painelCam.style.display='block';
        if(!modelsLoaded){ carregarModelos(); }
        else if(!stream){ iniciarCamera(); }
    }

    // Auto-dispara se empresa já selecionada
    if(selEmpresa.value){ userListWrap.style.display='block'; carregarUsuarios(selEmpresa.value); }
})();
</script>

<?php rodape(); ?>
