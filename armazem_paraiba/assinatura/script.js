
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
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error("Erro ao baixar o PDF");
        
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
            assinaturaStep.style.display = 'block';
        }
        currentFileName = "documento_solicitado.pdf";

    } catch (error) {
        console.error("Erro ao carregar PDF da URL:", error);
        alert("Não foi possível carregar o documento solicitado. Tente recarregar a página.");
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

    if (cpfInput && cpfTermo) {
        cpfInput.addEventListener('input', function() {
            cpfTermo.textContent = this.value || '[XXX.XXX.XXX-XX]';
        });
    }

    // Configura transição de tela se estiver no modo upload
    if (btnUpload) {
        btnUpload.addEventListener('click', function(e) {
            e.preventDefault();
            if (currentPdfBytes) {
                uploadStep.style.display = 'none';
                assinaturaStep.style.display = 'block';
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
                
                // Adiciona uma nova página ao final
                const page = pdfDoc.addPage();
                const { width, height } = page.getSize();

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

                // --- Layout da Página de Assinatura ---
                
                const margin = 50;
                let y = height - margin;
                const contentWidth = width - (margin * 2);

                // Desenha a Logo se carregada
                if (logoImage) {
                    const logoDims = logoImage.scale(0.25); // Ajuste de escala
                    page.drawImage(logoImage, {
                        x: margin,
                        y: y - logoDims.height,
                        width: logoDims.width,
                        height: logoDims.height,
                    });
                    y -= (logoDims.height + 20);
                } else {
                    y -= 20;
                }

                // Título
                page.drawText('COMPROVANTE DE ASSINATURA ELETRÔNICA', {
                    x: margin,
                    y: y,
                    size: 14,
                    font: helveticaBold,
                    color: rgb(0, 0, 0),
                });
                y -= 30;

                const fontSize = 10;
                const lineHeight = 15;

                // Função auxiliar para desenhar linhas de informação
                const drawInfoLine = (label, value) => {
                    // Verifica se precisa de quebra de linha no valor
                    const labelWidth = helveticaBold.widthOfTextAtSize(label, fontSize);
                    const valueMaxWidth = contentWidth - labelWidth - 10;
                    
                    page.drawText(label, { x: margin, y: y, size: fontSize, font: helveticaBold });
                    
                    // Tratamento simples para quebra de texto se for muito longo (ex: User Agent)
                    if (helveticaFont.widthOfTextAtSize(value, fontSize) > valueMaxWidth) {
                        // Divide em pedaços grosseiramente (melhoria futura: dividir por palavras)
                        const words = value.split(' ');
                        let line = '';
                        let currentY = y;
                        
                        // Primeira linha ao lado do label
                        let firstLine = true;
                        
                        for (let word of words) {
                            const testLine = line + word + ' ';
                            const testWidth = helveticaFont.widthOfTextAtSize(testLine, fontSize);
                            
                            if (testWidth > valueMaxWidth && line !== '') {
                                if (firstLine) {
                                    page.drawText(line, { x: margin + labelWidth + 5, y: currentY, size: fontSize, font: helveticaFont });
                                    firstLine = false;
                                } else {
                                    page.drawText(line, { x: margin, y: currentY, size: fontSize, font: helveticaFont });
                                }
                                line = word + ' ';
                                currentY -= lineHeight;
                            } else {
                                line = testLine;
                            }
                        }
                        // Última linha
                        if (firstLine) {
                             page.drawText(line, { x: margin + labelWidth + 5, y: currentY, size: fontSize, font: helveticaFont });
                        } else {
                             page.drawText(line, { x: margin, y: currentY, size: fontSize, font: helveticaFont });
                        }
                        y = currentY - lineHeight;
                    } else {
                        page.drawText(value, { x: margin + labelWidth + 5, y: y, size: fontSize, font: helveticaFont });
                        y -= lineHeight;
                    }
                };

                drawInfoLine('Documento Original:', nomeArquivoOriginal);
                
                // Pega a função/papel do usuário
                const papelUsuario = document.getElementById('papel_usuario') ? document.getElementById('papel_usuario').value : 'Signatário';
                
                drawInfoLine('Assinado por:', `${nome} (${papelUsuario})`);
                drawInfoLine('CPF:', cpf);
                if (rg) drawInfoLine('RG:', rg);
                drawInfoLine('Data e Hora:', dataHora);
                drawInfoLine('Endereço IP:', clientIp);
                drawInfoLine('Navegador/Dispositivo:', navigator.userAgent);
                drawInfoLine('Hash de Integridade (Assinatura Digital):', protocolHash);

                y -= 20;

                // Texto Legal
                const legalText = 'O signatário declarou ter lido e concordado com o teor do documento original referenciado acima. Esta assinatura foi realizada eletronicamente através do sistema TechPS, com validade jurídica assegurada pela Medida Provisória nº 2.200-2/2001.';
                
                // Função simples de wrap para o texto legal
                const words = legalText.split(' ');
                let line = '';
                for (const word of words) {
                    const testLine = line + word + ' ';
                    const width = helveticaFont.widthOfTextAtSize(testLine, fontSize);
                    if (width > contentWidth) {
                        page.drawText(line, { x: margin, y: y, size: fontSize, font: helveticaFont });
                        line = word + ' ';
                        y -= lineHeight;
                    } else {
                        line = testLine;
                    }
                }
                page.drawText(line, { x: margin, y: y, size: fontSize, font: helveticaFont });
                
                y -= 40;

                // Rodapé
                page.drawLine({
                    start: { x: margin, y: y + 20 },
                    end: { x: width - margin, y: y + 20 },
                    thickness: 1,
                    color: rgb(0.8, 0.8, 0.8),
                });

                page.drawText('TechPS - Tecnologia e Sistemas | Armazém Paraíba', {
                    x: margin,
                    y: y,
                    size: 8,
                    font: helveticaBold,
                    color: rgb(0.4, 0.4, 0.4),
                });
                
                page.drawText(`Gerado em: ${dataHora}`, {
                    x: width - margin - helveticaFont.widthOfTextAtSize(`Gerado em: ${dataHora}`, 8),
                    y: y,
                    size: 8,
                    font: helveticaFont,
                    color: rgb(0.4, 0.4, 0.4),
                });

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

                const data = await response.json();


                if (data.status === 'success') {
                    // Atualiza a interface
                    const container = document.getElementById('main-container') || document.querySelector('.container');
                    
                    if (!container) {
                        console.error('Container principal não encontrado para exibir sucesso.');
                        alert('Sucesso! Documento assinado. Protocolo: ' + data.protocolo);
                        return;
                    }
                    
                    // Gera URL para download direto do Blob local (mais rápido e garantido)
                    const blobUrl = URL.createObjectURL(pdfBlob);
                    
                    // Link para o PDF salvo no servidor (backup)
                    // Usa o caminho retornado pelo servidor se disponível, senão tenta adivinhar (fallback)
                    const linkArquivoServidor = data.caminho_arquivo ? data.caminho_arquivo : `docAssinado/assinado_${data.protocolo}.pdf`;

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
                                    <span class="font-mono font-medium text-gray-800">${data.protocolo}</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-500 text-sm">Data</span>
                                    <span class="font-medium text-gray-800">${data.data_hora}</span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 max-w-sm mx-auto">
                                <!-- Botão Principal: Download do Blob Local -->
                                <a href="${blobUrl}" download="${nomeFinalArquivo}" 
                                   class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                                    <i class="fas fa-download"></i> Baixar Documento (Agora)
                                </a>
                                
                                <!-- Link Backup: Download do Servidor -->
                                <a href="${linkArquivoServidor}" download="${nomeFinalArquivo}" target="_blank"
                                   class="w-full bg-white hover:bg-gray-50 text-gray-700 font-medium py-3 px-6 rounded-lg border border-gray-200 shadow-sm transition-all flex items-center justify-center gap-2">
                                    <i class="fas fa-cloud-download-alt"></i> Baixar do Servidor (Backup)
                                </a>
                            </div>
                            
                            <div class="mt-8 pt-6 border-t border-gray-100">
                                <a href="gerar_pdf.php?id_assinatura=${data.id_assinatura}" target="_blank" 
                                   class="text-sm text-gray-500 hover:text-blue-600 transition-colors inline-flex items-center gap-1">
                                    <i class="fas fa-file-contract"></i> Baixar Recibo de Assinatura
                                </a>
                            </div>
                        </div>
                    `;
                    
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
                alert('Ocorreu um erro ao processar o documento.');
                btnAssinar.disabled = false;
                btnAssinar.textContent = "Assinar Documento";
            }
        });
    }
});
