<?php
require_once __DIR__ . "/../tcpdf/tcpdf.php";
require __DIR__ . "/../funcoes_ponto.php";
require_once __DIR__."/funcoes_paineis.php";

    /* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
    //*/
    if(!empty($_POST["empresa"])){
        $empresa = mysqli_fetch_assoc(query(
            "SELECT * FROM empresa
            WHERE empr_tx_status = 'ativo'
                AND empr_nb_id = {$_POST["empresa"]}
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
        $imgWidth = 50;
        $imgHeight = 15;
        $this->Image(__DIR__ . "/../imagens/logo_topo_cliente.png", 10, 10, $imgWidth, $imgHeight);
        $this->Image(__DIR__ ."/../".self::$empresaData["empr_tx_logo"], $this->GetPageWidth() - $imgWidth - 10, 10, $imgWidth, $imgHeight);
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
                        $matriculasInativas = array_map(function($matricula) {
                            return $matricula . ".json";
                        }, array_column($motoristas, "enti_tx_matricula"));
                    }
                } else {
                    $dataMotorista = new DateTime($motorista["enti_tx_admissao"]);
                    $dataMotorista = $dataMotorista->format("Y-m");
                    if ($dataBusca < $dataMotorista) {
                        $matriculasInativas = array_map(function($matricula) {
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

        // dd($empresas);

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
    $pdf->tituloPersonalizado = 'Relatório Ajustes de Pontos Ativos';
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
    $pdf->Cell($colWidth, 8, number_format(($percEndosso["N"]*10000)/100, 2).'%', 1, 0, 'C');
    $pdf->Cell($espacoEntre);

    $pdf->SetFillColor(78, 169, 255);  // azul
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Meta', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemSaldos["meta"], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($performance["meta"]*10000)/100, 2).'%', 1, 1, 'C');

    // Linha 2
    $pdf->SetFillColor(241, 198, 31);  // amarelo
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Endo. Parcialmente', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemEndossos['EP'], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($percEndosso["EP"]*10000)/100, 2).'%', 1, 0, 'C');
    $pdf->Cell($espacoEntre);

    $pdf->SetFillColor(83, 208, 42);  // verde
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Positivo', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemSaldos["positivos"], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($performance["positivos"]*10000)/100, 2).'%', 1, 1, 'C');

    // Linha 3
    $pdf->SetFillColor(78, 169, 255);  // azul
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Endossado', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemEndossos['E'], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($percEndosso["E"]*10000)/100, 2).'%', 1, 0, 'C');
    $pdf->Cell($espacoEntre);

    $pdf->SetFillColor(236, 65, 65);  // vermelho
    $pdf->SetTextColor(255, 255, 255); // branco
    $pdf->Cell($colWidth, 8, 'Negativo', 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colWidth, 8, $contagemSaldos["negativos"], 1, 0, 'C');
    $pdf->Cell($colWidth, 8, number_format(($performance["negativos"]*10000)/100, 2).'%', 1, 1, 'C');

    // Adiciona espaço após a última linha da tabela
    $pdf->Ln(10);

    // Define posição lateral ao topo da tabela
    $pdf->SetXY($posX_inicial + $totalTabela + 10, $posY_inicial);

    // Texto "Atualizado em"
    $pdf->SetFont('', '', 9);
    $pdf->Cell(60, 6, 'Atualizado em: ' . date("d/m/Y H:i", filemtime($path."/empresa_".$empresa["empr_nb_id"].".json")), 0, 2, 'L');

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

        // Salva a posição atual
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->SetFillColor(78, 169, 255);  // azul

        // Primeira célula
        $pdf->Cell($larguras[0], 7, 'MATRICULA', 1, 'C', false, true, $x, $y);
        $x += $larguras[true];

        $pdf->Cell($larguras[2], 7, 'NOME', 1, 'C', false, true, $x, $y);
        $x += $larguras[2];

        $pdf->Cell($larguras[1], 7, 'Ocupação', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Status Endosso', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Jornada Prevista', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Jornada Efetiva', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'H.E. Semanal Pago', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'H.E. Ex. Pago', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Adicional Noturno', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Espera Indenizada', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Saldo Anterior', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Saldo Período', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Saldo Final', 1, 'C', false, true, $x, $y);

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
        usort($dadosOrdenados, function($a, $b) {
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

        // Salva a posição atual
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->SetFillColor(78, 169, 255);  // azul

        // Primeira célula
        $pdf->Cell($larguras[2], 7, 'Nome da Empresa/Filial', 1, 'C', false, true, $x, $y);
        $x += $larguras[true];

        $pdf->Cell($larguras[0], 7, '% Endossados', 1, 'C', false, true, $x, $y);
        $x += $larguras[2];

        $pdf->Cell($larguras[1], 7, 'Qtd. Motoristas', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Jornada Prevista', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Jornada Efetiva', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'H.E. Semanal Pago', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'H.E. Ex. Pago', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Adicional Noturno', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Espera Indenizada', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Saldo Anterior', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Saldo Período', 1, 'C', false, true, $x, $y);
        $x += $larguras[1];

        $pdf->Cell($larguras[1], 7, 'Saldo Final', 1, 'C', false, true, $x, $y);

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
            $pdf->Cell($larguras[0], $linhaAltura, number_format(($empresa['percEndossado']*10000)/100, 2).'%', 1, 0, 'C');
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

CustomPDF::setEmpresaData($empresa);
gerarPainelEndosso();
