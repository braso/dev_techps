<?php
include_once "../conecta.php";
require_once "../tcpdf/tcpdf.php";

function resolverCaminhoPdfArquivo(string $caminho): string {
    $caminho = trim($caminho);
    if ($caminho === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $caminho)) {
        return $caminho;
    }

    $candidatos = array();
    if ($caminho[0] === '/' || preg_match('/^[A-Za-z]:[\\\/]/', $caminho)) {
        $candidatos[] = $caminho;
    } else {
        $candidatos[] = __DIR__ . '/' . $caminho;
        $candidatos[] = dirname(__DIR__) . '/' . ltrim($caminho, '/\\');
        $candidatos[] = $caminho;
    }

    foreach ($candidatos as $candidato) {
        $real = realpath($candidato);
        if ($real && file_exists($real)) {
            return $real;
        }
        if (file_exists($candidato)) {
            return $candidato;
        }
    }

    return '';
}

$id_instancia = intval($_GET['id'] ?? 0);
if ($id_instancia <= 0) {
    die("ID do documento não informado.");
}

$docIdAssinatura = 'INST_' . $id_instancia;
$assinatura = null;
$assinaturaTableExiste = false;
$chkAss = @mysqli_query($conn, "SHOW TABLES LIKE 'solicitacoes_assinatura'");
if ($chkAss instanceof mysqli_result && mysqli_num_rows($chkAss) > 0) {
    $assinaturaTableExiste = true;
}

if ($assinaturaTableExiste) {
    $resAss = query(
        "SELECT s1.id, s1.status, s1.caminho_arquivo
         FROM solicitacoes_assinatura s1
         INNER JOIN (
             SELECT id_documento, MAX(id) AS max_id
             FROM solicitacoes_assinatura
             WHERE id_documento <> ''
             GROUP BY id_documento
         ) ult ON ult.max_id = s1.id
         WHERE s1.id_documento = '" . addslashes($docIdAssinatura) . "'
         LIMIT 1"
    );
    if ($resAss instanceof mysqli_result) {
        $assinatura = mysqli_fetch_assoc($resAss);
    }
}

if (!empty($assinatura)) {
    $statusAss = strtolower(trim(strval($assinatura['status'] ?? '')));
    $statusFinal = in_array($statusAss, ['concluido', 'assinado', 'finalizado'], true);

    if (!$statusFinal) {
        die("Documento disponível somente após conclusão das assinaturas.");
    }

    $caminhoAss = trim(strval($assinatura['caminho_arquivo'] ?? ''));
    if ($caminhoAss !== '') {
        $baseAss = realpath(__DIR__ . '/../assinatura');
        $candidato = realpath(__DIR__ . '/../assinatura/' . ltrim($caminhoAss, '/\\'));
        if ($baseAss && $candidato && strpos(str_replace('\\', '/', $candidato), str_replace('\\', '/', $baseAss) . '/') === 0 && file_exists($candidato)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="documento_assinado_' . $id_instancia . '.pdf"');
            readfile($candidato);
            exit;
        }
    }

    die("Documento assinado não encontrado.");
}

// 1. Busca dados da instância e do tipo
$a_inst = carregar('inst_documento_modulo', $id_instancia);
if (!$a_inst) {
    die("Documento não encontrado.");
}

$id_tipo = $a_inst['inst_nb_tipo_doc'];
$a_tipo = carregar('tipos_documentos', $id_tipo);

// Para compatibilidade com o restante do código que espera $dados
// Padrão baseado em gerenciar_ajustes.php que já funciona com logo
$resDados = query("SELECT i.*, t.tipo_tx_nome, t.tipo_tx_logo, t.tipo_tx_cabecalho, t.tipo_tx_rodape, u.user_tx_nome AS criador_nome FROM inst_documento_modulo i JOIN tipos_documentos t ON t.tipo_nb_id = i.inst_nb_tipo_doc LEFT JOIN user u ON u.user_nb_id = i.inst_nb_user WHERE i.inst_nb_id = " . intval($id_instancia) . " LIMIT 1");
$dados = ($resDados instanceof mysqli_result) ? mysqli_fetch_assoc($resDados) : [];

if (empty($dados)) {
    die("Documento não encontrado.");
}

// 2. Busca valores preenchidos
$resCampos = query("SELECT v.valo_tx_valor, c.camp_tx_label, c.camp_tx_tipo FROM valo_documento_modulo v JOIN camp_documento_modulo c ON c.camp_nb_id = v.valo_nb_campo WHERE v.valo_nb_instancia = " . intval($id_instancia) . " ORDER BY c.camp_nb_ordem ASC, c.camp_nb_id ASC");
$valores = [];
if ($resCampos instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($resCampos)) {
        $valores[] = $row;
    }
}

if (empty($valores)) {
    die("Nenhum valor preenchido para o documento.");
}

// 3. Configuração do PDF - Classe com logo incluída no cabeçalho
class MYPDF extends TCPDF {
    public $custom_header = '';
    public $custom_footer = '';
    public $logo_path = '';

    public function Header() {
        if (!empty($this->logo_path)) {
            $logo = resolverCaminhoPdfArquivo(strval($this->logo_path));
            if ($logo !== '') {
                $this->Image($logo, 15, 8, 30, 20, '', '', '', true);
            }
        }

        $this->SetY(15);
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 15, mb_strtoupper($this->custom_header, 'UTF-8'), 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Line(15, 28, 195, 28);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $textoRodape = trim(strval($this->custom_footer));
        if ($textoRodape !== '') {
            $textoRodape .= ' | ';
        }
        $this->Cell(0, 10, $textoRodape . 'Gerado em ' . date('d/m/Y H:i:s') . ' | Pagina ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->custom_header = trim(strip_tags(strval($dados['tipo_tx_cabecalho'] ?? '')));
if ($pdf->custom_header === '') {
    $pdf->custom_header = strval($dados['tipo_tx_nome'] ?? 'Documento');
}
$pdf->custom_footer = trim(strip_tags(strval($dados['tipo_tx_rodape'] ?? '')));
$pdf->logo_path = strval($dados['tipo_tx_logo'] ?? '');

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema Braso');
$pdf->SetTitle(strval($dados['tipo_tx_nome'] ?? 'Documento'));

$pdf->SetMargins(15, 35, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 11);

$html = '<table cellpadding="3" border="0" style="width:100%;">';
$html .= '<tr><td style="border-bottom:0.1pt solid #ddd;"><b>Data de Geração:</b> ' . date("d/m/Y H:i") . '</td></tr>';
$html .= '<tr><td style="border-bottom:0.1pt solid #ddd;"><b>Emitido por:</b> ' . htmlspecialchars(strval($dados['criador_nome'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td></tr>';
$html .= '</table>';
$html .= '<br><br>';

foreach ($valores as $v) {
    $valorBruto = strval($v['valo_tx_valor'] ?? '');
    if (trim($valorBruto) === '') {
        continue;
    }

    $label = htmlspecialchars(strval($v['camp_tx_label'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (strpos($valorBruto, '<table') !== false) {
        $html .= '<br><b>' . $label . ':</b><br>';
        $html .= $valorBruto . '<br>';
    } else {
        $valor = htmlspecialchars($valorBruto, ENT_QUOTES, 'UTF-8');
        $html .= '<table cellpadding="4" border="0" style="width:100%;">';
        $html .= '<tr>';
        $html .= '<td width="30%" style="border-bottom:0.1pt solid #eee;"><b>' . $label . ':</b></td>';
        $html .= '<td width="70%" style="border-bottom:0.1pt solid #eee;">' . $valor . '</td>';
        $html .= '</tr>';
        $html .= '</table>';
    }
}

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Output(strval($dados['tipo_tx_nome'] ?? 'Documento') . '_' . $id_instancia . '.pdf', 'I');
?>
