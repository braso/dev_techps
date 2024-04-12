<?php
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

$mesAtual = date("n");
$anoAtual = date("Y");
// Obtém a data de início do mês atual
$dataTimeInicio = new DateTime('first day of this month');
$dataInicio= $dataTimeInicio->format('d/m/Y');

// Obtém a data de fim do mês atual
$dataTimeFim = new DateTime('last day of this month');
$dataFim = $dataTimeFim->format('d/m/Y');


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

if(!is_dir("./arquivos/paineis")){
    mkdir("./arquivos/paineis",0755,true);
}

$csvPainelEmpresa = "./arquivos/paineis/Painel_Geral.csv";
// Cabeçalhos
$tabela1Cabecalho = ['','QUANT','%'];
$tabela1Ne = ['NÃO ENDOSSADO',"$empresasTotais[EmprTotalNaoEnd]","$porcentagenNaEndo"];
$tabela1Ep = ['ENDOSSO PARCIAL',"$empresasTotais[EmprTotalEndPac]","$porcentagenEndoPc"];
$tabela1E = ['ENDOSSADO',"$empresasTotais[EmprTotalEnd]","$porcentagenEndo"];
$espaco = ['','','','','','','','','','','','','','','','',''];

$arquivo = fopen($csvPainelEmpresa, 'w');

fputcsv($arquivo, $tabela1Cabecalho, ';');
fputcsv($arquivo, $tabela1Ne, ';');
fputcsv($arquivo, $tabela1Ep, ';');
fputcsv($arquivo, $tabela1E, ';');
fputcsv($arquivo, $espaco, ';');

$tabela2Totais = ["PERÍODO: De $dataInicio até $dataFim",'Status','',"$empresasTotais[EmprTotalJorPrev]","$empresasTotais[EmprTotalJorEfe]",
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