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

    // Token de curta duração (5 min) — assinado com chave derivada por empresa.
    $__supExp    = time() + 300;
    $__supJson   = json_encode([
        "empresa"      => $__supEmpresa,
        "empresa_nome" => $__supEmpNome,
        "uid"          => $__supUid,
        "ulogin"       => $__supLogin,
        "unome"        => $__supNome,
        "user_email"   => $__supEmail,
        "exp"          => $__supExp,
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
        <p style="margin:0 0 16px;font-size:12px;color:#888;">Descreva o problema para a equipe TechPS. Empresa e usuário são preenchidos automaticamente.</p>

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

        <div id="suporte-campo-setor-wrap" style="margin-bottom:10px;display:none;">
            <label style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:3px;">Setor <span style="color:#e74c3c;">*</span></label>
            <select id="suporte-campo-setor" style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;color:#333;box-sizing:border-box;">
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
            <label style="display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:3px;">Anexos (opcional, até 6 — imagem/documento até 5MB, no máx. 1 vídeo até 25MB)</label>
            <input type="file" id="suporte-campo-imagens" accept="image/*,video/mp4,video/quicktime,video/webm,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv" multiple style="display:none;" />
            <div style="display:flex;gap:8px;">
                <button type="button" id="suporte-botao-anexar" style="flex:1;border:1px dashed #aaa;background:#fafafa;color:#555;border-radius:4px;padding:10px;cursor:pointer;font-size:13px;">
                    <i class="fa fa-paperclip"></i> Anexar arquivo
                </button>
                <button type="button" id="suporte-botao-print" style="flex:1;border:1px dashed #aaa;background:#fafafa;color:#555;border-radius:4px;padding:10px;cursor:pointer;font-size:13px;">
                    <i class="fa fa-desktop"></i> Tirar print da tela
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
        setoresEndpoint: <?= json_encode($__supApiUrl . "/suporte/setores") ?>,
        token:    <?= json_encode($__supToken) ?>,
        empresa:  <?= json_encode($__supEmpNome) ?>,
        usuario:  <?= json_encode($__supNome . " (" . $__supLogin . ")") ?>
    };

    var MAX_ARQUIVOS      = 6;
    var MAX_VIDEOS        = 1;
    var MAX_BYTES_PADRAO  = 5 * 1024 * 1024;
    var MAX_BYTES_VIDEO   = 25 * 1024 * 1024;
    var EXT_IMAGEM    = ['jpg','jpeg','png','webp','gif'];
    var EXT_VIDEO     = ['mp4','mov','webm'];
    var EXT_DOCUMENTO = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv'];

    var arquivos     = [];
    var urlPagina    = "";
    var enviando     = false;
    var setorObrigatorio = false;

    var btnAbrir     = document.getElementById('suporte-widget-btn');
    var modal        = document.getElementById('suporte-widget-modal');
    var btnFechar    = document.getElementById('suporte-widget-fechar');
    var btnCancelar  = document.getElementById('suporte-botao-cancelar');
    var btnEnviar    = document.getElementById('suporte-botao-enviar');
    var btnAnexar    = document.getElementById('suporte-botao-anexar');
    var btnPrint     = document.getElementById('suporte-botao-print');
    var campoImg     = document.getElementById('suporte-campo-imagens');
    var txtDesc      = document.getElementById('suporte-campo-descricao');
    var contador     = document.getElementById('suporte-descricao-contador');
    var listaImg     = document.getElementById('suporte-lista-imagens');
    var setorWrap    = document.getElementById('suporte-campo-setor-wrap');
    var campoSetor   = document.getElementById('suporte-campo-setor');

    if (!btnAbrir || !modal) return;

    function categoriaDoArquivo(arquivo){
        var nome = (arquivo.name || '').toLowerCase();
        var ext = nome.indexOf('.') >= 0 ? nome.split('.').pop() : '';
        if (EXT_IMAGEM.indexOf(ext) !== -1) return 'imagem';
        if (EXT_VIDEO.indexOf(ext) !== -1) return 'video';
        if (EXT_DOCUMENTO.indexOf(ext) !== -1) return 'documento';
        return null;
    }

    function totalVideosAtual(){
        return arquivos.filter(function(a){ return categoriaDoArquivo(a) === 'video'; }).length;
    }

    function tentarAdicionarArquivo(arquivo){
        if (arquivos.length >= MAX_ARQUIVOS) { alert('Máximo de ' + MAX_ARQUIVOS + ' anexos por chamado.'); return false; }
        var categoria = categoriaDoArquivo(arquivo);
        if (!categoria) { alert('"' + arquivo.name + '" não é um tipo de arquivo permitido.'); return false; }
        if (categoria === 'video') {
            if (totalVideosAtual() >= MAX_VIDEOS) { alert('Permitido no máximo ' + MAX_VIDEOS + ' vídeo por chamado.'); return false; }
            if (arquivo.size > MAX_BYTES_VIDEO) { alert('"' + arquivo.name + '" excede 25MB.'); return false; }
        } else {
            if (arquivo.size > MAX_BYTES_PADRAO) { alert('"' + arquivo.name + '" excede 5MB.'); return false; }
        }
        arquivos.push(arquivo);
        return true;
    }

    // ── Carrega os setores disponíveis (marcados em /demo) ──
    function carregarSetores(){
        if (!campoSetor || !setorWrap) return;
        fetch(cfg.setoresEndpoint, { headers: { 'Authorization': 'Bearer ' + cfg.token } })
            .then(function(resposta){ return resposta.json(); })
            .then(function(json){
                var setores = (json && json.ok && Array.isArray(json.setores)) ? json.setores : [];
                setorObrigatorio = setores.length > 0;
                if (!setorObrigatorio) { setorWrap.style.display = 'none'; return; }
                campoSetor.innerHTML = '<option value="">Selecione...</option>';
                setores.forEach(function(s){
                    var opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.nome;
                    campoSetor.appendChild(opt);
                });
                setorWrap.style.display = '';
            })
            .catch(function(){ /* silencioso: se a API de setores falhar, não bloqueia o chamado */ });
    }

    // ── Abrir: captura a URL exata no momento do clique ──
    btnAbrir.addEventListener('click', function(){
        urlPagina = window.location.href;
        document.getElementById('suporte-campo-empresa').value  = cfg.empresa;
        document.getElementById('suporte-campo-usuario').value = cfg.usuario;
        document.getElementById('suporte-campo-pagina').value  = urlPagina;
        modal.style.display = 'flex';
        txtDesc.focus();
        carregarSetores();
    });

    function fechar(){
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
        imgTela.style.cssText = 'max-width:90vw;max-height:70vh;display:block;user-select:none;-webkit-user-drag:none;';
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

        palco.addEventListener('mousedown', iniciarSelecao);
        window.addEventListener('mousemove', atualizarSelecao);
        window.addEventListener('mouseup', finalizarSelecao);
        palco.addEventListener('touchstart', iniciarSelecao, { passive: false });
        window.addEventListener('touchmove', atualizarSelecao, { passive: false });
        window.addEventListener('touchend', finalizarSelecao);

        function mostrarPreview(canvasRecorte){
            limparEventos();
            palco.innerHTML = '';
            dica.textContent = 'Confira o recorte selecionado';
            canvasRecorte.style.cssText = 'max-width:90vw;max-height:70vh;display:block;border:1px solid #fff;';
            palco.appendChild(canvasRecorte);

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

    // ── Print da tela: fecha o widget, tira print da própria página (sem picker do navegador) e deixa recortar ──
    var html2canvasCarregando = null;
    function garantirHtml2Canvas(){
        if (typeof window.html2canvas === 'function') return Promise.resolve();
        if (html2canvasCarregando) return html2canvasCarregando;
        html2canvasCarregando = new Promise(function(resolve, reject){
            var script = document.createElement('script');
            script.src = 'https://html2canvas.hertzen.com/dist/html2canvas.min.js';
            script.onload = function(){ resolve(); };
            script.onerror = function(){ html2canvasCarregando = null; reject(new Error('Falha ao carregar html2canvas')); };
            document.head.appendChild(script);
        });
        return html2canvasCarregando;
    }

    if (btnPrint) {
        btnPrint.addEventListener('click', function(){
            garantirHtml2Canvas().then(function(){
                // Esconde o widget (modal + botão flutuante) para ele não aparecer no print.
                modal.style.display = 'none';
                btnAbrir.style.display = 'none';

                function restaurarWidget(){
                    modal.style.display = 'flex';
                    btnAbrir.style.display = 'flex';
                }

                // 2 frames de espera para garantir que o widget já sumiu da tela antes do print.
                requestAnimationFrame(function(){
                    requestAnimationFrame(function(){
                        window.html2canvas(document.body, {
                            useCORS: true,
                            allowTaint: false,
                            x: window.scrollX,
                            y: window.scrollY,
                            width: window.innerWidth,
                            height: window.innerHeight
                        }).then(function(canvas){
                            restaurarWidget();
                            abrirRecorteTela(canvas, function(blob){
                                var arquivo = new File([blob], 'print-' + Date.now() + '.png', { type: 'image/png' });
                                if (tentarAdicionarArquivo(arquivo)) { renderizarImagens(); }
                            });
                        }).catch(function(){
                            restaurarWidget();
                            alert('Não foi possível capturar a tela.');
                        });
                    });
                });
            }).catch(function(){
                alert('Não foi possível carregar o recurso de print. Verifique sua conexão.');
            });
        });
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
                var icone = document.createElement('div');
                icone.style.cssText = 'width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:4px;box-sizing:border-box;color:#555;';
                icone.innerHTML = '<i class="fa ' + (categoria === 'video' ? 'fa-file-video-o' : 'fa-file-o') + '" style="font-size:22px;"></i>' +
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
        if (setorObrigatorio && !campoSetor.value) { alert('Selecione o setor do chamado.'); campoSetor.focus(); return; }

        enviando = true;
        btnEnviar.disabled = true;
        btnEnviar.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Enviando...';

        var formData = new FormData();
        formData.append('descricao', descricao);
        formData.append('pagina_url', urlPagina);
        if (campoSetor && campoSetor.value) { formData.append('setor_id', campoSetor.value); }
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
                if (campoSetor) { campoSetor.value = ''; }
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
