<?php
    // ============================================================
    // Suporte Widget — botão flutuante + modal de abertura de chamado
    // Incluído pelo rodape.php (contex20) — vale para todas as páginas
    // de todas as empresas. Os dados são enviados à API externa
    // (server.js) que grava no banco central de suporte.
    // ============================================================

    $__supKey = $_ENV["SUPORTE_API_KEY"] ?? "";
    $__supUid = $_SESSION["user_nb_id"] ?? "";
    $__supApiUrl = rtrim(strval($_ENV["SUPORTE_API_URL"] ?? ""), "/");
    if (empty($__supKey) || empty($__supUid) || empty($_ENV["URL_BASE"]) || $__supApiUrl === "") {
        return; // Sem chave/URL configurada ou sem sessão: widget não renderiza.
    }

    $__supEmpresa = trim(strval($_ENV["CONTEX_PATH"] ?? ""), "/");
    $__supEmpNome = trim(strval($_SESSION["empr_tx_nome"] ?? "")) !== "" ? trim(strval($_SESSION["empr_tx_nome"])) : $__supEmpresa;
    $__supNome    = trim(strval($_SESSION["user_tx_nome"] ?? ""));
    $__supLogin   = trim(strval($_SESSION["user_tx_login"] ?? ""));
    $__supEmail   = trim(strval($_SESSION["user_tx_email"] ?? ""));
    if ($__supEmpresa === "" || $__supNome === "" || $__supLogin === "") {
        return;
    }
    // Valida formato simples do e-mail (usado para notificações do chamado).
    if (!filter_var($__supEmail, FILTER_VALIDATE_EMAIL)) {
        $__supEmail = "";
    }

    // Responsável vinculado ao funcionário do usuário (cadastro_funcionario) — também
    // recebe e-mail e acompanha o chamado. Ausência das colunas/vínculo é normal
    // (nem todo funcionário tem responsável cadastrado) e não deve travar o widget.
    $__supRespNome  = "";
    $__supRespEmail = "";
    if (!empty($__supUid) && function_exists("query")) {
        $__respResult = query(
            "SELECT r.enti_tx_nome AS resp_nome, r.enti_tx_email AS resp_email
             FROM user u
             JOIN entidade e ON e.enti_nb_id = u.user_nb_entidade
             JOIN entidade r ON r.enti_nb_id = e.enti_respFuncionario_id
             WHERE u.user_nb_id = ?
             LIMIT 1",
            "i",
            [(int) $__supUid]
        );
        $__respRow = $__respResult ? mysqli_fetch_assoc($__respResult) : null;
        if ($__respRow) {
            $__supRespNome  = trim(strval($__respRow["resp_nome"] ?? ""));
            $__supRespEmail = trim(strval($__respRow["resp_email"] ?? ""));
            if (!filter_var($__supRespEmail, FILTER_VALIDATE_EMAIL)) {
                $__supRespEmail = "";
            }
        }
    }

    // Token de curta duração (5 min) — assinado com chave derivada por empresa.
    $__supExp    = time() + 300;
    $__supJson   = json_encode([
        "empresa"           => $__supEmpresa,
        "empresa_nome"      => $__supEmpNome,
        "uid"               => $__supUid,
        "ulogin"            => $__supLogin,
        "unome"             => $__supNome,
        "user_email"        => $__supEmail,
        "responsavel_nome"  => $__supRespNome,
        "responsavel_email" => $__supRespEmail,
        "exp"               => $__supExp,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $__supPayload = rtrim(strtr(base64_encode($__supJson), "+/", "-_"), "=");
    $__supKeyD    = hash_hmac("sha256", "techps_suporte|" . $__supEmpresa, $__supKey, true);
    $__supSig     = rtrim(strtr(base64_encode(hash_hmac("sha256", $__supPayload, $__supKeyD, true)), "+/", "-_"), "=");
    $__supToken   = $__supPayload . "." . $__supSig;

    $__supEndpoint = $__supApiUrl . "/suporte/tickets";
?>
<!-- ══ WIDGET SUPORTE ══════════════════════════════════════════════════ -->
<button type="button" id="suporte-widget-btn" title="Abrir chamado de suporte"
    style="position:fixed;left:24px;bottom:24px;width:56px;height:56px;border-radius:50%;border:none;background:#337ab7;color:#fff;font-size:24px;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.25);z-index:9997;display:flex;align-items:center;justify-content:center;transition:transform .15s;">
    <i class="fa fa-life-ring" aria-hidden="true"></i>
</button>

<div id="suporte-widget-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.55);z-index:9998;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;max-width:480px;width:94%;max-height:92vh;overflow-y:auto;position:relative;padding:22px;box-shadow:0 10px 40px rgba(0,0,0,.3);">
        <button type="button" id="suporte-widget-fechar" style="position:absolute;top:10px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:#888;" title="Fechar">&times;</button>

        <h4 style="margin:0 0 4px;color:#333;"><i class="fa fa-life-ring" style="color:#337ab7;"></i> Suporte Técnico</h4>
        <p style="margin:0 0 8px;font-size:12px;color:#888;">Descreva o problema para a equipe TechPS. Empresa e usuário são preenchidos automaticamente.</p>
        <p style="margin:0 0 16px;font-size:12px;">
            <a href="<?= htmlspecialchars(rtrim(strval($_ENV["APP_PATH"] ?? "") . strval($_ENV["CONTEX_PATH"] ?? ""), "/")) ?>/suporte/meus_chamados.php"><i class="fa fa-list"></i> Ver meus chamados</a>
        </p>

        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:3px;">Empresa</label>
            <input type="text" id="suporte-campo-empresa" readonly style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:4px;background:#f5f5f5;font-size:13px;color:#333;box-sizing:border-box;" />
        </div>

        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:3px;">Usuário</label>
            <input type="text" id="suporte-campo-usuario" readonly style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:4px;background:#f5f5f5;font-size:13px;color:#333;box-sizing:border-box;" />
        </div>

        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:3px;">Página onde ocorreu o problema</label>
            <input type="text" id="suporte-campo-pagina" readonly style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:4px;background:#f5f5f5;font-size:12px;color:#333;box-sizing:border-box;" />
        </div>

        <div id="suporte-campo-tipo-wrap" style="margin-bottom:10px;display:none;">
            <label style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:3px;">Tipo de chamado <span style="color:#e74c3c;">*</span></label>
            <select id="suporte-campo-tipo" style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;color:#333;box-sizing:border-box;">
                <option value="">Selecione...</option>
            </select>
        </div>

        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:3px;">Descrição do problema <span style="color:#e74c3c;">*</span></label>
            <textarea id="suporte-campo-descricao" rows="4" maxlength="2000" placeholder="Ex.: ao tentar lançar a batida de ponto, a tela fica em branco..." style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;resize:vertical;box-sizing:border-box;"></textarea>
            <div style="font-size:11px;color:#8a6d3b;background:#fcf8e3;border:1px solid #faebcc;border-radius:4px;padding:6px 8px;margin-top:4px;"><i class="fa fa-info-circle"></i> Atenção: se houver vídeo do problema, hospede no Google Drive ou envie um link compartilhado e cole na descrição.</div>
            <div style="text-align:right;font-size:11px;color:#aaa;" id="suporte-descricao-contador">0/2000</div>
        </div>

        <div style="margin-bottom:14px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:3px;">Anexos (opcional, até 6 — imagem/documento até 5MB, no máx. 1 vídeo até 25MB, no máx. 2 áudios até 8MB)</label>
            <input type="file" id="suporte-campo-imagens" accept="image/*,video/mp4,video/quicktime,video/webm,audio/*,.mp3,.wav,.ogg,.oga,.m4a,.weba,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv" multiple style="display:none;" />
            <div style="display:flex;gap:8px;">
                <button type="button" id="suporte-botao-anexar" style="flex:1;border:1px dashed #aaa;background:#fafafa;color:#555;border-radius:4px;padding:10px;cursor:pointer;font-size:13px;">
                    <i class="fa fa-paperclip"></i> Anexar arquivo
                </button>
                <button type="button" id="suporte-botao-print" style="flex:1;border:1px dashed #aaa;background:#fafafa;color:#555;border-radius:4px;padding:10px;cursor:pointer;font-size:13px;">
                    <i class="fa fa-desktop"></i> Tirar print da tela
                </button>
                <button type="button" id="suporte-botao-audio" style="flex:1;border:1px dashed #aaa;background:#fafafa;color:#555;border-radius:4px;padding:10px;cursor:pointer;font-size:13px;">
                    <i class="fa fa-microphone"></i> Gravar áudio
                </button>
            </div>
            <div id="suporte-lista-imagens" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;"></div>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" id="suporte-botao-cancelar" style="border:1px solid #ccc;background:#fff;color:#555;border-radius:4px;padding:8px 16px;cursor:pointer;font-size:13px;">Cancelar</button>
            <button type="button" id="suporte-botao-enviar" style="border:none;background:#337ab7;color:#fff;border-radius:4px;padding:8px 18px;cursor:pointer;font-size:13px;font-weight:700;">
                <i class="fa fa-paper-plane"></i> Enviar chamado
            </button>
        </div>
    </div>
</div>

<script>
(function(){
    var cfg = {
        endpoint: <?= json_encode($__supEndpoint) ?>,
        tiposEndpoint: <?= json_encode($__supApiUrl . "/suporte/tipos") ?>,
        token:    <?= json_encode($__supToken) ?>,
        empresa:  <?= json_encode($__supEmpNome) ?>,
        usuario:  <?= json_encode($__supNome . " (" . $__supLogin . ")") ?>
    };

    var MAX_ARQUIVOS      = 6;
    var MAX_VIDEOS        = 1;
    var MAX_AUDIOS        = 2;
    var MAX_BYTES_PADRAO  = 5 * 1024 * 1024;
    var MAX_BYTES_VIDEO   = 25 * 1024 * 1024;
    var MAX_BYTES_AUDIO   = 8 * 1024 * 1024;
    var MAX_SEGUNDOS_GRAVACAO = 180; // 3 minutos — evita estourar o limite de 8MB do áudio
    var EXT_IMAGEM    = ['jpg','jpeg','png','webp','gif'];
    var EXT_VIDEO     = ['mp4','mov','webm'];
    var EXT_AUDIO     = ['mp3','wav','oga','ogg','m4a','weba'];
    var EXT_DOCUMENTO = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv'];

    var arquivos     = [];
    var urlPagina    = "";
    var enviando     = false;
    var tipoObrigatorio = false;
    var pararGravacaoEmAndamento = function(){}; // sobrescrita pelo bloco de gravação de áudio, se suportado

    var btnAbrir     = document.getElementById('suporte-widget-btn');
    var modal        = document.getElementById('suporte-widget-modal');
    var btnFechar    = document.getElementById('suporte-widget-fechar');
    var btnCancelar  = document.getElementById('suporte-botao-cancelar');
    var btnEnviar    = document.getElementById('suporte-botao-enviar');
    var btnAnexar    = document.getElementById('suporte-botao-anexar');
    var btnPrint     = document.getElementById('suporte-botao-print');
    var btnAudio     = document.getElementById('suporte-botao-audio');
    var campoImg     = document.getElementById('suporte-campo-imagens');
    var txtDesc      = document.getElementById('suporte-campo-descricao');
    var contador     = document.getElementById('suporte-descricao-contador');
    var listaImg     = document.getElementById('suporte-lista-imagens');
    var tipoWrap     = document.getElementById('suporte-campo-tipo-wrap');
    var campoTipo    = document.getElementById('suporte-campo-tipo');

    if (!btnAbrir || !modal) return;

    function categoriaDoArquivo(arquivo){
        var nome = (arquivo.name || '').toLowerCase();
        var ext = nome.indexOf('.') >= 0 ? nome.split('.').pop() : '';
        if (EXT_IMAGEM.indexOf(ext) !== -1) return 'imagem';
        if (EXT_VIDEO.indexOf(ext) !== -1) return 'video';
        if (EXT_AUDIO.indexOf(ext) !== -1) return 'audio';
        if (EXT_DOCUMENTO.indexOf(ext) !== -1) return 'documento';
        return null;
    }

    function totalVideosAtual(){
        return arquivos.filter(function(a){ return categoriaDoArquivo(a) === 'video'; }).length;
    }

    function totalAudiosAtual(){
        return arquivos.filter(function(a){ return categoriaDoArquivo(a) === 'audio'; }).length;
    }

    function tentarAdicionarArquivo(arquivo){
        if (arquivos.length >= MAX_ARQUIVOS) { alert('Máximo de ' + MAX_ARQUIVOS + ' anexos por chamado.'); return false; }
        var categoria = categoriaDoArquivo(arquivo);
        if (!categoria) { alert('"' + arquivo.name + '" não é um tipo de arquivo permitido.'); return false; }
        if (categoria === 'video') {
            if (totalVideosAtual() >= MAX_VIDEOS) { alert('Permitido no máximo ' + MAX_VIDEOS + ' vídeo por chamado.'); return false; }
            if (arquivo.size > MAX_BYTES_VIDEO) { alert('"' + arquivo.name + '" excede 25MB.'); return false; }
        } else if (categoria === 'audio') {
            if (totalAudiosAtual() >= MAX_AUDIOS) { alert('Permitido no máximo ' + MAX_AUDIOS + ' áudio(s) por chamado.'); return false; }
            if (arquivo.size > MAX_BYTES_AUDIO) { alert('"' + arquivo.name + '" excede 8MB.'); return false; }
        } else {
            if (arquivo.size > MAX_BYTES_PADRAO) { alert('"' + arquivo.name + '" excede 5MB.'); return false; }
        }
        arquivos.push(arquivo);
        return true;
    }

    // ── Carrega os tipos de chamado (cada tipo já sabe qual setor recebe) ──
    function carregarTipos(){
        if (!campoTipo || !tipoWrap) return;
        fetch(cfg.tiposEndpoint, { headers: { 'Authorization': 'Bearer ' + cfg.token } })
            .then(function(resposta){ return resposta.json(); })
            .then(function(json){
                var tipos = (json && json.ok && Array.isArray(json.tipos)) ? json.tipos : [];
                tipoObrigatorio = tipos.length > 0;
                if (!tipoObrigatorio) { tipoWrap.style.display = 'none'; return; }
                var atual = campoTipo.value;
                campoTipo.innerHTML = '<option value="">Selecione...</option>';
                tipos.forEach(function(t){
                    var opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.nome;
                    campoTipo.appendChild(opt);
                });
                campoTipo.value = atual;
                tipoWrap.style.display = '';
            })
            .catch(function(){ /* silencioso: se a API de tipos falhar, não bloqueia o chamado */ });
    }

    // ── Abrir: captura a URL exata no momento do clique ──
    btnAbrir.addEventListener('click', function(){
        urlPagina = window.location.href;
        document.getElementById('suporte-campo-empresa').value  = cfg.empresa;
        document.getElementById('suporte-campo-usuario').value = cfg.usuario;
        document.getElementById('suporte-campo-pagina').value  = urlPagina;
        modal.style.display = 'flex';
        txtDesc.focus();
        carregarTipos();
    });

    function fechar(){
        pararGravacaoEmAndamento();
        modal.style.display = 'none';
    }
    btnFechar.addEventListener('click', fechar);
    btnCancelar.addEventListener('click', fechar);
    modal.addEventListener('click', function(e){ if (e.target === modal) fechar(); });

    // ── Descrição / contador ──
    txtDesc.addEventListener('input', function(){
        contador.textContent = txtDesc.value.length + '/2000';
    });

    // ── Anexos ──
    btnAnexar.addEventListener('click', function(){ campoImg.click(); });

    campoImg.addEventListener('change', function(){
        Array.prototype.forEach.call(campoImg.files, function(arquivo){
            tentarAdicionarArquivo(arquivo);
        });
        campoImg.value = '';
        renderizarImagens();
    });

    // ── Recorte do print: mostra a captura em tela cheia e deixa o usuário arrastar a seleção ──
    function abrirRecorteTela(canvasOriginal, aoConfirmar){
        var overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:10000;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;box-sizing:border-box;';

        var dica = document.createElement('div');
        dica.textContent = 'Clique e arraste para selecionar a área do print';
        dica.style.cssText = 'color:#fff;font-size:22px;font-weight:700;text-align:center;margin-bottom:16px;text-shadow:0 2px 6px rgba(0,0,0,.6);max-width:90vw;';
        overlay.appendChild(dica);

        var palco = document.createElement('div');
        palco.style.cssText = 'position:relative;max-width:90vw;max-height:70vh;line-height:0;cursor:crosshair;touch-action:none;';
        var imgTela = document.createElement('img');
        imgTela.src = canvasOriginal.toDataURL('image/png');
        // width/height auto explícitos: há páginas com regra global de img (ex.: logistica_modal.css força 30x30).
        imgTela.style.cssText = 'width:auto;height:auto;min-width:0;min-height:0;max-width:90vw;max-height:70vh;display:block;user-select:none;-webkit-user-drag:none;';
        imgTela.draggable = false;
        palco.appendChild(imgTela);

        var selecao = document.createElement('div');
        selecao.style.cssText = 'position:absolute;border:2px dashed #337ab7;background:rgba(51,122,183,.25);display:none;pointer-events:none;';
        palco.appendChild(selecao);

        overlay.appendChild(palco);

        var barraBotoes = document.createElement('div');
        barraBotoes.style.cssText = 'margin-top:14px;display:flex;gap:8px;';
        overlay.appendChild(barraBotoes);

        document.body.appendChild(overlay);

        var inicio = null;

        function coordsRelativas(evento){
            var rect = imgTela.getBoundingClientRect();
            var ponto = (evento.touches && evento.touches[0]) || (evento.changedTouches && evento.changedTouches[0]) || evento;
            var x = Math.min(Math.max(ponto.clientX - rect.left, 0), rect.width);
            var y = Math.min(Math.max(ponto.clientY - rect.top, 0), rect.height);
            return { x: x, y: y, rect: rect };
        }

        function iniciarSelecao(evento){
            evento.preventDefault();
            inicio = coordsRelativas(evento);
            selecao.style.left = inicio.x + 'px';
            selecao.style.top = inicio.y + 'px';
            selecao.style.width = '0px';
            selecao.style.height = '0px';
            selecao.style.display = 'block';
        }

        function atualizarSelecao(evento){
            if (!inicio) return;
            evento.preventDefault();
            var c = coordsRelativas(evento);
            var x1 = Math.min(inicio.x, c.x), x2 = Math.max(inicio.x, c.x);
            var y1 = Math.min(inicio.y, c.y), y2 = Math.max(inicio.y, c.y);
            selecao.style.left = x1 + 'px';
            selecao.style.top = y1 + 'px';
            selecao.style.width = (x2 - x1) + 'px';
            selecao.style.height = (y2 - y1) + 'px';
        }

        function finalizarSelecao(evento){
            if (!inicio) return;
            var c = coordsRelativas(evento);
            var rect = c.rect;
            var x1 = Math.min(inicio.x, c.x), x2 = Math.max(inicio.x, c.x);
            var y1 = Math.min(inicio.y, c.y), y2 = Math.max(inicio.y, c.y);
            inicio = null;

            var larguraTela = x2 - x1;
            var alturaTela = y2 - y1;
            if (larguraTela < 10 || alturaTela < 10) {
                selecao.style.display = 'none';
                return; // seleção pequena demais — ignora e deixa tentar de novo
            }

            var escalaX = canvasOriginal.width / rect.width;
            var escalaY = canvasOriginal.height / rect.height;
            var sx = Math.round(x1 * escalaX);
            var sy = Math.round(y1 * escalaY);
            var sw = Math.round(larguraTela * escalaX);
            var sh = Math.round(alturaTela * escalaY);

            var canvasRecorte = document.createElement('canvas');
            canvasRecorte.width = sw;
            canvasRecorte.height = sh;
            canvasRecorte.getContext('2d').drawImage(canvasOriginal, sx, sy, sw, sh, 0, 0, sw, sh);

            mostrarPreview(canvasRecorte);
        }

        function limparEventos(){
            window.removeEventListener('mousemove', atualizarSelecao);
            window.removeEventListener('mouseup', finalizarSelecao);
            window.removeEventListener('touchmove', atualizarSelecao);
            window.removeEventListener('touchend', finalizarSelecao);
        }

        function fecharOverlay(){
            limparEventos();
            if (overlay.parentNode) { overlay.parentNode.removeChild(overlay); }
        }

        function criarBotao(texto, estilo){
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = texto;
            b.style.cssText = estilo;
            return b;
        }

        var ESTILO_SECUNDARIO = 'border:1px solid #ccc;background:#fff;color:#555;border-radius:4px;padding:8px 16px;cursor:pointer;font-size:13px;';
        var ESTILO_PRIMARIO = 'border:none;background:#337ab7;color:#fff;border-radius:4px;padding:8px 18px;cursor:pointer;font-size:13px;font-weight:700;';

        var btnCancelarSelecao = criarBotao('Cancelar', ESTILO_SECUNDARIO);
        btnCancelarSelecao.addEventListener('click', fecharOverlay);
        barraBotoes.appendChild(btnCancelarSelecao);

        var btnTelaInteira = criarBotao('Usar a tela inteira', ESTILO_SECUNDARIO);
        btnTelaInteira.addEventListener('click', function(){
            var copia = document.createElement('canvas');
            copia.width = canvasOriginal.width;
            copia.height = canvasOriginal.height;
            copia.getContext('2d').drawImage(canvasOriginal, 0, 0);
            mostrarPreview(copia);
        });
        barraBotoes.appendChild(btnTelaInteira);

        palco.addEventListener('mousedown', iniciarSelecao);
        window.addEventListener('mousemove', atualizarSelecao);
        window.addEventListener('mouseup', finalizarSelecao);
        palco.addEventListener('touchstart', iniciarSelecao, { passive: false });
        window.addEventListener('touchmove', atualizarSelecao, { passive: false });
        window.addEventListener('touchend', finalizarSelecao);

        // Depois do recorte: editor para marcar o print (lápis, marca-texto, retângulo, círculo, seta e texto).
        // O desenho é aplicado direto nos pixels do recorte, então o anexo já sai com as marcações.
        function mostrarPreview(canvasRecorte){
            limparEventos();
            palco.removeEventListener('mousedown', iniciarSelecao);
            palco.removeEventListener('touchstart', iniciarSelecao);
            palco.innerHTML = '';
            palco.style.cursor = 'crosshair';
            dica.textContent = 'Destaque o problema no print e clique em Anexar';

            var base = document.createElement('canvas');
            base.width = canvasRecorte.width;
            base.height = canvasRecorte.height;
            base.getContext('2d').drawImage(canvasRecorte, 0, 0);

            canvasRecorte.style.cssText = 'width:auto;height:auto;min-width:0;min-height:0;max-width:90vw;max-height:62vh;display:block;border:1px solid #fff;touch-action:none;';
            palco.style.maxHeight = '62vh';
            palco.appendChild(canvasRecorte);
            var ctx = canvasRecorte.getContext('2d');

            var marcas = [];
            var atual = null;
            var ferramenta = 'lapis';
            var cor = '#e74c3c';
            var espessura = 4;
            var campoTexto = null;

            // ── Barra de ferramentas ──
            var barraFerr = document.createElement('div');
            barraFerr.style.cssText = 'display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px;margin-bottom:10px;background:#fff;border-radius:6px;padding:6px 8px;max-width:90vw;box-sizing:border-box;line-height:normal;';
            overlay.insertBefore(barraFerr, palco);

            var ESTILO_FERR = 'border:1px solid #ccc;background:#fff;color:#333;border-radius:4px;padding:5px 9px;cursor:pointer;font-size:13px;min-width:34px;';
            var ESTILO_FERR_ATIVA = 'border:1px solid #337ab7;background:#337ab7;color:#fff;border-radius:4px;padding:5px 9px;cursor:pointer;font-size:13px;min-width:34px;';
            function separador(){
                var s = document.createElement('span');
                s.style.cssText = 'width:1px;align-self:stretch;background:#ddd;margin:0 2px;';
                barraFerr.appendChild(s);
            }

            // Ícones em SVG: a página mistura Font Awesome 4 e 7, e nem todo nome de ícone existe nas duas.
            function iconeSvg(conteudo){
                return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block;margin:auto;">' + conteudo + '</svg>';
            }
            var ICONES = {
                lapis: iconeSvg('<path d="M17 3l4 4L8 20H4v-4z"/>'),
                marca: iconeSvg('<path d="M4 20h8"/><path d="M14.5 4.5l5 5L11 18H6v-5z" fill="currentColor" fill-opacity=".35"/>'),
                retangulo: iconeSvg('<rect x="3" y="5" width="18" height="14" rx="1"/>'),
                elipse: iconeSvg('<ellipse cx="12" cy="12" rx="9" ry="7"/>'),
                seta: iconeSvg('<path d="M4 20L20 4"/><path d="M10 4h10v10"/>'),
                texto: iconeSvg('<path d="M5 6V4h14v2"/><path d="M12 4v16"/><path d="M9 20h6"/>'),
                desfazer: iconeSvg('<path d="M9 14L4 9l5-5"/><path d="M4 9h10a6 6 0 010 12h-3"/>'),
                limpar: iconeSvg('<path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/>')
            };

            var botoesFerr = {};
            [
                ['lapis', 'Lápis'],
                ['marca', 'Marca-texto'],
                ['retangulo', 'Retângulo'],
                ['elipse', 'Círculo'],
                ['seta', 'Seta'],
                ['texto', 'Texto']
            ].forEach(function(def){
                var b = document.createElement('button');
                b.type = 'button';
                b.title = def[1];
                b.innerHTML = ICONES[def[0]];
                b.addEventListener('click', function(){ confirmarTexto(); escolherFerramenta(def[0]); });
                botoesFerr[def[0]] = b;
                barraFerr.appendChild(b);
            });
            function escolherFerramenta(nome){
                ferramenta = nome;
                Object.keys(botoesFerr).forEach(function(k){ botoesFerr[k].style.cssText = k === nome ? ESTILO_FERR_ATIVA : ESTILO_FERR; });
                palco.style.cursor = nome === 'texto' ? 'text' : 'crosshair';
            }

            separador();
            var botoesCor = [];
            ['#e74c3c', '#f1c40f', '#27ae60', '#337ab7', '#000000', '#ffffff'].forEach(function(c){
                var b = document.createElement('button');
                b.type = 'button';
                b.title = 'Cor';
                b.setAttribute('data-cor', c);
                b.addEventListener('click', function(){ escolherCor(c); });
                botoesCor.push(b);
                barraFerr.appendChild(b);
            });
            function escolherCor(c){
                cor = c;
                if (campoTexto) campoTexto.style.color = c;
                botoesCor.forEach(function(b){
                    var ativa = b.getAttribute('data-cor') === c;
                    b.style.cssText = 'width:22px;height:22px;border-radius:50%;cursor:pointer;padding:0;background:' + b.getAttribute('data-cor') + ';border:' + (ativa ? '3px solid #337ab7' : '1px solid #999') + ';box-shadow:' + (ativa ? '0 0 0 2px #fff inset' : 'none') + ';';
                });
            }

            separador();
            var seletorEspessura = document.createElement('select');
            seletorEspessura.title = 'Espessura';
            seletorEspessura.style.cssText = 'border:1px solid #ccc;border-radius:4px;padding:4px;font-size:13px;';
            [['2', 'Fino'], ['4', 'Médio'], ['8', 'Grosso']].forEach(function(o){
                var op = document.createElement('option');
                op.value = o[0];
                op.textContent = o[1];
                if (o[0] === '4') op.selected = true;
                seletorEspessura.appendChild(op);
            });
            seletorEspessura.addEventListener('change', function(){ espessura = parseInt(seletorEspessura.value, 10) || 4; });
            barraFerr.appendChild(seletorEspessura);

            separador();
            var btnDesfazer = document.createElement('button');
            btnDesfazer.type = 'button';
            btnDesfazer.title = 'Desfazer (Ctrl+Z)';
            btnDesfazer.innerHTML = ICONES.desfazer;
            btnDesfazer.style.cssText = ESTILO_FERR;
            btnDesfazer.addEventListener('click', desfazer);
            barraFerr.appendChild(btnDesfazer);

            var btnLimpar = document.createElement('button');
            btnLimpar.type = 'button';
            btnLimpar.title = 'Apagar todas as marcações';
            btnLimpar.innerHTML = ICONES.limpar;
            btnLimpar.style.cssText = ESTILO_FERR;
            btnLimpar.addEventListener('click', function(){ confirmarTexto(); marcas = []; redesenhar(); });
            barraFerr.appendChild(btnLimpar);

            escolherFerramenta('lapis');
            escolherCor(cor);

            // ── Desenho ──
            // Espessura e fonte acompanham a escala exibida, para o traço parecer igual em qualquer tamanho de print.
            function escala(){
                var r = canvasRecorte.getBoundingClientRect();
                return r.width ? canvasRecorte.width / r.width : 1;
            }
            function ponto(evento){
                var r = canvasRecorte.getBoundingClientRect();
                var p = (evento.touches && evento.touches[0]) || (evento.changedTouches && evento.changedTouches[0]) || evento;
                return {
                    x: Math.min(Math.max(p.clientX - r.left, 0), r.width) * (canvasRecorte.width / r.width),
                    y: Math.min(Math.max(p.clientY - r.top, 0), r.height) * (canvasRecorte.height / r.height)
                };
            }

            function desenharMarca(m){
                ctx.save();
                ctx.strokeStyle = m.cor;
                ctx.fillStyle = m.cor;
                ctx.lineWidth = m.largura;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                if (m.tipo === 'lapis' || m.tipo === 'marca') {
                    if (m.tipo === 'marca') { ctx.globalAlpha = 0.35; ctx.lineCap = 'square'; }
                    ctx.beginPath();
                    m.pontos.forEach(function(p, i){ i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y); });
                    if (m.pontos.length === 1) ctx.lineTo(m.pontos[0].x + 0.1, m.pontos[0].y);
                    ctx.stroke();
                } else if (m.tipo === 'retangulo') {
                    ctx.strokeRect(Math.min(m.x1, m.x2), Math.min(m.y1, m.y2), Math.abs(m.x2 - m.x1), Math.abs(m.y2 - m.y1));
                } else if (m.tipo === 'elipse') {
                    ctx.beginPath();
                    ctx.ellipse((m.x1 + m.x2) / 2, (m.y1 + m.y2) / 2, Math.abs(m.x2 - m.x1) / 2, Math.abs(m.y2 - m.y1) / 2, 0, 0, Math.PI * 2);
                    ctx.stroke();
                } else if (m.tipo === 'seta') {
                    var ang = Math.atan2(m.y2 - m.y1, m.x2 - m.x1);
                    var ponta = Math.max(m.largura * 4, 12);
                    ctx.beginPath();
                    ctx.moveTo(m.x1, m.y1);
                    ctx.lineTo(m.x2 - Math.cos(ang) * ponta * 0.6, m.y2 - Math.sin(ang) * ponta * 0.6);
                    ctx.stroke();
                    ctx.beginPath();
                    ctx.moveTo(m.x2, m.y2);
                    ctx.lineTo(m.x2 - ponta * Math.cos(ang - Math.PI / 7), m.y2 - ponta * Math.sin(ang - Math.PI / 7));
                    ctx.lineTo(m.x2 - ponta * Math.cos(ang + Math.PI / 7), m.y2 - ponta * Math.sin(ang + Math.PI / 7));
                    ctx.closePath();
                    ctx.fill();
                } else if (m.tipo === 'texto') {
                    ctx.font = 'bold ' + m.tamanho + 'px Arial, sans-serif';
                    ctx.textBaseline = 'top';
                    // Contorno de contraste para o texto ser legível sobre qualquer fundo.
                    ctx.lineWidth = Math.max(m.tamanho / 6, 2);
                    ctx.strokeStyle = m.cor === '#ffffff' ? '#000000' : '#ffffff';
                    m.texto.split('\n').forEach(function(linha, i){
                        ctx.strokeText(linha, m.x, m.y + i * m.tamanho * 1.2);
                        ctx.fillText(linha, m.x, m.y + i * m.tamanho * 1.2);
                    });
                }
                ctx.restore();
            }

            function redesenhar(){
                ctx.clearRect(0, 0, canvasRecorte.width, canvasRecorte.height);
                ctx.drawImage(base, 0, 0);
                marcas.forEach(desenharMarca);
                if (atual) desenharMarca(atual);
                btnDesfazer.disabled = marcas.length === 0;
                btnDesfazer.style.opacity = marcas.length ? '1' : '.5';
                btnLimpar.disabled = marcas.length === 0;
                btnLimpar.style.opacity = marcas.length ? '1' : '.5';
            }

            function desfazer(){
                if (campoTexto) { cancelarTexto(); return; }
                marcas.pop();
                redesenhar();
            }

            // ── Texto: caixa digitável sobre o print; Enter confirma, Shift+Enter quebra linha, Esc cancela ──
            function abrirTexto(evento){
                var p = ponto(evento);
                var r = canvasRecorte.getBoundingClientRect();
                var pr = palco.getBoundingClientRect();
                var s = escala();
                var tamanhoTela = 12 + espessura * 2;
                campoTexto = document.createElement('textarea');
                campoTexto.rows = 1;
                campoTexto.setAttribute('data-x', p.x);
                campoTexto.setAttribute('data-y', p.y);
                campoTexto.setAttribute('data-tamanho', Math.round(tamanhoTela * s));
                campoTexto.style.cssText = 'position:absolute;left:' + (p.x / s + r.left - pr.left) + 'px;top:' + (p.y / s + r.top - pr.top) + 'px;min-width:140px;background:rgba(255,255,255,.85);border:1px dashed #337ab7;outline:none;resize:none;padding:0 2px;margin:0;font:bold ' + tamanhoTela + 'px Arial, sans-serif;line-height:1.2;color:' + cor + ';z-index:2;overflow:hidden;';
                campoTexto.placeholder = 'Digite...';
                campoTexto.addEventListener('keydown', function(e){
                    e.stopPropagation();
                    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); confirmarTexto(); }
                    else if (e.key === 'Escape') { e.preventDefault(); cancelarTexto(); }
                });
                campoTexto.addEventListener('input', function(){
                    campoTexto.rows = Math.max(campoTexto.value.split('\n').length, 1);
                });
                campoTexto.addEventListener('mousedown', function(e){ e.stopPropagation(); });
                campoTexto.addEventListener('touchstart', function(e){ e.stopPropagation(); });
                palco.appendChild(campoTexto);
                setTimeout(function(){ if (campoTexto) campoTexto.focus(); }, 0);
            }
            function confirmarTexto(){
                if (!campoTexto) return;
                var texto = campoTexto.value.replace(/\s+$/, '');
                if (texto !== '') {
                    marcas.push({
                        tipo: 'texto', cor: cor, texto: texto,
                        x: parseFloat(campoTexto.getAttribute('data-x')),
                        y: parseFloat(campoTexto.getAttribute('data-y')),
                        tamanho: parseInt(campoTexto.getAttribute('data-tamanho'), 10)
                    });
                }
                cancelarTexto();
                redesenhar();
            }
            function cancelarTexto(){
                if (campoTexto && campoTexto.parentNode) campoTexto.parentNode.removeChild(campoTexto);
                campoTexto = null;
            }

            function iniciarDesenho(evento){
                if (evento.button !== undefined && evento.button !== 0) return;
                evento.preventDefault();
                if (campoTexto) { confirmarTexto(); return; }
                if (ferramenta === 'texto') { abrirTexto(evento); return; }
                var p = ponto(evento);
                var largura = espessura * escala();
                if (ferramenta === 'lapis' || ferramenta === 'marca') {
                    atual = { tipo: ferramenta, cor: cor, largura: ferramenta === 'marca' ? largura * 4 : largura, pontos: [p] };
                } else {
                    atual = { tipo: ferramenta, cor: cor, largura: largura, x1: p.x, y1: p.y, x2: p.x, y2: p.y };
                }
                redesenhar();
            }
            function moverDesenho(evento){
                if (!atual) return;
                evento.preventDefault();
                var p = ponto(evento);
                if (atual.pontos) { atual.pontos.push(p); } else { atual.x2 = p.x; atual.y2 = p.y; }
                redesenhar();
            }
            function terminarDesenho(){
                if (!atual) return;
                // Clique sem arrastar em forma/seta não vira marcação.
                var valida = atual.pontos || Math.abs(atual.x2 - atual.x1) > 3 || Math.abs(atual.y2 - atual.y1) > 3;
                if (valida) marcas.push(atual);
                atual = null;
                redesenhar();
            }
            function atalhos(e){
                if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) { e.preventDefault(); desfazer(); }
            }

            canvasRecorte.addEventListener('mousedown', iniciarDesenho);
            window.addEventListener('mousemove', moverDesenho);
            window.addEventListener('mouseup', terminarDesenho);
            canvasRecorte.addEventListener('touchstart', iniciarDesenho, { passive: false });
            window.addEventListener('touchmove', moverDesenho, { passive: false });
            window.addEventListener('touchend', terminarDesenho);
            document.addEventListener('keydown', atalhos);

            // Os listeners do editor ficam na window/document: saem junto com o overlay.
            var limparEventosRecorte = limparEventos;
            limparEventos = function(){
                limparEventosRecorte();
                window.removeEventListener('mousemove', moverDesenho);
                window.removeEventListener('mouseup', terminarDesenho);
                window.removeEventListener('touchmove', moverDesenho);
                window.removeEventListener('touchend', terminarDesenho);
                document.removeEventListener('keydown', atalhos);
            };

            redesenhar();

            barraBotoes.innerHTML = '';

            var btnRefazer = criarBotao('Refazer seleção', ESTILO_SECUNDARIO);
            btnRefazer.addEventListener('click', function(){
                fecharOverlay();
                abrirRecorteTela(canvasOriginal, aoConfirmar);
            });

            var btnCancelar2 = criarBotao('Cancelar', ESTILO_SECUNDARIO);
            btnCancelar2.addEventListener('click', fecharOverlay);

            var btnAnexar2 = criarBotao('Anexar', ESTILO_PRIMARIO);
            btnAnexar2.addEventListener('click', function(){
                // Texto ainda sendo digitado entra no anexo.
                confirmarTexto();
                // Fecha a tela de recorte na hora — não espera a geração do blob para sumir.
                fecharOverlay();
                try {
                    canvasRecorte.toBlob(function(blob){
                        if (blob) {
                            aoConfirmar(blob);
                        } else {
                            alert('Não foi possível gerar a imagem do recorte. Tente novamente.');
                        }
                    }, 'image/png');
                } catch (e) {
                    alert('Não foi possível gerar a imagem do recorte. Tente novamente.');
                }
            });

            barraBotoes.appendChild(btnRefazer);
            barraBotoes.appendChild(btnCancelar2);
            barraBotoes.appendChild(btnAnexar2);
        }
    }

    // ── Print da tela: captura nativa da própria aba (getDisplayMedia) e depois recorte/desenho ──
    // O navegador tira o print real, então textos, campos, ícones e mapas saem exatamente como na tela.
    // (html2canvas redesenhava a página e deslocava textos de botões e apagava valores de campos.)
    // O Chrome pede uma confirmação ("Compartilhar esta aba?") a cada print.
    var capturaSuportada = !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia);

    function esperar(ms){
        return new Promise(function(resolve){ setTimeout(resolve, ms); });
    }

    function capturarAba(){
        return navigator.mediaDevices.getDisplayMedia({
            video: { displaySurface: 'browser', frameRate: 30 },
            audio: false,
            preferCurrentTab: true,
            selfBrowserSurface: 'include',
            surfaceSwitching: 'exclude',
            monitorTypeSurfaces: 'exclude'
        }).then(function(stream){
            var video = document.createElement('video');
            video.muted = true;
            video.playsInline = true;
            video.srcObject = stream;
            function parar(){
                stream.getTracks().forEach(function(t){ t.stop(); });
                video.srcObject = null;
            }
            return new Promise(function(resolve, reject){
                video.onloadedmetadata = function(){ video.play().then(resolve, reject); };
                video.onerror = reject;
            })
            // Dá tempo da janela de permissão sumir e do widget oculto sair do quadro.
            .then(function(){ return esperar(350); })
            .then(function(){
                var canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                parar();
                if (!canvas.width || !canvas.height) throw new Error('Quadro vazio');
                return canvas;
            }, function(erro){
                parar();
                throw erro;
            });
        });
    }

    if (btnPrint) {
        if (!capturaSuportada) {
            // Ex.: navegadores de celular — sem captura de tela; segue com anexar arquivo.
            btnPrint.style.display = 'none';
        }
        btnPrint.addEventListener('click', function(){
            // Esconde o widget (modal + botão flutuante) para ele não aparecer no print.
            modal.style.display = 'none';
            btnAbrir.style.display = 'none';

            function restaurarWidget(){
                modal.style.display = 'flex';
                btnAbrir.style.display = 'flex';
            }

            capturarAba().then(function(canvas){
                restaurarWidget();
                abrirRecorteTela(canvas, function(blob){
                    var arquivo = new File([blob], 'print-' + Date.now() + '.png', { type: 'image/png' });
                    if (tentarAdicionarArquivo(arquivo)) { renderizarImagens(); }
                });
            }).catch(function(erro){
                restaurarWidget();
                // Negar a permissão não é erro: só volta para o formulário.
                if (erro && (erro.name === 'NotAllowedError' || erro.name === 'AbortError')) return;
                alert('Não foi possível capturar a tela. Você pode anexar uma imagem pelo botão Anexar arquivo.');
            });
        });
    }

    // ── Gravação de áudio pelo microfone ──
    if (btnAudio) {
        if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder)) {
            btnAudio.style.display = 'none';
        } else {
            (function(){
                var gravando = false;
                var mediaRecorder = null;
                var streamAtual = null;
                var chunksGravacao = [];
                var timerGravacao = null;
                var inicioGravacao = 0;
                var textoOriginalBtnAudio = btnAudio.innerHTML;

                function formatarTempo(segundos){
                    var m = Math.floor(segundos / 60);
                    var s = segundos % 60;
                    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                }

                function pararStream(){
                    if (streamAtual) {
                        streamAtual.getTracks().forEach(function(t){ t.stop(); });
                        streamAtual = null;
                    }
                }

                function extensaoPorMime(mime){
                    if (!mime) return 'weba';
                    if (mime.indexOf('ogg') !== -1) return 'oga';
                    if (mime.indexOf('mp4') !== -1) return 'm4a';
                    if (mime.indexOf('mpeg') !== -1) return 'mp3';
                    if (mime.indexOf('wav') !== -1) return 'wav';
                    return 'weba'; // audio/webm
                }

                function restaurarBotao(){
                    btnAudio.style.background = '#fafafa';
                    btnAudio.style.borderColor = '#aaa';
                    btnAudio.style.color = '#555';
                    btnAudio.innerHTML = textoOriginalBtnAudio;
                }

                function pararGravacao(){
                    if (!gravando || !mediaRecorder) return;
                    gravando = false;
                    if (timerGravacao) { clearInterval(timerGravacao); timerGravacao = null; }
                    try { mediaRecorder.stop(); } catch (e) { /* já parado */ }
                }
                pararGravacaoEmAndamento = pararGravacao;

                function iniciarGravacao(){
                    navigator.mediaDevices.getUserMedia({ audio: true }).then(function(stream){
                        streamAtual = stream;
                        chunksGravacao = [];
                        var opcoes = {};
                        if (window.MediaRecorder.isTypeSupported && window.MediaRecorder.isTypeSupported('audio/webm')) {
                            opcoes.mimeType = 'audio/webm';
                        } else if (window.MediaRecorder.isTypeSupported && window.MediaRecorder.isTypeSupported('audio/mp4')) {
                            opcoes.mimeType = 'audio/mp4';
                        }
                        try {
                            mediaRecorder = new MediaRecorder(stream, opcoes);
                        } catch (e) {
                            mediaRecorder = new MediaRecorder(stream);
                        }
                        mediaRecorder.addEventListener('dataavailable', function(e){
                            if (e.data && e.data.size > 0) chunksGravacao.push(e.data);
                        });
                        mediaRecorder.addEventListener('stop', function(){
                            pararStream();
                            var mimeFinal = mediaRecorder.mimeType || 'audio/webm';
                            var blob = new Blob(chunksGravacao, { type: mimeFinal });
                            chunksGravacao = [];
                            restaurarBotao();
                            if (!blob.size) return;
                            var arquivo = new File([blob], 'gravacao-' + Date.now() + '.' + extensaoPorMime(mimeFinal), { type: mimeFinal });
                            if (tentarAdicionarArquivo(arquivo)) { renderizarImagens(); }
                        });
                        mediaRecorder.start();
                        gravando = true;
                        inicioGravacao = Date.now();
                        btnAudio.style.background = '#e74c3c';
                        btnAudio.style.borderColor = '#e74c3c';
                        btnAudio.style.color = '#fff';
                        timerGravacao = setInterval(function(){
                            var decorridos = Math.floor((Date.now() - inicioGravacao) / 1000);
                            btnAudio.innerHTML = '<i class="fa fa-stop"></i> Parar (' + formatarTempo(decorridos) + ')';
                            if (decorridos >= MAX_SEGUNDOS_GRAVACAO) { pararGravacao(); }
                        }, 500);
                    }).catch(function(){
                        alert('Não foi possível acessar o microfone. Verifique as permissões do navegador.');
                    });
                }

                btnAudio.addEventListener('click', function(){
                    if (arquivos.length >= MAX_ARQUIVOS && !gravando) { alert('Máximo de ' + MAX_ARQUIVOS + ' anexos por chamado.'); return; }
                    if (gravando) { pararGravacao(); } else { iniciarGravacao(); }
                });
            })();
        }
    }

    function renderizarImagens(){
        listaImg.innerHTML = '';
        arquivos.forEach(function(arquivo, indice){
            var categoria = categoriaDoArquivo(arquivo);
            var item = document.createElement('div');
            item.style.cssText = 'position:relative;width:72px;height:72px;border-radius:6px;overflow:hidden;border:1px solid #ddd;background:#f5f5f5;';

            if (categoria === 'imagem') {
                var img = document.createElement('img');
                img.src = URL.createObjectURL(arquivo);
                img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';
                item.appendChild(img);
            } else {
                var iconePorCategoria = { video: 'fa-file-video-o', audio: 'fa-file-audio-o' };
                var icone = document.createElement('div');
                icone.style.cssText = 'width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:4px;box-sizing:border-box;color:#555;';
                icone.innerHTML = '<i class="fa ' + (iconePorCategoria[categoria] || 'fa-file-o') + '" style="font-size:22px;"></i>' +
                    '<span style="font-size:9px;word-break:break-all;margin-top:4px;">' + arquivo.name + '</span>';
                item.appendChild(icone);
            }

            var remover = document.createElement('button');
            remover.type = 'button';
            remover.innerHTML = '&times;';
            remover.title = 'Remover anexo';
            remover.style.cssText = 'position:absolute;top:2px;right:2px;width:20px;height:20px;border:none;border-radius:50%;background:rgba(0,0,0,.6);color:#fff;font-size:12px;line-height:1;cursor:pointer;';
            remover.addEventListener('click', function(){
                var imgEl = item.querySelector('img');
                if (imgEl) { URL.revokeObjectURL(imgEl.src); }
                arquivos.splice(indice, 1);
                renderizarImagens();
            });
            item.appendChild(remover);
            listaImg.appendChild(item);
        });
    }

    // ── Envio ──
    btnEnviar.addEventListener('click', function(){
        if (enviando) return;
        var descricao = txtDesc.value.trim();
        if (descricao.length < 5) { alert('Descreva o problema (mínimo 5 caracteres).'); txtDesc.focus(); return; }
        if (tipoObrigatorio && !campoTipo.value) { alert('Selecione o tipo do chamado.'); campoTipo.focus(); return; }

        enviando = true;
        btnEnviar.disabled = true;
        btnEnviar.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Enviando...';

        var formData = new FormData();
        formData.append('descricao', descricao);
        formData.append('pagina_url', urlPagina);
        if (campoTipo && campoTipo.value) { formData.append('tipo_id', campoTipo.value); }
        arquivos.forEach(function(arquivo){ formData.append('anexos', arquivo); });

        fetch(cfg.endpoint, {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + cfg.token },
            body: formData
        }).then(function(resposta){
            return resposta.json().then(function(json){
                return { status: resposta.status, json: json };
            });
        }).then(function(res){
            if (res.json && res.json.ok) {
                var imgs = listaImg.querySelectorAll('img');
                imgs.forEach(function(i){ URL.revokeObjectURL(i.src); });
                arquivos = [];
                renderizarImagens();
                txtDesc.value = '';
                contador.textContent = '0/2000';
                if (campoTipo) { campoTipo.value = ''; }
                fechar();
                var msg = 'Chamado nº ' + res.json.ticket_id + ' aberto com sucesso. A equipe TechPS irá analisar.';
                if (window.Swal) {
                    Swal.fire({ icon: 'success', title: 'Chamado enviado', text: msg, confirmButtonColor: '#337ab7' });
                } else {
                    alert(msg);
                }
            } else {
                var erro = (res.json && res.json.msg) ? res.json.msg : 'Falha ao enviar o chamado. Tente novamente.';
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Erro', text: erro, confirmButtonColor: '#337ab7' });
                } else {
                    alert(erro);
                }
            }
        }).catch(function(){
            if (window.Swal) {
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Sem comunicação com o servidor de suporte. Tente novamente.', confirmButtonColor: '#337ab7' });
            } else {
                alert('Sem comunicação com o servidor de suporte. Tente novamente.');
            }
        }).finally(function(){
            enviando = false;
            btnEnviar.disabled = false;
            btnEnviar.innerHTML = '<i class="fa fa-paper-plane"></i> Enviar chamado';
        });
    });
})();
</script>
<!-- ══ FIM WIDGET SUPORTE ══════════════════════════════════════════════ -->
