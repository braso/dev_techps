<?php
    /* Modo debug
		ini_set('display_errors', 1);
		error_reporting(E_ALL);
	//*/

    $mesAtual = date("n");
    $anoAtual = date("Y");
    // Obtém a data de início do mês atual
    $dataTimeInicio = new DateTime('first day of this month');
    $dataInicio= $dataTimeInicio->format('d/m/Y');

    // Obtém a data de fim do mês atual
    $dataTimeFim = new DateTime('last day of this month');
    $dataFim = $dataTimeFim->format('d/m/Y');

    // Obtém O total dos saldos das empresa
    $file = "./arquivos/paineis/$idEmpresa/$anoAtual-$mesAtual/totalMotoristas.json";

    if (file_exists("./arquivos/paineis/$idEmpresa/$anoAtual-$mesAtual")) {
        $conteudo_json = file_get_contents($file);
        $MotoristasTotais = json_decode($conteudo_json,true);
    }


    // Obtém O total dos saldos de cada Motorista
    $fileEmpresas = "./arquivos/paineis/$idEmpresa/$anoAtual-$mesAtual/motoristas.json";
    if (file_exists("./arquivos/paineis/$idEmpresa/$anoAtual-$mesAtual")) {
        $conteudo_json = file_get_contents($fileEmpresas);
        $MotoristaTotais = json_decode($conteudo_json,true);
    }

    // Obtém o tempo da última modificação do arquivo
    $timestamp = filemtime($file);
    $Emissão = date('d/m/Y H:i:s', $timestamp);

    // Calcula a porcentagem
    $porcentagenNaEndo = number_format(0,2);
    $porcentagenEndoPc = number_format(0,2);
    $porcentagenEndo = number_format(0,2);
    if ($MotoristasTotais['naoEndossados'] != 0) {
        $porcentagenNaEndo = number_format(($MotoristasTotais['naoEndossados'] / $MotoristasTotais['totalMotorista']) * 100,2);
    }
    if ($MotoristasTotais['endossoPacial'] != 0) {
        $porcentagenEndoPc = number_format(($MotoristasTotais['endossoPacial']/ $MotoristasTotais['totalMotorista']) * 100, 2) ;
    }
    if ($MotoristasTotais['endossados'] != 0) {
        $porcentagenNaEndo = number_format(($MotoristasTotais['endossados'] / $MotoristasTotais['totalMotorista']) * 100,2);
    }

    $quantPosi = 0;
    $quantNega = 0;
    $quantMeta = 0;

    foreach ($MotoristaTotais as $MotoristaTotal) {
        $saldoFinal = $MotoristaTotal['saldoFinal'];

        if ($saldoFinal == '00:00') {
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
        $porcentagenMeta  = number_format(($quantMeta / count($MotoristaTotais)) * 100, 2);
    }
    if ($quantNega != 0) {
        $porcentagenNega = number_format(($quantNega / count($MotoristaTotais)) * 100, 2);
    }
    if ($quantPosi != 0) {
        $porcentagenPosi = number_format(($quantPosi / count($MotoristaTotais)) * 100, 2);
    }


    if(!is_dir("./arquivos/paineis")){
        mkdir("./arquivos/paineis",0755,true);
    }

    $csvPainelEmpresa = "./arquivos/paineis/Painel_$MotoristasTotais[empresaNome].csv";
    // Cabeçalhos
    $tabela1Cabecalho = ['','QUANT','%','','SALDO FINAL','QUANT','%'];
    $tabela1Ne = ['NÃO ENDOSSADO',"$MotoristasTotais[naoEndossados]","$porcentagenNaEndo",'',"$quantMeta","$porcentagenMeta"];
    $tabela1Ep = ['ENDOSSO PARCIAL',"$MotoristasTotais[endossoPacial]","$porcentagenEndoPc",'',"$quantPosi","$porcentagenPosi"];
    $tabela1E = ['ENDOSSADO',"$MotoristasTotais[endossados]","$porcentagenEndo",'',"$quantNega","$porcentagenNega"];
    $espaco = ['','','','','','','','','','','','','','','','',''];

    $arquivo = fopen($csvPainelEmpresa, 'w');

    fputcsv($arquivo, $tabela1Cabecalho, ';');
    fputcsv($arquivo, $tabela1Ne, ';');
    fputcsv($arquivo, $tabela1Ep, ';');
    fputcsv($arquivo, $tabela1E, ';');
    fputcsv($arquivo, $espaco, ';');

    $tabela2Totais = ["Período: De $dataInicio até $dataFim",'',"$MotoristasTotais[jornadaPrevista]","$MotoristasTotais[JornadaEfetiva]",
    "$MotoristasTotais[he50]","$MotoristasTotais[HE100]","$MotoristasTotais[adicionalNoturno]","$MotoristasTotais[esperaIndenizada]",
    "$MotoristasTotais[saldoAnterior]","$MotoristasTotais[saldoPeriodo]","$MotoristasTotais[saldoFinal]"];
    $tabela2Cabecalho = ["Unidade - $MotoristasTotais[empresaNome]",'Status Endosso','Jornada Prevista','Jornada Efetiva','HE 50%','HE 100%',
    'Adicional Noturno','Espera Indenizada','Saldo Anterior','Saldo Periodo','Saldo Final'];

    fputcsv($arquivo, $tabela2Totais, ';');
    fputcsv($arquivo, $tabela2Cabecalho, ';');

    foreach ($MotoristaTotais as $MotoristaTotal) {
        $tabela2Conteudo = ["$MotoristaTotal[motorista]","$MotoristaTotal[statusEndosso]","$MotoristaTotal[jornadaPrevista]",
        "$MotoristaTotal[jornadaEfetiva]","$MotoristaTotal[he50]","$MotoristaTotal[he100]","$MotoristaTotal[adicionalNoturno]",
        "$MotoristaTotal[esperaIndenizada]","$MotoristaTotal[saldoAnterior]","$MotoristaTotal[saldoPeriodo]","$MotoristaTotal[saldoFinal]"];
        fputcsv($arquivo, $tabela2Conteudo, ';');
    }

    fclose($arquivo);