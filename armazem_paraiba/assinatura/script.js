
// Variável global para armazenar os bytes do PDF carregado
let currentPdfBytes = null;
let currentFileName = "documento.pdf";

// Função global para lidar com a seleção de arquivo
window.handleFileSelect = async function(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        currentFileName = file.name;
        
        const fileNameElement = document.getElementById('fileName');
        if (fileNameElement) fileNameElement.textContent = file.name;
        
        const btnUpload = document.getElementById('btnUpload');
        if (btnUpload) btnUpload.disabled = false;
        
        // Lê o arquivo como ArrayBuffer
        currentPdfBytes = await file.arrayBuffer();
        
        // Cria URL para preview
        const blob = new Blob([currentPdfBytes], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        
        const pdfPreview = document.getElementById('pdfPreview');
        if (pdfPreview) pdfPreview.src = url;
    }
};

// Nova função para carregar PDF via URL (para fluxo de assinatura por e-mail)
window.loadPdfFromUrl = async function(url) {
    const openPdfInNewTab = document.getElementById('openPdfInNewTab');
    if (openPdfInNewTab) {
        openPdfInNewTab.href = url;
    }
    try {
        const response = await fetch(url, { credentials: 'same-origin' });
        if (!response.ok) throw new Error("Erro ao baixar o PDF");
        const contentType = String(response.headers.get('content-type') || '').toLowerCase();
        if (contentType.indexOf('application/pdf') === -1) {
            throw new Error("Resposta não é PDF");
        }
        
        const blob = await response.blob();
        currentPdfBytes = await blob.arrayBuffer();
        
        // Cria URL para preview
        const previewUrl = URL.createObjectURL(blob);
        const pdfPreview = document.getElementById('pdfPreview');
        if (pdfPreview) pdfPreview.src = previewUrl;

        // Atualiza interface para estado de "Pronto para assinar"
        const uploadStep = document.getElementById('uploadStep');
        const assinaturaStep = document.getElementById('assinaturaStep');
        if (uploadStep && assinaturaStep) {
            uploadStep.style.display = 'none';
            assinaturaStep.style.display = 'flex';
        }
        currentFileName = "documento_solicitado.pdf";

    } catch (error) {
        console.error("Erro ao carregar PDF da URL:", error);
        const pdfPreview = document.getElementById('pdfPreview');
        if (pdfPreview) {
            pdfPreview.src = url + (url.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
        } else {
            alert("Não foi possível carregar o documento solicitado. Tente recarregar a página.");
        }
    }
};

document.addEventListener('DOMContentLoaded', function() {
    // Verifica se existe um PDF para carregar automaticamente (definido no HTML)
    if (window.PDF_URL_TO_LOAD) {
        window.loadPdfFromUrl(window.PDF_URL_TO_LOAD);
    }

    const form = document.getElementById('formAssinatura');
    const btnAssinar = document.getElementById('btnAssinar');
    const btnUpload = document.getElementById('btnUpload');
    const uploadStep = document.getElementById('uploadStep');
    const assinaturaStep = document.getElementById('assinaturaStep');

    // Atualiza o texto do termo dinamicamente
    const nomeInput = document.getElementById('nome');
    const cpfInput = document.getElementById('cpf');
    const rgInput = document.getElementById('rg');
    const nomeTermo = document.getElementById('nome_termo');
    const cpfTermo = document.getElementById('cpf_termo');

    if (nomeInput && nomeTermo) {
        nomeInput.addEventListener('input', function() {
            nomeTermo.textContent = this.value || '[Nome do Usuário]';
        });
        // Inicializa se já tiver valor (preenchido pelo PHP)
        if (nomeInput.value) {
             nomeTermo.textContent = nomeInput.value;
        }
    }

    const cpfDigits = (v) => String(v || '').replace(/\D/g, '').slice(0, 11);
    const cpfFormat = (d) => {
        const s = String(d || '');
        if (s.length <= 3) return s;
        if (s.length <= 6) return s.slice(0, 3) + '.' + s.slice(3);
        if (s.length <= 9) return s.slice(0, 3) + '.' + s.slice(3, 6) + '.' + s.slice(6);
        return s.slice(0, 3) + '.' + s.slice(3, 6) + '.' + s.slice(6, 9) + '-' + s.slice(9, 11);
    };
    const rgDigits = (v) => String(v || '').replace(/\D/g, '').slice(0, 11);
    const rgFormat = (digits) => {
        const d = String(digits || '');
        if (!d) return '';
        const p1 = d.slice(0, 3);
        const p2 = d.slice(3, 6);
        const p3 = d.slice(6, 9);
        const p4 = d.slice(9, 11);
        let out = p1;
        if (p2) out += '.' + p2;
        if (p3) out += '.' + p3;
        if (p4) out += '-' + p4;
        return out;
    };

    if (cpfInput) {
        cpfInput.maxLength = 14;
        cpfInput.setAttribute('inputmode', 'numeric');
        cpfInput.setAttribute('autocomplete', 'off');
        const syncCpf = () => {
            const d = cpfDigits(cpfInput.value);
            const f = cpfFormat(d);
            cpfInput.value = f;
            if (cpfTermo) {
                cpfTermo.textContent = f || '[XXX.XXX.XXX-XX]';
            }
        };
        cpfInput.addEventListener('input', syncCpf);
        cpfInput.addEventListener('blur', syncCpf);
        syncCpf();
    } else if (cpfTermo) {
        cpfTermo.textContent = '[XXX.XXX.XXX-XX]';
    }

    if (rgInput) {
        rgInput.maxLength = 14;
        rgInput.setAttribute('autocomplete', 'off');
        rgInput.setAttribute('inputmode', 'numeric');
        const syncRg = () => {
            rgInput.value = rgFormat(rgDigits(rgInput.value));
        };
        rgInput.addEventListener('input', syncRg);
        rgInput.addEventListener('blur', syncRg);
        syncRg();
    }

    // Configura transição de tela se estiver no modo upload
    if (btnUpload) {
        btnUpload.addEventListener('click', function(e) {
            e.preventDefault();
            if (currentPdfBytes) {
                uploadStep.style.display = 'none';
                assinaturaStep.style.display = 'flex';
            } else {
                alert("Selecione um arquivo PDF primeiro.");
            }
        });
    }

    // Captura informações do dispositivo
    const deviceInfo = document.getElementById('device_info');
    if (deviceInfo) deviceInfo.value = navigator.userAgent;

    // Tenta capturar geolocalização
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = document.getElementById('latitude');
                const lon = document.getElementById('longitude');
                if (lat) lat.value = position.coords.latitude;
                if (lon) lon.value = position.coords.longitude;
            },
            function(error) {
                console.warn("Geolocalização não permitida:", error.message);
            }
        );
    }

    // Manipulador do envio do formulário
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const nome = document.getElementById('nome').value;
            const cpf = document.getElementById('cpf').value;
            const rg = document.getElementById('rg').value;
            const aceite = document.getElementById('aceite').checked;

            if (!nome || !cpf || !rg || !aceite) {
                alert("Por favor, preencha todos os campos e aceite os termos.");
                return;
            }

            if (!currentPdfBytes) {
                alert("Nenhum documento carregado para assinar.");
                return;
            }

            btnAssinar.disabled = true;
            btnAssinar.textContent = "Processando e Carimbando...";

            try {
                // 1. Modificar o PDF (Carimbar) usando pdf-lib
                const { PDFDocument, rgb, StandardFonts } = PDFLib;
                const pdfDoc = await PDFDocument.load(currentPdfBytes);
                
                // Carrega a logo
                let logoImage;
                try {
                    const logoBytes = await fetch('assets/logo.png').then(res => res.arrayBuffer());
                    logoImage = await pdfDoc.embedPng(logoBytes);
                } catch (e) {
                    console.warn("Logo não encontrada ou erro ao carregar:", e);
                }

                // Incorpora fonte
                const helveticaFont = await pdfDoc.embedFont(StandardFonts.Helvetica);
                const helveticaBold = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
                const courierFont = await pdfDoc.embedFont(StandardFonts.Courier);
                
                // Gera dados para consistência (Hash e Data)
                const dataObj = new Date();
                const dataHora = dataObj.toLocaleString('pt-BR');
                const dataISO = dataObj.toISOString();
                
                // Recupera nome original do arquivo
                const nomeArquivoOriginalInput = document.getElementById('nome_arquivo_original');
                const nomeArquivoOriginal = nomeArquivoOriginalInput ? nomeArquivoOriginalInput.value : currentFileName;

                // Gera um ID de documento se não existir
                let docId = document.getElementById('id_documento').value;
                if (!docId) {
                    docId = 'DOC-' + dataObj.getFullYear() + Math.floor(Math.random() * 100000);
                    document.getElementById('id_documento').value = docId;
                }

                // Obtém IP do cliente
                let clientIp = 'IP não identificado';
                try {
                    const ipResponse = await fetch('get_ip.php');
                    const ipData = await ipResponse.json();
                    if (ipData.ip) clientIp = ipData.ip;
                } catch (e) {
                    console.warn('Não foi possível obter o IP:', e);
                }

                // Gera um Hash simulado (MD5-like)
                const uniqueString = docId + cpf + dataISO + Math.random();
                const simpleHash = (str) => {
                    let hash = 0;
                    for (let i = 0; i < str.length; i++) {
                        const char = str.charCodeAt(i);
                        hash = (hash << 5) - hash + char;
                        hash = hash & hash;
                    }
                    return Math.abs(hash).toString(16);
                };
                // Gera string de 32 caracteres hexadecimais
                const protocolHash = (simpleHash(uniqueString) + simpleHash(uniqueString + 'salt') + '00000000000000000000000000000000').substring(0, 32);

                const papelUsuario = document.getElementById('papel_usuario') ? document.getElementById('papel_usuario').value : 'Signatário';

                const auditPrefix = 'TECHPS_AUDIT_V1:';
                const safeB64Encode = (str) => {
                    try { return btoa(unescape(encodeURIComponent(str))); } catch (e) { return ''; }
                };
                const safeB64Decode = (b64) => {
                    try { return decodeURIComponent(escape(atob(b64))); } catch (e) { return ''; }
                };
                const readAuditState = () => {
                    let rawPacked = '';
                    try { rawPacked = String(pdfDoc.getSubject() || ''); } catch (e) { rawPacked = ''; }
                    if (!rawPacked || !rawPacked.startsWith(auditPrefix)) {
                        try { rawPacked = String(pdfDoc.getTitle() || ''); } catch (e) { rawPacked = ''; }
                    }
                    if (!rawPacked || !rawPacked.startsWith(auditPrefix)) return null;
                    const raw = rawPacked.slice(auditPrefix.length);
                    const json = safeB64Decode(raw);
                    if (!json) return null;
                    try { return JSON.parse(json); } catch (e) { return null; }
                };
                const writeAuditState = (state) => {
                    const payload = safeB64Encode(JSON.stringify(state || {}));
                    if (!payload) return;
                    try { pdfDoc.setSubject(auditPrefix + payload); } catch (e) {}
                    try { pdfDoc.setTitle(auditPrefix + payload); } catch (e) {}
                };
                const removePreviousAuditPages = (auditPages) => {
                    const total = pdfDoc.getPageCount();
                    const n = Number.isFinite(auditPages) ? Math.max(0, Math.floor(auditPages)) : 0;
                    if (n <= 0) return;
                    if (total <= n) return;
                    for (let i = 0; i < n; i++) {
                        const idx = pdfDoc.getPageCount() - 1;
                        if (idx >= 0) pdfDoc.removePage(idx);
                    }
                };
                const wrapText = (text, font, size, maxWidth, maxLines = 99) => {
                    const t = String(text || '').replace(/\s+/g, ' ').trim();
                    if (!t) return [];
                    const words = t.split(' ');
                    const lines = [];
                    let line = '';
                    for (const w of words) {
                        const test = line ? (line + ' ' + w) : w;
                        if (font.widthOfTextAtSize(test, size) <= maxWidth) {
                            line = test;
                            continue;
                        }
                        if (line) lines.push(line);
                        line = w;
                        if (lines.length >= maxLines - 1) break;
                    }
                    if (line && lines.length < maxLines) lines.push(line);
                    return lines;
                };

                const prevState = readAuditState() || {};
                const prevEntries = Array.isArray(prevState.entries) ? prevState.entries : [];
                const prevPages = Number.isFinite(prevState.audit_pages) ? prevState.audit_pages : 0;
                removePreviousAuditPages(prevPages);

                const entries = prevEntries.concat([{
                    nome: String(nome || ''),
                    cpf: String(cpf || ''),
                    rg: String(rg || ''),
                    data_hora: String(dataHora || ''),
                    ip: String(clientIp || ''),
                    user_agent: String(navigator.userAgent || ''),
                    hash: String(protocolHash || ''),
                    papel: String(papelUsuario || '')
                }]);

                const renderAuditPages = () => {
                    const pageMargin = 36;
                    const headerH = 46;
                    const cardPad = 10;
                    const cardGap = 10;
                    const baseFontSize = 8.5;
                    const smallFontSize = 7.5;
                    const titleSize = 11.5;
                    const lineH = 11.5;

                    const makePage = () => {
                        const p = pdfDoc.addPage();
                        const { width, height } = p.getSize();
                        return { p, width, height };
                    };

                    let { p: page, width, height } = makePage();
                    let y = height - pageMargin;
                    const contentW = width - (pageMargin * 2);

                    const drawHeader = () => {
                        const title = 'COMPROVANTES DE ASSINATURA ELETRÔNICA';
                        const docLine = `Documento: ${nomeArquivoOriginal}`;
                        const idLine = `ID: ${docId}`;

                        if (logoImage) {
                            const logoDims = logoImage.scale(0.16);
                            page.drawImage(logoImage, {
                                x: pageMargin,
                                y: y - logoDims.height + 6,
                                width: logoDims.width,
                                height: logoDims.height,
                            });
                        }

                        page.drawText(title, { x: pageMargin + 70, y: y - 2, size: titleSize, font: helveticaBold, color: rgb(0, 0, 0) });
                        y -= 18;
                        const docLines = wrapText(docLine, helveticaFont, baseFontSize, contentW, 2);
                        for (const ln of docLines) {
                            page.drawText(ln, { x: pageMargin, y: y, size: baseFontSize, font: helveticaFont, color: rgb(0.1, 0.1, 0.1) });
                            y -= lineH;
                        }
                        page.drawText(idLine, { x: pageMargin, y: y, size: smallFontSize, font: helveticaFont, color: rgb(0.35, 0.35, 0.35) });
                        y -= 10;
                        page.drawLine({ start: { x: pageMargin, y: y }, end: { x: width - pageMargin, y: y }, thickness: 1, color: rgb(0.85, 0.85, 0.85) });
                        y -= 12;
                    };

                    drawHeader();

                    const drawFooter = () => {
                        const footerY = pageMargin - 18;
                        page.drawLine({ start: { x: pageMargin, y: footerY + 12 }, end: { x: width - pageMargin, y: footerY + 12 }, thickness: 1, color: rgb(0.9, 0.9, 0.9) });
                        page.drawText('TechPS - Tecnologia e Sistemas', { x: pageMargin, y: footerY, size: 7.5, font: helveticaBold, color: rgb(0.4, 0.4, 0.4) });
                        const gen = `Gerado em: ${dataHora}`;
                        page.drawText(gen, { x: width - pageMargin - helveticaFont.widthOfTextAtSize(gen, 7.5), y: footerY, size: 7.5, font: helveticaFont, color: rgb(0.4, 0.4, 0.4) });
                    };

                    const ensureSpace = (neededH) => {
                        const minY = pageMargin + 28;
                        if ((y - neededH) >= minY) return;
                        drawFooter();
                        ({ p: page, width, height } = makePage());
                        y = height - pageMargin;
                        drawHeader();
                    };

                    const drawCard = (entry, idx) => {
                        const headerText = `${idx + 1}. ${entry.nome} (${entry.papel || 'Signatário'})`;
                        const innerW = contentW - (cardPad * 2);
                        const linesUA = wrapText(entry.user_agent || '', helveticaFont, smallFontSize, innerW, 2);
                        const linesHash = wrapText(entry.hash || '', courierFont, smallFontSize, innerW, 2);

                        const rowLines = 6 + linesUA.length + linesHash.length;
                        const cardH = cardPad * 2 + rowLines * (lineH - 0.5) + 6;
                        ensureSpace(cardH + cardGap);

                        const x = pageMargin;
                        const w = contentW;
                        const topY = y;
                        const bottomY = y - cardH;

                        page.drawRectangle({ x, y: bottomY, width: w, height: cardH, borderWidth: 1, borderColor: rgb(0.88, 0.88, 0.88), color: rgb(0.98, 0.98, 0.98) });

                        let cy = topY - cardPad - 2;
                        page.drawText(headerText, { x: x + cardPad, y: cy, size: baseFontSize, font: helveticaBold, color: rgb(0, 0, 0) });
                        cy -= (lineH - 1);

                        const leftX = x + cardPad;
                        const cpfText = entry.cpf ? `CPF: ${entry.cpf}` : '';
                        const rgText = entry.rg ? `RG: ${entry.rg}` : '';
                        const cpfRg = (cpfText && rgText) ? `${cpfText} • ${rgText}` : (cpfText || rgText || '—');
                        page.drawText(cpfRg, { x: leftX, y: cy, size: smallFontSize, font: helveticaFont, color: rgb(0.2, 0.2, 0.2) });
                        cy -= (lineH - 1);

                        const dtLine = `Data/Hora: ${entry.data_hora || '—'}`;
                        page.drawText(dtLine, { x: leftX, y: cy, size: smallFontSize, font: helveticaFont, color: rgb(0.2, 0.2, 0.2) });
                        cy -= (lineH - 1);
                        const ipLine = `IP: ${entry.ip || '—'}`;
                        page.drawText(ipLine, { x: leftX, y: cy, size: smallFontSize, font: helveticaFont, color: rgb(0.2, 0.2, 0.2) });
                        cy -= (lineH - 1);

                        page.drawText('Hash:', { x: leftX, y: cy, size: smallFontSize, font: helveticaBold, color: rgb(0.25, 0.25, 0.25) });
                        let hx = leftX + 28;
                        let hy = cy;
                        for (const ln of linesHash.length ? linesHash : ['—']) {
                            page.drawText(ln, { x: hx, y: hy, size: smallFontSize, font: courierFont, color: rgb(0.15, 0.15, 0.15) });
                            hy -= (lineH - 1);
                        }
                        cy = hy;

                        page.drawText('Navegador:', { x: leftX, y: cy, size: smallFontSize, font: helveticaBold, color: rgb(0.25, 0.25, 0.25) });
                        let ux = leftX + 54;
                        let uy = cy;
                        for (const ln of linesUA.length ? linesUA : ['—']) {
                            page.drawText(ln, { x: ux, y: uy, size: smallFontSize, font: helveticaFont, color: rgb(0.2, 0.2, 0.2) });
                            uy -= (lineH - 1);
                        }

                        y = bottomY - cardGap;
                    };

                    for (let i = 0; i < entries.length; i++) {
                        drawCard(entries[i], i);
                    }

                    const minLegalY = pageMargin + 40;
                    if (y < minLegalY) {
                        drawFooter();
                        ({ p: page, width, height } = makePage());
                        y = height - pageMargin;
                        drawHeader();
                    }

                    const legal = 'Declaração: O(s) signatário(s) declarou(aram) ter lido e concordado com o teor do documento. Validade jurídica assegurada pela MP nº 2.200-2/2001.';
                    const legalLines = wrapText(legal, helveticaFont, 7.2, contentW, 3);
                    for (const ln of legalLines) {
                        page.drawText(ln, { x: pageMargin, y: y, size: 7.2, font: helveticaFont, color: rgb(0.35, 0.35, 0.35) });
                        y -= 9.5;
                    }

                    drawFooter();

                    return pdfDoc.getPageCount();
                };

                const beforePages = pdfDoc.getPageCount();
                const afterPages = renderAuditPages();
                const auditPagesCreated = Math.max(0, afterPages - beforePages);
                writeAuditState({ entries, audit_pages: auditPagesCreated });

                // Salva o PDF modificado
                // useObjectStreams: false é crucial para compatibilidade com FPDI (evita erro de compressão)
                const pdfBytes = await pdfDoc.save({ useObjectStreams: false });
                
                // Cria um Blob para envio - Nome do arquivo original
                const pdfBlob = new Blob([pdfBytes], { type: 'application/pdf' });
                const nomeFinalArquivo = nomeArquivoOriginal.toLowerCase().endsWith('.pdf') 
                    ? nomeArquivoOriginal 
                    : nomeArquivoOriginal + '.pdf';
                    
                const arquivoAssinado = new File([pdfBlob], nomeFinalArquivo, { type: 'application/pdf' });

                // 2. Enviar para o servidor
                const formData = new FormData(form);
                formData.append('arquivo_assinado', arquivoAssinado);
                // Envia os dados gerados para consistência
                formData.append('hash_assinatura', protocolHash);
                formData.append('data_hora', dataISO); // Formato ISO para banco
                formData.append('id_documento', docId); // Garante que o ID no banco é o mesmo do PDF

                const response = await fetch('assinar.php', {
                    method: 'POST',
                    body: formData
                });

                const text = await response.text();
                let data = null;
                try {
                    data = text ? JSON.parse(text) : null;
                } catch (e) {
                    throw new Error(`Resposta inválida do servidor (${response.status}). ${text.substring(0, 200)}`);
                }
                if (!data) {
                    throw new Error(`Resposta vazia do servidor (${response.status}).`);
                }

                if (!response.ok) {
                    const msg = (data && data.message) ? data.message : `Erro ${response.status}`;
                    const det = (data && data.detail) ? `\n\nDetalhe: ${data.detail}` : '';
                    throw new Error(msg + det);
                }


                if (data.status === 'success') {
                    // Atualiza a interface
                    const container = document.getElementById('main-container') || document.querySelector('.container');
                    
                    if (!container) {
                        console.error('Container principal não encontrado para exibir sucesso.');
                        alert('Sucesso! Documento assinado. Protocolo: ' + data.protocolo);
                        return;
                    }

                    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    }[ch] || ch));
                    
                    // Gera URL para download direto do Blob local (mais rápido e garantido)
                    const blobUrl = URL.createObjectURL(pdfBlob);
                    
                    // Usa o caminho retornado pelo servidor se disponível, senão tenta adivinhar (fallback)
                    const linkArquivoServidor = data.caminho_arquivo ? data.caminho_arquivo : `docAssinado/assinado_${data.protocolo}.pdf`;
                    const isIcpFinal = (linkArquivoServidor || '').includes('docFinalizado/');
                    const primaryHref = isIcpFinal ? linkArquivoServidor : blobUrl;
                    const primaryText = isIcpFinal ? 'Baixar Documento (ICP-Brasil)' : 'Baixar Documento (Agora)';
                    const secondaryHref = isIcpFinal ? blobUrl : linkArquivoServidor;
                    const secondaryText = isIcpFinal ? 'Baixar Comprovante (Visual)' : 'Baixar do Servidor (Backup)';
                    const secondaryIcon = isIcpFinal ? 'fa-file-pdf' : 'fa-cloud-download-alt';
                    const modoEnvio = String(data.modo_envio || '').toLowerCase().trim();
                    const isGovernanca = modoEnvio === 'governanca';
                    const isFinal = !!data.is_final;
                    const pendentesList = Array.isArray(data.pendentes) ? data.pendentes : [];
                    const pendentesCount = pendentesList.length;
                    const pendentesTitulo = pendentesCount === 1 ? 'Ainda falta 1 assinatura' : `Ainda faltam ${pendentesCount} assinaturas`;
                    const pendentesHtml = (!isFinal && pendentesList.length > 0)
                        ? `
                            <div class="mt-6 max-w-md mx-auto text-left">
                                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                                    <div class="text-sm font-semibold text-amber-900 mb-2">
                                        ${esc(pendentesTitulo)}
                                    </div>
                                    <ul class="space-y-1 text-sm text-amber-900">
                                        ${pendentesList.map((p) => {
                                            const ordem = (p && p.ordem !== undefined && p.ordem !== null) ? String(p.ordem) : '';
                                            const nome = (p && p.nome) ? String(p.nome) : 'Signatário';
                                            const funcao = (p && p.funcao) ? String(p.funcao) : 'Signatário';
                                            const prefix = ordem !== '' && ordem !== '0' ? `${ordem}. ` : '';
                                            return `<li><span class="font-semibold">${esc(prefix)}</span>${esc(nome)} <span class="text-amber-700">(${esc(funcao)})</span></li>`;
                                        }).join('')}
                                    </ul>
                                    ${isGovernanca ? `<div class="mt-3 text-xs text-amber-800">O documento final com todas as assinaturas será enviado por e-mail quando todas as etapas forem concluídas.</div>` : ``}
                                </div>
                            </div>
                        `
                        : (isFinal && isGovernanca)
                            ? `
                                <div class="mt-6 max-w-md mx-auto text-left">
                                    <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-sm text-green-900">
                                        Processo concluído. O documento final com todas as assinaturas será enviado por e-mail.
                                    </div>
                                </div>
                            `
                            : '';
                    const reciboHtml = (!isGovernanca && data.id_assinatura)
                        ? `
                            <div class="mt-8 pt-6 border-t border-gray-100">
                                <a href="gerar_pdf.php?id_assinatura=${data.id_assinatura}" target="_blank" 
                                   class="text-sm text-gray-500 hover:text-blue-600 transition-colors inline-flex items-center gap-1">
                                    <i class="fas fa-file-contract"></i> Baixar Recibo de Assinatura
                                </a>
                            </div>
                        `
                        : '';
                    const downloadHtml = (isGovernanca && !isFinal)
                        ? ''
                        : `
                            <div class="flex flex-col gap-3 max-w-sm mx-auto">
                                <a href="${esc(primaryHref)}" download="${esc(nomeFinalArquivo)}" target="_blank"
                                   class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                                    <i class="fas fa-download"></i> ${primaryText}
                                </a>
                                <a href="${esc(secondaryHref)}" download="${esc(nomeFinalArquivo)}" target="_blank"
                                   class="w-full bg-white hover:bg-gray-50 text-gray-700 font-medium py-3 px-6 rounded-lg border border-gray-200 shadow-sm transition-all flex items-center justify-center gap-2">
                                    <i class="fas ${secondaryIcon}"></i> ${secondaryText}
                                </a>
                            </div>
                        `;

                    container.innerHTML = `
                        <div class="p-8 text-center">
                            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 mb-6 border-4 border-white shadow-sm">
                                <i class="fas fa-check text-4xl text-green-600"></i>
                            </div>
                            
                            <h2 class="text-3xl font-bold text-gray-800 mb-2">Documento Assinado com Sucesso!</h2>
                            <p class="text-gray-500 mb-8">O arquivo foi carimbado e salvo em nosso sistema seguro.</p>
                            
                            <div class="bg-gray-50 rounded-xl p-6 mb-8 max-w-md mx-auto border border-gray-100 text-left">
                                <div class="flex justify-between py-2 border-b border-gray-200">
                                    <span class="text-gray-500 text-sm">Protocolo</span>
                                    <span class="font-mono font-medium text-gray-800">${esc(data.protocolo)}</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-500 text-sm">Data</span>
                                    <span class="font-medium text-gray-800">${esc(data.data_hora)}</span>
                                </div>
                            </div>

                            ${downloadHtml}
                            ${pendentesHtml}
                            <div id="assinatura-after-actions" class="mt-4 max-w-sm mx-auto"></div>
                            ${reciboHtml}
                        </div>
                    `;

                    try {
                        const ref = document.referrer || '';
                        if (ref.indexOf('pendentes') !== -1) {
                            const slot = document.getElementById('assinatura-after-actions');
                            if (slot) {
                                slot.innerHTML = `
                                    <a href="pendentes.php"
                                       class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-6 rounded-lg border border-gray-200 shadow-sm transition-all flex items-center justify-center gap-2">
                                        <i class="fas fa-arrow-left"></i> Voltar para Pendências
                                    </a>
                                `;
                            }
                        }
                    } catch (e) {
                    }
                    
                    // Opcional: Forçar download automático
                    // const link = document.createElement('a');
                    // link.href = blobUrl;
                    // link.download = `documento_assinado_${data.protocolo}.pdf`;
                    // link.click();
                    
                } else {
                    alert('Erro ao assinar: ' + data.message);
                    btnAssinar.disabled = false;
                    btnAssinar.textContent = "Assinar Documento";
                }

            } catch (error) {
                console.error('Erro:', error);
                alert(error && error.message ? error.message : 'Ocorreu um erro ao processar o documento.');
                btnAssinar.disabled = false;
                btnAssinar.textContent = "Assinar Documento";
            }
        });
    }
});
