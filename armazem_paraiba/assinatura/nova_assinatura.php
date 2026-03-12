<?php
include_once "../conecta.php";
// Simulação de recebimento de dados via GET (em produção viria do banco ou link criptografado)
$id_documento = isset($_GET['doc']) ? $_GET['doc'] : '';
$nome_funcionario = isset($_GET['nome']) ? $_GET['nome'] : 'Funcionário Exemplo';

// Se não tiver documento selecionado, permitir upload
$modo_upload = empty($id_documento);

include_once "componentes/layout_header.php";

$modoTela = $_GET["modo"] ?? "avulso";
$modoTela = in_array($modoTela, ["avulso", "funcionarios", "separar_paginas"], true) ? $modoTela : "avulso";

$funcionarios = [];
if(in_array($modoTela, ["funcionarios", "separar_paginas"], true)){
    $funcionarios = mysqli_fetch_all(query(
        "SELECT
            enti_nb_id,
            enti_tx_nome,
            enti_tx_email,
            enti_tx_cpf,
            enti_tx_matricula
        FROM entidade
        WHERE enti_tx_status = 'ativo'
        ORDER BY enti_tx_nome ASC"
    ), MYSQLI_ASSOC);
}

function contarPaginasPdf(string $path): int {
    if(extension_loaded("imagick")){
        try {
            $im = new Imagick();
            if(method_exists($im, "pingImage")){
                $im->pingImage($path);
            } else {
                $im->readImage($path);
            }
            $n = intval($im->getNumberImages());
            $im->clear();
            $im->destroy();
            return $n > 0 ? $n : 0;
        } catch (Throwable $e) {
        }
    }

    $content = @file_get_contents($path);
    if($content === false){
        return 0;
    }
    if(preg_match_all("/\\/Type\\s*\\/Page(?!s)/", $content, $m) === false){
        return 0;
    }
    return max(0, count($m[0] ?? []));
}

function gerarTokenPdf(): string {
    return bin2hex(random_bytes(16));
}

$tokenPdf = null;
$paginasPdf = 0;
$nomePdfOriginal = "";
$erroUploadPdf = "";
if($modoTela === "separar_paginas" && ($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && isset($_POST["acao_pdf"]) && $_POST["acao_pdf"] === "upload_pdf_paginas"){
    if(session_status() === PHP_SESSION_NONE){
        session_start();
    }
    $arquivo = $_FILES["pdf_multipage"] ?? null;
    if(!$arquivo || ($arquivo["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK){
        $erroUploadPdf = "Selecione um PDF válido.";
    } else {
        $original = strval($arquivo["name"] ?? "");
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if($ext !== "pdf"){
            $erroUploadPdf = "Apenas PDF é permitido.";
        } else {
            $tmp = strval($arquivo["tmp_name"] ?? "");
            $dirTmp = __DIR__ . "/uploads/tmp/";
            if(!is_dir($dirTmp)){
                mkdir($dirTmp, 0777, true);
            }
            $token = gerarTokenPdf();
            $dest = $dirTmp . $token . ".pdf";
            if(!move_uploaded_file($tmp, $dest)){
                $erroUploadPdf = "Falha ao salvar o PDF.";
            } else {
                $paginas = contarPaginasPdf($dest);
                if($paginas <= 0){
                    @unlink($dest);
                    $erroUploadPdf = "Não foi possível identificar as páginas do PDF.";
                } else {
                    if(!isset($_SESSION["pdf_split_tokens"]) || !is_array($_SESSION["pdf_split_tokens"])){
                        $_SESSION["pdf_split_tokens"] = [];
                    }
                    $_SESSION["pdf_split_tokens"][$token] = [
                        "path" => $dest,
                        "name" => $original,
                        "created" => time(),
                        "pages" => $paginas
                    ];
                    $tokenPdf = $token;
                    $paginasPdf = $paginas;
                    $nomePdfOriginal = $original;
                }
            }
        }
    }
}
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

        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100 mb-6">
            <div class="p-5 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Modo de envio</h3>
                    <p class="text-xs text-gray-500">Escolha como você deseja enviar os documentos para assinatura.</p>
                </div>
                <div class="flex gap-2">
                    <a href="nova_assinatura.php?modo=avulso" class="<?php echo $modoTela === "avulso" ? "bg-blue-600 text-white" : "bg-gray-100 text-gray-700"; ?> px-4 py-2 rounded-lg text-sm font-semibold">
                        Documento avulso
                    </a>
                    <a href="nova_assinatura.php?modo=funcionarios" class="<?php echo $modoTela === "funcionarios" ? "bg-blue-600 text-white" : "bg-gray-100 text-gray-700"; ?> px-4 py-2 rounded-lg text-sm font-semibold">
                        Enviar para funcionários
                    </a>
                    <a href="nova_assinatura.php?modo=separar_paginas" class="<?php echo $modoTela === "separar_paginas" ? "bg-blue-600 text-white" : "bg-gray-100 text-gray-700"; ?> px-4 py-2 rounded-lg text-sm font-semibold">
                        Separar páginas
                    </a>
                </div>
            </div>
        </div>

        <?php if(isset($_GET["status"])): ?>
            <?php
                $status = $_GET["status"] ?? "";
                $message = $_GET["message"] ?? "";
                $status = in_array($status, ["success", "error"], true) ? $status : "error";
                $messageSafe = htmlspecialchars((string)$message);
                $cls = $status === "success"
                    ? "bg-green-50 border-green-100 text-green-800"
                    : "bg-red-50 border-red-100 text-red-800";
                $titulo = $status === "success" ? "Sucesso" : "Erro";
            ?>
            <div class="<?php echo $cls; ?> border rounded-lg p-4 mb-6 text-sm">
                <div class="font-bold"><?php echo $titulo; ?></div>
                <?php if($messageSafe !== ""): ?>
                    <div class="mt-1"><?php echo $messageSafe; ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if($modoTela === "separar_paginas"): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800">Separar PDF em páginas e enviar</h3>
                    <p class="text-sm text-gray-500">Faça upload de um PDF com várias páginas e informe para qual funcionário cada página será enviada.</p>
                </div>

                <div class="p-6 space-y-5">
                    <?php if($erroUploadPdf !== ""): ?>
                        <div class="bg-red-50 border border-red-100 text-red-800 text-sm rounded-lg p-4">
                            <?php echo htmlspecialchars($erroUploadPdf); ?>
                        </div>
                    <?php endif; ?>

                    <?php if($tokenPdf === null): ?>
                        <form action="nova_assinatura.php?modo=separar_paginas" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="acao_pdf" value="upload_pdf_paginas">
                            <div class="max-w-xl mx-auto">
                                <div class="relative border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-blue-400 hover:bg-blue-50 transition-all cursor-pointer group" onclick="document.getElementById('fileInputMultipage').click()">
                                    <input type="file" id="fileInputMultipage" name="pdf_multipage" accept="application/pdf" class="hidden" onchange="handleFileSelectMultipage(this)">
                                    <div class="space-y-3 pointer-events-none">
                                        <p class="text-gray-600 group-hover:text-blue-600 transition-colors font-medium">Clique aqui ou arraste o PDF com múltiplas páginas</p>
                                        <p class="text-xs text-gray-400">Depois você escolhe o funcionário de cada página.</p>
                                        <span id="fileNameMultipage" class="block text-sm font-semibold text-blue-600 mt-2"></span>
                                    </div>
                                </div>
                                <div class="mt-6 text-center">
                                    <button type="submit" id="btnUploadMultipage" disabled class="bg-gray-300 text-white px-6 py-2.5 rounded-lg font-semibold shadow-sm cursor-not-allowed transition-all w-full sm:w-auto">
                                        <i class="fas fa-file-import mr-2"></i> Carregar PDF
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <form action="processar_envio.php" method="POST">
                            <input type="hidden" name="redirect_to" value="nova_assinatura.php?modo=separar_paginas">
                            <input type="hidden" name="modo_envio" value="separar_paginas">
                            <input type="hidden" name="pdf_token" value="<?php echo htmlspecialchars($tokenPdf); ?>">

                            <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                                <div class="text-sm font-semibold text-blue-800">PDF carregado</div>
                                <div class="text-xs text-blue-700 mt-1">
                                    <span class="font-semibold">Arquivo:</span> <?php echo htmlspecialchars($nomePdfOriginal); ?>
                                    <span class="mx-2">|</span>
                                    <span class="font-semibold">Páginas:</span> <?php echo intval($paginasPdf); ?>
                                </div>
                            </div>

                            <?php if(empty($funcionarios)): ?>
                                <div class="bg-yellow-50 border border-yellow-100 text-yellow-800 text-sm rounded-lg p-4">
                                    Nenhum funcionário ativo encontrado.
                                </div>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php for($p = 1; $p <= $paginasPdf; $p++): ?>
                                        <div class="border border-gray-200 rounded-xl p-4 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                                            <div class="text-sm font-bold text-gray-800">Página <?php echo $p; ?></div>
                                            <div class="w-full sm:w-[28rem]">
                                                <label class="block text-xs font-semibold text-gray-700 mb-1" for="page_<?php echo $p; ?>">Funcionário</label>
                                                <select id="page_<?php echo $p; ?>" name="page_funcionario[<?php echo $p; ?>]" class="w-full rounded-lg border-gray-300 bg-gray-50 border focus:bg-white focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5">
                                                    <option value="">Não enviar esta página</option>
                                                    <?php foreach($funcionarios as $f): ?>
                                                        <?php
                                                            $id = intval($f["enti_nb_id"] ?? 0);
                                                            $nome = trim(strval($f["enti_tx_nome"] ?? ""));
                                                            $email = trim(strval($f["enti_tx_email"] ?? ""));
                                                            $cpf = trim(strval($f["enti_tx_cpf"] ?? ""));
                                                            $matricula = trim(strval($f["enti_tx_matricula"] ?? ""));
                                                            $label = $nome;
                                                            if($cpf !== ""){ $label .= " | CPF: ".$cpf; }
                                                            if($matricula !== ""){ $label .= " | Mat: ".$matricula; }
                                                            if($email !== ""){ $label .= " | ".$email; }
                                                        ?>
                                                        <option value="<?php echo htmlspecialchars((string)$id); ?>"><?php echo htmlspecialchars($label); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>

                                <div class="pt-2">
                                    <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all">
                                        <i class="fas fa-paper-plane mr-2 text-lg"></i> Separar e enviar para assinatura
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif($modoTela === "funcionarios"): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <form id="formEnvioFuncionarios" action="processar_envio.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="redirect_to" value="nova_assinatura.php?modo=funcionarios">
                    <input type="hidden" name="modo_envio" value="funcionarios">

                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-800">Enviar documentos por funcionário</h3>
                        <p class="text-sm text-gray-500">Anexe um PDF para cada funcionário que deverá assinar.</p>
                    </div>

                    <div class="p-6 space-y-4">
                        <?php if(empty($funcionarios)): ?>
                            <div class="bg-yellow-50 border border-yellow-100 text-yellow-800 text-sm rounded-lg p-4">
                                Nenhum funcionário ativo encontrado.
                            </div>
                        <?php else: ?>
                            <?php foreach($funcionarios as $f): ?>
                                <?php
                                    $id = intval($f["enti_nb_id"] ?? 0);
                                    $nome = trim(strval($f["enti_tx_nome"] ?? ""));
                                    $email = trim(strval($f["enti_tx_email"] ?? ""));
                                    $cpf = trim(strval($f["enti_tx_cpf"] ?? ""));
                                    $matricula = trim(strval($f["enti_tx_matricula"] ?? ""));
                                    $idSafe = htmlspecialchars((string)$id);
                                    $nomeSafe = htmlspecialchars($nome);
                                    $emailSafe = htmlspecialchars($email);
                                    $cpfSafe = htmlspecialchars($cpf);
                                    $matriculaSafe = htmlspecialchars($matricula);
                                ?>
                                <div class="border border-gray-200 rounded-xl p-5">
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="text-sm font-bold text-gray-800 truncate"><?php echo $nomeSafe; ?></div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <span class="font-semibold text-gray-700">CPF:</span> <?php echo $cpfSafe !== "" ? $cpfSafe : "-"; ?>
                                                <span class="mx-2">|</span>
                                                <span class="font-semibold text-gray-700">Matrícula:</span> <?php echo $matriculaSafe !== "" ? $matriculaSafe : "-"; ?>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <span class="font-semibold text-gray-700">E-mail:</span> <?php echo $emailSafe !== "" ? $emailSafe : "-"; ?>
                                            </div>
                                        </div>
                                        <div class="w-full sm:w-72">
                                            <input type="hidden" name="funcionarios[]" value="<?php echo $idSafe; ?>">
                                            <label class="block text-xs font-semibold text-gray-700 mb-1" for="arquivo_<?php echo $idSafe; ?>">Documento (PDF)</label>
                                            <input
                                                type="file"
                                                id="arquivo_<?php echo $idSafe; ?>"
                                                name="arquivos[<?php echo $idSafe; ?>]"
                                                accept="application/pdf"
                                                class="block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                            >
                                            <?php if($emailSafe === ""): ?>
                                                <div class="text-xs text-red-600 mt-2">Sem e-mail cadastrado. O envio será ignorado.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="pt-2">
                                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all">
                                    <i class="fas fa-paper-plane mr-2 text-lg"></i> Enviar documentos para assinatura
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php else: ?>
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
            
            <form id="formEnvioIndividual" action="processar_envio.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="redirect_to" value="nova_assinatura.php?modo=avulso">
                
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
<?php endif; ?>

<script>
    function handleFileSelectMultipage(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const fileNameElement = document.getElementById('fileNameMultipage');
            if(fileNameElement) fileNameElement.textContent = file.name;

            const btnUpload = document.getElementById('btnUploadMultipage');
            if(btnUpload) {
                btnUpload.disabled = false;
                btnUpload.classList.remove('bg-gray-300', 'cursor-not-allowed');
                btnUpload.classList.add('bg-blue-600', 'hover:bg-blue-700', 'text-white', 'cursor-pointer');
            }
        }
    }

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
