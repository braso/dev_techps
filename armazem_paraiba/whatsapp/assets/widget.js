/* Widget flutuante do WhatsApp */
(function () {
    'use strict';

    var cfg = window.WA_WIDGET || {};
    var aberto = false;
    var conversaId = 0;
    var ultimoId = 0;
    var timer = null;
    var timerLista = null;
    var buscaTimer = null;

    function el(id) { return document.getElementById(id); }
    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function apiGet(params) {
        var qs = Object.keys(params || {}).map(function (k) {
            var key = (k === 'acao') ? 'wa_acao' : k;
            return encodeURIComponent(key) + '=' + encodeURIComponent(params[k]);
        }).join('&');
        return fetch(cfg.api + (qs ? '?' + qs : ''), { headers: { 'X-CSRF-Token': cfg.csrf }, cache: 'no-store' }).then(function (r) { return r.json(); });
    }
    function apiPost(params, data) {
        var body = new URLSearchParams();
        body.append('csrf', cfg.csrf);
        Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
        var qs = Object.keys(params || {}).map(function (k) {
            var key = (k === 'acao') ? 'wa_acao' : k;
            return encodeURIComponent(key) + '=' + encodeURIComponent(params[k]);
        }).join('&');
        return fetch(cfg.api + '?' + qs, {
            method: 'POST',
            headers: { 'X-CSRF-Token': cfg.csrf, 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }
    function formatarHora(valor) {
        if (!valor) return '';
        var d = new Date(String(valor).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        function p(n) { return String(n).padStart(2, '0'); }
        return p(d.getDate()) + '/' + p(d.getMonth() + 1) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    }

    function renderConversas(conversas) {
        var lista = el('waWidgetLista');
        if (!lista) return;
        var total = 0;
        conversas.forEach(function (c) { total += parseInt(c.conv_nb_nao_lidas || 0, 10); });
        atualizarBadge(total);
        if (!conversas.length) {
            lista.innerHTML = '<div class="wa-widget-vazio"><i class="fa fa-comments-o"></i><br>Nenhuma conversa.<br><small>Abra o módulo completo para iniciar.</small></div>';
            return;
        }
        lista.innerHTML = conversas.map(function (c) {
            var inicial = esc((c.cont_tx_nome || '?').substring(0, 1));
            var nao = parseInt(c.conv_nb_nao_lidas || 0, 10);
            return '<div class="wa-widget-conversa" data-conversa="' + parseInt(c.conv_nb_id || 0, 10) + '" data-contato="' + parseInt(c.conv_nb_contato || 0, 10) + '" data-nome="' + esc(c.cont_tx_nome || '') + '" data-telefone="' + esc(c.cont_tx_e164 || '') + '">' +
                '<div class="wa-widget-avatar">' + inicial + '</div>' +
                '<div class="wa-widget-conversa-info">' +
                    '<div class="wa-widget-conversa-top"><strong>' + esc(c.cont_tx_nome || '') + '</strong><span>' + esc(formatarHora(c.ultima_data || c.conv_dt_ultima_atividade)) + '</span></div>' +
                    '<div style="display:flex;justify-content:space-between;gap:6px;align-items:center;"><span class="wa-widget-conversa-preview">' + esc(c.ultima_msg || c.conv_tx_ultima_mensagem || 'Sem mensagens') + '</span>' +
                    (nao > 0 ? '<span class="wa-widget-naolidas">' + nao + '</span>' : '') + '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    function atualizarBadge(total) {
        var badge = el('waWidgetBadge');
        if (!badge) return;
        if (total > 0) {
            badge.textContent = total > 99 ? '99+' : String(total);
            badge.style.display = 'grid';
        } else {
            badge.style.display = 'none';
        }
    }

    function carregarConversas() {
        var busca = (el('waWidgetBusca') || {}).value || '';
        apiGet({ acao: 'conversas', busca: busca, filtro: 'todas' }).then(function (data) {
            if (data.success) renderConversas(data.conversas || []);
        }).catch(function () {});
    }

    function renderMensagem(m) {
        var out = m.msg_tx_direcao === 'out';
        var texto = esc(m.msg_tx_conteudo || '').replace(/\n/g, '<br>');
        return '<div class="wa-widget-msg ' + (out ? 'out' : 'in') + '">' + texto + '<small>' + esc(formatarHora(m.msg_dt_criacao)) + '</small></div>';
    }

    function carregarMensagens() {
        if (!conversaId) return;
        apiGet({ acao: 'mensagens', conversa: conversaId, apos_id: ultimoId }).then(function (data) {
            if (!data.success || !data.mensagens || !data.mensagens.length) return;
            var alvo = el('waWidgetMensagens');
            if (!alvo) return;
            data.mensagens.forEach(function (m) {
                alvo.insertAdjacentHTML('beforeend', renderMensagem(m));
                ultimoId = Math.max(ultimoId, parseInt(m.msg_nb_id || 0, 10));
            });
            alvo.scrollTop = alvo.scrollHeight;
            if (aberto && document.hasFocus()) {
                apiPost({ acao: 'marcar_lida' }, { conversa: conversaId })
                    .then(function () { carregarConversas(); })
                    .catch(function () { carregarConversas(); });
            } else {
                carregarConversas();
            }
        }).catch(function () {});
    }

    function abrirConversa(conversa, contato, nome, telefone) {
        conversaId = parseInt(conversa || 0, 10);
        ultimoId = 0;
        var lista = el('waWidgetListaWrap');
        var chat = el('waWidgetChat');
        if (lista) lista.style.display = 'none';
        if (chat) chat.style.display = 'flex';
        var nomeEl = el('waWidgetNome');
        var telEl = el('waWidgetTelefone');
        if (nomeEl) nomeEl.textContent = nome || 'Contato';
        if (telEl) telEl.textContent = telefone || '';
        var msgs = el('waWidgetMensagens');
        if (msgs) msgs.innerHTML = '';
        carregarMensagens();
        if (timer) clearInterval(timer);
        timer = setInterval(carregarMensagens, 5000);
    }

    function voltarLista() {
        conversaId = 0;
        var lista = el('waWidgetListaWrap');
        var chat = el('waWidgetChat');
        if (lista) lista.style.display = '';
        if (chat) chat.style.display = 'none';
        if (timer) { clearInterval(timer); timer = null; }
        carregarConversas();
    }

    function enviar() {
        var texto = el('waWidgetTexto');
        var botao = el('waWidgetEnviar');
        if (!texto || !conversaId) return;
        var mensagem = texto.value.trim();
        if (!mensagem) return;
        if (botao) botao.disabled = true;
        apiPost({ acao: 'enviar' }, { conversa: conversaId, texto: mensagem }).then(function (data) {
            if (data.success) {
                texto.value = '';
                carregarMensagens();
            } else if (window.Swal) {
                Swal.fire({ icon: 'error', title: 'Não foi possível enviar', text: data.message || '' });
            }
            if (botao) botao.disabled = false;
        }).catch(function () { if (botao) botao.disabled = false; });
    }

    function abrirPainel() {
        var painel = el('waWidgetPanel');
        if (!painel) return;
        aberto = true;
        painel.classList.add('open');
        painel.setAttribute('aria-hidden', 'false');
        carregarConversas();

        var chat = el('waWidgetChat');
        if (conversaId && chat && chat.style.display !== 'none') {
            carregarMensagens();
            if (timer) clearInterval(timer);
            timer = setInterval(carregarMensagens, 5000);
        }
    }

    function fecharPainel() {
        var painel = el('waWidgetPanel');
        if (!painel) return;
        aberto = false;
        painel.classList.remove('open');
        painel.setAttribute('aria-hidden', 'true');
        if (timer) { clearInterval(timer); timer = null; }
    }

    function iniciar() {
        var btn = el('waWidgetBtn');
        if (btn) btn.addEventListener('click', function () { aberto ? fecharPainel() : abrirPainel(); });
        var fechar = el('waWidgetClose');
        if (fechar) fechar.addEventListener('click', fecharPainel);
        var voltar = el('waWidgetBack');
        if (voltar) voltar.addEventListener('click', voltarLista);
        var enviarBtn = el('waWidgetEnviar');
        if (enviarBtn) enviarBtn.addEventListener('click', enviar);
        var texto = el('waWidgetTexto');
        if (texto) texto.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviar(); } });

        var busca = el('waWidgetBusca');
        if (busca) busca.addEventListener('input', function () {
            clearTimeout(buscaTimer);
            buscaTimer = setTimeout(carregarConversas, 300);
        });

        var lista = el('waWidgetLista');
        if (lista) lista.addEventListener('click', function (e) {
            var item = e.target.closest('.wa-widget-conversa');
            if (!item) return;
            abrirConversa(item.getAttribute('data-conversa'), item.getAttribute('data-contato'), item.getAttribute('data-nome'), item.getAttribute('data-telefone'));
        });

        document.addEventListener('click', function (e) {
            if (!aberto) return;
            if (e.target.closest('#waWidget')) return;
            fecharPainel();
        });

        // Mantém o badge e a lista de conversas atualizados mesmo com o
        // painel fechado. É isso que faz o contador aparecer no ícone verde
        // assim que uma mensagem chega.
        carregarConversas();
        timerLista = setInterval(carregarConversas, 5000);

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) return;
            carregarConversas();
            if (conversaId && aberto) {
                carregarMensagens();
                if (document.hasFocus()) {
                    apiPost({ acao: 'marcar_lida' }, { conversa: conversaId })
                        .then(function () { carregarConversas(); })
                        .catch(function () {});
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', iniciar);
})();
