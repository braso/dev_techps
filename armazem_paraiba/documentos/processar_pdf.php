<?php
include_once "../conecta.php";
require_once "../tcpdf/tcpdf.php";

$id_instancia = $_GET['id'];
if (empty($id_instancia)) {
    die("ID do documento não informado.");
}

// 1. Busca dados da instância e do tipo
$a_inst = carregar('inst_documento_modulo', $id_instancia);
if (!$a_inst) die("Documento não encontrado.");

$id_tipo = $a_inst['inst_nb_tipo_doc'];
$a_tipo = carregar('tipos_documentos', $id_tipo);

// Para compatibilidade com o restante do código que espera $dados
// e para incluir o nome do criador, que não está no carregar direto.
$sql_dados_completos = "SELECT i.*, t.*, u.user_tx_nome as criador 
                        FROM inst_documento_modulo i
                        JOIN tipos_documentos t ON i.inst_nb_tipo_doc = t.tipo_nb_id
                        JOIN user u ON i.inst_nb_user = u.user_nb_id
                        WHERE i.inst_nb_id = $id_instancia";
$res_inst = query($sql_dados_completos);
$dados = mysqli_fetch_assoc($res_inst);

if (!$dados) {
    die("Documento não encontrado."); // Should not happen if $a_inst was found
}

// 2. Busca valores preenchidos
$sql_v = "SELECT v.valo_tx_valor, c.camp_tx_label, c.camp_tx_tipo 
          FROM valo_documento_modulo v
          JOIN camp_documento_modulo c ON v.valo_nb_campo = c.camp_nb_id
          WHERE v.valo_nb_instancia = $id_instancia
          ORDER BY c.camp_nb_ordem ASC";
$res_v = query($sql_v);
$valores = [];
while ($v = mysqli_fetch_assoc($res_v)) {
    $valores[] = $v;
}

// 3. Configuração do PDF
class MYPDF extends TCPDF {
    public $custom_header = "";
    public $custom_footer = "";
    public $logo_path = "";

    public function Header() {
        if (!empty($this->logo_path)) {
            $logo_path = $this->logo_path;
            if (strpos($logo_path, '../') === 0) {
                $logo_path = realpath(__DIR__ . '/' . $logo_path);
            } else {
                $logo_path = realpath($logo_path);
            }

            if ($logo_path && file_exists($logo_path)) {
                $this->Image($logo_path, 15, 8, 25);
            }
        }
        
        $this->SetY(15);
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 15, strtoupper($this->custom_header), 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Line(15, 28, 195, 28);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $footer_text = 'Gerado em ' . date("d/m/Y H:i:s");
        $this->Cell(0, 10, $footer_text . ' | Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Dados dinâmicos para o Header
$pdf->custom_header = $dados['tipo_tx_nome'];
$pdf->logo_path = $dados['tipo_tx_logo'];

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema Braso');
$pdf->SetTitle($dados['tipo_tx_nome']);

$pdf->SetMargins(15, 35, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

$pdf->AddPage();
$pdf->SetFont('helvetica', '', 11);

$html = "<p><b>Data de Geração:</b> " . date("d/m/Y H:i", strtotime($dados['inst_dt_criacao'])) . "<br>";
$html .= "<b>Emitido por:</b> " . $dados['criador'] . "</p>";
$html .= "<hr><br>";

$html .= "<table cellpadding='5' border='0.1'>";
foreach ($valores as $v) {
    if (empty($v['valo_tx_valor'])) continue;
    $html .= "<tr>";
    $html .= "<td width='30%'><b>{$v['camp_tx_label']}:</b></td>";
    $html .= "<td width='70%'>{$v['valo_tx_valor']}</td>";
    $html .= "</tr>";
}
$html .= "</table>";

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Output($dados['tipo_tx_nome'] . '_' . $id_instancia . '.pdf', 'I');
?>
