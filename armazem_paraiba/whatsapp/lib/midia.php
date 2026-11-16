<?php

/**
 * Mídias do CRM - TECHPS: validação, armazenamento e download pelo provedor.
 *
 * Os arquivos ficam fora do document root (WHATSAPP_MEDIA_PATH) e só são
 * entregues por arquivo.php, que exige sessão e permissão de acesso ao chat.
 */

if (!function_exists('wa_midia_limites')) {
    /**
     * Limites por tipo, em bytes. A Cloud API aceita limites diferentes por
     * categoria; usamos valores conservadores para os dois provedores.
     */
    function wa_midia_limites(): array {
        $maxMb = max(1, min(100, (int)($_ENV['WHATSAPP_MEDIA_MAX_MB'] ?? 16)));
        $max = $maxMb * 1024 * 1024;
        return [
            'imagem' => min(5 * 1024 * 1024, $max),
            'audio' => min(16 * 1024 * 1024, $max),
            'video' => min(16 * 1024 * 1024, $max),
            'documento' => $max,
        ];
    }
}

if (!function_exists('wa_midia_limite_bytes')) {
    function wa_midia_limite_bytes(?string $tipo = null): int {
        $limites = wa_midia_limites();
        return $tipo !== null && isset($limites[$tipo]) ? (int)$limites[$tipo] : max($limites);
    }
}

if (!function_exists('wa_midia_tipos')) {
    /**
     * MIME => [tipo interno, extensão].
     * SVG e outros formatos executáveis ficam fora por segurança.
     */
    function wa_midia_tipos(): array {
        return [
            'image/jpeg' => ['imagem', 'jpg'],
            'image/jpg' => ['imagem', 'jpg'],
            'image/png' => ['imagem', 'png'],
            'image/webp' => ['imagem', 'webp'],
            'audio/ogg' => ['audio', 'ogg'],
            'application/ogg' => ['audio', 'ogg'],
            'audio/mpeg' => ['audio', 'mp3'],
            'audio/mp4' => ['audio', 'm4a'],
            'audio/x-m4a' => ['audio', 'm4a'],
            'audio/aac' => ['audio', 'aac'],
            'audio/amr' => ['audio', 'amr'],
            'audio/wav' => ['audio', 'wav'],
            'audio/x-wav' => ['audio', 'wav'],
            'video/mp4' => ['video', 'mp4'],
            'video/3gpp' => ['video', '3gp'],
            'application/pdf' => ['documento', 'pdf'],
            'application/x-pdf' => ['documento', 'pdf'],
            'application/msword' => ['documento', 'doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['documento', 'docx'],
            'application/vnd.ms-excel' => ['documento', 'xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['documento', 'xlsx'],
            'application/vnd.ms-powerpoint' => ['documento', 'ppt'],
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['documento', 'pptx'],
            'text/plain' => ['documento', 'txt'],
            'text/csv' => ['documento', 'csv'],
            'application/zip' => ['documento', 'zip'],
        ];
    }
}

if (!function_exists('wa_midia_tipo_por_mime')) {
    function wa_midia_tipo_por_mime(string $mime): ?string {
        $mime = strtolower(trim(explode(';', $mime)[0]));
        $tipos = wa_midia_tipos();
        return isset($tipos[$mime]) ? $tipos[$mime][0] : null;
    }
}

if (!function_exists('wa_midia_extensao_por_mime')) {
    function wa_midia_extensao_por_mime(string $mime): string {
        $mime = strtolower(trim(explode(';', $mime)[0]));
        $tipos = wa_midia_tipos();
        return isset($tipos[$mime]) ? $tipos[$mime][1] : 'bin';
    }
}

if (!function_exists('wa_midia_mime_por_extensao')) {
    function wa_midia_mime_por_extensao(string $nome): string {
        $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        $mapa = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
            'ogg' => 'audio/ogg', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac',
            'amr' => 'audio/amr', 'wav' => 'audio/wav',
            'mp4' => 'video/mp4', '3gp' => 'video/3gpp',
            'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain', 'csv' => 'text/csv', 'zip' => 'application/zip',
        ];
        return $mapa[$ext] ?? '';
    }
}

if (!function_exists('wa_midia_detectar_mime')) {
    function wa_midia_detectar_mime(string $caminho, string $nomeOriginal = ''): string {
        $mime = '';
        if (function_exists('finfo_open') && is_file($caminho)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectado = finfo_file($finfo, $caminho);
                finfo_close($finfo);
                if (is_string($detectado)) {
                    $mime = strtolower(trim(explode(';', $detectado)[0]));
                }
            }
        }
        $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        // O finfo pode identificar um M4A como video/mp4; a extensão .m4a
        // gravada pelo navegador é a indicação mais específica para áudio.
        if ($ext === 'm4a' && in_array($mime, ['video/mp4', 'application/mp4', 'audio/mp4'], true)) {
            return 'audio/mp4';
        }

        // O finfo pode identificar DOCX/XLSX/PPTX como ZIP e DOC/XLS/PPT
        // como OLE. Nesses casos, a extensão conhecida é mais específica.
        $porExtensao = $nomeOriginal !== '' ? wa_midia_mime_por_extensao($nomeOriginal) : '';
        $genericos = ['', 'application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'application/x-ole-storage'];
        $tipos = wa_midia_tipos();
        if (in_array($mime, $genericos, true) && $porExtensao !== '' && isset($tipos[$porExtensao])) {
            $mime = $porExtensao;
        }
        return $mime;
    }
}

if (!function_exists('wa_midia_nome_seguro')) {
    function wa_midia_nome_seguro(string $nome, string $mime): string {
        $nome = basename(str_replace('\\', '/', trim($nome)));
        $nome = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', $nome) ?? '';
        $nome = trim($nome, " ._");
        if ($nome === '') {
            $nome = 'midia.' . wa_midia_extensao_por_mime($mime);
        }
        if (pathinfo($nome, PATHINFO_EXTENSION) === '') {
            $nome .= '.' . wa_midia_extensao_por_mime($mime);
        }
        if (mb_strlen($nome) > 180) {
            $ext = pathinfo($nome, PATHINFO_EXTENSION);
            $base = pathinfo($nome, PATHINFO_FILENAME);
            $nome = mb_substr($base, 0, 170) . ($ext !== '' ? '.' . $ext : '');
        }
        return $nome;
    }
}

if (!function_exists('wa_midia_pasta')) {
    /**
     * Diretório de mídia fora do document root. Em produção, configure
     * WHATSAPP_MEDIA_PATH para um volume persistente fora da pasta pública.
     * O fallback em temp evita deixar anexos acessíveis por URL direta.
     */
    function wa_midia_pasta(): string {
        $dir = trim(strval($_ENV['WHATSAPP_MEDIA_PATH'] ?? getenv('WHATSAPP_MEDIA_PATH') ?: ''));
        if ($dir === '') {
            $docroot = realpath(strval($_SERVER['DOCUMENT_ROOT'] ?? ''));
            if ($docroot !== false && $docroot !== '') {
                $dir = dirname($docroot) . DIRECTORY_SEPARATOR . 'techps_whatsapp_media';
            } else {
                $dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
                    . 'techps_whatsapp_media_' . substr(hash('sha256', strval($_ENV['DB_NAME'] ?? 'default')), 0, 8);
            }
        }

        // Nunca aceitar um diretório dentro do document root.
        $docroot = realpath(strval($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $dirReal = realpath($dir);
        if ($docroot !== false && $docroot !== '' && $dirReal !== false
            && str_starts_with($dirReal, rtrim($docroot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            $dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
                . 'techps_whatsapp_media_' . substr(hash('sha256', strval($_ENV['DB_NAME'] ?? 'default')), 0, 8);
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $dirReal = realpath($dir);
        if ($docroot !== false && $docroot !== '' && $dirReal !== false
            && str_starts_with($dirReal, rtrim($docroot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            $dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
                . 'techps_whatsapp_media_' . substr(hash('sha256', strval($_ENV['DB_NAME'] ?? 'default')), 0, 8);
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            $dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
                . 'techps_whatsapp_media_' . substr(hash('sha256', strval($_ENV['DB_NAME'] ?? 'default')), 0, 8);
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
        }
        @chmod($dir, 0750);
        return $dir;
    }
}

if (!function_exists('wa_midia_gravar_arquivo')) {
    function wa_midia_gravar_arquivo(string $conteudo, string $mime, string $nomeOriginal, ?string &$erro = null): ?array {
        $erro = null;
        $mime = strtolower(trim(explode(';', $mime)[0]));
        $tipo = wa_midia_tipo_por_mime($mime);
        if ($tipo === null) {
            $erro = 'Tipo de arquivo não permitido.';
            return null;
        }
        $tamanho = strlen($conteudo);
        $limite = wa_midia_limite_bytes($tipo);
        if ($tamanho <= 0) {
            $erro = 'Arquivo vazio.';
            return null;
        }
        if ($tamanho > $limite) {
            $erro = 'Arquivo acima do limite de ' . round($limite / 1024 / 1024, 1) . ' MB para ' . $tipo . '.';
            return null;
        }

        $nome = wa_midia_nome_seguro($nomeOriginal, $mime);
        $ext = wa_midia_extensao_por_mime($mime);
        $arquivo = 'wa_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destino = wa_midia_pasta() . '/' . $arquivo;
        if (@file_put_contents($destino, $conteudo, LOCK_EX) === false) {
            $erro = 'Não foi possível gravar o arquivo no servidor.';
            return null;
        }
        @chmod($destino, 0640);

        return [
            'arquivo' => $arquivo,
            'nome' => $nome,
            'mime' => $mime,
            'tamanho' => $tamanho,
            'tipo' => $tipo,
        ];
    }
}

if (!function_exists('wa_midia_salvar_upload')) {
    function wa_midia_salvar_upload(array $file, ?string &$erro = null): ?array {
        $erro = null;
        $codigo = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($codigo === UPLOAD_ERR_NO_FILE) {
            $erro = 'Selecione um arquivo.';
            return null;
        }
        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
            $erro = 'Arquivo maior que o limite configurado no servidor.';
            return null;
        }
        if ($codigo !== UPLOAD_ERR_OK) {
            $erro = 'Falha no upload do arquivo (código ' . $codigo . ').';
            return null;
        }

        $tmp = strval($file['tmp_name'] ?? '');
        $nomeOriginal = strval($file['name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            $erro = 'Arquivo temporário não encontrado.';
            return null;
        }
        // Em requisições web o caminho precisa ser um upload real do PHP.
        // O teste CLI continua permitindo um arquivo temporário controlado.
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp)) {
            $erro = 'Upload inválido.';
            return null;
        }
        $mime = wa_midia_detectar_mime($tmp, $nomeOriginal);
        $tipo = wa_midia_tipo_por_mime($mime);
        if ($tipo === null) {
            $erro = 'Tipo de arquivo não permitido.';
            return null;
        }
        $limite = wa_midia_limite_bytes($tipo);
        $tamanho = (int)filesize($tmp);
        if ($tamanho <= 0) {
            $erro = 'Arquivo vazio.';
            return null;
        }
        if ($tamanho > $limite) {
            $erro = 'Arquivo acima do limite de ' . round($limite / 1024 / 1024, 1) . ' MB para ' . $tipo . '.';
            return null;
        }

        $conteudo = file_get_contents($tmp);
        if ($conteudo === false) {
            $erro = 'Não foi possível ler o arquivo.';
            return null;
        }
        return wa_midia_gravar_arquivo($conteudo, $mime, $nomeOriginal, $erro);
    }
}

if (!function_exists('wa_midia_anexar_mensagem')) {
    function wa_midia_anexar_mensagem(int $mensagemId, array $midia, string $direcao = 'out'): bool {
        if ($mensagemId <= 0 || empty($midia['arquivo'])) {
            return false;
        }
        $direcao = $direcao === 'in' ? 'in' : 'out';
        $ok = wa_exec(
            "INSERT INTO wa_anexo (anx_nb_msg, anx_tx_arquivo, anx_tx_nome, anx_tx_mime, anx_nb_tamanho, anx_tx_direcao)
             VALUES (?, ?, ?, ?, ?, ?)",
            "isssis",
            [
                $mensagemId,
                strval($midia['arquivo']),
                strval($midia['nome'] ?? $midia['arquivo']),
                strval($midia['mime'] ?? 'application/octet-stream'),
                (int)($midia['tamanho'] ?? 0),
                $direcao,
            ]
        );
        if (!$ok) {
            return false;
        }
        wa_exec(
            "UPDATE wa_mensagem SET msg_tx_arquivo = ?, msg_tx_mime = ?, msg_dt_atualizacao = NOW() WHERE msg_nb_id = ?",
            "ssi",
            [strval($midia['arquivo']), strval($midia['mime'] ?? ''), $mensagemId]
        );
        return true;
    }
}

if (!function_exists('wa_midia_baixar_evento')) {
    /**
     * Baixa e armazena a mídia de um evento recebido pelo webhook.
     * Falhas não interrompem o webhook: a mensagem continua no histórico.
     */
    function wa_midia_baixar_evento(array $evento, ProviderInterface $provider, int $mensagemId, string $direcao = 'in'): bool {
        $tipo = strval($evento['tipo_msg'] ?? '');
        if ($mensagemId <= 0 || !in_array($tipo, ['imagem', 'audio', 'video', 'documento'], true)) {
            return false;
        }
        $ref = [
            'media_id' => strval($evento['media_id'] ?? ''),
            'media_url' => strval($evento['media_url'] ?? ''),
            'media_base64' => strval($evento['media_base64'] ?? ''),
            'media_key' => $evento['media_key'] ?? [],
            'mime' => strval($evento['mime'] ?? ''),
            'nome' => strval($evento['filename'] ?? ''),
        ];
        $download = $provider->downloadMedia($ref);
        if (empty($download['success']) || empty($download['conteudo'])) {
            $erro = substr(strval($download['error'] ?? 'Não foi possível baixar a mídia.'), 0, 250);
            wa_log('midia_download_falhou', $erro, [
                'mensagem' => $mensagemId,
                'tipo' => $tipo,
                'media_id' => $ref['media_id'],
            ], 'warning');
            wa_exec("UPDATE wa_mensagem SET msg_tx_erro = ? WHERE msg_nb_id = ?", "si", [$erro, $mensagemId]);
            return false;
        }
        $mime = strval($download['mime'] ?? $ref['mime']);
        $nome = strval($download['nome'] ?? $ref['nome']);
        $midia = wa_midia_gravar_arquivo(strval($download['conteudo']), $mime, $nome);
        if (!$midia) {
            wa_log('midia_armazenamento_falhou', 'Não foi possível armazenar a mídia recebida.', [
                'mensagem' => $mensagemId,
                'mime' => $mime,
            ], 'warning');
            return false;
        }
        return wa_midia_anexar_mensagem($mensagemId, $midia, $direcao);
    }
}
