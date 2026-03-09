<?php
include_once "../conecta.php";
// Simulação de recebimento de dados via GET (em produção viria do banco ou link criptografado)
$id_documento = isset($_GET['doc']) ? $_GET['doc'] : '';
$nome_funcionario = isset($_GET['nome']) ? $_GET['nome'] : 'Funcionário Exemplo';

// Se não tiver documento selecionado, permitir upload
$modo_upload = empty($id_documento);

include_once "componentes/layout_header.php";
?>

<div class="font-sans">

    <div class="max-w-4xl mx-auto px-4 py-8">

        <!-- Header simplificado -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Assinatura Eletrônica</h2>
                <p class="text-sm text-gray-500">Módulo para envio individual de documentos para solicitação de assinatura digital com validade jurídica.</p>
            </div>
            <a href="index.php" class="text-blue-600 hover:text-blue-800 font-medium flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
            
            <form id="formEnvioIndividual" action="processar_envio.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="redirect_to" value="nova_assinatura.php">
                
                <?php if ($modo_upload): ?>
                <!-- Tela de Upload -->
                <div id="uploadStep" class="p-8">
                    <div class="mb-6 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 text-blue-600 mb-4">
                            <i class="fas fa-cloud-upload-alt text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Selecione o Documento</h3>
                        <p class="text-gray-500 text-sm">Faça o upload do arquivo PDF que deseja enviar para assinatura.</p>
                    </div>

                    <div class="max-w-xl mx-auto">
                        <div class="relative border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-blue-400 hover:bg-blue-50 transition-all cursor-pointer group" onclick="document.getElementById('fileInput').click()">
                            <input type="file" id="fileInput" name="arquivo" accept="application/pdf" class="hidden" onchange="handleFileSelect(this)">
                            
                            <div class="space-y-3 pointer-events-none">
                                <p class="text-gray-600 group-hover:text-blue-600 transition-colors font-medium">Clique aqui ou arraste o arquivo PDF</p>
                                <p class="text-xs text-gray-400">Suporta arquivos PDF de até 10MB</p>
                                <span id="fileName" class="block text-sm font-semibold text-blue-600 mt-2"></span>
                            </div>
                        </div>
                        
                        <div class="mt-6 text-center">
                            <button type="button" id="btnUpload" disabled class="bg-gray-300 text-white px-6 py-2.5 rounded-lg font-semibold shadow-sm cursor-not-allowed transition-all w-full sm:w-auto">
                                <i class="fas fa-file-import mr-2"></i> Carregar Documento
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tela de Visualização e Dados do Signatário -->
                <div id="assinaturaStep" style="<?php echo $modo_upload ? 'display:none;' : 'display:flex;'; ?>" class="flex flex-col lg:flex-row h-full">
                    
                    <!-- Coluna da Esquerda: Visualização -->
                    <div class="lg:w-1/2 bg-gray-100 border-r border-gray-200 flex flex-col">
                        <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Visualização do Documento</h3>
                            <?php if (!$modo_upload): ?>
                            <div class="text-xs text-gray-500 text-right">
                                <p><strong>ID:</strong> <?php echo htmlspecialchars($id_documento); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex-grow p-4 bg-gray-200 flex items-center justify-center overflow-hidden relative" style="min-height: 500px;">
                                <?php if (!$modo_upload): ?>
                                <div class="text-center p-6 bg-white rounded shadow">
                                    <i class="fas fa-file-pdf text-4xl text-red-500 mb-2"></i>
                                    <p class="text-gray-600">Documento pré-cadastrado carregado.</p>
                                </div>
                            <?php else: ?>
                                <div id="pdfPreviewContainer" class="w-full h-full">
                                    <iframe id="pdfPreview" src="" class="w-full h-full border-0 shadow-sm rounded"></iframe>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Coluna da Direita: Formulário -->
                    <div class="lg:w-1/2 p-6 lg:p-8 flex flex-col justify-center bg-white">
                        <div class="mb-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-2">Dados do Signatário</h3>
                            <p class="text-sm text-gray-500">Informe quem deverá assinar este documento.</p>
                        </div>

                        <div class="space-y-5">
                            <input type="hidden" id="id_documento" name="id_documento" value="<?php echo htmlspecialchars($id_documento); ?>">
                            
                            <div>
                                <label for="nome" class="block text-sm font-medium text-gray-700 mb-1">Nome do Signatário</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" id="nome" name="nome" placeholder="Nome Completo" required 
                                        class="pl-10 block w-full rounded-lg border-gray-300 bg-gray-50 border focus:bg-white focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2.5 transition-colors">
                                </div>
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-mail do Signatário</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-envelope text-gray-400"></i>
                                    </div>
                                    <input type="email" id="email" name="email" placeholder="email@exemplo.com" required
                                        class="pl-10 block w-full rounded-lg border-gray-300 bg-gray-50 border focus:bg-white focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2.5 transition-colors">
                                </div>
                            </div>

                            <div class="bg-blue-50 rounded-lg p-4 border border-blue-100 mt-4">
                                <p class="text-blue-700 text-xs text-justify leading-relaxed">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    O signatário receberá um e-mail com um link único e seguro para realizar a assinatura eletrônica do documento.
                                </p>
                            </div>

                            <button type="submit" id="btnEnviar" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all transform hover:-translate-y-0.5">
                                <i class="fas fa-paper-plane mr-2 text-lg"></i> Enviar para Assinatura
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="mt-6 text-center text-xs text-gray-400">
            <p><i class="fas fa-shield-alt mr-1"></i> Ambiente seguro. Ações registradas para fins de auditoria.</p>
        </div>

    </div>
</div>

<script>
    function handleFileSelect(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const fileNameElement = document.getElementById('fileName');
            if(fileNameElement) fileNameElement.textContent = file.name;
            
            const btnUpload = document.getElementById('btnUpload');
            if(btnUpload) {
                btnUpload.disabled = false;
                btnUpload.classList.remove('bg-gray-300', 'cursor-not-allowed');
                btnUpload.classList.add('bg-blue-600', 'hover:bg-blue-700', 'text-white', 'cursor-pointer');
            }

            // Preview
            const url = URL.createObjectURL(file);
            const pdfPreview = document.getElementById('pdfPreview');
            if(pdfPreview) pdfPreview.src = url;
        }
    }

    const btnUpload = document.getElementById('btnUpload');
    if(btnUpload) {
        btnUpload.addEventListener('click', function(e) {
            e.preventDefault(); // Impede submissão (botão é type="button", mas por segurança)
            document.getElementById('uploadStep').style.display = 'none';
            document.getElementById('assinaturaStep').style.display = 'flex';
        });
    }
</script>

<?php
include_once "componentes/layout_footer.php";
?>
