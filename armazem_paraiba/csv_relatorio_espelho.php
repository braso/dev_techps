<?php
    /* Modo debug
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	//*/
		
    // Nome do arquivo CSV
    if (!is_dir($_SERVER["DOCUMENT_ROOT"].$CONTEX["path"]."/arquivos/endosso_csv/".$aEmpresa["empr_nb_id"]."/".$aMotorista["enti_nb_id"])) {
        mkdir($_SERVER["DOCUMENT_ROOT"].$CONTEX["path"]."/arquivos/endosso_csv/".$aEmpresa["empr_nb_id"]."/".$aMotorista["enti_nb_id"], 0755, true);
    }

    $nomeArquivoCaminho = $_SERVER["DOCUMENT_ROOT"].$CONTEX["path"]."/arquivos/endosso_csv/".$aEmpresa["empr_nb_id"]."/".$aMotorista["enti_nb_id"]."/espelho-de-ponto.csv";

    // Cabeçalhos
    $cabecalhos = [
        [
            "Empresa:", $aEmpresa["empr_tx_nome"], "",
            "CNPJ:",$aEmpresa["empr_tx_cnpj"],"",
            "End.",$enderecoEmpresa.",".$aEmpresa["cida_tx_nome"]."/".$aEmpresa["cida_tx_uf"].", ".$aEmpresa["empr_tx_cep"],"",
            "Período:",date("d/m/Y", strtotime($endossoCompleto["endo_tx_de"])).date("d/m/Y", strtotime($endossoCompleto["endo_tx_ate"])),""
            ,"Emissão Doc.:",date("d/m/Y H:i:s", strtotime($endossoCompleto["endo_tx_dataCadastro"])). " (UTC-3)"
        ],
        ["Nome:",$aMotorista["enti_tx_nome"],"",
        "Função:",$aMotorista["enti_tx_ocupacao"],"",
        "Turno:","D.SEM/H: ".$aMotorista["enti_tx_jornadaSemanal"],"",
        "Matrícula:",$aMotorista["enti_tx_matricula"],"",
        "Admissão:",data($aMotorista["enti_tx_admissao"])],
        ["","","",
        "","","",
        "","","",
        "","","",
        "","","",
        "",""],
        ["DATA","DIA","INÍCIO","INÍCIO REF.","FIM REF","FIM","REFEIÇÃO","ESPERA","DESCANSO","REPOUSO","PREVISTA","EFETIVA","MDC","INTERSTÍCIO","HE 50%","HE 100%","ADICIONAL NOT.","ESPERA IND.","MOTIVO","SALDO"]
    ];

    // Abre o arquivo para escrita (ou cria se não existir)
    $arquivo = fopen($nomeArquivoCaminho, "w");

    // Adiciona os cabeçalhos
    fputcsv($arquivo, $cabecalhos[0], ";");
    fputcsv($arquivo, $cabecalhos[1], ";");
    fputcsv($arquivo, $cabecalhos[2], ";");
    fputcsv($arquivo, $cabecalhos[3], ";");

    // Adiciona os dados ao CSV
    foreach ($aDia as $aDiaVez) {
        $linha = [];
        for ($j = 0; $j < 20; $j++){
            // $conteudo = strip_tags($aDiaVez[$j]);
            // $linha[] = str_replace("&nbsp;",$conteudo,$conteudo);
            $linha[] = strip_tags(html_entity_decode($aDiaVez[$j],ENT_QUOTES | ENT_HTML5,"UTF-8"));
        }

        fputcsv($arquivo, $linha,";");
    }

    $totalDias = ["TOTAL:","$qtdDiasEndossados dias","","","","","","","","","","","","","","",""];
    $tabelaInfo = ["Carga Horaria Prevista:","{$totalResumo["jornadaPrevista"]}","","Legendas","","Saldo Anterior:","{$totalResumo["saldoAnterior"]}","Saldo Período:","{$totalResumo["diffSaldo"]}","Saldo Bruto:","{$totalResumo["saldoBruto"]}","","","","","",""];
    $tabelaInfo1 = ["Carga Horaria Efetiva Realizada:","$totalResumo[diffJornadaEfetiva]","","I","Incluída Manualmente","","","","","","","","","","","",""];
    $tabelaInfo2 = ["Adicional Noturno:","$totalResumo[adicionalNoturno]","","P","Pré-Assinalada","","","","","","","","","","","",""];
    $tabelaInfo3 = ["Espera Indenizada:","$totalResumo[esperaIndenizada]","","T","Outras fontes de marcação","","","","","","","","","","","",""];
    $tabelaInfo4 = ["","","","DSR","Descanso Semanal Remunerado e Abono","","","","","","","","","","","",""];
    $tabelaInfo5 = ["Horas Extras (50%) - a pagar:","$totalResumo[he50]","","*","Registros excluídos manualmente","","","","","","","","","","","",""];
    $tabelaInfo6 = ["Horas Extras (100%) - a pagar:","$totalResumo[he100]","","D+1","Jornada terminada nos dias seguintes","","","","","","","","","","","","","Impressão Doc.:",date("d/m/Y \T H:i:s")."(UTC-3)"];

    fputcsv($arquivo, $totalDias, ";");
    fputcsv($arquivo, $tabelaInfo, ";");
    fputcsv($arquivo, $tabelaInfo1, ";");
    fputcsv($arquivo, $tabelaInfo2, ";");
    fputcsv($arquivo, $tabelaInfo3, ";");
    fputcsv($arquivo, $tabelaInfo4, ";");
    fputcsv($arquivo, $tabelaInfo5, ";");
    fputcsv($arquivo, $tabelaInfo6, ";");
    // Fecha o arquivo
    fclose($arquivo);
