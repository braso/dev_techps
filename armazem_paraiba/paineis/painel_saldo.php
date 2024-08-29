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
        $total = 0;
        foreach($values as $value){
            $total += $value;
        }

        if($total == 0){
            return array_pad([], sizeof($values), 0);
        }
        
        $percentuais = array_pad([], sizeof($values), 0);
        for($f = 0; $f < sizeof($values); $f++){
            $percentuais[$f] = $values[$f]/$total;
        }

        return $percentuais;
    }

    function carregarJS(array $arquivos){

        $linha = "linha = '<tr>'";
        if(!empty($_POST["empresa"])){
            $linha .= "+'<td>'+row.matricula+'</td>'
                    +'<td>'+row.nome+'</td>'
                    +'<td>'+row.statusEndosso+'</td>'
                    +'<td>'+row.jornadaPrevista+'</td>'
                    +'<td>'+row.jornadaEfetiva+'</td>'
                    +'<td>'+row.HESemanal+'</td>'
                    +'<td>'+row.HESabado+'</td>'
                    +'<td>'+row.adicionalNoturno+'</td>'
                    +'<td>'+row.esperaIndenizada+'</td>'
                    +'<td>'+row.saldoAnterior+'</td>'
                    +'<td>'+row.saldoPeriodo+'</td>'
                    +'<td>'+row.saldoFinal+'</td>'
                +'</tr>';";
        }else{
            $linha .= "+'<td style=\"cursor: pointer;\" onclick=setAndSubmit('+row.empr_nb_id+')>'+row.empr_tx_nome+'</td>'
                    +'<td>'+Math.round(row.percEndossado*10000)/100+'%</td>'
                    +'<td>'+row.qtdMotoristas+'</td>'
                    +'<td>'+row.totais.jornadaPrevista+'</td>'
                    +'<td>'+row.totais.jornadaEfetiva+'</td>'
                    +'<td>'+row.totais.HESemanal+'</td>'
                    +'<td>'+row.totais.HESabado+'</td>'
                    +'<td>'+row.totais.adicionalNoturno+'</td>'
                    +'<td>'+row.totais.esperaIndenizada+'</td>'
                    +'<td>'+row.totais.saldoAnterior+'</td>'
                    +'<td>'+row.totais.saldoPeriodo+'</td>'
                    +'<td>'+row.totais.saldoFinal+'</td>'
                +'</tr>';";
        }

        $carregarDados = "";
        foreach($arquivos as $arquivo){
            $carregarDados .= "carregarDados('".$arquivo."');";
        }

        echo 
            "<form name='myForm' method='post' action='".htmlspecialchars($_SERVER["PHP_SELF"])."'>
                <input type='hidden' name='atualizar' id='atualizar'>
                <input type='hidden' name='empresa' id='empresa'>
                <input type='hidden' name='busca_dataInicio' id='busca_dataInicio'>
                <input type='hidden' name='busca_dataFim' id='busca_dataFim'>
            </form>
            <script>
                function setAndSubmit(empresa){
                    document.myForm.empresa.value = empresa;
                    document.myForm.busca_dataInicio.value = document.getElementById('busca_dataInicio').value;
                    document.myForm.busca_dataFim.value = document.getElementById('busca_dataFim').value;
                    document.myForm.submit();
                }

                function atualizarPainel(){
                    document.myForm.empresa.value = document.getElementById('empresa').value;
                    document.myForm.busca_dataInicio.value = document.getElementById('busca_dataInicio').value;
                    document.myForm.busca_dataFim.value = document.getElementById('busca_dataFim').value;
                    document.myForm.atualizar.value = 'atualizar';
                    document.myForm.submit();
                }

                function imprimir(){
                    window.print();
                }
            
                $(document).ready(function(){
                    var tabela = $('#tabela-empresas tbody');

                    function carregarDados(urlArquivo){
                        $.ajax({
                            url: urlArquivo,
                            dataType: 'json',
                            success: function(data){
                                var row = {};
                                $.each(data, function(index, item){
                                    row[index] = item;
                                });
                                console.log(row);
                                if(row.idMotorista != undefined){
                                    delete row.idMotorista;
                                }"
                                .$linha
                                ."tabela.append(linha);
                            },
                            error: function(){
                                console.log('Erro ao carregar os dados.');
                            }
                        });
                    }
                    // Função para ordenar a tabela
                    function ordenarTabela(coluna, ordem){
                        var linhas = tabela.find('tr').get();
                        linhas.sort(function(a, b){
                            var valorA = $(a).children('td').eq(coluna).text().toUpperCase();
                            var valorB = $(b).children('td').eq(coluna).text().toUpperCase();

                            if(valorA < valorB){
                                return ordem === 'asc' ? -1 : 1;
                            }
                            if(valorA > valorB){
                                return ordem === 'asc' ? 1 : -1;
                            }
                            return 0;
                        });
                        $.each(linhas, function(index, row){
                            tabela.append(row);
                        });
                    }

                    // Evento de clique para ordenar a tabela ao clicar no cabeçalho
                    $('#titulos th').click(function(){
                        var coluna = $(this).index();
                        var ordem = $(this).data('order');
                        $('#tabela-empresas th').data('order', 'desc'); // Redefinir ordem de todas as colunas
                        $(this).data('order', ordem === 'desc' ? 'asc' : 'desc');
                        ordenarTabela(coluna, $(this).data('order'));

                        // Ajustar classes para setas de ordenação
                        $('#titulos th').removeClass('sort-asc sort-desc');
                        $(this).addClass($(this).data('order') === 'asc' ? 'sort-asc' : 'sort-desc');
                    });

                    ".$carregarDados."
                });

                // function downloadCSV(){
                //     // Caminho do arquivo CSV no servidor
                //     var filePath = './arquivos/paineis/Painel_Geral.csv' // Substitua pelo caminho do seu arquivo

                //     // Cria um link para download
                //     var link = document.createElement('a');

                //     // Configurações do link
                //     link.setAttribute('href', filePath);
                //     link.setAttribute('download', 'Painel_Geral.csv');

                //     // Adiciona o link ao documento
                //     document.body.appendChild(link);

                //     // Simula um clique no link para iniciar o download
                //     link.click();

                //     // Remove o link
                //     document.body.removeChild(link);
                // }
            </script>"
        ;
    }

    function criar_relatorio_saldo(){
        global $totalResumo;
        $periodoInicio = $_POST["busca_dataInicio"];
        $periodoFim = $_POST["busca_dataFim"];
        $dataInicio = new DateTime($periodoInicio);
        $dataFim = new DateTime($periodoFim);

        $empresas = mysqli_fetch_all(query(
            "SELECT empr_nb_id, empr_tx_nome FROM empresa"
            ." WHERE empr_tx_status = 'ativo'"
                .(!empty($_POST["empresa"])? " AND empr_nb_id = ".$_POST["empresa"]: "")
            ." ORDER BY empr_tx_nome ASC;"
        ),MYSQLI_ASSOC);

        $totaisEmpresas = [
            "jornadaPrevista" => "00:00",
            "jornadaEfetiva" => "00:00",
            "HESemanal" => "00:00",
            "HESabado" => "00:00",
            "adicionalNoturno" => "00:00",
            "esperaIndenizada" => "00:00",
            "saldoAnterior" => "00:00",
            "saldoPeriodo" => "00:00",
            "saldoFinal" => "00:00",
            "qtdMotoristas" => 0
        ];

        echo "<script>";
        foreach($empresas as $empresa){
            echo "console.log('Carregando ".$empresa["empr_tx_nome"]."');";
            $path = "./arquivos/saldos"."/".$empresa["empr_nb_id"];
            if(!file_exists($path."/empresa_".$empresa["empr_nb_id"].".json")){
                if(!is_dir($path)){
                    mkdir($path, 0755, true);
                }
                file_put_contents($path."/empresa_".$empresa["empr_nb_id"].".json", "");
            }

            $motoristas = mysqli_fetch_all(query(
                "SELECT enti_nb_id, enti_tx_nome, enti_tx_matricula, enti_tx_banco FROM entidade"
                ." WHERE enti_tx_status = 'ativo'"
                    ." AND enti_nb_empresa = ".$empresa["empr_nb_id"]
                    ." AND enti_tx_ocupacao IN ('Motorista', 'Ajudante')"
                ." ORDER BY enti_tx_nome ASC;"
            ),MYSQLI_ASSOC);

            $rows = [];
            $statusEndossos = [
                "E" => 0,
                "EP" => 0,
                "N" => 0
            ];
            foreach($motoristas as $motorista){
                echo "console.log('    ".$motorista["enti_tx_nome"]."');";
                $dataInicio = new DateTime($periodoInicio);
                $dataFim = new DateTime($periodoFim);

                //Status Endosso{
                    $endossos = mysqli_fetch_all(query(
                        "SELECT * FROM endosso"
                        ." WHERE endo_tx_status = 'ativo'"
                            ." AND endo_nb_entidade = '".$motorista["enti_nb_id"]."'"
                            ." AND ("
                                ."(endo_tx_de  >= '".$periodoInicio."' AND endo_tx_de  <= '".$periodoFim."')"
                                ."OR (endo_tx_ate >= '".$periodoInicio."' AND endo_tx_ate <= '".$periodoFim."')"
                                ."OR (endo_tx_de  <= '".$periodoInicio."' AND endo_tx_ate >= '".$periodoFim."')"
                            .");"
                    ), MYSQLI_ASSOC);
                    
                    $statusEndosso = "N";
                    if(count($endossos) >= 1){
                        $statusEndosso = "E";
                        if(strtotime($periodoInicio) != strtotime($endossos[0]["endo_tx_de"]) || strtotime($periodoFim) > strtotime($endossos[count($endossos)-1]["endo_tx_ate"])){
                            $statusEndosso .= "P";
                        }
                    }
                    $statusEndossos[$statusEndosso]++;
                //}

                //saldoAnterior{
                    $saldoAnterior = mysqli_fetch_assoc(query(
                        "SELECT endo_tx_saldo FROM endosso"
                        ." WHERE endo_tx_status = 'ativo'"
                            ." AND endo_tx_ate < '".$periodoInicio."'"
                            ." AND endo_tx_matricula = '".$motorista["enti_tx_matricula"]."'"
                        ." ORDER BY endo_tx_ate DESC"
                        ." LIMIT 1;"
                    ));
                    
                    if(!empty($saldoAnterior)){
                        if(!empty($saldoAnterior["endo_tx_saldo"])){
                            $saldoAnterior = $saldoAnterior["endo_tx_saldo"];
                        }elseif(!empty($motorista["enti_tx_banco"])){
                            $saldoAnterior = $motorista["enti_tx_banco"];
                        }
                        if(strlen($motorista["enti_tx_banco"]) > 5 && $motorista["enti_tx_banco"][0] == "0"){
                            $saldoAnterior = substr($saldoAnterior, 1);
                        }
                    }else{
                        $saldoAnterior = "00:00";
                    }
                //}

                $totaisMot = [
                    "jornadaPrevista" => "00:00",
                    "jornadaEfetiva" => "00:00",
                    "HESemanal" => "00:00",
                    "HESabado" => "00:00",
                    "adicionalNoturno" => "00:00",
                    "esperaIndenizada" => "00:00",
                    "saldoPeriodo" => "00:00",
                    "saldoFinal" => "00:00"
                ];

                $dia = $dataInicio;
                for(; $dia <= $dataFim; $dia->modify("+1 day")){
                    $diaPonto = diaDetalhePonto($motorista["enti_tx_matricula"], $dia->format("Y-m-d"));

                    //Formatando informações{
                        foreach(array_keys($diaPonto) as $f){
                            if(in_array($f, ["data", "diaSemana"])){
                                continue;
                            }
                            if(strlen($diaPonto[$f]) > 5){
                                $diaPonto[$f] = preg_replace("/.*&nbsp;/", "", $diaPonto[$f]);
                                if(preg_match_all("/(-?\d{2,4}:\d{2})/", $diaPonto[$f], $matches)){
                                    $diaPonto[$f] = array_pop($matches[1]);
                                }else{
                                    $diaPonto[$f] = "00:00";
                                }
                            }
                        }
                    //}
                    
                    
                    $diaPonto["he50"]              = !empty($diaPonto["he50"])? $diaPonto["he50"]: "00:00";
                    $diaPonto["he100"]             = !empty($diaPonto["he100"])? $diaPonto["he100"]: "00:00";
                    
                    $totaisMot["jornadaPrevista"]  = somarHorarios([$totaisMot["jornadaPrevista"],  $diaPonto["jornadaPrevista"]]);
                    $totaisMot["jornadaEfetiva"]   = somarHorarios([$totaisMot["jornadaEfetiva"],   $diaPonto["diffJornadaEfetiva"]]);
                    $totaisMot["HESemanal"]        = somarHorarios([$totaisMot["HESemanal"],        $diaPonto["he50"]]);
                    $totaisMot["HESabado"]         = somarHorarios([$totaisMot["HESabado"],         $diaPonto["he100"]]);
                    $totaisMot["adicionalNoturno"] = somarHorarios([$totaisMot["adicionalNoturno"], $diaPonto["adicionalNoturno"]]);
                    $totaisMot["esperaIndenizada"] = somarHorarios([$totaisMot["esperaIndenizada"], $diaPonto["esperaIndenizada"]]);
                    $totaisMot["saldoPeriodo"]     = somarHorarios([$totaisMot["saldoPeriodo"],     $diaPonto["diffSaldo"]]);
                    $totaisMot["saldoFinal"]       = somarHorarios([$saldoAnterior,                 $totaisMot["saldoPeriodo"]]);
                }

                $row = [
                    "idMotorista" => $motorista["enti_nb_id"],
                    "matricula" => $motorista["enti_tx_matricula"],
                    "nome" => $motorista["enti_tx_nome"],
                    "statusEndosso" => $statusEndosso,
                    "jornadaPrevista" => $totaisMot["jornadaPrevista"],
                    "jornadaEfetiva" => $totaisMot["jornadaEfetiva"],
                    "HESemanal" => $totaisMot["HESemanal"],
                    "HESabado" => $totaisMot["HESabado"],
                    "adicionalNoturno" => $totaisMot["adicionalNoturno"],
                    "esperaIndenizada" => $totaisMot["esperaIndenizada"],
                    "saldoAnterior" => $saldoAnterior,
                    "saldoPeriodo" => $totaisMot["saldoPeriodo"],
                    "saldoFinal" => $totaisMot["saldoFinal"]
                ];
                $nomeArquivo = $motorista["enti_tx_matricula"].".json";
                file_put_contents($path."/".$nomeArquivo, json_encode($row, JSON_UNESCAPED_UNICODE));

                $rows[] = $row;
            }
            echo "console.log('Concluindo...');";
            

            $totaisEmpr = [
                "jornadaPrevista" => "00:00",
                "jornadaEfetiva" => "00:00",
                "HESemanal" => "00:00",
                "HESabado" => "00:00",
                "adicionalNoturno" => "00:00",
                "esperaIndenizada" => "00:00",
                "saldoAnterior" => "00:00",
                "saldoPeriodo" => "00:00",
                "saldoFinal" => "00:00"
            ];

            foreach($rows as $row){
                $totaisEmpr["jornadaPrevista"]  = operarHorarios([$totaisEmpr["jornadaPrevista"], $row["jornadaPrevista"]], "+");
                $totaisEmpr["jornadaEfetiva"]   = operarHorarios([$totaisEmpr["jornadaEfetiva"], $row["jornadaEfetiva"]], "+");
                $totaisEmpr["HESemanal"]        = operarHorarios([$totaisEmpr["HESemanal"], $row["HESemanal"]], "+");
                $totaisEmpr["HESabado"]         = operarHorarios([$totaisEmpr["HESabado"], $row["HESabado"]], "+");
                $totaisEmpr["adicionalNoturno"] = operarHorarios([$totaisEmpr["adicionalNoturno"], $row["adicionalNoturno"]], "+");
                $totaisEmpr["esperaIndenizada"] = operarHorarios([$totaisEmpr["esperaIndenizada"], $row["esperaIndenizada"]], "+");
                $totaisEmpr["saldoAnterior"]    = operarHorarios([$totaisEmpr["saldoAnterior"], $row["saldoAnterior"]], "+");
                $totaisEmpr["saldoPeriodo"]     = operarHorarios([$totaisEmpr["saldoPeriodo"], $row["saldoPeriodo"]], "+");
                $totaisEmpr["saldoFinal"]       = operarHorarios([$totaisEmpr["saldoFinal"], $row["saldoFinal"]], "+");
            }

            //Adicionar valores da empresa à soma total das empresas{
                if(empty($_POST["empresa"])){
                    foreach($totaisEmpr as $key => $value){
                        $totaisEmpresas[$key] = operarHorarios([$totaisEmpresas[$key], $value], "+");
                    }
                    $totaisEmpresas["qtdMotoristas"] += count($motoristas);
                }
            //}

            $empresa["totais"] = $totaisEmpr;
            $empresa["qtdMotoristas"] = count($motoristas);
            $empresa["dataInicio"] = $periodoInicio;
            $empresa["dataFim"] = $periodoFim;
            $empresa["percEndossado"] = ($statusEndossos["E"])/array_sum(array_values($statusEndossos));

            file_put_contents($path."/empresa_".$empresa["empr_nb_id"].".json", json_encode($empresa));
        }
        echo "console.log('Finalizando...');";
        echo "</script>";

        if(empty($_POST["empresa"])){
            $path = "./arquivos/saldos";
            if(!is_dir($path)){
                mkdir($path,0755,true);
            }
            $totaisEmpresas["dataInicio"] = $periodoInicio;
            $totaisEmpresas["dataFim"] = $periodoFim;
            file_put_contents($path."/empresas.json", json_encode($totaisEmpresas));
        }
        return;
    }

    function index(){

        if(!empty($_POST["atualizar"])){
            echo "<script>alert('Atualizando os painéis, aguarde um pouco.')</script>";
            ob_flush();
            flush();
            criar_relatorio_saldo();
        }

        cabecalho("Relatorio Geral de saldo");

        $extraCampoData = "";
        if(empty($_POST["busca_dataInicio"])){
            $_POST["busca_dataInicio"] = date("Y-m-01");
        }
        if(empty($_POST["busca_dataFim"])){
            $_POST["busca_dataFim"] = date("Y-m-d");
        }

        // $texto = "<div style=''><b>Periodo da Busca:</b> $monthName de $year</div>";
        //position: absolute; top: 101px; left: 420px;
        $fields = [
            combo_net("Empresa:", "empresa", $_POST["empresa"] ?? "", 4, "empresa", ""),
            campo_data("Data Início", "busca_dataInicio", ($_POST["busca_dataInicio"] ?? ""), 2, $extraCampoData),
            campo_data("Data Fim", "busca_dataFim", ($_POST["busca_dataFim"] ?? ""), 2, $extraCampoData)
            // $texto,
        ];
        $botao_volta = "";
        if(!empty($_POST["empresa"])){
            $botao_volta = "<button class='btn default' type='button' onclick='setAndSubmit(\"\")'>Voltar</button>";
        }
        $botao_imprimir = "<button class='btn default' type='button' onclick='imprimir()'>Imprimir</button>";
        if(!empty($_SESSION["user_tx_nivel"]) && is_int(strpos($_SESSION["user_tx_nivel"], "Administrador"))){
            $botaoAtualizarPainel = "<a class='btn btn-warning' onclick='atualizarPainel()'> Atualizar Painel</a>";
        }
        $buttons = [
            botao("Buscar", "index", "", "", "", "", "btn btn-info"),
            $botao_imprimir,
            $botao_volta,
            $botaoAtualizarPainel
        ];


        abre_form("Filtro de Busca");
        linha_form($fields);
        fecha_form($buttons);

        
        $arquivos = [];
        $dataEmissao = ""; //Utilizado no HTML
        $encontrado = true;
        $path = "./arquivos/saldos";
        $periodoRelatorio = ["dataInicio" => "", "dataFim" => ""];

        $contagemSaldos = [
            "positivos" => 0,
            "meta" => 0,
            "negativos" => 0
        ];
        $contagemEndossos = [
            "E" => 0,
            "EP" => 0,
            "N" => 0
        ];
        $totais = [
            "jornadaPrevista" => "00:00",
            "jornadaEfetiva" => "00:00",
            "HESemanal" => "00:00",
            "HESabado" => "00:00",
            "adicionalNoturno" => "00:00",
            "esperaIndenizada" => "00:00",
            "saldoAnterior" => "00:00",
            "saldoPeriodo" => "00:00",
            "saldoFinal" => "00:00"
        ];

        $periodoRelatorio = [
            "dataInicio" => "1900-01-01",
            "dataFim" => "1900-01-01"
        ];

        if(!empty($_POST["empresa"])){
            //Painel dos saldos dos motoristas de uma empresa específica
            $aEmpresa = mysqli_fetch_assoc(query(
                "SELECT * FROM empresa"
                ." WHERE empr_tx_status = 'ativo'"
                    ." AND empr_nb_id = ".$_POST["empresa"]
                ." LIMIT 1;"
            ));

            
            $path .= "/".$aEmpresa["empr_nb_id"];

            if(is_dir($path)){
                $pastaSaldosEmpresa = dir($path);
                while($arquivo = $pastaSaldosEmpresa->read()){
                    if(!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresa_"))){
                        $arquivos[] = $arquivo;
                    }
                }
                $pastaSaldosEmpresa->close();

                $dataEmissao = date("d/m/Y H:i", filemtime($path."/"."empresa_".$aEmpresa["empr_nb_id"].".json")); //Utilizado no HTML.
                $periodoRelatorio = json_decode(file_get_contents($path."/"."empresa_".$aEmpresa["empr_nb_id"].".json"), true);
                $periodoRelatorio = [
                    "dataInicio" => $periodoRelatorio["dataInicio"],
                    "dataFim" => $periodoRelatorio["dataFim"]
                ];

                $motoristas = [];
                foreach($arquivos as $arquivo){
                    $json = json_decode(file_get_contents($path."/".$arquivo), true);
                    $json["dataAtualizacao"] = date("d/m/Y H:i", filemtime($path."/".$arquivo));
                    foreach($totais as $key => $value){
                        $totais[$key] = operarHorarios([$totais[$key], $json[$key]], "+");
                    }
                    $motoristas[] = $json;
                }
                foreach($arquivos as &$arquivo){
                    $arquivo = $path."/".$arquivo;
                }
                $totais["empresaNome"] = $aEmpresa["empr_tx_nome"];

                foreach($motoristas as $saldosMotorista){
                    $contagemEndossos[$saldosMotorista["statusEndosso"]]++;
                    if($saldosMotorista["saldoFinal"] === "00:00"){
                        $contagemSaldos["meta"]++;
                    }elseif($saldosMotorista["saldoFinal"][0] == "-"){
                        $contagemSaldos["negativos"]++;
                    }else{
                        $contagemSaldos["positivos"]++;
                    }
                }
            }else{
                $encontrado = false;
            }
        }else{
            //Painel geral das empresas
            $empresas = [];
            $aEmpresa = mysqli_fetch_all(query(
                "SELECT empr_tx_logo FROM empresa"
                ." WHERE empr_tx_status = 'ativo'"
                ." AND empr_tx_Ehmatriz = 'sim';"
            ), MYSQLI_ASSOC);//Utilizado no HTML.
            
            if(is_dir($path) && file_exists($path."/"."empresas.json")){
                $arquivoGeral = $path."/empresas.json";
                $dataEmissao = date("d/m/Y H:i", filemtime($arquivoGeral)); //Utilizado no HTML.
                $arquivoGeral = json_decode(file_get_contents($arquivoGeral), true);

                $periodoRelatorio = [
                    "dataInicio" => $arquivoGeral["dataInicio"],
                    "dataFim" => $arquivoGeral["dataFim"]
                ];

                $pastaSaldos = dir($path);
                while($arquivo = $pastaSaldos->read()){
                    if(!empty($arquivo) && !in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresas"))){
                        $arquivo = $path."/".$arquivo."/"."empresa_".$arquivo.".json";
                        $arquivos[] = $arquivo;
                        $json = json_decode(file_get_contents($arquivo), true);
                        foreach($totais as $key => $value){
                            $totais[$key] = operarHorarios([$totais[$key], $json["totais"][$key]], "+");
                        }
                        $empresas[] = $json;
                    }
                }
                $pastaSaldos->close();
                
                foreach($empresas as $empresa){
                    if($empresa["totais"]["saldoFinal"] === "00:00"){
                        $contagemSaldos["meta"]++;
                    }elseif($empresa["totais"]["saldoFinal"][0] == "-"){
                        $contagemSaldos["negativos"]++;
                    }else{
                        $contagemSaldos["positivos"]++;
                    }
                }
            }else{
                $encontrado = false;
            }
        }
        $periodoRelatorio["dataInicio"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataInicio"])->format("d/m");
        $periodoRelatorio["dataFim"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataFim"])->format("d/m");

        [$percEndosso["E"], $percEndosso["EP"], $percEndosso["N"]] = calcPercs(array_values($contagemEndossos));
        [$performance["positivos"], $performance["meta"], $performance["negativos"]] = calcPercs(array_values($contagemSaldos));

        echo 
            "<script>
                var endossos = {
                    'totais': {
                        'E': ".$contagemEndossos["E"].",
                        'EP': ".$contagemEndossos["EP"].",
                        'N': ".$contagemEndossos["N"]."
                    },
                    'porcentagens': {
                        'E': ".$percEndosso["E"].",
                        'EP': ".$percEndosso["EP"].",
                        'N': ".$percEndosso["N"].",
                    }
                }
                var saldos = {
                    'totais': {
                        'meta': ".$contagemSaldos["meta"].",
                        'positivos': ".$contagemSaldos["positivos"].",
                        'negativos': ".$contagemSaldos["negativos"].",
                    },
                    'porcentagens': {
                        'meta': ".$performance["meta"].",
                        'positivos': ".$performance["positivos"].",
                        'negativos': ".$performance["negativos"].",
                    }
                };
            </script>"
        ;
        if($encontrado){
            $rowTotais = "<tr class='totais'>";
            $rowTitulos = "<tr id='titulos' class='titulos'>";

            if(!empty($_POST["empresa"])){
                $rowTotais .= 
                    "<th colspan='2'>".$totais["empresaNome"]."</th>"
                    ."<th colspan='1'></th>"
                    ."<th colspan='1'>".$totais["jornadaPrevista"]."</th>"
                    ."<th colspan='1'>".$totais["jornadaEfetiva"]."</th>"
                    ."<th colspan='1'>".$totais["HESemanal"]."</th>"
                    ."<th colspan='1'>".$totais["HESabado"]."</th>"
                    ."<th colspan='1'>".$totais["adicionalNoturno"]."</th>"
                    ."<th colspan='1'>".$totais["esperaIndenizada"]."</th>"
                    ."<th colspan='1'>".$totais["saldoAnterior"]."</th>"
                    ."<th colspan='1'>".$totais["saldoPeriodo"]."</th>"
                    ."<th colspan='1'>".$totais["saldoFinal"]."</th>";
                ;

                $rowTitulos .= 
                    "<th class='matricula'>Matrícula</th>"
                    ."<th class='nome'>Nome</th>"
                    ."<th class='status'>Status Endosso</th>"
                    ."<th class='jornadaPrevista'>Jornada Prevista</th>"
                    ."<th class='jornadaEfetiva'>Jornada Efetiva</th>"
                    ."<th class='HESemanal'>H.E. Semanal</th>"
                    ."<th class='HESabado'>H.E. Sábado</th>"
                    ."<th class='adicionalNoturno'>Adicional Noturno</th>"
                    ."<th class='esperaIndenizada'>Espera Indenizada</th>"
                    ."<th class='saldoAnterior'>Saldo Anterior</th>"
                    ."<th class='saldoPeriodo'>Saldo Período</th>"
                    ."<th class='saldoFinal'>Saldo Final</th>"
                ;
            }else{
                $rowTotais .= 
                    "<th colspan='1'></th>"
                    ."<th colspan='1'></th>"
                    ."<th colspan='1'></th>"
                    ."<th colspan='1'>".$totais["jornadaPrevista"]."</th>"
                    ."<th colspan='1'>".$totais["jornadaEfetiva"]."</th>"
                    ."<th colspan='1'> ".(($totais["HESemanal"] == "00:00")? '': $totais["HESemanal"])."</th>"
                    ."<th colspan='1'> ".(($totais["HESabado"] == "00:00")? '': $totais["HESabado"])."</th>"
                    ."<th colspan='1'> ".(($totais["adicionalNoturno"] == "00:00")? '': $totais["adicionalNoturno"])."</th>"
                    ."<th colspan='1'> ".(($totais["esperaIndenizada"] == "00:00")? '': $totais["esperaIndenizada"])."</th>"
                    ."<th colspan='1'> ".(($totais["saldoAnterior"] == "00:00")? '': $totais["saldoAnterior"])."</th>"
                    ."<th colspan='1'> ".(($totais["saldoPeriodo"] == "00:00")? '': $totais["saldoPeriodo"])."</th>"
                    ."<th colspan='1'> ".(($totais["saldoFinal"] == "00:00")? '': $totais["saldoFinal"])."</th>"
                ;

                $rowTitulos .= 
                    "<th data-column='nome' data-order='asc'>Nome da Empresa/Filial</th>"
                    ."<th data-column='percEndossados' data-order='asc'>% Endossados</th>"
                    ."<th data-column='qtdMotoristas' data-order='asc'>Qtd. Motoristas</th>"
                    ."<th data-column='jornadaPrevista' data-order='asc'>Jornada Prevista</th>"
                    ."<th data-column='JornadaEfetiva' data-order='asc'>Jornada Efetiva</th>"
                    ."<th data-column='HESemanal' data-order='asc'>HE 50%</th>"
                    ."<th data-column='HESabado' data-order='asc'>HE 100%</th>"
                    ."<th data-column='adicionalNoturno' data-order='asc'>Adicional Noturno</th>"
                    ."<th data-column='esperaIndenizada' data-order='asc'>Espera Indenizada</th>"
                    ."<th data-column='saldoAnterior' data-order='asc'>Saldo Anterior</th>"
                    ."<th data-column='saldoPeriodo' data-order='asc'>Saldo Periodo</th>"
                    ."<th data-column='saldoFinal' data-order='asc'>Saldo Final</th>"
                ;
            }
            $rowTotais .= "</tr>";
            $rowTitulos .= "</tr>";

            include_once "painel_saldo_html.php";

            echo "<div class='script'>";
            echo "<script>";
            echo ((!empty($_POST["empresa"]))? "document.getElementById('tabela1').style.display = 'table';": "");
            echo 
                "console.log(endossos);
                document.getElementsByClassName('porcentagemEndo')[0].getElementsByTagName('td')[1].innerHTML = endossos.totais.E;
                document.getElementsByClassName('porcentagemEndoPc')[0].getElementsByTagName('td')[1].innerHTML = endossos.totais.EP;
                document.getElementsByClassName('porcentagemNaEndo')[0].getElementsByTagName('td')[1].innerHTML = endossos.totais.N;
                document.getElementsByClassName('porcentagemEndo')[0].getElementsByTagName('td')[2].innerHTML = Math.round(endossos.porcentagens.E*10000)/100+'%';
                document.getElementsByClassName('porcentagemEndoPc')[0].getElementsByTagName('td')[2].innerHTML = Math.round(endossos.porcentagens.EP*10000)/100+'%';
                document.getElementsByClassName('porcentagemNaEndo')[0].getElementsByTagName('td')[2].innerHTML = Math.round(endossos.porcentagens.N*10000)/100+'%';
                
                document.getElementsByClassName('porcentagemPosi')[0].getElementsByTagName('td')[1].innerHTML = saldos.totais.positivos;
                document.getElementsByClassName('porcentagemMeta')[0].getElementsByTagName('td')[1].innerHTML = saldos.totais.meta;
                document.getElementsByClassName('porcentagemNega')[0].getElementsByTagName('td')[1].innerHTML = saldos.totais.negativos;
                document.getElementsByClassName('porcentagemPosi')[0].getElementsByTagName('td')[2].innerHTML = Math.round(saldos.porcentagens.positivos*10000)/100+'%';
                document.getElementsByClassName('porcentagemMeta')[0].getElementsByTagName('td')[2].innerHTML = Math.round(saldos.porcentagens.meta*10000)/100+'%';
                document.getElementsByClassName('porcentagemNega')[0].getElementsByTagName('td')[2].innerHTML = Math.round(saldos.porcentagens.negativos*10000)/100+'%';"
            ;
            echo 
                "document.getElementsByClassName('script')[0].innerHTML = '';
                </script>"
            ;
        }else{
            if(!empty($_POST["acao"])){
                echo "<script>alert('Não Possui dados desse mês')</script>";
            }
        }

        echo "</div>";

        
        carregarJS($arquivos);
        rodape();
    }