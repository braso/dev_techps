<?php
// Simulação de recebimento de dados via GET (em produção viria do banco ou link criptografado)
$id_documento = isset($_GET['doc']) ? $_GET['doc'] : '';
$nome_funcionario = isset($_GET['nome']) ? $_GET['nome'] : 'Funcionário Exemplo';

// Se não tiver documento selecionado, permitir upload
$modo_upload = empty($id_documento);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinatura Eletrônica de Documentos</title>
    <link rel="stylesheet" href="style.css">
    <!-- Adicionando pdf-lib para manipulação de PDF no cliente -->
    <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
    <style>
        .upload-area {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            cursor: pointer;
            background-color: #fafafa;
        }
        .upload-area:hover {
            background-color: #eee;
        }
        iframe {
            width: 100%;
            height: 400px;
            border: 1px solid #ccc;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-bar" style="margin-bottom: 20px; text-align: right;">
        <a href="../index.php" class="btn-nav" style="background-color: #6c757d;">Voltar ao Sistema</a>
        <a href="index.php" class="btn-nav">Nova Assinatura (Upload)</a>
        <a href="enviar_documento.php" class="btn-nav">Enviar Documento (Multi)</a>
        <a href="finalizar.php" class="btn-nav" style="background-color: #198754;">Finalizar (ICP-Brasil)</a>
    </div>

    <h2>Assinatura Eletrônica</h2>
    
    <?php if ($modo_upload): ?>
        <!-- Tela de Upload -->
        <div id="uploadStep">
            <p>Selecione um arquivo PDF para assinar:</p>
            <form id="formUpload" enctype="multipart/form-data">
                <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                    <p>Clique aqui ou arraste o arquivo PDF</p>
                    <input type="file" id="fileInput" name="arquivo" accept="application/pdf" style="display: none;" onchange="handleFileSelect(this)">
                    <span id="fileName"></span>
                </div>
                <button type="submit" class="btn-assinar" id="btnUpload" disabled>Carregar Documento</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Tela de Visualização e Assinatura (inicialmente oculta se for upload) -->
    <div id="assinaturaStep" style="<?php echo $modo_upload ? 'display:none;' : ''; ?>">
        
        <?php if (!$modo_upload): ?>
             <div class="documento-preview">
                <p><strong>Documento ID:</strong> <?php echo htmlspecialchars($id_documento); ?></p>
                <p><strong>Para:</strong> <?php echo htmlspecialchars($nome_funcionario); ?></p>
                <hr>
                <p>Visualização do documento pré-cadastrado...</p>
            </div>
        <?php else: ?>
            <div id="pdfPreviewContainer">
                <iframe id="pdfPreview" src=""></iframe>
            </div>
        <?php endif; ?>

        <form id="formAssinatura">
            <input type="hidden" id="id_documento" name="id_documento" value="<?php echo htmlspecialchars($id_documento); ?>">
            <input type="hidden" id="caminho_pdf_original" name="caminho_pdf_original">
            
            <div class="form-group">
                <label for="nome">Nome Completo:</label>
                <input type="text" id="nome" name="nome" placeholder="Seu Nome Completo" required value="<?php echo htmlspecialchars($nome_funcionario !== 'Funcionário Exemplo' ? $nome_funcionario : ''); ?>">
            </div>

            <div class="form-group">
                <label for="cpf">CPF (apenas números):</label>
                <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" required>
            </div>

            <div class="form-group">
                <label for="rg">RG:</label>
                <input type="text" id="rg" name="rg" placeholder="Digite seu RG" required>
            </div>

            <div class="termo-aceite">
                <input type="checkbox" id="aceite" name="aceite" required>
                <label for="aceite">
                    Declaro que li e estou de acordo com o teor deste documento, assinando-o eletronicamente conforme a Medida Provisória nº 2.200-2/2001.
                </label>
            </div>

            <!-- Campos ocultos para auditoria -->
            <input type="hidden" id="latitude" name="latitude">
            <input type="hidden" id="longitude" name="longitude">
            <input type="hidden" id="device_info" name="device_info">

            <button type="submit" class="btn-assinar" id="btnAssinar">Assinar Documento</button>
        </form>
    </div>

    <div class="info-auditoria">
        <p>Seu IP, localização e dados do dispositivo serão registrados para fins de auditoria.</p>
    </div>
</div>

<script src="script.js"></script>
</body>
</html>
