<?php
require_once __DIR__ . "/../tcpdf/tcpdf.php";
require __DIR__ . "/../funcoes_ponto.php";
require_once __DIR__ . "/funcoes_paineis.php";

/* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
    //*/

if (!empty($_POST["empresa"])) {
    $empresa = mysqli_fetch_assoc(query(
        "SELECT * FROM empresa
            WHERE empr_tx_status = 'ativo'
                AND empr_nb_id = {$_POST["empresa"]}
            LIMIT 1;"
    ));
} else {
    $empresa = mysqli_fetch_assoc(query(
        "SELECT * FROM empresa
            WHERE empr_tx_status = 'ativo'
                AND empr_tx_Ehmatriz = 'sim'
            LIMIT 1;"
    ));
}

class CustomPDF extends TCPDF {
    public $tituloPersonalizado = 'Relatório Sem titulo';
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
        // $this->Image('logo_esquerda.png', 10, 10, $imgWidth, $imgHeight);
        // $this->Image('logo_direita.png', $this->GetPageWidth() - $imgWidth - 10, 10, $imgWidth, $imgHeight);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 15, $this->tituloPersonalizado, 0, 1, 'C');
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

function gerarPainelEndosso() {
    $arquivos = [];
    $dataEmissao = ""; //Utilizado no HTML
    $encontrado = true;
    $path = "./arquivos/endossos";
    $periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

    $contagemSaldos = [
        "positivos" => 0,
        "meta" => 0,
        "negativos" => 0
    ];
    $contagemEndossos = [
        "E" => 0,
        "EP" => 0,
        "N" => 0
    ];
    $totais = [
        "jornadaPrevista" => "00:00",
        "jornadaEfetiva" => "00:00",
        "he50APagar" => "00:00",
        "he100APagar" => "00:00",
        "adicionalNoturno" => "00:00",
        "esperaIndenizada" => "00:00",
        "saldoAnterior" => "00:00",
        "saldoPeriodo" => "00:00",
        "saldoFinal" => "00:00"
    ];

    $periodoRelatorio = [
        "dataInicio" => "1900-01-01",
        "dataFim" => "1900-01-01"
    ];

    if (!empty($_POST["empresa"]) && !empty($_POST["busca_data"])) {
        //Painel dos endossos dos motoristas de uma empresa específica
        $empresa = mysqli_fetch_assoc(query(
            "SELECT * FROM empresa
                WHERE empr_tx_status = 'ativo'
                    AND empr_nb_id = {$_POST["empresa"]}
                LIMIT 1;"
        ));
        $path .= "/" . $_POST["busca_data"] . "/" . $empresa["empr_nb_id"];

        if (is_dir($path)) {
            $pastaSaldosEmpresa = dir($path);
            $motoristas = mysqli_fetch_all(query(
                "SELECT enti_tx_matricula, enti_tx_desligamento, enti_tx_admissao FROM entidade
                        WHERE enti_tx_status != 'ativo'
                            AND enti_nb_empresa = {$empresa["empr_nb_id"]}
                            AND enti_tx_ocupacao IN ('Motorista', 'Ajudante', 'Funcionário')
                        ORDER BY enti_tx_nome ASC;"
            ), MYSQLI_ASSOC);

            $dataBusca = new DateTime($_POST["busca_data"]);
            foreach ($motoristas as $motorista) {
                if (!empty($motorista["enti_tx_desligamento"])) {
                    $dataMotorista = new DateTime($motorista["enti_tx_desligamento"]);
                    $dataMotorista = $dataMotorista->format("Y-m");
                    if ($dataBusca > $dataMotorista) {
                        $matriculasInativas = array_map(function ($matricula) {
                            return $matricula . ".json";
                        }, array_column($motoristas, "enti_tx_matricula"));
                    }
                } else {
                    $dataMotorista = new DateTime($motorista["enti_tx_admissao"]);
                    $dataMotorista = $dataMotorista->format("Y-m");
                    if ($dataBusca < $dataMotorista) {
                        $matriculasInativas = array_map(function ($matricula) {
                            return $matricula . ".json";
                        }, array_column($motoristas, "enti_tx_matricula"));
                    }
                }
            }

            while ($arquivo = $pastaSaldosEmpresa->read()) {
                if (!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
                    $arquivos[] = $arquivo;

                    if (!empty($matriculasInativas) && in_array($arquivo, $matriculasInativas)) {
                        $arquivos = array_diff($arquivos, [$arquivo]);
                        // unlink($path."/". $arquivo);
                    }
                }
            }

            $pastaSaldosEmpresa->close();

            $dataArquivo = date("d/m/Y", filemtime($path . "/empresa_" . $empresa["empr_nb_id"] . ".json"));
            $horaArquivo = date("H:i", filemtime($path . "/empresa_" . $empresa["empr_nb_id"] . ".json"));

            $dataAtual = date("d/m/Y");
            $horaAtual = date("H:i");
            if ($dataArquivo != $dataAtual) {
                $alertaEmissao = "<span style='color: red; border: 2px solid; padding: 2px; border-radius: 4px;'>
                    <i style='color:red; margin-right: 5px;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
            } else {
                $alertaEmissao = "<span>";
            }

            $dataEmissao = $alertaEmissao . " Atualizado em: " . date("d/m/Y H:i", filemtime($path . "/empresa_" . $empresa["empr_nb_id"] . ".json")) . "</span>"; //Utilizado no HTML.
            $periodoRelatorio = json_decode(file_get_contents($path . "/empresa_" . $empresa["empr_nb_id"] . ".json"), true);
            $periodoRelatorio = [
                "dataInicio" => $periodoRelatorio["dataInicio"],
                "dataFim" => $periodoRelatorio["dataFim"]
            ];

            $motoristas = [];
            foreach ($arquivos as $arquivo) {
                $json = json_decode(file_get_contents($path . "/" . $arquivo), true);
                $json["dataAtualizacao"] = date("d/m/Y H:i", filemtime($path . "/" . $arquivo));
                foreach ($totais as $key => $value) {
                    $totais[$key] = operarHorarios([
                        !empty($totais[$key]) ? $totais[$key] : "00:00",
                        !empty($json[$key]) ? $json[$key] : "00:00"
                    ], "+");
                }
                $motoristas[] = $json;
            }
            foreach ($arquivos as &$arquivo) {
                $arquivo = $path . "/" . $arquivo;
            }
            $totais["empresaNome"] = $empresa["empr_tx_nome"];

            foreach ($motoristas as $saldosMotorista) {
                $contagemEndossos[$saldosMotorista["statusEndosso"]]++;
                if ($saldosMotorista["statusEndosso"] == "E") {
                    if ($saldosMotorista["saldoFinal"] === "00:00") {
                        $contagemSaldos["meta"]++;
                    } elseif ($saldosMotorista["saldoFinal"][0] == "-") {
                        $contagemSaldos["negativos"]++;
                    } else {
                        $contagemSaldos["positivos"]++;
                    }
                }
            }
        }
    } elseif (!empty($_POST["busca_data"])) {
        //Painel geral das empresas
        $empresas = [];

        $path .= "/" . $_POST["busca_data"];

        $dataArquivo = date("d/m/Y H:i", filemtime($path . "/empresas.json"));
        $horaArquivo = date("H:i", filemtime($path . "/empresas.json"));

        $dataAtual = date("d/m/Y");
        $horaAtual = date("H:i");
        if ($dataArquivo != $dataAtual) {
            $alertaEmissao = "<span style='color: red; border: 2px solid; padding: 2px; border-radius: 4px;'>
                    <i style='color:red;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
        } else {
            $alertaEmissao = "<span>";
        }
        $dataEmissao = $alertaEmissao . " Atualizado em: " . date("d/m/Y H:i", filemtime($path . "/empresas.json")) . "</span>"; //Utilizado no HTML.
        $arquivoGeral = json_decode(file_get_contents($path . "/empresas.json"), true);

        $periodoRelatorio = [
            "dataInicio" => $arquivoGeral["dataInicio"],
            "dataFim" => $arquivoGeral["dataFim"]
        ];

        $pastaEndossos = dir($path);
        while ($arquivo = $pastaEndossos->read()) {
            if (!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresas"))) {
                $arquivo = $path . "/" . $arquivo . "/empresa_" . $arquivo . ".json";
                $arquivos[] = $arquivo;
                $json = json_decode(file_get_contents($arquivo), true);
                foreach ($totais as $key => $value) {
                    $totais[$key] = operarHorarios([
                        !empty($totais[$key]) ? $totais[$key] : "00:00",
                        !empty($json["totais"][$key]) ? $json["totais"][$key] : "00:00"
                    ], "+");
                }
                $empresas[] = $json;
            }
        }
        $pastaEndossos->close();

        foreach ($empresas as $empresa) {
            if ($empresa["percEndossado"] < 1) {
                $empresa["totais"] = [
                    "saldoAnterior" => $empresa["totais"]["saldoAnterior"]
                ];
                if ($empresa["percEndossado"] <= 0) {
                    $contagemEndossos["N"]++;
                } else {
                    $contagemEndossos["EP"]++;
                }
            } else {
                $contagemEndossos["E"]++;

                if ($empresa["totais"]["saldoFinal"] === "00:00") {
                    $contagemSaldos["meta"]++;
                } elseif ($empresa["totais"]["saldoFinal"][0] == "-") {
                    $contagemSaldos["negativos"]++;
                } else {
                    $contagemSaldos["positivos"]++;
                }
            }
        }
    }

    [$percEndosso["E"], $percEndosso["EP"], $percEndosso["N"]] = calcPercs(array_values($contagemEndossos));
    [$performance["positivos"], $performance["meta"], $performance["negativos"]] = calcPercs(array_values($contagemSaldos));

    $pdf = new CustomPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->tituloPersonalizado = 'Relatório de Endossos';
    $pdf->SetCreator('TechPS');
    $pdf->SetAuthor('TechPS');
    $pdf->SetTitle('Relatório de Endossos');
    $pdf->SetMargins(2, 25, 2);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
    $pdf->AddPage();

    // Larguras
    $colWidth = 20;
    $espacoEntre = 3;
    $totalTabela = ($colWidth * 7) + $espacoEntre + ($colWidth * 3);

    // Coordenadas iniciais da tabela
    $posX_inicial = $pdf->GetX();
    $posY_inicial = $pdf->GetY();

    // Cabeçalhos
    $pdf->SetFont('', 'B', 6);
    $pdf->Cell($colWidth, 8, 'STATUS', 1, 0, 'C');
    $pdf->Cell($colWidth, 8, 'TOTAL', 1, 0, 'C');
    $pdf->Cell($colWidth, 8, '%', 1, 0, 'C');
    $pdf->Cell($espacoEntre);
    $pdf->Cell($colWidth, 8, 'SALDO FINAL', 1, 0, 'C');
    $pdf->Cell($colWidth, 8, 'TOTAL', 1, 0, 'C');
    $pdf->Cell($colWidth, 8, '%', 1, 1, 'C');

    // Conteúdo
    $pdf->SetFont('', '');

    // Linha 1
    $pdf->SetFillColor(236, 65, 65);  // vermelho
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Não Endossado', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemEndossos['N'], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($percEndosso["N"] * 10000) / 100, 2) . '%', 1, 0, 'C');
    $pdf->Cell($espacoEntre);

    $pdf->SetFillColor(78, 169, 255);  // azul
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Meta', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemSaldos["meta"], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($performance["meta"] * 10000) / 100, 2) . '%', 1, 1, 'C');

    // Linha 2
    $pdf->SetFillColor(241, 198, 31);  // amarelo
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Endo. Parcialmente', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemEndossos['EP'], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($percEndosso["EP"] * 10000) / 100, 2) . '%', 1, 0, 'C');
    $pdf->Cell($espacoEntre);

    $pdf->SetFillColor(83, 208, 42);  // verde
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Positivo', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemSaldos["positivos"], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($performance["positivos"] * 10000) / 100, 2) . '%', 1, 1, 'C');

    // Linha 3
    $pdf->SetFillColor(78, 169, 255);  // azul
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Endossado', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemEndossos['E'], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($percEndosso["E"] * 10000) / 100, 2) . '%', 1, 0, 'C');
    $pdf->Cell($espacoEntre);

    $pdf->SetFillColor(236, 65, 65);  // vermelho
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Negativo', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemSaldos["negativos"], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($performance["negativos"] * 10000) / 100, 2) . '%', 1, 1, 'C');

    // Adiciona espaço após a última linha da tabela
    $pdf->Ln(10);

    // Define posição lateral ao topo da tabela
    $pdf->SetXY($posX_inicial + $totalTabela + 10, $posY_inicial);

    // Texto "Atualizado em"
    $pdf->SetFont('', '', 9);
    $pdf->Cell(60, 6, 'Atualizado em: ' . date("d/m/Y H:i", filemtime($path . "/empresa_" . $empresa["empr_nb_id"] . ".json")), 0, 2, 'L');

    // Texto "Período"
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(60, 6, 'Período do relatório: ' . $periodoRelatorio["dataInicio"] . " a " . $periodoRelatorio["dataFim"], 0, 1, 'L');

    // Tabela FRUTICANA + 9 colunas
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetY(60);
    $pdf->SetX(2);

    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $larguraTotal = 218;
    $altura = 7;
    $larguraCell = round($larguraTotal / 12, 2);

    $larguras = [
        15, // célula 1
        21, // célula 2
        45, // célula 1
    ];

    $pdf->SetFillColor(241, 198, 31);  // amarelo

    $pdf->MultiCell($larguraCell * 3.3, $altura, $totais["empresaNome"], 1, 'C', true);
    $pdf->SetXY($x + ($larguraCell * 3.3), $y);

    $pdf->Cell($larguras[1], $altura, '', 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, '', 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["jornadaPrevista"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["jornadaEfetiva"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["he50APagar"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["he100APagar"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["adicionalNoturno"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["esperaIndenizada"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["saldoAnterior"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["saldoPeriodo"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["saldoFinal"], 1, 0, 'C', true);

    $pdf->Ln(7);

    $dadosOrdenados = [];

    if (!empty($_POST["empresa"]) && !empty($_POST["busca_data"])) {
        $pdf->SetFont('helvetica', 'B', 6);

        $pdf->SetFillColor(78, 169, 255);  // azul

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[0], 7, 'MATRICULA', 1, 0, 'C', true);
        $x += $larguras[0];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[2], 7, 'NOME', 1, 0, 'C', true);
        $x += $larguras[2];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Ocupação', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Status Endosso', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Jornada Prevista', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Jornada Efetiva', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'H.E. Semanal Pago', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'H.E. Ex. Pago', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Adicional Noturno', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Espera Indenizada', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Saldo Anterior', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Saldo Período', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Saldo Final', 1, 0, 'C', true);

        $pdf->Ln(7);

        $pdf->SetFont('helvetica', 'B', 6);

        foreach ($arquivos as $caminho) {
            if (file_exists($caminho)) {
                $conteudo = file_get_contents($caminho);
                $dados = json_decode($conteudo, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $dadosOrdenados[] = $dados;
                }
            }
        }

        // Ordenar pelo campo 'nome' (ordem alfabética)
        usort($dadosOrdenados, function ($a, $b) {
            return strcmp(mb_strtoupper($a['nome']), mb_strtoupper($b['nome']));
        });

        // Agora imprime os dados no PDF
        foreach ($dadosOrdenados as $dados) {
            if ($dados['statusEndosso'] == 'E') {
                $pdf->SetFillColor(78, 169, 255);  // azul
            } else if ($dados['statusEndosso'] == 'EP') {
                $pdf->SetFillColor(241, 198, 31);  // amarelo
            } else {
                $pdf->SetFillColor(236, 65, 65);   // vermelho
            }

            $pdf->Cell($larguras[0], 7, $dados['matricula'], 1, 0, 'C');
            $pdf->Cell($larguras[2], 7, $dados['nome'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['ocupacao'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['statusEndosso'], 1, 0, 'C', true);
            $pdf->Cell($larguras[1], 7, $dados['jornadaPrevista'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['jornadaEfetiva'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['he50APagar'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['he100APagar'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['adicionalNoturno'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['esperaIndenizada'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['saldoAnterior'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['saldoPeriodo'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['saldoFinal'], 1, 0, 'C');
            $pdf->Ln();
        }
    } elseif (!empty($_POST["busca_data"])) {

        $larguras = [
            20.9, // célula 1
            21, // célula 2
            60, // célula 1
        ];

        $pdf->SetFont('helvetica', 'B', 6);

        // Salva a posição inicial
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->SetFillColor(78, 169, 255);  // azul

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[2], 7, 'Nome da Empresa/Filial', 1, 0, 'C', true);
        $x += $larguras[2];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[0], 7, '% Endossados', 1, 0, 'C', true);
        $x += $larguras[0];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Qtd. Motoristas', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Jornada Prevista', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Jornada Efetiva', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'H.E. Semanal Pago', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'H.E. Ex. Pago', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Adicional Noturno', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Espera Indenizada', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Saldo Anterior', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Saldo Período', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Saldo Final', 1, 0, 'C', true);

        $pdf->Ln(7);

        $pdf->SetFont('helvetica', 'B', 6);
        foreach ($empresas as $empresa) {
            $linhaAltura = 7;
            $larguraNome = $larguras[2];

            // Salva a posição atual
            $x = $pdf->GetX();
            $y = $pdf->GetY();

            // MultiCell para o nome da empresa
            $pdf->MultiCell($larguraNome, $linhaAltura, $empresa['empr_tx_nome'], 1, 'C', false, 0);

            // Ajusta o Y da linha mais alta, caso MultiCell aumente
            $pdf->SetXY($x + $larguraNome, $y);

            // Células restantes
            $pdf->Cell($larguras[0], $linhaAltura, number_format(($empresa['percEndossado'] * 10000) / 100, 2) . '%', 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['qtdMotoristas'], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["jornadaPrevista"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["jornadaEfetiva"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["he50APagar"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["he100APagar"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["adicionalNoturno"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["esperaIndenizada"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["saldoAnterior"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["saldoPeriodo"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["saldoFinal"], 1, 0, 'C');
            $pdf->Ln();
        }
    }

    // Gera o PDF
    $nomeArquivo = 'relatorio_endossos.pdf';
    $pdf->Output($nomeArquivo, 'I');
}

function gerarPainelSaldo() {

    $arquivos = [];
    $path = "./arquivos/saldos";
    $periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

    $contagemSaldos = [
        "positivos" => 0,
        "meta" => 0,
        "negativos" => 0
    ];
    $contagemEndossos = [
        "E" => 0,
        "EP" => 0,
        "N" => 0
    ];
    $totais = [
        "jornadaPrevista"     => "00:00",
        "jornadaEfetiva"     => "00:00",
        "HESemanal"         => "00:00",
        "HESabado"             => "00:00",
        "adicionalNoturno"     => "00:00",
        "esperaIndenizada"     => "00:00",
        "saldoAnterior"     => "00:00",
        "saldoPeriodo"         => "00:00",
        "saldoFinal"         => "00:00"
    ];

    $periodoRelatorio = [
        "dataInicio" => "1900-01-01",
        "dataFim" => "1900-01-01"
    ];

    $path .= "/" . $_POST["busca_data"];
    if (!empty($_POST["empresa"])) {
        //Painel dos saldos dos motoristas de uma empresa específica
        $aEmpresa = mysqli_fetch_assoc(query(
            "SELECT * FROM empresa
                    WHERE empr_tx_status = 'ativo'
                        AND empr_nb_id = {$_POST["empresa"]}
                    LIMIT 1;"
        ));
        $path .= "/" . $aEmpresa["empr_nb_id"];
        if (is_dir($path)) {
            $pastaSaldosEmpresa = dir($path);
            while ($arquivo = $pastaSaldosEmpresa->read()) {
                if (!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
                    $arquivos[] = $arquivo;
                }
            }
            $pastaSaldosEmpresa->close();

            $dataArquivo = date("d/m/Y H:i", filemtime($path . "/" . "empresa_" . $aEmpresa["empr_nb_id"] . ".json"));
            $horaArquivo = date("H:i", filemtime($path . "/" . "empresa_" . $aEmpresa["empr_nb_id"] . ".json"));

            $dataAtual = date("d/m/Y");
            $horaAtual = date("H:i");

            $dataEmissao = " Atualizado em: " . date("d/m/Y H:i", filemtime($path . "/" . "empresa_" . $aEmpresa["empr_nb_id"] . ".json")) . "</span>"; //Utilizado no HTML.
            $periodoRelatorio = json_decode(file_get_contents($path . "/" . "empresa_" . $aEmpresa["empr_nb_id"] . ".json"), true);
            $periodoRelatorio = [
                "dataInicio" => $periodoRelatorio["dataInicio"],
                "dataFim" => $periodoRelatorio["dataFim"]
            ];
            $periodoRelatorio["dataInicio"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataInicio"])->format("d/m");
            $periodoRelatorio["dataFim"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataFim"])->format("d/m");

            $motoristas = [];
            foreach ($arquivos as $arquivo) {
                $json = json_decode(file_get_contents($path . "/" . $arquivo), true);
                $json["dataAtualizacao"] = date("d/m/Y H:i", filemtime($path . "/" . $arquivo));
                foreach ($totais as $key => $value) {
                    $totais[$key] = operarHorarios([$totais[$key], $json[$key]], "+");
                }
                $motoristas[] = $json;
            }
            foreach ($arquivos as &$arquivo) {
                $arquivo = $path . "/" . $arquivo;
            }
            $totais["empresaNome"] = $aEmpresa["empr_tx_nome"];

            foreach ($motoristas as $saldosMotorista) {
                $contagemEndossos[$saldosMotorista["statusEndosso"]]++;
                if ($saldosMotorista["saldoFinal"] === "00:00") {
                    $contagemSaldos["meta"]++;
                } elseif ($saldosMotorista["saldoFinal"][0] == "-") {
                    $contagemSaldos["negativos"]++;
                } else {
                    $contagemSaldos["positivos"]++;
                }
            }
        }
    } else {
        //Painel geral das empresas
        $empresas = [];
        $logoEmpresa = mysqli_fetch_assoc(query(
            "SELECT empr_tx_logo FROM empresa
                    WHERE empr_tx_status = 'ativo'
                        AND empr_tx_Ehmatriz = 'sim'
                    LIMIT 1;"
        ))["empr_tx_logo"]; //Utilizado no HTML.

        $logoEmpresa = $_ENV["APP_PATH"] . $_ENV["CONTEX_PATH"] . "/" . $logoEmpresa;


        if (is_dir($path) && is_file($path . "/empresas.json")) {
            $encontrado = true;
            $arquivoGeral = $path . "/empresas.json";

            $dataArquivo = date("d/m/Y", filemtime($arquivoGeral));
            $horaArquivo = date("H:i", filemtime($arquivoGeral));

            $dataAtual = date("d/m/Y");
            $horaAtual = date("H:i");
            if ($dataArquivo != $dataAtual) {
                $alertaEmissao = "<i style='color:red;' title='As informações do painel não correspondem à data de hoje.' class='fa fa-warning'></i>";
            } else {
                // Datas iguais: compara as horas
                // if ($horaArquivo < $horaAtual) {
                //     $alertaEmissao = "<i style='color:red;' title='As informações do painel podem estar desatualizadas.' class='fa fa-warning'></i>";
                // } else {
                $alertaEmissao = "";
                // }
            }

            $dataEmissao = $alertaEmissao . " Atualizado em: " . date("d/m/Y H:i", filemtime($arquivoGeral)); //Utilizado no HTML.
            $arquivoGeral = json_decode(file_get_contents($arquivoGeral), true);

            $periodoRelatorio = [
                "dataInicio" => $arquivoGeral["dataInicio"],
                "dataFim" => $arquivoGeral["dataFim"]
            ];

            $periodoRelatorio["dataInicio"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataInicio"])->format("d/m");
            $periodoRelatorio["dataFim"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataFim"])->format("d/m");


            $pastaSaldos = dir($path);
            while ($arquivo = $pastaSaldos->read()) {
                if (!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresas"))) {
                    $arquivo = $path . "/" . $arquivo . "/empresa_" . $arquivo . ".json";
                    $arquivos[] = $arquivo;
                    $json = json_decode(file_get_contents($arquivo), true);
                    foreach ($totais as $key => $value) {
                        $totais[$key] = operarHorarios([$totais[$key], $json["totais"][$key]], "+");
                    }
                    $empresas[] = $json;
                }
            }
            $pastaSaldos->close();

            foreach ($empresas as $empresa) {
                if ($empresa["totais"]["saldoFinal"] === "00:00") {
                    $contagemSaldos["meta"]++;
                } elseif ($empresa["totais"]["saldoFinal"][0] == "-") {
                    $contagemSaldos["negativos"]++;
                } else {
                    $contagemSaldos["positivos"]++;
                }

                if ($empresa["percEndossado"] === 1) {
                    $contagemEndossos["E"]++;
                } elseif ($empresa["percEndossado"] === 0) {
                    $contagemEndossos["N"]++;
                } else {
                    $contagemEndossos["EP"]++;
                }
            }
        }
    }

    [$percEndosso["E"], $percEndosso["EP"], $percEndosso["N"]] = calcPercs(array_values($contagemEndossos));
    [$performance["positivos"], $performance["meta"], $performance["negativos"]] = calcPercs(array_values($contagemSaldos));

    $pdf = new CustomPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->tituloPersonalizado = 'Relatório de Saldo';
    $pdf->SetCreator('TechPS');
    $pdf->SetAuthor('TechPS');
    $pdf->SetTitle('Relatório de Saldo');
    $pdf->SetMargins(2, 25, 2);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
    $pdf->AddPage();

    // Larguras
    $colWidth = 20;
    $espacoEntre = 3;
    $totalTabela = ($colWidth * 7) + $espacoEntre + ($colWidth * 3);

    // Coordenadas iniciais da tabela
    $posX_inicial = $pdf->GetX();
    $posY_inicial = $pdf->GetY();

    // Cabeçalhos
    $pdf->SetFont('', 'B', 6);
    $pdf->Cell($colWidth, 8, 'STATUS', 1, 0, 'C');
    $pdf->Cell($colWidth, 8, 'TOTAL', 1, 0, 'C');
    $pdf->Cell($colWidth, 8, '%', 1, 0, 'C');
    $pdf->Cell($espacoEntre);
    $pdf->Cell($colWidth, 8, 'SALDO FINAL', 1, 0, 'C');
    $pdf->Cell($colWidth, 8, 'TOTAL', 1, 0, 'C');
    $pdf->Cell($colWidth, 8, '%', 1, 1, 'C');

    // Conteúdo
    $pdf->SetFont('', '');

    // Linha 1
    $pdf->SetFillColor(236, 65, 65);  // vermelho
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Não Endossado', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemEndossos["N"], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($percEndosso["N"] * 10000) / 100, 2) . '%', 1, 0, 'C');
    $pdf->Cell($espacoEntre);

    $pdf->SetFillColor(78, 169, 255);  // azul
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Meta', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemSaldos["meta"], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($performance["meta"] * 10000) / 100, 2) . '%', 1, 1, 'C');

    // Linha 2
    $pdf->SetFillColor(241, 198, 31);  // amarelo
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Endo. Parcialmente', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemEndossos['EP'], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($percEndosso["EP"] * 10000) / 100, 2) . '%', 1, 0, 'C');
    $pdf->Cell($espacoEntre);

    $pdf->SetFillColor(83, 208, 42);  // verde
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Positivo', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemSaldos["positivos"], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($performance["positivos"] * 10000) / 100, 2) . '%', 1, 1, 'C');

    // Linha 3
    $pdf->SetFillColor(78, 169, 255);  // azul
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Endossado', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemEndossos['E'], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($percEndosso["E"] * 10000) / 100, 2) . '%', 1, 0, 'C');
    $pdf->Cell($espacoEntre);

    $pdf->SetFillColor(236, 65, 65);  // vermelho
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Negativo', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemSaldos["negativos"], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($performance["negativos"] * 10000) / 100, 2) . '%', 1, 1, 'C');

    // Adiciona espaço após a última linha da tabela
    $pdf->Ln(10);

    // Define posição lateral ao topo da tabela
    $pdf->SetXY($posX_inicial + $totalTabela + 10, $posY_inicial);

    // Texto "Atualizado em"
    $pdf->SetFont('', '', 9);
    $pdf->Cell(60, 6, 'Atualizado em: ' . date("d/m/Y H:i", filemtime($path . "/empresa_" . $empresa["empr_nb_id"] . ".json")), 0, 2, 'L');

    // Texto "Período"
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(60, 6, 'Período do relatório: ' . $periodoRelatorio["dataInicio"] . " a " . $periodoRelatorio["dataFim"], 0, 1, 'L');

    // Tabela FRUTICANA + 9 colunas
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetY(60);
    $pdf->SetX(2);

    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $larguraTotal = 218;
    $altura = 7;
    $larguraCell = round($larguraTotal / 12, 2);

    $larguras = [
        15, // célula 1
        21, // célula 2
        45, // célula 1
    ];

    $pdf->SetFillColor(241, 198, 31);  // amarelo

    $pdf->MultiCell($larguraCell * 3.3, $altura, $totais["empresaNome"], 1, 'C', true);
    $pdf->SetXY($x + ($larguraCell * 3.3), $y);

    $pdf->Cell($larguras[1], $altura, '', 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, '', 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["jornadaPrevista"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["jornadaEfetiva"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["he50APagar"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["he100APagar"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["adicionalNoturno"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["esperaIndenizada"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["saldoAnterior"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["saldoPeriodo"], 1, 0, 'C', true);
    $pdf->Cell($larguras[1], $altura, $totais["saldoFinal"], 1, 0, 'C', true);

    $pdf->Ln(7);

    $dadosOrdenados = [];

    if (!empty($_POST["empresa"]) && !empty($_POST["busca_data"])) {
        $pdf->SetFont('helvetica', 'B', 6);

        $pdf->SetFillColor(78, 169, 255);  // azul

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[0], 7, 'MATRICULA', 1, 0, 'C', true);
        $x += $larguras[0];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[2], 7, 'NOME', 1, 0, 'C', true);
        $x += $larguras[2];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Ocupação', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Status Endosso', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Jornada Prevista', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Jornada Efetiva', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'H.E. Semanal Pago', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'H.E. Ex. Pago', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Adicional Noturno', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Espera Indenizada', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Saldo Anterior', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Saldo Período', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Saldo Final', 1, 0, 'C', true);

        $pdf->Ln(7);

        $pdf->SetFont('helvetica', 'B', 6);

        foreach ($arquivos as $caminho) {
            if (file_exists($caminho)) {
                $conteudo = file_get_contents($caminho);
                $dados = json_decode($conteudo, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $dadosOrdenados[] = $dados;
                }
            }
        }

        // Ordenar pelo campo 'nome' (ordem alfabética)
        usort($dadosOrdenados, function ($a, $b) {
            return strcmp(mb_strtoupper($a['nome']), mb_strtoupper($b['nome']));
        });

        // Agora imprime os dados no PDF
        foreach ($dadosOrdenados as $dados) {
            if ($dados['statusEndosso'] == 'E') {
                $pdf->SetFillColor(78, 169, 255);  // azul
            } else if ($dados['statusEndosso'] == 'EP') {
                $pdf->SetFillColor(241, 198, 31);  // amarelo
            } else {
                $pdf->SetFillColor(236, 65, 65);   // vermelho
            }

            $pdf->Cell($larguras[0], 7, $dados['matricula'], 1, 0, 'C');
            $pdf->Cell($larguras[2], 7, $dados['nome'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['ocupacao'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['statusEndosso'], 1, 0, 'C', true);
            $pdf->Cell($larguras[1], 7, $dados['jornadaPrevista'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['jornadaEfetiva'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['he50APagar'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['he100APagar'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['adicionalNoturno'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['esperaIndenizada'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['saldoAnterior'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['saldoPeriodo'], 1, 0, 'C');
            $pdf->Cell($larguras[1], 7, $dados['saldoFinal'], 1, 0, 'C');
            $pdf->Ln();
        }
    } elseif (!empty($_POST["busca_data"])) {

        $larguras = [
            20.9, // célula 1
            21, // célula 2
            60, // célula 1
        ];

        $pdf->SetFont('helvetica', 'B', 6);

        // Salva a posição inicial
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->SetFillColor(78, 169, 255);  // azul

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[2], 7, 'Nome da Empresa/Filial', 1, 0, 'C', true);
        $x += $larguras[2];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[0], 7, '% Endossados', 1, 0, 'C', true);
        $x += $larguras[0];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Qtd. Motoristas', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Jornada Prevista', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Jornada Efetiva', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'H.E. Semanal Pago', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'H.E. Ex. Pago', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Adicional Noturno', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Espera Indenizada', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Saldo Anterior', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Saldo Período', 1, 0, 'C', true);
        $x += $larguras[1];

        $pdf->SetXY($x, $y);
        $pdf->Cell($larguras[1], 7, 'Saldo Final', 1, 0, 'C', true);

        $pdf->Ln(7);

        $pdf->SetFont('helvetica', 'B', 6);
        foreach ($empresas as $empresa) {
            $linhaAltura = 7;
            $larguraNome = $larguras[2];

            // Salva a posição atual
            $x = $pdf->GetX();
            $y = $pdf->GetY();

            // MultiCell para o nome da empresa
            $pdf->MultiCell($larguraNome, $linhaAltura, $empresa['empr_tx_nome'], 1, 'C', false, 0);

            // Ajusta o Y da linha mais alta, caso MultiCell aumente
            $pdf->SetXY($x + $larguraNome, $y);

            // Células restantes
            $pdf->Cell($larguras[0], $linhaAltura, number_format(($empresa['percEndossado'] * 10000) / 100, 2) . '%', 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['qtdMotoristas'], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["jornadaPrevista"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["jornadaEfetiva"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["he50APagar"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["he100APagar"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["adicionalNoturno"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["esperaIndenizada"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["saldoAnterior"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["saldoPeriodo"], 1, 0, 'C');
            $pdf->Cell($larguras[1], $linhaAltura, $empresa['totais']["saldoFinal"], 1, 0, 'C');
            $pdf->Ln();
        }
    }

    // Gera o PDF
    $nomeArquivo = 'relatorio_endossos.pdf';
    $pdf->Output($nomeArquivo, 'I');
}

function gerarPainelNc() {

    $arquivos = [];
    $dataEmissao = ""; //Utilizado no HTML
    $path = "./arquivos/nao_conformidade_juridica";
    $periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

    $totais = [
        "jornadaSemRegistro" => 0,
        "refeicaoSemRegistro" => 0,
        "refeicao1h" => 0,
        "refeicao2h" => 0,
        "esperaAberto" => 0,
        "descansoAberto" => 0,
        "repousoAberto" => 0,
        "jornadaAberto" => 0,
        "jornadaExedida" => 0,
        "mdcDescanso" => 0,
        "intersticio" => 0
    ];

    $periodoRelatorio = [
        "dataInicio" => "1900-01-01",
        "dataFim" => "1900-01-01"
    ];

    if (!empty($_POST["empresa"]) && !empty($_POST["busca_data"])) {
        //Painel dos endossos dos motoristas de uma empresa específica
        $empresa = mysqli_fetch_assoc(query(
            "SELECT * FROM empresa"
                . " WHERE empr_tx_status = 'ativo'"
                . " AND empr_nb_id = " . $_POST["empresa"]
                . " LIMIT 1;"
        ));

        if ($_POST["busca_endossado"] === "naoEndossado") {
            $pastaArquivo = "nao_endossado";
        } else {
            $pastaArquivo = "endossado";
        }

        $path .= "/" . $_POST["busca_data"] . "/" . $empresa["empr_nb_id"] . "/" . $pastaArquivo;

        if (is_dir($path)) {
            $pasta = dir($path);
            while ($arquivo = $pasta->read()) {
                if (!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))) {
                    $arquivos[] = $arquivo;
                }
            }
            $pasta->close();

            $totalizadores = [
                "refeicao" => 0,
                "jornadaEfetiva" => 0,
                "espera" => 0,
                "descanso" => 0,
                "repouso" => 0,
                "jornada" => 0,
                "mdc" => 0,
                "intersticioInferior" => 0,
                "intersticioSuperior" => 0,
                "jornadaExcedido10h" => 0,
                "jornadaExcedido12h" => 0,
                "mdcDescanso30m5h" => 0,
                "refeicaoSemRegistro" => 0,
                "refeicao1h" => 0,
                "refeicao2h" => 0,
                "faltaJustificada" => 0,
                "falta" => 0
            ];

            $motoristas = 0;
            $ajudante = 0;
            $funcionario = 0;
            $totalJsonComTudoZero = 0;
            $totalDiasNaoCFuncionario = 0;
            $totaisFuncionario = [];
            $totaisFuncionario2 = [];
            $totaisMediaFuncionario = [];
            foreach ($arquivos as &$arquivo) {
                $todosZeros = true;
                $arquivo = $path . "/" . $arquivo;
                $json = json_decode(file_get_contents($arquivo), true);

                $totalMotorista = $json["espera"] + $json["descanso"] + $json["repouso"] + $json["jornada"] + $json["falta"] + $json["jornadaEfetiva"] + $json["mdc"]
                    + $json["refeicao"] + $json["intersticioInferior"] + $json["intersticioSuperior"];

                $totalDiasNaoCFuncionario += $json["diasConformidade"];

                $data = new DateTime($json["dataInicio"]);
                $dias = $data->format('t');

                $mediaPerfTotal = round(($totalDiasNaoCFuncionario / ($dias * sizeof($arquivos)) * 100), 2);

                $mediaPerfFuncionario = round(($json["diasConformidade"] / $dias) * 100, 2);

                $totaisMediaFuncionario[$json["matricula"]] = $mediaPerfFuncionario;

                $totalNConformMax = 4 * $dias;
                // Baixar performance total
                $porcentagemFunNCon = round(($totalMotorista * 100) / ($totalNConformMax * sizeof($arquivos)), 2);
                // Baixar performance funcionario
                $porcentagemFunNCon2 = round(($totalMotorista * 100) / $totalNConformMax, 2);

                $totaisFuncionario[$json["matricula"]] = $porcentagemFunNCon;
                $totaisFuncionario2[$json["matricula"]] = $porcentagemFunNCon2;

                foreach ($totalizadores as $key => &$total) {
                    if (!in_array($key, ['faltaJustificada', 'jornadaPrevista']) && (!isset($json[$key]) || $json[$key] != 0)) {
                        $todosZeros = false; // Algum campo não está zerado
                        // break;
                    }
                    $total += $json[$key] ?? 0; // incrementa apenas se o índice existir no JSON
                }

                if ($todosZeros) {
                    $totalJsonComTudoZero++; // Incrementa o contador
                }

                $ocupacao = $json["ocupacao"] ?? "Desconhecida";
                if (!isset($ocupacoes[$ocupacao])) {
                    $ocupacoes[$ocupacao] = 0;
                }
                $ocupacoes[$ocupacao]++;

                unset($total);
            }
            $totalOcupacoes = array_sum($ocupacoes);

            $porcentagemTotalBaixa = array_sum((array) $totaisFuncionario);
            $totalFun = sizeof($arquivos) - $totalJsonComTudoZero;
            $porcentagemTotalBaixaG = 100 - $porcentagemTotalBaixa;
            $porcentagemTotalMedia = 100 - $mediaPerfTotal;
            $porcentagemTotalBaixa2 = (array) $totaisFuncionario2;

            if (!empty($_POST["empresa"]) && $_POST["busca_endossado"] === "endossado") {
                $totalNaoconformidade = array_sum([
                    $totalizadores["mdcDescanso30m5h"],
                    $totalizadores["inicioRefeicaoSemRegistro"],
                    $totalizadores["refeicaoSemRegistro"],
                    $totalizadores["refeicao1h"],
                    $totalizadores["refeicao2h"],
                    $totalizadores["intersticioInferior"],
                    $totalizadores["intersticioSuperior"],
                    $totalizadores["jornadaPrevista"]
                ]);

                $gravidadeAlta = $totalizadores["refeicao"] + $totalizadores["intersticioInferior"] + $totalizadores["intersticioSuperior"];
                $gravidadeMedia = $totalizadores["jornadaEfetiva"] + $totalizadores["mdc"];
                $gravidadeBaixa = $totalizadores["falta"];
            } else {
                $totalNaoconformidade = array_sum([
                    $totalizadores["espera"],
                    $totalizadores["descanso"],
                    $totalizadores["repouso"],
                    $totalizadores["jornada"],
                    $totalizadores["faltaJustificada"],
                    $totalizadores["falta"],
                    $totalizadores["jornadaExcedido10h"],
                    $totalizadores["jornadaExcedido12h"],
                    $totalizadores["mdcDescanso30m5h"],
                    $totalizadores["refeicaoSemRegistro"],
                    $totalizadores["refeicao1h"],
                    $totalizadores["refeicao2h"],
                    $totalizadores["intersticioInferior"],
                    $totalizadores["intersticioSuperior"]
                ]);

                $gravidadeAlta = $totalizadores["refeicao"] + $totalizadores["intersticioInferior"] + $totalizadores["intersticioSuperior"];
                $gravidadeMedia = $totalizadores["jornadaEfetiva"] + $totalizadores["mdc"];
                $gravidadeBaixa = $totalizadores["falta"] + $totalizadores["espera"] + $totalizadores["descanso"] +
                    $totalizadores["repouso"] + $totalizadores["jornada"];
            }

            $totalColaboradores = $motoristas + $ajudante + $funcionario;
            $porcentagemFun = $totalColaboradores != 0
                ? ($totalJsonComTudoZero / $totalColaboradores) * 100
                : 0;

            $totalGeral = $gravidadeAlta + $gravidadeMedia + $gravidadeBaixa;
            $graficoSintetico = [$gravidadeAlta, $gravidadeMedia, $gravidadeBaixa];

            $percentuais = [
                "performance" => $totalGeral > 0 ? round(($totalJsonComTudoZero ?? 0) / $totalGeral) : 0,
                "alta" => $totalGeral > 0 ? round(($gravidadeAlta / $totalGeral) * 100, 2) : 0,
                "media" => $totalGeral > 0 ? round(($gravidadeMedia / $totalGeral) * 100, 2) : 0,
                "baixa" => $totalGeral > 0 ? round(($gravidadeBaixa / $totalGeral) * 100, 2) : 0
            ];

            if ($_POST["busca_endossado"] !== "endossado") {

                $keys = [
                    "espera",
                    "descanso",
                    "repouso",
                    "jornada",
                    "falta",
                    "jornadaEfetiva",
                    "mdc",
                    "refeicao",
                    "intersticioInferior",
                    "intersticioSuperior"
                ];

                $keys2 = [
                    "espera",
                    "descanso",
                    "repouso",
                    "jornada",
                    "faltaJustificada",
                    "falta",
                    "jornadaExcedido10h",
                    "jornadaExcedido12h",
                    "mdcDescanso30m5h",
                    "refeicaoSemRegistro",
                    "refeicao1h",
                    "refeicao2h",
                    "intersticioInferior",
                    "intersticioSuperior"
                ];
            } else {
                $keys2 = [
                    "faltaJustificada",
                    "falta",
                    "jornadaExcedido10h",
                    "jornadaExcedido12h",
                    "mdcDescanso30m5h",
                    "refeicaoSemRegistro",
                    "refeicao1h",
                    "refeicao2h",
                    "intersticioInferior",
                    "intersticioSuperior"
                ];
            }

            // Percentuais gerais de Não Conformidade (baseado no total geral)
            foreach ($keys as $key) {
                $percentuais["Geral_" . $key] = $totalGeral > 0
                    ? number_format(round(($totalizadores[$key] / $totalGeral) * 100, 2), 2)
                    : "0.00";

                $graficoAnalitico[] = $totalizadores[$key];
            }

            // Percentuais específicos de Não Conformidade (baseado no total de não conformidade)
            foreach ($keys2 as $key) {
                if ($totalNaoconformidade > 0 && isset($totalizadores[$key])) {
                    $percentuais["Especifico_" . $key] =  number_format(round(($totalizadores[$key] / $totalNaoconformidade) * 100, 2), 2);
                } else {
                    $percentuais["Especifico_" . $key] = 0;
                }
                $graficoDetalhado[] = $totalizadores[$key];
            }

            if (!empty($arquivo)) {
                $dataArquivo = date("d/m/Y", filemtime($arquivo));
                $horaArquivo = date("H:i", filemtime($arquivo));

                $dataAtual = date("d/m/Y");
                $horaAtual = date("H:i");
                $dataEmissao =  " Atualizado em: " . date("d/m/Y H:i", filemtime($arquivo)) . "</span>"; //Utilizado no HTML.
                $arquivoGeral = json_decode(file_get_contents($arquivo), true);

                $periodoRelatorio = [
                    "dataInicio" => $arquivoGeral["dataInicio"],
                    "dataFim" => $arquivoGeral["dataFim"]
                ];


                $encontrado = true;
            }

            $pasta = dir($path);
            while ($arquivoEmpresa = $pasta->read()) {
                if (!empty($arquivoEmpresa) && !in_array($arquivoEmpresa, [".", ".."]) && !is_bool(strpos($arquivoEmpresa, "empresa_"))) {
                    $arquivoEmpresa = $path . "/" . $arquivoEmpresa;
                    $totalempre = json_decode(file_get_contents($arquivoEmpresa), true);
                }
            }
            $pasta->close();

            foreach ($arquivos as $caminho) {
                if (file_exists($caminho)) {
                    $conteudo = file_get_contents($caminho);
                    $dados = json_decode($conteudo, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $dadosOrdenados[] = $dados;
                    }
                }
            }

            usort($dadosOrdenados, function ($a, $b) {
                return strcmp(mb_strtoupper($a['nome']), mb_strtoupper($b['nome']));
            });
        }
    }

    $pdf = new CustomPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->tituloPersonalizado = 'Relatório de Saldo';
    $pdf->SetCreator('TechPS');
    $pdf->SetAuthor('TechPS');
    $pdf->SetTitle('Relatório de Saldo');
    $pdf->SetMargins(2, 25, 2);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
    $pdf->AddPage();

    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(0, 0, 0);

    $textoCabecalho = "Período do relatório: 01/04/2025 a 09/04/2025\nEmpresa: FRUTICANA PRODUCAO, COMERCIO, IMPORTACAO E EXPORTACAO LTDA";

    // Largura total da página útil
    $larguraPagina = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];

    $pdf->Ln(5); // Espaço antes
    $pdf->MultiCell($larguraPagina, 10, $textoCabecalho, 0, 'C'); // alinhamento 'C' = centro
    $pdf->Ln(5);

    $userEntrada = preg_replace('/[^a-zA-Z0-9_-]/', '', $_SESSION['horaEntrada']);
    $pdf->Image('./arquivos/graficos/grafico_graficoPerformance_' . $_POST["busca_data"] . '_' . $userEntrada . '.png', 10, 50, 60);
    $pdf->Image('./arquivos/graficos/grafico_graficoPerformanceMedia_' . $_POST["busca_data"] . '_' . $userEntrada . '.png', 122, 50, 60);
    $pdf->Image('./arquivos/graficos/grafico_graficoPerformanceBaixa_' . $_POST["busca_data"] . '_' . $userEntrada . '.png', 230, 50, 60);

    // Simula "Performance Alta"
    // $pdf->Rect(55, 50, 60, 40, 'DF'); // x, y, w, h, D=Draw, F=Fill
    // $pdf->SetXY(15, 40);

    // // Simula "Performance Média"
    // $pdf->Rect(125, 50, 60, 40, 'DF');
    // $pdf->SetXY(75, 40);

    // // Simula "Performance Baixa"
    // $pdf->Rect(195, 50, 60, 40, 'DF');
    // $pdf->SetXY(135, 40);

    // === Espaço após os gráficos ===
    $pdf->Ln(60);

    // === Tabela: Quantidade por ocupação ===
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(50, 7, 'Quantidade por ocupação', 1, 0, 'C');
    $pdf->Cell(10, 7,  $totalOcupacoes, 1, 0, 'C');
    $pdf->Cell(20, 7, '100,00 %', 1, 1, 'C');

    $pdf->SetFont('helvetica', '', 10);
    $somaPorcentagem = 0;
    foreach ($ocupacoes as $tipo => $quantidade) {
        $porcentagem = $totalOcupacoes > 0 ? round(($quantidade / $totalOcupacoes) * 100, 2) : 0;
        $somaPorcentagem += $porcentagem;

        $pdf->Cell(50, 7, $tipo, 1, 0, 'L');
        $pdf->Cell(10, 7, $quantidade, 1, 0, 'C');
        $pdf->Cell(20, 7, number_format($porcentagem, 2, ',', '.') . ' %', 1, 1, 'C');
    }

    // === Espaço antes da próxima tabela ===
    $pdf->Ln(5);

    // === Tabela: Nível de Gravidade ===
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(50, 7, 'Nível de Gravidade', 1, 0, 'C');
    $pdf->Cell(10, 7, 'Total', 1, 0, 'C');
    $pdf->Cell(20, 7, '%', 1, 1, 'C');

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(255, 232, 0);  // amarelo
    $pdf->Cell(50, 6, 'Baixa', 1, 0, '', true);
    $pdf->SetFillColor(0, 0, 0);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(10, 6, $gravidadeBaixa, 1);
    $pdf->Cell(20, 6, number_format($percentuais["baixa"], 2, ',', '.') . '%', 1, 1);

    $pdf->SetFillColor(255, 139, 0);  // laranja
    $pdf->Cell(50, 6, 'Média', 1, 0, '', true);
    $pdf->SetFillColor(0, 0, 0);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(10, 6, $gravidadeMedia, 1);
    $pdf->Cell(20, 6, number_format($percentuais["media"], 2, ',', '.') . '%', 1, 1);

    $pdf->SetFillColor(236, 65, 65);  // vermelho
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell(50, 6, 'Alta', 1, 0, '', true);
    $pdf->SetFillColor(0, 0, 0);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(10, 6, $gravidadeAlta, 1);
    $pdf->Cell(20, 6, number_format($percentuais["alta"], 2, ',', '.') . '%', 1, 1);

    // --- GRÁFICOS ABAIXO DAS TABELAS ---

    $alturaTabelas = 20 + (6 * 3) + 2; // Calcula altura total das tabelas
    $posYGraficos = 40 + $alturaTabelas + 30; // 15mm de espaçamento

    // Gráfico Sintético (Esquerda)
    $pdf->Image('./arquivos/graficos/grafico_graficoSintetico_' . $_POST["busca_data"] . '_' . $userEntrada . '.png', 95, $posYGraficos, 80);
    // Gráfico Analítico (Direita)
    $pdf->Image('./arquivos/graficos/grafico_graficoAnalitico_' . $_POST["busca_data"] . '_' . $userEntrada . '.png', 185, $posYGraficos, 100);

    // // Gráfico Sintético (Esquerda)
    // $pdf->SetY($posYGraficos - 10); // Ajuste para o título
    // $pdf->SetFont('helvetica', 'B', 12);
    // $pdf->Rect(90, $posYGraficos, 90, 70, 'DF');

    // // Gráfico Analítico (Direita)
    // $pdf->SetY($posYGraficos - 10); // Mesma altura do título esquerdo
    // $pdf->SetX(140);
    // $pdf->Rect(200, $posYGraficos, 90, 70, 'DF');

    $pdf->addPage();

    // Obter as margens atuais
    $margins = $pdf->getMargins();

    // Coordenadas para o retângulo (começa após o cabeçalho)
    $x = $margins['left']; // Margem esquerda
    $y = $margins['top'];  // Começa após o cabeçalho (25mm conforme seu SetMargins)
    $width = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
    $height = $pdf->getPageHeight() - $margins['top'] - $margins['bottom'];

    $pdf->Image('./arquivos/graficos/grafico_graficoDetalhado_' . $_POST["busca_data"] . '_' . $userEntrada . '.png', $x, $y, $width, $height);

    // $pdf->SetFillColor(240, 240, 240);
    // Adicionar retângulo que preenche a área útil da página
    // $pdf->Rect($x, $y, $width, $height, 'F'); // 'F' para preenchimento

    $pdf->addPage();

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 7, 'Legendas', 0, 1, 'L');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(70, 7, '', 1, 0, 'C');
    $pdf->Cell(180, 7, 'Descrição', 1, 0, 'C');
    $pdf->Cell(10, 7, 'Total', 1, 0, 'C');
    $pdf->Cell(20, 7, '%', 1, 1, 'C');

    $pdf->SetFillColor(255, 242, 96);  // amarelo

    $pdf->SetFont('helvetica', '', 10);
    if ($_POST["busca_endossado"] == "naoEndossado") {
        $pdf->Cell(70, 7, 'Espera', 1, 0, 'C', true);
        $pdf->Cell(180, 7, '"Inicio ou Fim de espera sem registro"', 1, 0, 'C', true);
        $pdf->Cell(10, 7, $totalizadores["espera"], 1, 0, 'C', true);
        $pdf->Cell(20, 7, $percentuais["Geral_espera"] . '%', 1, 1, 'C', true);

        $pdf->Cell(70, 7, 'Descanso', 1, 0, 'C', true);
        $pdf->Cell(180, 7, '"Inicio ou Fim de descanso sem registro"', 1, 0, 'C', true);
        $pdf->Cell(10, 7, $totalizadores["descanso"], 1, 0, 'C', true);
        $pdf->Cell(20, 7, $percentuais["Geral_descanso"] . '%', 1, 1, 'C', true);

        $pdf->Cell(70, 7, 'Repouso', 1, 0, 'C', true);
        $pdf->Cell(180, 7, '"Inicio ou Fim de repouso sem registro"', 1, 0, 'C', true);
        $pdf->Cell(10, 7, $totalizadores["repouso"], 1, 0, 'C', true);
        $pdf->Cell(20, 7, $percentuais["Geral_repouso"] . '%', 1, 1, 'C', true);

        $pdf->Cell(70, 7, 'Jornada', 1, 0, 'C', true);
        $pdf->Cell(180, 7, '"Inicio ou Fim de Jornada sem registro"', 1, 0, 'C', true);
        $pdf->Cell(10, 7, $totalizadores["jornada"], 1, 0, 'C', true);
        $pdf->Cell(20, 7, $percentuais["Geral_jornada"] . '%', 1, 1, 'C', true);
    }
    $pdf->Cell(70, 7, 'Jornada Prevista', 1, 0, 'C', true);
    $pdf->Cell(180, 7, '"Faltas não justificadas"', 1, 0, 'C', true);
    $pdf->Cell(10, 7, $totalizadores["falta"], 1, 0, 'C', true);
    $pdf->Cell(20, 7, $percentuais["Geral_falta"] . '%', 1, 1, 'C', true);
    $pdf->SetFillColor(255, 139, 0);  // laranja

    $pdf->Cell(70, 7, 'Jornada Efetiva', 1, 0, 'C', true);
    $pdf->Cell(180, 7, '"Tempo excedido de 12:00h de jornada efetiva"', 1, 0, 'C', true);
    $pdf->Cell(10, 7, $totalizadores["jornadaEfetiva"], 1, 0, 'C', true);
    $pdf->Cell(20, 7, $percentuais["Geral_jornadaEfetiva"] . '%', 1, 1, 'C', true);

    $pdf->Cell(70, 7, 'MDC - Máximo de Direção Continua', 1, 0, 'C', true);
    $pdf->Cell(180, 7, '"Descanso de 30 minutos a cada 05:30 de direção não respeitado."', 1, 0, 'C', true);
    $pdf->Cell(10, 7, $totalizadores["mdc"], 1, 0, 'C', true);
    $pdf->Cell(20, 7, $percentuais["Geral_mdc"] . '%', 1, 1, 'C', true);

    $pdf->SetFillColor(236, 65, 65);  // vermelho
    $pdf->SetTextColor(255, 255, 255); // branco
    // Célula da coluna "Refeição"
    $pdf->Cell(70, 14, 'Refeição', 1, 0, 'C', true);

    // Célula de descrição com quebra automática
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $pdf->MultiCell(
        180,
        14,
        '"Batida de início ou fim de refeição não registrada" ou "Refeição ininterrupta maior que 1 hora não respeitada" ou "Tempo máximo de 2 horas para a refeição não respeitado"',
        1,
        'C',
        true
    );

    // Posiciona as próximas células ao lado da MultiCell
    $pdf->SetXY($x + 180, $y);
    $pdf->Cell(10, 14, $totalizadores["refeicao"], 1, 0, 'C', true);
    $pdf->Cell(20, 14, $percentuais["Geral_refeicao"] . '%', 1, 1, 'C', true);

    $pdf->Cell(70, 7, 'Interstício Inferior', 1, 0, 'C', true);
    $pdf->Cell(180, 7, '"O mínimo de 11 horas de interstício não foi respeitado"', 1, 0, 'C', true);
    $pdf->Cell(10, 7, $totalizadores["intersticioInferior"], 1, 0, 'C', true);
    $pdf->Cell(20, 7, $percentuais["Geral_intersticioInferior"] . '%', 1, 1, 'C', true);

    $pdf->Cell(70, 7, 'Interstício Superior', 1, 0, 'C', true);
    $pdf->Cell(180, 7, '"Interstício total de 11 horas não respeitado"', 1, 0, 'C', true);
    $pdf->Cell(10, 7, $totalizadores["intersticioSuperior"], 1, 0, 'C', true);
    $pdf->Cell(20, 7, $percentuais["Geral_intersticioSuperior"] . '%', 1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0); // branco

    $pdf->addPage();

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 7, 'Tabela detalhada de não conformidade', 0, 1, 'L');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 7);

    // Cabeçalho
    $pdf->Cell(13, 7, 'Matricula', 1, 0, 'C');
    $pdf->Cell(52, 7, 'Funcionário', 1, 0, 'C');
    $pdf->Cell(13, 7, 'Ocupação', 1, 0, 'C');

    $pdf->SetFillColor(255, 242, 96);  // amarelo
    if ($_POST["busca_endossado"] == "naoEndossado") {
        $pdf->Cell(12, 7, 'Espera', 1, 0, 'C', true);
        $pdf->Cell(13, 7, 'Descanso', 1, 0, 'C', true);
        $pdf->Cell(12, 7, 'Repouso', 1, 0, 'C', true);
        $pdf->Cell(12, 7, 'Jornada', 1, 0, 'C', true);
    }
    $pdf->Cell(21, 7, 'Jornada Prevista', 1, 0, 'C', true);

    $pdf->SetFillColor(255, 139, 0);  // laranja
    $pdf->Cell(21, 7, 'Jornada Efetiva', 1, 0, 'C', true);
    $pdf->Cell(8, 7, 'MDC', 1, 0, 'C', true);

    $pdf->SetFillColor(236, 65, 65);
    $pdf->Cell(12, 7, 'Refeição', 1, 0, 'C', true);
    $pdf->Cell(23, 7, 'Interstício Inferior', 1, 0, 'C', true);
    $pdf->Cell(23, 7, 'Interstício Superior', 1, 0, 'C', true);

    $pdf->Cell(11, 7, 'TOTAL', 1, 0, 'C');
    $pdf->Cell(24, 7, 'Performance Média', 1, 0, 'C');
    $pdf->Cell(24, 7, 'Performance Baixa', 1, 1, 'C');

    $pdf->SetFont('helvetica', '', 7);

    // Conteúdo
    foreach ($dadosOrdenados as $dados) {
        $total = $dados['espera'] + $dados['descanso'] + $dados['repouso'] + $dados['jornada']
            + $dados['falta'] + $dados['jornadaEfetiva'] + $dados['mdc']
            + $dados['refeicao'] + $dados['intersticioInferior'] + $dados['intersticioSuperior'];

        $pdf->Cell(13, 7, $dados['matricula'], 1, 0, 'C');
        $pdf->Cell(52, 7, $dados['nome'], 1, 0, 'L');
        $pdf->Cell(13, 7, $dados['ocupacao'], 1, 0, 'L');

        $pdf->SetFillColor(255, 242, 96);  // amarelo
        if ($_POST["busca_endossado"] == "naoEndossado") {
            $pdf->Cell(12, 7, $dados['espera'] != 0 ? $dados['espera'] : '', 1, 0, 'C', true);
            $pdf->Cell(13, 7, $dados['descanso'] != 0 ? $dados['descanso'] : '', 1, 0, 'C', true);
            $pdf->Cell(12, 7, $dados['repouso'] != 0 ? $dados['repouso'] : '', 1, 0, 'C', true);
            $pdf->Cell(12, 7, $dados['jornada'] != 0 ? $dados['jornada'] : '', 1, 0, 'C', true);
        }
        $pdf->Cell(21, 7, $dados['falta'] != 0 ? $dados['falta'] : '', 1, 0, 'C', true);

        $pdf->SetFillColor(255, 139, 0);  // laranja
        $pdf->Cell(21, 7, $dados['jornadaEfetiva'] != 0 ? $dados['jornadaEfetiva'] : '', 1, 0, 'C', true);
        $pdf->Cell(8, 7, $dados['mdc'] != 0 ? $dados['mdc'] : '', 1, 0, 'C', true);

        $pdf->SetFillColor(236, 65, 65);
        $pdf->Cell(12, 7, $dados['refeicao'] != 0 ? $dados['refeicao'] : '', 1, 0, 'C', true);
        $pdf->Cell(23, 7, $dados['intersticioInferior'] != 0 ? $dados['intersticioInferior'] : '', 1, 0, 'C', true);
        $pdf->Cell(23, 7, $dados['intersticioSuperior'] != 0 ? $dados['intersticioSuperior'] : '', 1, 0, 'C', true);

        $pdf->Cell(11, 7, $total, 1, 0, 'C');

        if ((100 - $totaisMediaFuncionario[$dados['matricula']]) >= 75 && (100 - $totaisMediaFuncionario[$dados['matricula']]) <= 100) {
            $pdf->SetFillColor(144, 238, 144);  // verde
        } else if ((100 - $totaisMediaFuncionario[$dados['matricula']]) <= 75 && (100 - $totaisMediaFuncionario[$dados['matricula']]) >= 50) {
            $pdf->SetFillColor(255, 242, 96);  // amarelo
        } else if ((100 - $totaisMediaFuncionario[$dados['matricula']]) <= 50 && (100 - $totaisMediaFuncionario[$dados['matricula']]) >= 25) {
            $pdf->SetFillColor(255, 159, 44);  // laranja
        } else {
            $pdf->SetFillColor(196, 18, 18);  // vermelho
        }

        $porcent1 = isset($totaisMediaFuncionario[$dados['matricula']])
            ? number_format(100 - $totaisMediaFuncionario[$dados['matricula']], 2, ',', '.')
            : '0,00';
        $pdf->Cell(24, 7, $porcent1 . '%', 1, 0, 'C', true);

        if ((100 - $totaisFuncionario2[$dados['matricula']]) >= 75 && (100 - $totaisFuncionario2[$dados['matricula']]) <= 100) {
            $pdf->SetFillColor(144, 238, 144);  // verde
        } else if ((100 - $totaisFuncionario2[$dados['matricula']]) <= 75 && (100 - $totaisFuncionario2[$dados['matricula']]) >= 50) {
            $pdf->SetFillColor(255, 242, 96);  // amarelo
        } else if ((100 - $totaisFuncionario2[$dados['matricula']]) <= 50 && (100 - $totaisFuncionario2[$dados['matricula']]) >= 25) {
            $pdf->SetFillColor(255, 159, 44);  // laranja
        } else {
            $pdf->SetFillColor(196, 18, 18);  // vermelho
        }
        $porcent2 = isset($totaisFuncionario2[$dados['matricula']])
            ? number_format(100 - $totaisFuncionario2[$dados['matricula']], 2, ',', '.')
            : '0,00';
        $pdf->Cell(24, 7, $porcent2 . '%', 1, 1, 'C', true);
    }
    $totalBaixaPerformance = number_format(100 - array_sum($totaisFuncionario), 2, ',', '.');
    $totalMediaPerformance = number_format(100 - $mediaPerfTotal, 2, ',', '.');
    $pdf->Cell(13, 7, '', 1, 0, 'C');
    $pdf->Cell(52, 7, '', 1, 0, 'C');
    $pdf->Cell(13, 7, 'Total', 1, 0, 'C');
    if ($_POST["busca_endossado"] !== "endossado") {
        $pdf->Cell(12, 7, $totalempre["espera"], 1, 0, 'C');
        $pdf->Cell(13, 7, $totalempre["descanso"], 1, 0, 'C');
        $pdf->Cell(12, 7, $totalempre["repouso"], 1, 0, 'C');
        $pdf->Cell(12, 7, $totalempre["jornada"], 1, 0, 'C');
    }
    $pdf->Cell(21, 7, $totalempre["falta"], 1, 0, 'C');
    $pdf->Cell(21, 7, $totalempre["jornadaEfetiva"], 1, 0, 'C');
    $pdf->Cell(8, 7, $totalempre["mdc"], 1, 0, 'C');
    $pdf->Cell(12, 7, $totalempre["refeicao"], 1, 0, 'C');
    $pdf->Cell(23, 7, $totalempre["intersticioInferior"], 1, 0, 'C');
    $pdf->Cell(23, 7, $totalempre["intersticioSuperior"], 1, 0, 'C');
    $pdf->Cell(11, 7, $totalGeral, 1, 0, 'C');

    $pdf->Cell(24, 7, $totalMediaPerformance . '%', 1, 0, 'C');
    $pdf->Cell(24, 7, $totalBaixaPerformance . '%', 1, 1, 'C');

    // dd($dadosOrdenados);


    // Gera o PDF
    $nomeArquivo = 'relatorio_endossos.pdf';
    $pdf->Output($nomeArquivo, 'I');
}

CustomPDF::setEmpresaData($empresa);

if (!empty($_POST['relatorio']) && $_POST['relatorio'] == 'endosso') {
    gerarPainelEndosso();
} else if (!empty($_POST['relatorio']) && $_POST['relatorio'] == 'saldo') {
    gerarPainelSaldo();
} else if (!empty($_POST['relatorio']) && $_POST['relatorio'] == 'nc_juridica') {
    gerarPainelNc();
}
