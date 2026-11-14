/* Assistente Virtual TechPS — front-end do widget e da página completa */
(function () {
    'use strict';

    var cfg = window.IA_ASSISTENTE || {};
    var apiUrl = cfg.api || '';
    var csrf = cfg.csrf || '';
    var base = (cfg.base || '').replace(/\/$/, '');
    var telaAtual = cfg.tela || '';

    function el(tag, cls, html) {
        var node = document.createElement(tag);
        if (cls) { node.className = cls; }
        if (html !== undefined) { node.innerHTML = html; }
        return node;
    }

    function escapeHtml(text) {
        return String(text == null ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safeUrl(url) {
        url = String(url || '').trim();
        if (/^https?:\/\//i.test(url)) { return url; }
        if (url.charAt(0) === '/') { return base + url; }
        return '';
    }

    function inlineMarkdown(text) {
        var out = escapeHtml(text);
        out = out.replace(/`([^`]+)`/g, '<code>$1</code>');
        out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        out = out.replace(/(^|[^*])\*([^*]+)\*/g, '$1<em>$2</em>');
        out = out.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function (m, label, url) {
            var href = safeUrl(url);
            if (!href) { return label; }
            return '<a href="' + escapeHtml(href) + '" target="_blank" rel="noopener noreferrer">' + label + '</a>';
        });
        return out;
    }

    function renderMarkdown(text) {
        var lines = String(text || '').replace(/\r\n/g, '\n').split('\n');
        var html = '';
        var listType = '';
        var paragraph = [];

        function flushParagraph() {
            if (!paragraph.length) { return; }
            html += '<p>' + paragraph.map(inlineMarkdown).join('<br>') + '</p>';
            paragraph = [];
        }
        function closeList() {
            if (listType) {
                html += '</' + listType + '>';
                listType = '';
            }
        }

        lines.forEach(function (line) {
            var trimmed = line.trim();
            if (trimmed === '') {
                flushParagraph();
                closeList();
                return;
            }
            var ol = trimmed.match(/^\d+[.)]\s+(.*)$/);
            var ul = trimmed.match(/^[-*•]\s+(.*)$/);
            if (ol || ul) {
                flushParagraph();
                var wanted = ol ? 'ol' : 'ul';
                if (listType !== wanted) {
                    closeList();
                    listType = wanted;
                    html += '<' + listType + '>';
                }
                html += '<li>' + inlineMarkdown((ol || ul)[1]) + '</li>';
                return;
            }
            closeList();
            paragraph.push(trimmed);
        });
        flushParagraph();
        closeList();
        return html || '<p></p>';
    }

    function sourcesHtml(fontes) {
        if (!fontes || !fontes.length) { return ''; }
        var html = '<div class="ia-fontes"><strong>Fontes</strong>';
        fontes.slice(0, 5).forEach(function (f) {
            var titulo = escapeHtml(f.titulo || 'Documento');
            var secao = f.secao ? ' — ' + escapeHtml(f.secao) : '';
            var href = safeUrl(f.caminho || '');
            if (href) {
                html += '<div><a href="' + escapeHtml(href) + '" target="_blank" rel="noopener">' + titulo + secao + '</a></div>';
            } else {
                html += '<div>' + titulo + secao + '</div>';
            }
        });
        html += '</div>';
        return html;
    }

    function postJson(payload) {
        return fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify(Object.assign({ csrf: csrf }, payload))
        }).then(function (resp) {
            return resp.text().then(function (txt) {
                var data;
                try { data = JSON.parse(txt); } catch (e) { data = null; }
                if (!resp.ok || !data || data.ok === false) {
                    var msg = (data && (data.erro || data.error)) || ('Erro ' + resp.status);
                    throw new Error(msg);
                }
                return data;
            });
        });
    }

    function streamAsk(payload, handlers) {
        return fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify(Object.assign({ csrf: csrf, stream: true }, payload))
        }).then(function (resp) {
            if (!resp.ok) {
                return resp.text().then(function (txt) {
                    var data;
                    try { data = JSON.parse(txt); } catch (e) { data = null; }
                    throw new Error((data && data.erro) || ('Erro ' + resp.status));
                });
            }
            if (!resp.body || !window.TextDecoder) {
                return resp.json().then(function (data) {
                    if (data && data.resposta) { handlers.onDelta(data.resposta); }
                    handlers.onDone(data || {});
                });
            }
            var reader = resp.body.getReader();
            var decoder = new TextDecoder('utf-8');
            var buffer = '';
            var done = false;

            function processBuffer() {
                var parts = buffer.split('\n\n');
                buffer = parts.pop() || '';
                parts.forEach(function (block) {
                    block.split('\n').forEach(function (line) {
                        if (line.indexOf('data:') !== 0) { return; }
                        var json = line.slice(5).trim();
                        if (!json) { return; }
                        var data;
                        try { data = JSON.parse(json); } catch (e) { return; }
                        if (data.delta) { handlers.onDelta(data.delta); }
                        if (data.done) { done = true; handlers.onDone(data); }
                    });
                });
            }

            function read() {
                return reader.read().then(function (result) {
                    if (result.done) {
                        processBuffer();
                        if (!done) { handlers.onDone({}); }
                        return;
                    }
                    buffer += decoder.decode(result.value, { stream: true });
                    processBuffer();
                    return read();
                });
            }
            return read();
        });
    }

    function createChat(opts) {
        var root = opts.root;
        if (!root) { return null; }
        var messages = opts.messages;
        var form = opts.form;
        var input = opts.input;
        var suggestions = opts.suggestions;
        var state = { conversaId: 0, enviando: false, ultimoMsgId: 0 };
        var typingEl = null;
        var currentBubble = null;

        function scroll() {
            if (messages) { messages.scrollTop = messages.scrollHeight; }
        }

        function removeWelcome() {
            var welcome = messages ? messages.querySelector('[data-ia-welcome]') : null;
            if (welcome) { welcome.remove(); }
        }

        function addMessage(role, content, fontes, msgId) {
            removeWelcome();
            var row = el('div', 'ia-msg ia-' + (role === 'user' ? 'user' : 'assistant'));
            var bubble = el('div', 'ia-bubble');
            if (role === 'user') {
                bubble.innerHTML = '<p>' + escapeHtml(content).replace(/\n/g, '<br>') + '</p>';
            } else {
                bubble.innerHTML = renderMarkdown(content) + sourcesHtml(fontes);
                if (msgId) {
                    bubble.appendChild(feedbackButtons(msgId));
                }
            }
            row.appendChild(bubble);
            messages.appendChild(row);
            scroll();
            return bubble;
        }

        function feedbackButtons(msgId) {
            var wrap = el('div', 'ia-feedback');
            var up = el('button', '', '👍 Útil');
            var down = el('button', '', '👎 Não ajudou');
            up.type = 'button';
            down.type = 'button';
            up.addEventListener('click', function () {
                enviarFeedback(msgId, 1, up, down);
            });
            down.addEventListener('click', function () {
                enviarFeedback(msgId, -1, up, down);
            });
            wrap.appendChild(up);
            wrap.appendChild(down);
            return wrap;
        }

        function enviarFeedback(msgId, nota, up, down) {
            postJson({ acao: 'feedback', mensagem_id: msgId, nota: nota }).then(function () {
                up.classList.toggle('ia-ativo', nota > 0);
                down.classList.toggle('ia-ativo', nota < 0);
            }).catch(function () {});
        }

        function showTyping() {
            removeWelcome();
            typingEl = el('div', 'ia-msg ia-assistant');
            var bubble = el('div', 'ia-bubble');
            bubble.innerHTML = '<div class="ia-typing"><span></span><span></span><span></span></div><div class="ia-muted" style="margin-top:4px;">consultando a base local...</div>';
            typingEl.appendChild(bubble);
            messages.appendChild(typingEl);
            scroll();
        }

        function hideTyping() {
            if (typingEl && typingEl.parentNode) { typingEl.parentNode.removeChild(typingEl); }
            typingEl = null;
        }

        function setSending(value) {
            state.enviando = value;
            var btn = form ? form.querySelector('button[type="submit"], .ia-send') : null;
            if (btn) { btn.disabled = value; }
        }

        function addError(msg) {
            hideTyping();
            var row = el('div', 'ia-msg ia-assistant');
            var bubble = el('div', 'ia-bubble ia-aviso ia-erro');
            bubble.textContent = msg || 'Não foi possível responder agora. Tente novamente.';
            row.appendChild(bubble);
            messages.appendChild(row);
            scroll();
        }

        function send(text) {
            text = String(text || '').trim();
            if (!text || state.enviando) { return; }
            addMessage('user', text);
            if (input) {
                input.value = '';
                input.style.height = '38px';
            }
            setSending(true);
            showTyping();

            var assistantBubble = null;
            var assistantText = '';

            streamAsk({
                acao: 'ask',
                pergunta: text,
                conversa_id: state.conversaId,
                tela: telaAtual
            }, {
                onDelta: function (delta) {
                    hideTyping();
                    if (!assistantBubble) {
                        removeWelcome();
                        var row = el('div', 'ia-msg ia-assistant');
                        assistantBubble = el('div', 'ia-bubble');
                        row.appendChild(assistantBubble);
                        messages.appendChild(row);
                    }
                    assistantText += delta;
                    assistantBubble.innerHTML = renderMarkdown(assistantText);
                    scroll();
                },
                onDone: function (data) {
                    hideTyping();
                    setSending(false);
                    if (data && data.conversa_id) {
                        state.conversaId = data.conversa_id;
                    }
                    if (data && data.mensagem_id) {
                        state.ultimoMsgId = data.mensagem_id;
                    }
                    if (!assistantBubble && data && data.resposta) {
                        assistantBubble = addMessage('assistant', data.resposta, data.fontes, data.mensagem_id);
                    } else if (assistantBubble) {
                        assistantBubble.innerHTML = renderMarkdown(assistantText) + sourcesHtml(data && data.fontes);
                        if (data && data.mensagem_id) {
                            assistantBubble.appendChild(feedbackButtons(data.mensagem_id));
                        }
                    }
                    if (opts.onDone) { opts.onDone(data); }
                    scroll();
                }
            }).catch(function (err) {
                setSending(false);
                addError(err && err.message ? err.message : '');
            });
        }

        if (form && form.tagName === 'FORM') {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                send(input ? input.value : '');
            });
        }
        var sendBtn = form ? form.querySelector('button[type="submit"], .ia-send') : null;
        if (sendBtn) {
            sendBtn.addEventListener('click', function (e) {
                e.preventDefault();
                send(input ? input.value : '');
            });
        }
        if (suggestions) {
            Array.prototype.forEach.call(suggestions.querySelectorAll('.ia-suggestion'), function (btn) {
                btn.addEventListener('click', function () { send(btn.textContent); });
            });
        }
        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    send(input.value);
                }
            });
            input.addEventListener('input', function () {
                input.style.height = '38px';
                input.style.height = Math.min(110, input.scrollHeight) + 'px';
            });
        }

        function renderSuggestions(list) {
            if (!suggestions) { return; }
            suggestions.innerHTML = '';
            (list || []).forEach(function (texto) {
                var btn = el('button', 'ia-suggestion', escapeHtml(texto));
                btn.type = 'button';
                btn.addEventListener('click', function () { send(texto); });
                suggestions.appendChild(btn);
            });
        }

        function carregarSugestoes() {
            if (!suggestions) { return; }
            postJson({ acao: 'sugestoes', tela: telaAtual }).then(function (data) {
                renderSuggestions(data.sugestoes || []);
            }).catch(function () {});
        }

        function limpar() {
            if (messages) { messages.innerHTML = ''; }
            state.conversaId = 0;
            state.ultimoMsgId = 0;
            carregarSugestoes();
        }

        function novaConversa() {
            postJson({ acao: 'nova_conversa' }).then(function (data) {
                limpar();
                state.conversaId = data.conversa_id || 0;
                if (input) { input.focus(); }
            }).catch(function () {
                limpar();
            });
        }

        function carregarConversa(id) {
            postJson({ acao: 'mensagens', conversa_id: id }).then(function (data) {
                if (messages) { messages.innerHTML = ''; }
                state.conversaId = id;
                (data.mensagens || []).forEach(function (m) {
                    if (m.msg_tx_papel === 'user') {
                        addMessage('user', m.msg_tx_conteudo || '');
                    } else if (m.msg_tx_papel === 'assistant') {
                        addMessage('assistant', m.msg_tx_conteudo || '', m.fontes || [], m.msg_nb_id || 0);
                    }
                });
                if (opts.onConversaCarregada) { opts.onConversaCarregada(id); }
            }).catch(function () {});
        }

        carregarSugestoes();

        return {
            send: send,
            limpar: limpar,
            novaConversa: novaConversa,
            carregarConversa: carregarConversa,
            state: state,
            messages: messages
        };
    }

    function initWidget(chat) {
        var widget = document.getElementById('iaWidget');
        var btn = document.getElementById('iaWidgetBtn');
        var panel = document.getElementById('iaWidgetPanel');
        var close = document.getElementById('iaWidgetClose');
        var nova = document.getElementById('iaWidgetNova');
        if (!widget || !btn || !panel) { return; }

        function abrir() {
            panel.classList.add('ia-aberto');
            panel.setAttribute('aria-hidden', 'false');
            var input = document.getElementById('iaWidgetInput');
            if (input) { setTimeout(function () { input.focus(); }, 80); }
        }
        function fechar() {
            panel.classList.remove('ia-aberto');
            panel.setAttribute('aria-hidden', 'true');
        }
        btn.addEventListener('click', function () {
            if (panel.classList.contains('ia-aberto')) { fechar(); } else { abrir(); }
        });
        if (close) { close.addEventListener('click', fechar); }
        if (nova && chat) {
            nova.addEventListener('click', function () {
                chat.novaConversa();
                abrir();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panel.classList.contains('ia-aberto')) { fechar(); }
        });
    }

    function initPage(chat) {
        var nova = document.getElementById('iaPageNova');
        var lista = document.getElementById('iaHistoryList');
        if (nova && chat) {
            nova.addEventListener('click', function () { chat.novaConversa(); });
        }
        if (!lista || !chat) { return; }
        postJson({ acao: 'historico' }).then(function (data) {
            lista.innerHTML = '';
            (data.conversas || []).forEach(function (c) {
                var btn = el('button', 'ia-history-item', escapeHtml(c.conv_tx_titulo || 'Conversa'));
                btn.type = 'button';
                btn.appendChild(el('small', '', escapeHtml(c.conv_dt_atualizacao_fmt || '')));
                btn.addEventListener('click', function () {
                    Array.prototype.forEach.call(lista.querySelectorAll('.ia-history-item'), function (b) {
                        b.classList.remove('ia-ativo');
                    });
                    btn.classList.add('ia-ativo');
                    chat.carregarConversa(parseInt(c.conv_nb_id, 10));
                });
                lista.appendChild(btn);
            });
        }).catch(function () {});
    }

    document.addEventListener('DOMContentLoaded', function () {
        var pageMessages = document.getElementById('iaPageMessages');
        var widgetMessages = document.getElementById('iaWidgetMessages');

        if (pageMessages) {
            var pageChat = createChat({
                root: document.getElementById('iaPage'),
                messages: pageMessages,
                form: document.getElementById('iaPageForm'),
                input: document.getElementById('iaPageInput'),
                suggestions: document.getElementById('iaPageSuggestions'),
                onDone: function () {
                    // Recarrega o histórico para refletir a conversa atual.
                    var lista = document.getElementById('iaHistoryList');
                    if (lista) {
                        postJson({ acao: 'historico' }).then(function (data) {
                            lista.innerHTML = '';
                            (data.conversas || []).forEach(function (c) {
                                var btn = el('button', 'ia-history-item', escapeHtml(c.conv_tx_titulo || 'Conversa'));
                                btn.type = 'button';
                                btn.appendChild(el('small', '', escapeHtml(c.conv_dt_atualizacao_fmt || '')));
                                btn.addEventListener('click', function () {
                                    Array.prototype.forEach.call(lista.querySelectorAll('.ia-history-item'), function (b) {
                                        b.classList.remove('ia-ativo');
                                    });
                                    btn.classList.add('ia-ativo');
                                    pageChat.carregarConversa(parseInt(c.conv_nb_id, 10));
                                });
                                lista.appendChild(btn);
                            });
                        }).catch(function () {});
                    }
                }
            });
            initPage(pageChat);
        }

        if (widgetMessages) {
            var widgetChat = createChat({
                root: document.getElementById('iaWidget'),
                messages: widgetMessages,
                form: document.getElementById('iaWidgetForm'),
                input: document.getElementById('iaWidgetInput'),
                suggestions: document.getElementById('iaWidgetSuggestions')
            });
            initWidget(widgetChat);
        }
    });
})();
