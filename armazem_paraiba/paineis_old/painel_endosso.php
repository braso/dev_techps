<?php
    //* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);

    header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
    header("Pragma: no-cache"); // HTTP 1.0.
    header("Expires: 0");
    //*/


    include "../funcoes_ponto.php";

    function calcPercs(array $values): array{
        if(empty($values)){
            return [0];
        }

        $total = 0;
        foreach($values as $value){
            $total += $value;
        }
        
        $percentuais = array_pad([], sizeof($values), 0);
        for($f = 0; $f < sizeof($values); $f++){
            $percentuais[$f] = $values[$f]/$total;
        }

        return $percentuais;
    }

    function index(){
        global $totalResumo, $CONTEX;

        if(!empty($_POST["atualizar"])){
            echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
            ob_flush();
            flush();
            criar_relatorio($_POST["busca_data"]);
        }

        if(empty($_POST["busca_data"])){
            $_POST["busca_data"] = date("Y-m");
        }
        if(empty($_POST["busca_dataInicio"])){
            $_POST["busca_dataInicio"] = $_POST["busca_data"]."-01";
        }
        if(empty($_POST["busca_dataFim"])){
            $_POST["busca_dataFim"] = date("Y-m-d");
        }

        $c = [
            combo_net("Empresa", "empresa", ($_POST["empresa"]?? ""), 4, "empresa", ""),
            campo_mes("Data*", "busca_data", (!empty($_POST["busca_data"])? $_POST["busca_data"]: ""), 2),
        ];
        
        cabecalho("Relatório Final de Endosso");

        $botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";
        if(!empty($_SESSION["user_tx_nivel"]) && is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
            $botaoAtualizarPainel = "<a class='btn btn-warning' onclick='atualizarPainel()'> Atualizar Painel </a>";
        }

        $botao_volta = "";
        if(!empty($_POST["empresa"])){
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

        if(!empty($_POST["empresa"])){
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

            $pasta .= "/saldos/".$idEmpresa."/".$dataInicio->format("m-Y");

            if(
                is_dir($pasta) 
                && file_exists($pasta."/motoristas.json") 
                && file_exists($pasta."/totalMotoristas.json")
            ){
                $conteudo_json = file_get_contents($pasta."/motoristas.json");
                $objetos = json_decode($conteudo_json, true);
                
                $conteudo_json = file_get_contents($pasta."/totalMotoristas.json");
                $totais = json_decode($conteudo_json, true);

                foreach (["jornadaPrevista", "JornadaEfetiva", "he50", "he100", "adicionalNoturno", "esperaIndenizada", "saldoAnterior", "saldoPeriodo", "saldoFinal"] as $campo) {
                    if ($totais[$campo] == "00:00") {
                        $totais[$campo] = "";
                    }
                }
                
                $lastUpdateTimestamp = filemtime($pasta."/motoristas.json");
                if(filemtime($pasta."/totalMotoristas.json") == $lastUpdateTimestamp){
                    $emissao = date("d/m/Y H:i", $lastUpdateTimestamp); //Utilizado no HTML.php
                }
                $endosso = calcPercs($totais["totalMotorista"], $totais["endossados"], $totais["naoEndossados"], $totais["endossoPacial"]);

                $quantPosi = 0;
                $quantNega = 0;
                $quantMeta = 0;

                foreach($objetos as $objetoTotais){
                    if($objetoTotais["saldoFinal"] === "00:00"){
                        $quantMeta++;
                    }elseif($objetoTotais["saldoFinal"] > "00:00"){
                        $quantPosi++;
                    }elseif($objetoTotais["saldoFinal"] < "00:00"){
                        $quantNega++;
                    }
                }

                $performance = calcPercs(count($objetos), $quantMeta, $quantNega, $quantPosi);

                if(!empty($_POST["busca_data"])){
                    $url = $pasta."/motoristas.json";
                }else{
                    $url = $pasta."/totalEmpresas.json";
                }

                $linha = "'<tr>'";
                if (!empty($_POST['busca_data'])){
                    $linha .=
                        "+'<td>'+item.matricula+'</td>'"
                        ."+'<td>'+item.motorista+'</td>'"
                        ."+'<td>'+item.statusEndosso+'</td>'"
                    ;
                } else {
                    echo "var porcentagem = ((item.endossados/item.totalMotorista)*100)*(!isNaN(item.endossados) && !isNaN(item.totalMotorista) && item.totalMotorista !== 0);";
                    $linha .= 
                        "+'<td style=\"cursor: pointer;\" onclick=setAndSubmit('+item.empresaId+')>'+item.empresaNome+'</td>'"
                        ."+'<td>'+porcentagem+'</td>'"
                        ."+'<td>'+item.totalMotorista+'</td>'"
                    ;
                }
                $linha .= 
                    "+'<td>'+item.jornadaPrevista+'</td>'"
                    ."+'<td>'+item.jornadaEfetiva+'</td>'"
                    ."+'<td>'+((item.he50 === null || item.he50 === '00:00')? '': item.he50)+'</td>'"
                    ."+'<td>'+((item.he100 === null || item.he100 === '00:00')? '': item.he100)+'</td>'"
                    ."+'<td>'+((item.adicionalNoturno === null || item.adicionalNoturno === '00:00')? '': item.adicionalNoturno)+'</td>'"
                    ."+'<td>'+((item.esperaIndenizada === null || item.esperaIndenizada === '00:00')? '': item.esperaIndenizada)+'</td>'"
                    ."+'<td>'+((item.saldoAnterior === null || item.saldoAnterior === '00:00')? '': item.saldoAnterior)+'</td>'"
                    ."+'<td>'+((item.saldoPeriodo === null || item.saldoPeriodo === '00:00')? '': item.saldoPeriodo)+'</td>'"
                    ."+'<td>'+((item.saldoFinal === null || item.saldoFinal === '00:00')? '': item.saldoFinal)+'</td>'";
                
                $linha .= "+'</tr>'"; // Utilizado no painel.

                include_once "painel_endosso_html.php";

                echo "<script>document.getElementById('tabela1').display = 'block'</script>";
                echo "<script>";
                echo 
                    "var percNaoEndossado = '".$endosso["nega_naEndo"]."';"
                    ."var percEndoPC = '".$endosso["posi_endoPc"]."';"
                    ."var percEndossado = '".$endosso["meta_endo"]."'";
                
                if (!empty($_POST['busca_data'])) {
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
        }else{
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
                $endosso = calcPercs([$empresasTotais["EmprTotalMotorista"], $empresasTotais["EmprTotalEnd"], $empresasTotais["EmprTotalNaoEnd"], $empresasTotais["EmprTotalEndPac"]]);
            }else{
                echo "<script>alert('Não Possui dados desse mês')</script>";
                $endosso = [0,0,0,0];
            }

                // Calcula a porcentagem

            $quantPosi = 0;
            $quantNega = 0;
            $quantMeta = 0;

            foreach ($totais as $empresaTotal) {

                if ($empresaTotal["saldoFinal"] === "00:00") {
                    $quantMeta++;
                } elseif ($empresaTotal["saldoFinal"] > "00:00") {
                    $quantPosi++;
                } elseif ($empresaTotal["saldoFinal"] < "00:00") {
                    $quantNega++;
                }
            }

            $performance = calcPercs([$quantMeta, $quantNega, $quantPosi]);

        }
        echo
            "<form name='myForm' method='POST' action='".htmlspecialchars(basename($_SERVER["PHP_SELF"]))."'>
                <input type='hidden' name='empresa' id='empresa'>
                <input type='hidden' name='busca_data' id='busca_data'>
            </form>
            <form name='formAtualPainel' method='POST' action='".htmlspecialchars(basename($_SERVER["PHP_SELF"]))."'>
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
                function atualizarPainel(){
                    document.formAtualPainel.busca_dataAtualizar.value = document.getElementById('busca_data').value;
                    document.formAtualPainel.atualizar.value = 'atualizar';
                    document.formAtualPainel.submit();
                }
            </script>";

        rodape();
    }
