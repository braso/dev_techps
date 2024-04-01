<?php
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

include "funcoes_ponto.php";

function criar_relatorio(){

    $mesAtual = date("n");
    $anoAtual = date("Y");
    // Obtém a data de início do mês atual
    $dataTimeInicio = new DateTime('first day of this month');
    $dataInicio= $dataTimeInicio->format('Y-m-d');

    // Obtém a data de fim do mês atual
    $dataTimeFim = new DateTime('last day of this month');
    $dataFim = $dataTimeFim->format('Y-m-d');

    if (empty($dataInicio) || empty($dataFim)) {
        echo '<script>alert("Insira data e empresa para gerar relat贸rio.");</script>';
        index();
        exit;
    }
    
    $empresas = mysqli_fetch_all(
        query("SELECT empr_nb_id, empr_tx_nome FROM `empresa` WHERE empr_tx_status != 'inativo';"),
        MYSQLI_ASSOC
    );
    
    foreach ($empresas as $empresa) {

        $motoristas = mysqli_fetch_all(
            query("SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula  FROM entidade WHERE enti_nb_empresa = $empresa[empr_nb_id] AND enti_tx_status != 'inativo' AND enti_tx_tipo IN ('Motorista', 'Ajudante') "),
            MYSQLI_ASSOC
        );
        

        $rows = [];
        $endossoQuantN = 0;
        $endossoQuantE = 0;
        $endossoQuantEp = 0;
        foreach ($motoristas as $motorista) {
            $endossado = '';

            // Status Endosso{
            $endossos = mysqli_fetch_all(query("SELECT * FROM endosso 
            WHERE endo_tx_status = 'ativo'
            AND (endo_tx_de = '$dataInicio'
            OR endo_tx_ate = '$dataFim')
            AND endo_nb_entidade = $motorista[enti_nb_id]"), MYSQLI_ASSOC);

            switch (count($endossos)) {
                case 1:
                    if (strtotime($dataInicio) == strtotime($endossos[0]["endo_tx_de"]) && strtotime($dataFim) == strtotime($endossos[0]['endo_tx_ate'])) {
                        $endossado = "E";
                        $endossoQuantE += 1;
                    } else if (strtotime($dataFim) != strtotime($endossos[0]['endo_tx_ate'])) {
                        $endossado = "EP";
                        $endossoQuantEp += 1;
                    }
                    break;

                default:
                    $endossado = "N";
                    $endossoQuantN += 1;
                    break;
            }
            // }

            // Jornada Prevista, Jornada Efetiva, HE50%, HE100%, Adicional Noturno, Espera Indenizada{
            $totalJorPrevResut = '00:00';
            $totalJorPrev = '00:00';
            $totalJorEfe = '00:00';
            $totalHE50 = '00:00';
            $totalHE100 = '00:00';
            $totalAdicNot = '00:00';
            $totalEspInd = '00:00';
            $totalSaldoPeriodo = '00:00';
            $saldoFinal = '00:00';
            $diasPonto = [];

            for ($dia = $dataTimeInicio; $dia <= $dataTimeFim; $dia->modify('+1 day')) {
                $dataVez = $dia->format('Y-m-d');
                $diasPonto[] = diaDetalhePonto($motorista['enti_tx_matricula'], $dataVez);
            }
            // }
            // saldoAnterior, saldoPeriodo e saldoFinal{
            $saldoAnterior = mysqli_fetch_all(query("SELECT endo_tx_saldo FROM `endosso`
					WHERE endo_tx_matricula = '" . $motorista['enti_tx_matricula'] . "'
						AND endo_tx_ate < '" . $dataInicio . "'
						AND endo_tx_status = 'ativo'
					ORDER BY endo_tx_ate DESC
					LIMIT 1;"), MYSQLI_ASSOC);

            if (isset($saldoAnterior[0]['endo_tx_saldo'])) {
                $saldoAnterior = $saldoAnterior[0]['endo_tx_saldo'];
            } elseif (!empty($aMotorista['enti_tx_banco'])) {
                $saldoAnterior = $aMotorista['enti_tx_banco'];
                $saldoAnterior = $saldoAnterior[0][0] == '0' && strlen($saldoAnterior) > 5 ? substr($saldoAnterior, 1) : $saldoAnterior;
            } else {
                $saldoAnterior = '00:00';
            }
            // 		}

            foreach ($diasPonto as $diaPonto) {
                if (strlen($diaPonto['diffJornadaEfetiva']) > 5) {
                    $JorPrevHtml = strpos($diaPonto['diffJornadaEfetiva'], "&nbsp;") + 6;
                    $JorPrev = substr($diaPonto['diffJornadaEfetiva'], $JorPrevHtml, 5);
                } else
                    $JorPrev = $diaPonto['diffJornadaEfetiva'];
                $totalJorPrev      = somarHorarios([$totalJorPrev,      $diaPonto['jornadaPrevista']]);
                $totalJorEfe       = somarHorarios([$totalJorEfe,       $JorPrev]);
                $totalHE50         = somarHorarios([$totalHE50,         (empty($diaPonto['he50']) ? '00:00' : $diaPonto['he50'])]);
                $totalHE100        = somarHorarios([$totalHE100,        (empty($diaPonto['he100']) ? '00:00' : $diaPonto['he100'])]);
                $totalAdicNot      = somarHorarios([$totalAdicNot,      $diaPonto['adicionalNoturno']]);
                $totalEspInd       = somarHorarios([$totalEspInd,       $diaPonto['esperaIndenizada']]);
                $totalSaldoPeriodo = somarHorarios([$totalSaldoPeriodo, $diaPonto['diffSaldo']]);
            }


            if ($saldoAnterior != '00:00' && !empty($saldoAnterior)) {
                $saldoFinal = somarHorarios([$saldoAnterior, $totalSaldoPeriodo]);
            } else {
                $saldoFinal = somarHorarios(['00:00', $totalSaldoPeriodo]);
            }

            $rows[] = [
                'motorista' => $motorista['enti_tx_nome'],
                'statusEndosso' => $endossado,
                'jornadaPrevista' => $totalJorPrev,
                'jornadaEfetiva' => $totalJorEfe,
                'he50' => $totalHE50,
                'he100' => $totalHE100,
                'adicionalNoturno' => $totalAdicNot,
                'esperaIndenizada' => $totalEspInd,
                'saldoAnterior' => $saldoAnterior,
                'saldoPeriodo' => $totalSaldoPeriodo,
                'saldoFinal' => $saldoFinal
            ];
        }

        if(!is_dir("./arquivos/paineis/$empresa[empr_nb_id]/$anoAtual-$mesAtual")){
            mkdir("./arquivos/paineis/$empresa[empr_nb_id]/$anoAtual-$mesAtual",0755,true);
        }
        $path = "./arquivos/paineis/$empresa[empr_nb_id]/$anoAtual-$mesAtual/";
        $fileName = 'motoristas.json';
        $jsonArquiMoto = json_encode($rows,JSON_UNESCAPED_UNICODE);
        file_put_contents($path.$fileName, $jsonArquiMoto);

        $totalJorPrevResut = "00:00";
        $totalJorPrev = "00:00";
        $totalJorEfe = "00:00";
        $totalHE50 = "00:00";
        $totalHE100 = "00:00";
        $totalAdicNot = "00:00";
        $totalEspInd = "00:00";
        $totalSaldoPeriodo = "00:00";
        $saldoFinal = '00:00';

        foreach ($rows as $row) {
            $totalJorPrev      = somarHorarios([$totalJorPrev, $row['jornadaPrevista']]);
            $totalJorEfe       = somarHorarios([$totalJorEfe, $row['jornadaEfetiva']]);
            $totalHE50         = somarHorarios([$totalHE50, $row['he50']]);
            $totalHE100        = somarHorarios([$totalHE100, $row['he100']]);
            $totalAdicNot      = somarHorarios([$totalAdicNot, $row['adicionalNoturno']]);
            $totalEspInd       = somarHorarios([$totalEspInd, $row['esperaIndenizada']]);
            $saldoAnterior     = somarHorarios([$saldoAnterior, $row['saldoAnterior']]);
            $totalSaldoPeriodo = somarHorarios([$totalSaldoPeriodo, $row['saldoPeriodo']]);
            $saldoFinal        = somarHorarios([$saldoFinal, $row['saldoFinal']]);
        }

        $totais[] = [
            'empresaNome'      => $empresa['empr_tx_nome'],
            'jornadaPrevista'  => $totalJorPrev,
            'JornadaEfetiva'   => $totalJorEfe,
            'he50'             => $totalHE50,
            'he100'            => $totalHE100,
            'adicionalNoturno' => $totalAdicNot,
            'esperaIndenizada' => $totalEspInd,
            'saldoAnterior'    => $saldoAnterior,
            'saldoPeriodo'     => $totalSaldoPeriodo,
            'saldoFinal'       => $saldoFinal,
            'naoEndossados'    => $endossoQuantN,
            'endossados'       => $endossoQuantE,
            'endossoPacial'    => $endossoQuantEp,
            'totalMotorista'   => count($motoristas)
        ];
        
        if(!is_dir("./arquivos/paineis/$empresa[empr_nb_id]/$anoAtual-$mesAtual")){
            mkdir("./arquivos/paineis/$empresa[empr_nb_id]/$anoAtual-$mesAtual",0755,true);
        }
        $path = "./arquivos/paineis/$empresa[empr_nb_id]/$anoAtual-$mesAtual/";
        $fileName = 'totalMotoristas.json';
        $jsonArquiTotais = json_encode($totais,JSON_UNESCAPED_UNICODE);
        file_put_contents($path.$fileName, $jsonArquiTotais);
            
    }
    
    // $totalJorPrevResut = "00:00";
    $totalJorPrev = '00:00';
    $totalJorEfe = '00:00';
    $totalHE50 = '00:00';
    $totalHE100 = '00:00';
    $totalAdicNot = '00:00';
    $totalEspInd = '00:00';
    $totalSaldoPeriodo = '00:00';
    $toralSaldoAnter = '00:00';
    $saldoFinal = '00:00';
    $totalMotorista = 0;
    $totalNaoEndossados = 0;
    $totalEndossados = 0;
    $totalEndossoPacial = 0;
    
    foreach ($totais as $totalEmpresa) {

        $totalMotorista += $totalEmpresa['totalMotorista'];
    	$totalNaoEndossados += $totalEmpresa['naoEndossados'];
    	$totalEndossados += $totalEmpresa['endossados'];
    	$totalEndossoPacial += $totalEmpresa['endossoPacial'];
    	
        $totalJorPrev           = somarHorarios([$totalEmpresa['jornadaPrevista'],$totalJorPrev]);
        $totalJorEfe            = somarHorarios([$totalJorEfe, $totalEmpresa['jornadaEfetiva']]);
        $totalHE50              = somarHorarios([$totalHE50, $totalEmpresa['he50']]);
        $totalHE100             = somarHorarios([$totalHE100, $totalEmpresa['he100']]);
        $totalAdicNot           = somarHorarios([$totalAdicNot, $totalEmpresa['adicionalNoturno']]);
        $totalEspInd            = somarHorarios([$totalEspInd, $totalEmpresa['esperaIndenizada']]);
        $toralSaldoAnter        = somarHorarios([$toralSaldoAnter, $totalEmpresa['saldoAnterior']]);
        $totalSaldoPeriodo      = somarHorarios([$totalSaldoPeriodo, $totalEmpresa['saldoPeriodo']]);
        $saldoFinal             = somarHorarios([$saldoFinal, $totalEmpresa['saldoFinal']]);
    }
    
    $jsonTotaisEmpr = [
        'EmprTotalJorPrev'      => $totalJorPrev,
        'EmprTotalJorEfe'       => $totalJorEfe,
        'EmprTotalHE50'         => $totalHE50,
        'EmprTotalHE100'        => $totalHE100,
        'EmprTotalAdicNot'      => $totalAdicNot,
        'EmprTotalEspInd'       => $totalEspInd,
        'EmprTotalSaldoAnter'   => $toralSaldoAnter,
        'EmprTotalSaldoPeriodo' => $totalSaldoPeriodo,
        'EmprTotalSaldoFinal'   => $saldoFinal,
        'EmprTotalMotorista'    => $totalMotorista,
        'EmprTotalNaoEnd'       => $totalNaoEndossados,
        'EmprTotalEnd'          => $totalEndossados,
        'EmprTotalEndPac'       => $totalEndossoPacial,
        
    ];

    if(!is_dir("./arquivos/paineis/empresas/$mesAtual-$anoAtual")){
        mkdir("./arquivos/paineis/empresas/$mesAtual-$anoAtual",0755,true);
    }
    $path = "./arquivos/paineis/empresas/$mesAtual-$anoAtual/";
    $fileName = 'empresas.json';
    $jsonArqui = json_encode($jsonTotaisEmpr);
    file_put_contents($path.$fileName, $jsonArqui);
}

function index() {
    // criar_relatorio();
    global $totalResumo, $CONTEX;

    cabecalho('Relatorio Geral de Espelho de Ponto');

    $extraCampoData = '';

    $c = [
        campo_data('Data In閾哻io:', 'busca_dataInicio', ($_POST['busca_dataInicio'] ?? ''), 2, $extraCampoData),
        campo_data('Data Fim:', 'busca_dataFim', ($_POST['busca_dataFim'] ?? ''), 2, $extraCampoData)
    ];

    $b = [
        '<button name="acao" id="criaRelatorio" type="button" onload="disablePrintButton()" class="btn btn-info">Imprimir Relat璐竢io</button>',
    ];

    abre_form('Filtro de Busca');
    linha_form($c);
    fecha_form($b);
    
    $totaisEmpresas = criar_relatorio();
    
    // include_once 'relarorio_geral_empresa_html.php';
?>
    <!--<form name="form_imprimir_relatorio" method="post" target="_blank">-->
    <!--    <input type="hidden" name="acao" value="criar_relatorio">-->
    <!--    <input type="hidden" name="busca_empresa" value="">-->
    <!--    <input type="hidden" name="busca_dataInicio" value="">-->
    <!--    <input type="hidden" name="busca_dataFim" value="">-->
    <!--</form>-->
    <!--<script>-->
    <!--    window.onload = function() {-->
    <!--        document.getElementById('criaRelatorio').onclick = function() {-->
    <!--            var valorEmpresa = document.getElementById('busca_empresa').value-->
    <!--            var valorDataInicio = document.getElementById('busca_dataInicio').value-->
    <!--            var valorDataFim = document.getElementById('busca_dataFim').value-->
    <!--            document.form_imprimir_relatorio.busca_empresa.value = valorEmpresa;-->
    <!--            document.form_imprimir_relatorio.busca_dataInicio.value = valorDataInicio;-->
    <!--            document.form_imprimir_relatorio.busca_dataFim.value = valorDataFim;-->
    <!--            document.form_imprimir_relatorio.submit();-->
    <!--        }-->
    <!--    }-->
    <!--</script>-->

<?php
    // rodape();
}
?>