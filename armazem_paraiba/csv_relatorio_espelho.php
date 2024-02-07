<?php

header('Content-Type:text/html; charset=UTF-8');
// Nome do arquivo CSV
if (!is_dir("$_SERVER[DOCUMENT_ROOT].($CONTEX[path]).'/arquivos/endosso_csv")) {
    mkdir("$_SERVER[DOCUMENT_ROOT].($CONTEX[path]).'/arquivos/endosso_csv", 0755, true);
}

$nomeArquivoCaminho = $_SERVER['DOCUMENT_ROOT'].($CONTEX['path']).'/arquivos/endosso_csv/espelho-de-ponto.csv';

// Cabeçalhos
$cabecalho1 = ['Empresa:', "$aEmpresa[empr_tx_nome]", '','CNPJ:',"$aEmpresa[empr_tx_cnpj]",'','End.',"$enderecoEmpresa,$aEmpresa[cida_tx_nome]/$aEmpresa[cida_tx_uf], $aEmpresa[empr_tx_cep]",'','Período:',date("d/m/Y", strtotime($endossoCompleto['endo_tx_de'])).''.date("d/m/Y", strtotime($endossoCompleto['endo_tx_ate'])),'','Emissão Doc.:',date("d/m/Y H:i:s", strtotime($endossoCompleto['endo_tx_dataCadastro'])). " (UTC-3)"];
$cabecalho2 = ['Motorista:',"$aMotorista[enti_tx_nome]",'','Função:',"$aMotorista[enti_tx_ocupacao]",'','Turno:',"D.SEM/H: $aMotorista[enti_tx_jornadaSemanal]",'','Matrícula:',"$aMotorista[enti_tx_matricula]",'','Admissão:',data($aMotorista['enti_tx_admissao'])];
$cabecalho3 = ['','','','','','','','','','','','','','','','',''];
$cabecalho4 = ['DATA','DIA','INÍCIO','INÍCIO REF.','FIM REF','FIM','REFEIÇÃO','ESPERA','DESCANSO','REPOUSO','PREVISTA','EFETIVA','MDC','INTERSTÍCIO','HE 50%','HE 100%','ADICIONAL NOT.','ESPERA IND.','MOTIVO','SALDO'];

// Abre o arquivo para escrita (ou cria se não existir)
$arquivo = fopen($nomeArquivoCaminho, 'w');

// Adiciona os cabeçalhos
fputcsv($arquivo, $cabecalho1);
fputcsv($arquivo, $cabecalho2);
fputcsv($arquivo, $cabecalho3);
fputcsv($arquivo, $cabecalho4);

// Adiciona os dados ao CSV
foreach ($aDia as $aDiaVez) {
    $linha =[];
    for ($j = 0; $j < 20; $j++){
        $linha[] = strip_tags(html_entity_decode($aDiaVez[$j],ENT_QUOTES | ENT_HTML5,'UTF-8'));
    }

    fputcsv($arquivo, $linha);
}

// Fecha o arquivo
fclose($arquivo);

// echo "Arquivo CSV gerado com sucesso!";
?>
