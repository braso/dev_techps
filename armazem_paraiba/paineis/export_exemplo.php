<?php
require_once __DIR__ . "/../tcpdf/tcpdf.php";
require __DIR__."/../funcoes_ponto.php";

// Recebe os parâmetros do formulário
$periodo = trim($_POST['busca_periodo'], '[]"');
$datas = explode(',', $periodo);
$motivoFiltro = $_POST['motivo'] ?? null; // Novo parâmetro para filtrar por motivo

// Processa as datas
$data_inicio = trim($datas[0], '"');
$data_fim = trim($datas[1], '"');   
$periodoInicio = new DateTime($data_inicio);
$periodoFim = new DateTime($data_fim);

// Configura caminhos e busca dados da empresa
$path = "./arquivos/ajustes";
$path .= "/".$periodoInicio->format("Y-m")."/".$_POST["empresa"];

$empresa = mysqli_fetch_assoc(query(
    "SELECT * FROM empresa
    WHERE empr_tx_status = 'ativo'
        AND empr_nb_id = {$_POST["empresa"]}
    LIMIT 1;"
));

// Processa os arquivos de ajuste
$pastaAjuste = dir($path);
while ($arquivo = $pastaAjuste->read()) {
    if (!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
        $arquivo = $path . "/" . $arquivo;
        $json = json_decode(file_get_contents($arquivo), true);
        foreach ($json['pontos'] as $key) {
            if ($key['moti_tx_nome'] === null) {
                continue;
            }
            
            // Filtra por motivo se especificado
            if ($motivoFiltro && $key['moti_tx_nome'] != $motivoFiltro) {
                continue;
            }

            $motivo = $key['moti_tx_nome'];
            if (!isset($resultado2[$motivo])) {
                $resultado2[$motivo] = [];
            }

            $dadosFunc = [
                "matricula" => $json["matricula"] ?? 'SEM_MATRICULA',
                "nome" => $json["nome"] ?? 'NOME_NAO_INFORMADO',
                "ocupacao" => $json["ocupacao"] ?? 'OCUPACAO_NAO_INFORMADA'
            ];

            $funcionarioKey = $dadosFunc['matricula'] ?? md5($dadosFunc['nome']);
            
            if (!isset($resultado2[$motivo][$funcionarioKey])) {
                $resultado2[$motivo][$funcionarioKey] = [
                    'funcionario' => $dadosFunc,
                    'quantidade' => 0
                ];
            }
            $resultado2[$motivo][$funcionarioKey]['quantidade']++;
        }
    }
}
$pastaAjuste->close();
// Classe PDF personalizada
    class CustomPDF extends TCPDF {
        public function Header() {
            $imgWidth = 30;
            $imgHeight = 15;
            $this->Image('logo_esquerda.png', 10, 10, $imgWidth, $imgHeight);
            $this->Image('logo_direita.png', $this->GetPageWidth() - $imgWidth - 10, 10, $imgWidth, $imgHeight);
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 15, 'Relatório Ajustes de Pontos Ativos', 0, 1, 'C');
            $this->Ln(15);
        }

        public function Footer() {
            $this->SetY(-15);
            $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
            $this->SetFont('helvetica', 'B', 9);
            $this->Cell(90, 0, 'TECHP®', 0, 0, 'L');
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(1, 0, 'Gerado em: ' . date('d/m/Y H:i'), 0, 0, 'C');
            parent::Footer();
        }
    }

    // Cria o PDF
    $pdf = new CustomPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('TechPS');
    $pdf->SetAuthor('TechPS');
    $pdf->SetTitle('Relatório Ajustes de Pontos Ativos');
    $pdf->SetMargins(10, 25, 10);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
    $pdf->AddPage();

    // Cabeçalho do relatório
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Write(7, 'Período: ');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Write(7, $periodoInicio->format("d/m/Y") . ' a ' . $periodoFim->format("d/m/Y") . "\n");

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Write(7, 'Empresa: ');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Write(7, $empresa['empr_tx_nome'] . "\n");

    $pdf->Ln(5);

    // Configuração das colunas
    $larguras = [20, 20, 100, 30];

    // Processa os dados conforme o filtro
    if ($motivoFiltro && isset($resultado2[$motivoFiltro])) {
        $funcionarios = $resultado2[$motivoFiltro];
        $totalQuantidade = array_sum(array_column(array_column($funcionarios, 'quantidade'), 'quantidade'));
        $totalFuncionarios = count($funcionarios);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(array_sum($larguras), 7, $motivoFiltro, 1, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($larguras[0] + $larguras[1], 7, 'TOTAL', 1, 0, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell($larguras[2], 7, $totalFuncionarios . ' funcionários', 1, 0, 'C');
        $pdf->Cell($larguras[3], 7, $totalQuantidade . ' ocorrências', 1, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($larguras[0], 7, 'Matrícula', 1, 0, 'C');
        $pdf->Cell($larguras[1], 7, 'Ocupação', 1, 0, 'C');
        $pdf->Cell($larguras[2], 7, 'Nome', 1, 0, 'C');
        $pdf->Cell($larguras[3], 7, 'Quantidade', 1, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        foreach ($funcionarios as $dados) {
            $pdf->Cell($larguras[0], 7, $dados['funcionario']['matricula'], 1);
            $pdf->Cell($larguras[1], 7, $dados['funcionario']['ocupacao'], 1);
            $pdf->Cell($larguras[2], 7, $dados['funcionario']['nome'], 1);
            $pdf->Cell($larguras[3], 7, $dados['quantidade'], 1, 1, 'C');
        }
    } else {
        foreach ($resultado2 as $motivo => $funcionarios) {
            if (!empty($funcionarios)) {
                $totalQuantidade = array_sum(array_column(array_column($funcionarios, 'quantidade'), 'quantidade'));
                $totalFuncionarios = count($funcionarios);

                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(array_sum($larguras), 7, $motivo, 1, 1, 'C');

                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell($larguras[0] + $larguras[1], 7, 'TOTAL', 1, 0, 'C');
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell($larguras[2], 7, $totalFuncionarios . ' funcionários', 1, 0, 'C');
                $pdf->Cell($larguras[3], 7, $totalQuantidade . ' ocorrências', 1, 1, 'C');

                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell($larguras[0], 7, 'Matrícula', 1, 0, 'C');
                $pdf->Cell($larguras[1], 7, 'Ocupação', 1, 0, 'C');
                $pdf->Cell($larguras[2], 7, 'Nome', 1, 0, 'C');
                $pdf->Cell($larguras[3], 7, 'Quantidade', 1, 1, 'C');

                $pdf->SetFont('helvetica', '', 10);
                foreach ($funcionarios as $dados) {
                    $pdf->Cell($larguras[0], 7, $dados['funcionario']['matricula'], 1);
                    $pdf->Cell($larguras[1], 7, $dados['funcionario']['ocupacao'], 1);
                    $pdf->Cell($larguras[2], 7, $dados['funcionario']['nome'], 1);
                    $pdf->Cell($larguras[3], 7, $dados['quantidade'], 1, 1, 'C');
                }

                end($resultado2);
                if ($motivo !== key($resultado2)) {
                    $pdf->AddPage();
                }
            }
            $pdf->Ln(5);
        }
    }

    // Gera o PDF
    $nomeArquivo = $motivoFiltro
        ? 'ajustes_de_pontos_' . strtolower(str_replace(' ', '_', $motivoFiltro)) . '.pdf'
        : 'ajustes_de_pontos_ativos.pdf';
    $pdf->Output($nomeArquivo, 'I');