<?php
include_once "../conecta.php";
include_once "layout_header.php";

// Busca documentos que já foram assinados por todos (ou que estão 'concluidos' se houver status geral)
$sql = "
    SELECT s.*, 
    (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id) as total_assinantes,
    (SELECT COUNT(*) FROM assinantes a WHERE a.id_solicitacao = s.id AND a.status = 'assinado') as total_assinados
    FROM solicitacoes_assinatura s
    ORDER BY s.id DESC
";
$result = mysqli_query($conn, $sql);
?>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="bg-gray-50 min-h-screen font-sans">

    <div class="max-w-6xl mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
            <div class="flex items-center gap-4">
                <div class="bg-white p-2 rounded-lg shadow-sm">
                    <img src="assets/logo.png" alt="TechPS" class="h-10">
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Finalização de Documentos</h1>
                    <p class="text-sm text-gray-500">Assinatura Digital ICP-Brasil e Carimbo de Tempo</p>
                </div>
            </div>
            <a href="index.php" class="text-blue-600 hover:text-blue-800 font-medium flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-center justify-between" role="alert">
            <div class="flex items-center gap-2">
                <i class="fas fa-check-circle text-xl"></i>
                <div>
                    <p class="font-bold">Sucesso!</p>
                    <p><?php echo htmlspecialchars($_GET['message'] ?? 'Operação realizada com sucesso.'); ?></p>
                </div>
            </div>
            <button onclick="this.parentElement.remove()" class="text-green-500 hover:text-green-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm flex items-center justify-between" role="alert">
            <div class="flex items-center gap-2">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <div>
                    <p class="font-bold">Erro!</p>
                    <p><?php echo htmlspecialchars($_GET['message'] ?? 'Ocorreu um erro.'); ?></p>
                </div>
            </div>
            <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Coluna da Esquerda: Upload do PFX e Autenticação -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sticky top-8">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-certificate text-green-600"></i>
                        Certificado Digital
                    </h2>
                    
                    <?php
                    // Verifica se existe configuração automática
                    $hasAutoConfig = false;
                    $pfxPath = '';
                    if (file_exists('config_pfx.php')) {
                        $config = include('config_pfx.php');
                        if (!empty($config['auto_sign']) && !empty($config['pfx_path']) && file_exists($config['pfx_path'])) {
                            $hasAutoConfig = true;
                            $pfxPath = $config['pfx_path'];
                        }
                    }
                    ?>

                    <form action="processar_finalizacao.php" method="POST" enctype="multipart/form-data" id="formFinalizar">
                        
                        <?php if ($hasAutoConfig): ?>
                        <div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                                <div>
                                    <p class="text-sm text-blue-800 font-medium">Certificado do Servidor Detectado</p>
                                    <p class="text-xs text-blue-600 mt-1 break-all">
                                        <?php echo basename($pfxPath); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="use_server_cert" id="use_server_cert" value="1" checked 
                                        class="text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                        onchange="toggleCertInput(this)">
                                    <span class="text-xs text-gray-700">Usar este certificado automaticamente</span>
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div id="manual_cert_container" class="<?php echo $hasAutoConfig ? 'hidden' : ''; ?>">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Arquivo PFX (.pfx / .p12)</label>
                                <div class="relative border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-green-500 hover:bg-green-50 transition-colors cursor-pointer">
                                    <input type="file" name="pfx_file" id="pfx_file" accept=".pfx,.p12" <?php echo $hasAutoConfig ? '' : 'required'; ?> class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-2"></i>
                                    <p class="text-xs text-gray-500">Clique para selecionar</p>
                                </div>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Senha do Certificado</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" name="pfx_password" id="pfx_password" <?php echo $hasAutoConfig ? '' : 'required'; ?> 
                                        class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition-all"
                                        placeholder="Digite a senha">
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Obrigatório para upload manual ou se a senha do servidor não estiver salva.</p>
                            </div>
                        </div>

                        <input type="hidden" name="id_documento" id="selected_doc_id">

                        <button type="submit" id="btnFinalizar" disabled
                            class="w-full bg-gray-400 text-white font-bold py-3 px-4 rounded-lg cursor-not-allowed transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-check-circle"></i>
                            Finalizar Documento
                        </button>
                        
                        <p class="text-xs text-gray-400 mt-4 text-center">
                            <i class="fas fa-lock text-xs"></i> Seus dados são processados de forma segura e não são armazenados.
                        </p>
                    </form>
                </div>
            </div>

            <script>
            function toggleCertInput(checkbox) {
                const container = document.getElementById('manual_cert_container');
                const fileInput = document.getElementById('pfx_file');
                const passInput = document.getElementById('pfx_password');
                
                if (checkbox.checked) {
                    container.classList.add('hidden');
                    fileInput.required = false;
                    // passInput.required = false; // A senha pode ser necessária se não estiver no config
                } else {
                    container.classList.remove('hidden');
                    fileInput.required = true;
                    passInput.required = true;
                }
            }
            </script>

            <!-- Coluna da Direita: Lista de Documentos -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-gray-800">Documentos Disponíveis</h2>
                        <span class="bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded-full font-bold">
                            <?php echo mysqli_num_rows($result); ?> encontrados
                        </span>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <?php while ($row = mysqli_fetch_assoc($result)): 
                            $completo = ($row['total_assinantes'] > 0 && $row['total_assinantes'] == $row['total_assinados']);
                            $status_class = $completo ? "bg-green-100 text-green-700" : "bg-yellow-100 text-yellow-700";
                            $status_text = $completo ? "Pronto para Finalizar" : "Aguardando Assinaturas";
                            $icon = $completo ? "fa-check" : "fa-clock";
                            
                            // Verifica se já foi finalizado (se tivermos a coluna status_final futuramente)
                            $ja_finalizado = isset($row['status_final']) && $row['status_final'] == 'finalizado';
                            if ($ja_finalizado) {
                                $status_class = "bg-blue-100 text-blue-700";
                                $status_text = "Finalizado ICP-Brasil";
                                $icon = "fa-certificate";
                            }
                        ?>
                        <div class="p-4 hover:bg-gray-50 transition-colors cursor-pointer group doc-item" 
                             onclick="<?php echo $ja_finalizado ? "window.open('{$row['caminho_arquivo']}', '_blank')" : "selectDocument(this, '{$row['id']}', " . ($completo ? 'true' : 'false') . ")"; ?>">
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="h-10 w-10 rounded-full flex items-center justify-center <?php echo $completo ? 'bg-green-100 text-green-600' : 'bg-yellow-100 text-yellow-600'; ?>">
                                        <i class="fas <?php echo $ja_finalizado ? 'fa-file-signature' : 'fa-file-pdf'; ?>"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-gray-800 group-hover:text-blue-600 transition-colors">
                                            <?php echo htmlspecialchars($row['nome_arquivo_original'] ?: 'Documento Sem Nome'); ?>
                                        </h3>
                                        <div class="flex items-center gap-3 mt-1">
                                            <span class="text-xs text-gray-500">
                                                <i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($row['created_at'] ?? 'now')); ?>
                                            </span>
                                            <span class="text-xs <?php echo $status_class; ?> px-2 py-0.5 rounded-full flex items-center gap-1">
                                                <i class="fas <?php echo $icon; ?> text-[10px]"></i> <?php echo $status_text; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right flex flex-col items-end gap-1">
                                    <div class="text-sm font-bold text-gray-700">
                                        <?php echo $row['total_assinados']; ?> / <?php echo $row['total_assinantes']; ?>
                                    </div>
                                    <div class="text-xs text-gray-400 mb-1">Assinaturas</div>
                                    
                                    <?php if ($ja_finalizado): ?>
                                        <a href="<?php echo $row['caminho_arquivo']; ?>" download target="_blank" 
                                           class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold py-1.5 px-3 rounded-md shadow-sm transition-all transform hover:scale-105"
                                           onclick="event.stopPropagation();">
                                            <i class="fas fa-download"></i> Baixar Documento
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Indicador de Seleção (Apenas se não finalizado) -->
                            <?php if (!$ja_finalizado): ?>
                            <div class="selection-indicator hidden mt-3 pt-3 border-t border-gray-100 text-right">
                                <span class="text-sm text-green-600 font-semibold">
                                    <i class="fas fa-check-circle"></i> Selecionado
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>

                        <?php if (mysqli_num_rows($result) == 0): ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="far fa-folder-open text-4xl mb-3 text-gray-300"></i>
                            <p>Nenhum documento encontrado.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selectDocument(element, id, isReady) {
            // Remove seleção anterior
            document.querySelectorAll('.doc-item').forEach(el => {
                el.classList.remove('bg-blue-50', 'border-l-4', 'border-blue-500');
                const indicator = el.querySelector('.selection-indicator');
                if (indicator) {
                    indicator.classList.add('hidden');
                }
            });

            // Adiciona seleção atual
            element.classList.add('bg-blue-50', 'border-l-4', 'border-blue-500');
            const currentIndicator = element.querySelector('.selection-indicator');
            if (currentIndicator) {
                currentIndicator.classList.remove('hidden');
            }

            // Atualiza input hidden
            document.getElementById('selected_doc_id').value = id;

            // Habilita botão se estiver pronto
            const btn = document.getElementById('btnFinalizar');
            if (isReady) {
                btn.disabled = false;
                btn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                btn.classList.add('bg-green-600', 'hover:bg-green-700', 'shadow-lg', 'transform', 'hover:-translate-y-0.5');
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Finalizar Documento';
            } else {
                btn.disabled = true;
                btn.classList.add('bg-gray-400', 'cursor-not-allowed');
                btn.classList.remove('bg-green-600', 'hover:bg-green-700', 'shadow-lg', 'transform', 'hover:-translate-y-0.5');
                btn.innerHTML = '<i class="fas fa-lock"></i> Aguardando Assinaturas';
            }
        }

        // Preview do nome do arquivo PFX
        document.querySelector('input[name="pfx_file"]').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                this.parentElement.querySelector('p').textContent = fileName;
                this.parentElement.classList.add('border-green-500', 'bg-green-50');
            }
        });
    </script>
</div>
<?php
include_once "layout_footer.php";
?>