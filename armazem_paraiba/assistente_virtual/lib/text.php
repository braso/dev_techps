<?php
/**
 * Extração e normalização de texto para a base de conhecimento.
 */

if (!function_exists('ia_limpar_texto')) {
    function ia_limpar_texto(string $texto): string {
        $texto = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $texto);
        $texto = preg_replace('/[ \t]+/u', ' ', $texto);
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto);
        return trim($texto);
    }
}

if (!function_exists('ia_sem_acentos')) {
    function ia_sem_acentos(string $texto): string {
        $mapa = [
            'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n',
            'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A',
            'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
            'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
            'Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
            'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
            'Ç'=>'C','Ñ'=>'N',
        ];
        return strtr($texto, $mapa);
    }
}

if (!function_exists('ia_normalizar_busca')) {
    function ia_normalizar_busca(string $texto): string {
        $texto = ia_sem_acentos($texto);
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = preg_replace('/[^a-z0-9]+/u', ' ', $texto);
        return trim(preg_replace('/\s+/u', ' ', $texto));
    }
}

if (!function_exists('ia_hash_texto')) {
    function ia_hash_texto(string $texto): string {
        return hash('sha256', ia_limpar_texto($texto));
    }
}

if (!function_exists('ia_contar_palavras')) {
    function ia_contar_palavras(string $texto): int {
        $partes = preg_split('/\s+/u', trim($texto));
        return is_array($partes) ? count(array_filter($partes, fn($p) => $p !== '')) : 0;
    }
}

if (!function_exists('ia_docx_disponivel')) {
    function ia_docx_disponivel(): bool {
        if (class_exists('ZipArchive') || class_exists('PharData')) {
            return true;
        }
        $desabilitadas = array_map('trim', explode(',', strval(ini_get('disable_functions'))));
        return function_exists('exec') && !in_array('exec', $desabilitadas, true);
    }
}

if (!function_exists('ia_docx_para_paragrafos')) {
    /** Extrai os parágrafos de um .docx sem depender de bibliotecas externas. */
    function ia_docx_para_paragrafos(string $caminho): array {
        if (!is_file($caminho)) {
            return [];
        }
        $xml = false;

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($caminho) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
            }
        }

        // Fallback 1: PharData lê o ZIP interno do DOCX sem a extensão zip.
        if (($xml === false || $xml === '') && class_exists('PharData')) {
            try {
                $xml = file_get_contents('phar://' . $caminho . '/word/document.xml');
            } catch (Throwable $e) {
                $xml = false;
            }
        }

        // Fallback 2: unzip do sistema (presente em muitos containers Linux).
        if (($xml === false || $xml === '') && function_exists('exec')) {
            $desabilitadas = array_map('trim', explode(',', strval(ini_get('disable_functions'))));
            if (!in_array('exec', $desabilitadas, true)) {
                $saida = [];
                $codigo = 0;
                @exec('unzip -p ' . escapeshellarg($caminho) . ' word/document.xml', $saida, $codigo);
                if ($codigo === 0 && !empty($saida)) {
                    $xml = implode("\n", $saida);
                }
            }
        }

        if ($xml === false || $xml === '') {
            return [];
        }
        $dom = new DOMDocument();
        $ok = @$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (!$ok) {
            return [];
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragrafos = [];
        foreach ($xpath->query('//w:body/w:p') as $p) {
            $texto = '';
            foreach ($xpath->query('.//w:t | .//w:tab | .//w:br', $p) as $no) {
                if ($no->nodeName === 'w:tab') {
                    $texto .= "\t";
                } elseif ($no->nodeName === 'w:br') {
                    $texto .= "\n";
                } else {
                    $texto .= $no->nodeValue;
                }
            }
            $texto = ia_limpar_texto($texto);
            if ($texto !== '') {
                $paragrafos[] = $texto;
            }
        }
        return $paragrafos;
    }
}

if (!function_exists('ia_pdf_para_texto')) {
    function ia_pdf_para_texto(string $caminho): string {
        if (!is_file($caminho)) {
            return '';
        }
        $desabilitadas = array_map('trim', explode(',', strval(ini_get('disable_functions'))));
        if (function_exists('exec') && !in_array('exec', $desabilitadas, true)) {
            $cmd = 'pdftotext -layout -enc UTF-8 ' . escapeshellarg($caminho) . ' -';
            $saida = [];
            $codigo = 0;
            @exec($cmd, $saida, $codigo);
            if ($codigo === 0 && !empty($saida)) {
                return ia_limpar_texto(implode("\n", $saida));
            }
        }
        return '';
    }
}

if (!function_exists('ia_arquivo_para_texto')) {
    function ia_arquivo_para_texto(string $caminho): string {
        $ext = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
        if ($ext === 'docx') {
            return ia_limpar_texto(implode("\n\n", ia_docx_para_paragrafos($caminho)));
        }
        if ($ext === 'pdf') {
            return ia_pdf_para_texto($caminho);
        }
        if (in_array($ext, ['md', 'txt', 'csv'], true)) {
            $texto = @file_get_contents($caminho);
            return $texto === false ? '' : ia_limpar_texto($texto);
        }
        if (in_array($ext, ['html', 'htm'], true)) {
            $html = @file_get_contents($caminho);
            if ($html === false) { return ''; }
            $html = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', ' ', $html);
            return ia_limpar_texto(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return '';
    }
}

if (!function_exists('ia_titulo_de_arquivo')) {
    function ia_titulo_de_arquivo(string $caminho): string {
        $nome = pathinfo($caminho, PATHINFO_FILENAME);
        $nome = preg_replace('/[_\-]+/', ' ', $nome);
        return trim(mb_convert_case($nome, MB_CASE_TITLE, 'UTF-8'));
    }
}

if (!function_exists('ia_markdown_para_secoes')) {
    /**
     * Converte Markdown em seções (## título). O primeiro # vira o título do
     * documento e não entra como seção.
     */
    function ia_markdown_para_secoes(string $markdown, string $tituloPadrao = 'Documento'): array {
        $markdown = ia_limpar_texto($markdown);
        if ($markdown === '') {
            return [];
        }
        $linhas = explode("\n", $markdown);
        $tituloDoc = $tituloPadrao;
        $secoes = [];
        $atualTitulo = '';
        $buffer = [];

        $fechar = function () use (&$secoes, &$atualTitulo, &$buffer, $tituloDoc) {
            $texto = trim(implode("\n", $buffer));
            if ($texto !== '') {
                $secoes[] = [
                    'titulo' => $atualTitulo !== '' ? $atualTitulo : $tituloDoc,
                    'texto' => $texto,
                ];
            }
            $buffer = [];
        };

        foreach ($linhas as $linha) {
            if (preg_match('/^#\s+(.+)$/u', $linha, $m) && $atualTitulo === '' && empty($secoes)) {
                $tituloDoc = trim($m[1]);
                continue;
            }
            if (preg_match('/^##\s+(.+)$/u', $linha, $m)) {
                if ($atualTitulo === '' && empty($secoes)) {
                    // Preâmbulo antes do primeiro ##: blockquote de fonte ou
                    // apresentação não vira seção própria.
                    $pre = trim(implode("\n", $buffer));
                    if ($pre === '' || strpos($pre, '>') === 0) {
                        $buffer = [];
                    } else {
                        $fechar();
                    }
                } else {
                    $fechar();
                }
                $atualTitulo = trim($m[1]);
                continue;
            }
            $buffer[] = $linha;
        }
        $fechar();

        if (empty($secoes)) {
            $secoes[] = ['titulo' => $tituloDoc, 'texto' => $markdown];
        }
        return $secoes;
    }
}

if (!function_exists('ia_manual_para_secoes')) {
    /** Converte os parágrafos do manual do Espelho de Ponto em seções. */
    function ia_manual_para_secoes(array $paragrafos): array {
        $headings = [
            'OBJETIVO', 'LOGIN DE ACESSO', 'ESQUESEU SUA SENHA', 'TELA SISTEMA',
            'ROTINA DE EMPRESA/FILIAL', 'ROTINA DE ENDOSSO', 'ROTINA DE FERIADO',
            'ROTINA DE FÉRIAS', 'ROTINA DE FUNCIONÁRIO', 'ROTINA DE MACRO',
            'ROTINA DE MOTIVOS', 'RONTINA DE CARGO', 'ROTINA DE PARÂMETRO',
            'ROTINA DE USUÁRIO', 'ROTINA DE PLACAS', 'ROTINA DE CELULARES',
            'ROTINA SETOR', 'ROTINA TIPO DE DOCUMENTO', 'ROTINA HABILIDADES TÉCNICAS',
            'ROTINA HABILIDADES COMPORTAMENTAIS', 'ROTINA PERFIL DE ACESSO',
            'ROTINA PERMISSÃO DE USUÁRIOS', 'CONSULTAR ENDOSSOS', 'ESPELHO DE PONTO',
            'INTEGRAÇÃO DE PONTOS', 'NÃO CONFORMIDADES', 'NÃO CADASTRADO',
            'ROTINAS DE PAINÉIS',
        ];
        $mapa = [];
        foreach ($headings as $h) {
            $mapa[ia_normalizar_busca($h)] = $h;
        }

        // Começa no título real "OBJETIVO" (o do sumário contém pontilhado).
        $inicio = 0;
        foreach ($paragrafos as $i => $p) {
            if (mb_strtoupper(trim($p), 'UTF-8') === 'OBJETIVO') {
                $inicio = $i;
                break;
            }
        }

        $secoes = [];
        $titulo = '';
        $buffer = [];
        $fechar = function () use (&$secoes, &$titulo, &$buffer) {
            $texto = trim(implode("\n\n", $buffer));
            if ($texto !== '') {
                $secoes[] = [
                    'titulo' => $titulo !== '' ? $titulo : 'Manual do Sistema',
                    'texto' => $texto,
                ];
            }
            $buffer = [];
        };

        foreach (array_slice($paragrafos, $inicio) as $p) {
            $p = trim($p);
            if ($p === '') { continue; }
            $up = mb_strtoupper($p, 'UTF-8');
            if (in_array($up, ['SUMÁRIO', 'LISTA DE FIGURAS'], true)) { continue; }
            if (preg_match('/^Figura\s*:?\s*\d+/iu', $p)) { continue; }
            if (preg_match('/^\d{1,3}$/', $p)) { continue; }
            if (preg_match('/^_{5,}$/', $p)) { continue; }
            $norm = ia_normalizar_busca($p);
            if (isset($mapa[$norm])) {
                $fechar();
                $titulo = $mapa[$norm];
                continue;
            }
            if (preg_match('/^\d+(\.\d+)?\s*[–-]\s+.+/u', $p) && mb_strlen($p) < 140) {
                $buffer[] = '### ' . $p;
                continue;
            }
            $buffer[] = $p;
        }
        $fechar();

        if (empty($secoes)) {
            $secoes[] = ['titulo' => 'Manual do Sistema', 'texto' => implode("\n\n", $paragrafos)];
        }
        return $secoes;
    }
}

if (!function_exists('ia_chunkar_texto')) {
    /**
     * Divide um texto em blocos de aproximadamente N palavras, com sobreposição.
     * Preserva parágrafos quando possível.
     */
    function ia_chunkar_texto(string $texto, int $maxPalavras = 420, int $overlap = 60): array {
        $texto = ia_limpar_texto($texto);
        if ($texto === '') {
            return [];
        }
        $palavras = preg_split('/\s+/u', $texto);
        if (!is_array($palavras)) {
            return [$texto];
        }
        $total = count($palavras);
        if ($total <= $maxPalavras) {
            return [$texto];
        }
        $maxPalavras = max(100, $maxPalavras);
        $overlap = max(0, min($overlap, intdiv($maxPalavras, 2)));
        $chunks = [];
        $inicio = 0;
        while ($inicio < $total) {
            $fim = min($inicio + $maxPalavras, $total);
            $trecho = implode(' ', array_slice($palavras, $inicio, $fim - $inicio));
            $chunks[] = trim($trecho);
            if ($fim >= $total) {
                break;
            }
            $inicio = max($inicio + 1, $fim - $overlap);
        }
        return $chunks;
    }
}

if (!function_exists('ia_limitar_chars')) {
    function ia_limitar_chars(string $texto, int $max): string {
        if (mb_strlen($texto, 'UTF-8') <= $max) {
            return $texto;
        }
        return mb_substr($texto, 0, $max, 'UTF-8') . '…';
    }
}

if (!function_exists('ia_redigir_texto')) {
    /** Mascara CPF, e-mail e telefone antes de gravar logs de consulta. */
    function ia_redigir_texto(string $texto): string {
        $texto = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', '***.***.***-**', $texto);
        $texto = preg_replace('/\b[\w.+-]+@[\w.-]+\.[a-z]{2,}\b/iu', '***@***.***', $texto);
        $texto = preg_replace('/\b\(?\d{2}\)?\s?9?\d{4}[-.\s]?\d{4}\b/', '(**) *****-****', $texto);
        return $texto;
    }
}
