/* Módulo WhatsApp — chat/inbox */
(function () {
    'use strict';

    var cfg = window.WA_CONFIG || {};
    var ultimoId = parseInt(cfg.ultimoId || 0, 10);
    var conversaId = parseInt(cfg.conversaId || 0, 10);
    var filtroAtual = 'todas';
    var buscaAtual = '';
    var timerMensagens = null;
    var timerConversas = null;
    var buscaContatoTimer = null;
    var arquivoSelecionado = null;
    var arquivoPreviewUrl = null;
    var carregandoMensagens = false;
    var mensagensRenderizadas = Object.create(null);
    var enviando = false;
    var gravando = false;
    var mediaRecorder = null;
    var gravacaoStream = null;
    var gravacaoChunks = [];
    var gravacaoTimer = null;
    var gravacaoInicio = 0;
    var gravacaoMime = '';
    var cameraStream = null;
    var cameraAberta = false;
    var prontas = [];
    var prontasCarregadas = false;
    var prontasTituloAtual = '';
    var prontasMenuAberto = false;

    function apiUrl(params) {
        var url = cfg.api;
        var qs = [];
        Object.keys(params || {}).forEach(function (k) {
            var key = (k === 'acao') ? 'wa_acao' : k;
            qs.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[k]));
        });
        return url + (qs.length ? (url.indexOf('?') >= 0 ? '&' : '?') + qs.join('&') : '');
    }

    function apiGet(params) {
        return fetch(apiUrl(params), { headers: { 'X-CSRF-Token': cfg.csrf }, cache: 'no-store' })
            .then(function (r) { return r.json(); });
    }

    function apiPost(params, data) {
        var body = new URLSearchParams();
        body.append('csrf', cfg.csrf);
        Object.keys(data || {}).forEach(function (k) {
            var v = data[k];
            if (Array.isArray(v)) {
                v.forEach(function (item) { body.append(k + '[]', item); });
            } else {
                body.append(k, v);
            }
        });
        return fetch(apiUrl(params), {
            method: 'POST',
            headers: { 'X-CSRF-Token': cfg.csrf, 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    function apiPostForm(params, formData) {
        formData.append('csrf', cfg.csrf);
        return fetch(apiUrl(params), {
            method: 'POST',
            headers: { 'X-CSRF-Token': cfg.csrf },
            body: formData
        }).then(function (r) { return r.json(); });
    }

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function statusIcon(status) {
        switch (status) {
            case 'lido': return '<i class="fa fa-check-double" style="color:#34b7f1;" title="Lido"></i>';
            case 'entregue': return '<i class="fa fa-check-double" style="color:#8a9aa8;" title="Entregue"></i>';
            case 'enviado': return '<i class="fa fa-check" style="color:#8a9aa8;" title="Enviado"></i>';
            case 'falhou': return '<i class="fa fa-exclamation-circle" style="color:#e74c3c;" title="Falhou"></i>';
            default: return '<i class="fa fa-clock-o" style="color:#b0b7bf;" title="Pendente"></i>';
        }
    }

    function formatarData(valor) {
        if (!valor) return '';
        var d = new Date(String(valor).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        function p(n) { return String(n).padStart(2, '0'); }
        return p(d.getDate()) + '/' + p(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    }

    function baseName(valor) {
        return String(valor || '').split(/[\\/]/).pop();
    }

    function tipoMidia(mime) {
        mime = String(mime || '').toLowerCase();
        if (mime.indexOf('image/') === 0) return 'imagem';
        if (mime.indexOf('audio/') === 0) return 'audio';
        if (mime.indexOf('video/') === 0) return 'video';
        return 'documento';
    }

    function formatarTamanho(bytes) {
        bytes = parseInt(bytes || 0, 10);
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1024 / 1024).toFixed(1) + ' MB';
    }

    function renderMidia(m) {
        var tipo = m.msg_tx_tipo || 'documento';
        var arquivo = baseName(m.msg_tx_arquivo || '');
        var nome = m.anexo_nome || arquivo || 'arquivo';
        var url = cfg.urlArquivo + '?' + (m.anexo_id ? ('anexo=' + parseInt(m.anexo_id, 10)) : ('msg=' + parseInt(m.msg_nb_id, 10)));
        var legenda = m.msg_tx_conteudo ? '<div class="wa-midia-caption">' + esc(m.msg_tx_conteudo).replace(/\n/g, '<br>') + '</div>' : '';
        if (!arquivo) {
            return '<div class="wa-midia-indisponivel"><i class="fa fa-paperclip"></i> Mídia indisponível</div>' + legenda;
        }
        if (tipo === 'imagem') {
            return '<a href="' + esc(url) + '" target="_blank" rel="noopener" class="wa-midia-link">' +
                '<img src="' + esc(url) + '" alt="' + esc(nome) + '" class="wa-midia-img" loading="lazy">' +
                '</a>' + legenda;
        }
        if (tipo === 'audio') {
            return '<audio class="wa-midia-audio" controls preload="metadata" src="' + esc(url) + '"></audio>' + legenda;
        }
        if (tipo === 'video') {
            return '<video class="wa-midia-video" controls preload="metadata" src="' + esc(url) + '"></video>' + legenda;
        }
        return '<a class="wa-midia-doc" href="' + esc(url + '&download=1') + '" target="_blank" rel="noopener">' +
            '<i class="fa fa-file-o"></i><span>' + esc(nome) + '</span><i class="fa fa-download"></i>' +
            '</a>' + legenda;
    }

    function renderMensagem(m) {
        var direcao = (m.msg_tx_direcao === 'out') ? 'out' : 'in';
        var tipo = m.msg_tx_tipo || 'texto';
        var conteudo = m.msg_tx_conteudo || '';
        var corpo = esc(conteudo).replace(/\n/g, '<br>');
        if (['imagem', 'audio', 'video', 'documento'].indexOf(tipo) >= 0) {
            corpo = renderMidia(m);
        } else if (tipo !== 'texto' && tipo !== 'template' && tipo !== 'sistema') {
            corpo = '<em>' + esc(conteudo || ('[' + tipo + ']')) + '</em>';
        }
        var status = direcao === 'out' ? statusIcon(m.msg_tx_status || 'pendente') : '';
        var autor = direcao === 'out' ? esc(m.usuario_nome || 'Você') : 'Contato';
        return '<div class="wa-msg wa-msg-' + direcao + '" data-id="' + parseInt(m.msg_nb_id || 0, 10) + '">' +
            '<div class="wa-msg-meta"><strong>' + autor + '</strong><span>' + esc(m.msg_dt_criacao_fmt || formatarData(m.msg_dt_criacao)) + '</span></div>' +
            '<div class="wa-msg-body">' + corpo + '</div>' +
            (status ? '<div class="wa-msg-status">' + status + '</div>' : '') +
            '</div>';
    }

    function renderConversa(c) {
        var naoLidas = parseInt(c.conv_nb_nao_lidas || 0, 10);
        var ativa = parseInt(c.conv_nb_id || 0, 10) === conversaId;
        var ultima = c.ultima_msg || c.conv_tx_ultima_mensagem || 'Sem mensagens';
        var data = c.ultima_data_fmt || formatarData(c.ultima_data || c.conv_dt_ultima_atividade);
        if (data && !c.ultima_data_fmt) data = data.substring(0, 5);
        var inicial = esc((c.cont_tx_nome || '?').substring(0, 1));
        return '<a class="wa-conversa ' + (ativa ? 'active' : '') + '" href="' + esc(cfg.urlIndex) + '?contato=' + parseInt(c.conv_nb_contato || 0, 10) + '">' +
            '<div class="wa-avatar">' + inicial + '</div>' +
            '<div class="wa-conversa-info">' +
                '<div class="wa-conversa-top"><strong>' + esc(c.cont_tx_nome || '') + '</strong><span>' + esc(data) + '</span></div>' +
                '<div class="wa-conversa-bottom"><span class="wa-preview">' + esc(ultima) + '</span>' +
                    (naoLidas > 0 ? '<span class="wa-badge">' + naoLidas + '</span>' : '') +
                '</div>' +
            '</div>' +
        '</a>';
    }

    function atualizarTotalNaoLidas(conversas) {
        var badge = document.getElementById('waTotalNaoLidas');
        if (!badge) return;
        var total = 0;
        (conversas || []).forEach(function (c) {
            total += parseInt(c.conv_nb_nao_lidas || 0, 10);
        });
        if (total > 0) {
            badge.textContent = total > 99 ? '99+' : String(total);
            badge.style.display = 'inline-grid';
        } else {
            badge.style.display = 'none';
        }
    }

    function carregarConversas() {
        var lista = document.getElementById('waListaConversas');
        if (!lista) return;
        apiGet({ acao: 'conversas', busca: buscaAtual, filtro: filtroAtual, origem: cfg.origemFiltro || 'todas' })
            .then(function (data) {
                if (!data.success) return;
                var conversas = data.conversas || [];
                atualizarTotalNaoLidas(conversas);
                if (!conversas.length) {
                    lista.innerHTML = '<div class="wa-vazio-lista"><i class="fa fa-comments-o"></i><p>Nenhuma conversa.</p><small>Ajuste a busca ou inicie um novo chat.</small></div>';
                    return;
                }
                lista.innerHTML = conversas.map(renderConversa).join('');
            })
            .catch(function () {});
    }

    function carregarMensagens() {
        if (!conversaId || carregandoMensagens) return;
        var container = document.getElementById('waMensagens');
        if (!container) return;
        carregandoMensagens = true;
        var finalizar = function () { carregandoMensagens = false; };

        apiGet({ acao: 'mensagens', conversa: conversaId, apos_id: ultimoId })
            .then(function (data) {
                if (!data.success || !data.mensagens || !data.mensagens.length) {
                    finalizar();
                    return;
                }
                var vazio = container.querySelector('.wa-conversa-vazia');
                if (vazio) vazio.remove();
                var estavaPerto = (container.scrollHeight - container.scrollTop - container.clientHeight) < 120;
                data.mensagens.forEach(function (m) {
                    var id = parseInt(m.msg_nb_id || 0, 10);
                    if (id > 0 && (mensagensRenderizadas[id] || container.querySelector('.wa-msg[data-id="' + id + '"]'))) {
                        mensagensRenderizadas[id] = true;
                        ultimoId = Math.max(ultimoId, id);
                        return;
                    }
                    container.insertAdjacentHTML('beforeend', renderMensagem(m));
                    if (id > 0) mensagensRenderizadas[id] = true;
                    ultimoId = Math.max(ultimoId, id);
                });
                if (estavaPerto) container.scrollTop = container.scrollHeight;
                finalizar();
                if (document.hasFocus()) {
                    apiPost({ acao: 'marcar_lida' }, { conversa: conversaId })
                        .then(function () { carregarConversas(); })
                        .catch(function () { carregarConversas(); });
                } else {
                    carregarConversas();
                }
            })
            .catch(function () { finalizar(); });
    }

    function atualizarStatus() {
        if (!conversaId) return;
        apiGet({ acao: 'status_mensagens', conversa: conversaId }).then(function (data) {
            if (!data.success) return;
            (data.mensagens || []).forEach(function (m) {
                var bolha = document.querySelector('.wa-msg[data-id="' + m.id + '"] .wa-msg-status');
                if (bolha) bolha.innerHTML = statusIcon(m.status || 'pendente');
            });
        }).catch(function () {});
    }

    function mostrarErro(mensagem) {
        if (window.Swal) {
            Swal.fire({ icon: 'error', title: 'Não foi possível enviar', text: mensagem || 'Tente novamente.' });
        } else {
            alert(mensagem || 'Não foi possível enviar.');
        }
    }

    function limparArquivo() {
        var input = document.getElementById('waArquivo');
        var info = document.getElementById('waArquivoInfo');
        var preview = document.getElementById('waArquivoPreview');
        if (input) input.value = '';
        if (info) info.style.display = 'none';
        if (preview) {
            preview.style.display = 'none';
            preview.removeAttribute('src');
        }
        if (arquivoPreviewUrl) {
            URL.revokeObjectURL(arquivoPreviewUrl);
            arquivoPreviewUrl = null;
        }
        arquivoSelecionado = null;
    }

    function selecionarArquivo(file) {
        if (gravando) return;
        if (!file) {
            limparArquivo();
            return;
        }
        var tipo = tipoMidia(file.type);
        var limites = cfg.limitesMidia || {};
        var limite = parseInt(limites[tipo] || cfg.maxMidia || 0, 10);
        if (limite > 0 && file.size > limite) {
            mostrarErro('O arquivo tem ' + formatarTamanho(file.size) + ' e o limite para ' + tipo + ' é ' + formatarTamanho(limite) + '.');
            limparArquivo();
            return;
        }

        arquivoSelecionado = file;
        var nome = document.getElementById('waArquivoNome');
        var tamanho = document.getElementById('waArquivoTamanho');
        var info = document.getElementById('waArquivoInfo');
        var preview = document.getElementById('waArquivoPreview');
        if (nome) nome.textContent = file.name;
        if (tamanho) tamanho.textContent = formatarTamanho(file.size) + ' · ' + (file.type || 'arquivo');
        if (info) info.style.display = 'flex';

        if (preview) {
            if (arquivoPreviewUrl) {
                URL.revokeObjectURL(arquivoPreviewUrl);
                arquivoPreviewUrl = null;
            }
            if (tipo === 'imagem' && window.URL && URL.createObjectURL) {
                arquivoPreviewUrl = URL.createObjectURL(file);
                preview.src = arquivoPreviewUrl;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
                preview.removeAttribute('src');
            }
        }
    }

    function formatarDuracao(segundos) {
        var s = Math.max(0, parseInt(segundos || 0, 10));
        var m = Math.floor(s / 60);
        var r = s % 60;
        return String(m).padStart(2, '0') + ':' + String(r).padStart(2, '0');
    }

    function pararTimerGravacao() {
        if (gravacaoTimer) {
            clearInterval(gravacaoTimer);
            gravacaoTimer = null;
        }
    }

    function atualizarInterfaceGravacao() {
        var info = document.getElementById('waGravacaoInfo');
        var botao = document.getElementById('waGravarAudio');
        var anexar = document.getElementById('waAnexar');
        var camera = document.getElementById('waCameraBtn');
        var enviar = document.getElementById('waEnviar');
        if (info) info.style.display = gravando ? 'flex' : 'none';
        if (anexar) anexar.disabled = gravando;
        if (camera) camera.disabled = gravando;
        if (enviar) enviar.disabled = gravando;
        if (botao) {
            botao.classList.toggle('gravando', gravando);
            botao.title = gravando ? 'Parar gravação' : 'Gravar áudio';
            botao.setAttribute('aria-label', botao.title);
        }
    }

    function liberarMicrofone() {
        if (gravacaoStream) {
            gravacaoStream.getTracks().forEach(function (track) { track.stop(); });
            gravacaoStream = null;
        }
        pararTimerGravacao();
        mediaRecorder = null;
        gravacaoChunks = [];
        gravacaoMime = '';
        gravando = false;
        atualizarInterfaceGravacao();
    }

    function cancelarGravacao() {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            try { mediaRecorder.stop(); } catch (e) {}
        }
        liberarMicrofone();
    }

    function pararGravacao(anexar) {
        if (!gravando || !mediaRecorder) return;
        var recorder = mediaRecorder;
        var mime = gravacaoMime;
        var chunks = gravacaoChunks;
        gravando = false;
        pararTimerGravacao();

        recorder.onstop = function () {
            if (gravacaoStream) {
                gravacaoStream.getTracks().forEach(function (track) { track.stop(); });
                gravacaoStream = null;
            }
            if (anexar && chunks.length) {
                var blob = new Blob(chunks, { type: mime });
                var baseMime = String(mime || '').split(';')[0];
                var ext = baseMime.indexOf('mp4') >= 0 ? 'm4a' : (baseMime.indexOf('ogg') >= 0 ? 'ogg' : 'webm');
                var nome = 'audio-gravado-' + new Date().toISOString().replace(/[:.]/g, '-').substring(0, 19) + '.' + ext;
                if (blob.size > 0) {
                    var arquivo = new File([blob], nome, { type: baseMime || 'audio/mp4' });
                    selecionarArquivo(arquivo);
                } else {
                    mostrarErro('A gravação ficou vazia. Tente novamente.');
                }
            }
            mediaRecorder = null;
            gravacaoChunks = [];
            gravacaoMime = '';
            atualizarInterfaceGravacao();
        };

        try {
            if (recorder.state !== 'inactive') recorder.stop();
            else recorder.onstop();
        } catch (e) {
            liberarMicrofone();
            mostrarErro('Não foi possível finalizar a gravação.');
        }
    }

    function iniciarGravacao() {
        if (gravando || enviando || mediaRecorder) return;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
            mostrarErro('Este navegador não suporta gravação de áudio. Use o botão de anexo.');
            return;
        }
        if (arquivoSelecionado) limparArquivo();
        var tipos = [
            'audio/mp4;codecs=mp4a.40.2',
            'audio/mp4',
            'audio/ogg;codecs=opus',
            'audio/webm;codecs=opus'
        ];

        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            gravacaoStream = stream;
            gravacaoChunks = [];
            var mime = '';
            for (var i = 0; i < tipos.length; i++) {
                try {
                    if (!MediaRecorder.isTypeSupported(tipos[i])) continue;
                    mediaRecorder = new MediaRecorder(stream, { mimeType: tipos[i], audioBitsPerSecond: 128000 });
                    mime = tipos[i];
                    break;
                } catch (e) {
                    mediaRecorder = null;
                }
            }
            if (!mediaRecorder) {
                try {
                    mediaRecorder = new MediaRecorder(stream);
                    mime = mediaRecorder.mimeType || 'audio/webm';
                } catch (e) {
                    liberarMicrofone();
                    mostrarErro('Não foi possível iniciar o gravador neste navegador.');
                    return;
                }
            }
            gravacaoMime = mime;
            mediaRecorder.ondataavailable = function (e) {
                if (e.data && e.data.size > 0) gravacaoChunks.push(e.data);
            };
            mediaRecorder.onerror = function () {
                cancelarGravacao();
                mostrarErro('Ocorreu um erro durante a gravação.');
            };
            try {
                mediaRecorder.start(250);
            } catch (e) {
                liberarMicrofone();
                mostrarErro('Não foi possível iniciar a gravação.');
                return;
            }
            gravando = true;
            gravacaoInicio = Date.now();
            atualizarInterfaceGravacao();
            var tempo = document.getElementById('waGravacaoTempo');
            if (tempo) tempo.textContent = '00:00';
            pararTimerGravacao();
            gravacaoTimer = setInterval(function () {
                var el = document.getElementById('waGravacaoTempo');
                if (el) el.textContent = formatarDuracao((Date.now() - gravacaoInicio) / 1000);
            }, 500);
        }).catch(function () {
            mostrarErro('Não foi possível acessar o microfone. Verifique a permissão do navegador.');
        });
    }

    function fecharCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (track) { track.stop(); });
            cameraStream = null;
        }
        var video = document.getElementById('waCameraVideo');
        if (video) video.srcObject = null;
        var modal = document.getElementById('waCameraModal');
        if (modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
        cameraAberta = false;
    }

    function abrirCamera() {
        if (enviando || gravando) return;
        var input = document.getElementById('waCamera');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            if (input) input.click();
            return;
        }
        fecharCamera();
        navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false
        }).then(function (stream) {
            cameraStream = stream;
            var video = document.getElementById('waCameraVideo');
            var modal = document.getElementById('waCameraModal');
            if (!video || !modal) {
                fecharCamera();
                return;
            }
            video.srcObject = stream;
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            cameraAberta = true;
            var play = video.play();
            if (play && play.catch) play.catch(function () {});
        }).catch(function () {
            // Sem permissão ou sem câmera: usa o seletor nativo do celular.
            if (input) input.click();
        });
    }

    function capturarFoto() {
        if (!cameraAberta || !cameraStream) return;
        var video = document.getElementById('waCameraVideo');
        if (!video || !video.videoWidth || !video.videoHeight) {
            mostrarErro('A câmera ainda não está pronta. Tente novamente.');
            return;
        }
        var largura = video.videoWidth;
        var altura = video.videoHeight;
        var maxLargura = 1600;
        if (largura > maxLargura) {
            altura = Math.round(altura * maxLargura / largura);
            largura = maxLargura;
        }
        var canvas = document.createElement('canvas');
        canvas.width = largura;
        canvas.height = altura;
        var ctx = canvas.getContext('2d');
        if (!ctx) {
            mostrarErro('Não foi possível capturar a foto.');
            return;
        }
        ctx.drawImage(video, 0, 0, largura, altura);
        var nome = 'foto-camera-' + new Date().toISOString().replace(/[:.]/g, '-').substring(0, 19) + '.jpg';

        var finalizar = function (blob) {
            if (!blob || blob.size <= 0) {
                mostrarErro('Não foi possível capturar a foto.');
                return;
            }
            var arquivo = new File([blob], nome, { type: 'image/jpeg' });
            selecionarArquivo(arquivo);
            fecharCamera();
        };

        if (canvas.toBlob) {
            canvas.toBlob(finalizar, 'image/jpeg', 0.9);
        } else {
            try {
                var data = canvas.toDataURL('image/jpeg', 0.9);
                var bin = atob(data.split(',')[1]);
                var bytes = new Uint8Array(bin.length);
                for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
                finalizar(new Blob([bytes], { type: 'image/jpeg' }));
            } catch (e) {
                mostrarErro('Não foi possível capturar a foto.');
            }
        }
    }

    function atualizarExportacaoModo() {
        var marcado = document.querySelector('input[name="modo"]:checked');
        var modo = marcado ? marcado.value : 'dias';
        var datas = modo === 'datas';
        var dias = document.getElementById('waExportDias');
        var inicio = document.getElementById('waExportInicio');
        var fim = document.getElementById('waExportFim');
        if (dias) {
            dias.disabled = datas;
            dias.required = !datas;
        }
        if (inicio) {
            inicio.disabled = !datas;
            inicio.required = datas;
        }
        if (fim) {
            fim.disabled = !datas;
            fim.required = datas;
        }
        var erro = document.getElementById('waExportErro');
        if (erro) erro.textContent = '';
    }

    function abrirModalExportar() {
        if (!conversaId) return;
        atualizarExportacaoModo();
        if (window.jQuery) {
            window.jQuery('#waModalExportar').modal('show');
        }
    }

    function carregarProntas() {
        if (prontasCarregadas) return Promise.resolve(prontas);
        return apiGet({ acao: 'mensagens_prontas' }).then(function (data) {
            prontas = data && data.success && Array.isArray(data.mensagens) ? data.mensagens : [];
            prontasCarregadas = true;
            return prontas;
        }).catch(function () {
            prontas = [];
            prontasCarregadas = true;
            return prontas;
        });
    }

    function fecharMenuProntas() {
        var menu = document.getElementById('waProntasMenu');
        if (menu) {
            menu.classList.remove('open');
            menu.innerHTML = '';
        }
        prontasMenuAberto = false;
        prontasTituloAtual = '';
    }

    function renderMenuProntas(titulo) {
        var menu = document.getElementById('waProntasMenu');
        if (!menu) return;
        prontasTituloAtual = titulo || '';
        prontasMenuAberto = true;
        menu.classList.add('open');

        if (!prontas.length) {
            menu.innerHTML = '<div class="wa-prontas-vazio">Nenhuma mensagem pronta ativa. Cadastre em CRM - TECHPS → Mensagens Prontas.</div>';
            return;
        }

        var html = '';
        if (!titulo) {
            var titulos = [];
            var vistos = {};
            prontas.forEach(function (p) {
                var t = String(p.mpr_tx_titulo || '');
                if (t !== '' && !vistos[t]) {
                    vistos[t] = true;
                    titulos.push(t);
                }
            });
            html += '<div class="wa-prontas-menu-head"><strong>Mensagens prontas</strong><span style="font-size:9px;color:#94a3b8;">' + titulos.length + ' título(s)</span></div>';
            titulos.forEach(function (t) {
                var qtd = prontas.filter(function (p) { return String(p.mpr_tx_titulo || '') === t; }).length;
                html += '<button type="button" class="wa-prontas-item" data-pronta-titulo="' + esc(t) + '">' +
                    '<strong>' + esc(t) + '</strong>' +
                    '<small>' + qtd + ' subtítulo(s)</small>' +
                '</button>';
            });
        } else {
            var itens = prontas.filter(function (p) { return String(p.mpr_tx_titulo || '') === titulo; });
            html += '<div class="wa-prontas-menu-head"><strong>' + esc(titulo) + '</strong><button type="button" data-pronta-voltar="1">← Títulos</button></div>';
            itens.forEach(function (p) {
                var corpo = String(p.mpr_tx_mensagem || '').replace(/\s+/g, ' ').trim();
                if (corpo.length > 90) corpo = corpo.substring(0, 90) + '...';
                html += '<button type="button" class="wa-prontas-item" data-pronta-id="' + parseInt(p.mpr_nb_id || 0, 10) + '">' +
                    '<strong>' + esc(p.mpr_tx_subtitulo || '') + '</strong>' +
                    '<small>' + esc(corpo) + '</small>' +
                '</button>';
            });
        }
        menu.innerHTML = html;
    }

    function abrirMenuProntas() {
        if (!conversaId || gravando || enviando) return;
        var texto = document.getElementById('waTexto');
        if (texto) {
            var pos = typeof texto.selectionStart === 'number' ? texto.selectionStart : texto.value.length;
            if (!/(^|\s)\/$/.test(texto.value.slice(0, pos))) return;
        }
        carregarProntas().then(function () {
            renderMenuProntas('');
        });
    }

    function personalizarPronta(texto) {
        var c = cfg.contato || {};
        var nome = String(c.nome || '');
        var primeiro = nome.split(/\s+/)[0] || nome;
        return String(texto || '')
            .replace(/\{\{nome\}\}/g, nome)
            .replace(/\{\{primeiro_nome\}\}/g, primeiro)
            .replace(/\{\{setor\}\}/g, c.setor || '')
            .replace(/\{\{cargo\}\}/g, c.cargo || '')
            .replace(/\{\{empresa\}\}/g, c.empresa || '')
            .replace(/\{\{telefone\}\}/g, c.telefone || '')
            .replace(/\{nome\}/g, nome)
            .replace(/\{primeiro_nome\}/g, primeiro)
            .replace(/\{setor\}/g, c.setor || '')
            .replace(/\{cargo\}/g, c.cargo || '')
            .replace(/\{empresa\}/g, c.empresa || '')
            .replace(/\{telefone\}/g, c.telefone || '');
    }

    function selecionarPronta(id) {
        var pronta = null;
        for (var i = 0; i < prontas.length; i++) {
            if (parseInt(prontas[i].mpr_nb_id || 0, 10) === id) {
                pronta = prontas[i];
                break;
            }
        }
        if (!pronta) return;
        var texto = document.getElementById('waTexto');
        if (!texto) return;
        var valor = texto.value;
        var pos = typeof texto.selectionStart === 'number' ? texto.selectionStart : valor.length;
        var antes = valor.slice(0, pos);
        var depois = valor.slice(pos);
        var barra = antes.lastIndexOf('/');
        var corpo = personalizarPronta(pronta.mpr_tx_mensagem);
        if (barra >= 0) {
            antes = antes.slice(0, barra);
        }
        texto.value = antes + corpo + depois;
        fecharMenuProntas();
        texto.focus();
        var novaPos = (antes + corpo).length;
        if (texto.setSelectionRange) texto.setSelectionRange(novaPos, novaPos);
    }

    function enviarMidia(file, legenda) {
        var texto = document.getElementById('waTexto');
        var botao = document.getElementById('waEnviar');
        var anexar = document.getElementById('waAnexar');
        var camera = document.getElementById('waCameraBtn');
        var microfone = document.getElementById('waGravarAudio');
        if (!file || !conversaId || !botao || enviando || gravando) return;

        enviando = true;
        var form = new FormData();
        form.append('conversa', conversaId);
        form.append('legenda', legenda || '');
        form.append('arquivo', file, file.name);

        botao.disabled = true;
        if (texto) texto.disabled = true;
        if (anexar) anexar.disabled = true;
        if (camera) camera.disabled = true;
        if (microfone) microfone.disabled = true;

        apiPostForm({ acao: 'enviar_midia' }, form)
            .then(function (data) {
                if (!data.success) {
                    mostrarErro(data.message || 'Tente novamente.');
                } else {
                    if (texto) texto.value = '';
                    limparArquivo();
                }
                enviando = false;
                if (texto) texto.disabled = false;
                botao.disabled = false;
                if (anexar) anexar.disabled = false;
                if (camera) camera.disabled = false;
                if (microfone) microfone.disabled = false;
                if (texto) texto.focus();
                carregarMensagens();
                carregarConversas();
            })
            .catch(function () {
                mostrarErro('Falha de comunicação ao enviar a mídia.');
                enviando = false;
                if (texto) texto.disabled = false;
                botao.disabled = false;
                if (anexar) anexar.disabled = false;
                if (camera) camera.disabled = false;
                if (microfone) microfone.disabled = false;
            });
    }

    function enviar() {
        var texto = document.getElementById('waTexto');
        var botao = document.getElementById('waEnviar');
        if (!texto || !botao || !conversaId || enviando || gravando) return;
        var mensagem = texto.value.trim();
        if (!mensagem && !arquivoSelecionado) return;
        if (mensagem === '/') {
            abrirMenuProntas();
            return;
        }
        if (arquivoSelecionado) {
            enviarMidia(arquivoSelecionado, mensagem);
            return;
        }
        enviando = true;
        botao.disabled = true;
        texto.disabled = true;
        apiPost({ acao: 'enviar' }, { conversa: conversaId, texto: mensagem })
            .then(function (data) {
                if (!data.success) {
                    mostrarErro(data.message || 'Tente novamente.');
                } else {
                    texto.value = '';
                }
                enviando = false;
                texto.disabled = false;
                botao.disabled = false;
                texto.focus();
                carregarMensagens();
            })
            .catch(function () {
                enviando = false;
                texto.disabled = false;
                botao.disabled = false;
            });
    }

    function abrirModalNovo() {
        var modal = window.jQuery ? window.jQuery('#waModalNovo') : null;
        if (modal && modal.modal) modal.modal('show');
    }

    function buscarContatos(termo) {
        var alvo = document.getElementById('waResultadosContato');
        if (!alvo) return;
        apiGet({ acao: 'buscar_contatos', termo: termo })
            .then(function (data) {
                if (!data.success) return;
                var contatos = data.contatos || [];
                if (!contatos.length) {
                    alvo.innerHTML = '<div class="wa-resultado-vazio">Nenhum contato encontrado. Cadastre o número abaixo.</div>';
                    return;
                }
                alvo.innerHTML = contatos.map(function (c) {
                    var inicial = esc((c.cont_tx_nome || '?').substring(0, 1));
                    var tel = c.cont_tx_e164 || c.cont_tx_telefone || '';
                    return '<div class="wa-resultado" data-contato="' + parseInt(c.cont_nb_id || 0, 10) + '">' +
                        '<div class="wa-avatar">' + inicial + '</div>' +
                        '<div class="wa-resultado-info"><strong>' + esc(c.cont_tx_nome || '') + '</strong><small>' + esc(tel) + '</small></div>' +
                    '</div>';
                }).join('');
            })
            .catch(function () {});
    }

    function criarContato() {
        var nome = (document.getElementById('waNovoNome') || {}).value || '';
        var telefone = (document.getElementById('waNovoTelefone') || {}).value || '';
        var erro = document.getElementById('waNovoErro');
        if (erro) erro.textContent = '';
        apiPost({ acao: 'novo_contato' }, { nome: nome, telefone: telefone })
            .then(function (data) {
                if (!data.success) {
                    if (erro) erro.textContent = data.message || 'Não foi possível criar o contato.';
                    return;
                }
                window.location.href = cfg.urlIndex + '?contato=' + parseInt(data.contato.cont_nb_id || 0, 10);
            })
            .catch(function () {
                if (erro) erro.textContent = 'Falha de comunicação.';
            });
    }

    function iniciarEventos() {
        var busca = document.getElementById('waBusca');
        if (busca) {
            var debounce = null;
            busca.addEventListener('input', function () {
                clearTimeout(debounce);
                debounce = setTimeout(function () {
                    buscaAtual = busca.value.trim();
                    carregarConversas();
                }, 300);
            });
        }

        var filtros = document.querySelectorAll('.wa-filtro');
        filtros.forEach(function (btn) {
            btn.addEventListener('click', function () {
                filtros.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                filtroAtual = btn.getAttribute('data-filtro') || 'todas';
                carregarConversas();
            });
        });

        var enviarBtn = document.getElementById('waEnviar');
        if (enviarBtn) enviarBtn.addEventListener('click', enviar);

        var texto = document.getElementById('waTexto');
        if (texto) {
            texto.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && prontasMenuAberto) {
                    e.preventDefault();
                    fecharMenuProntas();
                    return;
                }
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    enviar();
                }
            });
            texto.addEventListener('input', function () {
                var valor = texto.value;
                var pos = typeof texto.selectionStart === 'number' ? texto.selectionStart : valor.length;
                var antes = valor.slice(0, pos);
                if (/(^|\s)\/$/.test(antes)) {
                    abrirMenuProntas();
                } else if (prontasMenuAberto) {
                    fecharMenuProntas();
                }
            });
        }

        var menuProntas = document.getElementById('waProntasMenu');
        if (menuProntas) {
            menuProntas.addEventListener('click', function (e) {
                // Impede que o clique chegue ao document depois que o menu
                // for re-renderizado e o botão original sair do DOM.
                e.stopPropagation();
                var voltar = e.target.closest('[data-pronta-voltar]');
                if (voltar) {
                    renderMenuProntas('');
                    return;
                }
                var titulo = e.target.closest('[data-pronta-titulo]');
                if (titulo) {
                    renderMenuProntas(titulo.getAttribute('data-pronta-titulo') || '');
                    return;
                }
                var item = e.target.closest('[data-pronta-id]');
                if (item) {
                    selecionarPronta(parseInt(item.getAttribute('data-pronta-id') || '0', 10));
                }
            });
        }
        document.addEventListener('click', function (e) {
            if (!prontasMenuAberto) return;
            if (e.target.closest('#waProntasMenu') || e.target === texto) return;
            fecharMenuProntas();
        });

        var anexar = document.getElementById('waAnexar');
        var arquivoInput = document.getElementById('waArquivo');
        if (anexar && arquivoInput) {
            anexar.addEventListener('click', function () { arquivoInput.click(); });
            arquivoInput.addEventListener('change', function () {
                selecionarArquivo(arquivoInput.files && arquivoInput.files[0] ? arquivoInput.files[0] : null);
            });
        }
        var removerArquivo = document.getElementById('waArquivoRemover');
        if (removerArquivo) removerArquivo.addEventListener('click', limparArquivo);

        var cameraBtn = document.getElementById('waCameraBtn');
        var cameraInput = document.getElementById('waCamera');
        if (cameraBtn && cameraInput) {
            cameraBtn.addEventListener('click', abrirCamera);
            cameraInput.addEventListener('change', function () {
                var file = cameraInput.files && cameraInput.files[0] ? cameraInput.files[0] : null;
                if (file) selecionarArquivo(file);
                cameraInput.value = '';
            });
        }

        var cameraFechar = document.getElementById('waCameraFechar');
        if (cameraFechar) cameraFechar.addEventListener('click', fecharCamera);
        var cameraCancelar = document.getElementById('waCameraCancelar');
        if (cameraCancelar) cameraCancelar.addEventListener('click', fecharCamera);
        var cameraCapturar = document.getElementById('waCameraCapturar');
        if (cameraCapturar) cameraCapturar.addEventListener('click', capturarFoto);
        var cameraModal = document.getElementById('waCameraModal');
        if (cameraModal) {
            cameraModal.addEventListener('click', function (e) {
                if (e.target === cameraModal) fecharCamera();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && cameraAberta) fecharCamera();
        });

        var microfone = document.getElementById('waGravarAudio');
        if (microfone) {
            microfone.addEventListener('click', function () {
                if (gravando) pararGravacao(true);
                else iniciarGravacao();
            });
        }

        var exportar = document.getElementById('waExportar');
        if (exportar) exportar.addEventListener('click', abrirModalExportar);
        document.querySelectorAll('input[name="modo"]').forEach(function (radio) {
            radio.addEventListener('change', atualizarExportacaoModo);
        });
        var formExportar = document.getElementById('waFormExportar');
        if (formExportar) {
            formExportar.addEventListener('submit', function (e) {
                var marcado = document.querySelector('input[name="modo"]:checked');
                var modo = marcado ? marcado.value : 'dias';
                var erro = document.getElementById('waExportErro');
                if (erro) erro.textContent = '';
                if (modo === 'datas') {
                    var inicio = document.getElementById('waExportInicio');
                    var fim = document.getElementById('waExportFim');
                    if (!inicio || !fim || !inicio.value || !fim.value) {
                        e.preventDefault();
                        if (erro) erro.textContent = 'Informe a data inicial e a data final.';
                        return;
                    }
                    if (inicio.value > fim.value) {
                        e.preventDefault();
                        if (erro) erro.textContent = 'A data inicial não pode ser maior que a data final.';
                    }
                }
            });
        }

        var cancelarGravacao = document.getElementById('waGravacaoCancelar');
        if (cancelarGravacao) cancelarGravacao.addEventListener('click', function () { pararGravacao(false); });

        ['waNovoChat', 'waNovoChatEmpty'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('click', abrirModalNovo);
        });

        var buscaContato = document.getElementById('waBuscaContato');
        if (buscaContato) {
            buscaContato.addEventListener('input', function () {
                clearTimeout(buscaContatoTimer);
                buscaContatoTimer = setTimeout(function () {
                    buscarContatos(buscaContato.value.trim());
                }, 300);
            });
        }

        var criar = document.getElementById('waCriarContato');
        if (criar) criar.addEventListener('click', criarContato);

        var resultados = document.getElementById('waResultadosContato');
        if (resultados) {
            resultados.addEventListener('click', function (e) {
                var item = e.target.closest('.wa-resultado');
                if (!item) return;
                var contatoId = item.getAttribute('data-contato');
                if (contatoId) window.location.href = cfg.urlIndex + '?contato=' + encodeURIComponent(contatoId);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('#waMensagens .wa-msg[data-id]').forEach(function (el) {
            var id = parseInt(el.getAttribute('data-id') || '0', 10);
            if (id > 0) mensagensRenderizadas[id] = true;
        });
        iniciarEventos();

        timerConversas = setInterval(function () {
            if (!buscaAtual) carregarConversas();
        }, 5000);

        if (conversaId) {
            var container = document.getElementById('waMensagens');
            if (container) container.scrollTop = container.scrollHeight;
            timerMensagens = setInterval(carregarMensagens, 5000);
            setInterval(atualizarStatus, 15000);
        }

        window.addEventListener('beforeunload', function () {
            if (gravacaoStream) {
                gravacaoStream.getTracks().forEach(function (track) { track.stop(); });
            }
            if (cameraStream) {
                cameraStream.getTracks().forEach(function (track) { track.stop(); });
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) return;
            carregarConversas();
            if (conversaId && document.hasFocus()) {
                carregarMensagens();
                apiPost({ acao: 'marcar_lida' }, { conversa: conversaId })
                    .then(function () { carregarConversas(); })
                    .catch(function () {});
            }
        });
    });
})();
