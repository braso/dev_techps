<?php
    /* 
    Exportação de Adicional Noturno - Arquivo TXT
    Gera arquivo formatado conforme padrão de Importação de Lançamentos
    */

    header("Expires: 01 Jan 2001 00:00:00 GMT");
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header('Cache-Control: post-check=0, pre-check=0', FALSE);
    header('Pragma: no-cache');

    // Validações de segurança
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method Not Allowed');
    }

    // Validar campos obrigatórios
    if (empty($_POST['empresa']) || empty($_POST['competencia']) || empty($_POST['dados'])) {
        http_response_code(400);
        die('Campos obrigatórios faltando');
    }

    // Sanitizar e validar dados
    $empresa = intval($_POST['empresa']);
    $competencia = preg_replace('/[^0-9]/', '', $_POST['competencia']);
    
    // Validar formato da competência AAAAMM
    if (strlen($competencia) !== 6 || !preg_match('/^\d{6}$/', $competencia)) {
        http_response_code(400);
        die('Competência em formato inválido');
    }

    // Decodificar dados JSON com validação
    $dados = json_decode($_POST['dados'], true);
    if (!is_array($dados) || empty($dados)) {
        http_response_code(400);
        die('Dados inválidos');
    }

    // Constantes do arquivo TXT
    define('TIPO_REGISTRO', '10');      // Fixo
    define('RUBRICA', '25');             // Fixo
    define('TIPO_PROCESSO', '11');       // Fixo
    define('CODIGO_EMPRESA', '210');     // Fixo

    // Gerar conteúdo TXT
    $linhasTxt = [];
    
    foreach ($dados as $registro) {
        // Sanitizar dados de entrada
        // Truncar para garantir que não ultrapassem o limite do layout (10 para matrícula, 9 para valor)
        $matricula = substr(preg_replace('/[^0-9]/', '', $registro['matricula'] ?? ''), 0, 10);
        $adicionalNoturno = substr(preg_replace('/[^0-9]/', '', $registro['adicionalNoturno'] ?? '0'), 0, 9);
        $statusEndosso = trim($registro['statusEndosso'] ?? '');

        // Validar: apenas registros ENDOSSADOS (E) ou Parcialmente (EP)
        if ($statusEndosso !== 'E' && $statusEndosso !== 'EP') {
            continue;
        }

        // Construir linha conforme especificação (48 caracteres)
        $linha = '';
        
        // Posição 001-002: Tipo Registro (fixo "10")
        $linha .= str_pad(TIPO_REGISTRO, 2, '0', STR_PAD_LEFT);
        
        // Posição 003-012: Código do empregado (10 dígitos)
        $linha .= str_pad($matricula, 10, '0', STR_PAD_LEFT);
        
        // Posição 013-018: Competência (AAAAMM - 6 dígitos)
        $linha .= $competencia;
        
        // Posição 019-027: Código da rubrica (9 dígitos)
        $linha .= str_pad(RUBRICA, 9, '0', STR_PAD_LEFT);
        
        // Posição 028-029: Tipo do Processo (fixo "11")
        $linha .= str_pad(TIPO_PROCESSO, 2, '0', STR_PAD_LEFT);
        
        // Posição 030-038: Valor do Adicional Noturno (9 dígitos)
        $linha .= str_pad($adicionalNoturno, 9, '0', STR_PAD_LEFT);
        
        // Posição 039-048: Código da Empresa (10 dígitos)
        $linha .= str_pad(CODIGO_EMPRESA, 10, '0', STR_PAD_LEFT);
        
        // Validar total de 48 caracteres
        if (strlen($linha) !== 48) {
            continue; // Pular linha se não tiver tamanho correto
        }
        
        $linhasTxt[] = $linha;
    }

    $conteudoFinal = implode("\r\n", $linhasTxt) . "\r\n";

    // Validar se há registros para exportar
    if (count($linhasTxt) === 0) {
        http_response_code(400);
        die('Nenhum registro endossado para exportar');
    }

    // Gerar nome do arquivo
    $nomeArquivo = 'adicional_noturno_' . $competencia . '_' . date('YmdHis') . '.txt';

    if (ob_get_level()) ob_end_clean();

    // Headers para download
    header('Content-Type: text/plain; charset=ISO-8859-1');
    header('Content-Disposition: attachment; filename="' . basename($nomeArquivo) . '"');
    header('Content-Length: ' . strlen($conteudoFinal));
    header('Pragma: no-cache');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

    // Enviar conteúdo
    echo $conteudoFinal;
    exit;
?>