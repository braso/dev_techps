/* Chamadas de voz — Cloud API + WebRTC */
(function () {
    'use strict';

    var cfg = window.WA_CHAMADA || {};
    var pc = null;
    var localStream = null;
    var remoteAudio = null;
    var callId = '';
    var callAtiva = false;
    var chamadaPendente = null;
    var iniciando = false;
    var timer = null;
    var inicioTimer = 0;
    var muted = false;
    var iceAplicado = false;
    var confirmando = false;
    var permissionTimer = null;

    function $(id) { return document.getElementById(id); }

    function apiUrl(params) {
        var qs = [];
        Object.keys(params || {}).forEach(function (k) {
            qs.push(encodeURIComponent(k === 'acao' ? 'wa_acao' : k) + '=' + encodeURIComponent(params[k]));
        });
        return cfg.api + (qs.length ? '?' + qs.join('&') : '');
    }

    function apiGet(params) {
        return fetch(apiUrl(params), { headers: { 'X-CSRF-Token': cfg.csrf }, cache: 'no-store' }).then(function (r) { return r.json(); });
    }

    function apiPost(params, data) {
        var body = new URLSearchParams();
        body.append('csrf', cfg.csrf);
        Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
        return fetch(apiUrl(params), {
            method: 'POST',
            headers: { 'X-CSRF-Token': cfg.csrf, 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    function esc(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function abrirModal() {
        var modal = $('waChamadaModal');
        if (modal) {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        }
    }

    function fecharModal() {
        var modal = $('waChamadaModal');
        if (modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    function status(texto, erro) {
        var el = $('waCallStatus');
        if (!el) return;
        el.textContent = texto || '';
        el.classList.toggle('error', !!erro);
    }

    function titulo(nome) {
        var el = $('waCallTitle');
        if (el) el.textContent = nome || cfg.contatoNome || 'Chamada';
        var sub = $('waCallSubtitle');
        if (sub) sub.textContent = cfg.contatoTelefone || '';
    }

    function pararTimer() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function iniciarTimer() {
        pararTimer();
        inicioTimer = Date.now();
        timer = setInterval(function () {
            var s = Math.max(0, Math.floor((Date.now() - inicioTimer) / 1000));
            var m = Math.floor(s / 60);
            var r = s % 60;
            var el = $('waCallTimer');
            if (el) el.textContent = String(m).padStart(2, '0') + ':' + String(r).padStart(2, '0');
        }, 1000);
    }

    function mostrarBotoes(estado) {
        var aceitar = $('waCallAceitar');
        var rejeitar = $('waCallRejeitar');
        var permitir = $('waCallPermitir');
        var mudo = $('waCallMudo');
        var encerrar = $('waCallEncerrar');
        var fechar = $('waCallFechar');
        if (aceitar) aceitar.style.display = estado === 'incoming' ? 'inline-block' : 'none';
        if (rejeitar) rejeitar.style.display = estado === 'incoming' ? 'inline-block' : 'none';
        if (permitir) permitir.style.display = estado === 'permission' ? 'inline-block' : 'none';
        if (mudo) mudo.style.display = (estado === 'connected' || estado === 'calling') ? 'inline-block' : 'none';
        if (encerrar) encerrar.style.display = (estado === 'connected' || estado === 'calling' || estado === 'incoming') ? 'inline-block' : 'none';
        if (fechar) fechar.style.display = estado === 'closed' || estado === 'error' || estado === 'permission' ? 'inline-block' : 'none';
    }

    function limparMidia() {
        if (pc) {
            try { pc.close(); } catch (e) {}
            pc = null;
        }
        if (localStream) {
            localStream.getTracks().forEach(function (t) { t.stop(); });
            localStream = null;
        }
        if (remoteAudio) {
            remoteAudio.pause();
            remoteAudio.srcObject = null;
            remoteAudio = null;
        }
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        if (permissionTimer) {
            clearInterval(permissionTimer);
            permissionTimer = null;
        }
        pararTimer();
        var el = $('waCallTimer');
        if (el) el.textContent = '00:00';
        iceAplicado = false;
        confirmando = false;
    }

    function finalizar(mensagem, erro) {
        limparMidia();
        callAtiva = false;
        callId = '';
        chamadaPendente = null;
        iniciando = false;
        status(mensagem || 'Chamada encerrada.', erro);
        mostrarBotoes(erro ? 'error' : 'closed');
    }

    function fecharTudo() {
        limparMidia();
        fecharModal();
        callAtiva = false;
        callId = '';
        chamadaPendente = null;
        iniciando = false;
        muted = false;
    }

    function getMedia() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return Promise.reject(new Error('Este navegador não suporta chamadas de áudio.'));
        }
        return navigator.mediaDevices.getUserMedia({ audio: true });
    }

    function criarPeer() {
        if (!window.RTCPeerConnection) throw new Error('WebRTC não suportado neste navegador.');
        pc = new RTCPeerConnection({ iceServers: cfg.iceServers || [] });
        pc.oniceconnectionstatechange = function () {
            if (!pc) return;
            if (pc.iceConnectionState === 'failed') {
                status('Falha na conexão de áudio. Encerrando...', true);
                encerrar();
            }
        };
        pc.onconnectionstatechange = function () {
            if (!pc) return;
            if (pc.connectionState === 'connected' && callAtiva && !inicioTimer) {
                // O timer é iniciado pelo status conectada; mantém o estado.
            }
            if (pc.connectionState === 'failed' && callAtiva) {
                status('Conexão de voz falhou.', true);
                encerrar();
            }
        };
        pc.ontrack = function (event) {
            if (!remoteAudio) {
                remoteAudio = new Audio();
                remoteAudio.autoplay = true;
            }
            remoteAudio.srcObject = event.streams[0];
            var p = remoteAudio.play();
            if (p && p.catch) p.catch(function () {});
        };
        return pc;
    }

    function esperarIce(peer) {
        return new Promise(function (resolve) {
            if (!peer || peer.iceGatheringState === 'complete') return resolve();
            var done = false;
            function finalizarIce() {
                if (done) return;
                done = true;
                resolve();
            }
            peer.addEventListener('icegatheringstatechange', function () {
                if (peer.iceGatheringState === 'complete') finalizarIce();
            });
            setTimeout(finalizarIce, 2500);
        });
    }

    function permissaoConcedida(data) {
        var p = data && data.permission ? data.permission : null;
        if (!p) return false;
        var s = String(p.status || '').toLowerCase();
        return s === 'granted' || s === 'permanent' || s === 'temporary';
    }

    function verificarPermissao() {
        return apiGet({ acao: 'chamada_permissao', conversa: cfg.conversaId });
    }

    function iniciarChamada() {
        if (!cfg.ativo || !cfg.conversaId || iniciando) return;
        iniciando = true;
        abrirModal();
        titulo(cfg.contatoNome);
        status('Verificando permissão do contato...');
        mostrarBotoes('permission');
        var permitir = $('waCallPermitir');
        if (permitir) permitir.style.display = 'none';

        verificarPermissao().then(function (data) {
            if (!data.success) {
                iniciando = false;
                status(data.message || 'Não foi possível verificar a permissão.', true);
                mostrarBotoes('error');
                return;
            }
            if (permissaoConcedida(data.data)) {
                iniciando = false;
                iniciarSaida();
            } else {
                iniciando = false;
                status('O contato ainda não autorizou chamadas. Envie uma solicitação de permissão.', true);
                mostrarBotoes('permission');
            }
        }).catch(function () {
            iniciando = false;
            status('Falha ao verificar a permissão.', true);
            mostrarBotoes('error');
        });
    }

    function solicitarPermissao() {
        if (!cfg.conversaId || iniciando) return;
        iniciando = true;
        var permitir = $('waCallPermitir');
        if (permitir) permitir.disabled = true;
        status('Solicitando permissão ao contato...');
        apiPost({ acao: 'solicitar_permissao_chamada' }, {
            conversa: cfg.conversaId,
            texto: 'Podemos ligar para você pelo WhatsApp para ajudar no atendimento?'
        }).then(function (data) {
            if (!data.success) {
                iniciando = false;
                if (permitir) permitir.disabled = false;
                status(data.message || 'Não foi possível solicitar a permissão.', true);
                mostrarBotoes('permission');
                return;
            }
            status('Solicitação enviada. Aguardando aprovação no WhatsApp...');
            var tentativas = 0;
            if (permissionTimer) clearInterval(permissionTimer);
            permissionTimer = setInterval(function () {
                tentativas++;
                if (tentativas > 40 || !cfg.ativo) {
                    clearInterval(permissionTimer);
                    permissionTimer = null;
                    iniciando = false;
                    if (permitir) permitir.disabled = false;
                    status('Permissão ainda não aprovada. Tente novamente após o contato autorizar.', true);
                    return;
                }
                verificarPermissao().then(function (res) {
                    if (res.success && permissaoConcedida(res.data)) {
                        clearInterval(permissionTimer);
                        permissionTimer = null;
                        iniciando = false;
                        if (permitir) permitir.disabled = false;
                        status('Permissão concedida. Iniciando chamada...');
                        iniciarSaida();
                    }
                }).catch(function () {});
            }, 3000);
        }).catch(function () {
            iniciando = false;
            if (permitir) permitir.disabled = false;
            status('Falha ao solicitar permissão.', true);
            mostrarBotoes('permission');
        });
    }

    function iniciarSaida() {
        if (!cfg.conversaId || callAtiva || iniciando) return;
        iniciando = true;
        callAtiva = true;
        titulo(cfg.contatoNome);
        mostrarBotoes('calling');
        status('Preparando microfone...');
        getMedia().then(function (stream) {
            localStream = stream;
            criarPeer();
            localStream.getTracks().forEach(function (t) { pc.addTrack(t, localStream); });
            return pc.createOffer();
        }).then(function (offer) {
            return pc.setLocalDescription(offer).then(function () { return esperarIce(pc); });
        }).then(function () {
            var sdp = pc.localDescription ? pc.localDescription.sdp : '';
            if (!sdp) throw new Error('Não foi possível gerar a oferta de áudio.');
            status('Chamando...');
            return apiPost({ acao: 'iniciar_chamada' }, { conversa: cfg.conversaId, sdp: sdp });
        }).then(function (data) {
            if (!data.success) throw new Error(data.message || 'Não foi possível iniciar a chamada.');
            callId = data.call_id || '';
            iniciando = false;
            status('Chamando...');
            mostrarBotoes('calling');
            if (!callId) throw new Error('A chamada não retornou um identificador.');
            pollChamada();
        }).catch(function (e) {
            var id = callId;
            finalizar(e.message || 'Falha ao iniciar a chamada.', true);
            if (id) apiPost({ acao: 'encerrar_chamada' }, { call_id: id }).catch(function () {});
        });
    }

    var pollTimer = null;
    function pollChamada() {
        if (!callId) return;
        if (pollTimer) clearInterval(pollTimer);
        var tentativas = 0;
        pollTimer = setInterval(function () {
            tentativas++;
            if (!callId || !callAtiva) {
                clearInterval(pollTimer);
                pollTimer = null;
                return;
            }
            apiGet({ acao: 'chamada_poll', call_id: callId }).then(function (data) {
                if (!data.success || !data.chamada) {
                    if (tentativas > 60) {
                        clearInterval(pollTimer);
                        pollTimer = null;
                        finalizar(data.message || 'A chamada não conectou.', true);
                    }
                    return;
                }
                var ch = data.chamada;
                if (ch.sdp_answer && pc && !iceAplicado) {
                    iceAplicado = true;
                    pc.setRemoteDescription({ type: 'answer', sdp: ch.sdp_answer }).catch(function () {});
                }
                if (ch.status === 'conectada') {
                    if (!timer) iniciarTimer();
                    status('Chamada conectada.');
                    mostrarBotoes('connected');
                } else if (ch.status === 'rejeitada' || ch.status === 'encerrada' || ch.status === 'falhou') {
                    clearInterval(pollTimer);
                    pollTimer = null;
                    finalizar(ch.erro || ('Chamada ' + ch.status + '.'), ch.status === 'falhou');
                } else if (ch.status === 'tocando') {
                    status('Chamando...');
                }
                if (tentativas > 90 && ch.status !== 'conectada') {
                    clearInterval(pollTimer);
                    pollTimer = null;
                    var id = callId;
                    finalizar('A chamada não foi atendida.', false);
                    if (id) apiPost({ acao: 'encerrar_chamada' }, { call_id: id }).catch(function () {});
                }
            }).catch(function () {});
        }, 1000);
    }

    function mostrarRecebida(ch) {
        if (!ch || !ch.call_id || callAtiva || chamadaPendente) return;
        chamadaPendente = ch;
        abrirModal();
        titulo(ch.contato || cfg.contatoNome);
        status('Chamada recebida');
        var sub = $('waCallSubtitle');
        if (sub) sub.textContent = ch.telefone || '';
        mostrarBotoes('incoming');
        var encerrarBtn = $('waCallEncerrar');
        if (encerrarBtn) encerrarBtn.style.display = 'none';
    }

    function aceitarChamada() {
        if (!chamadaPendente || !chamadaPendente.sdp_offer || callAtiva) return;
        var ch = chamadaPendente;
        callId = ch.call_id;
        callAtiva = true;
        mostrarBotoes('calling');

        status('Preparando microfone...');
        getMedia().then(function (stream) {
            localStream = stream;
            criarPeer();
            localStream.getTracks().forEach(function (t) { pc.addTrack(t, localStream); });
            return pc.setRemoteDescription({ type: 'offer', sdp: ch.sdp_offer });
        }).then(function () {
            return pc.createAnswer();
        }).then(function (answer) {
            return pc.setLocalDescription(answer).then(function () { return esperarIce(pc); });
        }).then(function () {
            var sdp = pc.localDescription ? pc.localDescription.sdp : '';
            if (!sdp) throw new Error('Não foi possível gerar a resposta de áudio.');
            status('Aceitando chamada...');
            return apiPost({ acao: 'pre_aceitar_chamada' }, { call_id: callId, sdp: sdp });
        }).then(function (data) {
            if (!data.success) throw new Error(data.message || 'Não foi possível pré-aceitar a chamada.');
            chamadaPendente = null;
            status('Conectando áudio...');
            var tentouConfirmar = false;
            function confirmar() {
                if (tentouConfirmar || !pc) return;
                tentouConfirmar = true;
                confirmando = true;
                apiPost({ acao: 'confirmar_chamada' }, { call_id: callId }).then(function (res) {
                    confirmando = false;
                    if (res.success) {
                        if (!timer) iniciarTimer();
                        status('Chamada conectada.');
                        mostrarBotoes('connected');
                    } else {
                        finalizar(res.message || 'Falha ao aceitar a chamada.', true);
                    }
                }).catch(function () {
                    confirmando = false;
                    finalizar('Falha ao confirmar a chamada.', true);
                });
            }
            if (pc.connectionState === 'connected') {
                confirmar();
            } else {
                pc.onconnectionstatechange = function () {
                    if (pc && pc.connectionState === 'connected') confirmar();
                };
                setTimeout(confirmar, 10000);
            }
        }).catch(function (e) {
            finalizar(e.message || 'Falha ao aceitar a chamada.', true);
            rejeitarChamada(true);
        });
    }

    function rejeitarChamada(silencioso) {
        var id = callId || (chamadaPendente ? chamadaPendente.call_id : '');
        if (id) {
            apiPost({ acao: 'rejeitar_chamada' }, { call_id: id }).catch(function () {});
        }
        if (!silencioso) finalizar('Chamada rejeitada.', false);
        else {
            limparMidia();
            callAtiva = false;
            callId = '';
            chamadaPendente = null;
        }
    }

    function encerrar() {
        var id = callId || (chamadaPendente ? chamadaPendente.call_id : '');
        if (id) {
            apiPost({ acao: 'encerrar_chamada' }, { call_id: id }).catch(function () {});
        }
        finalizar('Chamada encerrada.', false);
    }

    function alternarMudo() {
        muted = !muted;
        if (localStream) {
            localStream.getAudioTracks().forEach(function (t) { t.enabled = !muted; });
        }
        var btn = $('waCallMudo');
        if (btn) btn.innerHTML = muted ? '<i class="fa fa-microphone-slash"></i> Ativar som' : '<i class="fa fa-microphone"></i> Mudo';
    }

    function pollPendentes() {
        if (!cfg.ativo || !cfg.conversaId || callAtiva || chamadaPendente || iniciando) return;
        var modal = $('waChamadaModal');
        if (modal && modal.classList.contains('open')) return;
        apiGet({ acao: 'chamadas_pendentes' }).then(function (data) {
            if (!data.success || !data.chamadas || !data.chamadas.length) return;
            mostrarRecebida(data.chamadas[0]);
        }).catch(function () {});
    }

    function iniciarEventos() {
        var botao = $('waChamada');
        if (botao) botao.addEventListener('click', iniciarChamada);
        var permitir = $('waCallPermitir');
        if (permitir) permitir.addEventListener('click', solicitarPermissao);
        var aceitar = $('waCallAceitar');
        if (aceitar) aceitar.addEventListener('click', aceitarChamada);
        var rejeitar = $('waCallRejeitar');
        if (rejeitar) rejeitar.addEventListener('click', function () { rejeitarChamada(false); });
        var encerrarBtn = $('waCallEncerrar');
        if (encerrarBtn) encerrarBtn.addEventListener('click', encerrar);
        var mudo = $('waCallMudo');
        if (mudo) mudo.addEventListener('click', alternarMudo);
        var fechar = $('waCallFechar');
        if (fechar) fechar.addEventListener('click', fecharTudo);
        var modal = $('waChamadaModal');
        if (modal) modal.addEventListener('click', function (e) {
            if (e.target === modal && !callAtiva && !chamadaPendente) fecharTudo();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && modal.classList.contains('open') && !callAtiva && !chamadaPendente) fecharTudo();
        });
        setInterval(pollPendentes, 3000);
        window.addEventListener('beforeunload', function () {
            if (callId) {
                try {
                    var body = new URLSearchParams();
                    body.append('csrf', cfg.csrf);
                    body.append('call_id', callId);
                    navigator.sendBeacon(cfg.api + '?wa_acao=encerrar_chamada', body);
                } catch (e) {}
            }
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciarEventos);
    else iniciarEventos();
})();
