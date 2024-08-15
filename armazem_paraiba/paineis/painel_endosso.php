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

    function index(){
        global $totalResumo, $CONTEX;

        if (array_key_exists("atualizar", $_POST) && !empty($_POST["atualizar"])) {
            echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
            ob_flush();
            flush();
            criar_relatorio($_POST["busca_data"]);
        }

        if (empty($_POST["busca_data"])){       
            $_POST["busca_data"] = date("Y-m");
        }
        if (empty($_POST["busca_dataInicio"])) {
            $_POST["busca_dataInicio"] = $_POST["busca_data"]."-01";
        }
        if (empty($_POST["busca_dataFim"])) {
            $_POST["busca_dataFim"] = (DateTime::createFromFormat("Y-m", $_POST["busca_data"]))->format("Y-m-t");
        }

        $c = [
            combo_net("Empresa", "empresa", ($_POST["empresa"]?? ""), 4, "empresa", ""),
            campo_mes("Data*", "busca_data", (!empty($_POST["busca_data"])? $_POST["busca_data"]: ""), 2),
        ];
        
        cabecalho("Relatório Final de Endosso");

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

            $pasta .= "/saldos/".$idEmpresa."/".$mes."-".$ano;

            if (is_dir($pasta)){
                $file = $pasta."/motoristas.json";
                if (file_exists($file)) {
                    $conteudo_json = file_get_contents($file);
                    $objetos = json_decode($conteudo_json, true);
                }

                $file = $pasta."/../../".$_POST["empresa"]."/".$_POST["busca_data"]."totalMotoristas.json";
                // Obtém O total dos saldos
                if (file_exists($file)) {
                    $conteudo_json = file_get_contents($file);
                    $totais = json_decode($conteudo_json, true);
                }

                foreach (["jornadaPrevista", "JornadaEfetiva", "he50", "he100", "adicionalNoturno", "esperaIndenizada", "saldoAnterior", "saldoPeriodo", "saldoFinal"] as $campo) {
                    if ($totais[$campo] == "00:00") {
                        $totais[$campo] = "";
                    }
                }

                

                // Obtém o tempo da última modificação do arquivo
                $lastUpdateTimestamp = filemtime($pasta."/motoristas.json");
                if (filemtime($pasta."/totalMotoristas.json") == $lastUpdateTimestamp) {
                    $emissao = date("d/m/Y H:i", $lastUpdateTimestamp); //Utilizado no HTML.php
                }

                $endosso = calcPercs($totais["totalMotorista"],$totais["endossados"], $totais["naoEndossados"], $totais["endossoPacial"]);

                $quantPosi = 0;
                $quantNega = 0;
                $quantMeta = 0;

                foreach ($objetos as $MotoristaTotal) {
                    $saldoFinal = $MotoristaTotal["saldoFinal"];

                    if ($MotoristaTotal["statusEndosso"] == "E" && $saldoFinal == "00:00") {
                        $quantMeta++;
                    } elseif ($saldoFinal > "00:00") {
                        $quantPosi++;
                    } elseif ($saldoFinal < "00:00") {
                        $quantNega++;
                    }
                }

                $performance = calcPercs(count($objetos), $quantMeta, $quantNega, $quantPosi);

                $dataInicio = (new DateTime($_POST["busca_dataInicio"]));

                if(!empty($_POST["empresa"]) && !empty($_POST["busca_data"])){
                    $url = $pasta."/motoristas.json";
                }else{
                    $url = $pasta."/totalEmpresas.json";
                }

                include_once "painel_endosso_html.php";

                echo "<script>";
                echo 
                    "var percNaoEndossado = '".$endosso["nega_naEndo"]."';"
                    ."var percEndoPC = '".$endosso["posi_endoPc"]."';"
                    ."var percEndossado = '".$endosso["meta_endo"]."'";
                
                if (!empty($_POST['empresa']) && !empty($_POST['busca_data'])) {
                        echo 
                            "var totalNaoEndossado = '".$totaisMotoristas["naoEndossados"]."';"
                            ."var totalEndoPC = '".$totaisMotoristas["endossoPacial"]."';"
                            ."var totalEndossado = '".$totaisMotoristas["endossados"]."';";
                }else{
                    echo 
                        "var totalNaoEndossado = '".$empresasTotais["EmprTotalNaoEnd"]."';"
                        ."var totalEndoPC = '".$empresasTotais["EmprTotalEndPac"]."';"
                        ."var totalEndossado = '".$empresasTotais["EmprTotalEnd"]."';";
                }
                echo 
                    "document.getElementsByClassName('porcentagemNaEndo')[0] = totalNaoEndossado;"
                    ."document.getElementsByClassName('porcentagemNaEndo')[1] = percNaoEndossado;"
                    
                    ."document.getElementsByClassName('porcentagemEndoPc')[0] = totalEndoPc;"
                    ."document.getElementsByClassName('porcentagemEndoPc')[1] = percEndoPc;"
                    
                    ."document.getElementsByClassName('porcentagemEndo')[0] = totalEndossado;"
                    ."document.getElementsByClassName('porcentagemEndo')[1] = percEndossado;";
                echo "</script>";

                echo "<script>document.getElementById('tabela1').display = 'block'</script>";

                if(!empty($_POST["empresa"]) && !empty($_POST["busca_data"])){
                    $valorTotais = [
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
                        "motorista" => "Matrícula",
                        "empresaNome" => "Nome",
                        "status" => "Status",
                        "jornadaPrevista" => "Jornada Prevista",
                        "jornadaEfetiva" => "Jornada Efetiva",
                        "he50" => "H.E. Semanal",
                        "he100" => "H.E. Domingo",
                        "adicionalNoturno" => "Adicional Noturno",
                        "esperaIndenizada" => "Espera Indenizada",
                        "saldoAnterior" => "Saldo Anterior",
                        "saldoPeriodo" => "Saldo Periodo",
                        "saldoFinal" => "Saldo Final"
                    ];
                }else{
                    $valorTotais = [
                        $empresasTotais["EmprTotalJorPrev"],
                        $empresasTotais["EmprTotalJorEfe"]
                        (($empresasTotais["EmprTotalHE50"] == "00:00") ? "" : $empresasTotais["EmprTotalHE50"]),
                        (($empresasTotais["EmprTotalHE100"] == "00:00") ? "" : $empresasTotais["EmprTotalHE100"]),
                        (($empresasTotais["EmprTotalAdicNot"] == "00:00") ? "" : $empresasTotais["EmprTotalAdicNot"]),
                        (($empresasTotais["EmprTotalEspInd"] == "00:00") ? "" : $empresasTotais["EmprTotalEspInd"]),
                        (($empresasTotais["EmprTotalSaldoAnter"] == "00:00" || $empresasTotais["EmprTotalSaldoAnter"] == null) ? "" : $empresasTotais["EmprTotalSaldoAnter"]),
                        (($empresasTotais["EmprTotalSaldoPeriodo"] == "00:00") ? "" : $empresasTotais["EmprTotalSaldoPeriodo"]),
                        (($empresasTotais["EmprTotalSaldoFinal"] == "00:00") ? "" : $empresasTotais["EmprTotalSaldoFinal"])
                    ];

                    $nomeTitulos = [
                        "empresaNome"       => "Todos os CNPJ",
                        "porcentagem"       => "End %",
                        "totalMotorista"    => "Quant. Motoristas",
                        "jornadaPrevista"   => "Jornada Prevista",
                        "jornadaEfetiva"    => "Jornada Efetiva",
                        "he50"              => "H.E. Semanal",
                        "he100"             => "H.E. Domingo",
                        "adicionalNoturno"  => "Adicional Noturno",
                        "esperaIndenizada"  => "Espera Indenizada",
                        "saldoAnterior"     => "Saldo Anterior",
                        "saldoPeriodo"      => "Saldo Periodo",
                        "saldoFinal"        => "Saldo Final"
                    ];
                }
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
            $aEmpresa = mysqli_fetch_all(query(
                "SELECT empr_tx_logo FROM empresa"
                ." WHERE empr_tx_Ehmatriz = 'sim'"
            ), MYSQLI_ASSOC);

            $empresasTotais = [];
            $totais = [];
            $emissao = "";
            $pasta = "./arquivos/paineis/empresas/".$_POST["busca_data"];
            if (is_dir($pasta)){
                if (file_exists($pasta."/empresas.json")){
                    $conteudo_json = file_get_contents($pasta."/empresas.json");
                    $empresasTotais = json_decode($conteudo_json, true);
                }
        
                // Obtém O total dos saldos de cada empresa
                if (file_exists($pasta."/totalEmpresas.json")){
                    $conteudo_json = file_get_contents($pasta."/totalEmpresas.json");
                    $totais = json_decode($conteudo_json, true);
                }
        
                // Obtém o tempo da última modificação do arquivo
                $lastUpdateTimestamp = filemtime($pasta."/empresas.json");
                if (filemtime($pasta."/totalEmpresas.json") == $lastUpdateTimestamp){
                    $emissao = date("d/m/Y H:i", $lastUpdateTimestamp);
                }
            }else{
                echo "<script>alert('Não Possui dados desse mês')</script>";
            }

                // Calcula a porcentagem
            $endosso = calcPercs($empresasTotais["EmprTotalMotorista"], $empresasTotais["EmprTotalEnd"], $empresasTotais["EmprTotalNaoEnd"], $empresasTotais["EmprTotalEndPac"]);

            $quantPosi = 0;
            $quantNega = 0;
            $quantMeta = 0;

            foreach ($totais as $empresaTotal) {
                $saldoFinal = $empresaTotal["saldoFinal"];

                if ($saldoFinal === "00:00") {
                    $quantMeta++;
                } elseif ($saldoFinal > "00:00") {
                    $quantPosi++;
                } elseif ($saldoFinal < "00:00") {
                    $quantNega++;
                }
            }

            $performance = calcPercs(count($totais), $quantMeta, $quantNega, $quantPosi);

        }
        echo
            "<form name='myForm' method='POST' action='".htmlspecialchars(basename($_SERVER["PHP_SELF"]))."'>
                <input type='hidden' name='empresa' id='empresa'>
                <input type='hidden' name='busca_data' id='busca_data'>
            </form>
            <form name='formularioAtualizarPainel' method='POST' action='".htmlspecialchars(basename($_SERVER["PHP_SELF"]))."'>
                <input type='hidden' name='atualizar' id='atualizar'>
                <input type='hidden' name='busca_data' id='busca_dataAtualizar'>
            </form>

            <script>
                function imprimir(){
                    window.print();
                }
                
                function setAndSubmit(empresa){
                    document.myForm.empresa.value = empresa;
                    document.myForm.busca_data.value = document.getElementById('busca_data').value;
                    document.myForm.submit();
                }
                function atualizarPainel() {
                    document.formularioAtualizarPainel.busca_dataAtualizar.value = document.getElementById('busca_data').value;
                    document.formularioAtualizarPainel.atualizar.value = 'atualizar';
                    document.formularioAtualizarPainel.submit();
                }
            </script>";

        rodape();
    }
