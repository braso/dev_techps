<?php
    /* Modo debug
        ini_set("display_errors", 1);
        error_reporting(E_ALL);
    //*/

    include "funcoes_ponto.php";

    function criar_relatorio_saldo(){
        global $totalResumo;
        $periodoInicio = $_POST["busca_dataInicio"];
        $periodoFim = $_POST["busca_dataFim"];

        $empresas = mysqli_fetch_all(
            query(
                "SELECT empr_nb_id, empr_tx_nome FROM empresa"
                ." WHERE empr_tx_status = 'ativo'"
                .(!empty($_POST["empresa"])? " AND empr_nb_id = ".$_POST["empresa"]: "")
                ." ORDER BY empr_tx_nome ASC;"
            ),
            MYSQLI_ASSOC
        );
        
        foreach ($empresas as $empresa){
            $motoristas = mysqli_fetch_all(
                query(
                    "SELECT enti_nb_id, enti_tx_nome,enti_tx_matricula FROM entidade"
                        ." WHERE enti_tx_status = 'ativo'"
                            ." AND enti_nb_empresa = ".$empresa["empr_nb_id"]
                            ." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante')"
                        ." ORDER BY enti_tx_nome ASC"
                ),
                MYSQLI_ASSOC
            );

            $rows = [];

            $motoristas = [$motoristas[9]];

            foreach ($motoristas as $motorista){
                // Jornada Prevista, Jornada Efetiva, HE50%, HE100%, Adicional Noturno, Espera Indenizada{
                    $totalJorPrevResut = "00:00";
                    $totalJorPrev      = "00:00";
                    $totalJorEfe       = "00:00";
                    $totalHE50         = "00:00";
                    $totalHE100        = "00:00";
                    $totalAdicNot      = "00:00";
                    $totalEspInd       = "00:00";
                    $totalSaldoPeriodo = "00:00";
                    $totalSaldofinal   = "00:00";
                    $saldoAnt          = "00:00";
                //}
                
                // saldoAnterior, saldoPeriodo e saldoFinal{
                    $saldoAnterior = mysqli_fetch_all(query(
                        "SELECT endo_tx_saldo FROM endosso"
                            ." WHERE endo_tx_status = 'ativo'"
                                ." AND endo_tx_ate < '".$periodoInicio."'"
                                ." AND endo_tx_matricula = '".$motorista["enti_tx_matricula"]."'"
                            ." ORDER BY endo_tx_ate DESC"
                            ." LIMIT 1;"
                        ), 
                        MYSQLI_ASSOC
                    );
                            

                    if (!empty($saldoAnterior[0]["endo_tx_saldo"])){
                        $saldoAnterior = $saldoAnterior[0]["endo_tx_saldo"];
                    }elseif (!empty($aMotorista["enti_tx_banco"])){
                        $saldoAnterior = $aMotorista["enti_tx_banco"][0][0] == "0" && strlen($aMotorista["enti_tx_banco"]) > 5? substr($aMotorista["enti_tx_banco"], 1): $aMotorista["enti_tx_banco"];
                    }else{
                        $saldoAnterior = "00:00";
                    }
                //}
                
                $diasPonto = [];
                $dataTimeInicio = new DateTime($periodoInicio);
                $dataTimeFim = new DateTime($periodoFim);
                $mes = $dataTimeInicio->format("m");
                $ano = $dataTimeInicio->format("Y");
                for ($date = $dataTimeInicio; $date <= $dataTimeFim; $date->modify("+1 day")){

                    $dataVez = $date->format("Y-m-d");
                    $diasPonto[] = diaDetalhePonto($motorista["enti_tx_matricula"], $dataVez);

                }
                
                foreach ($diasPonto as $diaPonto){
                    if (strlen($diaPonto["jornadaPrevista"]) > 5){
                        
                        $diaPontoJ = preg_replace("/.*&nbsp;/", "", $diaPonto["jornadaPrevista"]);
                        if (preg_match("/(\d{2}:\d{2})$/", $diaPontoJ, $matches)){
                            $JorPrev = $matches[1];
                        }
                    }else{
                        $JorPrev = $diaPonto["jornadaPrevista"];
                    }

                    $he50 = empty($diaPonto["he50"])? "00:00": $diaPonto["he50"];
                    $he100 = empty($diaPonto["he100"])? "00:00": $diaPonto["he100"];
                    $adicNot = $diaPonto["adicionalNoturno"];
                    $espInd  = $diaPonto["esperaIndenizada"];
                    $saldoPer = $diaPonto["diffSaldo"];

                    if(!preg_match("/^-?\d{2,4}:\d{2}$/", $diaPonto["diffJornadaEfetiva"])){
                        $diaPonto["diffJornadaEfetiva"] = explode(";", $diaPonto["diffJornadaEfetiva"]);
                        $diaPonto["diffJornadaEfetiva"] = $diaPonto["diffJornadaEfetiva"][sizeof($diaPonto["diffJornadaEfetiva"])-1];
                        var_dump($diaPonto["diffJornadaEfetiva"]);

                        $diaPonto["diffJornadaEfetiva"] = str_replace(["<b>", "</b>"], ["", ""], $diaPonto["diffJornadaEfetiva"]);
                    }

                    $totalJorPrev      = somarHorarios([$totalJorPrev,      $JorPrev]);
                    $totalJorEfe       = somarHorarios([$totalJorEfe,       $diaPonto["diffJornadaEfetiva"]]);
                    $totalHE50         = somarHorarios([$totalHE50,         $he50]);
                    $totalHE100        = somarHorarios([$totalHE100,        $he100]);
                    $totalAdicNot      = somarHorarios([$totalAdicNot,      $adicNot]);
                    $totalEspInd       = somarHorarios([$totalEspInd,       $espInd]);
                    $totalSaldoPeriodo = somarHorarios([$totalSaldoPeriodo, $saldoPer]);
                    $totalSaldofinal   = somarHorarios([$saldoAnterior, $totalSaldoPeriodo]);
                    
                }
                echo "<br>";

                $rows[] = [
                    "IdMotorista"      => $motorista["enti_nb_id"],
                    "motorista"        => $motorista["enti_tx_nome"],
                    "statusEndosso"    => "",
                    "jornadaPrevista"  => $totalJorPrev,
                    "jornadaEfetiva"   => $totalJorEfe,
                    "he50"             => $totalHE50,
                    "he100"            => $totalHE100,
                    "adicionalNoturno" => $totalAdicNot,
                    "esperaIndenizada" => $totalEspInd,
                    "saldoAnterior"    => $saldoAnterior,
                    "saldoPeriodo"     => $totalSaldoPeriodo,
                    "saldoFinal"       => $totalSaldofinal
                ];
                
                var_dump($rows[sizeof($rows)-1]); echo "<br><br>";
            }
            die();
            if(!is_dir("./arquivos/paineis/Saldo/$empresa[empr_nb_id]/$mes-$ano")){
                mkdir("./arquivos/paineis/Saldo/$empresa[empr_nb_id]/$mes-$ano",0755,true);
            }
            $path = "./arquivos/paineis/Saldo/$empresa[empr_nb_id]/$mes-$ano/";
            $fileName = "motoristas.json";
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
            $saldoFinal = "00:00";

            foreach ($rows as $row){
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
            
            if(!is_dir("./arquivos/paineis/Saldo/$empresa[empr_nb_id]/$mes-$ano")){
                mkdir("./arquivos/paineis/Saldo/$empresa[empr_nb_id]/$mes-$ano",0755,true);
            }
            $path = "./arquivos/paineis/Saldo/$empresa[empr_nb_id]/$mes-$ano/";
            $fileName = "totalMotoristas.json";
            $jsonArquiTotais = json_encode($totaisJson,JSON_UNESCAPED_UNICODE);
            file_put_contents($path.$fileName, $jsonArquiTotais);
                
        }
        if(!is_dir("./arquivos/paineis/Saldo/empresas/$mes-$ano")){
            mkdir("./arquivos/paineis/Saldo/empresas/$mes-$ano",0755,true);
        }
        $path = "./arquivos/paineis/Saldo/empresas/$mes-$ano/";
        $fileName = "totalEmpresas.json";
        $jsonArquiTotais = json_encode($totais,JSON_UNESCAPED_UNICODE);
        file_put_contents($path.$fileName, $jsonArquiTotais);
        
        // $totalJorPrevResut = "00:00";
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
        $totalNaoEndossados = 0;
        $totalEndossados = 0;
        $totalEndossoPacial = 0;
        
        foreach ($totais as $totalEmpresa){

            $totalMotorista += $totalEmpresa["totalMotorista"];
            $totalNaoEndossados += $totalEmpresa["naoEndossados"];
            $totalEndossados += $totalEmpresa["endossados"];
            $totalEndossoPacial += $totalEmpresa["endossoPacial"];
            
            $totalJorPrev      = somarHorarios([$totalEmpresa["jornadaPrevista"],$totalJorPrev]);
            $totalJorEfe       = somarHorarios([$totalJorEfe, $totalEmpresa["JornadaEfetiva"]]);
            $totalHE50         = somarHorarios([$totalHE50, $totalEmpresa["he50"]]);
            $totalHE100        = somarHorarios([$totalHE100, $totalEmpresa["he100"]]);
            $totalAdicNot      = somarHorarios([$totalAdicNot, $totalEmpresa["adicionalNoturno"]]);
            $totalEspInd       = somarHorarios([$totalEspInd, $totalEmpresa["esperaIndenizada"]]);
            $toralSaldoAnter   = somarHorarios([$toralSaldoAnter, $totalEmpresa["saldoAnterior"]]);
            $totalSaldoPeriodo = somarHorarios([$totalSaldoPeriodo, $totalEmpresa["saldoPeriodo"]]);
            $saldoFinal        = somarHorarios([$saldoFinal, $totalEmpresa["saldoFinal"]]);
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
            "EmprTotalMotorista"    => $totalMotorista,
            "EmprTotalNaoEnd"       => $totalNaoEndossados,
            "EmprTotalEnd"          => $totalEndossados,
            "EmprTotalEndPac"       => $totalEndossoPacial,
            
        ];
        

        if(!is_dir("./arquivos/paineis/Saldo/empresas/$mes-$ano")){
            mkdir("./arquivos/paineis/Saldo/empresas/$mes-$ano",0755,true);
        }
        $path = "./arquivos/paineis/Saldo/empresas/$mes-$ano/";
        $fileName = "empresas.json";
        $jsonArqui = json_encode($jsonTotaisEmpr);
        file_put_contents($path.$fileName, $jsonArqui);
        return;
    }

    function empresa($aEmpresa, $idEmpresa){

		if(empty($_POST["busca_data"]) && !empty($_POST["busca_dataInicio"])){
			$_POST["busca_data"] = substr($_POST["busca_dataInicio"], 0, 7);
		}

		$MotoristasTotais = [];
		$MotoristaTotais = [];
		$endPastaPaineis = "./arquivos/paineis";

		global $CONTEX;
		
		if (!(is_dir($endPastaPaineis."/saldos/empresas/".$_POST["busca_data"]))){
			echo "<script>alert('Não Possui dados desse mês')</script>";
			exit;
		}

		// Obtém O total dos saldos das empresa
		$file = $endPastaPaineis."/saldos/".$idEmpresa."/".$_POST["busca_data"]."/totalMotoristas.json";

		if (file_exists($endPastaPaineis."/saldos/".$idEmpresa."/".$_POST["busca_data"])){
			$conteudo_json = file_get_contents($file);
			$MotoristasTotais = json_decode($conteudo_json,true);
		}

		foreach(["jornadaPrevista", "JornadaEfetiva", "he50", "he100", "adicionalNoturno", "esperaIndenizada", "saldoAnterior", "saldoPeriodo", "saldoFinal"] as $campo){
			if($MotoristasTotais[$campo] == "00:00"){
				$MotoristasTotais[$campo] = "";
			}
		}

		// Obtém o total dos saldos de cada Motorista
		$fileEmpresa = $endPastaPaineis."/".$idEmpresa."/".$_POST["busca_data"]."/motoristas.json";
		if (file_exists($endPastaPaineis."/".$idEmpresa."/".$_POST["busca_data"])){
			$conteudo_json = file_get_contents($fileEmpresa);
			$MotoristaTotais = json_decode($conteudo_json, true);
		}

        var_dump($MotoristaTotais);

		// Obtém o tempo da última modificação do arquivo
		$timestamp = filemtime($file);
		$emissão = date("d/m/Y H:i:s", $timestamp);


		// Calcula os percentuais de cada tipo de endosso, utilizado em painel_saldo_html
        $percentuaisEndossos = [
            "endossados" => number_format(($MotoristasTotais["endossados"]/$MotoristasTotais["totalMotorista"])*100, 2),
            "endossadosParcialmente" => number_format(($MotoristasTotais["endossoPacial"]/$MotoristasTotais["totalMotorista"])*100, 2),
            "naoEndossados" => number_format(($MotoristasTotais["naoEndossados"]/$MotoristasTotais["totalMotorista"])*100, 2),
        ];

		
        //Contar a quantidade de saldos positivos, na meta e negativos
		$contagemSaldos = [
            "positivos" => 0,
            "zerados" => 0,
            "negativos" => 0
        ];

		foreach ($MotoristaTotais as $MotoristaTotal){
            if($MotoristaTotal["saldoFinal"] > "00:00"){
				$contagemSaldos["positivos"]++;
			}elseif($MotoristaTotal["saldoFinal"] < "00:00"){
				$contagemSaldos["negativos"]++;
			}else{
                $contagemSaldos["zerados"]++;
            }
		}

        $percentuaisSaldos = [
            "positivos" => number_format(($contagemSaldos["zerados"]/count($MotoristaTotais))*100, 2),
            "zerados" => number_format(($contagemSaldos["negativos"]/count($MotoristaTotais))*100, 2),
            "negativos" => number_format(($contagemSaldos["positivos"]/count($MotoristaTotais))*100, 2)
        ];

		include "painel_saldo_html.php";
	}

    function index(){
        global $totalResumo, $CONTEX;

        cabecalho("Relatorio Geral de saldo");

        $extraCampoData = "";
        if (empty($_POST["busca_dataInicio"])){
            $_POST["busca_dataInicio"] = date("Y-m-01");
        }
        if (empty($_POST["busca_dataFim"])){
            $_POST["busca_dataFim"] = date("Y-m-d");
        }

        // $texto = "<div style=""><b>Periodo da Busca:</b> $monthName de $year</div>";
        //position: absolute; top: 101px; left: 420px;


        $c = [
            combo_net("Empresa:", "empresa", ($_POST["empresa"]?? ""), 4, "empresa", ""),
            campo_data("Data Início", "busca_dataInicio", ($_POST["busca_dataInicio"]?? ""), 2, $extraCampoData),
            campo_data("Data Fim", "busca_dataFim", ($_POST["busca_dataFim"]?? ""), 2,$extraCampoData)
            // $texto,
        ];
        
        $buttons = [
            botao("Buscar", "index", "", "", "", "","btn btn-info"),
            botao("Criar Relatório", "criar_relatorio_saldo", "busca_dataInicio,busca_dataFim", $_POST["busca_dataInicio"].",".$_POST["busca_dataFim"], "","btn btn-info"),
            "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>",
            (!empty($_POST["empresa"]))? "<button class='btn default' type='button' onclick='setAndSubmit(\"\")'>Voltar</button>": ""
        ];

        
        abre_form("Filtro de Busca");
        linha_form($c);
        fecha_form($buttons);
        
        if (!empty($_POST["acao"]) && !empty($_POST["empresa"]) && !empty($_POST["busca_dataInicio"]) && !empty($_POST["busca_dataFim"])){
            $idEmpresa = $_POST["empresa"];
            $aEmpresa = mysqli_fetch_all(query("SELECT empr_tx_logo FROM empresa WHERE empr_tx_Ehmatriz = 'sim' AND empr_nb_id = $idEmpresa"), MYSQLI_ASSOC);
            empresa($aEmpresa,$idEmpresa);
        }else{
            $aEmpresa = mysqli_fetch_all(query("SELECT empr_tx_logo FROM empresa WHERE empr_tx_Ehmatriz = 'sim'"), MYSQLI_ASSOC);
            include_once "painel_empresas.php";
        }
        
        echo 
            "<style>
                @media print{
                    body{
                        margin: 1cm;
                        margin-right: 0cm; /* Ajuste o valor conforme necessário para afastar do lado direito */
                        transform: scale(1.0);
                        transform-origin: top left;
                    }
                
                    @page{
                        size: A4 landscape;
                        margin: 1cm;
                    }
                    #tituloRelatorio{
                        /*font-size: 2px !important;*/
                        /*padding-left: 200px;*/
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: -50px !important;
                    }
                    body > div.scroll-to-top{
                        display: none !important;
                    }
                    body > div.page-container > div > div.page-content > div > div > div > div > div:nth-child(3){
                        display: none;
                    }
                    .portlet-body.form .table-responsive{
                        overflow-x: visible !important;
                        margin-left: -50px !important;
                    }
                    #pdf2htmldiv > div{
                        padding: 88px 20px 15px !important;
                    }
                    /* .portlet.light>.portlet-title{
                        border-bottom: none;
                        margin-bottom: 0px;
                    }*/
                    body > div.page-container > div > div.page-content > div > div > div > div > div:nth-child(7){
                        display: none !important;
                    }
                    .caption{
                        padding-top: 0px;
                        margin-left: -50px !important;
                        padding-bottom: 0px;
                    }
                    .emissao{
                        text-align: left;
                        padding-left: 650px !important;
                        position: absolute;
                    }
                    .porcentagenEndo{
                        box-shadow: 0 0 0 1000px #66b3ff inset !important;
                    }
                    .porcentagenNaEndo{
                        box-shadow: 0 0 0 1000px #ff471a inset !important;
                    }
                    .porcentagenEndoPc{
                        box-shadow: 0 0 0 1000px #ffff66 inset !important;
                    }
                    thead tr.totais th{
                        box-shadow: 0 0 0 1000px #ffe699 inset !important; /* Cor para impressão */
                    }
                    thead tr.titulos th{
                        box-shadow: 0 0 0 1000px #99ccff inset !important; /* Cor para impressão */
                    }
                    .porcentagenMeta{
                        box-shadow: 0 0 0 1000px #66b3ff inset !important;
                    }
                    .porcentagenPosit{
                        box-shadow: 0 0 0 1000px #00b33c inset !important;
                    }
                    .porcentagenNega{
                        box-shadow: 0 0 0 1000px #ff471a inset !important;
                    }
                }

                table thead tr th:nth-child(3),
                table thead tr th:nth-child(7),
                table thead tr th:nth-child(11),
                table td:nth-child(3),
                table td:nth-child(7),
                table td:nth-child(11){
                    border-right: 3px solid #d8e4ef !important;
                }
                .th-align{
                    text-align: center; /* Define o alinhamento horizontal desejado, pode ser center, left ou right */
                    vertical-align: middle !important; /* Define o alinhamento vertical desejado, pode ser top, middle ou bottom */
                }
                .emissao{
                    text-align: left;
                    padding-left: 63%;
                    position: absolute;
                }
            </style>

            <form name='myForm' method='post' action='".htmlspecialchars($_SERVER["PHP_SELF"])."'>
                <input type='hidden' name='empresa' id='empresa'>
                <input type='hidden' name='busca_data' id='busca_data'>
            </form>

            <script>
                function imprimir(){
                    // Abrir a caixa de diálogo de impressão
                    window.print();
                }
                function setAndSubmit(empresa){
                    document.myForm.empresa.value = empresa;
                    document.myForm.busca_data.value = document.getElementById('busca_data').value;
                    document.myForm.submit();
                }
            </script>"
        ;
        
        rodape();
    }