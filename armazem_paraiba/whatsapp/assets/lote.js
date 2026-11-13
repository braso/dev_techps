/* Envio em lote — assistente em 4 passos */
(function () {
    'use strict';

    var cfg = window.WA_LOTE || {};
    var passo = 1;
    var contatos = [];
    var selecionados = {};
    var loteId = 0;
    var timerLote = null;

    function $(id) { return document.getElementById(id); }
    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function apiUrl(params) {
        var qs = [];
        Object.keys(params || {}).forEach(function (k) {
            var key = (k === 'acao') ? 'wa_acao' : k;
            qs.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[k]));
        });
        return cfg.api + (qs.length ? '?' + qs.join('&') : '');
    }
    function apiGet(params) { return fetch(apiUrl(params), { headers: { 'X-CSRF-Token': cfg.csrf }, cache: 'no-store' }).then(function (r) { return r.json(); }); }
    function apiPost(params, data) {
        var body = new URLSearchParams();
        body.append('csrf', cfg.csrf);
        Object.keys(data || {}).forEach(function (k) {
            var v = data[k];
            if (Array.isArray(v)) v.forEach(function (item) { body.append(k + '[]', item); });
            else body.append(k, v);
        });
        return fetch(apiUrl(params), {
            method: 'POST',
            headers: { 'X-CSRF-Token': cfg.csrf, 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    function totalSelecionados() { return Object.keys(selecionados).length; }

    function atualizarIndicadores() {
        var total = totalSelecionados();
        if ($('loteContador')) $('loteContador').textContent = total + ' selecionado(s)';
        var marcados = document.querySelectorAll('.lote-check-contato:checked').length;
        if ($('loteCheckTodos')) $('loteCheckTodos').checked = marcados > 0 && marcados === document.querySelectorAll('.lote-check-contato:not(:disabled)').length;
    }

    function renderContatos() {
        var alvo = $('loteLista');
        if (!alvo) return;
        if (!contatos.length) {
            alvo.innerHTML = '<div style="padding:24px;text-align:center;color:#94a3b8;font-size:12px;">Nenhum contato encontrado com os filtros atuais.</div>';
            atualizarIndicadores();
            return;
        }
        var html = '<table class="wa-lote-tabela"><thead><tr><th style="width:32px;"></th><th>Nome</th><th>Telefone</th><th>Setor / Cargo</th><th>Opt-in</th></tr></thead><tbody>';
        contatos.forEach(function (c) {
            var id = String(c.cont_nb_id);
            var marcado = !!selecionados[id];
            html += '<tr>' +
                '<td><input type="checkbox" class="lote-check-contato" value="' + esc(id) + '" ' + (marcado ? 'checked' : '') + '></td>' +
                '<td><strong>' + esc(c.cont_tx_nome) + '</strong></td>' +
                '<td>' + esc(c.cont_tx_e164 || c.cont_tx_telefone || '') + '</td>' +
                '<td><span style="color:#64748b;font-size:10.5px;">' + esc(c.cont_tx_setor || '') + (c.cont_tx_cargo ? ' · ' + esc(c.cont_tx_cargo) : '') + '</span></td>' +
                '<td><span class="wa-badge-lote ' + (c.cont_tx_optin === 'sim' ? 'wa-badge-enviado' : 'wa-badge-pendente') + '">' + (c.cont_tx_optin === 'sim' ? 'Sim' : 'Não') + '</span></td>' +
            '</tr>';
        });
        html += '</tbody></table>';
        alvo.innerHTML = html;
        atualizarIndicadores();
    }

    function carregarContatos() {
        var params = {
            acao: 'contatos_lote',
            busca: ($('loteBusca') || {}).value || '',
            empresa: ($('loteEmpresa') || {}).value || 0,
            setor: ($('loteSetor') || {}).value || '',
            cargo: ($('loteCargo') || {}).value || '',
            somente_optin: ($('loteSomenteOptin') && $('loteSomenteOptin').checked) ? 1 : 0
        };
        apiGet(params).then(function (data) {
            if (!data.success) {
                alert(data.message || 'Não foi possível carregar os contatos.');
                return;
            }
            contatos = data.contatos || [];
            var idsValidos = {};
            contatos.forEach(function (c) { idsValidos[String(c.cont_nb_id)] = true; });
            Object.keys(selecionados).forEach(function (id) { if (!idsValidos[id]) delete selecionados[id]; });
            renderContatos();
        }).catch(function () {
            alert('Falha de comunicação ao carregar os contatos.');
        });
    }

    function mostrarPasso(n) {
        passo = n;
        document.querySelectorAll('.wa-step-pane').forEach(function (el) {
            el.classList.toggle('active', parseInt(el.getAttribute('data-step'), 10) === n);
        });
        document.querySelectorAll('.wa-step').forEach(function (el) {
            var i = parseInt(el.getAttribute('data-step-indicator'), 10);
            el.classList.toggle('active', i === n);
            el.classList.toggle('done', i < n);
        });
        var voltar = $('loteVoltar');
        if (voltar) voltar.style.visibility = n > 1 && n < 4 ? 'visible' : 'hidden';
        var avancar = $('loteAvancar');
        if (avancar) {
            avancar.style.display = n >= 4 ? 'none' : 'inline-block';
            avancar.innerHTML = n === 3 ? '<i class="fa fa-send"></i> Enviar lote' : 'Avançar <i class="fa fa-arrow-right"></i>';
        }
        if (n === 3) montarResumo();
    }

    function primeiroContatoSelecionado() {
        var ids = Object.keys(selecionados);
        if (!ids.length) return null;
        for (var i = 0; i < contatos.length; i++) {
            if (selecionados[String(contatos[i].cont_nb_id)]) return contatos[i];
        }
        return null;
    }

    function personalizar(texto, c) {
        if (!c) return texto;
        var nome = c.cont_tx_nome || '';
        var primeiro = nome.split(/\s+/)[0] || nome;
        return String(texto || '')
            .replace(/\{nome\}/g, nome)
            .replace(/\{primeiro_nome\}/g, primeiro)
            .replace(/\{setor\}/g, c.cont_tx_setor || '')
            .replace(/\{cargo\}/g, c.cont_tx_cargo || '')
            .replace(/\{empresa\}/g, c.cont_tx_empresa_nome || '')
            .replace(/\{telefone\}/g, c.cont_tx_e164 || c.cont_tx_telefone || '');
    }

    function montarPreview() {
        var texto = ($('loteMensagem') || {}).value || '';
        var alvo = $('lotePreviewMensagem');
        if (!alvo) return;
        var c = primeiroContatoSelecionado();
        var exibicao = texto ? personalizar(texto, c) : 'A prévia aparece aqui conforme você digita.';
        alvo.innerHTML = '<small>Prévia' + (c ? ' — ' + esc(c.cont_tx_nome) : '') + '</small>' + esc(exibicao);
    }

    function montarResumo() {
        var total = 0, optin = 0;
        var linhas = [];
        Object.keys(selecionados).forEach(function (id) {
            for (var i = 0; i < contatos.length; i++) {
                if (String(contatos[i].cont_nb_id) === String(id)) {
                    total++;
                    if (contatos[i].cont_tx_optin === 'sim') optin++;
                    linhas.push(contatos[i]);
                    break;
                }
            }
        });
        if ($('resumoTotal')) $('resumoTotal').textContent = total;
        if ($('resumoOptin')) $('resumoOptin').textContent = optin;
        if ($('resumoSemOptin')) $('resumoSemOptin').textContent = Math.max(0, total - optin);
        var c = linhas[0] || null;
        if ($('resumoMensagem')) {
            $('resumoMensagem').innerHTML = '<small>Mensagem personalizada para ' + esc(c ? c.cont_tx_nome : 'o primeiro contato') + '</small>' + esc(personalizar(($('loteMensagem') || {}).value || '', c));
        }
        var alvo = $('loteResumoLista');
        if (alvo) {
            var html = '<table class="wa-lote-tabela"><thead><tr><th>Nome</th><th>Telefone</th><th>Opt-in</th></tr></thead><tbody>';
            linhas.forEach(function (l) {
                html += '<tr><td>' + esc(l.cont_tx_nome) + '</td><td>' + esc(l.cont_tx_e164 || l.cont_tx_telefone || '') + '</td><td>' + (l.cont_tx_optin === 'sim' ? 'Sim' : 'Não') + '</td></tr>';
            });
            html += '</tbody></table>';
            alvo.innerHTML = total ? html : '<div style="padding:16px;text-align:center;color:#94a3b8;font-size:12px;">Nenhum contato selecionado.</div>';
        }
    }

    function avancar() {
        if (passo === 1) {
            if (!totalSelecionados()) {
                alert('Selecione pelo menos um destinatário.');
                return;
            }
            mostrarPasso(2);
            montarPreview();
            return;
        }
        if (passo === 2) {
            if (!(($('loteMensagem') || {}).value || '').trim()) {
                alert('Escreva a mensagem do lote.');
                return;
            }
            mostrarPasso(3);
            return;
        }
        if (passo === 3) {
            enviarLote();
        }
    }

    function enviarLote() {
        var ids = Object.keys(selecionados);
        if (!ids.length) {
            alert('Selecione pelo menos um destinatário.');
            mostrarPasso(1);
            return;
        }
        var permitir = $('lotePermitirOptout') && $('lotePermitirOptout').checked ? 1 : 0;
        var botao = $('loteAvancar');
        if (botao) { botao.disabled = true; botao.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Enviando...'; }

        apiPost({ acao: 'criar_lote' }, {
            ids: ids,
            titulo: ($('loteTitulo') || {}).value || '',
            mensagem: ($('loteMensagem') || {}).value || '',
            permitir_optout: permitir
        }).then(function (data) {
            if (!data.success) {
                alert(data.message || 'Não foi possível criar o lote.');
                if (botao) { botao.disabled = false; botao.innerHTML = '<i class="fa fa-send"></i> Enviar lote'; }
                return;
            }
            loteId = parseInt(data.lote_id || 0, 10);
            var exportar = $('loteExportar');
            if (exportar && loteId) {
                exportar.href = window.location.pathname + '?exportar=' + loteId;
                exportar.style.display = 'inline-block';
            }
            mostrarPasso(4);
            if ($('loteStatusTexto')) $('loteStatusTexto').textContent = 'Lote criado. Aguardando o processamento da fila...';
            if (data.sem_telefone || data.sem_optin) {
                if ($('loteStatusTexto')) $('loteStatusTexto').textContent += ' Ignorados: ' + (data.sem_telefone || 0) + ' sem telefone e ' + (data.sem_optin || 0) + ' sem opt-in.';
            }
            atualizarLote();
            timerLote = setInterval(atualizarLote, 5000);
        }).catch(function () {
            alert('Falha de comunicação ao criar o lote.');
            if (botao) { botao.disabled = false; botao.innerHTML = '<i class="fa fa-send"></i> Enviar lote'; }
        });
    }

    function atualizarLote() {
        if (!loteId) return;
        apiGet({ acao: 'status_lote', lote: loteId }).then(function (data) {
            if (!data.success) return;
            var lote = data.lote || {};
            var destinatarios = data.destinatarios || [];
            var total = parseInt(lote.lote_nb_total || 0, 10);
            var enviados = parseInt(lote.lote_nb_enviados || 0, 10);
            var falhas = parseInt(lote.lote_nb_falhas || 0, 10);
            var finalizados = enviados + falhas;
            if ($('loteTotal')) $('loteTotal').textContent = total;
            if ($('loteEnviados')) $('loteEnviados').textContent = enviados;
            if ($('loteFalhas')) $('loteFalhas').textContent = falhas;
            var pct = total > 0 ? Math.round((finalizados / total) * 100) : 0;
            if ($('loteProgresso')) $('loteProgresso').style.width = pct + '%';
            if ($('loteStatusTexto')) {
                $('loteStatusTexto').textContent = lote.lote_tx_status === 'concluido'
                    ? 'Lote concluído.'
                    : 'Processando fila: ' + finalizados + ' de ' + total + ' finalizados.';
            }
            var alvo = $('loteResultado');
            if (alvo) {
                var html = '<table class="wa-lote-tabela"><thead><tr><th>Nome</th><th>Telefone</th><th>Status</th><th>Erro</th></tr></thead><tbody>';
                destinatarios.forEach(function (d) {
                    var cls = d.ld_tx_status === 'falhou' ? 'wa-badge-falhou' : (d.ld_tx_status === 'pendente' ? 'wa-badge-pendente' : 'wa-badge-enviado');
                    html += '<tr><td>' + esc(d.cont_tx_nome || '') + '</td><td>' + esc(d.ld_tx_telefone || '') + '</td>' +
                        '<td><span class="wa-badge-lote ' + cls + '">' + esc(d.ld_tx_status || '') + '</span></td>' +
                        '<td style="color:#b91c1c;font-size:10.5px;">' + esc(d.ld_tx_erro || '') + '</td></tr>';
                });
                html += '</tbody></table>';
                alvo.innerHTML = html;
            }
            if (lote.lote_tx_status === 'concluido' && timerLote) {
                clearInterval(timerLote);
                timerLote = null;
                if ($('loteStatusTexto')) $('loteStatusTexto').textContent = 'Lote concluído. Você pode voltar para os contatos ou iniciar outro lote.';
            }
        }).catch(function () {});
    }

    function iniciar() {
        if ($('loteCarregar')) $('loteCarregar').addEventListener('click', carregarContatos);
        if ($('loteAvancar')) $('loteAvancar').addEventListener('click', avancar);
        if ($('loteVoltar')) {
            $('loteVoltar').addEventListener('click', function () {
                if (passo > 1) mostrarPasso(passo - 1);
            });
        }
        if ($('loteCheckTodos')) {
            $('loteCheckTodos').addEventListener('change', function () {
                var marcar = $('loteCheckTodos').checked;
                document.querySelectorAll('.lote-check-contato').forEach(function (c) {
                    c.checked = marcar;
                    if (marcar) selecionados[c.value] = true; else delete selecionados[c.value];
                });
                atualizarIndicadores();
            });
        }
        document.addEventListener('change', function (e) {
            if (e.target.classList && e.target.classList.contains('lote-check-contato')) {
                if (e.target.checked) selecionados[e.target.value] = true; else delete selecionados[e.target.value];
                atualizarIndicadores();
            }
        });
        if ($('loteMensagem')) $('loteMensagem').addEventListener('input', montarPreview);

        if ($('loteProcessarFila')) {
            $('loteProcessarFila').addEventListener('click', function () {
                var btn = $('loteProcessarFila');
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processando...';
                apiPost({ acao: 'processar_fila' }, {}).then(function (data) {
                    if (!data.success) alert(data.message || 'Não foi possível processar a fila.');
                    atualizarLote();
                }).catch(function () {
                    alert('Falha de comunicação.');
                }).finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-play"></i> Processar fila agora';
                });
            });
        }

        if ((cfg.idsIniciais || []).length) {
            cfg.idsIniciais.forEach(function (id) { selecionados[String(id)] = true; });
        }
        carregarContatos();
    }

    document.addEventListener('DOMContentLoaded', iniciar);
})();
