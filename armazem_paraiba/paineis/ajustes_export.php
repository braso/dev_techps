<?php
require_once __DIR__ . "/../tcpdf/tcpdf.php";
require __DIR__ . "/../funcoes_ponto.php";

// Recebe os parâmetros do formulário
$periodo = trim($_POST['busca_periodo'], '[]"');
$datas = explode(',', $periodo);
$motivoFiltro = $_POST['motivo'] ?? null;

// Processa as datas
$data_inicio = trim($datas[0], '"');
$data_fim = trim($datas[1], '"');
$periodoInicio = new DateTime($data_inicio);
$periodoFim = new DateTime($data_fim);

// Configura caminhos e busca dados da empresa
$path = "./arquivos/ajustes";
$path .= "/" . $periodoInicio->format("Y-m") . "/" . $_POST["empresa"];

$empresa = mysqli_fetch_assoc(query(
    "SELECT * FROM empresa
    WHERE empr_tx_status = 'ativo'
        AND empr_nb_id = {$_POST["empresa"]}
    LIMIT 1;"
));

// Processa os arquivos de ajuste
$pastaAjuste = dir($path);

if (!isset($_POST['matricula']) && $_POST['matricula'] != null) {
    while ($arquivo = $pastaAjuste->read()) {
        if (!empty($arquivo) && !in_array($arquivo, [".", ".."]) && $arquivo === $_POST['matricula'] . ".json") {
            $arquivo = $path . "/" . $arquivo;
            $arquivos[] = $arquivo;
            $json = json_decode(file_get_contents($arquivo), true);
            // Processa cada chave do JSON
            foreach ($json as $chave => $valor) {

                // Verifica se é um tipo de ponto válido
                if (is_array($valor) && isset($valor['ativo']) && isset($valor['inativo'])) {
                    // Inicializa a chave no array de totais se não existir
                    if (!isset($totais[$chave])) {
                        $totais[$chave] = ['ativo' => 0, 'inativo' => 0];
                    }

                    // Soma os valores (com conversão para inteiro para segurança)
                    $totais[$chave]['ativo'] += (int)$valor['ativo'];
                    $totais[$chave]['inativo'] += (int)$valor['inativo'];
                }
            }
            foreach ($json['pontos'] as $key) {
                // Filtra apenas pontos com status "ativo" (case-insensitive)
                $status = strtolower(trim($key['pont_tx_status'] ?? ''));
                if ($status !== 'ativo') {
                    continue;
                }

                // Define o motivo
                $motivo = $motivoFiltro ?? 'MOTIVO_NAO_INFORMADO';
                $motivoJson = $key['moti_tx_nome'] ?? 'MOTIVO_NAO_INFORMADO';
                if ($motivoJson !== $motivoFiltro) {
                    continue;
                }

                // Contagem geral por motivo
                if (!isset($resultado[$motivo])) {
                    $resultado[$motivo] = 0;
                }
                $resultado[$motivo]++;

                // Agrupamento por motivo e funcionário
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
                        'quantidade' => 0,
                        'tipos' => [] // ← adiciona array para tipos
                    ];
                }

                // Incrementa quantidade
                $resultado2[$motivo][$funcionarioKey]['quantidade']++;

                // Armazena tipo do campo macr_tx_nome
                $tipo = $key['macr_tx_nome'] ?? 'TIPO_NAO_INFORMADO';

                if (!isset($resultado2[$motivo][$funcionarioKey]['tipos'][$tipo])) {
                    $resultado2[$motivo][$funcionarioKey]['tipos'][$tipo] = 0;
                }

                $resultado2[$motivo][$funcionarioKey]['tipos'][$tipo]++;
            }
            $empresas[] = $json;
            break;
        }
    }
} else {
    while ($arquivo = $pastaAjuste->read()) {
        if (!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
            $arquivo = $path . "/" . $arquivo;
            $arquivos[] = $arquivo;
            $json = json_decode(file_get_contents($arquivo), true);

            // Processa cada chave do JSON
            foreach ($json as $chave => $valor) {

                // Verifica se é um tipo de ponto válido
                if (is_array($valor) && isset($valor['ativo']) && isset($valor['inativo'])) {
                    // Inicializa a chave no array de totais se não existir
                    if (!isset($totais[$chave])) {
                        $totais[$chave] = ['ativo' => 0, 'inativo' => 0];
                    }

                    // Soma os valores (com conversão para inteiro para segurança)
                    $totais[$chave]['ativo'] += (int)$valor['ativo'];
                    $totais[$chave]['inativo'] += (int)$valor['inativo'];
                }
            }
            foreach ($json['pontos'] as $key) {
                // Filtra apenas pontos com status "ativo" (case-insensitive)
                if (strtolower($key['pont_tx_status'] ?? '') !== 'ativo') {
                    continue; // Pula se não for "ativo"
                }

                // Define o motivo
                $motivo = $key['moti_tx_nome'] ?? 'MOTIVO_NAO_INFORMADO';

                // Contagem geral por motivo
                if (!isset($resultado[$motivo])) {
                    $resultado[$motivo] = 0;
                }
                $resultado[$motivo]++;

                // Agrupamento por motivo e funcionário
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
                        'quantidade' => 0,
                        'tipos' => [] // ← adiciona array para tipos
                    ];
                }

                // Incrementa quantidade
                $resultado2[$motivo][$funcionarioKey]['quantidade']++;

                // Armazena tipo do campo macr_tx_nome
                $tipo = $key['macr_tx_nome'] ?? 'TIPO_NAO_INFORMADO';

                if (!isset($resultado2[$motivo][$funcionarioKey]['tipos'][$tipo])) {
                    $resultado2[$motivo][$funcionarioKey]['tipos'][$tipo] = 0;
                }
                $resultado2[$motivo][$funcionarioKey]['tipos'][$tipo]++;
            }
            $empresas[] = $json;
        }
    }
}

$pastaAjuste->close();

class CustomPDF extends TCPDF {
    protected static $empresaData;

    public static function setEmpresaData($data) {
        self::$empresaData = $data;
    }

    public function Header() {
        $imgWidth = 20;
        $imgHeight = 15;
        $imgHeight2 = 10;
        $this->Image(__DIR__ . "/../imagens/logo_topo_cliente.png", 10, 10, $imgWidth, $imgHeight2);
        $this->Image(__DIR__ . "/../" . self::$empresaData["empr_tx_logo"], $this->GetPageWidth() - $imgWidth - 10, 10, $imgWidth, $imgHeight);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 15, 'Relatório Ajustes de Pontos Inseridos', 0, 1, 'C');
        $this->Ln(15);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(90, 0, 'TECHPS®', 0, 0, 'L');
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(1, 0, 'Gerado em: ' . date('d/m/Y H:i'), 0, 0, 'C');
        parent::Footer();
    }
}

function gerarRelatorio($tipo = 'pdf') {
    global $resultado2, $motivoFiltro, $periodoInicio, $periodoFim, $empresa;

    if ($tipo === 'csv') {
        // Gera CSV com mesmo conteúdo do PDF
        $nomeArquivo = 'ajustes_pontos_ativos_' . date('d-m-Y_H-i-s') . '.csv'; // Corrigido para evitar ":" no nome

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');

        // Abre output com codificação UTF-8 (adiciona BOM para Excel)
        $output = fopen('php://output', 'w');
        fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // Adiciona BOM UTF-8

        // Cabeçalho do CSV
        fputcsv($output, ['Relatório Ajustes de Pontos Inseridos'], ';');
        fputcsv($output, ['Período: ' . $periodoInicio->format("d/m/Y") . ' a ' . $periodoFim->format("d/m/Y")], ';');
        fputcsv($output, ['Empresa: ' . $empresa['empr_tx_nome']], ';');
        fputcsv($output, [], ';'); // Linha vazia

        if ($motivoFiltro && isset($resultado2[$motivoFiltro])) {
            $funcionarios = $resultado2[$motivoFiltro];
            $totalQuantidade = array_sum(array_column($funcionarios, 'quantidade'));
            $totalFuncionarios = count($funcionarios);

            fputcsv($output, ['Motivo: ' . $motivoFiltro], ';');
            fputcsv($output, ['TOTAL', $totalFuncionarios . ' funcionários', $totalQuantidade . ' ocorrências'], ';');
            fputcsv($output, ['Matrícula', 'Ocupação', 'Nome', 'Tipos', 'Quantidade'], ';');

            foreach ($funcionarios as $dados) {
                $tiposTexto = '';
                if (!empty($dados['tipos'])) {
                    $tiposTexto = implode(' | ', array_map(
                        function ($tipo, $qtd) {
                            return "$tipo: $qtd";
                        },
                        array_keys($dados['tipos']),
                        $dados['tipos']
                    ));
                }

                fputcsv($output, [
                    $dados['funcionario']['matricula'],
                    $dados['funcionario']['ocupacao'],
                    $dados['funcionario']['nome'],
                    $tiposTexto,
                    $dados['quantidade']
                ], ';');
            }
        } else {
            foreach ($resultado2 as $motivo => $funcionarios) {
                if (!empty($funcionarios)) {
                    $totalQuantidade = array_sum(array_column($funcionarios, 'quantidade'));
                    $totalFuncionarios = count($funcionarios);

                    fputcsv($output, ['Motivo: ' . $motivo], ';');
                    fputcsv($output, ['TOTAL', $totalFuncionarios . ' funcionários', $totalQuantidade . ' ocorrências'], ';');
                    fputcsv($output, ['Matrícula', 'Ocupação', 'Nome', 'Tipos', 'Quantidade'], ';');

                    foreach ($funcionarios as $dados) {
                        $tiposTexto = '';
                        if (!empty($dados['tipos'])) {
                            $tiposTexto = implode(' | ', array_map(
                                function ($tipo, $qtd) {
                                    return "$tipo: $qtd";
                                },
                                array_keys($dados['tipos']),
                                $dados['tipos']
                            ));
                        }

                        fputcsv($output, [
                            $dados['funcionario']['matricula'],
                            $dados['funcionario']['ocupacao'],
                            $dados['funcionario']['nome'],
                            $tiposTexto,
                            $dados['quantidade']
                        ], ';');
                    }

                    fputcsv($output, [], ';'); // Linha vazia entre motivos
                }
            }
        }

        fclose($output);
        exit;
    } else {
        // Gera PDF
        $pdf = new CustomPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('TechPS');
        $pdf->SetAuthor('TechPS');
        $pdf->SetTitle('Relatório Ajustes de Pontos Inseridos');
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
        $larguras = [20, 25, 100, 70, 70];

        // Processa os dados conforme o filtro
        if ($motivoFiltro && isset($resultado2[$motivoFiltro])) {
            $funcionarios = $resultado2[$motivoFiltro];
            $totalQuantidade = array_sum(array_column($funcionarios, 'quantidade'));
            $totalFuncionarios = count($funcionarios);

            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(array_sum($larguras), 7, $motivoFiltro, 1, 1, 'C');

            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell($larguras[0] + $larguras[1], 7, 'TOTAL', 1, 0, 'C');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell($larguras[2], 7, $totalFuncionarios . ' funcionários', 1, 0, 'C');
            $pdf->Cell($larguras[3], 7, '', 1, 0, 'C');
            $pdf->Cell($larguras[3], 7, $totalQuantidade . ' ocorrências', 1, 1, 'C');

            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell($larguras[0], 7, 'Matrícula', 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, 'Ocupação', 1, 0, 'C');
            $pdf->Cell($larguras[2], 7, 'Nome', 1, 0, 'C');
            $pdf->Cell($larguras[4], 7, 'Tipos', 1, 0, 'C');
            $pdf->Cell($larguras[3], 7, 'Quantidade', 1, 1, 'C');

            $pdf->SetFont('helvetica', '', 10);
            foreach ($funcionarios as $dados) {
                $tiposTexto = '';
                if (!empty($dados['tipos'])) {
                    $tiposTexto = implode(' | ', array_map(
                        function ($tipo, $qtd) {
                            return "$tipo: $qtd";
                        },
                        array_keys($dados['tipos']),
                        $dados['tipos']
                    ));
                }

                // Altura baseada no conteúdo de "Tipos"
                $alturaLinha = max(7, $pdf->GetStringHeight($larguras[4], $tiposTexto));

                // Verifica se há espaço suficiente na página
                $espacoRestante = $pdf->GetPageHeight() - $pdf->GetY() - $pdf->getFooterMargin();
                if ($alturaLinha > $espacoRestante) {
                    $pdf->AddPage();
                }

                $pdf->Cell($larguras[0], $alturaLinha, $dados['funcionario']['matricula'], 1, 0);
                $pdf->Cell($larguras[1], $alturaLinha, $dados['funcionario']['ocupacao'], 1, 0);
                $pdf->Cell($larguras[2], $alturaLinha, $dados['funcionario']['nome'], 1, 0);

                // $pdf->setCellPaddings(0, 1, 0, 1);
                $y = $pdf->GetY();
                $xTipos = $pdf->GetX();

                $pdf->MultiCell($larguras[4], $alturaLinha, $tiposTexto, 1, 'L', false, 0, $xTipos, $y);

                $pdf->SetXY($xTipos + $larguras[4], $y);
                $pdf->setCellPaddings(0, 0, 0, 0);
                $pdf->Cell($larguras[3], $alturaLinha, $dados['quantidade'], 1, 1, 'C');
            }
        } else {
            foreach ($resultado2 as $motivo => $funcionarios) {
                if (!empty($funcionarios)) {
                    $totalQuantidade = array_sum(array_column($funcionarios, 'quantidade'));
                    $totalFuncionarios = count($funcionarios);

                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->Cell(array_sum($larguras), 7, $motivo, 1, 1, 'C');

                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->Cell($larguras[0] + $larguras[1], 7, 'TOTAL', 1, 0, 'C');
                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->Cell($larguras[2], 7, $totalFuncionarios . ' funcionários', 1, 0, 'C');
                    $pdf->Cell($larguras[3], 7, ' ', 1, 0, 'C');
                    $pdf->Cell($larguras[3], 7, $totalQuantidade . ' ocorrências', 1, 1, 'C');

                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->Cell($larguras[0], 7, 'Matrícula', 1, 0, 'C');
                    $pdf->Cell($larguras[1], 7, 'Ocupação', 1, 0, 'C');
                    $pdf->Cell($larguras[2], 7, 'Nome', 1, 0, 'C');
                    $pdf->Cell($larguras[4], 7, 'Tipos', 1, 0, 'C');
                    $pdf->Cell($larguras[3], 7, 'Quantidade', 1, 1, 'C');

                    $pdf->SetFont('helvetica', '', 10);
                    foreach ($funcionarios as $dados) {
                        $tiposTexto = '';
                        if (!empty($dados['tipos'])) {
                            $tiposTexto = implode(' | ', array_map(
                                function ($tipo, $qtd) {
                                    return "$tipo: $qtd";
                                },
                                array_keys($dados['tipos']),
                                $dados['tipos']
                            ));
                        }

                        // Altura baseada no conteúdo de "Tipos"
                        $alturaLinha = max(7, $pdf->GetStringHeight($larguras[4], $tiposTexto));

                        // Verifica se há espaço suficiente na página
                        $espacoRestante = $pdf->GetPageHeight() - $pdf->GetY() - $pdf->getFooterMargin();
                        if ($alturaLinha > $espacoRestante) {
                            $pdf->AddPage();
                        }

                        $pdf->Cell($larguras[0], $alturaLinha, $dados['funcionario']['matricula'], 1, 0);
                        $pdf->Cell($larguras[1], $alturaLinha, $dados['funcionario']['ocupacao'], 1, 0);
                        $pdf->Cell($larguras[2], $alturaLinha, $dados['funcionario']['nome'], 1, 0);

                        // $pdf->setCellPaddings(0, 1, 0, 1);
                        $y = $pdf->GetY();
                        $xTipos = $pdf->GetX();

                        $pdf->MultiCell($larguras[4], $alturaLinha, $tiposTexto, 1, 'L', false, 0, $xTipos, $y);

                        $pdf->SetXY($xTipos + $larguras[4], $y);
                        $pdf->setCellPaddings(0, 0, 0, 0);
                        $pdf->Cell($larguras[3], $alturaLinha, $dados['quantidade'], 1, 1, 'C');
                    }

                    end($resultado2);
                    if ($motivo !== key($resultado2)) {
                        $pdf->AddPage();
                    }

                    $pdf->Ln(5);
                }
            }
        }


        $nomeArquivo = $motivoFiltro
            ? 'ajustes_de_pontos_' . strtolower(str_replace(' ', '_', $motivoFiltro)) . '.pdf'
            : 'ajustes_de_pontos_ativos.pdf';
        $pdf->Output($nomeArquivo, 'I');
    }
}

$tipo = $_POST['export'] ?? 'pdf';
CustomPDF::setEmpresaData($empresa);
// Verifica se é requisição para CSV ou PDF
if ($tipo === 'csv') {
    gerarRelatorio('csv');
} else {
    gerarRelatorio();
}
