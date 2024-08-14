<?php
    /* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/

    $mesAtual = date("n");
    $anoAtual = date("Y");

    // Obtém O total dos saldos das empresas
    $file = "./arquivos/paineis/empresas/$anoAtual-$mesAtual/empresas.json";

    if (file_exists("./arquivos/paineis/empresas/$anoAtual-$mesAtual")) {
        $conteudo_json = file_get_contents($file);
        $empresasTotais = json_decode($conteudo_json,true);
    }

    // Obtém O total dos saldos de cada empresa
    $fileEmpresas = "./arquivos/paineis/empresas/$anoAtual-$mesAtual/totalEmpresas.json";
    if (file_exists("./arquivos/paineis/empresas/$anoAtual-$mesAtual")) {
        $conteudo_json = file_get_contents($fileEmpresas);
        $empresaTotais = json_decode($conteudo_json,true);
    }

    // Obtém o tempo da última modificação do arquivo
    $timestamp = filemtime($file);
    $emissao = date("d/m/Y H:i:s", $timestamp);

    // Calcula a porcentagem
    $porcentagemNaEndo = number_format(0,2);
    $porcentagemEndoPc = number_format(0,2);
    $porcentagemEndo = number_format(0,2);
    if ($empresasTotais["EmprTotalNaoEnd"] != 0) {
        $porcentagemNaEndo = number_format(($empresasTotais["EmprTotalNaoEnd"] / $empresasTotais["EmprTotalMotorista"]) * 100, 2);
    }
    if ($empresasTotais["EmprTotalEndPac"] != 0) {
        $porcentagemEndoPc = number_format(($empresasTotais["EmprTotalEndPac"] / $empresasTotais["EmprTotalMotorista"]) * 100, 2);
    }
    if ($empresasTotais["EmprTotalEnd"] != 0) {
        $porcentagemEndo = number_format(($empresasTotais["EmprTotalEnd"] / $empresasTotais["EmprTotalMotorista"]) * 100, 2);
    }

    $quantPosi = 0;
    $quantNega = 0;
    $quantMeta = 0;

    foreach ($empresaTotais as $empresaTotal) {
        $saldoFinal = $empresaTotal["saldoFinal"];

        if ($saldoFinal === "00:00") {
            $quantMeta++;
        } elseif ($saldoFinal > "00:00") {
            $quantPosi++;
        } elseif ($saldoFinal < "00:00") {
            $quantNega++;
        }
    }

    $porcentagemMeta = ($quantMeta != 0)? number_format(($quantMeta / count($empresaTotais)) * 100, 2): number_format(0,2);
    $porcentagemNega = ($quantNega != 0)? number_format(($quantNega / count($empresaTotais)) * 100, 2): number_format(0,2);
    $porcentagemPosi = ($quantPosi != 0)? number_format(($quantPosi / count($empresaTotais)) * 100, 2): number_format(0,2);

    if(!is_dir("./arquivos/paineis")){
        mkdir("./arquivos/paineis", 0755, true);
    }

    $csvPainelEmpresa = "./arquivos/paineis/Painel_Geral.csv";
    // Cabeçalhos
    $tabela1 = [
        "cabecalho" =>              ["",                "QUANT",                                    "%",                "", "SALDO FINAL",  "QUANT","%"],
        "naoEndossado" =>           ["NÃO ENDOSSADO",   strval($empresasTotais["EmprTotalNaoEnd"]), $porcentagemNaEndo, "", $quantMeta,     $porcentagemMeta],
        "endossadoParcialmente" =>  ["ENDOSSO PARCIAL", strval($empresasTotais["EmprTotalEndPac"]), $porcentagemEndoPc, "", $quantPosi,     $porcentagemPosi],
        "endossado" =>              ["ENDOSSADO",        strval($empresasTotais["EmprTotalEnd"]),    $porcentagemEndo,  "", $quantNega,     $porcentagemNega],
        "espacos" =>                []
    ];
    for($f = 0; $f < 17; $f++){
        $tabela1["espacos"][] = "";
    }
    

    $arquivo = fopen($csvPainelEmpresa, "w");

    foreach($tabela1 as $row){
        fputcsv($arquivo, $row, ";");
    }

    $tabela2 = [
        "totais" => [
            "Período: De $dataInicio até $dataFim",
            "Status",
            "",
            $empresasTotais["EmprTotalJorPrev"],
            $empresasTotais["EmprTotalJorEfe"],
            $empresasTotais["EmprTotalHE50"],
            $empresasTotais["EmprTotalHE100"],
            $empresasTotais["EmprTotalAdicNot"],
            $empresasTotais["EmprTotalEspInd"],
            $empresasTotais["EmprTotalSaldoAnter"],
            $empresasTotais["EmprTotalSaldoPeriodo"],
            $empresasTotais["EmprTotalSaldoFinal"]
        ],
        "cabecalho" => [
            "Todos os CNPJ",
            "End %",
            "Quantidade de Motoristas",
            "Jornada Prevista",
            "Jornada Efetiva",
            "HE 50%",
            "HE 100%",
            "Adicional Noturno",
            "Espera Indenizada",
            "Saldo Anterior",
            "Saldo Periodo",
            "Saldo Final"
        ]
    ];
    foreach($tabela2 as $row){
        fputcsv($arquivo, $row, ";");
    }

    foreach ($empresaTotais as $empresaTotal) {
        $porcentagemEndoEmpresa = number_format(0,2);
        if ($empresaTotal["endossados"] != 0) {
            $porcentagemEndoEmpresa = number_format(($empresaTotal["endossados"]/$empresaTotal["totalMotorista"])*100, 2);
        }
        $tabela2["conteudo"] = [
            $empresaTotal["empresaNome"],
            $porcentagemEndoEmpresa,
            $empresaTotal["totalMotorista"],
            $empresaTotal["jornadaPrevista"],
            $empresaTotal["JornadaEfetiva"],
            $empresaTotal["he50"],
            $empresaTotal["he100"],
            $empresaTotal["adicionalNoturno"],
            $empresaTotal["esperaIndenizada"],
            $empresaTotal["saldoAnterior"],
            $empresaTotal["saldoPeriodo"],
            $empresaTotal["saldoFinal"]
        ];
        fputcsv($arquivo, $tabela2["conteudo"], ";");
    }

    fclose($arquivo);