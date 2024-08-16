<?php
/* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
    //*/

include "funcoes_ponto.php";

function porcentagemSaldo($total, $meta_endo, $nega_naEndo, $posi_endoPc): array {

    $porcentagen['meta_endo'] = 0;
    $porcentagen['nega_naEndo'] = 0;
    $porcentagen['posi_endoPc'] = 0;

    $porcentagen = [
        'meta_endo' => number_format(0, 2),
        'nega_naEndo' => number_format(0, 2),
        'posi_endoPc' => number_format(0, 2),
    ];

    // var_dump($meta_endo);
    // echo '<br>';
    // var_dump($meta_endo);
    // echo '<br>';
    // var_dump($meta_endo);
    // echo '<br>';
    // var_dump($meta_endo);
    // echo '<br>';
    if ($meta_endo != 0) {
        $porcentagen['meta_endo'] = number_format(($meta_endo / $total) * 100, 2);
    }
    if ($nega_naEndo != 0) {
        $porcentagen['nega_naEndo'] = number_format(($nega_naEndo / $total) * 100, 2);
    }
    if ($posi_endoPc != 0) {
        $porcentagen['posi_endoPc'] = number_format(($posi_endoPc / $total) * 100, 2);
    }

    return $porcentagen;
}

function criar_relatorio_saldo() {
    global $totalResumo;
    $periodoInicio = $_POST["busca_dataInicio"];
    $periodoFim = $_POST["busca_dataFim"];

    $empresas = mysqli_fetch_all(
        query("SELECT empr_nb_id, empr_tx_nome FROM `empresa` WHERE empr_tx_status != 'inativo' ORDER BY empr_tx_nome ASC;"),
        MYSQLI_ASSOC
    );

    foreach ($empresas as $empresa) {

        $motoristas = mysqli_fetch_all(
            query("SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula  FROM entidade WHERE enti_nb_empresa = $empresa[empr_nb_id] AND enti_tx_status != 'inativo' AND enti_tx_ocupacao IN ('Motorista', 'Ajudante') ORDER BY enti_tx_nome ASC"),
            MYSQLI_ASSOC
        );

        $rows = [];
        foreach ($motoristas as $motorista) {
            $endossado = '';
            // Jornada Prevista, Jornada Efetiva, HE50%, HE100%, Adicional Noturno, Espera Indenizada{
            $totalJorPrevResut = "00:00";
            $totalJorPrev = "00:00";
            $totalJorEfe = "00:00";
            $totalHE50 = "00:00";
            $totalHE100 = "00:00";
            $totalAdicNot = "00:00";
            $totalEspInd = "00:00";
            $totalSaldoPeriodo = "00:00";
            $totalSaldofinal = "00:00";
            $saldoAnt = "00:00";

            // saldoAnterior, saldoPeriodo e saldoFinal{
            $saldoAnterior = mysqli_fetch_all(query("SELECT endo_tx_saldo FROM `endosso`
                        WHERE endo_tx_matricula = '" . $motorista["enti_tx_matricula"] . "'
                            AND endo_tx_ate < '" . $periodoInicio . "'
                            AND endo_tx_status = 'ativo'
                        ORDER BY endo_tx_ate DESC
                        LIMIT 1;"), MYSQLI_ASSOC);

            if (!empty($saldoAnterior[0]["endo_tx_saldo"])) {
                $saldoAnterior = $saldoAnterior[0]["endo_tx_saldo"];
            } elseif (!empty($aMotorista["enti_tx_banco"])) {
                $saldoAnterior = $aMotorista["enti_tx_banco"][0][0] == "0" && strlen($aMotorista["enti_tx_banco"]) > 5 ? substr($aMotorista["enti_tx_banco"], 1) : $aMotorista["enti_tx_banco"];
            } else {
                $saldoAnterior = "00:00";
            }
            //}

            $diasPonto = [];
            $dataTimeInicio = new DateTime($periodoInicio);
            $dataTimeFim = new DateTime($periodoFim);
            $mes = $dataTimeInicio->format('m');
            $ano = $dataTimeInicio->format('Y');
            for ($date = $dataTimeInicio; $date <= $dataTimeFim; $date->modify('+1 day')) {
                $dataVez = $date->format('Y-m-d');

                $diasPonto[] = diaDetalhePonto($motorista['enti_tx_matricula'], $dataVez);
            }

            foreach ($diasPonto as $diaPonto) {
                if (strlen($diaPonto['jornadaPrevista']) > 5) {

                    $diaPontoJ = preg_replace('/.*&nbsp;/', '', $diaPonto['jornadaPrevista']);
                    if (preg_match('/(\d{2}:\d{2})$/', $diaPontoJ, $matches)) {
                        $JorPrev = $matches[1];
                    }
                } else {
                    $JorPrev = $diaPonto['jornadaPrevista'];
                }

                if (strlen($diaPonto['diffJornadaEfetiva']) > 5) {

                    $diaPontojP = preg_replace('/.*&nbsp;/', '', $diaPonto['diffJornadaEfetiva']);
                    if (preg_match('/(\d{2}:\d{2})$/', $diaPontojP, $matches)) {
                        $JorEfet = $matches[1];
                    }
                } else {
                    $JorEfet = $diaPonto['diffJornadaEfetiva'];
                }

                $he50 = empty($diaPonto['he50']) ? '00:00' : $diaPonto['he50'];
                $he100 = empty($diaPonto['he100']) ? '00:00' : $diaPonto['he100'];
                $adicNot = $diaPonto['adicionalNoturno'];
                $espInd  = $diaPonto['esperaIndenizada'];
                $saldoPer = strip_tags($diaPonto['diffSaldo']);

                $totalJorPrev      = somarHorarios([$totalJorPrev,      $JorPrev]);
                $totalJorEfe       = somarHorarios([$totalJorEfe,       $JorEfet]);
                $totalHE50         = somarHorarios([$totalHE50,         $he50]);
                $totalHE100        = somarHorarios([$totalHE100,        $he100]);
                $totalAdicNot      = somarHorarios([$totalAdicNot,      $adicNot]);
                $totalEspInd       = somarHorarios([$totalEspInd,       $espInd]);
                $totalSaldoPeriodo = somarHorarios([$totalSaldoPeriodo, $saldoPer]);
                $totalSaldofinal   = somarHorarios([$saldoAnterior, $totalSaldoPeriodo]);
            }

            $rows[] = [
                'IdMotorista' => $motorista['enti_nb_id'],
                'matricula' => $motorista['enti_tx_matricula'],
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
                'saldoFinal' => $totalSaldofinal
            ];
        }
        if (!is_dir("./arquivos/paineis/saldos/$empresa[empr_nb_id]/$mes-$ano")) {
            mkdir("./arquivos/paineis/saldos/$empresa[empr_nb_id]/$mes-$ano", 0755, true);
        }
        $path = "./arquivos/paineis/saldos/$empresa[empr_nb_id]/$mes-$ano/";
        $fileName = 'motoristas.json';
        $jsonArquiMoto = json_encode($rows, JSON_UNESCAPED_UNICODE);
        file_put_contents($path . $fileName, $jsonArquiMoto);

        $totalJorPrevResut = "00:00";
        $totalJorPrev = "00:00";
        $totalJorEfe = "00:00";
        $totalHE50 = "00:00";
        $totalHE100 = "00:00";
        $totalAdicNot = "00:00";
        $totalEspInd = "00:00";
        $totalSaldoPeriodo = "00:00";
        $saldoFinal = "00:00";

        foreach ($rows as $row) {
            $totalJorPrev      = somarHorarios([$totalJorPrev, $row["jornadaPrevista"]]);
            $totalJorEfe       = somarHorarios([$totalJorEfe, $row["jornadaEfetiva"]]);
            $totalHE50         = somarHorarios([$totalHE50, $row["he50"]]);
            $totalHE100        = somarHorarios([$totalHE100, $row["he100"]]);
            $totalAdicNot      = somarHorarios([$totalAdicNot, $row["adicionalNoturno"]]);
            $totalEspInd       = somarHorarios([$totalEspInd, $row["esperaIndenizada"]]);
            $saldoAnterior     = somarHorarios([$saldoAnterior, $row["saldoAnterior"]]);
            $totalSaldoPeriodo = somarHorarios([$totalSaldoPeriodo, $row["saldoPeriodo"]]);
            $saldoFinal        = somarHorarios([$saldoFinal, $row["saldoFinal"]]);
        }

        $totais[] = [
            "empresaId"        => $empresa["empr_nb_id"],
            "empresaNome"      => $empresa["empr_tx_nome"],
            "jornadaPrevista"  => $totalJorPrev,
            "JornadaEfetiva"   => $totalJorEfe,
            "he50"             => $totalHE50,
            "he100"            => $totalHE100,
            "adicionalNoturno" => $totalAdicNot,
            "esperaIndenizada" => $totalEspInd,
            "saldoAnterior"    => $saldoAnterior,
            "saldoPeriodo"     => $totalSaldoPeriodo,
            "saldoFinal"       => $saldoFinal,
            "totalMotorista"   => count($motoristas)
        ];

        $totaisJson = [
            'empresaId'        => $empresa['empr_nb_id'],
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
            'totalMotorista'   => count($motoristas)
        ];

        if (!is_dir("./arquivos/paineis/saldos/$empresa[empr_nb_id]/$mes-$ano")) {
            mkdir("./arquivos/paineis/saldos/$empresa[empr_nb_id]/$mes-$ano", 0755, true);
        }
        $path = "./arquivos/paineis/saldos/$empresa[empr_nb_id]/$mes-$ano/";
        $fileName = 'totalMotoristas.json';
        $jsonArquiTotais = json_encode($totaisJson, JSON_UNESCAPED_UNICODE);
        file_put_contents($path . $fileName, $jsonArquiTotais);
    }
    if (!is_dir("./arquivos/paineis/saldos/empresas/$mes-$ano")) {
        mkdir("./arquivos/paineis/saldos/empresas/$mes-$ano", 0755, true);
    }
    $path = "./arquivos/paineis/saldos/empresas/$mes-$ano/";
    $fileName = 'totalEmpresas.json';
    $jsonArquiTotais = json_encode($totais, JSON_UNESCAPED_UNICODE);
    file_put_contents($path . $fileName, $jsonArquiTotais);

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

    foreach ($totais as $totalEmpresa) {

        $totalMotorista += $totalEmpresa['totalMotorista'];

        $totalJorPrev           = somarHorarios([$totalEmpresa['jornadaPrevista'], $totalJorPrev]);
        $totalJorEfe            = somarHorarios([$totalJorEfe, $totalEmpresa['JornadaEfetiva']]);
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
        'EmprTotalMotorista'    => $totalMotorista
    ];


    if (!is_dir("./arquivos/paineis/saldos/empresas/$mes-$ano")) {
        mkdir("./arquivos/paineis/saldos/empresas/$mes-$ano", 0755, true);
    }
    $path = "./arquivos/paineis/saldos/empresas/$mes-$ano/";
    $fileName = 'empresas.json';
    $jsonArqui = json_encode($jsonTotaisEmpr);
    file_put_contents($path . $fileName, $jsonArqui);
    return;
}

function index() {
    global $totalResumo, $CONTEX;

    if (array_key_exists('atualizar', $_POST) && !empty($_POST['atualizar'])) {
        echo '<script>alert("Atualizando os painéis, aguarde um pouco ")</script>';
        ob_flush();
        flush();
        criar_relatorio_saldo();
    }

    cabecalho('Relatorio Geral de saldo');

    $extraCampoData = '';
    if (empty($_POST['busca_dataInicio'])) {
        $_POST['busca_dataInicio'] = date("Y-m-01");
    }
    if (empty($_POST['busca_dataFim'])) {
        $_POST['busca_dataFim'] = date("Y-m-d");
    }

    // $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
    //position: absolute; top: 101px; left: 420px;
    $c = [
        combo_net('Empresa:', 'empresa', $_POST['empresa'] ?? '', 4, 'empresa', ''),
        campo_data('Data Início', 'busca_dataInicio', ($_POST['busca_dataInicio'] ?? ""), 2, $extraCampoData),
        campo_data('Data Fim', 'busca_dataFim', ($_POST['busca_dataFim'] ?? ''), 2, $extraCampoData)
        // $texto,
    ];

    $botao_imprimir =
        '<button class="btn default" type="button" onclick="imprimir()">Imprimir</button >
                    <script>
                        function imprimir() {
                            // Abrir a caixa de diálogo de impressão
                            window.print();
                        }
                    </script>';
    if (!empty($_SESSION['user_tx_nivel']) && is_int(strpos($_SESSION['user_tx_nivel'], 'Administrador'))) {
        $botaoAtualizarPainel =
            '<a class="btn btn-warning" onclick="atualizarPainel()"> Atualizar Painel </a>';
    }

    if (!empty($_POST['empresa'])) {
        $botao_volta = "<button class='btn default' type='button' onclick='setAndSubmit(\"\")'>Voltar</button>";
    }

    $b = [
        botao("Buscar", 'index', '', '', '', '', 'btn btn-info'),
        $botao_imprimir,
        $botao_volta,
        $botaoAtualizarPainel
    ];


    abre_form('Filtro de Busca');
    linha_form($c);
    fecha_form($b);

    $dataTimeInicio = new DateTime($_POST['busca_dataInicio']);
    $dataTimeFim = new DateTime($_POST['busca_dataFim']);
    $mes = $dataTimeInicio->format('m');
    $ano = $dataTimeInicio->format('Y');

    if (!empty($_POST['empresa']) && !empty($_POST['busca_dataInicio']) && !empty($_POST['busca_dataFim'])) {
        $idEmpresa = $_POST['empresa'];
        $aEmpresa = mysqli_fetch_all(query("SELECT empr_tx_logo FROM `empresa` WHERE empr_tx_Ehmatriz = 'sim' AND empr_nb_id = $idEmpresa"), MYSQLI_ASSOC);

        $empresas = [];
        $empresasTotais = [];
        $emissao = [];

        $pasta = "./arquivos/paineis/saldos/$idEmpresa/$mes-$ano";

        if (is_dir($pasta) != false) {
            $file = "$pasta/motoristas.json";
            if (file_exists($file)) {
                $conteudo_json = file_get_contents($file);
                $motoristas = json_decode($conteudo_json, true);
            }

            // Obtém O total dos saldos de cada empresa 
            if (file_exists("$pasta/totalMotoristas.json")) {
                $conteudo_json = file_get_contents("$pasta/totalMotoristas.json");
                $motoristasTotais = json_decode($conteudo_json, true);
            }



            // Obtém o tempo da última modificação do arquivo
            $timestamp = '';
            $timestamp = filemtime($pasta . '/motoristas.json');
            if (filemtime($pasta . '/totalMotoristas.json') == filemtime($pasta . '/motoristas.json')) {
                $Emissão = date('d/m/Y H:i:s', $timestamp);
            }
        } else
            echo '<script>alert("Não Possui dados desse mês")</script>';

        $quantPosi = 0;
        $quantNega = 0;
        $quantMeta = 0;

        foreach ($motoristas as $motoristasTotal) {
            $saldoFinal = $motoristasTotal['saldoFinal'];

            if ($saldoFinal === '00:00') {
                $quantMeta++;
            } elseif ($saldoFinal > '00:00') {
                $quantPosi++;
            } elseif ($saldoFinal < '00:00') {
                $quantNega++;
            }
        }

        $perfomace = porcentagemSaldo(count($motoristas), $quantMeta, $quantNega, $quantPosi);

        include_once "painel_saldo_html.php";
    } else {
        $empresas = [];
        $empresasTotais = [];
        $emissao = '';
        $aEmpresa = mysqli_fetch_all(query("SELECT empr_tx_logo FROM `empresa` WHERE empr_tx_Ehmatriz = 'sim'"), MYSQLI_ASSOC);
        $pasta = "./arquivos/paineis/saldos/empresas/$mes-$ano";

        if (is_dir($pasta) != false) {
            $file = "$pasta/empresas.json";
            if (file_exists($file)) {
                $conteudo_json = file_get_contents($file);
                $empresas = json_decode($conteudo_json, true);
            }

            // Obtém O total dos saldos de cada empresa
            $fileEmpresas = "$pasta/totalEmpresas.json";

            if (file_exists($fileEmpresas)) {
                $conteudo_json = file_get_contents($fileEmpresas);
                $empresasTotais = json_decode($conteudo_json, true);
            }

            // Obtém o tempo da última modificação do arquivo
            $timestamp = '';
            $timestamp = filemtime($pasta . '/empresas.json');
            if (filemtime($pasta . '/totalEmpresas.json') == filemtime($pasta . '/empresas.json')) {
                $Emissão = date('d/m/Y H:i:s', $timestamp);
            }
        } else
            echo '<script>alert("Não Possui dados desse mês")</script>';

        $quantPosi = 0;
        $quantNega = 0;
        $quantMeta = 0;
        foreach ($empresasTotais as $empresaTotal) {
            $saldoFinal = $empresaTotal["saldoFinal"];

            if ($saldoFinal === "00:00") {
                $quantMeta++;
            } elseif ($saldoFinal > "00:00") {
                $quantPosi++;
            } elseif ($saldoFinal < "00:00") {
                $quantNega++;
            }
        }

        $perfomace = porcentagemSaldo(count($empresasTotais), $quantMeta, $quantNega, $quantPosi);

        include_once "painel_saldo_html.php";
    }
?>
    <style>
        @media print {
            body {
                margin: 1cm;
                margin-right: 0cm;
                /* Ajuste o valor conforme necessário para afastar do lado direito */
                transform: scale(1.0);
                transform-origin: top left;
            }

            @page {
                size: A4 landscape;
                margin: 1cm;
            }

            #tituloRelatorio {
                /*font-size: 2px !important;*/
                /*padding-left: 200px;*/
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: -50px !important;
            }

            div:nth-child(6)>div.portlet.light,
            .scroll-to-top  {
                display: none !important;
            }

            #pdf2htmldiv>div {
                padding: 88px 20px 15px !important;
            }

            /* .portlet.light>.portlet-title {
                border-bottom: none;
                margin-bottom: 0px;
            } */

            .caption {
                padding-top: 0px;
                margin-left: -50px !important;
                padding-bottom: 0px;
            }

            .emissao {
                padding-left: 680px !important;
            }

            .porcentagenEndo {
                box-shadow: 0 0 0 1000px #66b3ff inset !important;
            }

            .porcentagenNaEndo {
                box-shadow: 0 0 0 1000px #ff471a inset !important;
            }

            .porcentagenEndoPc {
                box-shadow: 0 0 0 1000px #ffff66 inset !important;
            }

            thead tr.totais th {
                box-shadow: 0 0 0 1000px #ffe699 inset !important;
                /* Cor para impressão */
            }

            thead tr.titulos th {
                box-shadow: 0 0 0 1000px #99ccff inset !important;
                /* Cor para impressão */
            }

            .porcentagenMeta {
                box-shadow: 0 0 0 1000px #66b3ff inset !important;
            }

            .porcentagenPosit {
                box-shadow: 0 0 0 1000px #00b33c inset !important;
            }

            .porcentagenNegat {
                box-shadow: 0 0 0 1000px #ff471a inset !important;
            }

            .portlet.light {
                padding: 75px 20px 15px !important;
            }

            #impressao {
                display: block !important;
                position: relative;
                padding-left: 630px!important;
            }
        }

        table thead tr th:nth-child(3),
        table thead tr th:nth-child(7),
        table thead tr th:nth-child(11),
        table td:nth-child(3),
        table td:nth-child(7),
        table td:nth-child(11) {
            border-right: 3px solid #d8e4ef !important;
        }

        .th-align {
            text-align: center;
            /* Define o alinhamento horizontal desejado, pode ser center, left ou right */
            vertical-align: middle !important;
            /* Define o alinhamento vertical desejado, pode ser top, middle ou bottom */

        }

        .emissao {
            text-align: left;
            padding-left: 63%;
            position: absolute;
        }
    </style>
    <form name="myForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <input type="hidden" name="empresa" id="empresa">
        <input type="hidden" name="busca_dataInicio" id="busca_dataInicio">
        <input type="hidden" name="busca_dataFim" id="busca_dataFim">
    </form>
    <form name="formularioAtualizarPainel" method="POST" action="<?= htmlspecialchars(basename($_SERVER["PHP_SELF"])); ?>">
        <input type="hidden" name="atualizar" id="atualizar">
        <input type="hidden" name="busca_dataInicio" id="busca_dataInicio">
        <input type="hidden" name="busca_dataFim" id="busca_dataFim">
    </form>

    <script>
        function setAndSubmit(empresa) {
            document.myForm.empresa.value = empresa;
            document.formularioAtualizarPainel.busca_dataInicio.value = document.getElementById("busca_dataInicio").value;
            document.formularioAtualizarPainel.busca_dataFim.value = document.getElementById("busca_dataFim").value;
            document.myForm.submit();
        }

        function atualizarPainel() {
            document.formularioAtualizarPainel.busca_dataInicio.value = document.getElementById("busca_dataInicio").value;
            document.formularioAtualizarPainel.busca_dataFim.value = document.getElementById("busca_dataFim").value;
            document.formularioAtualizarPainel.atualizar.value = "atualizar";
            document.formularioAtualizarPainel.submit();
        }
    </script>

<?php

    rodape();
}
?>