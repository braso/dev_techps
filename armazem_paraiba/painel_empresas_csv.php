<?php
    /* Modo debug
		ini_set('display_errors', 1);
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
    $Emissão = date('d/m/Y H:i:s', $timestamp);

    // Calcula a porcentagem
    $porcentagenNaEndo = number_format(0,2);
    $porcentagenEndoPc = number_format(0,2);
    $porcentagenEndo = number_format(0,2);
    if ($empresasTotais['EmprTotalNaoEnd'] != 0) {
        $porcentagenNaEndo = number_format(($empresasTotais['EmprTotalNaoEnd'] / $empresasTotais['EmprTotalMotorista']) * 100, 2);
    }
    if ($empresasTotais['EmprTotalEndPac'] != 0) {
        $porcentagenEndoPc = number_format(($empresasTotais['EmprTotalEndPac'] / $empresasTotais['EmprTotalMotorista']) * 100, 2);
    }
    if ($empresasTotais['EmprTotalEnd'] != 0) {
        $porcentagenEndo = number_format(($empresasTotais['EmprTotalEnd'] / $empresasTotais['EmprTotalMotorista']) * 100, 2);
    }

    $quantPosi = 0;
    $quantNega = 0;
    $quantMeta = 0;

    foreach ($empresaTotais as $empresaTotal) {
        $saldoFinal = $empresaTotal['saldoFinal'];

        if ($saldoFinal === '00:00') {
            $quantMeta++;
        } elseif ($saldoFinal > '00:00') {
            $quantPosi++;
        } elseif ($saldoFinal < '00:00') {
            $quantNega++;
        }
    }

    $porcentagenMeta = number_format(0,2);
    $porcentagenNega = number_format(0,2);
    $porcentagenPosi = number_format(0,2);

    if ($quantMeta != 0) {
        $porcentagenMeta  = number_format(($quantMeta / count($empresaTotais)) * 100, 2);
    }
    if ($quantNega != 0) {
        $porcentagenNega = number_format(($quantNega / count($empresaTotais)) * 100, 2);
    }
    if ($quantPosi != 0) {
        $porcentagenPosi = number_format(($quantPosi / count($empresaTotais)) * 100, 2);
    }

    if(!is_dir("./arquivos/paineis")){
        mkdir("./arquivos/paineis",0755,true);
    }

    $csvPainelEmpresa = "./arquivos/paineis/Painel_Geral.csv";
    // Cabeçalhos
    $tabela1Cabecalho = ['','QUANT','%','',"SALDO FINAL",'QUANT','%'];
    $tabela1Ne = ['NÃO ENDOSSADO',"$empresasTotais[EmprTotalNaoEnd]","$porcentagenNaEndo",'',"$quantMeta","$porcentagenMeta"];
    $tabela1Ep = ['ENDOSSO PARCIAL',"$empresasTotais[EmprTotalEndPac]","$porcentagenEndoPc",'',"$quantPosi","$porcentagenPosi"];
    $tabela1E = ['ENDOSSADO',"$empresasTotais[EmprTotalEnd]","$porcentagenEndo",'',"$quantNega","$porcentagenNega"];
    $espaco = ['','','','','','','','','','','','','','','','',''];

    $arquivo = fopen($csvPainelEmpresa, 'w');

    fputcsv($arquivo, $tabela1Cabecalho, ';');
    fputcsv($arquivo, $tabela1Ne, ';');
    fputcsv($arquivo, $tabela1Ep, ';');
    fputcsv($arquivo, $tabela1E, ';');
    fputcsv($arquivo, $espaco, ';');

    $tabela2Totais = ["Período: De $dataInicio até $dataFim",'Status','',"$empresasTotais[EmprTotalJorPrev]","$empresasTotais[EmprTotalJorEfe]",
    "$empresasTotais[EmprTotalHE50]","$empresasTotais[EmprTotalHE100]","$empresasTotais[EmprTotalAdicNot]","$empresasTotais[EmprTotalEspInd]",
    "$empresasTotais[EmprTotalSaldoAnter]","$empresasTotais[EmprTotalSaldoPeriodo]","$empresasTotais[EmprTotalSaldoFinal]"];
    $tabela2Cabecalho = ["Todos os CNPJ",'End %','Quantidade de Motoristas','Jornada Prevista','Jornada Efetiva','HE 50%','HE 100%',
    'Adicional Noturno','Espera Indenizada','Saldo Anterior','Saldo Periodo','Saldo Final'];

    fputcsv($arquivo, $tabela2Totais, ';');
    fputcsv($arquivo, $tabela2Cabecalho, ';');

    foreach ($empresaTotais as $empresaTotal) {
        $porcentagenEndoEmpresa = number_format(0,2);
        if ($empresaTotal['endossados'] != 0) {
            $porcentagenEndoEmpresa = number_format(($empresaTotal['endossados'] / $empresaTotal['totalMotorista']) * 100,2);
        }
        $tabela2Conteudo = ["$empresaTotal[empresaNome]","$porcentagenEndoEmpresa","$empresaTotal[totalMotorista]","$empresaTotal[jornadaPrevista]",
        "$empresaTotal[JornadaEfetiva]","$empresaTotal[he50]","$empresaTotal[he100]","$empresaTotal[adicionalNoturno]",
        "$empresaTotal[esperaIndenizada]","$empresaTotal[saldoAnterior]","$empresaTotal[saldoPeriodo]","$empresaTotal[saldoFinal]"];
        fputcsv($arquivo, $tabela2Conteudo, ';');
    }

    fclose($arquivo);


?>