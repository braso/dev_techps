<?php
    //* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
    //*/

    header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
	header("Pragma: no-cache"); // HTTP 1.0.
	header("Expires: 0");

    include "../funcoes_ponto.php";

    function calcPercs($total, int $meta_endo, int $nega_naEndo, int $posi_endoPc): array {
        $porcentagens = [
            "meta_endo"     => number_format(($meta_endo / $total)*100, 2),
            "nega_naEndo"   => number_format(($nega_naEndo / $total)*100, 2),
            "posi_endoPc"   => number_format(($posi_endoPc / $total)*100, 2),
        ];
        
        return $porcentagens;
    }

    function criar_relatorio_saldo(){
        global $totalResumo;
        $periodoInicio = $_POST["busca_dataInicio"];
        $periodoFim = $_POST["busca_dataFim"];

        $empresas = mysqli_fetch_all(query(
            "SELECT empr_nb_id, empr_tx_nome FROM empresa"
            ." WHERE empr_tx_status = 'ativo'"
            .(!empty($_POST["empresa"])? " AND empr_nb_id = ".$_POST["empresa"]: "")
            ." ORDER BY empr_tx_nome ASC;"
        ),MYSQLI_ASSOC);

        foreach ($empresas as $empresa){
            $motoristas = mysqli_fetch_all(query(
                "SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula FROM entidade"
                ." WHERE enti_tx_status = 'ativo'"
                    ." AND enti_nb_empresa = ".$empresa["empr_nb_id"]
                    ." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante')"
                ." ORDER BY enti_tx_nome ASC"
            ), MYSQLI_ASSOC);

            $rows = [];
            foreach ($motoristas as $motorista) {
                $diasPonto = [];
                $dataInicio = new DateTime($periodoInicio);
                $dataFim = new DateTime($periodoFim);
                $mes = $dataInicio->format("m");
                $ano = $dataInicio->format("Y");
                $endossado = "";

                // Jornada Prevista, Jornada Efetiva, HE50%, HE100%, Adicional Noturno, Espera Indenizada{
                    [
                        $totalJorPrev, $totalJorEfe,
                        $totalHE50, $totalHE100,
                        $totalAdicNot, $totalEspInd,
                        $totalSaldoPeriodo, $totalSaldofinal
                    ] = array_pad([], 8, "00:00");
                //}
                
                // saldoAnterior, saldoPeriodo e saldoFinal{
                    $saldoAnterior = mysqli_fetch_all(query(
                        "SELECT endo_tx_saldo FROM `endosso`"
                        ." WHERE endo_tx_status = 'ativo'"
                            ." AND endo_tx_matricula = '".$motorista["enti_tx_matricula"]."'"
                            ." AND endo_tx_ate < '".$periodoInicio."'"
                        ." ORDER BY endo_tx_ate DESC"
                        ." LIMIT 1;"
                    ), MYSQLI_ASSOC);

                    if (!empty($saldoAnterior[0]["endo_tx_saldo"])) {
                        $saldoAnterior = $saldoAnterior[0]["endo_tx_saldo"];
                    } elseif (!empty($aMotorista["enti_tx_banco"])) {
                        $saldoAnterior = $aMotorista["enti_tx_banco"][0][0] == "0" && strlen($aMotorista["enti_tx_banco"]) > 5 ? substr($aMotorista["enti_tx_banco"], 1) : $aMotorista["enti_tx_banco"];
                    }else{
                        $saldoAnterior = "00:00";
                    }
                //}
                
                for ($date = $dataInicio; $date <= $dataFim; $date->modify("+1 day")) {
                    $dataVez = $date->format("Y-m-d");

                    $diasPonto[] = diaDetalhePonto($motorista["enti_tx_matricula"], $dataVez);
                }

                foreach ($diasPonto as $diaPonto) {
                    $diaPonto["he50"]  = (empty($diaPonto["he50"])? "00:00": $diaPonto["he50"]);
                    $diaPonto["he100"] = (empty($diaPonto["he100"])? "00:00": $diaPonto["he100"]);
                    $JorPrev = "00:00";
                    if (strlen($diaPonto["jornadaPrevista"]) > 5) {

                        $diaPontoJ = preg_replace("/.*&nbsp;/", "", $diaPonto["jornadaPrevista"]);
                        if (preg_match("/(\d{2}:\d{2})$/", $diaPontoJ, $matches)) {
                            $JorPrev = $matches[1];
                        }
                    } else {
                        $JorPrev = $diaPonto["jornadaPrevista"];
                    }

                    if (strlen($diaPonto["diffJornadaEfetiva"]) > 5) {

                        $diaPontojP = preg_replace("/.*&nbsp;/", "", $diaPonto["diffJornadaEfetiva"]);
                        if (preg_match("/(\d{2}:\d{2})$/", $diaPontojP, $matches)) {
                            $JorEfet = $matches[1];
                        }
                    } else {
                        $JorEfet = $diaPonto["diffJornadaEfetiva"];
                    }

                    $he50 = $diaPonto["he50"];
                    $he100 = $diaPonto["he100"];
                    $adicNot = $diaPonto["adicionalNoturno"];
                    $espInd  = $diaPonto["esperaIndenizada"];
                    $saldoPer = strip_tags($diaPonto["diffSaldo"]);

                    $totalJorPrev      = operarHorarios([$totalJorPrev,      $JorPrev], "+");
                    $totalJorEfe       = operarHorarios([$totalJorEfe,       $JorEfet], "+");
                    $totalHE50         = operarHorarios([$totalHE50,         $he50], "+");
                    $totalHE100        = operarHorarios([$totalHE100,        $he100], "+");
                    $totalAdicNot      = operarHorarios([$totalAdicNot,      $adicNot], "+");
                    $totalEspInd       = operarHorarios([$totalEspInd,       $espInd], "+");
                    $totalSaldoPeriodo = operarHorarios([$totalSaldoPeriodo, $saldoPer], "+");
                    $totalSaldofinal   = operarHorarios([$saldoAnterior,     $totalSaldoPeriodo], "+");
                }

                $rows[] = [
                    "IdMotorista" => $motorista["enti_nb_id"],
                    "matricula" => $motorista["enti_tx_matricula"],
                    "motorista" => $motorista["enti_tx_nome"],
                    "statusEndosso" => $endossado,
                    "jornadaPrevista" => $totalJorPrev,
                    "jornadaEfetiva" => $totalJorEfe,
                    "he50" => $totalHE50,
                    "he100" => $totalHE100,
                    "adicionalNoturno" => $totalAdicNot,
                    "esperaIndenizada" => $totalEspInd,
                    "saldoAnterior" => $saldoAnterior,
                    "saldoPeriodo" => $totalSaldoPeriodo,
                    "saldoFinal" => $totalSaldofinal
                ];
            }
            if (!is_dir("./arquivos/paineis/saldos/$empresa[empr_nb_id]/$mes-$ano")) {
                mkdir("./arquivos/paineis/saldos/$empresa[empr_nb_id]/$mes-$ano", 0755, true);
            }
            $path = "./arquivos/paineis/saldos/$empresa[empr_nb_id]/$mes-$ano/";
            $fileName = "motoristas.json";
            $jsonArquiMoto = json_encode($rows, JSON_UNESCAPED_UNICODE);
            file_put_contents($path.$fileName, $jsonArquiMoto);

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

            if (!is_dir("./arquivos/paineis/saldos/$empresa[empr_nb_id]/$mes-$ano")) {
                mkdir("./arquivos/paineis/saldos/$empresa[empr_nb_id]/$mes-$ano", 0755, true);
            }
            $path = "./arquivos/paineis/saldos/$empresa[empr_nb_id]/$mes-$ano/";
            $fileName = "totalMotoristas.json";
            $jsonArquiTotais = json_encode($totaisJson, JSON_UNESCAPED_UNICODE);
            file_put_contents($path.$fileName, $jsonArquiTotais);
        }
        if (!is_dir("./arquivos/paineis/saldos/empresas/$mes-$ano")) {
            mkdir("./arquivos/paineis/saldos/empresas/$mes-$ano", 0755, true);
        }
        $path = "./arquivos/paineis/saldos/empresas/$mes-$ano/";
        $fileName = "totalEmpresas.json";
        $jsonArquiTotais = json_encode($totais, JSON_UNESCAPED_UNICODE);
        file_put_contents($path.$fileName, $jsonArquiTotais);

        $totalJorPrev = "00:00";
        $totalJorEfe = "00:00";
        $totalHE50 = "00:00";
        $totalHE100 = "00:00";
        $totalAdicNot = "00:00";
        $totalEspInd = "00:00";
        $totalSaldoPeriodo = "00:00";
        $toralSaldoAnter = "00:00";
        $saldoFinal = "00:00";
        $totalMotorista = 0;

        foreach ($totais as $totalEmpresa) {

            $totalMotorista += $totalEmpresa["totalMotorista"];

            $totalJorPrev           = somarHorarios([$totalEmpresa["jornadaPrevista"], $totalJorPrev]);
            $totalJorEfe            = somarHorarios([$totalJorEfe, $totalEmpresa["JornadaEfetiva"]]);
            $totalHE50              = somarHorarios([$totalHE50, $totalEmpresa["he50"]]);
            $totalHE100             = somarHorarios([$totalHE100, $totalEmpresa["he100"]]);
            $totalAdicNot           = somarHorarios([$totalAdicNot, $totalEmpresa["adicionalNoturno"]]);
            $totalEspInd            = somarHorarios([$totalEspInd, $totalEmpresa["esperaIndenizada"]]);
            $toralSaldoAnter        = somarHorarios([$toralSaldoAnter, $totalEmpresa["saldoAnterior"]]);
            $totalSaldoPeriodo      = somarHorarios([$totalSaldoPeriodo, $totalEmpresa["saldoPeriodo"]]);
            $saldoFinal             = somarHorarios([$saldoFinal, $totalEmpresa["saldoFinal"]]);
        }

        $jsonTotaisEmpr = [
            "EmprTotalJorPrev"      => $totalJorPrev,
            "EmprTotalJorEfe"       => $totalJorEfe,
            "EmprTotalHE50"         => $totalHE50,
            "EmprTotalHE100"        => $totalHE100,
            "EmprTotalAdicNot"      => $totalAdicNot,
            "EmprTotalEspInd"       => $totalEspInd,
            "EmprTotalSaldoAnter"   => $toralSaldoAnter,
            "EmprTotalSaldoPeriodo" => $totalSaldoPeriodo,
            "EmprTotalSaldoFinal"   => $saldoFinal,
            "EmprTotalMotorista"    => $totalMotorista
        ];


        if (!is_dir("./arquivos/paineis/saldos/empresas/$mes-$ano")) {
            mkdir("./arquivos/paineis/saldos/empresas/$mes-$ano", 0755, true);
        }
        $path = "./arquivos/paineis/saldos/empresas/$mes-$ano/";
        $fileName = "empresas.json";
        $jsonArqui = json_encode($jsonTotaisEmpr);
        file_put_contents($path.$fileName, $jsonArqui);
        return;
    }

    function index(){
        global $totalResumo, $CONTEX;

        if (array_key_exists("atualizar", $_POST) && !empty($_POST["atualizar"])) {
            echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
            ob_flush();
            flush();
            criar_relatorio_saldo();
        }

        if (empty($_POST["busca_dataInicio"])) {
            $_POST["busca_dataInicio"] = date("Y-m-01");
        }
        if (empty($_POST["busca_dataFim"])) {
            $_POST["busca_dataFim"] = date("Y-m-d");
        }

        $c = [
            combo_net("Empresa*", "empresa", ($_POST["empresa"]?? ""), 4, "empresa", ""),
            campo_data("Data Início*", "busca_dataInicio", ($_POST["busca_dataInicio"]?? ""), 2, ""),
            campo_data("Data Fim*", "busca_dataFim", ($_POST["busca_dataFim"]?? ""), 2, "")
        ];
        
        cabecalho("Relatório Geral de Saldo");
        
        $botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";
        if (!empty($_SESSION["user_tx_nivel"]) && is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))) {
            $botaoAtualizarPainel = "<a class='btn btn-warning' onclick='atualizarPainel()'> Atualizar Painel </a>";
        }

        $botao_volta = "";
        if (!empty($_POST["empresa"])) {
            $botao_volta = "<button class='btn default' type='button' onclick='setAndSubmit(\"\")'>Voltar</button>";
        }

        $b = [
            botao("Buscar", "index", "", "", "", "", "btn btn-info"),
            $botao_imprimir,
            $botao_volta,
            $botaoAtualizarPainel
        ];


        abre_form("Filtro de Busca");
        linha_form($c);
        fecha_form($b);

        $dataInicio = new DateTime($_POST["busca_dataInicio"]);
        $dataFim = new DateTime($_POST["busca_dataFim"]);
        $pasta = "./arquivos/paineis";
        $mes = $dataInicio->format("m");
        $ano = $dataInicio->format("Y");

        if (!empty($_POST["empresa"])) {
            $idEmpresa = $_POST["empresa"];

            $aEmpresa = mysqli_fetch_all(query(
                "SELECT empr_tx_logo FROM empresa"
                ." WHERE empr_tx_status = 'ativo'"
                    ." AND empr_tx_Ehmatriz = 'sim'"
                    ." AND empr_nb_id = ".$idEmpresa
            ), MYSQLI_ASSOC);

            $objetos = [];
            $totais = [];
            $emissao = [];

            $pasta .= "/saldos/".$idEmpresa."/".$dataInicio->format('m-Y');

            if (is_dir($pasta)){
                $file = $pasta."/motoristas.json";
                if (file_exists($file)) {
                    $conteudo_json = file_get_contents($file);
                    $objetos = json_decode($conteudo_json, true);
                }

                // Obtém O total dos saldos
                if (file_exists($pasta."/totalMotoristas.json")) {
                    $conteudo_json = file_get_contents($pasta."/totalMotoristas.json");
                    $totais = json_decode($conteudo_json, true);
                }



                // Obtém o tempo da última modificação do arquivo
                $lastUpdateTimestamp = filemtime($pasta."/motoristas.json");
                if (filemtime($pasta."/totalMotoristas.json") == $lastUpdateTimestamp) {
                    $emissao = date("d/m/Y H:i", $lastUpdateTimestamp); //Utilizado no HTML.php
                }

                $quantPosi = 0;
                $quantNega = 0;
                $quantMeta = 0;

                foreach ($objetos as $motoristasTotal) {
                    if ($motoristasTotal["saldoFinal"] === "00:00") {
                        $quantMeta++;
                    } elseif ($motoristasTotal["saldoFinal"] > "00:00") {
                        $quantPosi++;
                    } elseif ($motoristasTotal["saldoFinal"] < "00:00") {
                        $quantNega++;
                    }
                }

                $performance = calcPercs(count($objetos), $quantMeta, $quantNega, $quantPosi);

                if(!empty($_POST["empresa"]) && !empty($_POST["busca_dataInicio"]) && !empty($_POST["busca_dataFim"])){
                    $url = $pasta."/motoristas.json";
                }else{
                    $url = "./arquivos/paineis/saldos/empresas/".$mes."-".$ano."/totalEmpresas.json";
                }

                include_once "painel_saldo_html.php";

                echo "<script>document.getElementById('tabela1').style.display = 'none'</script>";

                $valorTotais = [
                    $totais["empresaNome"],
                    "",
                    $totais["jornadaPrevista"],
                    $totais["JornadaEfetiva"],
                    $totais["he50"],
                    $totais["he100"],
                    $totais["adicionalNoturno"],
                    $totais["esperaIndenizada"],
                    $totais["saldoAnterior"],
                    $totais["saldoPeriodo"],
                    $totais["saldoFinal"]
                ];
                $nomeTitulos = [
                    "motorista"         => "Matrícula",
                    "empresaNome"       => "Nome",
                    "status"            => "Status",
                    "jornadaPrevista"   => "Jornada Prevista",
                    "jornadaEfetiva"    => "Jornada Efetiva",
                    "he50"              => "H.E. Semanal",
                    "he100"             => "H.E. Domingo",
                    "adicionalNoturno"  => "Adicional Noturno",
                    "esperaIndenizada"  => "Espera Indenizada",
                    "saldoAnterior"     => "Saldo Anterior",
                    "saldoPeriodo"      => "Saldo Período",
                    "saldoFinal"        => "Saldo Final",    
                ];
                echo "<script>";
                for($f = 0; $f < sizeof($valorTotais); $f++){
                    echo "document.getElementsByClassName('totais')[0].getElementsByTagName('th')[".($f)."].innerHTML = '".$valorTotais[$f]."';\n";
                }
                $f = 0;
                foreach($nomeTitulos as $key => $value){
                    echo "document.getElementsByClassName('titulos')[0].getElementsByTagName('th')[".$f."].setAttribute('data-column', '".$key."');\n";
                    echo "document.getElementsByClassName('titulos')[0].getElementsByTagName('th')[".$f++."].innerHTML = '".$value."';\n";
                }
                echo "</script>";
            }else{
                echo "<script>alert('Não Possui dados desse mês')</script>";
            }
        } else {
            $objetos = [];
            $totais = [];
            $emissao = "";
            $aEmpresa = mysqli_fetch_all(query(
                "SELECT empr_tx_logo FROM `empresa`"
                ." WHERE empr_tx_Ehmatriz = 'sim'"
            ), MYSQLI_ASSOC);

            $pasta = "./arquivos/paineis/saldos/empresas/$mes-$ano";

            if(is_dir($pasta)){
                $file = $pasta."/empresas.json";
                if (file_exists($file)) {
                    $conteudo_json = file_get_contents($file);
                    $objetos = json_decode($conteudo_json, true);
                }

                // Obtém O total dos saldos de cada empresa
                $fileEmpresas = $pasta."/totalEmpresas.json";

                if (file_exists($fileEmpresas)) {
                    $conteudo_json = file_get_contents($fileEmpresas);
                    $totais = json_decode($conteudo_json, true);
                }

                // Obtém o tempo da última modificação do arquivo
                $lastUpdateTimestamp = filemtime($pasta."/empresas.json");
                if (filemtime($pasta."/totalEmpresas.json") == $lastUpdateTimestamp) {
                    $emissao = date("d/m/Y H:i", $lastUpdateTimestamp);
                }
            }else{
                echo "<script>alert('Não Possui dados desse mês')</script>";
            }

            $quantPosi = 0;
            $quantNega = 0;
            $quantMeta = 0;
            foreach($totais as $empresaTotal){
                if($empresaTotal["saldoFinal"] === "00:00"){
                    $quantMeta++;
                }elseif($empresaTotal["saldoFinal"] > "00:00"){
                    $quantPosi++;
                }elseif($empresaTotal["saldoFinal"] < "00:00"){
                    $quantNega++;
                }
            }
            $performance = calcPercs(count($totais), $quantMeta, $quantNega, $quantPosi);

            include_once "painel_saldo_html.php";
        }

        echo 
            "<form name='myForm' method='post' action='".htmlspecialchars($_SERVER["PHP_SELF"])."'>
                <input type='hidden' name='empresa' id='empresa'>
                <input type='hidden' name='busca_dataInicio' id='busca_dataInicio'>
                <input type='hidden' name='busca_dataFim' id='busca_dataFim'>
            </form>
            <form name='formularioAtualizarPainel' method='POST' action='".htmlspecialchars(basename($_SERVER["PHP_SELF"]))."'>
                <input type='hidden' name='atualizar' id='atualizar'>
                <input type='hidden' name='empresa' id='empresa'>
                <input type='hidden' name='busca_dataInicio' id='busca_dataInicio'>
                <input type='hidden' name='busca_dataFim' id='busca_dataFim'>
            </form>

            <script>
                function imprimir(){
                    window.print();
                }
                
                function setAndSubmit(empresa){
                    document.myForm.empresa.value = empresa;
                    document.formularioAtualizarPainel.busca_dataInicio.value = document.getElementById('busca_dataInicio').value;
                    document.formularioAtualizarPainel.busca_dataFim.value = document.getElementById('busca_dataFim').value;
                    document.myForm.submit();
                }

                function atualizarPainel(){
                    document.formularioAtualizarPainel.empresa.value = document.getElementById('empresa').value;
                    document.formularioAtualizarPainel.busca_dataInicio.value = document.getElementById('busca_dataInicio').value;
                    document.formularioAtualizarPainel.busca_dataFim.value = document.getElementById('busca_dataFim').value;
                    document.formularioAtualizarPainel.atualizar.value = 'atualizar';
                    document.formularioAtualizarPainel.submit();
                }
            </script>"
        ;

        rodape();
    }