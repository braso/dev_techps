<?php
include_once "../conecta.php";

function assinatura_ensureSetorResponsavelSchema(): void{
    $sql = "CREATE TABLE IF NOT EXISTS setor_responsavel (
        sres_nb_id INT AUTO_INCREMENT PRIMARY KEY,
        sres_nb_setor_id INT NOT NULL,
        sres_nb_entidade_id INT NOT NULL,
        sres_tx_assinar_governanca ENUM('sim','nao') NOT NULL DEFAULT 'nao',
        sres_tx_status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
        sres_tx_dataCadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_setor_entidade (sres_nb_setor_id, sres_nb_entidade_id),
        INDEX ix_setor (sres_nb_setor_id),
        INDEX ix_entidade (sres_nb_entidade_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    query($sql);
}

if(($_GET["ajax"] ?? "") === "funcionario_info"){
    header("Content-Type: application/json; charset=utf-8");
    assinatura_ensureSetorResponsavelSchema();

    $id = intval($_GET["id"] ?? 0);
    if($id <= 0){
        echo json_encode(["ok" => false]);
        exit;
    }

    $row = mysqli_fetch_assoc(query(
        "SELECT
            e.enti_nb_id,
            e.enti_tx_nome,
            e.enti_tx_email,
            e.enti_setor_id,
            g.grup_tx_nome AS setor_nome
        FROM entidade e
        LEFT JOIN grupos_documentos g ON g.grup_nb_id = e.enti_setor_id
        WHERE e.enti_nb_id = ?
        LIMIT 1",
        "i",
        [$id]
    ));

    if(empty($row)){
        echo json_encode(["ok" => false]);
        exit;
    }

    $setorId = intval($row["enti_setor_id"] ?? 0);
    $responsaveis = [];
    if($setorId > 0){
        $responsaveis = mysqli_fetch_all(query(
            "SELECT
                e.enti_nb_id AS id,
                e.enti_tx_nome AS nome,
                e.enti_tx_email AS email
            FROM setor_responsavel sr
            INNER JOIN entidade e ON e.enti_nb_id = sr.sres_nb_entidade_id
            WHERE sr.sres_nb_setor_id = ?
            AND sr.sres_tx_status = 'ativo'
            AND sr.sres_tx_assinar_governanca = 'sim'
            AND e.enti_tx_status = 'ativo'
            ORDER BY e.enti_tx_nome ASC",
            "i",
            [$setorId]
        ), MYSQLI_ASSOC);
    }

    echo json_encode([
        "ok" => true,
        "funcionario" => [
            "id" => intval($row["enti_nb_id"] ?? 0),
            "nome" => strval($row["enti_tx_nome"] ?? ""),
            "email" => strval($row["enti_tx_email"] ?? "")
        ],
        "setor" => [
            "id" => $setorId,
            "nome" => strval($row["setor_nome"] ?? "")
        ],
        "responsaveis" => $responsaveis
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

include_once "componentes/layout_header.php";
?>
<!-- Tailwind CSS (Included in header) -->
<!-- FontAwesome (Included in header) -->
<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" />
<link href="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" />
<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery.min.js" type="text/javascript"></script>
<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/js/select2.min.js" type="text/javascript"></script>
<script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/select2/js/i18n/pt-BR.js" type="text/javascript"></script>
<style>
    .drag-active {
        border-color: #3b82f6;
        background-color: #eff6ff;
    }

    .select2-container--default .select2-selection--single {
        height: 42px;
        border-radius: 0.5rem;
        border-color: #e5e7eb;
        background-color: #f9fafb;
        padding-top: 6px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        padding-left: 36px;
        color: #111827;
        line-height: 28px;
        font-size: 0.875rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
        right: 10px;
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
                <input type="hidden" name="redirect_to" value="governanca.php">
                <input type="hidden" name="modo_envio" value="governanca">
                
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
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">Funcionário</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400 text-xs"></i>
                        </div>
                        <select class="select-funcionario w-full pl-9 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all"></select>
                        <input type="hidden" class="input-entidade" value="">
                        <input type="hidden" class="input-setor-id" value="">
                        <input type="hidden" class="input-nome" value="">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">E-mail Corporativo</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400 text-xs"></i>
                        </div>
                        <input type="email" name="email" required placeholder="joao@empresa.com"
                            class="input-email w-full pl-9 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all" readonly>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">Setor</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-sitemap text-gray-400 text-xs"></i>
                        </div>
                        <input type="text" placeholder="—" class="input-setor w-full pl-9 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all" readonly>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase">Responsáveis do Setor (Assina Governança)</label>
                    <div class="box-responsaveis min-h-[42px] w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700 flex flex-wrap gap-2 items-center"></div>
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
                            <option value="Responsável do Setor">Responsável do Setor</option>
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
        const formEnvio = document.getElementById('formEnvio');

        const baseUrl = <?=json_encode($_ENV["URL_BASE"].$_ENV["APP_PATH"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
        const contexPath = <?=json_encode($_ENV["APP_PATH"].$_ENV["CONTEX_PATH"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
        const condAtivo = encodeURIComponent("AND enti_tx_status = 'ativo'");
        let cardUidSeq = 0;

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
            card.dataset.uid = String(++cardUidSeq);
            
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
            initCard(card);
            reordenar();
        }

        function escapeHtml(str){
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderResponsaveis(card, responsaveis) {
            const box = card.querySelector('.box-responsaveis');
            if (!box) return;
            if (!responsaveis || !Array.isArray(responsaveis) || responsaveis.length === 0) {
                box.innerHTML = '<span class="text-gray-400 text-xs">Nenhum responsável configurado para assinar governança.</span>';
                return;
            }
            box.innerHTML = responsaveis.map(r => {
                const nome = (r && r.nome) ? String(r.nome) : '';
                const email = (r && r.email) ? String(r.email) : '';
                const label = email ? (nome + ' | ' + email) : nome;
                return '<span class="inline-flex items-center px-2 py-1 rounded-md bg-blue-50 text-blue-700 text-xs border border-blue-100">' + escapeHtml(label) + '</span>';
            }).join('');
        }

        function parseSources(str){
            const raw = String(str || '').split(',').map(s => s.trim()).filter(Boolean);
            return Array.from(new Set(raw));
        }

        function setSources(card, sources){
            card.dataset.autoSources = Array.from(new Set((sources || []).map(s => String(s)))).join(',');
        }

        function removeSourceFromAutoCards(sourceUid){
            const uid = String(sourceUid || '');
            if(uid === '') return;
            const autos = container.querySelectorAll('.signatario-card[data-auto-responsavel="1"]');
            autos.forEach(c => {
                const sources = parseSources(c.dataset.autoSources || '');
                const next = sources.filter(s => s !== uid);
                if(next.length === 0){
                    c.remove();
                } else {
                    setSources(c, next);
                }
            });
        }

        function findCardByEntidadeId(entidadeId){
            const id = String(entidadeId || '');
            if(id === '') return null;
            const cards = container.querySelectorAll('.signatario-card');
            for(const c of cards){
                const inp = c.querySelector('.input-entidade');
                if(inp && String(inp.value || '') === id){
                    return c;
                }
            }
            return null;
        }

        function preselectFuncionarioNoCard(card, entidadeId, label){
            const sel = card.querySelector('.select-funcionario');
            if(!sel) return;
            const id = String(entidadeId || '');
            if(id === '') return;
            const text = String(label || ('ID ' + id));

            const opt = new Option(text, id, true, true);
            sel.appendChild(opt);

            if(window.jQuery && jQuery.fn && typeof jQuery.fn.select2 === 'function'){
                const $sel = jQuery(sel);
                $sel.val(id).trigger('change.select2');
            } else {
                sel.value = id;
            }
        }

        function syncAutoResponsaveis(card, responsaveis, setorId, setorNome){
            const sourceUid = String(card.dataset.uid || '');
            if(sourceUid === '') return;

            removeSourceFromAutoCards(sourceUid);

            const selectedEnti = (card.querySelector('.input-entidade')?.value || '').toString();
            const list = Array.isArray(responsaveis) ? responsaveis : [];
            let insertAfter = card;

            for(const r of list){
                const respId = r && r.id != null ? String(r.id) : '';
                if(respId === '' || respId === selectedEnti) continue;

                const existing = findCardByEntidadeId(respId);
                if(existing){
                    if(existing.dataset.autoResponsavel === '1'){
                        const sources = parseSources(existing.dataset.autoSources || '');
                        if(!sources.includes(sourceUid)){
                            sources.push(sourceUid);
                            setSources(existing, sources);
                        }
                    }
                    continue;
                }

                const clone = template.content.cloneNode(true);
                const respCard = clone.querySelector('.signatario-card');
                respCard.dataset.uid = String(++cardUidSeq);
                respCard.dataset.autoResponsavel = '1';
                respCard.dataset.autoSources = sourceUid;

                const btnRemove = respCard.querySelector('.btn-remove');
                if(btnRemove) btnRemove.style.display = 'none';

                const nome = (r && r.nome) ? String(r.nome) : '';
                const email = (r && r.email) ? String(r.email) : '';
                respCard.querySelector('.input-nome').value = nome;
                respCard.querySelector('.input-email').value = email;
                respCard.querySelector('.input-email').readOnly = true;
                respCard.querySelector('.input-entidade').value = respId;
                respCard.querySelector('.input-setor-id').value = setorId ? String(setorId) : '';
                const inpSetor = respCard.querySelector('.input-setor');
                if(inpSetor) inpSetor.value = setorNome ? String(setorNome) : '';
                const funcSel = respCard.querySelector('.select-funcao');
                if(funcSel) funcSel.value = 'Responsável do Setor';

                const label = email ? (nome + ' | ' + email) : nome;
                preselectFuncionarioNoCard(respCard, respId, label);

                insertAfter.insertAdjacentElement('afterend', respCard);
                insertAfter = respCard;
                initCard(respCard, { disableSelect: true, skipFetch: true });
            }
        }

        async function carregarInfoFuncionario(card, entidadeId) {
            const inputNome = card.querySelector('.input-nome');
            const inputEmail = card.querySelector('.input-email');
            const inputSetor = card.querySelector('.input-setor');
            const inputEnti = card.querySelector('.input-entidade');
            const inputSetorId = card.querySelector('.input-setor-id');
            const sourceUid = String(card.dataset.uid || '');

            if (inputEnti) inputEnti.value = entidadeId ? String(entidadeId) : '';
            if (!entidadeId) {
                if (inputNome) inputNome.value = '';
                if (inputEmail) inputEmail.value = '';
                if (inputSetor) inputSetor.value = '';
                if (inputSetorId) inputSetorId.value = '';
                renderResponsaveis(card, []);
                removeSourceFromAutoCards(sourceUid);
                reordenar();
                return;
            }

            try {
                const res = await fetch('governanca.php?ajax=funcionario_info&id=' + encodeURIComponent(String(entidadeId)), {
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (!data || !data.ok) {
                    renderResponsaveis(card, []);
                    removeSourceFromAutoCards(sourceUid);
                    reordenar();
                    return;
                }
                if (inputNome) inputNome.value = (data.funcionario && data.funcionario.nome) ? String(data.funcionario.nome) : '';
                if (inputEmail) inputEmail.value = (data.funcionario && data.funcionario.email) ? String(data.funcionario.email) : '';
                if (inputSetor) inputSetor.value = (data.setor && data.setor.nome) ? String(data.setor.nome) : '';
                if (inputSetorId) inputSetorId.value = (data.setor && data.setor.id) ? String(data.setor.id) : '';
                renderResponsaveis(card, data.responsaveis || []);
                syncAutoResponsaveis(card, data.responsaveis || [], (data.setor && data.setor.id) ? data.setor.id : '', (data.setor && data.setor.nome) ? data.setor.nome : '');
                reordenar();
            } catch (e) {
                renderResponsaveis(card, []);
                removeSourceFromAutoCards(sourceUid);
                reordenar();
            }
        }

        function initCard(card, opts){
            const options = opts || {};
            const box = card.querySelector('.box-responsaveis');
            if (box && box.innerHTML.trim() === '') {
                renderResponsaveis(card, []);
            }

            const sel = card.querySelector('.select-funcionario');
            if(!sel) return;

            if(window.jQuery && jQuery.fn && typeof jQuery.fn.select2 === 'function'){
                const $sel = jQuery(sel);
                $sel.select2({
                    language: 'pt-BR',
                    placeholder: 'Selecione',
                    allowClear: true,
                    width: '100%',
                    ajax: {
                        url: baseUrl + '/contex20/select2.php?path=' + encodeURIComponent(contexPath) + '&tabela=entidade&ordem=&limite=15&condicoes=' + condAtivo,
                        dataType: 'json',
                        delay: 250,
                        processResults: function (data) { return { results: data }; },
                        cache: true
                    }
                });

                if(!options.skipFetch){
                    $sel.on('select2:select', function(e){
                        const d = e && e.params ? e.params.data : null;
                        const id = d && d.id ? d.id : '';
                        carregarInfoFuncionario(card, id);
                    });

                    $sel.on('select2:clear', function(){
                        carregarInfoFuncionario(card, '');
                    });

                    $sel.on('change', function(){
                        const id = $sel.val();
                        if(!id){
                            carregarInfoFuncionario(card, '');
                        }
                    });
                }

                if(options.disableSelect){
                    $sel.prop('disabled', true);
                }
            }
        }

        function reordenar() {
            const cards = container.querySelectorAll('.signatario-card');
            
            cards.forEach((card, index) => {
                const realIndex = index + 1;
                
                // Update badge
                card.querySelector('.index-badge').textContent = realIndex;
                
                // Update Remove Button Visibility (hide for first if only one)
                const btnRemove = card.querySelector('.btn-remove');
                if(btnRemove){
                    if(card.dataset.autoResponsavel === '1'){
                        btnRemove.style.display = 'none';
                    } else {
                        btnRemove.style.display = cards.length === 1 ? 'none' : 'block';
                    }
                }

                // Update input names for PHP array
                card.querySelector('.input-nome').name = `signatarios[${index}][nome]`;
                card.querySelector('.input-email').name = `signatarios[${index}][email]`;
                card.querySelector('.select-funcao').name = `signatarios[${index}][funcao]`;
                const enti = card.querySelector('.input-entidade');
                if(enti) enti.name = `signatarios[${index}][enti_nb_id]`;
                const setorId = card.querySelector('.input-setor-id');
                if(setorId) setorId.name = `signatarios[${index}][setor_id]`;
                
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

        if(formEnvio){
            formEnvio.addEventListener('submit', function(e){
                const cards = container.querySelectorAll('.signatario-card');
                for(const card of cards){
                    const enti = card.querySelector('.input-entidade');
                    const nome = card.querySelector('.input-nome');
                    const email = card.querySelector('.input-email');
                    if(!enti || String(enti.value || '').trim() === ''){
                        e.preventDefault();
                        alert('Selecione um funcionário em todos os signatários.');
                        return;
                    }
                    if(!nome || String(nome.value || '').trim() === '' || !email || String(email.value || '').trim() === ''){
                        e.preventDefault();
                        alert('Os dados do funcionário (nome/e-mail) precisam estar preenchidos em todos os signatários.');
                        return;
                    }
                }
            });
        }

    </script>
</div>
<?php
include_once "componentes/layout_footer.php";
?>
