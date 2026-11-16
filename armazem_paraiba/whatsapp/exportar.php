<?php
/**
 * Exportação CSV de uma conversa do CRM - TECHPS.
 *
 * GET:
 *   conversa  ID da conversa
 *   modo      dias | datas
 *   dias      quantidade de dias (mínimo 1)
 *   inicio    data inicial (Y-m-d) quando modo=datas
 *   fim       data final (Y-m-d) quando modo=datas
 *   csrf      token da sessão
 */

require_once __DIR__ . '/bootstrap.php';

verificaPermissao('/whatsapp/index.php');

if (!wa_csrf_validar(isset($_GET['csrf']) ? strval($_GET['csrf']) : '')) {
    http_response_code(419);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Sessão expirada. Atualize a página e tente novamente.';
    exit;
}

$conversaId = (int)($_GET['conversa'] ?? 0);
if ($conversaId <= 0) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Conversa inválida.';
    exit;
}

$conversa = wa_conversa_por_id($conversaId);
if (!$conversa) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Conversa não encontrada.';
    exit;
}

$modo = strval($_GET['modo'] ?? 'dias') === 'datas' ? 'datas' : 'dias';
$hoje = new DateTimeImmutable('today');

if ($modo === 'datas') {
    $inicioRaw = trim(strval($_GET['inicio'] ?? ''));
    $fimRaw = trim(strval($_GET['fim'] ?? ''));
    $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $inicioRaw);
    $fim = DateTimeImmutable::createFromFormat('!Y-m-d', $fimRaw);
    if (!$inicio || !$fim || $inicio->format('Y-m-d') !== $inicioRaw || $fim->format('Y-m-d') !== $fimRaw) {
        http_response_code(422);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Informe uma data inicial e uma data final válidas.';
        exit;
    }
    $inicio = $inicio->setTime(0, 0, 0);
    $fim = $fim->setTime(23, 59, 59);
    if ($inicio > $fim) {
        http_response_code(422);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'A data inicial não pode ser maior que a data final.';
        exit;
    }
    if ($inicio->diff($fim)->days > 3650) {
        http_response_code(422);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'O intervalo máximo para exportação é de 10 anos.';
        exit;
    }
} else {
    $dias = max(1, min(3650, (int)($_GET['dias'] ?? 30)));
    $inicio = $hoje->modify('-' . ($dias - 1) . ' days')->setTime(0, 0, 0);
    $fim = $hoje->setTime(23, 59, 59);
}

// O banco grava em UTC; os limites escolhidos pelo usuário estão no fuso
// do servidor. Converte antes de filtrar para não perder mensagens perto
// da meia-noite.
$tzUtc = new DateTimeZone('UTC');
$inicioUtc = $inicio->setTimezone($tzUtc)->format('Y-m-d H:i:s');
$fimUtc = $fim->setTimezone($tzUtc)->format('Y-m-d H:i:s');

$rs = query(
    "SELECT m.msg_nb_id, m.msg_dt_criacao, m.msg_tx_direcao, m.msg_tx_tipo, m.msg_tx_conteudo,
            m.msg_tx_status, m.msg_tx_provider_id, m.msg_tx_mime, m.msg_tx_arquivo,
            u.user_tx_nome AS usuario_nome,
            c.cont_tx_nome, c.cont_tx_e164, c.cont_tx_telefone,
            (SELECT a.anx_tx_nome FROM wa_anexo a
              WHERE a.anx_nb_msg = m.msg_nb_id ORDER BY a.anx_nb_id DESC LIMIT 1) AS anexo_nome
     FROM wa_mensagem m
     JOIN wa_conversa cv ON cv.conv_nb_id = m.msg_nb_conversa
     JOIN wa_contato c ON c.cont_nb_id = cv.conv_nb_contato
     LEFT JOIN user u ON u.user_nb_id = m.msg_nb_user
     WHERE m.msg_nb_conversa = ? AND m.msg_dt_criacao BETWEEN ? AND ?
     ORDER BY m.msg_nb_id ASC",
    "iss",
    [$conversaId, $inicioUtc, $fimUtc]
);

$tipos = [
    'texto' => 'Texto',
    'imagem' => 'Imagem',
    'audio' => 'Áudio',
    'video' => 'Vídeo',
    'documento' => 'Documento',
    'template' => 'Template',
    'sistema' => 'Sistema',
];
$direcoes = ['in' => 'Entrada', 'out' => 'Saída'];

$csvSeguro = static function ($valor): string {
    $texto = strval($valor ?? '');
    // Evita que uma célula iniciada por fórmula seja interpretada pelo Excel.
    if ($texto !== '' && preg_match('/^[=+\-@]/', $texto)) {
        return "'" . $texto;
    }
    return $texto;
};

$nomeContato = trim(strval($conversa['cont_tx_nome'] ?? ''));
$slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $nomeContato) ?? '';
$slug = trim($slug, '-_.');
if ($slug === '') {
    $slug = 'contato';
}
$nomeArquivo = 'crm-techps-' . $slug . '-' . date('Ymd-His') . '.csv';

wa_log('exportacao_conversa', 'Conversa exportada em CSV.', [
    'conversa' => $conversaId,
    'inicio' => $inicio->format('Y-m-d H:i:s'),
    'fim' => $fim->format('Y-m-d H:i:s'),
    'usuario' => (int)($_SESSION['user_nb_id'] ?? 0),
]);

if (!headers_sent()) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
}
echo "\xEF\xBB\xBF";
$saida = fopen('php://output', 'w');
if ($saida === false) {
    exit;
}

fputcsv($saida, [
    'ID',
    'Data/Hora',
    'Direção',
    'Tipo',
    'Mensagem',
    'Status',
    'Usuário',
    'Contato',
    'Telefone',
    'Anexo',
    'MIME',
    'ID do provedor',
], ';', '"', '\\');

while ($rs && ($row = mysqli_fetch_assoc($rs))) {
    $direcao = strval($row['msg_tx_direcao'] ?? 'in');
    $tipo = strval($row['msg_tx_tipo'] ?? 'texto');
    $conteudo = strval($row['msg_tx_conteudo'] ?? '');
    $anexo = strval($row['anexo_nome'] ?? '');
    if ($anexo === '' && trim(strval($row['msg_tx_arquivo'] ?? '')) !== '') {
        $anexo = basename(strval($row['msg_tx_arquivo']));
    }
    if ($conteudo === '' && $anexo !== '') {
        $conteudo = '[' . ($tipos[$tipo] ?? $tipo) . ']';
    }
    $dataFormatada = wa_data_utc_para_local($row['msg_dt_criacao'] ?? '', 'd/m/Y H:i:s');
    $usuario = $direcao === 'out'
        ? (trim(strval($row['usuario_nome'] ?? '')) !== '' ? strval($row['usuario_nome']) : 'Sistema/Celular')
        : 'Contato';

    fputcsv($saida, [
        (int)$row['msg_nb_id'],
        $dataFormatada,
        $direcoes[$direcao] ?? $direcao,
        $tipos[$tipo] ?? $tipo,
        $csvSeguro($conteudo),
        strval($row['msg_tx_status'] ?? ''),
        $csvSeguro($usuario),
        $csvSeguro($row['cont_tx_nome'] ?? ''),
        wa_formatar_telefone($row['cont_tx_e164'] ?? $row['cont_tx_telefone'] ?? ''),
        $csvSeguro($anexo),
        strval($row['msg_tx_mime'] ?? ''),
        $csvSeguro($row['msg_tx_provider_id'] ?? ''),
    ], ';', '"', '\\');
}

fclose($saida);
exit;
