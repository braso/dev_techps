<?php
include_once "../conecta.php";
include_once "componentes/layout_header.php";
?>
<!-- Tailwind CSS (Included in header) -->
<!-- FontAwesome (Included in header) -->
<style>
    .drag-active {
        border-color: #3b82f6;
        background-color: #eff6ff;
    }
</style>

<div class="bg-gray-50 py-10 px-4 font-sans">

    <div class="max-w-4xl w-full mx-auto bg-white shadow-xl rounded-2xl overflow-hidden">
        
        <!-- Header simplified -->
        <div class="bg-white px-8 py-6 border-b border-gray-100 flex justify-between items-center text-left">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Assinatura com Governança</h2>
                <p class="text-gray-500 text-sm">Envie um documento com mais de 1 signatário para validar e acompanhar o processo de assinatura (etapas, ordem e auditoria).</p>
            </div>
            <a href="index.php" class="text-gray-500 hover:text-blue-600 text-sm font-medium transition-colors flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-50">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <div class="p-8">
            
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] === 'success'): ?>
                    <div class="mb-8 p-4 rounded-xl bg-green-50 text-green-800 border border-green-200 flex items-center gap-3 shadow-sm">
                        <i class="fas fa-check-circle text-xl"></i>
                        <div>
                            <p class="font-bold">Sucesso!</p>
                            <p class="text-sm">Processo iniciado. O primeiro signatário foi notificado via e-mail.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mb-8 p-4 rounded-xl bg-red-50 text-red-800 border border-red-200 flex items-center gap-3 shadow-sm">
                        <i class="fas fa-exclamation-circle text-xl"></i>
                        <div>
                            <p class="font-bold">Erro ao enviar</p>
                            <p class="text-sm"><?php echo htmlspecialchars($_GET['message'] ?? 'Ocorreu um erro desconhecido.'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form action="processar_envio.php" method="POST" enctype="multipart/form-data" id="formEnvio">
                
                <!-- Upload Section -->
                <div class="mb-10">
                    <label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">
                        1. Documento Original (PDF)
                    </label>
                    
                    <div id="drop-zone" class="relative border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-blue-400 hover:bg-blue-50 transition-all cursor-pointer group">
                        <input type="file" id="arquivo" name="arquivo" accept="application/pdf" required
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        
                        <div class="space-y-3 pointer-events-none">
                            <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center mx-auto group-hover:bg-white group-hover:scale-110 transition-transform">
                                <i class="fas fa-cloud-upload-alt text-3xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-700 font-medium group-hover:text-blue-600 transition-colors">Clique ou arraste o arquivo PDF aqui</p>
                                <p class="text-gray-400 text-xs mt-1">Tamanho máximo: 10MB</p>
                            </div>
                            <p id="file-name" class="text-sm font-semibold text-blue-600 hidden mt-2 py-1 px-3 bg-blue-100 rounded-full inline-block"></p>
                        </div>
                    </div>
                </div>

                <hr class="border-gray-100 mb-10">

                <!-- Signatários Section -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <label class="block text-sm font-bold text-gray-700 uppercase tracking-wide">
                            2. Signatários (Ordem Sequencial)
                        </label>
                        <button type="button" id="btnAddSignatario" 
                            class="text-sm bg-blue-50 text-blue-600 hover:bg-blue-100 px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2 border border-blue-200">
                            <i class="fas fa-plus"></i> Adicionar
                        </button>
                    </div>
                    
                    <p class="text-sm text-gray-500 mb-6 bg-yellow-50 p-3 rounded-lg border border-yellow-100 flex items-start gap-2">
                        <i class="fas fa-info-circle text-yellow-500 mt-0.5"></i>
                        O documento será enviado para o primeiro da lista. Assim que assinar, o próximo será notificado automaticamente.
                    </p>

                    <div id="signatarios-container" class="space-y-4">
                        <!-- Cards inseridos via JS -->
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" 
                    class="w-full bg-gradient-to-r from-green-600 to-green-500 hover:from-green-700 hover:to-green-600 text-white font-bold py-4 px-6 rounded-xl shadow-lg shadow-green-200 transform hover:-translate-y-0.5 transition-all flex items-center justify-center gap-3 text-lg">
                    <span>Iniciar Processo de Assinatura</span>
                    <i class="fas fa-paper-plane"></i>
                </button>

            </form>
        </div>
    </div>

    <!-- Template do Card de Signatário (Oculto) -->
    <template id="signatario-template">
        <div class="signatario-card bg-white border border-gray-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow relative group">
            <div class="absolute -left-3 top-5 bg-gray-800 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm shadow-md z-10 index-badge">
                1
            </div>
            
            <div class="flex justify-between items-start mb-4 ml-4">
                <h4 class="font-semibold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-user-pen text-gray-400"></i> Dados do Signatário
                </h4>
                <button type="button" class="btn-remove text-gray-400 hover:text-red-500 transition-colors p-1" title="Remover">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>

            <div class="grid md:grid-cols-2 gap-5 ml-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">Nome Completo</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400 text-xs"></i>
                        </div>
                        <input type="text" name="nome" required placeholder="Ex: João Silva"
                            class="input-nome w-full pl-9 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">E-mail Corporativo</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400 text-xs"></i>
                        </div>
                        <input type="email" name="email" required placeholder="joao@empresa.com"
                            class="input-email w-full pl-9 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all">
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">Função / Papel</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-briefcase text-gray-400 text-xs"></i>
                        </div>
                        <select name="funcao" required
                            class="select-funcao w-full pl-9 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all appearance-none">
                            <option value="Funcionário">Funcionário</option>
                            <option value="Gerente">Gerente</option>
                            <option value="Diretor">Diretor</option>
                            <option value="Testemunha">Testemunha</option>
                            <option value="Representante Legal">Representante Legal</option>
                            <option value="Contratante">Contratante</option>
                            <option value="Outro">Outro</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <input type="hidden" name="ordem" class="input-ordem">
        </div>
    </template>

    <script>
        const container = document.getElementById('signatarios-container');
        const btnAdd = document.getElementById('btnAddSignatario');
        const template = document.getElementById('signatario-template');
        const fileInput = document.getElementById('arquivo');
        const dropZone = document.getElementById('drop-zone');
        const fileNameDisplay = document.getElementById('file-name');

        // Drag and Drop Effects
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('drag-active');
        }

        function unhighlight(e) {
            dropZone.classList.remove('drag-active');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            updateFileName();
        }

        fileInput.addEventListener('change', updateFileName);

        function updateFileName() {
            if (fileInput.files.length > 0) {
                const name = fileInput.files[0].name;
                fileNameDisplay.textContent = name;
                fileNameDisplay.classList.remove('hidden');
            }
        }

        // Logic for Signatories
        function addSignatario(nome = '', email = '', funcao = 'Funcionário') {
            const clone = template.content.cloneNode(true);
            const card = clone.querySelector('.signatario-card');
            
            // Set values if provided (for edit/preload)
            if(nome) card.querySelector('.input-nome').value = nome;
            if(email) card.querySelector('.input-email').value = email;
            if(funcao) card.querySelector('.select-funcao').value = funcao;

            // Remove button logic
            const btnRemove = card.querySelector('.btn-remove');
            btnRemove.addEventListener('click', () => {
                if (container.children.length > 1) {
                    card.remove();
                    reordenar();
                } else {
                    alert('É necessário pelo menos um signatário.');
                }
            });

            container.appendChild(card);
            reordenar();
        }

        function reordenar() {
            const cards = container.querySelectorAll('.signatario-card');
            
            cards.forEach((card, index) => {
                const realIndex = index + 1;
                
                // Update badge
                card.querySelector('.index-badge').textContent = realIndex;
                
                // Update Remove Button Visibility (hide for first if only one)
                const btnRemove = card.querySelector('.btn-remove');
                btnRemove.style.display = cards.length === 1 ? 'none' : 'block';

                // Update input names for PHP array
                card.querySelector('.input-nome').name = `signatarios[${index}][nome]`;
                card.querySelector('.input-email').name = `signatarios[${index}][email]`;
                card.querySelector('.select-funcao').name = `signatarios[${index}][funcao]`;
                
                // Update hidden order input
                const ordemInput = card.querySelector('.input-ordem');
                ordemInput.name = `signatarios[${index}][ordem]`;
                ordemInput.value = realIndex;
            });
        }

        btnAdd.addEventListener('click', () => addSignatario());

        // Initialize with 2 empty slots or default
        addSignatario('', '', 'Funcionário');
        addSignatario('', '', 'Gerente');

    </script>
</div>
<?php
include_once "componentes/layout_footer.php";
?>
