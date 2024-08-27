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
            return [0];
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
            $linha .= "+'<td style=\"cursor: pointer;\" onclick=setAndSubmit('+row.empresaId+')>'+row.empr_tx_nome+'</td>'
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
                document.getElementsByClassName('porcentagemMeta')[0].getElementsByTagName('td')[1].innerHTML = saldos.quant.meta;
                document.getElementsByClassName('porcentagemPosi')[0].getElementsByTagName('td')[1].innerHTML = saldos.quant.positivos;
                document.getElementsByClassName('porcentagemNega')[0].getElementsByTagName('td')[1].innerHTML = saldos.quant.negativos;
                document.getElementsByClassName('porcentagemMeta')[0].getElementsByTagName('td')[2].innerHTML = saldos.porcentagens.meta*100+'%';
                document.getElementsByClassName('porcentagemPosi')[0].getElementsByTagName('td')[2].innerHTML = saldos.porcentagens.positivos*100+'%';
                document.getElementsByClassName('porcentagemNega')[0].getElementsByTagName('td')[2].innerHTML = saldos.porcentagens.negativos*100+'%';

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

        foreach($empresas as $empresa){
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
                    // }
                    
                    
                    $diaPonto["he50"]       = !empty($diaPonto["he50"])? $diaPonto["he50"]: "00:00";
                    $diaPonto["he100"]      = !empty($diaPonto["he100"])? $diaPonto["he100"]: "00:00";
                    
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

            file_put_contents($path."/empresa_".$empresa["empr_nb_id"].".json", json_encode($empresa));
        }

        if(empty($_POST["empresa"])){
            $path = "./arquivos/saldos";
            if(!is_dir($path)){
                mkdir($path,0755,true);
            }
            $totaisEmpresas["dataInicio"] = $periodoInicio;
            $totaisEmpresas["dataFim"] = $periodoFim;
            file_put_contents($path."/".$nomeArquivo, json_encode($totaisEmpresas));
        }
        
        return;

    }

    function index(){
        global $totalResumo, $CONTEX;

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


        if(!empty($_POST["empresa"])){
            //Painel dos saldos dos motoristas de uma empresa específica
            $aEmpresa = mysqli_fetch_assoc(query(
                "SELECT * FROM empresa"
                ." WHERE empr_tx_status = 'ativo'"
                    ." AND empr_tx_Ehmatriz = 'sim'"
                    ." AND empr_nb_id = ".$_POST["empresa"]
                ." LIMIT 1;"
            ));

            $nomeTitulos = [
                "motorista"         => "Matrícula",
                "empresaNome"       => "Nome",
                "status"            => "Status",
                "jornadaPrevista"   => "Jornada Prevista",
                "jornadaEfetiva"    => "Jornada Efetiva",
                "HESemanal"         => "H.E. Semanal",
                "HESabado"          => "H.E. Sábado",
                "adicionalNoturno"  => "Adicional Noturno",
                "esperaIndenizada"  => "Espera Indenizada",
                "saldoAnterior"     => "Saldo Anterior",
                "saldoPeriodo"      => "Saldo Período",
                "saldoFinal"        => "Saldo Final",    
            ];
            
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
                $periodoRelatorio["dataInicio"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataInicio"])->format("d/m");
                $periodoRelatorio["dataFim"] = DateTime::createFromFormat("Y-m-d", $periodoRelatorio["dataFim"])->format("d/m");
                

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

                $contagemSaldos = [
                    "positivos" => 0,
                    "meta" => 0,
                    "negativos" => 0
                ];
                foreach($motoristas as $saldosMotorista){
                    if($saldosMotorista["saldoFinal"] === "00:00"){
                        $contagemSaldos["meta"]++;
                    }elseif($saldosMotorista["saldoFinal"][0] == "-"){
                        $contagemSaldos["negativos"]++;
                    }else{
                        $contagemSaldos["positivos"]++;
                    }
                }
                [$performance["positivos"], $performance["meta"], $performance["negativos"]] = calcPercs(array_values($contagemSaldos));
            }else{
                $encontrado = false;
            }
        }else{
            //Painel geral das empresas
            $empresas = [];
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
            $aEmpresa = mysqli_fetch_all(query(
                "SELECT empr_tx_logo FROM empresa"
                ." WHERE empr_tx_status = 'ativo'"
                ." AND empr_tx_Ehmatriz = 'sim';"
            ), MYSQLI_ASSOC);
            
            if(is_dir($path) && file_exists($path."/"."empresas.json")){
                $arquivoGeral = $path."/empresas.json";
                $dataEmissao = date("d/m/Y H:i", filemtime($arquivoGeral)); //Utilizado no HTML.
                $arquivoGeral = json_decode(file_get_contents($arquivoGeral), true);

                $pastaSaldos = dir($path);
                while($arquivo = $pastaSaldos->read()){
                    if(!in_array($arquivo, [".", ".."]) && is_bool(strpos($arquivo, "empresas"))){
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

                
                $contagemSaldos = [
                    "positivos" => 0,
                    "meta" => 0,
                    "negativos" => 0
                ];
                
                foreach($empresas as $empresa){
                    if($empresa["totais"]["saldoFinal"] === "00:00"){
                        $contagemSaldos["meta"]++;
                    }elseif($empresa["totais"]["saldoFinal"][0] == "-"){
                        $contagemSaldos["negativos"]++;
                    }else{
                        $contagemSaldos["positivos"]++;
                    }
                }
                [$performance["positivos"], $performance["meta"], $performance["negativos"]] = calcPercs(array_values($contagemSaldos));
            }else{
                $encontrado = false;
            }
        }
        
        
        if($encontrado){
            echo 
                "<script>
                    var saldos = {
                        'quant': {
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
            include_once "painel_saldo_html.php";

            if(!empty($_POST["empresa"])){
                $nomeTitulos = [
                    "motorista"         => "Matricula",
                    "nome"              => "Unidade -  ".$aEmpresa["empr_tx_nome"]."",
                    "status"            => "Status Endosso",
                    "jornadaPrevista"   => "Jornada Prevista",
                    "jornadaEfetiva"    => "Jornada Efetiva",
                    "HESemanal"         => "H.E. Semanal",
                    "HESabado"          => "H.E. Sábado",
                    "adicionalNoturno"  => "Adicional Noturno",
                    "esperaIndenizada"  => "ESPERA INDENIZADA",
                    "saldoAnterior"     => "Saldo Anterior",
                    "saldoPeriodo"      => "Saldo Periodo",
                    "saldoFinal"        => "Saldo Final",    
                ];
                $valorTotais = [
                    "",
                    $totais["nome"],
                    ""
                ];
            }else{
                $nomeTitulos = [
                    "motorista"         => "Todos os CNPJ",
                    "status"            => "Quant. Motoristas",
                    "jornadaPrevista"   => "Jornada Prevista",
                    "jornadaEfetiva"    => "Jornada Efetiva",
                    "HESemanal"         => "H.E. Semanal",
                    "HESabado"          => "H.E. Sábado",
                    "adicionalNoturno"  => "Adicional Noturno",
                    "esperaIndenizada"  => "ESPERA INDENIZADA",
                    "saldoAnterior"     => "Saldo Anterior",
                    "saldoPeriodo"      => "Saldo Periodo",
                    "saldoFinal"        => "Saldo Final",    
                ];
                $valorTotais = [
                    "",
                    ""
                ];
            }

            $valorTotais = array_merge($valorTotais, 
                [
                    $totais["jornadaPrevista"],
                    $totais["jornadaEfetiva"],
                    $totais["HESemanal"],
                    $totais["HESabado"],
                    $totais["adicionalNoturno"],
                    $totais["esperaIndenizada"],
                    $totais["saldoAnterior"],
                    $totais["saldoPeriodo"],
                    $totais["saldoFinal"]
                ]
            );
            echo "<div class='script'><script>";
            for($f = 0; $f < sizeof($valorTotais); $f++){
                echo "document.getElementsByClassName('totais')[0].getElementsByTagName('th')[".($f)."].innerHTML = '".$valorTotais[$f]."';\n";
            }
            $f = 0;
            foreach($nomeTitulos as $key => $value){
                echo "document.getElementsByClassName('titulos')[0].getElementsByTagName('th')[".$f."].setAttribute('data-column', '".$key."');\n";
                echo "document.getElementsByClassName('titulos')[0].getElementsByTagName('th')[".$f++."].innerHTML = '".$value."';\n";
            }
            echo 
                "document.getElementsByClassName('script')[0].innerHTML = '';
                </script></div>"
            ;
        }else{
            echo "<script>alert('Não Possui dados desse mês')</script>";
        }

        carregarJS($arquivos);
        rodape();
    }