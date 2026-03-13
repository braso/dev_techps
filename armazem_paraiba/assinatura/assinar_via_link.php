<?php
// Define interno como true para evitar redirecionamento de login/logout no conecta.php
$interno = true;

include "../conecta.php";
include "email_config.php";

$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    die("Token inválido ou não fornecido.");
}

// Inicializa variáveis
$solicitacao = null;
$assinante = null;
$caminho_arquivo = '';
$nome_arquivo_original = '';
$id_documento = '';
$email_usuario = '';
$nome_usuario = '';
$already_signed = false;
$signed_data = [];

// 1. Tenta buscar na tabela nova de Assinantes
$sqlAssinante = "SELECT a.*, s.caminho_arquivo, s.nome_arquivo_original, s.id_documento as doc_id_global 
                 FROM assinantes a 
                 JOIN solicitacoes_assinatura s ON a.id_solicitacao = s.id 
                 WHERE a.token = ?";
$stmt = mysqli_prepare($conn, $sqlAssinante);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $assinante = mysqli_fetch_assoc($result);
}

if ($assinante) {
    // Fluxo Novo (Múltiplos Signatários)
    $solicitacao = $assinante; // Usa os dados combinados
    $caminho_arquivo = $assinante['caminho_arquivo'];
    $id_documento = $assinante['doc_id_global'];
    $nome_arquivo_original = $assinante['nome_arquivo_original'] ?: basename($caminho_arquivo);
    $email_usuario = $assinante['email'];
    $nome_usuario = $assinante['nome'];
    $papel_usuario = $assinante['funcao'];
    
    // Verifica status do próprio assinante
    if ($assinante['status'] === 'assinado') {
        $already_signed = true;
        $signed_data = [
            'nome' => $assinante['nome'],
            'email' => $assinante['email'],
            'cpf' => $assinante['cpf'] ?? 'N/A', // Assuming cpf is in assinantes or we fetch from user
            'data_assinatura' => $assinante['data_assinatura'],
            'ip' => $assinante['ip'],
            'metadados' => json_decode($assinante['metadados'] ?? '{}', true)
        ];
    } else {

        // Verifica Ordem (Sequencial)
        if ($assinante['ordem'] > 1) {
            $ordemAnterior = $assinante['ordem'] - 1;
            $sqlCheck = "SELECT status FROM assinantes WHERE id_solicitacao = ? AND ordem = ?";
            $stmtCheck = mysqli_prepare($conn, $sqlCheck);
            mysqli_stmt_bind_param($stmtCheck, "ii", $assinante['id_solicitacao'], $ordemAnterior);
            mysqli_stmt_execute($stmtCheck);
            $resCheck = mysqli_stmt_get_result($stmtCheck);
            $anterior = mysqli_fetch_assoc($resCheck);
            
            if (!$anterior || $anterior['status'] !== 'assinado') {
                die("<h3>Aguardando assinatura anterior.</h3><p>O signatário da etapa anterior ainda não assinou este documento. Você será notificado quando for sua vez.</p>");
            }
        }
    }

} else {
    // 2. Fluxo Antigo (Compatibilidade)
    $sql = "SELECT * FROM solicitacoes_assinatura WHERE token = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $solicitacao = mysqli_fetch_assoc($result);

    if (!$solicitacao) {
        die("Solicitação não encontrada.");
    }

    $caminho_arquivo = $solicitacao['caminho_arquivo'];
    $id_documento = $solicitacao['id_documento'];
    $nome_arquivo_original = isset($solicitacao['nome_arquivo_original']) ? $solicitacao['nome_arquivo_original'] : basename($caminho_arquivo);
    $email_usuario = $solicitacao['email'];
    $nome_usuario = $solicitacao['nome'];
    $papel_usuario = 'Signatário';

    if ($solicitacao['status'] === 'assinado' || $solicitacao['status'] === 'concluido') {
        $already_signed = true;
        $signed_data = [
            'nome' => $solicitacao['nome'],
            'email' => $solicitacao['email'],
            'cpf' => 'N/A', // Old flow might not have CPF in this table
            'data_assinatura' => $solicitacao['data_assinatura'],
            'ip' => $solicitacao['ip_address'] ?? 'N/A', // Assuming ip_address column exists or fetch from audit
            'metadados' => []
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinar Documento - Solicitação via E-mail</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- PDF Lib -->
    <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Ajuste específico para o iframe */
        #pdfPreview {
            height: 400px; /* Reduzido de 600px */
        }
        /* Scrollbar customizada para o termo */
        .scrollbar-thin::-webkit-scrollbar {
            width: 4px; /* Reduzido */
        }
        .scrollbar-thin::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #ccc; /* Mais sutil */
            border-radius: 2px;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #999;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center py-6 px-4 font-sans">

<!-- Container Principal Reduzido e Centralizado -->
<div id="main-container" class="max-w-3xl w-full bg-white shadow-2xl rounded-xl overflow-hidden">
    
    <!-- Header Compacto -->
    <div class="bg-white px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <img src="assets/logo.png" alt="TechPS" class="h-10 object-contain">
            <div class="border-l border-gray-300 h-8 mx-2"></div>
            <div>
                <h2 class="text-lg font-bold text-gray-800 leading-tight">Assinatura Digital</h2>
                <p class="text-gray-400 text-xs">Ambiente Seguro</p>
            </div>
        </div>
        <!-- Status Badge -->
        <div>
            <?php if ($already_signed): ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <i class="fas fa-check-circle mr-1"></i> Concluído
                </span>
            <?php else: ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    <i class="fas fa-pen-nib mr-1"></i> Pendente
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="p-5 md:p-6 space-y-5 bg-gray-50/50">
        
        <?php if ($already_signed): ?>
            <!-- TELA DE SUCESSO / JÁ ASSINADO (Compacta) -->
            <div class="text-center py-4">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-4 border-4 border-white shadow-sm">
                    <i class="fas fa-check text-3xl text-green-600"></i>
                </div>
                
                <h2 class="text-2xl font-bold text-gray-800 mb-1">Documento Assinado!</h2>
                <p class="text-gray-500 text-sm mb-6">Assinatura registrada e autenticada com sucesso.</p>
                
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 max-w-lg mx-auto overflow-hidden text-left mb-6">
                    <div class="p-5 space-y-3">
                        <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Signatário</span>
                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($signed_data['nome']); ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center border-b border-gray-100 pb-3">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Data/Hora</span>
                            <span class="text-sm font-medium text-gray-900">
                                <?php echo $signed_data['data_assinatura'] ? date('d/m/Y H:i:s', strtotime($signed_data['data_assinatura'])) : 'N/A'; ?>
                            </span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">IP Origem</span>
                            <span class="font-mono text-xs text-gray-600 bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($signed_data['ip']); ?></span>
                        </div>
                        
                        <?php if (!empty($signed_data['metadados']['hash'])): ?>
                        <div class="pt-2">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1">Hash de Segurança</span>
                            <div class="font-mono text-[10px] text-gray-500 bg-gray-50 p-2 rounded border border-gray-100 break-all leading-tight">
                                <?php echo htmlspecialchars($signed_data['metadados']['hash']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="bg-gray-50 px-5 py-3 text-center border-t border-gray-200">
                         <p class="text-[10px] text-gray-400">Validade jurídica conforme MP 2.200-2/2001</p>
                         <a href="<?php echo htmlspecialchars($caminho_arquivo); ?>" download class="text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors mt-2 inline-block">
                             <i class="fas fa-download mr-1"></i> Baixar Documento Assinado
                         </a>
                    </div>
                </div>

                <button onclick="window.print(); return false;" class="text-sm text-gray-600 hover:text-blue-600 font-medium transition-colors">
                    <i class="fas fa-print mr-1"></i> Imprimir Comprovante
                </button>
            </div>

        <?php else: ?>
            
            <!-- Info Documento Compacta -->
            <div class="bg-white p-3 rounded-lg border border-gray-200 shadow-sm flex flex-col sm:flex-row justify-between items-center gap-2 text-sm">
                <div class="flex items-center gap-2">
                    <div class="bg-blue-50 p-2 rounded text-blue-600">
                        <i class="far fa-file-pdf text-lg"></i>
                    </div>
                    <div>
                        <span class="block font-medium text-gray-700">Documento ID: <span class="font-mono text-blue-600"><?php echo htmlspecialchars($id_documento); ?></span></span>
                        <span class="block text-xs text-gray-500">Para: <?php echo htmlspecialchars($email_usuario); ?></span>
                    </div>
                </div>
                <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded font-medium border border-gray-200">
                    <?php echo htmlspecialchars($papel_usuario); ?>
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Coluna 1: Visualização (Esquerda em Desktop) -->
                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Documento</h3>
                    <div class="border border-gray-300 rounded-lg overflow-hidden shadow-sm bg-gray-100 h-[400px]">
                        <iframe id="pdfPreview" class="w-full h-full border-none" src=""></iframe>
                    </div>
                </div>

                <!-- Coluna 2: Formulário (Direita em Desktop) -->
                <div class="flex flex-col h-full">
                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Seus Dados</h3>
                    
                    <form id="formAssinatura" class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm flex-1 flex flex-col">
                        <input type="hidden" id="id_documento" name="id_documento" value="<?php echo htmlspecialchars($id_documento); ?>">
                        <input type="hidden" id="token_solicitacao" name="token_solicitacao" value="<?php echo htmlspecialchars($token); ?>">
                        <input type="hidden" id="papel_usuario" name="papel_usuario" value="<?php echo htmlspecialchars($papel_usuario); ?>">
                        
                        <div class="space-y-3 mb-4">
                            <div>
                                <label for="nome" class="block text-xs font-medium text-gray-700 mb-1">Nome Completo</label>
                                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome_usuario); ?>" required
                                    class="w-full text-sm rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500 py-1.5 px-3 bg-gray-50">
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="cpf" class="block text-xs font-medium text-gray-700 mb-1">CPF</label>
                                    <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" required
                                        class="w-full text-sm rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500 py-1.5 px-3 bg-gray-50">
                                </div>
                                <div>
                                    <label for="rg" class="block text-xs font-medium text-gray-700 mb-1">RG</label>
                                    <input type="text" id="rg" name="rg" placeholder="RG" required
                                        class="w-full text-sm rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500 py-1.5 px-3 bg-gray-50">
                                </div>
                            </div>
                        </div>

                        <!-- Termo Compacto -->
                        <div class="flex-1 min-h-0 flex flex-col mb-4">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Termo de Aceite</label>
                            <div class="bg-gray-50 p-3 rounded border border-gray-200 text-xs text-gray-600 text-justify overflow-y-auto h-32 scrollbar-thin leading-relaxed mb-2">
                                <p class="mb-2">Eu, <span id="nome_termo" class="font-bold text-gray-800">[Nome]</span>, portador(a) do CPF nº <span id="cpf_termo" class="font-bold text-gray-800">[CPF]</span>, declaro ciência da MP 2.200-2/2001.</p>
                                <p class="mb-2">A concordância é manifesta e inequívoca, garantindo validade jurídica, autenticidade e integridade.</p>
                                <p>"A assinatura digital terá validade quando houver concordância expressa."</p>
                            </div>

                            <div class="flex items-start gap-2 bg-blue-50/50 p-2 rounded border border-blue-100">
                                <input id="aceite" name="aceite" type="checkbox" required class="mt-0.5 h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                                <label for="aceite" class="text-xs text-gray-700 cursor-pointer select-none leading-tight">
                                    Li e concordo com a Declaração de Ciência.
                                </label>
                            </div>
                        </div>

                        <!-- Campos ocultos -->
                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">
                        <input type="hidden" id="device_info" name="device_info">
                        <input type="hidden" id="nome_arquivo_original" value="<?php echo htmlspecialchars($nome_arquivo_original); ?>">

                        <!-- Botão -->
                        <button type="submit" id="btnAssinar" 
                            class="w-full py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-[1.02]">
                            Assinar Digitalmente
                        </button>
                    </form>
                </div>
            </div>
            
            <script src="script.js"></script>
            <script>
                // Configura o carregamento automático do PDF
                window.PDF_URL_TO_LOAD = "<?php echo $caminho_arquivo; ?>";
            </script>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
