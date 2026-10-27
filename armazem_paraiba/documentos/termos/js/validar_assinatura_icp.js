/**
 * Validação compartilhada do modo Assinatura/ICP.
 *
 * Consulta o endpoint verificar_assinatura_icp.php e, quando o tipo de
 * documento do modelo está com Assinatura = Não, exibe um alerta com
 * atalho para abrir o cadastro do tipo e corrigir o campo.
 */
(function (global) {
    'use strict';

    function garantirZIndexAlerta() {
        var doc = global.document;
        if (!doc || doc.getElementById('termos-swal-zindex')) {
            return;
        }
        var style = doc.createElement('style');
        style.id = 'termos-swal-zindex';
        style.type = 'text/css';
        // O tema usa z-index 10050 nos modais Bootstrap; o padrão do SweetAlert (1060)
        // ficaria atrás do modal. Esta regra garante que o alerta apareça na frente.
        style.appendChild(doc.createTextNode(
            '.swal2-container{z-index:20000 !important;}' +
            '.swal2-container .swal2-popup{z-index:20001 !important;}'
        ));
        (doc.head || doc.getElementsByTagName('head')[0] || doc.body).appendChild(style);
    }

    function escaparHtml(valor) {
        return String(valor == null ? '' : valor).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function normalizarModelos(modelos) {
        var lista = [];
        var vistos = {};
        if (modelos == null) {
            return lista;
        }
        if (!Array.isArray(modelos)) {
            modelos = [modelos];
        }
        modelos.forEach(function (item) {
            var valor = (item && typeof item === 'object') ? (item.id || item.modelo_id) : item;
            var id = parseInt(valor, 10);
            if (id > 0 && !vistos[id]) {
                vistos[id] = true;
                lista.push(id);
            }
        });
        return lista;
    }

    function urlTipoDocumento(base, tipoId) {
        if (!base || !tipoId) {
            return '';
        }
        var separador = base.indexOf('?') >= 0 ? '&' : '?';
        return base + separador + 'acao=modificarTipoDoc%28%29&id=' + encodeURIComponent(tipoId);
    }

    function mostrarAlerta(titulo, html, tipoParaAbrir, opcoes) {
        if (global.Swal && typeof global.Swal.fire === 'function') {
            var config = {
                icon: 'warning',
                title: titulo,
                html: html,
                confirmButtonText: 'Fechar',
                allowOutsideClick: false,
                heightAuto: false,
                scrollbarPadding: false,
                target: global.document.body
            };
            if (tipoParaAbrir && tipoParaAbrir.editar_url) {
                config.showCancelButton = true;
                config.confirmButtonText = 'Abrir tipo de documento';
                config.cancelButtonText = 'Fechar';
            }
            return global.Swal.fire(config).then(function (resultado) {
                if (resultado && resultado.isConfirmed && tipoParaAbrir && tipoParaAbrir.editar_url) {
                    global.open(tipoParaAbrir.editar_url, '_blank', 'noopener');
                }
                if (typeof opcoes.onBlocked === 'function') {
                    opcoes.onBlocked(tipoParaAbrir, resultado);
                }
            });
        }

        var texto = html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        var abrir = tipoParaAbrir && tipoParaAbrir.editar_url
            ? global.confirm(texto + '\n\nDeseja abrir o tipo de documento para corrigir?')
            : (global.alert(texto), false);
        if (abrir) {
            global.open(tipoParaAbrir.editar_url, '_blank', 'noopener');
        }
        if (typeof opcoes.onBlocked === 'function') {
            opcoes.onBlocked(tipoParaAbrir, null);
        }
        return null;
    }

    function montarMensagemBloqueio(tiposInvalidos) {
        var html = [];
        html.push('<div style="text-align:left;line-height:1.5;">');
        html.push('<p style="margin:0 0 8px 0;">Para usar <b>Assinatura/ICP</b>, o tipo de documento precisa estar com <b>Assinatura = Sim</b>. Altere o tipo, salve e volte para enviar novamente.</p>');

        if (tiposInvalidos.length === 1) {
            var tipo = tiposInvalidos[0];
            html.push('<p style="margin:0;">Tipo de documento: <b>' + escaparHtml(tipo.tipo_nome || ('#' + tipo.tipo_id)) + '</b> — Assinatura = <b>Não</b>.</p>');
            if (tipo.modelos && tipo.modelos.length) {
                var nomes = tipo.modelos.map(function (m) {
                    return escaparHtml(m.nome || ('#' + m.id));
                });
                html.push('<p style="margin:6px 0 0 0;font-size:12px;color:#666;">Modelo(s): ' + nomes.join(', ') + '</p>');
            }
            if (tipo.editar_url) {
                html.push('<p style="margin:10px 0 0 0;"><a href="' + escaparHtml(tipo.editar_url) + '" target="_blank" rel="noopener" style="font-weight:bold;">Abrir tipo de documento</a></p>');
            }
        } else {
            html.push('<p style="margin:0 0 4px 0;">Os seguintes tipos estão com <b>Assinatura = Não</b>:</p>');
            html.push('<ul style="margin:6px 0 0 18px;padding:0;">');
            tiposInvalidos.forEach(function (tipo) {
                html.push('<li style="margin-bottom:6px;"><b>' + escaparHtml(tipo.tipo_nome || ('#' + tipo.tipo_id)) + '</b>');
                if (tipo.editar_url) {
                    html.push(' — <a href="' + escaparHtml(tipo.editar_url) + '" target="_blank" rel="noopener">abrir tipo de documento</a>');
                }
                html.push('</li>');
            });
            html.push('</ul>');
        }
        html.push('<p style="margin:10px 0 0 0;color:#b45309;font-size:12px;"><b>O envio com Assinatura/ICP não será realizado até a correção.</b></p>');
        html.push('</div>');
        return html.join('');
    }

    function verificar(opcoes) {
        opcoes = opcoes || {};
        garantirZIndexAlerta();
        var modelos = normalizarModelos(opcoes.modelos);
        if (!modelos.length) {
            if (typeof opcoes.onSuccess === 'function') {
                opcoes.onSuccess({ ok: true, tipos: [] });
            }
            return;
        }

        var endpoint = opcoes.endpoint || global.TERMOS_ICP_ENDPOINT || '';
        if (!endpoint) {
            if (typeof opcoes.onError === 'function') {
                opcoes.onError();
            }
            return;
        }

        var baseTipo = opcoes.tipoEditBase || global.TERMOS_TIPO_DOC_EDIT_BASE || '';

        global.jQuery.ajax({
            url: endpoint,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ modelos: modelos }),
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.ok) {
                var msg = (res && res.error) ? res.error : 'Não foi possível verificar o tipo de documento.';
                if (global.Swal) {
                    global.Swal.fire('Erro', msg, 'error');
                } else {
                    global.alert(msg);
                }
                if (typeof opcoes.onError === 'function') {
                    opcoes.onError(res);
                }
                return;
            }

            var tipos = Array.isArray(res.tipos) ? res.tipos : [];
            tipos.forEach(function (tipo) {
                if (!tipo.editar_url && baseTipo && tipo.tipo_id) {
                    tipo.editar_url = urlTipoDocumento(baseTipo, tipo.tipo_id);
                }
            });

            if (Array.isArray(res.erros) && res.erros.length) {
                var msgErro = res.erros.join('<br>');
                if (global.Swal) {
                    global.Swal.fire({
                        icon: 'error',
                        title: 'Não foi possível validar',
                        html: msgErro,
                        confirmButtonText: 'Fechar'
                    }).then(function () {
                        if (typeof opcoes.onBlocked === 'function') {
                            opcoes.onBlocked(null, null);
                        }
                    });
                } else {
                    global.alert(msgErro.replace(/<[^>]*>/g, ' '));
                    if (typeof opcoes.onBlocked === 'function') {
                        opcoes.onBlocked(null, null);
                    }
                }
                return;
            }

            var invalidos = tipos.filter(function (tipo) {
                return String(tipo.tipo_assinatura || 'nao').toLowerCase() !== 'sim';
            });
            if (!invalidos.length) {
                if (typeof opcoes.onSuccess === 'function') {
                    opcoes.onSuccess(res);
                }
                return;
            }

            var html = montarMensagemBloqueio(invalidos);
            var primeiro = invalidos.length === 1 ? invalidos[0] : null;
            mostrarAlerta('Assinatura/ICP indisponível', html, primeiro, opcoes);
        }).fail(function () {
            var msg = 'Falha de comunicação ao verificar o tipo de documento. Tente novamente.';
            if (global.Swal) {
                global.Swal.fire('Erro', msg, 'error');
            } else {
                global.alert(msg);
            }
            if (typeof opcoes.onError === 'function') {
                opcoes.onError();
            }
        });
    }

    garantirZIndexAlerta();

    global.TermosIcp = { verificar: verificar };
})(window);
